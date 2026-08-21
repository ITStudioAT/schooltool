<?php

use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableStudySelectionRefreshService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $scopes = DB::table('import116')
            ->whereNotNull('schoolyear_id')
            ->select(['school_id', 'schoolyear_id'])
            ->distinct()
            ->orderBy('school_id')
            ->orderBy('schoolyear_id')
            ->get();

        foreach ($scopes as $scope) {
            $importUserId = DB::table('import116')
                ->where('school_id', $scope->school_id)
                ->where('schoolyear_id', $scope->schoolyear_id)
                ->whereNotNull('import_user_id')
                ->orderByDesc('id')
                ->value('import_user_id');
            $user = User::query()
                ->where('school_id', $scope->school_id)
                ->when($importUserId, fn ($query) => $query->whereKey($importUserId))
                ->first();

            $user ??= User::query()
                ->where('school_id', $scope->school_id)
                ->orderBy('id')
                ->first();

            if (! $user) {
                continue;
            }

            app(StudentTimetableStudySelectionRefreshService::class)->refreshForUser(
                $user,
                (int) $scope->schoolyear_id,
            );
        }
    }
};
