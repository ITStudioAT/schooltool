<template>
    <div class="zero-board">
        <div v-if="state.session.status !== 'active'" class="zero-notice">
            {{ state.session.status === 'draft' ? 'Vorbereitung · Die Leitung startet die Koordination, sobald alles bereit ist.' : 'Diese Matura ist abgeschlossen. Das Protokoll bleibt verfügbar.' }}
        </div>
        <div v-if="!state.actor.manager" class="zero-duty">
            <div><span class="zero-eyebrow">MEINE STATION</span><h2>{{ ownStation }}</h2>
                <p>{{ state.actor.name }} · {{ state.actor.owns_station ? 'Sie haben die Aufsicht übernommen.' : `Aktuelle Aufsicht: ${state.actor.current_supervisor?.name || 'noch nicht übernommen'}` }}</p>
            </div>
            <button v-if="!state.actor.owns_station" class="zero-button" :disabled="disabled || state.session.status !== 'active'" @click="claim">
                {{ state.actor.current_supervisor ? 'Aufsicht ablösen' : 'Aufsicht übernehmen' }}
            </button>
            <span v-else class="zero-badge zero-badge--green">✓ Aufsicht aktiv</span>
        </div>
        <div class="zero-metrics">
            <section class="zero-metric" :class="toilet ? 'zero-metric--occupied' : 'zero-metric--free'">
                <span class="zero-eyebrow">TOILETTE</span><strong>{{ toilet ? 'Belegt' : 'Frei' }}</strong>
                <span>{{ toilet ? toilet.student_name : 'Ein Platz · gemeinsam koordiniert' }}</span>
                <small v-if="toilet">{{ toilet.room_name }} · seit {{ time(toilet.entered_at) }}</small>
            </section>
            <section class="zero-metric">
                <span class="zero-eyebrow">RESERVIERT & BELEGT</span><strong>{{ state.reserved }} <small>/ {{ state.capacity }}</small></strong>
                <span>{{ Math.max(0, state.capacity - state.reserved) }} Plätze verfügbar</span>
                <div class="zero-slots" aria-hidden="true"><i v-for="slot in state.capacity" :key="slot" :class="{ filled: slot <= state.reserved }"></i></div>
                <small>1 Toilette + {{ state.session.waiting_places }} Warteplätze</small>
            </section>
            <section class="zero-metric">
                <span class="zero-eyebrow">ANFRAGEN IM RAUM</span><strong>{{ requested.length }}</strong>
                <span>{{ requested.length ? `Als Nächstes: ${requested[0].room_name}` : 'Aktuell keine offenen Anfragen' }}</span>
                <small>Freigaben in der Reihenfolge der Anfrage</small>
            </section>
        </div>

        <section v-if="state.actor.manager" class="zero-room-strip" aria-label="Räume und Aufsichten">
            <div v-for="room in state.rooms" :key="room.id" class="zero-room-pill">
                <strong>{{ room.name }}</strong><span>{{ room.away_count }} unterwegs · {{ room.student_count }} Schüler</span>
                <small>{{ room.supervisor?.name || 'Aufsicht noch offen' }}</small>
            </div>
            <div class="zero-room-pill"><strong>Zwischenstation</strong><span>{{ state.station_supervisor?.name || 'Aufsicht noch offen' }}</span><small>{{ state.session.waiting_places }} Warteplätze</small></div>
        </section>

        <section v-if="state.actor.manager || state.actor.room_id !== null" class="zero-panel">
            <div class="zero-section-title"><div><span class="zero-eyebrow">IM PRÜFUNGSRAUM</span><h2>Toilettengang anmelden</h2></div></div>
            <form class="zero-request-form" @submit.prevent="requestVisit">
                <label v-if="state.actor.manager">Raum<select v-model="selectedRoom"><option :value="null">Raum auswählen</option><option v-for="room in state.rooms" :key="room.id" :value="room.id">{{ room.name }}</option></select></label>
                <label class="zero-grow">Schüler
                    <select v-model="selectedStudent" required><option :value="null">Schüler auswählen</option><option v-for="student in availableStudents" :key="student.id" :value="student.id">{{ student.name }} · {{ student.class_name }}</option></select>
                </label>
                <button class="zero-button" :disabled="!selectedStudent || cannotAct">Gang anmelden</button>
            </form>
            <p class="zero-help">Erst nach der Freigabe losschicken. Die Freigabe reserviert den Platz bis zur Ankunft.</p>
        </section>

        <div class="zero-section-title"><div><span class="zero-eyebrow">GEMEINSAMER STAND</span><h2>{{ state.actor.room_id !== null && !state.actor.manager ? 'Gänge in meinem Raum' : 'Aktuelle Gänge' }}</h2></div><span class="zero-count">{{ visibleVisits.length }}</span></div>
        <div v-if="!visibleVisits.length" class="zero-empty"><span class="zero-empty-symbol">✓</span><h3>Alles im Blick.</h3><p>Hier erscheinen Anfragen und laufende Gänge automatisch.</p></div>
        <div v-else class="zero-visits">
            <article v-for="visit in visibleVisits" :key="visit.id" class="zero-visit" :class="`zero-visit--${visit.status}`">
                <div class="zero-visit-heading"><span class="zero-badge">{{ statusLabels[visit.status] }}</span><small>{{ visit.room_name }}</small></div>
                <h3>{{ visit.student_name }}</h3>
                <p class="zero-help">{{ visit.class_name }} · angemeldet {{ time(visit.requested_at) }}</p>
                <div class="zero-timeline" aria-label="Bisherige Schritte"><span v-if="visit.departed_at">Abgang {{ time(visit.departed_at) }}</span><span v-if="visit.arrived_at">Ankunft {{ time(visit.arrived_at) }}</span><span v-if="visit.entered_at">Eintritt {{ time(visit.entered_at) }}</span><span v-if="visit.exited_at">Austritt {{ time(visit.exited_at) }}</span></div>
                <div class="zero-visit-actions">
                    <button v-for="action in actionsFor(visit)" :key="action.action" class="zero-button" :disabled="cannotAct || action.disabled" @click="perform(action.action, visit)">{{ action.label }}</button>
                    <button v-if="roomAllowed(visit) && ['requested', 'approved'].includes(visit.status)" class="zero-link" :disabled="cannotAct" @click="correct('cancel', visit)">Stornieren</button>
                    <button v-if="roomAllowed(visit) && ['departed', 'arrived'].includes(visit.status)" class="zero-link" :disabled="cannotAct" @click="correct('return_without_toilet', visit)">Zurück ohne Toilettengang</button>
                    <button v-if="state.actor.manager" class="zero-link" :disabled="cannotAct" @click="correct('void', visit)">Fehlbuchung korrigieren</button>
                </div>
                <p v-if="visit.status === 'requested' && !canApprove(visit)" class="zero-help">Im Raum warten · {{ state.reserved >= state.capacity ? 'alle Plätze reserviert' : 'eine frühere Anfrage ist zuerst an der Reihe' }}.</p>
            </article>
        </div>
        <v-dialog v-model="correctionOpen" max-width="520">
            <v-card class="pa-5"><h2>{{ correctionAction === 'void' ? 'Als Fehlbuchung markieren' : correctionAction === 'cancel' ? 'Anfrage stornieren' : 'Rückkehr ohne Toilettengang' }}</h2>
                <p class="my-3">{{ correctionVisit?.student_name }} · {{ correctionVisit?.room_name }}</p>
                <p v-if="correctionAction === 'void'" class="mb-3">Der Eintrag bleibt nachvollziehbar erhalten und wird aus den tatsächlichen Toilettengängen ausgeschlossen. Erst bestätigen, wenn die Person wieder sicher im Raum ist.</p>
                <label class="zero-field">Grund<textarea v-model="reason" rows="3" maxlength="1000" /></label>
                <div class="zero-actions mt-4"><button class="zero-button zero-button--secondary" @click="correctionOpen = false">Abbrechen</button><button class="zero-button" :disabled="reason.trim().length < 3 || disabled" @click="submitCorrection">Bestätigen</button></div>
            </v-card>
        </v-dialog>
        <v-dialog v-model="claimOpen" max-width="480"><v-card class="pa-5"><h2>Aufsicht übernehmen</h2><p class="my-4">Sie lösen {{ state.actor.current_supervisor?.name }} an dieser Station ab. Die bisherige Aufsicht kann danach keine Schritte mehr bestätigen.</p><div class="zero-actions"><button class="zero-button zero-button--secondary" @click="claimOpen = false">Abbrechen</button><button class="zero-button" @click="confirmClaim">Jetzt übernehmen</button></div></v-card></v-dialog>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { statusLabels, time } from './maturaFormat'
const props = defineProps({ state: { type: Object, required: true }, disabled: Boolean })
const emit = defineEmits(['action'])
const selectedRoom = ref(props.state.actor.room_id)
const selectedStudent = ref(null)
const correctionOpen = ref(false)
const correctionAction = ref('')
const correctionVisit = ref(null)
const reason = ref('')
const claimOpen = ref(false)
const toilet = computed(() => props.state.visits.find((visit) => visit.status === 'toilet'))
const requested = computed(() => props.state.visits.filter((visit) => visit.status === 'requested'))
const cannotAct = computed(() => props.disabled || !props.state.actor.owns_station || props.state.session.status !== 'active')
const ownStation = computed(() => props.state.actor.room_id === null ? 'Zwischenstation' : props.state.rooms.find((room) => room.id === props.state.actor.room_id)?.name)
const visibleVisits = computed(() => props.state.visits.filter((visit) => props.state.actor.manager || props.state.actor.room_id === null || visit.room_id === props.state.actor.room_id))
const availableStudents = computed(() => props.state.students.filter((student) => student.matura_room_id === selectedRoom.value && !props.state.visits.some((visit) => visit.student_id === student.id)))
watch(selectedRoom, () => { selectedStudent.value = null })
function roomAllowed(visit) { return props.state.actor.manager || props.state.actor.room_id === visit.room_id }
function canApprove(visit) { return props.state.reserved < props.state.capacity && requested.value[0]?.id === visit.id }
function actionsFor(visit) {
    const actions = []
    if (roomAllowed(visit)) {
        if (visit.status === 'requested') actions.push({ action: 'approve', label: 'Platz freigeben', disabled: !canApprove(visit) })
        if (visit.status === 'approved') actions.push({ action: 'depart', label: 'Jetzt losschicken' })
        if (visit.status === 'returning') actions.push({ action: 'return', label: 'Zurück im Raum' })
    }
    if (props.state.actor.manager || props.state.actor.room_id === null) {
        if (visit.status === 'departed') actions.push({ action: 'arrive', label: 'Ankunft bestätigen' })
        if (visit.status === 'arrived') actions.push({ action: 'enter', label: 'Toilette betreten', disabled: !!toilet.value || props.state.visits.filter((row) => row.status === 'arrived').sort((a, b) => a.arrived_at.localeCompare(b.arrived_at) || a.id - b.id)[0]?.id !== visit.id })
        if (visit.status === 'toilet') actions.push({ action: 'exit', label: 'Toilette verlassen' })
    }
    return actions
}
function perform(action, visit) { emit('action', { action, visit_id: visit.id, expected_status: visit.status }) }
function requestVisit() { emit('action', { action: 'request', student_id: selectedStudent.value }); selectedStudent.value = null }
function correct(action, visit) { correctionAction.value = action; correctionVisit.value = visit; reason.value = ''; correctionOpen.value = true }
function submitCorrection() { emit('action', { action: correctionAction.value, visit_id: correctionVisit.value.id, expected_status: correctionVisit.value.status, reason: reason.value }); correctionOpen.value = false }
function claim() { if (props.state.actor.current_supervisor) claimOpen.value = true; else confirmClaim() }
function confirmClaim() { emit('action', { action: 'claim', previous_access_id: props.state.actor.current_access_id }); claimOpen.value = false }
</script>
