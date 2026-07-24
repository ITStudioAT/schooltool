<?php

namespace App\Services\MaterialsV2;

use App\Ai\Agents\MaterialV2KeywordAgent;
use App\Models\MaterialV2Item;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MaterialV2KeywordService
{
    private const MAX_KEYWORDS = 16;

    /**
     * @var array<int, string>
     */
    private array $stopwords = [
        'aber', 'alle', 'also', 'auch', 'auf', 'aus', 'bei', 'bis', 'das', 'dass', 'dem', 'den', 'der', 'des', 'die',
        'dies', 'diese', 'doch', 'durch', 'ein', 'eine', 'einem', 'einen', 'einer', 'eines', 'für', 'hat', 'hier', 'ihre',
        'ihren', 'ihres', 'ist', 'kann', 'kein', 'keine', 'mit', 'nach', 'nicht', 'noch', 'oder', 'sein', 'sich', 'sind',
        'über', 'und', 'unter', 'vom', 'von', 'vor', 'war', 'was', 'wenn', 'werden', 'wie', 'wird', 'wir', 'the', 'and',
        'for', 'from', 'into', 'that', 'this', 'with', 'your', 'http', 'https', 'www', 'com', 'org', 'pdf', 'doc', 'docx',
        'ppt', 'pptx', 'xls', 'xlsx', 'seite', 'seiten', 'kapitel', 'dokument',
    ];

    /**
     * @return array<int, string>
     */
    public function generate(MaterialV2Item $item): array
    {
        $item->loadMissing('attachments');

        $sourceText = collect([
            $item->title,
            $item->description,
            ...$item->attachments->pluck('original_name')->all(),
            ...$item->attachments->pluck('extracted_text')->all(),
        ])->filter()->implode("\n");

        $localKeywords = $this->localKeywords($sourceText);
        $aiKeywords = $this->aiKeywords($item, $sourceText);

        return $this->sanitizeKeywords([...$aiKeywords, ...$localKeywords]);
    }

    public function rebuildSearchText(MaterialV2Item $item): string
    {
        $item->loadMissing('attachments');

        return Str::limit(
            collect([
                $item->title,
                $item->description,
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
     * @return array<int, string>
     */
    private function localKeywords(string $sourceText): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($sourceText)) ?: [];
        $frequencies = [];

        foreach ($tokens as $token) {
            $normalized = trim($token);
            if (Str::length($normalized) < 4 || in_array($normalized, $this->stopwords, true)) {
                continue;
            }

            $frequencies[$normalized] = ($frequencies[$normalized] ?? 0) + 1;
        }

        uksort($frequencies, function (string $left, string $right) use ($frequencies): int {
            $frequencyComparison = $frequencies[$right] <=> $frequencies[$left];

            return $frequencyComparison !== 0 ? $frequencyComparison : strcmp($left, $right);
        });

        return array_slice(array_keys($frequencies), 0, self::MAX_KEYWORDS);
    }

    /**
     * @return array<int, string>
     */
    private function aiKeywords(MaterialV2Item $item, string $sourceText): array
    {
        if (
            ! config('ai.materials_v2_keyword_enrichment', false)
            || trim((string) config('ai.providers.openai.key')) === ''
        ) {
            return [];
        }

        try {
            $response = (new MaterialV2KeywordAgent)->prompt(
                collect([
                    "Titel: {$item->title}",
                    'Beschreibung: '.trim((string) $item->description),
                    'Dokumentinhalt:',
                    Str::limit($sourceText, 32000, ''),
                ])->implode("\n\n")
            );

            $payload = method_exists($response, 'toArray') ? $response->toArray() : (array) $response;

            return $this->sanitizeKeywords(is_array($payload['keywords'] ?? null) ? $payload['keywords'] : []);
        } catch (Throwable $exception) {
            Log::warning('materials_v2.keyword_generation_failed', [
                'material_v2_item_id' => $item->id,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<int, mixed>  $keywords
     * @return array<int, string>
     */
    private function sanitizeKeywords(array $keywords): array
    {
        return collect($keywords)
            ->map(fn (mixed $keyword): string => Str::lower(Str::squish((string) $keyword)))
            ->filter(fn (string $keyword): bool => Str::length($keyword) >= 3 && Str::length($keyword) <= 80)
            ->unique()
            ->take(self::MAX_KEYWORDS)
            ->values()
            ->all();
    }
}
