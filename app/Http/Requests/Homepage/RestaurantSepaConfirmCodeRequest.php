<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantSepaConfirmCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['array'],
            'data.flow_uuid' => ['required', 'uuid'],
            'data.code' => ['required', 'string', 'size:6'],
        ];
    }
}
