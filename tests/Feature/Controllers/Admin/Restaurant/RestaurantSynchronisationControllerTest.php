<?php

use App\Models\Import116;
use App\Models\RestaurantBilling;
use App\Models\RestaurantFood;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\User;
use App\Services\RestaurantLiveSource;
use App\Services\RestaurantSynchronisationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function restaurantSyncFixtureSnapshot(School $school): array
{
    $data = ['school' => ['id' => $school->id, 'short_name' => $school->short_name, 'long_name' => $school->long_name],
        'captured_at' => now()->toIso8601String(), 'tables' => [], 'columns' => [], 'files' => [], 'schoolyears' => []];
    foreach (RestaurantSynchronisationService::TABLES as $table => $scope) {
        $query = DB::table($table);
        if (is_string($scope)) {
            $query->where('school_id', $school->id);
        } else {
            $query->whereIn($scope[1], array_column($data['tables'][$scope[0]], 'id'));
        }
        $columns = array_values(array_diff(Schema::getColumnListing($table), ['booking_slot_key']));
        $data['columns'][$table] = $columns;
        $data['tables'][$table] = $query->get($columns)->map(fn (object $row): array => (array) $row)->all();
    }
    $data['encrypted'] = array_keys(array_filter((new RestaurantSepaMandate)->getCasts(), fn (string $cast): bool => str_starts_with($cast, 'encrypted')));
    foreach ($data['tables']['restaurant_sepa_mandates'] as &$mandate) {
        foreach ($data['encrypted'] as $column) {
            if ($mandate[$column] !== null) {
                $mandate[$column] = Crypt::decryptString($mandate[$column]);
            }
        }
    }
    unset($mandate);
    $data['users'] = DB::table('users')->where('school_id', $school->id)->get(RestaurantSynchronisationService::USER_FIELDS)
        ->map(function (object $row): array {
            return [...(array) $row, 'restaurant_roles' => User::find($row->id)->getRoleNames()->intersect(['lunch_admin', 'lunch_user', 'lunch_candidate'])->sort()->values()->all()];
        })->all();
    $data['imports'] = DB::table('import116')->where('school_id', $school->id)->get(RestaurantSynchronisationService::IMPORT_FIELDS)->map(fn (object $row): array => (array) $row)->all();
    $settings = (array) DB::table('school_tools')->where('school_id', $school->id)->first();
    $data['settings'] = array_filter($settings, fn (string $key): bool => str_starts_with($key, 'restaurant_'), ARRAY_FILTER_USE_KEY);

    return $data;
}

beforeEach(function () {
    $this->app['env'] = 'local';
    config(['app.env' => 'local', 'schooltool.preview.instance' => false]);
    foreach (['super_admin', 'admin', 'lunch_admin', 'lunch_user', 'lunch_candidate', 'teacher'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    Storage::fake('local');
    Storage::fake('public');
    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'restaurant');
    grantSchoolToolLicenceForTests($this->school, 'Restaurant');
    $this->actor = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null, 'uuid' => null]);
    $this->actor->assignRole('super_admin');
    $this->restaurantUser = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null, 'uuid' => null]);
    $this->restaurantUser->assignRole(['lunch_user', 'teacher']);
    $this->student = Import116::factory()->forSchool($this->school)->importedBy($this->actor)->create([
        'schoolyear_id' => null, 'user_id' => $this->restaurantUser->id,
    ]);
    $this->restaurantUser->update(['import116_id' => $this->student->id]);
    $menu = RestaurantMenu::factory()->create(['school_id' => $this->school->id, 'title' => 'Live Menü']);
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $this->school->id]);
    $entry = RestaurantMenuPlanEntry::factory()->create(['restaurant_menu_plan_id' => $plan->id, 'restaurant_menu_id' => $menu->id]);
    RestaurantMenuPlanBooking::query()->create(['school_id' => $this->school->id, 'user_id' => $this->restaurantUser->id,
        'restaurant_menu_plan_entry_id' => $entry->id, 'import116_id' => $this->student->id, 'price' => 8.5, 'quantity' => 1]);
    RestaurantSepaMandate::query()->create(['school_id' => $this->school->id, 'user_id' => $this->restaurantUser->id,
        'flow_uuid' => (string) Str::uuid(), 'status' => 'completed', 'iban' => 'AT611904300234573201']);
    RestaurantBilling::query()->create(['school_id' => $this->school->id, 'created_by_user_id' => $this->actor->id,
        'start_date' => '2026-04-01', 'end_date' => '2026-04-07', 'weeks_count' => 1, 'bookings_count' => 1, 'total_amount' => 8.5,
        'snapshot' => ['rows' => [['user_id' => $this->restaurantUser->id, 'user_name' => 'Fixture']]]]);
    $this->snapshot = restaurantSyncFixtureSnapshot($this->school);
    $this->source = Mockery::mock(RestaurantLiveSource::class);
    $this->source->shouldReceive('snapshot')->andReturnUsing(fn (): array => $this->snapshot);
    app()->instance(RestaurantLiveSource::class, $this->source);
});

test('synchronisation rejects unauthenticated users and ordinary restaurant administrators', function () {
    $this->postJson('/api/admin/restaurant/synchronisation/preview')->assertUnauthorized();
    foreach (['admin', 'lunch_admin', 'lunch_user'] as $role) {
        $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
        $user->assignRole($role);
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')->assertForbidden();
        $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => str_repeat('a', 64), 'confirmed' => true])->assertForbidden();
    }
});

test('synchronisation rejects production preview instances and nonlocal databases', function () {
    $this->actingAs($this->actor, 'sanctum');
    $this->app['env'] = 'production';
    $this->postJson('/api/admin/restaurant/synchronisation/preview')->assertForbidden();
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => str_repeat('a', 64), 'confirmed' => true])->assertForbidden();
    $this->app['env'] = 'local';
    config(['schooltool.preview.instance' => true]);
    expect(RestaurantSynchronisationService::available())->toBeFalse();
    $this->postJson('/api/admin/restaurant/synchronisation/preview')->assertNotFound();
    $default = config('database.default');
    $configuration = DB::connection()->getConfig();
    $configuration['host'] = 'cloudways.invalid';
    config(['schooltool.preview.instance' => false, 'database.connections.restaurant_guard_fixture' => $configuration,
        'database.default' => 'restaurant_guard_fixture']);
    expect(RestaurantSynchronisationService::available())->toBeFalse();
    config(['database.default' => $default]);
});

test('http preview starts its live adapter in a filtered web environment', function () {
    $savedEnvironment = [];
    $savedServer = $_SERVER;
    $savedEnv = $_ENV;
    foreach (['OS', 'SCHOOLTOOL_MAIN_SSH'] as $key) {
        $savedEnvironment[$key] = ['process' => getenv($key), 'env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null];
    }
    try {
        putenv('OS');
        unset($_ENV['OS'], $_SERVER['OS']);
        $_SERVER = ['APP_ENV' => getenv('APP_ENV'), 'REQUEST_METHOD' => 'POST'];
        foreach (['USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'WINDIR', 'SystemRoot', 'PATH', 'Path', 'TEMP', 'TMP', 'COMSPEC', 'PSModulePath'] as $key) {
            unset($_ENV[$key]);
        }
        // Fail before SSH: the real PowerShell adapter must reach configuration validation on Windows.
        putenv('SCHOOLTOOL_MAIN_SSH=invalid');
        $_ENV['SCHOOLTOOL_MAIN_SSH'] = $_SERVER['SCHOOLTOOL_MAIN_SSH'] = 'invalid';
        app()->instance(RestaurantLiveSource::class, new RestaurantLiveSource);
        $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')
            ->assertUnprocessable()->assertJsonPath('message', 'Der Live-Lesezugriff wurde im Schritt „lokale SSH-Konfiguration“ blockiert. Es wurden keine Daten übernommen.');
        expect(RestaurantMenuPlanBooking::count())->toBe(1);
    } finally {
        foreach ($savedEnvironment as $key => $saved) {
            putenv($saved['process'] === false ? $key : $key.'='.$saved['process']);
            unset($_ENV[$key], $_SERVER[$key]);
            if ($saved['env'] !== null) {
                $_ENV[$key] = $saved['env'];
            }
            if ($saved['server'] !== null) {
                $_SERVER[$key] = $saved['server'];
            }
        }
        $_SERVER = $savedServer;
        $_ENV = $savedEnv;
    }
});

test('live reader preserves the native Windows environment needed for SSH startup', function () {
    $savedServer = $_SERVER;
    $savedEnv = $_ENV;
    try {
        $_SERVER = ['APP_ENV' => getenv('APP_ENV'), 'REQUEST_METHOD' => 'POST'];
        $_ENV = ['APP_ENV' => getenv('APP_ENV')];
        $environment = (new ReflectionMethod(RestaurantLiveSource::class, 'processEnvironment'))->invoke(null);
        $process = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command',
            '. ./scripts/git_ssh_helpers.ps1; $ssh = Get-SchooltoolPreviewExecutable ssh; & $ssh -G -F none -o BatchMode=yes -- fixture@example.invalid; exit $LASTEXITCODE'],
            base_path(), $environment, null, 10);
        // -G only evaluates local SSH configuration; no network connection or authentication occurs.
        $process->run();
        expect($process->getExitCode())->toBe(0);
    } finally {
        $_SERVER = $savedServer;
        $_ENV = $savedEnv;
    }
});

test('preview reports scope without database mutation or exposing personal data', function () {
    $before = DB::table('users')->get()->toJson();
    $response = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')
        ->assertSuccessful()->assertJsonCount(18, 'data.summary');
    expect($response->getContent())->not->toContain('AT611904300234573201')->not->toContain($this->restaurantUser->email)
        ->and(DB::table('users')->get()->toJson())->toBe($before);
});

test('legacy SEPA JSON check is rejected before writes and its repair preserves encrypted children and other constraints', function () {
    DB::statement('ALTER TABLE restaurant_sepa_mandates ADD CONSTRAINT restaurant_sepa_mandates_chk_1 CHECK (json_valid(child_entries))');
    DB::statement("ALTER TABLE restaurant_sepa_mandates ADD CONSTRAINT restaurant_sepa_status_fixture CHECK (status <> 'invalid')");
    $children = [['name' => 'Fixture Child', 'schoolclass' => '1A']];
    $this->snapshot['tables']['restaurant_sepa_mandates'][0]['child_entries'] = json_encode($children, JSON_THROW_ON_ERROR);
    $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')
        ->assertUnprocessable()->assertJsonPath('message', 'Die lokale SEPA-Tabelle enthält noch eine alte JSON-Prüfung für child_entries. Vor der Synchronisation muss die vorbereitete SEPA-Reparaturmigration ausgeführt werden. Es wurde nichts übernommen.');
    expect(RestaurantMenuPlanBooking::count())->toBe(1)->and(RestaurantSepaMandate::firstOrFail()->child_entries)->toBeNull();
    $repair = require database_path('migrations/2026_09_28_192256_remove_legacy_json_check_from_restaurant_sepa_mandates.php');
    $repair->up();
    $repair->up();
    expect(DB::table('information_schema.TABLE_CONSTRAINTS')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'restaurant_sepa_mandates')->where('CONSTRAINT_NAME', 'restaurant_sepa_status_fixture')->exists())->toBeTrue();
    $token = $this->postJson('/api/admin/restaurant/synchronisation/preview')->assertSuccessful()->json('data.token');
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertSuccessful();
    $stored = DB::table('restaurant_sepa_mandates')->value('child_entries');
    expect(RestaurantSepaMandate::firstOrFail()->child_entries)->toBe($children)
        ->and(json_decode($stored, true))->toBeNull()->and(Crypt::decryptString($stored))->toBe(json_encode($children, JSON_THROW_ON_ERROR));
});

test('apply uses the reviewed snapshot recreates missing students remaps collisions and preserves unrelated accounts', function () {
    $oldUserId = $this->restaurantUser->id;
    $oldStudentId = $this->student->id;
    $restaurantEmail = $this->restaurantUser->email;
    DB::table('restaurant_menu_plan_bookings')->delete();
    DB::table('restaurant_sepa_mandates')->delete();
    $this->student->delete();
    $this->restaurantUser->delete();
    $otherSchool = School::factory()->create();
    $foreignUser = User::factory()->create(['id' => $oldUserId, 'school_id' => $otherSchool->id]);
    $foreignImport = Import116::factory()->forSchool($otherSchool)->importedBy($foreignUser)->create(['id' => $oldStudentId]);
    $menuId = $this->snapshot['tables']['restaurant_menus'][0]['id'];
    DB::table('restaurant_menu_plan_entries')->delete();
    DB::table('restaurant_menus')->where('id', $menuId)->update(['school_id' => $otherSchool->id, 'title' => 'Unrelated menu']);
    $password = $this->actor->password;
    $token = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')->assertSuccessful()->json('data.token');
    $this->snapshot['tables']['restaurant_menus'][0]['title'] = 'Changed AFTER preview';
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertSuccessful();
    $newUser = User::where('school_id', $this->school->id)->where('email', $restaurantEmail)->firstOrFail();
    $newImport = Import116::where('school_id', $this->school->id)->firstOrFail();
    $booking = RestaurantMenuPlanBooking::where('school_id', $this->school->id)->firstOrFail();
    expect($newUser->id)->not->toBe($oldUserId)->and($newImport->id)->not->toBe($oldStudentId)
        ->and($booking->user_id)->toBe($newUser->id)->and($booking->import116_id)->toBe($newImport->id)
        ->and($newImport->user_id)->toBe($newUser->id)->and($this->actor->fresh()->password)->toBe($password)
        ->and($this->actor->fresh()->hasRole('super_admin'))->toBeTrue()
        ->and(RestaurantMenu::where('school_id', $this->school->id)->firstOrFail()->title)->toBe('Live Menü')
        ->and(RestaurantMenu::findOrFail($menuId)->title)->toBe('Unrelated menu')
        ->and($foreignUser->fresh()->school_id)->toBe($otherSchool->id)
        ->and($foreignImport->fresh()->school_id)->toBe($otherSchool->id)
        ->and(RestaurantSepaMandate::where('school_id', $this->school->id)->firstOrFail()->iban)->toBe('AT611904300234573201');
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertUnprocessable();
});

test('apply requires explicit confirmation and refuses changed local data', function () {
    $token = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')->assertSuccessful()->json('data.token');
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => false])->assertUnprocessable();
    $this->restaurantUser->update(['last_name' => 'Local change']);
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertUnprocessable();
    expect($this->restaurantUser->fresh()->last_name)->toBe('Local change')->and(RestaurantMenuPlanBooking::count())->toBe(1);
});

test('apply refuses a preview belonging to another actor or school', function () {
    $token = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')->assertSuccessful()->json('data.token');
    $otherActor = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $otherActor->assignRole('super_admin');
    $this->actingAs($otherActor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertUnprocessable();
    $this->actor->update(['school_id' => School::factory()->create()->id]);
    $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertUnprocessable();
});

test('failed database writes roll back restaurant and shared changes and staged files', function () {
    $path = 'restaurant/foods/fixture.png';
    $bytes = 'fixture image bytes';
    $food = RestaurantFood::factory()->create(['school_id' => $this->school->id, 'food_image_path' => $path]);
    $this->snapshot = restaurantSyncFixtureSnapshot($this->school);
    $this->snapshot['files'] = [['disk' => 'public', 'path' => $path, 'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)]];
    $userIndex = array_search($this->restaurantUser->id, array_column($this->snapshot['users'], 'id'), true);
    $this->snapshot['users'][$userIndex]['first_name'] = 'Would change';
    $this->snapshot['tables']['restaurant_sepa_mandates'][0]['flow_uuid'] = null;
    $token = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')->assertSuccessful()->json('data.token');
    $beforeName = $this->restaurantUser->first_name;
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertUnprocessable();
    expect($this->restaurantUser->fresh()->first_name)->toBe($beforeName)
        ->and($this->restaurantUser->fresh()->hasRole('teacher'))->toBeTrue()
        ->and(RestaurantMenuPlanBooking::count())->toBe(1)->and($food->fresh()->food_image_path)->toBe($path)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('preview rejects incomplete schemas identities and relationships', function () {
    $this->snapshot['school']['long_name'] = 'Other identity';
    $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')->assertUnprocessable();
    $this->snapshot = restaurantSyncFixtureSnapshot($this->school);
    $this->snapshot['columns']['restaurant_menus'][] = 'unknown_column';
    $this->postJson('/api/admin/restaurant/synchronisation/preview')->assertUnprocessable();
    $this->snapshot = restaurantSyncFixtureSnapshot($this->school);
    $this->snapshot['tables']['restaurant_menu_plan_bookings'][0]['user_id'] = 999999;
    $this->postJson('/api/admin/restaurant/synchronisation/preview')->assertUnprocessable();
});

test('image import preserves existing credentials roles and unrelated images', function () {
    $bytes = 'fixture image content';
    $path = 'restaurant/foods/source.png';
    $food = RestaurantFood::factory()->create(['school_id' => $this->school->id, 'food_image_path' => $path]);
    Storage::disk('public')->put($path, 'old content');
    $this->snapshot = restaurantSyncFixtureSnapshot($this->school);
    $this->snapshot['files'] = [['disk' => 'public', 'path' => $path, 'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)]];
    $password = $this->restaurantUser->password;
    $token = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')->assertSuccessful()->json('data.token');
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertSuccessful();
    $newPath = $food->fresh()->food_image_path;
    expect(Storage::disk('public')->get($newPath))->toBe($bytes)
        ->and(Storage::disk('public')->get($path))->toBe('old content')
        ->and($this->restaurantUser->fresh()->password)->toBe($password)
        ->and($this->restaurantUser->fresh()->hasRole('teacher'))->toBeTrue();
});

test('settings expose synchronisation only to a local superadmin', function () {
    $this->actingAs($this->actor, 'sanctum')->getJson('/api/admin/restaurant/settings')->assertSuccessful()->assertJsonPath('can_synchronise', true);
    $user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $user->assignRole('admin');
    $this->actingAs($user, 'sanctum')->getJson('/api/admin/restaurant/settings')->assertSuccessful()->assertJsonPath('can_synchronise', false);
});

test('preview blocks an existing student linked to a different local account', function () {
    $student = Import116::where('school_id', $this->school->id)->firstOrFail();
    $oldUserId = $student->user_id;
    $otherUser = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $student->update(['user_id' => $otherUser->id]);
    $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')
        ->assertUnprocessable()->assertJsonPath('message', 'Ein vorhandener Schüler gehört lokal zu einem anderen Benutzerkonto.');
    expect($student->fresh()->user_id)->toBe($otherUser->id)->not->toBe($oldUserId);
});

test('synchronisation reuses a proven student placeholder without changing login identity', function (string $placeholderType) {
    $this->student->update(['birth_date' => '2009-02-03', 'email' => 'student.fixture@example.test']);
    $this->restaurantUser->update(['first_name' => $this->student->first_name, 'last_name' => $this->student->last_name,
        'email' => $this->student->email]);
    $this->restaurantUser->syncRoles('lunch_user');
    $this->snapshot = restaurantSyncFixtureSnapshot($this->school);
    $localEmail = $placeholderType === 'import_id' ? 'import116.'.$this->student->id.'@schooltool.noemail'
        : 'noemail.'.preg_replace('/[^a-z0-9_]/', '_', strtolower($this->student->student_code)).'@schooltool.noemail';
    $this->restaurantUser->update(['email' => $localEmail, 'uuid' => (string) Str::uuid()]);
    $this->student->update(['email' => null]);
    $password = $this->restaurantUser->password;
    $uuid = $this->restaurantUser->uuid;
    $usersBefore = User::count();
    $token = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')
        ->assertSuccessful()->assertJsonPath('data.reused_student_accounts', 1)->json('data.token');
    $this->postJson('/api/admin/restaurant/synchronisation/apply', ['token' => $token, 'confirmed' => true])->assertSuccessful();
    expect(User::count())->toBe($usersBefore)
        ->and($this->restaurantUser->fresh()->email)->toBe($localEmail)
        ->and($this->restaurantUser->fresh()->password)->toBe($password)
        ->and($this->restaurantUser->fresh()->uuid)->toBe($uuid)
        ->and($this->student->fresh()->user_id)->toBe($this->restaurantUser->id)
        ->and($this->student->fresh()->email)->toBe('student.fixture@example.test')
        ->and(RestaurantMenuPlanBooking::firstOrFail()->user_id)->toBe($this->restaurantUser->id)
        ->and(RestaurantSepaMandate::firstOrFail()->user_id)->toBe($this->restaurantUser->id);
})->with(['import_id', 'student_code']);

test('placeholder matching blocks unproven identities and privileged accounts', function (string $risk) {
    $this->student->update(['birth_date' => '2009-02-03', 'email' => 'student.fixture@example.test']);
    $this->restaurantUser->update(['first_name' => $this->student->first_name, 'last_name' => $this->student->last_name,
        'email' => $this->student->email]);
    $this->restaurantUser->syncRoles('lunch_user');
    $this->snapshot = restaurantSyncFixtureSnapshot($this->school);
    $this->restaurantUser->update(['email' => 'import116.'.$this->student->id.'@schooltool.noemail']);
    $this->student->update(['email' => null]);
    match ($risk) {
        'birth_date' => $this->student->update(['birth_date' => '2009-02-04']),
        'name' => $this->student->update(['last_name' => 'Other identity']),
        'email_pattern' => $this->restaurantUser->update(['email' => 'unrelated@schooltool.noemail']),
        'reverse_link' => $this->restaurantUser->update(['import116_id' => null]),
        'privileges' => $this->restaurantUser->assignRole('teacher'),
        'email_collision' => User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null,
            'email' => 'student.fixture@example.test']),
    };
    $usersBefore = User::count();
    $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/restaurant/synchronisation/preview')->assertUnprocessable();
    expect(User::count())->toBe($usersBefore)->and($this->student->fresh()->user_id)->toBe($this->restaurantUser->id);
})->with(['birth_date', 'name', 'email_pattern', 'reverse_link', 'privileges', 'email_collision']);
