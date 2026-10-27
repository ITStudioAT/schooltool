<template>
    <article class="security-lesson" aria-labelledby="security-title">
        <header class="lesson-header">
            <div><p class="eyebrow">Netzwerke verstehen</p><h1 id="security-title">Netzwerksicherheit und Datenschutz</h1></div>
            <span class="page-number">{{ pageIndex + 1 }} / {{ pages.length }}</span>
        </header>
        <nav class="lesson-navigation" aria-label="Themen auswählen">
            <button v-for="(page, index) in pages" :key="page.key" type="button"
                :aria-pressed="pageIndex === index" @click="openPage(index)">{{ page.label }}</button>
        </nav>
        <main class="lesson-content">
            <div class="section-heading">
                <div><span>{{ currentPage.kicker }}</span><h2>{{ currentPage.title }}</h2></div>
                <p>{{ currentPage.intro }}</p>
            </div>
            <div class="two-columns">
                <section class="visual-panel" aria-label="Beispiel auswählen">
                    <div class="security-flow" role="img" :aria-label="selected.flow.join(' → ')">
                        <template v-for="(part, index) in selected.flow" :key="index">
                            <span>{{ part }}</span><b v-if="index < selected.flow.length - 1" aria-hidden="true">→</b>
                        </template>
                    </div>
                    <div class="choice-grid">
                        <button v-for="item in currentPage.items" :key="item.name" type="button"
                            :aria-pressed="selection === item.name" @click="selectItem(item.name)">
                            <strong>{{ item.name }}</strong><small>{{ item.short }}</small>
                        </button>
                    </div>
                    <p class="caption">{{ currentPage.caption }}</p>
                </section>
                <section class="detail-panel" aria-live="polite" aria-atomic="true">
                    <span>Einfach erklärt</span><h3>{{ selected.name }}</h3>
                    <p>{{ selected.description }}</p>
                    <p class="note">{{ selected.note }}</p>
                    <div v-if="selected.example" class="example"><strong>Beispiel im Schulnetz</strong><p>{{ selected.example }}</p></div>
                    <div v-if="selected.examples" class="example">
                        <strong>Konkrete MFA-Beispiele</strong>
                        <ul><li v-for="example in selected.examples" :key="example">{{ example }}</li></ul>
                    </div>
                    <details v-for="section in selected.guide || []" :key="section.title" class="guide-section">
                        <summary>{{ section.title }}</summary>
                        <p v-for="paragraph in section.paragraphs || []" :key="paragraph">{{ paragraph }}</p>
                        <ol v-if="section.steps"><li v-for="step in section.steps" :key="step">{{ step }}</li></ol>
                    </details>
                </section>
            </div>
        </main>
        <footer class="lesson-footer">
            <button type="button" :disabled="pageIndex === 0" @click="openPage(pageIndex - 1)">← Zurück</button>
            <span>{{ currentPage.label }} · {{ pageIndex + 1 }} von {{ pages.length }}</span>
            <button type="button" class="next-button" :disabled="pageIndex === pages.length - 1" @click="openPage(pageIndex + 1)">Weiter →</button>
        </footer>
        <details class="lesson-sources">
            <summary>Quellen und weiterführende Informationen</summary>
            <p>Nach dem Lernmodul „Netzwerksicherheit und Datenschutz“ von Dipl.-Ing. Günther Kron (26.10.2026).</p>
            <a v-for="source in sources" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer">{{ source.title }}</a>
        </details>
    </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const pages = [
    {
        key: 'goals', label: 'Schutzziele', kicker: 'Daten und Dienste schützen', title: 'Was soll sicher bleiben?',
        intro: 'Netzwerksicherheit schützt vor unbefugtem Zugriff, Veränderung und Ausfall. Mehrere Maßnahmen ergänzen einander.',
        caption: 'Keine einzelne Technik verhindert jede Gefahr. Eine Maßnahme kann mehrere Schutzziele unterstützen.',
        items: [
            { name: 'Vertraulichkeit', short: 'nur für Berechtigte', flow: ['Information', 'Zugriffsschutz', 'berechtigte Personen'], description: 'Informationen sind nur für berechtigte Personen oder Systeme zugänglich. Zugriffsschutz und passende Verschlüsselung können dazu beitragen.', note: 'Daten dürfen nicht allein deshalb frei zugänglich sein, weil sie technisch erreichbar sind.', example: 'Eine persönliche Rückmeldung ist nur für die vorgesehenen Personen sichtbar.' },
            { name: 'Integrität', short: 'korrekt und nachvollziehbar', flow: ['Information', 'Änderung prüfen', 'korrekte Daten'], description: 'Informationen bleiben korrekt. Veränderungen sind erkennbar oder nur berechtigt möglich.', note: 'Die Erkennung unbemerkter Veränderungen unterstützt die Integrität.', example: 'Ein Aufgabenblatt darf nur von berechtigten Personen verändert werden.' },
            { name: 'Verfügbarkeit', short: 'bei Bedarf nutzbar', flow: ['Daten und Dienste', 'Betrieb / Wiederherstellung', 'bei Bedarf verfügbar'], description: 'Dienste und Daten stehen bei Bedarf im vorgesehenen Umfang zur Verfügung. Eine geeignete Wiederherstellung hilft auch nach einem Ausfall.', note: 'Ein Dienst kann vertrauliche und korrekte Daten enthalten und trotzdem wegen eines Ausfalls unbenutzbar sein.', example: 'Nach einem Geräteausfall werden benötigte Dateien aus einer geschützten Sicherung wiederhergestellt.' },
        ],
    },
    {
        key: 'identity', label: 'Konten & MFA', kicker: 'Identität und erlaubte Aktionen', title: 'Anmelden heißt nicht alles dürfen',
        intro: 'Ein Benutzerkonto bezeichnet eine Identität. Der Identitätsnachweis und die Zugriffsentscheidung erfüllen verschiedene Aufgaben.',
        caption: 'Administrationsrechte erlauben weitreichende Änderungen und brauchen deshalb besonderen Schutz.',
        items: [
            { name: 'Authentifizierung', short: 'Identität nachweisen', flow: ['Benutzerkonto', 'Nachweis prüfen', 'Anmeldung'], description: 'Bei der Authentifizierung wird nachgewiesen, dass eine Person oder ein Gerät die behauptete Identität verwenden darf. Mögliche Nachweise sind ein Passwort, Sicherheitsschlüssel oder Zertifikat.', note: 'Eine erfolgreiche Anmeldung bedeutet nicht automatisch Zugriff auf alle Dateien und Dienste.' },
            { name: 'Autorisierung', short: 'Zugriffe beschränken', flow: ['angemeldete Identität', 'Berechtigungen prüfen', 'erlaubte Handlung'], description: 'Die Autorisierung bestimmt, was eine Identität tun darf. Rechte lassen sich über Benutzergruppen vergeben. Jedes Konto erhält nur die für seine Aufgaben erforderlichen Rechte.', note: 'Lesen, Ändern und Administrieren können unterschiedliche Berechtigungen erfordern.', example: 'Die Unterrichtsgruppe darf ein Material lesen; nur die zuständige Lehrperson darf es verändern.' },
            {
                name: 'MFA', short: 'unterschiedliche Faktoren', flow: ['Passwort · Wissen', 'Sicherheitsschlüssel · Besitz', 'mehrere Faktoren'],
                description: 'Mehrfaktor-Authentifizierung kombiniert mindestens zwei unterschiedliche Arten von Nachweisen, etwa Wissen und Besitz.',
                note: 'Passwort und PIN allein sind zwei Wissensnachweise und damit keine zwei unterschiedlichen Faktoren. MFA erschwert unbefugte Anmeldungen, ersetzt aber keine weiteren Schutzmaßnahmen.',
                examples: [
                    'Passwort + Code aus einer Authenticator-App: Du gibst dein Passwort ein (Wissen) und danach den wechselnden Code der auf deinem Smartphone eingerichteten App (Besitz).',
                    'Passwort + USB- oder NFC-Sicherheitsschlüssel: Nach dem Passwort (Wissen) bestätigst du die Anmeldung mit deinem registrierten Sicherheitsschlüssel (Besitz).',
                    'Passwort + Bestätigung in einer App: Nach dem Passwort (Wissen) bestätigst du deine eigene Anmeldung in der eingerichteten App auf deinem Smartphone (Besitz). Eine solche Freigabe kann zusätzlich einen angezeigten Zahlenabgleich verlangen.',
                    'Chipkarte + PIN: Du steckst deine persönliche Karte in ein Lesegerät (Besitz) und gibst die zugehörige PIN ein (Wissen), etwa bei einer Anmeldung mit einer Smartcard.',
                ],
            },
        ],
    },
    {
        key: 'boundaries', label: 'Netzgrenzen', kicker: 'Zugänge und Verbindungen regeln', title: 'Welche Verbindung ist erlaubt?',
        intro: 'Zugangskontrolle, getrennte Netzbereiche und Verbindungsregeln übernehmen unterschiedliche Teile des Schutzes.',
        caption: 'Die tatsächliche Konfiguration entscheidet. Ein VLAN oder NAT allein ersetzt keine Firewallregeln.',
        items: [
            { name: '802.1X', short: 'Netzzugang mit Identitätsnachweis', flow: ['Person / Gerät', 'Switch / Access Point', 'RADIUS-Server'], description: '802.1X kontrolliert den Zugang an Netzwerkanschlüssen und in passenden WLANs. Ein Switch oder Access Point vermittelt den Identitätsnachweis häufig an einen RADIUS-Server. Dieser entscheidet nach den eingerichteten Regeln über den Zugang.', note: 'Verwendete Nachweise und Schutzmechanismen hängen von der Konfiguration ab.' },
            { name: 'Firewall', short: 'Verbindungen nach Regeln', flow: ['Verbindungsversuch', 'Firewallregeln', 'erlauben / sperren'], description: 'Eine Firewall kontrolliert Netzwerkverbindungen nach festgelegten Regeln. Diese können Quell- und Zielnetz, Protokoll und Port berücksichtigen.', note: 'Eine Firewall allein schützt nicht vor jeder schädlichen Datei oder jeder betrügerischen Nachricht.' },
            { name: 'Segmentierung und VLAN', short: 'Bereiche trennen', flow: ['Gästenetz', 'Routing und Zugriffsregeln', 'internes Netz'], description: 'Segmentierung teilt ein Netz in Bereiche. VLANs können Geräte logisch zu getrennten lokalen Netzbereichen zusammenfassen. Kommunikation zwischen ihnen benötigt in der Regel Routing; Zugriffsregeln bestimmen die erlaubten Verbindungen.', note: 'Ein VLAN allein ist keine vollständige Sicherheitslösung.', example: 'Verwaltung, Unterricht und Gäste verwenden getrennte Bereiche. Regeln bestimmen, welche Dienste zwischen ihnen erreichbar sind.' },
            { name: 'NAT', short: 'Adressen übersetzen', flow: ['interne Adresse', 'Adressübersetzung', 'externe Adresse'], description: 'NAT übersetzt Netzwerkadressen. Das ist eine andere Aufgabe als das Prüfen und Beschränken von Verbindungen durch eine Firewall.', note: 'NAT ersetzt keine Firewall. Ob eine Verbindung erlaubt ist, muss durch passende Regeln festgelegt werden.' },
            {
                name: 'VPN', short: 'geschützter Tunnel', flow: ['Notebook unterwegs', 'verschlüsselter Tunnel', 'VPN-Gegenstelle'],
                description: 'VPN bedeutet „Virtual Private Network“. Zwei Gegenstellen transportieren Daten geschützt über eine vorhandene Verbindung, etwa das Internet. Beim Fernzugriff verbindet eine VPN-App auf dem Notebook das Gerät mit einem VPN-Server oder einem geeigneten Router im Zielnetz.',
                note: 'Der Tunnel schützt den Weg zwischen den VPN-Gegenstellen. Ein VPN macht ein Endgerät nicht automatisch sicher und garantiert keine Anonymität im Internet. Zugriffsrechte gelten weiterhin; HTTPS bleibt auch mit VPN sinnvoll.',
                example: 'Eine Lehrperson ist zu Hause. Ihr Notebook verbindet sich mit dem VPN-Zugang der Schule. Danach kann sie einen dafür freigegebenen internen Dateiserver erreichen. Der Server prüft weiterhin ihre Berechtigungen.',
                guide: [
                    {
                        title: 'Wie funktioniert der Tunnel?',
                        steps: [
                            'Zuerst besteht eine normale Internetverbindung, zum Beispiel über WLAN oder Mobilfunk.',
                            'Das Gerät kontaktiert die eingerichtete VPN-Gegenstelle. Beide Seiten prüfen die vorgesehenen Identitätsnachweise, etwa kryptografische Schlüssel oder Zertifikate; je nach VPN-System erfolgt zusätzlich eine Benutzeranmeldung mit MFA.',
                            'Daten für den Tunnel werden verschlüsselt und in weitere Netzwerkpakete verpackt. Diese reisen über das Internet zur Gegenstelle.',
                            'Die Gegenstelle prüft und entschlüsselt die Pakete und leitet sie zum erlaubten Ziel weiter. Antworten nehmen den entsprechenden Rückweg.',
                        ],
                        paragraphs: ['Der „Tunnel“ ist eine bildliche Bezeichnung. Es entsteht kein eigenes Kabel, sondern eine geschützte Verbindung über das bestehende Netz.'],
                    },
                    {
                        title: 'Welche VPN-Arten gibt es?',
                        paragraphs: [
                            'Fernzugriff: Ein einzelnes Notebook oder Smartphone verbindet sich mit dem Schul-, Firmen- oder Heimnetz. Voraussetzung ist eine dafür eingerichtete Gegenstelle.',
                            'Standortverbindung: Zwei Router oder VPN-Gateways verbinden ganze Netzbereiche, beispielsweise zwei Firmenstandorte.',
                            'VPN-Anbieter: Das Gerät verbindet sich mit einem Server des Anbieters und gelangt von dort ins Internet. Dadurch entsteht nicht automatisch ein Zugang zum eigenen Heim- oder Schulnetz.',
                            'Split-Tunnel: Nur die vorgesehenen Zielnetze laufen durch das VPN. Full-Tunnel: Der dafür konfigurierte gesamte Internetverkehr wird durch die Gegenstelle geleitet. Welche Variante gilt, bestimmt die Einrichtung.',
                        ],
                    },
                    {
                        title: 'Wie wird ein VPN grundsätzlich eingerichtet?',
                        steps: [
                            'Ziel festlegen: Zugang zu einem internen Netz oder Internetverkehr über einen VPN-Anbieter? Die Gegenstelle muss zum Zweck passen.',
                            'Gegenstelle bereitstellen: Im Schul- oder Firmennetz richtet die zuständige Administration den Zugang und die erlaubten Dienste ein. Für ein Heimnetz kann ein VPN-fähiger Router diese Aufgabe übernehmen.',
                            'Passenden Client verwenden: Die App oder Betriebssystemfunktion muss das gewählte VPN-Verfahren unterstützen. Beispiele für Verfahren sind WireGuard und IPsec/IKEv2.',
                            'Ein persönliches Verbindungsprofil übernehmen: je nach System als Konfigurationsdatei, QR-Code oder vorgegebene Einstellungen mit Serveradresse und Anmeldeverfahren. Profile können geheime Schlüssel enthalten und werden geschützt behandelt.',
                            'Verbindung aktivieren und die erforderlichen Nachweise erbringen. Danach gezielt prüfen, ob der vorgesehene Dienst erreichbar ist und die erlaubten Zugriffe passen.',
                        ],
                        paragraphs: ['Eine VPN-App allein genügt nicht: Sie braucht eine passende Gegenstelle und deren Konfiguration. Bei Fernzugriff muss die Gegenstelle von außen erreichbar sein; Routing und Firewallregeln müssen den vorgesehenen Zugang ermöglichen.'],
                    },
                    {
                        title: 'Konkrete Beispiele für die Einrichtung',
                        paragraphs: [
                            'Windows 11 mit einem unterstützten VPN-Verfahren: Einstellungen → Netzwerk und Internet → VPN → VPN hinzufügen. Dort werden Anbieter, Serveradresse, VPN-Typ und Anmeldeverfahren nach den Vorgaben eingetragen. Anschließend speichern und verbinden. Für andere Verfahren wird die passende App verwendet.',
                            'WireGuard mit einem unterstützten Heimrouter: Im Router einen Zugang für das einzelne Gerät erstellen und dessen Konfigurationsdatei exportieren. Die Datei in der WireGuard-App importieren und den Tunnel aktivieren. Die genauen Menünamen und Voraussetzungen hängen vom Router ab.',
                            'Schul- oder Firmennetz: Die Administration liefert das zugelassene Programm und ein persönliches Profil beziehungsweise die nötigen Einstellungen. Nach der Einrichtung wird ein freigegebener interner Dienst getestet.',
                        ],
                    },
                ],
            },
        ],
    },
    {
        key: 'encryption', label: 'WLAN & TLS', kicker: 'Den Übertragungsweg schützen', title: 'Was leistet Verschlüsselung?',
        intro: 'WLAN-Schutz und TLS schützen verschiedene Verbindungen. Die Vertrauenswürdigkeit eines Inhalts bleibt eine eigene Frage.',
        caption: 'Funk reicht häufig über die eigenen Räume hinaus. Schutz hängt von Technik und Einrichtung ab.',
        items: [
            { name: 'WLAN-Verschlüsselung', short: 'Funkverbindung schützen', flow: ['WLAN-Gerät', 'geschützte Funkverbindung', 'Access Point'], description: 'Geeignete WLAN-Verschlüsselung schützt die Funkverbindung vor unbefugtem Mitlesen und Verändern. Wo unterstützt, ist WPA3 eine zeitgemäße Wahl. Bei älteren Geräten kann eine geeignet eingerichtete WPA2-Verbindung erforderlich sein.', note: 'Die Schutzwirkung hängt von der tatsächlichen Einrichtung und den beteiligten Geräten ab.' },
            {
                name: 'Gäste-WLAN', short: 'Trennung braucht Regeln', flow: ['Gäste-WLAN', 'Gastnetz und Firewall', 'Internet'],
                description: 'Gastgeräte werden einem eigenen Netzbereich zugeordnet. Eine Firewall kontrolliert die Verbindungen aus diesem Bereich: Der vorgesehene Internetzugang wird erlaubt, der Zugriff auf geschützte interne Geräte gesperrt. Beide Aufgaben zusammen bewirken die Trennung.',
                note: 'Ein anderer WLAN-Name allein beweist keine sichere Trennung. Auch ein eigenes Passwort regelt zunächst nur die Anmeldung am WLAN; es ersetzt keine Netztrennung und keine Zugriffsregeln.',
                example: 'Ein Gastgerät darf Webseiten im Internet abrufen. Versucht es, eine Verbindung zu einem Verwaltungscomputer herzustellen, sperrt die Firewall diesen Zugriff.',
                guide: [
                    {
                        title: 'Wie entsteht ein eigener Netzbereich?',
                        paragraphs: [
                            'Ein Access Point kann mehrere WLAN-Namen, sogenannte SSIDs, anbieten. In einem verwalteten Netz wird die Gäste-SSID zum Beispiel einem eigenen VLAN zugeordnet. So bleiben die lokalen Netzbereiche logisch getrennt, obwohl sie denselben Access Point oder dieselben Kabel verwenden.',
                            'Switches und Router müssen diese Zuordnung passend weiterführen. Häufig erhält das Gastnetz einen eigenen IP-Adressbereich. Ein VLAN allein entscheidet aber noch nicht, welche Verbindungen der Router zwischen den Bereichen erlaubt.',
                            'Ein Heimrouter kann eine fertige Gastzugangsfunktion bereitstellen, die den getrennten Bereich und seine Zugriffsregeln gemeinsam einrichtet. Die technische Umsetzung hängt vom Gerät ab; ein zweiter physischer Router ist dafür nicht grundsätzlich nötig.',
                        ],
                    },
                    {
                        title: 'Welche Regeln setzt die Firewall um?',
                        steps: [
                            'Grundversorgung erlauben: Gastgeräte brauchen die vorgesehenen Dienste zur Adressvergabe und Namensauflösung, beispielsweise DHCP und DNS. Erlaubt werden die dafür zuständigen Dienste.',
                            'Interne Ziele schützen: Verbindungen aus dem Gastnetz zu Verwaltungsgeräten, internen Dateiablagen und den Verwaltungsoberflächen der Netzwerkgeräte sperren. Benötigte Ausnahmen werden ausdrücklich und eng festgelegt.',
                            'Internetzugang erlauben: Nur die dafür vorgesehenen Verbindungen freigeben. Eine zustandsbehaftete Firewall lässt passende Antworten auf erlaubte Verbindungen zurück.',
                            'Alle verwendeten Wege berücksichtigen: Die Trennung muss über sämtliche Access Points und Verbindungen hinweg gelten, bei Nutzung auch für IPv6.',
                        ],
                        paragraphs: ['Die Reihenfolge und genaue Form der Regeln hängen vom System ab. Ein nachfolgendes pauschales Erlauben darf die Sperre interner Ziele nicht aushebeln.'],
                    },
                    {
                        title: 'Sind die Gäste auch untereinander getrennt?',
                        paragraphs: [
                            'Das ist eine zusätzliche Einstellung. Client-Isolation oder AP-Isolation kann verhindern, dass Gastgeräte direkt miteinander kommunizieren. Die Trennung vom internen Netz und die Trennung der Gäste untereinander sind zwei verschiedene Aufgaben.',
                            'Geräte im selben lokalen Netz können Daten direkt austauschen, ohne den Router zu passieren. Deshalb reicht eine Firewallregel am Router für ihre gegenseitige Isolation allein nicht aus. Welche Verbindungen eine Isolationsfunktion tatsächlich blockiert, hängt vom Hersteller und Aufbau ab.',
                        ],
                    },
                    {
                        title: 'Ein vereinfachtes Beispiel mit zwei Netzen',
                        paragraphs: [
                            'Verwaltung: VLAN 10 mit dem IPv4-Netz 192.168.10.0/24. Gäste: VLAN 20 mit 192.168.20.0/24. Diese Nummern sind nur ein Lehrbeispiel.',
                            'Ein Gastgerät mit 192.168.20.25 erreicht einen Verwaltungscomputer mit 192.168.10.15 nur über eine Verbindung zwischen den Netzen. Die Firewall sperrt diese Verbindung. Den erlaubten Weg ins Internet darf das Gastgerät weiterhin benutzen.',
                            'Die verschiedenen Adressen allein sind keine Sicherheitsregel. Entscheidend bleiben die VLAN-Zuordnung und die tatsächlich durchgesetzten Zugriffsregeln.',
                        ],
                    },
                    {
                        title: 'Wie wird die Trennung überprüft?',
                        paragraphs: [
                            'Eine berechtigte Person verbindet ein Testgerät mit dem Gäste-WLAN und prüft gezielt: Internet funktioniert; bekannte geschützte interne Dienste und Verwaltungsoberflächen sind nicht erreichbar. Falls vorgesehen, wird auch die Isolation zwischen zwei Gastgeräten geprüft.',
                            'Ein fehlgeschlagener Ping allein beweist die Trennung nicht, weil ICMP unabhängig von anderen Diensten gesperrt sein kann. Zur Prüfung gehören die eingerichteten Regeln und passende Verbindungstests.',
                        ],
                    },
                ],
            },
            { name: 'TLS und HTTPS', short: 'Programmverbindung schützen', flow: ['Browser', 'TLS und Zertifikatsprüfung', 'Webserver'], description: 'TLS schützt eine Verbindung zwischen beteiligten Programmen. Bei HTTPS prüft der Browser normalerweise das Zertifikat des angesprochenen Servers.', note: 'Verschlüsselung schützt den Übertragungsweg. Sie beweist nicht, dass der Webseiteninhalt vertrauenswürdig ist oder der Anbieter Daten verantwortungsvoll verarbeitet.' },
        ],
    },
    {
        key: 'attacks', label: 'Angriffe', kicker: 'Gefahren unterscheiden', title: 'Was wird angegriffen?',
        intro: 'Angriffe können technische Schwachstellen und menschliches Verhalten kombinieren. Auch Verschlüsselung verhindert Täuschung nicht immer.',
        caption: 'Eine gefälschte Nachricht kann Zugangsdaten erschleichen, die anschließend für unbefugte Zugriffe verwendet werden.',
        items: [
            { name: 'Malware und Trojaner', short: 'unerwünschte Aktionen', flow: ['scheinbar nützliches Programm', 'Schadsoftware', 'unerwünschte Aktion'], description: 'Malware ist Schadsoftware, die unerwünschte Aktionen ausführt. Ein Trojaner tarnt sich als nützliches oder harmloses Programm.', note: 'Eine vertraut wirkende Bezeichnung oder ein ansprechendes Symbol beweist keine Unbedenklichkeit.' },
            { name: 'Ransomware', short: 'sperren, verschlüsseln, erpressen', flow: ['Schadsoftware', 'Daten / Systeme sperren', 'Verfügbarkeit beeinträchtigt'], description: 'Ransomware sperrt oder verschlüsselt Daten beziehungsweise Systeme. Häufig werden zusätzlich Daten gestohlen und Lösegeldforderungen gestellt.', note: 'Geschützte Sicherungskopien und ein geeignetes Wiederherstellungsverfahren helfen bei der Wiederherstellung. Auch die Vertraulichkeit kann betroffen sein.' },
            { name: 'Phishing', short: 'gefälschte Nachrichten und Seiten', flow: ['gefälschte Nachricht', 'Täuschung', 'Daten oder schädliche Aktion'], description: 'Phishing versucht mit gefälschten Nachrichten oder Webseiten, Zugangsdaten oder andere Informationen zu erhalten oder schädliche Aktionen auszulösen.', note: 'Auch eine verschlüsselte Webseite kann täuschen. HTTPS allein beweist nicht, dass eine Anmeldeseite echt ist.' },
            { name: 'Social Engineering', short: 'Täuschung oder Druck', flow: ['Vorwand / Druck', 'Mensch', 'Angriff ermöglichen'], description: 'Beim Social Engineering werden Menschen durch Täuschung oder Druck zu Handlungen bewegt, die einen Angriff ermöglichen.', note: 'Phishing ist ein möglicher Weg. Täuschung kann auch über andere Kommunikationswege stattfinden.' },
            { name: 'DoS und DDoS', short: 'Verfügbarkeit beeinträchtigen', flow: ['Anfragen / Belastung', 'Dienst überlastet', 'Nutzung beeinträchtigt'], description: 'Denial of Service beeinträchtigt die Verfügbarkeit eines Dienstes durch Anfragen oder andere Belastungen. Bei Distributed Denial of Service kommen sie aus mehreren Quellen.', note: 'Ein Ausfall bedeutet nicht automatisch, dass Daten gelesen oder verändert wurden.' },
            { name: 'Man-in-the-Middle', short: 'Verbindung abfangen', flow: ['Sender', 'Angreifer dazwischen', 'Empfänger'], description: 'Ein Angreifer versucht, sich in eine Verbindung einzuschalten, um Daten mitzulesen oder zu verändern.', note: 'Geeigneter Verbindungsschutz und die Prüfung der Gegenstelle tragen zum Schutz des Übertragungswegs bei.' },
            { name: 'Packet Sniffing', short: 'Datenverkehr aufzeichnen', flow: ['Netzwerkverkehr', 'Aufzeichnung', 'Analyse'], description: 'Packet Sniffing zeichnet Datenverkehr auf oder analysiert ihn. Es kann der berechtigten Diagnose dienen oder zum unerlaubten Ausspähen missbraucht werden.', note: 'Die technische Möglichkeit, Daten aufzuzeichnen, begründet für sich allein keine Berechtigung dazu.' },
        ],
    },
    {
        key: 'operations', label: 'Schutz im Betrieb', kicker: 'Maßnahmen ergänzen einander', title: 'Sicherheit braucht laufende Pflege',
        intro: 'Konten, Geräte, Netzgrenzen und Wiederherstellung müssen im Betrieb zusammenpassen.',
        caption: 'Sicherheitsvorfälle werden nach den Regeln der Organisation an die zuständige Stelle gemeldet und bearbeitet.',
        items: [
            { name: 'Updates', short: 'bekannte Schwachstellen schließen', flow: ['bekannte Schwachstelle', 'Sicherheitsaktualisierung', 'angepasste Umgebung'], description: 'Sicherheitsaktualisierungen schließen bekannte Schwachstellen in Betriebssystemen, Programmen und Geräte-Firmware.', note: 'Die Einführung muss zur betriebenen Umgebung passen. Updates ergänzen weitere Schutzmaßnahmen.' },
            { name: 'Konten und Netzgrenzen', short: 'Zugriffe begrenzen', flow: ['individuelles Konto und MFA', 'Berechtigungen / Firewall', 'benötigte Dienste'], description: 'Individuelle Konten, passende Berechtigungen und MFA verringern Risiken durch gestohlene Zugangsdaten. Segmentierung und Firewallregeln begrenzen die Erreichbarkeit zwischen Bereichen.', note: 'Mehrere Maßnahmen wirken zusammen. Weder MFA noch Segmentierung ersetzen alle anderen Schutzfunktionen.' },
            { name: 'Backup und Wiederherstellung', short: 'getrennt geschützte Kopien', flow: ['geschützte Sicherung', 'Wiederherstellungsverfahren', 'Daten wieder nutzbar'], description: 'Getrennt geschützte Sicherungskopien und ein geeignetes Wiederherstellungsverfahren helfen nach Datenverlust oder Ransomware.', note: 'Eine Synchronisierung ist nicht automatisch ein Backup: Fehlerhafte Änderungen oder Löschungen können mitübertragen werden.' },
            { name: 'Dokumentation und Meldung', short: 'Zuständigkeiten kennen', flow: ['Vorfall erkennen', 'zuständige Stelle melden', 'geregelt bearbeiten'], description: 'Aktuelle Übersichten über Geräte, Zuständigkeiten und Verbindungen unterstützen den Betrieb und die Reaktion auf Vorfälle.', note: 'Melde und bearbeite Sicherheitsvorfälle nach den Regeln deiner Organisation. Dabei gelten auch die Vorgaben zum Umgang mit Daten.' },
        ],
    },
    {
        key: 'privacy', label: 'Datenschutz', kicker: 'Personenbezogene Daten schützen', title: 'Welche Daten gehören zu einer Person?',
        intro: 'Personenbezogene Daten beziehen sich auf eine identifizierte oder identifizierbare Person. Auch technische Angaben können dazugehören.',
        caption: 'Zweck, Rechtsgrundlage, benötigter Umfang, berechtigte Personen und Aufbewahrung müssen zur konkreten Verarbeitung passen.',
        items: [
            { name: 'Personenbezogene Daten', short: 'direkt oder indirekt zuordenbar', flow: ['IP-Adresse / Gerätekennung', 'Zuordnung möglich', 'Personenbezug'], description: 'Auch IP-Adressen, Gerätekennungen, Anmeldezeiten und Protokolle können personenbezogen sein, wenn sie einer Person direkt oder indirekt zugeordnet werden können.', note: 'Technische Daten sind nicht automatisch ohne Personenbezug.', example: 'Eine Anmeldezeit kann zusammen mit einem individuellen Benutzerkonto einer bestimmten Person zugeordnet werden.' },
            { name: 'Zweck und Transparenz', short: 'warum und auf welcher Grundlage?', flow: ['konkreter Zweck', 'Rechtsgrundlage', 'transparente Verarbeitung'], description: 'Die Datenschutz-Grundverordnung (DSGVO) nennt unter anderem Rechtmäßigkeit, Transparenz und Zweckbindung als Grundsätze. Für den Schulnetzbetrieb müssen Zweck und Rechtsgrundlage der konkreten Verarbeitung festgelegt werden.', note: 'Eine technische Möglichkeit zur Überwachung ist für sich allein keine Berechtigung dazu.' },
            { name: 'Umfang und Aufbewahrung', short: 'nur so viel und so lange wie nötig', flow: ['benötigte Daten', 'begrenzte Nutzung und Aufbewahrung', 'geregelter Zugriff'], description: 'Datenminimierung und Speicherbegrenzung gehören zu den Datenschutzgrundsätzen. Umfang, Einsicht und Aufbewahrung von Protokollen sind nicht beliebig.', note: 'Sie richten sich nach den geltenden Vorgaben und der konkreten Aufgabe. Auch Integrität und Vertraulichkeit sind zu berücksichtigen.' },
        ],
    },
]

const sources = [
    { title: 'Österreichische Datenschutzbehörde: Pflichten und Grundsätze', url: 'https://dsb.gv.at/rechte-pflichten/ihre-pflichten-als-verantwortlicher' },
    { title: 'Microsoft: Netzwerksicherheit und WLAN', url: 'https://learn.microsoft.com/en-us/windows/security/book/operating-system-security-network-security' },
    { title: 'CISA: Ransomware-Leitfaden', url: 'https://www.cisa.gov/stopransomware/ransomware-guide' },
    { title: 'CISA: Phishing-resistente MFA', url: 'https://www.cisa.gov/sites/default/files/2023-01/fact-sheet-implementing-phishing-resistant-mfa-508c.pdf' },
    { title: 'Cisco: 802.1X-Komponenten', url: 'https://www.cisco.com/c/en/us/td/docs/solutions/Enterprise/Security/TrustSec_1-99/Dot1X_Deployment/Dot1x_Dep_Guide.html' },
    { title: 'WireGuard: Funktionsweise des VPN-Tunnels', url: 'https://www.wireguard.com/' },
    { title: 'Microsoft: VPN unter Windows einrichten', url: 'https://support.microsoft.com/de-de/windows/experience/connectivity-networking/connect-to-a-vpn-in-windows' },
    { title: 'FRITZ!: Beispiel für WireGuard am Heimrouter', url: 'https://fritz.com/apps/knowledge-base/FRITZ-Box-7682/3685_WireGuard-VPN-zur-FRITZ-Box-am-Computer-einrichten' },
    { title: 'Cisco Meraki: VLAN-Zuordnung im WLAN', url: 'https://documentation.meraki.com/Wireless/Design_and_Configure/Configuration_Guides/Client_Addressing_and_Bridging/VLAN_Tagging' },
    { title: 'Cisco Meraki: Gastnetz und Firewallregeln', url: 'https://documentation.meraki.com/Platform_Management/Dashboard_Administration/Design_and_Configure/Architectures_and_Best_Practices/Meraki_Reference_Architecture_-_Small_Office_Business' },
    { title: 'Cisco Meraki: Isolation von WLAN-Geräten', url: 'https://documentation.meraki.com/Wireless/Operate_and_Maintain/How_Tos/Firewall_and_Traffic_Shaping/Wireless_Client_Isolation' },
    { title: 'FRITZ!: Getrennter WLAN-Gastzugang', url: 'https://fritz.com/apps/knowledge-base/FRITZ-Box-7590/294_WLAN-Gastzugang-in-FRITZ-Box-einrichten/' },
]

const route = useRoute()
const router = useRouter()
const pageIndex = ref(0)
const selection = ref(pages[0].items[0].name)
const currentPage = computed(() => pages[pageIndex.value])
const selected = computed(() => currentPage.value.items.find(item => item.name === selection.value) || currentPage.value.items[0])

watch(() => [route?.query?.chapter, route?.query?.selection], ([chapter, selectedName]) => {
    const index = pages.findIndex(page => page.key === chapter)
    pageIndex.value = index >= 0 ? index : 0
    const { items } = currentPage.value
    selection.value = items.find(item => item.name === selectedName)?.name || items[0].name
}, { immediate: true })

function syncLocation(push) {
    if (!router || !route) {
        return
    }
    const query = { ...route.query, chapter: currentPage.value.key,
        selection: selection.value === currentPage.value.items[0].name ? undefined : selection.value }
    router[push ? 'push' : 'replace']({ query, hash: route.hash })
}

function selectItem(name) {
    selection.value = name
    syncLocation(false)
}

function openPage(index) {
    if (index < 0 || index >= pages.length) {
        return
    }
    pageIndex.value = index
    selection.value = currentPage.value.items[0].name
    syncLocation(true)
}
</script>

<style scoped>
.security-lesson { max-width: 1200px; margin: 0 auto; color: #1b3049; font-size: 1rem; line-height: 1.45; }
.lesson-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 10px; }
.lesson-header h1 { margin: 0; color: #193c68; font-size: clamp(1.35rem, 2.2vw, 1.8rem); line-height: 1.15; }
.eyebrow { margin: 0 0 2px; color: #52729a; font-size: .75rem; font-weight: 700; }.page-number { color: #64778b; font-size: .85rem; white-space: nowrap; }
button { font: inherit; cursor: pointer; } button:focus-visible, a:focus-visible, summary:focus-visible { outline: 3px solid #2c6fb6; outline-offset: 2px; }
button[aria-pressed='true'] { border-color: #3272b1; background: #e5f1fc; color: #174a7d; box-shadow: inset 0 0 0 1px #3272b1; }
.lesson-navigation { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 5px; margin-bottom: 10px; }
.lesson-navigation button { min-width: 0; min-height: 38px; padding: 5px; border: 1px solid #cbd9e8; border-radius: 9px; background: #fff; color: #32577f; font-size: .76rem; font-weight: 650; }
.lesson-navigation button[aria-pressed='true'], .choice-grid button[aria-pressed='true'] { border-color: #3272b1; background: #e5f1fc; color: #174a7d; }
.lesson-content { padding: 20px 24px; border: 1px solid #dce5ef; border-radius: 16px; background: #fff; }
.section-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 13px; }
.section-heading span, .detail-panel > span { color: #3973ad; font-size: .75rem; font-weight: 700; }
.section-heading h2 { margin: 1px 0 0; color: #193c68; font-size: clamp(1.25rem, 2vw, 1.65rem); line-height: 1.15; }
.section-heading > p { max-width: 45ch; margin: 0; color: #5a7189; font-size: .87rem; }
.two-columns { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); gap: 16px; align-items: stretch; }
.visual-panel, .detail-panel { min-width: 0; padding: 16px; border-radius: 12px; }.visual-panel { background: #f0f6fc; border: 1px solid #dbe8f4; }.detail-panel { background: #f8fafc; border: 1px solid #e1e8ef; }
.detail-panel h3 { margin: 3px 0 8px; color: #1f4d7d; font-size: 1.3rem; }.detail-panel p { margin: 0; font-size: .95rem; overflow-wrap: anywhere; }.detail-panel .note { margin-top: 15px; color: #516980; font-size: .86rem; }
.example { margin-top: 14px; padding: 10px 12px; border-left: 3px solid #3272b1; background: #eaf2fa; }.example strong { display: block; margin-bottom: 4px; color: #1f4d7d; font-size: .78rem; }.detail-panel .example p { font-size: .86rem; }
.example ul { margin: 0; padding-left: 20px; font-size: .86rem; }.example li + li { margin-top: 10px; }
.guide-section { margin-top: 16px; padding-top: 12px; border-top: 1px solid #dce5ef; }.guide-section summary { color: #1f4d7d; font-size: .9rem; font-weight: 700; cursor: pointer; }.detail-panel .guide-section p { margin-top: 10px; font-size: .86rem; }.guide-section ol { margin: 12px 0 0; padding-left: 22px; font-size: .86rem; }.guide-section li + li { margin-top: 10px; }
.choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }.choice-grid button { display: flex; flex-direction: column; align-items: start; justify-content: center; min-height: 58px; padding: 8px 10px; border: 1px solid #c5d7e8; border-radius: 9px; background: #fff; color: #28527b; text-align: left; overflow-wrap: anywhere; }.choice-grid button strong { font-size: .9rem; }.choice-grid button small { margin-top: 2px; color: #526b83; font-size: .73rem; }
.security-flow { display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 75px; margin-bottom: 12px; }.security-flow span { display: grid; place-items: center; flex: 1; min-width: 0; min-height: 60px; padding: 7px; border: 1px solid #bfd3e6; border-radius: 9px; background: #fff; color: #28527b; text-align: center; overflow-wrap: anywhere; font-size: .79rem; font-weight: 700; }.security-flow b { color: #3272b1; }
.caption { margin: 12px 0 0; color: #577089; font-size: .8rem; line-height: 1.35; }
.lesson-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0 0; }.lesson-footer button { min-height: 37px; padding: 6px 16px; border: 1px solid #b8cde1; border-radius: 8px; background: #fff; color: #265989; font-weight: 700; }.lesson-footer .next-button { border-color: #2868a8; background: #2868a8; color: #fff; }.lesson-footer button:disabled { opacity: .45; cursor: default; }.lesson-footer span { color: #667b91; font-size: .8rem; text-align: center; }
.lesson-sources { margin-top: 18px; color: #516980; font-size: .8rem; }.lesson-sources summary { cursor: pointer; }.lesson-sources a { display: inline-block; margin: 4px 16px 4px 0; color: #265989; text-decoration: underline; }
@media (max-width: 850px) { .lesson-navigation { grid-template-columns: repeat(4, minmax(0, 1fr)); }.section-heading { align-items: start; flex-direction: column; gap: 4px; }.two-columns { grid-template-columns: 1fr; } }
@media (max-width: 520px) { .lesson-content { padding: 17px 14px; }.lesson-navigation button { font-size: .7rem; }.security-flow { flex-direction: column; }.security-flow span { width: 100%; min-height: 36px; }.security-flow b { transform: rotate(90deg); }.choice-grid { grid-template-columns: 1fr; }.lesson-footer button { padding: 6px 10px; } }
</style>
