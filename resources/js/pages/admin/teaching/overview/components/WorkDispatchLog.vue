<template>
    <template v-for="group in groups" :key="group.purpose">
        <v-btn v-if="group.logs.length === 1" size="small" variant="tonal" :prepend-icon="group.icon"
            :href="downloadUrl(group.logs[0])" :title="group.logs[0].name" @click.stop>{{ group.label }}</v-btn>
        <v-menu v-else location="bottom start" :max-width="420" :max-height="320">
            <template #activator="{ props: activatorProps }">
                <v-btn v-bind="activatorProps" size="small" variant="tonal" :prepend-icon="group.icon"
                    append-icon="mdi-chevron-down" @click.stop>{{ group.label }}</v-btn>
            </template>
            <v-list density="compact" :aria-label="group.label" @click.stop>
                <v-list-item v-for="log in group.logs" :key="log.sha256" :href="downloadUrl(log)"
                    :title="logTitle(log)" :subtitle="logDescription(log)" :prepend-icon="group.icon" @click.stop />
            </v-list>
        </v-menu>
    </template>
</template>

<script setup>
import { computed } from 'vue'
import { downloadDispatch } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'

const props = defineProps({ work: { type: Object, default: null } })
const logs = computed(() => props.work?.id
    ? (props.work.status?.dispatch_logs || []).filter(log => log.origin === 'dispatch_import' && log.sha256)
    : [])
const groups = computed(() => ['tasks', 'results'].map(purpose => ({
    purpose, label: purpose === 'tasks' ? 'Download Aufgabenversand' : 'Download Ergebnisbenachrichtigung',
    icon: purpose === 'tasks' ? 'mdi-file-send-outline' : 'mdi-email-outline',
    logs: logs.value.filter(log => (log.purpose || 'results') === purpose)
        .sort((first, second) => String(second.dispatch_at || second.name).localeCompare(String(first.dispatch_at || first.name))),
})).filter(group => group.logs.length))
const downloadUrl = log => downloadDispatch.url({ course_work: props.work.id, sha256: log.sha256 })
function logTitle(log) {
    if (log.dispatch_at && Number.isFinite(Date.parse(log.dispatch_at))) {
        return new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna', dateStyle: 'medium', timeStyle: 'short' }).format(new Date(log.dispatch_at))
    }
    const run = /Versand_(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})/.exec(log.name || '')
    return run ? `${run[3]}.${run[2]}.${run[1]} um ${run[4]}:${run[5]}:${run[6]} Uhr` : log.name || 'Versandprotokoll'
}
function logDescription(log) {
    const records = (props.work.status?.dispatch_notifications || []).filter(record => record.log_sha256 === log.sha256 && record.student_id)
    const attempts = (props.work.status?.dispatch_attempts || []).filter(record => record.log_sha256 === log.sha256 && record.student_id)
    const mode = log.mode === 'teacher_test' ? 'Live-Test' : log.mode === 'test' || attempts.some(record => record.mode === 'test')
        ? 'Mailpit-Test' : records.length ? 'Bestätigter Live-Versand' : log.mode === 'live' ? 'Live-Protokoll' : 'Versandprotokoll'
    const recipients = log.recipient_scope === 'teacher' ? 'nur Lehrperson' : records.length || attempts.length ? 'Schüler:innen' : ''
    return [mode, recipients].filter(Boolean).join(' · ')
}
</script>
