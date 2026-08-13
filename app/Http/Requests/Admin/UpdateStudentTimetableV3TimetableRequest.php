<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentTimetableV3TimetableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modules' => ['required', 'array', 'min:1', 'max:10'],
            'modules.*' => ['required', 'string', 'distinct', 'max:255'],
            'parameters' => ['required', 'array:planning_mode,student_code,selection,selected_course_keys'],
            'parameters.planning_mode' => ['required', 'string', Rule::in(['with_student', 'without_student'])],
            'parameters.student_code' => [
                Rule::requiredIf(fn (): bool => $this->input('parameters.planning_mode') === 'with_student'),
                Rule::prohibitedIf(fn (): bool => $this->input('parameters.planning_mode') === 'without_student'),
                'nullable',
                'string',
                'max:255',
            ],
            'parameters.selection' => ['required', 'array:religion,language,branch,arts_subject'],
            'parameters.selection.religion' => ['nullable', 'string', 'max:20'],
            'parameters.selection.language' => ['nullable', 'string', 'max:20'],
            'parameters.selection.branch' => ['nullable', 'string', 'max:80'],
            'parameters.selection.arts_subject' => ['nullable', 'string', 'max:20'],
            'parameters.selected_course_keys' => ['required', 'array', 'min:1', 'max:500'],
            'parameters.selected_course_keys.*' => ['required', 'string', 'distinct', 'max:255'],
            'options' => ['required', 'array:allow_saturday_lessons'],
            'options.allow_saturday_lessons' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedKeys = array_diff(array_keys($this->all()), ['modules', 'parameters', 'options']);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', 'Die Anfrage enthält nicht erlaubte Felder.');
                }
            },
        ];
    }
}
