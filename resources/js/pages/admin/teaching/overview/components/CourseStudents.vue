<template>
    <ItsGridBox color="primary" title="Schüler:innen" icon="mdi-invoice-list" class="w-100" v-if="selected_course" :disabled="action != ''">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <!-- Anzeige ausgewählter Kurs -->
                <v-card tile flat color="transparent" class="d-flex flex-row align-center justify-space-between" v-if="selected_course">
                    <div>
                        <div class="text-body-1 font-weight-medium">{{ selected_course.title }}</div>
                        <div class="d-flex flex-wrap ga-1 mt-1">
                            <v-chip v-for="cls in selected_course.classes" :key="cls" size="small" variant="tonal">
                                {{ cls }}
                            </v-chip>
                        </div>
                    </div>
                    <div v-if="selectedCourseDateForCourse" class="d-flex align-center ga-2 text-right">
                        <v-btn
                            icon="mdi-chevron-left"
                            size="small"
                            color="primary"
                            variant="tonal"
                            :disabled="!hasPrevCourseDate"
                            @click="selectPrevCourseDate" />
                        <v-chip size="x-large" color="primary" variant="outlined" class="selected-course-date-chip">
                            {{ selectedCourseDateLabel }}
                        </v-chip>
                        <v-btn
                            icon="mdi-chevron-right"
                            size="small"
                            color="primary"
                            variant="tonal"
                            :disabled="!hasNextCourseDate"
                            @click="selectNextCourseDate" />
                    </div>
                    <div v-else class="text-caption text-medium-emphasis">
                        Kein Datum verfügbar
                    </div>
                </v-card>
                <div v-if="semesterCount === 2" class="d-flex flex-wrap align-center ga-2 mt-2">
                    <v-btn-toggle v-model="activeSemester" mandatory density="compact" color="primary">
                        <v-btn :value="1" size="small">1. Sem</v-btn>
                        <v-btn :value="2" size="small">2. Sem</v-btn>
                        <v-btn :value="3" size="small">1+2</v-btn>
                    </v-btn-toggle>
                </div>

                <!-- Ausgewählte Schülerinnen (Anzeige) -->
                <v-card variant="outlined" class="mt-4" v-if="selected_course">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 flex-wrap">
                        <div class="d-flex align-center ga-2 flex-wrap">
                            <v-icon size="18">mdi-account-check</v-icon>
                            Schüler:innen
                            <v-chip v-if="selected_course?.students_info?.length" size="x-small" color="primary" variant="tonal">
                                {{ selected_course.students_info.length }}
                            </v-chip>
                        </div>
                        <v-spacer class="students-header-spacer" />
                        <v-btn
                            v-if="selectedCourseDateForCourse"
                            size="small"
                            class="students-attendance-check-btn"
                            :variant="'flat'"
                            :color="attendanceCheckedForSelectedDate && !hasUnsavedAttendanceChanges ? 'success' : 'warning'"
                            :class="attendanceCheckedForSelectedDate && !hasUnsavedAttendanceChanges ? '' : 'students-attendance-check-btn--open'"
                            :loading="savingAttendance"
                            @click="toggleAttendanceChecked">
                            <v-icon start>{{ attendanceCheckedForSelectedDate && !hasUnsavedAttendanceChanges ? 'mdi-check-circle' : 'mdi-content-save' }}</v-icon>
                            {{ attendanceCheckedForSelectedDate && !hasUnsavedAttendanceChanges ? 'Anwesenheit geprüft' : 'Anwesenheit speichern & prüfen' }}
                        </v-btn>
                        <v-btn
                            size="small"
                            class="students-bulk-btn"
                            :variant="show_bulk_entry ? 'flat' : 'outlined'"
                            :color="show_bulk_entry ? 'warning' : 'primary'"
                            @click="toggleBulkEntry">
                            {{ show_bulk_entry ? 'Sammelaktion schließen' : 'Sammelaktion' }}
                        </v-btn>
                    </v-card-title>
                    <v-card-text v-if="show_bulk_entry" class="pt-0">
                        <v-card variant="outlined" class="pa-3">
                            <div class="text-caption text-medium-emphasis mb-2">Eintrag für mehrere Schüler:innen</div>
                            <v-form ref="bulkEntryForm" @submit.prevent="saveBulkEntry">
                                <v-select v-model="bulk_entry_form.type" label="Typ" :items="workTypeItems" item-title="title" item-value="value" clearable />
                                <v-select v-model="bulk_entry_form.grade" label="Note" :items="gradeItemsForType" item-title="title" item-value="value" clearable />
                                <v-date-input v-model="bulk_entry_form.date" label="Datum" />
                                <v-textarea v-model="bulk_entry_form.description" label="Beschreibung" rows="3" :counter="1024" :maxlength="1024" />

                                <div class="d-flex align-center justify-space-between mt-2">
                                    <div class="d-flex align-center ga-2">
                                        <v-btn size="small" variant="text" @click="selectAllBulkStudents">Alle auswählen</v-btn>
                                        <v-btn size="small" variant="text" @click="clearBulkStudents">Keine auswählen</v-btn>
                                    </div>
                                    <v-btn
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :disabled="!bulkEntryEnabled"
                                        type="submit">
                                        {{ bulk_entry_form.student_ids.length ? `Auf ${bulk_entry_form.student_ids.length} Schüler:in(nen) anwenden` : 'Auf alle anwenden' }}
                                    </v-btn>
                                </div>
                            </v-form>
                        </v-card>
                    </v-card-text>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item
                                v-for="student in sortedSelectedStudents"
                                :key="student.id"
                                :class="show_bulk_entry ? '' : ''">
                                <div class="student-row d-flex flex-wrap align-center ga-2 w-100">
                                    <v-checkbox
                                        v-if="show_bulk_entry"
                                        v-model="bulk_entry_form.student_ids"
                                        :value="student.id"
                                        density="compact"
                                        hide-details
                                        class="flex-grow-0" />
                                    <div class="student-presence student-presence--left">
                                        <v-btn
                                            v-if="selectedCourseDateForCourse"
                                            :key="`presence-${student.id}-${isStudentPresentForSelectedDate(student.id) ? '1' : '0'}`"
                                            size="x-small"
                                            :color="isStudentPresentForSelectedDate(student.id) ? 'success' : 'error'"
                                            variant="tonal"
                                            @click.stop="toggleStudentPresence(student)">
                                            <v-icon size="16">
                                                {{ isStudentPresentForSelectedDate(student.id) ? 'mdi-check' : 'mdi-close' }}
                                            </v-icon>
                                        </v-btn>
                                    </div>
                                    <v-chip v-if="student.schoolclass || student.class" size="x-small" variant="tonal" color="primary">
                                        {{ student.schoolclass || student.class }}
                                    </v-chip>
                                    <div class="student-name text-body-2" :class="show_bulk_entry ? '' : 'cursor-pointer'" @click="show_bulk_entry ? null : openStudent(student)">
                                        {{ student.last_name }}, {{ student.first_name }}
                                    </div>
                                    <v-chip v-if="(student.stars || []).length" size="x-small" variant="tonal" color="amber-darken-2">
                                        <v-icon start size="14">mdi-star</v-icon>
                                        {{ (student.stars || []).length }}
                                    </v-chip>
                                    <div class="student-metrics d-flex flex-wrap align-center ga-2 ml-auto">
                                        <template v-for="(count, type) in (studentBehaviourCounts[student.id] || {})" :key="`beh-${student.id}-${type}`">
                                            <v-chip size="x-small" variant="tonal" color="warning">{{ type }}{{ count > 1 ? ` ×${count}` : '' }}</v-chip>
                                        </template>
                                        <v-chip
                                            v-if="studentOpenNotificationCounts[student.id]"
                                            size="x-small"
                                            variant="flat"
                                            :color="dueDateColor(studentOpenNotificationDueDates[student.id])">
                                            <v-icon start size="14">mdi-bell-alert</v-icon>
                                            {{ studentOpenNotificationCounts[student.id] }}
                                        </v-chip>
                                        <template v-if="semesterCount === 2">
                                            <v-chip v-if="student.sem_1_grade" size="x-small" variant="tonal" color="success">{{ student.sem_1_grade }}</v-chip>
                                            <v-chip v-if="student.sem_2_grade && activeSemester !== 1" size="x-small" variant="tonal" color="success">{{ student.sem_2_grade }}</v-chip>
                                        </template>
                                        <template v-else>
                                            <v-chip v-if="student.sem_grade" size="x-small" variant="tonal" color="success">{{ student.sem_grade }}</v-chip>
                                        </template>
                                    </div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!selected_course?.students_info?.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Schülerinnen ausgewählt.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useImport116Store } from '@/stores/admin/teaching/Import116Store'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.import116Store = useImport116Store()
        this.courseStore = useCourseStore()
        this.courseDateStore = useCourseDateStore()
        this.entryStore = useCourseStudentEntryStore()
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.teachingStore = useTeachingStore()
        if (!this.teachingStore.settings) {
            await this.teachingStore.loadSettings()
        }
        this.activeSemester = Number(this.config?.user?.teaching_active_semester) || 1
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            import116Store: null,
            courseStore: null,
            courseDateStore: null,
            entryStore: null,
            behaviourEntryStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },

            delete_level: 0,
            show_bulk_entry: false,
            bulk_entry_form: this.emptyBulkEntryForm(),
            activeSemester: null,
            presence_date_id: null,
            presence_by_student: {},
            savingAttendance: false,
            hasUnsavedAttendanceChanges: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useImport116Store, ['import116_students']),
        ...mapWritableState(useCourseStore, ['courses', 'classes', 'selected_course', 'selected_course_id', 'selected_course_student']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useTeachingStore, ['settings']),
        semesterCount() {
            const schemaId = this.selected_course?.teaching_schema_id
            const grading = schemaId ? this.teachingStore.gradingForSchema(schemaId) : {}
            return Number(grading.semester_count) || 1
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
            const selectedType = this.bulk_entry_form.type
            if (!selectedType) return []
            const work = this.teachingWorks.find((w) => w.short_name === selectedType)
            const grades = work?.grades || []
            return grades.map((grade) => ({
                title: grade.name ? `${grade.grade} (${grade.name})` : grade.grade,
                value: grade.grade,
            }))
        },
        studentItems() {
            const list = this.selected_course?.students_info || []
            return [...list]
                .sort((a, b) => {
                    const classA = (a.schoolclass || a.class || '').toString()
                    const classB = (b.schoolclass || b.class || '').toString()
                    const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
                    if (classCmp !== 0) return classCmp

                    const lastA = (a.last_name || '').toString()
                    const lastB = (b.last_name || '').toString()
                    const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                    if (lastCmp !== 0) return lastCmp

                    const firstA = (a.first_name || '').toString()
                    const firstB = (b.first_name || '').toString()
                    return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
                })
                .map((student) => ({
                    title: this.studentLabel(student),
                    value: student.id,
                }))
        },
        hasStudents() {
            return (this.selected_course?.students_info || []).length > 0
        },
        bulkEntryEnabled() {
            if (!this.hasStudents) return false
            const type = (this.bulk_entry_form.type || '').toString().trim()
            const grade = (this.bulk_entry_form.grade || '').toString().trim()
            const desc = (this.bulk_entry_form.description || '').toString().trim()
            return !!(type || grade || desc)
        },
        filteredImport116Students() {
            const list = this.import116_students || []
            const selected = this.selected_course?.students_info || []
            if (!selected.length) return list

            const selectedEmails = new Set(selected.map((student) => (student.email || '').toString().trim().toLowerCase()).filter((email) => email))

            if (!selectedEmails.size) return list

            return list.filter((student) => {
                const email = (student.email || '').toString().trim().toLowerCase()
                return !email || !selectedEmails.has(email)
            })
        },
        schoolSem2StartDate() {
            return this.config?.selected_schoolyear?.sem_2_start || null
        },
        countSem2StartDate() {
            return this.config?.user?.teaching_count_for_semester_2_date || this.schoolSem2StartDate || null
        },
        studentBehaviourCounts() {
            let entries = (this.behaviourEntryStore?.courseEntries || []).filter((entry) => (entry.kind || 'behaviour') === 'behaviour')
            if (this.semesterCount === 2 && this.activeSemester !== 3) {
                const boundary = this.normalizeDateKey(this.schoolSem2StartDate || this.countSem2StartDate)
                if (boundary) {
                    entries = entries.filter((e) => {
                        if (!e.date) return true
                        const d = this.normalizeDateKey(e.date)
                        if (!d) return true
                        if (this.activeSemester === 1) return d < boundary
                        if (this.activeSemester === 2) return d >= boundary
                        return true
                    })
                }
            }
            const result = {}
            entries.forEach((entry) => {
                if (!result[entry.user_id]) result[entry.user_id] = {}
                const type = entry.type || '?'
                result[entry.user_id][type] = (result[entry.user_id][type] || 0) + 1
            })
            return result
        },
        studentOpenNotificationCounts() {
            let entries = (this.behaviourEntryStore?.courseEntries || []).filter((entry) => entry.kind === 'notification' && !!entry.due_date && !entry.done_date)
            if (this.semesterCount === 2 && this.activeSemester !== 3) {
                const boundary = this.normalizeDateKey(this.schoolSem2StartDate || this.countSem2StartDate)
                if (boundary) {
                    entries = entries.filter((e) => {
                        if (!e.date) return true
                        const d = this.normalizeDateKey(e.date)
                        if (!d) return true
                        if (this.activeSemester === 1) return d < boundary
                        if (this.activeSemester === 2) return d >= boundary
                        return true
                    })
                }
            }
            const result = {}
            entries.forEach((entry) => {
                result[entry.user_id] = (result[entry.user_id] || 0) + 1
            })
            return result
        },
        studentOpenNotificationDueDates() {
            let entries = (this.behaviourEntryStore?.courseEntries || []).filter((entry) => entry.kind === 'notification' && !!entry.due_date && !entry.done_date)
            if (this.semesterCount === 2 && this.activeSemester !== 3) {
                const boundary = this.normalizeDateKey(this.schoolSem2StartDate || this.countSem2StartDate)
                if (boundary) {
                    entries = entries.filter((e) => {
                        if (!e.date) return true
                        const d = this.normalizeDateKey(e.date)
                        if (!d) return true
                        if (this.activeSemester === 1) return d < boundary
                        if (this.activeSemester === 2) return d >= boundary
                        return true
                    })
                }
            }

            const result = {}
            entries.forEach((entry) => {
                const due = this.normalizeDateKey(entry.due_date)
                if (!due) return
                if (!result[entry.user_id] || due < result[entry.user_id]) {
                    result[entry.user_id] = due
                }
            })
            return result
        },
        sortedSelectedStudents() {
            const list = this.selected_course?.students_info || []
            return [...list].sort((a, b) => {
                const classA = (a.schoolclass || a.class || '').toString()
                const classB = (b.schoolclass || b.class || '').toString()
                const classCmp = classA.localeCompare(classB, 'de', { numeric: true, sensitivity: 'base' })
                if (classCmp !== 0) return classCmp

                const lastA = (a.last_name || '').toString()
                const lastB = (b.last_name || '').toString()
                const lastCmp = lastA.localeCompare(lastB, 'de', { sensitivity: 'base' })
                if (lastCmp !== 0) return lastCmp

                const firstA = (a.first_name || '').toString()
                const firstB = (b.first_name || '').toString()
                return firstA.localeCompare(firstB, 'de', { sensitivity: 'base' })
            })
        },
        selectedCourseDateForCourse() {
            const selectedDate = this.selected_courseDate
            const selectedCourse = this.selected_course
            if (!selectedDate?.id || !selectedCourse?.id) return null
            const exists = (selectedCourse.course_dates || []).some((d) => String(d?.id) === String(selectedDate.id))
            return exists ? selectedDate : null
        },
        sortedCourseDates() {
            const dates = Array.isArray(this.selected_course?.course_dates) ? [...this.selected_course.course_dates] : []
            return dates
                .filter((d) => !!this.normalizeDateKey(d?.date))
                .sort((a, b) => {
                    const da = this.normalizeDateKey(a?.date)
                    const db = this.normalizeDateKey(b?.date)
                    if (da !== db) return da.localeCompare(db)
                    const ha = Array.isArray(a?.hours) ? Math.min(...a.hours.map((h) => Number(h)).filter((h) => Number.isFinite(h))) : 999
                    const hb = Array.isArray(b?.hours) ? Math.min(...b.hours.map((h) => Number(h)).filter((h) => Number.isFinite(h))) : 999
                    return ha - hb
                })
        },
        selectedCourseDateIndex() {
            if (!this.selectedCourseDateForCourse?.id) return -1
            return this.sortedCourseDates.findIndex((d) => String(d?.id) === String(this.selectedCourseDateForCourse.id))
        },
        hasPrevCourseDate() {
            return this.selectedCourseDateIndex > 0
        },
        hasNextCourseDate() {
            return this.selectedCourseDateIndex >= 0 && this.selectedCourseDateIndex < this.sortedCourseDates.length - 1
        },
        attendanceCheckedForSelectedDate() {
            const courseDate = this.selectedCourseDateForCourse
            if (!courseDate) return false
            if (typeof courseDate.attendance_checked === 'boolean') return courseDate.attendance_checked
            const status = Array.isArray(courseDate.status) ? courseDate.status : []
            return status.includes('att_checked:1')
        },
        selectedCourseDateLabel() {
            const courseDate = this.selectedCourseDateForCourse
            if (!courseDate) return ''
            const weekday = this.getWeekday(courseDate.date)
            const date = this.formatDate(courseDate.date)
            const hours = Array.isArray(courseDate.hours)
                ? [...courseDate.hours]
                    .map((h) => Number(h))
                    .filter((h) => Number.isFinite(h))
                    .sort((a, b) => a - b)
                    .map((h) => `${h}. Std`)
                    .join(', ')
                : ''
            if (weekday && date && hours) return `${weekday}, ${date} - ${hours}`
            if (date && hours) return `${date} - ${hours}`
            return date || hours || ''
        },
    },

    watch: {
        activeSemester(val) {
            if (val !== this.config?.user?.teaching_active_semester) {
                this.teachingStore.saveActiveSemester(val)
            }
        },
        'config.user.teaching_active_semester'(val) {
            if (val) this.activeSemester = Number(val) || 1
        },
        selected_course: {
            handler(course) {
                if (course?.id) {
                    this.behaviourEntryStore.indexByCourse(course.id)
                    if (this.selected_courseDate?.id) {
                        const dates = Array.isArray(course.course_dates) ? [...course.course_dates] : []
                        const idx = dates.findIndex((d) => String(d?.id) === String(this.selected_courseDate.id))
                        if (idx === -1) {
                            this.selected_courseDate = null
                        } else {
                            const fresh = dates[idx] || null
                            this.selected_courseDate = fresh
                            if (fresh) {
                                this.syncPresenceMapFromDate(fresh)
                            }
                        }
                    }
                } else {
                    this.behaviourEntryStore.courseEntries = []
                    this.selected_courseDate = null
                    this.presence_date_id = null
                    this.presence_by_student = {}
                }
            },
        },
        selected_courseDate: {
            handler(val, oldVal) {
                if (!val?.id) return
                this.syncPresenceMapFromDate(val)
                // Clear unsaved flag only when switching to a different date
                if (String(val.id) !== String(oldVal?.id)) {
                    this.hasUnsavedAttendanceChanges = false
                }
            },
            deep: false,
        },
        'data.classes': {
            handler(newClasses) {
                if (this.action !== 'teaching_course_new_or_edit') return
                if (!Array.isArray(newClasses)) return
                this.selectStudents(newClasses)
            },
            deep: true,
        },
        'bulk_entry_form.date'(val) {
            if (val && val instanceof Date) {
                this.bulk_entry_form.date = this.toDateString(val)
            }
        },
    },

    methods: {
        normalizeDateKey(date) {
            if (!date) return ''
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}/.test(date)) {
                return date.slice(0, 10)
            }
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) return ''
            return this.toDateString(parsed)
        },
        emptyBulkEntryForm() {
            return {
                student_ids: [],
                type: '',
                grade: '',
                date: this.toDateString(new Date()),
                description: '',
            }
        },
        toggleBulkEntry() {
            this.show_bulk_entry = !this.show_bulk_entry
            if (this.show_bulk_entry) {
                this.bulk_entry_form = this.emptyBulkEntryForm()
            }
        },
        cancelBulkEntry() {
            this.show_bulk_entry = false
            this.bulk_entry_form = this.emptyBulkEntryForm()
        },
        async saveBulkEntry() {
            if (!this.selected_course) return
            const allIds = (this.selected_course?.students_info || []).map((s) => s.id)
            const targetIds = this.bulk_entry_form.student_ids.length ? this.bulk_entry_form.student_ids : allIds
            if (!targetIds.length) return

            const date = this.bulk_entry_form.date instanceof Date ? this.toDateString(this.bulk_entry_form.date) : this.bulk_entry_form.date
            const basePayload = {
                teaching_course_id: this.selected_course.id,
                type: this.bulk_entry_form.type,
                grade: this.bulk_entry_form.grade,
                date,
                description: this.bulk_entry_form.description,
            }

            let ok = true
            for (const userId of targetIds) {
                const result = await this.entryStore.store({ ...basePayload, user_id: userId })
                if (!result) ok = false
            }

            if (ok) {
                this.cancelBulkEntry()
            }
        },
        selectAllBulkStudents() {
            this.bulk_entry_form.student_ids = (this.selected_course?.students_info || []).map((s) => s.id)
        },
        clearBulkStudents() {
            this.bulk_entry_form.student_ids = []
        },
        studentLabel(student) {
            const cls = student.schoolclass ? `${student.schoolclass} ` : ''
            const name = `${student.last_name || ''}, ${student.first_name || ''}`.trim()
            return `${cls}${name}`.trim()
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        getWeekday(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { weekday: 'long' })
        },
        normalizeCourseDatePayload(response) {
            if (!response) return null
            return response?.data || response
        },
        applyUpdatedCourseDate(updatedDate) {
            if (!updatedDate?.id) return
            this.selected_courseDate = updatedDate
            this.syncPresenceMapFromDate(updatedDate)
            if (this.selected_course?.id) {
                const dates = Array.isArray(this.selected_course.course_dates) ? [...this.selected_course.course_dates] : []
                const idx = dates.findIndex((d) => String(d?.id) === String(updatedDate.id))
                if (idx >= 0) {
                    dates.splice(idx, 1, { ...dates[idx], ...updatedDate })
                } else {
                    dates.push(updatedDate)
                }
                this.selected_course.course_dates = dates

                const courseIdx = (this.courses || []).findIndex((course) => String(course?.id) === String(this.selected_course.id))
                if (courseIdx >= 0) {
                    const course = { ...this.courses[courseIdx] }
                    const courseDates = Array.isArray(course.course_dates) ? [...course.course_dates] : []
                    const dateIdx = courseDates.findIndex((d) => String(d?.id) === String(updatedDate.id))
                    if (dateIdx >= 0) {
                        courseDates.splice(dateIdx, 1, { ...courseDates[dateIdx], ...updatedDate })
                    } else {
                        courseDates.push(updatedDate)
                    }
                    course.course_dates = courseDates
                    this.courses.splice(courseIdx, 1, course)
                    if (String(this.selected_course_id) === String(course.id)) {
                        this.selected_course = course
                    }
                }
            }
        },
        isStudentPresentForSelectedDate(studentId) {
            const date = this.selectedCourseDateForCourse
            if (!date) return true
            const key = String(studentId)
            if (Object.prototype.hasOwnProperty.call(this.presence_by_student, key)) {
                return !!this.presence_by_student[key]
            }
            const attendance = this.getAttendanceMap(date)
            return this.isAttendancePresentValue(attendance[key])
        },
        syncPresenceMapFromDate(courseDate) {
            if (!courseDate?.id) return
            const dateId = String(courseDate.id)
            const attendance = this.getAttendanceMap(courseDate)
            const map = { ...this.presence_by_student }
            // default known keys to present, then apply absences from backend attendance map
            Object.keys(map).forEach((id) => {
                map[id] = true
            })
            Object.entries(attendance).forEach(([id, value]) => {
                const key = String(id || '').trim()
                if (!key) return
                map[key] = this.isAttendancePresentValue(value)
            })
            this.presence_date_id = dateId
            this.presence_by_student = map
        },
        getAttendanceMap(courseDate) {
            if (!courseDate) return {}
            if (courseDate.attendance && typeof courseDate.attendance === 'object') {
                const normalized = this.normalizeIndexedAttendance(courseDate.attendance)
                const unprefixed = {}
                // Strip 's_' prefix from keys
                Object.entries(normalized).forEach(([key, value]) => {
                    const cleanKey = key.startsWith('s_') ? key.substring(2) : key
                    unprefixed[cleanKey] = value
                })
                return this.sanitizeAttendanceMap(unprefixed)
            }
            const status = Array.isArray(courseDate.status) ? courseDate.status : []
            const attendance = {}
            status.forEach((item) => {
                if (typeof item !== 'string' || !item.startsWith('att:')) return
                const parts = item.split(':')
                if (parts.length < 3) return
                const studentId = (parts[1] || '').toString().trim()
                const present = (parts[2] || '').toString().trim()
                if (!studentId) return
                attendance[studentId] = ['1', 'true'].includes(present)
            })
            return this.sanitizeAttendanceMap(attendance)
        },
        normalizeIndexedAttendance(attendanceLike) {
            const input = attendanceLike && typeof attendanceLike === 'object' ? attendanceLike : {}
            const entries = Object.entries(input)
            if (!entries.length) return {}

            // If attendance arrives as indexed keys (0,1,2,...) remap to real student ids by course student order.
            const hasOnlySmallIndexes = entries.every(([k]) => /^\d+$/.test(String(k)) && Number(k) >= 0 && Number(k) <= 200)
            if (!hasOnlySmallIndexes) return input

            const studentIds = Array.isArray(this.selected_course?.students)
                ? this.selected_course.students.map((id) => String(id)).filter((id) => id && id !== 'undefined' && id !== 'null')
                : []
            if (!studentIds.length) return input

            const remapped = {}
            entries.forEach(([idxRaw, value]) => {
                const idx = Number(idxRaw)
                const studentId = studentIds[idx]
                if (!studentId) return
                remapped[String(studentId)] = value
            })
            return remapped
        },
        sanitizeAttendanceMap(attendanceLike) {
            const input = attendanceLike && typeof attendanceLike === 'object' ? attendanceLike : {}
            const validIds = new Set(
                (this.selected_course?.students_info || [])
                    .flatMap((s) => [s?.id, s?.user_id, s?.import116_id])
                    .map((id) => String(id))
                    .filter((id) => id && id !== 'undefined' && id !== 'null')
            )
            const hasKnownStudents = validIds.size > 0
            const sanitized = {}
            Object.entries(input).forEach(([studentId, value]) => {
                const key = String(studentId || '').trim()
                if (!key) return
                const isNumeric = /^\d+$/.test(key)
                const numericKey = isNumeric ? Number(key) : null
                if (hasKnownStudents) {
                    if (!validIds.has(key)) {
                        // Keep large numeric IDs and non-numeric IDs to avoid dropping valid students from partial course payloads.
                        if (isNumeric && Number.isFinite(numericKey) && numericKey >= 1000) {
                            // keep
                        } else if (!isNumeric) {
                            // keep
                        } else {
                            return
                        }
                    }
                } else {
                    // Ignore obvious positional array indexes from legacy payloads.
                    if (isNumeric && Number.isFinite(numericKey) && numericKey >= 0 && numericKey <= 60) return
                }
                if (!this.isAttendancePresentValue(value)) {
                    sanitized[key] = false
                }
            })
            return sanitized
        },
        buildStatusWithAttendanceMeta(baseStatus, attendance, attendanceChecked) {
            const publicStatus = Array.isArray(baseStatus)
                ? [...baseStatus].filter((item) => ['free', 'pruefung'].includes(item))
                : []
            const merged = [...publicStatus]
            Object.entries(attendance || {}).forEach(([studentId, present]) => {
                if (this.isAttendancePresentValue(present)) return
                const key = String(studentId || '').trim()
                if (!key) return
                merged.push(`att:${key}:0`)
            })
            if (attendanceChecked) merged.push('att_checked:1')
            return [...new Set(merged)]
        },
        isAttendancePresentValue(value) {
            if (value === false) return false
            if (value === 0) return false
            if (value === '0') return false
            if (value === 'false') return false
            return true
        },
        async persistAttendance(attendance, attendanceChecked) {
            const date = this.selectedCourseDateForCourse
            if (!date?.id) return false
            const cleanAttendance = this.sanitizeAttendanceMap(attendance)
            const payload = {
                attendance: cleanAttendance,
                attendance_checked: !!attendanceChecked,
            }
            let response = await this.courseDateStore.updateStatus(date.id, payload)
            let updatedDate = this.normalizeCourseDatePayload(response)
            if (!updatedDate?.id) {
                // Backend compatibility fallback: persist attendance via status field on update endpoint.
                const mergedStatus = this.buildStatusWithAttendanceMeta(date.status, cleanAttendance, attendanceChecked)
                response = await this.courseDateStore.update({
                    id: date.id,
                    date: date.date,
                    status: mergedStatus,
                })
                updatedDate = this.normalizeCourseDatePayload(response)
            }
            if (updatedDate?.id) {
                const normalizedStatus = Array.isArray(updatedDate.status)
                    ? updatedDate.status.filter((item) => typeof item === 'string' && ['free', 'pruefung'].includes(item))
                    : []
                this.applyUpdatedCourseDate({
                    ...updatedDate,
                    status: normalizedStatus,
                    attendance: this.getAttendanceMap(updatedDate),
                    attendance_checked: typeof updatedDate.attendance_checked === 'boolean'
                        ? updatedDate.attendance_checked
                        : !!(Array.isArray(updatedDate.status) && updatedDate.status.includes('att_checked:1')),
                })
                return updatedDate
            }
            return false
        },
        toggleStudentPresence(student) {
            if (!student?.id || !this.selectedCourseDateForCourse) return
            const date = this.selectedCourseDateForCourse
            const attendance = this.getAttendanceMap(date)
            const key = String(student.id)
            const isPresent = this.isAttendancePresentValue(attendance[key])

            // Toggle locally only - no API call
            if (isPresent) {
                attendance[key] = false
            } else {
                delete attendance[key]
            }

            // Update only selected_courseDate (minimal state change, no watchers triggered)
            this.selected_courseDate = {
                ...date,
                attendance: attendance,
            }

            // Mark as unsaved
            this.hasUnsavedAttendanceChanges = true
        },
        async toggleAttendanceChecked() {
            if (!this.selectedCourseDateForCourse || this.savingAttendance) return
            const date = this.selectedCourseDateForCourse
            const attendance = { ...this.getAttendanceMap(date) }

            // If there are unsaved changes, always save and mark as checked
            // If no unsaved changes, toggle the checked state
            const nextChecked = this.hasUnsavedAttendanceChanges ? true : !this.attendanceCheckedForSelectedDate

            this.savingAttendance = true
            try {
                await this.persistAttendance(attendance, nextChecked)
                this.hasUnsavedAttendanceChanges = false
            } finally {
                this.savingAttendance = false
            }
        },
        dueDateColor(date) {
            const due = parseLocalDate(date)
            if (isNaN(due.getTime())) return 'warning'
            due.setHours(0, 0, 0, 0)
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return due <= today ? 'error' : 'warning'
        },
        async selectStudents(classes) {
            if (!Array.isArray(classes) || !classes.length) {
                this.import116_students = []
                return
            }
            await this.import116Store.loadClassStudents(classes)
        },

        addAllStudents() {
            if (!this.selected_course) return
            if (!Array.isArray(this.import116_students) || !this.import116_students.length) return

            this.import116_students.slice().forEach((student) => {
                this.addStudent(student)
            })
        },

        removeAllStudents() {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            if (!Array.isArray(this.selected_course.students_info) || !this.selected_course.students_info.length) return

            this.selected_course.students_info.slice().forEach((student) => {
                this.removeStudent(student)
            })
        },

        addStudent(student) {
            if (!this.selected_course) return

            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            const email = (student.email || '').toString().trim().toLowerCase()

            // Check for duplicates by ID (with type conversion) and by email
            const isDuplicateById = this.selected_course.students.some(id => String(id) === String(student.id))
            const isDuplicateByEmail = email && this.selected_course.students_info.some(s => {
                const existingEmail = (s.email || '').toString().trim().toLowerCase()
                return existingEmail && existingEmail === email
            })

            if (!isDuplicateById && !isDuplicateByEmail) {
                this.selected_course.students.push(student.id)
                this.selected_course.students_info.push(student)
            }

            if (this.selected_course.students_deleted.length) {
                if (email) {
                    const remaining = []
                    this.selected_course.students_deleted_info = this.selected_course.students_deleted_info.filter((s) => {
                        const deletedEmail = (s.email || '').toString().trim().toLowerCase()
                        const keep = !deletedEmail || deletedEmail !== email
                        if (keep && s.id) remaining.push(s.id)
                        return keep
                    })
                    this.selected_course.students_deleted = remaining.length ? remaining : this.selected_course.students_deleted.filter((id) => id !== student.id)
                } else {
                    const deletedIndex = this.selected_course.students_deleted.indexOf(student.id)
                    if (deletedIndex !== -1) {
                        this.selected_course.students_deleted.splice(deletedIndex, 1)
                        this.selected_course.students_deleted_info = this.selected_course.students_deleted_info.filter((s) => s.id !== student.id)
                    }
                }
            }

            this.import116_students = this.import116_students.filter((s) => s.id !== student.id)
        },

        isStudentSelected(student) {
            if (!this.selected_course) return false
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            return this.selected_course.students.includes(student.id)
        },

        removeStudent(student) {
            if (!this.selected_course) return
            this.courseStore.ensureCourseStudentCollections(this.selected_course)

            const index = this.selected_course.students.indexOf(student.id)
            if (index !== -1) {
                this.selected_course.students.splice(index, 1)
                this.selected_course.students_info = this.selected_course.students_info.filter((s) => s.id !== student.id)
            }

            if (!this.selected_course.students_deleted.includes(student.id)) {
                this.selected_course.students_deleted.push(student.id)
                this.selected_course.students_deleted_info.push(student)
            }

            const email = (student.email || '').toString().trim().toLowerCase()
            const exists = this.import116_students.some((s) => {
                if (email) {
                    return (s.email || '').toString().trim().toLowerCase() === email
                }
                return s.id === student.id
            })

            if (!exists) {
                this.import116_students.push(student)
            }
        },

        async save(data) {
            const source = this.selected_course && data.id && this.selected_course.id === data.id ? this.selected_course : data
            this.courseStore.ensureCourseStudentCollections(source)

            const payload = {
                ...data,
                students: source.students || [],
                students_deleted: source.students_deleted || [],
            }

            if (data.id) {
                await this.courseStore.update(payload)
            } else {
                await this.courseStore.store(payload)
            }
            await this.courseStore.index()
            this.selected_course = this.courses.find((c) => c.id === data.id) || null
            this.selected_course = null
            this.action = ''
        },

        selectCourse(course) {
            if (this.action === 'teaching_course_new_or_edit') {
                return
            }
            if (this.selected_course != course) {
                this.courseStore.ensureCourseStudentCollections(course)
                this.selected_course = course
                this.delete_level = 0
            }
        },

        newCourse() {
            this.data = {}
            this.action = 'teaching_course_new_or_edit'
        },
        editCourse(course) {
            this.data = { ...course }
            this.selected_course = course
            this.courseStore.ensureCourseStudentCollections(this.selected_course)
            this.action = 'teaching_course_new_or_edit'
            this.selectStudents(this.data.classes || [])
        },
        abortNewCourse() {
            this.selected_course = null
            this.action = ''
        },
        async deleteCourse(course) {
            if (!(await this.courseStore.destroy(course.id))) {
                this.delete_level = 0
                return
            }
            await this.courseStore.index()
            this.selected_course = null
            this.delete_level = 0
        },
        openStudent(student) {
            if (!student) return
            this.action = ''
            this.selected_course_student = student
            this.action_2 = 'course_student_view'
        },
        selectPrevCourseDate() {
            if (!this.hasPrevCourseDate) return
            this.selected_courseDate = this.sortedCourseDates[this.selectedCourseDateIndex - 1] || null
        },
        selectNextCourseDate() {
            if (!this.hasNextCourseDate) return
            this.selected_courseDate = this.sortedCourseDates[this.selectedCourseDateIndex + 1] || null
        },
    },
}
</script>

<style scoped>
.student-row {
    min-width: 0;
}

.selected-course-date-chip {
    min-width: 320px;
    max-width: 60%;
    min-height: 44px;
    margin-left: 12px;
    font-weight: 800;
    background-color: #ffffff !important;
    color: #0d1b2a !important;
    border-color: #2f4ea1 !important;
}

.selected-course-date-chip :deep(.v-chip__content) {
    font-size: 1rem;
    line-height: 1.4;
    white-space: normal;
    overflow-wrap: anywhere;
}

.student-name {
    min-width: 0;
    flex: 0 1 auto;
    max-width: 60%;
    word-break: break-word;
}

.student-metrics {
    min-width: 0;
    margin-left: 0;
    justify-content: flex-end;
    flex: 0 1 auto;
    max-width: 55%;
}

.student-presence {
    flex: 0 0 auto;
}

.student-presence--left {
    margin-left: 0;
}

@media (max-width: 600px) {
    .students-header-spacer {
        display: none;
    }

    .students-bulk-btn {
        width: 100%;
        margin-top: 6px;
    }

    .students-attendance-check-btn {
        width: 100%;
        margin-top: 6px;
    }
}

.students-attendance-check-btn--open {
    color: #1f1300 !important;
    font-weight: 700;
}

@media (min-width: 601px) {
    .student-metrics {
        width: auto;
    }
}

@media (max-width: 900px) {
    .selected-course-date-chip {
        max-width: 100%;
    }

    .student-name {
        max-width: 100%;
    }

    .student-metrics {
        max-width: 100%;
    }
}
</style>
