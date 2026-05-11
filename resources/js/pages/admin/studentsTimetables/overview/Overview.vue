<template>
    <v-col cols="12" xl="10">
        <v-card rounded="lg" border>
            <v-card-title class="d-flex flex-wrap align-center ga-2">
                <v-icon icon="mdi-calendar-clock" />
                Stundenplan
                <v-chip v-if="schoolyearName" size="small" variant="tonal" color="light-blue">
                    {{ schoolyearName }}
                </v-chip>
                <v-spacer />
                <v-switch
                    v-model="showSaturday"
                    color="primary"
                    density="compact"
                    hide-details
                    inset
                    label="Sa"
                    class="timetable-saturday-switch" />
            </v-card-title>
            <v-card-text>
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3" />

                <v-alert v-if="!loading && !configuredSchoolHours.length" type="info" variant="tonal" class="mb-3">
                    Es sind noch keine Schulstunden hinterlegt. Die Übersicht zeigt vorläufig 10 Stunden.
                </v-alert>

                <div class="semester-grid">
                    <section v-for="semester in semesters" :key="semester.value" class="semester-section">
                        <div class="semester-section__header">
                            <div class="d-flex flex-wrap align-center ga-2">
                                <v-icon icon="mdi-calendar-range" size="18" color="primary" />
                                <span class="text-subtitle-2 font-weight-bold">{{ semester.label }}</span>
                                <span class="semester-section__dates">{{ semester.dateRangeLabel }}</span>
                            </div>
                            <v-chip size="x-small" color="primary" variant="tonal">{{ weekdayRangeLabel }}</v-chip>
                        </div>

                        <div class="timetable-table-wrapper">
                            <table class="timetable-grid-table">
                                <thead>
                                    <tr>
                                        <th class="timetable-hour-header-cell"></th>
                                        <th v-for="weekday in displayedWeekdays" :key="`${semester.value}-${weekday.key}`" class="timetable-day-header-cell">
                                            <div>{{ weekday.label }}</div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="hour in timetableHoursForSemester(semester.value)" :key="`${semester.value}-${hour.hour}`">
                                        <td class="timetable-hour-cell">
                                            <div class="timetable-hour-num">{{ hour.hour }}.</div>
                                            <div v-if="hour.from || hour.until" class="timetable-hour-time">
                                                {{ hour.from }}<br>{{ hour.until }}
                                            </div>
                                        </td>
                                        <td
                                            v-for="weekday in displayedWeekdays"
                                            :key="`${semester.value}-${weekday.key}-${hour.hour}`"
                                            class="timetable-grid-cell">
                                            <div
                                                v-for="courseGroup in courseGroupsForCell(semester.value, weekday.value, hour.hour)"
                                                :key="courseGroup.key"
                                                class="timetable-course-item"
                                                role="button"
                                                tabindex="0"
                                                @click="openCourseGroupDialog(courseGroup)"
                                                @keydown.enter="openCourseGroupDialog(courseGroup)">
                                                <span class="timetable-course-title">{{ courseGroup.display_label || courseGroup.title }}</span>
                                                <span v-if="courseGroup.recurrence_label || courseGroup.is_block" class="timetable-course-markers">
                                                    <v-chip
                                                        v-if="courseGroup.recurrence_label"
                                                        size="x-small"
                                                        color="primary"
                                                        variant="tonal">
                                                        {{ courseGroup.recurrence_label }}
                                                    </v-chip>
                                                    <v-chip
                                                        v-if="courseGroup.is_block"
                                                        size="x-small"
                                                        color="warning"
                                                        variant="tonal">
                                                        {{ courseGroup.block_label }}
                                                    </v-chip>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!timetableHoursForSemester(semester.value).length">
                                        <td :colspan="displayedWeekdays.length + 1" class="timetable-empty-cell">
                                            Keine Einträge in den sichtbaren Wochentagen.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </v-card-text>
        </v-card>

        <v-dialog v-model="courseGroupDialog" persistent max-width="520">
            <v-card>
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-calendar-multiselect" color="primary" />
                    {{ selectedCourseGroupLabel }}
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" size="small" @click="closeCourseGroupDialog" />
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="d-flex flex-wrap ga-2 mb-3">
                        <v-chip v-if="selectedCourseGroup?.recurrence_label" size="small" color="primary" variant="tonal">
                            {{ selectedCourseGroup.recurrence_label }}
                        </v-chip>
                        <v-chip v-if="selectedCourseGroup?.is_block" size="small" color="warning" variant="tonal">
                            {{ selectedCourseGroup.block_label }}
                        </v-chip>
                        <v-chip v-if="selectedCourseGroup?.dates_count" size="small" variant="outlined">
                            {{ selectedCourseGroup.dates_count }} Termine
                        </v-chip>
                    </div>

                    <div class="course-date-list">
                        <v-chip
                            v-for="date in selectedCourseGroupDates"
                            :key="date"
                            size="small"
                            variant="tonal"
                            color="secondary">
                            {{ formatDateValue(date) }}
                        </v-chip>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="closeCourseGroupDialog">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

const FALLBACK_HOUR_COUNT = 10

export default {
    name: 'StudentsTimetablesOverview',

    data() {
        return {
            loading: false,
            schoolHours: [],
            courseGroups: [],
            courseGroupDialog: false,
            selectedCourseGroup: null,
            showSaturday: false,
            weekdays: [
                { key: 'mo', label: 'Mo', value: 1 },
                { key: 'tu', label: 'Di', value: 2 },
                { key: 'we', label: 'Mi', value: 3 },
                { key: 'th', label: 'Do', value: 4 },
                { key: 'fr', label: 'Fr', value: 5 },
                { key: 'sa', label: 'Sa', value: 6 },
            ],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolyear() {
            return this.config?.selected_schoolyear || {}
        },
        schoolyearName() {
            return this.selectedSchoolyear?.name || ''
        },
        semesters() {
            return [
                {
                    value: 1,
                    label: 'Semester 1',
                    dateRangeLabel: this.semesterDateRange(1),
                },
                {
                    value: 2,
                    label: 'Semester 2',
                    dateRangeLabel: this.semesterDateRange(2),
                },
            ]
        },
        configuredSchoolHours() {
            return Array.isArray(this.schoolHours) ? this.schoolHours : []
        },
        configuredCourseGroups() {
            return Array.isArray(this.courseGroups) ? this.courseGroups : []
        },
        displayedWeekdays() {
            if (this.showSaturday) {
                return this.weekdays
            }

            return this.weekdays.filter((weekday) => weekday.value !== 6)
        },
        weekdayRangeLabel() {
            return this.showSaturday ? 'Mo-Sa' : 'Mo-Fr'
        },
        timetableHours() {
            const courseHours = this.configuredCourseGroups
                .map((courseGroup) => Number(courseGroup.hour))
                .filter((hour) => Number.isFinite(hour))

            if (this.configuredSchoolHours.length) {
                const schoolHours = this.configuredSchoolHours
                    .map((schoolHour) => ({
                        hour: Number(schoolHour.hour),
                        from: this.formatTimeValue(schoolHour.from),
                        until: this.formatTimeValue(schoolHour.until),
                    }))
                    .filter((schoolHour) => Number.isFinite(schoolHour.hour))
                    .sort((a, b) => a.hour - b.hour)

                const configuredHours = new Set(schoolHours.map((schoolHour) => schoolHour.hour))
                courseHours
                    .filter((hour) => !configuredHours.has(hour))
                    .forEach((hour) => schoolHours.push({ hour, from: '', until: '' }))

                return schoolHours.sort((a, b) => a.hour - b.hour)
            }

            const fallbackHourCount = Math.max(FALLBACK_HOUR_COUNT, ...courseHours, 0)

            return Array.from({ length: fallbackHourCount }, (item, index) => ({
                hour: index + 1,
                from: '',
                until: '',
            }))
        },
        courseGroupsByCell() {
            return this.configuredCourseGroups.reduce((groups, courseGroup) => {
                const key = this.courseCellKey(courseGroup.semester, courseGroup.weekday, courseGroup.hour)
                if (!groups[key]) {
                    groups[key] = []
                }

                groups[key].push(courseGroup)

                return groups
            }, {})
        },
        selectedCourseGroupLabel() {
            return this.selectedCourseGroup?.display_label || this.selectedCourseGroup?.title || 'Termine'
        },
        selectedCourseGroupDates() {
            return Array.isArray(this.selectedCourseGroup?.dates) ? this.selectedCourseGroup.dates : []
        },
    },

    watch: {
        'config.selected_schoolyear.id'() {
            this.loadData()
        },
    },

    mounted() {
        this.loadData()
    },

    methods: {
        async loadData() {
            this.loading = true
            try {
                const [schoolHoursResponse, courseGroupsResponse] = await Promise.all([
                    axios.get('/api/admin/students-timetables/school-hours'),
                    axios.get('/api/admin/students-timetables/course-groups'),
                ])

                this.schoolHours = schoolHoursResponse.data?.data || []
                this.courseGroups = courseGroupsResponse.data?.data || []
            } catch {
                this.schoolHours = []
                this.courseGroups = []
            } finally {
                this.loading = false
            }
        },
        formatTimeValue(value) {
            const raw = (value || '').toString().trim()
            if (!raw) return ''

            return raw.slice(0, 5)
        },
        courseGroupsForCell(semester, weekday, hour) {
            return [...(this.courseGroupsByCell[this.courseCellKey(semester, weekday, hour)] || [])]
                .sort((left, right) => this.courseGroupSortLabel(left).localeCompare(
                    this.courseGroupSortLabel(right),
                    'de',
                    { sensitivity: 'base' },
                ))
        },
        courseGroupSortLabel(courseGroup) {
            return (courseGroup?.display_label || courseGroup?.title || '').toString()
        },
        openCourseGroupDialog(courseGroup) {
            this.selectedCourseGroup = courseGroup
            this.courseGroupDialog = true
        },
        closeCourseGroupDialog() {
            this.courseGroupDialog = false
            this.selectedCourseGroup = null
        },
        timetableHoursForSemester(semester) {
            return this.timetableHours.filter((hour) => (
                this.displayedWeekdays.some((weekday) => (
                    this.courseGroupsForCell(semester, weekday.value, hour.hour).length > 0
                ))
            ))
        },
        courseCellKey(semester, weekday, hour) {
            return `${Number(semester)}-${Number(weekday)}-${Number(hour)}`
        },
        semesterDateRange(semester) {
            const from = this.normalizeDate(this.selectedSchoolyear?.from)
            const until = this.normalizeDate(this.selectedSchoolyear?.until)
            const semester2Start = this.normalizeDate(this.selectedSchoolyear?.sem_2_start)

            if (semester === 1) {
                const semester1Until = semester2Start ? this.previousDay(semester2Start) : null

                return this.formatDateRange(from, semester1Until)
            }

            return this.formatDateRange(semester2Start, until)
        },
        formatDateRange(from, until) {
            const fromLabel = this.formatDate(from)
            const untilLabel = this.formatDate(until)

            if (fromLabel && untilLabel) {
                return `${fromLabel} - ${untilLabel}`
            }

            return 'Datum nicht vollständig gesetzt'
        },
        normalizeDate(value) {
            if (!value) return null

            const date = parseLocalDate(value)
            if (Number.isNaN(date.getTime())) return null

            date.setHours(0, 0, 0, 0)

            return date
        },
        previousDay(date) {
            const previous = new Date(date)
            previous.setDate(previous.getDate() - 1)

            return previous
        },
        formatDate(date) {
            if (!date) return ''

            return date.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatDateValue(value) {
            const date = this.normalizeDate(value)
            if (!date) return value || ''

            return this.formatDate(date)
        },
    },
}
</script>

<style scoped>
.semester-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

.semester-section {
    border: 1px solid rgba(0, 0, 0, 0.12);
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
}

.semester-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
    background-color: #f5f5f5;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.semester-section__dates {
    font-size: 0.75rem;
    color: rgba(0, 0, 0, 0.6);
    white-space: nowrap;
}

.timetable-saturday-switch {
    flex: 0 0 auto;
}

.timetable-table-wrapper {
    overflow-x: auto;
}

.timetable-grid-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 680px;
    font-size: 0.8rem;
}

.timetable-grid-table th,
.timetable-grid-table td {
    border: 1px solid rgba(0, 0, 0, 0.1);
    padding: 0;
    vertical-align: top;
}

.timetable-hour-header-cell {
    width: 58px;
    min-width: 58px;
    background-color: #f5f5f5;
}

.timetable-day-header-cell {
    text-align: center;
    min-width: 96px;
    background-color: #f5f5f5;
    font-weight: 600;
    padding: 8px 4px;
}

.timetable-hour-cell {
    text-align: center;
    background-color: #f5f5f5;
    padding: 6px 4px;
    white-space: nowrap;
    min-width: 58px;
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
    min-width: 96px;
    height: 52px;
    background-color: #ffffff;
    padding: 3px;
}

.timetable-empty-cell {
    padding: 12px;
    text-align: center;
    font-size: 0.75rem;
    color: rgba(0, 0, 0, 0.6);
}

.timetable-course-item {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    border-left: 3px solid #1976d2;
    background-color: #e3f2fd;
    padding: 4px 6px;
    min-height: 28px;
    cursor: pointer;
}

.timetable-course-item + .timetable-course-item {
    margin-top: 3px;
}

.timetable-course-item:hover,
.timetable-course-item:focus-visible {
    background-color: #bbdefb;
    outline: none;
}

.timetable-course-title {
    font-weight: 700;
    font-size: 0.75rem;
    line-height: 1.15;
    color: #0d47a1;
}

.timetable-course-markers {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.course-date-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
</style>
