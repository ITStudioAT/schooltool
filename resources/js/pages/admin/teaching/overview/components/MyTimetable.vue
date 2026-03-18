<template>
    <ItsGridBox variant="overview" color="primary" title="Stundenplan" icon="mdi-calendar-clock" class="w-100" :disabled="action != ''">
        <template #header-actions>
            <v-btn-toggle v-if="tableViewAllowed" v-model="timetable_view_mode" mandatory color="primary" density="compact" class="timetable-view-toggle">
                <v-btn value="list" size="small" title="Listenansicht"><v-icon size="18">mdi-format-list-bulleted</v-icon></v-btn>
                <v-btn value="table" size="small" title="Tabellenansicht"><v-icon size="18">mdi-table</v-icon></v-btn>
            </v-btn-toggle>
            <v-btn icon="mdi-eye-off-outline" size="small" variant="tonal" title="Ausblenden" @click="show_timetable = false" />
        </template>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-btn-toggle v-model="range" mandatory color="primary" class="w-100 timetable-range-toggle" @update:model-value="resetOffset">
                    <v-btn :value="RANGE_TODAY">Heute</v-btn>
                    <v-btn :value="RANGE_WEEK">Diese Woche</v-btn>
                    <v-btn :value="RANGE_NEXT_WEEK">Nächste Woche</v-btn>
                    <v-btn :value="RANGE_MONTH">Dieser Monat</v-btn>
                    <v-btn :value="RANGE_CURRENT_SEMESTER">{{ currentSemesterButtonLabel }}</v-btn>
                </v-btn-toggle>

                <!-- Navigation -->
                <div class="d-flex align-center ga-2">
                    <v-btn
                        icon="mdi-chevron-left"
                        size="small"
                        variant="tonal"
                        :disabled="!canNavigatePrevious"
                        @click="navigatePrevious"
                    />
                    <div class="flex-grow-1 text-center text-caption">
                        <span v-if="dateRangeLabel">{{ dateRangeLabel }}</span>
                    </div>
                    <v-btn
                        icon="mdi-chevron-right"
                        size="small"
                        variant="tonal"
                        :disabled="!canNavigateNext"
                        @click="navigateNext"
                    />
                </div>

                <!-- List View -->
                <v-card v-if="activeViewMode === 'list'" variant="outlined" class="mt-2">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                        <v-icon size="18">mdi-format-list-bulleted</v-icon>
                        Unterricht
                        <v-chip size="x-small" color="primary" variant="tonal">{{ filteredItems.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="item in filteredItems" :key="item.key" class="cursor-pointer pa-0" @click="openCourse(item)">
                                <div :class="['d-flex flex-column ga-2 w-100 pa-3', getStatusClass(item), { 'timetable-item--today': isToday(item) }]" :style="getDateBackgroundStyle(item)">
                                    <div class="d-flex flex-wrap align-center ga-2 w-100">
                                        <v-chip v-if="isToday(item)" size="x-small" color="warning" variant="flat">Heute</v-chip>
                                        <v-icon
                                            v-if="isAttendanceChecked(item)"
                                            size="16"
                                            color="success"
                                            title="Anwesenheit geprüft">
                                            mdi-check-circle
                                        </v-icon>
                                        <v-chip v-if="!isToday(item)" size="x-small" variant="tonal" color="primary">{{ formatWeekdayDate(item.date) }}</v-chip>
                                        <v-chip size="x-small" variant="outlined" color="primary">{{ item.hoursLabel }}</v-chip>
                                        <v-chip size="x-small" variant="outlined" color="primary">{{ item.timeRangeLabel }}</v-chip>
                                        <v-chip size="x-small" variant="outlined">{{ item.classLabel }}</v-chip>
                                        <v-chip size="x-small" variant="tonal" color="primary" class="chip-truncate">{{ item.courseTitle }}</v-chip>
                                        <v-chip
                                            v-if="hasFreeStatus(item) && item.freeReason"
                                            size="x-small"
                                            variant="outlined"
                                            color="success"
                                        >
                                            {{ item.freeReason }}
                                        </v-chip>
                                    </div>
                                    <div v-if="item.content" class="text-caption timetable-content" v-html="contentHtml(item.content)"></div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!filteredItems.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Termine im gewaehlten Zeitraum.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <!-- Table View -->
                <v-card v-else variant="outlined" class="mt-2">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                        <v-icon size="18">mdi-table</v-icon>
                        Stundenplan
                        <v-chip size="x-small" color="primary" variant="tonal">{{ filteredItems.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-1">
                        <div class="timetable-table-wrapper">
                            <table class="timetable-grid-table">
                                <thead>
                                    <tr>
                                        <th class="timetable-hour-header-cell"></th>
                                        <th
                                            v-for="day in tableWeekDays"
                                            :key="normalizeDateToString(day)"
                                            :class="['timetable-day-header-cell', { 'day-today': isDayToday(day) }]">
                                            <div>{{ formatDayOfWeek(day) }}</div>
                                            <div class="timetable-day-date">{{ formatDayDate(day) }}</div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="hour in tableHours" :key="hour">
                                        <td class="timetable-hour-cell">
                                            <div class="timetable-hour-num">{{ hour }}.</div>
                                            <div v-if="schoolHoursByHour[hour]" class="timetable-hour-time">
                                                {{ formatTimeValue(schoolHoursByHour[hour]?.from) }}<br>{{ formatTimeValue(schoolHoursByHour[hour]?.until) }}
                                            </div>
                                        </td>
                                        <td v-for="day in tableWeekDays" :key="normalizeDateToString(day)" class="timetable-grid-cell">
                                            <div
                                                v-for="item in getTableCellItems(day, hour)"
                                                :key="item.key"
                                                :class="['timetable-grid-item', getStatusClass(item), { 'timetable-item--today': isToday(item) }]"
                                                @click="openCourse(item)">
                                                <div class="timetable-grid-course">{{ item.courseTitle }}</div>
                                                <div class="timetable-grid-class">{{ item.classLabel }}</div>
                                                <div v-if="range === RANGE_TODAY && item.content" class="timetable-grid-content" v-html="contentHtml(item.content)"></div>
                                                <v-icon v-if="isAttendanceChecked(item)" size="12" color="success">mdi-check-circle</v-icon>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!tableHours.length">
                                        <td colspan="99" class="text-caption text-medium-emphasis pa-3">Keine Termine im gewählten Zeitraum.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

const RANGE_TODAY = 'today'
const RANGE_WEEK = 'week'
const RANGE_NEXT_WEEK = 'next_week'
const RANGE_MONTH = 'month'
const RANGE_CURRENT_SEMESTER = 'current_semester'

export default {
    components: { ItsGridBox },

    async beforeMount() {
        this.schoolHourStore = useSchoolHourStore()
        if (!Array.isArray(this.school_hours) || this.school_hours.length === 0) {
            await this.schoolHourStore.index()
        }
    },

    data() {
        return {
            RANGE_TODAY,
            RANGE_WEEK,
            RANGE_NEXT_WEEK,
            RANGE_MONTH,
            RANGE_CURRENT_SEMESTER,
            range: RANGE_WEEK,
            offset: 0,
            schoolHourStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'selected_course', 'selected_course_id', 'selected_course_student', 'show_infos', 'show_timetable', 'timetable_view_mode']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useSchoolHourStore, ['school_hours']),
        schoolHoursByHour() {
            const entries = Array.isArray(this.school_hours) ? this.school_hours : []

            return entries.reduce((carry, item) => {
                const hour = Number(item?.hour)
                if (!Number.isFinite(hour)) {
                    return carry
                }

                carry[hour] = item
                return carry
            }, {})
        },
        myCourses() {
            const userId = this.config?.user?.id
            const list = Array.isArray(this.courses) ? this.courses : []
            if (!userId) return []
            return list.filter((course) => course?.user_id === userId)
        },
        timetableItems() {
            return (this.myCourses || [])
                .flatMap((course) => {
                    const classLabel = Array.isArray(course?.classes) ? course.classes.join(', ') : ''
                    const courseTitle = (course?.title || '').toString().trim()
                    const dates = Array.isArray(course?.course_dates) ? course.course_dates : []

                    return dates.map((courseDate) => {
                        const date = (courseDate?.date || '').toString().slice(0, 10)
                        const dateObj = parseLocalDate(date)
                        const hoursRaw = Array.isArray(courseDate?.hours) ? courseDate.hours : []
                        const hours = [...hoursRaw]
                            .map((h) => Number(h))
                            .filter((h) => Number.isFinite(h))
                            .sort((a, b) => a - b)
                        const hoursLabel = hours.length ? hours.map((h) => `${h}. Std`).join(', ') : '-'
                        const timeRangeLabel = this.formatHoursTimeRange(hours)

                        const status = Array.isArray(courseDate?.status) ? courseDate.status : []

                        return {
                            key: `${course?.id || 'x'}-${courseDate?.id || date}-${hoursLabel}`,
                            courseId: course?.id || null,
                            courseDateId: courseDate?.id || null,
                            date,
                            dateObj,
                            hours,
                            hoursLabel,
                            timeRangeLabel,
                            classLabel: classLabel || '-',
                            courseTitle: courseTitle || '-',
                            content: (courseDate?.content || '').toString().trim(),
                            freeReason: (courseDate?.free_reason || '').toString().trim(),
                            status,
                            attendanceChecked: typeof courseDate?.attendance_checked === 'boolean'
                                ? courseDate.attendance_checked
                                : status.includes('att_checked:1'),
                        }
                    })
                })
                .filter((item) => !isNaN(item.dateObj.getTime()))
                .sort((a, b) => {
                    const dateCmp = a.date.localeCompare(b.date)
                    if (dateCmp !== 0) return dateCmp
                    const hourA = a.hours[0] ?? 999
                    const hourB = b.hours[0] ?? 999
                    if (hourA !== hourB) return hourA - hourB
                    return a.courseTitle.localeCompare(b.courseTitle, 'de', { sensitivity: 'base' })
                })
        },
        filteredItems() {
            const [from, until] = this.currentRangeBounds()
            if (!from || !until) return this.timetableItems
            return this.timetableItems.filter((item) => item.dateObj >= from && item.dateObj <= until)
        },
        dateBackgroundByDate() {
            const accentColor = '#e3f2fd'
            const mapping = {}
            let dateIndex = 0

            for (const item of this.filteredItems) {
                const date = (item?.date || '').toString().slice(0, 10)
                if (!date || mapping[date]) continue
                if (dateIndex % 2 === 0) {
                    mapping[date] = '#ffffff'
                } else {
                    mapping[date] = accentColor
                }
                dateIndex++
            }

            return mapping
        },
        semesterMeta() {
            const schoolyear = this.config?.selected_schoolyear || {}
            const normalizeConfiguredDate = (value) => {
                if (!value) return null
                const parsed = this.normalizeDay(parseLocalDate(value))
                if (isNaN(parsed.getTime())) return null
                return parsed
            }

            const today = this.normalizeDay(new Date())
            const schoolFrom = normalizeConfiguredDate(schoolyear?.from)
            const schoolUntil = normalizeConfiguredDate(schoolyear?.until)
            const sem2Start = normalizeConfiguredDate(
                this.config?.user?.teaching_count_for_semester_2_date || schoolyear?.sem_2_start
            )
            const baseSemester = sem2Start && today >= sem2Start ? 2 : 1

            return { today, schoolFrom, schoolUntil, sem2Start, baseSemester }
        },
        selectedSemesterNumber() {
            const base = this.semesterMeta.baseSemester
            const sem2Exists = !!this.semesterMeta.sem2Start
            if (!sem2Exists) return 1

            const delta = this.range === RANGE_CURRENT_SEMESTER ? this.offset : 0
            const target = base + delta
            return Math.min(2, Math.max(1, target))
        },
        currentSemesterButtonLabel() {
            return `${this.selectedSemesterNumber}. Semester`
        },
        dateRangeLabel() {
            const [from, until] = this.currentRangeBounds()
            if (!from || !until) return ''
            const formatDate = (d) => d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
            if (from.getTime() === until.getTime()) {
                return formatDate(from)
            }
            return `${formatDate(from)} - ${formatDate(until)}`
        },
        canNavigatePrevious() {
            if (this.range === RANGE_CURRENT_SEMESTER) {
                if (!this.semesterMeta.sem2Start) return false
                return this.selectedSemesterNumber > 1
            }
            if (!this.timetableItems.length) return false
            const [from] = this.currentRangeBounds()
            if (!from) return false
            const earliestDate = this.timetableItems[0]?.dateObj
            if (!earliestDate) return false
            return earliestDate < from
        },
        canNavigateNext() {
            if (this.range === RANGE_CURRENT_SEMESTER) {
                if (!this.semesterMeta.sem2Start) return false
                return this.selectedSemesterNumber < 2
            }
            if (!this.timetableItems.length) return false
            const [, until] = this.currentRangeBounds()
            if (!until) return false
            const latestDate = this.timetableItems[this.timetableItems.length - 1]?.dateObj
            if (!latestDate) return false
            return latestDate > until
        },
        tableViewAllowed() {
            return this.range === RANGE_TODAY || this.range === RANGE_WEEK || this.range === RANGE_NEXT_WEEK
        },
        activeViewMode() {
            return this.tableViewAllowed ? this.timetable_view_mode : 'list'
        },
        tableWeekDays() {
            const [from, until] = this.currentRangeBounds()
            if (!from || !until) return []
            const days = []
            const cur = new Date(from)
            while (cur <= until) {
                const dayOfWeek = cur.getDay()
                if (dayOfWeek >= 1 && dayOfWeek <= 5) {
                    days.push(new Date(cur))
                }
                cur.setDate(cur.getDate() + 1)
            }
            return days
        },
        tableHours() {
            const schoolHourList = Array.isArray(this.school_hours) ? this.school_hours : []
            const itemHours = this.filteredItems.flatMap((i) => i.hours)
            if (!itemHours.length && !schoolHourList.length) return []
            if (!itemHours.length) {
                return schoolHourList.map((sh) => Number(sh.hour)).sort((a, b) => a - b)
            }
            const minHour = Math.min(...itemHours)
            const maxHour = Math.max(...itemHours)
            if (schoolHourList.length > 0) {
                return schoolHourList
                    .map((sh) => Number(sh.hour))
                    .filter((h) => h >= minHour && h <= maxHour)
                    .sort((a, b) => a - b)
            }
            const hours = []
            for (let h = minHour; h <= maxHour; h++) {
                hours.push(h)
            }
            return hours
        },
        tableCellItems() {
            const map = {}
            this.filteredItems.forEach((item) => {
                item.hours.forEach((h) => {
                    const key = `${item.date}-${h}`
                    if (!map[key]) map[key] = []
                    map[key].push(item)
                })
            })
            return map
        },
    },

    methods: {
        normalizeDay(date) {
            const d = new Date(date)
            d.setHours(0, 0, 0, 0)
            return d
        },
        startOfWeek(date) {
            const d = this.normalizeDay(date)
            const day = d.getDay()
            const diff = day === 0 ? -6 : 1 - day
            d.setDate(d.getDate() + diff)
            return d
        },
        endOfWeek(date) {
            const start = this.startOfWeek(date)
            const end = new Date(start)
            end.setDate(start.getDate() + 6)
            return end
        },
        currentRangeBounds() {
            const today = this.normalizeDay(new Date())
            let referenceDate = new Date(today)

            if (this.range === RANGE_TODAY) {
                // Shift by days
                referenceDate.setDate(today.getDate() + this.offset)
                return [referenceDate, referenceDate]
            }
            if (this.range === RANGE_WEEK) {
                // Shift by weeks (7 days)
                referenceDate.setDate(today.getDate() + (this.offset * 7))
                return [this.startOfWeek(referenceDate), this.endOfWeek(referenceDate)]
            }
            if (this.range === RANGE_NEXT_WEEK) {
                // Base is next week; shift further by offset weeks
                referenceDate.setDate(today.getDate() + 7 + (this.offset * 7))
                return [this.startOfWeek(referenceDate), this.endOfWeek(referenceDate)]
            }
            if (this.range === RANGE_MONTH) {
                // Shift by months
                referenceDate.setMonth(today.getMonth() + this.offset)
                const start = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), 1)
                const end = new Date(referenceDate.getFullYear(), referenceDate.getMonth() + 1, 0)
                end.setHours(0, 0, 0, 0)
                return [start, end]
            }
            if (this.range === RANGE_CURRENT_SEMESTER) {
                return this.currentSemesterBounds(this.selectedSemesterNumber)
            }
            return [null, null]
        },
        currentSemesterBounds(semesterNumber) {
            const { schoolFrom, schoolUntil, sem2Start } = this.semesterMeta
            if (sem2Start) {
                if (semesterNumber === 1) {
                    const start = schoolFrom || new Date(sem2Start.getFullYear(), 0, 1)
                    const end = new Date(sem2Start)
                    end.setDate(end.getDate() - 1)
                    return [start, end]
                }
                const start = new Date(sem2Start)
                const end = schoolUntil || new Date(sem2Start.getFullYear(), 11, 31)
                return [start, end]
            }

            if (schoolFrom && schoolUntil) return [schoolFrom, schoolUntil]
            return [null, null]
        },
        formatDate(date) {
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatWeekdayDate(date) {
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatHoursTimeRange(hours) {
            const sortedHours = Array.isArray(hours)
                ? [...hours]
                    .map((hour) => Number(hour))
                    .filter((hour) => Number.isFinite(hour))
                    .sort((a, b) => a - b)
                : []
            if (!sortedHours.length) return '-'

            const firstHourConfig = this.schoolHoursByHour[sortedHours[0]]
            const lastHourConfig = this.schoolHoursByHour[sortedHours[sortedHours.length - 1]]
            const from = this.formatTimeValue(firstHourConfig?.from)
            const until = this.formatTimeValue(lastHourConfig?.until)
            if (!from || !until) return '-'

            return `${from} - ${until}`
        },
        formatTimeValue(value) {
            const raw = (value || '').toString().trim()
            if (!raw) return ''
            return raw.slice(0, 5)
        },
        contentHtml(text) {
            if (!text) return ''
            if (text.includes('<p>') || text.includes('<br')) return text
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
        openCourse(item) {
            const courseId = item?.courseId
            if (!courseId) return
            const course = (this.courses || []).find((c) => c?.id === courseId)
            if (!course) return

            const courseStore = useCourseStore()
            courseStore.ensureCourseStudentCollections(course)

            this.selected_course = course
            this.selected_course_id = course.id
            this.selected_course_student = null
            this.action_2 = ''
            this.show_infos = true

            const dateId = item?.courseDateId
            const date = dateId ? (course?.course_dates || []).find((d) => d?.id === dateId) || null : null
            this.selected_courseDate = date

            const query = { course: String(course.id) }
            if (date?.id) query.date = String(date.id)
            this.$router.replace({ query }).catch(() => {})
        },
        normalizeDateToString(date) {
            const y = date.getFullYear()
            const m = String(date.getMonth() + 1).padStart(2, '0')
            const d = String(date.getDate()).padStart(2, '0')
            return `${y}-${m}-${d}`
        },
        isDayToday(day) {
            return this.normalizeDay(day).getTime() === this.normalizeDay(new Date()).getTime()
        },
        formatDayOfWeek(day) {
            return day.toLocaleDateString('de-DE', { weekday: 'short' })
        },
        formatDayDate(day) {
            return day.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
        },
        getTableCellItems(day, hour) {
            const key = `${this.normalizeDateToString(day)}-${hour}`
            return this.tableCellItems[key] || []
        },
        navigatePrevious() {
            if (this.range === RANGE_CURRENT_SEMESTER && !this.canNavigatePrevious) return
            this.offset--
        },
        navigateNext() {
            if (this.range === RANGE_CURRENT_SEMESTER && !this.canNavigateNext) return
            this.offset++
        },
        resetOffset() {
            this.offset = 0
        },
        getStatusClass(item) {
            if (this.hasExamStatus(item)) {
                return 'timetable-item--exam'
            }
            if (this.hasFreeStatus(item)) {
                return 'timetable-item--free'
            }
            return ''
        },
        getDateBackgroundStyle(item) {
            if (this.hasExamStatus(item) || this.hasFreeStatus(item)) return {}
            const date = (item?.date || '').toString().slice(0, 10)
            if (!date) return {}
            const backgroundColor = this.dateBackgroundByDate[date]
            if (!backgroundColor) return {}
            return { backgroundColor }
        },
        hasExamStatus(item) {
            const status = Array.isArray(item?.status) ? item.status : []
            const statusStr = status.join(' ').toLowerCase()
            return statusStr.includes('pruefung') || statusStr.includes('prüfung')
        },
        hasFreeStatus(item) {
            const status = Array.isArray(item?.status) ? item.status : []
            const statusStr = status.join(' ').toLowerCase()
            return statusStr.includes('frei')
                || statusStr.includes('free')
                || statusStr.includes('entfaellt')
                || statusStr.includes('entfällt')
                || statusStr.includes('entfallen')
        },
        isToday(item) {
            const today = this.normalizeDay(new Date())
            return item.dateObj.getTime() === today.getTime()
        },
        isAttendanceChecked(item) {
            if (!item) return false
            if (typeof item.attendanceChecked === 'boolean') return item.attendanceChecked
            const status = Array.isArray(item?.status) ? item.status : []
            return status.includes('att_checked:1')
        },
    },
}
</script>

<style scoped>
.timetable-range-toggle {
    flex-wrap: wrap;
    row-gap: 4px;
    height: auto !important;
}

.timetable-range-toggle :deep(.v-btn) {
    height: 52px !important;
}

.chip-truncate {
    max-width: 100%;
}

.chip-truncate :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timetable-content :deep(p) {
    margin: 0;
    min-height: 1.2em;
}

.timetable-item--exam {
    background-color: #ffebee !important;
    border-left: 4px solid #ff5722;
}

.timetable-item--free {
    background-color: #c8e6c9 !important;
    border-left: 4px solid #4caf50;
    position: relative;
    overflow: hidden;
}

.timetable-item--free::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to top right,
        transparent calc(50% - 0.6px),
        rgba(0, 0, 0, 0.22) calc(50% - 0.6px),
        rgba(0, 0, 0, 0.22) calc(50% + 0.6px),
        transparent calc(50% + 0.6px)
    );
    pointer-events: none;
}

.timetable-item--today {
    border-left: 4px solid #ff9800 !important;
}

.timetable-view-toggle {
    height: 32px;
}

.timetable-table-wrapper {
    overflow-x: auto;
}

.timetable-grid-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 300px;
    font-size: 0.8rem;
}

.timetable-grid-table th,
.timetable-grid-table td {
    border: 1px solid rgba(0, 0, 0, 0.1);
    padding: 0;
    vertical-align: top;
}

.timetable-hour-header-cell {
    width: 44px;
    min-width: 44px;
    background-color: #f5f5f5;
}

.timetable-day-header-cell {
    text-align: center;
    min-width: 90px;
    background-color: #f5f5f5;
    font-weight: 600;
    padding: 6px 4px;
}

.timetable-day-header-cell.day-today {
    background-color: #fff3e0;
    color: #e65100;
}

.timetable-day-date {
    font-size: 0.75rem;
    font-weight: 400;
    opacity: 0.7;
}

.timetable-hour-cell {
    text-align: center;
    background-color: #f5f5f5;
    padding: 6px 4px;
    white-space: nowrap;
    min-width: 44px;
}

.timetable-hour-num {
    font-weight: 600;
    font-size: 0.8rem;
}

.timetable-hour-time {
    font-size: 0.7rem;
    opacity: 0.65;
}

.timetable-grid-cell {
    min-width: 90px;
    height: 52px;
    padding: 2px;
}

.timetable-grid-item {
    padding: 4px 5px;
    border-radius: 3px;
    background-color: #e3f2fd;
    border-left: 3px solid #1976d2;
    margin-bottom: 2px;
    cursor: pointer;
    transition: opacity 0.15s;
}

.timetable-grid-item:hover {
    opacity: 0.8;
}

.timetable-grid-course {
    font-weight: 600;
    font-size: 0.75rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 120px;
}

.timetable-grid-class {
    font-size: 0.7rem;
    opacity: 0.7;
}

.timetable-grid-content {
    font-size: 0.7rem;
    margin-top: 2px;
    opacity: 0.85;
    white-space: normal;
}

.timetable-grid-content :deep(p) {
    margin: 0;
    min-height: 1em;
}

.timetable-grid-item.timetable-item--exam {
    background-color: #ffebee;
    border-left-color: #ff5722;
}

.timetable-grid-item.timetable-item--free {
    background-color: #c8e6c9;
    border-left-color: #4caf50;
}

.timetable-grid-item.timetable-item--today {
    border-left-color: #ff9800;
}

</style>
