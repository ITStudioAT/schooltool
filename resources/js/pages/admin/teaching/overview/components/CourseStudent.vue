<template>
    <ItsGridBox color="primary" title="Schüler:in" icon="mdi-account" class="w-100" v-if="selected_course_student" :disabled="action != ''">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between">
                    <div>
                        <div class="text-body-1 font-weight-medium">{{ selected_course_student.last_name }}, {{ selected_course_student.first_name }}</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ selected_course_student.schoolclass || selected_course_student.class || '–' }}
                        </div>
                    </div>
                    <v-btn color="warning" flat tile @click="closeStudent">Zurück</v-btn>
                </v-card>

                <div v-if="semesterCount === 2" class="d-flex flex-wrap align-center ga-2 mt-2">
                    <v-btn-toggle v-model="activeSemester" mandatory density="compact" color="primary">
                        <v-btn :value="1" size="small">1. Sem</v-btn>
                        <v-btn :value="2" size="small">2. Sem</v-btn>
                        <v-btn :value="3" size="small">1+2</v-btn>
                    </v-btn-toggle>
                </div>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form">
                    <v-card-text v-if="!is_editing">
                        <div class="text-body-2 course-comment" v-if="selected_comment" v-html="commentHtml"></div>
                        <div class="text-body-2" v-else>Kein Kommentar vorhanden.</div>
                        <div class="w-100 text-right">
                            <v-btn flat tile size="small" color="primary" icon="mdi-pencil" @click="editComment" />
                        </div>
                    </v-card-text>
                    <v-card-text v-else>
                        <v-form ref="form" @submit.prevent="saveComment">
                            <div class="mb-4">
                                <label class="text-caption text-medium-emphasis">Kommentar</label>
                                <ItsRichTextEditor v-model="edit_comment" />
                            </div>

                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="abortEdit">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">Speichern</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-school</v-icon>
                        Semesternoten
                        <v-spacer />
                        <v-btn v-if="!is_editing_grades" icon="mdi-pencil" size="x-small" color="primary" variant="flat" @click="editGrades" />
                        <v-btn v-if="is_editing_grades" icon="mdi-check" size="x-small" color="success" variant="flat" @click="saveGrades" />
                        <v-btn v-if="is_editing_grades" icon="mdi-close" size="x-small" color="warning" variant="flat" @click="is_editing_grades = false" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text>
                        <template v-if="!is_editing_grades">
                            <div class="d-flex flex-wrap ga-2" v-if="semesterCount === 2">
                                <v-chip size="small" variant="tonal" :color="selected_course_student.sem_1_grade ? 'success' : 'default'">
                                    1. Sem: {{ selected_course_student.sem_1_grade || '–' }}
                                </v-chip>
                                <v-chip size="small" variant="tonal" :color="selected_course_student.sem_2_grade ? 'success' : 'default'">
                                    2. Sem: {{ selected_course_student.sem_2_grade || '–' }}
                                </v-chip>
                            </div>
                            <div class="d-flex flex-wrap ga-2" v-else>
                                <v-chip size="small" variant="tonal" :color="selected_course_student.sem_grade ? 'success' : 'default'">
                                    Note: {{ selected_course_student.sem_grade || '–' }}
                                </v-chip>
                            </div>
                        </template>
                        <template v-else>
                            <div class="d-flex flex-wrap ga-2" v-if="semesterCount === 2">
                                <v-text-field v-model="grade_form.sem_1_grade" label="1. Semester" density="compact" hide-details class="flex-grow-1" />
                                <v-text-field v-model="grade_form.sem_2_grade" label="2. Semester" density="compact" hide-details class="flex-grow-1" />
                            </div>
                            <div v-else>
                                <v-text-field v-model="grade_form.sem_grade" label="Semesternote" density="compact" hide-details />
                            </div>
                        </template>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="!show_entry_form">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                        <v-icon size="18">mdi-clipboard-text</v-icon>
                        Einträge
                        <v-chip v-if="filteredEntries?.length" size="x-small" color="primary" variant="tonal">
                            {{ filteredEntries.length }}
                        </v-chip>
                        <v-spacer />
                        <v-btn size="small" variant="tonal" color="primary" @click="toggleSortByType">
                            {{ sort_by_type ? 'Sortierung: Typ' : 'Sortierung: Datum' }}
                        </v-btn>
                        <v-btn icon="mdi-plus" size="small" color="primary" variant="tonal" @click="newEntry" />
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="entry in sortedEntries" :key="entry.id">
                                <div class="d-flex align-center ga-2 w-100">
                                    <v-chip v-if="entry.date" size="x-small" variant="tonal" color="primary">
                                        {{ formatDate(entry.date) }}
                                    </v-chip>
                                    <v-chip v-if="entry.type" size="x-small" variant="outlined">
                                        {{ workTypeLabel(entry.type) }}
                                    </v-chip>
                                    <v-chip v-if="entry.grade" size="small" variant="tonal" color="success">
                                        {{ entry.grade }}
                                    </v-chip>
                                    <div class="text-caption flex-grow-1">
                                        {{ entry.description || '' }}
                                    </div>
                                    <v-btn icon="mdi-pencil" size="x-small" color="primary" variant="tonal" @click="editEntry(entry)" />
                                    <v-btn
                                        v-if="delete_entry_id !== entry.id"
                                        icon="mdi-delete"
                                        size="x-small"
                                        color="warning"
                                        variant="tonal"
                                        @click="delete_entry_id = entry.id" />
                                    <v-btn
                                        v-if="delete_entry_id === entry.id"
                                        icon="mdi-delete-off"
                                        size="x-small"
                                        color="success"
                                        variant="tonal"
                                        @click="delete_entry_id = null" />
                                    <v-btn v-if="delete_entry_id === entry.id" icon="mdi-delete" size="x-small" color="error" variant="tonal" @click="deleteEntry(entry)" />
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!filteredEntries?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Einträge vorhanden.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                    <v-divider v-if="categoryGroups.length" />
                    <v-card-text v-if="categoryGroups.length" class="py-2">
                        <div class="d-flex align-center justify-space-between mb-2">
                            <div class="text-subtitle-2">Auswertung</div>
                            <v-btn
                                :icon="show_auswertung ? 'mdi-eye' : 'mdi-eye-off'"
                                size="x-small"
                                variant="tonal"
                                color="primary"
                                @click="show_auswertung = !show_auswertung" />
                        </div>
                        <v-list v-if="show_auswertung" density="compact">
                            <template v-for="cat in categoryGroups" :key="`cat-${cat.name}`">
                                <v-list-item>
                                <div class="d-flex align-center ga-2 w-100">
                                    <v-list-item-title class="text-subtitle-2" :class="!cat.rows.length ? 'text-warning' : ''">
                                        {{ cat.name }}
                                    </v-list-item-title>
                                    <v-chip size="x-small" variant="outlined">{{ cat.weight }}%</v-chip>
                                    <v-spacer />
                                    <div v-if="cat.value != null || cat.grade" class="text-caption text-medium-emphasis">
                                        Bewertung: {{ cat.value != null ? formatTwoDecimals(cat.value) : formatTwoDecimals(cat.grade) }}
                                    </div>
                                    </div>
                                </v-list-item>
                                <v-list-item v-for="row in cat.rows" :key="`cat-${cat.name}-${row.key}`">
                                    <div class="d-flex align-center ga-2 w-100">
                                        <v-chip v-if="row.type" size="x-small" variant="outlined">
                                            {{ workTypeLabel(row.type) }}
                                        </v-chip>
                                        <v-spacer />
                                        <v-chip v-if="row.sum != null" size="x-small" variant="tonal" color="primary">Σ {{ row.sum }}</v-chip>
                                        <v-chip v-if="row.grade" size="x-small" variant="tonal" color="primary">{{ row.grade }}</v-chip>
                                        <v-chip v-if="row.date" size="x-small" variant="tonal" color="primary">
                                            {{ formatDate(row.date) }}
                                        </v-chip>
                                        <v-chip v-if="row.value != null" size="x-small" variant="tonal" color="primary">
                                            {{ row.value }}
                                        </v-chip>
                                    </div>
                                </v-list-item>
                            </template>
                            <v-list-item v-if="totalGrade">
                                <div class="d-flex align-center ga-2 w-100">
                                    <v-list-item-title class="text-subtitle-2">Gesamtnote</v-list-item-title>
                                    <v-spacer />
                                    <div
                                        class="text-subtitle-2"
                                        :class="hasMissingCategory ? 'text-warning' : 'text-medium-emphasis'">
                                        Bewertung: {{ formatTwoDecimals(totalGrade.value) }}
                                    </div>
                                </div>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-4" v-if="show_entry_form">
                    <v-card-text>
                        <v-form ref="entryForm" @submit.prevent="saveEntry">
                            <div class="text-subtitle-1 mb-2">Neuer Eintrag</div>
                            <v-select v-model="entry_form.type" label="Typ" :items="workTypeItems" item-title="title" item-value="value" clearable />
                            <v-select v-model="entry_form.grade" label="Note" :items="gradeItemsForType" item-title="title" item-value="value" clearable />
                            <v-date-input v-model="entry_form.date" label="Datum" />
                            <v-textarea v-model="entry_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />

                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="warning" flat tile @click="abortEntry">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit">{{ entry_form.id ? 'Aktualisieren' : 'Speichern' }}</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { parseLocalDate } from '@/helpers/date'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

export default {
    components: { ItsGridBox, ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.entryStore = useCourseStudentEntryStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        this.activeSemester = this.config?.user?.teaching_active_semester || 1
        await this.loadEntries()
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            entryStore: null,
            teachingStore: null,
            is_editing: false,
            edit_comment: '',
            is_editing_grades: false,
            grade_form: { sem_1_grade: '', sem_2_grade: '', sem_grade: '' },
            show_entry_form: false,
            entry_form: this.emptyEntryForm(),
            sort_by_type: false,
            delete_entry_id: null,
            show_auswertung: false,
            activeSemester: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['selected_course', 'selected_course_student']),
        ...mapWritableState(useCourseStudentEntryStore, ['entries']),
        ...mapWritableState(useTeachingStore, ['settings']),
        selected_comment() {
            return this.selected_course_student?.comment || ''
        },
        commentHtml() {
            const text = this.selected_comment
            if (!text) return ''
            if (text.includes('<p>') || text.includes('<br')) return text
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
        semesterCount() {
            const schemaId = this.selected_course?.teaching_schema_id
            const grading = schemaId ? this.teachingStore.gradingForSchema(schemaId) : {}
            return grading.semester_count || 1
        },
        sem2StartDate() {
            return this.config?.user?.teaching_count_for_semester_2_date || this.config?.selected_schoolyear?.sem_2_start || null
        },
        filteredEntries() {
            if (this.semesterCount === 1) return this.entries || []
            const semester = this.activeSemester
            if (!semester || semester === 3) return this.entries || []
            if (!this.sem2StartDate) return this.entries || []
            return (this.entries || []).filter((entry) => {
                if (!entry.date) return true
                if (semester === 1) return entry.date < this.sem2StartDate
                if (semester === 2) return entry.date >= this.sem2StartDate
                return true
            })
        },
        teachingWorks() {
            const schemaId = this.selected_course?.teaching_schema_id
            if (!schemaId) return []
            return this.teachingStore.worksForSchema(schemaId)
        },
        workTypeItems() {
            return this.teachingWorks.map((work) => ({
                title: `${work.short_name} - ${work.name}`,
                value: work.short_name,
            }))
        },
        gradeItemsForType() {
            const selectedType = this.entry_form.type
            if (!selectedType) return []
            const work = this.teachingWorks.find((w) => w.short_name === selectedType)
            const grades = work?.grades || []
            return grades.map((grade) => ({
                title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                value: grade.grade,
            }))
        },
        categoryGroups() {
            const schemaId = this.selected_course?.teaching_schema_id
            const grading = schemaId ? this.teachingStore.gradingForSchema(schemaId) : {}
            const categories = grading.categories || []
            const worksByType = new Map(this.teachingWorks.map((w) => [w.short_name, w]))
            const entries = this.filteredEntries
            const usedTypes = new Set()

            const grouped = categories
                .map((cat) => {
                    const works = (cat.works || []).map((w) => (typeof w === 'string' ? { short_name: w, factor: 100 } : w))
                    const rows = []
                    const workAverages = []
                    let categoryPointsGrade = null

                    works.forEach((workItem) => {
                        const type = workItem.short_name
                        const work = worksByType.get(type)
                        if (!work) return
                        usedTypes.add(type)

                        const weight = (parseFloat(workItem.factor) || 0) / 100

                        if (work.calculation === 'points') {
                            const values = entries
                                .filter((entry) => entry.type === type)
                                .map((entry) => this.gradeValueForWork(work, entry.grade))
                                .filter((val) => val !== null)
                            const sum = values.reduce((s, v) => s + v, 0)
                            const rounded = Number.isInteger(sum) ? sum : Number(sum.toFixed(2))
                            const grade = this.pointsGradeForWork(work, rounded)
                            rows.push({
                                key: `sum-${type}`,
                                type,
                                sum: rounded,
                                grade,
                            })
                            if (categoryPointsGrade == null && grade != null && grade !== '') {
                                categoryPointsGrade = grade
                            }
                            let numericGrade = this.gradeValueForWork(work, grade)
                            if (numericGrade === null && grade != null && grade !== '') {
                                const parsed = parseFloat(String(grade).replace(',', '.'))
                                numericGrade = Number.isNaN(parsed) ? null : parsed
                            }
                            if (numericGrade !== null) {
                                workAverages.push({ value: numericGrade, weight })
                            }
                            return
                        }

                        const values = entries
                            .filter((entry) => entry.type === type)
                            .map((entry) => this.gradeValueForWork(work, entry.grade))
                            .filter((val) => val !== null)
                        if (values.length) {
                            const avg = values.reduce((s, v) => s + v, 0) / values.length
                            workAverages.push({ value: avg, weight })
                        }

                        entries
                            .filter((entry) => entry.type === type)
                            .forEach((entry) => {
                                const value = this.gradeValueForWork(work, entry.grade)
                                rows.push({
                                    key: `entry-${entry.id}`,
                                    type,
                                    value: value !== null ? value : entry.grade,
                                    date: entry.date || null,
                                })
                            })
                    })

                    let categoryValue = null
                    let categoryGrade = null
                    if (workAverages.length) {
                        const totalWeight = workAverages.reduce((s, w) => s + w.weight, 0) || 1
                        const weighted = workAverages.reduce((s, w) => s + w.value * w.weight, 0) / totalWeight
                        categoryValue = Number(weighted.toFixed(2))
                        categoryGrade = categoryPointsGrade || null
                    } else if (categoryPointsGrade) {
                        categoryGrade = categoryPointsGrade
                    }

                    return {
                        name: cat.name || 'Kategorie',
                        weight: cat.weight ?? 0,
                        rows,
                        value: categoryValue,
                        grade: categoryGrade,
                    }
                })
                .map((cat) => {
                    if (!cat.rows.length) {
                        return { ...cat, rows: [] }
                    }
                    return cat
                })

            const undefinedRows = []
            entries.forEach((entry) => {
                if (!entry.type || usedTypes.has(entry.type)) return
                const work = worksByType.get(entry.type)
                if (work && work.calculation === 'points') {
                    const values = entries
                        .filter((e) => e.type === entry.type)
                        .map((e) => this.gradeValueForWork(work, e.grade))
                        .filter((val) => val !== null)
                    const sum = values.length ? values.reduce((s, v) => s + v, 0) : 0
                    const rounded = Number.isInteger(sum) ? sum : Number(sum.toFixed(2))
                    if (!undefinedRows.some((r) => r.key === `sum-${entry.type}`)) {
                        undefinedRows.push({
                            key: `sum-${entry.type}`,
                            type: entry.type,
                            sum: rounded,
                            grade: this.pointsGradeForWork(work, rounded),
                        })
                    }
                    return
                }

                const value = work ? this.gradeValueForWork(work, entry.grade) : entry.grade
                undefinedRows.push({
                    key: `entry-${entry.id}`,
                    type: entry.type,
                    value: value !== null ? value : entry.grade,
                    date: entry.date || null,
                })
            })

            if (undefinedRows.length) {
                grouped.push({
                    name: 'Undefiniert',
                    rows: undefinedRows,
                })
            }

            return grouped
        },
        totalGrade() {
            if (!this.categoryGroups.length) return null
            const weightedCats = this.categoryGroups
                .map((cat) => {
                    const value = cat.value != null ? cat.value : cat.grade != null ? parseFloat(String(cat.grade).replace(',', '.')) : null
                    return {
                        value: value,
                        weight: (parseFloat(cat.weight) || 0) / 100,
                    }
                })
                .filter((cat) => cat.value != null && !Number.isNaN(cat.value) && cat.weight > 0)

            if (!weightedCats.length) return null
            const totalWeight = weightedCats.reduce((s, c) => s + c.weight, 0) || 1
            const weighted = weightedCats.reduce((s, c) => s + c.value * c.weight, 0) / totalWeight
            const value = Number(weighted.toFixed(2))

            return {
                value,
            }
        },
        hasMissingCategory() {
            return this.categoryGroups.some((cat) => !cat.rows || !cat.rows.length)
        },
        sortedEntries() {
            const list = this.filteredEntries
            if (!this.sort_by_type) return list
            return [...list].sort((a, b) => {
                const typeA = (a.type || '').toString()
                const typeB = (b.type || '').toString()
                const typeCmp = typeA.localeCompare(typeB, 'de', { sensitivity: 'base' })
                if (typeCmp !== 0) return typeCmp
                const dateA = a.date ? parseLocalDate(a.date).getTime() : 0
                const dateB = b.date ? parseLocalDate(b.date).getTime() : 0
                return dateB - dateA
            })
        },
    },

    watch: {
        activeSemester(val) {
            if (val !== this.config?.user?.teaching_active_semester) {
                this.teachingStore.saveActiveSemester(val)
            }
        },
        'config.user.teaching_active_semester'(val) {
            if (val) this.activeSemester = val
        },
        selected_course_student: {
            handler() {
                this.show_entry_form = false
                this.entry_form = this.emptyEntryForm()
                this.is_editing_grades = false
                this.show_auswertung = false
                this.loadEntries()
            },
        },
        'entry_form.date'(val) {
            if (val && val instanceof Date) {
                this.entry_form.date = this.toDateString(val)
            }
        },
    },

    methods: {
        emptyEntryForm() {
            return {
                id: null,
                type: '',
                grade: '',
                date: this.toDateString(new Date()),
                description: '',
            }
        },
        async loadEntries() {
            if (!this.selected_course?.id || !this.selected_course_student?.id) {
                this.entryStore?.clear()
                return
            }
            await this.entryStore.index(this.selected_course.id, this.selected_course_student.id)
        },
        closeStudent() {
            this.selected_course_student = null
            this.action_2 = ''
            this.entryStore?.clear()
        },
        editGrades() {
            this.grade_form = {
                sem_1_grade: this.selected_course_student?.sem_1_grade || '',
                sem_2_grade: this.selected_course_student?.sem_2_grade || '',
                sem_grade: this.selected_course_student?.sem_grade || '',
            }
            this.is_editing_grades = true
        },
        async saveGrades() {
            if (!this.selected_course) return
            const gradeFields =
                this.semesterCount === 2
                    ? { sem_1_grade: this.grade_form.sem_1_grade || null, sem_2_grade: this.grade_form.sem_2_grade || null }
                    : { sem_grade: this.grade_form.sem_grade || null }

            const studentsInfo = (this.selected_course.students_info || []).map((student) => {
                if (student.id === this.selected_course_student.id) {
                    return { ...student, ...gradeFields }
                }
                return student
            })

            const payload = {
                ...this.selected_course,
                students: studentsInfo,
                students_deleted: this.selected_course.students_deleted || [],
            }

            const ok = await this.courseStore.update(payload)
            if (ok) {
                this.selected_course.students_info = studentsInfo
                this.selected_course_student = studentsInfo.find((s) => s.id === this.selected_course_student.id) || this.selected_course_student
                this.is_editing_grades = false
            }
        },
        editComment() {
            this.edit_comment = this.selected_comment || ''
            this.is_editing = true
        },
        abortEdit() {
            this.is_editing = false
            this.edit_comment = ''
        },
        async saveComment() {
            if (!this.selected_course) return
            const studentsInfo = (this.selected_course.students_info || []).map((student) => {
                if (student.id === this.selected_course_student.id) {
                    return { ...student, comment: this.edit_comment }
                }
                return student
            })

            const payload = {
                ...this.selected_course,
                students: studentsInfo,
                students_deleted: this.selected_course.students_deleted || [],
            }

            const ok = await this.courseStore.update(payload)
            if (ok) {
                this.selected_course.students_info = studentsInfo
                this.selected_course_student = studentsInfo.find((s) => s.id === this.selected_course_student.id) || this.selected_course_student
                this.abortEdit()
            }
        },
        newEntry() {
            this.entry_form = this.emptyEntryForm()
            this.show_entry_form = true
        },
        editEntry(entry) {
            if (!entry) return
            this.entry_form = {
                id: entry.id,
                type: entry.type || '',
                grade: entry.grade || '',
                date: entry.date || '',
                description: entry.description || '',
            }
            this.show_entry_form = true
        },
        abortEntry() {
            this.show_entry_form = false
            this.entry_form = this.emptyEntryForm()
        },
        async saveEntry() {
            if (!this.selected_course || !this.selected_course_student) return
            const payload = {
                id: this.entry_form.id,
                teaching_course_id: this.selected_course.id,
                user_id: this.selected_course_student.id,
                type: this.entry_form.type,
                grade: this.entry_form.grade,
                date: this.entry_form.date instanceof Date ? this.toDateString(this.entry_form.date) : this.entry_form.date,
                description: this.entry_form.description,
            }
            const ok = this.entry_form.id ? await this.entryStore.update(payload) : await this.entryStore.store(payload)
            if (ok) {
                await this.loadEntries()
                this.abortEntry()
            }
        },
        async deleteEntry(entry) {
            const ok = await this.entryStore.destroy(entry.id)
            if (ok) {
                await this.loadEntries()
            }
            this.delete_entry_id = null
        },
        toggleSortByType() {
            this.sort_by_type = !this.sort_by_type
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        workTypeLabel(type) {
            if (!type) return ''
            const found = this.teachingWorks.find((w) => w.short_name === type)
            if (!found) return type
            return `${found.short_name} - ${found.name}`
        },
        gradeValueForWork(work, gradeKey) {
            if (!work || !gradeKey) return null
            const grade = (work.grades || []).find((g) => g.grade === gradeKey)
            if (!grade || grade.value == null) return null
            const num = parseFloat(String(grade.value).replace(',', '.'))
            return Number.isNaN(num) ? null : num
        },
        pointsGradeForWork(work, points) {
            if (!work || work.calculation !== 'points') return null
            const table = work.points_table || []
            if (!table.length) return null
            const sorted = [...table].sort((a, b) => (b.min_points ?? 0) - (a.min_points ?? 0))
            const found = sorted.find((row) => points >= (row.min_points ?? 0))
            return found?.grade || null
        },
        formatTwoDecimals(value) {
            if (value == null || value === '') return ''
            const num = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.'))
            if (Number.isNaN(num)) return ''
            return num.toFixed(2)
        },
        pointsGradeForAnyWork(points) {
            const pointsWork = this.teachingWorks.find((w) => w.calculation === 'points' && (w.points_table || []).length)
            return pointsWork ? this.pointsGradeForWork(pointsWork, points) : null
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
    },
}
</script>

<style scoped>
.course-comment :deep(p) {
    margin: 0;
    min-height: 1.2em;
}
</style>
