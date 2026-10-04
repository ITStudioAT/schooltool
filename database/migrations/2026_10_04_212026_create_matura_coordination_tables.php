<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matura_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('schoolyear_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name', 160);
            $table->date('exam_date');
            $table->unsignedTinyInteger('waiting_places')->default(1);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('station_access_id')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'schoolyear_id', 'exam_date']);
        });
        Schema::create('matura_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matura_session_id')->constrained();
            $table->string('name', 80);
            $table->unsignedBigInteger('supervisor_access_id')->nullable();
            $table->timestamps();
            $table->unique(['matura_session_id', 'name']);
        });
        Schema::create('matura_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matura_session_id')->constrained();
            $table->foreignId('matura_room_id')->constrained();
            $table->unsignedBigInteger('import116_id')->nullable();
            $table->string('name', 180);
            $table->string('class_name', 80)->nullable();
            $table->timestamps();
            $table->unique(['matura_session_id', 'import116_id']);
        });
        Schema::create('matura_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matura_session_id')->constrained();
            $table->foreignId('matura_room_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('name', 180);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('matura_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matura_session_id')->constrained();
            $table->foreignId('matura_student_id')->constrained();
            $table->foreignId('matura_room_id')->constrained();
            $table->uuid('request_key');
            $table->string('status', 20);
            foreach (['requested', 'approved', 'departed', 'arrived', 'entered', 'exited', 'returned', 'cancelled', 'voided'] as $event) {
                $table->dateTime($event.'_at')->nullable();
            }
            $table->text('correction_reason')->nullable();
            $table->timestamps();
            $table->unique(['matura_session_id', 'request_key']);
            $table->index(['matura_session_id', 'status']);
            $table->index(['matura_student_id', 'status']);
        });
        Schema::create('matura_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matura_session_id')->constrained();
            $table->foreignId('matura_visit_id')->nullable()->constrained();
            $table->uuid('operation_key')->nullable();
            $table->string('actor', 180);
            $table->string('action', 40);
            $table->json('details')->nullable();
            $table->dateTime('occurred_at');
            $table->unique(['matura_session_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        foreach (['matura_events', 'matura_visits', 'matura_accesses', 'matura_students', 'matura_rooms', 'matura_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
