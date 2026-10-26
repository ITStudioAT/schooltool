<template>
    <article class="media-lesson" aria-labelledby="media-title">
        <header class="lesson-header">
            <div><p class="eyebrow">Netzwerke verstehen</p><h1 id="media-title">Übertragungsmedien, LAN und WLAN</h1></div>
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
                <section class="visual-panel" :aria-label="currentPage.visualLabel">
                    <div v-if="currentPage.key === 'wiring'" class="wiring-chain" aria-label="Verbindung vom Gerät zum Switch">
                        <template v-for="(part, index) in currentPage.items" :key="part.name">
                            <button type="button" :aria-pressed="selection === part.name" @click="selectItem(part.name)">{{ part.name }}</button>
                            <span v-if="index < currentPage.items.length - 1" class="wire-label">{{ ['Patchkabel', 'feste Leitung', 'Patchkabel'][index] }}</span>
                        </template>
                    </div>
                    <div v-else-if="currentPage.key === 'fiber'" class="fiber-diagram">
                        <p class="fiber-caption">So gelangen Daten von einem Gerät zum anderen:</p>
                        <div class="fiber-path" aria-label="Weg der Daten über Glasfaser">
                            <span>Gerät</span><span aria-hidden="true">→</span>
                            <template v-for="(item, index) in currentPage.items.slice(0, 3)" :key="item.name">
                                <button type="button" :aria-pressed="selection === item.name" @click="selectItem(item.name)">{{ item.name }}</button>
                                <span aria-hidden="true">→</span>
                            </template>
                            <span>Gerät</span>
                        </div>
                        <p class="fiber-caption">Zwei Arten von Glasfasern:</p>
                        <div class="choice-grid choice-grid--four">
                            <button v-for="item in currentPage.items.slice(3, 5)" :key="item.name" type="button"
                                :aria-pressed="selection === item.name" @click="selectItem(item.name)"><strong>{{ item.name }}</strong><small>{{ item.short }}</small></button>
                        </div>
                        <div class="choice-grid fiber-distance">
                            <button type="button" :aria-pressed="selection === 'Längere Strecken'" @click="selectItem('Längere Strecken')"><strong>Längere Strecken</strong><small>Was tun, wenn die Strecke zu lang ist?</small></button>
                        </div>
                    </div>
                    <div v-else-if="currentPage.key === 'poe'" class="poe-diagram">
                        <div class="poe-line"><span>{{ selection === 'Kein PoE' ? 'Switch ohne PoE' : 'PoE-Switch' }}</span><span class="signal-path">{{ selected.signal }}</span><span>Access Point</span></div>
                        <p class="visual-caption">{{ selected.diagram }}</p>
                        <div class="choice-grid choice-grid--three">
                            <button v-for="item in currentPage.items" :key="item.name" type="button"
                                :aria-pressed="selection === item.name" @click="selectItem(item.name)"><strong>{{ item.name }}</strong><small>{{ item.short }}</small></button>
                        </div>
                    </div>
                    <div v-else-if="currentPage.key === 'coverage'" class="coverage-diagram">
                        <div class="coverage-map" role="img" :aria-label="selected.diagram">
                            <span>{{ selection === 'Repeater' ? 'Access Point' : 'Netzwerk' }}</span><span class="signal-path" aria-hidden="true">{{ selected.signal }}</span><span>{{ selected.node }}</span><span class="signal-path" aria-hidden="true">{{ selected.lastSignal }}</span><span>Gerät</span>
                        </div>
                        <p class="visual-caption">{{ selected.diagram }}</p>
                        <div class="choice-grid choice-grid--four">
                            <button v-for="item in currentPage.items" :key="item.name" type="button"
                                :aria-pressed="selection === item.name" @click="selectItem(item.name)"><strong>{{ item.name }}</strong><small>{{ item.short }}</small></button>
                        </div>
                    </div>
                    <div v-else>
                        <div v-if="currentPage.key === 'media' || currentPage.key === 'compare'" class="signal-diagram" role="img" :aria-label="selected.diagram">
                            <span>{{ selected.from }}</span><span class="signal-path" aria-hidden="true">{{ selected.signal }}</span><span>{{ selected.to }}</span>
                        </div>
                        <div class="choice-grid" :class="{ 'choice-grid--four': currentPage.items.length === 4 }">
                            <button v-for="item in currentPage.items" :key="item.name" type="button"
                                :aria-pressed="selection === item.name" @click="selectItem(item.name)"><strong>{{ item.name }}</strong><small>{{ item.short }}</small></button>
                        </div>
                    </div>
                    <p v-if="currentPage.caption" class="caption">{{ currentPage.caption }}</p>
                </section>

                <section class="detail-panel" aria-live="polite" aria-atomic="true">
                    <span>{{ currentPage.detailLabel }}</span><h3>{{ selected.name }}</h3>
                    <p>{{ selected.description }}</p>
                    <p v-if="selected.note" class="note">{{ selected.note }}</p>
                    <p v-if="selected.extra" class="extra">{{ selected.extra }}</p>
                </section>
            </div>
        </main>

        <footer class="lesson-footer">
            <button type="button" :disabled="pageIndex === 0" @click="openPage(pageIndex - 1)">← Zurück</button>
            <span>{{ currentPage.label }} · {{ pageIndex + 1 }} von {{ pages.length }}</span>
            <button type="button" class="next-button" :disabled="pageIndex === pages.length - 1" @click="openPage(pageIndex + 1)">Weiter →</button>
        </footer>
    </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const pages = [
    {
        key: 'media', label: 'Medien', kicker: 'Daten unterwegs', title: 'Wie kommen Signale ans Ziel?',
        intro: 'Das Übertragungsmedium ist der Weg, über den Signale zwischen Geräten gelangen.',
        visualLabel: 'Übertragungsmedium auswählen', detailLabel: 'Das passiert auf dem Weg',
        caption: 'Ethernet kann je nach Variante Kupfer oder Glasfaser nutzen. WLAN verwendet Funk.',
        items: [
            { name: 'Kupfer', short: 'elektrische Signale', from: 'Gerät', to: 'Switch', signal: '━━━━', diagram: 'Gerät und Switch sind über eine Kupferleitung verbunden.', description: 'Ein Netzwerkkabel aus Kupfer überträgt elektrische Signale, etwa zwischen Computer und Switch.', note: 'Beide Anschlüsse und das Kabel müssen zur verwendeten Verbindung passen.' },
            { name: 'Glasfaser', short: 'Lichtsignale', from: 'Switch', to: 'Switch', signal: '✦ ━ ✦', diagram: 'Zwei Netzwerkgeräte sind über eine Glasfaserleitung verbunden.', description: 'Eine Glasfaser leitet Lichtsignale. Sie wird etwa zwischen Etagen oder Gebäuden eingesetzt.', note: 'Faser und optische Sende- und Empfangsmodule müssen zusammenpassen.' },
            { name: 'Funk', short: 'Funkwellen', from: 'Gerät', to: 'Access Point', signal: ')))', diagram: 'Ein WLAN-Gerät verbindet sich per Funk mit einem Access Point.', description: 'Ein WLAN-Gerät tauscht Funkwellen mit einem Access Point aus. Es braucht dafür kein eigenes Netzwerkkabel.', note: 'Der Access Point ist häufig per Kabel mit dem lokalen Netzwerk verbunden.' },
        ],
    },
    {
        key: 'copper', label: 'Kupfer', kicker: 'Das Netzwerkkabel', title: 'Was steckt im Kupferkabel?',
        intro: 'Die Cat-Kategorie gibt an, welche Übertragungsleistung ein Kabel unterstützt. Sie beeinflusst, welche Datenrate über welche Entfernung möglich ist.',
        visualLabel: 'Kabel und Anschluss auswählen', detailLabel: 'Kabel und Anschluss',
        caption: 'Die Geschwindigkeit hängt auch von den Geräten, Anschlüssen und der Kabellänge ab. Zur Länge zählen auch die Patchkabel.',
        items: [
            { name: 'Cat 5e', short: '1 Gbit/s · bis 100 m', description: 'Cat 5e eignet sich häufig für 1 Gbit/s. Mit Gigabit-Ethernet (1000BASE-T) sind unter passenden Bedingungen bis zu 100 m möglich.', note: 'Das ist eine typische Verwendung, keine Aussage über jede mögliche Ethernet-Variante.' },
            { name: 'Cat 6', short: '10 Gbit/s · bis 55 m', description: 'Cat 6 kann für 10 Gbit/s verwendet werden. Bei 10-Gigabit-Ethernet (10GBASE-T) sind unter geeigneten Bedingungen bis zu 55 m möglich.', note: 'Die mögliche Länge hängt von Kabel, Anschlüssen und Umgebung ab.' },
            { name: 'Cat 6A', short: '10 Gbit/s · bis 100 m', description: 'Cat 6A ist für 10 Gbit/s mit 10GBASE-T über eine geeignete Verbindung bis zu 100 m ausgelegt.', note: 'Ein Cat-6A-Kabel macht einen Anschluss mit 1 Gbit/s nicht automatisch schneller.' },
            { name: 'RJ45', short: 'häufiger Anschluss', description: 'Der im Alltag RJ45 genannte achtpolige Stecker wird häufig an Computern, Switches und Netzwerkdosen verwendet.', note: 'Kupferkabel enthalten verdrillte Adernpaare, die Störungen vermindern. Manche Kabel besitzen zusätzlich eine Schirmung gegen äußere Einflüsse.' },
        ],
    },
    {
        key: 'wiring', label: 'Verkabelung', kicker: 'Im Gebäude', title: 'Vom Gerät zum Switch',
        intro: 'Jede fest verlegte Leitung endet im Raum an einer Netzwerkdose und im Schrank am Patchpanel.',
        visualLabel: 'Bestandteile der Verkabelung auswählen', detailLabel: 'Funktion',
        caption: 'Gerät und Dose sowie Patchpanel und Switch werden jeweils mit einem Patchkabel verbunden.',
        items: [
            { name: 'Gerät', short: '', description: 'Ein Computer wird mit einem flexiblen Patchkabel an die Netzwerkdose angeschlossen.', note: 'Auch ein Access Point kann so mit dem lokalen Netzwerk verbunden sein.' },
            { name: 'Netzwerkdose', short: '', description: 'An der Netzwerkdose im Raum endet die fest installierte Leitung. Sie führt zum Patchpanel im Netzwerkschrank.', note: 'Die Leitung kann zum Beispiel in der Wand oder in einem Kabelweg verlegt sein.' },
            { name: 'Patchpanel', short: '', description: 'Das Patchpanel ist ein geordnetes Anschlussfeld. Jede fest verlegte Leitung endet dort an einem eigenen Anschluss.', note: 'Ein Patchkabel verbindet den Anschluss mit dem Switch. Das Patchpanel leitet Daten nicht aktiv wie ein Switch weiter.' },
            { name: 'Switch', short: '', description: 'Der Switch verbindet die angeschlossenen Geräte im lokalen Netzwerk.', note: 'Zwischen zentralem Netzwerkschrank und Etagenverteilern kann ein Backbone eingesetzt werden, zum Beispiel über Glasfaser.' },
        ],
    },
    {
        key: 'fiber', label: 'Glasfaser', kicker: 'Licht statt Strom', title: 'Wie funktioniert Glasfaser?',
        intro: 'Geräte wandeln Daten in Lichtsignale um. Diese laufen durch Glasfasern zum anderen Ende.',
        visualLabel: 'Übertragung und Fasertypen ansehen', detailLabel: 'Einfach erklärt',
        caption: 'Glasfaser überträgt Daten mit Licht. Die Geräte selbst benötigen eine Stromversorgung.',
        items: [
            { name: 'Sender', short: '', description: 'Der optische Sender ist ein Bauteil im Gerät oder einem Steckmodul. Er wandelt elektrische Signale in eine schnelle Folge von Lichtsignalen um und sendet sie in die Glasfaser.', note: 'Die Lichtsignale übertragen die Daten. Sie sind oft Infrarotlicht und für das menschliche Auge nicht sichtbar.' },
            { name: 'Glasfaserkabel', short: '', description: 'Im Kabel liegen eine oder mehrere sehr dünne Glasfasern. Jede Faser hat einen Kern, durch den das Licht läuft, und einen Mantel, der das Licht im Kern hält.', note: 'Zusätzliche Schutzschichten schützen die empfindlichen Fasern. Die Glasfaser selbst ist gegenüber elektromagnetischen Störungen unempfindlich.' },
            { name: 'Empfänger', short: '', description: 'Am anderen Ende nimmt ein optischer Empfänger im Gerät oder Steckmodul die Lichtsignale auf und wandelt sie wieder in elektrische Signale um.', note: 'Sender, Empfänger und Glasfaser müssen zusammenpassen. Für beide Geräte ist eine Stromversorgung nötig.' },
            { name: 'Multimode', short: 'oft kürzere Strecken', description: 'Multimode und Singlemode sind Arten von Glasfasern. Multimode hat einen größeren Kern, in dem sich das Licht auf mehreren Wegen ausbreiten kann.', note: 'Bei 10 Gbit/s mit 10GBASE-SR sind zum Beispiel mit OM3 bis zu 300 m und mit OM4 bis zu 400 m möglich. Die Reichweite hängt von Faser, Datenrate, Modulen und Dämpfung ab.' },
            { name: 'Singlemode', short: 'auch längere Strecken', description: 'Eine Singlemode-Faser hat einen kleineren Kern. Das Licht breitet sich darin auf eine andere Art aus als in einer Multimode-Faser.', note: 'Bei 10 Gbit/s sind mit 10GBASE-LR bis zu 10 km möglich; passende 10GBASE-ER-Module schaffen bis zu 40 km. Faser, Datenrate, Module und Dämpfung bestimmen die Reichweite.' },
            { name: 'Längere Strecken', short: '', description: 'Ist die Verbindung zu lang, kann passende Technik mit größerer Reichweite helfen. Alternativ empfängt ein Switch dazwischen die Daten und leitet sie über einen neuen Glasfaserabschnitt weiter.', note: 'Jeder Abschnitt muss zur Reichweite seiner Module und Fasern passen. Der Switch benötigt Strom.', extra: 'Gerät A → Glasfaser → Switch → Glasfaser → Gerät B' },
        ],
    },
    {
        key: 'poe', label: 'PoE', kicker: 'Strom und Daten', title: 'Was überträgt ein PoE-Kabel?',
        intro: 'PoE steht für Power over Ethernet. Ein geeignetes Netzwerkkabel überträgt dabei Daten und Strom gleichzeitig.',
        visualLabel: 'PoE-Situation auswählen', detailLabel: 'So wirkt sich die Verbindung aus',
        items: [
            { name: 'PoE passt', short: 'Daten und Strom', signal: 'Daten + Strom →', diagram: 'In dieser Darstellung unterstützen Switch und Access Point zueinander passendes PoE.', description: 'Ein PoE-Switch kann einen passenden Access Point über dasselbe Kabel mit Daten und Strom versorgen.', note: 'So braucht der Access Point kein eigenes Stromkabel.' },
            { name: 'Kein PoE', short: 'nur Daten', signal: 'Daten →', diagram: 'Hier stellt der Switch kein PoE bereit.', description: 'Ohne passende Stromversorgung über das Kabel braucht der Access Point eine andere geeignete Stromquelle.', note: 'Nicht jeder Switch und nicht jedes Endgerät unterstützt PoE.' },
            { name: 'Leistung beachten', short: 'Geräte müssen passen', signal: 'Daten + Strom ?', diagram: 'Hier muss geklärt werden, ob die bereitgestellte Leistung für das Gerät reicht.', description: 'Der Switch muss genug Leistung für das angeschlossene Gerät bereitstellen können. Auch die Leistung für alle angeschlossenen Geräte zusammen ist begrenzt.', note: 'Wenn mehrere Geräte Strom über den Switch erhalten, muss seine verfügbare Leistung für alle zusammen ausreichen.' },
        ],
    },
    {
        key: 'compare', label: 'LAN & WLAN', kicker: 'Zwei Zugangswege', title: 'Kabel oder Funk?',
        intro: 'Kabelgebundene und drahtlose Geräte können zum selben lokalen Netzwerk gehören.',
        visualLabel: 'Zugangsweg auswählen', detailLabel: 'Der Zugang zum Netzwerk',
        caption: 'Ein Netzwerkanschluss allein ersetzt keine Zugangskontrolle. Auch WLAN braucht passende Schutzeinstellungen.',
        items: [
            { name: 'Kabel', short: 'eigene Leitung', from: 'Computer', to: 'Switch', signal: '━━━━', diagram: 'Der Computer ist über ein Netzwerkkabel mit dem Switch verbunden.', description: 'Ein Gerät ist über eine eigene Leitung mit dem Switch verbunden. Das ist oft stabil, bindet das Gerät aber an das Kabel.', note: 'Die erreichbare Datenrate hängt unter anderem von Anschlüssen und Verkabelung ab.' },
            { name: 'WLAN', short: 'beweglich per Funk', from: 'Smartphone', to: 'Access Point', signal: ')))', diagram: 'Das Smartphone ist per Funk mit einem Access Point verbunden.', description: 'Ein Gerät kann sich innerhalb der Funkabdeckung bewegen. Mehrere Geräte teilen sich den verfügbaren Funkraum.', note: 'Abstand, Hindernisse, Störungen und die Zahl aktiver Geräte beeinflussen die Übertragung.' },
        ],
    },
    {
        key: 'frequencies', label: 'Frequenzen', kicker: 'WLAN über Funk', title: '2,4, 5 und 6 GHz',
        intro: 'Ein Frequenzbereich ist in Kanäle unterteilt. Access Points nutzen einen eingestellten Kanal oder Kanalbereich.',
        visualLabel: 'Frequenzbereich auswählen', detailLabel: 'Typische Eigenschaften',
        caption: 'Diese Eigenschaften sind Tendenzen: Gebäude, Störungen, Kanalbreite und Geräte beeinflussen das Ergebnis.',
        items: [
            { name: '2,4 GHz', short: 'oft größere Reichweite', description: '2,4 GHz erreicht häufig eine größere Reichweite und durchdringt Hindernisse besser als höhere Frequenzen.', note: 'Es gibt weniger störungsfrei nutzbare Kanäle; der Bereich ist oft stärker belegt.' },
            { name: '5 GHz', short: 'mehr Kanalmöglichkeiten', description: '5 GHz bietet mehr Kanalmöglichkeiten und häufig höhere Datenraten.', note: 'Wände und Entfernung können das Signal stärker abschwächen. Eng benachbarte Access Points brauchen eine abgestimmte Kanalplanung.' },
            { name: '6 GHz', short: 'für geeignete Geräte', description: '6 GHz bietet einen zusätzlichen Frequenzbereich für geeignete neuere Geräte.', note: 'Verfügbarkeit, erlaubte Kanäle und Bedingungen hängen vom Land und von den Geräten ab.' },
        ],
    },
    {
        key: 'wifi', label: 'Wi-Fi', kicker: 'WLAN-Technik', title: 'Was bedeuten die Wi-Fi-Generationen?',
        intro: 'Wi-Fi bezeichnet gängige WLAN-Techniken nach Standards der IEEE-802.11-Familie.',
        visualLabel: 'Wi-Fi-Generation auswählen', detailLabel: 'Generation',
        caption: 'Die Höchstwerte gelten nur unter idealen Bedingungen. Mehrere Datenströme übertragen Daten gleichzeitig. In der Praxis ist die Geschwindigkeit meist niedriger; Geräte, Funkbedingungen und erlaubte Frequenzen spielen eine Rolle.',
        items: [
            { name: 'Wi-Fi 5', short: 'bis zu 6,9 Gbit/s', description: 'Wi-Fi 5 basiert auf IEEE 802.11ac und nutzt den 5-GHz-Bereich. Die theoretische Funk-Datenrate beträgt bis zu 6,9 Gbit/s.', note: 'Der Höchstwert setzt einen breiten Funkkanal und acht gleichzeitige Datenströme voraus. Viele Geräte unterstützen weniger.' },
            { name: 'Wi-Fi 6', short: 'bis zu 9,6 Gbit/s', description: 'Wi-Fi 6 basiert auf IEEE 802.11ax und nutzt 2,4 GHz und 5 GHz. Die theoretische Funk-Datenrate beträgt bis zu 9,6 Gbit/s.', note: 'Der Höchstwert setzt einen breiten Funkkanal und acht gleichzeitige Datenströme voraus. Viele Geräte unterstützen weniger.' },
            { name: 'Wi-Fi 6E', short: 'bis zu 9,6 Gbit/s', description: 'Wi-Fi 6E erweitert Wi-Fi 6 um den 6-GHz-Bereich, wo Geräte und örtliche Regeln dies unterstützen. Die theoretische Funk-Datenrate beträgt ebenfalls bis zu 9,6 Gbit/s.', note: 'Auch hier gelten die besten Bedingungen mit breitem Funkkanal und acht gleichzeitigen Datenströmen.' },
            { name: 'Wi-Fi 7', short: 'bis zu 46 Gbit/s', description: 'Wi-Fi 7 basiert auf IEEE 802.11be. Je nach Gerät und Zulassung sind 2,4 GHz, 5 GHz und 6 GHz möglich. Die theoretische Funk-Datenrate beträgt bis zu 46 Gbit/s.', note: 'Der Höchstwert setzt unter anderem einen sehr breiten Funkkanal und 16 gleichzeitige Datenströme voraus. Übliche Geräte unterstützen weniger.' },
        ],
    },
    {
        key: 'coverage', label: 'Abdeckung', kicker: 'WLAN im Gebäude', title: 'Wie kommt WLAN in die Räume?',
        intro: 'Position, Räume, Wände und die Nutzung der Geräte beeinflussen die Funkabdeckung.',
        visualLabel: 'WLAN-Baustein auswählen', detailLabel: 'So funktioniert es',
        caption: 'Eine WLAN-Ausleuchtung ermittelt die tatsächliche Abdeckung. Kein einzelnes Gerät garantiert überall eine gleich gute Verbindung.',
        items: [
            { name: 'Access Point', short: 'WLAN-Zugang', node: 'Access Point', signal: '━━', lastSignal: ')))', diagram: 'Der Access Point ist hier per Kabel mit dem Netzwerk verbunden und funkt zum Gerät.', description: 'Ein Access Point stellt den drahtlosen Zugang zum lokalen Netzwerk bereit. Häufig führt ein Ethernet-Kabel zum Switch.', note: 'Die SSID ist der Name, unter dem ein WLAN bei der Verbindungsauswahl angezeigt wird.' },
            { name: 'Repeater', short: 'leitet Funk weiter', node: 'Repeater', signal: ')))', lastSignal: ')))', diagram: 'Der Repeater empfängt per Funk und leitet Daten ebenfalls per Funk weiter.', description: 'Ein Repeater nimmt ein Funksignal auf und leitet Daten über Funk weiter.', note: 'Auch für die Weiterleitung wird Funkkapazität benötigt.' },
            { name: 'Mesh', short: 'mehrere Knoten', node: 'Mesh-Knoten', signal: '↔', lastSignal: ')))', diagram: 'Mesh-Knoten arbeiten zusammen; die Verbindung zwischen ihnen kann per Kabel oder Funk erfolgen.', description: 'Ein Mesh-System besteht aus mehreren zusammenarbeitenden WLAN-Knoten, die die Abdeckung erweitern.', note: 'Die Verbindung zwischen den Knoten kann je nach System drahtlos oder kabelgebunden sein.' },
            { name: 'Roaming', short: 'Wechsel des Geräts', node: 'Access Point', signal: '━━', lastSignal: ')))', diagram: 'Ein mobiles Gerät kann bei Bedarf zwischen geeigneten Access Points wechseln.', description: 'Beim Roaming wechselt ein Endgerät bei Bedarf zwischen geeigneten Access Points.', note: 'Ob und wann der Wechsel erfolgt, hängt auch vom Endgerät und den Einstellungen ab.' },
        ],
    },
    {
        key: 'security', label: 'Sicherheit', kicker: 'WLAN schützen', title: 'Wer darf ins WLAN?',
        intro: 'Funk kann auch außerhalb eines Raums empfangen werden. Der Zugang und die Datenübertragung müssen geschützt werden.',
        visualLabel: 'Sicherheitsthema auswählen', detailLabel: 'Schutz und Zugang',
        caption: 'Zugangsdaten und Verwaltungszugänge schützen; Aktualisierungen schließen bekannte Sicherheitslücken.',
        items: [
            { name: 'Authentifizierung', short: 'Zugang prüfen', description: 'Authentifizierung bedeutet, dass sich eine Person oder ein Gerät gegenüber dem Netzwerk ausweist.', note: 'Der WLAN-Name allein entscheidet nicht, welche internen Netzwerkbereiche erreichbar sind.' },
            { name: 'WPA2 & WPA3', short: 'WLAN schützen', description: 'WPA steht für Wi-Fi Protected Access. WPA2 und WPA3 sind Sicherheitsstandards: Sie regeln die Anmeldung am WLAN und schützen die Funkverbindung.', note: 'WPA3 schützt die Anmeldung mit Passwort besser gegen das Erraten des Passworts. WEP und das ursprüngliche WPA sind veraltet; für 6 GHz reicht WPA2 allein nicht aus.' },
            { name: 'AES', short: 'Daten verschlüsseln', description: 'AES steht für Advanced Encryption Standard. Es ist ein Verschlüsselungsverfahren, das die Daten auf der WLAN-Funkverbindung schützt.', note: 'WPA2 und WPA3 können AES verwenden. WPA2 und WPA3 regeln den WLAN-Schutz insgesamt; AES ist ein Baustein für die Verschlüsselung.' },
            { name: 'Gäste-WLAN', short: 'Zugänge trennen', description: 'Über ein Gäste-WLAN können Besucherinnen und Besucher das Internet nutzen. Der Zugang zu internen Geräten und Diensten, etwa einer gemeinsamen Dateiablage, kann gesperrt werden.', note: 'Dafür muss die Trennung am Router oder im Netzwerk eingerichtet sein. Ein anderer WLAN-Name oder ein eigenes Passwort allein genügt nicht.' },
        ],
    },
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
    const items = pages[pageIndex.value].items
    selection.value = items.find(item => item.name === selectedName)?.name || items[0].name
}, { immediate: true })

function syncLocation(push) {
    if (!router || !route) return
    const page = pages[pageIndex.value]
    const query = { ...route.query, chapter: page.key, selection: selection.value === page.items[0].name ? undefined : selection.value }
    router[push ? 'push' : 'replace']({ query, hash: route.hash })
}

function selectItem(name) {
    selection.value = name
    syncLocation(false)
}

function openPage(index) {
    if (index < 0 || index >= pages.length) return
    pageIndex.value = index
    selection.value = pages[index].items[0].name
    syncLocation(true)
}
</script>

<style scoped>
.media-lesson { max-width: 1200px; margin: 0 auto; color: #1b3049; font-size: 1rem; line-height: 1.45; }
.lesson-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 10px; }
.lesson-header h1 { margin: 0; color: #193c68; font-size: clamp(1.35rem, 2.2vw, 1.8rem); line-height: 1.15; }
.eyebrow { margin: 0 0 2px; color: #52729a; font-size: .75rem; font-weight: 700; }
.page-number { flex: 0 0 auto; color: #64778b; font-size: .85rem; }
button { font: inherit; cursor: pointer; }
button:focus-visible { outline: 3px solid #2c6fb6; outline-offset: 2px; }
button[aria-pressed='true'] { border-color: #3272b1 !important; background: #e5f1fc !important; color: #174a7d !important; box-shadow: inset 0 0 0 1px #3272b1; }
.lesson-navigation { display: grid; grid-template-columns: repeat(10, minmax(0, 1fr)); gap: 5px; margin-bottom: 10px; }
.lesson-navigation button { min-width: 0; min-height: 38px; padding: 5px; border: 1px solid #cbd9e8; border-radius: 9px; background: #fff; color: #32577f; font-size: .76rem; font-weight: 650; }
.lesson-content { padding: 20px 24px; border: 1px solid #dce5ef; border-radius: 16px; background: #fff; }
.section-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 13px; }
.section-heading span, .detail-panel > span { color: #3973ad; font-size: .75rem; font-weight: 700; }
.section-heading h2 { margin: 1px 0 0; color: #193c68; font-size: clamp(1.25rem, 2vw, 1.65rem); line-height: 1.15; }
.section-heading > p { max-width: 45ch; margin: 0; color: #5a7189; font-size: .87rem; }
.two-columns { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); gap: 16px; align-items: stretch; }
.visual-panel, .detail-panel { min-width: 0; padding: 16px; border-radius: 12px; }
.visual-panel { background: #f0f6fc; border: 1px solid #dbe8f4; }
.detail-panel { background: #f8fafc; border: 1px solid #e1e8ef; }
.detail-panel h3 { margin: 3px 0 8px; color: #1f4d7d; font-size: 1.3rem; }
.detail-panel p { margin: 0; font-size: .95rem; }
.detail-panel .note, .detail-panel .extra { margin-top: 15px; color: #516980; font-size: .86rem; }
.choice-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.choice-grid--four { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.choice-grid button { display: flex; flex-direction: column; align-items: start; justify-content: center; min-height: 62px; padding: 8px 10px; border: 1px solid #c5d7e8; border-radius: 9px; background: white; color: #28527b; text-align: left; }
.choice-grid button strong { font-size: .9rem; }.choice-grid button small { margin-top: 2px; color: #667d94; font-size: .73rem; }
.signal-diagram, .coverage-map, .poe-line { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 84px; margin-bottom: 10px; }
.signal-diagram > span:not(.signal-path), .coverage-map > span:not(.signal-path), .poe-line > span:not(.signal-path) { display: grid; place-items: center; min-width: 100px; min-height: 46px; padding: 8px; border: 1px solid #bfd3e6; border-radius: 9px; background: #fff; color: #28527b; text-align: center; font-size: .85rem; font-weight: 700; }
.signal-path { color: #3272b1; font-weight: 700; text-align: center; font-size: .9rem; }
.fiber-path { display: flex; align-items: center; justify-content: center; gap: 5px; margin: 10px 0 14px; }
.fiber-path button { flex: 1; min-width: 0; min-height: 48px; padding: 5px; border: 1px solid #bfd3e6; border-radius: 9px; background: white; color: #28527b; font-size: .8rem; font-weight: 700; }
.fiber-path span { color: #52749a; font-size: .75rem; }
.fiber-caption { margin: 0; color: #577089; font-size: .8rem; }
.choice-grid.fiber-distance { grid-template-columns: 1fr; margin-top: 8px; }
.fiber-distance button { min-height: 44px; }
.wiring-chain { display: flex; align-items: center; gap: 3px; min-height: 82px; }
.wiring-chain button { flex: 1; min-width: max-content; min-height: 48px; padding: 5px; border: 1px solid #b9cfe5; border-radius: 9px; background: #fff; color: #28527b; font-weight: 700; font-size: .72rem; white-space: nowrap; }
.wiring-chain > span { max-width: 50px; color: #52749a; font-size: .62rem; line-height: 1.2; text-align: center; }
.caption, .visual-caption { margin: 10px 0 0; color: #577089; font-size: .8rem; line-height: 1.35; }
.visual-caption { margin: -4px 0 10px; text-align: center; }
.lesson-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0 0; }
.lesson-footer button { min-height: 37px; padding: 6px 16px; border: 1px solid #b8cde1; border-radius: 8px; background: #fff; color: #265989; font-weight: 700; }
.lesson-footer button.next-button { border-color: #2868a8; background: #2868a8; color: #fff; }
.lesson-footer button:disabled { opacity: .45; cursor: default; }.lesson-footer span { color: #667b91; font-size: .8rem; }
@media (max-width: 850px) { .lesson-navigation { grid-template-columns: repeat(5, minmax(0, 1fr)); }.section-heading { align-items: start; flex-direction: column; gap: 4px; }.two-columns { grid-template-columns: 1fr; }.detail-panel .note { margin-top: 8px; } }
@media (max-width: 520px) { .lesson-content { padding: 17px 14px; }.lesson-navigation button { font-size: .7rem; }.choice-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.wiring-chain { flex-wrap: wrap; }.wiring-chain button { flex: 1 1 35%; }.signal-diagram > span:not(.signal-path), .coverage-map > span:not(.signal-path), .poe-line > span:not(.signal-path) { min-width: 60px; font-size: .75rem; }.lesson-footer span { text-align: center; } }
</style>
