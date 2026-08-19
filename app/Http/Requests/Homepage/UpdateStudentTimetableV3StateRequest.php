<?php

namespace App\Http\Requests\Homepage;

use App\Services\StudentsTimetablesStudentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentTimetableV3StateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(StudentsTimetablesStudentService::ROLE_NAME) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'uuid'],
            'manual_timetable_draft' => [
                'required',
                'array:source,fingerprint,timetable_key,timetable_index,selected_course_keys,removed_course_keys',
            ],
            'manual_timetable_draft.source' => ['required', 'string', Rule::in(['automatic'])],
            'manual_timetable_draft.fingerprint' => [
                'required',
                'string',
                'size:64',
                'regex:/\A[a-f0-9]{64}\z/',
            ],
            'manual_timetable_draft.timetable_key' => ['required', 'string', 'max:255'],
            'manual_timetable_draft.timetable_index' => ['required', 'integer', 'min:0', 'max:1999'],
            'manual_timetable_draft.selected_course_keys' => ['required', 'array', 'max:500'],
            'manual_timetable_draft.selected_course_keys.*' => ['required', 'string', 'distinct', 'max:255'],
            'manual_timetable_draft.removed_course_keys' => ['required', 'array', 'max:500'],
            'manual_timetable_draft.removed_course_keys.*' => ['required', 'string', 'distinct', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedKeys = array_diff(
                    array_keys($this->all()),
                    ['workspace_id', 'manual_timetable_draft'],
                );

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', 'Die Anfrage enthält nicht erlaubte Felder.');
                }
            },
        ];
    }
}
