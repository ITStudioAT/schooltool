<template>
    <tr class="course-games-row">
        <th scope="row" class="course-games-label">
            <span>Spiele</span>
            <div v-if="loading" class="text-caption" role="status">Wird geladen …</div>
            <button v-else-if="error" type="button" class="course-game-link" @click="load">Spiele erneut laden</button>
            <button v-else type="button" class="course-game-link" @click="showAll">Alle Runden ({{ sessions.length }})</button>
            <v-dialog v-model="dialogOpen" :persistent="saving" scrollable max-width="1200">
                <v-card>
                    <v-card-title>Spiele · {{ course.title }}<template v-if="selectedDate"> · {{ dateLabel(selectedDate) }}</template></v-card-title>
                    <v-card-text>
                        <p v-if="assignmentError" role="alert" class="text-error">{{ assignmentError }}</p>
                        <p v-if="saving" role="status">Spiel wird hinzugefügt …</p>
                        <TeachingGames v-if="selecting" selection :busy="saving" :assigned="gamesForDate(selectedDate)" @select="addGame" />
                        <NetworksGame v-else-if="selectedGame === 'networks'" />
                        <NumberSystemsGame v-else-if="selectedGame === 'number-systems'" :key="selectedDate.id" :course-id="Number(course.id)" :course-date-id="selectedDate.id" />
                        <template v-else-if="!selectedSessionId">
                            <p v-if="!sessions.length">Für diesen Kurs sind noch keine Spielrunden vorhanden.</p>
                            <ul v-else class="course-game-list">
                                <li v-for="session in orderedSessions" :key="session.id">
                                    <button type="button" class="course-game-card" @click="selectedSessionId = session.id">
                                        <span class="course-game-type">Zahlensysteme</span>
                                        <span class="course-game-title">{{ session.title }}</span>
                                        <span class="course-game-time">{{ numberSystemSessionDates(session) }}</span>
                                    </button>
                                    <p>{{ states[session.status] }} · {{ session.runs_count ?? 0 }} Spielstände</p>
                                </li>
                            </ul>
                        </template>
                        <NumberSystemsGame v-else-if="dialogOpen" :key="selectedSessionId" :session-id="selectedSessionId" />
                    </v-card-text>
                    <v-card-actions>
                        <v-btn v-if="selectedSessionId" variant="text" @click="selectedSessionId = null">Alle Runden</v-btn>
                        <v-spacer />
                        <v-btn variant="text" :disabled="saving" @click="dialogOpen = false">{{ selecting ? 'Abbrechen' : 'Schließen' }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </th>
        <td v-for="(courseDate, dateIndex) in dates" :key="courseDate.id || courseDate.date" class="course-games-cell">
            <button v-for="game in gamesForDate(courseDate)" :key="game" type="button" class="course-game-card"
                :aria-label="`${gameTitles[game]} für ${dateLabel(courseDate)} öffnen`" @click="showGame(game, courseDate)">
                <span class="course-game-type">{{ gameTitles[game] }}</span>
                <span class="course-game-title">Spiel öffnen</span>
            </button>
            <button v-for="session in sessionsForDate(courseDate, dateIndex)" :key="session.id" type="button" class="course-game-card" :aria-label="`Ergebnisse von ${session.title} ansehen`" @click="showSession(session)">
                <span class="course-game-type">Zahlensysteme</span>
                <span class="course-game-title">{{ session.title }}</span>
                <span class="course-game-time">{{ numberSystemSessionDates(session) }}</span>
            </button>
            <button v-if="courseDate.id" type="button" class="course-game-add" :aria-label="`Spiel am ${dateLabel(courseDate)} hinzufügen`"
                :title="`Spiel am ${dateLabel(courseDate)} hinzufügen`" @click="showSelection(courseDate)"><span aria-hidden="true">+</span></button>
        </td>
    </tr>
</template>

<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { index } from '@/actions/App/Http/Controllers/Admin/NumberSystemGameController'
import { addGame as assignGame } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseDateController'
import { applicationDate } from '@/helpers/date'
import { numberSystemSessionDates } from '@/pages/components/numberSystems/sessionDates'

const NumberSystemsGame = defineAsyncComponent(() => import('@/pages/admin/teachingTools/NumberSystemsGame.vue'))
const TeachingGames = defineAsyncComponent(() => import('@/pages/admin/teachingTools/TeachingGames.vue'))
const NetworksGame = defineAsyncComponent(() => import('@/pages/admin/teachingTools/NetworksGame.vue'))
const props = defineProps({ course: { type: Object, required: true }, dates: { type: Array, required: true } })
const emit = defineEmits(['games-updated'])
const gameTitles = { 'number-systems': 'Zahlensysteme', networks: 'Netzwerke' }
const selecting = ref(false), selectedDate = ref(null), selectedGame = ref(null), saving = ref(false), assignmentError = ref('')
const savedGames = ref({})
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
    if (!date) { return [] }
    return orderedSessions.value.filter(session => session.teaching_course_date_id
        ? Number(session.teaching_course_date_id) === Number(courseDate.id)
        : props.dates.findIndex(item => item.date?.slice(0, 10) === date) === dateIndex && session.started_at && Number.isFinite(Date.parse(session.started_at)) && applicationDate(new Date(session.started_at)) === date)
}
function gamesForDate(courseDate) { return (savedGames.value[courseDate?.id] ?? courseDate?.games ?? []).filter(game => gameTitles[game]) }
function dateLabel(courseDate) { return courseDate?.date?.slice(0, 10).split('-').reverse().join('.') ?? '' }
function resetSelection() { selecting.value = false; selectedDate.value = null; selectedGame.value = null; selectedSessionId.value = null; assignmentError.value = '' }
function showAll() { resetSelection(); dialogOpen.value = true }
function showSession(session) { resetSelection(); selectedSessionId.value = session.id; dialogOpen.value = true }
function showSelection(courseDate) { resetSelection(); selectedDate.value = courseDate; selecting.value = true; dialogOpen.value = true }
function showGame(game, courseDate) { resetSelection(); selectedDate.value = courseDate; selectedGame.value = game; dialogOpen.value = true }
async function addGame(game) {
    if (saving.value || !selectedDate.value || gamesForDate(selectedDate.value).includes(game)) { return }
    const courseId = props.course.id
    const dateId = selectedDate.value.id
    saving.value = true; assignmentError.value = ''
    try {
        const { data } = await axios.post(assignGame.url(dateId), { game })
        if (props.course.id !== courseId) { return }
        savedGames.value = { ...savedGames.value, [dateId]: data.games }
        emit('games-updated', { id: dateId, games: data.games })
        dialogOpen.value = false
    } catch (failure) {
        if (props.course.id === courseId) { assignmentError.value = failure.response?.data?.message || 'Das Spiel konnte nicht hinzugefügt werden. Bitte versuche es erneut.' }
    } finally { saving.value = false }
}
watch(() => props.course.id, () => { dialogOpen.value = false; resetSelection(); savedGames.value = {}; void load() }, { immediate: true })
watch(dialogOpen, (open, wasOpen) => { if (!open && wasOpen) { selectedSessionId.value = null; void load() } })
onBeforeUnmount(() => request?.abort())
</script>

<style scoped>
.course-games-label, .course-games-cell { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; text-align: left; font-size: 0.78rem; background: #f0f9ff; color: #243447; }
.course-games-label { position: sticky; left: 0; z-index: 2; }
.course-game-link { display: block; color: #075985; text-align: left; text-decoration: underline; overflow-wrap: anywhere; cursor: pointer; }
.course-game-link:focus-visible { outline: 2px solid #075985; outline-offset: 2px; }
.course-game-card { display: flex; flex-direction: column; gap: 4px; width: max-content; min-width: 112px; box-sizing: border-box; padding: 5px 6px; border: 1px solid rgba(37, 99, 235, 0.3); border-radius: 7px; background: rgba(37, 99, 235, 0.12); box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18); color: #1e3a8a; text-align: left; cursor: pointer; }
.course-game-card + .course-game-card { margin-top: 8px; }
.course-game-add { display: grid; place-items: center; width: 32px; height: 32px; margin-top: 6px; border: 1px dashed #7babc5; border-radius: 7px; color: #075985; font-size: 1.4rem; cursor: pointer; }
.course-game-add:hover, .course-game-add:focus-visible { background: #dbeefa; outline: 2px solid #2563eb; outline-offset: 1px; }
.course-game-card:hover, .course-game-card:focus-visible { background: rgba(37, 99, 235, 0.2); outline: 2px solid #2563eb; outline-offset: 1px; }
.course-game-type { font-size: 0.62rem; font-weight: 800; line-height: 1; }
.course-game-title { max-width: 100px; font-size: 0.7rem; font-weight: 400; line-height: 1.25; overflow-wrap: anywhere; }
.course-game-time { font-size: 0.55rem; font-weight: 400; line-height: 1.25; white-space: nowrap; font-variant-numeric: tabular-nums; }
.course-game-list { list-style: none; padding: 0; font-weight: 400; }
.course-game-list li + li { margin-top: 16px; }
</style>
