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
        Schema::table('material_v2_items', function (Blueprint $table) {
            $table->foreignId('material_v2_cluster_id')
                ->nullable()
                ->after('user_id')
                ->constrained('material_v2_clusters')
                ->nullOnDelete();

            $table->index(
                ['school_id', 'user_id', 'material_v2_cluster_id'],
                'material_v2_items_owner_cluster_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_v2_items', function (Blueprint $table) {
            $table->dropIndex('material_v2_items_owner_cluster_index');
            $table->dropConstrainedForeignId('material_v2_cluster_id');
        });
    }
};
