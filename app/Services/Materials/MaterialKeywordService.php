<?php

namespace App\Services\Materials;

use App\Models\MaterialCard;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MaterialKeywordService
{
    private const MAX_KEYWORDS = 8;

    /**
     * Simple stop words (DE + EN) for rule-based token cleanup.
     *
     * @var array<int, string>
     */
    private array $stopwords = [
        'aber',
        'als',
        'am',
        'an',
        'auch',
        'auf',
        'aus',
        'bei',
        'bin',
        'bis',
        'bzw',
        'das',
        'dass',
        'dem',
        'den',
        'der',
        'des',
        'die',
        'dies',
        'diese',
        'dieser',
        'doch',
        'dort',
        'ein',
        'eine',
        'einem',
        'einen',
        'einer',
        'eines',
        'er',
        'es',
        'etwa',
        'für',
        'hat',
        'hier',
        'ich',
        'ihr',
        'ihre',
        'im',
        'in',
        'ist',
        'kein',
        'keine',
        'man',
        'mit',
        'nach',
        'nicht',
        'noch',
        'oder',
        'sehr',
        'sein',
        'sich',
        'sie',
        'sind',
        'so',
        'und',
        'uns',
        'unter',
        'vom',
        'von',
        'vor',
        'war',
        'was',
        'wenn',
        'wer',
        'wie',
        'wir',
        'wird',
        'you',
        'your',
        'the',
        'and',
        'for',
        'from',
        'with',
        'this',
        'that',
        'into',
        'are',
        'is',
        'was',
        'were',
        'www',
        'http',
        'https',
        'com',
        'org',
        'net',
        'html',
        'php',
        'pdf',
        'doc',
        'docx',
        'ppt',
        'pptx',
        'xls',
        'xlsx',
    ];

    /**
     * Build top keywords from card text + attachment metadata.
     *
     * @return array<int, string>
     */
    public function build(MaterialCard $card): array
    {
        if (Schema::hasTable('material_card_classifications')) {
            $card->loadMissing('attachments', 'classifications.subject', 'classifications.topic', 'classifications.unit');
        } else {
            $card->loadMissing('attachments');
        }

        $parts = [
            (string) $card->title,
            (string) ($card->notes ?? ''),
            (string) ($card->source_text ?? ''),
            $this->normalizeUrlToText($card->source_url),
        ];

        foreach ($card->attachments as $attachment) {
            $parts[] = (string) ($attachment->name ?? '');
            $parts[] = $this->normalizeUrlToText($attachment->url);
        }

        if (Schema::hasTable('material_card_classifications')) {
            foreach ($card->classifications as $classification) {
                $parts[] = (string) ($classification->subject?->name ?? '');
                $parts[] = (string) ($classification->topic?->name ?? '');
                $parts[] = (string) ($classification->unit?->name ?? '');
            }
        }

        $tokens = $this->tokenize(implode(' ', $parts));
        $tokenCounts = [];

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 3) {
                continue;
            }
            if (in_array($token, $this->stopwords, true)) {
                continue;
            }

            $tokenCounts[$token] = ($tokenCounts[$token] ?? 0) + 1;
        }

        if ($tokenCounts === []) {
            return [];
        }

        uksort($tokenCounts, function (string $a, string $b) use ($tokenCounts) {
            $aCount = $tokenCounts[$a];
            $bCount = $tokenCounts[$b];

            if ($aCount === $bCount) {
                return strcmp($a, $b);
            }

            return $bCount <=> $aCount;
        });

        return array_slice(array_keys($tokenCounts), 0, self::MAX_KEYWORDS);
    }

    /**
     * Rebuild and persist keywords on card.
     *
     * @return array<int, string>
     */
    public function rebuild(MaterialCard $card): array
    {
        $keywords = $this->build($card);
        $card->keywords = $keywords;
        $card->save();

        return $keywords;
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $normalized = Str::lower($text);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];

        return array_values(array_filter($tokens, fn ($token) => $token !== null && $token !== ''));
    }

    private function normalizeUrlToText(?string $url): string
    {
        if (! $url) {
            return '';
        }

        $text = Str::lower($url);
        $text = str_replace(['://', '/', '?', '&', '=', '-', '_', '.', ':', '#'], ' ', $text);

        return trim($text);
    }
}
