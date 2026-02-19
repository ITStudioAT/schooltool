<?php

namespace App\Services\Materials;

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'status_values' => [
                ['value' => MaterialCard::STATUS_INBOX, 'label' => 'Neu/Idee'],
                ['value' => MaterialCard::STATUS_IN_PROGRESS, 'label' => 'In Arbeit'],
                ['value' => MaterialCard::STATUS_DONE, 'label' => 'ok'],
                ['value' => MaterialCard::STATUS_UPDATE_NEEDED, 'label' => 'Änderung nötig'],
            ],
            'type_values' => $this->typeValuesForUser($user),
            'default_type_values' => $this->defaultTypeValues(),
            'can_manage_type_values' => true,
            'classification_tree' => $this->classificationTreeForUser($user),
        ];
    }

    public function listForUser(User $user, array $filters): LengthAwarePaginator
    {
        $query = MaterialCard::query()
            ->where('user_id', $user->id)
            ->with($this->cardRelations())
            ->orderByDesc('updated_at');

        $search = trim((string) ($filters['search'] ?? ''));
        $hasClassificationTables = $this->supportsClassificationTables();
        if ($search !== '') {
            $query->where(function ($q) use ($search, $hasClassificationTables) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%')
                    ->orWhere('source_text', 'like', '%' . $search . '%')
                    ->orWhere('source_url', 'like', '%' . $search . '%');

                if ($hasClassificationTables) {
                    $q->orWhereHas('classifications.subject', fn ($subjectQuery) => $subjectQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('classifications.topic', fn ($topicQuery) => $topicQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('classifications.unit', fn ($unitQuery) => $unitQuery->where('name', 'like', '%' . $search . '%'));
                }
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $subject = trim((string) ($filters['subject'] ?? ''));
        if ($subject !== '' && $hasClassificationTables) {
            $query->whereHas('classifications.subject', fn ($subjectQuery) => $subjectQuery->where('name', $subject));
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        return $query->paginate(config('schooltool.pagination'));
    }

    public function createCard(User $user, array $data): MaterialCard
    {
        $card = MaterialCard::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'title' => $data['title'],
            'source_url' => $data['source_url'] ?? null,
            'source_text' => $data['source_text'] ?? null,
            'subject' => null,
            'area' => null,
            'unit' => null,
            'type' => $this->normalizeOptionalName($data['type'] ?? null),
            'status' => $data['status'] ?? MaterialCard::STATUS_INBOX,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncClassifications($card, $user, $data['classifications'] ?? null);

        $this->keywordService->rebuild($card->fresh($this->cardRelations()));

        return $card->fresh($this->cardRelations());
    }

    public function updateCard(MaterialCard $card, array $data, ?User $user = null): MaterialCard
    {
        $card->update([
            'title' => $data['title'],
            'source_url' => $data['source_url'] ?? null,
            'source_text' => $data['source_text'] ?? null,
            'subject' => null,
            'area' => null,
            'unit' => null,
            'type' => $this->normalizeOptionalName($data['type'] ?? null),
            'status' => $data['status'] ?? MaterialCard::STATUS_INBOX,
            'notes' => $data['notes'] ?? null,
        ]);

        $owner = $user ?: $card->user()->first();
        if ($owner instanceof User) {
            $this->syncClassifications($card, $owner, $data['classifications'] ?? null);
        }

        $this->keywordService->rebuild($card->fresh($this->cardRelations()));

        return $card->fresh($this->cardRelations());
    }

    public function deleteCard(MaterialCard $card): void
    {
        $card->loadMissing('attachments', 'classifications');

        foreach ($card->attachments as $attachment) {
            if ($attachment->attachment_type === MaterialCardAttachment::TYPE_FILE && $attachment->file_path) {
                Storage::disk('local')->delete($attachment->file_path);
            }
        }

        $card->delete();
    }

    public function addFileAttachment(MaterialCard $card, UploadedFile $file, ?string $name = null): MaterialCardAttachment
    {
        $path = $file->storeAs(
            $this->materialAttachmentDirectory($card),
            $this->materialAttachmentStoredFileName($file),
            'local'
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'Datei konnte nicht gespeichert werden.',
            ]);
        }

        $displayName = $this->normalizeOptionalName($name);
        if ($displayName === null) {
            $displayName = mb_substr((string) $file->getClientOriginalName(), 0, 255);
        }

        $attachment = $card->attachments()->create([
            'attachment_type' => MaterialCardAttachment::TYPE_FILE,
            'name' => $displayName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        $this->keywordService->rebuild($card->fresh($this->cardRelations()));

        return $attachment;
    }

    public function addLinkAttachment(MaterialCard $card, string $url, ?string $name = null): MaterialCardAttachment
    {
        $attachment = $card->attachments()->create([
            'attachment_type' => MaterialCardAttachment::TYPE_LINK,
            'name' => $name ?: $url,
            'url' => $url,
        ]);

        $this->keywordService->rebuild($card->fresh($this->cardRelations()));

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
            $this->keywordService->rebuild($card->fresh($this->cardRelations()));
        }
    }

    public function updateAttachmentName(MaterialCardAttachment $attachment, string $name): MaterialCardAttachment
    {
        $normalizedName = $this->normalizeOptionalName($name);
        if ($normalizedName === null) {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen Dateititel angeben.',
            ]);
        }

        $attachment->update([
            'name' => $normalizedName,
        ]);

        $card = $attachment->materialCard()->first();
        if ($card) {
            $this->keywordService->rebuild($card->fresh($this->cardRelations()));
        }

        return $attachment->fresh();
    }

    public function typeValuesForUser(User $user): array
    {
        if (! Schema::hasTable('material_types')) {
            return [];
        }

        $this->ensureTypeValuesForUser($user);

        $query = MaterialType::query()
            ->orderBy('name');

        if ($this->isUserScopedMaterialTypes()) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('school_id', $user->school_id);
        }

        return $query->get()
            ->map(fn (MaterialType $type) => [
                'id' => $type->id,
                'value' => $type->name,
                'label' => $type->name,
            ])
            ->values()
            ->all();
    }

    public function createType(User $user, string $name): MaterialType
    {
        if (! Schema::hasTable('material_types')) {
            throw ValidationException::withMessages([
                'data.name' => 'Materialtypen sind noch nicht verfügbar.',
            ]);
        }

        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen gültigen Typ angeben.',
            ]);
        }

        $attributes = [
            'school_id' => $user->school_id,
            'name' => $normalized,
        ];

        if ($this->isUserScopedMaterialTypes()) {
            $attributes['user_id'] = $user->id;
        }

        return MaterialType::firstOrCreate($attributes);
    }

    public function updateType(User $user, MaterialType $type, string $name): MaterialType
    {
        if ($this->isUserScopedMaterialTypes()) {
            if ((int) $type->user_id !== (int) $user->id) {
                abort(403, 'Typ gehört nicht zum aktuellen Benutzer.');
            }
        } elseif ((int) $type->school_id !== (int) $user->school_id) {
            abort(403, 'Typ gehört nicht zur aktuellen Schule.');
        }

        $newName = $this->normalizeName($name);
        if ($newName === '') {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen gültigen Typ angeben.',
            ]);
        }

        $oldName = (string) $type->name;

        $isUserScoped = $this->isUserScopedMaterialTypes();

        DB::transaction(function () use ($type, $newName, $oldName, $user, $isUserScoped) {
            $existsQuery = MaterialType::query()
                ->where('id', '<>', $type->id)
                ->where('name', $newName);

            if ($isUserScoped) {
                $existsQuery->where('user_id', $user->id);
            } else {
                $existsQuery->where('school_id', $user->school_id);
            }

            $exists = $existsQuery->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'data.name' => 'Dieser Typ existiert bereits.',
                ]);
            }

            $type->update(['name' => $newName]);

            if ($oldName !== $newName) {
                $cards = MaterialCard::query()
                    ->where('type', $oldName);

                if ($isUserScoped) {
                    $cards->where('user_id', $user->id);
                } else {
                    $cards->where('school_id', $user->school_id);
                }

                $cards->update(['type' => $newName]);
            }
        });

        return $type->fresh();
    }

    public function deleteType(User $user, MaterialType $type): void
    {
        if ($this->isUserScopedMaterialTypes()) {
            if ((int) $type->user_id !== (int) $user->id) {
                abort(403, 'Typ gehört nicht zum aktuellen Benutzer.');
            }
        } elseif ((int) $type->school_id !== (int) $user->school_id) {
            abort(403, 'Typ gehört nicht zur aktuellen Schule.');
        }

        $inUseQuery = MaterialCard::query()
            ->where('type', $type->name);

        if ($this->isUserScopedMaterialTypes()) {
            $inUseQuery->where('user_id', $user->id);
        } else {
            $inUseQuery->where('school_id', $user->school_id);
        }

        $inUse = $inUseQuery->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'data.name' => 'Typ ist in Verwendung und kann nicht gelöscht werden.',
            ]);
        }

        $type->delete();
    }

    private function classificationTreeForUser(User $user): array
    {
        if (! $this->supportsClassificationTables()) {
            return [];
        }

        $subjects = MaterialSubject::query()
            ->where('user_id', $user->id)
            ->with(['topics.units'])
            ->orderBy('name')
            ->get();

        return $subjects->map(function (MaterialSubject $subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'topics' => $subject->topics->map(function (MaterialTopic $topic) {
                    return [
                        'id' => $topic->id,
                        'name' => $topic->name,
                        'units' => $topic->units->map(fn (MaterialUnit $unit) => [
                            'id' => $unit->id,
                            'name' => $unit->name,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    private function syncClassifications(MaterialCard $card, User $user, mixed $input): void
    {
        if (! $this->supportsClassificationTables()) {
            return;
        }

        $rows = $this->normalizeClassifications($input);
        $card->classifications()->delete();

        foreach ($rows as $row) {
            $subject = MaterialSubject::firstOrCreate([
                'user_id' => $user->id,
                'name' => $row['subject'],
            ]);

            $topic = null;
            $unit = null;

            if ($row['topic'] !== '') {
                $topic = MaterialTopic::firstOrCreate([
                    'subject_id' => $subject->id,
                    'name' => $row['topic'],
                ]);

                if ($row['unit'] !== '') {
                    $unit = MaterialUnit::firstOrCreate([
                        'topic_id' => $topic->id,
                        'name' => $row['unit'],
                    ]);
                }
            }

            $card->classifications()->create([
                'subject_id' => $subject->id,
                'topic_id' => $topic?->id,
                'unit_id' => $unit?->id,
            ]);
        }
    }

    private function normalizeClassifications(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $rows = [];
        $seen = [];

        foreach ($input as $row) {
            if (! is_array($row)) {
                continue;
            }

            $subject = $this->normalizeName($row['subject'] ?? null);
            $topic = $this->normalizeName($row['topic'] ?? null);
            $unit = $this->normalizeName($row['unit'] ?? null);

            if ($subject === '') {
                continue;
            }

            if ($topic === '') {
                $unit = '';
            }

            $key = mb_strtolower($subject . '|' . $topic . '|' . $unit);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = [
                'subject' => $subject,
                'topic' => $topic,
                'unit' => $unit,
            ];
        }

        return $rows;
    }

    private function normalizeName(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, 255);
    }

    private function normalizeOptionalName(mixed $value): ?string
    {
        $text = $this->normalizeName($value);
        return $text === '' ? null : $text;
    }

    private function ensureTypeValuesForUser(User $user): void
    {
        if (! Schema::hasTable('material_types')) {
            return;
        }

        $knownQuery = MaterialType::query();
        if ($this->isUserScopedMaterialTypes()) {
            $knownQuery->where('user_id', $user->id);
        } else {
            $knownQuery->where('school_id', $user->school_id);
        }

        $known = $knownQuery
            ->pluck('name')
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->flip()
            ->all();

        $cardTypesQuery = MaterialCard::query()
            ->whereNotNull('type')
            ->where('type', '<>', '');

        if ($this->isUserScopedMaterialTypes()) {
            $cardTypesQuery->where('user_id', $user->id);
        } else {
            $cardTypesQuery->where('school_id', $user->school_id);
        }

        $cardTypes = $cardTypesQuery->pluck('type');
        $schoolId = (int) $user->school_id;

        foreach ($cardTypes as $rawType) {
            $name = $this->normalizeName($rawType);
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($known[$key])) {
                continue;
            }

            $attributes = [
                'school_id' => $schoolId,
                'name' => $name,
            ];

            if ($this->isUserScopedMaterialTypes()) {
                $attributes['user_id'] = $user->id;
            }

            MaterialType::query()->create($attributes);

            $known[$key] = true;
        }
    }

    private function isUserScopedMaterialTypes(): bool
    {
        return Schema::hasTable('material_types') && Schema::hasColumn('material_types', 'user_id');
    }

    private function defaultTypeValues(): array
    {
        $rawValues = config('schooltool.materials_default_types', []);
        if (! is_array($rawValues)) {
            return [];
        }

        $result = [];
        $seen = [];

        foreach ($rawValues as $rawValue) {
            $name = $this->normalizeName($rawValue);
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = [
                'value' => $name,
                'label' => $name,
            ];
        }

        return $result;
    }

    private function cardRelations(): array
    {
        if (! $this->supportsClassificationTables()) {
            return ['attachments'];
        }

        return [
            'attachments',
            'classifications.subject',
            'classifications.topic',
            'classifications.unit',
        ];
    }

    private function supportsClassificationTables(): bool
    {
        return Schema::hasTable('material_subjects')
            && Schema::hasTable('material_topics')
            && Schema::hasTable('material_units')
            && Schema::hasTable('material_card_classifications');
    }

    private function materialAttachmentDirectory(MaterialCard $card): string
    {
        $now = now();

        return implode('/', [
            'materials',
            'schools',
            (string) $card->school_id,
            'users',
            (string) $card->user_id,
            'cards',
            (string) $card->id,
            $now->format('Y'),
            $now->format('m'),
        ]);
    }

    private function materialAttachmentStoredFileName(UploadedFile $file): string
    {
        $originalBaseName = (string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($originalBaseName, '-');

        if ($slug === '') {
            $slug = 'file';
        }

        $slug = mb_substr($slug, 0, 120);
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $suffix = $extension !== '' ? '.' . $extension : '';

        return Str::uuid()->toString() . '-' . $slug . $suffix;
    }
}
