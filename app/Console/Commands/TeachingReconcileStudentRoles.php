<?php

namespace App\Console\Commands;

use App\Models\SchoolTool;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Services\TeachingCourseStudentRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;

class TeachingReconcileStudentRoles extends Command
{
    protected $signature = 'teaching:reconcile-student-roles {--apply} {--expected=}';

    protected $description = 'Preview or add justified student roles for active teaching-course memberships';

    public function handle(TeachingCourseStudentRole $studentRoles): int
    {
        $apply = (bool) $this->option('apply');
        $expected = $this->option('expected');
        if (($apply && (! is_string($expected) || ! preg_match('/\A[a-f0-9]{64}\z/', $expected)))
            || (! $apply && $expected !== null)) {
            $this->error('Für --apply ist der unveränderte Digest aus der Vorschau mit --expected erforderlich.');

            return self::FAILURE;
        }
        if (! $apply) {
            $this->line(json_encode($this->snapshot($studentRoles, false)['report'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        try {
            $report = DB::transaction(function () use ($studentRoles, $expected): array {
                $snapshot = $this->snapshot($studentRoles, true);
                if (! hash_equals($snapshot['report']['digest'], $expected)) {
                    throw new RuntimeException('Der Prüfstand hat sich geändert. Bitte eine neue Vorschau erstellen.');
                }
                if (! $snapshot['report']['student_role_available']) {
                    throw new RuntimeException('Die Rolle student für den Guard web ist nicht vorhanden.');
                }
                $assigned = 0;
                foreach ($snapshot['eligible'] as $membership) {
                    $result = $studentRoles->assign($membership);
                    if ($result['status'] !== 'assigned') {
                        throw new RuntimeException('Eine Zuordnung hat sich geändert. Es wurden keine Rollen übernommen.');
                    }
                    $assigned++;
                }

                return [...$snapshot['report'], 'assigned_accounts' => $assigned];
            }, 3);
        } catch (\Throwable $exception) {
            $safeMessages = ['Der Prüfstand hat sich geändert. Bitte eine neue Vorschau erstellen.',
                'Die Rolle student für den Guard web ist nicht vorhanden.',
                'Eine Zuordnung hat sich geändert. Es wurden keine Rollen übernommen.'];
            $this->error(in_array($exception->getMessage(), $safeMessages, true) ? $exception->getMessage()
                : 'Die Rollenprüfung konnte nicht abgeschlossen werden. Es wurden keine Rollen übernommen.');

            return self::FAILURE;
        }
        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /** @return array{report: array<string, mixed>, eligible: array<int, TeachingCourseStudent>} */
    private function snapshot(TeachingCourseStudentRole $studentRoles, bool $lock): array
    {
        $query = TeachingCourseStudent::withTrashed()->orderBy('id');
        $memberships = ($lock ? $query->lockForUpdate() : $query)->get();
        $rows = [];
        $candidates = [];
        $already = [];
        $inactiveAccounts = [];
        $blocked = [];
        $issues = [];
        foreach ($memberships as $membership) {
            if ($membership->trashed() || $membership->canceled_at !== null) {
                continue;
            }
            $result = $studentRoles->inspect($membership, $lock);
            $status = $result['status'];
            $id = $result['user_id'];
            $course = $membership->teachingCourse()->first();
            $rows[] = ['membership_id' => (int) $membership->id, 'course_id' => (int) $membership->teaching_course_id,
                'school_id' => $course?->school_id, 'course_schoolyear_id' => $course?->schoolyear_id,
                'membership_user_id' => $membership->user_id, 'import116_id' => $membership->import116_id, ...$result];
            if ($status === 'eligible' && $id !== null) {
                $candidates[$id] ??= $membership;
            } elseif ($status === 'already_assigned' && $id !== null) {
                $already[$id] = true;
            } elseif ($status === 'inactive_account' && $id !== null) {
                $inactiveAccounts[$id] = true;
            } elseif (! in_array($status, ['inactive', 'inactive_year'], true)) {
                $issues[$status] = ($issues[$status] ?? 0) + 1;
                foreach ($result['related_user_ids'] as $relatedId) {
                    $blocked[$relatedId] = true;
                }
            }
        }
        foreach (array_keys($blocked + $already + $inactiveAccounts) as $id) {
            unset($candidates[$id]);
        }
        ksort($candidates, SORT_NUMERIC);
        ksort($issues, SORT_STRING);
        $accountIds = [];
        foreach ($rows as $row) {
            foreach ($row['related_user_ids'] as $id) {
                $accountIds[$id] = $id;
            }
        }
        sort($accountIds, SORT_NUMERIC);
        if ($lock && $accountIds !== []) {
            DB::table(config('permission.table_names.model_has_roles'))->where('model_type', (new User)->getMorphClass())
                ->whereIn('model_id', $accountIds)->lockForUpdate()->get();
        }
        $accounts = User::query()->with('roles')->whereIn('id', $accountIds)->orderBy('id')->get()->map(fn (User $user): array => [
            'id' => (int) $user->id, 'school_id' => $user->school_id, 'schoolyear_id' => $user->schoolyear_id,
            'import116_id' => $user->import116_id, 'is_active' => $user->is_active,
            'roles' => $user->roles->map(fn (Role $role): string => $role->guard_name.':'.$role->name)->sort()->values()->all(),
        ])->all();
        $schoolIds = array_values(array_unique(array_filter(array_column($rows, 'school_id'))));
        sort($schoolIds, SORT_NUMERIC);
        $toolsQuery = SchoolTool::query()->whereIn('school_id', $schoolIds)->orderBy('school_id')->orderBy('id');
        $schoolYears = ($lock ? $toolsQuery->lockForUpdate() : $toolsQuery)->get(['id', 'school_id', 'active_schoolyear_id'])->toArray();
        $role = Role::query()->where('name', 'student')->where('guard_name', 'web')->first();
        $digest = hash('sha256', json_encode(['rows' => $rows, 'accounts' => $accounts, 'schoolyears' => $schoolYears,
            'student_role_id' => $role?->id], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $report = ['digest' => $digest, 'student_role_available' => $role !== null,
            'eligible_accounts' => count($candidates), 'already_assigned_accounts' => count($already),
            'inactive_accounts' => count($inactiveAccounts), 'blocked_accounts' => count($blocked),
            'unresolved_assignments' => array_sum($issues), 'issue_types' => $issues];

        return ['report' => $report, 'eligible' => $candidates];
    }
}
