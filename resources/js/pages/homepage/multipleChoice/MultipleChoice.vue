<template>
    <main class="mc-page">
        <StudentNavigationDrawer v-if="!isPublic" v-model="showDrawer" current-route="multiple-choice" />
        <header class="mc-header">
            <div>
                <p class="mc-breadcrumb">Dein Lernraum</p>
                <h1>Multiple Choice</h1>
            </div>
            <button v-if="!isPublic" class="mc-button mc-button-secondary" @click="showDrawer = true">Mein Menü</button>
        </header>

        <div v-if="error" class="mc-alert" role="alert">
            <p>{{ error }}</p>
            <button class="mc-button mc-button-secondary" :disabled="retrySeconds > 0" @click="refresh(true)">{{ retrySeconds > 0 ? `Automatisch erneut verbinden in ${retrySeconds} Sekunden` : 'Erneut verbinden' }}</button>
            <a v-if="!isPublic && !attempt" href="/student" class="mc-login">Zur Schüleranmeldung</a>
        </div>
        <p v-if="loading" role="status" class="mc-empty">Deine Tests werden geladen …</p>

        <section v-else-if="!attempt" aria-labelledby="mc-tests-title">
            <h2 id="mc-tests-title">Deine Tests</h2>
            <p class="mc-description">Öffne einen zugewiesenen Test. Deine Fragen und gespeicherten Antworten bleiben bei jedem Einstieg erhalten.</p>
            <p v-if="!attempts.length && !error" class="mc-empty">Dir sind derzeit keine Tests zugewiesen.</p>
            <ul class="mc-test-list">
                <li v-for="item in attempts" :key="item.id" class="mc-test-row">
                    <div><h3>{{ item.title }}</h3><p>{{ item.question_count }} Fragen · {{ formatNumber(item.total_points) }} Punkte · {{ minutes(item.duration_seconds) }} Minuten</p></div>
                    <span class="mc-status">{{ statusLabel(item) }}</span>
                    <button class="mc-button" @click="openAttempt(item.id)">Test öffnen</button>
                </li>
            </ul>
        </section>

        <template v-else>
            <button v-if="!isPublic && !hasPending && !saving && !submitting" class="mc-back" @click="backToTests">Alle Tests</button>
            <section class="mc-test-heading" aria-labelledby="mc-current-title">
                <h2 id="mc-current-title">{{ attempt.title }}</h2>
                <dl class="mc-facts">
                    <div><dt>Fragen</dt><dd>{{ attempt.question_count }}</dd></div>
                    <div><dt>Gesamtpunkte</dt><dd>{{ formatNumber(attempt.total_points) }}</dd></div>
                    <div><dt>Bearbeitungsdauer</dt><dd>{{ minutes(attempt.duration_seconds) }} Minuten</dd></div>
                    <div v-if="attempt.start_mode === 'individual'"><dt>Schlussfrist</dt><dd>{{ deadlineLabel(attempt.closes_at) }}</dd></div>
                </dl>
            </section>

            <div v-if="examEnabled && !finished" class="mc-exam-banner" role="status">
                <div><strong>Prüfungsmodus</strong><p>Seitenwechsel, Fokusverlust, deren Häufigkeit und Dauer sowie das Verlassen des Vollbilds werden deiner Lehrperson angezeigt. Dein Test läuft dabei weiter.</p></div>
                <span v-if="fullscreen">Vollbild aktiv</span>
                <p v-if="monitoringError" class="mc-save-warning" role="alert">{{ monitoringError }}</p>
            </div>
            <div v-if="attempt.paused_at && !finished && !attempt.teacher_preview" class="mc-pause-banner" role="status"><strong>Der Test ist pausiert.</strong><p>Deine aktuelle Frage und Antworten bleiben erhalten. Eingaben und Restzeit sind eingefroren, bis deine Lehrperson den Test fortsetzt.</p></div>
            <section v-if="attempt.teacher_preview" aria-label="Schreibgeschützte Lehrkraft-Vorschau">
                <div class="mc-preview-banner" role="status">
                    <strong>Lehrkraft-Vorschau für {{ attempt.student_name }}</strong>
                    <p>Schreibgeschützt: Sie sehen die zugeteilten Fragen und die auf dem Server gespeicherten Antworten. Der Versuch wird dadurch weder gestartet noch verändert.</p>
                </div>
                <div class="mc-questions">
                    <article v-for="(question, position) in attempt.questions" :key="question.id" class="mc-question mc-preview-question">
                        <h3>{{ position + 1 }}. {{ question.prompt }}</h3>
                        <div v-for="option in question.options" :key="option.id" class="mc-option" :class="{ selected: selected(question.id, option.id) }">
                            <span>{{ selected(question.id, option.id) ? '✓ ' : '○ ' }}{{ option.text }}</span>
                        </div>
                    </article>
                </div>
            </section>
            <section v-else-if="finished" class="mc-finished" aria-labelledby="mc-finished-title">
                <h2 id="mc-finished-title">{{ neverStarted ? 'Nicht begonnen' : attempt.status === 'timeout' ? 'Die Bearbeitungszeit ist vorbei.' : 'Dein Test ist abgegeben.' }}</h2>
                <p v-if="neverStarted">Du hast diesen Test nicht begonnen. Die verfügbare Bearbeitungszeit ist abgelaufen. Es wird kein Punkteergebnis angezeigt.</p>
                <p v-else>Deine gespeicherten Antworten wurden eingesammelt.</p>
                <p v-if="expiredPending" class="mc-save-warning">Einige zuletzt auf diesem Gerät geänderte Antworten konnten vor Ablauf der Zeit nicht gespeichert werden. Gewertet werden die auf dem Server gespeicherten Antworten.</p>
                <div v-if="!neverStarted && attempt.result" class="mc-result">
                    <strong>{{ formatNumber(attempt.result.score) }} / {{ formatNumber(attempt.total_points) }} Punkte</strong>
                    <p>{{ formatNumber(attempt.result.percentage) }} % · {{ attempt.result.correct_count }} von {{ attempt.question_count }} Fragen richtig</p>
                    <div v-if="attempt.result.question_results?.length" class="mc-review">
                        <h3>Deine Frageauswertung</h3>
                        <p v-if="!attempt.result.solutions_released" class="mc-review-note">Du siehst deine Fragen, deine Auswahl und die Bewertung. Die Musterlösungen sind noch nicht freigegeben.</p>
                        <McQuestionReview v-for="(result, position) in attempt.result.question_results" :key="result.question_id || position"
                            :question="reviewQuestion(result)" :position="position" />
                    </div>
                </div>
                <p v-else-if="!neverStarted">Deine Lehrperson gibt Ergebnisse und Lösungen gesondert frei. Du kannst später hier wieder nachsehen.</p>
            </section>

            <section v-else-if="examEnabled && !fullscreen" class="mc-fullscreen-gate" aria-labelledby="mc-fullscreen-title">
                <h2 id="mc-fullscreen-title">Vollbild ist für diesen Test erforderlich.</h2>
                <p>Die Prüfungsfragen werden erst im Vollbild angezeigt. Beim Verlassen des Vollbilds bleiben deine Antworten erhalten und die Fragen werden ausgeblendet.</p>
                <p v-if="attempt.started_at">Deine Bearbeitungszeit läuft weiter. Eine Pause kann nur deine Lehrperson auslösen.</p>
                <button v-if="fullscreenAvailable" class="mc-button" @click="enterFullscreen">Vollbild starten</button>
                <p v-else class="mc-save-warning">Dieser Browser unterstützt den benötigten Vollbildmodus nicht. Verwende einen Browser mit Vollbildunterstützung oder wende dich an deine Lehrperson.</p>
            </section>
            <section v-else-if="!active && !attempt.paused_at" class="mc-waiting" aria-labelledby="mc-wait-title">
                <template v-if="attempt.ends_at && remainingSeconds === 0">
                    <h2 id="mc-wait-title">Die Bearbeitungszeit ist vorbei.</h2>
                    <p>Antworten können nicht mehr geändert werden. Der Server schließt deinen Versuch ab.</p>
                </template>
                <template v-else-if="attempt.start_mode === 'individual' && !attempt.started_at">
                    <h2 id="mc-wait-title">Du bestimmst den Start.</h2>
                    <p>Du kannst diesen Test einmal starten. Ab deinem Klick hast du {{ minutes(attempt.duration_seconds) }} Minuten. Die Zeit läuft auch weiter, wenn du die Seite verlässt.</p>
                    <p><strong>Spätestes Testende: {{ deadlineLabel(attempt.closes_at) }}</strong></p>
                    <p class="mc-description">Bei einem späten Start bleibt weniger Zeit. Nach der Schlussfrist oder nach Ablauf deiner Bearbeitungszeit sind keine Änderungen mehr möglich.</p>
                    <button class="mc-button" :disabled="submitting" @click="startAttempt">{{ submitting ? 'Test wird gestartet …' : 'Test jetzt starten' }}</button>
                </template>
                <template v-else>
                    <h2 id="mc-wait-title">{{ attempt.starts_at ? 'Gleich geht es los.' : 'Willkommen im Warteraum.' }}</h2>
                    <div v-if="attempt.starts_at" class="mc-countdown" role="timer" aria-label="Countdown bis zum gemeinsamen Start">
                        <span>Start in</span><strong>{{ countdownSeconds }}</strong><span>Sekunden</span>
                    </div>
                    <p v-else>Deine Lehrperson startet den Test gemeinsam mit der Klasse. Die Fragen werden dann automatisch angezeigt.</p>
                    <p v-if="attempt.ready_at" class="mc-ready" role="status">Du bist bereit. Lass diese Seite geöffnet.</p>
                    <button v-else class="mc-button" :disabled="submitting" @click="markReady">Ich bin bereit</button>
                    <p class="mc-description">Der Countdown zählt nicht zur Bearbeitungszeit. Ein späterer Einstieg verlängert die gemeinsame Endzeit nicht.</p>
                </template>
            </section>

            <section v-else class="mc-workspace" aria-label="Test bearbeiten">
                <aside class="mc-sidebar">
                    <div class="mc-clock" :class="{ 'mc-clock-urgent': remainingSeconds < 60 }" role="timer" aria-label="Verbleibende Bearbeitungszeit">
                        <span>Verbleibende Zeit</span><strong>{{ clock(remainingSeconds) }}</strong>
                    </div>
                    <p class="mc-progress">{{ answeredCount }} von {{ attempt.question_count }} beantwortet</p>
                    <progress :value="answeredCount" :max="attempt.question_count" aria-label="Beantwortete Fragen" />
                    <nav class="mc-question-nav" aria-label="Fragenübersicht">
                        <button v-for="(question, position) in attempt.questions" :key="question.id"
                            :class="{ answered: answers[question.id]?.length }" :aria-current="currentQuestion === position ? 'step' : undefined"
                            :aria-label="`Frage ${position + 1}${answers[question.id]?.length ? ', beantwortet' : ', noch offen'}`" :disabled="!active"
                            @click="goToQuestion(position)">{{ position + 1 }}</button>
                    </nav>
                    <p class="mc-save-state" :class="{ 'mc-save-warning': hasPending || saveError }" role="status" aria-live="polite">
                        {{ attempt.paused_at ? 'Pausiert. Vorgemerkte Änderungen bleiben erhalten.' : saveError ? 'Noch nicht gespeichert. Antworten bleiben auf diesem Gerät vorgemerkt.' : saving ? 'Wird gespeichert …' : hasPending ? 'Änderungen noch nicht gespeichert …' : 'Alle Antworten gespeichert.' }}
                    </p>
                    <p v-if="storageUnavailable" class="mc-save-warning">Der lokale Zwischenspeicher ist nicht verfügbar. Schließe diese Seite erst, wenn alle Antworten gespeichert sind.</p>
                    <button v-if="hasPending || saveError" class="mc-button mc-button-secondary" :disabled="saving || !active" @click="flush">Speichern erneut versuchen</button>
                </aside>

                <div class="mc-questions">
                    <template v-for="(question, position) in attempt.questions" :key="question.id">
                        <fieldset v-if="currentQuestion === position" :id="`mc-question-${position}`" class="mc-question" :disabled="submitting || !active">
                            <legend><span class="mc-question-number">Frage {{ position + 1 }}</span><span class="mc-prompt">{{ question.prompt || question.text }}</span></legend>
                            <p class="mc-answer-hint">{{ isMultiple(question) ? 'Mehrere Antworten sind richtig. Wähle alle richtigen Antworten aus.' : 'Eine Antwort ist richtig. Wähle genau eine Antwort aus.' }}</p>
                            <label v-for="option in question.options" :key="option.id" class="mc-option" :class="{ selected: selected(question.id, option.id) }">
                                <input :type="isMultiple(question) ? 'checkbox' : 'radio'" :name="`question-${question.id}`" :value="option.id" :checked="selected(question.id, option.id)"
                                    @focus="currentQuestion = position" @change="selectAnswer(question, option.id, $event.target.checked)" />
                                <span>{{ option.text || option.label }}</span>
                            </label>
                        </fieldset>
                    </template>
                    <nav class="mc-question-actions" aria-label="Frage wechseln">
                        <button class="mc-button mc-button-secondary" :disabled="currentQuestion === 0 || submitting || !active" @click="goToQuestion(currentQuestion - 1)">Zurück</button>
                        <span>{{ currentQuestion + 1 }} von {{ attempt.question_count }}</span>
                        <button v-if="currentQuestion < attempt.questions.length - 1" class="mc-button" :disabled="submitting || !active" @click="goToQuestion(currentQuestion + 1)">Weiter</button>
                        <button v-else class="mc-button" :disabled="submitting || !active" @click="showSubmitDialog = true">Abgabe prüfen</button>
                    </nav>
                </div>
            </section>
        </template>

        <dialog ref="submitDialog" class="mc-dialog" aria-labelledby="mc-submit-title" @cancel="showSubmitDialog = false" @close="showSubmitDialog = false">
            <form @submit.prevent="confirmSubmit">
                <h2 id="mc-submit-title">Test verbindlich abgeben?</h2>
                <p>{{ answeredCount }} von {{ attempt?.question_count }} Fragen sind beantwortet. Nach der Abgabe kannst du nichts mehr ändern.</p>
                <p v-if="error" class="mc-save-warning" role="alert">{{ error }}</p>
                <div class="mc-dialog-actions">
                    <button type="button" class="mc-button mc-button-secondary" :disabled="submitting || !active" @click="showSubmitDialog = false">Weiterarbeiten</button>
                    <button class="mc-button" :disabled="submitting">{{ submitting ? 'Abgabe läuft …' : 'Jetzt abgeben' }}</button>
                </div>
            </form>
        </dialog>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStudentStore } from '@/stores/student/StudentStore'
import StudentNavigationDrawer from '../student/components/StudentNavigationDrawer.vue'
import McQuestionReview from '@/components/McQuestionReview.vue'
import { useMultipleChoiceSession } from './useMultipleChoiceSession'

const route = useRoute()
const router = useRouter()
const { exam, retrySeconds, attempts, attempt, answers, loading, saving, submitting, error, saveError, storageUnavailable, expiredPending,
    finished, hasPending, active, canAnswer, remainingSeconds, countdownSeconds, answeredCount, isPublic,
    load, openAttempt, markReady, startAttempt, selectAnswer, flush, submitAttempt, refresh } = useMultipleChoiceSession(route)
const showDrawer = ref(false)
const currentQuestion = ref(0)
const showSubmitDialog = ref(false)
const submitDialog = ref(null)
const { enabled: examEnabled, fullscreen, fullscreenAvailable, monitoringError, enterFullscreen } = exam
const neverStarted = computed(() => attempt.value?.status === 'timeout' && !attempt.value?.started_at)

onMounted(() => {
    if (!isPublic.value) {
        const studentStore = useStudentStore()
        if (!studentStore.user) {
            void studentStore.getCurrentUser()
        }
    }
})

watch(showSubmitDialog, async (value) => {
    await nextTick()
    if (value && !submitDialog.value?.open) {
        submitDialog.value?.showModal()
    } else if (!value) {
        submitDialog.value?.close()
    }
})
watch(finished, value => { if (value) { showSubmitDialog.value = false } })
watch(canAnswer, value => { if (!value) { showSubmitDialog.value = false } })
watch(() => attempt.value?.id, () => { currentQuestion.value = 0 })

function formatNumber(value) {
    return new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(Number(value || 0))
}

function deadlineLabel(value) {
    return value ? new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—'
}

function minutes(seconds) {
    return formatNumber(Number(seconds || 0) / 60)
}

function clock(seconds) {
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

function statusLabel(item) {
    if (item.status === 'timeout' && !item.started_at) {
        return 'Nicht begonnen'
    }
    return { not_started: 'Noch nicht begonnen', ready: 'Bereit', in_progress: 'In Bearbeitung',
        submitted: 'Abgegeben', timeout: 'Zeit abgelaufen' }[item.status] || 'Zugewiesen'
}

function isMultiple(question) {
    return (question.type || question.answer_type) === 'multiple'
}

function selected(questionId, optionId) {
    return (answers.value[questionId] || []).some(value => String(value) === String(optionId))
}

async function goToQuestion(position) {
    if (!canAnswer.value || position < 0 || position >= (attempt.value?.questions?.length || 0)) {
        return
    }
    currentQuestion.value = position
    await nextTick()
    const target = document.getElementById(`mc-question-${position}`)
    target?.scrollIntoView?.({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' })
    target?.querySelector('input')?.focus({ preventScroll: true })
}

async function confirmSubmit() {
    if (await submitAttempt()) {
        showSubmitDialog.value = false
    }
}

async function backToTests() {
    exam.leave()
    if (route.params.attempt) {
        await router.push('/student/multiple-choice')
    } else {
        await load()
    }
}

function resultQuestion(result) {
    return attempt.value.questions?.find(item => String(item.id) === String(result.question_id))
}

function reviewQuestion(result) {
    return { ...resultQuestion(result), ...result }
}
</script>

<style scoped>
.mc-fullscreen-gate { margin: 24px 0; padding: 28px; border: 1px solid #cfdfda; border-radius: 14px; background: #fff; }
.mc-fullscreen-gate h2 { margin-top: 0; }
.mc-fullscreen-gate p { line-height: 1.6; margin: 12px 0 20px; }
.mc-exam-banner, .mc-pause-banner { padding: 18px 22px; margin: 18px 0; border: 1px solid #cfdfda; border-radius: 12px; background: #eff6f3; display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
.mc-exam-banner > div { flex: 1 1 360px; }
.mc-exam-banner p, .mc-pause-banner p { margin: 6px 0 0; }
.mc-pause-banner { display: block; background: #fff5df; border-color: #e4c991; }

.mc-preview-banner { padding: 18px 22px; border: 1px solid #cbded6; border-radius: 14px; background: #eef6f2; margin-bottom: 20px; }
.mc-preview-banner p { margin-top: 8px; line-height: 1.6; }
.mc-preview-question h3 { margin-bottom: 18px; line-height: 1.5; }
.mc-preview-question .mc-option { cursor: default; }

.mc-page { --mc-blue: #4056d6; --mc-ink: #18243b; --mc-muted: #536176; max-width: 1260px; padding: 36px 32px 64px; margin: 0 auto; color: var(--mc-ink); }
.mc-header, .mc-test-row, .mc-dialog-actions, .mc-question-actions { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.mc-header { margin-bottom: 34px; }
.mc-breadcrumb { color: var(--mc-muted); font-size: .9rem; margin: 0 0 4px; }
h1 { font-size: clamp(1.65rem, 3vw, 2.25rem); line-height: 1.2; }
h2 { font-size: 1.4rem; line-height: 1.4; margin-bottom: 12px; }
h3 { font-size: 1.05rem; }
p { line-height: 1.65; }
.mc-description { color: var(--mc-muted); max-width: 75ch; margin: 12px 0; }
.mc-button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 10px 20px; background: var(--mc-blue); border: 1px solid var(--mc-blue); border-radius: 10px; color: white; font: inherit; font-weight: 650; cursor: pointer; }
.mc-button-secondary { color: #3446af; background: #f1f3fc; border-color: #d9dff8; }
.mc-button:disabled { opacity: .6; cursor: wait; }
button:focus-visible, input:focus-visible { outline: 3px solid #273bb8; outline-offset: 4px; }
.mc-test-list { list-style: none; padding: 0; margin-top: 26px; }
.mc-test-row { padding: 24px 0; border-bottom: 1px solid #dce2ed; }
.mc-test-row > div { flex: 1; }
.mc-test-row p { margin-top: 6px; color: var(--mc-muted); }
.mc-status { font-size: .9rem; color: var(--mc-muted); }
.mc-empty { padding: 44px 20px; text-align: center; color: var(--mc-muted); background: #f5f7fb; border-radius: 12px; margin: 24px 0; }
.mc-alert { background: #fff2df; border-left: 4px solid #b97915; padding: 16px 20px; margin-bottom: 24px; }
.mc-alert .mc-button { margin-top: 8px; }
.mc-login { margin-left: 18px; color: #3446af; text-decoration: underline; }
.mc-back { color: #3446af; text-decoration: underline; text-underline-offset: 4px; margin-bottom: 18px; cursor: pointer; }
.mc-test-heading { padding-bottom: 28px; border-bottom: 1px solid #dce2ed; }
.mc-facts { display: flex; gap: 24px 48px; flex-wrap: wrap; margin-top: 18px; }
.mc-facts dt { color: var(--mc-muted); font-size: .85rem; }
.mc-facts dd { font-weight: 650; font-size: 1.1rem; margin-top: 4px; }
.mc-waiting, .mc-finished { padding: 44px 0; max-width: 780px; }
.mc-countdown { display: flex; align-items: baseline; gap: 14px; padding: 16px 0; }
.mc-countdown strong { font-size: 4rem; font-variant-numeric: tabular-nums; color: var(--mc-blue); }
.mc-ready { margin: 20px 0; color: #226149; font-weight: 650; }
.mc-workspace { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 48px; padding-top: 30px; align-items: start; }
.mc-sidebar { position: sticky; top: 90px; }
.mc-clock { padding: 18px; background: #eef1fd; color: #293da9; border-radius: 12px; }
.mc-clock span { font-size: .85rem; display: block; }
.mc-clock strong { display: block; font-size: 2.5rem; line-height: 1.2; font-variant-numeric: tabular-nums; margin-top: 4px; }
.mc-clock-urgent { background: #fff1e9; color: #9b3d1b; }
.mc-progress { margin: 20px 0 6px; font-size: .9rem; }
progress { width: 100%; height: 8px; accent-color: var(--mc-blue); }
.mc-question-nav { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 20px 0; }
.mc-question-nav button { min-height: 42px; border: 1px solid #c5cede; border-radius: 8px; cursor: pointer; color: #40506b; background: white; }
.mc-question-nav button.answered { background: #e9f6ef; border-color: #6eac8d; color: #225a3f; }
.mc-question-nav button[aria-current] { box-shadow: 0 0 0 2px var(--mc-blue); }
.mc-save-state { font-size: .85rem; color: #226149; margin-bottom: 14px; }
.mc-save-warning { color: #86531a; font-size: .85rem; }
.mc-question { min-width: 0; border: 0; border-bottom: 1px solid #dce2ed; padding: 0 0 30px; margin: 0 0 32px; scroll-margin-top: 95px; }
.mc-question legend { float: left; width: 100%; margin-bottom: 12px; }
.mc-question legend + * { clear: both; }
.mc-question-number { display: block; font-size: .9rem; color: var(--mc-blue); font-weight: 650; margin-bottom: 9px; }
.mc-prompt { font-weight: 650; font-size: 1.2rem; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
.mc-answer-hint { color: var(--mc-muted); font-size: .88rem; margin-bottom: 18px; }
.mc-option { display: flex; align-items: flex-start; gap: 15px; padding: 17px 18px; margin-top: 10px; border: 1px solid #cbd4e2; border-radius: 10px; cursor: pointer; background: white; min-height: 60px; }
.mc-option.selected { background: #eef1fd; border-color: var(--mc-blue); }
.mc-option input { flex: 0 0 auto; width: 20px; height: 20px; accent-color: var(--mc-blue); margin-top: 2px; }
.mc-option span { line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.mc-question-actions > span { color: var(--mc-muted); font-size: .9rem; white-space: nowrap; }
.mc-question-actions .mc-button:disabled { cursor: default; }
.mc-result { margin-top: 25px; }
.mc-result > strong { font-size: 2rem; color: #245d49; }
.mc-review { margin-top: 32px; }
.mc-review > h3 { margin-bottom: 18px; }
.mc-review-note { color: var(--mc-muted); margin: -6px 0 20px; max-width: 75ch; }
.mc-dialog { border: 0; border-radius: 16px; padding: 28px; max-width: 550px; width: calc(100% - 32px); color: var(--mc-ink); margin: auto; }
.mc-dialog::backdrop { background: rgb(24 36 59 / .45); }
.mc-dialog-actions { margin-top: 24px; justify-content: flex-end; flex-wrap: wrap; }
@media (max-width: 1000px) { .mc-workspace { grid-template-columns: 180px minmax(0, 1fr); gap: 28px; } }
@media (min-width: 701px) and (max-height: 850px) {
    .mc-page { padding-top: 24px; }
    .mc-header { margin-bottom: 20px; }
    .mc-test-heading { padding-bottom: 18px; }
    .mc-facts { margin-top: 14px; }
    .mc-workspace { padding-top: 24px; }
    .mc-question { padding-bottom: 20px; margin-bottom: 20px; }
    .mc-option { min-height: 52px; padding: 12px 18px; margin-top: 8px; }
}
@media (max-width: 700px) {
    .mc-page { padding: 24px 18px 48px; }
    .mc-workspace { display: block; }
    .mc-sidebar { position: static; margin-bottom: 28px; }
    .mc-clock { display: flex; align-items: center; justify-content: space-between; }
    .mc-clock strong { font-size: 2rem; margin: 0; }
    .mc-question-nav { grid-template-columns: repeat(auto-fill, minmax(42px, 1fr)); }
    .mc-test-row { align-items: flex-start; flex-direction: column; gap: 12px; }
    .mc-question-actions { gap: 10px; }
    .mc-question-actions .mc-button { padding: 10px 12px; }
    .mc-facts { gap: 18px 25px; }
}
</style>
