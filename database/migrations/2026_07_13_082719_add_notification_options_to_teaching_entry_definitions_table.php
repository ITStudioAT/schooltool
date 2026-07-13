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
            $table->boolean('has_notifications')->default(false)->after('fixed_properties');
            $table->json('notification_recipients')->nullable()->after('has_notifications');
        });
    }
};
