<?php

namespace App\Http\Resources\Admin\ABA;

use App\Models\AbaAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attachments = $this->relationLoaded('attachments') ? $this->attachments : collect();
        $mainAttachment = $attachments->firstWhere('document_kind', AbaAttachment::DOCUMENT_KIND_MAIN);
        $additionalAttachments = $attachments
            ->where('document_kind', AbaAttachment::DOCUMENT_KIND_ADDITIONAL)
            ->values();
        $latestAnalysisRun = $this->relationLoaded('latestAnalysisRun') ? $this->latestAnalysisRun : null;

        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'schoolyear_id' => $this->schoolyear_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'student_name' => $this->student_name,
            'student_class' => $this->student_class,
            'schoolyear_name' => $this->schoolyear?->name,
            'created_on' => $this->created_on?->toDateString(),
            'evaluated_on' => $this->evaluated_on?->toDateString(),
            'attachments' => AbaAttachmentResource::collection($attachments),
            'main_attachment' => $mainAttachment ? new AbaAttachmentResource($mainAttachment) : null,
            'additional_attachments_count' => $additionalAttachments->count(),
            'latest_analysis_run' => $latestAnalysisRun ? new AbaAnalysisRunResource($latestAnalysisRun) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
