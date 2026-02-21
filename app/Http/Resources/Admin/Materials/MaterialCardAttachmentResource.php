<?php

namespace App\Http\Resources\Admin\Materials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialCardAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_card_id' => $this->material_card_id,
            'attachment_type' => $this->attachment_type,
            'name' => $this->name,
            'url' => $this->url,
            'source_url' => $this->source_url,
            'file_path' => $this->file_path,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'downloaded_at' => $this->downloaded_at?->toDateTimeString(),
            'preview_url' => $this->attachment_type === 'file'
                ? '/api/admin/materials/attachments/' . $this->id . '/preview'
                : null,
            'download_url' => $this->attachment_type === 'file'
                ? '/api/admin/materials/attachments/' . $this->id . '/download'
                : null,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
