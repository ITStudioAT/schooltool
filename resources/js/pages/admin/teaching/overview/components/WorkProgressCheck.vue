<template>
    <span v-if="work?.id" class="d-inline-flex flex-column align-start ga-1">
        <v-btn size="small" variant="tonal" prepend-icon="mdi-refresh" :loading="loading" :disabled="disabled || loading" @click="check">
            <span class="d-flex flex-column align-start"><span>Erneut prüfen</span><span v-if="checkedAt" class="text-caption text-none">Letzter Check: {{ assessmentTimeText(checkedAt) }}</span></span>
        </v-btn>
        <span v-if="error" role="alert" class="text-caption text-error">{{ error }}</span>
    </span>
</template>

<script setup>
import { ref } from 'vue'
import { show } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { assessmentTimeText } from '@/helpers/workAssessmentTimes'

const props = defineProps({ work: { type: Object, required: true }, disabled: { type: Boolean, default: false } })
const emit = defineEmits(['saved', 'busy'])
const loading = ref(false)
const checkedAt = ref(null)
const error = ref('')
async function check() {
    if (props.disabled || loading.value) return
    const id = props.work.id
    loading.value = true
    error.value = ''
    emit('busy', true)
    try {
        const response = await axios.get(show.url(id), { params: { refresh_collection: true } })
        if (String(response.data?.data?.id) !== String(id) || String(response.data.data.teaching_course_id) !== String(props.work.teaching_course_id)) throw new Error('Arbeitsstand nicht bestätigt.')
        if (String(props.work.id) !== String(id)) return
        emit('saved', { data: response.data.data })
        checkedAt.value = new Date().toISOString()
    } catch (failure) {
        error.value = failure.response?.data?.message || failure.message || 'Prüfung fehlgeschlagen.'
    } finally {
        loading.value = false
        emit('busy', false)
    }
}
</script>
