<template>
    <v-btn v-if="labelled && (historical || status === 'found' || (canCreate && status === 'missing'))" size="small" min-height="44"
        variant="tonal" prepend-icon="mdi-clipboard-text-outline" :disabled="!accessible" :aria-label="!historical && status === 'missing' ? 'Leistungsfeststellung erstellen' : 'Leistungsfeststellung verwalten'" @click.stop="openDialog">
        {{ !historical && status === 'missing' ? 'Leistungsfeststellung erstellen' : 'Leistungsfeststellung verwalten' }}
    </v-btn>
    <span v-else-if="!labelled && work?.id && (needsPreparation || historical || status === 'found')" class="d-inline-flex" :class="{ 'cursor-pointer': accessible }"
        :tabindex="accessible ? 0 : undefined" :role="accessible ? 'button' : undefined" :aria-disabled="disabled"
        :aria-label="tooltip" :title="tooltip" @click.stop="openDialog"
        @keydown.enter.stop.prevent="openDialog" @keydown.space.stop.prevent="openDialog">
        <v-icon :size="size" :color="color" :aria-label="tooltip" :aria-hidden="false" role="img">
            mdi-clipboard-text-outline
        </v-icon>
        <v-tooltip activator="parent" location="top" :text="tooltip" :open-delay="200" :max-width="420" />
    </span>
    <v-dialog v-if="opened && accessible" v-model="opened" persistent scrollable max-width="720">
        <v-card>
            <v-card-title v-if="viewingRun" class="text-wrap">
                <span ref="runTitle" tabindex="-1">Erstellungsauftrag · {{ work.title || 'Ohne Titel' }}</span>
            </v-card-title>
            <v-card-title v-else-if="deletionProgress" class="text-wrap">
                <span ref="deleteTitle" tabindex="-1">Leistungsfeststellung löschen · {{ work.title || 'Ohne Titel' }}</span>
            </v-card-title>
            <v-card-title v-else class="text-wrap">{{ prepared ? 'Leistungsfeststellung vorbereitet' : 'Leistungsfeststellung' }} · {{ work.title || 'Ohne Titel' }}</v-card-title>
            <v-card-text v-if="viewingRun">
                <p v-if="runLoading" role="status">Erstellungsauftrag wird geladen …</p>
                <v-alert v-else-if="runError" type="error" variant="tonal" role="alert">{{ runError }}</v-alert>
                <template v-else-if="run">
                    <v-alert :type="run.state === 'completed' ? 'success' : 'info'" variant="tonal" class="mb-4">
                        {{ runLabels[run.state] || 'Status unbekannt' }}
                        <p v-if="run.message" class="mt-1">{{ run.message }}</p>
                    </v-alert>
                    <dl v-if="runDetails.length" class="exercise-info">
                        <template v-for="item in runDetails" :key="item.label">
                            <dt>{{ item.label }}</dt><dd>{{ item.value }}</dd>
                        </template>
                    </dl>
                    <div v-if="runCandidates.length || run.result?.participants_count != null" class="mt-4">
                        <h3 class="text-subtitle-2 mb-1">{{ run.result?.participants_count ?? runCandidates.length }} Kandidat:innen</h3>
                        <ol v-if="runCandidates.length" class="exercise-candidates text-body-2">
                            <li v-for="person in runCandidates" :key="person.name">{{ person.name }}</li>
                        </ol>
                        <p v-else class="text-body-2">Zu diesem Auftrag ist keine Namensliste gespeichert.</p>
                    </div>
                </template>
                <p v-else>Für diese Durchführung ist kein Erstellungsauftrag gespeichert.</p>
            </v-card-text>
            <v-card-text v-else-if="deletionProgress">
                <WorkCheckProgress :state="deletionProgress" deletion />
                <p v-if="deletionCompleted && !deleting" class="mt-3">Prüfungsordner, Verknüpfung und Arbeit sind erhalten.</p>
                <v-alert v-if="deleteError" type="error" variant="tonal" class="mt-4" role="alert">{{ deleteError }}</v-alert>
            </v-card-text>
            <v-card-text v-else>
                <v-alert v-if="pendingDeletion" type="warning" variant="tonal" class="mb-4">Die LF-Löschung ist noch nicht bestätigt. Bitte die Löschaktion erneut bestätigen.</v-alert>
                <v-alert v-else-if="!prepared" :type="historical || assignmentReady ? 'success' : 'warning'" variant="tonal" class="mb-4">{{ historical ? 'Bestehende Leistungsfeststellung – gespeicherte Originalnachweise vorhanden.' : assignmentReady ? 'Aufgabenstellung erstellt – Prüfungsordner bestätigt.' : 'Prüfungsordner bestätigt; die Aufgabenstellung ist noch nicht bestätigt.' }}</v-alert>
                <p v-if="checkTime && !prepared" class="text-caption mb-3">Letzter Check: {{ checkTime }}</p>
                <v-btn v-if="status === 'found' && !historical" ref="runButton" variant="tonal"
                    prepend-icon="mdi-file-document-outline" class="mb-4" :disabled="confirmDelete || deleting" @click="showRun">Erstellungsauftrag anzeigen</v-btn>
                <dl class="exercise-info">
                    <template v-for="item in details" :key="item.label">
                        <dt>{{ item.label }}</dt>
                        <dd>
                            <WorkPointScale v-if="item.workPoints" :assessment-points="item.value" :work-points="item.workPoints" />
                            <template v-else>{{ item.value }}</template>
                        </dd>
                    </template>
                </dl>
                <template v-if="work.description">
                    <h3 class="text-subtitle-2 mt-4 mb-1">Beschreibung</h3>
                    <p class="exercise-info-description text-body-2">{{ work.description }}</p>
                </template>
                <div v-if="folder?.id && !pendingDeletion" class="d-flex flex-wrap ga-2 mt-4" aria-label="Prüfungsunterlagen">
                    <WorkExercisePdf v-for="section in documentSections" :key="section" :work="work" :date="date" :section="section" :verify-exercise="prepared" />
                    <WorkEvaluationPdf :work="work" />
                </div>
                <v-alert v-if="confirmDelete" type="warning" variant="tonal" class="mt-4">Prüfungsunterlagen und Kandidatenordner samt Inhalt löschen? Prüfungsordner, Verknüpfung und Arbeit bleiben erhalten.</v-alert>
                <h3 class="text-subtitle-2 mt-4 mb-1">Prüfungsordner</h3>
                <p class="exercise-info-path text-body-2">{{ folder?.path || folder?.name || 'Der Prüfungsordner ist derzeit nicht bestätigt. Gespeicherte Nachweise bleiben erhalten.' }}</p>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <v-btn v-if="viewingRun" variant="text" prepend-icon="mdi-arrow-left" @click="backToExercise">Zurück zur Leistungsfeststellung</v-btn>
                <v-btn v-else-if="canDeleteExercise && !deletionCompleted" color="error" variant="text" :loading="deleting" :disabled="deleting"
                    @click="confirmDelete ? deleteExercise() : confirmDelete = true">{{ deletionProgress ? 'Löschen erneut versuchen' : confirmDelete ? 'Löschen bestätigen' : 'Leistungsfeststellung löschen' }}</v-btn>
                <v-btn v-if="confirmDelete && !deletionProgress" @click="confirmDelete = false">Abbrechen</v-btn>
                <v-spacer />
                <v-btn :disabled="deleting" @click="closeDialog">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { show as showExerciseRun } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingAssessmentRunController'
import { destroyExercise } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workLinkedMcTestTitle, workNeedsPreparation } from '@/helpers/workOverview'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { activeStudentIds, workParticipantIds } from '@/helpers/workCellStatus'
import { workHasProgress } from '@/helpers/workProgress'
import WorkExercisePdf from './WorkExercisePdf.vue'
import WorkEvaluationPdf from './WorkEvaluationPdf.vue'
import WorkCheckProgress from './WorkCheckProgress.vue'
import WorkPointScale from './WorkPointScale.vue'
import { createWorkCheckStream, useWorkCheckProgress } from './workCheckProgress'

const props = defineProps({ work: Object, course: Object, date: { type: String, default: '' }, state: Object, size: { type: [Number, String], default: 30 }, disabled: Boolean, canCreate: Boolean, recheck: Function, labelled: Boolean })
const emit = defineEmits(['create', 'deleted'])
const admin = useAdminStore()
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const binding = computed(() => workDropboxStates.get(key.value))
const historical = computed(() => workAutomationEnabled(props.work)
    && (Boolean(binding.value?.legacy_folders?.exercise?.id) || (workHasProgress(props.work) && !needsPreparation.value)))
const needsPreparation = computed(() => workNeedsPreparation(props.work,
    binding.value))
const folder = computed(() => {
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const cached = workExerciseCheck(key, props.work, props.date)
    const currentFolder = status.value === 'found' ? props.state?.folder : null
    const checkedFolder = cached?.status === 'found' ? cached.folder : null
    return currentFolder || checkedFolder || (historical.value ? binding.value?.legacy_folders?.exercise || binding.value?.folder : null)
})
const accessible = computed(() => !props.disabled && (historical.value || status.value === 'found' || (props.canCreate && status.value === 'missing')))
const opened = ref(false)
const checkTime = computed(() => props.state?.checkedAt ? new Intl.DateTimeFormat('de-AT', {
    timeZone: 'Europe/Vienna', dateStyle: 'short', timeStyle: 'short',
}).format(new Date(props.state.checkedAt)) : '')
const status = computed(() => {
    const state = props.state
    if (!state || state.userId !== admin.config?.user?.id || String(state.workId) !== String(props.work?.id)
        || String(state.courseId) !== String(props.work?.teaching_course_id) || state.date !== props.date) return 'checking'
    return state.status
})
const assignmentReady = computed(() => props.state?.assignmentStatus === 'ready')
const prepared = computed(() => status.value === 'found' && props.state?.preparation?.status === 'ready')
const pendingDeletion = computed(() => status.value === 'found' && props.state?.deletionPending === true)
const canDeleteExercise = computed(() => props.canCreate && !historical.value && (prepared.value || pendingDeletion.value)
    && Boolean(props.state?.folder?.id && binding.value?.module_link?.folder?.id))
const documentSections = computed(() => prepared.value
    ? props.state.preparation.document_sections ?? ['Angaben', 'Bewertungskriterien']
    : ['Angaben', 'Bewertungskriterien'])
const run = ref(null)
const viewingRun = ref(false)
const runTitle = ref(null)
const runButton = ref(null)
const runLoading = ref(false)
const runError = ref('')
const deleteError = ref('')
const confirmDelete = ref(false)
const deleting = ref(false)
const deleteTitle = ref(null)
const { state: deletionProgress, start: startDeletionProgress, update: updateDeletionProgress,
    finish: finishDeletionProgress, reset: resetDeletionProgress } = useWorkCheckProgress()
const deletionCompleted = computed(() => deletionProgress.value?.state === 'done')
const deletionSteps = ['exercise_delete_check', 'exercise_delete_contents', 'exercise_delete_reset', 'exercise_delete_verify']
let runRequest = null
const runLabels = { queued: 'Anlage wartet auf den Start', running: 'Anlage läuft', completed: 'Anlage abgeschlossen',
    failed: 'Anlage fehlgeschlagen', interrupted: 'Anlage unterbrochen' }
const runCandidates = computed(() => run.value?.generation?.participants ?? [])
const runDetails = computed(() => {
    const request = run.value?.generation
    const result = run.value?.result
    const scheduledDate = result?.scheduled_date ?? request?.scheduled_date ?? run.value?.date
    const assignmentPoints = result?.points ?? request?.points
    const mc = result?.multiple_choice ?? request?.multiple_choice
    const target = result?.folder?.path ?? request?.target
    return [
        ...(scheduledDate ? [{ label: 'Durchführung', value: displayDate(scheduledDate) }] : []),
        ...(request?.deadline ? [{ label: 'Abgabefrist bei Erstellung', value: deadline(request.deadline.date, request.deadline.time) }] : []),
        ...(assignmentPoints != null ? [{ label: 'Aufgabenblatt', value: `${formatPoints(assignmentPoints)} Punkte` }] : []),
        ...(mc ? [{ label: 'MC-Test', value: `${mc.title} (${formatPoints(mc.points)} Punkte)` }] : []),
        ...(mc && request?.combined_points != null ? [{ label: 'Gesamtpunkte', value: formatPoints(request.combined_points) }] : []),
        ...(target ? [{ label: result?.folder ? 'Prüfungsordner' : 'Zielordner', value: target }] : []),
    ]
})
const color = computed(() => props.disabled ? 'grey' : historical.value || (status.value === 'found' && (assignmentReady.value || prepared.value)) ? 'success'
    : status.value === 'found' ? 'warning' : status.value === 'missing' ? 'error' : 'grey')
const tooltip = computed(() => {
    if (props.disabled) return 'Zuerst den Lernmaterialordner verknüpfen'
    if (historical.value) return folder.value?.id ? `Leistungsfeststellung erstellt: ${folder.value.name}`
        : 'Bestehende Leistungsfeststellung – gespeicherte Nachweise ansehen'
    if (prepared.value) return 'Leistungsfeststellung vorbereitet'
    if (status.value === 'found') return assignmentReady.value ? `Leistungsfeststellung erstellt: ${props.state.folder?.name || ''}`
        : 'Prüfungsordner vorhanden; Aufgabenstellung noch nicht bestätigt'
    if (status.value === 'missing') return props.canCreate ? 'Leistungsfeststellung erstellen' : 'Leistungsfeststellung noch nicht erstellt'
    if (status.value === 'unknown') return 'Leistungsfeststellung konnte nicht geprüft werden'
    return 'Leistungsfeststellung wird geprüft …'
})

const course = computed(() => String(props.course?.id) === String(props.work?.teaching_course_id) ? props.course : null)
const groups = computed(() => (props.work?.groups || []).map((group, index) => ({ ...group, index }))
    .filter(group => !props.date || (group.date || props.work.date_for_all_groups) === props.date))
const linkedMcTests = computed(() => {
    const slots = [props.date || props.work?.date_for_all_groups || 'all', 'all']
    return [...new Map(slots.map(slot => [String(props.work?.status?.multiple_choice_tests?.[slot]?.test_id),
        workLinkedMcTestTitle(props.work, slot)]).filter(([, title]) => title)).values()]
})
const participants = computed(() => {
    if (!Array.isArray(course.value?.students_info)) return null
    if ((props.work?.groups || []).length) {
        if (!groups.value.length) return null
        return workParticipantIds({ groups: groups.value }, course.value.students_info).length
    }
    return activeStudentIds(course.value.students_info).length
})
const details = computed(() => {
    const work = props.work
    const definition = course.value?.teaching_entry_area?.entry_definitions
        ?.find(entry => entry.category === 'Benotung' && entry.short_name === work.type)
    const maximumPoints = definition?.maximum_points
    const totalPoints = prepared.value ? props.state.preparation.total_points : null
    const showPointScale = totalPoints != null && Number.isFinite(Number(totalPoints)) && Number.isFinite(Number(maximumPoints)) && Number(maximumPoints) > 0
    const classes = Array.isArray(course.value?.classes) ? course.value.classes.join(', ') : course.value?.classes
    return [
        { label: 'Titel', value: work.title || 'Ohne Titel' },
        { label: 'Typ', value: [work.type, definition?.name].filter(Boolean).join(' · ') || 'Nicht angegeben' },
        { label: 'Kurs', value: course.value?.title || 'Nicht angegeben' },
        ...(classes ? [{ label: 'Klasse', value: classes }] : []),
        { label: 'Durchführung', value: displayDate(props.date || work.date_for_all_groups) },
        { label: 'Arbeitsform', value: work.is_group_work ? 'Gruppenarbeit' : 'Einzelarbeit' },
        ...(work.is_group_work && groups.value.length ? [{ label: 'Prüfungsgruppe', value: groups.value
            .map(group => group.name || `Gruppe ${group.index + 1}`).join(', ') }] : []),
        { label: 'Teilnehmer:innen aktuell', value: participants.value === null ? 'Nicht bestätigt' : String(participants.value) },
        ...(prepared.value ? [
            { label: 'Prüfungspunkte Aufgabenblatt', value: String(props.state.preparation.components.aufgabenblatt.points) },
            { label: 'Prüfungspunkte MC', value: props.state.preparation.components.multiple_choice.included ? String(props.state.preparation.components.multiple_choice.points) : 'Nicht einbezogen' },
            { label: 'Prüfungspunkte gesamt', value: totalPoints == null ? 'Nicht bestätigt' : `${formatPoints(totalPoints)} Punkte`,
                workPoints: showPointScale ? `${formatPoints(maximumPoints)} Punkte` : null },
        ] : []),
        ...(!showPointScale && maximumPoints !== null && maximumPoints !== undefined && Number.isFinite(Number(maximumPoints))
            ? [{ label: prepared.value ? 'Schooltool-Bewertungsskala' : 'Maximale Punkte', value: new Intl.NumberFormat('de-AT').format(Number(maximumPoints)) }] : []),
        { label: 'Beurteilung', value: workAutomationEnabled(work) ? 'Automatische Beurteilung' : 'Manuelle Beurteilung' },
        ...(linkedMcTests.value.length ? [{ label: linkedMcTests.value.length === 1 ? 'MC-Test' : 'MC-Tests', value: linkedMcTests.value.join(', ') }] : []),
        { label: 'Endgültige Abgabefrist', value: deadline(work.finish_until_date, work.finish_until_time) },
    ]
})

function displayDate(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(value || '') ? value.split('-').reverse().join('.') : 'Nicht angegeben'
}

function formatPoints(value) {
    return new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(value)
}

function deadline(date, time, empty = 'Nicht festgelegt') {
    if (!date) return empty
    return `${displayDate(date)}${time ? `, ${time.slice(0, 5)} Uhr` : ''}`
}

async function openDialog() {
    if (!accessible.value) return
    if (!historical.value && status.value === 'missing' && props.canCreate) {
        emit('create')
        return
    }
    if (props.recheck && !prepared.value) {
        await props.recheck()
        await nextTick()
    }
    if (accessible.value && !historical.value && status.value === 'missing' && props.canCreate) {
        emit('create')
        return
    }
    if (accessible.value) opened.value = true
}

async function showRun() {
    resetRun()
    viewingRun.value = true
    nextTick(() => runTitle.value?.focus())
    const request = new AbortController()
    runRequest = request
    runLoading.value = true
    try {
        const { data } = await axios.get(showExerciseRun.url(props.work.id), { signal: request.signal,
            params: props.date ? { date: props.date } : {}, timeout: 10000 })
        if (runRequest === request) run.value = data.run
    } catch {
        if (runRequest === request) runError.value = 'Der Erstellungsauftrag konnte nicht geladen werden.'
    } finally {
        if (runRequest === request) runLoading.value = false
    }
}

function backToExercise() {
    resetRun()
    nextTick(() => runButton.value?.$el?.focus())
}

function resetRun() {
    runRequest?.abort()
    runRequest = null
    viewingRun.value = false
    run.value = null
    runError.value = ''
    runLoading.value = false
}

async function deleteExercise() {
    if (!confirmDelete.value || !canDeleteExercise.value || deleting.value) return
    const currentKey = key.value
    const date = props.date
    const folderId = props.state.folder.id
    deleting.value = true
    deleteError.value = ''
    const operation = startDeletionProgress(props.work.id, null, deletionSteps, true)
    const stream = createWorkCheckStream(progress => updateDeletionProgress(operation, progress))
    nextTick(() => deleteTitle.value?.focus())
    let finalProgress = null
    try {
        const response = await axios.delete(destroyExercise.url(props.work.id), { timeout: 120000,
            params: { stream_progress: 1 }, data: { folder_id: folderId, ...(date ? { date } : {}) }, ...stream.options })
        const result = stream.result(response)
        if (result?.deleted !== true || result.progress?.state !== 'done') throw new Error('Der Abschluss wurde nicht bestätigt.')
        finalProgress = result.progress
    } catch (failure) {
        if (key.value === currentKey && props.date === date) {
            deleteError.value = failure.response?.data?.message || 'Das Löschen der Leistungsfeststellung konnte nicht bestätigt werden.'
        }
    } finally {
        finishDeletionProgress(operation, finalProgress, finalProgress === null)
        deleting.value = false
    }
}

function closeDialog() {
    if (deleting.value) return
    opened.value = false
    if (deletionCompleted.value) emit('deleted')
}

watch([() => admin.config?.user?.id, () => props.work?.id, () => props.work?.teaching_course_id, () => props.date, status, () => props.state?.folder?.id],
    () => { opened.value = false })
watch(() => props.disabled, disabled => { if (disabled) opened.value = false })
watch(opened, value => {
    if (value) return
    resetRun()
    resetDeletionProgress()
    confirmDelete.value = false
    deleteError.value = ''
})
onBeforeUnmount(() => {
    resetRun()
    resetDeletionProgress()
})
</script>

<style scoped>
.exercise-info { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 8px 16px; }
.exercise-info dt { font-weight: 500; }
.exercise-info dd { margin: 0; overflow-wrap: anywhere; }
.exercise-info-path { overflow-wrap: anywhere; }
.exercise-info-description { white-space: pre-wrap; overflow-wrap: anywhere; }
.exercise-candidates { padding-inline-start: 1.5rem; }
@media (max-width: 480px) {
    .exercise-info { grid-template-columns: minmax(0, 1fr); gap: 4px; }
    .exercise-info dd { margin-bottom: 8px; }
}
</style>
