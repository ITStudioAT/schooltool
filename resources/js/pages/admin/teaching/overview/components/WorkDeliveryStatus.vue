<template>
    <WorkStudentDistribution :key="work.id" :work="work" :date="date" :available="exerciseReady || work.local_task_copy"
        :prepared-exercise="modernPrepared" :roster="courseRoster" :dispatch="dispatch" :disabled="disabled || savingCompletion" @saved="emit('saved', $event)">
    <template #activator="{ open: openStudentDistribution, active: studentDistributionOpen, available: canOpenStudentDistribution, label: studentDistributionLabel, buttonProps: studentDistributionButtonProps }">
    <slot v-if="$slots.workflow" name="workflow" :stages="stages" :open-stage="openStage"
        :release="portalRelease" :release-prepared="portalPrepared" :release-paused="portalWithdrawn"
        :release-available="canOpenStudentDistribution || Boolean(portalRelease)" :open-release="() => openWorkflowRelease(openStudentDistribution, canOpenStudentDistribution)"
        :dispatch="dispatch" :legacy-mail="legacyMailAvailable" :open-legacy-mail="openLegacyMail" />
    <span v-else-if="portalEnded || portalPending || work.local_task_copy || workAutomationEnabled(work) || workHasProgress(work)" class="d-inline-flex flex-wrap align-center ga-1" @click.stop @keydown.stop>
        <v-btn v-if="portalEnded || portalRelease || portalPending || work.can_end_email_release" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="portalEnded ? 'secondary' : portalPending ? 'warning' : 'success'" :disabled="disabled" :title="work.can_end_email_release && !portalRelease && !portalPending ? 'Freigabe verwalten' : portalActionLabel" :aria-label="work.can_end_email_release && !portalRelease && !portalPending ? 'Freigabe verwalten' : portalActionLabel" @click.stop="openStudentDistribution">
            <v-icon :size="size">mdi-account-school-outline</v-icon>
        </v-btn>
        <v-btn v-if="!portalEnded && !portalRelease && !portalPending" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="modernPrepared ? 'primary' : preparation.complete ? 'success' : 'error'" :disabled="disabled" :aria-label="preparationActionLabel" :title="preparationActionLabel" data-workflow-stage="preparation" @click.stop="opened = 'preparation'">
            <v-icon :size="size">mdi-email-edit-outline</v-icon>
        </v-btn>
        <v-btn v-if="!portalEnded && !portalRelease && !portalPending" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="sent || manuallyCompleted ? 'success' : 'error'" :disabled="disabled || sendBlocked" :aria-label="sendLabel" :title="sendBlocked ? 'Zuerst die E-Mail-Vorbereitung abschließen' : sendLabel" data-workflow-stage="send" @click.stop="opened = 'send'">
            <v-icon :size="size">mdi-send-outline</v-icon>
        </v-btn>
        <v-btn v-for="stage in stages" :key="stage.key" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="stage.color"
            :disabled="disabled || stage.blocked" :aria-label="stageLabel(stage)" :title="stageLabel(stage)" :data-workflow-stage="stage.key" :data-workflow-state="stage.state" @click.stop="openStage(stage)">
            <v-icon :size="size">{{ stage.icon }}</v-icon>
        </v-btn>
    </span>
    <v-dialog v-if="!studentDistributionOpen" v-model="dialog" max-width="640" scrollable :persistent="savingCompletion">
        <v-card>
            <v-card-title class="text-wrap">{{ opened === 'preparation' ? 'Aufgaben bereitstellen' : opened === 'send' ? 'E-Mail-Versand' : selectedStage?.title }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <v-alert v-if="completionError" type="error" variant="tonal" class="mb-3" role="alert">{{ completionError }}</v-alert>
                <v-alert :type="opened === 'send' ? (sent || manuallyCompleted ? 'success' : 'warning') : (selectedStage?.complete ? 'success' : 'info')" variant="tonal">{{ opened === 'send' ? sendLabel : selectedStage?.label }}</v-alert>
                <template v-if="opened === 'send'">
                    <p v-if="manuallyCompleted" class="mt-3">Der Versand ist manuell als erledigt markiert. Diese Markierung bestätigt keinen E-Mail-Versand.</p>
                    <p v-if="sent" class="mt-3">{{ dispatch.confirmed_count }} von {{ dispatch.expected_count }} Nachrichten bestätigt.</p>
                    <p v-if="dispatch.sent_at" class="mt-2">Versandt: {{ assessmentTimeText(dispatch.sent_at) }}</p>
                    <p v-if="sent && dispatch.transport === 'mailpit'" class="text-orange-darken-3 mt-2">Testversand über Mailpit. Die Nachrichten liegen ausschließlich im lokalen Mailpit.</p>
                    <p v-if="dispatch.last_protocol?.run" class="text-caption mt-2">Versandlauf: {{ dispatch.last_protocol.run }}</p>
                    <p class="mt-3">{{ sent || workHasProgress(work) ? nextActionText : 'E-Mails versenden oder diesen Schritt als erledigt markieren, wenn kein Versand erforderlich ist.' }}</p>
                </template>
                <template v-else>
                    <p v-if="opened !== 'preparation' && opened !== 'collection'" class="mt-3">{{ selectedStage?.count }} von {{ selectedStage?.total }} Personen mit gespeichertem Nachweis.</p>
                    <p v-if="selectedStage?.detail && opened !== 'collection'" class="mt-2">{{ selectedStage.detail }}</p>
                    <p v-if="selectedStage?.manuallyCompleted && opened !== 'collection'" class="mt-3">Dieser Schritt ist manuell als erledigt markiert. Die Markierung bestätigt keine erstellten E-Mails, Bewertungen, bereitgestellten PDFs oder versandten Nachrichten. Vorhandene Nachweise, Zeitstempel und Punkte bleiben unverändert.</p>
                    <p v-if="opened === 'preparation' && !modernPrepared && !portalPending && !portalRelease && !selectedStage?.manuallyCompleted" class="mt-3">Die E-Mail-Vorbereitung kann als erledigt markiert und die Markierung wieder aufgehoben werden. Damit werden keine E-Mails erstellt oder versandt.</p>
                    <p v-if="opened === 'notification' && selectedStage?.completedAt" class="mt-3"><strong>Abgeschlossen am:</strong> {{ assessmentTimeText(selectedStage.completedAt) }}</p>
                    <v-btn v-if="opened === 'notification' && selectedStage?.simulation" class="mt-2" variant="text" href="http://127.0.0.1:8025" target="_blank" rel="noopener noreferrer">Simulator-Mailpit öffnen</v-btn>
                    <table v-if="opened === 'evaluation' && simulationEvaluation" class="simulation-evaluation mt-3" aria-label="Testbewertungen">
                        <thead><tr><th>Person</th><th>Testbewertung</th></tr></thead>
                        <tbody><tr v-for="row in sortedSimulationRows" :key="row.student_id"><td>{{ row.last_name ? `${row.last_name}, ${row.first_name}` : row.person }}</td><td>{{ row.result }}</td></tr></tbody>
                    </table>
                    <section v-if="opened === 'evaluation' && !simulationEvaluation" class="student-status mt-4" aria-label="Punkteübersicht">
                        <div class="student-status__header"><div><h3 class="student-status__title">Punkteübersicht <span class="student-status__count">{{ evaluationStudents.length }}</span></h3>
                            <p class="student-status__hint">Gespeicherte Bewertungsteile und MC-Testergebnisse werden getrennt angezeigt. Ein MC-Ergebnis ist noch keine bestätigte Gesamtbewertung.</p></div></div>
                        <p v-if="studentStatusError" class="student-status__error text-error" role="alert">{{ studentStatusError }}</p>
                        <ul class="student-status__list">
                            <li v-for="student in evaluationStudents" :key="student.id" class="evaluation-row">
                                <span class="student-status__name">{{ student.name }}</span>
                                <div v-for="criterion in student.criteria" :key="criterion.criterion" class="evaluation-row__part"><span>{{ criterion.criterion }}</span><span>{{ minorPoints(criterion.earned_minor) }} / {{ minorPoints(criterion.maximum_minor) }}</span></div>
                                <div v-for="test in student.tests" :key="test.id" class="evaluation-row__part"><span>MC-Test · {{ test.title }} (Teststand)</span><span>{{ test.points }}</span></div>
                                <div class="evaluation-row__part evaluation-row__total"><span>Gesamtbewertung</span><span>{{ student.total }}</span></div>
                            </li>
                        </ul>
                        <div class="evaluation-averages" aria-label="Mittelwerte">
                            <h4>Mittelwerte</h4>
                            <p class="student-status__hint">Nur vorhandene vergleichbare Werte; offene Werte und vorläufige Gesamtsummen werden nicht mitgezählt.</p>
                            <div v-for="average in evaluationAverages" :key="average.key" class="evaluation-row__part"><span>{{ average.label }}</span><span>{{ average.value }}</span></div>
                        </div>
                    </section>
                    <template v-if="opened === 'collection'">
                        <p v-if="!portalEnded && !selectedStage?.simulation" class="text-body-2 mt-3">Abgaben können erst nach „Freigabe beenden“ eingesammelt werden.</p>
                        <section class="student-status mt-4" aria-label="Abgabestatus der Schülerinnen und Schüler">
                            <div class="student-status__header"><div>
                                <h3 class="student-status__title">Schüler:innen <span class="student-status__count">{{ collectionStudents.length }}</span></h3>
                                <p class="student-status__hint">Abgabe und MC-Test werden getrennt angezeigt. Ohne Abgabenachweis bleibt der Abgabestatus ungeklärt.</p>
                                <p v-if="work.status?.submission_checks?.length" class="student-status__hint">Ein ungeklärter Status kann auf fehlende oder unvollständige Nachweise zurückgehen.</p>
                            </div></div>
                            <p v-if="studentStatusError" class="student-status__error text-caption text-error" role="alert">{{ studentStatusError }}</p>
                            <p v-if="!collectionStudents.length" class="student-status__error">Keine zugeordneten Schüler:innen verfügbar.</p>
                            <ul class="student-status__list">
                                <li v-for="student in collectionStudents" :key="student.id" class="student-status__row">
                                    <div><span class="student-status__name">{{ student.name }}</span>
                                        <p v-if="['not_received', 'scoped_absence'].includes(student.status.state) || student.status.label.startsWith('Widersprüchliche')" class="student-status__hint">{{ student.status.label }}</p>
                                    </div>
                                    <div class="student-status__statuses">
                                        <div><span class="student-status__status-label">Abgabe</span><span class="student-status__badge" :title="student.status.label">{{ student.status.state === 'received' ? 'Liegt vor' : ['not_received', 'scoped_absence'].includes(student.status.state) ? 'Nicht abgegeben' : 'Ungeklärt' }}</span></div>
                                        <div><span class="student-status__status-label">MC-Test</span><span class="student-status__badge" :class="{ 'student-status__badge--warning': studentTestTimedOut(student.id) }">{{ studentStatus(student.id) }}</span></div>
                                    </div>
                                </li>
                            </ul>
                        </section>
                        <p class="text-caption mt-3">{{ selectedStage?.manuallyCompleted ? 'Manuell als erledigt markiert; dies bestätigt keine Abgaben und keinen Sammellauf.' : 'Eine manuelle Erledigung gibt „Bewerten“ frei, bestätigt aber keinen Sammellauf.' }}</p>
                        <p v-if="selectedStage?.simulation" class="text-caption mt-2">Simulator: {{ selectedStage.lastCollectedAt ? assessmentTimeText(selectedStage.lastCollectedAt) : 'Noch nicht eingesammelt.' }}</p>
                        <p v-else class="text-caption mt-2">Letztes belegtes Einsammeln: {{ lastCollectionAt ? assessmentTimeText(lastCollectionAt) : 'Zeitpunkt nicht gespeichert.' }}</p>
                        <p v-if="!selectedStage?.simulation && work.collection_run?.changes_detected === null" class="text-caption mt-2">Der ältere Einsammelbeleg enthält keinen auswertbaren Änderungsnachweis. Bisherige Bewertungsbelege bleiben erhalten.</p>
                    </template>
                    <v-alert v-if="selectedStage?.review" type="warning" variant="tonal" class="mt-3">Versandzeitpunkt und Bewertungsstand weichen ab. Vor einer weiteren Nachricht den bestätigten Stand abgleichen.</v-alert>
                    <v-alert v-if="opened === 'evaluation' && lastCollectionRunAt && work.collection_run?.checkpoint_state !== 'complete'" type="warning" variant="tonal" class="mt-3">Der Einsammellauf hat offene Prüflücken. Ungeklärte Abgaben bleiben offen; solange die Frist läuft, sind weitere Abgaben möglich.</v-alert>
                    <p class="mt-2">{{ nextActionText }}</p>
                    <WorkEvaluationPdf v-if="opened === 'publication' || opened === 'evaluation'" :work="work" class="mt-3" />
                    <p v-if="dispatch.transport === 'mailpit'" class="text-caption text-orange-darken-3 mt-2">Lokaler Entwicklungsdurchlauf nach Testversand über Mailpit.</p>
                </template>
                <div v-if="(opened === 'preparation' || opened === 'send') && !portalEnded && !portalPending" class="d-flex flex-wrap ga-2 mt-4">
                    <WorkEmailPreparation :work="work" :date="date" :state="state" :disabled="savingCompletion" :modern-prepared="modernPrepared" :action="opened === 'send' ? 'send' : 'prepare'" />
                    <v-btn v-if="opened === 'preparation' && !portalRelease && canOpenStudentDistribution" v-bind="studentDistributionButtonProps" @click.stop="openStudentDistribution">{{ studentDistributionLabel }}</v-btn>
                </div>
                <v-alert v-if="opened === 'preparation' && modernPrepared && !portalEnded && !portalPending"
                    type="info" variant="tonal" class="mt-3">Die KI zeigt die vorbereiteten E-Mails zur Prüfung. Der Prüfungsstatus bestätigt keine Versandbereitschaft.</v-alert>
            </v-card-text>
            <v-card-actions class="d-flex flex-wrap ga-2" aria-label="Dialogaktionen">
                <WorkEmailPreparation v-if="opened === 'preparation' && !portalEnded && !portalPending" :work="work" :date="date" :state="state" :disabled="savingCompletion" :modern-prepared="modernPrepared" status-only>
                    <template #status-activator="{ open, available, label }">
                        <v-btn v-if="!modernPrepared || available" v-show="available" variant="tonal" prepend-icon="mdi-eye-outline" :title="label" @click.stop="open">Versandbereitschaft ansehen</v-btn>
                    </template>
                </WorkEmailPreparation>
                <v-btn v-if="opened === 'send' && !modernPrepared && (!sent || manuallyCompleted)" variant="tonal" :prepend-icon="manuallyCompleted ? 'mdi-undo' : 'mdi-check'" :loading="savingCompletion" :disabled="savingCompletion" @click="saveCompletion('task_dispatch_completed', !manuallyCompleted)">
                    {{ manuallyCompleted ? 'Markierung aufheben' : 'Als erledigt markieren' }}
                </v-btn>
                <v-btn v-if="selectedStage?.completionField && !(opened === 'preparation' && portalPending)" variant="tonal" :prepend-icon="selectedStage?.manuallyCompleted ? 'mdi-undo' : 'mdi-check'" :loading="savingCompletion" :disabled="savingCompletion || selectedStage.blocked" @click="saveCompletion(selectedStage.completionField, !selectedStage.manuallyCompleted)">
                    {{ selectedStage?.manuallyCompleted ? 'Markierung aufheben' : 'Als erledigt markieren' }}
                </v-btn>
                <WorkSubmissionCollection v-if="opened === 'collection' && portalEnded && !selectedStage?.simulation" :work="work" :date="date" :repeat="(!selectedStage?.manuallyCompleted && selectedStage?.complete) || Boolean(lastCollectionAt)" :disabled="savingCompletion" />
                <v-btn v-if="opened === 'collection' && selectedStage?.simulation" variant="tonal" prepend-icon="mdi-flask-outline" to="/admin/teaching-tools/simulator">Simulator öffnen</v-btn>
                <WorkGradingAction v-if="opened === 'evaluation'" :work="work" :date="date" :repeat="selectedStage?.complete && !selectedStage?.manuallyCompleted"
                    :disabled="selectedStage?.blocked || savingCompletion || (!stages[0]?.complete && !lastCollectionAt && !hasPortalSubmissions)" />
                <v-btn v-if="opened === 'publication'" variant="tonal" prepend-icon="mdi-account-check-outline" :disabled="selectedStage?.blocked || savingCompletion" @click="emit('import', work, false, { date }); opened = null">
                    {{ selectedStage?.complete ? 'Ergebnisse erneut übernehmen' : 'Ergebnisse übernehmen' }}
                </v-btn>
                <WorkResultNotification v-if="opened === 'notification' && !selectedStage?.blocked && !savingCompletion" :work="work" :date="date" @saved="emit('saved', $event)" />
                <v-spacer />
                <v-btn :disabled="savingCompletion" @click="opened = null">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    </template>
    </WorkStudentDistribution>
</template>

<script setup>
import { computed, ref, useSlots, watch } from 'vue'
import { update } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { useWorkMcAttemptStatus } from '@/composables/useWorkMcAttemptStatus'
import { sortedPeople } from '@/helpers/personNames'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { assessmentTimeText } from '@/helpers/workAssessmentTimes'
import { workDispatchNotifications, workTaskDispatchCompleted, workStudentDistribution } from '@/helpers/workDispatch'
import { personalWorkAssessment, workCollectionStudentStatus } from '@/helpers/workSubmission'
import { workHasProgress, workProgressStages, workProgressNextAction, workLastCollectionAt, workLastCollectionRunAt, workSimulationEvaluation, workManualCompletionFields } from '@/helpers/workProgress'
import WorkEvaluationPdf from './WorkEvaluationPdf.vue'
import WorkSubmissionCollection from './WorkSubmissionCollection.vue'
import WorkGradingAction from './WorkGradingAction.vue'
import WorkResultNotification from './WorkResultNotification.vue'
import WorkEmailPreparation from './WorkEmailPreparation.vue'
import WorkStudentDistribution from './WorkStudentDistribution.vue'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, state: { type: Object, default: null }, size: { type: [Number, String], default: 18 }, disabled: Boolean, exerciseReady: Boolean })
const admin = useAdminStore()
const courseStore = useCourseStore()
const slots = useSlots()
const emit = defineEmits(['import', 'saved'])
const modernPrepared = computed(() => {
    const state = props.state
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const checked = workExerciseCheck(key, props.work, props.date)
    const currentMatches = state && state.userId === admin.config?.user?.id
        && String(state.workId) === String(props.work.id) && String(state.courseId) === String(props.work.teaching_course_id)
        && state.date === props.date
    if (currentMatches && (state.status === 'checking' || !checked
        || !(Date.parse(checked.checkedAt) > Date.parse(state.checkedAt || 0)))) {
        return state.status === 'found' && state.preparation?.status === 'ready'
    }
    return checked?.status === 'found' && checked.data?.exercise?.preparation?.status === 'ready'
})
const manuallyCompleted = computed(() => !modernPrepared.value && workTaskDispatchCompleted(props.work))
const portalEnded = computed(() => Boolean(props.work.status?.student_distribution?.ended_at))
const portalRelease = computed(() => workStudentDistribution(props.work))
const portalPrepared = computed(() => Boolean(props.work.status?.student_distribution?.prepared_at && !props.work.status.student_distribution.published_at))
const portalWithdrawn = computed(() => !portalEnded.value && Boolean(props.work.status?.student_distribution?.published_at && props.work.status.student_distribution.withdrawn_at))
const portalPending = computed(() => portalPrepared.value || portalWithdrawn.value)
const portalActionLabel = computed(() => portalEnded.value ? 'Beendete Freigabe ansehen' : portalPending.value ? 'Pausierte Aufgaben verwalten' : 'Aufgaben im Schülerbereich bereitgestellt')
function openWorkflowRelease(openStudentDistribution, available) {
    if (props.disabled) return
    if (available || portalEnded.value || portalRelease.value) openStudentDistribution()
}
const linkedTests = computed(() => {
    const tests = props.work.status?.multiple_choice_tests || {}
    return [...new Set([tests[props.date || props.work.date_for_all_groups], tests.all].map(test => test?.test_id).filter(Boolean))]
})
const courseRoster = computed(() => String(courseStore.selected_course?.id) === String(props.work.teaching_course_id)
    ? courseStore.selected_course?.students_info || [] : [])
const collectionStudents = computed(() => {
    const date = props.date || props.work.date_for_all_groups || ''
    const groups = (props.work.groups || []).filter(group => !date || !group.date || group.date === date)
    const ids = [...new Set(groups.flatMap(group => group.student_ids || []).map(String))]
    return ids.map(id => {
        const person = courseRoster.value.find(student => String(student.user_id ?? student.id) === id)
        return { id, name: person ? [person.last_name, person.first_name].filter(Boolean).join(' ') : 'Name nicht verfügbar', status: workCollectionStudentStatus(props.work, id) }
    }).sort((a, b) => a.name.localeCompare(b.name, 'de'))
})
function minorPoints(value) {
    return typeof value === 'number' && Number.isFinite(value) ? (value / 100).toLocaleString('de-AT', { maximumFractionDigits: 2 }) : 'Offen'
}
const evaluationStudents = computed(() => collectionStudents.value.map(student => {
    const record = personalWorkAssessment(props.work, student.id)
    const receipt = (props.work.status?.assessment_json_imports || []).findLast(item => String(item.student_id) === String(student.id))
    const pkg = record && (props.work.status?.assessment_json_packages || []).findLast(item => item.exercise_id === receipt?.exercise_id)
    const definition = String(courseStore.selected_course?.id) === String(props.work.teaching_course_id)
        ? courseStore.selected_course?.teaching_entry_area?.entry_definitions?.find(item => item.category === 'Benotung' && item.short_name === props.work.type) : null
    const maximum = Number.isFinite(pkg?.maximum_minor) ? minorPoints(pkg.maximum_minor)
        : definition?.maximum_points != null && Number.isFinite(Number(definition.maximum_points)) ? Number(definition.maximum_points).toLocaleString('de-AT') : null
    const total = record?.evaluation_state === 'complete' ? minorPoints(record.total_minor) : 'Offen'
    const tests = studentTests.value.map(test => {
        const matches = studentAttempts.value.filter(attempt => String(attempt.student_id) === String(student.id) && String(attempt.test_id) === String(test.id))
        const attempt = matches.length === 1 ? matches[0] : null
        const score = attempt?.completed_at && attempt.score != null && Number.isFinite(Number(attempt.score)) ? Number(attempt.score).toLocaleString('de-AT') : 'Offen'
        const max = test.total_points != null && Number.isFinite(Number(test.total_points)) ? Number(test.total_points).toLocaleString('de-AT') : null
        return { id: test.id, title: test.title, points: max === null ? score : `${score} / ${max}`, score: attempt?.completed_at && attempt.score != null && Number.isFinite(Number(attempt.score)) ? Number(attempt.score) : null, maximum: max === null ? null : Number(test.total_points) }
    })
    return { ...student, criteria: Array.isArray(record?.criteria) ? record.criteria : [], tests, total: maximum === null ? total : `${total} / ${maximum}`,
        totalMinor: record?.evaluation_state === 'complete' && Number.isFinite(record.total_minor) ? record.total_minor : null,
        maximumMinor: maximum === null ? null : Number.isFinite(pkg?.maximum_minor) ? pkg.maximum_minor : Number(definition.maximum_points) * 100 }
}))
const evaluationAverages = computed(() => {
    const groups = new Map()
    const add = (key, label, maximum, value) => {
        if (!Number.isFinite(maximum)) return
        if (!groups.has(key)) groups.set(key, { key, label, maximum, values: [] })
        if (Number.isFinite(value)) groups.get(key).values.push(value)
    }
    evaluationStudents.value.forEach(student => {
        student.criteria.forEach(c => add(`criterion:${c.criterion}:${c.maximum_minor}`, c.criterion, c.maximum_minor / 100,
            typeof c.earned_minor === 'number' ? c.earned_minor / 100 : null))
        student.tests.forEach(test => add(`test:${test.id}:${test.maximum}`, `MC-Test · ${test.title} (Teststand)`, test.maximum, test.score))
        if (student.maximumMinor != null) add(`total:${student.maximumMinor}`, 'Gesamtbewertung', student.maximumMinor / 100,
            student.totalMinor == null ? null : student.totalMinor / 100)
    })
    if (![...groups.values()].some(group => group.label === 'Gesamtbewertung')) groups.set('total:unknown', { key: 'total:unknown', label: 'Gesamtbewertung', maximum: null, values: [] })
    return [...groups.values()].map(group => ({ ...group, value: group.values.length
        ? `${(group.values.reduce((sum, value) => sum + value, 0) / group.values.length).toLocaleString('de-AT', { maximumFractionDigits: 2 })} / ${group.maximum.toLocaleString('de-AT')} · ${group.values.length} ${group.values.length === 1 ? 'Bewertung' : 'Bewertungen'}`
        : `Keine Werte${group.maximum === null ? '' : ` / ${group.maximum.toLocaleString('de-AT')}`} · 0 Bewertungen` }))
})
const { studentAttempts, studentTests, studentStatusError, studentStatus, studentTestTimedOut, loadStudentStatuses, clearStudentStatuses } = useWorkMcAttemptStatus(linkedTests)

const stages = computed(() => workProgressStages(props.work).map(stage => {
    if (stage.key === 'collection' && !portalEnded.value && !stage.simulation) {
        stage = { ...stage, title: 'Abgabestatus' }
    }
    if (slots.workflow) {
        if (stage.complete || stage.count > 0) return { ...stage, blocked: false }
        if (stage.key === 'evaluation' && hasPortalSubmissions.value) return { ...stage, blocked: false }
        if (!hasSentTasks.value && !assignmentMailSent.value && !portalWithdrawn.value && !lastCollectionRunAt.value && !lastCollectionAt.value
            && !props.work.local_task_copy) return { ...stage, blocked: true, blockedBy: 'Bereitstellung' }
        return stage
    }
    if (!portalEnded.value && !portalRelease.value && !preparation.value.complete) return { ...stage, blocked: true, blockedBy: 'E-Mail-Vorbereitung' }
    return !hasSentTasks.value ? { ...stage, blocked: true, blockedBy: 'E-Mail-Versand' } : stage
}))
const stageLabel = stage => stage.blocked ? `${stage.label} · zuerst „${stage.blockedBy}“ abschließen`
    : stage.completedAt ? `${stage.label} · ${assessmentTimeText(stage.completedAt)}` : stage.label
const openStage = stage => { if (!props.disabled && !stage.blocked) opened.value = stage.key }
const lastCollectionAt = computed(() => workLastCollectionAt(props.work))
const lastCollectionRunAt = computed(() => workLastCollectionRunAt(props.work))
const hasPortalSubmissions = computed(() => {
    if (!slots.workflow) return false
    const date = props.date || props.work.date_for_all_groups
    const students = new Set((props.work.groups || []).filter(group => !date || !group.date || group.date === date)
        .flatMap(group => group.student_ids || []).map(String))
    return (props.work.status?.portal_submissions || []).some(file => !file.withdrawn_at && students.has(String(file.student_id)))
})
const simulationEvaluation = computed(() => workSimulationEvaluation(props.work))
const sortedSimulationRows = computed(() => sortedPeople(simulationEvaluation.value?.rows))
const preparation = computed(() => {
    const manuallyCompleted = !modernPrepared.value && props.work.status?.email_preparation_completed === true
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const date = props.date || props.work.date_for_all_groups || ''
    const cached = workExerciseCheck(key, props.work, date)?.data || workDropboxStates.get(key)
    const checked = cached?.exercise?.status === 'found' && (!date || cached.exercise.folder?.name?.startsWith(`${date}_`))
        ? cached.exercise.email_preparation : null
    const current = props.state?.status === 'found' && props.state.userId === admin.config?.user?.id
        && String(props.state.workId) === String(props.work.id) && String(props.state.courseId) === String(props.work.teaching_course_id)
        && props.state.date === props.date ? props.state.emailPreparation : null
    const ready = !modernPrepared.value && (current || checked)?.status === 'ready'
    return { title: 'E-Mail-Vorbereitung', completionField: modernPrepared.value ? null : workManualCompletionFields.preparation, manuallyCompleted,
        complete: manuallyCompleted || ready, label: `E-Mail-Vorbereitung: ${manuallyCompleted ? 'manuell erledigt' : ready ? 'vorbereitet' : 'noch offen'}` }
})
const preparationActionLabel = computed(() => modernPrepared.value ? 'Aufgaben bereitstellen' : preparation.value.label)
const sendBlocked = computed(() => !preparation.value.complete)
const selectedStage = computed(() => opened.value === 'preparation' ? preparation.value : stages.value.find(stage => stage.key === opened.value))
const nextAction = computed(() => modernPrepared.value && !portalEnded.value && !portalRelease.value ? 'E-Mails vorbereiten'
    : workProgressNextAction(props.work) || (preparation.value.complete ? 'E-Mails versenden' : opened.value === 'preparation' ? 'E-Mails vorbereiten' : 'Abgaben einsammeln'))
const nextActionText = computed(() => nextAction.value === 'Abgeschlossen' ? 'Status: ABGESCHLOSSEN.'
    : nextAction.value === 'Abgaben einsammeln' && !portalEnded.value ? 'Abgaben können erst nach „Freigabe beenden“ eingesammelt werden.'
        : `Nächster Schritt: ${nextAction.value}.`)
const opened = ref(null)
const savingCompletion = ref(false)
const completionError = ref('')
const dialog = computed({ get: () => Boolean(opened.value), set: value => { if (!value) opened.value = null } })
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
    const groups = (props.work.groups || []).filter(group => !date || !group.date || group.date === date)
    const students = new Set(groups.flatMap(group => group.student_ids || []).map(String))
    const notifications = workDispatchNotifications(props.work, 'tasks').filter(item => students.has(String(item.student_id)))
    const count = new Set(notifications.map(item => String(item.student_id))).size
    return { status: count > 0 ? (count === students.size ? 'sent' : 'partial') : 'missing',
        confirmed_count: count, expected_count: students.size, sent_at: notifications[0]?.sent_at, transport: 'office_outlook' }
})
const sent = computed(() => !modernPrepared.value && dispatch.value.status === 'sent')
const assignmentMailSent = computed(() => {
    const batches = props.work.status?.assignment_mail?.batches
    const batch = batches?.[props.date || 'all']
    return ['partial', 'sent'].includes(batch?.state) && batch.transport === 'postmark'
})
const hasSentTasks = computed(() => portalEnded.value || Boolean(portalRelease.value) || (!modernPrepared.value && (manuallyCompleted.value || (['sent', 'partial'].includes(dispatch.value.status) && Number(dispatch.value.confirmed_count) > 0))))
watch(stages, value => { if (value.find(stage => stage.key === opened.value)?.blocked) opened.value = null })
watch(sendBlocked, blocked => { if (blocked && opened.value === 'send') opened.value = null })
const sendLabel = computed(() => modernPrepared.value ? 'E-Mail-Versand für diesen Prüfungsaufbau nicht eingerichtet'
    : sent.value ? `E-Mails versandt${dispatch.value.transport === 'mailpit' ? ' · Testversand über Mailpit' : ''}`
    : manuallyCompleted.value ? 'E-Mail-Versand: manuell erledigt'
    : dispatch.value.status === 'partial' ? `E-Mail-Versand unvollständig: ${dispatch.value.confirmed_count} bestätigt`
        : dispatch.value.status === 'unknown' ? 'E-Mail-Versand nicht bestätigt – erneut prüfen' : 'E-Mails noch nicht versandt')
const legacyMailAvailable = computed(() => !portalEnded.value && !modernPrepared.value && !portalPending.value && workAutomationEnabled(props.work)
    && (props.exerciseReady || preparation.value.complete || Number(dispatch.value.confirmed_count) > 0))
function openLegacyMail() {
    if (!props.disabled && legacyMailAvailable.value) opened.value = sendBlocked.value ? 'preparation' : 'send'
}

watch(opened, value => {
    completionError.value = ''
    if (value === 'collection' || value === 'evaluation') loadStudentStatuses()
    else clearStudentStatuses()
})
watch(() => [props.work.id, props.work.teaching_course_id, props.date, linkedTests.value.join(',')], () => {
    if (opened.value === 'collection' || opened.value === 'evaluation') loadStudentStatuses()
    else clearStudentStatuses()
})
watch(() => props.disabled, disabled => { if (disabled) opened.value = null })

async function saveCompletion(field, completed) {
    if (savingCompletion.value) return
    savingCompletion.value = true
    completionError.value = ''
    try {
        const response = await axios.put(update.url(props.work.id), { [field]: completed })
        emit('saved', response.data.data)
        opened.value = null
    } catch (error) {
        completionError.value = Object.values(error.response?.data?.errors || {}).flat()[0]
            || error.response?.data?.message || 'Die Markierung konnte nicht gespeichert werden.'
    } finally {
        savingCompletion.value = false
    }
}
</script>

<style scoped>
.student-status { border: 1px solid #dfe5ef; border-radius: 12px; overflow: hidden; background: #fff; }
.student-status__header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; background: #f5f7fb; }
.student-status__title { color: #243552; font-size: 1rem; font-weight: 650; }
.student-status__count { display: inline-flex; align-items: center; justify-content: center; min-width: 25px; margin-left: 5px; padding: 2px 7px; border-radius: 999px; background: #e1e9f8; color: #314e86; font-size: .75rem; }
.student-status__hint { margin-top: 5px; color: #5b687c; font-size: .75rem; line-height: 1.4; }
.student-status__error { padding: 10px 16px 0; }
.student-status__list { margin: 0; padding: 0; list-style: none; }
.student-status__row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 16px; border-top: 1px solid #edf0f5; }
.student-status__name { color: #26364e; font-size: .875rem; font-weight: 500; overflow-wrap: anywhere; }
.student-status__badge { flex-shrink: 0; padding: 4px 10px; border-radius: 999px; background: #edf1f7; color: #42546e; font-size: .75rem; font-weight: 500; line-height: 1.25; text-align: center; }
.student-status__badge--warning { background: #fff0da; color: #814300; }
.student-status__statuses { display: grid; gap: 7px; justify-items: end; }
.student-status__statuses > div { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 6px; }
.student-status__status-label { color: #5b687c; font-size: .75rem; font-weight: 600; }
.evaluation-row { padding: 10px 16px; border-top: 1px solid #edf0f5; }
.evaluation-row__part { display: flex; justify-content: space-between; gap: 12px; margin-top: 5px; color: #5b687c; font-size: .8rem; }
.evaluation-row__total { color: #26364e; font-weight: 600; }
.evaluation-averages { padding: 14px 16px; border-top: 1px solid #dfe5ef; background: #f5f7fb; }
@media (max-width: 480px) {
    .student-status__header { align-items: flex-start; flex-wrap: wrap; }
    .student-status__row { flex-wrap: wrap; }
}
.simulation-evaluation { width: 100%; border-collapse: collapse; font-size: .875rem; text-align: left; }
.simulation-evaluation th, .simulation-evaluation td { padding: 8px 10px; border-bottom: 1px solid rgba(var(--v-theme-on-surface), .15); }
</style>
