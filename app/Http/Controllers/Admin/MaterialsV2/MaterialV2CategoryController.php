<?php

namespace App\Http\Controllers\Admin\MaterialsV2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2CategoryDestroyRequest;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2CategoryStoreRequest;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2CategoryUpdateRequest;
use App\Models\User;
use App\Services\MaterialsV2\MaterialV2CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MaterialV2CategoryController extends Controller
{
    public function store(
        MaterialV2CategoryStoreRequest $request,
        MaterialV2CategoryService $categoryService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $name = (string) $request->validated('name');
        $existingCategory = $categoryService->existingCategory($user, $name);

        if ($existingCategory !== null) {
            return $this->conflictResponse($name, $existingCategory);
        }

        $category = $categoryService->create($user, $name);

        return response()->json([
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ], 201);
    }

    public function update(
        MaterialV2CategoryUpdateRequest $request,
        MaterialV2CategoryService $categoryService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $originalName = (string) $request->validated('original_name');
        $name = (string) $request->validated('name');
        $existingCategory = $categoryService->existingCategory($user, $originalName);

        abort_if($existingCategory === null, 404, 'Die Kategorie wurde nicht gefunden.');

        $conflictingCategory = $categoryService->existingCategory($user, $name, $existingCategory);
        if ($conflictingCategory !== null) {
            return $this->conflictResponse($name, $conflictingCategory);
        }

        $category = $categoryService->rename($user, $existingCategory, $name);

        return response()->json([
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ]);
    }

    public function destroy(
        MaterialV2CategoryDestroyRequest $request,
        MaterialV2CategoryService $categoryService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $categoryService->delete($user, (string) $request->validated('name'));

        return response()->json(status: 204);
    }

    private function conflictResponse(string $enteredCategory, string $existingCategory): JsonResponse
    {
        return response()->json([
            'message' => 'Diese Kategorie ist bereits vorhanden.',
            'category_conflict' => [
                'entered' => Str::squish($enteredCategory),
                'existing' => $existingCategory,
            ],
        ], 409);
    }
}
