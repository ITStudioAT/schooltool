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
            $table->string('link_url', 2048)->nullable()->after('reminder_time');
        });
    }
};
