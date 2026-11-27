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
        if (! Schema::hasTable('teaching_assessment_cache')) {
            Schema::create('teaching_assessment_cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration')->index();
            });
        }

        $source = DB::connection(config('cache.stores.database.connection'));
        $prefix = (string) config('cache.prefix');
        $runs = $prefix.'teaching-assessment-run:';
        $plans = $prefix.'teaching:direct-assessment:';

        $source->table(config('cache.stores.database.table'))
            ->where('expiration', '>', now()->getTimestamp())
            ->where(fn ($query) => $query->where('key', 'like', $runs.'%')->orWhere('key', 'like', $plans.'%'))
            ->orderBy('key')
            ->chunk(200, function ($entries) use ($runs, $plans): void {
                $rows = $entries->filter(fn ($entry): bool => str_starts_with($entry->key, $runs) || str_starts_with($entry->key, $plans))
                    ->map(fn ($entry): array => (array) $entry)->values()->all();
                if ($rows !== []) {
                    DB::table('teaching_assessment_cache')->insertOrIgnore($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_assessment_cache');
    }
};
