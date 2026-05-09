<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_subjects') && ! Schema::hasColumn('material_subjects', 'deleted_at')) {
            Schema::table('material_subjects', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        if (Schema::hasTable('material_topics') && ! Schema::hasColumn('material_topics', 'deleted_at')) {
            Schema::table('material_topics', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        if (Schema::hasTable('material_units') && ! Schema::hasColumn('material_units', 'deleted_at')) {
            Schema::table('material_units', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        if (Schema::hasTable('material_subjects')) {
            Schema::table('material_subjects', function (Blueprint $table) {
                if (! $this->hasIndex('material_subjects', 'material_subjects_workspace_name_deleted_at_idx')) {
                    $table->index(
                        ['workspace_id', 'name', 'deleted_at'],
                        'material_subjects_workspace_name_deleted_at_idx'
                    );
                }
                if ($this->hasIndex('material_subjects', 'material_subjects_workspace_id_name_unique')) {
                    $table->dropUnique('material_subjects_workspace_id_name_unique');
                }
            });
        }

        if (Schema::hasTable('material_topics')) {
            Schema::table('material_topics', function (Blueprint $table) {
                if (! $this->hasIndex('material_topics', 'material_topics_subject_name_deleted_at_idx')) {
                    $table->index(
                        ['subject_id', 'name', 'deleted_at'],
                        'material_topics_subject_name_deleted_at_idx'
                    );
                }
                if ($this->hasIndex('material_topics', 'material_topics_subject_id_name_unique')) {
                    $table->dropUnique('material_topics_subject_id_name_unique');
                }
            });
        }

        if (Schema::hasTable('material_units')
            && ! $this->hasIndex('material_units', 'material_units_topic_name_deleted_at_idx')) {
            Schema::table('material_units', function (Blueprint $table) {
                $table->index(
                    ['topic_id', 'name', 'deleted_at'],
                    'material_units_topic_name_deleted_at_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('material_units')) {
            Schema::table('material_units', function (Blueprint $table) {
                if ($this->hasIndex('material_units', 'material_units_topic_name_deleted_at_idx')) {
                    $table->dropIndex('material_units_topic_name_deleted_at_idx');
                }
                if (Schema::hasColumn('material_units', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('material_topics')) {
            Schema::table('material_topics', function (Blueprint $table) {
                if ($this->hasIndex('material_topics', 'material_topics_subject_name_deleted_at_idx')) {
                    $table->dropIndex('material_topics_subject_name_deleted_at_idx');
                }
                if (! $this->hasIndex('material_topics', 'material_topics_subject_id_name_unique')) {
                    $table->unique(['subject_id', 'name'], 'material_topics_subject_id_name_unique');
                }
                if (Schema::hasColumn('material_topics', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('material_subjects')) {
            Schema::table('material_subjects', function (Blueprint $table) {
                if ($this->hasIndex('material_subjects', 'material_subjects_workspace_name_deleted_at_idx')) {
                    $table->dropIndex('material_subjects_workspace_name_deleted_at_idx');
                }
                if (! $this->hasIndex('material_subjects', 'material_subjects_workspace_id_name_unique')) {
                    $table->unique(['workspace_id', 'name'], 'material_subjects_workspace_id_name_unique');
                }
                if (Schema::hasColumn('material_subjects', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return Schema::hasIndex($tableName, $indexName);
    }
};
