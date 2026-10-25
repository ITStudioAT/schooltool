<template>
    <span v-if="needsPreparation" class="exercise-action" @click.stop>
        <template v-if="exerciseFolder">
            <span v-if="!hideFolderName" class="exercise-folder text-body-2" aria-label="Leistungsfeststellungsordner">{{ exerciseFolder.name }}</span>
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
                <v-checkbox v-model="includeMc" label="Multiple-Choice-Fragen einbeziehen" :disabled="loading" hide-details />
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
                <v-btn :color="copied ? 'success' : 'primary'" :prepend-icon="copied ? 'mdi-check' : undefined" :loading="loading" :disabled="loading || !current" @click="copyExport">{{ copied ? 'Kopiert' : 'Übergabetext kopieren' }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { aiExport, show } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
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

function openDialog() {
    if (!current.value || !moduleLinked.value || props.disabled || exerciseChecking.value || exerciseFolder.value) return
    clear()
    includeMc.value = false
    opened.value = true
}

async function copyExport() {
    if (!opened.value || !current.value || !moduleLinked.value || loading.value) return
    clear()
    const operation = sequence
    const workId = props.work.id
    const courseId = props.course.id
    const mc = includeMc.value
    controller = new AbortController()
    loading.value = true
    try {
        const { data } = await axios.get(aiExport.url(workId), { params: { purpose: 'exercise', include_mc: mc ? 1 : 0,
            ...(props.date ? { date: props.date } : {}) }, signal: controller.signal })
        if (operation !== sequence || !opened.value || !current.value) return
        const exported = data.data
        if (String(exported?.work_id) !== String(workId) || String(exported?.course_id) !== String(courseId)
            || exported.roster_complete !== true || !Number.isInteger(exported.participants_count) || exported.participants_count < 1
            || typeof exported.text !== 'string' || !exported.text.trim() || exported.context?.include_multiple_choice !== mc) {
            throw new Error('Arbeitsdaten und Prüfungskandidaten konnten nicht vollständig bestätigt werden.')
        }
        result.value = exported
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

watch(() => [admin.config?.user?.id, props.work?.id, props.course?.id, props.date], () => { clear(); opened.value = false })
watch(opened, value => { if (!value) clear() })
watch(includeMc, clear)
watch(() => [admin.config?.user?.id, props.work?.id, props.course?.id, props.date, props.active, bindingVersion.value, needsPreparation.value,
    props.work?.finish_until_date, props.work?.finish_until_time, JSON.stringify(props.work?.communicated_deadline)], () => checkExercise(), { immediate: true })
onBeforeUnmount(() => { clear(); exerciseSequence++ })
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
