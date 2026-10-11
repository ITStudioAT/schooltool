<?php

use App\Models\DropboxConnection;
use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\Import116RunChange;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingPersonalAppointment;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use App\Services\RestaurantSynchronisationService;
use App\Services\TeachingLiveSource;
use App\Services\TeachingSynchronisationGraph;
use App\Services\TeachingSynchronisationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

class TeachingSynchronisationWindowsTargetTestService extends RestaurantSynchronisationService
{
    protected static function operatingSystemFamily(): string
    {
        return 'Windows';
    }
}

class TeachingSynchronisationLinuxTargetTestService extends RestaurantSynchronisationService
{
    protected static function operatingSystemFamily(): string
    {
        return 'Linux';
    }
}

beforeEach(function () {
    app()->bind(RestaurantSynchronisationService::class, TeachingSynchronisationWindowsTargetTestService::class);
    $this->app['env'] = 'local';
    config(['schooltool.preview.instance' => false]);
    Storage::fake('local');
    Storage::fake('public');
    config(['filesystems.disks.local.root' => Storage::disk('local')->path(''),
        'filesystems.disks.public.root' => Storage::disk('public')->path('')]);
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id, 'from' => '2025-09-01', 'until' => '2026-08-31']);
    $this->actor = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    Role::findOrCreate('super_admin', 'web');
    $this->actor->assignRole('super_admin');
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->course = TeachingCourse::factory()->forSchool($this->school)->forSchoolyear($this->year)->forTeacher($this->actor)->create(['title' => 'Local']);
    $this->snapshot = app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id);
    $this->snapshot['school'] = ['id' => $this->school->id, 'short_name' => $this->school->short_name, 'long_name' => $this->school->long_name];
    $this->snapshot['captured_at'] = now()->toISOString();
    $this->snapshot['files'] = [];
    $this->snapshot['external'] = [];
    $source = Mockery::mock(TeachingLiveSource::class);
    $source->shouldReceive('snapshot')->andReturnUsing(fn (): array => $this->snapshot);
    app()->instance(TeachingLiveSource::class, $source);
});

function teachingSyncApplyPayload(string $token): array
{
    return ['token' => $token, 'replace_confirmed' => true, 'contacts_confirmed' => true];
}

test('teaching sync rejects Linux at HTTP and service boundaries', function (): void {
    app()->bind(RestaurantSynchronisationService::class, TeachingSynchronisationLinuxTargetTestService::class);
    $service = app(TeachingSynchronisationService::class);

    expect($service::available())->toBeFalse();
    $this->actingAs($this->actor, 'sanctum')->getJson('/api/admin/teaching/synchronisation/status')
        ->assertSuccessful()->assertJsonPath('available', false);
    $this->postJson('/api/admin/teaching/synchronisation/preview')->assertForbidden();
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload(str_repeat('a', 64)))
        ->assertForbidden();
    expect(fn () => $service->preview($this->actor))->toThrow(HttpException::class)
        ->and(fn () => $service->apply($this->actor, str_repeat('a', 64)))->toThrow(HttpException::class)
        ->and($this->course->fresh()->title)->toBe('Local');
});

test('teaching sync requires authentication superadmin local target and explicit replacement confirmation', function () {
    $this->postJson('/api/admin/teaching/synchronisation/preview')->assertUnauthorized();
    $ordinary = User::factory()->create(['school_id' => $this->school->id]);
    Role::findOrCreate('teaching_admin', 'web');
    $ordinary->assignRole('teaching_admin');
    $this->actingAs($ordinary, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertForbidden();
    $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/apply', ['token' => str_repeat('a', 64)])->assertUnprocessable();
    $this->app['env'] = 'production';
    $this->postJson('/api/admin/teaching/synchronisation/preview')->assertForbidden();
    expect($this->course->fresh()->title)->toBe('Local');
});

test('preview is read only and apply imports every year with private backup and new disabled identities', function () {
    $teacher = Teacher::query()->create(['school_id' => $this->school->id, 'email' => 'roster@example.test',
        'first_name' => 'Local name', 'last_name' => 'Teacher', 'short' => 'ALT', 'is_active' => true, 'token' => 'local-token']);
    $this->snapshot['teachers'] = [['id' => $teacher->id, 'school_id' => $this->school->id, 'email' => $teacher->email,
        'first_name' => 'Cloud name', 'last_name' => 'Teacher', 'short' => 'NEU', 'is_active' => false]];
    $this->snapshot['schoolyears'][0]['name'] = 'Cloud reviewed year';
    $newYear = $this->snapshot['schoolyears'][0];
    $newYear['id'] = 90001;
    $newYear['name'] = '2026/27';
    $newYear['from'] = '2026-09-01';
    $newYear['until'] = '2027-08-31';
    $this->snapshot['schoolyears'][] = $newYear;
    $newUser = $this->snapshot['users'][0];
    $newUser['id'] = 90002;
    $newUser['email'] = 'new-teacher@example.test';
    $this->snapshot['users'][] = $newUser;
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud old year';
    $newCourse = $this->snapshot['tables']['teaching_courses'][0];
    $newCourse['id'] = 90003;
    $newCourse['schoolyear_id'] = 90001;
    $newCourse['user_id'] = 90002;
    $newCourse['title'] = 'Cloud new year';
    $this->snapshot['tables']['teaching_courses'][] = $newCourse;
    $password = $this->actor->password;
    $roles = $this->actor->getRoleNames()->all();
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    $preview->assertJsonPath('data.new_schoolyears', 1)->assertJsonPath('data.new_accounts', 1);
    $preview->assertJsonPath('data.changed_teachers.0.changes.0.before', 'Local name')
        ->assertJsonPath('data.changed_teachers.0.changes.0.after', 'Cloud name')
        ->assertJsonPath('data.updated_schoolyears.0.values.name', 'Cloud reviewed year');
    expect($teacher->fresh()->first_name)->toBe('Local name');
    expect($this->course->fresh()->title)->toBe('Local');
    $token = $preview->json('data.token');
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertSuccessful();
    expect($this->course->fresh()->title)->toBe('Cloud old year');
    expect($teacher->fresh()->first_name)->toBe('Cloud name')
        ->and($teacher->fresh()->is_active)->toBeTrue()
        ->and($teacher->fresh()->token)->toBe('local-token')
        ->and($this->year->fresh()->name)->toBe('Cloud reviewed year');
    $newAccount = User::where('email', 'new-teacher@example.test')->firstOrFail();
    expect($newAccount->is_active)->toBeFalsy()
        ->and($newAccount->getRoleNames()->all())->toBe([])
        ->and($newAccount->password)->not->toBe($password)
        ->and($this->actor->fresh()->password)->toBe($password)
        ->and($this->actor->fresh()->getRoleNames()->all())->toBe($roles);
    $course = TeachingCourse::findOrFail(90003);
    expect($course->user_id)->toBe($newAccount->id)->and($course->schoolyear_id)->not->toBe(90001);
    $backup = $result->json('backup');
    $contents = Storage::disk('local')->get("teaching/synchronisation-backups/{$this->school->id}/{$backup}.enc");
    expect($contents)->not->toContain('Local');
    $saved = json_decode(Crypt::decryptString($contents), true);
    expect($saved['state']['tables']['teaching_courses'][0]['title'])->toBe('Local');
    $this->get('/api/admin/teaching/synchronisation/backups/'.$backup)->assertSuccessful();
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
});

test('personal appointments are scoped to the school and imported across years with remapped identities and a recovery copy', function () {
    $local = TeachingPersonalAppointment::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->actor->id,
        'title' => 'Local appointment', 'date' => '2026-02-03',
    ]);
    $foreignSchool = School::factory()->create();
    $foreignYear = Schoolyear::factory()->create(['school_id' => $foreignSchool->id]);
    $foreignUser = User::factory()->create(['school_id' => $foreignSchool->id, 'schoolyear_id' => $foreignYear->id]);
    $foreign = TeachingPersonalAppointment::factory()->create([
        'school_id' => $foreignSchool->id, 'schoolyear_id' => $foreignYear->id, 'user_id' => $foreignUser->id,
        'title' => 'Foreign appointment',
    ]);
    $localAttributes = $local->fresh()->getAttributes();
    $foreignAttributes = $foreign->fresh()->getAttributes();
    $captured = app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id);
    expect(array_column($captured['tables']['teaching_personal_appointments'], 'id'))->toBe([$local->id]);
    $this->snapshot = array_replace($this->snapshot, $captured);
    $this->snapshot['schoolyears'][] = [...$this->snapshot['schoolyears'][0], 'id' => 90001,
        'name' => '2026/27', 'from' => '2026-09-01', 'until' => '2027-08-31'];
    $this->snapshot['users'][] = [...$this->snapshot['users'][0], 'id' => 90002, 'email' => 'appointment-teacher@example.test'];
    $cloud = [...$localAttributes, 'id' => $foreign->id, 'schoolyear_id' => 90001, 'user_id' => 90002,
        'title' => 'Cloud weekly appointment', 'date' => '2026-10-12', 'repeat_until' => '2027-06-28',
        'school_hours' => json_encode([6, 7], JSON_THROW_ON_ERROR),
        'time_segments' => json_encode([['starts_at' => '12:30', 'ends_at' => '13:20']], JSON_THROW_ON_ERROR),
        'title_exceptions' => json_encode(['2026-10-19:6' => 'Only this occurrence'], JSON_THROW_ON_ERROR)];
    $this->snapshot['tables']['teaching_personal_appointments'] = [
        $cloud,
        [...$localAttributes, 'id' => 90003, 'title' => 'Cloud previous year appointment'],
    ];

    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    expect($local->fresh()->getAttributes())->toBe($localAttributes);
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))
        ->assertSuccessful();

    $imported = TeachingPersonalAppointment::where('school_id', $this->school->id)->where('title', 'Cloud weekly appointment')->firstOrFail();
    $newAccount = User::where('email', 'appointment-teacher@example.test')->firstOrFail();
    $newYear = Schoolyear::where('school_id', $this->school->id)->where('from', '2026-09-01')->firstOrFail();
    expect($imported->id)->not->toBe($foreign->id);
    expect($imported->user_id)->toBe($newAccount->id)->not->toBe(90002);
    expect($imported->schoolyear_id)->toBe($newYear->id)->not->toBe(90001);
    expect($imported->school_hours)->toBe([6, 7]);
    expect($imported->time_segments)->toEqual([['starts_at' => '12:30', 'ends_at' => '13:20']]);
    expect($imported->title_exceptions)->toBe(['2026-10-19:6' => 'Only this occurrence']);
    expect($imported->getRawOriginal('repeat_until'))->toBe('2027-06-28');
    $this->assertDatabaseHas('teaching_personal_appointments', [
        'id' => 90003, 'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->actor->id, 'title' => 'Cloud previous year appointment',
    ]);
    expect(TeachingPersonalAppointment::where('school_id', $this->school->id)->count())->toBe(2);
    expect($foreign->fresh()->getAttributes())->toBe($foreignAttributes);
    $backup = $result->json('backup');
    $saved = json_decode(Crypt::decryptString(Storage::disk('local')->get(
        "teaching/synchronisation-backups/{$this->school->id}/{$backup}.enc"
    )), true, flags: JSON_THROW_ON_ERROR);
    expect($saved['state']['tables']['teaching_personal_appointments'])->toBe([$localAttributes]);
});

test('invalid personal appointment scope or references block synchronisation without replacing local appointments', function (string $field, string $conflict): void {
    $local = TeachingPersonalAppointment::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->actor->id,
        'title' => 'Preserve local appointment',
    ]);
    $attributes = $local->fresh()->getAttributes();
    $this->snapshot['tables']['teaching_personal_appointments'] = [[...$attributes, $field => 90099]];

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')
        ->assertSuccessful();

    expect($preview->json('data.token'))->toBeNull();
    expect(implode(' ', $preview->json('data.conflicts')))->toContain($conflict);
    expect($local->fresh()->getAttributes())->toBe($attributes);
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
})->with([
    'missing owner' => ['user_id', 'Beziehung zu users'],
    'missing schoolyear' => ['schoolyear_id', 'Beziehung zu schoolyears'],
    'foreign school' => ['school_id', 'Fremde Schule'],
]);

test('synchronisation preserves local Dropbox folder choices and credentials without exporting them or changing foreign choices', function () {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $foreignCourse = TeachingCourse::factory()->create();
    $foreignWork = TeachingCourseWork::factory()->create(['teaching_course_id' => $foreignCourse->id]);
    $foreignConnection = DropboxConnection::factory()->create(['user_id' => $foreignCourse->user_id]);
    $foreignFolder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $foreignWork->id, 'dropbox_connection_id' => $foreignConnection->id,
    ]);
    $folderAttributes = $folder->fresh()->getAttributes();
    $foreignAttributes = $foreignFolder->fresh()->getAttributes();
    $credentials = $connection->fresh()->getRawOriginal('credentials');
    $captured = app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id);
    expect($captured)->not->toHaveKey('dropbox_folders');
    expect($captured['tables'])->not->toHaveKey('teaching_work_dropbox_folders')->not->toHaveKey('dropbox_connections');
    $this->snapshot = array_replace($this->snapshot, $captured);
    $this->snapshot['tables']['teaching_course_works'][0]['description'] = 'Reviewed cloud description';

    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))
        ->assertSuccessful();

    expect($work->fresh()->description)->toBe('Reviewed cloud description');
    expect($folder->fresh()->getAttributes())->toBe($folderAttributes);
    expect($foreignFolder->fresh()->getAttributes())->toBe($foreignAttributes);
    expect($connection->fresh()->getRawOriginal('credentials'))->toBe($credentials);
    $backup = $result->json('backup');
    $savedBytes = Crypt::decryptString(Storage::disk('local')->get("teaching/synchronisation-backups/{$this->school->id}/{$backup}.enc"));
    expect($savedBytes)->not->toContain($credentials, 'refresh_token');
    expect(json_decode($savedBytes, true, flags: JSON_THROW_ON_ERROR)['state']['dropbox_folders'])->toBe([$folderAttributes]);
});

test('synchronisation blocks replacement of Dropbox linked work when its identity changes or disappears', function (string $change): void {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $attributes = $folder->fresh()->getAttributes();
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    if ($change === 'missing work') {
        $this->snapshot['tables']['teaching_course_works'] = [];
    } elseif ($change === 'different owner') {
        $this->snapshot['users'][] = [...$this->snapshot['users'][0], 'id' => 90002, 'email' => 'other-owner@example.test'];
        $this->snapshot['tables']['teaching_courses'][0]['user_id'] = 90002;
    } else {
        $this->snapshot['tables']['teaching_course_works'][0]['title'] = 'Different assignment';
    }

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    expect($preview->json('data.token'))->toBeNull();
    expect(implode(' ', $preview->json('data.conflicts')))->toContain('Dropbox-Ordnerzuordnung');
    expect($folder->fresh()->getAttributes())->toBe($attributes);
    $this->assertModelExists($work);
})->with(['missing work', 'different owner', 'different assignment']);

test('changed local Dropbox folder choices invalidate the synchronisation preview before replacement', function () {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_course_works'][0]['description'] = 'Incoming description';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $folder->update(['folder_id' => 'id:changed-after-preview']);

    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();

    expect($folder->fresh()->folder_id)->toBe('id:changed-after-preview');
    expect($work->fresh()->description)->not->toBe('Incoming description');
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
});

test('a Dropbox connection from another school blocks replacement of locally linked work', function () {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $foreignConnection = DropboxConnection::factory()->create();
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $foreignConnection->id,
    ]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    expect($preview->json('data.token'))->toBeNull();
    expect(implode(' ', $preview->json('data.conflicts')))->toContain('Konto außerhalb der Schule');
    $this->assertModelExists($work);
    $this->assertModelExists($folder);
});

test('a failure restoring local Dropbox choices rolls back all replacements and retains the recovery copy', function () {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $attributes = $folder->fresh()->getAttributes();
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Incoming course';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with(strtolower($query->sql), 'insert into `teaching_work_dropbox_folders`')) {
            throw new RuntimeException('Fixture failure restoring local Dropbox choices.');
        }
    });

    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();

    expect($this->course->fresh()->title)->toBe('Local');
    expect($folder->fresh()->getAttributes())->toBe($attributes);
    $this->assertModelExists($work);
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toHaveCount(1);
});

test('absent LIVE links preserve local student accounts and records used in another year', function () {
    $account = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'is_active' => true]);
    Role::findOrCreate('lunch_user', 'web');
    $account->assignRole('lunch_user');
    $laterYear = Schoolyear::factory()->create(['school_id' => $this->school->id, 'from' => '2026-09-01', 'until' => '2027-08-31']);
    $oldStudent = Import116::factory()->forSchool($this->school)->importedBy($this->actor)->create([
        'schoolyear_id' => $this->year->id, 'student_code' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => $account->email, 'user_id' => $account->id, 'mother_email' => 'local@example.test',
    ]);
    $laterStudent = Import116::factory()->forSchool($this->school)->importedBy($this->actor)->create([
        'schoolyear_id' => $laterYear->id, 'student_code' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => $account->email, 'user_id' => $account->id,
    ]);
    $account->update(['import116_id' => $laterStudent->id]);
    $laterAttributes = $laterStudent->fresh()->getAttributes();
    $password = $account->password;
    $active = $account->fresh()->is_active;
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['import116'] = array_values(array_filter($this->snapshot['import116'], fn (array $row): bool => $row['id'] !== $laterStudent->id));
    $this->snapshot['import116'][0]['user_id'] = null;
    $this->snapshot['import116'][0]['mother_email'] = 'cloud@example.test';
    foreach ($this->snapshot['users'] as &$user) {
        if ($user['id'] === $account->id) {
            $user['import116_id'] = null;
        }
    }
    unset($user);
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud synced';
    $this->actingAs($this->actor, 'sanctum');

    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([])
        ->and($preview->json('data.changed_contacts.0.fields'))->not->toContain('user_id')
        ->and($oldStudent->fresh()->mother_email)->toBe('local@example.test');
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();

    expect($oldStudent->fresh()->user_id)->toBe($account->id)
        ->and($oldStudent->fresh()->mother_email)->toBe('cloud@example.test')
        ->and($account->fresh()->import116_id)->toBe($laterStudent->id)
        ->and($account->fresh()->password)->toBe($password)
        ->and($account->fresh()->is_active)->toBe($active)
        ->and($account->fresh()->getRoleNames()->all())->toBe(['lunch_user'])
        ->and($laterStudent->fresh()->getAttributes())->toBe($laterAttributes)
        ->and($this->course->fresh()->title)->toBe('Cloud synced');
});

test('Unicode teacher accounts and roster entries survive the full synchronisation', function () {
    $this->actor->update(['email' => 'müller@büro.test']);
    $teacher = Teacher::query()->create(['school_id' => $this->school->id, 'email' => $this->actor->email,
        'first_name' => $this->actor->first_name, 'last_name' => $this->actor->last_name, 'short' => 'MÜ', 'is_active' => true]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Unicode cloud course';
    $password = $this->actor->password;
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([])->and($preview->json('data.new_accounts'))->toBe(0);
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();
    expect($this->actor->fresh()->email)->toBe('müller@büro.test')
        ->and($this->actor->fresh()->password)->toBe($password)
        ->and($teacher->fresh()->email)->toBe('müller@büro.test')
        ->and($this->course->fresh()->title)->toBe('Unicode cloud course');
});

test('proven changed student emails and deleted historical aliases preserve the existing login and links', function () {
    $account = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada',
        'last_name' => 'Lovelace', 'email' => 'local-login@example.test', 'import116_id' => null]);
    Role::findOrCreate('student', 'web');
    $account->assignRole('student');
    $student = Import116::factory()->forSchool($this->school)->importedBy($this->actor)->create([
        'schoolyear_id' => $this->year->id, 'student_code' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'birth_date' => '2002-08-03', 'email' => 'cloud-contact@example.test', 'user_id' => $account->id,
    ]);
    $run = Import116Run::query()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->actor->id, 'status' => 'finished', 'started_at' => now()]);
    $historical = [...$student->fresh()->getAttributes(), 'user_id' => 90011];
    $change = Import116RunChange::query()->create(['import116_run_id' => $run->id, 'school_id' => $this->school->id,
        'schoolyear_id' => $this->year->id, 'student_code' => 'S1', 'change_type' => 'updated', 'after_snapshot' => $historical]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['missing_user_ids'] = [90011];
    foreach ($this->snapshot['users'] as &$user) {
        if ($user['id'] === $account->id) {
            $user['id'] = 90010;
            $user['email'] = 'cloud-contact@example.test';
            $user['import116_id'] = $student->id;
        }
    }
    unset($user);
    $this->snapshot['import116'][0]['user_id'] = 90010;
    $this->snapshot['tables']['teaching_courses'][0]['user_id'] = 90010;
    $password = $account->password;
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([])->and($preview->json('data.new_accounts'))->toBe(0);
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();
    expect($account->fresh()->email)->toBe('local-login@example.test')
        ->and($account->fresh()->password)->toBe($password)
        ->and($account->fresh()->getRoleNames()->all())->toBe(['student'])
        ->and($account->fresh()->import116_id)->toBe($student->id)
        ->and($student->fresh()->user_id)->toBe($account->id)
        ->and($this->course->fresh()->user_id)->toBe($account->id)
        ->and($change->fresh()->after_snapshot['user_id'])->toBe($account->id)
        ->and(User::where('email', 'cloud-contact@example.test')->exists())->toBeFalse();
});

test('changed local data and another actor invalidate the preview', function () {
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $other = User::factory()->create(['school_id' => $this->school->id]);
    $other->assignRole('super_admin');
    $this->actingAs($other, 'sanctum')->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    $this->actingAs($this->actor, 'sanctum');
    $this->course->update(['title' => 'Changed meanwhile']);
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Changed meanwhile');
});

test('changed cloud rows or files require a new confirmed preview before creating a backup', function () {
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Changed on cloud';
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Local');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $this->snapshot['files'][] = ['path' => 'teaching/new.pdf', 'disk' => 's3', 'content' => base64_encode('new'), 'size' => 3, 'sha256' => hash('sha256', 'new')];
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Local')
        ->and(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
});

test('schema conflicts are visible while foreign teaching IDs are safely remapped', function () {
    $foreign = TeachingCourse::factory()->create();
    $incoming = $this->snapshot['tables']['teaching_courses'][0];
    $incoming['id'] = $foreign->id;
    $this->snapshot['tables']['teaching_courses'][] = $incoming;
    $this->snapshot['columns']['teaching_class_heads'][] = ['name' => 'unknown'];
    $result = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($result->json('data.token'))->toBeNull()
        ->and(implode(' ', $result->json('data.conflicts')))->toContain('Schema');
    expect(implode(' ', $result->json('data.conflicts')))->not->toContain('ID-Kollision');
    expect($foreign->fresh())->not->toBeNull();
    $foreignAttributes = $foreign->fresh()->getAttributes();
    array_pop($this->snapshot['columns']['teaching_class_heads']);
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    expect($token)->toBeString();
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertSuccessful();
    expect($foreign->fresh()->getAttributes())->toBe($foreignAttributes)
        ->and(TeachingCourse::where('school_id', $this->school->id)->count())->toBe(2);
});

test('equivalent foreign key order and restrict spelling pass but different cascades block', function () {
    foreach ($this->snapshot['foreign_keys'] as $table => $keys) {
        $this->snapshot['foreign_keys'][$table] = array_reverse(array_map(function (array $key): array {
            $key['name'] = 'different_constraint_name';
            $key['foreign_schema'] = 'different_installation';
            foreach (['on_update', 'on_delete'] as $action) {
                if ($key[$action] === 'no action') {
                    $key[$action] = 'RESTRICT';
                }
            }

            return $key;
        }, $keys));
    }
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    $key = &$this->snapshot['foreign_keys']['teaching_curricula'][0];
    $key['on_delete'] = $key['on_delete'] === 'cascade' ? 'set null' : 'cascade';
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.token'))->toBeNull()
        ->and(implode(' ', $preview->json('data.conflicts')))->toContain('Beziehungsschema');
});

test('cross school course groups cannot be deleted through the school sync scope', function () {
    $foreignSchool = School::factory()->create();
    $group = DB::table('user_groups')->insertGetId(['school_id' => $foreignSchool->id, 'type' => 'teaching',
        'name' => 'Foreign group', 'teaching_course_id' => $this->course->id, 'created_by_user_id' => $this->actor->id]);
    $result = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($result->json('data.token'))->toBeNull()
        ->and(implode(' ', $result->json('data.conflicts')))->toContain('anderen Schule');
    expect(DB::table('user_groups')->where('id', $group)->exists())->toBeTrue();
});

test('file failure rolls back replacements and leaves old files and the encrypted safety backup', function () {
    $bytes = 'Cloud attachment';
    $this->snapshot['files'] = [['disk' => 's3', 'path' => 'teaching/cloud.pdf', 'size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)]];
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    Storage::disk('local')->put('teaching/synchronisation/'.$this->school->id.'/'.hash('sha256', $bytes).'.pdf', 'Conflicting file');
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Local')
        ->and(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toHaveCount(1);
});

test('database failure after file creation rolls back the full graph and cleans only newly copied files', function () {
    $bytes = 'Cloud copy';
    $this->snapshot['files'] = [['disk' => 's3', 'path' => 'teaching/copy.pdf', 'size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)]];
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with(strtolower($query->sql), 'insert into `teaching_courses`')) {
            throw new RuntimeException('Fixture failure after course insertion.');
        }
    });
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Local')
        ->and(Storage::disk('local')->allFiles('teaching/synchronisation/'.$this->school->id))->toBe([])
        ->and(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toHaveCount(1);
});
