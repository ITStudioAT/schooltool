<template>
    <section class="network-floor" aria-labelledby="ground-floor-title" @pointermove="movePointer"
        @pointerup="finishPointer" @pointercancel="cancelPointer" @lostpointercapture="cancelPointer"
        @keydown.esc="patch.cancel"
        @click.self="clearPlacementSelection">
        <h2 id="ground-floor-title" class="text-h6 font-weight-bold mb-3">Erdgeschoss</h2>
        <div ref="zoomControls" class="network-floor__controls mb-3" role="group" aria-label="Grundriss-Zoom">
            <v-btn type="button" variant="outlined" height="44" min-width="44" class="network-floor__zoom-step"
                aria-label="Grundriss verkleinern" :disabled="zoom <= minZoom" @pointerdown="startActionTap"
                @pointerup="finishActionTap($event, () => adjustZoom(-zoomStep))" @pointercancel="cancelActionTap"
                @click="clickAction($event, () => adjustZoom(-zoomStep))">−</v-btn>
            <output class="network-floor__zoom-value" aria-label="Zoomstufe">{{ zoom }} %</output>
            <v-btn type="button" variant="outlined" height="44" min-width="44" class="network-floor__zoom-step"
                aria-label="Grundriss vergrößern" :disabled="zoom >= maxZoom" @pointerdown="startActionTap"
                @pointerup="finishActionTap($event, () => adjustZoom(zoomStep))" @pointercancel="cancelActionTap"
                @click="clickAction($event, () => adjustZoom(zoomStep))">+</v-btn>
            <v-btn type="button" variant="text" height="44" class="text-none"
                @pointerdown="startActionTap" @pointerup="finishActionTap($event, resetZoom)" @pointercancel="cancelActionTap"
                @click="clickAction($event, resetZoom)">Zurücksetzen</v-btn>
            <div v-if="patchActive || patchStatus" class="network-floor__patch-instructions">
                <span role="status">{{ patchStatus }}</span>
                <v-btn v-if="patchActive" type="button" variant="text" height="44" class="text-none"
                    @pointerdown="startActionTap" @pointerup="finishActionTap($event, patch.cancel)" @pointercancel="cancelActionTap"
                    @click="clickAction($event, patch.cancel)">Abbrechen</v-btn>
            </div>
        </div>
        <div class="network-floor__workspace" :style="{ '--floor-zoom': zoom / 100 }">
            <section class="network-floor__task" aria-labelledby="ground-floor-task-title">
                <h2 id="ground-floor-task-title" class="network-floor__task-title font-weight-bold mb-2">
                    Aufgabenbeschreibung
                </h2>
                <ol class="network-floor__tasks">
                    <li>
                        Platziere insgesamt <strong>fünf Arbeitsplätze</strong> in Büro 1 und Büro 2.
                        In einem Büro sollen drei Arbeitsplätze stehen, im anderen zwei.
                        Du entscheidest, welches Büro drei Arbeitsplätze bekommt.
                    </li>
                    <li>Im <strong>Foyer</strong> vier Stehtische für Besucher:innen platzieren, die mit ihren Laptops kommen.</li>
                </ol>
            </section>
            <div ref="viewport" class="network-floor__viewport" tabindex="0" role="region" aria-label="Scrollbarer Grundriss"
                @click.self="clearPlacementSelection">
                <div class="network-floor__extent" @click.self="clearPlacementSelection">
                    <div class="network-floor__plan" role="group" aria-label="Grundriss" @click.self="clearPlacementSelection">
                        <div class="network-floor__room">
                            Büro 1
                            <span class="network-floor__door" aria-hidden="true" />
                        </div>
                        <div class="network-floor__room">
                            Büro 2
                            <span class="network-floor__door" aria-hidden="true" />
                        </div>
                        <div class="network-floor__room">
                            Serverraum
                            <span class="network-floor__door" aria-hidden="true" />
                        </div>
                        <div class="network-floor__room network-floor__foyer">
                            Foyer
                            <span class="network-floor__door network-floor__entrance" aria-hidden="true" />
                        </div>
                        <div ref="layer" class="network-floor__placements"
                            :class="{ 'is-selecting': canPlaceBySelection, 'is-wiring': patchActive }"
                            :style="{ '--furniture-scale': furnitureScale, '--channel-stroke-screen': `${channelStroke}px` }"
                            role="group" aria-label="Geräte im Grundriss" tabindex="0"
                            @click="clickPlanOrCable" @pointerdown.self="startSurfaceTap" @pointerup.self="finishSurfaceTap"
                            @keydown.enter.self.prevent="patchActive ? patch.rejectTarget() : placeAtCenter()">
                            <svg class="network-floor__patch-lines" viewBox="0 0 100 100" preserveAspectRatio="none">
                                <g v-for="cable in patchConnections" :key="cable.id"
                                    :class="{ 'is-selected': selectedPatchId === cable.id }"
                                    :tabindex="patchActive ? -1 : 0" :role="patchActive ? undefined : 'button'"
                                    :aria-label="cable.label" :aria-pressed="selectedPatchId === cable.id" :data-patch-id="cable.id"
                                    @pointerdown="startPatchLineTap" @pointercancel="patchTap.cancelActionTap"
                                    @pointerup="finishPatchLineTap($event, cable.id)"
                                    @click.stop="clickPatchLine($event, cable.id)"
                                    @keydown.enter.prevent="patch.selectConnection(cable.id)"
                                    @keydown.space.prevent="patch.selectConnection(cable.id)">
                                    <line v-if="!patchActive" class="network-floor__patch-hit" aria-hidden="true"
                                        :x1="cable.start.x * 100" :y1="cable.start.y * 100" :x2="cable.end.x * 100" :y2="cable.end.y * 100" />
                                    <line class="network-floor__patch-outline" aria-hidden="true"
                                        :x1="cable.start.x * 100" :y1="cable.start.y * 100" :x2="cable.end.x * 100" :y2="cable.end.y * 100" />
                                    <line class="network-floor__patch-line" aria-hidden="true"
                                        :x1="cable.start.x * 100" :y1="cable.start.y * 100" :x2="cable.end.x * 100" :y2="cable.end.y * 100" />
                                </g>
                                <g v-if="patchPreview" class="network-floor__patch-preview"
                                    :class="{ 'is-invalid': patchPreview.invalid }" aria-hidden="true">
                                    <line class="network-floor__patch-outline"
                                        :x1="patchPreview.start.x * 100" :y1="patchPreview.start.y * 100"
                                        :x2="patchPreview.end.x * 100" :y2="patchPreview.end.y * 100" />
                                    <line class="network-floor__patch-line"
                                        :x1="patchPreview.start.x * 100" :y1="patchPreview.start.y * 100"
                                        :x2="patchPreview.end.x * 100" :y2="patchPreview.end.y * 100" />
                                </g>
                            </svg>
                            <v-btn v-if="selectedPatch" type="button" icon="mdi-close" variant="flat" color="error" size="44"
                                class="network-floor__patch-remove" aria-label="Patchkabel entfernen"
                                :style="{ '--patch-center-x': `${(selectedPatch.start.x + selectedPatch.end.x) * 50}%`,
                                    '--patch-center-y': `${(selectedPatch.start.y + selectedPatch.end.y) * 50}%` }"
                                @pointerdown.stop="startPlanActionTap" @pointerup.stop="finishPlanActionTap($event, removePatch)"
                                @pointercancel="cancelActionTap" @click.stop="clickPatchRemove" />
                            <button v-for="placement in placements" :key="placement.id" type="button"
                                :ref="element => setMarkerElement(placement.id, element)"
                                class="network-floor__marker" :class="[deviceShape(placement),
                                    { 'is-selected': selection?.placementId === placement.id,
                                        'is-patch-start': patchFirstEnd?.placementId === placement.id,
                                        'is-patch-target': patchActive && patch.availablePort(placement) },
                                    wallsById.get(placement.wallId)?.axis]"
                                :data-placement-id="placement.id" :data-wall="placement.wallId"
                                :data-channel-id="placement.channelId"
                                :data-wall-side="wallsById.get(placement.wallId)?.side"
                                :style="markerStyle(placement)"
                                :aria-label="markerLabel(placement)" :title="placement.device.label"
                                :aria-pressed="selection?.placementId === placement.id"
                                :aria-controls="actionPosition?.id === placement.id ? 'network-floor-marker-actions' : undefined"
                                @pointerdown="startMarkerDrag($event, placement)" @pointerup="finishMarkerTap($event, placement)"
                                @pointercancel="patchTap.cancelActionTap"
                                @click.stop="selectMarker($event, placement)" @keydown.esc.stop="escapeMarker">
                                <template v-if="placement.wallId">
                                    <span class="network-floor__channel-segment" aria-hidden="true" />
                                    <span class="network-floor__channel-label" aria-hidden="true">Kabelkanal</span>
                                    <span class="network-floor__channel-anchor" aria-hidden="true" />
                                </template>
                                <span v-else-if="placement.device.id === 'workstation'" class="network-floor__workstation" aria-hidden="true">
                                    <span class="network-floor__workstation-graphic">
                                        <NetworkDeviceIcon :device="placement.device" />
                                    </span>
                                    <span class="network-floor__workstation-label">Arbeitsplatz</span>
                                </span>
                                <span v-else class="network-floor__device-drawing" aria-hidden="true">
                                    <NetworkDeviceIcon :device="placement.device" />
                                    <span v-if="!placement.device.channelMounted" class="network-floor__object-label">
                                        {{ placement.device.label }}
                                    </span>
                                </span>
                            </button>
                            <span v-if="drag?.moved && drag.position?.wallId" aria-hidden="true"
                                class="network-floor__marker is-cable-duct network-floor__channel-preview"
                                :class="wallsById.get(drag.position.wallId)?.axis"
                                :style="markerStyle(drag.position)" :data-wall="drag.position.wallId"
                                :data-wall-side="wallsById.get(drag.position.wallId)?.side">
                                <span class="network-floor__channel-segment" />
                            </span>
                            <span v-for="corner in visibleChannelCorners" :key="corner.key"
                                class="network-floor__channel-corner" :class="[corner.arms, { 'is-preview': corner.preview }]"
                                :style="cornerStyle(corner)" aria-hidden="true" />
                            <span v-if="drag?.moved && drag.position?.channelId" aria-hidden="true"
                                class="network-floor__marker is-channel-mounted network-floor__outlet-preview"
                                :style="markerStyle(drag.position)" :data-channel-id="drag.position.channelId">
                                <NetworkDeviceIcon :device="drag.device" />
                            </span>
                            <span v-if="patchActive && patchHover && !patchHover.port" aria-hidden="true"
                                class="network-floor__drag-invalid network-floor__patch-invalid"
                                :style="{ left: `${patchHover.point.x * 100}%`, top: `${patchHover.point.y * 100}%` }">×</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <Teleport to="body">
            <div v-if="markerActionTarget && actionPosition?.id === selectedRemovable?.id" :key="selectedRemovable.id"
                id="network-floor-marker-actions" ref="markerActions" class="network-floor__marker-actions"
                :class="{ 'is-workstation': selectedWorkstation, 'is-below': actionPosition.below,
                    'is-beside': actionPosition.beside, 'is-near-right': actionPosition.onRight }"
                :style="{ '--marker-x': `${actionPosition.x}px`, '--marker-y': `${actionPosition.y}px` }" role="group"
                :aria-label="`Ausgewählter ${selectedRemovable.device.label}`" @pointerdown.stop @pointerup.stop @click.stop
                @pointercancel="cancelActionTap" @keydown.esc.stop="closeMarkerActions">
                <button v-if="selectedWorkstation" type="button" class="network-floor__rotate"
                    aria-label="Arbeitsplatz 90° drehen" @pointerdown="startPlanActionTap"
                    @pointerup="finishPlanActionTap($event, rotateWorkstation)"
                    @click="clickAction($event, rotateWorkstation)">
                    <v-icon icon="mdi-rotate-right" size="18" aria-hidden="true" />
                    90° drehen
                </button>
                <v-btn type="button" icon="mdi-close" variant="text" color="error" size="44"
                    :aria-label="`${selectedRemovable.device.label} entfernen`"
                    :title="`${selectedRemovable.device.label} entfernen`"
                    @pointerdown="startPlanActionTap" @pointerup="finishPlanActionTap($event, removeMarker)"
                    @click="clickAction($event, removeMarker)" />
                <output v-if="selectedWorkstation" class="d-sr-only" aria-label="Ausrichtung">
                    {{ selectedWorkstation.rotation }}°
                </output>
                <p v-if="actionFeedback" class="network-floor__action-feedback" role="status">{{ actionFeedback }}</p>
            </div>
        </Teleport>
        <section class="network-floor__draft mt-6" aria-labelledby="ground-floor-devices-title">
            <h2 id="ground-floor-devices-title" class="text-h6 font-weight-bold mb-1">Geräte</h2>
            <p class="text-body-2 mb-3">
                Ziehe ein Gerät auf den Plan oder wähle es aus und tippe auf die gewünschte Stelle.
                Netzwerkdosen werden an bereits platzierten Kabelkanälen befestigt.
            </p>
            <p v-if="placementFeedback" role="status" class="text-body-2 mb-3">{{ placementFeedback }}</p>
            <div class="network-floor__controls" role="group" aria-label="Geräteauswahl">
                <NetworkDevicePhotoPreview v-for="device in devices" :key="device.id" :device="device">
                    <v-btn type="button" height="44" color="primary" class="network-floor__device text-none"
                        :variant="paletteSelected(device) ? 'tonal' : 'outlined'"
                        :aria-pressed="paletteSelected(device)"
                        :title="device.channelMounted ? 'An einem platzierten Kabelkanal befestigen' : undefined"
                        :disabled="remaining(device) === 0" @pointerdown="startPaletteDrag($event, device)"
                        @click.stop="clickPalette($event, device)">
                        {{ device.limit === undefined ? device.label : `${remaining(device)} × ${device.label}` }}
                        <NetworkDeviceIcon class="ms-2" :device="device" />
                    </v-btn>
                </NetworkDevicePhotoPreview>
            </div>
        </section>
        <Teleport to="body">
            <div v-if="drag?.moved && !drag.position?.channelId" class="network-floor__drag-preview" :class="deviceShape(drag)" aria-hidden="true"
                :style="{ left: `${drag.x}px`, top: `${drag.y}px`, '--device-rotation': `${drag.rotation}deg` }">
                <NetworkDeviceIcon :device="drag.device" />
                <span v-if="!drag.position" class="network-floor__drag-invalid">×</span>
            </div>
        </Teleport>
    </section>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, shallowReactive, shallowRef, watch } from 'vue'
import NetworkDeviceIcon from './NetworkDeviceIcon.vue'
import NetworkDevicePhotoPreview from './NetworkDevicePhotoPreview.vue'
import { channelCenterShift, channelCorners, channelStroke, furnitureFits, measureNetworkWalls, outletMountPoint,
    outletMountRange, pointAlongWall, snapToChannel, snapToNetworkWall } from './networkFloorWalls'
import { useNetworkDevicePlacement } from './useNetworkDevicePlacement'
import { useNetworkPatchCables } from './useNetworkPatchCables'
import { useNetworkTapAction } from './useNetworkTapAction'

const startZoom = 100
const minZoom = 50
const maxZoom = 200
const zoomStep = 5
const zoom = ref(startZoom)
const viewport = ref(null)
const zoomControls = ref(null)
const markerActions = ref(null)
const markerElements = shallowReactive(new Map())
const actionPosition = shallowRef(null)
const placementFeedback = ref('')
const actionFeedback = ref('')
const wallsById = shallowRef(new Map())
const furnitureScale = ref(1)
let geometryObserver
let pendingFocusMarkerId = null
const { startActionTap, finishActionTap, cancelActionTap, clickAction } = useNetworkTapAction()
const outsideTap = useNetworkTapAction()
const patchTap = useNetworkTapAction()
let handledPlanTap = false
const devices = [
    { id: 'workstation', label: 'Arbeitsplatz', limit: 5, removable: true, ports: 1 },
    { id: 'standing-table', label: 'Stehtisch', limit: 4, removable: true, ports: 1 },
    { id: 'router', label: 'Router', icon: 'mdi-router-network', removable: true, ports: 'multiple' },
    { id: 'switch', label: 'Switch', removable: true, ports: 'multiple' },
    { id: 'access-point', label: 'Access Point', icon: 'mdi-access-point', removable: true, ports: 1 },
    { id: 'server', label: 'Server', icon: 'mdi-server', removable: true, ports: 1 },
    { id: 'server-rack', label: 'Serverschrank' },
    { id: 'cable-duct', label: 'Kabelkanal', wallMounted: true, removable: true },
    { id: 'network-outlet', label: 'Einfache Netzwerkdose', channelMounted: true, removable: true, ports: 1 },
    { id: 'double-network-outlet', label: 'Doppelte Netzwerkdose', channelMounted: true, removable: true, ports: 2 },
    { id: 'patch-cable', label: 'Patchkabel', icon: 'mdi-ethernet-cable' },
]
const { layer, placements, selection, selectedRemovable, selectedWorkstation, canPlaceBySelection, drag, remaining,
    startDrag, moveDrag, finishDrag, cancelDrag, selectDevice, clickPlan, startPlanTap, finishPlanTap, placeAtCenter,
    rotateWorkstation, removeSelectedPlacement } = useNetworkDevicePlacement(resolvePlacement)
const patch = useNetworkPatchCables({ layer, placements, markerElements, move: drag, clearSelection: () => { selection.value = null } })
const { active: patchActive, status: patchStatus, firstEnd: patchFirstEnd, hover: patchHover,
    visibleConnections: patchConnections, previewLine: patchPreview, selectedConnectionId: selectedPatchId } = patch
const selectedPatch = computed(() => patchConnections.value.find(cable => cable.id === selectedPatchId.value))
const selectedMarker = computed(() => {
    const marker = markerElements.get(selectedRemovable.value?.id)
    return marker?.querySelector('.network-floor__channel-anchor') ?? marker
})
const markerActionTarget = computed(() => drag.value?.moved ? undefined : selectedMarker.value)
const channelsById = computed(() => new Map(placements.value.filter(placement => placement.wallId)
    .map(placement => [placement.id, placement])))
const visibleChannelCorners = computed(() => {
    const channels = [...channelsById.value.values()]
    const walls = channels.map(channel => wallsById.value.get(channel.wallId)).filter(Boolean)
    const preview = wallsById.value.get(drag.value?.position?.wallId)
    if (!preview) return channelCorners(walls)

    const retained = channels.filter(channel => channel.id !== drag.value.placementId)
        .map(channel => wallsById.value.get(channel.wallId)).filter(Boolean)
    return channelCorners([...retained, preview], preview.id).concat(channelCorners(retained))
        .filter((corner, index, corners) => corners.findIndex(candidate => candidate.key === corner.key) === index)
})
watch(() => selection.value?.placementId, () => { actionFeedback.value = '' })
watch(() => [markerActionTarget.value, selectedRemovable.value?.x, selectedRemovable.value?.y,
    selectedRemovable.value?.channelId, selectedRemovable.value?.channelPosition, selectedWorkstation.value?.rotation,
    zoom.value], syncMarkerActions, { flush: 'post' })
watch(zoom, syncPlanGeometry, { flush: 'post' })
onMounted(() => {
    syncPlanGeometry()
    geometryObserver = new ResizeObserver(syncPlanGeometry)
    geometryObserver.observe(layer.value)
    window.addEventListener('resize', syncPlanGeometry)
    window.addEventListener('scroll', syncMarkerActions, { capture: true, passive: true })
    document.addEventListener('click', clickOutsidePlan)
    document.addEventListener('pointerdown', outsideTap.startActionTap)
    document.addEventListener('pointerup', finishOutsideTap)
    document.addEventListener('pointercancel', outsideTap.cancelActionTap)
})
onUnmounted(() => {
    geometryObserver?.disconnect()
    window.removeEventListener('resize', syncPlanGeometry)
    window.removeEventListener('scroll', syncMarkerActions, true)
    document.removeEventListener('click', clickOutsidePlan)
    document.removeEventListener('pointerdown', outsideTap.startActionTap)
    document.removeEventListener('pointerup', finishOutsideTap)
    document.removeEventListener('pointercancel', outsideTap.cancelActionTap)
})

function setMarkerElement(id, element) {
    if (element) {
        markerElements.set(id, element)
    } else {
        markerElements.delete(id)
    }
}

function syncMarkerActions() {
    const target = markerActionTarget.value
    if (!target?.isConnected) {
        actionPosition.value = null
        return
    }

    const marker = target.getBoundingClientRect()
    const frame = viewport.value.getBoundingClientRect()
    const x = marker.left + marker.width / 2
    const pageWidth = document.documentElement.clientWidth
    if (x < Math.max(0, frame.left) || x > Math.min(pageWidth, frame.right)
        || marker.bottom < Math.max(0, frame.top) || marker.top > Math.min(window.innerHeight, frame.bottom)) {
        actionPosition.value = null
        return
    }

    const id = selectedRemovable.value.id
    const wall = wallsById.value.get(selectedRemovable.value.wallId)
    const beside = wall?.axis === 'vertical'
    const onRight = beside ? wall.side === 'east' || x > pageWidth - 76 : x > pageWidth / 2
    const below = !beside && marker.top < 64
    const actionX = beside ? x + (onRight ? -45 : 45) : x
    const actionY = beside ? Math.max(29, Math.min(window.innerHeight - 29, marker.top + marker.height / 2))
        : below ? marker.bottom + 8 : marker.top - 8
    actionPosition.value = { id, x: actionX, y: actionY, below, beside, onRight }
    if (pendingFocusMarkerId === id) {
        pendingFocusMarkerId = null
        nextTick(() => markerActions.value?.querySelector('button')?.focus({ preventScroll: true }))
    }
}

function syncPlanGeometry() {
    const surface = layer.value
    if (!surface?.offsetWidth) {
        return
    }

    furnitureScale.value = surface.offsetWidth / 890
    wallsById.value = new Map(measureNetworkWalls(surface).map(wall => [wall.id, wall]))
    syncMarkerActions()
    patch.refreshPositions()
}

function resolvePlacement(device, point, rotation, report = false, placementId = null) {
    if (report) placementFeedback.value = ''
    if (device.channelMounted) {
        const bounds = layer.value.getBoundingClientRect()
        const snapped = snapToChannel(channelsById.value.values(), wallsById.value, bounds, point)
        if (!snapped && report) placementFeedback.value = 'Netzwerkdosen lassen sich nur an bereits platzierten Kabelkanälen befestigen.'
        return snapped
    }
    if (!device.wallMounted && device.id !== 'workstation' && device.id !== 'standing-table') {
        return point
    }

    if (!wallsById.value.size) syncPlanGeometry()
    const bounds = layer.value.getBoundingClientRect()
    const walls = wallsById.value.values()
    if (device.wallMounted) {
        const snapped = snapToNetworkWall(walls, bounds, point)
        if (!snapped && report) {
            placementFeedback.value = 'Lege den Kabelkanal in Wandnähe ab.'
        }
        if (snapped && placementId && snapped.wallId !== channelsById.value.get(placementId)?.wallId
            && !attachedOutletsFit(placementId, wallsById.value.get(snapped.wallId), bounds)) {
            if (report) placementFeedback.value = 'Die montierten Netzwerkdosen passen an dieser Wandstelle nicht auf den Kanal.'
            return null
        }
        return snapped
    }

    const turned = rotation % 180 !== 0
    const [width, height] = device.id === 'standing-table' ? [46, 50] : turned ? [32, 68] : [68, 32]
    const scale = furnitureScale.value * zoom.value / 100
    // The caption and its gap sit below the icon and shift its center up.
    const furnitureCenter = { x: point.x, y: point.y - 9.5 * scale / bounds.height }
    if (furnitureFits(walls, bounds, furnitureCenter, { width: width * scale, height: height * scale })) {
        return point
    }

    if (report) placementFeedback.value = 'Arbeitsplätze und Stehtische dürfen keine Wand überragen.'
    return null
}

function startMarkerDrag(event, placement) {
    handledPlanTap = false
    if (patchActive.value) {
        patchTap.startActionTap(event)
        return
    }
    selectedPatchId.value = null
    const wall = wallsById.value.get(placement.wallId)
    if (wall && event.isPrimary && event.button === 0) {
        const bounds = layer.value.getBoundingClientRect()
        const along = pointAlongWall(wall, {
            x: (event.clientX - bounds.left) / bounds.width,
            y: (event.clientY - bounds.top) / bounds.height,
        })
        if (wall.axis === 'horizontal') placement.x = along
        else placement.y = along
    }
    startDrag(event, placement.device, placement.id, placement.rotation)
}

function markerStyle(placement) {
    const mount = wallsById.value.get(channelsById.value.get(placement.channelId)?.wallId)
    if (mount) {
        const point = outletMountPoint(mount, placement.channelPosition)
        const shift = channelCenterShift(mount)
        return { '--device-x': `${point.x * 100}%`, '--device-y': `${point.y * 100}%`,
            '--mount-x-offset': shift.x, '--mount-y-offset': shift.y }
    }
    const wall = wallsById.value.get(placement.wallId)
    if (!wall) {
        return { '--device-x': `${placement.x * 100}%`, '--device-y': `${placement.y * 100}%`,
            '--device-rotation': `${placement.rotation}deg` }
    }
    const along = pointAlongWall(wall, placement)
    const length = wall.end - wall.start
    return { '--wall-line': `${wall.line * 100}%`, '--wall-start': `${wall.start * 100}%`, '--wall-length': `${length * 100}%`,
        '--channel-anchor': `${(along - wall.start) / length * 100}%` }
}

function cornerStyle({ x, y, shift }) {
    return { '--corner-x': `${x * 100}%`, '--corner-y': `${y * 100}%`,
        '--corner-x-offset': shift.x, '--corner-y-offset': shift.y }
}

function selectMarker(event, placement) {
    if (consumePlanTap(event)) return
    if (patchActive.value) {
        patch.chooseMarker(placement, event)
        return
    }
    selectDevice(event, placement.device, placement.id)
    if (event.detail === 0 && selectedRemovable.value?.id === placement.id) {
        pendingFocusMarkerId = placement.id
    }
}

function finishMarkerTap(event, placement) {
    if (!patchActive.value) return
    patchTap.finishActionTap(event, () => {
        handledPlanTap = true
        patch.chooseMarker(placement, event)
    })
}

function consumePlanTap(event) {
    // A touch can dispatch its follow-up click to a different element after the UI changes.
    if (!handledPlanTap || event.detail === 0) return false
    handledPlanTap = false
    return true
}

function escapeMarker() {
    if (patchActive.value) patch.cancel()
    else closeMarkerActions()
}

function startPaletteDrag(event, device) {
    handledPlanTap = false
    if (device.id === 'patch-cable') patch.startPaletteDrag(event)
    else {
        patch.cancel()
        selectedPatchId.value = null
        startDrag(event, device)
    }
}

function paletteSelected(device) {
    return device.id === 'patch-cable' ? patchActive.value
        : selection.value?.device.id === device.id && selection.value.placementId === null
}

function clickPalette(event, device) {
    if (device.id === 'patch-cable') patch.clickPalette(event)
    else {
        patch.cancel()
        selectedPatchId.value = null
        selectDevice(event, device)
    }
}

function movePointer(event) {
    moveDrag(event)
    patch.pointerMove(event)
}

function finishPointer(event) {
    finishDrag(event)
    patch.finishPaletteDrag(event)
}

function cancelPointer(event) {
    cancelDrag(event)
    patch.cancelPaletteDrag(event)
}

function startSurfaceTap(event) {
    handledPlanTap = false
    if (patchActive.value) patchTap.startActionTap(event)
    else startPlanTap(event)
}

function finishSurfaceTap(event) {
    if (patchActive.value) patchTap.finishActionTap(event, () => {
        handledPlanTap = true
        patch.rejectTarget()
    })
    else finishPlanTap(event)
}

function clickPlanOrCable(event) {
    if (consumePlanTap(event)) return
    selectedPatchId.value = null
    if (patchActive.value) patch.rejectTarget()
    else clickPlan(event)
}

function startPatchLineTap(event) {
    handledPlanTap = false
    patchTap.startActionTap(event)
}

function finishPatchLineTap(event, id) {
    patchTap.finishActionTap(event, () => {
        handledPlanTap = true
        patch.selectConnection(id)
    })
}

function clickPatchLine(event, id) {
    if (!consumePlanTap(event)) patchTap.clickAction(event, () => patch.selectConnection(id))
}

function startPlanActionTap(event) {
    handledPlanTap = false
    startActionTap(event)
}

function finishPlanActionTap(event, action) {
    finishActionTap(event, () => {
        handledPlanTap = true
        action()
    })
}

function clickPatchRemove(event) {
    if (!consumePlanTap(event)) clickAction(event, removePatch)
}

function removePatch() {
    if (selectedPatchId.value != null) patch.removeConnection(selectedPatchId.value)
}

function closeMarkerActions() {
    selectedMarker.value?.closest('button')?.focus({ preventScroll: true })
    selection.value = null
}

function clearPlacementSelection() {
    selectedPatchId.value = null
    if (selection.value?.placementId != null) {
        selection.value = null
    }
}

function clickOutsidePlan(event) {
    const path = event.composedPath()
    if (![layer.value?.parentElement, zoomControls.value, markerActions.value].some(element => path.includes(element))) {
        if (patchFirstEnd.value) patch.rejectTarget()
        clearPlacementSelection()
    }
}

function finishOutsideTap(event) {
    outsideTap.finishActionTap(event, () => clickOutsidePlan(event))
}

function deviceShape({ device, rotation }) {
    return { 'is-workstation': device.id === 'workstation', 'is-standing-table': device.id === 'standing-table',
        'is-server-rack': device.id === 'server-rack', 'is-cable-duct': device.wallMounted,
        'is-channel-mounted': device.channelMounted, 'is-turned': rotation % 180 !== 0 }
}

function markerLabel({ device, rotation }) {
    return device.id === 'workstation'
        ? `${device.label} im Grundriss, Drehung ${rotation}°`
        : `${device.label} im Grundriss`
}

function adjustZoom(amount) {
    zoom.value = Math.min(maxZoom, Math.max(minZoom, zoom.value + amount))
}

function resetZoom() {
    zoom.value = startZoom
    viewport.value?.scrollTo(0, 0)
}

function removeMarker() {
    if (channelHasOutlets(selectedRemovable.value?.id)) {
        actionFeedback.value = 'Entferne zuerst die Netzwerkdosen an diesem Kabelkanal.'
        return
    }
    patch.removeDeviceConnections(selectedRemovable.value?.id)
    removeSelectedPlacement()
    viewport.value?.focus({ preventScroll: true })
}

function channelHasOutlets(channelId) {
    return channelId != null && placements.value.some(placement => placement.channelId === channelId)
}

function attachedOutletsFit(channelId, wall, bounds) {
    const range = outletMountRange(wall, bounds)
    return placements.value.every(placement => {
        if (placement.channelId !== channelId) return true
        const along = pointAlongWall(wall, outletMountPoint(wall, placement.channelPosition))
        return range && along >= range.start && along <= range.end
    })
}
</script>

<style scoped>
.network-floor {
    --viewport-height: min(70vh, 650px);
    min-inline-size: 0;
    container-type: inline-size;
}

.network-floor__controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.network-floor__zoom-step {
    font-size: 1.25rem;
    touch-action: manipulation;
}

.network-floor__zoom-value {
    min-width: 4rem;
    font-variant-numeric: tabular-nums;
    text-align: center;
}

.network-floor__patch-instructions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 8px;
    font-size: .875rem;
}

.network-floor__workspace {
    --plan-maximum-width: 905px;
    --available-plan-width: 100cqi;
    --plan-width: calc(min(var(--plan-maximum-width), var(--available-plan-width)) * var(--floor-zoom));
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
        "tasks"
        "plan";
    align-items: start;
    gap: 24px;
}

.network-floor__task {
    grid-area: tasks;
    min-inline-size: 0;
}

.network-floor__task-title {
    font-size: clamp(1rem, 2cqi, 1.25rem);
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.network-floor__tasks {
    display: grid;
    gap: 8px;
    padding: 0;
    list-style-position: inside;
}

.network-floor__tasks > li {
    padding: 8px 12px;
    border: 1px solid #c6d1de;
    border-radius: 6px;
    background: #fff;
    color: #182b45;
}

.network-floor__tasks > li::marker {
    color: #315b8d;
    font-weight: 650;
}

@container (min-width: 720px) {
    .network-floor__workspace {
        --minimum-task-width: clamp(185px, 25cqi, 300px);
        --workspace-gap: clamp(12px, 1.5cqi, 16px);
        --available-plan-width: calc(100cqi - var(--minimum-task-width) - var(--workspace-gap));
        grid-template-columns: minmax(0, min(var(--plan-width), var(--available-plan-width)))
            minmax(var(--minimum-task-width), 1fr);
        grid-template-areas: "plan tasks";
        gap: var(--workspace-gap);
    }
}

.network-floor__viewport {
    grid-area: plan;
    max-height: var(--viewport-height);
    overflow: auto;
    overscroll-behavior-inline: contain;
}

.network-floor__viewport:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
}

.network-floor__extent {
    --inline-clearance: clamp(0px, calc((var(--plan-width) - 100%) * 16), 16px);
    --block-clearance: clamp(0px, calc((var(--plan-width) * 9 / 16 - var(--viewport-height)) * 16), 16px);
    box-sizing: content-box;
    inline-size: var(--plan-width);
    /* Include the bottom edge even when only the horizontal scrollbar is visible. */
    padding-inline: var(--inline-clearance);
    padding-block: max(var(--inline-clearance), var(--block-clearance));
}

.network-floor__plan {
    --wall: #40536b;
    --outer-wall: 7px;
    --inner-wall: 2px;
    --foyer-fill: #eef3f9;
    --door-size: clamp(19px, 6vw, 30px);
    box-sizing: border-box;
    position: relative;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-rows: repeat(3, minmax(0, 1fr));
    aspect-ratio: 16 / 9;
    gap: var(--inner-wall);
    border: var(--outer-wall) solid var(--wall);
    border-block-end: 0;
    padding-block-end: var(--outer-wall);
    background: var(--wall);
    zoom: var(--floor-zoom);
}

.network-floor__room {
    position: relative;
    display: grid;
    place-items: center;
    grid-column: 1;
    padding: 8px;
    background: #fff;
    color: #182b45;
    font-weight: 650;
    overflow-wrap: anywhere;
    text-align: center;
}

.network-floor__plan > .network-floor__foyer {
    grid-area: 1 / 2 / 4;
    background: var(--foyer-fill);
}

.network-floor__door {
    position: absolute;
    z-index: 1;
    inset-block-start: 50%;
    inset-inline-end: 0;
    inline-size: var(--door-size);
    block-size: var(--door-size);
    transform: translateY(-50%);
    border: 1px solid var(--wall);
    border-inline-end: 0;
    border-start-start-radius: 100%;
}

.network-floor__door::after {
    content: '';
    position: absolute;
    inset-block: -1px;
    inset-inline-start: 100%;
    inline-size: var(--inner-wall);
    background: var(--foyer-fill);
}

.network-floor__entrance {
    inset-block: auto 0;
    inset-inline: 50% auto;
    transform: translateX(-50%);
    border-inline-end: 1px solid var(--wall);
    border-block-end: 0;
    border-start-start-radius: 0;
    border-start-end-radius: 100%;
}

.network-floor__entrance::after {
    inset-block: 100% auto;
    inset-inline: -1px;
    inline-size: auto;
    block-size: var(--outer-wall);
}

.network-floor__draft {
    max-width: 905px;
}

.network-floor__placements {
    --channel-stroke: calc(var(--channel-stroke-screen) / var(--floor-zoom));
    --channel-border: #223a55;
    --channel-fill: #57718a;
    --channel-highlight: #aabed1;
    position: absolute;
    z-index: 2;
    inset: 0;
    inset-block-end: var(--outer-wall);
}

.network-floor__placements.is-selecting {
    cursor: crosshair;
}

.network-floor__placements:focus-visible {
    outline: 2px solid #315b8d;
    outline-offset: -2px;
}

.network-floor__patch-lines {
    position: absolute;
    inset: 0;
    inline-size: 100%;
    block-size: 100%;
    overflow: visible;
    pointer-events: none;
}

.network-floor__patch-line,
.network-floor__patch-outline,
.network-floor__patch-hit {
    vector-effect: non-scaling-stroke;
}

.network-floor__patch-line,
.network-floor__patch-outline {
    stroke-linecap: round;
}

.network-floor__patch-line {
    stroke: #f8c324;
    stroke-width: 3px;
}

.network-floor__patch-outline {
    stroke: #493611;
    stroke-width: 5px;
}

.network-floor__patch-hit {
    stroke: transparent;
    stroke-width: 18px;
    pointer-events: stroke;
    cursor: pointer;
}

.network-floor__patch-lines g:is(.is-selected, :focus-visible) .network-floor__patch-line {
    stroke: #ffcf35;
    stroke-width: 5px;
}

.network-floor__patch-lines g:is(.is-selected, :focus-visible) .network-floor__patch-outline {
    stroke: #211903;
    stroke-width: 8px;
}

.network-floor__patch-preview > line {
    stroke-dasharray: 7 4;
}

.network-floor__patch-preview.is-invalid .network-floor__patch-line {
    stroke: #b3262b;
}

.network-floor__patch-remove {
    position: absolute;
    z-index: 4;
    top: max(56px, var(--patch-center-y));
    left: clamp(24px, var(--patch-center-x), calc(100% - 24px));
    transform: translate(-50%, calc(-100% - 8px));
}

.network-floor__marker.is-patch-target {
    outline: 2px dashed #116b56;
    outline-offset: 2px;
    cursor: crosshair;
}

.network-floor__marker.is-patch-start {
    outline: 2px solid #116b56;
    outline-offset: 2px;
}

.network-floor__device,
.network-floor__marker {
    cursor: grab;
    touch-action: none;
}

.network-floor__device[aria-pressed="true"],
.network-floor__marker.is-selected {
    box-shadow: inset 0 0 0 2px currentColor;
}

.network-floor__marker-actions {
    --action-width: 50px;
    position: fixed;
    z-index: 1600;
    top: var(--marker-y);
    left: clamp(8px, calc(var(--marker-x) - var(--action-width) / 2), calc(100% - var(--action-width) - 8px));
    inline-size: var(--action-width);
    transform: translateY(-100%);
    display: flex;
    gap: 2px;
    align-items: center;
    padding: 2px;
    border: 1px solid #315b8d;
    border-radius: 6px;
    background: #fff;
    color: #173454;
    box-shadow: 0 2px 6px #17345430;
    touch-action: manipulation;
    pointer-events: none;
}

.network-floor__marker-actions button {
    pointer-events: auto;
}

.network-floor__marker-actions.is-workstation {
    --action-width: 166px;
}

.network-floor__action-feedback {
    position: absolute;
    inset: 0 auto auto calc(100% + 8px);
    inline-size: min(184px, 52vw);
    padding: 6px 8px;
    border: 1px solid #315b8d;
    border-radius: 6px;
    background: #fff;
    font-size: .8125rem;
    line-height: 1.35;
}

.is-near-right > .network-floor__action-feedback {
    inset-inline: auto calc(100% + 8px);
}

.network-floor__marker-actions.is-below {
    transform: none;
}

.network-floor__marker-actions.is-beside {
    transform: translateY(-50%);
}

.network-floor__rotate {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-block-size: 44px;
    padding-inline: 12px;
    border: 1px solid #315b8d;
    border-radius: 4px;
    background-color: #eef3f9;
    color: #173454;
    cursor: pointer;
}

.network-floor__rotate:hover {
    background-color: #dce7f3;
}

.network-floor__rotate:focus-visible {
    outline: 2px solid #315b8d;
    outline-offset: -4px;
}

.network-floor__marker,
.network-floor__drag-preview {
    --marker-zoom: var(--floor-zoom, 1);
    --half-width: calc(22px / var(--marker-zoom));
    --half-height: calc(22px / var(--marker-zoom));
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #315b8d;
    border-radius: 50%;
    background: #fff;
    color: #173454;
    inline-size: calc(2 * var(--half-width));
    block-size: calc(2 * var(--half-height));
    transform: translate(-50%, -50%);
}

.network-floor__marker {
    position: absolute;
    left: clamp(var(--half-width), var(--device-x), calc(100% - var(--half-width)));
    top: clamp(var(--half-height), var(--device-y), calc(100% - var(--half-height)));
}

.network-floor__marker.is-workstation,
.network-floor__drag-preview.is-workstation {
    --desk-width: 68px;
    --desk-height: 32px;
    --half-width: max(calc(22px / var(--marker-zoom)), calc(40px * var(--furniture-scale, 1)));
    --half-height: max(calc(22px / var(--marker-zoom)), calc(25.5px * var(--furniture-scale, 1)));
    border: 0;
    border-radius: 4px;
    background: transparent;
}

.network-floor__marker.is-workstation.is-turned,
.network-floor__drag-preview.is-workstation.is-turned {
    --half-height: max(calc(22px / var(--marker-zoom)), calc(43.5px * var(--furniture-scale, 1)));
}

.network-floor__marker.is-standing-table,
.network-floor__drag-preview.is-standing-table {
    --standing-table-width: 46px;
    --standing-table-height: 50px;
    --half-width: max(calc(22px / var(--marker-zoom)), calc(29px * var(--furniture-scale, 1)));
    --half-height: max(calc(22px / var(--marker-zoom)), calc(34.5px * var(--furniture-scale, 1)));
    border: 0;
    border-radius: 4px;
    background: transparent;
}

.network-floor__marker > *,
.network-floor__drag-preview > * {
    flex: none;
    pointer-events: none;
    transform: scale(calc(1 / var(--marker-zoom))) rotate(var(--device-rotation, 0deg));
}

.network-floor__marker.is-workstation,
.network-floor__marker.is-standing-table {
    left: var(--device-x);
    top: var(--device-y);
}

.network-floor__marker.is-workstation.is-selected,
.network-floor__marker.is-standing-table.is-selected {
    box-shadow: none;
}

.network-floor__marker.is-workstation.is-selected .network-floor__workstation-graphic,
.network-floor__marker.is-standing-table.is-selected > * {
    outline: 2px solid #315b8d;
    outline-offset: -2px;
    border-radius: 3px;
}

.network-floor__marker.is-standing-table > * {
    transform: scale(var(--furniture-scale));
}

.network-floor__marker > .network-floor__workstation {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    transform: scale(var(--furniture-scale));
}

.network-floor__marker.is-server-rack {
    --rack-width: 29px;
    --rack-height: 42px;
    border: 0;
    border-radius: 0;
    background: transparent;
}

.network-floor__marker.is-server-rack.is-selected {
    box-shadow: none;
    outline: 2px solid #315b8d;
}

.network-floor__workstation-graphic {
    position: relative;
    inline-size: var(--desk-width);
    block-size: var(--desk-height);
}

.network-floor__marker.is-turned .network-floor__workstation-graphic {
    inline-size: var(--desk-height);
    block-size: var(--desk-width);
}

.network-floor__workstation-graphic > * {
    position: absolute;
    inset: 50% auto auto 50%;
    transform: translate(-50%, -50%) rotate(var(--device-rotation));
}

.network-floor__device-drawing {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}

.network-floor__object-label,
.network-floor__workstation-label {
    font-size: 11px;
    font-weight: 650;
    text-align: center;
    text-shadow: 0 0 2px #fff;
    white-space: nowrap;
}

.network-floor__workstation-label,
.is-standing-table .network-floor__object-label {
    font-size: 14px;
    line-height: 16px;
}

.network-floor__workstation-label { inline-size: 80px; }
.is-standing-table .network-floor__object-label { inline-size: 58px; }

.network-floor__drag-preview.is-workstation,
.network-floor__drag-preview.is-standing-table,
.network-floor__drag-preview.is-cable-duct {
    box-shadow: none;
}

.network-floor__drag-invalid {
    position: absolute;
    inset: -8px -8px auto auto;
    display: grid;
    place-items: center;
    inline-size: 25px;
    block-size: 25px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: #b3262b;
    color: #fff;
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
    transform: none;
}

.network-floor__patch-invalid {
    inset: auto;
    z-index: 4;
    pointer-events: none;
    transform: translate(-50%, -50%) scale(calc(1 / var(--floor-zoom)));
}

.network-floor__drag-preview.is-cable-duct {
    border: 0;
    background: transparent;
}

.network-floor__marker.is-cable-duct {
    --channel-hit: calc(28px / var(--floor-zoom));
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    pointer-events: none;
}

.network-floor__marker.is-cable-duct.horizontal {
    left: var(--wall-start);
    top: var(--wall-line);
    inline-size: var(--wall-length);
    block-size: var(--channel-hit);
    transform: translateY(-50%);
}

.network-floor__marker.is-cable-duct.vertical {
    left: var(--wall-line);
    top: var(--wall-start);
    inline-size: var(--channel-hit);
    block-size: var(--wall-length);
    transform: translateX(-50%);
}

.network-floor__marker.is-cable-duct:is([data-wall-side="north"], [data-wall-side="west"]) {
    transform: none;
}

.network-floor__marker.is-cable-duct[data-wall-side="south"] {
    transform: translateY(-100%);
}

.network-floor__marker.is-cable-duct[data-wall-side="east"] {
    transform: translateX(-100%);
}

.network-floor__marker.is-cable-duct > * {
    position: absolute;
    transform: none;
}

.network-floor__marker.is-cable-duct > .network-floor__channel-segment {
    inset: 0;
    pointer-events: auto;
}

.network-floor__placements.is-wiring .network-floor__marker.is-cable-duct > .network-floor__channel-segment {
    pointer-events: none;
}

.network-floor__channel-segment::before,
.network-floor__channel-corner {
    box-sizing: border-box;
    position: absolute;
    content: '';
    border: 2px solid var(--channel-border);
    border-radius: 2px;
    background: var(--channel-fill);
    box-shadow: inset 0 0 0 1px var(--channel-highlight);
}

.network-floor__marker.is-cable-duct:is(.is-selected, :focus-visible) .network-floor__channel-segment::before {
    border-color: #0c3060;
    background: #326bad;
}

.horizontal > .network-floor__channel-segment::before {
    inset: 50% 0 auto;
    block-size: var(--channel-stroke);
    transform: translateY(-50%);
}

.vertical > .network-floor__channel-segment::before {
    inset: 0 auto 0 50%;
    inline-size: var(--channel-stroke);
    transform: translateX(-50%);
}

[data-wall-side="north"] > .network-floor__channel-segment::before { inset-block-start: 0; transform: none; }
[data-wall-side="south"] > .network-floor__channel-segment::before { inset-block: auto 0; transform: none; }
[data-wall-side="west"] > .network-floor__channel-segment::before { inset-inline-start: 0; transform: none; }
[data-wall-side="east"] > .network-floor__channel-segment::before { inset-inline: auto 0; transform: none; }

.network-floor__channel-anchor {
    inline-size: 1px;
    block-size: 1px;
    pointer-events: none;
}

.horizontal > .network-floor__channel-anchor { inset: 50% auto auto var(--channel-anchor); }
.vertical > .network-floor__channel-anchor { inset: var(--channel-anchor) auto auto 50%; }

.network-floor__marker.is-cable-duct > .network-floor__channel-label {
    padding-inline: 2px;
    border-radius: 2px;
    background: #fff;
    font-size: calc(11px / var(--floor-zoom));
    font-weight: 650;
    white-space: nowrap;
}

.is-cable-duct.horizontal > .network-floor__channel-label {
    inset: 80% auto auto 50%;
    transform: translateX(-50%);
}

.is-cable-duct.vertical > .network-floor__channel-label {
    inset: 50% auto auto 84%;
    transform: translate(-50%, -50%) rotate(90deg);
}

.is-cable-duct.vertical[data-wall-side="partition"] > .network-floor__channel-label {
    inset-inline-start: -12px;
}

.is-cable-duct[data-wall-side="south"] > .network-floor__channel-label {
    inset-block: auto calc(var(--door-size) + 4px);
}

.is-cable-duct[data-wall-side="east"] > .network-floor__channel-label {
    inset-inline: 16% auto;
}

.network-floor__channel-preview.is-cable-duct > .network-floor__channel-segment {
    pointer-events: none;
}

.network-floor__channel-preview,
.network-floor__channel-corner.is-preview {
    --channel-border: #0b346a;
    --channel-fill: #0755ab;
    --channel-highlight: #b5d8ff;
}

.network-floor__channel-corner {
    inset: calc(var(--corner-y) + var(--channel-stroke) * var(--corner-y-offset) / 2) auto auto
        calc(var(--corner-x) + var(--channel-stroke) * var(--corner-x-offset) / 2);
    inline-size: var(--channel-stroke);
    block-size: var(--channel-stroke);
    transform: translate(-50%, -50%);
    pointer-events: none;
    box-shadow: none;
}

.network-floor__channel-corner.left { border-inline-start-color: transparent; }
.network-floor__channel-corner.right { border-inline-end-color: transparent; }
.network-floor__channel-corner.up { border-block-start-color: transparent; }
.network-floor__channel-corner.down { border-block-end-color: transparent; }

.network-floor__marker.is-channel-mounted {
    z-index: 1;
    --half-width: calc(14px / var(--floor-zoom));
    --half-height: var(--half-width);
    left: calc(var(--device-x) + var(--channel-stroke) * var(--mount-x-offset));
    top: calc(var(--device-y) + var(--channel-stroke) * var(--mount-y-offset));
    border-radius: 4px;
}

.network-floor__marker.network-floor__outlet-preview {
    z-index: 1;
    border: 2px solid #0b346a;
    background: #d8ecff;
    pointer-events: none;
}

.network-floor__drag-preview {
    position: fixed;
    z-index: 3000;
    box-shadow: 0 2px 6px #17345447;
    pointer-events: none;
}
</style>
