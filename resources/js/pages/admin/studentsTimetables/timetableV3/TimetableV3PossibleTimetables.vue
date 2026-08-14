<template>
    <section
        class="timetable-v3-results"
        :aria-busy="loading ? 'true' : 'false'"
        aria-labelledby="timetable-v3-results-title">
        <header class="timetable-v3-results__header">
            <h4 id="timetable-v3-results-title">
                Stundenplan
                <span v-if="selectedTimetable" aria-live="polite">
                    {{ selectedTimetablePosition }} von {{ normalizedTotalCount }}
                </span>
            </h4>

            <span
                v-if="selectedTimetableQualityKey === 'occasional'"
                class="timetable-v3-results__warning">
                <v-icon icon="mdi-calendar-alert-outline" size="16" />
                Einzeltermin-Überschneidung
            </span>

            <nav
                v-if="normalizedTimetables.length"
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
            <div
                v-if="loading"
                class="timetable-v3-results__page-loading"
                role="status"
                aria-live="polite"
                aria-atomic="true">
                <v-progress-circular
                    color="primary"
                    indeterminate
                    :size="48"
                    :width="5" />
                <div>
                    <strong>{{ loadingPageRangeLabel }}</strong>
                    <span>Der angezeigte Stundenplan wird gleich ersetzt.</span>
                </div>
            </div>

            <div class="timetable-v3-results__table-scroll" tabindex="0" aria-label="Stundenplantabelle">
                <table
                    :key="selectedTimetableKey"
                    class="timetable-v3-results__table">
                    <caption class="timetable-v3-results__visually-hidden">
                        {{ `Stundenplan ${selectedTimetablePosition} von ${normalizedTotalCount}` }}
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
                        <tr v-for="hour in visibleHours" :key="hour">
                            <th scope="row" class="timetable-v3-results__period">
                                <strong>{{ hour }}.</strong>
                            </th>
                            <td
                                v-for="weekday in visibleWeekdays"
                                :key="`${weekday.value}-${hour}`">
                                <div
                                    v-if="timetableEntriesForCell(weekday.value, hour).length"
                                    class="timetable-v3-results__cell-entries">
                                    <article
                                        v-for="entry in timetableEntriesForCell(weekday.value, hour)"
                                        :key="entry.renderKey"
                                        class="timetable-v3-results__lesson"
                                        :class="{
                                            'timetable-v3-results__lesson--overlap': entry.relationship === 'overlap',
                                            'timetable-v3-results__lesson--same-slot': entry.relationship === 'same-slot',
                                        }"
                                        :aria-label="lessonAriaLabel(entry)">
                                        <div class="timetable-v3-results__lesson-heading">
                                            <strong>{{ entry.code || 'Unterricht' }}</strong>
                                            <v-icon
                                                v-if="entry.relationship === 'overlap'"
                                                icon="mdi-calendar-alert-outline"
                                                size="17"
                                                aria-label="Einzeltermin-Überschneidung" />
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
                                            v-if="lessonSpecialDetailsLabel(entry)"
                                            class="timetable-v3-results__lesson-special">
                                            {{ lessonSpecialDetailsLabel(entry) }}
                                        </span>
                                    </article>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

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
const WEEKDAYS = [
    { value: 1, title: 'Montag', shortTitle: 'Mo' },
    { value: 2, title: 'Dienstag', shortTitle: 'Di' },
    { value: 3, title: 'Mittwoch', shortTitle: 'Mi' },
    { value: 4, title: 'Donnerstag', shortTitle: 'Do' },
    { value: 5, title: 'Freitag', shortTitle: 'Fr' },
    { value: 6, title: 'Samstag', shortTitle: 'Sa' },
]
const TIMETABLE_PAGE_SIZE = 100

export default {
    name: 'TimetableV3PossibleTimetables',

    emits: ['navigate'],

    props: {
        allowSaturdayLessons: {
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
        pageOffset: {
            type: Number,
            default: 0,
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
            const totalCount = Number(this.totalCount)

            if (Number.isInteger(totalCount) && totalCount > 0) return totalCount

            return this.normalizedTimetables.length
        },
        selectedTimetableIndex() {
            return this.normalizedSelectedIndex - this.normalizedPageOffset
        },
        selectedTimetable() {
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
        loadingPageRangeLabel() {
            if (!this.loading || !this.normalizedTotalCount) return 'Stundenpläne werden geladen …'

            let targetPageOffset = this.normalizedPageOffset

            if (this.loadingDirection === 'previous') {
                targetPageOffset = Math.max(0, targetPageOffset - TIMETABLE_PAGE_SIZE)
            } else if (this.loadingDirection === 'next') {
                targetPageOffset += TIMETABLE_PAGE_SIZE
            }

            const firstPosition = Math.min(targetPageOffset + 1, this.normalizedTotalCount)
            const lastPosition = Math.min(targetPageOffset + TIMETABLE_PAGE_SIZE, this.normalizedTotalCount)

            return `Stundenpläne ${firstPosition}–${lastPosition} werden geladen …`
        },
        selectedTimetableQualityKey() {
            return this.selectedTimetable?.type === 'green' ? 'occasional' : 'clear'
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
        visibleHours() {
            const hours = this.selectedSlotEntries.map(entry => entry.hour)

            if (!hours.length) return []

            const firstHour = Math.min(...hours)
            const lastHour = Math.max(...hours)

            return Array.from({ length: (lastHour - firstHour) + 1 }, (_, index) => firstHour + index)
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

            const relatedEntries = [
                ...this.normalizedRelatedEntries(timetableSlot.sameSlotEntries, 'same-slot'),
                ...this.normalizedRelatedEntries(timetableSlot.conflicts, 'overlap'),
            ]

            return [this.normalizedTimetableEntry(timetableSlot, 'primary', `${weekday}-${hour}-primary`), ...relatedEntries]
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

            return {
                ...entry,
                code: String(entry.code || courseGroup.module_code || courseGroup.subject || '').trim(),
                courseGroup,
                name: String(entry.name || '').trim(),
                relationship,
                renderKey: `${relationship}:${String(entry.key || courseGroup.key || '')}:${fallbackKey}`,
                sourceLabel: String(entry.sourceLabel || courseGroup.display_label || courseGroup.class_name || '').trim(),
            }
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
            const dateRangeLabel = String(entry.dateRangeLabel || '').trim()
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
        lessonMarkers(entry) {
            return [
                entry.relationship === 'overlap' ? 'Einzeltermin-Überschneidung' : '',
                entry.isDistanceLearningCourse === true ? 'Fernunterricht' : '',
                entry.courseGroup?.is_kompaktunterricht === true ? 'Kompaktunterricht' : '',
                entry.courseGroup?.is_block === true
                    ? String(entry.courseGroup?.block_label || 'Blockunterricht').trim()
                    : '',
            ].filter(Boolean)
        },
        lessonSpecialDetailsLabel(entry) {
            return this.lessonMarkers(entry).join(' · ')
        },
        lessonAriaLabel(entry) {
            return [
                entry.code,
                entry.name,
                entry.sourceLabel,
                this.lessonScheduleLabel(entry),
                this.lessonDateLabel(entry),
                this.lessonPeopleAndRoomsLabel(entry),
                ...this.lessonMarkers(entry),
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

.timetable-v3-results__warning {
    display: inline-flex;
    gap: 5px;
    align-items: center;
    font-size: 0.76rem;
    font-weight: 700;
    color: #92400e;
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

.timetable-v3-results__page-loading {
    position: absolute;
    inset: 0;
    z-index: 5;
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: center;
    min-height: 180px;
    padding: 24px;
    color: #134e4a;
    text-align: left;
    background: rgba(248, 250, 252, 0.9);
    border: 2px solid rgba(13, 148, 136, 0.38);
    border-radius: 12px;
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
    backdrop-filter: blur(3px);
}

.timetable-v3-results__page-loading strong,
.timetable-v3-results__page-loading span {
    display: block;
}

.timetable-v3-results__page-loading strong {
    font-size: 1rem;
    font-weight: 800;
}

.timetable-v3-results__page-loading span {
    margin-top: 3px;
    font-size: 0.82rem;
    color: #475569;
}

.timetable-v3-results__table-scroll {
    overflow: auto;
    overscroll-behavior-inline: contain;
    background: #fff;
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

.timetable-v3-results__period-heading,
.timetable-v3-results__period {
    position: sticky;
    left: 0;
    z-index: 1;
    width: 58px;
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
    font-size: 0.9rem;
}

.timetable-v3-results__table td {
    min-height: 68px;
    padding: 4px;
    vertical-align: top;
    background: inherit;
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

    .timetable-v3-results__page-loading {
        flex-direction: column;
        text-align: center;
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
