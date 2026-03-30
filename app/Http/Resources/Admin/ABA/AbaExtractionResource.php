<?php

namespace App\Http\Resources\Admin\ABA;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbaExtractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = is_array($this->summary ?? null) ? $this->summary : [];

        return [
            'id' => (int) $this->id,
            'status' => $this->status,
            'status_message' => $this->status_message,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at,
            'running_at' => $this->running_at,
            'completed_at' => $this->completed_at,
            'failed_at' => $this->failed_at,
            'source_original_name' => $this->source_original_name,
            'source_mime_type' => $this->source_mime_type,
            'extracted_sections_count' => (int) ($this->extracted_sections_count ?? 0),
            'extracted_figures_count' => (int) ($this->extracted_figures_count ?? 0),
            'text_length' => $this->text_length,
            'rule_version' => $summary['rule_version'] ?? null,
            'rule_domain' => $summary['rule_domain'] ?? null,
            'scope' => is_array($summary['scope'] ?? null) ? $summary['scope'] : [],
            'document' => is_array($summary['document'] ?? null) ? $summary['document'] : [],
            'sections' => is_array($summary['sections'] ?? null) ? array_values($summary['sections']) : [],
            'found_required_section_keys' => is_array($summary['found_required_section_keys'] ?? null)
                ? array_values($summary['found_required_section_keys'])
                : [],
            'missing_required_section_keys' => is_array($summary['missing_required_section_keys'] ?? null)
                ? array_values($summary['missing_required_section_keys'])
                : [],
            'found_optional_section_keys' => is_array($summary['found_optional_section_keys'] ?? null)
                ? array_values($summary['found_optional_section_keys'])
                : [],
            'uncertain_matches' => is_array($summary['uncertain_matches'] ?? null)
                ? array_values($summary['uncertain_matches'])
                : [],
            'unmatched_blocks_count' => (int) ($summary['unmatched_blocks_count'] ?? 0),
            'warnings' => is_array($summary['warnings'] ?? null) ? array_values($summary['warnings']) : [],
            'errors' => is_array($summary['errors'] ?? null) ? array_values($summary['errors']) : [],
            'raw_structure' => is_array($summary['raw_structure'] ?? null) ? $summary['raw_structure'] : [],
        ];
    }
}
