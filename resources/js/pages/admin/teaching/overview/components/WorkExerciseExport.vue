<template>
    <div v-if="needsPreparation" class="exercise-action" :class="{ 'exercise-action--progress': checkProgressState }" @click.stop>
        <template v-if="exerciseFolder">
            <span v-if="!hideFolderName" class="exercise-folder text-body-2" aria-label="Leistungsfeststellungsordner">LF: {{ exerciseFolder.name }}</span>
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
        <span v-if="exerciseMessage" role="status" class="exercise-check-message d-block text-caption mt-1">{{ exerciseMessage }}</span>
        <WorkCheckProgress :state="checkProgressState" :unavailable="progressUnavailable" />
    </div>
    <span v-if="!needsPreparation && linkedMcTest && !hideMcTest" class="exercise-folder text-body-2">MC-Test: {{ linkedMcTest }}</span>
    <WorkProgressCheck v-if="workAutomationEnabled(work) && checkProgress && !needsPreparation && active && current" :work="work" :disabled="disabled"
        :check-key="workDropboxKey(admin.config?.user?.id, work)" @checked="emit('progress-checked', $event)"
        @saved="emit('deadline-saved', $event)" @busy="emit('check-busy', $event)" />
    <v-dialog v-if="opened" :model-value="opened" max-width="640" :persistent="loading || checkingModuleQuestions"
        @update:model-value="updateDialog">
        <v-card>
            <v-card-title class="text-wrap"><span ref="dialogTitle" tabindex="-1">Leistungsfeststellung erstellen</span></v-card-title>
            <v-card-text v-if="checkingModuleQuestions" class="module-questions-loading">
                <div ref="moduleQuestionsStatus" role="status" aria-live="polite" tabindex="-1" class="module-questions-status">
                    <v-progress-circular indeterminate color="primary" size="48" width="5" aria-hidden="true" />
                    <span>Multiple-Choice-Fragen werden geprüft …</span>
                </div>
            </v-card-text>
            <v-card-text v-else>
                <p class="text-body-2 mb-3">Übergabetext für die Erstellung der Leistungsfeststellung erzeugen.</p>
                <div class="exercise-deadline-fields mb-3">
                    <v-text-field v-model="deadlineDate" label="Endgültige Abgabefrist" type="date" :disabled="loading" hide-details />
                    <v-text-field v-model="deadlineTime" label="Uhrzeit (optional)" type="time" :disabled="loading" hide-details />
                </div>
                <p class="text-caption mb-3">Die endgültige Frist wird beim Erstellen gespeichert. Ohne Uhrzeit endet ein MC-Test um 23:59 Uhr.</p>
                <p v-if="!deadlineValid" class="text-caption text-error mb-3" role="status">Bitte ein gültiges Datum und eine gültige Uhrzeit eingeben.</p>
                <div class="exercise-points mb-3">
                    <p><strong>Prüfungsvorlage:</strong> <template v-if="templatePoints !== null">{{ templatePoints }} Punkte</template><template v-else>Punkte nicht eindeutig ermittelt</template></p>
                    <p><strong>Multiple-Choice-Teil:</strong> <template v-if="moduleQuestions === 'available' && mcPointsDisplayValid">{{ mcPoints }} Punkte (vorgesehener Anteil)</template><template v-else-if="moduleQuestions === 'available'">Punkte bitte gültig festlegen</template><template v-else-if="moduleQuestions === 'error'">Nicht eindeutig geprüft</template><template v-else>Kein passender Fragenkatalog verfügbar</template></p>
                </div>
                <p v-if="moduleQuestions === 'missing'" class="text-caption" role="status">Kein verwendbarer Multiple-Choice-Fragenkatalog gefunden.</p>
                <p v-else-if="moduleQuestions === 'error'" class="text-caption text-warning" role="status">Multiple-Choice-Fragen konnten nicht eindeutig geprüft werden. Bitte den Dialog erneut öffnen.</p>
                <p v-else-if="moduleQuestions === 'no-test'" class="text-caption" role="status">Kein passender Multiple-Choice-Test für diese Arbeit und das verknüpfte Lernmodul vorhanden.</p>
                <v-checkbox v-else-if="moduleQuestions === 'available'" v-model="includeMc" label="Multiple-Choice-Fragen einbeziehen" :disabled="loading || mcDispatchLocked" hide-details />
                <div v-if="includeMc" class="mt-4">
                    <p class="text-caption mb-3">Individueller Start beim Öffnen des persönlichen Links. Als Schlussfrist gilt das Enddatum der Arbeit; ohne Uhrzeit gilt 23:59:00 Uhr.</p>
                    <template v-if="mcOptions?.available">
                        <v-alert v-if="mcOptions.configuration?.locked" type="warning" variant="tonal" class="mb-3">{{ mcOptions.configuration.message }}</v-alert>
                        <p v-if="mcOptions.eligible_test" class="text-caption mt-2">Zugeordneter MC-Test: {{ mcOptions.eligible_test.title }}</p>
                        <template v-else>
                            <p class="text-caption mt-2">Fragenkatalog: {{ moduleCatalog.count }} Fragen verfügbar</p>
                            <v-text-field v-model.number="mcQuestionCount" label="Anzahl der Fragen" type="number" min="1" :max="moduleCatalog.count" :disabled="loading" hide-details />
                            <v-text-field v-model.number="mcDurationMinutes" label="MC-Bearbeitungszeit (Minuten)" type="number" min="1" max="1440" :disabled="loading" hide-details />
                        </template>
                        <v-text-field v-model.number="mcPoints" label="MC-Punkte" type="number" min="0.01" max="100000"
                            step="0.01" :disabled="loading || mcOptions.configuration?.locked" class="mt-3" hide-details />
                        <v-text-field v-model.number="mcOtherPoints" label="Punkte übrige Aufgaben" type="number" min="0" max="100000"
                            step="0.01" :disabled="loading || mcOptions.configuration?.locked" class="mt-3" hide-details />
                        <p v-if="mcPointsValid" class="text-caption mt-2" role="status">{{ mcPoints }} Punkte MC + {{ mcOtherPoints }} Punkte übrige Aufgaben = {{ assessmentPoints }} Punkte gesamt.<template v-if="mcOptions.eligible_test"> Die Punkte des vorhandenen MC-Tests werden auf diesen Anteil umgerechnet.</template></p>
                        <p v-if="mcPointsValid && assessmentPoints !== Number(mcMaximumPoints)" class="text-caption mt-2">Die Bewertungsskala in Schooltool beträgt {{ mcMaximumPoints }} Punkte. Die Umrechnung für die spätere Übernahme ist noch abzustimmen.</p>
                        <p v-if="!mcPointsValid" class="text-caption mt-2" role="status">Bitte gültige Punkte für den MC-Test und die übrigen Aufgaben angeben.</p>
                    </template>
                    <v-alert v-else-if="!error" type="warning" variant="tonal">Multiple Choice ist noch nicht verfügbar.</v-alert>
                </div>
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
            <v-card-actions v-if="!checkingModuleQuestions" class="flex-wrap ga-2">
                <v-spacer />
                <v-btn :disabled="loading" @click="opened = false">{{ result ? 'Ende' : 'Abbrechen' }}</v-btn>
                <v-btn :color="copied ? 'success' : 'primary'" :prepend-icon="copied ? 'mdi-check' : undefined" :loading="loading" :disabled="loading || !current || !mcReady || !deadlineValid" @click="copyExport()">{{ copied ? 'Kopiert' : result ? 'Text kopieren' : 'Übergabetext kopieren' }}</v-btn>
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
const includeMc = ref(false)
const moduleQuestions = ref('loading')
const templatePoints = ref(null)
const checkingModuleQuestions = computed(() => moduleQuestions.value === 'loading')
const moduleQuestionsStatus = ref(null)
const dialogTitle = ref(null)
let moduleQuestionsSequence = 0
const linkedMcTest = computed(() => workLinkedMcTestTitle(props.work, props.date))
const mcOptions = ref(null)
const moduleCatalog = ref(null)
const mcQuestionCount = ref(10)
const mcDurationMinutes = ref(7)
const mcPoints = ref(2)
const mcPointsDisplayValid = computed(() => Number(mcPoints.value) > 0 && /^\d+(?:\.\d{1,2})?$/.test(String(mcPoints.value)))
const mcMaximumPoints = computed(() => mcOptions.value?.configuration?.maximum_points ?? null)
const enteredOtherPoints = ref(null)
const otherPointsEdited = ref(false)
const mcOtherPoints = computed({
    get: () => otherPointsEdited.value ? enteredOtherPoints.value
        : (!mcOptions.value?.eligible_test && moduleCatalog.value && templatePoints.value !== null ? templatePoints.value
            : Math.max(0, Math.round((Number(mcMaximumPoints.value) - Number(mcPoints.value)) * 100) / 100)),
    set: value => { otherPointsEdited.value = true; enteredOtherPoints.value = value },
})
const assessmentPoints = computed(() => Math.round((Number(mcPoints.value) + Number(mcOtherPoints.value)) * 100) / 100)
const mcPointsValid = computed(() => Number(mcMaximumPoints.value) > 0 && Number(mcPoints.value) > 0
    && /^\d+(?:\.\d{1,2})?$/.test(String(mcPoints.value)) && mcOtherPoints.value !== null
    && /^\d+(?:\.\d{1,2})?$/.test(String(mcOtherPoints.value)) && assessmentPoints.value <= 100000)
const mcDispatchLocked = ref(false)
const mcReady = computed(() => !includeMc.value || (!mcDispatchLocked.value && !mcOptions.value?.configuration?.locked
    && mcPointsValid.value && mcOptions.value?.available && (Boolean(mcOptions.value?.eligible_test)
        || (moduleCatalog.value && Number.isInteger(mcQuestionCount.value) && mcQuestionCount.value > 0
            && mcQuestionCount.value <= moduleCatalog.value.count && Number.isInteger(mcDurationMinutes.value)
            && mcDurationMinutes.value > 0 && mcDurationMinutes.value <= 1440))))
let mcPayload = null
let expectedTestId = null
const loading = ref(false)
const result = ref(null)
const error = ref('')
const message = ref('')
const copied = ref(false)
const copyText = ref(null)
const instructions = ref('')
const handoffText = computed(() => result.value ? `${result.value.text}${instructions.value.trim() ? `\n\nErgänzende Vorgaben oder Antworten auf Rückfragen:\n${instructions.value.trim()}` : ''}` : '')
const exerciseFolder = ref(null)
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
    moduleLinked.value = false
    opened.value = false
    exerciseMessage.value = ''
    mcDispatchLocked.value = false
    checkedAt.value = null
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const previous = fresh && props.active && current.value ? workExerciseCheck(key, props.work, props.date) : null
    const cached = !fresh && props.active && current.value ? workExerciseCheck(key, props.work, props.date) : null
    exerciseChecking.value = props.active && current.value && needsPreparation.value && !cached
    const context = { userId: admin.config?.user?.id, workId: props.work?.id, courseId: props.course?.id, date: props.date }
    const publish = (status, folder = null, emailPreparation = null, emailDispatch = null, assignmentStatus = null) => {
        checkedStatus.value = status
        emit('exercise-status', { ...context, status, folder, emailPreparation, emailDispatch, assignmentStatus, checkedAt: checkedAt.value, checking: exerciseChecking.value })
    }
    if (cached) {
        checkedAt.value = cached.checkedAt
        moduleLinked.value = Boolean(cached.data.connected && cached.data.module_link?.folder?.id)
        exerciseFolder.value = cached.status === 'found' ? cached.folder : null
        mcDispatchLocked.value = Number(cached.data.exercise?.email_dispatch?.confirmed_count || 0) > 0
            || ['sent', 'partial'].includes(cached.data.exercise?.email_dispatch?.status)
    }
    const displayed = cached || (previous?.status === 'found' ? previous : null)
    if (displayed) checkedAt.value = displayed.checkedAt
    publish(displayed ? displayed.status : exerciseChecking.value ? 'checking' : 'unknown', displayed?.folder,
        displayed?.data?.exercise?.email_preparation, displayed?.data?.exercise?.email_dispatch, displayed?.data?.exercise?.assignment_status)
    if (!props.active || !current.value || !needsPreparation.value) return
    const progressId = fresh ? crypto.randomUUID() : null
    let progressOperation = null
    let finalProgress = null
    let progressFailed = false
    if (fresh) {
        progressOperation = startProgressPolling(props.work.id, progressId, ['connection', 'folder', 'deadline', 'verification', ...(props.checkProgress ? ['collection'] : [])], true)
        emit('check-busy', true)
    }
    try {
        const stream = fresh ? createWorkCheckStream(progress => updateProgress(progressOperation, progress)) : null
        const data = await loadWorkExercise(key, props.work, props.date, fresh, fresh ? props.deadlineDraft : null, fresh && props.checkProgress, progressId, stream)
        if (operation !== exerciseSequence) return
        if (fresh && data.progress) finalProgress = data.progress
        mcDispatchLocked.value = Number(data.exercise?.email_dispatch?.confirmed_count || 0) > 0
            || ['sent', 'partial'].includes(data.exercise?.email_dispatch?.status)
        const savedWork = data.saved_work
        if (fresh && props.checkProgress && data.collection_error) {
            if (data.saved_work) emit('deadline-saved', { data: data.saved_work })
            const failure = new Error('Der Arbeitsstand konnte nicht geprüft werden.')
            failure.response = { data: data.collection_error }
            throw failure
        }
        if (fresh && props.checkProgress && (String(savedWork?.id) !== String(context.workId)
            || String(savedWork?.teaching_course_id) !== String(context.courseId))) {
            if (data.saved_work) emit('deadline-saved', { data: data.saved_work })
            throw new Error('Der Arbeitsstand konnte nicht geprüft werden.')
        }
        if (savedWork) emit('deadline-saved', { data: savedWork })
        checkedAt.value = workExerciseCheck(key, savedWork || props.work, props.date)?.checkedAt || null
        moduleLinked.value = Boolean(data.connected && data.module_link?.folder?.id)
        if (data.connected && data.module_link?.folder?.id && data.exercise?.status === 'found'
            && typeof data.exercise.folder?.name === 'string' && data.exercise.folder.name.trim()) {
            exerciseFolder.value = data.exercise.folder
            publish('found', data.exercise.folder, data.exercise.email_preparation, data.exercise.email_dispatch, data.exercise.assignment_status)
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
    if (opened.value || !props.active || !needsPreparation.value || !current.value || !moduleLinked.value || props.disabled || exerciseChecking.value || checkedStatus.value !== 'missing' || exerciseFolder.value) return
    clear()
    instructions.value = ''
    deadlineDate.value = props.deadlineDraft ? props.deadlineDraft.date || '' : props.work?.finish_until_date?.slice(0, 10) || ''
    deadlineTime.value = props.deadlineDraft ? props.deadlineDraft.time || '' : props.work?.finish_until_time || ''
    includeMc.value = false
    mcOptions.value = null
    mcPoints.value = 2
    enteredOtherPoints.value = null
    otherPointsEdited.value = false
    mcPayload = null
    expectedTestId = null
    opened.value = true
    checkModuleQuestions()
}

defineExpose({ openDialog })

function updateDialog(value) {
    if (!value && (loading.value || checkingModuleQuestions.value)) return
    opened.value = value
}

async function checkModuleQuestions() {
    const operation = ++moduleQuestionsSequence
    moduleQuestions.value = 'loading'
    templatePoints.value = null
    mcOptions.value = null
    moduleCatalog.value = null
    const context = JSON.stringify([admin.config?.user?.id, props.work?.id, props.course?.id, props.date, bindingVersion.value])
    const isCurrent = () => operation === moduleQuestionsSequence && opened.value && current.value
        && context === JSON.stringify([admin.config?.user?.id, props.work?.id, props.course?.id, props.date, bindingVersion.value])
    nextTick(() => { if (isCurrent() && checkingModuleQuestions.value) moduleQuestionsStatus.value?.focus() })
    try {
        const { data } = await axios.get(exerciseOptions.url(props.work.id), { params: { check_module_questions: 1 } })
        if (!isCurrent()) return
        if (typeof data.module_questions !== 'boolean') throw new Error('Ungültiges Prüfergebnis')
        templatePoints.value = typeof data.assessment_points === 'number' && Number.isFinite(data.assessment_points)
            && data.assessment_points >= 0 ? data.assessment_points : null
        if (data.module_catalog && Number.isInteger(data.module_catalog.count) && data.module_catalog.count > 0
            && /^[a-f0-9]{64}$/.test(data.module_catalog.fingerprint)) moduleCatalog.value = data.module_catalog
        if (!data.module_questions) {
            moduleQuestions.value = 'missing'
        } else {
            const options = await axios.get(exerciseOptions.url(props.work.id), { params: props.date ? { date: props.date } : {} })
            if (!isCurrent()) return
            mcOptions.value = options.data
            expectedTestId = options.data.expected_test_id ?? options.data.linked_test_id ?? null
            mcPoints.value = options.data.linked_mc_points ?? 2
            enteredOtherPoints.value = options.data.linked_other_points ?? null
            otherPointsEdited.value = options.data.linked_other_points !== null && options.data.linked_other_points !== undefined
            moduleQuestions.value = options.data.available && (options.data.eligible_test || (moduleCatalog.value && expectedTestId === null)) ? 'available' : 'no-test'
        }
    } catch {
        if (isCurrent()) moduleQuestions.value = 'error'
    }
    if (isCurrent()) nextTick(() => { if (isCurrent()) dialogTitle.value?.focus() })
}

async function copyExport() {
    if (!opened.value || props.disabled || !current.value || !moduleLinked.value || loading.value || checkingModuleQuestions.value || !mcReady.value || !deadlineValid.value) return
    if (result.value) {
        const operation = sequence
        loading.value = true
        try {
            await copyResult(operation)
        } finally {
            if (operation === sequence) loading.value = false
        }
        return
    }
    clear()
    const operation = sequence
    const workId = props.work.id
    const courseId = props.course.id
    const mc = includeMc.value
    controller = new AbortController()
    loading.value = true
    let deadlineSaved = false
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
                if (checked.exercise?.status !== 'missing') throw new Error('Der Anlagezustand hat sich geändert. Bitte den Dialog erneut öffnen und prüfen.')
            } finally {
                savingDeadline.value = false
            }
        }
        if (mc && !mcPayload) {
            mcPayload = { ...(mcOptions.value.eligible_test
                ? { mc_mode: 'existing', test_id: mcOptions.value.eligible_test.id }
                : { mc_mode: 'module', catalog_fingerprint: moduleCatalog.value.fingerprint, question_count: mcQuestionCount.value, duration_minutes: mcDurationMinutes.value }),
                mc_points: Number(mcPoints.value), other_points: Number(mcOtherPoints.value), expected_maximum_points: Number(mcMaximumPoints.value), expected_test_id: expectedTestId, request_id: crypto.randomUUID(),
                ...(props.date ? { date: props.date } : {}) }
        }
        const { data } = mc ? await axios.post(prepareExercise.url(workId), mcPayload, { signal: controller.signal })
            : await axios.get(aiExport.url(workId), { params: { purpose: 'exercise', include_mc: 0,
                ...(props.date ? { date: props.date } : {}) }, signal: controller.signal })
        if (operation !== sequence || !opened.value || !current.value) return
        const exported = data.data
        if (String(exported?.work_id) !== String(workId) || String(exported?.course_id) !== String(courseId)
            || exported.roster_complete !== true || !Number.isInteger(exported.participants_count) || exported.participants_count < 1
            || typeof exported.text !== 'string' || !exported.text.trim() || exported.context?.include_multiple_choice !== mc
            || (mc && !Number.isInteger(exported.context?.multiple_choice_test?.test_id))) {
            throw new Error('Arbeitsdaten und Prüfungskandidaten konnten nicht vollständig bestätigt werden.')
        }
        result.value = exported
        if (mc) expectedTestId = exported.context.multiple_choice_test.test_id
        await nextTick()
        await copyResult(operation)
    } catch (failure) {
        if (operation === sequence) error.value = `${deadlineSaved ? 'Die Abgabefrist wurde gespeichert. ' : ''}${Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || failure.message || 'Der Übergabetext konnte nicht geladen werden.'}`
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function copyResult(operation) {
    copied.value = false
    message.value = ''
    let success = false
    try {
        await navigator.clipboard.writeText(handoffText.value)
        success = true
    } catch {
        if (operation !== sequence) return
        const details = copyText.value?.closest('details')
        if (details) details.open = true
        copyText.value?.focus()
        copyText.value?.select()
        try {
            success = document.execCommand?.('copy') === true
        } catch {
            success = false
        }
    }
    if (operation !== sequence) return
    copied.value = success
    message.value = success
        ? 'Übergabetext kopiert. Der vollständige Text ist jetzt in der Zwischenablage und kann an die KI übergeben werden.'
        : 'Der Browser blockiert das Kopieren. Bitte „Text kopieren“ anklicken oder den markierten Text mit Strg+C kopieren.'
}

watch([() => admin.config?.user?.id, () => props.work?.id, () => props.course?.id, () => props.date], () => {
    clear()
    pendingSavedDeadline = null
    if (checkProgressState.value?.state === 'running') emit('check-busy', false)
    resetProgressPolling()
    opened.value = false
})
watch(opened, value => { if (!value) { clear(); moduleQuestionsSequence++; includeMc.value = false; moduleQuestions.value = 'loading' } })
watch(includeMc, () => { clear(); mcPayload = null })
watch(() => [mcPoints.value, mcOtherPoints.value], () => { clear(); mcPayload = null })
watch(() => [deadlineDate.value, deadlineTime.value], () => { if (opened.value && (result.value || mcPayload)) { clear(); mcPayload = null } })
watch(instructions, () => { copied.value = false; message.value = '' })
watch(() => props.disabled, disabled => { if (disabled) { clear(); opened.value = false } })
watch([() => admin.config?.user?.id, () => props.work?.id, () => props.course?.id, () => props.date, () => props.active,
    bindingVersion, needsPreparation, () => JSON.stringify(props.work?.communicated_deadline)],
() => { if (!savingDeadline.value) checkExercise() }, { immediate: true })
watch([() => props.work?.finish_until_date, () => props.work?.finish_until_time], ([date, time]) => {
    if (pendingSavedDeadline && (date?.slice(0, 10) || null) === pendingSavedDeadline.date && (time || null) === pendingSavedDeadline.time) {
        pendingSavedDeadline = null
        return
    }
    if (!savingDeadline.value) checkExercise()
})
onBeforeUnmount(() => { clear(); resetProgressPolling(); exerciseSequence++; moduleQuestionsSequence++ })
</script>

<style scoped>
.exercise-deadline-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 8px; }
.exercise-points { background: #f5f8fa; border-radius: 6px; padding: 12px; }
.module-questions-loading { min-height: 180px; display: grid; place-items: center; }
.module-questions-status { display: flex; flex-direction: column; align-items: center; gap: 16px; text-align: center; }
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
