<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teaching_school_hours', 'schoolyear_id')) {
            Schema::table('teaching_school_hours', function (Blueprint $table) {
                $table->foreignId('schoolyear_id')->nullable()->after('school_id')->constrained('schoolyears')->nullOnDelete();
            });
        }

        DB::table('school_tools')
            ->whereNotNull('active_schoolyear_id')
            ->orderBy('id')
            ->get(['school_id', 'active_schoolyear_id'])
            ->each(function (object $schoolTool): void {
                DB::table('teaching_school_hours')
                    ->where('school_id', $schoolTool->school_id)
                    ->whereNull('schoolyear_id')
                    ->update([
                        'schoolyear_id' => $schoolTool->active_schoolyear_id,
                    ]);
            });

        try {
            Schema::table('teaching_school_hours', function (Blueprint $table) {
                $table->unique(['school_id', 'schoolyear_id', 'hour'], 'teaching_school_hours_school_schoolyear_hour_unique');
            });
        } catch (QueryException) {
        }

        try {
            Schema::table('teaching_school_hours', function (Blueprint $table) {
                $table->index(['school_id', 'schoolyear_id', 'hour'], 'teaching_school_hours_school_schoolyear_hour_idx');
            });
        } catch (QueryException) {
        }

        try {
            Schema::table('teaching_school_hours', function (Blueprint $table) {
                $table->dropUnique('teaching_school_hours_school_hour_unique');
            });
        } catch (QueryException) {
        }

        try {
            Schema::table('teaching_school_hours', function (Blueprint $table) {
                $table->dropIndex('teaching_school_hours_school_hour_idx');
            });
        } catch (QueryException) {
        }
    }

    public function down(): void
    {
        Schema::table('teaching_school_hours', function (Blueprint $table) {
            $table->dropUnique('teaching_school_hours_school_schoolyear_hour_unique');
            $table->dropIndex('teaching_school_hours_school_schoolyear_hour_idx');
            $table->dropConstrainedForeignId('schoolyear_id');
            $table->unique(['school_id', 'hour'], 'teaching_school_hours_school_hour_unique');
            $table->index(['school_id', 'hour'], 'teaching_school_hours_school_hour_idx');
        });
    }
};
