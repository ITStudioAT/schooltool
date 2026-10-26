<template>
    <article class="place-explanation" aria-labelledby="place-title">
        <header class="place-heading">
            <div>
                <p class="place-eyebrow">ZAHLENSYSTEME · INTERAKTIV</p>
                <h1 id="place-title">Dual auf Dezimal</h1>
                <p>Jede Stelle hat einen Wert. Addiere die Werte der Stellen mit einer 1.</p>
            </div>
            <form class="place-input" @submit.prevent="applyNumber">
                <label for="binary-number">Deine Dualzahl</label>
                <div><input id="binary-number" v-model="draft" inputmode="numeric" autocomplete="off" :aria-invalid="Boolean(inputError)" :aria-describedby="inputError ? 'binary-error' : 'binary-hint'" /><button type="submit">Zahl übernehmen</button></div>
                <small id="binary-hint">Nur 0 und 1, höchstens 16 Stellen.</small>
                <p v-if="inputError" id="binary-error" role="alert">{{ inputError }}</p>
            </form>
        </header>
        <div class="place-controls" aria-label="Erklärung Schritt für Schritt steuern">
            <button type="button" :disabled="step === 0" @click="move(-1)">← Zurück</button>
            <button type="button" :disabled="step === maximumStep" @click="move(1)">Weiter →</button>
            <button type="button" @click="restart">Neu starten</button>
        </div>
        <div class="place-progress"><progress :value="step + 1" :max="maximumStep + 1" aria-label="Fortschritt der Erklärung" /><span>Schritt {{ step + 1 }} von {{ maximumStep + 1 }}</span></div>
        <section class="place-instruction" aria-live="polite" aria-atomic="true">
            <h2>{{ instruction }}</h2>
            <p><template v-if="activePlace">Der Stellenwert ist 2<sup>{{ activePlace.exponent }}</sup> = {{ activePlace.value }}. </template>{{ explanation }}</p>
        </section>
        <section class="place-board" aria-labelledby="places-title">
            <h2 id="places-title">Die Stellenwerte</h2>
            <p class="place-hint">Rechts beginnt die Zählung bei 0: 2<sup>0</sup> = 1. Nach links steigt der Exponent um 1; der Stellenwert verdoppelt sich.</p>
            <div class="place-table" role="table" aria-label="Ziffern und ihre Stellenwerte">
                <div class="place-row" role="row"><span class="place-row-label" role="rowheader">Stellenwert</span><span v-for="place in places" :key="place.index" class="place-cell place-value" :class="{ 'place-cell--current': activeIndex === place.index }" role="cell"><span class="place-power">2<sup>{{ place.exponent }}</sup></span><span class="place-number">{{ place.value }}</span></span></div>
                <div class="place-row" role="row"><span class="place-row-label" role="rowheader">Ziffer</span><span v-for="place in places" :key="place.index" class="place-cell place-digit" :class="{ 'place-cell--current': activeIndex === place.index, 'place-cell--seen': place.index > activeIndex && step > 0 }" role="cell">{{ place.digit }}</span></div>
            </div>
            <div v-if="step > 0" class="place-sum" aria-live="polite">
                <strong>{{ step === maximumStep ? 'Ergebnis' : 'Bisher' }}</strong>
                <p>{{ selectedValues.length ? selectedValues.join(' + ') : '0' }} = {{ sum }}</p>
                <p v-if="step === maximumStep" class="place-result">{{ number }}<sub>2</sub> = {{ sum }}</p>
            </div>
        </section>
    </article>
</template>

<script setup>
import { computed, ref } from 'vue'

const number = ref('1101')
const draft = ref(number.value)
const inputError = ref('')
const step = ref(0)
const places = computed(() => [...number.value].map((digit, index) => ({
    index, digit, exponent: number.value.length - index - 1, value: 2 ** (number.value.length - index - 1),
})))
const maximumStep = computed(() => places.value.length + 1)
const activeIndex = computed(() => step.value > 0 && step.value <= places.value.length ? places.value.length - step.value : -1)
const activePlace = computed(() => places.value[activeIndex.value])
const selectedValues = computed(() => places.value.slice(Math.max(0, places.value.length - Math.min(step.value, places.value.length)))
    .filter(place => place.digit === '1').map(place => place.value))
const sum = computed(() => selectedValues.value.reduce((total, value) => total + value, 0))
const instruction = computed(() => {
    if (step.value === 0) return 'Jede Stelle hat einen Wert.'
    if (step.value === maximumStep.value) return 'Die Summe ist die Dezimalzahl.'
    return `Die ${activePlace.value.digit} steht an der Stelle ${activePlace.value.value}.`
})
const explanation = computed(() => {
    if (step.value === 0) return 'Die hochgestellte Zahl heißt Exponent. Sie gibt an, wie oft die 2 als Faktor vorkommt. 2 hoch 0 ist 1.'
    if (step.value === maximumStep.value) return 'Addiere die Stellenwerte, über denen eine 1 steht.'
    return activePlace.value.digit === '1'
        ? `Die 1 bedeutet: ${activePlace.value.value} wird zur Summe hinzugefügt.`
        : `Die 0 bedeutet: Der Stellenwert ${activePlace.value.value} wird nicht mitgezählt.`
})
function move(direction) { step.value = Math.max(0, Math.min(maximumStep.value, step.value + direction)) }
function restart() { step.value = 0 }
function applyNumber() {
    const value = draft.value.trim()
    if (!/^[01]{1,16}$/.test(value)) {
        inputError.value = 'Bitte eine Dualzahl mit 1 bis 16 Ziffern eingeben. Erlaubt sind nur 0 und 1.'
        return
    }
    inputError.value = ''
    number.value = value
    draft.value = value
    restart()
}
</script>

<style scoped>
.place-explanation { --ink: #173458; --blue: #1b62c5; color: var(--ink); max-width: 1180px; margin: 0 auto; }
.place-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 28px; margin: 8px 0 20px; }
.place-heading h1 { margin: 6px 0 8px; font-size: clamp(1.8rem, 3vw, 2.5rem); line-height: 1.15; letter-spacing: -.035em; }
.place-heading > div > p:last-child, .place-hint { color: #50647c; line-height: 1.5; }
.place-eyebrow { color: #5e748e; font-size: .7rem; letter-spacing: .13em; font-weight: 700; }
.place-input { min-width: 280px; }
.place-input label { display: block; font-size: .8rem; font-weight: 650; margin-bottom: 6px; }
.place-input > div { display: flex; gap: 8px; }
.place-input input { width: 180px; min-width: 0; padding: 10px 12px; border: 1px solid #9fb2cc; border-radius: 6px; background: white; font-size: 1.15rem; font-variant-numeric: tabular-nums; }
.place-input small { display: block; margin-top: 6px; color: #50647c; }
.place-input [role="alert"] { margin-top: 8px; color: #a62e30; font-size: .8rem; }
.place-explanation button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 9px 13px; border: 1px solid #aabbd0; border-radius: 6px; background: white; color: var(--ink); font-size: .85rem; font-weight: 650; cursor: pointer; }
.place-explanation button:hover:not(:disabled) { background: #eaf1fb; border-color: var(--blue); }
.place-explanation button:disabled { opacity: .45; cursor: default; }
.place-explanation button:focus-visible, .place-explanation input:focus-visible { outline: 3px solid var(--blue); outline-offset: 3px; }
.place-controls { display: flex; flex-wrap: wrap; gap: 12px; }
.place-controls button { padding: 12px 20px; font-size: 1.05rem; }
.place-progress { display: flex; align-items: center; gap: 14px; margin-top: 16px; color: #50647c; font-size: .75rem; }
.place-progress progress { width: min(300px, 55%); height: 5px; accent-color: var(--blue); }
.place-instruction { margin: 18px 0; padding: 18px 22px; border-left: 5px solid var(--blue); border-radius: 8px; background: #eaf1fb; }
.place-instruction h2 { font-size: clamp(1.3rem, 2.4vw, 1.7rem); line-height: 1.3; }
.place-instruction p { margin-top: 6px; font-size: 1rem; line-height: 1.5; }
.place-board { padding: 18px 22px; border: 1px solid #c8d5e5; border-radius: 10px; background: white; }
.place-board h2 { font-size: 1rem; }
.place-hint { margin: 5px 0 14px; font-size: .8rem; }
.place-table { overflow-x: auto; max-width: 100%; }
.place-row { display: flex; gap: 6px; width: max-content; margin: 6px 0; }
.place-row-label { width: 90px; flex-shrink: 0; align-self: center; color: #536880; font-size: .8rem; }
.place-cell { display: grid; place-items: center; width: 58px; min-height: 38px; border: 1px solid #d5e0ed; border-radius: 6px; background: #f5f8fc; font-variant-numeric: tabular-nums; }
.place-power { color: #50647c; font-size: .75rem; }
.place-number { font-weight: 650; }
.place-digit { font-size: 1.35rem; font-weight: 750; }
.place-cell--seen { background: #e4f4ef; border-color: #8bbdaf; }
.place-cell--current { border-color: #d4a733; background: #fff2cc; color: #684a00; }
.place-sum { margin-top: 15px; color: #137865; }
.place-sum p { margin-top: 4px; font-size: 1.2rem; font-weight: 700; overflow-wrap: anywhere; }
.place-result { margin-top: 8px !important; font-size: 1.5rem !important; }
.place-result sub { margin-left: 2px; font-size: .65rem; }
@media (max-width: 850px) { .place-heading { flex-direction: column; gap: 14px; } .place-input { min-width: 0; width: 100%; } }
</style>
