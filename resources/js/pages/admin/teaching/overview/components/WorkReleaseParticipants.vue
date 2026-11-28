<template>
    <details class="release-students" @toggle="expanded = $event.currentTarget.open">
        <summary>
            {{ testIds.length ? 'Bearbeitungsstand der Multiple-Choice-Tests' : 'Zugeordnete Schüler:innen' }}
            <span class="release-students__count">{{ students.length }}<template v-if="testIds.length"> Schüler:innen</template></span>
        </summary>
        <div class="release-students__body">
            <p v-if="testIds.length" class="release-students__hint">Die Zeilen zeigen den Stand der MC-Versuche pro Person.</p>
            <p v-if="studentStatusError || actionError" class="text-error text-body-2 mb-2" role="alert">{{ actionError || studentStatusError }}</p>
            <div v-if="resetTarget" class="release-students__confirm">
                <strong>Versuch zurücksetzen?</strong>
                <p>{{ resetTarget.name }} · {{ resetTarget.attempt.test_title }}. Antworten und Ergebnisse dieses Versuchs werden gelöscht.</p>
                <div class="release-students__actions">
                    <v-btn min-height="44" variant="text" :disabled="saving" @click="resetTarget = null">Abbrechen</v-btn>
                    <v-btn min-height="44" variant="tonal" color="error" :loading="saving" :disabled="disabled"
                        @click="controlAttempt(resetTarget.attempt, 'reset')">Versuch zurücksetzen</v-btn>
                </div>
            </div>
            <p v-if="!students.length" class="text-body-2">Keine zugeordneten Schüler:innen verfügbar.</p>
            <ul v-else class="release-students__list">
                <li v-for="student in students" :key="student.id">
                    <span class="release-students__name">{{ student.name }}</span>
                    <ul v-if="scopeTests.length" class="release-students__tests">
                        <li v-for="test in scopeTests" :key="test.id" class="release-students__test">
                            <span v-if="scopeTests.length > 1" class="release-students__test-name">{{ test.title }}</span>
                            <div class="release-students__details">
                                <span class="release-students__status">{{ studentStatus(student.id, test.id, { requireActualStart: true }) }}</span>
                                <template v-for="attempt in attemptsForTest(student.id, test.id)" :key="attempt.id">
                                    <span v-if="attempt.started_at || studentStatus(student.id, test.id, { requireActualStart: true }) !== 'Noch nicht gestartet'"
                                        class="release-students__detail">{{ attempt.started_at ? 'Gestartet' : 'Noch nicht gestartet' }}</span>
                                    <span v-if="attemptRemainingTime(attempt)" class="release-students__detail release-students__remaining">
                                        Restzeit {{ attemptRemainingTime(attempt) }}
                                    </span>
                                    <span v-if="attemptTime(attempt)" class="release-students__detail">{{ attemptTime(attempt) }}</span>
                                    <span v-if="attemptScore(attempt)" class="release-students__detail">{{ attemptScore(attempt) }}</span>
                                </template>
                            </div>
                            <div v-if="editableIds.has(String(test.id))" class="release-students__actions">
                                <template v-for="attempt in attemptsForTest(student.id, test.id)" :key="attempt.id">
                                    <v-btn v-if="canPauseOrResume(attempt)" icon size="small" variant="text"
                                        :aria-label="attemptLabel(attempt.individual_paused_at ? 'Fortsetzen' : 'Pausieren', student.name, attempt)"
                                        :title="attemptLabel(attempt.individual_paused_at ? 'Fortsetzen' : 'Pausieren', student.name, attempt)"
                                        :disabled="disabled || busy" @click="controlAttempt(attempt, attempt.individual_paused_at ? 'resume' : 'pause')">
                                        <v-icon :icon="attempt.individual_paused_at ? 'mdi-play-circle-outline' : 'mdi-pause-circle-outline'" />
                                    </v-btn>
                                    <v-btn v-if="attempt.control_revision" icon size="small" variant="text"
                                        :aria-label="attemptLabel('Zurücksetzen', student.name, attempt)"
                                        :title="attemptLabel('Zurücksetzen', student.name, attempt)"
                                        :disabled="disabled || busy" @click="askReset(attempt, student.name)">
                                        <v-icon icon="mdi-backup-restore" />
                                    </v-btn>
                                </template>
                            </div>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </details>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { controlAttempts } from '@/actions/App/Http/Controllers/Admin/MultipleChoiceController'
import { useWorkMcAttemptStatus } from '@/composables/useWorkMcAttemptStatus'
import { formatMcClock, mcRemainingSeconds } from '@/helpers/mcClock'

const props = defineProps({ work: { type: Object, required: true }, date: String, tests: { type: Array, required: true },
    roster: { type: Array, required: true }, disabled: Boolean, revision: Number })
const emit = defineEmits(['busy', 'updated'])
const expanded = ref(false)
const clockNow = ref(Date.now())
const saving = ref(false)
const resetTarget = ref(null)
const actionError = ref('')
const busy = computed(() => saving.value || Boolean(resetTarget.value))
const students = computed(() => {
    const names = new Map(props.roster.map(student => [String(student.user_id ?? student.id), [student.last_name, student.first_name].filter(Boolean).join(' ')]))
    return (props.work.status?.student_distribution?.student_ids || []).map(id => ({ id, name: names.get(String(id)) || 'Name nicht verfügbar' }))
        .sort((a, b) => a.name.localeCompare(b.name, 'de'))
})
const scopeTests = computed(() => {
    const bindings = props.work.status?.multiple_choice_tests || {}
    const ids = [bindings[props.date || props.work.date_for_all_groups]?.test_id, bindings.all?.test_id].map(String)
    return props.tests.filter(test => ids.includes(String(test.id)))
})
const testIds = computed(() => scopeTests.value.map(test => test.id))
const editableIds = computed(() => new Set(props.tests.filter(test => test.can_control_attempts).map(test => String(test.id))))
const { studentStatus, studentStatusError, studentAttempts, studentTests, loadStudentStatuses,
    clearStudentStatuses } = useWorkMcAttemptStatus(testIds)
const dateTime = new Intl.DateTimeFormat('de-AT', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Vienna' })
const day = new Intl.DateTimeFormat('de-AT', { dateStyle: 'short', timeZone: 'Europe/Vienna' })
const pointFormat = new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 })

function attemptsForTest(studentId, testId) {
    return studentAttempts.value.filter(attempt => String(attempt.student_id) === String(studentId) && String(attempt.test_id) === String(testId))
}

function attemptTest(attempt) {
    return studentTests.value.find(test => String(test.id) === String(attempt.test_id))
}

function attemptRemainingTime(attempt) {
    if (!attempt.started_at || !canPauseOrResume(attempt) || !attempt.effective_ends_at) return ''
    const test = attemptTest(attempt)
    const serverTime = Number.isFinite(test?.serverOffset) ? clockNow.value + test.serverOffset : null
    if (serverTime !== null && Date.parse(attempt.fixed_closes_at) <= serverTime) return formatMcClock(0)
    const pauseTimes = [attempt.individual_paused_at, test?.paused_at].map(value => Date.parse(value)).filter(Number.isFinite)
    const referenceTime = pauseTimes.length ? Math.min(...pauseTimes) : serverTime
    if (referenceTime === null) return ''
    const seconds = mcRemainingSeconds(attempt.effective_ends_at, referenceTime)
    return Number.isFinite(seconds) ? formatMcClock(seconds) : ''
}

function readableTime(value) {
    const time = Date.parse(value)
    return Number.isFinite(time) ? `${dateTime.format(time)} Uhr` : ''
}

function attemptTime(attempt) {
    const completed = readableTime(attempt.completed_at)
    if (completed) return `Abgeschlossen am ${completed}`
    if (attempt.status === 'timeout') return ''
    const test = attemptTest(attempt)
    if (attempt.individual_paused_at || test?.paused_at) {
        const cap = Date.parse(test?.work_closes_at)
        if (!Number.isFinite(cap)) return ''
        return props.work.finish_until_time ? `Arbeitsfrist bis ${readableTime(test.work_closes_at)}` : `Arbeitsfrist am ${day.format(cap)}`
    }
    const deadline = attempt.started_at && readableTime(attempt.effective_ends_at)
    return deadline ? `Persönliches Ende ${deadline}` : ''
}

function attemptScore(attempt) {
    if (!attempt.completed_at || !usableScore(attempt.score)) return ''
    const max = attemptTest(attempt)?.total_points
    const score = pointFormat.format(Number(attempt.score))
    return usableScore(max) ? `${score} von ${pointFormat.format(Number(max))} Punkten` : `${score} Punkte`
}

function usableScore(value) {
    return (typeof value === 'number' || (typeof value === 'string' && value.trim() !== '')) && Number.isFinite(Number(value))
}

function canPauseOrResume(attempt) {
    return !attempt.completed_at && !['submitted', 'timeout'].includes(attempt.status)
}

function attemptLabel(action, name, attempt) {
    return `${action}: ${name} · ${attempt.test_title}`
}

function askReset(attempt, name) {
    if (props.disabled || busy.value) return
    actionError.value = ''
    resetTarget.value = { attempt, name }
}

async function controlAttempt(attempt, action) {
    if (props.disabled || saving.value || !testIds.value.some(id => String(id) === String(attempt.test_id))
        || !editableIds.value.has(String(attempt.test_id)) || !studentAttempts.value.includes(attempt)) return
    if (action === 'reset' ? resetTarget.value?.attempt !== attempt : busy.value || !canPauseOrResume(attempt)) return
    saving.value = true
    actionError.value = ''
    try {
        await axios.post(controlAttempts.url(attempt.test_id), { action, attempt_ids: [attempt.id],
            ...(action === 'reset' ? { expected: { [attempt.id]: attempt.control_revision }, confirmed: true } : {}) })
        emit('updated')
    } catch (failure) {
        actionError.value = failure.response?.data?.message || 'Der MC-Testversuch konnte nicht geändert werden.'
        await loadStudentStatuses()
    } finally {
        resetTarget.value = null
        saving.value = false
    }
}

watch([expanded, () => testIds.value.join(','), () => props.revision], ([visible]) => {
    resetTarget.value = null
    if (visible) loadStudentStatuses()
    else clearStudentStatuses()
})
watch(expanded, (visible, _previous, onCleanup) => {
    if (!visible) return
    clockNow.value = Date.now()
    const timer = window.setInterval(() => { clockNow.value = Date.now() }, 1000)
    onCleanup(() => window.clearInterval(timer))
})
watch(busy, value => emit('busy', value), { flush: 'sync' })
onBeforeUnmount(() => {
    clearStudentStatuses()
    emit('busy', false)
})
</script>

<style scoped>
.release-students { margin-block-start: 1rem; border: 1px solid rgba(var(--v-border-color), .16); border-radius: .625rem; }
.release-students summary { display: list-item; align-content: center; min-height: 44px; padding: .45rem .875rem; cursor: pointer; font-weight: 650; }
.release-students summary:focus-visible { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: -2px; border-radius: .625rem; }
.release-students__count { margin-inline-start: .35rem; color: rgba(var(--v-theme-on-surface), .68); font-size: .85em; }
.release-students__hint { margin-block-end: .5rem; color: rgba(var(--v-theme-on-surface), .72); font-size: .8125rem; }
.release-students__body { padding: 0 .875rem .625rem; }
.release-students__list > li, .release-students__test { display: flex; justify-content: space-between; align-items: center; gap: .25rem .5rem; flex-wrap: wrap; }
.release-students__list { margin: 0; padding: 0; list-style: none; }
.release-students__list > li { align-items: flex-start; border-block-start: 1px solid rgba(var(--v-border-color), .13); min-height: 44px; padding-block: .25rem; }
.release-students__tests { flex: 1 1 17rem; min-width: 0; max-width: 100%; padding: 0; margin: 0; list-style: none; }
.release-students__test { justify-content: flex-start; min-height: 38px; }
.release-students__test-name { flex: 0 1 12rem; overflow-wrap: anywhere; font-size: .8125rem; }
.release-students__name { flex: 0 0 100%; min-width: 0; padding-block: .4rem; overflow-wrap: anywhere; font-weight: 550; }
.release-students__actions { display: flex; align-items: center; flex-wrap: wrap; gap: .25rem; }
.release-students__test > .release-students__actions { margin-inline-start: auto; }
.release-students__details { display: flex; flex: 1 1 10rem; align-items: baseline; flex-wrap: wrap; gap: .125rem .5rem; }
.release-students__status { color: rgba(var(--v-theme-on-surface), .85); font-size: .8rem; }
.release-students__detail { color: rgba(var(--v-theme-on-surface), .7); font-size: .8rem; }
.release-students__remaining { font-variant-numeric: tabular-nums; white-space: nowrap; }
.release-students__confirm { padding: .75rem; margin-block-end: .5rem; border-inline-start: 3px solid rgb(var(--v-theme-warning)); background: rgba(var(--v-theme-warning), .08); }
.release-students__confirm p { margin-block: .25rem; }
</style>
