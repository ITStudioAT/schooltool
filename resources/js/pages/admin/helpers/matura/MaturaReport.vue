<template>
    <section class="zero-panel">
        <div class="zero-section-title"><div><span class="zero-eyebrow">NACHVOLLZIEHBAR DOKUMENTIERT</span><h2>Protokoll & Auswertung</h2></div><a class="zero-button" :href="pdfUrl" target="_blank" rel="noopener">PDF herunterladen</a></div>
        <div class="zero-form-grid my-5">
            <label>Raum<select v-model="filters.room_id"><option value="">Alle Räume</option><option v-for="room in state.rooms" :key="room.id" :value="room.id">{{ room.name }}</option></select></label>
            <label>Schüler<select v-model="filters.student_id"><option value="">Alle Schüler</option><option v-for="student in students" :key="student.id" :value="student.id">{{ student.name }}</option></select></label>
            <label>Status<select v-model="filters.status"><option value="">Alle Status</option><option v-for="(label, status) in statusLabels" :key="status" :value="status">{{ label }}</option></select></label>
        </div>
        <p v-if="error" class="zero-error" role="alert">{{ error }}</p>
        <p v-if="loading" role="status">Auswertung wird geladen …</p>
        <template v-if="report">
            <div class="zero-report-summary"><strong>{{ report.summary.actual_visits }} tatsächliche Toilettengänge</strong><span>{{ report.summary.open }} offen</span><span>{{ report.summary.cancelled }} storniert / Fehlbuchung</span><span>{{ duration(report.summary.toilet_seconds) }} min:sek Toilettenzeit</span></div>
            <p class="zero-help my-3">Ein Gang zählt ab bestätigtem Toiletteneintritt. „—“ bedeutet: noch kein bestätigter Zeitpunkt oder keine abgeschlossene Dauer. Zeiten: Europe/Vienna.</p>
            <div class="zero-table-scroll"><table class="zero-table"><thead><tr><th>Schüler / Raum</th><th>Status</th><th>Anforderung / Freigabe</th><th>Abgang / Ankunft</th><th>Toilette ein / aus</th><th>Rückkehr</th><th>Dauer Toilette / Abwesenheit</th><th>Warten Raum / Station</th><th></th></tr></thead><tbody>
                <tr v-for="row in report.rows" :key="row.id"><td><strong>{{ row.student_name }}</strong><small>{{ row.class_name }} · {{ row.room_name }}</small></td><td>{{ row.status_label }}<small v-if="row.correction_reason">{{ row.correction_reason }}</small></td><td>{{ time(row.requested_at) }}<small>{{ time(row.approved_at) }}</small></td><td>{{ time(row.departed_at) }}<small>{{ time(row.arrived_at) }}</small></td><td>{{ time(row.entered_at) }}<small>{{ time(row.exited_at) }}</small></td><td>{{ time(row.returned_at) }}</td><td>{{ duration(row.toilet_seconds) }}<small>{{ duration(row.absence_seconds) }}</small></td><td>{{ duration(row.room_wait_seconds) }} / {{ duration(row.station_wait_seconds) }}</td><td><button v-if="row.status !== 'voided' && state.session.status === 'active'" class="zero-link" @click="correction = row">Korrigieren</button></td></tr>
                <tr v-if="!report.rows.length"><td colspan="9">Für diese Auswahl sind noch keine Gänge vorhanden.</td></tr>
            </tbody></table></div>
            <div class="zero-form-grid mt-5"><section><h3>Nach Raum</h3><div v-for="room in report.rooms" :key="room.name" class="zero-summary-row"><strong>{{ room.name }}</strong><span>{{ room.actual_visits }} Gänge · {{ duration(room.toilet_seconds) }} min:sek</span></div></section><section><h3>Nach Schüler</h3><div v-for="student in report.students" :key="student.name + student.room" class="zero-summary-row"><strong>{{ student.name }}</strong><span>{{ student.actual_visits }} Gänge · {{ duration(student.toilet_seconds) }} min:sek</span></div></section></div>
            <details class="mt-5"><summary>Ereignisse und Aufsichtswechsel</summary><div class="zero-table-scroll"><table class="zero-table"><thead><tr><th>Zeit</th><th>Aufsicht</th><th>Aktion</th><th>Hinweis</th></tr></thead><tbody><tr v-for="event in report.events" :key="event.id"><td>{{ time(event.occurred_at, true) }}</td><td>{{ event.actor }}</td><td>{{ eventLabels[event.action] || event.action }}</td><td>{{ event.details?.reason || event.details?.name || '—' }}</td></tr></tbody></table></div></details>
        </template>
        <v-dialog :model-value="!!correction" max-width="500" @update:model-value="correction = null"><v-card class="pa-5"><h2>Fehlbuchung korrigieren</h2><p class="my-3">{{ correction?.student_name }}: Der Eintrag bleibt im Protokoll, zählt aber nicht mehr als tatsächlicher Gang. Die Person muss wieder sicher im Raum sein.</p><label class="zero-field">Grund<textarea v-model="reason" rows="3" maxlength="1000" /></label><div class="zero-actions mt-4"><button class="zero-button zero-button--secondary" @click="correction = null">Abbrechen</button><button class="zero-button" :disabled="reason.trim().length < 3 || busy" @click="correct">Bestätigen</button></div></v-card></v-dialog>
    </section>
</template>
<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { report as reportRoute, pdf } from '@/actions/App/Http/Controllers/Admin/MaturaController'
import { duration, errorMessage, statusLabels, time } from './maturaFormat'
const props = defineProps({ state: { type: Object, required: true }, busy: Boolean })
const emit = defineEmits(['action'])
const report = ref(null)
const loading = ref(false)
const error = ref('')
const filters = reactive({ room_id: '', student_id: '', status: '' })
const correction = ref(null)
const reason = ref('')
let requestId = 0
const eventLabels = { request: 'Gang angefordert', approve: 'Platz freigegeben', depart: 'Raum verlassen', arrive: 'An Station angekommen', enter: 'Toilette betreten', exit: 'Toilette verlassen', return: 'Im Raum zurück', cancel: 'Storniert', void: 'Fehlbuchung korrigiert', return_without_toilet: 'Zurück ohne Toilettengang', claim: 'Aufsicht übernommen', configured: 'Matura eingerichtet', configuration_changed: 'Konfiguration geändert', access_created: 'Zugang erstellt', access_revoked: 'Zugang widerrufen' }
const students = computed(() => props.state.students.filter((student) => !filters.room_id || student.matura_room_id === filters.room_id))
const query = computed(() => Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')))
const pdfUrl = computed(() => pdf.url(props.state.session.id, { query: query.value }))
async function load() {
    const current = ++requestId
    loading.value = true
    try { const response = await axios.get(reportRoute.url(props.state.session.id), { params: query.value }); if (current === requestId) { report.value = response.data; error.value = '' } }
    catch (failure) { if (current === requestId) error.value = errorMessage(failure) }
    finally { if (current === requestId) loading.value = false }
}
function correct() { emit('action', { action: 'void', visit_id: correction.value.id, expected_status: correction.value.status, reason: reason.value }); correction.value = null; reason.value = '' }
watch(() => filters.room_id, () => { filters.student_id = '' })
watch(filters, load)
watch(() => props.busy, (busy, previous) => { if (previous && !busy) load() })
onMounted(load)
</script>
