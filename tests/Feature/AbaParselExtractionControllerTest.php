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
use Shipfastlabs\Parsel;
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

afterEach(function () {
    Parsel::flush();
});

function createParselExtractionTeacher(School $school, Schoolyear $schoolyear): User
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

function createParselExtractionAba(User $user, School $school, Schoolyear $schoolyear): Aba
{
    return Aba::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
    ]);
}

function createParselExtractionAttachment(Aba $aba, User $user, string $path = 'aba-tests/parsel-main.docx'): AbaAttachment
{
    Storage::disk('local')->put($path, 'fake docx bytes for parsel fake runner');

    return AbaAttachment::factory()->create([
        'aba_id' => $aba->id,
        'document_kind' => AbaAttachment::DOCUMENT_KIND_MAIN,
        'original_name' => basename($path),
        'stored_name' => basename($path),
        'path' => $path,
        'disk' => 'local',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'uploaded_by_user_id' => $user->id,
    ]);
}

/**
 * @param  array<int, string>  $foundKeys
 * @return array<string,mixed>
 */
function parselExtractionSummaryFixture(array $foundKeys, string $engine = 'conventional'): array
{
    $sections = [
        [
            'key' => 'title_page',
            'label' => 'Titelblatt',
            'required' => true,
            'found' => in_array('title_page', $foundKeys, true),
            'preview_text' => 'Titel der Arbeit',
        ],
        [
            'key' => 'introduction',
            'label' => 'Einleitung',
            'required' => true,
            'found' => in_array('introduction', $foundKeys, true),
            'preview_text' => 'Einleitungstext',
        ],
        [
            'key' => 'bibliography',
            'label' => 'Literaturverzeichnis',
            'required' => true,
            'found' => in_array('bibliography', $foundKeys, true),
            'preview_text' => 'Quelle A',
        ],
    ];

    $foundRequired = array_values(array_filter(
        ['title_page', 'introduction', 'bibliography'],
        fn (string $key): bool => in_array($key, $foundKeys, true),
    ));

    $missingRequired = array_values(array_diff(['title_page', 'introduction', 'bibliography'], $foundRequired));

    return [
        '_extraction_options' => [
            'engine' => $engine,
            'label' => $engine === 'parsel' ? 'Extraktion 2' : 'Extraktion',
        ],
        'document' => [
            'parser' => $engine === 'parsel' ? 'shipfastlabs/parsel' : 'docx-local',
        ],
        'sections' => $sections,
        'found_required_section_keys' => $foundRequired,
        'missing_required_section_keys' => $missingRequired,
        'found_optional_section_keys' => [],
        'uncertain_matches' => [],
        'unmatched_blocks_count' => 0,
        'warnings' => [],
        'errors' => [],
    ];
}

function createParselExtractionRun(
    Aba $aba,
    User $user,
    ?AbaAttachment $attachment,
    string $statusMessage,
    array $summary,
    int $textLength,
): AbaAnalysisRun {
    return AbaAnalysisRun::query()->create([
        'aba_id' => $aba->id,
        'aba_attachment_id' => $attachment?->id,
        'created_by_user_id' => $user->id,
        'status' => AbaAnalysisRun::STATUS_COMPLETED,
        'status_message' => $statusMessage,
        'source_original_name' => $attachment?->original_name,
        'source_path' => $attachment?->path,
        'source_mime_type' => $attachment?->mime_type,
        'started_at' => now()->subMinute(),
        'completed_at' => now(),
        'summary' => $summary,
        'extracted_sections_count' => count($summary['sections'] ?? []),
        'extracted_figures_count' => 0,
        'text_length' => $textLength,
        'text_length_without_spaces' => $textLength - 5,
    ]);
}

test('starts parsel extraction and keeps it separate from the conventional latest extraction', function () {
    $parsel = Parsel::fake([
        '--format text' => implode("\n", [
            'Titelblatt',
            'Titel der Arbeit',
            'Verfasser: Max Mustermann',
            'Inhaltsverzeichnis',
            '1 Einleitung 1',
            'Einleitung',
            'Einleitungstext der Arbeit.',
            'Literaturverzeichnis',
            'Quelle A',
        ]),
    ]);
    $user = createParselExtractionTeacher($this->school, $this->schoolyear);
    $aba = createParselExtractionAba($user, $this->school, $this->schoolyear);
    createParselExtractionAttachment($aba, $user);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson("/api/admin/abas/{$aba->id}/extraction/parsel")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.engine', 'parsel')
        ->assertJsonPath('data.document.parser', 'shipfastlabs/parsel')
        ->assertJsonPath('data.status_message', 'Extraktion 2 abgeschlossen.');

    $this->getJson("/api/admin/abas/{$aba->id}/extraction/parsel")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $response->json('data.id'))
        ->assertJsonPath('data.engine', 'parsel');

    $this->getJson("/api/admin/abas/{$aba->id}/extraction")
        ->assertSuccessful()
        ->assertJsonPath('data', null);

    expect($parsel->recordedCommands())->toHaveCount(1)
        ->and(implode(' ', $parsel->recordedCommands()[0]))->toContain('--format text')
        ->and(implode(' ', $parsel->recordedCommands()[0]))->toContain('--no-ocr');
});

test('compares the latest conventional extraction with the latest parsel extraction', function () {
    $user = createParselExtractionTeacher($this->school, $this->schoolyear);
    $aba = createParselExtractionAba($user, $this->school, $this->schoolyear);
    $attachment = createParselExtractionAttachment($aba, $user);

    createParselExtractionRun(
        $aba,
        $user,
        $attachment,
        'Extraktion abgeschlossen.',
        parselExtractionSummaryFixture(['title_page', 'introduction'], 'conventional'),
        120,
    );
    createParselExtractionRun(
        $aba,
        $user,
        $attachment,
        'Extraktion 2 abgeschlossen.',
        parselExtractionSummaryFixture(['title_page', 'introduction', 'bibliography'], 'parsel'),
        160,
    );

    $this->actingAs($user, 'sanctum');

    $response = $this->getJson("/api/admin/abas/{$aba->id}/extraction/compare")
        ->assertSuccessful()
        ->assertJsonPath('data.conventional.engine', 'conventional')
        ->assertJsonPath('data.parsel.engine', 'parsel')
        ->assertJsonPath('data.comparison.ready', true)
        ->assertJsonPath('data.comparison.delta.required_found', 1)
        ->assertJsonPath('data.comparison.delta.text_length', 40);

    $bibliographyRow = collect($response->json('data.comparison.sections'))
        ->firstWhere('key', 'bibliography');

    expect($bibliographyRow)->toBeArray()
        ->and($bibliographyRow['status'] ?? null)->toBe('only_parsel')
        ->and($bibliographyRow['conventional_found'] ?? null)->toBeFalse()
        ->and($bibliographyRow['parsel_found'] ?? null)->toBeTrue();
});
