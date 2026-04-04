<?php

namespace App\Http\Requests\Homepage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RestaurantCreateBookingRequest extends FormRequest
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
            'data' => 'required|array',
            'data.restaurant_menu_plan_entry_id' => 'required|integer|exists:restaurant_menu_plan_entries,id',
            'data.restaurant_eating_time_id' => 'nullable|integer|exists:restaurant_eating_times,id',
            'data.quantity' => 'nullable|integer|min:1|max:100',
            'data.price' => 'nullable|numeric|min:0|max:1000',
            'data.child_name' => 'nullable|string|max:255',
            'data.child_type' => 'nullable|string|in:child,other_person',
            'data.import116_id' => 'nullable|integer|exists:import116,id',
            'data.notes' => 'nullable|string|max:1000',
            'data.recipients' => 'nullable|array|max:100',
            'data.recipients.*.name' => 'required_with:data.recipients|string|max:255',
            'data.recipients.*.type' => 'nullable|string|in:self,child,other_person',
            'data.recipients.*.import116_id' => 'nullable|integer|exists:import116,id',
            'data.single_recipient_customized' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'data.restaurant_menu_plan_entry_id.required' => 'Bitte wählen Sie ein Menü aus.',
            'data.restaurant_menu_plan_entry_id.exists' => 'Das ausgewählte Menü existiert nicht.',
            'data.restaurant_eating_time_id.exists' => 'Die ausgewählte Speisezeit existiert nicht.',
            'data.quantity.min' => 'Die Menge muss mindestens 1 betragen.',
            'data.quantity.max' => 'Die Menge darf maximal 100 betragen.',
            'data.price.min' => 'Der Preis darf nicht negativ sein.',
            'data.price.max' => 'Der Preis ist zu hoch.',
            'data.child_type.in' => 'Ungültiger Typ für Kind/Person.',
            'data.import116_id.exists' => 'Der ausgewählte Import116-Eintrag existiert nicht.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'data' => $this->input('data', []),
        ]);
    }
}
