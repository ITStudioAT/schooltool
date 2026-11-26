<template>
    <v-dialog :model-value="true" max-width="780" scrollable persistent aria-labelledby="network-rack-configuration">
        <v-card>
            <v-card-title id="network-rack-configuration">Serverschrank konfigurieren</v-card-title>
            <v-card-text>
                <p v-if="incomingCableCount !== null" class="text-body-2 mb-3">
                    Eingehende Kabel: <strong>{{ incomingCableCount }}</strong>
                </p>
                <p class="text-body-2 mb-4">
                    Ziehe eine Komponente auf einen Platz im Serverschrank oder wähle sie aus und klicke auf den Zielplatz.
                </p>
                <v-btn type="button" height="44" variant="outlined" prepend-icon="mdi-ethernet-cable" class="text-none mb-3"
                    :aria-pressed="patchMode" @click="togglePatchMode">Patchkabel ein-/ausstecken</v-btn>
                <p v-if="patchMode && feedback" class="text-body-2 mb-3" role="status">{{ feedback }}</p>
                <div ref="editor" class="network-rack-config__editor" @pointermove="moveDrag" @pointerup="finishDrag"
                    @pointercancel="cancelDrag" @lostpointercapture="cancelDrag">
                    <section class="network-rack-config__palette" aria-labelledby="network-rack-components">
                        <h3 id="network-rack-components" class="text-subtitle-2 font-weight-bold mb-2">Komponenten</h3>
                        <div class="network-rack-config__choices">
                            <button v-for="(label, type) in rackModuleLabels" :key="type" type="button"
                                class="network-rack-config__choice"
                                :aria-pressed="selection?.type === type && selection.index === null"
                                @pointerdown="startDrag($event, type, null)" @click="chooseComponent($event, type)">
                                <NetworkRackModuleGraphic :type="type" />
                                <span>{{ label }}</span>
                            </button>
                        </div>
                    </section>
                    <section class="network-rack-config__cabinet" aria-labelledby="network-rack-cabinet">
                        <h3 id="network-rack-cabinet" class="text-subtitle-2 font-weight-bold mb-2">Einbauplätze</h3>
                        <ol ref="rackFrame" class="network-rack-config__frame"
                            :class="{ 'has-cables': wiredPatchPanelSlot > 0 || localPatchConnections.length }" role="list">
                            <li v-for="number in slotCount" :key="number" :data-rack-slot="number - 1"
                                :class="{ 'is-drop-target': drag?.target === number - 1,
                                    'is-dragging': drag?.index === number - 1,
                                    'has-cable-run': number <= wiredPatchPanelSlot,
                                    'has-cable-entry': number === wiredPatchPanelSlot }">
                                <span class="network-rack-config__slot-number" aria-hidden="true">{{ number }}</span>
                                <button type="button" class="network-rack-config__slot"
                                    :class="{ 'is-occupied': slots[number - 1] }"
                                    :aria-label="slotLabel(number)"
                                    :aria-pressed="selection?.index === number - 1"
                                    @pointerdown="startDrag($event, slots[number - 1], number - 1)"
                                    @click="chooseSlot($event, number - 1)">
                                    <NetworkRackModuleGraphic v-if="slots[number - 1]" :type="slots[number - 1]"
                                        :patched-ports="patchedPorts(number - 1)"
                                        :connected-ports="number === wiredPatchPanelSlot ? incomingPorts : []" />
                                    <span>{{ rackModuleLabels[slots[number - 1]] ?? 'Frei' }}</span>
                                </button>
                                <v-btn v-if="slots[number - 1]" type="button" icon="mdi-delete-outline" variant="text" size="44"
                                    class="network-rack-config__remove"
                                    :aria-label="`${rackModuleLabels[slots[number - 1]]} aus Platz ${number} entfernen`"
                                    :title="`${rackModuleLabels[slots[number - 1]]} entfernen`"
                                    @click="removeModule(number - 1)" />
                                <div v-if="patchMode && slots[number - 1]" class="network-rack-config__ports" role="group"
                                    :aria-label="`Buchsen auf Platz ${number}`">
                                    <button v-for="port in portCount(number - 1)" :key="port" type="button"
                                        class="network-rack-config__port"
                                        :data-rack-patch-port="port - 1"
                                        :class="{ 'has-patch-cable': patchByPort.has(rackPortKey({ slot: number - 1, port: port - 1 })) }"
                                        :aria-label="portLabel({ slot: number - 1, port: port - 1 })"
                                        :title="number === wiredPatchPanelSlot ? incomingDeviceLabel(port - 1) : undefined"
                                        :aria-pressed="selectedPort?.slot === number - 1 && selectedPort.port === port - 1"
                                        @click="choosePort({ slot: number - 1, port: port - 1 })">{{ port }}</button>
                                    <div v-if="selectedPatch && selectedPort?.slot === number - 1" class="network-rack-config__port-actions">
                                        <v-btn type="button" height="44" variant="flat" color="error"
                                            class="text-none" @click="removePatchConnection(selectedPatch.start)">Patchkabel entfernen</v-btn>
                                    </div>
                                </div>
                                <section v-if="number === wiredPatchPanelSlot" class="network-rack-config__assignments"
                                    aria-labelledby="network-rack-panel-devices">
                                    <h4 id="network-rack-panel-devices">Geräte am Patchpanel</h4>
                                    <dl>
                                        <div v-for="{ port, deviceLabel } in incomingConnections" :key="port">
                                            <dt>Buchse {{ port + 1 }}</dt>
                                            <dd>{{ deviceLabel }}</dd>
                                        </div>
                                    </dl>
                                </section>
                            </li>
                        </ol>
                        <div ref="patchLineOverlay" class="network-rack-config__patch-lines" aria-hidden="true">
                            <div v-for="line in patchLines" :key="line.key" class="network-rack-config__patch-line"
                                :class="{ 'is-selected': selectedPatch && line.key === rackPortKey(selectedPatch.start) }" :style="{ color: line.color }">
                                <span v-for="(segment, index) in line.segments" :key="index" :style="segment" />
                            </div>
                        </div>
                    </section>
                </div>
                <p v-if="!patchMode && feedback" class="text-body-2 mt-3" role="status">{{ feedback }}</p>
                <section v-if="localPatchConnections.length" class="mt-4" aria-labelledby="network-rack-patch-cables">
                    <h3 id="network-rack-patch-cables" class="text-subtitle-2 font-weight-bold">Patchkabel im Serverschrank</h3>
                    <ul class="network-rack-config__patch-list">
                        <li v-for="{ start, end } in localPatchConnections" :key="rackPortKey(start)">
                            <span>{{ portName(start) }} ↔ {{ portName(end) }}</span>
                            <v-btn type="button" icon="mdi-close" variant="text" size="44"
                                :aria-label="`Patchkabel zwischen ${portNameWithSlot(start)} und ${portNameWithSlot(end)} entfernen`"
                                @click="removePatchConnection(start)" />
                        </li>
                    </ul>
                </section>
                <p v-if="incomingCableCount !== null && !slots.includes(patchPanelModule)" class="text-body-2 mt-3" role="status">
                    Beim Speichern werden die Kabelstränge zu diesem Serverschrank getrennt.
                </p>
            </v-card-text>
            <v-card-actions class="justify-end flex-wrap ga-2">
                <v-btn type="button" height="44" variant="text" class="text-none" @click="emit('close')">Abbrechen</v-btn>
                <v-btn type="button" height="44" variant="flat" color="primary" class="text-none"
                    @click="emit('save', normalizeRackModules(slots), localPatchConnections)">Speichern</v-btn>
            </v-card-actions>
        </v-card>
        <Teleport to="body">
            <div v-if="drag" class="network-rack-config__ghost" :style="{ left: `${drag.x}px`, top: `${drag.y}px` }"
                aria-hidden="true">
                <NetworkRackModuleGraphic :type="drag.type" /> {{ rackModuleLabels[drag.type] }}
            </div>
        </Teleport>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, ref, shallowRef, watch } from 'vue'
import NetworkRackModuleGraphic from './NetworkRackModuleGraphic.vue'
import { normalizeRackModules, normalizeRackPatchConnections, patchPanelModule, rackModuleLabels, rackModulePorts,
    rackPortKey } from './networkRackModules'

const props = defineProps({
    modules: { type: Array, default: () => [] },
    incomingConnections: { type: Array, default: null },
    patchConnections: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'save', 'preview-change'])
const editor = ref(null)
const rackFrame = ref(null)
const patchLineOverlay = ref(null)
const patchLines = shallowRef([])
const patchColors = ['#ffbd67', '#6ee5c9', '#8dc7ff', '#f8a7c1', '#e3d47f']
const slots = ref(normalizeRackModules(props.modules))
const localPatchConnections = ref(normalizeRackPatchConnections(props.patchConnections, slots.value))
const patchMode = ref(false)
const selectedPort = shallowRef(null)
const patchByPort = computed(() => new Map(localPatchConnections.value.flatMap(connection =>
    [[rackPortKey(connection.start), connection], [rackPortKey(connection.end), connection]])))
const selectedPatch = computed(() => selectedPort.value && patchByPort.value.get(rackPortKey(selectedPort.value)))
const slotCount = computed(() => Math.max(6, slots.value.length + 1))
const incomingPorts = computed(() => props.incomingConnections?.map(({ port }) => port) ?? [])
const incomingCableCount = computed(() => props.incomingConnections?.length ?? null)
const wiredPatchPanelSlot = computed(() => incomingCableCount.value > 0 ? slots.value.indexOf(patchPanelModule) + 1 : 0)
const selection = shallowRef(null)
const drag = shallowRef(null)
const feedback = ref('')
let gesture = null
let handledButton = null

watch([rackFrame, patchLineOverlay], ([frame, overlay], _, onCleanup) => {
    if (!frame || !overlay) return
    refreshPatchLines()
    const observer = new ResizeObserver(refreshPatchLines)
    observer.observe(frame)
    observer.observe(overlay)
    onCleanup(() => observer.disconnect())
}, { flush: 'post' })
watch([slots, localPatchConnections, patchMode], refreshPatchLines, { deep: true, flush: 'post' })
watch([slots, localPatchConnections], ([rackModules, rackPatchConnections]) => {
    emit('preview-change', { rackModules, rackPatchConnections })
}, { deep: true })

function refreshPatchLines() {
    const frame = rackFrame.value
    const overlay = patchLineOverlay.value
    const bounds = overlay?.getBoundingClientRect()
    if (!frame || !bounds?.width || !bounds.height) {
        patchLines.value = []
        return
    }

    const scaleX = overlay.clientWidth / bounds.width
    const scaleY = overlay.clientHeight / bounds.height
    const portAttribute = patchMode.value ? 'data-rack-patch-port' : 'data-rack-port'
    const direction = patchMode.value ? -1 : 1
    patchLines.value = localPatchConnections.value.flatMap(({ start, end }, index) => {
        const source = socketPoint(start)
        const target = socketPoint(end)
        if (!source || !target) return []

        const track = index % patchColors.length
        const lane = source.gutter + 7 + track * 2
        const bend = direction * (4 + (patchMode.value ? track : 0))
        const sourceTurn = { x: source.x, y: source.y + bend }
        const targetTurn = { x: target.x, y: target.y + bend }
        const points = [source, sourceTurn, { x: lane, y: sourceTurn.y }, { x: lane, y: targetTurn.y }, targetTurn, target]
        const segments = points.slice(1).map((point, segmentIndex) => segmentStyle(points[segmentIndex], point))
        return [{ key: rackPortKey(start), color: patchColors[track], segments }]
    })

    function socketPoint({ slot, port }) {
        const row = frame.querySelector(`[data-rack-slot="${slot}"]`)
        const socket = row?.querySelector(`[${portAttribute}="${port}"]`)?.getBoundingClientRect()
        if (!socket?.width) return null

        const edge = patchMode.value ? socket.top : socket.bottom
        const slotLabelRight = row.querySelector('.network-rack-config__slot-number').getBoundingClientRect().right
        return {
            x: (socket.left + socket.width / 2 - bounds.left) * scaleX,
            y: (edge - bounds.top) * scaleY - direction,
            gutter: (slotLabelRight - bounds.left) * scaleX,
        }
    }
}

function segmentStyle(start, end) {
    return {
        left: `${Math.min(start.x, end.x) - 1}px`,
        top: `${Math.min(start.y, end.y) - 1}px`,
        width: `${Math.abs(end.x - start.x) + 2}px`,
        height: `${Math.abs(end.y - start.y) + 2}px`,
    }
}

function slotLabel(number) {
    const label = `Platz ${number}, ${rackModuleLabels[slots.value[number - 1]] ?? 'frei'}`
    if (number !== wiredPatchPanelSlot.value) return label

    const ports = incomingCableCount.value === 1 ? '1 Anschluss' : `${incomingCableCount.value} Anschlüsse`
    return `${label}, ${ports} belegt`
}

function patchedPorts(slot) {
    return localPatchConnections.value.flatMap(({ start, end }) => [start, end])
        .filter(endpoint => endpoint.slot === slot).map(endpoint => endpoint.port)
}

function portCount(slot) {
    const module = slots.value[slot]
    const incoming = slot + 1 === wiredPatchPanelSlot.value ? incomingPorts.value : []
    return Math.max(rackModulePorts[module], ...incoming.map(port => port + 1), ...patchedPorts(slot).map(port => port + 1))
}

function portName({ slot, port }) {
    return `${rackModuleLabels[slots.value[slot]]}, Buchse ${port + 1}`
}

function portNameWithSlot(endpoint) {
    return `Platz ${endpoint.slot + 1}, ${portName(endpoint)}`
}

function patchedPeer(endpoint) {
    const key = rackPortKey(endpoint)
    const connection = patchByPort.value.get(key)
    if (!connection) return null

    return rackPortKey(connection.start) === key ? connection.end : connection.start
}

function incomingDeviceLabel(port) {
    return props.incomingConnections?.find(assignment => assignment.port === port)?.deviceLabel
}

function portLabel(endpoint) {
    const peer = patchedPeer(endpoint)
    const device = endpoint.slot + 1 === wiredPatchPanelSlot.value ? incomingDeviceLabel(endpoint.port) : null
    return [portNameWithSlot(endpoint), peer && `verbunden mit ${portNameWithSlot(peer)}`, device && `Kabelkanal zu ${device}`]
        .filter(Boolean).join(', ')
}

function togglePatchMode() {
    patchMode.value = !patchMode.value
    selectedPort.value = null
    selection.value = null
    feedback.value = patchMode.value ? 'Wähle zwei freie Buchsen zum Verbinden oder eine belegte Buchse zum Entfernen.' : ''
}

function choosePort(endpoint) {
    const start = selectedPort.value
    const key = rackPortKey(endpoint)
    if (start && rackPortKey(start) === key) {
        selectedPort.value = null
        feedback.value = 'Wähle eine Buchse.'
        return
    }
    if (patchByPort.value.has(key)) {
        selectedPort.value = endpoint
        feedback.value = 'Diese Buchse ist bereits mit einem Patchkabel verbunden.'
        return
    }
    if (!start || selectedPatch.value) {
        selectedPort.value = endpoint
        feedback.value = 'Wähle die zweite Buchse an einer anderen Komponente.'
        return
    }
    if (start.slot === endpoint.slot) {
        feedback.value = 'Wähle eine freie Buchse an einer anderen Komponente.'
        return
    }

    localPatchConnections.value.push({ start, end: endpoint })
    selectedPort.value = null
    feedback.value = 'Patchkabel im Serverschrank verbunden.'
}

function removePatchConnection(start) {
    const key = rackPortKey(start)
    const portToFocus = selectedPatch.value && rackPortKey(selectedPatch.value.start) === key ? selectedPort.value : null
    localPatchConnections.value = localPatchConnections.value.filter(connection => rackPortKey(connection.start) !== key)
    if (portToFocus) {
        selectedPort.value = null
        nextTick(() => rackFrame.value?.querySelector(`[data-rack-slot="${portToFocus.slot}"] [data-rack-patch-port="${portToFocus.port}"]`)?.focus())
    }
    feedback.value = 'Patchkabel im Serverschrank entfernt.'
}

function startDrag(event, type, index) {
    if (!event.isPrimary || event.button !== 0 || gesture) return
    handledButton = null
    if (!type) return

    const button = event.currentTarget
    gesture = { pointerId: event.pointerId, button, type, index, x: event.clientX, y: event.clientY }
    button.setPointerCapture(event.pointerId)
}

function moveDrag(event) {
    if (gesture?.pointerId !== event.pointerId) return
    const { clientX: x, clientY: y } = event
    if (!drag.value && Math.hypot(x - gesture.x, y - gesture.y) < 6) return

    const slot = document.elementFromPoint(x, y)?.closest('[data-rack-slot]')
    drag.value = { type: gesture.type, index: gesture.index, x, y,
        target: slot && editor.value?.contains(slot) ? Number(slot.dataset.rackSlot) : null }
}

function finishDrag(event) {
    if (gesture?.pointerId !== event.pointerId) return
    moveDrag(event)
    const drop = drag.value
    if (drop) handledButton = gesture.button
    cancelDrag(event)
    if (drop?.target != null) placeModule(drop, drop.target)
}

function cancelDrag(event) {
    if (gesture?.pointerId !== event.pointerId) return
    gesture = null
    drag.value = null
}

function consumeDragClick(event) {
    if (!event.detail || event.currentTarget !== handledButton) return false
    handledButton = null
    return true
}

function chooseComponent(event, type) {
    if (consumeDragClick(event)) return
    feedback.value = ''
    selection.value = selection.value?.index === null && selection.value.type === type ? null : { type, index: null }
}

function chooseSlot(event, index) {
    if (consumeDragClick(event)) return
    const source = selection.value
    if (source && source.index !== index) {
        placeModule(source, index)
        return
    }
    const type = slots.value[index]
    feedback.value = type ? '' : 'Wähle zuerst eine Komponente aus.'
    selection.value = type && source?.index !== index ? { type, index } : null
}

function placeModule(source, target) {
    if (source.index === target) return
    const occupied = slots.value[target]
    if (source.index === null && occupied) {
        feedback.value = `Platz ${target + 1} ist belegt.`
        return
    }

    if (source.index !== null) {
        function movePort(endpoint) {
            if (endpoint.slot === source.index) return { ...endpoint, slot: target }
            if (endpoint.slot === target) return { ...endpoint, slot: source.index }
            return endpoint
        }
        localPatchConnections.value = localPatchConnections.value.map(({ start, end }) => ({ start: movePort(start), end: movePort(end) }))
        slots.value[source.index] = occupied ?? null
    }
    slots.value[target] = source.type
    slots.value = normalizeRackModules(slots.value)
    selection.value = null
    selectedPort.value = null
    feedback.value = occupied
        ? `Plätze ${source.index + 1} und ${target + 1} getauscht.`
        : `${rackModuleLabels[source.type]} auf Platz ${target + 1} eingesetzt.`
}

function removeModule(index) {
    const module = slots.value[index]
    const patch = selectedPatch.value
    if (selectedPort.value?.slot === index || patch?.start.slot === index || patch?.end.slot === index) selectedPort.value = null
    localPatchConnections.value = localPatchConnections.value.filter(({ start, end }) => start.slot !== index && end.slot !== index)
    slots.value[index] = null
    slots.value = normalizeRackModules(slots.value)
    if (selection.value?.index === index) selection.value = null
    feedback.value = `${rackModuleLabels[module]} aus Platz ${index + 1} entfernt.`
    nextTick(() => editor.value?.querySelector(`[data-rack-slot="${Math.min(index, slotCount.value - 1)}"] button`)?.focus())
}
</script>

<style scoped>
.network-rack-config__editor {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
    grid-template-areas: "cabinet palette";
    align-items: start;
    gap: clamp(12px, 3vw, 24px);
}

.network-rack-config__palette { grid-area: palette; }
.network-rack-config__cabinet { grid-area: cabinet; position: relative; }

.network-rack-config__choices {
    display: grid;
    gap: 6px;
}

.network-rack-config__choice,
.network-rack-config__slot {
    display: flex;
    min-block-size: 44px;
    min-inline-size: 0;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    border: 1px solid #b8c7d5;
    border-radius: 4px;
    background: #fff;
    color: #20364e;
    font-size: .875rem;
    font-weight: 600;
    text-align: start;
}

.network-rack-config__choice,
.network-rack-config__slot.is-occupied {
    cursor: grab;
    touch-action: none;
}

.network-rack-config__choice:is(:hover, [aria-pressed="true"]),
.network-rack-config__slot:is(:hover, [aria-pressed="true"]) {
    border-color: #3b6290;
    background: #eaf2fb;
}

.network-rack-config__editor button:focus-visible {
    outline: 2px solid #215c99;
    outline-offset: -2px;
}

.network-rack-config__frame {
    --rack-cable-gutter: 0px;
    display: grid;
    gap: 3px;
    padding: 7px;
    border: 3px solid #405366;
    border-radius: 7px;
    background: #273747;
    list-style: none;
}

.network-rack-config__frame.has-cables { --rack-cable-gutter: 16px; }

.network-rack-config__patch-lines {
    position: absolute;
    z-index: 2;
    inset: 0;
    pointer-events: none;
}

.network-rack-config__patch-line > span {
    position: absolute;
    border-radius: 1px;
    background: currentColor;
    box-shadow: 0 0 0 1px #242c36;
}

.network-rack-config__patch-line.is-selected > span {
    z-index: 1;
    box-shadow: 0 0 0 1px #fff, 0 0 0 2px #242c36;
}

.network-rack-config__frame > li {
    display: grid;
    grid-template-columns: calc(24px + var(--rack-cable-gutter)) minmax(0, 1fr) 44px;
    min-block-size: 48px;
    align-items: center;
    gap: 4px;
    padding: 2px;
    border-radius: 4px;
    background: #526779;
}

.network-rack-config__frame > li.has-cable-run { position: relative; }

.network-rack-config__frame > li.has-cable-run::before {
    position: absolute;
    z-index: 1;
    inset-block: -3px 0;
    inset-inline-start: 29px;
    border-inline-start: 4px solid #c8b2ec;
    content: '';
    pointer-events: none;
}

.network-rack-config__frame > li.has-cable-run:first-child::before { inset-block-start: -10px; }

.network-rack-config__frame > li.has-cable-entry::before {
    grid-area: 1 / 1 / 2 / -1;
    inset-block-start: -5px;
    inset-block-end: calc(50% - 2px);
    inset-inline-start: 27px;
    inline-size: 29px;
    border-block-end: 4px solid #c8b2ec;
    border-end-start-radius: 10px;
}

.network-rack-config__frame > li.has-cable-entry:first-child::before { inset-block-start: -12px; }

.network-rack-config__frame > li.is-drop-target {
    background: #367ab1;
    outline: 2px solid #fff;
    outline-offset: -2px;
}

.network-rack-config__frame > li.is-dragging .network-rack-config__slot {
    opacity: .55;
}

.network-rack-config__slot-number {
    inline-size: 24px;
    color: #fff;
    font-size: .8125rem;
    font-variant-numeric: tabular-nums;
    text-align: center;
}

.network-rack-config__slot:not(.is-occupied) {
    border-style: dashed;
    background: #e7edf2;
    font-weight: 400;
    cursor: pointer;
}

.network-rack-config__remove {
    color: #fff;
}

.network-rack-config__ports {
    grid-column: 2 / -1;
    display: flex;
    flex-wrap: wrap;
    gap: 9px 4px;
    padding-block: 5px 2px;
}

.network-rack-config__port-actions { flex: 0 0 100%; }

.network-rack-config__assignments {
    grid-column: 2 / -1;
    padding-block: 3px 5px;
    color: #fff;
    font-size: .8125rem;
}

.network-rack-config__assignments dl {
    display: grid;
    gap: 2px;
    margin-block-start: 3px;
}

.network-rack-config__assignments dl > div {
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr);
    gap: .5rem;
}

.network-rack-config__assignments dt { color: #efe5fc; }
.network-rack-config__assignments dd { margin: 0; }

.network-rack-config__port {
    position: relative;
    min-inline-size: 44px;
    min-block-size: 44px;
    border: 1px solid #9facbc;
    border-radius: 4px;
    background: #f3f7fb;
    color: #20364e;
    font-size: .875rem;
    font-weight: 650;
}

.network-rack-config__port[aria-pressed="true"] {
    border-color: #123e76;
    background: #cfe2ff;
    box-shadow: inset 0 0 0 2px #215c99;
}

.network-rack-config__port.has-patch-cable {
    border-color: #145f56;
    background: #d4f4ec;
    color: #14574d;
}

.network-rack-config__port.has-patch-cable::after {
    position: absolute;
    inset-block-start: 4px;
    inset-inline-end: 4px;
    inline-size: 6px;
    block-size: 6px;
    border-radius: 2px;
    background: currentColor;
    content: '';
}

.network-rack-config__patch-list { padding-inline-start: 0; list-style: none; }

.network-rack-config__patch-list > li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border-block-end: 1px solid #d9e1e9;
    font-size: .875rem;
}

.network-rack-config__ghost {
    position: fixed;
    z-index: 3000;
    display: flex;
    align-items: center;
    gap: 8px;
    inline-size: max-content;
    padding: 7px 9px;
    transform: translate(-50%, -50%);
    border: 1px solid #3b6290;
    border-radius: 4px;
    background: #fff;
    color: #20364e;
    box-shadow: 0 4px 12px #20364e40;
    pointer-events: none;
}

@media (max-width: 630px) {
    .network-rack-config__editor {
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas: "palette" "cabinet";
    }

    .network-rack-config__choices {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .network-rack-config__choice {
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
    }

    .network-rack-config__choice > :first-child {
        flex: none;
        max-inline-size: 100%;
    }
}
</style>
