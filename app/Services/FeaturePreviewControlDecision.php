<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;

class FeaturePreviewControlDecision
{
    public function __construct(private FeaturePreviewDatabaseGuard $databases) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function decide(string $operation, array $payload): array
    {
        abort_if(config('schooltool.preview.instance'), 404);
        abort_unless(in_array($operation, ['status', 'admission', 'recipient'], true), 422);

        if ($operation === 'status') {
            abort_unless($payload === [], 422);

            return $this->databases->withMainReadOnlyConnection(function (Connection $connection): array {
                $ready = $this->schemaReady($connection);

                return [
                    'schema_ready' => $ready,
                    'enabled' => $ready && $this->enabled($connection),
                    'source' => $this->databases->connectionIdentity($connection),
                ];
            });
        }

        if (! $this->validPayload($operation, $payload)) {
            return ['allowed' => false];
        }

        return ['allowed' => $this->databases->withMainReadOnlyConnection(
            fn (Connection $connection): bool => $this->allowed($connection, $operation, $payload),
        )];
    }

    private function schemaReady(Connection $connection): bool
    {
        return $connection->getSchemaBuilder()->hasTable('feature_preview_settings')
            && $connection->getSchemaBuilder()->hasColumn('users', 'feature_preview_allowed');
    }

    private function enabled(Connection $connection): bool
    {
        return (bool) $connection->table('feature_preview_settings')->where('id', 1)->value('enabled');
    }

    /** @param array<string, mixed> $payload */
    private function validPayload(string $operation, array $payload): bool
    {
        $keys = ['source_identity', 'identity', 'auth_fingerprint', 'roles', 'permissions'];
        $rules = [
            'source_identity' => ['required', 'array:database,server_fingerprint,app_key_fingerprint'],
            'source_identity.database' => ['required', 'string', 'max:64', 'regex:/\A[a-zA-Z0-9_]+\z/'],
            'source_identity.server_fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'source_identity.app_key_fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'identity' => ['required', 'array:id,school_id,email,created_at'],
            'identity.id' => ['required', 'integer', 'min:1'],
            'identity.school_id' => ['required', 'integer', 'min:1'],
            'identity.email' => ['required', 'string', 'max:255'],
            'identity.created_at' => ['required', 'string', 'max:40'],
            'auth_fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'roles' => ['present', 'array', 'max:128'],
            'roles.*' => ['array:name,guard_name,is_admin'],
            'roles.*.name' => ['required', 'string', 'max:255'],
            'roles.*.guard_name' => ['required', 'string', 'max:255'],
            'roles.*.is_admin' => ['required', 'boolean'],
            'permissions' => ['present', 'array', 'max:512'],
            'permissions.*' => ['array:name,guard_name'],
            'permissions.*.name' => ['required', 'string', 'max:255'],
            'permissions.*.guard_name' => ['required', 'string', 'max:255'],
        ];
        if ($operation === 'recipient') {
            $keys = [...$keys, 'recipient', 'recipient_kind', 'schoolyear_id'];
            $rules += [
                'recipient' => ['required', 'string', 'max:255'],
                'recipient_kind' => ['required', 'in:account,second_factor,teaching_parent,restaurant_parent'],
                'schoolyear_id' => ['present', 'nullable', 'integer', 'min:1'],
            ];
        }

        return array_diff(array_keys($payload), $keys) === [] && Validator::make($payload, $rules)->passes();
    }

    /** @param array<string, mixed> $payload */
    private function allowed(Connection $connection, string $operation, array $payload): bool
    {
        if (! $this->schemaReady($connection) || ! $this->enabled($connection)) {
            return false;
        }

        foreach ($this->databases->connectionIdentity($connection) as $key => $value) {
            if (! hash_equals($value, $payload['source_identity'][$key])) {
                return false;
            }
        }

        $identity = $payload['identity'];
        $user = (array) $connection->table('users')->where('id', $identity['id'])->first();
        if ($user === [] || ! $user['is_active'] || ! $user['feature_preview_allowed']
            || (int) $user['school_id'] !== (int) $identity['school_id']
            || $this->email($user['email']) !== $this->email($identity['email'])
            || ! is_string($user['created_at']) || $user['created_at'] !== $identity['created_at']
            || ! hash_equals(FeaturePreviewService::authenticationFingerprintFromAttributes($user), $payload['auth_fingerprint'])) {
            return false;
        }

        $tables = config('permission.table_names');
        $modelType = (new User)->getMorphClass();
        $roles = $connection->table($tables['roles'].' as r')
            ->join($tables['model_has_roles'].' as mr', 'mr.role_id', '=', 'r.id')
            ->where('mr.model_type', $modelType)->where('mr.model_id', $user['id'])
            ->select(['r.id', 'r.name', 'r.guard_name', 'r.is_admin'])->get();
        $adminRoles = app(AccessScopeService::class)->roleNamesForScope('admin_shell_access');
        $hasAdminShell = $roles->contains(fn ($role): bool => (bool) $role->is_admin || in_array($role->name, $adminRoles, true));
        if ($user['confirmed_at'] === null && ($hasAdminShell || $user['email_verified_at'] === null)) {
            return false;
        }

        $liveRoles = $roles->map(fn ($role): array => [
            'name' => $role->name, 'guard_name' => $role->guard_name, 'is_admin' => (bool) $role->is_admin,
        ])->all();
        foreach ($payload['roles'] as $role) {
            $role['is_admin'] = (bool) $role['is_admin'];
            if (! in_array($role, $liveRoles, true)) {
                return false;
            }
        }

        $permissions = $connection->table($tables['permissions'].' as p')
            ->where(function (Builder $query) use ($tables, $roles, $modelType, $user): void {
                $query->whereIn('p.id', function (Builder $query) use ($tables, $roles): void {
                    $query->select('permission_id')->from($tables['role_has_permissions'])->whereIn('role_id', $roles->pluck('id')->all());
                })->orWhereIn('p.id', function (Builder $query) use ($tables, $modelType, $user): void {
                    $query->select('permission_id')->from($tables['model_has_permissions'])
                        ->where('model_type', $modelType)->where('model_id', $user['id']);
                });
            })->get(['p.name', 'p.guard_name'])->map(fn ($permission): array => (array) $permission)->all();
        foreach ($payload['permissions'] as $permission) {
            if (! in_array($permission, $permissions, true)) {
                return false;
            }
        }

        if ($operation === 'admission') {
            return true;
        }

        $recipient = $this->email($payload['recipient']);

        return match ($payload['recipient_kind']) {
            'account' => $recipient !== '' && $recipient === $this->email($user['email']),
            'second_factor' => (bool) ($user['is_2fa'] ?? false) && ($user['email_2fa_verified_at'] ?? null) !== null
                && $recipient !== '' && $recipient === $this->email($user['email_2fa'] ?? ''),
            default => $this->parentAllowed($connection, $user, $roles->pluck('name')->all(), $recipient, $payload['recipient_kind'], isset($payload['schoolyear_id']) ? (int) $payload['schoolyear_id'] : null),
        };
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  list<string>  $roles
     */
    private function parentAllowed(Connection $connection, array $user, array $roles, string $email, string $kind, ?int $schoolyearId): bool
    {
        if ($email === '' || ! in_array($kind === 'teaching_parent' ? 'student' : 'lunch_user', $roles, true)) {
            return false;
        }

        $imports = $connection->table('import116 as i')->where('i.school_id', $user['school_id'])
            ->where(function (Builder $query) use ($user): void {
                $query->where('i.user_id', $user['id'])->orWhere(function (Builder $query) use ($user): void {
                    $query->whereNull('i.user_id')->where(function (Builder $query) use ($user): void {
                        $query->whereRaw('LOWER(TRIM(i.email)) = ?', [$this->email($user['email'])]);
                        if ($user['import116_id'] ?? null) {
                            $query->orWhere('i.id', $user['import116_id']);
                        }
                    });
                });
            })->where(function (Builder $query) use ($email): void {
                $query->whereRaw('LOWER(TRIM(i.mother_email)) = ?', [$email])
                    ->orWhereRaw('LOWER(TRIM(i.father_email)) = ?', [$email]);
            });

        if ($kind === 'teaching_parent') {
            $activeYear = $connection->table('school_tools')->where('school_id', $user['school_id'])->value('active_schoolyear_id');
            if (! $schoolyearId || (int) $activeYear !== $schoolyearId) {
                return false;
            }
            $imports->where('i.schoolyear_id', $schoolyearId)->whereNotNull('i.exists_date')
                ->whereBetween('i.birth_date', [today()->subYearsNoOverflow(18)->addDay()->toDateString(), today()->toDateString()])
                ->whereExists(function (Builder $query) use ($schoolyearId, $user): void {
                    $query->selectRaw('1')->from('teaching_course_students as cs')
                        ->join('teaching_courses as c', 'c.id', '=', 'cs.teaching_course_id')
                        ->whereNull('cs.deleted_at')->whereNull('cs.canceled_at')
                        ->where('c.school_id', $user['school_id'])->where('c.schoolyear_id', $schoolyearId)
                        ->where(function (Builder $query): void {
                            $query->whereColumn('cs.import116_id', 'i.id')->orWhereColumn('cs.user_id', 'i.user_id')
                                ->orWhereIn('cs.user_id', function (Builder $query): void {
                                    $query->select('u.id')->from('users as u')->whereColumn('u.import116_id', 'i.id');
                                });
                        });
                });
        }

        return $imports->exists();
    }

    private function email(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
