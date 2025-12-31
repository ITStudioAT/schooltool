# Tutoring Test-Daten - Quick Reference

## 🚀 Schnellstart

### Test-Daten erstellen
```bash
php artisan tutoring:test-data add
```

### Test-Daten löschen
```bash
php artisan tutoring:test-data remove
```

---

## 📊 Was wird erstellt?

| Typ | Anzahl | Details |
|-----|--------|---------|
| **Schulen** | 100 | Echte österreichische Gymnasien |
| **Schüler** | 100.000 | 1.000 pro Schule, Rolle: `tutoring_user` |
| **Lehrer** | 1.000 | 10 pro Schule, Rolle: `teacher` |
| **Fächer** | 1.000 | 10 pro Schule (M, D, E, F, L, PH, CH, BIO, GWK, GSPB) |
| **Angebote** | 20.000 | 200 pro Schule |
| **Lizenzen** | 100 | Nachhilfetool Lizenz pro Schule (gültig bis 2026-07-10) |

---

## 👤 Login-Daten

**Alle Accounts:**
- Passwort: `password`
- Email-Format: `[vorname].[nachname][nummer]@[schule].at`
- Beispiel: `hans.maier0@akg-wien.at`

---

## 📈 Angebots-Verteilung

- **70%** ✅ Bestätigt & Online
- **15%** ✅ Bestätigt & Offline
- **15%** ❌ Nicht bestätigt

---

## 🏫 Beispiel-Schulen

| Kürzel | Name | Domain |
|--------|------|--------|
| AKG | Akademisches Gymnasium Wien | akg-wien.at |
| BRG1 | BRG 1 Stubenbastei Wien | brg1.at |
| BG-GR | Bundesgymnasium Graz | bggraz.at |
| BG-LZ | Bundesgymnasium Linz | bglinz.at |
| BG-SB | Bundesgymnasium Salzburg | bgsalzburg.at |
| BG-IBK | Bundesgymnasium Innsbruck | bginnsbruck.at |
| BG-BZ | Bundesgymnasium Bregenz | bgbregenz.at |
| BG-VL | Bundesgymnasium Villach | bgvillach.at |

---

## ⏱️ Geschätzte Ausführungszeit

- **Erstellen**: 5-15 Minuten
- **Löschen**: 1-2 Minuten

---

## ⚠️ Wichtig

1. Nur in **Entwicklung/Test-Umgebungen** verwenden!
2. Backup vor erster Verwendung erstellen
3. Cleanup entfernt **nur** Test-Daten, keine echten Daten
4. Bei Speicher-Problemen: `php -d memory_limit=512M artisan tutoring:test-data add`

---

## 🔍 Daten überprüfen

```sql
-- Anzahl Test-Schulen
SELECT COUNT(*) FROM schools WHERE short_name IN ('AKG', 'BRG1', 'GRG3', ...);

-- Anzahl Schüler pro Schule
SELECT s.short_name, COUNT(u.id) as student_count
FROM schools s
LEFT JOIN users u ON u.school_id = s.id
INNER JOIN model_has_roles mhr ON mhr.model_id = u.id
INNER JOIN roles r ON r.id = mhr.role_id AND r.name = 'tutoring_user'
WHERE s.short_name IN ('AKG', 'BRG1', 'GRG3')
GROUP BY s.id
ORDER BY s.short_name;

-- Angebote pro Status
SELECT
    CASE
        WHEN is_active = 1 AND accepted_at IS NOT NULL THEN 'Bestätigt & Online'
        WHEN is_active = 0 AND accepted_at IS NOT NULL THEN 'Bestätigt & Offline'
        ELSE 'Nicht bestätigt'
    END as status,
    COUNT(*) as count
FROM tutoring_offers
GROUP BY status;
```

---

## 📞 Support

Bei Problemen siehe: [TUTORING_TEST_DATA.md](TUTORING_TEST_DATA.md#-troubleshooting)
