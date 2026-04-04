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
            $table->text('restaurant_sepa_payee')->nullable()->after('restaurant_user_information_intro_html');
            $table->text('restaurant_sepa_mandate_text')->nullable()->after('restaurant_sepa_payee');
        });
    }

    public function down(): void
    {
        Schema::table('school_tools', function (Blueprint $table) {
            $table->dropColumn(['restaurant_sepa_payee', 'restaurant_sepa_mandate_text']);
        });
    }
};
