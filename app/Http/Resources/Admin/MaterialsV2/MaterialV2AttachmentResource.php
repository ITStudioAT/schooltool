<?php

namespace App\Http\Resources\Admin\MaterialsV2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialV2AttachmentResource extends JsonResource
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
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'extraction_status' => $this->extraction_status,
            'extraction_error' => $this->extraction_error,
            'preview_url' => "/api/admin/materials-v2/attachments/{$this->id}/preview",
            'download_url' => "/api/admin/materials-v2/attachments/{$this->id}/download",
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
