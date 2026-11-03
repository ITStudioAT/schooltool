<template>
    <v-btn v-if="work?.id && workAutomationEnabled(work)" size="small" variant="tonal" prepend-icon="mdi-file-check-outline"
        :disabled="disabled || loading" @click.stop="openAction">{{ repeat ? 'Erneut bewerten' : 'Bewerten' }}</v-btn>
    <v-dialog v-if="opened" v-model="opened" max-width="720" scrollable :persistent="loading">
        <v-card>
            <v-card-title class="text-wrap">{{ repeat ? 'Arbeit erneut bewerten' : 'Arbeit bewerten' }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <p class="mb-3">Bewertungsauftrag kopieren und an die KI übergeben. Die Schaltfläche ändert noch keine Bewertungen.</p>
                <p v-if="loading" role="status">Bewertungsauftrag wird geladen …</p>
                <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
                <template v-if="result">
                    <v-alert v-if="result.context.operation.simulation" type="info" variant="tonal" class="mb-3">Simulation: Bewertet werden die eingesammelten Testabgaben. Auswertungen bleiben im lokalen Testbestand.</v-alert>
                    <details><summary>Bewertungsauftrag anzeigen</summary><textarea ref="copyText" :value="result.text" readonly rows="10" aria-label="KI-Auftrag zum Bewerten" class="grading-order-text" /></details>
                    <p v-if="message" role="status" class="mt-3" :class="copied ? 'text-success' : 'text-warning'">{{ message }}</p>
                </template>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn :disabled="!result || loading || copying || disabled" :color="copied ? 'success' : 'primary'"
                    :prepend-icon="copied ? 'mdi-check' : 'mdi-content-copy'" @click="copyOrder">{{ copied ? 'Kopiert' : 'Auftrag kopieren' }}</v-btn>
                <v-btn :disabled="loading" @click="opened = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { aiExport } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { workAutomationEnabled } from '@/helpers/workAutomation'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, repeat: Boolean, disabled: Boolean })
const opened = ref(false)
const loading = ref(false)
const copying = ref(false)
const copied = ref(false)
const result = ref(null)
const error = ref('')
const message = ref('')
const copyText = ref(null)
let sequence = 0
let controller = null

function clear() {
    sequence++
    controller?.abort()
    loading.value = false; copying.value = false; copied.value = false
    result.value = null; error.value = ''; message.value = ''
}

async function openAction() {
    if (props.disabled || !props.work?.id || !workAutomationEnabled(props.work)) return
    clear()
    opened.value = true
    loading.value = true
    const operation = sequence
    const mode = props.repeat ? 'repeat' : 'grade'
    controller = new AbortController()
    try {
        const response = await axios.get(aiExport.url(props.work.id), { params: { purpose: 'evaluation', evaluation_mode: mode,
            ...(props.date ? { date: props.date } : {}) }, signal: controller.signal })
        if (operation !== sequence || !opened.value) return
        const data = response.data?.data
        const simulation = data?.context?.simulation_source
        if (String(data?.work_id) !== String(props.work.id) || String(data?.course_id) !== String(props.work.teaching_course_id)
            || data.roster_complete !== true || data.context?.purpose !== 'grade-exercise' || data.context?.mode !== mode
            || data.context?.operation?.grade !== true || data.context?.operation?.preserve_existing !== true
            || data.context?.operation?.send !== false || data.context?.operation?.schooltool_import !== false
            || typeof data.text !== 'string' || !data.text.trim()
            || (props.work.simulation_collection && (data.context.operation.simulation !== true
                || simulation?.run_id !== props.work.simulation_collection.run_id))
            || (data.context.operation.simulation === true && (simulation?.simulation !== true
                || String(simulation.work_id) !== String(props.work.id) || String(simulation.course_id) !== String(props.work.teaching_course_id)))) {
            throw new Error('Der Bewertungsauftrag konnte nicht eindeutig dieser Arbeit und ihrem Abgabenbestand zugeordnet werden.')
        }
        result.value = data
    } catch (failure) {
        if (operation === sequence && !controller?.signal.aborted) error.value = failure.response?.data?.message
            || Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.message || 'Bewertungsauftrag konnte nicht geladen werden.'
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function copyOrder() {
    if (!result.value || loading.value || copying.value || props.disabled) return
    const operation = sequence
    copying.value = true; copied.value = false; message.value = ''
    try {
        await navigator.clipboard.writeText(result.value.text)
        if (operation === sequence) { copied.value = true; message.value = 'Bewertungsauftrag kopiert. Jetzt an die KI übergeben.' }
    } catch {
        if (operation !== sequence) return
        const details = copyText.value?.closest('details')
        if (details) details.open = true
        copyText.value?.focus(); copyText.value?.select()
        message.value = 'Automatisches Kopieren ist nicht verfügbar. Der Text ist markiert; bitte mit Strg+C kopieren.'
    } finally {
        if (operation === sequence) copying.value = false
    }
}

watch(() => [props.work?.id, props.work?.teaching_course_id, props.work?.updated_at, props.work?.simulation_collection?.run_id, props.date, props.repeat], () => { opened.value = false; clear() })
watch(opened, value => { if (!value) clear() })
onBeforeUnmount(clear)
</script>

<style scoped>
.grading-order-text { width: 100%; margin-top: 12px; padding: 10px; border: 1px solid #aaa; border-radius: 4px; font: 12px/1.5 monospace; white-space: pre-wrap; }
</style>
