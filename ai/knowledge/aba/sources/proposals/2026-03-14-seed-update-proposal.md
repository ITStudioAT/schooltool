# ABA Seed-Update Vorschlag – 2026-03-14

**Erstellt:** 2026-03-14T07:16:36+01:00
**Status:** draft – wartet auf manuelle Review/Freigabe

## Zusammenfassung

| Kategorie | Anzahl |
|-----------|--------|
| Claims gesamt | 40 |
| Verifiziert | 33 |
| Needs review | 7 |
| Veraltet (stale) | 0 |

## Claims mit Überprüfungsbedarf

### `format_b_rein_muendlich`
- **Status:** needs_review
- **Change Risk:** medium
- **Zuletzt verifiziert:** 2026-03-14
- **Quellen:** BMBWF-2025
- **Notiz:** Standortabhängig und noch nicht abschließend geregelt. Priorität: mittel.

**Aktion:**
- [ ] Quelle öffnen und Claim prüfen
- [ ] Claim bestätigt → `--status=verified`
- [ ] Claim unklar → `--status=unverifiable`
- [ ] Claim in Überarbeitung → `--status=draft_update`

```bash
php artisan aba:apply-seed-update --claim=format_b_rein_muendlich --status=verified --reviewed-by="Name"
```

### `page_count_orientation_values`
- **Status:** needs_review
- **Change Risk:** low
- **Zuletzt verifiziert:** 2026-03-14
- **Quellen:** PRAXIS-2025
- **Notiz:** Informelle Praxiswerte. Können sich mit Schuljahreserfahrung ändern.

**Aktion:**
- [ ] Quelle öffnen und Claim prüfen
- [ ] Claim bestätigt → `--status=verified`
- [ ] Claim unklar → `--status=unverifiable`
- [ ] Claim in Überarbeitung → `--status=draft_update`

```bash
php artisan aba:apply-seed-update --claim=page_count_orientation_values --status=verified --reviewed-by="Name"
```

### `ai_rules_may_change`
- **Status:** needs_review
- **Change Risk:** high
- **Zuletzt verifiziert:** 2026-03-14
- **Quellen:** PRAXIS-2025
- **Notiz:** Explizit als unsicher markiert. Beim nächsten Review aktuelle KI-Policy des BMBWF prüfen.

**Aktion:**
- [ ] Quelle öffnen und Claim prüfen
- [ ] Claim bestätigt → `--status=verified`
- [ ] Claim unklar → `--status=unverifiable`
- [ ] Claim in Überarbeitung → `--status=draft_update`

```bash
php artisan aba:apply-seed-update --claim=ai_rules_may_change --status=verified --reviewed-by="Name"
```

### `evaluation_raster_from_2026_27`
- **Status:** needs_review
- **Change Risk:** high
- **Zuletzt verifiziert:** 2026-03-14
- **Quellen:** BMBWF-2025
- **Notiz:** Noch nicht in finaler Form vorliegend. Vor Schuljahr 2026/27 dringend prüfen.

**Aktion:**
- [ ] Quelle öffnen und Claim prüfen
- [ ] Claim bestätigt → `--status=verified`
- [ ] Claim unklar → `--status=unverifiable`
- [ ] Claim in Überarbeitung → `--status=draft_update`

```bash
php artisan aba:apply-seed-update --claim=evaluation_raster_from_2026_27 --status=verified --reviewed-by="Name"
```

### `uncertainty_format_b_availability`
- **Status:** needs_review
- **Change Risk:** high
- **Zuletzt verifiziert:** 2026-03-14
- **Quellen:** PRAXIS-2025
- **Notiz:** Offene Frage. Sobald offizielle Klärung vorhanden, Status auf verified oder superseded setzen.

**Aktion:**
- [ ] Quelle öffnen und Claim prüfen
- [ ] Claim bestätigt → `--status=verified`
- [ ] Claim unklar → `--status=unverifiable`
- [ ] Claim in Überarbeitung → `--status=draft_update`

```bash
php artisan aba:apply-seed-update --claim=uncertainty_format_b_availability --status=verified --reviewed-by="Name"
```

### `uncertainty_evaluation_raster_final`
- **Status:** needs_review
- **Change Risk:** high
- **Zuletzt verifiziert:** 2026-03-14
- **Quellen:** PRAXIS-2025
- **Notiz:** Vor Schuljahr 2026/27 dringend klären. Status dann auf superseded setzen, sobald finales Raster vorliegt.

**Aktion:**
- [ ] Quelle öffnen und Claim prüfen
- [ ] Claim bestätigt → `--status=verified`
- [ ] Claim unklar → `--status=unverifiable`
- [ ] Claim in Überarbeitung → `--status=draft_update`

```bash
php artisan aba:apply-seed-update --claim=uncertainty_evaluation_raster_final --status=verified --reviewed-by="Name"
```

### `uncertainty_schools_in_implementation`
- **Status:** needs_review
- **Change Risk:** medium
- **Zuletzt verifiziert:** 2026-03-14
- **Quellen:** PRAXIS-2025
- **Notiz:** Gilt nur für 2025/26 (valid_to: 2026-06-30). Nach Schuljahresende Status prüfen.

**Aktion:**
- [ ] Quelle öffnen und Claim prüfen
- [ ] Claim bestätigt → `--status=verified`
- [ ] Claim unklar → `--status=unverifiable`
- [ ] Claim in Überarbeitung → `--status=draft_update`

```bash
php artisan aba:apply-seed-update --claim=uncertainty_schools_in_implementation --status=verified --reviewed-by="Name"
```

---

## Freigabe

- [ ] Review durchgeführt von: _______________
- [ ] Freigabe erteilt am: _______________
- [ ] `php artisan aba:apply-seed-update` für jeden Claim ausgeführt
- [ ] `php artisan aba:rebuild-from-seed` ausgeführt
