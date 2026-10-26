<template>
    <article class="network-lesson" aria-labelledby="network-lesson-title">
        <header class="lesson-intro">
            <div>
                <p class="lesson-kicker">Netzwerke</p>
                <h1 id="network-lesson-title">Netzwerkgrundlagen und Netzwerkgeräte</h1>
                <p>Wie Geräte miteinander verbunden sind, Daten austauschen und gemeinsame Dienste nutzen.</p>
            </div>
            <div class="intro-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        </header>

        <nav class="lesson-nav" aria-label="Themenübersicht">
            <a href="#netzwerk">Netzwerk</a><a href="#netzarten">Netzarten</a><a href="#dienste">Dienste</a><a href="#datenrate">Datenrate</a><a href="#geraete">Geräte</a><a href="#aufbau">Aufbau</a>
        </nav>

        <section id="netzwerk" class="lesson-section">
            <div class="section-heading"><span>Grundidee</span><h2>Was ist ein Computernetzwerk?</h2></div>
            <p>Ein <strong>Computernetzwerk</strong> verbindet Geräte, damit sie Daten austauschen und Dienste gemeinsam nutzen können. Die Verbindung erfolgt über Kabel oder über Funk.</p>
            <p>Für die Übertragung gelten gemeinsame Regeln, sogenannte <strong>Protokolle</strong>. Größere Datenmengen können in kleinere Einheiten aufgeteilt werden. Adressen helfen dabei, die Daten zum richtigen Ziel zu bringen.</p>
            <figure class="network-figure">
                <svg viewBox="0 0 780 290" role="img" aria-labelledby="network-map-title network-map-desc">
                    <title id="network-map-title">Ein einfaches Netzwerk</title>
                    <desc id="network-map-desc">Das Internet ist mit einem Router verbunden. Der Router ist mit einem Switch verbunden. Am Switch hängen ein Computer, ein Server und ein WLAN-Zugangspunkt, mit dem ein Smartphone per Funk verbunden ist.</desc>
                    <g class="map-lines"><path d="M115 145H195 M315 145H375 M495 145H520 M520 70V220 M520 70H540 M520 145H540 M520 220H540"/><path class="wireless-line" d="M660 145H710"/></g>
                    <g class="map-node"><rect x="10" y="117" width="105" height="56" rx="12"/><text x="62" y="151">Internet</text><rect x="195" y="117" width="120" height="56" rx="12"/><text x="255" y="151">Router</text><rect x="375" y="117" width="120" height="56" rx="12"/><text x="435" y="151">Switch</text><rect x="540" y="42" width="120" height="56" rx="12"/><text x="600" y="76">Computer</text><rect x="540" y="117" width="120" height="56" rx="12"/><text x="600" y="151">Access Point</text><rect x="540" y="192" width="120" height="56" rx="12"/><text x="600" y="226">Server</text><rect x="710" y="117" width="65" height="56" rx="12"/><text x="742" y="143" class="small-label">Smart-</text><text x="742" y="158" class="small-label">phone</text></g>
                    <text x="250" y="200" class="map-note">Verbindung zu anderen Netzen</text><text x="435" y="200" class="map-note">lokales Netzwerk</text><text x="685" y="130" class="map-note">Funk</text>
                </svg>
                <figcaption>Ein möglicher Aufbau: Kabelgebundene Geräte und WLAN-Geräte gehören zum selben lokalen Netzwerk.</figcaption>
            </figure>
            <div class="callout"><strong>Gut zu wissen</strong><p>Ein lokales Netzwerk kann auch ohne Internet funktionieren. Es ist nicht automatisch sicher: Zugriffsrechte, Schutzmaßnahmen und die Verwaltung der Geräte bleiben wichtig.</p></div>
            <p>Über ein Netzwerk können berechtigte Personen Dateien und Anwendungen gemeinsam nutzen, drucken, Nachrichten austauschen oder einen Internetzugang teilen.</p>
        </section>

        <section id="netzarten" class="lesson-section">
            <div class="section-heading"><span>Begriffe</span><h2>Lokale und weitreichende Netzwerke</h2></div>
            <p>LAN, WLAN und WAN beschreiben die räumliche Ausdehnung oder die Art der Verbindung. Internet, Intranet und Extranet bezeichnen miteinander verbundene Netze oder zugängliche Dienste.</p>
            <div class="concept-grid">
                <div><h3>LAN</h3><p>Ein <strong>Local Area Network</strong> ist ein lokales Netzwerk, zum Beispiel in einem Gebäude oder auf einem Schulgelände. Geräte können unter anderem per Ethernet-Kabel verbunden sein.</p></div>
                <div><h3>WLAN</h3><p>Ein <strong>Wireless Local Area Network</strong> bietet drahtlosen Zugang zum lokalen Netzwerk über Funk. WLAN und Kabel können zum selben LAN gehören.</p></div>
                <div><h3>WAN</h3><p>Ein <strong>Wide Area Network</strong> verbindet Netzwerke über größere Entfernungen, etwa Standorte in verschiedenen Städten. Es kann auch privat betrieben werden.</p></div>
                <div><h3>Internet</h3><p>Ein weltweiter Zusammenschluss vieler Netzwerke, die gemeinsame Internetprotokolle nutzen.</p></div>
                <div><h3>Intranet</h3><p>Ein interner Bereich mit Informationen und Diensten für eine begrenzte Organisation, etwa eine Schule.</p></div>
                <div><h3>Extranet</h3><p>Ein für ausgewählte externe Personen oder Organisationen zugänglicher Teil interner Dienste. Der Zugang ist üblicherweise geschützt.</p></div>
            </div>
            <p>Ein Internetanbieter verbindet das lokale Netzwerk über einen Internetanschluss mit anderen Netzwerken. Ob ein interner Dienst von außen erreichbar ist, hängt von seiner Einrichtung und den Zugriffsrechten ab.</p>
        </section>

        <section id="dienste" class="lesson-section">
            <div class="section-heading"><span>Zusammenarbeit</span><h2>Wie Geräte Dienste nutzen</h2></div>
            <p>Ein <strong>Dienst</strong> ist eine Funktion, die über das Netzwerk angeboten wird, etwa eine Dateiablage oder eine Webseite. Ein <strong>Server</strong> stellt den Dienst bereit; ein <strong>Client</strong> fordert ihn an oder nutzt ihn. Das sind Rollen – nicht zwingend verschiedene Gerätearten.</p>
            <figure class="network-figure client-figure">
                <svg viewBox="0 0 680 170" role="img" aria-labelledby="client-title client-desc">
                    <title id="client-title">Client und Server</title><desc id="client-desc">Ein Client sendet eine Anfrage an den Server. Der Server sendet eine Antwort zurück.</desc>
                    <g class="map-node"><rect x="28" y="48" width="155" height="75" rx="14"/><text x="105" y="81">Client</text><text x="105" y="101" class="small-label">nutzt den Dienst</text><rect x="497" y="48" width="155" height="75" rx="14"/><text x="575" y="81">Server</text><text x="575" y="101" class="small-label">bietet den Dienst an</text></g>
                    <g class="map-lines"><path d="M202 65H471 M463 57L471 65L463 73 M478 108H210 M218 100L210 108L218 116"/></g><text x="338" y="53" class="map-note">Anfrage</text><text x="338" y="133" class="map-note">Antwort</text>
                </svg>
                <figcaption>Beim Aufrufen einer Webseite ist der Browser der Client. Er fordert Inhalte vom Webserver an und zeigt die Antwort an.</figcaption>
            </figure>
            <div class="compare"><div><h3>Client-Server</h3><p>Server stellen Dienste bereit, Clients greifen darauf zu. Zentrale Verwaltung kann Rechtevergabe und Datensicherung erleichtern. Der Dienst hängt von der Erreichbarkeit des Servers ab.</p></div><div><h3>Peer-to-Peer</h3><p>Geräte können Ressourcen direkt austauschen und gleichzeitig anbieten und nutzen. Je nach Anwendung können zusätzliche zentrale Dienste unterstützen.</p></div></div>
        </section>

        <section id="datenrate" class="lesson-section">
            <div class="section-heading"><span>Übertragung</span><h2>Datenrate und Verzögerung</h2></div>
            <p>Die <strong>Datenrate</strong> beschreibt, wie viele Bits pro Sekunde übertragen werden. Acht Bit bilden ein Byte. Netzwerkgeschwindigkeiten stehen oft in Mbit/s oder Gbit/s, Dateigrößen dagegen in MB oder GB.</p>
            <div class="concept-grid concept-grid--two">
                <div><h3>Bandbreite</h3><p>Im Netzwerkalltag häufig die verfügbare Übertragungskapazität. Technisch kann der Begriff auch die Breite eines Frequenzbereichs in Hertz bezeichnen.</p></div>
                <div><h3>Datendurchsatz</h3><p>Die Datenmenge, die tatsächlich in einer bestimmten Zeit übertragen wird. Sie kann unter der angegebenen Verbindungsgeschwindigkeit liegen.</p></div>
                <div><h3>Latenz</h3><p>Die Verzögerung bei der Übertragung, meist in Millisekunden. Messungen geben oft die Zeit für Hin- und Rückweg an.</p></div>
                <div><h3>Download und Upload</h3><p>Beim <strong>Download</strong> kommen Daten zum eigenen Gerät. Beim <strong>Upload</strong> werden Daten vom eigenen Gerät zu einem anderen Gerät oder Dienst übertragen.</p></div>
            </div>
            <div class="callout"><strong>Einordnung der Einheiten</strong><p>100 Mbit/s entsprechen rechnerisch höchstens 12,5 MB/s bei dezimalen Einheiten. Protokollaufwand, andere Nutzer, Funkbedingungen und Geräte können den tatsächlichen Durchsatz verringern. Eine hohe Datenrate bedeutet nicht automatisch eine niedrige Latenz.</p></div>
        </section>

        <section id="geraete" class="lesson-section">
            <div class="section-heading"><span>Bausteine</span><h2>Netzwerkgeräte und ihre Aufgaben</h2></div>
            <p>Ein Gerät benötigt eine passende <strong>Netzwerkschnittstelle</strong>: Ein Netzwerkadapter verbindet es über Kabel oder Funk. Eine <strong>MAC-Adresse</strong> kennzeichnet eine Schnittstelle auf der lokalen Verbindungsebene. Sie kann je nach Gerät oder Einstellung geändert oder zufällig gewählt werden.</p>
            <dl class="device-list">
                <div><dt>Switch</dt><dd>Verbindet Geräte in einem Ethernet-LAN. Ein einfacher Switch leitet Ethernet-Daten anhand von MAC-Adressen an passende Anschlüsse weiter.</dd></div>
                <div><dt>Router</dt><dd>Verbindet unterschiedliche IP-Netzwerke und leitet Datenpakete anhand ihrer IP-Zieladresse weiter, etwa vom Schulnetz Richtung Internet.</dd></div>
                <div><dt>Access Point</dt><dd>Ermöglicht den drahtlosen Zugang zu einem Netzwerk. Häufig ist er per Netzwerkkabel mit dem LAN verbunden.</dd></div>
                <div><dt>Modem</dt><dd>Passt Signale an die Technik eines Internetanschlusses an, etwa bei einem DSL- oder Kabelanschluss.</dd></div>
                <div><dt>Glasfaser-ONT</dt><dd>Der optische Netzabschluss setzt die optische Verbindung des Anbieters in einen passenden Anschluss für das Kundennetz um.</dd></div>
                <div><dt>Firewall</dt><dd>Kontrolliert Netzwerkverkehr anhand festgelegter Regeln. Sie kann ein eigenes Gerät oder eine Funktion in einem anderen Gerät sein.</dd></div>
                <div><dt>Server und NAS</dt><dd>Ein Server stellt Dienste bereit. Ein NAS ist auf Datenspeicherung im Netzwerk ausgerichtet und kann Dateidienste bereitstellen.</dd></div>
            </dl>
            <p>Mehrere Aufgaben können in einem Gerät stecken: Ein Heimrouter enthält häufig Router, Switch, WLAN-Access-Point und Firewall; je nach Anschluss auch ein Modem. Ein erweiterter Switch kann ebenfalls Routing übernehmen.</p>
            <div class="compare"><div><h3>Unmanaged Switch</h3><p>Arbeitet normalerweise ohne eigene Konfiguration.</p></div><div><h3>Managed Switch</h3><p>Bietet je nach Modell Verwaltungsfunktionen, etwa Anschlussüberwachung oder VLANs. Ein VLAN trennt Bereiche logisch innerhalb einer gemeinsamen Netzwerkinfrastruktur.</p></div></div>
        </section>

        <section id="aufbau" class="lesson-section">
            <div class="section-heading"><span>Verbindungen</span><h2>Wie ein Netzwerk aufgebaut ist</h2></div>
            <p>Eine <strong>Topologie</strong> beschreibt die Anordnung von Geräten oder Verbindungen. Gemeint sein kann die tatsächliche Verkabelung oder der logische Aufbau.</p>
            <div class="topology-grid">
                <div><svg viewBox="0 0 120 75" role="img" aria-label="Stern: vier Geräte sind einzeln mit einer zentralen Stelle verbunden"><g class="topology-lines"><path d="M60 38L22 13 M60 38L98 13 M60 38L22 63 M60 38L98 63"/></g><g class="topology-nodes"><circle cx="60" cy="38" r="7"/><circle cx="22" cy="13" r="6"/><circle cx="98" cy="13" r="6"/><circle cx="22" cy="63" r="6"/><circle cx="98" cy="63" r="6"/></g></svg><h3>Stern</h3><p>Geräte sind einzeln mit einer zentralen Stelle verbunden. Fällt eine Verbindung aus, betrifft das meist nur diesen Anschluss; fällt der zentrale Switch aus, sind alle daran angeschlossenen Geräte betroffen.</p></div>
                <div><svg viewBox="0 0 120 75" role="img" aria-label="Baum: Verbindungen verzweigen sich hierarchisch"><g class="topology-lines"><path d="M60 10L30 35L15 62 M30 35L45 62 M60 10L90 35L75 62 M90 35L105 62"/></g><g class="topology-nodes"><circle cx="60" cy="10" r="6"/><circle cx="30" cy="35" r="6"/><circle cx="90" cy="35" r="6"/><circle cx="15" cy="62" r="5"/><circle cx="45" cy="62" r="5"/><circle cx="75" cy="62" r="5"/><circle cx="105" cy="62" r="5"/></g></svg><h3>Baum</h3><p>Mehrere sternförmige Bereiche sind hierarchisch verbunden, etwa Etagen-Switches mit einem zentralen Switch.</p></div>
                <div><svg viewBox="0 0 120 75" role="img" aria-label="Bus: Geräte teilen sich eine gemeinsame Leitung"><g class="topology-lines"><path d="M8 48H112 M25 48V20 M60 48V20 M95 48V20"/></g><g class="topology-nodes"><circle cx="25" cy="20" r="6"/><circle cx="60" cy="20" r="6"/><circle cx="95" cy="20" r="6"/></g></svg><h3>Bus</h3><p>Geräte teilen sich eine gemeinsame Leitung. Bei heutigen Ethernet-LANs mit Switches ist das unüblich.</p></div>
                <div><svg viewBox="0 0 120 75" role="img" aria-label="Ring: Netzwerkkomponenten bilden einen geschlossenen Ring"><g class="topology-lines"><path d="M25 15H95V60H25Z"/></g><g class="topology-nodes"><circle cx="25" cy="15" r="6"/><circle cx="95" cy="15" r="6"/><circle cx="25" cy="60" r="6"/><circle cx="95" cy="60" r="6"/></g></svg><h3>Ring</h3><p>Komponenten sind zu einem geschlossenen Ring verbunden. Das Verhalten bei einem Ausfall hängt von der Technik ab.</p></div>
                <div><svg viewBox="0 0 120 75" role="img" aria-label="Mesh: mehrere Knoten sind über verschiedene Wege miteinander verbunden"><g class="topology-lines"><path d="M25 15H95V60H25Z M25 15L95 60 M95 15L25 60"/></g><g class="topology-nodes"><circle cx="25" cy="15" r="6"/><circle cx="95" cy="15" r="6"/><circle cx="25" cy="60" r="6"/><circle cx="95" cy="60" r="6"/></g></svg><h3>Mesh</h3><p>Knoten besitzen mehrere Verbindungen untereinander. Je nach Technik können alternative Wege verfügbar sein.</p></div>
            </div>
            <p>In modernen kabelgebundenen LANs ist die Sternstruktur häufig: Endgeräte sind jeweils mit einem zentralen Switch verbunden.</p>
            <h3 class="subheading">Verkabelung im Gebäude</h3>
            <p>Die feste <strong>Gebäudeverkabelung</strong> führt von den Netzwerkdosen der Räume zu zentralen Anschlusspunkten. Ein <strong>Patchpanel</strong> sammelt diese Leitungen. Kurze <strong>Patchkabel</strong> verbinden seine Anschlüsse mit den Switches. Geräte und Patchpanel stehen häufig in einem Netzwerkschrank.</p>
            <figure class="network-figure building-figure">
                <svg viewBox="0 0 740 140" role="img" aria-labelledby="building-title building-desc">
                    <title id="building-title">Gebäudeverkabelung</title><desc id="building-desc">Eine Leitung führt von der Netzwerkdose zum Patchpanel. Ein Patchkabel verbindet das Patchpanel mit dem Switch. Der Switch ist über eine Backbone-Verbindung mit einem weiteren Netzwerkbereich verbunden.</desc>
                    <g class="map-lines"><path d="M145 70H205 M340 70H400 M535 70H590"/></g>
                    <g class="map-node"><rect x="10" y="42" width="135" height="56" rx="12"/><text x="77" y="76">Netzwerkdose</text><rect x="205" y="42" width="135" height="56" rx="12"/><text x="272" y="76">Patchpanel</text><rect x="400" y="42" width="135" height="56" rx="12"/><text x="467" y="76">Switch</text><rect x="590" y="42" width="140" height="56" rx="12"/><text x="660" y="68" class="small-label">weiterer</text><text x="660" y="85" class="small-label">Netzwerkbereich</text></g><text x="175" y="30" class="map-note">feste Leitung</text><text x="370" y="30" class="map-note">Patchkabel</text><text x="562" y="30" class="map-note">Backbone</text>
                </svg>
                <figcaption>Eine mögliche Verbindung vom Raum bis zu einem weiteren Netzwerkbereich.</figcaption>
            </figure>
            <p>Ein <strong>Backbone</strong> ist eine leistungsfähige zentrale Verbindung zwischen größeren Netzwerkbereichen. Leitungen, Anschlüsse und Geräte müssen zur benötigten Datenrate und zu den Entfernungen passen.</p>
            <p>Eine <strong>USV</strong> (unterbrechungsfreie Stromversorgung) versorgt geeignete Geräte bei Stromausfall für begrenzte Zeit weiter. Die Dauer hängt von ihrer Kapazität und der angeschlossenen Last ab.</p>
        </section>
    </article>
</template>

<style scoped>
.network-lesson { max-width: 1160px; margin: 0 auto; color: #1b3049; font-size: 1rem; line-height: 1.7; }
.lesson-intro { display: flex; align-items: center; justify-content: space-between; gap: 32px; padding: clamp(24px, 5vw, 54px); border-radius: 18px; background: #eaf2fb; overflow: hidden; }
.lesson-intro h1 { max-width: 680px; margin: 6px 0 12px; color: #193c68; font-size: clamp(2rem, 4.3vw, 3.3rem); line-height: 1.12; letter-spacing: -.035em; }
.lesson-intro p { max-width: 55ch; margin: 0; color: #445e7d; font-size: 1.1rem; }
.lesson-intro .lesson-kicker { color: #326db6; font-size: .9rem; font-weight: 700; }
.intro-mark { position: relative; flex: 0 0 170px; width: 170px; height: 130px; background: linear-gradient(25deg, transparent 48%, #8eb4dd 49%, #8eb4dd 51%, transparent 52%), linear-gradient(-25deg, transparent 48%, #8eb4dd 49%, #8eb4dd 51%, transparent 52%); }
.intro-mark span { position: absolute; width: 28px; height: 28px; border: 5px solid #d8e9f9; border-radius: 50%; background: #386fae; }
.intro-mark span:nth-child(1) { left: 0; top: 0; }.intro-mark span:nth-child(2) { right: 0; top: 0; }.intro-mark span:nth-child(3) { left: 0; bottom: 0; }.intro-mark span:nth-child(4) { right: 0; bottom: 0; }
.lesson-nav { display: flex; flex-wrap: wrap; gap: 9px; padding: 18px 0; }
.lesson-nav a { padding: 7px 14px; border: 1px solid #cbd9e8; border-radius: 999px; background: #fff; color: #285a92; font-size: .91rem; font-weight: 600; text-decoration: none; }
.lesson-nav a:focus-visible { outline: 3px solid #2f6fb8; outline-offset: 3px; }
.lesson-section { scroll-margin-top: 100px; margin: 0 0 18px; padding: clamp(22px, 4vw, 42px); border: 1px solid #e0e6ed; border-radius: 16px; background: #fff; }
.section-heading span { color: #356da8; font-size: .84rem; font-weight: 700; }
.section-heading h2 { margin: 3px 0 18px; color: #193c68; font-size: clamp(1.55rem, 3vw, 2rem); line-height: 1.2; }
.lesson-section > p { max-width: 88ch; margin: 0 0 16px; }.lesson-section > p:last-child { margin-bottom: 0; }
.lesson-section h3 { margin: 0 0 5px; color: #254b75; font-size: 1.05rem; line-height: 1.3; }
.concept-grid, .compare { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px; overflow: hidden; margin: 22px 0; border: 1px solid #dce6f1; border-radius: 12px; background: #dce6f1; }
.concept-grid > div, .compare > div { min-width: 0; padding: 19px; background: #f6f9fd; }
.concept-grid p, .compare p, .topology-grid p { margin: 0; font-size: .95rem; line-height: 1.55; }
.concept-grid--two, .compare { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.network-figure { margin: 24px 0; padding: clamp(14px, 3vw, 26px); border: 1px solid #dbe7f3; border-radius: 14px; background: #f4f8fc; }
.network-figure svg { display: block; width: 100%; height: auto; overflow: visible; }
.network-figure figcaption { margin-top: 10px; color: #526a81; font-size: .88rem; line-height: 1.5; }
.map-lines path, .topology-lines path { fill: none; stroke: #6a98c9; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }.map-lines .wireless-line { stroke-dasharray: 5 5; }
.map-node rect { fill: #fff; stroke: #bfd2e6; stroke-width: 2; }.map-node text { fill: #244a74; font-size: 16px; font-weight: 700; text-anchor: middle; }.map-node .small-label { font-size: 12px; }.map-note { fill: #526a81; font-size: 11px; text-anchor: middle; }
.callout { margin: 22px 0; padding: 17px 20px; border-left: 4px solid #4382bd; border-radius: 0 10px 10px 0; background: #edf5fc; }.callout strong { color: #214c78; }.callout p { margin: 4px 0 0; }
.device-list { margin: 24px 0; border-top: 1px solid #dce6f1; }.device-list > div { display: grid; grid-template-columns: minmax(140px, 180px) 1fr; gap: 18px; padding: 14px 0; border-bottom: 1px solid #dce6f1; }.device-list dt { color: #254b75; font-weight: 700; }.device-list dd { margin: 0; }
.topology-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; margin: 24px 0; }.topology-grid > div { padding: 15px; border: 1px solid #dce6f1; border-radius: 12px; background: #f8fbfe; }.topology-grid svg { width: 100%; max-height: 80px; margin-bottom: 8px; }.topology-nodes circle { fill: #326fae; stroke: #e3effa; stroke-width: 2; }.topology-grid p { font-size: .86rem; }
.subheading { margin-top: 25px !important; font-size: 1.25rem !important; }
@media (max-width: 850px) { .topology-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.concept-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.intro-mark { flex-basis: 130px; width: 130px; height: 110px; } }
@media (max-width: 560px) { .intro-mark { display: none; }.lesson-section { padding: 22px 18px; }.concept-grid, .compare, .topology-grid { grid-template-columns: 1fr; }.topology-grid svg { width: 120px; }.device-list > div { grid-template-columns: 1fr; gap: 4px; }.network-figure { overflow-x: auto; }.network-figure svg { min-width: 520px; }.client-figure svg { min-width: 400px; }.building-figure svg { min-width: 520px; } }
</style>
