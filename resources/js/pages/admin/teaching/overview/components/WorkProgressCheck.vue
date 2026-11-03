<template>
    <div v-if="work?.id" class="d-inline-flex flex-column align-start ga-1" :class="{ 'w-100': progressState }">
        <v-btn size="small" variant="tonal" prepend-icon="mdi-refresh" :loading="loading" :disabled="disabled || loading" @click="check">
            Erneut prüfen
        </v-btn>
        <span v-if="error" role="alert" class="text-caption text-error">{{ error }}</span>
        <WorkCheckProgress :state="progressState" :unavailable="progressUnavailable" />
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { show } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import WorkCheckProgress from './WorkCheckProgress.vue'
import { createWorkCheckStream, useWorkCheckProgress } from './workCheckProgress'

const props = defineProps({ work: { type: Object, required: true }, disabled: { type: Boolean, default: false },
    checkKey: { type: String, default: null } })
const emit = defineEmits(['saved', 'busy', 'checked'])
const loading = ref(false)
const error = ref('')
const { state: progressState, unavailable: progressUnavailable, start: startProgress,
    update: updateProgress, finish: finishProgress, reset: resetProgress } = useWorkCheckProgress()
let checkSequence = 0
let previousCheck = { checkKey: null, checkedAt: null, completed: false }
const storagePrefix = 'schooltool:work-progress-check:v1:'

function restoreCheck(key) {
    if (!key) return { checkedAt: null, completed: false, checkKey: key }
    try {
        const saved = JSON.parse(localStorage.getItem(storagePrefix + key) || 'null')
        if (saved && typeof saved.completed === 'boolean' && Number.isFinite(Date.parse(saved.checkedAt))) {
            return { checkedAt: saved.checkedAt, completed: saved.completed, checkKey: key }
        }
    } catch { /* A blocked or invalid browser cache must not prevent checking. */ }

    return { checkedAt: null, completed: false, checkKey: key }
}

function publishCheck(result) {
    previousCheck = result
    if (result.checkKey && result.checkedAt) {
        try { localStorage.setItem(storagePrefix + result.checkKey, JSON.stringify(result)) } catch { /* Keep the current result. */ }
    }
    emit('checked', result)
}

async function check() {
    if (props.disabled || loading.value) return
    const id = props.work.id
    const checkKey = props.checkKey
    const operation = ++checkSequence
    const progressId = crypto.randomUUID()
    const progressOperation = startProgress(id, progressId, ['connection', 'folder', 'collection'], true)
    const stream = createWorkCheckStream(progress => updateProgress(progressOperation, progress))
    let finalProgress = null
    let failed = false
    loading.value = true
    error.value = ''
    emit('busy', true)
    try {
        const response = await axios.get(show.url(id), { params: { refresh_collection: true, progress_id: progressId, stream_progress: true }, ...stream.options })
        response.data = stream.result(response)
        if (operation !== checkSequence) return
        finalProgress = response.data?.progress || null
        if (String(response.data?.data?.id) !== String(id) || String(response.data.data.teaching_course_id) !== String(props.work.teaching_course_id)) throw new Error('Arbeitsstand nicht bestätigt.')
        if (String(props.work.id) !== String(id)) return
        emit('saved', { data: response.data.data })
        const validProgress = finalProgress && ['done', 'failed'].includes(finalProgress.state)
            && Array.isArray(finalProgress.completed) && Array.isArray(finalProgress.skipped)
        if (validProgress) {
            publishCheck({ checkKey, checkedAt: new Date().toISOString(),
                completed: finalProgress.state === 'done' && finalProgress.completed.includes('collection') && finalProgress.skipped.length === 0 })
        } else {
            emit('checked', previousCheck)
        }
    } catch (failure) {
        failed = true
        if (operation === checkSequence) {
            error.value = failure.response?.data?.message || failure.message || 'Prüfung fehlgeschlagen.'
            emit('checked', previousCheck)
        }
    } finally {
        if (finishProgress(progressOperation, finalProgress, failed)) emit('busy', false)
        if (operation === checkSequence) loading.value = false
    }
}
watch(() => [props.work?.id, props.work?.teaching_course_id, props.checkKey], () => {
    checkSequence++
    if (loading.value) emit('busy', false)
    loading.value = false
    error.value = ''
    resetProgress()
    previousCheck = restoreCheck(props.checkKey)
    emit('checked', previousCheck)
}, { immediate: true })
onBeforeUnmount(() => { checkSequence++; resetProgress() })
</script>
