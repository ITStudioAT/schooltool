<?php

namespace App\ABA\Services;

/**
 * Normalisiert abgerufene Quellinhalte für Hash-Vergleiche und Änderungserkennung.
 *
 * V1: Heuristischer Ansatz – transparent und nachvollziehbar.
 * HTML-Boilerplate (Scripte, Styles, Nav, Footer) wird entfernt,
 * verbleibender Text wird normalisiert.
 */
class NormalizeFetchedContent
{
    public function normalize(string $rawContent, string $strategy = 'html'): string
    {
        if ($strategy === 'html') {
            return $this->normalizeHtml($rawContent);
        }

        return $this->normalizeText($rawContent);
    }

    private function normalizeHtml(string $html): string
    {
        // Remove script/style blocks including their content
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/si', ' ', $html) ?? $html;
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/si', ' ', $text) ?? $text;

        // Remove common navigation/layout boilerplate blocks
        $text = preg_replace('/<(nav|header|footer|aside)\b[^>]*>.*?<\/(nav|header|footer|aside)>/si', ' ', $text) ?? $text;

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities (e.g. &amp; &uuml; &shy; etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->normalizeText($text);
    }

    private function normalizeText(string $text): string
    {
        // Collapse all whitespace (spaces, tabs, newlines) into single space
        $normalized = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($normalized);
    }
}
