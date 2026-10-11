<template>
    <section class="student-performance" :aria-label="`Leistungen von ${student.first_name} ${student.last_name}`">
        <div v-if="!courseMatchesSchoolyear" class="text-caption" role="status">Kurse für das ausgewählte Schuljahr werden geladen …</div>
        <div v-else-if="loading" class="text-caption" role="status">Leistungen werden geladen …</div>
        <div v-else-if="loadFailed" class="text-caption text-error" role="alert">
            Leistungen konnten nicht vollständig geladen werden.
        </div>
        <template v-else>
            <div v-if="gradingPartSummaries.length" class="grading-part-cards" role="group" aria-label="Benotungsteile"
                :style="{ '--grading-part-columns': gradingPartColumns.length, '--grading-mobile-columns': Math.min(gradingPartColumns.length, 2) }">
                <div v-for="part in gradingPartSummaries" :key="part.key" class="grading-part-card"
                    :title="part.hint"
                    :style="{ ...gradingPartCardPosition(part), '--calculated-grade-color': part.gradeColor }"
                    :class="[{ 'grading-part-card--incomplete': part.incomplete && !part.balanceTone, 'grading-part-card--note': part.id === 'calculated-semester-grade', 'grading-part-card--calculated-note': part.calculatedGrade }, part.balanceTone ? `grading-part-card--${part.balanceTone}` : null]">
                    <span class="grading-part-card-title">{{ part.label }}:</span>
                    <strong class="grading-part-card-value">{{ part.value }}</strong>
                    <span v-if="part.detail" class="text-caption">{{ part.detail }}</span>
                </div>
            </div>
            <div class="performance-details">
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
                                    <WorkEvaluationPdf :work="detail.work" :student-id="student.user_id || student.id || ''" />
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
            </div>
            <div v-for="progress in assignmentsProgress" :key="progress.id" class="assignment-progress">
                <div class="assignment-progress-label">
                    <span>{{ progress.label }}</span>
                    <span :class="{ 'text-warning': progress.warning }">{{ progress.text }}</span>
                </div>
                <div class="assignment-progress-track">
                <v-progress-linear :model-value="progress.fill" :max="100" height="6" rounded
                    color="transparent" bg-color="grey-lighten-2" :bg-opacity="1"
                    :title="progress.bandLabel"
                    :aria-label="progress.label" :aria-valuetext="progress.text"
                    :aria-description="assignmentGradeDescription"
                    :aria-hidden="progress.percent === null ? true : undefined" />
                    <div v-if="progress.percent !== null" class="assignment-progress-fill" aria-hidden="true"
                        :style="{ backgroundImage: percentageProgressGradient, clipPath: `inset(0 ${100 - progress.fill}% 0 0)` }" />
                    <span v-for="band in assignmentGradeBoundaries" :key="band.grade" class="assignment-grade-boundary"
                        :style="{ left: `${band.min}%` }" :title="`Ab ${formatResult(band.min)} %: ${band.label}`" aria-hidden="true" />
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { percentageGradeBand, percentageProgressGradient, standardPercentageGrades } from '@/helpers/gradeCalculation'
import CourseStudentHoverDetails from './CourseStudentHoverDetails.vue'
import WorkEvaluationPdf from './WorkEvaluationPdf.vue'
import { teachingCourseMatchesSchoolyear, teachingDateKey, teachingPerformanceDateScope, teachingStarDateScope } from '@/helpers/teachingSemester'

const props = defineProps({
    student: { type: Object, required: true },
    course: { type: Object, required: true },
    entries: { type: Array, default: () => [] },
    behaviourEntries: { type: Array, default: () => [] },
    works: { type: Array, default: () => [] },
    evaluations: { type: Array, default: () => [] },
    gradingReport: { type: Object, default: null },
    showCalculatedGrade: { type: Boolean, default: false },
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
const gradingPartSummaries = computed(() => {
    const report = props.gradingReport
    if (!report || !sameId(report.course_id, props.course.id) || !sameId(report.schoolyear_id, props.schoolyear?.id)) return []
    const students = report.students || []
    const evaluation = students.find((item) => sameId(item.course_student_id, props.student.course_student_id))
        || students.find((item) => sameId(item.user_id, props.student.user_id))
        || students.find((item) => sameId(item.import116_id, props.student.import116_id))
    const cards = (evaluation?.semesters || [])
        .filter((semester) => Number(report.semester_count) === 1 || props.activeSemester === 3 || Number(semester.semester) === props.activeSemester)
        .flatMap((semester) => {
            const cards = (semester.parts || []).filter((part) => {
            const types = part.types || []
            const emptyOptionalStandardGrade = part.config?.is_required === false && types.length > 0
                && types.every((type) => type.config?.calculation_mode === 'grades' && Array.isArray(type.entries) && type.entries.length === 0)
            return !emptyOptionalStandardGrade
        }).map((part) => {
            const label = props.activeSemester === 3 && Number(report.semester_count) > 1 ? `${part.name} · Sem ${semester.semester}` : part.name
            let value = 'Keine Bewertung'
            let detail = ''
            let hint = ''
            const gradeMethodPending = ['grade_each', 'grade_mean'].includes(part.trace?.method)
            const pending = (part.trace?.method === 'plus_minus' && !part.trace?.thresholds) || gradeMethodPending
            const appliedAdjustment = part.trace?.purpose === 'adjust_grade' && part.status === 'complete'
                && Number.isFinite(part.result)
            if (part.trace?.purpose === 'adjust_grade') {
                const trace = part.trace
                value = trace.balance_status === 'complete' && Number.isFinite(trace.balance)
                    ? `Saldo ${trace.balance > 0 ? '+' : ''}${formatResult(trace.balance)}`
                    : trace.balance_status === 'empty' ? 'Saldo 0' : 'Saldo unvollständig'
            } else if (gradeMethodPending) {
                value = 'Regel offen'
                hint = `${part.trace.method === 'grade_each' ? 'Jede Note extra rechnen' : 'Notendurchschnitt'}: Berechnungsregel noch offen`
            } else if (pending) {
                const trace = part.trace || {}
                const pendingDetail = 'Note noch offen'
                if (trace.purpose) hint = 'Eigene Note berechnen: Regel wird noch festgelegt'
                if (trace.balance_status === 'complete' && Number.isFinite(trace.balance)) {
                    value = `Saldo ${trace.balance > 0 ? '+' : ''}${formatResult(trace.balance)}`
                    detail = pendingDetail
                } else if (trace.balance_status === 'incomplete') {
                    value = 'Saldo unvollständig'
                    detail = pendingDetail
                } else if (trace.balance_status === 'empty') {
                    value = 'Saldo 0'
                    detail = pendingDetail
                } else {
                    value = 'Berechnungsregel wird noch festgelegt'
                }
            } else if (part.trace?.method === 'plus_minus') {
                const trace = part.trace
                value = trace.balance_status === 'complete' ? `Saldo ${trace.balance > 0 ? '+' : ''}${formatResult(trace.balance)}` : trace.balance_status === 'empty' ? 'Saldo 0' : 'Saldo unvollständig'
                detail = part.status === 'complete' && Number.isFinite(part.result) ? `Note ${formatResult(part.result)}` : 'Note noch offen'
                hint = trace.rule_label
            } else if (part.status === 'incomplete' || (semester.issues || []).some((issue) => issue.severity === 'error' && ['invalid_period', 'unassigned_date'].includes(issue.code))) {
                value = 'Nicht vollständig berechenbar'
            } else if (part.status === 'complete') {
                const trace = part.trace || {}
                if (trace.method === 'single_grade' && Number.isFinite(part.result)) {
                    value = `Note ${formatResult(part.result)}`
                } else if (trace.method === 'standard_grade_mean' && Number.isFinite(part.result)) {
                    value = `Notendurchschnitt ${formatResult(part.result)}`
                    hint = trace.rule_label
                } else if (['sum_percent', 'overall_points'].includes(trace.method) && Number.isFinite(trace.sum) && Number.isFinite(trace.maximum) && trace.maximum > 0) {
                    value = `${formatResult(trace.sum)} / ${formatResult(trace.maximum)} Punkte`
                } else if (Number.isFinite(part.result)) {
                    value = `Bewertung ${formatResult(part.result)}`
                }
            }
            return { key: `${semester.semester}-${part.id}`, id: part.id, semester: Number(semester.semester), name: part.name, status: part.status, trace: part.trace,
                balanceTone: part.trace?.method === 'plus_minus'
                    ? part.trace.balance_status === 'empty' ? 'neutral'
                        : part.trace.balance_status === 'complete' && Number.isFinite(part.trace.balance)
                            ? part.trace.balance > 0 ? 'positive' : part.trace.balance < 0 ? 'negative' : 'neutral' : null
                    : null,
                label, value, detail, hint, incomplete: (pending && !appliedAdjustment) || value === 'Nicht vollständig berechenbar' }
            })
            if (props.showCalculatedGrade) {
                const complete = semester.status === 'complete' && Number.isFinite(semester.result)
                cards.push({ key: `${semester.semester}-calculated-grade`, id: 'calculated-semester-grade', semester: Number(semester.semester),
                    name: 'Note', label: props.activeSemester === 3 && Number(report.semester_count) > 1 ? `Note · Sem ${semester.semester}` : 'Note',
                    value: complete ? String(Math.round(semester.result)) : 'Nicht berechenbar', incomplete: !complete,
                    gradeColor: complete ? standardPercentageGrades.find((band) => band.grade === Math.round(semester.result))?.color : undefined,
                    calculatedGrade: complete, detail: complete && semester.result_exact != null ? formatExactResult(semester.result_exact) : '',
                    hint: complete ? 'Berechnete Semesternote' : (semester.issues || []).filter((issue) => issue.severity === 'error').map((issue) => issue.message).filter(Boolean).join(' ') || 'Eine vollständige berechenbare Semesterbewertung fehlt.',
                })
            }
            return cards
        })
    if (props.showCalculatedGrade && props.activeSemester === 3 && Number(report.semester_count) > 1) {
        const year = evaluation?.year
        const complete = year?.status === 'complete' && Number.isFinite(year.result)
        if (evaluation) cards.push({ key: 'calculated-year-grade', id: 'calculated-semester-grade', semester: 3,
            name: 'Gesamtnote', label: 'Gesamtnote', value: complete ? String(Math.round(year.result)) : 'Nicht berechenbar', incomplete: !complete,
            gradeColor: complete ? standardPercentageGrades.find((band) => band.grade === Math.round(year.result))?.color : undefined,
            calculatedGrade: complete, detail: complete && year.result_exact != null ? formatExactResult(year.result_exact) : '',
            hint: complete ? 'Berechnete Gesamtnote nach den Semester-Einstellungen' : (year?.issues || []).filter((issue) => issue.severity === 'error').map((issue) => issue.message).filter(Boolean).join(' ') || 'Vollständige Semesternoten und die Semestergewichtung fehlen.',
        })
    }
    return cards
})

const gradingPartColumns = computed(() => [...new Set(gradingPartSummaries.value.map((part) => String(part.id)))]
    .sort((left, right) => left === 'calculated-semester-grade' ? 1 : right === 'calculated-semester-grade' ? -1 : 0))

function gradingPartCardPosition(part) {
    const column = gradingPartColumns.value.indexOf(String(part.id))
    const twoSemesters = props.activeSemester === 3 && Number(props.gradingReport?.semester_count) > 1
    const row = twoSemesters ? part.semester : 1
    return {
        '--grading-column': column + 1,
        '--grading-row': row,
        '--grading-mobile-column': column % 2 + 1,
        '--grading-mobile-row': Math.floor(column / 2) * (twoSemesters ? 2 : 1) + row,
    }
}

const assignmentsProgress = computed(() => {
    const byPart = new Map()
    for (const part of gradingPartSummaries.value.filter((part) => ['sum_percent', 'overall_points'].includes(part.trace?.method))) {
        const key = String(part.id)
        if (!byPart.has(key)) byPart.set(key, [])
        byPart.get(key).push(part)
    }
    return [...byPart.values()].map((parts) => {
        const unavailable = (text) => ({ id: parts[0].id, label: parts[0].name, percent: null, fill: 0, text, warning: true })
        if (parts.some((part) => part.incomplete)) return unavailable('Nicht vollständig berechenbar')
        if (parts.some((part) => part.status !== 'complete')) return unavailable('Keine Bewertung')
        if (parts.some((part) => !['sum_percent', 'overall_points'].includes(part.trace?.method) || !Number.isFinite(part.trace?.sum))) return unavailable('Keine Punktesumme verfügbar')
        if (parts.some((part) => !Number.isFinite(part.trace?.maximum) || part.trace.maximum <= 0)) return unavailable('Keine Maximalpunkte')
        const sum = parts.reduce((total, part) => total + part.trace.sum, 0)
        const maximum = parts.reduce((total, part) => total + part.trace.maximum, 0)
        const percent = sum / maximum * 100
        const band = percentageGradeBand(percent)
        const warning = percent < 0 || percent > 100
        const text = `${percent.toLocaleString('de-AT', { maximumFractionDigits: 2 })} %${warning ? ' · außerhalb 0–100 %' : ''}`
        return { id: parts[0].id, label: parts[0].name, percent, fill: Math.max(0, Math.min(100, percent)), text, warning, bandLabel: band?.label }
    })
})

const assignmentGradeBoundaries = standardPercentageGrades.filter((band) => band.grade < 5)
const assignmentGradeDescription = `Notengrenzen: ${[...assignmentGradeBoundaries].reverse().map((band) => `${formatResult(band.min)} % ${band.label}`).join(', ')}`

function formatExactResult(value) {
    if (!/^\d+\/\d+$/.test(String(value))) return Number(value).toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    const [numerator, denominator] = String(value).split('/').map(BigInt)
    if (denominator === 0n) return '–'
    const scaled = numerator * 100n
    const hundredths = scaled / denominator + (scaled % denominator * 2n >= denominator ? 1n : 0n)
    return `${hundredths / 100n},${String(hundredths % 100n).padStart(2, '0')}`
}

function formatResult(value) {
    return Number(value).toLocaleString('de-AT', { maximumFractionDigits: 6 })
}
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
            work: sourceWork,
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
    display: contents;
}

.assignment-progress {
    order: 3;
    flex: 1 1 100%;
    min-width: 0;
}

.assignment-progress-label {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 4px;
    margin-bottom: 3px;
    font-size: 0.7rem;
    line-height: 1.3;
}

.assignment-progress-track { position: relative; }
.assignment-progress-fill {
    position: absolute;
    inset: 0;
    border-radius: 3px;
    pointer-events: none;
}
.assignment-grade-boundary {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 1px;
    background: rgba(0, 0, 0, 0.65);
    box-shadow: 1px 0 rgba(255, 255, 255, 0.8);
}

.performance-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1 1 160px;
    min-width: 0;
}

.performance-row {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-width: 100%;
}

.grading-part-cards {
    display: grid;
    grid-template-columns: repeat(var(--grading-part-columns), minmax(0, 100px));
    gap: 6px;
    order: 2;
    flex: 0 1 auto;
    max-width: 50%;
    margin-left: auto;
}

.grading-part-card {
    grid-column: var(--grading-column);
    grid-row: var(--grading-row);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 3px;
    width: 100%;
    max-width: 100%;
    min-height: 58px;
    padding: 6px 8px;
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
    border-radius: 8px;
    color: rgb(var(--v-theme-primary));
    background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.14), rgba(var(--v-theme-primary), 0.04));
    overflow-wrap: anywhere;
}

@media (max-width: 600px) {
    .grading-part-cards {
        grid-template-columns: repeat(var(--grading-mobile-columns), minmax(0, 100px));
    }

    .grading-part-card {
        grid-column: var(--grading-mobile-column);
        grid-row: var(--grading-mobile-row);
    }
}

.grading-part-card--incomplete {
    border-color: rgba(var(--v-theme-warning), 0.2);
    color: rgb(var(--v-theme-warning));
    background: linear-gradient(135deg, rgba(var(--v-theme-warning), 0.14), rgba(var(--v-theme-warning), 0.04));
}

.grading-part-card--positive {
    border-color: rgba(22, 163, 74, 0.45);
    color: rgb(var(--v-theme-on-surface));
    background: linear-gradient(135deg, rgba(22, 163, 74, 0.24), rgba(22, 163, 74, 0.14));
}

.grading-part-card--negative {
    border-color: rgba(var(--v-theme-error), 0.2);
    color: rgb(var(--v-theme-on-surface));
    background: linear-gradient(135deg, rgba(var(--v-theme-error), 0.14), rgba(var(--v-theme-error), 0.04));
}

.grading-part-card-title {
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.3;
}

.grading-part-card-value {
    font-size: 0.8rem;
    font-weight: 750;
    line-height: 1.25;
    font-variant-numeric: tabular-nums;
}

.grading-part-card--calculated-note .grading-part-card-value {
    font-size: 1.65rem;
    font-weight: 700;
    line-height: 1.1;
    text-align: right;
    align-self: stretch;
}

.grading-part-card--calculated-note {
    border-color: var(--calculated-grade-color);
    color: rgb(var(--v-theme-on-surface));
    background: linear-gradient(135deg, color-mix(in srgb, var(--calculated-grade-color) 32%, transparent), color-mix(in srgb, var(--calculated-grade-color) 20%, transparent));
}

.grading-part-card--calculated-note .text-caption {
    text-align: right;
    align-self: stretch;
    font-weight: 400;
}

.grading-part-card--note .grading-part-card-title {
    text-align: right;
    align-self: stretch;
}

.grading-part-card--note .grading-part-card-value {
    text-align: right;
    align-self: stretch;
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
