<?php

namespace App\Http\Resources\Admin;

use App\Models\SchoolLicence;
use App\Models\SchoolUserLicence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class SchoolResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $schoolLicenceRows = $this->relationLoaded('schoolLicences')
            ? $this->schoolLicences->keyBy('licence_id')
            : collect();

        $userLicenceAssignments = $this->relationLoaded('schoolUserLicences')
            ? $this->schoolUserLicences->groupBy('licence_id')
            : collect();

        return [
            'id' => $this->id,
            'long_name' => $this->long_name,
            'short_name' => $this->short_name,
            'logo' => $this->logo,
            'color' => $this->color,
            'email' => $this->email,
            'licences' => $this->relationLoaded('licences')
                ? $this->licences->map(function ($licence) use ($schoolLicenceRows, $userLicenceAssignments) {
                    $schoolLicence = $schoolLicenceRows->get($licence->id);
                    $licenceModel = $this->decodeLicenceModel($schoolLicence?->licence_model ?? $licence->pivot?->licence_model);
                    $schoolLicenceRequired = true;
                    if (is_array($licenceModel) && array_key_exists('school_licence_required', $licenceModel)) {
                        $schoolLicenceRequired = (bool) $licenceModel['school_licence_required'];
                    }

                    $adminSummary = $this->assignedUserSummaryForType(
                        $licence,
                        $schoolLicence,
                        $licenceModel,
                        $userLicenceAssignments->get($licence->id, collect()),
                        'admin',
                        $schoolLicenceRequired
                    );

                    $userSummary = $this->assignedUserSummaryForType(
                        $licence,
                        $schoolLicence,
                        $licenceModel,
                        $userLicenceAssignments->get($licence->id, collect()),
                        'user',
                        $schoolLicenceRequired
                    );

                    return [
                        'id' => $licence->id,
                        'name' => $licence->name,
                        'long_name' => $licence->long_name,
                        'valid_until' => $licence->pivot?->valid_until,
                        'school_licence_id' => $licence->pivot?->id,
                        'school_licence_required' => $schoolLicenceRequired,
                        'licence_model' => $licenceModel,
                        'school_licence_enabled' => (bool) $licence->school_licence_enabled,
                        'charged_school_price' => $schoolLicence?->charged_school_price,
                        'extra_storage_units' => $schoolLicence?->extra_storage_units,
                        'extra_storage_unit_price' => $schoolLicence?->extra_storage_unit_price,
                        'start_day_month' => $this->formatDayMonth($licence->start_day_month),
                        'end_day_month' => $this->formatDayMonth($licence->end_day_month),
                        'school_price_per_year' => $licence->school_price_per_year,
                        'school_included_storage_gb' => $licence->school_included_storage_gb,
                        'school_extra_storage_step_gb' => $licence->school_extra_storage_step_gb,
                        'school_extra_storage_step_price' => $licence->school_extra_storage_step_price,
                        'admin_licence_enabled' => (bool) $licence->admin_licence_enabled,
                        'admin_price_per_year' => $licence->admin_price_per_year,
                        'admin_role_names' => is_array($licence->admin_role_names) ? array_values($licence->admin_role_names) : [],
                        'admin_included_storage_gb' => $licence->admin_included_storage_gb,
                        'admin_extra_storage_step_gb' => $licence->admin_extra_storage_step_gb,
                        'admin_extra_storage_step_price' => $licence->admin_extra_storage_step_price,
                        'charged_admin_price' => $schoolLicence?->charged_admin_price,
                        'admin_extra_storage_units' => $schoolLicence?->admin_extra_storage_units,
                        'admin_extra_storage_unit_price' => $schoolLicence?->admin_extra_storage_unit_price,
                        'admin_licence_count' => $adminSummary['total'],
                        'admin_licence_active_count' => $adminSummary['active'],
                        'admin_licence_expired_count' => $adminSummary['expired'],
                        'user_licence_enabled' => (bool) $licence->user_licence_enabled,
                        'user_price_per_year' => $licence->user_price_per_year,
                        'user_role_names' => is_array($licence->user_role_names) ? array_values($licence->user_role_names) : [],
                        'user_included_storage_gb' => $licence->user_included_storage_gb,
                        'user_extra_storage_step_gb' => $licence->user_extra_storage_step_gb,
                        'user_extra_storage_step_price' => $licence->user_extra_storage_step_price,
                        'charged_user_price' => $schoolLicence?->charged_user_price,
                        'user_extra_storage_units' => $schoolLicence?->user_extra_storage_units,
                        'user_extra_storage_unit_price' => $schoolLicence?->user_extra_storage_unit_price,
                        'user_licence_count' => $userSummary['total'],
                        'user_licence_active_count' => $userSummary['active'],
                        'user_licence_expired_count' => $userSummary['expired'],
                    ];
                })->values()
                : [],
            'is_selectable' => $this->is_selectable ? true : false,
        ];
    }

    private function formatDayMonth(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', trim($value), $matches) === 1) {
            return sprintf('%s.%s.', $matches[2], $matches[1]);
        }

        return $value;
    }

    private function decodeLicenceModel($licenceModel): ?array
    {
        if (is_array($licenceModel)) {
            return $licenceModel;
        }

        if (! is_string($licenceModel) || trim($licenceModel) === '') {
            return null;
        }

        $decoded = json_decode($licenceModel, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array{total:int, active:int, expired:int}
     */
    private function assignedUserSummaryForType(
        $licence,
        ?SchoolLicence $schoolLicence,
        ?array $licenceModel,
        Collection $storedAssignments,
        string $assignmentType,
        bool $schoolLicenceRequired
    ): array {
        $statusesByUser = $storedAssignments
            ->filter(fn (SchoolUserLicence $assignment) => $assignment->assignment_type === $assignmentType)
            ->filter(fn (SchoolUserLicence $assignment) => is_numeric($assignment->user_id) && (int) $assignment->user_id > 0)
            ->groupBy(fn (SchoolUserLicence $assignment) => (int) $assignment->user_id)
            ->map(function (Collection $assignments) use ($schoolLicence, $schoolLicenceRequired): string {
                foreach ($assignments as $assignment) {
                    if (! $assignment instanceof SchoolUserLicence) {
                        continue;
                    }

                    if (! $assignment->is_active) {
                        continue;
                    }

                    if ($this->isAssignmentActive(
                        $schoolLicenceRequired,
                        $schoolLicence?->valid_until,
                        $assignment->valid_until?->format('Y-m-d') ?? null,
                        true
                    )) {
                        return 'active';
                    }
                }

                return 'expired';
            })
            ->all();

        $roleNames = $this->roleNamesForAssignmentType($licence, $licenceModel, $assignmentType);
        $payloadAssignments = is_array($schoolLicence?->user_licence_assignments)
            ? $schoolLicence->user_licence_assignments
            : [];

        if ($roleNames !== []) {
            foreach ($payloadAssignments as $userId => $userAssignments) {
                if (! is_array($userAssignments) || ! is_numeric($userId) || (int) $userId <= 0) {
                    continue;
                }

                $hasRelevantRoleAssignment = false;
                $resolvedStatus = 'expired';
                foreach ($roleNames as $roleName) {
                    if (! array_key_exists($roleName, $userAssignments)) {
                        continue;
                    }

                    $hasRelevantRoleAssignment = true;
                    $entry = $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null);
                    $isActivated = $assignmentType === 'user'
                        ? ($entry['is_activated'] || $entry['valid_until'] !== null)
                        : true;

                    if ($this->isAssignmentActive(
                        $schoolLicenceRequired,
                        $schoolLicence?->valid_until,
                        $entry['valid_until'],
                        $isActivated
                    )) {
                        $resolvedStatus = 'active';
                        break;
                    }
                }

                if (! $hasRelevantRoleAssignment) {
                    continue;
                }

                $existingStatus = $statusesByUser[(int) $userId] ?? null;
                if ($existingStatus !== 'active') {
                    $statusesByUser[(int) $userId] = $resolvedStatus;
                }
            }
        }

        $total = count($statusesByUser);
        $active = collect($statusesByUser)->filter(fn (string $status) => $status === 'active')->count();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $total - $active,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function roleNamesForAssignmentType($licence, ?array $licenceModel, string $assignmentType): array
    {
        if ($assignmentType === 'admin') {
            $configuredRoleNames = $this->normalizeRoleNames(
                is_array($licenceModel['admin_role_names'] ?? null)
                    ? $licenceModel['admin_role_names']
                    : ($licence->admin_role_names ?? [])
            );

            if ($configuredRoleNames !== []) {
                return $configuredRoleNames;
            }
        }

        if ($assignmentType === 'user') {
            $configuredRoleNames = $this->normalizeRoleNames(
                is_array($licenceModel['user_role_names'] ?? null)
                    ? $licenceModel['user_role_names']
                    : ($licence->user_role_names ?? [])
            );

            if ($configuredRoleNames !== []) {
                return $configuredRoleNames;
            }
        }

        $requiredByRole = is_array($licenceModel['user_licence_required_by_role'] ?? null)
            ? $licenceModel['user_licence_required_by_role']
            : [];

        return collect($requiredByRole)
            ->filter(fn ($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->filter(fn (string $roleName) => $assignmentType === 'admin'
                ? $this->looksLikeAdminRoleName($roleName)
                : ! $this->looksLikeAdminRoleName($roleName))
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $roleNames
     * @return array<int, string>
     */
    private function normalizeRoleNames($roleNames): array
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

    private function normalizeUserLicenceAssignmentEntry(mixed $entry): array
    {
        if (is_string($entry)) {
            $validUntil = trim($entry);

            return [
                'valid_until' => $validUntil !== '' ? $validUntil : null,
                'is_activated' => false,
            ];
        }

        if (! is_array($entry)) {
            return [
                'valid_until' => null,
                'is_activated' => false,
            ];
        }

        $rawValidUntil = $entry['valid_until'] ?? null;

        return [
            'valid_until' => is_string($rawValidUntil) && trim($rawValidUntil) !== '' ? trim($rawValidUntil) : null,
            'is_activated' => (bool) ($entry['is_activated'] ?? false),
        ];
    }

    private function isAssignmentActive(bool $schoolLicenceRequired, mixed $schoolValidUntil, ?string $userValidUntil, bool $isActivated): bool
    {
        if (! $isActivated) {
            return false;
        }

        if ($schoolLicenceRequired && ! $this->isDateActive($schoolValidUntil)) {
            return false;
        }

        return $this->isDateActive($userValidUntil);
    }

    private function isDateActive(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        $date = is_string($value) ? trim($value) : (string) $value;
        if ($date === '') {
            return true;
        }

        return $date >= now()->toDateString();
    }
}
