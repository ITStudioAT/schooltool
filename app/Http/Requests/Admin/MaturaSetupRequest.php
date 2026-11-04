<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MaturaSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->hasAdminShellAccess();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'schoolyear_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'exam_date' => ['required', 'date_format:Y-m-d'],
            'waiting_places' => ['required', 'integer', 'between:0,10'],
            'rooms' => ['required', 'array', 'min:1', 'max:40'],
            'rooms.*.name' => ['required', 'string', 'max:80', 'distinct'],
            'rooms.*.student_ids' => ['present', 'array', 'max:500'],
            'rooms.*.student_ids.*' => ['integer', 'distinct'],
            'rooms.*.manual_students' => ['present', 'array', 'max:200'],
            'rooms.*.manual_students.*.name' => ['required', 'string', 'max:180'],
            'rooms.*.manual_students.*.class_name' => ['nullable', 'string', 'max:80'],
        ];
    }
}
