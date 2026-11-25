<template>
    <section aria-label="Meine Aufgaben">
        <div class="task-list-heading">
            <h2>Meine Aufgaben</h2>
            <div class="task-heading-actions">
                <span v-if="!loading && !error && tasks.length" class="task-count">{{ tasks.length }} {{ tasks.length === 1 ? 'Aufgabe' : 'Aufgaben' }}</span>
                <v-btn variant="tonal" :disabled="loading || manualReloading || !active"
                    :aria-busy="manualReloading" @click="reloadTasks">
                    <template #prepend>
                        <v-icon icon="mdi-refresh" aria-hidden="true" :class="{ 'task-refresh-spinning': manualReloading }" />
                    </template>
                    {{ manualReloading ? 'Wird aktualisiert …' : 'Aktualisieren' }}
                </v-btn>
            </div>
        </div>
        <v-progress-linear v-if="loading" indeterminate aria-label="Aufgaben werden geladen" />
        <v-alert v-else-if="error" type="error" variant="tonal" role="alert">{{ error }}</v-alert>
        <div v-else-if="!tasks.length" class="task-empty">
            <v-icon size="26" aria-hidden="true">mdi-clipboard-text-outline</v-icon>
            <div><strong>Noch keine Aufgaben</strong><p>Hier findest du die Aufgaben, die deine Lehrkraft für dich bereitstellt.</p></div>
        </div>
        <template v-else>
            <div class="task-list">
                <article v-for="task in tasks" :key="task.id" class="task-card">
                    <header class="task-card-header">
                        <div class="task-title-group">
                            <span class="task-title-icon"><v-icon size="24" aria-hidden="true">mdi-clipboard-text-outline</v-icon></span>
                            <div class="task-title-text"><span>{{ task.has_assessment ? 'Leistungsfeststellung' : 'Aufgabe' }}</span><h3>{{ task.title || 'Leistungsfeststellung' }}</h3></div>
                        </div>
                        <span v-if="pendingTaskLabels[task.status]" class="task-status">{{ pendingTaskLabels[task.status] }}</span>
                        <span v-else-if="task.status === 'ended'" class="task-status task-status--ended">Freigabe beendet</span>
                        <span v-else class="task-status task-status--released">{{ task.status === 'email_delivery' ? 'Per E-Mail bereitgestellt' : 'Freigegeben' }}</span>
                    </header>
                    <dl class="task-announcement-details">
                        <div v-if="task.dates?.length">
                            <dt>Durchführung</dt>
                            <dd>{{ task.dates.map(dateLabel).join(' · ') }}</dd>
                        </div>
                        <div v-if="task.assignment_kind || task.has_multiple_choice">
                            <dt>Bestandteile</dt>
                            <dd v-if="task.assignment_kind">
                                {{ task.assignment_kind === 'worksheet' ? 'Aufgabenblatt' : 'Arbeitsauftrag' }}<span v-if="task.assignment_points != null"> · {{ pointsLabel(task.assignment_points) }}</span>
                            </dd>
                            <dd v-if="task.has_multiple_choice">
                                Multiple-Choice-Test<span v-if="task.mc_points != null"> · {{ pointsLabel(task.mc_points) }}</span>
                            </dd>
                        </div>
                        <div v-if="task.assessment_points != null">
                            <dt>Erreichbare Prüfungspunkte</dt>
                            <dd>{{ pointsLabel(task.assessment_points) }}</dd>
                        </div>
                        <div v-if="task.grading_points != null">
                            <dt>Wert der Arbeit in Schooltool</dt>
                            <dd>{{ pointsLabel(task.grading_points) }}</dd>
                        </div>
                        <div v-if="task.deadline?.date">
                            <dt>{{ task.submission_deadline_at ? 'Reguläre Abgabefrist' : 'Endgültige Abgabefrist' }}</dt>
                            <dd>{{ dateLabel(task.deadline.date) }}<template v-if="task.deadline.time"> · {{ task.deadline.time }} Uhr</template></dd>
                            <dd v-if="deadlineExpired(task.deadline)" class="task-deadline-expired">Abgelaufen</dd>
                        </div>
                    </dl>
                    <div v-if="mustOpenTask(task)" class="task-materials">
                        <v-btn min-height="44" variant="tonal" prepend-icon="mdi-file-document-outline"
                            :loading="openingTaskId === task.id" :disabled="openingTaskId !== null" @click="openTask(task)">
                            Aufgabenstellung öffnen
                        </v-btn>
                        <v-alert v-if="openTaskError?.id === task.id" type="error" variant="tonal" class="mt-3" role="alert">{{ openTaskError.message }}</v-alert>
                    </div>
                    <div v-if="!pendingTaskLabels[task.status] && !mustOpenTask(task) && task.instructions" class="task-description">
                        <h4>{{ task.status === 'ended' ? 'Aufgabentext' : 'Das ist zu tun' }}</h4>
                        <p class="task-instructions">{{ task.instructions }}</p>
                    </div>
                    <div v-if="!pendingTaskLabels[task.status] && (task.files?.length || task.tests?.length)" class="task-materials">
                        <h4>{{ task.files?.length && task.tests?.length ? 'Unterlagen & Test' : task.tests?.length ? 'MC-Test' : 'Unterlagen' }}</h4>
                        <div class="task-actions">
                            <v-btn v-for="file in mustOpenTask(task) ? [] : task.files" :key="file.id" variant="outlined" class="task-action task-action--file" @click="openPdf(task, file)">
                                <v-icon size="22" aria-hidden="true">mdi-file-pdf-box</v-icon>
                                <span class="task-action-text"><strong>PDF öffnen</strong><span>{{ file.name }}</span></span>
                                <v-icon size="20" aria-hidden="true">mdi-eye-outline</v-icon>
                            </v-btn>
                            <v-btn v-if="task.files?.length" variant="outlined" class="task-action task-action--file"
                                @click="openPdf(task, deadlineFile)">
                                <v-icon size="22" aria-hidden="true">mdi-calendar-clock-outline</v-icon>
                                <span class="task-action-text"><strong>Aktuelles Fristenblatt (PDF)</strong></span>
                            </v-btn>
                            <v-btn v-for="test in task.tests" :key="test.url" :href="test.url" target="_blank" rel="noopener noreferrer" variant="flat" class="task-action task-action--test">
                                <v-icon size="22" aria-hidden="true">mdi-clipboard-check-outline</v-icon>
                                <span class="task-action-text"><strong>{{ testActionLabel(test) }}</strong><span>{{ test.title }}</span></span>
                                <v-icon size="20" aria-hidden="true">mdi-arrow-right</v-icon>
                            </v-btn>
                        </div>
                    </div>
                    <StudentWorkSubmission :task="task" :course-id="courseId" :expired="submissionExpired(task)"
                        @saved="task.submission_files = $event" @refresh="refreshTasks" />
                </article>
            </div>
        </template>
        <v-dialog v-model="pdfDialog" max-width="1100" scrollable>
            <v-card>
                <v-card-title class="text-wrap">{{ pdfName }}</v-card-title>
                <v-card-text>
                    <p v-if="activePdfTask && pdfId !== deadlineFile.id" class="text-body-2 mb-3">
                        <template v-if="activePdfTask.deadline?.date">
                            Aktuelle Abgabefrist {{ dateLabel(activePdfTask.deadline.date) }}<template v-if="activePdfTask.deadline.time"> · {{ activePdfTask.deadline.time }} Uhr</template>
                        </template>
                        <template v-else>Derzeit ist keine Abgabefrist festgelegt.</template>
                    </p>
                    <v-progress-linear v-if="pdfLoading" indeterminate aria-label="PDF wird geladen" />
                    <v-alert v-if="pdfError" type="error" variant="tonal" role="alert">{{ pdfError }}</v-alert>
                    <CurriculumPdfPreview v-if="pdfUrl" :src="pdfUrl" :document-id="pdfId" class="task-pdf-preview" />
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn @click="pdfDialog = false">Schließen</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { configureEcho, echo, echoIsConfigured } from '@laravel/echo-vue'
import { resolveAdminEchoConfig } from '@/apps/adminEchoConfig'
import { download, index, start } from '@/actions/App/Http/Controllers/Student/CourseWorkTaskController'
import { workDeadlineExpired } from '@/helpers/date'
import CurriculumPdfPreview from '@/pages/admin/teaching/curricula/CurriculumPdfPreview.vue'
import StudentWorkSubmission from './StudentWorkSubmission.vue'

const props = defineProps({
    courseId: { type: [String, Number], required: true },
    active: { type: Boolean, default: true },
})
const pendingTaskLabels = { not_released: 'Noch nicht freigegeben', paused: 'Pausiert' }
const tasks = ref([])
const deadlineNow = ref(Date.now())
const loading = ref(false)
const manualReloading = ref(false)
const error = ref('')
const openingTaskId = ref(null)
const openTaskError = ref(null)
const pdfDialog = ref(false)
const pdfLoading = ref(false)
const pdfError = ref('')
const pdfUrl = ref('')
const pdfName = ref('')
const pdfId = ref('')
const pdfTaskId = ref(null)
const deadlineFile = { id: 'deadline', name: 'Fristenblatt.pdf' }
const activePdfTask = computed(() => tasks.value.find(task => task.id === pdfTaskId.value))
let requestNumber = 0
let tasksRequestController
let pdfRequestNumber = 0
let refreshing = false
let deadlineVersion = ''
let deadlineChannelName = ''
let subscribedDeadlineChannel = ''
let deadlineSignalPending = false
let taskRefreshTimer

function mustOpenTask(task) {
    return task.status === 'released' && task.can_start_task && !task.task_started
        && (task.instructions || task.files?.length) && (!task.deadline || !deadlineExpired(task.deadline))
}

async function openTask(task) {
    if (openingTaskId.value !== null || !mustOpenTask(task)) return
    openingTaskId.value = task.id
    openTaskError.value = null
    try {
        const { data } = await axios.post(start.url({ courseId: props.courseId, course_work: task.id }))
        if (data?.started !== true) throw new Error()
        const current = tasks.value.find(item => item.id === task.id)
        if (current) current.task_started = true
    } catch (failure) {
        openTaskError.value = { id: task.id, message: failure.response?.data?.message || 'Die Aufgabenstellung konnte nicht geöffnet werden.' }
    } finally {
        openingTaskId.value = null
    }
}

function leaveDeadlineChannel() {
    if (!subscribedDeadlineChannel) return
    echo().leaveChannel(`private-${subscribedDeadlineChannel}`)
    subscribedDeadlineChannel = ''
}

function subscribeToDeadlines() {
    if (!props.active || !/^user\.\d+$/.test(deadlineChannelName) || subscribedDeadlineChannel === deadlineChannelName) return

    const config = resolveAdminEchoConfig(window.schooltoolEchoEnvironment ?? {})
    if (config.broadcaster === 'null') return

    try {
        if (!echoIsConfigured()) {
            configureEcho({
                ...config,
                channelAuthorization: {
                    // The SPA login rotates the CSRF token. Axios uses the current XSRF cookie.
                    customHandler: ({ socketId, channelName }, callback) => {
                        axios.post(config.authEndpoint, { socket_id: socketId, channel_name: channelName }, { timeout: 3000 })
                            .then(({ data }) => callback(null, data), failure => callback(failure, null))
                    },
                },
            })
        }
        echo().private(deadlineChannelName).listen('.course-work.deadline-changed', () => {
            deadlineSignalPending = true
            flushDeadlineSignal()
        })
        subscribedDeadlineChannel = deadlineChannelName
    } catch {
        // The lightweight deadline check also works without a push connection.
    }
}

function flushDeadlineSignal() {
    if (!deadlineSignalPending || !canRefreshTasks()) return
    deadlineSignalPending = false
    void refreshDeadlines()
}

function deadlineExpired(deadline) {
    return workDeadlineExpired({ finish_until_date: deadline.date, finish_until_time: deadline.time }, deadlineNow.value)
}

function submissionExpired(task) {
    return task.submission_deadline_at ? deadlineNow.value > Date.parse(task.submission_deadline_at)
        : Boolean(task.deadline && deadlineExpired(task.deadline))
}

function testActionLabel(test) {
    if (test.attempt_state === 'in_progress') return 'MC-Test fortsetzen'
    if (['paused', 'finished', 'unavailable'].includes(test.attempt_state)) return 'MC-Test öffnen'
    return 'MC-Test starten'
}

function clearPdf() {
    pdfRequestNumber++
    if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value)
    pdfUrl.value = ''
    pdfLoading.value = false
}

async function openPdf(task, file) {
    clearPdf()
    const request = pdfRequestNumber
    pdfTaskId.value = task.id
    pdfName.value = file.name
    pdfId.value = file.id
    pdfError.value = ''
    pdfDialog.value = true
    pdfLoading.value = true
    try {
        const response = await axios.get(download.url({ courseId: props.courseId, course_work: task.id, fileId: file.id }), { responseType: 'blob' })
        if (request !== pdfRequestNumber) return
        if (!(response.data instanceof Blob) || response.data.type !== 'application/pdf') throw new Error('Ungültige PDF')
        pdfUrl.value = URL.createObjectURL(response.data)
    } catch {
        if (request === pdfRequestNumber) pdfError.value = 'Die Aufgaben-PDF konnte nicht geöffnet werden. Bitte versuche es erneut.'
    } finally {
        if (request === pdfRequestNumber) pdfLoading.value = false
    }
}

function dateLabel(value) {
    return new Intl.DateTimeFormat('de-AT').format(new Date(`${value}T12:00:00`))
}

const pointsFormat = new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 })
function pointsLabel(value) {
    return `${pointsFormat.format(value)} ${value === 1 ? 'Punkt' : 'Punkte'}`
}

async function load(preserveTasks = false) {
    const request = ++requestNumber
    const controller = new AbortController()
    tasksRequestController?.abort()
    tasksRequestController = controller
    if (!preserveTasks) {
        tasks.value = []
        loading.value = true
    }
    if (!preserveTasks) error.value = ''
    try {
        const response = await axios.get(index.url(props.courseId), { timeout: 10000, signal: controller.signal })
        if (request !== requestNumber) return false
        const channel = response.headers?.['x-schooltool-deadline-channel'] ?? ''
        if (channel !== deadlineChannelName) leaveDeadlineChannel()
        deadlineChannelName = channel
        subscribeToDeadlines()
        tasks.value = response.data.tasks || []
        deadlineVersion = ''
        deadlineNow.value = Date.now()
        error.value = ''
        return true
    } catch (failure) {
        if (request === requestNumber && (!preserveTasks || [401, 403, 404].includes(failure.response?.status))) {
            deadlineChannelName = ''
            leaveDeadlineChannel()
            tasks.value = []
            error.value = failure.response?.data?.message || 'Deine Aufgaben konnten nicht geladen werden.'
        }
        return false
    } finally {
        if (tasksRequestController === controller) tasksRequestController = undefined
        if (request === requestNumber) {
            loading.value = false
            flushDeadlineSignal()
        }
    }
}

async function reloadTasks() {
    if (!props.active || loading.value || manualReloading.value) return
    manualReloading.value = true
    try {
        const minimumFeedback = new Promise(resolve => window.setTimeout(resolve, 650))
        await Promise.all([load(), minimumFeedback])
    } finally {
        manualReloading.value = false
    }
}

function canRefreshTasks() {
    if (!props.active || document.visibilityState !== 'visible') return false
    deadlineNow.value = Date.now()
    return !loading.value && !refreshing
}

async function refreshTasks() {
    if (!canRefreshTasks()) return
    refreshing = true
    try {
        await load(true)
    } finally {
        refreshing = false
        flushDeadlineSignal()
    }
}

async function refreshDeadlines() {
    if (!canRefreshTasks()) return
    const request = requestNumber
    const controller = new AbortController()
    tasksRequestController = controller
    refreshing = true
    try {
        const { data } = await axios.get(index.url(props.courseId), {
            params: { view: 'deadlines', version: deadlineVersion }, timeout: 10000, signal: controller.signal,
        })
        if (request !== requestNumber || !props.active) return
        if (Array.isArray(data.tasks)) {
            const changed = new Map(data.tasks.map(task => [task.id, task]))
            const needsFullReload = tasks.value.length !== changed.size || tasks.value.some(task => {
                const saved = changed.get(task.id)
                return saved?.status !== (task.status || 'released')
                    || saved?.submission_method?.channel !== task.submission_method?.channel
                    || saved?.submission_method?.email !== task.submission_method?.email
                    || (saved?.submission_deadline_at || null) !== (task.submission_deadline_at || null)
                    || ((task.tests?.length || task.submission_method?.channel === 'portal') && (saved.deadline?.date !== task.deadline?.date || saved.deadline?.time !== task.deadline?.time))
            })
            if (needsFullReload) {
                if (!await load(true)) return
            } else {
                tasks.value = tasks.value.map(task => {
                    const { deadline } = changed.get(task.id)
                    return { ...task, deadline }
                })
            }
        }
        deadlineVersion = data.version
        deadlineNow.value = Date.now()
    } catch (failure) {
        if (request === requestNumber && [401, 403, 404].includes(failure.response?.status)) {
            deadlineChannelName = ''
            leaveDeadlineChannel()
            tasks.value = []
            deadlineVersion = ''
            error.value = failure.response?.data?.message || 'Deine Aufgaben konnten nicht geladen werden.'
        }
    } finally {
        if (tasksRequestController === controller) tasksRequestController = undefined
        refreshing = false
        flushDeadlineSignal()
    }
}

watch(pdfDialog, isOpen => { if (!isOpen) clearPdf() })
watch(() => [activePdfTask.value?.id, JSON.stringify(activePdfTask.value?.deadline)], ([id, deadline], [previousId, previousDeadline]) => {
    if (!pdfDialog.value) return
    if (!id) pdfDialog.value = false
    else if (id === previousId && deadline !== previousDeadline && pdfId.value === deadlineFile.id) {
        openPdf(activePdfTask.value, deadlineFile)
    }
})
watch(() => props.courseId, () => {
    deadlineVersion = ''
    pdfDialog.value = false
    clearPdf()
    load()
}, { immediate: true })
watch(() => props.active, active => {
    if (active) {
        subscribeToDeadlines()
        refreshTasks()
    } else {
        deadlineSignalPending = false
        leaveDeadlineChannel()
    }
})
onMounted(() => {
    window.addEventListener('focus', refreshTasks)
    document.addEventListener('visibilitychange', refreshTasks)
    taskRefreshTimer = window.setInterval(refreshDeadlines, 2000)
})
onBeforeUnmount(() => {
    requestNumber++
    tasksRequestController?.abort()
    deadlineSignalPending = false
    leaveDeadlineChannel()
    clearPdf()
    window.clearInterval(taskRefreshTimer)
    window.removeEventListener('focus', refreshTasks)
    document.removeEventListener('visibilitychange', refreshTasks)
})
</script>

<style scoped>
.task-list-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px 16px; margin: 4px 0 18px; }
.task-list-heading h2 { color: #18243b; font-size: 1.35rem; line-height: 1.25; font-weight: 800; }
.task-heading-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.task-heading-actions .v-btn[aria-busy="true"] { opacity: .85; }
.task-refresh-spinning { animation: task-refresh-spin .6s linear infinite; }
@media (prefers-reduced-motion: reduce) {
    /* The student workspace suppresses motion globally. A manual refresh gets one turn. */
    .task-refresh-spinning { animation: task-refresh-spin .6s linear 1 !important; }
}
@keyframes task-refresh-spin {
    to { transform: rotate(360deg); }
}
.task-count { flex: none; padding: 6px 12px; color: #4056a9; background: #edf1ff; border-radius: 999px; font-size: .8rem; font-weight: 800; }
.task-list { display: grid; gap: 16px; }
.task-card { overflow: hidden; background: #fff; border: 1px solid #dce4f2; border-radius: 18px; box-shadow: 0 4px 16px #1b2b5010; }
.task-card-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 22px 24px; }
.task-title-group { display: flex; align-items: center; gap: 14px; min-width: 0; }
.task-title-icon { display: grid; flex: none; place-items: center; width: 48px; height: 48px; border-radius: 14px; color: #4056d6; background: #edf1ff; }
.task-title-text { min-width: 0; }
.task-title-text > span { display: block; color: #64718a; font-size: .78rem; font-weight: 700; }
.task-title-text h3 { color: #18243b; font-size: 1.2rem; line-height: 1.3; font-weight: 800; overflow-wrap: anywhere; }
.task-status { flex-shrink: 0; align-self: flex-start; padding: 7px 11px; border-radius: 8px; background: #fff4db; color: #714a0e; font-size: .82rem; font-weight: 700; }
.task-status--released { color: #225c42; background: #e6f5ec; }
.task-status--ended { color: #394458; background: #edf0f5; }
.task-announcement-details { display: flex; flex-wrap: wrap; gap: 12px 24px; padding: 0 24px 22px; color: #18243b; }
.task-announcement-details:empty { display: none; }
.task-announcement-details dt { color: #53617a; font-size: .78rem; font-weight: 700; }
.task-announcement-details dd { font-weight: 700; }
.task-deadline-expired { color: #9a2525; font-size: .78rem; }
.task-description { padding: 0 24px 22px; }
.task-description h4, .task-materials h4 { color: #53617a; font-size: .8rem; font-weight: 800; margin-bottom: 7px; }
.task-instructions { white-space: pre-wrap; overflow-wrap: anywhere; }
.task-materials { padding: 18px 24px 22px; background: #f8faff; border-top: 1px solid #e7ecf5; }
.task-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.task-action.v-btn { height: auto; min-height: 60px; max-width: 100%; padding: 11px 14px; border-radius: 12px; letter-spacing: normal; text-transform: none; }
.task-action :deep(.v-btn__content) { display: flex; gap: 11px; width: 100%; white-space: normal; text-align: left; }
.task-action--file.v-btn { color: #3049a7; background: #fff; border-color: #ccd7f0; }
.task-action--test.v-btn { color: #fff; background: #4056d6; }
.task-action-text { display: grid; gap: 2px; min-width: 0; }
.task-action-text strong { font-size: .9rem; line-height: 1.2; }
.task-action-text > span { max-width: 330px; font-size: .76rem; line-height: 1.3; font-weight: 500; overflow-wrap: anywhere; opacity: .85; }
.task-empty { display: flex; align-items: flex-start; gap: 14px; padding: 24px; color: #4056a9; background: #f3f6ff; border: 1px solid #e0e7fb; border-radius: 16px; }
.task-empty strong { color: #18243b; }
.task-empty p { margin-top: 4px; color: #63718a; }
.task-pdf-preview { height: 70vh; min-height: 320px; }
@media (max-width: 600px) {
    .task-list-heading { align-items: flex-start; }
    .task-card-header { align-items: stretch; flex-direction: column; gap: 14px; padding: 18px; }
    .task-announcement-details { padding: 0 18px 18px; }
    .task-description { padding: 0 18px 18px; }
    .task-materials { padding: 16px 18px 18px; }
    .task-actions { flex-direction: column; }
    .task-action.v-btn { width: 100%; }
    .task-action-text { flex: 1; }
}
</style>
