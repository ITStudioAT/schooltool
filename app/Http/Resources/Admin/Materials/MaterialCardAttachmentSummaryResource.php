<?php

namespace App\Http\Resources\Admin\Materials;

use Illuminate\Http\Request;

class MaterialCardAttachmentSummaryResource extends MaterialCardAttachmentResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        unset(
            $data['file_path'],
            $data['downloaded_at'],
            $data['created_at'],
        );

        return $data;
    }
}
