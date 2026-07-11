<template>
    <ItsGridBox
        v-if="selected_course"
        variant="overview"
        color="primary"
        icon="mdi-table-large"
        class="w-100"
        :disabled="action != ''">
        <template #title>
            <div>Tabelle - {{ selected_course.title }}</div>
        </template>
        <template #header-actions>
            <div class="d-flex align-center ga-1 flex-wrap justify-end">
                <v-chip size="small" color="primary" variant="tonal" prepend-icon="mdi-calendar-clock-outline">
                    {{ sortedCourseDates.length }} Termine
                </v-chip>
                <v-chip size="small" color="success" variant="tonal" prepend-icon="mdi-account-group">
                    {{ sortedSelectedStudents.length }} Schüler:innen
                </v-chip>
            </div>
        </template>

        <v-card variant="outlined" class="course-table-card">
            <v-card-text class="pa-0">
                <div ref="courseTableScroll" class="course-table-scroll">
                    <table class="course-table" data-testid="course-table">
                        <thead>
                            <tr class="course-table-command-row">
                                <th
                                    class="course-table-command-cell"
                                    :colspan="tableColumnCount"
                                    scope="colgroup">
                                    <div class="course-table-command-bar">
                                        <v-btn
                                            class="course-table-command-button"
                                            :color="showAttendanceMarkers ? 'success' : undefined"
                                            density="comfortable"
                                            :icon="showAttendanceMarkers ? 'mdi-account-check' : 'mdi-account-off-outline'"
                                            size="small"
                                            :title="showAttendanceMarkers ? 'Anwesenheit ausblenden' : 'Anwesenheit anzeigen'"
                                            variant="tonal"
                                            @click="toggleAttendanceMarkers" />
                                    </div>
                                </th>
                            </tr>
                            <tr class="course-table-title-row">
                                <th class="course-table-student-col">
                                    <div class="course-table-header-label">Schüler:in</div>
                                </th>
                                <th
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`date-head-${courseDate.id || courseDate.date}`"
                                    :data-course-date-key="courseDateScrollKey(courseDate)"
                                    class="course-table-date-col"
                                    :class="{ 'course-table-date-col--free': isFreeCourseDate(courseDate) }">
                                    <div class="course-table-date-header">
                                        <div class="course-table-date-weekday">{{ courseDateWeekday(courseDate) }}</div>
                                        <div class="course-table-date-title">{{ courseDateDateLabel(courseDate) }}</div>
                                        <div v-if="courseDateHoursLabel(courseDate)" class="course-table-date-hours">
                                            {{ courseDateHoursLabel(courseDate) }}
                                        </div>
                                        <div
                                            v-if="showAttendanceMarkers && isAttendanceToggleable(courseDate)"
                                            class="course-table-date-attendance-actions">
                                            <v-btn
                                                class="course-table-date-attendance-action"
                                                color="success"
                                                density="compact"
                                                icon="mdi-check"
                                                size="x-small"
                                                :title="`${compactCourseDateTitle(courseDate)}: alle anwesend setzen`"
                                                variant="tonal"
                                                @click.stop="openBulkAttendanceDialog(courseDate, true)" />
                                            <v-btn
                                                class="course-table-date-attendance-action"
                                                color="error"
                                                density="compact"
                                                icon="mdi-close"
                                                size="x-small"
                                                :title="`${compactCourseDateTitle(courseDate)}: alle abwesend setzen`"
                                                variant="tonal"
                                                @click.stop="openBulkAttendanceDialog(courseDate, false)" />
                                        </div>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(student, studentIndex) in sortedSelectedStudents"
                                :key="`student-${student.id}`"
                                class="course-table-row">
                                <th scope="row" class="course-table-student-cell">
                                    <div class="course-table-main-text">{{ studentLastName(student) }}</div>
                                    <div class="course-table-student-subline">
                                        <v-icon
                                            v-if="studentSexIcon(student)"
                                            size="14"
                                            :color="studentSexColor(student)"
                                            :title="studentSexTitle(student)">
                                            {{ studentSexIcon(student) }}
                                        </v-icon>
                                        <span v-if="studentFirstName(student)">{{ studentFirstName(student) }}</span>
                                        <span v-if="studentClassValue(student)" class="course-table-student-class">
                                            {{ studentClassValue(student) }}
                                        </span>
                                    </div>
                                </th>
                                <template
                                    v-for="courseDate in sortedCourseDates"
                                    :key="`student-${student.id}-date-${courseDate.id || courseDate.date}`">
                                    <td
                                        v-if="!isFreeCourseDate(courseDate)"
                                        class="course-table-entry-cell">
                                        <v-btn
                                            v-if="showAttendanceMarkers && isAttendanceToggleable(courseDate)"
                                            class="course-table-attendance-marker"
                                            :color="isStudentPresentForCourseDate(student, courseDate) ? 'success' : 'error'"
                                            density="compact"
                                            :icon="isStudentPresentForCourseDate(student, courseDate) ? 'mdi-check' : 'mdi-close'"
                                            :loading="isAttendanceCellSaving(student, courseDate)"
                                            size="x-small"
                                            :title="attendanceMarkerTitle(student, courseDate)"
                                            variant="tonal"
                                            @click.stop="toggleStudentAttendance(student, courseDate)" />
                                    </td>
                                    <td
                                        v-else-if="studentIndex === 0"
                                        class="course-table-entry-cell course-table-entry-cell--free course-table-entry-cell--free-reason"
                                        :rowspan="sortedSelectedStudents.length">
                                        <span class="course-table-free-reason">{{ freeCourseDateReason(courseDate) }}</span>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="!sortedSelectedStudents.length" class="course-table-empty-row">
                                <td :colspan="tableColumnCount">Keine Schüler:innen ausgewählt.</td>
                            </tr>
                            <tr v-else-if="!sortedCourseDates.length" class="course-table-empty-row">
                                <td :colspan="tableColumnCount">Keine Termine vorhanden.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </v-card-text>
        </v-card>

        <v-dialog v-model="bulkAttendanceDialog.open" persistent max-width="460">
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-bold">
                    Anwesenheit setzen
                </v-card-title>
                <v-card-text>
                    Alle Schüler:innen für
                    <strong>{{ bulkAttendanceCourseDateTitle }}</strong>
                    als
                    <strong>{{ bulkAttendanceDialog.present ? 'anwesend' : 'abwesend' }}</strong>
                    markieren?
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn
                        :disabled="bulkAttendanceSaving"
                        variant="text"
                        @click="closeBulkAttendanceDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        :color="bulkAttendanceDialog.present ? 'success' : 'error'"
                        :loading="bulkAttendanceSaving"
                        variant="flat"
                        @click="confirmBulkAttendance">
                        Bestätigen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { parseLocalDate } from '@/helpers/date'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'

export default {
    data() {
        return {
            bulkAttendanceDialog: {
                courseDate: null,
                open: false,
                present: true,
            },
            bulkAttendanceSaving: false,
            courseDateStore: null,
            savingAttendanceCells: {},
            showAttendanceMarkers: true,
        }
    },

    mounted() {
        this.courseDateStore = useCourseDateStore()
        this.scrollToInitialCourseDate()
    },

    watch: {
        sortedCourseDates() {
            this.scrollToInitialCourseDate()
        },
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useCourseStore, ['selected_course', 'students_sort_mode']),
        sortedCourseDates() {
            const dates = Array.isArray(this.selected_course?.course_dates) ? [...this.selected_course.course_dates] : []

            return dates.sort((first, second) => {
                const firstDate = String(first?.date || '')
                const secondDate = String(second?.date || '')
                const dateComparison = firstDate.localeCompare(secondDate)
                if (dateComparison !== 0) return dateComparison

                return Number(first?.id || 0) - Number(second?.id || 0)
            })
        },
        sortedSelectedStudents() {
            const students = Array.isArray(this.selected_course?.students_info) ? [...this.selected_course.students_info] : []

            return students
                .filter((student) => !this.isStudentCanceled(student))
                .sort((first, second) => this.compareStudentsBySelectedSort(first, second))
        },
        tableColumnCount() {
            return this.sortedCourseDates.length + 1
        },
        bulkAttendanceCourseDateTitle() {
            return this.bulkAttendanceDialog.courseDate
                ? this.compactCourseDateTitle(this.bulkAttendanceDialog.courseDate)
                : ''
        },
    },

    methods: {
        compactCourseDateTitle(courseDate) {
            return [this.courseDateWeekday(courseDate), this.courseDateDateLabel(courseDate)].filter(Boolean).join(', ') || 'Ohne Datum'
        },
        courseDateWeekday(courseDate) {
            return this.getWeekdayShort(courseDate?.date)
        },
        courseDateDateLabel(courseDate) {
            return this.formatDateShort(courseDate?.date) || 'Ohne Datum'
        },
        courseDateHoursLabel(courseDate) {
            const hours = Array.isArray(courseDate?.hours) ? courseDate.hours : []
            const sortedHours = [...new Set(hours.map((hour) => Number(hour)).filter((hour) => Number.isFinite(hour)))]
                .sort((first, second) => first - second)

            if (!sortedHours.length) return ''

            return `${sortedHours.map((hour) => `${hour}.`).join(', ')} Std`
        },
        isFreeCourseDate(courseDate) {
            return Array.isArray(courseDate?.status) && courseDate.status.includes('free')
        },
        freeCourseDateReason(courseDate) {
            return String(courseDate?.free_reason || '').trim() || 'Frei'
        },
        toggleAttendanceMarkers() {
            this.showAttendanceMarkers = !this.showAttendanceMarkers
        },
        openBulkAttendanceDialog(courseDate, present) {
            if (!this.isAttendanceToggleable(courseDate)) return

            this.bulkAttendanceDialog = {
                courseDate,
                open: true,
                present,
            }
        },
        closeBulkAttendanceDialog() {
            if (this.bulkAttendanceSaving) return

            this.bulkAttendanceDialog = {
                courseDate: null,
                open: false,
                present: true,
            }
        },
        bulkAttendanceMap(present) {
            if (present) return {}

            return this.sortedSelectedStudents.reduce((attendance, student) => {
                if (student?.id) {
                    attendance[String(student.id)] = false
                }

                return attendance
            }, {})
        },
        async confirmBulkAttendance() {
            const courseDate = this.bulkAttendanceDialog.courseDate
            if (!courseDate?.id || this.bulkAttendanceSaving) return

            this.bulkAttendanceSaving = true

            const previousDate = { ...courseDate, attendance: { ...(courseDate.attendance || {}) } }
            const nextAttendance = this.bulkAttendanceMap(this.bulkAttendanceDialog.present)
            const optimisticDate = { ...courseDate, attendance: nextAttendance }
            this.applyUpdatedCourseDate(optimisticDate)

            try {
                const response = await this.courseDateStore?.updateStatus(courseDate.id, {
                    attendance: nextAttendance,
                    attendance_checked: this.isAttendanceChecked(courseDate),
                })
                if (response) {
                    this.applyUpdatedCourseDate({
                        ...response,
                        attendance: this.getAttendanceMap(response),
                    })
                    this.bulkAttendanceDialog = {
                        courseDate: null,
                        open: false,
                        present: true,
                    }
                } else {
                    this.applyUpdatedCourseDate(previousDate)
                }
            } finally {
                this.bulkAttendanceSaving = false
            }
        },
        isAttendanceToggleable(courseDate) {
            if (!courseDate?.id) return false
            if (this.isFreeCourseDate(courseDate)) return false

            const dateKey = this.normalizeDateKey(courseDate?.date)
            if (!dateKey) return false

            return dateKey <= this.dateKey(new Date())
        },
        attendanceCellKey(student, courseDate) {
            return `${courseDate?.id || 'date'}:${student?.id || 'student'}`
        },
        isAttendanceCellSaving(student, courseDate) {
            return Boolean(this.savingAttendanceCells[this.attendanceCellKey(student, courseDate)])
        },
        attendanceMarkerTitle(student, courseDate) {
            return `${this.studentName(student)} - ${this.compactCourseDateTitle(courseDate)}`
        },
        isStudentPresentForCourseDate(student, courseDate) {
            if (!student?.id || !courseDate) return false
            const attendance = this.getAttendanceMap(courseDate)

            return this.isAttendancePresentValue(attendance[String(student.id)])
        },
        getAttendanceMap(courseDate) {
            if (!courseDate) return {}
            if (courseDate.attendance && typeof courseDate.attendance === 'object') {
                const attendance = {}
                Object.entries(courseDate.attendance).forEach(([studentId, value]) => {
                    const key = String(studentId || '').startsWith('s_')
                        ? String(studentId).substring(2)
                        : String(studentId || '').trim()
                    if (!key) return
                    attendance[key] = value
                })

                return this.sanitizeAttendanceMap(attendance)
            }

            const attendance = {}
            const status = Array.isArray(courseDate?.status) ? courseDate.status : []
            status.forEach((item) => {
                if (typeof item !== 'string' || !item.startsWith('att:')) return
                const parts = item.split(':')
                const studentId = String(parts[1] || '').trim()
                const present = String(parts[2] || '').trim()
                if (!studentId) return
                attendance[studentId] = ['1', 'true'].includes(present)
            })

            return this.sanitizeAttendanceMap(attendance)
        },
        sanitizeAttendanceMap(attendanceLike) {
            const input = attendanceLike && typeof attendanceLike === 'object' ? attendanceLike : {}
            const sanitized = {}

            Object.entries(input).forEach(([studentId, value]) => {
                const key = String(studentId || '').trim()
                if (!key) return
                if (!this.isAttendancePresentValue(value)) {
                    sanitized[key] = false
                }
            })

            return sanitized
        },
        isAttendancePresentValue(value) {
            if (value === false) return false
            if (value === 0) return false
            if (value === '0') return false
            if (value === 'false') return false

            return true
        },
        isAttendanceChecked(courseDate) {
            if (typeof courseDate?.attendance_checked === 'boolean') return courseDate.attendance_checked
            const status = Array.isArray(courseDate?.status) ? courseDate.status : []

            return status.includes('att_checked:1')
        },
        applyUpdatedCourseDate(updatedDate) {
            if (!updatedDate?.id || !this.selected_course?.id) return

            const dates = Array.isArray(this.selected_course.course_dates) ? [...this.selected_course.course_dates] : []
            const index = dates.findIndex((date) => String(date?.id) === String(updatedDate.id))
            if (index >= 0) {
                dates.splice(index, 1, { ...dates[index], ...updatedDate })
            } else {
                dates.push(updatedDate)
            }
            this.selected_course.course_dates = dates

            if (String(this.selected_courseDate?.id) === String(updatedDate.id)) {
                this.selected_courseDate = { ...this.selected_courseDate, ...updatedDate }
            }
        },
        async toggleStudentAttendance(student, courseDate) {
            if (!student?.id || !this.isAttendanceToggleable(courseDate) || this.isAttendanceCellSaving(student, courseDate)) return

            const cellKey = this.attendanceCellKey(student, courseDate)
            this.savingAttendanceCells = { ...this.savingAttendanceCells, [cellKey]: true }

            const previousDate = { ...courseDate, attendance: { ...(courseDate.attendance || {}) } }
            const attendance = this.getAttendanceMap(courseDate)
            const studentId = String(student.id)
            if (this.isAttendancePresentValue(attendance[studentId])) {
                attendance[studentId] = false
            } else {
                delete attendance[studentId]
            }

            const nextAttendance = this.sanitizeAttendanceMap(attendance)
            const optimisticDate = { ...courseDate, attendance: nextAttendance }
            this.applyUpdatedCourseDate(optimisticDate)

            try {
                const response = await this.courseDateStore?.updateStatus(courseDate.id, {
                    attendance: nextAttendance,
                    attendance_checked: this.isAttendanceChecked(courseDate),
                })
                if (response) {
                    this.applyUpdatedCourseDate({
                        ...response,
                        attendance: this.getAttendanceMap(response),
                    })
                } else {
                    this.applyUpdatedCourseDate(previousDate)
                }
            } finally {
                const remainingCells = { ...this.savingAttendanceCells }
                delete remainingCells[cellKey]
                this.savingAttendanceCells = remainingCells
            }
        },
        targetInitialScrollCourseDate(referenceDate = new Date()) {
            const todayKey = this.dateKey(referenceDate)
            const datedCourseDates = this.sortedCourseDates.filter((courseDate) => this.normalizeDateKey(courseDate?.date))
            if (!datedCourseDates.length) return null

            const todayCourseDate = datedCourseDates.find((courseDate) => this.normalizeDateKey(courseDate.date) === todayKey)
            if (todayCourseDate) return todayCourseDate

            return datedCourseDates.find((courseDate) => this.normalizeDateKey(courseDate.date) > todayKey)
                || datedCourseDates[datedCourseDates.length - 1]
        },
        scrollToInitialCourseDate() {
            this.$nextTick(() => {
                const scrollContainer = this.$refs.courseTableScroll
                const targetCourseDate = this.targetInitialScrollCourseDate()
                if (!scrollContainer || !targetCourseDate) return

                const targetKey = this.courseDateScrollKey(targetCourseDate)
                const targetColumn = Array.from(scrollContainer.querySelectorAll('[data-course-date-key]'))
                    .find((element) => element.dataset.courseDateKey === targetKey)
                if (!targetColumn) return

                scrollContainer.scrollLeft = Math.max(
                    targetColumn.offsetLeft - (scrollContainer.clientWidth / 2) + (targetColumn.offsetWidth / 2),
                    0,
                )
            })
        },
        courseDateScrollKey(courseDate) {
            return `course-date-${courseDate?.id || this.normalizeDateKey(courseDate?.date) || 'unknown'}`
        },
        studentName(student) {
            return [student?.last_name, student?.first_name]
                .map((value) => String(value || '').trim())
                .filter(Boolean)
                .join(', ') || 'Schüler:in ohne Namen'
        },
        studentLastName(student) {
            return String(student?.last_name || '').trim() || 'Schüler:in'
        },
        studentFirstName(student) {
            return String(student?.first_name || '').trim()
        },
        studentClassValue(student) {
            return (student?.schoolclass || student?.class || '').toString()
        },
        normalizedStudentSex(student) {
            return String(student?.sex || '').trim().toLowerCase()
        },
        studentSexIcon(student) {
            const sex = this.normalizedStudentSex(student)
            if (sex === 'm') return 'mdi-gender-male'
            if (sex === 'w' || sex === 'f') return 'mdi-gender-female'
            if (sex === 'd') return 'mdi-gender-non-binary'

            return ''
        },
        studentSexColor(student) {
            const sex = this.normalizedStudentSex(student)
            if (sex === 'm') return 'blue'
            if (sex === 'w' || sex === 'f') return 'pink'
            if (sex === 'd') return 'amber-darken-2'

            return undefined
        },
        studentSexTitle(student) {
            const sex = this.normalizedStudentSex(student)
            if (sex === 'm') return 'männlich'
            if (sex === 'w' || sex === 'f') return 'weiblich'
            if (sex === 'd') return 'divers'

            return ''
        },
        isStudentCanceled(student) {
            return !!student?.canceled_at || !!student?.deleted_at
        },
        compareStudentsBySelectedSort(first, second) {
            const firstLastName = (first?.last_name || '').toString()
            const secondLastName = (second?.last_name || '').toString()
            const firstFirstName = (first?.first_name || '').toString()
            const secondFirstName = (second?.first_name || '').toString()
            const firstClass = this.studentClassValue(first)
            const secondClass = this.studentClassValue(second)

            if (this.students_sort_mode === 'last_name_first_name') {
                const lastNameComparison = firstLastName.localeCompare(secondLastName, 'de', { sensitivity: 'base' })
                if (lastNameComparison !== 0) return lastNameComparison

                const firstNameComparison = firstFirstName.localeCompare(secondFirstName, 'de', { sensitivity: 'base' })
                if (firstNameComparison !== 0) return firstNameComparison

                return firstClass.localeCompare(secondClass, 'de', { numeric: true, sensitivity: 'base' })
            }

            const classComparison = firstClass.localeCompare(secondClass, 'de', { numeric: true, sensitivity: 'base' })
            if (classComparison !== 0) return classComparison

            const lastNameComparison = firstLastName.localeCompare(secondLastName, 'de', { sensitivity: 'base' })
            if (lastNameComparison !== 0) return lastNameComparison

            return firstFirstName.localeCompare(secondFirstName, 'de', { sensitivity: 'base' })
        },
        getWeekdayShort(date) {
            if (!date) return ''
            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            return parsedDate.toLocaleDateString('de-DE', { weekday: 'short' })
        },
        formatDateShort(date) {
            if (!date) return ''
            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            return parsedDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
        },
        normalizeDateKey(date) {
            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            return this.dateKey(parsedDate)
        },
        dateKey(date) {
            const parsedDate = parseLocalDate(date)
            if (isNaN(parsedDate.getTime())) return ''

            const year = parsedDate.getFullYear()
            const month = String(parsedDate.getMonth() + 1).padStart(2, '0')
            const day = String(parsedDate.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },
    },
}
</script>

<style scoped>
.course-table-card {
    border: 1px solid rgba(37, 99, 235, 0.16);
    overflow: hidden;
}

.course-table-scroll {
    max-width: 100%;
    overflow: auto;
}

.course-table {
    border-collapse: separate;
    border-spacing: 0;
    min-width: 760px;
    table-layout: auto;
    width: max-content;
}

.course-table th,
.course-table td {
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
    border-right: 1px solid rgba(148, 163, 184, 0.14);
    padding: 8px 7px;
    vertical-align: middle;
}

.course-table thead th {
    background: #eff6ff;
    color: #1e3a8a;
    font-size: 0.74rem;
    font-weight: 800;
    position: sticky;
    text-align: left;
    top: 0;
    z-index: 3;
}

.course-table-command-cell {
    height: 36px;
    padding: 5px 7px;
    top: 0;
    z-index: 5 !important;
}

.course-table-command-bar {
    align-items: center;
    display: flex;
    gap: 6px;
    justify-content: flex-start;
    left: 7px;
    min-height: 24px;
    position: sticky;
    width: fit-content;
}

.course-table-command-button {
    height: 28px;
    min-width: 28px;
    width: 28px;
}

.course-table-title-row th {
    top: 36px;
}

.course-table-student-col,
.course-table-student-cell {
    left: 0;
    min-width: 132px;
    position: sticky;
    width: 1%;
    white-space: nowrap;
}

.course-table-student-col {
    z-index: 4 !important;
}

.course-table-student-cell {
    background: linear-gradient(180deg, #ffffff 0%, #fbfefc 100%);
    text-align: left;
    z-index: 2;
}

.course-table-date-col {
    min-width: 88px;
    width: 88px;
}

.course-table-header-label {
    font-size: 0.78rem;
    font-weight: 850;
}

.course-table-date-header {
    align-items: center;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-height: 44px;
    text-align: center;
}

.course-table-date-attendance-actions {
    align-items: center;
    display: flex;
    gap: 4px;
    justify-content: center;
}

.course-table-date-attendance-action {
    height: 22px;
    min-width: 22px;
    width: 22px;
}

.course-table-date-title {
    color: #172033;
    font-size: 0.82rem;
    font-weight: 850;
    line-height: 1.2;
}

.course-table-date-weekday {
    color: #1e3a8a;
    font-size: 0.76rem;
    font-weight: 700;
    line-height: 1.1;
}

.course-table-date-hours,
.course-table-sub-text {
    color: #64748b;
    font-size: 0.74rem;
    line-height: 1.25;
}

.course-table-row {
    background: #ffffff;
    transition: background 0.15s ease, box-shadow 0.15s ease;
}

.course-table-row:nth-child(even) td,
.course-table-row:nth-child(even) .course-table-student-cell {
    background: #f8fbff;
}

.course-table-row:hover td,
.course-table-row:hover .course-table-student-cell {
    background: #f8fafc;
}

.course-table-main-text {
    color: #172033;
    font-weight: 500;
    line-height: 1.12;
    overflow-wrap: anywhere;
}

.course-table-student-subline {
    align-items: center;
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    font-size: 0.76rem;
    font-weight: 500;
    gap: 5px;
    line-height: 1.08;
    min-width: 0;
}

.course-table-student-class {
    color: #334155;
    font-weight: 650;
}

.course-table-entry-cell {
    background: #ffffff;
    text-align: center;
}

.course-table-attendance-marker {
    height: 24px;
    min-width: 24px;
    width: 24px;
}

.course-table thead th.course-table-date-col--free,
.course-table-row td.course-table-entry-cell--free,
.course-table-row:nth-child(even) td.course-table-entry-cell--free {
    background: #e8f5e9;
}

.course-table-entry-cell--free-reason {
    overflow: hidden;
    position: relative;
    text-align: center;
    vertical-align: top;
    width: 88px;
}

.course-table-free-reason {
    color: #1b5e20;
    display: inline-block;
    font-size: 1.24rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    left: 50%;
    line-height: 1.2;
    max-height: calc(100% - 16px);
    opacity: 0.42;
    overflow: hidden;
    position: absolute;
    text-orientation: mixed;
    top: 8px;
    translate: -50% 0;
    white-space: normal;
    writing-mode: vertical-rl;
}

.course-table-empty-row td {
    color: #64748b;
    font-size: 0.86rem;
    padding: 14px 12px;
}

@media (max-width: 700px) {
    .course-table {
        min-width: 680px;
    }

    .course-table-student-col,
    .course-table-student-cell {
        min-width: 120px;
        width: 1%;
    }

    .course-table-date-col {
        min-width: 82px;
        width: 82px;
    }

    .course-table-entry-cell--free-reason {
        width: 82px;
    }

    .course-table th,
    .course-table td {
        padding: 8px 6px;
    }
}
</style>
