<?php

use App\Models\StudentTimetableSubjectRow;
use App\Services\StudentsTimetables\StudentTimetableSubjectRuleService;
use App\Services\StudentsTimetables\SubjectPlanRuleEvaluator;
use Illuminate\Support\Str;

function subjectPlanRule(string $key, string $selectionKey, array $options, array $conditions = []): array
{
    return [
        'stable_key' => $key,
        'name' => $selectionKey,
        'label' => $selectionKey,
        'selection_key' => $selectionKey,
        'selection_mode' => 'single',
        'min_selections' => 1,
        'max_selections' => 1,
        'conditions' => $conditions,
        'is_active' => true,
        'options' => $options,
    ];
}

function subjectPlanRuleOption(string $key, string $value, array $subjectKeys, ?string $prefix = null): array
{
    return [
        'stable_key' => $key,
        'value' => $value,
        'label' => $value,
        'course_code_prefix' => $prefix,
        'subject_keys' => $subjectKeys,
    ];
}

test('it composes controlling rules and keeps unruled subjects eligible', function () {
    $evaluator = new SubjectPlanRuleEvaluator([
        subjectPlanRule('branch-rule', 'branch', [
            subjectPlanRuleOption('branch-wiku', 'wirtschaftskundlich', ['inf']),
            subjectPlanRuleOption('branch-gym', 'gymnasial', ['spanish-six', 'spanish-seven']),
        ]),
        subjectPlanRule('language-rule', 'language', [
            subjectPlanRuleOption('language-l', 'L', ['latin-six', 'latin-seven']),
            subjectPlanRuleOption('language-f', 'F', ['french-six', 'french-seven']),
            subjectPlanRuleOption('language-s', 'S', ['spanish-six', 'spanish-seven']),
        ]),
    ]);

    expect($evaluator->evaluate(['stable_key' => 'unruled', 'json_code' => 'D7'], []))
        ->toMatchArray(['eligible' => true])
        ->and($evaluator->evaluate(['stable_key' => 'inf', 'json_code' => 'INF2'], [
            'branch' => 'wirtschaftskundlich',
            'language' => 'S',
        ]))
        ->toMatchArray(['eligible' => true])
        ->and($evaluator->evaluate(['stable_key' => 'spanish-six', 'json_code' => 'ALPHA6'], [
            'branch' => 'gymnasial',
            'language' => 'S',
        ]))
        ->toMatchArray(['eligible' => true])
        ->and($evaluator->evaluate(['stable_key' => 'spanish-seven', 'json_code' => 'ALPHA7'], [
            'branch' => 'wirtschaftskundlich',
            'language' => 'S',
        ]))
        ->toMatchArray(['eligible' => false])
        ->and($evaluator->evaluate(['stable_key' => 'latin-six', 'json_code' => 'BETA6'], [
            'branch' => 'gymnasial',
            'language' => null,
        ]))
        ->toMatchArray(['eligible' => false]);
});

test('one selected option grants every bundle member', function () {
    $evaluator = new SubjectPlanRuleEvaluator([
        subjectPlanRule('language-rule', 'language', [
            subjectPlanRuleOption('language-l', 'L', ['l6', 'l7']),
            subjectPlanRuleOption('language-f', 'F', ['f6', 'f7']),
        ]),
    ]);

    expect($evaluator->evaluate(['stable_key' => 'l6', 'json_code' => 'L6'], ['language' => 'L'])['eligible'])
        ->toBeTrue()
        ->and($evaluator->evaluate(['stable_key' => 'l7', 'json_code' => 'L7'], ['language' => 'L'])['eligible'])
        ->toBeTrue()
        ->and($evaluator->evaluate(['stable_key' => 'f6', 'json_code' => 'F6'], ['language' => 'L'])['eligible'])
        ->toBeFalse();
});

test('it resolves generic course codes and evaluates allowlisted conditions', function () {
    $evaluator = new SubjectPlanRuleEvaluator([
        subjectPlanRule('religion-rule', 'religion', [
            subjectPlanRuleOption('religion-eth', 'ETH', ['religion'], 'ETH'),
            subjectPlanRuleOption('religion-rk', 'Rk', ['religion'], 'Rk'),
        ], [[
            'field' => 'branch',
            'operator' => 'in',
            'value' => ['gymnasial', 'wirtschaftskundlich'],
        ]]),
    ]);
    $subject = ['stable_key' => 'religion', 'json_code' => 'R/ET1'];

    expect($evaluator->evaluate($subject, ['religion' => 'ETH', 'branch' => 'gymnasial']))
        ->toMatchArray(['eligible' => true, 'resolved_codes' => ['ETH1']])
        ->and($evaluator->evaluate($subject, ['religion' => 'Rk', 'branch' => 'gymnasial']))
        ->toMatchArray(['eligible' => true, 'resolved_codes' => ['Rk1']])
        ->and($evaluator->evaluate($subject, ['religion' => 'Rk', 'branch' => 'unknown']))
        ->toMatchArray(['eligible' => false]);
});

test('default compact language rules store modules six and seven as one option bundle', function () {
    $rows = collect(['L6', 'L7', 'F6', 'F7', 'S6', 'S7'])
        ->map(fn (string $code): StudentTimetableSubjectRow => new StudentTimetableSubjectRow([
            'stable_key' => (string) Str::uuid(),
            'semester' => 5,
            'branch' => 'gymnasial',
            'json_code' => $code,
            'json_subject' => mb_substr($code, 0, 1),
            'name' => $code,
            'is_active' => true,
        ]));
    $rules = (new StudentTimetableSubjectRuleService)->defaultRules($rows);
    $languageRule = collect($rules)->firstWhere('selection_key', 'language');

    expect($languageRule)->not->toBeNull()
        ->and(collect($languageRule['options'])->mapWithKeys(fn (array $option): array => [
            $option['value'] => collect($option['subject_keys'])
                ->map(fn (string $key): string => (string) $rows->firstWhere('stable_key', $key)?->json_code)
                ->values()
                ->all(),
        ])->all())->toBe([
            'L' => ['L6', 'L7'],
            'F' => ['F6', 'F7'],
            'S' => ['S6', 'S7'],
        ]);
});

test('default arts rules keep gym module one compulsory and select only wiku module one or gym module two', function () {
    $rows = collect([
        ['branch' => 'wirtschaftskundlich', 'code' => 'BE1'],
        ['branch' => 'wirtschaftskundlich', 'code' => 'ME1'],
        ['branch' => 'gymnasial', 'code' => 'BE1'],
        ['branch' => 'gymnasial', 'code' => 'ME1'],
        ['branch' => 'gymnasial', 'code' => 'BE2'],
        ['branch' => 'gymnasial', 'code' => 'ME2'],
    ])->map(fn (array $subject): StudentTimetableSubjectRow => new StudentTimetableSubjectRow([
        'stable_key' => (string) Str::uuid(),
        'semester' => str_ends_with($subject['code'], '1') ? 7 : 8,
        'branch' => $subject['branch'],
        'json_code' => $subject['code'],
        'json_subject' => preg_replace('/\d+$/u', '', $subject['code']),
        'name' => $subject['code'],
        'is_active' => true,
    ]));
    $rowsByBranchAndCode = $rows->keyBy(fn (StudentTimetableSubjectRow $row): string => "{$row->branch}:{$row->json_code}");
    $rules = (new StudentTimetableSubjectRuleService)->defaultRules($rows);
    $artsRule = collect($rules)->firstWhere('selection_key', 'arts_subject');
    $evaluator = new SubjectPlanRuleEvaluator($rules);

    expect(collect($artsRule['options'])->mapWithKeys(fn (array $option): array => [
        $option['value'] => collect($option['subject_keys'])
            ->map(function (string $subjectKey) use ($rows): string {
                $row = $rows->firstWhere('stable_key', $subjectKey);

                return "{$row->branch}:{$row->json_code}";
            })
            ->values()
            ->all(),
    ])->all())->toBe([
        'ME' => ['wirtschaftskundlich:ME1', 'gymnasial:ME2'],
        'BE' => ['wirtschaftskundlich:BE1', 'gymnasial:BE2'],
    ])->and($evaluator->evaluate(
        $rowsByBranchAndCode['gymnasial:BE1'],
        ['branch' => 'gymnasial', 'arts_subject' => 'ME'],
    )['eligible'])->toBeTrue()
        ->and($evaluator->evaluate(
            $rowsByBranchAndCode['gymnasial:ME1'],
            ['branch' => 'gymnasial', 'arts_subject' => 'BE'],
        )['eligible'])->toBeTrue()
        ->and($evaluator->evaluate(
            $rowsByBranchAndCode['gymnasial:BE2'],
            ['branch' => 'gymnasial', 'arts_subject' => 'ME'],
        )['eligible'])->toBeFalse()
        ->and($evaluator->evaluate(
            $rowsByBranchAndCode['wirtschaftskundlich:BE1'],
            ['branch' => 'wirtschaftskundlich', 'arts_subject' => 'ME'],
        )['eligible'])->toBeFalse()
        ->and($evaluator->evaluate(
            $rowsByBranchAndCode['wirtschaftskundlich:ME1'],
            ['branch' => 'wirtschaftskundlich', 'arts_subject' => 'ME'],
        )['eligible'])->toBeTrue();
});
