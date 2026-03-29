<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolTool;
use Illuminate\Support\Facades\Schema;

class SchoolToolModuleStatusService
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const TEST_MODUS = 'test_modus';

    public const COMMING_SOON = 'comming_soon';

    /**
     * @var array<int, string>
     */
    private const MODULE_KEYS = [
        'register',
        'tutoring',
        'teaching',
        'materials',
        'restaurant',
    ];

    /**
     * @return array<string, bool>
     */
    public function defaultAttributes(): array
    {
        $defaults = [];

        foreach (self::MODULE_KEYS as $moduleKey) {
            $isActive = (bool) config(sprintf('schooltool.%s_active', $moduleKey), false);

            $defaults[$this->adminVisibleField($moduleKey)] = $isActive;
            $defaults[$this->userVisibleField($moduleKey)] = $isActive;
            $defaults[$this->userTestModeField($moduleKey)] = false;
            $defaults[$this->userComingSoonField($moduleKey)] = false;
        }

        $globalTool = $this->globalSchoolTool();
        if (! $globalTool) {
            return $this->appendLegacyStatusFields($defaults);
        }

        return $this->appendLegacyStatusFields(array_merge($defaults, $this->normalizeAttributesFromRecord($globalTool)));
    }

    /**
     * @return array<int, string>
     */
    public function moduleVisibilityFields(): array
    {
        $fields = [];

        foreach (self::MODULE_KEYS as $moduleKey) {
            $fields[] = $this->adminVisibleField($moduleKey);
            $fields[] = $this->userVisibleField($moduleKey);
            $fields[] = $this->userTestModeField($moduleKey);
            $fields[] = $this->userComingSoonField($moduleKey);
        }

        return $fields;
    }

    public function adminVisibleForModule(string $moduleKey, ?School $school = null): bool
    {
        $attributes = $this->moduleAttributes($moduleKey, $school);

        return (bool) $attributes[$this->adminVisibleField($moduleKey)];
    }

    public function userStatusForModule(string $moduleKey, ?School $school = null): string
    {
        $attributes = $this->moduleAttributes($moduleKey, $school);

        if ((bool) $attributes[$this->userVisibleField($moduleKey)]) {
            return self::ACTIVE;
        }

        if ((bool) $attributes[$this->userTestModeField($moduleKey)]) {
            return self::TEST_MODUS;
        }

        if ((bool) $attributes[$this->userComingSoonField($moduleKey)]) {
            return self::COMMING_SOON;
        }

        return self::INACTIVE;
    }

    public function statusForModule(string $moduleKey, ?School $school = null): string
    {
        return $this->userStatusForModule($moduleKey, $school);
    }

    public function statusForSchool(string $moduleKey, ?School $school = null): string
    {
        return $this->userStatusForModule($moduleKey, $school);
    }

    public function allowsUserAccess(string $status): bool
    {
        return in_array($status, [self::ACTIVE, self::TEST_MODUS], true);
    }

    public function allowsAccess(string $status): bool
    {
        return $this->allowsUserAccess($status);
    }

    public function isUserVisible(string $status): bool
    {
        return $status !== self::INACTIVE;
    }

    public function isVisible(string $status): bool
    {
        return $this->isUserVisible($status);
    }

    public function moduleKeyForLicence(string $licenceName): ?string
    {
        return match ($licenceName) {
            'Anmeldetool' => 'register',
            'Nachhilfetool' => 'tutoring',
            'Lehrertool' => 'teaching',
            'Materialientool' => 'materials',
            'Restaurant' => 'restaurant',
            default => null,
        };
    }

    /**
     * @return array<string, bool>
     */
    private function moduleAttributes(string $moduleKey, ?School $school = null): array
    {
        if (! in_array($moduleKey, self::MODULE_KEYS, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown module key [%s].', $moduleKey));
        }

        $attributes = $this->defaultAttributes();

        return [
            $this->adminVisibleField($moduleKey) => (bool) $attributes[$this->adminVisibleField($moduleKey)],
            $this->userVisibleField($moduleKey) => (bool) $attributes[$this->userVisibleField($moduleKey)],
            $this->userTestModeField($moduleKey) => (bool) $attributes[$this->userTestModeField($moduleKey)],
            $this->userComingSoonField($moduleKey) => (bool) $attributes[$this->userComingSoonField($moduleKey)],
        ];
    }

    private function adminVisibleField(string $moduleKey): string
    {
        return sprintf('%s_visible_admin', $moduleKey);
    }

    private function userVisibleField(string $moduleKey): string
    {
        return sprintf('%s_visible_user', $moduleKey);
    }

    private function userTestModeField(string $moduleKey): string
    {
        return sprintf('%s_user_test_mode', $moduleKey);
    }

    private function userComingSoonField(string $moduleKey): string
    {
        return sprintf('%s_user_comming_soon', $moduleKey);
    }

    private function globalSchoolTool(): ?SchoolTool
    {
        if (! Schema::hasTable('school_tools')) {
            return null;
        }

        return SchoolTool::query()->orderBy('id')->first();
    }

    /**
     * @return array<string, bool>
     */
    private function normalizeAttributesFromRecord(SchoolTool $schoolTool): array
    {
        $attributes = [];

        foreach (self::MODULE_KEYS as $moduleKey) {
            $legacyStatusField = sprintf('%s_status', $moduleKey);
            $adminVisibleField = $this->adminVisibleField($moduleKey);
            $userVisibleField = $this->userVisibleField($moduleKey);
            $userTestModeField = $this->userTestModeField($moduleKey);
            $userComingSoonField = $this->userComingSoonField($moduleKey);

            if (
                Schema::hasColumn('school_tools', $adminVisibleField)
                && Schema::hasColumn('school_tools', $userVisibleField)
                && Schema::hasColumn('school_tools', $userTestModeField)
                && Schema::hasColumn('school_tools', $userComingSoonField)
            ) {
                $attributes[$adminVisibleField] = (bool) $schoolTool->{$adminVisibleField};
                $attributes[$userVisibleField] = (bool) $schoolTool->{$userVisibleField};
                $attributes[$userTestModeField] = (bool) $schoolTool->{$userTestModeField};
                $attributes[$userComingSoonField] = (bool) $schoolTool->{$userComingSoonField};

                continue;
            }

            $legacyStatus = Schema::hasColumn('school_tools', $legacyStatusField)
                ? (string) ($schoolTool->{$legacyStatusField} ?? self::INACTIVE)
                : self::INACTIVE;

            $attributes = array_merge($attributes, $this->legacyStatusAttributes($moduleKey, $legacyStatus));
        }

        return $attributes;
    }

    /**
     * @return array<string, bool>
     */
    private function legacyStatusAttributes(string $moduleKey, string $legacyStatus): array
    {
        return match ($legacyStatus) {
            self::ACTIVE => [
                $this->adminVisibleField($moduleKey) => true,
                $this->userVisibleField($moduleKey) => true,
                $this->userTestModeField($moduleKey) => false,
                $this->userComingSoonField($moduleKey) => false,
            ],
            self::TEST_MODUS => [
                $this->adminVisibleField($moduleKey) => true,
                $this->userVisibleField($moduleKey) => false,
                $this->userTestModeField($moduleKey) => true,
                $this->userComingSoonField($moduleKey) => false,
            ],
            self::COMMING_SOON => [
                $this->adminVisibleField($moduleKey) => true,
                $this->userVisibleField($moduleKey) => false,
                $this->userTestModeField($moduleKey) => false,
                $this->userComingSoonField($moduleKey) => true,
            ],
            default => [
                $this->adminVisibleField($moduleKey) => false,
                $this->userVisibleField($moduleKey) => false,
                $this->userTestModeField($moduleKey) => false,
                $this->userComingSoonField($moduleKey) => false,
            ],
        };
    }

    /**
     * @param  array<string, bool>  $attributes
     * @return array<string, bool|string>
     */
    private function appendLegacyStatusFields(array $attributes): array
    {
        foreach (self::MODULE_KEYS as $moduleKey) {
            $attributes[sprintf('%s_status', $moduleKey)] = $this->statusFromAttributes($moduleKey, $attributes);
        }

        return $attributes;
    }

    /**
     * @param  array<string, bool|string>  $attributes
     */
    private function statusFromAttributes(string $moduleKey, array $attributes): string
    {
        if ((bool) ($attributes[$this->userVisibleField($moduleKey)] ?? false)) {
            return self::ACTIVE;
        }

        if ((bool) ($attributes[$this->userTestModeField($moduleKey)] ?? false)) {
            return self::TEST_MODUS;
        }

        if ((bool) ($attributes[$this->userComingSoonField($moduleKey)] ?? false)) {
            return self::COMMING_SOON;
        }

        return self::INACTIVE;
    }
}
