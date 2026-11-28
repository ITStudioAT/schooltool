<template>
    <div class="d-inline-flex flex-column align-start ga-1">
        <div class="d-flex flex-wrap align-center ga-2">
            <VChip v-if="completed && !iconOnly" color="success" variant="tonal" prepend-icon="mdi-check-circle-outline"
                size="small" label class="work-completion-chip font-weight-bold">{{ workCompletedLabel }}</VChip>
            <VBtn :data-testid="`work-completion-toggle-${work.id}`"
                :text="iconOnly ? undefined : label"
                :icon="iconOnly ? modeIcon : undefined"
                :prepend-icon="iconOnly ? undefined : (completed ? 'mdi-undo' : 'mdi-check')"
                :min-height="iconOnly ? undefined : 44"
                :aria-label="iconOnly ? `${work.title || 'Arbeit'}: ${label}` : undefined"
                :aria-pressed="iconOnly ? completed : undefined"
                :title="iconOnly ? label : undefined"
                :color="iconOnly ? (completed ? 'success' : 'error') : (completed || quiet ? undefined : 'primary')"
                :variant="iconOnly || completed || quiet ? 'text' : 'tonal'" size="small" class="work-completion-action text-none"
                :disabled="disabled || Boolean(works.completionSavingId)"
                :loading="works.completionSavingId === work.id" @click.stop="toggle" />
        </div>
        <span v-if="error" role="alert" class="text-caption text-error">{{ error }}</span>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { VBtn } from 'vuetify/components/VBtn'
import { VChip } from 'vuetify/components/VChip'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workCompletedLabel, workWorkflowProgress } from '@/helpers/workProgress'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'

const props = defineProps({ work: { type: Object, required: true }, date: String, now: Number, iconOnly: Boolean, disabled: Boolean, quiet: Boolean })
const courses = useCourseStore()
const works = useCourseWorkStore()
const error = ref('')
const completed = computed(() => workWorkflowProgress(props.work, props.date, props.now, undefined,
    courses.selected_course?.students_info || []).completed)
const modeIcon = computed(() => workAutomationEnabled(props.work) ? 'mdi-brain' : 'mdi-account-check-outline')
const label = computed(() => completed.value ? 'Als offen markieren' : 'Als erledigt markieren')

async function toggle() {
    if (props.disabled || works.completionSavingId) return
    error.value = ''
    try {
        await works.setCompletion(props.work, !completed.value)
    } catch (saveError) {
        error.value = saveError.response?.data?.message || saveError.message || 'Abschlussmarkierung konnte nicht gespeichert werden.'
    }
}
</script>

<style scoped>
.v-theme--light.work-completion-chip {
    color: rgb(var(--v-theme-success-darken-1)) !important;
}

.work-completion-action {
    letter-spacing: normal;
}
</style>
