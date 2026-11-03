<?php

namespace App\Support;

use PhpOffice\PhpPresentation\Reader\PowerPoint2007;

/**
 * Keep ZIP relationship paths relative when Windows dirname() returns a root marker.
 */
final class PowerPoint2007Reader extends PowerPoint2007
{
    protected function relsOf(string $part): string
    {
        return ltrim(parent::relsOf($part), '/\\');
    }

    protected function resolve(string $relsPart, string $target): string
    {
        return ltrim(parent::resolve($relsPart, $target), '/\\');
    }
}
