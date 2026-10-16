<?php

use App\Services\NumberSystems\NumberSystemEngine;

test('all six numeric routes preserve boundary values and friendly equivalents', function () {
    $engine = new NumberSystemEngine;
    foreach ([0, 10, 11, 12, 13, 14, 15, 16, 53, 127, 128, 154, 173, 255, 256, 260, 527, 4095] as $value) {
        foreach ([2, 10, 16] as $source) {
            foreach (array_diff([2, 10, 16], [$source]) as $target) {
                $read = $engine->parse($engine->notation($value, $source), $source);
                expect($read)->toBe($value);
                expect($engine->parse($engine->notation($read, $target), $target))->toBe($value);
            }
        }
    }
    expect($engine->parse('  #00af  ', 16))->toBe(175)
        ->and($engine->parse('0xA7', 16))->toBe(167)
        ->and($engine->parse('0000 1010 (dual)', 2))->toBe(10)
        ->and($engine->parse('00015', 10))->toBe(15)
        ->and($engine->parse('1111', 2, 8))->toBeNull()
        ->and($engine->parse('00001111', 2, 8))->toBe(15);
});

test('invalid digits signs overflow and misplaced prefixes never silently convert', function (string $value, int $base) {
    expect((new NumberSystemEngine)->parse($value, $base))->toBeNull();
})->with([['102', 2], ['#G1', 16], ['-1', 10], ['1.5', 10], ['#2A', 10], ['', 2], ['#', 16], ['4096', 10], ['1e2', 10], ['0x1', 2]]);

function nsCanonicalDraft(array $mission, NumberSystemEngine $engine): array
{
    $draft = [];
    foreach ($mission['solution'] as $key => $spec) {
        $draft[$key] = match ($spec['type']) {
            'number' => $engine->notation($spec['value'], $spec['base'], $spec['width'] ?? null),
            'bytes' => implode(' ', array_map(fn (int $value): string => $engine->notation($value, $spec['base'], $spec['base'] === 2 ? 8 : 2), $spec['value'])),
            default => $spec['value'],
        };
        if ($spec['type'] === 'number' && $spec['base'] === 2) {
            $draft[$key] = str_replace(' (dual)', '', $draft[$key]);
        }
        if ($spec['type'] === 'bytes' && $spec['base'] === 2) {
            $draft[$key] = str_replace(' (dual)', '', $draft[$key]);
        }
    }

    return $draft;
}

test('generated adventures are reproducible solvable varied and cover every direction at every length and level', function () {
    $engine = new NumberSystemEngine;
    $signatures = [];
    foreach (range(1, 12) as $seed) {
        foreach ([30, 40, 60, 75] as $minutes) {
            foreach ([1, 2, 3] as $difficulty) {
                $directions = [];
                foreach (range(0, $engine->count($minutes) - 1) as $position) {
                    $mission = $engine->mission((string) $seed, 42, $position, $minutes, $difficulty);
                    expect($mission)->toBe($engine->mission((string) $seed, 42, $position, $minutes, $difficulty));
                    expect($engine->check($mission, nsCanonicalDraft($mission, $engine))['correct'])->toBeTrue();
                    expect($engine->publicMission($mission))->not->toHaveKeys(['solution', 'hints', 'explanation', 'private_clues']);
                    expect($mission['hints'])->toHaveCount(3);
                    if ($mission['kind'] === 'repair') {
                        $different = array_filter($mission['fields'], fn (array $field): bool => isset($field['initial']) && $engine->parse($field['initial'], $field['base']) !== (int) $mission['reference']);
                        expect($different)->toHaveCount(1);
                    }
                    if ($mission['kind'] === 'vault') {
                        expect($mission['private_clues'])->toHaveCount(4);
                    }
                    if (($mission['solution']['answer']['type'] ?? '') === 'bytes') {
                        $bytes = implode(' ', array_map(fn (int $value): string => $mission['solution']['answer']['base'] === 2 ? $engine->digits($value, 2, 8) : $engine->notation($value, 16, 2), $mission['solution']['answer']['value']));
                        expect($mission['hints'][2])->toContain($bytes);
                        expect($engine->bytes($bytes, $mission['solution']['answer']['base']))->toBe($mission['solution']['answer']['value']);
                    }
                    $directions = array_merge($directions, $mission['directions']);
                    if ($position === 0 && $minutes === 30 && $difficulty === 2) {
                        $signatures[] = json_encode([$mission['prompt'], $mission['fields'], $mission['solution']]);
                    }
                    if ($position === $engine->count($minutes) - 1) {
                        expect($mission['finale'])->toBeTrue();
                    }
                }
                expect(array_diff(NumberSystemEngine::DIRECTIONS, $directions))->toBe([]);
            }
        }
    }
    expect(array_unique($signatures))->toHaveCount(12);
});

test('a correct number cannot substitute for a wrong structured working step', function () {
    $engine = new NumberSystemEngine;
    foreach (range(0, 5) as $position) {
        $mission = $engine->mission('steps', 42, $position, 30, 2);
        if (isset($mission['solution']['quotient'])) {
            $draft = nsCanonicalDraft($mission, $engine);
            $draft['quotient'] = '999';
            expect($engine->check($mission, $draft))->toMatchArray(['correct' => false, 'result_correct' => true, 'step_correct' => false]);
        }
    }
});

test('ASCII bytes distinguish encoding from number bases and enforce seven bit values in eight bit fields', function () {
    $engine = new NumberSystemEngine;
    expect($engine->bytes('01010100 01000101 01000001 01001101', 2))->toBe([84, 69, 65, 77]);
    expect($engine->bytes('#54 #45 #41 #4d', 16))->toBe([84, 69, 65, 77]);
    expect($engine->bytes('1000001', 2))->toBeNull();
    expect($engine->bytes('#FF', 16))->toBeNull();
});
