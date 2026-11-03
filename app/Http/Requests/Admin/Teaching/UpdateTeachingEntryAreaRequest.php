<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryArea;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeachingEntryAreaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::of((string) $this->input('name'))->trim()->toString()]);
        if (is_array($this->input('grading_group_weights.weights'))) {
            $configuration = $this->input('grading_group_weights');
            $configuration['weights'] = array_map(function (mixed $item): mixed {
                if (is_array($item) && is_string($item['weight'] ?? null)) {
                    $item['weight'] = str_replace(',', '.', trim($item['weight']));
                }

                return $item;
            }, $configuration['weights']);
            $this->merge(['grading_group_weights' => $configuration]);
        }
        if (is_array($this->input('grading_level_weights'))) {
            $this->merge(['grading_level_weights' => array_map(function (mixed $item): mixed {
                if (is_array($item) && is_string($item['weight'] ?? null)) {
                    $item['weight'] = str_replace(',', '.', trim($item['weight']));
                }

                return $item;
            }, $this->input('grading_level_weights'))]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $area = $this->route('entryArea');

        return $user !== null
            && $user->schoolyear_id !== null
            && $user->hasAnyRole(['admin', 'teaching_admin', 'teacher'])
            && $area instanceof TeachingEntryArea
            && $area->user_id === $user->id
            && $area->school_id === $user->school_id
            && $area->schoolyear_id === $user->schoolyear_id;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('teaching_entry_areas', 'name')
                    ->where(fn ($query) => $query
                        ->where('user_id', Auth::id())
                        ->where('schoolyear_id', Auth::user()?->schoolyear_id))
                    ->ignore($this->route('entryArea')),
            ],
            'semester_count' => ['sometimes', 'required', 'integer', 'in:1,2'],
            'semester_1_weight' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'semester_2_weight' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'grading_part_groups' => ['sometimes', 'array', 'list', 'max:100'],
            'grading_part_groups.*' => ['required', 'array:id,name,part_ids,parent_group_id'],
            'grading_part_groups.*.id' => ['required', 'uuid', 'distinct'],
            'grading_part_groups.*.name' => ['required', 'string', 'max:100'],
            'grading_part_groups.*.parent_group_id' => ['sometimes', 'nullable', 'uuid'],
            'grading_part_groups.*.part_ids' => ['present', 'array', 'list', 'max:1000'],
            'grading_part_groups.*.part_ids.*' => ['required', 'integer', 'distinct',
                Rule::exists('teaching_entry_grading_parts', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id)
                    ->where('school_id', $this->user()?->school_id)
                    ->where('schoolyear_id', $this->user()?->schoolyear_id)
                    ->where('teaching_entry_area_id', $this->route('entryArea')?->id)),
            ],
            'grading_group_weights' => ['sometimes', 'required', 'array:group_id,weights'],
            'grading_group_weights.group_id' => ['required_with:grading_group_weights', 'uuid'],
            'grading_group_weights.weights' => ['present_with:grading_group_weights', 'nullable', 'array', 'list', 'min:1', 'max:1000'],
            'grading_group_weights.weights.*' => ['required', 'array:part_id,group_id,weight', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && isset($value['group_id']) === isset($value['part_id'])) {
                    $fail('Jede Gewichtung muss genau einem direkten Kind zugeordnet sein.');
                }
            }],
            'grading_group_weights.weights.*.part_id' => ['sometimes', 'required', 'integer', 'distinct'],
            'grading_group_weights.weights.*.group_id' => ['sometimes', 'required', 'uuid', 'distinct'],
            'grading_group_weights.weights.*.weight' => ['required', 'numeric', 'gt:0', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
                    $fail('Bitte eine endliche positive Gewichtung eingeben.');
                }
            }],
            'grading_level_weights' => ['sometimes', 'nullable', 'array', 'list', 'min:1', 'max:1000'],
            'grading_level_weights.*' => ['required', 'array:group_id,part_id,weight', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && isset($value['group_id']) === isset($value['part_id'])) {
                    $fail('Jede Gewichtung muss genau einer Gruppe oder einem ungruppierten Benotungsteil zugeordnet sein.');
                }
            }],
            'grading_level_weights.*.group_id' => ['sometimes', 'required', 'uuid'],
            'grading_level_weights.*.part_id' => ['sometimes', 'required', 'integer'],
            'grading_level_weights.*.weight' => ['required', 'numeric', 'gt:0', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
                    $fail('Bitte eine endliche positive Gewichtung eingeben.');
                }
            }],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $area = $this->route('entryArea');
                $groups = collect($this->input('grading_part_groups', []))->keyBy('id');
                foreach ($groups as $group) {
                    $childIds = $groups->filter(fn (array $item): bool => ($item['parent_group_id'] ?? null) === $group['id'])->keys()->all();
                    $children = [...array_map(fn (mixed $id): string => 'part:'.(int) $id, $group['part_ids']), ...array_map(fn (string $id): string => 'group:'.$id, $childIds)];
                    if (count(array_unique($children)) < 2) {
                        $saved = collect($area->grading_part_groups ?? [])->firstWhere('id', $group['id']);
                        $oldChildren = $saved === null ? [] : [
                            ...$area->gradingParts()->where('grading_group_id', $group['id'])->pluck('id')->map(fn (int $id): string => 'part:'.$id)->all(),
                            ...collect($area->grading_part_groups ?? [])->filter(fn (array $item): bool => ($item['parent_group_id'] ?? null) === $group['id'])->pluck('id')->map(fn (string $id): string => 'group:'.$id)->all(),
                        ];
                        sort($children);
                        sort($oldChildren);
                        if ($saved === null || $children !== $oldChildren) {
                            $validator->errors()->add('grading_part_groups', 'Eine Gruppe braucht mindestens zwei direkte Bausteine.');

                            return;
                        }
                    }
                    $visited = [$group['id']];
                    $parentId = $group['parent_group_id'] ?? null;
                    while ($parentId !== null) {
                        if (! $groups->has($parentId) || in_array($parentId, $visited, true)) {
                            $validator->errors()->add('grading_part_groups', 'Gruppen müssen im selben Bereich bleiben und dürfen keine Zyklen oder Selbstzuordnung bilden.');

                            return;
                        }
                        $visited[] = $parentId;
                        $parentId = $groups[$parentId]['parent_group_id'] ?? null;
                    }
                }
                $semesterCount = (int) $this->input('semester_count', $area->semester_count);
                $firstWeight = (int) $this->input('semester_1_weight', $area->semester_1_weight);
                $secondWeight = (int) $this->input('semester_2_weight', $area->semester_2_weight);

                if ($semesterCount === 2 && $firstWeight + $secondWeight !== 100) {
                    $validator->errors()->add('semester_2_weight', 'Die Gewichtungen der beiden Semester müssen zusammen 100 % ergeben.');
                }
            },
        ];
    }
}
