<template>
    <v-card variant="outlined" class="performances-plus-card mt-2" data-testid="teaching-performances-plus-card">
        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
            <v-icon size="18">mdi-chart-bar</v-icon>
            Leistungen Plus
        </v-card-title>
        <v-divider />
        <v-card-text>
            <div class="d-flex align-center ga-2 mb-3">
                <v-btn-toggle v-model="sortMode" mandatory density="compact" color="primary">
                    <v-btn value="class_last_name" size="small">Klasse, Name</v-btn>
                    <v-btn value="last_name_first_name" size="small">Name</v-btn>
                </v-btn-toggle>
            </div>

            <v-alert v-if="!selectedCourse" type="info" variant="tonal">
                Bitte einen Kurs auswählen.
            </v-alert>
            <v-alert v-else-if="loading" type="info" variant="tonal">
                Daten werden geladen…
            </v-alert>
            <v-alert v-else-if="!students.length" type="info" variant="tonal">
                Keine Schüler:innen im Kurs vorhanden.
            </v-alert>
            <div v-else class="performances-plus-table-wrap">
                <table class="performances-plus-table" data-testid="teaching-performances-plus-table">
                    <thead>
                        <tr>
                            <th class="student-col">Schüler:in</th>
                            <th v-for="col in gradeColumns" :key="col.key" :class="col.key === 'year' ? 'grade-col grade-col--narrow' : 'grade-col'">
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.student_id" :class="{ 'row--canceled': row.is_canceled }">
                            <td class="student-cell" :class="{ 'student-cell--canceled': row.is_canceled }">
                                <div>{{ row.student_label }}</div>
                                <div v-if="row.email" class="student-email">
                                    <span class="student-email-text">{{ row.email }}</span>
                                    <v-icon
                                        size="13"
                                        class="student-email-copy"
                                        :title="'E-Mail kopieren'"
                                        @click.stop="copyEmail(row.email)"
                                    >mdi-content-copy</v-icon>
                                    <v-icon
                                        v-if="copiedEmail === row.email"
                                        size="13"
                                        class="student-email-copied"
                                        color="success"
                                    >mdi-check</v-icon>
                                </div>
                            </td>
                            <td v-for="col in gradeColumns" :key="`${row.student_id}-${col.key}`" class="grade-cell">
                                <div class="grade-cell-inner">
                                    <div class="grade-effective" :class="gradeClass(row.grades[col.key]?.effective)">
                                        {{ formatGrade(row.grades[col.key]?.effective) }}
                                    </div>
                                    <div v-if="row.grades[col.key]?.effectiveSource" class="grade-source-label">
                                        {{ row.grades[col.key].effectiveSource }}
                                    </div>
                                </div>
                                <div v-if="row.grades[col.key]?.secondary != null" class="grade-secondary">
                                    {{ row.grades[col.key].secondaryLabel }}: {{ formatGrade(row.grades[col.key].secondary) }}
                                </div>
                                <div v-if="row.grades[col.key]?.categories?.length" class="grade-categories">
                                    <div
                                        v-for="cat in row.grades[col.key].categories"
                                        :key="`${row.student_id}-${col.key}-${cat.name}`"
                                        class="grade-category-block">
                                        <div class="grade-category-row">
                                            <span class="grade-category-name">{{ cat.name }} ({{ cat.weight }}%)</span>
                                            <span class="grade-category-value" :class="gradeClass(cat.value)">
                                                {{ formatGrade(cat.value) }}
                                            </span>
                                        </div>
                                        <div v-if="cat.entries?.length" class="grade-category-entries">
                                            <div
                                                v-for="(entry, idx) in cat.entries"
                                                :key="`${row.student_id}-${col.key}-${cat.name}-${idx}`"
                                                class="grade-entry-row">
                                                <span class="grade-entry-type">{{ entry.type }}</span>
                                                <span v-if="entry.shortDate" class="grade-entry-date">{{ entry.shortDate }}</span>
                                                <span class="grade-entry-label" :title="entry.description">{{ entry.label }}</span>
                                                <span class="grade-entry-value" :class="gradeClass(entry.displayValue)">
                                                    {{ entry.displayGrade }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </v-card-text>
    </v-card>
</template>

<script>
import { parseLocalDate } from '@/helpers/date'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    props: {
        selectedCourse: { type: Object, default: null },
        activeSemester: { type: Number, default: 1 },
        semesterCount: { type: Number, default: 1 },
        sem2StartDate: { type: String, default: null },
    },

    data() {
        return {
            entryStore: null,
            courseWorkStore: null,
            teachingStore: null,
            adminStore: null,
            sortMode: 'last_name_first_name',
            loading: false,
            allEntries: [],
            copiedEmail: null,
        }
    },

    async beforeMount() {
        this.entryStore = useCourseStudentEntryStore()
        this.courseWorkStore = useCourseWorkStore()
        this.teachingStore = useTeachingStore()
        this.adminStore = useAdminStore()

        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        await this.loadData()
    },

    computed: {
        config() {
            return this.adminStore?.config || {}
        },
        teachingSchemas() {
            return this.config?.user?.teaching_schemas || this.teachingStore?.settings?.teaching_schemas || []
        },
        selectedSchema() {
            const schemaId = this.selectedCourse?.teaching_schema_id
            if (!schemaId) return null
            return this.teachingSchemas.find((s) => String(s.id) === String(schemaId)) || null
        },
        teachingWorks() {
            return this.selectedSchema?.works || []
        },
        grading() {
            return this.selectedSchema?.grading || {}
        },
        hasTwoSemesters() {
            return this.semesterCount === 2
        },
        sem2Boundary() {
            if (!this.sem2StartDate) return null
            return this.normalizeDateKey(this.sem2StartDate)
        },
        gradeColumns() {
            const cols = []
            if (this.hasTwoSemesters) {
                cols.push({ key: 'sem1', label: '1. Semester' })
                cols.push({ key: 'sem2', label: '2. Semester' })
                cols.push({ key: 'year', label: 'Gesamt (1+2)' })
            } else {
                cols.push({ key: 'sem1', label: 'Gesamt' })
            }
            return cols
        },
        students() {
            const list = Array.isArray(this.selectedCourse?.students_info) ? [...this.selectedCourse.students_info] : []
            return list
                .filter((s) => !!s?.id)
                .sort((a, b) => {
                    const canceledA = this.isStudentCanceled(a) ? 1 : 0
                    const canceledB = this.isStudentCanceled(b) ? 1 : 0
                    if (canceledA !== canceledB) return canceledA - canceledB
                    return this.compareStudents(a, b)
                })
        },
        entriesByStudent() {
            const map = {}
            ;(this.allEntries || []).forEach((entry) => {
                const uid = String(entry?.user_id || '')
                if (!uid) return
                if (!map[uid]) map[uid] = []
                map[uid].push(entry)
            })
            return map
        },
        rows() {
            return this.students.map((student) => {
                const studentId = String(student.id)
                const studentEntries = this.entriesByStudent[studentId] || []
                const grades = {}

                if (this.hasTwoSemesters) {
                    const sem1Entries = this.entriesForSemester(studentEntries, 1)
                    const sem2Entries = this.entriesForSemester(studentEntries, 2)

                    const sem1Groups = this.buildCategoryGroups(sem1Entries)
                    const sem1ForcedNa = this.hasSingleNaSemesterGrade(sem1Entries)
                    const sem1Calculated = sem1ForcedNa ? 5 : this.totalFromCategoryGroups(sem1Groups)

                    const sem2Groups = this.buildCategoryGroups(sem2Entries)
                    const sem2ForcedNa = this.hasSingleNaSemesterGrade(sem2Entries)
                    const sem2Calculated = sem2ForcedNa ? 5 : this.totalFromCategoryGroups(sem2Groups)

                    const useSemGradeOnly = !!this.grading.use_semester_grade_only
                    const storedSem1 = student.sem_1_grade || null
                    const storedSem1Parsed = storedSem1 != null ? this.parseStoredGrade(storedSem1) : null

                    // Sem 1: effective = what feeds into yearly calculation
                    const sem1Effective = useSemGradeOnly ? storedSem1Parsed : sem1Calculated
                    grades.sem1 = {
                        effective: sem1Effective,
                        effectiveSource: useSemGradeOnly ? 'Semesternote' : 'Berechnung',
                        secondary: useSemGradeOnly ? sem1Calculated : storedSem1Parsed,
                        secondaryLabel: useSemGradeOnly ? 'Berechnung' : 'Semesternote',
                        categories: this.categoryDetails(sem1Groups),
                    }

                    // Sem 2: always calculated
                    const storedSem2 = student.sem_2_grade || null
                    const storedSem2Parsed = storedSem2 != null ? this.parseStoredGrade(storedSem2) : null
                    grades.sem2 = {
                        effective: sem2Calculated,
                        effectiveSource: 'Berechnung',
                        secondary: storedSem2Parsed,
                        secondaryLabel: 'Semesternote',
                        categories: this.categoryDetails(sem2Groups),
                    }

                    const yearGrade = this.computeYearlyGrade(sem1Calculated, sem2Calculated, student)
                    grades.year = {
                        effective: yearGrade,
                        effectiveSource: null,
                        secondary: null,
                        secondaryLabel: null,
                        categories: [],
                    }
                } else {
                    const groups = this.buildCategoryGroups(studentEntries)
                    const forcedNa = this.hasSingleNaSemesterGrade(studentEntries)
                    const calculated = forcedNa ? 5 : this.totalFromCategoryGroups(groups)

                    const storedGrade = student.sem_grade || null
                    const storedParsed = storedGrade != null ? this.parseStoredGrade(storedGrade) : null
                    grades.sem1 = {
                        effective: calculated,
                        effectiveSource: 'Berechnung',
                        secondary: storedParsed,
                        secondaryLabel: 'Semesternote',
                        categories: this.categoryDetails(groups),
                    }
                }

                return {
                    student_id: studentId,
                    student_label: this.studentLabelWithClass(student),
                    email: student.email || null,
                    is_canceled: this.isStudentCanceled(student),
                    grades,
                }
            })
        },
    },

    watch: {
        selectedCourse: {
            handler() { this.loadData() },
            deep: false,
        },
    },

    methods: {
        async loadData() {
            if (!this.selectedCourse?.id || !this.entryStore) return
            this.loading = true
            await Promise.allSettled([
                this.entryStore.indexByCourse(this.selectedCourse.id),
                this.courseWorkStore.index(this.selectedCourse.id),
            ])
            this.allEntries = [...(this.entryStore.courseEntries || [])]
            this.loading = false
        },

        // --- Student helpers ---
        isStudentCanceled(student) {
            return !!student?.canceled_at || !!student?.deleted_at
        },
        compareStudents(a, b) {
            const lastA = String(a?.last_name || '')
            const lastB = String(b?.last_name || '')
            const firstA = String(a?.first_name || '')
            const firstB = String(b?.first_name || '')
            const classA = String(a?.schoolclass || a?.class || '')
            const classB = String(b?.schoolclass || b?.class || '')
            if (this.sortMode === 'last_name_first_name') {
                const cmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (cmp !== 0) return cmp
                return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
            }
            const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
            if (classCmp !== 0) return classCmp
            const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
            if (lastCmp !== 0) return lastCmp
            return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
        },
        studentLabelWithClass(student) {
            const last = String(student?.last_name || '').trim()
            const first = String(student?.first_name || '').trim()
            const cls = String(student?.schoolclass || student?.class || '').trim()
            const name = `${last}, ${first}`.replace(/^,\s*/, '').trim() || '–'
            return cls ? `${name} (${cls})` : name
        },

        // --- Semester splitting ---
        normalizeDateKey(date) {
            if (!date) return ''
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) return date.slice(0, 10)
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) return ''
            const y = parsed.getFullYear()
            const m = String(parsed.getMonth() + 1).padStart(2, '0')
            const d = String(parsed.getDate()).padStart(2, '0')
            return `${y}-${m}-${d}`
        },
        entriesForSemester(entries, semester) {
            if (this.semesterCount !== 2) return entries
            const boundary = this.sem2Boundary
            if (!boundary) return entries
            return (entries || []).filter((entry) => {
                if (!entry.date) return true
                const d = this.normalizeDateKey(entry.date)
                if (!d) return true
                if (semester === 1) return d < boundary
                if (semester === 2) return d >= boundary
                return true
            })
        },

        // --- Grade helpers (mirrored from CourseStudent.vue) ---
        normalizeGradeKey(gradeKey) {
            return String(gradeKey || '').trim().toUpperCase()
        },
        isNaGradeKey(gradeKey) {
            return this.normalizeGradeKey(gradeKey) === 'NA'
        },
        isNbGradeKey(gradeKey) {
            return this.normalizeGradeKey(gradeKey) === 'NB'
        },
        isNbValue(value) {
            if (value == null || value === '') return false
            return this.isNbGradeKey(value)
        },
        defaultGradeForWork(work) {
            if (!work) return ''
            const dg = String(work.default_grade || '').trim()
            if (!dg) return ''
            const exists = (work.grades || []).some((g) => this.normalizeGradeKey(g?.grade) === this.normalizeGradeKey(dg))
            return exists ? dg : ''
        },
        effectiveGradeKeyForEntry(entry, workOverride) {
            const fromApi = String(entry?.effective_grade || '').trim()
            if (fromApi) return fromApi
            const direct = String(entry?.grade || '').trim()
            if (direct) return direct
            const work = workOverride || this.workConfigForType(entry?.type)
            return this.defaultGradeForWork(work)
        },
        isGradedEntry(entry, workOverride) {
            return this.effectiveGradeKeyForEntry(entry, workOverride) !== ''
        },
        workConfigForType(type) {
            if (!type) return null
            return this.teachingWorks.find((w) => w.short_name === type) || null
        },
        gradeValueForWork(work, gradeKey) {
            if (!work || !gradeKey) return null
            const lookup = this.normalizeGradeKey(gradeKey)
            const grade = (work.grades || []).find((g) => this.normalizeGradeKey(g.grade) === lookup)
            if (!grade || grade.value == null) return null
            const num = parseFloat(String(grade.value).replace(',', '.'))
            return Number.isNaN(num) ? null : num
        },
        pointsGradeForWork(work, points) {
            if (!work || work.calculation !== 'points') return null
            const table = (work.semester_points_table || []).length ? work.semester_points_table : (work.points_table || [])
            const fallback = (work.semester_points_sonst_grade || work.points_sonst_grade || '').toString().trim()
            if (!table.length) return fallback || null
            const sorted = [...table].sort((a, b) => (b.min_points ?? 0) - (a.min_points ?? 0))
            const found = sorted.find((row) => points >= (row.min_points ?? 0))
            return found?.grade || fallback || null
        },
        isTypeInRequireAllCategory(type) {
            if (!type) return false
            const categories = this.grading?.categories || []
            return categories.some((cat) => {
                if (!cat?.require_all_entries) return false
                return (cat.works || []).some((w) => {
                    const sn = typeof w === 'string' ? w : w?.short_name
                    return String(sn || '') === String(type)
                })
            })
        },
        hasSingleNaSemesterGrade(entries) {
            const graded = (entries || []).filter((e) => this.isGradedEntry(e))
            if (graded.length !== 1) return false
            const only = graded[0]
            if (!this.isNaGradeKey(this.effectiveGradeKeyForEntry(only))) return false
            if (!this.isTypeInRequireAllCategory(only?.type)) return false
            const sameType = (entries || []).filter((e) => e?.type === only?.type)
            return !sameType.some((e) => !this.isGradedEntry(e))
        },

        // --- Core calculation (mirrored from CourseStudent.vue) ---
        buildCategoryGroups(entries) {
            const categories = this.grading?.categories || []
            const worksByType = new Map(this.teachingWorks.map((w) => [w.short_name, w]))
            const entriesByType = new Map()
            ;(entries || []).forEach((entry) => {
                const type = entry?.type
                if (!type) return
                if (!entriesByType.has(type)) entriesByType.set(type, [])
                entriesByType.get(type).push(entry)
            })

            return categories.map((cat) => {
                const works = (cat.works || []).map((w) => (typeof w === 'string' ? { short_name: w, factor: 100 } : w))
                const workAverages = []
                const categoryRequireAll = Boolean(cat?.require_all_entries)
                let hasAny = false
                let hasUngraded = false
                let hasNa = false
                const catEntries = []

                works.forEach((workItem) => {
                    const type = workItem.short_name
                    const work = worksByType.get(type)
                    if (!work) return
                    const workEntries = entriesByType.get(type) || []
                    if (workEntries.length) hasAny = true
                    if (workEntries.some((e) => !this.isGradedEntry(e))) hasUngraded = true
                    if (categoryRequireAll && workEntries.some((e) => this.isNaGradeKey(this.effectiveGradeKeyForEntry(e, work)))) hasNa = true

                    const factorPercent = parseFloat(workItem.factor)
                    const weight = (Number.isNaN(factorPercent) ? 0 : factorPercent) / 100

                    workEntries.forEach((e) => {
                        const gradeKey = this.effectiveGradeKeyForEntry(e, work)
                        const numValue = this.gradeValueForWork(work, gradeKey)
                        catEntries.push({
                            type: type,
                            date: e.date || '',
                            shortDate: this.formatShortDate(e.date),
                            description: e.description || '',
                            label: this.entryWorkTitle(e) || e.description || type,
                            gradeKey: gradeKey,
                            numericValue: numValue,
                            displayGrade: gradeKey ? (this.isNaGradeKey(gradeKey) ? 'NA' : this.isNbGradeKey(gradeKey) ? 'NB' : gradeKey) : '–',
                            displayValue: gradeKey ? (this.isNaGradeKey(gradeKey) ? 'NA' : this.isNbGradeKey(gradeKey) ? 'NB' : numValue) : null,
                        })
                    })

                    if (work.calculation === 'points') {
                        const values = workEntries
                            .map((e) => this.gradeValueForWork(work, this.effectiveGradeKeyForEntry(e, work)))
                            .filter((v) => v !== null)
                        const sum = values.reduce((s, v) => s + v, 0)
                        const rounded = Number.isInteger(sum) ? sum : Number(sum.toFixed(2))
                        const grade = this.pointsGradeForWork(work, rounded)
                        let numericGrade = this.gradeValueForWork(work, grade)
                        if (numericGrade === null && grade != null && grade !== '') {
                            const parsed = parseFloat(String(grade).replace(',', '.'))
                            numericGrade = Number.isNaN(parsed) ? null : parsed
                        }
                        if (numericGrade !== null) workAverages.push({ value: numericGrade, weight })
                        return
                    }

                    const values = workEntries
                        .map((e) => this.gradeValueForWork(work, this.effectiveGradeKeyForEntry(e, work)))
                        .filter((v) => v !== null)
                    if (values.length) {
                        const avg = values.reduce((s, v) => s + v, 0) / values.length
                        workAverages.push({ value: avg, weight })
                    }
                })

                catEntries.sort((a, b) => (a.type || '').localeCompare(b.type || '', 'de') || (a.date || '').localeCompare(b.date || ''))

                const isNb = categoryRequireAll && hasAny && hasUngraded
                const isNa = categoryRequireAll && hasNa
                let value = null
                if (isNa) {
                    value = 'NA'
                } else if (isNb) {
                    value = 'NB'
                } else if (workAverages.length) {
                    const totalW = workAverages.reduce((s, w) => s + w.weight, 0) || 1
                    value = Number((workAverages.reduce((s, w) => s + w.value * w.weight, 0) / totalW).toFixed(2))
                }

                return {
                    name: cat.name || 'Kategorie',
                    weight: cat.weight ?? 0,
                    value,
                    isNb,
                    isNa,
                    entries: catEntries,
                }
            })
        },

        totalFromCategoryGroups(groups) {
            if (!groups?.length) return null
            if (groups.some((cat) => cat?.isNa)) return 'NA'
            if (groups.some((cat) => cat?.isNb)) return 'NB'
            const weighted = groups
                .map((cat) => ({
                    value: cat.value != null ? cat.value : cat.grade != null ? parseFloat(String(cat.grade).replace(',', '.')) : null,
                    weight: (parseFloat(cat.weight) || 0) / 100,
                }))
                .filter((c) => c.value != null && !Number.isNaN(c.value) && c.weight > 0)
            if (!weighted.length) return null
            const totalW = weighted.reduce((s, c) => s + c.weight, 0) || 1
            return Number((weighted.reduce((s, c) => s + c.value * c.weight, 0) / totalW).toFixed(2))
        },

        computeYearlyGrade(sem1Calculated, sem2Calculated, student) {
            const sem1Weight = parseFloat(this.grading.semester_1_weight)
            const sem2Weight = parseFloat(this.grading.semester_2_weight)
            const w1 = Number.isNaN(sem1Weight) ? 50 : sem1Weight
            const w2 = Number.isNaN(sem2Weight) ? 50 : sem2Weight
            const totalWeight = w1 + w2
            if (totalWeight <= 0) return null

            let sem1Value = sem1Calculated
            if (this.grading.use_semester_grade_only) {
                const raw = student?.sem_1_grade
                const key = this.normalizeGradeKey(raw)
                if (this.isNbGradeKey(key)) {
                    sem1Value = 'NB'
                } else {
                    const parsed = parseFloat(String(raw || '').replace(',', '.'))
                    sem1Value = Number.isNaN(parsed) ? null : parsed
                }
            }
            const sem2Value = sem2Calculated

            if (this.isNaGradeKey(sem1Value) || this.isNaGradeKey(sem2Value)) return 'NA'
            if (this.isNbValue(sem1Value) || this.isNbValue(sem2Value)) return 'NB'
            if (sem1Value == null || sem2Value == null) return null

            return Number((((sem1Value * w1) + (sem2Value * w2)) / totalWeight).toFixed(2))
        },

        categoryDetails(groups) {
            return (groups || [])
                .filter((g) => g.name !== 'Undefiniert')
                .map((g) => ({
                    name: g.name,
                    weight: g.weight,
                    value: g.value,
                    entries: g.entries || [],
                }))
        },

        entryWorkTitle(entry) {
            const workId = entry?.teaching_course_work_id
            if (!workId) return ''
            const work = (this.courseWorkStore?.courseWorks || []).find((w) => w.id === workId)
            if (!work) return ''
            const title = (work.title || '').toString().trim()
            const desc = (work.description || '').toString().trim()
            return title || desc || ''
        },

        formatShortDate(date) {
            if (!date) return ''
            const normalized = this.normalizeDateKey(date)
            if (!normalized) return ''
            const parts = normalized.split('-')
            if (parts.length !== 3) return ''
            return `${parseInt(parts[2], 10)}.${parseInt(parts[1], 10)}.`
        },

        parseStoredGrade(raw) {
            if (raw == null || raw === '') return null
            const key = this.normalizeGradeKey(raw)
            if (this.isNbGradeKey(key)) return 'NB'
            if (this.isNaGradeKey(key)) return 'NA'
            const parsed = parseFloat(String(raw).replace(',', '.'))
            return Number.isNaN(parsed) ? null : parsed
        },

        async copyEmail(email) {
            try {
                await navigator.clipboard.writeText(email)
                this.copiedEmail = email
                setTimeout(() => { this.copiedEmail = null }, 1500)
            } catch {
                /* clipboard not available */
            }
        },

        // --- Display helpers ---
        formatGrade(value) {
            if (value == null || value === '') return '–'
            if (this.isNbValue(value)) return 'NB'
            if (this.isNaGradeKey(value)) return 'NA'
            const num = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.'))
            if (!Number.isNaN(num)) return num.toFixed(2)
            return String(value)
        },
        gradeClass(value) {
            if (this.isNaGradeKey(value)) return 'grade--na'
            if (this.isNbValue(value)) return 'grade--nb'
            if (value != null && value !== '') return 'grade--ok'
            return 'grade--empty'
        },
    },
}
</script>

<style scoped>
.performances-plus-card {
    border: 1px dashed rgba(16, 38, 58, 0.2);
    background: rgba(255, 255, 255, 0.9);
}

.performances-plus-table-wrap {
    overflow-x: auto;
}

.performances-plus-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 600px;
    table-layout: fixed;
}

.performances-plus-table th,
.performances-plus-table td {
    border: 1px solid rgba(16, 38, 58, 0.1);
    text-align: left;
    padding: 8px 10px;
    vertical-align: top;
}

.performances-plus-table th {
    background: rgba(15, 23, 42, 0.06);
    font-weight: 600;
    white-space: nowrap;
}

.student-col {
    width: 240px;
    min-width: 240px;
}

.grade-col {
    text-align: center !important;
}

.grade-col--narrow {
    width: 140px;
}

.student-cell {
    font-weight: 500;
}

.student-cell--canceled {
    text-decoration: line-through;
    opacity: 0.75;
}

.student-email {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
}

.student-email-text {
    font-size: 0.72rem;
    font-weight: 400;
    color: rgba(16, 38, 58, 0.5);
    word-break: break-all;
}

.student-email-copy {
    cursor: pointer;
    opacity: 0.4;
    transition: opacity 0.15s;
    flex-shrink: 0;
}

.student-email-copy:hover {
    opacity: 0.9;
}

.student-email-copied {
    flex-shrink: 0;
}

.row--canceled td {
    opacity: 0.6;
}

.grade-cell {
    text-align: center !important;
}

.grade-cell-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.grade-effective {
    font-weight: 800;
    font-size: 1.25rem;
    line-height: 1.3;
}

.grade-source-label {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgba(16, 38, 58, 0.45);
    line-height: 1;
}

.grade-secondary {
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.50);
    margin-top: 2px;
}

.grade--ok {
    color: #1e40af;
}

.grade--na {
    color: #c62828;
}

.grade--nb {
    color: #e65100;
}

.grade--empty {
    color: rgba(16, 38, 58, 0.35);
}

.grade-categories {
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid rgba(16, 38, 58, 0.08);
}

.grade-category-block {
    margin-bottom: 4px;
}

.grade-category-block:last-child {
    margin-bottom: 0;
}

.grade-category-row {
    display: flex;
    justify-content: space-between;
    gap: 6px;
    font-size: 0.75rem;
    line-height: 1.5;
}

.grade-category-name {
    color: rgba(16, 38, 58, 0.6);
    text-align: left;
}

.grade-category-value {
    font-weight: 600;
    white-space: nowrap;
}

.grade-category-entries {
    margin-left: 8px;
    padding-left: 6px;
    border-left: 2px solid rgba(16, 38, 58, 0.08);
}

.grade-entry-row {
    display: flex;
    align-items: baseline;
    gap: 4px;
    font-size: 0.68rem;
    line-height: 1.6;
    color: rgba(16, 38, 58, 0.55);
}

.grade-entry-type {
    font-weight: 600;
    flex-shrink: 0;
}

.grade-entry-date {
    flex-shrink: 0;
    color: rgba(16, 38, 58, 0.4);
}

.grade-entry-label {
    flex: 1;
    text-align: left;
    word-break: break-word;
}

.grade-entry-value {
    font-weight: 400;
    flex-shrink: 0;
    white-space: nowrap;
    margin-left: auto;
}
</style>
