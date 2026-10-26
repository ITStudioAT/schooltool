const commons = 'https://commons.wikimedia.org/wiki/File:'
const cc = 'https://creativecommons.org/'

const photos = {
    switch: { file: 'switch-avaya.jpg', alt: 'Foto eines Ethernet-Switches mit Netzwerkanschlüssen', author: 'Geek2003', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}2550T-PWR-Front.jpg`, original: true },
    router: { file: 'router-cisco1800.jpg', alt: 'Foto eines Cisco-Routers der Serie 1800', author: 'Cisco Systems', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Cisco1800seriesrouter.jpg`, original: true },
    accessPoint: { file: 'access-point-ubiquiti.jpg', alt: 'Foto eines Ubiquiti-WLAN-Access-Points mit Zubehör', author: 'L3u', license: 'CC0', licenseUrl: `${cc}publicdomain/zero/1.0/`, source: `${commons}Ubiquiti_Unifi_AP.jpg` },
    modem: { file: 'modem-cisco-epc3010.jpg', alt: 'Foto eines Kabelmodems von Cisco', author: 'Kjetil Ree', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Cisco_Model_EPC3010.jpg` },
    ont: { file: 'ont.jpg', alt: 'Foto eines optischen Netzabschlusses für Glasfaser', author: 'Modesto Alexandre (L archi)', license: 'CC BY 1.0', licenseUrl: `${cc}licenses/by/1.0/`, source: `${commons}FTTH_ONU2.JPG` },
    server: { file: 'server-rack-tony-webster.jpg', alt: 'Foto mehrerer Server in einem Serverschrank', author: 'Tony Webster', license: 'CC BY 2.0', licenseUrl: `${cc}licenses/by/2.0/`, source: `${commons}Server_Rack_(54126210834).jpg` },
    nas: { file: 'nas-intel.jpg', alt: 'Foto eines NAS-Geräts mit vier Festplatteneinschüben', author: 'Lukas Gerhold (Ldi91)', license: 'CC BY-SA 2.0 DE', licenseUrl: `${cc}licenses/by-sa/2.0/de/`, source: `${commons}Intel_NAS.jpg`, original: true },
    computer: { file: 'computer.jpg', alt: 'Foto eines Laptops', author: 'KK IN HK', license: 'CC BY-SA 4.0', licenseUrl: `${cc}licenses/by-sa/4.0/`, source: `${commons}Laptop_PC.jpg` },
    adapter: { file: 'adapter.jpg', alt: 'Foto einer Ethernet-Netzwerkkarte', author: 'Jana.Wiki', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Fast_Ethernet_PCI_Network_Interface_Card_SN5100TX_(VIA_VT86C100A).jpg` },
    patchpanel: { file: 'patchpanel-keystone.jpg', alt: 'Foto eines Patchpanels für Keystone-Module', author: 'Phiarc', license: 'CC BY-SA 4.0', licenseUrl: `${cc}licenses/by-sa/4.0/`, source: `${commons}19%22_patchpanel_for_up_to_24_keystone_modules.jpg` },
    usv: { file: 'usv.jpg', alt: 'Foto einer unterbrechungsfreien Stromversorgung', author: 'Hundehalter', license: 'CC BY-SA 3.0', licenseUrl: `${cc}licenses/by-sa/3.0/`, source: `${commons}Apc_ups_rs500_06.jpg` },
}

export const networkDevicePhotos = {
    Switch: [photos.switch],
    Router: [photos.router],
    'Access Point': [photos.accessPoint],
    Modem: [photos.modem],
    'Glasfaser-ONT': [photos.ont],
    Server: [photos.server],
    NAS: [photos.nas],
    Computer: [photos.computer],
    Netzwerkadapter: [photos.adapter],
    Patchpanel: [photos.patchpanel],
    USV: [photos.usv],
}
