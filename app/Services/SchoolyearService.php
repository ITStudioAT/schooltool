<?php

namespace App\Services;

use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchoolyearService
{
    public function setToUser(User $user, int|string $schoolyear_id): Schoolyear
    {
        $schoolyear = Schoolyear::findOrFail($schoolyear_id);

        if ((int) $schoolyear->school_id !== (int) $user->school_id) {
            throw new HttpException(403, 'Sie haben keine Berechtigung');
        }

        $user->schoolyear_id = $schoolyear->id;
        $user->save();

        return $schoolyear;
    }

    public function ensureActualSchoolyearForUser(User $user): ?Schoolyear
    {
        if ($user->schoolyear_id) {
            return Schoolyear::query()
                ->where('school_id', $user->school_id)
                ->whereKey($user->schoolyear_id)
                ->first();
        }

        if (! $user->school_id) {
            return null;
        }

        $schoolyear = $this->actualSchoolyearForSchool((int) $user->school_id);

        if (! $schoolyear) {
            return null;
        }

        $user->schoolyear_id = $schoolyear->id;
        $user->save();
        $user->setRelation('selectedSchoolyear', $schoolyear);

        return $schoolyear;
    }

    public function actualSchoolyearForSchool(int $schoolId): ?Schoolyear
    {
        $today = Carbon::today();

        $currentSchoolyear = Schoolyear::query()
            ->where('school_id', $schoolId)
            ->whereDate('from', '<=', $today)
            ->whereDate('until', '>=', $today)
            ->orderByDesc('from')
            ->orderByDesc('id')
            ->first();

        if ($currentSchoolyear) {
            return $currentSchoolyear;
        }

        $activeSchoolyearId = SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('active_schoolyear_id');

        if ($activeSchoolyearId) {
            $activeSchoolyear = Schoolyear::query()
                ->where('school_id', $schoolId)
                ->whereKey($activeSchoolyearId)
                ->first();

            if ($activeSchoolyear) {
                return $activeSchoolyear;
            }
        }

        return Schoolyear::query()
            ->where('school_id', $schoolId)
            ->active()
            ->orderByDesc('from')
            ->orderByDesc('id')
            ->first();
    }
}
