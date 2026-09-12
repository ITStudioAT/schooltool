<template>
    <section class="student-performance" :aria-label="`Leistungen von ${student.first_name} ${student.last_name}`">
        <div v-if="!courseMatchesSchoolyear" class="text-caption" role="status">Kurse für das ausgewählte Schuljahr werden geladen …</div>
        <div v-else-if="loading" class="text-caption" role="status">Leistungen werden geladen …</div>
        <div v-else-if="loadFailed" class="text-caption text-error" role="alert">
            Leistungen konnten nicht vollständig geladen werden.
        </div>
        <template v-else>
            <span v-if="!hasYearBounds" class="text-caption text-warning">Schuljahresgrenzen fehlen: keine datierten Summen.</span>
            <v-chip v-if="summaryData.unassignedCount" size="x-small" color="warning" variant="outlined">
                Ohne Zeitraumzuordnung: {{ summaryData.unassignedCount }} (nicht mitgezählt)
            </v-chip>
            <template v-for="(groups, rowIndex) in performanceRows" :key="rowIndex">
                <div v-if="groups.length || (rowIndex === 0 && (assignedGrades.length || studentEvaluations.length))" class="performance-row">
                    <div v-if="rowIndex === 0 && (assignedGrades.length || studentEvaluations.length)" class="performance-group" role="group" aria-label="Noten und Beurteilungen">
                        <CourseStudentHoverDetails v-for="grade in assignedGrades" :key="grade.label" :title="grade.label" content-class="student-performance-tooltip">
                            <template #activator="{ props: activatorProps }">
                                <v-chip v-bind="activatorProps" size="x-small" color="success" variant="tonal" tabindex="0">
                                    {{ grade.label }}: {{ grade.value }}
                                </v-chip>
                            </template>
                            <div>Bewertung: {{ grade.value }}</div>
                        </CourseStudentHoverDetails>
                        <CourseStudentHoverDetails v-for="evaluation in studentEvaluations" :key="evaluation.id" :title="evaluation.category_name" content-class="student-performance-tooltip">
                            <template #activator="{ props: activatorProps }">
                                <v-chip v-bind="activatorProps" size="x-small" color="success" variant="tonal" tabindex="0">
                                    {{ evaluation.category_name }} · {{ Number(evaluation.semester) === 3 ? 'Gesamtjahr' : `Sem ${evaluation.semester}` }}: {{ evaluation.value }}
                                </v-chip>
                            </template>
                            <div>Bewertung: {{ evaluation.value }}</div>
                        </CourseStudentHoverDetails>
                    </div>
                    <div v-for="group in groups" :key="group.category" class="performance-group" role="group" :aria-label="group.category">
                        <CourseStudentHoverDetails v-for="summary in group.summaries" :key="summary.key" :title="summary.label"
                            :subtitle="`${group.category} · ${formatDate(summary.details[0].date)}`"
                            :details-label="`Eintrag: ${summary.label}`"
                            content-class="student-performance-tooltip">
                            <template #activator="{ props: activatorProps }">
                                <v-chip v-bind="activatorProps" size="x-small" tabindex="0"
                                    :color="group.category === 'Benotung' ? 'primary' : 'warning'" variant="tonal"
                                    :aria-label="summary.label + ': ' + (summary.values || 'Eintrag') + ', ' + formatDate(summary.details[0].date)">
                                    {{ summary.type }}{{ summary.values ? `: ${summary.values}` : '' }}
                                </v-chip>
                            </template>
                            <div class="performance-detail-list">
                                <div v-for="(detail, index) in summary.details" :key="index" class="performance-detail-item">
                                    <div class="d-flex flex-wrap ga-2">
                                        <strong>{{ formatDate(detail.date) }}</strong>
                                        <span v-if="detail.grade">Ergebnis: {{ detail.grade }}</span>
                                        <span v-if="detail.status">{{ detail.status }}</span>
                                    </div>
                                    <div v-if="detail.title" class="font-weight-bold">{{ detail.title }}</div>
                                    <div v-if="detail.description" class="performance-detail-text">Aufgabe: {{ detail.description }}</div>
                                    <div v-if="detail.comment" class="performance-detail-text">Kommentar: {{ detail.comment }}</div>
                                    <div v-if="detail.dueDate">Fällig: {{ formatDate(detail.dueDate) }} {{ detail.dueTime }}</div>
                                    <div v-if="detail.doneDate">Erledigt: {{ formatDate(detail.doneDate) }}</div>
                                </div>
                            </div>
                        </CourseStudentHoverDetails>
                    </div>
                </div>
            </template>
            <div v-if="!summaryData.groups.length && !summaryData.unassignedCount && !studentEvaluations.length && !assignedGrades.length && !hasStars && hasYearBounds" class="text-caption text-medium-emphasis">
                Keine Leistungen im Zeitraum.
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import CourseStudentHoverDetails from './CourseStudentHoverDetails.vue'
import { teachingCourseMatchesSchoolyear, teachingDateKey, teachingPerformanceDateScope, teachingStarDateScope } from '@/helpers/teachingSemester'

const props = defineProps({
    student: { type: Object, required: true },
    course: { type: Object, required: true },
    entries: { type: Array, default: () => [] },
    behaviourEntries: { type: Array, default: () => [] },
    works: { type: Array, default: () => [] },
    evaluations: { type: Array, default: () => [] },
    schema: { type: Object, default: null },
    usesEntryAreas: { type: Boolean, default: false },
    activeSemester: { type: Number, default: 3 },
    semesterTwoStartDate: { type: String, default: null },
    semesterCount: { type: Number, default: 2 },
    schoolyear: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    loadFailed: { type: Boolean, default: false },
})

function sameId(left, right) {
    return left != null && right != null && String(left) === String(right)
}

function belongsToStudent(entry) {
    const userId = 'user_id' in props.student ? props.student.user_id : props.student.id
    return sameId(entry.teaching_course_id, props.course.id) && sameId(entry.user_id, userId)
}

const courseMatchesSchoolyear = computed(() => teachingCourseMatchesSchoolyear(props.course, props.schoolyear))
const hasYearBounds = computed(() => {
    const from = teachingDateKey(props.schoolyear?.from)
    const until = teachingDateKey(props.schoolyear?.until)
    return from && until && from <= until
})

const assignedGrades = computed(() => [
    { label: 'Note', value: props.student.sem_grade, semester: props.semesterCount === 1 ? 1 : 3 },
    { label: 'Note Sem 1', value: props.student.sem_1_grade, semester: 1 },
    { label: 'Note Sem 2', value: props.student.sem_2_grade, semester: 2 },
    { label: 'Verhalten', value: props.student.behaviour_grade, semester: props.semesterCount === 1 ? 1 : 3 },
    { label: 'Verhalten Sem 1', value: props.student.behaviour_1_grade, semester: 1 },
    { label: 'Verhalten Sem 2', value: props.student.behaviour_2_grade, semester: 2 },
].filter((grade) => grade.value != null && grade.value !== '' && (props.activeSemester === 3 || props.activeSemester === grade.semester)))

const studentEvaluations = computed(() => props.evaluations.filter((evaluation) =>
    belongsToStudent(evaluation) && (props.activeSemester === 3 || Number(evaluation.semester) === props.activeSemester),
))
const hasStars = computed(() => props.student.stars?.some((star) => teachingStarDateScope(star.date, props.activeSemester, props.semesterTwoStartDate) === 'included'))

function formatDate(date) {
    const key = teachingDateKey(date)
    return key ? key.split('-').reverse().join('.') : 'Kein Datum'
}

function workStudentComment(work) {
    const userId = 'user_id' in props.student ? props.student.user_id : props.student.id
    const group = work?.groups?.find((item) => item.student_ids?.some((id) => sameId(id, userId)))
    const comments = group?.comments
    const savedComment = Array.isArray(comments)
        ? comments.find((item) => sameId(item.student_id, userId))?.comment
        : comments?.[userId]
    const comment = savedComment && typeof savedComment === 'object' ? savedComment.comment : savedComment
    return String(comment || group?.comment || '').trim()
}

const summaryData = computed(() => {
    const grouped = new Map()
    let unassignedCount = (props.student.stars || []).filter((star) => teachingStarDateScope(star.date, props.activeSemester, props.semesterTwoStartDate) === 'unassigned').length
    const entries = [
        ...props.entries.filter(belongsToStudent).map((entry) => ({ entry, legacy: false })),
        ...props.behaviourEntries.filter(belongsToStudent).map((entry) => ({ entry, legacy: true })),
    ]
    for (const [index, { entry, legacy }] of entries.entries()) {
        const work = props.works.find((item) => sameId(item.teaching_course_id, props.course.id) && sameId(item.id, entry.teaching_course_work_id))
        const finishDate = teachingDateKey(work?.finish_until_date)
        const displayDate = !legacy && entry.source === 'course_work' && finishDate ? finishDate : entry.date
        const dateScope = teachingPerformanceDateScope(displayDate, props.activeSemester, props.semesterTwoStartDate, props.schoolyear)
        if (dateScope === 'unassigned') unassignedCount++
        if (dateScope !== 'included') continue

        const definitions = legacy
            ? (entry.kind === 'notification' ? props.course.teacher_teaching_notifications : props.course.teacher_teaching_behaviour) || []
            : (props.usesEntryAreas ? props.course.teaching_entry_area?.entry_definitions || [] : props.schema?.works || [])
        const definition = definitions.find((item) => item.short_name === entry.type)
        const category = legacy ? (entry.kind === 'notification' ? 'Erinnerungen' : 'Verhalten') : definition?.category || 'Benotung'
        const key = `${legacy ? 'legacy' : 'entry'}-${entry.kind || ''}-${entry.id ?? index}-${index}`
        if (!grouped.has(category)) grouped.set(category, new Map())
        const summaries = grouped.get(category)
        summaries.set(key, {
            key,
            type: entry.type || 'Eintrag',
            label: definition?.name ? `${entry.type} · ${definition.name}` : entry.type || 'Eintrag',
            sortName: definition?.name || entry.type || 'Eintrag',
            values: '',
            details: [],
        })
        const summary = summaries.get(key)
        const defaultGrade = !props.usesEntryAreas && definition?.grades?.some((grade) => String(grade.grade) === String(definition.default_grade))
            ? definition.default_grade : ''
        const directGrade = String(entry.effective_grade ?? '').trim() || String(entry.grade ?? '').trim()
        const grade = legacy && entry.kind === 'notification'
            ? (entry.done_date ? 'Erledigt' : 'Offen')
            : directGrade || defaultGrade || (legacy || definition?.has_properties === false ? '' : 'Offen')
        summary.values = String(grade)
        const sourceWork = !legacy && entry.source === 'course_work' ? work : null
        summary.details.push({
            date: teachingDateKey(displayDate),
            grade: directGrade,
            status: legacy && entry.kind === 'notification' ? (entry.done_date ? 'Erledigt' : 'Offen') : '',
            title: sourceWork?.title || '',
            description: sourceWork?.description || '',
            comment: sourceWork ? workStudentComment(sourceWork) : String(entry.description || '').trim(),
            dueDate: legacy && entry.kind === 'notification' ? entry.due_date : null,
            dueTime: legacy && entry.kind === 'notification' ? entry.due_time : null,
            doneDate: legacy && entry.kind === 'notification' ? entry.done_date : null,
        })
    }
    const categoryOrder = ['Benotung', 'Verhalten', 'Weitere', 'Erinnerungen']
    const groups = [...grouped].sort(([left], [right]) =>
        (categoryOrder.indexOf(left) + 1 || 99) - (categoryOrder.indexOf(right) + 1 || 99),
    ).map(([category, summaries]) => ({
        category,
        summaries: [...summaries.values()].sort((left, right) =>
            left.sortName.localeCompare(right.sortName, 'de-AT', { sensitivity: 'base', numeric: true })
            || left.type.localeCompare(right.type, 'de-AT')
            || left.details[0].date.localeCompare(right.details[0].date),
        ),
    }))
    return { groups, unassignedCount }
})

const performanceRows = computed(() => [
    summaryData.value.groups.filter((group) => group.category === 'Benotung'),
    summaryData.value.groups.filter((group) => group.category === 'Verhalten'),
    summaryData.value.groups.filter((group) => !['Benotung', 'Verhalten'].includes(group.category)),
])
</script>

<style scoped>
.student-performance {
    display: flex;
    flex: 1 1 280px;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    min-width: 0;
}

.performance-row {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-width: 100%;
}

.performance-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    max-width: 100%;
    border: 1px solid rgba(37, 99, 235, 0.12);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.7);
}

.performance-group :deep(.v-chip) {
    height: auto;
    min-height: 20px;
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
}

.performance-group :deep(.v-chip:focus-visible) {
    outline: 2px solid currentColor;
    outline-offset: 2px;
}

.performance-detail-item {
    padding: 10px 0;
}

.performance-detail-item + .performance-detail-item {
    border-top: 1px solid #e2e8f0;
}

.performance-detail-text {
    white-space: pre-wrap;
}
</style>
