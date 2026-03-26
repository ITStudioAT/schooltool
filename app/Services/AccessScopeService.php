<?php

namespace App\Services;

class AccessScopeService
{
    public const SCOPE_PREFIX = 'scope:';

    /**
     * @var array<string, array<int, string>>
     */
    private const SCOPES = [
        'admin_access' => ['admin'],
        'super_admin_access' => ['super_admin'],
        'admin_or_super_admin_access' => ['admin', 'super_admin'],
        'aba_teacher_access' => ['aba_teacher'],
        'admin_user_profile_access' => ['user', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'teacher'],
        'teaching_upload_access' => ['admin', 'teaching_admin'],
        'tutoring_user_access' => ['tutoring_user'],
        'school_tool_access' => ['admin', 'tutoring_admin', 'register_admin', 'teacher'],
        'materials_access' => ['admin', 'materials_admin', 'materials_moderator'],
        'tutoring_admin_access' => ['admin', 'tutoring_admin'],
        'teaching_access' => ['admin', 'teaching_admin', 'teacher'],
        'staff_admin_access' => ['admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher'],
        'tool_web_access' => ['admin', 'register_admin', 'tutoring_admin', 'teacher', 'lunch_admin'],
        'admin_shell_access' => ['admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin', 'aba_teacher'],
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public function all(): array
    {
        return self::SCOPES;
    }

    /**
     * @return array<int, string>
     */
    public function roleNamesForScope(string $scopeName): array
    {
        if (! array_key_exists($scopeName, self::SCOPES)) {
            throw new \InvalidArgumentException(sprintf('Unknown access scope [%s].', $scopeName));
        }

        return self::SCOPES[$scopeName];
    }

    public function isScopeReference(string $value): bool
    {
        return str_starts_with($value, self::SCOPE_PREFIX);
    }

    public function extractScopeName(string $value): ?string
    {
        if (! $this->isScopeReference($value)) {
            return null;
        }

        $scopeName = trim(substr($value, strlen(self::SCOPE_PREFIX)));

        return $scopeName !== '' ? $scopeName : null;
    }

    /**
     * @param  array<int, string>|string|null  $roleNamesOrScopes
     * @return array<int, string>
     */
    public function resolveRoleNames(array|string|null $roleNamesOrScopes): array
    {
        $items = is_array($roleNamesOrScopes) ? $roleNamesOrScopes : [$roleNamesOrScopes];
        $resolvedRoleNames = [];

        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $scopeName = $this->extractScopeName($item);
            $roleNames = $scopeName !== null ? $this->roleNamesForScope($scopeName) : [$item];

            foreach ($roleNames as $roleName) {
                $roleName = trim($roleName);
                if ($roleName === '') {
                    continue;
                }

                $resolvedRoleNames[$roleName] = $roleName;
            }
        }

        return array_values($resolvedRoleNames);
    }
}
