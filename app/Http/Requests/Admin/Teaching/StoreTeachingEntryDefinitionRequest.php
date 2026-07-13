<?php

namespace App\Http\Requests\Admin\Teaching;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTeachingEntryDefinitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $fixedProperties = collect($this->input('fixed_properties', []))
            ->map(fn (mixed $property): string => trim((string) $property))
            ->all();
        $notificationRecipients = $this->input('notification_recipients', []);

        if (is_array($notificationRecipients)) {
            $notificationRecipients = collect($notificationRecipients)
                ->map(fn (mixed $recipient): string => trim((string) $recipient))
                ->all();
        }

        $this->merge([
            'teaching_entry_area_id' => (int) $this->input('teaching_entry_area_id'),
            'short_name' => Str::of((string) $this->input('short_name'))->trim()->upper()->toString(),
            'name' => Str::of((string) $this->input('name'))->trim()->toString(),
            'fixed_properties' => $fixedProperties,
            'has_notifications' => $this->input('has_notifications', false),
            'notification_recipients' => $notificationRecipients,
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
            'category' => ['required', 'string', Rule::in(['Benotung', 'Verhalten', 'Weitere'])],
            'has_properties' => ['required', 'boolean'],
            'properties_mode' => ['required', 'string', Rule::in(['fixed', 'free'])],
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
        ];
    }
}
