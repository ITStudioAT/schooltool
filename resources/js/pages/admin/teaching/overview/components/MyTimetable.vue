<template>
    <ItsGridBox color="primary" title="Stundenplan" icon="mdi-calendar-clock" class="w-100" :disabled="action != ''">
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-btn-toggle v-model="range" mandatory density="compact" color="primary" class="w-100" @update:model-value="resetOffset">
                    <v-btn :value="RANGE_TODAY" size="small">Heute</v-btn>
                    <v-btn :value="RANGE_WEEK" size="small">Diese Woche</v-btn>
                    <v-btn :value="RANGE_TWO_WEEKS" size="small">Zwei Wochen</v-btn>
                    <v-btn :value="RANGE_MONTH" size="small">Dieser Monat</v-btn>
                    <v-btn :value="RANGE_CURRENT_SEMESTER" size="small">{{ currentSemesterButtonLabel }}</v-btn>
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

                <v-card variant="outlined" class="mt-2">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                        <v-icon size="18">mdi-format-list-bulleted</v-icon>
                        Unterricht
                        <v-chip size="x-small" color="primary" variant="tonal">{{ filteredItems.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="item in filteredItems" :key="item.key" class="cursor-pointer pa-0" @click="openCourse(item)">
                                <div :class="['d-flex flex-column ga-2 w-100 pa-3', getStatusClass(item)]">
                                    <div class="d-flex flex-wrap align-center ga-2 w-100">
                                        <v-chip size="x-small" variant="tonal" color="primary">{{ formatWeekdayDate(item.date) }}</v-chip>
                                        <v-chip size="x-small" variant="outlined" color="primary">{{ item.hoursLabel }}</v-chip>
                                        <v-chip size="x-small" variant="outlined">{{ item.classLabel }}</v-chip>
                                        <v-chip size="x-small" variant="tonal" color="primary" class="chip-truncate">{{ item.courseTitle }}</v-chip>
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
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

const RANGE_TODAY = 'today'
const RANGE_WEEK = 'week'
const RANGE_TWO_WEEKS = 'two_weeks'
const RANGE_MONTH = 'month'
const RANGE_CURRENT_SEMESTER = 'current_semester'

export default {
    components: { ItsGridBox },

    data() {
        return {
            RANGE_TODAY,
            RANGE_WEEK,
            RANGE_TWO_WEEKS,
            RANGE_MONTH,
            RANGE_CURRENT_SEMESTER,
            range: RANGE_WEEK,
            offset: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, ['courses', 'selected_course', 'selected_course_id', 'selected_course_student', 'show_infos']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
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

                        const status = Array.isArray(courseDate?.status) ? courseDate.status : []

                        return {
                            key: `${course?.id || 'x'}-${courseDate?.id || date}-${hoursLabel}`,
                            courseId: course?.id || null,
                            courseDateId: courseDate?.id || null,
                            date,
                            dateObj,
                            hours,
                            hoursLabel,
                            classLabel: classLabel || '-',
                            courseTitle: courseTitle || '-',
                            content: (courseDate?.content || '').toString().trim(),
                            status,
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
            if (this.range === RANGE_TWO_WEEKS) {
                // Shift by 2-week periods (14 days)
                referenceDate.setDate(today.getDate() + (this.offset * 14))
                const start = this.startOfWeek(referenceDate)
                const end = new Date(start)
                end.setDate(start.getDate() + 13)
                return [start, end]
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
            if (!dateId) {
                this.selected_courseDate = null
                return
            }
            const date = (course?.course_dates || []).find((d) => d?.id === dateId) || null
            this.selected_courseDate = date
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
            const status = item?.status || []
            const statusStr = status.join(' ').toLowerCase()
            if (statusStr.includes('pruefung') || statusStr.includes('prüfung')) {
                return 'timetable-item--exam'
            }
            if (statusStr.includes('frei') || statusStr.includes('free')) {
                return 'timetable-item--free'
            }
            return ''
        },
    },
}
</script>

<style scoped>
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
}
</style>
