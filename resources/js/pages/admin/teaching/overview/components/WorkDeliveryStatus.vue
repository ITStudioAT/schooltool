<template>
    <span v-if="work.local_task_copy || workAutomationEnabled(work) || workHasProgress(work)" class="d-inline-flex flex-wrap align-center ga-1" @click.stop @keydown.stop>
        <v-btn v-if="portalRelease" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" color="success" :disabled="disabled" title="Aufgaben im Schülerbereich bereitgestellt" aria-label="Aufgaben im Schülerbereich bereitgestellt" @click.stop="opened = 'preparation'">
            <v-icon :size="size">mdi-account-school-outline</v-icon>
        </v-btn>
        <v-btn v-if="!portalRelease" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="preparation.complete ? 'success' : 'error'" :disabled="disabled" :aria-label="preparation.label" :title="preparation.label" data-workflow-stage="preparation" @click.stop="opened = 'preparation'">
            <v-icon :size="size">mdi-email-edit-outline</v-icon>
        </v-btn>
        <v-btn v-if="!portalRelease" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="sent || manuallyCompleted ? 'success' : 'error'" :disabled="disabled || sendBlocked" :aria-label="sendLabel" :title="sendBlocked ? 'Zuerst die E-Mail-Vorbereitung abschließen' : sendLabel" data-workflow-stage="send" @click.stop="opened = 'send'">
            <v-icon :size="size">mdi-send-outline</v-icon>
        </v-btn>
        <v-btn v-for="stage in stages" :key="stage.key" icon :size="Number(size) > 20 ? 'small' : 'x-small'" :density="Number(size) > 20 ? 'default' : 'compact'" variant="text" :color="stage.color"
            :disabled="disabled || stage.blocked" :aria-label="stageLabel(stage)" :title="stageLabel(stage)" :data-workflow-stage="stage.key" :data-workflow-state="stage.state" @click.stop="openStage(stage)">
            <v-icon :size="size">{{ stage.icon }}</v-icon>
        </v-btn>
    </span>
    <v-dialog v-model="dialog" max-width="640" scrollable :persistent="savingCompletion">
        <v-card>
            <v-card-title class="text-wrap">{{ opened === 'preparation' ? 'Aufgaben bereitstellen' : opened === 'send' ? 'E-Mail-Versand' : selectedStage?.title }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <v-alert v-if="completionError" type="error" variant="tonal" class="mb-3" role="alert">{{ completionError }}</v-alert>
                <v-alert :type="opened === 'preparation' && portalRelease ? 'success' : opened === 'send' ? (sent || manuallyCompleted ? 'success' : 'warning') : (selectedStage?.complete ? 'success' : 'info')" variant="tonal">{{ opened === 'preparation' && portalRelease ? 'Im Schülerbereich bereitgestellt' : opened === 'send' ? sendLabel : selectedStage?.label }}</v-alert>
                <section v-if="opened === 'preparation' && portalRelease" class="student-status mt-4" aria-label="Schülerinnen und Schüler">
                    <div class="student-status__header">
                        <div>
                            <h3 class="student-status__title">Schüler:innen <span class="student-status__count">{{ releasedStudents.length }}</span></h3>
                            <p class="student-status__hint">Status des MC-Tests · Die Bearbeitung der E-Mail-Aufgabe wird nicht erfasst.</p>
                        </div>
                        <v-btn v-if="linkedTests.length" size="small" variant="text" prepend-icon="mdi-refresh" :loading="studentStatusLoading" @click="loadStudentStatuses">Aktualisieren</v-btn>
                    </div>
                    <p v-if="studentStatusError" class="student-status__error text-caption text-error" role="alert">{{ studentStatusError }}</p>
                    <ul class="student-status__list">
                        <li v-for="student in releasedStudents" :key="student.id" class="student-status__row">
                            <span class="student-status__name">{{ student.name }}</span>
                            <div class="d-flex flex-wrap align-center justify-end ga-1">
                                <span class="student-status__badge">{{ studentStatus(student.id) }}</span>
                                <template v-for="attempt in attemptsForStudent(student.id)" :key="attempt.id">
                                    <v-btn v-if="!attempt.completed_at && !['submitted', 'timeout'].includes(attempt.status)" icon size="small" variant="text"
                                        :aria-label="`${attempt.individual_paused_at ? 'Fortsetzen' : 'Pausieren'}: ${student.name} · ${attempt.test_title}`"
                                        :title="`${attempt.individual_paused_at ? 'Fortsetzen' : 'Pausieren'} · ${attempt.test_title}`" :disabled="!!testActionBusy"
                                        @click="controlAttempt(attempt, attempt.individual_paused_at ? 'resume' : 'pause')">
                                        <v-icon>{{ attempt.individual_paused_at ? 'mdi-play-circle-outline' : 'mdi-pause-circle-outline' }}</v-icon>
                                        <v-tooltip activator="parent" location="top">{{ attempt.individual_paused_at ? 'Test fortsetzen' : 'Test pausieren' }} · {{ attempt.test_title }}</v-tooltip>
                                    </v-btn>
                                    <v-btn icon size="small" variant="text" :disabled="!!testActionBusy" :aria-label="`Zurücksetzen: ${student.name} · ${attempt.test_title}`"
                                        :title="`Test zurücksetzen · ${attempt.test_title}`" @click="askReset(attempt, student.name)">
                                        <v-icon>mdi-backup-restore</v-icon>
                                        <v-tooltip activator="parent" location="top">Test zurücksetzen · {{ attempt.test_title }}</v-tooltip>
                                    </v-btn>
                                </template>
                            </div>
                        </li>
                    </ul>
                </section>
                <v-alert v-if="opened === 'preparation' && portalRelease && studentTests.some(test => test.status === 'published' && test.paused_at)" type="warning" variant="tonal" class="mt-3">Der MC-Test ist pausiert. Schüler:innen können ihn derzeit weder starten noch fortsetzen.</v-alert>
                <div v-if="opened === 'preparation' && portalRelease && studentTests.length" class="mt-3 d-flex flex-wrap ga-2">
                    <template v-for="test in studentTests" :key="test.id">
                        <v-btn v-if="test.status === 'published' && test.paused_at" size="small" variant="tonal" prepend-icon="mdi-play" :disabled="!!testActionBusy" @click="setTestPause(test, false)">Alle fortsetzen · {{ test.title }}</v-btn>
                        <v-btn v-else-if="test.status === 'published' && test.hasOpenAttempts" size="small" variant="tonal" prepend-icon="mdi-pause" :disabled="!!testActionBusy" @click="setTestPause(test, true)">Alle pausieren · {{ test.title }}</v-btn>
                        <v-btn v-if="test.status === 'published'" size="small" variant="tonal" prepend-icon="mdi-restore" :disabled="!!testActionBusy || !studentAttempts.some(attempt => attempt.test_id === test.id)" @click="askResetAll(test)">Alle zurücksetzen · {{ test.title }}</v-btn>
                    </template>
                </div>
                <p v-if="opened === 'preparation' && portalRelease && studentTests.some(test => test.paused_at || test.hasOpenAttempts)" class="text-caption mt-2">Die Pause betrifft den gesamten jeweiligen MC-Test; währenddessen sind weitere Starts gesperrt.</p>
                <template v-if="opened === 'send'">
                    <p v-if="manuallyCompleted" class="mt-3">Der Versand ist manuell als erledigt markiert. Diese Markierung bestätigt keinen E-Mail-Versand.</p>
                    <p v-if="sent" class="mt-3">{{ dispatch.confirmed_count }} von {{ dispatch.expected_count }} Nachrichten bestätigt.</p>
                    <p v-if="dispatch.sent_at" class="mt-2">Versandt: {{ assessmentTimeText(dispatch.sent_at) }}</p>
                    <p v-if="sent && dispatch.transport === 'mailpit'" class="text-orange-darken-3 mt-2">Testversand über Mailpit. Die Nachrichten liegen ausschließlich im lokalen Mailpit.</p>
                    <p v-if="dispatch.last_protocol?.run" class="text-caption mt-2">Versandlauf: {{ dispatch.last_protocol.run }}</p>
                    <p class="mt-3">{{ sent || workHasProgress(work) ? nextActionText : 'E-Mails versenden oder diesen Schritt als erledigt markieren, wenn kein Versand erforderlich ist. Danach können die Abgaben eingesammelt werden.' }}</p>
                </template>
                <template v-else>
                    <p v-if="opened !== 'preparation' && opened !== 'collection'" class="mt-3">{{ selectedStage?.count }} von {{ selectedStage?.total }} Personen mit gespeichertem Nachweis.</p>
                    <p v-if="selectedStage?.detail && opened !== 'collection'" class="mt-2">{{ selectedStage.detail }}</p>
                    <p v-if="selectedStage?.manuallyCompleted && opened !== 'collection'" class="mt-3">Dieser Schritt ist manuell als erledigt markiert. Die Markierung bestätigt keine erstellten E-Mails, Bewertungen, bereitgestellten PDFs oder versandten Nachrichten. Vorhandene Nachweise, Zeitstempel und Punkte bleiben unverändert.</p>
                    <p v-if="opened === 'preparation' && !selectedStage?.manuallyCompleted" class="mt-3">Die E-Mail-Vorbereitung kann als erledigt markiert und die Markierung wieder aufgehoben werden. Damit werden keine E-Mails erstellt oder versandt.</p>
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
                <div v-if="opened === 'preparation' || opened === 'send'" class="d-flex flex-wrap ga-2 mt-4">
                    <WorkEmailPreparation :work="work" :date="date" :state="state" :disabled="savingCompletion" :action="opened === 'send' ? 'send' : 'prepare'" />
                    <WorkStudentDistribution v-if="opened === 'preparation' && !portalRelease" :work="work" :date="date" :available="exerciseReady || work.local_task_copy" :disabled="savingCompletion" @saved="emit('saved', $event)" />
                </div>
            </v-card-text>
            <v-card-actions class="d-flex flex-wrap ga-2" aria-label="Dialogaktionen">
                <v-btn v-if="opened === 'preparation' && portalRelease" color="warning" variant="tonal" :disabled="savingCompletion || !!testActionBusy" @click="withdrawConfirm = true">Freigabe zurücknehmen</v-btn>
                <WorkStudentDistribution v-if="opened === 'preparation' && portalRelease" :work="work" :date="date" :available="exerciseReady" :disabled="savingCompletion" @saved="emit('saved', $event)" />
                <WorkEmailPreparation v-if="opened === 'preparation'" :work="work" :date="date" :state="state" :disabled="savingCompletion" status-only>
                    <template #status-activator="{ open, available, label }">
                        <v-btn v-show="available" variant="tonal" prepend-icon="mdi-eye-outline" :title="label" @click.stop="open">Versandbereitschaft ansehen</v-btn>
                    </template>
                </WorkEmailPreparation>
                <v-btn v-if="opened === 'send' && (!sent || manuallyCompleted)" variant="tonal" :prepend-icon="manuallyCompleted ? 'mdi-undo' : 'mdi-check'" :loading="savingCompletion" :disabled="savingCompletion" @click="saveCompletion('task_dispatch_completed', !manuallyCompleted)">
                    {{ manuallyCompleted ? 'Markierung aufheben' : 'Als erledigt markieren' }}
                </v-btn>
                <v-btn v-if="selectedStage?.completionField" variant="tonal" :prepend-icon="selectedStage?.manuallyCompleted ? 'mdi-undo' : 'mdi-check'" :loading="savingCompletion" :disabled="savingCompletion || selectedStage.blocked" @click="saveCompletion(selectedStage.completionField, !selectedStage.manuallyCompleted)">
                    {{ selectedStage?.manuallyCompleted ? 'Markierung aufheben' : 'Als erledigt markieren' }}
                </v-btn>
                <WorkSubmissionCollection v-if="opened === 'collection' && !selectedStage?.simulation" :work="work" :date="date" :dispatch="dispatch" :repeat="(!selectedStage?.manuallyCompleted && selectedStage?.complete) || Boolean(lastCollectionAt)" :disabled="savingCompletion" />
                <v-btn v-if="opened === 'collection' && selectedStage?.simulation" variant="tonal" prepend-icon="mdi-flask-outline" to="/admin/teaching-tools/simulator">Simulator öffnen</v-btn>
                <WorkGradingAction v-if="opened === 'evaluation'" :work="work" :date="date" :repeat="selectedStage?.complete && !selectedStage?.manuallyCompleted"
                    :disabled="selectedStage?.blocked || savingCompletion || (!stages[0]?.complete && !lastCollectionAt)" />
                <v-btn v-if="opened === 'publication' && !selectedStage?.complete" variant="tonal" prepend-icon="mdi-account-check-outline" :disabled="selectedStage?.blocked || savingCompletion" @click="emit('import', work, false, { date }); opened = null">Ergebnisse übernehmen</v-btn>
                <WorkResultNotification v-if="opened === 'notification' && !selectedStage?.blocked && !savingCompletion" :work="work" :date="date" @saved="emit('saved', $event)" />
                <v-spacer />
                <v-btn :disabled="savingCompletion" @click="opened = null">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog v-model="resetConfirmation" max-width="520" :persistent="!!testActionBusy">
        <v-card>
            <v-card-title>MC-Test zurücksetzen?</v-card-title>
            <v-card-text>{{ resetTarget?.label }}: Alle bisherigen Antworten und Ergebnisse der ausgewählten Versuche werden gelöscht. Die Schüler:innen können den Test danach neu starten.</v-card-text>
            <v-card-actions><v-spacer /><v-btn :disabled="!!testActionBusy" @click="resetConfirmation = false">Abbrechen</v-btn>
                <v-btn color="error" variant="tonal" :loading="!!testActionBusy" @click="confirmReset">Zurücksetzen</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog v-model="withdrawConfirm" max-width="520" :persistent="withdrawing">
        <v-card>
            <v-card-title>Aufgabenfreigabe zurücknehmen?</v-card-title>
            <v-card-text>Die Aufgabe verschwindet aus dem Schülerbereich. Die Freigabe des MC-Tests bleibt bestehen.</v-card-text>
            <v-card-actions><v-spacer /><v-btn :disabled="withdrawing" @click="withdrawConfirm = false">Abbrechen</v-btn><v-btn color="warning" :loading="withdrawing" @click="withdrawRelease">Freigabe zurücknehmen</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { show, studentDistribution, update } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { showTest, pauseTest, resumeTest, controlAttempts } from '@/actions/App/Http/Controllers/Admin/MultipleChoiceController'
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
const emit = defineEmits(['import', 'saved'])
const manuallyCompleted = computed(() => workTaskDispatchCompleted(props.work))
const portalRelease = computed(() => workStudentDistribution(props.work))
const linkedTests = computed(() => {
    const tests = props.work.status?.multiple_choice_tests || {}
    return [...new Set([tests[props.date || props.work.date_for_all_groups], tests.all].map(test => test?.test_id).filter(Boolean))]
})
const releasedStudents = computed(() => {
    const roster = String(courseStore.selected_course?.id) === String(props.work.teaching_course_id) ? courseStore.selected_course?.students_info || [] : []
    return (portalRelease.value?.student_ids || []).map(id => {
        const person = roster.find(student => String(student.user_id ?? student.id) === String(id))
        return { id, name: person ? [person.last_name, person.first_name].filter(Boolean).join(' ') : 'Name nicht verfügbar' }
    }).sort((a, b) => a.name.localeCompare(b.name, 'de'))
})
const collectionStudents = computed(() => {
    const date = props.date || props.work.date_for_all_groups || ''
    const groups = (props.work.groups || []).filter(group => !date || !group.date || group.date === date)
    const ids = [...new Set(groups.flatMap(group => group.student_ids || []).map(String))]
    const roster = String(courseStore.selected_course?.id) === String(props.work.teaching_course_id) ? courseStore.selected_course?.students_info || [] : []
    return ids.map(id => {
        const person = roster.find(student => String(student.user_id ?? student.id) === id)
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
const studentAttempts = ref([])
const studentTests = ref([])
const testActionBusy = ref(null)
const resetConfirmation = ref(false)
const resetTarget = ref(null)
const withdrawConfirm = ref(false)
const withdrawing = ref(false)
const studentStatusLoading = ref(false)
const studentStatusError = ref('')
let studentStatusSequence = 0
function studentStatus(id) {
    if (!linkedTests.value.length) return 'Kein MC-Test'
    if (studentStatusLoading.value) return 'Wird geladen …'
    if (studentStatusError.value) return 'Status nicht verfügbar'
    const matches = studentAttempts.value.filter(attempt => String(attempt.student_id) === String(id))
    if (!matches.length) return 'Kein Test zugeordnet'
    const labels = { not_started: 'Noch nicht begonnen', ready: 'Bereit', in_progress: 'In Bearbeitung', submitted: 'Abgegeben', timeout: 'Zeit abgelaufen' }
    return matches.map(attempt => attempt.individual_paused_at ? 'Pausiert' : labels[attempt.status] || 'Status unbekannt').join(' · ')
}
function studentTestTimedOut(id) {
    return !studentStatusLoading.value && !studentStatusError.value && studentAttempts.value.some(attempt =>
        String(attempt.student_id) === String(id) && attempt.status === 'timeout' && !attempt.individual_paused_at)
}
function attemptsForStudent(id) {
    return studentAttempts.value.filter(attempt => String(attempt.student_id) === String(id)
        && studentTests.value.some(test => String(test.id) === String(attempt.test_id) && test.status === 'published'))
}
async function loadStudentStatuses() {
    const operation = ++studentStatusSequence
    studentAttempts.value = []
    studentTests.value = []
    studentStatusError.value = ''
    if (!linkedTests.value.length) return
    studentStatusLoading.value = true
    try {
        const responses = await Promise.all(linkedTests.value.map(id => axios.get(showTest.url(id))))
        if (operation === studentStatusSequence) {
            studentAttempts.value = responses.flatMap(response => (response.data.attempts || []).map(attempt => ({ ...attempt,
                test_id: response.data.test?.id, test_title: response.data.test?.title })))
            studentTests.value = responses.map(response => {
                return { ...response.data.test, hasOpenAttempts: (response.data.attempts || []).some(attempt => !attempt.completed_at && !['submitted', 'timeout'].includes(attempt.status)) }
            }).filter(test => test.id)
        }
    } catch {
        if (operation === studentStatusSequence) studentStatusError.value = 'Der MC-Test-Status konnte nicht geladen werden.'
    } finally {
        if (operation === studentStatusSequence) studentStatusLoading.value = false
    }
}
async function controlAttempt(attempt, action) {
    if (testActionBusy.value) return
    testActionBusy.value = attempt.test_id
    completionError.value = ''
    try {
        await axios.post(controlAttempts.url(attempt.test_id), { action, attempt_ids: [attempt.id] })
        await loadStudentStatuses()
    } catch (error) {
        completionError.value = error.response?.data?.message || 'Der MC-Testversuch konnte nicht geändert werden.'
    } finally { testActionBusy.value = null }
}
function askReset(attempt, name) {
    resetTarget.value = { testId: attempt.test_id, label: `${name} · ${attempt.test_title}`, ids: [attempt.id],
        expected: { [attempt.id]: attempt.control_revision }, all: false }
    resetConfirmation.value = true
}
function askResetAll(test) {
    const attempts = studentAttempts.value.filter(attempt => String(attempt.test_id) === String(test.id))
    resetTarget.value = { testId: test.id, label: `Alle ${attempts.length} Versuche · ${test.title}`,
        ids: attempts.map(attempt => attempt.id), expected: Object.fromEntries(attempts.map(attempt => [attempt.id, attempt.control_revision])), all: true }
    resetConfirmation.value = true
}
async function confirmReset() {
    if (testActionBusy.value || !resetTarget.value) return
    testActionBusy.value = resetTarget.value.testId
    completionError.value = ''
    try {
        await axios.post(controlAttempts.url(resetTarget.value.testId), { action: 'reset', attempt_ids: resetTarget.value.ids,
            all: resetTarget.value.all, expected: resetTarget.value.expected, confirmed: true })
        resetConfirmation.value = false
        await loadStudentStatuses()
    } catch (error) {
        resetConfirmation.value = false
        completionError.value = error.response?.data?.message || 'Die Versuche konnten nicht zurückgesetzt werden.'
        await loadStudentStatuses()
    } finally { testActionBusy.value = null }
}
async function setTestPause(test, paused) {
    if (testActionBusy.value) return
    testActionBusy.value = test.id
    completionError.value = ''
    try {
        await axios.post((paused ? pauseTest : resumeTest).url(test.id))
        await loadStudentStatuses()
    } catch (error) {
        completionError.value = error.response?.data?.message || 'Der MC-Test konnte nicht geändert werden.'
    } finally { testActionBusy.value = null }
}
async function withdrawRelease() {
    if (withdrawing.value) return
    withdrawing.value = true
    completionError.value = ''
    try {
        const response = await axios.get(show.url(props.work.id))
        const current = response.data?.data
        if (String(current?.id) !== String(props.work.id) || !current.student_distribution_version) throw new Error('Der aktuelle Stand konnte nicht bestätigt werden.')
        const data = new FormData()
        data.append('publish', '0')
        data.append('expected_version', current.student_distribution_version)
        const result = await axios.post(studentDistribution.url(props.work.id), data)
        emit('saved', result.data.data)
        withdrawConfirm.value = false
        opened.value = null
    } catch (error) {
        withdrawConfirm.value = false
        completionError.value = error.response?.data?.message || error.message || 'Die Freigabe konnte nicht zurückgenommen werden.'
    } finally { withdrawing.value = false }
}
const stages = computed(() => workProgressStages(props.work).map(stage => !portalRelease.value && !preparation.value.complete
    ? { ...stage, blocked: true, blockedBy: 'E-Mail-Vorbereitung' }
    : !hasSentTasks.value ? { ...stage, blocked: true, blockedBy: 'E-Mail-Versand' } : stage))
const stageLabel = stage => stage.blocked ? `${stage.label} · zuerst „${stage.blockedBy}“ abschließen`
    : stage.completedAt ? `${stage.label} · ${assessmentTimeText(stage.completedAt)}` : stage.label
const openStage = stage => { if (!props.disabled && !stage.blocked) opened.value = stage.key }
const lastCollectionAt = computed(() => workLastCollectionAt(props.work))
const lastCollectionRunAt = computed(() => workLastCollectionRunAt(props.work))
const simulationEvaluation = computed(() => workSimulationEvaluation(props.work))
const sortedSimulationRows = computed(() => sortedPeople(simulationEvaluation.value?.rows))
const preparation = computed(() => {
    const manuallyCompleted = props.work.status?.email_preparation_completed === true
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const date = props.date || props.work.date_for_all_groups || ''
    const cached = workExerciseCheck(key, props.work, date)?.data || workDropboxStates.get(key)
    const checked = cached?.exercise?.status === 'found' && (!date || cached.exercise.folder?.name?.startsWith(`${date}_`))
        ? cached.exercise.email_preparation : null
    const current = props.state?.status === 'found' && props.state.userId === admin.config?.user?.id
        && String(props.state.workId) === String(props.work.id) && String(props.state.courseId) === String(props.work.teaching_course_id)
        && props.state.date === props.date ? props.state.emailPreparation : null
    const ready = (current || checked)?.status === 'ready'
    return { title: 'E-Mail-Vorbereitung', completionField: workManualCompletionFields.preparation, manuallyCompleted,
        complete: manuallyCompleted || ready, label: `E-Mail-Vorbereitung: ${manuallyCompleted ? 'manuell erledigt' : ready ? 'vorbereitet' : 'noch offen'}` }
})
const sendBlocked = computed(() => !preparation.value.complete)
const selectedStage = computed(() => opened.value === 'preparation' ? preparation.value : stages.value.find(stage => stage.key === opened.value))
const nextAction = computed(() => workProgressNextAction(props.work) || (preparation.value.complete ? 'E-Mails versenden' : opened.value === 'preparation' ? 'E-Mails vorbereiten' : 'Abgaben einsammeln'))
const nextActionText = computed(() => nextAction.value === 'Abgeschlossen' ? 'Status: ABGESCHLOSSEN.' : `Nächster Schritt: ${nextAction.value}.`)
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
const sent = computed(() => dispatch.value.status === 'sent')
const hasSentTasks = computed(() => Boolean(portalRelease.value) || manuallyCompleted.value || (['sent', 'partial'].includes(dispatch.value.status) && Number(dispatch.value.confirmed_count) > 0))
watch(stages, value => { if (value.find(stage => stage.key === opened.value)?.blocked) opened.value = null })
watch(sendBlocked, blocked => { if (blocked && opened.value === 'send') opened.value = null })
const sendLabel = computed(() => sent.value ? `E-Mails versandt${dispatch.value.transport === 'mailpit' ? ' · Testversand über Mailpit' : ''}`
    : manuallyCompleted.value ? 'E-Mail-Versand: manuell erledigt'
    : dispatch.value.status === 'partial' ? `E-Mail-Versand unvollständig: ${dispatch.value.confirmed_count} bestätigt`
        : dispatch.value.status === 'unknown' ? 'E-Mail-Versand nicht bestätigt – erneut prüfen' : 'E-Mails noch nicht versandt')

watch(opened, value => {
    completionError.value = ''
    if (value === 'collection' || value === 'evaluation' || (value === 'preparation' && portalRelease.value)) loadStudentStatuses()
    else { studentStatusSequence++; studentStatusLoading.value = false }
})
watch(() => [props.work.id, props.work.teaching_course_id, props.date, linkedTests.value.join(',')], () => {
    if (opened.value === 'collection' || opened.value === 'evaluation' || (opened.value === 'preparation' && portalRelease.value)) loadStudentStatuses()
    else { studentStatusSequence++; studentStatusLoading.value = false }
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
