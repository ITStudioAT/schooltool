<template>
    <div v-if="work?.id" class="d-inline-flex flex-column align-start ga-1">
        <v-btn size="small" variant="tonal" prepend-icon="mdi-refresh" :loading="loading" :disabled="disabled || loading" @click="check">
            <span class="d-flex flex-column align-start"><span>Erneut prüfen</span><span v-if="checkedAt" class="text-caption text-none">Letzter Check: {{ assessmentTimeText(checkedAt) }}</span></span>
        </v-btn>
        <span v-if="error" role="alert" class="text-caption text-error">{{ error }}</span>
        <WorkCheckProgress :state="progressState" :unavailable="progressUnavailable" />
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { show } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { assessmentTimeText } from '@/helpers/workAssessmentTimes'
import WorkCheckProgress from './WorkCheckProgress.vue'
import { useWorkCheckProgress } from './workCheckProgress'

const props = defineProps({ work: { type: Object, required: true }, disabled: { type: Boolean, default: false } })
const emit = defineEmits(['saved', 'busy'])
const loading = ref(false)
const checkedAt = ref(null)
const error = ref('')
const { state: progressState, unavailable: progressUnavailable, start: startProgress,
    finish: finishProgress, reset: resetProgress } = useWorkCheckProgress()
let checkSequence = 0
async function check() {
    if (props.disabled || loading.value) return
    const id = props.work.id
    const operation = ++checkSequence
    const progressId = crypto.randomUUID()
    const progressOperation = startProgress(id, progressId)
    let finalProgress = null
    let failed = false
    loading.value = true
    error.value = ''
    emit('busy', true)
    try {
        const response = await axios.get(show.url(id), { params: { refresh_collection: true, progress_id: progressId } })
        if (operation !== checkSequence) return
        finalProgress = response.data?.progress || null
        if (String(response.data?.data?.id) !== String(id) || String(response.data.data.teaching_course_id) !== String(props.work.teaching_course_id)) throw new Error('Arbeitsstand nicht bestätigt.')
        if (String(props.work.id) !== String(id)) return
        emit('saved', { data: response.data.data })
        checkedAt.value = new Date().toISOString()
    } catch (failure) {
        failed = true
        if (operation === checkSequence) error.value = failure.response?.data?.message || failure.message || 'Prüfung fehlgeschlagen.'
    } finally {
        if (finishProgress(progressOperation, finalProgress, failed)) emit('busy', false)
        if (operation === checkSequence) loading.value = false
    }
}
watch(() => [props.work?.id, props.work?.teaching_course_id], () => {
    checkSequence++
    if (loading.value) emit('busy', false)
    loading.value = false
    checkedAt.value = null
    error.value = ''
    resetProgress()
})
onBeforeUnmount(() => { checkSequence++; resetProgress() })
</script>
