<?php

it('uses dompdf as the default laravel pdf driver', function () {
    expect(config('laravel-pdf.driver'))->toBe('dompdf');
});
