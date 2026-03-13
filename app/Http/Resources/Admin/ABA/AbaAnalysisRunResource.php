<?php

namespace App\Http\Resources\Admin\ABA;

use App\Models\AbaAnalysisRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbaAnalysisRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = is_array($this->summary) ? $this->summary : [];
        $analysisStats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];
        $recordCounts = is_array($summary['record_counts'] ?? null) ? $summary['record_counts'] : [];

        if ($recordCounts === []) {
            $recordCounts = [
                'detected_record_count' => (int) ($analysisStats['detected_record_count'] ?? 0),
                'normalized_record_count' => (int) ($analysisStats['normalized_record_count'] ?? 0),
                'validated_record_count' => (int) ($analysisStats['validated_record_count'] ?? 0),
                'persisted_record_count' => (int) ($analysisStats['persisted_record_count'] ?? $this->extracted_sections_count ?? 0),
            ];
        }

        return [
            'id' => $this->id,
            'aba_id' => $this->aba_id,
            'aba_attachment_id' => $this->aba_attachment_id,
            'created_by_user_id' => $this->created_by_user_id,
            'status' => $this->status,
            'status_label' => AbaAnalysisRun::statusLabel((string) $this->status),
            'status_message' => $this->status_message,
            'error_message' => $this->error_message,
            'source_original_name' => $this->source_original_name,
            'source_path' => $this->source_path,
            'source_mime_type' => $this->source_mime_type,
            'started_at' => $this->started_at?->toISOString(),
            'running_at' => $this->running_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'aborted_at' => $this->aborted_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'extracted_sections_count' => (int) ($this->extracted_sections_count ?? 0),
            'extracted_figures_count' => (int) ($this->extracted_figures_count ?? 0),
            'persisted_record_count' => (int) ($analysisStats['persisted_record_count'] ?? $this->extracted_sections_count ?? 0),
            'analysis_stats' => $analysisStats,
            'record_counts' => $recordCounts,
            'has_real_pagination' => (bool) ($analysisStats['has_real_pagination'] ?? false),
            'pagination_source' => (string) ($analysisStats['pagination_source'] ?? 'not_available'),
            'page_count_total' => (int) ($analysisStats['page_count_total'] ?? 0),
            'page_mapping_coverage' => (float) ($analysisStats['page_mapping_coverage'] ?? 0.0),
            'records_with_page_mapping_count' => (int) ($analysisStats['records_with_page_mapping_count'] ?? 0),
            'records_without_page_mapping_count' => (int) ($analysisStats['records_without_page_mapping_count'] ?? 0),
            'summary' => $summary,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
