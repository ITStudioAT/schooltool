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
        if (! Schema::hasTable('school_licences')) {
            return;
        }

        Schema::table('school_licences', function (Blueprint $table) {
            if (! Schema::hasColumn('school_licences', 'charged_school_price')) {
                $table->decimal('charged_school_price', 10, 2)->nullable()->after('valid_until');
            }

            if (! Schema::hasColumn('school_licences', 'extra_storage_units')) {
                $table->unsignedInteger('extra_storage_units')->nullable()->after('charged_school_price');
            }

            if (! Schema::hasColumn('school_licences', 'extra_storage_unit_price')) {
                $table->decimal('extra_storage_unit_price', 10, 2)->nullable()->after('extra_storage_units');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_licences')) {
            return;
        }

        $columnsToDrop = array_values(array_filter([
            'charged_school_price',
            'extra_storage_units',
            'extra_storage_unit_price',
        ], fn (string $column): bool => Schema::hasColumn('school_licences', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('school_licences', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
