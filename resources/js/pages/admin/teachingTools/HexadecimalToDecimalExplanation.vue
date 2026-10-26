<template>
    <article class="conversion" aria-labelledby="hex-decimal-title">
        <header class="heading">
            <div>
                <p class="eyebrow">ZAHLENSYSTEME · INTERAKTIV</p>
                <h1 id="hex-decimal-title">Hexadezimal auf Dezimal</h1>
                <p>Jede Ziffer hat einen Stellenwert. Die einzelnen Werte werden addiert.</p>
            </div>
            <form class="number-input" @submit.prevent="applyNumber">
                <label for="hex-decimal-input">Deine Hexadezimalzahl</label>
                <div><input id="hex-decimal-input" v-model="draft" autocomplete="off" spellcheck="false" :aria-invalid="Boolean(inputError)" :aria-describedby="inputError ? 'hex-decimal-error' : 'hex-decimal-hint'" /><button type="submit">Zahl übernehmen</button></div>
                <small id="hex-decimal-hint">1 bis 4 Stellen: 0–9 und A–F.</small>
                <p v-if="inputError" id="hex-decimal-error" role="alert">{{ inputError }}</p>
            </form>
        </header>

        <div class="controls" aria-label="Erklärung Schritt für Schritt steuern">
            <button type="button" :disabled="step === 0" @click="move(-1)"><span aria-hidden="true">←</span> Zurück</button>
            <button type="button" :disabled="step === maximumStep" @click="move(1)">Weiter <span aria-hidden="true">→</span></button>
            <button type="button" @click="step = 0">Neu starten</button>
        </div>
        <div class="progress"><progress :value="step + 1" :max="maximumStep + 1" aria-label="Fortschritt der Erklärung" /><span>Schritt {{ step + 1 }} von {{ maximumStep + 1 }}</span></div>

        <section class="instruction" aria-live="polite" aria-atomic="true">
            <template v-if="step === 0">
                <h2>So entstehen die Stellenwerte</h2>
                <p>Die hochgestellte Zahl zeigt, wie oft 16 als Faktor vorkommt: 16<sup>2</sup> = 16 × 16 = 256. Die rechte Stelle ist 16<sup>0</sup> = 1; nach links steigt der Exponent jeweils um 1.</p>
                <p>Die Buchstaben sind Ziffern: A = 10, B = 11, C = 12, D = 13, E = 14, F = 15.</p>
            </template>
            <template v-else-if="activePlace">
                <h2>Die Ziffer {{ activePlace.digit }} steht auf der {{ activePlace.place }}er-Stelle.</h2>
                <p>Der Stellenwert ist 16<sup>{{ activePlace.exponent }}</sup> = {{ activePlace.place }}.</p>
                <p v-if="activePlace.value >= 10">{{ activePlace.digit }} entspricht dem Wert {{ activePlace.value }}.</p>
                <p>{{ activePlace.value }} × {{ activePlace.place }} = <strong>{{ activePlace.contribution }}</strong></p>
            </template>
            <template v-else>
                <h2>Jetzt alle Werte addieren</h2>
                <p>{{ addition }} = <strong>{{ total }}</strong></p>
            </template>
        </section>

        <section class="board" aria-labelledby="places-title">
            <h2 id="places-title">Ziffern und Stellenwerte</h2>
            <p class="board-hint">Die rechte Stelle hat den Wert 1. Wir betrachten die Stellen von rechts nach links.</p>
            <div class="places" aria-label="Ziffern mit ihren Stellenwerten">
                <div v-for="(place, index) in places" :key="index" class="place" :class="{ 'place--active': activeIndex === index, 'place--done': isRevealed(index) }">
                    <span class="place-label">16<sup>{{ place.exponent }}</sup> = {{ place.place }}</span>
                    <strong class="place-digit">{{ place.digit }}</strong>
                    <span class="place-calculation">{{ isRevealed(index) ? `${place.value} × ${place.place} = ${place.contribution}` : 'noch offen' }}</span>
                </div>
            </div>
            <p v-if="step === maximumStep" class="result" role="status">#{{ number }} = {{ total }} im Dezimalsystem</p>
        </section>
    </article>
</template>

<script setup>
import { computed, ref } from 'vue'

const number = ref('1A3')
const draft = ref(number.value)
const step = ref(0)
const inputError = ref('')
const places = computed(() => [...number.value].map((digit, index) => {
    const value = Number.parseInt(digit, 16)
    const exponent = number.value.length - index - 1
    const place = 16 ** exponent
    return { digit, value, exponent, place, contribution: value * place }
}))
const maximumStep = computed(() => places.value.length + 1)
const activeIndex = computed(() => step.value > 0 && step.value < maximumStep.value ? places.value.length - step.value : -1)
const activePlace = computed(() => places.value[activeIndex.value] || null)
const total = computed(() => places.value.reduce((sum, place) => sum + place.contribution, 0))
const addition = computed(() => places.value.map(place => place.contribution).join(' + '))

function isRevealed(index) {
    return step.value === maximumStep.value || (step.value > 0 && index >= places.value.length - step.value)
}

function move(direction) {
    step.value = Math.max(0, Math.min(maximumStep.value, step.value + direction))
}

function applyNumber() {
    const selected = draft.value.trim().toUpperCase()
    if (!/^[0-9A-F]{1,4}$/.test(selected)) {
        inputError.value = 'Bitte 1 bis 4 Stellen mit den Ziffern 0–9 und A–F eingeben.'
        return
    }
    inputError.value = ''
    number.value = selected
    draft.value = selected
    step.value = 0
}
</script>

<style scoped>
.conversion { --ink: #173458; --blue: #1b62c5; color: var(--ink); max-width: 1180px; margin: 0 auto; }
.heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 28px; margin: 8px 0 18px; }
.eyebrow { color: #5e748e; font-size: .7rem; letter-spacing: .13em; font-weight: 700; }
.heading h1 { margin: 6px 0 8px; font-size: clamp(1.8rem, 3vw, 2.5rem); line-height: 1.15; letter-spacing: -.035em; }
.heading > div > p:last-child { color: #50647c; line-height: 1.5; }
.number-input { min-width: 280px; }
.number-input label { display: block; font-size: .8rem; font-weight: 650; margin-bottom: 6px; }
.number-input > div { display: flex; gap: 8px; }
.number-input input { width: 110px; min-width: 0; padding: 10px 12px; border: 1px solid #9fb2cc; border-radius: 6px; background: white; font-size: 1.15rem; }
.number-input small { display: block; margin-top: 6px; color: #50647c; }
.number-input [role="alert"] { margin-top: 8px; color: #a62e30; font-size: .8rem; }
.conversion button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 9px 13px; border: 1px solid #aabbd0; border-radius: 6px; background: #fff; color: var(--ink); font-size: .85rem; font-weight: 650; cursor: pointer; }
.conversion button:hover:not(:disabled) { background: #eaf1fb; border-color: var(--blue); }
.conversion button:disabled { opacity: .45; cursor: default; }
.conversion button:focus-visible, .conversion input:focus-visible { outline: 3px solid var(--blue); outline-offset: 3px; }
.controls { display: flex; flex-wrap: wrap; gap: 12px; }
.controls button { padding: 12px 20px; font-size: 1.05rem; }
.progress { display: flex; align-items: center; gap: 14px; margin-top: 14px; color: #50647c; font-size: .75rem; }
.progress progress { width: min(300px, 55%); height: 5px; accent-color: var(--blue); }
.instruction { margin: 18px 0; padding: 18px 24px; border-left: 5px solid var(--blue); border-radius: 8px; background: #eaf1fb; }
.instruction h2 { font-size: clamp(1.25rem, 2vw, 1.65rem); line-height: 1.35; }
.instruction p { margin-top: 8px; font-size: 1rem; line-height: 1.45; }
.board { padding: 18px 24px; border: 1px solid #c8d5e5; border-radius: 10px; background: white; }
.board h2 { font-size: 1rem; }
.board-hint { margin: 7px 0 14px; color: #536880; font-size: .85rem; }
.places { display: flex; flex-wrap: wrap; gap: 10px; font-variant-numeric: tabular-nums; }
.place { display: grid; gap: 6px; width: 145px; padding: 10px; border: 1px solid #c8d5e5; border-radius: 8px; background: #f5f8fc; text-align: center; }
.place--done { border-color: #8bbdaf; background: #e4f4ef; }
.place--active { border-color: #d4a733; background: #fff2cc; }
.place-label, .place-calculation { color: #536880; font-size: .76rem; }
.place-digit { font-size: 1.8rem; line-height: 1.1; }
.result { margin-top: 15px; color: #137865; font-size: 1.35rem; font-weight: 750; }
@media (max-width: 850px) { .heading { flex-direction: column; gap: 14px; } }
@media (max-width: 420px) { .number-input { min-width: 0; } .place { width: calc(50% - 5px); } }
</style>
