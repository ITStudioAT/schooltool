export function ipv4Network(address, prefix) {
    const octets = String(address).split('.')
    if (octets.length !== 4 || octets.some(value => !/^\d{1,3}$/.test(value) || Number(value) > 255)
        || !Number.isInteger(prefix) || prefix < 0 || prefix > 32) return null
    const numeric = octets.reduce((value, octet) => value * 256 + Number(octet), 0) >>> 0
    const mask = prefix === 0 ? 0 : (0xffffffff << (32 - prefix)) >>> 0
    const network = (numeric & mask) >>> 0
    const broadcast = (network | ~mask) >>> 0
    const dotted = value => [24, 16, 8, 0].map(shift => (value >>> shift) & 255).join('.')
    return {
        address, prefix, mask: dotted(mask), network: dotted(network), broadcast: dotted(broadcast),
        firstHost: dotted(prefix >= 31 ? network : network + 1),
        lastHost: dotted(prefix >= 31 ? broadcast : broadcast - 1),
        addressCount: 2 ** (32 - prefix),
        bits: numeric.toString(2).padStart(32, '0'), maskBits: mask.toString(2).padStart(32, '0'),
        networkBits: network.toString(2).padStart(32, '0'),
    }
}

export function matchingRoute(destination, routes) {
    return routes.filter(route => {
        const target = ipv4Network(destination, route.prefix)
        return target && target.network === ipv4Network(route.network, route.prefix)?.network
    }).sort((first, second) => second.prefix - first.prefix)[0] || null
}

export const networkScenarios = [
    { key: 'local', title: 'Drucker im eigenen Netz', address: '192.168.10.30', short: 'lokal · ohne Router' },
    { key: 'subnet', title: 'Server in einem anderen Subnetz', address: '192.168.20.30', short: 'zwischen VLANs · mit Router' },
    { key: 'internet', title: 'Webserver im Internet', address: '203.0.113.80', short: 'über Gateway · mit NAT' },
]

export const routerExampleRoutes = [
    { network: '192.168.10.0', prefix: 24, via: 'direkt ins Unterrichtsnetz · VLAN 10' },
    { network: '192.168.20.0', prefix: 24, via: 'direkt ins Servernetz · VLAN 20' },
    { network: '192.168.0.0', prefix: 16, via: 'zu einem weiteren Schulrouter' },
    { network: '0.0.0.0', prefix: 0, via: 'zum Provider · Standardroute' },
]

export const networkPaths = {
    toRouter: 'M100 165 L300 165 L500 270',
    fromRouter: 'M500 270 L300 165 L100 165',
    toPrinter: 'M100 165 L300 165 L500 65',
    fromPrinter: 'M500 65 L300 165 L100 165',
    toServer: 'M500 270 L790 300',
    fromServer: 'M790 300 L500 270',
    toWeb: 'M500 270 L690 150 L850 65',
    fromWeb: 'M850 65 L690 150 L500 270',
}
const paths = networkPaths

export function packetJourney(scenarioKey = 'internet', configuration = 'dhcp', blocked = false) {
    const scenario = networkScenarios.find(item => item.key === scenarioKey) || networkScenarios[2]
    const local = scenario.key === 'local'
    const internet = scenario.key === 'internet'
    const steps = []
    const add = (id, title, body, why, extras = {}) => steps.push({
        id, title, body, why, configured: true, concepts: [], active: 'pc', ...extras,
    })
    const outgoing = { source: '192.168.10.20', destination: scenario.address, sourceMac: 'MAC des PCs',
        nextMac: local ? 'MAC des Druckers' : 'MAC des Routers · VLAN 10' }
    const incoming = { source: scenario.address, destination: '192.168.10.20',
        sourceMac: local ? 'MAC des Druckers' : 'MAC des Routers · VLAN 10', nextMac: 'MAC des PCs' }

    add('link', 'Zuerst steht die lokale Verbindung',
        'Der PC ist an einem Switch-Anschluss im Unterrichts-VLAN 10 angeschlossen. Im Beispiel stellt der Router auch DHCP und DNS für dieses VLAN bereit.',
        'Die VLAN-Zuordnung ist schon konfiguriert. Sie grenzt den lokalen Broadcastbereich ab; ein VLAN ist kein Feld der IP-Adresse.',
        { configured: false, active: 'switch', concepts: ['VLAN'] })
    if (configuration === 'static') {
        add('static', 'Die Einstellungen werden von Hand eingetragen',
            'Der PC erhält 192.168.10.20/24, Gateway 192.168.10.1 und DNS-Server 192.168.10.1. Die Adresse muss frei sein und zum Netz passen.',
            'Bei statischer Konfiguration entfallen die vier DHCP-Nachrichten. Die folgenden Entscheidungen brauchen dieselben passenden Einstellungen.',
            { concepts: ['IP-Adresse', 'Präfix', 'Gateway'] })
    } else {
        add('discover', 'DHCP Discover · Wer kann mich konfigurieren?',
            'Der PC hat noch keine verwendbare IPv4-Konfiguration. Er sucht per Broadcast einen DHCP-Server im lokalen VLAN.',
            'Dafür muss er noch keine Ziel-IP im eigenen Subnetz vergleichen. Ein Router leitet solche Broadcasts nicht einfach in andere VLANs weiter; dafür wäre ein DHCP-Relay nötig.',
            { configured: false, active: 'pc', path: paths.toRouter, concepts: ['DHCP', 'VLAN'], source: '0.0.0.0', destination: '255.255.255.255', sourceMac: 'MAC des PCs', nextMac: 'Broadcast · ff:ff:ff:ff:ff:ff' })
        add('offer', 'DHCP Offer · Ein Konfigurationsvorschlag',
            'Der DHCP-Server bietet dem PC die Adresse 192.168.10.20 samt Netzwerkeinstellungen an. In diesem Beispiel sendet er das Angebot als Broadcast an alle Geräte im VLAN. Anhand einer Kennung erkennt der PC, dass das Angebot zu seiner Anfrage gehört.',
            'Ein Angebot allein ist noch nicht die abgeschlossene Zuweisung.',
            { configured: false, active: 'router', path: paths.fromRouter, concepts: ['DHCP'] })
        add('request', 'DHCP Request · Dieses Angebot möchte ich nutzen',
            'Der PC sendet eine DHCP-Request-Nachricht. Darin nennt er den ausgewählten DHCP-Server und die angebotene IP-Adresse 192.168.10.20. So erfahren die DHCP-Server im Unterrichts-VLAN, welches Angebot er annehmen möchte.',
            'Bei der erstmaligen Zuweisung bestätigt der Client damit seine Auswahl gegenüber den DHCP-Servern.',
            { configured: false, active: 'pc', path: paths.toRouter, concepts: ['DHCP', 'VLAN'] })
        add('ack', 'DHCP ACK · Jetzt sind Adresse und Präfix verfügbar',
            'Der Server bestätigt die Zuweisung: IP 192.168.10.20, Netzmaske 255.255.255.0 = /24, Gateway und DNS jeweils 192.168.10.1.',
            'Erst mit einer gültigen Konfiguration kann der PC seine normalen IP-Wege bestimmen. Die DHCP-Lease gilt begrenzt und kann verlängert werden.',
            { active: 'router', path: paths.fromRouter, concepts: ['DHCP', 'IP-Adresse', 'Präfix', 'Gateway'] })
    }
    if (internet) {
        add('dns-route', 'Auch die DNS-Anfrage braucht zuerst einen Weg',
            'Der Browser benötigt die IP-Adresse von www.beispiel.test. Im Beispiel gibt es keinen DNS-Cachetreffer; der DNS-Server ist 192.168.10.1.',
            'Der PC prüft mit seiner /24-Maske: Der DNS-Server liegt im eigenen Netz 192.168.10.0/24 und ist lokal erreichbar.',
            { concepts: ['Präfix', 'Subnetz', 'DNS'], compare: '192.168.10.1' })
        add('dns-arp', 'ARP ermittelt die lokale MAC-Adresse des DNS-Servers',
            'Der PC fragt im VLAN 10 nach der MAC-Adresse zu 192.168.10.1. Die Router-Schnittstelle antwortet; der PC merkt sich die Zuordnung im ARP-Cache.',
            'ARP überträgt noch nicht die eigentliche Webseitenanfrage. Es findet die Adresse für den lokalen Ethernet-Abschnitt.',
            { path: paths.toRouter, active: 'switch', concepts: ['ARP', 'VLAN'] })
        add('dns-query', 'DNS · Der PC fragt nach der Serveradresse',
            'Die DNS-Anfrage geht über den Switch an den lokalen Resolver 192.168.10.1. Falls nötig, fragt dieser weitere DNS-Server; dieser Teil ist hier zusammengefasst.',
            'Die Ziel-IP dieses DNS-Pakets ist der DNS-Server. Sie ist noch nicht die IP-Adresse des Webservers.',
            { path: paths.toRouter, concepts: ['DNS'], source: '192.168.10.20', destination: '192.168.10.1', sourceMac: 'MAC des PCs', nextMac: 'MAC des Routers · VLAN 10' })
        add('dns-answer', 'DNS-Antwort · Das Ziel der Webseite steht fest',
            'Der Resolver liefert im Beispiel 203.0.113.80 zurück. Jetzt kann der PC die Webseite an diese Ziel-IP ansprechen.',
            'Namensauflösung und eigentlicher Zugriff sind zwei getrennte Vorgänge. www.beispiel.test und die öffentlichen Adressen hier sind reine Beispiele.',
            { path: paths.fromRouter, active: 'router', concepts: ['DNS'], source: '192.168.10.1', destination: '192.168.10.20' })
    }
    add('network', 'Der PC vergleicht Netzanteile',
        `Eigene IP UND Maske ergibt 192.168.10.0. Ziel ${scenario.address} UND dieselbe Maske ergibt ${ipv4Network(scenario.address, 24).network}.`,
        local ? 'Die Netzanteile stimmen überein. Die direkt angeschlossene Route passt; der PC stellt lokal zu.'
            : 'Die Netzanteile unterscheiden sich. Die lokale /24-Route passt nicht. Im PC ist keine weitere spezifische Route konfiguriert, deshalb wählt er die Standardroute über 192.168.10.1.',
        { concepts: ['Präfix', 'Subnetz', 'Routing'], compare: scenario.address })
    add('neighbor', internet ? 'Die Gateway-MAC ist bereits im Cache' : local ? 'ARP fragt nach dem Drucker' : 'ARP fragt nach dem Gateway',
        internet ? 'DNS-Server und Standardgateway nutzen hier dieselbe Router-Schnittstelle 192.168.10.1. Deren MAC-Adresse ist seit der DNS-Anfrage bekannt; es ist kein erneuter ARP-Austausch nötig.'
            : `Der PC benötigt die MAC-Adresse von ${local ? '192.168.10.30' : '192.168.10.1'}. Die ARP-Anfrage bleibt im VLAN 10; der angesprochene Nachbar antwortet.`,
        'Die IP-Zieladresse und der nächste lokale Empfänger erfüllen unterschiedliche Aufgaben. Ein Cachetreffer spart einen erneuten Suchaustausch.',
        { concepts: ['ARP', 'Gateway'], path: internet ? null : local ? paths.toPrinter : paths.toRouter })
    add('local-frame', local ? 'Der Rahmen geht direkt zum Drucker' : 'Der Rahmen geht zum nächsten Router',
        `Der Switch vermittelt im VLAN 10 anhand der Ziel-MAC. Das IP-Paket hat weiterhin das eigentliche Ziel ${scenario.address}.`,
        local ? 'Drucker und PC liegen im selben Subnetz und lokalen VLAN. Der Router wird für diese Zustellung nicht gebraucht.'
            : 'Im Ethernet-Kopf steht die Gateway-MAC; im IP-Kopf steht die Server-IP. Das Gateway ersetzt nicht die IP-Adresse des eigentlichen Ziels.',
        { ...outgoing, active: 'switch', path: local ? paths.toPrinter : paths.toRouter, concepts: ['IP-Adresse', 'VLAN', 'Gateway'] })
    if (!local) {
        add('router-route', 'Der Router wählt die spezifischste passende Route',
            internet ? 'Für 203.0.113.80 passt in dieser Tabelle nur die Standardroute 0.0.0.0/0 zum Provider.'
                : 'Für 192.168.20.30 passen /24, /16 und /0. Die /24-Route ist am spezifischsten und führt direkt ins Servernetz.',
            'Der Router entfernt den eingehenden Ethernet-Rahmen. Er entscheidet anhand der Ziel-IP und seiner eigenen Routingtabelle; die Netzmaske des PCs wird nicht als Wegbeschreibung mitgesendet.',
            { ...outgoing, active: 'router', concepts: ['Präfix', 'Routing'], routeTarget: scenario.address })
        add('firewall', blocked ? 'Die Firewall blockiert diese Verbindung' : 'Die Firewallregel erlaubt die Verbindung',
            blocked ? 'Die Adressen und Routen passen, aber die konfigurierte Zugriffsregel verweigert die Weiterleitung. Das Paket erreicht den Zielserver nicht.'
                : 'Im Beispiel ist dieser Verkehr erlaubt. Eine passende IP-Adresse oder Route allein erteilt noch keine Zugriffsberechtigung.',
            'Routing bestimmt den Weg; die Firewall entscheidet über erlaubten Verkehr. Das genaue Zusammenspiel im Gerät hängt von dessen Konfiguration ab.',
            { ...outgoing, active: 'router', concepts: ['Firewall', 'VLAN'], blocked })
        if (blocked) return steps
        if (internet) {
            add('nat', 'NAT mit Portübersetzung trägt die Verbindung ein',
                'Für die Beispielverbindung wird 192.168.10.20:53000 nach außen zu 198.51.100.7:62001. Das Ziel bleibt 203.0.113.80:443.',
                'Die private Quelladresse wird für diesen Internetzugang übersetzt. Die gespeicherte Portzuordnung ermöglicht später die Rückübersetzung. NAT ersetzt keine Firewall.',
                { ...outgoing, source: '198.51.100.7', sourceMac: 'MAC der WAN-Schnittstelle', nextMac: 'MAC des nächsten Providerrouters', active: 'router', concepts: ['NAT'], nat: true })
            add('outward', 'Weitere Router bringen die Anfrage zum Webserver',
                'Der Provider und weitere Router wählen jeweils den nächsten Weg. Am Ziel verarbeitet der Webserver die Anfrage; TCP-Verbindungsaufbau, TLS und weitere Einzelpakete sind hier zusammengefasst.',
                'Jeder geroutete Ethernet-Abschnitt hat einen eigenen Rahmen mit lokalen MAC-Adressen. Die Ziel-IP bleibt hier gleich; NAT hat die Quell-IP übersetzt.',
                { source: '198.51.100.7', destination: scenario.address, sourceMac: 'jeweils lokale MAC', nextMac: 'jeweils nächster Nachbar', active: 'web', path: paths.toWeb, concepts: ['Routing', 'NAT'], nat: true })
            add('return', 'Die Antwort kommt an die öffentliche Adresse zurück',
                'Die Antwort lautet auf IP-Ebene 203.0.113.80 → 198.51.100.7. Ihr Zielport 62001 gehört zur zuvor gespeicherten Zuordnung.',
                'Auch der Rückweg braucht funktionierende Routen. Er kann über andere Zwischenrouter führen als der Hinweg.',
                { source: scenario.address, destination: '198.51.100.7', active: 'router', path: paths.fromWeb, concepts: ['Routing', 'NAT'], nat: true })
            add('reverse-nat', 'Die NAT-Tabelle ordnet die Antwort dem PC zu',
                '198.51.100.7:62001 wird wieder auf 192.168.10.20:53000 abgebildet. Der Router prüft den erlaubten Rückverkehr und verwendet die Route ins Unterrichtsnetz.',
                'Ohne die passende Zuordnung ist diese Antwort keiner solchen internen Verbindung zugeordnet. Die IP-Zieladresse wird hier zurückübersetzt.',
                { ...incoming, active: 'router', concepts: ['NAT', 'Routing'], nat: true })
        } else {
            add('server-arp', 'Im Ziel-VLAN wird die Server-MAC ermittelt',
                'Die Router-Schnittstelle 192.168.20.1 sucht per ARP nach 192.168.20.30 im VLAN 20. Der Server antwortet.',
                'Die frühere ARP-Anfrage aus VLAN 10 wird nicht durchgereicht. Der Router führt die Nachbarauflösung auf seinem ausgehenden Abschnitt selbst durch.',
                { active: 'router', path: paths.toServer, concepts: ['ARP', 'VLAN'] })
            add('server-frame', 'Ein neuer Rahmen erreicht das andere Subnetz',
                'Der Router sendet im VLAN 20 an die MAC des Servers. Im IP-Paket stehen weiterhin 192.168.10.20 als Quelle und 192.168.20.30 als Ziel.',
                'Zwischen diesen internen Netzen findet im Beispiel kein NAT statt. Neue MAC-Adressen bedeuten nicht automatisch neue IP-Adressen.',
                { ...outgoing, sourceMac: 'MAC des Routers · VLAN 20', nextMac: 'MAC des Servers', active: 'server', path: paths.toServer, concepts: ['Routing', 'VLAN'] })
            add('server-return', 'Der Server schickt die Antwort über sein Gateway',
                'Mit seinem eigenen /24-Präfix erkennt der Server: 192.168.10.20 liegt außerhalb seines Netzes 192.168.20.0/24. Er nutzt sein Gateway 192.168.20.1.',
                'Der Router wählt für die Antwort die Route 192.168.10.0/24 ins VLAN 10. Hin- und Rückrichtung werden jeweils entschieden.',
                { ...incoming, sourceMac: 'MAC des Servers', nextMac: 'MAC des Routers · VLAN 20', active: 'server', path: paths.fromServer, concepts: ['Präfix', 'Routing', 'Gateway'] })
        }
    }
    add('delivery', 'Die Antwort erreicht den PC im lokalen Netz',
        local ? 'Der Drucker antwortet im selben VLAN direkt an die MAC des PCs. Quell- und Ziel-IP sind gegenüber der Anfrage vertauscht.'
            : 'Der Router sendet einen Rahmen aus VLAN 10 an die MAC des PCs. Die passende Nachbarzuordnung ist hier bereits bekannt.',
        'Der PC entnimmt dem Rahmen das IP-Paket und reicht die Nutzdaten an die passende Anwendung weiter. Im Beispiel ist der gesamte Hin- und Rückweg abgeschlossen.',
        { ...incoming, active: 'pc', path: local ? paths.fromPrinter : paths.fromRouter,
            concepts: ['IP-Adresse', 'VLAN'], nat: internet, complete: true })
    return steps
}
