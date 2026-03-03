<?php

namespace App\Console\Commands;

use App\Models\Import116;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MergeImport116DuplicateUsersCommand extends Command
{
    protected $signature = 'users:merge-import116-duplicates
        {--apply : Persist changes (default is dry-run)}
        {--school-id=* : Limit to one or more school ids}
        {--import116-id=* : Limit to one or more import116 ids}';

    protected $description = 'Merge duplicate users that share the same school_id + import116_id.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $schoolIds = $this->normalizedIntegerOptionValues('school-id');
        $importIds = $this->normalizedIntegerOptionValues('import116-id');

        $groups = $this->duplicateGroups($schoolIds, $importIds);
        if ($groups->isEmpty()) {
            $this->info('No duplicate import116-linked users found.');

            return self::SUCCESS;
        }

        $userReferenceColumns = $this->userReferenceColumns();
        $mode = $apply ? 'APPLY' : 'DRY-RUN';
        $this->line("Mode: {$mode}");
        $this->line('Duplicate groups: '.$groups->count());
        $this->line('Reference columns to update: '.$userReferenceColumns->count());

        $mergedCount = 0;
        $skippedCount = 0;
        $rowsTouched = 0;

        foreach ($groups as $group) {
            $schoolId = (int) $group->school_id;
            $import116Id = (int) $group->import116_id;

            $users = User::query()
                ->where('school_id', $schoolId)
                ->where('import116_id', $import116Id)
                ->orderBy('id')
                ->get();

            if ($users->count() < 2) {
                continue;
            }

            $importRow = Import116::query()->find($import116Id);
            $keeper = $this->resolveKeeper($users, $importRow?->email);
            if (! $keeper) {
                $skippedCount += $users->count();
                $this->warn("Skipping school {$schoolId}, import116 {$import116Id}: no keeper candidate.");

                continue;
            }

            $duplicates = $users->where('id', '!=', $keeper->id)->values();
            $this->line(
                "school_id={$schoolId}, import116_id={$import116Id}, keeper={$keeper->id}, duplicates=[".
                $duplicates->pluck('id')->implode(',').
                ']'
            );

            if (! $apply) {
                continue;
            }

            foreach ($duplicates as $duplicate) {
                try {
                    $updatedRowsForDuplicate = $this->mergeDuplicateUser(
                        $keeper,
                        $duplicate,
                        $userReferenceColumns
                    );

                    $rowsTouched += $updatedRowsForDuplicate;
                    $mergedCount++;
                } catch (QueryException $exception) {
                    $skippedCount++;
                    $this->warn(
                        "Skipped duplicate user {$duplicate->id} -> {$keeper->id}: {$exception->getMessage()}"
                    );
                }
            }
        }

        if (! $apply) {
            $this->info('Dry-run only. Re-run with --apply to persist.');

            return self::SUCCESS;
        }

        $this->line('Merged users: '.$mergedCount);
        $this->line('Skipped users: '.$skippedCount);
        $this->line('Updated reference rows: '.$rowsTouched);

        if ($skippedCount > 0) {
            $this->warn('Some users could not be merged automatically.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function normalizedIntegerOptionValues(string $option): array
    {
        return array_values(
            array_unique(
                array_filter(
                    array_map('intval', (array) $this->option($option)),
                    fn (int $id): bool => $id > 0
                )
            )
        );
    }

    private function duplicateGroups(array $schoolIds, array $importIds): Collection
    {
        $query = DB::table('users')
            ->select([
                'school_id',
                'import116_id',
                DB::raw('COUNT(*) as duplicate_count'),
            ])
            ->whereNotNull('import116_id')
            ->groupBy('school_id', 'import116_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('school_id')
            ->orderBy('import116_id');

        if (! empty($schoolIds)) {
            $query->whereIn('school_id', $schoolIds);
        }

        if (! empty($importIds)) {
            $query->whereIn('import116_id', $importIds);
        }

        return collect($query->get());
    }

    private function resolveKeeper(Collection $users, ?string $importEmail): ?User
    {
        $normalizedImportEmail = mb_strtolower(trim((string) $importEmail));
        if ($users->isEmpty()) {
            return null;
        }

        /** @var Collection<int, User> $sorted */
        $sorted = $users->sort(function (User $left, User $right) use ($normalizedImportEmail): int {
            $leftScore = $this->keeperScore($left, $normalizedImportEmail);
            $rightScore = $this->keeperScore($right, $normalizedImportEmail);

            if ($leftScore === $rightScore) {
                return (int) $right->id <=> (int) $left->id;
            }

            return $rightScore <=> $leftScore;
        })->values();

        return $sorted->first();
    }

    private function keeperScore(User $user, string $normalizedImportEmail): int
    {
        $normalizedUserEmail = mb_strtolower(trim((string) $user->email));

        $score = 0;
        if ($normalizedImportEmail !== '' && $normalizedUserEmail === $normalizedImportEmail) {
            $score += 1000;
        }
        if (! $this->isPlaceholderEmail($normalizedUserEmail)) {
            $score += 100;
        }
        if ($user->email_verified_at) {
            $score += 20;
        }
        if ($user->confirmed_at) {
            $score += 20;
        }
        if ($user->login_at) {
            $score += 10;
        }

        return $score;
    }

    private function isPlaceholderEmail(string $normalizedEmail): bool
    {
        return $normalizedEmail !== '' && str_ends_with($normalizedEmail, '@schooltool.noemail');
    }

    private function userReferenceColumns(): Collection
    {
        $rows = DB::select(
            'SELECT table_name AS table_name, column_name AS column_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND column_name LIKE ?
               AND table_name <> ?
             ORDER BY table_name, column_name',
            ['%user_id', 'users']
        );

        return collect($rows)
            ->map(function (object $row): array {
                $table = isset($row->table_name) ? (string) $row->table_name : (string) ($row->TABLE_NAME ?? '');
                $column = isset($row->column_name) ? (string) $row->column_name : (string) ($row->COLUMN_NAME ?? '');

                return [
                    'table' => $table,
                    'column' => $column,
                ];
            })
            ->reject(fn (array $entry): bool => $entry['table'] === 'import116' && $entry['column'] === 'import_user_id')
            ->values();
    }

    private function mergeDuplicateUser(User $keeper, User $duplicate, Collection $userReferenceColumns): int
    {
        return DB::transaction(function () use ($keeper, $duplicate, $userReferenceColumns): int {
            $updatedRows = 0;

            $roleNames = $duplicate->roles()
                ->pluck('name')
                ->map(fn ($roleName): string => trim((string) $roleName))
                ->filter()
                ->values()
                ->all();

            if (! empty($roleNames)) {
                $keeper->assignRole($roleNames);
            }

            foreach ($userReferenceColumns as $columnReference) {
                $updatedRows += DB::table($columnReference['table'])
                    ->where($columnReference['column'], (int) $duplicate->id)
                    ->update([$columnReference['column'] => (int) $keeper->id]);
            }

            $duplicate->syncRoles([]);
            $duplicate->syncPermissions([]);
            $duplicate->delete();

            return $updatedRows;
        });
    }
}
