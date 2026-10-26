const commons = 'https://commons.wikimedia.org/wiki/File:'
const cc = 'https://creativecommons.org/'

const photos = {
    switch: { file: 'switch-avaya.jpg', alt: 'Foto eines Ethernet-Switches mit Netzwerkanschlüssen', author: 'Geek2003', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}2550T-PWR-Front.jpg`, original: true },
    router: { file: 'router.jpg', alt: 'Foto einer FRITZ!Box als Beispiel für einen Heimrouter', author: 'BrunoAleixo', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Fritzbox_Fon_WLAN_7390_vorne.jpg` },
    accessPoint: { file: 'access-point.jpg', alt: 'Foto eines WLAN-Access-Points', author: 'Pjpearce', license: 'CC0', licenseUrl: `${cc}publicdomain/zero/1.0/`, source: `${commons}Wireless_access_point.jpg` },
    modem: { file: 'modem.jpg', alt: 'Foto eines DSL-Modems', author: 'Feureau', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Linksys_ADSL_Modem_AM300.jpg` },
    ont: { file: 'ont.jpg', alt: 'Foto eines optischen Netzabschlusses für Glasfaser', author: 'Modesto Alexandre (L archi)', license: 'CC BY 1.0', licenseUrl: `${cc}licenses/by/1.0/`, source: `${commons}FTTH_ONU2.JPG` },
    firewall: { file: 'firewall.jpg', alt: 'Foto eines Firewall-Geräts als mögliche Bauform', author: 'Zuzu', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Netgear_ProSafe_Dual_WAN_VPN_Gigabit_Firewall_FVS336G_front.jpeg` },
    server: { file: 'server.jpg', alt: 'Foto mehrerer Server in einem Serverschrank', author: 'Inspiredbymetal', license: 'Gemeinfrei', source: `${commons}Dell_PowerEdge_2950.jpg` },
    nas: { file: 'nas.jpg', alt: 'Foto eines NAS-Geräts zur Datenspeicherung', author: 'Mro', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Synology_ds410.JPG` },
    computer: { file: 'computer.jpg', alt: 'Foto eines Laptops', author: 'KK IN HK', license: 'CC BY-SA 4.0', licenseUrl: `${cc}licenses/by-sa/4.0/`, source: `${commons}Laptop_PC.jpg` },
    smartphone: { file: 'smartphone.jpg', alt: 'Foto eines Smartphones', author: 'Océanos y dados', license: 'CC0', licenseUrl: `${cc}publicdomain/zero/1.0/`, source: `${commons}Smartphone_Use.jpg` },
    adapter: { file: 'adapter.jpg', alt: 'Foto einer Ethernet-Netzwerkkarte', author: 'Jana.Wiki', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Fast_Ethernet_PCI_Network_Interface_Card_SN5100TX_(VIA_VT86C100A).jpg` },
    dose: { file: 'dose.jpg', alt: 'Foto einer Netzwerkdose mit Ethernet-Anschluss', author: 'Tony Webster', license: 'CC BY 2.0', licenseUrl: `${cc}licenses/by/2.0/`, source: `${commons}Ethernet_Jack_on_Wall_-_CAT6.jpg` },
    patchpanel: { file: 'patchpanel.jpg', alt: 'Foto von Patchpanels in einem Netzwerkschrank', author: 'Robert.Harker', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Network_Cat6_Patch_Front.jpg` },
    usv: { file: 'usv.jpg', alt: 'Foto einer unterbrechungsfreien Stromversorgung', author: 'Hundehalter', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Apc_ups_rs500_06.jpg` },
}

export const networkDevicePhotos = {
    Switch: [photos.switch],
    Router: [photos.router],
    'Access Point': [photos.accessPoint],
    Modem: [photos.modem],
    'Glasfaser-ONT': [photos.ont],
    Firewall: [photos.firewall],
    Server: [photos.server],
    NAS: [photos.nas],
    Computer: [photos.computer],
    Smartphone: [photos.smartphone],
    Netzwerkadapter: [photos.adapter],
    Netzwerkdose: [photos.dose],
    Patchpanel: [photos.patchpanel],
    USV: [photos.usv],
}
