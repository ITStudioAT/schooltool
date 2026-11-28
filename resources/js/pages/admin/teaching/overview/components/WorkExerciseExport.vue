<template>
    <div v-if="needsPreparation || activity" class="exercise-action" :class="{ 'exercise-action--progress': checkProgressState }" @click.stop>
        <template v-if="exerciseFolder">
            <span v-if="!hideFolderName" class="exercise-folder text-body-2" aria-label="Leistungsfeststellungsordner">LF: {{ exerciseFolder.name }}</span>
            <WorkExerciseStatus v-if="!hideActivator" :work="work" :course="course" :date="date" :state="exerciseState"
                :disabled="disabled || !current" :can-create="!activity" @deleted="checkExercise(true)" />
        </template>
        <span v-else-if="exerciseChecking" role="status" class="text-body-2">Leistungsfeststellung wird geprüft …</span>
        <span v-else-if="!hideActivator && moduleLinked && checkedStatus === 'missing'" role="button" :tabindex="disabled || !current || activity ? -1 : 0"
            :aria-disabled="Boolean(disabled || !current || activity)" aria-label="Leistungsfeststellung erstellen" title="Leistungsfeststellung erstellen"
            class="d-inline-flex cursor-pointer" @click="openDialog" @keydown.enter.stop.prevent="openDialog" @keydown.space.stop.prevent="openDialog">
            <v-icon color="error" size="30">mdi-clipboard-text-outline</v-icon>
        </span>
        <span v-if="linkedMcTest && !hideMcTest" class="exercise-folder text-body-2">MC-Test: {{ linkedMcTest }}</span>
        <span v-if="!work?.id" class="d-block text-caption mt-1">Arbeit zuerst speichern, damit Arbeitskennung und Prüfungskandidaten bestätigt werden können.</span>
        <v-btn v-if="!hideStatusCheck && active && current && (activity || (moduleLinked && !exerciseChecking))" size="small" variant="tonal" prepend-icon="mdi-refresh"
            class="exercise-check-button" :disabled="disabled && !activity" @click="recheckStatus">
            <span class="d-flex flex-column align-start">
                <span>Status erneut prüfen</span>
                <span v-if="checkedAt && !hideCheckTime" class="text-caption text-none">Letzter Check: {{ checkTime }}</span>
            </span>
        </v-btn>
        <v-btn v-if="active && current && (activity || (!exerciseChecking && checkedStatus !== 'found' && (checkedStatus === 'unknown' || assessmentRun)))"
            size="small" variant="text" :disabled="disabled && !activity" @click="openRunDialog">Erstellungsauftrag anzeigen</v-btn>
        <span v-if="exerciseMessage && !activity" role="status" class="exercise-check-message d-block text-caption mt-1">{{ exerciseMessage }}</span>
        <WorkCheckProgress v-if="!activity" :state="checkProgressState" :unavailable="progressUnavailable" />
        <p v-if="!activity && checkProgressState?.state === 'done' && checkedStatus === 'missing'" class="exercise-check-message text-caption" role="status">Statusprüfung abgeschlossen: Der Prüfungsordner ist noch nicht angelegt.</p>
    </div>
    <span v-if="!needsPreparation && linkedMcTest && !hideMcTest" class="exercise-folder text-body-2">MC-Test: {{ linkedMcTest }}</span>
    <WorkProgressCheck v-if="!hideStatusCheck && !activity && workAutomationEnabled(work) && shouldCheckProgress && !needsPreparation && active && current" :work="work" :disabled="disabled"
        :check-key="workDropboxKey(admin.config?.user?.id, work)" @checked="emit('progress-checked', $event)"
        @saved="emit('deadline-saved', $event)" @busy="emit('check-busy', $event)" />
    <v-dialog v-if="opened" :model-value="opened" max-width="640" :persistent="dialogBusy"
        @update:model-value="updateDialog">
        <v-card>
            <v-card-title class="text-wrap">
                <span ref="dialogTitle" tabindex="-1">{{ mcOnly ? 'Multiple-Choice-Test hinzufügen' : 'Leistungsfeststellung erstellen' }}</span>
            </v-card-title>
            <v-card-text v-if="runLookup && !assessmentRun && !runStatusUnknown" class="exercise-template-loading" role="status">
                Erstellungsauftrag wird geladen …
            </v-card-text>
            <v-card-text v-else-if="templateLoading && !assessmentRun" class="exercise-template-loading">
                <div ref="templateStatus" role="status" aria-live="polite" tabindex="-1" class="exercise-template-status">
                    <v-progress-circular indeterminate color="primary" size="56" width="5" aria-hidden="true" />
                    <span>Prüfungsvorlage und Punkte werden geprüft …</span>
                </div>
            </v-card-text>
            <v-card-text v-else-if="templateError && !assessmentRun">
                <v-alert type="warning" variant="tonal" role="alert">{{ templateError }}</v-alert>
            </v-card-text>
            <v-card-text v-else>
                <div v-if="mcProgress" ref="mcProgressTarget" class="exercise-creation-progress mb-3">
                    <WorkCheckProgress :state="mcProgress" creation />
                </div>
                <v-alert v-if="mcAdded" type="success" variant="tonal" role="status">Multiple-Choice-Test hinzugefügt.</v-alert>
                <div v-if="creationProgress" ref="creationProgressTarget" class="exercise-creation-progress mb-3">
                    <WorkCheckProgress :state="creationProgress" creation />
                </div>
                <p v-if="assessmentRun?.state === 'queued' && !runStatusUnknown" class="text-body-2 mb-3" role="status">Die Anlage wartet auf den Start.</p>
                <p v-else-if="assessmentRun?.state === 'running' && !creationProgress" class="text-body-2 mb-3" role="status">Die Anlage läuft im Hintergrund.</p>
                <p v-else-if="!assessmentRun && activity && !creationProgress" class="text-body-2 mb-3" role="status">{{ assessmentActivityLabel(activity) }}</p>
                <p v-if="!mcOnly && !runLookup && !assessmentRun && !runStatusUnknown && !pendingRunRequest && !activity && checkedStatus !== 'missing'"
                    class="text-body-2 mb-3" role="status">Für diese Durchführung ist kein Erstellungsauftrag gespeichert.</p>
                <template v-if="mcOnly ? !mcAdded && !activity : (!activity && !runLookup && !runInProgress && !runStatusUnknown && !pendingRunRequest && !completion && checkedStatus !== 'found' && (checkedStatus === 'missing' || assessmentRun))">
                <p v-if="!mcOnly" class="text-body-2 mb-3">Prüfungsvorlage, Kandidaten und Zielordner prüfen und die Leistungsfeststellung anlegen.</p>
                <div class="exercise-deadline-fields mb-3">
                    <v-text-field v-model="deadlineDate" label="Endgültige Abgabefrist" type="date" :disabled="loading"
                        :error-messages="deadlineError" hide-details="auto" />
                    <v-text-field v-model="deadlineTime" label="Uhrzeit (optional)" type="time" :disabled="loading" hide-details />
                </div>
                <p class="text-caption mb-3">{{ mcOnly ? 'Eine geänderte Frist gilt für die gesamte Arbeit und wird beim Hinzufügen gespeichert.' : 'Die endgültige Frist wird beim Erstellen gespeichert.' }}</p>
                <p v-if="!deadlineValid" class="text-caption text-error mb-3" role="status">Bitte ein gültiges Datum und eine gültige Uhrzeit eingeben.</p>
                <template v-if="!mcOnly">
                <p class="text-body-2 mb-3"><strong>Prüfungsvorlage:</strong> {{ formatPoints(templatePoints) }} Punkte</p>
                <fieldset class="exercise-mc-choice" :disabled="loading || selectionLoading || !!selectionError">
                    <legend>Multiple-Choice-Teil</legend>
                    <label><input v-model="includeMc" type="radio" :value="false" aria-label="Multiple-Choice-Teil: Nein" /> Nein</label>
                    <label><input v-model="includeMc" type="radio" :value="true" aria-label="Multiple-Choice-Teil: Ja" /> Ja</label>
                </fieldset>
                <p class="text-caption mb-3">Multiple Choice wird getrennt in Schooltool bereitgestellt und gehört nicht zum Prüfungsordner.</p>
                </template>
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
                            <p v-if="mcOnly && !newTestDeadlineValid" class="text-caption text-error mt-2" role="status">
                                Setze oben eine künftige Abgabefrist, um einen neuen Test hinzuzufügen.
                            </p>
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
                </template>
                <v-alert v-if="error" :type="runStatusUnknown || pendingRunRequest ? 'warning' : 'error'" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
                <template v-if="result && !completion">
                    <div class="exercise-summary">
                        <section aria-labelledby="exercise-candidates-heading">
                            <div class="exercise-summary-heading">
                                <h3 id="exercise-candidates-heading">Prüfungskandidatinnen und Prüfungskandidaten</h3>
                                <span class="exercise-count">{{ result.participants.length }} gesamt</span>
                            </div>
                            <div class="exercise-people">
                                <table class="exercise-people-table">
                                    <thead><tr><th scope="col">Name</th><th scope="col">Geschlecht</th></tr></thead>
                                    <tbody><tr v-for="person in result.participants" :key="person.name"><td>{{ person.name }}</td><td>{{ formatSex(person.sex) }}</td></tr></tbody>
                                </table>
                            </div>
                            <p class="exercise-note">Geschlechtsangaben laut gespeicherten Kursdaten.</p>
                        </section>
                        <section aria-labelledby="exercise-documents-heading">
                            <h3 id="exercise-documents-heading">Unterlagen</h3>
                            <div class="exercise-check-row"><span>Aufgabenblatt</span><span class="exercise-check-result">OK</span></div>
                            <div class="exercise-check-row"><span>Beurteilungskriterien</span><span class="exercise-check-result">OK</span></div>
                            <p class="exercise-note">Lesbarkeit und Vorlagenpunkte geprüft. Die Beurteilungsgrundlage wird bei der Ablage aus den Kriterien übernommen; ein inhaltlicher Vergleich der Fassungen erfolgt nicht automatisch.</p>
                        </section>
                        <section aria-labelledby="exercise-target-heading">
                            <h3 id="exercise-target-heading">Ablage und Durchführung</h3>
                            <p class="exercise-target">{{ result.target }}</p>
                            <p v-if="result.reusable_folder === 'empty'" class="exercise-note">Der vorhandene leere Prüfungsordner wird für diese Anlage verwendet.</p>
                            <p v-else-if="result.reusable_folder === 'linked'" class="exercise-note">Der bei der Verknüpfung angelegte Prüfungsordner wird für diese Anlage ergänzt.</p>
                            <p v-else-if="result.reusable_folder === 'partial'" class="exercise-note">Der begonnene Prüfungsordner wird geprüft und fortgesetzt; vorhandene Dateien werden nicht überschrieben.</p>
                            <p class="exercise-note">Durchführung: {{ result.scheduled_date }} · Abgabefrist: {{ result.deadline?.date || 'nicht festgelegt' }} {{ result.deadline?.time || '' }}</p>
                            <p class="exercise-note">Aufgabenblatt: {{ formatPoints(result.points) }} Punkte<span v-if="result.multiple_choice"> · MC-Test: {{ result.multiple_choice.title }} ({{ formatPoints(result.multiple_choice.points) }} Punkte) · Gesamt: {{ formatPoints(result.combined_points) }} Punkte</span></p>
                        </section>
                        <p class="exercise-note">Die geprüfte Anlage kann bei einer Unterbrechung fortgesetzt werden.</p>
                    </div>
                </template>
                <template v-if="completion">
                    <v-alert type="success" variant="tonal" role="status">Prüfungsordner angelegt und online bestätigt.</v-alert>
                    <p class="mt-3"><strong>Ordner:</strong> {{ completion.folder.path }}</p>
                    <p>{{ completion.participants_count }} Kandidaten · Aufgabenblatt {{ formatPoints(completion.points) }} Punkte · Durchführung {{ completion.scheduled_date }}</p>
                    <p v-if="completion.multiple_choice">MC-Test: {{ completion.multiple_choice.title }} · {{ formatPoints(completion.multiple_choice.points) }} Punkte (separat in Schooltool)</p>
                    <p>status.json im Prüfungsordner: vorbereitet.</p>
                </template>
            </v-card-text>
            <v-card-actions v-if="!templateLoading || assessmentRun" class="flex-wrap ga-2">
                <v-spacer />
                <v-btn v-if="(runInProgress || activity?.state === 'queued' || activity?.state === 'running') && !runStatusUnknown" @click="opened = false">Im Hintergrund weiterlaufen lassen</v-btn>
                <v-btn v-else variant="text" min-height="44" :disabled="dialogBusy" @click="updateDialog(false)">{{ completion || mcAdded || runStatusUnknown ? 'Schließen' : 'Abbrechen' }}</v-btn>
                <template v-if="mcOnly">
                    <v-btn v-if="!mcAdded" color="primary" :loading="loading"
                        :disabled="dialogBusy || !!activity || !deadlineValid || !newTestDeadlineValid || !includeMc || !!selectionError || mcSelectionBlocked"
                        @click="addMcTest">Test hinzufügen</v-btn>
                </template>
                <v-btn v-else-if="runStatusUnknown" color="primary" :loading="runLookup" :disabled="runLookup" @click="loadAssessmentRun(true)">Auftragsstatus erneut abrufen</v-btn>
                <v-btn v-else-if="pendingRunRequest && !runInProgress" color="primary" :loading="loading" :disabled="dialogBusy || activity?.state !== 'unconfirmed'" @click="resendAssessmentRun">Auftrag erneut senden</v-btn>
                <v-btn v-else-if="templateError && !runInProgress" color="primary" @click="loadTemplatePoints(dialogSequence)">Erneut prüfen</v-btn>
                <v-btn v-else-if="!activity && !completion && !runInProgress && !runLookup && checkedStatus !== 'found' && (checkedStatus === 'missing' || assessmentRun)" color="primary" :loading="loading"
                    :disabled="loading || !current || !deadlineValid || !!deadlineError || selectionLoading || !!selectionError || mcSelectionBlocked"
                    @click="prepareAssessment">{{ assessmentRun ? 'Erstellung erneut versuchen' : 'Leistungsfeststellung erstellen' }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { exerciseOptions, prepareExercise } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { show as showExerciseRun, store as storeExerciseRun } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingAssessmentRunController'
import { confirmCreatedWorkExercise, loadWorkExercise, workDropboxBinding, workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { activeCourseDateHours } from '@/helpers/courseDateHours'
import { workLinkedMcTestTitle, workNeedsPreparation } from '@/helpers/workOverview'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { assessmentActivityLabel, observeAssessmentRun, pendingAssessmentRun, prepareAssessmentRequest, rememberAssessmentRequest,
    workAssessmentActivity } from '@/helpers/workAssessmentRun'
import { workDeadlineExpired } from '@/helpers/date'
import WorkProgressCheck from './WorkProgressCheck.vue'
import WorkCheckProgress from './WorkCheckProgress.vue'
import WorkExerciseStatus from './WorkExerciseStatus.vue'
import { createWorkCheckStream, useWorkCheckProgress } from './workCheckProgress'

const props = defineProps({ work: Object, course: Object, date: { type: String, default: '' }, disabled: Boolean, active: { type: Boolean, default: true }, hideFolderName: Boolean, hideActivator: Boolean, hideMcTest: Boolean, hideCheckTime: Boolean, hideStatusCheck: Boolean, checkProgress: Boolean, passiveStatus: Boolean, deadlineDraft: { type: Object, default: null } })
const emit = defineEmits(['exercise-status', 'progress-checked', 'deadline-saved', 'check-busy', 'busy', 'assessment-recheck'])
const admin = useAdminStore()
const schoolHourStore = useSchoolHourStore()
const assessmentKey = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const activity = computed(() => workAssessmentActivity(admin.config?.user?.id, props.work))
const current = computed(() => Boolean(props.work?.id && props.course?.id && String(props.work.teaching_course_id) === String(props.course.id)))
const opened = ref(false)
const mcOnly = ref(false)
const mcAdded = ref(false)
const mcProgress = ref(null)
const mcProgressTarget = ref(null)
const mcSteps = ['mc_check', 'mc_prepare', 'mc_assign']
const deadlineDate = ref('')
const deadlineTime = ref('')
const deadlineError = ref('')
const savingDeadline = ref(false)
let pendingSavedDeadline = null
const deadlineTimePattern = /^(?:[01]\d|2[0-3]):[0-5]\d$/
const deadlineValid = computed(() => (!deadlineDate.value || /^\d{4}-\d{2}-\d{2}$/.test(deadlineDate.value))
    && (!deadlineTime.value || (deadlineDate.value && deadlineTimePattern.test(deadlineTime.value))))
const dialogTitle = ref(null)
const templateStatus = ref(null)
const creationProgressTarget = ref(null)
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
const newTestDeadlineValid = computed(() => !newTestRequired.value || (Boolean(deadlineDate.value)
    && !workDeadlineExpired({ finish_until_date: deadlineDate.value, finish_until_time: deadlineTime.value })))
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
const formatPoints = value => new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(value)
const templateLoading = ref(false)
const templateError = ref('')
let dialogSequence = 0
let testsSequence = 0
let catalogsSequence = 0
let templateSequence = 0
const loading = ref(false)
const result = ref(null)
const completion = ref(null)
const creationProgress = ref(null)
const assessmentRun = ref(null)
const runLookup = ref(false)
const runStatusUnknown = ref(false)
const pendingRunRequest = ref(null)
const runInProgress = computed(() => ['queued', 'running'].includes(assessmentRun.value?.state))
const error = ref('')
const exerciseFolder = ref(null)
const exerciseState = ref(null)
const exerciseChecking = ref(false)
const checkedStatus = ref('unknown')
const checkedAt = ref(null)
const checkTime = computed(() => checkedAt.value ? new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna',
    dateStyle: 'short', timeStyle: 'short' }).format(new Date(checkedAt.value)) : '')
const moduleLinked = ref(false)
const exerciseMessage = ref('')
const binding = computed(() => workDropboxStates.get(workDropboxKey(admin.config?.user?.id, props.work)))
const needsPreparation = computed(() => workAutomationEnabled(props.work) && workNeedsPreparation(props.work, binding.value))
const shouldCheckProgress = computed(() => props.checkProgress || Boolean(props.work?.status?.student_distribution?.prepared_at))
const bindingVersion = computed(() => JSON.stringify([binding.value?.connected, binding.value?.link_version, binding.value?.module_link]))
const openExerciseContext = computed(() => props.active && current.value
    ? JSON.stringify([workDropboxKey(admin.config?.user?.id, props.work), props.date]) : null)
let sequence = 0
let controller = null
let creationBinding = null
let runTimer = null
let exerciseSequence = 0
let openingCheck = null
let overallCheck = null
let exerciseStateBinding = null
const { state: checkProgressState, unavailable: progressUnavailable, start: startProgressPolling,
    update: updateProgress, finish: finishProgressPolling, reset: resetProgressPolling } = useWorkCheckProgress()
const dialogBusy = computed(() => loading.value || templateLoading.value || selectionLoading.value
    || catalogsLoading.value || testsLoading.value || savingDeadline.value || exerciseChecking.value
    || checkProgressState.value?.state === 'running' || (runInProgress.value && !runStatusUnknown.value)
    || (runLookup.value && !runStatusUnknown.value))
const operationBusy = computed(() => exerciseChecking.value || checkProgressState.value?.state === 'running'
    || (opened.value && dialogBusy.value))
watch(() => activity.value?.state, state => {
    if (state && checkProgressState.value?.state === 'done') resetProgressPolling()
})
watch(operationBusy, value => emit('busy', value), { immediate: true, flush: 'sync' })

async function checkExercise(fresh = false, opening = false, overall = null) {
    if (!fresh && opened.value && (loading.value || runInProgress.value)) return
    if (!fresh && overallCheck && overallCheck.context === openExerciseContext.value) return
    const operation = ++exerciseSequence
    const storedOnly = !fresh && props.passiveStatus
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const requestContext = openExerciseContext.value
    const shouldLoad = Boolean(key && requestContext && (needsPreparation.value || storedOnly || overall))
    if (storedOnly && !requestContext) {
        exerciseChecking.value = false
        return
    }
    const context = { userId: admin.config?.user?.id, workId: props.work?.id, courseId: props.course?.id, date: props.date }
    const previous = exerciseState.value
    const retainStoredStatus = (storedOnly || overall) && previous && !previous.initialCheckPending && previous.status !== 'checking'
        && previous.userId === context.userId && previous.workId === context.workId
        && previous.courseId === context.courseId && previous.date === context.date
        && exerciseStateBinding === workDropboxBinding(binding.value)
    if (!retainStoredStatus) {
        exerciseFolder.value = null
        moduleLinked.value = false
        opened.value = false
        exerciseMessage.value = ''
        checkedAt.value = null
    }
    const previousCheck = !fresh && requestContext ? workExerciseCheck(key, props.work, props.date) : null
    const verifyMissing = !props.passiveStatus && opening && needsPreparation.value && previousCheck?.status === 'missing'
        && Boolean(previousCheck.data.connected && previousCheck.data.module_link?.folder?.id)
    const cached = verifyMissing ? null : previousCheck
    exerciseChecking.value = !overall && shouldLoad && needsPreparation.value && !cached && (fresh || !props.passiveStatus)
    let initialCheckPending = storedOnly && shouldLoad && !cached && !retainStoredStatus
    const publish = (status, folder = null, emailPreparation = null, emailDispatch = null, assignmentStatus = null, prepared = null, deletionPending = false) => {
        exerciseStateBinding = workDropboxBinding(binding.value)
        checkedStatus.value = status
        exerciseState.value = { ...context, status, folder, emailPreparation, emailDispatch, assignmentStatus, preparation: prepared, checkedAt: checkedAt.value, checking: exerciseChecking.value,
            ...(initialCheckPending ? { initialCheckPending: true } : {}),
            ...(deletionPending ? { deletionPending: true } : {}) }
        emit('exercise-status', exerciseState.value)
    }
    if (props.passiveStatus) moduleLinked.value = Boolean(binding.value?.connected && binding.value?.module_link?.folder?.id)
    if (!retainStoredStatus) {
        if (cached) {
            checkedAt.value = cached.checkedAt
            moduleLinked.value = Boolean(cached.data.connected && cached.data.module_link?.folder?.id)
            exerciseFolder.value = cached.status === 'found' ? cached.folder : null
        }
        publish(cached ? cached.status : exerciseChecking.value ? 'checking' : storedOnly && shouldLoad ? 'unchecked' : 'unknown', cached?.folder,
            cached?.data?.exercise?.email_preparation, cached?.data?.exercise?.email_dispatch, cached?.data?.exercise?.assignment_status, cached?.data?.exercise?.preparation, cached?.data?.exercise?.deletion_pending)
    }
    if (!shouldLoad) return
    const openingRequest = verifyMissing || storedOnly ? { context: requestContext } : null
    if (openingRequest) openingCheck = openingRequest
    const overallRequest = overall ? { context: requestContext } : null
    if (overallRequest) overallCheck = overallRequest
    const progressId = fresh && !overall ? crypto.randomUUID() : null
    let progressOperation = null
    let finalProgress = null
    let progressFailed = false
    if (progressId) {
        progressOperation = startProgressPolling(props.work.id, progressId, ['connection', 'folder', 'verification'], true)
        emit('check-busy', true)
    }
    try {
        const stream = overall?.stream || (progressId ? createWorkCheckStream(progress => updateProgress(progressOperation, progress)) : null)
        const recheck = fresh || verifyMissing
        const refreshCollection = fresh && (overall
            ? workAutomationEnabled(props.work) && shouldCheckProgress.value && props.work.status?.submission_method?.channel !== 'portal'
            : props.checkProgress)
        const data = await loadWorkExercise(key, props.work, props.date, recheck, fresh ? props.deadlineDraft : null,
            refreshCollection, overall?.progressId || progressId, stream, recheck, storedOnly, Boolean(overall))
        if (operation !== exerciseSequence || requestContext !== openExerciseContext.value) return
        initialCheckPending = false
        if (storedOnly) context.fromStoredProof = true
        if (fresh && data.progress) finalProgress = data.progress
        const savedWork = data.saved_work
        if (fresh && !overall && props.checkProgress && data.collection_error) {
            const failure = new Error('Der Arbeitsstand konnte nicht geprüft werden.')
            failure.response = { data: data.collection_error }
            throw failure
        }
        if (fresh && !overall && props.checkProgress && (String(savedWork?.id) !== String(context.workId)
            || String(savedWork?.teaching_course_id) !== String(context.courseId))) {
            throw new Error('Der Arbeitsstand konnte nicht geprüft werden.')
        }
        checkedAt.value = storedOnly ? data.exercise?.checked_at || null
            : workExerciseCheck(key, savedWork || props.work, props.date)?.checkedAt || null
        moduleLinked.value = Boolean(data.connected && data.module_link?.folder?.id)
        if (data.connected && data.module_link?.folder?.id && data.exercise?.status === 'found'
            && typeof data.exercise.folder?.name === 'string' && data.exercise.folder.name.trim()) {
            exerciseFolder.value = data.exercise.folder
            publish('found', data.exercise.folder, data.exercise.email_preparation, data.exercise.email_dispatch, data.exercise.assignment_status, data.exercise.preparation, data.exercise.deletion_pending)
            if (!mcOnly.value) opened.value = false
            exerciseMessage.value = data.deadline_sync?.message || ''
        } else {
            exerciseFolder.value = null
            publish(data.exercise?.status === 'missing' && (!overall || moduleLinked.value) ? 'missing'
                : storedOnly && !data.exercise?.checked_at ? 'unchecked' : 'unknown')
            exerciseMessage.value = checkedStatus.value === 'unknown' && (!overall || moduleLinked.value)
                ? data.exercise?.message || 'Der Anlagezustand konnte nicht bestätigt werden.' : ''
        }
        return data
    } catch (failure) {
        if (overall) throw failure
        progressFailed = true
        if (operation === exerciseSequence && requestContext === openExerciseContext.value) {
            initialCheckPending = false
            exerciseFolder.value = null
            checkedAt.value = workExerciseCheck(workDropboxKey(admin.config?.user?.id, props.work), props.work, props.date)?.checkedAt || null
            publish('unknown')
            exerciseMessage.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
                || failure.response?.data?.message || 'Der Anlagezustand konnte nicht geprüft werden. Bitte den Dialog erneut öffnen.'
        }
    } finally {
        if (openingCheck === openingRequest) openingCheck = null
        if (overallCheck === overallRequest) overallCheck = null
        if (progressId && finishProgressPolling(progressOperation, finalProgress, progressFailed)) {
            if (operation !== exerciseSequence && checkProgressState.value?.state === 'running') checkProgressState.value = null
            emit('check-busy', false)
        }
        if (operation === exerciseSequence) exerciseChecking.value = false
    }
}

function clear() {
    sequence++
    controller?.abort()
    clearTimeout(runTimer)
    runTimer = null
    loading.value = false
    runLookup.value = false
    runStatusUnknown.value = false
    result.value = null
    completion.value = null
    creationProgress.value = null
    mcProgress.value = null
    mcAdded.value = false
    error.value = ''
    deadlineError.value = ''
}

function openDialog() {
    if (opened.value || !props.active || !needsPreparation.value || !current.value || props.disabled
        || activity.value || exerciseChecking.value || checkedStatus.value === 'found') return
    showPreparationDialog(false, true)
}

function openRunDialog() {
    if (opened.value || !props.active || !current.value || (props.disabled && !activity.value)) return
    showPreparationDialog(false)
}

function recheckStatus() {
    if (!activity.value) checkExercise(true)
    else if (props.passiveStatus) emit('assessment-recheck')
    else openRunDialog()
}

function openMcSelection() {
    if (opened.value || !props.active || !current.value || props.disabled || !moduleLinked.value
        || activity.value || exerciseChecking.value || checkedStatus.value !== 'found' || linkedMcTest.value) return
    showPreparationDialog(true)
}

function assessmentDateForDeadline() {
    const { work, date } = props
    const dates = [...new Set([work.date_for_all_groups, ...(work.groups || []).map(group => group.date)].filter(Boolean))]
    const scheduledDate = date || (dates.length === 1 ? dates[0] : '')
    if (!dates.includes(scheduledDate) || !/^\d{4}-\d{2}-\d{2}$/.test(scheduledDate)) return ''

    const parsedDate = new Date(`${scheduledDate}T00:00:00Z`)
    return Number.isFinite(parsedDate.getTime()) && parsedDate.toISOString().slice(0, 10) === scheduledDate ? scheduledDate : ''
}

function initializeDeadline(suggestDeadline) {
    const { deadlineDraft, work, course } = props
    deadlineDate.value = deadlineDraft ? deadlineDraft.date || '' : work.finish_until_date?.slice(0, 10) || ''
    deadlineTime.value = deadlineDraft ? deadlineDraft.time || '' : work.finish_until_time || ''
    if (!suggestDeadline || deadlineDraft || work.status?.final_deadline_saved) return

    const assessmentDate = assessmentDateForDeadline()
    deadlineDate.value ||= assessmentDate
    if (!assessmentDate || deadlineDate.value !== assessmentDate || deadlineTime.value) return

    const hours = (course.course_dates || []).filter(courseDate => courseDate.date === assessmentDate).flatMap(activeCourseDateHours)
    if (!hours.length) return

    const lastHour = Math.max(...hours)
    const endTime = schoolHourStore.school_hours.find(entry => Number(entry.hour) === lastHour)?.until?.slice(0, 5) || ''
    if (deadlineTimePattern.test(endTime)) deadlineTime.value = endTime
}

function showPreparationDialog(onlyMc, suggestDeadline = false) {
    clear()
    mcOnly.value = onlyMc
    pendingRunRequest.value = onlyMc ? null : pendingAssessmentRun(assessmentKey.value)
    assessmentRun.value = null
    templateLoading.value = false
    templatePoints.value = null
    templateError.value = ''
    selectionLoading.value = false
    catalogsLoading.value = false
    testsLoading.value = false
    controller = new AbortController()
    creationBinding = workDropboxBinding(binding.value)
    newTestSettings.value = null
    serverTestErrors.value = {}
    initializeDeadline(suggestDeadline)
    opened.value = true
    nextTick(() => { if (opened.value) dialogTitle.value?.focus() })
    if (onlyMc) loadDialogData()
    else loadAssessmentRun(true)
}

defineExpose({ openDialog, openMcSelection, recheck: recheckStatus, checkOverall: options => checkExercise(true, false, options),
    refreshStored: () => { if (!opened.value) checkExercise(false) } })

function updateDialog(value) {
    if (!value && dialogBusy.value) return
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
    if (!mcOnly.value) loadTemplatePoints(operation)
    try {
        const { data } = await axios.get(exerciseOptions.url(workId), { params: { ...params, mc_selection: 'current' } })
        if (!active()) return
        if (data.linked_test_id !== null && !Number.isInteger(data.linked_test_id)) throw new Error('Die Testzuordnung konnte nicht bestätigt werden.')
        savedTestId.value = data.linked_test_id
        savedCatalogId.value = data.linked_catalog_id ?? null
        if (mcOnly.value && data.linked_test_id !== null) {
            throw new Error('Es ist inzwischen ein Test verknüpft. Bitte schließe den Dialog und aktualisiere den Stand.')
        }
        includeMc.value = mcOnly.value || data.linked_test_id !== null
        if (includeMc.value) {
            if (!mcOnly.value) {
                if (!Number.isInteger(data.linked_catalog_id)) throw new Error('Der Katalog des zugeordneten Tests konnte nicht bestätigt werden.')
                selectedCatalogId.value = data.linked_catalog_id
            }
            await loadCatalogs(operation)
            if (active() && selectedCatalogId.value) await loadAvailableTests(operation)
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

async function saveMcSelection(operation, workId, courseId) {
    const requestedTestId = includeMc.value && selectedTestId.value ? Number(selectedTestId.value) : null
    const requestedCatalogId = includeMc.value ? Number(selectedCatalogId.value) : null
    const settings = newTestRequired.value ? {
        question_count: Number(newTestSettings.value.question_count),
        total_points: Number(newTestSettings.value.total_points),
        duration_minutes: Number(newTestSettings.value.duration_minutes),
    } : {}
    const previousTestId = savedTestId.value
    const stream = mcOnly.value ? createWorkCheckStream(progress => {
        if (operation !== sequence || !mcProgress.value) return
        const deadline = mcProgress.value
        mcProgress.value = { ...progress, steps: ['mc_deadline', ...mcSteps],
            completed: [...deadline.completed.filter(step => step === 'mc_deadline'), ...progress.completed],
            skipped: [...deadline.skipped.filter(step => step === 'mc_deadline'), ...progress.skipped],
        }
    }) : null
    const response = await axios.post(prepareExercise.url(workId), {
        mc_selection: includeMc.value ? 'yes' : 'no', test_id: requestedTestId,
        ...(stream ? { mc_add_only: true, stream_progress: true } : {}),
        ...(includeMc.value ? { catalog_id: requestedCatalogId, ...settings } : {}),
        expected_test_id: previousTestId, ...(props.date ? { date: props.date } : {}),
    }, { signal: controller.signal, ...stream?.options })
    if (operation !== sequence || !opened.value || !current.value) return null
    const data = stream ? stream.result(response) : response.data
    if ((includeMc.value && (!Number.isInteger(data.linked_test_id) || data.linked_catalog_id !== requestedCatalogId
        || (requestedTestId !== null && data.linked_test_id !== requestedTestId)))
        || (!includeMc.value && data.linked_test_id !== null) || String(data.work?.id) !== String(workId)
        || String(data.work?.teaching_course_id) !== String(courseId)) {
        throw new Error('Die Testzuordnung konnte nicht bestätigt werden.')
    }
    savedTestId.value = data.linked_test_id
    savedCatalogId.value = data.linked_catalog_id ?? null
    if (data.test?.id === data.linked_test_id && !availableTests.value.some(test => test.id === data.test.id)) {
        availableTests.value = [data.test]
    }
    selectedTestId.value = data.linked_test_id
    const changed = data.linked_test_id !== previousTestId
    if (changed) emit('deadline-saved', { data: data.work })
    return { changed }
}

async function addMcTest() {
    if (!opened.value || !mcOnly.value || props.disabled || activity.value || !current.value || dialogBusy.value
        || mcAdded.value || !deadlineValid.value || !newTestDeadlineValid.value || !includeMc.value || selectionError.value || mcSelectionBlocked.value) return
    const operation = sequence
    const workId = props.work.id
    const courseId = props.course.id
    controller = new AbortController()
    loading.value = true
    let deadlineSaved = false
    error.value = ''
    serverTestErrors.value = {}
    mcProgress.value = { state: 'running', steps: ['mc_deadline', ...mcSteps], active: 'mc_deadline', completed: [], skipped: [] }
    await nextTick()
    if (operation !== sequence || !opened.value) return
    mcProgressTarget.value?.scrollIntoView?.({ block: 'nearest' })
    try {
        creationProgress.value = { state: 'starting', detail: 'Abgabefrist prüfen und bei Änderung speichern.' }
        const updatedDeadline = await saveDeadlineDraft(operation, workId, courseId)
        if (!updatedDeadline) return
        deadlineSaved = updatedDeadline.changed
        mcProgress.value = { ...mcProgress.value, active: 'mc_check',
            completed: deadlineSaved ? ['mc_deadline'] : [], skipped: deadlineSaved ? [] : ['mc_deadline'] }
        const saved = await saveMcSelection(operation, workId, courseId)
        if (saved) {
            const progress = mcProgress.value
            mcProgress.value = { ...progress, state: 'done', active: null,
                completed: progress.steps.filter(step => !progress.skipped.includes(step)) }
            mcAdded.value = true
        }
    } catch (failure) {
        if (operation !== sequence) return
        if (mcProgress.value.state !== 'failed') {
            mcProgress.value = { ...mcProgress.value, state: isConnectionError(failure) ? 'unconfirmed' : 'failed' }
        }
        const validationErrors = failure.response?.data?.errors || {}
        serverTestErrors.value = Object.fromEntries(['question_count', 'total_points', 'duration_minutes']
            .filter(field => Array.isArray(validationErrors[field]) && validationErrors[field].length)
            .map(field => [field, validationErrors[field]]))
        const message = Object.values(validationErrors).flat()[0] || failure.response?.data?.message
            || failure.message || 'Der Test konnte nicht hinzugefügt werden.'
        error.value = `${deadlineSaved ? 'Die Abgabefrist wurde gespeichert. ' : ''}${message}`
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function saveDeadlineDraft(operation, workId, courseId) {
    const date = deadlineDate.value || null
    const time = deadlineTime.value || null
    if (date === (props.work?.finish_until_date?.slice(0, 10) || null)
        && time === (props.work?.finish_until_time || null)) return { changed: false }

    savingDeadline.value = true
    try {
        const checked = await loadWorkExercise(workDropboxKey(admin.config?.user?.id, props.work), props.work, props.date, true, { date, time })
        if (String(checked?.saved_work?.id) !== String(workId) || String(checked.saved_work.teaching_course_id) !== String(courseId)) {
            throw new Error('Die gespeicherte Abgabefrist konnte nicht bestätigt werden.')
        }
        if (operation !== sequence || !opened.value || !current.value) return null
        pendingSavedDeadline = { date, time }
        emit('deadline-saved', { data: checked.saved_work })
        await nextTick()
        if (operation !== sequence || !opened.value || !current.value) return null
        return { changed: true, exercise: checked.exercise }
    } finally {
        savingDeadline.value = false
    }
}

async function prepareAssessment() {
    if (!opened.value || props.disabled || !current.value || !moduleLinked.value || loading.value || !deadlineValid.value || deadlineError.value
        || templateLoading.value || templateError.value || selectionLoading.value || selectionError.value || mcSelectionBlocked.value
        || activity.value || runLookup.value || runInProgress.value || runStatusUnknown.value || pendingRunRequest.value || completion.value || checkedStatus.value === 'found') return
    clear()
    const operation = sequence
    const workId = props.work.id
    const courseId = props.course.id
    const key = assessmentKey.value
    controller = new AbortController()
    loading.value = true
    prepareAssessmentRequest(key, true)
    creationProgress.value = { state: 'starting', detail: 'Angaben speichern und Erstellungsauftrag übermitteln.' }
    let deadlineSaved = false
    let selectionSaved = false
    serverTestErrors.value = {}
    try {
        await nextTick()
        if (operation !== sequence || !opened.value || !current.value) return
        creationProgressTarget.value?.scrollIntoView?.({ block: 'start' })
        creationProgress.value = { state: 'starting', detail: 'Abgabefrist prüfen und bei Änderung speichern.' }
        const updatedDeadline = await saveDeadlineDraft(operation, workId, courseId)
        if (!updatedDeadline) return
        deadlineSaved = updatedDeadline.changed
        if (deadlineSaved && updatedDeadline.exercise?.status !== 'missing' && updatedDeadline.exercise?.preparation?.status !== 'ready') {
            throw new Error('Der Anlagezustand hat sich geändert. Bitte den Dialog erneut öffnen und prüfen.')
        }
        creationProgress.value = { state: 'starting', detail: 'Auswahl für den Multiple-Choice-Teil speichern.' }
        const selection = await saveMcSelection(operation, workId, courseId)
        if (!selection) return
        selectionSaved = selection.changed
        pendingRunRequest.value = { request_id: crypto.randomUUID(), approved: true,
            template_points_snapshot: templatePoints.value, expected_test_id: savedTestId.value,
            ...(props.date ? { date: props.date } : {}) }
        creationProgress.value = { state: 'starting', detail: 'Erstellungsauftrag an die Warteschlange übermitteln.' }
        await dispatchAssessmentRun(operation)
    } catch (failure) {
        if (operation === sequence) {
            const validationErrors = failure.response?.data?.errors || {}
            if (creationProgress.value) creationProgress.value = { ...creationProgress.value, state: 'failed' }
            if (newTestRequired.value) {
                serverTestErrors.value = Object.fromEntries(['question_count', 'total_points', 'duration_minutes']
                    .filter(field => Array.isArray(validationErrors[field]) && validationErrors[field].length)
                    .map(field => [field, validationErrors[field]]))
            }
            deadlineError.value = validationErrors.finish_until_date?.[0] || validationErrors.finish_until_time?.[0] || ''
            if (!deadlineError.value) {
                error.value = `${deadlineSaved ? 'Die Abgabefrist wurde gespeichert. ' : ''}${selectionSaved ? 'Die Testzuordnung wurde gespeichert. ' : ''}${Object.values(validationErrors).flat()[0]
                    || failure.response?.data?.message || (isConnectionError(failure) ? 'Die Verbindung wurde unterbrochen. Bitte erneut versuchen.' : failure.message) || 'Die Angaben konnten nicht gespeichert werden.'}`
            }
        }
    } finally {
        prepareAssessmentRequest(key, false)
        if (operation === sequence) loading.value = false
    }
}

function formatSex(value) {
    if (value === null || value.trim() === '') return 'Keine Angabe'
    const recorded = value.trim()
    const label = { m: 'männlich', w: 'weiblich', f: 'weiblich', d: 'divers' }
    const key = recorded.toLocaleLowerCase('de-AT')
    return Object.hasOwn(label, key) ? label[key] : recorded
}

function isConnectionError(failure) {
    const response = failure.response
    return !response || [408, 502, 504].includes(response.status)
        || (response.status >= 500 && typeof response.data?.message !== 'string')
}

function watchRunAgain() {
    clearTimeout(runTimer)
    runTimer = setTimeout(() => loadAssessmentRun(), 2000)
}

function markRunUnconfirmed(message) {
    runStatusUnknown.value = true
    if (creationProgress.value && creationProgress.value.state !== 'failed' && creationProgress.value.state !== 'done') {
        creationProgress.value = { ...creationProgress.value, state: 'unconfirmed' }
    }
    error.value = message
}

function applyRun(run) {
    if (run !== null && (typeof run?.id !== 'string' || !['queued', 'running', 'completed', 'failed', 'interrupted'].includes(run.state))) {
        throw new Error('Der Erstellungsauftrag konnte nicht bestätigt werden.')
    }
    observeAssessmentRun(assessmentKey.value, run)
    const awaiting = pendingRunRequest.value
    if (awaiting) {
        const sameOrActive = run?.request_id === awaiting.request_id || run?.id === awaiting.request_id || ['queued', 'running'].includes(run?.state)
        if (sameOrActive) pendingRunRequest.value = null
        else run = null
    }
    assessmentRun.value = run
    runStatusUnknown.value = false
    error.value = ''
    if (!run) {
        creationProgress.value = null
        if (pendingRunRequest.value) error.value = 'Schooltool hat noch keinen Auftrag zu dieser Anfrage bestätigt.'
        return
    }

    const before = creationProgress.value?.active
    const progress = run.progress
    if (progress && Array.isArray(progress.steps) && Array.isArray(progress.completed)) {
        creationProgress.value = run.state === 'interrupted' ? { ...progress, state: 'interrupted' } : progress
        if (progress.state === 'running' && progress.active && progress.active !== before) {
            const operation = sequence
            nextTick(() => {
                if (operation === sequence && creationProgress.value?.active === progress.active) {
                    creationProgressTarget.value?.querySelector('.check-progress-active')?.scrollIntoView?.({ block: 'nearest' })
                }
            })
        }
    } else {
        creationProgress.value = null
    }
    if (run.generation && Array.isArray(run.generation.participants) && run.generation.participants.length
        && run.generation.participants.every(person => typeof person?.name === 'string' && (person.sex === null || typeof person.sex === 'string'))) {
        result.value = run.generation
    }
    if (run.state === 'failed' || run.state === 'interrupted') {
        error.value = run.message || (run.state === 'interrupted' ? 'Die Anlage wurde unterbrochen. Der vorhandene Bestand wird beim nächsten Versuch geprüft.' : 'Die Anlage konnte nicht abgeschlossen werden.')
    }
    if (run.state !== 'completed' || (props.date && run.date && props.date !== run.date)) return
    const done = run.result
    if (done?.status !== 'created' || typeof done.folder?.id !== 'string' || typeof done.folder?.path !== 'string'
        || !Number.isInteger(done.participants_count) || done.participants_count < 1
        || (props.date && done.scheduled_date !== props.date)
        || (result.value && done.participants_count !== result.value.participants.length)) {
        throw new Error('Das Ergebnis der Anlage konnte nicht bestätigt werden.')
    }
    completion.value = done
    const prepared = { status: 'ready', components: {
        aufgabenblatt: { included: true, points: done.points }, multiple_choice: { included: false, points: null },
    }, total_points: done.points }
    const userId = admin.config?.user?.id
    const confirmedAt = confirmCreatedWorkExercise(workDropboxKey(userId, props.work), props.work, props.date, creationBinding, done.folder, prepared)
    if (!confirmedAt) return
    exerciseFolder.value = done.folder
    checkedStatus.value = 'found'
    checkedAt.value = confirmedAt
    exerciseStateBinding = creationBinding
    exerciseState.value = { userId, workId: props.work.id, courseId: props.course.id, date: props.date,
        status: 'found', folder: done.folder, preparation: prepared, checkedAt: confirmedAt, checking: false }
    emit('exercise-status', exerciseState.value)
}

async function loadAssessmentRun(showLoading = false) {
    if (!opened.value || !current.value || runLookup.value) return
    clearTimeout(runTimer)
    const operation = sequence
    const date = activity.value?.date || props.date
    const params = date ? { date } : {}
    runLookup.value = true
    try {
        const { data } = await axios.get(showExerciseRun.url(props.work.id), { params, signal: controller.signal, timeout: 10000 })
        if (operation !== sequence || !opened.value || !current.value) return
        applyRun(data?.run)
        if (runInProgress.value || ['queued', 'running'].includes(activity.value?.state)) watchRunAgain()
        else if (!activity.value && (checkedStatus.value === 'missing' || assessmentRun.value) && !completion.value && !pendingRunRequest.value
            && templatePoints.value === null && !templateLoading.value) loadDialogData()
    } catch (failure) {
        if (operation !== sequence) return
        markRunUnconfirmed('Die Verbindung zum Auftragsstatus wurde unterbrochen. Eine begonnene Anlage kann weiterlaufen.')
        if (!showLoading || pendingRunRequest.value || runInProgress.value) watchRunAgain()
    } finally {
        if (operation === sequence) runLookup.value = false
    }
}

async function dispatchAssessmentRun(operation) {
    const key = assessmentKey.value
    const request = pendingRunRequest.value
    rememberAssessmentRequest(key, request)
    try {
        const { data } = await axios.post(storeExerciseRun.url(props.work.id), request, { signal: controller.signal, timeout: 15000 })
        observeAssessmentRun(key, data?.run)
        if (operation !== sequence || !opened.value || !current.value) return
        applyRun(data?.run)
        if (runInProgress.value) watchRunAgain()
    } catch (failure) {
        if (operation !== sequence) return
        if (isConnectionError(failure) || failure.response?.status === 409) {
            markRunUnconfirmed('Schooltool hat den Auftrag noch nicht bestätigt. Eine begonnene Anlage kann weiterlaufen.')
            await loadAssessmentRun(true)
            if (operation === sequence && !runInProgress.value && !runStatusUnknown.value && pendingRunRequest.value
                && failure.response?.status === 409 && typeof failure.response.data?.message === 'string') {
                error.value = failure.response.data.message
            }
        } else {
            rememberAssessmentRequest(key, null)
            pendingRunRequest.value = null
            if (creationProgress.value) creationProgress.value = { ...creationProgress.value, state: 'failed' }
            error.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
                || failure.response?.data?.message || 'Der Erstellungsauftrag konnte nicht gestartet werden.'
        }
    }
}

async function resendAssessmentRun() {
    if (!pendingRunRequest.value || loading.value || runLookup.value || runStatusUnknown.value || runInProgress.value
        || activity.value?.state !== 'unconfirmed') return
    const operation = sequence
    loading.value = true
    try {
        await dispatchAssessmentRun(operation)
    } finally {
        if (operation === sequence) loading.value = false
    }
}

watch([() => admin.config?.user?.id, () => props.work?.id, () => props.course?.id, () => props.date], () => {
    clear()
    assessmentRun.value = null
    pendingRunRequest.value = null
    pendingSavedDeadline = null
    if (checkProgressState.value?.state === 'running') emit('check-busy', false)
    resetProgressPolling()
    opened.value = false
})
watch(opened, value => { if (!value) { dialogSequence++; clear() } })
watch([deadlineDate, deadlineTime, includeMc], () => {
    if (!deadlineError.value) return
    deadlineError.value = ''
    if (creationProgress.value?.state === 'failed') creationProgress.value = null
})
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
watch(() => props.disabled, disabled => { if (disabled) { clear(); opened.value = false } })
watch([openExerciseContext, bindingVersion, needsPreparation],
    ([context, , canPrepare, deadline], [previousContext, , previouslyCouldPrepare, previousDeadline]) => {
        const opening = context !== previousContext
        const bindingChangedDuringOpeningCheck = !opening && openingCheck?.context === context
            && canPrepare === previouslyCouldPrepare && deadline === previousDeadline
        if (!savingDeadline.value && !bindingChangedDuringOpeningCheck) checkExercise(false, opening)
    }, { immediate: true })
watch(() => props.work?.title, (title, previous) => {
    if (title !== previous && !savingDeadline.value) checkExercise(!props.passiveStatus)
})
watch([() => props.work?.finish_until_date, () => props.work?.finish_until_time], ([date, time]) => {
    if (pendingSavedDeadline && (date?.slice(0, 10) || null) === pendingSavedDeadline.date && (time || null) === pendingSavedDeadline.time) {
        pendingSavedDeadline = null
        return
    }
    if (!savingDeadline.value) checkExercise()
})
onBeforeUnmount(() => { clear(); resetProgressPolling(); exerciseSequence++; dialogSequence++; emit('busy', false) })
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
.exercise-creation-progress { flex-basis: 100%; }
.exercise-folder { display: block; white-space: normal; overflow-wrap: anywhere; }
.exercise-summary { display: grid; gap: 20px; }
.exercise-summary h3 { font-size: 1rem; line-height: 1.35; font-weight: 600; }
.exercise-summary-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.exercise-count { flex: none; font-size: 0.85rem; color: #4a5b58; }
.exercise-people { margin-top: 8px; border: 1px solid #dce5e2; border-radius: 6px; }
.exercise-people-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
.exercise-people-table th, .exercise-people-table td { padding: 7px 10px; border-bottom: 1px solid #edf1f0; }
.exercise-people-table th { background: #f4f7f6; color: #42544f; font-size: 0.8rem; font-weight: 600; }
.exercise-people-table tr:last-child td { border-bottom: 0; }
.exercise-check-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #e4ebe8; font-size: 0.9rem; }
.exercise-check-result { color: #19754d; font-weight: 600; }
.exercise-target { margin-top: 7px; font-size: 0.9rem; overflow-wrap: anywhere; }
.exercise-note { margin-top: 6px; color: #53625e; font-size: 0.8rem; line-height: 1.4; }
</style>
