# Tutoring Test-Daten System

Dieses System ermöglicht es, realistische Test-Daten für das Tutoring-System zu erstellen und wieder zu entfernen.

## 📋 Übersicht

Das System erstellt:
- **100 österreichische Schulen** mit echten Namen
- **100.000 Schüler** (1.000 pro Schule) mit realistischen österreichischen Namen
- **1.000 Lehrer** (10 pro Schule)
- **1.000 Tutoring-Fächer** (10 pro Schule)
- **20.000 Tutoring-Angebote** (200 pro Schule)
- **100 Lizenzen** - Nachhilfetool Lizenz pro Schule (gültig bis 2026-07-10)

## 🚀 Verwendung

### Test-Daten hinzufügen

```bash
php artisan tutoring:test-data add
```

Dieser Befehl erstellt alle Test-Daten. Der Vorgang kann einige Minuten dauern.

### Test-Daten entfernen

```bash
php artisan tutoring:test-data remove
```

Dieser Befehl entfernt **alle** erstellten Test-Daten. Eine Bestätigung wird angefordert.

### Alternative Verwendung (Seeders direkt)

```bash
# Test-Daten hinzufügen
php artisan db:seed --class=TutoringTestDataSeeder

# Test-Daten entfernen
php artisan db:seed --class=TutoringTestDataCleanupSeeder
```

## 📊 Daten-Details

### Schulen
- 100 echte österreichische Gymnasien aus allen Bundesländern
- Realistische Kurznamen (z.B. "AKG", "BRG1", "GYM-STP")
- Email-Format: `office@[domain].at`
- **Lizenz**: Jede Schule erhält die "Nachhilfetool" Lizenz (ID: 2) mit Gültigkeit bis **2026-07-10**
- **Super-Admin**: Jede Schule erhält automatisch einen Super-Admin mit Email `kron@naturwelt.at` und Passwort `password`

### Schüler & Lehrer
- **Vornamen**: Häufige österreichische Vornamen (geschlechtsspezifisch)
- **Nachnamen**: Typisch österreichische Familiennamen (Bauer, Gruber, Müller, etc.)
- **Email-Format**: `[vorname].[nachname][nummer]@[schule].at`
  - Beispiel: `hans.maier123@akg-wien.at`
- **Passwort**: Alle Accounts haben das Passwort `password`
- **Rollen**:
  - Schüler: `tutoring_user`
  - Lehrer: `teacher`

### Tutoring-Fächer
10 echte österreichische Schulfächer pro Schule:
- Mathematik (M)
- Deutsch (D)
- Englisch (E)
- Französisch (F)
- Latein (L)
- Physik (PH)
- Chemie (CH)
- Biologie (BIO)
- Geografie und Wirtschaftskunde (GWK)
- Geschichte und Politische Bildung (GSPB)

Jedes Fach hat:
- 2-4 Mentor-Email-Adressen (Lehrer)
- 70% Wahrscheinlichkeit, dass Bestätigung erforderlich ist

### Tutoring-Angebote
200 Angebote pro Schule mit realistischer Verteilung:
- **Status-Verteilung**:
  - 70% bestätigt und online (`is_active=true`, `accepted_at` gesetzt)
  - 15% bestätigt aber offline (`is_active=false`, `accepted_at` gesetzt)
  - 15% nicht bestätigt (`is_active=false`, `accepted_at=null`)

- **Eigenschaften**:
  - Titel: "[Fach] Nachhilfe"
  - Beschreibung: Generiert
  - Klassen: 1-3 zufällige Schulstufen (1.-8. Klasse)
  - Preis: 10-25 Euro pro Stunde
  - 30% sind Gruppenangebote
  - Zeitplan: 2-4 Tage pro Woche, nachmittags (14-19 Uhr)
  - Gültig für: 1-6 Monate ab jetzt
  - Click-Count: 0-50 (simulierte Aufrufe)

## 🗂️ Dateien

### Seeders
- `database/seeders/TutoringTestDataSeeder.php` - Erstellt die Test-Daten
- `database/seeders/TutoringTestDataCleanupSeeder.php` - Entfernt die Test-Daten

### Commands
- `app/Console/Commands/TutoringTestDataCommand.php` - Artisan-Command für einfache Verwendung

## ⚠️ Wichtige Hinweise

1. **Performance**: Das Erstellen von 100.000+ Datensätzen kann mehrere Minuten dauern.

2. **Datenbank**: Die Daten werden in Transaktionen erstellt. Bei Fehlern wird alles zurückgerollt.

3. **Bereinigung**: Der Cleanup-Seeder entfernt **NUR** die Test-Schulen und ihre zugehörigen Daten. Andere Daten bleiben erhalten.

4. **Eindeutigkeit**: Jede Email-Adresse hat eine fortlaufende Nummer, um Eindeutigkeit zu gewährleisten.

5. **Realistische Daten**:
   - Alle Schulnamen sind echt
   - Alle Namen sind typisch österreichisch
   - Email-Domains passen zu den Schulen
   - Fächer entsprechen dem österreichischen Lehrplan

## 🔍 Verwendungsbeispiele

### Für lokale Entwicklung
```bash
# Test-Daten für lokale Entwicklung erstellen
php artisan tutoring:test-data add

# ... Entwickeln und Testen ...

# Test-Daten wieder entfernen
php artisan tutoring:test-data remove
```

### Für Performance-Tests
```bash
# Große Datenmenge für Performance-Tests
php artisan tutoring:test-data add

# Performance-Tests durchführen
# z.B. Suchfunktionen, Filter, Pagination testen

# Cleanup nach Tests
php artisan tutoring:test-data remove
```

### Für Demo-Zwecke
```bash
# Realistische Demo-Daten erstellen
php artisan tutoring:test-data add

# Demo-Präsentation

# Cleanup
php artisan tutoring:test-data remove
```

## 📈 Erwartete Datenmengen

Nach dem Seeding:
```
Schulen:              100
Benutzer:         101.000 (100.000 Schüler + 1.000 Lehrer)
Tutoring-Fächer:    1.000
Tutoring-Angebote: 20.000
```

Geschätzte Datenbankgröße: ~50-100 MB (abhängig vom DBMS)

## 🛠️ Troubleshooting

### Seeding dauert zu lange
- Normales Verhalten bei großen Datenmengen
- Erwartete Dauer: 5-15 Minuten (abhängig vom System)

### Speicher-Fehler
```bash
# PHP Memory Limit erhöhen (temporär)
php -d memory_limit=512M artisan tutoring:test-data add
```

### Datenbank-Fehler
- Prüfen Sie die Datenbankverbindung
- Stellen Sie sicher, dass genügend Speicherplatz verfügbar ist
- Bei Rollback: Alle oder keine Daten werden erstellt (Transaktions-Sicherheit)

## 💡 Tipps

1. **Erste Verwendung**: Starten Sie mit der Add-Funktion und überprüfen Sie die Daten
2. **Regelmäßiges Cleanup**: Entfernen Sie Test-Daten nach der Entwicklung
3. **Backup**: Erstellen Sie ein Datenbank-Backup vor der ersten Verwendung
4. **Anpassungen**: Die Seeder können an spezifische Anforderungen angepasst werden

## 🔄 Aktualisierung der Daten

Um die Test-Daten zu aktualisieren:
```bash
# Alte Daten entfernen
php artisan tutoring:test-data remove

# Neue Daten erstellen
php artisan tutoring:test-data add
```

## 📝 Lizenz

Teil des SchoolTool-Projekts.
