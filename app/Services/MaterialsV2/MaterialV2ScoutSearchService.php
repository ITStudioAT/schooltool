<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Item;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MaterialV2ScoutSearchService
{
    public function search(
        User $user,
        string $search,
        int $page,
        int $perPage,
        string $category = '',
        int $clusterId = 0,
        string $reminderFrom = '',
        string $reminderTo = '',
        string $reminderOrder = '',
    ): LengthAwarePaginator {
        $normalizedSearch = Str::squish($search);
        $normalizedCategory = Str::squish($category);

        if ($normalizedSearch === '') {
            return $this->browse(
                user: $user,
                page: $page,
                perPage: $perPage,
                category: $normalizedCategory,
                clusterId: $clusterId,
                reminderFrom: $reminderFrom,
                reminderTo: $reminderTo,
                reminderOrder: $reminderOrder,
            );
        }

        return MaterialV2Item::search($normalizedSearch)
            ->query(function (Builder $query) use (
                $user,
                $normalizedCategory,
                $clusterId,
                $reminderFrom,
                $reminderTo,
            ): void {
                $query
                    ->whereBelongsTo($user)
                    ->where('school_id', $user->school_id);

                $this->applyFilters(
                    query: $query,
                    category: $normalizedCategory,
                    clusterId: $clusterId,
                    reminderFrom: $reminderFrom,
                    reminderTo: $reminderTo,
                );
            })
            ->paginate($perPage, 'page', $page);
    }

    private function browse(
        User $user,
        int $page,
        int $perPage,
        string $category,
        int $clusterId,
        string $reminderFrom,
        string $reminderTo,
        string $reminderOrder,
    ): LengthAwarePaginator {
        $query = MaterialV2Item::query()
            ->whereBelongsTo($user)
            ->where('school_id', $user->school_id);

        $this->applyFilters($query, $category, $clusterId, $reminderFrom, $reminderTo);

        if (
            $category === MaterialV2CategoryService::REMINDER_CATEGORY
            && in_array($reminderOrder, ['asc', 'desc'], true)
        ) {
            return $query
                ->orderBy('reminder_date', $reminderOrder)
                ->orderBy('reminder_time', $reminderOrder)
                ->latest('id')
                ->paginate($perPage, page: $page);
        }

        if ($category === MaterialV2CategoryService::REMINDER_CATEGORY) {
            return $query
                ->orderByRaw('CASE WHEN reminder_date >= ? THEN 0 ELSE 1 END', [now()->toDateString()])
                ->orderByRaw(
                    'CASE WHEN reminder_date >= ? THEN reminder_date END ASC',
                    [now()->toDateString()],
                )
                ->orderByRaw(
                    'CASE WHEN reminder_date < ? THEN reminder_date END DESC',
                    [now()->toDateString()],
                )
                ->orderBy('reminder_time')
                ->latest('id')
                ->paginate($perPage, page: $page);
        }

        return $query
            ->latest()
            ->paginate($perPage, page: $page);
    }

    private function applyFilters(
        Builder $query,
        string $category,
        int $clusterId,
        string $reminderFrom,
        string $reminderTo,
    ): void {
        $query
            ->with(['attachments', 'automaticTagSuggestions', 'cluster'])
            ->when(
                $category !== '',
                fn (Builder $query): Builder => $query->where('category', $category),
            )
            ->when(
                $clusterId > 0,
                fn (Builder $query): Builder => $query->where('material_v2_cluster_id', $clusterId),
            )
            ->when(
                $reminderFrom !== '',
                fn (Builder $query): Builder => $query->whereDate('reminder_date', '>=', $reminderFrom),
            )
            ->when(
                $reminderTo !== '',
                fn (Builder $query): Builder => $query->whereDate('reminder_date', '<=', $reminderTo),
            );
    }
}
