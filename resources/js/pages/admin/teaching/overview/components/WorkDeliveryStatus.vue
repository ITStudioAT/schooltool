<template>
    <span v-if="workAutomationEnabled(work) || workHasProgress(work)" class="d-inline-flex flex-wrap align-center ga-1" @click.stop @keydown.stop>
        <v-btn icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="sent ? 'success' : 'error'" :aria-label="sendLabel" :title="sendLabel" @click.stop="opened = 'send'">
            <v-icon :size="size">{{ sent ? 'mdi-email-check-outline' : 'mdi-email-arrow-right-outline' }}</v-icon>
        </v-btn>
        <v-btn v-for="stage in stages" :key="stage.key" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="stage.color"
            :disabled="stage.blocked" :aria-label="stageLabel(stage)" :title="stageLabel(stage)" :data-workflow-stage="stage.key" :data-workflow-state="stage.state" @click.stop="openStage(stage)">
            <v-icon :size="size">{{ stage.icon }}</v-icon>
        </v-btn>
    </span>
    <v-dialog v-model="dialog" max-width="640" scrollable>
        <v-card>
            <v-card-title class="text-wrap">{{ opened === 'send' ? 'E-Mail-Versand' : selectedStage?.title }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <v-alert :type="opened === 'send' ? (sent ? 'success' : 'warning') : (selectedStage?.complete ? 'success' : 'info')" variant="tonal">{{ opened === 'send' ? sendLabel : selectedStage?.label }}</v-alert>
                <template v-if="opened === 'send'">
                    <p v-if="sent" class="mt-3">{{ dispatch.confirmed_count }} von {{ dispatch.expected_count }} Nachrichten bestätigt.</p>
                    <p v-if="dispatch.sent_at" class="mt-2">Versandt: {{ assessmentTimeText(dispatch.sent_at) }}</p>
                    <p v-if="sent && dispatch.transport === 'mailpit'" class="text-orange-darken-3 mt-2">Testversand über Mailpit. Die Nachrichten liegen ausschließlich im lokalen Mailpit.</p>
                    <p v-if="dispatch.last_protocol?.run" class="text-caption mt-2">Versandlauf: {{ dispatch.last_protocol.run }}</p>
                    <p class="mt-3">{{ sent || workHasProgress(work) ? nextActionText : 'Vorbereitete E-Mails versenden und anschließend „Erneut prüfen“ wählen.' }}</p>
                </template>
                <template v-else>
                    <p class="mt-3">{{ selectedStage?.count }} von {{ selectedStage?.total }} Personen mit gespeichertem Nachweis.</p>
                    <p class="mt-2">{{ selectedStage?.detail }}</p>
                    <p v-if="opened === 'notification' && selectedStage?.completedAt" class="mt-3"><strong>Abgeschlossen am:</strong> {{ assessmentTimeText(selectedStage.completedAt) }}</p>
                    <v-btn v-if="opened === 'notification' && selectedStage?.simulation" class="mt-2" variant="text" href="http://127.0.0.1:8025" target="_blank" rel="noopener noreferrer">Simulator-Mailpit öffnen</v-btn>
                    <table v-if="opened === 'evaluation' && simulationEvaluation" class="simulation-evaluation mt-3" aria-label="Testbewertungen">
                        <thead><tr><th>Person</th><th>Testbewertung</th></tr></thead>
                        <tbody><tr v-for="row in sortedSimulationRows" :key="row.student_id"><td>{{ row.last_name ? `${row.last_name}, ${row.first_name}` : row.person }}</td><td>{{ row.result }}</td></tr></tbody>
                    </table>
                    <template v-if="opened === 'collection'">
                        <p v-if="selectedStage?.simulation" class="mt-3">Letztes Einsammeln im Simulator: {{ selectedStage.lastCollectedAt ? assessmentTimeText(selectedStage.lastCollectedAt) : 'Noch nicht eingesammelt.' }}</p>
                        <p v-else class="mt-3">Letztes belegtes Einsammeln: {{ lastCollectionAt ? assessmentTimeText(lastCollectionAt) : 'Zeitpunkt nicht gespeichert.' }}</p>
                        <p v-if="!selectedStage?.simulation && work.collection_run?.changes_detected === null" class="text-caption mt-2">Der ältere Einsammelbeleg enthält keinen auswertbaren Änderungsnachweis. Bisherige Bewertungsbelege bleiben erhalten.</p>
                    </template>
                    <v-alert v-if="selectedStage?.review" type="warning" variant="tonal" class="mt-3">Versandzeitpunkt und Bewertungsstand weichen ab. Vor einer weiteren Nachricht den bestätigten Stand abgleichen.</v-alert>
                    <p class="mt-2">{{ nextActionText }}</p>
                    <WorkEvaluationPdf v-if="opened === 'publication' || opened === 'evaluation'" :work="work" class="mt-3" />
                    <p v-if="dispatch.transport === 'mailpit'" class="text-caption text-orange-darken-3 mt-2">Lokaler Entwicklungsdurchlauf nach Testversand über Mailpit.</p>
                </template>
            </v-card-text>
            <v-card-actions class="d-flex flex-wrap ga-2" aria-label="Dialogaktionen">
                <WorkSubmissionCollection v-if="opened === 'collection' && !selectedStage?.simulation" :work="work" :date="date" :repeat="selectedStage?.complete || Boolean(lastCollectionAt)" />
                <v-btn v-if="opened === 'collection' && selectedStage?.simulation" variant="tonal" prepend-icon="mdi-flask-outline" to="/admin/teaching-tools/simulator">Simulator öffnen</v-btn>
                <WorkGradingAction v-if="opened === 'evaluation'" :work="work" :date="date" :repeat="selectedStage?.complete"
                    :disabled="!stages[0]?.complete && !lastCollectionAt" />
                <v-btn v-if="opened === 'publication' && !selectedStage?.complete" variant="tonal" prepend-icon="mdi-account-check-outline" @click="emit('import', work, false, { date }); opened = null">Ergebnisse übernehmen</v-btn>
                <WorkResultNotification v-if="opened === 'notification'" :work="work" :date="date" @saved="emit('saved', $event)" />
                <v-spacer />
                <v-btn @click="opened = null">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { sortedPeople } from '@/helpers/personNames'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { assessmentTimeText } from '@/helpers/workAssessmentTimes'
import { workDispatchNotifications } from '@/helpers/workDispatch'
import { workHasProgress, workProgressStages, workProgressNextAction, workLastCollectionAt, workSimulationEvaluation } from '@/helpers/workProgress'
import WorkEvaluationPdf from './WorkEvaluationPdf.vue'
import WorkSubmissionCollection from './WorkSubmissionCollection.vue'
import WorkGradingAction from './WorkGradingAction.vue'
import WorkResultNotification from './WorkResultNotification.vue'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, state: { type: Object, default: null }, size: { type: [Number, String], default: 18 } })
const admin = useAdminStore()
const emit = defineEmits(['import', 'saved'])
const stages = computed(() => workProgressStages(props.work))
const stageLabel = stage => stage.blocked ? `${stage.title}: zuerst „${stage.blockedBy}“ abschließen`
    : stage.completedAt ? `${stage.label} · ${assessmentTimeText(stage.completedAt)}` : stage.label
const openStage = stage => { if (!stage.blocked) opened.value = stage.key }
const lastCollectionAt = computed(() => workLastCollectionAt(props.work))
const simulationEvaluation = computed(() => workSimulationEvaluation(props.work))
const sortedSimulationRows = computed(() => sortedPeople(simulationEvaluation.value?.rows))
const selectedStage = computed(() => stages.value.find(stage => stage.key === opened.value))
const nextAction = computed(() => workProgressNextAction(props.work) || 'Abgaben einsammeln')
const nextActionText = computed(() => nextAction.value === 'Abgeschlossen' ? 'Status: ABGESCHLOSSEN.' : `Nächster Schritt: ${nextAction.value}.`)
const opened = ref(null)
const dialog = computed({ get: () => Boolean(opened.value), set: value => { if (!value) opened.value = null } })
watch(stages, value => { if (value.find(stage => stage.key === opened.value)?.blocked) opened.value = null })
const dispatch = computed(() => {
    if (props.state?.status === 'found' && props.state.userId === admin.config?.user?.id
        && String(props.state.workId) === String(props.work.id) && String(props.state.courseId) === String(props.work.teaching_course_id)
        && props.state.date === props.date && props.state.emailDispatch) return props.state.emailDispatch
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const state = workDropboxStates.get(key)
    const date = props.date || props.work.date_for_all_groups || ''
    const cached = workExerciseCheck(key, props.work, date)?.data
    const checked = cached?.exercise?.email_dispatch ? cached : state
    if (checked?.exercise?.status === 'found' && (!date || checked.exercise.folder?.name?.startsWith(`${date}_`))) return checked.exercise.email_dispatch || {}
    const notifications = workDispatchNotifications(props.work, 'tasks')
    const expected = new Set((props.work.groups || []).flatMap(group => group.student_ids || [])).size
    return { status: expected > 0 && new Set(notifications.map(item => item.student_id)).size === expected ? 'sent' : 'missing',
        confirmed_count: notifications.length, expected_count: expected, sent_at: notifications[0]?.sent_at, transport: 'office_outlook' }
})
const sent = computed(() => dispatch.value.status === 'sent')
const sendLabel = computed(() => sent.value ? `E-Mails versandt${dispatch.value.transport === 'mailpit' ? ' · Testversand über Mailpit' : ''}`
    : dispatch.value.status === 'partial' ? `E-Mail-Versand unvollständig: ${dispatch.value.confirmed_count} bestätigt`
        : dispatch.value.status === 'unknown' ? 'E-Mail-Versand nicht bestätigt – erneut prüfen' : 'E-Mails noch nicht versandt')
</script>

<style scoped>
.simulation-evaluation { width: 100%; border-collapse: collapse; font-size: .875rem; text-align: left; }
.simulation-evaluation th, .simulation-evaluation td { padding: 8px 10px; border-bottom: 1px solid rgba(var(--v-theme-on-surface), .15); }
</style>
