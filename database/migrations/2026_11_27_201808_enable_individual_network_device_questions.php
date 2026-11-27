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
        Schema::table('network_test_questions', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->unsignedInteger('revision')->default(0);
        });

        Schema::table('network_device_answer_keys', function (Blueprint $table): void {
            $table->boolean('questions_initialized')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('network_device_answer_keys')->where('questions_initialized', true)->exists()
            || DB::table('network_test_questions')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Rolling back would discard individually managed device questions.');
        }

        Schema::table('network_device_answer_keys', function (Blueprint $table): void {
            $table->dropColumn('questions_initialized');
        });

        Schema::table('network_test_questions', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->dropColumn('revision');
        });
    }
};
