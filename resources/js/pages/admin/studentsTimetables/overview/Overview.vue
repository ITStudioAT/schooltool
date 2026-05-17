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
                            @click="handleShowSaturdayClick">
                            !
                        </v-btn>
                    </template>
                </v-tooltip>
                <v-btn
                    color="grey-darken-1"
                    variant="outlined"
                    size="small"
                    prepend-icon="mdi-restore"
                    :disabled="loading || timetableUpdatePending"
                    @click="resetSavedTimetable">
                    Zurücksetzen
                </v-btn>
                <v-switch
                    :model-value="showSaturday"
                    color="primary"
                    density="compact"
                    hide-details
                    inset
                    label="Sa"
                    class="timetable-saturday-switch"
                    @update:model-value="handleShowSaturdayUpdate" />
            </v-card-title>
            <v-card-text class="timetable-card-body">
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
                                        :color="courseMenuEntryHasOverlap(entry, semester.value) ? 'error' : 'primary'"
                                        :class="{ 'semester-course-menu-item--overlap': courseMenuEntryHasOverlap(entry, semester.value) }"
                                        @click="handleCourseMenuEntryFilterClick(entry)">
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
                                @click:close="handleCourseMenuEntryFilterClick(filterChip.entry)">
                                {{ filterChip.label }}
                            </v-chip>
                        </div>

                        <div v-if="recurrenceWeekOptions(semester.value).length > 1" class="recurrence-week-selector">
                            <v-btn-toggle
                                v-if="!areRecurrenceWeeksExpanded(semester.value)"
                                :model-value="selectedTimetableOptionValue(semester.value)"
                                mandatory
                                density="compact"
                                color="primary"
                                variant="outlined"
                                divided
                                @update:model-value="handleSelectedTimetableOptionValueUpdate(semester.value, $event)">
                                <v-btn
                                    v-for="weekOption in timetableSelectorOptions(semester.value)"
                                    :key="weekOption.value"
                                    :value="weekOption.value"
                                    size="small"
                                    class="recurrence-week-selector__btn">
                                    {{ weekOption.label }}
                                </v-btn>
                            </v-btn-toggle>
                            <v-btn
                                size="small"
                                color="grey-darken-1"
                                variant="outlined"
                                prepend-icon="mdi-table-multiple"
                                class="recurrence-week-selector__action-btn"
                                @click="handleToggleRecurrenceWeeksClick(semester.value)">
                                {{ areRecurrenceWeeksExpanded(semester.value) ? 'Eine Woche anzeigen' : 'Wochen anzeigen' }}
                            </v-btn>
                        </div>

                        <div
                            v-for="timetableWeek in visibleTimetableWeeks(semester.value)"
                            :key="timetableWeek.key"
                            class="timetable-week">
                            <div v-if="timetableWeek.showLabel" class="timetable-week__title">
                                {{ timetableWeek.label }}
                            </div>

                            <div class="timetable-table-wrapper">
                                <table class="timetable-grid-table">
                                    <colgroup>
                                        <col class="timetable-hour-column">
                                        <col
                                            v-for="weekday in displayedWeekdays"
                                            :key="`${semester.value}-${timetableWeek.key}-${weekday.key}-column`"
                                            class="timetable-day-column">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="timetable-hour-header-cell"></th>
                                            <th v-for="weekday in displayedWeekdays" :key="`${semester.value}-${timetableWeek.key}-${weekday.key}`" class="timetable-day-header-cell">
                                                <div>{{ weekday.label }}</div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="hour in timetableHoursForSemester(semester.value, timetableWeek)" :key="`${semester.value}-${timetableWeek.key}-${hour.hour}`">
                                            <td class="timetable-hour-cell">
                                                <div class="timetable-hour-num">{{ hour.hour }}.</div>
                                                <div v-if="hour.from || hour.until" class="timetable-hour-time">
                                                    {{ hour.from }}<br>{{ hour.until }}
                                                </div>
                                            </td>
                                            <td
                                                v-for="weekday in displayedWeekdays"
                                                :key="`${semester.value}-${timetableWeek.key}-${weekday.key}-${hour.hour}`"
                                                class="timetable-grid-cell">
                                                <div
                                                    v-for="courseGroup in courseGroupsForCell(semester.value, weekday.value, hour.hour, timetableWeek)"
                                                    :key="courseGroup.key"
                                                    :class="[
                                                        'timetable-course-item',
                                                        { 'timetable-course-item--overlap': courseGroupHasOverlap(courseGroup) },
                                                        { 'timetable-course-item--related-overlap': courseGroupHasRelatedOverlap(courseGroup) },
                                                    ]"
                                                    role="button"
                                                    tabindex="0"
                                                    @click="openCourseGroupDialog(courseGroup)"
                                                    @keydown.enter="openCourseGroupDialog(courseGroup)">
                                                    <span class="timetable-course-title">{{ courseGroup.display_label || courseGroup.title }}</span>
                                                    <span v-if="courseGroupRelatedOverlapMarker(courseGroup)" class="timetable-course-related-marker">
                                                        {{ courseGroupRelatedOverlapMarker(courseGroup) }}
                                                    </span>
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
                                                            {{ courseGroupBlockLabel(courseGroup) }}
                                                        </v-chip>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!timetableHoursForSemester(semester.value, timetableWeek).length">
                                            <td :colspan="displayedWeekdays.length + 1" class="timetable-empty-cell">
                                                Keine Einträge in den sichtbaren Wochentagen.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="shouldShowExtraDatesNotice(semester.value, timetableWeek)" class="extra-dates-notice">
                                <span class="extra-dates-notice__text">Zusatzwochen vorhanden</span>
                                <v-checkbox
                                    :model-value="showExtraDatesInSelectedWeek(semester.value)"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    label="Im aktuellen Stundenplan anzeigen"
                                    @update:model-value="handleShowExtraDatesUpdate(semester.value, $event)" />
                            </div>
                        </div>
                    </section>
                </div>

                <template v-if="timetableUpdatePending">
                    <div class="timetable-update-blocker" aria-hidden="true"></div>
                    <div class="timetable-update-indicator" role="status" aria-live="polite">
                        <LoadingAnimation class="timetable-update-indicator__dots" />
                        <span>Stundenplan wird aktualisiert...</span>
                    </div>
                </template>
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
                            {{ courseGroupBlockLabel(selectedCourseGroup) }}
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
import LoadingAnimation from '@/pages/components/LoadingAnimation.vue'

const FALLBACK_HOUR_COUNT = 10
const ALL_DATES_OPTION_VALUE = 'all_dates'
const TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:overview:last-timetable'

export default {
    name: 'StudentsTimetablesOverview',
    components: {
        LoadingAnimation,
    },

    data() {
        return {
            loading: false,
            schoolHours: [],
            courseGroups: [],
            courseGroupDialog: false,
            selectedCourseGroup: null,
            timetableUpdatePending: false,
            selectionSaveSequence: 0,
            activeCourseGroupFilterKeys: [],
            selectedRecurrenceWeeks: {
                1: ALL_DATES_OPTION_VALUE,
                2: ALL_DATES_OPTION_VALUE,
            },
            expandedRecurrenceWeeks: {
                1: false,
                2: false,
            },
            showExtraDatesInSelectedWeeks: {
                1: false,
                2: false,
            },
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
        activeCourseGroupFilterKeySet() {
            return new Set(this.activeCourseGroupFilterKeys.filter(Boolean))
        },
        semesterCourseMenusBySemester() {
            return {
                1: this.buildSemesterCourseMenus(1),
                2: this.buildSemesterCourseMenus(2),
            }
        },
        selectedCourseMenuEntriesBySemester() {
            return {
                1: this.semesterCourseMenus(1)
                    .flatMap((courseMenu) => courseMenu.entries)
                    .filter((entry) => this.isCourseMenuEntryFilterActive(entry)),
                2: this.semesterCourseMenus(2)
                    .flatMap((courseMenu) => courseMenu.entries)
                    .filter((entry) => this.isCourseMenuEntryFilterActive(entry)),
            }
        },
        recurrenceWeekOptionsBySemester() {
            return {
                1: this.buildRecurrenceWeekOptions(1),
                2: this.buildRecurrenceWeekOptions(2),
            }
        },
        extraDatesOptionsBySemester() {
            return {
                1: this.buildExtraDatesOptions(1),
                2: this.buildExtraDatesOptions(2),
            }
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
            this.restoreLastTimetableState()
            this.loadData()
        },
    },

    mounted() {
        this.restoreLastTimetableState()
        this.loadData()
    },

    methods: {
        async loadData() {
            this.loading = true
            try {
                const [schoolHoursResponse, courseGroupsResponse, selectionsResponse] = await Promise.all([
                    axios.get('/api/admin/students-timetables/school-hours'),
                    axios.get('/api/admin/students-timetables/course-groups'),
                    axios.get('/api/admin/students-timetables/overview-selections'),
                ])

                this.schoolHours = schoolHoursResponse.data?.data || []
                this.courseGroups = courseGroupsResponse.data?.data || []
                this.applySavedCourseGroupSelection(selectionsResponse.data?.data?.course_group_keys)
            } catch {
                this.schoolHours = []
                this.courseGroups = []
                this.activeCourseGroupFilterKeys = []
            } finally {
                this.loading = false
            }
        },
        formatTimeValue(value) {
            const raw = (value || '').toString().trim()
            if (!raw) return ''

            return raw.slice(0, 5)
        },
        runTimetableUpdate(action) {
            if (this.timetableUpdatePending) {
                return
            }

            this.timetableUpdatePending = true

            const updateWindow = typeof window !== 'undefined' ? window : null
            const timerTarget = updateWindow || globalThis
            const runAction = () => {
                action()

                this.$nextTick(() => {
                    timerTarget.setTimeout(() => {
                        this.timetableUpdatePending = false
                    }, 0)
                })
            }
            const scheduleActionAfterPaint = () => {
                if (updateWindow && typeof updateWindow.requestAnimationFrame === 'function') {
                    updateWindow.requestAnimationFrame(() => {
                        timerTarget.setTimeout(runAction, 0)
                    })

                    return
                }

                timerTarget.setTimeout(runAction, 0)
            }

            this.$nextTick(scheduleActionAfterPaint)
        },
        handleShowSaturdayClick() {
            this.runTimetableUpdate(() => {
                this.showSaturday = true
                this.saveLastTimetableState()
            })
        },
        handleShowSaturdayUpdate(value) {
            this.runTimetableUpdate(() => {
                this.showSaturday = Boolean(value)
                this.saveLastTimetableState()
            })
        },
        handleCourseMenuEntryFilterClick(entry) {
            this.runTimetableUpdate(() => {
                this.toggleCourseMenuEntryFilter(entry)
            })
        },
        handleSelectedTimetableOptionValueUpdate(semester, value) {
            this.runTimetableUpdate(() => {
                this.setSelectedTimetableOptionValue(semester, value)
            })
        },
        handleToggleRecurrenceWeeksClick(semester) {
            this.runTimetableUpdate(() => {
                this.toggleRecurrenceWeeks(semester)
            })
        },
        handleShowExtraDatesUpdate(semester, value) {
            this.runTimetableUpdate(() => {
                this.setShowExtraDatesInSelectedWeek(semester, value)
            })
        },
        resetSavedTimetable() {
            this.runTimetableUpdate(() => {
                this.removeSavedTimetableState()
                this.applyTimetableState(this.defaultTimetableState())
                this.saveSelectedCourseGroupSelections?.()
            })
        },
        defaultTimetableState() {
            return {
                activeCourseGroupFilterKeys: [],
                selectedRecurrenceWeeks: {
                    1: ALL_DATES_OPTION_VALUE,
                    2: ALL_DATES_OPTION_VALUE,
                },
                expandedRecurrenceWeeks: {
                    1: false,
                    2: false,
                },
                showExtraDatesInSelectedWeeks: {
                    1: false,
                    2: false,
                },
                showSaturday: false,
            }
        },
        currentTimetableState() {
            const defaults = this.defaultTimetableState()

            return {
                activeCourseGroupFilterKeys: [...new Set(this.activeCourseGroupFilterKeys || [])],
                selectedRecurrenceWeeks: {
                    ...defaults.selectedRecurrenceWeeks,
                    ...(this.selectedRecurrenceWeeks || {}),
                },
                expandedRecurrenceWeeks: {
                    ...defaults.expandedRecurrenceWeeks,
                    ...(this.expandedRecurrenceWeeks || {}),
                },
                showExtraDatesInSelectedWeeks: {
                    ...defaults.showExtraDatesInSelectedWeeks,
                    ...(this.showExtraDatesInSelectedWeeks || {}),
                },
                showSaturday: Boolean(this.showSaturday),
            }
        },
        applyTimetableState(state) {
            const defaults = this.defaultTimetableState()

            this.activeCourseGroupFilterKeys = Array.isArray(state?.activeCourseGroupFilterKeys)
                ? [...new Set(state.activeCourseGroupFilterKeys.filter(Boolean))]
                : defaults.activeCourseGroupFilterKeys
            this.selectedRecurrenceWeeks = {
                ...defaults.selectedRecurrenceWeeks,
                ...(state?.selectedRecurrenceWeeks || {}),
            }
            this.expandedRecurrenceWeeks = {
                ...defaults.expandedRecurrenceWeeks,
                ...(state?.expandedRecurrenceWeeks || {}),
            }
            this.showExtraDatesInSelectedWeeks = {
                ...defaults.showExtraDatesInSelectedWeeks,
                ...(state?.showExtraDatesInSelectedWeeks || {}),
            }
            this.showSaturday = Boolean(state?.showSaturday ?? defaults.showSaturday)
        },
        timetableStorageKey() {
            return `${TIMETABLE_STORAGE_KEY_PREFIX}:${this.selectedSchoolyear?.id || 'default'}`
        },
        timetableStorage() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return null
            }

            return window.localStorage
        },
        saveLastTimetableState() {
            try {
                this.timetableStorage()?.setItem(
                    this.timetableStorageKey(),
                    JSON.stringify(this.currentTimetableState()),
                )
            } catch {
                // Ignore unavailable or full browser storage.
            }
        },
        restoreLastTimetableState() {
            try {
                const storedState = this.timetableStorage()?.getItem(this.timetableStorageKey())
                if (!storedState) {
                    this.applyTimetableState(this.defaultTimetableState())

                    return
                }

                this.applyTimetableState(JSON.parse(storedState))
            } catch {
                this.applyTimetableState(this.defaultTimetableState())
            }
        },
        removeSavedTimetableState() {
            try {
                this.timetableStorage()?.removeItem(this.timetableStorageKey())
            } catch {
                // Ignore unavailable browser storage.
            }
        },
        persistTimetableState() {
            this.saveLastTimetableState()
            this.saveSelectedCourseGroupSelections()
        },
        async saveSelectedCourseGroupSelections() {
            const saveSequence = this.selectionSaveSequence + 1
            this.selectionSaveSequence = saveSequence

            try {
                const response = await axios.put('/api/admin/students-timetables/overview-selections', {
                    course_group_keys: [...new Set(this.activeCourseGroupFilterKeys || [])],
                })
                if (saveSequence !== this.selectionSaveSequence) {
                    return
                }

                this.applySavedCourseGroupSelection(response.data?.data?.course_group_keys)
                this.saveLastTimetableState()
            } catch {
                // Keep the local selection visible; a later click or reload can sync it again.
            }
        },
        applySavedCourseGroupSelection(courseGroupKeys) {
            this.activeCourseGroupFilterKeys = Array.isArray(courseGroupKeys)
                ? [...new Set(courseGroupKeys.filter(Boolean))]
                : []
        },
        courseGroupsForCell(semester, weekday, hour, recurrenceWeek = null) {
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])

            return [...(this.courseGroupsByCell[this.courseCellKey(semester, weekday, hour)] || [])]
                .filter((courseGroup) => (
                    activeCourseGroupFilterKeySet.has(courseGroup?.key)
                    && this.courseGroupMatchesSelectedRecurrenceWeek(courseGroup, semester, recurrenceWeek)
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
            if (this.semesterCourseMenusBySemester) {
                return this.semesterCourseMenusBySemester[Number(semester)] || []
            }

            return this.buildSemesterCourseMenus(semester)
        },
        buildSemesterCourseMenus(semester) {
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
                            scheduleLabel: this.courseMenuEntryScheduleLabelWithFrequency(entry),
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
            if (this.selectedCourseMenuEntriesBySemester) {
                return this.selectedCourseMenuEntriesBySemester[Number(semester)] || []
            }

            return this.buildSelectedCourseMenuEntries(semester)
        },
        buildSelectedCourseMenuEntries(semester) {
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
        courseMenuEntryScheduleLabelWithFrequency(entry) {
            const scheduleLabel = this.courseMenuEntryScheduleLabel(entry)
            const frequencyLabel = this.courseMenuEntryFrequencyLabel(entry)

            return [
                scheduleLabel,
                frequencyLabel ? `(${frequencyLabel})` : '',
            ]
                .filter(Boolean)
                .join(' ')
        },
        courseMenuEntryFrequencyLabel(entry) {
            const courseGroups = Array.isArray(entry?.courseGroups) ? entry.courseGroups : []
            const recurrenceLabels = [...new Set(courseGroups
                .map((courseGroup) => Number(courseGroup?.recurrence_interval))
                .filter((interval) => [1, 2, 3, 4].includes(interval))
                .sort((left, right) => left - right)
                .map((interval) => `${interval}w`))]

            const singleDateCount = this.courseMenuEntrySingleDateCount(courseGroups)
            const labels = singleDateCount > 0
                ? [...recurrenceLabels, `${singleDateCount}x`]
                : recurrenceLabels

            return labels.join(', ')
        },
        courseMenuEntrySingleDateCount(courseGroups) {
            const singleDateGroups = courseGroups.filter((courseGroup) => (
                ![1, 2, 3, 4].includes(Number(courseGroup?.recurrence_interval))
            ))

            const dates = singleDateGroups
                .flatMap((courseGroup) => (Array.isArray(courseGroup?.dates) ? courseGroup.dates : []))
                .filter(Boolean)

            if (dates.length) {
                return new Set(dates).size
            }

            return singleDateGroups
                .map((courseGroup) => Number(courseGroup?.dates_count))
                .filter((datesCount) => Number.isFinite(datesCount) && datesCount > 0)
                .reduce((total, datesCount) => total + datesCount, 0)
        },
        recurrenceWeekOptions(semester) {
            if (this.recurrenceWeekOptionsBySemester) {
                return this.recurrenceWeekOptionsBySemester[Number(semester)] || []
            }

            return this.buildRecurrenceWeekOptions(semester)
        },
        buildRecurrenceWeekOptions(semester) {
            const maxInterval = this.selectedCourseMenuEntries(semester)
                .flatMap((entry) => entry.courseGroups || [])
                .map((courseGroup) => Number(courseGroup?.recurrence_interval))
                .filter((interval) => [2, 3, 4].includes(interval))
                .reduce((highestInterval, interval) => Math.max(highestInterval, interval), 1)

            return Array.from({ length: maxInterval }, (item, index) => ({
                value: index + 1,
                label: `Woche ${index + 1}`,
            }))
        },
        timetableSelectorOptions(semester) {
            return [
                this.allDatesOption(semester),
                ...this.recurrenceWeekOptions(semester),
                ...this.extraDatesOptions(semester),
            ]
        },
        allDatesOption(semester) {
            return {
                key: `semester-${semester}-all-dates`,
                type: 'all_dates',
                value: ALL_DATES_OPTION_VALUE,
                label: 'Alle Termine',
                showLabel: false,
            }
        },
        selectedRecurrenceWeek(semester) {
            const selectedWeek = Number(this.selectedRecurrenceWeeks?.[Number(semester)])
            const maxWeek = this.recurrenceWeekOptions(semester).length

            if (!Number.isFinite(selectedWeek) || selectedWeek < 1 || selectedWeek > maxWeek) {
                return 1
            }

            return selectedWeek
        },
        selectedTimetableOptionValue(semester) {
            const selectedValue = this.selectedRecurrenceWeeks?.[Number(semester)]
            if (selectedValue === ALL_DATES_OPTION_VALUE) {
                return selectedValue
            }

            if (selectedValue === 'extra_dates' && this.extraDatesOptions(semester).length) {
                return selectedValue
            }

            if (Number.isFinite(Number(selectedValue))) {
                return this.selectedRecurrenceWeek(semester)
            }

            return ALL_DATES_OPTION_VALUE
        },
        setSelectedTimetableOptionValue(semester, value) {
            if ([ALL_DATES_OPTION_VALUE, 'extra_dates'].includes(value)) {
                this.selectedRecurrenceWeeks = {
                    ...this.selectedRecurrenceWeeks,
                    [Number(semester)]: value,
                }
                this.saveLastTimetableState?.()

                return
            }

            this.setSelectedRecurrenceWeek(semester, value)
        },
        setSelectedRecurrenceWeek(semester, week) {
            const selectedWeek = Number(week)
            if (!Number.isFinite(selectedWeek)) {
                return
            }

            this.selectedRecurrenceWeeks = {
                ...this.selectedRecurrenceWeeks,
                [Number(semester)]: selectedWeek,
            }
            this.saveLastTimetableState?.()
        },
        areRecurrenceWeeksExpanded(semester) {
            return Boolean(this.expandedRecurrenceWeeks?.[Number(semester)])
        },
        toggleRecurrenceWeeks(semester) {
            const semesterNumber = Number(semester)

            this.expandedRecurrenceWeeks = {
                ...this.expandedRecurrenceWeeks,
                [semesterNumber]: !this.areRecurrenceWeeksExpanded(semesterNumber),
            }
            this.saveLastTimetableState?.()
        },
        visibleTimetableWeeks(semester) {
            const options = this.recurrenceWeekOptions(semester)

            if (options.length <= 1) {
                return [{
                    key: `semester-${semester}-single`,
                    type: 'single',
                    value: null,
                    label: '',
                    showLabel: false,
                }]
            }

            if (this.areRecurrenceWeeksExpanded(semester)) {
                return [
                    ...options.map((option) => ({
                        ...option,
                        key: `semester-${semester}-week-${option.value}`,
                        type: 'recurrence',
                        showLabel: true,
                    })),
                    ...this.extraDatesOptions(semester),
                ]
            }

            const selectedWeek = this.selectedRecurrenceWeek(semester)
            const selectedOption = options.find((option) => option.value === selectedWeek) || options[0]
            const selectedTimetableOption = this.selectedTimetableOptionValue(semester)

            if (selectedTimetableOption === ALL_DATES_OPTION_VALUE) {
                return [this.allDatesOption(semester)]
            }

            if (selectedTimetableOption === 'extra_dates') {
                return this.extraDatesOptions(semester)
            }

            return [{
                ...selectedOption,
                key: `semester-${semester}-week-${selectedOption.value}`,
                type: 'recurrence',
                showLabel: false,
            }]
        },
        extraDatesOptions(semester) {
            if (this.extraDatesOptionsBySemester) {
                return this.extraDatesOptionsBySemester[Number(semester)] || []
            }

            return this.buildExtraDatesOptions(semester)
        },
        buildExtraDatesOptions(semester) {
            const hasExtraDates = this.selectedCourseMenuEntries(semester)
                .flatMap((entry) => entry.courseGroups || [])
                .filter((courseGroup) => this.courseGroupHasExtraDateWeek(courseGroup))
                .length > 0

            if (!hasExtraDates) {
                return []
            }

            return [{
                key: `semester-${semester}-extra-dates`,
                type: 'extra_dates',
                value: 'extra_dates',
                label: 'Zusatzwochen',
                showLabel: true,
            }]
        },
        shouldShowExtraDatesNotice(semester, timetableWeek) {
            return ['all_dates', 'recurrence'].includes(timetableWeek?.type)
                && !this.areRecurrenceWeeksExpanded(semester)
                && this.extraDatesOptions(semester).length > 0
        },
        showExtraDatesInSelectedWeek(semester) {
            return Boolean(this.showExtraDatesInSelectedWeeks?.[Number(semester)])
        },
        setShowExtraDatesInSelectedWeek(semester, value) {
            this.showExtraDatesInSelectedWeeks = {
                ...this.showExtraDatesInSelectedWeeks,
                [Number(semester)]: Boolean(value),
            }
            this.saveLastTimetableState?.()
        },
        shouldIncludeExtraDatesInRegularWeek(semester) {
            return this.selectedTimetableOptionValue(semester) !== 'extra_dates'
                && !this.areRecurrenceWeeksExpanded(semester)
                && this.extraDatesOptions(semester).length > 0
                && this.showExtraDatesInSelectedWeek(semester)
        },
        courseGroupHasRegularRecurrence(courseGroup) {
            return [1, 2, 3, 4].includes(Number(courseGroup?.recurrence_interval))
        },
        courseGroupHasExtraDateWeek(courseGroup) {
            return !this.courseGroupHasRegularRecurrence(courseGroup)
        },
        courseGroupMatchesSelectedRecurrenceWeek(courseGroup, semester, recurrenceWeek = null) {
            if (recurrenceWeek?.type === 'extra_dates') {
                return this.courseGroupHasExtraDateWeek(courseGroup)
            }

            const selectedTimetableOption = this.selectedTimetableOptionValue(semester)
            if (recurrenceWeek?.type === 'all_dates' || (!recurrenceWeek && selectedTimetableOption === ALL_DATES_OPTION_VALUE)) {
                if (this.courseGroupHasExtraDateWeek(courseGroup)) {
                    return this.shouldIncludeExtraDatesInRegularWeek(semester)
                }

                return true
            }

            const options = this.recurrenceWeekOptions(semester)
            if (options.length <= 1) {
                return true
            }

            const interval = Number(courseGroup?.recurrence_interval)
            if (this.courseGroupHasRegularRecurrence(courseGroup) && interval === 1) {
                if (recurrenceWeek?.type === 'recurrence' || Number.isFinite(Number(recurrenceWeek))) {
                    return true
                }

                return selectedTimetableOption !== 'extra_dates'
            }

            if (!this.courseGroupHasRegularRecurrence(courseGroup) || ![2, 3, 4].includes(interval)) {
                if (recurrenceWeek?.type === 'recurrence' || Number.isFinite(Number(recurrenceWeek))) {
                    return this.shouldIncludeExtraDatesInRegularWeek(semester)
                }

                return selectedTimetableOption === 'extra_dates'
            }

            const targetWeek = recurrenceWeek?.type === 'recurrence'
                ? Number(recurrenceWeek.value)
                : recurrenceWeek !== null && recurrenceWeek !== undefined && Number.isFinite(Number(recurrenceWeek))
                    ? Number(recurrenceWeek)
                    : this.selectedRecurrenceWeek(semester)

            const startWeek = this.courseGroupRecurrenceWeek(courseGroup, semester)
            const weekOffset = ((targetWeek - startWeek) % interval + interval) % interval

            return weekOffset === 0
        },
        courseGroupRecurrenceWeek(courseGroup, semester) {
            const interval = Number(courseGroup?.recurrence_interval)
            if (![2, 3, 4].includes(interval)) {
                return 1
            }

            const firstDate = this.normalizeDate(courseGroup?.first_date || courseGroup?.dates?.[0])
            const semesterStart = this.semesterStartDate(semester)
            if (!firstDate || !semesterStart) {
                return 1
            }

            const dayDifference = Math.floor((firstDate.getTime() - semesterStart.getTime()) / 86400000)
            const weekOffset = Math.floor(dayDifference / 7)
            const normalizedOffset = ((weekOffset % interval) + interval) % interval

            return normalizedOffset + 1
        },
        semesterStartDate(semester) {
            const configuredStart = Number(semester) === 1
                ? this.normalizeDate(this.selectedSchoolyear?.from)
                : this.normalizeDate(this.selectedSchoolyear?.sem_2_start)

            if (configuredStart) {
                return configuredStart
            }

            return this.configuredCourseGroups
                .filter((courseGroup) => Number(courseGroup?.semester) === Number(semester))
                .flatMap((courseGroup) => [courseGroup?.first_date, ...(courseGroup?.dates || [])])
                .map((date) => this.normalizeDate(date))
                .filter(Boolean)
                .sort((left, right) => left.getTime() - right.getTime())[0] || null
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

            if (!containingEntry) {
                return false
            }

            return this.selectedCourseMenuEntries(semester)
                .filter((selectedEntry) => selectedEntry.key !== containingEntry.key)
                .some((selectedEntry) => (
                    (selectedEntry?.courseGroups || []).some((selectedCourseGroup) => (
                        this.courseGroupsOverlap(courseGroup, selectedCourseGroup)
                    ))
                ))
        },
        courseGroupHasRelatedOverlap(courseGroup) {
            if (this.courseGroupHasOverlap(courseGroup)) {
                return false
            }

            const semester = Number(courseGroup?.semester)
            if (!Number.isFinite(semester)) {
                return false
            }

            const containingEntry = this.selectedCourseMenuEntries(semester)
                .find((entry) => this.courseMenuEntryKeys(entry).includes(courseGroup?.key))

            return Boolean(containingEntry && this.courseMenuEntryHasOverlap(containingEntry, semester))
        },
        courseGroupRelatedOverlapMarker(courseGroup) {
            return this.courseGroupHasRelatedOverlap(courseGroup) ? '⚠ Mitbetroffen' : ''
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
        courseGroupBlockLabel(courseGroup) {
            return this.courseGroupDateRangeLabel(courseGroup) || courseGroup?.block_label || 'Block'
        },
        courseGroupDateRangeLabel(courseGroup) {
            const dates = Array.isArray(courseGroup?.dates)
                ? [...courseGroup.dates].filter(Boolean).sort()
                : []
            const from = courseGroup?.first_date || dates[0] || null
            const until = courseGroup?.last_date || dates[dates.length - 1] || from
            const fromLabel = this.formatCompactDateValue(from)
            const untilLabel = this.formatCompactDateValue(until)

            if (fromLabel && untilLabel && fromLabel !== untilLabel) {
                return `${fromLabel} - ${untilLabel}`
            }

            return fromLabel || untilLabel || ''
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
                this.persistTimetableState?.()

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
            this.persistTimetableState?.()
        },
        isCourseMenuEntryFilterActive(entry) {
            const courseGroupKeys = this.courseMenuEntryKeys(entry)
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])

            return courseGroupKeys.length > 0
                && courseGroupKeys.every((courseGroupKey) => activeCourseGroupFilterKeySet.has(courseGroupKey))
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
                this.persistTimetableState?.()

                return
            }

            this.activeCourseGroupFilterKeys = [
                ...this.activeCourseGroupFilterKeys,
                courseGroupKey,
            ]
            if (Number(courseGroup?.weekday) === 6) {
                this.showSaturday = true
            }
            this.persistTimetableState?.()
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
        timetableHoursForSemester(semester, recurrenceWeek = null) {
            return this.timetableHours.filter((hour) => (
                this.displayedWeekdays.some((weekday) => (
                    this.courseGroupsForCell(semester, weekday.value, hour.hour, recurrenceWeek).length > 0
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
        formatCompactDateValue(value) {
            const date = this.normalizeDate(value)
            if (!date) return value || ''

            const day = date.getDate().toString().padStart(2, '0')
            const month = (date.getMonth() + 1).toString().padStart(2, '0')

            return `${day}.${month}.`
        },
    },
}
</script>

<style scoped>
.timetable-card-body {
    position: relative;
}

.timetable-update-blocker {
    position: fixed;
    inset: 0;
    z-index: 90;
    background: rgba(255, 255, 255, 0.28);
    cursor: progress;
}

.timetable-update-indicator {
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    min-width: 220px;
    padding: 18px 22px;
    transform: translate(-50%, -50%);
    border: 1px solid rgba(25, 118, 210, 0.22);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.18);
    color: #0d47a1;
    font-size: 0.82rem;
    font-weight: 700;
    text-align: center;
}

.timetable-update-indicator__dots {
    padding: 0;
}

.timetable-update-indicator__dots :deep(.loading-animation) {
    padding: 0;
}

.timetable-update-indicator__dots :deep(.loading-dots) {
    gap: 6px;
}

.timetable-update-indicator__dots :deep(.dot) {
    width: 8px;
    height: 8px;
}

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

.semester-course-menu-item--overlap:not(.v-list-item--active) .semester-course-menu-title,
.semester-course-menu-item--overlap:not(.v-list-item--active) .semester-course-menu-subtitle {
    color: #d32f2f;
}

.semester-course-menu-item--overlap:not(.v-list-item--active) .v-icon {
    color: #d32f2f;
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

.recurrence-week-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    background: #ffffff;
}

.recurrence-week-selector__btn {
    min-width: 76px;
}

.recurrence-week-selector__action-btn {
    background: #ffffff;
}

.timetable-week + .timetable-week {
    border-top: 1px solid rgba(0, 0, 0, 0.08);
}

.timetable-week__title {
    padding: 8px 12px;
    font-size: 0.78rem;
    font-weight: 700;
    color: rgba(0, 0, 0, 0.72);
    background: #fafafa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.extra-dates-notice {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 14px;
    padding: 8px 12px;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    background: #ffffff;
}

.extra-dates-notice__text {
    font-size: 0.78rem;
    font-weight: 700;
    color: rgba(0, 0, 0, 0.68);
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
    table-layout: fixed;
    min-width: 680px;
    font-size: 0.8rem;
}

.timetable-hour-column {
    width: 58px;
}

.timetable-day-column {
    width: auto;
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
    min-width: 0;
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

.timetable-course-item--related-overlap {
    border-left-color: #fb8c00;
    background-color: #fff3e0;
}

.timetable-course-item--related-overlap:hover,
.timetable-course-item--related-overlap:focus-visible {
    background-color: #ffe0b2;
}

.timetable-course-title {
    font-weight: 700;
    font-size: 0.75rem;
    line-height: 1.15;
    color: #0d47a1;
    overflow-wrap: anywhere;
}

.timetable-course-item--overlap .timetable-course-title {
    color: #b71c1c;
}

.timetable-course-item--related-overlap .timetable-course-title {
    color: #e65100;
}

.timetable-course-related-marker {
    flex: 0 0 auto;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1.1;
    color: #e65100;
    white-space: nowrap;
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
