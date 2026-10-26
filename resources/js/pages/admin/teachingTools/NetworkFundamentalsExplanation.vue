<template>
    <article class="network-lesson" aria-labelledby="network-title">
        <header class="lesson-header">
            <div><p class="eyebrow">Netzwerke verstehen</p><h1 id="network-title">Netzwerkgrundlagen und Geräte</h1></div>
            <span class="page-number">{{ pageIndex + 1 }} / {{ pages.length }}</span>
        </header>

        <nav class="lesson-navigation" aria-label="Themen auswählen">
            <button v-for="(page, index) in pages" :key="page.key" type="button"
                :aria-pressed="pageIndex === index" @click="pageIndex = index">{{ page.label }}</button>
        </nav>

        <main class="lesson-content">
            <section v-if="currentPage === 'network'" aria-labelledby="network-heading">
                <div class="section-heading"><div><span>Die Grundidee</span><h2 id="network-heading">Was verbindet ein Netzwerk?</h2></div>
                    <p>Geräte tauschen über Kabel oder Funk Daten aus und nutzen gemeinsame Dienste.</p></div>
                <div class="two-columns">
                    <div class="visual-panel network-map" aria-label="Aufbau eines lokalen Netzwerks">
                        <div class="map-core" :class="{ 'map-core--offline': !internetConnected }">
                            <button v-for="device in networkCore" :key="device" type="button" :aria-pressed="networkSelection === device" @click="networkSelection = device">{{ device }}</button>
                        </div>
                        <div class="map-branch" aria-hidden="true"></div>
                        <div class="map-devices">
                            <button v-for="device in networkEnds.slice(0, 3)" :key="device" type="button" :aria-pressed="networkSelection === device" @click="networkSelection = device">{{ device }}</button>
                            <div class="map-wireless"><span aria-hidden="true"></span><button type="button" :aria-pressed="networkSelection === 'Smartphone'" @click="networkSelection = 'Smartphone'">Smartphone · WLAN</button></div>
                        </div>
                        <p class="caption">Computer und Server sind am Switch; das Smartphone verbindet sich über den Access Point.</p>
                        <button class="connection-button" type="button" @click="internetConnected = !internetConnected">{{ internetConnected ? 'Internetverbindung trennen' : 'Internetverbindung herstellen' }}</button>
                        <p class="connection-state" role="status">{{ internetConnected ? 'Internetverbindung besteht' : 'Internetverbindung getrennt – das lokale Netzwerk bleibt bestehen' }}</p>
                    </div>
                    <div class="detail-panel" aria-live="polite"><span>Im Netzwerk</span><h3>{{ networkSelection }}</h3><p>{{ networkDescriptions[networkSelection] }}</p>
                        <p class="note">Gemeinsame Regeln heißen Protokolle; Adressen helfen, Daten zum Ziel zu bringen. Dateien, Drucker und Kommunikation können gemeinsam genutzt werden. Zugriffsrechte und Schutzmaßnahmen bleiben wichtig.</p></div>
                </div>
            </section>

            <section v-else-if="currentPage === 'types'" aria-labelledby="types-heading">
                <div class="section-heading"><div><span>Netze und Zugänge</span><h2 id="types-heading">Welche Arten von Netzwerken gibt es?</h2></div><p>Die Begriffe beschreiben die Reichweite, die Verbindung oder den Zugang zu Diensten.</p></div>
                <div class="two-columns"><div class="visual-panel"><div class="choice-grid">
                    <button v-for="item in networkTypes" :key="item.name" type="button" :aria-pressed="selectedType === item.name" @click="selectedType = item.name"><strong>{{ item.name }}</strong><span>{{ item.label }}</span></button>
                </div><p class="caption">LAN und WLAN können Teil desselben lokalen Netzwerks sein.</p></div>
                    <div class="detail-panel" aria-live="polite"><span>Ausgewählter Begriff</span><h3>{{ activeType.name }}</h3><p>{{ activeType.description }}</p><p class="note">Ein Internetanbieter verbindet das lokale Netzwerk mit anderen Netzen. Interne Dienste sind nur nach entsprechender Einrichtung und Berechtigung von außen erreichbar.</p></div></div>
            </section>

            <section v-else-if="currentPage === 'services'" aria-labelledby="services-heading">
                <div class="section-heading"><div><span>Dienste im Netzwerk</span><h2 id="services-heading">Wer bietet an, wer nutzt?</h2></div><p>Ein Dienst kann eine Webseite oder eine gemeinsame Dateiablage sein.</p></div>
                <div class="two-columns"><div class="visual-panel"><div class="segmented">
                    <button type="button" :aria-pressed="serviceMode === 'client'" @click="serviceMode = 'client'">Client-Server</button>
                    <button type="button" :aria-pressed="serviceMode === 'peer'" @click="serviceMode = 'peer'">Peer-to-Peer</button>
                </div><div class="service-diagram" v-if="serviceMode === 'client'">
                    <div><strong>Client</strong><small>nutzt den Dienst</small></div><div class="service-arrow"><span>{{ serviceStep === 'request' ? 'Anfrage' : 'Antwort' }}</span><b>{{ serviceStep === 'request' ? '→' : '←' }}</b></div><div><strong>Server</strong><small>bietet den Dienst an</small></div>
                </div><div v-else class="service-diagram"><div><strong>Gerät A</strong><small>bietet an und nutzt</small></div><div class="service-arrow"><span>direkter Austausch</span><b>↔</b></div><div><strong>Gerät B</strong><small>bietet an und nutzt</small></div></div>
                    <div v-if="serviceMode === 'client'" class="segmented"><button type="button" :aria-pressed="serviceStep === 'request'" @click="serviceStep = 'request'">Anfrage zeigen</button><button type="button" :aria-pressed="serviceStep === 'response'" @click="serviceStep = 'response'">Antwort zeigen</button></div></div>
                    <div class="detail-panel" aria-live="polite"><span>So funktioniert es</span><template v-if="serviceMode === 'client'"><h3>{{ serviceStep === 'request' ? 'Der Client fragt an' : 'Der Server antwortet' }}</h3><p>{{ serviceStep === 'request' ? 'Ein Browser fordert Inhalte vom Webserver an.' : 'Der Webserver sendet die Inhalte zurück. Der Browser zeigt sie an.' }}</p><p class="note">Client und Server sind Rollen, nicht zwingend verschiedene Gerätearten. Zentrale Verwaltung erleichtert etwa die Rechtevergabe; der Dienst hängt von der Erreichbarkeit des Servers ab.</p></template><template v-else><h3>Direkter Austausch</h3><p>Geräte können Ressourcen direkt austauschen und dabei gleichzeitig anbieten und nutzen.</p><p class="note">Je nach Anwendung können zusätzlich zentrale Dienste unterstützen.</p></template></div></div>
            </section>

            <section v-else-if="currentPage === 'transfer'" aria-labelledby="transfer-heading">
                <div class="section-heading"><div><span>Daten unterwegs</span><h2 id="transfer-heading">Datenrate und Latenz</h2></div><p>Wie schnell wird eine Datei übertragen? Wie lange dauern Hin- und Rückweg?</p></div>
                <div class="two-columns transfer-comparison">
                    <div class="visual-panel transfer-controls">
                        <h3>Datenrate: Wie viele Daten pro Sekunde?</h3>
                        <label for="transfer-rate">Datenrate <strong>{{ rate }} Mbit/s</strong></label><input id="transfer-rate" v-model.number="rate" type="range" min="10" max="1000" step="10" />
                        <div class="transfer-result" aria-live="polite"><span>Eine Datei mit 100 MB</span><strong>mindestens ca. {{ formattedTransferTime }} Sekunden</strong><small>Reine Übertragungszeit bei {{ formattedRate }} MB/s, ohne Verzögerung und Zusatzaufwand.</small></div>
                    </div>
                    <div class="detail-panel transfer-controls">
                        <h3>Latenz: Wie lange dauert der Hin- und Rückweg?</h3>
                        <label for="transfer-latency">Latenz <strong>{{ latency }} ms</strong></label><input id="transfer-latency" v-model.number="latency" type="range" min="5" max="200" step="5" />
                        <div class="transfer-result" aria-live="polite"><span>Gerät → Anfrage → Dienst<br />Gerät ← Antwort ← Dienst</span><strong>Hin und zurück: {{ latency }} ms = {{ formattedLatency }} s</strong><small>Bei höherer Latenz dauert dieser Weg länger. Der Wert ist hier frei gewählt, nicht gemessen.</small></div>
                    </div>
                </div>
                <p class="transfer-explanation">Eine hohe Datenrate bedeutet nicht automatisch eine kurze Reaktionszeit. Acht Bit sind ein Byte. Der tatsächliche Durchsatz kann niedriger sein; Download bedeutet Empfang, Upload das Senden von Daten. „Bandbreite“ bezeichnet im Netzwerkalltag oft die Kapazität, technisch auch einen Frequenzbereich.</p>
            </section>

            <section v-else-if="currentPage === 'devices'" aria-labelledby="devices-heading">
                <div class="section-heading"><div><span>Bausteine</span><h2 id="devices-heading">Was machen Netzwerkgeräte?</h2></div><p>Jedes Gerät übernimmt eine bestimmte Aufgabe. Mehrere Funktionen können in einem Gerät stecken.</p></div>
                <div class="two-columns"><div class="visual-panel"><div class="choice-grid devices-grid">
                    <button v-for="item in devices" :key="item.name" type="button" :aria-pressed="selectedDevice === item.name" @click="selectedDevice = item.name"><strong>{{ item.name }}</strong><span>{{ item.label }}</span></button>
                </div></div><div class="detail-panel" aria-live="polite"><span>Aufgabe</span><h3>{{ activeDevice.name }}</h3><p>{{ activeDevice.description }}</p><p class="note">Ein Heimrouter enthält häufig Router, Switch, WLAN-Access-Point und Firewall; je nach Anschluss auch ein Modem. Ein erweiterter Switch kann ebenfalls Routing übernehmen.</p></div></div>
            </section>

            <section v-else-if="currentPage === 'switch'" aria-labelledby="switch-heading">
                <div class="section-heading"><div><span>Im lokalen Netz</span><h2 id="switch-heading">Schnittstellen und Switches</h2></div><p>Die Netzwerkschnittstelle verbindet ein Gerät über Kabel oder Funk.</p></div>
                <div class="two-columns"><div class="visual-panel"><div class="choice-grid compact-grid">
                    <button v-for="item in switchConcepts" :key="item.name" type="button" :aria-pressed="selectedSwitch === item.name" @click="selectedSwitch = item.name"><strong>{{ item.name }}</strong><span>{{ item.label }}</span></button>
                </div></div><div class="detail-panel" aria-live="polite"><span>Begriff erklärt</span><h3>{{ activeSwitch.name }}</h3><p>{{ activeSwitch.description }}</p><p class="note">Eine MAC-Adresse ist die Adresse eines Netzwerkanschlusses, zum Beispiel des WLAN-Adapters oder des Ethernet-Anschlusses. Ein Switch kann sie nutzen, um Daten im lokalen Netzwerk an den richtigen Anschluss weiterzuleiten. WLAN und Kabel können unterschiedliche MAC-Adressen haben. Eine WLAN-Adresse kann zum Schutz der Privatsphäre auch zufällig gewählt werden.</p></div></div>
            </section>

            <section v-else-if="currentPage === 'topologies'" aria-labelledby="topology-heading">
                <div class="section-heading"><div><span>Strukturen</span><h2 id="topology-heading">Wie sind Geräte verbunden?</h2></div><p>Eine Topologie beschreibt den tatsächlichen oder logischen Aufbau eines Netzwerks.</p></div>
                <div class="two-columns"><div class="visual-panel topology-panel"><div class="segmented topology-options"><button v-for="item in topologies" :key="item.name" type="button" :aria-pressed="selectedTopology === item.name" @click="selectedTopology = item.name">{{ item.name }}</button></div>
                    <svg viewBox="0 0 250 120" role="img" :aria-label="activeTopology.diagram"><path :d="activeTopology.path"/><circle v-for="(point, index) in activeTopology.points" :key="index" :cx="point[0]" :cy="point[1]" r="7" /></svg>
                    <p class="caption">{{ activeTopology.diagram }}</p></div><div class="detail-panel" aria-live="polite"><span>Topologie</span><h3>{{ activeTopology.name }}</h3><p>{{ activeTopology.description }}</p><p class="note">In heutigen kabelgebundenen LANs ist die Sternstruktur häufig: Jedes Endgerät ist mit einem Switch verbunden.</p></div></div>
            </section>

            <section v-else aria-labelledby="building-heading">
                <div class="section-heading"><div><span>Im Gebäude</span><h2 id="building-heading">Von der Dose ins Netzwerk</h2></div><p>Die Gebäudeverkabelung führt zu zentralen Anschlusspunkten.</p></div>
                <div class="two-columns"><div class="visual-panel building-panel"><div class="building-chain">
                    <template v-for="(item, index) in buildingParts.slice(0, 4)" :key="item.name"><button type="button" :aria-pressed="selectedBuilding === item.name" @click="selectedBuilding = item.name">{{ item.name }}</button><span v-if="index < 3" aria-hidden="true">→</span></template>
                </div><p class="caption">Die feste Leitung endet am Patchpanel. Ein Patchkabel verbindet dieses mit dem Switch.</p>
                    <button type="button" class="usv-button" :aria-pressed="selectedBuilding === 'USV'" @click="selectedBuilding = 'USV'">USV – Stromversorgung</button></div>
                    <div class="detail-panel" aria-live="polite"><span>Baustein</span><h3>{{ activeBuilding.name }}</h3><p>{{ activeBuilding.description }}</p><p class="note">Patchpanel und Geräte stehen häufig in einem Netzwerkschrank. Leitungen und Anschlüsse müssen zu Datenrate und Entfernung passen.</p></div></div>
            </section>
        </main>

        <footer class="lesson-footer"><button type="button" :disabled="pageIndex === 0" @click="pageIndex--">← Zurück</button><span>{{ pages[pageIndex].label }} · {{ pageIndex + 1 }} von {{ pages.length }}</span><button type="button" class="next-button" :disabled="pageIndex === pages.length - 1" @click="pageIndex++">Weiter →</button></footer>
    </article>
</template>

<script setup>
import { computed, ref } from 'vue'

const pages = [
    { key: 'network', label: 'Netzwerk' }, { key: 'types', label: 'Netzarten' },
    { key: 'services', label: 'Dienste' }, { key: 'transfer', label: 'Übertragung' },
    { key: 'devices', label: 'Geräte' }, { key: 'switch', label: 'Switch & VLAN' },
    { key: 'topologies', label: 'Topologien' }, { key: 'building', label: 'Gebäude' },
]
const pageIndex = ref(0)
const currentPage = computed(() => pages[pageIndex.value].key)
const networkCore = ['Internet', 'Router', 'Switch']
const networkEnds = ['Computer', 'Server', 'Access Point', 'Smartphone']
const networkSelection = ref('Switch')
const internetConnected = ref(true)
const networkDescriptions = {
    Internet: 'Das Internet verbindet weltweit viele Netzwerke. Das lokale Netz kann auch ohne Internet funktionieren.',
    Router: 'Der Router verbindet verschiedene IP-Netze und leitet Datenpakete anhand ihrer Zieladresse weiter.',
    Switch: 'Der Switch verbindet Geräte im Ethernet-LAN und leitet Daten anhand von MAC-Adressen an passende Anschlüsse weiter.',
    Computer: 'Der Computer kann mit berechtigtem Zugriff Daten und Dienste im Netzwerk nutzen.',
    Server: 'Der Server stellt Dienste bereit, zum Beispiel eine Webseite oder eine gemeinsame Dateiablage.',
    'Access Point': 'Der Access Point stellt den drahtlosen Zugang zum lokalen Netzwerk bereit.',
    Smartphone: 'Das Smartphone ist hier über WLAN mit dem Access Point und damit dem lokalen Netzwerk verbunden.',
}
const networkTypes = [
    { name: 'LAN', label: 'lokal', description: 'Ein Local Area Network ist ein lokales Netzwerk, etwa in einem Gebäude oder auf einem Schulgelände. Geräte können unter anderem per Ethernet-Kabel verbunden sein.' },
    { name: 'WLAN', label: 'drahtlos', description: 'Ein Wireless Local Area Network bietet drahtlosen Zugang über Funk. Kabelgebundene und drahtlose Geräte können zum selben LAN gehören.' },
    { name: 'WAN', label: 'große Entfernung', description: 'Ein Wide Area Network verbindet Netzwerke über größere Entfernungen, etwa Standorte in verschiedenen Städten. Es kann auch privat betrieben werden.' },
    { name: 'Internet', label: 'weltweit', description: 'Das Internet ist ein weltweiter Zusammenschluss vieler Netzwerke, die gemeinsame Internetprotokolle nutzen.' },
    { name: 'Intranet', label: 'intern', description: 'Ein Intranet bietet interne Informationen und Dienste für eine begrenzte Organisation, etwa eine Schule.' },
    { name: 'Extranet', label: 'für ausgewählte Externe', description: 'Ein Extranet macht einen Teil interner Dienste für ausgewählte externe Personen oder Organisationen zugänglich. Der Zugang ist üblicherweise geschützt.' },
]
const selectedType = ref('LAN')
const activeType = computed(() => networkTypes.find(item => item.name === selectedType.value))
const serviceMode = ref('client')
const serviceStep = ref('request')
const rate = ref(100)
const latency = ref(40)
const formattedRate = computed(() => (rate.value / 8).toLocaleString('de-AT', { maximumFractionDigits: 2 }))
const formattedTransferTime = computed(() => (800 / rate.value).toLocaleString('de-AT', { maximumFractionDigits: 2 }))
const formattedLatency = computed(() => (latency.value / 1000).toLocaleString('de-AT', { minimumFractionDigits: 3, maximumFractionDigits: 3 }))
const devices = [
    { name: 'Switch', label: 'verbindet Geräte', description: 'Ein Switch verbindet Geräte in einem Ethernet-LAN. Ein einfacher Switch leitet Ethernet-Daten anhand von MAC-Adressen an passende Anschlüsse weiter.' },
    { name: 'Router', label: 'verbindet Netze', description: 'Ein Router verbindet verschiedene IP-Netzwerke und leitet Datenpakete anhand ihrer IP-Zieladresse weiter.' },
    { name: 'Access Point', label: 'ermöglicht WLAN', description: 'Ein Access Point bietet drahtlosen Zugang zum Netzwerk. Häufig ist er per Netzwerkkabel an das LAN angeschlossen.' },
    { name: 'Modem', label: 'passt Signale an', description: 'Ein Modem passt Signale an die Technik eines Internetanschlusses an, etwa bei DSL oder Kabel.' },
    { name: 'Glasfaser-ONT', label: 'optischer Netzabschluss', description: 'Ein optischer Netzabschluss setzt die Glasfaserverbindung des Anbieters in einen passenden Anschluss für das Kundennetz um.' },
    { name: 'Firewall', label: 'prüft Datenverkehr', description: 'Eine Firewall kontrolliert Netzwerkverkehr nach festgelegten Regeln. Sie kann ein eigenes Gerät oder eine Funktion in einem anderen Gerät sein.' },
    { name: 'Server und NAS', label: 'bieten Dienste', description: 'Ein Server stellt Dienste bereit. Ein NAS ist auf Datenspeicherung im Netzwerk ausgerichtet und kann Dateidienste bereitstellen.' },
]
const selectedDevice = ref('Switch')
const activeDevice = computed(() => devices.find(item => item.name === selectedDevice.value))
const switchConcepts = [
    { name: 'Netzwerkadapter', label: 'Anschluss ans Netz', description: 'Ein Netzwerkadapter verbindet ein Gerät per Kabel oder Funk mit dem Netzwerk.' },
    { name: 'Unmanaged Switch', label: 'ohne eigene Einrichtung', description: 'Ein unmanaged Switch arbeitet normalerweise ohne eigene Konfiguration.' },
    { name: 'Managed Switch', label: 'verwaltbar', description: 'Ein managed Switch bietet je nach Modell Verwaltungsfunktionen, etwa Anschlussüberwachung oder die Einrichtung von VLANs.' },
    { name: 'VLAN', label: 'logische Trennung', description: 'Ein VLAN trennt Netzwerkbereiche logisch innerhalb einer gemeinsamen Netzwerkinfrastruktur.' },
]
const selectedSwitch = ref('Netzwerkadapter')
const activeSwitch = computed(() => switchConcepts.find(item => item.name === selectedSwitch.value))
const topologies = [
    { name: 'Stern', diagram: 'Stern: Geräte sind einzeln mit einer zentralen Stelle verbunden.', path: 'M125 60L35 20 M125 60L215 20 M125 60L35 100 M125 60L215 100', points: [[125, 60], [35, 20], [215, 20], [35, 100], [215, 100]], description: 'Geräte sind einzeln mit einer zentralen Stelle verbunden. Fällt eine Leitung aus, betrifft das meist nur diesen Anschluss; fällt der zentrale Switch aus, sind alle angeschlossenen Geräte betroffen.' },
    { name: 'Baum', diagram: 'Baum: Verbindungen verzweigen sich hierarchisch.', path: 'M125 15L65 55L30 100 M65 55L100 100 M125 15L185 55L150 100 M185 55L220 100', points: [[125, 15], [65, 55], [185, 55], [30, 100], [100, 100], [150, 100], [220, 100]], description: 'Mehrere sternförmige Bereiche sind hierarchisch verbunden, etwa Etagen-Switches mit einem zentralen Switch.' },
    { name: 'Bus', diagram: 'Bus: Geräte teilen sich eine gemeinsame Leitung.', path: 'M15 80H235 M55 80V30 M125 80V30 M195 80V30', points: [[55, 30], [125, 30], [195, 30]], description: 'Geräte teilen sich eine gemeinsame Leitung. Bei heutigen Ethernet-LANs mit Switches ist diese Struktur unüblich.' },
    { name: 'Ring', diagram: 'Ring: Verbindungen bilden einen geschlossenen Ring.', path: 'M55 20H195V100H55Z', points: [[55, 20], [195, 20], [195, 100], [55, 100]], description: 'Komponenten sind zu einem geschlossenen Ring verbunden. Das Verhalten bei Ausfall hängt von der eingesetzten Technik ab.' },
    { name: 'Mesh', diagram: 'Mesh: Knoten sind über mehrere Wege verbunden.', path: 'M55 20H195V100H55Z M55 20L195 100 M195 20L55 100', points: [[55, 20], [195, 20], [195, 100], [55, 100]], description: 'Knoten besitzen mehrere Verbindungen untereinander. Je nach Technik können alternative Wege verfügbar sein.' },
]
const selectedTopology = ref('Stern')
const activeTopology = computed(() => topologies.find(item => item.name === selectedTopology.value))
const buildingParts = [
    { name: 'Netzwerkdose', description: 'Die Netzwerkdose im Raum ist Teil der festen Gebäudeverkabelung. Ihre Leitung führt zu einem zentralen Anschlusspunkt.' },
    { name: 'Patchpanel', description: 'Das Patchpanel sammelt die fest installierten Leitungen. Kurze Patchkabel verbinden seine Anschlüsse mit einem Switch.' },
    { name: 'Switch', description: 'Der Switch verbindet die angeschlossenen Geräte im lokalen Netzwerk. Er kann mit weiteren Netzwerkbereichen verbunden sein.' },
    { name: 'Backbone', description: 'Ein Backbone ist eine leistungsfähige zentrale Verbindung zwischen größeren Netzwerkbereichen, etwa zwischen Etagen-Switches.' },
    { name: 'USV', description: 'Eine unterbrechungsfreie Stromversorgung versorgt geeignete Geräte bei Stromausfall für begrenzte Zeit. Die Dauer hängt von Kapazität und angeschlossener Last ab.' },
]
const selectedBuilding = ref('Netzwerkdose')
const activeBuilding = computed(() => buildingParts.find(item => item.name === selectedBuilding.value))
</script>

<style scoped>
.network-lesson { max-width: 1200px; margin: 0 auto; color: #1b3049; font-size: 1rem; line-height: 1.45; }
.lesson-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 10px; }
.lesson-header h1 { margin: 0; color: #193c68; font-size: clamp(1.35rem, 2.2vw, 1.8rem); line-height: 1.15; }
.eyebrow { margin: 0 0 2px; color: #52729a; font-size: .75rem; font-weight: 700; }.page-number { flex: 0 0 auto; color: #64778b; font-size: .85rem; }
.lesson-navigation { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 5px; margin-bottom: 10px; }
button { font: inherit; cursor: pointer; } button:focus-visible, input:focus-visible { outline: 3px solid #2c6fb6; outline-offset: 2px; }
.lesson-navigation button, .segmented button { min-height: 38px; padding: 6px; border: 1px solid #cbd9e8; border-radius: 9px; background: #fff; color: #32577f; font-size: .8rem; font-weight: 650; }
button[aria-pressed='true'] { border-color: #3272b1 !important; background: #e5f1fc !important; color: #174a7d !important; box-shadow: inset 0 0 0 1px #3272b1; }
.lesson-content { padding: 20px 24px; border: 1px solid #dce5ef; border-radius: 16px; background: #fff; }
.section-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 13px; }.section-heading span, .detail-panel > span { color: #3973ad; font-size: .75rem; font-weight: 700; }.section-heading h2 { margin: 1px 0 0; color: #193c68; font-size: clamp(1.25rem, 2vw, 1.65rem); line-height: 1.15; }.section-heading > p { max-width: 43ch; margin: 0; color: #5a7189; font-size: .87rem; }
.two-columns { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); gap: 16px; align-items: stretch; }.visual-panel, .detail-panel { min-width: 0; padding: 16px; border-radius: 12px; }.visual-panel { background: #f0f6fc; border: 1px solid #dbe8f4; }.detail-panel { background: #f8fafc; border: 1px solid #e1e8ef; }.detail-panel h3 { margin: 3px 0 8px; color: #1f4d7d; font-size: 1.3rem; }.detail-panel p { margin: 0; font-size: .95rem; }.detail-panel .note { margin-top: 15px; color: #516980; font-size: .86rem; }
.map-core, .map-devices { display: grid; gap: 8px; }.map-core { position: relative; grid-template-columns: repeat(3, 1fr); }.map-core::before { position: absolute; top: 50%; right: 12%; left: 12%; height: 3px; background: #a6c1dc; content: ''; }.map-core--offline::before { background: linear-gradient(to right, transparent 0 50%, #a6c1dc 50% 100%); }.map-core--offline button:first-child { opacity: .55; }.map-core button { position: relative; }.map-devices { grid-template-columns: repeat(3, 1fr); }.map-core button, .map-devices button, .building-chain button, .usv-button { min-height: 43px; padding: 6px; border: 1px solid #b9cfe5; border-radius: 9px; background: #fff; color: #28527b; font-weight: 700; font-size: .82rem; }.map-branch { position: relative; height: 19px; }.map-branch::before { position: absolute; top: 0; left: 83.33%; height: 10px; border-left: 2px solid #a6c1dc; content: ''; }.map-branch::after { position: absolute; top: 9px; right: 16.67%; left: 16.67%; height: 10px; border: 2px solid #a6c1dc; border-bottom: 0; content: ''; }.map-wireless { grid-column: 3; display: grid; justify-items: stretch; margin-top: -8px; }.map-wireless span { justify-self: center; height: 12px; border-left: 2px dashed #7ba2c6; }.caption { margin: 9px 0 0; color: #577089; font-size: .8rem; line-height: 1.35; }.connection-button { margin-top: 8px; padding: 5px 10px; border: 1px solid #98b5d1; border-radius: 7px; background: #fff; color: #265a8d; font-size: .8rem; font-weight: 650; }.connection-state { display: inline; margin-left: 8px; color: #516980; font-size: .78rem; }
.choice-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }.choice-grid button { display: flex; flex-direction: column; align-items: start; justify-content: center; min-height: 62px; padding: 8px 10px; border: 1px solid #c5d7e8; border-radius: 9px; background: white; color: #28527b; text-align: left; }.choice-grid button strong { font-size: .9rem; }.choice-grid button span { margin-top: 2px; color: #667d94; font-size: .73rem; }.compact-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.devices-grid button { min-height: 52px; }
.segmented { display: flex; flex-wrap: wrap; gap: 6px; }.segmented button { padding-inline: 12px; }.service-diagram { display: flex; align-items: center; justify-content: center; gap: 12px; min-height: 126px; }.service-diagram > div:not(.service-arrow) { display: grid; min-width: 115px; padding: 12px; border: 1px solid #bfd3e6; border-radius: 10px; background: #fff; text-align: center; color: #28527b; }.service-diagram small { color: #637c94; font-size: .72rem; }.service-arrow { display: grid; color: #3973ad; text-align: center; font-size: .76rem; }.service-arrow b { font-size: 2rem; line-height: 1; }
.transfer-controls h3 { margin: 0 0 12px; color: #1f4d7d; font-size: 1rem; }.transfer-controls label { display: flex; justify-content: space-between; gap: 12px; color: #28527b; font-weight: 700; font-size: .9rem; }.transfer-controls input { width: 100%; margin: 8px 0; accent-color: #3272b1; }.transfer-result { display: grid; gap: 3px; margin-top: 8px; padding: 10px 12px; border: 1px solid #d5e2ee; border-radius: 9px; background: #fff; color: #28527b; font-size: .88rem; }.transfer-result strong { color: #193c68; }.transfer-result small { color: #516980; font-size: .8rem; line-height: 1.35; }.transfer-explanation { margin: 12px 0 0; color: #516980; font-size: .83rem; line-height: 1.4; }
.topology-panel svg { display: block; width: min(100%, 250px); height: 120px; margin: 8px auto 0; }.topology-panel svg path { fill: none; stroke: #84acd1; stroke-width: 3; }.topology-panel svg circle { fill: #326fae; stroke: #d9eaf8; stroke-width: 3; }.topology-options { justify-content: center; }.topology-options button { min-width: 65px; }
.building-chain { display: flex; align-items: center; gap: 6px; }.building-chain button { flex: 1; min-width: 0; }.building-chain > span { color: #6591b9; font-weight: 700; }.usv-button { margin-top: 16px; padding-inline: 14px; }
.lesson-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0 0; }.lesson-footer button { min-height: 37px; padding: 6px 16px; border: 1px solid #b8cde1; border-radius: 8px; background: #fff; color: #265989; font-weight: 700; }.lesson-footer button.next-button { border-color: #2868a8; background: #2868a8; color: #fff; }.lesson-footer button:disabled { opacity: .45; cursor: default; }.lesson-footer span { color: #667b91; font-size: .8rem; }
.map-branch { background: linear-gradient(#a6c1dc, #a6c1dc) 50% 100% / 2px 10px no-repeat; }
@media (max-width: 850px) { .lesson-navigation { grid-template-columns: repeat(4, minmax(0, 1fr)); }.section-heading { align-items: start; flex-direction: column; gap: 4px; }.two-columns { grid-template-columns: 1fr; }.detail-panel .note { margin-top: 8px; } }
@media (max-width: 520px) { .lesson-content { padding: 17px 14px; }.lesson-navigation { grid-template-columns: repeat(4, minmax(0, 1fr)); }.lesson-navigation button { font-size: .72rem; }.choice-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.map-core button, .map-devices button { font-size: .72rem; }.building-chain { flex-wrap: wrap; }.building-chain button { flex: 1 1 36%; }.lesson-footer span { text-align: center; }.service-diagram > div:not(.service-arrow) { min-width: 90px; } }
</style>
