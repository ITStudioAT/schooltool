<template>
    <div class="students-timetable-v2-row-break" aria-hidden="true"></div>

    <v-col
        v-for="card in displayedCards"
        :key="card.key"
        cols="12"
        :md="card.mdColumns"
        class="students-timetable-v2-card-column"
        :class="{
            'students-timetable-v2-card-column--completed': card.courseGroup === 'completed',
        }">
        <v-card
            rounded="lg"
            class="students-timetable-v2-card students-timetable-v2-course-card"
            :class="`students-timetable-v2-course-card--${card.courseGroup}`">
            <v-card-title class="students-timetable-v2-course-card-title">
                <span class="students-timetable-v2-course-card-title__copy">
                    <span>{{ card.title }}</span>
                    <small v-if="card.courseGroup === 'completed'">— zum Auswählen anklicken</small>
                </span>
                <span class="students-timetable-v2-course-card-title__meta">
                    <span v-if="card.courseGroup !== 'completed'" class="students-timetable-v2-course-card-title__summary">
                        {{ card.summaryLabel }}
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
            </v-card-title>
            <v-card-text class="students-timetable-v2-course-card__content">
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
                        variant="flat"
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
        studentCompletedCoursesLoading: {
            type: Boolean,
            default: false,
        },
        subjectRowsLoading: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['apply-course-selections'],

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
            const courseSelectionLimitSummary = this.courseSelectionLimitSummary(this.activeCourseSelections)

            return this.visibleCards.map((card) => {
                const selectedCourseSummary = this.selectedCourseSummaryForCard(card)

                return {
                    ...card,
                    actions: this.displayedCardActions(card, courseSelectionLimitSummary),
                    items: card.items.map((course) => this.displayedCourseItem(course, courseSelectionLimitSummary)),
                    mdColumns: card.mdColumns || this.courseCardMdColumns,
                    summaryLabel: `${selectedCourseSummary.countLabel} · ${selectedCourseSummary.hoursLabel}`,
                }
            })
        },
        activeCourseSelections() {
            return this.draftCourseSelections && typeof this.draftCourseSelections === 'object' && !Array.isArray(this.draftCourseSelections)
                ? this.draftCourseSelections
                : this.normalizedCourseSelections(this.courseSelections)
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
            this.$emit('apply-course-selections', this.activeCourseSelections)
        },
        resetDraftCourseSelections() {
            this.draftCourseSelections = null
        },
        courseSelected(course, courseSelections = this.activeCourseSelections) {
            if (course.unavailable) return false
            const selectionKeys = this.courseSelectionKeys(course)
            if (!selectionKeys.length) return course.defaultSelected === true
            if (selectionKeys.some((selectionKey) => courseSelections[selectionKey] === false)) return false
            if (selectionKeys.some((selectionKey) => courseSelections[selectionKey] === true)) return true

            return course.defaultSelected === true
        },
        displayedCourseItem(
            course,
            courseSelectionLimitSummary = this.courseSelectionLimitSummary(this.activeCourseSelections),
        ) {
            const selected = this.courseSelected(course)
            const disabled = this.courseSelectionDisabled(course, selected, courseSelectionLimitSummary)

            return {
                ...course,
                ariaDisabled: disabled ? 'true' : 'false',
                ariaPressed: selected ? 'true' : 'false',
                classes: this.courseClasses(course, selected, disabled),
                selected,
                title: this.courseSelectionDisabledLabel(course, disabled),
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
        courseSelectionDisabled(
            course,
            selected = this.courseSelected(course),
            courseSelectionLimitSummary = null,
        ) {
            if (course.unavailable) return true
            if (selected) return false

            return this.courseSelectionWouldExceedLimit(
                course,
                this.activeCourseSelections,
                courseSelectionLimitSummary,
            )
        },
        courseSelectionDisabledLabel(course, disabled) {
            if (course.unavailableReason === 'prerequisite') return 'Voraussetzung nicht erfüllt'
            if (course.unavailable) return 'Kein angebotenes Modul vorhanden'
            if (disabled) return 'Maximum 10 Module / 30 Stunden erreicht'

            return undefined
        },
        courseSelectionWouldExceedLimit(
            course,
            courseSelections = this.activeCourseSelections,
            courseSelectionLimitSummary = null,
        ) {
            if (!['completed', 'missing', 'semester', 'planned'].includes(course.courseGroup)) return false
            if (course.unavailable) return false
            if (this.courseSelected(course, courseSelections)) return false

            const selectedCourseLimitSummary = courseSelectionLimitSummary
                || this.courseSelectionLimitSummary(courseSelections)

            return selectedCourseLimitSummary.count + 1 > 10
                || selectedCourseLimitSummary.hours + this.courseHoursNumber(course) > 30
        },
        courseSelectionLimitSummary(courseSelections = this.activeCourseSelections) {
            const selectedCourses = this.visibleCards
                .flatMap((card) => card.items)
                .filter((courseItem) => ['completed', 'missing', 'semester', 'planned'].includes(courseItem.courseGroup))
                .filter((courseItem) => this.courseSelected(courseItem, courseSelections))

            return {
                count: selectedCourses.length,
                hours: selectedCourses.reduce((hours, courseItem) => hours + this.courseHoursNumber(courseItem), 0),
            }
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
        displayedCardActions(card, courseSelectionLimitSummary = null) {
            if (!card.actions?.length) return []

            return card.actions.map((action) => ({
                ...action,
                disabled: this.courseGroupActionDisabled(
                    card.courseGroup,
                    action.selected,
                    courseSelectionLimitSummary,
                ),
            }))
        },
        courseGroupActionDisabled(courseGroup, selected, courseSelectionLimitSummary = null) {
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
                    .some((course) => this.courseSelectionWouldExceedLimit(
                        course,
                        courseSelections,
                        courseSelectionLimitSummary,
                    ))
            }

            return courseItems.every((course) => !this.courseSelected(course))
        },
        selectedCourseSummaryForCard(card) {
            return this.courseItemsSummary(card.items.filter((course) => this.courseSelected(course)))
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
    border: 1px solid #e7e9ef;
    background: #ffffff;
    box-shadow: none;
}

.students-timetable-v2-card :deep(.v-card-text) {
    flex: 1 1 auto;
}

.students-timetable-v2-card-column {
    display: flex !important;
    align-self: stretch;
    min-width: 0;
}

.students-timetable-v2-card-column--completed {
    flex: 0 0 100%;
    max-width: 100%;
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
    gap: 10px;
    flex-wrap: wrap;
    min-height: 44px;
    border-bottom: 1px solid #e7e9ef;
    padding: 10px 16px;
    color: #1e2433;
    font-size: 0.84rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.students-timetable-v2-course-card-title__copy {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.students-timetable-v2-course-card-title__copy small {
    color: #8991a3;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0;
    text-transform: none;
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

.students-timetable-v2-course-card-title__summary {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
}

.students-timetable-v2-course-card--completed .students-timetable-v2-course-card-title {
    border-bottom: 0;
    padding: 16px 18px 6px;
    background: #ffffff;
    font-size: 0.94rem;
    letter-spacing: 0;
    text-transform: none;
}

.students-timetable-v2-course-card--missing {
    border-color: #f3b9b3;
}

.students-timetable-v2-course-card--missing .students-timetable-v2-course-card-title {
    border-bottom-color: #f3b9b3;
    background: #fdecea;
    color: #8f1f16;
}

.students-timetable-v2-course-card--planned {
    border-color: #d7dae2;
}

.students-timetable-v2-course-card--planned .students-timetable-v2-course-card-title {
    border-bottom-color: #d7dae2;
    background: #f1f2f6;
    color: #5b6472;
}

.students-timetable-v2-course-card--semester {
    border-color: #bbf7d0;
}

.students-timetable-v2-course-card--semester .students-timetable-v2-course-card-title {
    border-bottom-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}

.students-timetable-v2-course-card--additional {
    border-color: #c7c9f5;
}

.students-timetable-v2-course-card--additional .students-timetable-v2-course-card-title {
    border-bottom-color: #c7c9f5;
    background: #eef1ff;
    color: #4338ca;
}

.students-timetable-v2-course-card__content {
    min-height: 64px;
    padding: 14px 16px !important;
}

.students-timetable-v2-course-card--completed .students-timetable-v2-course-card__content {
    padding: 8px 18px 18px !important;
}

.students-timetable-v2-completed-courses__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.students-timetable-v2-completed-courses__item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #d7dae2;
    border-radius: 8px;
    padding: 5px 10px;
    background: #ffffff;
    color: #3d4451;
    font-size: 0.78rem;
    font-weight: 600;
}

.students-timetable-v2-completed-courses__item--completed {
    border: 1.5px dashed #c7cbd6;
}

.students-timetable-v2-completed-courses__item--missing {
    border-color: #f3b9b3;
    color: #8f1f16;
}

.students-timetable-v2-completed-courses__item--semester {
    border-color: #bbf7d0;
    color: #15803d;
}

.students-timetable-v2-completed-courses__item--planned {
    border-color: #d7dae2;
    color: #5b6472;
}

.students-timetable-v2-completed-courses__item--additional {
    border-color: #c7c9f5;
    color: #4338ca;
}

.students-timetable-v2-completed-courses__item--toggle {
    cursor: pointer;
}

.students-timetable-v2-completed-courses__item--deselected {
    background: #ffffff !important;
    box-shadow: none;
}

.students-timetable-v2-completed-courses__item--selected {
    border-color: var(--schedule-accent-dark) !important;
    background: var(--schedule-accent) !important;
    color: #ffffff !important;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.38);
}

.students-timetable-v2-completed-courses__item--selected .students-timetable-v2-completed-courses__item-meta {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}

.students-timetable-v2-completed-courses__item--limit-disabled {
    cursor: not-allowed;
    border-color: #d7dae2 !important;
    background: #f1f2f6 !important;
    color: #8991a3 !important;
    box-shadow: none;
    opacity: 0.74;
}

.students-timetable-v2-completed-courses__item--limit-disabled span:first-child {
    text-decoration: none;
}

.students-timetable-v2-completed-courses__item--unavailable {
    cursor: not-allowed;
    border-color: #f3b9b3 !important;
    background: #fdecea !important;
    color: #8f1f16 !important;
    box-shadow: none;
}

.students-timetable-v2-completed-courses__item--unavailable span:first-child {
    text-decoration: none;
}

.students-timetable-v2-completed-courses__item-meta {
    border-radius: 999px;
    padding: 1px 6px;
    background: #f1f2f6;
    color: #8991a3;
    font-size: 0.7rem;
    font-weight: 800;
}

.students-timetable-v2-completed-courses__loading,
.students-timetable-v2-completed-courses__alert {
    margin-top: 2px;
}

@media (max-width: 960px) {
    .students-timetable-v2-course-card-title {
        align-items: flex-start;
    }
}
</style>
