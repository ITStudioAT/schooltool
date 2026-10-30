<template>
    <section aria-label="Meine Aufgaben">
        <v-progress-linear v-if="loading" indeterminate aria-label="Aufgaben werden geladen" />
        <v-alert v-else-if="error" type="error" variant="tonal" role="alert">{{ error }} <v-btn variant="text" @click="load()">Erneut laden</v-btn></v-alert>
        <div v-else-if="!tasks.length" class="task-empty">
            <v-icon size="26" aria-hidden="true">mdi-clipboard-text-outline</v-icon>
            <div><strong>Noch keine Aufgaben</strong><p>Hier findest du die Aufgaben, die deine Lehrkraft für dich bereitstellt.</p></div>
        </div>
        <template v-else>
            <div class="task-list-heading">
                <div>
                    <h2>Meine Aufgaben</h2>
                    <p>Alle Aufgaben für dieses Fach auf einen Blick.</p>
                </div>
                <span class="task-count">{{ tasks.length }} {{ tasks.length === 1 ? 'Aufgabe' : 'Aufgaben' }}</span>
            </div>
            <div class="task-list">
                <article v-for="task in tasks" :key="task.id" class="task-card">
                    <header class="task-card-header">
                        <div class="task-title-group">
                            <span class="task-title-icon"><v-icon size="24" aria-hidden="true">mdi-clipboard-text-outline</v-icon></span>
                            <div class="task-title-text"><span>Aufgabe</span><h3>{{ task.title || 'Leistungsfeststellung' }}</h3></div>
                        </div>
                        <div v-if="task.deadline?.date" class="task-deadline">
                            <v-icon size="21" aria-hidden="true">mdi-calendar-clock-outline</v-icon>
                            <div><span>Abgabe bis</span><strong>{{ dateLabel(task.deadline.date) }} <span>· {{ task.deadline.time || '23:59' }} Uhr</span></strong></div>
                        </div>
                    </header>
                    <div v-if="task.instructions" class="task-description">
                        <h4>Das ist zu tun</h4>
                        <p class="task-instructions">{{ task.instructions }}</p>
                    </div>
                    <div v-if="task.files?.length || task.tests?.length" class="task-materials">
                        <h4>{{ task.files?.length && task.tests?.length ? 'Unterlagen & Test' : task.tests?.length ? 'MC-Test' : 'Unterlagen' }}</h4>
                        <div class="task-actions">
                            <v-btn v-for="file in task.files" :key="file.id" variant="outlined" class="task-action task-action--file" @click="openPdf(task, file)">
                                <v-icon size="22" aria-hidden="true">mdi-file-pdf-box</v-icon>
                                <span class="task-action-text"><strong>PDF öffnen</strong><span>{{ file.name }}</span></span>
                                <v-icon size="20" aria-hidden="true">mdi-eye-outline</v-icon>
                            </v-btn>
                            <v-btn v-for="test in task.tests" :key="test.url" :href="test.url" target="_blank" rel="noopener noreferrer" variant="flat" class="task-action task-action--test">
                                <v-icon size="22" aria-hidden="true">mdi-clipboard-check-outline</v-icon>
                                <span class="task-action-text"><strong>{{ testActionLabel(test) }}</strong><span>{{ test.title }}</span></span>
                                <v-icon size="20" aria-hidden="true">mdi-arrow-right</v-icon>
                            </v-btn>
                        </div>
                    </div>
                </article>
            </div>
        </template>
        <v-dialog v-model="pdfDialog" max-width="1100" scrollable>
            <v-card>
                <v-card-title class="text-wrap">{{ pdfName }}</v-card-title>
                <v-card-text>
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
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { download, index } from '@/actions/App/Http/Controllers/Student/CourseWorkTaskController'
import CurriculumPdfPreview from '@/pages/admin/teaching/curricula/CurriculumPdfPreview.vue'

const props = defineProps({ courseId: { type: [String, Number], required: true } })
const tasks = ref([])
const loading = ref(false)
const error = ref('')
const pdfDialog = ref(false)
const pdfLoading = ref(false)
const pdfError = ref('')
const pdfUrl = ref('')
const pdfName = ref('')
const pdfId = ref('')
let requestNumber = 0
let pdfRequestNumber = 0
let refreshing = false

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

async function load(preserveTasks = false) {
    const request = ++requestNumber
    if (!preserveTasks) {
        tasks.value = []
        loading.value = true
    }
    error.value = ''
    try {
        const response = await axios.get(index.url(props.courseId))
        if (request === requestNumber) tasks.value = response.data.tasks || []
    } catch (failure) {
        if (request === requestNumber) error.value = failure.response?.data?.message || 'Deine Aufgaben konnten nicht geladen werden.'
    } finally {
        if (request === requestNumber) loading.value = false
    }
}

async function refreshOnReturn() {
    if (document.visibilityState !== 'visible' || loading.value || refreshing) return
    refreshing = true
    try {
        await load(true)
    } finally {
        refreshing = false
    }
}

watch(pdfDialog, isOpen => { if (!isOpen) clearPdf() })
watch(() => props.courseId, () => { pdfDialog.value = false; clearPdf(); load() }, { immediate: true })
onMounted(() => {
    window.addEventListener('focus', refreshOnReturn)
    document.addEventListener('visibilitychange', refreshOnReturn)
})
onBeforeUnmount(() => {
    requestNumber++
    clearPdf()
    window.removeEventListener('focus', refreshOnReturn)
    document.removeEventListener('visibilitychange', refreshOnReturn)
})
</script>

<style scoped>
.task-list-heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin: 4px 0 18px; }
.task-list-heading h2 { color: #18243b; font-size: 1.35rem; line-height: 1.25; font-weight: 800; }
.task-list-heading p { margin-top: 3px; color: #63718a; font-size: .9rem; }
.task-count { flex: none; padding: 6px 12px; color: #4056a9; background: #edf1ff; border-radius: 999px; font-size: .8rem; font-weight: 800; }
.task-list { display: grid; gap: 16px; }
.task-card { overflow: hidden; background: #fff; border: 1px solid #dce4f2; border-radius: 18px; box-shadow: 0 4px 16px #1b2b5010; }
.task-card-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 22px 24px; }
.task-title-group { display: flex; align-items: center; gap: 14px; min-width: 0; }
.task-title-icon { display: grid; flex: none; place-items: center; width: 48px; height: 48px; border-radius: 14px; color: #4056d6; background: #edf1ff; }
.task-title-text { min-width: 0; }
.task-title-text > span, .task-deadline span { display: block; color: #64718a; font-size: .78rem; font-weight: 700; }
.task-title-text h3 { color: #18243b; font-size: 1.2rem; line-height: 1.3; font-weight: 800; overflow-wrap: anywhere; }
.task-deadline { display: flex; align-items: center; gap: 10px; flex: none; padding: 10px 14px; color: #4056d6; background: #f0f3ff; border: 1px solid #e1e6fc; border-radius: 12px; }
.task-deadline strong { display: block; color: #283b86; font-size: .92rem; }
.task-deadline strong span { display: inline; color: inherit; font-size: inherit; }
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
    .task-deadline { align-self: flex-start; }
    .task-description { padding: 0 18px 18px; }
    .task-materials { padding: 16px 18px 18px; }
    .task-actions { flex-direction: column; }
    .task-action.v-btn { width: 100%; }
    .task-action-text { flex: 1; }
}
</style>
