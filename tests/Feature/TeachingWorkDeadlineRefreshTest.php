<?php

use App\Models\DropboxConnection;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingWorkDeadlineSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $this->user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('admin');
    $this->course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($this->user)->create();
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id,
        'title' => 'Gespeicherter Titel', 'finish_until_date' => '2026-10-23', 'finish_until_time' => null]);
    $this->connection = DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $link = new TeachingWorkModuleLink(['dropbox_connection_id' => $this->connection->id,
        'account_id' => $this->connection->account_id, 'folder_id' => 'id:module', 'folder_name' => 'E-Mails']);
    $dropbox = $this->mock(DropboxWorkImport::class);
    $dropbox->shouldReceive('connection')->andReturn($this->connection);
    $dropbox->shouldReceive('configured', 'installed')->andReturnTrue();
    $modules = $this->mock(DropboxModuleLinks::class);
    $modules->shouldReceive('installed')->andReturnTrue();
    $modules->shouldReceive('current')->andReturn($link);
    $modules->shouldReceive('assignmentVersion')->andReturn('same-binding');
    $modules->shouldReceive('exercise')->andReturn(['status' => 'found', 'folder' => ['id' => 'id:exercise', 'path' => '/materials/work']]);
    $this->deadlines = $this->partialMock(TeachingWorkDeadlineSync::class);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id.'/dropbox/refresh';
    $this->payload = ['date' => '2026-10-16', 'finish_until_date' => '2026-10-24', 'finish_until_time' => '12:30',
        'expected' => ['date' => '2026-10-23', 'time' => null], 'title' => 'Nicht speichern'];
    $this->actingAs($this->user, 'sanctum');
});

test('refresh accepts the edited internal deadline without saving other form fields', function (): void {
    $this->deadlines->shouldReceive('sync')->once()->withArgs(fn ($connection, $work, $folder): bool => $work->finish_until_date->format('Y-m-d') === '2026-10-24' && $work->finish_until_time === '12:30')
        ->andReturn(['changed' => true, 'message' => 'Interne Endfrist übernommen.']);
    $this->postJson($this->url, $this->payload)->assertOk()
        ->assertJsonPath('saved_work.finish_until_date', '2026-10-24')
        ->assertJsonPath('saved_work.finish_until_time', '12:30');
    expect($this->work->fresh()->title)->toBe('Gespeicherter Titel');
    Http::assertNothingSent();
});

test('a Dropbox write failure does not save the edited deadline as synchronized', function (): void {
    $this->deadlines->shouldReceive('sync')->once()->andThrow(new HttpException(409, 'Dropbox-Schreibkonflikt'));
    $this->postJson($this->url, $this->payload)->assertConflict();
    expect($this->work->fresh()->finish_until_date->format('Y-m-d'))->toBe('2026-10-23')
        ->and($this->work->fresh()->finish_until_time)->toBeNull();
});

test('refresh rejects another editor changing the deadline and never writes Dropbox', function (): void {
    $this->deadlines->shouldNotReceive('sync');
    $this->work->update(['finish_until_time' => '11:00']);
    $this->postJson($this->url, $this->payload)->assertConflict();
    expect($this->work->fresh()->finish_until_time)->toBe('11:00');
});

test('refresh validates the internal time and keeps a different teachers work protected', function (): void {
    $this->deadlines->shouldNotReceive('sync');
    $this->postJson($this->url, array_replace($this->payload, ['finish_until_time' => '25:00']))->assertUnprocessable();
    $teacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->postJson($this->url, $this->payload)->assertForbidden();
});

test('teachers can set a student deadline with or without a time without changing the final deadline', function (?string $time): void {
    $url = str_replace('/dropbox/refresh', '/student-deadline', $this->url);
    $this->postJson($url, ['date' => '2026-10-22', 'time' => $time, 'expected' => ['date' => null, 'time' => null]])
        ->assertOk()->assertJsonPath('data.communicated_deadline.date', '2026-10-22')->assertJsonPath('data.communicated_deadline.time', $time);
    $saved = $this->work->fresh();
    expect($saved->finish_until_date->format('Y-m-d'))->toBe('2026-10-23')
        ->and($saved->finish_until_time)->toBeNull()
        ->and($saved->title)->toBe('Gespeicherter Titel')
        ->and($saved->communicatedDeadline()['at'])->toBe($time ? '2026-10-22T10:30:00+02:00' : null);
    Http::assertNothingSent();
})->with([null, '10:30']);

test('checking an internal deadline also validates an existing date-only student deadline', function (): void {
    $this->work->setStudentDeadline('2026-10-22', null);
    $this->work->save();
    $this->deadlines->shouldReceive('sync')->once()->andReturn(['changed' => true, 'message' => 'Frist übernommen.']);
    $this->postJson($this->url, $this->payload)->assertOk()->assertJsonPath('saved_work.communicated_deadline.date', '2026-10-22');
});

test('teachers can remove the optional student deadline and ordinary work edits preserve it', function (): void {
    $this->work->setStudentDeadline('2026-10-22', null);
    $this->work->save();
    $base = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->putJson($base, ['title' => 'Geänderter Titel', 'status' => ['communicated_deadline_date' => '2026-10-21']])
        ->assertOk()->assertJsonPath('data.communicated_deadline.date', '2026-10-22');
    $this->postJson($base.'/student-deadline', ['date' => null, 'time' => null, 'expected' => ['date' => '2026-10-22', 'time' => null]])
        ->assertOk()->assertJsonPath('data.communicated_deadline', null);
});

test('a student deadline after the final deadline and a later shortening of the final deadline are rejected', function (): void {
    $base = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->postJson($base.'/student-deadline', ['date' => '2026-10-24', 'time' => null, 'expected' => ['date' => null, 'time' => null]])
        ->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->work->setStudentDeadline('2026-10-22', '10:00');
    $this->work->save();
    $this->putJson($base, ['finish_until_date' => '2026-10-21'])->assertUnprocessable();
    expect($this->work->fresh()->finish_until_date->format('Y-m-d'))->toBe('2026-10-23');
});

test('the student deadline editor rejects stale edits and another teachers work', function (): void {
    $url = str_replace('/dropbox/refresh', '/student-deadline', $this->url);
    $payload = ['date' => '2026-10-22', 'time' => null, 'expected' => ['date' => '2026-10-20', 'time' => null]];
    $this->postJson($url, $payload)->assertConflict();
    $teacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->postJson($url, $payload)->assertForbidden();
    expect($this->work->fresh()->communicatedDeadline())->toBeNull();
});
