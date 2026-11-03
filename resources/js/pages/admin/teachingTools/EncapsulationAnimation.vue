<template>
    <section class="encapsulation-animation" aria-label="Animation zur Kapselung">
        <div class="animation-heading"><span>TCP und Ethernet · ein Netzwerkabschnitt</span><span>Schritt {{ step + 1 }} von {{ stages.length }}</span></div>
        <div class="animation-stations" aria-label="Übertragungsweg">
            <span :class="{ active: current.phase === 'Sender' }">Sender</span><span aria-hidden="true">→</span>
            <span :class="{ active: current.phase === 'Netzwerk' }">Netzwerk</span><span aria-hidden="true">→</span>
            <span :class="{ active: current.phase === 'Empfänger' }">Empfänger</span>
        </div>
        <div class="animation-stage">
            <strong class="animation-label">{{ current.label }}</strong>
            <svg class="animation-packet" viewBox="0 0 420 330" role="img" :aria-label="diagramLabel">
                <Transition name="layer">
                    <g v-if="current.parts.includes('ethernet')" class="diagram-layer">
                        <path :id="diagramId + '-ethernet'" class="diagram-ethernet" d="M 65 165 A 145 145 0 0 1 355 165" />
                        <text class="diagram-text" dy="5"><textPath :href="'#' + diagramId + '-ethernet'" startOffset="50%">Ethernet-Kopf · MAC-Adressen</textPath></text>
                    </g>
                </Transition>
                <Transition name="layer">
                    <g v-if="current.parts.includes('trailer')" class="diagram-layer">
                        <path :id="diagramId + '-trailer'" class="diagram-trailer" d="M 65 165 A 145 145 0 0 0 355 165" />
                        <text class="diagram-text" dy="5"><textPath :href="'#' + diagramId + '-trailer'" startOffset="50%">Rahmenende · Prüfsumme</textPath></text>
                    </g>
                </Transition>
                <Transition name="layer">
                    <g v-if="current.parts.includes('ip')" class="diagram-layer">
                        <path :id="diagramId + '-ip'" class="diagram-ip" d="M 95 165 A 115 115 0 0 1 325 165" />
                        <text class="diagram-text" dy="5"><textPath :href="'#' + diagramId + '-ip'" startOffset="50%">IP-Kopf · IP-Adressen</textPath></text>
                    </g>
                </Transition>
                <Transition name="layer">
                    <g v-if="current.parts.includes('tcp')" class="diagram-layer">
                        <path :id="diagramId + '-tcp'" class="diagram-tcp" d="M 125 165 A 85 85 0 0 1 295 165" />
                        <text class="diagram-text" dy="5"><textPath :href="'#' + diagramId + '-tcp'" startOffset="50%">TCP-Kopf · Ports</textPath></text>
                    </g>
                </Transition>
                <circle class="diagram-data" cx="210" cy="165" r="68" />
                <text x="210" y="161" class="diagram-data-text">Nachricht</text>
                <text x="210" y="179" class="diagram-data-subtitle">Anwendungsdaten</text>
            </svg>
            <p class="animation-schematic">Schematisch: Die Bestandteile stehen in Wirklichkeit hintereinander.</p>
            <p class="animation-description" aria-live="polite" aria-atomic="true">{{ current.description }}</p>
        </div>
        <div class="animation-controls">
            <button type="button" :disabled="step === 0" aria-label="Vorheriger Animationsschritt" @click="move(-1)">Zurück</button>
            <button type="button" class="primary" :disabled="step === stages.length - 1" aria-label="Nächster Animationsschritt" @click="move(1)">Weiter</button>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const stages = [
    { phase: 'Sender', label: 'Anwendungsdaten', parts: ['data'], description: 'Die Anwendung erzeugt die Daten, die übertragen werden sollen.' },
    { phase: 'Sender', label: 'TCP-Segment', parts: ['tcp', 'data'], description: 'TCP ergänzt unter anderem Ports. Das Ergebnis heißt TCP-Segment.' },
    { phase: 'Sender', label: 'IP-Paket', parts: ['ip', 'tcp', 'data'], description: 'IP ergänzt Quell- und Ziel-IP-Adresse. Das Segment steckt nun im IP-Paket.' },
    { phase: 'Sender', label: 'Ethernet-Rahmen', parts: ['ethernet', 'ip', 'tcp', 'data', 'trailer'], description: 'Ethernet ergänzt lokale MAC-Adressen und eine Prüfsumme. Der Rahmen enthält das IP-Paket.' },
    { phase: 'Netzwerk', label: 'Übertragung', parts: ['ethernet', 'ip', 'tcp', 'data', 'trailer'], description: 'Der Ethernet-Rahmen wird auf diesem Netzwerkabschnitt übertragen.' },
    { phase: 'Empfänger', label: 'Ethernet-Rahmen empfangen', parts: ['ethernet', 'ip', 'tcp', 'data', 'trailer'], description: 'Der Empfänger prüft den Ethernet-Rahmen und gibt das enthaltene IP-Paket weiter.' },
    { phase: 'Empfänger', label: 'IP-Paket', parts: ['ip', 'tcp', 'data'], description: 'Der Ethernet-Rahmen ist entfernt. IP wertet die Adressinformationen aus.' },
    { phase: 'Empfänger', label: 'TCP-Segment', parts: ['tcp', 'data'], description: 'IP gibt das TCP-Segment an TCP weiter. TCP ordnet es der Anwendung zu.' },
    { phase: 'Empfänger', label: 'Anwendungsdaten', parts: ['data'], description: 'Die Anwendung erhält die Daten. Die zusätzlichen Transportinformationen wurden ausgewertet.' },
]
const route = useRoute()
const router = useRouter()
const diagramId = useId()
const step = ref(0)
const current = computed(() => stages[step.value])
const partLabels = {
    ethernet: 'Ethernet-Kopf mit MAC-Adressen',
    ip: 'IP-Kopf mit IP-Adressen',
    tcp: 'TCP-Kopf mit Ports',
    data: 'Nachricht mit Anwendungsdaten',
    trailer: 'Rahmenende mit Prüfsumme',
}
const diagramLabel = computed(() => `Schematischer Aufbau: ${current.value.parts.map(part => partLabels[part]).join(', ')}.`)

function stepFromQuery(value) {
    if (typeof value !== 'string' || !/^\d+$/.test(value)) return 0
    const parsed = Number(value)
    return parsed >= 0 && parsed < stages.length ? parsed : 0
}

watch(() => route?.query?.encapStep, value => {
    const selected = stepFromQuery(value)
    if (step.value !== selected) {
        step.value = selected
    }
}, { immediate: true })

function goTo(index) {
    step.value = Math.max(0, Math.min(stages.length - 1, index))
    if (router && route) {
        router.push({ query: { ...route.query, encapStep: String(step.value) }, hash: route.hash })
    }
}

function move(direction) {
    goTo(step.value + direction)
}
</script>

<style scoped>
.encapsulation-animation { color: #1b3049; }
.animation-heading { display: flex; justify-content: space-between; gap: 12px; color: #58738d; font-size: .83rem; }
.animation-stations { display: flex; justify-content: center; align-items: center; gap: clamp(12px, 3vw, 40px); margin: 8px 0; color: #718397; font-size: .9rem; }
.animation-stations .active { color: #1f4d7d; font-weight: 700; border-bottom: 2px solid #3272b1; }
.animation-stage { display: flex; flex-direction: column; align-items: center; justify-content: flex-start; gap: 6px; }
.animation-label { color: #193c68; font-size: 1.05rem; min-height: 1.5em; text-align: center; }
.animation-packet { display: block; width: min(100%, 560px); height: auto; flex: none; overflow: visible; }
.animation-packet path { fill: none; stroke-width: 26; stroke-linecap: round; }
.diagram-ethernet { stroke: #b5d8ef; }.diagram-ip { stroke: #c8ddf2; }.diagram-tcp { stroke: #d3eadd; }.diagram-trailer { stroke: #e3d8f3; }
.diagram-data { fill: #fff0db; stroke: #e1c59d; stroke-width: 1; }
.diagram-text, .diagram-data-text, .diagram-data-subtitle { text-anchor: middle; fill: #193c68; font-family: inherit; font-size: 14px; font-weight: 600; }
.diagram-data-text { font-size: 16px; font-weight: 700; }.diagram-data-subtitle { font-size: 10px; font-weight: 400; }
.diagram-layer { transform-origin: 210px 165px; }
.layer-enter-active, .layer-leave-active { transition: opacity .35s ease, transform .35s ease; }.layer-enter-from, .layer-leave-to { opacity: 0; transform: scale(.9); }
.animation-schematic { margin: 0; color: #58738d; font-size: .78rem; text-align: center; }
.animation-description { max-width: 68ch; min-height: 2.9em; margin: 0; text-align: center; font-size: .9rem; line-height: 1.45; }
.animation-controls { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; }.animation-controls button { min-height: 38px; padding: 6px 16px; border: 1px solid #b8cde1; border-radius: 8px; background: #fff; color: #265989; font-weight: 700; }.animation-controls .primary { background: #2868a8; border-color: #2868a8; color: #fff; }.animation-controls button:disabled { opacity: .45; cursor: default; }.animation-controls button:focus-visible { outline: 3px solid #2c6fb6; outline-offset: 2px; }
@media (max-width: 520px) { .animation-heading { flex-wrap: wrap; }.diagram-text { font-size: 17px; }.diagram-data-text { font-size: 18px; }.diagram-data-subtitle { font-size: 12px; }.animation-description { min-height: 4.35em; } }
@media (prefers-reduced-motion: reduce) { .layer-enter-active, .layer-leave-active { transition: none; } }
</style>
