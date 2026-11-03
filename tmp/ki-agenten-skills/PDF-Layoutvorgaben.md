# Allgemeine Markdown-zu-PDF-Vorlage

Seit der ausdrücklichen Nutzerentscheidung am 11.10.2026 ist das aus der Moor-Referenz entwickelte grüne Layout der einzige Standard für Markdown-PDFs. Der Skill heißt weiterhin `markdown-pdf-quality`. Neuere ausdrückliche Vorgaben für einen konkreten Auftrag haben Vorrang. Fachliche Inhalte und ursprüngliche Erstellungsdaten bleiben ohne Änderungsauftrag unverändert. Bei tatsächlicher Dokumentkorrektur oder PDF-Neuerstellung, auch bei reinem Layout-/PDF-Reexport, vor der Umwandlung ausschließlich das Aktualisierungsmetadatum `update` auf den wirklichen Bearbeitungstag in `Europe/Vienna` setzen (`DD.MM.YYYY`). Dies ist die ausdrücklich erlaubte enge Ausnahme vom Quellschutz; die so vorbereitete Quelle bleibt während des Exports unverändert. Reine Lese-/Prüfschritte und das Rendern einer vorhandenen PDF aktualisieren kein Datum.

Die ausführbare Konfiguration liegt in [.agents/skills/markdown-pdf-quality/references/layout.json](../../.agents/skills/markdown-pdf-quality/references/layout.json). Schriftdateien und Lizenz liegen im selben Skill unter `assets/fonts`; Herkunft und Referenz sind dort dokumentiert. Änderungen an Layoutwerten müssen in Konfiguration und dieser Vorlage konsistent bleiben.

## Seite und Typografie

| Element | Vorgabe |
| --- | --- |
| Format | A4 Hochformat, 595,28 × 841,89 pt |
| Ränder | links/rechts/oben 38 pt; unten 54 pt für die Fußzeile |
| Schrift | mitgelieferte DejaVu Sans; Bold/Oblique/BoldOblique für Hervorhebungen; DejaVu Sans Mono für Code; keine stille Ersatzschrift |
| Fließtext | 11 pt, 15 pt Zeilenschritt, linksbündig, 8 pt Absatzabstand |
| Hauptkapitel | 18/23 pt, fett; 22 pt davor, 9 pt danach, feine grüngraue Abschlusslinie |
| Unterkapitel | 14/18 pt, fett; 18 pt davor, 7 pt danach |
| Dritte Ebene | 12/16 pt, fett; 13 pt davor, 5 pt danach |
| Tabellen | 10/13,5 pt; Kopfzeile fett, helle Grünfläche, weiße Inhaltszeilen, feine grüngraue Trennlinien |
| Code | DejaVu Sans Mono 9,5/12 pt |
| Bildnachweise | 9/10,35 pt mit vollständigen Original-Links |

An Seitenanfängen entfällt zusätzlicher Abstand vor Überschriften. Keine Überschrift oder Überschriftenfolge darf allein am Seitenende stehen. Auf derselben Seite muss sinnvoller Folgeinhalt stehen, etwa Text, ein Listeneintrag oder Tabellenkopf mit Daten. Nicht die gesamte mehrseitige Tabelle als unteilbaren Block mitziehen.

## Farben und Titelband

- Titelband/Überschriften: `#173A40`; Akzent und Links: `#2E6655`.
- Fließtext: `#263835`; Nebentext: `#57655D`; Linien: `#CBD8CF`.
- Ausdrücklich markierte fachliche Fehler-/Fehlstellenhinweise: `#B00020`. Inline-Markierung `[Fehler/Fehlstelle: konkreter Hinweis]{.feedback-error}` wird als roter Text gerendert, auch in Tabellenzellen; neutrale und richtige Aussagen behalten ihre normale Farbe. Der Text benennt den Mangel unabhängig von der Farbe. Keine rohen HTML-Ausgabestücke erforderlich.
- Hinweis-/QR-/Webkarten: `#E9F3EC`; nummerierte Schritte: `#F7F7F1`.
- Erste Seite: vollbreites dunkelgrünes Titelband ab der Blattoberkante. Haupttitel weiß, DejaVu Sans Bold 27/33 pt, in Großbuchstaben. Untertitel 12,2/16 pt in `#F2F7F1`; vorhandene Faktenzeile 10,2/14 pt fett in `#E6F4EB`.
- Mindesthöhe des Titelbands 135 pt; lange Titel, Untertitel oder vorhandene Fakten erhöhen die Höhe. Danach 23 pt Abstand. Titel-/Untertiteldaten nicht erneut als Körperüberschrift ausgeben.
- Titel, Untertitel, Fakten und Autor stammen aus Quellmetadaten. Das sichtbare Standdatum stammt vorrangig aus `update`, andernfalls aus `date`; ursprüngliche Erstellungsdaten und fachliche Datumsangaben bleiben erhalten. Für tatsächliche Neuerstellungen `update` wie oben vorbereiten. Der vorhandene MD-Änderungsdatum-Fallback in `Europe/Vienna` bei fehlenden Datumsmetadaten ersetzt diese Vorbereitung nicht; nur den Dateizeitstempel zu ändern genügt nicht.

## Optionales separates Titelblatt

Nur bei ausdrücklich gewünschtem Titelblatt `pdf-titlepage: true` in den Quellmetadaten setzen. Ohne dieses Metadatum oder bei `false` bleibt das bisherige Titelband unverändert; `--no-title` unterdrückt beide Varianten. Diese optionale Funktion ist ein Designversuch und führt keine allgemeine Titelblattpflicht ein.

```yaml
---
title: Manifest
subtitle: Titelseitenentwurf
author: Dipl.-Ing. Günther Kron
date: 14.10.2026
pdf-titlepage: true
pdf-titlepage-date: Oktober 2026
---
```

Der Markdown-Körper beginnt automatisch auf der nächsten Seite; kein zusätzlicher Seitenumbruch vor dem ersten Inhaltskapitel nötig. `pdf-titlepage-date` ist nur die Datumsanzeige des Titelblatts; ohne dieses Feld das vorhandene Dokumentdatum verwenden. Autor ausschließlich aus `author` beziehungsweise `lehrperson` übernehmen. Fakten bleiben optional. Kein erfundener Untertitel oder Autor.

Gestaltung gemäß `titlepage` in der zentralen Konfiguration: vollflächige dunkelgrüne obere Fläche, helle Grünfläche bis 310 pt über der Blattunterkante, 4 pt Akzentlinie am Übergang. Kleine helle Linie 110 pt unter der Blattoberkante; Titelblock beginnt 210 pt unter der Blattoberkante. Titel weiß, Großbuchstaben, DejaVu Sans Bold 48/58 pt; Untertitel 16/22 pt in der bestehenden Untertitelfarbe; optionale Fakten in bestehender Faktentypografie. Autor DejaVu Sans Bold 14/20 pt, darunter Datum 11/16 pt, beide dunkelgrün. Metadatenblock beginnt 242 pt über der Blattunterkante, mit 18 pt Abstand zwischen Textteilen. Reguläre Seitenzahlen und Fußzeile bleiben auf allen Seiten erhalten. Texte umbrechen innerhalb der normalen Seitenränder; zu lange Metadaten mit verständlichem Fehler ablehnen statt verkleinern oder abschneiden. Der Schulabschluss bleibt auf der letzten Inhaltsseite.

## Inhalt und Umbrüche

Hervorhebungen, Code, Zitate, Bilder und Hinweise vollständig übernehmen. Schrift nicht verkleinern, um eine Seitenzahl zu erzwingen. Vor und nach hervorgehobenen Blöcken, Tabellen, Bildern und Medienkarten ausreichend Abstand vorsehen; im Schulmaterial jeweils 1 cm (28,35 pt). Benachbarte Abstände zusammenführen statt addieren; an Seitenanfängen keinen künstlichen Leerraum erzeugen. Bei Tabellenfortsetzungen Abstände zum folgenden Text erhalten.

Tabellenzellen umbrechen innerhalb ihrer Breite. Bei drei oder mehr Spalten ohne Quellgewichte Breiten aus vorhandenen Zelltexten ableiten. Mehrseitige Tabellen wiederholen die Kopfzeile. Reicht die verfügbare Breite einer Tabelle ohne vorgegebene Spaltengewichte nicht für gut lesbare Spalten, darf sie in schmalere Spaltengruppen aufgeteilt werden, sofern die erste Spalte eindeutige, nicht leere Datensatzkennungen enthält. Diese Kennung in jeder Gruppe wiederholen, sämtliche ursprünglichen Spalten und Zellen in ihrer Reihenfolge erhalten und die reguläre Tabellenschrift beibehalten. Ohne eindeutige Zeilenzuordnung oder bei ausdrücklich vorgegebenen Spaltengewichten die Tabelle nicht automatisch so aufteilen. Fotos proportional darstellen; Bild und Nachweis möglichst zusammenhalten. Lokale Bilder gegen den Markdown-Quellordner auflösen; fehlende Bilder sind Fehler.

Hinweise sind helle, abgerundete Karten mit 12 pt Innenabstand und etwa 10 pt Rundungsradius. Nummerierte Listen erhalten grüne Nummern und helle Schrittflächen. Kurze Abläufe bis 300 pt zusammenhalten; längere Abläufe dürfen weiterlaufen.

Kurze Beispielüberschriften und alleinstehende Beschriftungen wie `**Beispiel:**` mit dem gesamten nachfolgenden Beispielabsatz zusammenhalten. Reicht der Restplatz nicht, beginnen beide auf der nächsten Seite. Kurze Karten mit Beispielüberschrift ebenfalls als Ganzes verschieben. Echte überlange Beispiele dürfen über Seiten laufen, benötigen aber auf der Anfangsseite die Bezeichnung mit mindestens zwei Textzeilen. Keine alleinstehende Beispielbezeichnung am Seitenende, keine Schriftverkleinerung und keine Quelländerung als Umbruchkorrektur.

Webseiten- und YouTube-Verweise aus vorhandenen `.web-link`-, `.youtube`- oder `.youtube-link`-Blöcken als eigene Karten erhalten. Titel, Beschreibung, Domain und URL aus der Quelle übernehmen; der unterstrichene Titel und das Symbol sind anklickbar. Webseitenkarten verwenden grünen Akzent. YouTube-Karten behalten ihren roten Videoakzent und die vorhandene Dauer. Kein eingebetteter Player; keine Videodateien herunterladen. QR-Karten nur aus vorgegebenen http-/https-URLs lokal erzeugen; keine fremden Referenzziele übernehmen.

Quell-Checkboxen bleiben Kästchen. Für ausdrücklich ausfüllbare Ausgaben `--interactive-checkboxes` verwenden; jedes Feld erhält einen eindeutigen Namen und eine Klickfläche. Anfangszustände aus `[x]` erhalten. Vorhandene spezialisierte Formulare über ihren Originalgenerator erzeugen. Nicht flatten, rasterisieren oder durch Druckexport ersetzen.

Explizite Seitenumbrüche übernehmen, soweit keine fast leeren Zwischenseiten entstehen. Nach einem harten oder natürlichen Seitenwechsel keinen zusätzlichen Abstand einschieben. Harte Markdown-Zeilenumbrüche erhalten. Quarto-/Typst-Gestaltungsmetadaten überschreiben dieses Layout nicht; unterstützte Bilder und Umbrüche aus Typst-Blöcken erhalten, unbekannte komplexe Elemente prüfen und nicht still weglassen.

## Fußzeile und freigegebener Schulabschluss

Fußzeile in DejaVu Sans 7,7 pt, grüngrau. Feine Linie bei 50 pt über Blattunterkante; Grundlinie bei 34 pt. Links Quellbezeichnung und `Stand: DD.MM.YYYY`, rechts `X / Y`. Ein zu langes linkes Label auf die Datumsangabe verkürzen. Jede Seite muss eine korrekte Seitenzahl tragen.

Unterrichtsmaterialien erhalten auf der letzten Seite den bereits vom Nutzer freigegebenen Abschluss am unteren Seitenbereich, oberhalb der Fußzeile, mit feiner Linie und ausreichendem Inhaltsabstand. Schrift 9/11,5 pt, Labels fett, Lizenzlink grün und unterstrichen. Wortlaut:

> **KI-Hinweis:** Unterstützend erstellt mit ChatGPT (OpenAI), fachlich geprüft und redaktionell bearbeitet.
>
> **Lizenz:** Dieses Dokument von Dipl.-Ing. Günther Kron steht unter der Lizenz [Creative Commons Namensnennung – Nicht kommerziell 4.0 International (CC BY-NC 4.0)](https://creativecommons.org/licenses/by-nc/4.0/). Verlinkte Fremdinhalte unterliegen ihren jeweiligen Nutzungsbedingungen.

Diese Aussage über fachliche Prüfung ist eine bestehende Nutzervorgabe; PDF-Erstellung bestätigt sie nicht selbst. Der konfigurierte Abschluss gilt für Unterrichtsmaterialien unter `Materialien*` beziehungsweise entsprechend angewiesenen Quellordnern. Außerhalb dieses Bereichs keine Autoren-, Lizenz- oder Prüfbehauptungen hinzufügen. Quellmetadatum `pdf-schulabschluss: true` fordert ihn an; `false` unterdrückt ihn ausdrücklich. Vorhandene Bildlizenzen und Hinweise bleiben immer Inhalt. Abschluss mit sinnvollem Inhalt auf der letzten Seite halten, keine reine Hinweis-Seite erzeugen. Einen kurzen letzten Abschnitt aus Überschrift, Text und gegebenenfalls Tabelle/Liste bis insgesamt 300 pt einschließlich Abständen mit dem Abschluss zusammenhalten, damit keine fast leere Seite mit nur dem letzten Absatz entsteht. Längere Abschnitte bleiben teilbar.

## Aufruf und Quellenintegrität

```powershell
& '<Gebündelte Python-Laufzeit>' 'C:/Dropbox/AI/Vorlagen/PDF/markdown_zu_pdf.py' 'C:/Pfad/Quelle.md'
& '<Gebündelte Python-Laufzeit>' 'C:/Dropbox/AI/Vorlagen/PDF/markdown_zu_pdf.py' 'C:/Pfad/Quelle.md' --overwrite
```

Ohne Zielargument entsteht `<Quellname>.pdf` neben der Markdown-Datei, ohne Layoutsuffix. Ein ausdrückliches Ziel hat Vorrang. Bei autorisiertem Austausch `--overwrite` verwenden; vorherige PDF wird unter Projekt-`tmp/pdfs/backups` gesichert. Sämtliche temporären PDFs, Renderbilder, Arbeitsdateien und Vorschauen ausschließlich unter Projekt-`tmp`, kein `output`-Ordner. SHA-256 nach der erlaubten Metadatenvorbereitung und vor/nach Export vergleichen; bei Änderung während des Exports Ergebnis nicht übernehmen. Zugehörige deterministische Arbeitsdaten vor dem Erzeugen/Abgleichen ebenfalls konsistent auf das tatsächliche Aktualisierungsdatum vorbereiten. Markdown und PDF erst nach bestandenem Gate als konsistentes Paar speichern beziehungsweise ersetzen.

Der zentrale Konverter und der Skill verwenden dieselbe Implementierung und Konfiguration. Unabhängige Protokoll- und Formularfähigkeiten bleiben bestehen.

## Verbindliches technisches und visuelles Freigabegate

Vor Übergabe oder Upload jede finale Seite rendern und einzeln als Bild prüfen: Schrift, Kontrast, Ränder, Umbrüche, Tabellen, Listen, Bilder, Karten, Antwortkästchen, Sonderzeichen, Fußzeilen und Schlussvermerk. Keine Überlagerung, Abschneidung, beschädigten Zeichen oder unnötig leeren Seiten. Kontaktbogen allein genügt nicht.

Mit `scripts/check_pdf.py` Seitenformat, Textgrenzen, Seitenzahlen und Struktur prüfen. Zusätzlich Seitenkoordinaten/Textstruktur auf Überschriften ohne Folgeinhalt, ungünstig getrennte Überschriftenfolgen, fast leere Zwischenblätter und auffällige Inhaltsdichte untersuchen. Jede Auffälligkeit samt Nachbarseite in voller Größe sichten, Ursache klären, korrigieren und erneut rendern. Bildgetragene, bewusst kurze oder abschließende Seiten einzeln beurteilen und Ausnahmen dokumentieren.

Nach bestandenem Gate Quelleninhalt, Bilder, Linkziele, Metadaten, sichtbares Aktualisierungsdatum des tatsächlichen Bearbeitungstags, erhaltenes Erstellungsdatum, Seitenzahlen und Lizenzlink kontrollieren. Bei interaktiven PDFs Feldbaum und Seiten-Widgets vollständig prüfen; An-/Abwählen, Mehrfachauswahl und gespeicherte Zustände an einer temporären Kopie testen und wieder öffnen. Nicht ausschließlich aus einer gerenderten Darstellung auf korrekte Feldwerte schließen.

Bei Serien Quellauswahl und Reihenfolge gegen die vorhandenen PDFs prüfen, Quelldateien ab der erlaubten Metadatenvorbereitung unverändert erhalten, Prüfungen und Ausnahmen dokumentieren. Spezialgeneratoren nicht durch statische Exporte ersetzen; `.quarto`-Caches sind keine Lieferdateien. Bei ausdrücklich beauftragter Wiederveröffentlichung Objektzuordnung, vorhandene Objekte und Metadaten sichern sowie Ersatzdateien nach Download per Hash verifizieren.
