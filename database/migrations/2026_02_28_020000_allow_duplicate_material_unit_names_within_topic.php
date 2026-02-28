<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_units')) {
            return;
        }

        Schema::table('material_units', function (Blueprint $table) {
            $table->dropUnique('material_units_topic_id_name_unique');
        });

        Schema::table('material_units', function (Blueprint $table) {
            $table->index(['topic_id', 'name'], 'material_units_topic_id_name_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_units')) {
            return;
        }

        Schema::table('material_units', function (Blueprint $table) {
            $table->dropIndex('material_units_topic_id_name_index');
        });

        Schema::table('material_units', function (Blueprint $table) {
            $table->unique(['topic_id', 'name'], 'material_units_topic_id_name_unique');
        });
    }
};

