<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permanently retire module data. Recovery requires restoring a backup.
     */
    public function up(): void
    {
        Schema::dropIfExists('tutoring_offer_requests');
        Schema::dropIfExists('tutoring_offers');
        Schema::dropIfExists('tutoring_subjects');

        foreach ([
            'users' => ['tutoring_filter'],
            'school_tools' => [
                'tutoring_student_must_be_confirmed',
                'tutoring_confirmer_email',
                'tutoring_max_offers_per_student',
                'may_visible_for_other_schools',
                'tutoring_status',
                'tutoring_visible_admin',
                'tutoring_visible_user',
                'tutoring_user_test_mode',
                'tutoring_user_comming_soon',
            ],
        ] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = array_values(array_intersect($columns, Schema::getColumnListing($tableName)));

            if ($columns !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
