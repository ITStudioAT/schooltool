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
        Schema::dropIfExists('material_share_rule_archives');
        Schema::dropIfExists('material_topic_inbox_imports');
        Schema::dropIfExists('material_unit_inbox_imports');
        Schema::dropIfExists('material_inbox_imports');
        Schema::dropIfExists('material_share_targets');
        Schema::dropIfExists('material_share_rules');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('material_share_rules')) {
            Schema::create('material_share_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('scope_type', 24);
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['school_id', 'is_active']);
                $table->index(['school_id', 'scope_type', 'scope_id'], 'material_share_rules_scope_idx');
            });
        }

        if (! Schema::hasTable('material_share_targets')) {
            Schema::create('material_share_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('material_share_rule_id')->constrained('material_share_rules')->cascadeOnDelete();
                $table->string('target_type', 24);
                $table->string('audience_scope', 24)->nullable();
                $table->string('permission', 24)->default('read_only');
                $table->foreignId('user_group_id')->nullable()->constrained('user_groups')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['material_share_rule_id', 'target_type'], 'material_share_targets_rule_type_idx');
                $table->index(['user_group_id']);
                $table->index(['user_id']);
            });
        }

        if (! Schema::hasTable('material_inbox_imports')) {
            Schema::create('material_inbox_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('target_material_card_id')->constrained('material_cards')->cascadeOnDelete();
                $table->unsignedBigInteger('source_rule_id')->nullable();
                $table->unsignedBigInteger('source_school_id');
                $table->unsignedBigInteger('source_material_id');
                $table->string('import_mode', 16)->default('copy');
                $table->timestamp('imported_at');
                $table->timestamps();

                $table->unique(
                    ['target_user_id', 'target_material_card_id'],
                    'material_inbox_imports_target_card_unique'
                );
                $table->index(
                    ['target_user_id', 'source_school_id', 'source_material_id'],
                    'material_inbox_imports_target_source_index'
                );
                $table->index(
                    ['target_user_id', 'import_mode'],
                    'material_inbox_imports_target_mode_index'
                );
            });
        }

        if (! Schema::hasTable('material_unit_inbox_imports')) {
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

        if (! Schema::hasTable('material_topic_inbox_imports')) {
            Schema::create('material_topic_inbox_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('target_topic_id')->constrained('material_topics')->cascadeOnDelete();
                $table->foreignId('source_rule_id')->constrained('material_share_rules')->cascadeOnDelete();
                $table->unsignedBigInteger('source_school_id');
                $table->unsignedBigInteger('source_topic_id');
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();

                $table->unique(['target_user_id', 'target_topic_id'], 'material_topic_inbox_imports_target_unique');
                $table->index(['source_school_id', 'source_topic_id'], 'material_topic_inbox_imports_source_idx');
            });
        }

        if (! Schema::hasTable('material_share_rule_archives')) {
            Schema::create('material_share_rule_archives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('material_share_rule_id')->constrained('material_share_rules')->cascadeOnDelete();
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
    }
};
