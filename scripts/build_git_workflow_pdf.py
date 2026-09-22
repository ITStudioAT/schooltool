"""Build the German workflow handbook with Python + ReportLab on Windows.

Run from any directory: python scripts/build_git_workflow_pdf.py
Uses installed Windows Arial/Consolas fonts; no project dependency changes.
Layout adapted from the existing Schooltool handbook of 2026-09-20.
"""

from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.platypus import PageBreak, Paragraph, SimpleDocTemplate, Table, TableStyle


ROOT = Path(__file__).resolve().parents[1]
PDF = ROOT / "public/documentation/Schooltool_Git_Workflow.pdf"
DATE = "22.09.2026"
for name, filename in [("Arial", "arial.ttf"), ("Arial-Bold", "arialbd.ttf"), ("Mono", "consola.ttf")]:
    pdfmetrics.registerFont(TTFont(name, str(Path("C:/Windows/Fonts") / filename)))
pdfmetrics.registerFontFamily("Arial", normal="Arial", bold="Arial-Bold", italic="Arial", boldItalic="Arial-Bold")

NAVY = colors.HexColor("#163347")
TEAL = colors.HexColor("#007F82")
GRAY = colors.HexColor("#465563")
PALE = colors.HexColor("#EDF5F6")
STYLES = {
    "title": ParagraphStyle("title", fontName="Arial-Bold", fontSize=23, leading=27, textColor=NAVY, spaceAfter=9),
    "sub": ParagraphStyle("sub", fontName="Arial", fontSize=10.5, leading=15, textColor=GRAY, spaceAfter=12),
    "h": ParagraphStyle("h", fontName="Arial-Bold", fontSize=12.5, leading=16, textColor=TEAL, spaceBefore=12, spaceAfter=7),
    "body": ParagraphStyle("body", fontName="Arial", fontSize=9.4, leading=13.2, textColor=NAVY, spaceAfter=7),
    "cell": ParagraphStyle("cell", fontName="Arial", fontSize=9, leading=12.2, textColor=NAVY),
    "code": ParagraphStyle("code", fontName="Mono", fontSize=8.6, leading=12, textColor=NAVY),
    "head": ParagraphStyle("head", fontName="Arial-Bold", fontSize=9, leading=12, textColor=colors.white),
    "small": ParagraphStyle("small", fontName="Arial", fontSize=8.3, leading=11.5, textColor=GRAY, spaceAfter=6),
}
story = []


def paragraph(text, style="body"):
    return Paragraph(text, STYLES[style])


def add(text, style="body"):
    story.append(paragraph(text, style))


def page(title, subtitle):
    if story:
        story.append(PageBreak())
    add(title, "title")
    add(subtitle, "sub")


def table(rows, headers=("Befehl / Schritt", "Auswirkung und Voraussetzung"), widths=(177, 334)):
    data = [[paragraph(value, "head") for value in headers]]
    data.extend([
        [paragraph(escape(left).replace("\n", "<br/>"), "code"), paragraph(right, "cell")]
        for left, right in rows
    ])
    result = Table(data, colWidths=widths, repeatRows=1, hAlign="LEFT")
    result.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, PALE]),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 9),
        ("RIGHTPADDING", (0, 0), (-1, -1), 9),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ("LINEBELOW", (0, 1), (-1, -1), 0.3, colors.HexColor("#D8E2E6")),
    ]))
    story.append(result)


def box(text):
    result = Table([[paragraph(text)]], colWidths=[511])
    result.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), PALE),
        ("BOX", (0, 0), (-1, -1), 0.5, TEAL),
        ("LEFTPADDING", (0, 0), (-1, -1), 11),
        ("RIGHTPADDING", (0, 0), (-1, -1), 11),
        ("TOPPADDING", (0, 0), (-1, -1), 9),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]))
    story.append(result)


page("Schooltool Git-Workflow", "Vollständige Bedienungsanleitung | Stand 22. September 2026")
box("<b>Mehrere Features, eine Vorschau:</b> Unabhängige Arbeiten bleiben auf eigenen <b>feature/*</b>-Branches offen. Sie wählen ausdrücklich, welches Feature die gemeinsame Online-Vorschau zeigt. Speichern auf GitHub, Vorschau, Freigabe nach main und Live-Deployment sind getrennte Schritte.")
add("1. Die Arbeitsbereiche", "h")
table([
    ("main", "Gemeinsamer freigegebener Code. Erst <b>gitdeploy</b> aktualisiert daraus die Live-Anwendung; ein Push allein genügt nicht."),
    ("feature/matura\nfeature/stundenplan\nfeature/elternportal", "Beispiele für drei unabhängige Arbeiten. Jeder Branch hat seine eigene unveränderliche Feature-Identität. Ein neues Feature beginnt vom aktuellen GitHub-main, nicht vom anderen Feature."),
    ("Lokaler Checkout", "Ein Projektordner zeigt jeweils einen Branch. <b>gitwork NAME</b> wählt die Arbeit; <b>gitsave</b> sichert genau diesen Branch. Vor dem Wechsel Änderungen speichern."),
    ("Gemeinsame Online-Vorschau", "Genau eine Installation mit eigener Datenbank, eigenen Dateien und Schlüsseln. <b>gitpreview -Feature NAME</b> wählt den zu veröffentlichenden Branch; er muss zum lokalen Checkout passen."),
    ("Live-Anwendung", "Produktiver Datenbestand. Vorschau-Testdaten werden niemals nach Live übernommen. Live-Aktualisierung nur aus dem geprüften main-Release nach <b>LIVE</b>."),
])
add("2. Welche Anleitung brauche ich gerade?", "h")
table([
    ("Seite 2", "Windows-PC einrichten, Helfer aktualisieren, Voraussetzungen prüfen."),
    ("Seite 3", "Mehrere Features anlegen, auswählen, speichern und Geräte wechseln."),
    ("Seite 4", "Genau ein Feature online testen; Datenwechsel A zu B und zurück zu A."),
    ("Seite 5", "Vorschau vorbereiten und fortsetzen; Zugriff und serverseitige Einrichtung."),
    ("Seite 6", "Ein Feature freigeben, main-Prüfungen abwarten und live bereitstellen."),
    ("Seite 7", "Abbrüche verstehen und sicher fortsetzen."),
    ("Seite 8", "Täglicher Spickzettel, Versionierung und Befehlsübersicht."),
], headers=("Nachschlagen", "Inhalt"))
add("Alle PC-Befehle in PowerShell im eigenen Schooltool-Projektordner ausführen. Namen in Beispielen durch die gewünschte Arbeit ersetzen. Befehle in Tabellen können aus Platzgründen umbrechen; jede dargestellte Befehlszeile einzeln eingeben.", "small")
add("Dieses Handbuch beschreibt die Bedienung der implementierten Funktionen. Es ersetzt weder den konkreten Installationsnachweis eines Servers noch die fachliche Abnahme eines Features. Zielwerte und geheime Zugangsdaten gehören in die geprüfte lokale Konfiguration, nicht in diese Anleitung.", "small")

page("Windows-PC einrichten", "Einmal je Gerät; nach Workflow-Updates die aktuellen Helfer verwenden")
add("1. Voraussetzungen", "h")
add("Benötigt werden Git und GitHub-Zugriff, PHP passend zu composer.lock mit PDO-MySQL und Sodium, Composer, Node/npm passend zum Projekt, PowerShell sowie Windows-OpenSSH. Für Live-Deployments muss die GitHub CLI <b>gh</b> angemeldet sein und die Actions des Projekt-Repositories lesen können.")
add("Die lokalen Vollprüfungen benötigen derzeit MySQL unter <b>127.0.0.1:3306</b> mit dem lokalen Entwicklungskonto <b>root</b> ohne Kennwort. Der Testhelfer erstellt eigene zufällige Testdatenbanken und entfernt nur nachweislich selbst angelegte Datenbanken. Bei anderer lokaler Einrichtung muss der Helfer gezielt angepasst werden; keine Serverkonten dafür ändern.")
add("2. Bestehenden sauberen main-Checkout aktualisieren", "h")
table([
    ("git status --short\ngit branch --show-current", "Zuerst prüfen: keine ungesicherten Änderungen und tatsächlich <b>main</b>. Vorhandene lokale Arbeit zuerst sichern, nicht zurücksetzen."),
    ("git pull --ff-only origin main", "Holt den veröffentlichten Workflow. Bei Divergenz stoppen und die unterschiedlichen Commits prüfen."),
    ("composer setup:powershell", "Aktualisiert die eigenen Profile für Windows PowerShell und PowerShell 7. Danach ein <b>neues Terminal</b> im Projektordner öffnen."),
    ("gitmain\ngitcheck", "Abhängigkeiten und Frontend vorbereiten und den Zustand kontrollieren. gitcheck zeigt auch offene Features, aber keinen Online-Serverstatus."),
])
add("Ein neues Gerät erhält einen eigenen Clone und eine eigene lokale <b>.env</b>. Lokale Datenbanken, Uploads, private Schlüssel und der .git-Ordner eines anderen PCs werden nicht kopiert. Entwicklungsserver und Worker nach Branchwechsel neu starten.")
add("3. Sichere Online-Verbindung", "h")
add("Für Hauptanwendung und Vorschau getrennte SSH-Schlüssel je PC verwenden. Die öffentlichen Schlüssel dem passenden Anwendungszugang zuordnen; private Schlüssel im lokalen Windows-SSH-Agent entsperren. Den Serverfingerabdruck unabhängig prüfen und in der vorgesehenen known_hosts-Datei hinterlegen. Die Helfer verlangen bekannte Hostschlüssel und Schlüsselauthentifizierung; sie reichen den Agent nicht an den Server weiter.")
add("Geprüfte Ziel-, Pfad- und Schlüsselvariablen je Gerät einrichten. Die vollständigen Feldnamen und Befehle stehen im Projekt-README unter <b>SSH-Schlüssel und vertrauenswürdige Server je PC</b>. Keine Hostnamen oder Konten aus einem Beispiel übernehmen. Ein funktionierender SSH-Zugang beweist noch keine sichere Vorschau-Isolation.")
add("4. Bestehende Features übernehmen", "h")
add("Alle verwendeten Feature-Branches brauchen den neuen Workflow-Code. Nach Aktualisierung von main: <font name='Mono'>gitwork NAME</font>, <font name='Mono'>gitupdate</font>, testen und <font name='Mono'>gitsave \"Workflow übernommen\"</font>. Falls das Terminal alte Definitionen hält, im Projekt <font name='Mono'>. ./scripts/git_helpers.ps1</font> laden.")
add("Die bisherige Matura-Reservierung wird unverändert ihrem Branch zugeordnet. Ihre Identität und Vorschau-Datenbindung bleiben erhalten; eine manuelle Remote-Migration ist nicht nötig. Ältere Branches ohne Reservierung werden mit gitwork NAME gezielt registriert. Widersprüchliche Reservierungen stoppen den Ablauf.", "small")

page("Mehrere Features bearbeiten", "Anlegen, auswählen, sichern und auf einem anderen Gerät fortsetzen")
add("1. Zwei oder drei unabhängige Features beginnen", "h")
table([
    ("gitmain\ngitstart \"stundenplan\"", "Erstellt und reserviert <b>feature/stundenplan</b> vom aktuellen GitHub-main, pusht den Branch und wechselt dorthin. Ein bereits offenes Matura-Feature bleibt erhalten."),
    ("gitsave \"Erster Entwurf\"", "Nach der Arbeit alle gewünschten Änderungen des aktuellen Branches committen und pushen. Zwischenstände sind erlaubt. Vorher gitcheck prüfen: gitsave nimmt alle Änderungen auf."),
    ("gitmain\ngitstart \"elternportal\"", "Beginnt ein weiteres unabhängiges Feature aus main. Stundenplan und Matura bleiben offen. Weitere Features auf dieselbe Weise anlegen."),
    ("gitsave \"Portal begonnen\"\ngitcheck", "Aktuellen Branch sichern und offene Features anzeigen. Ein erfolgreicher Push macht die Arbeit auf anderen Geräten verfügbar."),
])
add("Namen bestehen aus Kleinbuchstaben, Ziffern und einzelnen Bindestrichen, z. B. <b>neue-funktion</b>. Ein bereits vorhandener Name wird mit gitwork fortgesetzt. gitstart erzeugt keinen zweiten Branch gleichen Namens.", "small")
add("2. Gezielt zwischen Arbeiten wechseln", "h")
table([
    ("gitwork \"matura\"", "Wählt feature/matura und bereitet Abhängigkeiten und Frontend vor. Andere Features bleiben offen. Ohne Namen ist gitwork nur bei genau einem offenen Feature eindeutig."),
    ("gitsave \"Matura-Zwischenstand\"\ngitwork \"stundenplan\"", "Erst erfolgreich sichern, dann wechseln. Auf PC und Laptop identisch; am anderen Gerät denselben Namen angeben."),
    ("gitsave \"Zwischenstand\"\ngitmain", "Zur gemeinsamen Basis wechseln, etwa um einen unabhängigen Fehler auf main zu beheben. Keine automatische Freigabe des Features."),
    ("gitwork \"stundenplan\"\ngitupdate", "Aktuelles GitHub-main in den ausgewählten Feature-Branch übernehmen. Danach testen und mit gitsave sichern; kein automatisches Deployment."),
])
add("3. Was Speichern bewirkt", "h")
add("<b>Auf einem Feature:</b> gitsave speichert ausschließlich dieses Feature auf GitHub. Es ändert keine Versionsnummer, schließt kein anderes Feature und aktualisiert weder Vorschau noch Live. Zum Online-Test danach ausdrücklich gitpreview aufrufen.")
add("<b>Auf main:</b> gitsave baut den gebundenen Frontend-Release und pusht ihn. GitHub führt die erforderlichen Prüfungen aus. Erst anschließend kann gitdeploy diesen Stand live installieren. Einzelheiten und Versionierung stehen auf Seite 6.")
box("<b>Git schaltet keine Datenbank um.</b> gitmain, gitwork und gitstart wechseln Code und bereiten Abhängigkeiten/Frontend vor, führen aber keine Migrationen oder Seeder aus. Bereits ausgeführte Migrationen bleiben bestehen. Für experimentelle Schemaänderungen eine eigene lokale Feature-Datenbank verwenden.")

page("Ein Feature für die Vorschau", "Die gemeinsame Vorschau zeigt immer genau die ausgewählte Arbeit")
add("1. Auswählen, speichern und veröffentlichen", "h")
table([
    ("gitwork \"stundenplan\"\ngitcheck", "Zum gewünschten Feature wechseln. Ungesicherte Änderungen oder nur lokale Commits zuerst sichern."),
    ("gitsave \"Vorschau vorbereitet\"", "Den gewünschten Stand vollständig auf GitHub speichern. Bei bereits sauberem, aktuellem Stand ist kein zusätzlicher Commit nötig."),
    ("gitpreview\n  -Feature \"stundenplan\"", "Als <b>ein Befehl</b> eingeben. Erstellt einen separaten Kandidaten mit aktuellem main und führt lokale Vollprüfungen aus. Bei mehreren Features ist -Feature ausdrücklich erforderlich; der Name muss zum Checkout passen."),
    ("REFRESH\nfalls Daten ersetzt werden", "Der Dialog zeigt den bevorstehenden Datenersatz. Beim Wechsel des bereits belegten Vorschau-Features ersetzt eine frische Live-Kopie die bisherigen aktiven Testdaten und Dateien. Nur bei gewünschtem Ersatz REFRESH eingeben."),
    ("PREVIEW", "Den geprüften Vorschau-Stand ausdrücklich veröffentlichen. Der integrierte Feature-Stand wird auf GitHub und lokal übernommen und die Vorschau über SSH installiert. Andere Features bleiben unverändert."),
])
add("Vor gitpreview ist gitupdate nicht nötig: Der Kandidat integriert main selbst. Die Vorschau ist kein automatischer Spiegel des zuletzt gespeicherten oder zuletzt geöffneten Branches.", "small")
add("2. Was geschieht mit den Testdaten?", "h")
table([
    ("Erste Vorschau", "Die erste Veröffentlichung erhält eine frische Live-Kopie. Es existiert noch kein Vorschau-Testbestand, der ersetzt werden müsste. PREVIEW bleibt erforderlich."),
    ("A erneut veröffentlichen", "Bleibt dieselbe Feature-Identität aktiv, behalten normale Codeupdates die vorhandenen Testdaten. Vor Migrationen wird ein privater Wiederherstellungspunkt angelegt."),
    ("A zu B wechseln", "Neue Live-Kopie nach <b>REFRESH und PREVIEW</b>. B übernimmt nicht die Teständerungen aus A."),
    ("B zurück zu A wechseln", "Wieder eine <b>frische Live-Kopie</b> nach REFRESH und PREVIEW. Der frühere Teststand von A wird nicht wiederhergestellt."),
    ("gitpreview -Feature NAME\n  -RefreshData", "Als ein Befehl: Auch bei unverändertem Feature den aktiven Datenbestand ersetzen. Erfordert REFRESH und PREVIEW; vorher private Sicherung."),
])
add("Es gibt keinen dauerhaften Testdatenstand je Feature. Ein später neu angelegtes Feature gleichen Namens hat eine neue Identität. Private Sicherungen dienen der gezielten Fehlerwiederherstellung, nicht dem normalen Wechsel zwischen Features.", "small")
box("<b>Vor der Bestätigung prüfen:</b> Ist das richtige Feature gewählt, und dürfen die bisherigen Teständerungen ersetzt werden? Der Status wird nach der Bestätigung und unter der Serversperre erneut geprüft. Ändert sich die Vorschau zwischenzeitlich, stoppt der Ablauf statt ungefragt einen anderen Datenstand zu ersetzen.")

page("Vorschau sicher fortsetzen", "Lokale Vorbereitung, Online-Zugang und einmalige Kompatibilität")
add("1. Erst vorbereiten, später veröffentlichen", "h")
table([
    ("gitpreview prepare\n  -Feature \"stundenplan\"", "Ein Befehl: Vollprüfungen und lokalen Kandidaten erstellen. Keine Änderung von GitHub-Branches oder Servern. Nicht mit -RefreshData kombinieren."),
    ("gitpreview resume BUNDLE_ID\n  -Feature \"stundenplan\"", "Ein Befehl: Den vom Helfer angezeigten Fortsetzungsbefehl mit der tatsächlichen Bundle-ID verwenden. Keine erneuten Tests bei unverändertem, gültigem Prüfnachweis. PREVIEW und nötigenfalls REFRESH bleiben erforderlich."),
    ("Abbruch bei Bestätigung", "Leere oder falsche Eingabe veröffentlicht nichts. Der Helfer zeigt den Fortsetzungsbefehl. Kandidat, Paket und Nachweis aufbewahren."),
])
add("Resume funktioniert nur auf demselben Windows-PC, unter demselben Benutzer und aus dem ursprünglichen Projektordner. Der geschützte Nachweis bindet Feature-Identität, Quellstand, main, Kandidat und Paket-Prüfsumme. Geänderte Branches, ungesicherte Arbeit oder ein ungültiger Nachweis stoppen die Fortsetzung. Bei neuem Quellstand wieder regulär gitpreview für vollständige Prüfungen verwenden.")
add("Hat ein Veröffentlichungsversuch bereits begonnen, sperrt eine lokale <b>.started</b>-Markierung die automatische Wiederholung. Erst GitHub- und Serverzustand prüfen; diese Markierung nicht einfach löschen. Erhaltene Kandidaten in git worktree list sind Prüfarbeitsverzeichnisse, keine weiteren Nutzerfeatures.")
add("2. Online-Zugriff und fachlich testen", "h")
add("Vorschau-Zugänge werden <b>in der Hauptanwendung</b> verwaltet. Jede Testperson braucht eine aktive, geeignete und ausdrücklich freigeschaltete Identität. Das gilt auch für Administrierende, Studierende sowie Eltern. Die Freigabe erweitert keine normalen Rechte. Das Superadmin-Kennwort erlaubt keine Anmeldung bei anderen Konten in der Vorschau.")
add("Mit eigenen Zugangsdaten oder vorgesehenem Anmeldecode prüfen: Anmeldung, erlaubte und gesperrte Bereiche, Dateien und eine kleine Datenänderung. Kontrollierte Anmelde-Mails gesondert testen. Bei entzogener Freigabe oder fehlender Kontrollverbindung bleibt der Zugriff geschlossen. Eine technische Installation ersetzt keine fachliche Abnahme.")
add("Die Live-Kopie enthält weiterhin personenbezogene Daten und bleibt geschützt. Aktive Live-Sitzungen, Reset-/Zugriffstoken und ausstehende Jobs werden nicht als aktive Vorschau-Zustände übernommen. Andere E-Mails und externe Integrationen sind gesperrt; nur zulässige Anmelde-/Passwort-Nachrichten dürfen versendet werden.", "small")
add("3. Server erstmals auf diesen Ablauf umstellen", "h")
add("Eine neue Vorschau benötigt die vollständige isolierte Einrichtung aus dem README: eigene Datenbank, Storage, Schlüssel, geschützte Snapshotablage und Kontrollverbindung zur Hauptanwendung. Kein composer deploy oder ungeprüftes composer pdeploy in der Vorschau verwenden.")
add("Eine bereits isolierte ältere Vorschau braucht vor dem ersten neuen gitpreview einmalig die kompatible Status-/Planprüfung. Der geprüfte Einstieg ersetzt ausschließlich <b>FeaturePreviewSnapshotService.php</b> und <b>FeaturePreviewSnapshotCommand.php</b> aus dem Workflow-Release: private Übertragung und Sicherung, Vergleich der Ausgangshashes, PHP-Syntaxprüfung und Austausch unter derselben exklusiven Deployment-Sperre. Keine Änderung von Konfiguration, Datenbank, Uploads oder Feature-Identität.")
add("Danach preview:check sowie Snapshotstatus und assert-plan für die bestehende Identität prüfen; needs_snapshot muss false bleiben. Anschließend den Workflow ins bestehende Feature übernehmen und regulär gitpreview -Feature NAME mit Vollprüfungen ausführen. Der normale Kandidat ersetzt den Übergangspatch. Den genauen kontrollierten Administratorablauf beschreibt das README unter der Vorschau-Kompatibilität; dies ist kein Schalter zum Umgehen regulärer Prüfungen.", "small")

page("Ein Feature freigeben und live", "Andere offene Features und ihre Reservierungen bleiben erhalten")
add("1. Genau das fertige Feature freigeben", "h")
table([
    ("gitwork \"stundenplan\"\ngitsave \"Funktion fertig\"", "Das fertige und fachlich geprüfte Feature auswählen und alle Änderungen sichern. Ein anderer offener Branch, etwa Matura, wird nicht mit freigegeben."),
    ("gitrelease \"Neuer Stundenplan\"", "Prüft einen separaten Merge-Kandidaten mit aktuellem main. Bei Konflikten oder Fehlern stoppen und die Ursache im Feature beheben."),
    ("RELEASE", "Nach erfolgreichen lokalen Vollprüfungen: geprüften Stand atomar nach GitHub-main veröffentlichen und <b>nur diesen Feature-Branch und seine Reservierung</b> schließen. Lokal zu main wechseln und vorbereiten."),
    ("gitcheck", "Prüfen, dass main aktiv ist. Andere offene Features bleiben verfügbar. Release installiert noch nichts auf Live und wählt kein anderes Feature für die Vorschau."),
])
add("Ändern andere Geräte main, Feature oder Reservierung während der Prüfung, schützen erneute Zustandsprüfungen und atomare Git-Vergleiche vor Überschreiben. Nicht mit Force-Push umgehen. Das Feature wird nur geschlossen, wenn genau der geprüfte Stand vollständig integriert ist.")
add("2. main speichern und GitHub-Prüfungen abwarten", "h")
add("Bei direkter Arbeit auf main: <font name='Mono'>gitsave \"Beschreibung\"</font> bereitet Abhängigkeiten vor, prüft Format und Encoding, baut das Frontend und pusht den gebundenen Release. Die erforderlichen Tests laufen auf GitHub. Optional führt <font name='Mono'>gitsave \"Beschreibung\" -Full</font> zusätzlich lokale Vollprüfungen aus.")
add("GitHub prüft Anwendungscode mit PHP-Tests/Coverage, Format und statischer Analyse, Frontend-Tests/Build sowie Linux- und nativen Windows-Workflowtests. Nur eindeutig reine Dokumentations-/Versionsänderungen dürfen unter strengen Herkunfts- und Inhaltsprüfungen einen verkürzten Weg nehmen. Eine neue Versionsnummer allein genügt nicht. Vorschau und Feature-Release behalten immer ihre lokalen Vollprüfungen.")
add("3. Den exakt geprüften Release live installieren", "h")
table([
    ("gitdeploy", "Im sauberen Projekt ausführen. Prüft die erfolgreichen Pflichtjobs des exakten aktuellen GitHub-main-Release und zeigt das Live-Ziel. Fehlende, laufende oder gescheiterte Checks stoppen den Ablauf."),
    ("LIVE", "Diesen Stand und dieses Ziel bestätigen. Nach erneuter Prüfung per SSH installieren, <b>einschließlich vorgesehener Live-Datenbankschritte</b>. Danach tatsächliche Erreichbarkeit und Fachfunktion prüfen."),
    ("composer pdeploy", "Interner Serverpfad mit gebundenen Release-Identitäten und CI-Übergabe. Ein bloßer manueller Aufruf ist gesperrt; kein Ersatz bei fehlender GitHub-Freigabe."),
    ("composer deploy", "Nur für das vollständige <b>lokale</b> Anwendungsupdate einschließlich lokaler Datenbankschritte. Vorher gitmain; auf einem Feature gesperrt."),
])

page("Abbrüche und Fehler", "Prüfen und sicher fortsetzen, ohne Daten oder fremde Arbeit zu verlieren")
table([
    ("Ungesicherte Änderungen\noder lokale Commits", "gitcheck prüfen und die gewünschte Arbeit mit gitsave erfolgreich sichern. Bei Konflikten gezielt auflösen. Kein blindes Reset oder Überschreiben."),
    ("Featureauswahl fehlt\noder passt nicht", "gitwork NAME verwenden, dann gitpreview -Feature NAME. Der Vorschau-Name muss zum aktuellen Checkout passen; die Auswahl allein wechselt keinen Branch."),
    ("GitHub-Stand geändert", "Mit gitmain bzw. gitwork NAME aktualisieren. Echte Divergenz prüfen und auflösen. Kandidaten und Nachweise nicht auf einen anderen Stand umbiegen."),
    ("Tests oder Build scheitern", "Fehler im betreffenden Quellstand beheben, gezielt testen, mit gitsave sichern und den Vorgang neu vollständig prüfen. Keine Test- oder CI-Sperren umgehen."),
    ("Preview-Plan geändert", "Aktuellen Serverstatus und gewähltes Feature prüfen. Den Ablauf erneut planen und Datenersatz neu bestätigen; es wird nicht still weiterimportiert."),
    ("Release veröffentlicht,\nlokale Vorbereitung kaputt", "Ursache beheben und gitmain wiederholen. Der lokale Feature-Branch bleibt zur Sicherheit erhalten. Nur nach erfolgreicher Prüfung den vollständig integrierten Branch nötigenfalls mit git branch -d feature/name entfernen; kein -D."),
    ("SSH/Deployment unterbrochen", "GitHub- und tatsächlichen Serverzustand prüfen. Kandidaten, Paket, Nachweise und private Sicherungen erhalten. Nicht ungeprüft erneut veröffentlichen. Bei Vorschaufehlern bleibt der Zugang geschlossen."),
])
add("Gezielte Vorschau-Wiederherstellung", "h")
add("Nur nach Prüfung <b>im Vorschau-Verzeichnis</b>: <font name='Mono'>php artisan preview:snapshot restore --replace --no-interaction</font>. Stellt ausschließlich den zum offenen Wiederherstellungsvorgang passenden privaten Daten-/Dateistand und Vorschau-Zustand wieder her. Code wird nicht zurückgerollt; die Vorschau bleibt geschlossen. Danach Ursache beheben und gitpreview für passende Code-/Migrationsprüfungen erneut ausführen. Marker nicht manuell löschen; bei fehlender Sicherung oder fehlendem Schlüssel keinen Import erzwingen.", "small")
add("Keine automatische Rückabwicklung", "h")
add("Ein Fehler bedeutet nicht automatisch, dass zuvor erfolgreiche Git- oder Datenbankschritte rückgängig gemacht wurden. Ermitteln Sie zuerst den tatsächlich erreichten Zustand. Die erhaltenen Kandidaten und Sicherungen unterstützen eine gezielte Wiederherstellung; sie sind kein Auftrag zum ungeprüften Wiederholen.")
add("Bei laufenden oder fehlgeschlagenen GitHub-Prüfungen nicht auf den manuellen Serverbefehl ausweichen. Ein neues gitsave erzeugt einen neuen Release-Stand und braucht dessen eigene erfolgreiche Prüfungen. Die Freigabe eines älteren Commits gilt nicht für einen neueren.")

page("Der tägliche Spickzettel", "Befehle in der angegebenen Reihenfolge einzeln ausführen")
table([
    ("Weitere Arbeit anfangen", 'gitmain; dann gitstart "name"; entwickeln; gitsave "Beschreibung".'),
    ("Arbeit / Gerät wechseln", 'Erfolgreich gitsave; am Zielgerät gitwork "name" oder gitmain.'),
    ("main ins Feature holen", 'gitwork "name"; gitupdate; testen; gitsave "main übernommen".'),
    ("Ausgewählte Arbeit testen", 'gitwork "name"; sichern; gitpreview -Feature "name"; nötigenfalls REFRESH, dann PREVIEW.'),
    ("Nur ein Feature freigeben", 'gitwork "name"; sichern; gitrelease "Beschreibung"; RELEASE. Andere Features bleiben offen.'),
    ("Live aktualisieren", "Erfolgreiche GitHub-Pflichtprüfungen des exakten main-Release; gitdeploy; LIVE; Anwendung prüfen."),
], headers=("Anlass", "Reihenfolge (Befehle einzeln ausführen)"))
add("Optional eine Versionsnummer vergeben", "h")
add("<font name='Mono'>gitrelease \"Neue Funktion\" \"3.49.0\"</font> bzw. auf main <font name='Mono'>gitsave \"Beschreibung\" \"3.49.0\"</font>. Nummer ohne v; Beispiel durch die gewünschte Version ersetzen. Passende Notizen in UPDATES.md und das lokale Docusaurus-Projekt müssen vorbereitet sein. Der Ablauf aktualisiert Version, Changelog, Dokumentation und Versions-Tag. Feature-gitsave nimmt keine Version an.")
add("Das Docusaurus-Projekt liegt standardmäßig unter <font name='Mono'>C:/docusaurus/schooltool</font>; ein anderer Pfad wird mit SCHOOLTOOL_DOCUMENTATION_ROOT konfiguriert. Eine Versionsnummer ist für normale Zwischenstände und die reine Workflow-Einrichtung nicht erforderlich.")
add("Kompatibilitätsbefehle", "h")
add("gitpush bleibt auf main kompatibel zu gitsave; -Full ergänzt auch dort lokale Vollprüfungen. Im Alltag genügt gitsave. gitget, gitcommit und gitmerge sind für den beschriebenen Ablauf nicht nötig.")
box("<b>Die vier Bestätigungen:</b> REFRESH erlaubt den angezeigten Ersatz der Vorschau-Testdaten; PREVIEW veröffentlicht den geprüften Vorschau-Kandidaten; RELEASE gibt genau das ausgewählte Feature nach main frei; LIVE installiert den exakt geprüften main-Release auf der Hauptanwendung.")
add("Quellen und Dokumentpflege", "h")
add("Weiterführende Einrichtung: README.md im gleichen Projektstand. PDF-Quelle: scripts/build_git_workflow_pdf.py; Erzeugung mit Python und ReportLab sowie Windows-Schriften Arial/Consolas. Die Anleitung ist bewusst ohne geheime Zielkonfiguration und ohne pauschale Server-Abnahmebestätigung.", "small")


class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.states = []

    def showPage(self):
        self.states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        count = len(self.states)
        for state in self.states:
            self.__dict__.update(state)
            self.setStrokeColor(colors.HexColor("#CBD9DF"))
            self.line(42, 36, A4[0] - 42, 36)
            self.setFont("Arial", 8)
            self.setFillColor(GRAY)
            self.drawString(42, 23, f"Schooltool | Git-Workflow | {DATE}")
            self.drawRightString(A4[0] - 42, 23, f"{self._pageNumber} / {count}")
            super().showPage()
        super().save()


document = SimpleDocTemplate(
    str(PDF), pagesize=A4, rightMargin=42, leftMargin=42, topMargin=36,
    bottomMargin=47, title=f"Schooltool Git-Workflow - {DATE}",
    author="Schooltool", pageCompression=1, invariant=1,
)
document.build(story, canvasmaker=NumberedCanvas)
print(PDF)
