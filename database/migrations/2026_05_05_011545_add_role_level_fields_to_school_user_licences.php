<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('school_user_licences', function (Blueprint $table) {
            $table->string('role_name')->default('')->after('assignment_type');
            $table->unsignedBigInteger('plan_id')->nullable()->after('is_active');
            $table->unsignedInteger('extra_storage_units')->nullable()->after('plan_id');
            $table->decimal('extra_storage_unit_price', 10, 2)->nullable()->after('extra_storage_units');

            $table->dropUnique('school_user_licences_unique_assignment');
            $table->unique(
                ['school_id', 'licence_id', 'user_id', 'assignment_type', 'role_name'],
                'school_user_licences_unique_role_assignment'
            );
            $table->index(
                ['school_id', 'licence_id', 'assignment_type', 'role_name'],
                'school_user_licences_role_lookup'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('school_user_licences')
            ->where('role_name', '<>', '')
            ->delete();

        Schema::table('school_user_licences', function (Blueprint $table) {
            $table->dropIndex('school_user_licences_role_lookup');
            $table->dropUnique('school_user_licences_unique_role_assignment');
            $table->unique(['school_id', 'licence_id', 'user_id', 'assignment_type'], 'school_user_licences_unique_assignment');

            $table->dropColumn([
                'role_name',
                'plan_id',
                'extra_storage_units',
                'extra_storage_unit_price',
            ]);
        });
    }
};
