<template>
    <div class="students-timetable-v2-row-break" aria-hidden="true"></div>

    <v-col
        v-for="card in displayedCards"
        :key="card.key"
        cols="12"
        :md="card.mdColumns"
        class="students-timetable-v2-card-column">
        <v-card rounded="lg" class="students-timetable-v2-card">
            <v-card-title class="students-timetable-v2-course-card-title">
                <span>{{ card.title }}</span>
                <span v-if="card.actions?.length" class="students-timetable-v2-course-card-title__meta">
                    <span class="students-timetable-v2-course-card-title__chips">
                        <v-chip
                            v-for="summaryChip in card.summaryChips"
                            :key="summaryChip.key"
                            size="x-small"
                            :color="summaryChip.color"
                            variant="tonal">
                            {{ summaryChip.label }}
                        </v-chip>
                    </span>
                    <span class="students-timetable-v2-course-card-title__actions">
                        <v-btn
                            v-for="action in card.actions"
                            :key="action.key"
                            :icon="action.icon"
                            size="x-small"
                            density="compact"
                            variant="tonal"
                            :color="action.color"
                            class="students-timetable-v2-course-selection-interactive"
                            :title="action.title"
                            :disabled="action.disabled"
                            @click.stop="setGroupSelection(card.courseGroup, action.selected)" />
                    </span>
                </span>
                <span v-else class="students-timetable-v2-course-card-title__chips">
                    <v-chip
                        v-for="summaryChip in card.summaryChips"
                        :key="summaryChip.key"
                        size="x-small"
                        :color="summaryChip.color"
                        variant="tonal">
                        {{ summaryChip.label }}
                    </v-chip>
                </span>
            </v-card-title>
            <v-card-text>
                <v-progress-linear
                    v-if="studentCompletedCoursesLoading"
                    indeterminate
                    color="primary"
                    class="students-timetable-v2-completed-courses__loading" />
                <v-progress-linear
                    v-else-if="subjectRowsLoading"
                    indeterminate
                    color="primary"
                    class="students-timetable-v2-completed-courses__loading" />
                <v-alert
                    v-else-if="courseCardsError"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="students-timetable-v2-completed-courses__alert">
                    {{ courseCardsError }}
                </v-alert>
                <div v-else-if="card.items.length" class="students-timetable-v2-completed-courses__list">
                    <v-chip
                        v-for="course in card.items"
                        :key="course.key"
                        :color="course.color"
                        :variant="course.variant"
                        class="students-timetable-v2-completed-courses__item students-timetable-v2-completed-courses__item--toggle students-timetable-v2-course-selection-interactive"
                        :class="course.classes"
                        role="button"
                        :aria-pressed="course.ariaPressed"
                        :aria-disabled="course.ariaDisabled"
                        :title="course.title"
                        @click="toggleCourse(course)">
                        <v-icon v-if="course.selected" icon="mdi-check" size="14" />
                        <span>{{ course.label }}</span>
                        <span v-if="course.meta" class="students-timetable-v2-completed-courses__item-meta">
                            {{ course.meta }}
                        </span>
                    </v-chip>
                </div>
                <v-alert
                    v-else-if="card.emptyLabel"
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="students-timetable-v2-completed-courses__alert">
                    {{ card.emptyLabel }}
                </v-alert>
            </v-card-text>
        </v-card>
    </v-col>

    <v-col v-if="displayedCards.length" cols="12" class="students-timetable-v2-card-column">
        <v-alert
            type="info"
            variant="tonal"
            density="compact"
            icon="mdi-counter">
            <div class="students-timetable-v2-course-selection-summary">
                <span>Ausgewählt</span>
                <v-chip size="x-small" color="primary" variant="tonal">
                    {{ selectedCourseLimitSummary.countLabel }}
                </v-chip>
                <v-chip size="x-small" color="primary" variant="tonal">
                    {{ selectedCourseLimitSummary.hoursLabel }}
                </v-chip>
                <span>Maximal 10/30</span>
                <v-btn
                    v-if="courseSelectionDraftChanged"
                    color="success"
                    variant="flat"
                    size="default"
                    density="default"
                    prepend-icon="mdi-check"
                    class="students-timetable-v2-course-selection-draft-action"
                    :disabled="pageActionsDisabled"
                    @click="$emit('apply-course-selections', activeCourseSelections)">
                    Übernehmen
                </v-btn>
                <v-btn
                    v-if="courseSelectionDraftChanged"
                    color="warning"
                    variant="tonal"
                    size="default"
                    density="default"
                    prepend-icon="mdi-close"
                    class="students-timetable-v2-course-selection-draft-action"
                    :disabled="pageActionsDisabled"
                    @click="resetDraftCourseSelections">
                    Abbruch
                </v-btn>
            </div>
        </v-alert>
    </v-col>
</template>

<script>
export default {
    props: {
        cards: {
            type: Array,
            required: true,
        },
        courseCardMdColumns: {
            type: Number,
            required: true,
        },
        courseCardsError: {
            type: String,
            default: '',
        },
        courseSelections: {
            type: Object,
            default: () => ({}),
        },
        pageActionsDisabled: {
            type: Boolean,
            default: false,
        },
        studentCompletedCoursesLoading: {
            type: Boolean,
            default: false,
        },
        subjectRowsLoading: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['apply-course-selections', 'draft-change', 'draft-selections-change'],

    data() {
        return {
            draftCourseSelections: null,
        }
    },

    computed: {
        visibleCards() {
            return this.cards.filter((card) => card.visible)
        },
        displayedCards() {
            return this.visibleCards.map((card) => ({
                ...card,
                actions: this.displayedCardActions(card),
                items: card.items.map((course) => this.displayedCourseItem(course)),
                mdColumns: card.mdColumns || this.courseCardMdColumns,
                summaryChips: card.summaryChipsVisible === false
                    ? []
                    : this.courseSelectionCardSummaryChips(this.selectedCourseSummaryForCard(card), this.cardSummaryColor(card)),
            }))
        },
        activeCourseSelections() {
            return this.draftCourseSelections && typeof this.draftCourseSelections === 'object' && !Array.isArray(this.draftCourseSelections)
                ? this.draftCourseSelections
                : this.normalizedCourseSelections(this.courseSelections)
        },
        courseSelectionDraftChanged() {
            if (!this.draftCourseSelections || typeof this.draftCourseSelections !== 'object' || Array.isArray(this.draftCourseSelections)) {
                return false
            }

            return this.courseSelectionSignature(this.draftCourseSelections)
                !== this.courseSelectionSignature(this.courseSelections)
        },
        selectedCourseLimitItems() {
            return this.visibleCards
                .flatMap((card) => card.items)
                .filter((course) => ['completed', 'missing', 'semester', 'planned'].includes(course.courseGroup))
                .filter((course) => this.courseSelected(course, this.activeCourseSelections))
        },
        selectedCourseLimitSummary() {
            return this.courseItemsSummary(this.selectedCourseLimitItems)
        },
        cardRosterSignature() {
            return JSON.stringify(this.visibleCards.map((card) => ({
                courseGroup: card.courseGroup,
                items: card.items.map((course) => ({
                    courseGroup: course.courseGroup,
                    key: course.key,
                    selectionKey: course.selectionKey,
                    selectionKeys: this.courseSelectionKeys(course),
                    unavailable: course.unavailable === true,
                })),
                key: card.key,
            })))
        },
    },

    watch: {
        cardRosterSignature() {
            this.resetDraftCourseSelections()
        },
        courseSelections: {
            deep: true,
            handler() {
                this.resetDraftCourseSelections()
            },
        },
    },

    methods: {
        normalizedCourseSelections(courseSelections = {}) {
            const normalizedSelections = courseSelections && typeof courseSelections === 'object' && !Array.isArray(courseSelections)
                ? courseSelections
                : {}

            return Object.fromEntries(
                Object.entries(normalizedSelections)
                    .filter(([, selected]) => selected === true || selected === false),
            )
        },
        selectionSignatureEntries(courseSelections = {}) {
            return Object.entries(this.normalizedCourseSelections(courseSelections))
                .sort(([firstKey], [secondKey]) => firstKey.localeCompare(secondKey, 'de-AT'))
        },
        courseSelectionSignature(courseSelections = {}) {
            return JSON.stringify(this.selectionSignatureEntries(courseSelections))
        },
        uniqueValues(values) {
            return (Array.isArray(values) ? values : []).filter((value, index, allValues) => allValues.indexOf(value) === index)
        },
        courseSelectionKeys(course) {
            const selectionKeys = Array.isArray(course?.selectionKeys) ? course.selectionKeys : []

            return this.uniqueValues([
                course?.selectionKey,
                ...selectionKeys,
            ].map((selectionKey) => String(selectionKey || '').trim()).filter(Boolean))
        },
        draftCourseSelectionOverrides() {
            return {
                ...this.normalizedCourseSelections(this.activeCourseSelections),
            }
        },
        setDraftCourseSelections(courseSelections = {}) {
            this.draftCourseSelections = this.normalizedCourseSelections(courseSelections)
            this.$emit('draft-change', this.courseSelectionDraftChanged)
            this.$emit('draft-selections-change', this.activeCourseSelections)
        },
        resetDraftCourseSelections() {
            this.draftCourseSelections = null
            this.$emit('draft-change', false)
            this.$emit('draft-selections-change', null)
        },
        courseSelected(course, courseSelections = this.activeCourseSelections) {
            if (course.unavailable) return false
            const selectionKeys = this.courseSelectionKeys(course)
            if (!selectionKeys.length) return course.defaultSelected === true
            if (selectionKeys.some((selectionKey) => courseSelections[selectionKey] === false)) return false
            if (selectionKeys.some((selectionKey) => courseSelections[selectionKey] === true)) return true

            return course.defaultSelected === true
        },
        displayedCourseItem(course) {
            const selected = this.courseSelected(course)
            const disabled = this.courseSelectionDisabled(course, selected)

            return {
                ...course,
                ariaDisabled: disabled ? 'true' : 'false',
                ariaPressed: selected ? 'true' : 'false',
                classes: this.courseClasses(course, selected, disabled),
                color: this.courseColor(course, selected),
                selected,
                title: this.courseSelectionDisabledLabel(course, disabled),
                variant: this.courseVariant(course, selected),
            }
        },
        courseClasses(course, selected, disabled) {
            return {
                [`students-timetable-v2-completed-courses__item--${course.courseGroup}`]: true,
                'students-timetable-v2-completed-courses__item--selected': selected && (course.courseGroup === 'additional' || !course.unavailable),
                'students-timetable-v2-completed-courses__item--deselected': !selected,
                'students-timetable-v2-completed-courses__item--limit-disabled': disabled,
                'students-timetable-v2-completed-courses__item--unavailable': course.unavailable,
            }
        },
        courseColor(course) {
            return course.color
        },
        courseVariant(course, selected) {
            if (course.courseGroup === 'additional') return selected ? 'tonal' : 'outlined'

            return selected || course.unavailable ? 'tonal' : 'outlined'
        },
        courseSelectionDisabled(course, selected = this.courseSelected(course)) {
            if (course.unavailable) return true
            if (selected) return false

            return this.courseSelectionWouldExceedLimit(course, this.activeCourseSelections)
        },
        courseSelectionDisabledLabel(course, disabled) {
            if (course.unavailableReason === 'prerequisite') return 'Voraussetzung nicht erfüllt'
            if (course.unavailable) return 'Kein angebotenes Modul vorhanden'
            if (disabled) return 'Maximum 10 Module / 30 Stunden erreicht'

            return undefined
        },
        courseSelectionWouldExceedLimit(course, courseSelections = this.activeCourseSelections) {
            if (!['completed', 'missing', 'semester', 'planned'].includes(course.courseGroup)) return false
            if (course.unavailable) return false
            if (this.courseSelected(course, courseSelections)) return false

            const selectedCourses = this.visibleCards
                .flatMap((card) => card.items)
                .filter((courseItem) => ['completed', 'missing', 'semester', 'planned'].includes(courseItem.courseGroup))
                .filter((courseItem) => this.courseSelected(courseItem, courseSelections))
            const selectedCourseCount = selectedCourses.length
            const selectedCourseHours = selectedCourses
                .reduce((hours, courseItem) => hours + this.courseHoursNumber(courseItem), 0)

            return selectedCourseCount + 1 > 10
                || selectedCourseHours + this.courseHoursNumber(course) > 30
        },
        toggleCourse(course) {
            if (!course?.selectionKey) return
            if (this.courseSelectionDisabled(course)) return

            const courseSelections = this.draftCourseSelectionOverrides()
            const selectionKeys = this.courseSelectionKeys(course)

            if (this.courseSelected(course, courseSelections)) {
                if (course.defaultSelected === true) {
                    selectionKeys.forEach((selectionKey) => {
                        courseSelections[selectionKey] = false
                    })
                } else {
                    selectionKeys.forEach((selectionKey) => {
                        delete courseSelections[selectionKey]
                    })
                }
            } else {
                if (this.courseSelectionWouldExceedLimit(course, courseSelections)) return

                if (course.defaultSelected === true) {
                    selectionKeys.forEach((selectionKey) => {
                        delete courseSelections[selectionKey]
                    })
                } else {
                    selectionKeys.forEach((selectionKey) => {
                        delete courseSelections[selectionKey]
                    })
                    courseSelections[course.selectionKey] = true
                }
            }

            this.setDraftCourseSelections(courseSelections)
        },
        setGroupSelection(courseGroup, selected) {
            const courseSelections = this.draftCourseSelectionOverrides()
            const courseItems = this.visibleCards
                .filter((card) => card.courseGroup === courseGroup)
                .flatMap((card) => card.items)

            courseItems.forEach((course) => {
                if (!course.selectionKey) return
                if (selected && course.unavailable) return
                const selectionKeys = this.courseSelectionKeys(course)

                if (selected) {
                    if (this.courseSelectionWouldExceedLimit(course, courseSelections)) return

                    if (course.defaultSelected === true) {
                        selectionKeys.forEach((selectionKey) => {
                            delete courseSelections[selectionKey]
                        })
                    } else {
                        selectionKeys.forEach((selectionKey) => {
                            delete courseSelections[selectionKey]
                        })
                        courseSelections[course.selectionKey] = true
                    }
                } else if (course.defaultSelected === true) {
                    selectionKeys.forEach((selectionKey) => {
                        courseSelections[selectionKey] = false
                    })
                } else {
                    selectionKeys.forEach((selectionKey) => {
                        delete courseSelections[selectionKey]
                    })
                }
            })

            this.setDraftCourseSelections(courseSelections)
        },
        displayedCardActions(card) {
            if (!card.actions?.length) return []

            return card.actions.map((action) => ({
                ...action,
                disabled: this.courseGroupActionDisabled(card.courseGroup, action.selected),
            }))
        },
        courseGroupActionDisabled(courseGroup, selected) {
            const courseItems = this.visibleCards
                .filter((card) => card.courseGroup === courseGroup)
                .flatMap((card) => card.items)
                .filter((course) => !course.unavailable)

            if (!courseItems.length) return true

            if (selected) {
                if (courseItems.every((course) => this.courseSelected(course))) return true

                const courseSelections = this.draftCourseSelectionOverrides()

                return courseItems
                    .filter((course) => !this.courseSelected(course, courseSelections))
                    .some((course) => this.courseSelectionWouldExceedLimit(course, courseSelections))
            }

            return courseItems.every((course) => !this.courseSelected(course))
        },
        selectedCourseSummaryForCard(card) {
            return this.courseItemsSummary(card.items.filter((course) => this.courseSelected(course)))
        },
        cardSummaryColor(card) {
            return 'success'
        },
        courseSelectionCardSummaryChips(summary, color) {
            return [
                {
                    color,
                    key: 'count',
                    label: summary.countLabel,
                },
                {
                    color,
                    key: 'hours',
                    label: summary.hoursLabel,
                },
            ]
        },
        courseItemsSummary(courseItems) {
            const courses = Array.isArray(courseItems) ? courseItems : []
            const count = courses.length
            const hours = courses.reduce((sum, course) => sum + this.courseHoursNumber(course), 0)

            return {
                count,
                countLabel: `${count} ${count === 1 ? 'Modul' : 'Module'}`,
                hours,
                hoursLabel: `${this.formatHours(hours)} Std.`,
            }
        },
        courseHoursNumber(course) {
            const numericHours = Number(course?.hoursNumber ?? course?.hours ?? course?.hours_per_week ?? 0)

            return Number.isFinite(numericHours) ? numericHours : 0
        },
        formatHours(hours) {
            return Number(hours || 0).toLocaleString('de-AT', {
                maximumFractionDigits: 1,
                minimumFractionDigits: 0,
            })
        },
    },
}
</script>

<style scoped>
.students-timetable-v2-card {
    display: flex;
    flex: 1 1 auto;
    width: 100%;
    height: 100%;
    flex-direction: column;
    border: 1px solid rgba(37, 99, 235, 0.12);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
}

.students-timetable-v2-card :deep(.v-card-text) {
    flex: 1 1 auto;
}

.students-timetable-v2-card-column {
    display: flex !important;
    align-self: stretch;
    min-width: 0;
}

.students-timetable-v2-row-break {
    flex-basis: 100%;
    width: 0;
    height: 0;
    padding: 0;
}

.students-timetable-v2-course-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-course-card-title__chips {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.students-timetable-v2-course-card-title__meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-left: auto;
}

.students-timetable-v2-course-card-title__actions {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.students-timetable-v2-course-selection-summary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 0.82rem;
    font-weight: 800;
}

.students-timetable-v2-completed-courses__list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.students-timetable-v2-course-card-footer {
    min-height: 0;
    padding: 0 16px 14px;
    color: rgba(15, 23, 42, 0.62);
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.25;
}

.students-timetable-v2-completed-courses__item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(14, 165, 233, 0.18);
    border-radius: 8px;
    padding: 5px 7px;
    background: rgba(248, 250, 252, 0.88);
    color: #0f172a;
    font-size: 0.78rem;
    font-weight: 700;
}

.students-timetable-v2-completed-courses__item--completed {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--missing {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--semester {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--additional {
    border-color: rgba(22, 163, 74, 0.18);
    background: rgba(240, 253, 244, 0.78);
}

.students-timetable-v2-completed-courses__item--toggle {
    cursor: pointer;
}

.students-timetable-v2-completed-courses__item--deselected {
    background: rgba(255, 255, 255, 0.86);
    box-shadow: none;
}

.students-timetable-v2-completed-courses__item--selected {
    border-color: #15803d !important;
    background: #16a34a !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.34);
}

.students-timetable-v2-completed-courses__item--selected .students-timetable-v2-completed-courses__item-meta {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}

.students-timetable-v2-completed-courses__item--limit-disabled {
    cursor: not-allowed;
    border-color: rgba(14, 116, 144, 0.42);
    background: rgba(236, 254, 255, 0.96);
    color: #155e75;
}

.students-timetable-v2-completed-courses__item--limit-disabled span:first-child {
    text-decoration: none;
}

.students-timetable-v2-completed-courses__item--unavailable {
    cursor: not-allowed;
    border-color: rgba(220, 38, 38, 0.38);
    background: rgba(254, 226, 226, 0.94);
    color: #991b1b;
}

.students-timetable-v2-completed-courses__item--unavailable span:first-child {
    text-decoration: none;
}

.students-timetable-v2-completed-courses__item-meta {
    border-radius: 999px;
    padding: 1px 6px;
    background: rgba(255, 255, 255, 0.72);
    font-size: 0.7rem;
    font-weight: 800;
}

.students-timetable-v2-completed-courses__loading,
.students-timetable-v2-completed-courses__alert {
    margin-top: 2px;
}
</style>
