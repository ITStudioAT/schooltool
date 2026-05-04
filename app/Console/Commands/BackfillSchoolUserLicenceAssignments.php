<?php

namespace App\Console\Commands;

use App\Models\SchoolLicence;
use App\Models\User;
use App\Services\LicenceService;
use App\Services\SchoolUserLicenceAssignmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('schooltool:backfill-school-user-licences {--dry-run : Count records without writing them} {--school-id= : Limit to one school id}')]
#[Description('Backfill role-level school_user_licences from legacy school_licences.user_licence_assignments without deleting legacy JSON.')]
class BackfillSchoolUserLicenceAssignments extends Command
{
    public function handle(
        SchoolUserLicenceAssignmentService $assignmentService,
        LicenceService $licenceService
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $schoolId = $this->option('school-id');
        $schoolId = is_numeric($schoolId) ? (int) $schoolId : null;

        if (! $assignmentService->supportsRoleAssignments()) {
            $this->error('school_user_licences.role_name fehlt. Bitte zuerst die Migration ausführen.');

            return self::FAILURE;
        }

        $totals = [
            'school_licences' => 0,
            'users' => 0,
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
        ];

        SchoolLicence::query()
            ->with('licence')
            ->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId))
            ->whereNotNull('user_licence_assignments')
            ->orderBy('id')
            ->chunkById(100, function ($schoolLicences) use ($assignmentService, $licenceService, $dryRun, &$totals): void {
                foreach ($schoolLicences as $schoolLicence) {
                    if (! $schoolLicence instanceof SchoolLicence) {
                        continue;
                    }

                    $assignments = is_array($schoolLicence->user_licence_assignments)
                        ? $schoolLicence->user_licence_assignments
                        : [];
                    if ($assignments === []) {
                        continue;
                    }

                    $totals['school_licences']++;
                    $licenceModel = $this->mergedSchoolLicenceUserLicenceModel($schoolLicence, $licenceService);
                    $users = User::query()
                        ->with('roles')
                        ->whereIn('id', collect(array_keys($assignments))->map(fn ($id) => (int) $id)->filter()->values()->all())
                        ->get()
                        ->keyBy('id');

                    foreach ($assignments as $userId => $userAssignments) {
                        $user = $users->get((int) $userId);
                        if (! $user instanceof User || ! is_array($userAssignments)) {
                            continue;
                        }

                        $totals['users']++;
                        if ($dryRun) {
                            $totals['created'] += collect($userAssignments)->filter(fn ($entry, $roleName) => is_string($roleName) && trim($roleName) !== '')->count();

                            continue;
                        }

                        $result = $assignmentService->persistUserAssignments(
                            $schoolLicence,
                            $user,
                            $userAssignments,
                            $licenceModel,
                            $schoolLicence->licence,
                            deleteMissing: false
                        );

                        $totals['created'] += $result['created'];
                        $totals['updated'] += $result['updated'];
                        $totals['deleted'] += $result['deleted'];
                    }
                }
            });

        $this->components->info(sprintf(
            '%s: %d Schul-Lizenzen, %d Benutzer, %d erstellt/geprüft, %d aktualisiert, %d gelöscht.',
            $dryRun ? 'Dry-run' : 'Backfill abgeschlossen',
            $totals['school_licences'],
            $totals['users'],
            $totals['created'],
            $totals['updated'],
            $totals['deleted']
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedSchoolLicenceUserLicenceModel(SchoolLicence $schoolLicence, LicenceService $service): array
    {
        $schoolModel = $service->normalizeLicenceModel($schoolLicence->licence_model);
        $baseModel = $service->normalizeLicenceModel($schoolLicence->licence?->licence_model);
        $baseStructuredConfiguration = $schoolLicence->licence
            ? $service->editableLicenceConfiguration($schoolLicence->licence)
            : [];
        $baseStructuredRoleNames = collect(array_merge(
            (bool) ($baseStructuredConfiguration['admin_licence_enabled'] ?? false)
                ? (is_array($baseStructuredConfiguration['admin_role_names'] ?? null) ? $baseStructuredConfiguration['admin_role_names'] : [])
                : [],
            (bool) ($baseStructuredConfiguration['user_licence_enabled'] ?? false)
                ? (is_array($baseStructuredConfiguration['user_role_names'] ?? null) ? $baseStructuredConfiguration['user_role_names'] : [])
                : []
        ))
            ->map(fn ($role) => is_string($role) ? trim($role) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $affectedRoles = collect(array_merge(
            is_array($schoolModel['affected_roles'] ?? null) ? $schoolModel['affected_roles'] : [],
            is_array($baseModel['affected_roles'] ?? null) ? $baseModel['affected_roles'] : [],
            array_keys(is_array($schoolModel['user_licence_required_by_role'] ?? null) ? $schoolModel['user_licence_required_by_role'] : []),
            array_keys(is_array($baseModel['user_licence_required_by_role'] ?? null) ? $baseModel['user_licence_required_by_role'] : []),
            $baseStructuredRoleNames
        ))
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $requiredByRole = [];
        foreach ($affectedRoles as $roleName) {
            $requiredByRole[$roleName] = (bool) (
                $schoolModel['user_licence_required_by_role'][$roleName]
                ?? $baseModel['user_licence_required_by_role'][$roleName]
                ?? in_array($roleName, $baseStructuredRoleNames, true)
            );
        }

        return [
            'school_licence_required' => (bool) ($schoolModel['school_licence_required'] ?? true),
            'affected_roles' => $affectedRoles,
            'user_licence_required_by_role' => $requiredByRole,
        ];
    }
}
