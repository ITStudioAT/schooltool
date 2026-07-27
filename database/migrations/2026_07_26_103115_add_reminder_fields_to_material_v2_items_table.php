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
            $table->date('reminder_date')->nullable()->after('description');
            $table->time('reminder_time')->nullable()->after('reminder_date');
            $table->index(
                ['school_id', 'user_id', 'reminder_date'],
                'material_v2_items_owner_reminder_date_index',
            );
        });
    }
};
