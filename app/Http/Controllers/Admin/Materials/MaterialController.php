<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialCardAttachmentTextUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialCardAttachmentUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialCardFileAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardIndexRequest;
use App\Http\Requests\Admin\Materials\MaterialCardLinkAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardQuickStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardRemoteImageAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardTempAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardUpdateRequest;
use App\Http\Resources\Admin\Materials\MaterialCardAttachmentResource;
use App\Http\Resources\Admin\Materials\MaterialCardResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialCardClassification;
use App\Models\MaterialInboxImport;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareTarget;
use App\Models\MaterialTopic;
use App\Models\MaterialTopicInboxImport;
use App\Models\MaterialUnit;
use App\Models\MaterialUnitInboxImport;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Services\Materials\MaterialService;
use App\Services\Materials\MaterialWorkspaceService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Style\Language;

class MaterialController extends Controller
{
    public function config(Request $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();

        return response()->json($service->config($authUser), 200);
    }

    public function index(MaterialCardIndexRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $validated = $request->validated();

        $cards = $service->listForUser($authUser, $validated);

        return response()->json([
            'data' => MaterialCardResource::collection($cards),
            'meta' => new PaginateResource($cards),
        ], 200);
    }

    public function store(MaterialCardStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $validated = $request->validated()['data'];

        $card = $service->createCard($authUser, $validated);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function quickStore(MaterialCardQuickStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $validated = $request->validated()['data'];

        $card = $service->createCard($authUser, $validated);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function restoreLastDeleted(MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();

        $card = $service->restoreLastDeletedCard($authUser);
        if (! $card) {
            return response()->json([
                'message' => 'Kein zuletzt gelöschtes Material zum Wiederherstellen vorhanden.',
            ], 404);
        }

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function restoreDeletedById(int $card_id, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();

        $card = $service->restoreDeletedCard($authUser, $card_id);
        if (! $card) {
            return response()->json([
                'message' => 'Das gelöschte Material konnte nicht wiederhergestellt werden.',
            ], 404);
        }

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function purgeDeletedById(int $card_id, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();

        $deleted = $service->purgeDeletedCardById($authUser, $card_id);
        if (! $deleted) {
            return response()->json([
                'message' => 'Das gelöschte Material konnte nicht endgültig gelöscht werden.',
            ], 404);
        }

        return response()->noContent();
    }

    public function lastDeletedRestoreInfo(MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();

        $items = $service->deletedCardsRestoreList($authUser);
        $info = $items[0] ?? null;
        if (! $info) {
            return response()->json([
                'message' => 'Kein wiederherstellbares zuletzt gelöschtes Material vorhanden.',
            ], 404);
        }

        return response()->json([
            'data' => $info,
        ], 200);
    }

    public function deletedRestoreList(MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();

        $items = $service->deletedCardsRestoreList($authUser);

        return response()->json([
            'data' => $items,
        ], 200);
    }

    public function deletedWorkspaceRestoreList(MaterialService $service, MaterialWorkspaceService $workspaceService)
    {
        $authUser = $this->authorizeForMaterials();
        $workspace = $workspaceService->resolveActiveWorkspace($authUser);

        if (! $workspace) {
            return response()->json([
                'data' => [],
            ], 200);
        }

        $items = $service->deletedRestoreListForWorkspace($authUser, (int) $workspace->id);

        return response()->json([
            'data' => $items,
        ], 200);
    }

    public function restoreDeletedWorkspaceItem(Request $request, MaterialService $service, MaterialWorkspaceService $workspaceService)
    {
        $authUser = $this->authorizeForMaterials();
        $workspace = $workspaceService->resolveActiveWorkspace($authUser);

        if (! $workspace) {
            return response()->json([
                'message' => 'Kein aktiver Workspace vorhanden.',
            ], 404);
        }

        $validated = $request->validate([
            'data.type' => ['required', 'string', 'in:material,subject,topic,unit'],
            'data.id' => ['required', 'integer', 'min:1'],
        ]);

        $item = $service->restoreDeletedItemForWorkspace(
            $authUser,
            (int) $workspace->id,
            (string) ($validated['data']['type'] ?? ''),
            (int) ($validated['data']['id'] ?? 0),
        );

        if ($item === null) {
            return response()->json([
                'message' => 'Das gelöschte Element konnte nicht wiederhergestellt werden.',
            ], 404);
        }

        return response()->json([
            'data' => $item,
        ], 200);
    }

    public function purgeDeletedWorkspaceItem(Request $request, MaterialService $service, MaterialWorkspaceService $workspaceService)
    {
        $authUser = $this->authorizeForMaterials();
        $workspace = $workspaceService->resolveActiveWorkspace($authUser);

        if (! $workspace) {
            return response()->json([
                'message' => 'Kein aktiver Workspace vorhanden.',
            ], 404);
        }

        $validated = $request->validate([
            'data.type' => ['required', 'string', 'in:material,subject,topic,unit'],
            'data.id' => ['required', 'integer', 'min:1'],
        ]);

        $deleted = $service->purgeDeletedItemForWorkspace(
            $authUser,
            (int) $workspace->id,
            (string) ($validated['data']['type'] ?? ''),
            (int) ($validated['data']['id'] ?? 0),
        );

        if (! $deleted) {
            return response()->json([
                'message' => 'Das gelöschte Element konnte nicht endgültig gelöscht werden.',
            ], 404);
        }

        return response()->noContent();
    }

    public function show(MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $service->syncLinkedInboxCardForUser($authUser, $material_card);
        $card = $this->loadCardForResponse($material_card);
        $service->hydrateLinkedPermissionMetadata($authUser, collect([$card]));

        return response()->json(new MaterialCardResource($card), 200);
    }

    public function update(MaterialCardUpdateRequest $request, MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $this->assertLinkedCardAllowsEdit($service, $authUser, $material_card);
        $validated = $request->validated()['data'];

        $card = $service->updateCard($material_card, $validated, $authUser);
        $service->propagateLinkedWritableCardFromTarget($authUser, $card);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function destroy(MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $this->assertLinkedCardAllowsMaterialDelete($service, $authUser, $material_card);

        $service->deleteCard($material_card);

        return response()->noContent();
    }

    public function unlink(MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);

        if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
            abort(409, 'Link-Funktion ist erst nach aktueller Migration verfügbar.');
        }

        $removed = MaterialInboxImport::query()
            ->where('target_user_id', (int) $authUser->id)
            ->where('target_material_card_id', (int) $material_card->id)
            ->where('import_mode', MaterialInboxImport::MODE_LINK)
            ->delete();

        if ($removed <= 0) {
            abort(422, 'Material ist nicht als Link verknüpft.');
        }

        MaterialInboxImport::query()
            ->where('target_user_id', (int) $authUser->id)
            ->where('target_material_card_id', (int) $material_card->id)
            ->delete();

        $service->deleteCard($material_card);
        $service->purgeDeletedCardById($authUser, (int) $material_card->id);

        return response()->json([
            'message' => 'Link entfernt.',
            'data' => [
                'id' => (int) $material_card->id,
                'removed' => true,
            ],
        ], 200);
    }

    public function unlinkUnit(MaterialUnit $material_unit, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertUnitBelongsToOwner($authUser->id, $material_unit);

        if (! Schema::hasTable('material_unit_inbox_imports')) {
            abort(409, 'Link-Funktion für Einheiten ist erst nach aktueller Migration verfügbar.');
        }

        if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
            abort(409, 'Link-Funktion ist erst nach aktueller Migration verfügbar.');
        }

        $linkedUnitImportExists = MaterialUnitInboxImport::query()
            ->where('target_user_id', (int) $authUser->id)
            ->where('target_unit_id', (int) $material_unit->id)
            ->exists();

        if (! $linkedUnitImportExists) {
            abort(422, 'Einheit ist nicht als Link verknüpft.');
        }

        $unitId = (int) $material_unit->id;
        $ownerId = (int) $authUser->id;
        $unitCardIds = MaterialCardClassification::query()
            ->where('unit_id', $unitId)
            ->whereHas('materialCard', fn ($query) => $query->where('user_id', $ownerId))
            ->pluck('material_card_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $linkedCardIds = $unitCardIds->isEmpty()
            ? collect()
            : MaterialInboxImport::query()
                ->where('target_user_id', $ownerId)
                ->where('import_mode', MaterialInboxImport::MODE_LINK)
                ->whereIn('target_material_card_id', $unitCardIds->all())
                ->pluck('target_material_card_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

        $removedCardIds = [];
        $removedUnit = false;

        DB::transaction(function () use (
            $authUser,
            $service,
            $ownerId,
            $unitId,
            $linkedCardIds,
            &$removedCardIds,
            &$removedUnit
        ) {
            if ($linkedCardIds->isNotEmpty()) {
                MaterialInboxImport::query()
                    ->where('target_user_id', $ownerId)
                    ->whereIn('target_material_card_id', $linkedCardIds->all())
                    ->delete();

                $cards = MaterialCard::query()
                    ->where('user_id', $ownerId)
                    ->whereIn('id', $linkedCardIds->all())
                    ->get();

                foreach ($cards as $card) {
                    $cardId = (int) $card->id;
                    $service->deleteCard($card);
                    $service->purgeDeletedCardById($authUser, $cardId);
                    $removedCardIds[] = $cardId;
                }
            }

            MaterialUnitInboxImport::query()
                ->where('target_user_id', $ownerId)
                ->where('target_unit_id', $unitId)
                ->delete();

            $unitStillUsed = MaterialCardClassification::query()
                ->where('unit_id', $unitId)
                ->whereHas('materialCard', fn ($query) => $query->where('user_id', $ownerId))
                ->exists();

            if (! $unitStillUsed) {
                MaterialUnit::query()
                    ->whereKey($unitId)
                    ->delete();
                $removedUnit = true;
            }
        });

        sort($removedCardIds);

        return response()->json([
            'message' => 'Link entfernt.',
            'data' => [
                'id' => $unitId,
                'removed' => true,
                'removed_unit' => $removedUnit,
                'removed_card_ids' => array_values(array_unique($removedCardIds)),
            ],
        ], 200);
    }

    public function unlinkTopic(MaterialTopic $material_topic, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertTopicBelongsToOwner($authUser->id, $material_topic);

        if (! Schema::hasTable('material_topic_inbox_imports')) {
            abort(409, 'Link-Funktion für Themen ist erst nach aktueller Migration verfügbar.');
        }

        if (! Schema::hasTable('material_inbox_imports') || ! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
            abort(409, 'Link-Funktion ist erst nach aktueller Migration verfügbar.');
        }

        $linkedTopicImportExists = MaterialTopicInboxImport::query()
            ->where('target_user_id', (int) $authUser->id)
            ->where('target_topic_id', (int) $material_topic->id)
            ->exists();

        if (! $linkedTopicImportExists) {
            abort(422, 'Thema ist nicht als Link verknüpft.');
        }

        $topicId = (int) $material_topic->id;
        $ownerId = (int) $authUser->id;
        $topicCardIds = MaterialCardClassification::query()
            ->where('topic_id', $topicId)
            ->whereHas('materialCard', fn ($query) => $query->where('user_id', $ownerId))
            ->pluck('material_card_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $linkedCardIds = $topicCardIds->isEmpty()
            ? collect()
            : MaterialInboxImport::query()
                ->where('target_user_id', $ownerId)
                ->where('import_mode', MaterialInboxImport::MODE_LINK)
                ->whereIn('target_material_card_id', $topicCardIds->all())
                ->pluck('target_material_card_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

        $removedCardIds = [];
        $removedTopic = false;

        DB::transaction(function () use (
            $authUser,
            $service,
            $ownerId,
            $topicId,
            $linkedCardIds,
            &$removedCardIds,
            &$removedTopic
        ) {
            if ($linkedCardIds->isNotEmpty()) {
                MaterialInboxImport::query()
                    ->where('target_user_id', $ownerId)
                    ->whereIn('target_material_card_id', $linkedCardIds->all())
                    ->delete();

                $cards = MaterialCard::query()
                    ->where('user_id', $ownerId)
                    ->whereIn('id', $linkedCardIds->all())
                    ->get();

                foreach ($cards as $card) {
                    $cardId = (int) $card->id;
                    $service->deleteCard($card);
                    $service->purgeDeletedCardById($authUser, $cardId);
                    $removedCardIds[] = $cardId;
                }
            }

            if (Schema::hasTable('material_unit_inbox_imports')) {
                $topicUnitIds = MaterialUnit::query()
                    ->where('topic_id', $topicId)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->values();

                if ($topicUnitIds->isNotEmpty()) {
                    MaterialUnitInboxImport::query()
                        ->where('target_user_id', $ownerId)
                        ->whereIn('target_unit_id', $topicUnitIds->all())
                        ->delete();
                }
            }

            MaterialTopicInboxImport::query()
                ->where('target_user_id', $ownerId)
                ->where('target_topic_id', $topicId)
                ->delete();

            $topicStillUsed = MaterialCardClassification::query()
                ->where('topic_id', $topicId)
                ->whereHas('materialCard', fn ($query) => $query->where('user_id', $ownerId))
                ->exists();

            if (! $topicStillUsed) {
                MaterialTopic::query()
                    ->whereKey($topicId)
                    ->delete();
                $removedTopic = true;
            }
        });

        sort($removedCardIds);

        return response()->json([
            'message' => 'Link entfernt.',
            'data' => [
                'id' => $topicId,
                'removed' => true,
                'removed_topic' => $removedTopic,
                'removed_card_ids' => array_values(array_unique($removedCardIds)),
            ],
        ], 200);
    }

    public function storeLinkAttachment(
        MaterialCardLinkAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $this->assertLinkedCardAllowsAttachmentAppend($service, $authUser, $material_card);
        $validated = $request->validated()['data'];

        $attachment = $service->addLinkAttachment(
            $material_card,
            $validated['url'],
            $validated['name'] ?? null
        );
        $service->propagateLinkedWritableCardFromTarget($authUser, $material_card);

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function storeRemoteImageAttachment(
        MaterialCardRemoteImageAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $this->assertLinkedCardAllowsAttachmentAppend($service, $authUser, $material_card);
        $validated = $request->validated()['data'];

        $attachment = $service->addImageAttachmentFromUrl(
            $material_card,
            (string) ($validated['url'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );
        $service->propagateLinkedWritableCardFromTarget($authUser, $material_card);

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function storeFileAttachment(
        MaterialCardFileAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $this->assertLinkedCardAllowsAttachmentAppend($service, $authUser, $material_card);
        $validated = $request->validated();

        $attachment = $service->addFileAttachment(
            $material_card,
            $validated['file'],
            $validated['name'] ?? null
        );
        $service->propagateLinkedWritableCardFromTarget($authUser, $material_card);

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function storeTempFileAttachment(
        MaterialCardTempAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $this->assertLinkedCardAllowsAttachmentAppend($service, $authUser, $material_card);
        $validated = $request->validated()['data'];

        $attachment = $service->addFileAttachmentFromTempUpload(
            $authUser,
            $material_card,
            (string) ($validated['upload_id'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );
        $service->propagateLinkedWritableCardFromTarget($authUser, $material_card);

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function destroyAttachment(MaterialCardAttachment $material_card_attachment, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);
        $this->assertLinkedCardAllowsAttachmentDelete($service, $authUser, $material_card_attachment->materialCard);

        $service->deleteAttachment($material_card_attachment);
        $service->propagateLinkedWritableCardFromTarget($authUser, $material_card_attachment->materialCard);

        return response()->noContent();
    }

    public function updateAttachment(
        MaterialCardAttachmentUpdateRequest $request,
        MaterialCardAttachment $material_card_attachment,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);
        $this->assertLinkedCardAllowsEdit($service, $authUser, $material_card_attachment->materialCard);
        $validated = $request->validated()['data'];

        $attachment = $service->updateAttachmentName($material_card_attachment, $validated['name']);
        $service->propagateLinkedWritableCardFromTarget($authUser, $material_card_attachment->materialCard);

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function textAttachmentContent(MaterialCardAttachment $material_card_attachment, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);

        $contentHtml = $service->readEditableTextAttachmentContent($material_card_attachment);

        return response()->json([
            'data' => [
                'id' => (int) $material_card_attachment->id,
                'name' => (string) ($material_card_attachment->name ?? ''),
                'content_html' => $contentHtml,
            ],
        ], 200);
    }

    public function updateTextAttachmentContent(
        MaterialCardAttachmentTextUpdateRequest $request,
        MaterialCardAttachment $material_card_attachment,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);
        $this->assertLinkedCardAllowsEdit($service, $authUser, $material_card_attachment->materialCard);
        $validated = $request->validated()['data'];

        $attachment = $service->updateEditableTextAttachmentContent(
            $material_card_attachment,
            (string) ($validated['content_html'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );
        $service->propagateLinkedWritableCardFromTarget($authUser, $material_card_attachment->materialCard);

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function downloadAttachment(MaterialCardAttachment $material_card_attachment, Request $request)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertCanReadAttachment($authUser, $material_card_attachment, $request);
        $allowSharedDiskFallback = $this->isSharedInboxAttachmentReadable($authUser, $material_card_attachment, $request);

        if ($material_card_attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $material_card_attachment->file_path) {
            abort(404, 'Datei nicht gefunden');
        }

        $relativePath = (string) $material_card_attachment->file_path;
        $resolvedDisk = $this->resolveAttachmentStorageDisk($relativePath, $allowSharedDiskFallback);
        if ($resolvedDisk === null) {
            abort(404, 'Datei nicht gefunden');
        }
        $disk = $resolvedDisk;

        $name = $this->safeAttachmentDownloadName(
            $material_card_attachment->name ?: basename($relativePath)
        );

        if ($this->isHtmlAttachment($material_card_attachment)) {
            $rawHtml = (string) $disk->get($relativePath);
            $styledHtml = $this->ensureRichTextStylesForHtmlDownload($rawHtml, $name);

            return response()->streamDownload(
                static function () use ($styledHtml): void {
                    echo $styledHtml;
                },
                $name,
                [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        $stream = $disk->readStream($relativePath);
        if (! is_resource($stream)) {
            abort(404, 'Datei nicht gefunden');
        }

        $contentType = $this->attachmentDownloadContentType($material_card_attachment, $disk, $relativePath);

        return response()->streamDownload(
            static function () use ($stream): void {
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 8192);
                        if ($chunk === false) {
                            break;
                        }

                        echo $chunk;
                    }
                } finally {
                    fclose($stream);
                }
            },
            $name,
            [
                'Content-Type' => $contentType,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function downloadAttachmentDocx(MaterialCardAttachment $material_card_attachment, Request $request)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertCanReadAttachment($authUser, $material_card_attachment, $request);
        $allowSharedDiskFallback = $this->isSharedInboxAttachmentReadable($authUser, $material_card_attachment, $request);

        if (! $this->isHtmlAttachment($material_card_attachment)) {
            abort(422, 'DOCX-Export ist nur für Text/HTML-Anhänge verfügbar.');
        }

        $relativePath = (string) ($material_card_attachment->file_path ?? '');
        if ($relativePath === '') {
            abort(404, 'Datei nicht gefunden');
        }
        $disk = $this->resolveAttachmentStorageDisk($relativePath, $allowSharedDiskFallback);
        if ($disk === null) {
            abort(404, 'Datei nicht gefunden');
        }

        $name = $material_card_attachment->name ?: basename($relativePath);
        $rawHtml = (string) $disk->get($relativePath);
        $styledHtml = $this->ensureRichTextStylesForHtmlDownload($rawHtml, $name);
        $bodyHtml = $this->extractHtmlBody($styledHtml);
        $normalizedBodyHtml = $this->normalizeHtmlFragmentForDocx($bodyHtml);

        $phpWord = $this->newDocxDocument();
        $section = $phpWord->addSection();

        try {
            Html::addHtml($section, $normalizedBodyHtml, false, false);
        } catch (\Throwable $error) {
            Log::warning('DOCX export fallback to plain text.', [
                'attachment_id' => (int) $material_card_attachment->id,
                'error' => $error->getMessage(),
            ]);

            $phpWord = $this->newDocxDocument();
            $section = $phpWord->addSection();
            $fallbackText = $this->plainTextFromHtmlForDocx($bodyHtml);
            $this->addPlainTextToDocxSection($section, $fallbackText);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'materials-docx-');
        if (! is_string($tempPath) || $tempPath === '') {
            abort(500, 'Temporäre Datei konnte nicht erstellt werden.');
        }

        @unlink($tempPath);
        $tempDocxPath = $tempPath.'.docx';

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempDocxPath);
        } catch (\Throwable $error) {
            @unlink($tempDocxPath);
            abort(422, 'DOCX-Export konnte nicht erstellt werden.');
        }

        return response()
            ->download(
                $tempDocxPath,
                $this->docxDownloadName($name),
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            )
            ->deleteFileAfterSend(true);
    }

    public function previewAttachment(
        MaterialCardAttachment $material_card_attachment,
        MaterialAttachmentPreviewService $previewService,
        Request $request
    ) {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertCanReadAttachment($authUser, $material_card_attachment, $request);
        $allowSharedDiskFallback = $this->isSharedInboxAttachmentReadable($authUser, $material_card_attachment, $request);

        if ($material_card_attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $material_card_attachment->file_path) {
            abort(404, 'Datei nicht gefunden');
        }

        $downloadUrl = '/api/admin/materials/attachments/'.$material_card_attachment->id.'/download';
        $query = trim((string) $request->getQueryString());
        if ($query !== '') {
            $downloadUrl .= '?'.$query;
        }

        return $previewService->preview(
            $material_card_attachment,
            $downloadUrl,
            $this->attachmentStorageDiskCandidates($allowSharedDiskFallback)
        );
    }

    private function authorizeForMaterials()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }

    private function assertIsOwner(int $authUserId, int $ownerId): void
    {
        if ($authUserId !== $ownerId) {
            abort(403, 'Sie dürfen nur eigene Materialkarten verwalten.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function attachmentStorageDiskCandidates(bool $allowSharedDiskFallback = false): array
    {
        $candidates = [
            (string) config('filesystems.default'),
            'local',
            'public',
        ];

        if ($allowSharedDiskFallback) {
            $configuredDisks = array_keys((array) config('filesystems.disks', []));
            $candidates = [...$candidates, ...$configuredDisks];
        }

        return array_values(array_filter(array_unique($candidates), static fn (string $diskName): bool => $diskName !== ''));
    }

    private function resolveAttachmentStorageDisk(string $relativePath, bool $allowSharedDiskFallback = false): ?Filesystem
    {
        $path = trim($relativePath);
        if ($path === '') {
            return null;
        }

        foreach ($this->attachmentStorageDiskCandidates($allowSharedDiskFallback) as $diskName) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                return $disk;
            }
        }

        foreach ($this->attachmentStorageFallbackDisks() as $disk) {
            if ($disk->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * @return array<int, Filesystem>
     */
    private function attachmentStorageFallbackDisks(): array
    {
        $roots = [
            storage_path('app/private'),
            storage_path('app'),
            storage_path('app/public'),
        ];

        return array_map(
            static fn (string $root): Filesystem => Storage::build([
                'driver' => 'local',
                'root' => $root,
                'throw' => false,
            ]),
            array_values(array_unique($roots))
        );
    }

    private function assertCanReadAttachment(User $authUser, MaterialCardAttachment $attachment, Request $request): void
    {
        $ownerId = (int) ($attachment->materialCard?->user_id ?? 0);
        if ((int) $authUser->id === $ownerId) {
            return;
        }

        if ($this->isSharedInboxAttachmentReadable($authUser, $attachment, $request)) {
            return;
        }

        abort(403, 'Sie dürfen nur eigene Materialkarten verwalten.');
    }

    private function isSharedInboxAttachmentReadable(User $authUser, MaterialCardAttachment $attachment, Request $request): bool
    {
        if (! Schema::hasTable('material_share_rules') || ! Schema::hasTable('material_share_targets')) {
            return false;
        }

        $card = $attachment->materialCard;
        if (! $card) {
            return false;
        }

        $ruleId = (int) $request->query('rule_id');
        $materialId = (int) $request->query('material_id');
        if ($ruleId <= 0 || $materialId <= 0) {
            return false;
        }

        if ((int) $card->id !== $materialId || (int) $attachment->material_card_id !== $materialId) {
            return false;
        }

        $authUserId = (int) $authUser->id;
        $authSchoolId = (int) $authUser->school_id;
        $memberGroupIds = collect();
        if (Schema::hasTable('user_groups') && Schema::hasTable('user_group_user')) {
            $memberGroupIds = UserGroup::query()
                ->whereHas('members', fn ($query) => $query->where('users.id', $authUserId))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        $rule = MaterialShareRule::query()
            ->whereKey($ruleId)
            ->where('is_active', true)
            ->whereNotNull('created_by_user_id')
            ->where('created_by_user_id', '!=', $authUserId)
            ->where(function ($query) use ($authUserId, $authSchoolId, $memberGroupIds) {
                $query->whereHas('targets', function ($targetQuery) use ($authUserId) {
                    $targetQuery
                        ->where('target_type', MaterialShareTarget::TARGET_USER)
                        ->where('user_id', $authUserId);
                })->orWhereHas('targets', function ($targetQuery) {
                    $targetQuery
                        ->where('target_type', MaterialShareTarget::TARGET_EVERYONE)
                        ->where('audience_scope', MaterialShareTarget::AUDIENCE_SCOPE_GLOBAL);
                })->orWhere(function ($schoolWideQuery) use ($authSchoolId) {
                    $schoolWideQuery
                        ->where('school_id', $authSchoolId)
                        ->whereHas('targets', function ($targetQuery) {
                            $targetQuery
                                ->where('target_type', MaterialShareTarget::TARGET_EVERYONE)
                                ->where('audience_scope', MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL);
                        });
                });

                if ($memberGroupIds->isNotEmpty()) {
                    $query->orWhereHas('targets', function ($targetQuery) use ($memberGroupIds) {
                        $targetQuery
                            ->where('target_type', MaterialShareTarget::TARGET_GROUP)
                            ->whereIn('user_group_id', $memberGroupIds->all());
                    });
                }
            })
            ->first();

        if (! $rule) {
            return false;
        }

        if ((int) $rule->school_id !== (int) $card->school_id) {
            return false;
        }

        $creatorUserId = (int) ($rule->created_by_user_id ?? 0);
        if ($creatorUserId > 0 && $creatorUserId !== (int) $card->user_id) {
            return false;
        }

        $scopeType = (string) $rule->scope_type;
        $scopeId = (int) ($rule->scope_id ?? 0);
        if ($scopeType === MaterialShareRule::SCOPE_ALL) {
            return true;
        }
        if ($scopeType === MaterialShareRule::SCOPE_MATERIAL) {
            return $scopeId > 0 && $scopeId === $materialId;
        }
        if ($scopeType === MaterialShareRule::SCOPE_SUBJECT) {
            return $scopeId > 0
                && MaterialCardClassification::query()->where('material_card_id', $materialId)->where('subject_id', $scopeId)->exists();
        }
        if ($scopeType === MaterialShareRule::SCOPE_TOPIC) {
            return $scopeId > 0
                && MaterialCardClassification::query()->where('material_card_id', $materialId)->where('topic_id', $scopeId)->exists();
        }
        if ($scopeType === MaterialShareRule::SCOPE_UNIT) {
            return $scopeId > 0
                && MaterialCardClassification::query()->where('material_card_id', $materialId)->where('unit_id', $scopeId)->exists();
        }

        return false;
    }

    private function assertUnitBelongsToOwner(int $authUserId, MaterialUnit $unit): void
    {
        $unit->loadMissing('topic.subject');
        $ownerId = (int) ($unit->topic?->subject?->user_id ?? 0);
        if ($ownerId !== $authUserId) {
            abort(403, 'Sie dürfen nur eigene Einheiten verwalten.');
        }
    }

    private function assertTopicBelongsToOwner(int $authUserId, MaterialTopic $topic): void
    {
        $topic->loadMissing('subject');
        $ownerId = (int) ($topic->subject?->user_id ?? 0);
        if ($ownerId !== $authUserId) {
            abort(403, 'Sie dürfen nur eigene Themen verwalten.');
        }
    }

    private function linkedPermissionForCard(MaterialService $service, User $authUser, MaterialCard $card): ?string
    {
        $permission = trim((string) ($service->linkedPermissionForCard($authUser, $card) ?? ''));
        if (! in_array($permission, MaterialShareTarget::PERMISSIONS, true)) {
            return null;
        }

        return $permission;
    }

    private function assertLinkedCardAllowsEdit(MaterialService $service, User $authUser, MaterialCard $card): void
    {
        $permission = $this->linkedPermissionForCard($service, $authUser, $card);
        if ($permission !== null && ! in_array($permission, [MaterialShareTarget::PERMISSION_READ_WRITE, MaterialShareTarget::PERMISSION_FULL_ACCESS], true)) {
            abort(403, 'Dieses verlinkte Material ist auf NUR LESEN gesetzt.');
        }
    }

    private function assertLinkedCardAllowsAttachmentAppend(MaterialService $service, User $authUser, MaterialCard $card): void
    {
        $permission = $this->linkedPermissionForCard($service, $authUser, $card);
        if ($permission === MaterialShareTarget::PERMISSION_READ_ONLY) {
            abort(403, 'Bei verlinkten Materialien mit NUR LESEN können keine Anhänge hinzugefügt werden.');
        }
    }

    private function assertLinkedCardAllowsAttachmentDelete(MaterialService $service, User $authUser, MaterialCard $card): void
    {
        $permission = $this->linkedPermissionForCard($service, $authUser, $card);
        if ($permission !== null && $permission !== MaterialShareTarget::PERMISSION_FULL_ACCESS) {
            abort(403, 'Anhänge verlinkter Materialien dürfen nur mit VOLLZUGRIFF gelöscht werden.');
        }
    }

    private function assertLinkedCardAllowsMaterialDelete(MaterialService $service, User $authUser, MaterialCard $card): void
    {
        $permission = $this->linkedPermissionForCard($service, $authUser, $card);
        if ($permission !== null) {
            abort(403, 'Verlinkte Materialien können nicht gelöscht werden.');
        }
    }

    private function loadCardForResponse(MaterialCard $card): MaterialCard
    {
        if (Schema::hasTable('material_card_classifications')) {
            $relations = [
                'attachments',
                'classifications.subject',
                'classifications.topic',
                'classifications.unit',
            ];
            if (Schema::hasTable('material_inbox_imports')) {
                $relations[] = 'inboxImports';
            }

            return $card->loadMissing(...$relations);
        }

        $relations = ['attachments'];
        if (Schema::hasTable('material_inbox_imports')) {
            $relations[] = 'inboxImports';
        }

        return $card->loadMissing(...$relations);
    }

    private function isHtmlAttachment(MaterialCardAttachment $attachment): bool
    {
        if ($attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE) {
            return false;
        }

        $mimeType = strtolower(trim((string) ($attachment->mime_type ?? '')));
        if ($mimeType === 'text/html' || $mimeType === 'application/xhtml+xml') {
            return true;
        }

        $name = trim((string) ($attachment->name ?? ''));
        $filePath = trim((string) ($attachment->file_path ?? ''));
        $candidate = $name !== '' ? $name : $filePath;
        $extension = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));

        return $extension === 'html' || $extension === 'htm';
    }

    private function ensureRichTextStylesForHtmlDownload(string $html, string $fileName): string
    {
        $document = trim($html);
        if ($document === '') {
            $safeTitle = htmlspecialchars($fileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $document = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
                .$safeTitle.'</title></head><body></body></html>';
        }

        if (! mb_check_encoding($document, 'UTF-8')) {
            $document = mb_convert_encoding($document, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        if (! preg_match('/<html/i', $document)) {
            $safeTitle = htmlspecialchars($fileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $document = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
                .$safeTitle.'</title></head><body>'.$document.'</body></html>';
        }

        if (! preg_match('/<meta[^>]+charset=/i', $document)) {
            $document = preg_replace('/<head([^>]*)>/i', '<head$1><meta charset="utf-8">', $document, 1) ?? $document;
        }

        if (preg_match('/<style[^>]+id="materials-richtext-download-style"/i', $document)) {
            return $document;
        }

        $styleTag = $this->richTextDownloadStyleTag();
        if (preg_match('/<\/head>/i', $document)) {
            $document = preg_replace('/<\/head>/i', $styleTag.'</head>', $document, 1) ?? $document;

            return $document;
        }

        if (preg_match('/<head[^>]*>/i', $document)) {
            $document = preg_replace('/<head([^>]*)>/i', '<head$1>'.$styleTag, $document, 1) ?? $document;

            return $document;
        }

        return $document;
    }

    private function richTextDownloadStyleTag(): string
    {
        return '<style id="materials-richtext-download-style">'
            .'body{font-family:Arial,sans-serif;line-height:1.55;color:#1a2b3b;margin:14px;}'
            .'p{margin:0 0 .65rem 0;}'
            .'ul,ol{margin:.45rem 0 .75rem 0;padding-inline-start:1.4rem;}'
            .'li{margin:.2rem 0;}'
            .'blockquote{margin:.75rem 0;padding:.5rem .75rem;border-left:3px solid #fd802e;background:rgba(253,128,46,.10);border-radius:0 6px 6px 0;}'
            .'pre{background:#f5f7fb;border:1px solid #d9e1f3;border-radius:8px;padding:10px 12px;overflow:auto;}'
            .'code{background:#f5f7fb;border:1px solid #d9e1f3;border-radius:4px;padding:1px 4px;}'
            .'</style>';
    }

    private function extractHtmlBody(string $document): string
    {
        $match = [];
        if (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $document, $match)) {
            return trim((string) ($match[1] ?? ''));
        }

        return trim($document);
    }

    private function normalizeHtmlFragmentForDocx(string $html): string
    {
        $fragment = trim($html);
        if ($fragment === '') {
            return '<p></p>';
        }

        if (! mb_check_encoding($fragment, 'UTF-8')) {
            $fragment = mb_convert_encoding($fragment, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        }

        $fragment = $this->normalizeBlockquotesForDocx($fragment);
        $wrappedHtml = '<!doctype html><html><head><meta charset="UTF-8"></head><body>'.$fragment.'</body></html>';
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);

        try {
            $flags = 0;
            if (defined('LIBXML_HTML_NOIMPLIED')) {
                $flags |= LIBXML_HTML_NOIMPLIED;
            }
            if (defined('LIBXML_HTML_NODEFDTD')) {
                $flags |= LIBXML_HTML_NODEFDTD;
            }
            if (defined('LIBXML_NONET')) {
                $flags |= LIBXML_NONET;
            }

            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$wrappedHtml, $flags);
            if (! $loaded) {
                return $fragment;
            }

            $bodyNodes = $dom->getElementsByTagName('body');
            if ($bodyNodes->length === 0) {
                return $fragment;
            }

            $body = $bodyNodes->item(0);
            if (! $body) {
                return $fragment;
            }

            $normalized = '';
            foreach ($body->childNodes as $childNode) {
                $normalized .= (string) ($dom->saveXML($childNode) ?: '');
            }

            return trim($normalized) !== '' ? trim($normalized) : '<p></p>';
        } catch (\Throwable $error) {
            return $fragment;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);
        }
    }

    private function normalizeBlockquotesForDocx(string $html): string
    {
        $quoteTableStyle = $this->docxBlockquoteTableStyle();
        $quoteCellStyle = $this->docxBlockquoteCellStyle();

        $normalized = preg_replace_callback(
            '/<blockquote\b[^>]*>([\s\S]*?)<\/blockquote>/iu',
            static function (array $match) use ($quoteTableStyle, $quoteCellStyle): string {
                $inner = trim((string) ($match[1] ?? ''));
                if ($inner === '') {
                    return '';
                }

                $mergedParagraphs = preg_replace('/<\/p>\s*<p\b[^>]*>/iu', '<br/><br/>', $inner) ?? $inner;
                $mergedParagraphs = preg_replace('/^\s*<p\b[^>]*>/iu', '', $mergedParagraphs) ?? $mergedParagraphs;
                $mergedParagraphs = preg_replace('/<\/p>\s*$/iu', '', $mergedParagraphs) ?? $mergedParagraphs;

                return '<table style="'.$quoteTableStyle.'"><tr><td style="'.$quoteCellStyle.'">'
                    .trim($mergedParagraphs)
                    .'</td></tr></table>';
            },
            $html
        );

        return $normalized ?? $html;
    }

    private function docxBlockquoteTableStyle(): string
    {
        return 'width:100%; margin-top:10px; margin-bottom:10px;';
    }

    private function docxBlockquoteCellStyle(): string
    {
        return 'border-left:3px #FD802E solid; background-color:#FFF2E8; padding:8px 18px; font-style:italic; color:#5A3A12;';
    }

    private function plainTextFromHtmlForDocx(string $html): string
    {
        $normalized = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $normalized = preg_replace('/<\/(p|div|li|h[1-6]|blockquote)>/i', "$0\n", $normalized) ?? $normalized;

        $text = strip_tags($normalized);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace("/\r\n|\r/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        $text = trim((string) $text);

        return $text;
    }

    private function addPlainTextToDocxSection(Section $section, string $text): void
    {
        $normalizedText = trim($text);
        if ($normalizedText === '') {
            $section->addText(' ', ['size' => 12]);

            return;
        }

        $lines = preg_split('/\n/u', $normalizedText) ?: [];
        $wroteText = false;

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $section->addTextBreak();
            }

            $segment = trim((string) $line);
            if ($segment === '') {
                continue;
            }

            $section->addText($segment, ['size' => 12]);
            $wroteText = true;
        }

        if (! $wroteText) {
            $section->addText($normalizedText, ['size' => 12]);
        }
    }

    private function newDocxDocument(): PhpWord
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontSize(12);

        $docLocale = $this->docxLanguageFromLaravelLocale();
        $phpWord->getSettings()->setThemeFontLang(new Language($docLocale));

        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 24], ['spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 20], ['spaceAfter' => 220]);
        $phpWord->addTitleStyle(3, ['bold' => true, 'size' => 16], ['spaceAfter' => 200]);
        $phpWord->addTitleStyle(4, ['bold' => true, 'size' => 14], ['spaceAfter' => 180]);
        $phpWord->addTitleStyle(5, ['bold' => true, 'size' => 13], ['spaceAfter' => 160]);
        $phpWord->addTitleStyle(6, ['bold' => true, 'size' => 12], ['spaceAfter' => 140]);

        return $phpWord;
    }

    private function docxLanguageFromLaravelLocale(): string
    {
        $raw = trim((string) (app()->getLocale() ?: config('app.locale', 'en')));
        if ($raw === '') {
            return 'en-US';
        }

        $normalized = str_replace('_', '-', $raw);
        if (preg_match('/^[a-z]{2}$/i', $normalized) === 1) {
            return strtolower($normalized).'-'.strtoupper($normalized);
        }

        $parts = array_values(array_filter(explode('-', $normalized), static fn ($part) => $part !== ''));
        if (count($parts) >= 2) {
            $language = strtolower((string) $parts[0]);
            $region = strtoupper((string) $parts[1]);

            if (preg_match('/^[a-z]{2}$/', $language) === 1 && preg_match('/^[A-Z0-9]{2,4}$/', $region) === 1) {
                return $language.'-'.$region;
            }
        }

        return 'en-US';
    }

    private function docxDownloadName(string $name): string
    {
        $base = trim($name);
        if ($base === '') {
            return 'Text.docx';
        }

        $withoutHtml = preg_replace('/\.(html?|HTML?)$/', '', $base) ?? $base;
        $withoutTrailingDot = rtrim($withoutHtml, ". \t\n\r\0\x0B");
        if ($withoutTrailingDot === '') {
            return 'Text.docx';
        }

        return mb_substr($withoutTrailingDot, 0, 240).'.docx';
    }

    private function safeAttachmentDownloadName(string $name): string
    {
        $value = trim($name);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = str_replace(['/', '\\'], '_', $value);
        $value = trim($value, " .\t\n\r\0\x0B");

        if ($value === '') {
            return 'Datei';
        }

        return mb_substr($value, 0, 240);
    }

    private function attachmentDownloadContentType(
        MaterialCardAttachment $attachment,
        Filesystem $disk,
        string $relativePath
    ): string {
        $storedMimeType = trim((string) ($attachment->mime_type ?? ''));
        if ($this->isSafeHeaderValue($storedMimeType)) {
            return $storedMimeType;
        }

        $detectedMimeType = $disk->mimeType($relativePath);
        $detectedMimeType = is_string($detectedMimeType) ? trim($detectedMimeType) : '';

        if ($this->isSafeHeaderValue($detectedMimeType)) {
            return $detectedMimeType;
        }

        return 'application/octet-stream';
    }

    private function isSafeHeaderValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return ! preg_match('/[\r\n\x00]/', $value);
    }
}
