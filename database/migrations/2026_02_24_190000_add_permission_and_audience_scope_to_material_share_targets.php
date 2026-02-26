<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_share_targets', function (Blueprint $table) {
            if (! Schema::hasColumn('material_share_targets', 'audience_scope')) {
                $table->string('audience_scope', 24)->nullable()->after('target_type');
            }

            if (! Schema::hasColumn('material_share_targets', 'permission')) {
                $table->string('permission', 24)->default('read_only')->after('audience_scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_share_targets', function (Blueprint $table) {
            if (Schema::hasColumn('material_share_targets', 'permission')) {
                $table->dropColumn('permission');
            }
            if (Schema::hasColumn('material_share_targets', 'audience_scope')) {
                $table->dropColumn('audience_scope');
            }
        });
    }
};
