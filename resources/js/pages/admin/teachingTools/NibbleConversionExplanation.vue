<template>
    <article class="grouping-explanation" aria-labelledby="grouping-title">
        <header class="grouping-heading">
            <div><p class="grouping-eyebrow">ZAHLENSYSTEME · INTERAKTIV</p><h1 id="grouping-title">{{ toHex ? 'Dual auf Hexadezimal' : 'Hexadezimal auf Dual' }}</h1><p>Vier Bits gehören zusammen. Eine Vierergruppe entspricht einer Hexziffer.</p></div>
            <form class="grouping-input" @submit.prevent="applyInput">
                <label for="grouping-example">{{ toHex ? 'Deine Dualzahl' : 'Deine Hexadezimalzahl' }}</label>
                <div><input id="grouping-example" v-model="draft" autocomplete="off" spellcheck="false" :inputmode="toHex ? 'numeric' : 'text'" :aria-invalid="Boolean(error)" aria-describedby="grouping-input-help" /><button type="submit">Zahl übernehmen</button></div>
                <small id="grouping-input-help">{{ toHex ? '1 bis 16 Bits (0 und 1). Leerzeichen sind erlaubt.' : '1 bis 4 Hexziffern (0–9 und A–F), mit oder ohne #.' }}</small>
                <p v-if="error" role="alert">{{ error }}</p>
            </form>
        </header>
        <div class="grouping-controls" aria-label="Erklärung Schritt für Schritt steuern">
            <button type="button" :disabled="step === 0" @click="step--">← Zurück</button>
            <button type="button" :disabled="complete" @click="step++">Weiter →</button>
            <button type="button" @click="step = 0">Neu starten</button>
        </div>
        <div v-if="complete" class="grouping-completion" role="status"><strong>Erklärung abgeschlossen.</strong><p>Mit „Neu starten“ wiederholst du die Umrechnung. Oben kannst du eine andere Zahl eingeben.</p></div>
        <div class="grouping-progress"><progress :value="step + 1" :max="steps.length" aria-label="Fortschritt der Erklärung" /><span>Schritt {{ step + 1 }} von {{ steps.length }}</span></div>
        <section class="grouping-instruction" aria-live="polite" aria-atomic="true" data-testid="grouping-instruction">
            <h2>{{ current.title }}</h2><ul v-if="current.points.length"><li v-for="point in current.points" :key="point">{{ point }}</li></ul>
        </section>
        <section class="grouping-board" aria-label="Vierergruppierung">
            <div class="grouping-source"><span>Ausgangszahl</span><strong>{{ sourceNotation.prefix }}{{ conversion.source }}<sub v-if="sourceNotation.subscript">{{ sourceNotation.subscript }}</sub></strong></div>
            <div v-if="step > 0" class="grouping-groups">
                <div v-for="(group, index) in conversion.groups" :key="index" class="grouping-group" :class="{ 'grouping-group--active': current.groupIndex === index || (toHex && current.phase === 'groups' && index === conversion.groups.length - 1) }">
                    <span class="grouping-group-label">{{ index + 1 }}. Gruppe</span>
                    <template v-if="toHex">
                        <strong class="grouping-bits"><span v-if="paddingVisible && index === 0 && conversion.padding" class="grouping-padding">{{ '0'.repeat(conversion.padding) }}</span>{{ index === 0 ? group.original : group.binary }}<sub>2</sub></strong>
                        <span class="grouping-arrow" aria-hidden="true">↓</span><strong class="grouping-hex">{{ index < knownGroups ? '#' + group.hex : '?' }}</strong>
                    </template>
                    <template v-else>
                        <strong class="grouping-hex">#{{ group.hex }}</strong><span class="grouping-arrow" aria-hidden="true">↓</span><strong class="grouping-bits">{{ index < knownGroups ? group.binary : '????' }}<sub v-if="index < knownGroups">2</sub></strong>
                    </template>
                </div>
            </div>
            <p v-if="paddingVisible && toHex && conversion.padding" class="grouping-padding-note">{{ conversion.padding }} ergänzte {{ conversion.padding === 1 ? 'Null' : 'Nullen' }} links: gelb markiert. Der Zahlwert bleibt gleich.</p>
            <div v-if="complete" class="grouping-result" data-testid="grouping-result">{{ sourceNotation.prefix }}{{ conversion.source }}<sub v-if="sourceNotation.subscript">{{ sourceNotation.subscript }}</sub> = {{ targetNotation.prefix }}{{ toHex ? conversion.hex : conversion.groups.map(group => group.binary).join(' ') }}<sub v-if="targetNotation.subscript">{{ targetNotation.subscript }}</sub></div>
        </section>
        <section v-if="step > 0" class="grouping-reference" aria-label="Zuordnung der Vierergruppen">
            <h2>Vier Bits ↔ eine Hexziffer</h2>
            <div><span v-for="item in nibbleReference" :key="item.hex" :class="{ 'grouping-reference--active': current.groupIndex !== undefined && conversion.groups[current.groupIndex]?.hex === item.hex }">{{ item.binary }}<sub>2</sub> = #{{ item.hex }}</span></div>
        </section>
    </article>
</template>

<script setup>
import { computed, ref } from 'vue'
import { nibbleConversion, nibbleReference } from '@/helpers/nibbleConversionExplanation'
import { numberSystemNotation } from '@/helpers/numberSystemNotation'

const props = defineProps({ sourceBase: { type: Number, default: 2, validator: value => [2, 16].includes(value) } })
const toHex = computed(() => props.sourceBase === 2)
const draft = ref(props.sourceBase === 2 ? '110101' : '#AF')
const conversion = ref(nibbleConversion(draft.value, props.sourceBase))
const error = ref('')
const step = ref(0)
const sourceNotation = computed(() => numberSystemNotation(props.sourceBase))
const targetNotation = computed(() => numberSystemNotation(toHex.value ? 16 : 2))
const steps = computed(() => [
    { phase: 'start', title: toHex.value ? 'Wir beginnen ganz rechts.' : 'Jede Hexziffer wird zu genau vier Bits.', points: [toHex.value ? 'Eine Hexziffer ersetzt genau vier Bits. Deshalb bilden wir Vierergruppen von rechts nach links.' : 'Wir gehen die Hexziffern von links nach rechts durch. Für jede Ziffer schreiben wir eine Vierergruppe.'] },
    ...(toHex.value ? [
        { phase: 'groups', title: 'Teile die Bits von rechts in Vierergruppen.', points: ['Die Gruppe ganz links kann zunächst weniger als vier Bits enthalten. Die Reihenfolge der Bits bleibt gleich.'] },
        { phase: 'padding', title: conversion.value.padding ? `Ergänze links ${conversion.value.padding} ${conversion.value.padding === 1 ? 'Null' : 'Nullen'}.` : 'Alle Gruppen haben bereits vier Bits.', points: ['Führende Nullen verändern den Zahlwert nicht. Jetzt hat jede Gruppe genau vier Bits.'] },
    ] : [{ phase: 'groups', title: 'Nutze die Zuordnungstabelle.', points: ['Zum Beispiel: #A entspricht 1010₂, #F entspricht 1111₂. Auch #0 bekommt vier Bits: 0000₂.'] }]),
    ...conversion.value.groups.map((group, groupIndex) => ({ phase: 'convert', groupIndex,
        title: toHex.value ? `${group.binary}₂ wird zu #${group.hex}.` : `#${group.hex} wird zu ${group.binary}₂.`,
        points: [`${group.binary}₂ hat den Dezimalwert ${group.value}. ${group.value > 9 ? `Die Hexziffer ${group.hex} steht für ${group.value}.` : `Die Hexziffer ist ${group.hex}.`}`, toHex.value ? 'Lies die fertigen Hexziffern von links nach rechts.' : 'Schreibe alle vier Bits auf, auch führende Nullen.'] })),
    { phase: 'result', title: toHex.value ? 'Setze die Hexziffern zusammen.' : 'Setze die Vierergruppen zusammen.', points: [toHex.value ? 'Die Hexadezimalzahl bekommt vorne eine Raute #.' : 'Die Gruppen bleiben von links nach rechts in derselben Reihenfolge. Leerzeichen trennen die Vierergruppen zur besseren Lesbarkeit.'] },
])
const current = computed(() => steps.value[step.value])
const complete = computed(() => step.value === steps.value.length - 1)
const paddingVisible = computed(() => ['padding', 'convert', 'result'].includes(current.value.phase))
const knownGroups = computed(() => steps.value.slice(0, step.value + 1).filter(item => item.phase === 'convert').length)
function applyInput() {
    const result = nibbleConversion(draft.value, props.sourceBase)
    if (!result) { error.value = toHex.value ? 'Bitte 1 bis 16 Bits aus 0 und 1 eingeben.' : 'Bitte 1 bis 4 Hexziffern aus 0–9 und A–F eingeben, mit oder ohne #.'; return }
    error.value = ''
    step.value = 0
    conversion.value = result
    draft.value = sourceNotation.value.prefix + result.source
}
</script>

<style scoped>
.grouping-explanation { max-width: 1180px; margin: 0 auto; color: #173458; }
.grouping-heading { display: flex; justify-content: space-between; gap: 28px; margin: 8px 0 24px; }
.grouping-heading h1 { margin: 6px 0 10px; font-size: clamp(1.8rem, 3vw, 2.5rem); line-height: 1.2; letter-spacing: -.035em; }
.grouping-heading p { max-width: 52ch; line-height: 1.6; color: #526780; }
.grouping-heading .grouping-eyebrow { font-size: .7rem; letter-spacing: .13em; font-weight: 700; }
.grouping-input { flex: 0 1 390px; min-width: 280px; }
.grouping-input label { display: block; font-size: .85rem; font-weight: 650; margin-bottom: 7px; }
.grouping-input > div { display: flex; gap: 8px; }
.grouping-input input { width: 100%; min-width: 0; padding: 10px; border: 1px solid #aabbd0; border-radius: 6px; background: white; font-variant-numeric: tabular-nums; }
.grouping-input small { display: block; margin-top: 7px; color: #526780; }
.grouping-input [role="alert"] { color: #a62e30; }
.grouping-explanation button { min-height: 44px; padding: 10px 16px; border: 1px solid #aabbd0; border-radius: 6px; background: white; color: #173458; font-weight: 650; white-space: nowrap; }
.grouping-explanation button:disabled { opacity: .45; }
.grouping-explanation button:focus-visible, .grouping-input input:focus-visible { outline: 3px solid #1b62c5; outline-offset: 3px; }
.grouping-controls { display: flex; flex-wrap: wrap; gap: 12px; }
.grouping-controls button { font-size: 1.05rem; padding: 12px 20px; }
.grouping-completion { margin-top: 16px; color: #137865; line-height: 1.6; }
.grouping-progress { display: flex; gap: 14px; align-items: center; margin-top: 18px; font-size: .75rem; color: #526780; }
.grouping-progress progress { width: min(300px, 55%); height: 5px; accent-color: #1b62c5; }
.grouping-instruction { min-height: 190px; margin: 24px 0; padding: clamp(20px, 3vw, 30px); border-left: 5px solid #1b62c5; border-radius: 8px; background: #eaf1fb; }
.grouping-instruction h2 { font-size: clamp(1.4rem, 3vw, 2rem); line-height: 1.35; }
.grouping-instruction ul { margin-top: 18px; padding-left: 24px; font-size: 1.2rem; line-height: 1.6; }
.grouping-instruction li + li { margin-top: 10px; }
.grouping-board { padding: clamp(16px, 3vw, 28px); border: 1px solid #c8d5e5; border-radius: 10px; background: white; }
.grouping-source { display: flex; flex-wrap: wrap; align-items: baseline; gap: 14px; margin-bottom: 24px; }
.grouping-source > span { font-size: .85rem; color: #526780; }
.grouping-source strong, .grouping-result { font-size: clamp(1.15rem, 3vw, 1.8rem); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.grouping-groups { display: flex; flex-wrap: wrap; gap: 14px; }
.grouping-group { display: grid; justify-items: center; gap: 10px; min-width: 140px; padding: 18px; border: 1px solid #c8d5e5; border-radius: 10px; background: #f5f8fc; }
.grouping-group--active { border-color: #d4a733; background: #fff8e4; box-shadow: inset 0 0 0 1px #d4a733; }
.grouping-group-label { color: #526780; font-size: .75rem; }
.grouping-bits, .grouping-hex { font-size: 1.5rem; font-variant-numeric: tabular-nums; }
.grouping-padding { background: #f3cd64; border-radius: 3px; }
.grouping-padding-note { margin-top: 16px; font-size: .9rem; color: #70551a; }
.grouping-arrow { color: #7f96ae; }
.grouping-result { margin-top: 24px; color: #137865; font-weight: 750; }
.grouping-explanation sub { font-size: .65em; }
.grouping-reference { margin-top: 24px; }
.grouping-reference h2 { font-size: 1rem; margin-bottom: 12px; }
.grouping-reference > div { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.grouping-reference > div > span { padding: 8px; border: 1px solid #d6e0eb; border-radius: 6px; background: white; text-align: center; font-variant-numeric: tabular-nums; }
.grouping-reference .grouping-reference--active { background: #e4f4ef; border-color: #8bbdaf; color: #137865; font-weight: 700; }
@media (max-width: 850px) { .grouping-heading { flex-direction: column; } .grouping-input { flex-basis: auto; max-width: 420px; min-width: 0; } }
@media (max-width: 480px) { .grouping-reference > div { grid-template-columns: repeat(2, minmax(0, 1fr)); } .grouping-group { min-width: 120px; padding: 12px; } }
</style>
