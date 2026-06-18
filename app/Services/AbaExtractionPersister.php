<?php

namespace App\Services;

use App\Models\Aba;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AbaExtractionPersister
{
    /**
     * @param  array<string,mixed>  $summary
     */
    public function startRun(User $user, Aba $aba, ?AbaAttachment $attachment, array $summary = [], ?string $statusMessage = null): AbaAnalysisRun
    {
        return AbaAnalysisRun::query()->create([
            'aba_id' => $aba->id,
            'aba_attachment_id' => $attachment?->id,
            'created_by_user_id' => $user->id,
            'status' => AbaAnalysisRun::STATUS_STARTED,
            'source_original_name' => $attachment?->original_name,
            'source_path' => $attachment?->path,
            'source_mime_type' => $attachment?->mime_type,
            'status_message' => $statusMessage ?: 'Extraktion wurde gestartet.',
            'started_at' => now(),
            'summary' => $summary !== [] ? $summary : null,
        ]);
    }

    /**
     * @param  array<string,mixed>  $summary
     */
    public function markFailed(AbaAnalysisRun $run, string $message, array $summary = []): AbaAnalysisRun
    {
        $run->results()->delete();

        $run->forceFill([
            'status' => AbaAnalysisRun::STATUS_FAILED,
            'status_message' => $this->statusMessage($run, 'fehlgeschlagen.'),
            'error_message' => trim($message) !== '' ? $message : 'Unbekannter Fehler.',
            'failed_at' => now(),
            'running_at' => null,
            'completed_at' => null,
            'aborted_at' => null,
            'summary' => $summary === [] ? null : $summary,
            'extracted_sections_count' => 0,
            'extracted_figures_count' => 0,
            'text_length' => null,
            'text_length_without_spaces' => null,
        ])->save();

        return $run->fresh();
    }

    /**
     * @param  array<string,mixed>  $documentExtraction
     * @param  array<string,mixed>  $summary
     */
    public function markCompleted(AbaAnalysisRun $run, array $documentExtraction, array $summary): AbaAnalysisRun
    {
        $rawSections = is_array($documentExtraction['sections'] ?? null)
            ? array_values($documentExtraction['sections'])
            : [];
        $matchedRuleKeysBySection = is_array($summary['matched_rule_keys_by_section'] ?? null)
            ? $summary['matched_rule_keys_by_section']
            : [];

        DB::transaction(function () use ($run, $rawSections, $matchedRuleKeysBySection, $summary, $documentExtraction): void {
            $run->results()->delete();

            foreach ($rawSections as $sortOrder => $section) {
                $sectionKey = trim((string) ($section['section_key'] ?? ''));
                $metadata = is_array($section['metadata'] ?? null) ? $section['metadata'] : [];
                $metadata['matched_rule_keys'] = is_array($matchedRuleKeysBySection[$sectionKey] ?? null)
                    ? array_values($matchedRuleKeysBySection[$sectionKey])
                    : [];

                $run->results()->create([
                    'aba_id' => $run->aba_id,
                    'aba_attachment_id' => $run->aba_attachment_id,
                    'parent_result_id' => null,
                    'section_type' => trim((string) ($section['section_type'] ?? 'other_section')) ?: 'other_section',
                    'section_title' => $this->nullableSectionTitle($section['section_title'] ?? null),
                    'extracted_text' => $this->nullableString($section['extracted_text'] ?? null),
                    'sort_order' => $sortOrder,
                    'hierarchy_level' => is_numeric($section['hierarchy_level'] ?? null) ? (int) $section['hierarchy_level'] : null,
                    'start_line' => is_numeric($section['start_line'] ?? null) ? (int) $section['start_line'] : null,
                    'end_line' => is_numeric($section['end_line'] ?? null) ? (int) $section['end_line'] : null,
                    'start_page' => is_numeric($section['start_page'] ?? null) ? (int) $section['start_page'] : null,
                    'end_page' => is_numeric($section['end_page'] ?? null) ? (int) $section['end_page'] : null,
                    'anchor' => is_array($section['anchor'] ?? null) ? $section['anchor'] : null,
                    'metadata' => $metadata,
                ]);
            }

            $document = is_array($documentExtraction['document'] ?? null) ? $documentExtraction['document'] : [];

            $run->forceFill([
                'status' => AbaAnalysisRun::STATUS_COMPLETED,
                'status_message' => $this->statusMessage($run, 'abgeschlossen.'),
                'error_message' => null,
                'running_at' => null,
                'completed_at' => now(),
                'failed_at' => null,
                'aborted_at' => null,
                'summary' => $summary,
                'extracted_sections_count' => count($rawSections),
                'extracted_figures_count' => count(array_filter(
                    $rawSections,
                    fn (array $section): bool => (string) ($section['section_type'] ?? '') === 'figure'
                )),
                'text_length' => is_numeric($document['text_length'] ?? null) ? (int) $document['text_length'] : null,
                'text_length_without_spaces' => is_numeric($document['text_length_without_spaces'] ?? null) ? (int) $document['text_length_without_spaces'] : null,
            ])->save();
        });

        return $run->fresh('results');
    }

    public function markRunning(AbaAnalysisRun $run): AbaAnalysisRun
    {
        $run->forceFill([
            'status' => AbaAnalysisRun::STATUS_RUNNING,
            'status_message' => $this->statusMessage($run, 'läuft.'),
            'running_at' => $run->running_at ?? now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        return $run;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableSectionTitle(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);
        if ($normalized === null) {
            return null;
        }

        return mb_substr($normalized, 0, 255);
    }

    private function statusMessage(AbaAnalysisRun $run, string $suffix): string
    {
        $prefix = str_starts_with((string) $run->status_message, AbaAnalysisRun::PARSEL_EXTRACTION_STATUS_MESSAGE_PREFIX)
            ? AbaAnalysisRun::PARSEL_EXTRACTION_STATUS_MESSAGE_PREFIX
            : AbaAnalysisRun::EXTRACTION_STATUS_MESSAGE_PREFIX;

        return "{$prefix} {$suffix}";
    }
}
