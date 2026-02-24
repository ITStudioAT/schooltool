<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_share_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_type', 24); // all|subject|topic|unit|material
            $table->unsignedBigInteger('scope_id')->nullable(); // null for "all"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'is_active']);
            $table->index(['school_id', 'scope_type', 'scope_id'], 'material_share_rules_scope_idx');
        });

        Schema::create('material_share_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_share_rule_id')->constrained('material_share_rules')->cascadeOnDelete();
            $table->string('target_type', 24); // everyone|group|user
            $table->foreignId('user_group_id')->nullable()->constrained('user_groups')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_share_rule_id', 'target_type'], 'material_share_targets_rule_type_idx');
            $table->index(['user_group_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_share_targets');
        Schema::dropIfExists('material_share_rules');
    }
};
