<?php

namespace App\Services\Materials;

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialCardClassification;
use App\Models\MaterialCardDeletedClassification;
use App\Models\MaterialInboxImport;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareTarget;
use App\Models\MaterialStatus;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialTopicInboxImport;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\MaterialUnitInboxImport;
use App\Models\MaterialWorkspace;
use App\Models\SchoolTool;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaterialService
{
    private const DEFAULT_MAX_UPLOAD_SIZE_KB = 20480;

    private const DEFAULT_MATERIALS_PAGINATION_NUMBER = 30;

    private const DEFAULT_TYPE_ICON = 'mdi-file-document-outline';

    private ?bool $hasMaterialInboxImportsTableCache = null;

    private ?bool $materialInboxImportsHasImportModeColumnCache = null;

    private ?bool $hasMaterialUnitInboxImportsTableCache = null;

    private ?bool $hasMaterialTopicInboxImportsTableCache = null;

    /** @var array<int,int> */
    private array $activeWorkspaceByUserId = [];

    public function __construct(
        private readonly MaterialKeywordService $keywordService,
        private readonly MaterialWorkspaceService $workspaceService,
    ) {}

    public function config(User $user): array
    {
        $this->syncLinkedUnitInboxImportsForUser($user);
        $this->syncLinkedTopicInboxImportsForUser($user);
        $workspace = $this->activeWorkspaceForUser($user);

        return [
            'module' => 'materials',
            'school_id' => $user->school_id,
            'workspace' => [
                'id' => (int) $workspace->id,
                'name' => (string) $workspace->name,
            ],
            'status_values' => $this->statusValuesForUser($user),
            'type_values' => $this->typeValuesForUser($user),
            'default_type_values' => $this->defaultTypeValues(),
            'type_icon_options' => $this->typeIconOptions(),
            'can_manage_type_values' => true,
            'can_manage_status_values' => $user->hasAnyRole(['admin', 'super_admin']),
            'file_settings' => $this->fileSettingsForUser($user),
            'can_manage_file_settings' => $user->hasAnyRole(['admin', 'super_admin']) && $this->supportsSchoolFileSettings(),
            'user_settings' => $this->userSettingsForUser($user),
            'can_manage_user_settings' => $this->supportsUserMaterialsPaginationSettings(),
            'classification_tree' => $this->classificationTreeForUser($user),
        ];
    }

    public function listForUser(User $user, array $filters): LengthAwarePaginator
    {
        $this->syncLinkedInboxImportsForUser($user);
        $this->syncLinkedUnitInboxImportsForUser($user);
        $this->syncLinkedTopicInboxImportsForUser($user);
        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $query = MaterialCard::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->with($this->cardRelations())
            ->orderByDesc('updated_at');

        $search = trim((string) ($filters['search'] ?? ''));
        $hasClassificationTables = $this->supportsClassificationTables();
        if ($search !== '') {
            $query->where(function ($q) use ($search, $hasClassificationTables) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%')
                    ->orWhere('source_text', 'like', '%'.$search.'%')
                    ->orWhere('source_url', 'like', '%'.$search.'%');

                if ($hasClassificationTables) {
                    $q->orWhereHas('classifications.subject', fn ($subjectQuery) => $subjectQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('classifications.topic', fn ($topicQuery) => $topicQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('classifications.unit', fn ($unitQuery) => $unitQuery->where('name', 'like', '%'.$search.'%'));
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

        $topic = trim((string) ($filters['topic'] ?? ''));
        if ($topic !== '' && $hasClassificationTables) {
            $query->whereHas('classifications.topic', fn ($topicQuery) => $topicQuery->where('name', $topic));
        }

        $unit = trim((string) ($filters['unit'] ?? ''));
        if ($unit !== '' && $hasClassificationTables) {
            $query->whereHas('classifications.unit', fn ($unitQuery) => $unitQuery->where('name', $unit));
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        $cards = $query->paginate($this->materialsPaginationNumberForUser($user));
        $this->hydrateLinkedPermissionMetadata($user, $cards->getCollection());

        return $cards;
    }

    public function hydrateLinkedPermissionMetadata(User $user, Collection $cards): void
    {
        if ($cards->isEmpty() || ! $this->supportsLinkedInboxImports()) {
            return;
        }

        $importsByCardId = [];
        $ruleIds = [];

        foreach ($cards as $card) {
            if (! $card instanceof MaterialCard || ! $card->relationLoaded('inboxImports')) {
                continue;
            }

            $import = $card->inboxImports
                ->first(fn (MaterialInboxImport $row) => trim((string) ($row->import_mode ?? '')) === MaterialInboxImport::MODE_LINK);
            if (! $import) {
                continue;
            }

            $cardId = (int) ($card->id ?? 0);
            if ($cardId <= 0) {
                continue;
            }

            $importsByCardId[$cardId] = $import;
            $ruleId = (int) ($import->source_rule_id ?? 0);
            if ($ruleId > 0) {
                $ruleIds[] = $ruleId;
            }
        }

        if ($importsByCardId === []) {
            return;
        }

        $ruleMap = MaterialShareRule::query()
            ->whereIn('id', array_values(array_unique($ruleIds)))
            ->with(['targets:id,material_share_rule_id,target_type,audience_scope,permission,user_group_id,user_id'])
            ->get()
            ->keyBy(fn (MaterialShareRule $rule) => (int) $rule->id);

        $memberGroupSet = $this->memberGroupSetForUser($user);

        foreach ($cards as $card) {
            if (! $card instanceof MaterialCard) {
                continue;
            }

            $cardId = (int) ($card->id ?? 0);
            $import = $importsByCardId[$cardId] ?? null;
            if (! $import instanceof MaterialInboxImport) {
                continue;
            }

            $permission = MaterialShareTarget::PERMISSION_READ_ONLY;
            $ruleId = (int) ($import->source_rule_id ?? 0);
            $rule = $ruleId > 0 ? $ruleMap->get($ruleId) : null;
            if ($rule instanceof MaterialShareRule) {
                $permission = $this->resolveLinkedPermissionForUser(
                    rule: $rule,
                    userId: (int) $user->id,
                    schoolId: (int) $user->school_id,
                    memberGroupSet: $memberGroupSet,
                );
            }

            $card->setAttribute('linked_permission', $permission);
            $card->setAttribute('linked_permission_label', $this->linkedPermissionLabel($permission));
        }
    }

    public function linkedPermissionForCard(User $user, MaterialCard $card): ?string
    {
        if ((int) ($card->user_id ?? 0) !== (int) $user->id) {
            return null;
        }

        if (! $this->supportsLinkedInboxImports()) {
            return null;
        }

        $import = MaterialInboxImport::query()
            ->where('target_user_id', (int) $user->id)
            ->where('target_material_card_id', (int) $card->id)
            ->where('import_mode', MaterialInboxImport::MODE_LINK)
            ->latest('id')
            ->first(['source_rule_id']);

        if (! $import) {
            return null;
        }

        $ruleId = (int) ($import->source_rule_id ?? 0);
        if ($ruleId <= 0) {
            return MaterialShareTarget::PERMISSION_READ_ONLY;
        }

        $rule = MaterialShareRule::query()
            ->whereKey($ruleId)
            ->with(['targets:id,material_share_rule_id,target_type,audience_scope,permission,user_group_id,user_id'])
            ->first();

        if (! $rule) {
            return MaterialShareTarget::PERMISSION_READ_ONLY;
        }

        return $this->resolveLinkedPermissionForUser(
            rule: $rule,
            userId: (int) $user->id,
            schoolId: (int) $user->school_id,
            memberGroupSet: $this->memberGroupSetForUser($user),
        );
    }

    public function syncLinkedInboxCardForUser(User $user, MaterialCard $card): void
    {
        if ((int) ($card->user_id ?? 0) !== (int) $user->id) {
            return;
        }

        $this->syncLinkedInboxImportsForUser($user, [(int) $card->id]);
    }

    public function propagateLinkedWritableCardFromTarget(User $user, MaterialCard $targetCard): void
    {
        if ((int) ($targetCard->user_id ?? 0) !== (int) $user->id) {
            return;
        }

        if (! $this->supportsLinkedInboxImports()) {
            return;
        }

        $import = MaterialInboxImport::query()
            ->where('target_user_id', (int) $user->id)
            ->where('target_material_card_id', (int) $targetCard->id)
            ->where('import_mode', MaterialInboxImport::MODE_LINK)
            ->latest('id')
            ->first(['source_rule_id', 'source_school_id', 'source_material_id']);

        if (! $import) {
            return;
        }

        $permission = MaterialShareTarget::PERMISSION_READ_ONLY;
        $ruleId = (int) ($import->source_rule_id ?? 0);
        if ($ruleId > 0) {
            $rule = MaterialShareRule::query()
                ->whereKey($ruleId)
                ->with(['targets:id,material_share_rule_id,target_type,audience_scope,permission,user_group_id,user_id'])
                ->first();

            if ($rule) {
                $permission = $this->resolveLinkedPermissionForUser(
                    rule: $rule,
                    userId: (int) $user->id,
                    schoolId: (int) $user->school_id,
                    memberGroupSet: $this->memberGroupSetForUser($user),
                );
            }
        }

        if (! in_array($permission, [MaterialShareTarget::PERMISSION_READ_WRITE, MaterialShareTarget::PERMISSION_FULL_ACCESS], true)) {
            return;
        }

        $sourceSchoolId = (int) ($import->source_school_id ?? 0);
        $sourceMaterialId = (int) ($import->source_material_id ?? 0);
        if ($sourceSchoolId <= 0 || $sourceMaterialId <= 0) {
            return;
        }

        $sourceCard = MaterialCard::query()
            ->where('school_id', $sourceSchoolId)
            ->whereKey($sourceMaterialId)
            ->with('attachments')
            ->first();

        if (! $sourceCard) {
            return;
        }

        $targetFresh = MaterialCard::query()
            ->where('user_id', (int) $user->id)
            ->whereKey((int) $targetCard->id)
            ->with('attachments')
            ->first();

        if (! $targetFresh) {
            return;
        }

        if (! $this->linkedCardNeedsSync($sourceCard, $targetFresh)) {
            return;
        }

        $this->syncLinkedSourceCardFromTarget($sourceCard, $targetFresh);
    }

    public function createCard(User $user, array $data): MaterialCard
    {
        $defaultStatus = $this->defaultStatusValueForUser($user);
        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $card = MaterialCard::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
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
        $card->loadMissing('attachments', 'classifications.subject', 'classifications.topic', 'classifications.unit');
        $restoreLimit = $this->restorableDeletedCardsLimit();
        $this->trimRestorableDeletedCardsForUser(
            userId: (int) $card->user_id,
            keepCount: max(0, $restoreLimit - 1),
            workspaceId: (int) ($card->workspace_id ?? 0),
        );

        DB::transaction(function () use ($card) {
            $this->storeDeletedClassificationSnapshot($card);

            foreach ($card->attachments as $attachment) {
                $attachment->delete();
            }

            $card->delete();
        });
    }

    public function restoreLastDeletedCard(User $user): ?MaterialCard
    {
        $restoreLimit = $this->restorableDeletedCardsLimit();
        if ($restoreLimit < 1) {
            return null;
        }

        $workspaceId = $this->activeWorkspaceIdForUser($user);

        /** @var MaterialCard|null $card */
        $card = MaterialCard::onlyTrashed()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('deleted_at')
            ->first();

        if (! $card) {
            return null;
        }

        DB::transaction(function () use ($card, $user) {
            $card->restore();

            MaterialCardAttachment::onlyTrashed()
                ->where('material_card_id', $card->id)
                ->restore();

            $this->restoreClassificationPathForRestoredCard($card, $user);
        });

        return $card->fresh($this->cardRelations());
    }

    public function restoreDeletedCard(User $user, int $cardId): ?MaterialCard
    {
        if ($cardId <= 0 || $this->restorableDeletedCardsLimit() < 1) {
            return null;
        }

        $workspaceId = $this->activeWorkspaceIdForUser($user);

        /** @var MaterialCard|null $card */
        $card = MaterialCard::onlyTrashed()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->where('id', $cardId)
            ->first();

        if (! $card) {
            return null;
        }

        DB::transaction(function () use ($card, $user) {
            $card->restore();

            MaterialCardAttachment::onlyTrashed()
                ->where('material_card_id', $card->id)
                ->restore();

            $this->restoreClassificationPathForRestoredCard($card, $user);
        });

        return $card->fresh($this->cardRelations());
    }

    public function purgeDeletedCardById(User $user, int $cardId): bool
    {
        if ($cardId <= 0) {
            return false;
        }

        $workspaceId = $this->activeWorkspaceIdForUser($user);

        /** @var MaterialCard|null $card */
        $card = MaterialCard::onlyTrashed()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->where('id', $cardId)
            ->first();

        if (! $card) {
            return false;
        }

        DB::transaction(function () use ($card) {
            $this->forceDeleteDeletedCard($card);
        });

        return true;
    }

    public function deletedCardsRestoreList(User $user): array
    {
        $limit = $this->restorableDeletedCardsLimit();
        if ($limit < 1) {
            return [];
        }

        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $cards = MaterialCard::onlyTrashed()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('deleted_at')
            ->limit($limit)
            ->get();

        if ($cards->isEmpty()) {
            return [];
        }

        $counts = MaterialCardAttachment::onlyTrashed()
            ->whereIn('material_card_id', $cards->pluck('id')->all())
            ->selectRaw('material_card_id, COUNT(*) as aggregate_count')
            ->groupBy('material_card_id')
            ->pluck('aggregate_count', 'material_card_id');

        return $cards->map(function (MaterialCard $card) use ($counts) {
            return [
                'id' => (int) $card->id,
                'title' => trim((string) ($card->title ?? '')),
                'attachments_count' => (int) ($counts[(int) $card->id] ?? 0),
                'deleted_at' => $card->deleted_at?->toDateTimeString(),
            ];
        })->values()->all();
    }

    private function storeDeletedClassificationSnapshot(MaterialCard $card): void
    {
        if (! $this->supportsDeletedClassificationSnapshots()) {
            return;
        }

        $rows = $this->classificationSnapshotRowsFromCard($card);

        MaterialCardDeletedClassification::query()
            ->where('material_card_id', $card->id)
            ->delete();

        if ($rows === []) {
            return;
        }

        $first = $rows[0];
        $card->update([
            'subject' => $first['subject'],
            'area' => $first['topic'] !== '' ? $first['topic'] : null,
            'unit' => $first['unit'] !== '' ? $first['unit'] : null,
        ]);

        $now = now();
        $payload = array_map(function (array $row) use ($card, $now): array {
            return [
                'material_card_id' => (int) $card->id,
                'subject_name' => $row['subject'],
                'topic_name' => $row['topic'] !== '' ? $row['topic'] : null,
                'unit_name' => $row['unit'] !== '' ? $row['unit'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows);

        MaterialCardDeletedClassification::query()->insert($payload);
    }

    private function classificationSnapshotRowsFromCard(MaterialCard $card): array
    {
        $rows = [];
        $seen = [];

        foreach ($card->classifications as $classification) {
            $subject = $this->normalizeName($classification->subject?->name);
            $topic = $this->normalizeName($classification->topic?->name);
            $unit = $this->normalizeName($classification->unit?->name);

            if ($subject === '') {
                continue;
            }

            if ($topic === '') {
                $unit = '';
            }

            $key = mb_strtolower($subject.'|'.$topic.'|'.$unit);
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

        if ($rows !== []) {
            return $rows;
        }

        $subject = $this->normalizeName($card->subject);
        if ($subject === '') {
            return [];
        }

        $topic = $this->normalizeName($card->area);
        $unit = $this->normalizeName($card->unit);
        if ($topic === '') {
            $unit = '';
        }

        return [[
            'subject' => $subject,
            'topic' => $topic,
            'unit' => $unit,
        ]];
    }

    private function restoreClassificationPathForRestoredCard(MaterialCard $card, User $user): void
    {
        if (! $this->supportsClassificationTables()) {
            return;
        }

        $snapshotRows = $this->deletedClassificationSnapshotRowsForCard($card);
        if ($snapshotRows !== []) {
            $this->replaceCardClassificationsFromRows($card, $user, $snapshotRows);

            MaterialCardDeletedClassification::query()
                ->where('material_card_id', $card->id)
                ->delete();

            return;
        }

        $hasClassifications = MaterialCardClassification::query()
            ->where('material_card_id', $card->id)
            ->exists();

        if ($hasClassifications) {
            return;
        }

        $subject = $this->normalizeName($card->subject);
        if ($subject === '') {
            return;
        }

        $topic = $this->normalizeName($card->area);
        $unit = $this->normalizeName($card->unit);
        if ($topic === '') {
            $unit = '';
        }

        $this->replaceCardClassificationsFromRows($card, $user, [[
            'subject' => $subject,
            'topic' => $topic,
            'unit' => $unit,
        ]]);
    }

    private function deletedClassificationSnapshotRowsForCard(MaterialCard $card): array
    {
        if (! $this->supportsDeletedClassificationSnapshots()) {
            return [];
        }

        $rows = [];
        $seen = [];

        $snapshots = MaterialCardDeletedClassification::query()
            ->where('material_card_id', $card->id)
            ->orderBy('id')
            ->get();

        foreach ($snapshots as $snapshot) {
            $subject = $this->normalizeName($snapshot->subject_name);
            $topic = $this->normalizeName($snapshot->topic_name);
            $unit = $this->normalizeName($snapshot->unit_name);

            if ($subject === '') {
                continue;
            }

            if ($topic === '') {
                $unit = '';
            }

            $key = mb_strtolower($subject.'|'.$topic.'|'.$unit);
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

    private function replaceCardClassificationsFromRows(MaterialCard $card, User $user, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $card->classifications()->delete();

        foreach ($rows as $row) {
            $subject = $this->firstOrCreateSubject($user, $row['subject']);

            $topic = null;
            $unit = null;
            if ($row['topic'] !== '') {
                $topic = $this->firstOrCreateTopic($subject, $row['topic']);

                if ($row['unit'] !== '') {
                    $unit = $this->firstOrCreateUnit($topic, $row['unit']);
                }
            }

            $card->classifications()->create([
                'subject_id' => $subject->id,
                'topic_id' => $topic?->id,
                'unit_id' => $unit?->id,
            ]);
        }

        $first = $rows[0];
        $card->update([
            'subject' => $first['subject'],
            'area' => $first['topic'] !== '' ? $first['topic'] : null,
            'unit' => $first['unit'] !== '' ? $first['unit'] : null,
        ]);
    }

    public function addFileAttachment(MaterialCard $card, UploadedFile $file, ?string $name = null): MaterialCardAttachment
    {
        $detectedMimeType = $this->normalizeMimeType((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $originalFileName = (string) $file->getClientOriginalName();

        $path = $file->storeAs(
            $this->materialAttachmentDirectory($card),
            $this->materialAttachmentStoredFileNameFromOriginalName($originalFileName),
            config('filesystems.default')
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'Datei konnte nicht gespeichert werden.',
            ]);
        }

        $displayName = $this->normalizeOptionalName($name);
        if ($displayName === null) {
            $displayName = mb_substr($originalFileName, 0, 255);
        }

        if ($this->isHtmlAttachmentFile($detectedMimeType, $originalFileName)) {
            $displayName = $this->ensureHtmlAttachmentNameExtension($displayName);
        }

        $attachment = $card->attachments()->create([
            'attachment_type' => MaterialCardAttachment::TYPE_FILE,
            'name' => $displayName,
            'file_path' => $path,
            'mime_type' => $detectedMimeType,
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
            .'/'.$this->materialAttachmentStoredFileNameFromOriginalName($tempFileName);

        $stream = Storage::disk('local')->readStream($tempPath);
        $moved = $stream !== false && Storage::put($destinationPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        Storage::disk('local')->delete($tempPath);

        if (! $moved) {
            throw ValidationException::withMessages([
                'data.upload_id' => 'Upload konnte nicht übernommen werden.',
            ]);
        }

        $displayName = $this->normalizeOptionalName($name);
        if ($displayName === null) {
            $displayName = $this->defaultAttachmentNameFromTempFileName($tempFileName);
        }

        $mimeType = Storage::mimeType($destinationPath);
        $normalizedMimeType = $this->normalizeMimeType((string) ($mimeType ?? ''));
        if ($this->isHtmlAttachmentFile($normalizedMimeType, $tempFileName)) {
            $displayName = $this->ensureHtmlAttachmentNameExtension($displayName);
        }
        $finalSizeBytes = (int) (Storage::size($destinationPath) ?: $sizeBytes);

        $attachment = $card->attachments()->create([
            'attachment_type' => MaterialCardAttachment::TYPE_FILE,
            'name' => $displayName,
            'file_path' => $destinationPath,
            'mime_type' => $normalizedMimeType !== '' ? $normalizedMimeType : null,
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
        return 'app/private/'.$this->tempUploadDirectoryForUser($user);
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

    public function addImageAttachmentFromUrl(MaterialCard $card, string $url, ?string $name = null): MaterialCardAttachment
    {
        $normalizedUrl = $this->normalizeRemoteImageUrl($url);
        if ($normalizedUrl === '') {
            throw ValidationException::withMessages([
                'data.url' => 'Ungültige Bild-URL.',
            ]);
        }

        $tempFilePath = tempnam(sys_get_temp_dir(), 'material-image-');
        if (! is_string($tempFilePath) || $tempFilePath === '') {
            throw ValidationException::withMessages([
                'data.url' => 'Temporäre Datei konnte nicht erstellt werden.',
            ]);
        }

        try {
            try {
                $response = Http::connectTimeout(10)
                    ->timeout(30)
                    ->withOptions([
                        'allow_redirects' => true,
                        'sink' => $tempFilePath,
                    ])
                    ->get($normalizedUrl);
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'data.url' => 'Bild konnte nicht geladen werden.',
                ]);
            }

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'data.url' => 'Bild konnte nicht geladen werden.',
                ]);
            }

            $sizeBytes = (int) (filesize($tempFilePath) ?: 0);
            if ($sizeBytes <= 0) {
                throw ValidationException::withMessages([
                    'data.url' => 'Bild konnte nicht verarbeitet werden.',
                ]);
            }

            $maxBytes = $this->maxUploadSizeForSchool((int) $card->school_id) * 1024;
            if ($maxBytes > 0 && $sizeBytes > $maxBytes) {
                throw ValidationException::withMessages([
                    'data.url' => 'Bild überschreitet die maximal erlaubte Uploadgröße.',
                ]);
            }

            $headerMimeType = $this->normalizeMimeType($response->header('Content-Type'));
            $detectedMimeType = $this->detectedMimeTypeForPath($tempFilePath);

            $mimeType = null;
            if ($this->isImageMimeType($headerMimeType)) {
                $mimeType = $headerMimeType;
            } elseif ($this->isImageMimeType($detectedMimeType)) {
                $mimeType = $detectedMimeType;
            } else {
                $mimeType = $this->imageMimeTypeFromExtension($this->extensionFromUrl($normalizedUrl));
            }

            if (! $this->isImageMimeType($mimeType)) {
                throw ValidationException::withMessages([
                    'data.url' => 'URL verweist nicht auf ein Bild.',
                ]);
            }

            $originalName = $this->remoteImageOriginalName($normalizedUrl, $mimeType);
            $destinationPath = $this->materialAttachmentDirectory($card)
                .'/'.$this->materialAttachmentStoredFileNameFromOriginalName($originalName);

            $stream = fopen($tempFilePath, 'rb');
            if ($stream === false) {
                throw ValidationException::withMessages([
                    'data.url' => 'Bild konnte nicht gespeichert werden.',
                ]);
            }

            try {
                $stored = Storage::put($destinationPath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw ValidationException::withMessages([
                    'data.url' => 'Bild konnte nicht gespeichert werden.',
                ]);
            }

            $displayName = $this->normalizeOptionalName($name);
            if ($displayName === null) {
                $displayName = mb_substr($originalName, 0, 255);
            }

            $storedSizeBytes = (int) (Storage::size($destinationPath) ?: $sizeBytes);

            $attachment = $card->attachments()->create([
                'attachment_type' => MaterialCardAttachment::TYPE_FILE,
                'name' => $displayName,
                'source_url' => $normalizedUrl,
                'file_path' => $destinationPath,
                'mime_type' => $mimeType,
                'size_bytes' => $storedSizeBytes > 0 ? $storedSizeBytes : null,
                'downloaded_at' => now(),
            ]);

            $this->keywordService->rebuild($card->fresh($this->cardRelations()));

            return $attachment;
        } finally {
            @unlink($tempFilePath);
        }
    }

    public function deleteAttachment(MaterialCardAttachment $attachment): void
    {
        $card = $attachment->materialCard()->first();

        if ($attachment->attachment_type === MaterialCardAttachment::TYPE_FILE && $attachment->file_path) {
            Storage::delete($attachment->file_path);
        }

        $attachment->forceDelete();

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

    public function readEditableTextAttachmentContent(MaterialCardAttachment $attachment): string
    {
        $this->assertEditableTextAttachment($attachment);

        return (string) Storage::disk('local')->get($attachment->file_path);
    }

    public function updateEditableTextAttachmentContent(
        MaterialCardAttachment $attachment,
        string $contentHtml,
        ?string $name = null
    ): MaterialCardAttachment {
        $this->assertEditableTextAttachment($attachment);

        $normalizedHtml = trim($contentHtml);
        if ($normalizedHtml === '') {
            throw ValidationException::withMessages([
                'data.content_html' => 'Bitte einen Textinhalt angeben.',
            ]);
        }

        $stored = Storage::put($attachment->file_path, $normalizedHtml);
        if (! $stored) {
            throw ValidationException::withMessages([
                'data.content_html' => 'Text konnte nicht gespeichert werden.',
            ]);
        }

        $sizeBytes = (int) (Storage::size($attachment->file_path) ?: strlen($normalizedHtml));
        $normalizedName = $this->normalizeOptionalName($name ?? $attachment->name ?? null);
        $normalizedName = $this->ensureHtmlAttachmentNameExtension($normalizedName);

        $attachment->update([
            'name' => $normalizedName,
            'mime_type' => 'text/html',
            'size_bytes' => $sizeBytes > 0 ? $sizeBytes : null,
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
                'color' => $this->hasTypeColorColumn()
                    ? $this->normalizeTypeColor($type->color ?? null)
                    : null,
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
            'color' => $this->hasStatusColorColumn()
                ? $this->normalizeStatusColor($status->color ?? null)
                : null,
        ])->values()->all();
    }

    public function createType(User $user, string $name, ?string $icon = null, ?string $color = null): MaterialType
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

        if ($this->hasTypeColorColumn()) {
            $attributes['color'] = $this->normalizeTypeColor($color);
        }

        return MaterialType::firstOrCreate($attributes);
    }

    public function updateType(User $user, MaterialType $type, string $name, ?string $icon = null, ?string $color = null): MaterialType
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
        $newColor = $this->normalizeTypeColor($color);

        $isUserScoped = $this->isUserScopedMaterialTypes();

        DB::transaction(function () use ($type, $newName, $oldName, $newIcon, $newColor, $user, $isUserScoped) {
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
            if ($this->hasTypeColorColumn()) {
                $updateData['color'] = $newColor;
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

    public function createStatus(User $user, string $label, ?string $color = null): MaterialStatus
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

        $attributes = [
            'school_id' => $user->school_id,
            'value' => $value,
            'label' => $normalizedLabel,
        ];

        if ($this->hasStatusColorColumn()) {
            $attributes['color'] = $this->normalizeStatusColor($color);
        }

        return MaterialStatus::query()->create($attributes);
    }

    public function updateStatus(User $user, MaterialStatus $status, string $label, ?string $color = null): MaterialStatus
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

        $updateData = [
            'label' => $normalizedLabel,
        ];
        if ($this->hasStatusColorColumn()) {
            $updateData['color'] = $this->normalizeStatusColor($color);
        }

        $status->update($updateData);

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

    public function userSettingsForUser(User $user): array
    {
        return [
            'materials_pagination_number' => $this->materialsPaginationNumberForUser($user),
        ];
    }

    public function updateUserSettings(User $user, int $materialsPaginationNumber): array
    {
        $normalized = max(1, min(200, (int) $materialsPaginationNumber));

        if (! $this->supportsUserMaterialsPaginationSettings()) {
            throw ValidationException::withMessages([
                'data.materials_pagination_number' => 'Benutzereinstellungen sind noch nicht verfügbar. Bitte Migration ausführen.',
            ]);
        }

        $user->materials_pagination_number = $normalized;
        $user->save();

        return $this->userSettingsForUser($user->fresh());
    }

    public function createSubject(User $user, string $name): MaterialSubject
    {
        if (! $this->supportsClassificationTables()) {
            throw ValidationException::withMessages([
                'data.name' => 'Fachstruktur ist noch nicht verfügbar.',
            ]);
        }

        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen gültigen Fachnamen angeben.',
            ]);
        }

        return $this->firstOrCreateSubject($user, $normalized);
    }

    public function updateSubject(User $user, MaterialSubject $subject, string $name): MaterialSubject
    {
        $this->assertSubjectBelongsToUser($user, $subject);
        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen gültigen Fachnamen angeben.',
            ]);
        }

        $alreadyExists = MaterialSubject::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->where('name', $normalized)
            ->where('id', '<>', $subject->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'data.name' => 'Dieses Fach existiert bereits.',
            ]);
        }

        $subject->update([
            'name' => $normalized,
        ]);

        return $subject->fresh();
    }

    public function deleteSubject(User $user, MaterialSubject $subject): void
    {
        $this->assertSubjectBelongsToUser($user, $subject);
        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $topicIds = MaterialTopic::query()
            ->where('subject_id', $subject->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $unitIds = $topicIds->isEmpty()
            ? collect()
            : MaterialUnit::query()
                ->whereIn('topic_id', $topicIds->all())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

        $inUse = (! $topicIds->isEmpty() || ! $unitIds->isEmpty()) && MaterialCardClassification::query()
            ->whereHas('materialCard', fn ($query) => $query->where('workspace_id', $workspaceId))
            ->where(function ($query) use ($topicIds, $unitIds) {
                if (! $topicIds->isEmpty()) {
                    $query->whereIn('topic_id', $topicIds->all());
                }

                if (! $unitIds->isEmpty()) {
                    $method = $topicIds->isEmpty() ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('unit_id', $unitIds->all());
                }
            })
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'data.name' => 'Fach kann nicht gelöscht werden, solange es in Materialien verwendet wird.',
            ]);
        }

        $subject->delete();
    }

    public function moveSubject(User $user, MaterialSubject $subject, string $direction): bool
    {
        $this->assertSubjectBelongsToUser($user, $subject);
        $workspaceId = $this->activeWorkspaceIdForUser($user);

        if (! $this->supportsClassificationSortOrder()) {
            throw ValidationException::withMessages([
                'data.direction' => 'Sortierung der Fachstruktur ist noch nicht verfügbar.',
            ]);
        }

        $normalizedDirection = $this->normalizeMoveDirection($direction);
        $orderedIds = MaterialSubject::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $reorderedIds = $this->moveWithinOrderedIds($orderedIds, (int) $subject->id, $normalizedDirection);
        if ($reorderedIds === $orderedIds) {
            return false;
        }

        DB::transaction(function () use ($reorderedIds) {
            $this->persistSubjectSortOrder($reorderedIds);
        });

        return true;
    }

    public function createTopic(User $user, MaterialSubject $subject, string $name, bool $allowDuplicate = false): MaterialTopic
    {
        if (! $this->supportsClassificationTables()) {
            throw ValidationException::withMessages([
                'data.name' => 'Fachstruktur ist noch nicht verfügbar.',
            ]);
        }

        $this->assertSubjectBelongsToUser($user, $subject);

        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen gültigen Themennamen angeben.',
            ]);
        }

        if ($allowDuplicate) {
            $existingTopic = MaterialTopic::query()
                ->where('subject_id', (int) $subject->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized)])
                ->first();
            if ($existingTopic instanceof MaterialTopic) {
                return $existingTopic->fresh();
            }

            $data = [
                'subject_id' => (int) $subject->id,
                'name' => $normalized,
            ];
            if ($this->supportsClassificationSortOrder()) {
                $data['sort_order'] = $this->nextTopicSortOrder((int) $subject->id);
            }

            return MaterialTopic::query()->create($data)->fresh();
        }

        return $this->firstOrCreateTopic($subject, $normalized);
    }

    public function updateTopic(User $user, MaterialTopic $topic, string $name): MaterialTopic
    {
        $this->assertTopicBelongsToUser($user, $topic);

        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen gültigen Themennamen angeben.',
            ]);
        }

        $alreadyExists = MaterialTopic::query()
            ->where('subject_id', $topic->subject_id)
            ->where('name', $normalized)
            ->where('id', '<>', $topic->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'data.name' => 'Dieses Thema existiert bereits.',
            ]);
        }

        $topic->update([
            'name' => $normalized,
        ]);

        return $topic->fresh();
    }

    public function deleteTopic(User $user, MaterialTopic $topic): void
    {
        $this->assertTopicBelongsToUser($user, $topic);
        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $unitIds = MaterialUnit::query()
            ->where('topic_id', $topic->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $inUse = ! $unitIds->isEmpty() && MaterialCardClassification::query()
            ->whereHas('materialCard', fn ($query) => $query->where('workspace_id', $workspaceId))
            ->whereIn('unit_id', $unitIds->all())
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'data.name' => 'Thema kann nicht gelöscht werden, solange es in Materialien verwendet wird.',
            ]);
        }

        $topic->delete();
    }

    public function moveTopic(User $user, MaterialTopic $topic, string $direction): bool
    {
        $this->assertTopicBelongsToUser($user, $topic);

        if (! $this->supportsClassificationSortOrder()) {
            throw ValidationException::withMessages([
                'data.direction' => 'Sortierung der Fachstruktur ist noch nicht verfügbar.',
            ]);
        }

        $normalizedDirection = $this->normalizeMoveDirection($direction);
        $orderedIds = MaterialTopic::query()
            ->where('subject_id', $topic->subject_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $reorderedIds = $this->moveWithinOrderedIds($orderedIds, (int) $topic->id, $normalizedDirection);
        if ($reorderedIds === $orderedIds) {
            return false;
        }

        DB::transaction(function () use ($reorderedIds) {
            $this->persistTopicSortOrder($reorderedIds);
        });

        return true;
    }

    public function createUnit(User $user, MaterialTopic $topic, string $name, bool $allowDuplicate = false): MaterialUnit
    {
        if (! $this->supportsClassificationTables()) {
            throw ValidationException::withMessages([
                'data.name' => 'Fachstruktur ist noch nicht verfügbar.',
            ]);
        }

        $this->assertTopicBelongsToUser($user, $topic);

        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen gültigen Bereichsnamen angeben.',
            ]);
        }

        if ($allowDuplicate) {
            $data = [
                'topic_id' => (int) $topic->id,
                'name' => $normalized,
            ];
            if ($this->supportsClassificationSortOrder()) {
                $data['sort_order'] = $this->nextUnitSortOrder((int) $topic->id);
            }

            return MaterialUnit::query()->create($data)->fresh();
        }

        return $this->firstOrCreateUnit($topic, $normalized);
    }

    public function updateUnit(User $user, MaterialUnit $unit, string $name): MaterialUnit
    {
        $this->assertUnitBelongsToUser($user, $unit);

        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'data.name' => 'Bitte einen gültigen Bereichsnamen angeben.',
            ]);
        }

        $alreadyExists = MaterialUnit::query()
            ->where('topic_id', $unit->topic_id)
            ->where('name', $normalized)
            ->where('id', '<>', $unit->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'data.name' => 'Dieser Bereich existiert bereits.',
            ]);
        }

        $unit->update([
            'name' => $normalized,
        ]);

        return $unit->fresh();
    }

    public function deleteUnit(User $user, MaterialUnit $unit): void
    {
        $this->assertUnitBelongsToUser($user, $unit);
        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $inUse = MaterialCardClassification::query()
            ->whereHas('materialCard', fn ($query) => $query->where('workspace_id', $workspaceId))
            ->where('unit_id', $unit->id)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'data.name' => 'Einheit ist in Materialien verwendet und kann nicht gelöscht werden.',
            ]);
        }

        $unit->delete();
    }

    public function moveUnit(User $user, MaterialUnit $unit, string $direction): bool
    {
        $this->assertUnitBelongsToUser($user, $unit);

        if (! $this->supportsClassificationSortOrder()) {
            throw ValidationException::withMessages([
                'data.direction' => 'Sortierung der Fachstruktur ist noch nicht verfügbar.',
            ]);
        }

        $normalizedDirection = $this->normalizeMoveDirection($direction);
        $orderedIds = MaterialUnit::query()
            ->where('topic_id', $unit->topic_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $reorderedIds = $this->moveWithinOrderedIds($orderedIds, (int) $unit->id, $normalizedDirection);
        if ($reorderedIds === $orderedIds) {
            return false;
        }

        DB::transaction(function () use ($reorderedIds) {
            $this->persistUnitSortOrder($reorderedIds);
        });

        return true;
    }

    public function convertSubjectToTopic(User $user, MaterialSubject $subject, MaterialSubject $targetSubject): MaterialTopic
    {
        $this->assertSubjectBelongsToUser($user, $subject);
        $this->assertSubjectBelongsToUser($user, $targetSubject);

        if ((int) $subject->id === (int) $targetSubject->id) {
            throw ValidationException::withMessages([
                'data.target_subject_id' => 'Bitte ein anderes Zielfach auswählen.',
            ]);
        }

        $hasTopics = MaterialTopic::query()
            ->where('subject_id', $subject->id)
            ->exists();

        if ($hasTopics) {
            throw ValidationException::withMessages([
                'data.target_subject_id' => 'Fach enthält noch Themen und kann nicht direkt als Thema verschoben werden.',
            ]);
        }

        return DB::transaction(function () use ($subject, $targetSubject) {
            $duplicate = MaterialTopic::query()
                ->where('subject_id', $targetSubject->id)
                ->where('name', $subject->name)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'data.target_subject_id' => 'Im Zielfach existiert bereits ein Thema mit diesem Namen.',
                ]);
            }

            $topicData = [
                'subject_id' => $targetSubject->id,
                'name' => $subject->name,
            ];
            if ($this->supportsClassificationSortOrder()) {
                $topicData['sort_order'] = $this->nextTopicSortOrder((int) $targetSubject->id);
            }

            $newTopic = MaterialTopic::query()->create($topicData);

            MaterialCardClassification::query()
                ->where('subject_id', $subject->id)
                ->update([
                    'subject_id' => $targetSubject->id,
                    'topic_id' => $newTopic->id,
                    'unit_id' => null,
                ]);

            $subject->delete();

            return $newTopic->fresh();
        });
    }

    public function moveTopicToSubject(User $user, MaterialTopic $topic, MaterialSubject $targetSubject): MaterialTopic
    {
        $this->assertTopicBelongsToUser($user, $topic);
        $this->assertSubjectBelongsToUser($user, $targetSubject);

        if ((int) $topic->subject_id === (int) $targetSubject->id) {
            throw ValidationException::withMessages([
                'data.target_subject_id' => 'Thema ist bereits in diesem Fach.',
            ]);
        }

        return DB::transaction(function () use ($topic, $targetSubject) {
            $duplicate = MaterialTopic::query()
                ->where('subject_id', $targetSubject->id)
                ->where('name', $topic->name)
                ->where('id', '<>', $topic->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'data.target_subject_id' => 'Im Zielfach existiert bereits ein Thema mit diesem Namen.',
                ]);
            }

            $updateData = [
                'subject_id' => $targetSubject->id,
            ];
            if ($this->supportsClassificationSortOrder()) {
                $updateData['sort_order'] = $this->nextTopicSortOrder((int) $targetSubject->id);
            }

            $topic->update($updateData);

            MaterialCardClassification::query()
                ->where('topic_id', $topic->id)
                ->update([
                    'subject_id' => $targetSubject->id,
                ]);

            return $topic->fresh();
        });
    }

    public function convertTopicToSubject(User $user, MaterialTopic $topic, string $newSubjectName): MaterialSubject
    {
        $this->assertTopicBelongsToUser($user, $topic);
        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $normalizedName = $this->normalizeName($newSubjectName);
        if ($normalizedName === '') {
            throw ValidationException::withMessages([
                'data.new_subject_name' => 'Bitte einen gültigen Fachnamen angeben.',
            ]);
        }

        $subjectExists = MaterialSubject::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->where('name', $normalizedName)
            ->exists();

        if ($subjectExists) {
            throw ValidationException::withMessages([
                'data.new_subject_name' => 'Dieses Fach existiert bereits.',
            ]);
        }

        return DB::transaction(function () use ($user, $topic, $normalizedName) {
            $newSubjectData = [
                'user_id' => $user->id,
                'workspace_id' => $this->activeWorkspaceIdForUser($user),
                'name' => $normalizedName,
            ];
            if ($this->supportsClassificationSortOrder()) {
                $newSubjectData['sort_order'] = $this->nextSubjectSortOrder($user);
            }

            $newSubject = MaterialSubject::query()->create($newSubjectData);

            $unitsQuery = MaterialUnit::query()
                ->where('topic_id', $topic->id);
            if ($this->supportsClassificationSortOrder()) {
                $unitsQuery
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->orderBy('id');
            } else {
                $unitsQuery
                    ->orderBy('name')
                    ->orderBy('id');
            }

            $units = $unitsQuery->get();
            $unitToTopicMap = [];
            foreach ($units as $unit) {
                $newTopicData = [
                    'subject_id' => $newSubject->id,
                    'name' => $unit->name,
                ];
                if ($this->supportsClassificationSortOrder()) {
                    $newTopicData['sort_order'] = $this->nextTopicSortOrder((int) $newSubject->id);
                }

                $newTopic = MaterialTopic::query()->create($newTopicData);
                $unitToTopicMap[(int) $unit->id] = (int) $newTopic->id;
            }

            MaterialCardClassification::query()
                ->where('topic_id', $topic->id)
                ->whereNull('unit_id')
                ->update([
                    'subject_id' => $newSubject->id,
                    'topic_id' => null,
                    'unit_id' => null,
                ]);

            foreach ($unitToTopicMap as $oldUnitId => $newTopicId) {
                MaterialCardClassification::query()
                    ->where('unit_id', $oldUnitId)
                    ->update([
                        'subject_id' => $newSubject->id,
                        'topic_id' => $newTopicId,
                        'unit_id' => null,
                    ]);
            }

            if (count($unitToTopicMap) > 0) {
                MaterialUnit::query()->whereIn('id', array_keys($unitToTopicMap))->delete();
            }

            $topic->delete();

            return $newSubject->fresh();
        });
    }

    public function convertTopicToUnit(User $user, MaterialTopic $topic, MaterialTopic $targetTopic): MaterialUnit
    {
        $this->assertTopicBelongsToUser($user, $topic);
        $this->assertTopicBelongsToUser($user, $targetTopic);

        if ((int) $topic->id === (int) $targetTopic->id) {
            throw ValidationException::withMessages([
                'data.target_topic_id' => 'Bitte ein anderes Zielthema auswählen.',
            ]);
        }

        $hasUnits = MaterialUnit::query()
            ->where('topic_id', $topic->id)
            ->exists();

        if ($hasUnits) {
            throw ValidationException::withMessages([
                'data.target_topic_id' => 'Thema enthält noch Einheiten und kann nicht direkt als Einheit verschoben werden.',
            ]);
        }

        $targetTopic->loadMissing('subject');
        $targetSubjectId = (int) ($targetTopic->subject_id ?: 0);

        return DB::transaction(function () use ($topic, $targetTopic, $targetSubjectId) {
            $duplicate = MaterialUnit::query()
                ->where('topic_id', $targetTopic->id)
                ->where('name', $topic->name)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'data.target_topic_id' => 'Im Zielthema existiert bereits eine Einheit mit diesem Namen.',
                ]);
            }

            $newUnitData = [
                'topic_id' => $targetTopic->id,
                'name' => $topic->name,
            ];
            if ($this->supportsClassificationSortOrder()) {
                $newUnitData['sort_order'] = $this->nextUnitSortOrder((int) $targetTopic->id);
            }

            $newUnit = MaterialUnit::query()->create($newUnitData);

            MaterialCardClassification::query()
                ->where('topic_id', $topic->id)
                ->update([
                    'subject_id' => $targetSubjectId,
                    'topic_id' => $targetTopic->id,
                    'unit_id' => $newUnit->id,
                ]);

            $topic->delete();

            return $newUnit->fresh();
        });
    }

    public function moveUnitToTopic(User $user, MaterialUnit $unit, MaterialTopic $targetTopic): MaterialUnit
    {
        $this->assertUnitBelongsToUser($user, $unit);
        $this->assertTopicBelongsToUser($user, $targetTopic);

        if ((int) $unit->topic_id === (int) $targetTopic->id) {
            throw ValidationException::withMessages([
                'data.target_topic_id' => 'Einheit ist bereits in diesem Thema.',
            ]);
        }

        $targetTopic->loadMissing('subject');
        $targetSubjectId = (int) ($targetTopic->subject_id ?: 0);

        return DB::transaction(function () use ($unit, $targetTopic, $targetSubjectId) {
            $duplicate = MaterialUnit::query()
                ->where('topic_id', $targetTopic->id)
                ->where('name', $unit->name)
                ->where('id', '<>', $unit->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'data.target_topic_id' => 'Im Zielthema existiert bereits eine Einheit mit diesem Namen.',
                ]);
            }

            $updateData = [
                'topic_id' => $targetTopic->id,
            ];
            if ($this->supportsClassificationSortOrder()) {
                $updateData['sort_order'] = $this->nextUnitSortOrder((int) $targetTopic->id);
            }
            $unit->update($updateData);

            MaterialCardClassification::query()
                ->where('unit_id', $unit->id)
                ->update([
                    'subject_id' => $targetSubjectId,
                    'topic_id' => $targetTopic->id,
                ]);

            return $unit->fresh();
        });
    }

    public function convertUnitToTopic(
        User $user,
        MaterialUnit $unit,
        MaterialSubject $targetSubject,
        string $newTopicName
    ): MaterialTopic {
        $this->assertUnitBelongsToUser($user, $unit);
        $this->assertSubjectBelongsToUser($user, $targetSubject);

        $normalizedName = $this->normalizeName($newTopicName);
        if ($normalizedName === '') {
            throw ValidationException::withMessages([
                'data.new_topic_name' => 'Bitte einen gültigen Themennamen angeben.',
            ]);
        }

        $topicExists = MaterialTopic::query()
            ->where('subject_id', $targetSubject->id)
            ->where('name', $normalizedName)
            ->exists();

        if ($topicExists) {
            throw ValidationException::withMessages([
                'data.new_topic_name' => 'Dieses Thema existiert im Zielfach bereits.',
            ]);
        }

        return DB::transaction(function () use ($unit, $targetSubject, $normalizedName) {
            $newTopicData = [
                'subject_id' => $targetSubject->id,
                'name' => $normalizedName,
            ];
            if ($this->supportsClassificationSortOrder()) {
                $newTopicData['sort_order'] = $this->nextTopicSortOrder((int) $targetSubject->id);
            }

            $newTopic = MaterialTopic::query()->create($newTopicData);

            MaterialCardClassification::query()
                ->where('unit_id', $unit->id)
                ->update([
                    'subject_id' => $targetSubject->id,
                    'topic_id' => $newTopic->id,
                    'unit_id' => null,
                ]);

            $unit->delete();

            return $newTopic->fresh();
        });
    }

    private function classificationTreeForUser(User $user): array
    {
        if (! $this->supportsClassificationTables()) {
            return [];
        }

        $workspaceId = $this->activeWorkspaceIdForUser($user);

        $subjectsQuery = MaterialSubject::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->with(['topics.units']);

        if ($this->supportsClassificationSortOrder()) {
            $subjectsQuery
                ->orderBy('sort_order')
                ->orderBy('name')
                ->orderBy('id');
        } else {
            $subjectsQuery
                ->orderBy('name')
                ->orderBy('id');
        }

        $subjects = $subjectsQuery->get();

        $subjectIds = $subjects->pluck('id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values();
        $topicIds = $subjects
            ->flatMap(fn (MaterialSubject $subject) => $subject->topics->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();
        $unitIds = $subjects
            ->flatMap(fn (MaterialSubject $subject) => $subject->topics->flatMap(fn (MaterialTopic $topic) => $topic->units->pluck('id')))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $usageRows = $subjectIds->isEmpty() && $topicIds->isEmpty() && $unitIds->isEmpty()
            ? collect()
            : MaterialCardClassification::query()
                ->whereHas('materialCard', fn ($query) => $query->where('workspace_id', $workspaceId))
                ->where(function ($query) use ($subjectIds, $topicIds, $unitIds) {
                    if (! $subjectIds->isEmpty()) {
                        $query->whereIn('subject_id', $subjectIds->all());
                    }

                    if (! $topicIds->isEmpty()) {
                        $method = $subjectIds->isEmpty() ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('topic_id', $topicIds->all());
                    }

                    if (! $unitIds->isEmpty()) {
                        $method = $subjectIds->isEmpty() && $topicIds->isEmpty() ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('unit_id', $unitIds->all());
                    }
                })
                ->select(['subject_id', 'topic_id', 'unit_id'])
                ->distinct()
                ->get();

        $usedSubjectIds = [];
        $usedTopicIds = [];
        $usedUnitIds = [];
        foreach ($usageRows as $row) {
            $subjectId = (int) ($row->subject_id ?? 0);
            $topicId = (int) ($row->topic_id ?? 0);
            $unitId = (int) ($row->unit_id ?? 0);

            if ($subjectId > 0) {
                $usedSubjectIds[$subjectId] = true;
            }
            if ($topicId > 0) {
                $usedTopicIds[$topicId] = true;
            }
            if ($unitId > 0) {
                $usedUnitIds[$unitId] = true;
            }
        }

        $linkedTopicMetaMap = $this->linkedTopicMetaMapForUser($user, $topicIds->all());
        $linkedUnitMetaMap = $this->linkedUnitMetaMapForUser($user, $unitIds->all());

        return $subjects->map(function (MaterialSubject $subject) use ($usedTopicIds, $usedUnitIds, $linkedTopicMetaMap, $linkedUnitMetaMap) {
            $subjectTopicIds = $subject->topics
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0);

            $subjectUnitIds = $subject->topics
                ->flatMap(fn (MaterialTopic $topic) => $topic->units->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0);

            $subjectHasUsage = $subjectTopicIds->contains(fn ($id) => isset($usedTopicIds[$id]))
                || $subjectUnitIds->contains(fn ($id) => isset($usedUnitIds[$id]));

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'can_delete' => ! $subjectHasUsage,
                'topics' => $subject->topics->map(function (MaterialTopic $topic) use ($usedUnitIds, $linkedTopicMetaMap, $linkedUnitMetaMap) {
                    $topicUnitIds = $topic->units
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn ($id) => $id > 0);

                    $topicHasUsage = $topicUnitIds->contains(fn ($id) => isset($usedUnitIds[$id]));
                    $topicId = (int) $topic->id;
                    $linkedTopicMeta = $linkedTopicMetaMap[$topicId] ?? null;

                    return [
                        'id' => $topic->id,
                        'name' => $topic->name,
                        'can_delete' => ! $topicHasUsage,
                        'is_linked' => (bool) ($linkedTopicMeta['is_linked'] ?? false),
                        'linked_permission' => $linkedTopicMeta['linked_permission'] ?? null,
                        'linked_permission_label' => $linkedTopicMeta['linked_permission_label'] ?? null,
                        'units' => $topic->units->map(function (MaterialUnit $unit) use ($usedUnitIds, $linkedUnitMetaMap) {
                            $unitId = (int) $unit->id;
                            $linkedMeta = $linkedUnitMetaMap[$unitId] ?? null;

                            return [
                                'id' => $unit->id,
                                'name' => $unit->name,
                                'can_delete' => ! isset($usedUnitIds[$unitId]),
                                'is_linked' => (bool) ($linkedMeta['is_linked'] ?? false),
                                'linked_permission' => $linkedMeta['linked_permission'] ?? null,
                                'linked_permission_label' => $linkedMeta['linked_permission_label'] ?? null,
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<int,int>  $unitIds
     * @return array<int,array{is_linked:bool,linked_permission:?string,linked_permission_label:?string}>
     */
    private function linkedUnitMetaMapForUser(User $user, array $unitIds): array
    {
        $ids = collect($unitIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! $this->supportsMaterialUnitInboxImports()) {
            return [];
        }

        $imports = MaterialUnitInboxImport::query()
            ->where('target_user_id', (int) $user->id)
            ->whereIn('target_unit_id', $ids->all())
            ->get(['target_unit_id', 'source_rule_id']);

        if ($imports->isEmpty()) {
            return [];
        }

        $ruleIds = $imports
            ->pluck('source_rule_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $rulesById = $ruleIds->isEmpty()
            ? collect()
            : MaterialShareRule::query()
                ->whereIn('id', $ruleIds->all())
                ->where('is_active', true)
                ->with(['targets:id,material_share_rule_id,target_type,audience_scope,permission,user_group_id,user_id'])
                ->get()
                ->keyBy('id');

        $memberGroupSet = $this->memberGroupSetForUser($user);
        $schoolId = (int) $user->school_id;
        $userId = (int) $user->id;

        $result = [];
        foreach ($imports as $import) {
            $targetUnitId = (int) ($import->target_unit_id ?? 0);
            if ($targetUnitId <= 0) {
                continue;
            }

            $permission = MaterialShareTarget::PERMISSION_READ_ONLY;
            $sourceRuleId = (int) ($import->source_rule_id ?? 0);
            $rule = $sourceRuleId > 0 ? $rulesById->get($sourceRuleId) : null;
            if ($rule instanceof MaterialShareRule) {
                $permission = $this->resolveLinkedPermissionForUser($rule, $userId, $schoolId, $memberGroupSet);
            }

            $currentRank = $this->linkedPermissionRank($result[$targetUnitId]['linked_permission'] ?? '');
            $nextRank = $this->linkedPermissionRank($permission);
            if (! isset($result[$targetUnitId]) || $nextRank >= $currentRank) {
                $result[$targetUnitId] = [
                    'is_linked' => true,
                    'linked_permission' => $permission,
                    'linked_permission_label' => $this->linkedPermissionLabel($permission),
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<int,int>  $topicIds
     * @return array<int,array{is_linked:bool,linked_permission:?string,linked_permission_label:?string}>
     */
    private function linkedTopicMetaMapForUser(User $user, array $topicIds): array
    {
        $ids = collect($topicIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! $this->supportsMaterialTopicInboxImports()) {
            return [];
        }

        $imports = MaterialTopicInboxImport::query()
            ->where('target_user_id', (int) $user->id)
            ->whereIn('target_topic_id', $ids->all())
            ->get(['target_topic_id', 'source_rule_id']);

        if ($imports->isEmpty()) {
            return [];
        }

        $ruleIds = $imports
            ->pluck('source_rule_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $rulesById = $ruleIds->isEmpty()
            ? collect()
            : MaterialShareRule::query()
                ->whereIn('id', $ruleIds->all())
                ->where('is_active', true)
                ->with(['targets:id,material_share_rule_id,target_type,audience_scope,permission,user_group_id,user_id'])
                ->get()
                ->keyBy('id');

        $memberGroupSet = $this->memberGroupSetForUser($user);
        $schoolId = (int) $user->school_id;
        $userId = (int) $user->id;

        $result = [];
        foreach ($imports as $import) {
            $targetTopicId = (int) ($import->target_topic_id ?? 0);
            if ($targetTopicId <= 0) {
                continue;
            }

            $permission = MaterialShareTarget::PERMISSION_READ_ONLY;
            $sourceRuleId = (int) ($import->source_rule_id ?? 0);
            $rule = $sourceRuleId > 0 ? $rulesById->get($sourceRuleId) : null;
            if ($rule instanceof MaterialShareRule) {
                $permission = $this->resolveLinkedPermissionForUser($rule, $userId, $schoolId, $memberGroupSet);
            }

            $currentRank = $this->linkedPermissionRank($result[$targetTopicId]['linked_permission'] ?? '');
            $nextRank = $this->linkedPermissionRank($permission);
            if (! isset($result[$targetTopicId]) || $nextRank >= $currentRank) {
                $result[$targetTopicId] = [
                    'is_linked' => true,
                    'linked_permission' => $permission,
                    'linked_permission_label' => $this->linkedPermissionLabel($permission),
                ];
            }
        }

        return $result;
    }

    private function syncClassifications(MaterialCard $card, User $user, mixed $input): void
    {
        if (! $this->supportsClassificationTables()) {
            return;
        }

        $rows = $this->normalizeClassifications($input);
        $card->classifications()->delete();

        foreach ($rows as $row) {
            $subject = $this->firstOrCreateSubject($user, $row['subject']);

            $topic = null;
            $unit = null;

            if ($row['topic'] !== '') {
                $topic = $this->firstOrCreateTopic($subject, $row['topic']);

                if ($row['unit'] !== '') {
                    $unit = $this->firstOrCreateUnit($topic, $row['unit']);
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

            $key = mb_strtolower($subject.'|'.$topic.'|'.$unit);
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

    private function firstOrCreateSubject(User $user, string $name): MaterialSubject
    {
        $workspaceId = $this->activeWorkspaceIdForUser($user);
        $attributes = [
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
            'name' => $name,
        ];

        if (! $this->supportsClassificationSortOrder()) {
            return MaterialSubject::query()->firstOrCreate($attributes);
        }

        return MaterialSubject::query()->firstOrCreate(
            $attributes,
            ['sort_order' => $this->nextSubjectSortOrder($user)]
        );
    }

    private function firstOrCreateTopic(MaterialSubject $subject, string $name): MaterialTopic
    {
        $attributes = [
            'subject_id' => $subject->id,
            'name' => $name,
        ];

        if (! $this->supportsClassificationSortOrder()) {
            return MaterialTopic::query()->firstOrCreate($attributes);
        }

        return MaterialTopic::query()->firstOrCreate(
            $attributes,
            ['sort_order' => $this->nextTopicSortOrder((int) $subject->id)]
        );
    }

    private function firstOrCreateUnit(MaterialTopic $topic, string $name): MaterialUnit
    {
        $attributes = [
            'topic_id' => $topic->id,
            'name' => $name,
        ];

        if (! $this->supportsClassificationSortOrder()) {
            return MaterialUnit::query()->firstOrCreate($attributes);
        }

        return MaterialUnit::query()->firstOrCreate(
            $attributes,
            ['sort_order' => $this->nextUnitSortOrder((int) $topic->id)]
        );
    }

    private function nextSubjectSortOrder(User $user): int
    {
        $workspaceId = $this->activeWorkspaceIdForUser($user);
        $max = (int) MaterialSubject::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->max('sort_order');

        return max(0, $max) + 1;
    }

    private function nextTopicSortOrder(int $subjectId): int
    {
        $max = (int) MaterialTopic::query()
            ->where('subject_id', $subjectId)
            ->max('sort_order');

        return max(0, $max) + 1;
    }

    private function nextUnitSortOrder(int $topicId): int
    {
        $max = (int) MaterialUnit::query()
            ->where('topic_id', $topicId)
            ->max('sort_order');

        return max(0, $max) + 1;
    }

    private function normalizeMoveDirection(mixed $direction): string
    {
        $value = mb_strtolower(trim((string) $direction));
        if ($value !== 'up' && $value !== 'down') {
            throw ValidationException::withMessages([
                'data.direction' => 'Ungültige Sortierrichtung.',
            ]);
        }

        return $value;
    }

    private function moveWithinOrderedIds(array $orderedIds, int $currentId, string $direction): array
    {
        $index = array_search($currentId, $orderedIds, true);
        if ($index === false) {
            return $orderedIds;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($targetIndex < 0 || $targetIndex >= count($orderedIds)) {
            return $orderedIds;
        }

        $targetId = $orderedIds[$targetIndex];
        $orderedIds[$targetIndex] = $orderedIds[$index];
        $orderedIds[$index] = $targetId;

        return $orderedIds;
    }

    private function persistSubjectSortOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            MaterialSubject::query()
                ->whereKey($id)
                ->update(['sort_order' => $index + 1]);
        }
    }

    private function persistTopicSortOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            MaterialTopic::query()
                ->whereKey($id)
                ->update(['sort_order' => $index + 1]);
        }
    }

    private function persistUnitSortOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            MaterialUnit::query()
                ->whereKey($id)
                ->update(['sort_order' => $index + 1]);
        }
    }

    private function assertSubjectBelongsToUser(User $user, MaterialSubject $subject): void
    {
        if ((int) $subject->user_id !== (int) $user->id) {
            abort(403, 'Fach gehört nicht zum aktuellen Benutzer.');
        }

        $workspaceId = $this->activeWorkspaceIdForUser($user);
        if ((int) ($subject->workspace_id ?? 0) !== $workspaceId) {
            abort(403, 'Fach liegt nicht im aktiven Workspace.');
        }
    }

    private function assertTopicBelongsToUser(User $user, MaterialTopic $topic): void
    {
        $topic->loadMissing('subject');
        $subject = $topic->subject;
        if (! $subject instanceof MaterialSubject || (int) $subject->user_id !== (int) $user->id) {
            abort(403, 'Thema gehört nicht zum aktuellen Benutzer.');
        }

        $workspaceId = $this->activeWorkspaceIdForUser($user);
        if ((int) ($subject->workspace_id ?? 0) !== $workspaceId) {
            abort(403, 'Thema liegt nicht im aktiven Workspace.');
        }
    }

    private function assertUnitBelongsToUser(User $user, MaterialUnit $unit): void
    {
        $unit->loadMissing('topic.subject');
        $topic = $unit->topic;
        $subject = $topic?->subject;

        if (! $subject instanceof MaterialSubject || (int) $subject->user_id !== (int) $user->id) {
            abort(403, 'Bereich gehört nicht zum aktuellen Benutzer.');
        }

        $workspaceId = $this->activeWorkspaceIdForUser($user);
        if ((int) ($subject->workspace_id ?? 0) !== $workspaceId) {
            abort(403, 'Bereich liegt nicht im aktiven Workspace.');
        }
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

    private function assertEditableTextAttachment(MaterialCardAttachment $attachment): void
    {
        if ($attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE) {
            throw ValidationException::withMessages([
                'data.content_html' => 'Nur Datei-Anhänge können bearbeitet werden.',
            ]);
        }

        $filePath = trim((string) ($attachment->file_path ?? ''));
        if ($filePath === '' || ! Storage::disk('local')->exists($filePath)) {
            throw ValidationException::withMessages([
                'data.content_html' => 'Datei wurde nicht gefunden.',
            ]);
        }

        $mimeType = strtolower(trim((string) ($attachment->mime_type ?? '')));
        $extension = strtolower((string) pathinfo((string) ($attachment->name ?: $filePath), PATHINFO_EXTENSION));
        $isHtmlMime = $mimeType === 'text/html' || $mimeType === 'application/xhtml+xml';
        $isHtmlExtension = $extension === 'html' || $extension === 'htm';

        if (! $isHtmlMime && ! $isHtmlExtension) {
            throw ValidationException::withMessages([
                'data.content_html' => 'Dieser Anhangstyp kann nicht als Text bearbeitet werden.',
            ]);
        }
    }

    private function ensureHtmlAttachmentNameExtension(?string $name): string
    {
        $normalized = trim((string) ($name ?? ''));
        if ($normalized === '') {
            return 'Text.html';
        }

        if (preg_match('/\.(html?|HTML?)$/', $normalized)) {
            return mb_substr($normalized, 0, 255);
        }

        return mb_substr($normalized.'.html', 0, 255);
    }

    private function isHtmlAttachmentFile(string $mimeType, string $fileName): bool
    {
        $normalizedMimeType = strtolower(trim($mimeType));
        if ($normalizedMimeType === 'text/html' || $normalizedMimeType === 'application/xhtml+xml') {
            return true;
        }

        $extension = strtolower((string) pathinfo((string) $fileName, PATHINFO_EXTENSION));

        return $extension === 'html' || $extension === 'htm';
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
        $defaultTypeMeta = [];
        foreach ($this->defaultTypeValues() as $defaultType) {
            $defaultName = $this->normalizeName($defaultType['value'] ?? '');
            if ($defaultName === '') {
                continue;
            }
            $defaultKey = mb_strtolower($defaultName);
            $defaultTypeMeta[$defaultKey] = [
                'icon' => $this->normalizeTypeIcon($defaultType['icon'] ?? null),
                'color' => $this->normalizeTypeColor($defaultType['color'] ?? null),
            ];
        }

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
                $attributes['icon'] = $defaultTypeMeta[$key]['icon'] ?? $this->defaultTypeIcon();
            }

            if ($this->hasTypeColorColumn()) {
                $attributes['color'] = $defaultTypeMeta[$key]['color'] ?? null;
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

        $hasColorColumn = $this->hasStatusColorColumn();
        $defaultStatusMeta = [];
        foreach ($this->defaultStatusValues() as $defaultStatus) {
            $defaultValue = trim((string) ($defaultStatus['value'] ?? ''));
            if ($defaultValue === '') {
                continue;
            }
            $defaultStatusMeta[mb_strtolower($defaultValue)] = [
                'label' => trim((string) ($defaultStatus['label'] ?? '')),
                'color' => $this->normalizeStatusColor($defaultStatus['color'] ?? null),
            ];
        }

        $knownRows = MaterialStatus::query()
            ->where('school_id', $schoolId)
            ->get();

        $known = [];
        foreach ($knownRows as $row) {
            $value = trim((string) ($row->value ?? ''));
            if ($value === '') {
                continue;
            }
            $key = mb_strtolower($value);
            $known[$key] = true;

            if (! $hasColorColumn) {
                continue;
            }

            $currentColor = $this->normalizeStatusColor($row->color ?? null);
            $defaultColor = $defaultStatusMeta[$key]['color'] ?? null;
            if ($currentColor !== null || $defaultColor === null) {
                continue;
            }

            $row->update([
                'color' => $defaultColor,
            ]);
        }

        foreach ($this->defaultStatusValues() as $defaultStatus) {
            $value = trim((string) ($defaultStatus['value'] ?? ''));
            $label = trim((string) ($defaultStatus['label'] ?? ''));
            $color = $this->normalizeStatusColor($defaultStatus['color'] ?? null);
            if ($value === '' || $label === '') {
                continue;
            }

            $key = mb_strtolower($value);
            if (isset($known[$key])) {
                continue;
            }

            $attributes = [
                'school_id' => $schoolId,
                'value' => $value,
                'label' => $label,
            ];
            if ($hasColorColumn) {
                $attributes['color'] = $color;
            }

            MaterialStatus::query()->create($attributes);
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

            $attributes = [
                'school_id' => $schoolId,
                'value' => $value,
                'label' => $this->defaultStatusLabelForValue($value),
            ];
            if ($hasColorColumn) {
                $attributes['color'] = $this->defaultStatusColorForValue($value);
            }

            MaterialStatus::query()->create($attributes);
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

    private function hasTypeColorColumn(): bool
    {
        return Schema::hasTable('material_types') && Schema::hasColumn('material_types', 'color');
    }

    private function hasStatusColorColumn(): bool
    {
        return Schema::hasTable('material_statuses') && Schema::hasColumn('material_statuses', 'color');
    }

    private function defaultStatusValues(): array
    {
        return [
            ['value' => MaterialCard::STATUS_INBOX, 'label' => 'Neu/Idee', 'color' => '#607d8b'],
            ['value' => MaterialCard::STATUS_IN_PROGRESS, 'label' => 'In Arbeit', 'color' => '#f9a825'],
            ['value' => MaterialCard::STATUS_DONE, 'label' => 'ok', 'color' => '#2e7d32'],
            ['value' => MaterialCard::STATUS_UPDATE_NEEDED, 'label' => 'Änderung nötig', 'color' => '#c62828'],
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

    private function defaultStatusColorForValue(string $value): ?string
    {
        $normalizedValue = mb_strtolower(trim($value));
        foreach ($this->defaultStatusValues() as $status) {
            $statusValue = mb_strtolower(trim((string) ($status['value'] ?? '')));
            $statusColor = $this->normalizeStatusColor($status['color'] ?? null);
            if ($statusValue === $normalizedValue && $statusColor !== null) {
                return $statusColor;
            }
        }

        return null;
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

    private function supportsUserMaterialsPaginationSettings(): bool
    {
        return Schema::hasTable('users') && Schema::hasColumn('users', 'materials_pagination_number');
    }

    private function materialsPaginationNumberForUser(User $user): int
    {
        $configValue = (int) config('schooltool.pagination', self::DEFAULT_MATERIALS_PAGINATION_NUMBER);
        $defaultValue = max(1, min(200, $configValue > 0 ? $configValue : self::DEFAULT_MATERIALS_PAGINATION_NUMBER));

        if (! $this->supportsUserMaterialsPaginationSettings()) {
            return $defaultValue;
        }

        $value = (int) ($user->materials_pagination_number ?? 0);
        if ($value <= 0) {
            return $defaultValue;
        }

        return max(1, min(200, $value));
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
            $color = null;

            if (is_array($rawValue)) {
                $name = $this->normalizeName($rawValue['value'] ?? $rawValue['label'] ?? '');
                $icon = $this->normalizeTypeIcon($rawValue['icon'] ?? null);
                $color = $this->normalizeTypeColor($rawValue['color'] ?? null);
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
                'color' => $color,
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

    private function normalizeTypeColor(?string $color): ?string
    {
        $value = trim((string) $color);
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value)) {
            return null;
        }

        if (strlen($value) === 4) {
            $value = '#'.$value[1].$value[1].$value[2].$value[2].$value[3].$value[3];
        }

        return mb_strtolower($value);
    }

    private function normalizeStatusColor(?string $color): ?string
    {
        return $this->normalizeTypeColor($color);
    }

    private function cardRelations(): array
    {
        $relations = ['attachments'];

        if ($this->supportsMaterialInboxImports()) {
            $relations[] = 'inboxImports';
        }

        if (! $this->supportsClassificationTables()) {
            return $relations;
        }

        $relations[] = 'classifications.subject';
        $relations[] = 'classifications.topic';
        $relations[] = 'classifications.unit';

        return $relations;
    }

    private function syncLinkedInboxImportsForUser(User $user, array $targetCardIds = []): void
    {
        if (! $this->supportsLinkedInboxImports()) {
            return;
        }

        $normalizedTargetIds = collect($targetCardIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $importsQuery = MaterialInboxImport::query()
            ->where('target_user_id', (int) $user->id)
            ->where('import_mode', MaterialInboxImport::MODE_LINK);

        if ($normalizedTargetIds !== []) {
            $importsQuery->whereIn('target_material_card_id', $normalizedTargetIds);
        }

        $imports = $importsQuery->get([
            'id',
            'target_user_id',
            'target_material_card_id',
            'source_rule_id',
            'source_school_id',
            'source_material_id',
            'import_mode',
        ]);

        if ($imports->isEmpty()) {
            return;
        }

        $ruleMap = MaterialShareRule::query()
            ->whereIn(
                'id',
                $imports->pluck('source_rule_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all()
            )
            ->with(['targets:id,material_share_rule_id,target_type,audience_scope,permission,user_group_id,user_id'])
            ->get()
            ->keyBy(fn (MaterialShareRule $rule) => (int) $rule->id);

        $memberGroupSet = $this->memberGroupSetForUser($user);

        $targetCards = MaterialCard::query()
            ->where('user_id', (int) $user->id)
            ->whereIn('id', $imports->pluck('target_material_card_id')->map(fn ($id) => (int) $id)->all())
            ->with('attachments')
            ->get()
            ->keyBy(fn (MaterialCard $card) => (int) $card->id);

        $sourceCardsByKey = [];
        $sourceGroups = $imports->groupBy(fn (MaterialInboxImport $import) => (int) ($import->source_school_id ?? 0));
        foreach ($sourceGroups as $sourceSchoolId => $group) {
            $schoolId = (int) $sourceSchoolId;
            if ($schoolId <= 0) {
                continue;
            }

            $sourceIds = $group->pluck('source_material_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            if ($sourceIds === []) {
                continue;
            }

            $sourceCards = MaterialCard::query()
                ->where('school_id', $schoolId)
                ->whereIn('id', $sourceIds)
                ->with('attachments')
                ->get();

            foreach ($sourceCards as $sourceCard) {
                $key = $this->inboxSourceCardKey((int) $sourceCard->school_id, (int) $sourceCard->id);
                $sourceCardsByKey[$key] = $sourceCard;
            }
        }

        foreach ($imports as $import) {
            $targetCardId = (int) ($import->target_material_card_id ?? 0);
            $targetCard = $targetCards->get($targetCardId);
            if (! $targetCard) {
                continue;
            }

            $sourceKey = $this->inboxSourceCardKey((int) ($import->source_school_id ?? 0), (int) ($import->source_material_id ?? 0));
            $sourceCard = $sourceCardsByKey[$sourceKey] ?? null;
            if (! $sourceCard) {
                continue;
            }

            $permission = MaterialShareTarget::PERMISSION_READ_ONLY;
            $ruleId = (int) ($import->source_rule_id ?? 0);
            $rule = $ruleId > 0 ? $ruleMap->get($ruleId) : null;
            if ($rule instanceof MaterialShareRule) {
                $permission = $this->resolveLinkedPermissionForUser(
                    rule: $rule,
                    userId: (int) $user->id,
                    schoolId: (int) $user->school_id,
                    memberGroupSet: $memberGroupSet,
                );
            }

            if (! $this->linkedCardNeedsSync($targetCard, $sourceCard)) {
                continue;
            }

            if ($permission !== MaterialShareTarget::PERMISSION_READ_ONLY) {
                $targetUpdatedAt = $targetCard->updated_at;
                $sourceUpdatedAt = $sourceCard->updated_at;
                $targetIsNewer = $targetUpdatedAt && (! $sourceUpdatedAt || $targetUpdatedAt->gt($sourceUpdatedAt));

                if ($targetIsNewer) {
                    $this->syncLinkedSourceCardFromTarget($sourceCard, $targetCard);

                    continue;
                }
            }

            $this->syncLinkedCardFromSource($targetCard, $sourceCard);
        }
    }

    private function syncLinkedTopicInboxImportsForUser(User $user): void
    {
        if (! $this->supportsMaterialTopicInboxImports()) {
            return;
        }

        $imports = MaterialTopicInboxImport::query()
            ->where('target_user_id', (int) $user->id)
            ->get(['target_topic_id', 'source_school_id', 'source_topic_id']);

        if ($imports->isEmpty()) {
            return;
        }

        $targetTopicIds = $imports->pluck('target_topic_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($targetTopicIds === []) {
            return;
        }

        $targetTopics = MaterialTopic::query()
            ->whereIn('id', $targetTopicIds)
            ->with('subject:id,user_id')
            ->get()
            ->filter(fn (MaterialTopic $topic) => (int) ($topic->subject?->user_id ?? 0) === (int) $user->id)
            ->keyBy(fn (MaterialTopic $topic) => (int) $topic->id);

        if ($targetTopics->isEmpty()) {
            return;
        }

        $subjectTopicNameMap = [];
        $subjectIds = $targetTopics->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($subjectIds !== []) {
            $subjectTopics = MaterialTopic::query()
                ->whereIn('subject_id', $subjectIds)
                ->get(['id', 'subject_id', 'name']);

            foreach ($subjectTopics as $subjectTopic) {
                $subjectId = (int) ($subjectTopic->subject_id ?? 0);
                if ($subjectId <= 0) {
                    continue;
                }

                $normalizedName = mb_strtolower($this->normalizeName($subjectTopic->name));
                if ($normalizedName === '') {
                    continue;
                }

                $subjectTopicNameMap[$subjectId][$normalizedName] = (int) ($subjectTopic->id ?? 0);
            }
        }

        $sourceTopicsByKey = [];
        $sourceGroups = $imports->groupBy(fn (MaterialTopicInboxImport $import) => (int) ($import->source_school_id ?? 0));
        foreach ($sourceGroups as $sourceSchoolId => $group) {
            $schoolId = (int) $sourceSchoolId;
            if ($schoolId <= 0) {
                continue;
            }

            $sourceTopicIds = $group->pluck('source_topic_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            if ($sourceTopicIds === []) {
                continue;
            }

            $sourceTopics = MaterialTopic::query()
                ->whereIn('id', $sourceTopicIds)
                ->whereHas('subject.user', fn ($query) => $query->where('school_id', $schoolId))
                ->get(['id', 'name']);

            foreach ($sourceTopics as $sourceTopic) {
                $sourceTopicId = (int) ($sourceTopic->id ?? 0);
                if ($sourceTopicId <= 0) {
                    continue;
                }

                $sourceTopicsByKey[$this->inboxSourceTopicKey($schoolId, $sourceTopicId)] = $sourceTopic;
            }
        }

        foreach ($imports as $import) {
            $targetTopicId = (int) ($import->target_topic_id ?? 0);
            if ($targetTopicId <= 0) {
                continue;
            }

            $targetTopic = $targetTopics->get($targetTopicId);
            if (! $targetTopic instanceof MaterialTopic) {
                continue;
            }

            $sourceSchoolId = (int) ($import->source_school_id ?? 0);
            $sourceTopicId = (int) ($import->source_topic_id ?? 0);
            if ($sourceSchoolId <= 0 || $sourceTopicId <= 0) {
                continue;
            }

            $sourceTopic = $sourceTopicsByKey[$this->inboxSourceTopicKey($sourceSchoolId, $sourceTopicId)] ?? null;
            if (! $sourceTopic instanceof MaterialTopic) {
                continue;
            }

            $nextName = $this->normalizeName($sourceTopic->name);
            if ($nextName === '') {
                continue;
            }

            $subjectId = (int) ($targetTopic->subject_id ?? 0);
            if ($subjectId <= 0) {
                continue;
            }

            $currentName = $this->normalizeName($targetTopic->name);
            if ($currentName === $nextName) {
                continue;
            }

            $nextNameKey = mb_strtolower($nextName);
            $currentNameKey = mb_strtolower($currentName);
            $subjectNameMap = $subjectTopicNameMap[$subjectId] ?? [];
            $existingTopicIdForNextName = (int) ($subjectNameMap[$nextNameKey] ?? 0);
            if ($existingTopicIdForNextName > 0 && $existingTopicIdForNextName !== $targetTopicId) {
                continue;
            }

            $targetTopic->update([
                'name' => $nextName,
            ]);
            $targetTopic->name = $nextName;

            if ($currentNameKey !== '' && (($subjectTopicNameMap[$subjectId][$currentNameKey] ?? null) === $targetTopicId)) {
                unset($subjectTopicNameMap[$subjectId][$currentNameKey]);
            }
            $subjectTopicNameMap[$subjectId][$nextNameKey] = $targetTopicId;
        }
    }

    private function syncLinkedUnitInboxImportsForUser(User $user): void
    {
        if (! $this->supportsMaterialUnitInboxImports()) {
            return;
        }

        $imports = MaterialUnitInboxImport::query()
            ->where('target_user_id', (int) $user->id)
            ->get(['target_unit_id', 'source_school_id', 'source_unit_id']);

        if ($imports->isEmpty()) {
            return;
        }

        $targetUnitIds = $imports->pluck('target_unit_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($targetUnitIds === []) {
            return;
        }

        $targetUnits = MaterialUnit::query()
            ->whereIn('id', $targetUnitIds)
            ->with('topic.subject:id,user_id')
            ->get()
            ->filter(fn (MaterialUnit $unit) => (int) ($unit->topic?->subject?->user_id ?? 0) === (int) $user->id)
            ->keyBy(fn (MaterialUnit $unit) => (int) $unit->id);

        if ($targetUnits->isEmpty()) {
            return;
        }

        $topicUnitNameMap = [];
        $topicIds = $targetUnits->pluck('topic_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($topicIds !== []) {
            $topicUnits = MaterialUnit::query()
                ->whereIn('topic_id', $topicIds)
                ->get(['id', 'topic_id', 'name']);

            foreach ($topicUnits as $topicUnit) {
                $topicId = (int) ($topicUnit->topic_id ?? 0);
                if ($topicId <= 0) {
                    continue;
                }

                $normalizedName = mb_strtolower($this->normalizeName($topicUnit->name));
                if ($normalizedName === '') {
                    continue;
                }

                $topicUnitNameMap[$topicId][$normalizedName] = (int) ($topicUnit->id ?? 0);
            }
        }

        $sourceUnitsByKey = [];
        $sourceGroups = $imports->groupBy(fn (MaterialUnitInboxImport $import) => (int) ($import->source_school_id ?? 0));
        foreach ($sourceGroups as $sourceSchoolId => $group) {
            $schoolId = (int) $sourceSchoolId;
            if ($schoolId <= 0) {
                continue;
            }

            $sourceUnitIds = $group->pluck('source_unit_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            if ($sourceUnitIds === []) {
                continue;
            }

            $sourceUnits = MaterialUnit::query()
                ->whereIn('id', $sourceUnitIds)
                ->whereHas('topic.subject.user', fn ($query) => $query->where('school_id', $schoolId))
                ->get(['id', 'name']);

            foreach ($sourceUnits as $sourceUnit) {
                $sourceUnitId = (int) ($sourceUnit->id ?? 0);
                if ($sourceUnitId <= 0) {
                    continue;
                }

                $sourceUnitsByKey[$this->inboxSourceUnitKey($schoolId, $sourceUnitId)] = $sourceUnit;
            }
        }

        foreach ($imports as $import) {
            $targetUnitId = (int) ($import->target_unit_id ?? 0);
            if ($targetUnitId <= 0) {
                continue;
            }

            $targetUnit = $targetUnits->get($targetUnitId);
            if (! $targetUnit instanceof MaterialUnit) {
                continue;
            }

            $sourceSchoolId = (int) ($import->source_school_id ?? 0);
            $sourceUnitId = (int) ($import->source_unit_id ?? 0);
            if ($sourceSchoolId <= 0 || $sourceUnitId <= 0) {
                continue;
            }

            $sourceUnit = $sourceUnitsByKey[$this->inboxSourceUnitKey($sourceSchoolId, $sourceUnitId)] ?? null;
            if (! $sourceUnit instanceof MaterialUnit) {
                continue;
            }

            $nextName = $this->normalizeName($sourceUnit->name);
            if ($nextName === '') {
                continue;
            }

            $topicId = (int) ($targetUnit->topic_id ?? 0);
            if ($topicId <= 0) {
                continue;
            }

            $currentName = $this->normalizeName($targetUnit->name);
            if ($currentName === $nextName) {
                continue;
            }

            $nextNameKey = mb_strtolower($nextName);
            $currentNameKey = mb_strtolower($currentName);
            $topicNameMap = $topicUnitNameMap[$topicId] ?? [];
            $existingUnitIdForNextName = (int) ($topicNameMap[$nextNameKey] ?? 0);
            if ($existingUnitIdForNextName > 0 && $existingUnitIdForNextName !== $targetUnitId) {
                continue;
            }

            $targetUnit->update([
                'name' => $nextName,
            ]);
            $targetUnit->name = $nextName;

            if ($currentNameKey !== '' && (($topicUnitNameMap[$topicId][$currentNameKey] ?? null) === $targetUnitId)) {
                unset($topicUnitNameMap[$topicId][$currentNameKey]);
            }
            $topicUnitNameMap[$topicId][$nextNameKey] = $targetUnitId;
        }
    }

    private function linkedCardNeedsSync(MaterialCard $targetCard, MaterialCard $sourceCard): bool
    {
        $fields = ['title', 'source_url', 'source_text', 'type', 'status', 'notes'];
        foreach ($fields as $field) {
            $targetValue = $this->normalizeNullableText($targetCard->{$field} ?? null);
            $sourceValue = $this->normalizeNullableText($sourceCard->{$field} ?? null);
            if ($targetValue !== $sourceValue) {
                return true;
            }
        }

        $targetSignature = $this->attachmentsSignature(is_array($targetCard->attachments) ? $targetCard->attachments : $targetCard->attachments->all());
        $sourceSignature = $this->attachmentsSignature(is_array($sourceCard->attachments) ? $sourceCard->attachments : $sourceCard->attachments->all());

        return $targetSignature !== $sourceSignature;
    }

    private function syncLinkedCardFromSource(MaterialCard $targetCard, MaterialCard $sourceCard): void
    {
        DB::transaction(function () use ($targetCard, $sourceCard) {
            $targetCard->update([
                'title' => trim((string) ($sourceCard->title ?? '')) !== '' ? (string) $sourceCard->title : 'Material',
                'source_url' => $this->normalizeNullableText($sourceCard->source_url),
                'source_text' => $this->normalizeNullableText($sourceCard->source_text),
                'type' => $this->normalizeOptionalName($sourceCard->type),
                'status' => trim((string) ($sourceCard->status ?? '')),
                'notes' => $this->normalizeNullableText($sourceCard->notes),
            ]);

            $this->replaceCardAttachmentsFromSource($targetCard, $sourceCard);
            $this->keywordService->rebuild($targetCard->fresh($this->cardRelations()));
        });
    }

    private function syncLinkedSourceCardFromTarget(MaterialCard $sourceCard, MaterialCard $targetCard): void
    {
        DB::transaction(function () use ($sourceCard, $targetCard) {
            $sourceCard->update([
                'title' => trim((string) ($targetCard->title ?? '')) !== '' ? (string) $targetCard->title : 'Material',
                'source_url' => $this->normalizeNullableText($targetCard->source_url),
                'source_text' => $this->normalizeNullableText($targetCard->source_text),
                'type' => $this->normalizeOptionalName($targetCard->type),
                'status' => trim((string) ($targetCard->status ?? '')),
                'notes' => $this->normalizeNullableText($targetCard->notes),
            ]);

            $this->replaceCardAttachmentsFromSource($sourceCard, $targetCard);
            $this->keywordService->rebuild($sourceCard->fresh($this->cardRelations()));
        });
    }

    private function replaceCardAttachmentsFromSource(MaterialCard $targetCard, MaterialCard $sourceCard): void
    {
        $targetCard->loadMissing('attachments');
        $sourceCard->loadMissing('attachments');

        foreach ($targetCard->attachments as $targetAttachment) {
            if ($targetAttachment->attachment_type === MaterialCardAttachment::TYPE_FILE && $targetAttachment->file_path) {
                Storage::delete($targetAttachment->file_path);
            }
            $targetAttachment->forceDelete();
        }

        $disk = Storage::disk(config('filesystems.default'));
        foreach ($sourceCard->attachments as $sourceAttachment) {
            $attachmentType = trim((string) ($sourceAttachment->attachment_type ?? ''));

            if ($attachmentType === MaterialCardAttachment::TYPE_LINK) {
                $targetCard->attachments()->create([
                    'attachment_type' => MaterialCardAttachment::TYPE_LINK,
                    'name' => $sourceAttachment->name,
                    'url' => $sourceAttachment->url,
                    'source_url' => $sourceAttachment->source_url,
                    'mime_type' => $sourceAttachment->mime_type,
                    'size_bytes' => $sourceAttachment->size_bytes,
                    'downloaded_at' => $sourceAttachment->downloaded_at,
                ]);

                continue;
            }

            if ($attachmentType !== MaterialCardAttachment::TYPE_FILE) {
                continue;
            }

            $sourcePath = trim((string) ($sourceAttachment->file_path ?? ''));
            if ($sourcePath === '' || ! $disk->exists($sourcePath)) {
                continue;
            }

            $nameSource = trim((string) ($sourceAttachment->name ?: basename($sourcePath)));
            if ($nameSource === '') {
                $nameSource = 'file';
            }
            $targetPath = $this->materialAttachmentDirectory($targetCard).'/'.$this->materialAttachmentStoredFileNameFromOriginalName($nameSource);
            $copied = $disk->copy($sourcePath, $targetPath);
            if (! $copied) {
                continue;
            }

            $targetCard->attachments()->create([
                'attachment_type' => MaterialCardAttachment::TYPE_FILE,
                'name' => $sourceAttachment->name,
                'file_path' => $targetPath,
                'mime_type' => $sourceAttachment->mime_type,
                'size_bytes' => $sourceAttachment->size_bytes,
                'source_url' => $sourceAttachment->source_url,
                'downloaded_at' => $sourceAttachment->downloaded_at,
            ]);
        }
    }

    private function attachmentsSignature(array $attachments): string
    {
        $rows = array_map(function ($attachment) {
            $type = trim((string) ($attachment->attachment_type ?? ''));

            return [
                'type' => $type,
                'name' => trim((string) ($attachment->name ?? '')),
                'url' => trim((string) ($attachment->url ?? '')),
                'source_url' => trim((string) ($attachment->source_url ?? '')),
                'mime_type' => trim((string) ($attachment->mime_type ?? '')),
                'size_bytes' => (int) ($attachment->size_bytes ?? 0),
            ];
        }, $attachments);

        usort($rows, function (array $left, array $right): int {
            return json_encode($left, JSON_UNESCAPED_UNICODE) <=> json_encode($right, JSON_UNESCAPED_UNICODE);
        });

        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    private function inboxSourceCardKey(int $schoolId, int $cardId): string
    {
        return $schoolId.':'.$cardId;
    }

    private function inboxSourceTopicKey(int $schoolId, int $topicId): string
    {
        return $schoolId.':'.$topicId;
    }

    private function inboxSourceUnitKey(int $schoolId, int $unitId): string
    {
        return $schoolId.':'.$unitId;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<int,true>  $memberGroupSet
     */
    private function resolveLinkedPermissionForUser(
        MaterialShareRule $rule,
        int $userId,
        int $schoolId,
        array $memberGroupSet,
    ): string {
        $bestPermission = MaterialShareTarget::PERMISSION_READ_ONLY;
        $bestRank = $this->linkedPermissionRank($bestPermission);

        foreach ($rule->targets as $target) {
            $targetType = trim((string) ($target->target_type ?? ''));

            $matches = false;
            if ($targetType === MaterialShareTarget::TARGET_USER) {
                $matches = (int) ($target->user_id ?? 0) === $userId;
            } elseif ($targetType === MaterialShareTarget::TARGET_EVERYONE) {
                $audienceScope = trim((string) ($target->audience_scope ?? ''));
                $matches = $audienceScope === MaterialShareTarget::AUDIENCE_SCOPE_GLOBAL
                    || ($audienceScope === MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL && (int) ($rule->school_id ?? 0) === $schoolId);
            } elseif ($targetType === MaterialShareTarget::TARGET_GROUP) {
                $matches = isset($memberGroupSet[(int) ($target->user_group_id ?? 0)]);
            }

            if (! $matches) {
                continue;
            }

            $permission = trim((string) ($target->permission ?? MaterialShareTarget::PERMISSION_READ_ONLY));
            $rank = $this->linkedPermissionRank($permission);
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $bestPermission = $permission;
            }
        }

        return $bestPermission;
    }

    private function linkedPermissionRank(string $permission): int
    {
        return match ($permission) {
            MaterialShareTarget::PERMISSION_FULL_ACCESS => 3,
            MaterialShareTarget::PERMISSION_READ_WRITE => 2,
            MaterialShareTarget::PERMISSION_READ_ONLY => 1,
            default => 0,
        };
    }

    private function linkedPermissionLabel(string $permission): string
    {
        return match ($permission) {
            MaterialShareTarget::PERMISSION_FULL_ACCESS => 'VOLLZUGRIFF',
            MaterialShareTarget::PERMISSION_READ_WRITE => 'LESEN/SCHREIBEN',
            MaterialShareTarget::PERMISSION_READ_ONLY => 'NUR LESEN',
            default => mb_strtoupper(trim((string) $permission)),
        };
    }

    /**
     * @return array<int,true>
     */
    private function memberGroupSetForUser(User $user): array
    {
        $memberGroupIds = UserGroup::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', (int) $user->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        return array_fill_keys($memberGroupIds, true);
    }

    private function activeWorkspaceForUser(User $user): MaterialWorkspace
    {
        $userId = (int) $user->id;
        $workspaceId = $this->activeWorkspaceByUserId[$userId] ?? 0;
        if ($workspaceId > 0) {
            $cachedWorkspace = MaterialWorkspace::query()->find($workspaceId);
            if ($cachedWorkspace instanceof MaterialWorkspace) {
                return $cachedWorkspace;
            }
        }

        $workspace = $this->workspaceService->resolveActiveWorkspace($user);
        $this->activeWorkspaceByUserId[$userId] = (int) $workspace->id;

        return $workspace;
    }

    private function activeWorkspaceIdForUser(User $user): int
    {
        return (int) $this->activeWorkspaceForUser($user)->id;
    }

    private function supportsMaterialInboxImports(): bool
    {
        if ($this->hasMaterialInboxImportsTableCache === null) {
            $this->hasMaterialInboxImportsTableCache = Schema::hasTable('material_inbox_imports');
        }

        return $this->hasMaterialInboxImportsTableCache;
    }

    private function supportsMaterialUnitInboxImports(): bool
    {
        if ($this->hasMaterialUnitInboxImportsTableCache === null) {
            $this->hasMaterialUnitInboxImportsTableCache = Schema::hasTable('material_unit_inbox_imports');
        }

        return $this->hasMaterialUnitInboxImportsTableCache;
    }

    private function supportsMaterialTopicInboxImports(): bool
    {
        if ($this->hasMaterialTopicInboxImportsTableCache === null) {
            $this->hasMaterialTopicInboxImportsTableCache = Schema::hasTable('material_topic_inbox_imports');
        }

        return $this->hasMaterialTopicInboxImportsTableCache;
    }

    private function supportsLinkedInboxImports(): bool
    {
        if (! $this->supportsMaterialInboxImports()) {
            return false;
        }

        if ($this->materialInboxImportsHasImportModeColumnCache === null) {
            $this->materialInboxImportsHasImportModeColumnCache = Schema::hasColumn('material_inbox_imports', 'import_mode');
        }

        return $this->materialInboxImportsHasImportModeColumnCache;
    }

    private function restorableDeletedCardsLimit(): int
    {
        return max(1, (int) config('schooltool.materials_restore_deleted_cards_limit', 5));
    }

    private function trimRestorableDeletedCardsForUser(int $userId, int $keepCount, ?int $workspaceId = null): void
    {
        $keep = max(0, $keepCount);
        $deletedCardsQuery = MaterialCard::onlyTrashed()
            ->where('user_id', $userId)
            ->orderByDesc('deleted_at')
            ->orderByDesc('id');

        if ((int) ($workspaceId ?? 0) > 0) {
            $deletedCardsQuery->where('workspace_id', (int) $workspaceId);
        }

        $deletedCards = $deletedCardsQuery->get();

        if ($deletedCards->count() <= $keep) {
            return;
        }

        $cardsToPurge = $deletedCards->slice($keep)->values();
        foreach ($cardsToPurge as $deletedCard) {
            $this->forceDeleteDeletedCard($deletedCard);
        }
    }

    private function forceDeleteDeletedCard(MaterialCard $deletedCard): void
    {
        $attachments = MaterialCardAttachment::withTrashed()
            ->where('material_card_id', $deletedCard->id)
            ->get();

        foreach ($attachments as $attachment) {
            if ($attachment->attachment_type === MaterialCardAttachment::TYPE_FILE && $attachment->file_path) {
                Storage::delete($attachment->file_path);
            }
        }

        foreach ($attachments as $attachment) {
            $attachment->forceDelete();
        }

        $deletedCard->forceDelete();
    }

    private function supportsClassificationTables(): bool
    {
        return Schema::hasTable('material_subjects')
            && Schema::hasTable('material_topics')
            && Schema::hasTable('material_units')
            && Schema::hasTable('material_card_classifications');
    }

    private function supportsDeletedClassificationSnapshots(): bool
    {
        return $this->supportsClassificationTables()
            && Schema::hasTable('material_card_deleted_classifications');
    }

    private function supportsClassificationSortOrder(): bool
    {
        return $this->supportsClassificationTables()
            && Schema::hasColumn('material_subjects', 'sort_order')
            && Schema::hasColumn('material_topics', 'sort_order')
            && Schema::hasColumn('material_units', 'sort_order');
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
        $suffix = $extension !== '' ? '.'.$extension : '';

        return Str::uuid()->toString().'-'.$slug.$suffix;
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

    private function normalizeRemoteImageUrl(string $url): string
    {
        $value = trim($url);
        if ($value === '') {
            return '';
        }

        $validated = filter_var($value, FILTER_VALIDATE_URL);
        if (! is_string($validated) || $validated === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return mb_substr($validated, 0, 2048);
    }

    private function normalizeMimeType(?string $mimeType): ?string
    {
        $raw = trim((string) $mimeType);
        if ($raw === '') {
            return null;
        }

        $normalized = strtolower(trim(explode(';', $raw)[0] ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function detectedMimeTypeForPath(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        if (! function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $this->normalizeMimeType(is_string($mimeType) ? $mimeType : null);
    }

    private function isImageMimeType(?string $mimeType): bool
    {
        return is_string($mimeType) && Str::startsWith($mimeType, 'image/');
    }

    private function extensionFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return trim($extension);
    }

    private function imageMimeTypeFromExtension(string $extension): ?string
    {
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'avif' => 'image/avif',
            'heic' => 'image/heic',
        ];

        $key = strtolower(trim($extension));

        return $map[$key] ?? null;
    }

    private function imageExtensionFromMimeType(?string $mimeType): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/avif' => 'avif',
            'image/heic' => 'heic',
        ];

        $key = strtolower(trim((string) $mimeType));

        return $map[$key] ?? null;
    }

    private function remoteImageOriginalName(string $url, ?string $mimeType): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $fileName = basename($path);
        $fileName = trim(urldecode($fileName));
        if ($fileName === '' || $fileName === '/' || $fileName === '.') {
            $fileName = 'bild';
        }

        $baseName = trim((string) pathinfo($fileName, PATHINFO_FILENAME));
        if ($baseName === '') {
            $baseName = 'bild';
        }

        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = (string) ($this->imageExtensionFromMimeType($mimeType) ?? 'jpg');
        }

        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'jpg';

        return mb_substr($baseName, 0, 200).'.'.$extension;
    }
}
