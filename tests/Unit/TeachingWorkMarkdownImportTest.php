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

test('work evaluation PDF references survive both backups and synchronisation with private paths', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $attachments = [];
    foreach ([null, 1] as $index => $studentId) {
        $content = 'PDF '.$index;
        $path = 'teaching/work_evaluations/1/9/'.$index.'.pdf';
        $disk->put($path, $content);
        $attachments[] = ['file_path' => $path, 'storage_disk' => 'local', 'student_id' => $studentId, 'sha256' => hash('sha256', $content)];
    }
    $tables = ['teaching_courses' => [['id' => 2, 'school_id' => 1]],
        'teaching_course_works' => [['id' => 9, 'teaching_course_id' => 2, 'status' => json_encode(['evaluation_pdfs' => $attachments])]],
        'teaching_curriculum_documents' => [], 'teaching_course_date_material_attachments' => [], 'teaching_imported_curricula' => []];
    $sync = new TeachingSynchronisationFiles;
    $files = $sync->capture($tables, ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => rtrim($disk->path(''), '/\\')]]]);
    expect($files)->toHaveCount(2);
    $result = $sync->rewrite($tables, $files, 1);
    $status = json_decode($result['tables']['teaching_course_works'][0]['status'], true);
    expect($status['evaluation_pdfs'][1]['file_path'])->toStartWith('teaching/synchronisation/1/')
        ->and($result['files'])->toHaveCount(2);
    $graph = new TeachingSynchronisationGraph;
    $remapped = (new ReflectionMethod($graph, 'remapJson'))->invoke($graph, ['evaluation_pdfs' => $attachments], ['users' => [1 => 11]], 'status', [1 => 11]);
    expect($remapped['evaluation_pdfs'][0]['student_id'])->toBeNull()->and($remapped['evaluation_pdfs'][1]['student_id'])->toBe(11);
    $backup = app(TeachingBackupService::class);
    $backedUp = (new ReflectionMethod($backup, 'filesForTables'))->invoke($backup, $tables);
    expect($backedUp)->toHaveCount(2)->and($backedUp[0]['exists'])->toBeTrue();
    $archiveFiles = [];
    foreach ($attachments as $pdf) {
        $archiveFiles[$pdf['file_path']] = ['exists' => true, 'base64' => base64_encode($disk->get($pdf['file_path']))];
    }
    $restoredSchool = json_decode((new ReflectionMethod($backup, 'restoreWorkEvaluationFiles'))->invoke($backup, $tables['teaching_course_works'][0]['status'], $archiveFiles, 1, [1 => 11]), true);
    expect($restoredSchool['evaluation_pdfs'][1]['student_id'])->toBe(11)
        ->and($disk->get($restoredSchool['evaluation_pdfs'][1]['file_path']))->toBe('PDF 1');
    $personal = app(PersonalTeachingBackupService::class);
    $references = (new ReflectionMethod($personal, 'fileReferences'))->invoke($personal, $tables);
    expect($references)->toHaveCount(2)->and($references[1]['column'])->toBe('status.evaluation_pdfs.1.file_path');
    foreach ($references as &$reference) {
        $reference['content'] = base64_encode($disk->get($reference['path']));
    }
    unset($reference);
    $created = [];
    (new ReflectionMethod($personal, 'restoreFiles'))->invokeArgs($personal, [&$tables, $references, &$created]);
    $restored = json_decode($tables['teaching_course_works'][0]['status'], true);
    expect($restored['evaluation_pdfs'][0]['file_path'])->toStartWith('teaching/personal_restores/')
        ->and($disk->get($restored['evaluation_pdfs'][1]['file_path']))->toBe('PDF 1');
});
