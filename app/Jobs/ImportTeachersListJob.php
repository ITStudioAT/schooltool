<?php

namespace App\Jobs;

use App\Events\TeachersListImportFinishedEvent;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use Spatie\Permission\Models\Role;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

class ImportTeachersListJob implements ShouldQueue
{
    use Queueable;

    private const STATUS_CACHE_TTL_SECONDS = 7200;

    public int $schoolId;

    public int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(public $user, public string $path)
    {
        $this->schoolId = (int) $user->school_id;
        $this->userId = (int) $user->id;
        $this->onQueue('imports');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("teachers-list-import:{$this->schoolId}"))
                ->expireAfter(3600),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $fullPath = storage_path($this->path);
            [$headers, $rows] = $this->readRowsWithHeaders($fullPath);

            // Validiere die Header und hole die Mapping-Informationen
            $headerMapping = $this->validateAndMapHeaders($headers);

            if ($headerMapping === false) {
                $this->broadcastFailed('Die Überschriften der Datei sind nicht korrekt! (Kurz/Kürzel, Nachname/Familienname, Vorname, Email)');

                return;
            }

            $importedTeachers = $this->mapImportedTeachers($rows, $headerMapping);
            if ($importedTeachers === []) {
                $this->broadcastFailed('Die Datei enthält keine importierbaren Lehrer:innen mit E-Mail-Adresse.');

                return;
            }

            $counts = $this->synchronizeTeachers($importedTeachers);
            $message = 'Die Lehrerliste wurde erfolgreich importiert ('
                .$counts['created'].' neu, '
                .$counts['updated'].' aktualisiert, '
                .$counts['activated'].' bestehend aktiviert, '
                .$counts['inactive'].' inaktiv)';

            self::markFinished($this->schoolId, $this->userId, 200, $message, $counts);

            broadcast(new TeachersListImportFinishedEvent(
                200,
                $this->userId,
                $message,
                $counts,
            ));
        } catch (Throwable $e) {
            report($e);
            $message = str_contains(strtolower($e->getMessage()), 'no readers supporting the given type: xls')
                ? 'Das Dateiformat .xls wird beim Lehrerlisten-Import nicht direkt unterstützt. Bitte als .xlsx speichern und erneut importieren.'
                : 'Die Lehrerliste konnte nicht importiert werden.';
            $this->broadcastFailed($message);
        }
    }

    public function failed(?Throwable $exception): void
    {
        self::markFinished(
            $this->schoolId,
            $this->userId,
            500,
            'Die Lehrerliste konnte nicht importiert werden.',
        );
    }

    public static function statusCacheKey(int $schoolId, int $userId): string
    {
        return "teachers-list-import:status:{$schoolId}:{$userId}";
    }

    public static function markRunning(int $schoolId, int $userId): void
    {
        self::storeStatus($schoolId, $userId, [
            'state' => 'running',
            'status' => null,
            'message' => 'Die Lehrerliste wird importiert.',
            'data' => [],
        ]);
    }

    /** @return array{state: string, status: ?int, message: string, data: array<string, mixed>} */
    public static function status(int $schoolId, int $userId): array
    {
        return Cache::get(self::statusCacheKey($schoolId, $userId), [
            'state' => 'idle',
            'status' => null,
            'message' => '',
            'data' => [],
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function markFinished(int $schoolId, int $userId, int $status, string $message, array $data = []): void
    {
        self::storeStatus($schoolId, $userId, [
            'state' => 'finished',
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /** @param array{state: string, status: ?int, message: string, data: array<string, mixed>} $status */
    private static function storeStatus(int $schoolId, int $userId, array $status): void
    {
        Cache::put(
            self::statusCacheKey($schoolId, $userId),
            $status,
            now()->addSeconds(self::STATUS_CACHE_TTL_SECONDS),
        );
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $headerMapping
     * @return array<string, array{email: string, short: string, last_name: string, first_name: ?string}>
     */
    private function mapImportedTeachers(iterable $rows, array $headerMapping): array
    {
        $importedTeachers = [];

        foreach ($rows as $row) {
            $mappedRow = [];
            foreach ($headerMapping as $originalHeader => $standardHeader) {
                $mappedRow[$standardHeader] = $row[$originalHeader] ?? null;
            }

            $email = trim((string) ($mappedRow['Email'] ?? ''));
            if ($email === '') {
                continue;
            }

            $normalizedEmail = mb_strtolower($email);
            $importedTeachers[$normalizedEmail] = [
                'email' => $normalizedEmail,
                'short' => strtoupper(trim((string) ($mappedRow['Kurz'] ?? ''))),
                'last_name' => trim((string) ($mappedRow['Nachname'] ?? '')),
                'first_name' => trim((string) ($mappedRow['Vorname'] ?? '')) ?: null,
            ];
        }

        return $importedTeachers;
    }

    /**
     * @param  array<string, array{email: string, short: string, last_name: string, first_name: ?string}>  $importedTeachers
     * @return array{created: int, updated: int, deleted: int, activated: int, inactive: int, skipped_existing: int}
     */
    private function synchronizeTeachers(array $importedTeachers): array
    {
        return DB::transaction(function () use ($importedTeachers): array {
            School::query()
                ->whereKey($this->schoolId)
                ->lockForUpdate()
                ->firstOrFail();

            User::teachers($this->schoolId)
                ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['admin', 'super_admin']))
                ->update(['is_active' => false]);

            $registeredUsersByEmail = User::query()
                ->where('school_id', $this->schoolId)
                ->whereNotNull('email')
                ->whereIn(DB::raw('LOWER(TRIM(email))'), array_keys($importedTeachers))
                ->with('roles')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (User $user): string => mb_strtolower(trim((string) $user->email)));

            Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

            $created = 0;
            $updated = 0;
            $activated = 0;
            $skippedExisting = 0;

            foreach ($importedTeachers as $normalizedEmail => $importedTeacher) {
                $registeredUser = $registeredUsersByEmail->get($normalizedEmail);
                if ($registeredUser) {
                    $skippedExisting++;

                    $wasActive = (bool) $registeredUser->is_active;
                    $registeredUser->forceFill([
                        'email' => $importedTeacher['email'],
                        'short' => $importedTeacher['short'],
                        'last_name' => $importedTeacher['last_name'],
                        'first_name' => $importedTeacher['first_name'],
                        'is_active' => true,
                        'students_timetables_teacher_listed' => true,
                    ])->save();
                    $registeredUser->assignRole('teacher');

                    if (! $wasActive) {
                        $activated++;
                    }

                    continue;
                }

                $teacher = Teacher::query()
                    ->where('school_id', $this->schoolId)
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                    ->first() ?? new Teacher(['school_id' => $this->schoolId]);

                $teacher->fill([
                    'email' => $importedTeacher['email'],
                    'short' => $importedTeacher['short'],
                    'last_name' => $importedTeacher['last_name'],
                    'first_name' => $importedTeacher['first_name'],
                    'is_active' => true,
                ])->save();

                $teacher->wasRecentlyCreated ? $created++ : $updated++;
            }

            $inactive = User::teachers($this->schoolId)
                ->where('is_active', false)
                ->count();

            return [
                'created' => $created,
                'updated' => $updated,
                'deleted' => 0,
                'activated' => $activated,
                'inactive' => $inactive,
                'skipped_existing' => $skippedExisting,
            ];
        }, attempts: 3);
    }

    /**
     * @return array{0: array<int, string>, 1: iterable<int, array<string, mixed>>}
     */
    private function readRowsWithHeaders(string $fullPath): array
    {
        try {
            $reader = SimpleExcelReader::create($fullPath);
            if (strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION)) === 'csv') {
                $reader->useDelimiter($this->detectCsvDelimiter($fullPath));
            }

            $headers = $reader->getHeaders();
            $rows = $reader->getRows();

            return [$headers, $rows];
        } catch (UnsupportedTypeException $e) {
            $ext = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));
            if ($ext !== 'xls') {
                throw $e;
            }

            return $this->readLegacyXls($fullPath);
        }
    }

    private function detectCsvDelimiter(string $fullPath): string
    {
        $handle = fopen($fullPath, 'rb');
        if ($handle === false) {
            return ',';
        }

        $headerLine = fgets($handle);
        fclose($handle);

        if (! is_string($headerLine)) {
            return ',';
        }

        $detectedDelimiter = ',';
        $detectedColumnCount = 1;

        foreach ([',', ';', "\t"] as $delimiter) {
            $columnCount = count(str_getcsv($headerLine, $delimiter, '"', ''));
            if ($columnCount <= $detectedColumnCount) {
                continue;
            }

            $detectedDelimiter = $delimiter;
            $detectedColumnCount = $columnCount;
        }

        return $detectedDelimiter;
    }

    /**
     * @return array{0: array<int, string>, 1: iterable<int, array<string, mixed>>}
     */
    private function readLegacyXls(string $fullPath): array
    {
        $reader = SpreadsheetIOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        $worksheetInfo = $reader->listWorksheetInfo($fullPath)[0] ?? null;
        if (! is_array($worksheetInfo) || (int) ($worksheetInfo['totalRows'] ?? 0) < 1) {
            return [[], []];
        }

        $lastColumn = (string) ($worksheetInfo['lastColumnLetter'] ?? 'A');
        $totalRows = (int) ($worksheetInfo['totalRows'] ?? 0);
        $headerReader = SpreadsheetIOFactory::createReaderForFile($fullPath);
        $headerReader->setReadDataOnly(true);
        $headerReader->setReadFilter($this->spreadsheetRowFilter(1, 1));
        $headerSpreadsheet = $headerReader->load($fullPath);
        $headerValues = $headerSpreadsheet->getActiveSheet()
            ->rangeToArray("A1:{$lastColumn}1", null, true, true, false)[0] ?? [];
        $headerSpreadsheet->disconnectWorksheets();
        unset($headerSpreadsheet);

        $headers = array_map(fn ($value) => trim((string) $value), $headerValues);
        $rows = $this->legacyXlsRows($fullPath, $headers, $lastColumn, $totalRows);

        return [$headers, $rows];
    }

    /**
     * @param  array<int, string>  $headers
     * @return iterable<int, array<string, mixed>>
     */
    private function legacyXlsRows(string $fullPath, array $headers, string $lastColumn, int $totalRows): iterable
    {
        $chunkSize = 500;

        for ($startRow = 2; $startRow <= $totalRows; $startRow += $chunkSize) {
            $endRow = min($totalRows, $startRow + $chunkSize - 1);
            $reader = SpreadsheetIOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter($this->spreadsheetRowFilter($startRow, $endRow));
            $spreadsheet = $reader->load($fullPath);
            $rawRows = $spreadsheet->getActiveSheet()
                ->rangeToArray("A{$startRow}:{$lastColumn}{$endRow}", null, true, true, false);

            foreach ($rawRows as $rowValues) {
                $row = [];
                foreach ($headers as $index => $header) {
                    if ($header !== '') {
                        $row[$header] = $rowValues[$index] ?? null;
                    }
                }

                yield $row;
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $rawRows);
        }
    }

    private function spreadsheetRowFilter(int $startRow, int $endRow): IReadFilter
    {
        return new class($startRow, $endRow) implements IReadFilter
        {
            public function __construct(
                private readonly int $startRow,
                private readonly int $endRow,
            ) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= $this->startRow && $row <= $this->endRow;
            }
        };
    }

    private function broadcastFailed(string $message): void
    {
        self::markFinished($this->schoolId, $this->userId, 500, $message);

        broadcast(new TeachersListImportFinishedEvent(
            500,
            $this->userId,
            $message,
            []
        ));
    }

    /**
     * Validiert die Excel-Header und gibt das Mapping zurück
     *
     * @return array|false Array mit Mapping von Original-Header zu Standard-Header, oder false bei Fehler
     */
    protected function validateAndMapHeaders(array $headers): array|false
    {
        $requiredColumns = [
            'Kurz' => ['kurz', 'short', 'kurzbezeichnung', 'kürzel', 'kuerzel'],
            'Nachname' => ['nachname', 'familienname', 'last_name', 'lastname', 'name', 'surname'],
            'Vorname' => ['vorname', 'first_name', 'firstname', 'givenname'],
            'Email' => ['email', 'e-mail', 'mail', 'e_mail'],
        ];

        $normalizedHeaders = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
        $normalizedRequiredColumns = [];
        foreach ($requiredColumns as $standardName => $variations) {
            $normalizedRequiredColumns[$standardName] = array_values(array_unique(array_map(
                fn ($variation) => $this->normalizeHeader((string) $variation),
                $variations
            )));
        }

        $headerMapping = [];
        $matchedStandardColumns = [];
        $usedHeaderIndexes = [];

        // Phase 1: exact header matching against known variants
        foreach ($normalizedRequiredColumns as $standardName => $variations) {
            $exactMatchIndex = $this->findExactHeaderIndex($normalizedHeaders, $variations, $usedHeaderIndexes);
            if ($exactMatchIndex === null) {
                continue;
            }

            $usedHeaderIndexes[] = $exactMatchIndex;
            $matchedStandardColumns[] = $standardName;
            $headerMapping[$headers[$exactMatchIndex]] = $standardName;
        }

        // Phase 2: fuzzy matching for minor typos in remaining required headers
        foreach ($normalizedRequiredColumns as $standardName => $variations) {
            if (in_array($standardName, $matchedStandardColumns, true)) {
                continue;
            }

            $fuzzyMatchIndex = $this->findFuzzyHeaderIndex($normalizedHeaders, $variations, $usedHeaderIndexes);
            if ($fuzzyMatchIndex === null) {
                return false;
            }

            $usedHeaderIndexes[] = $fuzzyMatchIndex;
            $matchedStandardColumns[] = $standardName;
            $headerMapping[$headers[$fuzzyMatchIndex]] = $standardName;
        }

        return $headerMapping;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);

        return preg_replace('/[^a-z0-9]/', '', $normalized) ?? '';
    }

    private function findExactHeaderIndex(array $normalizedHeaders, array $normalizedVariations, array $usedHeaderIndexes): ?int
    {
        foreach ($normalizedHeaders as $index => $normalizedHeader) {
            if ($normalizedHeader === '' || in_array($index, $usedHeaderIndexes, true)) {
                continue;
            }

            if (in_array($normalizedHeader, $normalizedVariations, true)) {
                return $index;
            }
        }

        return null;
    }

    private function findFuzzyHeaderIndex(array $normalizedHeaders, array $normalizedVariations, array $usedHeaderIndexes): ?int
    {
        $bestIndex = null;
        $bestDistance = PHP_INT_MAX;
        $bestSimilarity = 0.0;

        foreach ($normalizedHeaders as $index => $normalizedHeader) {
            if ($normalizedHeader === '' || in_array($index, $usedHeaderIndexes, true)) {
                continue;
            }

            foreach ($normalizedVariations as $variation) {
                if ($variation === '') {
                    continue;
                }

                $distance = levenshtein($normalizedHeader, $variation);
                if (! $this->isAcceptableFuzzyMatch($distance, $normalizedHeader, $variation)) {
                    continue;
                }

                $maxLength = max(strlen($normalizedHeader), strlen($variation));
                $similarity = $maxLength > 0 ? 1 - ($distance / $maxLength) : 0.0;

                if (
                    $distance < $bestDistance
                    || ($distance === $bestDistance && $similarity > $bestSimilarity)
                ) {
                    $bestDistance = $distance;
                    $bestSimilarity = $similarity;
                    $bestIndex = $index;
                }
            }
        }

        return $bestIndex;
    }

    private function isAcceptableFuzzyMatch(int $distance, string $candidate, string $expected): bool
    {
        $maxLength = max(strlen($candidate), strlen($expected));
        if ($maxLength === 0) {
            return false;
        }

        $maxDistance = match (true) {
            $maxLength <= 4 => 1,
            $maxLength <= 8 => 2,
            default => 3,
        };

        if ($distance > $maxDistance) {
            return false;
        }

        $similarity = 1 - ($distance / $maxLength);

        return $similarity >= 0.6;
    }
}
