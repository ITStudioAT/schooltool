<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || Schema::hasColumn('roles', 'is_admin')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('guard_name');
        });

        if (Schema::hasColumn('roles', 'is_admin')) {
            DB::table('roles')
                ->whereIn('name', [
                    'admin',
                    'register_admin',
                    'teaching_admin',
                    'materials_admin',
                    'materials_moderator',
                    'teacher',
                    'lunch_admin',
                    'aba_teacher',
                ])
                ->update(['is_admin' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'is_admin')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
