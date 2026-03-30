<?php

use App\Models\Aba;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['queue.default' => 'sync']);
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    Role::firstOrCreate(['name' => 'aba_teacher', 'guard_name' => 'web']);

    $licence = Licence::firstOrCreate(
        ['name' => 'ABA'],
        [
            'long_name' => 'ABA',
            'is_selectable' => true,
        ]
    );

    $this->school->licences()->syncWithoutDetaching([
        $licence->id => ['valid_until' => now()->addMonth()->toDateString()],
    ]);
});

function createAbaTeacher(School $school, Schoolyear $schoolyear): User
{
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $user->assignRole('aba_teacher');

    return $user;
}

function createDocxFixture(string $path): void
{
    $document = new PhpWord;
    $section = $document->addSection();
    $section->addTitle('Titelblatt', 1);
    $section->addText('Titel der Arbeit');
    $section->addText('Untertitel der Arbeit');
    $section->addText('Schule: BORG Musterstadt');
    $section->addText('Fach: Medieninformatik');
    $section->addText('Verfasser: Max Mustermann');
    $section->addText('Klasse: 8M');
    $section->addText('Betreuer: Mag. Erika Muster');
    $section->addText('Datum: März 2026');
    $section->addTitle('Inhaltsverzeichnis', 1);
    $section->addText('1 Einleitung 1');
    $section->addTitle('Zusammenfassung', 1);
    $section->addText('Dies ist die deutsche Zusammenfassung der Arbeit.');
    $section->addTitle('Einleitung', 1);
    $section->addText('Einleitungstext der Arbeit.');
    $section->addTitle('Hauptteil', 1);
    $section->addText('Fachliche Ausarbeitung im Hauptteil.');
    $section->addTitle('Fazit', 1);
    $section->addText('Zusammenfassung der Ergebnisse.');
    $section->addTitle('Literaturverzeichnis', 1);
    $section->addText('Autor, Titel, 2024.');
    $section->addTitle('Eigenständigkeitserklärung', 1);
    $section->addText('Hiermit erkläre ich die eigenständige Erstellung.');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'aba-docx-');
    if (! is_string($temporaryFile) || $temporaryFile === '') {
        throw new RuntimeException('Temporary DOCX file could not be created.');
    }

    @unlink($temporaryFile);
    $temporaryFile .= '.docx';

    IOFactory::createWriter($document, 'Word2007')->save($temporaryFile);
    Storage::disk('local')->put($path, file_get_contents($temporaryFile));
    @unlink($temporaryFile);
}

test('starts aba extraction for main docx and returns structured results', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture('aba-tests/test-main.docx');

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main.docx',
        'stored_name' => 'test-main.docx',
        'path' => 'aba-tests/test-main.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.source_original_name', 'test-main.docx');

    $this->assertDatabaseHas('aba_analysis_runs', [
        'aba_id' => $aba->id,
        'status' => 'completed',
    ]);

    $this->assertDatabaseCount('aba_analysis_results', 8);

    $sectionKeys = collect($response->json('data.sections'))->pluck('key')->all();
    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($sectionKeys)
        ->toContain('title_page')
        ->toContain('introduction')
        ->toContain('main_body')
        ->toContain('bibliography')
        ->toContain('consent_declaration');
    $titlePageOtherThings = collect(data_get($titlePageSection, 'title_page.other_things', []));

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Titel der Arbeit')
        ->and(data_get($titlePageSection, 'title_page.subtitle'))->toBe('Untertitel der Arbeit')
        ->and(data_get($titlePageSection, 'title_page.author'))->toBe('Max Mustermann')
        ->and(data_get($titlePageSection, 'title_page.class'))->toBe('8M')
        ->and(data_get($titlePageSection, 'title_page.advisor'))->toBe('Mag. Erika Muster')
        ->and(data_get($titlePageSection, 'title_page.date'))->toBe('März 2026')
        ->and(data_get($titlePageSection, 'title_page.page_number'))->toBe(1)
        ->and(data_get($titlePageSection, 'title_page.found_images_count'))->toBe(0);

    expect($titlePageOtherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schule' && ($item['value'] ?? null) === 'BORG Musterstadt'))->toBeTrue()
        ->and($titlePageOtherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Fach' && ($item['value'] ?? null) === 'Medieninformatik'))->toBeTrue();

    $getResponse = $this->getJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    expect(collect($getResponse->json('data.missing_required_section_keys'))->all())->not->toContain('bibliography');
});

test('returns failed extraction result for non docx main document', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    Storage::disk('local')->put('aba-tests/test-main.pdf', 'fake-pdf-content');

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main.pdf',
        'stored_name' => 'test-main.pdf',
        'path' => 'aba-tests/test-main.pdf',
        'disk' => 'local',
        'mime_type' => 'application/pdf',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.errors.0', 'Für die Extraktion wird derzeit ein DOCX-Hauptdokument benötigt.');
});

test('ignores legacy analysis runs when loading the latest extraction', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'created_by_user_id' => $user->id,
        'status' => 'completed',
        'status_message' => 'Analyse abgeschlossen (Review erforderlich).',
        'source_original_name' => 'legacy-analysis.docx',
        'started_at' => now()->subMinutes(2),
        'completed_at' => now()->subMinute(),
        'summary' => [
            'analysis_stats' => [
                'document_type' => 'aba',
                'detected_record_count' => 40,
            ],
        ],
    ]);

    $this->actingAs($user, 'sanctum');

    $this->getJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data', null);
});
