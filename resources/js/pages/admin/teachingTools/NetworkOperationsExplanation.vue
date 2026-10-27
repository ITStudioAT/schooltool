<template>
    <article class="operations-lesson" aria-labelledby="operations-title">
        <header class="lesson-header">
            <div><p class="eyebrow">Netzwerke verstehen</p><h1 id="operations-title">Netzwerke planen, betreiben und Störungen erkennen</h1></div>
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
                    <svg v-if="selectedDiagram" class="topology-diagram" viewBox="0 0 380 190" role="img" :aria-label="selected.diagramLabel">
                        <line v-for="(edge, index) in selectedDiagram.edges" :key="`edge-${index}`" :x1="edge[0]" :y1="edge[1]" :x2="edge[2]" :y2="edge[3]" />
                        <g v-for="node in selectedDiagram.nodes" :key="node.label" :transform="`translate(${node.x}, ${node.y})`">
                            <rect x="-37" y="-17" width="74" height="34" rx="7" />
                            <text text-anchor="middle" dominant-baseline="middle">{{ node.label }}</text>
                        </g>
                    </svg>
                    <div v-else class="network-flow" role="img" :aria-label="selected.flow.join(' → ')">
                        <template v-for="(part, index) in selected.flow" :key="index">
                            <span>{{ part }}</span><b v-if="index < selected.flow.length - 1" aria-hidden="true">→</b>
                        </template>
                    </div>
                    <p v-if="selectedDiagram" class="diagram-caption">{{ selected.diagramLabel }}</p>
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
                    <div v-if="selected.tools" class="example tool-list">
                        <strong>Konkrete Werkzeuge</strong>
                        <ul>
                            <li v-for="tool in selected.tools" :key="tool.name">
                                <a :href="tool.url" target="_blank" rel="noopener noreferrer">{{ tool.name }}</a>
                                <p>{{ tool.description }}</p>
                                <code v-if="tool.command">{{ tool.command }}</code>
                            </li>
                        </ul>
                    </div>
                    <div v-if="selected.command" class="command-example"><span>Windows-Befehl zum Nachlesen</span><code>{{ selected.command }}</code></div>
                    <p class="note">{{ selected.note }}</p>
                    <div v-if="selected.example" class="example"><strong>Beispiel im Schulnetz</strong><p>{{ selected.example }}</p></div>
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
            <p>Nach dem Lernmodul „Netzwerke planen, betreiben und Störungen erkennen“ von Dipl.-Ing. Günther Kron (26.10.2026).</p>
            <a v-for="source in sources" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer">{{ source.title }}</a>
        </details>
    </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const diagrams = {
    star: {
        nodes: [{ label: 'Switch', x: 190, y: 95 }, { label: 'PC 1', x: 50, y: 35 }, { label: 'PC 2', x: 330, y: 35 }, { label: 'PC 3', x: 50, y: 155 }, { label: 'PC 4', x: 330, y: 155 }],
        edges: [[190, 95, 50, 35], [190, 95, 330, 35], [190, 95, 50, 155], [190, 95, 330, 155]],
    },
    tree: {
        nodes: [{ label: 'Zentrale', x: 190, y: 25 }, { label: 'Etage 1', x: 95, y: 90 }, { label: 'Etage 2', x: 285, y: 90 }, { label: 'PC 1', x: 45, y: 160 }, { label: 'PC 2', x: 145, y: 160 }, { label: 'PC 3', x: 235, y: 160 }, { label: 'PC 4', x: 335, y: 160 }],
        edges: [[190, 25, 95, 90], [190, 25, 285, 90], [95, 90, 45, 160], [95, 90, 145, 160], [285, 90, 235, 160], [285, 90, 335, 160]],
    },
    bus: {
        nodes: [{ label: 'Gerät 1', x: 50, y: 55 }, { label: 'Gerät 2', x: 145, y: 55 }, { label: 'Gerät 3', x: 235, y: 55 }, { label: 'Gerät 4', x: 330, y: 55 }],
        edges: [[25, 130, 355, 130], [50, 55, 50, 130], [145, 55, 145, 130], [235, 55, 235, 130], [330, 55, 330, 130]],
    },
    ring: {
        nodes: [{ label: 'Knoten 1', x: 80, y: 45 }, { label: 'Knoten 2', x: 300, y: 45 }, { label: 'Knoten 3', x: 300, y: 145 }, { label: 'Knoten 4', x: 80, y: 145 }],
        edges: [[80, 45, 300, 45], [300, 45, 300, 145], [300, 145, 80, 145], [80, 145, 80, 45]],
    },
    mesh: {
        nodes: [{ label: 'Knoten 1', x: 80, y: 40 }, { label: 'Knoten 2', x: 300, y: 40 }, { label: 'Knoten 3', x: 300, y: 150 }, { label: 'Knoten 4', x: 80, y: 150 }],
        edges: [[80, 40, 300, 40], [300, 40, 300, 150], [300, 150, 80, 150], [80, 150, 80, 40], [80, 40, 300, 150], [300, 40, 80, 150]],
    },
}

const pages = [
    {
        key: 'planning', label: 'Planung', kicker: 'Vom Bedarf zum Netzwerk', title: 'Was wird wo gebraucht?',
        intro: 'Menschen, Räume, Geräte und Anwendungen bestimmen die Anforderungen. Verbindungen sollen verfügbar und Zugriffe angemessen geschützt sein.',
        caption: 'Die Internetverbindung allein bestimmt nicht die Leistung des gesamten Netzes. Auch Funk, Kabel, Geräte und Anwendungen wirken mit.',
        items: [
            { name: 'Nutzung und Dienste', short: 'wer nutzt was gleichzeitig?', flow: ['Menschen und Geräte', 'gleichzeitige Nutzung', 'benötigte Dienste'], description: 'Plane, wie viele Personen und Geräte gleichzeitig arbeiten und welche Dienste gebraucht werden, etwa Web, Dateiablage und Druck.', note: 'Nicht alle Geräte übertragen gleichzeitig mit voller Geschwindigkeit. Entscheidend sind die tatsächlichen Anwendungen.', example: 'Ein einzelner Computerraum stellt andere Anforderungen als ein Schulgebäude mit mehreren Stockwerken.' },
            { name: 'Gebäude und Versorgung', short: 'Räume, Wege, Funk und Strom', flow: ['Räume und Stockwerke', 'Kabelwege und Strom', 'WLAN-Abdeckung'], description: 'Berücksichtige Räume, Stockwerke, Kabelwege, Stromversorgung und die Bereiche, in denen WLAN gebraucht wird.', note: 'Eine schnelle Verbindung hilft einem Gerät wenig, wenn an seinem Standort keine brauchbare Funkabdeckung oder kein passender Anschluss vorhanden ist.' },
            { name: 'Leistung und Schutz', short: 'Anwendungen und Zugriffsregeln', flow: ['Datenmengen / Anwendungen', 'Verbindungskapazität', 'geschützte Nutzung'], description: 'Die erwarteten Datenmengen und Anwendungen beeinflussen die nötige Leistung. Plane zugleich Zugriffsrechte, getrennte Netzbereiche und den Schutz von Verwaltungsdaten.', note: 'Videokonferenzen, Dateiübertragungen und einfache Webseiten können unterschiedliche Anforderungen stellen.' },
            { name: 'Betrieb und Erweiterung', short: 'Wartung, Ausfälle und Mittel', flow: ['heutiger Bedarf', 'Wartung und Erweiterung', 'langfristiger Betrieb'], description: 'Erweiterbarkeit, Wartung, Ausfallsicherheit und verfügbare finanzielle Mittel gehören schon zur Planung.', note: 'Ein Netz muss auch nach seiner Einrichtung gepflegt und bei verändertem Bedarf angepasst werden.' },
        ],
    },
    {
        key: 'topologies', label: 'Topologien', kicker: 'Verbindungen sichtbar machen', title: 'Wie sind die Geräte verbunden?',
        intro: 'Die physische Topologie zeigt Geräte und Leitungen. Die logische Topologie beschreibt die Organisation der Kommunikation; beide können sich unterscheiden.',
        caption: 'Die Bilder sind vereinfachte Verbindungsbilder. In Gebäuden sind Sternstrukturen und hierarchische Verkabelungen häufig.',
        items: [
            { name: 'Stern', short: 'ein zentraler Switch', diagram: 'star', diagramLabel: 'Vier Computer sind jeweils direkt mit dem zentralen Switch verbunden.', description: 'Jedes Endgerät hat eine eigene Verbindung zum zentralen Gerät, meist einem Switch. Fällt eine einzelne Leitung aus, sind die anderen Leitungen normalerweise nicht betroffen.', note: 'Der zentrale Switch ist ein gemeinsamer Ausfallpunkt. Fällt er aus, sind alle an ihn angeschlossenen Geräte betroffen.' },
            { name: 'Baum', short: 'mehrere Ebenen', diagram: 'tree', diagramLabel: 'Ein zentraler Switch verbindet zwei Etagen-Switches mit jeweils zwei Computern.', description: 'Mehrere Sternstrukturen werden hierarchisch verbunden, etwa Etagen-Switches mit einem zentralen Switch.', note: 'Gemeinsame Verbindungen müssen zur erwarteten Nutzung passen. Ihr Ausfall kann mehrere untergeordnete Geräte betreffen.' },
            { name: 'Bus', short: 'ein gemeinsames Medium', diagram: 'bus', diagramLabel: 'Vier Geräte sind an ein gemeinsames Übertragungsmedium angeschlossen.', description: 'Geräte teilen sich ein gemeinsames Übertragungsmedium. Diese ältere Struktur ist empfindlich gegenüber Störungen am gemeinsamen Medium.', note: 'Eine Störung des gemeinsamen Mediums kann sich auf mehrere Geräte auswirken.' },
            { name: 'Ring', short: 'ringförmige Verbindung', diagram: 'ring', diagramLabel: 'Vier Knoten sind zu einem geschlossenen Ring verbunden.', description: 'Geräte sind ringförmig miteinander verbunden. Wie sich eine Unterbrechung auswirkt, hängt von der verwendeten Technik und vorhandenen Ersatzwegen ab.', note: 'Aus der Ringform allein lässt sich die Wirkung eines Ausfalls nicht sicher ableiten.' },
            { name: 'Mesh', short: 'mehrere mögliche Wege', diagram: 'mesh', diagramLabel: 'Vier Knoten sind in diesem vollständig vermaschten Beispiel über mehrere Wege verbunden.', description: 'Mehrere Knoten sind über mehrere Wege miteinander verbunden. Zusätzliche Wege können die Ausfallsicherheit verbessern; Einrichtung und Verwaltung sind aufwendiger.', note: 'Das Bild zeigt ein vollständig vermaschtes Beispiel. Nicht jedes Mesh verbindet jeden Knoten direkt mit jedem anderen. Auch in einem WLAN-Mesh muss nicht jede Verbindung drahtlos sein.' },
        ],
    },
    {
        key: 'building', label: 'Gebäudeaufbau', kicker: 'Vom Anschluss bis zum Endgerät', title: 'Welchen Weg nimmt die Verbindung?',
        intro: 'Zentrale Geräte, Etagen-Switches, feste Leitungen und Access Points verbinden die Bereiche eines Gebäudes.',
        caption: 'Der gezeigte Weg ist vereinfacht. Geräte und Verbindungen hängen vom konkreten Aufbau ab.',
        items: [
            { name: 'Internet und Zentrale', short: 'Router, Firewall und Switch', flow: ['Internetanschluss', 'Router und Firewall', 'zentraler Switch'], description: 'Vom Internetanschluss führt ein vereinfachter Weg über Router und Firewall zum zentralen Switch. Von dort gehen Verbindungen in weitere Netzbereiche.', note: 'Die Aufgaben können je nach Aufbau auf unterschiedliche Geräte verteilt oder in einem Gerät kombiniert sein.' },
            { name: 'Backbone und Etage', short: 'wichtige Netzteile verbinden', flow: ['zentraler Switch', 'Uplink / Backbone', 'Etagen-Switch'], description: 'Der Backbone verbindet wichtige Teile des Netzwerks leistungsfähig miteinander. Ein Etagen-Switch verbindet die Geräte eines Bereichs und ist über einen Uplink an das übrige Netz angeschlossen.', note: 'Eine gemeinsame Uplink-Verbindung muss zur erwarteten gleichzeitigen Nutzung passen.' },
            { name: 'Dose und Access Point', short: 'Kabel und drahtlose Geräte', flow: ['Etagen-Switch', 'Netzwerkdose / Access Point', 'Computer / WLAN-Gerät'], description: 'Ein Computer kann über eine Netzwerkdose und die feste Verkabelung am Switch angeschlossen sein. Ein Access Point kann über einen Switch drahtlosen Geräten Zugang zum lokalen Netz ermöglichen.', note: 'Die Verbindung eines WLAN-Geräts zum Access Point und der Anschluss des Access Points sind verschiedene Abschnitte des Weges.' },
            { name: 'Schrank und Patchpanel', short: 'feste Leitungen zuordnen', flow: ['fest verlegte Leitung', 'Patchpanel', 'Patchkabel zum Switch'], description: 'Ein Netzwerkschrank kann Patchpanel, Switches, Stromversorgung und weitere Geräte aufnehmen. Das Patchpanel ordnet die fest verlegten Leitungen den Anschlüssen im Schrank zu. Kurze Patchkabel verbinden sie mit Switch-Ports.', note: 'Eine USV kann bei Stromausfall begrenzt Energie bereitstellen. Ihre Laufzeit hängt von Kapazität und angeschlossenen Geräten ab.' },
            { name: 'Netzwerkplan', short: 'Aufbau und Zuständigkeiten', flow: ['Geräte und Standorte', 'Verbindungen / Netzbereiche', 'geschützte Dokumentation'], description: 'Ein Netzwerkplan dokumentiert Standorte, Geräte, Verbindungen, Netzbereiche und benötigte Dienste. Auch Änderungen und Zuständigkeiten gehören dazu.', note: 'Netzwerkpläne können vertrauliche Informationen enthalten und brauchen angemessenen Zugriffsschutz.' },
        ],
    },
    {
        key: 'operations', label: 'Betrieb', kicker: 'Einrichten, pflegen und erweitern', title: 'Was hält das Netzwerk nutzbar?',
        intro: 'Im Betrieb zählen technische Verfügbarkeit, Sicherheit und Datenschutz gemeinsam.',
        caption: 'Eine Maßnahme wirkt nur so gut wie ihre konkrete Umsetzung. Auch der Rückweg nach Änderungen muss bedacht werden.',
        items: [
            { name: 'Überwachung', short: 'Meldungen und Messwerte', flow: ['Meldungen / Messwerte', 'Ausfälle und Last erkennen', 'gezielt reagieren'], description: 'Meldungen und Messwerte können Ausfälle, hohe Auslastung oder ungewöhnliche Vorgänge sichtbar machen.', note: 'Auswahl und Aufbewahrung der Daten müssen zu Zweck und Berechtigung passen.' },
            { name: 'Geplante Updates', short: 'Unterbrechung und Rückweg', flow: ['Änderung planen', 'Firmware / Software aktualisieren', 'Betrieb kontrollieren'], description: 'Firmware und Software erhalten Fehlerbehebungen und Sicherheitsupdates. Änderungen werden geplant, damit mögliche Unterbrechungen und ein Rückweg berücksichtigt werden.', note: 'Die Planung hilft, Auswirkungen auf den laufenden Betrieb zu berücksichtigen.' },
            { name: 'Sicherung und Dokumentation', short: 'Wiederherstellung vorbereiten', flow: ['Konfigurationen und Daten', 'geschützte Sicherung', 'Wiederherstellung'], description: 'Gesicherte Konfigurationen und Daten helfen bei der Wiederherstellung. Geräte, Anschlüsse, Änderungen und verantwortliche Stellen werden nachvollziehbar dokumentiert.', note: 'Eine Sicherung allein verhindert keinen Ausfall. Zugriff, Aufbewahrung und Wiederherstellbarkeit sind ebenfalls wichtig.' },
            { name: 'Ausfallsicherheit', short: 'Auswirkungen begrenzen', flow: ['einzelner Ausfall', 'Ersatz oder redundanter Weg', 'Auswirkung verringern'], description: 'Ersatzgeräte, redundante Verbindungen oder eine passende Stromversorgung können die Auswirkungen einzelner Ausfälle reduzieren.', note: 'Ob ein Ersatzweg oder eine zusätzliche Stromversorgung hilft, hängt von der konkreten Umsetzung ab.' },
        ],
    },
    {
        key: 'performance', label: 'Leistung', kicker: 'Mehr als die Internetgeschwindigkeit', title: 'Schnell heißt nicht immer dasselbe',
        intro: 'Kapazität, tatsächliche Datenrate, Verzögerung, Schwankungen und Paketverlust beschreiben verschiedene Eigenschaften.',
        caption: 'Ein einzelner Messwert beschreibt nicht alle Verbindungen. Messpunkt, Gerät, Server und aktuelle Last beeinflussen das Ergebnis.',
        items: [
            { name: 'Kapazität und Datenrate', short: 'möglich und tatsächlich erreicht', flow: ['Verbindungskapazität', 'Auslastung / Geräte / Anwendung', 'erreichte Datenrate'], description: 'Bandbreite bezeichnet im Netzwerkalltag häufig die Übertragungskapazität. Die tatsächlich erreichte Datenrate kann darunter liegen. Technisch kann Bandbreite auch die Breite eines Frequenzbereichs in Hertz meinen.', note: 'Eine lange Dateiübertragung kann durch eine ausgelastete Verbindung, schwaches WLAN, Serverlast oder langsamen Speicher entstehen.' },
            {
                name: 'Latenz und Jitter', short: 'Wie lange? Wie gleichmäßig?', flow: ['Datenpakete senden', 'unterschiedlich lange unterwegs', 'unregelmäßige Ankunft'],
                description: 'Latenz ist die Zeit, die ein Datenpaket vom Sender bis zum Empfänger braucht. Je länger diese Zeit ist, desto später kommen die übertragenen Daten an.',
                note: 'Jitter bedeutet, dass die Laufzeit von Paket zu Paket schwankt. Beispiel: Drei nacheinander gesendete Pakete brauchen 20, 80 und 30 Millisekunden. Eine Millisekunde ist eine Tausendstelsekunde. Bei gleichbleibender Laufzeit gibt es keinen Jitter, auch wenn die Latenz hoch ist.',
                example: 'Bei einem Videogespräch hörst du die andere Person bei hoher Latenz mit Verzögerung. Starker Jitter lässt Tonpakete unregelmäßig ankommen; der Ton kann dadurch stocken. Eine hohe Datenrate allein beseitigt diese Probleme nicht.',
            },
            { name: 'Paketverlust und WLAN', short: 'fehlende Pakete und Funkbedingungen', flow: ['Funk / Verbindung', 'Störung oder Überlastung', 'Pakete kommen nicht an'], description: 'Paketverlust bedeutet, dass einzelne Datenpakete ihr Ziel nicht erreichen. Bei langsamen Verbindungen in einzelnen Räumen können auch Abdeckung, Hindernisse, Funkstörungen oder viele aktive Geräte eine Rolle spielen.', note: 'Stockendes Bild oder Ton ist ein Hinweis mit mehreren möglichen Ursachen und keine eindeutige Diagnose.' },
            {
                name: 'Quality of Service', short: 'wichtige Daten zuerst', flow: ['Videogespräch und Download', 'Tonpakete zuerst', 'Download wartet bei Bedarf'],
                description: 'Wenn ein Router mehr Daten weiterleiten soll, als eine Verbindung gerade schafft, müssen manche Daten warten. Mit Quality of Service (QoS) kann er zeitkritische Daten bevorzugen: zum Beispiel den Ton eines Videogesprächs vor den Daten eines großen Downloads.',
                note: 'QoS erzeugt keine zusätzliche Internetbandbreite: Die Leitung kann dadurch insgesamt nicht mehr Daten übertragen. Reicht ihre Kapazität selbst für die bevorzugten Daten nicht aus, kann auch QoS Störungen nicht verhindern.',
                example: 'Während einer Videokonferenz lädt jemand eine große Datei herunter. Ohne passende Bevorzugung können die Tonpakete in der Warteschlange hängen bleiben. QoS kann sie früher weiterleiten. Der Download dauert dann möglicherweise etwas länger, dafür kann das Gespräch flüssiger bleiben.',
            },
        ],
    },
    {
        key: 'troubleshooting', label: 'Störungssuche', kicker: 'Den Umfang eingrenzen', title: 'Wer oder was ist betroffen?',
        intro: 'Ein Symptom kann mehrere Ursachen haben. Beginne mit dem Umfang: ein Gerät, ein Raum, mehrere Bereiche oder nur ein Dienst?',
        caption: 'Diese Zusammenhänge sind Hinweise, keine eindeutigen Diagnosen. Zuständige Personen prüfen Informationen, bevor sie produktive Geräte ändern.',
        items: [
            { name: 'Ein Computer', short: 'keine Netzwerkverbindung', flow: ['ein Gerät betroffen', 'Anschluss und Verbindung', 'Einstellungen prüfen'], description: 'Mögliche Ansatzpunkte sind Netzwerkkabel, Anschluss, WLAN-Verbindung, Netzwerkkarte und Netzwerkeinstellungen.', note: 'Aus dem Symptom allein folgt nicht, welches Bauteil oder welche Einstellung die Ursache ist.' },
            { name: 'Lokal geht, Web nicht', short: 'lokales Netz und Internet trennen', flow: ['lokale Dienste funktionieren', 'Internet / DNS / Router', 'entfernter Dienst'], description: 'Wenn lokale Dienste funktionieren, Webseiten aber nicht, kommen Internetzugang, Router, DNS-Auflösung oder der entfernte Dienst als Ansatzpunkte infrage.', note: 'Ein funktionierendes lokales Netz beweist keinen funktionierenden Internetzugang.' },
            { name: 'Nur eine Website', short: 'andere Seiten funktionieren', flow: ['einzelne Website betroffen', 'DNS oder Verbindung', 'betroffener Webdienst'], description: 'Wenn andere Websites funktionieren, untersuche die betroffene Website, ihre Namensauflösung oder eine spezifische Verbindung.', note: 'Eine einzelne nicht erreichbare Website bedeutet nicht automatisch einen allgemeinen Internetausfall.' },
            { name: 'Mehrere Geräte im Raum', short: 'gemeinsame Komponenten', flow: ['mehrere Geräte betroffen', 'Access Point / Switch / Uplink', 'gemeinsame Versorgung'], description: 'Sind mehrere Geräte eines Bereichs betroffen, sind gemeinsame Komponenten mögliche Ansatzpunkte: Access Point, Switch, Uplink oder Stromversorgung.', note: 'Suche nach gemeinsamen Verbindungen und Komponenten. Ein gemeinsames Symptom legt eine Ursache nahe, beweist sie aber nicht.' },
        ],
    },
    {
        key: 'tools', label: 'Werkzeuge', kicker: 'Gezielt messen und nachsehen', title: 'Welche Frage beantwortet das Werkzeug?',
        intro: 'Windows-Befehle und Paketanalysen zeigen verschiedene Teile der Verbindung. Ausgaben können vertrauliche Geräte- und Netzdaten enthalten.',
        caption: 'Hier werden die Werkzeuge erklärt. Aufzeichnungen und Diagnose im echten Netz setzen die nötige Berechtigung und die geltenden Regeln voraus.',
        items: [
            { name: 'ipconfig', short: 'Adapterkonfiguration anzeigen', flow: ['Netzwerkadapter', 'Konfiguration anzeigen', 'IP / Maske / Gateway'], command: 'ipconfig /all', description: 'ipconfig zeigt unter anderem IP-Adressen, Subnetzmaske und Standardgateway der Netzwerkadapter. ipconfig /all liefert weitere Konfigurationsdaten.', note: 'Eine angezeigte Konfiguration beweist für sich allein nicht, dass der gewünschte Dienst erreichbar ist. Behandle die Ausgabe vertraulich.' },
            { name: 'ping', short: 'ICMP-Antwort und Laufzeit', flow: ['ICMP-Echoanfrage', 'Zielgerät', 'Antwort und Laufzeit'], command: 'ping <Zieladresse>', description: 'ping sendet ICMP-Echoanfragen und zeigt bei einer Antwort unter anderem eine Laufzeit an.', note: 'Eine fehlende Antwort beweist keinen Ausfall: ICMP kann gefiltert sein. Eine Antwort bestätigt wiederum nicht die Funktion jedes Dienstes.' },
            { name: 'tracert', short: 'mögliche Zwischenstationen', flow: ['Diagnosepakete', 'antwortende Zwischenstationen', 'Weg zum Ziel'], command: 'tracert <Zieladresse>', description: 'tracert zeigt anhand von Antworten auf Diagnosepakete mögliche Zwischenstationen auf dem Weg zum Ziel.', note: 'Einzelne Stationen können Antworten unterdrücken. Der angezeigte Weg ist deshalb nicht immer vollständig.' },
            { name: 'nslookup', short: 'DNS getrennt untersuchen', flow: ['Name / Adresse', 'DNS-Server abfragen', 'DNS-Informationen'], command: 'nslookup <Domainname>', description: 'nslookup fragt einen DNS-Server nach Informationen zu Namen oder Adressen. Damit lässt sich die Namensauflösung getrennt von anderen Ursachen betrachten.', note: 'Eine erfolgreiche DNS-Antwort beweist nicht, dass der Webserver oder ein anderer Dienst funktioniert.' },
            { name: 'Wireshark', short: 'aufgezeichnete Pakete ansehen', flow: ['berechtigte Aufzeichnung', 'Pakete darstellen', 'Datenverkehr analysieren'], description: 'Wireshark stellt aufgezeichneten Netzwerkverkehr als Pakete dar. Inhalt und Metadaten können vertraulich sein.', note: 'Eine Aufzeichnung setzt Berechtigung und Beachtung der geltenden Regeln voraus. Teile Ausgaben nicht ungeprüft.' },
            {
                name: 'WLAN- und Geschwindigkeitstest', short: 'Funk und Internet getrennt prüfen', flow: ['Werkzeug auswählen', 'an mehreren Stellen messen', 'Ergebnisse vergleichen'],
                description: 'Ein Internet-Geschwindigkeitstest misst die Verbindung deines Geräts zu einem Testserver. Eine WLAN-Analyse zeigt dagegen Eigenschaften der Funkverbindung, etwa Signalstärke und Funkkanal. Für die Fehlersuche sind beide Sichtweisen hilfreich.',
                tools: [
                    {
                        name: 'RTR-Netztest: Internetgeschwindigkeit messen', url: 'https://www.netztest.at/',
                        description: 'Im Browser öffnen und den Test starten. Angezeigt werden unter anderem Download, Upload und Ping. Es gibt auch Apps. Der Test misst den gesamten Weg zum Testserver; ein schlechtes Ergebnis beweist deshalb noch kein WLAN-Problem.',
                    },
                    {
                        name: 'FRITZ!App WLAN: Funkverbindung untersuchen', url: 'https://fritz.com/pages/fritz-app-wlan',
                        description: 'Mit dem Smartphone an verschiedenen Stellen die WLAN-Verbindung vergleichen. Unter Android zeigt die App unter anderem Signalstärke und Funkkanäle der gefundenen Netze. Die iOS-Version bietet weniger Funktionen. Die verfügbaren Verbindungstests helfen, schwache Standorte zu finden.',
                    },
                    {
                        name: 'Windows: WLAN-Informationen ohne Zusatzprogramm', url: 'https://learn.microsoft.com/en-us/troubleshoot/windows-client/networking/wireless-network-connectivity-issues-troubleshooting',
                        description: 'In der Eingabeaufforderung den folgenden Befehl eingeben. Er zeigt sichtbare WLANs mit Informationen wie Signalstärke und Kanal. Diese Anzeige ist kein Internet-Geschwindigkeitstest. Verfügbare Angaben hängen auch von Adapter und Freigaben ab.',
                        command: 'netsh wlan show networks mode=bssid',
                    },
                ],
                note: 'An mehreren Standorten und zu verschiedenen Zeiten messen. Messpunkt, Endgerät, Testserver und gleichzeitige Nutzung beeinflussen die Werte. Ein einzelner Messwert beschreibt nicht alle Verbindungen des Netzwerks.',
                example: 'Teste mit demselben Notebook nahe am Access Point und in einem weiter entfernten Raum. Vergleiche anschließend mit einer Messung über ein LAN-Kabel am Router. Ist LAN deutlich schneller, ist die Funkverbindung ein möglicher Engpass; die Ursache muss weiter eingegrenzt werden.',
            },
        ],
    },
]

const sources = [
    { title: 'Cisco: Netzwerktopologien', url: 'https://www.cisco.com/site/us/en/learn/topics/networking/what-is-network-topology.html' },
    { title: 'Microsoft: DNS- und Verbindungsdiagnose', url: 'https://learn.microsoft.com/windows-server/networking/dns/troubleshoot/troubleshoot-dns-client' },
    { title: 'CISA: Netzpläne und Sicherheitsmaßnahmen', url: 'https://www.cisa.gov/stopransomware/ransomware-guide' },
    { title: 'RTR: Was der Netztest misst', url: 'https://www.rtr.at/TKP/service/rtr-nettest/help/general/links/netztestfaq_allgemein_0400.de.html' },
    { title: 'FRITZ!: WLAN analysieren und messen', url: 'https://fritz.com/pages/fritz-app-wlan' },
    { title: 'Microsoft: WLAN-Diagnose unter Windows', url: 'https://learn.microsoft.com/en-us/troubleshoot/windows-client/networking/wireless-network-connectivity-issues-troubleshooting' },
]

const route = useRoute()
const router = useRouter()
const pageIndex = ref(0)
const selection = ref(pages[0].items[0].name)
const currentPage = computed(() => pages[pageIndex.value])
const selected = computed(() => currentPage.value.items.find(item => item.name === selection.value) || currentPage.value.items[0])
const selectedDiagram = computed(() => diagrams[selected.value.diagram] || null)

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
.operations-lesson { max-width: 1200px; margin: 0 auto; color: #1b3049; font-size: 1rem; line-height: 1.45; }
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
.example, .command-example { margin-top: 14px; padding: 10px 12px; border-left: 3px solid #3272b1; background: #eaf2fa; }.example strong, .command-example span { display: block; margin-bottom: 4px; color: #1f4d7d; font-size: .78rem; }.detail-panel .example p { font-size: .86rem; }.command-example code { color: #1f4d7d; font-size: .95rem; overflow-wrap: anywhere; }
.tool-list ul { margin: 0; padding-left: 20px; font-size: .86rem; }.tool-list li + li { margin-top: 14px; }.tool-list a { color: #265989; font-weight: 700; text-decoration: underline; }.detail-panel .tool-list p { margin-top: 4px; }.tool-list code { display: block; margin-top: 6px; color: #1f4d7d; font-size: .83rem; overflow-wrap: anywhere; }
.choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }.choice-grid button { display: flex; flex-direction: column; align-items: start; justify-content: center; min-height: 58px; padding: 8px 10px; border: 1px solid #c5d7e8; border-radius: 9px; background: #fff; color: #28527b; text-align: left; overflow-wrap: anywhere; }.choice-grid button strong { font-size: .9rem; }.choice-grid button small { margin-top: 2px; color: #526b83; font-size: .73rem; }
.network-flow { display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 75px; margin-bottom: 12px; }.network-flow span { display: grid; place-items: center; flex: 1; min-width: 0; min-height: 60px; padding: 7px; border: 1px solid #bfd3e6; border-radius: 9px; background: #fff; color: #28527b; text-align: center; overflow-wrap: anywhere; font-size: .79rem; font-weight: 700; }.network-flow b { color: #3272b1; }
.topology-diagram { display: block; width: 100%; max-height: 220px; margin-bottom: 8px; }.topology-diagram line { stroke: #699ac5; stroke-width: 3; }.topology-diagram rect { fill: #fff; stroke: #8caecf; stroke-width: 1.5; }.topology-diagram text { fill: #28527b; font-size: 12px; font-weight: 650; }.diagram-caption { margin: 0 0 12px; color: #516980; font-size: .8rem; }
.caption { margin: 12px 0 0; color: #577089; font-size: .8rem; line-height: 1.35; }
.lesson-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0 0; }.lesson-footer button { min-height: 37px; padding: 6px 16px; border: 1px solid #b8cde1; border-radius: 8px; background: #fff; color: #265989; font-weight: 700; }.lesson-footer .next-button { border-color: #2868a8; background: #2868a8; color: #fff; }.lesson-footer button:disabled { opacity: .45; cursor: default; }.lesson-footer span { color: #667b91; font-size: .8rem; text-align: center; }
.lesson-sources { margin-top: 18px; color: #516980; font-size: .8rem; }.lesson-sources summary { cursor: pointer; }.lesson-sources a { display: inline-block; margin: 4px 16px 4px 0; color: #265989; text-decoration: underline; }
@media (max-width: 850px) { .lesson-navigation { grid-template-columns: repeat(4, minmax(0, 1fr)); }.section-heading { align-items: start; flex-direction: column; gap: 4px; }.two-columns { grid-template-columns: 1fr; } }
@media (max-width: 520px) { .lesson-content { padding: 17px 14px; }.lesson-navigation button { font-size: .7rem; }.network-flow { flex-direction: column; }.network-flow span { width: 100%; min-height: 36px; }.network-flow b { transform: rotate(90deg); }.choice-grid { grid-template-columns: 1fr; }.lesson-footer button { padding: 6px 10px; } }
</style>
