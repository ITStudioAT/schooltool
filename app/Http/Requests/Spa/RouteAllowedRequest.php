<?php

namespace App\Http\Requests\Spa;

use Illuminate\Foundation\Http\FormRequest;

class RouteAllowedRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'data.route' => 'in:admin',
            'data.from' => 'nullable|string',
            'data.to' => 'nullable|string',
            'data.matching_path' => 'nullable|string',
            'data.base_path' => 'nullable|string',
        ];
    }
}
