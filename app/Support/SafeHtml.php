<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class SafeHtml
{
    private const ALLOWED_TAGS = [
        'a',
        'b',
        'blockquote',
        'br',
        'div',
        'em',
        'h1',
        'h2',
        'h3',
        'h4',
        'i',
        'li',
        'ol',
        'p',
        'span',
        'strong',
        'u',
        'ul',
    ];

    private const REMOVED_WITH_CONTENT = [
        'button',
        'embed',
        'form',
        'iframe',
        'input',
        'math',
        'object',
        'script',
        'style',
        'svg',
        'textarea',
    ];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        if (! class_exists(DOMDocument::class)) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="utf-8" ?><div data-safe-html-root="1">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );

            $root = $document->getElementsByTagName('div')->item(0);

            if (! $root instanceof DOMElement) {
                return '';
            }

            $this->sanitizeChildren($root);

            $sanitized = '';

            foreach ($root->childNodes as $child) {
                $sanitized .= $document->saveHTML($child);
            }

            return trim($sanitized);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($child = $parent->firstChild; $child !== null;) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);

                if (in_array($tagName, self::REMOVED_WITH_CONTENT, true)) {
                    $parent->removeChild($child);
                    $child = $next;

                    continue;
                }

                if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
                    $this->sanitizeChildren($child);

                    while ($child->firstChild !== null) {
                        $parent->insertBefore($child->firstChild, $child);
                    }

                    $parent->removeChild($child);
                    $child = $next;

                    continue;
                }

                $this->sanitizeAttributes($child, $tagName);
                $this->sanitizeChildren($child);
            }

            $child = $next;
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tagName): void
    {
        $attributeNames = [];

        foreach ($element->attributes as $attribute) {
            $attributeNames[] = $attribute->name;
        }

        foreach ($attributeNames as $attributeName) {
            if ($tagName !== 'a' || ! in_array(strtolower($attributeName), ['href', 'target'], true)) {
                $element->removeAttribute($attributeName);
            }
        }

        if ($tagName !== 'a') {
            return;
        }

        $href = trim($element->getAttribute('href'));

        if (! $this->isAllowedLink($href)) {
            $element->removeAttribute('href');
            $element->removeAttribute('target');

            return;
        }

        if ($element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        } else {
            $element->removeAttribute('target');
        }
    }

    private function isAllowedLink(string $href): bool
    {
        if ($href === '') {
            return false;
        }

        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
