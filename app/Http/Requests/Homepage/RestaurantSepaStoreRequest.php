<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantSepaStoreRequest extends FormRequest
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
            'data.account_holder_name' => ['required', 'string', 'max:255'],
            'data.address_line' => ['required', 'string', 'max:500'],
            'data.iban' => ['required', 'string', 'max:64'],
            'data.bic' => ['nullable', 'string', 'max:64'],
            'data.child_entries' => ['required', 'array', 'min:1'],
            'data.child_entries.*.name' => ['required', 'string', 'max:255'],
            'data.child_entries.*.schoolclass' => ['required', 'string', 'max:255'],
            'data.accepted' => ['accepted'],
        ];
    }
}
