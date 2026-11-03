<template>
    <section class="simulation">
        <header class="simulation__heading">
            <div><h1>Aufgaben-Simulator</h1><p>Vorhandene Schülergruppe, automatisch bearbeitete Aufgaben und Rückgaben über Mailpit.</p></div>
            <a href="http://127.0.0.1:8025" target="_blank" rel="noopener noreferrer" class="simulation__mailpit">Simulator-Mailpit öffnen ↗</a>
        </header>
        <p v-if="error" role="alert" class="simulation__error">{{ error }}</p>
        <p v-if="message" role="status" class="simulation__message">{{ message }}</p>
        <div class="simulation__workspace">
            <aside class="simulation__setup">
                <h2>Neuer Durchlauf</h2>
                <button type="button" :disabled="busy" @click="startLocalMailpit">Simulator-Mailpit starten</button>
                <p>Die Personen kommen aus der gespeicherten Arbeit. Erzeugte Testdateien und Nachrichten werden getrennt abgelegt.</p>
                <form @submit.prevent="prepare">
                    <label>Leistungsfeststellung<select v-model="selectedWork" :disabled="busy || loading" required aria-label="Leistungsfeststellung auswählen">
                        <option value="" disabled>Arbeit auswählen</option>
                        <option v-for="work in works" :key="work.id" :value="String(work.id)">{{ work.course_title }} · {{ work.title }}{{ work.dates.length ? ` · ${work.dates.join(', ')}` : '' }}</option>
                    </select></label>
                    <label v-if="currentWork?.dates.length > 1">Durchführungstag<select v-model="selectedDate" :disabled="busy" required aria-label="Durchführungstag"><option value="" disabled>Termin auswählen</option><option v-for="date in currentWork.dates" :key="date" :value="date">{{ date }}</option></select></label>
                    <label>Testfälle<select v-model="profile" :disabled="busy" aria-label="Testfälle">
                        <option value="mixed">Gemischte Abgaben</option><option value="good">Alle vollständig</option>
                    </select></label>
                    <p v-if="profile === 'mixed'" class="simulation__hint">Gute, lückenhafte und fehlerhafte Antworten, fehlender Anhang, Nichtabgabe, verspätete Abgabe und falsche Aufgabe.</p>
                    <button class="simulation__primary" type="submit" :disabled="busy || !selectedWork">{{ preparing ? 'Aufgaben werden bearbeitet …' : 'Abgaben automatisch erzeugen' }}</button>
                    <p v-if="preparing" role="status">Das Aufgabenblatt wird online gelesen. Die KI erzeugt unterschiedliche Bearbeitungen; Namen und Klasse werden lokal ergänzt.</p>
                </form>
                <div v-if="runs.length" class="simulation__previous"><label>Vorhandener Durchlauf<select :value="run?.id || ''" :disabled="busy" aria-label="Vorhandenen Durchlauf öffnen" @change="openRun($event.target.value)">
                    <option value="" disabled>Durchlauf öffnen</option><option v-for="item in runs" :key="item.id" :value="item.id">{{ item.course_title }} · {{ item.title }} · {{ time(item.created_at) }}</option>
                </select></label></div>
            </aside>
            <main class="simulation__run" :aria-busy="busy">
                <div v-if="loading" role="status" class="simulation__empty">Gespeicherte Arbeiten werden geladen …</div>
                <div v-else-if="!run" class="simulation__empty"><v-icon icon="mdi-email-fast-outline" size="56" aria-hidden="true" /><h2>Die Schülerseite übernimmt der Simulator.</h2><p>Wähle eine vorhandene Arbeit aus. Du musst keine Lösungen selbst schreiben. Das vorhandene Aufgabenblatt wird verwendet.</p></div>
                <template v-else>
                    <header class="simulation__run-heading"><div><span class="simulation__badge">Simulation · nur lokal</span><h2>{{ run.title }}</h2><p>{{ run.course_title }} · {{ run.rows.length }} Personen · {{ time(run.created_at) }}</p></div>
                        <a :href="download.url(run.id)" class="simulation__download">Testbestand herunterladen</a>
                    </header>
                    <ol class="simulation__steps" aria-label="Simulationsablauf">
                        <li class="simulation__step--done"><span>1</span><div><strong>Abgaben erzeugt</strong><small>{{ run.rows.length }} zugeordnete Personen</small></div></li>
                        <li :class="{ 'simulation__step--done': sent > 0 }"><span>2</span><div><strong>Rückgaben senden</strong><small>{{ sent }} in Mailpit bestätigt</small></div></li>
                        <li :class="{ 'simulation__step--done': collected > 0 }"><span>3</span><div><strong>Testabgaben einsammeln</strong><small>{{ collected }} Nachrichten gesichert</small></div></li>
                    </ol>
                    <div class="simulation__actions">
                        <button type="button" class="simulation__primary" :disabled="busy" @click="execute('send')">{{ action === 'send' ? 'Rückgaben werden gesendet …' : 'Rückgaben über Mailpit senden' }}</button>
                        <button type="button" :disabled="busy || !canCollect" @click="execute('collect')">{{ action === 'collect' ? 'Testabgaben werden gesichert …' : 'Testabgaben einsammeln' }}</button>
                    </div>
                    <p v-if="!canCollect" class="simulation__collect-hint">Zuerst alle vorgesehenen Rückgaben über Mailpit senden. Danach wird das Einsammeln freigegeben.</p>
                    <p class="simulation__scope">Alle Nachrichten bleiben in der lokalen Mailpit-Instanz. Dieser Durchlauf ändert keine Punkte oder Bewertungen der ausgewählten Arbeit.</p>
                    <div class="simulation__table-wrap"><table>
                        <thead><tr><th>Person</th><th>Testfall</th><th>Rückgabe</th><th>Erzeugte Dateien</th><th>Weitere Fassung</th></tr></thead>
                        <tbody><tr v-for="row in sortedRows" :key="row.participant_id">
                            <td><strong>{{ row.person.last_name }}, {{ row.person.first_name }}</strong><small>{{ row.person.class_name }} · {{ row.person.email }}</small>
                                <details v-if="latest(row).scenario !== 'no_submission'"><summary>Nachricht ansehen</summary><p><strong>{{ latest(row).subject }}</strong></p><p class="simulation__mail-body">{{ latest(row).message }}</p></details>
                            </td>
                            <td><span class="simulation__case">{{ scenarioLabel(latest(row).scenario) }}</span><small>Fassung {{ latest(row).number }}</small><small v-if="latest(row).scenario === 'late'">Simulierte Abgabezeit: {{ time(latest(row).simulated_received_at) }}</small></td>
                            <td><span :class="latest(row).collected_at ? 'simulation__received' : ''">{{ latest(row).scenario === 'no_submission' ? 'Keine Rückgabe vorgesehen' : latest(row).collected_at ? 'Gesichert' : latest(row).mailpit_id ? 'In Mailpit bestätigt' : 'Noch nicht gesendet' }}</span><small v-if="latest(row).collected_at">{{ time(latest(row).collected_at) }}</small><small v-if="latest(row).mailpit_id">{{ latest(row).attachments.length }} tatsächliche Anhänge</small></td>
                            <td><a v-for="file in latest(row).artifacts" :key="file.path" :href="artifact(file.path)" target="_blank" rel="noopener noreferrer">{{ file.name }}</a><small v-if="latest(row).scenario === 'missing_attachment'">Datei erzeugt, absichtlich nicht angehängt.</small><a v-if="latest(row).saved_message" :href="artifact(latest(row).saved_message)">Vollständige E-Mail</a></td>
                            <td><button type="button" :disabled="busy || row.versions.length >= 5" @click="revise(row.participant_id)">Neue Fassung erzeugen</button><small v-if="row.versions.length > 1">{{ row.versions.length }} Fassungen erhalten</small></td>
                        </tr></tbody>
                    </table></div>
                    <details class="simulation__history"><summary>Verlauf des Testdurchlaufs</summary><ul><li v-for="(event, index) in run.history" :key="index">{{ time(event.at) }} · {{ eventLabel(event.event) }}</li></ul></details>
                </template>
            </main>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { sortedPeople } from '@/helpers/personNames'
import { index, store, show, send, collect, startMailpit, revise as reviseRun, file as getFile, download } from '@/actions/App/Http/Controllers/Admin/TeachingSimulationController'
import { assessmentTimeText } from '@/helpers/workAssessmentTimes'

const works = ref([])
const runs = ref([])
const run = ref(null)
const sortedRows = computed(() => sortedPeople(run.value?.rows))
const selectedWork = ref('')
const selectedDate = ref('')
const profile = ref('mixed')
const loading = ref(true)
const preparing = ref(false)
const action = ref('')
const error = ref('')
const message = ref('')
const busy = computed(() => loading.value || preparing.value || Boolean(action.value))
const currentWork = computed(() => works.value.find(work => String(work.id) === selectedWork.value))
const latest = row => row.versions.at(-1)
const sent = computed(() => run.value?.rows.filter(row => latest(row).mailpit_id).length || 0)
const collected = computed(() => run.value?.rows.filter(row => latest(row).collected_at).length || 0)
const canCollect = computed(() => {
    const returns = run.value?.rows.map(latest).filter(version => version.scenario !== 'no_submission') || []
    return returns.length > 0 && returns.every(version => Boolean(version.mailpit_id))
})
const time = assessmentTimeText
function scenarioLabel(value) { return { good: 'Vollständig', partial: 'Lückenhaft', weak: 'Fachliche Fehler', missing_attachment: 'Anhang fehlt', no_submission: 'Nichtabgabe', late: 'Verspätet', wrong_assignment: 'Falsche Aufgabe' }[value] || value }
function eventLabel(value) { return { prepared: 'Testabgaben erzeugt', mailpit_returns_sent: 'Rückgaben über Mailpit geprüft', simulation_collected: 'Testabgaben eingesammelt', revision_prepared: 'Weitere Fassung erzeugt' }[value] || value }
function artifact(path) { return getFile.url(run.value.id, { query: { path } }) }
function accept(data) {
    if (data?.simulation !== true || !data.id || !Array.isArray(data.rows)) throw new Error('Der Testdurchlauf konnte nicht bestätigt werden.')
    run.value = data
    runs.value = [data, ...runs.value.filter(item => item.id !== data.id)]
}
function failure(problem) { error.value = problem.response?.data?.message || problem.message || 'Der Simulator konnte den Vorgang nicht abschließen.' }
async function prepare() {
    if (busy.value || !currentWork.value) return
    error.value = ''; message.value = ''; preparing.value = true
    try {
        const response = await axios.post(store.url(currentWork.value.id), { profile: profile.value,
            date: currentWork.value.dates.length === 1 ? currentWork.value.dates[0] : selectedDate.value || null }, { timeout: 180000 })
        accept(response.data.data)
        message.value = 'Die Testbearbeitungen sind erzeugt. Du kannst sie ansehen und anschließend über Mailpit zurücksenden.'
    } catch (problem) { failure(problem) } finally { preparing.value = false }
}
async function startLocalMailpit() {
    if (busy.value) return
    action.value = 'mailpit'; error.value = ''; message.value = ''
    try { message.value = (await axios.post(startMailpit.url())).data.message }
    catch (problem) { failure(problem) } finally { action.value = '' }
}
async function openRun(id) {
    if (!id || busy.value) return
    action.value = 'open'; error.value = ''; message.value = ''
    try { accept((await axios.get(show.url(id))).data.data) } catch (problem) { failure(problem) } finally { action.value = '' }
}
async function execute(name) {
    if (busy.value || !run.value || (name === 'collect' && !canCollect.value)) return
    const id = run.value.id
    action.value = name; error.value = ''; message.value = ''
    try {
        const endpoint = name === 'send' ? send : collect
        const result = (await axios.post(endpoint.url(id), {}, { timeout: 180000 })).data.data
        if (result.id !== id) throw new Error('Die Antwort gehört zu einem anderen Durchlauf.')
        accept(result)
        message.value = name === 'send' ? 'Die Rückgaben sind in Mailpit bestätigt. Bereits gesendete Fassungen wurden nicht erneut gesendet.' : 'Die vollständigen Testnachrichten und ihre Anhänge sind gesichert. Vorhandene Sammelzeitpunkte bleiben erhalten.'
    } catch (problem) { failure(problem) } finally { action.value = '' }
}
async function revise(participant) {
    if (busy.value || !run.value) return
    action.value = 'revise'; error.value = ''; message.value = ''
    try {
        accept((await axios.post(reviseRun.url(run.value.id), { participant_id: participant })).data.data)
        message.value = 'Eine neue Fassung ist vorbereitet. Die früheren Fassungen bleiben erhalten. Sende die neue Fassung anschließend über Mailpit.'
    } catch (problem) { failure(problem) } finally { action.value = '' }
}
watch(selectedWork, () => { selectedDate.value = '' })
onMounted(async () => {
    try { const response = await axios.get(index.url()); works.value = response.data.works; runs.value = response.data.runs; run.value = runs.value[0] || null }
    catch (problem) { failure(problem) } finally { loading.value = false }
})
</script>

<style scoped>
.simulation__collect-hint { padding: 0 24px 12px; color: #526780; font-size: .8rem; }
.simulation{padding:24px 8px 36px;color:#173458}.simulation__heading{display:flex;align-items:start;justify-content:space-between;gap:24px;margin-bottom:28px}.simulation h1{font-size:1.7rem;line-height:1.25}.simulation__heading p{margin-top:10px;color:#526780;max-width:74ch}.simulation__workspace{display:grid;grid-template-columns:290px minmax(0,1fr);gap:24px}.simulation__setup{padding:24px;background:#edf3f9;border-radius:12px;align-self:start}.simulation h2{font-size:1.15rem;margin-bottom:12px}.simulation__setup>p{font-size:.9rem;line-height:1.65;color:#526780}.simulation form{margin-top:24px}.simulation label{display:block;font-size:.88rem;font-weight:650;margin:0 0 20px}.simulation select{display:block;width:100%;margin-top:8px;min-height:44px;padding:9px 10px;background:white;border:1px solid #b8c9dc;border-radius:6px;font:inherit;font-weight:400}.simulation__hint,.simulation__scope{font-size:.8rem;line-height:1.6;color:#526780}.simulation button,.simulation__download{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 14px;border:1px solid #b8c9dc;border-radius:6px;background:white;color:#1b62c5;font:inherit;font-size:.85rem;font-weight:650;text-decoration:none;cursor:pointer}.simulation button:disabled{opacity:.55;cursor:default}.simulation .simulation__primary{background:#1b62c5;color:white;border-color:#1b62c5}.simulation__setup button{width:100%;margin-top:18px}.simulation button:focus-visible,.simulation select:focus-visible,.simulation a:focus-visible{outline:3px solid #1b62c5;outline-offset:3px}.simulation__previous{margin-top:32px;padding-top:22px;border-top:1px solid #c8d5e5}.simulation__run{min-width:0;border:1px solid #d6e0eb;background:white;border-radius:12px;overflow:hidden}.simulation__empty{display:flex;min-height:430px;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:40px;color:#526780}.simulation__empty h2{color:#173458;margin-top:24px}.simulation__empty p{max-width:48ch;line-height:1.7}.simulation__run-heading{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:24px}.simulation__run-heading h2{font-size:1.5rem;margin:14px 0 8px}.simulation__run-heading p{font-size:.85rem;color:#526780}.simulation__badge{display:inline-block;color:#775c16;background:#fff3ce;border:1px solid #ead9a2;padding:5px 9px;border-radius:5px;font-size:.75rem;font-weight:650}.simulation__steps{display:flex;gap:20px;list-style:none;padding:20px 24px;margin:0;background:#f4f7fb;border-block:1px solid #e0e7ef}.simulation__steps li{display:flex;gap:10px;align-items:center;flex:1}.simulation__steps li>span{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:30px;height:30px;border:1px solid #b8c9dc;border-radius:50%;font-weight:700}.simulation__steps strong{display:block;font-size:.82rem}.simulation small{display:block;font-size:.75rem;color:#526780;margin-top:7px;line-height:1.5}.simulation__step--done>span{background:#137865!important;color:white;border-color:#137865!important}.simulation__actions{display:flex;gap:12px;flex-wrap:wrap;padding:24px 24px 12px}.simulation__scope{padding:0 24px 24px}.simulation__table-wrap{overflow-x:auto}.simulation table{width:100%;border-collapse:collapse;text-align:left;font-size:.85rem}.simulation th{padding:14px 16px;background:#edf3f9;border-block:1px solid #d6e0eb;font-weight:650;white-space:nowrap}.simulation td{padding:18px 16px;border-bottom:1px solid #e0e7ef;vertical-align:top;min-width:150px}.simulation td:first-child{min-width:240px}.simulation td a{display:block;margin-bottom:8px;color:#1b62c5}.simulation details{margin-top:14px}.simulation summary{cursor:pointer;font-size:.8rem;color:#1b62c5}.simulation__mail-body{white-space:pre-wrap;max-width:45ch;line-height:1.6;margin-top:12px}.simulation__received{color:#137865;font-weight:650}.simulation__case{font-weight:600}.simulation__history{padding:16px 24px 24px;margin-top:0!important}.simulation__history ul{padding-left:20px;margin-top:14px;color:#526780;font-size:.8rem;line-height:1.8}.simulation__mailpit{color:#1b62c5;font-size:.85rem;white-space:nowrap}.simulation__error{padding:15px 18px;margin-bottom:20px;background:#fbeeee;color:#a62e30;border-radius:7px}.simulation__message{padding:15px 18px;margin-bottom:20px;background:#eaf4ef;color:#17634e;border-radius:7px}@media(max-width:1000px){.simulation__workspace{grid-template-columns:1fr}.simulation__setup form{display:grid;grid-template-columns:1fr 1fr;gap:0 20px}.simulation__setup button{align-self:start}.simulation__steps{flex-wrap:wrap}.simulation__steps li{min-width:180px}}@media(max-width:600px){.simulation{padding:16px 0}.simulation__heading,.simulation__run-heading{flex-direction:column;align-items:start}.simulation__setup form{display:block}.simulation__steps{display:block}.simulation__steps li+li{margin-top:18px}.simulation__download{width:100%}}
</style>
