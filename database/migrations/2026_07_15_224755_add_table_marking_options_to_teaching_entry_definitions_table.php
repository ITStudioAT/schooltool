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
        Schema::table('teaching_entry_definitions', function (Blueprint $table) {
            $table->boolean('has_table_marking')->default(false)->after('notification_recipients');
            $table->string('table_marking_color', 20)->nullable()->after('has_table_marking');
        });
    }
};
