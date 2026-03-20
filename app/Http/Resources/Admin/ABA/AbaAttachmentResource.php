<?php

namespace App\Http\Resources\Admin\ABA;

use App\Models\AbaAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbaAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aba_id' => $this->aba_id,
            'document_kind' => $this->document_kind,
            'is_main_document' => $this->document_kind === AbaAttachment::DOCUMENT_KIND_MAIN,
            'original_name' => $this->original_name,
            'path' => $this->path,
            'stored_name' => $this->stored_name,
            'disk' => $this->disk,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'created_at' => $this->created_at,
        ];
    }
}
