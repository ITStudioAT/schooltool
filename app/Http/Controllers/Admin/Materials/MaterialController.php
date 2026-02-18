<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialCardAttachmentUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialCardFileAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardIndexRequest;
use App\Http\Requests\Admin\Materials\MaterialCardLinkAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardQuickStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardUpdateRequest;
use App\Http\Resources\Admin\Materials\MaterialCardAttachmentResource;
use App\Http\Resources\Admin\Materials\MaterialCardResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Services\Materials\MaterialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

    public function show(MaterialCard $material_card)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($material_card)), 200);
    }

    public function update(MaterialCardUpdateRequest $request, MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $validated = $request->validated()['data'];

        $card = $service->updateCard($material_card, $validated, $authUser);

        return response()->json(new MaterialCardResource($this->loadCardForResponse($card)), 200);
    }

    public function destroy(MaterialCard $material_card, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);

        $service->deleteCard($material_card);

        return response()->noContent();
    }

    public function storeLinkAttachment(
        MaterialCardLinkAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $validated = $request->validated()['data'];

        $attachment = $service->addLinkAttachment(
            $material_card,
            $validated['url'],
            $validated['name'] ?? null
        );

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function storeFileAttachment(
        MaterialCardFileAttachmentStoreRequest $request,
        MaterialCard $material_card,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForMaterials();
        $this->assertIsOwner($authUser->id, $material_card->user_id);
        $validated = $request->validated();

        $attachment = $service->addFileAttachment(
            $material_card,
            $validated['file'],
            $validated['name'] ?? null
        );

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function destroyAttachment(MaterialCardAttachment $material_card_attachment, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);

        $service->deleteAttachment($material_card_attachment);

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
        $validated = $request->validated()['data'];

        $attachment = $service->updateAttachmentName($material_card_attachment, $validated['name']);

        return response()->json(new MaterialCardAttachmentResource($attachment), 200);
    }

    public function downloadAttachment(MaterialCardAttachment $material_card_attachment)
    {
        $authUser = $this->authorizeForMaterials();
        $material_card_attachment->loadMissing('materialCard');
        $this->assertIsOwner($authUser->id, (int) $material_card_attachment->materialCard->user_id);

        if ($material_card_attachment->attachment_type !== MaterialCardAttachment::TYPE_FILE || ! $material_card_attachment->file_path) {
            abort(404, 'Datei nicht gefunden');
        }

        if (! Storage::disk('local')->exists($material_card_attachment->file_path)) {
            abort(404, 'Datei nicht gefunden');
        }

        $name = $material_card_attachment->name ?: basename($material_card_attachment->file_path);

        return response()->download(Storage::disk('local')->path($material_card_attachment->file_path), $name);
    }

    private function authorizeForMaterials()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'teaching_admin', 'teacher'])) {
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

    private function loadCardForResponse(MaterialCard $card): MaterialCard
    {
        if (Schema::hasTable('material_card_classifications')) {
            return $card->loadMissing(
                'attachments',
                'classifications.subject',
                'classifications.topic',
                'classifications.unit'
            );
        }

        return $card->loadMissing('attachments');
    }
}
