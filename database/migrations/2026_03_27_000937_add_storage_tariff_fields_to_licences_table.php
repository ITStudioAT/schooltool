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
        if (! Schema::hasTable('licences')) {
            return;
        }

        Schema::table('licences', function (Blueprint $table) {
            if (! Schema::hasColumn('licences', 'school_included_storage_gb')) {
                $table->unsignedInteger('school_included_storage_gb')->nullable()->after('school_price_per_year');
            }

            if (! Schema::hasColumn('licences', 'school_extra_storage_step_gb')) {
                $table->unsignedInteger('school_extra_storage_step_gb')->nullable()->after('school_included_storage_gb');
            }

            if (! Schema::hasColumn('licences', 'school_extra_storage_step_price')) {
                $table->decimal('school_extra_storage_step_price', 10, 2)->nullable()->after('school_extra_storage_step_gb');
            }

            if (! Schema::hasColumn('licences', 'admin_included_storage_gb')) {
                $table->unsignedInteger('admin_included_storage_gb')->nullable()->after('admin_role_names');
            }

            if (! Schema::hasColumn('licences', 'admin_extra_storage_step_gb')) {
                $table->unsignedInteger('admin_extra_storage_step_gb')->nullable()->after('admin_included_storage_gb');
            }

            if (! Schema::hasColumn('licences', 'admin_extra_storage_step_price')) {
                $table->decimal('admin_extra_storage_step_price', 10, 2)->nullable()->after('admin_extra_storage_step_gb');
            }

            if (! Schema::hasColumn('licences', 'user_included_storage_gb')) {
                $table->unsignedInteger('user_included_storage_gb')->nullable()->after('user_role_names');
            }

            if (! Schema::hasColumn('licences', 'user_extra_storage_step_gb')) {
                $table->unsignedInteger('user_extra_storage_step_gb')->nullable()->after('user_included_storage_gb');
            }

            if (! Schema::hasColumn('licences', 'user_extra_storage_step_price')) {
                $table->decimal('user_extra_storage_step_price', 10, 2)->nullable()->after('user_extra_storage_step_gb');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('licences')) {
            return;
        }

        $columnsToDrop = array_values(array_filter([
            'school_included_storage_gb',
            'school_extra_storage_step_gb',
            'school_extra_storage_step_price',
            'admin_included_storage_gb',
            'admin_extra_storage_step_gb',
            'admin_extra_storage_step_price',
            'user_included_storage_gb',
            'user_extra_storage_step_gb',
            'user_extra_storage_step_price',
        ], fn (string $column): bool => Schema::hasColumn('licences', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('licences', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
