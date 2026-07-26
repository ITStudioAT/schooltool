<?php

namespace App\Console\Commands;

use App\Jobs\MaterialsV2\ProcessMaterialV2Item;
use App\Models\MaterialV2Item;
use App\Services\MaterialsV2\MaterialV2KeywordService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

#[Signature(
    'materials:rebuild-automatic-tags
    {--material= : Nur ein Material verarbeiten}
    {--attachment= : Das Material einer bestimmten Anlage verarbeiten}
    {--force : Unveränderte Quellen erneut auswerten}
    {--sync : Direkt statt über die Queue verarbeiten}
    {--chunk=100 : Anzahl Materialien pro Datenbank-Chunk}
    {--dry-run : Alte und vorgeschlagene Tags anzeigen, ohne Daten zu ändern}',
)]
#[Description('Erstellt die lokalen automatischen Tags für Materialien neu')]
class RebuildMaterialV2AutomaticTags extends Command
{
    public function handle(MaterialV2KeywordService $keywordService): int
    {
        $chunkSize = (int) $this->option('chunk');
        if ($chunkSize < 1 || $chunkSize > 1000) {
            $this->error('Die Option --chunk muss zwischen 1 und 1000 liegen.');

            return self::FAILURE;
        }

        $query = $this->query();
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('Keine passenden Materialien gefunden.');

            return self::SUCCESS;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $isSynchronous = (bool) $this->option('sync');
        $force = (bool) $this->option('force');
        $processed = 0;
        $queued = 0;
        $failed = 0;
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $query->chunkById($chunkSize, function ($items) use (
            $keywordService,
            $isDryRun,
            $isSynchronous,
            $force,
            &$processed,
            &$queued,
            &$failed,
            $progressBar,
        ): void {
            foreach ($items as $item) {
                try {
                    if ($isDryRun) {
                        $this->renderPreview($item, $keywordService);
                        $processed++;
                    } elseif ($isSynchronous) {
                        ProcessMaterialV2Item::dispatchSync($item->id, $force);
                        $processed++;
                    } else {
                        ProcessMaterialV2Item::dispatch($item->id, $force)->afterCommit();
                        $queued++;
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    $this->newLine();
                    $this->error("Material {$item->id}: {$exception->getMessage()}");
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);
        $this->info(
            "Zusammenfassung: {$processed} verarbeitet, {$queued} eingereiht, {$failed} fehlgeschlagen.",
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Builder<MaterialV2Item>
     */
    private function query(): Builder
    {
        $query = MaterialV2Item::query()->orderBy('id');
        $materialId = filter_var($this->option('material'), FILTER_VALIDATE_INT);
        $attachmentId = filter_var($this->option('attachment'), FILTER_VALIDATE_INT);

        if ($materialId !== false && $materialId !== null) {
            $query->whereKey($materialId);
        }

        if ($attachmentId !== false && $attachmentId !== null) {
            $query->whereHas(
                'attachments',
                fn (Builder $attachmentQuery): Builder => $attachmentQuery->whereKey($attachmentId),
            );
        }

        return $query;
    }

    private function renderPreview(
        MaterialV2Item $item,
        MaterialV2KeywordService $keywordService,
    ): void {
        $this->newLine();
        $this->line("Material {$item->id}: {$item->title}");

        $previews = $keywordService->preview($item);
        if ($previews === []) {
            $this->line('  Keine auswertbare Anlage.');

            return;
        }

        foreach ($previews as $preview) {
            $oldTags = $preview['old_tags'] === [] ? '–' : implode(', ', $preview['old_tags']);
            $newTags = collect($preview['suggestions'])
                ->map(fn (array $suggestion): string => "{$suggestion['rank']}. {$suggestion['name']}")
                ->implode(', ');

            $this->line("  Anlage {$preview['attachment_id']}: {$preview['attachment_name']}");
            $this->line("    Alt: {$oldTags}");
            $this->line('    Neu: '.($newTags !== '' ? $newTags : '–'));
        }
    }
}
