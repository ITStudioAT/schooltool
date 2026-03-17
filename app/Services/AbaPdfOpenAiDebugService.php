<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AbaPdfOpenAiDebugService
{
    private const MODEL = 'gpt-4o';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Du analysierst die Struktur eines AHS-ABA-Dokuments (Abschlussarbeit an allgemeinbildenden höheren Schulen, Österreich).

Deine Aufgabe ist ausschließlich die STRUKTURERKENNUNG – kein pädagogisches Urteil, keine inhaltliche Bewertung.

Erkenne, welche der folgenden Dokumentzonen vorhanden sind:
- titlepage: Titelseite (Titel, Schule, Jahr, Autor)
- abstract_de: Deutsches Abstract / Kurzfassung
- abstract_en: Englisches Abstract / Summary
- toc: Inhaltsverzeichnis
- introduction: Einleitung
- main_part: Hauptteil (ein oder mehrere Kapitel)
- conclusion: Schluss / Fazit / Resümee
- bibliography: Literaturverzeichnis / Quellenverzeichnis
- declaration: Eidesstattliche Erklärung / Selbstständigkeitserklärung
- appendix: Anhang
- figure_index: Abbildungsverzeichnis / Tabellenverzeichnis

Sei präzise bei Confidence:
- high: Klare Überschrift oder eindeutiges Strukturmerkmal gefunden
- medium: Wahrscheinlich vorhanden, aber nicht eindeutig erkennbar
- low: Nur aus Kontext erschlossen, unsicher
PROMPT;

    /**
     * @return array<string, mixed>
     */
    public function analyze(UploadedFile $file): array
    {
        $base64Pdf = base64_encode(file_get_contents($file->getRealPath()));
        $apiKey = config('services.openai.api_key');

        $schema = $this->buildJsonSchema();

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => self::MODEL,
                'max_tokens' => 2000,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => self::SYSTEM_PROMPT,
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Analysiere die Struktur dieses ABA-Dokuments. Erkenne alle vorhandenen Zonen und halte dich strikt an das JSON-Schema.',
                            ],
                            [
                                'type' => 'file',
                                'file' => [
                                    'filename' => $file->getClientOriginalName(),
                                    'file_data' => 'data:application/pdf;base64,'.$base64Pdf,
                                ],
                            ],
                        ],
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'aba_structure_analysis',
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::error('AbaPdfOpenAiDebugService: OpenAI API-Fehler', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'OpenAI API-Fehler: '.$response->status().' – '.$response->json('error.message', 'Unbekannter Fehler'),
            ];
        }

        $content = $response->json('choices.0.message.content');

        if (! $content) {
            return [
                'success' => false,
                'error' => 'Keine Antwort von OpenAI erhalten.',
            ];
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'OpenAI-Antwort konnte nicht als JSON geparst werden.',
            ];
        }

        return [
            'success' => true,
            'model' => self::MODEL,
            'filename' => $file->getClientOriginalName(),
            'result' => $parsed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJsonSchema(): array
    {
        $booleanField = ['type' => 'boolean'];
        $confidenceEnum = ['type' => 'string', 'enum' => ['high', 'medium', 'low']];
        $stringField = ['type' => 'string'];
        $stringOrNull = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'titlepage_detected', 'abstract_de_detected', 'abstract_en_detected',
                'toc_detected', 'introduction_detected', 'main_part_detected',
                'conclusion_detected', 'bibliography_detected', 'declaration_detected',
                'appendix_detected', 'figure_index_detected',
                'zones_found', 'missing_required_parts', 'suspicious_items',
                'sequence_observations', 'notes', 'overall_confidence',
            ],
            'properties' => [
                'titlepage_detected' => $booleanField,
                'abstract_de_detected' => $booleanField,
                'abstract_en_detected' => $booleanField,
                'toc_detected' => $booleanField,
                'introduction_detected' => $booleanField,
                'main_part_detected' => $booleanField,
                'conclusion_detected' => $booleanField,
                'bibliography_detected' => $booleanField,
                'declaration_detected' => $booleanField,
                'appendix_detected' => $booleanField,
                'figure_index_detected' => $booleanField,
                'zones_found' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['zone', 'confidence', 'evidence', 'page_hint'],
                        'properties' => [
                            'zone' => $stringField,
                            'confidence' => $confidenceEnum,
                            'evidence' => $stringField,
                            'page_hint' => $stringOrNull,
                        ],
                    ],
                ],
                'missing_required_parts' => ['type' => 'array', 'items' => $stringField],
                'suspicious_items' => ['type' => 'array', 'items' => $stringField],
                'sequence_observations' => ['type' => 'array', 'items' => $stringField],
                'notes' => ['type' => 'array', 'items' => $stringField],
                'overall_confidence' => $confidenceEnum,
            ],
        ];
    }
}
