<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_tools', function (Blueprint $table) {
            $table->string('students_timetables_admin_version', 2)
                ->default('v3')
                ->change();
        });

        DB::table('school_tools')
            ->where('students_timetables_admin_version', 'v2')
            ->update(['students_timetables_admin_version' => 'v3']);
    }
};
