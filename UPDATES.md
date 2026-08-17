# UPDATES

## 3.46.0 !!!

### Timetable V3

- Startseite erzeugt
- Seite Modulauswahl
- Erstellung der Stundenpläne
- Falsche Schulstufe oder falsche Stundentafel erkennen
- Bereinigung der Stundenpläne nach User und Sitzung
- Samstag-Filter
- Freie Tage Filter

### Manueller Stundenplan

- Übernahme der Studienauswahl
- Modulauswahl optimiert
- PDF

## 3.45.0 - 3.45.1

### System

- Git-Upload and Pull on Cloudways
- Testing

## 3.44.6

### System

- Fix cross-platform deployment source verification

## 3.44.5

### System

- Update-Run

## 3.44.3 - 3.44.4

### Unterricht

- Finish-Datum für Arbeiten
- Curricula-Zuordnungen scheinen besser auf
- Arbeiten können ein End-Datum haben und werden angezeigt
- pdf-table-creator erstellt
- Tabellen-Übersicht: pdf erstellen
- Arbeiten: Bessere Darstellung
- Stoff: Curricula-Zuordnungen können einfach übernommen werden
- Verständigungen per E-Mail inkl. Bestätigungen

### System

- Laravel 13.23.0

## 3.44.0-3.44.2

- Major Security Update

## 3.43.5

### Materialien

- Kategorien Links, Termine, Screenshot by default vorhanden
- UI-Design adaptiert
- Screenshots werden in der Übersicht auch angezeigt

### Curriculum

- Übernahme einzelner Einheiten aus anderem Curriculum

## 3.43.4

### Materialien

- Anzeige auswählbar nach Kategorien
- Kategorien Safety-check bei Anlegen
- Automatische Tag-Erzeugung

### General

- Ladepunkte werden zuverlässig ausgeblendet

## 3.43.3

### Unterrichtstool

- Eltern können sich einloggen
- Curricula können Attachments haben
- Curricula sind von Materialien unabhängig

## Materialien

- Version 2 gestartet
- Keine 3-schichtige Einteilung
- Nur noch eine Kategorie plus Suchbegriffe
- Tolerante Flex-Suche

### General

- 2-Factor-Authentification renewed completly
- 2-Factor-Authentification now works with official apps

## 3.43.2

### Unterrichtstool

- Curriculum: PDF-Vorschau fixed
- Eingabe neues Thema: Fokus in Eingabefeld, ESC=Abbrechen

## 3.43.1

### Unterrichtstool

- Curriculum: Status In Arbeit/Ok

## 3.43.0

### Unterrichtstool

- Synchronisierung der Schuldaten von online zu lokal
- Neue Einträge für Beurteilungen
- Veraltens- und weitere Einträge: Verständigungsoptionen vorgesehen
- Bereiche/Einträge auf neues Schuljahr übernehmen
- Import116-Datei: Strenge Abgrenzung nach Schuljahr
- Schülergruppen: Neue Gruppierung/Sortierung
- Import116: Beschleunigen des Imports
- Testumgebung 2026/27 erzeugen
- Schüler:innen-Anwesenheiten auch im Vorhinein erfassbar
- Auswahl neuer Arbeiten ab 2026/27
- Tabelle: Einträge: Mehrere Einträge in Zellen eintragen
- Tabelle: Einträge: Ganze Spalte farblich markieren z. B. w/Prüfung
- Tabelle: Einträge: Stoff kann eingetragen werden
- Menüpunkt Termine: An die anderen Änderungen angepasst
- UI-Verbesserungen

### Unterrichtstool/Curricula

- Auflösung der Datums-/Monatszuordnung

### Curriculm

- UI-Design
- PDF
- Curriculum übernehmen

## 3.42.2

### Schülerstundenpläne

- Geschwindigkeit der Berechnungen verbessert
- Bei Übernahme: Alle Konflikte werden genau aufgeschlüsselt
- Speichern-Button: Richtiger Name

### Materialien

- Material-Details: Angehängte Dateien können jetzt einfach ersetzt werden.

### System

- New composer deploy command

## 3.42.1

### Auswahl-Seite

- Bei Auswahl eines neuen Studierenden, wird der 1. in der Liste automatisch markiert
- Auswahl oder Abwahl von Modulen wird sofort übernommen
- UI-Design verbessert
- Neustart-Button links

### Module-Seite

- UI-Design verbessert

### Stundenplan-Seite

- Mehr Module: Funktionalität und Design verbessert
- Optionen: Immer nur eine Option gleichzeitig anwendbar
- Optionen: Werden wieder angezeigt
- UI-Design verbessert

### System

- Upgrade Laravel 13.19.0

## 3.42.0

### Major Updates

### Konkrete Antworten auf E-Mails

- Rolle Moderatoren für Lehrer. Diese können z. B. keine Importe durchführen
- Studierende erahlten einen gesonderten Zugang
- Anzeige z. B. M4 und M5 bei Khairi gebessert
- Auswahl E2: richtig gestellt
- Abgeschlossene Kurse sind neu buchbar
- Vorauswahl der Module bereinigt
- 2-wöchig abwechselnde Module werden jetzt richtig erkannt
- Sprachwechsel: Module richtig gestellt

## 3.41.8

### Fixes

- Auswahl & Neuberechnung von Mehr Kurse und Optionen
- Modulbezeichnungen vereinheitlicht
- Kurse zu Module umbenannt
- Darstellung zwei Module in der gleichen TT-Zelle, die sich nicht überschneiden: Konflikt entfernt.
- Selektion/De-Selektion bei Mehr Kurse: Unstimmigkeiten gefixt.
- Sprache, Religion Vorauswahl bei Studierenden gefixt
- Neu gewählte Module werden nicht mehr unter "Weitere Module" angezeigt

### Neuer Menüpunkt TT-Einträge

- Hier können alle Module bzw. deren Angebot und Einträge ausgewählt werden.
- Die Auswahl scheint unter _Gemerkte Module_ auf.
- Unter _Gemerkte Module_ werden alle Termine und Überschneidungen angezeigt
- Unter _Gemerkte Module_ können Termine gestrichen werden, um Überschneidungen zu vermeiden

### Stundenplan mit Überschneidung

- Konflikt-Lösungsbuttons auch beim Weiterscrollen zu weiteren TTs
- Konflikte werden mit Wochentag und Stunde, sortiert, angezeigt

### Auswahl Studierender

- Ohne Auswahl wird der Dialog nicht mehr verlassen (ausser bei Abbruch)
- UI-Design des Dialog verbessert
- Liste der Studierenden alphabetisch

### Auswahl/Start

- UI-Design adaptiert
- Module richtig zugeordnet (frühere, zusätzliche Module)

### Druck

- Druck mit differenzierter Auswahl

## 3.41.7

### Unterricht

- Leistungen Plus: 1-semstrige Leistungen sind jetzt editierbar
- Druck: 1-semestrige Leistungen druckbar
- Nicht-aktive Studierende werden nicht mehr gedruckt

## 3.41.6

### Kompaktkurse

- R, S, T, U, V, Q - Klassen-Kurse werden als Kompaktkurse erkannt (nicht mehr als Fernkurse).
- Kompaktkurse werden sowohl bei der Auswahl, also auch im Stundenplan und im Pdf gekennzeichnet.
- Die Darstellung zweier Kompaktkurse in der selben Stundenplan-Zelle wurde verbessert.
- Das Datums-Range wird beim zugehörigen Wochentag angezeigt.
- Ein Kompaktkurs ist nicht gleichzeitig ein Fernunterricht.

### PDF

- Darstellung verbessert
- Zusätzlich zum Gesamtstundenplan: Wochenstundenpläne

### Übernommener Stundenplan

- UI-Design: Farbanpassungen von Buttons

### Bugs

- Auswahl eines speziellen Englisch-Kurses: Es wurde ein anderer E-Kurs in den TT eingebaut: behoben.

### Vorgeschlagene Kurse

- Diese wurden bisher auch aufgrund des Semesters des Studierenden ausgewählt. Das Studierenden-Semester spielt am sofort keine Rolle mehr.
- Alphabethische Sortierung

### Ausgewählte Kurse

- Änderung der Selektion auf Checkboxen für Fernunterricht und Kompaktunterricht
- Chip-Färbung verändert

## 3.41.5

Major Updates
