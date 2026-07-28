<?php

require_once dirname(__DIR__, 2).'/scripts/check-changed-code-coverage.php';

it('parses added PHP line ranges and ignores unrelated files', function () {
    $diff = <<<'DIFF'
diff --git a/app/Services/Example.php b/app/Services/Example.php
--- a/app/Services/Example.php
+++ b/app/Services/Example.php
@@ -8,0 +9,2 @@
+first
+second
@@ -20 +22 @@
-old
+new
diff --git a/resources/js/example.js b/resources/js/example.js
--- a/resources/js/example.js
+++ b/resources/js/example.js
@@ -1,0 +2 @@
+ignored
DIFF;

    expect(ChangedCodeCoverageGate::parseDiff($diff))->toBe([
        'app/Services/Example.php' => [
            9 => true,
            10 => true,
            22 => true,
        ],
    ]);
});

it('normalizes absolute Windows and Linux paths in Clover reports', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="C:\workspace\schooltool\app\Services\WindowsService.php">
      <line num="12" type="stmt" count="1"/>
      <line num="13" type="method" count="1"/>
    </file>
    <file name="/home/runner/work/schooltool/schooltool/app/Services/LinuxService.php">
      <line num="7" type="stmt" count="0"/>
    </file>
  </project>
</coverage>
XML;

    expect(ChangedCodeCoverageGate::parseCloverXml($xml, 'C:\workspace\schooltool'))->toBe([
        'app/Services/LinuxService.php' => [7 => 0],
        'app/Services/WindowsService.php' => [12 => 1],
    ]);
});

it('enforces the threshold over changed executable statements only', function () {
    $result = ChangedCodeCoverageGate::evaluate(
        [
            'app/Services/Example.php' => [
                10 => true,
                11 => true,
                12 => true,
                13 => true,
                14 => true,
                15 => true,
            ],
        ],
        [
            'app/Services/Example.php' => [
                10 => 1,
                11 => 1,
                12 => 1,
                13 => 1,
                14 => 0,
            ],
        ],
        80,
    );

    expect($result)
        ->toMatchArray([
            'covered' => 4,
            'executable' => 5,
            'passes' => true,
            'percentage' => 80.0,
        ])
        ->and($result['uncovered'])->toBe([
            'app/Services/Example.php' => [14],
        ]);
});

it('passes when a change contains no executable statements', function () {
    $result = ChangedCodeCoverageGate::evaluate(
        ['app/Services/Example.php' => [5 => true]],
        ['app/Services/Example.php' => [10 => 0]],
        100,
    );

    expect($result)
        ->toMatchArray([
            'covered' => 0,
            'executable' => 0,
            'passes' => true,
            'percentage' => 100.0,
        ])
        ->and($result['uncovered'])->toBeEmpty();
});

it('fails closed when a changed PHP file is missing from coverage', function () {
    expect(fn () => ChangedCodeCoverageGate::evaluate(
        ['app/Services/MissingService.php' => [10 => true]],
        [],
        80,
    ))->toThrow(
        RuntimeException::class,
        'Changed PHP file is missing from the Clover report: app/Services/MissingService.php',
    );
});

it('rejects unsafe references and invalid thresholds', function () {
    expect(fn () => ChangedCodeCoverageGate::parseOptions([
        '--base=origin/main;malicious',
        '--coverage=clover.xml',
    ]))->toThrow(InvalidArgumentException::class, 'unsafe Git reference')
        ->and(fn () => ChangedCodeCoverageGate::parseOptions([
            '--base=origin/main',
            '--coverage=clover.xml',
            '--min=101',
        ]))->toThrow(InvalidArgumentException::class, 'between 0 and 100');
});
