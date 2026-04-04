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
        Schema::table('school_tools', function (Blueprint $table) {
            $table->boolean('restaurant_sepa_online_enabled')->default(false)->after('restaurant_user_information_intro_html');
        });
    }

    public function down(): void
    {
        Schema::table('school_tools', function (Blueprint $table) {
            $table->dropColumn('restaurant_sepa_online_enabled');
        });
    }
};
