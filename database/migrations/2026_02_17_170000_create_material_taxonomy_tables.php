<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('material_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('material_subjects')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['subject_id', 'name']);
        });

        Schema::create('material_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('material_topics')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['topic_id', 'name']);
        });

        Schema::create('material_card_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_card_id')->constrained('material_cards')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('material_subjects')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('material_topics')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('material_units')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_card_id', 'subject_id', 'topic_id', 'unit_id'], 'material_card_class_lookup');
            $table->index(['subject_id', 'topic_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_card_classifications');
        Schema::dropIfExists('material_units');
        Schema::dropIfExists('material_topics');
        Schema::dropIfExists('material_subjects');
    }
};
