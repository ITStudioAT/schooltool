<?php

namespace App\Http\Controllers\Admin\ABA;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AbaSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $analysis = config('aba_analysis');
        $pandoc = config('aba_pandoc');
        $documentRules = config('aba_document_rules');

        return response()->json([
            'analysis' => [
                'debug_log_enabled' => $analysis['debug_log_enabled'] ?? false,
                'openai_normalization_enabled' => $analysis['openai_normalization_enabled'] ?? false,
                'openai_model' => $analysis['openai_model'] ?? null,
                'auto_approve_confidence' => $analysis['auto_approve_confidence'] ?? 0.82,
                'max_block_text_length' => $analysis['max_block_text_length'] ?? 8000,
                'max_section_text_length' => $analysis['max_section_text_length'] ?? 16000,
            ],
            'pandoc' => [
                'enabled' => $pandoc['enabled'] ?? false,
                'binary' => $pandoc['binary'] ?? 'pandoc',
                'timeout_seconds' => $pandoc['timeout_seconds'] ?? 30,
            ],
            'document_rules' => [
                'version' => $documentRules['version'] ?? null,
                'domain' => $documentRules['domain'] ?? null,
                'school_type' => $documentRules['scope']['school_type'] ?? null,
                'excluded_school_types' => $documentRules['scope']['excluded_school_types'] ?? [],
                'structure_sections_count' => count($documentRules['structure_rules']['sections'] ?? []),
                'document_zones_count' => count($documentRules['document_zone_rules']['zones'] ?? []),
                'sequence_rules_count' => count($documentRules['document_zone_rules']['sequence_rules'] ?? []),
                'formal_rules_count' => count($documentRules['formal_rules'] ?? []),
                'language_rules_count' => count($documentRules['language_rules'] ?? []),
                'citation_rules_count' => count($documentRules['citation_rules'] ?? []),
                'governance_rules_count' => count($documentRules['governance_and_safety_rules'] ?? []),
            ],
        ]);
    }
}
