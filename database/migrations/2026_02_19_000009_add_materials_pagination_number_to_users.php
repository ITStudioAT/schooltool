<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'materials_pagination_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('materials_pagination_number')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'materials_pagination_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('materials_pagination_number');
            });
        }
    }
};

