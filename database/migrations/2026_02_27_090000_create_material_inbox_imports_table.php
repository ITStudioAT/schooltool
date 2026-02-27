<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_inbox_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_material_card_id')->constrained('material_cards')->cascadeOnDelete();
            $table->unsignedBigInteger('source_rule_id')->nullable();
            $table->unsignedBigInteger('source_school_id');
            $table->unsignedBigInteger('source_material_id');
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique(
                ['target_user_id', 'source_school_id', 'source_material_id'],
                'material_inbox_imports_target_source_unique'
            );
            $table->index(
                ['target_user_id', 'source_school_id', 'source_material_id'],
                'material_inbox_imports_target_source_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_inbox_imports');
    }
};
