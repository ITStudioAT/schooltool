<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Item;
use App\Models\MaterialV2TagSuggestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MaterialV2KeywordService
{
    public function __construct(
        private MaterialV2TextPreparer $textPreparer,
        private MaterialV2KeywordCandidateExtractor $candidateExtractor,
        private MaterialV2KeywordRanker $ranker,
    ) {}

    /**
     * @return array<int, string>
     */
    public function generate(MaterialV2Item $item): array
    {
        return collect($this->preview($item))
            ->flatMap(fn (array $attachment): array => $attachment['suggestions'])
            ->sortByDesc('final_score')
            ->unique('normalized_name')
            ->take((int) config('material-keywords.maximum_tags', 10))
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     keywords:array<int,string>,
     *     processed:int,
     *     skipped:int,
     *     failed:int
     * }
     */
    public function extractAndPersist(MaterialV2Item $item, bool $force = false): array
    {
        $item->loadMissing('attachments');
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($item->attachments as $attachment) {
            if ($attachment->extraction_status !== MaterialV2Attachment::STATUS_READY) {
                $this->markKeywordExtractionSkipped($attachment);
                $skipped++;

                continue;
            }

            $sourceHash = $this->sourceHash($item, $attachment);
            if (
                ! $force
                && hash_equals((string) $attachment->keyword_source_hash, $sourceHash)
                && in_array(
                    $attachment->keyword_extraction_status,
                    [MaterialV2Attachment::KEYWORD_STATUS_READY, MaterialV2Attachment::KEYWORD_STATUS_EMPTY],
                    true,
                )
            ) {
                $skipped++;

                continue;
            }

            $attachment->update([
                'keyword_extraction_status' => MaterialV2Attachment::KEYWORD_STATUS_PROCESSING,
                'keyword_extraction_error' => null,
            ]);

            try {
                $result = $this->extractAttachment($item, $attachment);
                $this->replaceAttachmentSuggestions(
                    $item,
                    $attachment,
                    $result['suggestions'],
                    $sourceHash,
                    $result['language'],
                );
                $processed++;
            } catch (Throwable $exception) {
                $failed++;
                $attachment->update([
                    'keyword_extraction_status' => MaterialV2Attachment::KEYWORD_STATUS_FAILED,
                    'keyword_extraction_error' => Str::limit($exception->getMessage(), 2000, ''),
                    'keywords_extracted_at' => now(),
                ]);

                Log::warning('materials_v2.keyword_extraction_failed', [
                    'material_v2_item_id' => $item->id,
                    'material_v2_attachment_id' => $attachment->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $keywords = $this->syncItemKeywords($item);

        return [
            'keywords' => $keywords,
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<int, array{
     *     attachment_id:int,
     *     attachment_name:string,
     *     old_tags:array<int,string>,
     *     suggestions:array<int,array{
     *         name:string,
     *         normalized_name:string,
     *         base_score:float,
     *         final_score:float,
     *         rank:int,
     *         source_locations:array<string,mixed>
     *     }>
     * }>
     */
    public function preview(MaterialV2Item $item): array
    {
        $item->loadMissing(['attachments', 'automaticTagSuggestions']);

        return $item->attachments
            ->filter(
                fn (MaterialV2Attachment $attachment): bool => $attachment->extraction_status
                    === MaterialV2Attachment::STATUS_READY,
            )
            ->map(function (MaterialV2Attachment $attachment, int $index) use ($item): array {
                $existingTags = $item->automaticTagSuggestions
                    ->where('material_v2_attachment_id', $attachment->id)
                    ->sortBy('rank')
                    ->pluck('tag_name')
                    ->values()
                    ->all();

                if ($existingTags === [] && $index === 0) {
                    $existingTags = $item->generated_keywords ?? [];
                }

                return [
                    'attachment_id' => $attachment->id,
                    'attachment_name' => $attachment->original_name,
                    'old_tags' => $existingTags,
                    'suggestions' => $this->extractAttachment($item, $attachment)['suggestions'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function dismissAutomaticTag(MaterialV2Item $item, string $tagName): array
    {
        $normalizedName = $this->normalize($tagName);

        MaterialV2TagSuggestion::query()
            ->where('material_v2_item_id', $item->id)
            ->where('normalized_name', $normalizedName)
            ->update(['dismissed_at' => now()]);

        return $this->syncItemKeywords($item);
    }

    /**
     * @return array<int, string>
     */
    public function convertAutomaticTagToManual(MaterialV2Item $item, string $tagName): array
    {
        return DB::transaction(function () use ($item, $tagName): array {
            $manualTags = collect($item->user_keywords ?? [])
                ->push(Str::squish($tagName))
                ->filter()
                ->unique(fn (string $tag): string => $this->normalize($tag))
                ->take(20)
                ->values()
                ->all();

            $item->update(['user_keywords' => $manualTags]);

            return $this->dismissAutomaticTag($item, $tagName);
        });
    }

    public function rebuildSearchText(MaterialV2Item $item): string
    {
        $item->loadMissing('attachments');

        return Str::limit(
            collect([
                $item->title,
                $item->description,
                $item->link_url,
                ...($item->user_keywords ?? []),
                ...($item->generated_keywords ?? []),
                ...$item->attachments->pluck('original_name')->all(),
                ...$item->attachments->pluck('extracted_text')->all(),
            ])->filter()->implode("\n"),
            120000,
            '',
        );
    }

    /**
     * @return array{
     *     language:string,
     *     suggestions:array<int, array{
     *         name:string,
     *         normalized_name:string,
     *         base_score:float,
     *         final_score:float,
     *         rank:int,
     *         source_locations:array<string,mixed>
     *     }>
     * }
     */
    private function extractAttachment(
        MaterialV2Item $item,
        MaterialV2Attachment $attachment,
    ): array {
        $document = $this->textPreparer->prepare((string) $attachment->extracted_text);
        $candidates = $this->candidateExtractor->extract(
            $document,
            (string) $item->title,
            (string) $attachment->original_name,
        );

        return [
            'language' => $document['language'],
            'suggestions' => $this->ranker->rank($candidates, $item),
        ];
    }

    /**
     * @param  array<int, array{
     *     name:string,
     *     normalized_name:string,
     *     base_score:float,
     *     final_score:float,
     *     rank:int,
     *     source_locations:array<string,mixed>
     * }>  $suggestions
     */
    private function replaceAttachmentSuggestions(
        MaterialV2Item $item,
        MaterialV2Attachment $attachment,
        array $suggestions,
        string $sourceHash,
        string $language,
    ): void {
        DB::transaction(function () use (
            $item,
            $attachment,
            $suggestions,
            $sourceHash,
            $language,
        ): void {
            $dismissedNames = $attachment->automaticTagSuggestions()
                ->whereNotNull('dismissed_at')
                ->pluck('normalized_name')
                ->flip();

            $attachment->automaticTagSuggestions()->delete();

            foreach ($suggestions as $suggestion) {
                $attachment->automaticTagSuggestions()->create([
                    'material_v2_item_id' => $item->id,
                    'tag_name' => $suggestion['name'],
                    'normalized_name' => $suggestion['normalized_name'],
                    'base_score' => $suggestion['base_score'],
                    'final_score' => $suggestion['final_score'],
                    'rank' => $suggestion['rank'],
                    'algorithm' => sprintf(
                        '%s@%s',
                        config('material-keywords.algorithm'),
                        config('material-keywords.algorithm_version'),
                    ),
                    'language' => $language,
                    'source_locations' => $suggestion['source_locations'],
                    'source_hash' => $sourceHash,
                    'dismissed_at' => $dismissedNames->has($suggestion['normalized_name'])
                        ? now()
                        : null,
                ]);
            }

            $attachment->update([
                'keyword_extraction_status' => $suggestions === []
                    ? MaterialV2Attachment::KEYWORD_STATUS_EMPTY
                    : MaterialV2Attachment::KEYWORD_STATUS_READY,
                'keyword_extraction_error' => null,
                'keywords_extracted_at' => now(),
                'keyword_source_hash' => $sourceHash,
            ]);
        });
    }

    private function markKeywordExtractionSkipped(MaterialV2Attachment $attachment): void
    {
        if ($attachment->keyword_extraction_status === MaterialV2Attachment::KEYWORD_STATUS_FAILED) {
            return;
        }

        $attachment->update([
            'keyword_extraction_status' => MaterialV2Attachment::KEYWORD_STATUS_SKIPPED,
            'keyword_extraction_error' => $attachment->extraction_status === MaterialV2Attachment::STATUS_UNSUPPORTED
                ? 'Für dieses Dateiformat ist keine lokale Textextraktion verfügbar.'
                : $attachment->extraction_error,
            'keywords_extracted_at' => now(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function syncItemKeywords(MaterialV2Item $item): array
    {
        $item->unsetRelation('automaticTagSuggestions');
        $suggestions = $item->automaticTagSuggestions()
            ->get()
            ->sort(function (MaterialV2TagSuggestion $left, MaterialV2TagSuggestion $right): int {
                $scoreComparison = $right->final_score <=> $left->final_score;

                return $scoreComparison !== 0
                    ? $scoreComparison
                    : $left->rank <=> $right->rank;
            })
            ->unique('normalized_name')
            ->take((int) config('material-keywords.maximum_tags', 10));

        $keywords = $suggestions->pluck('tag_name')->values()->all();
        $item->generated_keywords = $keywords;
        $item->search_text = $this->rebuildSearchText($item);
        $item->save();

        return $keywords;
    }

    private function sourceHash(MaterialV2Item $item, MaterialV2Attachment $attachment): string
    {
        return hash('sha256', implode("\0", [
            (string) config('material-keywords.algorithm_version'),
            (string) $item->title,
            (string) $item->description,
            (string) $attachment->original_name,
            (string) $attachment->extracted_text,
        ]));
    }

    private function normalize(string $tagName): string
    {
        return Str::of($tagName)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')
            ->squish()
            ->toString();
    }
}
