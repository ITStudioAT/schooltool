<template>
    <article class="networks-game">
        <header class="page-header">
            <div><h1>Netzwerke</h1><p>Kurvenlabor<span class="brand-dot" aria-hidden="true">.</span> Deine Kurve entsteht Punkt für Punkt.</p></div>
            <span class="range-label">Y von −5 bis +5</span>
        </header>
        <section class="workspace" aria-label="Kurve zeichnen">
            <div class="drawing-area">
                <div class="plot-toolbar"><span>Direkt im Gitter zeichnen</span><span>{{ points.size }} von 8 Punkten</span></div>
                <p class="plot-instruction">Klicke direkt auf einen Gitterknoten, um einen Punkt zu setzen oder zu ändern.</p>
                <div class="graph-wrap">
                    <svg viewBox="0 0 900 520" role="group" aria-label="Koordinatensystem" :aria-describedby="descriptionId">
                        <desc :id="descriptionId">{{ graphDescription }}</desc>
                        <line v-for="height in heights" :key="`horizontal-${height}`" :x1="plot.left" :y1="yPixel(height)"
                            :x2="plot.right + 15" :y2="yPixel(height)" :stroke="height === 0 ? '#163b54' : '#dce7ee'"
                            :stroke-width="height === 0 ? 2 : 1" />
                        <line v-for="column in columns" :key="`vertical-${column}`" :x1="xPixel(column)" :y1="plot.top"
                            :x2="xPixel(column)" :y2="plot.bottom" stroke="#dce7ee" />
                        <line :x1="plot.left" :y1="plot.bottom + 8" :x2="plot.left" :y2="plot.top - 17" stroke="#163b54" stroke-width="2" />
                        <path :d="`M ${plot.left - 5} ${plot.top - 9} L ${plot.left} ${plot.top - 19} L ${plot.left + 5} ${plot.top - 9}`" fill="none" stroke="#163b54" stroke-width="2" />
                        <path :d="`M ${plot.right + 8} ${plot.zero - 5} L ${plot.right + 18} ${plot.zero} L ${plot.right + 8} ${plot.zero + 5}`" fill="none" stroke="#163b54" stroke-width="2" />
                        <path v-if="curvePath" :d="curvePath" class="curve-path" fill="none" stroke="#087c85" stroke-width="4" stroke-linecap="round" />
                        <circle :cx="plot.left" :cy="plot.zero" r="5" fill="#163b54" class="origin-point" />
                        <g v-for="node in nodes" :key="`${node.x}-${node.y}`" class="grid-node-choice"
                            :class="{ 'is-point': points.get(node.x) === node.y, 'is-current': points.get(node.x) === node.y && selectedX === node.x }"
                            :data-x="node.x" :data-y="node.y" role="button" tabindex="0"
                            :aria-label="`Punkt (${node.x} | ${node.y}) ${points.get(node.x) === node.y ? 'auswählen' : 'setzen'}`"
                            :aria-pressed="points.get(node.x) === node.y" @click="setPoint(node.x, node.y)"
                            @keydown.enter.prevent="setPoint(node.x, node.y)" @keydown.space.prevent="setPoint(node.x, node.y)">
                            <circle :cx="xPixel(node.x)" :cy="yPixel(node.y)" r="18" class="node-hit" fill="transparent" />
                            <circle :cx="xPixel(node.x)" :cy="yPixel(node.y)" :r="points.get(node.x) === node.y ? 7 : 4" class="node-marker" />
                        </g>
                    </svg>
                </div>
                <div class="plot-footer"><span class="line-key"><i aria-hidden="true"></i>Glatte Kurve ab dem Ursprung (0 | 0)</span><button type="button" :disabled="!history.length" @click="undo">↶ Rückgängig</button></div>
            </div>
            <aside class="controls" aria-label="Punkte wählen">
                <section class="step">
                    <h2>Ein Klick, ein Punkt</h2>
                    <p>Jeder kleine Kreis ist ein Gitterknoten. Klicke ihn an: Der Punkt und die Kurve erscheinen sofort.</p>
                    <p>Zum Ändern klickst du in derselben senkrechten Spalte auf eine andere Höhe.</p>
                    <p>Zum Entfernen klickst du den gesetzten Punkt an und danach auf „Punkt entfernen“.</p>
                </section>
                <p class="status" role="status" aria-live="polite">{{ status }}</p>
                <button type="button" class="delete-button" :disabled="!points.has(selectedX)" @click="removePoint">Punkt entfernen</button>
            </aside>
        </section>
        <section class="points-section" aria-label="Deine Punkte">
            <div class="points-heading"><h2>Deine Punkte</h2><button type="button" :disabled="!points.size" @click="reset">Alles zurücksetzen</button></div>
            <div class="point-list">
                <div v-for="column in columns" :key="column" class="point-chip" :class="{ 'is-empty': !points.has(column) }" :aria-current="selectedX === column">
                    <span>X = {{ column }}</span><strong>{{ points.has(column) ? `Y = ${signed(points.get(column))}` : 'Noch offen' }}</strong>
                </div>
            </div>
            <p class="help">Acht senkrechte Spalten, jeweils elf Höhen von −5 bis +5. Pro Spalte gibt es einen Punkt. Die glatte Kurve startet im Ursprung und verläuft von links nach rechts durch deine Punkte.</p>
        </section>
        <footer class="page-footer">Ausprobieren. Verändern. Deine eigene Kurve zeichnen.</footer>
    </article>
</template>

<script setup>
import { computed, ref, useId } from 'vue'
import { curveSegments } from './networkCurve'

// Port of the current Kurvenlabor Site, version 4 (a26c1bd), with component-local state.
const descriptionId = useId()
const columns = Array.from({ length: 8 }, (_, index) => index + 1)
const heights = Array.from({ length: 11 }, (_, index) => 5 - index)
const nodes = columns.flatMap(x => heights.map(y => ({ x, y })))
const plot = { left: 58, right: 850, top: 38, bottom: 478, zero: 258 }
const points = ref(new Map())
const selectedX = ref(null)
const history = ref([])
const status = ref('Los geht’s: Klicke direkt auf einen Gitterknoten.')
const sortedPoints = computed(() => [...points.value].sort((first, second) => first[0] - second[0]))
const graphDescription = computed(() => `Unbeschriftetes Koordinatensystem mit acht X-Positionen und elf Y-Höhen von −5 bis +5. Jeder Gitterknoten ist direkt anklickbar. ${sortedPoints.value.length ? sortedPoints.value.map(([x, y]) => `Punkt (${x} | ${y})`).join(', ') : 'Noch keine Punkte gesetzt.'} Die glatte Kurve beginnt im Ursprung.`)
const curvePath = computed(() => {
    if (!points.value.size) {
        return ''
    }
    const segments = curveSegments([[0, 0], ...sortedPoints.value])
    return `M ${pixelPair([0, 0])} ` + segments.map(segment =>
        `C ${pixelPair(segment.control1)} ${pixelPair(segment.control2)} ${pixelPair(segment.end)}`,
    ).join(' ')
})

function xPixel(x) {
    return plot.left + x * (plot.right - plot.left) / 8
}

function yPixel(y) {
    return plot.zero - y * (plot.bottom - plot.top) / 10
}

function pixelPair([x, y]) {
    return `${xPixel(x)},${yPixel(y)}`
}

function signed(value) {
    return value > 0 ? `+${value}` : String(value).replace('-', '−')
}

function remember() {
    history.value.push({ points: [...points.value], selectedX: selectedX.value })
}

function setPoint(x, y) {
    if (!Number.isInteger(x) || x < 1 || x > 8 || !Number.isInteger(y) || y < -5 || y > 5) {
        return
    }
    const exists = points.value.has(x)
    if (points.value.get(x) !== y) {
        remember()
        points.value.set(x, y)
    }
    selectedX.value = x
    status.value = `Punkt (${x} | ${signed(y)}) ${exists ? 'aktualisiert' : 'gesetzt'}. Klicke direkt den nächsten Gitterknoten an.`
}

function removePoint() {
    if (!points.value.has(selectedX.value)) {
        return
    }
    remember()
    const x = selectedX.value
    points.value.delete(x)
    selectedX.value = null
    status.value = `Punkt bei X = ${x} entfernt.`
}

function undo() {
    const previous = history.value.pop()
    if (!previous) {
        return
    }
    points.value = new Map(previous.points)
    selectedX.value = previous.selectedX
    status.value = 'Letzte Änderung rückgängig gemacht.'
}

function reset() {
    if (!points.value.size) {
        return
    }
    remember()
    points.value.clear()
    selectedX.value = null
    status.value = 'Alles zurückgesetzt. Klicke direkt einen Gitterknoten für eine neue Kurve an.'
}
</script>

<style scoped>
.networks-game { max-width: 1280px; padding: 28px 30px 20px; margin: auto; background: #edf3f8; color: #163b54; border-radius: 14px; font-family: 'Segoe UI', Arial, sans-serif; }
.networks-game * { box-sizing: border-box; }
.page-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 28px; }
h1 { font-size: 36px; letter-spacing: -1.3px; line-height: 1.1; font-weight: 700; }
.brand-dot { color: #087c85; }
.page-header p { margin: 10px 0 0; color: #536e81; font-size: 17px; }
.range-label { padding: 9px 13px; background: #dfeaf2; border-radius: 6px; font-size: 14px; white-space: nowrap; }
.workspace { display: grid; grid-template-columns: minmax(0, 1fr) 290px; background: white; border: 1px solid #dce7ee; border-radius: 14px; overflow: hidden; }
.drawing-area { padding: 20px 22px 0; min-width: 0; }
.plot-toolbar { display: flex; align-items: center; justify-content: space-between; font-size: 14px; gap: 10px; color: #536e81; }
.plot-instruction { margin: 17px 0 0; padding: 11px 13px; border-radius: 7px; background: #edf3f8; color: #163b54; font-size: 14px; font-weight: 600; line-height: 1.5; }
.graph-wrap { margin: 16px 0 4px; }
svg { width: 100%; height: auto; display: block; overflow: visible; }
.plot-footer { min-height: 56px; display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px solid #dce7ee; font-size: 12px; color: #5e7788; }
.line-key { display: flex; align-items: center; gap: 7px; }
.line-key i { display: inline-block; width: 22px; height: 3px; background: #087c85; border-radius: 2px; }
button { color: #315b73; min-height: 44px; padding: 8px 0; border: 0; background: transparent; font: inherit; font-size: 13px; cursor: pointer; touch-action: manipulation; }
button:hover:not(:disabled) { color: #087c85; text-decoration: underline; }
button:disabled { cursor: default; opacity: .45; }
button:focus-visible { outline: 3px solid #b45418; outline-offset: 3px; }
.controls { background: #f7fafc; border-left: 1px solid #dce7ee; padding: 24px 22px; }
h2 { font-size: 16px; margin: 0; font-weight: 650; }
.step p { font-size: 13px; color: #536e81; margin: 9px 0 12px; line-height: 1.6; }
.status { padding: 13px 0; border-top: 1px solid #dce7ee; margin-top: 20px; line-height: 1.5; font-size: 13px; min-height: 39px; color: #365e72; }
.delete-button { color: #9f4731; }
.points-section { margin-top: 22px; }
.points-heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; gap: 12px; }
.point-list { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 8px; }
.point-chip { display: flex; flex-direction: column; gap: 5px; align-items: flex-start; background: rgb(255 255 255 / .72); border: 1px solid #d2e0e9; border-radius: 7px; padding: 10px 12px; min-width: 0; }
.point-chip span { font-size: 11px; color: #637e90; }
.point-chip strong { font-size: 17px; font-weight: 600; }
.point-chip.is-empty strong { color: #8ba0ae; font-weight: 400; }
.point-chip[aria-current=true] { border-color: #087c85; box-shadow: inset 0 0 0 1px #087c85; background: #e6f3f3; }
.help { font-size: 12px; color: #5d788a; line-height: 1.6; margin: 12px 0 0; }
.page-footer { text-align: center; color: #7790a0; font-size: 12px; padding-top: 24px; }
.grid-node-choice { cursor: crosshair; outline: none; }
.node-marker { fill: #abc5ce; stroke: #7597a6; stroke-width: 1; pointer-events: none; }
.is-point .node-marker { fill: white; stroke: #087c85; stroke-width: 3; }
.is-current .node-marker { fill: #087c85; stroke: #163b54; stroke-width: 3; }
.grid-node-choice:hover .node-marker, .grid-node-choice:focus-visible .node-marker { fill: #087c85; stroke: #075e65; stroke-width: 3; }
.grid-node-choice:hover .node-hit { fill: #087c851c; }
.grid-node-choice:focus-visible .node-marker { stroke: #b45418; stroke-width: 4; }
.origin-point { pointer-events: none; }
@media (max-width: 1000px) {
    .networks-game { padding: 24px 20px; }
    .workspace { grid-template-columns: minmax(0, 1fr) 250px; }
    .drawing-area { padding: 17px 15px 0; }
    .controls { padding: 21px 16px; }
    .point-chip { padding: 9px; }
    .point-chip strong { font-size: 15px; }
    .plot-footer { flex-wrap: wrap; padding: 8px 0; gap: 3px; }
}
@media (max-width: 720px) {
    .networks-game { padding: 22px 14px; }
    .page-header { margin-bottom: 20px; }
    h1 { font-size: 30px; }
    .page-header p { font-size: 14px; }
    .range-label { display: none; }
    .workspace { grid-template-columns: 1fr; }
    .drawing-area { padding: 15px 12px 0; }
    .controls { border-left: 0; border-top: 1px solid #dce7ee; padding: 20px 15px; }
    .point-list { grid-template-columns: repeat(4, 1fr); }
    .point-chip { padding: 9px 11px; }
    .plot-footer { font-size: 11px; }
    .graph-wrap { overflow-x: auto; padding-bottom: 7px; }
    .graph-wrap svg { min-width: 620px; }
    .plot-instruction { font-size: 13px; }
}
</style>
