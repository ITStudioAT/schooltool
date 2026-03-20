<?php

use App\Services\AbaPandocDocxExtractionService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->tempFiles = [];
});

afterEach(function () {
    foreach ($this->tempFiles as $path) {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
});

function rememberTempPath(string $path): string
{
    $current = is_array(test()->tempFiles ?? null) ? test()->tempFiles : [];
    $current[] = $path;
    test()->tempFiles = $current;

    return $path;
}

function createDocxFixture(string $contents = 'fake docx payload'): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'aba_docx_');
    if (! is_string($tmp) || $tmp === '') {
        throw new RuntimeException('Could not create temporary DOCX fixture.');
    }

    $docxPath = $tmp.'.docx';
    @rename($tmp, $docxPath);
    file_put_contents($docxPath, $contents);

    return rememberTempPath($docxPath);
}

function createTextFixture(string $contents = 'plain text payload'): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'aba_txt_');
    if (! is_string($tmp) || $tmp === '') {
        throw new RuntimeException('Could not create temporary TXT fixture.');
    }

    $txtPath = $tmp.'.txt';
    @rename($tmp, $txtPath);
    file_put_contents($txtPath, $contents);

    return rememberTempPath($txtPath);
}

function createFakePandocBinary(bool $successful = true): string
{
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $tmp = tempnam(sys_get_temp_dir(), 'aba_pandoc_');
    if (! is_string($tmp) || $tmp === '') {
        throw new RuntimeException('Could not create temporary fake pandoc binary.');
    }

    $path = $isWindows ? $tmp.'.bat' : $tmp.'.sh';
    @rename($tmp, $path);

    if ($successful) {
        if ($isWindows) {
            $script = "@echo off\r\n"
                .'if "%1"=="--version" ('."\r\n"
                ."  echo pandoc 3.1.1\r\n"
                ."  exit /b 0\r\n"
                .")\r\n"
                .'echo {"pandoc-api-version":[1,23,0],"meta":{},"blocks":[{"t":"Para","c":[{"t":"Str","c":"Hallo"}]}]}'."\r\n"
                ."exit /b 0\r\n";
        } else {
            $script = "#!/usr/bin/env sh\n"
                ."if [ \"$1\" = \"--version\" ]; then\n"
                ."  echo \"pandoc 3.1.1\"\n"
                ."  exit 0\n"
                ."fi\n"
                ."echo '{\"pandoc-api-version\":[1,23,0],\"meta\":{},\"blocks\":[{\"t\":\"Para\",\"c\":[{\"t\":\"Str\",\"c\":\"Hallo\"}]}]}'\n"
                ."exit 0\n";
        }
    } else {
        if ($isWindows) {
            $script = "@echo off\r\n"
                ."echo Pandoc failed 1>&2\r\n"
                ."exit /b 2\r\n";
        } else {
            $script = "#!/usr/bin/env sh\n"
                ."echo 'Pandoc failed' 1>&2\n"
                ."exit 2\n";
        }
    }

    file_put_contents($path, $script);
    if (! $isWindows) {
        @chmod($path, 0755);
    }

    return rememberTempPath($path);
}

test('extracts pandoc json ast from docx successfully', function () {
    $docxPath = createDocxFixture();
    $fakePandoc = createFakePandocBinary(successful: true);

    config()->set('aba_pandoc.enabled', true);
    config()->set('aba_pandoc.binary', $fakePandoc);
    config()->set('aba_pandoc.timeout_seconds', 10);

    $result = app(AbaPandocDocxExtractionService::class)->extractFromPath($docxPath);

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($result['engine'] ?? null)->toBe('pandoc')
        ->and($result['format'] ?? null)->toBe('pandoc_json_ast')
        ->and($result['metadata']['block_count'] ?? null)->toBe(1)
        ->and($result['metadata']['pandoc_version'] ?? null)->toContain('pandoc')
        ->and(is_array($result['ast']['blocks'] ?? null))->toBeTrue()
        ->and($result['error'] ?? null)->toBeNull();
});

test('returns binary_not_found when configured pandoc binary is invalid', function () {
    $docxPath = createDocxFixture();

    config()->set('aba_pandoc.enabled', true);
    config()->set('aba_pandoc.binary', sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-pandoc-binary');
    config()->set('aba_pandoc.timeout_seconds', 10);

    $result = app(AbaPandocDocxExtractionService::class)->extractFromPath($docxPath);

    expect($result['ok'] ?? true)->toBeFalse()
        ->and($result['error']['type'] ?? null)->toBe('binary_not_found');
});

test('returns invalid_extension when file is not a docx', function () {
    $textPath = createTextFixture();
    $fakePandoc = createFakePandocBinary(successful: true);

    config()->set('aba_pandoc.enabled', true);
    config()->set('aba_pandoc.binary', $fakePandoc);
    config()->set('aba_pandoc.timeout_seconds', 10);

    $result = app(AbaPandocDocxExtractionService::class)->extractFromPath($textPath);

    expect($result['ok'] ?? true)->toBeFalse()
        ->and($result['error']['type'] ?? null)->toBe('invalid_extension');
});
