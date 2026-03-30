<?php

namespace App\Services;

class AbaDocxPageImageCounter
{
    /**
     * @return array{
     *   page_image_counts: array<int, int>,
     *   total_image_count: int,
     *   page_count_with_images: int,
     *   method: string
     * }
     */
    public function countByPage(string $absolutePath): array
    {
        if (! class_exists(\ZipArchive::class)) {
            return $this->emptyResult('zip_unavailable');
        }

        $zip = new \ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            return $this->emptyResult('zip_open_failed');
        }

        $xml = false;
        try {
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if (! is_string($xml) || trim($xml) === '') {
            return $this->emptyResult('document_xml_missing');
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return $this->emptyResult('document_xml_invalid');
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $blocks = $xpath->query('//w:body//*[self::w:tbl or (self::w:p and not(ancestor::w:tbl))]');
        if (! $blocks || $blocks->length === 0) {
            return $this->emptyResult('no_content_blocks');
        }

        $pageImageCounts = [];
        $currentPage = 1;
        $lastBreakWasExplicit = false;

        foreach ($blocks as $block) {
            $localName = mb_strtolower((string) ($block->localName ?? $block->nodeName));
            if ($localName === 'tbl') {
                $imageCount = $this->countImagesInNode($xpath, $block);
                if ($imageCount > 0) {
                    $pageImageCounts[$currentPage] = (int) ($pageImageCounts[$currentPage] ?? 0) + $imageCount;
                }

                continue;
            }

            if ($localName !== 'p') {
                continue;
            }

            $imageCount = $this->countImagesInNode($xpath, $block);
            $hasText = $this->paragraphHasText($xpath, $block);
            $hasContent = $hasText || $imageCount > 0;

            $pageBreakBeforeCount = (int) $xpath->evaluate(
                'count(w:pPr/w:pageBreakBefore[not(@w:val) or @w:val="true" or @w:val="1" or @w:val="on"])',
                $block
            );
            if ($pageBreakBeforeCount > 0) {
                $currentPage++;
                $lastBreakWasExplicit = true;
            }

            $mixedNodes = $xpath->query('.//w:br[@w:type="page"]|.//w:lastRenderedPageBreak|.//w:t|.//w:drawing|.//w:pict', $block);
            $firstNodeType = $mixedNodes && $mixedNodes->length > 0
                ? (string) ($mixedNodes->item(0)?->localName ?? 'none')
                : 'none';

            $hasExplicitBreak = (int) $xpath->evaluate('count(.//w:br[@w:type="page"])', $block) > 0;
            $hasRenderedBreak = (int) $xpath->evaluate('count(.//w:lastRenderedPageBreak)', $block) > 0;
            $explicitBreakBeforeContent = $hasExplicitBreak && $firstNodeType === 'br';
            $renderedBreakBeforeContent = $hasRenderedBreak && $firstNodeType === 'lastRenderedPageBreak';
            $anyBreakAfterContent = ($hasExplicitBreak || $hasRenderedBreak)
                && in_array($firstNodeType, ['t', 'drawing', 'pict'], true);

            if ($explicitBreakBeforeContent && $hasContent) {
                $currentPage++;
                $lastBreakWasExplicit = true;
            }

            if ($renderedBreakBeforeContent && $hasContent && ! $lastBreakWasExplicit) {
                $currentPage++;
                $lastBreakWasExplicit = false;
            }

            if ($imageCount > 0) {
                $pageImageCounts[$currentPage] = (int) ($pageImageCounts[$currentPage] ?? 0) + $imageCount;
            }

            if ($hasText) {
                $lastBreakWasExplicit = false;
            }

            if ($hasContent && $anyBreakAfterContent) {
                $currentPage++;
                $lastBreakWasExplicit = false;
            }

            if (! $hasContent && $hasExplicitBreak) {
                $currentPage++;
                $lastBreakWasExplicit = true;
            }
        }

        ksort($pageImageCounts);

        return [
            'page_image_counts' => $pageImageCounts,
            'total_image_count' => array_sum($pageImageCounts),
            'page_count_with_images' => count($pageImageCounts),
            'method' => 'docx_xml_body_scan',
        ];
    }

    private function paragraphHasText(\DOMXPath $xpath, \DOMNode $paragraph): bool
    {
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $textNode) {
            $text .= (string) $textNode->textContent;
        }

        return trim($text) !== '';
    }

    private function countImagesInNode(\DOMXPath $xpath, \DOMNode $node): int
    {
        return (int) $xpath->evaluate('count(.//w:drawing | .//w:pict)', $node);
    }

    /**
     * @return array{
     *   page_image_counts: array<int, int>,
     *   total_image_count: int,
     *   page_count_with_images: int,
     *   method: string
     * }
     */
    private function emptyResult(string $method): array
    {
        return [
            'page_image_counts' => [],
            'total_image_count' => 0,
            'page_count_with_images' => 0,
            'method' => $method,
        ];
    }
}
