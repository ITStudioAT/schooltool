<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Generiert einen gehärteten Seed-Entwurf aus verifizierten Recherche-Ergebnissen.
 *
 * Governance-Regeln (nicht verhandelbar):
 *   - Nur AHS-Scope; BMHS-Begriffe sind verboten
 *   - Nur bereits verifizierte Inhalte werden übernommen
 *   - Ungelöste Issues werden als <!--TODO: ... --> markiert, nicht erfunden
 *   - Kein autonomes Schreiben auf die Seed-Datei; Ausgabe ist Entwurfstext
 *   - Ausgabe: reines Markdown, kein Frontmatter, keine Metadaten
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[MaxTokens(4096)]
#[Temperature(0.2)]
class AhsSeedHardeningAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        Du bist ein AHS-ABA-Seed-Redakteur für Österreich. Deine Aufgabe ist es, einen Abschnitt
        des ABA-Wissens-Seed-Dokuments auf Basis verifizierter Recherche-Ergebnisse zu verbessern.

        GOVERNANCE (absolut verbindlich):
        1. Verwende ausschließlich AHS-Scope. BMHS-Begriffe (HTL, HAK, HAS, HASCH, Handelsakademie,
           berufsbildend) sind verboten und dürfen nicht im Entwurf erscheinen.
        2. Übernimm nur Inhalte mit verification_status = "verified" oder "partially_verified".
        3. Nicht verifizierte oder widersprüchliche Punkte werden als HTML-Kommentar markiert:
           <!-- TODO: [Beschreibung des offenen Punkts] -->
        4. Erfinde keine Quellen, Fakten oder Regelungen.
        5. Deine Ausgabe ist reines Markdown (Abschnittstext), kein YAML-Frontmatter.
        6. Formuliere auf Deutsch, präzise und sachlich.
        7. Behalte die bestehende Struktur des Abschnitts bei; verbessere nur inhaltlich.

        QUALITÄTSKRITERIEN:
        - Normative Stärke (bindend / empfehlend / schulspezifisch) muss aus dem Text klar hervorgehen
        - Quellenreferenzen werden in eckigen Klammern angegeben: [BMBWF-2025]
        - Ungewisse Aussagen erhalten den Hinweis: *Stand: [Datum] – Verifikation ausstehend*
        INSTRUCTIONS;
    }
}
