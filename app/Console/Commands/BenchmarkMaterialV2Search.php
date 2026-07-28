<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MaterialsV2\MaterialV2ScoutSearchService;
use App\Services\MaterialsV2\MaterialV2SearchService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class BenchmarkMaterialV2Search extends Command
{
    protected $signature = 'materials-v2:benchmark-search
                            {--user= : Materials V2 owner user ID}
                            {--query=* : Search phrase; may be repeated}
                            {--category= : Optional exact category filter}
                            {--iterations=5 : Measured iterations per implementation}
                            {--per-page=18 : Result limit used for comparison}
                            {--json : Print the full report as JSON}';

    protected $description = 'Benchmarks the existing Materials V2 fuzzy search against Scout database search.';

    public function handle(
        MaterialV2SearchService $existingSearch,
        MaterialV2ScoutSearchService $scoutSearch,
    ): int {
        $userId = (int) $this->option('user');
        $queries = array_values(array_filter(
            array_map(
                fn (mixed $query): string => trim((string) $query),
                Arr::wrap($this->option('query')),
            ),
        ));
        $iterations = max(1, min(50, (int) $this->option('iterations')));
        $perPage = max(1, min(200, (int) $this->option('per-page')));
        $category = trim((string) $this->option('category'));

        if ($userId < 1 || $queries === []) {
            $this->error('Provide a valid --user ID and at least one --query value.');

            return self::INVALID;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            $this->error("User {$userId} was not found.");

            return self::FAILURE;
        }

        $reports = array_map(
            fn (string $query): array => $this->benchmarkQuery(
                user: $user,
                query: $query,
                category: $category,
                iterations: $iterations,
                perPage: $perPage,
                existingSearch: $existingSearch,
                scoutSearch: $scoutSearch,
            ),
            $queries,
        );

        $this->table(
            ['Query', 'Existing ms', 'Scout ms', 'Existing total', 'Scout total', 'Top-ID overlap'],
            array_map(fn (array $report): array => [
                $report['query'],
                number_format($report['existing']['average_ms'], 2),
                number_format($report['scout']['average_ms'], 2),
                $report['existing']['total'],
                $report['scout']['total'],
                $report['top_id_overlap'],
            ], $reports),
        );

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'driver' => config('scout.driver'),
                'user_id' => $user->id,
                'category' => $category !== '' ? $category : null,
                'iterations' => $iterations,
                'per_page' => $perPage,
                'queries' => $reports,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function benchmarkQuery(
        User $user,
        string $query,
        string $category,
        int $iterations,
        int $perPage,
        MaterialV2SearchService $existingSearch,
        MaterialV2ScoutSearchService $scoutSearch,
    ): array {
        $existingSearch->search($user, $query, 1, $perPage, $category);
        $scoutSearch->search($user, $query, 1, $perPage, $category);

        $existing = $this->measure(
            fn (): LengthAwarePaginator => $existingSearch->search(
                $user,
                $query,
                1,
                $perPage,
                $category,
            ),
            $iterations,
        );
        $scout = $this->measure(
            fn (): LengthAwarePaginator => $scoutSearch->search(
                $user,
                $query,
                1,
                $perPage,
                $category,
            ),
            $iterations,
        );

        return [
            'query' => $query,
            'existing' => $existing,
            'scout' => $scout,
            'top_id_overlap' => count(array_intersect($existing['ids'], $scout['ids'])),
        ];
    }

    /**
     * @param  callable(): LengthAwarePaginator  $search
     * @return array{average_ms: float, minimum_ms: float, maximum_ms: float, total: int, ids: array<int, int>}
     */
    private function measure(callable $search, int $iterations): array
    {
        $durations = [];
        $result = null;

        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $startedAt = hrtime(true);
            $result = $search();
            $durations[] = (hrtime(true) - $startedAt) / 1_000_000;
        }

        return [
            'average_ms' => array_sum($durations) / count($durations),
            'minimum_ms' => min($durations),
            'maximum_ms' => max($durations),
            'total' => $result?->total() ?? 0,
            'ids' => collect($result?->items() ?? [])
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all(),
        ];
    }
}
