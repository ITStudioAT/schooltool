<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_licences')) {
            return;
        }

        Schema::table('school_licences', function (Blueprint $table) {
            if (! Schema::hasColumn('school_licences', 'charged_admin_price')) {
                $table->decimal('charged_admin_price', 10, 2)->nullable()->after('extra_storage_unit_price');
            }

            if (! Schema::hasColumn('school_licences', 'admin_extra_storage_units')) {
                $table->unsignedInteger('admin_extra_storage_units')->nullable()->after('charged_admin_price');
            }

            if (! Schema::hasColumn('school_licences', 'admin_extra_storage_unit_price')) {
                $table->decimal('admin_extra_storage_unit_price', 10, 2)->nullable()->after('admin_extra_storage_units');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_licences')) {
            return;
        }

        $columnsToDrop = array_values(array_filter([
            'charged_admin_price',
            'admin_extra_storage_units',
            'admin_extra_storage_unit_price',
        ], fn (string $column): bool => Schema::hasColumn('school_licences', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('school_licences', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
