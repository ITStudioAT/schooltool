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
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('school_tools', 'restaurant_new_users_confirmer_email')) {
                $table->string('restaurant_new_users_confirmer_email')
                    ->nullable()
                    ->after('restaurant_new_users_must_confirm_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('school_tools') || ! Schema::hasColumn('school_tools', 'restaurant_new_users_confirmer_email')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            $table->dropColumn('restaurant_new_users_confirmer_email');
        });
    }
};
