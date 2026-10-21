<?php

namespace App\Services\MultipleChoice;

use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\User;
use App\Services\DropboxWorkImport;
use Illuminate\Support\Facades\Crypt;

class McQuestionSummary
{
    private const MAX_BYTES = 1024 * 1024;

    private const MULTIPLE_ANSWER_INSTRUCTION = 'Mehrfachantworten: Wähle alle richtigen Antworten.';

    public function __construct(
        private DropboxWorkImport $dropbox,
        private McCatalogTemplateSync $sync,
        private McQuestionValidator $validator,
    ) {}

    /** @return array<string, mixed> */
    public function preview(User $actor, McCatalog $catalog): array
    {
        $catalog->refresh();
        $context = $this->context($actor, $catalog);
        $summary = $this->render($catalog);
        $target = $this->target($context['source_folder'], $catalog);
        $existing = $this->existing($context['connection'], $target['path']);
        $action = $existing === null ? 'create' : ($existing['contents'] === $summary['content'] ? 'unchanged' : 'update');
        abort_unless(hash_equals($context['version'], $this->sync->version($catalog->fresh())), 409,
            'Der Katalog wurde während der Vorschau geändert. Bitte erneut prüfen.');
        $token = Crypt::encryptString(json_encode([
            'kind' => 'mc-question-summary', 'catalog' => $catalog->id, 'actor' => $actor->id,
            'version' => $context['version'], 'account_id' => $context['connection']->account_id,
            'source_file_id' => $context['source']['source_file_id'], 'source_path' => $context['source']['source_path'],
            'path' => $target['path'], 'revision' => $existing['metadata']['rev'] ?? null,
            'content_sha256' => hash('sha256', $summary['content']), 'expires' => now()->addMinutes(15)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return ['path' => $target['path'], 'action' => $action, 'question_count' => $summary['question_count'],
            'incomplete_count' => $summary['incomplete_count'], 'content' => $summary['content'],
            'rows' => $summary['rows'],
            'can_apply' => $action !== 'unchanged', 'token' => $action === 'unchanged' ? null : $token,
            'warning' => 'Die Question Summary wird als Markdown gespeichert. Eine PDF wird hier nicht automatisch erstellt.'];
    }

    /** @return array<string, mixed> */
    public function apply(User $actor, McCatalog $catalog, string $token): array
    {
        return $this->sync->serialize($catalog, function () use ($actor, $catalog, $token): array {
            try {
                $plan = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                abort(409, 'Die Vorschau ist ungültig. Bitte die Question Summary erneut prüfen.');
            }
            abort_unless(is_array($plan) && ($plan['kind'] ?? null) === 'mc-question-summary'
                && ($plan['catalog'] ?? null) === $catalog->id && ($plan['actor'] ?? null) === $actor->id
                && ($plan['expires'] ?? 0) >= now()->timestamp, 409,
                'Die Vorschau ist abgelaufen oder gehört nicht zu diesem Katalog. Bitte erneut prüfen.');
            $catalog->refresh();
            $context = $this->context($actor, $catalog);
            abort_unless(($plan['version'] ?? null) === $context['version']
                && ($plan['account_id'] ?? null) === $context['connection']->account_id
                && ($plan['source_file_id'] ?? null) === $context['source']['source_file_id']
                && ($plan['source_path'] ?? null) === $context['source']['source_path'], 409,
                'Katalog, Vorlage oder Dropbox-Konto wurden seit der Vorschau geändert. Bitte erneut prüfen.');
            $summary = $this->render($catalog);
            abort_unless(($plan['content_sha256'] ?? null) === hash('sha256', $summary['content']), 409,
                'Der Inhalt der Question Summary hat sich seit der Vorschau geändert. Bitte erneut prüfen.');
            $target = $this->target($context['source_folder'], $catalog);
            abort_unless(($plan['path'] ?? null) === $target['path'], 409,
                'Der Zielordner wurde seit der Vorschau geändert. Bitte erneut prüfen.');
            $existing = $this->existing($context['connection'], $target['path']);
            abort_unless(($plan['revision'] ?? null) === ($existing['metadata']['rev'] ?? null), 409,
                'Die Question Summary wurde inzwischen geändert. Bitte erneut prüfen.');
            $folder = $this->dropbox->ensureQuestionSummaryFolder($context['connection'], $context['source_folder']);
            abort_unless(($folder['path_display'] ?? $folder['path_lower'] ?? null) === $target['folder_path'], 409,
                'Der Zielordner wurde unerwartet geändert. Bitte erneut prüfen.');
            $sourceNow = $this->dropbox->readModuleApi($context['connection'], 'files/get_metadata',
                ['path' => $context['source']['source_file_id']])->json();
            abort_unless(($sourceNow['id'] ?? null) === $context['source']['source_file_id']
                && ($sourceNow['path_display'] ?? $sourceNow['path_lower'] ?? null) === $context['source']['source_path'],
                409, 'Die verbundene Fragenvorlage wurde während der Bestätigung verschoben.');
            $saved = $this->dropbox->writeQuestionSummary($context['connection'], $folder['id'],
                $target['filename'], $summary['content'], $plan['revision'] ?? null, $target['path']);

            return ['path' => $target['path'], 'revision' => $saved['rev'],
                'action' => ($plan['revision'] ?? null) === null ? 'create' : 'update',
                'question_count' => $summary['question_count'], 'incomplete_count' => $summary['incomplete_count'],
                'message' => 'Die Question Summary wurde in Dropbox gespeichert und zurückgelesen.'];
        });
    }

    /** @return array{connection: DropboxConnection, source: array<string, mixed>, source_folder: array<string, mixed>, version: string} */
    private function context(User $actor, McCatalog $catalog): array
    {
        abort_unless($catalog->exists && $catalog->owner_id === $actor->id && $catalog->school_id === $actor->school_id, 404);
        $version = $this->sync->version($catalog);
        $this->sync->assertEditable($catalog, $version);
        $binding = $this->sync->confirmedBinding($catalog);
        $sources = collect($catalog->module_sources ?? [])->keyBy('source_file_id')->values();
        abort_unless($sources->count() === 1, 409, 'Bitte zuerst genau eine Fragenvorlage fest mit diesem Katalog verbinden.');
        $source = $sources->first();
        abort_unless(($source['source_file_id'] ?? null) === ($binding['source']['source_file_id'] ?? null)
            && ($source['source_path'] ?? null) === ($binding['source']['source_path'] ?? null), 409,
            'Die gespeicherte Vorlagenverbindung ist nicht eindeutig. Bitte erneut prüfen.');
        $connection = $this->dropbox->connection($actor);
        abort_unless($connection->account_id === $binding['account_id'], 409,
            'Bitte die ursprüngliche Dropbox-Verbindung dieses Katalogs wiederherstellen.');
        abort_unless($this->dropbox->canWrite($connection), 422,
            'Für die Question Summary fehlt die Dropbox-Schreibfreigabe.');
        $file = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $source['source_file_id']])->json();
        abort_unless(($file['.tag'] ?? null) === 'file' && ($file['id'] ?? null) === $source['source_file_id']
            && ($file['path_display'] ?? $file['path_lower'] ?? null) === $source['source_path']
            && is_string($file['path_lower'] ?? null), 409,
            'Die verbundene Fragenvorlage ist in Dropbox nicht mehr am erwarteten Ort.');
        $sourceFolder = $this->dropbox->metadata($connection, dirname($file['path_lower']));
        abort_unless(in_array(mb_strtolower($sourceFolder['name']), ['multiple choice fragen', 'fragenkatalog'], true)
            && $sourceFolder['path_lower'] === dirname($file['path_lower']), 409,
            'Der Fragenordner passt nicht mehr zur bestätigten Vorlage.');

        return ['connection' => $connection, 'source' => $source, 'source_folder' => $sourceFolder,
            'version' => $version];
    }

    /** @return array{folder_path: string, path: string, filename: string} */
    private function target(array $sourceFolder, McCatalog $catalog): array
    {
        $folderPath = rtrim($sourceFolder['path_display'] ?? $sourceFolder['path_lower'], '/').'/Question Summary';
        $filename = 'Question Summary_Katalog-'.$catalog->id.'.md';

        return ['folder_path' => $folderPath, 'path' => $folderPath.'/'.$filename, 'filename' => $filename];
    }

    /** @return array{metadata: array<string, mixed>, contents: string}|null */
    private function existing(DropboxConnection $connection, string $path): ?array
    {
        $entry = $this->dropbox->findModuleEntry($connection, $path);
        if ($entry === null) {
            return null;
        }
        abort_unless(($entry['.tag'] ?? null) === 'file' && is_string($entry['rev'] ?? null)
            && is_int($entry['size'] ?? null) && $entry['size'] <= self::MAX_BYTES
            && ($entry['path_display'] ?? $entry['path_lower'] ?? null) === $path, 409,
            'Am Ziel liegt keine passende Question-Summary-Datei.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
        $after = $this->dropbox->findModuleEntry($connection, $path);
        abort_unless(strlen($contents) === $entry['size'] && ($after['rev'] ?? null) === $entry['rev'], 409,
            'Die Question Summary wurde während des Lesens geändert. Bitte erneut prüfen.');

        return ['metadata' => $entry, 'contents' => $contents];
    }

    /** @return array{content: string, question_count: int, incomplete_count: int, rows: array} */
    private function render(McCatalog $catalog): array
    {
        $questions = $catalog->questions ?? [];
        abort_unless(is_array($questions) && array_is_list($questions) && count($questions) <= 2000, 422,
            'Der Katalog enthält keine gültige Fragenliste.');
        $date = now()->setTimezone('Europe/Vienna')->format('d.m.Y');
        $title = trim((string) $catalog->title);
        $yamlTitle = json_encode('Question Summary: '.$title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $lines = ['---', 'title: '.$yamlTitle, 'update: '.$date, '---', '', '# Question Summary: '.$this->escape($title), '',
            'Aktualisiert: '.$date, '', 'Fragen: '.count($questions), ''];
        $errors = $this->validator->errors($questions);
        $incomplete = 0;
        $previewRows = [];
        foreach ($questions as $index => $question) {
            $question = is_array($question) ? $question : [];
            $number = $index + 1;
            $type = ($question['type'] ?? null) === 'multiple' ? 'Mehrfachauswahl' : 'Einfachauswahl';
            $lines[] = '## Frage '.$number.' · '.$type;
            $lines[] = '';
            $lines[] = $this->escape((string) ($question['prompt'] ?? ''), preserveInstruction: true) ?: 'Fragetext fehlt.';
            $lines[] = '';
            $rightIds = is_array($question['correct_option_ids'] ?? null) ? $question['correct_option_ids'] : [];
            $answers = [];
            $plainAnswers = [];
            $options = is_array($question['options'] ?? null) ? $question['options'] : [];
            foreach ($options as $option) {
                if (is_array($option) && in_array($option['id'] ?? null, $rightIds, true) && trim((string) ($option['text'] ?? '')) !== '') {
                    $plainAnswers[] = trim((string) $option['text']);
                    $answers[] = $this->escape($option['text']);
                }
            }
            $lines[] = count($answers) === 1 ? '**Richtige Antwort:**' : '**Richtige Antworten:**';
            foreach ($answers as $answer) {
                $lines[] = '- '.str_replace("\n", "\n  ", $answer);
            }
            if ($answers === []) {
                $lines[] = '- Keine gültige Lösung hinterlegt.';
            }
            $questionErrors = array_filter(array_keys($errors), fn (string $key): bool => $key === 'questions.'.$index
                || str_starts_with($key, 'questions.'.$index.'.'));
            if ($questionErrors !== []) {
                $lines[] = '';
                $lines[] = '**Unvollständig – bitte prüfen.**';
                $incomplete++;
            }
            $previewRows[] = ['number' => $number, 'type' => $type,
                'prompt' => $this->plainPrompt((string) ($question['prompt'] ?? '')), 'answers' => $plainAnswers,
                'active' => ($question['active'] ?? true) !== false, 'incomplete' => $questionErrors !== []];
            if (($question['active'] ?? true) === false) {
                $lines[] = '';
                $lines[] = '*Im Katalog inaktiv.*';
            }
            $lines[] = '';
        }
        if ($questions === []) {
            $lines[] = 'Der Katalog enthält noch keine Fragen.';
            $lines[] = '';
        }
        $content = implode("\n", $lines);
        abort_unless(strlen($content) <= self::MAX_BYTES, 422,
            'Die Question Summary ist größer als 1 MiB und kann so nicht sicher gespeichert werden.');

        return ['content' => $content, 'question_count' => count($questions), 'incomplete_count' => $incomplete,
            'rows' => $previewRows];
    }

    private function plainPrompt(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));

        return implode("\n", array_map(fn (string $line): string => trim($line) === '**'.self::MULTIPLE_ANSWER_INSTRUCTION.'**'
            ? self::MULTIPLE_ANSWER_INSTRUCTION : $line, explode("\n", $text)));
    }

    private function escape(string $text, bool $preserveInstruction = false): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = array_map(function (string $line) use ($preserveInstruction): string {
            if ($preserveInstruction && trim($line) === '**'.self::MULTIPLE_ANSWER_INSTRUCTION.'**') {
                return '**'.self::MULTIPLE_ANSWER_INSTRUCTION.'**';
            }
            $line = htmlspecialchars(trim($line), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            // Only escape punctuation that changes inline Markdown meaning. Ordinary
            // hyphens, periods and exclamation marks stay readable in the source.
            $line = preg_replace('/([\\`*_\[\]|~])/u', '\\\\$1', $line);

            return preg_replace('/^(#{1,6}(?=\s)|[+*-](?=\s)|\d{1,9}[.)](?=\s))/u', '\\\\$1', $line);
        }, explode("\n", $text));
        $result = array_shift($lines) ?? '';
        $previous = $result;
        foreach ($lines as $line) {
            $result .= ($previous === '' || $line === '' ? "\n" : "  \n").$line;
            $previous = $line;
        }

        return $result;
    }
}
