<template>
    <section
        class="timetable-v3-results"
        :aria-busy="loading ? 'true' : 'false'"
        aria-labelledby="timetable-v3-results-title">
        <header class="timetable-v3-results__header">
            <h4 id="timetable-v3-results-title">
                Stundenplan
                <span v-if="selectedTimetable && positionVisible" aria-live="polite">
                    {{ selectedTimetablePosition }} von {{ normalizedTotalCount }}
                </span>
            </h4>

            <nav
                v-if="navigationVisible && normalizedTimetables.length"
                class="timetable-v3-results__navigation"
                aria-label="Stundenplan auswählen">
                <v-btn
                    type="button"
                    icon="mdi-chevron-left"
                    size="small"
                    variant="tonal"
                    :disabled="loading || !previousTimetableAvailable"
                    :loading="loading && loadingDirection === 'previous'"
                    aria-label="Vorheriger Stundenplan"
                    @click="selectPreviousTimetable" />
                <v-btn
                    type="button"
                    icon="mdi-chevron-right"
                    size="small"
                    variant="tonal"
                    :disabled="loading || !nextTimetableAvailable"
                    :loading="loading && loadingDirection === 'next'"
                    aria-label="Nächster Stundenplan"
                    @click="selectNextTimetable" />
            </nav>
        </header>

        <p v-if="error" class="timetable-v3-results__page-error" role="alert">
            <v-icon icon="mdi-alert-circle-outline" size="18" />
            {{ error }}
        </p>

        <div v-if="selectedTimetable" class="timetable-v3-results__selected">
            <div class="timetable-v3-results__table-scroll" tabindex="0" aria-label="Stundenplantabelle">
                <table
                    :key="selectedTimetableKey"
                    class="timetable-v3-results__table">
                    <caption class="timetable-v3-results__visually-hidden">
                        {{ emptyTimetable
                            ? (selectedSlotEntries.length ? 'Manueller Stundenplan' : 'Leerer Stundenplan')
                            : (positionVisible
                                ? `Stundenplan ${selectedTimetablePosition} von ${normalizedTotalCount}`
                                : 'Stundenplan') }}
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col" class="timetable-v3-results__period-heading">Std.</th>
                            <th
                                v-for="weekday in visibleWeekdays"
                                :key="weekday.value"
                                scope="col">
                                <span class="timetable-v3-results__weekday-long">{{ weekday.title }}</span>
                                <span class="timetable-v3-results__weekday-short">{{ weekday.shortTitle }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="hourRow in visibleHourRows" :key="hourRow.hour">
                            <th scope="row" class="timetable-v3-results__period">
                                <strong>{{ hourRow.hour }}.</strong>
                                <span
                                    v-if="hourRow.timeRange"
                                    class="timetable-v3-results__period-time">
                                    {{ hourRow.timeRange }}
                                </span>
                            </th>
                            <td
                                v-for="weekday in visibleWeekdays"
                                :key="`${weekday.value}-${hourRow.hour}`">
                                <div
                                    v-if="timetableEntriesForCell(weekday.value, hourRow.hour).length"
                                    class="timetable-v3-results__cell-entries">
                                    <article
                                        v-for="entry in timetableEntriesForCell(weekday.value, hourRow.hour)"
                                        :key="entry.renderKey"
                                        class="timetable-v3-results__lesson"
                                        :class="{
                                            'timetable-v3-results__lesson--overlap': lessonHasConflictMarker(
                                                entry,
                                                weekday.value,
                                                hourRow.hour,
                                            ),
                                            'timetable-v3-results__lesson--same-slot': entry.relationship === 'same-slot',
                                        }"
                                        :aria-label="lessonAriaLabel(entry, weekday.value, hourRow.hour)">
                                        <div class="timetable-v3-results__lesson-heading">
                                            <strong>{{ entry.code || 'Unterricht' }}</strong>
                                            <span
                                                v-if="lessonHasConflictMarker(entry, weekday.value, hourRow.hour)"
                                                class="timetable-v3-results__lesson-conflict-reference"
                                                :aria-label="`${conflictLabelForCell(weekday.value, hourRow.hour)} ${conflictNumberForCell(weekday.value, hourRow.hour)}`">
                                                <v-icon
                                                    icon="mdi-calendar-alert-outline"
                                                    size="17"
                                                    aria-hidden="true" />
                                                <strong>{{ conflictNumberForCell(weekday.value, hourRow.hour) }}</strong>
                                            </span>
                                        </div>
                                        <span
                                            v-if="lessonSourceLabel(entry)"
                                            class="timetable-v3-results__lesson-source">
                                            {{ lessonSourceLabel(entry) }}
                                        </span>
                                        <span
                                            v-if="lessonPrimaryDetailsLabel(entry)"
                                            class="timetable-v3-results__lesson-detail">
                                            {{ lessonPrimaryDetailsLabel(entry) }}
                                        </span>
                                        <span
                                            v-if="lessonSpecialDetailsLabel(entry, weekday.value, hourRow.hour)"
                                            class="timetable-v3-results__lesson-special">
                                            {{ lessonSpecialDetailsLabel(entry, weekday.value, hourRow.hour) }}
                                        </span>
                                    </article>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <section
                v-if="selectedConflictGroups.length"
                class="timetable-v3-results__conflict-summary"
                aria-label="Überschneidungsdetails">
                <h5>Überschneidungen</h5>
                <div
                    v-for="conflictGroup in selectedConflictGroups"
                    :key="conflictGroup.slotKey"
                    class="timetable-v3-results__conflict-group">
                    <p
                        v-for="course in conflictGroup.courses"
                        :key="course.key"
                        class="timetable-v3-results__conflict-course">
                        <span class="timetable-v3-results__conflict-counter">!{{ conflictGroup.number }}</span>
                        <strong>{{ course.label }}:</strong>
                        <template v-if="course.dates.length">
                            <template v-for="(date, dateIndex) in course.dates" :key="date.value">
                                <span
                                    class="timetable-v3-results__conflict-date"
                                    :class="{
                                        'timetable-v3-results__conflict-date--overlap': date.isOverlapping,
                                    }"
                                    :aria-label="date.isOverlapping
                                        ? `${date.label}, Überschneidung`
                                        : date.label">
                                    {{ date.label }}
                                </span><span v-if="dateIndex < course.dates.length - 1">, </span>
                            </template>
                        </template>
                        <span v-else>Keine Termine vorhanden</span>
                    </p>
                </div>
            </section>

            <p class="timetable-v3-results__hint">
                <v-icon icon="mdi-gesture-swipe-horizontal" size="18" aria-hidden="true" />
                Horizontal wischen
            </p>
        </div>

        <div v-else class="timetable-v3-results__unavailable" role="status">
            <v-icon icon="mdi-calendar-question-outline" size="30" />
            <div>
                <strong>Die Stundenpläne konnten nicht angezeigt werden.</strong>
                <span>Bitte starten Sie die Berechnung erneut.</span>
            </div>
        </div>
    </section>
</template>

<script>
import {
    canonicalTimetableCourseLabel,
    canonicalTimetableModuleName,
} from './courseLabels'

const WEEKDAYS = [
    { value: 1, title: 'Montag', shortTitle: 'Mo' },
    { value: 2, title: 'Dienstag', shortTitle: 'Di' },
    { value: 3, title: 'Mittwoch', shortTitle: 'Mi' },
    { value: 4, title: 'Donnerstag', shortTitle: 'Do' },
    { value: 5, title: 'Freitag', shortTitle: 'Fr' },
    { value: 6, title: 'Samstag', shortTitle: 'Sa' },
]
const EMPTY_TIMETABLE_FIRST_HOUR = 1
const EMPTY_TIMETABLE_LAST_HOUR = 15
const EMPTY_TIMETABLE = Object.freeze({
    key: 'empty-timetable',
    slots: Object.freeze({}),
})

export default {
    name: 'TimetableV3PossibleTimetables',

    emits: ['navigate'],

    props: {
        allowSaturdayLessons: {
            type: Boolean,
            default: false,
        },
        emptyTimetable: {
            type: Boolean,
            default: false,
        },
        emptyHourRows: {
            type: Array,
            default: () => [],
        },
        highlightMultipleEntries: {
            type: Boolean,
            default: false,
        },
        error: {
            type: String,
            default: '',
        },
        loading: {
            type: Boolean,
            default: false,
        },
        loadingDirection: {
            type: String,
            default: '',
            validator: value => ['', 'previous', 'next'].includes(value),
        },
        manualTimetable: {
            type: Object,
            default: null,
        },
        navigationVisible: {
            type: Boolean,
            default: true,
        },
        pageOffset: {
            type: Number,
            default: 0,
        },
        positionVisible: {
            type: Boolean,
            default: true,
        },
        showAllHours: {
            type: Boolean,
            default: false,
        },
        selectedIndex: {
            type: Number,
            default: 0,
        },
        timetables: {
            type: Array,
            default: () => [],
        },
        totalCount: {
            type: Number,
            default: 0,
        },
    },

    computed: {
        normalizedTimetables() {
            return this.timetables.filter(timetable => (
                timetable
                && typeof timetable === 'object'
                && !Array.isArray(timetable)
                && timetable.slots
                && typeof timetable.slots === 'object'
                && !Array.isArray(timetable.slots)
            ))
        },
        normalizedPageOffset() {
            const pageOffset = Number(this.pageOffset)

            return Number.isInteger(pageOffset) && pageOffset >= 0 ? pageOffset : 0
        },
        normalizedSelectedIndex() {
            const selectedIndex = Number(this.selectedIndex)

            return Number.isInteger(selectedIndex) && selectedIndex >= 0 ? selectedIndex : 0
        },
        normalizedTotalCount() {
            if (this.emptyTimetable) return 1

            const totalCount = Number(this.totalCount)

            if (Number.isInteger(totalCount) && totalCount > 0) return totalCount

            return this.normalizedTimetables.length
        },
        selectedTimetableIndex() {
            return this.normalizedSelectedIndex - this.normalizedPageOffset
        },
        selectedTimetable() {
            if (this.emptyTimetable) {
                const manualTimetableSlots = this.manualTimetable?.slots

                if (
                    manualTimetableSlots
                    && typeof manualTimetableSlots === 'object'
                    && !Array.isArray(manualTimetableSlots)
                ) {
                    return this.manualTimetable
                }

                return EMPTY_TIMETABLE
            }

            return this.normalizedTimetables[this.selectedTimetableIndex] || null
        },
        selectedTimetableKey() {
            return String(this.selectedTimetable?.key || `timetable-${this.selectedTimetableIndex}`)
        },
        selectedTimetablePosition() {
            return this.selectedTimetable ? this.normalizedSelectedIndex + 1 : 0
        },
        previousTimetableAvailable() {
            return this.normalizedSelectedIndex > 0
        },
        nextTimetableAvailable() {
            return this.normalizedSelectedIndex < this.normalizedTotalCount - 1
        },
        selectedSlotEntries() {
            return Object.entries(this.selectedTimetable?.slots || {})
                .map(([slotKey, slot]) => {
                    if (!slot || typeof slot !== 'object' || Array.isArray(slot)) return null

                    const [keyWeekday, keyHour] = slotKey.split('-')
                    const weekday = Number(slot.courseGroup?.weekday || keyWeekday || 0)
                    const hour = Number(slot.courseGroup?.hour || keyHour || 0)

                    if (!Number.isFinite(weekday) || !Number.isFinite(hour) || weekday < 1 || hour < 1) return null

                    return { hour, slot, slotKey, weekday }
                })
                .filter(Boolean)
        },
        visibleWeekdays() {
            const hasSaturday = this.allowSaturdayLessons
                || this.selectedSlotEntries.some(entry => entry.weekday === 6)

            return WEEKDAYS.filter(weekday => weekday.value <= 5 || hasSaturday)
        },
        normalizedEmptyHourRows() {
            const rowsByHour = new Map()

            this.emptyHourRows.forEach((row) => {
                const hour = Number(row?.hour)

                if (!Number.isInteger(hour) || hour < 1) return

                const startsAt = String(row?.from || '').trim().slice(0, 5)
                const endsAt = String(row?.until || '').trim().slice(0, 5)

                rowsByHour.set(hour, {
                    hour,
                    timeRange: [startsAt, endsAt].filter(Boolean).join('–'),
                })
            })

            return [...rowsByHour.values()].sort((firstRow, secondRow) => firstRow.hour - secondRow.hour)
        },
        visibleHours() {
            const hours = this.selectedSlotEntries.map(entry => entry.hour)

            if (this.showAllHours || this.emptyTimetable) {
                const configuredHours = this.normalizedEmptyHourRows
                    .map(row => row.hour)
                    .filter(hour => hour >= EMPTY_TIMETABLE_FIRST_HOUR)
                const finalHour = Math.max(
                    ...configuredHours,
                    ...hours,
                    EMPTY_TIMETABLE_LAST_HOUR,
                )

                return Array.from(
                    { length: (finalHour - EMPTY_TIMETABLE_FIRST_HOUR) + 1 },
                    (_, index) => EMPTY_TIMETABLE_FIRST_HOUR + index,
                )
            }

            if (!hours.length) return []

            const firstHour = Math.min(...hours)
            const lastHour = Math.max(...hours)

            return Array.from({ length: (lastHour - firstHour) + 1 }, (_, index) => firstHour + index)
        },
        visibleHourRows() {
            const configuredRowsByHour = new Map(this.normalizedEmptyHourRows.map(row => [row.hour, row]))

            return this.visibleHours.map((hour) => {
                const configuredRow = configuredRowsByHour.get(hour)
                if (configuredRow?.timeRange) return configuredRow

                const timeRanges = this.selectedSlotEntries
                    .filter(entry => entry.hour === hour)
                    .map(entry => this.courseGroupTimeRange(entry.slot?.courseGroup))
                    .filter(Boolean)

                return {
                    hour,
                    timeRange: [...new Set(timeRanges)].join(' / '),
                }
            })
        },
        selectedConflictGroups() {
            return this.selectedSlotEntries
                .map(({ hour, slotKey, weekday }) => {
                    const entries = this.timetableEntriesForCell(weekday, hour)
                    const overlapEntries = entries.filter(entry => entry.relationship === 'overlap')
                    if (!overlapEntries.length) return null

                    const exactOverlapEntries = entries.filter((entry, entryIndex) => entries
                        .some((otherEntry, otherEntryIndex) => (
                            entryIndex !== otherEntryIndex
                            && this.lessonEntriesShareDateAndTime(entry, otherEntry)
                        )))
                    const summaryEntries = exactOverlapEntries.length
                        ? exactOverlapEntries
                        : [entries[0], ...overlapEntries].filter(Boolean)

                    const courses = this.conflictSummaryCourses(summaryEntries)
                    const overlappingDates = new Set(courses.flatMap(course => course.dates
                        .filter(date => date.isOverlapping)
                        .map(date => date.value)))

                    return {
                        courses,
                        hour,
                        label: overlappingDates.size === 1
                            ? 'Einzeltermin-Überschneidung'
                            : 'Überschneidungen',
                        slotKey,
                        weekday,
                    }
                })
                .filter(Boolean)
                .sort((firstGroup, secondGroup) => (
                    firstGroup.weekday - secondGroup.weekday
                    || firstGroup.hour - secondGroup.hour
                    || firstGroup.slotKey.localeCompare(secondGroup.slotKey)
                ))
                .map((conflictGroup, index) => ({
                    ...conflictGroup,
                    number: index + 1,
                }))
        },
        selectedConflictNumberBySlot() {
            return Object.fromEntries(this.selectedConflictGroups
                .map(conflictGroup => [conflictGroup.slotKey, conflictGroup.number]))
        },
        selectedConflictLabelBySlot() {
            return Object.fromEntries(this.selectedConflictGroups
                .map(conflictGroup => [conflictGroup.slotKey, conflictGroup.label]))
        },
    },

    methods: {
        selectPreviousTimetable() {
            if (this.loading || !this.previousTimetableAvailable) return

            this.$emit('navigate', this.normalizedSelectedIndex - 1)
        },
        selectNextTimetable() {
            if (this.loading || !this.nextTimetableAvailable) return

            this.$emit('navigate', this.normalizedSelectedIndex + 1)
        },
        timetableEntriesForCell(weekday, hour) {
            const timetableSlot = this.selectedTimetable?.slots?.[`${weekday}-${hour}`]

            if (!timetableSlot || typeof timetableSlot !== 'object' || Array.isArray(timetableSlot)) return []

            const primaryEntry = this.normalizedTimetableEntry(
                timetableSlot,
                'primary',
                `${weekday}-${hour}-primary`,
            )
            const normalizedSameSlotEntries = this.normalizedRelatedEntries(
                timetableSlot.sameSlotEntries,
                'same-slot',
            )
            const conflictEntries = this.normalizedRelatedEntries(timetableSlot.conflicts, 'overlap')
            const comparableEntries = [primaryEntry, ...normalizedSameSlotEntries, ...conflictEntries].filter(Boolean)
            const sameSlotEntries = normalizedSameSlotEntries.map(entry => ({
                ...entry,
                relationship: this.highlightMultipleEntries
                    && comparableEntries.some(otherEntry => (
                        otherEntry.renderKey !== entry.renderKey
                        && this.lessonEntriesShareDateAndTime(entry, otherEntry)
                    ))
                    ? 'overlap'
                    : 'same-slot',
            }))

            return [primaryEntry, ...sameSlotEntries, ...conflictEntries]
                .filter(Boolean)
        },
        normalizedRelatedEntries(entries, relationship) {
            return (Array.isArray(entries) ? entries : [])
                .map((entry, index) => this.normalizedTimetableEntry(entry, relationship, `${relationship}-${index}`))
                .filter(Boolean)
        },
        normalizedTimetableEntry(entry, relationship, fallbackKey) {
            if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return null

            const courseGroup = entry.courseGroup && typeof entry.courseGroup === 'object'
                ? entry.courseGroup
                : {}

            const code = canonicalTimetableCourseLabel(
                entry.code || courseGroup.module_code || courseGroup.subject || '',
            )

            return {
                ...entry,
                code,
                courseGroup,
                name: canonicalTimetableModuleName(entry.name, code),
                relationship,
                renderKey: `${relationship}:${String(entry.key || courseGroup.key || '')}:${fallbackKey}`,
                sourceLabel: canonicalTimetableCourseLabel(
                    entry.sourceLabel || courseGroup.display_label || courseGroup.class_name || '',
                    code,
                ),
            }
        },
        conflictNumberForCell(weekday, hour) {
            return this.selectedConflictNumberBySlot[`${weekday}-${hour}`] || ''
        },
        conflictLabelForCell(weekday, hour) {
            return this.selectedConflictLabelBySlot[`${weekday}-${hour}`] || 'Überschneidungen'
        },
        conflictSummaryCourses(entries) {
            const uniqueEntriesByKey = new Map()

            entries.forEach((entry, index) => {
                const entryKey = String(
                    entry.courseGroup?.key
                    || entry.key
                    || entry.renderKey
                    || `course-${index}`,
                )

                if (!uniqueEntriesByKey.has(entryKey)) uniqueEntriesByKey.set(entryKey, entry)
            })

            const uniqueEntries = [...uniqueEntriesByKey.entries()]

            return uniqueEntries.map(([entryKey, entry]) => {
                const dates = this.normalizedLessonDates(entry)
                const otherDateSets = uniqueEntries
                    .filter(([otherEntryKey, otherEntry]) => (
                        otherEntryKey !== entryKey
                        && this.lessonEntriesOverlapInTime(entry, otherEntry)
                    ))
                    .map(([, otherEntry]) => new Set(this.normalizedLessonDates(otherEntry)))

                return {
                    dates: dates.map(date => ({
                        isOverlapping: otherDateSets.some(otherDates => otherDates.has(date)),
                        label: this.localizedShortDate(date),
                        value: date,
                    })),
                    key: entryKey,
                    label: this.conflictSummaryCourseLabel(entry),
                }
            })
        },
        conflictSummaryCourseLabel(entry) {
            return [...new Set([
                String(entry.code || '').trim(),
                this.lessonSourceLabel(entry),
            ].filter(Boolean))].join(' ')
        },
        normalizedLessonDates(entry) {
            return [...new Set((Array.isArray(entry.courseGroup?.dates) ? entry.courseGroup.dates : [])
                .map(date => String(date || '').trim())
                .filter(Boolean))]
                .sort((firstDate, secondDate) => firstDate.localeCompare(secondDate))
        },
        lessonEntriesShareDateAndTime(firstEntry, secondEntry) {
            const firstDates = this.normalizedLessonDates(firstEntry)
            const secondDates = new Set(this.normalizedLessonDates(secondEntry))
            if (!firstDates.length || !secondDates.size) return false

            return this.lessonEntriesOverlapInTime(firstEntry, secondEntry)
                && firstDates.some(date => secondDates.has(date))
        },
        lessonEntriesOverlapInTime(firstEntry, secondEntry) {
            const firstStartsAt = this.lessonTimeInMinutes(
                firstEntry.courseGroup?.starts_at || firstEntry.courseGroup?.time_from,
            )
            const firstEndsAt = this.lessonTimeInMinutes(
                firstEntry.courseGroup?.ends_at || firstEntry.courseGroup?.time_until,
            )
            const secondStartsAt = this.lessonTimeInMinutes(
                secondEntry.courseGroup?.starts_at || secondEntry.courseGroup?.time_from,
            )
            const secondEndsAt = this.lessonTimeInMinutes(
                secondEntry.courseGroup?.ends_at || secondEntry.courseGroup?.time_until,
            )

            if (
                firstStartsAt === null
                || firstEndsAt === null
                || secondStartsAt === null
                || secondEndsAt === null
            ) {
                return true
            }

            return firstStartsAt < secondEndsAt && secondStartsAt < firstEndsAt
        },
        lessonTimeInMinutes(value) {
            const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})/u)
            if (!match) return null

            const hours = Number(match[1])
            const minutes = Number(match[2])

            if (!Number.isInteger(hours) || !Number.isInteger(minutes) || hours > 23 || minutes > 59) return null

            return (hours * 60) + minutes
        },
        courseGroupTimeRange(courseGroup) {
            const startsAt = String(courseGroup?.starts_at || courseGroup?.time_from || '').trim()
            const endsAt = String(courseGroup?.ends_at || courseGroup?.time_until || '').trim()

            return [startsAt, endsAt].filter(Boolean).join('–')
        },
        lessonScheduleLabel(entry) {
            return this.courseGroupTimeRange(entry.courseGroup)
        },
        lessonDateLabel(entry) {
            const recurrenceLabel = String(entry.courseGroup?.recurrence_label || '').trim()
            const dateRangeLabel = entry.courseGroup?.is_full_semester === true
                ? ''
                : String(entry.dateRangeLabel || '').trim()
            const dates = Array.isArray(entry.courseGroup?.dates) ? entry.courseGroup.dates : []
            const singleDate = dates.length === 1 ? this.localizedDate(dates[0]) : ''

            return [recurrenceLabel, singleDate || dateRangeLabel].filter(Boolean).join(' · ')
        },
        lessonSourceLabel(entry) {
            const code = String(entry.code || '').trim()
            const sourceLabel = String(entry.sourceLabel || '').trim()

            if (!code || !sourceLabel.toLocaleLowerCase().startsWith(code.toLocaleLowerCase())) {
                return sourceLabel
            }

            const labelWithoutCode = sourceLabel.slice(code.length)

            if (!/^[\s·|:/-]+/.test(labelWithoutCode)) return sourceLabel

            return labelWithoutCode.replace(/^[\s·|:/-]+/, '').trim() || sourceLabel
        },
        lessonPrimaryDetailsLabel(entry) {
            return [this.lessonScheduleLabel(entry), this.lessonDateLabel(entry)].filter(Boolean).join(' · ')
        },
        lessonPeopleAndRoomsLabel(entry) {
            const teacher = String(entry.courseGroup?.teacher || '').trim()
            const roomValues = Array.isArray(entry.courseGroup?.rooms)
                ? entry.courseGroup.rooms
                : [entry.courseGroup?.room]
            const rooms = roomValues
                .map(room => typeof room === 'object' ? room?.name || room?.code : room)
                .map(room => String(room || '').trim())
                .filter(Boolean)

            return [teacher, rooms.length ? `Raum ${rooms.join(', ')}` : ''].filter(Boolean).join(' · ')
        },
        localizedDate(value) {
            const [year, month, day] = String(value || '').split('-').map(Number)

            if (!year || !month || !day) return String(value || '').trim()

            return new Intl.DateTimeFormat('de-AT').format(new Date(Date.UTC(year, month - 1, day)))
        },
        localizedShortDate(value) {
            const [year, month, day] = String(value || '').split('-').map(Number)

            if (!year || !month || !day) return String(value || '').trim()

            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
            }).format(new Date(Date.UTC(year, month - 1, day)))
        },
        lessonMarkers(entry, weekday, hour) {
            const hasSingleDate = this.normalizedLessonDates(entry).length === 1

            return [
                this.lessonHasConflictMarker(entry, weekday, hour)
                    ? this.conflictLabelForCell(weekday, hour)
                    : '',
                entry.isDistanceLearningCourse === true ? 'Fernunterricht' : '',
                !hasSingleDate && entry.courseGroup?.is_kompaktunterricht === true ? 'Kompaktunterricht' : '',
                !hasSingleDate && entry.courseGroup?.is_block === true
                    ? String(entry.courseGroup?.block_label || 'Blockunterricht').trim()
                    : '',
            ].filter(Boolean)
        },
        lessonHasConflictMarker(entry, weekday, hour) {
            const entries = this.timetableEntriesForCell(weekday, hour)
            const exactOverlapEntries = entries.filter((candidateEntry, candidateIndex) => entries
                .some((otherEntry, otherEntryIndex) => (
                    candidateIndex !== otherEntryIndex
                    && this.lessonEntriesShareDateAndTime(candidateEntry, otherEntry)
                )))
            const singleDateEntries = exactOverlapEntries
                .filter(candidateEntry => this.normalizedLessonDates(candidateEntry).length === 1)

            if (singleDateEntries.length === 1) {
                return singleDateEntries[0].renderKey === entry.renderKey
            }

            return entry.relationship === 'overlap'
        },
        lessonSpecialDetailsLabel(entry, weekday, hour) {
            return this.lessonMarkers(entry, weekday, hour).join(' · ')
        },
        lessonAriaLabel(entry, weekday, hour) {
            return [
                entry.code,
                entry.name,
                entry.sourceLabel,
                this.lessonScheduleLabel(entry),
                this.lessonDateLabel(entry),
                this.lessonPeopleAndRoomsLabel(entry),
                ...this.lessonMarkers(entry, weekday, hour),
            ].filter(Boolean).join(', ')
        },
    },
}
</script>

<style scoped>
.timetable-v3-results {
    display: grid;
    gap: 12px;
    padding-top: 18px;
    margin-top: 8px;
}

.timetable-v3-results__header,
.timetable-v3-results__navigation {
    display: flex;
    gap: 8px;
    align-items: center;
}

.timetable-v3-results__header {
    flex-wrap: wrap;
    justify-content: space-between;
}

.timetable-v3-results h4 {
    font-size: 1.12rem;
    font-weight: 800;
    color: #134e4a;
}

.timetable-v3-results h4 span {
    margin-left: 5px;
    font-weight: 650;
    color: #64748b;
}

.timetable-v3-results__navigation {
    flex-wrap: nowrap;
}

.timetable-v3-results__page-error {
    display: flex;
    gap: 7px;
    align-items: center;
    margin: 0;
    font-size: 0.82rem;
    font-weight: 650;
    color: #b42318;
}

.timetable-v3-results__selected {
    position: relative;
    display: grid;
    gap: 8px;
}

.timetable-v3-results__table-scroll {
    overflow: auto;
    overscroll-behavior-inline: contain;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    scrollbar-color: rgba(15, 118, 110, 0.55) transparent;
}

.timetable-v3-results__table-scroll:focus-visible {
    outline: 3px solid rgba(20, 184, 166, 0.35);
    outline-offset: 3px;
}

.timetable-v3-results__table {
    width: 100%;
    min-width: 800px;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0;
}

.timetable-v3-results__table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    height: 40px;
    padding: 7px;
    font-size: 0.82rem;
    font-weight: 850;
    color: #fff;
    text-align: center;
    background: #0f766e;
}

.timetable-v3-results__table thead th:not(:last-child) {
    border-right: 1px solid rgba(255, 255, 255, 0.3);
}

.timetable-v3-results__period-heading,
.timetable-v3-results__period {
    position: sticky;
    left: 0;
    z-index: 1;
    width: 94px;
}

.timetable-v3-results__period-heading {
    z-index: 3 !important;
}

.timetable-v3-results__period {
    padding: 6px 4px;
    color: #334155;
    text-align: center;
    background: inherit;
}

.timetable-v3-results__period strong {
    display: block;
    font-size: 0.9rem;
}

.timetable-v3-results__period-time {
    display: block;
    margin-top: 2px;
    font-size: 0.68rem;
    font-weight: 650;
    line-height: 1.2;
    color: #64748b;
}

.timetable-v3-results__table td {
    min-height: 68px;
    padding: 4px;
    vertical-align: top;
    background: inherit;
}

.timetable-v3-results__table tbody th,
.timetable-v3-results__table tbody td {
    border-right: 1px solid #dbe4ea;
    border-bottom: 1px solid #dbe4ea;
}

.timetable-v3-results__table tbody tr > :last-child {
    border-right: 0;
}

.timetable-v3-results__table tbody tr:last-child th,
.timetable-v3-results__table tbody tr:last-child td {
    border-bottom: 0;
}

.timetable-v3-results__table tbody tr:nth-child(odd) {
    background: #fff;
}

.timetable-v3-results__table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.timetable-v3-results__cell-entries {
    display: grid;
    gap: 4px;
}

.timetable-v3-results__lesson {
    display: grid;
    gap: 2px;
    min-width: 0;
    padding: 6px;
    color: #1e293b;
    background: rgba(240, 253, 250, 0.72);
    border: 0;
    border-left: 3px solid #0d9488;
    border-radius: 3px;
}

.timetable-v3-results__lesson--same-slot {
    background: #f8fafc;
}

.timetable-v3-results__lesson--overlap {
    color: #78350f;
    background: #fffbeb;
    border-left-color: #f59e0b;
}

.timetable-v3-results__lesson-heading {
    display: flex;
    gap: 5px;
    align-items: center;
    justify-content: space-between;
}

.timetable-v3-results__lesson-heading strong {
    font-size: 0.84rem;
    line-height: 1.15;
}

.timetable-v3-results__lesson-conflict-reference {
    display: inline-flex;
    gap: 2px;
    align-items: center;
    justify-content: flex-end;
    flex: 0 0 auto;
    margin-left: auto;
    text-align: right;
}

.timetable-v3-results__lesson-conflict-reference strong {
    font-size: 0.72rem;
}

.timetable-v3-results__lesson-heading span,
.timetable-v3-results__lesson-source,
.timetable-v3-results__lesson-detail,
.timetable-v3-results__lesson-special {
    overflow-wrap: anywhere;
    font-size: 0.72rem;
    line-height: 1.3;
}

.timetable-v3-results__lesson-heading span {
    flex: 1;
}

.timetable-v3-results__lesson-heading span,
.timetable-v3-results__lesson-detail {
    color: #475569;
}

.timetable-v3-results__lesson-source {
    font-weight: 650;
}

.timetable-v3-results__lesson-special {
    font-weight: 700;
    color: #92400e;
}

.timetable-v3-results__conflict-summary {
    display: grid;
    gap: 7px;
    padding: 11px 12px;
    color: #334155;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
}

.timetable-v3-results__conflict-summary h5 {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 800;
    color: #78350f;
}

.timetable-v3-results__conflict-group {
    display: grid;
    gap: 3px;
    padding: 8px 9px;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-left: 3px solid #f59e0b;
    border-radius: 6px;
}

.timetable-v3-results__conflict-course {
    margin: 0;
    overflow-wrap: anywhere;
    font-size: 0.76rem;
    line-height: 1.45;
}

.timetable-v3-results__conflict-course > strong {
    margin-right: 4px;
}

.timetable-v3-results__conflict-counter {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 26px;
    min-height: 22px;
    margin-right: 5px;
    padding: 0 5px;
    font-weight: 800;
    color: #92400e;
    background: #fff7ed;
    border: 1px solid #d97706;
    border-radius: 5px;
}

.timetable-v3-results__conflict-date--overlap {
    font-weight: 650;
    color: #b42318;
}

.timetable-v3-results__hint {
    display: none;
    gap: 7px;
    align-items: center;
    margin: 0;
    font-size: 0.75rem;
    color: #64748b;
}

.timetable-v3-results__unavailable {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 18px;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 14px;
}

.timetable-v3-results__unavailable strong,
.timetable-v3-results__unavailable span {
    display: block;
}

.timetable-v3-results__unavailable span {
    margin-top: 2px;
    font-size: 0.85rem;
}

.timetable-v3-results__weekday-short {
    display: none;
}

.timetable-v3-results__visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

@media (max-width: 900px) {
    .timetable-v3-results__table {
        min-width: 720px;
    }

    .timetable-v3-results__hint {
        display: flex;
    }
}

@media (max-width: 600px) {
    .timetable-v3-results__header {
        align-items: flex-start;
    }

    .timetable-v3-results__weekday-long {
        display: none;
    }

    .timetable-v3-results__weekday-short {
        display: inline;
    }

    .timetable-v3-results__table {
        min-width: 650px;
    }

}

@media (prefers-reduced-motion: reduce) {
    .timetable-v3-results *,
    .timetable-v3-results *::before,
    .timetable-v3-results *::after {
        scroll-behavior: auto !important;
        transition: none !important;
    }
}
</style>
