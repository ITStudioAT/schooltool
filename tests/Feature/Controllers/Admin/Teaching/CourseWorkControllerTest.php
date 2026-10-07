<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Support\SchooltoolAssessmentJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\TeachingWorkDispatchFixture;
use Tests\Support\TeachingWorkEvaluationFixture;
use Tests\Support\TeachingWorkJsonFixture;

uses(RefreshDatabase::class);

function jsonAssessmentForWork(object $context, TeachingCourseWork $work): array
{
    $package = TeachingWorkJsonFixture::package();
    $package['records'][0]['identity']['class_name'] = '1A';
    $groups = $work->groups;
    $groups[0]['name'] = 'Gruppe 1';
    $groups[1]['name'] = 'Gruppe 1';
    $groups[0]['use_individual_grades'] = true;
    $groups[1]['use_individual_grades'] = true;
    $work->update(['groups' => $groups, 'is_group_work' => true]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

    return $package;
}

test('JSON teacher absence zero assessment previews without writes and applies only after fresh confirmation', function () {
    $this->travelTo('2026-10-07T11:00:00Z');
    $work = prepareWorkDispatchImport($this);
    $package = TeachingWorkJsonFixture::withTeacherAbsenceDecision(jsonAssessmentForWork($this, $work));
    $work->update(['finish_until_date' => '2026-10-07', 'finish_until_time' => '12:00']);
    $before = $work->fresh()->getAttributes();
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];

    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');

    expect($preview['can_import'])->toBeTrue()
        ->and($preview['rows'][0]['total_minor'])->toBe(0)
        ->and($preview['rows'][0]['will_replace'])->toBeTrue()
        ->and($preview['submission_check'])->toBeNull()
        ->and($preview['teacher_absence_decision'])->toBe($package['teacher_absence_decision'])
        ->and($work->fresh()->getAttributes())->toBe($before);

    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $work = $work->fresh();
    expect($work->groups[0]['points'][0]['points'])->toBe('0.00')
        ->and($work->groups[0]['comments'][0]['comment'])->toBe('Innerhalb der Frist nicht abgegeben')
        ->and($work->status['assessment_json_packages'][0]['teacher_absence_decision'])->toEqual($package['teacher_absence_decision'])
        ->and($work->status)->not->toHaveKey('submission_checks');
});

test('JSON teacher absence decision rejects target deadline changes and stale confirmation without writes', function (string $case) {
    $this->travelTo('2026-10-07T11:00:00Z');
    $work = prepareWorkDispatchImport($this);
    $package = TeachingWorkJsonFixture::withTeacherAbsenceDecision(jsonAssessmentForWork($this, $work));
    $work->update(['finish_until_date' => '2026-10-07', 'finish_until_time' => '12:00']);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $hash = $this->postJson($url, $payload)->assertOk()->json('preview.hash');
    $work->update($case === 'deadline' ? ['finish_until_time' => '12:01'] : ['title' => 'Changed']);
    $before = $work->fresh()->getAttributes();

    $this->postJson($url, $payload + ['apply' => true, 'hash' => $hash])->assertStatus($case === 'deadline' ? 422 : 409);

    expect($work->fresh()->getAttributes())->toBe($before);
})->with(['deadline', 'stale confirmation']);

test('JSON submission checkpoint is previewed applied idempotently and invalidated by changed target boundaries', function () {
    $work = prepareWorkDispatchImport($this);
    $package = TeachingWorkJsonFixture::withSubmissionCheck(jsonAssessmentForWork($this, $work));
    $work->update(['finish_until_date' => '2026-10-06', 'finish_until_time' => '12:00']);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $preview = $this->postJson($url, $payload)->assertOk()->assertJsonPath('preview.submission_check.state', 'complete')->json('preview');
    expect($work->fresh()->status)->not->toHaveKey('submission_checks');
    $payload += ['apply' => 1, 'hash' => $preview['hash']];
    $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.submission_check_status.complete', true);
    expect($work->fresh()->status['submission_checks'])->toHaveCount(1);
    $preview = $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package)])->assertOk()->json('preview');
    $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package), 'apply' => 1, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->status['submission_checks'])->toHaveCount(1);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['status' => ['submission_checks' => []]])->assertOk();
    expect($work->fresh()->status['submission_checks'])->toHaveCount(1);
    $work->update(['finish_until_time' => '12:01']);
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.submission_check_status.complete', false);
    $work->update(['finish_until_time' => '12:00']);
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.submission_check_status.complete', true);
    $this->openStudent->update(['schoolclass' => 'OTHER']);
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.submission_check_status.complete', false);
});

test('JSON submission checkpoint rejects changed deadline incomplete roster and stale apply atomically', function (string $case) {
    $work = prepareWorkDispatchImport($this);
    $package = TeachingWorkJsonFixture::withSubmissionCheck(jsonAssessmentForWork($this, $work));
    $work->update(['finish_until_date' => '2026-10-06', 'finish_until_time' => '12:00']);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $hash = $this->postJson($url, $payload)->assertOk()->json('preview.hash');
    if ($case === 'deadline') {
        $work->update(['finish_until_time' => '12:01']);
    } elseif ($case === 'no time') {
        $work->update(['finish_until_time' => null]);
    } elseif ($case === 'roster') {
        $work->teachingCourse->teachingCourseStudents()->where('user_id', $this->openStudent->id)->update(['canceled_at' => now()]);
    } else {
        $work->update(['title' => 'Changed']);
    }
    $this->postJson($url, $payload + ['apply' => 1, 'hash' => $hash])->assertStatus($case === 'stale preview' ? 409 : 422);
    expect($work->fresh()->status)->not->toHaveKey('submission_checks')->not->toHaveKey('assessment_json_imports');
})->with(['deadline', 'no time', 'roster', 'stale preview']);

test('JSON submission checkpoint preserves history rejects older proofs and requires a fresh proof for changed submissions', function () {
    $work = prepareWorkDispatchImport($this);
    $package = TeachingWorkJsonFixture::withSubmissionCheck(jsonAssessmentForWork($this, $work));
    $work->update(['finish_until_date' => '2026-10-06', 'finish_until_time' => '12:00']);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $apply = function (array $data) use ($url): void {
        $payload = ['package' => TeachingWorkJsonFixture::upload($data)];
        $hash = $this->postJson($url, $payload)->assertOk()->json('preview.hash');
        $this->postJson($url, $payload + ['apply' => 1, 'hash' => $hash])->assertOk();
    };
    $apply($package);
    $changed = $package;
    $changed['records'][0]['source_fingerprint'] = str_repeat('b', 64);
    $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($changed)])->assertUnprocessable();
    unset($changed['submission_check']);
    $apply($changed);
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.submission_check_status.complete', false);
    $changed['submission_check'] = $package['submission_check'];
    $changed['submission_check']['checked_at'] = $changed['submission_check']['completed_at'] = '2026-10-06T10:02:00Z';
    foreach ($changed['submission_check']['coverage'] as &$coverage) {
        $coverage['end_at'] = '2026-10-06T10:02:00Z';
    }
    unset($coverage, $changed['submission_check']['check_checksum']);
    $changed['submission_check']['check_checksum'] = SchooltoolAssessmentJson::digest($changed['submission_check']);
    $apply($changed);
    expect($work->fresh()->status['submission_checks'])->toHaveCount(2);
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.submission_check_status.complete', true);
    $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package)])->assertUnprocessable();
    expect($work->fresh()->status['submission_checks'])->toHaveCount(2);
});

test('JSON folder previews and imports original dispatch evidence atomically and idempotently', function () {
    Mail::fake();
    Notification::fake();
    $work = prepareWorkDispatchImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $folder = '2026-10-04_Test';
    $text = TeachingWorkDispatchFixture::officeResultsText();
    $documents = [['path' => $folder.'/Versand/Ergebnisse/Versand_2026-10-04_02-12-33/Versandprotokoll.txt', 'text' => $text]];
    $payload = ['package' => TeachingWorkJsonFixture::upload($package), 'folder' => $folder, 'documents' => json_encode($documents, JSON_THROW_ON_ERROR)];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['dispatches'][0]['purpose'])->toBe('results')
        ->and($work->fresh()->status['dispatch_logs'] ?? [])->toBe([])
        ->and($work->fresh()->status['assessment_json_imports'] ?? [])->toBe([]);
    $changed = $payload;
    $changed['documents'] = json_encode([array_replace($documents[0], ['text' => $text."\n"])], JSON_THROW_ON_ERROR);
    $this->postJson($url, $changed + ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $status = $work->fresh()->status;
    expect($status['dispatch_logs'])->toHaveCount(1)
        ->and($status['dispatch_notifications'])->toHaveCount(1)
        ->and($status['dispatch_notifications'][0]['purpose'])->toBe('results')
        ->and($status['dispatch_notifications'][0]['student_id'])->toBe($this->student->id)
        ->and(Storage::disk('local')->get($status['dispatch_logs'][0]['file_path']))->toBe($text);
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->status['dispatch_logs'])->toHaveCount(1)
        ->and($work->fresh()->status['dispatch_notifications'])->toHaveCount(1);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

test('JSON folder Office results require confirmed send state and explicit verified attachment lists', function (string $case) {
    $work = prepareWorkDispatchImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $text = TeachingWorkDispatchFixture::officeResultsText();
    $metadata = json_decode(substr($text, strpos($text, '{')), true, flags: JSON_THROW_ON_ERROR);
    if ($case === 'unconfirmed') {
        $metadata['Empfaenger'][0]['Office_Zustand']['SentConfirmed'] = false;
    } else {
        unset($metadata['Empfaenger'][0]['Anhaenge']);
    }
    $text = substr($text, 0, strpos($text, '{')).json_encode($metadata, JSON_THROW_ON_ERROR);
    $folder = '2026-10-04_Test';
    $payload = ['package' => TeachingWorkJsonFixture::upload($package), 'folder' => $folder,
        'documents' => json_encode([['path' => $folder.'/Versand/Ergebnisse/Versand_2026-10-04_02-12-33/Versandprotokoll.txt', 'text' => $text]], JSON_THROW_ON_ERROR)];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->status['dispatch_logs'])->toHaveCount(1)
        ->and($work->fresh()->status['dispatch_notifications'])->toBe([]);
})->with(['unconfirmed', 'missing attachments']);

test('JSON folder blocks mismatched dispatch identities before applying assessments', function () {
    $work = prepareWorkDispatchImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $recipients = TeachingWorkDispatchFixture::recipients();
    $recipients[0]['To'] = 'wrong@example.test';
    $folder = '2026-10-04_Test';
    $payload = ['package' => TeachingWorkJsonFixture::upload($package), 'folder' => $folder,
        'documents' => json_encode([['path' => $folder.'/Versand/Ergebnisse/Versand_2026-10-04_02-12-33/Versandprotokoll.txt', 'text' => TeachingWorkDispatchFixture::text($recipients)]], JSON_THROW_ON_ERROR)];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['can_import'])->toBeFalse();
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertUnprocessable();
    expect($work->fresh()->status['assessment_json_imports'] ?? [])->toBe([])
        ->and($work->fresh()->status['dispatch_logs'] ?? [])->toBe([]);
});

test('JSON assessment event times retain version history and preserve open grades with idempotent receipts', function (string $nextState) {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $package['records'][0]['email_collected_at'] = '2026-10-06T21:50:00Z';
    $package['records'][0]['evaluation_completed_at'] = '2026-10-07T00:10:00Z';
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['rows'][0]['event_times']['email_collected_at'])->toBe('2026-10-06T21:50:00Z')
        ->and($work->fresh()->status['assessment_json_imports'] ?? [])->toBe([]);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $grades = $work->fresh()->groups;
    $previous = $work->fresh()->status['assessment_json_imports'][0];
    $record = &$package['records'][0];
    $record['evaluation_completed_at'] = null;
    unset($record['email_collected_at']);
    $record['evaluation_state'] = $nextState;
    if ($nextState !== 'complete') {
        $record['total_minor'] = null;
        $record['criteria'][0]['earned_minor'] = null;
    }
    if ($nextState === 'partial') {
        $package['rubric'][0]['maximum_minor'] = 300;
        $package['rubric'][] = ['criterion' => 'Weitere Prüfung', 'maximum_minor' => 200];
        $record['criteria'][0]['maximum_minor'] = 300;
        $record['criteria'][] = ['criterion' => 'Weitere Prüfung', 'maximum_minor' => 200, 'earned_minor' => 100, 'checkability' => 'checkable', 'reason' => 'Vorläufig'];
    }
    unset($record);
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['rows'][0]['previous_event_times']['evaluation_completed_at'])->toBe('2026-10-07T00:10:00Z')
        ->and($preview['rows'][0]['event_times']['evaluation_completed_at'])->toBeNull();
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $receipt = $work->fresh()->status['assessment_json_imports'][0];
    expect($receipt['record']['evaluation_completed_at'])->toBeNull()
        ->and($receipt['history'][0]['record']['evaluation_completed_at'])->toBe('2026-10-07T00:10:00Z')
        ->and($receipt['history'][0]['record']['email_collected_at'])->toBe('2026-10-06T21:50:00Z')
        ->and($receipt['history'][0]['record_checksum'])->toBe($previous['record_checksum'])
        ->and($work->fresh()->groups)->toBe($grades);
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->status['assessment_json_imports'][0])->toBe($receipt);
    $package['records'][0]['email_collected_at'] = '2026-10-07T02:00:00Z';
    $changed = ['package' => TeachingWorkJsonFixture::upload($package)];
    $this->postJson($url, $changed + ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $preview = $this->postJson($url, $changed)->assertOk()->json('preview');
    $this->postJson($url, $changed + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->status['assessment_json_imports'][0]['record']['email_collected_at'])->toBe('2026-10-07T02:00:00Z')
        ->and($work->fresh()->status['assessment_json_imports'][0]['history'])->toHaveCount(2)
        ->and($work->fresh()->groups)->toBe($grades);
})->with(['open', 'partial', 'complete']);

test('JSON assessment previews separately applies 475 hundredths and preserves manually corrected repeats', function () {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $before = $work->fresh()->groups;
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($work->fresh()->groups)->toBe($before)
        ->and($preview['rows'][0]['total_minor'])->toBe(475)
        ->and($preview['rows'][0]['status'])->toBe('Neu');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect((float) $work->fresh()->groups[0]['points'][0]['points'])->toBe(4.75)
        ->and($work->fresh()->groups[0]['comments'][0]['comment'])->toBe($package['records'][0]['comment']);
    $groups = $work->fresh()->groups;
    $groups[0]['points'][0]['points'] = 3.5;
    $groups[0]['grades'][0]['grade'] = '3.5';
    $work->update(['groups' => $groups]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);
    $repeat = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($repeat['rows'][0]['status'])->toBe('Unverändert');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $repeat['hash']])->assertOk();
    expect($work->fresh()->groups[0]['points'][0]['points'])->toBe(3.5)
        ->and($work->fresh()->status['assessment_json_packages'])->toHaveCount(1);
    $package['records'][0]['total_minor'] = 450;
    $package['records'][0]['criteria'][0]['earned_minor'] = 450;
    $changed = ['package' => TeachingWorkJsonFixture::upload($package)];
    $next = $this->postJson($url, $changed)->assertOk()->json('preview');
    expect($next['rows'][0]['status'])->toBe('Aktualisierung');
    $this->postJson($url, $changed + ['apply' => true, 'hash' => $next['hash']])->assertOk();
    expect((float) $work->fresh()->groups[0]['points'][0]['points'])->toBe(4.50);
});

test('JSON assessment open and partial preserve points comments and personal PDFs', function (string $state) {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $pdf = workEvaluationPdf('frei benannter Bericht.pdf');
    $package['records'][0]['pdf'] = ['filename' => $pdf->getClientOriginalName(), 'sha256' => hash_file('sha256', $pdf->getRealPath())];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package), 'pdfs' => [$pdf]];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $previous = $work->fresh()->groups;
    $previousPdfs = $work->fresh()->status['evaluation_pdfs'];
    $package['records'][0]['evaluation_state'] = $state;
    $package['records'][0]['total_minor'] = null;
    $package['records'][0]['comment'] = 'Vorläufiger Kommentar';
    $newPdf = workEvaluationPdf('vorlaeufig.pdf');
    $package['records'][0]['pdf'] = ['filename' => 'vorlaeufig.pdf', 'sha256' => hash_file('sha256', $newPdf->getRealPath())];
    $package['records'][0]['criteria'][0]['earned_minor'] = null;
    if ($state === 'partial') {
        $package['rubric'][0]['maximum_minor'] = 300;
        $package['rubric'][] = ['criterion' => 'Weitere Prüfung', 'maximum_minor' => 200];
        $package['records'][0]['criteria'][0]['maximum_minor'] = 300;
        $package['records'][0]['criteria'][] = ['criterion' => 'Weitere Prüfung', 'maximum_minor' => 200, 'earned_minor' => 100, 'checkability' => 'checkable', 'reason' => 'Vorläufig'];
    }
    $payload = ['package' => TeachingWorkJsonFixture::upload($package), 'pdfs' => [$newPdf]];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['rows'][0]['will_replace'])->toBeFalse();
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->groups)->toBe($previous)
        ->and($work->fresh()->status['evaluation_pdfs'])->toBe($previousPdfs)
        ->and($work->fresh()->status['assessment_json_imports'][0]['record']['evaluation_state'])->toBe($state);
})->with(['open', 'partial']);

test('JSON assessment matches verified current Import116 fields without changing accounts', function () {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $this->student->update(['first_name' => 'Registered name', 'schoolclass' => null]);
    $import = Import116::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->student->id, 'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'class' => '1A', 'import_user_id' => $this->admin->id,
    ]);
    $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->update(['import116_id' => $import->id]);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['can_import'])->toBeTrue();
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($this->student->fresh()->schoolclass)->toBeNull()->and($this->student->fresh()->first_name)->toBe('Registered name');
    $import->update(['schoolyear_id' => $this->otherSchoolyear->id]);
    $this->postJson($url, $payload)->assertOk()->assertJsonPath('preview.can_import', false);
});

test('JSON assessment blocks incorrect identities groups IDs and duplicate targets atomically', function (string $case) {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    if ($case === 'class') {
        $package['records'][0]['identity']['class_name'] = '3B';
    }
    if ($case === 'group') {
        $package['records'][0]['identity']['group_name'] = 'Andere Gruppe';
    }
    if ($case === 'id') {
        $package['records'][0]['identity']['schooltool_person_id'] = (string) $this->openStudent->id;
    }
    if ($case === 'canceled') {
        $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->update(['canceled_at' => now()]);
    }
    if ($case === 'ambiguous') {
        $duplicate = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'schoolclass' => '1A']);
        $this->course->teachingCourseStudents()->create(['user_id' => $duplicate->id]);
    }
    if ($case === 'group work') {
        $groups = $work->groups;
        $groups[0]['use_individual_grades'] = false;
        $work->update(['groups' => $groups]);
    }
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $before = $work->fresh()->toArray();
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['can_import'])->toBeFalse();
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertUnprocessable();
    expect($work->fresh()->toArray())->toBe($before);
})->with(['class', 'group', 'id', 'canceled', 'ambiguous', 'group work']);

test('JSON assessment rejects stale previews and wrong target maxima', function () {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $work->update(['title' => 'Manuell geändert']);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $this->course->teachingEntryArea->entryDefinitions()->update(['maximum_points' => 10]);
    $this->postJson($url, $payload)->assertUnprocessable();
});

test('JSON assessment checks PDF presence hash MIME and upload transport limits', function (string $case) {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $pdf = workEvaluationPdf('report.pdf');
    $package['records'][0]['pdf'] = ['filename' => 'report.pdf', 'sha256' => hash_file('sha256', $pdf->getRealPath())];
    $uploads = [$pdf];
    if ($case === 'missing') {
        $uploads = [];
    }
    if ($case === 'hash') {
        $package['records'][0]['pdf']['sha256'] = str_repeat('a', 64);
    }
    if ($case === 'mime') {
        $uploads = [UploadedFile::fake()->createWithContent('report.pdf', 'not a PDF')];
    }
    if ($case === 'transport') {
        $this->withServerVariables(['CONTENT_LENGTH' => 6291457]);
    }
    $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-json", ['package' => TeachingWorkJsonFixture::upload($package), 'pdfs' => $uploads])->assertUnprocessable();
    expect($work->fresh()->status['assessment_json_imports'] ?? [])->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['missing', 'hash', 'mime', 'transport']);

test('JSON assessment PDF failure rolls back all evaluations receipts and newly stored files', function () {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $package['records'][0]['email_collected_at'] = '2026-10-06T21:50:00Z';
    $package['records'][0]['evaluation_completed_at'] = '2026-10-07T00:10:00Z';
    $pdfs = [workEvaluationPdf('overview.pdf'), workEvaluationPdf('personal.pdf')];
    $package['overview_pdf'] = ['filename' => 'overview.pdf', 'sha256' => hash_file('sha256', $pdfs[0]->getRealPath())];
    $package['records'][0]['pdf'] = ['filename' => 'personal.pdf', 'sha256' => hash_file('sha256', $pdfs[1]->getRealPath())];
    $payload = ['package' => TeachingWorkJsonFixture::upload($package), 'pdfs' => $pdfs];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $before = $work->fresh()->toArray();
    $entries = TeachingCourseStudentEntry::where('teaching_course_work_id', $work->id)->get()->toArray();
    $disk = Storage::disk('local');
    Storage::shouldReceive('disk')->with('local')->andReturn($mock = Mockery::mock($disk)->makePartial());
    $mock->shouldReceive('putFileAs')->once()->passthru()->ordered();
    $mock->shouldReceive('putFileAs')->once()->andReturn(false)->ordered();
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertUnprocessable();
    expect($work->fresh()->toArray())->toBe($before)
        ->and(TeachingCourseStudentEntry::where('teaching_course_work_id', $work->id)->get()->toArray())->toBe($entries)
        ->and($disk->allFiles())->toBe([]);
});

test('JSON assessment preserves immutable participant binding and protects receipts from manual updates', function () {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $receipts = $work->fresh()->status['assessment_json_imports'];
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['status' => ['assessment_json_imports' => [], 'assessment_json_packages' => []]])->assertOk();
    expect($work->fresh()->status['assessment_json_imports'])->toBe($receipts);
    $package['records'][0]['identity']['first_name'] = 'Bea';
    $package['records'][0]['identity']['last_name'] = 'Beta';
    $next = $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package)])->assertOk()->json('preview');
    expect($next['can_import'])->toBeFalse()->and($next['rows'][0]['status'])->toContain('umgehängt');
});

test('JSON assessment rejects unauthorized or foreign school access before any changes', function (string $actor) {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $user = $actor === 'student' ? $this->student : User::factory()->create(['school_id' => $this->otherSchool->id, 'schoolyear_id' => $this->otherSchoolyear->id])->assignRole('teacher');
    $this->actingAs($user, 'sanctum');
    $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-json", ['package' => TeachingWorkJsonFixture::upload($package)])->assertForbidden();
    expect($work->fresh()->status['assessment_json_imports'] ?? [])->toBe([]);
})->with(['student', 'foreign school']);

test('JSON assessment detects concurrent participant changes and does not create missing accounts', function () {
    $work = prepareWorkEvaluationImport($this);
    $package = jsonAssessmentForWork($this, $work);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->openStudent->update(['first_name' => 'Verändert']);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $users = User::count();
    $this->course->teachingCourseStudents()->create(['user_id' => null]);
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect(User::count())->toBe($users);
});

test('JSON assessment individual work binds group name to the course and uses only existing users', function () {
    $work = prepareWorkEvaluationImport($this);
    $package = TeachingWorkJsonFixture::package();
    $package['records'][0]['identity']['class_name'] = '1A';
    $package['records'][0]['identity']['group_name'] = $this->course->title;
    $package['records'][0]['identity']['schooltool_person_id'] = (string) $this->student->id;
    $this->course->teachingCourseStudents()->create(['user_id' => null]);
    $users = User::count();
    $url = "/api/admin/teaching/course_works/{$work->id}/import-json";
    $payload = ['package' => TeachingWorkJsonFixture::upload($package)];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['can_import'])->toBeTrue()->and($preview['rows'][0]['target']['group_name'])->toBe($this->course->title);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect((float) $work->fresh()->groups[0]['points'][0]['points'])->toBe(4.75)
        ->and(User::count())->toBe($users);
});

beforeEach(function () {
    $this->freezeTime();
});

test('folder import records the latest successful import time and protects it from manual edits', function () {
    $work = prepareWorkDispatchImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-folder";
    $updateUrl = "/api/admin/teaching/course_works/{$work->id}";
    $this->putJson($updateUrl, ['status' => ['folder_imported_at' => '2000-01-01T00:00:00Z']])->assertOk();
    expect($work->fresh()->status)->not->toHaveKey('folder_imported_at');

    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(8, 30));
    $firstImport = now()->toISOString();
    $this->postJson($url, workFolderPayload())->assertOk()->assertJsonPath('data.status.folder_imported_at', $firstImport);
    expect($work->fresh()->status['folder_imported_at'])->toBe($firstImport);

    $this->travel(1)->hour();
    $lastImport = now()->toISOString();
    $this->postJson($url, workFolderPayload())->assertOk()->assertJsonPath('data.status.folder_imported_at', $lastImport);
    expect($lastImport)->not->toBe($firstImport);
    expect($work->fresh()->status['folder_imported_at'])->toBe($lastImport);

    $this->putJson($updateUrl, ['status' => ['folder_imported_at' => '2000-01-01T00:00:00Z']])->assertOk();
    expect($work->fresh()->status['folder_imported_at'])->toBe($lastImport);

    $this->travel(1)->hour();
    $this->postJson($url, workFolderPayload([], [], false))->assertOk();
    expect($work->fresh()->status['folder_imported_at'])->toBe($lastImport);
    $invalid = workFolderPayload();
    $invalid['documents'] = str_replace('title:', 'unknown:', $invalid['documents']);
    $this->postJson($url, $invalid)->assertUnprocessable();
    expect($work->fresh()->status['folder_imported_at'])->toBe($lastImport);
});

function prepareWorkDispatchImport(object $context): TeachingCourseWork
{
    $work = prepareWorkEvaluationImport($context);
    $context->course->update(['title' => 'INF 1']);
    $context->student->update(['email' => 'ada@example.test']);
    $context->openStudent->update(['email' => 'bea@example.test']);
    $work->update(['title' => 'Übung: E-Mails', 'status' => ['manual' => 'Keep']]);

    return $work;
}

/** @return array<string, mixed> */
function workFolderPayload(?array $reports = null, ?array $protocols = null, bool $includePdfs = true): array
{
    $folder = '2026-10-02_IT-Grundlagen_Test';
    if ($reports === null) {
        $reports = TeachingWorkEvaluationFixture::reports();
        unset($reports['Beurteilung_Gamma_Chris.md']);
        $reports['Gesamtuebersicht_Beurteilungen_Test.md'] = str_replace("| Chris Gamma / 5F | Vorhanden | Beurteilt | 2,0 | 2,5 | 4,5 |\n", '', $reports['Gesamtuebersicht_Beurteilungen_Test.md']);
    }
    $documents = [];
    foreach ($reports as $name => $text) {
        $documents[] = ['path' => $folder.'/Beurteilungen/'.$name, 'text' => $text];
    }
    foreach ($protocols ?? [
        'Versand/Aufgaben/Versand_2026-10-02_17-02-40/Versandprotokoll.txt' => TeachingWorkDispatchFixture::tasksText(true),
        'Versand/Ergebnisse/Versand_2026-10-04_02-12-33/Versandprotokoll.txt' => TeachingWorkDispatchFixture::text(),
    ] as $path => $text) {
        $documents[] = ['path' => $folder.'/'.$path, 'text' => $text];
    }
    $pdfs = $includePdfs && $reports !== [] ? [workEvaluationPdf('Gesamtuebersicht_Beurteilungen_Test.pdf'), workEvaluationPdf('Beurteilung_Van Alpha_Ada.pdf')] : [];

    return ['folder' => $folder, 'documents' => json_encode($documents, JSON_THROW_ON_ERROR),
        'pdf_paths' => json_encode(array_map(fn (UploadedFile $file): string => $folder.'/Beurteilungen/'.$file->getClientOriginalName(), $pdfs), JSON_THROW_ON_ERROR), 'pdfs' => $pdfs];
}

test('compact folder evaluations import points and personal PDFs while retaining open grades and comments', function () {
    $work = prepareWorkDispatchImport($this);
    $payload = workFolderPayload(TeachingWorkEvaluationFixture::compactReports(), [], false);
    $payload['pdfs'] = [workEvaluationPdf('Gesamtübersicht.pdf'), workEvaluationPdf('Van Alpha_Ada.pdf'), workEvaluationPdf('Beta_Bea.pdf')];
    $payload['pdf_paths'] = json_encode(array_map(fn (UploadedFile $pdf): string => $payload['folder'].'/Beurteilungen/'.$pdf->getClientOriginalName(), $payload['pdfs']), JSON_THROW_ON_ERROR);

    $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-folder", $payload)->assertOk();
    $work->refresh();
    expect($work->groups[0]['points'][0]['points'])->toBe(4.6);
    expect($work->groups[0]['comments'][0]['comment'])->toBe('**Ergebnis der vorliegenden Abgabe: 4,6 von 5,0 Punkten.** E-Mail: 3,0/3,0; MC-PDF: 1,6/2,0.');
    expect($work->groups[1]['points'][0]['points'])->toBe(3);
    expect($work->groups[1]['comments'][0]['comment'])->toBe('Offen vorher');
    expect($work->status['evaluation_pdfs'])->toHaveCount(3);
    expect(collect($work->status['evaluation_pdfs'])->firstWhere('student_id', $this->student->id)['name'])->toBe('Van Alpha_Ada.pdf');
});

test('single teacher task test folder logs retain original bytes without grades student flags or mail', function (string $provider) {
    $work = prepareWorkDispatchImport($this);
    $before = $work->groups;
    Mail::fake();
    Notification::fake();
    $text = TeachingWorkDispatchFixture::teacherTaskTestText($provider);
    $path = 'Versand/Aufgaben/Versand_2026-10-04_16-35-22/Versandprotokoll.txt';
    $payload = workFolderPayload([], [$path => $text], false);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-folder";

    $this->postJson($url, $payload)->assertOk();
    $status = $work->fresh()->status;
    expect($status['dispatch_logs'])->toHaveCount(1);
    expect($status['dispatch_logs'][0]['purpose'])->toBe('tasks');
    expect($status['dispatch_logs'][0]['mode'])->toBe('teacher_test');
    expect($status['dispatch_logs'][0]['recipient_scope'])->toBe('teacher');
    expect($status['dispatch_notifications'] ?? [])->toBe([]);
    expect($status['dispatch_attempts'] ?? [])->toBe([]);
    expect($work->fresh()->groups)->toEqual($before);
    expect(Storage::disk('local')->get($status['dispatch_logs'][0]['file_path']))->toBe($text);
    $this->postJson($url, $payload)->assertOk();
    expect($work->fresh()->status)->toBe($status);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
})->with(['Postmark', 'Office']);

test('teacher test folder logs are archived without grades or student dispatch flags', function (string $subject) {
    $work = prepareWorkDispatchImport($this);
    Mail::fake();
    Notification::fake();
    $path = 'Versand/Ergebnisse/Versand_2026-10-04_02-47-41/Versandprotokoll.txt';
    $text = TeachingWorkDispatchFixture::teacherTestText($subject);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-folder";
    $this->postJson($url, workFolderPayload([], [$path => $text], false))->assertOk();
    $status = $work->fresh()->status;
    expect($status['dispatch_logs'])->toHaveCount(1)
        ->and($status['dispatch_notifications'] ?? [])->toBe([])->and($status['dispatch_attempts'] ?? [])->toBe([]);
    expect(Storage::disk('local')->get($status['dispatch_logs'][0]['file_path']))->toBe($text);
    $this->postJson($url, workFolderPayload([], [$path => $text], false))->assertOk();
    expect($work->fresh()->status['dispatch_logs'])->toHaveCount(1);
    $invalidSubject = 'Andere Nachricht: E-Mails';
    $response = $this->postJson($url, workFolderPayload([], [$path => TeachingWorkDispatchFixture::teacherTestText($invalidSubject)], false))->assertUnprocessable();
    expect(implode(' ', array_merge(...array_values($response->json('errors')))))->toContain($path)->toContain($invalidSubject);
    expect($work->fresh()->status)->toBe($status);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
})->with(['Test der Ergebnisbenachrichtigung: E-Mails', 'Formatierungstest der Ergebnisbenachrichtigung: E-Mails', 'Test der Ergebnisbenachrichtigung: Abweichender Titel']);

test('one folder imports evaluations task tests and result notifications atomically and rescans new or changed contents', function () {
    $work = prepareWorkDispatchImport($this);
    Mail::fake();
    Notification::fake();
    $url = "/api/admin/teaching/course_works/{$work->id}/import-folder";
    $payload = workFolderPayload();
    $response = $this->postJson($url, $payload)->assertOk();
    expect($response->json('summary.messages'))->toHaveCount(3)
        ->and($work->fresh()->groups[0]['grades'][0]['grade'])->toBe('4.5')
        ->and($work->fresh()->groups[1]['points'][0]['points'])->toBe(3);
    $status = $work->fresh()->status;
    expect($status['evaluation_pdfs'])->toHaveCount(2)->and($status['dispatch_logs'])->toHaveCount(2)
        ->and($status['dispatch_notifications'])->toHaveCount(1)
        ->and($status['dispatch_notifications'][0]['purpose'])->toBe('results')
        ->and($status['folder_import_sources'])->toHaveCount(7);
    $taskLog = collect($status['dispatch_logs'])->firstWhere('purpose', 'tasks');
    expect(Storage::disk('local')->get($taskLog['file_path']))->toBe(TeachingWorkDispatchFixture::tasksText(true));
    $this->postJson($url, workFolderPayload())->assertOk();
    expect($work->fresh()->status)->toBe($status);
    $groups = $work->fresh()->groups;
    $groups[0]['grades'][0]['grade'] = '3.5';
    $groups[0]['points'][0]['points'] = 3.5;
    $work->update(['groups' => $groups]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);
    $this->postJson($url, workFolderPayload())->assertOk();
    expect($work->fresh()->groups[0]['grades'][0]['grade'])->toBe('3.5');
    $changed = workFolderPayload();
    $documents = json_decode($changed['documents'], true);
    foreach ($documents as &$document) {
        if (str_ends_with($document['path'], '.md')) {
            $document['text'] = str_replace(['2,5', '4,5'], ['2,0', '4,0'], $document['text']);
        }
    }
    unset($document);
    $recipients = TeachingWorkDispatchFixture::recipients();
    $recipients[0]['Providerkennung'] = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $recipients[0]['Providerzeit'] = '2026-10-05T03:15:00+02:00';
    $documents[] = ['path' => $changed['folder'].'/Versand/Ergebnisse/Versand_2026-10-05_03-15-00/Versandprotokoll.txt', 'text' => TeachingWorkDispatchFixture::text($recipients)];
    $changed['documents'] = json_encode($documents, JSON_THROW_ON_ERROR);
    $changed['pdfs'][1] = UploadedFile::fake()->createWithContent('Beurteilung_Van Alpha_Ada.pdf', "%PDF-1.4\n% Updated original\n%%EOF\n");
    $this->postJson($url, $changed)->assertOk();
    $fresh = $work->fresh();
    expect($fresh->groups[0]['grades'][0]['grade'])->toBe('4')
        ->and($fresh->status['dispatch_logs'])->toHaveCount(3)
        ->and($fresh->status['dispatch_notifications'])->toHaveCount(2)
        ->and($fresh->status['evaluation_pdfs'])->toHaveCount(2)
        ->and($fresh->status['evaluation_pdfs'][1]['sha256'])->not->toBe($status['evaluation_pdfs'][1]['sha256']);
    $this->postJson($url, $changed)->assertOk();
    expect($work->fresh()->status)->toBe($fresh->status);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['status' => ['folder_import_sources' => []]])->assertOk();
    expect($work->fresh()->status['folder_import_sources'])->toBe($fresh->status['folder_import_sources']);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

test('folder import accepts absent optional types and retains existing history', function (string $part) {
    $work = prepareWorkDispatchImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-folder";
    $payload = match ($part) {
        'evaluations' => workFolderPayload(null, [], false),
        'tasks' => workFolderPayload([], ['Versand/Aufgaben/Versand_2026-10-02_17-02-40/Versandprotokoll.txt' => TeachingWorkDispatchFixture::tasksText(true)], false),
        'results' => workFolderPayload([], ['Versand/Ergebnisse/Versand_2026-10-04_02-12-33/Versandprotokoll.txt' => TeachingWorkDispatchFixture::text()], false),
        default => workFolderPayload([], [], false),
    };
    $before = $work->groups;
    $response = $this->postJson($url, $payload)->assertOk();
    expect($response->json('summary.missing'))->not->toBe([]);
    if ($part === 'empty') {
        expect($work->fresh()->status)->toBe(['manual' => 'Keep']);
    } elseif ($part !== 'evaluations') {
        expect($work->fresh()->groups)->toEqual($before);
    }
    if ($part === 'tasks') {
        expect($work->fresh()->status['dispatch_notifications'])->toBe([])
            ->and($work->fresh()->status['dispatch_attempts'][0]['mode'])->toBe('test');
    }
    $this->postJson($url, workFolderPayload())->assertOk();
    $status = $work->fresh()->status;
    $this->postJson($url, $payload)->assertOk();
    expect($work->fresh()->status)->toBe($status);
})->with(['evaluations', 'tasks', 'results', 'empty']);

test('folder imports accept different source dates while preserving work group dates and deadlines', function () {
    $work = prepareWorkDispatchImport($this);
    $groups = array_map(fn (array $group): array => [...$group, 'date' => '2026-09-29'], $work->groups);
    $work->update(['date_for_all_groups' => '2026-09-29', 'finish_until_date' => '2026-10-10', 'groups' => $groups]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);
    $otherWork = TeachingCourseWork::create(['teaching_course_id' => $work->teaching_course_id, 'type' => $work->type,
        'title' => $work->title, 'date_for_all_groups' => '2026-10-02', 'groups' => [], 'status' => ['other' => 'Keep']]);
    $payload = workFolderPayload();
    $payload['documents'] = str_replace('2026-10-02_IT-Grundlagen_INF1', '2026-10-05_IT-Grundlagen_INF1', $payload['documents']);

    $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-folder", $payload)->assertSuccessful();

    $work->refresh();
    expect($work->date_for_all_groups->format('Y-m-d'))->toBe('2026-09-29')
        ->and($work->finish_until_date->format('Y-m-d'))->toBe('2026-10-10')
        ->and(array_column($work->groups, 'date'))->toBe(['2026-09-29', '2026-09-29'])
        ->and($work->groups[0]['points'][0]['points'])->toBe(4.5)
        ->and($work->status['dispatch_notifications'])->toHaveCount(1)
        ->and($work->status['dispatch_notifications'][0]['sent_at'])->toBe('2026-10-04T00:15:39Z')
        ->and($otherWork->fresh()->status)->toBe(['other' => 'Keep']);
});

test('folder import uses the explicitly selected work despite different titles and another matching work', function (string $title) {
    $work = prepareWorkDispatchImport($this);
    $work->update(['title' => $title]);
    $otherWork = TeachingCourseWork::create(['teaching_course_id' => $this->course->id, 'title' => 'Übung: E-Mails',
        'date_for_all_groups' => '2026-10-02', 'groups' => [], 'status' => ['other' => 'Keep']]);
    $otherBefore = $otherWork->fresh()->getAttributes();
    Mail::fake();
    Notification::fake();

    $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-folder", workFolderPayload())->assertOk();

    $work->refresh();
    expect($work->title)->toBe($title)
        ->and($work->groups[0]['points'][0]['points'])->toBe(4.5)
        ->and($work->status['evaluation_pdfs'])->toHaveCount(2)
        ->and($work->status['dispatch_logs'])->toHaveCount(2)
        ->and($work->status['dispatch_notifications'])->toHaveCount(1)
        ->and($work->status['dispatch_notifications'][0]['student_id'])->toBe($this->student->id)
        ->and($work->status['dispatch_attempts'][0]['mode'])->toBe('test')
        ->and($otherWork->fresh()->getAttributes())->toBe($otherBefore);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
})->with(['E-Mail', 'Übung: E-Mail schreiben', 'Übung: E-Mails']);

test('folder errors leave grades entries original files and notification history intact', function (string $case) {
    $work = prepareWorkDispatchImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-folder";
    $payload = workFolderPayload();
    $documents = json_decode($payload['documents'], true);
    if ($case === 'unmapped result') {
        $documents[4]['text'] = str_replace('ada@example.test', 'other@example.test', $documents[4]['text']);
    }
    if ($case === 'type contradiction') {
        $documents[4]['path'] = str_replace('/Ergebnisse/', '/Aufgaben/', $documents[4]['path']);
    }
    if ($case === 'wrong work') {
        $documents[0]['text'] = str_replace('E-Mails', 'Other work', $documents[0]['text']);
    }
    if ($case === 'unknown assessment person') {
        $payload = workFolderPayload(TeachingWorkEvaluationFixture::reports());
        $documents = json_decode($payload['documents'], true);
    }
    if ($case === 'different roots') {
        $documents[4]['path'] = 'Other/'.$documents[4]['path'];
    }
    if ($case === 'duplicate source') {
        $documents[] = $documents[4];
    }
    if ($case === 'traversal') {
        $documents[4]['path'] = str_replace('/Ergebnisse/', '/../Ergebnisse/', $documents[4]['path']);
    }
    if ($case === 'partial assessment') {
        unset($documents[2]);
        $documents = array_values($documents);
    }
    $payload['documents'] = json_encode($documents, JSON_THROW_ON_ERROR);
    $before = $work->fresh()->getAttributes();
    $entries = TeachingCourseStudentEntry::where('teaching_course_id', $this->course->id)->get()->toArray();
    $this->postJson($url, $payload)->assertUnprocessable();
    expect($work->fresh()->getAttributes())->toBe($before)
        ->and(TeachingCourseStudentEntry::where('teaching_course_id', $this->course->id)->get()->toArray())->toBe($entries)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['unmapped result', 'type contradiction', 'wrong work', 'unknown assessment person', 'different roots', 'duplicate source', 'traversal', 'partial assessment']);

test('folder import preserves old files and state when a later protocol fails after new PDF storage', function () {
    $work = prepareWorkDispatchImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-folder";
    $this->postJson($url, workFolderPayload())->assertOk();
    $before = $work->fresh()->getAttributes();
    $files = Storage::disk('local')->allFiles();
    $payload = workFolderPayload();
    $payload['pdfs'][1] = UploadedFile::fake()->createWithContent('Beurteilung_Van Alpha_Ada.pdf', "%PDF-1.4\n% Changed source\n%%EOF\n");
    $documents = json_decode($payload['documents'], true);
    $documents[] = ['path' => $payload['folder'].'/Versand/Ergebnisse/Versand_2026-10-05_03-15-00/Versandprotokoll.txt',
        'text' => str_replace('ada@example.test', 'other@example.test', TeachingWorkDispatchFixture::text())];
    $payload['documents'] = json_encode($documents, JSON_THROW_ON_ERROR);
    $this->postJson($url, $payload)->assertUnprocessable();
    expect($work->fresh()->getAttributes())->toBe($before)->and(Storage::disk('local')->allFiles())->toBe($files);
});

test('folder import blocks unauthorized roles and foreign school access before mutation', function (string $actor) {
    $work = prepareWorkDispatchImport($this);
    $user = $actor === 'foreign school'
        ? User::factory()->create(['school_id' => $this->otherSchool->id, 'schoolyear_id' => $this->otherSchoolyear->id])->assignRole('teacher')
        : $this->{$actor};
    $this->actingAs($user, 'sanctum')->postJson("/api/admin/teaching/course_works/{$work->id}/import-folder", workFolderPayload())->assertForbidden();
    expect($work->fresh()->status)->toBe(['manual' => 'Keep'])->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['student', 'regularUser', 'foreign school']);

test('tasks and results remain separate across previews reimports and normal work updates', function (bool $legacy) {
    $work = prepareWorkDispatchImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $file = fn (): UploadedFile => UploadedFile::fake()->createWithContent('Versandprotokoll.txt', TeachingWorkDispatchFixture::tasksText($legacy));
    $this->postJson($url, ['protocol' => $file(), 'purpose' => 'results'])->assertUnprocessable()->assertJsonValidationErrors('protocol');
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload(), 'purpose' => 'tasks'])->assertUnprocessable()->assertJsonValidationErrors('protocol');
    $preview = $this->postJson($url, ['protocol' => $file(), 'purpose' => 'tasks'])->assertOk()->json('preview');
    expect($preview['purpose'])->toBe('tasks')->and($preview['can_import'])->toBeTrue();
    $this->postJson($url, ['protocol' => $file(), 'purpose' => 'tasks', 'apply' => true, 'hash' => $preview['hash']])->assertOk();
    $status = $work->fresh()->status;
    expect($status['dispatch_logs'][0]['purpose'])->toBe('tasks');
    if ($legacy) {
        expect($status['dispatch_notifications'])->toBe([])->and($status['dispatch_attempts'])->toHaveCount(2)
            ->and($status['dispatch_attempts'][0]['mode'])->toBe('test')
            ->and($status['dispatch_attempts'][0]['sent_at'])->toBe('2026-10-02T15:02:40Z');
    } else {
        expect($status['dispatch_notifications'])->toHaveCount(1)
            ->and($status['dispatch_notifications'][0]['purpose'])->toBe('tasks')
            ->and($status['dispatch_notifications'][0]['sent_at'])->toBe('2026-10-01T15:02:40Z');
    }
    $again = $this->postJson($url, ['protocol' => $file(), 'purpose' => 'tasks'])->assertOk()->json('preview');
    expect($again['already_imported'])->toBeTrue();
    $this->postJson($url, ['protocol' => $file(), 'purpose' => 'tasks', 'apply' => true, 'hash' => $again['hash']])->assertOk();
    expect($work->fresh()->status)->toBe($status);
    $result = $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload(), 'purpose' => 'results'])->assertOk()->json('preview');
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload(), 'purpose' => 'results', 'apply' => true, 'hash' => $result['hash']])->assertOk();
    $combined = $work->fresh()->status;
    expect(array_column($combined['dispatch_logs'], 'purpose'))->toBe(['tasks', 'results'])
        ->and(collect($combined['dispatch_notifications'])->where('purpose', 'results')->count())->toBe(1);
    $this->getJson("/api/admin/teaching/course_works/{$work->id}/dispatch/{$combined['dispatch_logs'][0]['sha256']}")->assertOk()->assertStreamedContent(TeachingWorkDispatchFixture::tasksText($legacy));
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['status' => ['dispatch_attempts' => [], 'dispatch_notifications' => []]])->assertOk();
    expect($work->fresh()->status['dispatch_attempts'])->toBe($combined['dispatch_attempts'])
        ->and($work->fresh()->status['dispatch_notifications'])->toBe($combined['dispatch_notifications']);
})->with(['historical Mailpit' => true, 'structured live tasks' => false]);

test('folder import archives stopped Postmark and confirmed Outlook tasks without changing grades or sending mail', function () {
    $work = prepareWorkDispatchImport($this);
    $before = $work->groups;
    Mail::fake();
    Notification::fake();
    $stopped = TeachingWorkDispatchFixture::combinedTasksText('Postmark', true);
    $outlook = TeachingWorkDispatchFixture::combinedTasksText();
    $payload = workFolderPayload([], [
        'Versand/Aufgaben/Versand_2026-10-04_16-41-31/Versandprotokoll.txt' => $stopped,
        'Versand/Aufgaben/Versand_2026-10-04_16-56-40/Versandprotokoll.txt' => $outlook,
    ], false);

    $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-folder", $payload)->assertSuccessful();

    $status = $work->fresh()->status;
    expect($status['dispatch_logs'])->toHaveCount(2)->and($status['dispatch_notifications'])->toHaveCount(1)
        ->and($status['dispatch_notifications'][0]['student_id'])->toBe($this->student->id)
        ->and($status['dispatch_notifications'][0]['purpose'])->toBe('tasks')
        ->and($status['dispatch_notifications'][0]['sent_at'])->toBe('2026-10-04T14:59:41Z')
        ->and($status['dispatch_attempts'])->toHaveCount(1)->and($work->fresh()->groups)->toEqual($before);
    expect(Storage::disk('local')->get($status['dispatch_logs'][0]['file_path']))->toBe($stopped);
    expect(Storage::disk('local')->get($status['dispatch_logs'][1]['file_path']))->toBe($outlook);
    $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-folder", $payload)->assertSuccessful();
    expect($work->fresh()->status)->toBe($status);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

test('Outlook preview requires consistent sent-folder evidence for a live task marker', function (string $case) {
    $work = prepareWorkDispatchImport($this);
    $text = TeachingWorkDispatchFixture::combinedTasksText();
    $jsonStart = strpos($text, '{');
    $metadata = json_decode(substr($text, $jsonStart), true, 32, JSON_THROW_ON_ERROR);
    $field = ['not confirmed' => 'SentConfirmed', 'attachments not verified' => 'AttachmentsVerified',
        'different recipient' => 'To', 'different subject' => 'Subject', 'different account' => 'Account',
        'different message' => 'InternetMessageID', 'different entry' => 'SentEntryID', 'different store' => 'SentStoreID',
        'different time' => 'SentOn', 'different attachment' => 'Attachments'][$case];
    $metadata['Empfaenger'][0]['Office_Zustand'][$field] = match ($field) {
        'SentConfirmed', 'AttachmentsVerified' => false,
        'Attachments' => [['Name' => 'Tasks.pdf', 'SHA256' => str_repeat('b', 64)]],
        default => 'different',
    };
    $text = substr($text, 0, $jsonStart).json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $preview = $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-dispatch", [
        'protocol' => UploadedFile::fake()->createWithContent('Versandprotokoll.txt', $text), 'purpose' => 'tasks',
    ])->assertSuccessful()->json('preview');

    expect($preview['rows'][0]['accepted'])->toBeFalse()->and($preview['rows'][0]['sent_at'])->toBeNull()
        ->and($work->fresh()->status)->toBe(['manual' => 'Keep']);
})->with(['not confirmed', 'attachments not verified', 'different recipient', 'different subject', 'different account',
    'different message', 'different entry', 'different store', 'different time', 'different attachment']);

test('historical task preview enforces the same participant and work assignment checks', function () {
    $work = prepareWorkDispatchImport($this);
    $text = str_replace('ada@example.test', 'other@example.test', TeachingWorkDispatchFixture::tasksText(true));
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $file = fn (): UploadedFile => UploadedFile::fake()->createWithContent('Versandprotokoll.txt', $text);
    $preview = $this->postJson($url, ['protocol' => $file(), 'purpose' => 'tasks'])->assertOk()->json('preview');
    expect($preview['can_import'])->toBeFalse();
    $this->postJson($url, ['protocol' => $file(), 'purpose' => 'tasks', 'apply' => true, 'hash' => $preview['hash']])->assertUnprocessable();
    expect($work->fresh()->status)->toBe(['manual' => 'Keep']);
});

test('teacher only result logs can be archived without implying any student notification', function () {
    $work = prepareWorkDispatchImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $file = fn (): UploadedFile => TeachingWorkDispatchFixture::upload([TeachingWorkDispatchFixture::recipients()[2]]);
    $preview = $this->postJson($url, ['protocol' => $file(), 'purpose' => 'results'])->assertOk()->json('preview');
    expect($preview['rows'][0]['accepted'])->toBeFalse()->and($preview['rows'][0]['student_id'])->toBeNull();
    $this->postJson($url, ['protocol' => $file(), 'purpose' => 'results', 'apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->status['dispatch_notifications'])->toBe([])->and($work->fresh()->status['dispatch_attempts'])->toBe([]);
});

test('dispatch preview imports provider time and private original once while preserving grades and failed recipients', function () {
    $work = prepareWorkDispatchImport($this);
    Mail::fake();
    Notification::fake();
    $before = $work->groups;
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $preview = $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload()])->assertOk()->json('preview');
    expect($preview['rows'][0]['sent_at'])->toBe('2026-10-04T00:15:39Z')
        ->and($preview['rows'][1]['accepted'])->toBeFalse()
        ->and($preview['rows'][2]['student_id'])->toBeNull()
        ->and($work->fresh()->status)->toBe(['manual' => 'Keep']);
    expect(Storage::disk('local')->allFiles())->toBe([]);

    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload(), 'apply' => true, 'hash' => $preview['hash']])->assertOk();
    $status = $work->fresh()->status;
    expect($status['dispatch_logs'])->toHaveCount(1)
        ->and($status['dispatch_notifications'])->toHaveCount(1)
        ->and($status['dispatch_notifications'][0]['student_id'])->toBe($this->student->id)
        ->and($status['dispatch_notifications'][0]['sent_at'])->toBe('2026-10-04T00:15:39Z')
        ->and($work->fresh()->groups)->toEqual($before);
    expect(Storage::disk('local')->get($status['dispatch_logs'][0]['file_path']))->toBe(TeachingWorkDispatchFixture::text());
    $this->getJson("/api/admin/teaching/course_works/{$work->id}/dispatch/{$status['dispatch_logs'][0]['sha256']}")
        ->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertStreamedContent(TeachingWorkDispatchFixture::text());
    $this->getJson("/api/admin/teaching/course_works/{$work->id}/dispatch/".str_repeat('f', 64))->assertNotFound();

    $second = $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload()])->assertOk()->json('preview');
    expect($second['already_imported'])->toBeTrue();
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload(), 'apply' => true, 'hash' => $second['hash']])->assertOk();
    expect($work->fresh()->status)->toBe($status);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['status' => ['dispatch_logs' => [], 'dispatch_notifications' => []]])->assertOk();
    expect($work->fresh()->status['dispatch_notifications'])->toBe($status['dispatch_notifications'])
        ->and($work->fresh()->status['dispatch_logs'])->toBe($status['dispatch_logs']);
    $failed = TeachingWorkDispatchFixture::recipients();
    $failed[0]['HTTPStatus'] = '500';
    $failedPreview = $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload($failed)])->assertOk()->json('preview');
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload($failed), 'apply' => true, 'hash' => $failedPreview['hash']])->assertOk();
    expect($work->fresh()->status['dispatch_notifications'])->toBe($status['dispatch_notifications'])
        ->and($work->fresh()->status['dispatch_logs'])->toHaveCount(2);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

test('dispatch archives unsuccessful and test sends without creating successful notification markers', function (array $recipient, array $metadata) {
    $work = prepareWorkDispatchImport($this);
    $recipients = TeachingWorkDispatchFixture::recipients();
    $recipients[0] = array_replace($recipients[0], $recipient);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $preview = $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload($recipients, $metadata)])->assertOk()->json('preview');

    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload($recipients, $metadata), 'apply' => true, 'hash' => $preview['hash']])->assertOk();

    expect($work->fresh()->status['dispatch_notifications'])->toBe([])
        ->and($work->fresh()->status['dispatch_logs'])->toHaveCount(1);
})->with([
    'mailpit recipient' => [['Modus' => 'Testversand (Mailpit)'], []],
    'test metadata' => [[], ['Modus' => 'Testversand (Mailpit)']],
    'sandbox server' => [[], ['AktuelleServerpruefung' => ['DeliveryType' => 'Sandbox']]],
    'rejected status' => [['Status' => 'Fehlgeschlagen'], []],
    'queued provider' => [['Providerstatus' => 'Queued'], []],
    'http failure' => [['HTTPStatus' => 422], []],
    'provider failure' => [['PostmarkFehlercode' => 10], []],
    'error text' => [['Fehler' => 'Failed'], []],
    'missing provider identifier' => [['Providerkennung' => ''], []],
    'missing provider time' => [['Providerzeit' => ''], []],
    'timezone missing' => [['Providerzeit' => '2026-10-04T02:15:39'], []],
    'invalid calendar date' => [['Providerzeit' => '2026-02-30T02:15:39+01:00'], []],
    'before assessment' => [['Providerzeit' => '2026-10-01T02:15:39+02:00'], []],
]);

test('dispatch preview blocks unknown ambiguous canceled foreign course and wrong email recipients without writing', function (string $case) {
    $work = prepareWorkDispatchImport($this);
    $recipients = TeachingWorkDispatchFixture::recipients();
    if ($case === 'unknown') {
        $recipients[0]['Nachname'] = 'Unknown';
    }
    if ($case === 'wrong email') {
        $recipients[0]['To'] = 'other@example.test';
    }
    if ($case === 'wrong course') {
        $recipients[0]['Kurs'] = 'INF 2';
    }
    if ($case === 'canceled') {
        $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->update(['canceled_at' => now()]);
    }
    if ($case === 'no work assignment') {
        $work->update(['groups' => [$work->groups[1]]]);
        app(TeachingCourseWorkEntrySyncService::class)->syncGroupStudentIndex($work->fresh());
    }
    if ($case === 'ambiguous') {
        $duplicate = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'schoolclass' => '1A']);
        $this->course->teachingCourseStudents()->create(['user_id' => $duplicate->id]);
    }
    if ($case === 'provider collision') {
        $recipients[1] = array_replace($recipients[0], ['Vorname' => 'Bea', 'Nachname' => 'Beta', 'To' => 'bea@example.test']);
    }
    if ($case === 'teacher provider collision') {
        $recipients[2]['Providerkennung'] = $recipients[0]['Providerkennung'];
    }
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $preview = $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload($recipients)])->assertOk()->json('preview');

    expect($preview['can_import'])->toBeFalse();
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload($recipients), 'apply' => true, 'hash' => $preview['hash']])->assertUnprocessable();
    expect($work->fresh()->status)->toBe(['manual' => 'Keep'])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['unknown', 'wrong email', 'wrong course', 'canceled', 'no work assignment', 'ambiguous', 'provider collision', 'teacher provider collision']);

test('dispatch rejects malformed uploads and ambiguous work titles without changing the database', function () {
    $work = prepareWorkDispatchImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('protocol');
    $this->postJson($url, ['protocol' => UploadedFile::fake()->createWithContent('Versandprotokoll.txt', 'Invalid protocol')])->assertUnprocessable()->assertJsonValidationErrors('protocol');
    TeachingCourseWork::create(['teaching_course_id' => $this->course->id, 'title' => 'E-Mails', 'date_for_all_groups' => '2026-10-02']);
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload()])->assertUnprocessable()->assertJsonValidationErrors('protocol');
    expect($work->fresh()->status)->toBe(['manual' => 'Keep'])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('a test dispatch heading never creates live markers even with inconsistent live metadata', function () {
    $work = prepareWorkDispatchImport($this);
    $text = str_replace('LIVE-VERSAND (POSTMARK)', 'TESTVERSAND (MAILPIT)', TeachingWorkDispatchFixture::text());
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $file = fn (): UploadedFile => UploadedFile::fake()->createWithContent('Versandprotokoll.txt', $text);
    $preview = $this->postJson($url, ['protocol' => $file()])->assertOk()->json('preview');

    $this->postJson($url, ['protocol' => $file(), 'apply' => true, 'hash' => $preview['hash']])->assertOk();

    expect($work->fresh()->status['dispatch_notifications'])->toBe([]);
});

test('dispatch apply rejects a stale preview and mismatched work identity without files or notification writes', function () {
    $work = prepareWorkDispatchImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-dispatch";
    $preview = $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload()])->assertOk()->json('preview');
    $work->update(['description' => 'Changed']);
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload(), 'apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload(null, ['Leistungsfeststellung' => 'C:/source/2026-02-30_wrong'])])->assertUnprocessable()->assertJsonValidationErrors('protocol');
    $work->update(['title' => 'Other assignment']);
    $this->postJson($url, ['protocol' => TeachingWorkDispatchFixture::upload()])->assertUnprocessable()->assertJsonValidationErrors('protocol');
    expect($work->fresh()->status)->toBe(['manual' => 'Keep'])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('dispatch rejects import and download for unauthorized users and foreign schools', function (string $actor) {
    $work = prepareWorkDispatchImport($this);
    $user = $actor === 'foreign school'
        ? User::factory()->create(['school_id' => $this->otherSchool->id, 'schoolyear_id' => $this->otherSchoolyear->id])->assignRole('teacher')
        : $this->{$actor};
    $this->actingAs($user, 'sanctum');

    $this->postJson("/api/admin/teaching/course_works/{$work->id}/import-dispatch", ['protocol' => TeachingWorkDispatchFixture::upload()])->assertForbidden();
    $this->getJson("/api/admin/teaching/course_works/{$work->id}/dispatch/".str_repeat('a', 64))->assertForbidden();
    expect($work->fresh()->status)->toBe(['manual' => 'Keep']);
})->with(['student', 'regularUser', 'foreign school']);

function prepareWorkEvaluationImport(object $context): TeachingCourseWork
{
    $definition = enableCourseWorkMaximumPlus($context);
    $definition->update(['properties_mode' => 'points', 'maximum_points' => 5]);
    $context->student->update(['first_name' => 'Ada', 'last_name' => 'VAN Alpha', 'schoolclass' => '1A']);
    $context->course->teachingCourseStudents()->create(['user_id' => $context->student->id]);
    $context->openStudent = User::factory()->create(['school_id' => $context->school->id, 'first_name' => 'Bea', 'last_name' => 'Beta', 'schoolclass' => '1A']);
    $context->course->teachingCourseStudents()->create(['user_id' => $context->openStudent->id]);
    $work = TeachingCourseWork::create(['teaching_course_id' => $context->course->id, 'type' => 'MA', 'title' => 'E-Mails', 'date_for_all_groups' => '2026-10-02',
        'groups' => [['student_ids' => [$context->student->id], 'grade' => '2', 'points' => [['student_id' => $context->student->id, 'points' => 2]], 'comments' => [['student_id' => $context->student->id, 'comment' => 'Vorher']]],
            ['student_ids' => [$context->openStudent->id], 'grade' => '3', 'points' => [['student_id' => $context->openStudent->id, 'points' => 3]], 'comments' => [['student_id' => $context->openStudent->id, 'comment' => 'Offen vorher']]]]]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);
    Storage::fake('local');

    return $work;
}

function workEvaluationPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% {$name}\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
}

test('work evaluation folder preview and apply import points comments and private paired PDFs without touching open or foreign students', function () {
    $work = prepareWorkEvaluationImport($this);
    $pdfs = [workEvaluationPdf('Gesamtuebersicht_Beurteilungen_Test.pdf'), workEvaluationPdf('Beurteilung_Van Alpha_Ada.pdf')];
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload(), 'pdfs' => $pdfs];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($work->fresh()->groups[0]['comments'][0]['comment'])->toBe('Vorher')
        ->and($preview['rows'][2]['status'])->toBe('Nicht im Kurs – übersprungen');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $fresh = $work->fresh();
    expect($fresh->groups[0]['grades'][0]['grade'])->toBe('4.5')
        ->and($fresh->groups[0]['points'][0]['points'])->toBe(4.5)
        ->and($fresh->groups[0]['comments'][0]['comment'])->toBe('**Gesamt: 4,5 von 5,0 Punkten.** MC: 2,0 von 2,0; E-Mail: 2,5 von 3,0.')
        ->and($fresh->groups[1]['points'][0]['points'])->toBe(3)
        ->and($fresh->groups[1]['comments'][0]['comment'])->toBe('Offen vorher')
        ->and($fresh->status['evaluation_pdfs'])->toHaveCount(2);
    foreach ($fresh->status['evaluation_pdfs'] as $pdf) {
        Storage::disk('local')->assertExists($pdf['file_path']);
    }
    $sha = $fresh->status['evaluation_pdfs'][0]['sha256'];
    $this->getJson("/api/admin/teaching/course_works/{$work->id}/evaluations/{$sha}")->assertOk();
    $second = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $second['hash']])->assertOk();
    expect($work->fresh()->status['evaluation_pdfs'])->toHaveCount(2);
    $oldPdf = $work->fresh()->status['evaluation_pdfs'][1];
    $changed = array_map(fn (string $text): string => str_replace(['2,5', '4,5', 'Die Begründung bleibt vollständig.'], ['2,05', '4,05', 'Neue Detailbeurteilung.'], $text), TeachingWorkEvaluationFixture::reports());
    $newPdf = UploadedFile::fake()->createWithContent('Beurteilung_Van Alpha_Ada.pdf', "%PDF-1.4\n% Aktualisierte Auswertung\n%%EOF\n");
    $newPayload = ['reports' => TeachingWorkEvaluationFixture::payload($changed), 'pdfs' => [$pdfs[0], $newPdf]];
    $changedPreview = $this->postJson($url, $newPayload)->assertOk()->json('preview');
    $this->postJson($url, $newPayload + ['apply' => true, 'hash' => $changedPreview['hash']])->assertOk();
    expect($work->fresh()->groups[0]['grades'][0]['grade'])->toBe('4.05')
        ->and($work->fresh()->groups[0]['comments'][0]['comment'])->toBe('**Gesamt: 4,05 von 5,0 Punkten.** MC: 2,0 von 2,0; E-Mail: 2,05 von 3,0.')
        ->and($work->fresh()->status['evaluation_pdfs'])->toHaveCount(2)
        ->and($work->fresh()->status['evaluation_pdfs'][1]['sha256'])->not->toBe($oldPdf['sha256']);
    Storage::disk('local')->assertExists($oldPdf['file_path']);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['status' => []])->assertOk();
    expect($work->fresh()->status['evaluation_pdfs'])->toHaveCount(2);
});

test('surname first evaluations preview and import paired overall and personal PDFs while retaining open values', function () {
    $work = prepareWorkEvaluationImport($this);
    $reports = TeachingWorkEvaluationFixture::surnameFirstReports();
    $pdfs = array_map(fn (string $name): UploadedFile => workEvaluationPdf(str_replace('.md', '.pdf', $name)), array_keys($reports));
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload($reports), 'pdfs' => $pdfs];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');

    expect($preview['pdf']['name'])->toBe('Gesamtübersicht.pdf')
        ->and($preview['rows'][0]['student_id'])->toBe($this->student->id)
        ->and($preview['rows'][0]['status'])->toBe('Übernehmen')
        ->and($preview['rows'][1]['status'])->toBe('Offen – unverändert')
        ->and($preview['rows'][2]['status'])->toBe('Nicht im Kurs – übersprungen')
        ->and($work->fresh()->groups[0]['points'][0]['points'])->toBe(2);

    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $fresh = $work->fresh();
    expect($fresh->groups[0]['points'][0]['points'])->toBe(4.5)
        ->and($fresh->groups[0]['comments'][0]['comment'])->toBe('**Gesamt: 4,50 von 5,0 Punkten.** MC: 2,00 von 2,0; E-Mail: 2,50 von 3,0.')
        ->and($fresh->groups[1]['points'][0]['points'])->toBe(3)
        ->and($fresh->groups[1]['comments'][0]['comment'])->toBe('Offen vorher')
        ->and($fresh->status['evaluation_pdfs'])->toHaveCount(3)
        ->and(array_column($fresh->status['evaluation_pdfs'], 'student_id'))->toEqual([null, $this->student->id, $this->openStudent->id]);

    $attachments = $fresh->status['evaluation_pdfs'];
    $this->getJson("/api/admin/teaching/course_works?course_id={$this->course->id}")->assertOk()
        ->assertJsonPath('data.0.status.evaluation_pdfs.0.name', 'Gesamtübersicht.pdf')
        ->assertJsonCount(3, 'data.0.status.evaluation_pdfs');
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()
        ->assertJsonPath('data.status.evaluation_pdfs.1.student_id', $this->student->id);
    $teacherUrl = "/api/admin/teaching/course_works/{$work->id}/evaluations/{$attachments[0]['sha256']}";
    expect($this->get($teacherUrl.'?inline=1')->assertOk()->headers->get('Content-Disposition'))->toStartWith('inline;');
    expect($this->get($teacherUrl)->assertOk()->headers->get('Content-Disposition'))->toStartWith('attachment;');

    SchoolTool::where('school_id', $this->school->id)->update(['active_schoolyear_id' => $this->schoolyear->id]);
    foreach ([$this->student, $this->openStudent] as $index => $student) {
        $student->assignRole('student');
        $this->actingAs($student, 'sanctum')->actingAs($student, 'web');
        $pdf = $attachments[$index + 1];
        $this->getJson("/api/homepage/student/courses/{$this->course->id}/entries")->assertOk()
            ->assertJsonPath('entries.0.work.evaluation_pdf.sha256', $pdf['sha256'])
            ->assertJsonMissing(['sha256' => $attachments[0]['sha256']])
            ->assertJsonMissing(['sha256' => $attachments[$index === 0 ? 2 : 1]['sha256']]);
        $studentUrl = "/api/homepage/student/courses/{$this->course->id}/works/{$work->id}/evaluations/";
        $response = $this->get($studentUrl.$pdf['sha256'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->streamedContent())->toBe(Storage::disk('local')->get($pdf['file_path']));
        $this->get($studentUrl.$attachments[0]['sha256'])->assertNotFound();
        $this->get($studentUrl.$attachments[$index === 0 ? 2 : 1]['sha256'])->assertNotFound();
        $this->get($teacherUrl.'?inline=1')->assertForbidden();
    }
});

test('work evaluation uses the linked current student import identity and class without modifying accounts', function (bool $surnameFirst) {
    $work = prepareWorkEvaluationImport($this);
    $this->student->update(['first_name' => 'Registered name', 'schoolclass' => null]);
    $import = Import116::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->student->id, 'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'class' => '1A',
        'import_user_id' => $this->admin->id,
    ]);
    $membership = $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->firstOrFail();
    $membership->update(['import116_id' => $import->id]);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload($surnameFirst ? TeachingWorkEvaluationFixture::surnameFirstReports() : null)];
    $count = User::count();
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['rows'][0]['status'])->toBe('Übernehmen')
        ->and($preview['rows'][0]['student_id'])->toBe($this->student->id)
        ->and($this->student->fresh()->schoolclass)->toBeNull()
        ->and(User::count())->toBe($count);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->groups[0]['points'][0]['points'])->toBe(4.5);

    $import->update(['schoolyear_id' => $this->otherSchoolyear->id]);
    expect($this->postJson($url, $payload)->assertOk()->json('preview.rows.0.status'))->toBe('Nicht im Kurs – übersprungen');
    $import->update(['schoolyear_id' => $this->schoolyear->id, 'school_id' => $this->otherSchool->id]);
    expect($this->postJson($url, $payload)->assertOk()->json('preview.rows.0.status'))->toBe('Nicht im Kurs – übersprungen');
    $import->update(['school_id' => $this->school->id, 'user_id' => $this->openStudent->id]);
    expect($this->postJson($url, $payload)->assertOk()->json('preview.rows.0.status'))->toBe('Nicht im Kurs – übersprungen');
})->with(['legacy' => false, 'surname first' => true]);

test('work evaluation imports enforce school ownership maximum points and unchanged preview', function () {
    $work = prepareWorkEvaluationImport($this);
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload()];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $work->update(['title' => 'Geändert']);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $this->course->teachingEntryArea->entryDefinitions()->update(['maximum_points' => 10]);
    $this->postJson($url, $payload)->assertUnprocessable();
    $this->actingAs(User::factory()->create(['school_id' => $this->otherSchool->id, 'schoolyear_id' => $this->otherSchoolyear->id])->assignRole('teacher'), 'sanctum');
    $this->postJson($url, $payload)->assertForbidden();
});

test('work evaluation imports reject ambiguous PDF pairs and roll back a failed PDF write', function () {
    $work = prepareWorkEvaluationImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $name = 'Beurteilung_Van Alpha_Ada.pdf';
    $this->postJson($url, ['reports' => TeachingWorkEvaluationFixture::payload(), 'pdfs' => [workEvaluationPdf($name), workEvaluationPdf($name)]])->assertUnprocessable();
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload(), 'pdfs' => [workEvaluationPdf($name)]];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $disk = Storage::disk('local');
    Storage::shouldReceive('disk')->with('local')->andReturn($mock = Mockery::mock($disk)->makePartial());
    $mock->shouldReceive('putFileAs')->once()->andReturn(false);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertUnprocessable();
    expect($work->fresh()->groups[0]['comments'][0]['comment'])->toBe('Vorher')
        ->and($work->fresh()->status['evaluation_pdfs'] ?? [])->toBeEmpty()
        ->and($disk->allFiles())->toBeEmpty();
});

function enableCourseWorkMaximumPlus(object $context): TeachingEntryDefinition
{
    $context->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create([
        'school_id' => $context->school->id, 'schoolyear_id' => $context->schoolyear->id, 'user_id' => $context->admin->id,
    ]);
    $context->course->update(['teaching_entry_area_id' => $area->id]);
    $context->actingAs($context->admin, 'sanctum');

    return TeachingEntryDefinition::factory()->create([
        'school_id' => $context->school->id, 'schoolyear_id' => $context->schoolyear->id, 'user_id' => $context->admin->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'plus', 'allows_maximum_plus' => true,
    ]);
}

test('requires a strict positive maximum plus for eligible course works', function (mixed $maximum) {
    enableCourseWorkMaximumPlus($this);
    $this->postJson('/api/admin/teaching/course_works', [
        'teaching_course_id' => $this->course->id, 'type' => 'MA', 'maximum_plus' => $maximum,
    ])->assertUnprocessable()->assertJsonValidationErrors('maximum_plus');
})->with([null, false, true, 0, -1, 1.5, '3']);

test('persists maximum plus on works and validates groups without discarding existing grades', function () {
    enableCourseWorkMaximumPlus($this);
    $this->course->teachingCourseStudents()->create(['user_id' => $this->student->id]);
    $response = $this->postJson('/api/admin/teaching/course_works', [
        'teaching_course_id' => $this->course->id, 'type' => 'MA', 'maximum_plus' => 5,
        'groups' => [['student_ids' => [$this->student->id], 'grade' => '+++']],
    ])->assertCreated()->assertJsonPath('data.maximum_plus', 5);
    $work = TeachingCourseWork::findOrFail($response->json('data.id'));
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.maximum_plus', 5);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['maximum_plus' => 2])
        ->assertUnprocessable();
    expect($work->fresh()->maximum_plus)->toBe(5);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['title' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.maximum_plus', 5);
    expect($work->fresh()->teachingCourseStudentEntries()->first()->grade)->toBe('+++');
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", [
        'groups' => [['student_ids' => [$this->student->id], 'grades' => [['student_id' => $this->student->id, 'grade' => '++++++']]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('groups.0.grades.0.grade');
});

test('allows missing legacy maximum plus to be read and repaired', function () {
    $definition = enableCourseWorkMaximumPlus($this);
    $work = TeachingCourseWork::create(['teaching_course_id' => $this->course->id, 'type' => 'MA', 'groups' => []]);
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.maximum_plus', null);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['title' => 'Repair'])
        ->assertUnprocessable()->assertJsonValidationErrors('maximum_plus');
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['maximum_plus' => 4])
        ->assertOk()->assertJsonPath('data.maximum_plus', 4);
    $definition->update(['allows_maximum_plus' => false]);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['maximum_plus' => 8])
        ->assertOk()->assertJsonPath('data.maximum_plus', null);
});

test('validates repeated sign grades for work groups and students on create and update', function (string $mode, ?string $grade, bool $valid) {
    $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => $mode,
        'maximum_points' => $mode === 'points' ? 10.5 : null,
    ]);
    $this->course->update(['teaching_entry_area_id' => $area->id]);
    $this->actingAs($this->admin, 'sanctum');
    $groups = [['student_ids' => [$this->student->id], 'grade' => $grade, 'grades' => [['student_id' => $this->student->id, 'grade' => $grade]]]];
    $response = $this->postJson('/api/admin/teaching/course_works', [
        'teaching_course_id' => $this->course->id, 'type' => 'MA', 'groups' => $groups,
    ]);
    if ($valid) {
        $response->assertCreated();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors(['groups.0.grade', 'groups.0.grades.0.grade']);
    }
    $work = TeachingCourseWork::query()->create(['teaching_course_id' => $this->course->id, 'type' => 'MA', 'groups' => []]);
    if (! $valid) {
        $work->update(['type' => 'OLD', 'groups' => $groups]);
        $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['type' => 'MA'])
            ->assertUnprocessable()->assertJsonValidationErrors(['groups.0.grade', 'groups.0.grades.0.grade']);
        $work->update(['type' => 'MA', 'groups' => []]);
    }
    $response = $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['groups' => $groups]);
    if ($valid) {
        $response->assertOk();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors(['groups.0.grade', 'groups.0.grades.0.grade']);
        expect($work->fresh()->groups)->toBe([]);
    }
})->with([
    ['points', '0', true], ['points', '10.5', true], ['points', 'NA', true],
    ['points', '-1', false], ['points', '10.6', false], ['points', 'abc', false], ['points', '1e999', false],
    ['plus', '+++', true], ['plus', '--', false], ['plus_minus', '---', true],
    ['plus_minus', '+-', false], ['plus_minus', '', true], ['plus', null, true],
    ['plus', 'NA', true], ['plus_minus', 'F', true],
]);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'student',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'teaching');
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->student->assignRole('student');

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);

    $this->schemaId = 'schema-work';
    $schemaWorks = [
        [
            'short_name' => 'MA',
            'name' => 'Mitarbeit',
            'calculation' => 'average',
            'grades' => [
                ['grade' => '1', 'value' => '1'],
                ['grade' => '2', 'value' => '2'],
                ['grade' => '3', 'value' => '3'],
                ['grade' => 'NA', 'value' => ''],
            ],
            'default_grade' => null,
        ],
        [
            'short_name' => 'SA',
            'name' => 'Schularbeit',
            'calculation' => 'average',
            'grades' => [
                ['grade' => '1', 'value' => '1'],
                ['grade' => '2', 'value' => '2'],
                ['grade' => '3', 'value' => '3'],
                ['grade' => 'NA', 'value' => ''],
            ],
            'default_grade' => null,
        ],
    ];

    foreach ([$this->admin, $this->teachingAdmin, $this->teacher] as $schemaOwner) {
        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $schemaOwner->id,
            'schema_id' => $this->schemaId,
            'name' => 'Standard',
            'works' => $schemaWorks,
            'grading' => [],
        ]);
    }

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'teaching_schema_id' => $this->schemaId,
        'classes' => ['1A'],
    ]);

    $this->otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'teaching_schema_id' => $this->schemaId,
        'classes' => ['9Z'],
    ]);
});

describe('authorization', function () {
    test('index returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)->assertStatus(401);
    });

    test('returns 403 for users without allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)->assertStatus(403);
    });
});

describe('index', function () {
    test('returns empty array when no course_id is given', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works')
            ->assertOk()
            ->assertJson(['data' => []]);
    });

    test('returns works for course sorted by date descending', function () {
        $this->actingAs($this->admin, 'sanctum');

        $older = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Older',
            'date_for_all_groups' => '2026-02-01',
            'groups' => [],
        ]);

        $newer = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Newer',
            'date_for_all_groups' => '2026-03-01',
            'groups' => [],
        ]);

        $response = $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id);

        $response->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('data.0.id'))->toBe($newer->id)
            ->and($response->json('data.1.id'))->toBe($older->id);
    });

    test('returns 403 for course from other school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->otherCourse->id)
            ->assertStatus(403);
    });

    test('teacher cannot access another teachers course or another schoolyear', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $sameSchoolOtherYear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);
        $otherYearCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $sameSchoolOtherYear->id,
            'user_id' => $this->teacher->id,
            'teaching_schema_id' => $this->schemaId,
            'classes' => ['2B'],
        ]);

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)
            ->assertStatus(403);

        $this->getJson('/api/admin/teaching/course_works?course_id='.$otherYearCourse->id)
            ->assertStatus(403);
    });
});

describe('store', function () {
    test('creates work and syncs derived student entry', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Mitarbeit Woche 1',
            'description' => 'Lernzielkontrolle',
            'is_group_work' => true,
            'date_for_all_groups' => '2026-03-10',
            'finish_until_date' => '2026-03-17',
            'finish_until_time' => '14:30',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
                'comment' => 'Gute Leistung',
                'date' => '2026-03-10',
            ]],
        ];

        $response = $this->postJson('/api/admin/teaching/course_works', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'MA')
            ->assertJsonPath('data.finish_until_date', '2026-03-17')
            ->assertJsonPath('data.finish_until_time', '14:30');

        $workId = $response->json('data.id');
        $this->assertDatabaseHas('teaching_course_works', [
            'id' => $workId,
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'finish_until_date' => '2026-03-17',
        ]);

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $workId,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'date' => '2026-03-10',
            'type' => 'MA',
            'grade' => '2',
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $workId,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });

    test('validates work type against schema', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'INVALID',
            'groups' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    });

    test('validates finish until date', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'finish_until_date' => 'not-a-date',
            'groups' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('finish_until_date');
    });

    test('validates finish until time', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'finish_until_time' => '25:70',
            'groups' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('finish_until_time');
    });

    test('defaults finish until date to the work date', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'date_for_all_groups' => '2026-03-10',
            'groups' => [],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.finish_until_date', '2026-03-10');

        $this->assertDatabaseHas('teaching_course_works', [
            'id' => $response->json('data.id'),
            'date_for_all_groups' => '2026-03-10',
            'finish_until_date' => '2026-03-10',
        ]);
    });

    test('limits random group size to the number of active course students', function () {
        $this->actingAs($this->admin, 'sanctum');

        $courseStudents = User::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $courseStudents->each(fn (User $student) => $this->course->teachingCourseStudents()->create([
            'user_id' => $student->id,
        ]));

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'is_random_groups' => true,
            'group_size' => 4,
            'groups' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('group_size');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'is_random_groups' => true,
            'group_size' => 3,
            'groups' => [],
        ])->assertCreated()->assertJsonPath('data.group_size', 3);
    });

    test('uses grading entries from the area assigned to courses from 2026/27 onward', function (string $schoolyearLabel) {
        $this->actingAs($this->admin, 'sanctum');
        $this->schoolyear->update(['name' => $schoolyearLabel, 'concerns' => $schoolyearLabel]);

        $entryArea = TeachingEntryArea::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'name' => 'Digitale Grundbildung',
        ]);
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_entry_area_id' => $entryArea->id,
            'short_name' => 'A',
            'name' => 'Auftrag',
            'category' => 'Benotung',
        ]);
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_entry_area_id' => $entryArea->id,
            'short_name' => 'E',
            'name' => 'Ermahnung',
            'category' => 'Verhalten',
        ]);
        $this->course->update(['teaching_entry_area_id' => $entryArea->id]);

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'A',
            'groups' => [],
        ])->assertCreated()->assertJsonPath('data.type', 'A');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'E',
            'groups' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('type');
    })->with(['2026/27', '2027/28']);

    test('returns 403 when trying to store on course of other school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->otherCourse->id,
            'type' => 'MA',
            'groups' => [],
        ])->assertStatus(403);
    });

    test('store uses the course owner schema definitions for admins', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingSchema::query()
            ->where('user_id', $this->teacher->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('schema_id', $this->schemaId)
            ->update([
                'works' => [[
                    'short_name' => 'TE',
                    'name' => 'Teacher Work',
                    'calculation' => 'average',
                    'grades' => [
                        ['grade' => '1', 'value' => '1'],
                        ['grade' => '2', 'value' => '2'],
                    ],
                    'default_grade' => null,
                ]],
            ]);

        $teacherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'teaching_schema_id' => $this->schemaId,
            'classes' => ['2A'],
        ]);

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $teacherCourse->id,
            'type' => 'TE',
            'groups' => [],
        ])->assertCreated()->assertJsonPath('data.type', 'TE');
    });
});

describe('show update destroy', function () {
    test('show returns work for own school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Show me',
            'groups' => [],
        ]);

        $this->getJson('/api/admin/teaching/course_works/'.$work->id)
            ->assertOk()
            ->assertJsonPath('data.id', $work->id);
    });

    test('update re-syncs derived entries', function () {
        $this->actingAs($this->admin, 'sanctum');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Sync me',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
                'comment' => 'Initial',
                'date' => '2026-03-01',
            ]],
        ]);
        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $this->putJson('/api/admin/teaching/course_works/'.$work->id, [
            'type' => 'MA',
            'title' => 'Sync me updated',
            'is_group_work' => true,
            'finish_until_date' => '2026-03-09',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '1',
                'comment' => 'Updated',
                'date' => '2026-03-02',
            ]],
        ])->assertOk();

        $work->refresh();
        expect($work->title)->toBe('Sync me updated')
            ->and($work->finish_until_date?->toDateString())->toBe('2026-03-09');

        $entry = TeachingCourseStudentEntry::query()
            ->where('teaching_course_work_id', $work->id)
            ->where('user_id', $this->student->id)
            ->where('source', 'course_work')
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->grade)->toBe('1')
            ->and($entry->description)->toBe('Updated');

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });

    test('update persists an individual students grade and comment', function () {
        $this->actingAs($this->admin, 'sanctum');
        $this->course->teachingCourseStudents()->create(['user_id' => $this->student->id]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Individual work',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-09-21',
            'groups' => [],
        ]);

        $this->putJson('/api/admin/teaching/course_works/'.$work->id, [
            'type' => 'MA',
            'title' => 'Individual work',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-09-21',
            'finish_until_date' => '2026-10-12',
            'finish_until_time' => '16:45',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'date' => '2026-09-21',
                'comment' => null,
                'grade' => null,
                'grades' => [[
                    'student_id' => $this->student->id,
                    'grade' => '1',
                ]],
                'comments' => [[
                    'student_id' => $this->student->id,
                    'comment' => 'Sehr sauber gearbeitet',
                ]],
                'points' => [],
            ]],
        ])->assertOk()
            ->assertJsonPath('data.groups.0.student_ids.0', $this->student->id)
            ->assertJsonPath('data.groups.0.date', '2026-09-21')
            ->assertJsonPath('data.groups.0.grades.0.grade', '1')
            ->assertJsonPath('data.groups.0.comments.0.comment', 'Sehr sauber gearbeitet')
            ->assertJsonPath('data.finish_until_time', '16:45');

        $this->putJson('/api/admin/teaching/course_works/'.$work->id, [
            'finish_until_time' => null,
        ])->assertOk()->assertJsonPath('data.finish_until_time', null);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $this->student->id,
            'student_grade' => '1',
            'student_comment' => 'Sehr sauber gearbeitet',
        ]);
        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $this->student->id,
            'date' => '2026-09-21',
            'grade' => '1',
            'description' => 'Sehr sauber gearbeitet',
            'source' => 'course_work',
        ]);
    });

    test('updates a deadline while retaining imported feedback longer than 1024 characters', function (int $length) {
        $this->actingAs($this->admin, 'sanctum');
        $this->course->teachingCourseStudents()->create(['user_id' => $this->student->id]);
        $feedback = str_repeat('R', $length);
        $groups = [[
            'student_ids' => [$this->student->id],
            'date' => '2026-10-06',
            'comment' => $feedback,
            'grade' => null,
            'grades' => [['student_id' => $this->student->id, 'grade' => '2']],
            'comments' => [['student_id' => $this->student->id, 'comment' => $feedback]],
        ]];
        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-10-06',
            'groups' => $groups,
        ]);

        $this->putJson('/api/admin/teaching/course_works/'.$work->id, [
            'finish_until_date' => '2026-10-13',
            'finish_until_time' => '12:00',
            'groups' => $groups,
        ])->assertOk()
            ->assertJsonPath('data.finish_until_time', '12:00')
            ->assertJsonPath('data.groups.0.comments.0.comment', $feedback);

        expect($work->fresh()->finish_until_time)->toBe('12:00');
        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $this->student->id,
            'description' => $feedback,
            'grade' => '2',
        ]);
    })->with([1090, 1109, 2048]);

    test('rejects feedback longer than 2048 characters', function (string $field) {
        $this->actingAs($this->admin, 'sanctum');
        $this->course->teachingCourseStudents()->create(['user_id' => $this->student->id]);
        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'groups' => [],
        ]);
        $groups = [['student_ids' => [$this->student->id]]];
        if ($field === 'groups.0.comment') {
            $groups[0]['comment'] = str_repeat('R', 2049);
        } else {
            $groups[0]['comments'] = [['student_id' => $this->student->id, 'comment' => str_repeat('R', 2049)]];
        }

        $this->putJson('/api/admin/teaching/course_works/'.$work->id, [
            'finish_until_time' => '12:00',
            'groups' => $groups,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);

        expect($work->fresh()->finish_until_time)->toBeNull();
    })->with(['groups.0.comment', 'groups.0.comments.0.comment']);

    test('destroy deletes work and derived entries', function () {
        $this->actingAs($this->admin, 'sanctum');
        $secondStudent = User::factory()->create(['school_id' => $this->school->id]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Delete me',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$this->student->id, $secondStudent->id],
                'grade' => '3',
                'comment' => 'Remove',
            ]],
        ]);
        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        expect(TeachingCourseStudentEntry::where('teaching_course_work_id', $work->id)->where('source', 'course_work')->count())->toBe(2);

        $this->deleteJson('/api/admin/teaching/course_works/'.$work->id)->assertNoContent();

        $this->assertDatabaseMissing('teaching_course_works', ['id' => $work->id]);
        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'source' => 'course_work',
        ]);
        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
        ]);
    });

    test('syncWork rebuilds non-group work groups to current course students and preserves existing grade data', function () {
        $studentA = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentA->assignRole('student');

        $studentB = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentB->assignRole('student');

        $staleStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $staleStudent->assignRole('student');

        $this->course->teachingCourseStudents()->create(['user_id' => $studentA->id]);
        $this->course->teachingCourseStudents()->create(['user_id' => $studentB->id]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Legacy non-group list',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-03-09',
            'groups' => [[
                'student_ids' => [$studentA->id],
                'date' => '2026-03-09',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $studentA->id,
                    'grade' => '2',
                ]],
                'points' => [[
                    'student_id' => $studentA->id,
                    'points' => 37.5,
                ]],
                'comments' => [[
                    'student_id' => $studentA->id,
                    'comment' => 'already graded',
                ]],
            ], [
                'student_ids' => [$staleStudent->id],
                'date' => '2026-03-09',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $staleStudent->id,
                    'grade' => '',
                ]],
                'comments' => [[
                    'student_id' => $staleStudent->id,
                    'comment' => '',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $work->refresh();
        $groupStudentIds = collect($work->groups)
            ->flatMap(fn ($group) => (array) ($group['student_ids'] ?? []))
            ->unique()
            ->values()
            ->all();

        expect($groupStudentIds)->toContain($studentA->id, $studentB->id)
            ->and($groupStudentIds)->not->toContain($staleStudent->id);

        $groupForStudentA = collect($work->groups)
            ->first(fn ($group) => in_array($studentA->id, (array) ($group['student_ids'] ?? []), true));

        expect($groupForStudentA)->not->toBeNull()
            ->and($groupForStudentA['grades'][0]['grade'] ?? null)->toBe('2')
            ->and($groupForStudentA['points'][0]['points'] ?? null)->toBe(37.5)
            ->and($groupForStudentA['comments'][0]['comment'] ?? null)->toBe('already graded');

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentA->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentA->id,
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
        ]);

        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $staleStudent->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $staleStudent->id,
        ]);
    });

    test('syncWork resolves collided import course students to placeholder users', function () {
        $collisionId = 880001;

        $collidingUser = User::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'clara.work-collision@test.invalid',
            'first_name' => 'Clara',
            'last_name' => 'Foetschl',
            'schoolclass' => '4T',
        ]);

        $import = Import116::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'WORK-COLLISION-001',
            'import_user_id' => $this->admin->id,
            'first_name' => 'Alina',
            'last_name' => 'Husic',
            'class' => '5A',
            'email' => null,
        ]);

        $courseStudent = $this->course->teachingCourseStudents()->create([
            'user_id' => $collidingUser->id,
            'import116_id' => $import->id,
        ]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Import collision work',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-06-03',
            'groups' => [[
                'student_ids' => [$import->id],
                'date' => '2026-06-03',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $import->id,
                    'grade' => '0',
                ]],
                'comments' => [[
                    'student_id' => $import->id,
                    'comment' => 'Preserve me',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $placeholderUserId = $import->fresh()->user_id;

        expect($placeholderUserId)->not->toBeNull()
            ->and($placeholderUserId)->not->toBe($collidingUser->id)
            ->and($courseStudent->fresh()->user_id)->toBe($placeholderUserId)
            ->and($courseStudent->fresh()->import116_id)->toBe($import->id);

        $work->refresh();
        $group = collect($work->groups)->first();

        expect($group['student_ids'] ?? [])->toBe([$placeholderUserId])
            ->and($group['grades'][0]['student_id'] ?? null)->toBe($placeholderUserId)
            ->and($group['grades'][0]['grade'] ?? null)->toBe('0')
            ->and($group['comments'][0]['comment'] ?? null)->toBe('Preserve me');

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'teaching_course_work_id' => $work->id,
            'user_id' => $placeholderUserId,
            'grade' => '0',
            'source' => 'course_work',
        ]);

        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'teaching_course_work_id' => $work->id,
            'user_id' => $collidingUser->id,
            'source' => 'course_work',
        ]);
    });

    test('syncWork indexes nested group student references', function () {
        $this->actingAs($this->admin, 'sanctum');

        $studentA = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentA->assignRole('student');

        $studentB = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentB->assignRole('student');

        $studentC = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentC->assignRole('student');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$studentA->id],
                'date' => '2026-03-11',
                'name' => 'Gruppe Alpha',
                'comment' => 'Gemeinsame Gruppenrückmeldung',
                'grades' => [[
                    'student_id' => $studentB->id,
                    'grade' => '2',
                ]],
                'points' => [[
                    'student_id' => $studentC->id,
                    'points' => 12,
                ]],
                'comments' => [[
                    'student_id' => $this->student->id,
                    'comment' => 'OK',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        foreach ([$studentA, $studentB, $studentC, $this->student] as $student) {
            $this->assertDatabaseHas('teaching_course_work_group_students', [
                'teaching_course_work_id' => $work->id,
                'teaching_course_id' => $this->course->id,
                'user_id' => $student->id,
            ]);
        }

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
            'group_index' => 0,
            'group_name' => 'Gruppe Alpha',
            'group_date' => '2026-03-11',
            'group_comment' => 'Gemeinsame Gruppenrückmeldung',
            'uses_individual_grades' => true,
            'student_grade' => '2',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentC->id,
            'student_points' => 12,
        ]);

        $work->forceFill(['groups' => []])->save();

        $response = $this->getJson('/api/admin/teaching/course_works/'.$work->id);

        $response->assertOk()
            ->assertJsonPath('data.groups.0.name', 'Gruppe Alpha')
            ->assertJsonPath('data.groups.0.comment', 'Gemeinsame Gruppenrückmeldung')
            ->assertJsonPath('data.groups.0.student_ids.0', $studentA->id)
            ->assertJsonPath('data.groups.0.grades.0.student_id', $studentA->id)
            ->assertJsonPath('data.groups.0.grades.1.student_id', $studentB->id)
            ->assertJsonPath('data.groups.0.grades.1.grade', '2')
            ->assertJsonPath('data.groups.0.points.0.student_id', $studentC->id)
            ->assertJsonPath('data.groups.0.points.0.points', 12)
            ->assertJsonPath('data.groups.0.comments.2.student_id', $this->student->id)
            ->assertJsonPath('data.groups.0.comments.2.comment', 'OK');
    });

    test('backfill command indexes existing legacy work groups', function () {
        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
            ]],
        ]);

        $this->artisan('schooltool:backfill-teaching-course-work-group-students', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $this->student->id,
        ]);

        $this->artisan('schooltool:backfill-teaching-course-work-group-students')
            ->assertSuccessful();

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });
});
