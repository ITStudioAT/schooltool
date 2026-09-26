# About SchoolTool

SchoolTool beinhaltet Tools zur Unterstützung der Administration von Schulen.

## Git-Workflow einrichten und veröffentlichen

Dieser Ablauf verwendet `main` und mehrere unabhängig offene `feature/*`. Genau eine gemeinsame Online-Vorschau zeigt das ausdrücklich ausgewählte Feature. GitHub, die Live-Anwendung und die Online-Vorschau werden getrennt aktualisiert. Alle Windows-Befehle werden im Projektverzeichnis ausgeführt.

Die Anleitung beschreibt den implementierten Ablauf und seine Einrichtung. Sie ist kein Nachweis eines erfolgreichen Online-Deployments. Ein funktionierender SSH-Zugang allein bedeutet noch nicht, dass Datenbanktrennung und Vorschau eingerichtet sind. Alle Werte in `<…>` sind durch geprüfte eigene Werte zu ersetzen; keine Schlüssel oder Kennwörter in dieses Dokument eintragen.

### Befehle und Auswirkungen

| Befehl | Ausführen | Auswirkung |
| --- | --- | --- |
| `composer setup:powershell` | Windows, Projektordner | Installiert oder aktualisiert die Helfer in den eigenen PowerShell-Profilen. Danach ein neues Terminal öffnen. |
| `gitstart "neue-funktion"` | Windows | Reserviert ein weiteres unabhängiges Feature, erstellt `feature/neue-funktion` aus GitHub-`main`, pusht und wechselt dorthin. Andere offene Features bleiben erhalten. |
| `gitwork "neue-funktion"` | Windows | Wählt das benannte Feature, holt es und bereitet Abhängigkeiten und Frontend vor. Ältere Branches ohne Reservierung werden gezielt registriert. Ohne Namen nur bei genau einem offenen Feature. |
| `gitmain` | Windows | Wechselt sicher zu `main`, holt dessen GitHub-Stand und bereitet Abhängigkeiten und Frontend vor. Keine Migrationen oder Seeder. |
| `gitsave "Beschreibung"` | Windows, Feature | Committet und pusht ausschließlich das aktuelle Feature. Keine Version, kein Deployment; Zwischenstände sind erlaubt. |
| `gitsave "Beschreibung"` | Windows, `main` | Bereitet Abhängigkeiten vor, prüft Format und Encoding, baut das Frontend, committet und pusht den gebundenen Release. GitHub führt die erforderlichen Prüfungen im Hintergrund aus. Kein Live-Deployment. |
| `gitsave "Beschreibung" "3.49.0"` | Windows, `main` | Zusätzlich Versionsnummer, Changelog und Dokumentation aktualisieren sowie `v3.49.0` veröffentlichen. Auf dem Feature ist diese Variante gesperrt. |
| `gitsave "Beschreibung" ["Version"] -Full` | Windows, `main` | Optional zusätzlich die vollständigen lokalen Releaseprüfungen ausführen. GitHub prüft weiterhin im Hintergrund. |
| `gitupdate` | Windows, gespeichertes Feature | Übernimmt den aktuellen GitHub-`main` in das Feature und bereitet es lokal vor. Danach testen und mit `gitsave` sichern. Keine Online-Aktualisierung. |
| `gitcheck` | Windows | Zeigt Branch, Änderungen, lokale/entfernte Commits und fehlende `main`-Commits. Fragt die Online-Anwendungen nicht ab. |
| `gitpreview -Feature "neue-funktion"` | Windows, gespeichertes ausgewähltes Feature | Erstellt einen separaten Kandidaten einschließlich aktuellem `main`, prüft ihn und aktualisiert nach `PREVIEW` die gemeinsame Vorschau über SSH. Der Name muss zum aktuellen Checkout passen. Beim Featurewechsel ersetzt eine frische Live-Kopie die bisherigen Testdaten erst nach zusätzlichem `REFRESH`. |
| `gitpreview -Feature "neue-funktion" -RefreshData` | Windows, gespeichertes Feature | Ersetzt auch bei unverändertem Feature die aktiven Vorschau-Daten und Dateien durch eine frische Live-Kopie. Vorher private Sicherung; verlangt `REFRESH` und `PREVIEW`. |
| `gitpreview prepare -Feature "neue-funktion"` | Windows, gespeichertes Feature | Prüft und erstellt ausschließlich einen lokalen Vorschau-Kandidaten. Verändert weder GitHub-Branches noch Server. |
| `gitpreview resume <Bundle-ID> -Feature "neue-funktion"` | Derselbe Windows-PC und Benutzer, ursprünglicher Projektordner | Setzt einen vor der Veröffentlichung abgebrochenen Kandidaten mit erfolgreicher Build- und Paketprüfung fort. Prüft Nachweis, Paket, Quellstand und aktuelle GitHub-Branches erneut; veröffentlicht erst nach `PREVIEW`. Datenersatz verlangt zusätzlich `REFRESH`. |
| `gitrelease "Beschreibung"` | Windows, gespeichertes Feature | Prüft einen separaten Merge-Kandidaten des mit `gitwork NAME` ausgewählten Features mit aktuellem `main`. Nach `RELEASE` auf GitHub veröffentlichen und ausschließlich dieses Feature schließen; andere Features und Reservierungen bleiben erhalten. Lokal zu `main` wechseln und Abhängigkeiten/Frontend vorbereiten, ohne Migrationen oder Seeder. Kein Live-Deployment. |
| `gitrelease "Beschreibung" "3.49.0"` | Windows, gespeichertes Feature | Zusätzlich Version, Changelog, Dokumentation und Versions-Tag veröffentlichen. |
| `gitdiscard "testfunktion"` | Windows, sauberer aktueller `main` | Verwirft `feature/testfunktion` ohne Merge. Nach `DISCARD feature/testfunktion` den exakt geprüften Remote-Branch und seine Reservierung atomar schließen und den passenden lokalen Branch entfernen. Wiederherstellungsreferenzen bleiben erhalten. |
| `gitdeploy` | Windows, sauberes Projekt | Prüft das exakte Release-Paket und einen kurzen isolierten Laufzeit-Smoke. Nach `LIVE` Quellstand erneut prüfen und über SSH installieren, einschließlich vorgesehener Live-Datenbankschritte. Wartet nicht auf umfangreiche GitHub-Tests. |
| `composer deploy` | Windows, sauberer aktueller `main` | Vollständiges lokales Anwendungsupdate einschließlich lokaler Datenbankschritte. Vorher `gitmain`; auf dem Feature gesperrt. |
| `composer pdeploy` | Cloudways, intern durch `gitdeploy` | Benötigt die vom bestätigten SSH-Aufruf übergebenen Release-Identitäten und die Kennung `background-ci-v1`. Ein bloßer manueller Aufruf ist gesperrt; im Alltag `gitdeploy` am PC verwenden. |

`gitpush "Beschreibung" ["Version"]` bleibt als Kompatibilitätsbefehl für `main` mit denselben Hintergrundprüfungen erhalten; `-Full` ergänzt lokale Vollprüfungen. Im Alltag genügt `gitsave`. Ein `gitget`, `gitcommit` oder `gitmerge` ist für diesen Ablauf nicht erforderlich. `gitcheck` listet die offenen Features. Bei mehreren Features verlangt `gitpreview` ausdrücklich `-Feature NAME`; zuerst mit `gitwork NAME` zum passenden gespeicherten Branch wechseln.

Neue Reservierungen liegen unter `codex/features/<name>`. Eine bestehende Reservierung unter `codex/active-feature`, etwa für Matura, wird unverändert ausschließlich ihrem Branch zugeordnet; ihre Lifecycle-ID, Datenbindung und alten Prüfnachweise bleiben erhalten. Es ist keine Remote-Umschreibung zur Übernahme nötig. Widersprüchliche doppelte Reservierungen stoppen den Ablauf. Alle verwendeten Feature-Branches und PCs müssen die neuen Helfer erhalten, bevor mehrere Features mit ihnen bearbeitet werden.

**Veröffentlichen ohne auf Volltests zu warten.** Der normale Ablauf auf `main` ist `gitsave` → `gitdeploy` → kurzer Smoke-Check → `LIVE`. Preview und Feature-Release prüfen lokal Build, Encoding und gebundene Pakete, führen aber keine Volltests aus. `gitsave ... -Full` auf Main bleibt die ausdrücklich gewählte vollständige lokale Validierung.

GitHub startet Prüfungen bei Pushes auf `main`, `feature/**` und `preview/**`. Preview-Prüfungen beziehen sich auf den tatsächlichen Merge-/Artefakt-Commit; ein früherer Feature-Lauf gilt nicht als Prüfung dieses Kandidaten. Quellcode-Features benötigen noch kein Release-Paket. Die vorhandenen umfangreichen PHP-, Frontend-, Infrastruktur- und Windows-Prüfungen sowie die konservativen CI-Klassifizierungsregeln bleiben erhalten. Fehler bleiben echte fehlgeschlagene Actions-Läufe; laufende oder fehlgeschlagene Volltests blockieren das Veröffentlichen nicht.

Vor Live prüft `gitdeploy` den unveränderlichen Git-Release einschließlich Paket- und Quellbindung. Der zusätzliche Smoke hat ein Gesamtlimit von 60 Sekunden: PHP-Syntax, Frontend-Verweise und Laravel-Bootstrap mit internem HTTP-Aufruf von `/up`. Er läuft aus einem temporären Snapshot des exakten Kandidaten mit frisch erzeugtem Schlüssel, SQLite im Speicher und blockierten externen Verbindungen. Die lokal installierten Composer-Abhängigkeiten müssen zum Release-Lock passen. Der Smoke prüft keine fachlichen Abläufe oder echten Datenbankmigrationen. SSH-/Zielprüfungen, ausdrückliches `LIVE` und erneute Main-Prüfung nach der Bestätigung bleiben bestehen.

Der bestätigte Aufruf überträgt den Launcher aus genau diesem Release mit Prüfsumme in `storage/framework` und startet ihn unter der bestehenden Deployment-Sperre. Dadurch funktioniert auch der erste Umstieg von einem älteren Server-Launcher mit CI-Pflicht, ohne erfundene CI-Laufdaten. Server-Konfiguration, Live-/Preview-Trennung und Paketprüfung nach dem Pull bleiben wirksam. Es erfolgt kein automatisches Deployment allein durch Speichern.

Für Fehler-E-Mails in den [persönlichen GitHub-Benachrichtigungen](https://github.com/settings/notifications) unter Actions E-Mail-Benachrichtigungen und gegebenenfalls „nur fehlgeschlagene Workflows“ aktivieren. Zustellung und Empfänger hängen von den persönlichen GitHub-Einstellungen ab; das Repository aktiviert oder testet keine E-Mail-Zustellung.

Nach `prepare` oder einer leeren/falschen Bestätigung zeigt `gitpreview` den vollständigen Fortsetzungsbefehl an. Der erfolgreiche Prüfnachweis wird als Windows-DPAPI-geschütztes JSON in `.git/schooltool-preview/<Bundle-ID>.receipt` gespeichert und bindet Original-Checkout, Feature-Reservierung, Main, Quellstand, Kandidat und Paket-Prüfsumme. Kandidat und Paket aufbewahren. Änderungen an diesen Ständen, ungesicherte Arbeit oder ein fehlender/ungültiger Nachweis stoppen die Wiederaufnahme; es werden nicht automatisch Tests neu gestartet. Ein neuer Quellstand benötigt eine neue Build- und Paketprüfung. Nach Beginn eines Veröffentlichungsversuchs sperrt eine lokale `.started`-Markierung die automatische Wiederholung; Git- und Serverzustand müssen dann gezielt geprüft werden.

Prüfkandidaten liegen in eigenen Arbeitsverzeichnissen ohne benannten Branch (Detached HEAD). Ihre Commits bleiben über lokale Wiederherstellungsreferenzen unter `refs/schooltool/candidates/` erreichbar. Deshalb zeigt `git branch` weiterhin nur die normalen Arbeitsbranches an; `git worktree list` zeigt zusätzlich die erhaltenen Prüfarbeitsverzeichnisse. Kandidaten nicht zum normalen Entwickeln verwenden oder ihre Referenzen manuell verschieben. Ältere Prüfnachweise bleiben unverändert lesbar, wenn der ursprüngliche Kandidatenbranch existiert oder genau sein archivierter Stand unter `refs/schooltool/archived-heads/` erhalten ist.

Bereits separat geprüfte ältere Nachweise (`legacy-reviewed` und die fest begrenzten `reviewed-patch`-Umfänge) behalten ihre Herkunfts-, Hash- und Quelldelta-Prüfungen. Lose Textlogs werden nicht automatisch übernommen. Neue V3-Nachweise bescheinigen ausschließlich `preflight-success` mit `inline-build-and-integrity`. V1-/V2-Nachweise behalten ihre ursprünglichen Nachweisregeln; ein schneller Check wird niemals nachträglich als bestandene Vollprüfung ausgegeben.

Die installierten Profilfunktionen laden den Workflow bei jedem Aufruf aus dem Projekt. Falls das Terminal noch ältere Funktionsdefinitionen hält, im Projektordner `. ./scripts/git_helpers.ps1` ausführen. Das überschreibt keine Profildatei.

### Gemeinsame Vorschau auf main zurückstellen

`gitpreview -Main` veröffentlicht den exakt gespeicherten aktuellen `main` ausschließlich in der isolierten Vorschau. Es entsteht kein Ersatz-Feature und keine Feature-Reservierung. Live bleibt unverändert. Alle PCs benötigen die aktuellen Helfer; im bestehenden Terminal `. ./scripts/git_helpers.ps1` laden.

Der erste Umstieg verwendet vor der Installation weiterhin die vorhandenen Status-, Export- und Planprüfungen. Die neue Abschlussfunktion kommt mit dem geprüften Vorschaupaket und wird erst nach dessen Installation aufgerufen. Ein separates Live-Deployment ist dafür nicht erforderlich. Fehlen schon die vorhandenen Snapshot-Befehle oder ist eine ältere Vorschau noch nicht isoliert eingerichtet, stoppt die Planung vor Veröffentlichung; dann gilt die dokumentierte Ersteinrichtung.

Zuerst lokale Änderungen prüfen und mit `gitsave "Main-Vorschau unterstützen"` normal veröffentlichen. Danach auf sauberem, mit GitHub übereinstimmendem `main`:

```powershell
gitpreview -Main
```

Beim Wechsel von einem Feature zuerst `REFRESH`, danach `PREVIEW` bestätigen. Die aktiven Vorschau-Testdaten werden nach privater Sicherung durch eine frische Live-Kopie ersetzt. Es gibt keine getrennt aufgehobenen Testdaten pro Feature. Nur die Vorschau erhält die Migrationen des geprüften Codes. Bei erneutem Veröffentlichen desselben Main-Modus bleiben Testdaten normalerweise erhalten; `gitpreview -Main -RefreshData` ersetzt sie ausdrücklich erneut.

`gitpreview prepare -Main` erstellt nur einen lokalen, quellengebundenen Kandidaten. `gitpreview resume <Bundle-ID> -Main` setzt ihn vor einem Veröffentlichungsversuch fort. `-Main` und `-Feature` sind gegenseitig ausgeschlossen. Ein geänderter Main-Stand, ein veränderter Vorschauplan oder eine belegte Operationssperre stoppen den Ablauf.

Erst nach erfolgreichem Import, Migrationen, Aktivierung und Abschluss gilt die Vorschau als Main-Modus. Bis dahin bleiben Wiederherstellungsnachweis und Verwerfen-Schutz wirksam; bei Fehlern Zustand und erhaltene Sicherung prüfen, keine Marker löschen oder denselben begonnenen Kandidaten blind wiederholen. Nach erfolgreichem Abschluss kann das zuvor aktive Feature verworfen werden:

```powershell
gitdiscard "feature/matura"
# Bestätigung: DISCARD feature/matura
```

Ein noch belegter Feature-Worktree muss vorher bewusst freigegeben werden. Bei sauberem Hilfs-Worktree kann `git -C "EXAKTER-PFAD" switch --detach HEAD` den Branch freigeben, ohne Dateien oder Commit zu entfernen. Vorher dort Status und laufende Arbeit prüfen.

### Ein Feature ohne Übernahme verwerfen

```powershell
gitmain
gitdiscard "testfunktion"
# Bestätigung: DISCARD feature/testfunktion
```

Auch `gitdiscard "feature/testfunktion"` ist möglich. Ungesicherte Änderungen, abweichende lokale/remote Feature-Commits, ein Worktree mit diesem Branch, eine fehlende oder widersprüchliche Reservierung sowie ein aktives oder nicht sicher prüfbares Vorschau-Feature stoppen den Ablauf. Für die lesende Vorschauprüfung ist die vorhandene PREVIEW-SSH-Konfiguration erforderlich. Der Befehl wechselt keinen Branch und ändert keine Datenbank. Andere Features, vorhandene Vorschau-Kandidaten und Prüfnachweise bleiben erhalten; ein verworfenes Feature kann mit seinen alten Nachweisen nicht mehr veröffentlicht werden.

Vor dem Löschen werden beide exakten Commit-IDs unter `refs/schooltool/discarded/<Lifecycle-ID>/<Vorgangs-ID>/feature` und `/reservation` gesichert. Nach einer teilweise abgeschlossenen Remote-Aktion niemals blind erneut löschen: Die Ausgabe unterscheidet Remote-Abschluss und lokale Aufräumprobleme. Die lokalen Wiederherstellungsreferenzen und etwaige Operationssperren erst nach Prüfung eines unterbrochenen Vorgangs anfassen.

`gitdiscard` und `gitpreview` verwenden nach ihrer Bestätigung dieselbe exklusive Lifecycle-Sperre auf GitHub (`codex/operations/<Lifecycle-ID>`). Deshalb müssen alle beteiligten PCs vor Nutzung des neuen Befehls die aktualisierten Helfer laden. Eine fremde oder nach Abbruch verbliebene Sperre wird nicht überschrieben. Zum erstmaligen Laden `. ./scripts/git_helpers.ps1` ausführen; dauerhaft die Profil-Wrapper mit `composer setup:powershell` aktualisieren. Weitere Schritte und Grenzen stehen in der [Bedienungsanleitung](public/documentation/git-workflow/index.html); ihre Docusaurus-Quelle liegt unter `C:/docusaurus/schooltool/src/pages/git-workflow.md`.

### Erste Veröffentlichung des neuen Ablaufs

1. Im vorhandenen Projekt `git status --short` und `git branch --show-current` prüfen. Die zu veröffentlichenden Änderungen müssen bekannt sein; `gitsave` nimmt alle Änderungen im aktuellen Branch auf. Bestehende Arbeit nicht durch Branchwechsel oder Reset verwerfen.
2. `composer setup:powershell` ausführen und ein neues PowerShell-Terminal im Projekt öffnen. So werden die neuen Helfer bereits vor der ersten Veröffentlichung geladen.
3. Auf `main` mit `gitsave "Git-Workflow und getrennte Vorschau einrichten"` veröffentlichen. Liegt die Arbeit bereits auf einem registrierten Feature, zuerst dort `gitsave`, anschließend `gitrelease` verwenden. Bei einem älteren Feature ohne Reservierung zunächst `gitwork NAME` zur Registrierung nutzen.
4. Bei bereits eingerichtetem Cloudways-Deployment `gitdeploy` mit kurzem Smoke und `LIVE` verwenden. Ein noch nicht eingerichteter Server benötigt eine separat geprüfte Erstinstallation; der ungeprüfte manuelle `composer pdeploy` ist kein Ersatz für die Freigabe. Die aktuelle laufende Anwendung wird durch das Aktualisieren der PC-Helfer allein nicht verändert.
5. Die Vorschau wie unten getrennt einrichten und einmalig mit dem veröffentlichten Anwendungscode bereitstellen. Erst nach erfolgreichen Konfigurationsprüfungen das erste `gitpreview` starten.

Ein Versionswechsel ist für diese Einrichtung nicht nötig. Wenn einer gewünscht ist, vorher passende Notizen unter der exakten Version in `UPDATES.md` ergänzen. Das Docusaurus-Projekt muss lokal verfügbar sein; Standardpfad ist `C:/docusaurus/schooltool`, abweichend über `SCHOOLTOOL_DOCUMENTATION_ROOT` konfigurierbar. Die Version wird ohne `v` angegeben.

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

Jeder PC erhält getrennte Schlüssel für Hauptanwendung und Vorschau. Neue Schlüssel nur an noch nicht belegten Dateipfaden erstellen; vorhandene Schlüssel nicht überschreiben. Beispielnamen:

```powershell
New-Item -ItemType Directory -Force -Path "$env:USERPROFILE\.ssh" | Out-Null
ssh-keygen -t ed25519 -a 64 -f "$env:USERPROFILE\.ssh\schooltool-main" -C "schooltool-main-mein-pc"
ssh-keygen -t ed25519 -a 64 -f "$env:USERPROFILE\.ssh\schooltool-preview" -C "schooltool-preview-mein-pc"
```

Eine Passphrase vergeben. Die beiden öffentlichen `.pub`-Dateien werden dem jeweiligen Cloudways-Anwendungszugang zugeordnet; die privaten Dateien bleiben auf diesem PC. Falls der Windows-OpenSSH-Agent noch nicht eingerichtet ist, einmal in einer administrativen PowerShell starten und für weitere Starts aktivieren:

```powershell
Set-Service -Name ssh-agent -StartupType Automatic
Start-Service ssh-agent
```

Anschließend in der normalen eigenen PowerShell die Schlüssel entsperren. Die Helfer bevorzugen Windows-OpenSSH; deshalb denselben Agent verwenden:

```powershell
& "$env:WINDIR\System32\OpenSSH\ssh-add.exe" "$env:USERPROFILE\.ssh\schooltool-main"
& "$env:WINDIR\System32\OpenSSH\ssh-add.exe" "$env:USERPROFILE\.ssh\schooltool-preview"
```

Vor der ersten Verbindung den SSH-Hostschlüssel-Fingerabdruck über einen unabhängig bestätigten Cloudways-Zugang verifizieren und den passenden Schlüssel in einer eigenen `known_hosts`-Datei speichern. Ein bloßes `ssh-keyscan` bestätigt die Identität des Servers nicht. Die Helfer akzeptieren nur bereits bekannte Hostschlüssel, verwenden ausschließlich Schlüsselauthentifizierung und reichen den Agent nicht an den Server weiter. Eine Passwort- oder Passphrase-Abfrage während des automatischen Deployments ist nicht vorgesehen.

Bei einer ersten interaktiven Verbindung den angezeigten Fingerabdruck nur nach diesem Vergleich bestätigen. Beispiel für eine lesende Prüfung; anschließend entsprechend für den Vorschau-Zugang wiederholen:

```powershell
& "$env:WINDIR\System32\OpenSSH\ssh.exe" -F none -o StrictHostKeyChecking=ask -o IdentitiesOnly=yes -o ForwardAgent=no -i "$env:USERPROFILE\.ssh\schooltool-main" '<hauptanwendungs-login>@<server-host>' 'id -un'
```

Folgende Windows-Benutzervariablen je Gerät setzen. Die Namen sind verbindlich; die Werte stammen aus der überprüften Einrichtung, nicht aus dieser Vorlage.

| Variable | Wert |
| --- | --- |
| `SCHOOLTOOL_MAIN_SSH` | `<hauptanwendungs-login>@<server-host>` |
| `SCHOOLTOOL_MAIN_UNIX_USER` | Tatsächliches `id -un` der Hauptanwendung nach dem SSH-Login |
| `SCHOOLTOOL_MAIN_PATH` | Geprüfter absoluter, kanonischer Pfad zur Hauptanwendung, endet auf `/public_html` |
| `SCHOOLTOOL_MAIN_KEY` | Absoluter lokaler Pfad zum privaten Hauptanwendungsschlüssel, ohne `.pub` |
| `SCHOOLTOOL_MAIN_KNOWN_HOSTS` | Absoluter lokaler Pfad zur geprüften Hostschlüsseldatei |
| `SCHOOLTOOL_PREVIEW_SSH` | `schooltool-feature@<server-host>` |
| `SCHOOLTOOL_PREVIEW_UNIX_USER` | Tatsächliches `id -un` und Eigentümerkonto der Vorschau-Anwendung |
| `SCHOOLTOOL_PREVIEW_PATH` | Eigener geprüfter kanonischer Vorschau-Pfad, endet auf `/public_html` |
| `SCHOOLTOOL_PREVIEW_KEY` | Absoluter lokaler Pfad zum privaten Vorschau-Schlüssel, ohne `.pub` |
| `SCHOOLTOOL_PREVIEW_KNOWN_HOSTS` | Absoluter lokaler Pfad zur geprüften Hostschlüsseldatei |

Cloudways kann einen SSH-Login-Alias auf ein anders benanntes Unix-Anwendungskonto abbilden. Deshalb `*_UNIX_USER` anhand von `id -un` setzen und nicht aus dem Login-Namen ableiten. Den Zielpfad mit `pwd -P` und den Vorschau-Verzeichniseigentümer ebenfalls prüfen. Bei ausgelassener `*_KNOWN_HOSTS`-Variable wird die vorhandene Datei `$env:USERPROFILE\.ssh\known_hosts` verwendet.

Benutzervariablen können in den Windows-Umgebungsvariablen oder einzeln so gespeichert werden:

```powershell
[Environment]::SetEnvironmentVariable('SCHOOLTOOL_MAIN_SSH', '<hauptanwendungs-login>@<server-host>', 'User')
```

Für jede Tabellenzeile den passenden Namen/Wert verwenden und anschließend ein neues Terminal öffnen. Die Helfer ignorieren benutzerspezifische SSH-Konfigurationsdateien; dort vorhandene Host-Aliase oder Spezialports ersetzen diese Angaben nicht.

### Cloudways einmalig vorbereiten

Die Haupt- und Vorschau-Anwendung müssen getrennte Anwendungsordner und Anwendungszugänge haben. Für die Vorschau werden eine eigene Datenbank, ein ausschließlich dafür berechtigtes Datenbankkonto und ein eigener `APP_KEY` benötigt. Die bestehende gemeinsam genutzte Live-Datenbank darf nicht als Vorschau-Ziel weiterverwendet werden.

| Bereich | Benötigte Einrichtung |
| --- | --- |
| Hauptanwendung | Veröffentlichter neuer Code, bestehende Live-Datenbank und unveränderter Live-`APP_KEY`; private Schlüsseldatei für die HTTPS-Kontrollverbindung und eigenes privates Snapshot-Verzeichnis ergänzen. |
| Vorschau | Eigener Code, eigene leere Datenbank mit anderem Namen, eigener Datenbanknutzer, eigener `APP_KEY`, eigene Dateien/Sessions/Caches und eigener Snapshot-Schlüssel. |
| Kontrollverbindung | Signierte HTTPS-Anfragen an den festen Kontrollendpunkt der Hauptanwendung. Derselbe ausschließlich dafür erzeugte Schlüssel liegt in getrennten privaten Dateien beider Anwendungen. Die Vorschau erhält keine Live-Datenbank-Zugangsdaten. |
| Vorschau-Datenbankrechte | Nur das Vorschau-Schema; Lese-, Schreib- und für Migrationen erforderliche Schema-Rechte. Keine Live-Rechte, globalen Rechte, Rollen oder `GRANT OPTION`. |
| Snapshot-Verzeichnisse | Je Anwendung ein vorhandener eigener kanonischer Ordner außerhalb der Anwendung und des Webverzeichnisses; Eigentümer ist das betreffende Anwendungskonto, Modus `0700`, keine Symlinks. Dateien darin sind `0600`. |
| SMTP | Eigener kontrollierter Vorschau-SMTP-Zugang. Nur freigegebene Anmelde-/Passwort-Nachrichten dürfen versendet werden. |

Auf Cloudways eignet sich ein eigener Unterordner `private_html/schooltool-preview` neben `public_html`. Cloudways sieht [`private_html` für vertrauliche Dateien außerhalb des Webverzeichnisses](https://support.cloudways.com/en/articles/5123384-securing-app-configuration-files-in-private_html-folder) vor; das Anwendungskonto kann dort seinen privaten Unterordner anlegen, auch wenn der gemeinsame Elternordner `root` gehört. Vorher Eigentümer und kanonischen Pfad prüfen, ausschließlich dem neuen Unterordner Modus `0700` geben und die bestehenden Verzeichnisrechte nicht pauschal verändern. Schlüssel und Sicherungen darin dürfen nicht durch eine Cloudways-Speicherbereinigung gelöscht werden.

Ein zusätzliches lesendes Live-Datenbankkonto entfällt. Wenn die Vorschau-App bereits ihre eigene Datenbank und ihr eigenes auf dieses Schema begrenztes Konto hat, ist dafür kein zusätzlicher `CREATE USER`-/`GRANT`-Schritt erforderlich. Den Snapshot liest die Hauptanwendung mit ihrem bestehenden Konto über eine eigene Verbindung in einer von MySQL erzwungenen Read-only-Transaktion mit konsistentem Stand. Laufende Zugangsprüfungen führt ausschließlich der feste Anwendungscode auf Main aus; die Vorschau kann keine eigenen SQL-Abfragen über die Kontrollverbindung senden.

Die Rechteprüfung liest `SHOW GRANTS`. Sie verlangt für die verwendeten Anwendungs-Datenbankkonten explizite Rechte auf genau ihrem eigenen Schema, keine Wildcards und keine Rollen. Unterstriche in MySQL-Schema-Grants müssen als Literale behandelt werden. Ein global berechtigtes Konto, etwa `root`, wird für diese Servervorgänge nicht akzeptiert. Der Vorschau-Nutzer darf selbst bei identischem Datenbankserver ausschließlich die Vorschau-Datenbank erreichen. MySQL/MariaDB-Verbindungen müssen ohne `DB_URL`, Socket, Tabellenpräfix oder Read/Write-Umleitungen konfiguriert sein.

Die Snapshot-Funktion unterstützt InnoDB-Basistabellen. Views, Trigger, Routinen, Datenbankevents und nicht unterstützte Tabellenstrukturen stoppen den Vorgang und müssen vor der Einrichtung geprüft werden. Auf dem Server werden außerdem PHP mit Sodium/PDO-MySQL sowie `composer`, `bash`, `rsync`, `flock`, `tar` und `sha256sum` benötigt.

### Serverkonfiguration: gemeinsame Angaben

Diese Variablen werden in der privaten Konfiguration beider Cloudways-Anwendungen benötigt. Die Kontroll-URL muss auf beiden Seiten exakt gleich sein und auf die Hauptanwendung zeigen; Schlüssel- und Snapshot-Pfade gehören jeweils zum eigenen Anwendungskonto.

| Variable | Bedeutung |
| --- | --- |
| `SCHOOLTOOL_PREVIEW_URL` | HTTPS-Ursprung der Vorschau, ohne Query oder Fragment |
| `SCHOOLTOOL_PREVIEW_LIVE_URL` | HTTPS-Ursprung der Hauptanwendung, anderer Host als die Vorschau |
| `SCHOOLTOOL_PREVIEW_CONTROL_URL` | `https://<hauptanwendungs-host>/api/feature-preview/control`; genau dieser Pfad, HTTPS-Port `443`, kein Query, Fragment oder Redirect |
| `SCHOOLTOOL_PREVIEW_CONTROL_KEY_PATH` | Absoluter kanonischer Pfad zur eigenen privaten Datei mit dem gemeinsamen Kontrollschlüssel; außerhalb der Anwendung und jedes `public_html` |
| `SCHOOLTOOL_PREVIEW_SNAPSHOT_DIRECTORY` | Privater kanonischer Snapshot-Ordner dieser Anwendung außerhalb von `public_html` |

Alle alten `SCHOOLTOOL_PREVIEW_CONTROL_DB_*`-Variablen und eine eventuell selbst ergänzte `preview_control`-Datenbankverbindung entfernen. Die Vorschauprüfung blockiert verbliebene Live-Kontrolldatenbank-Zugangsdaten. Die Hauptanwendung muss über die angegebene HTTPS-Adresse mit gültigem Zertifikat erreichbar sein. Signierte Anfragen und Antworten werden geprüft; Weiterleitungen werden nicht verfolgt. Beide Serveruhren müssen korrekt synchronisiert sein.

Der Kontrollschlüssel besteht aus genau **32 rohen, kryptografisch zufälligen Bytes**, nicht aus einem Base64- oder Hex-Text. Einmal erzeugen und denselben Inhalt über die verifizierten SSH-Zugänge in je eine eigene Datei beider Anwendungskonten übertragen. Er darf nicht aus `APP_KEY`, SSH-Schlüsseln oder dem Snapshot-Empfängerschlüssel abgeleitet werden. Beide Dateien erhalten Modus `0600`, ihr jeweiliger vorhandener Elternordner `0700` und den jeweiligen Anwendungseigentümer; keine Symlinks oder gemeinsam verlinkten Dateien. Den Inhalt niemals im Terminal ausgeben oder in Git speichern.

Beispiel zur einmaligen Erzeugung auf der Hauptanwendung in einem bereits geprüften privaten Ordner; ein vorhandener Dateiname wird nicht überschrieben:

```bash
php -r 'umask(0077); $file = fopen($argv[1], "x+b"); if ($file === false) { exit(1); } if (fwrite($file, random_bytes(32)) !== 32 || ! fflush($file)) { fclose($file); unlink($argv[1]); exit(1); } fclose($file);' '/absoluter/privater/ordner/control.key'
```

Diese Datei anschließend geschützt zur Vorschau übertragen, dort nicht einen zweiten unabhängigen Kontrollschlüssel erzeugen. Lokale Übertragungsdateien privat halten und nach erfolgreicher Einrichtung entfernen. Nach Änderungen an den privaten Servervariablen den Konfigurationscache der jeweiligen Anwendung kontrolliert aktualisieren; ein alter Cache darf nicht weiter die vorherigen Datenbankzugänge verwenden.

In der Hauptanwendung bleibt `SCHOOLTOOL_PREVIEW_INSTANCE=false`. Ihre vorhandenen Live-Datenbank-, Mail-, Worker- und Anwendungsschlüssel-Einstellungen werden dafür nicht durch Vorschau-Werte ersetzt.

### Serverkonfiguration: ausschließlich Vorschau

| Variable/Einstellung | Vorschau-Wert |
| --- | --- |
| `SCHOOLTOOL_PREVIEW_INSTANCE` | `true` |
| `APP_ENV` | `production` für die online erreichbare Vorschau |
| `APP_DEBUG` | `false` |
| `APP_URL` | Genau der HTTPS-Ursprung der Vorschau |
| `SCHOOLTOOL_PREVIEW_EXPECTED_HOST` | Nur der Vorschau-Hostname, ohne Protokoll oder Pfad |
| `APP_KEY` | Neu erzeugter eigener Anwendungsschlüssel; niemals den Live-Schlüssel übernehmen |
| `APP_PREVIOUS_KEYS` | Leer; keine alten Live-Schlüssel hinterlegen |
| `DB_CONNECTION` | `mysql` oder entsprechend konfigurierte `mariadb`-Verbindung |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE` | Verbindung zur eigenen Vorschau-Datenbank; Name muss von Live abweichen |
| `DB_USERNAME`, `DB_PASSWORD` | Ausschließlich für diese Vorschau-Datenbank berechtigtes Konto |
| `DB_URL`, `DB_SOCKET` | Nicht setzen bzw. leer lassen |
| `SCHOOLTOOL_PREVIEW_SNAPSHOT_KEY_PATH` | Vollständiger Dateipfad innerhalb des privaten Vorschau-Snapshot-Verzeichnisses |
| `FILESYSTEM_DISK` | `local`; `local` und `public` bleiben lokale Disks unter dieser Anwendung in `storage/app/private` bzw. `storage/app/public` |
| `SESSION_DRIVER` | `file` |
| `SESSION_COOKIE` | Eigener Name mit `preview`, z. B. `schooltool_preview_session` |
| `SESSION_DOMAIN` | Leer, damit Cookies ausschließlich zum Vorschau-Host gehören |
| `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY` | Jeweils `true` |
| `SESSION_SAME_SITE` | `lax` oder `strict` |
| `CACHE_STORE` | `file`; Rate-Limiter und Berechtigungscache verwenden ebenfalls diesen lokalen Store |
| `QUEUE_CONNECTION` | `sync` |
| `BROADCAST_CONNECTION` | `log` oder `null` |
| `APP_MAINTENANCE_DRIVER` | `file` |
| `PULSE_ENABLED` | `false`; Telescope/Nightwatch müssen, falls vorhanden, ebenfalls deaktiviert sein |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | Geprüfter Vorschau-SMTP-Zugang |
| `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Zum SMTP-Dienst passende sichere Versandkonfiguration und eindeutig erkennbare Vorschau-Absenderangaben |

Die Vorschau erzwingt TLS für SMTP automatisch. Der Dienst muss ESMTP mit STARTTLS oder direktes TLS (`MAIL_SCHEME=smtps`, gewöhnlich Port 465) mit einem gültigen, zum SMTP-Host passenden Zertifikat unterstützen; der Rückfall auf unverschlüsseltes altes SMTP ist gesperrt. `preview:check` prüft den tatsächlich aufgelösten Transport einschließlich `MAIL_URL`; Optionen wie `require_tls=false` für unverschlüsseltes SMTP oder `verify_peer=false` werden abgelehnt. SMTP-Benutzername und Passwort müssen gesetzt sein.

Keine Produktions-`.env` in die Vorschau kopieren. Auch nicht benötigte Live-API-, Redis-, Cloud-Storage- und Mail-Zugangsdaten gehören nicht in ihre Konfiguration. `storage` und seine Disks dürfen keine Symlinks auf Live-Dateien enthalten. Die Anwendungskonfiguration und private Sicherungen dürfen nicht öffentlich erreichbar sein. `public/storage` muss in der Vorschau fehlen; vorhandene Links oder Ordner erst nach Prüfung entfernen. Für die Vorschau keine Scheduler-, Horizon- oder Queue-Worker-Prozesse einrichten.

### Vorschau-Code erstmals bereitstellen und prüfen

Vor dem ersten automatischen `gitpreview` müssen der neue Kontrollendpunkt auf Main und die neuen Artisan-Befehle in der Vorschau bereits vorhanden sein: Der PC fragt zuerst ihren Snapshot-Status ab. Die Hauptanwendung zuerst wie oben veröffentlichen und die HTTPS-Kontrollverbindung samt beiden privaten Schlüsseldateien einrichten. Die bestehende Vorschau anschließend einmalig kontrolliert mit dem veröffentlichten `main`-Code bereitstellen, unter Beibehaltung ausschließlich ihrer eigenen Konfiguration und ihres eigenen Storage. Dafür nicht `composer pdeploy` und nicht `composer deploy` verwenden. Eine noch mit Live verbundene Vorschau vorher geschlossen halten und auf ihre eigene Datenbank umstellen.

Nach Bereitstellung des vertrauenswürdigen Codes im geprüften Vorschau-Verzeichnis, während die Vorschau für Zugriffe geschlossen bleibt, zunächst ohne Anwendungsskripte installieren:

```bash
composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts
```

Vor dem ersten neuen Artisan-Aufruf die getrennte Vorschau-Konfiguration fertigstellen und den bisherigen Konfigurationscache kontrolliert aus dem aktiven Pfad nehmen. Den tatsächlichen Pfad einschließlich eines möglichen `APP_CONFIG_CACHE` prüfen; normalerweise ist es `bootstrap/cache/config.php`. Alte `.env`- und Cache-Sicherungen können Live-Zugangsdaten enthalten: Falls zur Wiederherstellung benötigt, vor der Umstellung verschlüsselt auf dem administrierenden PC außerhalb des Repositorys sichern und die Sicherung prüfen. Keine für das Vorschau-Konto lesbare Kopie dieser alten Geheimnisse auf dem Server zurücklassen, auch nicht unter `private_html`. Erst danach die geprüfte alte Cache-Datei gezielt entfernen und die Vorschau-Konfiguration ersetzen. Auch übergeordnete Servervariablen dürfen keine alten Live-Werte vorgeben. Erst wenn kein alter Cache mehr geladen wird, fortfahren: Ein bloßes Ändern der `.env` reicht nicht, und auch `config:clear` startet zunächst die Anwendung mit ihrer bisherigen effektiven Konfiguration.

```bash
php artisan config:clear --no-interaction
php artisan package:discover --no-interaction
```

Den eigenen Vorschau-`APP_KEY` einmalig sicher erzeugen und in ihrer privaten Konfiguration setzen. `php artisan key:generate --force --no-interaction` darf nur bei dieser gezielten Ersteinrichtung verwendet werden, bevor Vorschau-Daten importiert werden. Weder den Live-`APP_KEY` ändern noch später einen verwendeten Vorschau-Schlüssel ohne Migrationsplan ersetzen.

Die privaten Snapshot-Ordner und benötigten lokalen Cache-/Session-Verzeichnisse müssen bereits existieren und dem richtigen Anwendungskonto gehören. Anschließend ausschließlich in der Vorschau:

```bash
php artisan preview:snapshot key:generate --no-interaction
php artisan preview:check --configuration-only --no-interaction
```

Der erste Befehl legt den Empfängerschlüssel exklusiv am konfigurierten privaten Pfad an und gibt ausschließlich dessen öffentlichen Teil zurück. Ein bestehender Schlüssel wird nicht überschrieben. Der zweite Befehl prüft Datenbankrechte, getrennte Pfade, die signierte Verbindung zur Hauptanwendung und die Laufzeitkonfiguration lesend; er importiert nichts. Dafür muss der Main-Endpunkt bereits erreichbar und korrekt eingerichtet sein. Anwendungsschlüssel, Snapshot-Empfängerschlüssel und Kontrollschlüssel erfüllen unterschiedliche Aufgaben und müssen privat gesichert werden. Ohne die ursprünglichen Anwendungs- und Snapshot-Schlüssel sind verschlüsselte Sicherungen nicht zuverlässig wiederherstellbar.

Danach am PC das gespeicherte Feature mit `gitpreview` veröffentlichen. Ein separates `gitupdate` ist davor nicht nötig: `gitpreview` integriert `main` im Kandidaten selbst. Das Deployment prüft und sichert vor dem Ersetzen, importiert die Kopie und führt anschließend ausschließlich die Vorschau-Migrationen aus. Keine Seeder und kein allgemeines `app:update` in der Vorschau. Auch bei einer reinen Codeaktualisierung wird vor den Migrationen ein Wiederherstellungspunkt angelegt.

Nach dem ersten erfolgreichen Deployment kann auf der Vorschau zusätzlich `php artisan preview:check --no-interaction` aufgerufen werden. Anmelden, Berechtigungen, eine kleine Vorschau-Datenänderung und den kontrollierten E-Mail-Versand separat testen; anschließend prüfen, dass der Live-Datensatz unverändert blieb. Erst dieser Schritt bestätigt die konkrete Online-Einrichtung.

### Vorschau-Zugang, E-Mails und Daten

Vorschau-Zugänge ausschließlich in der Hauptanwendung verwalten. Jede Testperson benötigt eine aktive, geeignete und ausdrücklich freigeschaltete Identität. Das gilt auch für Administrierende. Die Freigabe verleiht keine weiteren Anwendungsrechte. Schülerinnen, Schüler, Studierende und Eltern können die für sie freigegebenen Bereiche verwenden; Eltern behalten ihre bestehenden eingeschränkten Zugriffe. Registrierung, Identitätsübernahme und das Anmeldetool bleiben gesperrt.

In der Vorschau erfolgt die Anmeldung mit den eigenen Zugangsdaten beziehungsweise dem vorgesehenen Anmeldecode; das Superadmin-Kennwort kann dort nicht zur Anmeldung bei einem anderen Konto verwendet werden.

Die Vorschau prüft Zugangsfreigaben und Berechtigungen über die signierte HTTPS-Kontrollverbindung gegen die Hauptanwendung. Sie besitzt dafür weder einen Live-Datenbankzugang noch den Live-`APP_KEY`. Ist die Kontrollverbindung nicht erreichbar oder wurde der Zugriff entzogen, bleibt der Zugang geschlossen. Nach einer sicherheitsrelevanten Änderung an einer Live-Identität, etwa Kennwort oder Zwei-Faktor-Konfiguration, kann eine neue Datenkopie nötig sein; alte Anmeldedaten werden nicht stillschweigend weiter akzeptiert.

Anmelde- und Passwort-Zurücksetzen-Nachrichten dürfen ausschließlich an die aktuell zulässigen Empfänger einer freigeschalteten Identität gehen. Versendete Nachrichten sind mit `[VORSCHAU]` gekennzeichnet und dürfen nur auf den Vorschau-Ursprung verweisen. Andere E-Mails werden abgefangen und mit Metadaten protokolliert; Nachrichteninhalt und Anmeldecodes werden dabei nicht ins Versand-Audit geschrieben. Externe HTTP-Integrationen außer dem festen signierten Main-Kontrollendpunkt, Remote-Storage, Redis-Verbindungen und externe Broadcasts sind in der Vorschau gesperrt.

Die erste Veröffentlichung erhält eine Live-Kopie; auch ein späteres neues Feature mit demselben Namen hat eine neue interne Identität. Weitere Veröffentlichungen des aktuell angezeigten Features behalten dessen Testdaten. Jeder Wechsel A→B oder B→A ersetzt die bisher aktiven Testdaten nach `REFRESH` und `PREVIEW` durch eine frische Live-Kopie. Es gibt keinen gespeicherten Testdatenstand je Feature. `gitpreview -Feature NAME -RefreshData` ersetzt Daten auch ohne Featurewechsel. Private Sicherungen dienen weiterhin der gezielten Fehlerwiederherstellung. Live-Sitzungen, aktive Reset-/Zugriffstoken und ausstehende Jobs werden nicht als aktive Vorschau-Zustände übernommen. Bekannte verschlüsselte Anwendungsfelder werden für den eigenen Vorschau-Schlüssel neu verschlüsselt.

Der Snapshot-Status muss einen `state_token` liefern. Der PC prüft diesen nach der Bestätigung erneut; der Server prüft ihn unter seinem Deployment-Lock vor Änderungen. Ein inzwischen geänderter Vorschauzustand stoppt den alten Plan. Vor der ersten Nutzung des erweiterten Ablaufs muss die bestehende Vorschau die neuen `status`-/`assert-plan`-Befehle unterstützen; ein alter Server wird sicher abgewiesen.

Bei einer bereits korrekt isolierten älteren Vorschau erfolgt der einmalige Einstieg als ausdrücklich freigegebenes Kompatibilitätsupdate: `app/Services/FeaturePreviewSnapshotService.php` und `app/Console/Commands/FeaturePreviewSnapshotCommand.php` aus dem geprüften Workflow-Release verwenden. Vorher `preview:check` und Snapshotstatus für die vorhandene Lifecycle-ID prüfen und die SHA256-Werte der installierten Dateien mit dem geprüften Ausgangsstand vergleichen. Die zwei neuen Dateien über den vorhandenen SSH-Byte-Transfer in ein exklusives privates Verzeichnis laden, ihre Prüfsummen und PHP-Syntax prüfen. Unter demselben exklusiven `storage/framework/preview-deploy.lock` die alten Dateien privat sichern, ihre unveränderten Hashes erneut prüfen und ausschließlich diese zwei Dateien ersetzen. Konfiguration, Schlüssel, Datenbank, Uploads und Snapshotzustand bleiben dabei unangetastet. Danach `preview:check`, `preview:snapshot status --feature=ID` und `preview:snapshot assert-plan --feature=ID --state-token=TOKEN` prüfen; `needs_snapshot` muss für das bestehende Feature weiterhin `false` sein. Weichen Ausgangsdateien oder bestehende Datenbindung ab, den Updateplan neu prüfen. Anschließend den aktuellen Workflow in das vorhandene Feature übernehmen und regulär `gitpreview -Feature NAME` mit Build- und Paketprüfung ausführen. So ersetzt der reguläre Kandidat den vorübergehenden Kompatibilitätspatch durch einen vollständig gebundenen Release. Ein neuer Server ohne gültige isolierte Konfiguration benötigt weiterhin die oben beschriebene vollständige Ersteinrichtung.

Ein bereits geprüfter Kandidat muss dasselbe Deploymentskript wie die geladenen aktuellen Helfer enthalten. Alte Prüfnachweise bleiben lesbar und erhalten, dürfen aber kein älteres Skript ohne diesen Schutz veröffentlichen. In diesem Fall den aktuellen Workflow in das Feature übernehmen und einen neuen Kandidaten vollständig prüfen lassen.

Die Hauptanwendung kopiert ihre Datenbank über eine eigene Verbindung in einer konsistenten Read-only-Transaktion mit Repeatable Read; die gewöhnliche Live-Verbindung bleibt davon unabhängig. Der Import prüft den verschlüsselten Snapshot gegen die unabhängig über die signierte HTTPS-Verbindung bestätigte Live-Datenbank-, Server- und Schlüsselfingerabdruck-Identität sowie die per SSH erhaltene Export-Prüfsumme und den Vorschau-Empfänger. Ein unvollständiger oder falsch zugeordneter Snapshot wird nicht importiert.

Dateibestand und Prüfsummen werden vor und nach dem Export verglichen; erkannte Änderungen brechen den Export ab. Das ist keine atomare gemeinsame Datenbank-/Dateisystem-Sicherung und kein Ersatz für ein Live-Notfallbackup. Es wird dafür kein neuer automatischer Live-Wartungsmodus aktiviert. Bei gleichzeitig laufenden Dateiänderungen später erneut versuchen.

Die Kopie bleibt personenbezogen und ist entsprechend geschützt aufzubewahren. Platz für Datenkopie, verschlüsselte Sicherung, Dateizwischenstände und vorherige Vorschau-Dateien vorsehen. Sicherungen werden zur Wiederherstellung bewusst erhalten; ihre geprüfte Aufbewahrung und spätere Bereinigung einplanen. Vorschau-Daten werden niemals zurück nach Live übertragen. `gitrelease` veröffentlicht Code; `gitdeploy` führt die vorgesehenen Migrationen auf Live aus.

### Störungen und Wiederherstellung

| Situation | Vorgehen |
| --- | --- |
| SSH-Zugang scheitert | Zielvariablen, richtiges Unix-Konto, Agent, Schlüsselzuordnung und verifizierten Hostschlüssel prüfen. Hostschlüsselprüfung nicht abschalten. |
| Vorschau-Kontrollprüfung scheitert | Main-Code und festen HTTPS-Endpunkt, gültiges Zertifikat, gleiche Kontroll-URL/Schlüsselbytes, private Dateirechte, aktuelle Konfiguration und synchronisierte Serveruhren prüfen. Keinen Live-Datenbankzugang als Ersatz eintragen. |
| GitHub hat neuere Commits | Mit `gitmain` bzw. `gitwork` aktualisieren; bei echter Divergenz bewusst auflösen. Kein blindes Überschreiben oder Zurücksetzen. |
| Veröffentlichung nach Prüfungen gescheitert | Lokale Commits und Kandidaten bleiben erhalten. Bei Verbindungsabbruch erst `gitcheck` und GitHub prüfen, dann gezielt fortsetzen. |
| Release veröffentlicht, lokale Vorbereitung gescheitert | Das Release bleibt veröffentlicht und der lokale Feature-Branch erhalten. Ursache beheben und `gitmain` wiederholen; danach den integrierten lokalen Branch bei Bedarf mit `git branch -d feature/name` entfernen. Nicht erneut veröffentlichen und kein `-D` verwenden. |
| Vorschau-Export fehlgeschlagen | Die Live-Datenbank wurde vom Export nicht verändert. Ursache beheben; ein vollständiger geprüfter Snapshot wird vor dem Import verlangt. |
| Vorschau nach Datenimport oder Migration fehlgeschlagen | Vorschau geschlossen lassen. Private Sicherung und Wiederherstellungsmarker erhalten. Den folgenden Wiederherstellungsablauf verwenden. |
| Live-Deployment fehlgeschlagen | Live-Ausgabe und bestehenden Wartungs-/Deploymentstatus prüfen. Die Vorschau-Wiederherstellung ist kein Live-Rollback. Nicht ungeprüft `artisan up` aufrufen. |

Nach einem begonnenen Vorschau-Import oder einem vorbereiteten Migrationslauf liegt im privaten Snapshot-Ordner ein `snapshot-pending.json` mit dem geprüften Sicherungsbezug. Zur bewussten Wiederherstellung ausschließlich im überprüften Vorschau-Verzeichnis:

```bash
php artisan preview:snapshot restore --replace --no-interaction
```

Dieser Befehl stellt die zuvor gesicherte Vorschau-Datenbank, Vorschau-Dateien und den vorherigen Vorschau-Zustand wieder her. Er akzeptiert ausschließlich den zum offenen Wiederherstellungsvorgang gehörenden privaten Sicherungsstand. Die Vorschau bleibt geschlossen; Code wird dabei nicht zurückgerollt. Anschließend Ursache beheben und am PC erneut `gitpreview` für passende Code- und Migrationsprüfungen ausführen. Weder den Pending-Marker manuell löschen noch die Vorschau vor diesen Prüfungen öffnen. Fehlt eine gültige Sicherung oder ist deren Schlüssel nicht verfügbar, erst den Zustand untersuchen und keinen neuen Import erzwingen.

### Typische Abläufe

| Anlass | Ablauf |
| --- | --- |
| Feature beginnen | `gitstart "neue-funktion"` → entwickeln → `gitsave "Beschreibung"` |
| Gerät oder Feature wechseln | Erfolgreiches `gitsave`; anschließend `gitwork NAME` oder `gitmain` |
| Hauptanwendung korrigieren | Feature sichern → `gitmain` → korrigieren → `gitsave "Fehler behoben"` → `gitdeploy` → Smoke und `LIVE` |
| Korrektur im Feature weiterverwenden | `gitwork` → `gitupdate` → testen → `gitsave "main übernommen"` |
| Online testen | `gitwork NAME` → `gitsave "Vorschau vorbereitet"` → `gitpreview -Feature NAME` |
| Feature freigeben | `gitsave "Funktion fertig"` → `gitrelease "Neue Funktion"` → `gitdeploy` → Smoke und `LIVE` |
| Lokal den vollständigen Hauptstand installieren | `gitmain` → `composer deploy` |

Git wechselt keine lokalen Datenbanken. `gitupdate` führt ausstehende additive Migrationen nach Prüfung von Ziel und SQL automatisch auf der konfigurierten lokalen Datenbank aus; unklare oder destruktive Migrationen werden gestoppt. Für experimentelle Feature-Migrationen eine eigene lokale Feature-Datenbank verwenden. `gitmain` und `gitwork` führen keine Migrationen aus und machen bereits ausgeführte Migrationen nicht rückgängig.

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
