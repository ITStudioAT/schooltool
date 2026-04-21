<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_imported_curricula', function (Blueprint $table) {
            $table->foreignId('adopted_curriculum_id')
                ->nullable()
                ->after('user_id')
                ->constrained('teaching_curricula')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teaching_imported_curricula', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adopted_curriculum_id');
        });
    }
};
