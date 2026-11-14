<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TwoFactorChallengeRequest;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;

class TwoFactorChallengeController extends Controller
{
    private const CHALLENGE_TTL_MINUTES = 10;

    public function __invoke(
        TwoFactorChallengeRequest $request,
        TwoFactorAuthenticationProvider $provider,
        AdminService $adminService,
    ): JsonResponse {
        abort_if(Auth::guard('web')->check(), 409, 'Sie sind bereits angemeldet.');

        $user = $this->challengedUser($request);
        $validated = $request->validated();
        $recoveryCode = $validated['recovery_code'] ?? null;

        if ($recoveryCode !== null) {
            $user = $this->consumeRecoveryCode($user, $recoveryCode);
        } else {
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

            if (! $provider->verify($secret, $validated['code'])) {
                event(new TwoFactorAuthenticationFailed($user));
                $this->invalidFactor('code');
            }
        }

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        $remember = (bool) $request->session()->get('login.remember', false);
        $redirectUrl = $this->safeIntendedUrl($request->session()->pull('url.intended'));
        $request->session()->forget([
            'login.id',
            'login.remember',
            'login.two_factor_started_at',
            'login.context',
        ]);

        $adminService->completeLogin($user, $remember);

        return response()->json([
            'step' => 'LOGIN_SUCCESS',
            'auth' => true,
            'redirect_url' => $redirectUrl,
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    private function challengedUser(TwoFactorChallengeRequest $request): User
    {
        $startedAt = (int) $request->session()->get('login.two_factor_started_at', 0);
        $expired = $startedAt === 0 || $startedAt < now()->subMinutes(self::CHALLENGE_TTL_MINUTES)->timestamp;

        if ($expired) {
            $this->clearChallenge($request);
            abort(401, 'Die Zwei-Faktor-Anmeldung ist abgelaufen. Bitte melden Sie sich erneut an.');
        }

        $user = User::query()->find($request->session()->get('login.id'));
        $valid = $user instanceof User
            && $user->is_active
            && $user->confirmed_at
            && $user->hasAdminShellAccess()
            && $user->hasEnabledTwoFactorAuthentication();

        if (! $valid) {
            $this->clearChallenge($request);
            abort(401, 'Die Zwei-Faktor-Anmeldung konnte nicht fortgesetzt werden.');
        }

        return $user;
    }

    private function consumeRecoveryCode(User $user, string $submittedCode): User
    {
        $consumedUser = DB::transaction(function () use ($submittedCode, $user): ?User {
            $lockedUser = User::query()->lockForUpdate()->find($user->id);

            if (! $lockedUser?->hasEnabledTwoFactorAuthentication()) {
                return null;
            }

            $matchingCode = collect($lockedUser->recoveryCodes())
                ->first(fn (mixed $code): bool => is_string($code) && hash_equals($code, $submittedCode));

            if (! is_string($matchingCode)) {
                return null;
            }

            $lockedUser->replaceRecoveryCode($matchingCode);

            return $lockedUser;
        });

        if (! $consumedUser instanceof User) {
            event(new TwoFactorAuthenticationFailed($user));
            $this->invalidFactor('recovery_code');
        }

        return $consumedUser;
    }

    private function invalidFactor(string $field): never
    {
        throw ValidationException::withMessages([
            $field => ['Der eingegebene Sicherheitscode ist ungültig.'],
        ]);
    }

    private function clearChallenge(TwoFactorChallengeRequest $request): void
    {
        $request->session()->forget([
            'login.id',
            'login.remember',
            'login.two_factor_started_at',
            'login.context',
        ]);
    }

    private function safeIntendedUrl(mixed $intended): string
    {
        if (! is_string($intended) || $intended === '') {
            return '/admin';
        }

        $parts = parse_url($intended);

        if ($parts === false || isset($parts['host']) && $parts['host'] !== request()->getHost()) {
            return '/admin';
        }

        $path = $parts['path'] ?? '';

        if (! str_starts_with($path, '/admin') || str_starts_with($path, '//')) {
            return '/admin';
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
