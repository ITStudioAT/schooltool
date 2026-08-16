<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentTimetableV3StateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'context' => ['required', 'array:workspace_id,planning_mode,student_code'],
            'context.workspace_id' => ['required', 'uuid'],
            'context.planning_mode' => ['required', 'string', Rule::in(['with_student', 'without_student'])],
            'context.student_code' => [
                Rule::requiredIf(fn (): bool => $this->input('context.planning_mode') === 'with_student'),
                Rule::prohibitedIf(fn (): bool => $this->input('context.planning_mode') === 'without_student'),
                'nullable',
                'string',
                'max:255',
            ],
            'state' => ['required', 'array', 'max:50'],
        ];
    }
}
