<?php

namespace App\ABA\Services;

use App\ABA\Knowledge\SeedSourceRegistry;

/**
 * Ermittelt, welche Claims von einer Quelländerung betroffen sein könnten.
 *
 * Strategie V1: Wenn eine Quelle eine technische Änderung zeigt, werden alle
 * Claims aus den critical_topics dieser Quelle (gemäß source-registry.json)
 * als potenziell betroffen markiert.
 *
 * Das Mapping Topic → Claim-Keys ist explizit und auditierbar – keine KI, keine Magie.
 *
 * @return array{
 *   affected_topics: string[],
 *   affected_claim_keys: string[]
 * }
 */
class CompareClaimsToSources
{
    /** Vollständige, statische Zuordnung von Topics zu Claim-Keys. */
    private const TOPIC_TO_CLAIMS = [
        'Einführungsphase' => [
            'aba_voluntary_until_2028',
            'aba_mandatory_from_2028_29',
        ],
        'Fristen' => [
            'aba_opt_out_deadline',
            'submission_deadline_location_dependent',
        ],
        'Formate' => [
            'format_a_schriftlich_muendlich',
            'format_b_rein_muendlich',
        ],
        'Aufbau' => [
            'structure_titelblatt',
            'structure_inhaltsverzeichnis',
            'structure_abstract_de',
            'structure_abstract_en',
            'structure_einleitung',
            'structure_hauptteil',
            'structure_schluss',
            'structure_literaturverzeichnis',
            'structure_eigenstaendigkeitserklaerung',
        ],
        'Umfang' => [
            'no_fixed_page_count',
            'page_count_orientation_values',
        ],
        'Einreichung' => [
            'submission_digital_mandatory',
            'submission_printed_copies',
            'submission_begleitprotokoll',
        ],
        'Begleitprotokoll' => [
            'begleitprotokoll_content',
            'begleitprotokoll_signature',
        ],
        'KI-Policy' => [
            'ai_usage_allowed',
            'ai_documentation_required',
            'ai_content_must_be_verified',
            'ai_generated_text_must_be_marked',
            'ai_full_generation_is_plagiarism',
            'ai_rules_may_change',
        ],
        'Plagiatsprüfung' => [
            'plagiarism_check_mandatory',
            'plagiarism_consequence',
        ],
        'Bewertung' => [
            'evaluation_raster_2025_26',
            'evaluation_raster_from_2026_27',
            'evaluation_grading_scale',
        ],
        'Zitation' => [
            'citation_system_required',
            'citation_apa_recommended',
            'citation_web_with_access_date',
            'citation_min_sources_location_dependent',
        ],
        'Unsicherheiten' => [
            'uncertainty_format_b_availability',
            'uncertainty_evaluation_raster_final',
            'uncertainty_schools_in_implementation',
        ],
    ];

    public function __construct(private readonly SeedSourceRegistry $registry) {}

    public function findAffected(string $sourceId, bool $changeDetected): array
    {
        if (! $changeDetected) {
            return ['affected_topics' => [], 'affected_claim_keys' => []];
        }

        $source = $this->registry->findById($sourceId);
        $criticalTopics = $source['critical_topics'] ?? [];

        $affectedClaimKeys = [];

        foreach ($criticalTopics as $topic) {
            $claimKeys = self::TOPIC_TO_CLAIMS[$topic] ?? [];
            $affectedClaimKeys = array_merge($affectedClaimKeys, $claimKeys);
        }

        return [
            'affected_topics' => $criticalTopics,
            'affected_claim_keys' => array_values(array_unique($affectedClaimKeys)),
        ];
    }
}
