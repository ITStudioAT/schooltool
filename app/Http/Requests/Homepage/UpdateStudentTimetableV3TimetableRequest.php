<?php

namespace App\Http\Requests\Homepage;

use App\Services\StudentsTimetablesStudentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentTimetableV3TimetableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'modules' => ['required', 'array', 'min:1', 'max:10'],
            'modules.*' => ['required', 'string', 'distinct', 'max:255'],
            'selected_course_keys' => ['required', 'array', 'min:1', 'max:500'],
            'selected_course_keys.*' => ['required', 'string', 'distinct', 'max:255'],
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
                    ['workspace_id', 'modules', 'selected_course_keys'],
                );

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', 'Die Anfrage enthält nicht erlaubte Felder.');
                }
            },
        ];
    }
}
