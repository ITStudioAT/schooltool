<?php

namespace App\Http\Resources\Admin\Materials;

use Illuminate\Http\Request;

class MaterialCardSummaryResource extends MaterialCardResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['details_loaded'] = false;
        $data['attachments'] = MaterialCardAttachmentSummaryResource::collection(
            $this->whenLoaded('attachments')
        );
        unset($data['keywords']);

        return $data;
    }
}
