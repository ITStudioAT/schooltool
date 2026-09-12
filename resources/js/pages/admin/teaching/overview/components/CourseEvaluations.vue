<template>
    <section class="course-evaluations" aria-label="Auswertungen">
        <div class="d-flex flex-wrap align-center ga-3 mb-4">
            <div>
                <h2 class="text-h6">Auswertungen</h2>
                <p class="text-body-2 text-medium-emphasis">Berechnung aus den gespeicherten Einträgen und Bewertungseinstellungen. Ergebnisse ohne Rundung.</p>
            </div>
            <v-spacer />
            <v-btn-toggle v-if="report?.semester_count !== 1" :model-value="activeSemester" mandatory density="compact" color="primary"
                @update:model-value="$emit('update:activeSemester', $event)">
                <v-btn :value="1" size="small">Sem 1</v-btn>
                <v-btn :value="2" size="small">Sem 2</v-btn>
                <v-btn :value="3" size="small">Sem 1+2</v-btn>
            </v-btn-toggle>
            <v-btn variant="text" prepend-icon="mdi-refresh" :loading="loading" @click="loadReport">Aktualisieren</v-btn>
        </div>

        <p v-if="loading" role="status">Auswertungen werden geladen …</p>
        <v-alert v-else-if="error" type="error" variant="tonal" role="alert">{{ error }}</v-alert>
        <template v-else-if="report">
            <p v-for="(issue, index) in report.issues || []" :key="index" class="mb-2" :class="issue.severity === 'error' ? 'text-error' : 'text-medium-emphasis'" role="status">{{ issue.message }}</p>
            <p v-if="!report.students?.length" class="text-body-2">Keine Schüler:innen für diese Auswertung vorhanden.</p>
            <details v-for="student in sortedStudents" :key="student.course_student_id" class="evaluation-student mb-3">
                <summary class="d-flex flex-wrap align-center ga-3 pa-4">
                    <v-icon icon="mdi-chart-box-outline" aria-hidden="true" />
                    <strong>{{ studentLabel(student) }}</strong>
                    <v-icon v-if="studentResult(student)?.status === 'incomplete'" icon="mdi-alert-circle" color="error" size="small" aria-label="Auswertung unvollständig" />
                    <span class="text-caption text-medium-emphasis">Details anzeigen</span>
                    <v-spacer />
                    <span>{{ resultLabel(studentResult(student)) }}</span>
                </summary>
                <div class="pa-4 pt-0">
                    <p v-for="(issue, index) in student.issues || []" :key="index" class="mb-2" :class="issue.severity === 'error' ? 'text-error' : 'text-medium-emphasis'">{{ issue.message }}</p>
                    <section v-for="semester in student.semesters || []" :key="semester.semester" class="mb-5">
                        <h3 class="text-subtitle-1 font-weight-bold">Semester {{ semester.semester }} · {{ resultLabel(semester) }}</h3>
                        <p v-if="semester.trace?.rule_label" class="text-body-2">{{ semester.trace.rule_label }}</p>
                        <p v-for="line in traceLines(semester.trace)" :key="line" class="text-caption">{{ line }}</p>
                        <p v-for="(issue, index) in semester.issues || []" :key="index"  :class="issue.severity === 'error' ? 'text-error' : 'text-medium-emphasis'">{{ issue.message }}</p>
                        <div v-for="part in semester.parts || []" :key="part.id" class="evaluation-part mt-3 pa-3">
                            <h4 class="text-subtitle-1 font-weight-bold">{{ part.name }} · {{ resultLabel(part) }}</h4>
                            <p v-if="part.trace?.rule_label" class="text-body-2">{{ part.trace.rule_label }}</p>
                            <p v-for="line in configurationLines(part.config)" :key="line" class="text-caption">{{ line }}</p>
                            <p v-for="line in traceLines(part.trace, part.types)" :key="line" class="text-caption">{{ line }}</p>
                            <p v-for="(issue, index) in part.issues || []" :key="index"  :class="issue.severity === 'error' ? 'text-error' : 'text-medium-emphasis'">{{ issue.message }}</p>
                            <details v-for="type in part.types || []" :key="type.id" class="evaluation-type mt-3">
                                <summary class="pa-2"><strong>{{ type.short_name }} · {{ type.name }}</strong> — {{ resultLabel(type) }}</summary>
                                <div class="pa-2">
                                    <p v-if="type.trace?.rule_label" class="text-body-2 font-weight-medium">{{ type.trace.rule_label }}</p>
                                    <p v-for="line in configurationLines(type.config, part.config)" :key="line" class="text-caption">{{ line }}</p>
                                    <p v-for="line in traceLines(type.trace)" :key="line" class="text-caption">{{ line }}</p>
                                    <p v-for="(issue, index) in type.issues || []" :key="index"  :class="issue.severity === 'error' ? 'text-error' : 'text-medium-emphasis'">{{ issue.message }}</p>
                                    <div class="evaluation-table-wrap mt-2">
                                        <table class="evaluation-table">
                                            <caption class="text-left text-caption">Verwendete Einträge und Einzelbewertungen</caption>
                                            <thead><tr><th>Datum</th><th>Quelle</th><th>Eingabe</th><th>Rechenwert</th><th>Bewertung</th><th>Hinweise</th></tr></thead>
                                            <tbody>
                                                <tr v-for="(entry, index) in type.entries || []" :key="`${entry.source}-${entry.id}-${index}`">
                                                    <td>{{ dateLabel(entry.date) }}</td><td>{{ sourceLabel(entry.source) }}</td>
                                                    <td>{{ displayValue(entry.raw_value) }}</td><td>{{ displayValue(entry.numeric_value) }}</td>
                                                    <td>{{ displayValue(entry.grade) }}</td>
                                                    <td>{{ entryStatusLabel(entry.status) }}<p v-for="(issue, issueIndex) in entry.issues || []" :key="issueIndex">{{ issue.message }}</p></td>
                                                </tr>
                                                <tr v-if="!type.entries?.length"><td colspan="6">Keine Einträge in diesem Semester.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </details>
                        </div>
                        <section v-if="semester.unassigned_types?.length" class="mt-3">
                            <h4 class="text-subtitle-2 text-error">Nicht zugeordnete Eintragstypen</h4>
                            <p v-for="type in semester.unassigned_types" :key="type.id">{{ type.short_name }} · {{ type.name }}<span v-for="(issue, index) in type.issues || []" :key="index"> — {{ issue.message }}</span></p>
                        </section>
                        <details v-if="semester.excluded_entries?.length" class="mt-3">
                            <summary>Ausgeschlossene Einträge ({{ semester.excluded_entries.length }})</summary>
                            <p v-for="(entry, index) in semester.excluded_entries" :key="index" class="text-body-2 mt-1">
                                {{ dateLabel(entry.date) }} · {{ displayValue(entry.raw_value) }}
                                <span v-for="(issue, issueIndex) in entry.issues || []" :key="issueIndex"> — {{ issue.message }}</span>
                            </p>
                        </details>
                    </section>
                    <section v-if="student.year && activeSemester === 3" class="evaluation-part pa-3">
                        <h3 class="text-subtitle-1 font-weight-bold">Jahresergebnis · {{ resultLabel(student.year) }}</h3>
                        <p v-if="student.year.trace?.rule_label">{{ student.year.trace.rule_label }}</p>
                        <p v-for="line in traceLines(student.year.trace)" :key="line" class="text-caption">{{ line }}</p>
                        <p v-for="(issue, index) in student.year.issues || []" :key="index"  :class="issue.severity === 'error' ? 'text-error' : 'text-medium-emphasis'">{{ issue.message }}</p>
                    </section>
                </div>
            </details>
        </template>
    </section>
</template>

<script>
import axios from 'axios'
import { show } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseEvaluationController'
import { teachingCourseMatchesSchoolyear } from '@/helpers/teachingSemester'

export default {
    props: {
        course: { type: Object, required: true },
        schoolyear: { type: Object, default: null },
        activeSemester: { type: Number, default: 3 },
    },
    emits: ['update:activeSemester'],
    data() {
        return { report: null, loading: false, error: '', requestId: 0 }
    },
    computed: {
        sortedStudents() {
            return [...(this.report?.students || [])].sort((left, right) => this.studentLabel(left).localeCompare(this.studentLabel(right), 'de'))
        },
        reportKey() {
            return `${this.course?.id}:${this.course?.schoolyear_id}:${this.schoolyear?.id}:${this.activeSemester}`
        },
    },
    watch: {
        reportKey: { immediate: true, handler() { this.loadReport() } },
    },
    beforeUnmount() { this.requestId++ },
    methods: {
        async loadReport() {
            const requestId = ++this.requestId
            this.report = null
            this.error = ''
            this.loading = false
            if (!this.course?.id || !teachingCourseMatchesSchoolyear(this.course, this.schoolyear)) {
                this.error = 'Der Unterricht gehört nicht zum ausgewählten Schuljahr.'
                return
            }
            this.loading = true
            try {
                const response = await axios.get(show.url(this.course.id, { query: { semester: this.activeSemester } }))
                if (requestId !== this.requestId) return
                const report = response.data.data
                if (String(report.course_id) !== String(this.course.id) || String(report.schoolyear_id) !== String(this.schoolyear.id)) {
                    this.error = 'Die Auswertung passt nicht zum ausgewählten Unterricht oder Schuljahr.'
                    return
                }
                this.report = report
            } catch {
                if (requestId === this.requestId) this.error = 'Die Auswertung konnte nicht geladen werden. Bitte erneut versuchen.'
            } finally {
                if (requestId === this.requestId) this.loading = false
            }
        },
        studentLabel(student) {
            const roster = this.course?.students_info || []
            const sameId = (left, right) => left != null && right != null && String(left) === String(right)
            const match = roster.find((entry) => sameId(entry.course_student_id, student.course_student_id))
                || roster.find((entry) => sameId(entry.user_id, student.user_id))
                || roster.find((entry) => sameId(entry.import116_id, student.import116_id))
            return match ? `${match.last_name || ''}, ${match.first_name || ''}`.replace(/^, |, $/g, '') : 'Schüler:in nicht zuordenbar'
        },
        studentResult(student) {
            if (this.report?.semester_count === 1) return student.semesters?.[0]
            return this.activeSemester === 3 ? student.year : student.semesters?.find((semester) => semester.semester === this.activeSemester)
        },
        displayValue(value) {
            return value === null || value === undefined || value === '' ? '—' : String(value).replace('.', ',')
        },
        resultLabel(result) {
            if (!result || result.status === 'incomplete') return 'Nicht berechenbar'
            if (result.status === 'empty' || result.result === null || result.result === undefined) return 'Keine Bewertung'
            const exact = result.result_exact ? ` (exakt: ${this.displayValue(result.result_exact)})` : ''
            return `Ergebnis: ${this.displayValue(result.result)}${exact}`
        },
        dateLabel(value) {
            const date = String(value || '').slice(0, 10)
            return /^\d{4}-\d{2}-\d{2}$/.test(date) ? date.split('-').reverse().join('.') : 'Ohne Datum'
        },
        sourceLabel(source) { return source === 'course_work' ? 'Arbeit' : 'Eintrag' },
        entryStatusLabel(status) {
            return { included: 'Berücksichtigt', excluded: 'Ausgeschlossen', ignored: 'Nicht berücksichtigt', incomplete: 'Nicht berechenbar', complete: 'Berechnet', empty: 'Ohne Bewertung' }[status] || ''
        },
        configurationLines(config, part = null) {
            if (!config) return []
            const lines = []
            const add = (label, value) => {
                if (value !== null && value !== undefined) lines.push(`${label}: ${this.displayValue(value)}`)
            }
            const modes = { points: 'Punkte', plus: 'Nur Plus', plus_minus: 'Plus und Minus', fixed: 'Feste Auswahl', free: 'Freie Eingabe' }
            if (config.properties_mode) add('Eigenschaft', modes[config.properties_mode])
            else if (config.fixed_percentage !== null && config.fixed_percentage !== undefined) add('Fester Anteil (%)', config.fixed_percentage)
            else add('Gewichtung', config.weight)
            const overallPoints = part?.allowed_entry_types === 'points' && part.points_assessment_mode === 'overall'
            let thresholds = null
            let thresholdLabel = 'Notengrenzen'
            if (config.properties_mode === 'points') {
                add('Maximalpunkte', config.maximum_points)
                if (!overallPoints) thresholds = config.points_grade_thresholds
            } else if (config.properties_mode === 'free') {
                for (const item of config.property_evaluations || []) lines.push(`${item.property} → ${item.evaluation === 'ignored' ? 'Nicht berücksichtigen' : this.displayValue(item.evaluation)}`)
                const usesPoints = config.free_grading_mode === 'points'
                thresholds = usesPoints ? config.free_points_grade_thresholds : config.free_deficit_grade_thresholds
                thresholdLabel = usesPoints ? 'Punktegrenzen' : 'Defizitgrenzen'
            } else if (['plus', 'plus_minus'].includes(config.properties_mode) && config.grading_part_assessment_mode !== 'other') {
                if (config.maximum_plus_grading_mode === 'other' || !config.allows_maximum_plus) thresholds = config.maximum_plus_grade_thresholds
            } else if (!config.properties_mode && config.allowed_entry_types === 'points' && config.points_assessment_mode === 'overall') {
                thresholds = config.overall_points_grade_thresholds
                thresholdLabel = 'Gesamtpunktegrenzen'
            }
            if (thresholds) lines.push(`${thresholdLabel}: ${Object.entries(thresholds).map(([grade, value]) => `Note ${grade}: ${this.displayValue(value)}`).join(' · ')}`)
            return lines
        },
        traceLines(trace, types = []) {
            if (!trace) return []
            const labels = { count: 'Anzahl', sum: 'Summe', sum_exact: 'Exakte Summe', maximum: 'Maximum', deficit: 'Defizit', deficit_exact: 'Exaktes Defizit', balance: 'Plus/Minus-Saldo', percentage: 'Anteil (%)', weighted_sum: 'Gewichtete Summe', weight_sum: 'Summe der Gewichte', numerator: 'Zähler', denominator: 'Nenner' }
            const lines = Object.entries(labels).filter(([key]) => trace[key] !== null && trace[key] !== undefined)
                .map(([key, label]) => `${label}: ${this.displayValue(trace[key])}`)
            for (const [key, label] of [['values', 'Einzelwerte'], ['grades', 'Einzelbewertungen'], ['weights', 'Gewichte'], ['contributions', 'Gewichtete Beiträge']]) {
                if (Array.isArray(trace[key]) && trace[key].every((value) => value === null || ['number', 'string'].includes(typeof value))) {
                    lines.push(`${label}: ${trace[key].map((value) => this.displayValue(value)).join(' · ')}`)
                }
            }
            for (const adjustment of trace.adjustments || []) {
                const name = types.find((type) => type.id === adjustment.type_id)?.name || 'Plus/Minus-Anpassung'
                lines.push(`${name}: Saldo ${this.displayValue(adjustment.balance)} · Anpassungswert ${this.displayValue(adjustment.amount)} · Änderung ${this.displayValue(adjustment.delta)} · ${adjustment.applied ? 'Angewendet' : 'Nicht angewendet'}`)
            }
            return lines
        },
    },
}
</script>

<style scoped>
.evaluation-student, .evaluation-part, .evaluation-type { border: 1px solid rgba(var(--v-theme-on-surface), .16); border-radius: 10px; }
.evaluation-student { background: rgb(var(--v-theme-surface)); }
summary { cursor: pointer; }
.evaluation-table-wrap { overflow-x: auto; }
.evaluation-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.evaluation-table th, .evaluation-table td { padding: 8px; text-align: left; vertical-align: top; border-bottom: 1px solid rgba(var(--v-theme-on-surface), .12); }
.evaluation-table th { white-space: nowrap; }
</style>
