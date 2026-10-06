<template>
    <div class="zero-manager">
        <header class="zero-hero">
            <div><div class="zero-eyebrow">MATURA · GEMEINSAM KOORDINIERT</div><h1>00-Manager<span class="zero-hero-dot">.</span></h1><p>Ruhiger Ablauf. Klare Wege. Alles im Blick.</p></div>
            <div class="zero-live" :class="{ 'zero-live--offline': stale }"><span></span>{{ state ? (stale ? 'Verbindung unterbrochen' : 'Gemeinsamer Stand') : 'Bereit für die Prüfung' }}<small v-if="state">Aktualisiert {{ time(lastUpdated) }}</small></div>
        </header>
        <div v-if="error" class="zero-error" role="alert">{{ error }} <button class="zero-link" @click="refresh">Erneut laden</button></div>
        <div v-if="!ready" class="zero-empty"><h2>Der 00-Manager ist vorbereitet.</h2><p>Die einmalige Freischaltung durch die Administration steht noch aus.</p></div>
        <template v-else>
            <div v-if="!setupOpen" class="zero-toolbar">
                <label class="zero-grow">Matura auswählen<select v-model="selectedId"><option :value="null">Alle Maturen</option><option v-for="session in sessions" :key="session.id" :value="session.id">{{ session.name }} · {{ date(session.exam_date) }} · {{ lifecycleLabels[session.status] }}</option></select></label>
                <button class="zero-button" :disabled="!schoolyearId || busy" @click="newSession">+ Neue Matura</button>
                <button v-if="state" class="zero-button zero-button--secondary" @click="selectedId = null">Übersicht</button>
            </div>
            <p v-if="loading && !state" class="zero-loading" role="status">Koordination wird geladen …</p>
            <p v-if="setupOpen" class="zero-help mb-3" role="status">{{ draftWarning || 'Deine Eingaben bleiben in diesem Browser gespeichert. Abbrechen behält den Entwurf.' }}</p>
            <MaturaSetup v-if="setupOpen" :key="setupKey || setupYear" :roster="roster.students" :schoolyear-id="setupYear" :existing="editing ? state : null" :busy="busy" :draft="setupDraft" @draft="persistDraft" @save="saveSetup" @cancel="cancelSetup" />
            <template v-else-if="state">
                <div class="zero-section-title"><div><h2>{{ state.session.name }}</h2><p class="zero-help">{{ date(state.session.exam_date) }} · {{ lifecycleLabels[state.session.status] }}</p></div><span class="zero-badge">{{ state.actor.manager ? 'Leitung' : state.actor.name }}</span></div>
                <nav class="zero-tabs" aria-label="00-Manager Ansichten"><button :class="{ active: tab === 'live' }" @click="tab = 'live'">Koordination</button><button v-if="state.actor.manager" :class="{ active: tab === 'access' }" @click="openAccess">Aufsichten & Einrichtung</button><button v-if="state.actor.manager" :class="{ active: tab === 'report' }" @click="tab = 'report'">Protokoll & PDF</button></nav>
                <MaturaBoard v-if="tab === 'live'" :key="state.session.id" :state="state" :disabled="busy || stale" @action="runAction" />
                <MaturaReport v-if="tab === 'report'" :key="state.session.id" :state="state" :busy="busy" @action="runAction" />
                <section v-if="tab === 'access'" class="zero-panel">
                    <div class="zero-section-title"><div><span class="zero-eyebrow">PRÜFUNGSLEITUNG</span><h2>Einrichtung & Betrieb</h2></div><button v-if="state.session.status === 'draft' && !state.accesses.length" class="zero-link" @click="editSetup">Räume und Schüler bearbeiten</button></div>
                    <p class="zero-help mb-4">Nach Vergabe der Zugänge bleiben die Räume fest zugeordnet. Zugänge sind persönlich, auf eine Station begrenzt und jederzeit widerrufbar.</p>
                    <div class="zero-actions">
                        <label>Warteplätze<input v-model.number="waitingPlaces" type="number" min="0" max="10"></label>
                        <button v-if="state.session.status !== 'closed'" class="zero-button" :disabled="busy" @click="changeLifecycle('active')">{{ state.session.status === 'draft' ? 'Matura starten' : 'Kapazität speichern' }}</button>
                        <button v-if="state.session.status === 'active'" class="zero-button zero-button--secondary" :disabled="busy || state.visits.length > 0" @click="closeDialog = true">Matura abschließen</button>
                    </div>
                    <p v-if="state.visits.length" class="zero-help mt-2">Abschluss möglich, sobald alle offenen Gänge beendet sind.</p>
                    <template v-if="state.session.status !== 'closed'">
                        <hr class="zero-divider"><h2>Aufsicht zuordnen</h2>
                        <form class="zero-form-grid mt-4" @submit.prevent="inviteAccess">
                            <label>Station<select v-model="invitation.room_id"><option :value="null">Zwischenstation / Toilette</option><option v-for="room in state.rooms" :key="room.id" :value="room.id">{{ room.name }}</option></select></label>
                            <label>Bestehendes Konto<select v-model="invitation.user_id"><option :value="null">Gast ohne Lehrerkonto</option><option v-for="person in roster.supervisors" :key="person.id" :value="person.id">{{ person.name }}</option></select></label>
                            <label v-if="!invitation.user_id">Name der Aufsicht<input v-model="invitation.name" required maxlength="180" placeholder="Vorname Nachname"></label>
                            <label>Gültig ab jetzt (Stunden)<input v-model.number="invitation.hours" type="number" min="1" max="72" required></label>
                            <div class="zero-actions"><button class="zero-button" :disabled="busy">Zugang erstellen</button></div>
                        </form>
                    </template>
                    <div v-if="invitationUrl" class="zero-share" role="status"><h3>Persönlicher Stationszugang</h3><p>Diesen Link an die zugeordnete Aufsicht weitergeben. Er wird nur jetzt angezeigt.</p><input :value="invitationUrl" readonly aria-label="Persönlicher Zugangslink" @focus="$event.target.select()"><div class="zero-actions mt-3"><button class="zero-button" @click="copyLink">{{ copied ? 'Kopiert ✓' : 'Link kopieren' }}</button><a class="zero-link" :href="invitationUrl" target="_blank" rel="noreferrer">Zugang öffnen</a></div><small>Am Handy eine im Schulnetz erreichbare Adresse verwenden. localhost ist nur auf diesem Computer erreichbar.</small></div>
                    <h3 class="mt-6 mb-3">Zugeordnete Aufsichten</h3>
                    <div v-if="!state.accesses.length" class="zero-help">Noch keine Aufsichten zugeordnet.</div>
                    <article v-for="access in state.accesses" :key="access.id" class="zero-access-row"><div><strong>{{ access.name }}</strong><small>{{ roomName(access.matura_room_id) }} · {{ access.user_id ? 'Schulkonto' : 'Gastzugang' }} · gültig bis {{ time(access.expires_at, true) }}</small></div><span class="zero-badge">{{ access.valid ? 'Gültig' : 'Abgelaufen / widerrufen' }}</span><button v-if="access.valid" class="zero-link" :disabled="busy" @click="revokeAccess(access.id)">Widerrufen</button></article>
                </section>
            </template>
            <div v-else-if="!loading && !setupOpen" class="zero-session-grid">
                <button v-for="session in sessions" :key="session.id" class="zero-session-card" @click="selectedId = session.id"><span class="zero-eyebrow">{{ date(session.exam_date) }}</span><h2>{{ session.name }}</h2><p>{{ session.rooms_count }} Räume · {{ session.students_count }} Schüler</p><div><span class="zero-badge">{{ lifecycleLabels[session.status] }}</span><span aria-hidden="true">↗</span></div></button>
                <div v-if="!sessions.length" class="zero-empty"><span class="zero-empty-symbol">00</span><h2>Die nächste Matura kann kommen.</h2><p>Räume, Schüler und Aufsichten einmal einrichten — danach gemeinsam koordinieren.</p><button class="zero-button mt-4" :disabled="!schoolyearId" @click="newSession">Erste Matura einrichten</button><p v-if="!schoolyearId" class="zero-help mt-3">Bitte zuerst ein Schuljahr auswählen.</p></div>
            </div>
        </template>
        <v-dialog v-model="closeDialog" max-width="480"><v-card class="pa-5"><h2>Matura abschließen?</h2><p class="my-4">Alle Stationszugänge werden beendet. Das Protokoll und die PDF-Auswertung bleiben für die Leitung verfügbar.</p><div class="zero-actions"><button class="zero-button zero-button--secondary" @click="closeDialog = false">Abbrechen</button><button class="zero-button" :disabled="busy" @click="changeLifecycle('closed')">Abschließen</button></div></v-card></v-dialog>
    </div>
</template>
<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { index, roster as rosterRoute, show, store, update, action, lifecycle, invite, revoke } from '@/actions/App/Http/Controllers/Admin/MaturaController'
import MaturaBoard from './MaturaBoard.vue'
import MaturaSetup from './MaturaSetup.vue'
import MaturaReport from './MaturaReport.vue'
import { errorMessage, operationKey, time } from './maturaFormat'
import { setupDraftScope, setupDraftKey, readSetupDraft, readActiveSetup, writeSetupDraft, removeSetupDraft } from './maturaSetupDraft'
import '../../../../../css/matura-manager.css'
const adminStore = useAdminStore()
const route = useRoute()
const router = useRouter()
const schoolyearId = computed(() => Number((adminStore.selected_schoolyear ?? adminStore.config?.selected_schoolyear)?.id) || null)
const draftScope = computed(() => setupDraftScope(adminStore.config?.user?.id, (adminStore.selected_school ?? adminStore.config?.selected_school)?.id ?? adminStore.config?.user?.school_id, schoolyearId.value))
const selectedId = ref(Number(route.query.matura) || null)
const sessions = ref([])
const state = ref(null)
const roster = ref({ students: [], supervisors: [] })
const ready = ref(true)
const loading = ref(false)
const busy = ref(false)
const stale = ref(false)
const error = ref('')
const lastUpdated = ref(null)
const tab = ref('live')
const setupOpen = ref(false)
const editing = ref(false)
const setupYear = ref(null)
const setupKey = ref(null)
const setupDraft = ref(null)
const draftWarning = ref('')
const waitingPlaces = ref(1)
const invitation = reactive({ room_id: null, user_id: null, name: '', hours: 24 })
const invitationUrl = ref('')
const copied = ref(false)
const closeDialog = ref(false)
const lifecycleLabels = { draft: 'Vorbereitung', active: 'Läuft', closed: 'Abgeschlossen' }
let timer
let destroyed = false
let loadingState = null
let contextVersion = 0
let listedYear = null
function date(value) { return new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value)) }
function roomName(id) { return id === null ? 'Zwischenstation' : state.value.rooms.find((room) => room.id === id)?.name }
function isCurrent(version) { return version === contextVersion && !destroyed }
function isListed(id) { return schoolyearId.value && listedYear === schoolyearId.value && sessions.value.some((session) => Number(session.id) === Number(id)) }
function resetDetails() {
    state.value = null
    roster.value = { students: [], supervisors: [] }
    tab.value = 'live'
    setupOpen.value = false
    editing.value = false
    setupYear.value = null
    setupKey.value = null
    setupDraft.value = null
    draftWarning.value = ''
    closeDialog.value = false
    waitingPlaces.value = 1
    Object.assign(invitation, { room_id: null, user_id: null, name: '', hours: 24 })
    invitationUrl.value = ''
    copied.value = false
    stale.value = false
    lastUpdated.value = null
    error.value = ''
}
async function list() {
    const year = schoolyearId.value
    const version = contextVersion
    if (!year) { sessions.value = []; listedYear = null; selectedId.value = null; return }
    const response = await axios.get(index.url({ query: { schoolyear_id: year } }))
    if (!isCurrent(version)) return
    sessions.value = response.data.sessions
    ready.value = response.data.ready
    listedYear = year
    if (selectedId.value && !isListed(selectedId.value)) selectedId.value = null
}
async function loadState() {
    if (!selectedId.value || !isListed(selectedId.value)) return
    const id = selectedId.value
    const version = contextVersion
    if (loadingState?.id === id && loadingState?.version === version) return
    const request = { id, version }
    loadingState = request
    try {
        const response = await axios.get(show.url(id), { timeout: 12000 })
        if (id === selectedId.value && isCurrent(version)) { if (stale.value) error.value = ''; state.value = response.data; stale.value = false; lastUpdated.value = response.data.server_time }
    } catch (failure) { if (id === selectedId.value && isCurrent(version)) { stale.value = true; error.value = errorMessage(failure); if ([401, 403, 404].includes(failure.response?.status)) state.value = null } }
    finally { if (loadingState === request) loadingState = null }
}
async function refresh() {
    const version = contextVersion
    error.value = ''
    loading.value = Boolean(schoolyearId.value)
    try { await list(); if (isCurrent(version)) await loadState(); if (isCurrent(version) && !setupOpen.value) await resumeSetup() }
    catch (failure) { if (isCurrent(version)) { sessions.value = []; listedYear = null; resetDetails(); error.value = errorMessage(failure) } }
    finally { if (isCurrent(version)) loading.value = false }
}
async function loadRoster(year) {
    const version = contextVersion
    const response = await axios.get(rosterRoute.url(), { params: { schoolyear_id: year } })
    if (!isCurrent(version)) return false
    roster.value = response.data
    return true
}
function openSetup(isEditing) {
    editing.value = isEditing
    setupYear.value = schoolyearId.value
    setupKey.value = setupDraftKey(draftScope.value, isEditing ? selectedId.value : null)
    setupDraft.value = readSetupDraft(setupKey.value)
    draftWarning.value = setupKey.value ? '' : 'Der Entwurf kann noch nicht lokal gesichert werden. Bitte vor dem Neuladen speichern.'
    setupOpen.value = true
}
function persistDraft(data) {
    if (!setupOpen.value) return
    const key = setupDraftKey(draftScope.value, editing.value ? selectedId.value : null)
    if (key !== setupKey.value) return
    const draftStored = writeSetupDraft(key, data)
    const activeStored = writeSetupDraft(draftScope.value ? `${draftScope.value}:active` : null, { sessionId: editing.value ? selectedId.value : null })
    const stored = draftStored && activeStored
    draftWarning.value = stored ? '' : 'Der Browser kann den Entwurf nicht sichern. Bitte vor dem Neuladen speichern.'
}
function cancelSetup() {
    removeSetupDraft(draftScope.value ? `${draftScope.value}:active` : null)
    setupOpen.value = false
}
async function resumeSetup() {
    if (!ready.value || !draftScope.value) return
    const active = readActiveSetup(draftScope.value)
    if (!active) return
    if (active.sessionId === null) { await newSession(); return }
    const version = contextVersion
    const session = sessions.value.find((item) => Number(item.id) === active.sessionId)
    if (!session || session.status !== 'draft' || !session.can_manage) return
    let detail = state.value
    if (detail?.session.id !== active.sessionId) {
        try { detail = (await axios.get(show.url(active.sessionId), { timeout: 12000 })).data }
        catch (failure) {
            if (isCurrent(version)) {
                if ([403, 404].includes(failure.response?.status)) removeSetupDraft(`${draftScope.value}:active`)
                else error.value = errorMessage(failure)
            }
            return
        }
    }
    if (!isCurrent(version) || !detail.actor.manager || detail.session.status !== 'draft' || detail.accesses.length || Number(detail.session.schoolyear_id) !== schoolyearId.value) return
    selectedId.value = active.sessionId
    await nextTick()
    if (!isCurrent(version)) return
    state.value = detail
    await editSetup()
}
async function newSession() {
    const year = schoolyearId.value
    const version = contextVersion
    if (!year) return
    error.value = ''
    try { if (!await loadRoster(year)) return; openSetup(false) }
    catch (failure) { if (isCurrent(version)) error.value = errorMessage(failure) }
}
async function editSetup() {
    const version = contextVersion
    const year = schoolyearId.value
    try { if (!await loadRoster(year)) return; openSetup(true) }
    catch (failure) { if (isCurrent(version)) error.value = errorMessage(failure) }
}
async function mutate(callback) {
    if (busy.value || !schoolyearId.value) return
    const version = contextVersion
    busy.value = true
    error.value = ''
    try { await callback(() => isCurrent(version)) } catch (failure) { if (isCurrent(version)) error.value = errorMessage(failure) }
    finally { if (isCurrent(version)) { await loadState(); if (isCurrent(version)) busy.value = false } }
}
async function saveSetup(data) {
    const key = setupKey.value
    const scope = draftScope.value
    const sessionId = editing.value ? selectedId.value : null
    const savedDraft = JSON.stringify(readSetupDraft(key))
    await mutate(async (current) => {
        const response = sessionId ? await axios.put(update.url(sessionId), data) : await axios.post(store.url(), data)
        if (JSON.stringify(readSetupDraft(key)) === savedDraft) {
            if (readActiveSetup(scope)?.sessionId === sessionId) removeSetupDraft(`${scope}:active`)
            removeSetupDraft(key)
        }
        if (!current()) return
        setupOpen.value = false
        await list()
        if (current()) selectedId.value = response.data.id
    })
}
async function runAction(data) { await mutate(async () => { await axios.post(action.url(selectedId.value), { ...data, operation_key: operationKey() }) }) }
async function changeLifecycle(status) { await mutate(async (current) => { await axios.put(lifecycle.url(selectedId.value), { status, waiting_places: waitingPlaces.value }); if (!current()) return; closeDialog.value = false; await list() }) }
async function openAccess() { const version = contextVersion; tab.value = 'access'; waitingPlaces.value = state.value.session.waiting_places; try { await loadRoster(schoolyearId.value) } catch (failure) { if (isCurrent(version)) error.value = errorMessage(failure) } }
async function inviteAccess() { await mutate(async (current) => { const response = await axios.post(invite.url(selectedId.value), { ...invitation }); if (!current()) return; invitationUrl.value = response.data.url || ''; copied.value = false; invitation.name = '' }) }
async function revokeAccess(id) { await mutate(async () => { await axios.delete(revoke.url({ matura: selectedId.value, access: id })) }) }
async function copyLink() { const version = contextVersion; try { await navigator.clipboard.writeText(invitationUrl.value); if (isCurrent(version)) copied.value = true } catch { if (isCurrent(version)) error.value = 'Bitte den Link im Feld markieren und kopieren.' } }
watch(selectedId, async (id) => { resetDetails(); await router.replace({ query: { ...route.query, matura: id || undefined } }); await loadState(); if (state.value) waitingPlaces.value = state.value.session.waiting_places })
watch(() => route.query.matura, (id) => {
    const value = Number(id) || null
    selectedId.value = value && isListed(value) ? value : null
    if (value && !selectedId.value) void router.replace({ query: { ...route.query, matura: undefined } })
})
watch(() => `${schoolyearId.value}:${draftScope.value}`, () => {
    contextVersion++
    sessions.value = []
    listedYear = null
    selectedId.value = null
    busy.value = false
    resetDetails()
    void router.replace({ query: { ...route.query, matura: undefined } })
    void refresh()
}, { flush: 'sync' })
async function poll() { if (destroyed) return; if (!document.hidden && !busy.value) await loadState(); timer = setTimeout(poll, 3000) }
onMounted(async () => { await refresh(); poll() })
onBeforeUnmount(() => { destroyed = true; clearTimeout(timer) })
</script>
