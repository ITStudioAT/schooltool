<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_subjects', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('name');
            $table->index(['user_id', 'sort_order'], 'material_subjects_user_sort_order_index');
        });

        Schema::table('material_topics', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('name');
            $table->index(['subject_id', 'sort_order'], 'material_topics_subject_sort_order_index');
        });

        Schema::table('material_units', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('name');
            $table->index(['topic_id', 'sort_order'], 'material_units_topic_sort_order_index');
        });

        $subjectOwnerIds = DB::table('material_subjects')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($subjectOwnerIds as $userId) {
            $subjectIds = DB::table('material_subjects')
                ->where('user_id', $userId)
                ->orderBy('name')
                ->orderBy('id')
                ->pluck('id')
                ->values()
                ->all();

            foreach ($subjectIds as $index => $subjectId) {
                DB::table('material_subjects')
                    ->where('id', $subjectId)
                    ->update(['sort_order' => $index + 1]);
            }
        }

        $topicParentIds = DB::table('material_topics')
            ->select('subject_id')
            ->distinct()
            ->pluck('subject_id');

        foreach ($topicParentIds as $subjectId) {
            $topicIds = DB::table('material_topics')
                ->where('subject_id', $subjectId)
                ->orderBy('name')
                ->orderBy('id')
                ->pluck('id')
                ->values()
                ->all();

            foreach ($topicIds as $index => $topicId) {
                DB::table('material_topics')
                    ->where('id', $topicId)
                    ->update(['sort_order' => $index + 1]);
            }
        }

        $unitParentIds = DB::table('material_units')
            ->select('topic_id')
            ->distinct()
            ->pluck('topic_id');

        foreach ($unitParentIds as $topicId) {
            $unitIds = DB::table('material_units')
                ->where('topic_id', $topicId)
                ->orderBy('name')
                ->orderBy('id')
                ->pluck('id')
                ->values()
                ->all();

            foreach ($unitIds as $index => $unitId) {
                DB::table('material_units')
                    ->where('id', $unitId)
                    ->update(['sort_order' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('material_units', function (Blueprint $table) {
            $table->dropIndex('material_units_topic_sort_order_index');
            $table->dropColumn('sort_order');
        });

        Schema::table('material_topics', function (Blueprint $table) {
            $table->dropIndex('material_topics_subject_sort_order_index');
            $table->dropColumn('sort_order');
        });

        Schema::table('material_subjects', function (Blueprint $table) {
            $table->dropIndex('material_subjects_user_sort_order_index');
            $table->dropColumn('sort_order');
        });
    }
};
