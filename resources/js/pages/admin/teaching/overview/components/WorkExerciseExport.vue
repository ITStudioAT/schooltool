<template>
    <span v-if="needsPreparation" class="exercise-action" @click.stop>
        <template v-if="exerciseFolder">
            <span v-if="!hideFolderName" class="exercise-folder text-body-2" aria-label="Leistungsfeststellungsordner">{{ exerciseFolder.name }}</span>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-format-list-checks" :disabled="disabled || !current || mcDispatchLocked"
                :title="mcDispatchLocked ? 'Nach dem Aufgabenversand können keine MC-Fragen mehr einbezogen werden.' : undefined" @click="openDialog(true)">MC-Fragen einbeziehen</v-btn>
        </template>
        <span v-else-if="exerciseChecking" role="status" class="text-body-2">Leistungsfeststellung wird geprüft …</span>
        <v-btn v-else-if="moduleLinked && checkedStatus === 'missing'" size="small" variant="tonal" prepend-icon="mdi-clipboard-text-outline" :disabled="disabled || !current" @click="openDialog">Leistungsfeststellung erstellen</v-btn>
        <span v-if="!work?.id" class="d-block text-caption mt-1">Arbeit zuerst speichern, damit Arbeitskennung und Prüfungskandidaten bestätigt werden können.</span>
        <v-btn v-if="active && current && !exerciseChecking" size="small" variant="tonal" prepend-icon="mdi-refresh"
            class="exercise-check-button" :disabled="disabled" @click="checkExercise(true)">
            <span class="d-flex flex-column align-start">
                <span>Erneut prüfen</span>
                <span v-if="checkedAt" class="text-caption text-none">Letzter Check: {{ checkTime }}</span>
            </span>
        </v-btn>
        <span v-if="exerciseMessage" role="status" class="exercise-check-message d-block text-caption mt-1">{{ exerciseMessage }}</span>
    </span>
    <WorkProgressCheck v-if="checkProgress && !needsPreparation && active && current" :work="work" :disabled="disabled"
        @saved="emit('deadline-saved', $event)" @busy="emit('check-busy', $event)" />
    <v-dialog v-if="opened" v-model="opened" max-width="640" :persistent="loading">
        <v-card>
            <v-card-title class="text-wrap">Leistungsfeststellung erstellen</v-card-title>
            <v-card-text>
                <p class="text-body-2 mb-3">Den Auftrag mit den gespeicherten Arbeitsdaten und Prüfungskandidaten kopieren und anschließend an die KI übergeben.</p>
                <v-checkbox v-model="includeMc" label="Multiple-Choice-Fragen einbeziehen" :disabled="loading || addingMc || mcDispatchLocked" hide-details />
                <div v-if="includeMc" class="mt-4">
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
                            <p class="text-caption mt-2">10 Fragen aus einem Katalog mit mindestens 10 aktiven, geprüften Fragen. Die Prüfungskandidaten werden aus dieser Leistungsfeststellung übernommen.</p>
                            <v-text-field v-model.number="mcDuration" label="MC-Bearbeitungszeit in Minuten" type="number" min="1" max="1440" :disabled="loading" class="mt-3" hide-details />
                            <p class="text-caption mt-2">Der neue Test wird als Entwurf erstellt und mit dieser Leistungsfeststellung verknüpft. Die Freigabe erfolgt später im MC-Bereich.</p>
                            <p v-if="!mcOptions.catalogs.length" class="text-caption mt-2">Bitte zuerst unter Unterricht-Tools · Multiple Choice einen Fragenkatalog vorbereiten.</p>
                        </template>
                        <p class="text-body-2 mt-4">LF gesamt: {{ mcMaximumPoints ?? 'nicht festgelegt' }} Punkte.</p>
                        <v-text-field v-model.number="mcPoints" label="MC-Punkte" type="number" min="0.01" :max="mcMaximumPoints"
                            step="0.01" :disabled="loading || mcOptions.configuration?.locked" class="mt-3" hide-details />
                        <p v-if="mcPointsValid" class="text-caption mt-2" role="status">{{ mcPoints }} Punkte MC + {{ mcOtherPoints }} Punkte übrige Aufgaben = {{ mcMaximumPoints }} Punkte gesamt.<template v-if="mcMode === 'existing'"> Die Punkte des vorhandenen MC-Tests werden auf diesen Anteil umgerechnet.</template></p>
                        <p v-else class="text-caption mt-2" role="status">Bitte einen positiven MC-Anteil bis zur LF-Gesamtpunktzahl angeben. Die übrigen Aufgaben und Bewertungskriterien werden anschließend erneut abgestimmt.</p>
                    </template>
                    <v-alert v-else-if="!error" type="warning" variant="tonal">Multiple Choice ist noch nicht verfügbar.</v-alert>
                </div>
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
                <p v-if="message" role="status" class="text-body-2 mt-3">{{ message }}</p>
                <template v-if="result">
                    <p class="text-caption mt-3">{{ result.participants_count }} bestätigte Prüfungskandidaten.</p>
                    <details class="mt-2"><summary>Übergabetext anzeigen</summary>
                        <textarea ref="copyText" :value="result.text" readonly rows="9" aria-label="Leistungsfeststellungsauftrag zum Kopieren" class="exercise-export-text" />
                    </details>
                </template>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn :disabled="loading" @click="opened = false">{{ copied ? 'Schließen' : 'Abbrechen' }}</v-btn>
                <v-btn :color="copied ? 'success' : 'primary'" :prepend-icon="copied ? 'mdi-check' : undefined" :loading="loading" :disabled="loading || !current || !mcReady" @click="copyExport">{{ copied ? 'Kopiert' : includeMc && mcOptions?.available && mcMode === 'new' ? 'MC-Test erstellen und Übergabetext kopieren' : 'Übergabetext kopieren' }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { VRadio, VRadioGroup } from 'vuetify/components'
import { aiExport, exerciseOptions, prepareExercise, show } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { loadWorkExercise, workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workNeedsPreparation } from '@/helpers/workOverview'
import WorkProgressCheck from './WorkProgressCheck.vue'

const props = defineProps({ work: Object, course: Object, date: { type: String, default: '' }, disabled: Boolean, active: { type: Boolean, default: true }, hideFolderName: Boolean, checkProgress: Boolean, deadlineDraft: { type: Object, default: null } })
const emit = defineEmits(['exercise-status', 'deadline-saved', 'check-busy'])
const admin = useAdminStore()
const current = computed(() => Boolean(props.work?.id && props.course?.id && String(props.work.teaching_course_id) === String(props.course.id)))
const opened = ref(false)
const includeMc = ref(false)
const addingMc = ref(false)
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
const mcDuration = ref(20)
const mcPoints = ref(null)
const mcMaximumPoints = computed(() => mcOptions.value?.configuration?.maximum_points ?? null)
const mcOtherPoints = computed(() => Math.round((Number(mcMaximumPoints.value) - Number(mcPoints.value)) * 100) / 100)
const mcPointsValid = computed(() => Number(mcMaximumPoints.value) > 0 && Number(mcPoints.value) > 0
    && Number(mcPoints.value) <= Number(mcMaximumPoints.value) && /^\d+(?:\.\d{1,2})?$/.test(String(mcPoints.value)))
const mcDispatchLocked = ref(false)
const mcReady = computed(() => !includeMc.value || (!mcLoading.value && !mcDispatchLocked.value && !mcOptions.value?.configuration?.locked
    && mcPointsValid.value && mcOptions.value?.available && (mcMode.value === 'existing'
    ? Boolean(selectedMcTest.value) : Boolean(mcCatalogId.value)
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

async function checkExercise(fresh = false) {
    const operation = ++exerciseSequence
    exerciseFolder.value = null
    moduleLinked.value = false
    opened.value = false
    exerciseMessage.value = ''
    mcDispatchLocked.value = false
    checkedAt.value = null
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const previous = fresh && props.active && current.value && needsPreparation.value ? workExerciseCheck(key, props.work, props.date) : null
    const cached = !fresh && props.active && current.value && needsPreparation.value ? workExerciseCheck(key, props.work, props.date) : null
    exerciseChecking.value = props.active && current.value && needsPreparation.value && !cached
    const context = { userId: admin.config?.user?.id, workId: props.work?.id, courseId: props.course?.id, date: props.date }
    const publish = (status, folder = null, emailPreparation = null, emailDispatch = null) => {
        checkedStatus.value = status
        emit('exercise-status', { ...context, status, folder, emailPreparation, emailDispatch, checkedAt: checkedAt.value, checking: exerciseChecking.value })
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
        displayed?.data?.exercise?.email_preparation, displayed?.data?.exercise?.email_dispatch)
    if (!props.active || !current.value || !needsPreparation.value) return
    if (fresh) emit('check-busy', true)
    try {
        const data = await loadWorkExercise(key, props.work, props.date, fresh, fresh ? props.deadlineDraft : null)
        if (operation !== exerciseSequence) return
        mcDispatchLocked.value = Number(data.exercise?.email_dispatch?.confirmed_count || 0) > 0
            || ['sent', 'partial'].includes(data.exercise?.email_dispatch?.status)
        let savedWork = data.saved_work
        if (fresh && props.checkProgress) {
            try {
                const response = await axios.get(show.url(context.workId), { params: { refresh_collection: true } })
                if (operation !== exerciseSequence) return
                if (String(response.data?.data?.id) !== String(context.workId)
                    || String(response.data.data.teaching_course_id) !== String(context.courseId)) throw new Error('Der Arbeitsstand konnte nicht geprüft werden.')
                savedWork = response.data.data
            } catch (failure) {
                if (operation === exerciseSequence && data.saved_work) emit('deadline-saved', { data: data.saved_work })
                throw failure
            }
        }
        if (savedWork) emit('deadline-saved', { data: savedWork })
        checkedAt.value = workExerciseCheck(key, savedWork || props.work, props.date)?.checkedAt || null
        moduleLinked.value = Boolean(data.connected && data.module_link?.folder?.id)
        if (data.connected && data.module_link?.folder?.id && data.exercise?.status === 'found'
            && typeof data.exercise.folder?.name === 'string' && data.exercise.folder.name.trim()) {
            exerciseFolder.value = data.exercise.folder
            publish('found', data.exercise.folder, data.exercise.email_preparation, data.exercise.email_dispatch)
            opened.value = false
            exerciseMessage.value = data.deadline_sync?.message || ''
        } else {
            publish(data.exercise?.status === 'missing' ? 'missing' : 'unknown')
            if (data.exercise?.status === 'unknown') {
                exerciseMessage.value = data.exercise.message || 'Der Anlagezustand konnte nicht bestätigt werden.'
            }
        }
    } catch (failure) {
        if (operation === exerciseSequence) {
            checkedAt.value = workExerciseCheck(workDropboxKey(admin.config?.user?.id, props.work), props.work, props.date)?.checkedAt || null
            publish('unknown')
            exerciseMessage.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
                || failure.response?.data?.message || 'Der Anlagezustand konnte nicht geprüft werden. Bitte den Dialog erneut öffnen.'
        }
    } finally {
        if (operation === exerciseSequence) exerciseChecking.value = false
        if (fresh) emit('check-busy', false)
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

function openDialog(mcOnly = false) {
    mcOnly = mcOnly === true
    if (!current.value || !moduleLinked.value || props.disabled || exerciseChecking.value || (exerciseFolder.value && !mcOnly) || (mcOnly && mcDispatchLocked.value)) return
    clear()
    addingMc.value = mcOnly
    includeMc.value = mcOnly
    mcOptions.value = null
    mcTestId.value = null
    mcCatalogId.value = null
    mcExistingCatalogId.value = null
    mcPoints.value = null
    mcMode.value = 'existing'
    mcPayload = null
    expectedTestId = null
    opened.value = true
    if (mcOnly) loadMcOptions()
}

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
        mcPoints.value = data.linked_mc_points ?? null
        mcMode.value = mcTestId.value || data.tests.length ? 'existing' : 'new'
    } catch (failure) {
        if (operation === mcSequence) error.value = failure.response?.data?.message || failure.message || 'Die MC-Auswahl konnte nicht geladen werden.'
    } finally {
        if (operation === mcSequence) mcLoading.value = false
    }
}

async function copyExport() {
    if (!opened.value || !current.value || !moduleLinked.value || loading.value || !mcReady.value) return
    clear()
    const operation = sequence
    const workId = props.work.id
    const courseId = props.course.id
    const mc = includeMc.value
    controller = new AbortController()
    loading.value = true
    try {
        if (mc && !mcPayload) {
            mcPayload = { mc_mode: mcMode.value, mc_points: Number(mcPoints.value), expected_maximum_points: Number(mcMaximumPoints.value), expected_test_id: expectedTestId, request_id: crypto.randomUUID(),
                ...(props.date ? { date: props.date } : {}), ...(mcMode.value === 'existing' ? { test_id: mcTestId.value }
                    : { catalog_id: mcCatalogId.value, total_points: Number(mcPoints.value), duration_minutes: mcDuration.value }) }
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
        try {
            await navigator.clipboard.writeText(exported.text)
            if (operation === sequence) {
                copied.value = true
                message.value = 'Übergabetext kopiert. Sie können ihn jetzt an die KI übergeben.'
            }
        } catch {
            if (operation !== sequence) return
            const details = copyText.value?.closest('details')
            if (details) details.open = true
            copyText.value?.focus()
            copyText.value?.select()
            message.value = 'Automatisches Kopieren ist nicht verfügbar. Der vollständige Text ist markiert; mit Strg+C kopieren.'
        }
    } catch (failure) {
        if (operation === sequence) error.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || failure.message || 'Der Übergabetext konnte nicht geladen werden.'
    } finally {
        if (operation === sequence) loading.value = false
    }
}

watch(() => [admin.config?.user?.id, props.work?.id, props.course?.id, props.date], () => { clear(); mcSequence++; opened.value = false })
watch(opened, value => { if (!value) { clear(); mcSequence++ } })
watch(includeMc, value => {
    clear(); mcPayload = null; mcOptions.value = null
    if (value && opened.value) {
        if (!addingMc.value) loadMcOptions()
    } else mcSequence++
})
watch(mcExistingCatalogId, () => {
    if (!selectedMcTest.value) mcTestId.value = null
})
watch(() => [mcMode.value, mcTestId.value, mcCatalogId.value, mcDuration.value, mcPoints.value], () => { clear(); mcPayload = null })
watch(() => [admin.config?.user?.id, props.work?.id, props.course?.id, props.date, props.active, bindingVersion.value, needsPreparation.value,
    props.work?.finish_until_date, props.work?.finish_until_time, JSON.stringify(props.work?.communicated_deadline)], () => checkExercise(), { immediate: true })
onBeforeUnmount(() => { clear(); exerciseSequence++; mcSequence++ })
</script>

<style scoped>
.exercise-action { display: inline-block; max-width: 100%; }
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
