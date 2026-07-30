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
            'cluster' => $this->whenLoaded(
                'cluster',
                fn (): ?array => $this->cluster === null
                    || (int) $this->cluster->user_id !== (int) $request->user()?->id
                    || (int) $this->cluster->school_id !== (int) $request->user()?->school_id
                    ? null
                    : [
                        'id' => (int) $this->cluster->id,
                        'name' => $this->cluster->name,
                    ],
            ),
            'description' => $this->description,
            'reminder_date' => $this->reminder_date?->format('Y-m-d'),
            'reminder_time' => $this->reminder_time
                ? mb_substr((string) $this->reminder_time, 0, 5)
                : null,
            'link_url' => $this->link_url,
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
