<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('licences', function (Blueprint $table) {
            $table->unsignedTinyInteger('licence_schema_version')->default(1)->after('licence_model');
            $table->boolean('school_licence_enabled')->default(true)->after('licence_schema_version');
            $table->decimal('school_price_per_year', 10, 2)->nullable()->after('school_licence_enabled');
            $table->boolean('admin_licence_enabled')->default(false)->after('school_price_per_year');
            $table->decimal('admin_price_per_year', 10, 2)->nullable()->after('admin_licence_enabled');
            $table->json('admin_role_names')->nullable()->after('admin_price_per_year');
            $table->boolean('user_licence_enabled')->default(false)->after('admin_role_names');
            $table->decimal('user_price_per_year', 10, 2)->nullable()->after('user_licence_enabled');
            $table->json('user_role_names')->nullable()->after('user_price_per_year');
        });

        DB::table('licences')
            ->whereNull('school_price_per_year')
            ->update([
                'school_price_per_year' => DB::raw('price_per_year'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licences', function (Blueprint $table) {
            $table->dropColumn([
                'licence_schema_version',
                'school_licence_enabled',
                'school_price_per_year',
                'admin_licence_enabled',
                'admin_price_per_year',
                'admin_role_names',
                'user_licence_enabled',
                'user_price_per_year',
                'user_role_names',
            ]);
        });
    }
};
