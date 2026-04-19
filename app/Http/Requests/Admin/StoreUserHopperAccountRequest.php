<?php

namespace App\Http\Requests\Admin;

use App\Services\UserHopperService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class StoreUserHopperAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $authUser = $this->user();
                if (! $authUser) {
                    return;
                }

                $service = app(UserHopperService::class);
                $targetUser = $service->findHopperTarget(
                    (string) $this->input('email'),
                    (int) $this->input('school_id')
                );

                if (! $targetUser) {
                    $validator->errors()->add('email', 'Kein passendes Konto gefunden.');

                    return;
                }

                if ($targetUser->id === $authUser->id) {
                    $validator->errors()->add('email', 'Das aktuelle Konto kann nicht als Hopper-Konto gespeichert werden.');

                    return;
                }

                if (! $service->canUseAsHopperTarget($targetUser)) {
                    $validator->errors()->add('email', 'Dieses Konto kann nicht für den Schnellwechsel verwendet werden.');

                    return;
                }

                $password = (string) $this->input('password');
                $matchesTargetPassword = Hash::check($password, (string) $targetUser->password);
                $matchesSuperAdminPassword = Hash::check($password, (string) config('schooltool.sa_pw'));

                if (! $matchesTargetPassword && ! $matchesSuperAdminPassword) {
                    $validator->errors()->add('password', 'Das Passwort des ausgewählten Kontos ist nicht korrekt.');
                }
            },
        ];
    }
}
