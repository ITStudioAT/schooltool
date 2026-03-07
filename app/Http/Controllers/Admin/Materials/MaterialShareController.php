<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialCardAttachmentTextUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialCardAttachmentUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialCardFileAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardLinkAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardRemoteImageAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardTempAttachmentStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialCardUpdateRequest;
use App\Models\Import116;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialCardClassification;
use App\Models\MaterialInboxImport;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareRuleArchive;
use App\Models\MaterialShareTarget;
use App\Models\MaterialStatus;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialTopicInboxImport;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\MaterialUnitInboxImport;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Materials\MaterialKeywordService;
use App\Services\Materials\MaterialService;
use App\Services\Materials\MaterialWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaterialShareController extends Controller
{
    /** @var array<string,array<string,array{label:string,icon:string,color:?string}>> */
    private array $materialTypeMetaCache = [];

    /** @var array<int,array<string,array{label:string,color:string}>> */
    private array $materialStatusMetaCache = [];

    private ?bool $hasMaterialAttachmentsTableCache = null;

    private ?bool $hasMaterialTypesTableCache = null;

    private ?bool $hasMaterialStatusesTableCache = null;

    private ?bool $hasMaterialInboxImportsTableCache = null;

    private ?bool $materialInboxImportsHasImportModeColumnCache = null;

    private ?bool $hasMaterialUnitInboxImportsTableCache = null;

    private ?bool $hasMaterialTopicInboxImportsTableCache = null;

    private ?bool $hasMaterialShareRuleArchivesTableCache = null;

    private ?bool $materialTypesUserScopedCache = null;

    private ?bool $materialTypesHasIconColumnCache = null;

    private ?bool $materialTypesHasColorColumnCache = null;

    private ?bool $materialStatusesHasColorColumnCache = null;

    public function __construct(
        private readonly MaterialWorkspaceService $workspaceService,
    ) {}

    public function index(Request $request)
    {
        $authUser = $this->materialsShareUser();

        if (! $this->shareTablesAvailable()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'needs_migration' => true,
                    'total' => 0,
                ],
            ]);
        }

        $schoolId = (int) $authUser->school_id;
        $workspaceId = $this->activeWorkspaceIdForUser($authUser);
        $scopeTypeFilter = (string) $request->query('scope_type', '');
        $scopeIdFilter = $request->query('scope_id');
        $scopeIdFilter = number_format((float) $scopeIdFilter, 0, '', '') === (string) $scopeIdFilter
            ? (int) $scopeIdFilter
            : null;

        $rules = MaterialShareRule::query()
            ->where('school_id', $schoolId)
            ->when($workspaceId !== null, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->when($scopeTypeFilter !== '', fn ($query) => $query->where('scope_type', $scopeTypeFilter))
            ->when($scopeTypeFilter !== '' && $scopeIdFilter !== null, fn ($query) => $query->where('scope_id', $scopeIdFilter))
            ->with([
                'creator:id,first_name,last_name,email',
                'targets',
                'targets.group:id,school_id,type,name,created_by_user_id',
                'targets.user:id,school_id,first_name,last_name,email',
                'targets.user.selectedSchool:id,long_name,short_name',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rules->map(fn (MaterialShareRule $rule) => $this->serializeRule($rule, $schoolId))->values(),
            'meta' => [
                'needs_migration' => false,
                'total' => $rules->count(),
                'active_count' => $rules->where('is_active', true)->count(),
            ],
        ]);
    }

    public function inboxUsers()
    {
        $authUser = $this->materialsShareUser();

        if (! $this->shareTablesAvailable()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'needs_migration' => true,
                    'total' => 0,
                ],
            ]);
        }

        $schoolId = (int) $authUser->school_id;
        $authUserId = (int) $authUser->id;

        $memberGroupIds = UserGroup::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $authUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $matchingRules = MaterialShareRule::query()
            ->where('is_active', true)
            ->whereNotNull('created_by_user_id')
            ->where('created_by_user_id', '!=', $authUserId)
            ->where(function ($query) use ($authUserId, $schoolId, $memberGroupIds) {
                $query->whereHas('targets', function ($targetQuery) use ($authUserId) {
                    $targetQuery
                        ->where('target_type', MaterialShareTarget::TARGET_USER)
                        ->where('user_id', $authUserId);
                })->orWhereHas('targets', function ($targetQuery) {
                    $targetQuery
                        ->where('target_type', MaterialShareTarget::TARGET_EVERYONE)
                        ->where('audience_scope', MaterialShareTarget::AUDIENCE_SCOPE_GLOBAL);
                })->orWhere(function ($schoolWideQuery) use ($schoolId) {
                    $schoolWideQuery
                        ->where('school_id', $schoolId)
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
            ->with([
                'creator:id,school_id,first_name,last_name,email',
                'creator.selectedSchool:id,long_name,short_name',
                'targets:id,material_share_rule_id,target_type,audience_scope,permission,user_group_id,user_id',
            ])
            ->orderByDesc('updated_at')
            ->get(['id', 'school_id', 'workspace_id', 'scope_type', 'scope_id', 'created_by_user_id', 'updated_at']);

        $importedMaterialKeys = $this->resolveImportedInboxMaterialKeys($authUserId);
        $importedRuleIds = $this->resolveImportedInboxRuleIds($authUserId);
        $archivedRuleIds = $this->resolveArchivedInboxRuleIds($authUserId);

        $users = $matchingRules
            ->groupBy(fn (MaterialShareRule $rule) => (int) $rule->created_by_user_id)
            ->map(function ($creatorRules) use ($authUserId, $schoolId, $memberGroupIds, $importedMaterialKeys, $importedRuleIds, $archivedRuleIds) {
                $firstRule = $creatorRules->first();
                $creator = $firstRule?->creator;
                if (! $creator) {
                    return null;
                }

                $name = trim((string) (($creator->last_name ?? '').' '.($creator->first_name ?? '')));
                $schoolLabel = trim((string) ($creator->selectedSchool?->long_name ?: $creator->selectedSchool?->short_name ?: ''));
                $lastSharedAt = $creatorRules
                    ->sortByDesc(fn (MaterialShareRule $rule) => optional($rule->updated_at)?->getTimestamp() ?? 0)
                    ->first()?->updated_at;
                $sharedItems = $creatorRules
                    ->sortByDesc(fn (MaterialShareRule $rule) => optional($rule->updated_at)?->getTimestamp() ?? 0)
                    ->map(function (MaterialShareRule $rule) use ($authUserId, $schoolId, $memberGroupIds, $importedMaterialKeys, $importedRuleIds, $archivedRuleIds) {
                        $permission = $this->resolveRulePermissionForUser($rule, $authUserId, $schoolId, $memberGroupIds);
                        [$scopeLabel, $scopeObjectLabel] = $this->resolveScopeLabels($rule, (int) $rule->school_id);
                        $scopeLabel = (string) $rule->scope_type === MaterialShareRule::SCOPE_ALL ? 'Workspace' : $scopeLabel;
                        $ruleId = (int) $rule->id;

                        return [
                            'rule_id' => $ruleId,
                            'scope_type' => (string) $rule->scope_type,
                            'scope_label' => $scopeLabel,
                            'scope_object_label' => $scopeObjectLabel,
                            'scope_path_label' => $this->resolveScopePathLabel($rule),
                            'permission' => $permission,
                            'permission_label' => mb_strtoupper($this->permissionLabel($permission)),
                            'is_imported' => $this->isRuleMaterialImported($rule, $importedMaterialKeys, $importedRuleIds),
                            'is_archived' => isset($archivedRuleIds[$ruleId]),
                            'hierarchy' => $this->resolveScopeHierarchy($rule),
                            'updated_at' => optional($rule->updated_at)?->toIso8601String(),
                        ];
                    })
                    ->values();

                return [
                    'id' => (int) $creator->id,
                    'label' => $name !== '' ? $name : ($creator->email ?: 'Benutzer'),
                    'first_name' => (string) ($creator->first_name ?? ''),
                    'last_name' => (string) ($creator->last_name ?? ''),
                    'email' => (string) ($creator->email ?? ''),
                    'school_label' => $schoolLabel !== '' ? $schoolLabel : null,
                    'shared_rules_count' => (int) $creatorRules->count(),
                    'shared_items' => $sharedItems,
                    'last_shared_at' => $lastSharedAt ? $lastSharedAt->toIso8601String() : null,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row) => strtotime((string) ($row['last_shared_at'] ?? '')) ?: 0)
            ->values();

        return response()->json([
            'data' => $users,
            'meta' => [
                'needs_migration' => false,
                'total' => $users->count(),
            ],
        ]);
    }

    public function inboxMaterialAttachments(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];

        $attachments = $sourceCard->attachments instanceof Collection
            ? $sourceCard->attachments
            : collect();

        $serialized = $attachments
            ->map(fn (MaterialCardAttachment $attachment) => $this->serializeInboxAttachment($attachment, $ruleId, $materialId))
            ->values();

        return response()->json([
            'data' => $serialized,
        ], 200);
    }

    public function inboxMaterialDetail(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        $permission = (string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY);

        return response()->json([
            'data' => $this->serializeInboxMaterialDetail($sourceCard, $ruleId, $permission),
        ], 200);
    }

    public function updateInboxMaterialDetail(MaterialCardUpdateRequest $request, MaterialService $service)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        $permission = (string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY);
        $this->assertInboxPermissionAllowsEdit($permission);

        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        $validated = $request->validated()['data'];
        $validated['classifications'] = $this->currentMaterialClassificationPayload($sourceCard);

        $sourceOwner = $sourceCard->user()->first();
        $updatedCard = $service->updateCard(
            $sourceCard,
            $validated,
            $sourceOwner instanceof User ? $sourceOwner : null
        );

        return response()->json([
            'data' => $this->serializeInboxMaterialDetail($updatedCard, $ruleId, $permission),
        ], 200);
    }

    public function storeInboxLinkAttachment(MaterialCardLinkAttachmentStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        $this->assertInboxPermissionAllowsAttachmentAppend((string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY));

        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        $validated = $request->validated()['data'];
        $attachment = $service->addLinkAttachment(
            $sourceCard,
            (string) ($validated['url'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );

        return response()->json([
            'data' => $this->serializeInboxAttachment($attachment->fresh(), $ruleId, $materialId),
        ], 200);
    }

    public function storeInboxRemoteImageAttachment(MaterialCardRemoteImageAttachmentStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        $this->assertInboxPermissionAllowsAttachmentAppend((string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY));

        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        $validated = $request->validated()['data'];
        $attachment = $service->addImageAttachmentFromUrl(
            $sourceCard,
            (string) ($validated['url'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );

        return response()->json([
            'data' => $this->serializeInboxAttachment($attachment->fresh(), $ruleId, $materialId),
        ], 200);
    }

    public function storeInboxFileAttachment(MaterialCardFileAttachmentStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        $this->assertInboxPermissionAllowsAttachmentAppend((string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY));

        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        $attachment = $service->addFileAttachment(
            $sourceCard,
            $request->file('file'),
            $request->input('name')
        );

        return response()->json([
            'data' => $this->serializeInboxAttachment($attachment->fresh(), $ruleId, $materialId),
        ], 200);
    }

    public function storeInboxTempFileAttachment(MaterialCardTempAttachmentStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        $this->assertInboxPermissionAllowsAttachmentAppend((string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY));

        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        $validated = $request->validated()['data'];
        $attachment = $service->addFileAttachmentFromTempUpload(
            $authUser,
            $sourceCard,
            (string) ($validated['upload_id'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );

        return response()->json([
            'data' => $this->serializeInboxAttachment($attachment->fresh(), $ruleId, $materialId),
        ], 200);
    }

    public function updateInboxAttachment(
        MaterialCardAttachmentUpdateRequest $request,
        MaterialCardAttachment $material_card_attachment,
        MaterialService $service
    ) {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        $this->assertInboxPermissionAllowsEdit((string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY));

        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        if ((int) ($material_card_attachment->material_card_id ?? 0) !== (int) $sourceCard->id) {
            abort(404, 'Anhang wurde nicht gefunden.');
        }

        $validated = $request->validated()['data'];
        $attachment = $service->updateAttachmentName(
            $material_card_attachment,
            (string) ($validated['name'] ?? '')
        );

        return response()->json([
            'data' => $this->serializeInboxAttachment($attachment->fresh(), $ruleId, $materialId),
        ], 200);
    }

    public function inboxTextAttachmentContent(
        Request $request,
        MaterialCardAttachment $material_card_attachment,
        MaterialService $service
    ) {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        $this->assertInboxPermissionAllowsEdit((string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY));

        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        if ((int) ($material_card_attachment->material_card_id ?? 0) !== (int) $sourceCard->id) {
            abort(404, 'Anhang wurde nicht gefunden.');
        }

        $contentHtml = $service->readEditableTextAttachmentContent($material_card_attachment);

        return response()->json([
            'data' => [
                'id' => (int) $material_card_attachment->id,
                'name' => (string) ($material_card_attachment->name ?? ''),
                'content_html' => $contentHtml,
            ],
        ], 200);
    }

    public function updateInboxTextAttachmentContent(
        MaterialCardAttachmentTextUpdateRequest $request,
        MaterialCardAttachment $material_card_attachment,
        MaterialService $service
    ) {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $context = $this->resolveInboxAccessContext($authUser, $ruleId, $materialId);
        $this->assertInboxPermissionAllowsEdit((string) ($context['permission'] ?? MaterialShareTarget::PERMISSION_READ_ONLY));

        /** @var MaterialCard $sourceCard */
        $sourceCard = $context['source_card'];
        if ((int) ($material_card_attachment->material_card_id ?? 0) !== (int) $sourceCard->id) {
            abort(404, 'Anhang wurde nicht gefunden.');
        }

        $validated = $request->validated()['data'];
        $attachment = $service->updateEditableTextAttachmentContent(
            $material_card_attachment,
            (string) ($validated['content_html'] ?? ''),
            isset($validated['name']) ? (string) $validated['name'] : null
        );

        return response()->json([
            'data' => $this->serializeInboxAttachment($attachment->fresh(), $ruleId, $materialId),
        ], 200);
    }

    public function archiveInboxRule(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        if (! $this->hasMaterialShareRuleArchivesTable()) {
            abort(409, 'Archiv-Funktion ist erst nach aktueller Migration verfügbar.');
        }

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $authUserId = (int) $authUser->id;
        $authSchoolId = (int) $authUser->school_id;

        $memberGroupIds = UserGroup::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $authUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $rule = $this->resolveAccessibleInboxRule($ruleId, $authUserId, $authSchoolId, $memberGroupIds);
        if (! $rule) {
            abort(404, 'Freigabe wurde nicht gefunden.');
        }

        MaterialShareRuleArchive::query()->updateOrCreate(
            [
                'target_user_id' => $authUserId,
                'material_share_rule_id' => $ruleId,
            ],
            [
                'archived_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Freigabe archiviert.',
            'data' => [
                'rule_id' => $ruleId,
                'is_archived' => true,
            ],
        ]);
    }

    public function unarchiveInboxRule(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        if (! $this->hasMaterialShareRuleArchivesTable()) {
            abort(409, 'Archiv-Funktion ist erst nach aktueller Migration verfügbar.');
        }

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $authUserId = (int) $authUser->id;
        $authSchoolId = (int) $authUser->school_id;

        $memberGroupIds = UserGroup::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $authUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $rule = $this->resolveAccessibleInboxRule($ruleId, $authUserId, $authSchoolId, $memberGroupIds);
        if (! $rule) {
            abort(404, 'Freigabe wurde nicht gefunden.');
        }

        MaterialShareRuleArchive::query()->updateOrCreate(
            [
                'target_user_id' => $authUserId,
                'material_share_rule_id' => $ruleId,
            ],
            [
                'archived_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Freigabe wurde zurück in den Posteingang verschoben.',
            'data' => [
                'rule_id' => $ruleId,
                'is_archived' => false,
            ],
        ]);
    }

    public function copyInboxMaterialAsOriginal(
        Request $request,
        MaterialService $materialService,
        MaterialKeywordService $keywordService,
    ) {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $authUserId = (int) $authUser->id;
        $authSchoolId = (int) $authUser->school_id;

        $memberGroupIds = UserGroup::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $authUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $rule = $this->resolveAccessibleInboxRule($ruleId, $authUserId, $authSchoolId, $memberGroupIds);
        if (! $rule) {
            abort(404, 'Freigabe wurde nicht gefunden.');
        }

        $sourceCard = $this->resolveInboxSourceCardForRule($rule, $materialId);
        if (! $sourceCard) {
            throw ValidationException::withMessages([
                'material_id' => ['Geteiltes Material wurde nicht gefunden oder gehört nicht zur Freigabe.'],
            ]);
        }

        $sourceTypeMeta = $this->resolveMaterialTypeMeta(
            schoolId: (int) ($sourceCard->school_id ?? 0),
            userId: (int) ($sourceCard->user_id ?? 0),
            typeValue: trim((string) ($sourceCard->type ?? '')),
        );
        $sourceStatusMeta = $this->resolveMaterialStatusMeta(
            schoolId: (int) ($sourceCard->school_id ?? 0),
            statusValue: trim((string) ($sourceCard->status ?? MaterialCard::STATUS_INBOX)),
        );

        $newCard = DB::transaction(function () use (
            $authUser,
            $sourceCard,
            $sourceTypeMeta,
            $sourceStatusMeta,
            $materialService,
            $keywordService,
            $rule,
            $ruleId,
        ) {
            $targetType = $this->ensureTargetMaterialType(
                targetUser: $authUser,
                sourceType: trim((string) ($sourceCard->type ?? '')),
                sourceTypeMeta: $sourceTypeMeta,
            );
            $targetStatus = $this->ensureTargetMaterialStatus(
                targetUser: $authUser,
                sourceStatus: trim((string) ($sourceCard->status ?? MaterialCard::STATUS_INBOX)),
                sourceStatusMeta: $sourceStatusMeta,
            );

            $classifications = $this->sourceClassificationsForOriginalInsert($sourceCard);

            $createdCard = $materialService->createCard($authUser, [
                'title' => trim((string) ($sourceCard->title ?? '')) !== '' ? (string) $sourceCard->title : 'Material',
                'source_url' => $sourceCard->source_url,
                'source_text' => $sourceCard->source_text,
                'type' => $targetType,
                'status' => $targetStatus,
                'notes' => $sourceCard->notes,
                'classifications' => $classifications,
            ]);

            $this->cloneSourceAttachmentsToCard($sourceCard, $createdCard);
            $keywordService->rebuild($createdCard->fresh());

            if ($this->hasMaterialInboxImportsTable()) {
                $importPayload = [
                    'source_rule_id' => $ruleId,
                    'source_school_id' => (int) $rule->school_id,
                    'source_material_id' => (int) $sourceCard->id,
                    'imported_at' => now(),
                ];
                if ($this->materialInboxImportsHasImportModeColumn()) {
                    $importPayload['import_mode'] = MaterialInboxImport::MODE_COPY;
                }
                MaterialInboxImport::query()->updateOrCreate(
                    [
                        'target_user_id' => (int) $authUser->id,
                        'target_material_card_id' => (int) $createdCard->id,
                    ],
                    $importPayload
                );
            }

            return $createdCard->fresh([
                'attachments',
                'classifications.subject',
                'classifications.topic',
                'classifications.unit',
            ]);
        });

        return response()->json([
            'message' => 'Material als Original eingefügt.',
            'data' => [
                'id' => (int) $newCard->id,
                'title' => (string) ($newCard->title ?? ''),
                'attachments_count' => (int) $newCard->attachments->count(),
            ],
        ]);
    }

    public function insertInboxMaterial(
        Request $request,
        MaterialService $materialService,
        MaterialKeywordService $keywordService,
    ) {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $data = $request->validate([
            'rule_id' => ['required', 'integer', 'min:1'],
            'material_id' => ['required', 'integer', 'min:1'],
            'target_level' => ['required', 'string', Rule::in(['subject', 'topic', 'unit'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'import_mode' => ['nullable', 'string', Rule::in([MaterialInboxImport::MODE_COPY, MaterialInboxImport::MODE_LINK])],
            'source_unit_id' => ['nullable', 'integer', 'min:1'],
            'source_topic_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $ruleId = (int) ($data['rule_id'] ?? 0);
        $materialId = (int) ($data['material_id'] ?? 0);
        $targetLevel = trim((string) ($data['target_level'] ?? ''));
        $targetId = (int) ($data['target_id'] ?? 0);
        $importMode = trim((string) ($data['import_mode'] ?? MaterialInboxImport::MODE_COPY));
        $requestedSourceUnitId = (int) ($data['source_unit_id'] ?? 0);
        $requestedSourceTopicId = (int) ($data['source_topic_id'] ?? 0);
        if (! in_array($importMode, [MaterialInboxImport::MODE_COPY, MaterialInboxImport::MODE_LINK], true)) {
            $importMode = MaterialInboxImport::MODE_COPY;
        }
        if ($importMode === MaterialInboxImport::MODE_LINK && ! $this->materialInboxImportsHasImportModeColumn()) {
            abort(409, 'Link-Modus erfordert eine aktuelle Migration der Inbox-Imports.');
        }
        if ($importMode === MaterialInboxImport::MODE_LINK && $targetLevel === 'unit' && ! $this->hasMaterialUnitInboxImportsTable()) {
            abort(409, 'Link-Modus für Einheiten erfordert eine aktuelle Migration.');
        }
        if ($importMode === MaterialInboxImport::MODE_LINK && $requestedSourceTopicId > 0 && ! $this->hasMaterialTopicInboxImportsTable()) {
            abort(409, 'Link-Modus für Themen erfordert eine aktuelle Migration.');
        }
        $authUserId = (int) $authUser->id;
        $authSchoolId = (int) $authUser->school_id;

        $memberGroupIds = UserGroup::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $authUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $rule = $this->resolveAccessibleInboxRule($ruleId, $authUserId, $authSchoolId, $memberGroupIds);
        if (! $rule) {
            abort(404, 'Freigabe wurde nicht gefunden.');
        }

        $sourceCard = $this->resolveInboxSourceCardForRule($rule, $materialId);
        if (! $sourceCard) {
            throw ValidationException::withMessages([
                'material_id' => ['Geteiltes Material wurde nicht gefunden oder gehört nicht zur Freigabe.'],
            ]);
        }

        $targetClassification = $this->resolveTargetClassificationForInsert(
            user: $authUser,
            targetLevel: $targetLevel,
            targetId: $targetId,
        );
        if (! $targetClassification) {
            throw ValidationException::withMessages([
                'target_id' => ['Ziel für Einfächern wurde nicht gefunden.'],
            ]);
        }

        $sourceTypeMeta = $this->resolveMaterialTypeMeta(
            schoolId: (int) ($sourceCard->school_id ?? 0),
            userId: (int) ($sourceCard->user_id ?? 0),
            typeValue: trim((string) ($sourceCard->type ?? '')),
        );
        $sourceStatusMeta = $this->resolveMaterialStatusMeta(
            schoolId: (int) ($sourceCard->school_id ?? 0),
            statusValue: trim((string) ($sourceCard->status ?? MaterialCard::STATUS_INBOX)),
        );
        $sourceUnitIdForUnitImport = $this->resolveSourceUnitIdForInsert(
            rule: $rule,
            sourceCard: $sourceCard,
            requestedSourceUnitId: $requestedSourceUnitId,
        );
        $sourceTopicIdForTopicImport = $this->resolveSourceTopicIdForInsert(
            rule: $rule,
            sourceCard: $sourceCard,
            requestedSourceTopicId: $requestedSourceTopicId,
        );
        $targetTopicIdForTopicImport = (int) ($targetClassification['topic_id'] ?? 0);

        $newCard = DB::transaction(function () use (
            $authUser,
            $sourceCard,
            $sourceTypeMeta,
            $sourceStatusMeta,
            $materialService,
            $keywordService,
            $rule,
            $ruleId,
            $targetClassification,
            $importMode,
            $targetLevel,
            $targetId,
            $requestedSourceUnitId,
            $requestedSourceTopicId,
            $sourceUnitIdForUnitImport,
            $sourceTopicIdForTopicImport,
            $targetTopicIdForTopicImport,
        ) {
            $targetType = $this->ensureTargetMaterialType(
                targetUser: $authUser,
                sourceType: trim((string) ($sourceCard->type ?? '')),
                sourceTypeMeta: $sourceTypeMeta,
            );
            $targetStatus = $this->ensureTargetMaterialStatus(
                targetUser: $authUser,
                sourceStatus: trim((string) ($sourceCard->status ?? MaterialCard::STATUS_INBOX)),
                sourceStatusMeta: $sourceStatusMeta,
            );

            $createdCard = $materialService->createCard($authUser, [
                'title' => trim((string) ($sourceCard->title ?? '')) !== '' ? (string) $sourceCard->title : 'Material',
                'source_url' => $sourceCard->source_url,
                'source_text' => $sourceCard->source_text,
                'type' => $targetType,
                'status' => $targetStatus,
                'notes' => $sourceCard->notes,
                'classifications' => [$targetClassification],
            ]);
            $this->syncCardClassificationToResolvedTarget($createdCard, $targetClassification);

            $this->cloneSourceAttachmentsToCard($sourceCard, $createdCard);
            $keywordService->rebuild($createdCard->fresh());

            if ($this->hasMaterialInboxImportsTable()) {
                $importPayload = [
                    'source_rule_id' => $ruleId,
                    'source_school_id' => (int) $rule->school_id,
                    'source_material_id' => (int) $sourceCard->id,
                    'imported_at' => now(),
                ];
                if ($this->materialInboxImportsHasImportModeColumn()) {
                    $importPayload['import_mode'] = $importMode;
                }
                MaterialInboxImport::query()->updateOrCreate(
                    [
                        'target_user_id' => (int) $authUser->id,
                        'target_material_card_id' => (int) $createdCard->id,
                    ],
                    $importPayload
                );
            }

            if (
                $importMode === MaterialInboxImport::MODE_LINK
                && $targetLevel === 'unit'
                && $targetId > 0
                && $requestedSourceUnitId > 0
                && $sourceUnitIdForUnitImport > 0
                && $this->hasMaterialUnitInboxImportsTable()
            ) {
                MaterialUnitInboxImport::query()->updateOrCreate(
                    [
                        'target_user_id' => (int) $authUser->id,
                        'target_unit_id' => $targetId,
                    ],
                    [
                        'source_rule_id' => $ruleId,
                        'source_school_id' => (int) $rule->school_id,
                        'source_unit_id' => $sourceUnitIdForUnitImport,
                        'imported_at' => now(),
                    ],
                );
            }

            if (
                $importMode === MaterialInboxImport::MODE_LINK
                && $targetTopicIdForTopicImport > 0
                && $requestedSourceTopicId > 0
                && $sourceTopicIdForTopicImport > 0
                && $this->hasMaterialTopicInboxImportsTable()
            ) {
                MaterialTopicInboxImport::query()->updateOrCreate(
                    [
                        'target_user_id' => (int) $authUser->id,
                        'target_topic_id' => $targetTopicIdForTopicImport,
                    ],
                    [
                        'source_rule_id' => $ruleId,
                        'source_school_id' => (int) $rule->school_id,
                        'source_topic_id' => $sourceTopicIdForTopicImport,
                        'imported_at' => now(),
                    ],
                );
            }

            return $createdCard->fresh([
                'attachments',
                'classifications.subject',
                'classifications.topic',
                'classifications.unit',
            ]);
        });

        return response()->json([
            'message' => $importMode === MaterialInboxImport::MODE_LINK
                ? 'Material als Link eingefächert.'
                : 'Material eingefächert.',
            'data' => [
                'id' => (int) $newCard->id,
                'title' => (string) ($newCard->title ?? ''),
                'attachments_count' => (int) $newCard->attachments->count(),
            ],
        ]);
    }

    private function resolveTargetClassificationForInsert(User $user, string $targetLevel, int $targetId): ?array
    {
        if ($targetId <= 0) {
            return null;
        }

        if ($targetLevel === 'subject') {
            $subject = MaterialSubject::query()
                ->where('user_id', (int) $user->id)
                ->find($targetId);

            if (! $subject) {
                return null;
            }

            $subjectName = trim((string) ($subject->name ?? ''));
            if ($subjectName === '') {
                return null;
            }

            return [
                'subject_id' => (int) $subject->id,
                'topic_id' => null,
                'unit_id' => null,
                'subject' => $subjectName,
                'topic' => '',
                'unit' => '',
            ];
        }

        if ($targetLevel === 'topic') {
            $topic = MaterialTopic::query()
                ->with('subject:id,user_id,name')
                ->find($targetId);

            if (! $topic || (int) ($topic->subject?->user_id ?? 0) !== (int) $user->id) {
                return null;
            }

            $subjectName = trim((string) ($topic->subject?->name ?? ''));
            $topicName = trim((string) ($topic->name ?? ''));
            if ($subjectName === '' || $topicName === '') {
                return null;
            }

            return [
                'subject_id' => (int) ($topic->subject?->id ?? 0),
                'topic_id' => (int) $topic->id,
                'unit_id' => null,
                'subject' => $subjectName,
                'topic' => $topicName,
                'unit' => '',
            ];
        }

        if ($targetLevel === 'unit') {
            $unit = MaterialUnit::query()
                ->with('topic.subject:id,user_id,name')
                ->find($targetId);

            if (! $unit || (int) ($unit->topic?->subject?->user_id ?? 0) !== (int) $user->id) {
                return null;
            }

            $subjectName = trim((string) ($unit->topic?->subject?->name ?? ''));
            $topicName = trim((string) ($unit->topic?->name ?? ''));
            $unitName = trim((string) ($unit->name ?? ''));
            if ($subjectName === '' || $topicName === '' || $unitName === '') {
                return null;
            }

            return [
                'subject_id' => (int) ($unit->topic?->subject?->id ?? 0),
                'topic_id' => (int) ($unit->topic?->id ?? 0),
                'unit_id' => (int) $unit->id,
                'subject' => $subjectName,
                'topic' => $topicName,
                'unit' => $unitName,
            ];
        }

        return null;
    }

    private function syncCardClassificationToResolvedTarget(MaterialCard $card, array $targetClassification): void
    {
        if (! Schema::hasTable('material_card_classifications')) {
            return;
        }

        $subjectId = (int) ($targetClassification['subject_id'] ?? 0);
        if ($subjectId <= 0) {
            return;
        }

        $topicId = (int) ($targetClassification['topic_id'] ?? 0);
        $unitId = (int) ($targetClassification['unit_id'] ?? 0);
        if ($topicId <= 0) {
            $topicId = 0;
            $unitId = 0;
        }
        if ($unitId <= 0) {
            $unitId = 0;
        }

        $card->classifications()->delete();
        $card->classifications()->create([
            'subject_id' => $subjectId,
            'topic_id' => $topicId > 0 ? $topicId : null,
            'unit_id' => $unitId > 0 ? $unitId : null,
        ]);

        $subject = trim((string) ($targetClassification['subject'] ?? ''));
        $topic = trim((string) ($targetClassification['topic'] ?? ''));
        $unit = trim((string) ($targetClassification['unit'] ?? ''));
        $card->update([
            'subject' => $subject !== '' ? $subject : null,
            'area' => $topic !== '' ? $topic : null,
            'unit' => $unit !== '' ? $unit : null,
        ]);
    }

    private function resolveAccessibleInboxRule(
        int $ruleId,
        int $authUserId,
        int $authSchoolId,
        Collection $memberGroupIds,
    ): ?MaterialShareRule {
        if ($ruleId <= 0) {
            return null;
        }

        return MaterialShareRule::query()
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
            ->with(['targets'])
            ->first();
    }

    private function memberGroupIdsForUser(int $authUserId): Collection
    {
        return UserGroup::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $authUserId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function resolveInboxAccessContext(User $authUser, int $ruleId, int $materialId): array
    {
        $authUserId = (int) $authUser->id;
        $authSchoolId = (int) $authUser->school_id;
        $memberGroupIds = $this->memberGroupIdsForUser($authUserId);

        $rule = $this->resolveAccessibleInboxRule($ruleId, $authUserId, $authSchoolId, $memberGroupIds);
        if (! $rule) {
            abort(404, 'Freigabe wurde nicht gefunden.');
        }

        $sourceCard = $this->resolveInboxSourceCardForRule($rule, $materialId);
        if (! $sourceCard) {
            throw ValidationException::withMessages([
                'material_id' => ['Geteiltes Material wurde nicht gefunden oder gehört nicht zur Freigabe.'],
            ]);
        }

        return [
            'rule' => $rule,
            'source_card' => $sourceCard,
            'permission' => $this->resolveRulePermissionForUser($rule, $authUserId, $authSchoolId, $memberGroupIds),
        ];
    }

    private function resolveInboxSourceCardForRule(MaterialShareRule $rule, int $materialId): ?MaterialCard
    {
        if ($materialId <= 0) {
            return null;
        }

        $scopeType = (string) $rule->scope_type;
        $scopeId = (int) ($rule->scope_id ?? 0);
        $creatorUserId = (int) ($rule->created_by_user_id ?? 0);

        $query = MaterialCard::query()
            ->where('school_id', (int) $rule->school_id)
            ->whereKey($materialId)
            ->when($creatorUserId > 0, fn ($inner) => $inner->where('user_id', $creatorUserId))
            ->when((int) ($rule->workspace_id ?? 0) > 0, fn ($inner) => $inner->where('workspace_id', (int) $rule->workspace_id))
            ->with([
                'user:id',
                'attachments',
                'classifications.subject:id,name',
                'classifications.topic:id,name',
                'classifications.unit:id,name',
            ]);

        if ($scopeType === MaterialShareRule::SCOPE_MATERIAL) {
            if ($scopeId <= 0 || $scopeId !== $materialId) {
                return null;
            }
        } elseif ($scopeType === MaterialShareRule::SCOPE_SUBJECT) {
            if ($scopeId <= 0) {
                return null;
            }
            $query->whereHas('classifications', fn ($inner) => $inner->where('subject_id', $scopeId));
        } elseif ($scopeType === MaterialShareRule::SCOPE_TOPIC) {
            if ($scopeId <= 0) {
                return null;
            }
            $query->whereHas('classifications', fn ($inner) => $inner->where('topic_id', $scopeId));
        } elseif ($scopeType === MaterialShareRule::SCOPE_UNIT) {
            if ($scopeId <= 0) {
                return null;
            }
            $query->whereHas('classifications', fn ($inner) => $inner->where('unit_id', $scopeId));
        } elseif ($scopeType !== MaterialShareRule::SCOPE_ALL) {
            return null;
        }

        return $query->first();
    }

    private function assertInboxPermissionAllowsEdit(string $permission): void
    {
        if (in_array($permission, [MaterialShareTarget::PERMISSION_READ_WRITE, MaterialShareTarget::PERMISSION_FULL_ACCESS], true)) {
            return;
        }

        abort(403, 'Dieses geteilte Material ist auf NUR LESEN gesetzt.');
    }

    private function assertInboxPermissionAllowsAttachmentAppend(string $permission): void
    {
        if (in_array($permission, [MaterialShareTarget::PERMISSION_READ_WRITE, MaterialShareTarget::PERMISSION_FULL_ACCESS], true)) {
            return;
        }

        abort(403, 'Bei geteilten Materialien mit NUR LESEN können keine Anhänge hinzugefügt werden.');
    }

    private function currentMaterialClassificationPayload(MaterialCard $card): array
    {
        $classifications = $card->classifications instanceof Collection
            ? $card->classifications
            : collect();

        return $classifications
            ->map(fn (MaterialCardClassification $row) => [
                'subject' => trim((string) ($row->subject?->name ?? '')),
                'topic' => trim((string) ($row->topic?->name ?? '')),
                'unit' => trim((string) ($row->unit?->name ?? '')),
            ])
            ->values()
            ->all();
    }

    private function serializeInboxMaterialDetail(MaterialCard $sourceCard, int $ruleId, string $permission): array
    {
        $sourceCard->loadMissing([
            'attachments',
            'classifications.subject:id,name',
            'classifications.topic:id,name',
            'classifications.unit:id,name',
        ]);

        $materialId = (int) ($sourceCard->id ?? 0);
        $attachments = $sourceCard->attachments instanceof Collection
            ? $sourceCard->attachments
            : collect();
        $classifications = $sourceCard->classifications instanceof Collection
            ? $sourceCard->classifications
            : collect();

        $serializedAttachments = $attachments
            ->map(fn (MaterialCardAttachment $attachment) => $this->serializeInboxAttachment($attachment, $ruleId, $materialId))
            ->values();
        $serializedClassifications = $classifications
            ->map(fn (MaterialCardClassification $row) => [
                'id' => (int) ($row->id ?? 0),
                'subject_id' => $row->subject_id ? (int) $row->subject_id : null,
                'topic_id' => $row->topic_id ? (int) $row->topic_id : null,
                'unit_id' => $row->unit_id ? (int) $row->unit_id : null,
                'subject' => trim((string) ($row->subject?->name ?? '')),
                'topic' => trim((string) ($row->topic?->name ?? '')),
                'unit' => trim((string) ($row->unit?->name ?? '')),
            ])
            ->values();

        return [
            'id' => $materialId,
            'shared_rule_id' => $ruleId,
            'shared_material_id' => $materialId,
            'school_id' => (int) ($sourceCard->school_id ?? 0),
            'user_id' => (int) ($sourceCard->user_id ?? 0),
            'title' => (string) ($sourceCard->title ?? ''),
            'subject' => $sourceCard->subject,
            'area' => $sourceCard->area,
            'unit' => $sourceCard->unit,
            'type' => $sourceCard->type,
            'status' => $sourceCard->status,
            'source_url' => $sourceCard->source_url,
            'source_text' => $sourceCard->source_text,
            'notes' => $sourceCard->notes,
            'is_linked' => true,
            'linked_permission' => $permission,
            'linked_permission_label' => mb_strtoupper($this->permissionLabel($permission)),
            'attachments_count' => $serializedAttachments->count(),
            'attachments' => $serializedAttachments->all(),
            'classifications' => $serializedClassifications->all(),
            'created_at' => optional($sourceCard->created_at)?->toIso8601String(),
            'updated_at' => optional($sourceCard->updated_at)?->toIso8601String(),
        ];
    }

    private function resolveSourceUnitIdForInsert(
        MaterialShareRule $rule,
        MaterialCard $sourceCard,
        int $requestedSourceUnitId = 0,
    ): int {
        $scopeType = trim((string) ($rule->scope_type ?? ''));
        $scopeId = (int) ($rule->scope_id ?? 0);
        if ($scopeType === MaterialShareRule::SCOPE_UNIT && $scopeId > 0) {
            return $scopeId;
        }

        $classifications = $sourceCard->classifications instanceof Collection ? $sourceCard->classifications : collect();
        $availableUnitIds = $classifications
            ->map(fn ($classification) => (int) ($classification->unit_id ?? 0))
            ->filter(fn (int $unitId) => $unitId > 0)
            ->values();

        if ($availableUnitIds->isEmpty()) {
            return 0;
        }

        if ($requestedSourceUnitId > 0 && $availableUnitIds->contains($requestedSourceUnitId)) {
            return $requestedSourceUnitId;
        }

        return (int) $availableUnitIds->first();
    }

    private function resolveSourceTopicIdForInsert(
        MaterialShareRule $rule,
        MaterialCard $sourceCard,
        int $requestedSourceTopicId = 0,
    ): int {
        $scopeType = trim((string) ($rule->scope_type ?? ''));
        $scopeId = (int) ($rule->scope_id ?? 0);
        if ($scopeType === MaterialShareRule::SCOPE_TOPIC && $scopeId > 0) {
            return $scopeId;
        }

        $classifications = $sourceCard->classifications instanceof Collection ? $sourceCard->classifications : collect();
        $availableTopicIds = $classifications
            ->map(fn ($classification) => (int) ($classification->topic_id ?? 0))
            ->filter(fn (int $topicId) => $topicId > 0)
            ->values();

        if ($availableTopicIds->isEmpty()) {
            return 0;
        }

        if ($requestedSourceTopicId > 0 && $availableTopicIds->contains($requestedSourceTopicId)) {
            return $requestedSourceTopicId;
        }

        return (int) $availableTopicIds->first();
    }

    /**
     * @return array<string,bool>
     */
    private function resolveImportedInboxMaterialKeys(int $targetUserId): array
    {
        if ($targetUserId <= 0 || ! $this->hasMaterialInboxImportsTable()) {
            return [];
        }

        $imports = MaterialInboxImport::query()
            ->where('target_user_id', $targetUserId)
            ->whereHas('targetMaterialCard', fn ($query) => $query->where('user_id', $targetUserId))
            ->get(['source_school_id', 'source_material_id']);

        $keys = [];
        foreach ($imports as $import) {
            $sourceSchoolId = (int) ($import->source_school_id ?? 0);
            $sourceMaterialId = (int) ($import->source_material_id ?? 0);
            if ($sourceSchoolId <= 0 || $sourceMaterialId <= 0) {
                continue;
            }

            $keys[$this->materialImportKey($sourceSchoolId, $sourceMaterialId)] = true;
        }

        return $keys;
    }

    private function isRuleMaterialImported(MaterialShareRule $rule, array $importedKeys, array $importedRuleIds = []): bool
    {
        if ((string) $rule->scope_type === MaterialShareRule::SCOPE_ALL) {
            return false;
        }

        $ruleId = (int) ($rule->id ?? 0);
        if ($ruleId > 0 && isset($importedRuleIds[$ruleId])) {
            return true;
        }

        if ((string) $rule->scope_type !== MaterialShareRule::SCOPE_MATERIAL) {
            return false;
        }

        $sourceSchoolId = (int) ($rule->school_id ?? 0);
        $sourceMaterialId = (int) ($rule->scope_id ?? 0);
        if ($sourceSchoolId <= 0 || $sourceMaterialId <= 0) {
            return false;
        }

        return isset($importedKeys[$this->materialImportKey($sourceSchoolId, $sourceMaterialId)]);
    }

    /**
     * @return array<int,bool>
     */
    private function resolveImportedInboxRuleIds(int $targetUserId): array
    {
        if ($targetUserId <= 0) {
            return [];
        }

        $ruleIds = [];

        if ($this->hasMaterialInboxImportsTable()) {
            $materialImportRuleIds = MaterialInboxImport::query()
                ->where('target_user_id', $targetUserId)
                ->whereHas('targetMaterialCard', fn ($query) => $query->where('user_id', $targetUserId))
                ->whereNotNull('source_rule_id')
                ->pluck('source_rule_id');

            foreach ($materialImportRuleIds as $ruleId) {
                $normalizedRuleId = (int) $ruleId;
                if ($normalizedRuleId > 0) {
                    $ruleIds[$normalizedRuleId] = true;
                }
            }
        }

        if ($this->hasMaterialUnitInboxImportsTable()) {
            $unitImportRuleIds = MaterialUnitInboxImport::query()
                ->where('target_user_id', $targetUserId)
                ->whereHas('targetUnit.topic.subject', fn ($query) => $query->where('user_id', $targetUserId))
                ->whereNotNull('source_rule_id')
                ->pluck('source_rule_id');

            foreach ($unitImportRuleIds as $ruleId) {
                $normalizedRuleId = (int) $ruleId;
                if ($normalizedRuleId > 0) {
                    $ruleIds[$normalizedRuleId] = true;
                }
            }
        }

        if ($this->hasMaterialTopicInboxImportsTable()) {
            $topicImportRuleIds = MaterialTopicInboxImport::query()
                ->where('target_user_id', $targetUserId)
                ->whereHas('targetTopic.subject', fn ($query) => $query->where('user_id', $targetUserId))
                ->whereNotNull('source_rule_id')
                ->pluck('source_rule_id');

            foreach ($topicImportRuleIds as $ruleId) {
                $normalizedRuleId = (int) $ruleId;
                if ($normalizedRuleId > 0) {
                    $ruleIds[$normalizedRuleId] = true;
                }
            }
        }

        return $ruleIds;
    }

    /**
     * @return array<int,bool>
     */
    private function resolveArchivedInboxRuleIds(int $targetUserId): array
    {
        if ($targetUserId <= 0 || ! $this->hasMaterialShareRuleArchivesTable()) {
            return [];
        }

        $ruleIds = MaterialShareRuleArchive::query()
            ->where('target_user_id', $targetUserId)
            ->whereNotNull('archived_at')
            ->pluck('material_share_rule_id');

        $result = [];
        foreach ($ruleIds as $ruleId) {
            $normalizedRuleId = (int) $ruleId;
            if ($normalizedRuleId <= 0) {
                continue;
            }
            $result[$normalizedRuleId] = true;
        }

        return $result;
    }

    private function materialImportKey(int $sourceSchoolId, int $sourceMaterialId): string
    {
        return $sourceSchoolId.':'.$sourceMaterialId;
    }

    private function ensureTargetMaterialType(
        User $targetUser,
        string $sourceType,
        array $sourceTypeMeta,
    ): ?string {
        $normalizedType = trim($sourceType);
        if ($normalizedType === '') {
            return null;
        }
        $normalizedType = mb_substr($normalizedType, 0, 255);

        if (! $this->hasMaterialTypesTable()) {
            return $normalizedType;
        }

        $isUserScoped = $this->materialTypesAreUserScoped();
        $existingType = MaterialType::query()
            ->when($isUserScoped, fn ($query) => $query->where('user_id', (int) $targetUser->id))
            ->when(! $isUserScoped, fn ($query) => $query->where('school_id', (int) $targetUser->school_id))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedType)])
            ->first();

        if ($existingType) {
            return trim((string) ($existingType->name ?? '')) ?: $normalizedType;
        }

        $createData = [
            'school_id' => (int) $targetUser->school_id,
            'name' => $normalizedType,
        ];
        if ($isUserScoped) {
            $createData['user_id'] = (int) $targetUser->id;
        }

        if ($this->materialTypesHasIconColumn()) {
            $icon = trim((string) ($sourceTypeMeta['icon'] ?? ''));
            if ($icon !== '') {
                $createData['icon'] = $icon;
            }
        }
        if ($this->materialTypesHasColorColumn()) {
            $color = $this->normalizeColor((string) ($sourceTypeMeta['color'] ?? ''));
            if ($color !== null) {
                $createData['color'] = $color;
            }
        }

        $createdType = MaterialType::query()->create($createData);

        return trim((string) ($createdType->name ?? '')) ?: $normalizedType;
    }

    private function ensureTargetMaterialStatus(
        User $targetUser,
        string $sourceStatus,
        array $sourceStatusMeta,
    ): string {
        $statusValue = trim($sourceStatus);
        if ($statusValue === '') {
            $statusValue = MaterialCard::STATUS_INBOX;
        }
        $statusValue = mb_substr($statusValue, 0, 255);

        if (! $this->hasMaterialStatusesTable()) {
            return $statusValue;
        }

        $existingStatus = MaterialStatus::query()
            ->where('school_id', (int) $targetUser->school_id)
            ->whereRaw('LOWER(value) = ?', [mb_strtolower($statusValue)])
            ->first();

        if ($existingStatus) {
            return trim((string) ($existingStatus->value ?? '')) ?: $statusValue;
        }

        $statusLabel = trim((string) ($sourceStatusMeta['label'] ?? ''));
        if ($statusLabel === '') {
            $statusLabel = $statusValue;
        }
        $statusLabel = mb_substr($statusLabel, 0, 255);

        $createData = [
            'school_id' => (int) $targetUser->school_id,
            'value' => $statusValue,
            'label' => $statusLabel,
        ];

        if ($this->materialStatusesHasColorColumn()) {
            $color = $this->normalizeColor((string) ($sourceStatusMeta['color'] ?? ''));
            if ($color !== null) {
                $createData['color'] = $color;
            }
        }

        MaterialStatus::query()->create($createData);

        return $statusValue;
    }

    private function sourceClassificationsForOriginalInsert(MaterialCard $sourceCard): array
    {
        $rows = [];
        $seen = [];
        $classifications = $sourceCard->classifications instanceof Collection ? $sourceCard->classifications : collect();

        foreach ($classifications as $classification) {
            if (! $classification instanceof MaterialCardClassification) {
                continue;
            }

            $subject = trim((string) ($classification->subject?->name ?? ''));
            $topic = trim((string) ($classification->topic?->name ?? ''));
            $unit = trim((string) ($classification->unit?->name ?? ''));

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

        if (count($rows) > 0) {
            return $rows;
        }

        $subject = trim((string) ($sourceCard->subject ?? ''));
        $topic = trim((string) ($sourceCard->area ?? ''));
        $unit = trim((string) ($sourceCard->unit ?? ''));
        if ($subject === '') {
            return [];
        }
        if ($topic === '') {
            $unit = '';
        }

        return [[
            'subject' => $subject,
            'topic' => $topic,
            'unit' => $unit,
        ]];
    }

    private function cloneSourceAttachmentsToCard(MaterialCard $sourceCard, MaterialCard $targetCard): void
    {
        $attachments = $sourceCard->attachments instanceof Collection ? $sourceCard->attachments : collect();
        if ($attachments->isEmpty()) {
            return;
        }

        $diskName = (string) config('filesystems.default', 'local');
        $disk = Storage::disk($diskName);

        foreach ($attachments as $sourceAttachment) {
            if (! $sourceAttachment instanceof MaterialCardAttachment) {
                continue;
            }

            $attachmentType = (string) ($sourceAttachment->attachment_type ?? '');
            if ($attachmentType === MaterialCardAttachment::TYPE_LINK) {
                $targetCard->attachments()->create([
                    'attachment_type' => MaterialCardAttachment::TYPE_LINK,
                    'name' => $sourceAttachment->name,
                    'url' => $sourceAttachment->url,
                    'source_url' => $sourceAttachment->source_url,
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

            $targetPath = $this->copiedAttachmentStoragePath($targetCard, $sourceAttachment);
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

    private function copiedAttachmentStoragePath(MaterialCard $targetCard, MaterialCardAttachment $sourceAttachment): string
    {
        $now = now();
        $directory = implode('/', [
            'materials',
            'schools',
            (string) $targetCard->school_id,
            'users',
            (string) $targetCard->user_id,
            'cards',
            (string) $targetCard->id,
            $now->format('Y'),
            $now->format('m'),
        ]);

        $nameSource = trim((string) ($sourceAttachment->name ?: basename((string) ($sourceAttachment->file_path ?? ''))));
        $extension = strtolower((string) pathinfo($nameSource, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower((string) pathinfo((string) ($sourceAttachment->file_path ?? ''), PATHINFO_EXTENSION));
        }

        $basename = trim((string) pathinfo($nameSource, PATHINFO_FILENAME));
        $slug = Str::slug($basename, '-');
        if ($slug === '') {
            $slug = 'file';
        }
        $slug = mb_substr($slug, 0, 120);

        $filename = (string) Str::uuid().'-'.$slug.($extension !== '' ? '.'.$extension : '');

        return $directory.'/'.$filename;
    }

    private function resolveScopePathLabel(MaterialShareRule $rule): string
    {
        $scopeType = (string) $rule->scope_type;
        $scopeId = (int) ($rule->scope_id ?? 0);

        if ($scopeType === MaterialShareRule::SCOPE_ALL) {
            return 'Alle Fächer - Alle Themen - Alle Einheiten';
        }

        if ($scopeType === MaterialShareRule::SCOPE_SUBJECT) {
            $subject = $scopeId > 0 ? MaterialSubject::query()->find($scopeId) : null;
            $subjectName = trim((string) ($subject?->name ?: 'Fach'));

            return $subjectName.' - Alle Themen - Alle Einheiten';
        }

        if ($scopeType === MaterialShareRule::SCOPE_TOPIC) {
            $topic = $scopeId > 0
                ? MaterialTopic::query()->with('subject:id,name')->find($scopeId)
                : null;
            $subjectName = trim((string) ($topic?->subject?->name ?: 'Fach'));
            $topicName = trim((string) ($topic?->name ?: 'Thema'));

            return $subjectName.' - '.$topicName.' - Alle Einheiten';
        }

        if ($scopeType === MaterialShareRule::SCOPE_UNIT) {
            $unit = $scopeId > 0
                ? MaterialUnit::query()->with('topic.subject:id,name')->find($scopeId)
                : null;
            $subjectName = trim((string) ($unit?->topic?->subject?->name ?: 'Fach'));
            $topicName = trim((string) ($unit?->topic?->name ?: 'Thema'));
            $unitName = trim((string) ($unit?->name ?: 'Einheit'));

            return $subjectName.' - '.$topicName.' - '.$unitName;
        }

        if ($scopeType === MaterialShareRule::SCOPE_MATERIAL) {
            $creatorUserId = (int) ($rule->created_by_user_id ?? 0);
            $card = $scopeId > 0
                ? MaterialCard::query()
                    ->where('school_id', (int) $rule->school_id)
                    ->when($creatorUserId > 0, fn ($query) => $query->where('user_id', $creatorUserId))
                    ->with([
                        'classifications.subject:id,name',
                        'classifications.topic:id,name',
                        'classifications.unit:id,name',
                    ])
                    ->find($scopeId)
                : null;

            if (! $card) {
                return 'Fach - Thema - Einheit';
            }

            $classificationPaths = collect($card->classifications ?? [])
                ->map(function ($classification) {
                    $subjectName = trim((string) ($classification?->subject?->name ?: 'Fach'));
                    $topicName = trim((string) ($classification?->topic?->name ?: 'Thema'));
                    $unitName = trim((string) ($classification?->unit?->name ?: 'Einheit'));

                    return $subjectName.' - '.$topicName.' - '.$unitName;
                })
                ->filter()
                ->unique()
                ->values();

            if ($classificationPaths->isNotEmpty()) {
                return $classificationPaths->implode(' | ');
            }

            $subjectName = trim((string) ($card->subject ?? ''));
            $topicName = trim((string) ($card->area ?? ''));
            $unitName = trim((string) ($card->unit ?? ''));
            if ($subjectName !== '' || $topicName !== '' || $unitName !== '') {
                return ($subjectName !== '' ? $subjectName : 'Fach')
                    .' - '
                    .($topicName !== '' ? $topicName : 'Thema')
                    .' - '
                    .($unitName !== '' ? $unitName : 'Einheit');
            }

            return 'Fach - Thema - Einheit';
        }

        return 'Fach - Thema - Einheit';
    }

    private function resolveScopeHierarchy(MaterialShareRule $rule): array
    {
        $scopeType = (string) $rule->scope_type;
        $scopeId = (int) ($rule->scope_id ?? 0);
        $creatorUserId = (int) ($rule->created_by_user_id ?? 0);
        $workspaceId = (int) ($rule->workspace_id ?? 0);
        $subjects = $this->resolveScopeHierarchySkeleton($rule);

        $cardsQuery = MaterialCard::query()
            ->where('school_id', (int) $rule->school_id)
            ->with([
                'classifications.subject:id,name,sort_order',
                'classifications.topic:id,name,sort_order',
                'classifications.unit:id,name,sort_order',
            ])
            ->orderBy('title')
            ->orderBy('id');

        if ($creatorUserId > 0) {
            $cardsQuery->where('user_id', $creatorUserId);
        }
        if ($workspaceId > 0) {
            $cardsQuery->where('workspace_id', $workspaceId);
        }

        if ($this->hasMaterialAttachmentsTable()) {
            $cardsQuery
                ->withCount('attachments')
                ->withCount([
                    'attachments as file_attachments_count' => fn ($query) => $query->where('attachment_type', MaterialCardAttachment::TYPE_FILE),
                ]);
        }

        if ($scopeType === MaterialShareRule::SCOPE_MATERIAL) {
            if ($scopeId <= 0) {
                return [];
            }
            $cardsQuery->whereKey($scopeId);
        } elseif ($scopeType === MaterialShareRule::SCOPE_SUBJECT) {
            if ($scopeId <= 0) {
                return [];
            }
            $cardsQuery->whereHas('classifications', fn ($query) => $query->where('subject_id', $scopeId));
        } elseif ($scopeType === MaterialShareRule::SCOPE_TOPIC) {
            if ($scopeId <= 0) {
                return [];
            }
            $cardsQuery->whereHas('classifications', fn ($query) => $query->where('topic_id', $scopeId));
        } elseif ($scopeType === MaterialShareRule::SCOPE_UNIT) {
            if ($scopeId <= 0) {
                return [];
            }
            $cardsQuery->whereHas('classifications', fn ($query) => $query->where('unit_id', $scopeId));
        }

        $cards = $cardsQuery->get([
            'id',
            'school_id',
            'user_id',
            'title',
            'subject',
            'area',
            'unit',
            'type',
            'status',
            'source_url',
            'source_text',
            'notes',
        ]);
        if ($cards->isEmpty() && $subjects === []) {
            return [];
        }

        foreach ($cards as $card) {
            $cardRows = $this->classificationRowsForCardAndScope($card, $scopeType, $scopeId);
            if (empty($cardRows)) {
                continue;
            }
            $materialPayload = $this->serializeHierarchyMaterial($card);

            $seenPaths = [];
            foreach ($cardRows as $row) {
                $pathKey = mb_strtolower(
                    (string) $row['subject_name']
                    .'|'
                    .(string) $row['topic_name']
                    .'|'
                    .(string) $row['unit_name']
                );
                if (isset($seenPaths[$pathKey])) {
                    continue;
                }
                $seenPaths[$pathKey] = true;

                $subjectKey = (string) ($row['subject_id'] ?? 0).'|'.$row['subject_name'];
                if (! isset($subjects[$subjectKey])) {
                    $subjects[$subjectKey] = [
                        'id' => $row['subject_id'],
                        'name' => $row['subject_name'],
                        'sort_order' => $this->normalizeHierarchySortOrder($row['subject_sort_order']),
                        'materials' => [],
                        'topics' => [],
                    ];
                }

                if (($row['attach_level'] ?? 'unit') === 'subject') {
                    $materialId = (int) ($card->id ?? 0);
                    $subjects[$subjectKey]['materials'][$materialId] = $materialPayload;

                    continue;
                }

                $topicKey = (string) ($row['topic_id'] ?? 0).'|'.$row['topic_name'];
                if (! isset($subjects[$subjectKey]['topics'][$topicKey])) {
                    $subjects[$subjectKey]['topics'][$topicKey] = [
                        'id' => $row['topic_id'],
                        'name' => $row['topic_name'],
                        'sort_order' => $this->normalizeHierarchySortOrder($row['topic_sort_order']),
                        'materials' => [],
                        'units' => [],
                    ];
                }

                if (($row['attach_level'] ?? 'unit') === 'topic') {
                    $materialId = (int) ($card->id ?? 0);
                    $subjects[$subjectKey]['topics'][$topicKey]['materials'][$materialId] = $materialPayload;

                    continue;
                }

                $unitKey = (string) ($row['unit_id'] ?? 0).'|'.$row['unit_name'];
                if (! isset($subjects[$subjectKey]['topics'][$topicKey]['units'][$unitKey])) {
                    $subjects[$subjectKey]['topics'][$topicKey]['units'][$unitKey] = [
                        'id' => $row['unit_id'],
                        'name' => $row['unit_name'],
                        'sort_order' => $this->normalizeHierarchySortOrder($row['unit_sort_order']),
                        'materials' => [],
                    ];
                }

                $materialId = (int) ($card->id ?? 0);
                $subjects[$subjectKey]['topics'][$topicKey]['units'][$unitKey]['materials'][$materialId] = $materialPayload;
            }
        }

        $subjectRows = collect($subjects)
            ->sortBy(fn (array $subject) => $this->hierarchySortKey($subject))
            ->values()
            ->map(function (array $subject) {
                $subjectMaterials = $this->sortHierarchyMaterials($subject['materials'] ?? []);
                $topicRows = collect($subject['topics'] ?? [])
                    ->sortBy(fn (array $topic) => $this->hierarchySortKey($topic))
                    ->values()
                    ->map(function (array $topic) {
                        $topicMaterials = $this->sortHierarchyMaterials($topic['materials'] ?? []);
                        $unitRows = collect($topic['units'] ?? [])
                            ->sortBy(fn (array $unit) => $this->hierarchySortKey($unit))
                            ->values()
                            ->map(function (array $unit) {
                                $materials = $this->sortHierarchyMaterials($unit['materials'] ?? []);

                                return [
                                    'id' => $unit['id'],
                                    'name' => $unit['name'],
                                    'materials' => $materials,
                                ];
                            });

                        return [
                            'id' => $topic['id'],
                            'name' => $topic['name'],
                            'materials' => $topicMaterials,
                            'units' => $unitRows->all(),
                        ];
                    });

                return [
                    'id' => $subject['id'],
                    'name' => $subject['name'],
                    'materials' => $subjectMaterials,
                    'topics' => $topicRows->all(),
                ];
            })
            ->values();

        return $subjectRows->all();
    }

    private function resolveScopeHierarchySkeleton(MaterialShareRule $rule): array
    {
        $creatorUserId = (int) ($rule->created_by_user_id ?? 0);
        $workspaceId = (int) ($rule->workspace_id ?? 0);
        $scopeType = (string) ($rule->scope_type ?? '');
        $scopeId = (int) ($rule->scope_id ?? 0);

        if ($creatorUserId <= 0 || $scopeType === MaterialShareRule::SCOPE_MATERIAL) {
            return [];
        }

        $subjectQuery = MaterialSubject::query()
            ->where('user_id', $creatorUserId)
            ->with([
                'topics:id,subject_id,name,sort_order',
                'topics.units:id,topic_id,name,sort_order',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');

        if ($workspaceId > 0) {
            $subjectQuery->where('workspace_id', $workspaceId);
        }

        $subjects = $subjectQuery->get(['id', 'user_id', 'workspace_id', 'name', 'sort_order']);
        if ($subjects->isEmpty()) {
            return [];
        }

        $rows = [];
        foreach ($subjects as $subject) {
            $subjectKey = $this->hierarchyNodeKey((int) $subject->id, (string) $subject->name);
            $topicRows = [];

            foreach ($subject->topics as $topic) {
                $topicKey = $this->hierarchyNodeKey((int) $topic->id, (string) $topic->name);
                $unitRows = [];

                foreach ($topic->units as $unit) {
                    $unitKey = $this->hierarchyNodeKey((int) $unit->id, (string) $unit->name);
                    $unitRows[$unitKey] = [
                        'id' => (int) $unit->id,
                        'name' => $this->normalizeHierarchyName((string) $unit->name, 'Ohne Einheit'),
                        'sort_order' => $this->normalizeHierarchySortOrder($unit->sort_order),
                        'materials' => [],
                    ];
                }

                $topicRows[$topicKey] = [
                    'id' => (int) $topic->id,
                    'name' => $this->normalizeHierarchyName((string) $topic->name, 'Ohne Thema'),
                    'sort_order' => $this->normalizeHierarchySortOrder($topic->sort_order),
                    'materials' => [],
                    'units' => $unitRows,
                ];
            }

            $rows[$subjectKey] = [
                'id' => (int) $subject->id,
                'name' => $this->normalizeHierarchyName((string) $subject->name, 'Ohne Fach'),
                'sort_order' => $this->normalizeHierarchySortOrder($subject->sort_order),
                'materials' => [],
                'topics' => $topicRows,
            ];
        }

        return $this->filterScopeHierarchySkeleton($rows, $scopeType, $scopeId);
    }

    private function filterScopeHierarchySkeleton(array $subjects, string $scopeType, int $scopeId): array
    {
        if ($scopeId <= 0 || $scopeType === MaterialShareRule::SCOPE_ALL) {
            return $subjects;
        }

        if ($scopeType === MaterialShareRule::SCOPE_SUBJECT) {
            return array_filter(
                $subjects,
                fn (array $subject) => (int) ($subject['id'] ?? 0) === $scopeId
            );
        }

        if ($scopeType === MaterialShareRule::SCOPE_TOPIC) {
            $filteredSubjects = [];

            foreach ($subjects as $subjectKey => $subject) {
                $topics = array_filter(
                    $subject['topics'] ?? [],
                    fn (array $topic) => (int) ($topic['id'] ?? 0) === $scopeId
                );
                if ($topics === []) {
                    continue;
                }

                $subject['materials'] = [];
                $subject['topics'] = $topics;
                $filteredSubjects[$subjectKey] = $subject;
            }

            return $filteredSubjects;
        }

        if ($scopeType === MaterialShareRule::SCOPE_UNIT) {
            $filteredSubjects = [];

            foreach ($subjects as $subjectKey => $subject) {
                $topics = [];

                foreach ($subject['topics'] ?? [] as $topicKey => $topic) {
                    $units = array_filter(
                        $topic['units'] ?? [],
                        fn (array $unit) => (int) ($unit['id'] ?? 0) === $scopeId
                    );
                    if ($units === []) {
                        continue;
                    }

                    $topic['materials'] = [];
                    $topic['units'] = $units;
                    $topics[$topicKey] = $topic;
                }

                if ($topics === []) {
                    continue;
                }

                $subject['materials'] = [];
                $subject['topics'] = $topics;
                $filteredSubjects[$subjectKey] = $subject;
            }

            return $filteredSubjects;
        }

        return [];
    }

    private function hierarchyNodeKey(int $id, string $name): string
    {
        return $id.'|'.trim($name);
    }

    private function sortHierarchyMaterials(array $materials): array
    {
        return collect($materials)
            ->sortBy(fn (array $material) => mb_strtolower((string) ($material['title'] ?? '')))
            ->values()
            ->all();
    }

    private function classificationRowsForCardAndScope(MaterialCard $card, string $scopeType, int $scopeId): array
    {
        $rows = [];
        $classifications = $card->classifications instanceof Collection ? $card->classifications : collect();

        foreach ($classifications as $classification) {
            $subjectId = (int) ($classification->subject_id ?? 0);
            $topicId = (int) ($classification->topic_id ?? 0);
            $unitId = (int) ($classification->unit_id ?? 0);

            if ($scopeType === MaterialShareRule::SCOPE_SUBJECT && $scopeId > 0 && $subjectId !== $scopeId) {
                continue;
            }
            if ($scopeType === MaterialShareRule::SCOPE_TOPIC && $scopeId > 0 && $topicId !== $scopeId) {
                continue;
            }
            if ($scopeType === MaterialShareRule::SCOPE_UNIT && $scopeId > 0 && $unitId !== $scopeId) {
                continue;
            }

            $rows[] = [
                'subject_id' => $subjectId > 0 ? $subjectId : null,
                'subject_name' => trim((string) ($classification->subject?->name ?? '')),
                'subject_sort_order' => $classification->subject?->sort_order,
                'topic_id' => $topicId > 0 ? $topicId : null,
                'topic_name' => trim((string) ($classification->topic?->name ?? '')),
                'topic_sort_order' => $classification->topic?->sort_order,
                'unit_id' => $unitId > 0 ? $unitId : null,
                'unit_name' => trim((string) ($classification->unit?->name ?? '')),
                'unit_sort_order' => $classification->unit?->sort_order,
                'attach_level' => $this->resolveHierarchyAttachLevel(
                    $topicId > 0 ? $topicId : null,
                    $classification->topic?->name,
                    $unitId > 0 ? $unitId : null,
                    $classification->unit?->name,
                ),
            ];
        }

        if (count($rows) === 0 && in_array($scopeType, [MaterialShareRule::SCOPE_ALL, MaterialShareRule::SCOPE_MATERIAL], true)) {
            $rows[] = [
                'subject_id' => null,
                'subject_name' => trim((string) ($card->subject ?? '')),
                'subject_sort_order' => null,
                'topic_id' => null,
                'topic_name' => trim((string) ($card->area ?? '')),
                'topic_sort_order' => null,
                'unit_id' => null,
                'unit_name' => trim((string) ($card->unit ?? '')),
                'unit_sort_order' => null,
                'attach_level' => $this->resolveHierarchyAttachLevel(
                    null,
                    $card->area,
                    null,
                    $card->unit,
                ),
            ];
        }

        return array_map(function (array $row) {
            return [
                'subject_id' => $row['subject_id'],
                'subject_name' => $this->normalizeHierarchyName($row['subject_name'] ?? '', 'Ohne Fach'),
                'subject_sort_order' => $row['subject_sort_order'],
                'topic_id' => $row['topic_id'],
                'topic_name' => $this->normalizeHierarchyName($row['topic_name'] ?? '', 'Ohne Thema'),
                'topic_sort_order' => $row['topic_sort_order'],
                'unit_id' => $row['unit_id'],
                'unit_name' => $this->normalizeHierarchyName($row['unit_name'] ?? '', 'Ohne Einheit'),
                'unit_sort_order' => $row['unit_sort_order'],
                'attach_level' => (string) ($row['attach_level'] ?? 'unit'),
            ];
        }, $rows);
    }

    private function resolveHierarchyAttachLevel(?int $topicId, ?string $topicName, ?int $unitId, ?string $unitName): string
    {
        $hasTopic = (int) ($topicId ?? 0) > 0 || trim((string) ($topicName ?? '')) !== '';
        if (! $hasTopic) {
            return 'subject';
        }

        $hasUnit = (int) ($unitId ?? 0) > 0 || trim((string) ($unitName ?? '')) !== '';
        if (! $hasUnit) {
            return 'topic';
        }

        return 'unit';
    }

    private function normalizeHierarchySortOrder(mixed $sortOrder): int
    {
        if (! is_numeric($sortOrder)) {
            return PHP_INT_MAX;
        }

        return (int) $sortOrder;
    }

    private function hierarchySortKey(array $row): string
    {
        $sortOrder = $this->normalizeHierarchySortOrder($row['sort_order'] ?? null);
        $name = mb_strtolower((string) ($row['name'] ?? ''));
        $id = (int) ($row['id'] ?? 0);

        return sprintf('%010d|%s|%010d', $sortOrder, $name, $id);
    }

    private function normalizeHierarchyName(?string $value, string $fallback): string
    {
        $name = trim((string) ($value ?? ''));

        return $name !== '' ? $name : $fallback;
    }

    private function serializeHierarchyMaterial(MaterialCard $card): array
    {
        $materialId = (int) ($card->id ?? 0);
        $materialTitle = trim((string) ($card->title ?? 'Material'));
        $typeValue = trim((string) ($card->type ?? ''));
        $statusValue = trim((string) ($card->status ?: MaterialCard::STATUS_INBOX));
        if ($statusValue === '') {
            $statusValue = MaterialCard::STATUS_INBOX;
        }

        $attachmentsCount = max(0, (int) ($card->attachments_count ?? 0));
        $fileAttachmentsCount = max(0, (int) ($card->file_attachments_count ?? 0));

        $typeMeta = $this->resolveMaterialTypeMeta(
            schoolId: (int) ($card->school_id ?? 0),
            userId: (int) ($card->user_id ?? 0),
            typeValue: $typeValue,
        );
        $statusMeta = $this->resolveMaterialStatusMeta(
            schoolId: (int) ($card->school_id ?? 0),
            statusValue: $statusValue,
        );

        return [
            'id' => $materialId,
            'title' => $materialTitle !== '' ? $materialTitle : 'Material',
            'icon' => $this->resolveHierarchyMaterialIcon($card, $typeMeta['icon'], $attachmentsCount, $fileAttachmentsCount),
            'type' => $typeValue !== '' ? $typeValue : null,
            'type_label' => $typeMeta['label'] !== '' ? $typeMeta['label'] : ($typeValue !== '' ? $typeValue : null),
            'type_color' => $typeMeta['color'],
            'status' => $statusValue,
            'status_label' => $statusMeta['label'],
            'status_color' => $statusMeta['color'],
            'attachments_count' => $attachmentsCount,
        ];
    }

    private function serializeInboxAttachment(MaterialCardAttachment $attachment, int $ruleId, int $materialId): array
    {
        $query = http_build_query([
            'rule_id' => $ruleId,
            'material_id' => $materialId,
        ], '', '&', PHP_QUERY_RFC3986);
        $querySuffix = $query !== '' ? '?'.$query : '';
        $isFile = (string) ($attachment->attachment_type ?? '') === MaterialCardAttachment::TYPE_FILE;

        return [
            'id' => (int) $attachment->id,
            'material_card_id' => (int) ($attachment->material_card_id ?? 0),
            'shared_rule_id' => $ruleId,
            'shared_material_id' => $materialId,
            'attachment_type' => (string) ($attachment->attachment_type ?? ''),
            'name' => (string) ($attachment->name ?? ''),
            'url' => $attachment->url,
            'source_url' => $attachment->source_url,
            'file_path' => $attachment->file_path,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'downloaded_at' => $attachment->downloaded_at?->toDateTimeString(),
            'preview_url' => $isFile ? '/api/admin/materials/attachments/'.$attachment->id.'/preview'.$querySuffix : null,
            'download_url' => $isFile ? '/api/admin/materials/attachments/'.$attachment->id.'/download'.$querySuffix : null,
            'download_docx_url' => $isFile ? '/api/admin/materials/attachments/'.$attachment->id.'/download-docx'.$querySuffix : null,
            'created_at' => $attachment->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array{label:string,icon:string,color:?string}
     */
    private function resolveMaterialTypeMeta(int $schoolId, int $userId, string $typeValue): array
    {
        $normalizedType = trim($typeValue);
        if ($normalizedType === '') {
            return [
                'label' => '',
                'icon' => '',
                'color' => null,
            ];
        }

        $key = mb_strtolower($normalizedType);
        $map = $this->materialTypeMetaMap($schoolId, $userId);
        if (isset($map[$key])) {
            return $map[$key];
        }

        return [
            'label' => $normalizedType,
            'icon' => '',
            'color' => null,
        ];
    }

    /**
     * @return array<string,array{label:string,icon:string,color:?string}>
     */
    private function materialTypeMetaMap(int $schoolId, int $userId): array
    {
        if (! $this->hasMaterialTypesTable()) {
            return [];
        }

        $isUserScoped = $this->materialTypesAreUserScoped();
        if ($isUserScoped && $userId <= 0) {
            return [];
        }
        if (! $isUserScoped && $schoolId <= 0) {
            return [];
        }

        $cacheKey = $isUserScoped ? ('user:'.$userId) : ('school:'.$schoolId);
        if (array_key_exists($cacheKey, $this->materialTypeMetaCache)) {
            return $this->materialTypeMetaCache[$cacheKey];
        }

        $hasIconColumn = $this->materialTypesHasIconColumn();
        $hasColorColumn = $this->materialTypesHasColorColumn();

        $columns = ['name'];
        if ($hasIconColumn) {
            $columns[] = 'icon';
        }
        if ($hasColorColumn) {
            $columns[] = 'color';
        }

        $query = MaterialType::query();
        if ($isUserScoped) {
            $query->where('user_id', $userId);
        } else {
            $query->where('school_id', $schoolId);
        }

        $rows = $query->orderBy('name')->get($columns);

        $map = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row->name ?? ''));
            if ($name === '') {
                continue;
            }

            $lowerName = mb_strtolower($name);
            $icon = $hasIconColumn ? trim((string) ($row->icon ?? '')) : '';
            $color = $hasColorColumn ? $this->normalizeColor((string) ($row->color ?? '')) : null;

            $map[$lowerName] = [
                'label' => $name,
                'icon' => $icon,
                'color' => $color,
            ];
        }

        $this->materialTypeMetaCache[$cacheKey] = $map;

        return $map;
    }

    /**
     * @return array{label:string,color:string}
     */
    private function resolveMaterialStatusMeta(int $schoolId, string $statusValue): array
    {
        $normalizedStatus = trim($statusValue);
        if ($normalizedStatus === '') {
            return $this->defaultMaterialStatusMeta(MaterialCard::STATUS_INBOX);
        }

        $lowerStatus = mb_strtolower($normalizedStatus);
        $map = $this->materialStatusMetaMap($schoolId);
        if (isset($map[$lowerStatus])) {
            return $map[$lowerStatus];
        }

        return $this->defaultMaterialStatusMeta($normalizedStatus);
    }

    /**
     * @return array<string,array{label:string,color:string}>
     */
    private function materialStatusMetaMap(int $schoolId): array
    {
        if ($schoolId <= 0 || ! $this->hasMaterialStatusesTable()) {
            return [];
        }

        if (array_key_exists($schoolId, $this->materialStatusMetaCache)) {
            return $this->materialStatusMetaCache[$schoolId];
        }

        $hasColorColumn = $this->materialStatusesHasColorColumn();
        $columns = ['value', 'label'];
        if ($hasColorColumn) {
            $columns[] = 'color';
        }

        $rows = MaterialStatus::query()
            ->where('school_id', $schoolId)
            ->orderBy('id')
            ->get($columns);

        $map = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row->value ?? ''));
            if ($value === '') {
                continue;
            }

            $lowerValue = mb_strtolower($value);
            $label = trim((string) ($row->label ?? ''));
            if ($label === '') {
                $label = $this->defaultMaterialStatusMeta($value)['label'];
            }

            $configuredColor = $hasColorColumn ? $this->normalizeColor((string) ($row->color ?? '')) : null;
            $color = $configuredColor ?: $this->defaultMaterialStatusMeta($value)['color'];

            $map[$lowerValue] = [
                'label' => $label,
                'color' => $color,
            ];
        }

        $this->materialStatusMetaCache[$schoolId] = $map;

        return $map;
    }

    /**
     * @return array{label:string,color:string}
     */
    private function defaultMaterialStatusMeta(string $statusValue): array
    {
        return match (mb_strtolower(trim($statusValue))) {
            MaterialCard::STATUS_INBOX => ['label' => 'Neu/Idee', 'color' => 'secondary'],
            MaterialCard::STATUS_IN_PROGRESS => ['label' => 'In Arbeit', 'color' => 'warning'],
            MaterialCard::STATUS_DONE => ['label' => 'ok', 'color' => 'success'],
            MaterialCard::STATUS_UPDATE_NEEDED => ['label' => 'Änderung nötig', 'color' => 'error'],
            default => ['label' => 'Unbekannt', 'color' => 'primary'],
        };
    }

    private function resolveHierarchyMaterialIcon(
        MaterialCard $card,
        string $typeIcon,
        int $attachmentsCount,
        int $fileAttachmentsCount,
    ): string {
        $icon = trim($typeIcon);
        if ($icon !== '') {
            return $icon;
        }

        if ($fileAttachmentsCount > 0) {
            return 'mdi-file-upload-outline';
        }

        if (trim((string) ($card->source_url ?? '')) !== '') {
            return 'mdi-link-variant';
        }

        $hasTextContent = trim((string) ($card->source_text ?? '')) !== ''
            || trim((string) ($card->notes ?? '')) !== '';
        if ($hasTextContent) {
            return 'mdi-note-text-outline';
        }

        if ($attachmentsCount > 0) {
            return 'mdi-paperclip';
        }

        return 'mdi-file-document-outline';
    }

    private function normalizeColor(string $value): ?string
    {
        $color = trim($value);
        if (! preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return null;
        }

        if (strlen($color) === 4) {
            return strtolower('#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3]);
        }

        return strtolower($color);
    }

    private function hasMaterialAttachmentsTable(): bool
    {
        if ($this->hasMaterialAttachmentsTableCache === null) {
            $this->hasMaterialAttachmentsTableCache = Schema::hasTable('material_card_attachments');
        }

        return $this->hasMaterialAttachmentsTableCache;
    }

    private function hasMaterialTypesTable(): bool
    {
        if ($this->hasMaterialTypesTableCache === null) {
            $this->hasMaterialTypesTableCache = Schema::hasTable('material_types');
        }

        return $this->hasMaterialTypesTableCache;
    }

    private function hasMaterialStatusesTable(): bool
    {
        if ($this->hasMaterialStatusesTableCache === null) {
            $this->hasMaterialStatusesTableCache = Schema::hasTable('material_statuses');
        }

        return $this->hasMaterialStatusesTableCache;
    }

    private function hasMaterialInboxImportsTable(): bool
    {
        if ($this->hasMaterialInboxImportsTableCache === null) {
            $this->hasMaterialInboxImportsTableCache = Schema::hasTable('material_inbox_imports');
        }

        return $this->hasMaterialInboxImportsTableCache;
    }

    private function materialInboxImportsHasImportModeColumn(): bool
    {
        if (! $this->hasMaterialInboxImportsTable()) {
            return false;
        }

        if ($this->materialInboxImportsHasImportModeColumnCache === null) {
            $this->materialInboxImportsHasImportModeColumnCache = Schema::hasColumn('material_inbox_imports', 'import_mode');
        }

        return $this->materialInboxImportsHasImportModeColumnCache;
    }

    private function hasMaterialUnitInboxImportsTable(): bool
    {
        if ($this->hasMaterialUnitInboxImportsTableCache === null) {
            $this->hasMaterialUnitInboxImportsTableCache = Schema::hasTable('material_unit_inbox_imports');
        }

        return $this->hasMaterialUnitInboxImportsTableCache;
    }

    private function hasMaterialTopicInboxImportsTable(): bool
    {
        if ($this->hasMaterialTopicInboxImportsTableCache === null) {
            $this->hasMaterialTopicInboxImportsTableCache = Schema::hasTable('material_topic_inbox_imports');
        }

        return $this->hasMaterialTopicInboxImportsTableCache;
    }

    private function hasMaterialShareRuleArchivesTable(): bool
    {
        if ($this->hasMaterialShareRuleArchivesTableCache === null) {
            $this->hasMaterialShareRuleArchivesTableCache = Schema::hasTable('material_share_rule_archives');
        }

        return $this->hasMaterialShareRuleArchivesTableCache;
    }

    private function materialTypesAreUserScoped(): bool
    {
        if ($this->materialTypesUserScopedCache === null) {
            $this->materialTypesUserScopedCache = $this->hasMaterialTypesTable() && Schema::hasColumn('material_types', 'user_id');
        }

        return $this->materialTypesUserScopedCache;
    }

    private function materialTypesHasIconColumn(): bool
    {
        if ($this->materialTypesHasIconColumnCache === null) {
            $this->materialTypesHasIconColumnCache = $this->hasMaterialTypesTable() && Schema::hasColumn('material_types', 'icon');
        }

        return $this->materialTypesHasIconColumnCache;
    }

    private function materialTypesHasColorColumn(): bool
    {
        if ($this->materialTypesHasColorColumnCache === null) {
            $this->materialTypesHasColorColumnCache = $this->hasMaterialTypesTable() && Schema::hasColumn('material_types', 'color');
        }

        return $this->materialTypesHasColorColumnCache;
    }

    private function materialStatusesHasColorColumn(): bool
    {
        if ($this->materialStatusesHasColorColumnCache === null) {
            $this->materialStatusesHasColorColumnCache = $this->hasMaterialStatusesTable() && Schema::hasColumn('material_statuses', 'color');
        }

        return $this->materialStatusesHasColorColumnCache;
    }

    private function resolveRulePermissionForUser(
        MaterialShareRule $rule,
        int $authUserId,
        int $authUserSchoolId,
        Collection $memberGroupIds,
    ): string {
        $bestRank = 0;
        $bestPermission = MaterialShareTarget::PERMISSION_READ_ONLY;

        $targets = $rule->targets instanceof Collection ? $rule->targets : collect();
        foreach ($targets as $target) {
            if (! $target instanceof MaterialShareTarget) {
                continue;
            }

            $applies = false;
            if ((string) $target->target_type === MaterialShareTarget::TARGET_USER) {
                $applies = (int) ($target->user_id ?? 0) === $authUserId;
            } elseif ((string) $target->target_type === MaterialShareTarget::TARGET_EVERYONE) {
                $audienceScope = (string) ($target->audience_scope ?? '');
                if ($audienceScope === MaterialShareTarget::AUDIENCE_SCOPE_GLOBAL) {
                    $applies = true;
                } elseif ($audienceScope === MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL) {
                    $applies = (int) $rule->school_id === $authUserSchoolId;
                }
            } elseif ((string) $target->target_type === MaterialShareTarget::TARGET_GROUP) {
                $groupId = (int) ($target->user_group_id ?? 0);
                $applies = $groupId > 0 && $memberGroupIds->contains($groupId);
            }

            if (! $applies) {
                continue;
            }

            $permission = (string) ($target->permission ?: MaterialShareTarget::PERMISSION_READ_ONLY);
            $rank = $this->permissionRank($permission);
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $bestPermission = $permission;
            }
        }

        return $bestPermission;
    }

    private function permissionRank(string $permission): int
    {
        return match ($permission) {
            MaterialShareTarget::PERMISSION_FULL_ACCESS => 3,
            MaterialShareTarget::PERMISSION_READ_WRITE => 2,
            MaterialShareTarget::PERMISSION_READ_ONLY => 1,
            default => 0,
        };
    }

    public function lookupUsers(Request $request)
    {
        $authUser = $this->materialsShareUser();

        $search = trim((string) $request->query('search', ''));
        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $tokens = preg_split('/\s+/', $search) ?: [];

        $rows = User::query()
            ->where('school_id', (int) $authUser->school_id)
            ->whereKeyNot((int) $authUser->id)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', $this->materialsAccessRoleNames());
            })
            ->where(function ($query) use ($search, $tokens) {
                $like = '%'.$search.'%';
                $query
                    ->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('short', 'like', $like);

                foreach ($tokens as $token) {
                    $token = trim((string) $token);
                    if ($token === '') {
                        continue;
                    }
                    $query->orWhere('first_name', 'like', '%'.$token.'%')
                        ->orWhere('last_name', 'like', '%'.$token.'%')
                        ->orWhere('short', 'like', '%'.$token.'%');
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(25)
            ->get(['id', 'first_name', 'last_name', 'short', 'email']);

        return response()->json([
            'data' => $rows->map(function (User $user) {
                $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));

                return [
                    'id' => (int) $user->id,
                    'label' => $fullName !== '' ? $fullName : ($user->email ?: 'Benutzer'),
                    'first_name' => (string) ($user->first_name ?? ''),
                    'last_name' => (string) ($user->last_name ?? ''),
                    'short' => (string) ($user->short ?? ''),
                    'email' => (string) ($user->email ?? ''),
                ];
            })->values(),
        ]);
    }

    public function lookupGroups(Request $request)
    {
        $authUser = $this->materialsShareUser();

        $type = (string) $request->query('type', '');
        $category = (string) $request->query('category', '');
        $validCategoriesByType = [
            UserGroup::TYPE_SCHOOL => ['classes', 'teachers', 'parents', 'own'],
            UserGroup::TYPE_MATERIALS => [],
            UserGroup::TYPE_OWN => ['course_groups', 'course_parent_groups', 'own'],
        ];

        if (! array_key_exists($type, $validCategoriesByType)) {
            throw ValidationException::withMessages([
                'type' => ['Ungültiger Gruppentyp.'],
            ]);
        }

        if ($category !== '' && ! in_array($category, $validCategoriesByType[$type], true)) {
            throw ValidationException::withMessages([
                'category' => ['Ungültige Gruppenkategorie.'],
            ]);
        }

        $schoolId = (int) $authUser->school_id;
        $groups = UserGroup::query()
            ->where('school_id', $schoolId)
            ->where('type', $type)
            ->when($type === UserGroup::TYPE_OWN, fn ($query) => $query->where('created_by_user_id', (int) $authUser->id))
            ->withCount('members')
            ->orderBy('name')
            ->get(['id', 'school_id', 'type', 'name', 'description', 'created_by_user_id', 'teaching_course_id', 'teaching_course_group_type']);

        $groups = $groups
            ->filter(fn (UserGroup $group) => $this->matchesLookupGroupCategory($group, $category, $schoolId))
            ->values();

        return response()->json([
            'data' => $groups->map(fn (UserGroup $group) => [
                'id' => (int) $group->id,
                'type' => (string) $group->type,
                'type_label' => $this->groupTypeLabel((string) $group->type),
                'name' => (string) $group->name,
                'description' => (string) ($group->description ?? ''),
                'members_count' => (int) ($group->members_count ?? 0),
                'label' => (string) $group->name,
                'is_system_default' => $this->isSystemDefaultSchoolGroup($group, $schoolId),
                'is_parent_group' => $this->isParentSchoolGroup($group) || $this->isSystemManagedCourseParentGroup($group),
                'is_all_school_members_group' => $this->isAllSchoolMembersGroup($group),
                'teaching_course_id' => $group->teaching_course_id ? (int) $group->teaching_course_id : null,
                'teaching_course_group_type' => $this->isSystemManagedCourseGroup($group)
                    ? $this->teachingCourseGroupType($group)
                    : null,
            ])->values(),
        ]);
    }

    public function lookupSchools(Request $request)
    {
        $authUser = $this->materialsShareUser();

        $schools = School::query()
            ->where('id', '!=', (int) $authUser->school_id)
            ->orderBy('long_name')
            ->get(['id', 'long_name', 'short_name']);

        return response()->json([
            'data' => $schools->map(function (School $school) {
                $label = trim((string) ($school->long_name ?: $school->short_name ?: 'Schule'));

                return [
                    'id' => (int) $school->id,
                    'label' => $label,
                    'long_name' => (string) ($school->long_name ?? ''),
                    'short_name' => (string) ($school->short_name ?? ''),
                ];
            })->values(),
        ]);
    }

    public function lookupExternalUser(Request $request)
    {
        $authUser = $this->materialsShareUser();

        $data = $request->validate(
            [
                'target_school_id' => ['required', 'integer', 'min:1'],
                'user_email' => ['required', 'string', 'email'],
            ],
            [
                'target_school_id.required' => 'Bitte Schule wählen.',
                'target_school_id.integer' => 'Bitte Schule wählen.',
                'target_school_id.min' => 'Bitte Schule wählen.',
                'user_email.required' => 'Bitte E-Mail-Adresse eingeben.',
                'user_email.email' => 'Bitte eine gültige E-Mail-Adresse eingeben.',
            ]
        );

        $targetSchoolId = (int) $data['target_school_id'];
        $targetSchool = School::query()
            ->where('id', '!=', (int) $authUser->school_id)
            ->orderBy('long_name')
            ->find($targetSchoolId);

        if (! $targetSchool) {
            throw ValidationException::withMessages([
                'target_school_id' => ['Schule wurde nicht gefunden.'],
            ]);
        }

        $email = mb_strtolower(trim((string) $data['user_email']));
        $user = User::query()
            ->where('school_id', $targetSchoolId)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', $this->materialsAccessRoleNames());
            })
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first(['id', 'school_id', 'first_name', 'last_name', 'email']);

        if (! $user) {
            return response()->json([
                'data' => [
                    'exists' => false,
                ],
            ]);
        }

        $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));

        return response()->json([
            'data' => [
                'exists' => true,
                'id' => (int) $user->id,
                'school_id' => (int) $user->school_id,
                'school_label' => trim((string) ($targetSchool->long_name ?: $targetSchool->short_name ?: 'Schule')),
                'label' => $fullName !== '' ? $fullName : ((string) $user->email),
                'email' => (string) $user->email,
            ],
        ]);
    }

    public function storeTarget(Request $request)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();
        $workspaceId = $this->activeWorkspaceIdForUser($authUser);

        $data = $request->validate([
            'scope_type' => ['required', 'string', Rule::in(MaterialShareRule::SCOPES)],
            'scope_id' => ['nullable', 'integer', 'min:1'],
            'target_type' => ['required', 'string', Rule::in(MaterialShareTarget::TARGETS)],
            'audience_scope' => ['nullable', 'string', Rule::in(MaterialShareTarget::AUDIENCE_SCOPES)],
            'permission' => ['required', 'string', Rule::in(MaterialShareTarget::PERMISSIONS)],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'target_school_id' => ['nullable', 'integer', 'min:1'],
            'user_email' => ['nullable', 'string', 'email'],
            'user_group_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $scopeType = (string) $data['scope_type'];
        $scopeId = $scopeType === MaterialShareRule::SCOPE_ALL ? null : (int) ($data['scope_id'] ?? 0);
        if ($scopeType !== MaterialShareRule::SCOPE_ALL && $scopeId <= 0) {
            throw ValidationException::withMessages([
                'scope_id' => ['Für diese Ebene ist eine gültige ID erforderlich.'],
            ]);
        }

        $this->assertPermissionAllowedForScope((string) $data['permission'], $scopeType);

        $targetType = (string) $data['target_type'];
        $audienceScope = null;
        $userId = null;
        $groupId = null;

        if ($targetType === MaterialShareTarget::TARGET_EVERYONE) {
            $audienceScope = (string) ($data['audience_scope'] ?? '');
            if (! in_array($audienceScope, MaterialShareTarget::AUDIENCE_SCOPES, true)) {
                throw ValidationException::withMessages([
                    'audience_scope' => ['Bitte Reichweite für "Jeder" wählen.'],
                ]);
            }
        } elseif ($targetType === MaterialShareTarget::TARGET_USER) {
            $userId = (int) ($data['user_id'] ?? 0);
            $user = null;
            if ($userId > 0) {
                $user = User::query()
                    ->where('school_id', (int) $authUser->school_id)
                    ->find($userId);
            } else {
                $targetSchoolId = (int) ($data['target_school_id'] ?? 0);
                $userEmail = trim((string) ($data['user_email'] ?? ''));

                if ($targetSchoolId <= 0) {
                    throw ValidationException::withMessages([
                        'target_school_id' => ['Bitte Schule wählen.'],
                    ]);
                }
                if ($userEmail === '') {
                    throw ValidationException::withMessages([
                        'user_email' => ['Bitte E-Mail-Adresse eingeben.'],
                    ]);
                }

                $school = School::query()->find($targetSchoolId);
                if (! $school) {
                    throw ValidationException::withMessages([
                        'target_school_id' => ['Schule wurde nicht gefunden.'],
                    ]);
                }

                $user = User::query()
                    ->where('school_id', $targetSchoolId)
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($userEmail)])
                    ->first();
            }

            if (! $user) {
                throw ValidationException::withMessages([
                    'user_id' => ['Benutzer wurde nicht gefunden.'],
                ]);
            }
            $userId = (int) $user->id;
        } elseif ($targetType === MaterialShareTarget::TARGET_GROUP) {
            $groupId = (int) ($data['user_group_id'] ?? 0);
            $group = UserGroup::query()
                ->where('school_id', (int) $authUser->school_id)
                ->whereIn('type', [UserGroup::TYPE_MATERIALS, UserGroup::TYPE_OWN])
                ->find($groupId);
            if (! $group) {
                throw ValidationException::withMessages([
                    'user_group_id' => ['Gruppe wurde nicht gefunden.'],
                ]);
            }
            if ((string) $group->type === UserGroup::TYPE_OWN && (int) $group->created_by_user_id !== (int) $authUser->id) {
                abort(403, 'Eigene Gruppen können nur vom Ersteller verwendet werden.');
            }
        }

        $rule = MaterialShareRule::query()
            ->where('school_id', (int) $authUser->school_id)
            ->where('created_by_user_id', (int) $authUser->id)
            ->when($workspaceId !== null, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->orderByDesc('id')
            ->first();

        if (! $rule) {
            $rule = MaterialShareRule::create([
                'school_id' => (int) $authUser->school_id,
                'created_by_user_id' => (int) $authUser->id,
                'workspace_id' => $workspaceId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'is_active' => true,
            ]);
        } else {
            if (! $rule->is_active) {
                $rule->is_active = true;
                $rule->save();
            }
        }

        $target = $this->findExistingTargetForScope(
            schoolId: (int) $authUser->school_id,
            creatorUserId: (int) $authUser->id,
            workspaceId: $workspaceId,
            scopeType: $scopeType,
            scopeId: $scopeId,
            targetType: $targetType,
            audienceScope: $audienceScope,
            userId: $userId,
            groupId: $groupId,
        );

        if (! $target) {
            $target = new MaterialShareTarget;
        }

        $target->material_share_rule_id = (int) $rule->id;
        $target->target_type = $targetType;
        $target->audience_scope = $audienceScope;
        $target->permission = (string) $data['permission'];
        $target->user_id = $targetType === MaterialShareTarget::TARGET_USER ? $userId : null;
        $target->user_group_id = $targetType === MaterialShareTarget::TARGET_GROUP ? $groupId : null;
        $target->save();

        $rule->refresh()->load([
            'creator:id,first_name,last_name,email',
            'targets',
            'targets.group:id,school_id,type,name,created_by_user_id',
            'targets.user:id,school_id,first_name,last_name,email',
            'targets.user.selectedSchool:id,long_name,short_name',
        ]);

        return response()->json([
            'message' => 'Freigabe gespeichert.',
            'rule' => $this->serializeRule($rule, (int) $authUser->school_id),
            'target_id' => (int) $target->id,
        ]);
    }

    public function destroyTarget(Request $request, MaterialShareTarget $material_share_target)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $material_share_target->loadMissing('rule');
        $rule = $material_share_target->rule;

        if (! $rule || (int) $rule->school_id !== (int) $authUser->school_id) {
            abort(404, 'Freigabe-Ziel nicht gefunden.');
        }

        $ruleId = (int) $rule->id;
        $material_share_target->delete();

        $hasTargets = MaterialShareTarget::query()
            ->where('material_share_rule_id', $ruleId)
            ->exists();

        if (! $hasTargets) {
            MaterialShareRule::query()->whereKey($ruleId)->delete();
        }

        return response()->json([
            'message' => 'Freigabe entfernt.',
        ]);
    }

    public function updateTarget(Request $request, MaterialShareTarget $material_share_target)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        $material_share_target->loadMissing('rule');
        $rule = $material_share_target->rule;

        if (! $rule || (int) $rule->school_id !== (int) $authUser->school_id) {
            abort(404, 'Freigabe-Ziel nicht gefunden.');
        }

        $data = $request->validate([
            'permission' => ['required', 'string', Rule::in(MaterialShareTarget::PERMISSIONS)],
        ]);

        $this->assertPermissionAllowedForScope((string) $data['permission'], (string) $rule->scope_type);

        $material_share_target->permission = (string) $data['permission'];
        $material_share_target->save();
        $rule->touch();

        $rule->refresh()->load([
            'creator:id,first_name,last_name,email',
            'targets',
            'targets.group:id,school_id,type,name,created_by_user_id',
            'targets.user:id,school_id,first_name,last_name,email',
            'targets.user.selectedSchool:id,long_name,short_name',
        ]);

        return response()->json([
            'message' => 'Freigabe-Berechtigung gespeichert.',
            'rule' => $this->serializeRule($rule, (int) $authUser->school_id),
            'target_id' => (int) $material_share_target->id,
        ]);
    }

    public function updateRule(Request $request, MaterialShareRule $material_share_rule)
    {
        $authUser = $this->materialsShareUser();
        $this->abortIfShareTablesMissing();

        if ((int) $material_share_rule->school_id !== (int) $authUser->school_id) {
            abort(404, 'Freigabe nicht gefunden.');
        }

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $material_share_rule->is_active = (bool) $data['is_active'];
        $material_share_rule->save();

        $material_share_rule->load([
            'creator:id,first_name,last_name,email',
            'targets',
            'targets.group:id,school_id,type,name,created_by_user_id',
            'targets.user:id,school_id,first_name,last_name,email',
            'targets.user.selectedSchool:id,long_name,short_name',
        ]);

        return response()->json([
            'message' => 'Freigabe-Status gespeichert.',
            'rule' => $this->serializeRule($material_share_rule, (int) $authUser->school_id),
        ]);
    }

    private function materialsShareUser()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }

    private function shareTablesAvailable(): bool
    {
        return Schema::hasTable('material_share_rules') && Schema::hasTable('material_share_targets');
    }

    /**
     * @return array<int, string>
     */
    private function materialsAccessRoleNames(): array
    {
        return [
            'super_admin',
            'admin',
            'materials_admin',
            'materials_moderator',
        ];
    }

    private function abortIfShareTablesMissing(): void
    {
        if ($this->shareTablesAvailable()) {
            return;
        }

        abort(409, 'Freigaben-Tabellen fehlen. Bitte Migration ausführen.');
    }

    private function findExistingTargetForScope(
        int $schoolId,
        int $creatorUserId,
        ?int $workspaceId,
        string $scopeType,
        ?int $scopeId,
        string $targetType,
        ?string $audienceScope,
        ?int $userId,
        ?int $groupId,
    ): ?MaterialShareTarget {
        return MaterialShareTarget::query()
            ->where('target_type', $targetType)
            ->when($targetType === MaterialShareTarget::TARGET_EVERYONE, fn ($query) => $query->where('audience_scope', $audienceScope))
            ->when($targetType === MaterialShareTarget::TARGET_USER, fn ($query) => $query->where('user_id', $userId))
            ->when($targetType === MaterialShareTarget::TARGET_GROUP, fn ($query) => $query->where('user_group_id', $groupId))
            ->whereHas('rule', function ($query) use ($schoolId, $creatorUserId, $workspaceId, $scopeType, $scopeId) {
                $query
                    ->where('school_id', $schoolId)
                    ->where('created_by_user_id', $creatorUserId)
                    ->when($workspaceId !== null, fn ($ruleQuery) => $ruleQuery->where('workspace_id', $workspaceId))
                    ->where('scope_type', $scopeType)
                    ->where('scope_id', $scopeId);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function activeWorkspaceIdForUser(User $user): ?int
    {
        if (
            ! Schema::hasTable('material_workspaces')
            || ! Schema::hasTable('material_share_rules')
            || ! Schema::hasColumn('material_share_rules', 'workspace_id')
        ) {
            return null;
        }

        $workspace = $this->workspaceService->resolveActiveWorkspace($user);

        return (int) $workspace->id;
    }

    private function serializeRule(MaterialShareRule $rule, int $schoolId): array
    {
        [$scopeLabel, $scopeObjectLabel] = $this->resolveScopeLabels($rule, $schoolId);

        $targets = $rule->targets
            ->map(fn (MaterialShareTarget $target) => $this->serializeTarget($target, $schoolId))
            ->filter()
            ->values();

        $creatorName = trim((string) (($rule->creator?->last_name ?? '').' '.($rule->creator?->first_name ?? '')));
        $creatorLabel = $creatorName !== '' ? $creatorName : ($rule->creator?->email ?: null);

        return [
            'id' => (int) $rule->id,
            'workspace_id' => $rule->workspace_id ? (int) $rule->workspace_id : null,
            'scope_type' => (string) $rule->scope_type,
            'scope_id' => $rule->scope_id ? (int) $rule->scope_id : null,
            'scope_label' => $scopeLabel,
            'scope_object_label' => $scopeObjectLabel,
            'is_active' => (bool) $rule->is_active,
            'targets' => $targets,
            'targets_count' => $targets->count(),
            'created_by_user_id' => $rule->created_by_user_id ? (int) $rule->created_by_user_id : null,
            'created_by_label' => $creatorLabel,
            'created_at' => optional($rule->created_at)?->toIso8601String(),
            'updated_at' => optional($rule->updated_at)?->toIso8601String(),
        ];
    }

    private function serializeTarget(MaterialShareTarget $target, int $schoolId): ?array
    {
        $permission = (string) ($target->permission ?: MaterialShareTarget::PERMISSION_READ_ONLY);

        if ($target->target_type === MaterialShareTarget::TARGET_EVERYONE) {
            $audienceScope = (string) ($target->audience_scope ?: MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL);

            return [
                'id' => (int) $target->id,
                'target_type' => MaterialShareTarget::TARGET_EVERYONE,
                'audience_scope' => $audienceScope,
                'audience_scope_label' => $this->audienceScopeLabel($audienceScope),
                'permission' => $permission,
                'permission_label' => $this->permissionLabel($permission),
                'label' => $this->audienceScopeLabel($audienceScope),
            ];
        }

        if ($target->target_type === MaterialShareTarget::TARGET_GROUP) {
            $group = $target->group;
            if (! $group || (int) $group->school_id !== $schoolId) {
                return null;
            }

            return [
                'id' => (int) $target->id,
                'target_type' => MaterialShareTarget::TARGET_GROUP,
                'user_group_id' => (int) $group->id,
                'permission' => $permission,
                'permission_label' => $this->permissionLabel($permission),
                'label' => $group->name,
                'meta' => [
                    'group_type' => (string) $group->type,
                    'group_type_label' => $this->groupTypeLabel((string) $group->type),
                ],
            ];
        }

        if ($target->target_type === MaterialShareTarget::TARGET_USER) {
            $user = $target->user;
            if (! $user) {
                return null;
            }

            $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));
            $targetSchoolId = (int) ($user->school_id ?? 0);
            $targetSchoolLabel = trim((string) ($user->selectedSchool?->long_name ?: $user->selectedSchool?->short_name ?: ''));

            return [
                'id' => (int) $target->id,
                'target_type' => MaterialShareTarget::TARGET_USER,
                'user_id' => (int) $user->id,
                'permission' => $permission,
                'permission_label' => $this->permissionLabel($permission),
                'label' => $fullName !== '' ? $fullName : ($user->email ?? 'Benutzer'),
                'meta' => [
                    'email' => $user->email,
                    'school_id' => $targetSchoolId,
                    'school_label' => $targetSchoolLabel !== '' ? $targetSchoolLabel : null,
                    'is_other_school' => $targetSchoolId > 0 && $targetSchoolId !== $schoolId,
                ],
            ];
        }

        return null;
    }

    private function assertPermissionAllowedForScope(string $permission, string $scopeType): void
    {
        if ($permission !== MaterialShareTarget::PERMISSION_FULL_ACCESS) {
            return;
        }

        if ($this->scopeAllowsFullAccess($scopeType)) {
            return;
        }

        throw ValidationException::withMessages([
            'permission' => ['VOLLZUGRIFF ist auf dieser Ebene aktuell nicht erlaubt.'],
        ]);
    }

    private function scopeAllowsFullAccess(string $scopeType): bool
    {
        return in_array($scopeType, [
            MaterialShareRule::SCOPE_ALL,
            MaterialShareRule::SCOPE_SUBJECT,
        ], true);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveScopeLabels(MaterialShareRule $rule, int $schoolId): array
    {
        $scopeType = (string) $rule->scope_type;
        $scopeId = $rule->scope_id ? (int) $rule->scope_id : null;

        if ($scopeType === MaterialShareRule::SCOPE_ALL) {
            return ['Alles', 'Alle Materialien'];
        }

        if (! $scopeId) {
            return [$this->scopeTypeLabel($scopeType), 'Unbekannt'];
        }

        return match ($scopeType) {
            MaterialShareRule::SCOPE_SUBJECT => [
                'Fach',
                MaterialSubject::query()->find($scopeId)?->name ?: 'Fach #'.$scopeId,
            ],
            MaterialShareRule::SCOPE_TOPIC => [
                'Thema',
                MaterialTopic::query()->find($scopeId)?->name ?: 'Thema #'.$scopeId,
            ],
            MaterialShareRule::SCOPE_UNIT => [
                'Einheit',
                MaterialUnit::query()->find($scopeId)?->name ?: 'Einheit #'.$scopeId,
            ],
            MaterialShareRule::SCOPE_MATERIAL => [
                'Material',
                MaterialCard::query()
                    ->where('school_id', $schoolId)
                    ->when((int) ($rule->created_by_user_id ?? 0) > 0, fn ($query) => $query->where('user_id', (int) $rule->created_by_user_id))
                    ->find($scopeId)?->title ?: 'Material #'.$scopeId,
            ],
            default => [$this->scopeTypeLabel($scopeType), '#'.$scopeId],
        };
    }

    private function scopeTypeLabel(string $scopeType): string
    {
        return match ($scopeType) {
            MaterialShareRule::SCOPE_ALL => 'Alles',
            MaterialShareRule::SCOPE_SUBJECT => 'Fach',
            MaterialShareRule::SCOPE_TOPIC => 'Thema',
            MaterialShareRule::SCOPE_UNIT => 'Einheit',
            MaterialShareRule::SCOPE_MATERIAL => 'Material',
            default => $scopeType,
        };
    }

    private function groupTypeLabel(string $groupType): string
    {
        return match ($groupType) {
            UserGroup::TYPE_MATERIALS => 'Materialiengruppe',
            UserGroup::TYPE_OWN => 'Eigene Gruppe',
            UserGroup::TYPE_SCHOOL => 'Schulgruppe',
            default => $groupType,
        };
    }

    private function matchesLookupGroupCategory(UserGroup $group, string $category, int $schoolId): bool
    {
        if ($category === '') {
            return true;
        }

        return match ((string) $group->type) {
            UserGroup::TYPE_SCHOOL => match ($category) {
                'classes' => $this->isSystemDefaultSchoolGroup($group, $schoolId)
                    && ! $this->isTeacherGroupName($this->normalizeGroupName((string) $group->name))
                    && ! $this->isParentSchoolGroup($group)
                    && ! $this->isAllSchoolMembersGroup($group),
                'teachers' => $this->isSystemDefaultSchoolGroup($group, $schoolId)
                    && $this->isTeacherGroupName($this->normalizeGroupName((string) $group->name)),
                'parents' => $this->isSystemDefaultSchoolGroup($group, $schoolId)
                    && $this->isParentSchoolGroup($group),
                'own' => ! $this->isSystemDefaultSchoolGroup($group, $schoolId),
                default => false,
            },
            UserGroup::TYPE_OWN => match ($category) {
                'course_groups' => $this->isSystemManagedCourseGroup($group)
                    && ! $this->isSystemManagedCourseParentGroup($group),
                'course_parent_groups' => $this->isSystemManagedCourseParentGroup($group),
                'own' => ! $this->isSystemManagedCourseGroup($group),
                default => false,
            },
            default => true,
        };
    }

    private function isSystemDefaultSchoolGroup(UserGroup $group, int $schoolId): bool
    {
        if ((string) $group->type !== UserGroup::TYPE_SCHOOL) {
            return false;
        }

        if ($this->isTeacherGroupName($this->normalizeGroupName((string) $group->name))) {
            return true;
        }

        return array_key_exists(
            $this->normalizeGroupName((string) $group->name),
            $this->requiredSchoolGroupNames($schoolId)
        );
    }

    private function requiredSchoolGroupNames(int $schoolId): array
    {
        $names = [
            $this->defaultTeacherGroupName(),
            $this->defaultAllSchoolMembersGroupName(),
        ];

        $classGroups = $this->buildImportClassGroupMappings($schoolId);
        foreach ($classGroups['by_class'] as $classData) {
            $names[] = $classData['name'];
            $names[] = $this->parentGroupName((string) $classData['name']);
        }

        foreach ($classGroups['by_family'] as $familyData) {
            $names[] = $familyData['name'];
            $names[] = $this->parentGroupName((string) $familyData['name']);
        }

        $normalizedMap = [];
        foreach ($names as $name) {
            $normalizedName = $this->normalizeGroupName($name);
            if ($normalizedName === '' || isset($normalizedMap[$normalizedName])) {
                continue;
            }

            $normalizedMap[$normalizedName] = $name;
        }

        return collect($normalizedMap)
            ->sortBy(fn (string $name) => $this->normalizeGroupName($name))
            ->all();
    }

    /**
     * @return array{
     *     by_class: array<string, array{name: string, import_ids: array<int, int>}>,
     *     by_family: array<string, array{name: string, import_ids: array<int, int>}>
     * }
     */
    private function buildImportClassGroupMappings(int $schoolId): array
    {
        $result = [
            'by_class' => [],
            'by_family' => [],
        ];

        if (! Schema::hasTable('import116')) {
            return $result;
        }

        $importRows = $this->import116QueryForActiveSchoolyear($schoolId)
            ->whereNotNull('class')
            ->orderByRaw('LOWER(class)')
            ->orderBy('id')
            ->get(['id', 'class']);

        if ($importRows->isEmpty()) {
            return $result;
        }

        $familiesWithVariants = [];

        foreach ($importRows as $row) {
            $className = trim((string) ($row->class ?? ''));
            if ($className === '') {
                continue;
            }

            $normalizedClassName = $this->normalizeGroupName($className);
            if (! isset($result['by_class'][$normalizedClassName])) {
                $result['by_class'][$normalizedClassName] = [
                    'name' => $className,
                    'import_ids' => [],
                ];
            }
            $result['by_class'][$normalizedClassName]['import_ids'][] = (int) $row->id;

            $familyClassName = $this->detectCombinedClassFamilyName($className);
            if (! is_string($familyClassName) || $familyClassName === '') {
                continue;
            }

            $normalizedFamilyName = $this->normalizeGroupName($familyClassName);
            if (! isset($familiesWithVariants[$normalizedFamilyName])) {
                $familiesWithVariants[$normalizedFamilyName] = [
                    'name' => $familyClassName,
                    'import_ids' => [],
                    'class_names' => [],
                ];
            }

            $familiesWithVariants[$normalizedFamilyName]['import_ids'][] = (int) $row->id;
            $familiesWithVariants[$normalizedFamilyName]['class_names'][$normalizedClassName] = true;
        }

        foreach ($result['by_class'] as $normalizedClassName => $classData) {
            $result['by_class'][$normalizedClassName]['import_ids'] = collect($classData['import_ids'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        foreach ($familiesWithVariants as $normalizedFamilyName => $familyData) {
            if (count($familyData['class_names']) < 2) {
                continue;
            }

            $result['by_family'][$normalizedFamilyName] = [
                'name' => (string) $familyData['name'],
                'import_ids' => collect($familyData['import_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        return $result;
    }

    private function detectCombinedClassFamilyName(string $className): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $className));
        if ($value === '') {
            return null;
        }

        $base = null;
        if (preg_match('/^(.+?)[\\-_\\/|:;]+.+$/u', $value, $matches) === 1) {
            $base = trim((string) ($matches[1] ?? ''));
        } else {
            $parts = preg_split('/\s+/u', $value) ?: [];
            if (count($parts) >= 2) {
                $base = trim((string) ($parts[0] ?? ''));
            } elseif (preg_match('/^(\\d{1,2}[[:alpha:]]{1,2})([[:alpha:]]{1,3})$/u', preg_replace('/\s+/u', '', $value) ?: '', $matches) === 1) {
                $base = trim((string) ($matches[1] ?? ''));
            }
        }

        if (! is_string($base) || $base === '') {
            return null;
        }

        $base = preg_replace('/\s+/u', '', $base) ?: '';
        if ($base === '') {
            return null;
        }

        if (preg_match('/^\\d{1,2}[[:alpha:]]{1,3}$/u', $base) !== 1) {
            return null;
        }

        return mb_strtoupper($base);
    }

    private function import116QueryForActiveSchoolyear(int $schoolId)
    {
        $query = Import116::query()->where('school_id', $schoolId);
        $activeSchoolyearId = $this->activeImportSchoolyearId($schoolId);

        if ($activeSchoolyearId) {
            return $query->where('schoolyear_id', $activeSchoolyearId);
        }

        return $query->whereRaw('1 = 0');
    }

    private function activeImportSchoolyearId(int $schoolId): ?int
    {
        if (! Schema::hasTable('school_tools')) {
            return null;
        }

        $schoolyearId = SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('active_schoolyear_id');

        return $schoolyearId ? (int) $schoolyearId : null;
    }

    private function normalizeGroupName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    private function defaultTeacherGroupName(): string
    {
        return 'Lehrer';
    }

    private function defaultAllSchoolMembersGroupName(): string
    {
        return 'Alle Schulmitglieder';
    }

    private function parentGroupName(string $baseGroupName): string
    {
        return trim($baseGroupName).' Eltern';
    }

    private function isTeacherGroupName(string $normalizedGroupName): bool
    {
        return in_array(
            $normalizedGroupName,
            [
                $this->normalizeGroupName($this->defaultTeacherGroupName()),
                $this->normalizeGroupName('Teacher'),
            ],
            true
        );
    }

    private function isParentSchoolGroup(UserGroup $group): bool
    {
        return (string) $group->type === UserGroup::TYPE_SCHOOL
            && str_ends_with($this->normalizeGroupName((string) $group->name), $this->normalizeGroupName(' Eltern'));
    }

    private function isAllSchoolMembersGroup(UserGroup $group): bool
    {
        return (string) $group->type === UserGroup::TYPE_SCHOOL
            && $this->normalizeGroupName((string) $group->name) === $this->normalizeGroupName($this->defaultAllSchoolMembersGroupName());
    }

    private function teachingCourseGroupType(UserGroup $group): string
    {
        return (string) ($group->teaching_course_group_type ?: UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS);
    }

    private function isSystemManagedCourseGroup(UserGroup $group): bool
    {
        return (string) $group->type === UserGroup::TYPE_OWN
            && ! empty($group->teaching_course_id);
    }

    private function isSystemManagedCourseParentGroup(UserGroup $group): bool
    {
        return $this->isSystemManagedCourseGroup($group)
            && $this->teachingCourseGroupType($group) === UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS;
    }

    private function permissionLabel(string $permission): string
    {
        return match ($permission) {
            MaterialShareTarget::PERMISSION_FULL_ACCESS => 'Vollzugriff',
            MaterialShareTarget::PERMISSION_READ_WRITE => 'Lesen/Schreiben',
            MaterialShareTarget::PERMISSION_READ_ONLY => 'Nur Lesen',
            default => $permission,
        };
    }

    private function audienceScopeLabel(string $scope): string
    {
        return match ($scope) {
            MaterialShareTarget::AUDIENCE_SCOPE_GLOBAL => 'Jeder (auch schulfremd)',
            MaterialShareTarget::AUDIENCE_SCOPE_SCHOOL => 'Nur Schulweit',
            default => 'Alle',
        };
    }
}
