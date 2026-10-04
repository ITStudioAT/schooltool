<template>
    <section class="zero-panel">
        <div class="zero-section-title"><div><span class="zero-eyebrow">VORBEREITUNG</span><h2>{{ existing ? 'Matura bearbeiten' : 'Neue Matura einrichten' }}</h2></div><button class="zero-link" @click="$emit('cancel')">Schließen</button></div>
        <p class="zero-help mb-5">Alle Räume mit derselben Toilette gehören zu einer gemeinsamen Matura. Jeder freigegebene Gang reserviert einen Platz.</p>
        <form @submit.prevent="save">
            <div class="zero-form-grid">
                <label>Name der Matura<input v-model="form.name" required maxlength="160" placeholder="z. B. Deutsch · Haupttermin"></label>
                <label>Prüfungsdatum<input v-model="form.exam_date" type="date" required></label>
                <label>Warteplätze an der Zwischenstation<input v-model.number="form.waiting_places" type="number" min="0" max="10" required><small>Zusätzlich zu genau einem Toilettenplatz. Auch 0 möglich.</small></label>
            </div>
            <div v-for="(room, index) in form.rooms" :key="room.key" class="zero-room-editor">
                <div class="zero-section-title"><label class="zero-grow">Raum {{ index + 1 }}<input v-model="room.name" required maxlength="80" placeholder="z. B. 3.12"></label><button v-if="form.rooms.length > 1" type="button" class="zero-link" @click="form.rooms.splice(index, 1)">Raum entfernen</button></div>
                <div class="zero-actions my-3">
                    <label>Klasse filtern<select v-model="room.classFilter"><option value="">Alle Klassen</option><option v-for="schoolClass in classes" :key="schoolClass">{{ schoolClass }}</option></select></label>
                    <label class="zero-grow">Namen suchen<input v-model="room.search" type="search" placeholder="Nachname oder Vorname"></label>
                    <button type="button" class="zero-button zero-button--secondary" @click="selectClass(room)">Sichtbare auswählen</button>
                    <button type="button" class="zero-link" @click="room.student_ids = []">Auswahl leeren</button>
                </div>
                <div class="zero-roster">
                    <label v-for="student in filteredStudents(room)" :key="student.id" class="zero-checkbox">
                        <input v-model="room.student_ids" type="checkbox" :value="student.id" :disabled="assignedElsewhere(student.id, room)">
                        <span>{{ student.last_name }} {{ student.first_name }}<small>{{ student.class }}{{ assignedElsewhere(student.id, room) ? ' · bereits anderem Raum zugeordnet' : '' }}</small></span>
                    </label>
                    <p v-if="!filteredStudents(room).length" class="zero-help pa-3">Keine passenden Schüler im aktuellen Import.</p>
                </div>
                <p class="zero-help mt-2">{{ room.student_ids.length }} Schüler ausgewählt</p>
                <details class="mt-4"><summary>Weitere Schüler ohne Import hinzufügen</summary><label class="zero-field mt-3">Ein Name pro Zeile<textarea v-model="room.manualText" rows="3" placeholder="Nachname Vorname" /><small>Diese Namen werden nur in dieser Matura gespeichert.</small></label></details>
            </div>
            <div class="zero-actions"><button type="button" class="zero-button zero-button--secondary" @click="addRoom">+ Weiteren Raum hinzufügen</button><button class="zero-button" :disabled="busy">Matura speichern</button></div>
        </form>
    </section>
</template>
<script setup>
import { computed, reactive } from 'vue'
const props = defineProps({ roster: { type: Array, default: () => [] }, schoolyearId: Number, existing: Object, busy: Boolean })
const emit = defineEmits(['save', 'cancel'])
let roomKey = 0
function blankRoom() { return { key: ++roomKey, name: '', student_ids: [], classFilter: '', search: '', manualText: '' } }
const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Vienna' }).format(new Date())
const form = reactive({ name: props.existing?.session.name || '', exam_date: props.existing?.session.exam_date || today, waiting_places: props.existing?.session.waiting_places ?? 1, rooms: props.existing ? props.existing.rooms.map((room) => ({ ...blankRoom(), name: room.name, student_ids: props.existing.students.filter((student) => student.matura_room_id === room.id && student.import116_id).map((student) => student.import116_id), manualText: props.existing.students.filter((student) => student.matura_room_id === room.id && !student.import116_id).map((student) => student.name).join('\n') })) : [blankRoom()] })
const classes = computed(() => [...new Set(props.roster.map((student) => student.class))].sort())
function filteredStudents(room) { return props.roster.filter((student) => (!room.classFilter || student.class === room.classFilter) && `${student.last_name} ${student.first_name}`.toLocaleLowerCase().includes(room.search.toLocaleLowerCase())) }
function assignedElsewhere(id, room) { return form.rooms.some((other) => other !== room && other.student_ids.includes(id)) }
function selectClass(room) { room.student_ids = [...new Set([...room.student_ids, ...filteredStudents(room).filter((student) => !assignedElsewhere(student.id, room)).map((student) => student.id)])] }
function addRoom() { if (form.rooms.length < 40) form.rooms.push(blankRoom()) }
function save() { emit('save', { schoolyear_id: props.schoolyearId, name: form.name, exam_date: form.exam_date, waiting_places: form.waiting_places, rooms: form.rooms.map((room) => ({ name: room.name, student_ids: room.student_ids, manual_students: room.manualText.split('\n').map((name) => name.trim()).filter(Boolean).map((name) => ({ name })) })) }) }
</script>
