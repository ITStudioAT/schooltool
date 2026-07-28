<?php

use App\Support\DiagnosticLogContextSanitizer;
use App\Support\SafeExternalUrl;
use Tests\TestCase;

uses(TestCase::class);

it('only exposes HTTP and HTTPS external URLs', function (?string $url, ?string $expected) {
    expect(SafeExternalUrl::sanitize($url))->toBe($expected);
})->with([
    'https' => ['https://example.com/material', 'https://example.com/material'],
    'http' => ['http://example.com/material', 'http://example.com/material'],
    'trimmed' => ['  https://example.com/material  ', 'https://example.com/material'],
    'javascript' => ['javascript:alert(1)', null],
    'data' => ['data:text/html,<script>alert(1)</script>', null],
    'vbscript' => ['vbscript:msgbox(1)', null],
    'relative' => ['/materials/1', null],
    'invalid' => ['not a URL', null],
    'empty' => ['', null],
    'null' => [null, null],
]);

it('removes document-derived text from ABA diagnostic contexts', function () {
    $context = [
        'selected_candidate' => 'docx_xml',
        'heading_count' => 4,
        'heading_samples' => [
            [
                'line' => 12,
                'type' => 'chapter',
                'title' => 'Confidential student document title',
            ],
        ],
        'toc_block_lines' => ['Confidential table of contents'],
        'mapping' => [
            ['section_key' => 'chapter-1', 'title' => 'Confidential chapter'],
        ],
        'metrics' => [
            'score' => 90,
            'reason' => 'best_score',
            'raw_preview' => 'Confidential preview',
        ],
    ];

    $sanitized = app(DiagnosticLogContextSanitizer::class)->sanitize($context);
    $encoded = json_encode($sanitized, JSON_THROW_ON_ERROR);

    expect(config('aba_analysis.debug_log_enabled'))->toBeFalse()
        ->and($sanitized)->toMatchArray([
            'selected_candidate' => 'docx_xml',
            'heading_count' => 4,
            'metrics' => [
                'score' => 90,
                'reason' => 'best_score',
            ],
        ])
        ->and($encoded)
        ->not->toContain('Confidential')
        ->not->toContain('heading_samples')
        ->not->toContain('toc_block_lines')
        ->not->toContain('mapping');
});
