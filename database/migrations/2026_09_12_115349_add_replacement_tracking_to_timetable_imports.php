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
        Schema::table('timetable_imports', function (Blueprint $table): void {
            $table->string('import_operation', 16)->default('merge');
            $table->string('replacement_scope', 16)->nullable();
            $table->date('replacement_from')->nullable();
            $table->date('replacement_until')->nullable();
            $table->char('comparison_fingerprint', 64)->nullable();
            $table->json('change_summary')->nullable();
        });

        Schema::table('student_timetable_entries', function (Blueprint $table): void {
            $table->foreignId('superseded_by_import_id')->nullable()->index();
        });
    }
};
