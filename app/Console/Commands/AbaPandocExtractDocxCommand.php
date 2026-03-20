<?php

namespace App\Console\Commands;

use App\Services\AbaPandocAstNormalizerService;
use App\Services\AbaPandocDocxExtractionService;
use Illuminate\Console\Command;

class AbaPandocExtractDocxCommand extends Command
{
    protected $signature = 'aba:extract-docx-pandoc
                            {path : Relative or absolute path to the DOCX file}
                            {--normalize : Build ABA internal blocks from Pandoc JSON AST}
                            {--pretty : Print the full extraction payload as JSON}';

    protected $description = 'Runs the baseline Pandoc DOCX -> JSON AST extraction pipeline.';

    public function handle(
        AbaPandocDocxExtractionService $service,
        AbaPandocAstNormalizerService $normalizer,
    ): int {
        $pathArgument = trim((string) $this->argument('path'));
        $resolvedPath = $this->resolvePath($pathArgument);
        $result = $service->extractFromPath($resolvedPath);

        if (($result['ok'] ?? false) !== true) {
            $errorType = (string) ($result['error']['type'] ?? 'unknown_error');
            $errorMessage = (string) ($result['error']['message'] ?? 'Unbekannter Fehler');
            $this->error('Pandoc DOCX extraction failed: '.$errorType.' - '.$errorMessage);

            $details = $result['error']['details'] ?? [];
            if (is_array($details) && $details !== []) {
                $this->line(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            if ((bool) $this->option('pretty')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::FAILURE;
        }

        $this->info('Pandoc DOCX extraction succeeded.');
        $this->line('Binary: '.(string) ($result['runtime']['binary'] ?? 'n/a'));
        $this->line('Input: '.(string) ($result['input']['path'] ?? 'n/a'));
        $this->line('Block count: '.(string) ($result['metadata']['block_count'] ?? '0'));
        $this->line('Pandoc version: '.(string) ($result['metadata']['pandoc_version'] ?? 'unknown'));

        $normalized = null;
        if ((bool) $this->option('normalize')) {
            $normalized = $normalizer->normalizeAst(
                is_array($result['ast'] ?? null)
                    ? $result['ast']
                    : []
            );

            if (($normalized['ok'] ?? false) !== true) {
                $errorType = (string) ($normalized['error']['type'] ?? 'normalization_error');
                $errorMessage = (string) ($normalized['error']['message'] ?? 'Unbekannter Fehler');
                $this->warn('Normalization failed: '.$errorType.' - '.$errorMessage);
            } else {
                $this->line('Normalized block model: '.(string) ($normalized['format'] ?? 'n/a'));
                $this->line('Normalized block count: '.(string) ($normalized['metadata']['normalized_block_count'] ?? '0'));
                $this->line('Normalized headings: '.(string) ($normalized['metadata']['heading_count'] ?? '0'));
                $this->line('Normalized images: '.(string) ($normalized['metadata']['image_count'] ?? '0'));
            }
        }

        if ((bool) $this->option('pretty')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (is_array($normalized)) {
                $this->newLine();
                $this->line(json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        if (
            str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $path) === 1
        ) {
            return $path;
        }

        return base_path($path);
    }
}
