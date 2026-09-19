<?php

namespace App\Http\Controllers;

use App\Services\FeaturePreviewControlDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class FeaturePreviewControlController extends Controller
{
    public function __invoke(Request $request, FeaturePreviewControlDecision $decision): JsonResponse
    {
        $operation = $request->attributes->get('feature_preview_control_operation');
        $payload = $request->attributes->get('feature_preview_control_payload');
        abort_unless(is_string($operation) && is_array($payload), 403);

        try {
            return response()->json($decision->decide($operation, $payload));
        } catch (Throwable) {
            return response()->json(['error' => 'control_unavailable'], 503);
        }
    }
}
