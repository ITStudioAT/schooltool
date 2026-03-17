<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);

    $this->tempFiles = [];
});

afterEach(function () {
    foreach ($this->tempFiles as $path) {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
});

function rememberPandocDebugTempPath(string $path): string
{
    $current = is_array(test()->tempFiles ?? null) ? test()->tempFiles : [];
    $current[] = $path;
    test()->tempFiles = $current;

    return $path;
}

function createPandocDebugUserWithRole(string $role): User
{
    $user = User::factory()->create([
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

function createPandocDebugFakePandocBinary(): string
{
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $tmp = tempnam(sys_get_temp_dir(), 'aba_pandoc_debug_');
    if (! is_string($tmp) || $tmp === '') {
        throw new RuntimeException('Konnte temporäre Pandoc-Testdatei nicht erstellen.');
    }

    $path = $isWindows ? $tmp.'.bat' : $tmp.'.sh';
    @rename($tmp, $path);

    if ($isWindows) {
        $script = "@echo off\r\n"
            .'if "%1"=="--version" ('."\r\n"
            ."  echo pandoc 3.1.1\r\n"
            ."  exit /b 0\r\n"
            .")\r\n"
            .'echo {"pandoc-api-version":[1,23,0],"meta":{},"blocks":[{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"Auswirkungen digitaler Medien auf Lernmotivation im Unterricht"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"Inhaltsverzeichnis"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"1. Einleitung 5"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"1. Einleitung"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"Literaturverzeichnis"}]]},{"t":"Header","c":[1,["",[],[]],[{"t":"Str","c":"Eigenständigkeitserklärung"}]]},{"t":"Header","c":[1,["",[],[]],[]]},{"t":"Para","c":[{"t":"Strong","c":[{"t":"Str","c":"Osteoporose: ...3.1.5. Knochendichte"}]}]},{"t":"Para","c":[{"t":"Image","c":[["",[],[]],[{"t":"Str","c":"Abbildung 1"}],["media/image1.png",""]]}]},{"t":"Para","c":[{"t":"Str","c":"Absatz"}]}]}'."\r\n"
            ."exit /b 0\r\n";
    } else {
        $script = "#!/usr/bin/env sh\n"
            ."if [ \"$1\" = \"--version\" ]; then\n"
            ."  echo \"pandoc 3.1.1\"\n"
            ."  exit 0\n"
            ."fi\n"
            ."echo '{\"pandoc-api-version\":[1,23,0],\"meta\":{},\"blocks\":[{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"Auswirkungen digitaler Medien auf Lernmotivation im Unterricht\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"Inhaltsverzeichnis\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"1. Einleitung 5\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"1. Einleitung\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"Literaturverzeichnis\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"Eigenständigkeitserklärung\"}]]},{\"t\":\"Header\",\"c\":[1,[\"\",[],[]],[]]},{\"t\":\"Para\",\"c\":[{\"t\":\"Strong\",\"c\":[{\"t\":\"Str\",\"c\":\"Osteoporose: ...3.1.5. Knochendichte\"}]}]},{\"t\":\"Para\",\"c\":[{\"t\":\"Image\",\"c\":[[\"\",[],[]],[{\"t\":\"Str\",\"c\":\"Abbildung 1\"}],[\"media/image1.png\",\"\"]]}]},{\"t\":\"Para\",\"c\":[{\"t\":\"Str\",\"c\":\"Absatz\"}]}]}'\n"
            ."exit 0\n";
    }

    file_put_contents($path, $script);
    if (! $isWindows) {
        @chmod($path, 0755);
    }

    return rememberPandocDebugTempPath($path);
}

test('admin can run pandoc debug endpoint and receives normalized summary', function () {
    $user = createPandocDebugUserWithRole('admin');
    $fakePandoc = createPandocDebugFakePandocBinary();

    config()->set('aba_pandoc.enabled', true);
    config()->set('aba_pandoc.binary', $fakePandoc);
    config()->set('aba_pandoc.timeout_seconds', 10);

    $file = UploadedFile::fake()->create(
        'testdokument.docx',
        120,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/ai-settings/pandoc-debug/run', [
            'file' => $file,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('summary.normalized_block_count', fn (mixed $value): bool => is_int($value) && $value >= 9)
        ->assertJsonPath('summary.heading_count', fn (mixed $value): bool => is_int($value) && $value >= 6)
        ->assertJsonPath('summary.image_count', 1)
        ->assertJsonPath('summary.section_hint_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('summary.document_title_candidate_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('summary.empty_heading_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('summary.probable_toc_artifact_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('summary.suspicious_heading_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('summary.zone_count', fn (mixed $value): bool => is_int($value) && $value >= 3)
        ->assertJsonPath('summary.zone_table_of_contents_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('summary.zone_main_content_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('summary.zone_bibliography_area_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('summary.zone_declaration_area_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('review.counts.main_sections_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('review.counts.document_title_candidate_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('review.counts.uncertain_heading_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('review.counts.empty_heading_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('review.counts.probable_toc_artifact_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('review.counts.suspicious_heading_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('review.counts.zone_count', fn (mixed $value): bool => is_int($value) && $value >= 3)
        ->assertJsonPath('review.zone_overview.0.zone_key', fn (mixed $value): bool => is_string($value))
        ->assertJsonPath('review.bibliography_groups.0.group_key', fn (mixed $value): bool => is_string($value))
        ->assertJsonPath('comparison.format', 'aba_path_compare_v1')
        ->assertJsonPath('comparison.paths.legacy_local.label', 'Lokaler Pfad')
        ->assertJsonPath('comparison.paths.pandoc.label', 'Pandoc-Pfad')
        ->assertJsonPath('comparison.paths.openai_pdf.supported', false)
        ->assertJsonPath('comparison.summary.required_zone_count', fn (mixed $value): bool => is_int($value) && $value >= 1)
        ->assertJsonPath('comparison.matrix.zones.0.zone_key', fn (mixed $value): bool => is_string($value));
});

test('register admin cannot access pandoc debug endpoint', function () {
    $user = createPandocDebugUserWithRole('register_admin');
    $file = UploadedFile::fake()->create('testdokument.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/ai-settings/pandoc-debug/run', [
            'file' => $file,
        ])
        ->assertForbidden();
});

test('pandoc debug endpoint validates docx extension', function () {
    $user = createPandocDebugUserWithRole('admin');
    $file = UploadedFile::fake()->create('falsch.pdf', 120, 'application/pdf');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/aba/ai-settings/pandoc-debug/run', [
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('file');
});

test('admin router registers pandoc debug view route', function () {
    $routerContent = file_get_contents(resource_path('routes/admin.js'));

    expect($routerContent)
        ->toContain('/admin/aba/ai-settings/pandoc-debug')
        ->toContain('AbaPandocDebug.vue');
});

test('ai settings page links to pandoc debug view', function () {
    $content = file_get_contents(resource_path('js/pages/admin/aba/AbaAiSettings.vue'));

    expect($content)
        ->toContain('/admin/aba/ai-settings/pandoc-debug')
        ->toContain('DOCX prüfen');
});

test('pandoc debug page shows condensed review sections for human checks', function () {
    $content = file_get_contents(resource_path('js/pages/admin/aba/AbaPandocDebug.vue'));

    expect($content)
        ->toContain('Prüfbericht kopieren')
        ->toContain('Prüfbericht kopiert')
        ->toContain('Vergleich kopieren')
        ->toContain('Vergleich kopiert')
        ->toContain('AHS-ABA · Pandoc-Prüfbericht')
        ->toContain('AHS-ABA · Pfadvergleich')
        ->toContain('Pfadvergleich: Lokal vs. Pandoc')
        ->toContain('Dokumentphasen / Zonen')
        ->toContain('Erkannte Hauptabschnitte')
        ->toContain('Dokumenttitel-Kandidaten')
        ->toContain('Quellen-/Verzeichnisbereich')
        ->toContain('Unsichere Überschriften')
        ->toContain('Leere Überschriften')
        ->toContain('Wahrscheinliche Inhaltsverzeichnis-Einträge')
        ->toContain('Auffällige Überschriftentexte');
});
