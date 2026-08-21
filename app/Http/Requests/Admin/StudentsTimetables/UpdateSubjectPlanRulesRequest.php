<?php

namespace App\Http\Requests\Admin\StudentsTimetables;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSubjectPlanRulesRequest extends FormRequest
{
    /** @var array<string, list<string>> */
    private const OPTION_VALUES = [
        'branch' => ['wirtschaftskundlich', 'gymnasial'],
        'language' => ['L', 'F', 'S'],
        'arts_subject' => ['ME', 'BE'],
        'religion' => ['ETH', 'Rev', 'Ris', 'Rk', 'Ror'],
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'rules' => ['present', 'array', 'max:30'],
            'rules.*' => ['array:stable_key,name,label,selection_key,selection_mode,min_selections,max_selections,conditions,is_active,options'],
            'rules.*.stable_key' => ['required', 'uuid'],
            'rules.*.name' => ['required', 'string', 'max:120'],
            'rules.*.label' => ['required', 'string', 'max:120'],
            'rules.*.selection_key' => ['required', Rule::in(['branch', 'language', 'arts_subject', 'religion'])],
            'rules.*.selection_mode' => ['required', Rule::in(['single'])],
            'rules.*.min_selections' => ['required', 'integer', Rule::in([1])],
            'rules.*.max_selections' => ['required', 'integer', Rule::in([1])],
            'rules.*.conditions' => ['present', 'array', 'max:20'],
            'rules.*.conditions.*' => ['array:field,operator,value'],
            'rules.*.conditions.*.field' => ['required', Rule::in(['branch', 'language', 'arts_subject', 'religion', 'student_religion'])],
            'rules.*.conditions.*.operator' => ['required', Rule::in(['equals', 'not_equals', 'in', 'not_in'])],
            'rules.*.conditions.*.value' => ['required'],
            'rules.*.is_active' => ['required', 'boolean'],
            'rules.*.options' => ['present', 'array', 'min:1', 'max:30'],
            'rules.*.options.*' => ['array:stable_key,value,label,course_code_prefix,subject_keys'],
            'rules.*.options.*.stable_key' => ['required', 'uuid'],
            'rules.*.options.*.value' => ['required', 'string', 'max:80'],
            'rules.*.options.*.label' => ['required', 'string', 'max:120'],
            'rules.*.options.*.course_code_prefix' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-zÄÖÜäöüß0-9_\/-]+$/u'],
            'rules.*.options.*.subject_keys' => ['present', 'array', 'min:1', 'max:250'],
            'rules.*.options.*.subject_keys.*' => ['required', 'uuid'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rules = collect($this->input('rules', []));

            if ($rules->pluck('stable_key')->duplicates()->isNotEmpty()) {
                $validator->errors()->add('rules', 'Regel-Schlüssel dürfen nicht doppelt vorkommen.');
            }

            $rules->each(function (array $rule, int $ruleIndex) use ($validator): void {
                $options = collect($rule['options'] ?? []);
                $minSelections = (int) ($rule['min_selections'] ?? 0);
                $maxSelections = (int) ($rule['max_selections'] ?? 0);
                $selectionKey = (string) ($rule['selection_key'] ?? '');

                if ($minSelections > $maxSelections || $maxSelections > $options->count()) {
                    $validator->errors()->add(
                        "rules.{$ruleIndex}.max_selections",
                        'Die Auswahlgrenzen passen nicht zur Anzahl der Optionen.',
                    );
                }

                if ($options->pluck('stable_key')->duplicates()->isNotEmpty()) {
                    $validator->errors()->add("rules.{$ruleIndex}.options", 'Option-Schlüssel dürfen nicht doppelt vorkommen.');
                }

                if ($options->pluck('value')->duplicates()->isNotEmpty()) {
                    $validator->errors()->add("rules.{$ruleIndex}.options", 'Optionswerte dürfen nicht doppelt vorkommen.');
                }

                $invalidOptionValues = $options
                    ->pluck('value')
                    ->diff(self::OPTION_VALUES[$selectionKey] ?? []);

                if ($invalidOptionValues->isNotEmpty()) {
                    $validator->errors()->add(
                        "rules.{$ruleIndex}.options",
                        'Die Optionswerte müssen zur gewählten Auswahldimension passen.',
                    );
                }

                $options->each(function (array $option, int $optionIndex) use ($ruleIndex, $validator): void {
                    if (collect($option['subject_keys'] ?? [])->duplicates()->isNotEmpty()) {
                        $validator->errors()->add(
                            "rules.{$ruleIndex}.options.{$optionIndex}.subject_keys",
                            'Ein Fach darf innerhalb einer Option nur einmal vorkommen.',
                        );
                    }
                });

                collect($rule['conditions'] ?? [])->each(function (array $condition, int $conditionIndex) use ($ruleIndex, $validator): void {
                    $value = $condition['value'] ?? null;
                    $validValue = is_string($value)
                        || (is_array($value) && collect($value)->every(fn (mixed $item): bool => is_string($item)));

                    if (! $validValue) {
                        $validator->errors()->add(
                            "rules.{$ruleIndex}.conditions.{$conditionIndex}.value",
                            'Bedingungswerte müssen Text oder eine Liste von Textwerten sein.',
                        );

                        return;
                    }

                    $values = is_array($value) ? $value : [$value];

                    if (count($values) > 30 || collect($values)->contains(
                        fn (string $conditionValue): bool => mb_strlen($conditionValue) > 80,
                    )) {
                        $validator->errors()->add(
                            "rules.{$ruleIndex}.conditions.{$conditionIndex}.value",
                            'Bedingungen dürfen höchstens 30 Textwerte mit je 80 Zeichen enthalten.',
                        );
                    }
                });
            });
        }];
    }
}
