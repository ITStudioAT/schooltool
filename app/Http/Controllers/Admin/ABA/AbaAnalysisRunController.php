<?php

namespace App\Http\Controllers\Admin\ABA;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ABA\AbaAnalysisResultResource;
use App\Http\Resources\Admin\ABA\AbaAnalysisRunResource;
use App\Models\Aba;
use App\Models\AbaAttachment;
use App\Services\AbaAnalysisService;
use App\Services\AbaExtractionPathComparisonService;
use App\Services\AbaLocalDocumentStructureExtractor;
use App\Services\AbaLocalDocumentTextExtractor;
use App\Services\AbaPandocAstNormalizerService;
use App\Services\AbaPandocDocxExtractionService;
use App\Services\AbaPandocReviewBuilderService;
use Illuminate\Http\JsonResponse;

class AbaAnalysisRunController extends Controller
{
    public function store(Aba $aba, AbaAnalysisService $analysisService): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $result = $analysisService->startRun($authUser, $aba);
        $run = $result['run'];
        $statusCode = $result['precondition_failed']
            ? 422
            : ($result['already_running'] ? 200 : 202);

        return response()->json([
            'message' => $result['message'],
            'data' => new AbaAnalysisRunResource($run),
        ], $statusCode);
    }

    public function showLatest(Aba $aba): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $aba->load('mainDocument');

        $latestRun = $aba->analysisRuns()
            ->with([
                'attachment',
                'results' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'aba' => [
                    'id' => (int) $aba->id,
                    'title' => $aba->title,
                    'student_name' => $aba->student_name,
                    'schoolyear_id' => $aba->schoolyear_id,
                ],
                'main_document' => $aba->mainDocument ? [
                    'id' => (int) $aba->mainDocument->id,
                    'original_name' => $aba->mainDocument->original_name,
                    'mime_type' => $aba->mainDocument->mime_type,
                    'size_bytes' => $aba->mainDocument->size_bytes !== null ? (int) $aba->mainDocument->size_bytes : null,
                    'created_at' => $aba->mainDocument->created_at?->toISOString(),
                ] : null,
                'analysis_run' => $latestRun ? new AbaAnalysisRunResource($latestRun) : null,
                'sections' => $latestRun ? AbaAnalysisResultResource::collection($latestRun->results)->resolve() : [],
            ],
        ]);
    }

    public function showDocumentReview(
        Aba $aba,
        AbaPandocDocxExtractionService $extractionService,
        AbaPandocAstNormalizerService $normalizerService,
        AbaLocalDocumentTextExtractor $localTextExtractor,
        AbaLocalDocumentStructureExtractor $localStructureExtractor,
        AbaExtractionPathComparisonService $comparisonService,
        AbaPandocReviewBuilderService $reviewBuilderService,
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $aba->load('mainDocument');
        $mainDocument = $aba->mainDocument;

        if (! $mainDocument instanceof AbaAttachment) {
            return response()->json([
                'data' => [
                    'available' => false,
                    'message' => 'Kein Hauptdokument vorhanden.',
                    'note' => 'Dokumentprüfung ist nur für ein vorhandenes DOCX-Hauptdokument verfügbar.',
                    'document' => null,
                ],
            ]);
        }

        $document = [
            'id' => (int) $mainDocument->id,
            'original_name' => $mainDocument->original_name,
            'mime_type' => $mainDocument->mime_type,
            'size_bytes' => $mainDocument->size_bytes !== null ? (int) $mainDocument->size_bytes : null,
            'created_at' => $mainDocument->created_at?->toISOString(),
        ];

        if (! $this->isDocxAttachment($mainDocument)) {
            return response()->json([
                'data' => [
                    'available' => false,
                    'message' => 'Die Dokumentprüfung ist derzeit nur für DOCX-Hauptdokumente verfügbar.',
                    'note' => 'Pandoc-Dokumentprüfung verwendet den DOCX-Primärpfad.',
                    'document' => $document,
                ],
            ]);
        }

        $legacyPayload = $this->buildLegacyComparisonPayload($mainDocument, $localTextExtractor, $localStructureExtractor);
        $pandocContext = $this->buildPandocComparisonPayload($mainDocument, $localTextExtractor, $extractionService, $normalizerService);
        $pandocPayload = is_array($pandocContext['payload'] ?? null) ? $pandocContext['payload'] : ['ok' => false, 'error' => 'Pandoc-Payload fehlt.'];
        $comparison = $comparisonService->compare($legacyPayload, $pandocPayload);

        $blocks = is_array($pandocPayload['blocks'] ?? null)
            ? array_values($pandocPayload['blocks'])
            : [];
        $review = $reviewBuilderService->buildReview($blocks);
        $summary = array_merge(
            $reviewBuilderService->buildSummary($blocks),
            [
                'document_title_candidate_count' => (int) ($review['counts']['document_title_candidate_count'] ?? 0),
                'empty_heading_count' => (int) ($review['counts']['empty_heading_count'] ?? 0),
                'probable_toc_artifact_count' => (int) ($review['counts']['probable_toc_artifact_count'] ?? 0),
                'suspicious_heading_count' => (int) ($review['counts']['suspicious_heading_count'] ?? 0),
            ]
        );

        return response()->json([
            'data' => [
                'available' => true,
                'note' => 'Interne Dokumentprüfung für Extraktion und Normalisierung. Keine finale ABA-Bewertung.',
                'document' => $document,
                'summary' => $summary,
                'review' => $review,
                'comparison' => $comparison,
                'legacy_local' => [
                    'ok' => (bool) ($legacyPayload['ok'] ?? false),
                    'error' => $legacyPayload['error'] ?? null,
                    'diagnostics' => is_array($legacyPayload['diagnostics'] ?? null) ? $legacyPayload['diagnostics'] : [],
                    'extraction' => is_array($legacyPayload['extraction'] ?? null) ? $legacyPayload['extraction'] : [],
                ],
                'extraction' => is_array($pandocContext['extraction'] ?? null) ? $pandocContext['extraction'] : [],
                'normalization' => is_array($pandocContext['normalization'] ?? null) ? $pandocContext['normalization'] : [],
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildLegacyComparisonPayload(
        AbaAttachment $attachment,
        AbaLocalDocumentTextExtractor $localTextExtractor,
        AbaLocalDocumentStructureExtractor $localStructureExtractor,
    ): array {
        try {
            $legacyExtraction = $localTextExtractor->extractDocument($attachment);
            $legacySections = $localStructureExtractor->extractSections(
                (string) ($legacyExtraction['text'] ?? ''),
                [
                    'outline' => is_array($legacyExtraction['outline'] ?? null) ? array_values($legacyExtraction['outline']) : [],
                    'toc_lines' => is_array($legacyExtraction['toc_lines'] ?? null) ? array_values($legacyExtraction['toc_lines']) : [],
                    'selected_candidate' => $legacyExtraction['selected_candidate'] ?? null,
                    'extraction_candidates' => is_array($legacyExtraction['candidates'] ?? null) ? array_values($legacyExtraction['candidates']) : [],
                ]
            );

            return [
                'ok' => true,
                'sections' => is_array($legacySections) ? array_values($legacySections) : [],
                'diagnostics' => $localStructureExtractor->lastDiagnostics(),
                'extraction' => [
                    'selected_candidate' => $legacyExtraction['selected_candidate'] ?? null,
                    'candidate_count' => is_array($legacyExtraction['candidates'] ?? null) ? count($legacyExtraction['candidates']) : 0,
                    'metadata' => is_array($legacyExtraction['metadata'] ?? null) ? $legacyExtraction['metadata'] : [],
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'error' => 'Lokaler Vergleichspfad fehlgeschlagen: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{
     *   payload:array<string,mixed>,
     *   extraction:array<string,mixed>,
     *   normalization:array<string,mixed>
     * }
     */
    private function buildPandocComparisonPayload(
        AbaAttachment $attachment,
        AbaLocalDocumentTextExtractor $localTextExtractor,
        AbaPandocDocxExtractionService $extractionService,
        AbaPandocAstNormalizerService $normalizerService,
    ): array {
        $extractionContext = [
            'engine' => 'pandoc',
            'format' => 'json',
            'runtime' => [],
            'metadata' => [],
            'warnings' => [],
        ];
        $normalizationContext = [
            'format' => 'aba_normalized_blocks_v1',
            'model_version' => null,
            'metadata' => [],
            'warnings' => [],
            'blocks' => [],
        ];
        $preparedPath = null;
        $isTemp = false;

        try {
            $preparedFile = $localTextExtractor->prepareLocalFile($attachment);
            $preparedPath = trim((string) ($preparedFile['absolute_path'] ?? ''));
            $isTemp = (bool) ($preparedFile['is_temp'] ?? false);

            if ($preparedPath === '') {
                return [
                    'payload' => [
                        'ok' => false,
                        'error' => 'Dokumentdatei konnte nicht vorbereitet werden.',
                    ],
                    'extraction' => $extractionContext,
                    'normalization' => $normalizationContext,
                ];
            }

            $extraction = $extractionService->extractFromPath($preparedPath);
            $extractionContext = [
                'engine' => $extraction['engine'] ?? 'pandoc',
                'format' => $extraction['format'] ?? null,
                'runtime' => is_array($extraction['runtime'] ?? null) ? $extraction['runtime'] : [],
                'metadata' => is_array($extraction['metadata'] ?? null) ? $extraction['metadata'] : [],
                'warnings' => is_array($extraction['warnings'] ?? null) ? $extraction['warnings'] : [],
            ];

            if (($extraction['ok'] ?? false) !== true) {
                return [
                    'payload' => [
                        'ok' => false,
                        'error' => (string) (($extraction['error']['message'] ?? null) ?: 'Pandoc-Extraktion fehlgeschlagen.'),
                    ],
                    'extraction' => $extractionContext,
                    'normalization' => $normalizationContext,
                ];
            }

            $normalization = $normalizerService->normalizeAst(
                is_array($extraction['ast'] ?? null)
                    ? $extraction['ast']
                    : []
            );
            $normalizationContext = [
                'format' => $normalization['format'] ?? null,
                'model_version' => $normalization['model_version'] ?? null,
                'metadata' => is_array($normalization['metadata'] ?? null) ? $normalization['metadata'] : [],
                'warnings' => is_array($normalization['warnings'] ?? null) ? $normalization['warnings'] : [],
                'blocks' => is_array($normalization['blocks'] ?? null) ? array_values($normalization['blocks']) : [],
            ];

            if (($normalization['ok'] ?? false) !== true) {
                return [
                    'payload' => [
                        'ok' => false,
                        'error' => (string) (($normalization['error']['message'] ?? null) ?: 'Pandoc-Normalisierung fehlgeschlagen.'),
                    ],
                    'extraction' => $extractionContext,
                    'normalization' => $normalizationContext,
                ];
            }

            return [
                'payload' => [
                    'ok' => true,
                    'blocks' => $normalizationContext['blocks'],
                    'model_version' => $normalization['model_version'] ?? null,
                    'extraction_engine' => $extraction['engine'] ?? 'pandoc',
                ],
                'extraction' => $extractionContext,
                'normalization' => $normalizationContext,
            ];
        } catch (\Throwable $exception) {
            return [
                'payload' => [
                    'ok' => false,
                    'error' => 'Pandoc-Pfad fehlgeschlagen: '.$exception->getMessage(),
                ],
                'extraction' => $extractionContext,
                'normalization' => $normalizationContext,
            ];
        } finally {
            if ($isTemp && is_string($preparedPath) && $preparedPath !== '') {
                @unlink($preparedPath);
            }
        }
    }

    private function isDocxAttachment(AbaAttachment $attachment): bool
    {
        $originalName = trim((string) ($attachment->original_name ?? ''));
        $storedPath = trim((string) ($attachment->path ?? ''));
        $name = $originalName !== '' ? $originalName : $storedPath;
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $mimeType = strtolower(trim((string) ($attachment->mime_type ?? '')));

        if (in_array($extension, ['docx', 'docm', 'dotx'], true)) {
            return true;
        }

        return in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-word.document.macroenabled.12',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        ], true);
    }

    private function canAccessAba(Aba $aba, int $userId, int $schoolId): bool
    {
        return (int) $aba->school_id === $schoolId && (int) $aba->user_id === $userId;
    }
}
