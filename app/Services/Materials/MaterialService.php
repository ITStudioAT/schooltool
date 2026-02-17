<?php

namespace App\Services\Materials;

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MaterialService
{
    public function __construct(
        private readonly MaterialKeywordService $keywordService,
    ) {}

    public function config(User $user): array
    {
        return [
            'module' => 'materials',
            'school_id' => $user->school_id,
            'source_types' => [
                ['value' => MaterialCard::SOURCE_UPLOAD, 'label' => 'Upload'],
                ['value' => MaterialCard::SOURCE_LINK, 'label' => 'Link'],
                ['value' => MaterialCard::SOURCE_NOTE, 'label' => 'Notiz'],
            ],
            'status_values' => [
                ['value' => MaterialCard::STATUS_INBOX, 'label' => 'Inbox'],
                ['value' => MaterialCard::STATUS_IN_PROGRESS, 'label' => 'In Arbeit'],
                ['value' => MaterialCard::STATUS_DONE, 'label' => 'Fertig'],
            ],
        ];
    }

    public function listForUser(User $user, array $filters): LengthAwarePaginator
    {
        $query = MaterialCard::query()
            ->where('user_id', $user->id)
            ->with('attachments')
            ->orderByDesc('updated_at');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%')
                    ->orWhere('source_text', 'like', '%' . $search . '%')
                    ->orWhere('source_url', 'like', '%' . $search . '%');
            });
        }

        foreach (['status', 'subject', 'area', 'unit', 'type'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $query->where($field, $value);
            }
        }

        return $query->paginate(config('schooltool.pagination'));
    }

    public function createCard(User $user, array $data): MaterialCard
    {
        $card = MaterialCard::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'title' => $data['title'],
            'source_type' => $data['source_type'],
            'source_url' => $data['source_url'] ?? null,
            'source_text' => $data['source_text'] ?? null,
            'subject' => $data['subject'] ?? null,
            'area' => $data['area'] ?? null,
            'unit' => $data['unit'] ?? null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? MaterialCard::STATUS_INBOX,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->keywordService->rebuild($card);

        return $card->fresh(['attachments']);
    }

    public function updateCard(MaterialCard $card, array $data): MaterialCard
    {
        $card->update([
            'title' => $data['title'],
            'source_type' => $data['source_type'],
            'source_url' => $data['source_url'] ?? null,
            'source_text' => $data['source_text'] ?? null,
            'subject' => $data['subject'] ?? null,
            'area' => $data['area'] ?? null,
            'unit' => $data['unit'] ?? null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? MaterialCard::STATUS_INBOX,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->keywordService->rebuild($card);

        return $card->fresh(['attachments']);
    }

    public function deleteCard(MaterialCard $card): void
    {
        $card->loadMissing('attachments');

        foreach ($card->attachments as $attachment) {
            if ($attachment->attachment_type === MaterialCardAttachment::TYPE_FILE && $attachment->file_path) {
                Storage::disk('local')->delete($attachment->file_path);
            }
        }

        $card->delete();
    }

    public function addFileAttachment(MaterialCard $card, UploadedFile $file, ?string $name = null): MaterialCardAttachment
    {
        $path = $file->store('materials/' . $card->school_id . '/' . $card->user_id, 'local');

        $attachment = $card->attachments()->create([
            'attachment_type' => MaterialCardAttachment::TYPE_FILE,
            'name' => $name ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        $this->keywordService->rebuild($card->fresh('attachments'));

        return $attachment;
    }

    public function addLinkAttachment(MaterialCard $card, string $url, ?string $name = null): MaterialCardAttachment
    {
        $attachment = $card->attachments()->create([
            'attachment_type' => MaterialCardAttachment::TYPE_LINK,
            'name' => $name ?: $url,
            'url' => $url,
        ]);

        $this->keywordService->rebuild($card->fresh('attachments'));

        return $attachment;
    }

    public function deleteAttachment(MaterialCardAttachment $attachment): void
    {
        $card = $attachment->materialCard()->first();

        if ($attachment->attachment_type === MaterialCardAttachment::TYPE_FILE && $attachment->file_path) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        $attachment->delete();

        if ($card) {
            $this->keywordService->rebuild($card->fresh('attachments'));
        }
    }
}
