<?php

namespace App\Services;

use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

class RestaurantSynchronisationService
{
    public const array TABLES = [
        'restaurant_categories' => 'school_id',
        'restaurant_ingredient_icons' => 'school_id',
        'restaurant_foods' => 'school_id',
        'restaurant_menus' => 'school_id',
        'restaurant_eating_times' => 'school_id',
        'restaurant_free_days' => 'school_id',
        'restaurant_menu_plans' => 'school_id',
        'restaurant_food_restaurant_menu' => ['restaurant_menus', 'restaurant_menu_id'],
        'restaurant_food_restaurant_ingredient_icon' => ['restaurant_foods', 'restaurant_food_id'],
        'restaurant_menu_plan_entries' => ['restaurant_menu_plans', 'restaurant_menu_plan_id'],
        'restaurant_menu_plan_entry_eating_times' => ['restaurant_menu_plan_entries', 'restaurant_menu_plan_entry_id'],
        'restaurant_menu_plan_bookings' => 'school_id',
        'restaurant_billings' => 'school_id',
        'restaurant_sepa_mandates' => 'school_id',
    ];

    public const array USER_FIELDS = ['id', 'email', 'first_name', 'last_name', 'phone', 'sex', 'schoolclass',
        'is_active', 'import116_id', 'restaurant_confirmed_at', 'sepa_at', 'restaurant_booking_defaults', 'restaurant_foods_pagination_number'];

    public const array IMPORT_FIELDS = ['id', 'schoolyear_id', 'student_code', 'first_name', 'last_name', 'email', 'class',
        'school_level', 'attendance_year', 'religion', 'sex', 'birth_date', 'phone_1', 'phone_2',
        'mother_name', 'mother_email', 'mother_phone_1', 'mother_phone_2', 'father_name', 'father_email', 'father_phone_1', 'father_phone_2',
        'import_date', 'exists_date', 'user_id'];

    private const array ROLES = ['lunch_admin', 'lunch_user', 'lunch_candidate'];

    private const array REFERENCES = [
        'restaurant_category_id' => 'restaurant_categories', 'restaurant_food_id' => 'restaurant_foods',
        'restaurant_menu_id' => 'restaurant_menus', 'restaurant_ingredient_icon_id' => 'restaurant_ingredient_icons',
        'restaurant_eating_time_id' => 'restaurant_eating_times', 'restaurant_menu_plan_id' => 'restaurant_menu_plans',
        'restaurant_menu_plan_entry_id' => 'restaurant_menu_plan_entries', 'user_id' => 'users',
        'created_by_user_id' => 'users', 'import116_id' => 'import116',
    ];

    public function __construct(private RestaurantLiveSource $source) {}

    protected static function operatingSystemFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    public static function available(): bool
    {
        if (static::operatingSystemFamily() !== 'Windows' || ! app()->environment('local') || config('schooltool.preview.instance')) {
            return false;
        }
        $connection = DB::connection();

        return $connection->getDriverName() === 'mysql'
            && in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            && empty($connection->getConfig('read')) && empty($connection->getConfig('write'))
            && config('filesystems.disks.local.driver') === 'local' && config('filesystems.disks.public.driver') === 'local';
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->hasRole('super_admin'), 403, 'Nur Superadmins dürfen Restaurantdaten synchronisieren.');
        abort_unless(static::available(), 403, 'Die Synchronisation ist ausschließlich in der lokalen Windows-Anwendung verfügbar.');
    }

    public function preview(User $actor): array
    {
        $this->authorize($actor);
        $school = School::query()->findOrFail($actor->school_id);
        $source = $this->source->snapshot($school);
        $local = $this->localState((int) $actor->school_id);
        $plan = $this->plan($source, $local, (int) $actor->school_id);
        $token = Str::random(64);
        Cache::store('file')->put('restaurant-sync:'.$token, Crypt::encryptString(json_encode([
            'actor' => $actor->id, 'school' => $actor->school_id, 'local_hash' => $this->fingerprint($local),
            'snapshot_hash' => $this->fingerprint($source), 'source' => $source,
        ], JSON_THROW_ON_ERROR)), now()->addMinutes(15));

        return ['token' => $token, 'captured_at' => $source['captured_at'], 'expires_in_minutes' => 15,
            'school' => $school->long_name, 'summary' => $plan['summary'], 'files' => count($plan['files']),
            'reused_student_accounts' => $plan['reused_student_accounts'],
            'removed_student_links' => $plan['removed_student_links']];
    }

    public function apply(User $actor, string $token): void
    {
        $this->authorize($actor);
        Cache::store('file')->lock('restaurant-sync-apply', 120)->block(3, function () use ($actor, $token): void {
            $encrypted = Cache::store('file')->get('restaurant-sync:'.$token);
            if (! is_string($encrypted)) {
                throw new RuntimeException('Die Vorschau ist abgelaufen oder wurde bereits übernommen. Bitte erneut prüfen.');
            }
            $preview = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            if ($preview['actor'] !== $actor->id || $preview['school'] !== $actor->school_id
                || ! hash_equals($preview['snapshot_hash'], $this->fingerprint($preview['source']))) {
                throw new RuntimeException('Die Vorschau gehört nicht zu diesem Benutzer und Schulkontext.');
            }
            $createdFiles = [];
            try {
                DB::transaction(function () use ($actor, $preview, &$createdFiles): void {
                    $local = $this->localState((int) $actor->school_id, true);
                    if (! hash_equals($preview['local_hash'], $this->fingerprint($local))) {
                        throw new RuntimeException('Lokale Daten wurden seit der Vorschau geändert. Es wurde nichts übernommen; bitte eine neue Vorschau laden.');
                    }
                    $plan = $this->plan($preview['source'], $local, (int) $actor->school_id);
                    $this->writeFiles($plan['files'], $createdFiles);
                    $this->writeSharedRows($plan, $actor);
                    foreach (array_reverse(array_keys(self::TABLES)) as $table) {
                        $this->scopedQuery($table, self::TABLES[$table], $local['tables'], (int) $actor->school_id)->delete();
                    }
                    foreach ($plan['tables'] as $table => $rows) {
                        foreach (array_chunk($rows, 100) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                    DB::table('school_tools')->where('school_id', $actor->school_id)->update($plan['settings']);
                });
            } catch (\Throwable $exception) {
                foreach ($createdFiles as $file) {
                    $this->assertFileTarget($file['disk'], $file['path']);
                    Storage::disk($file['disk'])->delete($file['path']);
                }
                throw $exception;
            }
            Cache::store('file')->forget('restaurant-sync:'.$token);
        });
    }

    private function scopedQuery(string $table, string|array $scope, array $tables, int $schoolId): Builder
    {
        $query = DB::table($table);

        return is_string($scope) ? $query->where('school_id', $schoolId)
            : $query->whereIn($scope[1], array_column($tables[$scope[0]], 'id'));
    }

    private function localState(int $schoolId, bool $lock = false): array
    {
        $conflicts = [];
        $checks = DB::table('information_schema.TABLE_CONSTRAINTS as tables')
            ->join('information_schema.CHECK_CONSTRAINTS as checks', function ($join): void {
                $join->on('checks.CONSTRAINT_SCHEMA', '=', 'tables.CONSTRAINT_SCHEMA')
                    ->on('checks.CONSTRAINT_NAME', '=', 'tables.CONSTRAINT_NAME');
            })
            ->where('tables.TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('tables.TABLE_NAME', 'restaurant_sepa_mandates')
            ->where('tables.CONSTRAINT_TYPE', 'CHECK')->pluck('checks.CHECK_CLAUSE');
        if ($checks->contains(fn (string $clause): bool => (bool) preg_match('/\Ajson_valid\(`?child_entries`?\)\z/i', preg_replace('/\s+/', '', $clause)))) {
            $conflicts[] = 'Die lokale SEPA-Tabelle enthält noch eine alte JSON-Prüfung für child_entries. Vor der Synchronisation muss die vorbereitete SEPA-Reparaturmigration ausgeführt werden. Es wurde nichts übernommen.';
        }
        $tables = [...array_keys(self::TABLES), 'users', 'import116', 'school_tools', 'model_has_roles'];
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->whereIn('TABLE_NAME', $tables)->pluck('ENGINE');
        if ($engines->count() !== count($tables) || $engines->contains(fn (string $engine): bool => strcasecmp($engine, 'InnoDB') !== 0)) {
            $conflicts[] = 'Die Synchronisation benötigt für alle betroffenen Tabellen transaktionale InnoDB-Tabellen.';
        }
        if (DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())->whereIn('EVENT_OBJECT_TABLE', $tables)->exists()) {
            $conflicts[] = 'Betroffene Tabellen enthalten Datenbank-Trigger. Die Synchronisation ist zum Schutz anderer Daten gesperrt.';
        }
        if ($engines->count() !== count($tables)) {
            $this->assertNoConflicts($conflicts);
        }
        $state = ['tables' => [], 'max_ids' => [], 'users' => [], 'imports' => [], 'schoolyears' => [], 'settings' => []];
        foreach (self::TABLES as $table => $scope) {
            $query = $this->scopedQuery($table, $scope, $state['tables'], $schoolId);
            if ($lock) {
                $query->lockForUpdate();
            }
            $columns = array_values(array_diff(Schema::getColumnListing($table), ['booking_slot_key']));
            $state['tables'][$table] = $query->get($columns)->map(fn (object $row): array => (array) $row)->all();
            if (in_array('id', $columns, true)) {
                $maximum = DB::table($table)->orderByDesc('id');
                if ($lock) {
                    $maximum->lockForUpdate();
                }
                $state['max_ids'][$table] = (int) ($maximum->value('id') ?? 0);
            }
        }
        foreach (['users', 'import116', 'schoolyears', 'school_tools'] as $table) {
            $query = DB::table($table)->where('school_id', $schoolId)->orderBy('id');
            if ($lock) {
                $query->lockForUpdate();
            }
            $rows = $query->get()->map(fn (object $row): array => (array) $row)->all();
            $key = ['users' => 'users', 'import116' => 'imports', 'schoolyears' => 'schoolyears', 'school_tools' => 'settings'][$table];
            $state[$key] = $rows;
            $maximum = DB::table($table)->orderByDesc('id');
            if ($lock) {
                $maximum->lockForUpdate();
            }
            $state['max_ids'][$table] = (int) ($maximum->value('id') ?? 0);
        }
        foreach ($state['tables'] as $table => $rows) {
            foreach ($rows as $row) {
                foreach (self::REFERENCES as $column => $parent) {
                    if (isset($row[$column], $state['tables'][$parent]) && ! in_array($row[$column], array_column($state['tables'][$parent], 'id'), true)) {
                        $conflicts[] = 'Lokale Restaurantdaten enthalten eine Beziehung außerhalb der ausgewählten Schule.';
                    }
                }
            }
        }
        $state['roles'] = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', User::class)->whereIn('model_id', array_column($state['users'], 'id'))
            ->whereIn('roles.name', self::ROLES)->orderBy('model_id')->orderBy('roles.name')->get(['model_id', 'roles.name'])
            ->map(fn (object $row): array => (array) $row)->all();
        // Reject pre-existing cross-school references before deleting any restaurant rows.
        foreach (self::TABLES as $table => $scope) {
            foreach (self::REFERENCES as $column => $parent) {
                if (! isset($state['tables'][$parent]) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $foreign = DB::table($table)->whereIn($column, array_column($state['tables'][$parent], 'id'));
                if (Schema::hasColumn($table, 'school_id')) {
                    $foreign->where('school_id', '!=', $schoolId);
                } elseif (is_array($scope)) {
                    $foreign->whereNotIn($scope[1], array_column($state['tables'][$scope[0]], 'id'));
                } else {
                    continue;
                }
                if ($foreign->exists()) {
                    $conflicts[] = 'Restaurantdaten sind mit einer anderen Schule verknüpft. Die Übernahme wurde gesperrt.';
                }
            }
        }

        $state['conflicts'] = $conflicts;

        return $state;
    }

    private function plan(array $source, array $local, int $schoolId): array
    {
        $school = School::query()->findOrFail($schoolId);
        if (($source['school'] ?? null) !== ['id' => $schoolId, 'short_name' => $school->short_name, 'long_name' => $school->long_name]
            || array_keys($source['tables'] ?? []) !== array_keys(self::TABLES) || $local['settings'] === []) {
            throw new RuntimeException('Schulidentität oder Restaurantumfang stimmt nicht überein.');
        }
        $conflicts = $local['conflicts'] ?? [];
        $maps = [];
        $validTables = [];
        $yearMap = [];
        foreach ($source['schoolyears'] as $year) {
            try {
                $matches = array_values(array_filter($local['schoolyears'], fn (array $row): bool => $row['from'] === $year['from'] && $row['until'] === $year['until']));
                if (count($matches) !== 1) {
                    throw new RuntimeException('Ein benötigtes Schuljahr fehlt lokal oder ist mehrdeutig. Schuljahre werden nicht automatisch verändert.');
                }
                $yearMap[$year['id']] = $matches[0]['id'];
            } catch (RuntimeException|JsonException $exception) {
                $this->collectConflict($conflicts, $exception);
            }
        }
        $importMaximum = $local['max_ids']['import116'];
        foreach ($source['imports'] as $import) {
            try {
                $year = $import['schoolyear_id'] ? ($yearMap[$import['schoolyear_id']] ?? throw new RuntimeException('Schuljahrzuordnung fehlt.')) : null;
                $matches = array_values(array_filter($local['imports'], fn (array $row): bool => $row['student_code'] === $import['student_code'] && $row['schoolyear_id'] === $year));
                if (count($matches) > 1 || trim($import['student_code']) === '') {
                    throw new RuntimeException('Eine Import-116-Schüleridentität ist nicht eindeutig.');
                }
                $maps['import116'][$import['id']] = $matches[0]['id'] ?? ++$importMaximum;
            } catch (RuntimeException|JsonException $exception) {
                $this->collectConflict($conflicts, $exception);
            }
        }
        $userMaximum = $local['max_ids']['users'];
        $reusedStudentAccounts = 0;
        foreach ($source['users'] as $user) {
            try {
                $isLiveTeacher = in_array(mb_strtolower(trim($user['email'])), $source['teacher_emails'] ?? [], true);
                $isLocalTeacher = DB::table('teachers')->where('school_id', $schoolId)->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($user['email']))])->exists();
                if ($isLiveTeacher !== $isLocalTeacher) {
                    $conflicts[] = 'Die Herkunft eines Restaurantbenutzers aus der Lehrerliste unterscheidet sich. Lehrerdaten werden nicht durch die Restaurant-Synchronisation verändert.';
                }
                $matches = array_values(array_filter($local['users'], fn (array $row): bool => mb_strtolower(trim($row['email'])) === mb_strtolower(trim($user['email']))));
                if (count($matches) > 1 || trim($user['email']) === '') {
                    throw new RuntimeException('Eine Benutzeridentität ist nicht eindeutig. Es wurden keine Konten verändert.');
                }
                $placeholder = $matches === [] ? $this->matchingStudentPlaceholder($user, $source, $local, $maps, $schoolId) : null;
                $id = $matches[0]['id'] ?? $placeholder['id'] ?? ++$userMaximum;
                if (in_array($id, $maps['users'] ?? [], true)) {
                    throw new RuntimeException('Mehrere Live-Konten würden demselben lokalen Konto zugeordnet. Die Übernahme ist gesperrt.');
                }
                $maps['users'][$user['id']] = $id;
                $reusedStudentAccounts += $placeholder !== null ? 1 : 0;
            } catch (RuntimeException|JsonException $exception) {
                $this->collectConflict($conflicts, $exception);
            }
        }
        foreach ($source['tables'] as $table => $rows) {
            try {
                $columnDefinitions = Schema::getColumns($table);
                $columns = array_values(array_diff(array_column($columnDefinitions, 'name'), ['booking_slot_key']));
                if ($columns !== $source['columns'][$table]) {
                    throw new RuntimeException('Das Live- und lokale Restaurant-Schema unterscheiden sich. Die Übernahme ist gesperrt.');
                }
                $validTables[$table] = true;
                $owned = array_column($local['tables'][$table], 'id');
                $maximum = max($local['max_ids'][$table] ?? 0, max(array_column($rows, 'id') ?: [0]));
                foreach ($rows as $row) {
                    try {
                        foreach ($columnDefinitions as $column) {
                            if (! $column['nullable'] && ! $column['auto_increment'] && $column['generation'] === null
                                && ($row[$column['name']] ?? null) === null) {
                                throw new RuntimeException('Live-Restaurantdaten enthalten eine leere Pflichtangabe. Der Datensatz muss vor der Übernahme korrigiert werden.');
                            }
                        }
                        if (! in_array('id', $columns, true)) {
                            continue;
                        }
                        if ((int) ($row['id'] ?? 0) <= 0 || (isset($row['school_id']) && (int) $row['school_id'] !== $schoolId)) {
                            throw new RuntimeException('Ein Live-Datensatz liegt außerhalb des geprüften Schulkontexts.');
                        }
                        $collision = DB::table($table)->where('id', $row['id'])->exists() && ! in_array($row['id'], $owned, true);
                        $maps[$table][$row['id']] = $collision ? ++$maximum : $row['id'];
                    } catch (RuntimeException|JsonException $exception) {
                        $this->collectConflict($conflicts, $exception);
                    }
                }
            } catch (RuntimeException|JsonException $exception) {
                $this->collectConflict($conflicts, $exception);
            }
        }
        $encryptedColumns = array_keys(array_filter((new RestaurantSepaMandate)->getCasts(), fn (string $cast): bool => str_starts_with($cast, 'encrypted')));
        if ($source['encrypted'] !== $encryptedColumns) {
            $conflicts[] = 'Die SEPA-Verschlüsselungszuordnung stimmt nicht überein.';
        }
        $plan = ['tables' => [], 'users' => [], 'imports' => [], 'summary' => [], 'files' => [], 'roles' => [],
            'reused_student_accounts' => $reusedStudentAccounts, 'removed_student_links' => 0];
        $fileMap = [];
        foreach ($source['files'] as $file) {
            try {
                $bytes = base64_decode($file['content'], true);
                if (! in_array($file['disk'], ['local', 'public'], true) || $bytes === false || ! hash_equals($file['sha256'], hash('sha256', $bytes))) {
                    throw new RuntimeException('Ein Restaurantbild ist unvollständig oder wurde verändert.');
                }
                $extension = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
                if (! in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                    throw new RuntimeException('Ein Restaurantbild hat ein nicht unterstütztes Dateiformat.');
                }
                $targetPath = "restaurant/synchronisation/{$schoolId}/{$file['sha256']}.{$extension}";
                $this->assertFileTarget($file['disk'], $targetPath);
                $disk = Storage::disk($file['disk']);
                if ($disk->exists($targetPath) && ! hash_equals($file['sha256'], hash('sha256', $disk->get($targetPath)))) {
                    throw new RuntimeException('Ein vorhandenes lokales Bild hat eine unerwartete Prüfsumme.');
                }
                $fileMap[$file['path']] = $targetPath;
                $plan['files'][] = ['disk' => $file['disk'], 'path' => $targetPath, 'content' => $file['content'], 'sha256' => $file['sha256']];
            } catch (RuntimeException|JsonException $exception) {
                $this->collectConflict($conflicts, $exception);
            }
        }
        foreach ($source['tables'] as $table => $rows) {
            if (! isset($validTables[$table])) {
                continue;
            }
            try {
                foreach ($rows as $row) {
                    try {
                        if (isset($row['id'])) {
                            if (! isset($maps[$table][$row['id']])) {
                                continue;
                            }
                            $row['id'] = $maps[$table][$row['id']];
                        }
                        foreach (self::REFERENCES as $column => $parent) {
                            if (isset($row[$column])) {
                                $row[$column] = $maps[$parent][$row[$column]] ?? throw new RuntimeException('Eine benötigte Restaurant-Beziehung fehlt im Live-Snapshot.');
                            }
                        }
                        foreach (['food_image_path', 'image_path'] as $column) {
                            if (! empty($row[$column])) {
                                $row[$column] = $fileMap[$row[$column]] ?? throw new RuntimeException('Ein benötigtes Bild fehlt im Snapshot.');
                            }
                        }
                        if (! empty($row['foods_snapshot'])) {
                            $foods = json_decode($row['foods_snapshot'], true, 512, JSON_THROW_ON_ERROR);
                            foreach ($foods as &$food) {
                                $food = $this->remapFood($food, $fileMap, $maps, array_column($plan['files'], 'content', 'path'));
                            }
                            unset($food);
                            $row['foods_snapshot'] = json_encode($foods, JSON_THROW_ON_ERROR);
                        }
                        if ($table === 'restaurant_billings') {
                            $billing = json_decode($row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
                            foreach ($billing['rows'] ?? [] as $index => $person) {
                                $billing['rows'][$index]['user_id'] = $maps['users'][$person['user_id']] ?? 0;
                            }
                            $row['snapshot'] = json_encode($billing, JSON_THROW_ON_ERROR);
                        }
                        $plan['tables'][$table][] = $row;
                    } catch (RuntimeException|JsonException $exception) {
                        $this->collectConflict($conflicts, $exception);
                    }
                }
                $plan['tables'][$table] ??= [];
                $plan['summary'][] = $this->counts($table, $local['tables'][$table], $plan['tables'][$table], $table === 'restaurant_sepa_mandates' ? $encryptedColumns : []);
            } catch (RuntimeException|JsonException $exception) {
                $this->collectConflict($conflicts, $exception);
            }
        }
        foreach ($source['users'] as $sourceUser) {
            if (! isset($maps['users'][$sourceUser['id']])) {
                continue;
            }
            try {
                $id = $maps['users'][$sourceUser['id']];
                $existing = collect($local['users'])->firstWhere('id', $id);
                $fields = array_intersect_key($sourceUser, array_flip(array_diff(self::USER_FIELDS, ['id', 'email', 'is_active'])));
                $fields['import116_id'] = $fields['import116_id'] ? ($maps['import116'][$fields['import116_id']] ?? throw new RuntimeException('Schülerzuordnung fehlt.')) : null;
                if ($existing && $existing['import116_id'] && $fields['import116_id'] && $existing['import116_id'] !== $fields['import116_id']) {
                    throw new RuntimeException('Ein vorhandenes Benutzerkonto ist mit einem anderen Schüler verknüpft.');
                }
                if ($existing && $existing['import116_id'] && ! $fields['import116_id']) {
                    $plan['removed_student_links']++;
                }
                if ($fields['restaurant_booking_defaults']) {
                    $defaults = json_decode($fields['restaurant_booking_defaults'], true, 512, JSON_THROW_ON_ERROR);
                    foreach ($defaults['recipients'] ?? [] as $index => $recipient) {
                        if (! empty($recipient['import116_id'])) {
                            $defaults['recipients'][$index]['import116_id'] = $maps['import116'][$recipient['import116_id']] ?? throw new RuntimeException('Eine vorgemerkte Schülerzuordnung fehlt.');
                        }
                    }
                    $fields['restaurant_booking_defaults'] = json_encode($defaults, JSON_THROW_ON_ERROR);
                }
                $plan['users'][] = ['id' => $id, 'fields' => $fields, 'existing' => $existing !== null,
                    'email' => $sourceUser['email'], 'is_active' => $sourceUser['is_active']];
                $plan['roles'][$id] = array_values(array_intersect($sourceUser['restaurant_roles'], self::ROLES));
            } catch (RuntimeException|JsonException $exception) {
                $this->collectConflict($conflicts, $exception);
            }
        }
        foreach ($source['imports'] as $import) {
            if (! isset($maps['import116'][$import['id']])) {
                continue;
            }
            try {
                $id = $maps['import116'][$import['id']];
                $existing = collect($local['imports'])->firstWhere('id', $id);
                $fields = array_intersect_key($import, array_flip(array_diff(self::IMPORT_FIELDS, ['id'])));
                $fields['schoolyear_id'] = $import['schoolyear_id'] ? $yearMap[$import['schoolyear_id']] : null;
                $fields['user_id'] = $import['user_id'] ? ($maps['users'][$import['user_id']] ?? throw new RuntimeException('Import-116-Benutzerzuordnung fehlt.')) : ($existing['user_id'] ?? null);
                if ($existing && $existing['user_id'] && $fields['user_id'] && $existing['user_id'] !== $fields['user_id']) {
                    throw new RuntimeException('Ein vorhandener Schüler gehört lokal zu einem anderen Benutzerkonto.');
                }
                $plan['imports'][] = ['id' => $id, 'fields' => $fields, 'existing' => $existing !== null];
            } catch (RuntimeException|JsonException $exception) {
                $this->collectConflict($conflicts, $exception);
            }
        }
        foreach (['users' => 'users', 'imports' => 'import116'] as $key => $table) {
            $current = array_map(fn (array $row): array => array_intersect_key($row, array_flip($key === 'users' ? self::USER_FIELDS : self::IMPORT_FIELDS)), $local[$key]);
            $incoming = array_map(fn (array $row): array => ['id' => $row['id'], ...$row['fields']], $plan[$key]);
            $summary = $this->counts($table, $current, $incoming);
            $summary['removed'] = 0;
            $plan['summary'][] = $summary;
        }
        $plan['settings'] = array_intersect_key($source['settings'], array_flip(array_filter(Schema::getColumnListing('school_tools'), fn (string $column): bool => str_starts_with($column, 'restaurant_'))));
        if (count($plan['settings']) !== count($source['settings']) || $plan['settings'] === []) {
            $conflicts[] = 'Restaurant-Einstellungen können nicht vollständig zugeordnet werden.';
        }
        $plan['summary'][] = ['table' => 'school_tools (Restaurant)', 'added' => 0, 'changed' => $plan['settings'] == array_intersect_key($local['settings'][0], $plan['settings']) ? 0 : 1, 'removed' => 0];
        $oldRoles = array_map(fn (array $row): string => $row['model_id'].':'.$row['name'], $local['roles']);
        $newRoles = [];
        foreach ($plan['roles'] as $id => $roles) {
            foreach ($roles as $role) {
                $newRoles[] = $id.':'.$role;
            }
        }
        $plan['summary'][] = ['table' => 'Restaurant-Rollen', 'added' => count(array_diff($newRoles, $oldRoles)),
            'changed' => 0, 'removed' => count(array_diff($oldRoles, $newRoles))];
        $this->assertNoConflicts($conflicts);

        // Plain SEPA values only exist in the encrypted preview; database rows use the local application key.
        foreach ($plan['tables']['restaurant_sepa_mandates'] as &$row) {
            foreach ($encryptedColumns as $column) {
                if ($row[$column] !== null) {
                    $row[$column] = Crypt::encryptString($row[$column]);
                }
            }
        }
        unset($row);

        return $plan;
    }

    /** @param list<string> $conflicts */
    private function collectConflict(array &$conflicts, RuntimeException|JsonException $exception): void
    {
        if ($exception instanceof QueryException) {
            throw $exception;
        }
        $conflicts[] = match (true) {
            $exception instanceof JsonException => 'Restaurantdaten enthalten ungültige JSON-Inhalte.',
            $exception instanceof DecryptException => 'Lokale SEPA-Daten können mit dem lokalen Anwendungsschlüssel nicht entschlüsselt werden.',
            default => $exception->getMessage(),
        };
    }

    /** @param list<string> $conflicts */
    private function assertNoConflicts(array $conflicts): void
    {
        $conflicts = array_values(array_unique($conflicts));
        if (count($conflicts) === 1) {
            throw new RuntimeException($conflicts[0]);
        }
        if ($conflicts !== []) {
            throw ValidationException::withMessages(['synchronisation' => $conflicts]);
        }
    }

    /**
     * Match only a reciprocal, synthetic student account; preserve its email, password and privileges.
     *
     * @return array<string, mixed>|null
     */
    private function matchingStudentPlaceholder(array $user, array $source, array $local, array $maps, int $schoolId): ?array
    {
        $import = collect($source['imports'])->firstWhere('id', $user['import116_id']);
        if (! $import || $import['user_id'] !== $user['id'] || ! $import['birth_date']) {
            return null;
        }
        $student = collect($local['imports'])->firstWhere('id', $maps['import116'][$import['id']] ?? null);
        $candidate = $student ? collect($local['users'])->firstWhere('id', $student['user_id']) : null;
        $normalize = fn (?string $value): string => mb_strtolower(trim((string) $value));
        $placeholderEmails = $student ? ['import116.'.$student['id'].'@schooltool.noemail',
            'noemail.'.preg_replace('/[^a-z0-9_]/', '_', strtolower($student['student_code'])).'@schooltool.noemail'] : [];
        if (! $candidate || $candidate['school_id'] !== $schoolId || $candidate['import116_id'] !== $student['id']
            || ! in_array($candidate['email'], $placeholderEmails, true)
            || $student['birth_date'] !== $import['birth_date'] || $normalize($import['email']) !== $normalize($user['email'])) {
            return null;
        }
        foreach (['first_name', 'last_name'] as $field) {
            if ($normalize($import[$field]) === '' || $normalize($student[$field]) !== $normalize($import[$field])
                || $normalize($candidate[$field]) !== $normalize($import[$field]) || $normalize($user[$field]) !== $normalize($import[$field])) {
                return null;
            }
        }
        $privileged = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_type', User::class)->where('model_id', $candidate['id'])
            ->whereNotIn('roles.name', [...self::ROLES, 'student'])->exists();

        return $privileged ? null : $candidate;
    }

    private function remapFood(array $food, array $fileMap, array $maps, array $imageContents): array
    {
        if (! empty($food['id']) && isset($maps['restaurant_foods'][$food['id']])) {
            $food['id'] = $maps['restaurant_foods'][$food['id']];
        }
        if (! empty($food['category']['id']) && isset($maps['restaurant_categories'][$food['category']['id']])) {
            $food['category']['id'] = $maps['restaurant_categories'][$food['category']['id']];
        }
        if (! empty($food['food_image_path'])) {
            $food['food_image_path'] = $fileMap[$food['food_image_path']] ?? throw new RuntimeException('Snapshot-Bild fehlt.');
            $food['food_image_url'] = Storage::disk('public')->url($food['food_image_path']);
        }
        foreach ($food['ingredient_icons'] ?? [] as $index => $icon) {
            if (! empty($icon['id']) && isset($maps['restaurant_ingredient_icons'][$icon['id']])) {
                $icon['id'] = $maps['restaurant_ingredient_icons'][$icon['id']];
            }
            if (! empty($icon['image_path'])) {
                $icon['image_path'] = $fileMap[$icon['image_path']] ?? throw new RuntimeException('Snapshot-Symbol fehlt.');
                $icon['image_url'] = str_ends_with(strtolower($icon['image_path']), '.svg')
                    ? 'data:image/svg+xml;base64,'.$imageContents[$icon['image_path']]
                    : Storage::disk('public')->url($icon['image_path']);
            }
            $food['ingredient_icons'][$index] = $icon;
        }

        return $food;
    }

    private function counts(string $table, array $current, array $incoming, array $encrypted = []): array
    {
        $currentById = collect($current)->keyBy('id');
        $added = 0;
        $changed = 0;
        foreach ($incoming as $row) {
            if (! isset($row['id'])) {
                continue;
            }
            $old = $currentById->get($row['id']);
            if ($old === null) {
                $added++;

                continue;
            }
            foreach ($encrypted as $column) {
                if ($old[$column] !== null) {
                    $old[$column] = Crypt::decryptString($old[$column]);
                }
            }
            $old = array_intersect_key($old, $row);
            unset($old['created_at'], $old['updated_at'], $row['created_at'], $row['updated_at']);
            $changed += $old == $row ? 0 : 1;
        }
        if (($incoming && ! isset($incoming[0]['id'])) || ($current && ! isset($current[0]['id']))) {
            $oldKeys = array_map(fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $current);
            $newKeys = array_map(fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $incoming);

            return ['table' => $table, 'added' => count(array_diff($newKeys, $oldKeys)), 'changed' => 0, 'removed' => count(array_diff($oldKeys, $newKeys))];
        }

        return ['table' => $table, 'added' => $added, 'changed' => $changed,
            'removed' => count(array_diff(array_column($current, 'id'), array_column($incoming, 'id')))];
    }

    private function writeSharedRows(array $plan, User $actor): void
    {
        foreach ($plan['users'] as $row) {
            if ($row['existing']) {
                DB::table('users')->where('id', $row['id'])->where('school_id', $actor->school_id)->update($row['fields']);
            } else {
                DB::table('users')->insert(['id' => $row['id'], 'school_id' => $actor->school_id,
                    'email' => $row['email'], 'is_active' => $row['is_active'], 'password' => Hash::make(Str::random(64)),
                    'created_at' => now(), 'updated_at' => now(), ...$row['fields']]);
            }
        }
        foreach ($plan['imports'] as $row) {
            if ($row['existing']) {
                DB::table('import116')->where('id', $row['id'])->where('school_id', $actor->school_id)->update($row['fields']);
            } else {
                DB::table('import116')->insert(['id' => $row['id'], 'school_id' => $actor->school_id, 'import_user_id' => $actor->id,
                    'created_at' => now(), 'updated_at' => now(), ...$row['fields']]);
            }
        }
        foreach (User::query()->where('school_id', $actor->school_id)->get() as $user) {
            $unrelatedRoles = $user->getRoleNames()->diff(self::ROLES)->all();
            $roles = [...$unrelatedRoles, ...($plan['roles'][$user->id] ?? [])];
            if ($user->getRoleNames()->sort()->values()->all() !== collect($roles)->sort()->values()->all()) {
                $user->syncRoles($roles);
            }
        }
    }

    private function writeFiles(array $files, array &$created): void
    {
        foreach ($files as $file) {
            $disk = Storage::disk($file['disk']);
            $this->assertFileTarget($file['disk'], $file['path']);
            if ($disk->exists($file['path'])) {
                if (! hash_equals($file['sha256'], hash('sha256', $disk->get($file['path'])))) {
                    throw new RuntimeException('Ein vorhandenes lokales Bild hat eine unerwartete Prüfsumme.');
                }

                continue;
            }
            if (! $disk->put($file['path'], base64_decode($file['content'], true))) {
                throw new RuntimeException('Ein Restaurantbild konnte nicht lokal gespeichert werden.');
            }
            $created[] = ['disk' => $file['disk'], 'path' => $file['path']];
        }
    }

    private function assertFileTarget(string $disk, string $path): void
    {
        $rootPath = rtrim(Storage::disk($disk)->path(''), '/\\');
        $rootPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rootPath);
        $root = realpath($rootPath);
        $storage = realpath(storage_path());
        if (! $root || ! $storage || strcasecmp($root, $rootPath) !== 0
            || ! str_starts_with(strtolower($root), strtolower($storage).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Das lokale Bildverzeichnis liegt außerhalb des Anwendungsspeichers oder ist umgeleitet.');
        }
        $current = $root;
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || in_array($segment, ['.', '..'], true)) {
                throw new RuntimeException('Ungültiger lokaler Bildpfad.');
            }
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current) || (file_exists($current) && strcasecmp((string) realpath($current), $current) !== 0)) {
                throw new RuntimeException('Der lokale Bildpfad enthält eine Verknüpfung oder Umleitung.');
            }
        }
    }

    private function fingerprint(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
