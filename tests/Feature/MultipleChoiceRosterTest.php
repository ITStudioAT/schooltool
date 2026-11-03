<?php

use App\Ai\Agents\MultipleChoiceDraftAgent;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\MultipleChoice\McImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Prompts\AgentPrompt;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    Role::findOrCreate('student', 'web');
    $this->student = User::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $year->id,
        'email' => 'student@example.test', 'first_name' => 'Jörg', 'last_name' => 'Müller',
        'schoolclass' => '7AM', 'is_active' => true,
    ])->assignRole('student');
});

test('roster reads every quoted unicode row and matches only existing school students', function () {
    $csv = "\xEF\xBB\xBF\"Vorname\",\"Nachname\",\"Geschlecht\",\"E-Mail\",\"Kurs\",\"Klasse\"\r\n\"Jörg\",\"Müller\",\"m\",\" STUDENT@EXAMPLE.TEST \",\"INF, Abend\",\"7AM\"\r\n\"Unbekannt\",\"Person\",\"\",\"unknown@example.test\",\"INF\",\"7AM\"";
    $preview = app(McImportService::class)->rosterPreview($csv, $this->school->id);
    expect($preview['rows'])->toHaveCount(2)
        ->and($preview['rows'][0]['user_id'])->toBe($this->student->id)
        ->and($preview['rows'][0]['status'])->toBe('matched')
        ->and($preview['rows'][0]['course'])->toBe('INF, Abend')
        ->and($preview['rows'][1]['user_id'])->toBeNull()
        ->and($preview['rows'][1]['status'])->toBe('error')
        ->and($preview['valid'])->toBeFalse();
});

test('roster accepts semicolon and quoted embedded newlines', function () {
    $preview = app(McImportService::class)->rosterPreview("Vorname;Nachname;E-Mail;Kurs;Klasse\nJörg;Müller;student@example.test;\"INF\nAbend\";7AM", $this->school->id);
    expect($preview['valid'])->toBeTrue()->and($preview['rows'][0]['course'])->toBe("INF\nAbend");
});

test('roster preserves the existing student login rule for active legacy accounts', function () {
    $this->student->forceFill(['is_active' => null])->save();
    $preview = app(McImportService::class)->rosterPreview("Vorname;Nachname;E-Mail;Klasse\nJörg;Müller;student@example.test;7AM", $this->school->id);

    expect($preview['valid'])->toBeTrue()->and($preview['rows'][0]['user_id'])->toBe($this->student->id);
});

test('roster marks both duplicate identities and rejects mismatched names classes malformed rows', function () {
    $csv = "Vorname,Nachname,E-Mail,Klasse\nJörg,Müller,student@example.test,7AM\nJörg,Müller,STUDENT@example.test,7AM\nAndere,Müller,student@example.test,7BM\nJörg,Müller,invalid,7AM,extra";
    $preview = app(McImportService::class)->rosterPreview($csv, $this->school->id);
    expect($preview['valid'])->toBeFalse()->and($preview['rows'])->toHaveCount(4);
    foreach ($preview['rows'] as $row) {
        expect($row['status'])->toBe('error')->and($row['user_id'])->toBeNull()->and($row['errors'])->not->toBeEmpty();
    }
    expect(implode(' ', $preview['rows'][2]['errors']))->toContain('Vorname', 'Klasse');
});

test('roster does not reveal foreign school inactive or non student accounts', function () {
    $csv = "Vorname,Nachname,E-Mail,Klasse\nJörg,Müller,student@example.test,7AM";
    $service = app(McImportService::class);
    expect($service->rosterPreview($csv, $this->school->id + 1000)['rows'][0]['user_id'])->toBeNull();
    $this->student->forceFill(['is_active' => false])->save();
    expect($service->rosterPreview($csv, $this->school->id)['valid'])->toBeFalse();
    $this->student->forceFill(['is_active' => true])->save();
    $this->student->removeRole('student');
    expect($service->rosterPreview($csv, $this->school->id)['valid'])->toBeFalse();
    Role::query()->where('name', 'student')->where('guard_name', 'web')->delete();
    expect($service->rosterPreview($csv, $this->school->id)['valid'])->toBeFalse();
});

test('roster rejects missing duplicated headers and empty files', function (string $csv) {
    $preview = app(McImportService::class)->rosterPreview($csv, $this->school->id);
    expect($preview['valid'])->toBeFalse()->and($preview['errors'])->not->toBeEmpty();
})->with(['Vorname,Nachname,E-Mail', 'Vorname,Nachname,E-Mail,Klasse,Klasse', "Vorname,Nachname,E-Mail,Klasse\n"]);

test('ai generation uses the supplied source and returns an explicitly unreviewed editable draft', function () {
    config(['ai.default' => 'openai', 'ai.providers.openai.key' => 'fake-test-key']);
    MultipleChoiceDraftAgent::fake([[
        'questions' => [['id' => 'q1', 'prompt' => 'Was ist E-Mail?', 'type' => 'single', 'options' => [['id' => 'a', 'text' => 'Elektronische Post'], ['id' => 'b', 'text' => 'Papierpost']], 'correct_option_ids' => ['a'], 'reviewed' => true]],
        'warnings' => ['Bitte fachlich prüfen.'],
    ]])->preventStrayPrompts();
    $source = 'Was ist E-Mail? Musterantwort: Elektronische Post.';
    $draft = app(McImportService::class)->generateDraft($source);
    expect($draft['valid'])->toBeTrue()->and($draft['source'])->toBe('ai')
        ->and($draft['questions'][0]['reviewed'])->toBeFalse()
        ->and($draft['questions'][0]['active'])->toBeTrue()
        ->and($draft['warnings'])->toContain('Bitte fachlich prüfen.');
    MultipleChoiceDraftAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains($source));
});

test('ai generation correctly refuses missing provider configuration without prompting', function () {
    config(['ai.default' => 'openai', 'ai.providers.openai.key' => '']);
    MultipleChoiceDraftAgent::fake()->preventStrayPrompts();
    expect(fn () => app(McImportService::class)->generateDraft('Frage und Musterantwort'))->toThrow(ValidationException::class);
    MultipleChoiceDraftAgent::assertNeverPrompted();
});

test('ai failures and malformed provider output cannot masquerade as a complete catalog', function () {
    config(['ai.default' => 'openai', 'ai.providers.openai.key' => 'fake-test-key']);
    MultipleChoiceDraftAgent::fake([['questions' => [['id' => 'q1']], 'warnings' => []]])->preventStrayPrompts();
    $preview = app(McImportService::class)->generateDraft('Quelle');
    expect($preview['valid'])->toBeFalse()->and($preview['errors'])->not->toBeEmpty();
    MultipleChoiceDraftAgent::fake(fn () => throw new RuntimeException('Provider unavailable'))->preventStrayPrompts();
    expect(fn () => app(McImportService::class)->generateDraft('Quelle'))->toThrow(ValidationException::class);
});

test('non utf8 and empty generation sources return actionable validation errors', function () {
    $service = app(McImportService::class);
    expect(fn () => $service->preview("\xFF"))->toThrow(ValidationException::class)
        ->and(fn () => $service->rosterPreview("\xFF", $this->school->id))->toThrow(ValidationException::class)
        ->and(fn () => $service->generateDraft(' '))->toThrow(ValidationException::class);
});
