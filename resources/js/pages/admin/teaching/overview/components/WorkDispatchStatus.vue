<template>
    <span v-if="records.length || showAssessmentTimes" :class="!compact && (aggregate || showResultTime) ? 'd-flex flex-column ga-1' : 'd-inline-flex align-center ga-1'">
        <template v-for="item in records" :key="item.purpose + (item.record.method || '')">
            <template v-if="showAssessmentTimes && item.purpose === 'results'">
                <span class="d-inline-flex align-start ga-1 text-caption"><v-icon icon="mdi-inbox-arrow-down" size="16" />Abgabe eingesammelt: {{ assessmentTimeText(assessment?.email_collected_at) }}</span>
                <span class="d-inline-flex align-start ga-1 text-caption"><v-icon icon="mdi-clipboard-check-outline" size="16" />Beurteilung abgeschlossen: {{ assessmentCompletionText(assessment?.evaluation_completed_at, assessment?.evaluation_state) }}</span>
            </template>
            <span class="d-inline-flex align-start ga-1">
            <v-icon
                :icon="item.purpose === 'provision' ? 'mdi-file-check-outline' : item.purpose === 'tasks' ? 'mdi-file-send-outline' : item.record.mode === 'live' ? 'mdi-email-check-outline' : 'mdi-email-alert-outline'"
                size="16"
                :color="item.purpose === 'provision' ? 'info' : item.record.mode === 'live' ? 'success' : 'grey-darken-1'"
                :title="nativeTitle ? item.label : undefined"
                :aria-label="item.label" />
            <span v-if="!compact && (aggregate || showResultTime)" class="text-caption">{{ visibleText(item) }}</span>
            </span>
        </template>
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { dispatchNotificationText, workDispatchRecord, workProvisionRecords, provisionNotificationText } from '@/helpers/workDispatch'
import { assessmentTimeText, assessmentCompletionText, currentAssessmentRecord } from '@/helpers/workAssessmentTimes'

const props = defineProps({
    work: { type: Object, default: null },
    studentId: { type: [Number, String], default: null },
    aggregate: { type: Boolean, default: false },
    showResultTime: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    nativeTitle: { type: Boolean, default: true },
})
const resultSentText = sentAt => dispatchNotificationText(sentAt)
    .replace('Über die Korrektur per E-Mail verständigt', props.aggregate ? 'Ergebnisbenachrichtigung zuletzt versandt' : 'Ergebnisbenachrichtigung versandt')
const showAssessmentTimes = computed(() => workAutomationEnabled(props.work) && !props.compact && !props.aggregate && props.showResultTime && props.work?.id && props.studentId)
const assessment = computed(() => currentAssessmentRecord(props.work, props.studentId))
function visibleText(item) {
    if (item.purpose === 'provision') return item.label
    if (item.record.mode === 'live' && item.purpose === 'results') return resultSentText(item.record.sent_at)
    if (item.record.mode === 'live') return dispatchNotificationText(item.record.sent_at, 'tasks').replace('Aufgaben per E-Mail versandt', 'Aufgabenversand')
    if (item.record.mode === 'test') return dispatchNotificationText(item.record.sent_at, item.purpose, 'test')
        .replace(': lokaler Mailpit-Test', ' · Test')
    if (['prepared', 'scheduled'].includes(item.record.mode)) return dispatchNotificationText(item.record.planned_at, item.purpose, item.record.mode)
    return item.purpose === 'tasks' ? 'Kein bestätigter Aufgabenversand.' : 'Keine bestätigte Ergebnisbenachrichtigung.'
}
const records = computed(() => {
    if (!workAutomationEnabled(props.work)) return []
    const provisionEvents = workProvisionRecords(props.work, props.studentId, props.aggregate)
    const provisions = ['download_release', 'direct_issue'].flatMap(method => {
        const events = provisionEvents.filter(event => event.method === method).sort((first, second) => Date.parse(first.available_at) - Date.parse(second.available_at))
        if (!events.length) return []
        const record = props.aggregate ? events.at(-1) : events[0]
        return [{ purpose: 'provision', record, label: provisionNotificationText(record, props.aggregate, new Set(events.map(event => event.student_id)).size) }]
    })
    const items = [...provisions, ...['tasks', 'results'].map(purpose => {
        const record = workDispatchRecord(props.work, props.studentId, purpose, props.aggregate)
        const label = props.compact && record?.mode === 'live'
            ? `${purpose === 'tasks' ? 'Aufgabenversand' : 'Ergebnisbenachrichtigung'}: ${dispatchNotificationText(record.sent_at, purpose)}`
            : props.aggregate && record?.mode === 'live'
                ? (purpose === 'tasks' ? 'Mindestens eine Aufgaben-E-Mail versandt' : 'Mindestens eine Ergebnis-E-Mail versandt')
                : props.compact && record?.mode === 'test'
                    ? dispatchNotificationText(record.sent_at, purpose, 'test').replace(': lokaler Mailpit-Test', ' · Test')
                    : dispatchNotificationText(['prepared', 'scheduled'].includes(record?.mode) ? record.planned_at : record?.sent_at, purpose, record?.mode)
        return { purpose, record, label }
    })].filter(item => item.purpose !== 'tasks' || item.record || !provisions.length)
    if (!props.compact && (props.aggregate || props.showResultTime) && (items.some(item => item.record) || showAssessmentTimes.value)) {
        return items.map(item => item.record ? item : { ...item, record: { mode: 'unconfirmed' }, label: item.purpose === 'tasks' ? 'Kein bestätigter Aufgabenversand.' : 'Keine bestätigte Ergebnisbenachrichtigung.' })
    }
    return items.filter(item => item.record)
})
</script>
