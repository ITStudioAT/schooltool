<?php

use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('keeps source files encoded as clean utf-8', function () {
    $process = new Process([PHP_BINARY, base_path('scripts/check-encoding.php')], base_path());
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getOutput().$process->getErrorOutput());
    }

    expect($process->isSuccessful())->toBeTrue();
});
