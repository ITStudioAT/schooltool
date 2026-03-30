<?php

use App\Models\Aba;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\AbaExtractionPersister;
use App\Services\AbaExtractionResultBuilder;
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

function createDocxFixture(string $path, bool $withTitlePageImage = false, ?array $titlePageLines = null): void
{
    $document = new PhpWord;
    $section = $document->addSection();
    $section->addTitle('Titelblatt', 1);
    $temporaryImage = null;

    if ($withTitlePageImage) {
        $temporaryImage = tempnam(sys_get_temp_dir(), 'aba-title-image-');
        if (! is_string($temporaryImage) || $temporaryImage === '') {
            throw new RuntimeException('Temporary image file could not be created.');
        }

        @unlink($temporaryImage);
        $temporaryImage .= '.png';

        file_put_contents(
            $temporaryImage,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==', true)
        );

        $section->addImage($temporaryImage, [
            'width' => 96,
            'height' => 96,
        ]);
    }

    $titlePageLines ??= [
        'Titel der Arbeit',
        'Untertitel der Arbeit',
        'Schule: BORG Musterstadt',
        'Fach: Medieninformatik',
        'Verfasser: Max Mustermann',
        'Klasse: 8M',
        'Betreuer: Mag. Erika Muster',
        'Datum: März 2026',
    ];

    foreach ($titlePageLines as $line) {
        $value = trim((string) $line);
        if ($value === '') {
            $section->addTextBreak();

            continue;
        }

        $section->addText($value);
    }

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
    if (is_string($temporaryImage)) {
        @unlink($temporaryImage);
    }
}

function createDocxFixtureWithManualNumberedToc(string $path): void
{
    $document = new PhpWord;
    $document->addNumberingStyle('aba-manual-toc', [
        'type' => 'multilevel',
        'levels' => [
            ['format' => 'decimal', 'text' => '%1.', 'left' => 720, 'hanging' => 360, 'tabPos' => 720],
            ['format' => 'decimal', 'text' => '%1.%2', 'left' => 1080, 'hanging' => 360, 'tabPos' => 1080],
        ],
    ]);

    $section = $document->addSection();
    foreach ([
        'Die Rolle der Fotografie in sozialen Medien',
        'Vorwissenschaftliche Arbeit verfasst von',
        'Sandra Banu',
        'Klasse: 8M',
        'Betreuer: Dipl.-Ing. Günther Kron',
        '',
    ] as $line) {
        $value = trim((string) $line);
        if ($value === '') {
            $section->addTextBreak();

            continue;
        }

        $section->addText($value);
    }

    $section->addText('Inhaltsverzeichnis');
    $section->addTextBreak();
    $section->addText('Abstract');
    $section->addListItem('Einleitung', 0, null, 'aba-manual-toc');
    $section->addListItem('Entwicklung der Fotografie im Kontext sozialer Medien', 0, null, 'aba-manual-toc');
    $section->addListItem('Fotografie vor dem Zeitalter sozialer Medien', 1, null, 'aba-manual-toc');
    $section->addListItem('Übergang zu Plattformen wie Instagram und Pinterest', 1, null, 'aba-manual-toc');
    $section->addListItem('Veränderungen in Ästhetik und Verbreitung', 1, null, 'aba-manual-toc');
    $section->addListItem('Fazit', 0, null, 'aba-manual-toc');
    $section->addListItem('Zusammenfassung der wichtigsten Erkenntnisse', 1, null, 'aba-manual-toc');
    $section->addListItem('Reflexion und Ausblick', 1, null, 'aba-manual-toc');
    $section->addText('Literaturverzeichnis');
    $section->addText('Abbildungsverzeichnis');
    $section->addPageBreak();
    $section->addTitle('Einleitung', 1);
    $section->addText('Einleitungstext der Arbeit.');
    $section->addTitle('Entwicklung der Fotografie im Kontext sozialer Medien', 1);
    $section->addText('Kapiteltext.');
    $section->addTitle('Fotografie vor dem Zeitalter sozialer Medien', 2);
    $section->addText('Unterkapiteltext.');
    $section->addTitle('Fazit', 1);
    $section->addText('Schlusstext.');
    $section->addTitle('Literaturverzeichnis', 1);
    $section->addText('Quelle A');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'aba-docx-manual-toc-');
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

test('preserves numbering in manually formatted docx table of contents entries', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixtureWithManualNumberedToc('aba-tests/test-main-with-manual-numbered-toc.docx');

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-manual-numbered-toc.docx',
        'stored_name' => 'test-main-with-manual-numbered-toc.docx',
        'path' => 'aba-tests/test-main-with-manual-numbered-toc.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $tableOfContents = collect($response->json('data.sections'))
        ->firstWhere('key', 'table_of_contents');
    $entries = collect(data_get($tableOfContents, 'table_of_contents.entries', []))->values()->all();

    expect($tableOfContents)->toBeArray()
        ->and(data_get($tableOfContents, 'table_of_contents.heading'))->toBe('Inhaltsverzeichnis')
        ->and($entries)->toContain('1. Einleitung')
        ->and($entries)->toContain('2. Entwicklung der Fotografie im Kontext sozialer Medien')
        ->and($entries)->toContain('2.1 Fotografie vor dem Zeitalter sozialer Medien')
        ->and($entries)->toContain('2.2 Übergang zu Plattformen wie Instagram und Pinterest')
        ->and($entries)->toContain('3. Fazit')
        ->and($entries)->toContain('3.1 Zusammenfassung der wichtigsten Erkenntnisse')
        ->and($entries)->not->toContain('- Einleitung');
});

test('does not overwrite previously edited aba fields when extraction overwrite is disabled', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
        'title' => 'Manuell gesetzter Titel',
        'student_name' => 'Manuell gesetzt',
        'student_class' => '9Z',
        'title_page_overrides' => [
            'subtitle' => 'Manueller Untertitel',
            'advisor' => 'Manuelle Betreuung',
            'school_full' => 'Manuelle Schule',
            'date' => 'Manuelles Datum',
        ],
    ]);

    createDocxFixture('aba-tests/test-main-preserve-fields.docx');

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-preserve-fields.docx',
        'stored_name' => 'test-main-preserve-fields.docx',
        'path' => 'aba-tests/test-main-preserve-fields.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $this->postJson("/api/admin/abas/{$aba->id}/extraction", [
        'data' => [
            'overwrite_existing_fields' => false,
        ],
    ])->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $freshAba = $aba->fresh();

    expect($freshAba->title)->toBe('Manuell gesetzter Titel')
        ->and($freshAba->student_name)->toBe('Manuell gesetzt')
        ->and($freshAba->student_class)->toBe('9Z')
        ->and($freshAba->title_page_overrides)->toMatchArray([
            'subtitle' => 'Manueller Untertitel',
            'advisor' => 'Manuelle Betreuung',
            'school_full' => 'Manuelle Schule',
            'date' => 'Manuelles Datum',
        ]);
});

test('overwrites editable aba fields with extracted values when extraction overwrite is enabled', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
        'title' => 'Manuell gesetzter Titel',
        'student_name' => 'Manuell gesetzt',
        'student_class' => '9Z',
        'title_page_overrides' => [
            'subtitle' => 'Manueller Untertitel',
            'advisor' => 'Manuelle Betreuung',
            'school_full' => 'Manuelle Schule',
            'date' => 'Manuelles Datum',
        ],
    ]);

    createDocxFixture('aba-tests/test-main-overwrite-fields.docx');

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-overwrite-fields.docx',
        'stored_name' => 'test-main-overwrite-fields.docx',
        'path' => 'aba-tests/test-main-overwrite-fields.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $this->postJson("/api/admin/abas/{$aba->id}/extraction", [
        'data' => [
            'overwrite_existing_fields' => true,
        ],
    ])->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $freshAba = $aba->fresh();

    expect($freshAba->title)->toBe('Titel der Arbeit')
        ->and($freshAba->student_name)->toBe('Max Mustermann')
        ->and($freshAba->student_class)->toBe('8M')
        ->and($freshAba->title_page_overrides)->toMatchArray([
            'subtitle' => 'Untertitel der Arbeit',
            'advisor' => 'Mag. Erika Muster',
            'school_full' => 'BORG, Musterstadt',
            'date' => 'März 2026',
        ]);
});

test('clears previously edited aba fields when extraction overwrite is enabled and the new extraction has missing values', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
        'title' => 'Manuell gesetzter Titel',
        'student_name' => 'Manuell gesetzt',
        'student_class' => '9Z',
        'title_page_overrides' => [
            'subtitle' => 'Manueller Untertitel',
            'advisor' => 'Manuelle Betreuung',
            'school_full' => 'Manuelle Schule',
            'date' => 'Manuelles Datum',
        ],
    ]);

    createDocxFixture(
        'aba-tests/test-main-overwrite-clears-missing-fields.docx',
        false,
        [
            'Neuer extrahierter Titel',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-overwrite-clears-missing-fields.docx',
        'stored_name' => 'test-main-overwrite-clears-missing-fields.docx',
        'path' => 'aba-tests/test-main-overwrite-clears-missing-fields.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $this->postJson("/api/admin/abas/{$aba->id}/extraction", [
        'data' => [
            'overwrite_existing_fields' => true,
        ],
    ])->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $freshAba = $aba->fresh();

    expect($freshAba->title)->toBe('Neuer extrahierter Titel')
        ->and($freshAba->student_name)->toBe('')
        ->and($freshAba->student_class)->toBeNull()
        ->and($freshAba->title_page_overrides)->toMatchArray([
            'subtitle' => null,
            'advisor' => null,
            'school_full' => null,
            'date' => null,
        ]);
});

test('persists overlong section titles without failing the extraction result insert', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main.docx',
        'stored_name' => 'test-main.docx',
        'path' => 'aba-tests/test-main.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $run = app(AbaExtractionPersister::class)->startRun($user, $aba, $attachment);
    $overlongSectionTitle = str_repeat('In der Analyse wird der Wirkstoff Adalimumab herausgegriffen. ', 8);

    app(AbaExtractionPersister::class)->markCompleted(
        $run,
        [
            'sections' => [[
                'section_key' => 'chapter-1',
                'section_type' => 'chapter',
                'section_title' => $overlongSectionTitle,
                'extracted_text' => 'Kurzer Abschnittstext.',
            ]],
            'document' => [
                'text_length' => 1234,
                'text_length_without_spaces' => 1000,
            ],
        ],
        [
            'matched_rule_keys_by_section' => [],
        ],
    );

    $result = $run->fresh('results')->results->sole();

    expect($result->section_title)
        ->not->toBeNull()
        ->and(mb_strlen((string) $result->section_title))->toBe(255)
        ->and((string) $result->section_title)->toBe(mb_substr($overlongSectionTitle, 0, 255));
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

test('reports title page images when the docx contains an image on the title page', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture('aba-tests/test-main-with-image.docx', true);

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-image.docx',
        'stored_name' => 'test-main-with-image.docx',
        'path' => 'aba-tests/test-main-with-image.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');
    $titlePageImages = collect(data_get($titlePageSection, 'title_page.images', []));
    $previewNotes = collect(data_get($titlePageSection, 'title_page.preview_notes', []));
    $imageUrl = (string) ($titlePageImages->first()['url'] ?? '');

    expect(data_get($titlePageSection, 'title_page.found_images_count'))->toBeGreaterThan(0)
        ->and($titlePageImages)->not->toBeEmpty()
        ->and($imageUrl)->not->toBe('')
        ->and($previewNotes->contains('DOCX-Seitenanalyse: 1 Bild auf Seite 1 erkannt.'))->toBeTrue()
        ->and($previewNotes->contains('Vorschau enthält 1 renderbares Titelblatt-Bild/Logo.'))->toBeTrue();

    $this->get($imageUrl)
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('extracts a multi-line school block from the title page', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture(
        'aba-tests/test-main-with-school-block.docx',
        false,
        [
            'Christian Doppler-Gymnasium',
            'Franz-Josef-Kai 41',
            '5020 Salzburg',
            '',
            'Die Rolle der Medien in der politischen Meinungsbildung',
            '',
            'Verfasst von',
            'Yvonne Pucher',
            'Betreuer: Dipl.-Ing. Günther Kron',
            'Klasse: 8M',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-school-block.docx',
        'stored_name' => 'test-main-with-school-block.docx',
        'path' => 'aba-tests/test-main-with-school-block.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');
    $titlePageOtherThings = collect(data_get($titlePageSection, 'title_page.other_things', []));

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Die Rolle der Medien in der politischen Meinungsbildung')
        ->and($titlePageOtherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schule' && ($item['value'] ?? null) === 'Christian Doppler-Gymnasium'))->toBeTrue()
        ->and($titlePageOtherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schuladresse' && ($item['value'] ?? null) === 'Franz-Josef-Kai 41'))->toBeTrue()
        ->and($titlePageOtherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schulort' && ($item['value'] ?? null) === '5020 Salzburg'))->toBeTrue()
        ->and($titlePageOtherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schule (vollständig)' && ($item['value'] ?? null) === 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg'))->toBeTrue();
});

test('extracts title and advisor correctly when school block follows the date on the title page', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture(
        'aba-tests/test-main-with-bottom-school-block.docx',
        false,
        [
            'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
            '',
            'Vorwissenschaftliche Arbeit verfasst von',
            'Hanna Danninger',
            'Klasse 8B',
            'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
            '',
            'Februar 2024',
            'Christian-Doppler-Gymnasium',
            'Franz-Josef-Kai 41',
            '5020 Salzburg',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-bottom-school-block.docx',
        'stored_name' => 'test-main-with-bottom-school-block.docx',
        'path' => 'aba-tests/test-main-with-bottom-school-block.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePageSection, 'title_page.author'))->toBe('Hanna Danninger')
        ->and(data_get($titlePageSection, 'title_page.class'))->toBe('8B')
        ->and(data_get($titlePageSection, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron')
        ->and(data_get($titlePageSection, 'title_page.date'))->toBe('Februar 2024');
});

test('extracts the actual title when the title page ends with a location placeholder date', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture(
        'aba-tests/test-main-with-location-placeholder-date.docx',
        false,
        [
            'Christian-Doppler-Gymnasium Salzburg',
            'Franz-Josef Kai 41',
            '5020 Salzburg',
            '',
            '',
            '',
            'Behandlungsmethoden bei Neurodermitis',
            '',
            '',
            'Betreuer: Mag. Ungeringer-Kron Gerhild',
            '',
            'Eingereicht von: Anh Vu Duy',
            'Klasse: 8C',
            '',
            'Salzburg, Abgabedatum',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-location-placeholder-date.docx',
        'stored_name' => 'test-main-with-location-placeholder-date.docx',
        'path' => 'aba-tests/test-main-with-location-placeholder-date.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Behandlungsmethoden bei Neurodermitis')
        ->and(data_get($titlePageSection, 'title_page.subtitle'))->toBeNull()
        ->and(data_get($titlePageSection, 'title_page.author'))->toBe('Anh Vu Duy')
        ->and(data_get($titlePageSection, 'title_page.class'))->toBe('8C')
        ->and(data_get($titlePageSection, 'title_page.advisor'))->toBe('Mag. Ungeringer-Kron Gerhild');
});

test('extracts school name address and city when the school line ends with a city fragment', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture(
        'aba-tests/test-main-with-inline-school-city-fragment.docx',
        false,
        [
            'Christian-Doppler-Gymnasium Salzburg',
            'Franz-Josef Kai 41',
            '5020 Salzburg',
            '',
            '',
            '',
            'Behandlungsmethoden bei Neurodermitis',
            '',
            '',
            'Betreuer: Mag. Ungeringer-Kron Gerhild',
            '',
            'Eingereicht von: Anh Vu Duy',
            'Klasse: 8C',
            '',
            'Salzburg, Abgabedatum',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-inline-school-city-fragment.docx',
        'stored_name' => 'test-main-with-inline-school-city-fragment.docx',
        'path' => 'aba-tests/test-main-with-inline-school-city-fragment.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.school'))->toBe('Christian-Doppler-Gymnasium')
        ->and(data_get($titlePageSection, 'title_page.school_address'))->toBe('Franz-Josef Kai 41')
        ->and(data_get($titlePageSection, 'title_page.school_city'))->toBe('5020 Salzburg')
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Behandlungsmethoden bei Neurodermitis');
});

test('returns a cleaned title when the persisted title page contains a single-line school block', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    $attachment = AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main.docx',
        'stored_name' => 'test-main.docx',
        'path' => 'aba-tests/test-main.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $documentExtraction = [
        'document' => [
            'source_original_name' => 'test-main.docx',
            'page_image_counts' => [],
        ],
        'title_page_processing' => [
            'ui_model' => [
                'preview_title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich Christian-Doppler-Gymnasium Franz-Josef-Kai 41 5020 Salzburg',
                'preview_author' => 'Hanna Danninger',
                'preview_advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                'preview_class' => '8B',
                'preview_date' => 'Februar 2024',
                'additional_properties' => [],
                'preview_notes' => [],
            ],
            'logo' => [
                'logo_detected_count' => 0,
            ],
            'logos' => [],
        ],
        'sections' => [
            [
                'section_key' => 'title-page',
                'section_type' => 'title_page',
                'section_title' => 'Titelseite',
                'extracted_text' => implode("\n", [
                    'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                    'Vorwissenschaftliche Arbeit verfasst von',
                    'Hanna Danninger',
                    'Klasse 8B',
                    'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                    'Februar 2024',
                    'Christian-Doppler-Gymnasium Franz-Josef-Kai 41 5020 Salzburg',
                ]),
                'start_page' => 1,
                'end_page' => 1,
                'metadata' => [
                    'title_page_details' => [
                        'title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                        'submitter' => 'Hanna Danninger',
                        'advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                        'class' => '8B',
                        'date_context' => 'Februar 2024',
                    ],
                ],
            ],
        ],
        'outline' => [],
        'toc_lines' => [],
        'diagnostics' => [],
    ];
    $matchedSections = [
        'sections' => [
            [
                'key' => 'title_page',
                'label' => 'Titelblatt',
                'required' => true,
                'found' => true,
                'uncertain' => false,
                'confidence' => 0.99,
                'matched_heading' => 'Titelseite',
                'preview_text' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                'start_index' => 0,
                'end_index' => 0,
                'matched_section_keys' => ['title-page'],
                'warnings' => [],
            ],
        ],
        'missing_required_section_keys' => [],
        'found_optional_section_keys' => [],
        'uncertain_matches' => [],
        'unmatched_blocks_count' => 0,
        'warnings' => [],
        'errors' => [],
        'matched_rule_keys_by_section' => [
            'title-page' => ['title_page'],
        ],
    ];
    $summary = app(AbaExtractionResultBuilder::class)->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        $documentExtraction,
        $matchedSections,
    );

    $run = app(AbaExtractionPersister::class)->startRun($user, $aba, $attachment);
    app(AbaExtractionPersister::class)->markCompleted($run, $documentExtraction, $summary);

    $this->actingAs($user, 'sanctum');

    $response = $this->getJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful();

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePageSection, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron')
        ->and(data_get($titlePageSection, 'title_page.school'))->toBe('Christian-Doppler-Gymnasium')
        ->and(data_get($titlePageSection, 'title_page.school_address'))->toBe('Franz-Josef-Kai 41')
        ->and(data_get($titlePageSection, 'title_page.school_city'))->toBe('5020 Salzburg');
});

test('extracts title correctly when a location-prefixed date appears on the title page', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture(
        'aba-tests/test-main-with-location-date.docx',
        false,
        [
            'Christian-Doppler-Gymnasium',
            'Franz-Josef Kai 41',
            '5020 Salzburg',
            '',
            'Der Einfluss ionisierender Strahlung auf den menschlichen Körper',
            '',
            'Betreuerin: Mag. Gerhild Ungeringer-Kron',
            '',
            'Eingereicht von: Marx Azad',
            'Klasse: 8C',
            '',
            'Salzburg, 24.02.2023',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-location-date.docx',
        'stored_name' => 'test-main-with-location-date.docx',
        'path' => 'aba-tests/test-main-with-location-date.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Der Einfluss ionisierender Strahlung auf den menschlichen Körper')
        ->and(data_get($titlePageSection, 'title_page.subtitle'))->toBeNull()
        ->and(data_get($titlePageSection, 'title_page.author'))->toBe('Marx Azad')
        ->and(data_get($titlePageSection, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron')
        ->and(data_get($titlePageSection, 'title_page.date'))->toBe('24.02.2023');
});

test('extracts title correctly when a toc entry was appended to the title line', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture(
        'aba-tests/test-main-with-toc-title-fragment.docx',
        false,
        [
            'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich 1 Einleitung 5',
            '',
            'Vorwissenschaftliche Arbeit verfasst von',
            'Hanna Danninger',
            'Klasse 8B',
            'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
            '',
            'Februar 2024',
            'Christian-Doppler-Gymnasium',
            'Franz-Josef-Kai 41',
            '5020 Salzburg',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-toc-title-fragment.docx',
        'stored_name' => 'test-main-with-toc-title-fragment.docx',
        'path' => 'aba-tests/test-main-with-toc-title-fragment.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePageSection, 'title_page.author'))->toBe('Hanna Danninger')
        ->and(data_get($titlePageSection, 'title_page.class'))->toBe('8B')
        ->and(data_get($titlePageSection, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron')
        ->and(data_get($titlePageSection, 'title_page.date'))->toBe('Februar 2024');
});

test('extracts title correctly when a toc heading suffix was appended to the title line', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture(
        'aba-tests/test-main-with-toc-heading-suffix.docx',
        false,
        [
            'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich - Einleitung',
            '',
            'Vorwissenschaftliche Arbeit verfasst von',
            'Hanna Danninger',
            'Klasse 8B',
            'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
            '',
            'Februar 2024',
            'Christian-Doppler-Gymnasium',
            'Franz-Josef-Kai 41',
            '5020 Salzburg',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-toc-heading-suffix.docx',
        'stored_name' => 'test-main-with-toc-heading-suffix.docx',
        'path' => 'aba-tests/test-main-with-toc-heading-suffix.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePageSection, 'title_page.author'))->toBe('Hanna Danninger')
        ->and(data_get($titlePageSection, 'title_page.class'))->toBe('8B')
        ->and(data_get($titlePageSection, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron')
        ->and(data_get($titlePageSection, 'title_page.date'))->toBe('Februar 2024');
});

test('does not expose a subtitle when only a structure heading with punctuation was extracted', function () {
    $user = createAbaTeacher($this->school, $this->schoolyear);
    $aba = Aba::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $user->id,
    ]);

    createDocxFixture(
        'aba-tests/test-main-with-heading-subtitle-fragment.docx',
        false,
        [
            'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich - Einleitung',
            '',
            'Vorwissenschaftliche Arbeit verfasst von',
            'Hanna Danninger',
            'Klasse 8B',
            'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
            '',
            'Februar 2024',
            'Christian-Doppler-Gymnasium',
            'Franz-Josef-Kai 41',
            '5020 Salzburg',
        ],
    );

    AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => 'test-main-with-heading-subtitle-fragment.docx',
        'stored_name' => 'test-main-with-heading-subtitle-fragment.docx',
        'path' => 'aba-tests/test-main-with-heading-subtitle-fragment.docx',
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $titlePageSection = collect($response->json('data.sections'))
        ->firstWhere('key', 'title_page');

    expect($titlePageSection)->toBeArray()
        ->and(data_get($titlePageSection, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePageSection, 'title_page.subtitle'))->toBeNull()
        ->and(data_get($titlePageSection, 'title_page.author'))->toBe('Hanna Danninger');
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
