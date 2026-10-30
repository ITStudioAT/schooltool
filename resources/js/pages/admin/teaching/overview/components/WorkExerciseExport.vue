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
        <v-btn v-if="active && current && !exerciseChecking" size="small" variant="tonal" prepend-icon="mdi-refresh"
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
    <WorkProgressCheck v-if="checkProgress && !needsPreparation && active && current" :work="work" :disabled="disabled"
        :check-key="workDropboxKey(admin.config?.user?.id, work)" @checked="emit('progress-checked', $event)"
        @saved="emit('deadline-saved', $event)" @busy="emit('check-busy', $event)" />
    <v-dialog v-if="opened" v-model="opened" max-width="640" :persistent="loading">
        <v-card>
            <v-card-title class="text-wrap">Leistungsfeststellung erstellen</v-card-title>
            <v-card-text>
                <p class="text-body-2 mb-3">Text für die KI erzeugen oder das vollständige Materialpaket direkt über die OpenAI-API vorbereiten und nach Prüfung in Dropbox anlegen.</p>
                <div class="exercise-deadline-fields mb-3">
                    <v-text-field v-model="deadlineDate" label="Endgültige Abgabefrist" type="date" :disabled="loading" hide-details />
                    <v-text-field v-model="deadlineTime" label="Uhrzeit (optional)" type="time" :disabled="loading" hide-details />
                </div>
                <p class="text-caption mb-3">Die endgültige Frist wird beim Erstellen gespeichert. Ohne Uhrzeit endet ein MC-Test um 23:59 Uhr.</p>
                <p v-if="!deadlineValid" class="text-caption text-error mb-3" role="status">Bitte ein gültiges Datum und eine gültige Uhrzeit eingeben.</p>
                <v-checkbox v-model="includeMc" label="Multiple-Choice-Fragen einbeziehen" :disabled="loading || mcDispatchLocked" hide-details />
                <div v-if="includeMc" class="mt-4">
                    <p class="text-caption mb-3">Individueller Start beim Öffnen des persönlichen Links. Als Schlussfrist gilt das Enddatum der Arbeit; ohne Uhrzeit gilt 23:59:00 Uhr.</p>
                    <p v-if="mcLoading" role="status">MC-Tests und Fragenkataloge laden …</p>
                    <template v-else-if="mcOptions?.available">
                        <v-alert v-if="mcOptions.configuration?.locked" type="warning" variant="tonal" class="mb-3">{{ mcOptions.configuration.message }}</v-alert>
                        <v-radio-group v-model="mcMode" :disabled="loading || mcOptions.configuration?.locked" hide-details>
                            <v-radio value="existing" label="Bestehenden MC-Test verwenden" />
                            <v-radio value="new" label="Neuen MC-Test erstellen" />
                        </v-radio-group>
                        <template v-if="mcMode === 'existing'">
                            <v-select v-model="mcExistingCatalogId" :items="mcTestCatalogs" item-title="title" item-value="id"
                                label="Fragenkatalog filtern" placeholder="Alle Fragenkataloge" clearable :disabled="loading" class="mt-3" hide-details />
                            <v-select v-model="mcTestId" :items="filteredMcTests" item-title="selection_label" item-value="id"
                                label="MC-Test auswählen" :disabled="loading" class="mt-3" hide-details />
                            <p v-if="selectedMcTest" class="text-caption mt-2">Fragenkatalog: {{ selectedMcTest.catalog_label }}</p>
                            <p v-if="!filteredMcTests.length" class="text-caption mt-2">Keine bestehenden MC-Tests für diese Auswahl vorhanden.</p>
                        </template>
                        <template v-else>
                            <v-select v-model="mcCatalogId" :items="mcOptions.catalogs" item-title="title" item-value="id"
                                label="Fragenkatalog auswählen" :disabled="loading" class="mt-3" hide-details />
                            <p class="text-caption mt-2">Die Prüfungskandidaten werden aus dieser Leistungsfeststellung übernommen.<template v-if="selectedMcCatalog"> Im Katalog sind {{ selectedMcCatalog.reviewed_question_count }} aktive, geprüfte Fragen verfügbar.</template></p>
                            <v-text-field v-model.number="mcQuestionCount" label="Anzahl der Fragen" type="number" min="1" :max="mcQuestionLimit || 2000" :disabled="loading" class="mt-3" hide-details />
                            <p v-if="selectedMcCatalog && !mcQuestionCountValid" class="text-caption text-error mt-2" role="status">Bitte eine Anzahl zwischen 1 und {{ mcQuestionLimit }} auswählen.</p>
                            <v-text-field v-model.number="mcDuration" label="MC-Bearbeitungszeit in Minuten" type="number" min="1" max="1440" :disabled="loading" class="mt-3" hide-details />
                            <p class="text-caption mt-2">Der neue Test wird als Entwurf erstellt und mit dieser Leistungsfeststellung verknüpft. Die Freigabe erfolgt später im MC-Bereich.</p>
                            <p v-if="!mcOptions.catalogs.length" class="text-caption mt-2">Bitte zuerst unter Unterricht-Tools · Multiple Choice einen Fragenkatalog vorbereiten.</p>
                        </template>
                        <v-text-field v-model.number="mcPoints" label="MC-Punkte" type="number" min="0.01" max="100000"
                            step="0.01" :disabled="loading || mcOptions.configuration?.locked" class="mt-3" hide-details />
                        <v-text-field v-model.number="mcOtherPoints" label="Punkte übrige Aufgaben" type="number" min="0" max="100000"
                            step="0.01" :disabled="loading || mcOptions.configuration?.locked" class="mt-3" hide-details />
                        <p v-if="mcPointsValid" class="text-caption mt-2" role="status">{{ mcPoints }} Punkte MC + {{ mcOtherPoints }} Punkte übrige Aufgaben = {{ assessmentPoints }} Punkte gesamt.<template v-if="mcMode === 'existing'"> Die Punkte des vorhandenen MC-Tests werden auf diesen Anteil umgerechnet.</template></p>
                        <p v-if="mcPointsValid && assessmentPoints !== Number(mcMaximumPoints)" class="text-caption mt-2">Die Bewertungsskala in Schooltool beträgt {{ mcMaximumPoints }} Punkte. Die Umrechnung für die spätere Übernahme ist noch abzustimmen.</p>
                        <p v-if="!mcPointsValid" class="text-caption mt-2" role="status">Bitte gültige Punkte für den MC-Test und die übrigen Aufgaben angeben.</p>
                    </template>
                    <v-alert v-else-if="!error" type="warning" variant="tonal">Multiple Choice ist noch nicht verfügbar.</v-alert>
                </div>
                <v-divider class="my-4" />
                <h3 class="text-subtitle-2 mb-2">Direkt über die OpenAI-API</h3>
                <p class="text-caption mb-3">Schooltool liest die verknüpfte Modulquelle und „Assessment Template“. OpenAI erhält die fachlichen Quellen und Arbeitsvorgaben; die Schülerliste wird erst beim Anlegen aus den bestätigten Schooltool-Daten gespeichert.</p>
                <v-select v-model="formDecision" :items="formDecisions" item-title="title" item-value="id"
                    label="Formabzüge für Betreff, Anrede, Inhalt und Signatur?" :disabled="loading" hide-details />
                <v-text-field v-if="formDecision === 1" v-model="formDeductions" label="Abzug je Bereich und maximaler Gesamtabzug" :disabled="loading" class="mt-3" hide-details />
                <v-textarea v-model="instructions" label="Ergänzende Vorgaben oder Antworten auf Rückfragen" :disabled="loading" rows="3" class="mt-3" hide-details />
                <v-alert v-if="generation?.questions?.length" type="warning" variant="tonal" class="mt-3" role="status">
                    <p>Vor der Erstellung fehlen Angaben:</p><ul><li v-for="question in generation.questions" :key="question">{{ question }}</li></ul>
                </v-alert>
                <template v-if="generation?.ready">
                    <h3 class="text-subtitle-2 mt-4">Konkrete Fassung prüfen</h3>
                    <p class="text-caption mb-2">Mit „Paket in Dropbox anlegen“ bestätigen Sie diese Aufgabenstellung und Kriterien. Die Vorschau gilt 30 Minuten.</p>
                    <details open class="mt-2"><summary>Aufgabenstellung</summary><pre class="exercise-preview">{{ generation.assignment_markdown }}</pre></details>
                    <details open class="mt-2"><summary>Bewertungskriterien</summary><pre class="exercise-preview">{{ generation.criteria_markdown }}</pre></details>
                    <ul class="text-caption mt-2"><li v-for="criterion in generation.criteria" :key="criterion.title">{{ criterion.title }}: {{ criterion.points }} Punkte · {{ criterion.description }}</li></ul>
                    <p class="text-caption mt-3">Bestätigte Quellen:</p>
                    <ul class="text-caption"><li v-for="source in generation.sources" :key="source.id">{{ source.path_lower }} · {{ source.revision }}</li></ul>
                    <div class="d-flex flex-wrap ga-2 mt-3"><v-btn v-for="pdf in generation.pdfs || []" :key="pdf.name" size="small" variant="tonal" prepend-icon="mdi-file-pdf-box" @click="previewPdf(pdf)">{{ pdf.name }}</v-btn></div>
                </template>
                <v-alert v-if="generation?.warnings?.length" type="warning" variant="tonal" class="mt-3"><ul><li v-for="warning in generation.warnings" :key="warning">{{ warning }}</li></ul></v-alert>
                <v-alert v-if="createdPackage" type="success" variant="tonal" class="mt-3" role="status">Materialpaket in Dropbox bestätigt: {{ createdPackage.folder.path }}. Versand und Schülerfreigabe erfolgen gesondert.</v-alert>
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
                <v-alert v-if="message" :type="copied ? 'success' : 'warning'" variant="tonal" role="status" aria-live="polite" class="mt-3">{{ message }}</v-alert>
                <template v-if="result">
                    <p class="text-caption mt-3">{{ result.participants_count }} bestätigte Prüfungskandidaten.</p>
                    <details class="mt-2"><summary>Übergabetext anzeigen</summary>
                        <textarea ref="copyText" :value="result.text" readonly rows="9" aria-label="Leistungsfeststellungsauftrag zum Kopieren" class="exercise-export-text" />
                    </details>
                </template>
            </v-card-text>
            <v-card-actions class="flex-wrap ga-2">
                <v-spacer />
                <v-btn :disabled="loading" @click="opened = false">{{ result ? 'Ende' : 'Abbrechen' }}</v-btn>
                <v-btn v-if="!createdPackage" :color="copied ? 'success' : 'primary'" :prepend-icon="copied ? 'mdi-check' : undefined" :loading="loading" :disabled="loading || !current || !mcReady || !deadlineValid" @click="copyExport()">{{ copied ? 'Kopiert' : result ? 'Text kopieren' : includeMc && mcOptions?.available && mcMode === 'new' ? 'MC-Test erstellen und Übergabetext kopieren' : 'Übergabetext kopieren' }}</v-btn>
                <v-btn v-if="!createdPackage" color="primary" :loading="loading" :disabled="loading || !current || !mcReady || !deadlineValid || !formPolicy" @click="copyExport(true)">Über OpenAI vorbereiten</v-btn>
                <v-btn v-if="generation?.ready && !createdPackage" color="success" :loading="loading" :disabled="loading || disabled || !current" @click="storePackage">Paket in Dropbox anlegen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { VRadio, VRadioGroup, VTextarea, VDivider } from 'vuetify/components'
import { aiExport, exerciseOptions, prepareExercise, storeExercise } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { loadWorkExercise, workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workLinkedMcTestTitle, workNeedsPreparation } from '@/helpers/workOverview'
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
const linkedMcTest = computed(() => workLinkedMcTestTitle(props.work, props.date))
const mcOptions = ref(null)
const mcLoading = ref(false)
const mcMode = ref('existing')
const mcTestId = ref(null)
const mcCatalogId = ref(null)
const mcExistingCatalogId = ref(null)
const mcTestCatalogs = computed(() => [...new Map((mcOptions.value?.tests || []).map(test => [Number(test.catalog_id || 0),
    { id: Number(test.catalog_id || 0), title: test.catalog_label }])).values()]
    .sort((first, second) => first.title.localeCompare(second.title, 'de', { sensitivity: 'base' })))
const filteredMcTests = computed(() => (mcOptions.value?.tests || []).filter(test => mcExistingCatalogId.value === null
    || Number(test.catalog_id || 0) === Number(mcExistingCatalogId.value)))
const selectedMcTest = computed(() => filteredMcTests.value.find(test => String(test.id) === String(mcTestId.value)))
const mcQuestionCount = ref(10)
const selectedMcCatalog = computed(() => (mcOptions.value?.catalogs || []).find(catalog => String(catalog.id) === String(mcCatalogId.value)))
const mcQuestionLimit = computed(() => Math.min(2000, Number(selectedMcCatalog.value?.reviewed_question_count) || 0))
const mcQuestionCountValid = computed(() => Boolean(selectedMcCatalog.value) && Number.isInteger(Number(mcQuestionCount.value))
    && Number(mcQuestionCount.value) >= 1 && Number(mcQuestionCount.value) <= mcQuestionLimit.value)
const mcDuration = ref(7)
const mcPoints = ref(2)
const mcMaximumPoints = computed(() => mcOptions.value?.configuration?.maximum_points ?? null)
const enteredOtherPoints = ref(null)
const otherPointsEdited = ref(false)
const mcOtherPoints = computed({
    get: () => otherPointsEdited.value ? enteredOtherPoints.value
        : Math.max(0, Math.round((Number(mcMaximumPoints.value) - Number(mcPoints.value)) * 100) / 100),
    set: value => { otherPointsEdited.value = true; enteredOtherPoints.value = value },
})
const assessmentPoints = computed(() => Math.round((Number(mcPoints.value) + Number(mcOtherPoints.value)) * 100) / 100)
const mcPointsValid = computed(() => Number(mcMaximumPoints.value) > 0 && Number(mcPoints.value) > 0
    && /^\d+(?:\.\d{1,2})?$/.test(String(mcPoints.value)) && mcOtherPoints.value !== null
    && /^\d+(?:\.\d{1,2})?$/.test(String(mcOtherPoints.value)) && assessmentPoints.value <= 100000)
const mcDispatchLocked = ref(false)
const mcReady = computed(() => !includeMc.value || (!mcLoading.value && !mcDispatchLocked.value && !mcOptions.value?.configuration?.locked
    && mcPointsValid.value && mcOptions.value?.available && (mcMode.value === 'existing'
    ? Boolean(selectedMcTest.value) : mcQuestionCountValid.value
        && Number.isInteger(Number(mcDuration.value)) && Number(mcDuration.value) >= 1 && Number(mcDuration.value) <= 1440)))
let mcSequence = 0
let mcPayload = null
let expectedTestId = null
const loading = ref(false)
const result = ref(null)
const error = ref('')
const message = ref('')
const copied = ref(false)
const copyText = ref(null)
const generation = ref(null)
const createdPackage = ref(null)
const formDecision = ref(null)
const formDeductions = ref('')
const instructions = ref('')
const formDecisions = [{ id: 0, title: 'Keine Abzüge in allen vier Formbereichen' },
    { id: 1, title: 'Konkrete Formabzüge festlegen' }, { id: 2, title: 'Keine Abgabe-E-Mail vorgesehen' }]
const formPolicy = computed(() => formDecision.value === 0 ? 'Keine Formabzüge für Betreff, Anrede, Inhalt und Signatur.'
    : formDecision.value === 2 ? 'Keine Abgabe-E-Mail vorgesehen; Betreff, Anrede, Inhalt und Signatur sind nicht anwendbar.'
        : formDecision.value === 1 && formDeductions.value.trim() ? `Formabzüge für Betreff, Anrede, Inhalt und Signatur: ${formDeductions.value.trim()}` : '')
const exerciseFolder = ref(null)
const exerciseChecking = ref(false)
const checkedStatus = ref('unknown')
const checkedAt = ref(null)
const checkTime = computed(() => checkedAt.value ? new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna',
    dateStyle: 'short', timeStyle: 'short' }).format(new Date(checkedAt.value)) : '')
const moduleLinked = ref(false)
const exerciseMessage = ref('')
const binding = computed(() => workDropboxStates.get(workDropboxKey(admin.config?.user?.id, props.work)))
const needsPreparation = computed(() => workNeedsPreparation(props.work, binding.value))
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
    generation.value = null
    createdPackage.value = null
    error.value = ''
    message.value = ''
    copied.value = false
}

function openDialog() {
    if (!props.active || !needsPreparation.value || !current.value || !moduleLinked.value || props.disabled || exerciseChecking.value || checkedStatus.value !== 'missing' || exerciseFolder.value) return
    clear()
    formDecision.value = null
    formDeductions.value = ''
    instructions.value = ''
    deadlineDate.value = props.deadlineDraft ? props.deadlineDraft.date || '' : props.work?.finish_until_date?.slice(0, 10) || ''
    deadlineTime.value = props.deadlineDraft ? props.deadlineDraft.time || '' : props.work?.finish_until_time || ''
    includeMc.value = false
    mcOptions.value = null
    mcTestId.value = null
    mcCatalogId.value = null
    mcExistingCatalogId.value = null
    mcQuestionCount.value = 10
    mcDuration.value = 7
    mcPoints.value = 2
    enteredOtherPoints.value = null
    otherPointsEdited.value = false
    mcMode.value = 'existing'
    mcPayload = null
    expectedTestId = null
    opened.value = true
}

defineExpose({ openDialog })

async function loadMcOptions() {
    const operation = ++mcSequence
    mcLoading.value = true
    try {
        const { data } = await axios.get(exerciseOptions.url(props.work.id), { params: props.date ? { date: props.date } : {} })
        if (operation !== mcSequence || !opened.value || !includeMc.value || !current.value) return
        if (!Array.isArray(data.tests) || !Array.isArray(data.catalogs)) throw new Error('Die MC-Auswahl konnte nicht geladen werden.')
        mcOptions.value = { ...data, tests: data.tests.map(test => {
            const catalogLabel = test.catalog_title?.trim()
                || data.catalogs.find(catalog => String(catalog.id) === String(test.catalog_id))?.title
                || 'Fragenkatalog nicht verfügbar'
            return { ...test, catalog_label: catalogLabel,
                selection_label: `${test.title} · ${catalogLabel} · Nr. ${test.id} · ${test.status === 'published' ? 'freigegeben' : 'Entwurf'}` }
        }) }
        expectedTestId = data.expected_test_id ?? data.linked_test_id ?? null
        mcTestId.value = data.linked_test_id ?? null
        const linkedTest = mcOptions.value.tests.find(test => String(test.id) === String(mcTestId.value))
        mcExistingCatalogId.value = linkedTest ? Number(linkedTest.catalog_id || 0) : null
        mcPoints.value = data.linked_mc_points ?? 2
        enteredOtherPoints.value = data.linked_other_points ?? null
        otherPointsEdited.value = data.linked_other_points !== null && data.linked_other_points !== undefined
        mcMode.value = mcTestId.value || data.tests.length ? 'existing' : 'new'
    } catch (failure) {
        if (operation === mcSequence) error.value = failure.response?.data?.message || failure.message || 'Die MC-Auswahl konnte nicht geladen werden.'
    } finally {
        if (operation === mcSequence) mcLoading.value = false
    }
}

async function copyExport(direct = false) {
    if (!opened.value || props.disabled || !current.value || !moduleLinked.value || loading.value || !mcReady.value || !deadlineValid.value || (direct && !formPolicy.value)) return
    if (result.value && !direct) {
        const operation = sequence
        loading.value = true
        try {
            await copyResult(operation)
        } finally {
            if (operation === sequence) loading.value = false
        }
        return
    }
    const requestedInstructions = generation.value?.questions?.length
        ? `Vorherige Rückfragen:\n${generation.value.questions.join('\n')}\n\nAntworten und Vorgaben der Lehrperson:\n${instructions.value}` : instructions.value
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
            mcPayload = { mc_mode: mcMode.value, mc_points: Number(mcPoints.value), other_points: Number(mcOtherPoints.value), expected_maximum_points: Number(mcMaximumPoints.value), expected_test_id: expectedTestId, request_id: crypto.randomUUID(),
                ...(props.date ? { date: props.date } : {}), ...(mcMode.value === 'existing' ? { test_id: mcTestId.value }
                    : { catalog_id: mcCatalogId.value, question_count: Number(mcQuestionCount.value), total_points: Number(mcPoints.value), duration_minutes: mcDuration.value }) }
        }
        const { data } = direct ? await axios.post(prepareExercise.url(workId), {
            ...(mc ? mcPayload : { request_id: crypto.randomUUID(), expected_test_id: null, ...(props.date ? { date: props.date } : {}) }),
            execution: 'openai', email_form_policy: formPolicy.value, instructions: requestedInstructions,
        }, { signal: controller.signal }) : mc ? await axios.post(prepareExercise.url(workId), mcPayload, { signal: controller.signal })
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
        if (direct) {
            if (!data.generation || (data.generation.ready !== true && !data.generation.questions?.length)
                || (data.generation.ready && (!data.generation.id || !data.generation.assignment_markdown || !data.generation.criteria_markdown))) {
                throw new Error('Die API-Vorschau konnte nicht vollständig bestätigt werden.')
            }
            generation.value = data.generation
            return
        }
        await nextTick()
        await copyResult(operation)
    } catch (failure) {
        if (operation === sequence) error.value = `${deadlineSaved ? 'Die Abgabefrist wurde gespeichert. ' : ''}${Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || failure.message || 'Der Übergabetext konnte nicht geladen werden.'}`
    } finally {
        if (operation === sequence) loading.value = false
    }
}

function previewPdf(pdf) {
    if (typeof pdf.base64 !== 'string' || !pdf.base64.startsWith('JVBERi0')) return
    const bytes = Uint8Array.from(atob(pdf.base64), character => character.charCodeAt(0))
    const url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }))
    window.open(url, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
}

async function storePackage() {
    if (!opened.value || !props.active || props.disabled || loading.value || !current.value || !generation.value?.ready || createdPackage.value) return
    const operation = sequence
    const generationId = generation.value.id
    const workId = props.work.id
    loading.value = true
    error.value = ''
    controller = new AbortController()
    try {
        const { data } = await axios.post(storeExercise.url(workId), { generation_id: generationId, approved: true }, { signal: controller.signal })
        if (operation !== sequence || !opened.value || !current.value) return
        if (data.data?.status !== 'created' || !data.data.folder?.id || !data.data.folder?.path) throw new Error('Die vollständige Dropbox-Anlage wurde nicht bestätigt.')
        createdPackage.value = data.data
        const checked = await loadWorkExercise(workDropboxKey(admin.config?.user?.id, props.work), props.work, props.date, true)
        if (operation !== sequence || !opened.value) return
        if (checked.exercise?.status === 'found') {
            exerciseFolder.value = checked.exercise.folder
            checkedStatus.value = 'found'
            checkedAt.value = new Date().toISOString()
            emit('exercise-status', { userId: admin.config?.user?.id, workId, courseId: props.course.id, date: props.date,
                status: 'found', folder: checked.exercise.folder, assignmentStatus: checked.exercise.assignment_status,
                emailPreparation: checked.exercise.email_preparation, emailDispatch: checked.exercise.email_dispatch,
                checkedAt: checkedAt.value, checking: false })
        }
    } catch (failure) {
        if (operation === sequence) error.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || failure.message || 'Die Dropbox-Anlage konnte nicht bestätigt werden.'
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function copyResult(operation) {
    copied.value = false
    message.value = ''
    let success = false
    try {
        await navigator.clipboard.writeText(result.value.text)
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
    mcSequence++
    opened.value = false
})
watch(opened, value => { if (!value) { clear(); mcSequence++ } })
watch(includeMc, value => {
    clear(); mcPayload = null; mcOptions.value = null
    if (value && opened.value) {
        loadMcOptions()
    } else mcSequence++
})
watch(mcExistingCatalogId, () => {
    if (!selectedMcTest.value) mcTestId.value = null
})
watch(() => [mcMode.value, mcTestId.value, mcCatalogId.value, mcQuestionCount.value, mcDuration.value, mcPoints.value, mcOtherPoints.value], () => { clear(); mcPayload = null })
watch(() => [deadlineDate.value, deadlineTime.value], () => { if (opened.value && (result.value || mcPayload)) { clear(); mcPayload = null } })
watch([formDecision, formDeductions, instructions], () => { if (opened.value && !loading.value) { generation.value = null; createdPackage.value = null } })
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
onBeforeUnmount(() => { clear(); resetProgressPolling(); exerciseSequence++; mcSequence++ })
</script>

<style scoped>
.exercise-deadline-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 8px; }
.exercise-action { display: inline-block; max-width: 100%; }
.exercise-action--progress { flex-basis: 100%; width: 100%; }
.exercise-check-button { height: auto; min-height: 32px; padding-block: 6px; }
.exercise-check-message { flex-basis: 100%; }
.exercise-folder { display: block; white-space: normal; overflow-wrap: anywhere; }
.exercise-preview { white-space: pre-wrap; overflow-wrap: anywhere; font: inherit; font-size: 0.85rem; background: #f7f8fa; padding: 12px; max-height: 420px; overflow-y: auto; }
.exercise-export-text {
    width: 100%;
    padding: 8px;
    border: 1px solid currentColor;
    border-radius: 4px;
    font-size: 0.75rem;
}
</style>
