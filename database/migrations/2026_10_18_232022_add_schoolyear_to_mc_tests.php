<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mc_tests', function (Blueprint $table): void {
            $table->foreignId('schoolyear_id')->nullable()->constrained('schoolyears');
            $table->index(['school_id', 'owner_id', 'schoolyear_id']);
        });

        DB::table('mc_tests')->whereNull('schoolyear_id')->orderBy('id')->chunkById(100, function ($tests): void {
            foreach ($tests as $test) {
                if ($test->created_at === null) {
                    continue;
                }
                $date = substr((string) $test->created_at, 0, 10);
                $years = DB::table('schoolyears')->where('school_id', $test->school_id)
                    ->whereDate('from', '<=', $date)->whereDate('until', '>=', $date)->pluck('id');
                if ($years->count() === 1) {
                    DB::table('mc_tests')->where('id', $test->id)->whereNull('schoolyear_id')->update(['schoolyear_id' => $years->first()]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('mc_tests', function (Blueprint $table): void {
            $table->dropForeign(['schoolyear_id']);
            $table->dropIndex(['school_id', 'owner_id', 'schoolyear_id']);
            $table->dropColumn('schoolyear_id');
        });
    }
};
