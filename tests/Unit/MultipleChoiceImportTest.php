<?php

use App\Services\MultipleChoice\McImportService;
use App\Services\MultipleChoice\McQuestionValidator;

function mcImportFixtureQuestion(array $changes = []): array
{
    return array_replace([
        'id' => 'q1', 'prompt' => 'Welche Antwort stimmt?', 'type' => 'single',
        'options' => [['id' => 'a', 'text' => 'Richtig'], ['id' => 'b', 'text' => 'Falsch']],
        'correct_option_ids' => ['a'], 'active' => true, 'reviewed' => true,
    ], $changes);
}

test('complete markdown catalogs preserve questions choices solutions and unicode without ai', function () {
    $text = "\xEF\xBB\xBF---\ntitle: Katalog\n---\n# Fragen\nHinweise\n### Frage 1: Wofür steht Österreich?\n\n- [ ] A) Falsch\n- [x] B) Richtig\n\n### Frage 2: Welche Tätigkeiten?\n\n**Mehrfachantworten: Wähle alle richtigen Antworten.**\n\n- [X] A) Lesen\n- [x] B) Schreiben\n- [ ] C) Löschen\n";
    $result = (new McImportService(new McQuestionValidator))->preview(str_replace("\n", "\r\n", $text));

    expect($result['valid'])->toBeTrue()
        ->and($result['source'])->toBe('direct')
        ->and($result['questions'])->toHaveCount(2)
        ->and($result['questions'][0]['prompt'])->toBe('Wofür steht Österreich?')
        ->and($result['questions'][0]['options'][1]['text'])->toBe('B) Richtig')
        ->and($result['questions'][0]['correct_option_ids'])->toBe(['o2'])
        ->and($result['questions'][0]['type'])->toBe('single')
        ->and($result['questions'][1]['type'])->toBe('multiple')
        ->and($result['questions'][1]['correct_option_ids'])->toBe(['o1', 'o2'])
        ->and($result['questions'][1]['reviewed'])->toBeFalse();
});

test('structured json previews preserve question identities but require a fresh review', function () {
    $question = mcImportFixtureQuestion();
    $service = new McImportService(new McQuestionValidator);
    foreach ([[$question], ['questions' => [$question]]] as $payload) {
        $preview = $service->preview(json_encode($payload), 'json');
        expect($preview['valid'])->toBeTrue()
            ->and($preview['questions'][0])->toBe(array_replace($question, ['reviewed' => false]));
    }
});

test('catalog preview reports malformed and incomplete sources', function (string $text, string $format) {
    $preview = (new McImportService(new McQuestionValidator))->preview($text, $format);
    expect($preview['valid'])->toBeFalse()->and($preview['errors'])->not->toBeEmpty();
})->with([
    'invalid json' => ['{broken', 'json'],
    'json scalar' => ['null', 'json'],
    'json object' => ['{"questions":{"first":{}}}', 'json'],
    'no questions' => ['# Nur Musterantworten', 'markdown'],
    'missing solution' => ["### Frage 1: Frage?\n- [ ] A\n- [ ] B", 'markdown'],
    'unsupported' => ['text', 'csv'],
]);

test('question validation rejects ambiguous invalid and incomplete answer keys', function (array $changes, string $errorKey) {
    $errors = (new McQuestionValidator)->errors([mcImportFixtureQuestion($changes)]);
    expect($errors)->toHaveKey('questions.0.'.$errorKey);
})->with([
    'empty prompt' => [['prompt' => ' '], 'prompt'],
    'invalid type' => [['type' => 'free'], 'type'],
    'single with multiple solutions' => [['correct_option_ids' => ['a', 'b']], 'correct_option_ids'],
    'multiple with only one solution' => [['type' => 'multiple'], 'correct_option_ids'],
    'no solution' => [['correct_option_ids' => []], 'correct_option_ids'],
    'unknown solution' => [['correct_option_ids' => ['c']], 'correct_option_ids'],
    'duplicate solution' => [['correct_option_ids' => ['a', 'a']], 'correct_option_ids'],
    'too few options' => [['options' => [['id' => 'a', 'text' => 'A']]], 'options'],
    'duplicate option id' => [['options' => [['id' => 'a', 'text' => 'A'], ['id' => 'a', 'text' => 'B']]], 'options.1.id'],
    'duplicate option text' => [['options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => ' a ']]], 'options.1.text'],
    'invalid boolean' => [['active' => 'false'], 'active'],
]);

test('publication checks review status and ignores archived invalid questions', function () {
    $validator = new McQuestionValidator;
    $draft = mcImportFixtureQuestion(['reviewed' => false]);
    expect($validator->errors([$draft]))->toBe([])
        ->and($validator->errors([$draft], true))->toHaveKey('questions.0.reviewed')
        ->and($validator->errors([mcImportFixtureQuestion(), ['active' => false]], true))->toBe([])
        ->and($validator->errors([mcImportFixtureQuestion(), mcImportFixtureQuestion()]))->toHaveKey('questions.1.id');
});

test('malformed nested question values produce validation errors without crashing', function () {
    $errors = (new McQuestionValidator)->errors([null, mcImportFixtureQuestion([
        'options' => ['invalid', ['id' => [], 'text' => []]], 'correct_option_ids' => [[]],
    ])]);
    expect($errors)->toHaveKey('questions.0')->toHaveKey('questions.1.correct_option_ids');
});
