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
        Schema::table('teaching_backup_restore_runs', function (Blueprint $table) {
            $table->json('audit_metadata')->nullable()->after('result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_backup_restore_runs', function (Blueprint $table) {
            $table->dropColumn('audit_metadata');
        });
    }
};
