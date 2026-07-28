<?php

namespace App\Http\Requests\Tutoring;

use App\Models\TutoringOfferRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OfferRequestMailClickedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $authenticatedUser = $this->user();

        if (! $authenticatedUser instanceof User || ! $authenticatedUser->hasRole('tutoring_user')) {
            return false;
        }

        $offerRequestId = $this->integer('request_id');
        if ($offerRequestId < 1) {
            return true;
        }

        $offerRequest = TutoringOfferRequest::query()->find($offerRequestId);
        if (! $offerRequest) {
            return true;
        }

        return $authenticatedUser->can('markMailClicked', $offerRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_id' => ['required', 'integer', 'exists:tutoring_offer_requests,id'],
        ];
    }
}
