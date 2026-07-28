<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\SchoolLicence;
use App\Models\SchoolUserLicence;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SchoolUserLicenceAssignmentService
{
    private ?bool $supportsRoleAssignmentsCache = null;

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function assignmentsForSchoolLicence(
        SchoolLicence $schoolLicence,
        array $roleNames = [],
        array $userIds = [],
        ?Collection $preloadedRows = null
    ): array {
        $assignments = is_array($schoolLicence->user_licence_assignments)
            ? $schoolLicence->user_licence_assignments
            : [];

        if (! $this->supportsRoleAssignments()) {
            return $assignments;
        }

        $rows = $this->roleAssignmentRows($schoolLicence, $roleNames, $userIds, $preloadedRows);

        foreach ($rows as $row) {
            if (! $row instanceof SchoolUserLicence) {
                continue;
            }

            $userId = (string) $row->user_id;
            $roleName = trim((string) $row->role_name);
            if ($userId === '' || $roleName === '') {
                continue;
            }

            $assignments[$userId] ??= [];
            $assignments[$userId][$roleName] = $this->entryFromRoleAssignment($row);
        }

        return $assignments;
    }

    /**
     * @param  array<string, mixed>  $userAssignments
     * @param  array<string, mixed>  $licenceModel
     * @return array{created:int, updated:int, deleted:int}
     */
    public function persistUserAssignments(
        SchoolLicence $schoolLicence,
        User $user,
        array $userAssignments,
        array $licenceModel,
        ?Licence $licence = null,
        bool $deleteMissing = true
    ): array {
        if (! $this->supportsRoleAssignments()) {
            return ['created' => 0, 'updated' => 0, 'deleted' => 0];
        }

        $roleNames = $this->roleNamesFromLicenceModel($licenceModel, $licence);
        $assignmentRows = collect($userAssignments)
            ->filter(fn (mixed $entry, string|int $roleName): bool => is_string($roleName) && trim($roleName) !== '')
            ->mapWithKeys(function (mixed $entry, string|int $roleName) use ($licenceModel, $licence): array {
                $normalizedRoleName = trim((string) $roleName);

                return [
                    $normalizedRoleName => [
                        'entry' => $this->normalizeAssignmentEntry($entry),
                        'assignment_type' => $this->assignmentTypeForRole($normalizedRoleName, $licenceModel, $licence),
                    ],
                ];
            });

        $deleted = 0;
        if ($deleteMissing && $roleNames !== []) {
            $deleted = SchoolUserLicence::query()
                ->where('school_id', $schoolLicence->school_id)
                ->where('licence_id', $schoolLicence->licence_id)
                ->where('user_id', $user->id)
                ->where('role_name', '<>', '')
                ->whereIn('role_name', $roleNames)
                ->whereNotIn('role_name', $assignmentRows->keys()->all())
                ->delete();
        }

        $created = 0;
        $updated = 0;

        foreach ($assignmentRows as $roleName => $payload) {
            $entry = $payload['entry'];
            $assignmentType = $payload['assignment_type'];
            $basePrice = $this->basePriceForType($assignmentType, $licence);

            $schoolUserLicence = SchoolUserLicence::query()->updateOrCreate(
                [
                    'school_id' => $schoolLicence->school_id,
                    'licence_id' => $schoolLicence->licence_id,
                    'user_id' => $user->id,
                    'assignment_type' => $assignmentType,
                    'role_name' => $roleName,
                ],
                [
                    'valid_from' => null,
                    'valid_until' => $entry['valid_until'],
                    'base_price_per_year' => $basePrice,
                    'charged_price' => $entry['charged_price'],
                    'is_active' => (bool) $entry['is_activated'],
                    'plan_id' => $entry['plan_id'],
                    'extra_storage_units' => $entry['extra_storage_units'],
                    'extra_storage_unit_price' => $entry['extra_storage_unit_price'],
                ]
            );

            $schoolUserLicence->wasRecentlyCreated ? $created++ : $updated++;
        }

        return compact('created', 'updated', 'deleted');
    }

    public function supportsRoleAssignments(): bool
    {
        if ($this->supportsRoleAssignmentsCache !== null) {
            return $this->supportsRoleAssignmentsCache;
        }

        return $this->supportsRoleAssignmentsCache = Schema::hasColumn('school_user_licences', 'role_name');
    }

    /**
     * @return array<int, string>
     */
    public function roleNamesFromLicenceModel(array $licenceModel, ?Licence $licence = null): array
    {
        $roleNames = collect(array_merge(
            is_array($licenceModel['affected_roles'] ?? null) ? $licenceModel['affected_roles'] : [],
            array_keys(is_array($licenceModel['user_licence_required_by_role'] ?? null) ? $licenceModel['user_licence_required_by_role'] : []),
            is_array($licence?->admin_role_names ?? null) ? $licence->admin_role_names : [],
            is_array($licence?->user_role_names ?? null) ? $licence->user_role_names : []
        ));

        return $roleNames
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function assignmentTypeForRole(string $roleName, array $licenceModel, ?Licence $licence = null): string
    {
        $normalizedRoleName = trim($roleName);
        $adminRoleNames = $this->normalizeRoleNames($licence?->admin_role_names ?? []);
        $userRoleNames = $this->normalizeRoleNames($licence?->user_role_names ?? []);

        if (in_array('*', $adminRoleNames, true) || in_array($normalizedRoleName, $adminRoleNames, true)) {
            return 'admin';
        }

        if (in_array('*', $userRoleNames, true) || in_array($normalizedRoleName, $userRoleNames, true)) {
            return 'user';
        }

        return $this->looksLikeAdminRoleName($normalizedRoleName) ? 'admin' : 'user';
    }

    /**
     * @param  array<int, string>  $roleNames
     * @param  array<int, int>  $userIds
     * @return Collection<int, SchoolUserLicence>
     */
    private function roleAssignmentRows(
        SchoolLicence $schoolLicence,
        array $roleNames,
        array $userIds,
        ?Collection $preloadedRows = null
    ): Collection {
        $normalizedRoleNames = $this->normalizeRoleNames($roleNames);
        $normalizedUserIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($preloadedRows !== null) {
            return $preloadedRows
                ->filter(fn (mixed $row): bool => $row instanceof SchoolUserLicence)
                ->filter(fn (SchoolUserLicence $row): bool => (int) $row->school_id === (int) $schoolLicence->school_id)
                ->filter(fn (SchoolUserLicence $row): bool => (int) $row->licence_id === (int) $schoolLicence->licence_id)
                ->filter(fn (SchoolUserLicence $row): bool => trim((string) $row->role_name) !== '')
                ->when(
                    $normalizedRoleNames !== [],
                    fn (Collection $rows): Collection => $rows->filter(
                        fn (SchoolUserLicence $row): bool => in_array((string) $row->role_name, $normalizedRoleNames, true)
                    )
                )
                ->when(
                    $normalizedUserIds !== [],
                    fn (Collection $rows): Collection => $rows->filter(
                        fn (SchoolUserLicence $row): bool => in_array((int) $row->user_id, $normalizedUserIds, true)
                    )
                )
                ->values();
        }

        return SchoolUserLicence::query()
            ->where('school_id', $schoolLicence->school_id)
            ->where('licence_id', $schoolLicence->licence_id)
            ->where('role_name', '<>', '')
            ->when($normalizedRoleNames !== [], fn ($query) => $query->whereIn('role_name', $normalizedRoleNames))
            ->when($normalizedUserIds !== [], fn ($query) => $query->whereIn('user_id', $normalizedUserIds))
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFromRoleAssignment(SchoolUserLicence $assignment): array
    {
        return [
            'valid_until' => $assignment->valid_until?->format('Y-m-d'),
            'is_activated' => (bool) $assignment->is_active,
            'plan_id' => $assignment->plan_id !== null ? (int) $assignment->plan_id : null,
            'charged_price' => $assignment->charged_price !== null ? (float) $assignment->charged_price : null,
            'extra_storage_units' => $assignment->extra_storage_units !== null ? (int) $assignment->extra_storage_units : null,
            'extra_storage_unit_price' => $assignment->extra_storage_unit_price !== null ? (float) $assignment->extra_storage_unit_price : null,
        ];
    }

    /**
     * @return array{valid_until:?string,is_activated:bool,plan_id:?int,charged_price:?float,extra_storage_units:?int,extra_storage_unit_price:?float}
     */
    private function normalizeAssignmentEntry(mixed $entry): array
    {
        if (is_string($entry)) {
            $entry = ['valid_until' => trim($entry)];
        }

        $entry = is_array($entry) ? $entry : [];
        $validUntil = $entry['valid_until'] ?? null;
        $planId = $entry['plan_id'] ?? null;

        return [
            'valid_until' => is_string($validUntil) && trim($validUntil) !== '' ? trim($validUntil) : null,
            'is_activated' => (bool) ($entry['is_activated'] ?? false),
            'plan_id' => is_numeric($planId) && (int) $planId > 0 ? (int) $planId : null,
            'charged_price' => isset($entry['charged_price']) && is_numeric($entry['charged_price']) ? round((float) $entry['charged_price'], 2) : null,
            'extra_storage_units' => isset($entry['extra_storage_units']) && is_numeric($entry['extra_storage_units']) ? (int) $entry['extra_storage_units'] : null,
            'extra_storage_unit_price' => isset($entry['extra_storage_unit_price']) && is_numeric($entry['extra_storage_unit_price']) ? round((float) $entry['extra_storage_unit_price'], 2) : null,
        ];
    }

    private function basePriceForType(string $assignmentType, ?Licence $licence): ?float
    {
        $value = $assignmentType === 'admin'
            ? ($licence?->admin_price_per_year ?? null)
            : ($licence?->user_price_per_year ?? null);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoleNames(mixed $roleNames): array
    {
        return collect(is_array($roleNames) ? $roleNames : [])
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function looksLikeAdminRoleName(string $roleName): bool
    {
        $normalized = mb_strtolower(trim($roleName));

        return str_contains($normalized, 'admin') || $normalized === 'super_admin';
    }
}
