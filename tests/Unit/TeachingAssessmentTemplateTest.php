<?php

use App\Models\DropboxConnection;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\MultipleChoice\McModuleTemplateSource;
use App\Services\TeachingAssessmentTemplate;
use Dompdf\Dompdf;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

function assessmentTemplateFixture(string $folderName = 'Prüfungsvorlage', bool $withSubfolder = false): object
{
    $pdf = new Dompdf;
    $pdf->loadHtml('<html><body>Aufgabe Datenschutz: 35 Punkte</body></html>');
    $pdf->render();
    $fixture = (object) ['files' => [], 'listCount' => 0, 'changeListing' => false];
    foreach (['LF_Wordtest.pdf' => $pdf->output(), 'LF_Wordtest.md' => "# Aufgabe\n35 Punkte",
        'Beurteilungskriterien_Wordtest.pdf' => $pdf->output(), 'Beurteilungskriterien_Wordtest.md' => "# Kriterien\n35 Punkte"] as $name => $bytes) {
        $id = 'id:'.count($fixture->files);
        $fixture->files[$id] = ['.tag' => 'file', 'id' => $id, 'name' => $name, 'rev' => 'r'.$id,
            'path_lower' => '/modul/pruefung/'.mb_strtolower($name), 'size' => strlen($bytes), 'bytes' => $bytes];
    }
    $connection = new DropboxConnection(['account_id' => 'account']);
    $connection->id = 7;
    $link = new TeachingWorkModuleLink(['dropbox_connection_id' => 7, 'account_id' => 'account',
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'one', 'folder_id' => 'id:module',
        'folder_path' => '/modul', 'module_id' => 'module']);
    $folder = ['.tag' => 'folder', 'id' => 'id:folder', 'name' => $folderName, 'path_lower' => '/modul/pruefung'];
    $dropbox = Mockery::mock(DropboxWorkImport::class);
    $dropbox->shouldReceive('connection')->andReturn($connection);
    $dropbox->shouldReceive('metadata')->with($connection, 'id:folder')->andReturn($folder);
    $dropbox->shouldReceive('readModuleApi')->andReturnUsing(function ($connection, string $endpoint, array $params) use ($fixture, $folder, $withSubfolder) {
        $data = match ($endpoint) {
            'files/list_folder' => $params['path'] === 'id:module' ? ['entries' => [$folder], 'has_more' => false]
                : ['entries' => array_values(array_map(fn (array $file): array => array_diff_key($file, ['bytes' => true]), $fixture->files)), 'has_more' => false],
            'files/get_metadata' => array_diff_key($fixture->files[$params['path']], ['bytes' => true]),
            'files/download' => collect($fixture->files)->first(fn (array $file): bool => 'rev:'.$file['rev'] === $params['path'])['bytes'],
            default => throw new RuntimeException('Unexpected Dropbox request'),
        };
        if ($endpoint === 'files/list_folder' && $params['path'] === 'id:folder' && $withSubfolder) {
            $data['entries'][] = ['.tag' => 'folder', 'id' => 'id:prior', 'name' => '2026-10-20 – 3B'];
        }
        if ($endpoint === 'files/list_folder' && $params['path'] === 'id:folder' && ++$fixture->listCount === 2 && $fixture->changeListing) {
            $data['entries'][0]['rev'] = 'changed';
        }

        return new Illuminate\Http\Client\Response(new Response(200, [], is_string($data) ? $data : json_encode($data, JSON_THROW_ON_ERROR)));
    });
    $modules = Mockery::mock(DropboxModuleLinks::class);
    $modules->shouldReceive('inspect')->andReturn(['folder_id' => 'id:module', 'module_id' => 'module', 'folder_path' => '/modul', 'handshake_revision' => 'one']);
    $points = Mockery::mock(McModuleTemplateSource::class);
    $points->shouldReceive('assessmentPoints')->andReturn(35.0);
    $fixture->service = new TeachingAssessmentTemplate($dropbox, $modules, $points);
    $fixture->link = $link;
    $fixture->user = new User;

    return $fixture;
}

test('returns the readable template candidates and independent template points without writing', function (): void {
    $fixture = assessmentTemplateFixture();
    $result = $fixture->service->inspect($fixture->user, $fixture->link);
    expect($result['points'])->toBe(35.0);
    expect($result['documents']['assignment']['name'])->toBe('LF_Wordtest.pdf');
    expect($result['documents']['criteria_markdown']['bytes'])->toContain('Kriterien');
});

test('rejects missing or ambiguous task PDFs before returning a candidate', function (bool $ambiguous): void {
    $fixture = assessmentTemplateFixture();
    if ($ambiguous) {
        $fixture->files['id:extra'] = array_replace($fixture->files['id:0'], ['id' => 'id:extra', 'name' => 'LF_Andere.pdf']);
    } else {
        unset($fixture->files['id:0']);
    }
    $fixture->service->inspect($fixture->user, $fixture->link);
})->with([false, true])->throws(HttpException::class);

test('rejects a changed template listing before returning a candidate', function (): void {
    $fixture = assessmentTemplateFixture();
    $fixture->changeListing = true;
    $fixture->service->inspect($fixture->user, $fixture->link);
})->throws(HttpException::class);

test('a legacy template folder may contain dated exam folders', function (): void {
    $fixture = assessmentTemplateFixture('Leistungsfeststellung', true);
    expect($fixture->service->inspect($fixture->user, $fixture->link)['points'])->toBe(35.0);
});

test('rejects unreadable task PDFs and competing criteria', function (string $case): void {
    $fixture = assessmentTemplateFixture();
    if ($case === 'pdf') {
        $fixture->files['id:0']['bytes'] = '%PDF-not-a-valid-document';
        $fixture->files['id:0']['size'] = strlen($fixture->files['id:0']['bytes']);
    } else {
        $fixture->files['id:extra'] = array_replace($fixture->files['id:3'], ['id' => 'id:extra', 'name' => 'Beurteilungskriterien_Andere.md']);
    }
    $fixture->service->inspect($fixture->user, $fixture->link);
})->with(['pdf', 'criteria'])->throws(HttpException::class);
