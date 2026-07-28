<?php

namespace App\Services\Materials;

use App\Jobs\SynchronizeMaterialLinkedContent;
use App\Models\MaterialCard;
use App\Models\MaterialInboxImport;
use App\Models\MaterialTopicInboxImport;
use App\Models\MaterialUnitInboxImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class MaterialLinkedContentSynchronizer
{
    public function dispatchForSourceCard(MaterialCard $sourceCard): void
    {
        $schoolId = (int) ($sourceCard->school_id ?? 0);
        $cardId = (int) ($sourceCard->id ?? 0);

        if (
            $schoolId <= 0
            || $cardId <= 0
            || ! Schema::hasTable('material_inbox_imports')
            || ! Schema::hasColumn('material_inbox_imports', 'import_mode')
        ) {
            return;
        }

        $this->dispatchTargetUsers(
            MaterialInboxImport::query()
                ->where('source_school_id', $schoolId)
                ->where('source_material_id', $cardId)
                ->where('import_mode', MaterialInboxImport::MODE_LINK)
        );
    }

    public function dispatchForSourceTopic(int $schoolId, int $topicId): void
    {
        if ($schoolId <= 0 || $topicId <= 0 || ! Schema::hasTable('material_topic_inbox_imports')) {
            return;
        }

        $this->dispatchTargetUsers(
            MaterialTopicInboxImport::query()
                ->where('source_school_id', $schoolId)
                ->where('source_topic_id', $topicId)
        );
    }

    public function dispatchForSourceUnit(int $schoolId, int $unitId): void
    {
        if ($schoolId <= 0 || $unitId <= 0 || ! Schema::hasTable('material_unit_inbox_imports')) {
            return;
        }

        $this->dispatchTargetUsers(
            MaterialUnitInboxImport::query()
                ->where('source_school_id', $schoolId)
                ->where('source_unit_id', $unitId)
        );
    }

    private function dispatchTargetUsers(Builder $query): void
    {
        $query
            ->whereNotNull('target_user_id')
            ->distinct()
            ->pluck('target_user_id')
            ->map(fn ($userId): int => (int) $userId)
            ->filter(fn (int $userId): bool => $userId > 0)
            ->each(
                fn (int $userId) => SynchronizeMaterialLinkedContent::dispatch($userId)->afterCommit()
            );
    }
}
