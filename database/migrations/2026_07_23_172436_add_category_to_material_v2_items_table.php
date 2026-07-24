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
            $table->string('category')->nullable()->after('title');
            $table->index(
                ['school_id', 'user_id', 'category'],
                'material_v2_items_owner_category_index',
            );
        });
    }
};
