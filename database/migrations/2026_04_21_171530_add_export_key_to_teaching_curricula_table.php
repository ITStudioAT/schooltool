<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teaching_curricula') || Schema::hasColumn('teaching_curricula', 'export_key')) {
            return;
        }

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->uuid('export_key')->nullable()->after('description')->unique();
        });

        DB::table('teaching_curricula')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $curriculum): void {
                DB::table('teaching_curricula')
                    ->where('id', $curriculum->id)
                    ->whereNull('export_key')
                    ->update([
                        'export_key' => (string) Str::uuid(),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teaching_curricula') || ! Schema::hasColumn('teaching_curricula', 'export_key')) {
            return;
        }

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->dropUnique('teaching_curricula_export_key_unique');
            $table->dropColumn('export_key');
        });
    }
};
