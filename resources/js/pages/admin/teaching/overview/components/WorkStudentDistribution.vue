<template>
    <slot name="activator" :open="open" :active="dialog" :available="canOpen" :label="buttonLabel" :buttonProps="buttonProps">
        <v-btn v-if="canOpen" v-bind="buttonProps" @click.stop="open">{{ buttonLabel }}</v-btn>
    </slot>
    <v-dialog v-if="dialog" v-model="dialog" max-width="640" :persistent="saving || loading" scrollable>
        <v-card>
            <v-card-title class="text-wrap">{{ managing ? 'Aufgaben verwalten' : 'Aufgaben im Schülerbereich' }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <v-alert v-if="error" type="error" class="mb-3" role="alert">{{ error }}</v-alert>
                <v-alert v-if="notice" type="success" variant="tonal" class="mb-3" role="status">{{ notice }}</v-alert>
                <details v-if="loadSteps.length && (!managing || !ready)" :open="loading || mcLoading || !ready" class="assignment-load-progress mb-3" aria-label="Fortschritt beim Laden" aria-live="polite">
                    <summary>{{ loading || mcLoading ? 'Vorbereitung wird geladen …' : loadSteps.some(step => step.state === 'failed') ? 'Laden mit Fehlern beendet' : 'Vorbereitung geladen' }}</summary>
                    <ul>
                        <li v-for="step in loadSteps" :key="step.id" :class="`assignment-load-${step.state}`">
                            <span class="assignment-load-icon" aria-hidden="true">{{ step.state === 'completed' ? '✓' : step.state === 'failed' ? '!' : step.state === 'running' ? '…' : '–' }}</span>
                            <span>{{ step.label }}</span>
                            <span class="assignment-load-caption">{{ step.detail || loadStepState(step.state) }}</span>
                        </li>
                    </ul>
                </details>
                <v-alert v-if="testConfirmation" type="warning" variant="tonal" :title="`${testLifecycle[testConfirmation.action].label}? · ${testConfirmation.test.title}`">
                    <template v-if="testConfirmation.action === 'reset'">Antworten, Ergebnisse und zusätzliche Bearbeitungszeit aller {{ testConfirmation.test.attempt_count }} Versuche werden zurückgesetzt. Die Startart und persönlichen Links bleiben erhalten. Der neue Durchlauf beginnt erst mit dem vorgesehenen Start.</template>
                    <template v-else-if="testConfirmation.action === 'stop'">Alle offenen Versuche werden mit den gespeicherten Antworten abgeschlossen. Begonnene Versuche werden ausgewertet. Danach sind keine weiteren Antworten möglich.</template>
                    <template v-else>Der MC-Test, seine {{ testConfirmation.test.attempt_count }} Versuche, Antworten, Ergebnisse und persönlichen Zugangslinks werden gelöscht. Die Arbeit, Aufgaben-PDFs, bereits importierte Bewertungen und der Fragenkatalog bleiben erhalten.</template>
                </v-alert>
                <v-alert v-else-if="confirmDelete" type="warning" variant="tonal">{{ removalLabel }}? Die Ankündigung verschwindet aus „Meine Aufgaben“.<template v-if="hasLinkedTests"> Neue Starts über persönliche MC-Links sind bis zur nächsten Freigabe gesperrt.</template></v-alert>
                <template v-else-if="ready && managing">
                    <v-alert :type="dialogPublished ? 'success' : 'info'" variant="tonal" class="mb-3">
                        {{ dialogPublished ? 'Im Schülerbereich freigegeben.' : dialogWithdrawn ? 'Die Aufgabenfreigabe ist pausiert.' : 'Pausiert vorbereitet · noch nicht für Schüler:innen freigegeben.' }}
                    </v-alert>
                    <p class="text-body-2">{{ distribution?.student_ids?.length || 0 }} zugeordnete Schüler:innen · {{ existingFiles.length }} {{ existingFiles.length === 1 ? 'Aufgaben-PDF' : 'Aufgaben-PDFs' }}<template v-if="distribution?.instructions?.trim()"> · Aufgabentext gespeichert</template></p>
                    <p v-if="preparationLocked" class="text-body-2 mt-2">Die Vorbereitung ist seit dem Ansetzen des gemeinsamen Countdowns gesperrt.</p>
                    <v-alert v-if="mcError" type="warning" variant="tonal" class="mt-3" role="alert">
                        {{ mcError }}
                        <v-btn variant="text" size="small" :disabled="mcLoading || saving || stale" @click="loadMcTests">MC-Tests erneut laden</v-btn>
                    </v-alert>
                    <p v-else-if="mcLoading" class="mt-3" role="status">MC-Tests werden geladen …</p>
                    <section v-if="mcTests.length" aria-label="Verknüpfte MC-Tests" class="mt-4">
                        <h3 class="text-subtitle-1 mb-2">Multiple-Choice-Test</h3>
                        <v-card v-for="test in mcTests" :key="test.id" variant="outlined" class="mb-2 pa-3">
                            <strong>{{ test.title }}</strong>
                            <p class="text-body-2">{{ managedTestStatus(test) }}</p>
                            <template v-if="test.management_action && !(dialogWithdrawn && test.management_action === 'pause')">
                                <v-btn class="mt-2" size="small" variant="tonal" :prepend-icon="test.management_action === 'pause' ? 'mdi-pause' : 'mdi-play'" :loading="activeTestAction === test.id" :disabled="saving || mcLoading || stale"
                                    :aria-label="`${testActions[test.management_action]} · ${test.title}`" @click="runTestAction(test)">{{ testActions[test.management_action] }}</v-btn>
                            </template>
                            <div v-if="test.lifecycle_actions?.length" class="d-flex flex-wrap ga-1 mt-2">
                                <v-btn v-for="action in test.lifecycle_actions" :key="action" size="small" variant="text" :color="action === 'delete' ? 'error' : undefined"
                                    :prepend-icon="testLifecycle[action].icon"
                                    :aria-label="`${testLifecycle[action].label} · ${test.title}`" :disabled="saving || mcLoading || stale" @click="requestTestConfirmation(test, action)">{{ testLifecycle[action].label }}</v-btn>
                            </div>
                        </v-card>
                    </section>
                    <div v-if="canRepairPause" class="mt-3">
                        <p class="text-body-2 mb-2">Mindestens ein MC-Test ist noch aktiv. Die Pause beginnt beim Nachholen.</p>
                        <v-btn variant="tonal" prepend-icon="mdi-pause" :disabled="saving || mcLoading || stale" :loading="repairingPause" @click="repairPause">MC-Pause nachholen</v-btn>
                    </div>
                </template>
                <template v-else-if="ready">
                <p v-if="!dialogPaused && !dialogPublished" class="text-body-2 mb-3">Nach dem pausierten Speichern sehen die zugeordneten Schüler:innen den Titel unter „Meine Aufgaben“. Mit „Jetzt freigeben“ erhalten sie Zugriff auf die Aufgabe.</p>
                <v-alert v-if="dialogPrepared" type="warning" variant="tonal" class="mb-3">Unter „Meine Aufgaben“ angekündigt. Aufgabentext, PDFs und neue MC-Starts sind gesperrt, bis du die Aufgabe freigibst.</v-alert>
                <v-alert v-else-if="dialogWithdrawn" type="info" variant="tonal" class="mb-3">Die Aufgabenfreigabe ist pausiert. Gespeicherte MC-Zeiten und vorhandene Versuche bleiben erhalten.</v-alert>
                <div v-if="assignmentFile" class="d-flex flex-wrap align-center ga-2 mb-2">
                    <span class="text-caption">Aufgabenstellung aus „{{ assignmentSection }}“</span>
                    <v-btn variant="text" size="small" color="primary" prepend-icon="mdi-file-pdf-box" @click="openPreview">{{ assignmentFile.name }}</v-btn>
                </div>
                <v-alert v-if="assignmentWarning" type="warning" variant="tonal" class="mb-3">{{ assignmentWarning }}</v-alert>
                <v-textarea v-model="instructions" label="Aufgabentext" :disabled="saving" :maxlength="20000" auto-grow rows="2" />
                <v-btn v-if="assignmentFile && !instructions?.trim()" variant="text" size="small" :disabled="saving" @click="instructions = assignmentInstructions">Standardtext übernehmen</v-btn>
                <v-checkbox v-for="file in existingFiles" :key="file.id" v-model="retainedFileIds" :value="file.id" :label="file.name" :disabled="saving" density="compact" hide-details />
                <v-file-input v-if="!assignmentFile && (!preparedExercise || !existingFiles.length)" v-model="files" label="Aufgaben-PDFs ergänzen (je höchstens 8 MB, insgesamt 5)" accept="application/pdf,.pdf" multiple :disabled="saving" />
                <p v-if="!assignmentFile && !retainedFileIds.length && !files.length" class="text-caption mb-2">Bitte einen Aufgabentext eingeben oder ein Aufgaben-PDF auswählen.</p>
                <v-alert v-if="mcError" type="warning" variant="tonal" class="mb-3" role="alert">
                    {{ mcError }}
                    <v-btn variant="text" size="small" :disabled="mcLoading || saving" @click="loadMcTests">MC-Tests erneut laden</v-btn>
                </v-alert>
                <section v-if="mcTests.length" aria-label="Verknüpfte MC-Tests" class="mb-4">
                    <h3 class="text-subtitle-1 mb-2">Multiple-Choice-Test</h3>
                    <v-card v-for="test in mcTests" :key="test.id" variant="outlined" class="mb-3 pa-3">
                        <strong>{{ test.title }}</strong>
                        <p class="text-caption mt-1">{{ mcStatus(test.status) }} · {{ test.duration_seconds / 60 }} Minuten</p>
                        <p v-if="test.status === 'withdrawn'" class="text-caption">Die zurückgenommene Testfreigabe wird mit der Aufgabe erneut erteilt.</p>
                        <p v-if="test.paused_at" class="text-caption">Der Test bleibt pausiert, bis er in der Teststeuerung fortgesetzt wird.</p>
                        <fieldset class="mc-start-options mt-3" :disabled="saving || mcLoading || !test.mode_editable">
                            <legend>Startart</legend>
                            <label><input v-model="test.selectedMode" type="radio" :name="`mc-start-${test.id}`" value="individual"> Selbstständig starten</label>
                            <p class="text-caption">Nach der Freigabe beginnt die persönliche Bearbeitungszeit beim Öffnen des Links.</p>
                            <label><input v-model="test.selectedMode" type="radio" :name="`mc-start-${test.id}`" value="common"> Gemeinsam starten</label>
                            <p class="text-caption">Die Lehrkraft startet den Countdown später.</p>
                        </fieldset>
                        <p v-if="test.selectedMode === 'individual'" class="text-caption mt-2">
                            Schlussfrist des Tests: {{ test.individual_closes_at ? formatDeadline(test.individual_closes_at) : 'Nicht festgelegt' }}
                        </p>
                        <template v-if="test.selectedMode === 'individual' && test.deadline_differs">
                            <p class="text-caption mt-1">Endgültige Abgabefrist der Arbeit: {{ formatDeadline(test.work_closes_at) }}</p>
                            <label v-if="test.can_adopt_final_deadline" class="mt-2 d-flex align-center ga-2">
                                <input v-model="test.adoptFinalDeadline" type="checkbox" :disabled="saving || mcLoading">
                                Abgabefrist der Arbeit als MC-Schlussfrist übernehmen
                            </label>
                        </template>
                        <p v-if="test.selectedMode === 'common' && test.starts_at" class="text-caption mt-2">Der gemeinsame Start wurde bereits festgelegt: {{ formatDeadline(test.starts_at) }}.</p>
                        <p v-if="!test.mode_editable" class="text-caption mt-2">Die gespeicherte Startart bleibt unverändert.</p>
                    </v-card>
                </section>
                <p v-if="dialogPublished" class="text-success mt-3">Die Aufgaben sind für {{ currentWork.status.student_distribution.student_ids.length }} zugeordnete Schüler:innen freigegeben.</p>
                <p v-if="dialogPublished && hasLinkedTests" class="text-caption mt-2">Beim Pausieren der Aufgabenfreigabe werden laufende und startbare MC-Tests mitpausiert.</p>
                </template>
            </v-card-text>
            <v-card-actions class="flex-wrap ga-2">
                <v-spacer />
                <template v-if="testConfirmation">
                    <v-btn :disabled="saving" @click="testConfirmation = null">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="saving" :disabled="saving || stale" @click="confirmTestAction">{{ testLifecycle[testConfirmation.action].confirm }}</v-btn>
                </template>
                <template v-else-if="confirmDelete">
                    <v-btn :disabled="saving" @click="confirmDelete = false">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="saving" :disabled="saving || stale" @click="deletePreparation">{{ dialogWithdrawn ? 'Zurücknehmen bestätigen' : 'Löschen bestätigen' }}</v-btn>
                </template>
                <template v-else-if="ready && managing">
                    <v-btn v-if="dialogPaused && !preparationLocked" color="error" variant="text" :disabled="saving || stale || mcLoading || !!mcError" @click="confirmDelete = true">{{ removalLabel }}</v-btn>
                    <v-btn :disabled="saving" @click="dialog = false">Schließen</v-btn>
                    <v-btn v-if="error" :disabled="saving || mcLoading" @click="open">Stand neu laden</v-btn>
                    <v-btn v-if="!preparationLocked" variant="text" :disabled="saving || stale || mcLoading || !!mcError" @click="beginEditing">Vorbereitung bearbeiten</v-btn>
                    <v-btn v-if="dialogPaused" color="success" variant="flat" prepend-icon="mdi-play" :loading="saving && activeTestAction === null && !repairingPause" :disabled="saving || mcLoading || !!mcError || stale" @click="save(true)">Jetzt freigeben</v-btn>
                </template>
                <template v-else>
                    <v-btn v-if="ready && editing" :disabled="saving" @click="returnToManagement">Zurück zur Verwaltung</v-btn>
                    <v-btn v-else :disabled="saving || loading" @click="dialog = false">Abbrechen</v-btn>
                    <v-btn v-if="!ready && !loading" @click="open">Erneut laden</v-btn>
                    <v-btn v-if="ready && stale && !editing" :disabled="saving" @click="open">Stand neu laden</v-btn>
                    <v-btn v-if="ready && !dialogPublished" color="primary" variant="flat" :loading="saving" :disabled="mcLoading || !!mcError || stale" @click="save(false)">{{ dialogPaused ? 'Vorbereitung speichern' : 'Pausiert vorbereiten' }}</v-btn>
                    <v-btn v-if="ready && dialogPublished" color="success" variant="flat" :loading="saving" :disabled="mcLoading || !!mcError || stale" @click="save(true)">Freigabe aktualisieren</v-btn>
                </template>
            </v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog v-model="previewOpen" max-width="1100" scrollable>
        <v-card>
            <v-card-title class="text-wrap">{{ assignmentFile?.name }}</v-card-title>
            <v-card-text>
                <CurriculumPdfPreview v-if="previewUrl" :src="previewUrl" :document-id="assignmentFileId" class="assignment-pdf-preview" />
            </v-card-text>
            <v-card-actions><v-spacer /><v-btn @click="previewOpen = false">Schließen</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { deleteStudentDistributionPreparation, show, studentDistribution, studentDistributionPreview, studentDistributionTestAction } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { folders } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { workPdfFilename } from '@/helpers/workDropbox'
import CurriculumPdfPreview from '../../curricula/CurriculumPdfPreview.vue'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, available: Boolean, disabled: Boolean, preparedExercise: Boolean })
const emit = defineEmits(['saved', 'tests-updated'])
const dialog = ref(false)
const confirmDelete = ref(false)
const saving = ref(false)
const loading = ref(false)
const ready = ref(false)
const currentWork = ref(null)
const error = ref('')
const notice = ref('')
const instructions = ref('')
const files = ref([])
const assignmentFile = ref(null)
const assignmentFileId = ref('')
const assignmentSection = ref('Angaben')
const assignmentInstructions = 'Siehe Aufgaben-PDF.'
const assignmentWarning = ref('')
const mcTests = ref([])
const mcLoading = ref(false)
const mcError = ref('')
const previewOpen = ref(false)
const previewUrl = ref('')
const expectedVersion = ref('')
const retainedFileIds = ref([])
const loadSteps = ref([])
const editing = ref(false)
const stale = ref(false)
const activeTestAction = ref(null)
const testConfirmation = ref(null)
const repairingPause = ref(false)
const clockNow = ref(Date.now())
let clockOffset = 0
let clockTimer
const testActions = { countdown: 'Gemeinsamen Countdown starten', pause: 'MC-Test pausieren', resume: 'MC-Test fortsetzen' }
const testLifecycle = {
    reset: { icon: 'mdi-rewind', label: 'Gesamten Test zurücksetzen', confirm: 'Alle Versuche zurücksetzen', done: 'Der gesamte MC-Test wurde zurückgesetzt.' },
    stop: { icon: 'mdi-stop', label: 'Test endgültig beenden', confirm: 'Test jetzt beenden', done: 'Der MC-Test wurde endgültig beendet.' },
    delete: { icon: 'mdi-delete-outline', label: 'Test löschen', confirm: 'MC-Test endgültig löschen', done: 'Der MC-Test wurde gelöscht.' },
}
const distribution = computed(() => currentWork.value?.status?.student_distribution)
const existingFiles = computed(() => (currentWork.value?.status?.student_task_files || []).filter(file => distribution.value?.file_ids?.includes(file.id)))
const published = computed(() => Boolean(props.work.status?.student_distribution?.published_at && !props.work.status.student_distribution.withdrawn_at))
const prepared = computed(() => isPausedPreparation(props.work))
const withdrawn = computed(() => Boolean(props.work.status?.student_distribution?.published_at && props.work.status.student_distribution.withdrawn_at))
const canOpen = computed(() => Boolean(props.work.id && (props.available || published.value || prepared.value || withdrawn.value)))
const buttonLabel = computed(() => published.value ? 'Freigabe verwalten' : prepared.value || withdrawn.value ? 'Pausierte Aufgaben verwalten' : 'Im Schülerbereich bereitstellen')
const buttonProps = computed(() => ({
    size: 'small', variant: 'tonal', color: published.value ? 'success' : prepared.value || withdrawn.value ? 'warning' : 'primary',
    prependIcon: 'mdi-account-school-outline', disabled: props.disabled,
}))
const dialogPublished = computed(() => Boolean(distribution.value?.published_at && !distribution.value.withdrawn_at))
const dialogWithdrawn = computed(() => Boolean(distribution.value?.published_at && distribution.value.withdrawn_at))
const dialogPrepared = computed(() => isPausedPreparation(currentWork.value))
const dialogPaused = computed(() => dialogPrepared.value || dialogWithdrawn.value)
const preparationLocked = computed(() => mcTests.value.some(test => test.preparation_locked))
const canRepairPause = computed(() => dialogWithdrawn.value && mcTests.value.some(test => test.management_action === 'pause'))
const managing = computed(() => !editing.value && (dialogPaused.value || dialogPublished.value || preparationLocked.value))
const hasLinkedTests = computed(() => Object.keys(currentWork.value?.status?.multiple_choice_tests || {}).length > 0)
const removalLabel = computed(() => dialogWithdrawn.value ? 'Verteilung zurücknehmen' : 'Vorbereitung löschen')
const assignmentParams = computed(() => ({ contents: 1, exercise: 1, verify_exercise: 1, section: 'Angaben', ...(props.date ? { date: props.date } : {}) }))

function isPausedPreparation(work) {
    const release = work?.status?.student_distribution
    return Boolean(release?.prepared_at && !release.published_at && !release.withdrawn_at)
}

function clearPreview() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = ''
}

function openPreview() {
    if (!assignmentFile.value) return
    clearPreview()
    previewUrl.value = URL.createObjectURL(assignmentFile.value)
    previewOpen.value = true
}

watch(previewOpen, isOpen => { if (!isOpen) clearPreview() })
watch(() => props.disabled, disabled => { if (disabled) dialog.value = false })
watch(dialog, isOpen => {
    if (isOpen) {
        clockTimer = window.setInterval(() => { clockNow.value = Date.now() + clockOffset }, 1000)
        return
    }
    window.clearInterval(clockTimer)
    previewOpen.value = false
    confirmDelete.value = false
    testConfirmation.value = null
})
onBeforeUnmount(() => {
    window.clearInterval(clockTimer)
    clearPreview()
})

function mcStatus(status) {
    return ({ draft: 'Noch nicht freigegeben', published: 'Bereits freigegeben', withdrawn: 'Freigabe zurückgenommen' })[status] || 'Status unbekannt'
}

function managedTestStatus(test) {
    const mode = test.start_mode === 'common' ? 'Gemeinsamer Start' : 'Individueller Start'
    if (test.stopped_at) return `${mode} · Endgültig beendet`
    if (test.status !== 'published') return `${mode} · ${mcStatus(test.status)}`
    if (test.paused_at) return `${mode} · MC-Test pausiert${test.management_action === 'resume' ? '' : ' · Fortsetzen ist hier derzeit gesperrt.'}`
    if (test.has_open_attempts === false) return `${mode} · Keine offenen Versuche`
    if (test.start_mode === 'individual') {
        return `${mode} · ${dialogPublished.value || dialogWithdrawn.value
            ? 'Schüler:innen starten selbst über ihren persönlichen Link.' : 'Schüler:innen starten nach der Aufgabenfreigabe über ihren persönlichen Link.'}`
    }
    if (test.starts_at) {
        const phase = Date.parse(test.starts_at) > clockNow.value ? 'Countdown angesetzt'
            : test.ends_at && Date.parse(test.ends_at) <= clockNow.value ? 'Gemeinsamer Test beendet' : 'MC-Test läuft'
        return `${phase} · Startzeit: ${formatDeadline(test.starts_at)}${test.ends_at ? ` · Ende: ${formatDeadline(test.ends_at)}` : ''}`
    }
    if (!dialogPublished.value) return `${mode} · Countdown erst nach Aufgabenfreigabe.`
    return `${mode} · ${test.management_action === 'countdown' ? 'Wartet auf den Countdown.' : 'Der Countdown kann hier nicht gestartet werden.'}`
}

function setLoadStep(id, state, detail = '') {
    const step = loadSteps.value.find(step => step.id === id)
    if (step) Object.assign(step, { state, detail })
}

function loadStepState(state) {
    return ({ pending: 'Noch nicht gestartet', running: 'Läuft …', completed: 'Abgeschlossen',
        failed: 'Fehlgeschlagen', skipped: 'Nicht erforderlich', blocked: 'Nicht geladen' })[state]
}

function formatDeadline(value) {
    const date = new Date(value)
    if (!Number.isFinite(date.getTime())) return 'Ungültige Schlussfrist'
    return `${new Intl.DateTimeFormat('de-AT', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Vienna' }).format(date)} Uhr`
}

async function loadMcTests() {
    mcLoading.value = true
    mcError.value = ''
    setLoadStep('mc', 'running')
    try {
        const response = await axios.get(studentDistributionPreview.url(props.work.id))
        applyTestStatus(response.data?.data)
        setLoadStep('mc', 'completed')
    } catch (failure) {
        mcTests.value = []
        mcError.value = failure.response?.data?.message || 'Die MC-Tests konnten nicht geladen werden. Bitte erneut versuchen.'
        setLoadStep('mc', 'failed')
    } finally {
        mcLoading.value = false
    }
}

function applyTests(tests) {
    if (!Array.isArray(tests) || tests.some(test => !test.id || !test.version || !['individual', 'common'].includes(test.start_mode))) {
        throw new Error('Die MC-Testdaten sind unvollständig.')
    }
    mcTests.value = tests.map(test => ({ ...test, selectedMode: test.start_mode, adoptFinalDeadline: false }))
}

function applyTestStatus(data) {
    applyTests(data?.tests)
    const serverTime = Date.parse(data?.server_now)
    clockOffset = Number.isFinite(serverTime) ? serverTime - Date.now() : 0
    clockNow.value = Date.now() + clockOffset
}

async function readAssignmentPdf(fileId) {
    const { verify_exercise, ...preparedParams } = assignmentParams.value
    const response = await axios.get(folders.url(props.work.id), {
        params: props.preparedExercise ? { ...preparedParams, download_prepared: 1 } : { ...assignmentParams.value, pdf: 1, file_id: fileId },
        responseType: 'blob', timeout: 120000,
    })
    if (!(response.data instanceof Blob) || response.data.type !== 'application/pdf' || response.data.size > 8 * 1024 * 1024) {
        throw new Error('Die Aufgabenstellung muss eine gültige PDF mit höchstens 8 MB sein.')
    }
    return { bytes: response.data, filename: workPdfFilename(response.headers?.['content-disposition']) }
}

async function loadAssignment() {
    if (props.preparedExercise) {
        const pdf = await readAssignmentPdf(null)
        assignmentFile.value = new File([pdf.bytes], pdf.filename, { type: 'application/pdf' })
        assignmentFileId.value = pdf.filename
        setLoadStep('assignment', 'completed')
        setLoadStep('pdf', 'completed')
        return
    }
    const entries = []
    let cursor = null
    let sharedAssignment = false
    for (let page = 0; page < 10; page++) {
        const response = await axios.get(folders.url(props.work.id), {
            params: { ...assignmentParams.value, ...(cursor ? { cursor } : {}) }, timeout: 120000,
        })
        entries.push(...response.data.entries)
        sharedAssignment ||= response.data.shared_assignment === true
        assignmentSection.value = response.data.root?.name || 'Angaben'
        cursor = response.data.cursor
        if (!cursor) break
    }
    if (cursor) throw new Error('Die Aufgabenliste konnte nicht vollständig geladen werden.')
    const filenamePattern = /^(?:Aufgabenstellung|Leistungsfeststellung|LF)_.+\.pdf$/i
    const candidates = [...new Map(entries.filter(entry => entry.type === 'file' && (sharedAssignment || filenamePattern.test(entry.name)))
        .map(entry => [entry.id, entry])).values()]
    if (candidates.length === 0) throw new Error(`Unter „${assignmentSection.value}“ wurde keine Aufgaben-PDF gefunden.`)
    if (candidates.length > 1) throw new Error(`Unter „${assignmentSection.value}“ wurde keine eindeutige Aufgabenstellungs-PDF gefunden. Es gibt mehrere passende Dateien.`)
    const candidate = candidates[0]
    setLoadStep('assignment', 'completed')
    setLoadStep('pdf', 'running')
    const pdf = await readAssignmentPdf(candidate.id)
    assignmentFile.value = new File([pdf.bytes], candidate.name, { type: 'application/pdf' })
    assignmentFileId.value = candidate.id
    setLoadStep('pdf', 'completed')
}

async function assignmentFailure(failure) {
    let data = failure.response?.data
    if (data instanceof Blob) {
        try { data = JSON.parse(await data.text()) } catch { data = null }
    }
    if (failure.response?.status === 404) return data?.message || 'Im verknüpften Prüfungsordner wurde kein Aufgabenbereich gefunden.'
    if (failure.response?.status === 409) {
        return `Die Aufgaben-PDF konnte nicht automatisch geladen werden. ${data?.message || 'Der Prüfungsordner konnte nicht bestätigt werden.'} Bitte ein Aufgaben-PDF auswählen oder einen Aufgabentext eingeben.`
    }
    return data?.message || (failure.response ? 'Die Aufgabenstellung konnte nicht geladen werden.' : failure.message)
}

async function open() {
    if (!canOpen.value || props.disabled || loading.value || saving.value) return
    dialog.value = true
    ready.value = false
    loading.value = true
    currentWork.value = null
    editing.value = false
    clockOffset = 0
    clockNow.value = Date.now()
    stale.value = false
    confirmDelete.value = false
    error.value = ''
    notice.value = ''
    assignmentWarning.value = ''
    mcError.value = ''
    mcTests.value = []
    assignmentFile.value = null
    assignmentFileId.value = ''
    assignmentSection.value = 'Angaben'
    loadSteps.value = [
        { id: 'work', label: 'Aktuellen Stand der Arbeit laden', state: 'running' },
        { id: 'mc', label: 'Verknüpfte MC-Tests laden', state: 'pending' },
        { id: 'assignment', label: 'Aufgaben-PDF im Prüfungsordner suchen', state: 'pending' },
        { id: 'pdf', label: 'Aufgaben-PDF laden und prüfen', state: 'pending' },
    ]
    try {
        const response = await axios.get(show.url(props.work.id))
        const work = response.data?.data
        if (String(work?.id) !== String(props.work.id) || !work.student_distribution_version) throw new Error('Invalid work')
        applyWork(work)
        setLoadStep('work', 'completed')
        if (hasLinkedTests.value) await loadMcTests()
        else setLoadStep('mc', 'skipped', 'Kein MC-Test verknüpft')
        if (!managing.value && !work.local_task_copy && !existingFiles.value.length && props.available) {
            setLoadStep('assignment', 'running')
            try {
                await loadAssignment()
            } catch (failure) {
                for (const step of loadSteps.value.filter(step => ['assignment', 'pdf'].includes(step.id))) {
                    if (step.state === 'running') setLoadStep(step.id, 'failed')
                    else if (step.state === 'pending') setLoadStep(step.id, 'blocked')
                }
                assignmentWarning.value = await assignmentFailure(failure)
            }
        } else {
            const savedTasks = work.local_task_copy || existingFiles.value.length || work.status?.student_distribution?.published_at
            setLoadStep('assignment', 'skipped', savedTasks ? 'Gespeicherte Aufgaben verwenden' : 'Kein automatischer PDF-Import erforderlich')
            setLoadStep('pdf', 'skipped')
        }
        resetPreparationFields()
        ready.value = true
    } catch {
        for (const step of loadSteps.value) {
            if (step.state === 'running') setLoadStep(step.id, 'failed')
            else if (step.state === 'pending') setLoadStep(step.id, 'blocked')
        }
        error.value = 'Der aktuelle Stand konnte nicht geladen werden. Bitte erneut versuchen.'
    } finally {
        loading.value = false
    }
}

function applyWork(work) {
    currentWork.value = work
    expectedVersion.value = work.student_distribution_version
    resetPreparationFields()
}

function resetPreparationFields() {
    const savedInstructions = distribution.value?.instructions ?? (dialogPaused.value || dialogPublished.value ? ''
        : new DOMParser().parseFromString(currentWork.value?.description || '', 'text/html').body.textContent)
    instructions.value = !savedInstructions?.trim() && assignmentFile.value ? assignmentInstructions : savedInstructions
    retainedFileIds.value = existingFiles.value.map(file => file.id)
    files.value = []
}

async function beginEditing() {
    if (!managing.value || saving.value || stale.value || preparationLocked.value || mcLoading.value || mcError.value) return
    if (hasLinkedTests.value) await loadMcTests()
    if (dialog.value && !mcError.value && !preparationLocked.value) editing.value = true
}

async function returnToManagement() {
    resetPreparationFields()
    applyTests(mcTests.value)
    previewOpen.value = false
    editing.value = false
    if (stale.value) await open()
    else {
        error.value = ''
        if (hasLinkedTests.value) await loadMcTests()
    }
}

function showActionError(failure, fallback) {
    const data = failure.response?.data
    stale.value = failure.response?.status === 409
    error.value = stale.value
        ? `${data?.message || 'Der gespeicherte Stand wurde inzwischen geändert.'}${managing.value ? '' : ' Bitte den Aufgabentext bei Bedarf kopieren, bevor du den Stand neu lädst.'}`
        : Object.values(data?.errors || {}).flat().join(' ') || data?.message || failure.message || fallback
}

function requestTestConfirmation(test, action) {
    if (saving.value || stale.value || !test.lifecycle_actions?.includes(action)) return
    error.value = ''
    notice.value = ''
    testConfirmation.value = { test, action }
}

function confirmTestAction() {
    if (!testConfirmation.value) return
    const { test, action } = testConfirmation.value
    return runTestAction(test, action, true)
}

async function runTestAction(test, action = test.management_action, confirmed = false) {
    if (!managing.value || saving.value || mcLoading.value || stale.value || !action) return
    saving.value = true
    activeTestAction.value = test.id
    error.value = ''
    notice.value = ''
    try {
        const { data: { data } } = await axios.post(studentDistributionTestAction.url(props.work.id), {
            test_id: test.id, action, expected_version: expectedVersion.value,
            expected_test_version: action === 'pause' ? test.pause_version : test.version, ...(confirmed ? { confirmed: true } : {}),
        })
        if (data.work) {
            applyWork(data.work)
            emit('saved', data.work)
        } else {
            emit('tests-updated')
        }
        applyTestStatus(data)
        if (confirmed) {
            testConfirmation.value = null
            notice.value = testLifecycle[action].done
        }
    } catch (failure) {
        showActionError(failure, 'Die MC-Aktion konnte nicht ausgeführt werden.')
    } finally {
        activeTestAction.value = null
        saving.value = false
    }
}

async function repairPause() {
    if (!canRepairPause.value || saving.value || mcLoading.value || stale.value) return
    saving.value = true
    repairingPause.value = true
    error.value = ''
    try {
        const data = new FormData()
        data.append('publish', '0')
        data.append('expected_version', expectedVersion.value)
        for (const test of mcTests.value) data.append(`expected_test_versions[${test.id}]`, test.pause_version)
        const response = await axios.post(studentDistribution.url(props.work.id), data)
        applyWork(response.data.data)
        emit('saved', response.data.data)
        await loadMcTests()
    } catch (failure) {
        showActionError(failure, 'Die MC-Pause konnte nicht nachgeholt werden.')
    } finally {
        repairingPause.value = false
        saving.value = false
    }
}

async function save(releaseNow) {
    if (saving.value || mcLoading.value || mcError.value || stale.value) return
    const fromManagement = managing.value
    const returnAfterEditing = editing.value
    const releaseSaved = preparationLocked.value && fromManagement && dialogPaused.value && releaseNow
    if (preparationLocked.value && !releaseSaved) return
    if (!releaseSaved && !instructions.value?.trim() && !assignmentFile.value && files.value.length === 0 && retainedFileIds.value.length === 0) {
        error.value = 'Bitte einen Aufgabentext eingeben oder ein Aufgaben-PDF auswählen.'
        return
    }
    saving.value = true
    error.value = ''
    try {
        if (assignmentFile.value) {
            const latest = await readAssignmentPdf(assignmentFileId.value)
            const [selectedBytes, latestBytes] = await Promise.all([assignmentFile.value.arrayBuffer(), latest.bytes.arrayBuffer()])
            const selected = new Uint8Array(selectedBytes)
            const current = new Uint8Array(latestBytes)
            if ((props.preparedExercise && latest.filename !== assignmentFile.value.name)
                || selected.length !== current.length || selected.some((byte, index) => byte !== current[index])) {
                throw new Error('Die Aufgabenstellung wurde geändert. Bitte den Aufgabentext bei Bedarf kopieren, den Dialog schließen und erneut öffnen.')
            }
        }
        const data = new FormData()
        data.append('publish', '1')
        data.append('release_now', releaseNow ? '1' : '0')
        data.append('expected_version', expectedVersion.value)
        if (releaseSaved) data.append('release_saved', '1')
        else {
            if (!releaseNow && dialogWithdrawn.value) data.append('retain_paused_release', '1')
            data.append('instructions', instructions.value)
            data.append('mc_tests', JSON.stringify(mcTests.value.map(test => ({
                id: test.id, expected_version: test.version, start_mode: test.selectedMode,
                ...(test.selectedMode === 'individual' && test.adoptFinalDeadline ? { adopt_final_deadline: true } : {}),
            }))))
            for (const id of retainedFileIds.value) data.append('retained_file_ids[]', id)
            if (assignmentFile.value) data.append('files[]', assignmentFile.value)
            for (const file of files.value) data.append('files[]', file)
        }
        const response = await axios.post(studentDistribution.url(props.work.id), data)
        emit('saved', response.data.data)
        if (fromManagement || returnAfterEditing) {
            applyWork(response.data.data)
            editing.value = false
            if (hasLinkedTests.value) await loadMcTests()
        } else {
            dialog.value = false
        }
    } catch (failure) {
        showActionError(failure, 'Die Freigabe konnte nicht gespeichert werden.')
    } finally {
        saving.value = false
    }
}

async function deletePreparation() {
    if (!confirmDelete.value || !dialogPaused.value || preparationLocked.value || saving.value || stale.value) return
    saving.value = true
    error.value = ''
    try {
        const response = await axios.delete(deleteStudentDistributionPreparation.url(currentWork.value.id), {
            data: { expected_version: expectedVersion.value },
        })
        emit('saved', response.data.data)
        dialog.value = false
    } catch (failure) {
        confirmDelete.value = false
        showActionError(failure, 'Die Vorbereitung konnte nicht gelöscht werden.')
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.assignment-load-progress { padding: 12px 14px; border: 1px solid #d8e4f0; border-radius: 10px; background: #f7fafc; color: #233b53; }
.assignment-load-progress ul { list-style: none; padding: 0; margin: 8px 0 0; }
.assignment-load-progress li { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; padding: 4px 0; font-size: 0.8rem; }
.assignment-load-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 20px; width: 20px; height: 20px; border-radius: 50%; background: #e4ebf1; color: #63798c; font-weight: 700; }
.assignment-load-completed .assignment-load-icon { background: #d6f2e3; color: #08783e; }
.assignment-load-running .assignment-load-icon { background: #d8eaff; color: #1565b8; }
.assignment-load-failed .assignment-load-icon { background: #fde3e3; color: #b42318; }
.assignment-load-caption { margin-left: auto; color: #536d83; font-size: 0.72rem; }
.assignment-load-completed .assignment-load-caption { color: #08783e; }
.assignment-load-failed .assignment-load-caption { color: #b42318; }
.assignment-pdf-preview { height: 70vh; min-height: 320px; }
.mc-start-options { border: 0; padding: 0; }
.mc-start-options label { display: block; }
.mc-start-options input { margin-right: 6px; }
</style>
