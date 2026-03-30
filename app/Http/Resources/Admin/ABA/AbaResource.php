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

        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school_name' => $this->school?->long_name,
            'school_short_name' => $this->school?->short_name,
            'schoolyear_id' => $this->schoolyear_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'student_name' => $this->student_name,
            'student_class' => $this->student_class,
            'title_page_overrides' => is_array($this->title_page_overrides) ? $this->title_page_overrides : [],
            'schoolyear_name' => $this->schoolyear?->name,
            'created_on' => $this->created_on?->toDateString(),
            'evaluated_on' => $this->evaluated_on?->toDateString(),
            'attachments' => AbaAttachmentResource::collection($attachments),
            'main_attachment' => $mainAttachment ? new AbaAttachmentResource($mainAttachment) : null,
            'additional_attachments_count' => $additionalAttachments->count(),
            'latest_extraction' => $this->whenLoaded('latestExtractionRun', function () use ($request) {
                $run = $this->latestExtractionRun;

                return $run ? (new AbaExtractionResource($run))->toArray($request) : null;
            }),
            'next_aba' => $this->when(
                $this->relationLoaded('nextNavigationAba'),
                function () {
                    $nextAba = $this->getRelation('nextNavigationAba');

                    if (! $nextAba) {
                        return null;
                    }

                    return [
                        'id' => (int) $nextAba->id,
                        'title' => $nextAba->title,
                    ];
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
