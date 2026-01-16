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

        if (! Schema::hasColumn('school_tools', 'may_visible_for_other_schools')) {
            Schema::table('school_tools', function (Blueprint $table) {
                $table->boolean('may_visible_for_other_schools')->default(false);
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

        if (Schema::hasColumn('school_tools', 'may_visible_for_other_schools')) {
            Schema::table('school_tools', function (Blueprint $table) {
                $table->dropColumn('may_visible_for_other_schools');
            });
        }
    }
};
