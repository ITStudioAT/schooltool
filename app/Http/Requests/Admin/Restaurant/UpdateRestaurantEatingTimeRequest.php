<?php

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateRestaurantEatingTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $schoolId = Auth::user()?->school_id;
        $eatingTimeId = $this->route('eating_time');

        return [
            'eating_time' => [
                'required',
                'string',
                'regex:/^\d{2}:\d{2}$/',
                Rule::unique('restaurant_eating_times', 'eating_time')
                    ->ignore($eatingTimeId)
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'eating_time.required' => 'Bitte geben Sie eine Uhrzeit ein.',
            'eating_time.regex' => 'Die Uhrzeit muss im Format HH:MM angegeben werden.',
            'eating_time.unique' => 'Diese Speisezeit ist bereits vorhanden.',
        ];
    }
}
