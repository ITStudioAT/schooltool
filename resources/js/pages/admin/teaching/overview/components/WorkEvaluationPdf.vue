<template>
    <v-btn v-if="pdfUrl" size="small" variant="tonal" prepend-icon="mdi-file-pdf-box"
        :href="pdfUrl" :title="pdf.name" target="_blank" rel="noopener" @click.stop>
        {{ studentId === null ? 'Gesamtauswertung (PDF)' : 'Auswertung (PDF)' }}
    </v-btn>
</template>

<script setup>
import { computed } from 'vue'
import { downloadEvaluation } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'

const props = defineProps({
    work: { type: Object, default: null },
    studentId: { type: [Number, String], default: null },
})

const pdf = computed(() => (props.work?.status?.evaluation_pdfs || []).filter((attachment) => {
    if (attachment.origin !== 'evaluation_import') return false
    if (props.studentId === null) return attachment.student_id === null

    return attachment.student_id !== null && String(attachment.student_id) === String(props.studentId)
}).at(-1))

const pdfUrl = computed(() => {
    if (!props.work?.id || !pdf.value?.sha256) return null

    return downloadEvaluation.url({ course_work: props.work.id, sha256: pdf.value.sha256 }, { query: { inline: 1 } })
})
</script>
