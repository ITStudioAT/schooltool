<template>
    <v-btn v-if="work?.id && workAutomationEnabled(work)" size="small" variant="tonal" prepend-icon="mdi-inbox-arrow-down-outline" :disabled="disabled || !hasSentTasks" @click.stop="openAction">{{ repeat ? 'Erneut einsammeln' : 'Abgaben einsammeln' }}</v-btn>
    <v-dialog v-if="opened" v-model="opened" max-width="720" scrollable :persistent="loading">
        <v-card>
            <v-card-title class="text-wrap">{{ repeat ? 'Abgaben erneut einsammeln' : 'Abgaben einsammeln' }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <p class="mb-3">KI-Auftrag kopieren und an die KI übergeben. Vorhandene Abgaben und Bewertungen bleiben erhalten.</p>
                <p v-if="loading" role="status">KI-Auftrag wird geladen …</p>
                <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
                <template v-if="result">
                    <details><summary>Übergabetext anzeigen</summary><textarea ref="copyText" :value="result.text" readonly rows="10" aria-label="KI-Auftrag zum Einsammeln" class="collection-order-text" /></details>
                    <p v-if="message" role="status" class="mt-3" :class="copied ? 'text-success' : 'text-warning'">{{ message }}</p>
                </template>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn :disabled="!result || loading || copying || disabled || !hasSentTasks" :color="copied ? 'success' : 'primary'" :prepend-icon="copied ? 'mdi-check' : 'mdi-content-copy'" @click="copyOrder">{{ copied ? 'Kopiert' : 'Auftrag kopieren' }}</v-btn>
                <v-btn :disabled="loading" @click="opened = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { aiExport } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { workDispatchNotifications, workStudentDistribution, workTaskDispatchCompleted } from '@/helpers/workDispatch'
import { useAdminStore } from '@/stores/admin/AdminStore'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, repeat: Boolean, disabled: Boolean, dispatch: { type: Object, default: null } })
const admin = useAdminStore()
const hasSentTasks = computed(() => {
    const date = props.date || props.work.date_for_all_groups || ''
    const releaseGroups = (props.work.groups || []).filter(group => !date || (group.date || props.work.date_for_all_groups) === date)
    const releaseStudents = new Set(releaseGroups.flatMap(group => group.student_ids || []).map(String))
    const release = workStudentDistribution(props.work)
    if (release?.student_ids?.some(id => releaseStudents.has(String(id)))) return true
    if (workTaskDispatchCompleted(props.work)) return true
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const cached = workExerciseCheck(key, props.work, date)?.data
    const checked = cached?.exercise?.email_dispatch ? cached : workDropboxStates.get(key)
    const status = props.dispatch || (checked?.exercise?.status === 'found' && (!date || checked.exercise.folder?.name?.startsWith(`${date}_`))
        ? checked.exercise.email_dispatch : null)
    if (status) return ['sent', 'partial'].includes(status.status) && Number(status.confirmed_count) > 0
    const groups = (props.work.groups || []).filter(group => !date || !group.date || group.date === date)
    const students = new Set(groups.flatMap(group => group.student_ids || []).map(String))
    return workDispatchNotifications(props.work, 'tasks').some(notification => students.has(String(notification.student_id)))
})
const opened = ref(false)
const loading = ref(false)
const copying = ref(false)
const copied = ref(false)
const result = ref(null)
const error = ref('')
const message = ref('')
const copyText = ref(null)
let sequence = 0
let controller = null

function clear() {
    sequence++
    controller?.abort()
    loading.value = false
    copying.value = false
    copied.value = false
    result.value = null
    error.value = ''
    message.value = ''
}

async function openAction() {
    if (props.disabled || !hasSentTasks.value || !props.work?.id || !workAutomationEnabled(props.work)) return
    clear()
    opened.value = true
    loading.value = true
    const operation = sequence
    const mode = props.repeat ? 'repeat' : 'collect'
    controller = new AbortController()
    try {
        const response = await axios.get(aiExport.url(props.work.id), { params: { purpose: 'submission_collection', collection_mode: mode,
            ...(props.date ? { date: props.date } : {}) }, signal: controller.signal })
        if (operation !== sequence || !opened.value) return
        const data = response.data?.data
        if (String(data?.work_id) !== String(props.work.id) || String(data?.course_id) !== String(props.work.teaching_course_id)
            || data.roster_complete !== true || data.context?.purpose !== 'collect-exercise-submissions' || data.context?.mode !== mode
            || data.context?.operation?.collect_submissions !== true || data.context?.operation?.preserve_existing !== true
            || data.context?.operation?.grade !== false || data.context?.operation?.send !== false || data.context?.operation?.schooltool_import !== false
            || typeof data.text !== 'string' || !data.text.trim()) throw new Error('Der Einsammelauftrag konnte nicht eindeutig bestätigt werden.')
        result.value = data
    } catch (failure) {
        if (operation === sequence && !controller?.signal.aborted) error.value = failure.response?.data?.message || failure.message || 'KI-Auftrag konnte nicht geladen werden.'
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function copyOrder() {
    if (!result.value || loading.value || copying.value || props.disabled || !hasSentTasks.value) return
    const operation = sequence
    copying.value = true
    copied.value = false
    message.value = ''
    try {
        await navigator.clipboard.writeText(result.value.text)
        if (operation === sequence) {
            copied.value = true
            message.value = 'KI-Auftrag kopiert. Jetzt an die KI übergeben.'
        }
    } catch {
        if (operation !== sequence) return
        const details = copyText.value?.closest('details')
        if (details) details.open = true
        copyText.value?.focus()
        copyText.value?.select()
        message.value = 'Automatisches Kopieren ist nicht verfügbar. Der Text ist markiert; bitte mit Strg+C kopieren.'
    } finally {
        if (operation === sequence) copying.value = false
    }
}

watch(() => [props.work?.id, props.work?.teaching_course_id, props.work?.updated_at, props.date, props.repeat], () => { opened.value = false; clear() })
watch(hasSentTasks, value => { if (!value && opened.value) { opened.value = false; clear() } })
watch(opened, value => { if (!value) clear() })
onBeforeUnmount(clear)
</script>

<style scoped>
.collection-order-text { width: 100%; margin-top: 12px; padding: 10px; border: 1px solid #aaa; border-radius: 4px; font: 12px/1.5 monospace; white-space: pre-wrap; }
</style>
