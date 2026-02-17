<?php

namespace App\Http\Resources\Admin\Materials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'source_type' => $this->source_type,
            'source_url' => $this->source_url,
            'source_text' => $this->source_text,
            'subject' => $this->subject,
            'area' => $this->area,
            'unit' => $this->unit,
            'type' => $this->type,
            'status' => $this->status,
            'notes' => $this->notes,
            'keywords' => $this->keywords ?? [],
            'attachments' => MaterialCardAttachmentResource::collection($this->whenLoaded('attachments')),
            'attachments_count' => $this->when(
                $this->relationLoaded('attachments'),
                fn() => $this->attachments->count()
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
