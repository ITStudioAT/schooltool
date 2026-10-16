<template>
    <div class="ns-game">
        <div class="ns-toolbar ns-player-top">
            <div><h1>Signal Null</h1><p class="ns-muted">{{ run?.session.title || 'Verbindung zum Rechenzentrum' }}</p></div>
            <button class="ns-button ns-button-quiet" @click="$emit('back')">Meine Abenteuer</button>
        </div>
        <div v-if="error" class="ns-alert ns-alert-error" role="alert">
            <p>{{ error }}</p>
            <button class="ns-button ns-button-secondary" style="margin-top:10px" :disabled="busy || saving" @click="conflict ? load(true) : run ? flush() : load()">
                {{ conflict ? 'Gespeicherten Stand laden' : 'Erneut verbinden / speichern' }}
            </button>
        </div>
        <p v-if="loading" role="status">Spielstand wird geladen …</p>
        <template v-else-if="run">
            <div v-if="run.team_name" class="ns-small ns-muted">Team {{ run.team_name }} · {{ run.members.join(', ') }}<br />Teamcode für eure weiteren Mitglieder: <strong class="ns-code ns-code-small">{{ run.team_code }}</strong></div>
            <details v-if="run.invitation" class="ns-panel" style="margin-top:16px"><summary>Team einladen · QR-Code anzeigen</summary><NumberSystemInvitation :invitation="run.invitation" title="Deinem privaten Team beitreten" private-team :local-probe="Boolean(probeId)" /></details>
            <ol class="ns-path" aria-label="Rechenzentrum wiederherstellen">
                <li v-for="(chapter, index) in chapters" :key="chapter" :class="{ 'ns-restored': run.completed || index < run.mission?.chapter, 'ns-current': !run.completed && index === run.mission?.chapter }">
                    {{ run.completed || index < run.mission?.chapter ? '✓ ' : '' }}{{ chapter }}
                </li>
            </ol>
            <div v-if="run.completed" class="ns-panel ns-stack" role="status">
                <h2 class="ns-gold">Das Rechenzentrum ist wieder am Netz.</h2>
                <p>Alle {{ run.total }} Datenwege sind bestätigt. Ihr habt Stellenwerte verbunden, Daten geprüft und Nachrichten wieder lesbar gemacht.</p>
                <p>{{ independent }} Missionen selbstständig · {{ run.history.length - independent }} mit Hilfe. Hilfen sind Lernwege und kosten keine Punkte.</p>
                <ul class="ns-checks"><li v-for="direction in learnedDirections" :key="direction">✓ {{ directionLabels[direction] }}</li></ul>
                <p class="ns-muted">Besprecht zum Abschluss: Welche Regel hat euch am meisten geholfen? Warum bedeutet eine gültige ASCII-Nachricht noch nicht, dass sie unverändert ist?</p>
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
                        <p class="ns-small ns-muted">{{ run.mission.chapter_title }} · Datenweg {{ run.position + 1 }} von {{ run.total }}</p>
                        <h2 ref="missionTitle" tabindex="-1" style="margin-top:8px">{{ run.mission.title }}</h2>
                        <p class="ns-prompt">{{ run.mission.prompt }}</p>
                        <form @submit.prevent="act('answer')">
                            <NumberSystemPuzzle :mission="run.mission" :model-value="draft" :disabled="!canPlay || busy || conflict" @update:model-value="edit" />
                            <div class="ns-toolbar" style="margin-top:24px">
                                <button v-if="!run.solved" type="submit" class="ns-button" :disabled="!canPlay || busy || saving || conflict">{{ busy ? 'Datenweg wird geprüft …' : 'Datenweg prüfen' }}</button>
                                <button v-else type="button" class="ns-button" :disabled="busy || run.session.status !== 'running'" @click="act('advance')">
                                    {{ run.position === run.total - 1 ? 'Rechenzentrum aktivieren' : 'Nächsten Datenweg öffnen' }}
                                </button>
                                <span class="ns-saving" role="status">{{ saving ? 'Entwurf wird gespeichert …' : dirty ? 'Noch nicht synchronisiert' : 'Spielstand gespeichert' }}</span>
                            </div>
                        </form>
                        <div v-if="run.feedback" class="ns-alert ns-feedback" :class="{ 'ns-success': run.solved }" role="status">{{ run.feedback.message }}</div>
                    </section>
                    <aside class="ns-player-aside ns-stack">
                        <div class="ns-panel">
                            <h3>{{ run.history.length }} / {{ run.total }} bestätigt</h3>
                            <progress class="ns-progress" :value="run.history.length" :max="run.total" aria-label="Bestätigte Datenwege" />
                            <p class="ns-small ns-muted">{{ run.mission.story }}</p>
                            <p v-if="storageUnavailable" class="ns-small ns-gold">Lokale Sicherung ist gesperrt. Warte vor dem Schließen auf „Spielstand gespeichert“.</p>
                        </div>
                        <div class="ns-panel ns-logbook">
                            <details><summary>Logbuch &amp; Zahlensysteme</summary>
                                <p>Dual: nur 0 und 1. Stellenwerte verdoppeln sich nach links. Hex: 0–9 und A–F; A = 10 bis F = 15.</p>
                                <p>Vier Bits entsprechen einer Hexziffer. Von rechts gruppieren; nur links mit Nullen ergänzen. #20F enthält die innere Null weiterhin: 0010 0000 1111 (dual).</p>
                                <p>Dezimal umrechnen: wiederholt durch 2 oder 16 teilen, Reste von unten nach oben lesen. Null bleibt 0.</p>
                                <p>Der Zahlenwert 256 benötigt neun Bits. Er passt nicht in ein Byte mit acht Bits.</p>
                                <table><thead><tr><th>Hex</th><th>Vier Bits</th><th>Dezimal</th></tr></thead><tbody><tr v-for="value in 16" :key="value"><td>#{{ (value - 1).toString(16).toUpperCase() }}</td><td>{{ (value - 1).toString(2).padStart(4, '0') }}</td><td>{{ value - 1 }}</td></tr></tbody></table>
                            </details>
                            <details v-if="run.mission.ascii"><summary>ASCII-Zeichentabelle</summary>
                                <p>Zeichen → Nummer → Zahlendarstellung. ASCII ist keine Verschlüsselung. Hier stehen nur Großbuchstaben und Leerzeichen.</p>
                                <table><thead><tr><th>Zeichen</th><th>Dezimal</th><th>Hex</th></tr></thead><tbody><tr v-for="char in asciiCharacters" :key="char"><td>{{ char === ' ' ? 'Leerzeichen' : char }}</td><td>{{ char.charCodeAt(0) }}</td><td>#{{ char.charCodeAt(0).toString(16).toUpperCase() }}</td></tr></tbody></table>
                            </details>
                        </div>
                        <div class="ns-panel ns-stack">
                            <h3>Werkstatt-Hilfe</h3><p class="ns-small ns-muted">Drei Stufen, ohne Punkteverlust. Die letzte zeigt einen durchgerechneten Weg.</p>
                            <button class="ns-button ns-button-secondary" :disabled="run.hints >= 3 || busy || saving || !canPlay || conflict" @click="act('hint')">{{ run.hints < 3 ? `Hilfe ${run.hints + 1} öffnen` : 'Alle Hilfen geöffnet' }}</button>
                            <p v-for="(hint, index) in run.hint_texts" :key="index" class="ns-small">{{ hint }}</p>
                        </div>
                    </aside>
                </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, toRef, watch } from 'vue'
import NumberSystemPuzzle from './NumberSystemPuzzle.vue'
import NumberSystemInvitation from './NumberSystemInvitation.vue'
import { useNumberSystemPlayer } from './useNumberSystemPlayer'
import './game.css'
const props = defineProps({ runId: { type: [Number, String], required: true }, probeId: String, participant: Number })
defineEmits(['back'])
const { run, draft, loading, saving, busy, dirty, conflict, error, storageUnavailable, canPlay, load, edit, flush, act } = useNumberSystemPlayer(toRef(props, 'runId'), props)
const chapters = ['Maschinenraum', 'Fehlerarchiv', 'Tresor', 'Funkzentrale']
const asciiCharacters = ' ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')
const directionLabels = { decimal_binary: 'Dezimal → Dual', binary_decimal: 'Dual → Dezimal', decimal_hex: 'Dezimal → Hex', hex_decimal: 'Hex → Dezimal', binary_hex: 'Dual → Hex', hex_binary: 'Hex → Dual' }
const independent = computed(() => (run.value?.history ?? []).filter(item => !item.hints).length)
const learnedDirections = computed(() => [...new Set((run.value?.history ?? []).flatMap(item => item.directions))])
const missionTitle = ref(null)
watch(() => run.value?.mission?.id, async (value, previous) => { if (previous && value !== previous) { await nextTick(); missionTitle.value?.focus({ preventScroll: true }); missionTitle.value?.scrollIntoView({ block: 'start', behavior: 'instant' }) } })
</script>
