<?php

use App\Support\SafeHtml;

it('removes executable markup while preserving supported formatting', function () {
    $html = '<p onclick="alert(1)"><strong>Safe</strong><script>alert(1)</script>'
        .'<img src=x onerror=alert(1)><a href="javascript:alert(1)" target="_blank">link</a></p>';

    $sanitized = app(SafeHtml::class)->sanitize($html);

    expect($sanitized)
        ->toContain('<p><strong>Safe</strong><a>link</a></p>')
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('<img');
});

it('keeps safe links and protects links opened in a new tab', function () {
    $sanitized = app(SafeHtml::class)->sanitize(
        '<a href="https://example.com/path" target="_blank" class="unsafe">Example</a>',
    );

    expect($sanitized)
        ->toContain('href="https://example.com/path"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->not->toContain('class=');
});
