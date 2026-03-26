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
        Schema::table('licences', function (Blueprint $table) {
            $table->unsignedInteger('school_included_storage_gb')->nullable()->after('school_price_per_year');
            $table->unsignedInteger('school_extra_storage_step_gb')->nullable()->after('school_included_storage_gb');
            $table->decimal('school_extra_storage_step_price', 10, 2)->nullable()->after('school_extra_storage_step_gb');
            $table->unsignedInteger('admin_included_storage_gb')->nullable()->after('admin_role_names');
            $table->unsignedInteger('admin_extra_storage_step_gb')->nullable()->after('admin_included_storage_gb');
            $table->decimal('admin_extra_storage_step_price', 10, 2)->nullable()->after('admin_extra_storage_step_gb');
            $table->unsignedInteger('user_included_storage_gb')->nullable()->after('user_role_names');
            $table->unsignedInteger('user_extra_storage_step_gb')->nullable()->after('user_included_storage_gb');
            $table->decimal('user_extra_storage_step_price', 10, 2)->nullable()->after('user_extra_storage_step_gb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licences', function (Blueprint $table) {
            $table->dropColumn([
                'school_included_storage_gb',
                'school_extra_storage_step_gb',
                'school_extra_storage_step_price',
                'admin_included_storage_gb',
                'admin_extra_storage_step_gb',
                'admin_extra_storage_step_price',
                'user_included_storage_gb',
                'user_extra_storage_step_gb',
                'user_extra_storage_step_price',
            ]);
        });
    }
};
