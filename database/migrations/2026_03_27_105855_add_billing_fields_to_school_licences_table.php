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
        Schema::table('school_licences', function (Blueprint $table) {
            $table->decimal('charged_school_price', 10, 2)->nullable()->after('valid_until');
            $table->unsignedInteger('extra_storage_units')->nullable()->after('charged_school_price');
            $table->decimal('extra_storage_unit_price', 10, 2)->nullable()->after('extra_storage_units');
        });
    }

    public function down(): void
    {
        Schema::table('school_licences', function (Blueprint $table) {
            $table->dropColumn(['charged_school_price', 'extra_storage_units', 'extra_storage_unit_price']);
        });
    }
};
