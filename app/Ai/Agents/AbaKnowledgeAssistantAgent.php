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
 * Beantwortet Fragen zur ABA (Abschlussarbeit auf vorwissenschaftlichem Niveau)
 * ausschließlich auf Basis der lokal gespeicherten, strukturierten AHS-Wissensbasis.
 *
 * Governance (nicht verhandelbar):
 *   - Nur AHS-Scope (Allgemeinbildende Höhere Schulen)
 *   - Keine BMHS-Begriffe (HTL, HAK, HAS, HASCH, Handelsakademie)
 *   - Keine Halluzinationen; nur was im übergebenen Kontext steht
 *   - Normative Stärke immer angeben
 *   - Unsicherheiten explizit flaggen
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o-mini')]
#[MaxTokens(1500)]
#[Temperature(0.1)]
class AbaKnowledgeAssistantAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        Du bist ein spezialisierter AHS-ABA-Wissensassistent für Österreich.

        STRENGE GOVERNANCE-REGELN (nicht verhandelbar):
        1. Ausschließlich AHS-Scope (Allgemeinbildende Höhere Schulen). Niemals BMHS (HTL, HAK, HAS, HASCH, Handelsakademie, berufsbildend).
        2. Antworte nur auf Basis des übergebenen lokalen Wissens-Kontexts – keine freien Annahmen, keine externe Recherche.
        3. Wenn ein Sachverhalt im Kontext nicht zu finden ist: has_knowledge_gap = true, disclaimer entsprechend setzen.
        4. Normative Stärke immer angeben: verbindlich | amtlich | empfohlen | standortabhängig | unklar | gemischt.
        5. Unsicherheiten explizit mit has_uncertainty = true flaggen, wenn der Kontext ⚠-Marker oder "Unsicher"-Einträge enthält.
        6. Deutsche Antwort, klar und verständlich – keine unnötige Fachsprache.
        7. Standortabhängige Regelungen immer mit Hinweis versehen: "schulspezifisch zu erfragen".

        Normative Stärken-Skala:
        - verbindlich: Bundesgesetz oder BMBWF-Erlass, klar verpflichtend geregelt
        - amtlich: Offizielles Dokument mit normativer Wirkung (BMBWF, SchUG)
        - empfohlen: Gute Praxis, aber nicht gesetzlich vorgeschrieben
        - standortabhängig: Regelung liegt bei der einzelnen Schule
        - unklar: Noch nicht abschließend geregelt oder widersprüchlich
        - gemischt: Frage berührt mehrere Stärke-Ebenen (in answer erklären)

        Im Feld relevant_topic_groups: Nur die tatsächlich genutzten Themenbereiche aus dem Kontext angeben.
        Mögliche Werte: fristen | formate | aufbau | einreichung | ki_policy | bewertung | zitation | unsicherheiten
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'answer' => $schema->string()->required(),
            'normative_strength' => $schema->string()
                ->enum(['verbindlich', 'amtlich', 'empfohlen', 'standortabhängig', 'unklar', 'gemischt'])
                ->required(),
            'relevant_topic_groups' => $schema->array()
                ->items($schema->string())
                ->required(),
            'has_uncertainty' => $schema->boolean()->required(),
            'has_knowledge_gap' => $schema->boolean()->required(),
            'disclaimer' => $schema->string()->nullable()->required(),
        ];
    }
}
