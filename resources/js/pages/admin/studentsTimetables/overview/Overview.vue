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
                <v-tooltip v-if="hasHiddenSaturdayCourses" text="Es gibt Einträge am Samstag. Sa aktivieren, um sie zu sehen.">
                    <template #activator="{ props }">
                        <v-btn
                            v-bind="props"
                            color="error"
                            variant="flat"
                            size="large"
                            class="hidden-saturday-warning"
                            aria-label="Samstag hat ausgeblendete Einträge"
                            @click="showSaturday = true">
                            !
                        </v-btn>
                    </template>
                </v-tooltip>
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

                        <div v-if="semesterCourseMenus(semester.value).length" class="semester-course-chips">
                            <v-menu
                                v-for="courseMenu in semesterCourseMenus(semester.value)"
                                :key="courseMenu.key"
                                location="bottom start"
                                max-height="360">
                                <template #activator="{ props }">
                                    <v-chip
                                        v-bind="props"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        append-icon="mdi-menu-down"
                                        class="semester-course-chip">
                                        {{ courseMenu.label }}
                                    </v-chip>
                                </template>

                                <v-list density="compact" class="semester-course-menu-list">
                                    <v-list-item
                                        v-for="entry in courseMenu.entries"
                                        :key="entry.key"
                                        :active="isCourseMenuEntryFilterActive(entry)"
                                        color="primary"
                                        @click="toggleCourseMenuEntryFilter(entry)">
                                        <template #prepend>
                                            <v-icon
                                                :icon="isCourseMenuEntryFilterActive(entry) ? 'mdi-check' : 'mdi-calendar-blank'"
                                                size="18" />
                                        </template>
                                        <v-list-item-title class="semester-course-menu-title">
                                            {{ entry.label }}
                                        </v-list-item-title>
                                        <v-list-item-subtitle v-if="entry.scheduleLabel" class="semester-course-menu-subtitle">
                                            {{ entry.scheduleLabel }}
                                        </v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </v-menu>
                        </div>

                        <div v-if="selectedCourseFilterChips(semester.value).length" class="selected-course-filter-chips">
                            <v-chip
                                v-for="filterChip in selectedCourseFilterChips(semester.value)"
                                :key="filterChip.key"
                                size="small"
                                :color="filterChip.hasOverlap ? 'error' : 'success'"
                                variant="tonal"
                                closable
                                class="selected-course-filter-chip"
                                @click:close="toggleCourseMenuEntryFilter(filterChip.entry)">
                                {{ filterChip.label }}
                            </v-chip>
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
                                                :class="[
                                                    'timetable-course-item',
                                                    { 'timetable-course-item--overlap': courseGroupHasOverlap(courseGroup) },
                                                ]"
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
                            v-for="dateItem in selectedCourseGroupDateItems"
                            :key="dateItem.key"
                            size="small"
                            variant="tonal"
                            color="secondary"
                            class="course-date-chip">
                            <span>{{ dateItem.dateLabel }}</span>
                            <span v-if="dateItem.timeRangeLabel" class="course-date-chip__separator">·</span>
                            <span v-if="dateItem.timeRangeLabel" class="course-date-chip__time">
                                {{ dateItem.timeRangeLabel }}
                            </span>
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
            activeCourseGroupFilterKeys: [],
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
        hasHiddenSaturdayCourses() {
            return !this.showSaturday
                && this.configuredCourseGroups.some((courseGroup) => (
                    Number(courseGroup?.weekday) === 6
                    && this.activeCourseGroupFilterKeys.includes(courseGroup?.key)
                ))
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
        selectedCourseGroupDateItems() {
            const timeRangeLabel = this.courseGroupTimeRangeLabel(this.selectedCourseGroup)

            return this.selectedCourseGroupDates.map((date) => ({
                key: `${date}-${timeRangeLabel}`,
                dateLabel: this.formatDateValue(date),
                timeRangeLabel,
            }))
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
                .filter((courseGroup) => (
                    this.activeCourseGroupFilterKeys.includes(courseGroup?.key)
                ))
                .sort((left, right) => this.courseGroupSortLabel(left).localeCompare(
                    this.courseGroupSortLabel(right),
                    'de',
                    { sensitivity: 'base' },
                ))
        },
        courseGroupSortLabel(courseGroup) {
            return (courseGroup?.display_label || courseGroup?.title || '').toString()
        },
        semesterCourseMenus(semester) {
            const courseMenusByLabel = this.configuredCourseGroups
                .filter((courseGroup) => Number(courseGroup?.semester) === Number(semester))
                .reduce((courseMenus, courseGroup) => {
                    const label = this.mainCourseLabel(courseGroup)
                    if (!label) {
                        return courseMenus
                    }

                    const normalizedLabel = label.toLocaleUpperCase('de-AT')
                    if (!courseMenus.has(normalizedLabel)) {
                        courseMenus.set(normalizedLabel, {
                            key: `semester-${semester}-${normalizedLabel}`,
                            label,
                            entriesByLabel: new Map(),
                        })
                    }

                    const courseMenu = courseMenus.get(normalizedLabel)
                    const entryLabel = this.courseGroupMenuLabel(courseGroup)
                    const normalizedEntryLabel = entryLabel.toLocaleUpperCase('de-AT')
                    if (!courseMenu.entriesByLabel.has(normalizedEntryLabel)) {
                        courseMenu.entriesByLabel.set(normalizedEntryLabel, {
                            key: `semester-${semester}-${normalizedLabel}-${normalizedEntryLabel}`,
                            label: entryLabel,
                            courseGroups: [],
                            courseGroupKeys: [],
                        })
                    }

                    const entry = courseMenu.entriesByLabel.get(normalizedEntryLabel)
                    entry.courseGroups.push(courseGroup)
                    if (courseGroup?.key) {
                        entry.courseGroupKeys.push(courseGroup.key)
                    }

                    return courseMenus
                }, new Map())

            return [...courseMenusByLabel.values()]
                .map((courseMenu) => ({
                    key: courseMenu.key,
                    label: courseMenu.label,
                    entries: [...courseMenu.entriesByLabel.values()]
                        .map((entry) => ({
                            ...entry,
                            courseGroupKeys: [...new Set(entry.courseGroupKeys)],
                            scheduleLabel: this.courseMenuEntryScheduleLabel(entry),
                        }))
                        .sort((left, right) => (
                            left.label.localeCompare(right.label, 'de', { sensitivity: 'base' })
                        )),
                }))
                .sort((left, right) => left.label.localeCompare(right.label, 'de', { sensitivity: 'base' }))
        },
        semesterCourseChips(semester) {
            return this.semesterCourseMenus(semester).map((courseMenu) => ({
                key: courseMenu.key,
                label: courseMenu.label,
            }))
        },
        selectedCourseFilterChips(semester) {
            return this.semesterCourseMenus(semester)
                .flatMap((courseMenu) => courseMenu.entries)
                .filter((entry) => this.isCourseMenuEntryFilterActive(entry))
                .map((entry) => ({
                    key: `selected-${entry.key}`,
                    label: entry.scheduleLabel ? `${entry.label} · ${entry.scheduleLabel}` : entry.label,
                    hasOverlap: this.courseMenuEntryHasOverlap(entry, semester),
                    entry,
                }))
        },
        selectedCourseMenuEntries(semester) {
            return this.semesterCourseMenus(semester)
                .flatMap((courseMenu) => courseMenu.entries)
                .filter((entry) => this.isCourseMenuEntryFilterActive(entry))
        },
        courseGroupMenuLabel(courseGroup) {
            return (
                courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || 'Ohne Bezeichnung'
            ).toString()
        },
        courseMenuEntryScheduleLabel(entry) {
            const schedulesByWeekday = (entry?.courseGroups || []).reduce((schedules, courseGroup) => {
                const weekday = this.weekdayForCourseGroup(courseGroup)
                const timeRange = this.courseGroupTimeRangeParts(courseGroup)
                if (!weekday.label && !timeRange.label) {
                    return schedules
                }

                const key = weekday.value ? `weekday-${weekday.value}` : `time-${timeRange.label}`
                if (!schedules.has(key)) {
                    schedules.set(key, {
                        weekdayOrder: weekday.value || 99,
                        weekdayLabel: weekday.label,
                        from: '',
                        until: '',
                        fallbackLabels: new Set(),
                    })
                }

                const schedule = schedules.get(key)
                if (timeRange.from && timeRange.until) {
                    schedule.from = schedule.from && schedule.from < timeRange.from ? schedule.from : timeRange.from
                    schedule.until = schedule.until && schedule.until > timeRange.until ? schedule.until : timeRange.until

                    return schedules
                }

                schedule.fallbackLabels.add([weekday.label, timeRange.label].filter(Boolean).join(' '))

                return schedules
            }, new Map())

            return [...schedulesByWeekday.values()]
                .sort((left, right) => left.weekdayOrder - right.weekdayOrder)
                .flatMap((schedule) => {
                    if (schedule.from && schedule.until) {
                        return [[schedule.weekdayLabel, `${schedule.from} - ${schedule.until}`].filter(Boolean).join(' ')]
                    }

                    return [...schedule.fallbackLabels]
                })
                .filter(Boolean)
                .join(', ')
        },
        courseGroupScheduleLabel(courseGroup) {
            const weekdayLabel = this.weekdayForCourseGroup(courseGroup).label
            const timeRangeLabel = this.courseGroupTimeRangeLabel(courseGroup)

            return [weekdayLabel, timeRangeLabel]
                .filter(Boolean)
                .join(' ')
        },
        courseMenuEntryHasOverlap(entry, semester) {
            return this.selectedCourseMenuEntries(semester)
                .some((selectedEntry) => (
                    selectedEntry.key !== entry?.key
                    && this.courseMenuEntriesOverlap(entry, selectedEntry)
                ))
        },
        courseGroupHasOverlap(courseGroup) {
            const semester = Number(courseGroup?.semester)
            if (!Number.isFinite(semester)) {
                return false
            }

            const containingEntry = this.selectedCourseMenuEntries(semester)
                .find((entry) => this.courseMenuEntryKeys(entry).includes(courseGroup?.key))

            return Boolean(containingEntry && this.courseMenuEntryHasOverlap(containingEntry, semester))
        },
        courseMenuEntriesOverlap(leftEntry, rightEntry) {
            return (leftEntry?.courseGroups || []).some((leftCourseGroup) => (
                (rightEntry?.courseGroups || []).some((rightCourseGroup) => (
                    this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                ))
            ))
        },
        courseGroupsOverlap(leftCourseGroup, rightCourseGroup) {
            if (leftCourseGroup?.key && leftCourseGroup.key === rightCourseGroup?.key) {
                return false
            }

            if (
                Number(leftCourseGroup?.semester) !== Number(rightCourseGroup?.semester)
                || Number(leftCourseGroup?.weekday) !== Number(rightCourseGroup?.weekday)
            ) {
                return false
            }

            const leftTimeRange = this.courseGroupTimeRangeParts(leftCourseGroup)
            const rightTimeRange = this.courseGroupTimeRangeParts(rightCourseGroup)
            if (leftTimeRange.from && leftTimeRange.until && rightTimeRange.from && rightTimeRange.until) {
                return leftTimeRange.from < rightTimeRange.until && rightTimeRange.from < leftTimeRange.until
            }

            return Number(leftCourseGroup?.hour) === Number(rightCourseGroup?.hour)
        },
        weekdayForCourseGroup(courseGroup) {
            const weekday = (this.weekdays || [])
                .find((configuredWeekday) => Number(configuredWeekday.value) === Number(courseGroup?.weekday))

            return {
                label: weekday?.label || '',
                value: Number(weekday?.value) || null,
            }
        },
        mainCourseLabel(courseGroup) {
            const source = this.courseGroupCourseSource(courseGroup)
            if (!source) return ''

            const firstSegment = source.split(/\s+-\s+/u)[0]?.trim() || ''
            if (!firstSegment || this.isTimeOnlyValue(firstSegment)) return ''

            const match = firstSegment.match(/^([^\d\s-]+)\d*/u)

            return (match?.[1] || firstSegment).trim()
        },
        courseGroupCourseSource(courseGroup) {
            return [
                courseGroup?.course,
                courseGroup?.title,
                courseGroup?.display_label,
                courseGroup?.subject,
            ]
                .map((value) => (value || '').toString().trim())
                .find((value) => value !== '' && !this.isTimeOnlyValue(value)) || ''
        },
        isTimeOnlyValue(value) {
            return /^\d{1,2}:\d{2}$/.test((value || '').toString().trim())
        },
        courseGroupTimeRangeLabel(courseGroup) {
            return this.courseGroupTimeRangeParts(courseGroup).label
        },
        courseGroupTimeRangeParts(courseGroup) {
            const importedTimeRange = this.importedCourseGroupTimeRange(courseGroup)
            const schoolHourTimeRange = this.schoolHourTimeRange(courseGroup)
            const from = importedTimeRange.from || schoolHourTimeRange.from
            const until = importedTimeRange.until || schoolHourTimeRange.until

            if (from && until) {
                return {
                    from,
                    until,
                    label: `${from} - ${until}`,
                }
            }

            return {
                from,
                until,
                label: from || until || '',
            }
        },
        importedCourseGroupTimeRange(courseGroup) {
            const from = this.isTimeOnlyValue(courseGroup?.subject)
                ? this.formatTimeValue(courseGroup.subject)
                : ''
            const until = this.isTimeOnlyValue(courseGroup?.teacher)
                ? this.formatTimeValue(courseGroup.teacher)
                : ''

            return { from, until }
        },
        schoolHourTimeRange(courseGroup) {
            const schoolHour = this.configuredSchoolHours.find((configuredSchoolHour) => (
                Number(configuredSchoolHour?.hour) === Number(courseGroup?.hour)
            ))

            return {
                from: this.formatTimeValue(schoolHour?.from),
                until: this.formatTimeValue(schoolHour?.until),
            }
        },
        toggleCourseMenuEntryFilter(entry) {
            const courseGroupKeys = this.courseMenuEntryKeys(entry)
            if (!courseGroupKeys.length) {
                return
            }

            if (this.isCourseMenuEntryFilterActive(entry)) {
                this.activeCourseGroupFilterKeys = this.activeCourseGroupFilterKeys
                    .filter((activeKey) => !courseGroupKeys.includes(activeKey))

                return
            }

            this.activeCourseGroupFilterKeys = [
                ...new Set([
                    ...this.activeCourseGroupFilterKeys,
                    ...courseGroupKeys,
                ]),
            ]

            if ((entry?.courseGroups || []).some((courseGroup) => Number(courseGroup?.weekday) === 6)) {
                this.showSaturday = true
            }
        },
        isCourseMenuEntryFilterActive(entry) {
            const courseGroupKeys = this.courseMenuEntryKeys(entry)

            return courseGroupKeys.length > 0
                && courseGroupKeys.every((courseGroupKey) => this.activeCourseGroupFilterKeys.includes(courseGroupKey))
        },
        courseMenuEntryKeys(entry) {
            if (Array.isArray(entry?.courseGroupKeys)) {
                return entry.courseGroupKeys.filter(Boolean)
            }

            return entry?.courseGroup?.key ? [entry.courseGroup.key] : []
        },
        toggleCourseGroupFilter(courseGroup) {
            const courseGroupKey = courseGroup?.key || null
            if (!courseGroupKey) {
                return
            }

            if (this.isCourseGroupFilterActive(courseGroup)) {
                this.activeCourseGroupFilterKeys = this.activeCourseGroupFilterKeys
                    .filter((activeKey) => activeKey !== courseGroupKey)

                return
            }

            this.activeCourseGroupFilterKeys = [
                ...this.activeCourseGroupFilterKeys,
                courseGroupKey,
            ]
            if (Number(courseGroup?.weekday) === 6) {
                this.showSaturday = true
            }
        },
        isCourseGroupFilterActive(courseGroup) {
            return this.activeCourseGroupFilterKeys.includes(courseGroup?.key)
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

.semester-course-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    background: #ffffff;
}

.semester-course-chip {
    font-weight: 650;
}

.semester-course-menu-list {
    min-width: 220px;
}

.semester-course-menu-title {
    font-size: 0.82rem;
    font-weight: 650;
}

.semester-course-menu-subtitle {
    font-size: 0.72rem;
}

.selected-course-filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    background: #ffffff;
}

.selected-course-filter-chip {
    font-weight: 650;
}

.timetable-saturday-switch {
    flex: 0 0 auto;
}

.hidden-saturday-warning {
    min-width: 44px;
    height: 44px;
    font-size: 1.7rem;
    font-weight: 900;
    line-height: 1;
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

.timetable-course-item--overlap {
    border-left-color: #d32f2f;
    background-color: #ffebee;
}

.timetable-course-item--overlap:hover,
.timetable-course-item--overlap:focus-visible {
    background-color: #ffcdd2;
}

.timetable-course-title {
    font-weight: 700;
    font-size: 0.75rem;
    line-height: 1.15;
    color: #0d47a1;
}

.timetable-course-item--overlap .timetable-course-title {
    color: #b71c1c;
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

.course-date-chip {
    gap: 6px;
}

.course-date-chip__separator {
    margin: 0 6px;
    opacity: 0.65;
}

.course-date-chip__time {
    font-weight: 700;
}
</style>
