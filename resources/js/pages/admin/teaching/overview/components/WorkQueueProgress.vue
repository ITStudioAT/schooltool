<template>
    <aside v-if="!monitorOnly && (run || mailBatches.length || unavailable)" class="work-queue" aria-label="Auftragsverlauf">
        <details v-if="run" :open="expanded" @toggle="expanded = $event.currentTarget.open">
            <summary>
                <span>{{ run.state === 'completed' ? 'Letzter Auftrag · Leistungsfeststellung' : 'Auftrag zur Leistungsfeststellung' }}</span>
                <strong>{{ stateLabels[run.state] }}</strong>
                <time v-if="run.finished_at" :datetime="run.finished_at"> · {{ assessmentTimeText(run.finished_at) }}</time>
            </summary>
            <WorkCheckProgress v-if="run.progress?.steps?.length" :state="progress" creation />
            <p v-else-if="run.state === 'queued'" class="text-body-2 mt-2" role="status">Der Auftrag wartet auf die Ausführung.</p>
            <p v-if="run.message" class="text-body-2 mt-2">{{ run.message }}</p>
        </details>
        <details v-for="batch in mailBatches" :key="batch.date ?? 'all'" :open="batch.state === 'sending'">
            <summary>
                <span>{{ batch.transport === 'mailpit' ? 'Aufgabenmail im lokalen Test' : 'Aufgabenmail' }}<template v-if="mailBatches.length > 1 && batch.date"> · {{ workDate(batch.date) }}</template></span>
                <strong>{{ batch.withdrawn ? 'Versand gestoppt' : mailStates[batch.state] }}</strong>
            </summary>
            <ul class="work-queue__mail" aria-label="Versandfortschritt">
                <li v-for="(label, state) in recipientStates" v-show="batch.counts[state]" :key="state">{{ label }} · {{ batch.counts[state] }}</li>
            </ul>
        </details>
        <div v-if="unavailable" role="status">
            Der Auftragsstand konnte nicht geladen werden.
            <v-btn size="small" variant="text" @click="restart">Erneut abrufen</v-btn>
        </div>
    </aside>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { show } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingAssessmentRunController'
import { assessmentTimeText } from '@/helpers/workAssessmentTimes'
import { observeAssessmentRun, workAssessmentActivity } from '@/helpers/workAssessmentRun'
import { workDropboxKey } from '@/helpers/workDropbox'
import WorkCheckProgress from './WorkCheckProgress.vue'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, userId: [Number, String], monitorOnly: Boolean })
const emit = defineEmits(['assessment-completed', 'portal-completed'])
const stateLabels = {
    queued: 'In der Warteschlange', running: 'Läuft', completed: 'Abgeschlossen', failed: 'Fehlgeschlagen', interrupted: 'Unterbrochen',
}
const mailStates = { sending: 'Versand läuft', partial: 'Teilweise abgeschlossen', sent: 'Angenommen', uncertain: 'Annahme offen' }
const recipientStates = { draft: 'Vorbereitet', queued: 'In der Warteschlange', sending: 'Wird versendet',
    accepted: 'Vom Versanddienst angenommen', already_sent: 'Bereits früher angenommen', rejected: 'Abgelehnt', uncertain: 'Annahme ungeklärt' }
const workDate = date => date.split('-').reverse().join('.')
const run = ref(null)
const mailBatches = ref([])
const expanded = ref(false)
const unavailable = ref(false)
const progress = computed(() => run.value?.state === 'interrupted'
    ? { ...run.value.progress, state: 'interrupted' } : run.value?.progress)
const key = computed(() => workDropboxKey(props.userId, props.work))
const activity = computed(() => workAssessmentActivity(props.userId, props.work))
const assessmentRunning = computed(() => Boolean(activity.value) || ['queued', 'running'].includes(run.value?.state))
let timer
let controller
let announcedCompletion

function stop() {
    clearTimeout(timer)
    controller?.abort()
    controller = null
}

async function refresh() {
    if (!props.work?.id || document.visibilityState === 'hidden') return
    const request = new AbortController()
    controller = request
    const watchRelease = !props.monitorOnly && props.work.status?.submission_method?.channel === 'portal'
        && !props.work.status.student_distribution?.ended_at
    try {
        const { data } = await axios.get(show.url(props.work.id), {
            params: { progress_only: 1, ...(props.date ? { date: props.date } : {}),
                ...(watchRelease ? { include_portal_completion: 1 } : {}) },
            signal: request.signal, timeout: 8000,
        })
        if (request.signal.aborted) return
        const completed = data?.completed_portal_work
        if (watchRelease && !props.work.status?.student_distribution?.ended_at
            && completed?.status?.student_distribution?.ended_at
            && String(completed.id) === String(props.work.id) && String(completed.teaching_course_id) === String(props.work.teaching_course_id)) {
            emit('portal-completed', completed)
        }
        if (data?.run !== null && !Object.hasOwn(stateLabels, data?.run?.state)) throw new Error()
        if (data.run?.state !== run.value?.state) {
            expanded.value = ['queued', 'running', 'failed', 'interrupted'].includes(data.run?.state)
        }
        run.value = data.run
        const latest = data.activity ?? data.run
        observeAssessmentRun(key.value, latest)
        if (latest?.state === 'completed' && latest.id && latest.id !== announcedCompletion
            && (!latest.date || !props.date || latest.date === props.date)) {
            announcedCompletion = latest.id
            emit('assessment-completed', latest)
        }
        mailBatches.value = (data.mail ?? []).filter(batch => batch && (batch.withdrawn || Object.hasOwn(mailStates, batch.state)))
        unavailable.value = false
    } catch {
        if (!request.signal.aborted) unavailable.value = true
    } finally {
        if (controller === request) {
            controller = null
            const active = assessmentRunning.value || mailBatches.value.some(batch => batch.state === 'sending')
            timer = setTimeout(refresh, active ? (unavailable.value ? 5000 : 2000) : 15000)
        }
    }
}

function restart() {
    stop()
    refresh()
}

watch([() => props.work?.id, () => props.date, () => props.userId], () => {
    run.value = null
    mailBatches.value = []
    unavailable.value = false
    expanded.value = false
    announcedCompletion = null
    restart()
})
watch(() => activity.value?.state, (state) => {
    if (state && state !== 'starting' && !controller) restart()
}, { flush: 'sync' })
defineExpose({ restart })
onMounted(() => {
    document.addEventListener('visibilitychange', restart)
    refresh()
})
onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', restart)
    stop()
})
</script>

<style scoped>
.work-queue { border-top: 1px solid rgba(var(--v-border-color), .16); color: rgba(var(--v-theme-on-surface), .72); font-size: .8125rem; }
.work-queue summary { cursor: pointer; padding: .625rem .25rem; min-block-size: 44px; line-height: 1.5; overflow-wrap: anywhere; }
.work-queue summary strong { margin-inline-start: .5rem; color: rgb(var(--v-theme-on-surface)); }
.work-queue summary:focus-visible { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: 3px; }
.work-queue__mail { padding-inline-start: 1.25rem; font-size: .875rem; }
</style>
