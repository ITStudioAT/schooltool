<template>
    <v-dialog :model-value="true" max-width="780" scrollable persistent aria-labelledby="network-rack-configuration">
        <v-card>
            <v-card-title id="network-rack-configuration">Serverschrank konfigurieren</v-card-title>
            <v-card-text>
                <p class="text-body-2 mb-4">
                    Ziehe eine Komponente auf einen Platz im Serverschrank oder wähle sie aus und klicke auf den Zielplatz.
                </p>
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
                        <ol class="network-rack-config__frame" role="list">
                            <li v-for="number in slotCount" :key="number" :data-rack-slot="number - 1"
                                :class="{ 'is-drop-target': drag?.target === number - 1,
                                    'is-dragging': drag?.index === number - 1 }">
                                <span class="network-rack-config__slot-number" aria-hidden="true">{{ number }}</span>
                                <button type="button" class="network-rack-config__slot"
                                    :class="{ 'is-occupied': slots[number - 1] }"
                                    :aria-label="`Platz ${number}, ${rackModuleLabels[slots[number - 1]] ?? 'frei'}`"
                                    :aria-pressed="selection?.index === number - 1"
                                    @pointerdown="startDrag($event, slots[number - 1], number - 1)"
                                    @click="chooseSlot($event, number - 1)">
                                    <NetworkRackModuleGraphic v-if="slots[number - 1]" :type="slots[number - 1]" />
                                    <span>{{ rackModuleLabels[slots[number - 1]] ?? 'Frei' }}</span>
                                </button>
                                <v-btn v-if="slots[number - 1]" type="button" icon="mdi-delete-outline" variant="text" size="44"
                                    class="network-rack-config__remove"
                                    :aria-label="`${rackModuleLabels[slots[number - 1]]} aus Platz ${number} entfernen`"
                                    :title="`${rackModuleLabels[slots[number - 1]]} entfernen`"
                                    @click="removeModule(number - 1)" />
                            </li>
                        </ol>
                    </section>
                </div>
                <p v-if="feedback" class="text-body-2 mt-3" role="status">{{ feedback }}</p>
                <p v-if="hasLinkedBundles && !slots.includes(patchPanelModule)" class="text-body-2 mt-3" role="status">
                    Beim Speichern werden die Kabelstränge zu diesem Serverschrank getrennt.
                </p>
            </v-card-text>
            <v-card-actions class="justify-end flex-wrap ga-2">
                <v-btn type="button" height="44" variant="text" class="text-none" @click="emit('close')">Abbrechen</v-btn>
                <v-btn type="button" height="44" variant="flat" color="primary" class="text-none"
                    @click="emit('save', normalizeRackModules(slots))">Speichern</v-btn>
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
import { computed, nextTick, ref, shallowRef } from 'vue'
import NetworkRackModuleGraphic from './NetworkRackModuleGraphic.vue'
import { normalizeRackModules, patchPanelModule, rackModuleLabels } from './networkRackModules'

const props = defineProps({
    modules: { type: Array, default: () => [] },
    hasLinkedBundles: Boolean,
})
const emit = defineEmits(['close', 'save'])
const editor = ref(null)
const slots = ref(normalizeRackModules(props.modules))
const slotCount = computed(() => Math.max(6, slots.value.length + 1))
const selection = shallowRef(null)
const drag = shallowRef(null)
const feedback = ref('')
let gesture = null
let handledButton = null

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

    if (source.index !== null) slots.value[source.index] = occupied ?? null
    slots.value[target] = source.type
    slots.value = normalizeRackModules(slots.value)
    selection.value = null
    feedback.value = occupied
        ? `Plätze ${source.index + 1} und ${target + 1} getauscht.`
        : `${rackModuleLabels[source.type]} auf Platz ${target + 1} eingesetzt.`
}

function removeModule(index) {
    const module = slots.value[index]
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
.network-rack-config__cabinet { grid-area: cabinet; }

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
    display: grid;
    gap: 3px;
    padding: 7px;
    border: 3px solid #405366;
    border-radius: 7px;
    background: #273747;
    list-style: none;
}

.network-rack-config__frame > li {
    display: grid;
    grid-template-columns: 24px minmax(0, 1fr) 44px;
    min-block-size: 48px;
    align-items: center;
    gap: 4px;
    padding: 2px;
    border-radius: 4px;
    background: #526779;
}

.network-rack-config__frame > li.is-drop-target {
    background: #367ab1;
    outline: 2px solid #fff;
    outline-offset: -2px;
}

.network-rack-config__frame > li.is-dragging .network-rack-config__slot {
    opacity: .55;
}

.network-rack-config__slot-number {
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
