<template>
    <article class="addressing-lesson" aria-labelledby="addressing-title">
        <header class="lesson-heading"><div><p class="eyebrow">Netzwerke verstehen · Lernmodul 04</p><h1 id="addressing-title">IP-Adressen, Netze und Routing</h1></div><span>Vom Anschluss bis zur Antwort</span></header>
        <nav class="chapter-tabs" aria-label="Themen auswählen">
            <button v-for="chapter in chapters" :key="chapter.key" type="button" :aria-pressed="chapterKey === chapter.key" @click="openChapter(chapter.key)">{{ chapter.title }}</button>
        </nav>

        <section v-if="chapterKey === 'journey'" class="chapter" aria-labelledby="journey-title">
            <header class="section-heading"><div><p class="eyebrow">Ein Ablauf · drei Ziele</p><h2 id="journey-title">Was passiert wann?</h2></div><p>Wähle ein Ziel und gehe den Weg Schritt für Schritt durch.</p></header>
            <div class="scenario-choices" aria-label="Beispielverbindung auswählen">
                <button v-for="item in networkScenarios" :key="item.key" type="button" :aria-pressed="scenarioKey === item.key" @click="selectScenario(item.key)"><strong>{{ item.title }}</strong><code>{{ item.address }}</code><small>{{ item.short }}</small></button>
            </div>
            <div class="journey-options">
                <label>Konfiguration <select v-model="configuration" aria-label="Netzwerkeinstellungen beziehen" @change="restart"><option value="dhcp">Automatisch per DHCP</option><option value="static">Statisch eingetragen</option></select></label>
                <label class="check-option"><input v-model="blocked" type="checkbox" :disabled="scenarioKey === 'local'" @change="restart" /> Router-Firewall blockiert die Verbindung</label>
            </div>
            <div class="device-config" aria-label="Aktuelle Einstellungen des PCs">
                <div><span>PC-Adresse</span><code>{{ current.configured ? '192.168.10.20' : 'noch nicht eingerichtet' }}</code></div>
                <div :class="{ relevant: current.concepts.includes('Präfix') }"><span>Netzmaske / Präfix</span><code>{{ current.configured ? '255.255.255.0 · /24' : 'noch nicht eingerichtet' }}</code></div>
                <div><span>Gateway</span><code>{{ current.configured ? '192.168.10.1' : '—' }}</code></div>
                <div><span>DNS-Server</span><code>{{ current.configured ? '192.168.10.1' : '—' }}</code></div>
            </div>
            <div class="journey-workbench">
                <div class="network-scene">
                    <svg viewBox="0 0 960 390" role="img" :aria-label="`Netzwerkbeispiel: ${current.title}`" class="network-map">
                        <rect x="28" y="18" width="555" height="195" rx="16" class="vlan-zone" /><text x="48" y="44" class="zone-title">Unterricht · VLAN 10 · 192.168.10.0/24</text>
                        <rect x="659" y="242" width="273" height="129" rx="16" class="vlan-zone server-zone" /><text x="679" y="267" class="zone-title">Server · VLAN 20</text>
                        <path d="M100 165 L300 165 L500 65 M300 165 L500 270 L790 300 M500 270 L690 150 L850 65" class="network-links" />
                        <g v-if="current.path" :key="`${scenarioKey}-${stepIndex}-${configuration}`">
                            <path :d="current.path" class="active-path" />
                            <circle r="9" class="moving-packet"><animateMotion v-if="!reducedMotion" :path="current.path" dur="1.25s" fill="freeze" /><set v-else attributeName="opacity" to="0" /></circle>
                        </g>
                        <g :class="{ active: current.active === 'pc' }" class="device"><rect x="52" y="121" width="96" height="65" rx="10"/><text x="100" y="148">PC</text><text x="100" y="170" class="small pc-address">{{ current.configured ? '.10.20' : 'keine IP' }}</text></g>
                        <g :class="{ active: current.active === 'switch' }" class="device"><rect x="247" y="126" width="106" height="60" rx="10"/><text x="300" y="151">Switch</text><text x="300" y="172" class="small">VLAN 10</text></g>
                        <g :class="{ active: current.active === 'printer' || (current.id === 'local-frame' && scenarioKey === 'local') }" class="device"><rect x="447" y="49" width="106" height="62" rx="10"/><text x="500" y="75">Drucker</text><text x="500" y="96" class="small">.10.30</text></g>
                        <g :class="{ active: current.active === 'router' }" class="device router"><rect x="427" y="240" width="146" height="72" rx="12"/><text x="500" y="266">Router</text><text x="500" y="289" class="small">DHCP · DNS · Firewall</text></g>
                        <text x="350" y="236" class="interface-label">LAN: .10.1</text><text x="583" y="329" class="interface-label">LAN: .20.1</text>
                        <g :class="{ active: current.active === 'server' }" class="device"><rect x="734" y="278" width="112" height="64" rx="10"/><text x="790" y="303">Server</text><text x="790" y="325" class="small">.20.30/24</text></g>
                        <g class="device provider"><rect x="627" y="119" width="126" height="59" rx="26"/><text x="690" y="144">Provider</text><text x="690" y="164" class="small">weitere Router</text></g>
                        <g :class="{ active: current.active === 'web' }" class="device"><rect x="781" y="32" width="142" height="65" rx="10"/><text x="852" y="59">Webserver</text><text x="852" y="81" class="small">203.0.113.80</text></g>
                    </svg>
                    <p class="diagram-caption">Adressen mit „.10“ bzw. „.20“ beginnen hier mit 192.168. Der Serverzweig steht schematisch für den lokalen Abschnitt im VLAN 20. Die Verbindung zum Provider fasst weitere Router zusammen.</p>
                    <dl class="packet-ledger" aria-label="Adressen im aktuellen Schritt">
                        <div><dt>Quell-IP</dt><dd>{{ current.source || '—' }}</dd></div><div><dt>Ziel-IP</dt><dd>{{ current.destination || '—' }}</dd></div>
                        <div><dt>Quell-MAC auf diesem Abschnitt</dt><dd>{{ current.sourceMac || '—' }}</dd></div><div><dt>Ziel-MAC auf diesem Abschnitt</dt><dd>{{ current.nextMac || '—' }}</dd></div>
                    </dl>
                    <p v-if="!current.source" class="diagram-caption">In diesem Schritt stehen Konfiguration, Entscheidung oder Nachbarauflösung im Mittelpunkt; die Striche sind keine erfundenen IP-Adressen.</p>
                </div>
                <section class="step-explanation" aria-live="polite" aria-atomic="true" :class="{ failure: current.blocked, finished: current.complete }">
                    <div class="step-progress">
                        <span>Aktueller Schritt</span>
                        <strong>Schritt {{ stepIndex + 1 }} / {{ steps.length }}</strong>
                        <progress :value="stepIndex + 1" :max="steps.length" aria-label="Fortschritt im Ablauf">{{ stepIndex + 1 }} / {{ steps.length }}</progress>
                    </div>
                    <h3>{{ current.title }}</h3><p>{{ current.body }}</p>
                    <div class="reason"><strong>Warum gerade jetzt?</strong><p>{{ current.why }}</p></div>
                    <div class="concepts" aria-label="Jetzt benötigte Begriffe"><span v-for="concept in current.concepts" :key="concept">{{ concept }}</span></div>
                    <div v-if="current.compare" class="net-comparison"><code>192.168.10.20 UND /24 = 192.168.10.0</code><code>{{ current.compare }} UND /24 = {{ ipv4Network(current.compare, 24).network }}</code><strong>{{ ipv4Network(current.compare, 24).network === '192.168.10.0' ? 'Gleiches Subnetz → lokal zustellen' : 'Anderes Subnetz → Routingtabelle prüfen' }}</strong></div>
                    <table v-if="current.routeTarget" class="route-table"><caption>Routingtabelle des Beispielrouters</caption><thead><tr><th>Zielpräfix</th><th>Weg</th></tr></thead><tbody><tr v-for="route in routerExampleRoutes" :key="route.network" :class="{ chosen: matchingRoute(current.routeTarget, routerExampleRoutes) === route }"><td><code>{{ route.network }}/{{ route.prefix }}</code></td><td>{{ route.via }}</td></tr></tbody></table>
                    <div v-if="current.nat" class="nat-record"><strong>Gespeicherte NAT-/Portzuordnung</strong><code>192.168.10.20:53000 ↔ 198.51.100.7:62001</code><small>Ziel der Verbindung: 203.0.113.80:443</small></div>
                </section>
            </div>
            <div class="playback-controls" aria-label="Animation steuern">
                <button type="button" :disabled="stepIndex === 0" aria-label="Vorheriger Ablaufschritt" @click="go(stepIndex - 1)">Zurück</button>
                <button type="button" :disabled="stepIndex === steps.length - 1" aria-label="Nächster Ablaufschritt" @click="go(stepIndex + 1)">Weiter</button>
                <button type="button" @click="restart">Neustart</button>
            </div>
            <ol class="step-timeline" aria-label="Gesamten Ablauf auswählen"><li v-for="(step, index) in steps" :key="step.id"><button type="button" :aria-current="index === stepIndex ? 'step' : undefined" @click="go(index)"><span>{{ index + 1 }}</span>{{ step.title }}</button></li></ol>
            <p class="footnote">Schematisches IPv4-Beispiel. Broadcastantworten, Cachezustand und Firewallregeln sind bewusst festgelegt. Einzelne Protokollpakete und Verbindungsaufbauten sind zusammengefasst; die Animation zeigt Reihenfolge und Entscheidungen, keine gemessenen Zeiten. Die öffentlichen Adressen stammen aus Dokumentationsnetzen.</p>
        </section>

        <section v-else-if="chapterKey === 'mask'" class="chapter" aria-labelledby="mask-title">
            <header class="section-heading"><div><p class="eyebrow">Selbst ausprobieren</p><h2 id="mask-title">Wo endet das Netz, wo beginnt der Host?</h2></div><p>Das Präfix zählt führende Netzbits. Die Netzmaske beschreibt dieselbe Grenze für IPv4.</p></header>
            <div class="lab-controls"><label>Präfixlänge <select v-model.number="prefix" aria-label="Präfixlänge auswählen" @change="syncLocation"><option v-for="value in [16, 24, 25, 26, 27, 28]" :key="value" :value="value">/{{ value }}</option></select></label><label>Zieladresse <select v-model="labTarget" aria-label="Zieladresse vergleichen" @change="syncLocation"><option value="192.168.10.30">192.168.10.30</option><option value="192.168.10.130">192.168.10.130</option><option value="192.168.20.30">192.168.20.30</option></select></label><strong class="mask-value">/{{ prefix }} = {{ ownNetwork.mask }}</strong></div>
            <div class="bit-lab" tabindex="0" aria-label="Binäre UND-Verknüpfung, horizontal scrollbar"><div v-for="row in bitRows" :key="row.title" class="bit-row"><span>{{ row.title }}</span><code><b v-for="(bit, index) in row.bits" :key="index" :class="{ host: index >= prefix, 'octet-start': index % 8 === 0 }">{{ bit }}</b></code></div><div class="bit-key"><span>Netzanteil: {{ prefix }} Bit</span><span>Hostanteil: {{ 32 - prefix }} Bit</span></div></div>
            <div class="lab-results"><section><h3>Der PC</h3><code>192.168.10.20/{{ prefix }}</code><p>Netz: <strong>{{ ownNetwork.network }}/{{ prefix }}</strong></p><p>Netzadresse: {{ ownNetwork.network }}<br />Broadcast: {{ ownNetwork.broadcast }}<br />Gewöhnliche Hostadressen: {{ ownNetwork.firstHost }} bis {{ ownNetwork.lastHost }}<br />{{ ownNetwork.addressCount }} Adressen insgesamt, {{ ownNetwork.addressCount - 2 }} gewöhnliche Hostadressen.</p></section><section><h3>Die Zieladresse</h3><code>{{ labTarget }}/{{ prefix }}</code><p>Verglichener Netzanteil: <strong>{{ targetNetwork.network }}</strong></p><p class="lab-decision">{{ sameSubnet ? 'Der PC hält das Ziel für lokal erreichbar.' : 'Der PC benötigt eine passende Route über einen Router.' }}</p><p>Verglichen wird mit dem Präfix des sendenden PCs. Die Gegenstelle besitzt ihre eigene Konfiguration und muss den Rückweg ebenfalls bestimmen.</p></section></div>
            <p v-if="prefix === 16 && labTarget === '192.168.20.30'" class="warning-note">Achtung: /16 lässt den PC beide Adressen demselben Netz zuordnen. Dadurch werden VLAN 10 und VLAN 20 aber nicht verbunden. Im gezeigten Schulnetz wäre diese zu breite PC-Maske falsch: Der PC würde lokal per ARP suchen, statt das Gateway zu benutzen.</p>
            <div class="subnet-demo"><h3>Ein /24-Netz in vier /26-Subnetze aufteilen</h3><div class="subnet-blocks"><div v-for="start in [0, 64, 128, 192]" :key="start" :class="{ contains: prefix === 26 && Number(ownNetwork.network.split('.')[3]) === start }"><code>192.168.10.{{ start }}/26</code><span>.{{ start }} bis .{{ start + 63 }}</span><small>64 Adressen · gewöhnlich 62 für Hosts</small></div></div><p>Bei /26 sind zwei zusätzliche Bits Netzbits. Die Netzadressen liegen deshalb im Abstand 64. Routeradressen belegen ebenfalls Hostadressen. /31 und /32 haben spezielle Verwendungen; die übliche „minus zwei“-Zählung gilt dort nicht so.</p></div>
        </section>

        <section v-else-if="chapterKey === 'addresses'" class="chapter">
            <header class="section-heading"><div><p class="eyebrow">Eine Adresse gehört zu einer Schnittstelle</p><h2>IPv4, IPv6 und Adressräume</h2></div><p>Ein Gerät kann für LAN und WLAN mehrere Adressen besitzen. Eine IP-Adresse ist kein Personen- oder Benutzername.</p></header>
            <div class="knowledge-grid"><section><h3>IPv4 · 32 Bit</h3><code class="big-address">192.168.10.20/24</code><p>Vier Dezimalzahlen von 0 bis 255. Das /24 beschreibt den Netzanteil; es gehört nicht zu den vier Adresszahlen.</p><h4>Private Bereiche</h4><table><thead><tr><th>Bereich</th><th>Präfix</th></tr></thead><tbody><tr><td>10.0.0.0 – 10.255.255.255</td><td>/8</td></tr><tr><td>172.16.0.0 – 172.31.255.255</td><td>/12</td></tr><tr><td>192.168.0.0 – 192.168.255.255</td><td>/16</td></tr></tbody></table><p>Unabhängige private Netze dürfen dieselben Adressen nutzen. Private Adressen werden im öffentlichen Internet nicht als gewöhnliche global erreichbare Adressen geroutet.</p></section>
            <section><h3>IPv6 · 128 Bit</h3><code class="big-address">2001:db8:1:2::20/64</code><p>Ausgeschrieben:</p><code class="expanded-address">2001:0db8:0001:0002:0000:0000:0000:0020</code><p>Führende Nullen pro Gruppe dürfen entfallen. <strong>::</strong> ersetzt eine zusammenhängende Folge von Nullgruppen – höchstens einmal in einer Adresse.</p><p>/64 bezeichnet hier die ersten 64 Netzbits. 2001:db8::/32 ist für Beispiele reserviert.</p><h4>Was läuft anders?</h4><p>IPv6 nutzt Nachbarerkennung statt ARP. Router Advertisements können Präfixe und Standardrouter bekanntgeben; Adressen entstehen etwa durch SLAAC oder DHCPv6. Ein IPv6-Standardrouter wird normalerweise über Router Advertisements gelernt, nicht durch DHCPv6.</p><p>IPv4 und IPv6 können gleichzeitig laufen: <strong>Dual Stack</strong>. IPv6 benötigt nicht grundsätzlich NAT; Firewallregeln bleiben wichtig.</p></section></div>
            <p class="warning-note">Eine öffentliche IP-Adresse bedeutet noch keine Erreichbarkeit eines Dienstes. Routen, Firewall und Dienstkonfiguration müssen passen. Bei zusätzlicher Provider-NAT teilen sich mitunter mehrere Anschlüsse eine öffentliche IPv4-Adresse.</p>
        </section>

        <section v-else-if="chapterKey === 'routing'" class="chapter">
            <header class="section-heading"><div><p class="eyebrow">Jedes Gerät entscheidet selbst</p><h2>Die längste passende Präfixlänge gewinnt</h2></div><p>Das Standardgateway ist der Ausweg, wenn keine spezifischere Route passt.</p></header>
            <label class="route-picker">Ziel beim Beispielrouter <select v-model="routeTarget" aria-label="Routingziel auswählen"><option value="192.168.10.20">192.168.10.20</option><option value="192.168.20.30">192.168.20.30</option><option value="192.168.30.40">192.168.30.40</option><option value="203.0.113.80">203.0.113.80</option></select></label>
            <table class="route-table"><caption>Zusätzliche /16-Route zum Vergleich mit der spezifischen /24-Route</caption><thead><tr><th>Zielpräfix</th><th>Passt?</th><th>Weiterleitung</th></tr></thead><tbody><tr v-for="route in routerExampleRoutes" :key="route.network" :class="{ chosen: selectedRoute === route }"><td><code>{{ route.network }}/{{ route.prefix }}</code></td><td>{{ ipv4Network(routeTarget, route.prefix).network === route.network ? 'ja' : 'nein' }}{{ selectedRoute === route ? ' · ausgewählt' : '' }}</td><td>{{ route.via }}</td></tr></tbody></table>
            <div class="knowledge-grid"><section><h3>Beim PC</h3><p>Sein angeschlossenes 192.168.10.0/24 erreicht er direkt. Andere Ziele laufen im Ablaufbeispiel über 192.168.10.1. Eine zusätzliche spezifischere Route könnte einen anderen Weg vorgeben.</p></section><section><h3>Beim Router</h3><p>Er prüft seine eigene Tabelle neu. /24 ist spezifischer als /16 und /0. Die Tabellenzeilen werden nicht bloß von oben nach unten als willkürliche Reihenfolge behandelt.</p><p>Auch die Antwort braucht passende Routen. Ein nicht erreichbares Gateway, eine fehlende Route oder eine sperrende Firewall kann die Verbindung verhindern.</p></section></div>
        </section>

        <section v-else-if="chapterKey === 'dhcp'" class="chapter">
            <header class="section-heading"><div><p class="eyebrow">Konfiguration kommt vor normaler Zustellung</p><h2>Woher weiß der PC sein Netz?</h2></div><p>DHCP kann bei IPv4 Adresse, Netzmaske, Gateway und DNS-Server liefern.</p></header>
            <ol class="dhcp-sequence"><li v-for="item in dhcpSteps" :key="item.title"><strong>{{ item.title }}</strong><span>{{ item.text }}</span></li></ol>
            <div class="knowledge-grid"><section><h3>Lease und Reservierung</h3><p>Eine dynamische Adresse wird zeitlich begrenzt überlassen. Der Client kann die Lease verlängern. Eine DHCP-Reservierung kann einem bestimmten Gerät wieder dieselbe Adresse zuweisen.</p></section><section><h3>Statisch konfigurieren</h3><p>Eine Person oder feste Konfiguration setzt die Werte. Adresse, Präfix und Gateway müssen passen; doppelt vergebene Adressen können Störungen auslösen. Eine manuelle Adresse ist kein Ersatz für die übrigen Einstellungen.</p></section></div>
            <button type="button" class="primary action" @click="configuration = 'dhcp'; openChapter('journey'); restart()">DHCP im gesamten Ablauf ansehen</button>
        </section>

        <section v-else class="chapter">
            <header class="section-heading"><div><p class="eyebrow">Drei unterschiedliche Aufgaben</p><h2>NAT, VLAN und Firewall</h2></div><p>Adressübersetzung, Netztrennung und Zugriffsregeln wirken zusammen, ersetzen sich aber nicht.</p></header>
            <div class="knowledge-grid"><section><h3>NAT mit Portübersetzung</h3><p>Beim Internetbeispiel teilt der Router seine öffentliche Beispieladresse zwischen mehreren internen Verbindungen auf. Unterschiedliche Portzuordnungen unterscheiden die Verbindungen.</p><div class="nat-record"><code>192.168.10.20:53000 ↔ 198.51.100.7:62001</code><small>Die Antwort an Port 62001 wird diesem PC und Port 53000 zugeordnet.</small></div><p>Der Router übersetzt beim Hinweg die Quelle und beim Rückweg das zugehörige Ziel. Im lokalen Druckerweg und zwischen den beiden Schulnetzen wird hier kein NAT benötigt.</p></section>
            <section><h3>VLANs trennen lokale Bereiche</h3><p>Ein geeigneter verwaltbarer Switch kann Anschlüsse für Unterricht, Verwaltung und Gäste logisch trennen. Verbindungen zwischen Switches können VLAN-Informationen über konfigurierte Trunks mitführen.</p><p>VLANs können die Verkabelung teilen und trotzdem getrennte Broadcastbereiche bilden. Im Beispiel gehören VLAN 10 und VLAN 20 zu zwei verschiedenen /24-Subnetzen.</p><p>Ein VLAN ist eine Trennung auf der lokalen Netzebene; das Subnetz beschreibt den IP-Adressbereich. Für die Verbindung zwischen den gezeigten VLANs braucht es Routing.</p></section></div>
            <p class="warning-note"><strong>Firewall:</strong> Sie legt erlaubte Verbindungen fest. NAT und VLANs allein bieten keinen vollständigen Schutz. Passende Regeln, Zugriffsrechte, Updates und sichere Endgeräte bleiben erforderlich.</p>
            <button type="button" class="primary action" @click="scenarioKey = 'subnet'; blocked = true; openChapter('journey'); restart()">Eine blockierte Verbindung im Ablauf ansehen</button>
        </section>
        <details class="source-note"><summary>Grundlage und Vertiefung</summary><p>Nach dem Lernmodul „04 – IP-Adressen, Netze und Routing“, Stand 26.10.2026. Beispiele und Animation veranschaulichen die dort beschriebenen Entscheidungen.</p><p><a href="https://www.rfc-editor.org/rfc/rfc2131.html" target="_blank" rel="noopener noreferrer">DHCP · RFC 2131</a> · <a href="https://www.rfc-editor.org/rfc/rfc4632.html" target="_blank" rel="noopener noreferrer">CIDR · RFC 4632</a> · <a href="https://www.rfc-editor.org/rfc/rfc3022.html" target="_blank" rel="noopener noreferrer">NAT · RFC 3022</a> · <a href="https://www.rfc-editor.org/rfc/rfc4861.html" target="_blank" rel="noopener noreferrer">IPv6-Nachbarerkennung · RFC 4861</a></p></details>
    </article>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ipv4Network, matchingRoute, networkScenarios, packetJourney, routerExampleRoutes } from '@/helpers/networkAddressing'

const chapters = [{ key: 'journey', title: 'Der gesamte Ablauf' }, { key: 'mask', title: 'Netzmaske & Subnetze' }, { key: 'addresses', title: 'IPv4 & IPv6' }, { key: 'routing', title: 'Routing' }, { key: 'dhcp', title: 'DHCP' }, { key: 'separation', title: 'NAT & VLAN' }]
const dhcpSteps = [{ title: 'Discover', text: 'Der Client sucht im lokalen Netz nach einem DHCP-Server.' }, { title: 'Offer', text: 'Ein Server bietet Adresse und Einstellungen an.' }, { title: 'Request', text: 'Der Client fordert das ausgewählte Angebot an.' }, { title: 'ACK', text: 'Der Server bestätigt die Zuweisung und ihre Lease.' }]
const route = useRoute()
const router = useRouter()
const chapterKey = ref('journey')
const scenarioKey = ref('internet')
const configuration = ref('dhcp')
const blocked = ref(false)
const stepIndex = ref(0)
const prefix = ref(24)
const labTarget = ref('192.168.20.30')
const routeTarget = ref('192.168.20.30')
const reducedMotion = ref(false)
const steps = computed(() => packetJourney(scenarioKey.value, configuration.value, blocked.value && scenarioKey.value !== 'local'))
const current = computed(() => steps.value[Math.min(stepIndex.value, steps.value.length - 1)])
const ownNetwork = computed(() => ipv4Network('192.168.10.20', prefix.value))
const targetNetwork = computed(() => ipv4Network(labTarget.value, prefix.value))
const sameSubnet = computed(() => ownNetwork.value.network === targetNetwork.value.network)
const bitRows = computed(() => [{ title: 'PC-IP', bits: ownNetwork.value.bits }, { title: 'Maske', bits: ownNetwork.value.maskBits }, { title: 'UND → Netz', bits: ownNetwork.value.networkBits }])
const selectedRoute = computed(() => matchingRoute(routeTarget.value, routerExampleRoutes))

function syncLocation() {
    if (!route || !router) return
    router.replace({ query: { ...route.query, chapter: chapterKey.value, ipScenario: scenarioKey.value,
        ipConfig: configuration.value, ipBlocked: blocked.value ? '1' : undefined,
        ipStep: String(stepIndex.value), ipPrefix: String(prefix.value), ipTarget: labTarget.value }, hash: route.hash })
}
function go(index) {
    stepIndex.value = Math.max(0, Math.min(index, steps.value.length - 1))
    syncLocation()
}
function restart() { go(0) }
function openChapter(key) { chapterKey.value = key; syncLocation() }
function selectScenario(key) { scenarioKey.value = key; blocked.value = false; restart() }
watch(() => route?.query, query => {
    if (!query) return
    const nextChapter = chapters.some(item => item.key === query.chapter) ? query.chapter : 'journey'
    chapterKey.value = nextChapter
    scenarioKey.value = networkScenarios.some(item => item.key === query.ipScenario) ? query.ipScenario : 'internet'
    configuration.value = query.ipConfig === 'static' ? 'static' : 'dhcp'
    blocked.value = query.ipBlocked === '1'
    const index = Number(query.ipStep)
    stepIndex.value = Number.isInteger(index) ? Math.max(0, Math.min(index, steps.value.length - 1)) : 0
    const length = Number(query.ipPrefix)
    prefix.value = [16, 24, 25, 26, 27, 28].includes(length) ? length : 24
    labTarget.value = ['192.168.10.30', '192.168.10.130', '192.168.20.30'].includes(query.ipTarget) ? query.ipTarget : '192.168.20.30'
}, { immediate: true })
onMounted(() => { reducedMotion.value = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || false })
</script>

<style scoped>
.addressing-lesson { max-width: 1320px; margin: auto; color: #20364d; font-size: 1rem; line-height: 1.55; }
.step-progress { display: grid; gap: 4px; margin-bottom: 16px; }
.step-progress span { color: #4b6b83; font-size: .78rem; font-weight: 650; }
.step-progress strong { color: #174c76; font-size: 1.3rem; line-height: 1.2; }
.step-progress progress { width: 100%; height: 8px; margin-top: 4px; appearance: none; border: 0; border-radius: 999px; overflow: hidden; background: #d5e4ef; color: #287ca9; }
.step-progress progress::-webkit-progress-bar { background: #d5e4ef; }
.step-progress progress::-webkit-progress-value { background: #287ca9; border-radius: 999px; }
.step-progress progress::-moz-progress-bar { background: #287ca9; border-radius: 999px; }
.lesson-heading, .section-heading { display: flex; justify-content: space-between; align-items: end; gap: 24px; margin-bottom: 18px; }.lesson-heading h1 { margin: 0; color: #193c68; font-size: clamp(1.45rem, 2.5vw, 2rem); line-height: 1.2; }.lesson-heading > span { color: #61788e; font-size: .8rem; white-space: nowrap; }.eyebrow { margin: 0 0 5px; color: #3870a1; font-size: .76rem; font-weight: 700; }.section-heading h2 { margin: 0; color: #193c68; font-size: 1.6rem; line-height: 1.2; }.section-heading > p { max-width: 47ch; margin: 0; color: #5b7288; font-size: .88rem; }
button, select, input { font: inherit; }button { cursor: pointer; }button:focus-visible, select:focus-visible, input:focus-visible, .bit-lab:focus-visible { outline: 3px solid #397db8; outline-offset: 3px; }button:disabled { cursor: default; opacity: .45; }.chapter-tabs { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 14px; }.chapter-tabs button, .playback-controls button, .action { min-height: 40px; padding: 8px 14px; border: 1px solid #c7d8e7; border-radius: 8px; background: white; color: #285a85; font-weight: 650; }.chapter-tabs button[aria-pressed='true'] { background: #e5f0fa; border-color: #387db4; color: #174c7b; box-shadow: inset 0 -2px #387db4; }.chapter { padding: 24px; border: 1px solid #d8e3ed; border-radius: 16px; background: #fff; }.scenario-choices { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }.scenario-choices button { display: flex; flex-direction: column; gap: 3px; padding: 12px 15px; border: 1px solid #cbdbe8; border-radius: 10px; background: #f9fbfd; color: #2a4e70; text-align: left; }.scenario-choices button[aria-pressed='true'] { background: #e9f4fa; border: 2px solid #377ca4; padding: 11px 14px; }.scenario-choices small { color: #56748c; font-size: .75rem; }.scenario-choices code { font-size: .85rem; }
.journey-options, .lab-controls, .playback-controls { display: flex; align-items: center; flex-wrap: wrap; gap: 14px; margin: 15px 0; }.journey-options label, .lab-controls label, .playback-controls label, .route-picker { color: #4b647b; font-size: .83rem; }select { margin-left: 6px; min-height: 36px; padding: 5px 27px 5px 9px; border: 1px solid #bfcfdd; border-radius: 6px; color: #204a70; background: #fff; appearance: auto; }.check-option { display: flex; align-items: center; gap: 7px; }.check-option input { width: 17px; height: 17px; accent-color: #296c9b; }
.device-config { display: grid; grid-template-columns: 1fr 1.5fr 1fr 1fr; gap: 8px; margin-bottom: 14px; }.device-config > div { min-width: 0; padding: 9px 12px; border: 1px solid #dce6ef; background: #f5f8fb; border-radius: 8px; }.device-config span { display: block; color: #62768a; font-size: .72rem; }.device-config code { color: #234b71; font-size: .8rem; overflow-wrap: anywhere; }.device-config .relevant { background: #e8f6f2; border-color: #3c917b; }
.journey-workbench { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 18px; align-items: stretch; }.network-scene { min-width: 0; padding: 13px; border: 1px solid #d9e7f1; border-radius: 12px; background: #f7fafc; }.network-map { display: block; width: 100%; height: auto; }.vlan-zone { fill: #e9f3fb; stroke: #c0d6e8; stroke-dasharray: 6 4; }.server-zone { fill: #eaf5f0; stroke: #b8d8c7; }.zone-title { fill: #4c6e89; font: 600 14px sans-serif; }.network-links { fill: none; stroke: #afc4d6; stroke-width: 3; }.device rect { fill: #fff; stroke: #a6bfd4; stroke-width: 2; }.device text { text-anchor: middle; font: 650 19px sans-serif; fill: #254e72; }.device text.small { font-size: 13px; font-weight: 400; }.device.active rect { fill: #d6edf6; stroke: #2d7aa5; stroke-width: 3; }.router rect { fill: #e6f2ec; stroke: #8cbba7; }.device.router.active rect { fill: #cceadd; stroke: #287a60; }.interface-label { font: 13px sans-serif; fill: #56738a; }.provider rect { fill: #f1edf9; stroke: #bbaccd; }.active-path { fill: none; stroke: #258eaa; stroke-width: 5; stroke-linecap: round; }.moving-packet { fill: #e4a344; stroke: #fff; stroke-width: 3; }.diagram-caption { margin: 5px 0 12px; font-size: .73rem; line-height: 1.45; color: #677d90; }
.packet-ledger { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 12px 0 8px; }.packet-ledger > div { padding: 8px 10px; background: white; border-left: 3px solid #91b9d8; border-radius: 5px; }.packet-ledger dt { font-size: .7rem; color: #62768a; }.packet-ledger dd { margin: 3px 0 0; color: #244d72; font: 600 .8rem/1.5 monospace; overflow-wrap: anywhere; }
.step-explanation { min-width: 0; padding: 20px; border: 1px solid #d5e3ef; border-radius: 12px; background: #f0f6fc; }.step-explanation h3 { margin: 0 0 10px; color: #1c4e7b; font-size: 1.3rem; line-height: 1.3; }.step-explanation > p:not(.eyebrow) { margin: 0; font-size: .94rem; }.reason { padding-top: 14px; margin-top: 14px; border-top: 1px solid #cfdeeb; }.reason strong { color: #2d627f; font-size: .8rem; }.reason p { margin: 5px 0 0; font-size: .86rem; }.concepts { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 14px; }.concepts span { padding: 3px 8px; border-radius: 5px; background: #d9eaf7; color: #285a82; font-size: .72rem; font-weight: 700; }.step-explanation.failure { background: #fff1ed; border-color: #d49a86; }.step-explanation.finished { background: #edf7f1; border-color: #91c1a4; }.net-comparison { display: grid; gap: 5px; margin-top: 15px; padding: 10px; background: #fff; border-radius: 7px; font-size: .76rem; overflow-wrap: anywhere; }.nat-record { display: grid; gap: 6px; margin-top: 15px; padding: 12px; border: 1px solid #dfc99f; border-radius: 8px; background: #fff7e9; font-size: .78rem; }.nat-record code, .nat-record small { overflow-wrap: anywhere; }.playback-controls .primary, .primary.action { background: #296c9b; border-color: #296c9b; color: #fff; }.step-timeline { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 5px 10px; list-style: none; margin: 16px 0 0; padding: 0; }.step-timeline button { display: flex; align-items: start; gap: 7px; width: 100%; padding: 8px; border: 1px solid transparent; background: transparent; color: #5a7288; text-align: left; font-size: .73rem; border-radius: 6px; }.step-timeline button[aria-current='step'] { color: #174b76; border-color: #8cb6d4; background: #e9f3fa; font-weight: 700; }.step-timeline span { display: grid; place-items: center; min-width: 20px; height: 20px; border-radius: 50%; background: #e1eaf2; font-size: .65rem; }.footnote, .source-note { color: #687d90; font-size: .78rem; }.footnote { margin: 17px 0 0; }
.mask-value { color: #226b5c; }.bit-lab { padding: 20px; background: #f0f6fa; border: 1px solid #d3e2ed; border-radius: 10px; overflow-x: auto; }.bit-row { display: flex; align-items: center; gap: 12px; min-width: 730px; margin-bottom: 8px; }.bit-row > span { width: 95px; flex: none; color: #46637b; font-size: .8rem; }.bit-row code { display: flex; }.bit-row b { display: block; min-width: 17px; text-align: center; color: #136c5c; background: #d7eee4; font-size: .86rem; }.bit-row b.host { background: #e3e5f6; color: #655397; }.bit-row b.octet-start { margin-left: 8px; }.bit-key { display: flex; gap: 30px; margin: 14px 0 0 105px; font-size: .8rem; color: #30786b; }.bit-key span + span { color: #655397; }.lab-results, .knowledge-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }.lab-results section, .knowledge-grid section { min-width: 0; padding: 20px; border: 1px solid #d9e5ee; border-radius: 10px; background: #f8fafc; }h3 { margin: 0 0 10px; color: #234f77; font-size: 1.15rem; }h4 { margin: 18px 0 7px; color: #365f7c; }.lab-results p, .knowledge-grid p { font-size: .92rem; margin: 12px 0 0; }.lab-decision { color: #1e725e; font-weight: 700; }.warning-note { margin: 20px 0 0; padding: 14px 18px; border-left: 4px solid #c2954d; background: #fff8eb; color: #685334; font-size: .9rem; }.subnet-demo { margin-top: 24px; }.subnet-demo p { color: #587087; font-size: .88rem; }.subnet-blocks { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }.subnet-blocks > div { display: grid; gap: 6px; padding: 13px; border: 1px solid #b8d3c7; background: #eff7f2; border-radius: 9px; font-size: .78rem; }.subnet-blocks .contains { border: 2px solid #2f8a70; }.subnet-blocks small { color: #59776d; }.big-address { display: block; color: #215981; font-size: 1.2rem; overflow-wrap: anywhere; }.expanded-address { font-size: .85rem; overflow-wrap: anywhere; }table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: .83rem; }th, td { padding: 10px; border-bottom: 1px solid #d3e0e9; text-align: left; }th { color: #315876; background: #e8f1f8; }caption { text-align: left; color: #677e91; font-size: .75rem; margin: 5px 0; }.route-table .chosen { background: #dcf0e7; font-weight: 650; }.route-table td code { white-space: nowrap; }.step-explanation .route-table { font-size: .7rem; }.step-explanation .route-table th, .step-explanation .route-table td { padding: 6px; }.dhcp-sequence { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; padding-left: 0; list-style: none; counter-reset: dhcp; }.dhcp-sequence li { counter-increment: dhcp; padding: 18px; border-top: 3px solid #3980ac; background: #eef5fb; border-radius: 6px; }.dhcp-sequence strong { display: block; color: #245c84; }.dhcp-sequence strong::before { content: counter(dhcp) '. '; }.dhcp-sequence span { display: block; margin-top: 8px; font-size: .9rem; }.action { margin-top: 20px; }.source-note { margin-top: 18px; }.source-note summary { cursor: pointer; }.source-note a { color: #2b6797; }
@media (max-width: 1000px) { .journey-workbench { grid-template-columns: 1fr; }.network-map { max-height: 350px; }.step-timeline { grid-template-columns: repeat(2, minmax(0, 1fr)); }.lesson-heading > span { display: none; }.device-config { grid-template-columns: 1fr 1fr; } }
@media (max-width: 650px) { .chapter { padding: 16px 12px; }.section-heading { flex-direction: column; align-items: start; gap: 8px; }.scenario-choices { grid-template-columns: 1fr; }.scenario-choices button { display: grid; grid-template-columns: 1fr auto; }.scenario-choices small { grid-column: 1 / -1; }.knowledge-grid, .lab-results { grid-template-columns: 1fr; }.step-timeline { grid-template-columns: 1fr; }.subnet-blocks, .dhcp-sequence { grid-template-columns: 1fr 1fr; }.network-scene { padding: 5px; }.device-config code { font-size: .72rem; }.packet-ledger dd { font-size: .72rem; }.step-explanation { padding: 16px; }.chapter-tabs button { flex: 1 1 30%; font-size: .75rem; padding: 6px; }.route-table { font-size: .73rem; }.subnet-blocks code { font-size: .7rem; } }
@media (prefers-reduced-motion: reduce) { .moving-packet { display: none; } }
</style>
