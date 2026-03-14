# ABA Seed-Report Changelog

Dieses Dokument protokolliert alle kontrollierten Änderungen an `aba-knowledge-seed-report.md`,
am Quellenregister (`source-registry.json`) und am Review-Status (`seed-review-state.json`).

Neue Einträge werden **oben** eingefügt (neueste Änderung zuerst).

---


## 2026-03-14 – Seed-Ersatzdraft übernommen

**Durchgeführt von:** Test-Admin

**Änderungen:**
- Produktive Seed-Datei aktualisiert aus Draft: `seed-replacement-draft-2026-03-14.md`
- Backup der bisherigen Fassung: `archive/aba-knowledge-seed-report-2026-03-14-132839.md`
- Draft-Metadaten bereinigt (status → active, draft_type/draft_generated_at/requires_review entfernt)
- last_reviewed_at aktualisiert: 2026-03-14

**Zeitstempel:** 2026-03-14 13:28

---
## 2026-03-14 – Scope-Korrektur: BMHS → AHS

**Durchgeführt von:** system (Audit)

**Änderungen:**
- Seed-Report: YAML-Titel, domain, scope_note auf AHS-ABA korrigiert; ⚠ Audit-Hinweisblock eingefügt
- Seed-Report: Abschnitt 1 auf AHS-Framing korrigiert; Abschnitt 6 BMHS-Schultypen (HASCH/HAK/HTL) entfernt
- Seed-Report: Abschnitt 14 – BMHS-Handbuch-Quelle durch AHS-Platzhalter ersetzt
- `source-registry.json`: Version 1.1; BMHS-HB-2025 → AHS-HB-2025 (needs_identification); scope_note ergänzt
- `aba-system-instructions.md`: BMHS → AHS in Schultyp und ABA-Bezeichnung korrigiert
- `ExtractKnowledgeClaims.php`: Docblock, applies_to-Werte, source_refs (BMHS-HB-2025 → AHS-HB-2025) korrigiert; page_count_orientation_values (HASCH/HTL etc.) durch AHS-Platzhalter ersetzt
- `seed-refresh-policy.md`: Domain und Quellenhinweise auf AHS angepasst

**Hintergrund:** Initiale Wissensbasis wurde irrtümlich auf Basis von BMHS-Quellen erstellt. Korrekte Domäne ist AHS-ABA (ahs-aba.at). Alle BMHS-spezifischen Claims wurden als needs_review markiert bzw. inhaltlich auf AHS-Kontext angepasst. Keine inhaltlichen AHS-Regelungen wurden erfunden.

---

## 2026-03-14 – Initiale Versionierung

**Durchgeführt von:** system (initiale Einrichtung)

**Änderungen:**
- Seed-Report von `deep-research-report.md` in `aba-knowledge-seed-report.md` umbenannt
- YAML-Frontmatter dem Seed-Report hinzugefügt (title, domain, status, version, review-Daten)
- `source-registry.json` erstellt mit 4 Quellen: SCHULRECHT-2024, BMBWF-2025, BMHS-HB-2025, PRAXIS-2025
- `seed-review-state.json` erstellt mit Review-Status für alle 39 Claims
- Refresh-Governance-Infrastruktur eingerichtet (Services, Commands)

**Claims-Stand:**
- Gesamt: 39
- Verifiziert: 32
- Needs review: 7 (format_b_rein_muendlich, page_count_orientation_values, ai_rules_may_change, evaluation_raster_from_2026_27, uncertainty_format_b_availability, uncertainty_evaluation_raster_final, uncertainty_schools_in_implementation)

---

## 2026-03-14 – Knowledge Base neu aufgebaut

- Claims verarbeitet: 40
- Retrieval-Chunks: 8

---

## 2026-03-14 – Knowledge Base neu aufgebaut

- Claims verarbeitet: 40
- Retrieval-Chunks: 8

---

## 2026-03-14 – Knowledge Base neu aufgebaut

- Claims verarbeitet: 40
- Retrieval-Chunks: 8

---

## 2026-03-14 – Knowledge Base neu aufgebaut

- Claims verarbeitet: 40
- Retrieval-Chunks: 8

---
