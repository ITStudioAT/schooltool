<template>
    <Teleport to="body">
        <Transition name="ns-cinematic">
            <section v-if="reward" :key="reward.id" class="ns-reward-cinematic" :class="[`ns-scene-${reward.chapter}`, { 'ns-scene-online': reward.chapterProgress === 100, 'ns-scene-finale': reward.kind === 'complete', 'ns-scene-still': !motionEnabled }]" :style="scenePalette" role="dialog" aria-modal="true" :aria-labelledby="`${sceneId}-title`" :aria-describedby="`${sceneId}-detail`" @keydown="trapFocus" @wheel.prevent>
                <div class="ns-scene-grain" aria-hidden="true" />
                <div class="ns-scene-art" aria-hidden="true">
                    <svg viewBox="0 0 1200 760" fill="none" preserveAspectRatio="xMidYMid slice">
                        <defs>
                            <pattern :id="`${sceneId}-dots`" width="6" height="6" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="currentColor" /></pattern>
                            <radialGradient :id="`${sceneId}-light`"><stop :stop-color="colors.primary" stop-opacity=".4" /><stop offset="1" :stop-color="colors.primary" stop-opacity="0" /></radialGradient>
                        </defs>
                        <ellipse cx="600" cy="335" rx="560" ry="330" :fill="`url(#${sceneId}-light)`" class="ns-scene-glow" />
                        <g v-if="reward.kind === 'complete'" class="ns-finale-fireworks">
                            <path class="ns-finale-skyline" d="M0 470H80V410H125V370H175V435H225V390H275V450H335V405H385V440H445V380H490V445H550V395H600V430H650V375H695V440H750V400H805V450H855V385H905V435H960V365H1010V415H1060V390H1110V455H1200V760H0Z" :fill="`url(#${sceneId}-dots)`" />
                            <g class="ns-finale-network">
                                <path d="M350 390L515 350L685 350L850 390M350 390L600 460L850 390M515 350L600 460L685 350" />
                                <g v-for="(node, index) in finaleNodes" :key="index" :transform="`translate(${node.x} ${node.y})`" :style="{ '--node-color': colors.modules[index] }"><circle r="16" /><circle r="5" /><path d="M-6 0L-1 5L8 -5" /></g>
                            </g>
                            <g v-for="(burst, index) in fireworks" :key="index" :transform="`translate(${burst.x} ${burst.y})`" class="ns-firework" :style="{ '--firework-color': colors.modules[index % 4], '--launch-delay': `${burst.delay}s`, '--bloom-delay': `${burst.delay + .7}s` }">
                                <path class="ns-firework-launch" :d="`M${index % 2 ? -50 : 50} ${650 - burst.y}Q${index % 2 ? 80 : -80} 160 0 0`" pathLength="100" />
                                <g :transform="`scale(${burst.scale})`">
                                    <circle class="ns-firework-halo" r="105" />
                                    <g class="ns-firework-bloom">
                                        <g v-for="ray in 32" :key="ray" :transform="`rotate(${ray * 11.25})`"><path :d="`M0 -${ray % 3 ? 26 : 40}V-${ray % 3 ? 100 : 125}`" /><circle cx="0" :cy="ray % 3 ? -110 : -134" :r="ray % 4 ? 2 : 3.5" /><path v-if="ray % 2" d="M4 -50L10 -68" class="ns-firework-inner" /></g>
                                    </g>
                                </g>
                            </g>
                        </g>
                        <g v-else class="ns-scene-room">
                            <g class="ns-scene-grid">
                                <path v-for="line in 17" :key="`floor-${line}`" :d="`M600 335L${(line - 9) * 180 + 600} 760`" />
                                <path v-for="line in 9" :key="`cross-${line}`" :d="`M0 ${335 + line * line * 5.5}H1200`" />
                                <path d="M0 0L600 335L1200 0M0 190L600 335L1200 190M0 335H1200" />
                            </g>
                            <g v-for="(rack, index) in racks" :key="index" :transform="`translate(${rack.x} ${rack.y}) scale(${rack.scale})`" class="ns-scene-rack" :style="{ '--rack-color': colors.modules[index % 4] }">
                                <path d="M0 0H115V300H0Z" :fill="`url(#${sceneId}-dots)`" />
                                <path d="M0 0L-24 18V314L0 300M115 0L139 18V314L115 300M-24 314H139" />
                                <g v-for="slot in 8" :key="slot" :transform="`translate(9 ${slot * 33 - 16})`" :class="moduleClass(index * 8 + slot)" :style="{ '--charge-delay': `${180 + index * 55 + slot * 30}ms` }">
                                    <rect width="97" height="24" rx="1" />
                                    <path d="M8 8H53M8 15H42" />
                                    <circle cx="80" cy="12" r="3" />
                                </g>
                            </g>
                            <g v-if="reward.chapter === 1" class="ns-scene-archive" transform="translate(600 290)">
                                <g v-for="column in 7" :key="column" :transform="`translate(${(column - 4) * 42} 0)`" :class="moduleClass(column * 8)" :style="{ '--rack-color': colors.modules[(column - 1) % 4], '--charge-delay': `${column * 90}ms` }">
                                    <g class="ns-archive-data">
                                        <rect x="-14" :y="-45 - (column % 3) * 32" width="28" :height="100 + (column % 3) * 32" :fill="`url(#${sceneId}-dots)`" />
                                        <path :d="`M-14 ${-45 - (column % 3) * 32}H14V55H-14Z`" />
                                        <path v-for="row in 6" :key="row" :d="`M-9 ${row * 12 - 30}H9`" />
                                    </g>
                                </g>
                                <path d="M-180 75L0 135L180 75L0 15Z" />
                            </g>
                            <g v-else-if="reward.chapter === 2" class="ns-scene-vault" transform="translate(600 290)">
                                <circle r="150" :fill="`url(#${sceneId}-dots)`" />
                                <circle r="143" /><circle r="117" /><circle r="87" />
                                <g class="ns-scene-turbine"><path v-for="spoke in 12" :key="spoke" d="M0 -98V-136M-7 -117H7" :transform="`rotate(${spoke * 30})`" /></g>
                                <path d="M0 -58V58M-58 0H58M-42 -42L42 42M-42 42L42 -42" />
                                <circle r="20" class="ns-scene-core" />
                                <g v-for="lock in 4" :key="lock" :transform="`rotate(${lock * 90})`" :class="moduleClass((lock - 1) * 16 + 1)" :style="{ '--rack-color': colors.warm }"><rect x="-13" y="-170" width="26" height="40" rx="3" :class="{ 'ns-vault-bolt-released': lock <= Math.ceil(reward.chapterProgress * 4 / 100) }" /></g>
                            </g>
                            <g v-else-if="reward.chapter === 3" class="ns-scene-radio" transform="translate(600 290)">
                                <path d="M0 -115L-68 145H68ZM0 -115V145M-51 80H51M-34 16H34M-17 -50H17M-51 80L34 16L-17 -50M51 80L-34 16L17 -50" />
                                <circle cy="-118" r="12" class="ns-scene-core" />
                                <path v-for="wave in 4" :key="wave" :d="`M${-wave * 34} ${-118 - wave * 28}Q${-wave * 72} -118 ${-wave * 34} ${-118 + wave * 28}M${wave * 34} ${-118 - wave * 28}Q${wave * 72} -118 ${wave * 34} ${-118 + wave * 28}`" class="ns-scene-radio-wave" :class="{ 'ns-wave-online': wave <= Math.ceil(reward.chapterProgress * 4 / 100) }" :style="{ '--wave-delay': `${wave * 180}ms` }" />
                                <path d="M-125 145L0 184L125 145L0 106Z" />
                            </g>
                            <g v-else class="ns-scene-engine" transform="translate(600 290)">
                                <path d="M-120 -100H120V115H-120ZM-120 -100L-155 -73V142L-120 115M120 -100L155 -73V142L120 115M-155 142H155" :fill="`url(#${sceneId}-dots)`" />
                                <circle r="91" /><circle r="78" />
                                <g class="ns-scene-turbine">
                                    <path v-for="blade in 16" :key="blade" d="M-5 -30L-19 -72L7 -77L17 -40Z" :transform="`rotate(${blade * 22.5})`" :fill="`url(#${sceneId}-dots)`" />
                                </g>
                                <circle r="29" class="ns-scene-core" /><circle r="9" />
                                <path d="M-98 101H98M-98 -86H98M-120 80H-195V-30H-250M120 80H195V-30H250" />
                            </g>
                            <g class="ns-scene-energy" :style="{ '--energy-before': Math.max(.1, (reward.previousChapterProgress ?? 0) / 100), '--energy-after': .4 + (reward.chapterProgress ?? 0) / 100 }">
                                <circle cx="600" cy="290" r="160" /><circle cx="600" cy="290" r="220" />
                                <path d="M0 335H320L400 290H470M730 290H800L880 335H1200" />
                            </g>
                        </g>
                        <g class="ns-scene-points">
                            <circle v-for="(point, index) in points" :key="index" :cx="point.x" :cy="point.y" :r="point.size" :style="{ '--point-delay': `${point.delay}ms` }" />
                        </g>
                    </svg>
                    <template v-if="reward.kind !== 'complete'"><span v-for="spark in 36" :key="spark" class="ns-scene-spark" :style="{ '--spark-angle': `${spark * 137.5}deg`, '--spark-distance': `${30 + (spark % 5) * 7}vmin`, '--spark-delay': `${120 + (spark % 7) * 65}ms`, '--spark-color': colors.modules[spark % 4] }" /></template>
                </div>
                <div class="ns-scene-heading"><span>{{ reward.preview ? 'Vorschau · ' : '' }}{{ reward.kind === 'complete' ? 'Finale' : reward.chapterTitle }}</span><span>{{ reward.kind === 'complete' ? 'Alle Systeme online' : `${reward.chapterSolved} / ${reward.chapterTotal} Aufgaben` }}</span></div>
                <div class="ns-scene-controls"><button ref="motionButton" type="button" class="ns-scene-motion" :aria-pressed="motionEnabled" @click="toggleMotion">{{ motionEnabled ? 'Animation pausieren' : 'Animation abspielen' }}</button><button v-if="soundAvailable" type="button" class="ns-scene-motion" :aria-pressed="soundActive" @click="$emit('toggle-sound')">{{ soundLabel }}</button></div>
                <div class="ns-scene-copy">
                    <p class="ns-scene-signal">{{ sceneMessage }}</p>
                    <h2 :id="`${sceneId}-title`">{{ reward.title }}</h2>
                    <p :id="`${sceneId}-detail`">{{ reward.message }}</p>
                    <div class="ns-scene-progress" role="progressbar" :aria-label="`Fortschritt ${reward.kind === 'complete' ? 'Rechenzentrum' : reward.chapterTitle}`" :aria-valuenow="reward.chapterProgress" aria-valuemin="0" aria-valuemax="100">
                        <span v-for="step in (reward.kind === 'complete' ? 4 : reward.chapterTotal)" :key="step" :class="{ 'ns-scene-step-online': step <= reward.chapterSolved || reward.kind === 'complete' }" :style="reward.kind === 'complete' ? { backgroundColor: colors.modules[step - 1] } : null" />
                    </div>
                </div>
                <button ref="continueButton" type="button" class="ns-scene-continue" :aria-label="reward.preview ? 'Vorschau schließen' : 'Belohnung ausblenden'" @click="$emit('dismiss')">{{ reward.preview ? 'Vorschau schließen' : 'Weiterspielen' }} <span aria-hidden="true">↵</span></button>
            </section>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
const props = defineProps({ reward: { type: Object, default: null }, motion: { type: String, default: 'auto' }, soundLabel: { type: String, default: 'Ton einschalten' }, soundActive: Boolean, soundAvailable: Boolean })
const emit = defineEmits(['dismiss', 'replay', 'toggle-sound'])
const sceneId = `ns-cinematic-${crypto.randomUUID()}`
const continueButton = ref(null), motionButton = ref(null), motionChoice = ref(null)
const motionPreference = window.matchMedia?.('(prefers-reduced-motion: reduce)')
const prefersReducedMotion = ref(motionPreference?.matches ?? false)
const motionEnabled = computed(() => motionChoice.value === null ? props.motion === 'full' || !prefersReducedMotion.value : motionChoice.value)
function preferenceChanged(event) { prefersReducedMotion.value = event.matches }
motionPreference?.addEventListener?.('change', preferenceChanged)
function toggleMotion() {
    motionChoice.value = !motionEnabled.value
    if (motionChoice.value) { emit('replay') }
}
function trapFocus(event) {
    if (event.key !== 'Tab') { return }
    event.preventDefault()
    const buttons = [...event.currentTarget.querySelectorAll('button:not(:disabled)')]
    const index = buttons.indexOf(document.activeElement)
    buttons[(index + (event.shiftKey ? -1 : 1) + buttons.length) % buttons.length]?.focus({ preventScroll: true })
}
const palettes = [
    { tones: [[58, 216, 255], [181, 112, 255], [255, 186, 72], [84, 243, 172]], background: [16, 36, 46], grid: [42, 151, 179] },
    { tones: [[169, 132, 255], [83, 180, 255], [82, 242, 198], [154, 235, 255]], background: [30, 22, 48], grid: [91, 74, 155] },
    { tones: [[255, 194, 72], [62, 218, 199], [255, 136, 67], [255, 227, 134]], background: [43, 32, 18], grid: [155, 122, 47] },
    { tones: [[71, 237, 196], [67, 188, 255], [190, 132, 255], [197, 246, 91]], background: [14, 39, 35], grid: [38, 150, 128] },
    { tones: [[255, 210, 105], [122, 170, 255], [255, 115, 168], [90, 245, 205]], background: [10, 15, 32], grid: [90, 120, 175] },
]
const colors = computed(() => {
    const amount = (Math.min(100, Math.max(0, props.reward?.chapterProgress ?? 0)) / 100) ** 1.25
    const palette = palettes[props.reward?.kind === 'complete' ? 4 : props.reward?.chapter ?? 0] ?? palettes[0]
    const mix = target => `rgb(${target.map(value => Math.round(236 + (value - 236) * amount)).join(', ')})`
    const blend = (initial, target) => `rgb(${target.map((value, index) => Math.round(initial[index] + (value - initial[index]) * amount)).join(', ')})`
    const [primary, secondary, warm, green] = palette.tones.map(mix)
    return { amount, primary, secondary, warm, green, modules: [primary, green, secondary, warm], background: blend([16, 22, 24], palette.background), grid: blend([119, 119, 119], palette.grid) }
})
const scenePalette = computed(() => ({
    '--scene-primary': colors.value.primary, '--scene-secondary': colors.value.secondary, '--scene-warm': colors.value.warm,
    '--scene-ink': colors.value.primary, '--module-fill': colors.value.background, '--scene-grid': colors.value.grid,
}))
let previousFocus = null, focusContext = null
const sceneMessage = computed(() => {
    if (props.reward?.kind === 'complete') { return 'Das Rechenzentrum lebt.' }
    const messages = props.reward?.chapterProgress === 100
        ? ['Maschinenraum online.', 'Archiv wiederhergestellt.', 'Tresor entriegelt.', 'Verbindung steht.']
        : ['Der Maschinenraum erwacht.', 'Das Archiv setzt sich zusammen.', 'Die Sicherungen lösen sich.', 'Das Signal wird stärker.']
    return messages[props.reward?.chapter ?? 0]
})
const racks = [
    { x: 110, y: 110, scale: 1.12 }, { x: 286, y: 173, scale: .76 },
    { x: 408, y: 217, scale: .47 }, { x: 976, y: 110, scale: 1.12 },
    { x: 826, y: 173, scale: .76 }, { x: 738, y: 217, scale: .47 },
    { x: 480, y: 243, scale: .25 }, { x: 691, y: 243, scale: .25 },
]
const finaleNodes = [{ x: 350, y: 390 }, { x: 515, y: 350 }, { x: 685, y: 350 }, { x: 850, y: 390 }]
const fireworks = [
    { x: 270, y: 195, scale: .85, delay: .12 }, { x: 890, y: 165, scale: 1, delay: .5 },
    { x: 590, y: 125, scale: 1.15, delay: .95 }, { x: 420, y: 275, scale: .7, delay: 1.6 },
    { x: 790, y: 285, scale: .8, delay: 2.35 }, { x: 600, y: 225, scale: 1.4, delay: 3.35 },
]
const points = Array.from({ length: 90 }, (_, index) => ({
    x: (index * 137 + 47) % 1200, y: (index * 83 + 17) % 570,
    size: index % 5 === 0 ? 2.2 : .9, delay: (index % 11) * 100,
}))
function moduleClass(module) {
    const activated = Math.ceil((props.reward?.chapterProgress ?? 0) * 64 / 100)
    const previous = Math.ceil((props.reward?.previousChapterProgress ?? 0) * 64 / 100)
    return { 'ns-scene-module': true, 'ns-module-online': module <= activated, 'ns-module-charging': module > previous && module <= activated }
}
function restoreFocus() {
    const target = previousFocus?.isConnected && !previousFocus.disabled ? previousFocus : focusContext?.querySelector('button:not(:disabled)')
    target?.focus({ preventScroll: true })
    previousFocus = null; focusContext = null
}
function keydown(event) {
    if (props.reward && event.key === 'Escape') { event.preventDefault(); emit('dismiss') }
}
watch(() => props.reward?.id, async (value, previous) => {
    if (value && !previous) {
        previousFocus = document.activeElement
        focusContext = previousFocus?.closest('form, .ns-toolbar')
    }
    await nextTick()
    if (props.reward) { continueButton.value?.focus({ preventScroll: true }) }
    else { restoreFocus() }
}, { immediate: true })
document.addEventListener('keydown', keydown)
onBeforeUnmount(() => { document.removeEventListener('keydown', keydown); motionPreference?.removeEventListener?.('change', preferenceChanged); if (props.reward) { restoreFocus() } })
</script>

<style scoped>
.ns-reward-cinematic { position: fixed; z-index: 2500; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; overflow: hidden; isolation: isolate; padding: max(28px, env(safe-area-inset-top)) clamp(22px, 5vw, 76px) max(28px, env(safe-area-inset-bottom)); background: #060606; color: #f5f5f0; font-family: inherit; touch-action: none; }
.ns-reward-cinematic *, .ns-reward-cinematic *::before, .ns-reward-cinematic *::after { box-sizing: border-box; }
.ns-scene-grain { position: absolute; z-index: -1; inset: 0; background-image: radial-gradient(#fff4 .65px, transparent .85px); background-size: 4px 4px; opacity: .15; pointer-events: none; }
.ns-scene-art { position: absolute; z-index: -1; inset: 0; overflow: hidden; }
.ns-scene-art::after { content: ''; position: absolute; inset: 0; background: linear-gradient(transparent 34%, #06060642 50%, #060606 87%), linear-gradient(90deg, #06060670, transparent 20%, transparent 80%, #06060670); }
.ns-scene-art svg { width: 100%; height: 100%; color: var(--scene-ink, #f5f5f0); }
.ns-scene-room { transform-origin: 600px 300px; animation: ns-room-approach 4.3s cubic-bezier(.16,.7,.3,1) both; }
.ns-scene-grid { stroke: var(--scene-grid, #777); stroke-width: .8; stroke-dasharray: 1 6; stroke-linecap: round; opacity: .5; }
.ns-scene-rack { stroke: #92928e; stroke-width: 1.4; }
.ns-scene-rack > path:first-child { opacity: .4; }
.ns-scene-module { stroke: #373735; fill: #090909; transition: stroke .8s, fill .8s; }
.ns-scene-module circle { fill: #343432; stroke: none; }
.ns-module-online { stroke: var(--rack-color, #eeeee7); fill: var(--module-fill, #161615); }
.ns-module-online circle { fill: var(--rack-color, #fff); animation: ns-indicator-flow 1.8s var(--charge-delay, 0ms) ease-in-out infinite; }
.ns-module-charging { animation: ns-module-charge 1.4s var(--charge-delay, 240ms) both; }
.ns-scene-engine, .ns-scene-vault, .ns-scene-radio, .ns-scene-archive { stroke: var(--scene-primary); stroke-width: 2; }
.ns-scene-engine > path:first-child { stroke-width: 1; }
.ns-scene-0 .ns-scene-engine { stroke: var(--scene-primary); }
.ns-scene-0 .ns-scene-turbine path:nth-child(3n) { stroke: var(--scene-secondary); }
.ns-scene-0 .ns-scene-turbine path:nth-child(4n) { stroke: var(--scene-warm); }
.ns-scene-0 .ns-scene-core { fill: var(--scene-warm); stroke: var(--scene-warm); filter: drop-shadow(0 0 12px var(--scene-primary)); }
.ns-scene-archive .ns-module-charging .ns-archive-data { animation: ns-archive-restore 1s var(--charge-delay, .25s) both; }
.ns-scene-vault > circle:nth-child(3) { stroke: var(--scene-secondary); }
.ns-vault-bolt-released { transform: translateY(-18px); }
.ns-module-charging .ns-vault-bolt-released { animation: ns-bolt-release .7s .25s both; }
.ns-scene-core { fill: #f5f5f0; stroke: #fff; animation: ns-core-power 1.6s ease-out both; }
.ns-scene-turbine { transform-origin: 0 0; animation: ns-turbine-spin 2.4s linear infinite; }
.ns-scene-vault .ns-scene-turbine { stroke: var(--scene-secondary); animation: ns-vault-unlock 2.2s ease-out both; }
.ns-scene-archive .ns-scene-core, .ns-scene-vault .ns-scene-core, .ns-scene-radio .ns-scene-core { fill: var(--scene-warm); stroke: var(--scene-warm); filter: drop-shadow(0 0 10px var(--scene-primary)); }
.ns-scene-energy { stroke: var(--scene-primary, #f5f5f0); stroke-width: 2; stroke-dasharray: 4 13; transform-origin: 600px 290px; animation: ns-energy-burst 2.8s .15s ease-out both; }
.ns-scene-energy path { stroke-dasharray: 60 1100; animation: ns-energy-travel 1.6s .2s ease-out both; }
.ns-scene-glow { opacity: .2; animation: ns-glow-power 2.2s ease-out both; }
.ns-scene-radio-wave { stroke: var(--scene-secondary); stroke-linecap: round; opacity: .08; }
.ns-scene-radio-wave.ns-wave-online { animation: ns-radio-broadcast 1.8s var(--wave-delay) ease-out infinite; }
.ns-scene-points { fill: var(--scene-primary, #fff); }
.ns-scene-points circle:nth-child(3n) { fill: var(--scene-secondary, #fff); }
.ns-scene-points circle:nth-child(5n) { fill: var(--scene-warm, #fff); }
.ns-scene-points circle { opacity: 0; animation: ns-dust-arrive 3.6s var(--point-delay) ease-out both; }
.ns-scene-spark { position: absolute; top: 38%; left: 50%; width: 3px; height: 20px; background: var(--spark-color, #fff); opacity: 0; transform-origin: 50% 0; animation: ns-firework-release 2.3s var(--spark-delay) cubic-bezier(.1,.55,.2,1) both; }
.ns-scene-heading { position: absolute; inset: max(28px, env(safe-area-inset-top)) clamp(22px, 5vw, 76px) auto; display: flex; justify-content: space-between; gap: 18px; color: #c7c7c1; font-size: clamp(.78rem, 1.5vw, 1rem); }
.ns-scene-heading span:first-child { color: #fff; font-weight: 700; }
.ns-scene-heading span { padding: 3px 6px; background: #060606dc; }
.ns-scene-controls { position: absolute; top: max(66px, calc(env(safe-area-inset-top) + 46px)); right: clamp(22px, 5vw, 76px); display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.ns-scene-motion { min-height: 44px; padding: 8px 12px; border: 1px solid #696965; background: #060606e8; color: #ddd; font: inherit; font-size: .78rem; cursor: pointer; }
.ns-scene-motion:focus-visible { outline: 2px solid #fff; outline-offset: 4px; }
.ns-scene-copy { width: min(850px, 100%); text-align: center; padding: 22px 0 20px; animation: ns-copy-arrive .6s .22s both; }
.ns-scene-copy .ns-scene-signal { display: table; margin: 0 auto 9px; padding: 3px 10px; background: #060606e8; color: #bcbcb6; font-size: clamp(.9rem, 2vw, 1.15rem); }
.ns-scene-copy h2 { margin: 0; color: #f5f5f0; font-size: clamp(1.9rem, 5.6vw, 4.6rem); font-weight: 900; line-height: 1.04; letter-spacing: -.045em; text-wrap: balance; }
.ns-scene-copy p { margin: 14px auto 0; color: #bcbcb6; font-size: clamp(.85rem, 1.5vw, 1rem); max-width: 60ch; line-height: 1.5; }
.ns-scene-progress { display: flex; justify-content: center; gap: 7px; width: min(290px, 80%); margin: 22px auto 0; }
.ns-scene-progress span { flex: 1; height: 5px; background: #363634; }
.ns-scene-progress .ns-scene-step-online { background: var(--scene-primary, #f5f5f0); }
.ns-scene-continue { display: inline-flex; align-items: center; justify-content: center; gap: 30px; min-height: 46px; padding: 10px 20px; border: 1px solid #696965; background: #111; border-radius: 0; color: #f5f5f0; font: inherit; cursor: pointer; }
.ns-scene-continue span { color: #aaa; }
.ns-scene-continue:hover { background: #292927; border-color: #fff; }
.ns-scene-continue:focus-visible { outline: 2px solid #fff; outline-offset: 5px; }
.ns-scene-online .ns-scene-glow { animation-name: ns-glow-complete; }
.ns-scene-online .ns-scene-art { transform: translateY(-7vh); }
.ns-scene-online .ns-scene-energy { stroke-width: 3; }
.ns-scene-finale { background: #050814; }
.ns-scene-finale .ns-scene-art { transform: none; }
.ns-scene-finale .ns-scene-art::after { background: linear-gradient(transparent 38%, #05081470 65%, #050814 95%); }
.ns-scene-finale .ns-scene-copy h2 { color: #ffe8a5; }
.ns-finale-skyline { stroke: #587099; stroke-width: 1; fill-opacity: .3; opacity: .5; }
.ns-finale-network { stroke: var(--scene-secondary); stroke-width: 1.5; }
.ns-finale-network > path { stroke-dasharray: 6 9; animation: ns-network-connect 3s ease-out both; }
.ns-finale-network circle:first-child { fill: #101a2b; stroke: var(--node-color); filter: drop-shadow(0 0 12px var(--node-color)); }
.ns-finale-network circle:nth-child(2) { fill: var(--node-color); stroke: none; opacity: .2; }
.ns-finale-network g > path { stroke: var(--node-color); stroke-width: 2.5; }
.ns-firework { stroke: var(--firework-color); fill: var(--firework-color); }
.ns-firework-launch { fill: none; stroke-width: 2.5; stroke-dasharray: 10 90; opacity: 0; animation: ns-firework-launch .7s var(--launch-delay) ease-in both; }
.ns-firework-bloom { opacity: 0; transform-origin: 0 0; animation: ns-firework-bloom 1.8s var(--bloom-delay) cubic-bezier(.12,.55,.25,1) both; }
.ns-firework-bloom path { fill: none; stroke-width: 2.5; stroke-linecap: round; }
.ns-firework-bloom circle { stroke: none; filter: drop-shadow(0 0 5px var(--firework-color)); }
.ns-firework-bloom .ns-firework-inner { stroke: #fff4d5; stroke-width: 1; }
.ns-firework-halo { stroke: none; opacity: 0; transform-origin: 0 0; filter: blur(24px); animation: ns-firework-halo 1.5s var(--bloom-delay) ease-out both; }
.ns-cinematic-enter-active { transition: opacity .2s ease; }
.ns-cinematic-leave-active { transition: opacity .22s ease; }
.ns-cinematic-enter-from, .ns-cinematic-leave-to { opacity: 0; }
@keyframes ns-room-approach { from { opacity: .2; transform: scale(.8) translateY(35px); } to { opacity: 1; transform: scale(1.07) translateY(-15px); } }
@keyframes ns-module-charge { 0% { stroke: #373735; fill: #090909; opacity: .2; } 65% { stroke: #fff; fill: #3f3f3c; opacity: 1; } 100% { stroke: var(--rack-color, #eeeee7); fill: var(--module-fill, #161615); } }
@keyframes ns-turbine-spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
@keyframes ns-indicator-flow { 0%, 100% { opacity: .35; } 50% { opacity: 1; } }
@keyframes ns-vault-unlock { from { transform: rotate(-50deg); } to { transform: rotate(40deg); } }
@keyframes ns-archive-restore { from { transform: translateY(55px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes ns-bolt-release { from { transform: translateY(0); } to { transform: translateY(-18px); } }
@keyframes ns-core-power { from { opacity: .1; } to { opacity: 1; } }
@keyframes ns-energy-burst { 0% { transform: scale(var(--energy-before)); opacity: 0; } 18% { opacity: .8; } 100% { transform: scale(calc(var(--energy-after) * 2.5)); opacity: 0; } }
@keyframes ns-energy-travel { from { stroke-dashoffset: 1150; } to { stroke-dashoffset: 0; } }
@keyframes ns-radio-broadcast { 0%, 8% { opacity: 0; } 28%, 58% { opacity: 1; } 100% { opacity: 0; } }
@keyframes ns-glow-power { 0% { opacity: .1; } 35% { opacity: .7; } 100% { opacity: .35; } }
@keyframes ns-glow-complete { 0% { opacity: .2; } 40% { opacity: 1; } 100% { opacity: .7; } }
@keyframes ns-dust-arrive { 0% { opacity: 0; transform: translateY(30px); } 25%, 75% { opacity: .7; } 100% { opacity: 0; transform: translateY(-70px); } }
@keyframes ns-firework-release { 0% { transform: rotate(var(--spark-angle)) translateY(-20px) scaleY(.3); opacity: 0; } 12% { opacity: 1; } 100% { transform: rotate(var(--spark-angle)) translateY(calc(var(--spark-distance) * -1)) scaleY(.1); opacity: 0; } }
@keyframes ns-copy-arrive { from { transform: translateY(18px); opacity: 0; } to { transform: none; opacity: 1; } }
@keyframes ns-firework-launch { 0% { stroke-dashoffset: 10; opacity: 0; } 15%, 80% { opacity: .9; } 100% { stroke-dashoffset: -100; opacity: 0; } }
@keyframes ns-firework-bloom { 0% { transform: scale(.04); opacity: 0; } 7% { opacity: 1; } 42% { transform: scale(.85) translateY(3px); opacity: .95; } 100% { transform: scale(1.25) translateY(32px); opacity: 0; } }
@keyframes ns-firework-halo { 0% { transform: scale(.1); opacity: 0; } 15% { opacity: .18; } 100% { transform: scale(1.5); opacity: 0; } }
@keyframes ns-network-connect { from { stroke-dashoffset: 180; opacity: .1; } to { stroke-dashoffset: 0; opacity: 1; } }
@media (max-width: 600px) { .ns-scene-art svg { width: 170%; max-width: none; margin-left: -35%; } .ns-scene-copy { padding-bottom: 22px; } .ns-scene-copy h2 { font-size: clamp(1.8rem, 9vw, 2.9rem); } .ns-scene-heading { font-size: .78rem; } .ns-scene-spark { width: 2px; height: 12px; } }
@media (max-height: 520px) { .ns-scene-copy { padding: 0 0 12px; } .ns-scene-copy h2 { font-size: 1.8rem; } .ns-scene-progress { margin-top: 12px; } .ns-scene-copy p { margin-top: 7px; } }
.ns-scene-still, .ns-scene-still * { animation: none !important; transition: none !important; }
.ns-scene-still .ns-scene-energy, .ns-scene-still .ns-scene-spark, .ns-scene-still .ns-scene-points { display: none; }
.ns-scene-still .ns-scene-room { transform: none; opacity: 1; }
.ns-scene-still .ns-scene-glow { opacity: .35; }
.ns-scene-still .ns-scene-core, .ns-scene-still .ns-wave-online { opacity: 1; }
.ns-scene-still .ns-firework-launch { display: none; }
.ns-scene-still .ns-firework-bloom { opacity: .8; transform: scale(.8); }
.ns-scene-still .ns-firework-halo { opacity: .08; }
@media (max-width: 600px) { .ns-scene-finale .ns-scene-art svg { width: 100%; margin-left: 0; } }
</style>
