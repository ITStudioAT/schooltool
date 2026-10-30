<template>
    <v-btn v-if="work?.id && (available || published)" size="small" variant="tonal" :color="published ? 'success' : 'primary'" prepend-icon="mdi-account-school-outline" :disabled="disabled" @click.stop="open">
        {{ published ? 'Freigabe verwalten' : 'Im Schülerbereich bereitstellen' }}
    </v-btn>
    <v-dialog v-model="dialog" max-width="640" :persistent="saving || loading" scrollable>
        <v-card>
            <v-card-title class="text-wrap">Aufgaben im Schülerbereich · {{ work.title }}</v-card-title>
            <v-card-text>
                <v-alert v-if="error" type="error" class="mb-3" role="alert">{{ error }}</v-alert>
                <p v-if="loading" class="mb-4">Aktueller Stand der Arbeit wird geladen …</p>
                <template v-if="ready">
                <p class="mb-4">Die zugeordneten Schüler:innen finden diese Aufgaben unter „Meine Aufgaben“ in ihrem Fach. Die Abgabe bleibt per E-Mail oder im persönlichen MC-Test.</p>
                <v-textarea v-model="instructions" label="Aufgabentext" :disabled="saving" :maxlength="20000" auto-grow rows="5" />
                <v-checkbox v-for="file in existingFiles" :key="file.id" v-model="retainedFileIds" :value="file.id" :label="file.name" :disabled="saving" density="compact" hide-details />
                <div v-if="assignmentFile" class="mb-3">
                    <span>Aufgabenstellung aus „{{ assignmentSection }}“:</span>
                    <v-btn variant="text" size="small" color="primary" prepend-icon="mdi-file-pdf-box" @click="openPreview">{{ assignmentFile.name }}</v-btn>
                </div>
                <v-alert v-if="assignmentWarning" type="warning" variant="tonal" class="mb-3">{{ assignmentWarning }}</v-alert>
                <v-file-input v-model="files" label="Aufgaben-PDFs ergänzen (je höchstens 8 MB, insgesamt 5)" accept="application/pdf,.pdf" multiple :disabled="saving" />
                <p v-if="!assignmentFile && !retainedFileIds.length && !files.length" class="text-caption mb-2">Bitte einen Aufgabentext eingeben oder ein Aufgaben-PDF auswählen.</p>
                <v-alert v-if="mcError" type="warning" variant="tonal" class="mb-3" role="alert">
                    {{ mcError }}
                    <v-btn variant="text" size="small" :disabled="mcLoading || saving" @click="loadMcTests">MC-Tests erneut laden</v-btn>
                </v-alert>
                <section v-if="mcTests.length" aria-label="Verknüpfte MC-Tests" class="mb-4">
                    <h3 class="text-subtitle-1 mb-2">MC-Test mit bereitstellen</h3>
                    <v-card v-for="test in mcTests" :key="test.id" variant="outlined" class="mb-3 pa-3">
                        <strong>{{ test.title }}</strong>
                        <p class="text-caption mt-1">{{ mcStatus(test.status) }} · {{ test.duration_seconds / 60 }} Minuten</p>
                        <p v-if="test.status === 'withdrawn'" class="text-caption">Die zurückgenommene Testfreigabe wird mit der Aufgabe erneut erteilt.</p>
                        <p v-if="test.paused_at" class="text-caption">Der Test bleibt pausiert, bis er in der Teststeuerung fortgesetzt wird.</p>
                        <fieldset class="mc-start-options mt-3" :disabled="saving || mcLoading || !test.mode_editable">
                            <legend>Startart</legend>
                            <label><input v-model="test.selectedMode" type="radio" :name="`mc-start-${test.id}`" value="individual"> Selbstständig starten</label>
                            <p class="text-caption">Jede Person startet ihre eigene Bearbeitungszeit.</p>
                            <label><input v-model="test.selectedMode" type="radio" :name="`mc-start-${test.id}`" value="common"> Gemeinsam starten</label>
                            <p class="text-caption">Die Lehrkraft startet den Countdown später.</p>
                        </fieldset>
                        <p v-if="test.selectedMode === 'individual'" class="text-caption mt-2">Schlussfrist: {{ test.individual_closes_at ? formatDeadline(test.individual_closes_at) : 'Nicht festgelegt. Bitte zuerst die Endfrist der Arbeit festlegen.' }}</p>
                        <p v-if="test.selectedMode === 'common' && test.starts_at" class="text-caption mt-2">Der gemeinsame Start wurde bereits festgelegt: {{ formatDeadline(test.starts_at) }}.</p>
                        <p v-if="test.status !== 'draft'" class="text-caption mt-2">Gespeicherte Zeiten bleiben unverändert. Nach Ablauf der Frist ist kein neuer Start möglich.</p>
                        <p v-if="!test.mode_editable" class="text-caption mt-2">Die gespeicherte Startart bleibt unverändert.</p>
                    </v-card>
                </section>
                <p v-if="dialogPublished" class="text-success mt-3">Die Aufgaben sind für {{ currentWork.status.student_distribution.student_ids.length }} zugeordnete Schüler:innen freigegeben.</p>
                <p v-if="dialogPublished && Object.keys(currentWork?.status?.multiple_choice_tests || {}).length" class="text-caption mt-2">Beim Zurücknehmen der Aufgabenfreigabe bleibt die MC-Testfreigabe bestehen. Sie kann in der Teststeuerung separat zurückgenommen werden.</p>
                </template>
            </v-card-text>
            <v-card-actions class="flex-wrap ga-2">
                <v-spacer />
                <v-btn :disabled="saving || loading" @click="dialog = false">Abbrechen</v-btn>
                <v-btn v-if="!ready && !loading" @click="open">Erneut laden</v-btn>
                <v-btn v-if="ready" color="primary" variant="flat" :loading="saving" :disabled="mcLoading || !!mcError" @click="save(true)">{{ dialogPublished ? 'Freigabe aktualisieren' : 'Jetzt bereitstellen' }}</v-btn>
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
import { show, studentDistribution, studentDistributionPreview } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { folders } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import CurriculumPdfPreview from '../../curricula/CurriculumPdfPreview.vue'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, available: Boolean, disabled: Boolean })
const emit = defineEmits(['saved'])
const dialog = ref(false)
const saving = ref(false)
const loading = ref(false)
const ready = ref(false)
const currentWork = ref(null)
const error = ref('')
const instructions = ref('')
const files = ref([])
const assignmentFile = ref(null)
const assignmentFileId = ref('')
const assignmentSection = ref('Angaben')
const assignmentWarning = ref('')
const mcTests = ref([])
const mcLoading = ref(false)
const mcError = ref('')
const previewOpen = ref(false)
const previewUrl = ref('')
const expectedVersion = ref('')
const retainedFileIds = ref([])
const existingFiles = computed(() => (currentWork.value?.status?.student_task_files || []).filter(file => currentWork.value?.status?.student_distribution?.file_ids?.includes(file.id)))
const published = computed(() => Boolean(props.work.status?.student_distribution?.published_at && !props.work.status.student_distribution.withdrawn_at))
const dialogPublished = computed(() => Boolean(currentWork.value?.status?.student_distribution?.published_at && !currentWork.value.status.student_distribution.withdrawn_at))
const assignmentParams = computed(() => ({ contents: 1, exercise: 1, section: 'Angaben', ...(props.date ? { date: props.date } : {}) }))

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
watch(dialog, isOpen => { if (!isOpen) previewOpen.value = false })
onBeforeUnmount(clearPreview)

function mcStatus(status) {
    return ({ draft: 'Noch nicht freigegeben', published: 'Bereits freigegeben', withdrawn: 'Freigabe zurückgenommen' })[status] || 'Status unbekannt'
}

function formatDeadline(value) {
    const date = new Date(value)
    if (!Number.isFinite(date.getTime())) return 'Ungültige Schlussfrist'
    return `${new Intl.DateTimeFormat('de-AT', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Vienna' }).format(date)} Uhr`
}

async function loadMcTests() {
    mcLoading.value = true
    mcError.value = ''
    try {
        const response = await axios.get(studentDistributionPreview.url(props.work.id))
        const tests = response.data?.data?.tests
        if (!Array.isArray(tests) || tests.some(test => !test.id || !test.version || !['individual', 'common'].includes(test.start_mode))) {
            throw new Error('Die MC-Testdaten sind unvollständig.')
        }
        mcTests.value = tests.map(test => ({ ...test, selectedMode: test.start_mode }))
    } catch (failure) {
        mcTests.value = []
        mcError.value = failure.response?.data?.message || 'Die MC-Tests konnten nicht geladen werden. Bitte erneut versuchen.'
    } finally {
        mcLoading.value = false
    }
}


async function readAssignmentPdf(fileId) {
    const response = await axios.get(folders.url(props.work.id), {
        params: { ...assignmentParams.value, pdf: 1, file_id: fileId }, responseType: 'blob', timeout: 120000,
    })
    if (!(response.data instanceof Blob) || response.data.type !== 'application/pdf' || response.data.size > 8 * 1024 * 1024) {
        throw new Error('Die Aufgabenstellung muss eine gültige PDF mit höchstens 8 MB sein.')
    }
    return response.data
}

async function loadAssignment() {
    const entries = []
    let cursor = null
    for (let page = 0; page < 10; page++) {
        const response = await axios.get(folders.url(props.work.id), {
            params: { ...assignmentParams.value, ...(cursor ? { cursor } : {}) }, timeout: 120000,
        })
        entries.push(...response.data.entries)
        assignmentSection.value = response.data.root?.name || 'Angaben'
        cursor = response.data.cursor
        if (!cursor) break
    }
    if (cursor) throw new Error('Die Aufgabenliste konnte nicht vollständig geladen werden.')
    const filenamePattern = assignmentSection.value === 'Quellunterlagen'
        ? /^(?:Aufgabenstellung|Leistungsfeststellung)_.+\.pdf$/i : /^Aufgabenstellung_.+\.pdf$/i
    const candidates = [...new Map(entries.filter(entry => entry.type === 'file' && filenamePattern.test(entry.name))
        .map(entry => [entry.id, entry])).values()]
    if (candidates.length !== 1) throw new Error(`Unter „${assignmentSection.value}“ wurde keine eindeutige Aufgabenstellungs-PDF gefunden. Bitte ein Aufgaben-PDF auswählen.`)
    const candidate = candidates[0]
    const pdf = await readAssignmentPdf(candidate.id)
    assignmentFile.value = new File([pdf], candidate.name, { type: 'application/pdf' })
    assignmentFileId.value = candidate.id
}

async function open() {
    dialog.value = true
    ready.value = false
    loading.value = true
    error.value = ''
    assignmentWarning.value = ''
    mcError.value = ''
    mcTests.value = []
    assignmentFile.value = null
    assignmentFileId.value = ''
    assignmentSection.value = 'Angaben'
    try {
        const response = await axios.get(show.url(props.work.id))
        const work = response.data?.data
        if (String(work?.id) !== String(props.work.id) || !work.student_distribution_version) throw new Error('Invalid work')
        currentWork.value = work
        expectedVersion.value = work.student_distribution_version
        retainedFileIds.value = existingFiles.value.map(file => file.id)
        files.value = []
        if (Object.keys(work.status?.multiple_choice_tests || {}).length) await loadMcTests()
        if (!work.local_task_copy && !work.status?.student_distribution?.published_at && props.available) {
            try {
                await loadAssignment()
            } catch (failure) {
                assignmentWarning.value = failure.response?.status === 404
                    ? 'Im verknüpften Prüfungsordner wurde kein Aufgabenbereich gefunden. Bitte ein Aufgaben-PDF auswählen.'
                    : failure.response?.data?.message || failure.message || 'Die Aufgabenstellung konnte nicht geladen werden. Bitte ein Aufgaben-PDF auswählen.'
            }
        }
        instructions.value = work.status?.student_distribution?.instructions
            ?? (assignmentFile.value ? 'Siehe Aufgaben-PDF.' : new DOMParser().parseFromString(work.description || '', 'text/html').body.textContent)
        ready.value = true
    } catch {
        error.value = 'Der aktuelle Stand konnte nicht geladen werden. Bitte erneut versuchen.'
    } finally {
        loading.value = false
    }
}

async function save(publish) {
    if (publish && (mcLoading.value || mcError.value)) return
    if (publish && !instructions.value?.trim() && !assignmentFile.value && files.value.length === 0 && retainedFileIds.value.length === 0) {
        error.value = 'Bitte einen Aufgabentext eingeben oder ein Aufgaben-PDF auswählen.'
        return
    }
    saving.value = true
    error.value = ''
    try {
        if (publish && assignmentFile.value) {
            const latest = await readAssignmentPdf(assignmentFileId.value)
            const [selectedBytes, latestBytes] = await Promise.all([assignmentFile.value.arrayBuffer(), latest.arrayBuffer()])
            const selected = new Uint8Array(selectedBytes)
            const current = new Uint8Array(latestBytes)
            if (selected.length !== current.length || selected.some((byte, index) => byte !== current[index])) {
                throw new Error('Die Aufgabenstellung wurde geändert. Bitte den Aufgabentext bei Bedarf kopieren, den Dialog schließen und erneut öffnen.')
            }
        }
        const data = new FormData()
        data.append('publish', publish ? '1' : '0')
        data.append('instructions', instructions.value)
        data.append('expected_version', expectedVersion.value)
        if (publish) data.append('mc_tests', JSON.stringify(mcTests.value.map(test => ({ id: test.id, expected_version: test.version, start_mode: test.selectedMode }))))
        for (const id of retainedFileIds.value) data.append('retained_file_ids[]', id)
        if (publish && assignmentFile.value) data.append('files[]', assignmentFile.value)
        for (const file of files.value) data.append('files[]', file)
        const response = await axios.post(studentDistribution.url(props.work.id), data)
        emit('saved', response.data.data)
        dialog.value = false
    } catch (failure) {
        error.value = failure.response?.status === 409
            ? `${failure.response?.data?.message || 'Die Arbeit oder der MC-Test wurde während der Eingabe geändert.'} Bitte den Aufgabentext bei Bedarf kopieren, den Dialog schließen und erneut öffnen.`
            : Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || failure.message || 'Die Freigabe konnte nicht gespeichert werden.'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.assignment-pdf-preview { height: 70vh; min-height: 320px; }
.mc-start-options { border: 0; padding: 0; }
.mc-start-options label { display: block; }
.mc-start-options input { margin-right: 6px; }
</style>
