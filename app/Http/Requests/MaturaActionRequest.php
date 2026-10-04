<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaturaActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['claim', 'request', 'approve', 'depart', 'arrive', 'enter', 'exit', 'return', 'cancel', 'void', 'return_without_toilet'])],
            'operation_key' => ['required', 'uuid'],
            'student_id' => ['required_if:action,request', 'integer'],
            'visit_id' => ['nullable', 'integer'],
            'expected_status' => ['nullable', 'string', 'max:20'],
            'previous_access_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
