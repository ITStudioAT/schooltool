<template>
    <article class="binary-explanation" :class="{ 'hexadecimal-explanation': hexadecimal }" aria-labelledby="binary-title">
        <header class="binary-heading"><div><p class="binary-eyebrow">ZAHLENSYSTEME · INTERAKTIV</p><h1 id="binary-title">{{ hexadecimal ? 'Dezimal auf Hexadezimal' : 'Dezimal auf Dual' }}</h1>
            <p>Eine Zahl. Immer durch {{ hexadecimal ? 'sechzehn' : 'zwei' }}. Die Reste verraten das Ergebnis.</p></div>
            <form class="binary-input" @submit.prevent="applyNumber">
                <label for="decimal-example">Deine Dezimalzahl</label>
                <div><input id="decimal-example" v-model="draft" inputmode="numeric" autocomplete="off" :aria-invalid="Boolean(inputError)" :aria-describedby="inputError ? 'decimal-error' : 'decimal-hint'" /><button type="submit">Zahl übernehmen</button></div>
                <small id="decimal-hint">Ganze Zahlen von 0 bis 65.535.</small>
                <p v-if="inputError" id="decimal-error" role="alert">{{ inputError }}</p>
            </form>
        </header>
        <div class="binary-controls" aria-label="Erklärung Schritt für Schritt steuern">
            <button type="button" :disabled="step === 0" @click="move(-1)"><span aria-hidden="true">←</span> Zurück</button>
            <button type="button" :disabled="step === maximumStep" @click="move(1)">Weiter <span aria-hidden="true">→</span></button>
            <button type="button" @click="restart">Neu starten</button>
        </div>
        <div v-if="step === maximumStep" class="binary-completion" role="status">
            <strong>Erklärung abgeschlossen.</strong>
            <p>Mit „Neu starten“ wiederholst du die Umrechnung. Oben kannst du eine andere Zahl eingeben.</p>
        </div>
        <div class="binary-progress"><progress :value="step + 1" :max="maximumStep + 1" aria-label="Fortschritt der Erklärung" /><span>Schritt {{ step + 1 }} von {{ maximumStep + 1 }}</span></div>
        <section v-if="step === readingStep" class="binary-phase-stop" aria-labelledby="binary-phase-title" role="status">
            <p>TEIL 1 ABGESCHLOSSEN</p>
            <h2 id="binary-phase-title">Das Teilen ist fertig!</h2>
            <ul><li>Der Quotient ist 0. Wir hören auf zu teilen.</li><li>Jetzt setzen wir aus den Resten die {{ hexadecimal ? 'Hexadezimalzahl' : 'Dualzahl' }} zusammen.</li></ul>
        </section>
        <section v-else class="binary-instruction" aria-live="polite" aria-atomic="true" :data-testid="hasInlineExplanation ? null : 'step-instruction'">
            <h2>{{ mainInstruction }}</h2>
            <ul v-if="!hasInlineExplanation && currentStep.points.length"><li v-for="point in currentStep.points" :key="point">{{ point }}</li></ul>
        </section>
        <section v-if="hasInlineExplanation" class="binary-instruction binary-inline-explanation" aria-live="polite" aria-atomic="true" data-testid="step-instruction">
            <h2>{{ currentStep.title }}</h2>
            <ul><li v-for="point in currentStep.points" :key="point">{{ point }}</li></ul>
        </section>
        <div class="binary-board" :class="{ 'binary-board--dividing': step <= readingStep }">
            <section class="binary-divisions" aria-labelledby="division-heading">
                <h2 id="division-heading"><span>1</span> Teilen und Reste notieren</h2>
                <div class="binary-calculation" aria-label="Rechenweg">
                    <div class="binary-column-labels" aria-hidden="true"><span>Rechnung</span><span>Rest</span></div>
                    <TransitionGroup name="division" tag="ol" class="binary-rows">
                        <li v-for="(row, index) in visibleRows" :key="`${number}-${index}`" class="binary-row" :class="{ 'binary-row--current': highlightedRow === index }">
                            <span class="binary-equation"><strong>{{ row.dividend }}</strong> <span>÷ {{ base }} =</span> <strong>{{ isCalculationVisible(index) ? row.quotient : '?' }}</strong></span>
                            <span v-if="assembling" class="binary-rest-arrow" :class="{ 'binary-rest-arrow--current': highlightedRow === index }" aria-hidden="true">↑</span>
                            <span class="binary-remainder" :class="{ 'binary-remainder--reading': highlightedRow === index && assembling }" :aria-label="isCalculationVisible(index) ? `Rest ${row.remainder}` : 'Rest noch ermitteln'">{{ isCalculationVisible(index) ? row.remainder : '?' }}<small v-if="hexadecimal && isCalculationVisible(index) && row.remainder > 9">{{ digit(row.remainder) }}</small></span>
                        </li>
                    </TransitionGroup>
                </div>
            </section>
            <section v-if="step > readingStep" class="binary-result" aria-labelledby="result-heading">
                <h2 id="result-heading"><span>2</span> Von unten nach oben lesen</h2>
                <div class="binary-reading-hint" :class="{ 'binary-reading-hint--active': assembling }"><span aria-hidden="true">↑</span> Reste von unten nach oben lesen</div>
                <div class="binary-digits" aria-label="Ergebnisziffern" :key="number">
                    <div v-for="(row, index) in reversedRows" :key="index" class="binary-digit" :class="{ 'binary-digit--known': index < revealedDigits, 'binary-digit--current': index === revealedDigits - 1 && assembling }">
                        <span class="binary-digit__place">{{ row.place }}</span>
                        <Transition name="digit" mode="out-in"><strong :key="index < revealedDigits ? 'known' : 'unknown'">{{ index < revealedDigits ? digit(row.remainder) : '?' }}</strong></Transition>
                    </div>
                </div>
                <div v-if="revealedDigits === rows.length" class="binary-identity" :data-testid="hexadecimal ? 'hexadecimal-result' : 'binary-result'">{{ number }} = {{ notation.prefix }}{{ binary }}<sub v-if="notation.subscript">{{ notation.subscript }}</sub></div>
                <Transition name="proof"><section v-if="step === maximumStep" class="binary-proof" aria-label="Probe mit Stellenwerten">
                    <h3>{{ hexadecimal ? 'Die Probe: Ziffer mal Stellenwert.' : 'Die Probe: Nur die Einsen zählen.' }}</h3><p>{{ proof }}</p><small>{{ number === 0 ? 'Alle Stellen sind 0. Das Ergebnis ist deshalb 0.' : hexadecimal ? 'Multipliziere jede Ziffer mit ihrem Stellenwert und addiere. A = 10, B = 11, C = 12, D = 13, E = 14, F = 15.' : 'Addiere die Stellenwerte, über denen eine 1 steht.' }}</small>
                </section></Transition>
            </section>
        </div>
        <p v-if="step === maximumStep" class="binary-takeaway"><strong>Merke:</strong> Teilen von oben nach unten. Reste lesen von unten nach oben.</p>
    </article>
</template>

<script setup>
import { computed, ref } from 'vue'
import { radixDivisionSteps, parseDecimalExample } from '@/helpers/decimalToBinaryExplanation'
import { numberSystemNotation } from '@/helpers/numberSystemNotation'

const props = defineProps({ base: { type: Number, default: 2, validator: value => [2, 16].includes(value) } })
const hexadecimal = computed(() => props.base === 16)
const notation = computed(() => numberSystemNotation(props.base))
const base = computed(() => props.base)
const number = ref(props.base === 16 ? 254 : 13)
const draft = ref(String(number.value))
const step = ref(0)
const inputError = ref('')
const rows = computed(() => radixDivisionSteps(number.value, props.base))
const reversedRows = computed(() => [...rows.value].reverse())
const steps = computed(() => {
    const divisions = rows.value.flatMap((row, rowIndex) => {
        const stages = [{ rowIndex, phase: 'divide', title: `Wir teilen ${row.dividend} durch ${props.base}.`, points: rowIndex ? [`Wir nehmen den Quotienten ${row.dividend} aus der vorherigen Zeile.`] : [] }]
        if (!rowIndex) {
            stages.push({ rowIndex, phase: 'why', title: `Warum teilen wir durch ${props.base}?`, points: hexadecimal.value ? ['Wir rechnen ins Hexadezimalsystem um.', 'Das Hexadezimalsystem hat die Basis 16.'] : ['Wir rechnen ins Dualsystem um.', 'Dual steht für zwei.'] })
        }
        stages.push({ rowIndex, phase: 'calculation', title: `${row.dividend} ÷ ${props.base} = ${row.quotient}, Rest ${row.remainder}`, points: [] })
        if (!rowIndex) {
            stages.push({ rowIndex, phase: 'dividend', title: `${row.dividend} wird geteilt.`, points: ['Die Zahl, die geteilt wird, nennt man Dividend.'] })
            stages.push({ rowIndex, phase: 'divisor', title: `Wir teilen durch ${props.base}.`, points: ['Die Zahl, durch die wir teilen, nennt man Divisor.', `Unser Divisor ist ${props.base}.`] })
            stages.push({ rowIndex, phase: 'quotient-name', title: `${row.quotient} ist das Ergebnis ohne Nachkommastellen.`, points: ['Dieses Ergebnis nennt man Quotient.'] })
            stages.push({ rowIndex, phase: 'remainder-name', title: `${row.remainder} bleibt übrig.`, points: ['Das Übriggebliebene heißt Rest.'] })
            stages.push({ rowIndex, phase: 'remainder-rule', title: hexadecimal.value ? 'Der Rest liegt zwischen 0 und 15.' : 'Der Rest ist immer 0 oder 1.', points: [`Beim Teilen durch ${props.base} muss der Rest kleiner als ${props.base} sein.`] })
        }
        if (hexadecimal.value && !rowIndex) {
            stages.push({ rowIndex, phase: 'hex-digits', title: 'Für 10 bis 15 verwenden wir Buchstaben.', points: ['Das Hexadezimalsystem hat 16 Ziffern: 0 bis 9 und A bis F.', '10 = A, 11 = B, 12 = C, 13 = D, 14 = E, 15 = F.', 'Ein Rest von 14 wird als eine einzige Ziffer E geschrieben.'] })
        }
        return stages
    })
    return [
        ...divisions,
        { phase: 'stop', title: '', points: [] },
        { phase: 'read-order', title: 'Reste von unten nach oben lesen.', points: [] },
        ...reversedRows.value.map((row, index) => ({ phase: 'digit', rowIndex: rows.value.length - index - 1,
            title: index ? 'Eine Zeile nach oben.' : 'Beginne ganz unten.',
            points: [`Rest ${row.remainder} kommt an die Stelle mit dem Wert ${row.place}.`, `Wir schreiben die ${index + 1}. Ziffer von links: ${digit(row.remainder)}.${hexadecimal.value && row.remainder > 9 ? ` ${digit(row.remainder)} steht für ${row.remainder}.` : ''}`] })),
        { phase: 'proof', title: `${number.value} im Dezimalsystem ist ${notation.value.prefix}${binary.value}${notation.value.unicodeSubscript} im ${hexadecimal.value ? 'Hexadezimalsystem' : 'Dualsystem'}.`, points: [hexadecimal.value ? 'Zur Probe multiplizieren wir jede Ziffer mit ihrem Stellenwert und addieren.' : 'Zur Probe addieren wir die Stellenwerte mit einer 1.'] },
    ]
})
const currentStep = computed(() => steps.value[step.value])
const hasInlineExplanation = computed(() => ['why', 'dividend', 'divisor', 'quotient-name', 'remainder-name', 'remainder-rule', 'hex-digits'].includes(currentStep.value.phase))
const mainInstruction = computed(() => {
    if (!hasInlineExplanation.value) return currentStep.value.title
    const row = rows.value[currentStep.value.rowIndex]
    return currentStep.value.phase === 'why' ? `Wir teilen ${row.dividend} durch ${props.base}.` : `${row.dividend} ÷ ${props.base} = ${row.quotient}, Rest ${row.remainder}`
})
const readingStep = computed(() => steps.value.findIndex(item => item.phase === 'stop'))
const maximumStep = computed(() => steps.value.length - 1)
const visibleRows = computed(() => rows.value.slice(0, step.value < readingStep.value ? currentStep.value.rowIndex + 1 : rows.value.length))
const revealedDigits = computed(() => steps.value.slice(0, step.value + 1).filter(item => item.phase === 'digit').length)
const assembling = computed(() => step.value > readingStep.value && step.value < maximumStep.value)
const highlightedRow = computed(() => assembling.value ? rows.value.length - Math.max(1, revealedDigits.value)
    : step.value < readingStep.value ? currentStep.value.rowIndex : -1)
const binary = computed(() => reversedRows.value.map(row => digit(row.remainder)).join(''))
const proof = computed(() => `${reversedRows.value.filter(row => row.remainder).map(row => hexadecimal.value ? `${row.remainder} × ${row.place}` : row.place).join(' + ') || '0'} = ${number.value}`)
function digit(remainder) { return remainder.toString(props.base).toUpperCase() }
function isCalculationVisible(index) {
    return step.value >= readingStep.value || index < currentStep.value.rowIndex || !['divide', 'why'].includes(currentStep.value.phase)
}
function move(direction) { step.value = Math.max(0, Math.min(maximumStep.value, step.value + direction)) }
function restart() { step.value = 0 }
function applyNumber() {
    const selected = parseDecimalExample(draft.value)
    if (selected === null) { inputError.value = 'Bitte eine ganze Zahl von 0 bis 65.535 eingeben.'; return }
    inputError.value = ''
    restart()
    number.value = selected
    draft.value = String(selected)
}
</script>

<style scoped>
.binary-explanation { --ink: #173458; --blue: #1b62c5; --line: #c8d5e5; color: var(--ink); max-width: 1180px; margin: 0 auto; }
.binary-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 28px; margin: 8px 0 22px; }
.binary-eyebrow { color: #5e748e; font-size: .7rem; letter-spacing: .13em; font-weight: 700; }
.binary-heading h1 { margin: 6px 0 8px; font-size: clamp(1.8rem, 3vw, 2.5rem); line-height: 1.15; letter-spacing: -.035em; }
.binary-heading > div > p:last-child { color: #50647c; max-width: 440px; line-height: 1.5; }
.binary-input { max-width: 390px; min-width: 280px; }
.binary-input label { display: block; font-size: .8rem; font-weight: 650; margin-bottom: 6px; }
.binary-input > div { display: flex; gap: 8px; }
.binary-input input { background: white; border: 1px solid #9fb2cc; border-radius: 6px; min-width: 0; width: 110px; padding: 10px 12px; font-size: 1.15rem; font-variant-numeric: tabular-nums; }
.binary-input small { display: block; margin-top: 6px; color: #50647c; }
.binary-input [role="alert"] { margin-top: 8px; color: #a62e30; font-size: .8rem; }
.binary-explanation button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid #aabbd0; border-radius: 6px; padding: 9px 13px; min-height: 44px; background: #fff; color: var(--ink); font-size: .85rem; font-weight: 650; cursor: pointer; }
.binary-explanation button:hover:not(:disabled) { background: #eaf1fb; border-color: var(--blue); }
.binary-explanation button:disabled { opacity: .45; cursor: default; }
.binary-explanation button:focus-visible, .binary-explanation input:focus-visible { outline: 3px solid var(--blue); outline-offset: 3px; }
.binary-controls { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.binary-controls button { padding: 12px 20px; font-size: 1.05rem; }
.binary-completion { margin-top: 16px; color: #137865; font-size: 1.1rem; line-height: 1.6; }
.binary-progress { display: flex; align-items: center; gap: 14px; margin-top: 18px; font-size: .75rem; color: #50647c; }
.binary-progress progress { width: min(300px, 55%); height: 5px; accent-color: var(--blue); }
.binary-instruction { min-height: 220px; margin: 24px 0; padding: clamp(20px, 3vw, 30px); border-left: 5px solid var(--blue); background: #eaf1fb; border-radius: 8px; }
.binary-instruction h2 { font-size: clamp(1.45rem, 3vw, 2rem); line-height: 1.35; }
.binary-instruction ul, .binary-phase-stop ul { margin-top: 18px; padding-left: 24px; font-size: clamp(1.1rem, 2vw, 1.3rem); line-height: 1.6; }
.binary-instruction li + li, .binary-phase-stop li + li { margin-top: 12px; }
.binary-phase-stop { margin: 22px 0; padding: clamp(20px, 3vw, 32px); border: 3px solid #b86a00; border-radius: 12px; background: #fff0cc; color: #643900; }
.binary-phase-stop > p:first-child { font-size: .8rem; font-weight: 800; letter-spacing: .08em; }
.binary-phase-stop h2 { margin: 8px 0 14px; font-size: clamp(1.8rem, 4vw, 2.6rem); font-weight: 800; line-height: 1.15; }
.binary-phase-stop p { max-width: 760px; margin-bottom: 10px; line-height: 1.6; }
.binary-board { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; background: #fff; }
.binary-board--dividing { grid-template-columns: 1fr; }
.binary-board--dividing .binary-calculation { min-height: 0; }
.binary-inline-explanation { border-left-color: #8b61b1; background: #f3edfb; }
.binary-divisions, .binary-result { min-width: 0; padding: clamp(16px, 2.4vw, 28px); }
.binary-result { background: #f5f8fc; border-left: 1px solid var(--line); }
.binary-board h2 { display: flex; align-items: center; gap: 10px; font-size: 1rem; line-height: 1.4; }
.binary-board h2 > span { display: grid; place-items: center; width: 26px; height: 26px; flex-shrink: 0; color: var(--blue); border: 1px solid #aac4e9; border-radius: 50%; font-size: .8rem; }
.binary-help { margin-top: 10px; color: #536880; font-size: .8rem; line-height: 1.5; }
.binary-calculation { max-width: 350px; margin: 24px auto 0; min-height: 242px; font-variant-numeric: tabular-nums; }
.binary-column-labels { display: flex; justify-content: space-between; padding: 0 14px 8px; color: #5e748e; font-size: .7rem; }
.binary-rows { display: grid; gap: 8px; padding: 0; list-style: none; }
.binary-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 10px; border-radius: 7px; border: 1px solid transparent; }
.binary-row--current { background: #eaf1fb; border-color: #b7cdec; }
.binary-equation { display: grid; grid-template-columns: 5ch 5ch 5ch; gap: 8px; align-items: baseline; text-align: right; font-size: clamp(1rem, 2vw, 1.3rem); }
.binary-equation > span { color: #61778f; font-size: .9rem; }
.binary-remainder { display: grid; place-items: center; width: 38px; height: 38px; flex-shrink: 0; background: #fff2cc; border: 1px solid #e1b74b; border-radius: 7px; color: #684a00; font-size: 1.3rem; font-weight: 750; }
.binary-remainder--reading { animation: remainder-pulse .65s ease-out; background: #f3cd64; }
.binary-rest-arrow { color: #61778f; font-size: 1.5rem; line-height: 1; }
.binary-rest-arrow--current { color: #137865; font-size: 2rem; font-weight: 900; }
.binary-reading-hint { display: flex; align-items: center; gap: 12px; margin: 26px 0 18px; color: #61778f; font-size: .8rem; }
.binary-reading-hint > span { font-size: 2rem; line-height: 1; }
.binary-reading-hint--active { padding: 18px; border: 2px solid #137865; border-radius: 8px; background: #ddf4eb; color: #075644; font-size: clamp(1.2rem, 2.5vw, 1.7rem); font-weight: 800; line-height: 1.35; }
.binary-reading-hint--active > span { font-size: 3rem; font-weight: 900; }
.binary-digits { display: flex; flex-wrap: wrap; gap: 7px; font-variant-numeric: tabular-nums; }
.binary-digit { width: 48px; text-align: center; }
.binary-digit__place { display: block; color: #61778f; font-size: .68rem; margin-bottom: 6px; }
.binary-digit strong { display: grid; place-items: center; height: 58px; border: 1px solid #c4d1e2; border-radius: 7px; background: #fff; color: #8e9db0; font-size: 1.9rem; }
.binary-digit--known strong { color: #137865; background: #e4f4ef; border-color: #8bbdaf; }
.binary-digit--current strong { background: #fff2cc; color: #684a00; border-color: #d4a733; }
.binary-place-caption { max-width: 380px; margin-top: 13px; font-size: .73rem; color: #61778f; line-height: 1.5; }
.binary-identity { margin: 24px 0 0; font-size: 1.8rem; font-weight: 750; color: #137865; overflow-wrap: anywhere; }
.binary-identity sub { font-size: .65rem; margin-left: 2px; }
.binary-proof { margin-top: 20px; padding-top: 18px; border-top: 1px solid #bddbce; }
.binary-proof h3 { font-size: .88rem; }
.binary-proof p { margin: 10px 0; color: #137865; font-size: 1.3rem; font-weight: 750; overflow-wrap: anywhere; }
.binary-proof small { color: #536880; }
.binary-takeaway { margin-top: 20px; padding-left: 14px; border-left: 3px solid #e1a631; font-size: .88rem; line-height: 1.6; }
.division-enter-active, .digit-enter-active, .proof-enter-active { transition: opacity .35s ease, transform .35s ease; }
.division-enter-from { opacity: 0; transform: translateY(-14px); }
.digit-enter-from { opacity: 0; transform: translate(-12px, 10px) scale(.7); }
.proof-enter-from { opacity: 0; transform: translateY(8px); }
@keyframes remainder-pulse { 50% { transform: scale(1.2) translateX(5px); } }
@media (max-width: 850px) { .binary-heading { flex-direction: column; gap: 18px; } .binary-input { width: 100%; max-width: 420px; } .binary-board { grid-template-columns: 1fr; } .binary-result { border-left: 0; border-top: 1px solid var(--line); } .binary-calculation { min-height: 180px; } }
@media (max-width: 420px) { .binary-equation { gap: 3px; grid-template-columns: 5ch 4ch 5ch; font-size: 1rem; } .binary-input { min-width: 0; } .binary-input button { padding: 8px; } .binary-digit { width: 43px; } }
@media (prefers-reduced-motion: reduce) { .division-enter-active, .digit-enter-active, .proof-enter-active { transition: none; } .binary-remainder--reading { animation: none; } }
.hexadecimal-explanation .binary-remainder { width: 48px; height: auto; min-height: 44px; }
.hexadecimal-explanation .binary-remainder small { display: block; font-size: .75rem; }
</style>
