<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (!Schema::hasColumn('school_tools', 'tutoring_student_must_be_confirmed')) {
                $table->boolean('tutoring_student_must_be_confirmed')->default(false);
            }

            if (!Schema::hasColumn('school_tools', 'tutoring_confirmer_email')) {
                $table->string('tutoring_confirmer_email')->nullable();
            }

            if (!Schema::hasColumn('school_tools', 'tutoring_max_offers_per_student')) {
                $table->unsignedInteger('tutoring_max_offers_per_student')->default(0);
            }

            if (!Schema::hasColumn('school_tools', 'may_visible_for_other_schools')) {
                $table->boolean('may_visible_for_other_schools')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (Schema::hasColumn('school_tools', 'may_visible_for_other_schools')) {
                $table->dropColumn('may_visible_for_other_schools');
            }
            if (Schema::hasColumn('school_tools', 'tutoring_max_offers_per_student')) {
                $table->dropColumn('tutoring_max_offers_per_student');
            }
            if (Schema::hasColumn('school_tools', 'tutoring_confirmer_email')) {
                $table->dropColumn('tutoring_confirmer_email');
            }
            if (Schema::hasColumn('school_tools', 'tutoring_student_must_be_confirmed')) {
                $table->dropColumn('tutoring_student_must_be_confirmed');
            }
        });
    }
};

