<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_statuses')) {
            return;
        }

        if (! Schema::hasColumn('material_statuses', 'color')) {
            Schema::table('material_statuses', function (Blueprint $table) {
                $table->string('color', 20)
                    ->nullable()
                    ->after('label');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_statuses')) {
            return;
        }

        if (Schema::hasColumn('material_statuses', 'color')) {
            Schema::table('material_statuses', function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
