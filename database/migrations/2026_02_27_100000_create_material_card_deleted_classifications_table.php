<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_card_deleted_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_card_id')->constrained('material_cards')->cascadeOnDelete();
            $table->string('subject_name');
            $table->string('topic_name')->nullable();
            $table->string('unit_name')->nullable();
            $table->timestamps();

            $table->index('material_card_id', 'material_card_deleted_classifications_card_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_card_deleted_classifications');
    }
};
