<?php

use App\ABA\Services\BuildSeedReplacementDraft;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->seedPath = base_path('ai/knowledge/aba/sources/aba-knowledge-seed-report.md');
    $this->proposalsDir = base_path('ai/knowledge/aba/sources/proposals');

    File::ensureDirectoryExists($this->proposalsDir);
});

test('replacement draft treats partially resolved sources as not fully proven in main text', function () {
    Carbon::setTestNow(Carbon::parse('2099-01-02 10:00:00'));

    $verificationPath = $this->proposalsDir.'/ai-seed-verification-2099-01-02.json';
    $resolutionPath = $this->proposalsDir.'/ai-seed-source-resolution-2099-01-02.json';
    $draftPath = $this->proposalsDir.'/seed-replacement-draft-2099-01-02.md';

    $paths = [$this->seedPath, $verificationPath, $resolutionPath, $draftPath];
    $backups = [];
    foreach ($paths as $path) {
        $backups[$path] = File::exists($path) ? File::get($path) : null;
    }

    $seedContent = <<<'MD'
---
title: "ABA – Abschlussarbeit an AHS: Knowledge Seed Report"
status: active
version: "1.0"
last_reviewed_at: "2026-03-14"
---
# ABA – Abschlussarbeit an AHS: Knowledge Seed Report

## 1. Einleitung
Grundlage.

## 2. Kontext
Kontexttext.

## 3. Rahmen
Rahmentext.

## 4. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 5. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 6. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 7. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 8. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 9. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 10. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 11. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 12. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 13. Abschnitt
Inhalt A.
Inhalt B.
Inhalt C.
Inhalt D.

## 14. Quellen und Verlässlichkeit
- **BMBWF-Erlass 2025 (AHS)**: Amtliche Quelle, bindend – URL noch zu recherchieren

## 15. Offene Fragen
- Platzhalter.
MD;

    $verification = [
        'results' => [
            [
                'issue_id' => 'issue-013',
                'issue_type' => 'source_placeholder',
                'section' => '14. Quellen und Verlässlichkeit',
                'original_text' => '- **BMBWF-Erlass 2025 (AHS)**: Amtliche Quelle, bindend – URL noch zu recherchieren',
                'verification_status' => 'unverified',
                'skipped_reason' => null,
            ],
        ],
    ];

    $resolution = [
        'items' => [
            [
                'issue_id' => 'issue-013',
                'issue_type' => 'source_placeholder',
                'original_text' => '- **BMBWF-Erlass 2025 (AHS)**: Amtliche Quelle, bindend – URL noch zu recherchieren',
                'resolution_status' => 'partially_resolved',
                'source_id' => 'BMBWF-2025',
                'source_title' => 'AHS-ABA-Richtlinien (offizielle AHS-ABA-Seite)',
                'url' => 'https://www.ahs-aba.at/schueler/planen/richtlinien',
                'reason' => 'Belastbare AHS-Referenzseite vorhanden, aber kein eindeutig zitierfähiger Direktlink zum genannten BMBWF-Erlass 2025.',
            ],
        ],
    ];

    File::put($this->seedPath, $seedContent);
    File::put($verificationPath, json_encode($verification, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    File::put($resolutionPath, json_encode($resolution, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    try {
        $result = app(BuildSeedReplacementDraft::class)->build();

        expect($result['success'])->toBeTrue()
            ->and(File::exists($draftPath))->toBeTrue();

        $draft = File::get($draftPath);

        expect($draft)
            ->toContain('Offizielle Referenz, konkrete zitierfähige Einzelfundstelle noch offen')
            ->toContain('nicht vollständig geklärt')
            ->toContain('## 15. Noch nicht abschließend geklärte Punkte')
            ->toContain('Quelle nur teilweise geklärt')
            ->toContain('Belastbare AHS-Referenzseite vorhanden');
    } finally {
        Carbon::setTestNow();

        foreach ($paths as $path) {
            $backup = $backups[$path];
            if (is_string($backup)) {
                File::put($path, $backup);
            } elseif (File::exists($path)) {
                File::delete($path);
            }
        }
    }
});
