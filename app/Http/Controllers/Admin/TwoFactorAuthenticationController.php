<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmCurrentPasswordRequest;
use App\Http\Requests\Admin\ConfirmTwoFactorAuthenticationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lab404\Impersonate\Services\ImpersonateManager;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

class TwoFactorAuthenticationController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $this->ensureNotImpersonating();

        return $this->secureJson($this->statusPayload($this->user($request)));
    }

    public function confirmPassword(ConfirmCurrentPasswordRequest $request): JsonResponse
    {
        $this->ensureNotImpersonating();

        if (! Hash::check($request->validated('password'), $this->user($request)->password)) {
            throw ValidationException::withMessages([
                'password' => ['Das eingegebene Kennwort ist nicht korrekt.'],
            ]);
        }

        $request->session()->passwordConfirmed();

        return $this->secureJson(['confirmed' => true]);
    }

    public function store(Request $request, EnableTwoFactorAuthentication $enable): JsonResponse
    {
        $this->ensureNotImpersonating();
        $user = $this->user($request);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            abort(409, 'Die Zwei-Faktor-Authentifizierung ist bereits aktiviert.');
        }

        DB::transaction(function () use ($enable, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $enable($lockedUser);
        });

        $user->refresh();

        return $this->secureJson([
            ...$this->statusPayload($user),
            ...$this->setupPayload($user),
        ]);
    }

    public function confirm(
        ConfirmTwoFactorAuthenticationRequest $request,
        ConfirmTwoFactorAuthentication $confirm,
    ): JsonResponse {
        $this->ensureNotImpersonating();
        $user = $this->user($request);

        if (blank($user->two_factor_secret) || $user->two_factor_confirmed_at !== null) {
            abort(409, 'Es gibt keine unbestätigte Zwei-Faktor-Einrichtung.');
        }

        DB::transaction(function () use ($confirm, $request, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $confirm($lockedUser, $request->validated('code'));
        });

        $user->refresh();

        return $this->secureJson([
            ...$this->statusPayload($user),
            'recovery_codes' => $user->recoveryCodes(),
        ]);
    }

    public function cancel(Request $request, DisableTwoFactorAuthentication $disable): JsonResponse
    {
        $this->ensureNotImpersonating();
        $user = $this->user($request);

        if ($user->two_factor_confirmed_at !== null) {
            abort(409, 'Eine aktive Zwei-Faktor-Authentifizierung muss deaktiviert werden.');
        }

        if ($user->two_factor_secret !== null || $user->two_factor_recovery_codes !== null) {
            DB::transaction(fn () => $disable(User::query()->lockForUpdate()->findOrFail($user->id)));
        }

        $this->clearSetupState($request);

        return $this->secureJson($this->statusPayload($user->fresh()));
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $this->ensureNotImpersonating();
        $user = $this->confirmedUser($request);

        return $this->secureJson(['recovery_codes' => $user->recoveryCodes()]);
    }

    public function regenerateRecoveryCodes(
        Request $request,
        GenerateNewRecoveryCodes $generate,
    ): JsonResponse {
        $this->ensureNotImpersonating();
        $user = $this->confirmedUser($request);

        DB::transaction(function () use ($generate, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $generate($lockedUser);
        });

        return $this->secureJson(['recovery_codes' => $user->fresh()->recoveryCodes()]);
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable): JsonResponse
    {
        $this->ensureNotImpersonating();
        $user = $this->user($request);

        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at === null) {
            abort(409, 'Brechen Sie die unvollständige Einrichtung stattdessen ab.');
        }

        if ($user->two_factor_confirmed_at !== null) {
            DB::transaction(fn () => $disable(User::query()->lockForUpdate()->findOrFail($user->id)));
        }

        $this->clearSetupState($request);

        return $this->secureJson([
            ...$this->statusPayload($user->fresh()),
            'message' => 'Die Zwei-Faktor-Authentifizierung wurde deaktiviert.',
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Nicht angemeldet.');

        return $user;
    }

    private function confirmedUser(Request $request): User
    {
        $user = $this->user($request);

        abort_unless($user->hasEnabledTwoFactorAuthentication(), 409, 'Die Zwei-Faktor-Authentifizierung ist nicht aktiviert.');

        return $user;
    }

    /** @return array{enabled: bool, pending: bool, confirmed_at: string|null} */
    private function statusPayload(User $user): array
    {
        return [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        ];
    }

    /** @return array{qr_code_svg: string, manual_key: string} */
    private function setupPayload(User $user): array
    {
        abort_if($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null, 404);

        return [
            'qr_code_svg' => $user->twoFactorQrCodeSvg(),
            'manual_key' => Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
        ];
    }

    private function ensureNotImpersonating(): void
    {
        abort_if(app(ImpersonateManager::class)->isImpersonating(), 403, 'Die Zwei-Faktor-Einstellungen sind während einer Benutzer-Übernahme gesperrt.');
    }

    private function clearSetupState(Request $request): void
    {
        $request->session()->forget(['two_factor_empty_at', 'two_factor_confirming_at']);
    }

    /** @param array<string, mixed> $data */
    private function secureJson(array $data): JsonResponse
    {
        return response()->json($data)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
