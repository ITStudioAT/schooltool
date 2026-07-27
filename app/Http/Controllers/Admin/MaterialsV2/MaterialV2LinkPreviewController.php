<?php

namespace App\Http\Controllers\Admin\MaterialsV2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaterialsV2\MaterialV2LinkPreviewRequest;
use App\Services\MaterialsV2\MaterialV2LinkPreviewService;
use Illuminate\Http\JsonResponse;

class MaterialV2LinkPreviewController extends Controller
{
    public function __invoke(
        MaterialV2LinkPreviewRequest $request,
        MaterialV2LinkPreviewService $linkPreviewService,
    ): JsonResponse {
        return response()->json([
            'data' => $linkPreviewService->inspect((string) $request->validated('url')),
        ]);
    }
}
