<template>
    <v-card variant="outlined" class="attendance-matrix-card mt-2" data-testid="teaching-attendance-matrix">
        <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
            <v-icon size="18">mdi-table</v-icon>
            Anwesenheiten
        </v-card-title>
        <v-divider />
        <v-card-text>
            <v-alert v-if="!students.length" type="info" variant="tonal">
                Keine Schüler:innen im Kurs vorhanden.
            </v-alert>
            <v-alert v-else-if="!filteredCourseDates.length" type="info" variant="tonal">
                Keine Termine im gewählten Zeitraum vorhanden.
            </v-alert>
            <div v-else class="attendance-matrix-wrap">
                <table class="attendance-matrix-table" data-testid="teaching-attendance-table">
                    <thead>
                        <tr>
                            <th class="sticky-col">Schüler:in</th>
                            <th v-for="courseDate in filteredCourseDates" :key="courseDate.id">
                                <div class="attendance-date-head">
                                    <div>{{ formatDate(courseDate.date) }}</div>
                                    <div class="attendance-date-weekday">{{ weekdayLabel(courseDate.date) }}</div>
                                </div>
                            </th>
                            <th class="attendance-percent-col attendance-percent-sticky" data-testid="teaching-attendance-percent-header">Anwesenheit %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="student in students" :key="student.id">
                            <th class="sticky-col">
                                <div class="attendance-student-name">
                                    {{ student.last_name }}, {{ student.first_name }}
                                </div>
                                <div class="attendance-student-class">
                                    {{ student.schoolclass || student.class || '–' }}
                                </div>
                            </th>
                            <td
                                v-for="courseDate in filteredCourseDates"
                                :key="`${student.id}-${courseDate.id}`"
                                :class="attendanceCellClass(student.id, courseDate)">
                                <span v-if="attendanceState(student.id, courseDate) === 'free'" class="attendance-free-marker">E</span>
                                <v-icon v-else-if="attendanceState(student.id, courseDate) === 'present'" size="16">mdi-check</v-icon>
                                <v-icon v-else-if="attendanceState(student.id, courseDate) === 'absent'" size="16">mdi-close</v-icon>
                                <v-icon v-else size="16">mdi-minus</v-icon>
                            </td>
                            <td :class="['attendance-percent-col', 'attendance-percent-cell', 'attendance-percent-sticky', presencePercentClass(student.id)]">
                                {{ presencePercentLabel(student.id) }}
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

export default {
    props: {
        selectedCourse: {
            type: Object,
            default: null,
        },
        activeSemester: {
            type: Number,
            default: 1,
        },
        semesterCount: {
            type: Number,
            default: 1,
        },
        sem2StartDate: {
            type: String,
            default: null,
        },
    },
    computed: {
        students() {
            const list = Array.isArray(this.selectedCourse?.students_info) ? [...this.selectedCourse.students_info] : []
            return list
                .filter((student) => student?.id)
                .sort((a, b) => {
                    const lastNameCompare = String(a?.last_name || '').localeCompare(String(b?.last_name || ''), 'de', { sensitivity: 'base' })
                    if (lastNameCompare !== 0) {
                        return lastNameCompare
                    }
                    return String(a?.first_name || '').localeCompare(String(b?.first_name || ''), 'de', { sensitivity: 'base' })
                })
        },
        studentIdOrder() {
            const orderedIdsFromCourse = Array.isArray(this.selectedCourse?.students)
                ? this.selectedCourse.students
                    .map((item) => {
                        if (item && typeof item === 'object') {
                            return item.id ?? null
                        }
                        return item ?? null
                    })
                    .filter((id) => id !== null && id !== undefined)
                    .map((id) => String(id))
                : []

            if (orderedIdsFromCourse.length > 0) {
                return orderedIdsFromCourse
            }

            return this.students.map((student) => String(student.id))
        },
        studentIdSet() {
            return new Set(this.students.map((student) => String(student.id)))
        },
        filteredCourseDates() {
            const dates = Array.isArray(this.selectedCourse?.course_dates) ? [...this.selectedCourse.course_dates] : []
            const normalizedSem2Start = this.normalizeDateKey(this.sem2StartDate)
            const filtered = dates.filter((courseDate) => {
                if (this.semesterCount !== 2) {
                    return true
                }
                if (this.activeSemester === 3 || !normalizedSem2Start) {
                    return true
                }
                const dateKey = this.normalizeDateKey(courseDate?.date)
                if (!dateKey) {
                    return true
                }
                if (this.activeSemester === 1) {
                    return dateKey < normalizedSem2Start
                }
                if (this.activeSemester === 2) {
                    return dateKey >= normalizedSem2Start
                }
                return true
            })

            return filtered.sort((a, b) => String(a?.date || '').localeCompare(String(b?.date || '')))
        },
        absenceMapByDate() {
            const result = {}
            this.filteredCourseDates.forEach((courseDate) => {
                result[String(courseDate.id)] = this.buildAbsenceMap(courseDate)
            })
            return result
        },
        countedCourseDates() {
            return this.filteredCourseDates.filter((courseDate) => this.isCountedDate(courseDate))
        },
    },
    methods: {
        normalizeDateKey(date) {
            if (!date) {
                return ''
            }
            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
                return date.slice(0, 10)
            }
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) {
                return ''
            }
            const year = parsed.getFullYear()
            const month = String(parsed.getMonth() + 1).padStart(2, '0')
            const day = String(parsed.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        formatDate(date) {
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) {
                return '–'
            }
            return parsed.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
        },
        weekdayLabel(date) {
            const parsed = parseLocalDate(date)
            if (Number.isNaN(parsed.getTime())) {
                return ''
            }
            return parsed.toLocaleDateString('de-DE', { weekday: 'short' })
        },
        isPresentValue(value) {
            if (value === false || value === 0 || value === '0' || value === 'false') {
                return false
            }
            return true
        },
        normalizeIndexedAttendance(attendanceLike) {
            const input = attendanceLike && typeof attendanceLike === 'object' ? attendanceLike : {}
            const entries = Object.entries(input)
            if (!entries.length) {
                return {}
            }

            const onlySmallIndexes = entries.every(([key]) => /^\d+$/.test(String(key)) && Number(key) >= 0 && Number(key) <= 200)
            if (!onlySmallIndexes) {
                return input
            }

            if (!this.studentIdOrder.length) {
                return input
            }

            const remapped = {}
            entries.forEach(([indexRaw, value]) => {
                const studentId = this.studentIdOrder[Number(indexRaw)]
                if (!studentId) {
                    return
                }
                remapped[studentId] = value
            })
            return remapped
        },
        buildAbsenceMap(courseDate) {
            const absences = {}

            if (courseDate?.attendance && typeof courseDate.attendance === 'object') {
                const normalizedAttendance = this.normalizeIndexedAttendance(courseDate.attendance)
                Object.entries(normalizedAttendance).forEach(([rawKey, value]) => {
                    const key = String(rawKey || '').trim()
                    if (!key) {
                        return
                    }
                    const cleanKey = key.startsWith('s_') ? key.slice(2) : key
                    if (!this.studentIdSet.has(cleanKey)) {
                        return
                    }
                    if (!this.isPresentValue(value)) {
                        absences[cleanKey] = false
                    }
                })
                return absences
            }

            const statusItems = Array.isArray(courseDate?.status) ? courseDate.status : []
            statusItems.forEach((statusItem) => {
                if (typeof statusItem !== 'string' || !statusItem.startsWith('att:')) {
                    return
                }
                const parts = statusItem.split(':')
                if (parts.length < 3) {
                    return
                }
                const studentId = String(parts[1] || '').trim()
                const cleanKey = studentId.startsWith('s_') ? studentId.slice(2) : studentId
                if (!cleanKey || !this.studentIdSet.has(cleanKey)) {
                    return
                }
                if (!this.isPresentValue(String(parts[2] || '').trim())) {
                    absences[cleanKey] = false
                }
            })

            return absences
        },
        isStudentPresent(studentId, courseDateId) {
            const map = this.absenceMapByDate[String(courseDateId)] || {}
            return !Object.prototype.hasOwnProperty.call(map, String(studentId))
        },
        isFreeDate(courseDate) {
            return Array.isArray(courseDate?.status) && courseDate.status.includes('free')
        },
        isFutureDate(date) {
            const dateKey = this.normalizeDateKey(date)
            const todayKey = this.normalizeDateKey(new Date())
            if (!dateKey || !todayKey) {
                return false
            }
            return dateKey > todayKey
        },
        isCountedDate(courseDate) {
            if (this.isFreeDate(courseDate)) {
                return false
            }
            if (this.isFutureDate(courseDate?.date)) {
                return false
            }
            return true
        },
        attendanceState(studentId, courseDate) {
            if (this.isFreeDate(courseDate)) {
                return 'free'
            }
            if (this.isFutureDate(courseDate?.date)) {
                return 'future'
            }
            return this.isStudentPresent(studentId, courseDate?.id) ? 'present' : 'absent'
        },
        presencePercentLabel(studentId) {
            const percentage = this.presencePercentValue(studentId)
            if (percentage === null) {
                return '–'
            }
            return `${percentage}%`
        },
        presencePercentValue(studentId) {
            if (!this.countedCourseDates.length) {
                return null
            }
            const presentDays = this.countedCourseDates.reduce((count, courseDate) => {
                return count + (this.isStudentPresent(studentId, courseDate.id) ? 1 : 0)
            }, 0)
            return Math.round((presentDays / this.countedCourseDates.length) * 100)
        },
        presencePercentClass(studentId) {
            const percentage = this.presencePercentValue(studentId)
            if (percentage === null) {
                return 'attendance-percent--neutral'
            }
            if (percentage >= 90) {
                return 'attendance-percent--green'
            }
            if (percentage >= 70) {
                return 'attendance-percent--yellow'
            }
            if (percentage >= 50) {
                return 'attendance-percent--orange'
            }
            return 'attendance-percent--red'
        },
        attendanceCellClass(studentId, courseDate) {
            const state = this.attendanceState(studentId, courseDate)
            if (state === 'present') {
                return 'attendance-cell attendance-cell--present'
            }
            if (state === 'absent') {
                return 'attendance-cell attendance-cell--absent'
            }
            if (state === 'free') {
                return 'attendance-cell attendance-cell--free'
            }
            return 'attendance-cell attendance-cell--future'
        },
    },
}
</script>

<style scoped>
.attendance-matrix-wrap {
    overflow-x: auto;
}

.attendance-matrix-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 760px;
}

.attendance-matrix-table th,
.attendance-matrix-table td {
    border: 1px solid rgba(16, 38, 58, 0.1);
    text-align: center;
    padding: 6px 8px;
}

.attendance-matrix-table th {
    background: rgba(15, 23, 42, 0.06);
    font-weight: 600;
}

.sticky-col {
    position: sticky;
    left: 0;
    z-index: 1;
    text-align: left !important;
    width: 200px;
    min-width: 200px;
    max-width: 200px;
    background: #fff;
}

.attendance-date-head {
    line-height: 1.2;
}

.attendance-date-weekday {
    font-size: 0.72rem;
    color: rgba(16, 38, 58, 0.7);
}

.attendance-student-name {
    font-size: 0.86rem;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.attendance-student-class {
    font-size: 0.72rem;
    color: rgba(16, 38, 58, 0.7);
}

.attendance-cell {
    width: 54px;
    min-width: 54px;
}

.attendance-percent-col {
    width: 110px;
    min-width: 110px;
    max-width: 110px;
}

.attendance-percent-cell {
    font-weight: 600;
}

.attendance-percent-sticky {
    position: sticky;
    right: 0;
    z-index: 2;
}

thead .attendance-percent-sticky {
    z-index: 4;
    background: rgba(15, 23, 42, 0.06);
}

.attendance-percent--neutral {
    background: rgba(148, 163, 184, 0.16);
    color: #475569;
}

.attendance-percent--green {
    background: rgba(67, 160, 71, 0.24);
    color: #1b5e20;
}

.attendance-percent--yellow {
    background: rgba(253, 216, 53, 0.28);
    color: #795548;
}

.attendance-percent--orange {
    background: rgba(255, 167, 38, 0.28);
    color: #e65100;
}

.attendance-percent--red {
    background: rgba(229, 57, 53, 0.22);
    color: #b71c1c;
}

.attendance-cell--present {
    background: rgba(67, 160, 71, 0.2);
    color: #1b5e20;
}

.attendance-cell--absent {
    background: rgba(229, 57, 53, 0.22);
    color: #b71c1c;
}

.attendance-cell--future {
    background: rgba(148, 163, 184, 0.18);
    color: #475569;
}

.attendance-cell--free {
    background: rgba(67, 160, 71, 0.28);
    color: #1b5e20;
}

.attendance-free-marker {
    font-weight: 400;
    font-size: 0.86rem;
}
</style>
