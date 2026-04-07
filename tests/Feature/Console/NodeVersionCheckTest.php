<?php

use Symfony\Component\Process\Process;

dataset('node_version_checks', [
    '20.19.0 passes' => ['20.19.0', 0],
    '20.18.x fails' => ['20.18.9', 1],
    '22.12.0 passes' => ['22.12.0', 0],
    '22.18.0 passes' => ['22.18.0', 0],
    '22.11.x fails' => ['22.11.9', 1],
    '23.x passes' => ['23.0.0', 0],
]);

it('validates the shared node version check consistently', function (string $version, int $expectedExitCode): void {
    $process = new Process(['node', base_path('scripts/check_node_version.cjs'), $version]);
    $process->run();

    expect($process->getExitCode())->toBe($expectedExitCode);

    if ($expectedExitCode === 0) {
        expect($process->isSuccessful())->toBeTrue();
        expect($process->getOutput())->toContain("Node {$version} OK");
    } else {
        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput())->toContain("Node {$version} is too old");
    }
})->with('node_version_checks');
