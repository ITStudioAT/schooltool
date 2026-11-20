<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\MultipleChoice\McModuleTemplateSource;
use Smalot\PdfParser\Parser;
use Throwable;

/** Finds candidate documents for a teacher to review, without writing to Dropbox. */
class TeachingAssessmentTemplate
{
    public function __construct(
        private DropboxWorkImport $dropbox,
        private DropboxModuleLinks $modules,
        private McModuleTemplateSource $points,
    ) {}

    /** @return array<string, mixed> */
    public function inspect(User $user, TeachingWorkModuleLink $link, ?\Closure $progress = null): array
    {
        $connection = $this->dropbox->connection($user);
        abort_unless($connection->id === $link->dropbox_connection_id && $connection->account_id === $link->account_id, 409,
            'Die Modulverbindung gehört nicht zum aktuellen Dropbox-Konto.');
        $module = $this->modules->inspect($connection, $link->handshake_file_id);
        abort_unless($module['folder_id'] === $link->folder_id && strcasecmp($module['module_id'], $link->module_id) === 0
            && strcasecmp($module['folder_path'], $link->folder_path) === 0 && $module['handshake_revision'] === $link->handshake_revision,
            409, 'Die Modulverbindung wurde geändert. Bitte erneut prüfen.');
        $folders = array_values(array_filter($this->children($connection, $module['folder_id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && in_array(mb_strtolower($entry['name']), ['prüfungsvorlage', 'leistungsfeststellung', 'assessment template', 'assessment_template'], true)));
        abort_unless(count($folders) === 1, 422, 'Die Prüfungsvorlage fehlt oder ist nicht eindeutig.');
        $folder = $this->dropbox->metadata($connection, $folders[0]['id']);
        abort_unless(($folder['id'] ?? null) === $folders[0]['id'] && is_string($folder['path_lower'] ?? null)
            && dirname($folder['path_lower']) === mb_strtolower($module['folder_path']), 409, 'Der Vorlagenordner wurde verschoben.');
        $entries = $this->children($connection, $folder['id']);
        abort_if(mb_strtolower($folder['name']) !== 'leistungsfeststellung'
            && collect($entries)->contains(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'), 422,
            'Der Vorlagenordner enthält Unterordner; die benötigten Dokumente sind nicht eindeutig.');
        $taskFiles = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && preg_match('/\A(?:LF|Leistungsfeststellung)_.+\.pdf\z/iu', $entry['name']) === 1));
        abort_unless(count($taskFiles) === 1, 422, 'Das PDF-Aufgabenblatt fehlt oder ist nicht eindeutig.');
        $suffix = preg_replace('/\A(?:LF|Leistungsfeststellung)_/iu', '', pathinfo($taskFiles[0]['name'], PATHINFO_FILENAME));
        $find = fn (string $name): array => array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && mb_strtolower($entry['name']) === mb_strtolower($name)));
        $taskMarkdown = $find(pathinfo($taskFiles[0]['name'], PATHINFO_FILENAME).'.md');
        $criteriaMarkdown = $find('Beurteilungskriterien_'.$suffix.'.md');
        $criteriaPdf = $find('Beurteilungskriterien_'.$suffix.'.pdf');
        $allCriteria = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && preg_match('/\ABeurteilungskriterien_.+\.(?:md|pdf)\z/iu', $entry['name']) === 1));
        abort_unless(count($taskMarkdown) === 1 && count($criteriaMarkdown) === 1 && count($criteriaPdf) === 1 && count($allCriteria) === 2, 422,
            'Aufgabenblatt und Beurteilungskriterien benötigen jeweils eine eindeutig zugeordnete Markdown- und PDF-Fassung.');
        $selected = ['assignment' => $taskFiles[0], 'assignment_markdown' => $taskMarkdown[0],
            'criteria' => $criteriaPdf[0], 'criteria_markdown' => $criteriaMarkdown[0]];
        $documents = [];
        foreach ($selected as $key => $entry) {
            $documents[$key] = $this->document($connection, $entry, $folder['path_lower'], str_ends_with($key, 'markdown'));
            if ($key === 'assignment_markdown') {
                $progress?->__invoke('completed', 'assessment_assignment');
                $progress?->__invoke('start', 'assessment_criteria');
            }
        }
        $total = $this->points->assessmentPoints($user, $link);
        abort_unless($total !== null && is_finite($total) && $total >= 0, 422, 'Die Punkte der Prüfungsvorlage sind nicht eindeutig bestätigt.');
        $identity = fn (array $items): array => collect($items)->map(fn (array $item): array => [
            'id' => $item['id'], 'name' => $item['name'], 'rev' => $item['rev'] ?? null, 'path_lower' => $item['path_lower'] ?? null,
        ])->sortBy('id')->values()->all();
        abort_unless($identity($entries) === $identity($this->children($connection, $folder['id'])),
            409, 'Der Vorlagenbestand wurde während der Prüfung geändert. Bitte erneut prüfen.');
        foreach ($documents as $document) {
            $current = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $document['id']])->json();
            abort_unless(($current['rev'] ?? null) === $document['rev'] && ($current['path_lower'] ?? null) === $document['path_lower'],
                409, 'Eine Vorlage wurde während der Prüfung geändert. Bitte erneut prüfen.');
        }

        $progress?->__invoke('completed', 'assessment_criteria');

        return ['folder' => $folder, 'points' => $total, 'documents' => $documents];
    }

    /** @return list<array<string, mixed>> */
    private function children(DropboxConnection $connection, string $id): array
    {
        $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder', ['path' => $id, 'recursive' => false, 'limit' => 200])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            abort_unless(is_array($listing['entries'] ?? null) && is_bool($listing['has_more'] ?? null), 422,
                'Der Vorlagenbestand konnte nicht vollständig gelesen werden.');
            foreach ($listing['entries'] as $entry) {
                abort_unless(is_array($entry) && is_string($entry['id'] ?? null) && is_string($entry['name'] ?? null)
                    && in_array($entry['.tag'] ?? null, ['file', 'folder'], true), 422, 'Der Vorlagenbestand ist ungültig.');
                abort_if(isset($entries[$entry['id']]), 409, 'Der Vorlagenbestand enthält eine doppelte Kennung.');
                $entries[$entry['id']] = $entry;
            }
            if (! $listing['has_more']) {
                return array_values($entries);
            }
            abort_if($page >= 2 || ! is_string($listing['cursor'] ?? null), 422, 'Der Vorlagenbestand konnte nicht vollständig gelesen werden.');
            $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder/continue', ['cursor' => $listing['cursor']])->json();
        }
    }

    /** @param array<string, mixed> $entry @return array<string, mixed> */
    private function document(DropboxConnection $connection, array $entry, string $folderPath, bool $markdown): array
    {
        $current = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $entry['id']])->json();
        abort_unless(($current['.tag'] ?? null) === 'file' && ($current['id'] ?? null) === $entry['id']
            && ($current['name'] ?? null) === $entry['name'] && is_string($current['rev'] ?? null)
            && ($current['rev'] ?? null) === ($entry['rev'] ?? null) && ($current['path_lower'] ?? null) === ($entry['path_lower'] ?? null)
            && is_string($current['path_lower'] ?? null) && dirname($current['path_lower']) === $folderPath
            && is_int($current['size'] ?? null) && $current['size'] > 0 && $current['size'] <= 6 * 1024 * 1024,
            422, 'Ein Vorlagendokument ist ungültig, verschoben oder zu groß.');
        $bytes = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$current['rev']])->body();
        abort_unless(strlen($bytes) === $current['size'], 409, 'Ein Vorlagendokument wurde nicht vollständig gelesen.');
        if ($markdown) {
            abort_unless(mb_check_encoding($bytes, 'UTF-8') && trim($bytes) !== '', 422, 'Eine Markdown-Vorlage ist nicht lesbar.');
        } else {
            try {
                $text = str_starts_with($bytes, '%PDF-') ? (new Parser)->parseContent($bytes)->getText() : '';
            } catch (Throwable) {
                $text = '';
            }
            abort_unless(trim($text) !== '', 422, 'Ein Vorlagen-PDF ist nicht lesbar.');
        }

        return ['id' => $current['id'], 'name' => $current['name'], 'rev' => $current['rev'],
            'path_lower' => $current['path_lower'], 'sha256' => hash('sha256', $bytes), 'bytes' => $bytes];
    }
}
