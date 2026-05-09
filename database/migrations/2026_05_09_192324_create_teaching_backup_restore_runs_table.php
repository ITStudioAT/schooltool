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
        Schema::create('teaching_backup_restore_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_backup_id')->nullable()->constrained('teaching_backups')->nullOnDelete();
            $table->foreignId('pre_restore_backup_id')->nullable()->constrained('teaching_backups')->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->unsignedInteger('progress_current')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            $table->json('selection')->nullable();
            $table->json('result')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'schoolyear_id', 'created_at'], 'tbr_runs_scope_created_idx');
            $table->index(['teaching_backup_id', 'status'], 'tbr_runs_backup_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_backup_restore_runs');
    }
};
