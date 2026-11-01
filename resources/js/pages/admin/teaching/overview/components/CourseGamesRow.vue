<template>
    <tr class="course-games-row">
        <th scope="row" class="course-games-label">
            <span>Spiele</span>
            <div v-if="loading" class="text-caption" role="status">Wird geladen …</div>
            <button v-else-if="error" type="button" class="course-game-link" @click="load">Spiele erneut laden</button>
            <button v-else type="button" class="course-game-link" @click="showAll">Alle Runden ({{ sessions.length }})</button>
            <v-dialog v-model="dialogOpen" scrollable max-width="1200">
                <v-card>
                    <v-card-title>Spiele · {{ course.title }}</v-card-title>
                    <v-card-text>
                        <template v-if="!selectedSessionId">
                            <p v-if="!sessions.length">Für diesen Kurs sind noch keine Spielrunden vorhanden.</p>
                            <ul v-else class="course-game-list">
                                <li v-for="session in orderedSessions" :key="session.id">
                                    <button type="button" class="course-game-link" @click="selectedSessionId = session.id">Signal Null · {{ session.title }}</button>
                                    <p>{{ numberSystemSessionDates(session) }}</p>
                                    <p>{{ states[session.status] }} · {{ session.runs_count ?? 0 }} Spielstände</p>
                                </li>
                            </ul>
                        </template>
                        <NumberSystemsGame v-else-if="dialogOpen" :key="selectedSessionId" :session-id="selectedSessionId" />
                    </v-card-text>
                    <v-card-actions>
                        <v-btn v-if="selectedSessionId" variant="text" @click="selectedSessionId = null">Alle Runden</v-btn>
                        <v-spacer />
                        <v-btn variant="text" @click="dialogOpen = false">Schließen</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </th>
        <td v-for="(courseDate, dateIndex) in dates" :key="courseDate.id || courseDate.date" class="course-games-cell">
            <div v-for="session in sessionsForDate(courseDate, dateIndex)" :key="session.id">
                <button type="button" class="course-game-link" :aria-label="`Ergebnisse von ${session.title} ansehen`" @click="showSession(session)">{{ session.title }}</button>
                <p class="text-caption">{{ numberSystemSessionDates(session) }}</p>
            </div>
        </td>
    </tr>
</template>

<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { index } from '@/actions/App/Http/Controllers/Admin/NumberSystemGameController'
import { applicationDate } from '@/helpers/date'
import { numberSystemSessionDates } from '@/pages/components/numberSystems/sessionDates'

const NumberSystemsGame = defineAsyncComponent(() => import('@/pages/admin/teachingTools/NumberSystemsGame.vue'))
const props = defineProps({ course: { type: Object, required: true }, dates: { type: Array, required: true } })
const states = { waiting: 'Vorbereitet', running: 'Läuft', paused: 'Pausiert', ended: 'Beendet' }
const sessions = ref([]), loading = ref(false), error = ref(false), dialogOpen = ref(false), selectedSessionId = ref(null)
let request = null
const orderedSessions = computed(() => [...sessions.value].sort((left, right) =>
    Date.parse(right.started_at || right.created_at || 0) - Date.parse(left.started_at || left.created_at || 0)))

async function load() {
    request?.abort()
    const controller = new AbortController()
    request = controller
    sessions.value = []; loading.value = true; error.value = false
    try {
        const { data } = await axios.get(index.url({ query: { course_id: props.course.id } }), { signal: controller.signal })
        if (!controller.signal.aborted) { sessions.value = (data.sessions ?? []).filter(session => Number(session.teaching_course_id) === Number(props.course.id)) }
    } catch {
        if (!controller.signal.aborted) { error.value = true }
    } finally {
        if (!controller.signal.aborted) { loading.value = false }
    }
}
function sessionsForDate(courseDate, dateIndex) {
    const date = courseDate.date?.slice(0, 10)
    if (!date || props.dates.findIndex(item => item.date?.slice(0, 10) === date) !== dateIndex) { return [] }
    return orderedSessions.value.filter(session => session.started_at && Number.isFinite(Date.parse(session.started_at)) && applicationDate(new Date(session.started_at)) === date)
}
function showAll() { selectedSessionId.value = null; dialogOpen.value = true }
function showSession(session) { selectedSessionId.value = session.id; dialogOpen.value = true }
watch(() => props.course.id, () => { dialogOpen.value = false; selectedSessionId.value = null; void load() }, { immediate: true })
watch(dialogOpen, (open, wasOpen) => { if (!open && wasOpen) { selectedSessionId.value = null; void load() } })
onBeforeUnmount(() => request?.abort())
</script>

<style scoped>
.course-games-label, .course-games-cell { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; text-align: left; font-size: 0.78rem; background: #f0f9ff; color: #243447; }
.course-games-label { position: sticky; left: 0; z-index: 2; }
.course-game-link { display: block; color: #075985; text-align: left; text-decoration: underline; overflow-wrap: anywhere; cursor: pointer; }
.course-game-link:focus-visible { outline: 2px solid #075985; outline-offset: 2px; }
.course-games-cell > div + div { margin-top: 8px; }
.course-game-list { list-style: none; padding: 0; font-weight: 400; }
.course-game-list li + li { margin-top: 16px; }
</style>
