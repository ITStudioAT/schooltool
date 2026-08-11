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
        Schema::table('school_tools', function (Blueprint $table) {
            $table->string('students_timetables_admin_version', 2)
                ->default('v2')
                ->after('students_timetables_user_comming_soon');
        });
    }
};
