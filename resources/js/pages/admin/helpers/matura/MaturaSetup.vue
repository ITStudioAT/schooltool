<template>
    <section class="zero-panel zero-setup">
        <div class="zero-section-title"><div><span class="zero-eyebrow">{{ viewOnly ? 'MATURA' : 'VORBEREITUNG' }}</span><h2>{{ viewOnly ? existing.session.name : roomOnly ? 'Raum bearbeiten' : existing ? 'Matura bearbeiten' : 'Neue Matura einrichten' }}</h2></div><button v-if="!viewOnly" type="button" class="zero-link" :disabled="busy" @click="$emit('cancel')">Abbrechen</button></div>
        <p class="zero-help mb-4">{{ viewOnly ? 'Matura und Klassen, Räume und Schüler oder den Ablauf öffnen.' : existing ? 'Mit „Übernehmen“ speicherst du deine Änderungen und kehrst zur Matura zurück.' : 'Öffne eine Aufgabe und bestätige sie mit „Übernehmen“. Gespeichert wird erst, wenn alles bereit ist.' }}</p>
        <p v-if="assignmentNotice" class="zero-help mb-4" role="status">{{ assignmentNotice }}</p>
        <div class="zero-setup-tasks" aria-label="Aufgaben zur Einrichtung">
            <button v-for="(task, index) in tasks" :key="task.title" type="button" class="zero-setup-task" :class="{ active: activeStep === index, complete: completed[index] }" :disabled="busy || !canOpen(index)" :aria-expanded="activeStep === index" :aria-controls="'setup-task-' + index" @click="openStep(index)">
                <span class="zero-setup-task-number">{{ !viewOnly && completed[index] ? '✓' : index + 1 }}</span>
                <strong>{{ task.title }}</strong><span>{{ task.description }}</span>
                <small>{{ viewOnly ? (activeStep === index ? 'Geöffnet' : 'Anzeigen') : activeStep === index ? 'In Bearbeitung' : completed[index] ? 'Erledigt' : 'Offen' }}</small>
                <small v-if="!roomOnly && !canOpen(index)">Zuerst „{{ tasks[index - 1].title }}“ erledigen</small>
            </button>
        </div>
        <section v-if="viewOnly" :id="'setup-task-' + activeStep" class="mt-4">
            <template v-if="activeStep === 0">
                <h3>Matura & Klassen</h3>
                <p class="zero-confirmed-value">{{ existing.session.name }} · {{ savedDate }}</p>
                <p class="zero-help">Teilnehmende Klassen</p>
                <p class="zero-confirmed-value">{{ savedClasses.join(', ') || 'Nur manuelle Schüler' }}</p>
                <slot name="edit" />
            </template>
            <template v-else-if="activeStep === 1">
                <h3>Räume & Schüler</h3>
                <p class="zero-confirmed-value">{{ existing.rooms.length }} Räume · {{ existing.students?.length || 0 }} Schüler insgesamt</p>
                <div class="zero-confirmed-rooms">
                    <article v-for="savedRoom in existing.rooms" :key="savedRoom.id" class="zero-confirmed-room">
                        <h4>{{ savedRoom.name }}</h4>
                        <p class="zero-help">{{ savedRoomStudents(savedRoom.id).length }} Schüler</p>
                        <ul><li v-for="student in savedRoomStudents(savedRoom.id)" :key="student.id">{{ student.name }}{{ student.class_name ? ` · ${student.class_name}` : '' }}</li></ul>
                        <slot name="room-actions" :room="savedRoom" />
                    </article>
                </div>
            </template>
            <slot v-else name="flow" />
        </section>
        <p v-if="!viewOnly" class="zero-help mt-3 mb-4" role="status">{{ completed.filter(Boolean).length }} von {{ tasks.length }} Aufgaben erledigt</p>
        <form v-if="!viewOnly && activeStep !== null" :id="'setup-task-' + activeStep" ref="stepForm" @submit.prevent @keydown.enter="preventInputSubmit">
            <p v-if="validationError" class="zero-error" role="alert">{{ validationError }}</p>
            <fieldset :disabled="busy" class="zero-setup-fields">
                <section v-if="activeStep === 0" aria-labelledby="setup-basics">
                    <h3 id="setup-basics">Wann findet die Matura statt?</h3>
                    <p class="zero-help mb-4">Gib der Matura einen Namen, damit du sie später leicht wiederfindest.</p>
                    <div class="zero-form-grid">
                        <label>Name der Matura<input v-model="form.name" required maxlength="160" placeholder="z. B. Deutsch · Haupttermin"></label>
                        <label>Prüfungsdatum<input v-model="form.exam_date" type="date" required></label>
                    </div>
                    <h3 class="mt-4">Welche Klassen nehmen teil?</h3>
                    <p class="zero-help mt-2">Wähle eine oder mehrere Klassen. Ihre Schüler stehen im nächsten Schritt zur Verfügung und werden dort einzeln einem Raum zugeordnet.</p>
                    <div class="zero-setup-classes" aria-label="Teilnehmende Klassen">
                        <button v-for="schoolClass in classes" :key="schoolClass" type="button" class="zero-button zero-button--secondary" :class="{ 'zero-class-selected': form.selected_classes.includes(schoolClass) }" :aria-pressed="form.selected_classes.includes(schoolClass)" @click="toggleClass(schoolClass)"><strong>{{ schoolClass }}</strong><small>{{ classStudents(schoolClass).length }} Schüler verfügbar</small></button>
                    </div>
                    <p class="zero-help">{{ form.selected_classes.length }} Klassen ausgewählt · {{ studentPool.length }} Schüler verfügbar. Noch keine Raumzuordnung durch die Klassenwahl.</p>
                    <p v-if="!form.selected_classes.length" class="zero-help mt-2">Ohne Klassenauswahl stehen keine Import-Schüler zur Verfügung. Manuelle Namen kannst du im Raumschritt ergänzen.</p>
                    <p class="zero-help mt-2">Wenn du eine Klasse abwählst, entfallen ihre bisherigen Raumzuordnungen. Manuelle Namen bleiben erhalten.</p>
                </section>
                <section v-else-if="activeStep === 1" aria-labelledby="setup-rooms">
                    <h3 id="setup-rooms">Welche Schüler gehören in welchen Raum?</h3>
                    <p class="zero-help mb-4">Alle Räume mit derselben Toilette gehören zu dieser Matura. Ordne jeden Schüler einem Raum zu.</p>
                    <nav v-if="!roomOnly" class="zero-setup-rooms" aria-label="Räume bearbeiten">
                        <button v-for="(item, index) in form.rooms" :key="item.key" type="button" class="zero-button zero-button--secondary" :aria-current="roomIndex === index ? 'true' : undefined" @click="chooseRoom(index)">{{ item.name || `Raum ${index + 1}` }} <small>{{ studentCount(item) }} Schüler</small></button>
                        <button type="button" class="zero-link" :disabled="form.rooms.length >= 40" @click="addRoom">+ Raum hinzufügen</button>
                    </nav>
                    <div :key="room.key" class="zero-room-editor">
                        <div class="zero-section-title"><label class="zero-grow">Name von Raum {{ roomIndex + 1 }}<input v-model="room.name" required maxlength="80" placeholder="z. B. 3.12"></label><button v-if="!roomOnly && form.rooms.length > 1" type="button" class="zero-link" @click="removeRoom">Raum entfernen</button></div>
                        <p class="zero-help">Raum {{ roomIndex + 1 }} von {{ form.rooms.length }} · {{ studentCount(room) }} Schüler zugeordnet</p>
                        <template v-if="room.name.trim()">
                        <h3 class="mt-4">Welche Schüler sitzen in diesem Raum?</h3>
                        <p class="zero-help mt-2">Hier erscheinen nur Schüler der in Schritt 1 ausgewählten Klassen. Bereits anderen Räumen zugeordnete Schüler bleiben dort.</p>
                        <div class="zero-actions my-3">
                            <label>Klasse filtern<select v-model="room.classFilter"><option value="">Alle teilnehmenden Klassen</option><option v-for="schoolClass in selectedClassOptions" :key="schoolClass">{{ schoolClass }}</option></select></label>
                            <label class="zero-grow">Namen suchen<input v-model="room.search" type="search" placeholder="Nachname oder Vorname"></label>
                            <button type="button" class="zero-link" @click="room.student_ids = []">Auswahl leeren</button>
                        </div>
                        <div class="zero-roster" :class="{ 'zero-roster--single-class': visibleStudentGroups.length === 1 }">
                            <template v-for="group in visibleStudentGroups" :key="group.schoolClass">
                            <h4 class="zero-roster-class">{{ group.schoolClass }}</h4>
                            <label v-for="student in group.students" :key="student.id" class="zero-checkbox">
                                <input v-model="room.student_ids" type="checkbox" :value="student.id" :disabled="assignedElsewhere(student.id, room)">
                                <span>{{ student.last_name }} {{ student.first_name }}<small>{{ student.class }}{{ assignedElsewhere(student.id, room) ? ' · bereits anderem Raum zugeordnet' : '' }}</small></span>
                            </label>
                            </template>
                            <p v-if="!filteredStudents(room).length" class="zero-help pa-3">Keine passenden Schüler aus den ausgewählten Klassen.</p>
                        </div>
                        <p class="zero-help mt-2">{{ room.student_ids.length }} Schüler aus dem Import ausgewählt</p>
                        <details class="mt-4"><summary>Weitere Schüler ohne Import hinzufügen</summary><label class="zero-field mt-3">Ein Name pro Zeile<textarea v-model="room.manualText" rows="3" placeholder="Nachname Vorname" /><small>Diese Namen werden nur in dieser Matura gespeichert.</small></label></details>
                        </template>
                        <p v-else class="zero-help mt-4">Gib zuerst den Raum an. Danach kannst du seine Schüler auswählen.</p>
                        <div v-if="!roomOnly && form.rooms.length > 1" class="zero-actions mt-4"><button type="button" class="zero-link" :disabled="roomIndex === 0" @click="chooseRoom(roomIndex - 1)">Vorheriger Raum</button><button type="button" class="zero-link" :disabled="roomIndex === form.rooms.length - 1" @click="chooseRoom(roomIndex + 1)">Nächster Raum</button></div>
                    </div>
                </section>
                <section v-else aria-labelledby="setup-flow">
                    <h3 id="setup-flow">Wie viele Warteplätze gibt es?</h3>
                    <p class="zero-help mb-4">An der Toilette ist genau ein Platz. Weitere Schüler können an der Zwischenstation warten.</p>
                    <label class="zero-setup-capacity">Warteplätze an der Zwischenstation<input v-model.number="form.waiting_places" type="number" min="0" max="10" required><small>0 bis 10 Warteplätze. Mit 0 wartet niemand an der Zwischenstation.</small></label>
                </section>
            </fieldset>
            <div class="zero-setup-footer">
                <button v-if="!roomOnly" type="button" class="zero-link" :disabled="busy" @click="activeStep = null">Zur Aufgabenübersicht</button>
                <button type="button" class="zero-button" :disabled="busy" @click="finishStep">Übernehmen</button>
            </div>
        </form>
        <section v-else-if="!viewOnly && completed.some(Boolean)" class="zero-setup-summary" aria-labelledby="setup-summary">
            <h3 id="setup-summary">{{ allCompleted ? 'Alles bereit?' : 'Übernommene Angaben' }}</h3>
            <section v-if="completed[0]" class="zero-confirmed-section" aria-label="Bestätigte Matura und Klassen">
                <span class="zero-eyebrow">✓ MATURA & KLASSEN ÜBERNOMMEN</span>
                <p class="zero-confirmed-name">{{ form.name }}</p>
                <p class="zero-confirmed-value">{{ formattedDate }}</p>
                <span class="zero-help">Teilnehmende Klassen</span>
                <p class="zero-confirmed-value">{{ selectedClassOptions.join(', ') || 'Nur manuelle Schüler' }}</p>
            </section>
            <section v-if="completed[1]" class="zero-confirmed-section" aria-label="Bestätigte Räume und Schüler">
                <span class="zero-eyebrow">✓ RÄUME & SCHÜLER ÜBERNOMMEN</span>
                <p class="zero-confirmed-value">{{ form.rooms.length }} Räume · {{ totalStudents }} Schüler insgesamt</p>
                <div class="zero-confirmed-rooms">
                    <article v-for="item in form.rooms" :key="item.key" class="zero-confirmed-room">
                        <h4>{{ item.name }}</h4><p class="zero-confirmed-value">{{ studentCount(item) }} Schüler</p>
                        <details v-if="studentCount(item)">
                            <summary>Schüler anzeigen</summary>
                            <section v-for="group in confirmedStudentGroups(item)" :key="group.schoolClass" class="mt-3">
                                <h5>{{ group.schoolClass }}</h5><ul><li v-for="student in group.students" :key="student.id">{{ student.last_name }} {{ student.first_name }}</li></ul>
                            </section>
                            <section v-if="manualStudents(item).length" class="mt-3"><h5>Manuell hinzugefügt</h5><ul><li v-for="(student, index) in sortedManualStudents(item)" :key="index">{{ student.name }}</li></ul></section>
                        </details>
                    </article>
                </div>
            </section>
            <section v-if="completed[2]" class="zero-confirmed-section" aria-label="Bestätigter Ablauf">
                <span class="zero-eyebrow">✓ ABLAUF ÜBERNOMMEN</span>
                <p class="zero-confirmed-value">{{ form.waiting_places }} Warteplätze + 1 Toilettenplatz</p>
                <p class="zero-help">Die Matura wird als Vorbereitung gespeichert. Aufsichten zuordnen und starten kannst du danach.</p>
            </section>
            <p v-if="!allCompleted" class="zero-help">Offene oder geänderte Aufgaben bitte noch mit „Übernehmen“ bestätigen.</p>
            <button v-if="allCompleted" type="button" class="zero-button mt-4" :disabled="busy" @click="save">{{ busy ? 'Wird gespeichert …' : 'Matura speichern' }}</button>
        </section>
    </section>
</template>
<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { restoreSetupDraft, selectedSetupClasses } from './maturaSetupDraft'
const props = defineProps({ roster: { type: Array, default: () => [] }, schoolyearId: Number, existing: Object, busy: Boolean, draft: Object, viewOnly: Boolean, initialStep: { type: Number, default: null }, roomOnly: Boolean, initialRoomIndex: { type: Number, default: 0 } })
const emit = defineEmits(['save', 'cancel', 'draft', 'step'])
const restoredDraft = props.draft ? restoreSetupDraft(props.draft, props.roster) : null
const tasks = [
    { title: 'Matura & Klassen', description: props.viewOnly ? 'Name, Datum und teilnehmende Klassen ansehen.' : 'Name, Datum und teilnehmende Klassen festlegen.' },
    { title: 'Räume & Schüler', description: props.viewOnly ? 'Räume und zugeordnete Schüler ansehen.' : 'Räume anlegen und Schüler zuordnen.' },
    { title: 'Ablauf', description: props.viewOnly ? 'Aufsichten, Koordination und Protokoll öffnen.' : 'Warteplätze an der Zwischenstation festlegen.' },
]
const activeStep = ref(props.initialStep ?? (props.viewOnly ? 2 : restoredDraft?.activeStep ?? null))
const savedDate = computed(() => new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(props.existing.session.exam_date)))
const savedClasses = computed(() => [...new Set((props.existing?.students ?? []).map((student) => student.class_name).filter(Boolean))].sort((first, second) => first.localeCompare(second, 'de-AT', { numeric: true })))
function savedRoomStudents(id) { return (props.existing.students ?? []).filter((student) => student.matura_room_id === id).sort((first, second) => first.name.localeCompare(second.name, 'de-AT')) }
const completed = reactive(restoredDraft?.completed ?? (props.existing ? [true, true, true] : [false, false, false]))
const roomIndex = ref(props.roomOnly ? props.initialRoomIndex : restoredDraft?.roomIndex ?? 0)
const stepForm = ref(null)
const validationError = ref('')
const assignmentNotice = ref(restoredDraft?.removedStudents ? 'Einige Schüler sind nicht mehr im aktuellen Import oder Klassenpool verfügbar. Bitte prüfe die Raumzuordnung und übernimm sie erneut.' : '')
let roomKey = 0
function blankRoom() { return { key: ++roomKey, name: '', student_ids: [], classFilter: '', search: '', manualText: '' } }
const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Vienna' }).format(new Date())
const form = reactive({ name: props.existing?.session.name || '', exam_date: props.existing?.session.exam_date?.slice(0, 10) || today, waiting_places: props.existing?.session.waiting_places ?? 1, rooms: props.existing && !props.viewOnly ? props.existing.rooms.map((room) => ({ ...blankRoom(), name: room.name, student_ids: props.existing.students.filter((student) => student.matura_room_id === room.id && student.import116_id).map((student) => student.import116_id), manualText: props.existing.students.filter((student) => student.matura_room_id === room.id && !student.import116_id).map((student) => student.name).join('\n') })) : [blankRoom()] })
if (restoredDraft) Object.assign(form, restoredDraft.form, { rooms: restoredDraft.form.rooms.map((item) => ({ ...item, key: ++roomKey })) })
form.selected_classes = selectedSetupClasses(form, props.roster)
pruneAssignments()
const room = computed(() => form.rooms[roomIndex.value])
const studentCollator = new Intl.Collator('de-AT', { numeric: true, sensitivity: 'base' })
const orderedRoster = computed(() => [...props.roster].sort((first, second) => studentCollator.compare(first.class ?? '', second.class ?? '')
    || studentCollator.compare(first.last_name, second.last_name) || studentCollator.compare(first.first_name, second.first_name) || first.id - second.id))
const classes = computed(() => [...new Set(orderedRoster.value.map((student) => student.class).filter(Boolean))])
const selectedClassOptions = computed(() => classes.value.filter((schoolClass) => form.selected_classes.includes(schoolClass)))
const studentPool = computed(() => orderedRoster.value.filter((student) => form.selected_classes.includes(student.class)))
const visibleStudentGroups = computed(() => studentGroups(room.value))
const allCompleted = computed(() => completed.every(Boolean))
const totalStudents = computed(() => form.rooms.reduce((count, item) => count + studentCount(item), 0))
const formattedDate = computed(() => new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(form.exam_date)))
function manualStudents(item) { return item.manualText.split('\n').map((name) => name.trim()).filter(Boolean).map((name) => ({ name })) }
function studentCount(item) { return item.student_ids.length + manualStudents(item).length }
function filteredStudents(item) { return studentPool.value.filter((student) => (!item.classFilter || student.class === item.classFilter) && `${student.last_name} ${student.first_name}`.toLocaleLowerCase().includes(item.search.toLocaleLowerCase())) }
function studentGroups(item) {
    return groupStudents(filteredStudents(item))
}
function confirmedStudentGroups(item) { return groupStudents(orderedRoster.value.filter((student) => item.student_ids.includes(student.id))) }
function sortedManualStudents(item) { return manualStudents(item).sort((first, second) => studentCollator.compare(first.name, second.name)) }
function groupStudents(students) {
    const groups = []
    for (const student of students) {
        if (groups.at(-1)?.schoolClass !== student.class) groups.push({ schoolClass: student.class, students: [] })
        groups.at(-1).students.push(student)
    }
    return groups
}
function assignedElsewhere(id, item) { return form.rooms.some((other) => other !== item && other.student_ids.includes(id)) }
function classStudents(schoolClass) { return props.roster.filter((student) => student.class === schoolClass) }
function toggleClass(schoolClass) {
    form.selected_classes = form.selected_classes.includes(schoolClass)
        ? form.selected_classes.filter((item) => item !== schoolClass) : [...form.selected_classes, schoolClass]
}
function pruneAssignments() {
    const ids = new Set(props.roster.filter((student) => form.selected_classes.includes(student.class)).map((student) => student.id))
    let removed = 0
    for (const [index, item] of form.rooms.entries()) {
        if (props.roomOnly && index !== roomIndex.value) continue
        const retained = item.student_ids.filter((id) => ids.has(id))
        removed += item.student_ids.length - retained.length
        if (retained.length !== item.student_ids.length) item.student_ids = retained
        if (!form.selected_classes.includes(item.classFilter)) item.classFilter = ''
    }
    if (removed) assignmentNotice.value = `${removed} Schülerzuordnungen wurden entfernt, weil sie nicht mehr zu den ausgewählten Klassen gehören. Manuell hinzugefügte Namen bleiben erhalten.`
}
function chooseRoom(index) { roomIndex.value = index; validationError.value = '' }
function addRoom() { if (form.rooms.length < 40) { form.rooms.push(blankRoom()); chooseRoom(form.rooms.length - 1) } }
function removeRoom() { if (form.rooms.length > 1) { form.rooms.splice(roomIndex.value, 1); chooseRoom(Math.min(roomIndex.value, form.rooms.length - 1)) } }
function canOpen(index) { return props.roomOnly ? index === 1 : props.viewOnly || index === 0 || completed.slice(0, index).every(Boolean) }
function openStep(index) {
    if (!props.busy && canOpen(index)) {
        activeStep.value = index
        validationError.value = ''
        if (props.viewOnly) emit('step', index)
    }
}
function invalidate(index) { for (let current = index; current < completed.length; current++) completed[current] = false }
watch(() => [form.name, form.exam_date, form.selected_classes], () => invalidate(0), { deep: true, flush: 'sync' })
watch(() => form.selected_classes, pruneAssignments, { deep: true, flush: 'sync' })
watch(() => JSON.stringify(form.rooms.map((item) => ({ name: item.name, student_ids: item.student_ids, manualText: item.manualText }))), () => invalidate(1), { flush: 'sync' })
watch(() => form.waiting_places, () => invalidate(2), { flush: 'sync' })
watch(() => ({ form: { ...form, rooms: form.rooms.map(({ key, ...item }) => item) }, completed, activeStep: activeStep.value, roomIndex: roomIndex.value }), (draft) => { if (!props.viewOnly) emit('draft', JSON.parse(JSON.stringify(draft))) }, { deep: true, immediate: true, flush: 'sync' })
function preventInputSubmit(event) { if (event.target.tagName === 'INPUT' && event.target.type !== 'checkbox') event.preventDefault() }
function validateStep() {
    validationError.value = ''
    if (activeStep.value === 0 && !form.name.trim()) { validationError.value = 'Bitte gib der Matura einen Namen.'; return false }
    if (activeStep.value === 1) {
        const invalidRoom = form.rooms.findIndex((item, index) => {
            if (props.roomOnly && index !== roomIndex.value) return false
            if (!item.name.trim() || item.name.length > 80) { validationError.value = 'Bitte gib jedem Raum einen Namen (höchstens 80 Zeichen).'; return true }
            if (form.rooms.some((other) => other !== item && other.name.trim() === item.name.trim())) { validationError.value = 'Bitte verwende für jeden Raum einen eigenen Namen.'; return true }
            if (item.student_ids.length > 500 || manualStudents(item).length > 200) { validationError.value = 'Pro Raum sind höchstens 500 Schüler aus dem Import und 200 weitere Schüler möglich.'; return true }
            if (manualStudents(item).some((student) => student.name.length > 180)) { validationError.value = 'Ein Schülername darf höchstens 180 Zeichen haben.'; return true }
            return false
        })
        if (invalidRoom !== -1) { roomIndex.value = invalidRoom; return false }
    }
    if (activeStep.value === 2 && (!Number.isInteger(form.waiting_places) || form.waiting_places < 0 || form.waiting_places > 10)) { validationError.value = 'Bitte wähle 0 bis 10 ganze Warteplätze.'; return false }
    return stepForm.value.reportValidity()
}
function finishStep() {
    if (props.busy || !validateStep()) return
    if (props.existing) {
        emitSave()
        return
    }
    completed[activeStep.value] = true
    activeStep.value = null
}
function save() {
    if (props.busy || !allCompleted.value || activeStep.value !== null) return
    emitSave()
}
function emitSave() {
    emit('save', { schoolyear_id: props.schoolyearId, name: form.name.trim(), exam_date: form.exam_date, waiting_places: form.waiting_places, rooms: form.rooms.map((item) => ({ name: item.name.trim(), student_ids: [...item.student_ids], manual_students: manualStudents(item) })) })
}
</script>
<style scoped>
.zero-setup { max-width: 1000px; margin-inline: auto; }
.zero-setup-tasks { display: grid; grid-template-columns: repeat(3, 200px); gap: 12px; }
.zero-setup-task { aspect-ratio: 1; padding: 14px; border: 1px solid var(--zero-line); border-radius: 14px; background: #f7faf9; text-align: left; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; color: var(--zero-ink); cursor: pointer; }
.zero-setup-task strong { font-size: 1rem; }
.zero-setup-task > span:not(.zero-setup-task-number) { font-size: .78rem; line-height: 1.35; }
.zero-setup-task small { font-size: .72rem; line-height: 1.25; }
.zero-setup-task small:first-of-type { margin-top: auto; font-weight: 750; }
.zero-setup-task-number { display: grid; place-items: center; width: 26px; height: 26px; flex-shrink: 0; border-radius: 50%; background: #e4eeeb; font-weight: 750; }
.zero-setup-task.active { border-color: var(--zero-teal); background: #edf7f3; box-shadow: 0 0 0 1px var(--zero-teal); }
.zero-setup-task.complete .zero-setup-task-number { background: var(--zero-teal); color: white; }
.zero-setup-task:disabled { opacity: .55; cursor: not-allowed; }
.zero-setup-fields { border: 0; padding: 0; min-width: 0; margin: 0; }
.zero-setup-rooms { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0; }
.zero-setup-rooms button small { margin-left: 8px; font-weight: 400; }
.zero-setup-rooms [aria-current=true] { border-color: var(--zero-teal); background: #dcefe9; }
.zero-setup-classes { display: flex; flex-wrap: wrap; gap: 10px; margin: 16px 0; }
.zero-setup-classes button { position: relative; align-items: flex-start; flex-direction: column; gap: 5px; min-width: 160px; padding-right: 42px; }
.zero-setup-classes button small { font-size: .72rem; font-weight: 400; }
.zero-setup-classes .zero-class-selected { border-color: var(--zero-teal); background: var(--zero-teal); color: white !important; box-shadow: 0 0 0 2px var(--zero-teal); }
.zero-setup-classes .zero-class-selected strong, .zero-setup-classes .zero-class-selected small, .zero-setup-classes .zero-class-selected::after { color: white !important; }
.zero-setup-classes .zero-class-selected::after { content: '✓'; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 1.3rem; font-weight: 750; }
.zero-roster-class { grid-column: 1 / -1; text-align: left; padding: 10px 12px; background: #edf4f3; font-size: .8rem; color: var(--zero-teal); }
.zero-roster--single-class { max-height: none; overflow: visible; }
.zero-setup-capacity { max-width: 340px; }
.zero-setup-summary { padding: 20px; border: 1px solid var(--zero-line); border-radius: 12px; background: #f7faf9; }
.zero-setup-summary p { margin-top: 12px; }
.zero-confirmed-section { margin-top: 22px; }
.zero-setup-summary .zero-confirmed-name { font-size: clamp(1.7rem, 4vw, 2.3rem); font-weight: 750; line-height: 1.2; overflow-wrap: anywhere; margin: 8px 0 14px; }
.zero-setup-summary .zero-confirmed-value { font-size: 1.3rem; font-weight: 650; line-height: 1.4; margin: 8px 0 16px; }
.zero-confirmed-rooms { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 12px; }
.zero-confirmed-room { padding: 18px; border: 1px solid var(--zero-line); border-radius: 12px; background: white; }
.zero-confirmed-room h4 { font-size: 1.3rem; overflow-wrap: anywhere; }
.zero-confirmed-room h5 { font-size: .9rem; color: var(--zero-teal); }
.zero-confirmed-room summary { font-size: .9rem; cursor: pointer; }
.zero-confirmed-room ul { list-style: none; padding: 0; margin: 6px 0 14px; }
.zero-confirmed-room li { padding: 4px 0; font-size: .95rem; overflow-wrap: anywhere; }
.zero-setup-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--zero-line); }
@media (max-width: 760px) {
    .zero-setup-tasks { grid-template-columns: repeat(2, 200px); }
}
@media (max-width: 520px) {
    .zero-setup-tasks { grid-template-columns: minmax(0, 200px); }
}
</style>
