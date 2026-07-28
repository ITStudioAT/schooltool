<?php

namespace App\Http\Requests\Tutoring;

use App\Models\TutoringOffer;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OfferToggleOfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $offer = TutoringOffer::query()->find($this->integer('id'));

        return $user instanceof User
            && $offer instanceof TutoringOffer
            && $user->can('update', $offer);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:tutoring_offers,id',
        ];
    }
}
