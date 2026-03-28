<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_licences', function (Blueprint $table) {
            $table->decimal('charged_user_price', 10, 2)->nullable()->after('admin_extra_storage_unit_price');
            $table->integer('user_extra_storage_units')->nullable()->after('charged_user_price');
            $table->decimal('user_extra_storage_unit_price', 10, 2)->nullable()->after('user_extra_storage_units');
        });
    }

    public function down(): void
    {
        Schema::table('school_licences', function (Blueprint $table) {
            $table->dropColumn([
                'charged_user_price',
                'user_extra_storage_units',
                'user_extra_storage_unit_price',
            ]);
        });
    }
};
