<?php

namespace App\Services;

class AbaDocxPageMapper
{
    /**
     * Build a line-to-page mapping from a DOCX file.
     *
     * Iterates paragraphs exactly as AbaLocalDocumentTextExtractor::buildDocxXmlCandidate does:
     * - Text detection uses .//w:t elements only (not string(.) which catches textbox content)
     * - Skips empty paragraphs, increments lineNumber for non-empty ones
     * - Includes direct body paragraphs and TOC paragraphs nested in content controls (w:sdt)
     *
     * Page break sources handled:
     *   - <w:pageBreakBefore/> paragraph property → paragraph starts on a new page (pre-increment)
     *   - <w:br w:type="page"/> before text in a paragraph → paragraph starts on new page (pre-increment)
     *   - <w:br w:type="page"/> in an empty paragraph → next paragraph starts on new page (post-increment)
     *   - <w:lastRenderedPageBreak/> before text, not preceded by explicit break → pre-increment
     *     (sub-section headings where Word's rendered break is the only page break indicator)
     *   - <w:lastRenderedPageBreak/> before text, preceded by explicit break → skip
     *     (confirmation marker redundant with the preceding empty-paragraph explicit break)
     *   - <w:lastRenderedPageBreak/> after text → post-increment
     *     (page break within a long paragraph spanning multiple pages)
     *
     * @return array{
     *   line_to_page: array<int, int>,
     *   total_page_count: int,
     *   has_real_pagination: bool,
     *   page_mapping_method: string,
     *   page_break_count: int
     * }
     */
    public function buildPageMap(string $absolutePath): array
    {
        if (! class_exists(\ZipArchive::class)) {
            return $this->emptyMap('zip_unavailable');
        }

        $zip = new \ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            return $this->emptyMap('zip_open_failed');
        }

        $xml = false;
        try {
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if (! is_string($xml) || trim($xml) === '') {
            return $this->emptyMap('document_xml_missing');
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return $this->emptyMap('document_xml_invalid');
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $xpath->query('//w:body/w:p | //w:body/w:sdt//w:p');
        if (! $paragraphs || $paragraphs->length === 0) {
            return $this->emptyMap('no_paragraphs');
        }

        $lineToPage = [];
        $currentPage = 1;
        $lineNumber = 0;
        $pageBreakCount = 0;
        $hasRealPageBreaks = false;
        // Tracks whether the most recent page increment was from an explicit break
        // (used to skip redundant rendered-break confirmation markers on section headings)
        $lastBreakWasExplicit = false;

        foreach ($paragraphs as $paragraph) {
            // Paragraph property: pageBreakBefore means this paragraph opens a new page
            $pageBreakBeforeCount = (int) $xpath->evaluate(
                'count(w:pPr/w:pageBreakBefore[not(@w:val) or @w:val="true" or @w:val="1" or @w:val="on"])',
                $paragraph
            );
            if ($pageBreakBeforeCount > 0) {
                $currentPage++;
                $pageBreakCount++;
                $hasRealPageBreaks = true;
                $lastBreakWasExplicit = true;
            }

            // Determine the position of page break markers relative to text content.
            // Union query returns nodes in document order — first node reveals break/text ordering.
            $mixedNodes = $xpath->query('.//w:br[@w:type="page"]|.//w:lastRenderedPageBreak|.//w:t', $paragraph);
            $firstNodeType = $mixedNodes && $mixedNodes->length > 0 ? $mixedNodes->item(0)->localName : 'none';

            $hasExplicitBreak = (int) $xpath->evaluate('count(.//w:br[@w:type="page"])', $paragraph) > 0;
            $hasRenderedBreak = (int) $xpath->evaluate('count(.//w:lastRenderedPageBreak)', $paragraph) > 0;

            // "before text" = the first content node is a break, not a text element
            $explicitBreakBeforeText = $hasExplicitBreak && $firstNodeType === 'br';
            $renderedBreakBeforeText = $hasRenderedBreak && $firstNodeType === 'lastRenderedPageBreak';
            // "after text" = text comes first; any breaks in the paragraph are after some text
            $anyBreakAfterText = ($hasExplicitBreak || $hasRenderedBreak) && $firstNodeType === 't';

            // Text detection: use .//w:t only, matching AbaLocalDocumentTextExtractor behaviour.
            // string(.) also captures textbox/drawing content which the extractor skips.
            $textContent = '';
            foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $textNode) {
                $textContent .= $textNode->textContent;
            }
            $hasText = trim($textContent) !== '';

            // --- Pre-increment: explicit break appears before this paragraph's text ---
            if ($explicitBreakBeforeText && $hasText) {
                $currentPage++;
                $pageBreakCount++;
                $hasRealPageBreaks = true;
                $lastBreakWasExplicit = true;
            }

            // --- Pre-increment: rendered break before text ---
            // Skip when the page was just explicitly incremented (the rendered break is a
            // Word confirmation marker, not a new physical break). Count it when it is the
            // only page-break indicator for this paragraph (sub-section headings).
            if ($renderedBreakBeforeText && ! $lastBreakWasExplicit) {
                $currentPage++;
                $pageBreakCount++;
                $hasRealPageBreaks = true;
                $lastBreakWasExplicit = false;
            }

            // --- Assign line number ---
            if ($hasText) {
                $lineNumber++;
                $lineToPage[$lineNumber] = $currentPage;
                $lastBreakWasExplicit = false;
            }

            // --- Post-increment: breaks after text (page break within a long paragraph) ---
            if ($hasText && $anyBreakAfterText) {
                $currentPage++;
                $pageBreakCount++;
                $hasRealPageBreaks = true;
                $lastBreakWasExplicit = false;
            }

            // --- Post-increment: empty paragraph with an explicit break ---
            // The break separates the previous section from the next one.
            if (! $hasText && $hasExplicitBreak) {
                $currentPage++;
                $pageBreakCount++;
                $hasRealPageBreaks = true;
                $lastBreakWasExplicit = true;
            }
        }

        if ($lineToPage === []) {
            return $this->emptyMap('no_non_empty_paragraphs');
        }

        return [
            'line_to_page' => $lineToPage,
            'total_page_count' => $currentPage,
            'has_real_pagination' => $hasRealPageBreaks,
            'page_mapping_method' => $hasRealPageBreaks ? 'docx_xml_pagebreaks' : 'not_available',
            'page_break_count' => $pageBreakCount,
        ];
    }

    /**
     * Resolve the page number for a given line number.
     * Falls back to nearest-neighbour when the exact line is not in the map.
     *
     * @param  array<int, int>  $lineToPage
     */
    public function resolvePageForLine(int $line, array $lineToPage): ?int
    {
        if ($line <= 0 || $lineToPage === []) {
            return null;
        }

        if (isset($lineToPage[$line])) {
            return $lineToPage[$line];
        }

        $nearest = null;
        $minDistance = PHP_INT_MAX;
        foreach ($lineToPage as $mappedLine => $page) {
            $distance = abs($mappedLine - $line);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $page;
            }
        }

        return $nearest;
    }

    /**
     * @return array{
     *   line_to_page: array<int, int>,
     *   total_page_count: int,
     *   has_real_pagination: bool,
     *   page_mapping_method: string,
     *   page_break_count: int
     * }
     */
    private function emptyMap(string $reason): array
    {
        return [
            'line_to_page' => [],
            'total_page_count' => 0,
            'has_real_pagination' => false,
            'page_mapping_method' => $reason,
            'page_break_count' => 0,
        ];
    }
}
