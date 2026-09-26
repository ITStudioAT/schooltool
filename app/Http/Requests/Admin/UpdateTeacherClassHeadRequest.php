<?php

namespace App\Http\Requests\Admin;

use App\Models\Import116;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherClassHeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $classes = Import116::query()
            ->where('school_id', $this->user()->school_id)
            ->where('schoolyear_id', $this->user()->schoolyear_id)
            ->distinct()
            ->pluck('class');

        return [
            'class_names' => ['present', 'array'],
            'class_names.*' => ['required', 'string', 'max:255', 'distinct:strict', Rule::in($classes)],
        ];
    }
}
