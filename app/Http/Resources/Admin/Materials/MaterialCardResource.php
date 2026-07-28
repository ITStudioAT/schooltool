<?php

namespace App\Http\Resources\Admin\Materials;

use App\Models\MaterialInboxImport;
use App\Models\MaterialShareTarget;
use App\Support\SafeExternalUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'details_loaded' => true,
            'id' => $this->id,
            'school_id' => $this->school_id,
            'user_id' => $this->user_id,
            'workspace_id' => $this->workspace_id,
            'source_school_id' => $this->school_id,
            'source_school_label' => $this->sourceSchoolLabel(),
            'source_user_id' => $this->user_id,
            'source_user_label' => $this->sourceUserLabel(),
            'is_hopper_material' => $this->isHopperMaterial($request),
            'is_shared_material' => (bool) ($this->is_shared_material ?? false),
            'shared_rule_id' => $this->shared_rule_id !== null ? (int) $this->shared_rule_id : null,
            'title' => $this->title,
            'source_url' => SafeExternalUrl::sanitize($this->source_url),
            'source_text' => $this->source_text,
            'subject' => $this->subject,
            'area' => $this->area,
            'unit' => $this->unit,
            'type' => $this->type,
            'status' => $this->status,
            'notes' => $this->notes,
            'keywords' => $this->keywords ?? [],
            'classifications' => $this->classificationRows(),
            'is_linked' => $this->isLinkedCard(),
            'inbox_import_mode' => $this->inboxImportMode(),
            'linked_permission' => $this->linkedPermission(),
            'linked_permission_label' => $this->linkedPermissionLabel(),
            'attachments' => MaterialCardAttachmentResource::collection($this->whenLoaded('attachments')),
            'attachments_count' => $this->when(
                $this->relationLoaded('attachments'),
                fn () => $this->attachments->count()
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function classificationRows(): array
    {
        if (! $this->relationLoaded('classifications')) {
            return [];
        }

        return $this->classifications->map(function ($classification) {
            $subjectName = trim((string) ($classification->subject?->name ?? ''));
            $topicName = trim((string) ($classification->topic?->name ?? ''));
            $unitName = trim((string) ($classification->unit?->name ?? ''));

            return [
                'id' => $classification->id,
                'subject_id' => $classification->subject_id,
                'topic_id' => $classification->topic_id,
                'unit_id' => $classification->unit_id,
                'subject' => $subjectName,
                'topic' => $topicName,
                'unit' => $unitName,
            ];
        })->filter(fn ($row) => $row['subject'] !== '' || $row['topic'] !== '' || $row['unit'] !== '')
            ->values()
            ->all();
    }

    private function sourceSchoolLabel(): ?string
    {
        if (! $this->relationLoaded('school') || ! $this->school) {
            return null;
        }

        $label = trim((string) ($this->school->long_name ?: $this->school->short_name));

        return $label !== '' ? $label : null;
    }

    private function sourceUserLabel(): ?string
    {
        if (! $this->relationLoaded('user') || ! $this->user) {
            return null;
        }

        $name = trim(((string) ($this->user->first_name ?? '')).' '.((string) ($this->user->last_name ?? '')));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($this->user->email ?? ''));

        return $email !== '' ? $email : null;
    }

    private function isHopperMaterial(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && (int) $this->user_id !== (int) $user->id;
    }

    private function inboxImportMode(): ?string
    {
        if (! $this->relationLoaded('inboxImports')) {
            return null;
        }

        foreach ($this->inboxImports as $import) {
            $mode = trim((string) ($import->import_mode ?? ''));
            if ($mode === MaterialInboxImport::MODE_LINK || $mode === MaterialInboxImport::MODE_COPY) {
                return $mode;
            }
        }

        return null;
    }

    private function isLinkedCard(): bool
    {
        return $this->inboxImportMode() === MaterialInboxImport::MODE_LINK;
    }

    private function linkedPermission(): ?string
    {
        $permission = trim((string) ($this->linked_permission ?? ''));
        if (in_array($permission, MaterialShareTarget::PERMISSIONS, true)) {
            return $permission;
        }

        if ($this->isLinkedCard()) {
            return MaterialShareTarget::PERMISSION_READ_ONLY;
        }

        return null;
    }

    private function linkedPermissionLabel(): ?string
    {
        $label = trim((string) ($this->linked_permission_label ?? ''));
        if ($label !== '') {
            return $label;
        }

        return match ($this->linkedPermission()) {
            MaterialShareTarget::PERMISSION_FULL_ACCESS => 'VOLLZUGRIFF',
            MaterialShareTarget::PERMISSION_READ_WRITE => 'LESEN/SCHREIBEN',
            MaterialShareTarget::PERMISSION_READ_APPEND => 'LESEN/HINZUFÜGEN',
            MaterialShareTarget::PERMISSION_READ_ONLY => 'NUR LESEN',
            default => null,
        };
    }
}
