<template>
    <main class="ns-student-page">
        <StudentNavigationDrawer v-if="!guest" v-model="drawer" current-route="number-systems" />
        <div v-if="!guest" class="ns-student-toolbar"><RouterLink to="/student/overview">Meine Fächer</RouterLink><button class="ns-student-menu" @click="drawer = true">Mein Menü</button></div>
        <NumberSystemPlayer v-if="runId" :key="runId" :run-id="runId" @back="back" />
        <div v-else class="ns-game">
            <header><h1>Signal Null</h1><p class="ns-muted" style="margin-top:12px">Rette das Rechenzentrum: Rechne Zahlen um, finde Fehler und entziffere Nachrichten. Für jeden geschafften Bereich sammelst du ein Abzeichen.</p></header>
            <div v-if="error" class="ns-alert ns-alert-error" role="alert">{{ error }} <RouterLink :to="loginDestination">Zur Schüleranmeldung</RouterLink></div>
            <section class="ns-panel" style="margin-top:24px">
                <h2>Einer Klassenrunde beitreten</h2>
                <form v-if="!found" class="ns-toolbar" style="align-items:end;margin-top:16px" @submit.prevent="findSession">
                    <label class="ns-field" style="flex:1"><span>Kurscode von deiner Lehrkraft</span><input v-model="code" required maxlength="20" autocapitalize="characters" autocomplete="off" :disabled="busy" /></label>
                    <button class="ns-button" :disabled="busy || !code.trim()">Runde finden</button>
                </form>
                <form v-else class="ns-stack" style="margin-top:16px" @submit.prevent="joinSession">
                    <h3>{{ found.title }}</h3>
                    <template v-if="found.guest"><label class="ns-field"><span>Dein Anzeigename</span><input v-model="displayName" required maxlength="60" autocomplete="nickname" /></label><p class="ns-small">Du spielst als externe Person ohne Schooltool-Konto. Zum Fortsetzen denselben Browser verwenden und die Browser-Sitzung beibehalten. Jede Person im Team braucht einen eigenen Browser oder ein eigenes Gerät.</p></template>
                    <p v-if="found.status === 'ended'" class="ns-muted">Diese Runde ist beendet. Deinen gespeicherten Fortschritt kannst du unten weiter ansehen.</p>
                    <template v-if="found.mode === 'teams'">
                        <p>{{ found.guest ? 'Jedes Teammitglied tritt mit einem eigenen Gastzugang bei.' : 'Jedes Teammitglied braucht den Kurscode und einen persönlichen Schülerzugang.' }} Eine Person gründet das Team und gibt dessen privaten Teamcode weiter.</p>
                        <div class="ns-form"><label class="ns-choice"><input v-model="teamChoice" type="radio" value="new" />Neues Team gründen</label><label class="ns-choice"><input v-model="teamChoice" type="radio" value="existing" />Zu meinem Team</label></div>
                        <label v-if="teamChoice === 'new'" class="ns-field"><span>Neuer Teamname</span><input v-model="teamName" required maxlength="60" /></label>
                        <label v-else class="ns-field"><span>Privater Teamcode aus deiner Gruppe</span><input v-model="teamCode" required maxlength="12" autocapitalize="characters" autocomplete="off" /></label>
                    </template>
                    <p v-else>Du hast einen eigenen Spielstand. Wenn ihr zu zweit an einem Gerät spielt, könnt ihr gemeinsam überlegen.</p>
                    <div class="ns-toolbar"><button type="button" class="ns-button ns-button-quiet" @click="found = null">Anderer Kurscode</button><button class="ns-button" :disabled="busy || found.status === 'ended' || (found.guest && !displayName.trim())">{{ busy ? 'Verbindung wird hergestellt …' : 'Abenteuer beitreten' }}</button></div>
                </form>
            </section>
            <section style="margin-top:28px">
                <h2>Meine gespeicherten Abenteuer</h2><p class="ns-small ns-muted" style="margin-top:8px">Fortsetzen lädt dieselben Rätsel und deinen gespeicherten Fortschritt. Ein neues Abenteuer startet deine Lehrkraft mit einem neuen Kurscode.</p>
                <p v-if="loading" style="margin-top:12px" role="status">Abenteuer werden geladen …</p>
                <p v-else-if="!runs.length" class="ns-muted" style="margin-top:16px">Du bist noch keiner Runde beigetreten.</p>
                <ul class="ns-session-list"><li v-for="run in runs" :key="run.id" class="ns-session-row"><div><h3>{{ run.title }}</h3><p class="ns-small ns-muted">{{ run.team_name || 'Dein Spielstand' }} · {{ run.solved }} / {{ run.total }} Aufgaben gelöst</p></div><button class="ns-button ns-button-secondary" @click="open(run.id)">{{ run.completed || run.status === 'ended' ? 'Fortschritt ansehen' : 'Fortsetzen' }}</button></li></ul>
            </section>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { index, lookup, join } from '@/actions/App/Http/Controllers/NumberSystemPlayerController'
import { entry } from '@/routes/number-systems'
import StudentNavigationDrawer from '../components/StudentNavigationDrawer.vue'
import NumberSystemPlayer from '@/pages/components/numberSystems/NumberSystemPlayer.vue'
import '@/pages/components/numberSystems/game.css'
const route = useRoute(), router = useRouter()
const runId = computed(() => route.params.run || null)
const drawer = ref(false), runs = ref([]), error = ref(''), loading = ref(false), busy = ref(false), code = ref(String(route.query.code ?? '')), found = ref(null)
const teamCode = ref(typeof route.query.team_code === 'string' ? route.query.team_code : '')
const teamChoice = ref(teamCode.value ? 'existing' : 'new'), teamName = ref('')
const guest = ref(true), displayName = ref('')
let invitationGeneration = 0
const loginDestination = computed(() => ({ path: '/student', query: { number_systems_code: code.value, ...(teamCode.value ? { number_systems_team: teamCode.value } : {}) } }))
function handleFailure(failure) {
    error.value = failureMessage(failure)
    if (failure.response?.status === 401 && route.path === entry.url()) { void router.replace(loginDestination.value) }
}
const failureMessage = failure => failure.response?.status === 404 ? 'Diese Runde oder dieser Teamcode wurde für deinen Kurs nicht gefunden. Prüfe den Code mit deiner Lehrkraft.' : failure.response?.data?.message || 'Die Verbindung ist unterbrochen. Bitte versuche es erneut.'
async function load() {
    loading.value = true
    try { const { data } = await axios.get(index.url()); runs.value = data.runs; guest.value = data.guest === true }
    catch (failure) { handleFailure(failure) } finally { loading.value = false }
}
async function findSession() {
    if (busy.value) { return }
    const generation = ++invitationGeneration
    busy.value = true; error.value = ''
    try { const { data } = await axios.post(lookup.url(), { code: code.value }); if (generation === invitationGeneration) { found.value = data.session; guest.value = data.session.guest === true } }
    catch (failure) { if (generation === invitationGeneration) { handleFailure(failure) } } finally { if (generation === invitationGeneration) { busy.value = false } }
}
async function joinSession() {
    if (busy.value) { return }
    const generation = invitationGeneration
    busy.value = true; error.value = ''
    try { const { data } = await axios.post(join.url(), { code: code.value,
        ...(found.value.guest ? { display_name: displayName.value.trim() } : {}),
        team_name: found.value.mode === 'teams' && teamChoice.value === 'new' ? teamName.value : null,
        team_code: found.value.mode === 'teams' && teamChoice.value === 'existing' ? teamCode.value : null }); if (generation === invitationGeneration) { await open(data.run.id) } }
    catch (failure) { if (generation === invitationGeneration) { handleFailure(failure) } } finally { if (generation === invitationGeneration) { busy.value = false } }
}
async function open(id) { await router.push(entry.url({ run: id })) }
async function back() { await router.push(entry.url()); await load() }
watch(() => [route.path, route.query.code, route.query.team_code], () => {
    if (route.path !== entry.url()) { return }
    ++invitationGeneration
    code.value = typeof route.query.code === 'string' ? route.query.code.trim().toUpperCase() : ''
    teamCode.value = typeof route.query.team_code === 'string' ? route.query.team_code.trim().toUpperCase() : ''
    teamChoice.value = teamCode.value ? 'existing' : 'new'
    teamName.value = ''; found.value = null; error.value = ''; busy.value = false
    if (!runId.value && code.value) { void findSession() }
}, { immediate: true })
onMounted(() => { void load() })
</script>

<style scoped>
.ns-student-page { max-width: 1360px; margin: 0 auto; padding: 18px; }
.ns-student-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; }
.ns-student-toolbar a { color: #3446af; text-underline-offset: 4px; }
.ns-student-menu { border: 1px solid #bfc8da; color: #3446af; border-radius: 8px; padding: 10px 16px; min-height: 44px; }
@media (max-width: 600px) { .ns-student-page { padding: 10px; } }
</style>
