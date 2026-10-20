<template>
    <v-icon v-if="work?.id" :size="size" :color="color" :title="tooltip" :aria-label="tooltip" :aria-hidden="false" role="img">
        mdi-clipboard-text-outline
    </v-icon>
</template>

<script setup>
import { computed } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

const props = defineProps({ work: Object, date: { type: String, default: '' }, state: Object, size: { type: [Number, String], default: 30 } })
const admin = useAdminStore()
const status = computed(() => {
    const state = props.state
    if (!state || state.userId !== admin.config?.user?.id || String(state.workId) !== String(props.work?.id)
        || String(state.courseId) !== String(props.work?.teaching_course_id) || state.date !== props.date) return 'checking'
    return state.status
})
const color = computed(() => status.value === 'found' ? 'success' : status.value === 'missing' ? 'error' : 'grey')
const tooltip = computed(() => {
    if (status.value === 'found') return `Leistungsfeststellung erstellt: ${props.state.folder?.name || ''}`
    if (status.value === 'missing') return 'Leistungsfeststellung noch nicht erstellt'
    if (status.value === 'unknown') return 'Leistungsfeststellung konnte nicht geprüft werden'
    return 'Leistungsfeststellung wird geprüft …'
})
</script>
