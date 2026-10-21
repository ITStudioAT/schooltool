<?php

namespace App\Services\MultipleChoice;

use Illuminate\Validation\ValidationException;

class McTemplateDocument
{
    private const HEADING = '/^(?:\s*#{1,6}\s+(?:Frage\s*)?\d+\s*[.:)\-]\s*.*|\s*\d+\s*[.)]\s+.*)$/um';

    public function __construct(private McImportService $imports) {}

    public function read(string $contents, string $format): array
    {
        $parsed = $this->imports->preview($contents, $format);
        if (! $parsed['valid']) {
            $this->fail('Die Vorlage enthält ungültige Fragen. Bitte zuerst die Datei korrigieren.');
        }
        $questions = $parsed['questions'];
        $layout = ['prefix' => '', 'suffix' => ''];
        if ($format === 'markdown') {
            preg_match_all(self::HEADING, $contents, $headings, PREG_OFFSET_CAPTURE);
            if (count($headings[0]) !== count($questions)) {
                $this->fail('Die Fragenabschnitte sind nicht eindeutig. Die Vorlage wird nicht überschrieben.');
            }
            $layout['prefix'] = substr($contents, 0, $headings[0][0][1]);
            foreach ($headings[0] as $index => [$heading, $offset]) {
                $end = $headings[0][$index + 1][1] ?? strlen($contents);
                $block = substr($contents, $offset, $end - $offset);
                if ($index !== count($questions) - 1 && preg_match('/^\s*(?:#{1,6}\s+|---\s*$)/m', substr($block, strlen($heading)))) {
                    $this->fail('Die Vorlage enthält Zwischenüberschriften innerhalb des Fragenbereichs. Diese Struktur muss vor dem Schreiben verlustfrei zugeordnet werden.');
                }
                if ($index === count($questions) - 1 && preg_match('/^\s*(?:#{1,6}\s+|---\s*$)/m', substr($block, strlen($heading)), $footer, PREG_OFFSET_CAPTURE)) {
                    $footerOffset = strlen($heading) + $footer[0][1];
                    $layout['suffix'] = substr($block, $footerOffset);
                    $block = substr($block, 0, $footerOffset);
                }
                if (preg_match_all('/<!-- schooltool-mc:([A-Za-z0-9+\/=]+) -->/', $block, $markers) > 1) {
                    $this->fail('Eine Frage enthält mehrere Synchronisationskennungen.');
                }
                if (isset($markers[1][0])) {
                    $marker = json_decode(base64_decode($markers[1][0], true) ?: '', true);
                    if (! is_array($marker) || ! is_string($marker['id'] ?? null) || strlen($marker['id']) > 100
                        || ! is_array($marker['option_ids'] ?? null) || count($marker['option_ids']) > 30
                        || count(array_unique($marker['option_ids'], SORT_REGULAR)) !== count($marker['option_ids'])
                        || count(array_filter($marker['option_ids'], fn ($id): bool => is_string($id) && $id !== '' && strlen($id) <= 100)) !== count($marker['option_ids'])) {
                        $this->fail('Die Synchronisationskennung einer Frage wurde beschädigt.');
                    }
                    $optionIds = count($marker['option_ids']) === count($questions[$index]['options']) ? $marker['option_ids']
                        : array_map(fn (int $index): string => 'source-option-'.($index + 1), array_keys($questions[$index]['options']));
                    $questions[$index] = $this->identify($questions[$index], $marker['id'], $optionIds);
                    $questions[$index]['_identified'] = true;
                }
            }
            foreach ($parsed['warnings'] as $warning) {
                if (! str_starts_with($warning, 'Importvorschau:')) {
                    $this->fail('Die Vorlage enthält zusätzliche Inhalte innerhalb der Fragen. Eine verlustfreie Synchronisierung ist nicht möglich: '.$warning);
                }
            }
        }

        return ['questions' => $questions, 'layout' => $layout, 'format' => $format, 'contents' => $contents];
    }

    public function write(array $document, array $questions): string
    {
        if ($document['format'] === 'json') {
            $original = json_decode($document['contents'], true, flags: JSON_THROW_ON_ERROR);
            $output = array_is_list($original) ? $questions : array_replace($original, ['questions' => $questions]);
            $contents = json_encode($output, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
        } else {
            $blocks = [];
            foreach ($questions as $index => $question) {
                $marker = base64_encode(json_encode(['id' => $question['id'], 'option_ids' => array_column($question['options'], 'id')], JSON_THROW_ON_ERROR));
                $block = '### '.($index + 1).'. '.$question['prompt']."\n<!-- schooltool-mc:{$marker} -->\n\n";
                foreach ($question['options'] as $option) {
                    $block .= '- ['.(in_array($option['id'], $question['correct_option_ids'], true) ? 'x' : ' ').'] '.str_replace("\n", "\n  ", $option['text'])."\n";
                }
                $blocks[] = rtrim($block);
            }
            $contents = $document['layout']['prefix'].implode("\n\n", $blocks)."\n\n".$document['layout']['suffix'];
            if (str_contains($document['contents'], "\r\n")) {
                $contents = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $contents));
            }
            if ($contents !== $document['contents']) {
                $contents = $this->updatedDate($contents);
            }
        }
        if (strlen($contents) > 1024 * 1024) {
            $this->fail('Die synchronisierte Vorlage wäre größer als 1 MiB.');
        }
        $roundtrip = $this->read($contents, $document['format']);
        if (array_map($this->semantic(...), $roundtrip['questions']) !== array_map($this->semantic(...), $questions)) {
            $this->fail('Die Vorlage lässt sich nicht verlustfrei schreiben. Es wurde nichts übertragen.');
        }

        return $contents;
    }

    public function semantic(?array $question): ?array
    {
        if ($question === null) {
            return null;
        }

        return ['prompt' => trim($question['prompt']), 'type' => $question['type'],
            'options' => array_map(fn (array $option): array => ['text' => trim($option['text']),
                'correct' => in_array($option['id'], $question['correct_option_ids'], true)], $question['options'])];
    }

    public function identify(array $question, string $id, array $optionIds): array
    {
        $correct = [];
        foreach ($question['options'] as $index => &$option) {
            if (in_array($option['id'], $question['correct_option_ids'], true)) {
                $correct[] = $optionIds[$index];
            }
            $option['id'] = $optionIds[$index];
        }
        unset($option);
        $question['id'] = $id;
        $question['correct_option_ids'] = $correct;

        return $question;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['template' => $message]);
    }

    private function updatedDate(string $contents): string
    {
        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $line = 'update: '.now('Europe/Vienna')->format('d.m.Y');
        if (! preg_match('/\A---\r?\n(.*?)\r?\n---(?:\r?\n|\z)/s', $contents, $frontmatter)) {
            return '---'.$newline.$line.$newline.'---'.$newline.$newline.$contents;
        }
        $metadata = $frontmatter[1];
        if (preg_match('/^update:[^\r\n]*$/m', $metadata)) {
            $metadata = preg_replace('/^update:[^\r\n]*$/m', $line, $metadata);
        } else {
            $metadata .= $newline.$line;
        }

        return '---'.$newline.$metadata.$newline.'---'.$newline.substr($contents, strlen($frontmatter[0]));
    }
}
