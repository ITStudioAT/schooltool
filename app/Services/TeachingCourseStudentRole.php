<?php

namespace App\Services;

use App\Jobs\Teaching\Import116Job;
use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TeachingCourseStudentRole
{
    public function assignForSchoolYear(int $schoolId, int $schoolyearId): void
    {
        TeachingCourseStudent::query()->whereNull('canceled_at')
            ->whereHas('teachingCourse', fn ($query) => $query->where('school_id', $schoolId)->where('schoolyear_id', $schoolyearId))
            ->orderBy('id')->chunkById(100, function ($memberships): void {
                foreach ($memberships as $membership) {
                    $this->assign($membership);
                }
            });
    }

    /** @return array{status: string, user_id: int|null, related_user_ids: list<int>} */
    public function inspect(TeachingCourseStudent $membership, bool $lock = false): array
    {
        $directId = (int) ($membership->user_id ?? 0);
        $relatedIds = $directId ? [$directId] : [];
        $result = function (string $status, ?int $userId = null) use (&$relatedIds): array {
            return ['status' => $status, 'user_id' => $userId ?? ($relatedIds[0] ?? null), 'related_user_ids' => $relatedIds];
        };
        if ($membership->trashed() || $membership->canceled_at !== null) {
            return $result('inactive');
        }
        $courseQuery = $membership->teachingCourse();
        $course = ($lock ? $courseQuery->lockForUpdate() : $courseQuery)->first();
        if (! $course || ! $course->school_id || ! $course->schoolyear_id) {
            return $result('identity_conflict');
        }
        $importQuery = Import116::query();
        $import = $membership->import116_id ? ($lock ? $importQuery->lockForUpdate() : $importQuery)->find($membership->import116_id) : null;
        $linkedId = (int) ($import?->user_id ?? 0);
        if ($linkedId && ! in_array($linkedId, $relatedIds, true)) {
            $relatedIds[] = $linkedId;
        }
        $reverseIds = [];
        if ($import) {
            $reverseQuery = User::query()->where('import116_id', $import->id)->orderBy('id');
            $reverseIds = ($lock ? $reverseQuery->lockForUpdate() : $reverseQuery)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $relatedIds = array_values(array_unique([...$relatedIds, ...$reverseIds]));
        }
        $toolsQuery = SchoolTool::query()->where('school_id', $course->school_id);
        $schoolTools = ($lock ? $toolsQuery->lockForUpdate() : $toolsQuery)->get(['active_schoolyear_id']);
        $activeYears = $schoolTools->pluck('active_schoolyear_id')->unique()->values();
        if ($activeYears->count() !== 1 || ! $activeYears[0]
            || ! Schoolyear::query()->where('school_id', $course->school_id)->whereKey($activeYears[0])->exists()) {
            return $result('schoolyear_unconfirmed');
        }
        if ((int) $activeYears[0] !== (int) $course->schoolyear_id) {
            return $result('inactive_year');
        }
        if ($membership->import116_id && (! $import || (int) $import->school_id !== (int) $course->school_id
            || (int) $import->schoolyear_id !== (int) $course->schoolyear_id)) {
            return $result('identity_conflict');
        }
        if (count($relatedIds) > 1) {
            return $result('identity_conflict');
        }
        $id = $directId ?: ($linkedId ?: ($reverseIds[0] ?? 0));
        if (! $id) {
            return $result('missing_account');
        }
        $userQuery = User::query()->with('roles');
        $user = ($lock ? $userQuery->lockForUpdate() : $userQuery)->find($id);
        if (! $user) {
            return $result('missing_account');
        }
        if ((int) $user->school_id !== (int) $course->school_id) {
            return $result('identity_conflict');
        }
        $provenImport = $import && ($linkedId === $id || in_array($id, $reverseIds, true));
        if ($import && ! $provenImport) {
            return $result('identity_conflict');
        }
        if (! $import) {
            $sourceQuery = Import116::query()->where('school_id', $course->school_id)->where('schoolyear_id', $course->schoolyear_id)
                ->where(fn ($query) => $query->where('user_id', $id)
                    ->when($user->import116_id, fn ($query) => $query->orWhereKey($user->import116_id)))->orderBy('id');
            $sources = ($lock ? $sourceQuery->lockForUpdate() : $sourceQuery)->get();
            if ($sources->count() > 1) {
                return $result('identity_conflict');
            }
            if ($source = $sources->first()) {
                $sourceUserId = (int) ($source->user_id ?? 0);
                if ($sourceUserId && $sourceUserId !== $id) {
                    $relatedIds[] = $sourceUserId;

                    return $result('identity_conflict');
                }
                $inverseQuery = User::query()->where('import116_id', $source->id)->orderBy('id');
                $inverse = ($lock ? $inverseQuery->lockForUpdate() : $inverseQuery)->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $relatedIds = array_values(array_unique([...$relatedIds, ...$inverse]));
                if (count($relatedIds) > 1) {
                    return $result('identity_conflict');
                }
                $provenImport = $sourceUserId === $id || in_array($id, $inverse, true);
            }
        }
        if ($user->is_active !== null && ! (bool) $user->is_active) {
            return $result('inactive_account', $id);
        }
        if ($user->hasRole('student', 'web')) {
            return $result('already_assigned', $id);
        }
        $roles = $user->roles->pluck('name')->all();
        $hasStudentEvidence = $provenImport || $user->hasRole('studentstimetables_user', 'web');
        $email = mb_strtolower(trim((string) $user->email));
        if (! $hasStudentEvidence && (! filter_var($email, FILTER_VALIDATE_EMAIL) || Import116Job::isPlaceholderEmail($email))) {
            return $result('unverified_placeholder');
        }
        if (! $hasStudentEvidence
            && array_diff($roles, ['user']) !== []) {
            return $result('role_conflict');
        }

        return $result('eligible', $id);
    }

    /** @return array{status: string, user_id: int|null, related_user_ids: list<int>} */
    public function assign(TeachingCourseStudent $membership): array
    {
        return DB::transaction(function () use ($membership): array {
            $locked = TeachingCourseStudent::withTrashed()->whereKey($membership->getKey())->lockForUpdate()->first();
            if (! $locked) {
                return ['status' => 'inactive', 'user_id' => null, 'related_user_ids' => []];
            }
            $result = $this->inspect($locked, true);
            if ($result['status'] !== 'eligible') {
                return $result;
            }
            $user = User::query()->whereKey($result['user_id'])->lockForUpdate()->firstOrFail();
            $otherMemberships = TeachingCourseStudent::query()->whereKeyNot($locked->getKey())->whereNull('canceled_at')
                ->where(fn ($query) => $query->where('user_id', $result['user_id'])
                    ->orWhereHas('import116', fn ($query) => $query->where('user_id', $result['user_id']))
                    ->when($user->import116_id, fn ($query) => $query->orWhere('import116_id', $user->import116_id)))
                ->orderBy('id')->get();
            foreach ($otherMemberships as $otherMembership) {
                $other = $this->inspect($otherMembership);
                if (in_array($other['status'], ['identity_conflict', 'role_conflict', 'unverified_placeholder', 'schoolyear_unconfirmed'], true)
                    && in_array($result['user_id'], $other['related_user_ids'], true)) {
                    return ['status' => 'identity_conflict', 'user_id' => $result['user_id'], 'related_user_ids' => $other['related_user_ids']];
                }
            }
            $role = Role::query()->where('name', 'student')->where('guard_name', 'web')->firstOrFail();
            $user->assignRole($role);

            return ['status' => 'assigned', 'user_id' => (int) $user->id, 'related_user_ids' => [(int) $user->id]];
        });
    }
}
