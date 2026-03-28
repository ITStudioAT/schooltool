<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_licences', function (Blueprint $table) {
            $table->decimal('charged_admin_price', 10, 2)->nullable()->after('extra_storage_unit_price');
            $table->unsignedInteger('admin_extra_storage_units')->nullable()->after('charged_admin_price');
            $table->decimal('admin_extra_storage_unit_price', 10, 2)->nullable()->after('admin_extra_storage_units');
        });
    }

    public function down(): void
    {
        Schema::table('school_licences', function (Blueprint $table) {
            $table->dropColumn(['charged_admin_price', 'admin_extra_storage_units', 'admin_extra_storage_unit_price']);
        });
    }
};
