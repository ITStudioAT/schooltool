<template>
    <span v-if="records.length" :class="!compact && (aggregate || showResultTime) ? 'd-flex flex-column ga-1' : 'd-inline-flex align-center ga-1'">
        <span v-for="item in records" :key="item.purpose" class="d-inline-flex align-start ga-1">
            <v-icon
                :icon="item.purpose === 'tasks' ? 'mdi-file-send-outline' : item.record.mode === 'live' ? 'mdi-email-check-outline' : 'mdi-email-alert-outline'"
                size="16"
                :color="item.record.mode === 'live' ? 'success' : 'grey-darken-1'"
                :title="item.label"
                :aria-label="item.label" />
            <span v-if="!compact && (aggregate || showResultTime)" class="text-caption">{{ visibleText(item) }}</span>
        </span>
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { dispatchNotificationText, workDispatchRecord } from '@/helpers/workDispatch'

const props = defineProps({
    work: { type: Object, default: null },
    studentId: { type: [Number, String], default: null },
    aggregate: { type: Boolean, default: false },
    showResultTime: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
})
const resultSentText = sentAt => dispatchNotificationText(sentAt)
    .replace('Über die Korrektur per E-Mail verständigt', props.aggregate ? 'Ergebnisbenachrichtigung zuletzt versandt' : 'Ergebnisbenachrichtigung versandt')
function visibleText(item) {
    if (item.record.mode === 'live' && item.purpose === 'results') return resultSentText(item.record.sent_at)
    if (item.record.mode === 'live') return dispatchNotificationText(item.record.sent_at, 'tasks').replace('Aufgaben per E-Mail versandt', 'Aufgabenversand')
    if (item.record.mode === 'test') return dispatchNotificationText(item.record.sent_at, item.purpose, 'test')
        .replace(': lokaler Mailpit-Test', ' · Test')
    return item.purpose === 'tasks' ? 'Kein bestätigter Aufgabenversand.' : 'Keine bestätigte Ergebnisbenachrichtigung.'
}
const records = computed(() => {
    const items = ['tasks', 'results'].map(purpose => {
        const record = workDispatchRecord(props.work, props.studentId, purpose, props.aggregate)
        const label = props.compact && record?.mode === 'live'
            ? `${purpose === 'tasks' ? 'Aufgabenversand' : 'Ergebnisbenachrichtigung'}: ${dispatchNotificationText(record.sent_at, purpose)}`
            : props.aggregate && record?.mode === 'live'
                ? (purpose === 'tasks' ? 'Mindestens eine Aufgaben-E-Mail versandt' : 'Mindestens eine Ergebnis-E-Mail versandt')
                : props.compact && record?.mode === 'test'
                    ? dispatchNotificationText(record.sent_at, purpose, 'test').replace(': lokaler Mailpit-Test', ' · Test')
                    : dispatchNotificationText(record?.sent_at, purpose, record?.mode)
        return { purpose, record, label }
    })
    if (!props.compact && (props.aggregate || props.showResultTime) && items.some(item => item.record)) {
        return items.map(item => item.record ? item : { ...item, record: { mode: 'unconfirmed' }, label: item.purpose === 'tasks' ? 'Kein bestätigter Aufgabenversand.' : 'Keine bestätigte Ergebnisbenachrichtigung.' })
    }
    return items.filter(item => item.record)
})
</script>
