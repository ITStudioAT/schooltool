<?php

use App\Models\DropboxConnection;
use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseWork;
use App\Models\TeachingCurriculum;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingSchema;
use App\Models\TeachingSchoolHour;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('submission collection exports the selected work without collecting sending grading or modifying it', function (string $mode): void {
    Http::preventStrayRequests();
    Mail::fake();
    $before = $this->work->fresh()->getAttributes();
    $data = $this->getJson($this->url.'?purpose=submission_collection&collection_mode='.$mode.'&date=2026-10-20')
        ->assertOk()->json('data');
    expect($data['context']['purpose'])->toBe('collect-exercise-submissions')
        ->and($data['context']['mode'])->toBe($mode)
        ->and($data['context']['work_id'])->toBe($this->work->id)
        ->and($data['context']['course_id'])->toBe($this->course->id)
        ->and($data['context']['work']['scheduled_date'])->toBe('2026-10-20')
        ->and($data['context']['operation'])->toBe(['collect_submissions' => true, 'preserve_existing' => true, 'grade' => false, 'send' => false, 'schooltool_import' => false])
        ->and($data['context']['exercise_folder'])->toBeNull()
        ->and($data['context']['collection_receipt_contract']['completion'])->toMatchArray([
            'kind' => 'schooltool.collection-run-completion', 'exercise_id' => 'schooltool-work:'.$this->work->id, 'course_id' => $this->course->id])
        ->and($data['context']['collection_receipt_contract']['storage']['verified_file_fields'])->toBe(['id', 'revision', 'dropbox_path', 'sha256'])
        ->and($data['text'])->toContain('changed_versions', 'new_first_submissions', 'keine Abgabenänderung', 'verified_files')
        ->and($data['text'])->toContain('download-exercise-submissions-from-email', 'guenther.kron@cdgym.at', 'guenther.kron@bildung.gv.at', 'Keine fachliche Bewertung', 'kein Schooltool-Import')
        ->and($this->work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
    Mail::assertNothingSent();
})->with(['collect', 'repeat']);

test('submission collection rejects invalid mode and changed dates', function (string $query): void {
    Http::preventStrayRequests();
    $this->getJson($this->url.'?purpose=submission_collection'.$query)->assertUnprocessable();
    Http::assertNothingSent();
})->with(['', '&collection_mode=invalid', '&collection_mode=collect&date=2026-10-21']);

test('submission collection reuses the confirmed folder without a new Dropbox scan', function (): void {
    Http::preventStrayRequests();
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkModuleLink::query()->create(['teaching_course_work_id' => $this->work->id, 'dropbox_connection_id' => $connection->id,
        'account_id' => $connection->account_id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'rev1', 'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/Materialien/Modul']);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->once()->withArgs(fn (...$arguments): bool => $arguments[4] === false && $arguments[5] === false)
        ->andReturn(['status' => 'found', 'folder' => ['id' => 'id:exercise', 'name' => 'Vorhandene Arbeit'],
            'email_dispatch' => ['status' => 'sent', 'transport' => 'mailpit']]);
    $this->getJson($this->url.'?purpose=submission_collection&collection_mode=collect')->assertOk()
        ->assertJsonPath('data.context.exercise_folder.id', 'id:exercise')->assertJsonPath('data.context.task_delivery.transport', 'mailpit');
    Http::assertNothingSent();
});

test('submission collection preserves course access and manual mode boundaries', function (): void {
    Http::preventStrayRequests();
    $url = $this->url.'?purpose=submission_collection&collection_mode=repeat';
    $this->work->update(['status' => ['automation_enabled' => false]]);
    $this->getJson($url)->assertUnprocessable();
    $this->work->update(['status' => []]);
    $foreign = User::factory()->create(['school_id' => School::factory()->create()->id]);
    $foreign->assignRole('teacher');
    $this->actingAs($foreign, 'sanctum')->getJson($url)->assertForbidden();
    Http::assertNothingSent();
});

test('email preparation exports a confirmed exercise with the chosen form and a preparation-only instruction', function (string $addressForm): void {
    Http::preventStrayRequests();
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkModuleLink::query()->create(['teaching_course_work_id' => $this->work->id, 'dropbox_connection_id' => $connection->id,
        'account_id' => $connection->account_id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'rev1', 'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/Materialien/Modul']);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->once()->andReturn([
        'status' => 'found', 'folder' => ['id' => 'id:exercise', 'name' => '2026-10-20_Datenschutz', 'path' => '/Materialien/Modul/Leistungsfeststellung/2026-10-20_Datenschutz'],
    ]);
    $before = $this->work->fresh()->getAttributes();

    $data = $this->getJson($this->url.'?purpose=email_preparation&date=2026-10-20&address_form='.$addressForm)->assertOk()->json('data');
    expect($data['context']['purpose'])->toBe('prepare-exercise-emails')
        ->and($data['context']['delivery']['send_authorized'])->toBeFalse()
        ->and($data['context']['email_address_form'])->toBe($addressForm)
        ->and($data['context']['exercise_folder']['id'])->toBe('id:exercise')
        ->and($data['participants_count'])->toBe(1)
        ->and($data['text'])->toContain('send-exercise-via-email', 'Nichts senden', 'Schooltool-Versandvorbereitung.json', 'Erneut prüfen')
        ->and($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNothingSent();
})->with(['du', 'sie']);

test('email dispatch references the confirmed preparation with the explicitly selected delivery mode', function (string $deliveryMode, bool $local): void {
    $this->app['env'] = $local ? 'local' : 'production';
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'localhost', 'mail.mailers.smtp.port' => 1025, 'mail.mailers.smtp.url' => null]);
    Http::preventStrayRequests();
    Mail::fake();
    $this->teacher->update(['email' => 'admin@example.test']);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkModuleLink::query()->create(['teaching_course_work_id' => $this->work->id, 'dropbox_connection_id' => $connection->id,
        'account_id' => $connection->account_id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'rev1', 'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/Materialien/Modul']);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->once()->withArgs(
        fn ($connection, $work, $link, $date, $refresh, $initial): bool => $date === '2026-10-20' && $refresh === false && $initial === false,
    )->andReturn(['status' => 'found', 'folder' => ['id' => 'id:exercise', 'name' => '2026-10-20_Datenschutz'],
        'email_preparation' => ['status' => 'ready', 'recipients_count' => 1, 'prepared_at' => '2026-10-20T10:00:00+02:00']]);
    $before = $this->work->fresh()->getAttributes();

    $data = $this->getJson($this->url.'?purpose=email_dispatch&delivery_mode='.$deliveryMode.'&date=2026-10-20&recipient=forged@example.test')
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('data');

    expect($data['context']['purpose'])->toBe('send-exercise-emails')
        ->and($data['context']['delivery']['selection'])->toBe($deliveryMode)
        ->and($data['context']['delivery']['mode'])->toBe(($deliveryMode === 'test' ? 'test_' : 'live_').($local ? 'mailpit' : 'office_outlook'))
        ->and($data['context']['delivery']['send_authorized'])->toBeTrue()
        ->and($data['context']['delivery']['requires_explicit_send_confirmation'])->toBeFalse()
        ->and($data['context']['delivery']['record_dispatch'])->toBe($deliveryMode === 'live')
        ->and($data['context']['email_preparation']['status'])->toBe('ready')
        ->and($data['context']['exercise_folder']['id'])->toBe('id:exercise')
        ->and($data['context']['date'])->toBe('2026-10-20')
        ->and($data['context'])->not->toHaveKeys(['email_address_form', 'course_roster', 'work', 'materials'])
        ->and($data['participants_count'])->toBe(1)
        ->and($data['text'])->toContain('# Auftrag: E-Mails versenden', 'send-exercise-via-email', 'E-Mail-Vorbereitung', 'Dieser Auftrag ist die Versandfreigabe', 'ohne erneute Vorschau oder Freigabefrage')
        ->and($data['text'])->not->toContain('Änne', 'Ćelebić', 'private@example.test', 'course_roster', 'forged@example.test')
        ->and($this->work->fresh()->getAttributes())->toEqual($before);
    if ($deliveryMode === 'test') {
        expect($data['context']['delivery']['recipient'])->toBe('admin@example.test')
            ->and($data['context']['delivery']['recipient_user_id'])->toBe($this->teacher->id)
            ->and($data['context']['delivery']['messages_count'])->toBe(1)
            ->and($data['text'])->toContain('genau eine Testmail', 'CC/BCC', 'Keine Versandaufzeichnung anlegen', 'keinen Versandstatus ändern');
    } else {
        expect($data['context']['delivery'])->not->toHaveKey('recipient')
            ->and($data['text'])->toContain('Tatsächlich versenden', 'Doppelversand', 'protokollieren');
    }
    Http::assertNothingSent();
    Mail::assertNothingOutgoing();
    if ($local) {
        expect($data['context']['delivery']['smtp_host'])->toBe('127.0.0.1')
            ->and($data['context']['delivery']['smtp_port'])->toBe(1025)
            ->and($data['context']['delivery']['local_only'])->toBeTrue()
            ->and($data['text'])->toContain('Testversand über Mailpit', 'Niemals Office/Outlook', 'Schnellversand', 'keine neue Erstellung')
            ->and($data['text'])->not->toContain('ausschließlich das vorhandene Office-/Outlook-Konto');
    }
})->with([['live', false], ['test', false], ['live', true], ['test', true]]);

test('local dispatch refuses external or ambiguous transports before reading preparations', function (array $mail): void {
    $this->app['env'] = 'local';
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'localhost', 'mail.mailers.smtp.port' => 1025, 'mail.mailers.smtp.url' => null]);
    config($mail);
    Http::preventStrayRequests();
    Mail::fake();
    $this->getJson($this->url.'?purpose=email_dispatch&delivery_mode=live')->assertUnprocessable()
        ->assertJsonPath('message', 'Lokaler Versand gesperrt – Mailpit nicht konfiguriert');
    Http::assertNothingSent();
    Mail::assertNothingOutgoing();
})->with([
    [['mail.default' => 'postmark']],
    [['mail.mailers.smtp.host' => 'smtp.example.test']],
    [['mail.mailers.smtp.port' => 587]],
    [['mail.mailers.smtp.url' => 'smtp://external.example.test:587']],
]);

test('email dispatch with no cached check asks for an explicit recheck without calling Dropbox', function (): void {
    Http::preventStrayRequests();
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkModuleLink::query()->create(['teaching_course_work_id' => $this->work->id, 'dropbox_connection_id' => $connection->id,
        'account_id' => $connection->account_id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'rev1', 'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/Materialien/Modul']);

    $this->getJson($this->url.'?purpose=email_dispatch&delivery_mode=test')->assertUnprocessable()
        ->assertJsonPath('message', 'Bitte den LF-Status einmal mit „Erneut prüfen“ bestätigen.');

    Http::assertNothingSent();
});

test('email dispatch requires an explicit valid delivery mode before looking up preparations', function (?string $deliveryMode): void {
    Http::preventStrayRequests();
    $this->getJson($this->url.'?purpose=email_dispatch'.($deliveryMode !== null ? '&delivery_mode='.$deliveryMode : ''))
        ->assertUnprocessable()->assertJsonValidationErrors('delivery_mode');
    Http::assertNothingSent();
})->with([null, '', 'mailpit']);

test('test dispatch refuses an invalid logged-in recipient without falling back to any other address', function (string $email): void {
    Http::preventStrayRequests();
    Mail::fake();
    $this->teacher->update(['email' => $email]);

    $this->getJson($this->url.'?purpose=email_dispatch&delivery_mode=test&recipient=forged@example.test')
        ->assertUnprocessable()->assertJsonPath('message', 'Für das angemeldete Konto ist keine gültige E-Mail-Adresse für den Test hinterlegt.');

    Http::assertNothingSent();
    Mail::assertNothingOutgoing();
})->with(['not-an-address', '']);

test('email dispatch rejects missing or changed readiness even when the client claims it is ready', function (?string $status, string $deliveryMode): void {
    Http::preventStrayRequests();
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkModuleLink::query()->create(['teaching_course_work_id' => $this->work->id, 'dropbox_connection_id' => $connection->id,
        'account_id' => $connection->account_id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'rev1', 'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/Materialien/Modul']);
    $this->partialMock(DropboxModuleLinks::class)->shouldReceive('exercise')->once()->andReturn([
        'status' => 'found', 'folder' => ['id' => 'id:exercise'], 'email_preparation' => $status ? ['status' => $status] : null,
    ]);
    $before = $this->work->fresh()->getAttributes();

    $this->getJson($this->url.'?purpose=email_dispatch&delivery_mode='.$deliveryMode.'&email_preparation[status]=ready')->assertUnprocessable()
        ->assertJsonPath('message', 'Die E-Mails sind nicht als versandbereit bestätigt. Bitte die Vorbereitung erneut prüfen.');

    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNothingSent();
})->with(['no preparation' => [null], 'missing' => ['missing'], 'changed' => ['unknown']])->with(['live', 'test']);

test('email dispatch preserves course authorization', function (): void {
    Http::preventStrayRequests();
    $foreign = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');

    $this->actingAs($foreign, 'sanctum')->getJson($this->url.'?purpose=email_dispatch&delivery_mode=test')->assertForbidden();

    Http::assertNothingSent();
});

test('email dispatch stays unavailable when automation is disabled', function (): void {
    Http::preventStrayRequests();
    $this->work->update(['status' => ['automation_enabled' => false]]);

    $this->getJson($this->url.'?purpose=email_dispatch&delivery_mode=live')->assertUnprocessable()
        ->assertJsonPath('message', 'Automatik ist für diese Arbeit ausgeschaltet.');

    Http::assertNothingSent();
});

test('email preparation requires a created exercise and preserves course authorization', function (): void {
    $this->getJson($this->url.'?purpose=email_preparation')->assertUnprocessable()->assertJsonValidationErrors('address_form');
    $this->getJson($this->url.'?purpose=email_preparation&address_form=du')->assertUnprocessable();
    $foreign = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    $this->actingAs($foreign, 'sanctum')->getJson($this->url.'?purpose=email_preparation&address_form=sie')->assertForbidden();
});

beforeEach(function () {
    foreach (['teacher', 'admin', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id, 'name' => '2026/27', 'concerns' => '2026/27']);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $this->teacher->assignRole('teacher');
    $area = TeachingEntryArea::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'LF', 'name' => 'Leistungsfeststellung', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->teacher->id, 'title' => 'INF 2 Abend', 'classes' => ['Abend'], 'teaching_entry_area_id' => $area->id]);
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'type' => 'LF',
        'title' => 'Datenschutz', 'description' => 'Datenschutzfälle aus den hinterlegten Materialien.', 'date_for_all_groups' => '2026-10-20',
        'finish_until_date' => '2026-10-21', 'finish_until_time' => '20:15', 'is_group_work' => false, 'groups' => [], 'status' => []]);
    $this->student = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'first_name' => 'Änne', 'last_name' => 'Ćelebić', 'schoolclass' => 'Abend', 'email' => 'private@example.test', 'sex' => 'w']);
    $this->membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $this->student->id, 'import116_id' => null, 'canceled_at' => null]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id.'/ai-export';
    $this->actingAs($this->teacher, 'sanctum');
});

test('exercise handoff respects explicit MC selection and exports only active saved work candidates without changing work data', function (bool $mc): void {
    Http::preventStrayRequests();
    $other = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'first_name' => 'Andere', 'last_name' => 'Person', 'schoolclass' => 'Abend']);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $other->id, 'canceled_at' => null]);
    $this->work->update(['groups' => [['name' => 'Prüfungsgruppe', 'date' => '2026-10-20', 'student_ids' => [$this->student->id]]]]);
    $before = $this->work->fresh()->getAttributes();
    $data = $this->getJson($this->url.'?purpose=exercise&include_mc='.(int) $mc.'&date=2026-10-20')
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('data');

    expect($data['context']['include_multiple_choice'])->toBe($mc);
    expect($data['context']['course_roster']['participants'])->toHaveCount(1);
    expect($data['context']['course_roster']['participants'][0]['schooltool_person_id'])->toBe((string) $this->student->id);
    expect($data['context']['work']['groups'][0]['name'])->toBe('Prüfungsgruppe');
    expect($data['context']['work']['main_deadline'])->toBe(['date' => '2026-10-21', 'time' => '20:15']);
    expect($data['text'])->toContain('Skill create-exercise', 'keine zweite Schooltool-Arbeit', 'Modul: nicht verknüpft',
        $mc ? 'Multiple-Choice-Fragen einbeziehen: Ja.' : 'Multiple-Choice-Fragen einbeziehen: Nein.');
    expect($data['text'])->not->toContain('Andere');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNothingSent();
})->with([false, true]);

test('exercise handoff excludes cancelled memberships and does not revive a cancelled assigned candidate', function (): void {
    $this->membership->update(['canceled_at' => now()]);
    $other = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $other->id, 'canceled_at' => null]);
    $this->work->update(['groups' => [['student_ids' => [$this->student->id]]]]);
    $this->getJson($this->url.'?purpose=exercise&include_mc=0')->assertUnprocessable()->assertJsonValidationErrors('roster');
});

test('exercise handoff includes a real saved module path and identity without treating an import directory as the module', function (): void {
    Http::preventStrayRequests();
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkModuleLink::query()->create(['dropbox_connection_id' => $connection->id,
        'teaching_course_work_id' => $this->work->id, 'module_id' => 'A263B873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'account_id' => $connection->account_id, 'folder_id' => 'id:module', 'folder_name' => 'Modul',
        'folder_path' => '/Materialien/Lerninhalte/Modul', 'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'r1']);
    $data = $this->getJson($this->url.'?purpose=exercise&include_mc=1')->assertOk()->json('data');

    expect($data['context']['module_binding']['module_id'])->toBe('A263B873-4f27-4167-90f0-7d94ea0f67fa');
    expect($data['text'])->toContain('Modul: verknüpft – Dropbox: /Materialien/Lerninhalte/Modul', 'Assessment Template', 'Multiple Choice Fragen');
    expect($data['text'])->not->toContain('Keine Arbeitsordnerbindung');
    Http::assertNothingSent();
});

test('exercise handoff requires a conscious MC choice and a real saved date and permits authorized manual work text export', function (): void {
    $this->getJson($this->url.'?purpose=exercise')->assertUnprocessable()->assertJsonValidationErrors('include_mc');
    $this->getJson($this->url.'?purpose=exercise&include_mc=0&date=2026-11-25')->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->work->update(['status' => ['automation_enabled' => false]]);
    $this->getJson($this->url.'?purpose=exercise&include_mc=0')->assertOk();
    $this->getJson($this->url)->assertUnprocessable();
    $foreign = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id])->assignRole('teacher');
    $this->actingAs($foreign, 'sanctum')->getJson($this->url.'?purpose=exercise&include_mc=0')->assertForbidden();
});

test('folder initialization prompt requests only create exercise part one and retains the structured work data', function (): void {
    Http::preventStrayRequests();
    $this->course->update(['title' => 'DGB - 3B - 1', 'classes' => ['3B']]);
    $this->student->update(['schoolclass' => '3B']);
    $this->work->update(['title' => 'E-Mails', 'date_for_all_groups' => '2026-11-10']);
    $before = $this->work->fresh()->getAttributes();

    $data = $this->getJson($this->url)->assertOk()->json('data');

    $instructions = explode('## Schooltool-Daten', $data['text'], 2)[0];
    expect($instructions)->toStartWith('# Auftrag an Codex: Create Exercise – Teil 1: Ordner und Schülerliste')
        ->toContain('ausschließlich Teil 1', 'VOR jeglicher Anlage', 'check_existing_exercise.py',
            'Schooltool_Arbeit_ID '.$this->work->id, 'Schooltool_Kurs_ID '.$this->course->id,
            'auch unvollständig: ABBRECHEN', 'nichts anlegen, ergänzen oder verändern',
            'ANDEREM Durchführungstermin und einer anderen belegten Schooltool-Arbeitsidentität',
            'kein identischer Auftrag und kein automatischer Duplikat-Abbruch', 'Abbruch oder neue Anlage mit neuem Datum?',
            'Erst nach expliziter Fortsetzungsfreigabe', 'für genau diese Arbeitsidentität und den neuen Durchführungstermin',
            'nicht erneut dieselbe Zustimmung verlangen', 'Der gesamte Altbestand einschließlich Ordnern, Dateien, Metadaten und History bleibt unberührt',
            'weder einen zweiten Ordner für dieselbe belegte Schooltool-Arbeitsidentität', 'echter Ziel- oder ID-Konflikte',
            'Lerninhalt-Quellordner', 'Leistungsfeststellung', 'work.scheduled_date', 'work.groups[].date',
            'technical_created_at, exported_at und ein ungeprüftes heutiges Datum sind keine Durchführungstermine',
            'course.folder_group', 'fehlende Kursgruppen nicht aus Arbeitsgruppen erfinden',
            'exakt diese fünf Unterordner: Schülerlisten, Angaben, Abgaben, Auswertungen, Transaktionen',
            'Schüler als JSON im Unterordner Schülerlisten', 'Die vollständige Kursliste', 'keine pauschale Teilnehmerfreigabe',
            'schooltool_person_id, import116_id und course_assignments', 'Belegen Sie in der JSON-Liste die Quelle',
            'sämtliche tatsächlich ausgeführten Aktionen chronologisch in Transaktionen/History.json',
            'Testordneranlage, jede Unterordneranlage und das Schreiben der Schülerlisten-JSON',
            'echten Zeitpunkt der Aktion mit Zeitzone', 'betroffenen tatsächlichen Pfade',
            'Ereignisse nicht auf den Durchführungstermin zurückdatieren',
            'tatsächliche Ergebnis einschließlich aufgetretener Fehler', 'keine Prüfzeiten oder Erfolge erfinden',
            'Bei Duplikat-Abbruch darf im vorhandenen Ordner auch keine History angelegt, ergänzt oder verändert werden',
            'keine weiteren Stufen von create-exercise')
        ->not->toContain('Markdown', 'PDF', 'Bewertungskriterien', 'MC-Fragen', 'Zugangslinks', 'Versandvorbereitung',
            'Schooltool-Import', 'setzen Sie nur die fehlenden Schritte', '5L2_Gruppe-1', 'IT_Grundlagen/emails');
    expect($data['context']['work']['scheduled_date'])->toBe('2026-11-10')
        ->and($data['context']['work']['main_deadline'])->toBe(['date' => '2026-10-21', 'time' => '20:15'])
        ->and($data['context']['course']['classes'])->toBe(['3B'])
        ->and($data['context']['course']['group_number'])->toBe(1)
        ->and($data['context']['course']['folder_group'])->toBe('3B_Gruppe-1')
        ->and($data['context']['course_roster']['participants'][0]['schooltool_person_id'])->toBe((string) $this->student->id)
        ->and($data['context']['course_roster']['participants'][0]['course_assignments'][0]['id'])->toBe($this->membership->id);
    preg_match('/```json\n(.*?)\n```/s', $data['text'], $matches);
    expect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR))->toBe($data['context'])
        ->and($this->work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
});

test('AI export includes student email addresses without personal assessment or family contact data', function () {
    Http::preventStrayRequests();
    $second = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'first_name' => 'Béa', 'last_name' => 'Beta', 'schoolclass' => 'Abend']);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $second->id, 'import116_id' => null, 'canceled_at' => null]);
    $alias = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->student->id,
        'first_name' => $this->student->first_name, 'last_name' => $this->student->last_name, 'class' => $this->student->schoolclass,
        'mother_email' => 'mother@example.test', 'father_email' => 'father@example.test', 'sex' => 'w']);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => null, 'import116_id' => $alias->id, 'canceled_at' => null]);
    $this->work->update(['is_group_work' => true, 'groups' => [['name' => 'Gruppe A', 'student_ids' => [$this->student->id, $this->student->id], 'comments' => [['student_id' => $this->student->id, 'comment' => 'Private assessment']]],
        ['name' => 'Gruppe B', 'student_ids' => [$this->student->id]]]]);
    $before = $this->work->fresh()->getAttributes();
    $data = $this->getJson($this->url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('data');
    expect($data['roster_complete'])->toBeTrue()->and($data['participants_count'])->toBe(2);
    expect(array_column($data['context']['course_roster']['participants'], 'last_name'))->toBe(['Beta', 'Ćelebić']);
    expect($data['context']['course_roster']['participants'][1]['course_assignments'])->toHaveCount(2);
    expect($data['context']['work']['groups'][0]['schooltool_student_ids'])->toBe([$this->student->id]);
    expect($data['context']['course_roster']['participants'][1]['email'])->toBe('private@example.test');
    expect($data['context']['course_roster']['participants'][1]['sex'])->toBe('w');
    expect($data['text'])->toContain('Änne')->toContain('private@example.test')->not->toContain('Private assessment')
        ->not->toContain('mother@example.test')->not->toContain('father@example.test');
    expect($data['context']['work']['scheduled_date'])->toBe('2026-10-20');
    expect($data['context']['work']['main_deadline'])->toBe(['date' => '2026-10-21', 'time' => '20:15']);
    expect($data['context']['work']['communicated_deadline'])->toBeNull();
    expect($data['context']['work']['maximum_points'])->toEqual(5);
    expect($this->work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
});

test('AI export keeps the optional first deadline separate without claiming communication', function () {
    $this->work->update(['status' => ['communicated_deadline_at' => '2026-10-20T20:00:00+02:00']]);
    $before = $this->work->fresh()->getAttributes();

    $data = $this->getJson($this->url)->assertOk()->json('data');

    expect($data['context']['work']['communicated_deadline'])->toBe([
        'at' => '2026-10-20T20:00:00+02:00', 'date' => '2026-10-20', 'time' => '20:00',
    ])->and($data['context']['work']['main_deadline'])->toBe(['date' => '2026-10-21', 'time' => '20:15'])
        ->and($data['text'])->toContain('kein Nachweis einer tatsächlichen Mitteilung')
        ->and($this->work->fresh()->getAttributes())->toBe($before);
});

test('AI export preserves the course group for folder naming independently of work groups', function (string $title, array $classes, ?int $number, ?string $folderGroup) {
    $this->course->update(['title' => $title, 'classes' => $classes]);
    $this->work->update(['is_group_work' => false, 'groups' => [['name' => 'Arbeitsgruppe 9', 'student_ids' => [$this->student->id]]]]);
    $before = $this->course->fresh()->getAttributes();

    $context = $this->getJson($this->url)->assertOk()->json('data.context');

    expect($context['course']['title'])->toBe($title);
    expect($context['course']['group_number'])->toBe($number);
    expect($context['course']['folder_group'])->toBe($folderGroup);
    expect($context['course']['group_source'])->toBe($number !== null ? 'course_title' : null);
    expect($context['work']['is_group_work'])->toBeFalse();
    expect($this->course->fresh()->getAttributes())->toBe($before);
})->with([
    'first course group' => ['INF - 5L2 - 1', ['5L2'], 1, '5L2_Gruppe-1'],
    'second course group' => ['DGB - 3B - 2', ['3B'], 2, '3B_Gruppe-2'],
    'unnumbered course' => ['INF 2 Abend', ['Abend'], null, null],
    'class mismatch' => ['INF - 5L2 - 1', ['5L1'], null, null],
    'multiple classes need explicit context' => ['INF - 5L2 - 1', ['5L2', '5L1'], null, null],
    'number in another title format' => ['INF 5L2 1', ['5L2'], null, null],
]);

test('AI export is freshly read and preserves imported-only members and cancelled membership context', function () {
    $first = $this->getJson($this->url)->assertOk()->json('data');
    expect($first['participants_count'])->toBe(1);
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'first_name' => 'Unbewertet', 'last_name' => 'Neu', 'class' => 'Abend', 'user_id' => null, 'email' => 'neu@example.test', 'sex' => 'm']);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => null, 'import116_id' => $import->id, 'canceled_at' => null]);
    $this->membership->update(['canceled_at' => now()]);
    $data = $this->getJson($this->url)->assertOk()->json('data');
    expect($data['participants_count'])->toBe(2)->and($data['active_participants_count'])->toBe(1);
    $person = collect($data['context']['course_roster']['participants'])->firstWhere('import116_id', $import->id);
    expect($person['schooltool_person_id'])->toBeNull()->and($person['active'])->toBeTrue();
    expect($person['email'])->toBe('neu@example.test');
    expect($person['sex'])->toBe('m');
});

test('AI export uses the real account email or falls back to the verified import', function (string $accountEmail, ?string $importEmail, ?string $expected) {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->student->id,
        'first_name' => $this->student->first_name, 'last_name' => $this->student->last_name, 'class' => $this->student->schoolclass, 'email' => $importEmail]);
    $this->student->update(['email' => $accountEmail, 'import116_id' => $import->id]);

    $data = $this->getJson($this->url)->assertOk()->json('data');

    expect($data['context']['course_roster']['participants'][0]['email'])->toBe($expected);
    $missingEmailWarnings = array_values(array_filter($data['warnings'], fn (string $warning): bool => str_contains($warning, 'Fehlende E-Mail-Adressen')));
    expect($missingEmailWarnings)->toHaveCount($expected === null ? 1 : 0);
    expect($data['text'])->not->toContain('@schooltool.noemail');
})->with([
    'account address' => ['student@example.test', 'import@example.test', 'student@example.test'],
    'empty account address' => ['', 'import@example.test', 'import@example.test'],
    'placeholder account address' => ['noemail.student@schooltool.noemail', 'import@example.test', 'import@example.test'],
    'missing import address' => ['noemail.student@schooltool.noemail', null, null],
    'placeholder import address' => ['noemail.student@schooltool.noemail', 'noemail.import@schooltool.noemail', null],
]);

test('AI export resolves email for import memberships linked to an existing account', function () {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->student->id,
        'first_name' => $this->student->first_name, 'last_name' => $this->student->last_name, 'class' => $this->student->schoolclass]);
    $this->membership->update(['user_id' => null, 'import116_id' => $import->id]);

    $this->getJson($this->url)->assertOk()->assertJsonPath('data.context.course_roster.participants.0.email', 'private@example.test');
});

test('AI export preserves recorded sex and leaves missing values unknown', function (?string $accountSex, ?string $importSex, ?string $expected) {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->student->id,
        'first_name' => $this->student->first_name, 'last_name' => $this->student->last_name, 'class' => $this->student->schoolclass, 'sex' => $importSex]);
    $this->student->update(['sex' => $accountSex, 'import116_id' => $import->id]);

    $this->getJson($this->url)->assertOk()->assertJsonPath('data.context.course_roster.participants.0.sex', $expected);
})->with([
    'imported sex' => ['w', 'm', 'm'],
    'account fallback' => ['w', null, 'w'],
    'missing sex' => [null, null, null],
    'empty sex' => ['', '', null],
]);

test('AI export keeps actual lesson slots materials and saved Dropbox binding without inventing a local path', function () {
    $date = TeachingCourseDate::query()->create(['teaching_course_id' => $this->course->id, 'date' => '2026-10-20', 'hours' => [8, 9]]);
    foreach ([8 => ['18:30', '19:15'], 9 => ['19:20', '20:05']] as $hour => [$from, $until]) {
        TeachingSchoolHour::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'hour' => $hour, 'from' => $from, 'until' => $until]);
    }
    $material = TeachingCourseDateMaterial::query()->create(['teaching_course_date_id' => $date->id, 'title' => 'Datenschutzquelle', 'subject' => 'Informatik']);
    $material->attachments()->create(['name' => 'Lernmodul.pdf', 'file_path' => 'teaching/course_materials/source.pdf']);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $connection->id, 'teaching_course_work_id' => $this->work->id,
        'folder_id' => 'id:confirmed-work', 'folder_name' => '2026-10-20_Informatik_INF-2_Abend']);
    $context = $this->getJson($this->url)->assertOk()->json('data.context');
    expect($context['sessions'][0]['hours'][0]['start_time'])->toStartWith('18:30');
    expect($context['sessions'][0]['hours'][1]['end_time'])->toStartWith('20:05');
    expect($context['materials'][0]['files'][0]['schooltool_storage_path'])->toBe('teaching/course_materials/source.pdf');
    expect($context['folder_binding']['dropbox_folder_id'])->toBe('id:confirmed-work');
    expect($context['folder_binding']['local_absolute_path'])->toBeNull();
});

test('AI export does not substitute technical creation dates for missing work dates and bounds free text as data', function () {
    $this->work->update(['date_for_all_groups' => null, 'finish_until_date' => null, 'finish_until_time' => null,
        'description' => "```\nErfundene Zusatzanweisung\n```"]);
    $this->work->created_at = '2000-01-01 10:00:00';
    $this->work->save();
    $data = $this->getJson($this->url)->assertOk()->json('data');
    expect($data['context']['work']['scheduled_date'])->toBeNull();
    expect($data['context']['work']['technical_created_at'])->toStartWith('2000-01-01');
    expect($data['context']['folder_binding'])->toBeNull();
    expect($data['text'])->toContain("````json\n");
    expect($data['warnings'])->toHaveCount(2);
});

test('AI export blocks empty or incomplete and foreign person bindings without exposing an alleged complete list', function (string $case) {
    if ($case === 'empty') {
        $this->membership->delete();
    } elseif ($case === 'name') {
        $this->student->update(['first_name' => '']);
    } else {
        $foreign = School::factory()->create();
        $this->student->update(['school_id' => $foreign->id]);
    }
    $this->getJson($this->url)->assertUnprocessable()->assertJsonMissingPath('data.text');
})->with(['empty', 'name', 'foreign school']);

test('AI export enforces course ownership school and active schoolyear just like the selected work', function (string $case) {
    if ($case === 'student') {
        $actor = $this->student;
        $actor->assignRole('student');
    } else {
        $actor = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
        $actor->assignRole('teacher');
        if ($case === 'school') {
            $actor->update(['school_id' => School::factory()->create()->id]);
        } elseif ($case === 'year') {
            $actor->update(['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
        }
    }
    $this->actingAs($actor, 'sanctum')->getJson($this->url)->assertForbidden()->assertJsonMissingPath('data.text');
})->with(['owner', 'school', 'year', 'student']);

test('the authorised school admin can export the actual teacher-owned work', function () {
    $admin = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $admin->assignRole('admin');
    $connection = DropboxConnection::factory()->create(['user_id' => $admin->id]);
    TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $connection->id, 'teaching_course_work_id' => $this->work->id,
        'folder_id' => 'id:admin-owned-choice', 'folder_name' => 'Admin choice']);
    $this->actingAs($admin, 'sanctum')->getJson($this->url)->assertOk()->assertJsonPath('data.context.work.id', $this->work->id)
        ->assertJsonPath('data.context.folder_binding.dropbox_folder_id', 'id:admin-owned-choice')->assertJsonPath('data.context.work.maximum_points', 5);
});

test('AI export preserves the existing legacy work definition and stored assessment criteria without personal earned values', function () {
    TeachingSchema::query()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->teacher->id, 'schema_id' => 'legacy-ai-export', 'name' => 'Bestehendes Schema', 'grading' => [],
        'works' => [['short_name' => 'LF', 'name' => 'Bekannte Leistungsfeststellung', 'grades' => [['grade' => '1', 'value' => '1']]]]]);
    $this->course->update(['teaching_schema_id' => 'legacy-ai-export', 'teaching_entry_area_id' => null]);
    $this->work->update(['status' => ['assessment_json_packages' => [['exercise_id' => 'source-1', 'exercise' => ['subject' => 'Informatik']]],
        'assessment_json_imports' => [['exercise_id' => 'source-1', 'record' => ['criteria' => [['criterion' => 'Begründung', 'maximum_minor' => 200, 'earned_minor' => 123, 'reason' => 'Private Rückmeldung']]]]]]]);
    $data = $this->getJson($this->url)->assertOk()->json('data');
    expect($data['context']['work']['type_definition']['name'])->toBe('Bekannte Leistungsfeststellung');
    expect($data['context']['work']['criteria'])->toBe([['criterion' => 'Begründung', 'maximum_minor' => 200]]);
    expect($data['text'])->not->toContain('Private Rückmeldung')->not->toContain('earned_minor');
    expect($data['context']['historical_source_metadata']['subject'])->toBe('Informatik');
});

test('AI export uses the verified current imported identity instead of outdated account names', function () {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->student->id,
        'first_name' => 'Aktuelle Änne', 'last_name' => 'Ćelebić', 'class' => 'Abend']);
    $this->student->update(['import116_id' => $import->id]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('data.context.course_roster.participants.0.first_name', 'Aktuelle Änne');
});

test('AI export rejects a foreign private curriculum even if it has been incorrectly linked to an owned course', function () {
    $foreignSchool = School::factory()->create();
    $foreignTeacher = User::factory()->create(['school_id' => $foreignSchool->id]);
    $curriculum = TeachingCurriculum::query()->create(['school_id' => $foreignSchool->id, 'user_id' => $foreignTeacher->id, 'title' => 'Privater fremder Lehrplan']);
    $this->course->update(['teaching_curriculum_id' => $curriculum->id]);
    $this->getJson($this->url)->assertUnprocessable()->assertJsonMissingPath('data.text');
});

test('AI export blocks work groups with persons outside the course instead of exposing foreign member identifiers', function () {
    $this->work->update(['is_group_work' => true, 'groups' => [['name' => 'Fehlerhafte Gruppe', 'student_ids' => [999999]]]]);
    $this->getJson($this->url)->assertUnprocessable()->assertJsonMissingPath('data.context');
});
