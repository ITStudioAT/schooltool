<template>
    <section
        class="student-evaluation-settings"
        :class="{ 'student-evaluation-settings--criteria': currentStep === 'criteria' }">
        <div
            v-if="currentStep !== 'criteria'"
            class="student-evaluation-settings__title">
            <div class="student-evaluation-settings__title-label">
                <v-icon icon="mdi-auto-fix" />
                <div>
                    <p>Automatischer Stundenplan</p>
                    <h3>{{ currentStepTitle }}</h3>
                </div>
            </div>
        </div>

        <template v-else>
            <div class="student-evaluation-settings__automatic-card">
                <v-icon icon="mdi-calendar-clock" size="22" />
                <h2>Automatischer Stundenplan</h2>
                <span class="student-evaluation-settings__automatic-stars" aria-hidden="true">
                    <v-icon icon="mdi-star-four-points" size="10" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--1" />
                    <v-icon icon="mdi-star-four-points" size="14" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--2" />
                    <v-icon icon="mdi-star-four-points" size="8" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--3" />
                </span>
            </div>

            <div class="student-evaluation-settings__summary-card">
                <div class="student-evaluation-settings__summary-header">
                    <div class="student-evaluation-settings__summary-title">
                        <v-icon icon="mdi-tune-variant" size="18" />
                        Bewertungskriterien
                    </div>
                    <v-btn
                        class="student-evaluation-settings__summary-cog"
                        icon="mdi-cog-outline"
                        variant="text"
                        density="comfortable"
                        color="primary"
                        title="Einstellungen"
                        aria-label="Einstellungen"
                        @click="criteriaEditorDialogOpen = true" />
                </div>

            <v-skeleton-loader v-if="loading" type="list-item-three-line" />
            <div v-else-if="activeCriteria.length" class="student-evaluation-settings__summary-list">
                <span
                    v-for="(criterion, index) in activeCriteria"
                    :key="criterion.key"
                    class="student-evaluation-settings__summary-item">
                    <span class="student-evaluation-settings__summary-rank">{{ index + 1 }}</span>
                    <span class="student-evaluation-settings__summary-label">{{ criterion.label }}</span>
                    <span v-if="criterionOptionLabel(criterion)" class="student-evaluation-settings__summary-option">
                        {{ criterionOptionLabel(criterion) }}
                    </span>
                </span>
            </div>
            <div v-else class="student-evaluation-settings__summary-empty">
                Keine Bewertungskriterien aktiv
            </div>
            </div>

            <div class="student-evaluation-settings__course-panel">
                <div class="student-evaluation-settings__course-panel-head">
                    <div class="student-evaluation-settings__course-panel-title">
                        {{ courseSummaryTitle }}
                        <v-chip size="x-small" color="primary" variant="tonal">
                            {{ effectiveSelectedCourseCount }} / {{ effectiveCourseSummaryTotal }}
                        </v-chip>
                        <v-chip v-if="selectedCourseHoursLabel" size="x-small" color="default" variant="tonal">
                            {{ selectedCourseHoursLabel }}
                        </v-chip>
                    </div>
                    <v-btn
                        variant="text"
                        color="primary"
                        size="small"
                        prepend-icon="mdi-restore"
                        :disabled="allSelectableCoursesSelected"
                        @click="resetCourseSelectionToDefault">
                        Zurücksetzen
                    </v-btn>
                </div>

                <div class="student-evaluation-settings__course-section-grid">
                    <div
                        v-for="section in displayedCourseSections"
                        :key="section.key"
                        class="student-evaluation-settings__course-section-card">
                        <div class="student-evaluation-settings__course-section-title">
                            {{ section.title }}
                            <span class="student-evaluation-settings__course-section-count">
                                {{ displayedCourseSectionSelectedCount(section) }}
                            </span>
                        </div>

                        <div class="student-evaluation-settings__course-table">
                            <div class="student-evaluation-settings__course-table-row student-evaluation-settings__course-table-row--head">
                                <span>Aktiv</span>
                                <span>Code</span>
                                <span>Bezeichnung</span>
                                <span>Zweig</span>
                                <span>Std.</span>
                            </div>
                            <div
                                v-for="course in section.items"
                                :key="courseSelectionKey(course)"
                                class="student-evaluation-settings__course-table-row">
                                <span>
                                    <v-checkbox
                                        :model-value="courseSelectedByDefault(course)"
                                        color="primary"
                                        density="compact"
                                        hide-details
                                        @update:model-value="setCourseSelected(course, $event)" />
                                </span>
                                <strong>{{ course.code || '-' }}</strong>
                                <span>{{ course.name || course.code || '-' }}</span>
                                <span>{{ courseBranchLabel(course) }}</span>
                                <span>{{ courseHoursValue(course) }}</span>
                            </div>
                            <div v-if="!section.items.length" class="student-evaluation-settings__course-empty">
                                Keine Kurse gefunden.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <v-alert v-if="error" type="error" variant="tonal" density="comfortable" class="mb-3">
            {{ error }}
        </v-alert>

        <v-dialog v-model="criteriaEditorDialogOpen" max-width="980" scrollable persistent>
            <v-card rounded="lg">
                <v-card-title class="student-evaluation-settings__editor-title">
                    <v-icon icon="mdi-tune-variant" />
                    Bewertungskriterien bearbeiten
                </v-card-title>
                <v-card-text>
                    <v-skeleton-loader v-if="loading" type="list-item-three-line@5" />
                    <div v-else class="student-evaluation-settings__list">
                        <div
                            v-for="(criterion, index) in criteria"
                            :key="criterion.key"
                            class="student-evaluation-settings__item"
                            :class="{
                                'student-evaluation-settings__item--active': criterion.enabled,
                                'student-evaluation-settings__item--disabled': !criterion.enabled,
                            }">
                            <div class="student-evaluation-settings__priority">
                                {{ index + 1 }}
                            </div>

                            <v-switch
                                :model-value="criterion.enabled"
                                color="success"
                                density="compact"
                                hide-details
                                inset
                                class="student-evaluation-settings__switch"
                                @update:model-value="setCriterionEnabled(criterion, $event)" />

                            <div class="student-evaluation-settings__content">
                                <div class="student-evaluation-settings__header">
                                    <div>
                                        <div class="student-evaluation-settings__label">{{ criterion.label }}</div>
                                        <div class="student-evaluation-settings__description">
                                            {{ criterion.description }}
                                        </div>
                                    </div>

                                    <v-chip size="small" :color="criterion.enabled ? 'success' : 'default'" variant="tonal">
                                        {{ criterion.enabled ? 'Aktiv' : 'Inaktiv' }}
                                    </v-chip>
                                </div>

                                <v-select
                                    v-if="criterion.options.length"
                                    v-model="criterion.option"
                                    :items="criterion.options"
                                    item-title="label"
                                    item-value="value"
                                    label="Variante"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    class="student-evaluation-settings__option" />
                            </div>

                            <div class="student-evaluation-settings__actions">
                                <v-btn
                                    icon="mdi-arrow-up"
                                    size="small"
                                    variant="text"
                                    color="primary"
                                    :disabled="index === 0"
                                    title="Nach oben"
                                    @click="moveCriterion(index, -1)" />
                                <v-btn
                                    icon="mdi-arrow-down"
                                    size="small"
                                    variant="text"
                                    color="primary"
                                    :disabled="index === criteria.length - 1"
                                    title="Nach unten"
                                    @click="moveCriterion(index, 1)" />
                            </div>
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn
                        color="primary"
                        variant="flat"
                        :loading="saving"
                        :disabled="saving"
                        @click="closeCriteriaEditorDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <div v-if="currentStep === 'courses'" class="student-course-selection">
            <div class="student-course-selection__head">
                <div>
                    <div class="student-course-selection__label">Vorgesehene Kurse</div>
                    <div class="student-course-selection__description">
                        Wählen Sie die Kurse aus, die für den automatischen Stundenplan berücksichtigt werden sollen.
                    </div>
                </div>
                <v-chip size="small" color="info" variant="tonal">
                    {{ selectedCourseCount }} / {{ proposedCourses.length }}
                </v-chip>
            </div>

            <div v-if="proposedCourses.length" class="student-course-selection__grid">
                <label
                    v-for="course in proposedCourses"
                    :key="courseSelectionKey(course)"
                    class="student-course-selection__item"
                    :class="{ 'student-course-selection__item--selected': courseSelected(course) }">
                    <v-checkbox
                        :model-value="courseSelected(course)"
                        color="success"
                        density="compact"
                        hide-details
                        @update:model-value="setCourseSelected(course, $event)" />
                    <span class="student-course-selection__content">
                        <strong>{{ course.code || course.name || '-' }}</strong>
                        <span v-if="course.name && course.name !== course.code">{{ course.name }}</span>
                        <span class="student-course-selection__meta">
                            <v-chip v-if="courseHoursLabel(course)" size="x-small" color="primary" variant="tonal">
                                {{ courseHoursLabel(course) }}
                            </v-chip>
                            <v-chip v-if="course.semester" size="x-small" variant="tonal">S{{ course.semester }}</v-chip>
                            <v-chip v-if="course.branch && course.branch !== 'common'" size="x-small" variant="tonal">
                                {{ course.branch }}
                            </v-chip>
                        </span>
                    </span>
                </label>
            </div>

            <div v-else class="student-course-selection__empty">
                <v-icon size="20">mdi-information-outline</v-icon>
                <span>Keine vorgesehenen Kurse gefunden.</span>
            </div>
        </div>

        <div v-else-if="currentStep === 'result'" class="student-generated-timetable">
            <v-alert v-if="timetableError" type="error" variant="tonal" density="comfortable">
                {{ timetableError }}
            </v-alert>

            <v-skeleton-loader v-else-if="generatingTimetable" type="list-item-three-line@3" />

            <template v-else-if="generatedTimetable">
                <div class="student-generated-timetable__head">
                    <div>
                        <div class="student-generated-timetable__label">Stundenplan</div>
                        <div class="student-generated-timetable__description">
                            {{ generatedTimetable.statusMessage || 'Erster passender Stundenplan' }}
                        </div>
                    </div>
                    <div class="student-generated-timetable__controls">
                        <v-btn
                            icon="mdi-chevron-left"
                            size="small"
                            variant="tonal"
                            color="primary"
                            :disabled="!canMoveGeneratedTimetable(-1) || generatingTimetable"
                            title="Vorheriger Stundenplan"
                            @click="moveGeneratedTimetable(-1)" />
                        <span v-if="generatedTimetablePositionLabel" class="student-generated-timetable__counter">
                            {{ generatedTimetablePositionLabel }}
                        </span>
                        <v-btn
                            icon="mdi-chevron-right"
                            size="small"
                            variant="tonal"
                            color="primary"
                            :disabled="!canMoveGeneratedTimetable(1) || generatingTimetable"
                            title="Nächster Stundenplan"
                            @click="moveGeneratedTimetable(1)" />
                        <v-chip size="small" color="success" variant="tonal">
                            {{ generatedTimetableTypeLabel }}
                        </v-chip>
                    </div>
                </div>

                <div v-if="activeGeneratedQualityCriteria.length" class="student-generated-criteria">
                    <button
                        v-for="criterion in activeGeneratedQualityCriteria"
                        :key="criterion.key"
                        type="button"
                        class="student-generated-criteria__card"
                        :class="{
                            'student-generated-criteria__card--selected': generatedQualityCriterionSelected(criterion),
                            'student-generated-criteria__card--reached': generatedQualityCriterionReached(criterion),
                            'student-generated-criteria__card--missed': generatedQualityCriterionSelected(criterion) && !generatedQualityCriterionReached(criterion),
                        }"
                        :disabled="generatingTimetable"
                        @click="toggleGeneratedQualityCriterion(criterion)">
                        <span class="student-generated-criteria__label">{{ criterion.label }}</span>
                        <span v-if="generatedQualityCriterionMeta(criterion)" class="student-generated-criteria__meta">
                            {{ generatedQualityCriterionMeta(criterion) }} erfüllen das Kriterium
                        </span>
                        <v-icon
                            v-if="generatedQualityCriterionSelected(criterion)"
                            :icon="generatedQualityCriterionReached(criterion) ? 'mdi-check-circle' : 'mdi-checkbox-marked-circle-outline'"
                            :color="generatedQualityCriterionReached(criterion) ? 'success' : 'primary'"
                            size="16"
                            class="student-generated-criteria__icon" />
                    </button>
                </div>

                <div
                    class="student-generated-timetable__grid"
                    :style="{ '--student-generated-weekdays': generatedTimetableWeekdays.length }">
                    <div class="student-generated-timetable__cell student-generated-timetable__cell--header">Std.</div>
                    <div
                        v-for="weekday in generatedTimetableWeekdays"
                        :key="`generated-header-${weekday.value}`"
                        class="student-generated-timetable__cell student-generated-timetable__cell--header">
                        {{ weekday.label }}
                    </div>

                    <template
                        v-for="hour in generatedTimetableHours"
                        :key="`generated-hour-${hour}`">
                        <div class="student-generated-timetable__cell student-generated-timetable__cell--time">
                            {{ hour }}.
                        </div>
                        <div
                            v-for="weekday in generatedTimetableWeekdays"
                            :key="`generated-${weekday.value}-${hour}`"
                            class="student-generated-timetable__cell"
                            :class="{
                                'student-generated-timetable__cell--filled': generatedTimetableDisplaySlot(weekday.value, hour),
                                'student-generated-timetable__cell--conflict': generatedSlotVisualConflictBlocks(generatedTimetableDisplaySlot(weekday.value, hour)).length,
                                'student-generated-timetable__cell--has-occasional': generatedTimetableOccasionalMarkers(weekday.value, hour).length,
                            }">
                            <template v-if="generatedTimetableDisplaySlot(weekday.value, hour)">
                                <div
                                    v-for="block in generatedSlotDisplayBlocks(generatedTimetableDisplaySlot(weekday.value, hour))"
                                    :key="generatedSlotBlockKey(block)"
                                    class="student-generated-timetable__entry"
                                    :class="{ 'student-generated-timetable__entry--conflict': block.isConflict === true }">
                                    <div class="student-generated-timetable__code">
                                        {{ generatedSlotTitle(block) }}
                                    </div>
                                    <div v-if="generatedSlotDetails(block)" class="student-generated-timetable__details">
                                        {{ generatedSlotDetails(block) }}
                                    </div>
                                </div>
                            </template>
                            <div
                                v-if="generatedTimetableOccasionalMarkers(weekday.value, hour).length"
                                class="student-generated-timetable__occasional-markers">
                                <span
                                    v-for="marker in generatedTimetableOccasionalMarkers(weekday.value, hour)"
                                    :key="marker.key"
                                    class="student-generated-timetable__occasional-marker"
                                    :class="{ 'student-generated-timetable__occasional-marker--additional': marker.isAdditionalCourse }"
                                    :title="generatedOccasionalMarkerTitle(marker)">
                                    {{ marker.code }}
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div v-else class="student-course-selection__empty">
                <v-icon size="20">mdi-information-outline</v-icon>
                <span>Für die ausgewählten Kurse wurde kein passender Stundenplan gefunden.</span>
            </div>
        </div>

        <div
            v-if="currentStep === 'criteria'"
            class="student-evaluation-settings__footer student-evaluation-settings__footer--automatic">
            <v-btn
                class="student-evaluation-settings__create-button"
                variant="flat"
                color="success"
                size="large"
                prepend-icon="mdi-calendar-clock"
                :loading="saving || generatingTimetable"
                :disabled="loading || saving || generatingTimetable"
                @click="continueToNextStep">
                Stundenplan erstellen
            </v-btn>
            <v-btn
                class="student-evaluation-settings__close-button ms-3"
                variant="tonal"
                color="primary"
                size="large"
                prepend-icon="mdi-close"
                :disabled="saving || generatingTimetable"
                @click="$emit('close')">
                Schließen
            </v-btn>
        </div>

        <div v-else class="student-evaluation-settings__footer">
            <v-btn
                variant="tonal"
                color="secondary"
                prepend-icon="mdi-arrow-left"
                rounded="pill"
                :disabled="saving"
                @click="handleBack">
                Zurück
            </v-btn>
            <v-btn
                color="primary"
                variant="flat"
                append-icon="mdi-arrow-right"
                rounded="pill"
                :loading="saving || generatingTimetable"
                :disabled="loading || saving || generatingTimetable"
                @click="continueToNextStep">
                {{ continueButtonLabel }}
            </v-btn>
        </div>
    </section>
</template>

<script>
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    props: {
        initialStep: {
            type: String,
            default: 'criteria',
            validator: value => ['criteria', 'courses', 'result'].includes(value),
        },
        initialSelectedCourseKeys: {
            type: Array,
            default: () => [],
        },
        initialSelectedQualityCriterionKeys: {
            type: Array,
            default: () => [],
        },
        defaultQualityCriterionSelection: {
            type: Boolean,
            default: true,
        },
        proposedCourses: {
            type: Array,
            default: () => [],
        },
        courseSummary: {
            type: Object,
            default: () => ({}),
        },
        courseSections: {
            type: Array,
            default: () => [],
        },
        selectionOverride: {
            type: Object,
            default: () => ({}),
        },
    },

    emits: [
        'close',
        'course-selection-change',
        'courses-selected',
        'quality-criteria-selection-change',
        'saved',
        'step-change',
    ],

    data() {
        return {
            currentStep: this.normalizedStep(this.initialStep),
            loading: false,
            saving: false,
            criteria: [],
            originalCriteria: [],
            selectedCourseKeys: this.normalizedCourseKeys(this.initialSelectedCourseKeys),
            selectedQualityCriterionKeys: this.normalizedCourseKeys(this.initialSelectedQualityCriterionKeys),
            generatedTimetable: null,
            timetableCounts: {},
            selectedTimetableType: null,
            selectedTimetableNumber: 1,
            defaultQualityCriterionSelectionApplied: false,
            generatingTimetable: false,
            timetableError: '',
            error: '',
            criteriaEditorDialogOpen: false,
        }
    },

    computed: {
        currentStepTitle() {
            if (this.currentStep === 'result') {
                return 'Erster Stundenplan'
            }

            return this.currentStep === 'courses' ? 'Vorgesehene Kurse' : 'Bewertungskriterien'
        },
        activeCriteria() {
            return this.criteria.filter(criterion => criterion.enabled === true)
        },
        continueButtonLabel() {
            return this.currentStep === 'result' ? 'Schließen' : 'Weiter'
        },
        selectedCourseCount() {
            return this.selectedCourseKeys.length
        },
        allCoursesSelectedByDefault() {
            return this.selectedCourseKeys.length === 0
        },
        allSelectableCoursesSelected() {
            if (this.allCoursesSelectedByDefault) {
                return true
            }

            return this.proposedCourses.every(course => this.selectedCourseKeys.includes(this.courseSelectionKey(course)))
        },
        effectiveSelectedCourseCount() {
            return this.selectedCourseKeys.length || this.effectiveCourseSummaryTotal
        },
        effectiveCourseSummaryTotal() {
            return Number(this.courseSummary?.total || this.proposedCourses.length || 0)
        },
        courseSummaryTitle() {
            return this.courseSummary?.title || 'Fehlende Kurse + Vorgesehene Kurse'
        },
        displayedCourseSections() {
            return Array.isArray(this.courseSections) ? this.courseSections : []
        },
        selectedCourseHoursLabel() {
            const hours = this.selectedCoursesForSummary()
                .reduce((totalHours, course) => totalHours + this.courseHoursNumber(course), 0)

            if (!hours) {
                return ''
            }

            return `${new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(hours)} Std.`
        },
        selectedCourses() {
            return this.proposedCourses.filter(course => this.courseSelected(course))
        },
        generatedTimetableWeekdays() {
            const weekdaysWithSlots = new Set(
                Object.values(this.generatedTimetable?.slots || {})
                    .map(slot => Number(slot?.courseGroup?.weekday))
                    .filter(Number.isFinite),
            )
            const showSaturday = weekdaysWithSlots.has(6)

            return [
                { value: 1, label: 'Mo' },
                { value: 2, label: 'Di' },
                { value: 3, label: 'Mi' },
                { value: 4, label: 'Do' },
                { value: 5, label: 'Fr' },
                { value: 6, label: 'Sa' },
            ].filter(weekday => weekday.value <= 5 || showSaturday)
        },
        generatedTimetableHours() {
            const hours = Object.values(this.generatedTimetable?.slots || {})
                .map(slot => Number(slot?.courseGroup?.hour))
                .filter(Number.isFinite)

            if (!hours.length) {
                return []
            }

            const firstHour = Math.min(...hours)
            const lastHour = Math.max(...hours)

            return Array.from({ length: (lastHour - firstHour) + 1 }, (value, index) => firstHour + index)
        },
        generatedTimetableTypeLabel() {
            return {
                full_green: 'Voller grüner Plan',
                green: 'Grüner Plan',
                conflict: 'Plan mit Konflikten',
            }[this.generatedTimetable?.type] || 'Stundenplan'
        },
        generatedTimetableNavigationType() {
            return this.generatedTimetable?.type || this.selectedTimetableType || null
        },
        generatedTimetableTotalCount() {
            return this.generatedTimetableTypeOrder()
                .reduce((totalCount, timetableType) => totalCount + this.generatedTimetableCountForType(timetableType), 0)
        },
        generatedTimetableAbsoluteNumber() {
            const timetableType = this.generatedTimetableNavigationType
            const timetableTypeIndex = this.generatedTimetableTypeOrder().indexOf(timetableType)

            if (timetableTypeIndex < 0) {
                return this.selectedTimetableNumber
            }

            const previousTypeCount = this.generatedTimetableTypeOrder()
                .slice(0, timetableTypeIndex)
                .reduce((totalCount, previousType) => totalCount + this.generatedTimetableCountForType(previousType), 0)

            return previousTypeCount + this.selectedTimetableNumber
        },
        generatedTimetablePositionLabel() {
            const totalCount = this.generatedTimetableTotalCount

            if (!totalCount) {
                return ''
            }

            return `${new Intl.NumberFormat('de-AT').format(this.generatedTimetableAbsoluteNumber)} / ${new Intl.NumberFormat('de-AT').format(totalCount)}`
        },
        activeGeneratedQualityCriteria() {
            const countersByKey = new Map((this.timetableCounts?.quality_counters || []).map(counter => [counter.key, counter]))

            return this.criteria
                .filter(criterion => criterion.enabled === true)
                .map(criterion => ({
                    ...criterion,
                    ...(countersByKey.get(criterion.key) || {}),
                }))
        },
    },

    watch: {
        initialStep(step) {
            this.moveToStep(step, false)
            this.ensureAutomaticTimetableForResultStep()
        },
        initialSelectedCourseKeys(courseKeys) {
            const selectedCourseKeys = this.normalizedCourseKeys(courseKeys)

            if (selectedCourseKeys.length || this.selectedCourseKeys.length) {
                this.selectedCourseKeys = selectedCourseKeys
            }
        },
        initialSelectedQualityCriterionKeys(criterionKeys) {
            const selectedCriterionKeys = this.normalizedCourseKeys(criterionKeys)

            if (selectedCriterionKeys.length || this.selectedQualityCriterionKeys.length) {
                this.selectedQualityCriterionKeys = selectedCriterionKeys
            }
        },
        proposedCourses() {
            if (this.currentStep === 'courses' && this.selectedCourseKeys.length === 0) {
                this.selectAllProposedCourses()
            }

            this.ensureAutomaticTimetableForResultStep()
        },
    },

    async mounted() {
        if (this.currentStep === 'courses') {
            this.selectAllProposedCourses()
        }

        await this.loadSettings()
        this.ensureDefaultQualityCriterionSelection()
        this.ensureAutomaticTimetableForResultStep()
    },

    methods: {
        async loadSettings() {
            this.loading = true
            this.error = ''

            try {
                const response = await axios.get('/api/homepage/students-timetables/evaluation-settings')
                this.criteria = this.normalizedCriteria(response.data?.data?.criteria || [])
                this.originalCriteria = this.cloneCriteria(this.criteria)
                this.syncSelectedQualityCriterionKeys()
            } catch (error) {
                this.criteria = []
                this.originalCriteria = []
                this.selectedQualityCriterionKeys = []
                this.error = error?.response?.data?.message || 'Die Bewertungskriterien konnten nicht geladen werden.'
            } finally {
                this.loading = false
            }
        },

        async saveSettings() {
            this.saving = true
            this.error = ''
            this.normalizePriorities()

            try {
                const response = await axios.put('/api/homepage/students-timetables/evaluation-settings', {
                    criteria: this.storageCriteria(this.criteria),
                })
                this.criteria = this.normalizedCriteria(response.data?.data?.criteria || [])
                this.originalCriteria = this.cloneCriteria(this.criteria)
                this.syncSelectedQualityCriterionKeys()
                useNotificationStore().notify({
                    message: response.data?.message || 'Bewertungskriterien wurden gespeichert.',
                    type: 'success',
                    timeout: 3000,
                })
                this.$emit('saved', this.cloneCriteria(this.criteria))

                return true
            } catch (error) {
                this.error = error?.response?.data?.message || 'Die Bewertungskriterien konnten nicht gespeichert werden.'

                return false
            } finally {
                this.saving = false
            }
        },

        async continueToNextStep() {
            if (this.currentStep === 'result') {
                this.$emit('courses-selected', this.selectedCourses)

                return
            }

            if (this.currentStep === 'courses') {
                this.resetGeneratedTimetableSelection()
                await this.createAutomaticTimetable()

                return
            }

            if (await this.saveSettings()) {
                this.ensureDefaultQualityCriterionSelection()
                this.selectAllProposedCourses()
                this.moveToStep('courses')
            }
        },

        handleBack() {
            if (this.currentStep === 'result') {
                this.moveToStep('courses')

                return
            }

            if (this.currentStep === 'courses') {
                this.moveToStep('criteria')

                return
            }

            this.$emit('close')
        },

        async closeCriteriaEditorDialog() {
            if (await this.saveSettings()) {
                this.criteriaEditorDialogOpen = false
            }
        },

        setCriterionEnabled(criterion, enabled) {
            this.criteria = this.criteria.map((currentCriterion) => {
                if (currentCriterion.key === criterion.key) {
                    return {
                        ...currentCriterion,
                        enabled: enabled === true,
                    }
                }

                if (enabled === true && this.oppositeDistanceLearningPreferenceKey(criterion.key) === currentCriterion.key) {
                    return {
                        ...currentCriterion,
                        enabled: false,
                    }
                }

                return currentCriterion
            })
        },

        moveCriterion(index, direction) {
            const targetIndex = index + direction

            if (targetIndex < 0 || targetIndex >= this.criteria.length) {
                return
            }

            const criteria = [...this.criteria]
            const currentCriterion = criteria[index]
            criteria[index] = criteria[targetIndex]
            criteria[targetIndex] = currentCriterion
            this.criteria = criteria
            this.normalizePriorities()
        },

        normalizePriorities() {
            this.criteria = this.criteria.map((criterion, index) => ({
                ...criterion,
                priority: index + 1,
            }))
        },

        normalizedCriteria(criteria) {
            const normalizedCriteria = this.cloneCriteria(criteria)
                .sort((firstCriterion, secondCriterion) => firstCriterion.priority - secondCriterion.priority)
                .map((criterion, index) => ({
                    ...criterion,
                    enabled: criterion.enabled === true,
                    priority: index + 1,
                    options: Array.isArray(criterion.options) ? criterion.options : [],
                    option: criterion.option || null,
                }))

            return this.withExclusiveDistanceLearningPreference(normalizedCriteria)
        },

        storageCriteria(criteria) {
            return this.withExclusiveDistanceLearningPreference(criteria).map((criterion, index) => ({
                key: criterion.key,
                enabled: criterion.enabled === true,
                priority: index + 1,
                option: criterion.option || null,
            }))
        },

        withExclusiveDistanceLearningPreference(criteria) {
            const preferenceKeys = ['prefer_distance_learning', 'avoid_distance_learning']
            const enabledPreferenceKey = (criteria || [])
                .filter(criterion => preferenceKeys.includes(criterion.key) && criterion.enabled === true)
                .sort((firstCriterion, secondCriterion) => firstCriterion.priority - secondCriterion.priority)
                .map(criterion => criterion.key)
                .shift()

            if (!enabledPreferenceKey) {
                return criteria
            }

            return (criteria || []).map(criterion => preferenceKeys.includes(criterion.key)
                ? {
                    ...criterion,
                    enabled: criterion.key === enabledPreferenceKey,
                }
                : criterion)
        },

        oppositeDistanceLearningPreferenceKey(key) {
            if (key === 'prefer_distance_learning') return 'avoid_distance_learning'
            if (key === 'avoid_distance_learning') return 'prefer_distance_learning'

            return ''
        },

        cloneCriteria(criteria) {
            return JSON.parse(JSON.stringify(criteria || []))
        },

        courseSelectionKey(course) {
            return String(course?.key || [
                course?.code || '',
                course?.name || '',
                course?.semester || '',
                course?.branch || '',
                course?.hours ?? course?.hours_per_week ?? '',
            ].join('|'))
        },

        courseSelected(course) {
            return this.selectedCourseKeys.includes(this.courseSelectionKey(course))
        },
        courseSelectedByDefault(course) {
            return this.allCoursesSelectedByDefault || this.courseSelected(course)
        },

        setCourseSelected(course, selected) {
            const courseKey = this.courseSelectionKey(course)

            if (selected === true && !this.selectedCourseKeys.includes(courseKey)) {
                this.selectedCourseKeys = [...this.selectedCourseKeys, courseKey]

                return
            }

            if (selected !== true) {
                const selectedCourseKeys = this.selectedCourseKeys.length
                    ? this.selectedCourseKeys
                    : this.proposedCourses.map(proposedCourse => this.courseSelectionKey(proposedCourse))

                this.selectedCourseKeys = selectedCourseKeys.filter(selectedCourseKey => selectedCourseKey !== courseKey)
            }

            this.emitCourseSelectionChange()
        },
        displayedCourseSectionSelectedCount(section) {
            const courses = Array.isArray(section?.items) ? section.items : []

            if (this.allCoursesSelectedByDefault) {
                return courses.length
            }

            return courses.filter(course => this.courseSelected(course)).length
        },
        selectedCoursesForSummary() {
            if (this.allCoursesSelectedByDefault) {
                return this.proposedCourses
            }

            return this.proposedCourses.filter(course => this.courseSelected(course))
        },
        resetCourseSelectionToDefault() {
            this.selectedCourseKeys = []
            this.emitCourseSelectionChange()
        },
        courseHoursNumber(course) {
            const hours = Number(course?.hours ?? course?.hours_per_week ?? 0)

            return Number.isFinite(hours) ? hours : 0
        },
        courseHoursValue(course) {
            const hours = this.courseHoursNumber(course)

            if (!hours) {
                return '-'
            }

            return new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(hours)
        },
        courseBranchLabel(course) {
            const branch = String(course?.branch || '').trim()

            return !branch || branch === 'common' ? 'alle' : branch
        },

        courseHoursLabel(course) {
            const hours = course.hours ?? course.hours_per_week

            if (hours === null || hours === undefined || hours === '') {
                return null
            }

            const numericHours = Number(hours)

            if (!Number.isFinite(numericHours)) {
                return null
            }

            return `${new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(numericHours)} Std.`
        },

        async createAutomaticTimetable() {
            this.emitCourseSelectionChange()
            this.generatingTimetable = true
            this.timetableError = ''
            this.generatedTimetable = null
            this.timetableCounts = {}

            try {
                const response = await axios.post('/api/homepage/students-timetables/automatic-timetable', {
                    selected_course_keys: this.selectedCourseKeys,
                    selected_quality_criterion_keys: this.selectedQualityCriterionKeys,
                    selected_timetable_type: this.selectedTimetableType,
                    selected_timetable_number: this.selectedTimetableNumber,
                    selection: this.selectionOverride,
                })

                this.generatedTimetable = response.data?.data?.selected_timetable || null
                this.timetableCounts = response.data?.data || {}
                this.selectedTimetableType = this.generatedTimetable?.type || this.selectedTimetableType
                this.selectedTimetableNumber = this.normalizedTimetableNumber(this.generatedTimetable?.number)
                this.moveToStep('result')
            } catch (error) {
                this.timetableError = error?.response?.data?.message || 'Der Stundenplan konnte nicht erstellt werden.'
                this.moveToStep('result')
            } finally {
                this.generatingTimetable = false
            }
        },

        ensureAutomaticTimetableForResultStep() {
            if (this.currentStep !== 'result' || this.generatingTimetable || this.generatedTimetable || this.timetableError) {
                return
            }

            if (!this.selectedCourseKeys.length) {
                this.selectAllProposedCourses()
            }

            if (!this.selectedCourseKeys.length) {
                return
            }

            this.createAutomaticTimetable()
        },

        generatedTimetableSlot(weekday, hour) {
            return this.generatedTimetable?.slots?.[`${weekday}-${hour}`] || null
        },

        async toggleGeneratedQualityCriterion(criterion) {
            if (this.generatingTimetable) {
                return
            }

            const criterionKey = String(criterion?.key || '')
            if (!criterionKey) {
                return
            }

            if (this.selectedQualityCriterionKeys.includes(criterionKey)) {
                this.selectedQualityCriterionKeys = this.selectedQualityCriterionKeys
                    .filter(selectedKey => selectedKey !== criterionKey)
            } else {
                this.selectedQualityCriterionKeys = [...this.selectedQualityCriterionKeys, criterionKey]
            }

            this.emitQualityCriteriaSelectionChange()

            if (this.currentStep === 'result' && this.selectedCourseKeys.length) {
                this.resetGeneratedTimetableSelection()
                await this.createAutomaticTimetable()
            }
        },

        canMoveGeneratedTimetable(direction) {
            const nextTimetableNumber = this.generatedTimetableAbsoluteNumber + direction

            return this.generatedTimetable
                && this.generatedTimetableTotalCount > 0
                && nextTimetableNumber >= 1
                && nextTimetableNumber <= this.generatedTimetableTotalCount
        },

        async moveGeneratedTimetable(direction) {
            if (!this.canMoveGeneratedTimetable(direction)) {
                return
            }

            const nextSelection = this.resolveGeneratedTimetableSelection(this.generatedTimetableAbsoluteNumber + direction)
            this.selectedTimetableType = nextSelection.type
            this.selectedTimetableNumber = nextSelection.number
            await this.createAutomaticTimetable()
        },

        resetGeneratedTimetableSelection() {
            this.selectedTimetableType = null
            this.selectedTimetableNumber = 1
        },

        normalizedTimetableNumber(value) {
            const timetableNumber = Number(value)

            return Number.isFinite(timetableNumber) && timetableNumber > 0 ? timetableNumber : this.selectedTimetableNumber
        },

        generatedTimetableTypeOrder() {
            return ['full_green', 'green', 'conflict']
        },

        generatedTimetableCountForType(timetableType) {
            const counts = {
                full_green: this.timetableCounts?.full_green_timetable_count,
                green: this.timetableCounts?.green_timetable_count,
                conflict: this.timetableCounts?.conflict_timetable_count ?? this.timetableCounts?.red_timetable_count,
            }
            const count = Number(counts[timetableType] || 0)

            return Number.isFinite(count) ? count : 0
        },

        resolveGeneratedTimetableSelection(absoluteTimetableNumber) {
            let remainingTimetableNumber = absoluteTimetableNumber

            for (const timetableType of this.generatedTimetableTypeOrder()) {
                const timetableTypeCount = this.generatedTimetableCountForType(timetableType)

                if (remainingTimetableNumber <= timetableTypeCount) {
                    return {
                        type: timetableType,
                        number: remainingTimetableNumber,
                    }
                }

                remainingTimetableNumber -= timetableTypeCount
            }

            return {
                type: this.generatedTimetableNavigationType,
                number: this.selectedTimetableNumber,
            }
        },

        generatedQualityCriterionSelected(criterion) {
            return this.selectedQualityCriterionKeys.includes(String(criterion?.key || ''))
        },

        generatedQualityCriterionReached(criterion) {
            if (!this.generatedQualityCriterionSelected(criterion)) {
                return false
            }

            if (typeof criterion?.selected_reached === 'boolean') {
                return criterion.selected_reached === true
            }

            return Number(criterion?.count || 0) > 0
        },

        generatedQualityCriterionMeta(criterion) {
            const count = Number(criterion?.count)

            if (Number.isFinite(count)) {
                return `${new Intl.NumberFormat('de-AT').format(count)} Stundenpläne`
            }

            const selectedLabel = String(criterion?.selected_label || '').trim()
            if (selectedLabel && selectedLabel !== '-') {
                return selectedLabel
            }

            const optionLabel = this.selectedOptionLabel(criterion)

            return optionLabel || ''
        },

        syncSelectedQualityCriterionKeys() {
            const activeCriterionKeys = this.activeQualityCriterionKeys()

            this.selectedQualityCriterionKeys = this.selectedQualityCriterionKeys
                .filter(criterionKey => activeCriterionKeys.includes(criterionKey))

            this.ensureDefaultQualityCriterionSelection()
        },

        ensureDefaultQualityCriterionSelection() {
            if (!this.defaultQualityCriterionSelection || this.defaultQualityCriterionSelectionApplied) {
                this.emitQualityCriteriaSelectionChange()

                return
            }

            if (this.selectedQualityCriterionKeys.length) {
                this.defaultQualityCriterionSelectionApplied = true
                this.emitQualityCriteriaSelectionChange()

                return
            }

            const defaultCriterionKey = this.activeQualityCriterionKeys()[0]
            if (!defaultCriterionKey) {
                return
            }

            this.selectedQualityCriterionKeys = [defaultCriterionKey]
            this.defaultQualityCriterionSelectionApplied = true
            this.emitQualityCriteriaSelectionChange()
        },

        activeQualityCriterionKeys() {
            return this.criteria
                .filter(criterion => criterion.enabled === true)
                .map(criterion => String(criterion.key || ''))
                .filter(Boolean)
        },

        selectedOptionLabel(criterion) {
            if (!criterion?.option || !Array.isArray(criterion?.options)) {
                return ''
            }

            return String(criterion.options.find(option => option.value === criterion.option)?.label || '')
        },

        generatedTimetableDisplaySlot(weekday, hour) {
            const slot = this.generatedTimetableSlot(weekday, hour)

            if (slot?.isOccasional !== true) {
                return slot
            }

            return this.generatedSlotConflictBlocksIncludingRegular(slot)[0] || null
        },

        generatedSlotDisplayBlocks(slot) {
            if (!slot) {
                return []
            }

            return [
                slot,
                ...this.generatedSlotBlocks(slot, 'sameSlotEntries'),
                ...this.generatedSlotVisualConflictBlocks(slot).map(block => ({
                    ...block,
                    isConflict: true,
                })),
            ]
        },

        generatedSlotBlocks(slot, key) {
            return Array.isArray(slot?.[key]) ? slot[key] : []
        },

        generatedSlotVisualConflictBlocks(slot) {
            return this.generatedSlotConflictBlocksIncludingRegular(slot)
                .filter(block => !this.generatedSlotBlockIsOccasional(block))
        },

        generatedSlotConflictBlocksIncludingRegular(slot) {
            if (!Array.isArray(slot?.conflicts) || !slot.conflicts.length) {
                return []
            }

            const slotBlock = this.generatedSlotBlock(slot)

            return this.uniqueGeneratedSlotBlocks(
                slot.conflicts
                    .toSorted((firstConflict, secondConflict) =>
                        String(firstConflict.sortValue || '').localeCompare(String(secondConflict.sortValue || '')),
                    )
                    .map(conflict => this.generatedSlotBlock(conflict))
                    .filter(block => block.code || this.generatedSlotDetails(block))
                    .filter(block => !this.generatedSlotBlocksMatch(block, slotBlock)),
            )
        },

        generatedSlotBlock(source) {
            return {
                key: source?.key || source?.label || [
                    source?.code,
                    source?.sourceLabel,
                    source?.courseGroup?.weekday,
                    source?.courseGroup?.hour,
                ].filter(Boolean).join('|'),
                code: source?.code || '',
                name: source?.name || '',
                sourceLabel: source?.sourceLabel || source?.courseGroup?.display_label || '',
                alternativeLabels: source?.alternativeLabels || [],
                courseGroup: source?.courseGroup || {},
                dateRangeLabel: source?.dateRangeLabel || '',
                isOccasional: source?.isOccasional === true,
                isAdditionalCourse: source?.isAdditionalCourse === true,
                isDistanceLearningCourse: source?.isDistanceLearningCourse === true,
            }
        },

        generatedSlotBlockIsOccasional(block) {
            const courseGroup = block?.courseGroup || {}

            return block?.isOccasional === true
                || courseGroup?.recurrence_type === 'single'
                || Number(courseGroup?.dates_count) === 1
        },

        generatedSlotBlocksMatch(firstBlock, secondBlock) {
            const firstIdentity = this.generatedSlotBlockIdentity(firstBlock)
            const secondIdentity = this.generatedSlotBlockIdentity(secondBlock)

            return Boolean(firstIdentity && secondIdentity && firstIdentity === secondIdentity)
        },

        generatedSlotBlockIdentity(block) {
            const courseGroup = block?.courseGroup || {}
            const dates = Array.isArray(courseGroup?.dates) ? courseGroup.dates.join(',') : ''

            return [
                block?.code,
                block?.sourceLabel,
                courseGroup?.course,
                courseGroup?.subject,
                courseGroup?.class_name,
                courseGroup?.display_label,
                courseGroup?.title,
                dates,
            ]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join('|')
        },

        uniqueGeneratedSlotBlocks(blocks) {
            const uniqueBlocks = new Map()

            blocks.forEach(block => {
                const identity = this.generatedSlotBlockIdentity(block) || block?.key

                if (!identity || uniqueBlocks.has(identity)) {
                    return
                }

                uniqueBlocks.set(identity, block)
            })

            return Array.from(uniqueBlocks.values())
        },

        generatedTimetableOccasionalMarkers(weekday, hour) {
            const slot = this.generatedTimetableSlot(weekday, hour)
            const appointmentMarkers = Array.isArray(this.generatedTimetable?.occasionalAppointments)
                ? this.generatedTimetable.occasionalAppointments
                    .filter(appointment => Number(appointment?.weekday) === Number(weekday))
                    .filter(appointment => Number(appointment?.hour) === Number(hour))
                    .filter(appointment => this.occasionalAppointmentSelectedForGeneratedTimetable(appointment))
                    .map(appointment => ({
                        key: appointment?.key || [
                            appointment?.code,
                            appointment?.sourceLabel,
                            appointment?.date,
                        ].filter(Boolean).join('|'),
                        code: appointment?.code || appointment?.courseKey || '',
                        sourceLabel: appointment?.sourceLabel || '',
                        date: appointment?.date || this.courseGroupDates(appointment?.courseGroup)[0] || '',
                        isAdditionalCourse: appointment?.isAdditionalCourse === true,
                    }))
                : []

            return this.uniqueGeneratedTimetableOccasionalMarkers([
                ...appointmentMarkers,
                ...this.generatedSlotOccasionalConflictMarkers(slot),
            ])
        },

        occasionalAppointmentSelectedForGeneratedTimetable(appointment) {
            const selectedGroups = this.generatedTimetable?.selectedOccasionalAppointmentGroups || {}
            const courseKey = String(appointment?.courseKey || appointment?.code || '').trim()

            if (!courseKey || !selectedGroups[courseKey]) {
                return true
            }

            return selectedGroups[courseKey] === appointment?.key
        },

        generatedSlotOccasionalConflictMarkers(slot) {
            if (!slot) {
                return []
            }

            const slotBlock = this.generatedSlotBlock(slot)
            const slotBlocks = this.generatedSlotBlockIsOccasional(slotBlock) ? [slotBlock] : []
            const conflictBlocks = this.generatedSlotConflictBlocksIncludingRegular(slot)
                .filter(block => this.generatedSlotBlockIsOccasional(block))

            return this.uniqueGeneratedSlotBlocks([
                ...slotBlocks,
                ...conflictBlocks,
            ])
                .map(block => ({
                    key: `occasional-${block.key}`,
                    code: block.code,
                    sourceLabel: block.sourceLabel,
                    date: this.courseGroupDates(block.courseGroup)[0] || '',
                    isAdditionalCourse: block.isAdditionalCourse === true,
                }))
        },

        uniqueGeneratedTimetableOccasionalMarkers(markers) {
            const uniqueMarkers = new Map()

            markers.forEach(marker => {
                const identity = [
                    marker?.code,
                    marker?.sourceLabel,
                    marker?.date,
                ]
                    .map(value => String(value || '').trim())
                    .filter(Boolean)
                    .join('|')

                if (!identity || uniqueMarkers.has(identity)) {
                    return
                }

                uniqueMarkers.set(identity, marker)
            })

            return Array.from(uniqueMarkers.values())
        },

        courseGroupDates(courseGroup) {
            return Array.isArray(courseGroup?.dates)
                ? courseGroup.dates
                    .map(date => String(date || '').trim())
                    .filter(Boolean)
                : []
        },

        generatedOccasionalMarkerTitle(marker) {
            return [
                'Einzelunterricht',
                marker?.code,
                marker?.sourceLabel,
                marker?.date,
            ]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join(' · ')
        },

        generatedSlotBlockKey(block) {
            return [
                block?.key || block?.code || '',
                block?.sourceLabel || '',
                block?.courseGroup?.weekday || '',
                block?.courseGroup?.hour || '',
                block?.isConflict === true ? 'conflict' : 'regular',
            ].join('|')
        },

        generatedSlotTitle(slot) {
            return String(slot?.code || slot?.courseGroup?.display_label || slot?.sourceLabel || '-').trim()
        },

        generatedSlotDetails(slot) {
            const courseGroup = slot?.courseGroup || {}
            const label = String(slot?.sourceLabel || courseGroup?.title || '').trim()
            const teacher = String(courseGroup?.teacher || '').trim()
            const rooms = Array.isArray(courseGroup?.rooms)
                ? courseGroup.rooms.join(', ')
                : String(courseGroup?.room || '').trim()

            return [label, teacher, rooms]
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
                .join(' · ')
        },

        moveToStep(step, emitChange = true) {
            const nextStep = this.normalizedStep(step)

            if (this.currentStep === nextStep) {
                return
            }

            this.currentStep = nextStep

            if (nextStep === 'courses' && this.selectedCourseKeys.length === 0) {
                this.selectAllProposedCourses()
            }

            if (emitChange) {
                this.$emit('step-change', nextStep)
            }
        },

        normalizedStep(step) {
            return ['criteria', 'courses', 'result'].includes(String(step || '')) ? step : 'criteria'
        },

        selectAllProposedCourses() {
            this.selectedCourseKeys = this.proposedCourses.map(course => this.courseSelectionKey(course))
            this.emitCourseSelectionChange()
        },

        normalizedCourseKeys(courseKeys) {
            return Array.isArray(courseKeys)
                ? courseKeys.map(courseKey => String(courseKey || '')).filter(Boolean)
                : []
        },

        criterionOptionLabel(criterion) {
            const option = (criterion.options || [])
                .find(criterionOption => criterionOption.value === criterion.option)

            return option?.label || ''
        },

        emitCourseSelectionChange() {
            this.$emit('course-selection-change', [...this.selectedCourseKeys])
        },
        emitQualityCriteriaSelectionChange() {
            this.$emit('quality-criteria-selection-change', [...this.selectedQualityCriterionKeys])
        },
    },
}
</script>

<style scoped>
.student-evaluation-settings {
    display: grid;
    gap: 14px;
    margin: 0 0 20px;
    padding: 16px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.76);
}

.student-evaluation-settings--criteria {
    padding: 0;
    border: 0;
    background: transparent;
}

.student-evaluation-settings__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.student-evaluation-settings__title-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    color: #10263a;
}

.student-evaluation-settings__title-label p {
    margin: 0 0 2px;
    color: rgba(23, 45, 64, 0.66);
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
}

.student-evaluation-settings__title-label h3 {
    margin: 0;
    font-size: 1.05rem;
}

.student-evaluation-settings__summary-card {
    display: grid;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid rgba(59, 130, 246, 0.16);
    border-radius: 8px;
    background:
        linear-gradient(135deg, rgba(239, 246, 255, 0.94), rgba(248, 250, 252, 0.9)),
        radial-gradient(circle at top left, rgba(59, 130, 246, 0.14), transparent 34%);
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
}

.student-evaluation-settings__automatic-card {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    max-width: 100%;
    padding: 12px;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(135deg, #1d4ed8 0%, #6366f1 100%);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
}

.student-evaluation-settings__automatic-card h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 900;
    letter-spacing: -0.01em;
}

.student-evaluation-settings__automatic-stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    color: rgba(255, 255, 255, 0.9);
}

.student-evaluation-settings__automatic-star {
    animation: student-automatic-star-twinkle 1.8s ease-in-out infinite;
}

.student-evaluation-settings__automatic-star--1 {
    animation-delay: 0s;
}

.student-evaluation-settings__automatic-star--2 {
    animation-delay: 0.5s;
}

.student-evaluation-settings__automatic-star--3 {
    animation-delay: 1.1s;
}

.student-evaluation-settings__summary-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.student-evaluation-settings__summary-title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #172554;
    font-size: 0.82rem;
    font-weight: 900;
}

.student-evaluation-settings__summary-cog {
    color: #2563eb !important;
}

.student-evaluation-settings__summary-cog :deep(.v-icon) {
    color: #2563eb !important;
}

.student-evaluation-settings__summary-list {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.student-evaluation-settings__summary-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 24px;
    padding: 3px 8px 3px 4px;
    border: 1px solid rgba(37, 99, 235, 0.2);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.86);
    color: #0f172a;
    font-size: 0.68rem;
    font-weight: 850;
    line-height: 1.1;
}

.student-evaluation-settings__summary-rank {
    display: inline-grid;
    place-items: center;
    width: 18px;
    height: 18px;
    border-radius: 999px;
    background: #2563eb;
    color: #ffffff;
    font-size: 0.66rem;
    font-weight: 900;
}

.student-evaluation-settings__summary-label,
.student-evaluation-settings__summary-option {
    overflow-wrap: anywhere;
}

.student-evaluation-settings__summary-option {
    color: #475569;
}

.student-evaluation-settings__summary-option::before {
    content: "· ";
}

.student-evaluation-settings__summary-empty {
    color: #475569;
    font-size: 0.72rem;
    font-weight: 750;
}

.student-evaluation-settings__course-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.84);
}

.student-evaluation-settings__course-summary-title {
    color: #172d40;
    font-size: 0.9rem;
    font-weight: 850;
}

.student-evaluation-settings__course-panel {
    display: grid;
    gap: 10px;
    border: 1px solid rgba(59, 130, 246, 0.34);
    border-radius: 8px;
    background: rgba(219, 234, 254, 0.58);
    overflow: hidden;
}

.student-evaluation-settings__course-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(59, 130, 246, 0.22);
}

.student-evaluation-settings__course-panel-title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    color: #0f172a;
    font-size: 0.86rem;
    font-weight: 900;
}

.student-evaluation-settings__course-section-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
    padding: 0 8px 8px;
}

.student-evaluation-settings__course-section-card {
    min-width: 0;
    border: 1px solid rgba(16, 38, 58, 0.14);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.82);
    overflow: hidden;
}

.student-evaluation-settings__course-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    color: #020617;
    font-weight: 900;
}

.student-evaluation-settings__course-section-count {
    display: inline-grid;
    place-items: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #dbe4f5;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 900;
}

.student-evaluation-settings__course-table {
    display: grid;
}

.student-evaluation-settings__course-table-row {
    display: grid;
    grid-template-columns: 46px 88px minmax(130px, 1fr) 90px 54px;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 8px 10px;
    border-top: 1px solid rgba(16, 38, 58, 0.1);
    color: #0f172a;
    font-size: 0.78rem;
}

.student-evaluation-settings__course-table-row--head {
    color: #172554;
    font-size: 0.7rem;
    font-weight: 900;
}

.student-evaluation-settings__course-table-row :deep(.v-selection-control) {
    min-height: 24px;
}

.student-evaluation-settings__course-empty {
    padding: 12px 10px;
    border-top: 1px solid rgba(16, 38, 58, 0.1);
    color: #64748b;
    font-size: 0.82rem;
}

.student-evaluation-settings__editor-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

@keyframes student-automatic-star-twinkle {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.8);
    }

    50% {
        opacity: 1;
        transform: scale(1.2);
    }
}

.student-evaluation-settings__list {
    display: grid;
    gap: 10px;
}

.student-evaluation-settings__item {
    display: grid;
    grid-template-columns: 36px 64px minmax(0, 1fr) 40px;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.86);
}

.student-evaluation-settings__item--active {
    border-color: rgba(var(--v-theme-success), 0.34);
    background: linear-gradient(90deg, rgba(var(--v-theme-success), 0.13), rgba(255, 255, 255, 0.92));
    box-shadow: inset 4px 0 0 rgba(var(--v-theme-success), 0.82);
}

.student-evaluation-settings__item--disabled {
    background: rgba(16, 38, 58, 0.035);
}

.student-evaluation-settings__priority {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));
    font-size: 0.82rem;
    font-weight: 800;
}

.student-evaluation-settings__item--active .student-evaluation-settings__priority {
    background: rgba(var(--v-theme-success), 0.16);
    color: rgb(var(--v-theme-success));
}

.student-evaluation-settings__switch {
    margin-top: -6px;
}

.student-evaluation-settings__content {
    min-width: 0;
}

.student-evaluation-settings__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.student-evaluation-settings__label {
    color: #172d40;
    font-weight: 800;
}

.student-evaluation-settings__description {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.86rem;
}

.student-evaluation-settings__option {
    max-width: 320px;
    margin-top: 10px;
}

.student-evaluation-settings__actions {
    display: grid;
    gap: 4px;
}

.student-evaluation-settings__footer {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
}

.student-evaluation-settings__footer--automatic {
    justify-content: flex-end;
}

.student-evaluation-settings__create-button,
.student-evaluation-settings__close-button {
    flex: 0 0 auto;
    min-height: 48px;
    border-radius: 8px;
    font-weight: 400;
    letter-spacing: 0;
    text-transform: none;
}

.student-course-selection {
    display: grid;
    gap: 12px;
}

.student-course-selection__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.student-course-selection__label {
    color: #172d40;
    font-weight: 800;
}

.student-course-selection__description {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.86rem;
}

.student-course-selection__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
}

.student-course-selection__item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
    padding: 10px 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.86);
    cursor: pointer;
    transition: border-color 140ms ease, background 140ms ease;
}

.student-course-selection__item--selected {
    border-color: rgba(var(--v-theme-success), 0.34);
    background: linear-gradient(90deg, rgba(var(--v-theme-success), 0.11), rgba(255, 255, 255, 0.94));
}

.student-course-selection__item :deep(.v-selection-control) {
    min-height: 28px;
}

.student-course-selection__content {
    display: grid;
    gap: 4px;
    min-width: 0;
    padding-top: 3px;
}

.student-course-selection__content strong {
    color: #172d40;
}

.student-course-selection__content > span:not(.student-course-selection__meta) {
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.86rem;
}

.student-course-selection__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.student-course-selection__empty {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 18px 14px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.72);
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.92rem;
}

.student-generated-timetable {
    display: grid;
    gap: 12px;
}

.student-generated-timetable__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.student-generated-timetable__label {
    color: #172d40;
    font-weight: 800;
}

.student-generated-timetable__description {
    margin-top: 2px;
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.86rem;
}

.student-generated-timetable__controls {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.student-generated-timetable__counter {
    min-width: 72px;
    color: rgba(23, 45, 64, 0.74);
    font-size: 0.82rem;
    font-weight: 800;
    text-align: center;
}

.student-generated-criteria {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
}

.student-generated-criteria__card {
    position: relative;
    display: grid;
    gap: 2px;
    min-height: 48px;
    padding: 8px 28px 8px 10px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.86);
    color: #172d40;
    cursor: pointer;
    text-align: left;
    transition: border-color 140ms ease, background 140ms ease, box-shadow 140ms ease;
}

.student-generated-criteria__card:disabled {
    cursor: wait;
    opacity: 0.72;
}

.student-generated-criteria__card--selected {
    border-color: rgba(var(--v-theme-primary), 0.42);
    background: rgba(var(--v-theme-primary), 0.08);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-primary));
}

.student-generated-criteria__card--reached {
    border-color: rgba(var(--v-theme-success), 0.42);
    background: rgba(var(--v-theme-success), 0.1);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-success));
}

.student-generated-criteria__card--missed {
    border-color: rgba(var(--v-theme-primary), 0.34);
}

.student-generated-criteria__label {
    font-size: 0.78rem;
    font-weight: 850;
    line-height: 1.15;
}

.student-generated-criteria__meta {
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1.15;
}

.student-generated-criteria__icon {
    position: absolute;
    top: 8px;
    right: 8px;
}

.student-generated-timetable__grid {
    display: grid;
    grid-template-columns: 52px repeat(var(--student-generated-weekdays, 5), minmax(86px, 1fr));
    overflow-x: auto;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.9);
}

.student-generated-timetable__cell {
    position: relative;
    min-height: 70px;
    padding: 8px;
    border-right: 1px solid rgba(16, 38, 58, 0.08);
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.74);
}

.student-generated-timetable__cell--header,
.student-generated-timetable__cell--time {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    background: rgba(16, 38, 58, 0.06);
    color: #172d40;
    font-size: 0.78rem;
    font-weight: 800;
}

.student-generated-timetable__cell--filled {
    background: rgba(var(--v-theme-success), 0.09);
}

.student-generated-timetable__cell--conflict {
    background: rgba(var(--v-theme-error), 0.09);
}

.student-generated-timetable__cell--has-occasional {
    padding-top: 24px;
}

.student-generated-timetable__occasional-markers {
    position: absolute;
    top: 4px;
    right: 4px;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 3px;
    max-width: calc(100% - 8px);
    pointer-events: none;
}

.student-generated-timetable__occasional-marker {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    min-height: 16px;
    padding: 1px 5px;
    border: 1px solid rgba(30, 64, 175, 0.18);
    border-radius: 4px;
    background: #bfdbfe;
    color: #1e3a8a;
    font-size: 0.58rem;
    font-weight: 900;
    line-height: 1;
}

.student-generated-timetable__occasional-marker--additional {
    border-color: rgba(234, 88, 12, 0.28);
    background: #fed7aa;
    color: #7c2d12;
}

.student-generated-timetable__entry {
    display: grid;
    gap: 3px;
}

.student-generated-timetable__entry + .student-generated-timetable__entry {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed rgba(16, 38, 58, 0.16);
}

.student-generated-timetable__entry--conflict .student-generated-timetable__code {
    color: rgb(var(--v-theme-error));
}

.student-generated-timetable__code {
    color: rgb(var(--v-theme-success));
    font-size: 0.86rem;
    font-weight: 900;
}

.student-generated-timetable__details {
    white-space: pre-line;
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.76rem;
    line-height: 1.25;
}

@media (max-width: 720px) {
    .student-evaluation-settings__title {
        align-items: stretch;
        flex-direction: column;
    }

    .student-evaluation-settings__item {
        grid-template-columns: 32px minmax(0, 1fr) 36px;
    }

    .student-evaluation-settings__switch {
        grid-column: 2;
        grid-row: 1;
        margin-left: auto;
    }

    .student-evaluation-settings__content {
        grid-column: 1 / -1;
    }

    .student-evaluation-settings__actions {
        grid-column: 3;
        grid-row: 1;
    }

    .student-evaluation-settings__footer .v-btn {
        flex: 1 1 180px;
    }

    .student-course-selection__head {
        flex-direction: column;
    }

    .student-generated-timetable__head {
        flex-direction: column;
    }

    .student-generated-timetable__controls {
        justify-content: flex-start;
        flex-wrap: wrap;
        width: 100%;
    }

    .student-generated-timetable__grid {
        grid-template-columns: 42px repeat(var(--student-generated-weekdays, 5), minmax(74px, 1fr));
    }

    .student-generated-timetable__cell {
        min-height: 64px;
        padding: 6px;
    }
}
</style>
