<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_types')) {
            return;
        }

        if (! Schema::hasColumn('material_types', 'icon')) {
            Schema::table('material_types', function (Blueprint $table) {
                $table->string('icon', 100)
                    ->default('mdi-file-document-outline')
                    ->after('name');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_types')) {
            return;
        }

        if (Schema::hasColumn('material_types', 'icon')) {
            Schema::table('material_types', function (Blueprint $table) {
                $table->dropColumn('icon');
            });
        }
    }
};
