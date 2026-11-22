<template>
    <section class="work-workflow" aria-label="Ablauf der Arbeit">
        <WorkAssessmentSummary :work="work" :course="course" :date="date" :assessment="assessment" :assessment-run="confirmedRun"
            :can-add-test="active && automation && linked && assessment?.status === 'found' && !assessment.deletionPending
                && !work.status?.student_distribution?.published_at"
            :disabled="mutationDisabled" @add-test="exercise?.openMcSelection()" />
        <header class="work-workflow__header">
            <h3>Ablauf der Arbeit</h3>
            <p v-if="lastCheck && !activity" data-testid="work-dialog-last-check">Zuletzt geprüft: {{ lastCheck }}</p>
        </header>
        <v-alert v-if="activity" role="status" type="info" variant="tonal" density="compact" class="mb-3" data-testid="assessment-background-status">
            {{ assessmentActivityLabel(activity) }}
        </v-alert>
        <WorkQueueProgress v-if="active" ref="queueProgress" :work="work" :date="date" :user-id="admin.config?.user?.id"
            @assessment-completed="assessmentCompleted" />
        <WorkDeliveryStatus :work="work" :date="date" :state="assessment" :exercise-ready="exerciseReady"
            :disabled="mutationDisabled" @import="forwardImport" @saved="emit('saved', $event)">
            <template #workflow="{ stages, openStage, release, releasePrepared, releasePaused, releaseAvailable, openRelease, dispatch, legacyMail, openLegacyMail }">
                <div class="work-workflow__phases">
                    <article v-for="phase in phasesFor(stages, { release, releasePrepared, releasePaused }, dispatch)"
                        :key="phase.key" class="work-phase"
                        :class="{ 'work-phase--current': phase.current && phase.primary, 'work-phase--open': phaseOpen(phase) }">
                        <h4 class="work-phase__heading">
                            <button type="button" class="work-phase__toggle" :disabled="phaseLocked(phase)" :aria-expanded="phaseOpen(phase)"
                                :aria-controls="panelId + phase.key" @click="togglePhase(phase)">
                                <span class="work-phase__icon"><v-icon :icon="phase.icon" size="19" aria-hidden="true" /></span>
                                <span class="work-phase__caption">
                                    <span class="work-phase__title">{{ phase.title }}</span>
                                    <span class="work-phase__status">{{ phaseLocked(phase) ? 'Während der Erstellung gesperrt' : phase.status }}</span>
                                </span>
                                <v-icon :icon="phaseLocked(phase) ? 'mdi-lock-outline' : phaseOpen(phase) ? 'mdi-chevron-up' : 'mdi-chevron-down'" size="19" aria-hidden="true" />
                            </button>
                        </h4>
                        <div v-show="phaseOpen(phase)" :id="panelId + phase.key" class="work-phase__body">
                            <template v-if="phase.key === 'preparation'">
                                <div v-if="automation || linked" class="work-phase__item" role="group" aria-label="Leistungsfeststellung">
                                    <div class="work-phase__item-label">
                                        <strong>Leistungsfeststellung</strong>
                                        <span v-if="assessmentStatus !== phase.status">{{ assessmentStatus }}</span>
                                    </div>
                                    <div class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'link'">
                                            <WorkImportActions :work="work" :date="date" link-only :disabled="mutationDisabled" @link="emit('link', $event)" />
                                        </div>
                                        <div class="workflow-management-actions">
                                            <div class="workflow-command">
                                                <WorkFolderStatus v-if="linked" :work="work" :date="date" labelled :disabled="disabled" />
                                            </div>
                                            <div class="workflow-command" :data-primary="phase.primary === 'create'">
                                                <WorkExerciseStatus :work="work" :course="course" :date="date" :state="assessment" labelled
                                                    :can-create="automation && linked && !activity" :disabled="disabled || !linked"
                                                    @create="exercise?.openDialog()" @deleted="exercise?.recheck()" />
                                            </div>
                                        </div>
                                        <div class="workflow-command workflow-command--check" :data-primary="phase.primary === 'check'">
                                            <WorkExerciseExport :ref="trackExercise" :work="work" :course="course" :date="date" :active="active"
                                                :disabled="disabled" :check-progress="checkProgress" passive-status hide-folder-name hide-activator hide-mc-test hide-check-time
                                                @exercise-status="emit('exercise-status', $event)" @progress-checked="emit('progress-checked', $event)"
                                                @assessment-recheck="queueProgress?.restart()" @deadline-saved="emit('deadline-saved', $event)" @check-busy="emit('check-busy', $event)" @busy="emit('busy', $event)" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else-if="phase.key === 'provision'">
                                <div class="work-phase__item" role="group" aria-label="Bereitstellung">
                                    <div class="work-phase__item-label work-phase__item-label--provision">
                                        <strong>Bereitstellung</strong>
                                        <WorkDispatchStatus :provision-summary="phase.provisionSummary" />
                                    </div>
                                    <div class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'provision'">
                                            <v-btn v-if="showPortalAction(release, releasePrepared, releasePaused)" aria-haspopup="dialog"
                                                :prepend-icon="releaseEnded ? 'mdi-lock-outline' : release ? 'mdi-pause' : releasePaused ? 'mdi-pencil-outline' : 'mdi-play'"
                                                :disabled="mutationDisabled || !releaseAvailable" @click="openProvisionDialog(openRelease)">
                                                {{ releaseEnded ? 'Freigabe ansehen' : release ? 'Freigabe verwalten' : releasePaused ? 'Freigabe bearbeiten' : work.can_end_email_release ? 'Freigabe verwalten' : 'Jetzt freigeben' }}
                                            </v-btn>
                                            <WorkProvisionMethod v-if="!releaseEnded" :work="work" :date="date" :disabled="mutationDisabled" :portal-available="releaseAvailable"
                                                :hide-activator="showPortalAction(release, releasePrepared, releasePaused)"
                                                @saved="emit('saved', $event)" @portal="openProvisionDialog(openRelease)"
                                                @mail="openProvisionDialog(openAssignmentMail)" />
                                            <v-btn v-if="!releaseEnded && (release || work.can_end_email_release) && provisionChannel === 'email' && submissionChannel" variant="text"
                                                prepend-icon="mdi-email-outline" :disabled="mutationDisabled"
                                                @click="openProvisionDialog(openAssignmentMail)">Aufgabenmail verwalten</v-btn>
                                        </div>
                                    </div>
                                    <details v-if="hasTaskLogs" class="work-phase__details">
                                        <summary>Bereitstellungsbelege der Arbeit</summary>
                                        <div class="workflow-command"><WorkDispatchLog :work="work" :purposes="['tasks', 'provision']" /></div>
                                    </details>
                                </div>
                            </template>
                            <template v-else-if="phase.key === 'submission'">
                                <div class="work-phase__item" role="group" aria-label="Abgabe">
                                    <div class="work-phase__item-label">
                                        <strong>Abgabe</strong>
                                        <span>{{ submissionStatus }}</span>
                                        <span v-if="portalSubmissionCount">{{ portalSubmissionCount }} Schüler:innen mit Portalabgabe</span>
                                        <span v-else-if="stages[0]?.count || stages[0]?.complete">{{ stepStatus(stages[0]) }}</span>
                                        <span v-if="lastCollection" data-testid="work-dialog-last-collection">Zuletzt eingesammelt: {{ lastCollection }}</span>
                                    </div>
                                    <div class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'submission'">
                                            <WorkSubmissionMethod :work="work" :disabled="mutationDisabled" @saved="emit('saved', $event)" />
                                        </div>
                                        <div class="workflow-command" :data-primary="phase.primary === 'collect'">
                                            <WorkSubmissionCollection v-if="releaseEnded && !stages[0]?.simulation" :work="work" :date="date"
                                                :repeat="Boolean(lastCollection)" :disabled="mutationDisabled" />
                                            <v-btn v-if="stages[0] && !stages[0].blocked && (stages[0].complete || lastCollection || stages[0].simulation)"
                                                min-height="44" :disabled="mutationDisabled" variant="text" :prepend-icon="stages[0].icon"
                                                @click.stop="openStage(stages[0])">Abgabestatus ansehen</v-btn>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <div class="work-phase__item" role="group" aria-label="Bewertung">
                                    <div class="work-phase__item-label">
                                        <strong>Bewertung</strong>
                                        <span data-testid="work-detail-progress">{{ assessmentCount ?? stepStatus(stages[1]) }}</span>
                                        <span v-if="lastEvaluation" data-testid="work-dialog-last-evaluation">Zuletzt bewertet: {{ lastEvaluation }}</span>
                                    </div>
                                    <div class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'manual'">
                                            <v-btn :disabled="mutationDisabled" variant="text" prepend-icon="mdi-pencil-outline" @click.stop="emit('edit-grades')">Bewertung eintragen</v-btn>
                                        </div>
                                        <div class="workflow-command" :data-primary="phase.primary === 'grade'">
                                            <v-btn v-if="stages[1] && !stages[1].blocked" :disabled="mutationDisabled" variant="text"
                                                :prepend-icon="stages[1].icon" @click.stop="openStage(stages[1])">{{ stages[1].complete ? 'Bewertung ansehen' : 'Bewertung verwalten' }}</v-btn>
                                        </div>
                                        <div class="workflow-command">
                                            <v-btn v-if="automation" :disabled="mutationDisabled" variant="text" prepend-icon="mdi-file-import-outline" @click.stop="emit('import', work)">Importieren</v-btn>
                                        </div>
                                        <div class="workflow-command"><WorkEvaluationPdf :work="work" /></div>
                                    </div>
                                </div>
                                <details class="work-phase__details work-phase__details--results" :open="['visibility', 'publish', 'notice'].includes(phase.primary)">
                                    <summary>Ergebnisse freigeben und mitteilen</summary>
                                <div class="work-phase__item" role="group" aria-label="Sichtbarkeit">
                                    <div class="work-phase__item-label">
                                        <strong>Sichtbarkeit</strong>
                                        <span v-if="stages[2]?.complete">Ergebnisse übernommen</span>
                                    </div>
                                    <div class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'visibility'">
                                            <WorkGradeVisibility mode="visibility" :work="work" :disabled="mutationDisabled" @saved="emit('saved', $event)" />
                                        </div>
                                        <div class="workflow-command" :data-primary="phase.primary === 'publish'">
                                            <v-btn v-if="stages[2] && !stages[2].blocked" :disabled="mutationDisabled" variant="text"
                                                :prepend-icon="stages[2].icon" @click.stop="openStage(stages[2])">{{ stages[2].complete ? 'Übernahme ansehen' : stages[2].title }}</v-btn>
                                        </div>
                                    </div>
                                </div>
                                <div class="work-phase__item" role="group" aria-label="Verständigung">
                                    <div class="work-phase__item-label">
                                        <strong>Verständigung</strong>
                                        <WorkDispatchStatus v-if="savedResultDispatch" :work="work" aggregate hide-portal :purposes="['results']" />
                                    </div>
                                    <div class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'notice'">
                                            <WorkGradeVisibility mode="notice" :work="work" :disabled="mutationDisabled" @saved="emit('saved', $event)" />
                                        </div>
                                        <div class="workflow-command">
                                            <v-btn v-if="stages[3] && !stages[3].blocked" :disabled="mutationDisabled" variant="text"
                                                :prepend-icon="stages[3].icon" @click.stop="openStage(stages[3])">{{ stages[3].complete ? 'Verständigung ansehen' : stages[3].title }}</v-btn>
                                        </div>
                                        <div class="workflow-command"><WorkDispatchLog :work="work" :purposes="['results']" /></div>
                                    </div>
                                </div>
                                </details>
                            </template>
                        </div>
                    </article>
                </div>
                <WorkAssignmentMail v-if="!releaseEnded" ref="assignmentMail" :work="work" :date="date" :disabled="mutationDisabled" hide-activator
                    @saved="emit('saved', $event)">
                    <template v-if="legacyMail" #secondary-actions="{ close }">
                        <v-btn variant="text" min-height="44" prepend-icon="mdi-email-outline" :disabled="mutationDisabled"
                            @click="close(); openProvisionDialog(openLegacyMail)">Früheren Versand ansehen</v-btn>
                    </template>
                </WorkAssignmentMail>
            </template>
        </WorkDeliveryStatus>
        <footer class="work-workflow__footer">
            <div v-if="$slots['secondary-actions']" class="workflow-command"><slot name="secondary-actions" /></div>
        </footer>
    </section>
</template>

<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue'
import { assessmentActivityLabel, workAssessmentActivity } from '@/helpers/workAssessmentRun'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workDispatchRecord, workProvisionEvidence } from '@/helpers/workDispatch'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { confirmedExerciseFolder, workFolderNeedsRetry } from '@/helpers/workOverview'
import { useAdminStore } from '@/stores/admin/AdminStore'
import WorkAssignmentMail from './WorkAssignmentMail.vue'
import WorkAssessmentSummary from './WorkAssessmentSummary.vue'
import WorkDeliveryStatus from './WorkDeliveryStatus.vue'
import WorkDispatchLog from './WorkDispatchLog.vue'
import WorkDispatchStatus from './WorkDispatchStatus.vue'
import WorkEvaluationPdf from './WorkEvaluationPdf.vue'
import WorkExerciseExport from './WorkExerciseExport.vue'
import WorkExerciseStatus from './WorkExerciseStatus.vue'
import WorkFolderStatus from './WorkFolderStatus.vue'
import WorkGradeVisibility from './WorkGradeVisibility.vue'
import WorkImportActions from './WorkImportActions.vue'
import WorkProvisionMethod from './WorkProvisionMethod.vue'
import WorkQueueProgress from './WorkQueueProgress.vue'
import WorkSubmissionMethod from './WorkSubmissionMethod.vue'
import WorkSubmissionCollection from './WorkSubmissionCollection.vue'

const props = defineProps({ work: { type: Object, required: true }, course: Object, date: { type: String, default: '' },
    active: { type: Boolean, default: true }, disabled: Boolean, linkError: Boolean,
    assessment: Object, exerciseReady: Boolean, checkProgress: Boolean, assessmentCount: String, allAssessed: Boolean,
    lastCollection: String, lastEvaluation: String, lastCheck: String })
const emit = defineEmits(['link', 'import', 'saved', 'edit-grades', 'exercise-status', 'progress-checked', 'deadline-saved', 'check-busy', 'busy'])
const admin = useAdminStore()
const panelId = `${useId()}-workflow-`
const selectedPhase = ref(null)
const queueProgress = ref(null)
const activity = computed(() => workAssessmentActivity(admin.config?.user?.id, props.work))
const mutationDisabled = computed(() => props.disabled || Boolean(activity.value))
const assignmentMail = ref(null)
const confirmedRun = ref(null)
let exercise = null

function trackExercise(component) {
    exercise = component
}

function assessmentCompleted(run) {
    if (!run.date || !props.date || run.date === props.date) {
        confirmedRun.value = run
        exercise?.refreshStored()
    }
}

function openAssignmentMail() {
    assignmentMail.value?.open()
}

async function openProvisionDialog(open) {
    const workId = props.work.id
    const date = props.date
    await nextTick()
    if (props.work.id === workId && props.date === date && props.active && !mutationDisabled.value) open()
}

watch([() => props.work.id, () => props.date, () => Boolean(activity.value)], () => {
    selectedPhase.value = null
})
watch([() => props.work.id, () => props.date], () => { confirmedRun.value = null })

const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const binding = computed(() => workDropboxStates.get(key.value))
const checked = computed(() => workExerciseCheck(key.value, props.work, props.date))
const linked = computed(() => Boolean(binding.value?.module_link?.folder?.id || binding.value?.folder?.id))
const automation = computed(() => workAutomationEnabled(props.work))
const connectionStatus = computed(() => {
    if (!automation.value) return 'Manuelle Beurteilung'
    if (props.linkError) return 'Verknüpfung konnte nicht geladen werden'
    return binding.value ? 'Kein Lernmodul verknüpft' : 'Verknüpfung wird geladen …'
})
const assessmentStatus = computed(() => {
    if (activity.value) return assessmentActivityLabel(activity.value)
    if (!linked.value) return connectionStatus.value
    if (props.assessment?.deletionPending) return 'Löschung noch nicht bestätigt'
    if (props.exerciseReady) return 'Aufgabenstellung bestätigt'
    if (props.assessment?.status === 'missing' && confirmedExerciseFolder(checked.value)?.id) return 'Prüfungsordner bestätigt'
    return { found: 'Prüfungsordner bestätigt', missing: 'Noch nicht erstellt', unknown: 'Status nicht bestätigt',
        checking: 'Statusprüfung läuft …' }[props.assessment?.status] || 'Noch nicht geprüft'
})
const hasTaskLogs = computed(() => (props.work.status?.dispatch_logs ?? []).some(log => log.sha256
    && ['dispatch_import', 'provision_import'].includes(log.origin) && ['tasks', 'provision'].includes(log.purpose)))
const savedResultDispatch = computed(() => Boolean(workDispatchRecord(props.work, null, 'results', true)))
const releaseEnded = computed(() => Boolean(props.work.status?.student_distribution?.ended_at))
const provisionChannel = computed(() => props.work.status?.provision_method?.channel)
const submissionChannel = computed(() => props.work.status?.submission_method?.channel)
const portalSubmissionCount = computed(() => new Set((props.work.status?.portal_submissions || [])
    .filter(file => file.student_id && !file.withdrawn_at).map(file => file.student_id)).size)
const mailBatch = computed(() => props.work.status?.assignment_mail?.batches?.[props.date || 'all'])
const hasMailReceipt = computed(() => {
    const batch = mailBatch.value
    return batch?.state === 'sent' || (batch?.recipients || []).some(row =>
        ['accepted', 'already_sent'].includes(row.state) && row.accepted_at)
})
const submissionStatus = computed(() => {
    const method = props.work.status?.submission_method
    if (method?.channel === 'portal') return 'Abgabe im Schülerbereich'
    if (method?.channel === 'email') return method.email ? `Abgabe per E-Mail an ${method.email}` : 'Abgabe per E-Mail'
    return 'Abgabeweg noch nicht festgelegt'
})
function showPortalAction(release, releasePrepared, releasePaused) {
    const method = props.work.status?.submission_method
    const submissionChosen = method?.channel === 'portal'
        || (method?.channel === 'email' && typeof method.email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(method.email))
    return Boolean(releaseEnded.value || release || props.work.can_end_email_release
        || ((releasePrepared || releasePaused) && provisionChannel.value === 'portal' && submissionChosen))
}

function nextAction([collection, evaluation, publication], releaseState, previousMailSent) {
    const { release, releasePrepared, releasePaused } = releaseState
    const currentMailSent = mailBatch.value?.transport === 'postmark' && !mailBatch.value.withdrawn_at
        && ['sent', 'partial'].includes(mailBatch.value.state) && hasMailReceipt.value
    const mailed = currentMailSent || previousMailSent
    const collected = portalSubmissionCount.value || collection?.count || collection?.complete || props.lastCollection
    const evaluated = props.allAssessed || evaluation?.complete || evaluation?.count || props.lastEvaluation
    const prepared = props.exerciseReady || props.work.local_task_copy

    if (activity.value) return ['preparation', null]
    if (releasePaused || releasePrepared) return ['provision', 'provision']
    if (!releaseEnded.value && !release && !mailed && !mailBatch.value && !collected && !evaluated && automation.value && !prepared) {
        if (!binding.value) return ['preparation', null]
        if (!linked.value) return ['preparation', 'link']
        if (props.assessment?.status === 'missing') {
            const retryFolder = workFolderNeedsRetry(binding.value, checked.value)
            return ['preparation', retryFolder ? 'link' : 'create']
        }
        if (props.assessment?.status !== 'found') return ['preparation', 'check']
    }

    if (!releaseEnded.value && !release && !mailed && !collected && !evaluated) return ['provision', 'provision']
    if (!releaseEnded.value && !submissionChannel.value && !collected && !evaluated) return ['submission', 'submission']
    if (!evaluated && !collection?.blocked && !collection?.complete && submissionChannel.value !== 'portal') {
        return ['submission', 'collect']
    }
    if (!props.allAssessed && !evaluation?.complete) return ['assessment', automation.value && !evaluation?.blocked ? 'grade' : 'manual']
    if (automation.value && publication && !publication.complete && !publication.blocked) return ['assessment', 'publish']
    if (props.work.status?.grade_visibility?.visible === false) return ['assessment', 'visibility']
    if (!props.work.status?.grade_visibility?.student_notice?.student_ids?.length && !savedResultDispatch.value) return ['assessment', 'notice']
    return ['assessment', null]
}

function phasesFor(stages, releaseState, dispatch) {
    const previousMailSent = ['sent', 'partial'].includes(dispatch.status) && dispatch.confirmed_count > 0 && dispatch.transport !== 'mailpit'
    const [nextPhase, primary] = nextAction(stages, releaseState, previousMailSent)
    const provisionSummary = workProvisionEvidence(props.work, props.date, dispatch)
    const submissionSummary = submissionChannel.value === 'portal' ? 'Portalabgabe'
        : submissionChannel.value === 'email' ? 'Abgabe per E-Mail' : 'Abgabeweg offen'

    return [
        { key: 'preparation', title: 'Vorbereiten', icon: 'mdi-clipboard-text-outline', status: assessmentStatus.value },
        { key: 'provision', title: 'Bereitstellen', icon: 'mdi-send-outline', status: provisionSummary.map(entry => entry.key === 'portal' && entry.state ? entry.state : entry.label).join(' · '), provisionSummary },
        { key: 'submission', title: 'Abgeben', icon: 'mdi-inbox-arrow-down-outline', status: submissionSummary },
        { key: 'assessment', title: 'Bewerten', icon: 'mdi-clipboard-check-outline',
            status: props.assessmentCount ?? stepStatus(stages[1]) },
    ].map(phase => ({ ...phase, current: phase.key === nextPhase, primary: phase.key === nextPhase ? primary : null }))
}

function phaseLocked(phase) {
    return Boolean(activity.value) && phase.key !== 'preparation'
}

function phaseOpen(phase) {
    if (phaseLocked(phase)) return false
    return selectedPhase.value === null ? phase.current : selectedPhase.value === phase.key
}

function togglePhase(phase) {
    if (phaseLocked(phase)) return
    selectedPhase.value = phaseOpen(phase) ? '' : phase.key
}

function forwardImport(...args) {
    if (!mutationDisabled.value) emit('import', ...args)
}

function stepStatus(stage) {
    if (!stage) return 'Status unbekannt'
    if (stage.manuallyCompleted) return 'Manuell erledigt'
    if (stage.total) return `${stage.count} von ${stage.total} mit Nachweis${stage.simulation ? ' · Simulation' : ''}`
    return stage.complete ? 'Abgeschlossen' : 'Noch offen'
}
</script>

<style scoped>
.work-workflow {
    --flow-divider: rgba(var(--v-border-color), .16);
    --flow-muted: rgba(var(--v-theme-on-surface), .72);
    --flow-accent: rgb(var(--v-theme-primary));
    min-inline-size: 0;
}
.work-workflow__header {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: .25rem .75rem;
    margin-block-end: .625rem;
}
.work-workflow__header h3 { font-size: 1rem; font-weight: 700; letter-spacing: -.012em; }
.work-workflow__header p { color: var(--flow-muted); font-size: .75rem; overflow-wrap: anywhere; }
.work-workflow__phases {
    border: 1px solid var(--flow-divider);
    border-radius: .875rem;
    min-inline-size: 0;
}
.work-phase + .work-phase { border-block-start: 1px solid var(--flow-divider); }
.work-phase__heading { margin: 0; }
.work-phase__toggle {
    display: grid;
    grid-template-columns: 2.125rem minmax(0, 1fr) 1.25rem;
    align-items: center;
    gap: .75rem;
    inline-size: 100%;
    min-block-size: 3.625rem;
    padding: .625rem .875rem;
    text-align: start;
    border-radius: .75rem;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
}
.work-phase__toggle:disabled { cursor: default; opacity: .7; }
.work-phase__toggle:not(:disabled):hover { background: rgba(var(--v-theme-on-surface), .04); }
.work-phase--open .work-phase__toggle { border-end-start-radius: 0; border-end-end-radius: 0; }
.work-phase--current .work-phase__toggle { background: rgba(var(--v-theme-primary), .065); }
.work-phase__icon {
    display: grid;
    place-items: center;
    inline-size: 2.125rem;
    block-size: 2.125rem;
    border-radius: .625rem;
    background: rgba(var(--v-theme-on-surface), .055);
    color: var(--flow-muted);
}
.work-phase--current .work-phase__icon { background: var(--flow-accent); color: rgb(var(--v-theme-on-primary)); }
.work-phase__caption { display: grid; min-inline-size: 0; }
.work-phase__title { font-size: .9375rem; font-weight: 700; line-height: 1.25; }
.work-phase__status { color: var(--flow-muted); font-size: .773rem; font-weight: 500; line-height: 1.4; overflow-wrap: anywhere; }
.work-phase__body { padding: .125rem .875rem .625rem; }
.work-phase__item { min-inline-size: 0; padding-block: .625rem .25rem; }
.work-phase__item + .work-phase__item { border-block-start: 1px solid var(--flow-divider); }
.work-phase__item-label {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: .125rem .5rem;
    color: var(--flow-muted);
    font-size: .8125rem;
    overflow-wrap: anywhere;
}
.work-phase__item-label--provision { display: grid; gap: .25rem; }
.work-phase__item-label strong { color: rgb(var(--v-theme-on-surface)); font-weight: 650; }
.work-phase__actions, .workflow-management-actions, .work-workflow__footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0 .375rem;
    min-inline-size: 0;
}
.workflow-management-actions:not(:has(.workflow-command > *)) { display: none; }
.work-phase__actions { margin-block-start: .1875rem; }
.workflow-command { display: inline-flex; min-inline-size: 0; max-inline-size: 100%; }
.workflow-command:empty { display: none; }
.workflow-command[data-primary="true"] { margin-inline-end: .125rem; }
.workflow-command--check > :deep(div) { max-inline-size: 100%; }
.workflow-command :deep(.v-btn) {
    min-block-size: 44px;
    min-inline-size: 0;
    max-inline-size: 100%;
    padding-inline: .5rem;
    border-radius: .5rem;
    background: transparent !important;
    color: var(--flow-accent) !important;
    box-shadow: none !important;
    text-transform: none;
    letter-spacing: normal;
    font-weight: 620;
    font-size: .8125rem;
    text-align: start;
}
.workflow-command :deep(.v-btn__underlay) { opacity: 0; }
.workflow-command :deep(.v-btn__content) { white-space: nowrap; }
.workflow-command[data-primary="true"] > :deep(.v-btn),
.workflow-command[data-primary="true"] > :deep(.work-import-action > .v-btn),
.workflow-command[data-primary="true"] > :deep(.exercise-action .exercise-check-button),
.workflow-command[data-primary="true"] > :deep(.result-actions section > .v-btn:first-of-type),
.workflow-command[data-primary="true"] > :deep(.result-actions .notice-actions .v-btn:first-child) {
    padding-inline: .75rem;
    background: var(--flow-accent) !important;
    color: rgb(var(--v-theme-on-primary)) !important;
}
.work-phase__details { color: var(--flow-muted); font-size: .8125rem; }
.work-phase__details summary { display: list-item; align-content: center; min-block-size: 44px; cursor: pointer; }
.work-phase__details--results { border-block-start: 1px solid var(--flow-divider); margin-block-start: .25rem; }
.work-phase__details--results .work-phase__item { padding-inline-start: .5rem; }
.work-phase__toggle:focus-visible, .work-phase__details summary:focus-visible, .workflow-command :deep(.v-btn:focus-visible) {
    outline: 2px solid var(--flow-accent);
    outline-offset: -2px;
}
.work-workflow__footer { margin-block-start: .25rem; }
@media (max-width: 420px) {
    .work-phase__toggle { padding-inline: .625rem; gap: .5rem; }
    .work-phase__body { padding-inline: .625rem; }
    .workflow-command :deep(.v-btn) { padding-inline: .375rem; font-size: .79rem; }
}
</style>

