<template>
    <v-icon
        icon="mdi-inbox-arrow-down"
        size="16"
        :color="status.color"
        :title="nativeTitle ? status.label : undefined"
        :aria-label="status.label"
        :data-submission-state="status.state" />
</template>

<script setup>
import { computed } from 'vue'
import { workSubmissionStatus } from '@/helpers/workSubmission'

const props = defineProps({
    work: { type: Object, default: null },
    studentId: { type: [Number, String], default: null },
    now: { type: Number, default: undefined },
    nativeTitle: { type: Boolean, default: true },
})
const status = computed(() => workSubmissionStatus(props.work, props.studentId, props.now))
</script>
