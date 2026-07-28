<?php

use App\Models\Register;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseWork;
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

it('sanitizes every rich-text model attribute on write and legacy reads', function () {
    $attributes = [
        [new Register, 'description_on_website'],
        [new SchoolTool, 'restaurant_user_information_intro_html'],
        [new SchoolTool, 'restaurant_sepa_payee'],
        [new SchoolTool, 'restaurant_sepa_mandate_text'],
        [new TeachingCourse, 'description'],
        [new TeachingCourseDate, 'content'],
        [new TeachingCourseStudent, 'comment'],
        [new TeachingCourseWork, 'description'],
    ];

    foreach ($attributes as [$model, $attribute]) {
        $model->setAttribute($attribute, '<p onclick="bad()">Safe<script>bad()</script></p>');

        expect($model->getAttribute($attribute))
            ->toBe('<p>Safe</p>');

        $model->setRawAttributes([$attribute => '<strong>Legacy</strong><img src=x onerror=bad()>']);

        expect($model->getAttribute($attribute))
            ->toBe('<strong>Legacy</strong>');
    }
});

it('preserves the formatting tags produced by the rich-text editor', function () {
    $sanitized = app(SafeHtml::class)->sanitize(
        '<h5>Heading</h5><pre><code>example</code></pre><s>old</s><sub>1</sub><sup>2</sup><hr>',
    );

    expect($sanitized)
        ->toContain('<h5>Heading</h5>')
        ->toContain('<pre><code>example</code></pre>')
        ->toContain('<s>old</s>')
        ->toContain('<sub>1</sub>')
        ->toContain('<sup>2</sup>')
        ->toContain('<hr>');
});
