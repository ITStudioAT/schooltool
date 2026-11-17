<template>
    <div v-if="needsPreparation" class="exercise-action" :class="{ 'exercise-action--progress': checkProgressState }" @click.stop>
        <template v-if="exerciseFolder">
            <span v-if="!hideFolderName" class="exercise-folder text-body-2" aria-label="Leistungsfeststellungsordner">LF: {{ exerciseFolder.name }}</span>
            <span v-if="!hideActivator && preparation?.status === 'ready'" role="button" :tabindex="disabled || !current ? -1 : 0"
                :aria-disabled="disabled || !current" aria-label="Leistungsfeststellung vorbereitet" title="Leistungsfeststellung vorbereitet"
                class="d-inline-flex cursor-pointer" @click="openDialog" @keydown.enter.stop.prevent="openDialog" @keydown.space.stop.prevent="openDialog">
                <v-icon color="success" size="30">mdi-clipboard-text-outline</v-icon>
            </span>
        </template>
        <span v-else-if="exerciseChecking" role="status" class="text-body-2">Leistungsfeststellung wird geprüft …</span>
        <span v-else-if="!hideActivator && moduleLinked && checkedStatus === 'missing'" role="button" :tabindex="disabled || !current ? -1 : 0"
            :aria-disabled="disabled || !current" aria-label="Leistungsfeststellung erstellen" title="Leistungsfeststellung erstellen"
            class="d-inline-flex cursor-pointer" @click="openDialog" @keydown.enter.stop.prevent="openDialog" @keydown.space.stop.prevent="openDialog">
            <v-icon color="error" size="30">mdi-clipboard-text-outline</v-icon>
        </span>
        <span v-if="linkedMcTest && !hideMcTest" class="exercise-folder text-body-2">MC-Test: {{ linkedMcTest }}</span>
        <span v-if="!work?.id" class="d-block text-caption mt-1">Arbeit zuerst speichern, damit Arbeitskennung und Prüfungskandidaten bestätigt werden können.</span>
        <v-btn v-if="active && current && moduleLinked && !exerciseChecking" size="small" variant="tonal" prepend-icon="mdi-refresh"
            class="exercise-check-button" :disabled="disabled" @click="checkExercise(true)">
            <span class="d-flex flex-column align-start">
                <span>Erneut prüfen</span>
                <span v-if="checkedAt && !hideCheckTime" class="text-caption text-none">Letzter Check: {{ checkTime }}</span>
            </span>
        </v-btn>
        <span v-if="preparation?.status === 'ready'" role="status" class="exercise-check-message d-block text-caption mt-1">
            Prüfungsordner vorbereitet: Aufgabenblatt {{ preparation.components.aufgabenblatt.points }} Punkte;
            MC {{ preparation.components.multiple_choice.included ? preparation.components.multiple_choice.points + ' Punkte' : 'nicht einbezogen' }};
            Gesamt {{ preparation.total_points }} Punkte. Diese Punkte sind unabhängig von der Schooltool-Bewertungsskala.
            <template v-if="schooltoolScale !== null && Number(preparation.total_points) !== schooltoolScale">
                Die Schooltool-Bewertungsskala beträgt {{ schooltoolScale }} Punkte und weicht davon ab.
            </template>
        </span>
        <span v-if="exerciseMessage" role="status" class="exercise-check-message d-block text-caption mt-1">{{ exerciseMessage }}</span>
        <WorkCheckProgress :state="checkProgressState" :unavailable="progressUnavailable" />
    </div>
    <span v-if="!needsPreparation && linkedMcTest && !hideMcTest" class="exercise-folder text-body-2">MC-Test: {{ linkedMcTest }}</span>
    <WorkProgressCheck v-if="workAutomationEnabled(work) && checkProgress && !needsPreparation && active && current" :work="work" :disabled="disabled"
        :check-key="workDropboxKey(admin.config?.user?.id, work)" @checked="emit('progress-checked', $event)"
        @saved="emit('deadline-saved', $event)" @busy="emit('check-busy', $event)" />
    <v-dialog v-if="opened" :model-value="opened" max-width="640" :persistent="loading"
        @update:model-value="updateDialog">
        <v-card>
            <v-card-title class="text-wrap"><span ref="dialogTitle" tabindex="-1">Leistungsfeststellung erstellen</span></v-card-title>
            <v-card-text v-if="templateLoading" class="exercise-template-loading">
                <div ref="templateStatus" role="status" aria-live="polite" tabindex="-1" class="exercise-template-status">
                    <v-progress-circular indeterminate color="primary" size="56" width="5" aria-hidden="true" />
                    <span>Prüfungsvorlage und Punkte werden geprüft …</span>
                </div>
            </v-card-text>
            <v-card-text v-else-if="templateError">
                <v-alert type="warning" variant="tonal" role="alert">{{ templateError }}</v-alert>
            </v-card-text>
            <v-card-text v-else>
                <p class="text-body-2 mb-3">Übergabetext für die Erstellung der Leistungsfeststellung erzeugen.</p>
                <div class="exercise-deadline-fields mb-3">
                    <v-text-field v-model="deadlineDate" label="Endgültige Abgabefrist" type="date" :disabled="loading" hide-details />
                    <v-text-field v-model="deadlineTime" label="Uhrzeit (optional)" type="time" :disabled="loading" hide-details />
                </div>
                <p class="text-caption mb-3">Die endgültige Frist wird beim Erstellen gespeichert.</p>
                <p v-if="!deadlineValid" class="text-caption text-error mb-3" role="status">Bitte ein gültiges Datum und eine gültige Uhrzeit eingeben.</p>
                <p class="text-body-2 mb-3"><strong>Prüfungsvorlage:</strong> {{ formatPoints(templatePoints) }} Punkte</p>
                <v-text-field v-model="templatePointsTarget" label="Alternative Gesamtpunkte für das Aufgabenblatt (optional)" type="number"
                    min="0" max="100000" step="0.5" :disabled="loading" hide-details />
                <p class="text-caption mb-3">Für das Aufgabenblatt gelten {{ formatPoints(effectiveTemplatePoints) }} Punkte. Leer übernimmt die Punkte der Vorlage.</p>
                <p v-if="!templateTargetValid" class="text-caption text-error mb-3" role="alert">Bitte eine nichtnegative ganze oder halbe Punktzahl eingeben.</p>
                <fieldset class="exercise-mc-choice" :disabled="loading || selectionLoading || !!selectionError">
                    <legend>Multiple-Choice-Teil</legend>
                    <label><input v-model="includeMc" type="radio" :value="false" aria-label="Multiple-Choice-Teil: Nein" /> Nein</label>
                    <label><input v-model="includeMc" type="radio" :value="true" aria-label="Multiple-Choice-Teil: Ja" /> Ja</label>
                </fieldset>
                <p class="text-caption mb-3">Multiple Choice wird getrennt in Schooltool bereitgestellt und gehört nicht zum Prüfungsordner.</p>
                <p v-if="selectionLoading" class="text-caption" role="status">Testzuordnung wird geladen …</p>
                <v-alert v-if="selectionError" type="warning" variant="tonal" role="alert">{{ selectionError }}</v-alert>
                <v-btn v-if="selectionError" variant="text" @click="loadDialogData()">Zuordnung erneut prüfen</v-btn>
                <template v-if="includeMc">
                    <v-select v-model="selectedCatalogId" label="Multiple-Choice-Katalog" :items="availableCatalogs" item-title="title" item-value="id"
                        :disabled="loading || catalogsLoading || !!selectionError" hide-details />
                    <p v-if="catalogsLoading" class="text-caption" role="status">Kataloge werden geladen …</p>
                    <v-alert v-if="catalogsError" type="warning" variant="tonal" role="alert">{{ catalogsError }}</v-alert>
                    <v-btn v-if="catalogsError" variant="text" @click="loadCatalogs()">Kataloge erneut laden</v-btn>
                    <p v-else-if="!catalogsLoading && !availableCatalogs.length" class="text-caption" role="status">Kein berechtigter Multiple-Choice-Katalog vorhanden.</p>
                    <p v-if="selectedCatalogId && testsLoading" class="text-caption" role="status">Passende Tests werden geprüft …</p>
                    <v-alert v-if="testsError" type="warning" variant="tonal" role="alert">{{ testsError }}</v-alert>
                    <v-btn v-if="testsError" variant="text" @click="loadAvailableTests()">Tests erneut prüfen</v-btn>
                    <p v-else-if="testsMessage" class="text-caption" role="status">{{ testsMessage }}</p>
                    <template v-if="selectedCatalogId && !testsLoading && !testsError">
                        <v-select v-if="availableTests.length > 1" v-model="selectedTestId" label="Passender Multiple-Choice-Test" :items="availableTests"
                            item-title="title" item-value="id" :disabled="loading" hide-details />
                        <p v-else-if="availableTests.length === 1" class="text-caption mt-2">Vorhandener Test: {{ availableTests[0].title }}</p>
                        <p v-else class="text-caption mt-2">Für diese Durchführung wird ein neuer Test aus dem gewählten Katalog vorbereitet.</p>
                        <p v-if="selectedTest" class="text-caption mt-2">Test: {{ selectedTest.question_count }} Fragen, {{ selectedTest.total_points }} Punkte, {{ selectedTest.duration_minutes }} Minuten.</p>
                        <template v-if="newTestRequired && newTestSettings">
                            <p class="text-caption mt-2 mb-2">{{ availableQuestionCount }} aktive, geprüfte Fragen für diesen Test verfügbar.</p>
                            <div class="exercise-deadline-fields">
                                <v-text-field v-model="newTestSettings.question_count" label="Fragenanzahl" type="number"
                                    min="1" :max="availableQuestionCount" step="1" :disabled="loading"
                                    :error-messages="newTestErrors.question_count" hide-details="auto" />
                                <v-text-field v-model="newTestSettings.total_points" label="Testpunkte" type="number"
                                    min="0.01" max="100000" step="0.01" :disabled="loading"
                                    :error-messages="newTestErrors.total_points" hide-details="auto" />
                                <v-text-field v-model="newTestSettings.duration_minutes" label="Bearbeitungszeit (Minuten)" type="number"
                                    min="1" max="1440" step="1" :disabled="loading"
                                    :error-messages="newTestErrors.duration_minutes" hide-details="auto" />
                            </div>
                        </template>
                    </template>
                </template>
                <v-divider class="my-4" />
                <v-textarea v-model="instructions" label="Ergänzende Vorgaben oder Antworten auf Rückfragen" :disabled="loading" rows="3" class="mt-3" hide-details />
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
                <v-alert v-if="message" :type="copied ? 'success' : 'warning'" variant="tonal" role="status" aria-live="polite" class="mt-3">{{ message }}</v-alert>
                <template v-if="result">
                    <p class="text-caption mt-3">{{ result.participants_count }} bestätigte Prüfungskandidaten.</p>
                    <details class="mt-2"><summary>Übergabetext anzeigen</summary>
                        <textarea ref="copyText" :value="handoffText" readonly rows="9" aria-label="Leistungsfeststellungsauftrag zum Kopieren" class="exercise-export-text" />
                    </details>
                </template>
            </v-card-text>
            <v-card-actions v-if="!templateLoading" class="flex-wrap ga-2">
                <v-spacer />
                <v-btn :disabled="loading" @click="opened = false">{{ result ? 'Ende' : 'Abbrechen' }}</v-btn>
                <v-btn v-if="templateError" color="primary" @click="loadTemplatePoints(dialogSequence)">Erneut prüfen</v-btn>
                <v-btn v-else :color="copied ? 'success' : 'primary'" :prepend-icon="copied ? 'mdi-check' : undefined" :loading="loading"
                    :disabled="loading || !current || !deadlineValid || !templateTargetValid || selectionLoading || !!selectionError || mcSelectionBlocked"
                    @click="copyExport()">{{ copied ? 'Kopiert' : result ? 'Text kopieren' : 'Übergabetext kopieren' }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { VTextarea, VDivider } from 'vuetify/components'
import { aiExport, exerciseOptions, prepareExercise } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { loadWorkExercise, workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workLinkedMcTestTitle, workNeedsPreparation } from '@/helpers/workOverview'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import WorkProgressCheck from './WorkProgressCheck.vue'
import WorkCheckProgress from './WorkCheckProgress.vue'
import { createWorkCheckStream, useWorkCheckProgress } from './workCheckProgress'

const props = defineProps({ work: Object, course: Object, date: { type: String, default: '' }, disabled: Boolean, active: { type: Boolean, default: true }, hideFolderName: Boolean, hideActivator: Boolean, hideMcTest: Boolean, hideCheckTime: Boolean, checkProgress: Boolean, deadlineDraft: { type: Object, default: null } })
const emit = defineEmits(['exercise-status', 'progress-checked', 'deadline-saved', 'check-busy'])
const admin = useAdminStore()
const current = computed(() => Boolean(props.work?.id && props.course?.id && String(props.work.teaching_course_id) === String(props.course.id)))
const opened = ref(false)
const deadlineDate = ref('')
const deadlineTime = ref('')
const savingDeadline = ref(false)
let pendingSavedDeadline = null
const deadlineValid = computed(() => (!deadlineDate.value || /^\d{4}-\d{2}-\d{2}$/.test(deadlineDate.value))
    && (!deadlineTime.value || (deadlineDate.value && /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(deadlineTime.value))))
const dialogTitle = ref(null)
const templateStatus = ref(null)
const linkedMcTest = computed(() => workLinkedMcTestTitle(props.work, props.date))
const includeMc = ref(false)
const selectedTestId = ref(null)
const savedTestId = ref(null)
const savedCatalogId = ref(null)
const selectedCatalogId = ref(null)
const availableCatalogs = ref([])
const catalogsLoading = ref(false)
const catalogsError = ref('')
const availableTests = ref([])
const selectedTest = computed(() => availableTests.value.find(test => test.id === selectedTestId.value) || null)
const newTestSettings = ref(null)
const availableQuestionCount = ref(null)
const serverTestErrors = ref({})
const newTestRequired = computed(() => includeMc.value && !!selectedCatalogId.value
    && !testsLoading.value && !testsError.value && availableTests.value.length === 0 && selectedTestId.value === null)
const invalidTestSettings = computed(() => {
    if (!newTestRequired.value || !newTestSettings.value || availableQuestionCount.value === null) return {}
    const { question_count, total_points, duration_minutes } = newTestSettings.value
    const errors = {}
    if (availableQuestionCount.value === 0) {
        errors.question_count = 'Der Katalog enthält keine aktiven, geprüften Fragen.'
    } else if (!/^\d+$/.test(String(question_count)) || Number(question_count) < 1 || Number(question_count) > availableQuestionCount.value) {
        errors.question_count = `Bitte eine ganze Zahl von 1 bis ${availableQuestionCount.value} eingeben.`
    }
    if (!/^\d+(?:\.\d{1,2})?$/.test(String(total_points)) || Number(total_points) < 0.01 || Number(total_points) > 100000) {
        errors.total_points = 'Bitte 0,01 bis 100.000 Punkte mit höchstens zwei Nachkommastellen eingeben.'
    }
    if (!/^\d+$/.test(String(duration_minutes)) || Number(duration_minutes) < 1 || Number(duration_minutes) > 1440) {
        errors.duration_minutes = 'Bitte eine ganze Zahl von 1 bis 1.440 Minuten eingeben.'
    }
    return errors
})
const newTestErrors = computed(() => ({ ...serverTestErrors.value, ...invalidTestSettings.value }))
const mcSelectionBlocked = computed(() => includeMc.value && (catalogsLoading.value || !!catalogsError.value
    || !selectedCatalogId.value || testsLoading.value || !!testsError.value
    || (newTestRequired.value && (!newTestSettings.value || availableQuestionCount.value === null
        || Object.keys(invalidTestSettings.value).length > 0))
    || (availableTests.value.length > 1 && !selectedTestId.value)))
const selectionLoading = ref(false)
const testsLoading = ref(false)
const selectionError = ref('')
const testsError = ref('')
const testsMessage = ref('')
const templatePoints = ref(null)
const templatePointsTarget = ref(null)
const hasTemplateTarget = computed(() => templatePointsTarget.value !== null && String(templatePointsTarget.value).trim() !== '')
const templateTargetValid = computed(() => !hasTemplateTarget.value || (Number.isFinite(Number(templatePointsTarget.value))
    && Number(templatePointsTarget.value) >= 0 && Number(templatePointsTarget.value) <= 100000 && Number.isInteger(Number(templatePointsTarget.value) * 2)))
const effectiveTemplatePoints = computed(() => hasTemplateTarget.value && templateTargetValid.value ? Number(templatePointsTarget.value) : templatePoints.value)
const formatPoints = value => new Intl.NumberFormat('de-AT', { maximumFractionDigits: 1 }).format(value)
const templateLoading = ref(false)
const templateError = ref('')
let dialogSequence = 0
let testsSequence = 0
let catalogsSequence = 0
let templateSequence = 0
const loading = ref(false)
const result = ref(null)
const error = ref('')
const message = ref('')
const copied = ref(false)
const copyText = ref(null)
const instructions = ref('')
const handoffText = computed(() => result.value ? `${result.value.text}${instructions.value.trim() ? `\n\nErgänzende Vorgaben oder Antworten auf Rückfragen:\n${instructions.value.trim()}` : ''}` : '')
const exerciseFolder = ref(null)
const preparation = ref(null)
const schooltoolScale = computed(() => {
    const value = props.course?.teaching_entry_area?.entry_definitions
        ?.find(entry => entry.category === 'Benotung' && entry.short_name === props.work?.type)?.maximum_points
    return value !== null && value !== undefined && Number.isFinite(Number(value)) ? Number(value) : null
})
const exerciseChecking = ref(false)
const checkedStatus = ref('unknown')
const checkedAt = ref(null)
const checkTime = computed(() => checkedAt.value ? new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna',
    dateStyle: 'short', timeStyle: 'short' }).format(new Date(checkedAt.value)) : '')
const moduleLinked = ref(false)
const exerciseMessage = ref('')
const binding = computed(() => workDropboxStates.get(workDropboxKey(admin.config?.user?.id, props.work)))
const needsPreparation = computed(() => workAutomationEnabled(props.work) && workNeedsPreparation(props.work, binding.value))
const bindingVersion = computed(() => JSON.stringify([binding.value?.connected, binding.value?.link_version, binding.value?.module_link]))
let sequence = 0
let controller = null
let exerciseSequence = 0
const { state: checkProgressState, unavailable: progressUnavailable, start: startProgressPolling,
    update: updateProgress, finish: finishProgressPolling, reset: resetProgressPolling } = useWorkCheckProgress()

async function checkExercise(fresh = false) {
    const operation = ++exerciseSequence
    exerciseFolder.value = null
    preparation.value = null
    moduleLinked.value = false
    opened.value = false
    exerciseMessage.value = ''
    checkedAt.value = null
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const cached = !fresh && props.active && current.value ? workExerciseCheck(key, props.work, props.date) : null
    exerciseChecking.value = props.active && current.value && needsPreparation.value && !cached
    const context = { userId: admin.config?.user?.id, workId: props.work?.id, courseId: props.course?.id, date: props.date }
    const publish = (status, folder = null, emailPreparation = null, emailDispatch = null, assignmentStatus = null, prepared = null) => {
        checkedStatus.value = status
        preparation.value = prepared
        emit('exercise-status', { ...context, status, folder, emailPreparation, emailDispatch, assignmentStatus, preparation: prepared, checkedAt: checkedAt.value, checking: exerciseChecking.value })
    }
    if (cached) {
        checkedAt.value = cached.checkedAt
        moduleLinked.value = Boolean(cached.data.connected && cached.data.module_link?.folder?.id)
        exerciseFolder.value = cached.status === 'found' ? cached.folder : null
    }
    const displayed = cached
    if (displayed) checkedAt.value = displayed.checkedAt
    publish(displayed ? displayed.status : exerciseChecking.value ? 'checking' : 'unknown', displayed?.folder,
        displayed?.data?.exercise?.email_preparation, displayed?.data?.exercise?.email_dispatch, displayed?.data?.exercise?.assignment_status, displayed?.data?.exercise?.preparation)
    if (!props.active || !current.value || !needsPreparation.value) return
    const progressId = fresh ? crypto.randomUUID() : null
    let progressOperation = null
    let finalProgress = null
    let progressFailed = false
    if (fresh) {
        progressOperation = startProgressPolling(props.work.id, progressId, ['connection', 'folder', 'verification'], true)
        emit('check-busy', true)
    }
    try {
        const stream = fresh ? createWorkCheckStream(progress => updateProgress(progressOperation, progress)) : null
        const data = await loadWorkExercise(key, props.work, props.date, fresh, fresh ? props.deadlineDraft : null, fresh && props.checkProgress, progressId, stream, fresh)
        if (operation !== exerciseSequence) return
        if (fresh && data.progress) finalProgress = data.progress
        const savedWork = data.saved_work
        if (fresh && props.checkProgress && data.collection_error) {
            const failure = new Error('Der Arbeitsstand konnte nicht geprüft werden.')
            failure.response = { data: data.collection_error }
            throw failure
        }
        if (fresh && props.checkProgress && (String(savedWork?.id) !== String(context.workId)
            || String(savedWork?.teaching_course_id) !== String(context.courseId))) {
            throw new Error('Der Arbeitsstand konnte nicht geprüft werden.')
        }
        checkedAt.value = workExerciseCheck(key, savedWork || props.work, props.date)?.checkedAt || null
        moduleLinked.value = Boolean(data.connected && data.module_link?.folder?.id)
        if (data.connected && data.module_link?.folder?.id && data.exercise?.status === 'found'
            && typeof data.exercise.folder?.name === 'string' && data.exercise.folder.name.trim()) {
            exerciseFolder.value = data.exercise.folder
            publish('found', data.exercise.folder, data.exercise.email_preparation, data.exercise.email_dispatch, data.exercise.assignment_status, data.exercise.preparation)
            opened.value = false
            exerciseMessage.value = data.deadline_sync?.message || ''
        } else {
            publish(data.exercise?.status === 'missing' ? 'missing' : 'unknown')
            if (data.exercise?.status === 'unknown') {
                exerciseMessage.value = data.exercise.message || 'Der Anlagezustand konnte nicht bestätigt werden.'
            }
        }
    } catch (failure) {
        progressFailed = true
        if (operation === exerciseSequence) {
            checkedAt.value = workExerciseCheck(workDropboxKey(admin.config?.user?.id, props.work), props.work, props.date)?.checkedAt || null
            publish('unknown')
            exerciseMessage.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
                || failure.response?.data?.message || 'Der Anlagezustand konnte nicht geprüft werden. Bitte den Dialog erneut öffnen.'
        }
    } finally {
        if (fresh && finishProgressPolling(progressOperation, finalProgress, progressFailed)) {
            if (operation !== exerciseSequence && checkProgressState.value?.state === 'running') checkProgressState.value = null
            emit('check-busy', false)
        }
        if (operation === exerciseSequence) exerciseChecking.value = false
    }
}

function clear() {
    sequence++
    controller?.abort()
    loading.value = false
    result.value = null
    error.value = ''
    message.value = ''
    copied.value = false
}

function openDialog() {
    if (opened.value || !props.active || !needsPreparation.value || !current.value || !moduleLinked.value || props.disabled || exerciseChecking.value
        || (checkedStatus.value !== 'missing' && preparation.value?.status !== 'ready')) return
    clear()
    instructions.value = ''
    templatePointsTarget.value = null
    newTestSettings.value = null
    serverTestErrors.value = {}
    deadlineDate.value = props.deadlineDraft ? props.deadlineDraft.date || '' : props.work?.finish_until_date?.slice(0, 10) || ''
    deadlineTime.value = props.deadlineDraft ? props.deadlineDraft.time || '' : props.work?.finish_until_time || ''
    opened.value = true
    nextTick(() => { if (opened.value) dialogTitle.value?.focus() })
    loadDialogData()
}

defineExpose({ openDialog, recheck: () => checkExercise(true) })

function updateDialog(value) {
    if (!value && loading.value) return
    opened.value = value
}

async function loadDialogData() {
    const operation = ++dialogSequence
    const workId = props.work.id
    const params = props.date ? { date: props.date } : {}
    savedTestId.value = null
    savedCatalogId.value = null
    selectedTestId.value = null
    selectedCatalogId.value = null
    includeMc.value = false
    availableCatalogs.value = []
    catalogsError.value = ''
    availableTests.value = []
    availableQuestionCount.value = null
    selectionError.value = ''
    testsError.value = ''
    testsMessage.value = ''
    selectionLoading.value = true
    testsLoading.value = false
    templatePoints.value = null
    const active = () => operation === dialogSequence && opened.value && current.value && String(props.work.id) === String(workId)
    loadTemplatePoints(operation)
    try {
        const { data } = await axios.get(exerciseOptions.url(workId), { params: { ...params, mc_selection: 'current' } })
        if (!active()) return
        if (data.linked_test_id !== null && !Number.isInteger(data.linked_test_id)) throw new Error('Die Testzuordnung konnte nicht bestätigt werden.')
        savedTestId.value = data.linked_test_id
        savedCatalogId.value = data.linked_catalog_id ?? null
        includeMc.value = data.linked_test_id !== null
        if (includeMc.value) {
            if (!Number.isInteger(data.linked_catalog_id)) throw new Error('Der Katalog des zugeordneten Tests konnte nicht bestätigt werden.')
            selectedCatalogId.value = data.linked_catalog_id
            await loadCatalogs(operation)
            if (active()) await loadAvailableTests(operation)
        }
    } catch (failure) {
        if (active()) selectionError.value = failure.response?.data?.message || failure.message || 'Die Testzuordnung konnte nicht geladen werden.'
    } finally {
        if (active()) selectionLoading.value = false
    }
}

async function loadTemplatePoints(operation) {
    const request = ++templateSequence
    const workId = props.work.id
    templateLoading.value = true
    templateError.value = ''
    templatePoints.value = null
    nextTick(() => { if (operation === dialogSequence && request === templateSequence && opened.value && templateLoading.value) templateStatus.value?.focus() })
    try {
        const { data } = await axios.get(exerciseOptions.url(workId), { params: { ...(props.date ? { date: props.date } : {}), mc_selection: 'template_points' } })
        if (operation !== dialogSequence || request !== templateSequence || !opened.value || !current.value || String(props.work.id) !== String(workId)) return
        if (typeof data.assessment_points !== 'number' || !Number.isFinite(data.assessment_points) || data.assessment_points < 0) {
            throw new Error('Die Punkte der Prüfungsvorlage konnten nicht eindeutig bestätigt werden.')
        }
        templatePoints.value = data.assessment_points
    } catch (failure) {
        if (operation === dialogSequence && request === templateSequence && opened.value) {
            templateError.value = failure.response?.data?.message || failure.message || 'Die Prüfungsvorlage konnte nicht geprüft werden.'
        }
    } finally {
        if (operation === dialogSequence && request === templateSequence && opened.value) templateLoading.value = false
    }
}

async function loadCatalogs(operation = dialogSequence) {
    const request = ++catalogsSequence
    catalogsLoading.value = true
    catalogsError.value = ''
    try {
        const { data } = await axios.get(exerciseOptions.url(props.work.id), { params: { ...(props.date ? { date: props.date } : {}), mc_selection: 'catalogs' } })
        if (operation !== dialogSequence || request !== catalogsSequence || !opened.value || !includeMc.value) return
        if (!Array.isArray(data.catalogs)) throw new Error('Die Kataloge konnten nicht bestätigt werden.')
        availableCatalogs.value = data.catalogs.filter(catalog => Number.isInteger(catalog.id) && typeof catalog.title === 'string')
        if (selectedCatalogId.value !== null && !availableCatalogs.value.some(catalog => catalog.id === selectedCatalogId.value)) {
            throw new Error('Der Katalog des zugeordneten Tests ist nicht mehr verfügbar.')
        }
    } catch (failure) {
        if (operation === dialogSequence && request === catalogsSequence && opened.value && includeMc.value) catalogsError.value = failure.response?.data?.message || failure.message || 'Die Kataloge konnten nicht geladen werden.'
    } finally {
        if (operation === dialogSequence && request === catalogsSequence && opened.value) catalogsLoading.value = false
    }
}

async function loadAvailableTests(operation = dialogSequence) {
    const catalogId = selectedCatalogId.value
    if (!catalogId) return
    const request = ++testsSequence
    testsLoading.value = true
    testsError.value = ''
    testsMessage.value = ''
    availableTests.value = []
    selectedTestId.value = null
    availableQuestionCount.value = null
    serverTestErrors.value = {}
    try {
        const { data } = await axios.get(exerciseOptions.url(props.work.id), { params: { ...(props.date ? { date: props.date } : {}), mc_selection: 'candidates', catalog_id: catalogId } })
        if (operation !== dialogSequence || request !== testsSequence || !opened.value || !includeMc.value || selectedCatalogId.value !== catalogId) return
        if (!Array.isArray(data.candidates) || !data.defaults
            || !['question_count', 'total_points', 'duration_minutes'].every(key => Number.isFinite(data.defaults[key]) && data.defaults[key] > 0)
            || (data.candidates.length === 0 && (!Number.isInteger(data.available_question_count)
                || data.available_question_count < 0 || data.available_question_count > 2000))) {
            throw new Error('Die passenden Tests und Vorgaben konnten nicht bestätigt werden.')
        }
        availableTests.value = data.candidates.filter(test => Number.isInteger(test.id) && typeof test.title === 'string')
        availableQuestionCount.value = data.available_question_count ?? null
        newTestSettings.value ??= { ...data.defaults }
        testsMessage.value = typeof data.message === 'string' ? data.message : ''
        if (catalogId === savedCatalogId.value && savedTestId.value !== null && !availableTests.value.some(test => test.id === savedTestId.value)) {
            throw new Error('Der zugeordnete Test ist nicht mehr verfügbar. Bitte die Zuordnung prüfen.')
        }
        if (availableTests.value.some(test => test.id === savedTestId.value)) selectedTestId.value = savedTestId.value
        else if (availableTests.value.length === 1) selectedTestId.value = availableTests.value[0].id
    } catch (failure) {
        if (operation === dialogSequence && request === testsSequence && opened.value && includeMc.value) testsError.value = failure.response?.data?.message || failure.message || 'Die vorhandenen Tests konnten nicht geladen werden.'
    } finally {
        if (operation === dialogSequence && request === testsSequence && opened.value) testsLoading.value = false
    }
}

async function copyExport() {
    if (!opened.value || props.disabled || !current.value || !moduleLinked.value || loading.value || !deadlineValid.value || !templateTargetValid.value
        || templateLoading.value || templateError.value || selectionLoading.value || selectionError.value || mcSelectionBlocked.value) return
    if (result.value) {
        const operation = sequence
        loading.value = true
        try {
            await copyResult(operation, null, !!message.value && !copied.value)
        } finally {
            if (operation === sequence) loading.value = false
        }
        return
    }
    clear()
    const operation = sequence
    const workId = props.work.id
    const courseId = props.course.id
    controller = new AbortController()
    const pendingCopy = startClipboardWrite(controller.signal)
    loading.value = true
    let deadlineSaved = false
    let selectionSaved = false
    serverTestErrors.value = {}
    const creationSettings = newTestRequired.value ? {
        question_count: Number(newTestSettings.value.question_count),
        total_points: Number(newTestSettings.value.total_points),
        duration_minutes: Number(newTestSettings.value.duration_minutes),
    } : {}
    try {
        const date = deadlineDate.value || null
        const time = deadlineTime.value || null
        if (date !== (props.work?.finish_until_date?.slice(0, 10) || null) || time !== (props.work?.finish_until_time || null)) {
            savingDeadline.value = true
            try {
                const checked = await loadWorkExercise(workDropboxKey(admin.config?.user?.id, props.work), props.work, props.date, true, { date, time })
                if (String(checked?.saved_work?.id) !== String(workId) || String(checked.saved_work.teaching_course_id) !== String(courseId)) {
                    throw new Error('Die gespeicherte Abgabefrist konnte nicht bestätigt werden.')
                }
                deadlineSaved = true
                pendingSavedDeadline = { date, time }
                emit('deadline-saved', { data: checked.saved_work })
                await nextTick()
                if (operation !== sequence || !opened.value || !current.value) return
                if (checked.exercise?.status !== 'missing' && checked.exercise?.preparation?.status !== 'ready') throw new Error('Der Anlagezustand hat sich geändert. Bitte den Dialog erneut öffnen und prüfen.')
            } finally {
                savingDeadline.value = false
            }
        }
        const requestedTestId = includeMc.value && selectedTestId.value ? Number(selectedTestId.value) : null
        const requestedCatalogId = includeMc.value ? Number(selectedCatalogId.value) : null
        {
            const changed = requestedTestId !== savedTestId.value
            const { data: selection } = await axios.post(prepareExercise.url(workId), { mc_selection: includeMc.value ? 'yes' : 'no',
                test_id: requestedTestId, ...(includeMc.value ? { catalog_id: requestedCatalogId, ...creationSettings } : {}),
                expected_test_id: savedTestId.value, ...(props.date ? { date: props.date } : {}) }, { signal: controller.signal })
            if (operation !== sequence || !opened.value || !current.value) return
            if ((includeMc.value && (!Number.isInteger(selection.linked_test_id) || selection.linked_catalog_id !== requestedCatalogId
                || (requestedTestId !== null && selection.linked_test_id !== requestedTestId)))
                || (!includeMc.value && selection.linked_test_id !== null) || String(selection.work?.id) !== String(workId)
                || String(selection.work?.teaching_course_id) !== String(courseId)) throw new Error('Die Testzuordnung konnte nicht bestätigt werden.')
            savedTestId.value = selection.linked_test_id
            savedCatalogId.value = selection.linked_catalog_id ?? null
            if (selection.test?.id === selection.linked_test_id && !availableTests.value.some(test => test.id === selection.test.id)) {
                availableTests.value = [selection.test]
            }
            selectedTestId.value = selection.linked_test_id
            selectionSaved = changed || selection.action === 'created'
            if (selectionSaved) emit('deadline-saved', { data: selection.work })
        }
        const { data } = await axios.get(aiExport.url(workId), { params: { purpose: 'exercise', include_mc: 0,
            ...(templatePoints.value !== null ? { template_points_snapshot: templatePoints.value } : {}),
            ...(hasTemplateTarget.value ? { template_points_target: Number(templatePointsTarget.value) } : {}),
            ...(props.date ? { date: props.date } : {}) }, signal: controller.signal })
        if (operation !== sequence || !opened.value || !current.value) return
        const exported = data.data
        if (String(exported?.work_id) !== String(workId) || String(exported?.course_id) !== String(courseId)
            || exported.roster_complete !== true || !Number.isInteger(exported.participants_count) || exported.participants_count < 1
            || typeof exported.text !== 'string' || !exported.text.trim() || exported.context?.include_multiple_choice !== false) {
            throw new Error('Arbeitsdaten und Prüfungskandidaten konnten nicht vollständig bestätigt werden.')
        }
        result.value = exported
        await nextTick()
        await copyResult(operation, pendingCopy)
    } catch (failure) {
        if (operation === sequence) {
            const validationErrors = failure.response?.data?.errors || {}
            if (newTestRequired.value) {
                serverTestErrors.value = Object.fromEntries(['question_count', 'total_points', 'duration_minutes']
                    .filter(field => Array.isArray(validationErrors[field]) && validationErrors[field].length)
                    .map(field => [field, validationErrors[field]]))
            }
            error.value = `${deadlineSaved ? 'Die Abgabefrist wurde gespeichert. ' : ''}${selectionSaved ? 'Die Testzuordnung wurde gespeichert. ' : ''}${Object.values(validationErrors).flat()[0]
                || failure.response?.data?.message || failure.message || 'Der Übergabetext konnte nicht geladen werden.'}`
        }
    } finally {
        pendingCopy?.dispose()
        if (operation === sequence) loading.value = false
    }
}

function startClipboardWrite(signal) {
    const clipboard = navigator.clipboard
    if (typeof globalThis.ClipboardItem !== 'function' || typeof clipboard?.write !== 'function') return null
    let sendText
    let rejectText
    const content = new Promise((resolve, reject) => { sendText = resolve; rejectText = reject })
    const cancel = () => rejectText(new DOMException('Kopieren abgebrochen', 'AbortError'))
    content.catch(() => {})
    signal.addEventListener('abort', cancel, { once: true })
    const dispose = () => {
        signal.removeEventListener('abort', cancel)
        cancel()
    }
    try {
        // Start during the click. Supply content only after the work and roster have been verified.
        const written = clipboard.write([new ClipboardItem({ 'text/plain': content })]).then(() => true, () => false)
        return {
            written,
            provide(text) { if (!signal.aborted) sendText(new Blob([text], { type: 'text/plain' })) },
            dispose,
        }
    } catch {
        dispose()
        return null
    }
}

function selectHandoff() {
    const details = copyText.value?.closest('details')
    if (details) details.open = true
    copyText.value?.focus()
    copyText.value?.select()
}

function copySelection() {
    selectHandoff()
    try {
        return document.execCommand?.('copy') === true
    } catch {
        return false
    }
}

async function copyResult(operation, pendingCopy = null, retry = false) {
    if (operation !== sequence || !opened.value || !current.value || !handoffText.value) return
    const text = handoffText.value
    copied.value = false
    message.value = ''
    let success = false
    if (pendingCopy) {
        pendingCopy.provide(text)
        success = await pendingCopy.written
    } else if (retry) {
        // The fallback also runs within the new click if write permission was previously denied.
        success = copySelection()
    }
    if (operation !== sequence) return
    if (!success) {
        try {
            await navigator.clipboard.writeText(text)
            success = true
        } catch {
            if (operation !== sequence) return
            if (!retry) success = copySelection()
        }
    }
    if (operation !== sequence) return
    copied.value = success
    message.value = success
        ? 'Übergabetext kopiert. Der vollständige Text ist jetzt in der Zwischenablage und kann an die KI übergeben werden.'
        : 'Automatisches Kopieren war nicht möglich. Bitte „Text kopieren“ anklicken oder den markierten Text mit Strg+C kopieren.'
}

watch([() => admin.config?.user?.id, () => props.work?.id, () => props.course?.id, () => props.date], () => {
    clear()
    pendingSavedDeadline = null
    if (checkProgressState.value?.state === 'running') emit('check-busy', false)
    resetProgressPolling()
    opened.value = false
})
watch(opened, value => { if (!value) { dialogSequence++; clear() } })
watch(includeMc, value => {
    if (!opened.value || selectionLoading.value) return
    if (value) {
        loadCatalogs()
        if (selectedCatalogId.value) loadAvailableTests()
    } else { catalogsSequence++; testsSequence++; catalogsLoading.value = false; testsLoading.value = false; catalogsError.value = ''; testsError.value = '' }
})
watch(selectedCatalogId, value => {
    if (!opened.value || selectionLoading.value || !includeMc.value) return
    if (value) loadAvailableTests()
    else { testsSequence++; availableTests.value = []; selectedTestId.value = null; availableQuestionCount.value = null; testsLoading.value = false; testsError.value = '' }
})
watch(newTestSettings, () => { serverTestErrors.value = {} }, { deep: true })
watch([includeMc, selectedCatalogId, selectedTestId, templatePointsTarget], () => { if (opened.value && result.value) clear() })
watch(() => [deadlineDate.value, deadlineTime.value], () => { if (opened.value && result.value) clear() })
watch(instructions, () => { copied.value = false; message.value = '' })
watch(() => props.disabled, disabled => { if (disabled) { clear(); opened.value = false } })
watch([() => admin.config?.user?.id, () => props.work?.id, () => props.course?.id, () => props.date, () => props.active,
    bindingVersion, needsPreparation, () => JSON.stringify(props.work?.communicated_deadline)],
() => { if (!savingDeadline.value) checkExercise() }, { immediate: true })
watch(() => props.work?.title, (title, previous) => {
    if (title !== previous && !savingDeadline.value) checkExercise(true)
})
watch([() => props.work?.finish_until_date, () => props.work?.finish_until_time], ([date, time]) => {
    if (pendingSavedDeadline && (date?.slice(0, 10) || null) === pendingSavedDeadline.date && (time || null) === pendingSavedDeadline.time) {
        pendingSavedDeadline = null
        return
    }
    if (!savingDeadline.value) checkExercise()
})
onBeforeUnmount(() => { clear(); resetProgressPolling(); exerciseSequence++; dialogSequence++ })
</script>

<style scoped>
.exercise-deadline-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 8px; }
.exercise-mc-choice { border: 0; padding: 0; margin: 0 0 12px; display: flex; gap: 20px; }
.exercise-mc-choice legend { font-weight: 600; margin-bottom: 6px; }
.exercise-template-loading { min-height: 180px; display: grid; place-items: center; }
.exercise-template-status { display: flex; flex-direction: column; align-items: center; gap: 16px; text-align: center; }
.exercise-action { display: inline-block; max-width: 100%; }
.exercise-action--progress { flex-basis: 100%; width: 100%; }
.exercise-check-button { height: auto; min-height: 32px; padding-block: 6px; }
.exercise-check-message { flex-basis: 100%; }
.exercise-folder { display: block; white-space: normal; overflow-wrap: anywhere; }
.exercise-export-text {
    width: 100%;
    padding: 8px;
    border: 1px solid currentColor;
    border-radius: 4px;
    font-size: 0.75rem;
}
</style>
