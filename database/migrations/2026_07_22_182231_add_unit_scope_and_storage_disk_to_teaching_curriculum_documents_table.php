<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_curriculum_documents', function (Blueprint $table) {
            $table->string('topic_id', 100)->nullable()->after('teaching_curriculum_id');
            $table->string('unit_id', 100)->nullable()->after('topic_id');
            $table->string('storage_disk')->nullable()->after('file_path');

            $table->index(
                ['teaching_curriculum_id', 'topic_id', 'unit_id'],
                'curriculum_documents_unit_scope_index'
            );
        });
    }
};
