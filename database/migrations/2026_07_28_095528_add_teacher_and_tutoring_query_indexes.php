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
        Schema::table('teachers', function (Blueprint $table): void {
            $table->index(['school_id', 'email'], 'teachers_school_email_index');
            $table->index('email', 'teachers_email_index');
            $table->index(['school_id', 'short', 'last_name'], 'teachers_school_sort_index');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropIndex('teachers_school_sort_index');
            $table->dropIndex('teachers_email_index');
            $table->dropIndex('teachers_school_email_index');
        });
    }
};
