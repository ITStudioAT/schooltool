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
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        if (! Schema::hasColumn('school_tools', 'import_166_at')) {
            Schema::table('school_tools', function (Blueprint $table) {
                $table->timestamp('import_166_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        if (Schema::hasColumn('school_tools', 'import_166_at')) {
            Schema::table('school_tools', function (Blueprint $table) {
                $table->dropColumn('import_166_at');
            });
        }
    }
};
