<?php

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

function rememberConsoleTempPath(string $path): string
{
    $current = is_array(test()->tempFiles ?? null) ? test()->tempFiles : [];
    $current[] = $path;
    test()->tempFiles = $current;

    return $path;
}

function createConsoleDocxFixture(string $contents = 'fake docx payload'): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'aba_cmd_docx_');
    if (! is_string($tmp) || $tmp === '') {
        throw new RuntimeException('Could not create temporary DOCX fixture.');
    }

    $docxPath = $tmp.'.docx';
    @rename($tmp, $docxPath);
    file_put_contents($docxPath, $contents);

    return rememberConsoleTempPath($docxPath);
}

function createConsoleFakePandocBinary(): string
{
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $tmp = tempnam(sys_get_temp_dir(), 'aba_cmd_pandoc_');
    if (! is_string($tmp) || $tmp === '') {
        throw new RuntimeException('Could not create temporary fake pandoc binary.');
    }

    $path = $isWindows ? $tmp.'.bat' : $tmp.'.sh';
    @rename($tmp, $path);

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

    file_put_contents($path, $script);
    if (! $isWindows) {
        @chmod($path, 0755);
    }

    return rememberConsoleTempPath($path);
}

it('runs aba:extract-docx-pandoc successfully with fake pandoc binary', function () {
    $docxPath = createConsoleDocxFixture();
    $fakePandoc = createConsoleFakePandocBinary();

    config()->set('aba_pandoc.enabled', true);
    config()->set('aba_pandoc.binary', $fakePandoc);
    config()->set('aba_pandoc.timeout_seconds', 10);

    $this->artisan('aba:extract-docx-pandoc', ['path' => $docxPath])
        ->expectsOutputToContain('Pandoc DOCX extraction succeeded.')
        ->expectsOutputToContain('Block count: 1')
        ->assertSuccessful();
});

it('can print normalized block statistics via --normalize', function () {
    $docxPath = createConsoleDocxFixture();
    $fakePandoc = createConsoleFakePandocBinary();

    config()->set('aba_pandoc.enabled', true);
    config()->set('aba_pandoc.binary', $fakePandoc);
    config()->set('aba_pandoc.timeout_seconds', 10);

    $this->artisan('aba:extract-docx-pandoc', [
        'path' => $docxPath,
        '--normalize' => true,
    ])
        ->expectsOutputToContain('Pandoc DOCX extraction succeeded.')
        ->expectsOutputToContain('Normalized block model:')
        ->expectsOutputToContain('Normalized block count:')
        ->assertSuccessful();
});

it('fails aba:extract-docx-pandoc for missing input file', function () {
    config()->set('aba_pandoc.enabled', true);
    config()->set('aba_pandoc.binary', 'pandoc');
    config()->set('aba_pandoc.timeout_seconds', 10);

    $this->artisan('aba:extract-docx-pandoc', ['path' => 'this/file/does/not/exist.docx'])
        ->expectsOutputToContain('Pandoc DOCX extraction failed')
        ->assertExitCode(1);
});
