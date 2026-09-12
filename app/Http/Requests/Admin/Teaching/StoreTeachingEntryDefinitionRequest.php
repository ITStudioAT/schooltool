<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingEntryDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTeachingEntryDefinitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('property_evaluations')) {
            $this->merge(['property_evaluations' => UpdateTeachingEntryCalculationSettingsRequest::normalizeEvaluations($this->input('property_evaluations'))]);
        }

        $fixedProperties = collect($this->input('fixed_properties', []))
            ->map(fn (mixed $property): string => trim((string) $property))
            ->all();
        $notificationRecipients = $this->input('notification_recipients', []);

        if (is_array($notificationRecipients)) {
            $notificationRecipients = collect($notificationRecipients)
                ->map(fn (mixed $recipient): string => trim((string) $recipient))
                ->all();
        }

        $tableMarkingColor = $this->input('table_marking_color');

        if (is_string($tableMarkingColor)) {
            $tableMarkingColor = Str::of($tableMarkingColor)->trim()->lower()->toString() ?: null;
        }

        $description = $this->input('description');

        if (is_string($description)) {
            $description = Str::of($description)->trim()->toString() ?: null;
        }

        $this->merge([
            'teaching_entry_area_id' => (int) $this->input('teaching_entry_area_id'),
            'short_name' => Str::of((string) $this->input('short_name'))->trim()->upper()->toString(),
            'name' => Str::of((string) $this->input('name'))->trim()->toString(),
            'description' => $description,
            'fixed_properties' => $fixedProperties,
            'has_properties' => $this->input('category') === 'Benotung',
            'has_notifications' => $this->input('has_notifications', false),
            'notification_recipients' => $notificationRecipients,
            'has_table_marking' => $this->input('has_table_marking', false),
            'table_marking_color' => $tableMarkingColor,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->schoolyear_id !== null
            && $user->hasAnyRole(['admin', 'teaching_admin', 'teacher']);
    }

    public function rules(): array
    {
        return [
            ...UpdateTeachingEntryCalculationSettingsRequest::definitionEvaluationRules($this->input('category'), $this->input('properties_mode'), $this->input('fixed_properties', []), $this->input('enabled_special_properties', $this->route('entryDefinition')?->enabled_special_properties ?? TeachingEntryDefinition::SpecialProperties)),
            'teaching_entry_area_id' => [
                'required',
                'integer',
                Rule::exists('teaching_entry_areas', 'id')->where(fn ($query) => $query
                    ->where('user_id', Auth::id())
                    ->where('school_id', Auth::user()?->school_id)
                    ->where('schoolyear_id', Auth::user()?->schoolyear_id)),
            ],
            'short_name' => [
                'required',
                'string',
                'max:2',
                'regex:/^[\pL\pN]+$/u',
                Rule::unique('teaching_entry_definitions', 'short_name')->where(fn ($query) => $query
                    ->where('user_id', Auth::id())
                    ->where('schoolyear_id', Auth::user()?->schoolyear_id)
                    ->where('teaching_entry_area_id', $this->input('teaching_entry_area_id'))),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1024'],
            'category' => ['required', 'string', Rule::in(['Benotung', 'Verhalten', 'Weitere'])],
            'has_properties' => ['required', 'boolean'],
            'enabled_special_properties' => ['sometimes', 'array', 'list', 'max:3'],
            'enabled_special_properties.*' => ['required', 'string', Rule::in(TeachingEntryDefinition::SpecialProperties), 'distinct:strict'],
            'properties_mode' => ['required', 'string', Rule::in(['fixed', 'free', 'plus', 'plus_minus', 'points'])],
            'maximum_points' => [
                Rule::requiredIf(fn (): bool => $this->boolean('has_properties') && $this->input('properties_mode') === 'points'),
                'nullable', 'numeric', 'gt:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_numeric($value) || ! is_finite((float) $value)) {
                        $fail('Bitte eine endliche positive maximale Punktzahl eingeben.');
                    }
                },
            ],
            'fixed_properties' => [
                Rule::requiredIf(fn () => $this->boolean('has_properties') && $this->input('properties_mode') === 'fixed'),
                'array',
                'max:20',
            ],
            'fixed_properties.*' => ['required', 'string', 'max:50', 'distinct:strict'],
            'has_notifications' => ['required', 'boolean'],
            'notification_recipients' => ['array', 'max:3'],
            'notification_recipients.*' => [
                'required',
                'string',
                Rule::in(['class_teacher', 'parents', 'student']),
                'distinct:strict',
            ],
            'has_table_marking' => ['required', 'boolean'],
            'table_marking_color' => [
                Rule::requiredIf(fn () => $this->input('category') === 'Benotung' && $this->boolean('has_table_marking')),
                'nullable',
                'string',
                Rule::in(TeachingEntryDefinition::TableMarkingColors),
            ],
        ];
    }
}
