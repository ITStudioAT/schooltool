# UPDATES

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
