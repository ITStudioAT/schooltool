<template>
    <div class="ns-game">
        <NumberSystemReward :reward="reward" :motion="probeId ? 'full' : 'auto'" :sound-label="soundLabel" :sound-active="soundEnabled && soundReady" :sound-available="sound.available" @dismiss="dismissReward" @replay="restartReward" @toggle-sound="toggleSound" />
        <div class="ns-toolbar ns-player-top">
            <div><h1>Signal Null</h1><p class="ns-muted">{{ run?.session.title || 'Verbindung zum Rechenzentrum' }}</p></div>
            <div class="ns-toolbar ns-player-actions">
                <button v-if="sound.available" type="button" class="ns-button ns-button-quiet" :aria-pressed="soundEnabled && soundReady" @click="toggleSound">{{ soundLabel }}</button>
                <div v-if="probeId" class="ns-reward-previews" role="group" aria-label="Erfolgsanimationen ansehen">
                    <span class="ns-small ns-muted">Vorschau</span>
                    <button v-for="(chapter, index) in chapters" :key="chapter" type="button" class="ns-button ns-button-quiet ns-preview-button" :disabled="!run" @click="previewReward(index)"><span aria-hidden="true">▶</span>{{ chapter }}</button>
                    <button type="button" class="ns-button ns-button-quiet ns-preview-button" :disabled="!run" @click="previewReward(3, true)"><span aria-hidden="true">▶</span>Finale</button>
                </div>
                <button type="button" class="ns-button ns-button-quiet" @click="$emit('back')">Meine Abenteuer</button>
            </div>
        </div>
        <div v-if="error" class="ns-alert ns-alert-error" role="alert">
            <p>{{ error }}</p>
            <button class="ns-button ns-button-secondary" style="margin-top:10px" :disabled="busy || saving" @click="conflict ? load(true) : run ? flush() : load()">
                {{ conflict ? 'Gespeicherten Stand laden' : 'Erneut verbinden / speichern' }}
            </button>
        </div>
        <p v-if="loading" role="status">Spielstand wird geladen …</p>
        <template v-else-if="run">
            <NumberSystemClock :clock="run.clock" style="margin:18px 0" />
            <div v-if="run.team_name && !probeId" class="ns-small ns-muted">Team {{ run.team_name }} · {{ run.members.join(', ') }}<br />Teamcode für eure weiteren Mitglieder: <strong class="ns-code ns-code-small">{{ run.team_code }}</strong></div>
            <details v-if="run.invitation && !probeId" class="ns-panel" style="margin-top:16px"><summary>Team einladen · QR-Code anzeigen</summary><NumberSystemInvitation :invitation="run.invitation" title="Deinem privaten Team beitreten" private-team /></details>
            <ol class="ns-path" aria-label="Rechenzentrum wiederherstellen">
                <li v-for="(chapter, index) in chapters" :key="chapter" :class="{ 'ns-restored': run.completed || index < run.mission?.chapter, 'ns-current': !run.completed && index === run.mission?.chapter }">
                    {{ run.completed || index < run.mission?.chapter ? '✓ ' : '' }}{{ chapter }}
                </li>
            </ol>
            <div v-if="run.completed" class="ns-panel ns-stack" role="status">
                <div class="ns-finale-emblem" aria-hidden="true">✓</div>
                <h2 class="ns-gold">Geschafft! Das Rechenzentrum ist wieder am Netz.</h2>
                <p>Alle {{ run.total }} Aufgaben sind gelöst. Ihr habt Zahlen umgerechnet, Fehler gefunden und Nachrichten lesbar gemacht.</p>
                <p>{{ independent }} Aufgaben ohne Tipp · {{ run.history.length - independent }} mit Tipp. Tipps zu nutzen gehört zum Lernen und kostet keine Punkte.</p>
                <ul class="ns-achievements ns-achievements-finale" aria-label="Deine gesammelten Abzeichen"><li v-for="chapter in chapters" :key="chapter" class="ns-earned"><span aria-hidden="true">✓</span><span>{{ chapter }}<small>Geschafft</small></span></li></ul>
                <ul class="ns-checks"><li v-for="direction in learnedDirections" :key="direction">✓ {{ directionLabels[direction] }}</li></ul>
                <p class="ns-muted">Besprecht zum Abschluss: Welche Regel hat euch am meisten geholfen? Kann ein Text noch lesbar sein, obwohl jemand einen Buchstaben verändert hat?</p>
                <button class="ns-button" @click="$emit('back')">Zur Abenteuerübersicht</button>
            </div>
            <template v-else>
                <div v-if="run.session.status === 'waiting'" class="ns-panel ns-stack" role="status">
                    <h2>Die Verbindung steht. Gleich geht es los.</h2><p>Deine Lehrkraft startet die Runde für den Kurs. Du kannst diese Seite geöffnet lassen.</p>
                </div>
                <div v-else-if="run.session.status === 'ended'" class="ns-alert" role="status">Die Lehrkraft hat die Runde beendet. {{ run.history.length }} von {{ run.total }} Missionen bleiben als Fortschritt gespeichert.</div>
                <div v-else-if="run.session.status === 'paused'" class="ns-alert" role="status">Pause im Rechenzentrum. Dein Entwurf bleibt erhalten; neue Lösungen werden nach dem Fortsetzen geprüft.</div>
                <div v-if="run.mission" class="ns-player-grid">
                    <section class="ns-mission ns-panel" :aria-label="run.mission.title">
                        <p class="ns-small ns-muted">{{ run.mission.chapter_title }} · Aufgabe {{ run.position + 1 }} von {{ run.total }}</p>
                        <h2 ref="missionTitle" tabindex="-1" style="margin-top:8px">{{ run.mission.title }}</h2>
                        <p class="ns-prompt">{{ run.mission.prompt }}</p>
                        <ol v-if="run.mission.instructions?.length" class="ns-instructions"><li v-for="instruction in run.mission.instructions" :key="instruction">{{ instruction }}</li></ol>
                        <form @submit.prevent="submitAnswer">
                            <NumberSystemPuzzle :mission="run.mission" :model-value="draft" :disabled="!canPlay || busy || conflict" @update:model-value="edit" />
                            <div class="ns-toolbar" style="margin-top:24px">
                                <button v-if="!run.solved" type="submit" class="ns-button" :disabled="!canPlay || busy || saving || conflict">{{ busy ? 'Antwort wird geprüft …' : 'Antwort prüfen' }}</button>
                                <button v-else type="button" class="ns-button" :disabled="busy || run.session.status !== 'running'" @click="act('advance')">
                                    {{ run.position === run.total - 1 ? 'Rechenzentrum aktivieren' : 'Nächste Aufgabe' }}
                                </button>
                                <span class="ns-saving" role="status">{{ saving ? 'Deine Eingabe wird gespeichert …' : dirty ? 'Eingabe noch nicht gespeichert' : 'Spielstand gespeichert' }}</span>
                            </div>
                        </form>
                        <div v-if="run.feedback" class="ns-alert ns-feedback" :class="{ 'ns-success': run.solved }" role="status">{{ run.feedback.message }}</div>
                    </section>
                    <aside class="ns-player-aside ns-stack">
                        <div class="ns-panel">
                            <h3>{{ run.history.length }} / {{ run.total }} Aufgaben gelöst</h3>
                            <progress class="ns-progress" :value="run.history.length" :max="run.total" aria-label="Gelöste Aufgaben" />
                            <p class="ns-small ns-muted">{{ run.mission.story }}</p>
                            <p v-if="storageUnavailable" class="ns-small ns-gold">Lokale Sicherung ist gesperrt. Warte vor dem Schließen auf „Spielstand gespeichert“.</p>
                        </div>
                        <div class="ns-panel ns-stack">
                            <h3>Deine Erfolge</h3>
                            <p class="ns-small ns-muted">Für jeden abgeschlossenen Bereich bekommst du ein Abzeichen. Auch mit Tipps!</p>
                            <ul class="ns-achievements"><li v-for="(chapter, index) in chapters" :key="chapter" :class="{ 'ns-earned': index < restoredChapters }"><span aria-hidden="true">{{ index < restoredChapters ? '✓' : '○' }}</span><span>{{ chapter }}<small>{{ index < restoredChapters ? 'Geschafft' : 'Noch offen' }}</small></span></li></ul>
                        </div>
                        <div class="ns-panel ns-logbook">
                            <details><summary>Rechenhilfe: Dual, Dezimal &amp; Hex</summary>
                                <p>Dual und binär bedeuten dasselbe: Eine Zahl besteht nur aus 0 und 1. Die Stellenwerte sind von rechts nach links 1, 2, 4, 8, 16, … . Addiere die Stellenwerte, bei denen eine 1 steht.</p>
                                <p>Dezimal sind die gewohnten Zahlen mit 0–9. Hexadezimal (kurz: Hex) verwendet zusätzlich A–F: A = 10, B = 11, C = 12, D = 13, E = 14, F = 15. Das Zeichen # kennzeichnet hier eine Hexzahl.</p>
                                <p>Vier Bits entsprechen einer Hexziffer. Von rechts gruppieren; nur links mit Nullen ergänzen. #20F enthält die innere Null weiterhin: 0010 0000 1111 (dual).</p>
                                <p>Von Dezimal nach Binär: Teile durch 2 und notiere den Rest. Teile das ganzzahlige Ergebnis erneut durch 2. Wiederhole das, bis das Ergebnis 0 ist. Lies die Reste von unten nach oben. Für Hexzahlen teilst du stattdessen durch 16.</p>
                                <p>Der Zahlenwert 256 benötigt neun Bits. Er passt nicht in ein Byte mit acht Bits.</p>
                                <table><thead><tr><th>Hex</th><th>Vier Bits</th><th>Dezimal</th></tr></thead><tbody><tr v-for="value in 16" :key="value"><td>#{{ (value - 1).toString(16).toUpperCase() }}</td><td>{{ (value - 1).toString(2).padStart(4, '0') }}</td><td>{{ value - 1 }}</td></tr></tbody></table>
                            </details>
                            <details v-if="run.mission.ascii"><summary>ASCII-Zeichentabelle</summary>
                                <p>Zeichen → Nummer → Zahlendarstellung. ASCII ist keine Verschlüsselung. Hier stehen nur Großbuchstaben und Leerzeichen.</p>
                                <table><thead><tr><th>Zeichen</th><th>Dezimal</th><th>Hex</th></tr></thead><tbody><tr v-for="char in asciiCharacters" :key="char"><td>{{ char === ' ' ? 'Leerzeichen' : char }}</td><td>{{ char.charCodeAt(0) }}</td><td>#{{ char.charCodeAt(0).toString(16).toUpperCase() }}</td></tr></tbody></table>
                            </details>
                        </div>
                        <div class="ns-panel ns-stack">
                            <h3>Brauchst du einen Tipp?</h3><p class="ns-small ns-muted">1: So fängst du an. 2: Weitere Hilfe. 3: Lösung mit Rechenweg. Ohne Punkteabzug.</p>
                            <button class="ns-button ns-button-secondary" :disabled="run.hints >= 3 || busy || saving || !canPlay || conflict" @click="act('hint')">{{ run.hints < 3 ? `Tipp ${run.hints + 1} anzeigen` : 'Alle Tipps angezeigt' }}</button>
                            <p v-for="(hint, index) in run.hint_texts" :key="index" class="ns-small">{{ hint }}</p>
                        </div>
                    </aside>
                </div>
            </template>
            <NumberSystemRanking :ranking="run.ranking" />
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, toRef, watch } from 'vue'
import NumberSystemPuzzle from './NumberSystemPuzzle.vue'
import NumberSystemInvitation from './NumberSystemInvitation.vue'
import NumberSystemClock from './NumberSystemClock.vue'
import NumberSystemRanking from './NumberSystemRanking.vue'
import NumberSystemReward from './NumberSystemReward.vue'
import { useNumberSystemPlayer } from './useNumberSystemPlayer'
import { createNumberSystemSound } from './numberSystemSound'
import './game.css'
const props = defineProps({ runId: { type: [Number, String], required: true }, probeId: String, participant: Number })
defineEmits(['back'])
const { run, draft, loading, saving, busy, dirty, conflict, error, storageUnavailable, canPlay, load, edit, flush, act } = useNumberSystemPlayer(toRef(props, 'runId'), props)
const chapters = ['Maschinenraum', 'Fehlerarchiv', 'Tresor', 'Funkzentrale']
const asciiCharacters = ' ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')
const directionLabels = { decimal_binary: 'Dezimal → Dual', binary_decimal: 'Dual → Dezimal', decimal_hex: 'Dezimal → Hex', hex_decimal: 'Hex → Dezimal', binary_hex: 'Dual → Hex', hex_binary: 'Hex → Dual' }
const independent = computed(() => (run.value?.history ?? []).filter(item => !item.hints).length)
const learnedDirections = computed(() => [...new Set((run.value?.history ?? []).flatMap(item => item.directions))])
const restoredChapters = computed(() => Math.min(4, Math.floor((run.value?.history?.length ?? 0) * 4 / (run.value?.total || 1))))
const reward = ref(null)
const sound = createNumberSystemSound()
function savedSoundPreference() { try { return localStorage.getItem('schooltool-number-system-sound') !== 'off' } catch { return true } }
const soundEnabled = ref(savedSoundPreference()), soundReady = ref(false)
const soundLabel = computed(() => soundEnabled.value && soundReady.value ? 'Ton ausschalten' : 'Ton einschalten')
async function prepareSound() { if (soundEnabled.value) { soundReady.value = await sound.unlock() } }
async function toggleSound() {
    if (soundEnabled.value && soundReady.value) { soundEnabled.value = false; sound.stop() }
    else { soundEnabled.value = true; await prepareSound(); if (reward.value && soundReady.value) { restartReward() } }
    try { localStorage.setItem('schooltool-number-system-sound', soundEnabled.value ? 'on' : 'off') } catch { /* The switch still works for this game. */ }
}
function submitAnswer() { void prepareSound(); return act('answer') }
let rewardTimer = null
let rewardSequence = 0
function dismissReward() { clearTimeout(rewardTimer); sound.stop(); reward.value = null }
function restartReward() {
    clearTimeout(rewardTimer)
    if (reward.value) {
        if (soundEnabled.value && !document.hidden) { void sound.play(reward.value) }
        rewardTimer = setTimeout(dismissReward, reward.value.kind === 'task' ? 4200 : reward.value.kind === 'chapter' ? 5200 : 6200)
    }
}
function previewReward(chapter, complete = false) {
    dismissReward()
    void prepareSound()
    const total = run.value?.total || 12
    const chapterTotal = Math.ceil((chapter + 1) * total / 4) - Math.ceil(chapter * total / 4)
    reward.value = {
        id: ++rewardSequence, preview: true, kind: complete ? 'complete' : 'chapter',
        chapter, chapterTitle: chapters[chapter], chapterTotal, chapterSolved: chapterTotal,
        chapterProgress: 100, previousChapterProgress: 0,
        title: complete ? 'Du hast das Netz gerettet!' : `${chapters[chapter]} geschafft!`,
        message: complete ? 'Alle vier Bereiche sind wieder verbunden.' : 'So sieht der Erfolg am Ende dieses Bereichs aus.',
    }
    restartReward()
}
watch(run, (current, previous) => {
    if (!current || !previous || current.id !== previous.id) { dismissReward(); return }
    const solved = current.history?.length ?? 0
    const earlier = previous.history?.length ?? 0
    if (solved <= earlier && !(current.completed && !previous.completed)) { return }
    const chaptersNow = Math.min(4, Math.floor(solved * 4 / current.total))
    const chaptersBefore = Math.min(4, Math.floor(earlier * 4 / current.total))
    const milestone = chaptersNow > chaptersBefore
    const complete = current.completed && !previous.completed
    const kind = complete ? 'complete' : milestone ? 'chapter' : 'task'
    const solvedAll = solved === current.total
    const chapter = complete ? 3 : milestone ? chaptersNow - 1 : current.mission?.chapter ?? Math.min(3, Math.floor((solved - 1) * 4 / current.total))
    const chapterStart = Math.ceil(chapter * current.total / 4)
    const chapterTotal = Math.ceil((chapter + 1) * current.total / 4) - chapterStart
    const chapterSolved = Math.min(chapterTotal, Math.max(0, solved - chapterStart))
    clearTimeout(rewardTimer)
    reward.value = {
        id: ++rewardSequence, kind, progress: Math.round(solved * 100 / current.total),
        chapter, chapterTitle: chapters[chapter], chapterTotal, chapterSolved,
        chapterProgress: Math.round(chapterSolved * 100 / chapterTotal),
        previousChapterProgress: Math.round(Math.min(chapterTotal, Math.max(0, earlier - chapterStart)) * 100 / chapterTotal),
        caption: complete ? 'Mission erfüllt' : milestone ? 'Neues Abzeichen' : 'Signal angekommen',
        title: complete ? 'Du hast das Netz gerettet!' : milestone ? `${chapters[chaptersNow - 1]} geschafft!` : `Aufgabe ${solved} gelöst!`,
        message: complete ? 'Alle vier Bereiche sind wieder verbunden.' : solvedAll ? 'Alle Aufgaben gelöst. Aktiviere jetzt das Rechenzentrum!' : `${solved} von ${current.total} Aufgaben geschafft. Weiter so!`,
    }
    restartReward()
})
function visibilityChanged() { if (document.hidden) { sound.stop() } }
document.addEventListener('visibilitychange', visibilityChanged)
onBeforeUnmount(() => { clearTimeout(rewardTimer); sound.dispose(); document.removeEventListener('visibilitychange', visibilityChanged) })
const missionTitle = ref(null)
watch(() => run.value?.mission?.id, async (value, previous) => { if (previous && value !== previous) { await nextTick(); missionTitle.value?.focus({ preventScroll: true }); missionTitle.value?.scrollIntoView({ block: 'start', behavior: 'instant' }) } })
</script>
