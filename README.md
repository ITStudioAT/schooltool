# About SchoolTool

SchoolTool beinhaltet Tools zur Unterstützung der Administration von Schulen.

## Git-Workflow einrichten und veröffentlichen

Dieser Ablauf verwendet `main` und unabhängige `feature/*`-Branches. Cloudways hostet die Anwendung Schooltool. Alle Windows-Befehle werden im Projektverzeichnis ausgeführt.

Die Anleitung beschreibt den implementierten Ablauf und seine Einrichtung. Sie ist kein Nachweis eines erfolgreichen Online-Deployments. Alle Werte in `<…>` sind durch geprüfte eigene Werte zu ersetzen; keine Schlüssel oder Kennwörter in dieses Dokument eintragen.

### Befehle und Auswirkungen

| Befehl                                                   | Ausführen                                                      | Auswirkung                                                                                                                                                                                                                                                                                                                                                                  |
| -------------------------------------------------------- | -------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `composer setup:powershell`                              | Windows, Projektordner                                         | Installiert oder aktualisiert die Helfer in den eigenen PowerShell-Profilen. Danach ein neues Terminal öffnen.                                                                                                                                                                                                                                                              |
| `gitstart "neue-funktion"`                               | Windows                                                        | Reserviert ein weiteres unabhängiges Feature, erstellt `feature/neue-funktion` aus GitHub-`main`, pusht und wechselt dorthin. Andere offene Features bleiben erhalten.                                                                                                                                                                                                      |
| `gitwork "neue-funktion"`                                | Windows                                                        | Wählt das benannte Feature, holt es und bereitet Abhängigkeiten und Frontend vor. Ältere Branches ohne Reservierung werden gezielt registriert. Ohne Namen nur bei genau einem offenen Feature.                                                                                                                                                                             |
| `gitmain`                                                | Windows                                                        | Wechselt sicher zu `main`, holt dessen GitHub-Stand und führt automatisch `composer deploy` für das vollständige lokale Update einschließlich Migrationen aus. Ungesicherte Änderungen verhindern das Update.                                                                                                                                                               |
| `gitsave "Beschreibung"`                                 | Windows, Feature                                               | Committet und pusht ausschließlich das aktuelle Feature. Keine Version, kein Deployment; Zwischenstände sind erlaubt.                                                                                                                                                                                                                                                       |
| `gitsave "Beschreibung"`                                 | Windows, `main`                                                | Bereitet Abhängigkeiten vor, prüft Format und Encoding, baut das Frontend, committet und pusht den gebundenen Release. GitHub führt die erforderlichen Prüfungen im Hintergrund aus. Kein Live-Deployment.                                                                                                                                                                  |
| `gitsave "Beschreibung" "3.49.0"`                        | Windows, `main`                                                | Zusätzlich Versionsnummer, Changelog und Dokumentation aktualisieren sowie `v3.49.0` veröffentlichen. Auf dem Feature ist diese Variante gesperrt.                                                                                                                                                                                                                          |
| `gitsave "Beschreibung" ["Version"] -Full`               | Windows, `main`                                                | Optional zusätzlich die vollständigen lokalen Releaseprüfungen ausführen. GitHub prüft weiterhin im Hintergrund.                                                                                                                                                                                                                                                            |
| `gitupdate`                                              | Windows, gespeichertes Feature                                 | Sichert offene Änderungen lokal, übernimmt den aktuellen Feature- und `main`-Stand von GitHub und stellt die Änderungen nach der lokalen Vorbereitung wieder her. Bei einem Fehler bleibt die Sicherung erhalten.                                                                                                                                                           |
| `gitcheck`                                               | Windows                                                        | Zeigt Branch, Änderungen, lokale/entfernte Commits und fehlende `main`-Commits. Fragt die Online-Anwendungen nicht ab.                                                                                                                                                                                                                                                      |
| `gitrelease "Beschreibung"`                              | Windows, gespeichertes Feature                                 | Prüft einen separaten Merge-Kandidaten des mit `gitwork NAME` ausgewählten Features mit aktuellem `main`. Nach `RELEASE` auf GitHub veröffentlichen und ausschließlich dieses Feature schließen; andere Features und Reservierungen bleiben erhalten. Lokal zu `main` wechseln und Abhängigkeiten/Frontend vorbereiten, ohne Migrationen oder Seeder. Kein Live-Deployment. |
| `gitrelease "Beschreibung" "3.49.0"`                     | Windows, gespeichertes Feature                                 | Zusätzlich Version, Changelog, Dokumentation und Versions-Tag veröffentlichen.                                                                                                                                                                                                                                                                                              |
| `gitdiscard "testfunktion"`                              | Windows, sauberer aktueller `main`                             | Verwirft `feature/testfunktion` ohne Merge. Nach `DISCARD feature/testfunktion` den exakt geprüften Remote-Branch und seine Reservierung atomar schließen und den passenden lokalen Branch entfernen. Wiederherstellungsreferenzen bleiben erhalten.                                                                                                                        |
| `gitdeploy`                                              | Windows, sauberes Projekt                                      | Prüft das exakte Release-Paket und einen kurzen isolierten Laufzeit-Smoke. Quellstand erneut prüfen und über SSH installieren, einschließlich vorgesehener Live-Datenbankschritte. Wartet nicht auf umfangreiche GitHub-Tests.                                                                                                                                  |
| `composer deploy`                                        | Windows, sauberer aktueller `main`                             | Vollständiges lokales Anwendungsupdate einschließlich lokaler Datenbankschritte. Wird von `gitmain` automatisch ausgeführt; auf dem Feature gesperrt.                                                                                                                                                                                                                       |
| `composer pdeploy`                                       | Cloudways, intern durch `gitdeploy`                            | Benötigt die vom bestätigten SSH-Aufruf übergebenen Release-Identitäten und die Kennung `background-ci-v1`. Ein bloßer manueller Aufruf ist gesperrt; im Alltag `gitdeploy` am PC verwenden.                                                                                                                                                                                |

`gitpush "Beschreibung" ["Version"]` bleibt als Kompatibilitätsbefehl für `main` mit denselben Hintergrundprüfungen erhalten; `-Full` ergänzt lokale Vollprüfungen. Im Alltag genügt `gitsave`. Ein `gitget`, `gitcommit` oder `gitmerge` ist für diesen Ablauf nicht erforderlich. `gitcheck` listet die offenen Features.

Neue Reservierungen liegen unter `codex/features/<name>`. Eine bestehende Reservierung unter `codex/active-feature`, etwa für Matura, wird unverändert ausschließlich ihrem Branch zugeordnet; ihre Lifecycle-ID, Datenbindung und alten Prüfnachweise bleiben erhalten. Es ist keine Remote-Umschreibung zur Übernahme nötig. Widersprüchliche doppelte Reservierungen stoppen den Ablauf. Alle verwendeten Feature-Branches und PCs müssen die neuen Helfer erhalten, bevor mehrere Features mit ihnen bearbeitet werden.

**Veröffentlichen ohne auf Volltests zu warten.** Der normale Ablauf auf `main` ist `gitsave` → `gitdeploy` → kurzer Smoke-Check → Live-Deployment. Feature-Releases prüfen lokal Build, Encoding und gebundene Pakete, führen aber keine Volltests aus. `gitsave ... -Full` auf Main bleibt die ausdrücklich gewählte vollständige lokale Validierung.

GitHub startet Prüfungen bei Pushes auf `main` und `feature/**`. Quellcode-Features benötigen noch kein Release-Paket. Die vorhandenen umfangreichen PHP-, Frontend-, Infrastruktur- und Windows-Prüfungen sowie die konservativen CI-Klassifizierungsregeln bleiben erhalten. Fehler bleiben echte fehlgeschlagene Actions-Läufe; laufende oder fehlgeschlagene Volltests blockieren das Veröffentlichen nicht.

Vor Live prüft `gitdeploy` den unveränderlichen Git-Release einschließlich Paket- und Quellbindung. Der zusätzliche Smoke hat ein Gesamtlimit von 60 Sekunden: PHP-Syntax, Frontend-Verweise und Laravel-Bootstrap mit internem HTTP-Aufruf von `/up`. Er läuft aus einem temporären Snapshot des exakten Kandidaten mit frisch erzeugtem Schlüssel, SQLite im Speicher und blockierten externen Verbindungen. Die lokal installierten Composer-Abhängigkeiten müssen zum Release-Lock passen. Der Smoke prüft keine fachlichen Abläufe oder echten Datenbankmigrationen. Nach den SSH- und Zielprüfungen prüft `gitdeploy` den Main-Stand erneut und startet das Deployment ohne weitere Eingabe.

Der bestätigte Aufruf überträgt den Launcher aus genau diesem Release mit Prüfsumme in `storage/framework` und startet ihn unter der bestehenden Deployment-Sperre. Dadurch funktioniert auch der erste Umstieg von einem älteren Server-Launcher mit CI-Pflicht, ohne erfundene CI-Laufdaten. Server-Konfiguration, Paketprüfung nach dem Pull bleiben wirksam. Es erfolgt kein automatisches Deployment allein durch Speichern.

Für Fehler-E-Mails in den [persönlichen GitHub-Benachrichtigungen](https://github.com/settings/notifications) unter Actions E-Mail-Benachrichtigungen und gegebenenfalls „nur fehlgeschlagene Workflows“ aktivieren. Zustellung und Empfänger hängen von den persönlichen GitHub-Einstellungen ab; das Repository aktiviert oder testet keine E-Mail-Zustellung.

Prüfkandidaten liegen in eigenen Arbeitsverzeichnissen ohne benannten Branch (Detached HEAD). Ihre Commits bleiben über lokale Wiederherstellungsreferenzen unter `refs/schooltool/candidates/` erreichbar. Deshalb zeigt `git branch` weiterhin nur die normalen Arbeitsbranches an; `git worktree list` zeigt zusätzlich die erhaltenen Prüfarbeitsverzeichnisse. Kandidaten nicht zum normalen Entwickeln verwenden oder ihre Referenzen manuell verschieben. Ältere Prüfnachweise bleiben unverändert lesbar, wenn der ursprüngliche Kandidatenbranch existiert oder genau sein archivierter Stand unter `refs/schooltool/archived-heads/` erhalten ist.

Bereits separat geprüfte ältere Nachweise (`legacy-reviewed` und die fest begrenzten `reviewed-patch`-Umfänge) behalten ihre Herkunfts-, Hash- und Quelldelta-Prüfungen. Lose Textlogs werden nicht automatisch übernommen. Neue V3-Nachweise bescheinigen ausschließlich `preflight-success` mit `inline-build-and-integrity`. V1-/V2-Nachweise behalten ihre ursprünglichen Nachweisregeln; ein schneller Check wird niemals nachträglich als bestandene Vollprüfung ausgegeben.

Die installierten Profilfunktionen laden den Workflow bei jedem Aufruf aus dem Projekt. Falls das Terminal noch ältere Funktionsdefinitionen hält, im Projektordner `. ./scripts/git_helpers.ps1` ausführen. Das überschreibt keine Profildatei.

### Ein Feature ohne Übernahme verwerfen

```powershell
gitmain
gitdiscard "testfunktion"
# Bestätigung: DISCARD feature/testfunktion
```

Auch `gitdiscard "feature/testfunktion"` ist möglich. Ungesicherte Änderungen, abweichende lokale/remote Feature-Commits, ein Worktree mit diesem Branch, eine fehlende oder widersprüchliche Reservierung stoppen den Ablauf. Der Befehl wechselt keinen Branch und ändert keine Datenbank. Andere Features und lokale Wiederherstellungsreferenzen bleiben erhalten.

Vor dem Löschen werden beide exakten Commit-IDs unter `refs/schooltool/discarded/<Lifecycle-ID>/<Vorgangs-ID>/feature` und `/reservation` gesichert. Nach einer teilweise abgeschlossenen Remote-Aktion niemals blind erneut löschen: Die Ausgabe unterscheidet Remote-Abschluss und lokale Aufräumprobleme. Die lokalen Wiederherstellungsreferenzen und etwaige Operationssperren erst nach Prüfung eines unterbrochenen Vorgangs anfassen.

`gitdiscard` verwendet nach Bestätigung eine exklusive Lifecycle-Sperre auf GitHub (`codex/operations/<Lifecycle-ID>`). Deshalb müssen alle beteiligten PCs vor Nutzung des neuen Befehls die aktualisierten Helfer laden. Eine fremde oder nach Abbruch verbliebene Sperre wird nicht überschrieben. Zum erstmaligen Laden `. ./scripts/git_helpers.ps1` ausführen; dauerhaft die Profil-Wrapper mit `composer setup:powershell` aktualisieren. Weitere Schritte und Grenzen stehen in der [Bedienungsanleitung](public/documentation/git-workflow/index.html); ihre Docusaurus-Quelle liegt unter `C:/docusaurus/schooltool/src/pages/git-workflow.md`.

### Erste Veröffentlichung des neuen Ablaufs

1. Im vorhandenen Projekt `git status --short` und `git branch --show-current` prüfen. Die zu veröffentlichenden Änderungen müssen bekannt sein; `gitsave` nimmt alle Änderungen im aktuellen Branch auf. Bestehende Arbeit nicht durch Branchwechsel oder Reset verwerfen.
2. `composer setup:powershell` ausführen und ein neues PowerShell-Terminal im Projekt öffnen. So werden die neuen Helfer bereits vor der ersten Veröffentlichung geladen.
3. Auf `main` mit `gitsave "Git-Workflow einrichten"` veröffentlichen. Liegt die Arbeit bereits auf einem registrierten Feature, zuerst dort `gitsave`, anschließend `gitrelease` verwenden. Bei einem älteren Feature ohne Reservierung zunächst `gitwork NAME` zur Registrierung nutzen.
4. Bei bereits eingerichtetem Cloudways-Deployment `gitdeploy` mit kurzem Smoke verwenden. Ein noch nicht eingerichteter Server benötigt eine separat geprüfte Erstinstallation; der ungeprüfte manuelle `composer pdeploy` ist kein Ersatz für die Freigabe. Die aktuelle laufende Anwendung wird durch das Aktualisieren der PC-Helfer allein nicht verändert.

Ein Versionswechsel ist für diese Einrichtung nicht nötig. Wenn einer gewünscht ist, vorher passende Notizen unter der exakten Version in `UPDATES.md` ergänzen. Ist das Docusaurus-Projekt lokal verfügbar, wird es automatisch erkannt und gebaut; ohne Docusaurus überspringt dieser PC den Dokumentationsschritt. Ein abweichender Pfad kann über `SCHOOLTOOL_DOCUMENTATION_ROOT` konfiguriert werden. Die Version wird ohne `v` angegeben.

### Einrichtung auf jedem weiteren Windows-PC

Voraussetzungen: Git, PHP passend zu `composer.lock` mit PDO-MySQL und Sodium, Composer, Node/npm passend zum Projekt, PowerShell und OpenSSH. GitHub-Zugriff muss bereits funktionieren. Für die Releaseprüfungen benötigt der derzeitige lokale Testhelfer MySQL unter `127.0.0.1:3306` mit dem lokalen Entwicklungskonto `root` ohne Kennwort. Er legt eigene Datenbanken mit Zufallsnamen an und entfernt nur nachweislich von ihm erstellte Testdatenbanken. Bei einer anders eingerichteten lokalen Datenbank muss diese Helferkonfiguration zuerst angepasst werden; keine Serverkonten dafür ändern.

Bei einem vorhandenen sauberen Checkout auf `main`:

```powershell
git pull --ff-only origin main
composer setup:powershell
```

Danach ein neues Terminal öffnen und `gitmain` oder `gitwork` ausführen. Ein neues Gerät erhält einen eigenen Clone und eine eigene lokale `.env`; diese Datei, lokale Datenbanken und Uploads werden nicht über Git verteilt. Den Ordner eines anderen PCs einschließlich `.git` oder privaten Schlüsseln nicht kopieren. Bereits laufende Entwicklungsserver und Worker nach dem Branchwechsel neu starten.

Bei ungesicherten Änderungen oder lokalen Commits zuerst diese Arbeit sichern. `gitmain` und `gitwork` brechen bei ungesicherten oder auseinanderlaufenden Git-Ständen ab. Ein auf einem anderen Gerät bereits freigegebenes Feature wird nicht erneut geöffnet; mit `gitmain` zum veröffentlichten Stand wechseln.

### SSH-Schlüssel und vertrauenswürdige Server je PC

Jeder PC erhält einen eigenen Schlüssel für die Hauptanwendung. Neue Schlüssel nur an noch nicht belegten Dateipfaden erstellen; vorhandene Schlüssel nicht überschreiben. Beispielnamen:

```powershell
New-Item -ItemType Directory -Force -Path "$env:USERPROFILE\.ssh" | Out-Null
ssh-keygen -t ed25519 -a 64 -f "$env:USERPROFILE\.ssh\schooltool-main" -C "schooltool-main-mein-pc"
```

Eine Passphrase vergeben. Die öffentliche `.pub`-Datei wird dem Cloudways-Anwendungszugang zugeordnet. Der private Schlüssel bleibt auf diesem PC. Falls der Windows-OpenSSH-Agent noch nicht eingerichtet ist, einmal in einer administrativen PowerShell starten und für weitere Starts aktivieren:

```powershell
Set-Service -Name ssh-agent -StartupType Automatic
Start-Service ssh-agent
```

Anschließend in der normalen eigenen PowerShell die Schlüssel entsperren. Die Helfer bevorzugen Windows-OpenSSH; deshalb denselben Agent verwenden:

```powershell
& "$env:WINDIR\System32\OpenSSH\ssh-add.exe" "$env:USERPROFILE\.ssh\schooltool-main"
```

Vor der ersten Verbindung den SSH-Hostschlüssel-Fingerabdruck über einen unabhängig bestätigten Cloudways-Zugang verifizieren und den passenden Schlüssel in einer eigenen `known_hosts`-Datei speichern. Ein bloßes `ssh-keyscan` bestätigt die Identität des Servers nicht. Die Helfer akzeptieren nur bereits bekannte Hostschlüssel, verwenden ausschließlich Schlüsselauthentifizierung und reichen den Agent nicht an den Server weiter. Eine Passwort- oder Passphrase-Abfrage während des automatischen Deployments ist nicht vorgesehen.

Bei einer ersten interaktiven Verbindung den angezeigten Fingerabdruck nur nach diesem Vergleich bestätigen. Beispiel für eine lesende Prüfung:

```powershell
& "$env:WINDIR\System32\OpenSSH\ssh.exe" -F none -o StrictHostKeyChecking=ask -o IdentitiesOnly=yes -o ForwardAgent=no -i "$env:USERPROFILE\.ssh\schooltool-main" '<hauptanwendungs-login>@<server-host>' 'id -un'
```

Das Hauptanwendungsziel ist im Projekt zentral hinterlegt. Auf einem neuen Gerät genügt ein eigener, auf Cloudways freigeschalteter privater Schlüssel unter `$env:USERPROFILE\.ssh\schooltool-main` und die bereits verifizierte Datei `$env:USERPROFILE\.ssh\known_hosts`. Der Helfer startet den Windows-SSH-Agent bei Bedarf selbst. Ein passwortgeschützter Schlüssel muss nach der Anmeldung einmal mit `ssh-add` entsperrt werden.

Die folgenden Windows-Benutzervariablen bleiben als explizite Überschreibungen verfügbar:

| Variable                         | Wert                                                                               |
| -------------------------------- | ---------------------------------------------------------------------------------- |
| `SCHOOLTOOL_MAIN_SSH`            | `<hauptanwendungs-login>@<server-host>`                                            |
| `SCHOOLTOOL_MAIN_UNIX_USER`      | Tatsächliches `id -un` der Hauptanwendung nach dem SSH-Login                       |
| `SCHOOLTOOL_MAIN_PATH`           | Geprüfter absoluter, kanonischer Pfad zur Hauptanwendung, endet auf `/public_html` |
| `SCHOOLTOOL_MAIN_KEY`            | Absoluter lokaler Pfad zum privaten Hauptanwendungsschlüssel, ohne `.pub`          |
| `SCHOOLTOOL_MAIN_KNOWN_HOSTS`    | Absoluter lokaler Pfad zur geprüften Hostschlüsseldatei                            |

Cloudways kann einen SSH-Login-Alias auf ein anders benanntes Unix-Anwendungskonto abbilden. Deshalb `*_UNIX_USER` anhand von `id -un` setzen und nicht aus dem Login-Namen ableiten. Den Zielpfad mit `pwd -P` und den Eigentümer des Anwendungsverzeichnisses ebenfalls prüfen. Bei ausgelassener `*_KNOWN_HOSTS`-Variable wird die vorhandene Datei `$env:USERPROFILE\.ssh\known_hosts` verwendet.

Benutzervariablen können in den Windows-Umgebungsvariablen oder einzeln so gespeichert werden:

```powershell
[Environment]::SetEnvironmentVariable('SCHOOLTOOL_MAIN_SSH', '<hauptanwendungs-login>@<server-host>', 'User')
```

Für jede Tabellenzeile den passenden Namen/Wert verwenden und anschließend ein neues Terminal öffnen. Die Helfer ignorieren benutzerspezifische SSH-Konfigurationsdateien; dort vorhandene Host-Aliase oder Spezialports ersetzen diese Angaben nicht.

### Cloudways

Der Live-Deployment-Befehl verwendet ausschließlich die Anwendung Schooltool und den Branch `main`.

### Störungen und Wiederherstellung

| Situation                                               | Vorgehen                                                                                                                                                                                                                                                                 |
| ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| SSH-Zugang scheitert                                    | Zielvariablen, richtiges Unix-Konto, Agent, Schlüsselzuordnung und verifizierten Hostschlüssel prüfen. Hostschlüsselprüfung nicht abschalten.                                                                                                                            |
| GitHub hat neuere Commits                               | Mit `gitmain` bzw. `gitwork` aktualisieren; bei echter Divergenz bewusst auflösen. Kein blindes Überschreiben oder Zurücksetzen.                                                                                                                                         |
| Veröffentlichung nach Prüfungen gescheitert             | Lokale Commits und Kandidaten bleiben erhalten. Bei Verbindungsabbruch erst `gitcheck` und GitHub prüfen, dann gezielt fortsetzen.                                                                                                                                       |
| Release veröffentlicht, lokale Vorbereitung gescheitert | Das Release bleibt veröffentlicht und der lokale Feature-Branch erhalten. Ursache beheben und `gitmain` wiederholen; danach den integrierten lokalen Branch bei Bedarf mit `git branch -d feature/name` entfernen. Nicht erneut veröffentlichen und kein `-D` verwenden. |
| Live-Deployment fehlgeschlagen                          | Live-Ausgabe und bestehenden Wartungs-/Deploymentstatus prüfen. Nicht ungeprüft `artisan up` aufrufen.                                                                                                            |

### Typische Abläufe

| Anlass                                          | Ablauf                                                                                                  |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Feature beginnen                                | `gitstart "neue-funktion"` → entwickeln → `gitsave "Beschreibung"`                                      |
| Gerät oder Feature wechseln                     | Erfolgreiches `gitsave`; anschließend `gitwork NAME` oder `gitmain`                                     |
| Hauptanwendung korrigieren                      | Feature sichern → `gitmain` → korrigieren → `gitsave "Fehler behoben"` → `gitdeploy` → Smoke und Live-Deployment |
| Korrektur im Feature weiterverwenden            | `gitwork` → `gitupdate` → testen → `gitsave "main übernommen"`                                          |
| Feature freigeben                               | `gitsave "Funktion fertig"` → `gitrelease "Neue Funktion"` → `gitdeploy` → Smoke und Live-Deployment             |
| Lokal den vollständigen Hauptstand installieren | `gitmain` (führt `composer deploy` automatisch aus)                                                     |

Git wechselt keine lokalen Datenbanken. gitmain aktualisiert die konfigurierte lokale Datenbank über composer deploy. gitupdate führt ausstehende additive Migrationen nach Prüfung von Ziel und SQL aus. Unklare oder destruktive Migrationen stoppen den Vorgang. Für experimentelle Feature-Migrationen eine eigene lokale Datenbank verwenden. Bereits ausgeführte Migrationen werden beim Branchwechsel nicht rückgängig gemacht.

# Code simplifying with Claude

Review recent changes using the laravel-simplifier agent

# Reommented actions

```
php artisan ide-helper:generate
php artisan ide-helper:models -W
```

# Last Changes

## 3.11.0

- Teacher/Student-Modul implementated

## 3.10.2

- Tutoring: Manual 1/x

## 3.10.1

- Tutoring: Users: Minimal adaptions

## 3.10.0

- Tutoring: Made a lot of tests and change some things to get better

## 3.9.6

- Tutoring: Logo in E-Mails

## 3.9.4/3.9.5

- Cookie Consent DSGVO

## 3.9.3

- Manual disabled

## 3.9.2

- Register: User: Next step, when pressing enter

## 3.9.0/3.9.1

- Register: User: New UI-Design

## 3.8.4

- Tutoring: User: New UI-Design for MyRequests and ReceivedRequests

## 3.8.3

- Tutoring: User: Info-E-Mail, if request is deleted

## 3.7.13 - 16.01.2025

- Tutoring: Archive received requests

## 3.3.0 - 17.12.2025

- Tutoring alpha merged in Main

## 3.2.13 - 21.11.2025

- Entry-Point changed

## 3.2.12 - 20.11.2025

- Super-Admin: Bei Erstellung, Löschen von Schulen oder Upload von Schul-Logo: Erstellen bzw. korrekte Verwendung von Verzeichnissen

## 3.2.11 - 17.11.2025

- User-Übersicht für Super-Admin mit:
- Selektion nach Rolle
- Erstellen, Ändern, Löschen von Benutzern
- More pest tests added

## 3.2.10 - 16.11.2025

- Many pest tests added

## 3.2.9 - 15.11.2025

- Übersicht über Benutzer im Anmeldetool
- Bereinigen Benutzer ohne Anmeldung

## 3.2.8 - 15.11.2025

- Optionale 2-Faktoren-Authentifizierung

## 3.2.7 - 15.11.2025

- Deleting test-files, when updating app
- Queue test updated

## 3.2.6 - 14.11.2025

- Queue Test

## 3.2.5 - 13.11.2025

- Added some Pest tests for Service-Classes

## 3.2.3 – 3.2.4 - 12.11.2025

### Anmeldetool

- Breitere Spalten bei allen Ansichten
- Nach Buchung löschen: sofortige Akualisierung der Buchungen
