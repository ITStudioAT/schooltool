<?php

namespace App\Http\Requests\Admin\Teaching;

use App\Services\Teaching\TeachingExplanationCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurriculumExplanationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'explanation_key' => ['required', 'string', Rule::in(array_keys(TeachingExplanationCatalog::all()))],
        ];
    }
}
