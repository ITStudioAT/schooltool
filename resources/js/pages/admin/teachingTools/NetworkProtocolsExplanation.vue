<template>
    <article class="protocols-lesson" aria-labelledby="protocols-title">
        <header class="lesson-header">
            <div><p class="eyebrow">Netzwerke verstehen</p><h1 id="protocols-title">Netzwerkprotokolle und Dienste</h1></div>
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
                    <div class="service-flow" role="img" :aria-label="selected.flow.join(' → ')">
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
                    <dl v-if="selected.ports" class="port-details"><dt>Übliche Standardports</dt><dd>{{ selected.ports }}</dd></dl>
                    <p class="note">{{ selected.note }}</p>
                    <p v-if="selected.example" class="example">{{ selected.example }}</p>
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
            <p>Nach dem Lernmodul „Netzwerkprotokolle und Dienste“ von Dipl.-Ing. Günther Kron (26.10.2026).</p>
            <a v-for="source in sources" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer">{{ source.title }}</a>
        </details>
    </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const pages = [
    {
        key: 'services', label: 'Zusammenspiel', kicker: 'Funktion und Regeln', title: 'Wer bietet was an?',
        intro: 'Ein Dienst stellt eine Funktion bereit. Ein Protokoll bestimmt, wie Programme dafür Nachrichten austauschen.',
        caption: 'Ein Rechner kann mehrere Dienste anbieten und je nach Situation Client und Server sein.',
        items: [
            { name: 'Client und Server', short: 'anfordern und anbieten', flow: ['Browser · Client', 'Anfrage / Antwort', 'Webserver'], description: 'Der Client fordert eine Funktion an; der Server bietet sie an. Beim Aufrufen einer Webseite fragt dein Browser den Webserver nach Inhalten.', note: '„Server“ bezeichnet sowohl das anbietende Programm als auch den Rechner, auf dem es läuft.' },
            { name: 'Dienst und Protokoll', short: 'Aufgabe und gemeinsame Regeln', flow: ['Webdienst', 'HTTP / HTTPS', 'Webinhalte'], description: 'Das Abrufen einer Webseite ist eine Funktion des Webdienstes. HTTP legt fest, wie Anfrage und Antwort aufgebaut sind. HTTPS schützt die Kommunikation.', note: 'Gemeinsame Regeln ermöglichen, dass Programme unterschiedlicher Hersteller miteinander kommunizieren.' },
            { name: 'Protokolle im Team', short: 'Anwendung, Transport, IP', flow: ['HTTP', 'TCP', 'IP'], description: 'Die Anwendung verwendet ein Anwendungsprotokoll. Darunter ermöglicht das Transportprotokoll die Kommunikation zwischen Programmen. IP übernimmt Adressierung und Weiterleitung zwischen Netzwerken.', note: 'Das Beispiel zeigt HTTP über TCP. HTTP/3 verwendet QUIC über UDP.' },
        ],
    },
    {
        key: 'transport', label: 'IP & Ports', kicker: 'Das richtige Programm erreichen', title: 'Adresse, Transport und Port',
        intro: 'Die IP-Adresse bezeichnet eine Netzwerkschnittstelle. Ein Port hilft, die Daten dem passenden Programm zuzuordnen.',
        caption: 'TCP- und UDP-Ports sind getrennt zu betrachten. Ein Port allein beweist weder Sicherheit noch Erreichbarkeit.',
        items: [
            { name: 'IP und Ports', short: 'ein Gerät, mehrere Dienste', flow: ['192.0.2.10', 'TCP 443', 'Webdienst'], description: 'IP transportiert Pakete zwischen Netzwerken und verwendet IPv4 oder IPv6. Auf derselben IP-Adresse können verschiedene Dienste über unterschiedliche Ports erreichbar sein.', note: 'Beispiel: TCP 443 führt üblicherweise zum HTTPS-Webdienst, TCP 22 zum SSH-Dienst. Die Konfiguration kann abweichen.' },
            { name: 'TCP', short: 'geordneter Datenstrom', flow: ['Verbindung', 'Daten & Bestätigungen', 'Empfänger'], description: 'TCP baut eine Verbindung auf und stellt während einer funktionsfähigen Verbindung einen geordneten, zuverlässigen Datenstrom bereit. Fehlende Daten werden bei Bedarf erneut übertragen.', note: 'TCP allein verschlüsselt nichts und bestätigt nicht, dass die Anwendung die Daten bereits verarbeitet hat.' },
            { name: 'UDP', short: 'einzelne Datagramme', flow: ['Sender', 'Datagramm', 'Empfänger'], description: 'UDP verschickt Datagramme ohne eigenen Verbindungsaufbau. Zustellung und Reihenfolge sind durch UDP nicht garantiert.', note: 'Weniger eigene Mechanismen bedeuten nicht, dass jede UDP-Anwendung automatisch schneller oder unzuverlässig ist. Anwendungen können weitere Funktionen ergänzen.' },
            { name: 'ICMP', short: 'Kontrolle und Diagnose', flow: ['IP-Gerät', 'Diagnosemeldung', 'IP-Gerät'], description: 'ICMP tauscht Kontroll- und Diagnosemeldungen für IP aus. Es verwendet keine TCP- oder UDP-Portnummer.', note: 'Ports gehören hier nicht zum Zuordnungsprinzip: ICMP ist weder TCP noch UDP.' },
        ],
    },
    {
        key: 'configuration', label: 'DNS & DHCP', kicker: 'Namen und Einstellungen', title: 'Zwei Dienste, zwei Aufgaben',
        intro: 'DNS beantwortet Namens- und andere Datenanfragen. DHCP stellt Netzwerkeinstellungen bereit.',
        caption: 'DNS und DHCP können auf demselben Gerät laufen. Ihre Aufgaben bleiben verschieden.',
        items: [
            { name: 'DNS', short: 'Informationen zu Namen', flow: ['Domainname', 'DNS-Resolver', 'DNS-Antwort'], ports: 'UDP 53 oder TCP 53', description: 'Das Domain Name System liefert passende Informationen zu einer Anfrage. Dein Gerät fragt normalerweise einen Resolver, der Antworten zwischenspeichern oder weitere DNS-Server befragen kann.', note: 'Ein A-Eintrag enthält eine IPv4-Adresse, ein AAAA-Eintrag eine IPv6-Adresse. DNS ist mehr als eine einfache Liste von Namen und Adressen.', example: 'Verschlüsselte DNS-Verfahren verwenden zusätzliche Protokolle und gegebenenfalls andere Ports.' },
            { name: 'DHCP für IPv4', short: 'Konfiguration automatisch erhalten', flow: ['Client', 'DHCP-Server', 'Netzwerkeinstellungen'], ports: 'UDP 67 · Server / UDP 68 · Client', description: 'Dynamic Host Configuration Protocol kann IP-Adresse, Subnetzmaske, Standardgateway und DNS-Server automatisch bereitstellen. Die Adresse wird für eine begrenzte Gültigkeitsdauer vergeben.', note: 'Eine Reservierung ordnet einem bestimmten Client über DHCP eine feste Adresse zu. Bei IPv6 sind unter anderem automatische Adresskonfiguration und DHCPv6 möglich.' },
            { name: 'Im Schulnetz', short: 'erst Einstellungen, dann Namen', flow: ['DHCP-Konfiguration', 'DNS-Anfrage', 'Webdienst'], description: 'Ein neu verbundenes Gerät kann zunächst seine Einstellungen über DHCP erhalten. Beim späteren Webseitenaufruf fragt es DNS nach Informationen zum Domainnamen und verbindet sich dann mit dem Webdienst.', note: 'DHCP übersetzt keine Domainnamen. DNS verteilt keine vollständige Netzwerkkonfiguration.' },
        ],
    },
    {
        key: 'web', label: 'Web & TLS', kicker: 'Webinhalte geschützt übertragen', title: 'Was schützt HTTPS?',
        intro: 'Ein Browser fordert Webinhalte an. Der Schutz der Verbindung und das Vertrauen in den Inhalt sind unterschiedliche Fragen.',
        caption: 'Die tatsächlich verwendete HTTP-Version hängt unter anderem von Client, Server und Netz ab.',
        items: [
            { name: 'HTTP', short: 'Anfrage und Antwort', flow: ['Browser', 'HTTP', 'Webserver'], ports: 'häufig TCP 80', description: 'Mit HTTP fordert der Browser Informationen vom Webserver an und erhält eine Antwort. HTTP ohne TLS verschlüsselt die Verbindung nicht.', note: 'Die Portnummer ist ein üblicher Standardwert. Dienste können anders konfiguriert sein.' },
            { name: 'HTTPS und TLS', short: 'Verbindung und Identität schützen', flow: ['Browser', 'geschützte Verbindung', 'Webserver'], ports: 'häufig TCP 443', description: 'TLS schützt Daten auf der Verbindung vor Einsicht und unbemerkter Veränderung durch Dritte auf dem Übertragungsweg. Ein Zertifikat hilft, die Identität des Servers zu prüfen.', note: 'HTTPS beweist nicht, dass die Webseite vertrauenswürdig ist oder wie der Betreiber die erhaltenen Daten verwendet. HTTP/1.1 und HTTP/2 verwenden bei HTTPS häufig TLS über TCP.' },
            { name: 'HTTP/3 und QUIC', short: 'zuverlässige Datenströme über UDP', flow: ['HTTP/3', 'QUIC mit TLS', 'UDP'], ports: 'üblicherweise UDP 443', description: 'HTTP/3 nutzt QUIC als Transport über UDP. QUIC ergänzt eigene Mechanismen für zuverlässige Datenströme und den Schutz der Kommunikation.', note: 'TLS ist in den QUIC-Verbindungsaufbau eingebunden. Die Zuverlässigkeit entsteht durch QUIC; UDP selbst garantiert sie nicht.' },
        ],
    },
    {
        key: 'email', label: 'E-Mail', kicker: 'Senden und verwalten', title: 'Wie arbeiten Mailprogramme zusammen?',
        intro: 'Für die Einlieferung, Weiterleitung und Verwaltung von E-Mails sind verschiedene Protokolle zuständig.',
        caption: 'TLS auf einer Übertragungsstrecke ist nicht dasselbe wie Ende-zu-Ende-Verschlüsselung des Nachrichteninhalts.',
        items: [
            { name: 'SMTP', short: 'senden und weiterleiten', flow: ['Mailprogramm', 'Mailserver', 'Mailserver'], ports: 'häufig TCP 587 · Einlieferung / TCP 25 · Weiterleitung', description: 'Simple Mail Transfer Protocol übernimmt die Einlieferung und die Weiterleitung von Nachrichten zwischen Mailservern.', note: 'Je nach Konfiguration kann TLS die jeweilige Übertragung schützen. Die Portnummer allein belegt keinen Schutz.' },
            { name: 'IMAP', short: 'Postfach abrufen und verwalten', flow: ['Mailprogramm', 'IMAP', 'Postfach auf dem Server'], ports: 'häufig TCP 143 / TCP 993 · direktes TLS', description: 'Internet Message Access Protocol erlaubt dem Mailprogramm, Nachrichten und Ordner auf dem Mailserver abzurufen und zu verwalten.', note: 'SMTP sendet und leitet weiter; IMAP verwaltet das Postfach. Mehrere Geräte können so mit demselben Serverpostfach arbeiten.' },
        ],
    },
    {
        key: 'files', label: 'Dateien & SSH', kicker: 'Übertragung und Freigaben', title: 'Welcher Weg führt zur Datei?',
        intro: 'Dateien übertragen, Ressourcen freigeben und einen Rechner fernsteuern sind unterschiedliche Aufgaben.',
        caption: 'Eine erreichbare Freigabe gibt nicht automatisch Zugang zu jeder Datei. Dafür gelten Benutzerrechte.',
        items: [
            { name: 'FTP', short: 'Steuer- und Datenverbindungen', flow: ['Client', 'FTP', 'Dateiserver'], description: 'FTP überträgt Dateien über getrennte Steuer- und Datenverbindungen. Einfaches FTP verschlüsselt Zugangsdaten und Inhalte nicht.', note: 'Ein erfolgreicher Dateitransfer sagt nichts über die Vertraulichkeit der Verbindung aus.' },
            { name: 'FTPS', short: 'FTP mit TLS', flow: ['FTP-Client', 'TLS', 'FTP-Server'], description: 'FTPS erweitert FTP um TLS. Eine sichere Einrichtung schützt sowohl die Anmeldung als auch die Datenverbindungen.', note: 'Der Schutz hängt von der konkreten Konfiguration ab. FTPS ist ein anderes Verfahren als SFTP.' },
            { name: 'SFTP', short: 'Dateiübertragung über SSH', flow: ['SFTP-Client', 'SSH', 'Server'], ports: 'üblicherweise TCP 22 · SSH', description: 'SFTP überträgt Dateien über SSH. Es ist ein eigenständiges Verfahren und keine Variante von FTP über TLS.', note: 'Der Server muss verlässlich identifiziert werden. Eine verschlüsselte Verbindung mit dem falschen Server hilft nicht.' },
            { name: 'SSH', short: 'gesicherter Fernzugriff', flow: ['SSH-Client', 'verschlüsselte Verbindung', 'entfernter Rechner'], ports: 'üblicherweise TCP 22', description: 'Secure Shell ermöglicht verschlüsselten Fernzugriff und weitere Dienste. SSH kann auch die Grundlage für SFTP sein.', note: 'Prüfe die Identität des Servers verlässlich. Verschlüsselung ersetzt diese Prüfung nicht.' },
            { name: 'SMB', short: 'Datei- und Druckerfreigaben', flow: ['Computer', 'SMB', 'Freigabe'], ports: 'häufig TCP 445', description: 'SMB stellt Datei- und Druckerfreigaben bereit, besonders in Windows-Netzen. Eine Freigabe macht eine Ressource erreichbar.', note: 'Benutzerrechte legen fest, wer lesen, ändern oder verwalten darf. Schutz und Zugriff hängen von Rechten und Konfiguration ab.' },
        ],
    },
    {
        key: 'infrastructure', label: 'Server & Cloud', kicker: 'Dienste bereitstellen', title: 'Wo läuft der Dienst?',
        intro: 'Lokale Server und externe Anbieter können gemeinsam die Dienste eines Netzwerks bereitstellen.',
        caption: 'In hybriden Netzwerken müssen Verbindung, Identitäten, Rechte und Datensicherung zusammenpassen.',
        items: [
            { name: 'Dateiserver und NAS', short: 'zentral gespeicherte Dateien', flow: ['Geräte', 'Netzwerk', 'Dateiserver / NAS'], description: 'Ein Dateiserver stellt zentral gespeicherte Dateien im Netzwerk bereit. Ein NAS ist ein auf Netzwerkspeicher spezialisiertes System.', note: 'Die zentrale Ablage erleichtert gemeinsamen Zugriff. Welche Dateien jemand nutzen darf, wird über Rechte geregelt.' },
            { name: 'Druckserver', short: 'Freigaben und Druckaufträge', flow: ['Computer', 'Druckserver', 'Netzwerkdrucker'], description: 'Ein Druckserver verwaltet Druckerfreigaben und kann Druckaufträge an Netzwerkdrucker weiterleiten.', note: 'Ein Rechner kann neben dem Druckdienst weitere Dienste anbieten.' },
            { name: 'Web- und Datenbankserver', short: 'Inhalte und strukturierte Daten', flow: ['Browser', 'Webanwendung', 'Datenbankserver'], description: 'Ein Webserver beantwortet HTTP- oder HTTPS-Anfragen und liefert Webinhalte oder Anwendungen. Ein Datenbankserver verwaltet strukturierte Daten für berechtigte Anwendungen.', note: 'Im Beispiel fragt die Webanwendung die Datenbank ab. Der Browser erhält die Antwort der Webanwendung.' },
            { name: 'Lokal, Cloud und hybrid', short: 'eigener Standort und Anbieter', flow: ['lokale Dienste', 'Netzverbindung', 'Cloud-Dienste'], description: 'Ein lokaler Server steht im eigenen Netzwerk oder an einem selbst betriebenen Standort. Ein Cloud-Dienst stellt Anwendungen oder Speicher auf Infrastruktur eines Anbieters bereit. Ein hybrides Netzwerk kombiniert beides.', note: 'Verfügbarkeit und Zuständigkeiten hängen vom gewählten Dienst ab.' },
            { name: 'Synchronisierung und Backup', short: 'Abgleichen ist nicht Sichern', flow: ['Dateiänderung', 'Synchronisierung', 'weitere Geräte'], description: 'Synchronisierung gleicht Dateien zwischen Geräten ab. Dabei können auch fehlerhafte Änderungen und Löschungen weitergegeben werden.', note: 'Eine Synchronisierung ist nicht automatisch eine Datensicherung. Zur Wiederherstellung braucht es geeignete, getrennt geschützte Sicherungskopien und ein Wiederherstellungsverfahren.' },
        ],
    },
    {
        key: 'access', label: 'Konten & Rechte', kicker: 'Identität und erlaubte Aktionen', title: 'Wer darf welche Datei öffnen?',
        intro: 'Ein Benutzerkonto steht für eine digitale Identität. Anmeldung und Zugriffsentscheidung erfüllen unterschiedliche Aufgaben.',
        caption: 'Das Prinzip der erforderlichen Rechte: Jedes Konto erhält nur die Zugriffe, die es für seine Aufgaben braucht.',
        items: [
            { name: 'Authentifizierung', short: 'Wer bist du?', flow: ['Benutzerkonto', 'Identität nachweisen', 'Anmeldung'], description: 'Bei der Authentifizierung weist eine Person oder ein Gerät die behauptete Identität nach.', note: 'Eine erfolgreiche Anmeldung erlaubt nicht automatisch jede Handlung und den Zugriff auf jede Datei.' },
            { name: 'Autorisierung', short: 'Was darfst du?', flow: ['angemeldetes Konto', 'Rechte prüfen', 'Zugriff erlauben / verweigern'], description: 'Die Autorisierung entscheidet, welche Ressourcen und Handlungen erlaubt sind. Lesen, Ändern und Verwalten können getrennt vergeben werden.', note: 'Beispiel: Du kannst dich anmelden und ein Aufgabenblatt lesen, darfst es aber nicht verändern.' },
            { name: 'Gruppen', short: 'Rechte gemeinsam verwalten', flow: ['Konten', 'Gruppe', 'Berechtigungen'], description: 'Gruppen bündeln Konten mit ähnlichen Aufgaben. Berechtigungen können der Gruppe zugewiesen werden, statt jedes Konto einzeln zu verwalten.', note: 'Eine Gruppe kann zum Beispiel Leserechte auf einen gemeinsamen Materialordner erhalten.' },
            { name: 'Verzeichnisdienst', short: 'Konten und Geräte zentral', flow: ['Schulcomputer', 'Verzeichnisdienst', 'Konten, Gruppen, Geräte'], description: 'Ein Verzeichnisdienst verwaltet Konten, Gruppen und Geräte zentral. Active Directory Domain Services ist ein Beispiel für eine Windows-Domäne.', note: 'Eine Schulanmeldung kann darüber erfolgen; die Einrichtung hängt von der Schule ab. Cloud-Identitäten und lokale Verzeichnisse können verbunden sein, sind aber nicht automatisch dasselbe System.' },
        ],
    },
]

const sources = [
    { title: 'IETF: TCP · RFC 9293', url: 'https://www.rfc-editor.org/rfc/rfc9293.html' },
    { title: 'IETF: UDP · RFC 768', url: 'https://www.rfc-editor.org/rfc/rfc768.html' },
    { title: 'IETF: HTTP/3 · RFC 9114', url: 'https://www.rfc-editor.org/rfc/rfc9114.html' },
    { title: 'IETF: DNS über TCP · RFC 9210', url: 'https://www.rfc-editor.org/rfc/rfc9210.html' },
    { title: 'IETF: DHCP · RFC 2131', url: 'https://www.rfc-editor.org/info/rfc2131/' },
    { title: 'IETF: TLS 1.3 · RFC 8446', url: 'https://www.rfc-editor.org/info/rfc8446/' },
    { title: 'IETF: FTP mit TLS · RFC 4217', url: 'https://www.rfc-editor.org/info/rfc4217/' },
    { title: 'Microsoft: Active Directory Domain Services', url: 'https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/active-directory-domain-services' },
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
.protocols-lesson { max-width: 1200px; margin: 0 auto; color: #1b3049; font-size: 1rem; line-height: 1.45; }
.lesson-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 10px; }
.lesson-header h1 { margin: 0; color: #193c68; font-size: clamp(1.35rem, 2.2vw, 1.8rem); line-height: 1.15; }
.eyebrow { margin: 0 0 2px; color: #52729a; font-size: .75rem; font-weight: 700; }.page-number { color: #64778b; font-size: .85rem; white-space: nowrap; }
button { font: inherit; cursor: pointer; } button:focus-visible, a:focus-visible, summary:focus-visible { outline: 3px solid #2c6fb6; outline-offset: 2px; }
button[aria-pressed='true'] { border-color: #3272b1; background: #e5f1fc; color: #174a7d; box-shadow: inset 0 0 0 1px #3272b1; }
.lesson-navigation { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 5px; margin-bottom: 10px; }
.lesson-navigation button { min-width: 0; min-height: 38px; padding: 5px; border: 1px solid #cbd9e8; border-radius: 9px; background: #fff; color: #32577f; font-size: .76rem; font-weight: 650; }
.lesson-navigation button[aria-pressed='true'], .choice-grid button[aria-pressed='true'] { border-color: #3272b1; background: #e5f1fc; color: #174a7d; }
.lesson-content { padding: 20px 24px; border: 1px solid #dce5ef; border-radius: 16px; background: #fff; }
.section-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 13px; }
.section-heading span, .detail-panel > span { color: #3973ad; font-size: .75rem; font-weight: 700; }
.section-heading h2 { margin: 1px 0 0; color: #193c68; font-size: clamp(1.25rem, 2vw, 1.65rem); line-height: 1.15; }
.section-heading > p { max-width: 45ch; margin: 0; color: #5a7189; font-size: .87rem; }
.two-columns { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); gap: 16px; align-items: stretch; }
.visual-panel, .detail-panel { min-width: 0; padding: 16px; border-radius: 12px; }.visual-panel { background: #f0f6fc; border: 1px solid #dbe8f4; }.detail-panel { background: #f8fafc; border: 1px solid #e1e8ef; }
.detail-panel h3 { margin: 3px 0 8px; color: #1f4d7d; font-size: 1.3rem; }.detail-panel p { margin: 0; font-size: .95rem; overflow-wrap: anywhere; }.detail-panel .note, .detail-panel .example { margin-top: 15px; color: #516980; font-size: .86rem; }
.port-details { margin: 14px 0 0; padding: 10px 12px; border-left: 3px solid #3272b1; background: #eaf2fa; }.port-details dt { color: #516980; font-size: .75rem; }.port-details dd { margin: 3px 0 0; color: #1f4d7d; font-size: .9rem; font-weight: 650; }
.choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }.choice-grid button { display: flex; flex-direction: column; align-items: start; justify-content: center; min-height: 58px; padding: 8px 10px; border: 1px solid #c5d7e8; border-radius: 9px; background: #fff; color: #28527b; text-align: left; overflow-wrap: anywhere; }.choice-grid button strong { font-size: .9rem; }.choice-grid button small { margin-top: 2px; color: #526b83; font-size: .73rem; }
.service-flow { display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 75px; margin-bottom: 12px; }.service-flow span { display: grid; place-items: center; flex: 1; min-width: 0; min-height: 60px; padding: 7px; border: 1px solid #bfd3e6; border-radius: 9px; background: #fff; color: #28527b; text-align: center; overflow-wrap: anywhere; font-size: .79rem; font-weight: 700; }.service-flow b { color: #3272b1; }
.caption { margin: 12px 0 0; color: #577089; font-size: .8rem; line-height: 1.35; }
.lesson-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0 0; }.lesson-footer button { min-height: 37px; padding: 6px 16px; border: 1px solid #b8cde1; border-radius: 8px; background: #fff; color: #265989; font-weight: 700; }.lesson-footer .next-button { border-color: #2868a8; background: #2868a8; color: #fff; }.lesson-footer button:disabled { opacity: .45; cursor: default; }.lesson-footer span { color: #667b91; font-size: .8rem; text-align: center; }
.lesson-sources { margin-top: 18px; color: #516980; font-size: .8rem; }.lesson-sources summary { cursor: pointer; }.lesson-sources a { display: inline-block; margin: 4px 16px 4px 0; color: #265989; text-decoration: underline; }
@media (max-width: 850px) { .lesson-navigation { grid-template-columns: repeat(4, minmax(0, 1fr)); }.section-heading { align-items: start; flex-direction: column; gap: 4px; }.two-columns { grid-template-columns: 1fr; } }
@media (max-width: 520px) { .lesson-content { padding: 17px 14px; }.lesson-navigation button { font-size: .7rem; }.service-flow { flex-direction: column; }.service-flow span { width: 100%; min-height: 36px; }.service-flow b { transform: rotate(90deg); }.choice-grid { grid-template-columns: 1fr; }.lesson-footer button { padding: 6px 10px; } }
</style>
