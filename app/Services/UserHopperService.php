<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserHopperService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadHopperAccounts(User $user): array
    {
        return $this->linkedAccountUsers($user)
            ->map(fn (User $account) => $this->hopperAccountPayload($account))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    public function linkedAccountUsers(User $user): Collection
    {
        $accountIds = $this->normalizeHopperAccountIds($user->hopper_account_ids);
        if ($accountIds === []) {
            return collect();
        }

        $accounts = User::query()
            ->with(['roles', 'selectedSchool:id,long_name,short_name'])
            ->whereIn('id', $accountIds)
            ->get()
            ->filter(fn (User $account) => $this->canUseAsHopperTarget($account))
            ->keyBy('id');

        $resolvedAccountIds = collect($accountIds)
            ->filter(fn (int $accountId) => $accounts->has($accountId))
            ->values()
            ->all();

        if ($resolvedAccountIds !== $accountIds) {
            $user->forceFill([
                'hopper_account_ids' => $resolvedAccountIds,
            ])->save();
        }

        return collect($resolvedAccountIds)
            ->map(fn (int $accountId) => $accounts->get($accountId))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, School>
     */
    public function loadSwitchableSchools(User $user, ?string $email = null): Collection
    {
        $normalizedEmail = is_string($email) ? trim($email) : '';
        if ($normalizedEmail === '') {
            $normalizedEmail = trim((string) $user->email);
        }

        if ($normalizedEmail === '') {
            return collect();
        }

        return User::query()
            ->with(['selectedSchool:id,long_name,short_name', 'roles'])
            ->where('email', $normalizedEmail)
            ->get()
            ->reject(fn (User $candidate) => $candidate->id === $user->id)
            ->filter(fn (User $candidate) => $this->canUseAsHopperTarget($candidate))
            ->map(fn (User $candidate) => $candidate->selectedSchool)
            ->filter()
            ->unique('id')
            ->sortBy(fn (School $school) => mb_strtolower(trim((string) ($school->long_name ?: $school->short_name))))
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchUsers(User $user, ?string $lastName = null): array
    {
        $search = is_string($lastName) ? trim($lastName) : '';
        if ($search === '') {
            return [];
        }

        $users = User::query()
            ->with(['roles', 'selectedSchool:id,long_name,short_name'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('last_name', 'like', "{$search}%")
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email')
            ->limit(100)
            ->get()
            ->reject(fn (User $candidate) => $candidate->id === $user->id)
            ->filter(fn (User $candidate) => $this->canUseAsHopperTarget($candidate));

        return $users
            ->groupBy(fn (User $candidate) => mb_strtolower(trim((string) $candidate->email)))
            ->map(function (Collection $group) {
                /** @var User $first */
                $first = $group->first();
                $schools = $group
                    ->map(function (User $candidate) {
                        $label = trim((string) ($candidate->selectedSchool?->long_name ?: $candidate->selectedSchool?->short_name));

                        return [
                            'id' => (int) $candidate->school_id,
                            'label' => $label !== '' ? $label : ('Schule #'.(int) $candidate->school_id),
                        ];
                    })
                    ->unique('id')
                    ->sortBy('label')
                    ->values()
                    ->all();

                $fullName = trim(((string) ($first->last_name ?? '')).' '.((string) ($first->first_name ?? '')));
                $schoolsText = collect($schools)->pluck('label')->implode(', ');

                return [
                    'email' => (string) $first->email,
                    'first_name' => $first->first_name,
                    'last_name' => $first->last_name,
                    'school_count' => count($schools),
                    'schools' => $schools,
                    'label' => trim(($fullName !== '' ? $fullName : (string) $first->email).' • '.(string) $first->email),
                    'subtitle' => $schoolsText,
                ];
            })
            ->sortBy([
                ['last_name', 'asc'],
                ['first_name', 'asc'],
                ['email', 'asc'],
            ])
            ->values()
            ->take(20)
            ->all();
    }

    public function findHopperTarget(string $email, int $schoolId): ?User
    {
        return User::query()
            ->with(['roles', 'selectedSchool:id,long_name,short_name'])
            ->where('email', trim($email))
            ->where('school_id', $schoolId)
            ->first();
    }

    public function storeHopperAccount(User $user, User $targetUser): array
    {
        if ($user->id === $targetUser->id) {
            abort(422, 'Das aktuelle Konto kann nicht als Hopper-Konto gespeichert werden.');
        }

        if (! $this->canUseAsHopperTarget($targetUser)) {
            abort(422, 'Dieses Konto kann nicht für den Schnellwechsel verwendet werden.');
        }

        DB::transaction(function () use ($user, $targetUser) {
            $freshUser = $user->fresh(['roles']);
            $freshTargetUser = $targetUser->fresh(['roles']);

            if (! $freshUser || ! $freshTargetUser) {
                abort(404, 'Das ausgewählte Konto wurde nicht gefunden.');
            }

            $userAccountIds = $this->normalizeHopperAccountIds($freshUser->hopper_account_ids);
            if (! in_array($freshTargetUser->id, $userAccountIds, true)) {
                $userAccountIds[] = $freshTargetUser->id;
            }

            $targetAccountIds = $this->normalizeHopperAccountIds($freshTargetUser->hopper_account_ids);
            if (! in_array($freshUser->id, $targetAccountIds, true)) {
                $targetAccountIds[] = $freshUser->id;
            }

            $freshUser->forceFill([
                'hopper_account_ids' => array_values($userAccountIds),
            ])->save();

            $freshTargetUser->forceFill([
                'hopper_account_ids' => array_values($targetAccountIds),
            ])->save();
        });

        $refreshedUser = $user->fresh();

        return $this->loadHopperAccounts($refreshedUser instanceof User ? $refreshedUser : $user);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function removeHopperAccount(User $user, int $targetUserId): array
    {
        DB::transaction(function () use ($user, $targetUserId) {
            $freshUser = $user->fresh();
            if (! $freshUser) {
                abort(404, 'Das aktuelle Konto wurde nicht gefunden.');
            }

            $userAccountIds = collect($this->normalizeHopperAccountIds($freshUser->hopper_account_ids))
                ->reject(fn (int $accountId) => $accountId === $targetUserId)
                ->values()
                ->all();

            $freshUser->forceFill([
                'hopper_account_ids' => $userAccountIds,
            ])->save();

            $targetUser = User::find($targetUserId);
            if (! $targetUser) {
                return;
            }

            $targetAccountIds = collect($this->normalizeHopperAccountIds($targetUser->hopper_account_ids))
                ->reject(fn (int $accountId) => $accountId === $freshUser->id)
                ->values()
                ->all();

            $targetUser->forceFill([
                'hopper_account_ids' => $targetAccountIds,
            ])->save();
        });

        $refreshedUser = $user->fresh();

        return $this->loadHopperAccounts($refreshedUser instanceof User ? $refreshedUser : $user);
    }

    public function switchToHopperAccount(User $user, int $targetUserId): User
    {
        $accountIds = $this->normalizeHopperAccountIds($user->hopper_account_ids);
        if (! in_array($targetUserId, $accountIds, true)) {
            abort(403, 'Wechsel zu diesem Hopper-Konto ist nicht erlaubt.');
        }

        $targetUser = User::query()
            ->with('roles')
            ->find($targetUserId);

        if (! $targetUser) {
            abort(404, 'Das Hopper-Konto wurde nicht gefunden.');
        }

        app(FeaturePreviewService::class)->assertCanEnter($targetUser);

        if (! $this->canUseAsHopperTarget($targetUser)) {
            abort(403, 'Dieses Hopper-Konto ist nicht mehr verfügbar.');
        }

        if ($targetUser->hasEnabledTwoFactorAuthentication()) {
            abort(423, 'Dieses Konto ist mit Zwei-Faktor-Authentifizierung geschützt. Bitte melden Sie sich direkt beim Zielkonto an.');
        }

        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        session()->forget([
            'auth.password_confirmed_at',
            'login.id',
            'login.remember',
            'login.two_factor_started_at',
            'login.context',
            'two_factor_empty_at',
            'two_factor_confirming_at',
        ]);

        Auth::guard('web')->login($targetUser, true);
        session()->regenerate();

        return $targetUser;
    }

    public function canUseAsHopperTarget(User $user): bool
    {
        return $user->hasAdminShellAccess();
    }

    /**
     * @return array<string, mixed>
     */
    private function hopperAccountPayload(User $user): array
    {
        $school = $user->selectedSchool;
        $schoolLabel = trim((string) ($school?->long_name ?: $school?->short_name));

        return [
            'id' => (int) $user->id,
            'school_id' => (int) $user->school_id,
            'school_label' => $schoolLabel !== '' ? $schoolLabel : ('Schule #'.(int) $user->school_id),
            'email' => (string) $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function normalizeHopperAccountIds(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn ($accountId) => is_numeric($accountId) ? (int) $accountId : 0)
            ->filter(fn (int $accountId) => $accountId > 0)
            ->unique()
            ->values()
            ->all();
    }
}
