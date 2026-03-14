<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abas', function (Blueprint $table) {
            $table->string('student_class', 100)->nullable()->after('student_name');
        });
    }

    public function down(): void
    {
        Schema::table('abas', function (Blueprint $table) {
            $table->dropColumn('student_class');
        });
    }
};
