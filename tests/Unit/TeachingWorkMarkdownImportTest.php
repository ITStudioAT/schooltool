<?php

use App\Services\PersonalTeachingBackupService;
use App\Services\TeachingBackupService;
use App\Services\TeachingSynchronisationFiles;
use App\Services\TeachingSynchronisationGraph;
use App\Services\TeachingWorkMarkdownImport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\TeachingWorkEvaluationFixture;
use Tests\TestCase;

uses(TestCase::class);

test('status table evaluation reports preserve decimal points names and open submissions', function () {
    $reports = array_map(fn (string $text): string => "\xEF\xBB\xBF".str_replace("\n", "\r\n", $text), TeachingWorkEvaluationFixture::statusReports());
    $report = app(TeachingWorkMarkdownImport::class)->parse($reports);

    expect($report['title'])->toBe('E-Mails · DGB · 3B · Gruppe 2');
    expect($report['date'])->toBe('04.10.2026');
    expect($report['maximum'])->toBe(5.0);
    expect($report['rows']['ada van alpha / 3b']['points'])->toBe(4.75);
    expect($report['rows']['ada van alpha / 3b']['source'])->toBe('Van Alpha_Ada.md');
    expect($report['rows']['ada van alpha / 3b']['comment'])->toBe('4,75 von 5,0 Punkten; **Teilbereiche:** Multiple Choice: 2,0 / 2,0; E-Mail: 2,75 / 3,0.');
    expect($report['rows']['bea beta / 3b']['points'])->toBeNull();
    expect($report['rows']['bea beta / 3b']['comment'])->toBe('');
});

test('status table evaluation reports reject inconsistent metadata statuses and points', function (string $file, string $from, string $to) {
    $reports = TeachingWorkEvaluationFixture::statusReports();
    $reports[$file] = str_replace($from, $to, $reports[$file]);

    expect(fn () => app(TeachingWorkMarkdownImport::class)->parse($reports))->toThrow(ValidationException::class);
})->with([
    'overview maximum' => ['Gesamtübersicht.md', '**Maximale Punkte:** 5,0', '**Maximale Punkte:** 6,0'],
    'invalid date' => ['Gesamtübersicht.md', '04.10.2026', '31.02.2026'],
    'different assignment' => ['Van Alpha_Ada.md', 'Gruppe 2', 'Gruppe 1'],
    'different date' => ['Van Alpha_Ada.md', '04.10.2026', '05.10.2026'],
    'identity' => ['Van Alpha_Ada.md', 'Van Alpha Ada /', 'Ada Van Alpha /'],
    'class' => ['Van Alpha_Ada.md', '/ 3B', '/ 3A'],
    'duplicate status' => ['Van Alpha_Ada.md', '| Person |', "| Ergebnis | 4,75 von 5,0 Punkten |\n| Person |"],
    'submission status' => ['Van Alpha_Ada.md', '| E-Mail und PDF vorhanden |', '| Offen |'],
    'evaluation status' => ['Van Alpha_Ada.md', '| Abgeschlossen |', '| Bewertung offen |'],
    'overview status' => ['Gesamtübersicht.md', '| Abgeschlossen |', '| Bewertung offen |'],
    'overview points' => ['Gesamtübersicht.md', '| 4,75 |', '| 4,5 |'],
    'result total' => ['Van Alpha_Ada.md', '4,75 von', '4,5 von'],
    'result maximum' => ['Van Alpha_Ada.md', 'von 5,0 Punkten', 'von 6,0 Punkten'],
    'criterion points' => ['Van Alpha_Ada.md', '| 3,0 | 2,75 |', '| 3,0 | 3,1 |'],
    'criterion sum' => ['Van Alpha_Ada.md', '| 3,0 | 2,75 |', '| 3,0 | 2,5 |'],
    'duplicate criterion' => ['Van Alpha_Ada.md', '| Nachrichtentext |', '| Multiple-Choice-PDF |'],
    'component points' => ['Van Alpha_Ada.md', 'E-Mail: 2,75', 'E-Mail: 2,5'],
    'component maximum' => ['Van Alpha_Ada.md', '2,75 / 3,0', '2,75 / 4,0'],
    'open numeric criterion' => ['Beta_Bea.md', '| 2,0 | offen |', '| 2,0 | 0 |'],
    'open numeric overview' => ['Gesamtübersicht.md', '| offen | 5,0 |', '| 0 | 5,0 |'],
    'open result' => ['Beta_Bea.md', 'Keine abschließende Gesamtsumme', '0 von 5,0 Punkten'],
]);

test('status table evaluation reports require complete unique overview and detail files', function () {
    $reports = TeachingWorkEvaluationFixture::statusReports();
    unset($reports['Beta_Bea.md']);
    expect(fn () => app(TeachingWorkMarkdownImport::class)->parse($reports))->toThrow(ValidationException::class);

    $reports = TeachingWorkEvaluationFixture::statusReports();
    $reports['Kopie.md'] = $reports['Gesamtübersicht.md'];
    expect(fn () => app(TeachingWorkMarkdownImport::class)->parse($reports))->toThrow(ValidationException::class);
});

test('compact evaluation reports preserve original basenames result lines and open values', function () {
    $report = app(TeachingWorkMarkdownImport::class)->parse(TeachingWorkEvaluationFixture::compactReports());

    expect($report['maximum'])->toBe(5.0);
    expect($report['date'])->toBe('04.10.2026');
    expect($report['rows']['ada van alpha / 1a']['points'])->toBe(4.6);
    expect($report['rows']['ada van alpha / 1a']['source'])->toBe('Van Alpha_Ada.md');
    expect($report['rows']['ada van alpha / 1a']['comment'])->toBe('**Ergebnis der vorliegenden Abgabe: 4,6 von 5,0 Punkten.** E-Mail: 3,0/3,0; MC-PDF: 1,6/2,0.');
    expect($report['rows']['bea beta / 1a']['points'])->toBeNull();
    expect($report['rows']['bea beta / 1a']['comment'])->toBe('');
});

test('compact evaluation reports reject inconsistent identities totals criteria statuses and incomplete bundles', function (string $case) {
    $reports = TeachingWorkEvaluationFixture::compactReports();
    if ($case === 'missing detail') {
        unset($reports['Beta_Bea.md']);
    } elseif ($case === 'unknown file') {
        $reports['Unknown_Person.md'] = '# Unsupported';
    } else {
        [$file, $from, $to] = match ($case) {
            'identity' => ['Van Alpha_Ada.md', 'Van Alpha Ada /', 'Ada Van Alpha /'],
            'class' => ['Van Alpha_Ada.md', ' / 1A', ' / 1F'],
            'title' => ['Van Alpha_Ada.md', 'Auswertung: E-Mails', 'Auswertung: Andere Arbeit'],
            'result total' => ['Van Alpha_Ada.md', 'Abgabe: 4,6 von', 'Abgabe: 4,5 von'],
            'criteria total' => ['Van Alpha_Ada.md', '| **4,6** |', '| **4,5** |'],
            'criterion points' => ['Van Alpha_Ada.md', '| 2,0 | 1,6 |', '| 2,0 | 2,1 |'],
            'component points' => ['Van Alpha_Ada.md', 'MC-PDF: 1,6/2,0', 'MC-PDF: 1,5/2,0'],
            'overview points' => ['Gesamtübersicht.md', '| 4,6 | 5,0 |', '| 4,5 | 5,0 |'],
            'maximum' => ['Gesamtübersicht.md', '| offen | 5,0 |', '| offen | 6,0 |'],
            'open numeric' => ['Beta_Bea.md', '| 2,0 | offen |', '| 2,0 | 0 |'],
            'open status' => ['Gesamtübersicht.md', '| Bewertung offen |', '| vorliegende Abgabe beurteilt |'],
            'date' => ['Gesamtübersicht.md', '04.10.2026', '31.02.2026'],
        };
        $reports[$file] = str_replace($from, $to, $reports[$file]);
    }

    expect(fn () => app(TeachingWorkMarkdownImport::class)->parse($reports))->toThrow(ValidationException::class);
})->with(['missing detail', 'unknown file', 'identity', 'class', 'title', 'result total', 'criteria total', 'criterion points', 'component points', 'overview points', 'maximum', 'open numeric', 'open status', 'date']);

test('surname first evaluation reports preserve verified name boundaries decimal points and open submissions', function () {
    $report = app(TeachingWorkMarkdownImport::class)->parse(TeachingWorkEvaluationFixture::surnameFirstReports());

    expect($report['source'])->toBe('Gesamtübersicht.md')
        ->and($report['maximum'])->toBe(5.0)
        ->and($report['rows']['ada van alpha / 1a']['points'])->toBe(4.5)
        ->and($report['rows']['ada van alpha / 1a']['comment'])->toBe('**Gesamt: 4,50 von 5,0 Punkten.** MC: 2,00 von 2,0; E-Mail: 2,50 von 3,0.')
        ->and($report['rows']['bea beta / 1a']['points'])->toBeNull()
        ->and($report['rows']['bea beta / 1a']['comment'])->toBe('')
        ->and($report['rows']['chris-jo van gamma delta / 5f']['source'])->toBe('Beurteilung_Van Gamma Delta_Chris-Jo.md');
});

test('surname first evaluation reports reject inconsistent identity metadata and points', function (string $file, string $from, string $to) {
    $reports = TeachingWorkEvaluationFixture::surnameFirstReports();
    $reports[$file] = str_replace($from, $to, $reports[$file]);

    expect(fn () => app(TeachingWorkMarkdownImport::class)->parse($reports))->toThrow(ValidationException::class);
})->with([
    'filename identity' => ['Beurteilung_Van Alpha_Ada.md', 'Van Alpha Ada /', 'Ada Van Alpha /'],
    'class' => ['Beurteilung_Van Alpha_Ada.md', ' / 1A', ' / 1F'],
    'date' => ['Beurteilung_Van Alpha_Ada.md', '02.10.2026', '03.10.2026'],
    'assignment' => ['Beurteilung_Van Alpha_Ada.md', 'Beurteilung: E-Mails', 'Beurteilung: Andere Arbeit'],
    'total' => ['Beurteilung_Van Alpha_Ada.md', 'Gesamt: 4,50', 'Gesamt: 4,05'],
    'component maximum' => ['Beurteilung_Van Alpha_Ada.md', 'MC: 2,00 von 2,0', 'MC: 2,00 von 1,0'],
    'overview maximum' => ['Gesamtübersicht.md', 'Maximal 5,0 Punkte:', 'Maximal 6,0 Punkte:'],
    'open with numeric points' => ['Gesamtübersicht.md', '| offen | offen | offen |', '| 0 | 0 | 0 |'],
    'partially open' => ['Gesamtübersicht.md', '| offen | offen | offen |', '| offen | 0 | offen |'],
    'contradicting submission' => ['Gesamtübersicht.md', '| Fehlt | Offen |', '| Vorhanden | Offen |'],
    'unknown open result' => ['Beurteilung_Beta_Bea.md', '**Keine abschließende Gesamtsumme.**', '**Unbekanntes Ergebnis.**'],
]);

test('work evaluation Markdown retains decimal points multiword identities and open submissions', function () {
    $report = app(TeachingWorkMarkdownImport::class)->parse(TeachingWorkEvaluationFixture::reports());
    expect($report['maximum'])->toBe(5.0)
        ->and($report['rows']['ada van alpha / 1a']['points'])->toBe(4.5)
        ->and($report['rows']['ada van alpha / 1a']['comment'])->toBe('**Gesamt: 4,5 von 5,0 Punkten.** MC: 2,0 von 2,0; E-Mail: 2,5 von 3,0.')
        ->and($report['rows']['bea beta / 1a']['points'])->toBeNull()
        ->and(TeachingWorkMarkdownImport::identity(' Ada  VAN Alpha ', '1a'))->toBe('ada van alpha / 1a');
});

test('work evaluation Markdown keeps only the result line even with long PDF detail text', function () {
    $reports = TeachingWorkEvaluationFixture::reports();
    $reports['Beurteilung_Van Alpha_Ada.md'] .= "\n".str_repeat('Ausführliche Begründung. ', 100);
    $report = app(TeachingWorkMarkdownImport::class)->parse($reports);
    expect($report['rows']['ada van alpha / 1a']['comment'])->toBe('**Gesamt: 4,5 von 5,0 Punkten.** MC: 2,0 von 2,0; E-Mail: 2,5 von 3,0.');
});

test('work evaluation Markdown refuses conflicts missing reports and unknown formats', function (string $case) {
    $reports = TeachingWorkEvaluationFixture::reports();
    if ($case === 'conflict') {
        $reports['Beurteilung_Van Alpha_Ada.md'] = str_replace('Gesamt: 4,5', 'Gesamt: 4,05', $reports['Beurteilung_Van Alpha_Ada.md']);
    }
    if ($case === 'missing') {
        unset($reports['Beurteilung_Beta_Bea.md']);
    }
    if ($case === 'unknown') {
        $reports['unexpected.md'] = '# Auswertung';
    }
    if ($case === 'duplicate') {
        $reports['Beurteilung_duplicate.md'] = $reports['Beurteilung_Van Alpha_Ada.md'];
    }
    if ($case === 'wrong person') {
        $reports['Beurteilung_Van Alpha_Ada.md'] = str_replace('Ada Van Alpha |', 'Bea Beta |', $reports['Beurteilung_Van Alpha_Ada.md']);
    }
    expect(fn () => app(TeachingWorkMarkdownImport::class)->parse($reports))->toThrow(ValidationException::class);
})->with(['conflict', 'missing', 'unknown', 'duplicate', 'wrong person']);

test('work evaluation PDFs and dispatch logs survive both backups and synchronisation with private references', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $attachments = [];
    foreach ([null, 1] as $index => $studentId) {
        $content = 'PDF '.$index;
        $path = 'teaching/work_evaluations/1/9/'.$index.'.pdf';
        $disk->put($path, $content);
        $attachments[] = ['file_path' => $path, 'storage_disk' => 'local', 'student_id' => $studentId, 'sha256' => hash('sha256', $content)];
    }
    $logPath = 'teaching/work_dispatches/1/9/log.txt';
    $disk->put($logPath, 'Dispatch');
    $workStatus = ['evaluation_pdfs' => $attachments,
        'dispatch_logs' => [['file_path' => $logPath, 'storage_disk' => 'local', 'sha256' => hash('sha256', 'Dispatch')]],
        'dispatch_notifications' => [['student_id' => 1, 'sent_at' => '2026-10-04T00:15:39Z']],
        'dispatch_attempts' => [['student_id' => 1, 'purpose' => 'tasks', 'mode' => 'test']]];
    $tables = ['teaching_courses' => [['id' => 2, 'school_id' => 1]],
        'teaching_course_works' => [['id' => 9, 'teaching_course_id' => 2, 'status' => json_encode($workStatus)]],
        'teaching_curriculum_documents' => [], 'teaching_course_date_material_attachments' => [], 'teaching_imported_curricula' => []];
    $sync = new TeachingSynchronisationFiles;
    $files = $sync->capture($tables, ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => rtrim($disk->path(''), '/\\')]]]);
    expect($files)->toHaveCount(3);
    $result = $sync->rewrite($tables, $files, 1);
    $status = json_decode($result['tables']['teaching_course_works'][0]['status'], true);
    expect($status['evaluation_pdfs'][1]['file_path'])->toStartWith('teaching/synchronisation/1/')
        ->and($status['dispatch_logs'][0]['file_path'])->toStartWith('teaching/synchronisation/1/')
        ->and($result['files'])->toHaveCount(3);
    $graph = new TeachingSynchronisationGraph;
    $remapped = (new ReflectionMethod($graph, 'remapJson'))->invoke($graph, $workStatus, ['users' => [1 => 11]], 'status', [1 => 11]);
    expect($remapped['evaluation_pdfs'][0]['student_id'])->toBeNull()->and($remapped['evaluation_pdfs'][1]['student_id'])->toBe(11)
        ->and($remapped['dispatch_notifications'][0]['student_id'])->toBe(11)
        ->and($remapped['dispatch_attempts'][0]['student_id'])->toBe(11);
    $backup = app(TeachingBackupService::class);
    $backedUp = (new ReflectionMethod($backup, 'filesForTables'))->invoke($backup, $tables);
    expect($backedUp)->toHaveCount(3)->and($backedUp[0]['exists'])->toBeTrue();
    $archiveFiles = [];
    foreach ($attachments as $pdf) {
        $archiveFiles[$pdf['file_path']] = ['exists' => true, 'base64' => base64_encode($disk->get($pdf['file_path']))];
    }
    $archiveFiles[$logPath] = ['exists' => true, 'base64' => base64_encode('Dispatch')];
    $restoredSchool = json_decode((new ReflectionMethod($backup, 'restoreWorkEvaluationFiles'))->invoke($backup, $tables['teaching_course_works'][0]['status'], $archiveFiles, 1, [1 => 11]), true);
    expect($restoredSchool['evaluation_pdfs'][1]['student_id'])->toBe(11)
        ->and($disk->get($restoredSchool['evaluation_pdfs'][1]['file_path']))->toBe('PDF 1');
    expect($restoredSchool['dispatch_notifications'][0]['student_id'])->toBe(11)
        ->and($restoredSchool['dispatch_attempts'][0]['student_id'])->toBe(11)
        ->and($disk->get($restoredSchool['dispatch_logs'][0]['file_path']))->toBe('Dispatch');
    $personal = app(PersonalTeachingBackupService::class);
    $references = (new ReflectionMethod($personal, 'fileReferences'))->invoke($personal, $tables);
    expect($references)->toHaveCount(3)->and($references[1]['column'])->toBe('status.evaluation_pdfs.1.file_path')
        ->and($references[2]['column'])->toBe('status.dispatch_logs.0.file_path');
    foreach ($references as &$reference) {
        $reference['content'] = base64_encode($disk->get($reference['path']));
    }
    unset($reference);
    $created = [];
    (new ReflectionMethod($personal, 'restoreFiles'))->invokeArgs($personal, [&$tables, $references, &$created]);
    $restored = json_decode($tables['teaching_course_works'][0]['status'], true);
    expect($restored['evaluation_pdfs'][0]['file_path'])->toStartWith('teaching/personal_restores/')
        ->and($disk->get($restored['evaluation_pdfs'][1]['file_path']))->toBe('PDF 1');
    expect($restored['dispatch_logs'][0]['file_path'])->toStartWith('teaching/personal_restores/')
        ->and($disk->get($restored['dispatch_logs'][0]['file_path']))->toBe('Dispatch');
});
