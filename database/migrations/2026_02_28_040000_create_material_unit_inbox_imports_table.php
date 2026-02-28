<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_unit_inbox_imports')) {
            return;
        }

        Schema::create('material_unit_inbox_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_unit_id')->constrained('material_units')->cascadeOnDelete();
            $table->foreignId('source_rule_id')->constrained('material_share_rules')->cascadeOnDelete();
            $table->unsignedBigInteger('source_school_id');
            $table->unsignedBigInteger('source_unit_id');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['target_user_id', 'target_unit_id'], 'material_unit_inbox_imports_target_unique');
            $table->index(['source_school_id', 'source_unit_id'], 'material_unit_inbox_imports_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_unit_inbox_imports');
    }
};
