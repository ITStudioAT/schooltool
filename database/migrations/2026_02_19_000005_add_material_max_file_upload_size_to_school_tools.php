<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        if (! Schema::hasColumn('school_tools', 'material_max_file_upload_size')) {
            Schema::table('school_tools', function (Blueprint $table) {
                $table->unsignedInteger('material_max_file_upload_size')
                    ->default(20480)
                    ->after('tutoring_max_offers_per_student');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        if (Schema::hasColumn('school_tools', 'material_max_file_upload_size')) {
            Schema::table('school_tools', function (Blueprint $table) {
                $table->dropColumn('material_max_file_upload_size');
            });
        }
    }
};
