<template>
    <div class="ns-game">
        <header class="ns-toolbar">
            <div><h1>Signal Null</h1><p class="ns-muted" style="margin-top:8px">Das Zahlensystem-Abenteuer für deinen Kurs</p></div>
            <div class="ns-toolbar"><button v-if="localProbeAvailable" class="ns-button ns-button-secondary" @click="probeDialog = true">Lokal ausprobieren</button><button class="ns-button" :disabled="busy || !available || !timingAvailable || !courses.length" @click="createDialog = true">Neues Abenteuer</button></div>
        </header>
        <div v-if="error" class="ns-alert ns-alert-error" role="alert">{{ error }} <button class="ns-button ns-button-quiet" @click="load">Erneut laden</button></div>
        <p v-if="loading" role="status" style="margin-top:20px">Die Abenteuer werden geladen …</p>
        <div v-else-if="!available" class="ns-alert">Die Spieltabellen müssen noch lokal aktiviert werden.</div>
        <template v-else-if="!selected">
            <p v-if="!timingAvailable" class="ns-alert">Die lokale Datenbank-Erweiterung für neue Runden mit Zeitmessung und Reihung ist vorbereitet und muss noch freigegeben werden. Bestehende Runden und lokale Probeläufe bleiben verfügbar.</p>
            <section v-if="localProbeAvailable && localProbes.length" class="ns-panel ns-stack" style="margin-top:20px"><h2>Deine lokalen Probeläufe</h2><p class="ns-small ns-muted">Eigene Testdaten, getrennte Testpersonen und gespeicherte Fortschritte. Zum Entfernen den Probelauf öffnen.</p><a v-for="probe in localProbes" :key="probe.id" class="ns-button ns-button-secondary" :href="probe.url">Probelauf {{ probe.id.slice(0, 8) }} fortsetzen</a></section>
            <section class="ns-briefing">
                <h2>Ein Rechenzentrum ist verstummt.</h2>
                <p>Dein Kurs stellt die Verbindung wieder her: mit schaltbaren Bits, Hex-Bausteinen, manipulierten Protokollen und codierten Funksignalen. Jede neue Runde bekommt eine neue Variante. Fortsetzen bewahrt das bestehende Abenteuer.</p>
                <ol class="ns-path"><li v-for="chapter in chapters" :key="chapter">{{ chapter }}</li></ol>
                <p class="ns-small">Plane 30, 40, 60 oder 75 Minuten Spielzeit und wähle die Schwierigkeit separat. Die tatsächliche Dauer hängt von Gruppe, Hilfen und Gesprächen ab. Pausen sind jederzeit möglich; es gibt keine Zeitstrafe und keine Benotung.</p>
            </section>
            <p v-if="!courses.length" class="ns-alert">Für eine Klassenrunde brauchst du einen eigenen Kurs im ausgewählten Schuljahr mit bestehenden Schülerkonten.</p>
            <h2>Deine Klassenrunden</h2>
            <p v-if="!sessions.length" class="ns-muted" style="margin-top:12px">Noch keine Runde vorbereitet. Beginne mit „Neues Abenteuer“.</p>
            <ul class="ns-session-list">
                <li v-for="session in sessions" :key="session.id" class="ns-session-row">
                    <div><h3>{{ session.title }}</h3><p class="ns-small ns-muted">{{ session.planned_minutes }} Minuten geplant · {{ levels[session.difficulty] }} · {{ states[session.status] }}</p></div>
                    <div class="ns-toolbar"><button class="ns-button ns-button-secondary" @click="open(session.id)">{{ session.status === 'ended' ? 'Fortschritt ansehen' : 'Runde öffnen / fortsetzen' }}</button><button class="ns-button ns-button-quiet" :disabled="busy || !['waiting', 'ended'].includes(session.status)" :aria-label="`Klassenrunde ${session.title} löschen`" @click="openDelete(session)">Runde löschen</button></div>
                </li>
            </ul>
            <p v-if="sessions.length" class="ns-small ns-muted" style="margin-top:12px">Zum Löschen laufende oder pausierte Runden zuerst beenden.</p>
        </template>
        <template v-else>
            <button class="ns-button ns-button-quiet" style="margin:20px 0" @click="closeMonitor">Zur Rundenübersicht</button>
            <section class="ns-panel ns-stack">
                <div class="ns-toolbar"><div><h2>{{ selected.session.title }}</h2><p class="ns-muted">{{ states[selected.session.status] }} · {{ selected.session.planned_minutes }} Minuten geplant · {{ levels[selected.session.difficulty] }}</p></div></div>
                <NumberSystemClock :clock="selected.clock" />
                <div v-if="selected.session.status !== 'ended'">
                    <p class="ns-small ns-muted">Kurscode für den Beitritt</p><p class="ns-code">{{ selected.session.join_code }}</p>
                    <p class="ns-small" style="margin-top:12px">Im Schülerkonto: Menü → Zahlensysteme → Kurscode eingeben. Oder per QR-Code beitreten:</p>
                    <NumberSystemInvitation v-if="selected.invitation" :invitation="selected.invitation" />
                    <p class="ns-small ns-muted" style="margin-top:10px">Nur aktive Mitglieder dieses Kurses können beitreten. {{ selected.session.mode === 'teams' ? 'Teams bestehen aus 2–4 Personen mit persönlichen Zugängen. Den privaten Teamcode teilen sie innerhalb der Gruppe; Tresorhinweise werden getrennt angezeigt.' : 'Jede Person speichert ihren eigenen Weg. An einem gemeinsamen Gerät kann ein Paar gemeinsam überlegen und erklären.' }}</p>
                </div>
                <div class="ns-toolbar">
                    <button v-if="selected.session.status === 'waiting'" class="ns-button" :disabled="busy" @click="control('start')">Klassenrunde starten</button>
                    <button v-if="selected.session.status === 'running'" class="ns-button ns-button-secondary" :disabled="busy" @click="control('pause')">Runde pausieren</button>
                    <button v-if="selected.session.status === 'paused'" class="ns-button" :disabled="busy" @click="control('resume')">Runde fortsetzen</button>
                    <button v-if="selected.session.status !== 'ended'" class="ns-button ns-button-quiet" :disabled="busy" @click="endDialog = true">Runde beenden</button>
                    <span class="ns-small ns-muted">{{ joinedCount }} / {{ selected.enrolled }} Kurskonten beigetreten</span>
                </div>
            </section>
            <section class="ns-panel" style="margin-top:20px">
                <div class="ns-toolbar"><h2>Fortschritt im Kurs</h2><span class="ns-small ns-muted">Automatisch aktualisiert</span></div>
                <p v-if="!selected.runs.length" class="ns-muted" style="margin-top:15px">Noch niemand beigetreten. Gib den Kurscode weiter und warte, bis die Gruppe bereit ist.</p>
                <div v-else class="ns-monitor"><table><thead><tr><th>Person / Team</th><th>Bereich</th><th>Bestätigt</th><th>Mit Hilfe</th><th v-if="selected.clock?.mode !== 'none'">Spielzeit</th></tr></thead><tbody>
                    <tr v-for="run in selected.runs" :key="run.id"><td><strong>{{ run.team_name || run.members[0] }}</strong><NumberSystemPresence :people="run.presence" /></td><td>{{ run.completed ? 'Verbindung wiederhergestellt' : run.chapter }}</td><td>{{ run.solved }} / {{ run.total }}<progress class="ns-progress" :value="run.solved" :max="run.total" :aria-label="`Fortschritt ${run.team_name || run.members[0]}`" /></td><td>{{ run.with_help }}</td><td v-if="selected.clock?.mode !== 'none'"><NumberSystemClock :clock="run.clock" compact /></td></tr>
                </tbody></table></div>
                <p class="ns-small ns-muted" style="margin-top:16px">Verbindung: Nach 45 Sekunden ohne Signal erscheint „Signal verzögert“, nach 90 Sekunden „Verbindung unterbrochen“. Der Grund ist nicht sicher erkennbar; auch Hintergrundfenster können verzögert signalisieren. Fortschritt bleibt gespeichert.</p>
                <p class="ns-small ns-muted" style="margin-top:16px">Zum Abschluss: Lasst verschiedene Lösungswege erklären. „Mit Hilfe“ beschreibt den Lernweg und zieht keine Punkte ab.</p>
            </section>
            <NumberSystemRanking :ranking="selected.ranking" />
        </template>
        <v-dialog v-model="createDialog" persistent max-width="660">
            <v-card class="ns-dialog"><form @submit.prevent="create">
                <h2>Neues Abenteuer vorbereiten</h2>
                <p>Die Runde beginnt im Warteraum. Beitreten und gemeinsames Starten sind getrennte Schritte.</p>
                <label class="ns-field"><span>Bezeichnung</span><input v-model="form.title" required maxlength="120" autofocus /></label>
                <div class="ns-form">
                    <label class="ns-field"><span>Kurs</span><select v-model="form.course_id" required><option value="">Kurs auswählen</option><option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option></select></label>
                    <label class="ns-field"><span>Geplante Spielzeit</span><select v-model.number="form.planned_minutes"><option v-for="minutes in [30,40,60,75]" :key="minutes" :value="minutes">{{ minutes }} Minuten</option></select></label>
                    <label class="ns-field"><span>Schwierigkeit</span><select v-model.number="form.difficulty"><option v-for="(label, value) in levels" :key="value" :value="Number(value)">{{ label }}</option></select></label>
                    <label class="ns-field"><span>Zusammenarbeit</span><select v-model="form.mode"><option value="solo">Einzeln / gemeinsames Gerät</option><option value="teams">Teams, getrennte Hinweise (2–4)</option></select></label>
                </div>
                <NumberSystemStartOptions :model="form" />
                <p>Leicht: kleine Zahlen und acht Bits. Mittel: Werte bis 255. Anspruchsvoll: auch bis 4095 und zwölf Bits. Jede Runde enthält alle sechs Umrechnungsrichtungen, Forscheraufträge, ASCII und ein Finale.</p>
                <p v-if="error" role="alert">{{ error }}</p>
                <div class="ns-toolbar"><button type="button" class="ns-button ns-button-quiet" :disabled="busy" @click="createDialog = false">Abbrechen</button><button class="ns-button" :disabled="busy || !form.title.trim() || !form.course_id">{{ busy ? 'Wird vorbereitet …' : 'Abenteuer vorbereiten' }}</button></div>
            </form></v-card>
        </v-dialog>
        <v-dialog v-model="probeDialog" persistent max-width="600"><v-card class="ns-dialog"><form @submit.prevent="createProbe"><h2>Lokal mit mehreren Fenstern ausprobieren</h2><p>Eine eigene Datei speichert nur synthetische Testpersonen. Du bleibst im Lehrkraftkonto angemeldet. Keine echten Kurse, Konten oder Noten werden geändert.</p><div class="ns-form">
            <label class="ns-field"><span>Geplante Spielzeit</span><select v-model.number="probeForm.planned_minutes"><option v-for="minutes in [30,40,60,75]" :key="minutes" :value="minutes">{{ minutes }} Minuten</option></select></label>
            <label class="ns-field"><span>Schwierigkeit</span><select v-model.number="probeForm.difficulty"><option v-for="(label, value) in levels" :key="value" :value="Number(value)">{{ label }}</option></select></label>
            <label class="ns-field"><span>Spielweise</span><select v-model="probeForm.mode"><option value="teams">Gemeinsames Testteam</option><option value="solo">Zwei eigene Spielstände</option></select></label>
            <label class="ns-field"><span>Umfang des Probelaufs</span><select v-model="probeForm.short"><option :value="true">Kurztest mit 3 echten Aufgaben</option><option :value="false">Vollständiges Abenteuer</option></select></label>
        </div><NumberSystemStartOptions :model="probeForm" /><p>Der Kurztest enthält Teamtresor, Bit-Schalter und Finale. Er ist kürzer als die gewählte Unterrichtsplanung; die normalen Spieloptionen bleiben unverändert.</p><p v-if="error" role="alert">{{ error }}</p><div class="ns-toolbar"><button type="button" class="ns-button ns-button-quiet" @click="probeDialog = false">Abbrechen</button><button class="ns-button" :disabled="busy">Probelauf anlegen</button></div></form></v-card></v-dialog>
        <v-dialog v-model="endDialog" persistent max-width="500"><v-card class="ns-dialog"><form @submit.prevent="control('end')"><h2>Klassenrunde beenden?</h2><p>Der Fortschritt bleibt sichtbar. Neue Antworten sind danach gesperrt. Für eine Unterbrechung wähle stattdessen Pause.</p><div class="ns-toolbar"><button type="button" class="ns-button ns-button-quiet" @click="endDialog = false">Zurück</button><button class="ns-button" :disabled="busy">Runde beenden</button></div></form></v-card></v-dialog>
        <v-dialog :model-value="Boolean(deleteTarget)" persistent max-width="540"><v-card v-if="deleteTarget" class="ns-dialog"><form @submit.prevent="remove"><h2>„{{ deleteTarget.title }}“ löschen?</h2><p>Diese Klassenrunde mit {{ deleteTarget.runs_count ?? 0 }} Spielständen wird endgültig entfernt. Antworten, Teamzuordnungen, Zeitdaten und Reihung gehen verloren. Kurs- und Teamcodes funktionieren danach nicht mehr.</p><p>Andere Runden, Kurse und Schülerkonten bleiben erhalten.</p><p v-if="error" role="alert">{{ error }}</p><div class="ns-toolbar"><button type="button" class="ns-button ns-button-quiet" :disabled="busy" @click="deleteTarget = null">Abbrechen</button><button class="ns-button ns-delete-confirm" :disabled="busy">{{ busy ? 'Wird gelöscht …' : 'Klassenrunde endgültig löschen' }}</button></div></form></v-card></v-dialog>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { index, create as createSession, show, control as controlSession, remove as removeSession } from '@/actions/App/Http/Controllers/Admin/NumberSystemGameController'
import NumberSystemInvitation from '@/pages/components/numberSystems/NumberSystemInvitation.vue'
import NumberSystemStartOptions from '@/pages/components/numberSystems/NumberSystemStartOptions.vue'
import NumberSystemClock from '@/pages/components/numberSystems/NumberSystemClock.vue'
import NumberSystemRanking from '@/pages/components/numberSystems/NumberSystemRanking.vue'
import NumberSystemPresence from '@/pages/components/numberSystems/NumberSystemPresence.vue'
import { create as createLocalProbe } from '@/actions/App/Http/Controllers/Admin/NumberSystemLocalProbeController'
import { useRouter } from 'vue-router'
import '@/pages/components/numberSystems/game.css'
const chapters = ['Maschinenraum', 'Fehlerarchiv', 'Tresor', 'Funkzentrale']
const levels = { 1: 'Leicht', 2: 'Mittel', 3: 'Anspruchsvoll' }
const states = { waiting: 'Warteraum', running: 'Runde läuft', paused: 'Pausiert', ended: 'Beendet' }
const courses = ref([]), sessions = ref([]), selected = ref(null), available = ref(true), loading = ref(false), busy = ref(false), error = ref('')
const createDialog = ref(false), endDialog = ref(false)
const timingAvailable = ref(true)
const deleteTarget = ref(null)
const form = reactive({ title: 'Signal Null', course_id: '', planned_minutes: 40, difficulty: 2, mode: 'solo', timer_mode: 'none', countdown_minutes: null, ranking_enabled: false })
const router = useRouter()
const localProbeAvailable = ref(false), localProbes = ref([]), probeDialog = ref(false)
const probeForm = reactive({ planned_minutes: 30, difficulty: 2, mode: 'teams', short: true, timer_mode: 'none', countdown_minutes: null, ranking_enabled: false })
let poll = null, disposed = false, polling = false, monitorGeneration = 0
const joinedCount = computed(() => (selected.value?.runs ?? []).reduce((sum, run) => sum + run.members.length, 0))
const failureMessage = failure => failure.response?.data?.message || Object.values(failure.response?.data?.errors ?? {}).flat()[0] || 'Die Verbindung ist unterbrochen. Bitte versuche es erneut.'
async function load() {
    loading.value = true; error.value = ''
    try { const { data } = await axios.get(index.url()); courses.value = data.courses; sessions.value = data.sessions; available.value = data.available; timingAvailable.value = data.timing_available !== false; localProbeAvailable.value = data.local_probe_available === true; localProbes.value = data.local_probes ?? [] }
    catch (failure) { error.value = failureMessage(failure) } finally { loading.value = false }
}
function closeMonitor() { ++monitorGeneration; selected.value = null }
async function open(id, background = false) {
    const ticket = background ? monitorGeneration : ++monitorGeneration
    try { const { data } = await axios.get(show.url(id)); if (!disposed && ticket === monitorGeneration && (!background || selected.value?.session.id === id)) { selected.value = data; error.value = '' } }
    catch (failure) { error.value = failureMessage(failure) }
}
async function create() {
    if (busy.value) { return }
    busy.value = true; error.value = ''
    try { const { data } = await axios.post(createSession.url(), { ...form, title: form.title.trim() }); createDialog.value = false; await load(); await open(data.session.id) }
    catch (failure) { error.value = failureMessage(failure) } finally { busy.value = false }
}
function openDelete(session) { error.value = ''; deleteTarget.value = session }
async function remove() {
    if (busy.value || !deleteTarget.value) { return }
    busy.value = true; error.value = ''
    try { await axios.delete(removeSession.url(deleteTarget.value.id), { data: { confirmation: deleteTarget.value.title } }); deleteTarget.value = null; await load() }
    catch (failure) { error.value = failureMessage(failure) } finally { busy.value = false }
}
async function createProbe() {
    if (busy.value) { return }
    busy.value = true; error.value = ''
    try { const { data } = await axios.post(createLocalProbe.url(), { ...probeForm }); await router.push(new URL(data.url, window.location.origin).pathname) }
    catch (failure) { error.value = failureMessage(failure) } finally { busy.value = false }
}
async function control(action) {
    if (busy.value || !selected.value) { return }
    ++monitorGeneration
    busy.value = true; error.value = ''
    try { const { data } = await axios.post(controlSession.url(selected.value.session.id), { action }); selected.value = data; endDialog.value = false; await load() }
    catch (failure) { error.value = failureMessage(failure) } finally { busy.value = false }
}
onMounted(() => { void load(); poll = setInterval(async () => { if (selected.value && !busy.value && !polling && !createDialog.value && !endDialog.value && !document.hidden) { polling = true; try { await open(selected.value.session.id, true) } finally { polling = false } } }, 4000) })
onBeforeUnmount(() => { disposed = true; clearInterval(poll) })
</script>
<style scoped>
.ns-dialog .ns-delete-confirm { background: #f2b5a7; border-color: #f2b5a7; color: #381c1b; }
</style>
