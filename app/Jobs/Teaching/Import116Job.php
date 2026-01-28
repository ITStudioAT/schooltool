<?php

namespace App\Jobs\Teaching;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\Import116FinishedEvent;
use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\User;
use Carbon\Carbon;
use Spatie\SimpleExcel\SimpleExcelReader;

class Import116Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public $user, public string $path)
    {
        // placeholder for future payload
    }

    public function handle(): void
    {
        $fullPath = storage_path($this->path);

        if (! is_file($fullPath)) {
            broadcast(new Import116FinishedEvent(
                404,
                $this->user->id,
                'Import 116 fehlgeschlagen: Datei nicht gefunden.',
                ['path' => $this->path]
            ));
            return;
        }

        $reader = SimpleExcelReader::create($fullPath);
        $headers = $reader->getHeaders();
        $headerMapping = $this->mapHeaders($headers);

        if ($headerMapping === false) {
            broadcast(new Import116FinishedEvent(
                422,
                $this->user->id,
                'Import 116 fehlgeschlagen: Spaltenüberschriften nicht erkannt.',
                []
            ));
            return;
        }

        $schoolId = $this->user->school_id;
        $now = now();
        $seenCodes = [];
        $created = 0;
        $updated = 0;

        $reader->getRows()->each(function (array $row) use ($headerMapping, $schoolId, $now, &$seenCodes, &$created, &$updated) {
            $mapped = [];
            foreach ($headerMapping as $originalHeader => $field) {
                $mapped[$field] = isset($row[$originalHeader]) ? $this->normalizeCell($row[$originalHeader]) : null;
            }

            $studentCode = $mapped['student_code'] ?? null;
            if (! $studentCode) {
                return;
            }

            $seenCodes[] = $studentCode;

            $data = [
                'school_id' => $schoolId,
                'class' => $mapped['class'] ?? '',
                'student_code' => $studentCode,
                'last_name' => $mapped['last_name'] ?? '',
                'first_name' => $mapped['first_name'] ?? '',
                'sex' => $mapped['sex'] ?? null,
                'birth_date' => $this->parseDate($mapped['birth_date'] ?? null),
                'import_date' => $now,
                'exists_date' => $now,
                'import_user_id' => $this->user->id,
            ];

            $addressType = strtolower((string) ($mapped['address_type'] ?? ''));
            if (in_array($addressType, ['eigen', 'schüler', 'schueler'], true)) {
                $this->setIfPresent($data, 'email', $mapped['email'] ?? null);
                $this->setIfPresent($data, 'phone_1', $mapped['phone_1'] ?? null);
                $this->setIfPresent($data, 'phone_2', $mapped['phone_2'] ?? null);
            } elseif ($addressType === 'vater') {
                $this->setIfPresent($data, 'father_name', $mapped['address_name'] ?? null);
                $this->setIfPresent($data, 'father_email', $mapped['email'] ?? null);
                $this->setIfPresent($data, 'father_phone_1', $mapped['phone_1'] ?? null);
                $this->setIfPresent($data, 'father_phone_2', $mapped['phone_2'] ?? null);
            } elseif ($addressType === 'mutter') {
                $this->setIfPresent($data, 'mother_name', $mapped['address_name'] ?? null);
                $this->setIfPresent($data, 'mother_email', $mapped['email'] ?? null);
                $this->setIfPresent($data, 'mother_phone_1', $mapped['phone_1'] ?? null);
                $this->setIfPresent($data, 'mother_phone_2', $mapped['phone_2'] ?? null);
            }

            $record = Import116::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'student_code' => $studentCode,
                ],
                $data
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            // Link Import116 record with existing User by email and school_id
            if ($record->email) {
                $matchingUser = User::where('email', $record->email)
                    ->where('school_id', $schoolId)
                    ->first();

                if ($matchingUser) {
                    $record->user_id = $matchingUser->id;
                    $record->save();

                    $matchingUser->import116_id = $record->id;
                    $matchingUser->save();
                }
            }
        });

        if (! empty($seenCodes)) {
            Import116::where('school_id', $schoolId)
                ->whereNotIn('student_code', $seenCodes)
                ->update(['exists_date' => null]);
        } else {
            Import116::where('school_id', $schoolId)->update(['exists_date' => null]);
        }

        $schoolTool = SchoolTool::firstOrCreate(['school_id' => $schoolId]);
        $schoolTool->import_166_at = $now;
        $schoolTool->save();

        broadcast(new Import116FinishedEvent(
            200,
            $this->user->id,
            'Import 116 wurde abgeschlossen.',
            ['created' => $created, 'updated' => $updated]
        ));
    }

    private function mapHeaders(array $headers): array|false
    {
        $required = [
            'class' => ['klasse', 'class', 'klasse/bezeichnung'],
            'student_code' => ['schülerkennzahl', 'schuelerkennzahl', 'student_code', 'schueler_kennzahl'],
            'last_name' => ['familienname', 'nachname', 'last_name'],
            'first_name' => ['vorname', 'first_name'],
        ];

        $optional = [
            'email' => ['mailadresse', 'e-mail', 'email'],
            'phone_1' => ['mobiltelefon', 'handy', 'telefonnummer', 'telefonnummer 1', 'tel1'],
            'phone_2' => ['telefonnummer 2', 'tel2', 'telefon 2'],
            'sex' => ['geschlecht', 'sex'],
            'birth_date' => ['geburtsdatum', 'birth_date', 'geburtstag'],
            'address_type' => ['adressart', 'adress-art', 'adresse', 'adresseart'],
            'address_name' => ['name (anschrift)', 'anschrift (name)', 'name', 'anschrift'],
        ];

        $normalized = array_map(fn($h) => trim(mb_strtolower($h)), $headers);
        $mapping = [];

        foreach ($required as $field => $variants) {
            $found = false;
            foreach ($normalized as $index => $name) {
                if (in_array($name, $variants, true)) {
                    $mapping[$headers[$index]] = $field;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        foreach ($optional as $field => $variants) {
            foreach ($normalized as $index => $name) {
                if (in_array($name, $variants, true)) {
                    $mapping[$headers[$index]] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function parseDate(?string $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! $value) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (is_numeric($trimmed)) {
            $seconds = ((int) $trimmed - 25569) * 86400;
            if ($seconds > 0) {
                return Carbon::createFromTimestamp($seconds)->toDateString();
            }
        }

        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    private function setIfPresent(array &$data, string $key, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return;
        }

        $data[$key] = $trimmed;
    }
}
