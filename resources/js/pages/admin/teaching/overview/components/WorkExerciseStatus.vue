<template>
    <span v-if="work?.id && (needsPreparation || historical)" class="d-inline-flex" :class="{ 'cursor-pointer': accessible }"
        :tabindex="accessible ? 0 : undefined" :role="accessible ? 'button' : undefined" :aria-disabled="disabled"
        :aria-label="tooltip" :title="tooltip" @click.stop="openDialog"
        @keydown.enter.stop.prevent="openDialog" @keydown.space.stop.prevent="openDialog">
        <v-icon :size="size" :color="color" :aria-label="tooltip" :aria-hidden="false" role="img">
            mdi-clipboard-text-outline
        </v-icon>
        <v-tooltip activator="parent" location="top" :text="tooltip" :open-delay="200" :max-width="420" />
    </span>
    <v-dialog v-if="opened && accessible" v-model="opened" persistent scrollable max-width="720">
        <v-card>
            <v-card-title class="text-wrap">Leistungsfeststellung · {{ work.title || 'Ohne Titel' }}</v-card-title>
            <v-card-text>
                <v-alert :type="historical || assignmentReady || prepared ? 'success' : 'warning'" variant="tonal" class="mb-4">{{ historical ? 'Bestehende Leistungsfeststellung – gespeicherte Originalnachweise vorhanden.' : prepared ? 'Leistungsfeststellung vorbereitet.' : assignmentReady ? 'Aufgabenstellung erstellt – Prüfungsordner bestätigt.' : 'Prüfungsordner bestätigt; die Aufgabenstellung ist noch nicht bestätigt.' }}</v-alert>
                <p v-if="checkTime" class="text-caption mb-3">Letzter Check: {{ checkTime }}</p>
                <dl class="exercise-info">
                    <template v-for="item in details" :key="item.label">
                        <dt>{{ item.label }}</dt><dd>{{ item.value }}</dd>
                    </template>
                </dl>
                <template v-if="work.description">
                    <h3 class="text-subtitle-2 mt-4 mb-1">Beschreibung</h3>
                    <p class="exercise-info-description text-body-2">{{ work.description }}</p>
                </template>
                <h3 class="text-subtitle-2 mt-4 mb-1">Prüfungsordner</h3>
                <p class="exercise-info-path text-body-2">{{ folder?.path || folder?.name || 'Der Prüfungsordner ist derzeit nicht bestätigt. Gespeicherte Nachweise bleiben erhalten.' }}</p>
                <div v-if="folder?.id && !prepared" class="d-flex flex-wrap ga-2 mt-4" aria-label="Prüfungsunterlagen">
                    <WorkExercisePdf v-for="section in ['Angaben', 'Bewertungskriterien']" :key="section" :work="work" :date="date" :section="section" />
                    <WorkEvaluationPdf :work="work" />
                </div>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <v-spacer />
                <v-btn v-if="prepared && canCreate" @click="opened = false; emit('create')">Leistungsfeststellung erstellen</v-btn>
                <v-btn @click="opened = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workLinkedMcTestTitle, workNeedsPreparation } from '@/helpers/workOverview'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { activeStudentIds, workParticipantIds } from '@/helpers/workCellStatus'
import { workHasProgress } from '@/helpers/workProgress'
import WorkExercisePdf from './WorkExercisePdf.vue'
import WorkEvaluationPdf from './WorkEvaluationPdf.vue'

const props = defineProps({ work: Object, course: Object, date: { type: String, default: '' }, state: Object, size: { type: [Number, String], default: 30 }, disabled: Boolean, canCreate: Boolean, recheck: Function })
const emit = defineEmits(['create'])
const admin = useAdminStore()
const binding = computed(() => workDropboxStates.get(workDropboxKey(admin.config?.user?.id, props.work)))
const historical = computed(() => workAutomationEnabled(props.work) && workHasProgress(props.work) && !needsPreparation.value)
const needsPreparation = computed(() => workNeedsPreparation(props.work,
    binding.value))
const folder = computed(() => {
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    const cached = workExerciseCheck(key, props.work, props.date)
    const currentFolder = status.value === 'found' ? props.state?.folder : null
    const checkedFolder = cached?.status === 'found' ? cached.folder : null
    return currentFolder || checkedFolder || (historical.value ? binding.value?.legacy_folders?.exercise || binding.value?.folder : null)
})
const accessible = computed(() => !props.disabled && (historical.value || status.value === 'found' || (props.canCreate && status.value === 'missing')))
const opened = ref(false)
const checkTime = computed(() => props.state?.checkedAt ? new Intl.DateTimeFormat('de-AT', {
    timeZone: 'Europe/Vienna', dateStyle: 'short', timeStyle: 'short',
}).format(new Date(props.state.checkedAt)) : '')
const status = computed(() => {
    const state = props.state
    if (!state || state.userId !== admin.config?.user?.id || String(state.workId) !== String(props.work?.id)
        || String(state.courseId) !== String(props.work?.teaching_course_id) || state.date !== props.date) return 'checking'
    return state.status
})
const assignmentReady = computed(() => props.state?.assignmentStatus === 'ready')
const prepared = computed(() => status.value === 'found' && props.state?.preparation?.status === 'ready')
const color = computed(() => props.disabled ? 'grey' : historical.value || (status.value === 'found' && (assignmentReady.value || prepared.value)) ? 'success'
    : status.value === 'found' ? 'warning' : status.value === 'missing' ? 'error' : 'grey')
const tooltip = computed(() => {
    if (props.disabled) return 'Zuerst den Lernmaterialordner verknüpfen'
    if (historical.value) return folder.value?.id ? `Leistungsfeststellung erstellt: ${folder.value.name}`
        : 'Bestehende Leistungsfeststellung – gespeicherte Nachweise ansehen'
    if (prepared.value) return 'Leistungsfeststellung vorbereitet'
    if (status.value === 'found') return assignmentReady.value ? `Leistungsfeststellung erstellt: ${props.state.folder?.name || ''}`
        : 'Prüfungsordner vorhanden; Aufgabenstellung noch nicht bestätigt'
    if (status.value === 'missing') return 'Leistungsfeststellung noch nicht erstellt'
    if (status.value === 'unknown') return 'Leistungsfeststellung konnte nicht geprüft werden'
    return 'Leistungsfeststellung wird geprüft …'
})

const course = computed(() => String(props.course?.id) === String(props.work?.teaching_course_id) ? props.course : null)
const groups = computed(() => (props.work?.groups || []).map((group, index) => ({ ...group, index }))
    .filter(group => !props.date || (group.date || props.work.date_for_all_groups) === props.date))
const linkedMcTests = computed(() => {
    const slots = [props.date || props.work?.date_for_all_groups || 'all', 'all']
    return [...new Map(slots.map(slot => [String(props.work?.status?.multiple_choice_tests?.[slot]?.test_id),
        workLinkedMcTestTitle(props.work, slot)]).filter(([, title]) => title)).values()]
})
const participants = computed(() => {
    if (!Array.isArray(course.value?.students_info)) return null
    if ((props.work?.groups || []).length) {
        if (!groups.value.length) return null
        return workParticipantIds({ groups: groups.value }, course.value.students_info).length
    }
    return activeStudentIds(course.value.students_info).length
})
const details = computed(() => {
    const work = props.work
    const definition = course.value?.teaching_entry_area?.entry_definitions
        ?.find(entry => entry.category === 'Benotung' && entry.short_name === work.type)
    const maximumPoints = definition?.maximum_points
    const classes = Array.isArray(course.value?.classes) ? course.value.classes.join(', ') : course.value?.classes
    return [
        { label: 'Titel', value: work.title || 'Ohne Titel' },
        { label: 'Typ', value: [work.type, definition?.name].filter(Boolean).join(' · ') || 'Nicht angegeben' },
        { label: 'Kurs', value: course.value?.title || 'Nicht angegeben' },
        ...(classes ? [{ label: 'Klasse', value: classes }] : []),
        { label: 'Durchführung', value: displayDate(props.date || work.date_for_all_groups) },
        { label: 'Arbeitsform', value: work.is_group_work ? 'Gruppenarbeit' : 'Einzelarbeit' },
        ...(work.is_group_work && groups.value.length ? [{ label: 'Prüfungsgruppe', value: groups.value
            .map(group => group.name || `Gruppe ${group.index + 1}`).join(', ') }] : []),
        { label: 'Teilnehmer:innen aktuell', value: participants.value === null ? 'Nicht bestätigt' : String(participants.value) },
        ...(prepared.value ? [
            { label: 'Prüfungspunkte Aufgabenblatt', value: String(props.state.preparation.components.aufgabenblatt.points) },
            { label: 'Prüfungspunkte MC', value: props.state.preparation.components.multiple_choice.included ? String(props.state.preparation.components.multiple_choice.points) : 'Nicht einbezogen' },
            { label: 'Prüfungspunkte gesamt', value: String(props.state.preparation.total_points) },
        ] : []),
        ...(maximumPoints !== null && maximumPoints !== undefined && Number.isFinite(Number(maximumPoints))
            ? [{ label: prepared.value ? 'Schooltool-Bewertungsskala' : 'Maximale Punkte', value: new Intl.NumberFormat('de-AT').format(Number(maximumPoints)) }] : []),
        { label: 'Beurteilung', value: workAutomationEnabled(work) ? 'Automatische Beurteilung' : 'Manuelle Beurteilung' },
        ...(linkedMcTests.value.length ? [{ label: linkedMcTests.value.length === 1 ? 'MC-Test' : 'MC-Tests', value: linkedMcTests.value.join(', ') }] : []),
        { label: 'Hauptfrist', value: deadline(work.finish_until_date, work.finish_until_time) },
        { label: 'Mitgeteilte Frist', value: deadline(work.communicated_deadline?.date, work.communicated_deadline?.time, 'Nicht mitgeteilt') },
    ]
})

function displayDate(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(value || '') ? value.split('-').reverse().join('.') : 'Nicht angegeben'
}

function deadline(date, time, empty = 'Nicht festgelegt') {
    if (!date) return empty
    return `${displayDate(date)}${time ? `, ${time.slice(0, 5)} Uhr` : ''}`
}

async function openDialog() {
    if (!accessible.value) return
    if (props.recheck) {
        await props.recheck()
        await nextTick()
    }
    if (accessible.value && !historical.value && status.value === 'missing' && props.canCreate) {
        emit('create')
        return
    }
    if (accessible.value) opened.value = true
}

watch(() => [admin.config?.user?.id, props.work?.id, props.work?.teaching_course_id, props.date, status.value, props.state?.folder?.id],
    () => { opened.value = false })
watch(() => props.disabled, disabled => { if (disabled) opened.value = false })
</script>

<style scoped>
.exercise-info { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 8px 16px; }
.exercise-info dt { font-weight: 500; }
.exercise-info dd { margin: 0; overflow-wrap: anywhere; }
.exercise-info-path { overflow-wrap: anywhere; }
.exercise-info-description { white-space: pre-wrap; overflow-wrap: anywhere; }
@media (max-width: 480px) {
    .exercise-info { grid-template-columns: minmax(0, 1fr); gap: 4px; }
    .exercise-info dd { margin-bottom: 8px; }
}
</style>
