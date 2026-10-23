<template>
    <div class="ns-stack">
        <div v-if="mission.evidence" class="ns-evidence">
            <p v-for="line in mission.evidence" :key="line" class="ns-number">{{ line }}</p>
        </div>
        <div v-if="mission.role_cards" class="ns-panel ns-private">
            <h3>Deine persönliche Hinweiskarte</h3>
            <p v-for="clue in mission.role_cards" :key="clue">{{ clue }}</p>
            <p v-if="!mission.role_cards.length">Du hast bei dieser Aufgabe keinen eigenen Zahlenhinweis. Frage dein Team nach den Zahlen und rechne mit.</p>
            <p class="ns-small ns-muted" style="margin-top:10px">Dein Team sieht andere Zahlenhinweise. Lest sie euch vor und erklärt, wie ihr die Zahlen umrechnet.</p>
        </div>
        <div v-if="mission.tokens?.length" class="ns-token-row" aria-label="Empfangene Zeichencodes">
            <span v-for="(token, index) in mission.tokens" :key="index" class="ns-token">{{ token }}</span>
        </div>
        <p v-if="mission.note" class="ns-small ns-muted">{{ mission.note }}</p>
        <template v-for="field in mission.fields" :key="field.name">
            <fieldset v-if="field.widget === 'bits'" :disabled="disabled" :aria-describedby="field.help ? `${mission.id}-${field.name}-help` : undefined">
                <legend>{{ field.label }} · {{ field.width }} Schalter</legend>
                <p class="ns-small ns-muted">Klicke auf einen Schalter: 1 = ein, 0 = aus. Die Zahl darüber zeigt seinen Wert.</p>
                <div class="ns-bit-grid" :class="{ 'ns-bit-grid-wide': field.width > 8 }">
                    <button v-for="(bit, index) in bits(field)" :key="index" type="button" class="ns-bit"
                        :aria-label="`Stellenwert ${2 ** (field.width - index - 1)}`" :aria-pressed="bit === '1'"
                        :disabled="disabled" @click="toggleBit(field, index)">
                        <span>{{ 2 ** (field.width - index - 1) }}</span><strong>{{ bit }}</strong>
                    </button>
                </div>
                <p class="ns-small ns-muted" aria-live="polite">Summe der eingeschalteten Schalter: {{ bitSum(field) }}</p>
                <p class="ns-number">{{ bits(field).join('') }} (dual)</p>
            </fieldset>
            <fieldset v-else-if="field.widget === 'nibbles'" :disabled="disabled" :aria-describedby="field.help ? `${mission.id}-${field.name}-help` : undefined">
                <legend>{{ field.label }}</legend>
                <p class="ns-small ns-muted">Wähle pro Feld eine Hexziffer. Jede Hexziffer steht für 4 Bits.</p>
                <div class="ns-nibbles">
                    <label v-for="position in field.width" :key="position" class="ns-field ns-nibble">
                        <span>Stellenwert {{ 16 ** (field.width - position) }}</span>
                        <select :aria-label="`Hexziffer ${position}`" :value="hexDigit(field, position - 1)" :disabled="disabled"
                            @change="chooseHex(field, position - 1, $event.target.value)">
                            <option value="">–</option><option v-for="digit in hexDigits" :key="digit" :value="digit">{{ digit }}</option>
                        </select>
                    </label>
                </div>
                <p class="ns-number" style="margin-top:10px">#{{ String(modelValue[field.name] ?? '').replace(/^#/, '') || '…' }}</p>
            </fieldset>
            <fieldset v-else-if="field.widget === 'weights'" :disabled="disabled" :aria-describedby="field.help ? `${mission.id}-${field.name}-help` : undefined">
                <legend>{{ field.label }}</legend>
                <div class="ns-weights">
                    <label v-for="weight in weights(field.width)" :key="weight" class="ns-choice">
                        <input type="checkbox" :checked="(modelValue[field.name] ?? []).includes(weight)" :disabled="disabled"
                            @change="toggleWeight(field, weight)" />{{ weight }}
                    </label>
                </div>
            </fieldset>
            <fieldset v-else-if="field.widget === 'choice'" class="ns-stack" :disabled="disabled" :aria-describedby="field.help ? `${mission.id}-${field.name}-help` : undefined">
                <legend>{{ field.label }}</legend>
                <label v-for="option in field.options" :key="option.id" class="ns-choice">
                    <input type="radio" :name="`${mission.id}-${field.name}`" :value="option.id"
                        :checked="modelValue[field.name] === option.id" :disabled="disabled" @change="set(field.name, option.id)" />
                    <span>{{ option.label }}</span>
                </label>
            </fieldset>
            <label v-else class="ns-field">
                <span>{{ field.label }}</span>
                <input :id="`${mission.id}-${field.name}`" :value="modelValue[field.name] ?? ''" :placeholder="field.placeholder ?? (field.base === 16 ? 'z. B. #2A' : '')"
                    :inputmode="field.base === 10 ? 'numeric' : 'text'" :disabled="disabled" :aria-label="field.label"
                    :aria-describedby="field.help ? `${mission.id}-${field.name}-help` : undefined"
                    autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="500" @input="set(field.name, $event.target.value)" />
            </label>
            <p v-if="field.help" :id="`${mission.id}-${field.name}-help`" class="ns-small ns-field-help">{{ field.help }}</p>
        </template>
    </div>
</template>

<script setup>
const props = defineProps({ mission: { type: Object, required: true }, modelValue: { type: Object, required: true }, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const hexDigits = '0123456789ABCDEF'.split('')
function set(name, value) { emit('update:modelValue', { ...props.modelValue, [name]: value }) }
function bits(field) {
    const raw = String(props.modelValue[field.name] ?? '').replace(/\s*\(dual\)$/i, '').replace(/\s/g, '')
    return /^[01]+$/.test(raw) ? raw.padStart(field.width, '0').slice(-field.width).split('') : Array(field.width).fill('0')
}
function toggleBit(field, index) { const value = bits(field); value[index] = value[index] === '1' ? '0' : '1'; set(field.name, value.join('')) }
function bitSum(field) {
    const values = bits(field).map((bit, index) => bit === '1' ? 2 ** (field.width - index - 1) : 0).filter(Boolean)
    return values.length ? `${values.join(' + ')} = ${values.reduce((sum, value) => sum + value, 0)}` : 'keine · Summe 0'
}
function hexDigit(field, index) { const digit = String(props.modelValue[field.name] ?? '').replace(/^#/, '')[index]; return hexDigits.includes(digit) ? digit : '' }
function chooseHex(field, index, digit) {
    const values = Array.from({ length: field.width }, (_, offset) => hexDigit(field, offset) || '_')
    values[index] = digit || '_'; set(field.name, values.join(''))
}
function weights(width) { return Array.from({ length: width }, (_, index) => 2 ** (width - index - 1)) }
function toggleWeight(field, weight) {
    const values = props.modelValue[field.name] ?? []
    set(field.name, values.includes(weight) ? values.filter(value => value !== weight) : [...values, weight])
}
</script>
