<template>
    <div v-if="photo" ref="paletteItem" class="network-device-photo__item"
        @pointerenter="openHover" @pointerleave="scheduleClose">
        <span @pointerdown.capture="close(false)"><slot /></span>
        <v-btn ref="photoButton" type="button" icon="mdi-image-outline" variant="tonal" color="primary" size="44"
            :aria-label="`Foto anzeigen: ${device.label}`" :aria-expanded="visible" aria-haspopup="dialog"
            :aria-controls="visible ? dialogId : undefined" @pointerdown="startActionTap"
            @pointerup="finishActionTap($event, openDialog)" @pointercancel="cancelActionTap"
            @click.stop="clickAction($event, openDialog)" @keyup.esc.stop />
        <v-overlay v-model="visible" :activator="paletteItem" :open-on-click="false" :scrim="false" :offset="6"
            location-strategy="connected" location="top center" scroll-strategy="reposition" :transition="false">
            <div :id="dialogId" class="network-device-photo__dialog" role="dialog" :aria-label="`Foto: ${device.label}`"
                @pointerenter="cancelClose" @pointerleave="scheduleClose" @keydown.esc.stop.prevent="close(true)">
                <div class="network-device-photo__heading">
                    <strong>{{ device.label }}</strong>
                    <v-btn ref="closeButton" type="button" icon="mdi-close" variant="text" size="44"
                        aria-label="Foto schließen" @pointerdown="startActionTap"
                        @pointerup="finishActionTap($event, () => close(true))" @pointercancel="cancelActionTap"
                        @click.stop="clickAction($event, () => close(true))" />
                </div>
                <img :src="photo.image" :alt="photo.alt" />
                <p class="text-caption mt-2">
                    {{ photo.author }} · <a :href="photo.source" :title="photo.title"
                        :aria-label="`${photo.captureDate ? 'Bildquelle' : 'Originalfoto'}: ${photo.title}`"
                        target="_blank" rel="noopener noreferrer">
                        {{ photo.captureDate ? photo.title : 'Originalfoto' }}
                    </a>
                    <template v-if="photo.captureDate"> · Bildschirmaufnahme vom {{ photo.captureDate }}</template>
                    <template v-else>
                        · <a :href="photo.licenseUrl" target="_blank" rel="noopener noreferrer">{{ photo.license }}</a>
                        · {{ photo.edits ?? 'skaliert' }}
                    </template>
                </p>
            </div>
        </v-overlay>
    </div>
    <slot v-else />
</template>

<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue'
import accessPoint from '../../../../images/teaching-tools/network-devices/access-point.webp'
import cableDuct from '../../../../images/teaching-tools/network-devices/cable-duct.webp'
import doubleOutlet from '../../../../images/teaching-tools/network-devices/double-outlet.webp'
import ethernetSwitch from '../../../../images/teaching-tools/network-devices/ethernet-switch.webp'
import patchCable from '../../../../images/teaching-tools/network-devices/patch-cable.webp'
import router from '../../../../images/teaching-tools/network-devices/router-cisco-c8375.webp'
import serverRack from '../../../../images/teaching-tools/network-devices/server-rack.webp'
import singleOutlet from '../../../../images/teaching-tools/network-devices/single-outlet.webp'
import { useNetworkTapAction } from './useNetworkTapAction'

const props = defineProps({ device: { type: Object, required: true } })
const photos = {
    'network-outlet': {
        image: singleOutlet,
        alt: 'Weiße montierte Wandblende mit einer einzelnen RJ45-Netzwerkbuchse',
        author: 'Alec',
        title: 'How can I supply internet to the ethernet ports in my house?',
        source: 'https://diy.stackexchange.com/questions/296374/how-can-i-supply-internet-to-the-ethernet-ports-in-my-house',
        license: 'CC BY-SA 4.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/',
        edits: 'zugeschnitten, aufgehellt und skaliert',
    },
    'double-network-outlet': {
        image: doubleOutlet,
        alt: 'Geschlossene weiße Wandblende mit zwei sichtbaren RJ45-Netzwerkbuchsen',
        author: 'Aaron J Spetner',
        title: 'Dual Ethernet jack',
        source: 'https://networkengineering.stackexchange.com/questions/41615/two-ethernet-jacks-causing-each-other-problems',
        license: 'CC BY-SA 3.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/3.0/',
        edits: 'zugeschnitten und skaliert',
    },
    'cable-duct': {
        image: cableDuct,
        alt: 'Weißer rechteckiger Kabelkanal mit geschlossenem Deckel an einer Innenwand',
        author: 'Aaron J Spetner',
        title: 'Dual Ethernet jack, Ausschnitt des Kabelkanals',
        source: 'https://networkengineering.stackexchange.com/questions/41615/two-ethernet-jacks-causing-each-other-problems',
        license: 'CC BY-SA 3.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/3.0/',
        edits: 'zugeschnitten, aufgehellt und skaliert',
    },
    'server-rack': {
        image: serverRack,
        alt: 'Aufrechter schwarzer Serverschrank mit Fronttür',
        author: 'Krzysztofmika',
        title: 'Szafa serwerowa ZPAS',
        source: 'https://commons.wikimedia.org/wiki/File:Szafa_serwerowa_ZPAS.jpg',
        license: 'CC BY-SA 3.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/3.0/',
    },
    switch: {
        image: ethernetSwitch,
        alt: 'Front eines Ethernet-Switches mit zwei Reihen sichtbarer Netzwerkbuchsen',
        author: 'Geek2003',
        title: '2550T-PWR-Front',
        source: 'https://commons.wikimedia.org/wiki/File:2550T-PWR-Front.jpg',
        license: 'CC BY-SA 3.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/3.0/',
        edits: 'zugeschnitten und skaliert',
    },
    'patch-cable': {
        image: patchCable,
        alt: 'Kurzes Netzwerkkabel mit zwei sichtbaren RJ45-Steckern',
        author: 'Uwe Schwöbel',
        title: 'Patchkabel Kat5',
        source: 'https://commons.wikimedia.org/wiki/File:Patchkabel_Kat5.jpg',
        license: 'CC BY-SA 3.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/3.0/',
        edits: 'Bildbeschriftung entfernt und skaliert',
    },
    router: {
        image: router,
        alt: 'Cisco C8375-E-G2, silberner Rackrouter mit Netzwerkanschlüssen an der Vorderseite',
        author: 'Courtesy of Cisco Systems, Inc. Unauthorized use not permitted',
        title: 'Cisco C8375-E-G2',
        source: 'https://www.cisco.com/content/dam/cisco-cdc/site/images/photography/products/networking/sdwan-routers/8000-secure-routers/8300-series-c8375-g2-1600x900.jpg',
        captureDate: '26.11.2026',
    },
    'access-point': {
        image: accessPoint,
        alt: 'An der Decke montierter UniFi Access Point mit blauem Leuchtring',
        author: 'Sabbo91',
        title: 'Access Point UniFi',
        edits: 'zugeschnitten',
        source: 'https://commons.wikimedia.org/wiki/File:Access_Point_UniFi.jpg',
        license: 'CC BY-SA 4.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/',
    },
}
const photo = photos[props.device.id]
const dialogId = `network-device-photo-${props.device.id}`
const paletteItem = ref(null)
const photoButton = ref(null)
const closeButton = ref(null)
const visible = ref(false)
const { startActionTap, finishActionTap, cancelActionTap, clickAction } = useNetworkTapAction()
let pinned = false
let closeTimer

watch(visible, (open) => {
    if (!open) pinned = false
})
watch(closeButton, () => {
    if (pinned) focusCloseButton()
}, { flush: 'post' })
onUnmounted(cancelClose)

function openHover(event) {
    if (event.pointerType === 'mouse' && event.buttons === 0) {
        cancelClose()
        visible.value = true
    }
}

async function openDialog() {
    if (pinned) {
        close(true)
        return
    }
    cancelClose()
    pinned = true
    visible.value = true
    await nextTick()
    focusCloseButton()
}

function focusCloseButton() {
    closeButton.value?.$el.focus({ preventScroll: true })
}

function scheduleClose(event) {
    if (event.pointerType === 'mouse' && !pinned) {
        cancelClose()
        closeTimer = setTimeout(() => close(false), 180)
    }
}

function cancelClose() {
    clearTimeout(closeTimer)
}

function close(restoreFocus) {
    cancelClose()
    visible.value = false
    pinned = false
    if (restoreFocus) photoButton.value?.$el.focus({ preventScroll: true })
}
</script>

<style scoped>
.network-device-photo__item,
.network-device-photo__heading {
    display: flex;
    align-items: center;
    gap: 4px;
}

.network-device-photo__heading {
    justify-content: space-between;
}

.network-device-photo__dialog {
    inline-size: min(244px, calc(100vw - 32px));
    padding: 6px 12px 12px;
    border: 1px solid #c6d1de;
    border-radius: 6px;
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 3px 12px #17345433;
}

.network-device-photo__dialog img {
    display: block;
    inline-size: 100%;
    block-size: 184px;
    object-fit: contain;
    background: #eef3f9;
}
</style>
