<?php

namespace App\Services;

use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RestaurantCdgymUserSyncService
{
    /**
     * @param  Collection<int, array<string, mixed>|object>  $sourceRows
     * @return array<string, int>
     */
    public function sync(int $schoolId, Collection $sourceRows, bool $apply = false): array
    {
        $summary = [
            'source_rows_seen' => $sourceRows->count(),
            'source_users_seen' => 0,
            'source_users_skipped' => 0,
            'users_matched' => 0,
            'users_to_create' => 0,
            'users_created' => 0,
            'roles_to_assign' => 0,
            'roles_assigned' => 0,
            'lunch_user_roles_assigned' => 0,
            'lunch_admin_roles_assigned' => 0,
        ];

        $normalizedSourceUsers = $this->normalizedSourceUsers($sourceRows, $summary);
        $summary['source_users_seen'] = $normalizedSourceUsers->count();

        $activeSchoolyearId = Schoolyear::query()
            ->where('school_id', $schoolId)
            ->active()
            ->value('id');

        if ($apply) {
            $this->ensureTargetRolesExist();
        }

        foreach ($normalizedSourceUsers as $sourceUser) {
            $localUser = User::query()
                ->where('school_id', $schoolId)
                ->whereRaw('LOWER(email) = ?', [$sourceUser['email']])
                ->first();

            if ($localUser) {
                $summary['users_matched']++;
            } else {
                $summary['users_to_create']++;
            }

            $missingRoles = collect($sourceUser['roles'])
                ->filter(fn (string $roleName): bool => ! $localUser || ! $localUser->hasRole($roleName))
                ->values();

            $summary['roles_to_assign'] += $missingRoles->count();

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use (
                $schoolId,
                $activeSchoolyearId,
                $sourceUser,
                $localUser,
                $missingRoles,
                &$summary
            ): void {
                $targetUser = $localUser;

                if (! $targetUser) {
                    $targetUser = $this->createLocalUser($schoolId, $activeSchoolyearId, $sourceUser);
                    $summary['users_created']++;
                }

                if ($missingRoles->isEmpty()) {
                    return;
                }

                $targetUser->assignRole($missingRoles->all());
                $summary['roles_assigned'] += $missingRoles->count();
                $summary['lunch_user_roles_assigned'] += $missingRoles
                    ->filter(fn (string $roleName): bool => $roleName === 'lunch_user')
                    ->count();
                $summary['lunch_admin_roles_assigned'] += $missingRoles
                    ->filter(fn (string $roleName): bool => $roleName === 'lunch_admin')
                    ->count();
            });
        }

        return $summary;
    }

    private function ensureTargetRolesExist(): void
    {
        foreach (['lunch_user', 'lunch_admin'] as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>|object>  $sourceRows
     * @param  array<string, int>  $summary
     * @return Collection<int, array{email: string, first_name: ?string, last_name: ?string, email_verified_at: ?string, confirmed_at: ?string, roles: array<int, string>}>
     */
    private function normalizedSourceUsers(Collection $sourceRows, array &$summary): Collection
    {
        /** @var array<string, array{email: string, first_name: ?string, last_name: ?string, email_verified_at: ?string, confirmed_at: ?string, roles: array<int, string>}> $groupedUsers */
        $groupedUsers = [];

        foreach ($sourceRows as $sourceRow) {
            $normalizedRow = $this->normalizeSourceRow($sourceRow);

            if (! $normalizedRow) {
                $summary['source_users_skipped']++;

                continue;
            }

            $email = $normalizedRow['email'];

            if (! array_key_exists($email, $groupedUsers)) {
                $groupedUsers[$email] = $normalizedRow;

                continue;
            }

            $groupedUsers[$email] = [
                'email' => $email,
                'first_name' => $groupedUsers[$email]['first_name'] ?: $normalizedRow['first_name'],
                'last_name' => $groupedUsers[$email]['last_name'] ?: $normalizedRow['last_name'],
                'email_verified_at' => $groupedUsers[$email]['email_verified_at'] ?: $normalizedRow['email_verified_at'],
                'confirmed_at' => $groupedUsers[$email]['confirmed_at'] ?: $normalizedRow['confirmed_at'],
                'roles' => collect([
                    ...$groupedUsers[$email]['roles'],
                    ...$normalizedRow['roles'],
                ])->unique()->values()->all(),
            ];
        }

        return collect(array_values($groupedUsers));
    }

    /**
     * @param  array<string, mixed>|object  $sourceRow
     * @return array{email: string, first_name: ?string, last_name: ?string, email_verified_at: ?string, confirmed_at: ?string, roles: array<int, string>}|null
     */
    private function normalizeSourceRow(array|object $sourceRow): ?array
    {
        $email = Str::lower(trim((string) data_get($sourceRow, 'email')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $roleNames = collect(data_get($sourceRow, 'roles', [data_get($sourceRow, 'role_name')]))
            ->flatten()
            ->map(fn ($roleName): string => trim((string) $roleName))
            ->filter(fn (string $roleName): bool => in_array($roleName, ['lunch_user', 'lunch_admin'], true))
            ->unique()
            ->values()
            ->all();

        if ($roleNames === []) {
            return null;
        }

        $emailVerifiedAt = $this->normalizeNullableString(data_get($sourceRow, 'email_verified_at'));

        return [
            'email' => $email,
            'first_name' => $this->normalizeNullableString(data_get($sourceRow, 'first_name')),
            'last_name' => $this->normalizeNullableString(data_get($sourceRow, 'last_name')),
            'email_verified_at' => $emailVerifiedAt,
            'confirmed_at' => $this->normalizeNullableString(data_get($sourceRow, 'confirmed_at')) ?: $emailVerifiedAt,
            'roles' => $roleNames,
        ];
    }

    /**
     * @param  array{email: string, first_name: ?string, last_name: ?string, email_verified_at: ?string, confirmed_at: ?string, roles: array<int, string>}  $sourceUser
     */
    private function createLocalUser(int $schoolId, ?int $activeSchoolyearId, array $sourceUser): User
    {
        $user = new User;
        $user->school_id = $schoolId;
        $user->schoolyear_id = $activeSchoolyearId;
        $user->email = $sourceUser['email'];
        $user->password = Hash::make(Str::random(40));
        $user->first_name = $sourceUser['first_name'];
        $user->last_name = $sourceUser['last_name'];
        $user->email_verified_at = $sourceUser['email_verified_at'];
        $user->confirmed_at = $sourceUser['confirmed_at'];
        $user->teaching_show_behaviour = true;
        $user->remember_token = Str::random(10);
        $user->save();

        return $user;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
