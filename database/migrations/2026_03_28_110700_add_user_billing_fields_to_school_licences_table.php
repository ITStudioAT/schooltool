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
            if (! Schema::hasColumn('school_licences', 'charged_user_price')) {
                $table->decimal('charged_user_price', 10, 2)->nullable()->after('admin_extra_storage_unit_price');
            }

            if (! Schema::hasColumn('school_licences', 'user_extra_storage_units')) {
                $table->integer('user_extra_storage_units')->nullable()->after('charged_user_price');
            }

            if (! Schema::hasColumn('school_licences', 'user_extra_storage_unit_price')) {
                $table->decimal('user_extra_storage_unit_price', 10, 2)->nullable()->after('user_extra_storage_units');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_licences')) {
            return;
        }

        $columnsToDrop = array_values(array_filter([
            'charged_user_price',
            'user_extra_storage_units',
            'user_extra_storage_unit_price',
        ], fn (string $column): bool => Schema::hasColumn('school_licences', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('school_licences', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
