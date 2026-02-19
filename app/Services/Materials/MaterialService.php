<?php

namespace App\Services\Materials;

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialStatus;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\SchoolTool;
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
    private const DEFAULT_MAX_UPLOAD_SIZE_KB = 20480;
    private const DEFAULT_TYPE_ICON = 'mdi-file-document-outline';

    public function __construct(
        private readonly MaterialKeywordService $keywordService,
    ) {}

    public function config(User $user): array
    {
        return [
            'module' => 'materials',
            'school_id' => $user->school_id,
            'status_values' => $this->statusValuesForUser($user),
            'type_values' => $this->typeValuesForUser($user),
            'default_type_values' => $this->defaultTypeValues(),
            'type_icon_options' => $this->typeIconOptions(),
            'can_manage_type_values' => true,
            'can_manage_status_values' => $user->hasAnyRole(['admin', 'super_admin']),
            'file_settings' => $this->fileSettingsForUser($user),
            'can_manage_file_settings' => $user->hasAnyRole(['admin', 'super_admin']) && $this->supportsSchoolFileSettings(),
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
        $defaultStatus = $this->defaultStatusValueForUser($user);

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
            'status' => $data['status'] ?? $defaultStatus,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncClassifications($card, $user, $data['classifications'] ?? null);

        $this->keywordService->rebuild($card->fresh($this->cardRelations()));

        return $card->fresh($this->cardRelations());
    }

    public function updateCard(MaterialCard $card, array $data, ?User $user = null): MaterialCard
    {
        $owner = $user ?: $card->user()->first();
        $defaultStatus = $owner instanceof User ? $this->defaultStatusValueForUser($owner) : MaterialCard::STATUS_INBOX;

        $card->update([
            'title' => $data['title'],
            'source_url' => $data['source_url'] ?? null,
            'source_text' => $data['source_text'] ?? null,
            'subject' => null,
            'area' => null,
            'unit' => null,
            'type' => $this->normalizeOptionalName($data['type'] ?? null),
            'status' => $data['status'] ?? $defaultStatus,
            'notes' => $data['notes'] ?? null,
        ]);

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
            $this->materialAttachmentStoredFileNameFromOriginalName((string) $file->getClientOriginalName()),
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

    public function addFileAttachmentFromTempUpload(
        User $user,
        MaterialCard $card,
        string $uploadId,
        ?string $name = null
    ): MaterialCardAttachment {
        $normalizedUploadId = $this->normalizeTempUploadId($uploadId);
        $tempPath = $this->tempUploadPathById($user, $normalizedUploadId);

        if ($tempPath === null || ! Storage::disk('local')->exists($tempPath)) {
            throw ValidationException::withMessages([
                'data.upload_id' => 'Upload wurde nicht gefunden.',
            ]);
        }

        $sizeBytes = (int) (Storage::disk('local')->size($tempPath) ?: 0);
        $maxBytes = $this->maxUploadSizeForSchool((int) $user->school_id) * 1024;
        if ($maxBytes > 0 && $sizeBytes > $maxBytes) {
            Storage::disk('local')->delete($tempPath);
            throw ValidationException::withMessages([
                'data.upload_id' => 'Datei überschreitet die maximal erlaubte Uploadgröße.',
            ]);
        }

        $tempFileName = basename($tempPath);
        $destinationPath = $this->materialAttachmentDirectory($card)
            . '/' . $this->materialAttachmentStoredFileNameFromOriginalName($tempFileName);

        $moved = Storage::disk('local')->move($tempPath, $destinationPath);
        if (! $moved) {
            throw ValidationException::withMessages([
                'data.upload_id' => 'Upload konnte nicht übernommen werden.',
            ]);
        }

        $displayName = $this->normalizeOptionalName($name);
        if ($displayName === null) {
            $displayName = $this->defaultAttachmentNameFromTempFileName($tempFileName);
        }

        $mimeType = Storage::disk('local')->mimeType($destinationPath);
        $finalSizeBytes = (int) (Storage::disk('local')->size($destinationPath) ?: $sizeBytes);

        $attachment = $card->attachments()->create([
            'attachment_type' => MaterialCardAttachment::TYPE_FILE,
            'name' => $displayName,
            'file_path' => $destinationPath,
            'mime_type' => is_string($mimeType) ? $mimeType : null,
            'size_bytes' => $finalSizeBytes > 0 ? $finalSizeBytes : null,
        ]);

        $this->keywordService->rebuild($card->fresh($this->cardRelations()));

        return $attachment;
    }

    public function deleteTempUpload(User $user, string $uploadId): void
    {
        $normalizedUploadId = $this->normalizeTempUploadId($uploadId);
        $tempPath = $this->tempUploadPathById($user, $normalizedUploadId);
        if ($tempPath === null) {
            return;
        }

        Storage::disk('local')->delete($tempPath);
    }

    public function resolveTempUploadPathForUser(User $user, string $uploadId): ?string
    {
        return $this->tempUploadPathById($user, $uploadId);
    }

    public function tempUploadStoragePathForUser(User $user): string
    {
        return 'app/private/' . $this->tempUploadDirectoryForUser($user);
    }

    public function maxUploadSizeKbForUser(User $user): int
    {
        return $this->maxUploadSizeForSchool((int) $user->school_id);
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
                'icon' => $this->normalizeTypeIcon($type->icon ?? null),
            ])
            ->values()
            ->all();
    }

    public function statusValuesForUser(User $user): array
    {
        if (! Schema::hasTable('material_statuses')) {
            return $this->defaultStatusValues();
        }

        $this->ensureStatusValuesForSchool((int) $user->school_id);

        $rows = MaterialStatus::query()
            ->where('school_id', $user->school_id)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return $this->defaultStatusValues();
        }

        return $rows->map(fn (MaterialStatus $status) => [
            'id' => $status->id,
            'value' => $status->value,
            'label' => $status->label,
        ])->values()->all();
    }

    public function createType(User $user, string $name, ?string $icon = null): MaterialType
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

        if ($this->hasTypeIconColumn()) {
            $attributes['icon'] = $this->normalizeTypeIcon($icon);
        }

        return MaterialType::firstOrCreate($attributes);
    }

    public function updateType(User $user, MaterialType $type, string $name, ?string $icon = null): MaterialType
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
        $newIcon = $this->normalizeTypeIcon($icon);

        $isUserScoped = $this->isUserScopedMaterialTypes();

        DB::transaction(function () use ($type, $newName, $oldName, $newIcon, $user, $isUserScoped) {
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

            $updateData = ['name' => $newName];
            if ($this->hasTypeIconColumn()) {
                $updateData['icon'] = $newIcon;
            }

            $type->update($updateData);

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

    public function createStatus(User $user, string $label): MaterialStatus
    {
        if (! Schema::hasTable('material_statuses')) {
            throw ValidationException::withMessages([
                'data.label' => 'Statuswerte sind noch nicht verfügbar.',
            ]);
        }

        $normalizedLabel = $this->normalizeName($label);
        if ($normalizedLabel === '') {
            throw ValidationException::withMessages([
                'data.label' => 'Bitte einen gültigen Status angeben.',
            ]);
        }

        $this->ensureStatusValuesForSchool((int) $user->school_id);

        $value = $this->statusValueFromLabel($normalizedLabel);

        $alreadyExists = MaterialStatus::query()
            ->where('school_id', $user->school_id)
            ->where('value', $value)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'data.label' => 'Dieser Status existiert bereits.',
            ]);
        }

        return MaterialStatus::query()->create([
            'school_id' => $user->school_id,
            'value' => $value,
            'label' => $normalizedLabel,
        ]);
    }

    public function updateStatus(User $user, MaterialStatus $status, string $label): MaterialStatus
    {
        if ((int) $status->school_id !== (int) $user->school_id) {
            abort(403, 'Status gehört nicht zur aktuellen Schule.');
        }

        $normalizedLabel = $this->normalizeName($label);
        if ($normalizedLabel === '') {
            throw ValidationException::withMessages([
                'data.label' => 'Bitte einen gültigen Status angeben.',
            ]);
        }

        $status->update([
            'label' => $normalizedLabel,
        ]);

        return $status->fresh();
    }

    public function deleteStatus(User $user, MaterialStatus $status): void
    {
        if ((int) $status->school_id !== (int) $user->school_id) {
            abort(403, 'Status gehört nicht zur aktuellen Schule.');
        }

        $countInSchool = MaterialStatus::query()
            ->where('school_id', $user->school_id)
            ->count();

        if ($countInSchool <= 1) {
            throw ValidationException::withMessages([
                'data.label' => 'Mindestens ein Status muss bestehen bleiben.',
            ]);
        }

        $inUse = MaterialCard::query()
            ->where('school_id', $user->school_id)
            ->where('status', $status->value)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'data.label' => 'Status ist in Verwendung und kann nicht gelöscht werden.',
            ]);
        }

        $status->delete();
    }

    public function fileSettingsForUser(User $user): array
    {
        $maxUploadSizeKb = $this->maxUploadSizeForSchool((int) $user->school_id);

        return [
            'max_upload_size_kb' => $maxUploadSizeKb,
            'max_upload_size_mb' => round($maxUploadSizeKb / 1024, 2),
        ];
    }

    public function updateFileSettings(User $user, int $maxUploadSizeKb): array
    {
        $normalized = max(1, min(1024 * 1024, (int) $maxUploadSizeKb));

        if (! $this->supportsSchoolFileSettings()) {
            throw ValidationException::withMessages([
                'data.max_upload_size_kb' => 'Dateieinstellungen sind noch nicht verfügbar. Bitte Migration ausführen.',
            ]);
        }

        $schoolTool = SchoolTool::query()->firstOrCreate(
            ['school_id' => $user->school_id],
            [
                'tutoring_student_must_be_confirmed' => false,
                'tutoring_confirmer_email' => '',
                'material_max_file_upload_size' => self::DEFAULT_MAX_UPLOAD_SIZE_KB,
            ]
        );

        $schoolTool->update([
            'material_max_file_upload_size' => $normalized,
        ]);

        return $this->fileSettingsForUser($user);
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

    private function defaultStatusValueForUser(User $user): string
    {
        $statusValues = $this->statusValuesForUser($user);
        foreach ($statusValues as $status) {
            $value = trim((string) ($status['value'] ?? ''));
            if ($value === MaterialCard::STATUS_INBOX) {
                return $value;
            }
        }

        $first = trim((string) ($statusValues[0]['value'] ?? ''));

        return $first !== '' ? $first : MaterialCard::STATUS_INBOX;
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

            if ($this->hasTypeIconColumn()) {
                $attributes['icon'] = $this->defaultTypeIcon();
            }

            MaterialType::query()->create($attributes);

            $known[$key] = true;
        }
    }

    private function ensureStatusValuesForSchool(int $schoolId): void
    {
        if (! Schema::hasTable('material_statuses')) {
            return;
        }

        $knownRows = MaterialStatus::query()
            ->where('school_id', $schoolId)
            ->get();

        $known = $knownRows
            ->pluck('value')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->map(fn ($value) => mb_strtolower($value))
            ->flip()
            ->all();

        foreach ($this->defaultStatusValues() as $defaultStatus) {
            $value = trim((string) ($defaultStatus['value'] ?? ''));
            $label = trim((string) ($defaultStatus['label'] ?? ''));
            if ($value === '' || $label === '') {
                continue;
            }

            $key = mb_strtolower($value);
            if (isset($known[$key])) {
                continue;
            }

            MaterialStatus::query()->create([
                'school_id' => $schoolId,
                'value' => $value,
                'label' => $label,
            ]);
            $known[$key] = true;
        }

        if (! Schema::hasTable('material_cards')) {
            return;
        }

        $cardStatuses = MaterialCard::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('status')
            ->where('status', '<>', '')
            ->distinct()
            ->pluck('status');

        foreach ($cardStatuses as $rawStatus) {
            $value = $this->normalizeName($rawStatus);
            if ($value === '') {
                continue;
            }

            $key = mb_strtolower($value);
            if (isset($known[$key])) {
                continue;
            }

            MaterialStatus::query()->create([
                'school_id' => $schoolId,
                'value' => $value,
                'label' => $this->defaultStatusLabelForValue($value),
            ]);
            $known[$key] = true;
        }
    }

    private function isUserScopedMaterialTypes(): bool
    {
        return Schema::hasTable('material_types') && Schema::hasColumn('material_types', 'user_id');
    }

    private function hasTypeIconColumn(): bool
    {
        return Schema::hasTable('material_types') && Schema::hasColumn('material_types', 'icon');
    }

    private function defaultStatusValues(): array
    {
        return [
            ['value' => MaterialCard::STATUS_INBOX, 'label' => 'Neu/Idee'],
            ['value' => MaterialCard::STATUS_IN_PROGRESS, 'label' => 'In Arbeit'],
            ['value' => MaterialCard::STATUS_DONE, 'label' => 'ok'],
            ['value' => MaterialCard::STATUS_UPDATE_NEEDED, 'label' => 'Änderung nötig'],
        ];
    }

    private function defaultStatusLabelForValue(string $value): string
    {
        $normalizedValue = mb_strtolower(trim($value));
        foreach ($this->defaultStatusValues() as $status) {
            $statusValue = mb_strtolower(trim((string) ($status['value'] ?? '')));
            $statusLabel = trim((string) ($status['label'] ?? ''));
            if ($statusValue === $normalizedValue && $statusLabel !== '') {
                return $statusLabel;
            }
        }

        return $value;
    }

    private function statusValueFromLabel(string $label): string
    {
        $value = Str::slug($label, '_');

        if ($value === '') {
            $value = 'status';
        }

        return mb_substr($value, 0, 255);
    }

    private function maxUploadSizeForSchool(int $schoolId): int
    {
        if ($schoolId <= 0 || ! $this->supportsSchoolFileSettings()) {
            return self::DEFAULT_MAX_UPLOAD_SIZE_KB;
        }

        $schoolTool = SchoolTool::query()->firstOrCreate(
            ['school_id' => $schoolId],
            [
                'tutoring_student_must_be_confirmed' => false,
                'tutoring_confirmer_email' => '',
                'material_max_file_upload_size' => self::DEFAULT_MAX_UPLOAD_SIZE_KB,
            ]
        );

        $value = (int) ($schoolTool->material_max_file_upload_size ?? 0);
        if ($value <= 0) {
            return self::DEFAULT_MAX_UPLOAD_SIZE_KB;
        }

        return $value;
    }

    private function supportsSchoolFileSettings(): bool
    {
        return Schema::hasTable('school_tools') && Schema::hasColumn('school_tools', 'material_max_file_upload_size');
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
            $name = '';
            $icon = null;

            if (is_array($rawValue)) {
                $name = $this->normalizeName($rawValue['value'] ?? $rawValue['label'] ?? '');
                $icon = $this->normalizeTypeIcon($rawValue['icon'] ?? null);
            } else {
                $name = $this->normalizeName($rawValue);
            }

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
                'icon' => $icon ?: $this->defaultTypeIcon(),
            ];
        }

        return $result;
    }

    private function typeIconOptions(): array
    {
        $rawOptions = config('schooltool.materials_type_icon_options', []);
        if (! is_array($rawOptions) || count($rawOptions) === 0) {
            return [
                ['value' => self::DEFAULT_TYPE_ICON, 'label' => 'Dokument'],
            ];
        }

        $result = [];
        $seen = [];

        foreach ($rawOptions as $rawOption) {
            $value = '';
            $label = '';

            if (is_array($rawOption)) {
                $value = trim((string) ($rawOption['value'] ?? ''));
                $label = trim((string) ($rawOption['label'] ?? ''));
            } else {
                $value = trim((string) $rawOption);
            }

            if ($value === '') {
                continue;
            }

            $key = mb_strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }

            if ($label === '') {
                $label = $value;
            }

            $seen[$key] = true;
            $result[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        if (count($result) === 0) {
            return [
                ['value' => self::DEFAULT_TYPE_ICON, 'label' => 'Dokument'],
            ];
        }

        return $result;
    }

    private function defaultTypeIcon(): string
    {
        return (string) ($this->typeIconOptions()[0]['value'] ?? self::DEFAULT_TYPE_ICON);
    }

    private function allowedTypeIcons(): array
    {
        return array_values(array_filter(
            array_map(
                fn (array $option) => trim((string) ($option['value'] ?? '')),
                $this->typeIconOptions()
            ),
            fn ($value) => $value !== ''
        ));
    }

    private function normalizeTypeIcon(?string $icon): string
    {
        $value = trim((string) $icon);
        if ($value === '') {
            return $this->defaultTypeIcon();
        }

        $allowed = $this->allowedTypeIcons();
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return $this->defaultTypeIcon();
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

    private function materialAttachmentStoredFileNameFromOriginalName(string $originalName): string
    {
        $originalBaseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Str::slug($originalBaseName, '-');

        if ($slug === '') {
            $slug = 'file';
        }

        $slug = mb_substr($slug, 0, 120);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $suffix = $extension !== '' ? '.' . $extension : '';

        return Str::uuid()->toString() . '-' . $slug . $suffix;
    }

    private function tempUploadDirectoryForUser(User $user): string
    {
        return implode('/', [
            'materials',
            'temp',
            (string) $user->school_id,
            (string) $user->id,
        ]);
    }

    private function normalizeTempUploadId(string $uploadId): string
    {
        $value = trim($uploadId);
        if ($value === '' || ! preg_match('/^[a-f0-9-]{20,64}$/i', $value)) {
            throw ValidationException::withMessages([
                'data.upload_id' => 'Ungültige Upload-ID.',
            ]);
        }

        return $value;
    }

    private function tempUploadPathById(User $user, string $uploadId): ?string
    {
        $normalizedUploadId = $this->normalizeTempUploadId($uploadId);
        $directory = $this->tempUploadDirectoryForUser($user);
        $files = Storage::disk('local')->files($directory);

        foreach ($files as $file) {
            $name = basename((string) $file);
            if (Str::startsWith($name, $normalizedUploadId)) {
                return $file;
            }
        }

        return null;
    }

    private function defaultAttachmentNameFromTempFileName(string $tempFileName): string
    {
        $base = (string) pathinfo($tempFileName, PATHINFO_FILENAME);
        $clean = preg_replace('/^[a-f0-9-]{20,64}-?/i', '', $base);
        $clean = trim((string) $clean);
        if ($clean === '') {
            $clean = $base;
        }

        return mb_substr($clean, 0, 255);
    }
}
