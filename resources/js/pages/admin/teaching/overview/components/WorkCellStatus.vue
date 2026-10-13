<template>
    <span class="d-inline-flex align-center ga-1">
        <v-icon v-if="showTasks" icon="mdi-file-send-outline" size="16"
            :color="tasks?.mode === 'live' ? 'success' : 'orange-darken-2'"
            :aria-label="taskLabel" data-work-status="tasks" />
        <WorkSubmissionStatus :work="work" :student-id="studentId" :now="now" :native-title="false" />
        <v-icon :icon="results?.mode === 'live' ? 'mdi-email-check-outline' : 'mdi-email-alert-outline'" size="16"
            :color="results?.mode === 'live' ? 'success' : 'orange-darken-2'"
            :aria-label="resultLabel" data-work-status="results" />
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { dispatchNotificationText, workDispatchRecord } from '@/helpers/workDispatch'
import { personalWorkProvision } from '@/helpers/workSubmission'
import WorkSubmissionStatus from './WorkSubmissionStatus.vue'

const props = defineProps({
    work: { type: Object, default: null },
    studentId: { type: [Number, String], default: null },
    now: { type: Number, default: undefined },
})
const tasks = computed(() => workDispatchRecord(props.work, props.studentId, 'tasks'))
const results = computed(() => workDispatchRecord(props.work, props.studentId, 'results'))
const showTasks = computed(() => tasks.value?.mode === 'live' || !personalWorkProvision(props.work, props.studentId))
function notificationLabel(record, purpose) {
    if (!record) return `${purpose === 'tasks' ? 'Aufgabenversand' : 'Ergebnisbenachrichtigung'}: kein bestätigter E-Mail-Versand.`
    return dispatchNotificationText(['prepared', 'scheduled'].includes(record.mode) ? record.planned_at : record.sent_at,
        purpose, record.mode)
}
const taskLabel = computed(() => notificationLabel(tasks.value, 'tasks'))
const resultLabel = computed(() => notificationLabel(results.value, 'results'))
</script>
