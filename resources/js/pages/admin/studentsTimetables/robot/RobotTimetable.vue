<template>
    <v-col cols="12" lg="8" xl="7">
        <v-card rounded="lg" border class="robot-timetable-card">
            <v-card-title class="robot-timetable-card__title d-flex align-center ga-2">
                <v-icon icon="mdi-robot-outline" />
                Roboter Stundenplan
            </v-card-title>
            <v-card-text class="robot-timetable-card__text">
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3" />

                <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
                    {{ error }}
                </v-alert>

                <div class="robot-timetable-grid">
                    <v-select
                        v-model="selection.semester"
                        :items="semesterOptions"
                        item-title="title"
                        item-value="value"
                        label="Semester"
                        variant="outlined"
                        density="compact"
                        hide-details="auto" />
                    <v-select
                        v-model="selection.religion"
                        :items="religionOptions"
                        item-title="title"
                        item-value="value"
                        label="Ethik / Religion"
                        variant="outlined"
                        density="compact"
                        hide-details="auto" />
                    <v-select
                        v-model="selection.branch"
                        :items="branchOptions"
                        item-title="title"
                        item-value="value"
                        label="Zweig"
                        variant="outlined"
                        density="compact"
                        hide-details="auto" />
                    <v-select
                        v-model="selection.artsSubject"
                        :items="artsSubjectOptions"
                        item-title="title"
                        item-value="value"
                        label="ME / BE"
                        variant="outlined"
                        density="compact"
                        hide-details="auto" />
                    <v-select
                        v-model="selection.language"
                        :items="languageOptions"
                        item-title="title"
                        item-value="value"
                        label="Sprache"
                        variant="outlined"
                        density="compact"
                        hide-details="auto" />
                </div>

                <div class="robot-timetable-summary">
                    <v-chip
                        v-for="item in selectedSummary"
                        :key="item.key"
                        size="small"
                        color="primary"
                        variant="tonal"
                        class="robot-timetable-summary__chip">
                        <strong>{{ item.label }}</strong>
                        <span>{{ item.value }}</span>
                    </v-chip>
                </div>

                <div class="robot-constraints">
                    <div class="robot-constraints__title">Zeitvorgaben</div>
                    <div class="robot-constraint-block">
                        <div class="robot-constraint-block__label">Tage, an denen ich kann</div>
                        <div class="robot-chip-row">
                            <v-chip
                                v-for="weekday in weekdayOptions"
                                :key="`available-weekday-${weekday.value}`"
                                size="small"
                                :color="constraintSelected('availableWeekdays', weekday.value) ? 'success' : 'secondary'"
                                :variant="constraintSelected('availableWeekdays', weekday.value) ? 'flat' : 'tonal'"
                                filter
                                @click="toggleConstraint('availableWeekdays', weekday.value)">
                                {{ weekday.shortTitle }}
                            </v-chip>
                        </div>
                    </div>

                    <div class="robot-constraint-block">
                        <div class="robot-constraint-block__label">Diese Stunden verwenden</div>
                        <div class="robot-chip-row">
                            <v-chip
                                v-for="time in timeOptions"
                                :key="`available-time-${time.value}`"
                                size="small"
                                :color="constraintSelected('availableTimes', time.value) ? 'success' : 'secondary'"
                                :variant="constraintSelected('availableTimes', time.value) ? 'flat' : 'tonal'"
                                filter
                                @click="toggleConstraint('availableTimes', time.value)">
                                {{ time.shortTitle }}
                            </v-chip>
                        </div>
                    </div>

                    <div class="robot-constraint-block">
                        <div class="robot-constraint-block__label">Einzelne Zeiten sperren</div>
                        <div class="robot-time-matrix">
                            <template
                                v-for="weekday in weekdayOptions"
                                :key="`weekday-time-row-${weekday.value}`">
                                <div class="robot-time-matrix__weekday">{{ weekday.shortTitle }}</div>
                                <div class="robot-chip-row robot-chip-row--times">
                                    <v-chip
                                        v-for="time in timeOptions"
                                        :key="`excluded-weekday-time-${weekday.value}-${time.value}`"
                                        size="x-small"
                                        :color="weekdayTimeAvailable(weekday.value, time.value) ? 'success' : 'error'"
                                        variant="flat"
                                        filter
                                        @click="toggleWeekdayTime(weekday.value, time.value)">
                                        {{ time.value }}
                                    </v-chip>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div v-if="selectedConstraintSummary.length" class="robot-timetable-summary">
                        <v-chip
                            v-for="item in selectedConstraintSummary"
                            :key="item.key"
                            size="small"
                            color="secondary"
                            variant="tonal"
                            class="robot-timetable-summary__chip">
                            <strong>{{ item.label }}</strong>
                            <span>{{ item.value }}</span>
                        </v-chip>
                    </div>
                </div>

                <div class="robot-course-list">
                    <div class="robot-course-list__header">
                        <div class="robot-course-list__title">Kurse</div>
                        <v-chip size="x-small" color="primary" variant="tonal">
                            {{ selectedCourses.length }} / {{ availableCourses.length }}
                        </v-chip>
                        <v-chip size="x-small" color="secondary" variant="tonal">
                            {{ formatHours(selectedCoursesHours) }} Std.
                        </v-chip>
                        <v-spacer />
                        <v-btn
                            size="x-small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="mdi-check-all"
                            @click="selectAllCourses">
                            Alle auswählen
                        </v-btn>
                        <v-btn
                            size="x-small"
                            variant="tonal"
                            color="secondary"
                            prepend-icon="mdi-close-box-multiple-outline"
                            @click="deselectAllCourses">
                            Alle abwählen
                        </v-btn>
                    </div>

                    <v-alert v-if="!loading && !availableCourses.length" type="info" variant="tonal" class="mb-0">
                        Keine passenden Kurse gefunden.
                    </v-alert>

                    <v-alert v-else-if="!selectedCourses.length" type="warning" variant="tonal" class="mb-3">
                        Keine Kurse ausgewählt.
                    </v-alert>

                    <div v-if="availableCourses.length" class="robot-course-columns">
                        <v-table
                            v-for="(courseColumn, columnIndex) in availableCourseColumns"
                            :key="`course-column-${columnIndex}`"
                            density="compact"
                            class="robot-course-table">
                            <thead>
                                <tr>
                                    <th class="robot-course-table__select">Aktiv</th>
                                    <th>Code</th>
                                    <th>Bezeichnung</th>
                                    <th>Zweig</th>
                                    <th class="text-right">Std.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="course in courseColumn"
                                    :key="course.key"
                                    :class="{ 'robot-course-table__row--disabled': !courseSelected(course) }">
                                    <td class="robot-course-table__select">
                                        <v-checkbox
                                            :model-value="courseSelected(course)"
                                            :aria-label="`${course.code} auswählen`"
                                            density="compact"
                                            color="primary"
                                            hide-details
                                            @update:model-value="setCourseSelected(course, $event)" />
                                    </td>
                                    <td class="font-weight-bold">{{ course.code }}</td>
                                    <td>{{ course.name }}</td>
                                    <td>{{ course.branch }}</td>
                                    <td class="text-right">{{ formatHours(course.hours) }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </div>
                </div>

                <div class="robot-generator">
                    <div class="robot-generator__actions">
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-calendar-clock"
                            :disabled="loading || !selectedCourses.length"
                            @click="generateTimetables">
                            Stundenpläne erstellen
                        </v-btn>
                    </div>

                    <v-alert v-if="generationError" type="error" variant="tonal" class="mt-3 mb-0">
                        {{ generationError }}
                    </v-alert>

                    <div v-if="generatedTimetables.length" class="robot-generated">
                        <div class="robot-course-list__header">
                            <div class="robot-course-list__title">Stundenpläne</div>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ generatedTimetables.length }}
                            </v-chip>
                        </div>

                        <div
                            v-for="timetable in generatedTimetables"
                            :key="timetable.key"
                            class="robot-generated-timetable">
                            <div class="robot-generated-timetable__header">
                                <div class="robot-generated-timetable__title">
                                    Stundenplan {{ timetable.number }}
                                </div>
                                <v-chip
                                    v-if="timetable.problems.length"
                                    size="x-small"
                                    color="warning"
                                    variant="tonal">
                                    {{ timetable.problems.length }} Probleme
                                </v-chip>
                            </div>
                            <v-alert
                                v-if="timetable.problems.length"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="robot-generated-timetable__problems">
                                <ul class="robot-problems">
                                    <li v-for="problem in timetable.problems" :key="`${timetable.key}-${problem}`">
                                        <div>{{ problemSummary(problem) }}</div>
                                        <ul v-if="problemDetails(problem).length" class="robot-problems__details">
                                            <li
                                                v-for="detail in problemDetails(problem)"
                                                :key="`${timetable.key}-${problem}-${detail}`">
                                                {{ detail }}
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </v-alert>
                            <div
                                class="robot-generated-grid"
                                :style="{ '--robot-generated-weekdays': generatedWeekdays.length }">
                                <div class="robot-generated-cell robot-generated-cell--header">Std.</div>
                                <div
                                    v-for="weekday in generatedWeekdays"
                                    :key="`${timetable.key}-header-${weekday.value}`"
                                    class="robot-generated-cell robot-generated-cell--header">
                                    {{ weekday.shortTitle }}
                                </div>

                                <template
                                    v-for="time in generatedTimes"
                                    :key="`${timetable.key}-time-${time.value}`">
                                    <div class="robot-generated-cell robot-generated-cell--time">
                                        {{ time.shortTitle }}
                                    </div>
                                    <div
                                        v-for="weekday in generatedWeekdays"
                                        :key="`${timetable.key}-${weekday.value}-${time.value}`"
                                        class="robot-generated-cell"
                                        :class="{
                                            'robot-generated-cell--filled': timetable.slots[slotKey(weekday.value, time.value)],
                                            'robot-generated-cell--occasional': timetable.slots[slotKey(weekday.value, time.value)]?.isOccasional,
                                            'robot-generated-cell--affected': timetable.slots[slotKey(weekday.value, time.value)]?.isConflictPreview,
                                            'robot-generated-cell--conflict': generatedSlotConflicts(timetable.slots[slotKey(weekday.value, time.value)]).length,
                                        }">
                                        <template v-if="timetable.slots[slotKey(weekday.value, time.value)]">
                                            <template v-if="generatedSlotConflictBlocks(timetable.slots[slotKey(weekday.value, time.value)]).length">
                                                <div
                                                    v-for="block in generatedSlotConflictBlocks(timetable.slots[slotKey(weekday.value, time.value)])"
                                                    :key="`${timetable.key}-${weekday.value}-${time.value}-${block.key}`"
                                                    class="robot-generated-cell__conflict-block">
                                                    <div class="robot-generated-cell__code">
                                                        {{ block.code }}
                                                    </div>
                                                    <div
                                                        v-if="generatedSlotDateLabel(block)"
                                                        class="robot-generated-cell__date">
                                                        {{ generatedSlotDateLabel(block) }}
                                                    </div>
                                                    <div
                                                        v-if="generatedSlotDetails(block)"
                                                        class="robot-generated-cell__details">
                                                        {{ generatedSlotDetails(block) }}
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="robot-generated-cell__code">
                                                    {{ timetable.slots[slotKey(weekday.value, time.value)].code }}
                                                </div>
                                                <div
                                                    v-if="generatedSlotDateLabel(timetable.slots[slotKey(weekday.value, time.value)])"
                                                    class="robot-generated-cell__date">
                                                    {{ generatedSlotDateLabel(timetable.slots[slotKey(weekday.value, time.value)]) }}
                                                </div>
                                                <div
                                                    v-if="generatedSlotDetails(timetable.slots[slotKey(weekday.value, time.value)])"
                                                    class="robot-generated-cell__details">
                                                    {{ generatedSlotDetails(timetable.slots[slotKey(weekday.value, time.value)]) }}
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    data() {
        return {
            loading: false,
            error: '',
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            schoolHours: [],
            courseGroups: [],
            deselectedCourseKeys: [],
            subjectMappings: [],
            subjectRows: [],
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            },
        }
    },
    mounted() {
        this.loadSettings()
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        semesterOptions() {
            return Array.from({ length: 8 }, (value, index) => ({
                title: `Semester ${index + 1}`,
                value: index + 1,
            }))
        },
        religionOptions() {
            return [
                { title: 'ETH - Ethik', value: 'ETH' },
                { title: 'Rev - Religion evangelisch', value: 'Rev' },
                { title: 'Ris - Religion Islam', value: 'Ris' },
                { title: 'Rk - Religion katholisch', value: 'Rk' },
                { title: 'Ror - Religion orthodox', value: 'Ror' },
            ]
        },
        branchOptions() {
            return [
                { title: 'Wirtschaftskundlicher Zweig', value: 'wirtschaftskundlich' },
                { title: 'Gymnasialer Zweig', value: 'gymnasial' },
            ]
        },
        artsSubjectOptions() {
            return [
                { title: 'ME - Musikerziehung', value: 'ME' },
                { title: 'BE - Bildnerische Erziehung', value: 'BE' },
            ]
        },
        languageOptions() {
            return [
                { title: 'L - Latein', value: 'L' },
                { title: 'F - Französisch', value: 'F' },
                { title: 'S - Spanisch', value: 'S' },
            ]
        },
        weekdayOptions() {
            return [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Mittwoch', shortTitle: 'Mi', value: 3 },
                { title: 'Donnerstag', shortTitle: 'Do', value: 4 },
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
                { title: 'Samstag', shortTitle: 'Sa', value: 6 },
            ]
        },
        timeOptions() {
            const configuredHours = Array.isArray(this.schoolHours) ? this.schoolHours : []

            if (!configuredHours.length) return this.defaultTimeOptions()

            return configuredHours
                .map(schoolHour => ({
                    title: this.timeOptionTitle(schoolHour),
                    shortTitle: this.timeOptionShortTitle(schoolHour),
                    value: Number(schoolHour.hour),
                }))
                .filter(time => Number.isFinite(time.value))
                .sort((firstTime, secondTime) => firstTime.value - secondTime.value)
        },
        weekdayTimeOptions() {
            return this.weekdayOptions.flatMap(weekday =>
                this.timeOptions.map(time => ({
                    title: `${weekday.title}, ${time.title}`,
                    value: `${weekday.value}-${time.value}`,
                })),
            )
        },
        selectedSummary() {
            return [
                { key: 'semester', label: 'Semester', value: this.selection.semester },
                { key: 'religion', label: 'Ethik / Religion', value: this.selectedOptionTitle(this.religionOptions, this.selection.religion) },
                { key: 'branch', label: 'Zweig', value: this.selectedOptionTitle(this.branchOptions, this.selection.branch) },
                { key: 'artsSubject', label: 'ME / BE', value: this.selectedOptionTitle(this.artsSubjectOptions, this.selection.artsSubject) },
                { key: 'language', label: 'Sprache', value: this.selectedOptionTitle(this.languageOptions, this.selection.language) },
            ]
        },
        selectedConstraintSummary() {
            return [
                {
                    key: 'unavailableWeekdays',
                    label: 'Nicht möglich',
                    value: this.selectedOptionTitles(this.weekdayOptions, this.unavailableWeekdays),
                },
                {
                    key: 'excludedWeekdayTimes',
                    label: 'Keine Zeiten',
                    value: this.selectedOptionTitles(this.weekdayTimeOptions, this.constraints.excludedWeekdayTimes),
                },
                {
                    key: 'unavailableTimes',
                    label: 'Nicht verwendete Stunden',
                    value: this.selectedOptionTitles(this.timeOptions, this.unavailableTimes),
                },
            ].filter(item => item.value)
        },
        availableCourses() {
            return this.subjectRows
                .filter(subject => subject.is_active !== false)
                .filter(subject => Number(subject.semester) === Number(this.selection.semester))
                .filter(subject => this.subjectMatchesSelectedBranch(subject))
                .filter(subject => this.subjectMatchesSelectedChoices(subject))
                .flatMap(subject => this.selectedCoursesFromSubject(subject))
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        availableCourseColumns() {
            const splitIndex = Math.ceil(this.availableCourses.length / 2)

            return [
                this.availableCourses.slice(0, splitIndex),
                this.availableCourses.slice(splitIndex),
            ].filter(courseColumn => courseColumn.length)
        },
        selectedCourses() {
            return this.availableCourses.filter(course => this.courseSelected(course))
        },
        selectedCoursesHours() {
            return this.selectedCourses.reduce((total, course) => total + Number(course.hours || 0), 0)
        },
        configuredCourseGroups() {
            return Array.isArray(this.courseGroups) ? this.courseGroups : []
        },
        unavailableWeekdays() {
            return this.weekdayOptions
                .map(weekday => weekday.value)
                .filter(weekday => !this.constraintSelected('availableWeekdays', weekday))
        },
        unavailableTimes() {
            return this.timeOptions
                .map(time => time.value)
                .filter(time => !this.constraintSelected('availableTimes', time))
        },
        allowedTimetableSlots() {
            return this.weekdayOptions
                .filter(weekday => this.constraintSelected('availableWeekdays', weekday.value))
                .flatMap(weekday => this.timeOptions
                    .filter(time => this.constraintSelected('availableTimes', time.value))
                    .filter(time => !this.constraintSelected('excludedWeekdayTimes', this.weekdayTimeValue(weekday.value, time.value)))
                    .map(time => ({
                        key: this.slotKey(weekday.value, time.value),
                        weekday: weekday.value,
                        time: time.value,
                    })))
                .sort((firstSlot, secondSlot) => {
                    if (firstSlot.time !== secondSlot.time) return firstSlot.time - secondSlot.time

                    return firstSlot.weekday - secondSlot.weekday
                })
        },
        generatedWeekdays() {
            return this.weekdayOptions.filter(weekday =>
                Number(weekday.value) <= 5
                    || (Number(weekday.value) === 6 && this.constraintSelected('availableWeekdays', weekday.value)),
            )
        },
        generatedTimes() {
            const generatedTimeValues = new Set(this.generatedTimetables.flatMap(timetable =>
                Object.values(timetable.slots).map(slot => Number(slot?.courseGroup?.hour)),
            ).filter(Number.isFinite))
            if (this.generatedTimetables.length && !generatedTimeValues.size) {
                return this.timeOptions.filter(time => this.constraintSelected('availableTimes', time.value))
            }

            const configuredTimes = this.timeOptions.filter(time => generatedTimeValues.has(Number(time.value)))
            const configuredTimeValues = new Set(configuredTimes.map(time => Number(time.value)))
            const missingTimes = [...generatedTimeValues]
                .filter(time => !configuredTimeValues.has(time))
                .map(time => ({
                    title: `${time}. Stunde`,
                    shortTitle: `${time}. Stunde`,
                    value: time,
                }))

            return [...configuredTimes, ...missingTimes]
                .sort((firstTime, secondTime) => firstTime.value - secondTime.value)
        },
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.loadSettings()
        },
        selection: {
            deep: true,
            handler() {
                this.deselectedCourseKeys = []
                this.clearGeneratedTimetables()
            },
        },
        constraints: {
            deep: true,
            handler() {
                this.clearGeneratedTimetables()
            },
        },
    },
    methods: {
        async loadSettings() {
            this.loading = true
            this.error = ''
            this.generatedTimetables = []
            this.generationError = ''
            this.generationProblems = []
            this.deselectedCourseKeys = []
            try {
                const [settingsResponse, schoolHoursResponse, courseGroupsResponse] = await Promise.all([
                    axios.get('/api/admin/students-timetables/subjects-overview-settings'),
                    axios.get('/api/admin/students-timetables/school-hours'),
                    axios.get('/api/admin/students-timetables/course-groups'),
                ])

                this.subjectRows = settingsResponse.data.data?.subjects || []
                this.subjectMappings = settingsResponse.data.data?.mappings || []
                this.schoolHours = schoolHoursResponse.data?.data || []
                this.courseGroups = courseGroupsResponse.data?.data || []
                this.syncAvailableTimes()
            } catch {
                this.schoolHours = []
                this.courseGroups = []
                this.subjectMappings = []
                this.subjectRows = []
                this.error = 'Die Kurse konnten nicht geladen werden.'
            } finally {
                this.loading = false
            }
        },
        selectedOptionTitle(options, value) {
            return options.find(option => option.value === value)?.title || value
        },
        selectedOptionTitles(options, values) {
            if (!values.length) return ''

            return values
                .map(value => this.selectedOptionTitle(options, value))
                .join(', ')
        },
        constraintSelected(key, value) {
            return this.constraints[key].some(item => String(item) === String(value))
        },
        toggleConstraint(key, value) {
            const selectedIndex = this.constraints[key].findIndex(item => String(item) === String(value))

            if (selectedIndex === -1) {
                this.constraints[key].push(value)

                return
            }

            this.constraints[key].splice(selectedIndex, 1)
        },
        weekdayTimeValue(weekday, time) {
            return `${weekday}-${time}`
        },
        slotKey(weekday, time) {
            return `${weekday}-${time}`
        },
        weekdayTimeAvailable(weekday, time) {
            return this.constraintSelected('availableWeekdays', weekday)
                && this.constraintSelected('availableTimes', time)
                && !this.constraintSelected('excludedWeekdayTimes', this.weekdayTimeValue(weekday, time))
        },
        toggleWeekdayTime(weekday, time) {
            if (!this.constraintSelected('availableWeekdays', weekday) || !this.constraintSelected('availableTimes', time)) {
                return
            }

            this.toggleConstraint('excludedWeekdayTimes', this.weekdayTimeValue(weekday, time))
        },
        generateTimetables() {
            this.generationError = ''
            this.generationProblems = []
            this.generatedTimetables = []

            const selectedCourses = this.selectedCourses
            const candidateSets = this.timetableCandidateSets(selectedCourses)

            if (!selectedCourses.length) {
                this.generationError = 'Es gibt keine Kurse für diese Auswahl.'

                return
            }

            if (!this.configuredCourseGroups.length) {
                const problems = selectedCourses.map(course =>
                    this.candidateSetProblemMessage({
                        course,
                        occasionalCourseGroups: [],
                        matchingCourseGroupsCount: 0,
                        regularCourseGroupsCount: 0,
                        hasOnlyOccasionalMatches: false,
                    }),
                )

                this.generationProblems = this.uniqueProblems(problems)
                this.generatedTimetables = [{
                    key: 'generated-1',
                    number: 1,
                    slots: {},
                    problems: this.generationProblems,
                }]
                return
            }

            const combinations = []

            this.buildTimetableCombinations(
                [...candidateSets].sort((firstSet, secondSet) => firstSet.options.length - secondSet.options.length),
                0,
                {},
                new Set(),
                [],
                new Set(),
                combinations,
            )

            if (!combinations.length) {
                const problems = candidateSets.map(candidateSet => this.candidateSetProblemMessage(candidateSet))
                combinations.push({
                    slots: {},
                    problems,
                    scheduledCourseCount: 0,
                    scheduledSlotCount: 0,
                })
            }

            const rankedCombinations = this.visibleTimetableCombinations(this.rankTimetableCombinations(combinations))
            if (!rankedCombinations.length) {
                this.generationError = 'Es gibt keinen Stundenplan ohne fehlende Kurse.'

                return
            }

            const bestCombinations = rankedCombinations.filter(combination =>
                combination.scheduledCourseCount === rankedCombinations[0].scheduledCourseCount
                    && combination.scheduledSlotCount === rankedCombinations[0].scheduledSlotCount,
            )

            this.generatedTimetables = bestCombinations.map((combination, index) => ({
                key: `generated-${index + 1}`,
                number: index + 1,
                ...this.timetableWithOccasionalSlots(combination, candidateSets),
            }))
            this.generationProblems = this.uniqueProblems(this.generatedTimetables.flatMap(timetable => timetable.problems))
        },
        clearGeneratedTimetables() {
            this.generationError = ''
            this.generationProblems = []
            this.generatedTimetables = []
        },
        generatedSlotDetails(slot) {
            const courseGroup = slot?.courseGroup || {}
            const alternativeLabels = this.generatedSlotAlternativeLabels(slot)

            if (alternativeLabels.length > 1) {
                return alternativeLabels.join('\noder\n')
            }

            const label = String(slot?.sourceLabel || this.courseGroupOptionLabel(courseGroup) || '').trim()
            const teacher = String(courseGroup?.teacher || '').trim()
            const rooms = this.courseGroupRoomsLabel(courseGroup)

            return [label, teacher, rooms]
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
                .join(' · ')
        },
        generatedSlotAlternativeLabels(slot) {
            return Array.isArray(slot?.alternativeLabels)
                ? slot.alternativeLabels
                    .map(label => String(label || '').trim())
                    .filter(Boolean)
                    .filter((label, index, labels) => labels.indexOf(label) === index)
                : []
        },
        generatedSlotConflicts(slot) {
            return Array.isArray(slot?.conflicts)
                ? slot.conflicts
                    .toSorted((firstConflict, secondConflict) =>
                        String(firstConflict.sortValue || '').localeCompare(String(secondConflict.sortValue || '')),
                    )
                    .map(conflict => conflict.label)
                    .filter(Boolean)
                : []
        },
        generatedSlotConflictBlocks(slot) {
            if (!Array.isArray(slot?.conflicts) || !slot.conflicts.length) return []

            const conflictBlocks = slot.conflicts
                .toSorted((firstConflict, secondConflict) =>
                    String(firstConflict.sortValue || '').localeCompare(String(secondConflict.sortValue || '')),
                )
                .map(conflict => this.generatedSlotConflictBlock(conflict))
                .filter(block => block.code || this.generatedSlotDetails(block))

            return [
                ...conflictBlocks,
                this.generatedSlotConflictBlock({
                    ...slot,
                    sortValue: `zz-${slot?.code || ''}`,
                }),
            ]
                .filter(block => block.code || this.generatedSlotDetails(block))
        },
        generatedSlotConflictBlock(source) {
            return {
                key: source?.key || source?.label || [
                    source?.code,
                    source?.sourceLabel,
                    source?.courseGroup?.weekday,
                    source?.courseGroup?.hour,
                ].filter(Boolean).join('|'),
                code: source?.code || '',
                name: source?.name || '',
                sourceLabel: source?.sourceLabel || this.courseGroupSourceLabel(source?.courseGroup),
                alternativeLabels: source?.alternativeLabels || [],
                courseGroup: source?.courseGroup || {},
                isOccasional: Boolean(source?.isOccasional),
            }
        },
        courseGroupRoomsLabel(courseGroup) {
            if (Array.isArray(courseGroup?.rooms)) {
                return courseGroup.rooms
                    .map(room => String(room || '').trim())
                    .filter(Boolean)
                    .join(', ')
            }

            return String(courseGroup?.room || courseGroup?.rooms || '').trim()
        },
        courseSelected(course) {
            return !this.deselectedCourseKeys.includes(course.key)
        },
        setCourseSelected(course, selected) {
            const courseKey = course.key
            const deselectedIndex = this.deselectedCourseKeys.indexOf(courseKey)

            if (selected && deselectedIndex !== -1) {
                this.deselectedCourseKeys.splice(deselectedIndex, 1)
                this.clearGeneratedTimetables()

                return
            }

            if (!selected && deselectedIndex === -1) {
                this.deselectedCourseKeys.push(courseKey)
                this.clearGeneratedTimetables()
            }
        },
        selectAllCourses() {
            this.deselectedCourseKeys = []
            this.clearGeneratedTimetables()
        },
        deselectAllCourses() {
            this.deselectedCourseKeys = this.availableCourses.map(course => course.key)
            this.clearGeneratedTimetables()
        },
        generatedSlotDateLabel(slot) {
            if (!slot?.isOccasional) return ''

            return this.courseGroupDatesLabel(slot.courseGroup)
        },
        timetableCandidateSets(courses) {
            return courses.map(course => {
                const matchingCourseGroups = this.configuredCourseGroups
                    .filter(courseGroup => this.courseGroupMatchesCourse(courseGroup, course))
                const regularCourseGroups = matchingCourseGroups
                    .filter(courseGroup => !this.isOccasionalCourseGroup(courseGroup))
                const occasionalCourseGroups = matchingCourseGroups
                    .filter(courseGroup => this.isOccasionalCourseGroup(courseGroup))

                return {
                    course,
                    options: this.timetableOptionsForCourse(course, regularCourseGroups),
                    occasionalOptions: this.timetableOptionsForCourse(course, occasionalCourseGroups, false),
                    occasionalCourseGroups,
                    hasOnlyOccasionalMatches: matchingCourseGroups.length > 0 && regularCourseGroups.length === 0,
                    matchingCourseGroupsCount: matchingCourseGroups.length,
                    regularCourseGroupsCount: regularCourseGroups.length,
                }
            })
        },
        timetableOptionsForCourse(course, courseGroups = null, completeOptions = true) {
            const sourceCourseGroups = Array.isArray(courseGroups) ? courseGroups : this.configuredCourseGroups
            const entriesByLabel = sourceCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourse(courseGroup, course))
                .reduce((entries, courseGroup) => {
                    const label = this.courseGroupOptionLabel(courseGroup)

                    entries[label] ??= {
                        key: `${course.code}-${label}`,
                        label,
                        course,
                        courseGroups: [],
                    }

                    entries[label].courseGroups.push(courseGroup)

                    return entries
                }, {})

            const entries = Object.values(entriesByLabel)
                .map(option => ({
                    ...option,
                    courseGroups: this.uniqueCourseGroupsBySlot(option.courseGroups),
                }))
                .filter(option => option.courseGroups.length > 0)
                .filter(option => option.courseGroups.every(courseGroup =>
                    this.weekdayTimeAvailable(Number(courseGroup.weekday), Number(courseGroup.hour)),
                ))
                .sort((firstOption, secondOption) => this.compareTimetableOptions(firstOption, secondOption))

            if (!completeOptions) return this.mergeTimetableOptionsBySlots(entries)

            return this.mergeTimetableOptionsBySlots(this.completeTimetableOptionsForCourse(course, entries))
        },
        isOccasionalCourseGroup(courseGroup) {
            const datesCount = this.courseGroupDatesCount(courseGroup)

            return Number.isFinite(datesCount) && datesCount > 0 && datesCount <= 2
        },
        courseGroupDatesCount(courseGroup) {
            const explicitDatesCount = Number(courseGroup?.dates_count)
            if (Number.isFinite(explicitDatesCount)) return explicitDatesCount

            if (Array.isArray(courseGroup?.dates)) return courseGroup.dates.filter(Boolean).length

            return null
        },
        completeTimetableOptionsForCourse(course, entries) {
            const requiredSlotCount = Math.max(1, Math.round(Number(course.hours || 0) || 0))
            const exactOptions = entries.filter(entry => entry.courseGroups.length === requiredSlotCount)

            if (exactOptions.length) return exactOptions

            const combinedOptions = []
            this.buildCourseEntryCombinations(course, entries, requiredSlotCount, 0, [], combinedOptions)

            if (combinedOptions.length) {
                return combinedOptions.sort((firstOption, secondOption) =>
                    this.compareTimetableOptions(firstOption, secondOption),
                )
            }

            if (this.entriesContainAlternativeGroupChoices(entries)) {
                return entries
            }

            return entries
                .filter(entry => entry.courseGroups.length >= requiredSlotCount)
                .sort((firstOption, secondOption) => this.compareTimetableOptions(firstOption, secondOption))
        },
        buildCourseEntryCombinations(course, entries, requiredSlotCount, entryIndex, selectedEntries, combinations) {
            const selectedSlotCount = selectedEntries
                .reduce((total, entry) => total + entry.courseGroups.length, 0)

            if (selectedSlotCount === requiredSlotCount) {
                combinations.push(this.combinedCourseOption(course, selectedEntries))

                return
            }

            if (selectedSlotCount > requiredSlotCount || entryIndex >= entries.length) return

            for (let index = entryIndex; index < entries.length; index++) {
                const entry = entries[index]
                if (this.courseEntriesOverlap(selectedEntries, entry) || this.courseEntriesAreAlternatives(selectedEntries, entry)) {
                    continue
                }

                this.buildCourseEntryCombinations(
                    course,
                    entries,
                    requiredSlotCount,
                    index + 1,
                    [...selectedEntries, entry],
                    combinations,
                )
            }
        },
        combinedCourseOption(course, entries) {
            const label = entries.map(entry => entry.label).join(' + ')

            return {
                key: entries.map(entry => entry.key).join('|'),
                label,
                alternativeLabels: [label],
                slotAlternativeLabels: this.combinedCourseOptionSlotAlternativeLabels(entries),
                course,
                courseGroups: entries.flatMap(entry => entry.courseGroups),
            }
        },
        combinedCourseOptionSlotAlternativeLabels(entries) {
            return entries.reduce((slotLabels, entry) => ({
                ...slotLabels,
                ...this.optionSlotAlternativeLabels(entry),
            }), {})
        },
        mergeTimetableOptionsBySlots(options) {
            const optionsBySlotSignature = options.reduce((mergedOptions, option) => {
                const signature = this.timetableOptionSlotSignature(option)

                mergedOptions[signature] ??= {
                    ...option,
                    alternativeLabels: [],
                    slotAlternativeLabels: {},
                }

                mergedOptions[signature].alternativeLabels = this.uniqueProblems([
                    ...mergedOptions[signature].alternativeLabels,
                    ...(option.alternativeLabels || [option.label]),
                ])
                Object.entries(this.optionSlotAlternativeLabels(option)).forEach(([slotKey, labels]) => {
                    mergedOptions[signature].slotAlternativeLabels[slotKey] = this.uniqueProblems([
                        ...(mergedOptions[signature].slotAlternativeLabels[slotKey] || []),
                        ...labels,
                    ])
                })

                return mergedOptions
            }, {})

            return Object.values(optionsBySlotSignature)
                .map(option => ({
                    ...option,
                    label: option.alternativeLabels[0] || option.label,
                }))
                .sort((firstOption, secondOption) => this.compareTimetableOptions(firstOption, secondOption))
        },
        optionSlotAlternativeLabels(option) {
            if (option.slotAlternativeLabels) return option.slotAlternativeLabels

            return option.courseGroups.reduce((labelsBySlot, courseGroup) => {
                labelsBySlot[this.slotKey(courseGroup.weekday, courseGroup.hour)] = option.alternativeLabels || [option.label]

                return labelsBySlot
            }, {})
        },
        timetableOptionSlotSignature(option) {
            return option.courseGroups
                .map(courseGroup => this.slotKey(courseGroup.weekday, courseGroup.hour))
                .sort()
                .join('|')
        },
        courseEntriesOverlap(selectedEntries, nextEntry) {
            const usedSlotKeys = new Set(selectedEntries.flatMap(entry =>
                entry.courseGroups.map(courseGroup => this.slotKey(courseGroup.weekday, courseGroup.hour)),
            ))

            return nextEntry.courseGroups.some(courseGroup =>
                usedSlotKeys.has(this.slotKey(courseGroup.weekday, courseGroup.hour)),
            )
        },
        courseEntriesAreAlternatives(selectedEntries, nextEntry) {
            const nextAlternativeKey = this.courseEntryAlternativeKey(nextEntry)
            if (!nextAlternativeKey) return false

            return selectedEntries.some(entry => this.courseEntryAlternativeKey(entry) === nextAlternativeKey)
        },
        entriesContainAlternativeGroupChoices(entries) {
            const alternativeKeys = entries
                .map(entry => this.courseEntryAlternativeKey(entry))
                .filter(Boolean)

            return alternativeKeys.some((alternativeKey, index) => alternativeKeys.indexOf(alternativeKey) !== index)
        },
        courseEntryAlternativeKey(entry) {
            const label = String(entry?.label || '')
                .trim()
                .toLocaleUpperCase('de-AT')

            const normalizedLabel = label.replace(/\s+/gu, '')

            if (/(^|-)GRP\d+(?=-|$)/u.test(normalizedLabel)) {
                return normalizedLabel
                    .replace(/(^|-)GRP\d+(?=-|$)/u, '$1')
                    .replace(/-+/gu, '-')
                    .replace(/^-|-$/gu, '')
            }

            const leadingCourseCode = normalizedLabel.match(/^([A-ZÄÖÜ]+[0-9]+)(?=-)/u)?.[1] || ''

            return leadingCourseCode
        },
        courseGroupMatchesCourse(courseGroup, course) {
            const courseAliases = this.courseCodeAliases(course)
            if (!courseAliases.length) return false

            const courseGroupCodes = this.courseGroupCodes(courseGroup)

            if (!courseAliases.some(courseAlias => courseGroupCodes.includes(courseAlias))) {
                return false
            }

            const leadingCourseCodes = this.courseGroupLeadingCodes(courseGroup)

            if (this.courseGroupHasConflictingModuleCode(leadingCourseCodes, courseAliases)) {
                return false
            }

            return true
        },
        courseGroupHasConflictingModuleCode(leadingCourseCodes, courseAliases) {
            const aliasesWithModule = courseAliases
                .map(alias => this.courseCodeModuleParts(alias))
                .filter(parts => parts.module)

            if (!aliasesWithModule.length) return false

            return leadingCourseCodes
                .map(code => this.courseCodeModuleParts(code))
                .filter(parts => parts.module)
                .some(parts => {
                    const aliasesWithSameBase = aliasesWithModule.filter(aliasParts => aliasParts.base === parts.base)

                    return aliasesWithSameBase.length
                        && !aliasesWithSameBase.some(aliasParts => aliasParts.module === parts.module)
                })
        },
        courseCodeModuleParts(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-ZÄÖÜ]+)(\d+)$/u)

            if (!match) {
                return {
                    base: this.normalizedCourseModuleBase(normalizedValue),
                    module: '',
                }
            }

            return {
                base: this.normalizedCourseModuleBase(match[1]),
                module: match[2],
            }
        },
        normalizedCourseModuleBase(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const mapping = this.activeSubjectMappings().find(subjectMapping =>
                [
                    subjectMapping?.json_subject,
                    subjectMapping?.tt_subject,
                ]
                    .map(subject => this.normalizedCourseCode(subject))
                    .includes(normalizedValue),
            )

            return this.normalizedCourseCode(mapping?.json_subject) || normalizedValue
        },
        courseGroupLeadingCodes(courseGroup) {
            return [
                courseGroup?.class_name,
                courseGroup?.display_label,
                courseGroup?.title,
            ]
                .flatMap(value => this.leadingCourseCodesFromValue(value))
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        courseGroupCodes(courseGroup) {
            return [
                ...this.courseGroupLeadingCodes(courseGroup),
                ...[
                    courseGroup?.course,
                    courseGroup?.subject,
                ].flatMap(value => this.courseCodeTokensFromValue(value)),
            ]
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        courseCodeAliases(course) {
            return [
                course?.ttCode,
                ...(Array.isArray(course?.ttCodes) ? course.ttCodes : []),
                course?.code,
                this.defaultTimetableCodeAlias(course?.code),
            ]
                .flatMap(value => this.courseCodeAliasParts(value))
                .flatMap(value => [
                    value,
                    this.defaultTimetableCodeAlias(value),
                ])
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        defaultTimetableCodeAlias(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-ZÄÖÜ]+)(\d*)$/u)
            if (!match) return ''

            const aliases = {
                GS: 'GPB',
                GW: 'GWB',
                ME: 'MU',
                LPT: 'LET',
            }
            const mappedBase = aliases[match[1]]

            return mappedBase ? `${mappedBase}${match[2] || ''}` : ''
        },
        courseCodeAliasParts(value) {
            const normalizedValue = String(value || '').trim()
            if (!normalizedValue) return []

            return normalizedValue
                .split('/')
                .map(part => part.trim())
                .filter(Boolean)
        },
        leadingCourseCodesFromValue(value) {
            const firstSegment = String(value || '')
                .split(/\s+-\s+|[-\s]/u)[0]
                ?.trim() || ''

            return this.courseCodeTokensFromValue(firstSegment)
        },
        courseCodeTokensFromValue(value) {
            const text = String(value || '')

            return [...text.matchAll(/[A-Za-zÄÖÜäöüß]+[0-9]*/gu)]
                .map(match => match[0])
        },
        normalizedCourseCode(value) {
            return String(value || '')
                .trim()
                .toLocaleUpperCase('de-AT')
                .replace(/\s+/gu, '')
        },
        courseGroupOptionLabel(courseGroup) {
            return String(
                courseGroup?.class_name
                || courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || 'Ohne Bezeichnung',
            )
        },
        courseGroupSourceLabel(courseGroup) {
            return String(
                courseGroup?.class_name
                || courseGroup?.display_label
                || courseGroup?.title
                || '',
            ).trim()
        },
        uniqueCourseGroupsBySlot(courseGroups) {
            return Object.values(courseGroups.reduce((groups, courseGroup) => {
                const key = this.slotKey(courseGroup.weekday, courseGroup.hour)

                groups[key] ??= courseGroup

                return groups
            }, {}))
                .sort((firstGroup, secondGroup) => {
                    if (Number(firstGroup.weekday) !== Number(secondGroup.weekday)) {
                        return Number(firstGroup.weekday) - Number(secondGroup.weekday)
                    }

                    return Number(firstGroup.hour) - Number(secondGroup.hour)
                })
        },
        compareTimetableOptions(firstOption, secondOption) {
            const firstStart = this.optionFirstSlotValue(firstOption)
            const secondStart = this.optionFirstSlotValue(secondOption)

            if (firstStart !== secondStart) return firstStart - secondStart

            return firstOption.label.localeCompare(secondOption.label, 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        optionFirstSlotValue(option) {
            const firstCourseGroup = option.courseGroups[0] || {}

            return (Number(firstCourseGroup.weekday || 99) * 100) + Number(firstCourseGroup.hour || 99)
        },
        buildTimetableCombinations(
            candidateSets,
            candidateSetIndex,
            assignedSlots,
            usedSlotKeys,
            problems,
            scheduledCourseKeys,
            combinations,
        ) {
            if (candidateSetIndex >= candidateSets.length) {
                combinations.push({
                    slots: { ...assignedSlots },
                    problems: this.uniqueProblems(problems),
                    scheduledCourseCount: scheduledCourseKeys.size,
                    scheduledSlotCount: Object.values(assignedSlots)
                        .filter(slot => !slot?.isConflictPreview)
                        .length,
                })

                return
            }

            const candidateSet = candidateSets[candidateSetIndex]
            if (!candidateSet.options.length) {
                this.buildTimetableCombinations(
                    candidateSets,
                    candidateSetIndex + 1,
                    assignedSlots,
                    usedSlotKeys,
                    [...problems, this.candidateSetProblemMessage(candidateSet)],
                    scheduledCourseKeys,
                    combinations,
                )

                return
            }

            let placedOption = false

            candidateSet.options.forEach(option => {
                if (this.optionHasUsedSlot(option, usedSlotKeys)) {
                    return
                }

                placedOption = true
                const nextAssignedSlots = { ...assignedSlots }
                const nextUsedSlotKeys = new Set(usedSlotKeys)
                const nextScheduledCourseKeys = new Set(scheduledCourseKeys)
                nextScheduledCourseKeys.add(candidateSet.course.key || candidateSet.course.code)

                option.courseGroups.forEach(courseGroup => {
                    const key = this.slotKey(courseGroup.weekday, courseGroup.hour)

                    nextAssignedSlots[key] = {
                        ...option.course,
                        sourceLabel: option.label,
                        alternativeLabels: this.optionAlternativeLabelsForSlot(option, key),
                        courseGroup,
                    }
                    nextUsedSlotKeys.add(key)
                })

                this.buildTimetableCombinations(
                    candidateSets,
                    candidateSetIndex + 1,
                    nextAssignedSlots,
                    nextUsedSlotKeys,
                    problems,
                    nextScheduledCourseKeys,
                    combinations,
                )
            })

            const assignedSlotsWithConflicts = this.assignedSlotsWithCourseConflicts(
                candidateSet,
                assignedSlots,
                usedSlotKeys,
            )

            this.buildTimetableCombinations(
                candidateSets,
                candidateSetIndex + 1,
                assignedSlotsWithConflicts,
                usedSlotKeys,
                [...problems, this.candidateSetConflictProblemMessage(candidateSet, assignedSlots, usedSlotKeys, placedOption)],
                scheduledCourseKeys,
                combinations,
            )
        },
        rankTimetableCombinations(combinations) {
            return combinations
                .map(combination => ({
                    ...combination,
                    problems: this.uniqueProblems(combination.problems),
                }))
                .sort((firstCombination, secondCombination) => {
                    if (firstCombination.scheduledCourseCount !== secondCombination.scheduledCourseCount) {
                        return secondCombination.scheduledCourseCount - firstCombination.scheduledCourseCount
                    }

                    if (firstCombination.scheduledSlotCount !== secondCombination.scheduledSlotCount) {
                        return secondCombination.scheduledSlotCount - firstCombination.scheduledSlotCount
                    }

                    return firstCombination.problems.length - secondCombination.problems.length
                })
        },
        visibleTimetableCombinations(combinations) {
            return combinations.filter(combination => !this.timetableCombinationHasMissingCourse(combination))
        },
        timetableCombinationHasMissingCourse(combination) {
            return this.uniqueProblems(combination?.problems || [])
                .some(problem => this.isMissingCourseVariantProblem(problem))
        },
        isMissingCourseVariantProblem(problem) {
            return String(problem || '').includes(': in dieser Variante nicht eingeplant, damit andere Kurse Platz haben.')
        },
        timetableWithOccasionalSlots(combination, candidateSets) {
            const slots = { ...combination.slots }
            const problems = [...combination.problems]
            const conflicts = {}

            candidateSets.forEach(candidateSet => {
                candidateSet.occasionalOptions.forEach(option => {
                    option.courseGroups.forEach(courseGroup => {
                        const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                        const existingSlot = slots[key]

                        if (existingSlot) {
                            this.trackOccasionalCourseConflict(conflicts, candidateSet.course, courseGroup, existingSlot)

                            return
                        }

                        slots[key] = {
                            ...candidateSet.course,
                            sourceLabel: option.label,
                            alternativeLabels: this.optionAlternativeLabelsForSlot(option, key),
                            courseGroup,
                            isOccasional: true,
                        }
                    })
                })
            })

            return {
                slots,
                problems: this.uniqueProblems([
                    ...problems,
                    ...Object.values(conflicts)
                        .map(conflict => this.occasionalCourseConflictProblemMessage(conflict)),
                ]),
            }
        },
        candidateSetProblemMessage(candidateSet) {
            const courseLabel = this.courseProblemLabel(candidateSet.course)

            if (candidateSet.hasOnlyOccasionalMatches) {
                const datesLabel = this.candidateSetOccasionalDatesLabel(candidateSet)

                return `${courseLabel}: nur Einzeltermine${datesLabel ? ` (${datesLabel})` : ''}, orange im Stundenplan markiert.`
            }

            if (!candidateSet.matchingCourseGroupsCount) {
                return `${courseLabel}: keine passenden TT-Stunden gefunden.`
            }

            if (candidateSet.regularCourseGroupsCount > 0) {
                return `${courseLabel}: TT-Stunden liegen außerhalb deiner Zeitvorgaben.`
            }

            return `${courseLabel}: konnte nicht eingeplant werden.`
        },
        candidateSetOccasionalDatesLabel(candidateSet) {
            return (candidateSet.occasionalCourseGroups || [])
                .flatMap(courseGroup => this.courseGroupDateTimeEntries(courseGroup))
                .sort((firstEntry, secondEntry) => firstEntry.sortValue.localeCompare(secondEntry.sortValue))
                .map(entry => entry.label)
                .filter((label, index, labels) => labels.indexOf(label) === index)
                .join(', ')
        },
        trackOccasionalCourseConflict(conflicts, course, courseGroup, existingSlot) {
            const datesLabel = this.courseGroupDatesLabel(courseGroup)
            const key = [
                this.courseProblemLabel(course),
                datesLabel,
                this.courseProblemLabel(existingSlot),
            ].join('|')

            conflicts[key] ??= {
                course,
                existingSlot,
                dateTimeLabels: [],
            }

            const dateTimeEntries = this.courseGroupDateTimeEntries(courseGroup)
            const entries = dateTimeEntries.length
                ? dateTimeEntries
                : [{ label: this.courseGroupTimeRangeLabel(courseGroup), date: null }]
            const existingLabel = this.courseProblemLabel(existingSlot)

            entries
                .filter(entry => entry.label)
                .forEach(entry => {
                    this.trackGeneratedSlotConflict(existingSlot, course, courseGroup, entry.date)

                    const existingDateTimeLabel = this.courseGroupDateTimeLabelForDate(
                        existingSlot?.courseGroup || courseGroup,
                        entry.date,
                    )
                    const conflictLabel = [existingLabel, existingDateTimeLabel].filter(Boolean).join(' ')
                    const detailLabel = conflictLabel ? `${entry.label} <-> ${conflictLabel}` : entry.label

                    if (!conflicts[key].dateTimeLabels.includes(detailLabel)) {
                        conflicts[key].dateTimeLabels.push(detailLabel)
                    }
                })
        },
        trackGeneratedSlotConflict(slot, course, courseGroup, date) {
            slot.conflicts ??= []

            const courseLabel = this.courseProblemLabel(course)
            const dateTimeLabel = this.courseGroupDateTimeLabelForDate(courseGroup, date)
            const label = [courseLabel, dateTimeLabel].filter(Boolean).join(' ')

            if (!label || slot.conflicts.some(conflict => conflict.label === label)) {
                return
            }

            slot.conflicts.push({
                label,
                sortValue: `${date || ''}-${String(courseGroup?.hour || '').padStart(2, '0')}-${courseLabel}`,
                code: course?.code || '',
                name: course?.name || '',
                sourceLabel: this.courseGroupSourceLabel(courseGroup),
                courseGroup,
            })
        },
        assignedSlotsWithCourseConflicts(candidateSet, assignedSlots, usedSlotKeys) {
            const nextAssignedSlots = { ...assignedSlots }

            candidateSet.options.forEach(option => {
                if (!this.optionHasUsedSlot(option, usedSlotKeys)) {
                    return
                }

                const conflictEntries = this.optionCourseConflictEntries(option, assignedSlots, usedSlotKeys)
                if (!conflictEntries.length) {
                    return
                }

                option.courseGroups.forEach(courseGroup => {
                    const key = this.slotKey(courseGroup.weekday, courseGroup.hour)

                    if (usedSlotKeys.has(key)) {
                        if (!this.assignedSlotConfrontsCourseGroup(assignedSlots, courseGroup)) return

                        const existingSlot = nextAssignedSlots[key]
                        nextAssignedSlots[key] = {
                            ...existingSlot,
                            conflicts: [...(existingSlot.conflicts || [])],
                        }
                        this.trackGeneratedSlotConflict(nextAssignedSlots[key], candidateSet.course, courseGroup)

                        return
                    }

                    const existingSlot = nextAssignedSlots[key]
                    if (existingSlot && !existingSlot.isConflictPreview) {
                        return
                    }

                    nextAssignedSlots[key] = {
                        ...option.course,
                        sourceLabel: option.label,
                        alternativeLabels: this.optionAlternativeLabelsForSlot(option, key),
                        courseGroup,
                        isConflictPreview: true,
                        conflicts: [],
                    }
                })
            })

            return nextAssignedSlots
        },
        optionCourseConflictEntries(option, assignedSlots, usedSlotKeys) {
            return option.courseGroups
                .filter(courseGroup => usedSlotKeys.has(this.slotKey(courseGroup.weekday, courseGroup.hour)))
                .filter(courseGroup => this.assignedSlotConfrontsCourseGroup(assignedSlots, courseGroup))
                .map(courseGroup => {
                    const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                    const assignedSlot = assignedSlots[key]
                    const label = [
                        this.courseProblemLabel(assignedSlot),
                        this.courseGroupTimeRangeLabel(assignedSlot?.courseGroup || courseGroup),
                    ]
                        .filter(Boolean)
                        .join(' ')

                    return {
                        label,
                        sortValue: `${String(courseGroup?.weekday || '').padStart(2, '0')}-${String(courseGroup?.hour || '').padStart(2, '0')}-${label}`,
                        code: assignedSlot?.code || '',
                        name: assignedSlot?.name || '',
                        sourceLabel: assignedSlot?.sourceLabel || this.courseGroupSourceLabel(assignedSlot?.courseGroup),
                        alternativeLabels: assignedSlot?.alternativeLabels || [],
                        courseGroup: assignedSlot?.courseGroup,
                    }
                })
                .filter(conflict => conflict.label)
                .filter((conflict, index, conflicts) =>
                    conflicts.findIndex(existingConflict => existingConflict.label === conflict.label) === index,
                )
        },
        occasionalCourseConflictProblemMessage(conflict) {
            const courseLabel = this.courseProblemLabel(conflict.course)
            const existingLabel = this.courseProblemLabel(conflict.existingSlot)
            const dateLabel = conflict.dateTimeLabels.join(', ')

            return `${courseLabel}: Einzeltermin${dateLabel ? ` ${dateLabel}` : ''} überschneidet sich mit ${existingLabel}.`
        },
        candidateSetConflictProblemMessage(candidateSet, assignedSlots, usedSlotKeys, placedOption) {
            const courseLabel = this.courseProblemLabel(candidateSet.course)
            const conflictLabels = this.candidateSetConflictLabels(candidateSet, assignedSlots, usedSlotKeys)

            if (conflictLabels.length) {
                return `${courseLabel}: überschneidet sich mit ${conflictLabels.join(', ')}.`
            }

            if (placedOption) {
                return `${courseLabel}: in dieser Variante nicht eingeplant, damit andere Kurse Platz haben.`
            }

            return `${courseLabel}: keine überschneidungsfreie Variante gefunden.`
        },
        candidateSetConflictLabels(candidateSet, assignedSlots, usedSlotKeys) {
            return this.uniqueProblems(candidateSet.options.flatMap(option =>
                option.courseGroups
                    .filter(courseGroup => usedSlotKeys.has(this.slotKey(courseGroup.weekday, courseGroup.hour)))
                    .filter(courseGroup => this.assignedSlotConfrontsCourseGroup(assignedSlots, courseGroup))
                    .map(courseGroup => {
                        const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                        const assignedSlot = assignedSlots[key]
                        const weekday = this.weekdayOptions.find(item => Number(item.value) === Number(courseGroup.weekday))
                        const time = this.timeOptions.find(item => Number(item.value) === Number(courseGroup.hour))
                        const slotLabel = `${weekday?.shortTitle || courseGroup.weekday} ${time?.shortTitle || `${courseGroup.hour}. Stunde`}`

                        return assignedSlot?.code ? `${assignedSlot.code} (${slotLabel})` : slotLabel
                    }),
            )).slice(0, 4)
        },
        courseProblemLabel(course) {
            return [course?.code, course?.name]
                .filter(Boolean)
                .join(' - ')
        },
        uniqueProblems(problems) {
            return problems
                .filter(Boolean)
                .filter((problem, index, values) => values.indexOf(problem) === index)
        },
        courseGroupDates(courseGroup) {
            const explicitDates = Array.isArray(courseGroup?.dates) ? courseGroup.dates : []

            return [
                ...explicitDates,
                courseGroup?.first_date,
                courseGroup?.date,
            ]
                .map(date => String(date || '').trim())
                .filter(Boolean)
                .filter((date, index, dates) => dates.indexOf(date) === index)
                .sort()
        },
        courseGroupDatesLabel(courseGroup) {
            return this.courseGroupDates(courseGroup)
                .map(date => this.formatDateLabel(date))
                .join(', ')
        },
        courseGroupDateTimeLabel(courseGroup) {
            const dateTimeLabels = this.courseGroupDateTimeLabels(courseGroup)

            if (dateTimeLabels.length) return dateTimeLabels.join(', ')

            return this.courseGroupTimeRangeLabel(courseGroup)
        },
        courseGroupDateTimeLabels(courseGroup) {
            return this.courseGroupDateTimeEntries(courseGroup)
                .map(entry => entry.label)
        },
        courseGroupDateTimeEntries(courseGroup) {
            return this.courseGroupDates(courseGroup)
                .map(date => ({
                    label: this.courseGroupDateTimeLabelForDate(courseGroup, date),
                    sortValue: `${date}-${String(courseGroup?.hour || '').padStart(2, '0')}`,
                    date,
                }))
        },
        courseGroupDateTimeLabelForDate(courseGroup, date) {
            const dateLabel = date ? this.formatDateWithWeekdayLabel(date) : ''
            const timeRange = this.courseGroupTimeRangeLabel(courseGroup)

            return [dateLabel, timeRange].filter(Boolean).join(' ')
        },
        formatDateWithWeekdayLabel(value) {
            const weekdayLabel = this.weekdayLabelForDate(value)
            const dateLabel = this.formatDateLabel(value)

            return [weekdayLabel, dateLabel].filter(Boolean).join(', ')
        },
        weekdayLabelForDate(value) {
            const dateValue = String(value || '').trim()
            const match = dateValue.match(/^(\d{4})-(\d{2})-(\d{2})$/u)

            if (!match) return ''

            const [, year, month, day] = match
            const weekday = new Date(Number(year), Number(month) - 1, Number(day)).getDay()
            const isoWeekday = weekday === 0 ? 7 : weekday
            const weekdayOptions = this.weekdayOptions || [
                { shortTitle: 'Mo', value: 1 },
                { shortTitle: 'Di', value: 2 },
                { shortTitle: 'Mi', value: 3 },
                { shortTitle: 'Do', value: 4 },
                { shortTitle: 'Fr', value: 5 },
                { shortTitle: 'Sa', value: 6 },
            ]

            return weekdayOptions.find(option => Number(option.value) === isoWeekday)?.shortTitle || ''
        },
        formatDateLabel(value) {
            const dateValue = String(value || '').trim()
            const match = dateValue.match(/^(\d{4})-(\d{2})-(\d{2})$/u)

            if (match) return `${match[3]}.${match[2]}.${match[1]}`

            return dateValue
        },
        optionHasUsedSlot(option, usedSlotKeys) {
            return option.courseGroups.some(courseGroup =>
                usedSlotKeys.has(this.slotKey(courseGroup.weekday, courseGroup.hour)),
            )
        },
        assignedSlotConfrontsCourseGroup(assignedSlots, courseGroup) {
            const assignedSlot = assignedSlots[this.slotKey(courseGroup.weekday, courseGroup.hour)]
            if (!assignedSlot) return false

            return this.courseGroupsConfront(assignedSlot.courseGroup, courseGroup)
        },
        optionAlternativeLabelsForSlot(option, slotKey) {
            return option.slotAlternativeLabels?.[slotKey] || option.alternativeLabels || [option.label]
        },
        courseGroupsOverlap(leftCourseGroup, rightCourseGroup) {
            return Number(leftCourseGroup?.weekday) === Number(rightCourseGroup?.weekday)
                && Number(leftCourseGroup?.hour) === Number(rightCourseGroup?.hour)
        },
        courseGroupsConfront(leftCourseGroup, rightCourseGroup) {
            return this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                && this.courseGroupDatesOverlap(leftCourseGroup, rightCourseGroup)
        },
        courseGroupDatesOverlap(leftCourseGroup, rightCourseGroup) {
            const leftDates = this.courseGroupDates(leftCourseGroup)
            const rightDates = this.courseGroupDates(rightCourseGroup)

            if (!leftDates.length || !rightDates.length) return true

            return leftDates.some(date => rightDates.includes(date))
        },
        courseGroupTimeRangeLabel(courseGroup) {
            const schoolHours = Array.isArray(this.schoolHours) ? this.schoolHours : []
            const schoolHour = schoolHours.find(configuredSchoolHour =>
                Number(configuredSchoolHour?.hour) === Number(courseGroup?.hour),
            )

            return this.timeRangeLabel(schoolHour)
        },
        defaultTimeOptions() {
            return Array.from({ length: 10 }, (value, index) => ({
                title: `${index + 1}. Stunde`,
                shortTitle: `${index + 1}. Stunde`,
                value: index + 1,
            }))
        },
        timeOptionTitle(schoolHour) {
            const hour = Number(schoolHour.hour)
            const timeRange = this.timeRangeLabel(schoolHour)

            return timeRange ? `${hour}. Stunde (${timeRange})` : `${hour}. Stunde`
        },
        timeOptionShortTitle(schoolHour) {
            const hour = Number(schoolHour.hour)
            const timeRange = this.timeRangeLabel(schoolHour)

            return timeRange ? `${hour}. ${timeRange}` : `${hour}. Stunde`
        },
        timeRangeLabel(schoolHour) {
            const from = this.formatTimeValue(schoolHour?.from)
            const until = this.formatTimeValue(schoolHour?.until)

            return [from, until].filter(Boolean).join('-')
        },
        formatTimeValue(value) {
            const rawValue = String(value || '').trim()

            if (!rawValue) return ''

            return rawValue.slice(0, 5)
        },
        problemSummary(problem) {
            const problemText = String(problem || '')
            const onlyOccasionalMatch = problemText.match(/^(.*?): nur Einzeltermine \((.*?)\)(, orange im Stundenplan markiert\.)$/u)

            if (onlyOccasionalMatch) {
                return `${onlyOccasionalMatch[1]}: nur Einzeltermine${onlyOccasionalMatch[3]}`
            }

            const conflictMatch = problemText.match(/^(.*?): Einzeltermin (.*?) überschneidet sich mit (.*?)\.$/u)

            if (conflictMatch) {
                return `${conflictMatch[1]}: Einzeltermin überschneidet sich mit ${conflictMatch[3]}.`
            }

            return problemText
        },
        problemDetails(problem) {
            const problemText = String(problem || '')
            const onlyOccasionalMatch = problemText.match(/: nur Einzeltermine \((.*?)\), orange im Stundenplan markiert\.$/u)
            const conflictMatch = problemText.match(/: Einzeltermin (.*?) überschneidet sich mit .*?\.$/u)
            const details = onlyOccasionalMatch?.[1] || conflictMatch?.[1] || ''

            if (!details) return []

            const appointmentLabels = details.match(/(?:Mo|Di|Mi|Do|Fr|Sa|So),? \d{2}\.\d{2}\.\d{4}(?: \d{2}:\d{2}-\d{2}:\d{2})?(?: <-> (?:(?!, (?:Mo|Di|Mi|Do|Fr|Sa|So),? \d{2}\.\d{2}\.\d{4}).)+)?/gu)

            if (appointmentLabels?.length) return appointmentLabels

            return details
                .split(', ')
                .map(detail => detail.trim())
                .filter(Boolean)
        },
        syncAvailableTimes() {
            const availableTimeValues = this.timeOptions.map(time => time.value)
            const isInitialFallbackSelection = this.constraints.availableTimes.length === 10
                && this.constraints.availableTimes.every((time, index) => Number(time) === index + 1)

            if (isInitialFallbackSelection) {
                this.constraints.availableTimes = availableTimeValues

                return
            }

            this.constraints.availableTimes = this.constraints.availableTimes
                .filter(time => availableTimeValues.includes(Number(time)))
        },
        selectedOptionDescription(options, value) {
            return String(this.selectedOptionTitle(options, value)).split(' - ').pop()
        },
        subjectMatchesSelectedBranch(subject) {
            return !subject.branch || subject.branch === 'common' || subject.branch === this.selection.branch
        },
        subjectMatchesSelectedChoices(subject) {
            if (this.isArtsSubject(subject)) return this.subjectBaseKey(subject) === this.selection.artsSubject

            return true
        },
        selectedCourseFromSubject(subject) {
            const selectedCourseCode = this.selectedCourseCode(subject)
            const timetableCodes = this.selectedCourseTimetableCodes(subject)

            return {
                key: [
                    subject.id || subject.local_id || '',
                    subject.semester || '',
                    subject.branch || 'common',
                    subject.json_code || '',
                    subject.json_subject || '',
                    subject.name || '',
                    selectedCourseCode,
                ].join('|'),
                code: selectedCourseCode,
                ttCode: timetableCodes[0] || '',
                ttCodes: timetableCodes,
                name: this.selectedCourseName(subject),
                branch: this.selectedCourseBranch(subject),
                hours: Number(subject.hours_per_week || 0),
            }
        },
        selectedCoursesFromSubject(subject) {
            return this.subjectCourseVariants(subject)
                .map(courseSubject => this.selectedCourseFromSubject(courseSubject))
        },
        subjectCourseVariants(subject) {
            if (this.isReligionSubject(subject) || this.isLanguageSubject(subject)) {
                return [subject]
            }

            const courseCodes = this.courseCodeAliasParts(subject.json_code)
            if (courseCodes.length <= 1) return [subject]

            const splitHours = Number(subject.hours_per_week || 0) / courseCodes.length

            return courseCodes.map(courseCode => ({
                ...subject,
                json_code: courseCode,
                hours_per_week: Number.isFinite(splitHours) ? splitHours : subject.hours_per_week,
            }))
        },
        selectedCourseCode(subject) {
            if (this.isReligionSubject(subject)) return `${this.selection.religion}${this.subjectModuleNumber(subject)}`
            if (this.isLanguageSubject(subject)) return `${this.selection.language}${this.subjectModuleNumber(subject)}`

            return this.alternativeDisplay(subject.json_code || subject.json_subject || subject.name)
        },
        selectedCourseTimetableCodes(subject) {
            const moduleNumber = this.subjectModuleNumber(subject)
            const jsonSubjectAliases = this.subjectMappingJsonAliases(subject)
            const mappingCodes = this.activeSubjectMappings()
                .filter(mapping => jsonSubjectAliases.includes(this.normalizedCourseCode(mapping.json_subject)))
                .flatMap(mapping => this.timetableCodesForSubjectMapping(mapping, subject, moduleNumber))

            return [
                ...mappingCodes,
                ...this.timetableCodesForMappedSubject(subject.tt_subject, moduleNumber),
            ]
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        timetableCodesForSubjectMapping(mapping, subject, fallbackModuleNumber) {
            const moduleNumbers = this.selectedSubjectMappedModuleNumbers(subject, mapping.json_subject)
            const mappedModuleNumbers = moduleNumbers.length ? moduleNumbers : [fallbackModuleNumber]

            return mappedModuleNumbers.flatMap(moduleNumber =>
                this.timetableCodesForMappedSubject(mapping.tt_subject, moduleNumber),
            )
        },
        selectedSubjectMappedModuleNumbers(subject, jsonSubject) {
            const normalizedJsonSubject = this.normalizedCourseModuleBase(jsonSubject)

            return this.selectedCourseCodeParts(subject)
                .map(code => this.courseCodeModuleParts(code))
                .filter(parts => parts.module && this.normalizedCourseModuleBase(parts.base) === normalizedJsonSubject)
                .map(parts => parts.module)
                .filter((moduleNumber, index, moduleNumbers) => moduleNumbers.indexOf(moduleNumber) === index)
        },
        selectedCourseCodeParts(subject) {
            return [
                subject.json_code,
                this.selectedCourseCode(subject),
            ]
                .flatMap(value => this.courseCodeAliasParts(value))
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        timetableCodesForMappedSubject(value, moduleNumber) {
            const exactCode = this.normalizedCourseCode(value)
            const moduleCode = this.normalizedCourseCode(this.timetableCodeWithModule(value, moduleNumber))
            if (!exactCode) return []

            return [
                moduleCode,
                exactCode !== moduleCode && this.timetableCourseCodeExists(exactCode) ? exactCode : '',
            ]
        },
        timetableCourseCodeExists(code) {
            const normalizedCode = this.normalizedCourseCode(code)
            if (!normalizedCode) return false

            const courseGroups = Array.isArray(this.configuredCourseGroups) ? this.configuredCourseGroups : []

            return courseGroups.some(courseGroup => this.courseGroupCodes(courseGroup).includes(normalizedCode))
        },
        subjectMappingJsonAliases(subject) {
            return [
                this.subjectBaseKey(subject),
                subject.json_subject,
                this.courseCodeWithoutModule(subject.json_code),
                this.courseCodeWithoutModule(this.selectedCourseCode(subject)),
            ]
                .flatMap(value => this.courseCodeAliasParts(value))
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        activeSubjectMappings() {
            return Array.isArray(this.subjectMappings)
                ? this.subjectMappings.filter(mapping => mapping?.is_active !== false)
                : []
        },
        timetableCodeWithModule(value, moduleNumber) {
            const timetableSubject = String(value || '').trim()
            if (!timetableSubject) return ''

            if (moduleNumber && !/\d/u.test(timetableSubject)) {
                return `${timetableSubject}${moduleNumber}`
            }

            return timetableSubject
        },
        courseCodeWithoutModule(value) {
            return String(value || '').replace(/\d+$/u, '')
        },
        selectedCourseName(subject) {
            const moduleNumber = this.subjectModuleNumber(subject)

            if (this.isReligionSubject(subject)) {
                return `${this.selectedOptionDescription(this.religionOptions, this.selection.religion)} ${moduleNumber}`.trim()
            }

            if (this.isLanguageSubject(subject)) {
                return `${this.selectedOptionDescription(this.languageOptions, this.selection.language)} ${moduleNumber}`.trim()
            }

            if (this.isArtsSubject(subject)) {
                return `${this.selectedOptionDescription(this.artsSubjectOptions, this.selection.artsSubject)} ${moduleNumber}`.trim()
            }

            return subject.name || subject.json_subject || subject.json_code || '-'
        },
        selectedCourseBranch(subject) {
            if (!subject.branch || subject.branch === 'common') return 'alle'

            return this.selectedOptionTitle(this.branchOptions, subject.branch)
        },
        subjectBaseKey(subject) {
            const jsonSubject = String(subject.json_subject || '').trim()

            if (jsonSubject) return jsonSubject

            return String(subject.json_code || '').replace(/\d+$/u, '')
        },
        subjectModuleNumber(subject) {
            const moduleMatch = String(subject.json_code || '').match(/(\d+)$/u)

            return moduleMatch?.[1] || ''
        },
        isReligionSubject(subject) {
            return this.subjectBaseKey(subject) === 'R/ET'
        },
        isLanguageSubject(subject) {
            return this.subjectBaseKey(subject) === 'L/F/S'
        },
        isArtsSubject(subject) {
            return ['ME', 'BE'].includes(this.subjectBaseKey(subject))
        },
        alternativeDisplay(value) {
            const normalizedValue = String(value || '').trim()

            if (!normalizedValue.includes('/')) return normalizedValue || '-'

            return normalizedValue
                .split('/')
                .map(part => part.trim())
                .filter(Boolean)
                .join(' / ')
        },
        compareCourses(firstCourse, secondCourse) {
            return firstCourse.code.localeCompare(secondCourse.code, 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        formatHours(value) {
            const hours = Number(value || 0)

            if (Number.isInteger(hours)) return String(hours)

            return String(hours).replace('.', ',')
        },
    },
}
</script>

<style scoped>
.robot-timetable-card {
    max-width: 920px;
}

.robot-timetable-card__title {
    min-height: 40px;
    padding: 8px 12px;
    font-size: 0.95rem;
}

.robot-timetable-card__text {
    padding: 12px;
}

.robot-timetable-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.robot-timetable-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.robot-timetable-summary__chip {
    height: auto;
}

.robot-timetable-summary__chip :deep(.v-chip__content) {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
    line-height: 1.2;
    white-space: normal;
}

.robot-course-list {
    margin-top: 14px;
}

.robot-generator {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid rgba(15, 23, 42, 0.1);
}

.robot-generator__actions {
    display: flex;
    justify-content: flex-end;
}

.robot-generated {
    margin-top: 14px;
}

.robot-generated-timetable + .robot-generated-timetable {
    margin-top: 12px;
}

.robot-generated-timetable__header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.robot-generated-timetable__title {
    font-size: 0.86rem;
    font-weight: 750;
}

.robot-generated-timetable__problems {
    margin-bottom: 6px;
}

.robot-problems {
    margin: 0;
    padding-left: 18px;
    font-size: 0.78rem;
    line-height: 1.35;
}

.robot-problems__details {
    margin: 2px 0 4px;
    padding-left: 18px;
    color: #475569;
}

.robot-problems__details li {
    white-space: nowrap;
}

.robot-problems__title {
    margin-bottom: 4px;
    font-size: 0.82rem;
    font-weight: 750;
}

.robot-generated-grid {
    display: grid;
    grid-template-columns: 88px repeat(var(--robot-generated-weekdays, 6), minmax(72px, 1fr));
    gap: 2px;
    overflow-x: auto;
}

.robot-generated-cell {
    min-height: 34px;
    padding: 5px;
    border-radius: 5px;
    background: #eef2f7;
    font-size: 0.76rem;
    line-height: 1.15;
    text-align: center;
}

.robot-generated-cell--header,
.robot-generated-cell--time {
    background: #dbeafe;
    color: #1e3a8a;
    font-weight: 750;
}

.robot-generated-cell--filled {
    background: #bbf7d0;
    color: #052e16;
}

.robot-generated-cell--occasional {
    background: #fed7aa;
    color: #7c2d12;
}

.robot-generated-cell--affected {
    background: #fed7aa;
    color: #7c2d12;
}

.robot-generated-cell--conflict {
    background: #fecaca;
    color: #7f1d1d;
}

.robot-generated-cell__code {
    font-weight: 750;
}

.robot-generated-cell__date {
    margin-top: 1px;
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1.1;
}

.robot-generated-cell__details {
    margin-top: 2px;
    font-size: 0.66rem;
    font-weight: 400;
    line-height: 1.12;
    opacity: 0.78;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

.robot-generated-cell__conflicts {
    margin-top: 3px;
    font-size: 0.64rem;
    font-weight: 650;
    line-height: 1.12;
    overflow-wrap: anywhere;
}

.robot-generated-cell__conflict-block + .robot-generated-cell__conflict-block {
    margin-top: 6px;
    padding-top: 5px;
    border-top: 1px solid rgba(127, 29, 29, 0.24);
}

.robot-constraints {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid rgba(15, 23, 42, 0.1);
}

.robot-constraints__title {
    margin-bottom: 8px;
    font-size: 0.9rem;
    font-weight: 750;
}

.robot-constraint-block + .robot-constraint-block {
    margin-top: 12px;
}

.robot-constraint-block__label {
    margin-bottom: 6px;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 700;
}

.robot-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.robot-chip-row--times {
    gap: 4px;
}

.robot-time-matrix {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    gap: 6px 8px;
    align-items: center;
}

.robot-time-matrix__weekday {
    color: #334155;
    font-size: 0.76rem;
    font-weight: 750;
}

.robot-course-list__header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.robot-course-list__title {
    font-size: 0.9rem;
    font-weight: 750;
}

.robot-course-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    align-items: start;
}

.robot-course-table {
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 8px;
}

.robot-course-table :deep(th),
.robot-course-table :deep(td) {
    padding: 6px 8px !important;
}

.robot-course-table :deep(th) {
    color: #334155;
    font-size: 0.76rem;
    font-weight: 750 !important;
}

.robot-course-table__select {
    width: 54px;
}

.robot-course-table__row--disabled {
    color: #64748b;
    opacity: 0.62;
}

@media (max-width: 700px) {
    .robot-timetable-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .robot-course-columns {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
