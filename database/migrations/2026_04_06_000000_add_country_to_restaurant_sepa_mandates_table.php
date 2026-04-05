<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_sepa_mandates')) {
            return;
        }

        Schema::table('restaurant_sepa_mandates', function (Blueprint $table): void {
            $table->string('country', 255)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('restaurant_sepa_mandates')) {
            return;
        }

        Schema::table('restaurant_sepa_mandates', function (Blueprint $table): void {
            $table->dropColumn('country');
        });
    }
};
