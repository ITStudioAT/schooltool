<?php

namespace Tests\Support;

class TeachingWorkEvaluationFixture
{
    public static function reports(): array
    {
        $front = fn (string $title, string $subject): string => "---\ntitle: \"{$title}: E-Mails\"\nfach: \"{$subject}\"\nlehrperson: \"Test\"\n---\n\nMaximal sind **5,0 Punkte** erreichbar: MC 2,0; E-Mail 3,0.\n\n";

        return [
            'Gesamtuebersicht_Beurteilungen_Test.md' => $front('Gesamtübersicht', 'IT | 1A | 02.10.2026').
                "| Person / Klasse | Abgabe | Bewertung | MC / 2 | E-Mail / 3 | Gesamt / 5 |\n| --- | --- | --- | --- | --- | --- |\n".
                "| Ada Van Alpha / 1A | Vorhanden | Beurteilt | 2,0 | 2,5 | 4,5 |\n| Bea Beta / 1A | Fehlt | Offen | — | — | — |\n| Chris Gamma / 5F | Vorhanden | Beurteilt | 2,0 | 2,5 | 4,5 |\n",
            'Beurteilung_Van Alpha_Ada.md' => $front('Beurteilung', 'Ada Van Alpha | Klasse 1A | INF 1 | 02.10.2026').self::result(),
            'Beurteilung_Beta_Bea.md' => $front('Beurteilung', 'Bea Beta | Klasse 1A | INF 1 | 02.10.2026')."## Abgabe offen\n\n**Keine Gesamtsumme:** Es werden keine Nullpunkte angesetzt.\n",
            'Beurteilung_Gamma_Chris.md' => $front('Beurteilung', 'Chris Gamma | Klasse 5F | INF 1 | 02.10.2026').self::result(),
        ];
    }

    private static function result(): string
    {
        return "## Ergebnis\n\n**Gesamt: 4,5 von 5,0 Punkten.** MC: 2,0 von 2,0; E-Mail: 2,5 von 3,0.\n\n## E-Mail\n\n| Kriterium | Max. Punkte | Erreicht | Begründung |\n| --- | --- | --- | --- |\n| Text | 3,0 | 2,5 | Die Begründung bleibt vollständig. |\n";
    }

    public static function surnameFirstReports(): array
    {
        $front = fn (string $title, string $subject): string => "---\ntitle: \"{$title}: E-Mails\"\nfach: \"{$subject}\"\nlehrperson: \"Test\"\n---\n\n";
        $maximum = "Maximal sind **5,0 Punkte** erreichbar: MC 2,0; E-Mail 3,0.\n\n";

        return [
            'Gesamtübersicht.md' => $front('Gesamtübersicht', 'IT-Grundlagen | Abend INF1 | 1A, 5F | 02.10.2026').
                "3 Personen: zwei beurteilt; eine ohne Abgabe und Punktwert. Maximal 5,0 Punkte: MC 2,0; E-Mail 3,0.\n\n".
                "| Person / Klasse | Abgabe | Bewertung | MC / 2 | E-Mail / 3 | Gesamt / 5 |\n| --- | --- | --- | --- | --- | --- |\n".
                "| Van Alpha Ada / 1A | Vorhanden | Beurteilt | 2,00 | 2,50 | 4,50 |\n| Beta Bea / 1A | Fehlt | Offen | offen | offen | offen |\n| Van Gamma Delta Chris-Jo / 5F | Vorhanden | Beurteilt | 2,00 | 2,50 | 4,50 |\n: {tbl-colwidths=\"36 15 17 10 10 12\"}\n",
            'Beurteilung_Van Alpha_Ada.md' => $front('Beurteilung', 'Van Alpha Ada / 1A | INF 1 | 02.10.2026').$maximum.str_replace(['4,5 von', '2,0 von', '2,5 von'], ['4,50 von', '2,00 von', '2,50 von'], self::result()),
            'Beurteilung_Beta_Bea.md' => $front('Beurteilung', 'Beta Bea / 1A | INF 1 | 02.10.2026').$maximum.
                "## Abgabe offen\n\n| Multiple Choice | 2,0 | offen | Zugeordnete PDF fehlt. |\n\n**Keine abschließende Gesamtsumme.** Fehlende Abgaben werden nicht mit null Punkten bewertet.\n",
            'Beurteilung_Van Gamma Delta_Chris-Jo.md' => $front('Beurteilung', 'Van Gamma Delta Chris-Jo / 5F | INF 1 | 02.10.2026').$maximum.self::result(),
        ];
    }

    public static function payload(?array $reports = null): string
    {
        $documents = [];
        foreach ($reports ?? self::reports() as $name => $text) {
            $documents[] = compact('name', 'text');
        }

        return json_encode($documents, JSON_THROW_ON_ERROR);
    }
}
