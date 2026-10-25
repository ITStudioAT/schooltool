<template>
    <v-btn variant="tonal" prepend-icon="mdi-email-outline" @click.stop="open">Benachrichtigung vorbereiten</v-btn>
    <v-dialog v-model="opened" persistent scrollable max-width="900">
        <v-card>
            <v-card-title class="text-wrap">Schüler:innen informieren · {{ work.title }}</v-card-title>
            <v-card-text>
                <v-progress-linear v-if="busy" indeterminate class="mb-3" />
                <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
                <v-alert v-if="success" type="success" variant="tonal" class="mb-3">{{ success }}</v-alert>
                <template v-if="result">
                    <v-alert :type="result.simulation ? 'warning' : 'info'" variant="tonal" class="mb-3">
                        {{ result.simulation ? 'Simulation: Alle Nachrichten bleiben im lokalen Mailpit.' : 'Die echten Nachrichten werden über den vorhandenen Office-/Outlook-Ablauf vorbereitet.' }}
                    </v-alert>
                    <v-btn v-if="result.simulation" variant="text" href="http://127.0.0.1:8025" target="_blank" rel="noopener noreferrer" prepend-icon="mdi-open-in-new" class="mb-3">Simulator-Mailpit öffnen</v-btn>
                    <p v-if="result.last_completed_at" class="mb-3"><strong>Letzter Abschluss:</strong> {{ assessmentTimeText(result.last_completed_at) }}</p>
                    <label v-if="result.simulation" class="d-flex align-center ga-2 mb-3"><input v-model="teacherPreview" type="checkbox" :disabled="busy" @change="dirty = true" />Nur eine Testvorschau an mich senden</label>
                    <p v-if="teacherPreview" class="mb-2">Genau eine persönliche Gestaltungsvorschau an die Lehrperson. Bereits informierte Schüler:innen erhalten keine weitere Nachricht.</p>
                    <p v-else class="mb-2">{{ selected.length }} Schüler:innen ausgewählt. Dazu kommt eine separate Nachricht an die Lehrperson. Jede Person erhält eine eigene E-Mail ohne Anhänge.</p>
                    <v-table density="compact" aria-label="Empfänger der Ergebnisbenachrichtigung">
                        <thead><tr><th>Auswahl</th><th>Nachname, Vorname</th><th>Status</th></tr></thead>
                        <tbody><tr v-for="row in sortedPeople(result.rows)" :key="row.student_id">
                            <td><input v-model="selected" type="checkbox" :value="row.student_id" :disabled="busy || teacherPreview || !row.eligible"
                                :aria-label="`${row.last_name}, ${row.first_name} informieren`" @change="dirty = true" /></td>
                            <td>{{ row.last_name }}, {{ row.first_name }}<div class="text-caption">{{ row.email || 'E-Mail-Adresse fehlt' }}</div></td>
                            <td>{{ row.status }}</td>
                        </tr></tbody>
                    </v-table>
                    <v-text-field v-model="subject" label="Betreff" :disabled="busy" class="mt-4" @update:model-value="dirty = true" />
                    <fieldset class="mb-3"><legend>Anredeform</legend>
                        <label class="mr-4"><input v-model="addressForm" type="radio" value="sie" :disabled="busy" @change="changeAddressForm" /> Sie-Form</label>
                        <label><input v-model="addressForm" type="radio" value="du" :disabled="busy" @change="changeAddressForm" /> Du-Form</label>
                    </fieldset>
                    <v-textarea v-model="body" label="Nachrichtentext" auto-grow :disabled="busy" rows="8" @update:model-value="dirty = true" />
                    <p class="text-caption">Die persönliche Anrede wird für jede Person eingesetzt. Offene Bewertungen, fehlende Übernahmen und bereits bestätigte Benachrichtigungen sind nicht auswählbar.</p>
                    <v-btn v-if="dirty" variant="outlined" class="mt-2" :disabled="busy" @click="loadPreview(false)">Vorschau aktualisieren</v-btn>
                    <template v-if="!dirty && result.messages.length">
                        <v-select v-model="example" :items="examples" label="Persönliche Vorschau" class="mt-3" :disabled="busy" />
                        <p><strong>{{ previewMessage?.subject }}</strong></p>
                        <pre class="notification-preview" aria-label="Persönliche Nachricht">{{ previewMessage?.text }}</pre>
                        <details class="mt-2"><summary>Gestaltung der E-Mail ansehen</summary>
                            <iframe :srcdoc="previewMessage?.html" sandbox="" title="E-Mail-Vorschau" class="notification-html" />
                        </details>
                    </template>
                    <details v-if="order" open class="mt-3"><summary>Office-Auftrag</summary><textarea ref="orderText" :value="order" readonly class="notification-order" aria-label="Office-Auftrag" /></details>
                </template>
            </v-card-text>
            <v-card-actions class="d-flex flex-wrap ga-2">
                <v-spacer />
                <v-btn v-if="result?.simulation" color="primary" :disabled="busy || dirty || (!teacherPreview && !selected.length)" @click="send">{{ teacherPreview ? 'Testvorschau an mich senden' : 'Testbenachrichtigungen senden' }}</v-btn>
                <v-btn v-else-if="result" color="primary" :disabled="busy || dirty || !selected.length" @click="copyOffice">Office-Auftrag kopieren</v-btn>
                <v-btn :disabled="busy" @click="opened = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { preview, send as sendNotification, officeOrder } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkResultNotificationController'
import { comparePersonNames, sortedPeople } from '@/helpers/personNames'
import { assessmentTimeText } from '@/helpers/workAssessmentTimes'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' } })
const emit = defineEmits(['saved'])
const opened = ref(false)
const busy = ref(false)
const result = ref(null)
const error = ref('')
const success = ref('')
const selected = ref([])
const teacherPreview = ref(false)
const subject = ref('')
const body = ref('')
const addressForm = ref('sie')
const dirty = ref(false)
const example = ref(0)
const order = ref('')
const orderText = ref(null)
let sequence = 0
const previewMessage = computed(() => result.value?.messages[example.value] || result.value?.messages[0])
const examples = computed(() => (result.value?.messages || []).map((message, index) => {
    const row = result.value.rows.find(row => row.student_id === message.student_id) || result.value.teacher
    return { first_name: row.first_name, last_name: row.last_name, title: `${row.last_name}, ${row.first_name}${message.student_id === null ? ' · Lehrperson' : ''}`, value: index }
}).sort(comparePersonNames))
const input = () => ({ student_ids: teacherPreview.value ? [] : selected.value, subject: subject.value, ...(body.value ? { body: body.value } : {}),
    ...(addressForm.value === 'du' ? { address_form: 'du' } : {}),
    ...(teacherPreview.value ? { teacher_preview: true, preview_id: result.value?.preview_id } : {}), ...(props.date ? { date: props.date } : {}) })
const failureText = failure => Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || failure.message || 'Die Benachrichtigung konnte nicht vorbereitet werden.'

async function open() {
    if (busy.value) return
    opened.value = true; result.value = null; subject.value = ''; body.value = ''; success.value = ''; order.value = ''
    teacherPreview.value = false
    addressForm.value = 'sie'
    await loadPreview(true)
}

async function loadPreview(reset) {
    if (busy.value) return
    busy.value = true; error.value = ''
    const operation = ++sequence
    try {
        const payload = reset ? { ...(subject.value ? { subject: subject.value, body: body.value } : {}), ...(teacherPreview.value ? { teacher_preview: true } : {}), ...(props.date ? { date: props.date } : {}) } : input()
        const response = await axios.post(preview.url(props.work.id), payload)
        if (operation !== sequence || !opened.value) return
        const data = response.data.data
        if (String(data.work_id) !== String(props.work.id) || String(data.course_id) !== String(props.work.teaching_course_id)) throw new Error('Die Vorschau gehört nicht zu dieser Arbeit.')
        result.value = data; selected.value = data.student_ids; teacherPreview.value = Boolean(data.teacher_preview); subject.value = data.subject; body.value = data.body; addressForm.value = data.address_form || addressForm.value; dirty.value = false; example.value = 0
    } catch (failure) { if (operation === sequence) error.value = failureText(failure) }
    finally { if (operation === sequence) busy.value = false }
}

function changeAddressForm() {
    if (body.value === result.value?.default_body) body.value = ''
    dirty.value = true
}

async function send() {
    if (busy.value || dirty.value || !result.value?.simulation || (!teacherPreview.value && !selected.value.length)) return
    busy.value = true; error.value = ''; success.value = ''
    const operation = ++sequence
    try {
        const response = await axios.post(sendNotification.url(props.work.id), { ...input(), hash: result.value.hash })
        if (operation !== sequence) return
        emit('saved', response.data.data)
        success.value = teacherPreview.value ? 'Die Gestaltungsvorschau an die Lehrperson ist in Mailpit bestätigt. Schülernachrichten wurden nicht wiederholt.'
            : 'Die Testbenachrichtigungen sind in Mailpit bestätigt und gespeichert. Es wurden keine echten E-Mails versandt.'
    } catch (failure) { if (operation === sequence) error.value = failureText(failure) }
    finally {
        if (operation === sequence) {
            busy.value = false
            const sendError = error.value
            await loadPreview(true)
            if (sendError) error.value = sendError + ' Bereits bestätigte Nachrichten werden nicht erneut gesendet.'
        }
    }
}

async function copyOffice() {
    if (busy.value || dirty.value || !selected.value.length) return
    busy.value = true; error.value = ''
    const operation = ++sequence
    try {
        const response = await axios.post(officeOrder.url(props.work.id), { ...input(), hash: result.value.hash })
        if (operation !== sequence || !opened.value) return
        order.value = response.data.text
        try { await navigator.clipboard.writeText(order.value); success.value = 'Office-Auftrag kopiert. An die KI übergeben; der Auftrag selbst versendet noch keine Nachrichten.' }
        catch { success.value = 'Bitte den angezeigten Office-Auftrag kopieren und an die KI übergeben.' }
    } catch (failure) { if (operation === sequence) error.value = failureText(failure) }
    finally { if (operation === sequence) busy.value = false }
}

watch(() => [props.work.id, props.work.teaching_course_id, props.date], () => { sequence++; opened.value = false; busy.value = false; result.value = null })
onBeforeUnmount(() => { sequence++ })
</script>

<style scoped>
.notification-preview { white-space: pre-wrap; overflow-wrap: anywhere; font: inherit; line-height: 1.6; padding: 16px; background: rgba(var(--v-theme-on-surface), .04); }
.notification-html { width: 100%; height: 430px; border: 1px solid #ddd; margin-top: 8px; background: white; }
.notification-order { width: 100%; min-height: 200px; font: 12px/1.5 monospace; }
</style>
