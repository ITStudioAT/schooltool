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
        Schema::create('personal_teaching_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->longText('payload');
            $table->json('summary');
            $table->string('mail_status')->default('pending');
            $table->text('mail_message')->nullable();
            $table->timestamp('mailed_at')->nullable();
            $table->timestamp('recovery_requested_at')->nullable();
            $table->json('student_mappings')->nullable();
            $table->json('import_mappings')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'school_id', 'created_at']);
        });
    }
};
