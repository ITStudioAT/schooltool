<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;

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
        'aba',
        'students_timetables',
    ];

    /**
     * @var array<string, bool>
     */
    private const MODULE_DEFAULT_VISIBILITY = [
        'register' => true,
        'tutoring' => false,
        'teaching' => false,
        'materials' => false,
        'restaurant' => false,
        'aba' => true,
        'students_timetables' => false,
    ];

    /**
     * @var array<string, string>
     */
    private const MODULE_FALLBACK_META = [
        'register' => 'Lizenz aus Tabelle',
        'tutoring' => 'Lizenz aus Tabelle',
        'teaching' => 'Lizenz aus Tabelle',
        'materials' => 'Lizenz aus Tabelle',
        'restaurant' => 'Lizenz aus Tabelle',
        'aba' => 'Lizenz aus Tabelle',
        'students_timetables' => 'Lizenz aus Tabelle',
    ];

    /**
     * @var array<string, bool|string>|null
     */
    private ?array $defaultAttributesCache = null;

    private ?SchoolTool $globalSchoolToolCache = null;

    public static function moduleEnabledByDefault(string $moduleKey): bool
    {
        return self::MODULE_DEFAULT_VISIBILITY[$moduleKey] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function defaultAttributes(): array
    {
        if ($this->defaultAttributesCache !== null) {
            return $this->defaultAttributesCache;
        }

        $defaults = [];

        foreach (self::MODULE_KEYS as $moduleKey) {
            $isActive = self::moduleEnabledByDefault($moduleKey);

            $defaults[$this->adminVisibleField($moduleKey)] = $isActive;
            $defaults[$this->userVisibleField($moduleKey)] = $isActive;
            $defaults[$this->userTestModeField($moduleKey)] = false;
            $defaults[$this->userComingSoonField($moduleKey)] = false;
        }

        $globalTool = $this->globalSchoolTool();
        if (! $globalTool) {
            return $this->defaultAttributesCache = $this->appendLegacyStatusFields($defaults);
        }

        return $this->defaultAttributesCache = $this->appendLegacyStatusFields(array_merge($defaults, $this->normalizeAttributesFromRecord($globalTool)));
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

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function existingSchoolToolAttributes(array $attributes): array
    {
        $fillableAttributes = (new SchoolTool)->getFillable();

        return collect($attributes)
            ->filter(fn (mixed $value, string $key): bool => in_array($key, $fillableAttributes, true))
            ->all();
    }

    /**
     * @return array<int, array{
     *     key:string,
     *     label:string,
     *     meta:string,
     *     licence_id:int,
     *     licence_name:string,
     *     adminVisibleField:string,
     *     userVisibleField:string,
     *     userTestModeField:string,
     *     userComingSoonField:string
     * }>
     */
    public function configurableModuleRows(): array
    {
        return Licence::query()
            ->select(['id', 'name', 'long_name'])
            ->orderByRaw('COALESCE(long_name, name)')
            ->get()
            ->map(fn (Licence $licence): ?array => $this->mapLicenceToModuleRow($licence))
            ->filter()
            ->values()
            ->all();
    }

    public function adminVisibleForModule(string $moduleKey, ?School $school = null): bool
    {
        $attributes = $this->moduleAttributes($moduleKey, $school);

        return (bool) $attributes[$this->adminVisibleField($moduleKey)];
    }

    public function userVisibleForModule(string $moduleKey, ?School $school = null): bool
    {
        $attributes = $this->moduleAttributes($moduleKey, $school);

        return (bool) $attributes[$this->userVisibleField($moduleKey)];
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
            'ABA', 'Auswerten von ABAs' => 'aba',
            'StudentsTimetables' => 'students_timetables',
            default => null,
        };
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     meta:string,
     *     licence_id:int,
     *     licence_name:string,
     *     adminVisibleField:string,
     *     userVisibleField:string,
     *     userTestModeField:string,
     *     userComingSoonField:string
     * }|null
     */
    private function mapLicenceToModuleRow(Licence $licence): ?array
    {
        $moduleKey = $this->moduleKeyForLicence((string) ($licence->name ?? ''))
            ?? $this->moduleKeyForLicence((string) ($licence->long_name ?? ''));
        if ($moduleKey === null) {
            return null;
        }

        $adminVisibleField = $this->adminVisibleField($moduleKey);
        $userVisibleField = $this->userVisibleField($moduleKey);
        $userTestModeField = $this->userTestModeField($moduleKey);
        $userComingSoonField = $this->userComingSoonField($moduleKey);

        $label = trim((string) ($licence->long_name ?: $licence->name));
        $licenceName = trim((string) ($licence->name ?? ''));
        $meta = $licenceName !== '' && $licenceName !== $label
            ? $licenceName
            : (self::MODULE_FALLBACK_META[$moduleKey] ?? 'Lizenz aus Tabelle');

        return [
            'key' => $moduleKey,
            'label' => $label,
            'meta' => $meta,
            'licence_id' => (int) $licence->id,
            'licence_name' => $licenceName,
            'adminVisibleField' => $adminVisibleField,
            'userVisibleField' => $userVisibleField,
            'userTestModeField' => $userTestModeField,
            'userComingSoonField' => $userComingSoonField,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function moduleAttributes(string $moduleKey, ?School $school = null): array
    {
        if (! in_array($moduleKey, self::MODULE_KEYS, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown module key [%s].', $moduleKey));
        }

        $attributes = $this->schoolAwareAttributes($school);

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

    /**
     * @return array<string, bool|string>
     */
    private function schoolAwareAttributes(?School $school = null): array
    {
        if ($school) {
            $school->loadMissing('schoolTool');

            if ($school->schoolTool) {
                return $this->appendLegacyStatusFields(
                    array_merge($this->defaultAttributes(), $this->normalizeAttributesFromRecord($school->schoolTool))
                );
            }
        }

        return $this->defaultAttributes();
    }

    private function globalSchoolTool(): ?SchoolTool
    {
        if ($this->globalSchoolToolCache !== null) {
            return $this->globalSchoolToolCache;
        }

        return $this->globalSchoolToolCache = SchoolTool::query()->orderBy('id')->first();
    }

    /**
     * @return array<string, bool>
     */
    private function normalizeAttributesFromRecord(SchoolTool $schoolTool): array
    {
        $attributes = [];
        $recordAttributes = $schoolTool->getAttributes();

        foreach (self::MODULE_KEYS as $moduleKey) {
            $legacyStatusField = sprintf('%s_status', $moduleKey);
            $adminVisibleField = $this->adminVisibleField($moduleKey);
            $userVisibleField = $this->userVisibleField($moduleKey);
            $userTestModeField = $this->userTestModeField($moduleKey);
            $userComingSoonField = $this->userComingSoonField($moduleKey);

            if (
                array_key_exists($adminVisibleField, $recordAttributes)
                && array_key_exists($userVisibleField, $recordAttributes)
                && array_key_exists($userTestModeField, $recordAttributes)
                && array_key_exists($userComingSoonField, $recordAttributes)
            ) {
                $attributes[$adminVisibleField] = (bool) $schoolTool->{$adminVisibleField};
                $attributes[$userVisibleField] = (bool) $schoolTool->{$userVisibleField};
                $attributes[$userTestModeField] = (bool) $schoolTool->{$userTestModeField};
                $attributes[$userComingSoonField] = (bool) $schoolTool->{$userComingSoonField};

                continue;
            }

            $legacyStatus = array_key_exists($legacyStatusField, $recordAttributes)
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
