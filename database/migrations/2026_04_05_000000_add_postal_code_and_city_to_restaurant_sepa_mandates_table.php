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
            $table->string('postal_code', 16)->nullable()->after('address_line');
            $table->string('city', 255)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('restaurant_sepa_mandates')) {
            return;
        }

        Schema::table('restaurant_sepa_mandates', function (Blueprint $table): void {
            $table->dropColumn(['postal_code', 'city']);
        });
    }
};
