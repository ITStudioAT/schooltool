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
        Schema::create('material_share_rule_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('material_share_rule_id')
                ->constrained('material_share_rules')
                ->cascadeOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['target_user_id', 'material_share_rule_id'],
                'material_share_rule_archives_target_rule_unique'
            );
            $table->index(
                ['target_user_id', 'archived_at'],
                'material_share_rule_archives_target_archived_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_share_rule_archives');
    }
};
