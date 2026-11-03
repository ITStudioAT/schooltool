<template>
    <article class="communication-lesson" aria-labelledby="communication-title">
        <header class="lesson-header">
            <div><p class="eyebrow">Netzwerke verstehen</p><h1 id="communication-title">Datenkommunikation und Internet</h1></div>
            <span class="page-number">{{ pageIndex + 1 }} / {{ pages.length }}</span>
        </header>
        <nav class="lesson-navigation" aria-label="Themen auswählen">
            <button v-for="(page, index) in pages" :key="page.key" type="button" :aria-pressed="pageIndex === index" @click="openPage(index)">{{ page.label }}</button>
        </nav>
        <main class="lesson-content">
            <div class="section-heading"><div><span>{{ currentPage.kicker }}</span><h2>{{ currentPage.title }}</h2></div><p>{{ currentPage.intro }}</p></div>
            <template v-if="currentPage.key === 'encapsulation'">
                <div class="two-columns">
                    <section class="visual-panel" :aria-label="currentPage.visualLabel">
                        <p class="caption envelope-caption">Schematische Einbettung – von außen nach innen:</p>
                        <div class="envelope" :class="'envelope--' + selectionIndex" aria-label="Schematische Einbettung der Daten von außen nach innen">
                            <span v-for="label in selected.layers" :key="label">{{ label }}</span>
                        </div>
                        <div class="choice-grid">
                            <button v-for="item in currentPage.items" :key="item.name" type="button" :aria-pressed="selection === item.name" @click="selectItem(item.name)">
                                <strong>{{ item.name }}</strong><small>{{ item.short }}</small>
                            </button>
                        </div>
                        <p class="caption">{{ currentPage.caption }}</p>
                    </section>
                    <section class="detail-panel" aria-live="polite" aria-atomic="true">
                        <span>{{ currentPage.detailLabel }}</span><h3>{{ selected.name }}</h3><p>{{ selected.description }}</p><p v-if="selected.note" class="note">{{ selected.note }}</p>
                    </section>
                </div>
                <details class="encapsulation-animation-details" :open="encapsulationAnimationOpen" @toggle="setEncapsulationAnimation">
                    <summary>Ablauf zur Kapselung anzeigen</summary>
                    <EncapsulationAnimation v-if="encapsulationAnimationOpen" />
                </details>
            </template>
            <div v-else class="two-columns">
                <section class="visual-panel" :aria-label="currentPage.visualLabel">
                    <p v-if="selected.flowMode === 'contents'" class="caption flow-caption">{{ selected.flowLabel }}</p>
                    <div v-if="selected.flow" class="flow" role="img" :aria-label="selected.flow.join(selected.flowMode === 'contents' ? ', ' : ' zu ')">
                        <template v-for="(part, index) in selected.flow" :key="index"><span>{{ part }}</span><b v-if="index < selected.flow.length - 1 && selected.flowMode !== 'contents'" aria-hidden="true">→</b></template>
                    </div>
                    <div class="choice-grid" :class="{ 'choice-grid--layers': currentPage.key === 'layers' }">
                        <button v-for="item in currentPage.items" :key="item.name" type="button" :aria-pressed="selection === item.name" @click="selectItem(item.name)">
                            <strong>{{ item.name }}</strong><small>{{ item.short }}</small>
                        </button>
                    </div>
                    <p v-if="currentPage.caption" class="caption">{{ currentPage.caption }}</p>
                </section>
                <section class="detail-panel" aria-live="polite" aria-atomic="true">
                    <span>{{ currentPage.detailLabel }}</span><h3>{{ selected.name }}</h3><p>{{ selected.description }}</p><p v-if="selected.note" class="note">{{ selected.note }}</p>
                    <dl v-if="selected.addressSteps" class="address-steps">
                        <template v-for="step in selected.addressSteps" :key="step.label"><dt>{{ step.label }}</dt><dd>{{ step.address }}</dd></template>
                    </dl>
                    <p v-if="selected.extra" class="extra">{{ selected.extra }}</p>
                </section>
            </div>
        </main>
        <footer class="lesson-footer"><button type="button" :disabled="pageIndex === 0" @click="openPage(pageIndex - 1)">← Zurück</button><span>{{ currentPage.label }} · {{ pageIndex + 1 }} von {{ pages.length }}</span><button type="button" class="next-button" :disabled="pageIndex === pages.length - 1" @click="openPage(pageIndex + 1)">Weiter →</button></footer>
    </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import EncapsulationAnimation from './EncapsulationAnimation.vue'

const pages = [
    {
        key: 'transfer', label: 'Übertragung', kicker: 'Daten unterwegs', title: 'Wie gelangen Daten ans Ziel?',
        intro: 'Größere Datenmengen werden für den Transport in kleinere Einheiten aufgeteilt.', visualLabel: 'Schritt der Datenübertragung auswählen', detailLabel: 'Einfach erklärt',
        caption: 'Der genaue Ablauf hängt von der Verbindung und den verwendeten Protokollen ab.',
        items: [
            { name: 'Client und Server', short: 'Anfrage und Antwort', flow: ['Browser', 'Anfrage', 'Webserver'], description: 'Der Browser fordert eine Webseite an. Der Webserver stellt sie bereit und sendet eine Antwort zurück.', note: 'Der Browser ist hier der Client: Er nutzt den Dienst. Der Server bietet ihn an.' },
            { name: 'Daten', short: 'Bits und Nutzdaten', flow: ['Text und Bilder', 'Bits', 'kleinere Einheiten'], description: 'Texte, Bilder und andere Inhalte werden digital als Bits dargestellt. Für die Übertragung werden größere Datenmengen aufgeteilt.', note: 'Zusätzliche Angaben helfen dabei, die Daten zuzuordnen und weiterzuleiten.' },
            { name: 'IP-Paket', short: 'Adressen und Nutzdaten', flowMode: 'contents', flowLabel: 'Bestandteile eines IP-Pakets:', flow: ['Quell-IP', 'Ziel-IP', 'Nutzdaten'], description: 'Ein IP-Paket enthält unter anderem die IP-Adresse des Absenders, die IP-Adresse des Ziels und Nutzdaten.', note: 'Router verwenden die Ziel-IP-Adresse, um das Paket zwischen Netzen weiterzuleiten.' },
            { name: 'Ethernet-Rahmen', short: 'für den nächsten Abschnitt', flowMode: 'contents', flowLabel: 'Ein Ethernet-Rahmen enthält unter anderem:', flow: ['Ziel-MAC', 'Quell-MAC', 'IP-Paket'], description: 'Das IP-Paket enthält Daten und die IP-Adresse des Ziels. Für den Weg durch das lokale Ethernet wird es in einen Ethernet-Rahmen verpackt.', note: 'Der Rahmen enthält die MAC-Adresse des Absenders und die des nächsten Empfängers – zum Beispiel des Routers. So gelangt das Paket zum nächsten Gerät.', extra: 'Ein Router entfernt den alten Rahmen und erstellt für den nächsten Abschnitt einen neuen. Die Ziel-IP-Adresse bleibt dabei normalerweise gleich.' },
        ],
    },
    {
        key: 'addresses', label: 'Adressen', kicker: 'Zwei Adressen', title: 'MAC-Adresse und IP-Adresse',
        intro: 'Beide Adressen haben unterschiedliche Aufgaben bei der Zustellung.', visualLabel: 'Adresse auswählen', detailLabel: 'Wofür wird sie gebraucht?',
        items: [
            { name: 'MAC-Adresse', short: 'auf der lokalen Verbindung', flow: ['Gerät', 'MAC-Adresse', 'nächstes Gerät'], description: 'Eine MAC-Adresse ist die Adresse eines Netzwerkadapters. Sie wird verwendet, um Daten innerhalb eines lokalen Netzwerkabschnitts an den richtigen Anschluss zuzustellen.', note: 'Für WLAN und Netzwerkkabel besitzt ein Gerät normalerweise unterschiedliche Netzwerkadapter und damit unterschiedliche MAC-Adressen.' },
            { name: 'IP-Adresse', short: 'innerhalb und zwischen Netzen', flowMode: 'contents', flowLabel: 'Adressen im IP-Paket:', flow: ['Quell-IP', 'Ziel-IP'], description: 'Die IP-Adresse ist die Netzwerkadresse eines Geräts beziehungsweise seines Netzwerkadapters. Sie steht im IP-Paket und hilft, das Ziel auch über mehrere Netze hinweg zu erreichen.', note: 'IPv4-Beispiel: 192.168.1.25 – vier Zahlen, durch Punkte getrennt. IPv6-Beispiel: 2001:db8::25 – eine verkürzte Schreibweise. Unter „IPv6-Schreibweise“ siehst du die vollständige Adresse.', extra: 'IPv4 (32 Bit): 0.0.0.0 bis 255.255.255.255. IPv6 (128 Bit): 0000:0000:0000:0000:0000:0000:0000:0000 bis ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff. Das sind die rechnerisch möglichen Adressen; manche sind reserviert und nicht für Geräte nutzbar.' },
            { name: 'IPv6-Schreibweise', short: 'vollständig und verkürzt', description: 'Eine IPv6-Adresse besteht ausgeschrieben aus acht Gruppen mit je vier Zeichen. Die Zeichen sind hexadezimal: 0–9 und a–f. Die Gruppen stehen zwischen Doppelpunkten.', addressSteps: [
                { label: 'Vollständige Adresse', address: '2001:0db8:0000:0000:0000:0000:0000:0025' },
                { label: '1. Führende Nullen weglassen; aus 0000 wird 0', address: '2001:db8:0:0:0:0:0:25' },
                { label: '2. Die fünf aufeinanderfolgenden Nullgruppen durch :: ersetzen', address: '2001:db8::25' },
            ], extra: 'Alle drei Schreibweisen bezeichnen dieselbe Adresse. Das Zeichen :: darf pro Adresse nur einmal verwendet werden.' },
            { name: 'Adresse finden', short: 'IP zu lokaler MAC', flowMode: 'contents', flowLabel: 'Zwei Wege zur MAC-Adresse:', flow: ['IPv4: ARP', 'IPv6: Nachbarerkennung'], description: 'Bei IPv4 fragt ein Gerät über ARP: „Wer hat diese IPv4-Adresse?“ Die Anfrage geht üblicherweise an alle Geräte im lokalen Netzwerkabschnitt – das heißt Broadcast. Das passende Gerät antwortet mit seiner MAC-Adresse.', note: 'Bei IPv6 heißt das Verfahren Nachbarerkennung. Die Anfrage geht an eine bestimmte Gruppe von Geräten statt an alle – das heißt Multicast. Das passende Gerät antwortet mit seiner MAC-Adresse.', extra: 'Die IPv6-Nachbarerkennung kann auch Router finden. Liegt das Ziel außerhalb des eigenen Netzwerks, wird die MAC-Adresse des Routers benötigt.' },
            { name: 'Adressen ändern', short: 'MAC und IP', flow: ['Gerät', 'Netzwerkadapter', 'Adresse'], description: 'Ein Netzwerkadapter besitzt normalerweise eine vom Hersteller vergebene und dauerhaft gespeicherte MAC-Adresse. Diese Hardwareadresse bleibt in der Regel gleich.', note: 'Das Betriebssystem kann jedoch eine andere MAC-Adresse verwenden. Bei WLAN wird zum Schutz der Privatsphäre oft für jedes Netzwerk eine eigene zufällige Adresse genutzt. Die gespeicherte Hardwareadresse bleibt dabei erhalten.', extra: 'Eine IP-Adresse wird für das jeweilige Netzwerk vergeben oder eingestellt und kann sich ändern. Aus einer Adresse allein lässt sich eine Person nicht zuverlässig bestimmen.' },
        ],
    },
    {
        key: 'routing', label: 'Weiterleitung', kicker: 'Im Netz und darüber hinaus', title: 'Switch und Router',
        intro: 'Das Ziel entscheidet, ob Daten lokal zugestellt oder an einen Router übergeben werden.', visualLabel: 'Weg der Daten auswählen', detailLabel: 'Was passiert?',
        items: [
            { name: 'Switch', short: 'im lokalen Ethernet', flow: ['Computer', 'Switch', 'Drucker'], description: 'Ein Switch verbindet Geräte in einem lokalen Ethernet-Netz. Er lernt, über welche Anschlüsse MAC-Adressen erreichbar sind.', note: 'Kennt er ein Ziel noch nicht, kann er den Rahmen innerhalb des betreffenden Netzes an mehrere Anschlüsse weitergeben.' },
            { name: 'Router', short: 'zwischen IP-Netzen', flow: ['Computer', 'Heimrouter', 'Internet', 'Webserver'], description: 'Ein Computer möchte eine Webseite öffnen. Der Webserver liegt außerhalb des eigenen Netzwerks. Deshalb sendet der Computer das IP-Paket zuerst an seinen Heimrouter.', note: 'Der Heimrouter ist hier das Standardgateway: der Ausgang aus dem eigenen Netzwerk. Das Ziel des Pakets bleibt der Webserver.', extra: 'Der Router liest die Ziel-IP-Adresse und sieht in seiner Routingtabelle nach. Sie ist wie ein Wegweiser: Sie zeigt, über welchen Anschluss oder nächsten Router er das Paket in Richtung Ziel weiterleitet.' },
            { name: 'Lokales Ziel', short: 'direkt im eigenen Netz', flow: ['Computer', 'Switch', 'lokaler Drucker'], description: 'Ist der Drucker im selben Netz wie der Computer, werden die Daten lokal zugestellt. Der Router ist für diesen Weg nicht nötig.', note: 'Der Computer verwendet für die lokale Zustellung die MAC-Adresse des Druckers.' },
            { name: 'Ziel außerhalb', short: 'zuerst zum Router', flow: ['Computer', 'Router', 'anderes Netz'], description: 'Liegt das Ziel außerhalb des eigenen Netzes, sendet der Computer das Paket zuerst an den Router – sein Standardgateway.', note: 'Für jeden Netzwerkabschnitt wird ein neuer Rahmen mit passenden MAC-Adressen verwendet. Die Ziel-IP-Adresse bleibt dabei normalerweise gleich.', extra: 'NAT bedeutet Netzwerkadressübersetzung (Network Address Translation). Ein Heimrouter ersetzt häufig die private Absender-IP im Paket durch seine öffentliche IPv4-Adresse. Er merkt sich die Verbindung und leitet die Antwort an das richtige Gerät zurück. So können mehrere Geräte eine öffentliche Adresse nutzen.' },
        ],
    },
    {
        key: 'layers', label: 'Schichten', kicker: 'Aufgaben ordnen', title: 'Das OSI-Modell',
        intro: 'Ein Schichtenmodell teilt die Aufgaben der Datenübertragung in Bereiche ein.', visualLabel: 'OSI-Schicht auswählen', detailLabel: 'Aufgabe der Schicht',
        caption: 'Das TCP/IP-Modell fasst die Aufgaben häufig in vier Bereiche: Anwendung, Transport, Internet und Netzzugang. Die Zuordnung ist vereinfacht.',
        items: [
            { name: '7 Anwendung', short: 'Dienste', description: 'Hier werden Netzdienste für Anwendungen beschrieben, zum Beispiel Webkommunikation und Namensauflösung.', note: 'Im TCP/IP-Modell fasst die Anwendungsschicht mehrere obere OSI-Aufgaben zusammen.' },
            { name: '6 Darstellung', short: 'Daten darstellen', description: 'Diese Schicht beschreibt, wie Daten dargestellt oder umgewandelt werden, zum Beispiel die Zeichencodierung.', note: 'Das Modell ist eine Erklärungshilfe; reale Protokolle passen nicht immer genau in eine Schicht.' },
            { name: '5 Sitzung', short: 'Sitzungen organisieren', description: 'Sie beschreibt die Organisation und Verwaltung von Kommunikationssitzungen.', note: 'Im TCP/IP-Modell werden diese Aufgaben nicht als eigene Schicht dargestellt.' },
            { name: '4 Transport', short: 'zwischen Anwendungen', description: 'Die Transportschicht ordnet Daten Anwendungen zu und übernimmt je nach Protokoll weitere Aufgaben.', note: 'TCP und UDP arbeiten in diesem Bereich und verwenden Ports.' },
            { name: '3 Vermittlung', short: 'zwischen Netzen', description: 'Die Vermittlungsschicht kümmert sich um logische Adressierung und Weiterleitung über Netzwerkgrenzen.', note: 'IP-Adressen stehen im IP-Paket; Router verwenden die Zieladresse für die Weiterleitung.' },
            { name: '2 Sicherung', short: 'lokaler Abschnitt', description: 'Die Sicherungsschicht beschreibt die Übertragung auf einem lokalen Netzwerkabschnitt.', note: 'Bei Ethernet gehören Rahmen und MAC-Adressen dazu.' },
            { name: '1 Bitübertragung', short: 'Signale übertragen', description: 'Die unterste Schicht überträgt Signale über ein Medium, zum Beispiel Kupferkabel, Glasfaser oder Funk.', note: 'Die Nummerierung des OSI-Modells beginnt unten bei dieser Schicht.' },
        ],
    },
    {
        key: 'encapsulation', label: 'Kapselung', kicker: 'Daten verpacken', title: 'Wie werden Daten eingebettet?',
        intro: 'Für den Transport kommen schrittweise zusätzliche Informationen zu den Daten hinzu.', visualLabel: 'Einbettung auswählen', detailLabel: 'Das wird ergänzt',
        caption: 'Am Ziel werden die Informationen wieder ausgewertet und die Nutzdaten weitergegeben.',
        items: [
            { name: 'Anwendungsdaten', short: 'die Anfrage', layers: ['Daten'], description: 'Die Anwendung erzeugt Daten, zum Beispiel eine Anfrage des Browsers an einen Webdienst.', note: 'Diese Daten werden für den weiteren Transport an die nächste Schicht übergeben.' },
            { name: 'Transport', short: 'Ports und Daten', layers: ['Transport', 'Daten'], description: 'TCP oder UDP ergänzt Transportinformationen, darunter Ports zur Zuordnung zu Anwendungen.', note: 'TCP verwendet Segmente, UDP verwendet Datagramme.' },
            { name: 'IP-Paket', short: 'IP-Adressen', layers: ['IP-Paket', 'Transport', 'Daten'], description: 'IP bettet die Transportdaten in ein IP-Paket ein. Es enthält unter anderem Quell- und Ziel-IP-Adresse.', note: 'Die IP-Adressen helfen bei der Weiterleitung zwischen Netzen.' },
            { name: 'Ethernet-Rahmen', short: 'lokale MAC-Adressen', layers: ['Ethernet-Rahmen', 'IP-Paket', 'Transport', 'Daten'], description: 'Auf einer Ethernet-Verbindung wird das IP-Paket in einen Rahmen mit lokalen MAC-Adressen eingebettet.', note: 'Der Rahmen gilt für diesen Übertragungsabschnitt. Bei WLAN ist der konkrete Rahmenaufbau anders.' },
        ],
    },
    {
        key: 'transport', label: 'TCP & UDP', kicker: 'Anwendungen erreichen', title: 'TCP, UDP und Ports',
        intro: 'TCP steht für Transmission Control Protocol, UDP für User Datagram Protocol. Beide helfen beim Datenaustausch zwischen Anwendungen.', visualLabel: 'Begriff auswählen', detailLabel: 'Das bedeutet es',
        items: [
            { name: 'Port', short: 'Anwendung zuordnen', flow: ['IP-Adresse', 'Gerät', 'Port → Anwendung'], description: 'Die IP-Adresse führt zur Netzwerkschnittstelle. Der Port hilft dabei, die Daten dort dem angesprochenen Dienst oder der beteiligten Anwendung zuzuordnen.', note: 'Ein Server hat zum Beispiel die IP-Adresse 192.168.1.10. Ein HTTPS-Webserver kann auf diesem Server über Port 443 erreichbar sein.', extra: '443 ist der übliche Port für HTTPS. Ein Webserver kann aber auch einen anderen Port verwenden.' },
            { name: 'TCP', short: 'geordnet und zuverlässig', flow: ['Sender', 'TCP', 'Empfänger'], description: 'TCP überträgt einen geordneten Datenstrom. Fehlende Daten können erkannt und erneut übertragen werden.', note: 'TCP ist verbindungsorientiert. HTTP/1.1 und HTTP/2 werden bei HTTPS häufig über TCP genutzt.' },
            { name: 'UDP', short: 'einzelne Datenstücke', flow: ['Sender', 'einzelne Datenstücke', 'Empfänger'], description: 'UDP verschickt Datenstücke einzeln, ohne vorher eine Verbindung aufzubauen. Dabei können Datenstücke verloren gehen oder in einer anderen Reihenfolge ankommen.', note: 'UDP fordert fehlende Daten nicht selbst erneut an und sortiert sie nicht. Ein Programm kann diese Aufgaben übernehmen, wenn es sie benötigt.', extra: 'Bei HTTP/3 sorgt zum Beispiel QUIC dafür, dass fehlende Daten erneut gesendet werden – obwohl UDP für den Transport verwendet wird.' },
            { name: 'UDP-Einsatzbereiche', short: 'wo UDP verwendet wird', description: 'Bei Live-Audio und Video sowie in Online-Spielen kann UDP verwendet werden. Dort sind aktuelle Daten oft wichtiger als verspätete ältere Daten.', note: 'Auch DNS, die Adressauskunft für Webseiten, sendet Anfragen häufig über UDP. DNS kann aber ebenso TCP nutzen.' },
            { name: 'HTTP/3', short: 'QUIC über UDP', flow: ['Browser', 'HTTP/3 und QUIC', 'UDP', 'Webserver'], description: 'HTTP/3 überträgt Webseiten zwischen Browser und Webserver. Für die Verbindung nutzt es QUIC; QUIC verschickt die Daten mit UDP. QUIC ist der Name eines Transportprotokolls, keine Abkürzung.', note: 'UDP allein kümmert sich nicht darum, ob Daten verloren gehen. QUIC erkennt fehlende Daten und sorgt dafür, dass sie erneut gesendet werden.', extra: 'QUIC ordnet die Daten auch in die richtige Reihenfolge, bevor sie an HTTP/3 weitergegeben werden.' },
        ],
    },
    {
        key: 'web', label: 'Webseite', kicker: 'Ein Aufruf im Internet', title: 'Wie öffnet der Browser eine Webseite?',
        intro: 'Vom Namen bis zur angezeigten Seite arbeiten mehrere Dienste und Geräte zusammen.', visualLabel: 'Schritt des Webseitenaufrufs auswählen', detailLabel: 'Was passiert?',
        caption: 'Der genaue Ablauf kann sich durch Zwischenspeicher und verwendete Protokolle unterscheiden.',
        items: [
            { name: 'DNS', short: 'Namen auflösen', flow: ['schooltool.at', 'DNS', 'IP-Adresse'], description: 'DNS steht für Domain Name System. Es funktioniert wie eine Adressauskunft: Zu einem Namen wie schooltool.at findet es die passende IP-Adresse.', note: 'Ist die Adresse noch nicht im Zwischenspeicher, fragt das Gerät einen DNS-Dienst. Mit der Antwort kann der Browser den Webserver erreichen.', extra: 'DNS liefert die Adresse; die Webseite selbst wird anschließend vom Webserver übertragen. DNS-Anfragen können UDP oder TCP verwenden.' },
            { name: 'Datenweg', short: 'über Router', flow: ['Gerät', 'Router', 'Webserver'], description: 'Liegt das Ziel außerhalb des eigenen Netzes, wird das IP-Paket zunächst an das Standardgateway gesendet. Weitere Router leiten es Richtung Ziel weiter.', note: 'Der Internetanbieter verbindet das lokale Netzwerk mit anderen Netzen.' },
            { name: 'HTTPS', short: 'Verbindung schützen', flow: ['Browser', 'verschlüsselt', 'Webserver'], description: 'Bei HTTPS wird eine verschlüsselte Verbindung aufgebaut. Dabei prüft das Gerät das Zertifikat des Servers.', note: 'Welche Transportverfahren verwendet werden, hängt unter anderem von der HTTP-Version ab.' },
            { name: 'Webseite', short: 'anfordern und anzeigen', flow: ['Browser', 'Anfrage', 'Webserver'], description: 'Der Browser fordert den Inhalt an. Der Server antwortet mit Daten, die der Browser verarbeitet und anzeigt.', note: 'Für Bilder, Schriftdateien und andere Bestandteile können weitere Anfragen folgen.' },
        ],
    },
    {
        key: 'local', label: 'LAN & Internet', kicker: 'Verbindungen unterscheiden', title: 'Was funktioniert ohne Internet?',
        intro: 'Ein lokales Netzwerk und die Internetverbindung sind unterschiedliche Teile des Datenwegs.', visualLabel: 'Situation auswählen', detailLabel: 'Mögliche Einordnung',
        caption: 'Eine einzelne Beobachtung beweist die Ursache nicht.',
        items: [
            { name: 'Lokales Netz', short: 'Geräte im eigenen Netz', flow: ['Computer', 'Switch', 'Drucker'], description: 'Ein lokaler Drucker oder Dateiserver kann auch ohne Internet erreichbar bleiben.', note: 'Dafür müssen die lokalen Geräte, Verbindungen, Dienste und Einstellungen funktionieren.' },
            { name: 'Internet fällt aus', short: 'lokal eventuell weiter nutzbar', flow: ['Computer', 'lokales Netz', 'Internet ✕'], description: 'Wenn externe Webseiten nicht erreichbar sind, kann der lokale Weg trotzdem funktionieren.', note: 'Mögliche Ursachen liegen etwa beim Internetzugang, bei DNS oder auf der Gegenseite.' },
            { name: 'Lokaler Dienst', short: 'nicht erreichbar', flow: ['Computer', 'lokales Netz', 'Dienst ✕'], description: 'Ist ein lokaler Dienst nicht erreichbar, kann der Fehler verschiedene Ursachen haben.', note: 'Dazu gehören das Gerät oder der Dienst selbst, das lokale Netz, fehlende Rechte oder eine Netztrennung.' },
            { name: 'Einzelne Webseite', short: 'andere Seiten funktionieren', flow: ['Browser', 'Internet', 'Webseite ✕'], description: 'Wenn nur eine Webseite nicht erreichbar ist, kann auch der betreffende Webdienst oder der Weg dorthin betroffen sein.', note: 'Eine DNS-Störung kann die Namensauflösung verhindern, obwohl eine andere Verbindung nach außen besteht.' },
        ],
    },
]

const route = useRoute()
const router = useRouter()
const pageIndex = ref(0)
const selection = ref(pages[0].items[0].name)
const currentPage = computed(() => pages[pageIndex.value])
const selectionIndex = computed(() => Math.max(0, currentPage.value.items.findIndex(item => item.name === selection.value)))
const selected = computed(() => currentPage.value.items[selectionIndex.value])
const encapsulationAnimationOpen = ref(false)

watch(() => [route?.query?.encapAnimation, route?.query?.encapStep], ([animation, step]) => {
    encapsulationAnimationOpen.value = animation === '1' || (animation !== '0' && step !== undefined)
}, { immediate: true })

watch(() => [route?.query?.chapter, route?.query?.selection], ([chapter, selectedName]) => {
    const index = pages.findIndex(page => page.key === chapter)
    pageIndex.value = index >= 0 ? index : 0
    const items = pages[pageIndex.value].items
    selection.value = items.find(item => item.name === selectedName)?.name || items[0].name
}, { immediate: true })

function syncLocation(push) {
    if (!router || !route) return
    const page = pages[pageIndex.value]
    const query = { ...route.query, chapter: page.key, selection: selection.value === page.items[0].name ? undefined : selection.value }
    if (page.key !== 'encapsulation') {
        delete query.encapStep
        delete query.encapDetails
        delete query.encapAnimation
    }
    router[push ? 'push' : 'replace']({ query, hash: route.hash })
}
function setEncapsulationAnimation(event) {
    const open = event.target.open
    if (open === encapsulationAnimationOpen.value) return
    encapsulationAnimationOpen.value = open
    if (!router || !route) return
    router.replace({ query: { ...route.query, encapAnimation: open ? '1' : '0' }, hash: route.hash })
}
function selectItem(name) { selection.value = name; syncLocation(false) }
function openPage(index) {
    if (index < 0 || index >= pages.length) return
    pageIndex.value = index
    selection.value = pages[index].items[0].name
    syncLocation(true)
}
</script>

<style scoped>
.communication-lesson { max-width: 1200px; margin: 0 auto; color: #1b3049; font-size: 1rem; line-height: 1.45; }
.lesson-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 10px; }
.lesson-header h1 { margin: 0; color: #193c68; font-size: clamp(1.35rem, 2.2vw, 1.8rem); line-height: 1.15; }
.eyebrow { margin: 0 0 2px; color: #52729a; font-size: .75rem; font-weight: 700; }.page-number { color: #64778b; font-size: .85rem; }
button { font: inherit; cursor: pointer; } button:focus-visible { outline: 3px solid #2c6fb6; outline-offset: 2px; }
button[aria-pressed='true'] { border-color: #3272b1 !important; background: #e5f1fc !important; color: #174a7d !important; box-shadow: inset 0 0 0 1px #3272b1; }
.lesson-navigation { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 5px; margin-bottom: 10px; }
.lesson-navigation button { min-width: 0; min-height: 38px; padding: 5px; border: 1px solid #cbd9e8; border-radius: 9px; background: #fff; color: #32577f; font-size: .76rem; font-weight: 650; }
.lesson-content { padding: 20px 24px; border: 1px solid #dce5ef; border-radius: 16px; background: #fff; }
.section-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 13px; }
.section-heading span, .detail-panel > span { color: #3973ad; font-size: .75rem; font-weight: 700; }
.section-heading h2 { margin: 1px 0 0; color: #193c68; font-size: clamp(1.25rem, 2vw, 1.65rem); line-height: 1.15; }
.section-heading > p { max-width: 45ch; margin: 0; color: #5a7189; font-size: .87rem; }
.two-columns { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); gap: 16px; align-items: stretch; }
.visual-panel, .detail-panel { min-width: 0; padding: 16px; border-radius: 12px; }.visual-panel { background: #f0f6fc; border: 1px solid #dbe8f4; }.detail-panel { background: #f8fafc; border: 1px solid #e1e8ef; }
.detail-panel h3 { margin: 3px 0 8px; color: #1f4d7d; font-size: 1.3rem; }.detail-panel p { margin: 0; font-size: .95rem; overflow-wrap: anywhere; }.detail-panel .note, .detail-panel .extra { margin-top: 15px; color: #516980; font-size: .86rem; }
.address-steps { margin: 12px 0 0; }.address-steps dt { color: #516980; font-size: .78rem; }.address-steps dd { margin: 1px 0 8px; color: #1f4d7d; font-size: .86rem; font-weight: 650; overflow-wrap: anywhere; }
.choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }.choice-grid button { display: flex; flex-direction: column; align-items: start; justify-content: center; min-height: 55px; padding: 8px 10px; border: 1px solid #c5d7e8; border-radius: 9px; background: #fff; color: #28527b; text-align: left; }.choice-grid button strong { font-size: .9rem; }.choice-grid button small { margin-top: 2px; color: #667d94; font-size: .73rem; }.choice-grid--layers button { min-height: 46px; }
.flow { display: flex; align-items: center; justify-content: center; gap: 7px; min-height: 64px; margin-bottom: 12px; }.flow span { display: grid; place-items: center; flex: 1; min-width: 0; min-height: 45px; padding: 7px; border: 1px solid #bfd3e6; border-radius: 9px; background: #fff; color: #28527b; text-align: center; font-size: .79rem; font-weight: 700; }.flow b { color: #3272b1; }
.envelope { display: flex; gap: 4px; align-items: center; min-height: 64px; margin-bottom: 12px; padding: 8px; border: 2px solid #8ab6dc; border-radius: 10px; background: #dbeeff; }.envelope span { flex: 1; padding: 7px 4px; border: 1px solid #a5c5df; border-radius: 7px; background: #fff; color: #28527b; text-align: center; font-size: .76rem; font-weight: 700; }
.caption { margin: 10px 0 0; color: #577089; font-size: .8rem; line-height: 1.35; }
.encapsulation-animation-details { margin-top: 12px; color: #28527b; }.encapsulation-animation-details summary { cursor: pointer; font-weight: 650; }.encapsulation-animation-details .encapsulation-animation { margin-top: 12px; }
.lesson-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0 0; }.lesson-footer button { min-height: 37px; padding: 6px 16px; border: 1px solid #b8cde1; border-radius: 8px; background: #fff; color: #265989; font-weight: 700; }.lesson-footer .next-button { border-color: #2868a8; background: #2868a8; color: #fff; }.lesson-footer button:disabled { opacity: .45; cursor: default; }.lesson-footer span { color: #667b91; font-size: .8rem; }
@media (max-width: 850px) { .lesson-navigation { grid-template-columns: repeat(4, minmax(0, 1fr)); }.section-heading { align-items: start; flex-direction: column; gap: 4px; }.two-columns { grid-template-columns: 1fr; }.detail-panel .note { margin-top: 8px; } }
@media (max-width: 520px) { .lesson-content { padding: 17px 14px; }.lesson-navigation button { font-size: .7rem; }.flow span { font-size: .7rem; }.lesson-footer span { text-align: center; } }
</style>
