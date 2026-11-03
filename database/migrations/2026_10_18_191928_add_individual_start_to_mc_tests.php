<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mc_tests', function (Blueprint $table): void {
            $table->string('start_mode', 20)->default('common');
            $table->timestamp('closes_at')->nullable();
        });
        Schema::table('mc_attempts', function (Blueprint $table): void {
            $table->timestamp('closes_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mc_attempts', fn (Blueprint $table) => $table->dropColumn('closes_at'));
        Schema::table('mc_tests', fn (Blueprint $table) => $table->dropColumn(['start_mode', 'closes_at']));
    }
};
