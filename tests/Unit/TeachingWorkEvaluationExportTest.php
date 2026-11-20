<?php

use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingSimulation;
use App\Services\TeachingWorkAiExport;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('evaluation orders preserve identities and separate simulation outputs without starting grading or sending', function (bool $simulation, bool $repeat): void {
    Http::preventStrayRequests();
    $work = (new TeachingCourseWork)->forceFill(['id' => 86, 'teaching_course_id' => 16, 'title' => 'E-Mails']);
    $actor = (new User)->forceFill(['id' => 7]);
    $original = $work->getAttributes();
    $source = $simulation ? ['simulation' => true, 'run_id' => 'test-run', 'work_id' => 86, 'course_id' => 16,
        'output_directory' => 'test-run/Auswertungen', 'submissions' => []] : null;
    $simulator = Mockery::mock(TeachingSimulation::class);
    $simulator->shouldReceive('gradingSource')->once()->with($work, 7, [492, 493])->andReturn($source);
    $this->instance(TeachingSimulation::class, $simulator);
    $export = Mockery::mock(TeachingWorkAiExport::class)->makePartial();
    $export->shouldReceive('buildSubmissionCollection')->once()->with($work, $actor, false, '2026-10-16')->andReturn([
        'work_id' => 86, 'course_id' => 16, 'roster_complete' => true,
        'context' => ['course_roster' => ['participants' => [['schooltool_person_id' => 492], ['schooltool_person_id' => 493]]]],
    ]);
    $result = $export->buildEvaluation($work, $actor, $repeat, '2026-10-16');
    expect($result['context'])->toMatchArray(['purpose' => 'grade-exercise', 'mode' => $repeat ? 'repeat' : 'grade',
        'evaluation_policy' => ['missing_required_component' => 'zero_points_final', 'uncheckable_existing_component' => 'open', 'no_submission_before_deadline' => 'open'],
        'simulation_source' => $source, 'operation' => ['grade' => true, 'preserve_existing' => true,
            'send' => false, 'schooltool_import' => false, 'simulation' => $simulation]])
        ->and($result['text'])->toContain('Skill grade-exercise', 'verbindliche Bewertungskriterien', 'Keine E-Mails versenden', 'Allein ein fehlender Anhang macht die Bewertung nicht vorläufig')
        ->and($work->getAttributes())->toBe($original);
    if ($simulation) {
        expect($result['text'])->toContain('ausschließlich eine lokale Simulation', 'run.json nicht als Bewertungsmaßstab', 'lokalen output_directory');
    }
    Http::assertNothingSent();
})->with([[false, false], [false, true], [true, false], [true, true]]);

test('grading uses active roster portal files even after switching to email and keeps them out of simulations', function (bool $simulation): void {
    Http::preventStrayRequests();
    $file = [
        'id' => '67ec2065-adfb-465a-9498-c203cb88b951', 'student_id' => 492, 'name' => 'Aufgabe.pdf',
        'size' => 131, 'sha256' => str_repeat('b', 64), 'submitted_at' => '2026-11-19T15:00:00Z',
        'file_path' => 'private/original.pdf', 'storage_disk' => 'local',
    ];
    $work = (new TeachingCourseWork)->forceFill(['id' => 86, 'teaching_course_id' => 16, 'title' => 'Projekt', 'status' => [
        'submission_method' => ['channel' => 'email'], 'portal_submissions' => [
            $file, [...$file, 'withdrawn_at' => '2026-11-20T12:00:00Z'], [...$file, 'student_id' => 123],
            [...$file, 'id' => 'invalid'], null,
        ],
    ]]);
    $actor = (new User)->forceFill(['id' => 7]);
    $simulator = Mockery::mock(TeachingSimulation::class);
    $simulator->shouldReceive('gradingSource')->once()->with($work, 7, [492])->andReturn($simulation ? ['simulation' => true] : null);
    $this->instance(TeachingSimulation::class, $simulator);
    $export = Mockery::mock(TeachingWorkAiExport::class)->makePartial();
    $export->shouldReceive('buildSubmissionCollection')->once()->andReturn(['work_id' => 86, 'course_id' => 16,
        'context' => ['course_roster' => ['participants' => [['schooltool_person_id' => 492]]]]]);
    $result = $export->buildEvaluation($work, $actor, false);
    expect($result['context']['submission_channel'])->toBe('email');
    if ($simulation) {
        expect($result['context']['portal_submissions'])->toBe([])
            ->and($result['text'])->not->toContain($file['id']);
    } else {
        $files = $result['context']['portal_submissions'];
        expect($files)->toHaveCount(1)
            ->and($files[0]['download_url'])->toBe(route('teaching.work-submissions.download', ['course_work' => 86, 'fileId' => $file['id']]))
            ->and($files[0]['sha256'])->toBe($file['sha256'])
            ->and($files[0])->not->toHaveKeys(['file_path', 'storage_disk'])
            ->and($result['text'])->toContain('Eine Portalabgabe erfüllt den Übermittlungsweg.');
    }
    Http::assertNothingSent();
})->with([false, true]);
