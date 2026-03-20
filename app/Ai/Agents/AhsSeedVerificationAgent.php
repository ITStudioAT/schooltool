<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Verifiziert ABA-Seed-Claims gegen autorisierte Quellen mit strukturiertem Output.
 *
 * Gibt für jeden Claim zurück:
 *   - verification_status: verified | partially_verified | unverified | contradicted
 *   - confidence: high | medium | low
 *   - source_refs: belegende Quellen-IDs
 *   - reasoning_summary: Begründung in 1-2 Sätzen
 *   - recommended_rewrite: verbesserte Formulierung (oder null wenn keine nötig)
 *   - unresolved_points: offene Fragen (oder null wenn keine)
 *   - scope_violation: true wenn BMHS-Scope-Verletzung erkannt
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[MaxTokens(1024)]
#[Temperature(0.0)]
class AhsSeedVerificationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        Du bist ein strenger AHS-ABA-Verifikationsassistent für Österreich.

        GOVERNANCE (absolut verbindlich):
        1. Nur AHS-Scope. Niemals BMHS (HTL, HAK, HAS, HASCH, Handelsakademie, berufsbildend).
        2. Verifiziere ausschließlich anhand des übergebenen Quelleninhalts – keine Annahmen.
        3. Halluziniere keine Quellen oder Fakten.
        4. Bei Scope-Verletzung (BMHS-Begriff im Claim): scope_violation = true, status = contradicted.
        5. Deine Ausgabe ist maschinenlesbar (JSON) – halte das Schema strikt ein.

        Bewertungsskala:
        - verified: Claim ist vollständig durch Quelle belegt
        - partially_verified: Claim ist teilweise belegt; Restunsicherheit bleibt
        - unverified: Claim ist weder belegt noch widerlegt (fehlende Quellenlage)
        - contradicted: Claim widerspricht belegter Quelle oder enthält BMHS-Scope-Verletzung
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'verification_status' => $schema->string()
                ->enum(['verified', 'partially_verified', 'unverified', 'contradicted'])
                ->required(),
            'confidence' => $schema->string()
                ->enum(['high', 'medium', 'low'])
                ->required(),
            'source_refs' => $schema->array()
                ->items($schema->string())
                ->required(),
            'reasoning_summary' => $schema->string()->required(),
            'recommended_rewrite' => $schema->string()->nullable()->required(),
            'unresolved_points' => $schema->string()->nullable()->required(),
            'scope_violation' => $schema->boolean()->required(),
        ];
    }
}
