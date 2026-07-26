<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class MaterialV2DocumentTextExtractor
{
    private const MAX_EXTRACTED_CHARACTERS = 120000;

    /**
     * @var array<int, string>
     */
    private const SUPPORTED_EXTENSIONS = [
        'csv',
        'docx',
        'htm',
        'html',
        'json',
        'md',
        'odp',
        'ods',
        'odt',
        'pdf',
        'pptx',
        'rtf',
        'txt',
        'xls',
        'xlsx',
        'xml',
        'yaml',
        'yml',
    ];

    public function supports(MaterialV2Attachment $attachment): bool
    {
        return in_array($this->extension($attachment), self::SUPPORTED_EXTENSIONS, true)
            || Str::startsWith(Str::lower((string) $attachment->mime_type), 'text/');
    }

    public function extract(MaterialV2Attachment $attachment): string
    {
        if (! $this->supports($attachment)) {
            return '';
        }

        $localFile = $this->prepareLocalFile($attachment);

        try {
            $extension = $this->extension($attachment);
            $mimeType = Str::lower(trim((string) $attachment->mime_type));

            $text = match (true) {
                $extension === 'pdf' || $mimeType === 'application/pdf' => $this->extractPdf($localFile['path']),
                in_array($extension, ['docx', 'odt'], true) => $this->extractXmlArchive($localFile['path'], ['word/document.xml', 'content.xml']),
                in_array($extension, ['pptx', 'odp'], true) => $this->extractPresentation($localFile['path']),
                in_array($extension, ['xlsx', 'xls', 'ods'], true) => $this->extractSpreadsheet($localFile['path']),
                $extension === 'rtf' => $this->extractRtf($localFile['path']),
                in_array($extension, ['htm', 'html'], true) => strip_tags($this->readFile($localFile['path'])),
                default => $this->readFile($localFile['path']),
            };

            return Str::limit($this->normalize($text), self::MAX_EXTRACTED_CHARACTERS, '');
        } finally {
            if ($localFile['temporary']) {
                @unlink($localFile['path']);
            }
        }
    }

    /**
     * @return array{path: string, temporary: bool}
     */
    private function prepareLocalFile(MaterialV2Attachment $attachment): array
    {
        $diskName = trim((string) $attachment->disk);
        $path = trim((string) $attachment->path);

        if ($diskName === '' || $path === '') {
            throw new RuntimeException('Der Speicherort der Datei fehlt.');
        }

        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            throw new RuntimeException('Die gespeicherte Datei wurde nicht gefunden.');
        }

        if ($diskName === 'local') {
            return [
                'path' => $disk->path($path),
                'temporary' => false,
            ];
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'material_v2_');
        if (! is_string($temporaryPath) || $temporaryPath === '') {
            throw new RuntimeException('Die Datei konnte nicht zur Verarbeitung vorbereitet werden.');
        }

        $extension = $this->extension($attachment);
        if ($extension !== '') {
            $pathWithExtension = "{$temporaryPath}.{$extension}";
            if (@rename($temporaryPath, $pathWithExtension)) {
                $temporaryPath = $pathWithExtension;
            }
        }

        $stream = $disk->readStream($path);
        if ($stream === false) {
            @unlink($temporaryPath);

            throw new RuntimeException('Die gespeicherte Datei konnte nicht gelesen werden.');
        }

        $target = fopen($temporaryPath, 'wb');
        if ($target === false) {
            fclose($stream);
            @unlink($temporaryPath);

            throw new RuntimeException('Die Datei konnte nicht zur Verarbeitung vorbereitet werden.');
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($stream);
            fclose($target);
        }

        return [
            'path' => $temporaryPath,
            'temporary' => true,
        ];
    }

    private function extractPdf(string $path): string
    {
        return (new PdfParser)->parseFile($path)->getText();
    }

    /**
     * @param  array<int, string>  $preferredEntries
     */
    private function extractXmlArchive(string $path, array $preferredEntries): string
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('Das Dokumentarchiv konnte nicht geöffnet werden.');
        }

        try {
            foreach ($preferredEntries as $entry) {
                $xml = $archive->getFromName($entry);
                if (is_string($xml) && $xml !== '') {
                    return $this->xmlToText($xml);
                }
            }
        } finally {
            $archive->close();
        }

        return '';
    }

    private function extractPresentation(string $path): string
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('Die Präsentation konnte nicht geöffnet werden.');
        }

        try {
            $entries = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = (string) $archive->getNameIndex($index);
                if (preg_match('#^(ppt/slides/slide\d+\.xml|content\.xml)$#', $name) !== 1) {
                    continue;
                }

                $entries[] = $name;
            }

            natsort($entries);

            return collect($entries)
                ->map(function (string $entry) use ($archive): string {
                    $xml = $archive->getFromName($entry);

                    return is_string($xml) ? $this->xmlToText($xml) : '';
                })
                ->filter()
                ->implode("\n");
        } finally {
            $archive->close();
        }
    }

    private function extractSpreadsheet(string $path): string
    {
        $spreadsheet = SpreadsheetIOFactory::load($path);
        $lines = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheetIndex => $worksheet) {
            if ($sheetIndex >= 20) {
                break;
            }

            $lines[] = $worksheet->getTitle();
            foreach ($worksheet->toArray(null, true, true, false) as $rowIndex => $row) {
                if ($rowIndex >= 500) {
                    break;
                }

                $line = collect($row)
                    ->map(fn (mixed $value): string => trim((string) $value))
                    ->filter()
                    ->implode(' ');

                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        $spreadsheet->disconnectWorksheets();

        return implode("\n", $lines);
    }

    private function extractRtf(string $path): string
    {
        $content = $this->readFile($path);
        $content = preg_replace_callback(
            "/\\\\'([0-9a-fA-F]{2})/",
            fn (array $matches): string => chr((int) hexdec($matches[1])),
            $content,
        ) ?? $content;
        $content = preg_replace('/\\\\[a-z]+-?\d* ?/i', ' ', $content) ?? $content;

        return str_replace(['{', '}'], ' ', $content);
    }

    private function readFile(string $path): string
    {
        $content = file_get_contents($path);
        if (! is_string($content)) {
            throw new RuntimeException('Der Dateiinhalt konnte nicht gelesen werden.');
        }

        return $content;
    }

    private function xmlToText(string $xml): string
    {
        $xml = preg_replace('/<(w:tab|a:br|w:br)\b[^>]*\/?>/i', ' ', $xml) ?? $xml;
        $xml = preg_replace('/<\/(w:p|a:p|text:p|table:table-row)>/i', "\n", $xml) ?? $xml;

        return strip_tags($xml);
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^\P{C}\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]*\n[ \t]*/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function extension(MaterialV2Attachment $attachment): string
    {
        $extension = pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION);
        if ($extension === '') {
            $extension = pathinfo((string) $attachment->path, PATHINFO_EXTENSION);
        }

        return Str::lower($extension);
    }
}
