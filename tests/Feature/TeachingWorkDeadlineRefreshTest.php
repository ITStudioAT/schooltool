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

test('normal saving can shorten a historical work, synchronizes its linked folders, and the old editor is gone', function (): void {
    $this->work->update(['status' => ['communicated_deadline_date' => '2026-10-22']]);
    $this->deadlines->shouldReceive('syncLinkedFolders')->once()
        ->withArgs(fn ($work, $actor): bool => $work->finish_until_date->format('Y-m-d') === '2026-10-21' && $actor->is($this->user));
    $base = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->putJson($base, ['finish_until_date' => '2026-10-21', 'finish_until_time' => null])
        ->assertOk()->assertJsonPath('data.finish_until_date', '2026-10-21')->assertJsonMissingPath('data.status.communicated_deadline_date');
    $this->postJson($base.'/student-deadline', [])->assertNotFound();
    expect($this->work->fresh()->status['communicated_deadline_date'])->toBe('2026-10-22');
});
