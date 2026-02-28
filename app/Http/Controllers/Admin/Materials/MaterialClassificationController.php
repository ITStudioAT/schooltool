<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialSubjectStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialSubjectUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialTopicStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialTopicUpdateRequest;
use App\Http\Requests\Admin\Materials\MaterialUnitStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialUnitUpdateRequest;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialUnit;
use App\Services\Materials\MaterialService;
use Illuminate\Http\Request;

class MaterialClassificationController extends Controller
{
    public function storeSubject(MaterialSubjectStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validated()['data'];

        $subject = $service->createSubject(
            $authUser,
            (string) ($validated['name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => $subject->id,
                'name' => $subject->name,
            ],
        ], 200);
    }

    public function updateSubject(
        MaterialSubjectUpdateRequest $request,
        MaterialSubject $material_subject,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validated()['data'];

        $subject = $service->updateSubject(
            $authUser,
            $material_subject,
            (string) ($validated['name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => $subject->id,
                'name' => $subject->name,
            ],
        ], 200);
    }

    public function destroySubject(MaterialSubject $material_subject, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $service->deleteSubject($authUser, $material_subject);

        return response()->noContent();
    }

    public function moveSubject(Request $request, MaterialSubject $material_subject, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.direction' => ['required', 'string', 'in:up,down'],
        ]);

        $service->moveSubject(
            $authUser,
            $material_subject,
            (string) ($validated['data']['direction'] ?? '')
        );

        return response()->noContent();
    }

    public function convertSubjectToTopic(Request $request, MaterialSubject $material_subject, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.target_subject_id' => ['required', 'integer', 'min:1'],
        ]);

        $targetSubject = MaterialSubject::query()->findOrFail((int) ($validated['data']['target_subject_id'] ?? 0));

        $topic = $service->convertSubjectToTopic($authUser, $material_subject, $targetSubject);

        return response()->json([
            'data' => [
                'id' => $topic->id,
                'name' => $topic->name,
                'subject_id' => $topic->subject_id,
            ],
        ], 200);
    }

    public function storeTopic(MaterialTopicStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validated()['data'];
        $subjectId = (int) ($validated['subject_id'] ?? 0);

        $subject = MaterialSubject::query()->findOrFail($subjectId);

        $topic = $service->createTopic(
            $authUser,
            $subject,
            (string) ($validated['name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => $topic->id,
                'name' => $topic->name,
                'subject_id' => $topic->subject_id,
            ],
        ], 200);
    }

    public function updateTopic(
        MaterialTopicUpdateRequest $request,
        MaterialTopic $material_topic,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validated()['data'];

        $topic = $service->updateTopic(
            $authUser,
            $material_topic,
            (string) ($validated['name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => $topic->id,
                'name' => $topic->name,
                'subject_id' => $topic->subject_id,
            ],
        ], 200);
    }

    public function destroyTopic(MaterialTopic $material_topic, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $service->deleteTopic($authUser, $material_topic);

        return response()->noContent();
    }

    public function moveTopic(Request $request, MaterialTopic $material_topic, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.direction' => ['required', 'string', 'in:up,down'],
        ]);

        $service->moveTopic(
            $authUser,
            $material_topic,
            (string) ($validated['data']['direction'] ?? '')
        );

        return response()->noContent();
    }

    public function moveTopicToSubject(Request $request, MaterialTopic $material_topic, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.target_subject_id' => ['required', 'integer', 'min:1'],
        ]);

        $targetSubject = MaterialSubject::query()->findOrFail((int) ($validated['data']['target_subject_id'] ?? 0));

        $topic = $service->moveTopicToSubject($authUser, $material_topic, $targetSubject);

        return response()->json([
            'data' => [
                'id' => $topic->id,
                'name' => $topic->name,
                'subject_id' => $topic->subject_id,
            ],
        ], 200);
    }

    public function convertTopicToUnit(Request $request, MaterialTopic $material_topic, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.target_topic_id' => ['required', 'integer', 'min:1'],
        ]);

        $targetTopic = MaterialTopic::query()->findOrFail((int) ($validated['data']['target_topic_id'] ?? 0));

        $unit = $service->convertTopicToUnit($authUser, $material_topic, $targetTopic);

        return response()->json([
            'data' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'topic_id' => $unit->topic_id,
            ],
        ], 200);
    }

    public function convertTopicToSubject(Request $request, MaterialTopic $material_topic, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.new_subject_name' => ['required', 'string', 'max:255'],
        ]);

        $subject = $service->convertTopicToSubject(
            $authUser,
            $material_topic,
            (string) ($validated['data']['new_subject_name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => $subject->id,
                'name' => $subject->name,
            ],
        ], 200);
    }

    public function storeUnit(MaterialUnitStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validated()['data'];
        $topicId = (int) ($validated['topic_id'] ?? 0);
        $allowDuplicate = (bool) ($validated['allow_duplicate'] ?? false);

        $topic = MaterialTopic::query()->findOrFail($topicId);

        $unit = $service->createUnit(
            $authUser,
            $topic,
            (string) ($validated['name'] ?? ''),
            $allowDuplicate
        );

        return response()->json([
            'data' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'topic_id' => $unit->topic_id,
            ],
        ], 200);
    }

    public function updateUnit(
        MaterialUnitUpdateRequest $request,
        MaterialUnit $material_unit,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validated()['data'];

        $unit = $service->updateUnit(
            $authUser,
            $material_unit,
            (string) ($validated['name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'topic_id' => $unit->topic_id,
            ],
        ], 200);
    }

    public function destroyUnit(MaterialUnit $material_unit, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $service->deleteUnit($authUser, $material_unit);

        return response()->noContent();
    }

    public function moveUnit(Request $request, MaterialUnit $material_unit, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.direction' => ['required', 'string', 'in:up,down'],
        ]);

        $service->moveUnit(
            $authUser,
            $material_unit,
            (string) ($validated['data']['direction'] ?? '')
        );

        return response()->noContent();
    }

    public function moveUnitToTopic(Request $request, MaterialUnit $material_unit, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.target_topic_id' => ['required', 'integer', 'min:1'],
        ]);

        $targetTopic = MaterialTopic::query()->findOrFail((int) ($validated['data']['target_topic_id'] ?? 0));

        $unit = $service->moveUnitToTopic($authUser, $material_unit, $targetTopic);

        return response()->json([
            'data' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'topic_id' => $unit->topic_id,
            ],
        ], 200);
    }

    public function convertUnitToTopic(Request $request, MaterialUnit $material_unit, MaterialService $service)
    {
        $authUser = $this->authorizeForClassificationManagement();
        $validated = $request->validate([
            'data.target_subject_id' => ['required', 'integer', 'min:1'],
            'data.new_topic_name' => ['required', 'string', 'max:255'],
        ]);

        $targetSubject = MaterialSubject::query()->findOrFail((int) ($validated['data']['target_subject_id'] ?? 0));

        $topic = $service->convertUnitToTopic(
            $authUser,
            $material_unit,
            $targetSubject,
            (string) ($validated['data']['new_topic_name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => $topic->id,
                'name' => $topic->name,
                'subject_id' => $topic->subject_id,
            ],
        ], 200);
    }

    private function authorizeForClassificationManagement()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
