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
            if (! Schema::hasColumn('licences', 'start_day_month')) {
                $table->string('start_day_month', 5)->nullable()->after('price_per_year');
            }

            if (! Schema::hasColumn('licences', 'end_day_month')) {
                $table->string('end_day_month', 5)->nullable()->after('start_day_month');
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
            'start_day_month',
            'end_day_month',
        ], fn (string $column): bool => Schema::hasColumn('licences', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('licences', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
