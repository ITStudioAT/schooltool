<template>
    <section class="work-workflow" aria-label="Ablauf der Arbeit">
        <WorkAssessmentSummary v-if="!openingStatusPending" :work="work" :course="course" :date="date" :assessment="assessment" :assessment-run="confirmedRun"
            :compact="compactAssessment"
            :can-add-test="active && automation && linked && assessment?.status === 'found' && !assessment.deletionPending
                && !work.status?.student_distribution?.published_at"
            :disabled="mutationDisabled" @add-test="exercise?.openMcSelection()" />
        <header class="work-workflow__header">
            <h3>Ablauf der Arbeit</h3>
            <div v-if="active" class="work-workflow__check" :data-primary="nextAction?.action === 'check'">
                <v-btn color="primary" variant="tonal" prepend-icon="mdi-refresh" min-height="44" :loading="checkingOverall"
                    :disabled="checkingOverall || (disabled && !activity)" @click="checkOverall">Gesamtstatus prüfen</v-btn>
            </div>
            <p v-if="(overallCheckedAt || lastCheck) && !activity" data-testid="work-dialog-last-check">Zuletzt geprüft: {{ overallCheckedAt || lastCheck }}</p>
        </header>
        <WorkCheckProgress :state="overallProgress" overall />
        <p v-if="overallMessage" :role="overallError ? 'alert' : 'status'" class="work-workflow__result" :class="{ 'text-error': overallError }">
            {{ overallMessage }}
        </p>
        <v-alert v-if="activity" role="status" type="info" variant="tonal" density="compact" class="mb-3" data-testid="assessment-background-status">
            {{ assessmentActivityLabel(activity) }}
        </v-alert>
        <WorkQueueProgress v-if="active" ref="queueProgress" :work="work" :date="date" :user-id="admin.config?.user?.id"
            @assessment-completed="assessmentCompleted" @portal-completed="emit('saved', $event)" />
        <WorkDeliveryStatus :work="work" :date="date" :state="assessment" :exercise-ready="exerciseReady" :workflow-progress="workflowProgress"
            :disabled="provisionDisabled" :workflow-assessment-locked="Boolean(phaseLockReason('assessment'))"
            @import="forwardImport" @saved="emit('saved', $event)" @portal-target="openPortalTarget">
            <template #workflow="{ stages, openStage, release, releasePrepared, releasePaused, releaseAvailable, openRelease, dispatch, legacyMail, openLegacyMail }">
                <div v-if="openingStatusPending" class="work-workflow__phases work-workflow__loading text-body-2" role="status">
                    Arbeitsstatus wird geladen …
                </div>
                <div v-show="!openingStatusPending" class="work-workflow__phases">
                    <article v-for="phase in phasesFor(stages, dispatch)"
                        :key="phase.key" class="work-phase"
                        :class="{ 'work-phase--current': phase.current && (phase.primary || phase.nextLabel === 'Abgeben'),
                            'work-phase--complete': phase.complete, 'work-phase--locked': phase.locked,
                            'work-phase--next': phase.next, 'work-phase--next-assessment': phase.nextLabel === 'Bewerten',
                            'work-phase--next-copy': phase.portalNext }">
                        <div class="work-phase__heading-row" :class="{ 'work-phase__heading-row--submission': phase.key === 'submission' }">
                            <h4 class="work-phase__heading work-phase__header">
                                <span class="work-phase__icon" :class="{ 'work-phase__icon--closed': phase.closed && !phase.locked,
                                    'text-success': phase.complete && !phase.closed && !phase.locked }">
                                    <v-icon :icon="phase.displayIcon" size="19" aria-hidden="true" />
                                </span>
                                <span class="work-phase__caption">
                                    <span class="work-phase__title" :class="{ 'text-success': phase.complete }">{{ phase.title }}</span>
                                    <span v-if="phase.key === 'provision' && !phase.locked" class="work-phase__status">{{ phase.status }}</span>
                                    <span v-else-if="phase.complete && phase.key === 'assessment'" class="work-phase__status text-success font-weight-bold">Erledigt</span>
                                    <span v-if="phase.next" class="work-phase__status text-orange-darken-2 font-weight-bold">
                                        Nächster Schritt<span v-if="phase.nextLabel && phase.nextLabel !== phase.title && phase.primary !== 'copy-portal'"> · {{ phase.nextLabel }}</span>
                                    </span>
                                    <span v-if="phase.key === 'preparation' && confirmedPreparationFolder"
                                        :key="confirmedPreparationFolder.id + ':' + date" tabindex="0" class="work-phase__status work-phase__folder-name"
                                        @mouseenter="loadPreparationPath" @focus="loadPreparationPath">
                                        {{ confirmedPreparationFolder.name }}
                                        <v-tooltip activator="parent" location="bottom" open-on-focus :max-width="420" content-class="work-preparation-path-tooltip"
                                            :text="preparationPath || (preparationPathFailed ? 'Dropbox-Pfad konnte nicht geladen werden.' : 'Dropbox-Pfad wird geladen …')" />
                                    </span>
                                    <span v-else-if="phase.key !== 'provision' && phase.status && (!phase.locked || phase.closed)" class="work-phase__status">
                                        {{ phase.status }}
                                    </span>
                                    <span v-if="phase.locked" class="work-phase__status">{{ phase.lockReason }}</span>
                                    <span v-if="phase.key === 'preparation' && confirmedPreparationFolder && !phase.complete && phase.status !== 'Prüfungsordner bestätigt'"
                                        class="work-phase__status">{{ phase.status }}</span>
                                </span>
                            </h4>
                            <div v-if="phase.key === 'preparation' && (automation || linked)"
                                class="work-phase__heading-action work-phase__heading-action--preparation">
                                <WorkFolderStatus v-if="linked && !directExerciseFolder" :work="work" :date="date" icon-only :disabled="disabled" />
                                <WorkExerciseStatus :work="work" :course="course" :date="date" :state="assessment" action="manage"
                                    :direct-portal-folder="Boolean(directExerciseFolder)"
                                    :disabled="disabled || !linked" @deleted="exercise?.recheck()" />
                            </div>
                            <div v-else-if="phase.key === 'provision' && releaseEnded && !phase.locked" class="work-phase__heading-action">
                                <v-btn icon width="40" height="40" variant="text" aria-label="Freigabe ansehen" aria-haspopup="dialog"
                                    :disabled="mutationDisabled || !releaseAvailable" @click.stop="openProvisionDialog(openRelease)">
                                    <v-icon icon="mdi-cog-outline" size="20" aria-hidden="true" />
                                    <v-tooltip activator="parent" location="top" open-on-focus text="Freigabe ansehen" />
                                </v-btn>
                            </div>
                            <div v-else-if="phase.key === 'submission' && !phase.locked" class="work-phase__heading-action work-phase__heading-action--submission"
                                :data-primary="phase.primary === 'submission'">
                                <div v-if="!portalTargetPending && !stages[0]?.simulation" class="workflow-command work-phase__copy-action"
                                    :data-primary="phase.primary === 'copy-portal'">
                                    <WorkPortalSubmissionCopy :work="work" :date="date"
                                        :closed="workflowProgress.portalSubmissionClosed" :recommended="phase.primary === 'copy-portal'" :disabled="mutationDisabled"
                                        @saved="emit('saved', $event)" />
                                </div>
                                <WorkSubmissionReopening v-if="portalClosureNotice" :work="work" :date="date" :now="now"
                                    :disabled="mutationDisabled" @saved="emit('saved', $event)" />
                                <WorkSubmissionMethod :work="work" :date="date" :portal-closure-notice="portalClosureNotice"
                                    :disabled="mutationDisabled" @saved="emit('saved', $event)" />
                            </div>
                            <div v-else-if="phase.key === 'assessment' && !phase.locked && stages[1]"
                                class="work-phase__heading-action work-phase__heading-action--assessment"
                                :data-primary="phase.primary === 'manual'">
                                <v-btn icon width="40" height="40" variant="text" :aria-label="stages[1].complete ? 'Bewertung ansehen' : 'Bewertung verwalten'"
                                    aria-haspopup="dialog" :disabled="mutationDisabled" @click.stop="openStage(stages[1])">
                                    <v-icon icon="mdi-cog-outline" size="20" aria-hidden="true" />
                                    <v-tooltip activator="parent" location="top" open-on-focus :text="stages[1].complete ? 'Bewertung ansehen' : 'Bewertung verwalten'" />
                                </v-btn>
                            </div>
                        </div>
                        <div v-if="phase.key !== 'provision' || phase.provisionDetails.length || hasTaskLogs || (!phase.locked && !releaseEnded)"
                            class="work-phase__body"
                            :class="{ 'work-phase__body--preparation': phase.key === 'preparation',
                                'work-phase__body--closed-portal': phase.key === 'submission' && workflowProgress.portalSubmissionClosed }">
                            <template v-if="phase.key === 'preparation'">
                                <div v-show="automation || linked" class="work-phase__item" role="group" :aria-label="directExerciseFolder ? 'Dropbox-Prüfungsordner' : 'Leistungsfeststellung'">
                                    <div class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'link'">
                                            <WorkImportActions :work="work" :date="date" link-only :disabled="mutationDisabled" @link="emit('link', $event)" />
                                        </div>
                                        <div class="workflow-command" :data-primary="phase.primary === 'create'">
                                            <WorkExerciseStatus :work="work" :course="course" :date="date" :state="assessment" action="create"
                                                :can-create="automation && linked && !activity && !directExerciseFolder" :disabled="disabled || !linked"
                                                @create="exercise?.openDialog()" />
                                        </div>
                                        <div class="workflow-command workflow-command--check">
                                            <WorkExerciseExport :ref="trackExercise" :work="work" :course="course" :date="date" :active="active"
                                                :disabled="disabled" :check-progress="checkProgress" passive-status hide-folder-name hide-activator hide-mc-test hide-check-time hide-status-check
                                                @exercise-status="emit('exercise-status', $event)" @progress-checked="emit('progress-checked', $event)"
                                                @assessment-recheck="queueProgress?.restart()" @deadline-saved="emit('deadline-saved', $event)" @check-busy="emit('check-busy', $event)" @busy="emit('busy', $event)" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else-if="phase.key === 'provision'">
                                <div class="work-phase__item" role="group" aria-label="Bereitstellung">
                                    <div v-if="phase.provisionDetails.length" class="work-phase__item-label work-phase__item-label--provision">
                                        <WorkDispatchStatus :provision-summary="phase.provisionDetails" />
                                    </div>
                                    <div v-if="!phase.locked && !releaseEnded" class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'provision'">
                                            <v-btn v-if="showPortalAction(release, releasePrepared, releasePaused)" aria-haspopup="dialog"
                                                :prepend-icon="release ? 'mdi-pause' : releasePaused ? 'mdi-pencil-outline' : 'mdi-play'"
                                                :disabled="mutationDisabled || !releaseAvailable" @click="openProvisionDialog(openRelease)">
                                                {{ release ? 'Freigabe verwalten' : releasePaused ? 'Freigabe bearbeiten' : work.can_end_email_release ? 'Freigabe verwalten' : 'Jetzt freigeben' }}
                                            </v-btn>
                                            <WorkProvisionMethod :work="work" :date="date" :disabled="mutationDisabled" :portal-available="releaseAvailable"
                                                :hide-activator="showPortalAction(release, releasePrepared, releasePaused)"
                                                @saved="emit('saved', $event)" @portal="openProvisionDialog(openRelease)"
                                                @mail="openProvisionDialog(openAssignmentMail)" />
                                            <v-btn v-if="(release || work.can_end_email_release) && provisionChannel === 'email' && submissionChannel" variant="text"
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
                                        <template v-if="!workflowProgress.portalSubmissionClosed">
                                            <strong>Abgabe</strong>
                                            <span>{{ submissionStatus }}</span>
                                            <span v-if="portalSubmissionCount">{{ portalSubmissionCount }} Schüler:innen mit Portalabgabe</span>
                                            <span v-else-if="stages[0]?.count || stages[0]?.complete">{{ stepStatus(stages[0]) }}</span>
                                        </template>
                                        <span v-if="reopenedCount">Upload für {{ reopenedCount }} wieder geöffnet</span>
                                        <span v-if="workflowProgress.portalCopyPending && !releaseEnded">Die Bereitstellung muss vor dem Kopieren endgültig beendet sein.</span>
                                        <span v-else-if="phase.primary === 'link-portal-target'">Für diesen Termin fehlt der zugeordnete Dropbox-Prüfungsordner.</span>
                                        <span v-else-if="phase.primary === 'check-portal-target'">Der gespeicherte Prüfungsordner ist für diesen Termin noch nicht bestätigt.</span>
                                        <span v-if="lastCollection" data-testid="work-dialog-last-collection">Zuletzt eingesammelt: {{ lastCollection }}</span>
                                    </div>
                                    <div v-if="!phase.locked" class="work-phase__actions">
                                        <div class="workflow-command" :data-primary="phase.primary === 'collect'">
                                            <WorkSubmissionCollection v-if="!workflowProgress.submissionComplete && releaseEnded && !stages[0]?.simulation" :work="work" :date="date"
                                                :repeat="Boolean(lastCollection)" :disabled="mutationDisabled" hide-portal-action />
                                        </div>
                                        <div v-if="portalTargetPending" class="workflow-command" data-primary="true">
                                            <v-btn min-height="48" variant="tonal" prepend-icon="mdi-folder-link-outline"
                                                :disabled="mutationDisabled" @click.stop="openPortalTarget">{{ phase.nextLabel }}</v-btn>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <div class="work-phase__item" role="group" aria-label="Bewertung">
                                    <div class="work-phase__item-label">
                                        <strong>Bewertung</strong>
                                        <span data-testid="work-detail-progress">{{ assessmentProgress ?? stepStatus(stages[1]) }}</span>
                                        <span v-if="lastEvaluation" data-testid="work-dialog-last-evaluation">Zuletzt bewertet: {{ lastEvaluation }}</span>
                                    </div>
                                    <div class="work-phase__actions">
                                        <div v-if="!phase.locked && automation" class="workflow-command" :data-primary="phase.primary === 'grade' && canCopyGradingContext(stages)">
                                            <WorkGradingAction :work="work" :date="date" :repeat="stages[1]?.complete && !stages[1]?.manuallyCompleted"
                                                :disabled="mutationDisabled || !canCopyGradingContext(stages)" copy-on-open />
                                        </div>
                                        <div v-if="!phase.locked && automation" class="workflow-command">
                                            <v-btn :disabled="mutationDisabled" variant="text" prepend-icon="mdi-file-import-outline"
                                                @click.stop="forwardImport(work, false, { date, automatic: true })">Importieren</v-btn>
                                        </div>
                                        <div class="workflow-command"><WorkEvaluationPdf :work="work" /></div>
                                    </div>
                                </div>
                                <details v-if="!phase.locked || hasResultLogs || savedResultDispatch" class="work-phase__details work-phase__details--results" :open="['visibility', 'publish', 'notice'].includes(phase.primary)">
                                    <summary>{{ phase.locked ? 'Versandbelege der Ergebnisse' : 'Ergebnisse freigeben und mitteilen' }}</summary>
                                <div v-if="!phase.locked" class="work-phase__item" role="group" aria-label="Sichtbarkeit">
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
                                        <div v-if="!phase.locked" class="workflow-command" :data-primary="phase.primary === 'notice'">
                                            <WorkGradeVisibility mode="notice" :work="work" :disabled="mutationDisabled" @saved="emit('saved', $event)" />
                                        </div>
                                        <div v-if="!phase.locked" class="workflow-command">
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
                <WorkAssignmentMail v-if="!releaseEnded && !phaseLockReason('provision')" ref="assignmentMail" :work="work" :date="date" :disabled="mutationDisabled" hide-activator
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
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { folders, show } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { assessmentActivityLabel, workAssessmentActivity } from '@/helpers/workAssessmentRun'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workDispatchRecord, workProvisionEvidence, workStudentDistribution } from '@/helpers/workDispatch'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { confirmedExerciseFolder, workFolderNeedsRetry } from '@/helpers/workOverview'
import { workWorkflowProgress } from '@/helpers/workProgress'
import { useAdminStore } from '@/stores/admin/AdminStore'
import WorkAssignmentMail from './WorkAssignmentMail.vue'
import WorkAssessmentSummary from './WorkAssessmentSummary.vue'
import WorkCheckProgress from './WorkCheckProgress.vue'
import WorkDeliveryStatus from './WorkDeliveryStatus.vue'
import WorkDispatchLog from './WorkDispatchLog.vue'
import WorkDispatchStatus from './WorkDispatchStatus.vue'
import WorkEvaluationPdf from './WorkEvaluationPdf.vue'
import WorkExerciseExport from './WorkExerciseExport.vue'
import WorkExerciseStatus from './WorkExerciseStatus.vue'
import WorkFolderStatus from './WorkFolderStatus.vue'
import WorkGradeVisibility from './WorkGradeVisibility.vue'
import WorkGradingAction from './WorkGradingAction.vue'
import WorkImportActions from './WorkImportActions.vue'
import WorkProvisionMethod from './WorkProvisionMethod.vue'
import WorkQueueProgress from './WorkQueueProgress.vue'
import WorkSubmissionMethod from './WorkSubmissionMethod.vue'
import WorkSubmissionCollection from './WorkSubmissionCollection.vue'
import WorkPortalSubmissionCopy from './WorkPortalSubmissionCopy.vue'
import WorkSubmissionReopening from './WorkSubmissionReopening.vue'
import { createWorkCheckStream, useWorkCheckProgress } from './workCheckProgress'

const props = defineProps({ work: { type: Object, required: true }, course: Object, date: { type: String, default: '' },
    now: Number, active: { type: Boolean, default: true }, disabled: Boolean, linkError: Boolean,
    assessment: Object, exerciseReady: Boolean, checkProgress: Boolean, assessmentCount: String, compactAssessment: Boolean,
    lastCollection: String, lastEvaluation: String, lastCheck: String })
const emit = defineEmits(['link', 'import', 'saved', 'exercise-status', 'progress-checked', 'deadline-saved', 'check-busy', 'busy'])
const admin = useAdminStore()
const queueProgress = ref(null)
const activity = computed(() => workAssessmentActivity(admin.config?.user?.id, props.work))
const mutationDisabled = computed(() => props.disabled || Boolean(activity.value))
const assignmentMail = ref(null)
const confirmedRun = ref(null)
const checkingOverall = ref(false)
const overallCheckedAt = ref('')
const overallMessage = ref('')
const overallError = ref(false)
const { state: overallProgress, start: startOverallProgress, update: updateOverallProgress,
    finish: finishOverallProgress, reset: resetOverallProgress } = useWorkCheckProgress()
let overallRequest = null
let exercise = null

function trackExercise(component) {
    exercise = component
}

async function checkOverall() {
    if (!props.active || checkingOverall.value || (props.disabled && !activity.value)) return
    if (activity.value) {
        queueProgress.value?.restart()
        return
    }
    const progressId = crypto.randomUUID()
    const operation = startOverallProgress(props.work.id, progressId, [], true)
    const request = new AbortController()
    overallRequest = request
    const stream = createWorkCheckStream(progress => updateOverallProgress(operation, progress))
    stream.options.signal = request.signal
    checkingOverall.value = true
    overallMessage.value = ''
    overallError.value = false
    try {
        const data = await exercise?.checkOverall({ progressId, stream })
        if (request !== overallRequest) return
        const saved = data?.saved_work
        if (String(saved?.id) !== String(props.work.id) || String(saved?.teaching_course_id) !== String(props.work.teaching_course_id)) {
            throw new Error('Der Arbeitsstatus konnte nicht bestätigt werden.')
        }
        finishOverallProgress(operation, data.progress || null)
        emit('saved', saved)
        const checkedAt = Date.parse(data.workflow_checked_at)
        if (Number.isFinite(checkedAt)) {
            overallCheckedAt.value = new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna', dateStyle: 'short', timeStyle: 'short' }).format(checkedAt)
        }
        const portal = data.portal_reconciliation
        if (portal?.total) {
            overallMessage.value = [`${portal.confirmed} von ${portal.total} Portaldateien in Dropbox bestätigt`,
                portal.missing === 1 ? '1 fehlt' : portal.missing && `${portal.missing} fehlen`,
                portal.different === 1 ? '1 weicht ab' : portal.different && `${portal.different} weichen ab`,
                portal.unverifiable && `${portal.unverifiable} nicht prüfbar`].filter(Boolean).join(' · ') + '.'
        }
        const message = portal?.message || data.collection_error?.message
            || (data.exercise?.status === 'unknown' && data.exercise?.message)
        overallError.value = Boolean(message)
        if (message) overallMessage.value = [overallMessage.value, message].filter(Boolean).join(' ')
    } catch (failure) {
        if (request !== overallRequest) return
        finishOverallProgress(operation, { ...overallProgress.value, state: 'failed' }, true)
        overallError.value = true
        overallMessage.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || failure.message || 'Der Arbeitsstatus konnte nicht geprüft werden. Bitte erneut versuchen.'
    } finally {
        if (request === overallRequest) {
            overallRequest = null
            checkingOverall.value = false
        }
    }
}

function resetOverallCheck() {
    overallRequest?.abort()
    overallRequest = null
    resetOverallProgress()
    checkingOverall.value = false
    overallCheckedAt.value = ''
    overallMessage.value = ''
    overallError.value = false
}

watch([() => props.work.id, () => props.course?.id, () => props.date, () => props.active,
    () => admin.config?.user?.id], resetOverallCheck, { flush: 'sync' })
onBeforeUnmount(resetOverallCheck)

function openPortalTarget() {
    const date = workflowProgress.value.portalCopyDate
    if (!date || mutationDisabled.value || phaseLockReason('submission')) return
    emit('link', props.work, { portalDate: date })
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
    if (props.work.id === workId && props.date === date && props.active && !provisionDisabled.value) open()
}

watch([() => props.work.id, () => props.date], () => { confirmedRun.value = null })

const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const binding = computed(() => workDropboxStates.get(key.value))
const checked = computed(() => workExerciseCheck(key.value, props.work, props.date))
const linked = computed(() => Boolean(binding.value?.module_link?.folder?.id || binding.value?.folder?.id))
const automation = computed(() => workAutomationEnabled(props.work))
const openingStatusPending = computed(() => {
    if (workflowProgress.value.portalCopyPending) return false
    if (!props.active || !automation.value || !key.value
        || String(props.course?.id) !== String(props.work.teaching_course_id)) return false
    return props.assessment ? props.assessment.initialCheckPending === true : !checked.value
})
const connectionStatus = computed(() => {
    if (!automation.value) return 'Manuelle Beurteilung'
    if (props.linkError || (!binding.value && props.assessment?.status === 'unknown')) return 'Verknüpfung konnte nicht geladen werden'
    return binding.value ? 'Kein Lernmodul verknüpft' : 'Verknüpfung wird geladen …'
})
const assessmentStatus = computed(() => {
    if (activity.value) return assessmentActivityLabel(activity.value)
    if (!linked.value) return connectionStatus.value
    if (props.assessment?.deletionPending) return 'Löschung noch nicht bestätigt'
    if (props.assessment?.status === 'missing' && confirmedExerciseFolder(checked.value)?.id) return 'Prüfungsordner bestätigt'
    return { found: 'Prüfungsordner bestätigt', missing: 'Noch nicht erstellt', unknown: 'Status nicht bestätigt',
        checking: 'Statusprüfung läuft …' }[props.assessment?.status] || 'Noch nicht geprüft'
})
const hasTaskLogs = computed(() => (props.work.status?.dispatch_logs ?? []).some(log => log.sha256
    && ['dispatch_import', 'provision_import'].includes(log.origin) && ['tasks', 'provision'].includes(log.purpose)))
const hasResultLogs = computed(() => (props.work.status?.dispatch_logs ?? []).some(log => log.sha256
    && ['dispatch_import', 'provision_import'].includes(log.origin) && (log.purpose || 'results') === 'results'))
const savedResultDispatch = computed(() => Boolean(workDispatchRecord(props.work, null, 'results', true)))
const releaseEnded = computed(() => Boolean(props.work.status?.student_distribution?.ended_at))
const provisionChannel = computed(() => props.work.status?.provision_method?.channel)
const submissionChannel = computed(() => props.work.status?.submission_method?.channel)
const workflowProgress = computed(() => workWorkflowProgress(props.work, props.date, props.now,
    props.assessment?.emailDispatch || checked.value?.data?.exercise?.email_dispatch || {}, props.course?.students_info || [],
    props.assessment || checked.value?.data?.exercise))
const directExerciseFolder = computed(() => {
    const state = binding.value
    const folder = state?.folder
    const proof = props.work.portal_copy_progress?.[workflowProgress.value.portalCopyDate]
    if (!automation.value || !state?.connected || !state.link_version || state.module_link || state.module_conflict
        || props.assessment?.deletionPending || props.assessment?.deletion_pending
        || proof?.linked !== true || !proof.folder_id || proof.folder_id !== folder?.id || !proof.candidates_id
        || !Number.isFinite(Date.parse(proof.observed_at)) || typeof folder.name !== 'string' || !folder.name.trim()) return null
    return folder
})
const confirmedPreparationFolder = computed(() => {
    if (activity.value || props.assessment?.deletionPending || props.assessment?.deletion_pending) return null
    if (directExerciseFolder.value) return directExerciseFolder.value
    if (!binding.value?.module_link?.folder?.id) return null
    const status = props.assessment?.status
    const folder = status === 'found' ? props.assessment.folder
        : !status || status === 'missing' ? confirmedExerciseFolder(checked.value) : null
    return folder?.id && typeof folder.name === 'string' && folder.name.trim() ? folder : null
})
const readPreparationPath = ref('')
const preparationPathFailed = ref(false)
const preparationPath = computed(() => {
    const savedPath = confirmedPreparationFolder.value?.path
    return isDropboxFolderPath(savedPath) ? savedPath : readPreparationPath.value
})
let preparationPathRequest = null

function isDropboxFolderPath(path) {
    return typeof path === 'string' && path.startsWith('/') && !/[\u0000-\u001f]/u.test(path)
}

async function loadPreparationPath() {
    const folder = confirmedPreparationFolder.value
    const version = binding.value?.link_version
    if (!props.active || !folder || preparationPath.value || preparationPathRequest) return
    if (!version) {
        preparationPathFailed.value = true
        return
    }
    const request = new AbortController()
    preparationPathRequest = request
    preparationPathFailed.value = false
    const direct = Boolean(directExerciseFolder.value)
    try {
        const { data } = await axios.get(direct ? show.url(props.work.id) : folders.url(props.work.id), {
            signal: request.signal, timeout: 45000, params: direct ? { include_folder_path: 1 }
                : { contents: 1, exercise: 1, ...(props.date ? { date: props.date } : {}) },
        })
        if (preparationPathRequest !== request) return
        const target = direct ? data.folder : data.root
        const validLink = !direct || (data.connected && !data.module_link && !data.module_conflict)
        if (validLink && data.link_version === version && target?.id === folder.id && isDropboxFolderPath(target.path)) {
            readPreparationPath.value = target.path
        } else {
            preparationPathFailed.value = true
        }
    } catch {
        if (preparationPathRequest === request) preparationPathFailed.value = true
    } finally {
        if (preparationPathRequest === request) preparationPathRequest = null
    }
}

function resetPreparationPath() {
    preparationPathRequest?.abort()
    preparationPathRequest = null
    readPreparationPath.value = ''
    preparationPathFailed.value = false
}

watch([key, () => props.date, () => props.active, () => binding.value?.link_version, () => confirmedPreparationFolder.value?.id],
    resetPreparationPath, { flush: 'sync' })
onBeforeUnmount(resetPreparationPath)
const assessmentProgress = computed(() => {
    const progress = workflowProgress.value
    return progress.scoped === props.work ? props.assessmentCount : `${progress.assessed} von ${progress.total} beurteilt`
})
const reopenedCount = computed(() => workflowProgress.value.reopenedCount)
const portalClosureNotice = computed(() => {
    const cutoff = workflowProgress.value.portalCutoff
    if (!cutoff) return ''
    const upload = reopenedCount.value ? 'Datei-Upload nur mit persönlicher Frist.' : 'Datei-Upload gesperrt.'
    return `${cutoff === 'deadline' ? 'Abgabefrist abgelaufen.' : 'Abgabe geschlossen.'} ${upload}`
})
const portalSubmissionCount = computed(() => {
    const scoped = workflowProgress.value.scoped
    const ids = new Set((scoped.groups || []).flatMap(group => group.student_ids || []).map(String))
    return new Set((scoped.status?.portal_submissions || []).filter(file => !file.withdrawn_at
        && (!scoped.groups?.length && !props.work.groups?.length || ids.has(String(file.student_id))))
        .map(file => file.student_id).filter(Boolean)).size
})
const mailBatch = computed(() => props.work.status?.assignment_mail?.batches?.[props.date || 'all'])
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
    return Boolean(release || props.work.can_end_email_release
        || ((releasePrepared || releasePaused) && provisionChannel.value === 'portal' && submissionChosen))
}

const nextAction = computed(() => {
    if (activity.value) return { phase: 'preparation' }
    if (workflowProgress.value.completed) return null
    if (workflowProgress.value.next) return workflowProgress.value.next
    if (!automation.value) return { phase: 'assessment', label: 'Bewerten' }

    const distribution = props.work.status?.student_distribution
    const releasePrepared = distribution?.prepared_at && !distribution.published_at
    const releasePaused = distribution?.published_at && distribution.withdrawn_at
    const prepared = props.exerciseReady || props.work.local_task_copy
    if (!releaseEnded.value && !releasePrepared && !releasePaused && !workStudentDistribution(props.work)
        && !mailBatch.value && !prepared) {
        if (!binding.value) return { phase: 'preparation' }
        if (!linked.value) return { phase: 'preparation', action: 'link' }
        if (props.assessment?.status === 'missing') {
            const retryFolder = workFolderNeedsRetry(binding.value, checked.value)
            return { phase: 'preparation', action: retryFolder ? 'link' : 'create' }
        }
        if (props.assessment?.status !== 'found') return { phase: 'preparation', action: 'check' }
    }

    return { phase: 'provision', action: 'provision' }
})
const phaseLabels = { preparation: 'Vorbereiten', provision: 'Bereitstellen', submission: 'Abgeben', assessment: 'Bewerten' }
const phaseOrder = Object.keys(phaseLabels)

function phaseLockReason(phase) {
    const next = nextAction.value
    if (!next?.phase || phaseOrder.indexOf(phase) <= phaseOrder.indexOf(next.phase)) return ''
    if (activity.value) return 'Während der Erstellung gesperrt'
    if (next.action === 'create') return 'Zuerst Leistungsfeststellung erstellen'
    return 'Zuerst „' + phaseLabels[next.phase] + '“ abschließen'
}

const provisionDisabled = computed(() => mutationDisabled.value || Boolean(phaseLockReason('provision')))
const portalTargetPending = computed(() => releaseEnded.value
    && ['link-portal-target', 'check-portal-target'].includes(nextAction.value?.action))

function provisionOverview(dispatch) {
    const evidence = workProvisionEvidence(props.work, props.date, dispatch)
    const methodLabels = { portal: 'Im Schülerbereich', email: 'Per E-Mail', 'email-pending': 'Per E-Mail' }
    const recordedMethods = new Set()
    const provisionDetails = []
    for (const entry of evidence) {
        if (entry.key === 'choice') continue
        if (entry.key === 'direct_issue' || entry.key === 'download_release') {
            recordedMethods.add(entry.label)
            provisionDetails.push({ ...entry, label: entry.detail, detail: '' })
            continue
        }
        const method = methodLabels[entry.key]
        if (method && entry.label !== 'Bereitstellung im Schülerbereich entfernt') recordedMethods.add(method)
        if (entry.key === 'portal' && entry.label === 'Im Schülerbereich bereitgestellt') {
            if (entry.state && entry.state !== 'Freigabe beendet') provisionDetails.push({ ...entry, label: entry.state, state: '' })
        } else {
            provisionDetails.push(entry)
        }
    }
    const status = [...recordedMethods].join(' · ') || methodLabels[provisionChannel.value] || 'Bereitstellungsweg offen'
    return { status, provisionDetails }
}

function phasesFor(stages, dispatch) {
    const next = nextAction.value
    const progress = workflowProgress.value
    const primary = next?.phase === 'assessment' && next.label === 'Bewerten'
        ? (automation.value && canCopyGradingContext(stages) ? 'grade' : 'manual') : next?.action
    const submissionSummary = submissionChannel.value === 'portal' ? 'Portalabgabe'
        : submissionChannel.value === 'email' ? 'Abgabe per E-Mail' : 'Abgabeweg offen'
    const count = portalSubmissionCount.value
    const submissionStatus = progress.portalSubmissionClosed ? `Portalabgabe geschlossen · ${count} ${count === 1 ? 'Abgabe' : 'Abgaben'}`
        : progress.reopenedCount ? `${submissionSummary} · Persönliche Frist aktiv` : submissionSummary
    const run = confirmedRun.value || props.work.assessment_run
    const completedRun = run?.state === 'completed' && (run.date ? run.date === props.date
        : progress.scoped === props.work)
    const assignmentConfirmed = props.exerciseReady || props.assessment?.preparation?.status === 'ready'
        || (binding.value?.module_link?.folder?.id && progress.portalCopyTargetConfirmed)
    const preparationEvidence = completedRun ? 'Leistungsfeststellung angelegt' : props.work.local_task_copy ? 'Aufgabenstellung vorhanden'
        : props.work.status?.email_preparation_completed === true ? 'E-Mail vorbereitet' : ''
    const folderOrAssignmentConfirmed = Boolean(directExerciseFolder.value || assignmentConfirmed)
    const prepared = !activity.value && (folderOrAssignmentConfirmed || Boolean(preparationEvidence) || progress.preparationProven)
    const preparationStatus = prepared
        ? (folderOrAssignmentConfirmed ? '' : preparationEvidence || 'Durch spätere Schritte belegt')
        : assessmentStatus.value

    return [
        { key: 'preparation', icon: 'mdi-clipboard-text-outline', status: preparationStatus, complete: prepared },
        { key: 'provision', icon: 'mdi-send-outline', complete: progress.provisionComplete,
            ...provisionOverview(dispatch) },
        { key: 'submission', icon: 'mdi-inbox-arrow-down-outline',
            status: submissionStatus, closed: progress.portalSubmissionClosed,
            complete: !progress.portalCopyPending && (progress.submissionComplete || progress.portalSubmissionClosed) },
        { key: 'assessment', icon: 'mdi-clipboard-check-outline', complete: progress.assessmentComplete,
            status: assessmentProgress.value ?? stepStatus(stages[1]) },
    ].map(phase => {
        const lockReason = phaseLockReason(phase.key)
        const current = phase.key === next?.phase
        const displayIcon = lockReason ? 'mdi-lock-outline' : phase.closed ? 'mdi-lock' : phase.complete ? 'mdi-check-bold' : phase.icon
        return { ...phase, title: phaseLabels[phase.key], lockReason, locked: Boolean(lockReason), displayIcon, current,
            next: current && Boolean(primary || next.label), primary: current ? primary : null,
            portalNext: phase.key === 'submission' && current && progress.portalCopyPending, nextLabel: current ? next.label : null }
    })
}

function forwardImport(...args) {
    if (!mutationDisabled.value && !phaseLockReason('assessment')) emit('import', ...args)
}

function canCopyGradingContext(stages) {
    const { portalSubmissionClosed, submissionComplete } = workflowProgress.value
    const closedPortalReady = portalSubmissionClosed && props.exerciseReady && !props.assessment?.deletionPending
    return Boolean(stages[1] && (closedPortalReady
        || (!stages[1].blocked && (submissionComplete || props.lastCollection || portalSubmissionCount.value))))
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
.work-workflow__check { max-inline-size: 100%; }
.work-workflow__check :deep(.v-btn) { max-inline-size: 100%; height: auto; }
.work-workflow__check :deep(.v-btn__content) { min-inline-size: 0; white-space: normal; overflow-wrap: anywhere; }
.work-workflow__result { color: var(--flow-muted); font-size: .8rem; margin-block-end: .625rem; overflow-wrap: anywhere; }
.work-workflow__phases {
    border: 1px solid var(--flow-divider);
    border-radius: .875rem;
    min-inline-size: 0;
}
.work-workflow__loading {
    display: grid;
    place-items: center;
    min-block-size: 24rem;
    padding: 1rem;
    color: var(--flow-muted);
}
.work-phase + .work-phase { border-block-start: 1px solid var(--flow-divider); }
.work-phase__heading { margin: 0; }
.work-phase__heading-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    column-gap: .5rem;
    padding-inline: .875rem;
    border-radius: .75rem .75rem 0 0;
}
.work-phase__heading-action { display: flex; }
.work-phase__heading-action:empty { display: none; }
.work-phase__heading-action--preparation { align-self: center; }
.work-phase__heading-action > :deep(.v-btn) { color: var(--flow-accent); }
.work-phase__heading-action--assessment[data-primary="true"] > :deep(.v-btn) {
    background: var(--flow-current-accent, var(--flow-accent));
    color: var(--flow-current-on-accent, rgb(var(--v-theme-on-primary)));
}
.work-phase__header {
    display: grid;
    grid-template-columns: 2.125rem minmax(0, 1fr);
    align-items: center;
    gap: .75rem;
    inline-size: 100%;
    min-block-size: 3.625rem;
    padding-block: .625rem;
    text-align: start;
    color: rgb(var(--v-theme-on-surface));
}
.work-phase--current .work-phase__heading-row { background: rgba(var(--v-theme-primary), .065); }
.work-phase__icon {
    display: grid;
    place-items: center;
    inline-size: 2.125rem;
    block-size: 2.125rem;
    border-radius: .625rem;
    background: rgba(var(--v-theme-on-surface), .055);
    color: var(--flow-muted);
}
.work-phase--current .work-phase__icon { background: var(--flow-current-accent, var(--flow-accent)); color: var(--flow-current-on-accent, rgb(var(--v-theme-on-primary))); }
.work-phase--complete:not(.work-phase--locked) .work-phase__icon { background: rgba(var(--v-theme-success), .12); }
.work-phase .work-phase__icon--closed { background: rgba(var(--v-theme-error), .12); color: rgb(var(--v-theme-error)); }
.work-phase--next .work-phase__heading-row { background: rgba(var(--v-theme-warning), .11); }
.work-phase--next { --flow-current-accent: rgb(var(--v-theme-warning)); --flow-current-on-accent: #201400; }
.work-phase--next-copy .work-phase__title { font-size: 1.0625rem; }
.work-phase__caption { display: grid; min-inline-size: 0; align-self: start; }
.work-phase__title { font-size: .9375rem; font-weight: 700; line-height: 1.25; }
.work-phase__status { color: var(--flow-muted); font-size: .773rem; font-weight: 500; line-height: 1.4; overflow-wrap: anywhere; }
.work-phase__folder-name { justify-self: start; max-inline-size: 100%; }
:global(.work-preparation-path-tooltip) { overflow-wrap: anywhere; white-space: normal; }
.work-phase__body { padding: .125rem .875rem .625rem; }
.work-phase__body--preparation:not(:has(button, [role="status"], [role="alert"])) { display: none; }
.work-phase__body--closed-portal:not(:has(button, .work-phase__item-label > *)) { display: none; }
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
.work-phase__actions, .work-workflow__footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0 .375rem;
    min-inline-size: 0;
}
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
    background: var(--flow-current-accent, var(--flow-accent)) !important;
    color: var(--flow-current-on-accent, rgb(var(--v-theme-on-primary))) !important;
}
.work-phase--next-copy .workflow-command[data-primary="true"] > :deep(.v-btn) {
    min-block-size: 48px;
    font-size: .9375rem;
}
.work-phase--next-copy .workflow-command[data-primary="true"] :deep(.v-btn__content) { white-space: normal; }
.work-phase__heading-row--submission { container-type: inline-size; }
.work-phase__heading-action--submission { align-items: center; }
.work-phase__copy-action { margin-inline-end: .375rem; }
@container (max-width: 30rem) {
    .work-phase__copy-action > :deep(.v-btn) { inline-size: 44px; }
    .work-phase__copy-action :deep(.v-btn__prepend) { margin-inline: 0; }
    .work-phase__copy-action :deep(.v-btn__content) { display: none; }
}
.work-phase__details { color: var(--flow-muted); font-size: .8125rem; }
.work-phase__details summary { display: list-item; align-content: center; min-block-size: 44px; cursor: pointer; }
.work-phase__details--results { border-block-start: 1px solid var(--flow-divider); margin-block-start: .25rem; }
.work-phase__details--results .work-phase__item { padding-inline-start: .5rem; }
.work-phase__folder-name:focus-visible, .work-phase__details summary:focus-visible,
.work-phase__heading-action :deep(.v-btn:focus-visible), .workflow-command :deep(.v-btn:focus-visible) {
    outline: 2px solid var(--flow-accent);
    outline-offset: -2px;
}
.work-workflow__footer { margin-block-start: .25rem; }
@media (max-width: 420px) {
    .work-phase__header { gap: .5rem; }
    .work-phase__heading-row { padding-inline: .625rem; }
    .work-phase__body { padding-inline: .625rem; }
    .workflow-command { flex-wrap: wrap; }
    .workflow-command :deep(.v-btn) { height: auto; padding-inline: .375rem; font-size: .79rem; }
    .workflow-command :deep(.v-btn__content) { min-inline-size: 0; white-space: normal; overflow-wrap: anywhere; }
}
</style>

