<?php

namespace App\Jobs\MaterialsV2;

use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Item;
use App\Services\MaterialsV2\MaterialV2DocumentTextExtractor;
use App\Services\MaterialsV2\MaterialV2KeywordService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMaterialV2Item implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 900;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $itemId) {}

    public function uniqueId(): string
    {
        return (string) $this->itemId;
    }

    public function handle(
        MaterialV2DocumentTextExtractor $extractor,
        MaterialV2KeywordService $keywordService,
    ): void {
        $item = MaterialV2Item::query()->with('attachments')->find($this->itemId);
        if (! $item) {
            return;
        }

        $item->update([
            'processing_status' => MaterialV2Item::STATUS_PROCESSING,
            'processing_error' => null,
            'processing_started_at' => now(),
            'processed_at' => null,
        ]);

        $failedAttachments = 0;

        foreach ($item->attachments as $attachment) {
            if ($attachment->extraction_status === MaterialV2Attachment::STATUS_READY) {
                continue;
            }

            if (! $extractor->supports($attachment)) {
                $attachment->update([
                    'extraction_status' => MaterialV2Attachment::STATUS_UNSUPPORTED,
                    'extraction_error' => null,
                    'extracted_at' => now(),
                ]);

                continue;
            }

            $attachment->update([
                'extraction_status' => MaterialV2Attachment::STATUS_PROCESSING,
                'extraction_error' => null,
            ]);

            try {
                $attachment->update([
                    'extracted_text' => $extractor->extract($attachment),
                    'extraction_status' => MaterialV2Attachment::STATUS_READY,
                    'extraction_error' => null,
                    'extracted_at' => now(),
                ]);
            } catch (Throwable $exception) {
                $failedAttachments++;
                $attachment->update([
                    'extraction_status' => MaterialV2Attachment::STATUS_FAILED,
                    'extraction_error' => $exception->getMessage(),
                    'extracted_at' => now(),
                ]);

                Log::warning('materials_v2.attachment_extraction_failed', [
                    'material_v2_item_id' => $item->id,
                    'material_v2_attachment_id' => $attachment->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $item->refresh()->load('attachments');
        $item->generated_keywords = $keywordService->generate($item);
        $item->search_text = $keywordService->rebuildSearchText($item);
        $item->processing_status = $failedAttachments > 0
            ? MaterialV2Item::STATUS_PARTIAL
            : MaterialV2Item::STATUS_READY;
        $item->processing_error = $failedAttachments > 0
            ? "{$failedAttachments} Anlage(n) konnten nicht gelesen werden."
            : null;
        $item->processed_at = now();
        $item->save();
    }

    public function failed(?Throwable $exception): void
    {
        MaterialV2Item::query()->whereKey($this->itemId)->update([
            'processing_status' => MaterialV2Item::STATUS_FAILED,
            'processing_error' => $exception?->getMessage() ?? 'Die Verarbeitung ist fehlgeschlagen.',
            'processed_at' => now(),
        ]);
    }
}
