# ABA Seed-Refresh Policy

**Domain:** ABA – Abschlussarbeit an AHS (Allgemeinbildende Höhere Schulen)
**Version:** 1.0
**Erstellt:** 2026-03-14

---

## Zweck

Diese Policy beschreibt den kontrollierten Refresh-Prozess für `aba-knowledge-seed-report.md`
und die daraus abgeleitete Wissensbasis.

**Grundregel:** Die Seed-Datei wird NICHT unkontrolliert direkt überschrieben.
Jede Änderung durchläuft den folgenden 4-Phasen-Prozess.

---

## Refresh-Zyklus

### Phase 1: Freshness-Check (`aba:check-seed-freshness`)

Prüft automatisch, welche Claims laut `seed-review-state.json` überprüfungsbedürftig sind.

Auslöser:
- Manuell (mindestens quartalsweise empfohlen)
- Nach Erscheinen neuer Erlässe oder Handbuchupdates
- Bei Schuljahreswechsel (September)

Ergebnis:
- Konsolenausgabe mit Freshness-Report (verifiziert / needs_review / stale)

```bash
php artisan aba:check-seed-freshness
```

---

### Phase 2: Update-Vorschlag generieren (`aba:propose-seed-update`)

Generiert eine prüfbare Markdown-Datei unter `ai/knowledge/aba/sources/proposals/`.

Die Datei listet alle überprüfungsbedürftigen Claims mit:
- Aktuellem Status und change_risk
- Checkliste für die manuelle Review-Aktion (prüfen / aktualisieren / als verifiziert markieren / superseded setzen)

```bash
php artisan aba:propose-seed-update
```

---

### Phase 3: Manuelle Review und Freigabe

Ein Mensch prüft die Proposal-Datei anhand der Quellen (BMBWF-Erlass für AHS, ahs-aba.at, AHS-Handbuch etc.):

1. Quelle öffnen und Claim gegen aktuellen Stand prüfen.
2. Falls Claim noch korrekt: Status auf `verified` setzen:
   ```bash
   php artisan aba:apply-seed-update --claim=CLAIM_KEY --status=verified --reviewed-by="Name"
   ```
3. Falls Claim veraltet oder überholt: Status auf `superseded` oder `stale` setzen und
   `aba-knowledge-seed-report.md` manuell anpassen.
4. Falls noch unklar: Status `needs_review` belassen oder `unverifiable` setzen.

Erlaubte Status-Werte:
| Status | Bedeutung |
|--------|-----------|
| `verified` | Claim aktuell bestätigt |
| `needs_review` | Überprüfung ausstehend |
| `stale` | Wahrscheinlich veraltet, noch nicht bestätigt |
| `superseded` | Durch neue Regelung ersetzt |
| `unverifiable` | Quelle nicht mehr zugänglich oder widersprüchlich |
| `draft_update` | Neue Version im Entwurf |

---

### Phase 4: Knowledge Base neu aufbauen (`aba:rebuild-from-seed`)

Nachdem alle Claims geprüft und `aba-knowledge-seed-report.md` aktuell ist:

```bash
php artisan aba:rebuild-from-seed
```

Dieser Command:
- Ruft `ExtractKnowledgeClaims::fromKnowledgeSeedReport()` auf
- Schreibt alle normalisierten JSONL-Dateien neu
- Schreibt `retrieval/chunks.jsonl` neu
- Aktualisiert `last_rebuilt_at` in `seed-review-state.json`
- Protokolliert den Rebuild in `seed-report-changelog.md`

---

## Refresh-Frequenzen nach Quelle

| Quelle | Typ | Frequenz | Begründung |
|--------|-----|----------|------------|
| SCHULRECHT-2024 | legal | alle 180 Tage | Gesetze ändern sich selten, aber dann mit großer Wirkung |
| BMBWF-2025 | ministry_guidance | alle 90 Tage | Erlässe können quartalsweise aktualisiert werden |
| AHS-HB-2025 | official_portal | alle 60 Tage | AHS-Handbuch/Richtlinien werden aktiver gepflegt als Erlass |
| PRAXIS-2025 | comparative_best_practice | alle 30 Tage | Pilotphase-Beobachtungen ändern sich schnell |

---

## Change-Risk Klassifikation

| Risiko | Bedeutung | Vorgehen |
|--------|-----------|----------|
| `low` | Kaum Änderungsrisiko, stabile Regelung | Standard-Refresh-Zyklus |
| `medium` | Mäßiges Risiko (standortabhängig oder technologieabhängig) | Priorität bei nächstem Review |
| `high` | Hohes Risiko (explizit unsicher oder ausstehend) | Vor nächstem Schuljahresbeginn verpflichtend prüfen |

---

## Was NICHT automatisiert werden darf

- Direktes Überschreiben von `aba-knowledge-seed-report.md` ohne manuelle Prüfung
- Automatisches Setzen von Claims auf `verified` ohne Quellenprüfung
- Rebuild der Knowledge Base ohne vorherigen Review

---

## Zuständigkeit

Der Refresh-Prozess liegt in der Verantwortung der Person, die die ABA-Knowledge-Pipeline betreut.
Bei Unsicherheiten über Regeländerungen: BMBWF-Website oder Schulrechtsabteilung konsultieren.
