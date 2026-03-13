<?php

namespace App\Http\Resources\Admin\ABA;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbaAnalysisResultResource extends JsonResource
{
    private function resolvePageLabel(): ?string
    {
        $startPage = $this->start_page !== null && (int) $this->start_page > 0 ? (int) $this->start_page : null;
        $endPage = $this->end_page !== null && (int) $this->end_page > 0 ? (int) $this->end_page : null;

        if ($startPage === null) {
            return null;
        }

        if ($endPage !== null && $endPage !== $startPage) {
            return "Seiten {$startPage}–{$endPage}";
        }

        return "Seite {$startPage}";
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aba_id' => $this->aba_id,
            'aba_analysis_run_id' => $this->aba_analysis_run_id,
            'aba_attachment_id' => $this->aba_attachment_id,
            'parent_result_id' => $this->parent_result_id,
            'section_type' => $this->section_type,
            'section_title' => $this->section_title,
            'extracted_text' => $this->extracted_text,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'hierarchy_level' => $this->hierarchy_level !== null ? (int) $this->hierarchy_level : null,
            'start_line' => $this->start_line !== null ? (int) $this->start_line : null,
            'end_line' => $this->end_line !== null ? (int) $this->end_line : null,
            'start_page' => $this->start_page !== null ? (int) $this->start_page : null,
            'end_page' => $this->end_page !== null ? (int) $this->end_page : null,
            'page_label' => $this->resolvePageLabel(),
            'anchor' => is_array($this->anchor) ? $this->anchor : [],
            'metadata' => is_array($this->metadata) ? $this->metadata : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
