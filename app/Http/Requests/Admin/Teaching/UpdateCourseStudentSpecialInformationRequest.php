<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Models\TeachingCourse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseStudentSpecialInformationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof TeachingCourse
            && $this->user()?->can('update', $course) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'special_information' => ['present', 'nullable', 'string', 'max:4096'],
        ];
    }
}
