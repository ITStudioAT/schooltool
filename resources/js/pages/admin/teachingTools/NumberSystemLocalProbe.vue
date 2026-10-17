<template>
    <div class="ns-game">
        <header class="ns-stack">
            <div class="ns-toolbar"><h1>Lokaler Probelauf</h1><RouterLink class="ns-button ns-button-quiet" to="/admin/teaching-tools/games/number-systems">Zur Spielverwaltung</RouterLink></div>
            <p class="ns-gold">Nur Testdaten · Proberunde {{ probe.slice(0, 8) }} · {{ participant ? info?.label || 'Testperson' : 'Lehrkraft' }}</p>
            <p class="ns-small ns-muted">Anmeldungen werden hier durch getrennte Testpersonen ersetzt. Alle Spielaktionen laufen auf dem Server und werden in einer eigenen lokalen Datei gespeichert. Deine normale Anmeldung bleibt unverändert.</p>
            <p v-if="info?.short" class="ns-small ns-muted">Kurztest: drei unveränderte Aufgaben aus dem echten Generator (Teamtresor, Bits, Finale). Das ist ein Techniktest und kein vollständiger Lernweg für 30 Minuten.</p>
        </header>
        <div v-if="error" class="ns-alert ns-alert-error" role="alert">{{ error }} <button class="ns-button ns-button-secondary" @click="load">Erneut laden</button></div>
        <p v-if="loading" style="margin-top:20px">Probelauf wird geladen …</p>
        <template v-if="info && !participant">
            <section class="ns-panel ns-stack" style="margin-top:22px">
                <div class="ns-toolbar"><h2>Fenster für zwei Testpersonen</h2><span>{{ statusLabels[info.session.status] }}</span></div>
                <NumberSystemClock :clock="info.clock" />
                <p>Öffne A und B über die Links in getrennten Fenstern oder Tabs. A gründet ein Team; B tritt mit dessen privatem Teamcode bei. Die Links für B werden nach dem ersten Beitritt automatisch vorbelegt.</p>
                <div class="ns-toolbar"><a v-for="person in info.players" :key="person.participant" :href="person.url" class="ns-button ns-button-secondary" target="_blank" rel="noopener noreferrer">{{ person.label }} öffnen</a></div>
                <p class="ns-small ns-muted">Ein dupliziertes Fenster bleibt derselben Testperson zugeordnet. Ein neuer Probelauf erhält eine neue Kennung; alte Fenster bleiben bei ihrer Runde.</p>
                <p>Kurscode im Probelauf: <strong class="ns-code ns-code-small">{{ info.session.join_code }}</strong></p>
                <div class="ns-toolbar">
                    <button v-if="info.session.status === 'waiting'" class="ns-button" :disabled="busy" @click="control('start')">Proberunde starten</button>
                    <button v-if="info.session.status === 'running'" class="ns-button ns-button-secondary" :disabled="busy" @click="control('pause')">Proberunde pausieren</button>
                    <button v-if="info.session.status === 'paused'" class="ns-button" :disabled="busy" @click="control('resume')">Proberunde fortsetzen</button>
                    <button v-if="info.session.status !== 'ended'" class="ns-button ns-button-quiet" :disabled="busy" @click="endDialog = true">Proberunde beenden</button>
                </div>
            </section>
            <section class="ns-panel ns-stack" style="margin-top:20px"><h2>Gespeicherter Fortschritt</h2>
                <p v-if="!info.runs.length" class="ns-muted">Noch keine Testperson beigetreten.</p>
                <div v-for="(run, index) in info.runs" :key="index"><strong>{{ run.name }}</strong> · {{ run.members.join(', ') }} · {{ run.solved }} / {{ run.total }} bestätigt {{ run.completed ? '· abgeschlossen' : '' }}<progress class="ns-progress" :value="run.solved" :max="run.total" /><NumberSystemClock :clock="run.clock" compact /><NumberSystemPresence :people="run.presence" /></div>
                <p class="ns-small ns-muted">Ohne Signal nach 45 Sekunden: „Signal verzögert“; nach 90 Sekunden: „Verbindung unterbrochen“. Der Grund ist nicht sicher erkennbar. Beim Wiederverbinden bleibt der Fortschritt erhalten.</p>
            </section>
            <NumberSystemRanking :ranking="info.ranking" />
            <section class="ns-panel ns-stack" style="margin-top:20px"><h2>Probelauf verwalten</h2><p class="ns-small ns-muted">Dieser gespeicherte Probelauf {{ probe.slice(0, 8) }} hat eine eigene lokale Datei. Beim Löschen gehen seine Testpersonen, Fortschritte, Zeitdaten und Ergebnisse verloren.</p><div><button class="ns-button ns-button-quiet" :disabled="busy" @click="removeDialog = true">Probelauf löschen</button></div></section>
        </template>
        <template v-else-if="info && participant">
            <section v-if="!info.run" class="ns-panel ns-stack" style="margin-top:22px">
                <h2>{{ info.label }} beitreten lassen</h2>
                <form class="ns-stack" @submit.prevent="join">
                    <label class="ns-field"><span>Kurscode des Probelaufs</span><input v-model="code" required maxlength="20" /></label>
                    <template v-if="info.session.mode === 'teams'">
                        <div class="ns-toolbar"><label class="ns-choice"><input v-model="teamChoice" type="radio" value="new" />Neues Testteam</label><label class="ns-choice"><input v-model="teamChoice" type="radio" value="existing" />Zu vorhandenem Testteam</label></div>
                        <label v-if="teamChoice === 'new'" class="ns-field"><span>Teamname</span><input v-model="teamName" required maxlength="60" /></label>
                        <label v-else class="ns-field"><span>Privater Teamcode</span><input v-model="teamCode" required maxlength="12" /></label>
                    </template>
                    <button class="ns-button" :disabled="busy || info.session.status === 'ended'">Mit {{ info.label }} beitreten</button>
                </form>
            </section>
            <NumberSystemPlayer v-else :key="`${probe}-${participant}`" :run-id="info.run.id" :probe-id="probe" :participant="participant" @back="teacherView" />
        </template>
        <v-dialog v-model="endDialog" max-width="500"><v-card class="ns-dialog"><form @submit.prevent="control('end'); endDialog = false"><h2>Proberunde beenden?</h2><p>Für eine Unterbrechung genügt Pause. Nach dem Beenden bleiben die Ergebnisse sichtbar.</p><div class="ns-toolbar"><button type="button" class="ns-button ns-button-quiet" @click="endDialog = false">Zurück</button><button class="ns-button">Beenden</button></div></form></v-card></v-dialog>
        <v-dialog v-model="removeDialog" persistent max-width="520"><v-card class="ns-dialog"><form @submit.prevent="remove"><h2>Probelauf {{ probe.slice(0, 8) }} löschen?</h2><p>Die eigene lokale Datei dieses Probelaufs wird endgültig entfernt, einschließlich Testpersonen, Spielständen, Zeitdaten und Ergebnissen. Offene Testfenster können danach nicht mehr fortsetzen.</p><p>Andere Probeläufe, echte Klassenrunden und deine normale Anmeldung bleiben erhalten.</p><p v-if="error" role="alert">{{ error }}</p><div class="ns-toolbar"><button type="button" class="ns-button ns-button-quiet" :disabled="busy" @click="removeDialog = false">Abbrechen</button><button class="ns-button" :disabled="busy">{{ busy ? 'Wird gelöscht …' : 'Probelauf endgültig löschen' }}</button></div></form></v-card></v-dialog>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { show, player, join as joinProbe, control as controlProbe, remove as removeProbe } from '@/actions/App/Http/Controllers/Admin/NumberSystemLocalProbeController'
import NumberSystemPlayer from '@/pages/components/numberSystems/NumberSystemPlayer.vue'
import NumberSystemClock from '@/pages/components/numberSystems/NumberSystemClock.vue'
import NumberSystemRanking from '@/pages/components/numberSystems/NumberSystemRanking.vue'
import NumberSystemPresence from '@/pages/components/numberSystems/NumberSystemPresence.vue'
import '@/pages/components/numberSystems/game.css'
const route = useRoute(), router = useRouter()
const probe = computed(() => String(route.params.probe))
const participant = computed(() => ['1', '2'].includes(String(route.query.participant)) ? Number(route.query.participant) : null)
const info = ref(null), loading = ref(false), busy = ref(false), error = ref(''), endDialog = ref(false), removeDialog = ref(false)
const code = ref(''), teamCode = ref(''), teamName = ref('Testteam'), teamChoice = ref('new')
const statusLabels = { waiting: 'Warteraum', running: 'Runde läuft', paused: 'Pausiert', ended: 'Beendet' }
let generation = 0, disposed = false, polling = false
const previousTitle = document.title
const parameters = () => ({ probe: probe.value, participant: participant.value })
const message = failure => failure.response?.data?.message || 'Der Probelauf konnte nicht geladen werden. Er wurde möglicherweise entfernt oder ist nur lokal im berechtigten Lehrkraftkonto verfügbar.'
async function load(background = false) {
    const ticket = generation
    if (!background) { loading.value = true; error.value = '' }
    try { const { data } = await axios.get(participant.value ? player.url(parameters()) : show.url(probe.value)); if (!disposed && ticket === generation) { info.value = data; error.value = '' } }
    catch (failure) { if (ticket === generation) { error.value = message(failure); if (failure.response?.status === 404) { info.value = null } } }
    finally { if (ticket === generation) { loading.value = false } }
}
async function join() {
    if (busy.value) { return }
    ++generation
    busy.value = true; error.value = ''
    try { const { data } = await axios.post(joinProbe.url(parameters()), { code: code.value, team_name: teamChoice.value === 'new' ? teamName.value : null, team_code: teamChoice.value === 'existing' ? teamCode.value : null }); info.value.run = data.run }
    catch (failure) { error.value = message(failure) } finally { busy.value = false }
}
async function control(action) {
    if (busy.value) { return }
    ++generation
    busy.value = true; error.value = ''
    try { const { data } = await axios.post(controlProbe.url(probe.value), { action }); info.value = data }
    catch (failure) { error.value = message(failure) } finally { busy.value = false }
}
async function remove() {
    ++generation
    busy.value = true
    try { await axios.delete(removeProbe.url(probe.value)); await router.push('/admin/teaching-tools/games/number-systems') }
    catch (failure) { error.value = message(failure) } finally { busy.value = false }
}
function teacherView() { void router.push({ path: route.path }) }
watch(() => [probe.value, route.query.participant, route.query.code, route.query.team_code], () => {
    ++generation; info.value = null; error.value = ''
    document.title = `Signal Null · ${participant.value ? (participant.value === 1 ? 'Testperson A' : 'Testperson B') : 'Lehrkraft'} · Probe ${probe.value.slice(0, 8)}`
    code.value = typeof route.query.code === 'string' ? route.query.code : ''
    teamCode.value = typeof route.query.team_code === 'string' ? route.query.team_code : ''
    teamChoice.value = teamCode.value ? 'existing' : 'new'; teamName.value = 'Testteam'
    void load()
}, { immediate: true })
const poll = setInterval(async () => { if (!document.hidden && !polling && !busy.value && !loading.value && !(participant.value && info.value?.run)) { polling = true; try { await load(true) } finally { polling = false } } }, 3000)
onBeforeUnmount(() => { disposed = true; ++generation; clearInterval(poll); document.title = previousTitle })
</script>
