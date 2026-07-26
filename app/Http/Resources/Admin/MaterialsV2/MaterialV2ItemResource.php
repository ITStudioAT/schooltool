<?php

namespace App\Http\Resources\Admin\MaterialsV2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialV2ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'user_keywords' => $this->user_keywords ?? [],
            'generated_keywords' => $this->generated_keywords ?? [],
            'automatic_tag_suggestions' => $this->when(
                $this->relationLoaded('automaticTagSuggestions'),
                fn (): array => $this->automaticTagSuggestions
                    ->sortByDesc('final_score')
                    ->unique('normalized_name')
                    ->values()
                    ->map(fn ($suggestion, int $index): array => [
                        'name' => $suggestion->tag_name,
                        'score' => round((float) $suggestion->final_score, 2),
                        'rank' => $index + 1,
                        'language' => $suggestion->language,
                        'attachment_id' => (int) $suggestion->material_v2_attachment_id,
                    ])
                    ->all(),
            ),
            'processing_status' => $this->processing_status,
            'processing_error' => $this->processing_error,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'search_score' => $this->when(
                $this->getAttribute('search_score') !== null,
                fn (): float => (float) $this->getAttribute('search_score'),
            ),
            'matched_terms' => $this->when(
                $this->getAttribute('matched_terms') !== null,
                fn (): array => (array) $this->getAttribute('matched_terms'),
            ),
            'attachments' => MaterialV2AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
