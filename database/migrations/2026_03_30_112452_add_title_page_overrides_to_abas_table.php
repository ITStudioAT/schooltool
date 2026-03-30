<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abas', function (Blueprint $table) {
            $table->json('title_page_overrides')->nullable()->after('student_class');
        });
    }

    public function down(): void
    {
        Schema::table('abas', function (Blueprint $table) {
            $table->dropColumn('title_page_overrides');
        });
    }
};
