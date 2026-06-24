<template>
    <section
        class="student-evaluation-settings"
        :class="{ 'student-evaluation-settings--criteria': currentStep === 'criteria' }">
        <div
            v-if="currentStep === 'courses'"
            class="student-evaluation-settings__title">
            <div class="student-evaluation-settings__title-label">
                <v-icon icon="mdi-auto-fix" />
                <div>
                    <p>Neuer Stundenplan</p>
                    <h3>{{ currentStepTitle }}</h3>
                </div>
            </div>
        </div>

        <template v-else-if="currentStep === 'criteria'">
            <div class="student-evaluation-settings__automatic-card">
                <v-icon icon="mdi-calendar-clock" size="22" />
                <h2>Neuer Stundenplan</h2>
                <span class="student-evaluation-settings__automatic-stars" aria-hidden="true">
                    <v-icon icon="mdi-star-four-points" size="10" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--1" />
                    <v-icon icon="mdi-star-four-points" size="14" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--2" />
                    <v-icon icon="mdi-star-four-points" size="8" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--3" />
                </span>
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
                        v-for="section in regularCourseSections"
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
                        Wählen Sie die Kurse aus, die für den neuen Stundenplan berücksichtigt werden sollen.
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
            <v-card rounded="lg" class="student-selected-courses-card">
                <v-card-title class="student-selected-courses-card__title">
                    <span>Ausgewählte Kurse</span>
                    <span class="student-selected-courses-card__summary">
                        <v-chip size="x-small" color="primary" variant="tonal">
                            {{ resultSelectedCourseSummary.countLabel }}
                        </v-chip>
                        <v-chip size="x-small" color="primary" variant="tonal">
                            {{ resultSelectedCourseSummary.hoursLabel }}
                        </v-chip>
                    </span>
                </v-card-title>
                <v-card-text>
                    <div v-if="resultSelectedCourseItems.length" class="student-selected-courses-card__list">
                        <v-chip
                            v-for="course in resultSelectedCourseItems"
                            :key="course.selectionKey"
                            size="small"
                            color="success"
                            variant="tonal"
                            closable
                            :disabled="generatingTimetable"
                            :close-label="`Kurs ${course.label} entfernen`"
                            close-icon="mdi-close"
                            class="student-selected-courses-card__course"
                            @click:close.stop="removeResultSelectedCourseItem(course)">
                            <span>{{ course.label }}</span>
                            <span v-if="course.meta" class="student-selected-courses-card__meta">
                                {{ course.meta }}
                            </span>
                        </v-chip>
                    </div>
                    <v-alert v-else type="info" variant="tonal" density="compact">
                        Keine Kurse ausgewählt.
                    </v-alert>
                </v-card-text>
                <v-card-actions
                    v-if="resultSelectedCourseRemovalPending"
                    class="student-selected-courses-card__actions">
                    <span class="student-selected-courses-card__pending-copy">
                        Auswahl geändert. Stundenplan neu erstellen?
                    </span>
                    <v-spacer />
                    <v-btn
                        size="large"
                        color="warning"
                        variant="tonal"
                        prepend-icon="mdi-close"
                        :disabled="generatingTimetable"
                        @click="cancelResultSelectedCourseRemoval">
                        Abbruch
                    </v-btn>
                    <v-btn
                        size="large"
                        color="success"
                        variant="tonal"
                        append-icon="mdi-check"
                        :loading="generatingTimetable"
                        :disabled="generatingTimetable || !resultSelectedCourseItems.length"
                        @click="applyResultSelectedCourseRemoval">
                        Anwenden
                    </v-btn>
                </v-card-actions>
            </v-card>

            <div v-if="!generatingTimetable" class="student-evaluation-settings__automatic-card">
                <v-icon icon="mdi-calendar-clock" size="22" />
                <h2>Stundenplan</h2>
                <span class="student-evaluation-settings__automatic-stars" aria-hidden="true">
                    <v-icon icon="mdi-star-four-points" size="10" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--1" />
                    <v-icon icon="mdi-star-four-points" size="14" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--2" />
                    <v-icon icon="mdi-star-four-points" size="8" class="student-evaluation-settings__automatic-star student-evaluation-settings__automatic-star--3" />
                </span>
                <span class="student-generated-timetable__toolbar">
                    <span class="student-generated-timetable__primary-actions">
                        <v-btn
                            size="large"
                            :color="resultMoreCoursesUnavailable ? 'error' : 'primary'"
                            variant="tonal"
                            prepend-icon="mdi-plus-circle-outline"
                            :disabled="generatingTimetable || resultMoreCoursesUnavailable"
                            :aria-expanded="resultMoreCoursesVisible ? 'true' : 'false'"
                            @click="toggleResultMoreCourses">
                            Mehr Kurse
                        </v-btn>
                        <v-btn
                            size="large"
                            :color="resultOptionsUnavailable ? 'error' : 'info'"
                            variant="tonal"
                            prepend-icon="mdi-cog-outline"
                            :disabled="generatingTimetable || resultOptionsUnavailable"
                            :aria-expanded="resultOptionsVisible ? 'true' : 'false'"
                            @click="toggleResultOptions">
                            Optionen
                        </v-btn>
                    </span>
                    <v-btn
                        size="large"
                        color="warning"
                        variant="tonal"
                        prepend-icon="mdi-restore"
                        class="student-generated-timetable__reset-button"
                        :disabled="generatingTimetable || !resultCalculationResetAvailable"
                        @click="resetResultCalculationChanges">
                        Zurücksetzen
                    </v-btn>
                </span>
            </div>

            <v-card
                v-if="resultMoreCoursesVisible"
                rounded="lg"
                variant="tonal"
                class="student-result-more-courses-card">
                <v-card-title class="student-result-more-courses-card__title">
                    Mehr Kurse
                </v-card-title>
                <v-card-text>
                    <div v-if="resultMoreCourseItems.length" class="student-result-more-courses-card__list">
                        <v-chip
                            v-for="course in resultMoreCourseItems"
                            :key="course.selectionKey"
                            size="small"
                            color="success"
                            :variant="selectedResultMoreCourseItem?.selectionKey === course.selectionKey ? 'flat' : 'tonal'"
                            class="student-selected-courses-card__course student-result-more-courses-card__course"
                            :class="{
                                'student-selected-courses-card__course--active': selectedResultMoreCourseItem?.selectionKey === course.selectionKey,
                                'student-selected-courses-card__course--offered-partial': resultMoreOfferedCourseItemsPartlySelected(course),
                                'student-selected-courses-card__course--offered-deselected': resultMoreOfferedCourseItemsAllDeselected(course),
                            }"
                            role="button"
                            :aria-pressed="resultDraftAdditionalCourseSelected(course) ? 'true' : 'false'"
                            :aria-expanded="selectedResultMoreCourseItem?.selectionKey === course.selectionKey ? 'true' : 'false'"
                            @click="toggleResultMoreCourseOffers(course)"
                            @keydown.enter.prevent="toggleResultMoreCourseOffers(course)"
                            @keydown.space.prevent="toggleResultMoreCourseOffers(course)">
                            <span class="student-result-more-courses-card__label">
                                {{ course.label }}
                            </span>
                            <span v-if="course.meta" class="student-result-more-courses-card__meta">
                                {{ course.meta }}
                            </span>
                            <span class="student-result-more-courses-card__group">
                                {{ course.courseGroupLabel }}
                            </span>
                        </v-chip>
                    </div>
                    <v-alert v-else type="info" variant="tonal" density="compact">
                        Keine weiteren Kurse verfügbar.
                    </v-alert>
                </v-card-text>
            </v-card>

            <v-card
                v-if="resultMoreCoursesVisible && selectedResultMoreCourseItem"
                rounded="lg"
                variant="tonal"
                class="student-result-more-courses-offered-card student-result-offered-courses-card">
                <v-card-title class="student-result-offered-courses-card__title">
                    <span>Angebotene Kurse</span>
                    <v-chip size="x-small" color="primary" variant="tonal">
                        {{ selectedResultMoreCourseItem.label }}
                    </v-chip>
                    <span
                        v-if="selectedResultMoreCourseOfferedCourseItems.length"
                        class="student-result-offered-courses-card__actions">
                        <v-btn
                            icon="mdi-checkbox-blank-outline"
                            size="x-small"
                            density="compact"
                            variant="tonal"
                            color="secondary"
                            title="Alle angebotenen Kurse abwählen"
                            aria-label="Alle angebotenen Kurse abwählen"
                            :disabled="generatingTimetable || resultMoreOfferedCourseItemsAllDeselected(selectedResultMoreCourseItem)"
                            @click.stop="deselectSelectedResultMoreCourseOfferedCourses" />
                    </span>
                </v-card-title>
                <v-card-text>
                    <div
                        v-if="selectedResultMoreCourseOfferedCourseItems.length"
                        class="student-result-offered-courses-card__list">
                        <div
                            v-for="course in selectedResultMoreCourseOfferedCourseItems"
                            :key="course.selectionKey"
                            class="student-result-offered-courses-card__item student-result-offered-courses-card__item--toggle"
                            :class="{
                                'student-result-offered-courses-card__item--selected': resultMoreOfferedCourseSelected(course),
                                'student-result-offered-courses-card__item--deselected': !resultMoreOfferedCourseSelected(course),
                            }"
                            role="button"
                            tabindex="0"
                            :aria-pressed="resultMoreOfferedCourseSelected(course) ? 'true' : 'false'"
                            @click="toggleResultMoreOfferedCourseItem(course)"
                            @keydown.enter.prevent="toggleResultMoreOfferedCourseItem(course)"
                            @keydown.space.prevent="toggleResultMoreOfferedCourseItem(course)">
                            <v-icon v-if="resultMoreOfferedCourseSelected(course)" icon="mdi-check" size="16" color="success" />
                            <span class="student-result-offered-courses-card__name">
                                {{ course.name || course.code }}
                            </span>
                            <span v-if="resultMoreOfferedCourseCodeVisible(course)" class="student-result-offered-courses-card__code">
                                {{ course.code }}
                            </span>
                            <v-chip v-if="course.scheduleLabel" size="x-small" color="primary" variant="tonal">
                                {{ course.scheduleLabel }}
                            </v-chip>
                            <v-chip v-if="course.recurrenceLabel" size="x-small" color="primary" variant="tonal">
                                {{ course.recurrenceLabel }}
                            </v-chip>
                            <v-chip
                                v-if="course.distanceLearning"
                                size="x-small"
                                color="warning"
                                variant="tonal"
                                title="Fernunterricht">
                                Fernunterricht
                            </v-chip>
                            <v-chip v-if="course.roomsLabel" size="x-small" color="secondary" variant="outlined">
                                {{ course.roomsLabel }}
                            </v-chip>
                        </div>
                    </div>
                    <v-alert v-else type="info" variant="tonal" density="compact">
                        Keine angebotenen Kurse gefunden.
                    </v-alert>
                </v-card-text>
            </v-card>

            <v-card
                v-if="resultMoreCoursesVisible"
                rounded="lg"
                variant="tonal"
                class="student-result-more-courses-actions-card">
                <v-card-actions class="student-result-options-card__actions">
                    <v-spacer />
                    <v-btn
                        size="large"
                        color="warning"
                        variant="tonal"
                        prepend-icon="mdi-close"
                        :disabled="generatingTimetable"
                        @click="closeResultMoreCourses">
                        Abbruch
                    </v-btn>
                    <v-btn
                        size="large"
                        color="success"
                        variant="tonal"
                        append-icon="mdi-check"
                        :loading="generatingTimetable"
                        :disabled="generatingTimetable || !resultAdditionalCoursesChanged"
                        @click="applyResultMoreCourses">
                        Anwenden
                    </v-btn>
                </v-card-actions>
            </v-card>

            <v-card
                v-if="resultOptionsVisible"
                rounded="lg"
                variant="tonal"
                class="student-result-options-card">
                <v-card-title class="student-result-options-card__title">
                    Optionen
                </v-card-title>
                <v-card-text>
                    <div v-if="activeGeneratedQualityCriteria.length" class="student-result-options-card__list">
                        <label
                            v-for="criterion in activeGeneratedQualityCriteria"
                            :key="criterion.key"
                            class="student-result-options-card__item"
                            :class="{ 'student-result-options-card__item--selected': resultDraftQualityCriterionSelected(criterion) }">
                            <v-checkbox
                                :model-value="resultDraftQualityCriterionSelected(criterion)"
                                color="success"
                                density="compact"
                                hide-details
                                :disabled="generatingTimetable"
                                @update:model-value="setResultDraftQualityCriterionSelected(criterion, $event)" />
                            <span class="student-result-options-card__content">
                                <strong>{{ criterion.label }}</strong>
                                <span v-if="criterion.description">{{ criterion.description }}</span>
                                <span v-if="generatedQualityCriterionMeta(criterion)" class="student-result-options-card__meta">
                                    <v-chip size="x-small" color="primary" variant="tonal">
                                        {{ generatedQualityCriterionMeta(criterion) }}
                                    </v-chip>
                                </span>
                            </span>
                        </label>
                    </div>
                    <v-alert v-else type="info" variant="tonal" density="compact">
                        Keine Optionen verfügbar.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="student-result-options-card__actions">
                    <v-spacer />
                    <v-btn
                        size="large"
                        color="warning"
                        variant="tonal"
                        prepend-icon="mdi-close"
                        :disabled="generatingTimetable"
                        @click="closeResultOptions">
                        Abbruch
                    </v-btn>
                    <v-btn
                        size="large"
                        color="success"
                        variant="flat"
                        append-icon="mdi-check"
                        :loading="generatingTimetable"
                        :disabled="generatingTimetable || !resultOptionsChanged"
                        @click="applyResultOptions">
                        Anwenden
                    </v-btn>
                </v-card-actions>
            </v-card>

            <v-expansion-panels
                v-if="resultCoursePanelsVisible"
                v-model="resultCoursePanelOpen"
                class="student-result-course-panels"
                multiple
                variant="accordion">
                <v-expansion-panel
                    v-if="readOnlySelectedCourseSections.length"
                    value="selected-courses"
                    class="student-result-course-panel">
                    <v-expansion-panel-title class="student-result-course-panel__head">
                        <div class="student-result-course-panel__title">
                            <v-icon icon="mdi-format-list-checks" size="18" color="primary" />
                            <span>{{ courseSummaryTitle }}</span>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ effectiveSelectedCourseCount }} / {{ effectiveCourseSummaryTotal }}
                            </v-chip>
                            <v-chip v-if="selectedCourseHoursLabel" size="x-small" color="default" variant="tonal">
                                {{ selectedCourseHoursLabel }}
                            </v-chip>
                        </div>
                    </v-expansion-panel-title>
                    <v-expansion-panel-text>
                        <div class="student-evaluation-settings__course-section-grid student-evaluation-settings__course-section-grid--readonly">
                            <div
                                v-for="section in readOnlySelectedCourseSections"
                                :key="`result-${section.key}`"
                                class="student-evaluation-settings__course-section-card">
                                <div class="student-evaluation-settings__course-section-title">
                                    {{ section.title }}
                                    <span class="student-evaluation-settings__course-section-count">
                                        {{ section.items.length }}
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
                                        class="student-evaluation-settings__course-table-row student-evaluation-settings__course-table-row--readonly">
                                        <span>
                                            <v-checkbox
                                                :model-value="true"
                                                color="primary"
                                                density="compact"
                                                disabled
                                                hide-details />
                                        </span>
                                        <strong>{{ course.code || '-' }}</strong>
                                        <span>{{ course.name || course.code || '-' }}</span>
                                        <span>{{ courseBranchLabel(course) }}</span>
                                        <span>{{ courseHoursValue(course) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-expansion-panel-text>
                </v-expansion-panel>

                <v-expansion-panel
                    v-if="additionalCoursePanelVisible"
                    value="additional-courses"
                    class="student-result-course-panel student-result-course-panel--additional">
                    <v-expansion-panel-title class="student-result-course-panel__head">
                        <div class="student-result-course-panel__title">
                            <v-icon icon="mdi-plus-circle-outline" size="18" color="warning" />
                            <span>Zusätzliche Kurse</span>
                            <v-chip size="x-small" color="warning" variant="tonal">
                                {{ selectedAdditionalCourseKeys.length }} / {{ additionalCourses.length }}
                            </v-chip>
                        </div>
                    </v-expansion-panel-title>
                    <v-expansion-panel-text>
                        <div class="student-evaluation-settings__course-section-grid student-evaluation-settings__course-section-grid--readonly">
                            <div
                                v-for="section in additionalCourseSections"
                                :key="`result-${section.key}`"
                                class="student-evaluation-settings__course-section-card student-evaluation-settings__course-section-card--additional">
                                <div class="student-evaluation-settings__course-section-title">
                                    {{ section.title }}
                                    <span class="student-evaluation-settings__course-section-count">
                                        {{ additionalCourseSectionSelectedCount(section) }}
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
                                        class="student-evaluation-settings__course-table-row"
                                        :class="{ 'student-evaluation-settings__course-table-row--selected': additionalCourseSelected(course) }">
                                        <span>
                                            <v-checkbox
                                                :model-value="additionalCourseSelected(course)"
                                                color="success"
                                                density="compact"
                                                :disabled="additionalCourseSelectionLocked || generatingTimetable"
                                                hide-details
                                                @update:model-value="setAdditionalCourseSelected(course, $event)" />
                                        </span>
                                        <strong>{{ course.code || '-' }}</strong>
                                        <span>{{ course.name || course.code || '-' }}</span>
                                        <span>{{ courseBranchLabel(course) }}</span>
                                        <span>{{ courseHoursValue(course) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>

            <v-alert v-if="timetableError" type="error" variant="tonal" density="comfortable">
                {{ timetableError }}
            </v-alert>

            <v-alert
                v-else-if="generatingTimetable"
                type="info"
                variant="tonal"
                density="comfortable"
                class="student-generated-timetable__calculation-alert"
                role="status"
                aria-live="polite">
                <template #prepend>
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="20"
                        width="2" />
                </template>
                Stundenpläne werden berechnet. Das kann einen Moment dauern.
            </v-alert>

            <template v-else-if="generatedTimetable">
                <div
                    v-if="additionalCourseSelectionChangedAfterTimetable"
                    class="student-generated-timetable__extension-actions">
                    <v-btn
                        color="success"
                        variant="flat"
                        prepend-icon="mdi-calendar-plus"
                        rounded="pill"
                        :loading="generatingTimetable"
                        :disabled="loading || saving || generatingTimetable"
                        @click="createExtendedAutomaticTimetable">
                        Stundenplan erweitern
                    </v-btn>
                    <v-btn
                        class="student-generated-timetable__back-button"
                        variant="tonal"
                        color="secondary"
                        prepend-icon="mdi-arrow-left"
                        rounded="pill"
                        :disabled="saving || generatingTimetable"
                        @click="handleBack">
                        Zurück
                    </v-btn>
                </div>

                <template v-else>
                    <div class="student-generated-timetable__success-strip">
                        <v-icon icon="mdi-check-circle-outline" size="24" color="success" />
                        <span class="student-generated-timetable__success-message">
                            Die Stundenpläne wurden erfolgreich erstellt.
                        </span>
                        <span class="student-generated-timetable__summary">
                            <v-chip size="x-small" color="success" variant="tonal">
                                {{ generatedTimetableTotalCountLabel }}
                            </v-chip>
                            <v-chip
                                v-for="countItem in generatedTimetableResultCountItems"
                                :key="countItem.key"
                                size="x-small"
                                :color="countItem.color"
                                variant="tonal">
                                {{ countItem.label }}
                            </v-chip>
                        </span>
                        <span class="student-generated-timetable__selector">
                            <v-btn
                                icon="mdi-chevron-left"
                                size="x-small"
                                variant="tonal"
                                color="primary"
                                :disabled="!canMoveGeneratedTimetable(-1) || generatingTimetable"
                                aria-label="Vorheriger Stundenplan"
                                @click="moveGeneratedTimetable(-1)" />
                            <span v-if="generatedTimetablePositionLabel" class="student-generated-timetable__counter" aria-live="polite">
                                {{ generatedTimetableCounterLabel }}
                            </span>
                            <v-btn
                                icon="mdi-chevron-right"
                                size="x-small"
                                variant="tonal"
                                color="primary"
                                :disabled="!canMoveGeneratedTimetable(1) || generatingTimetable"
                                aria-label="Nächster Stundenplan"
                                @click="moveGeneratedTimetable(1)" />
                            <v-text-field
                                :model-value="selectedTimetableNumber"
                                type="number"
                                min="1"
                                :max="generatedTimetableNumberLimit"
                                density="compact"
                                variant="solo-filled"
                                flat
                                hide-spin-buttons
                                hide-details
                                single-line
                                prefix="Nr."
                                :disabled="generatingTimetable"
                                class="student-generated-timetable__number-input"
                                aria-label="Stundenplan Nummer"
                                @keydown.enter.prevent="commitGeneratedTimetableNumber($event.target.value)"
                                @blur="commitGeneratedTimetableNumber($event.target.value)" />
                        </span>
                    </div>

                    <div class="student-generated-timetable__actions">
                        <v-btn
                            color="error"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-restart"
                            :disabled="loading || saving || generatingTimetable"
                            @click="$emit('restart')">
                            Neustart
                        </v-btn>
                        <span class="student-generated-timetable__actions-spacer" />
                        <v-btn
                            class="student-generated-timetable__back-button"
                            variant="tonal"
                            color="primary"
                            size="large"
                            prepend-icon="mdi-arrow-left"
                            :disabled="saving"
                            @click="handleBack">
                            Zurück
                        </v-btn>
                    </div>

                <div
                    v-if="generatedCriteriaVisible"
                    class="student-generated-criteria">
                    <div
                        v-for="criterion in activeGeneratedQualityCriteria"
                        :key="criterion.key"
                        class="student-generated-criteria__card"
                        :class="{
                            'student-generated-criteria__card--selected': generatedQualityCriterionSelected(criterion),
                            'student-generated-criteria__card--reached': generatedQualityCriterionSelected(criterion) && generatedQualityCriterionReached(criterion),
                            'student-generated-criteria__card--missed': !generatedQualityCriterionReached(criterion),
                        }"
                        :role="generatingTimetable ? null : 'button'"
                        :tabindex="generatingTimetable ? -1 : 0"
                        @click="toggleGeneratedQualityCriterion(criterion)"
                        @keydown.enter.prevent="toggleGeneratedQualityCriterion(criterion)"
                        @keydown.space.prevent="toggleGeneratedQualityCriterion(criterion)">
                        <span class="student-generated-criteria__content">
                            <span class="student-generated-criteria__label">{{ criterion.label }}</span>
                            <span class="student-generated-criteria__meta-row">
                                <span v-if="generatedQualityCriterionMeta(criterion)" class="student-generated-criteria__meta">
                                    {{ generatedQualityCriterionMeta(criterion) }}
                                </span>
                                <span class="student-generated-criteria__controls">
                                    <v-icon
                                        class="student-generated-criteria__status"
                                        :class="{
                                            'student-generated-criteria__status--reached': generatedQualityCriterionReached(criterion),
                                            'student-generated-criteria__status--missed': !generatedQualityCriterionReached(criterion),
                                        }"
                                        :icon="generatedQualityCriterionReached(criterion) ? 'mdi-check-circle' : 'mdi-close-circle'"
                                        size="18"
                                        :title="generatedQualityCriterionReached(criterion)
                                            ? 'Aktueller Stundenplan erfüllt das Kriterium'
                                            : 'Aktueller Stundenplan erfüllt das Kriterium nicht'" />
                                    <v-checkbox-btn
                                        class="student-generated-criteria__check"
                                        :model-value="generatedQualityCriterionSelected(criterion)"
                                        color="primary"
                                        density="compact"
                                        :disabled="generatingTimetable"
                                        :aria-label="`${criterion.label} als Kriterium verwenden`"
                                        @click.stop
                                        @update:model-value="toggleGeneratedQualityCriterion(criterion)" />
                                </span>
                            </span>
                        </span>
                    </div>
                    <div
                        v-if="selectedAdditionalCourseKeys.length"
                        class="student-generated-criteria__card student-generated-criteria__card--additional student-generated-criteria__card--static"
                        :class="{
                            'student-generated-criteria__card--reached': generatedAdditionalCoursesAccepted(),
                            'student-generated-criteria__card--missed': !generatedAdditionalCoursesAccepted(),
                        }">
                        <span class="student-generated-criteria__content">
                            <span class="student-generated-criteria__label">Zusatzkurse</span>
                            <span class="student-generated-criteria__meta-row">
                                <span class="student-generated-criteria__meta">
                                    {{ generatedAdditionalCourseAcceptanceLabel() }}
                                </span>
                                <span class="student-generated-criteria__controls">
                                    <v-icon
                                        class="student-generated-criteria__status"
                                        :icon="generatedAdditionalCourseAcceptanceIcon()"
                                        :color="generatedAdditionalCourseAcceptanceColor()"
                                        size="18" />
                                </span>
                            </span>
                        </span>
                    </div>
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
                        :key="`generated-hour-${hour.value}`">
                        <div class="student-generated-timetable__cell student-generated-timetable__cell--time">
                            <span class="student-generated-timetable__hour">{{ hour.hourLabel }}</span>
                            <span v-if="hour.timeFrom" class="student-generated-timetable__time-range">{{ hour.timeFrom }}</span>
                            <span v-if="hour.timeUntil" class="student-generated-timetable__time-range">{{ hour.timeUntil }}</span>
                        </div>
                        <div
                            v-for="weekday in generatedTimetableWeekdays"
                            :key="`generated-${weekday.value}-${hour.value}`"
                            class="student-generated-timetable__cell"
                            :class="{
                                'student-generated-timetable__cell--filled': generatedTimetableDisplaySlot(weekday.value, hour.value),
                                'student-generated-timetable__cell--conflict': generatedSlotVisualConflictBlocks(generatedTimetableDisplaySlot(weekday.value, hour.value)).length,
                                'student-generated-timetable__cell--has-occasional': generatedTimetableOccasionalMarkers(weekday.value, hour.value).length,
                                'student-generated-timetable__cell--additional': generatedTimetableCellHasAdditionalCourse(weekday.value, hour.value),
                            }">
                            <template v-if="generatedTimetableDisplaySlot(weekday.value, hour.value)">
                                <div
                                    v-for="block in generatedSlotDisplayBlocks(generatedTimetableDisplaySlot(weekday.value, hour.value))"
                                    :key="generatedSlotBlockKey(block)"
                                    class="student-generated-timetable__entry"
                                    :class="{ 'student-generated-timetable__entry--conflict': block.isConflict === true }">
                                    <div class="student-generated-timetable__code">
                                        <span>{{ generatedSlotTitle(block) }}</span>
                                        <sup v-if="block.isDistanceLearningCourse" class="student-generated-timetable__badge">FU</sup>
                                        <sup
                                            v-if="generatedSlotWeekMarker(block)"
                                            class="student-generated-timetable__badge student-generated-timetable__badge--week">
                                            {{ generatedSlotWeekMarker(block) }}
                                        </sup>
                                    </div>
                                    <div v-if="generatedSlotDetails(block)" class="student-generated-timetable__details">
                                        {{ generatedSlotDetails(block) }}
                                    </div>
                                    <div v-if="generatedSlotDateLabel(block)" class="student-generated-timetable__date">
                                        {{ generatedSlotDateLabel(block) }}
                                    </div>
                                </div>
                            </template>
                            <div
                                v-if="generatedTimetableOccasionalMarkers(weekday.value, hour.value).length"
                                class="student-generated-timetable__occasional-markers">
                                <span
                                    v-for="marker in generatedTimetableOccasionalMarkers(weekday.value, hour.value)"
                                    :key="marker.key"
                                    class="student-generated-timetable__occasional-marker"
                                    :class="{ 'student-generated-timetable__occasional-marker--additional': marker.isAdditionalCourse }"
                                    :title="generatedOccasionalMarkerTitle(marker)">
                                    <span>{{ generatedOccasionalMarkerLabel(marker) }}</span>
                                    <sup v-if="marker.isDistanceLearningCourse" class="student-generated-timetable__badge">FU</sup>
                                    <sup
                                        v-if="marker.weekMarker"
                                        class="student-generated-timetable__badge student-generated-timetable__badge--week">
                                        {{ marker.weekMarker }}
                                    </sup>
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
                <div
                    v-if="generatedTimetableConflictSummaryItems.length"
                    class="student-generated-conflicts"
                    :class="`student-generated-conflicts--${generatedTimetableConflictSeverity}`">
                    <div class="student-generated-conflicts__title">
                        <v-icon :icon="generatedTimetableConflictIcon" size="16" />
                        <span>{{ generatedTimetableConflictTitle }}</span>
                    </div>
                    <ul class="student-generated-conflicts__list">
                        <li
                            v-for="conflict in generatedTimetableConflictSummaryItems"
                            :key="conflict">
                            {{ conflict }}
                        </li>
                    </ul>
                </div>
                </template>
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
                prepend-icon="mdi-arrow-left"
                :disabled="saving || generatingTimetable"
                @click="$emit('close')">
                Zurück
            </v-btn>
        </div>

        <div v-else-if="currentStep !== 'result'" class="student-evaluation-settings__footer">
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

const SHOW_RESULT_COURSE_PANELS = false
const SHOW_GENERATED_CRITERIA = false

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
        initialDeselectedCourseGroupKeys: {
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
            deselectedCourseGroupKeys: this.normalizedCourseKeys(this.initialDeselectedCourseGroupKeys),
            selectedAdditionalCourseKeys: [],
            selectedQualityCriterionKeys: this.normalizedCourseKeys(this.initialSelectedQualityCriterionKeys),
            generatedTimetable: null,
            schoolHours: [],
            timetableCounts: {},
            selectedTimetableType: null,
            selectedTimetableNumber: 1,
            defaultQualityCriterionSelectionApplied: false,
            generatingTimetable: false,
            timetableError: '',
            error: '',
            criteriaEditorDialogOpen: false,
            resultBackStep: this.normalizedResultBackStep(this.normalizedStep(this.initialStep)),
            resultCoursePanelOpen: ['additional-courses'],
            additionalCourseSelectionChangedAfterTimetable: false,
            additionalCourseSelectionLocked: false,
            pendingRemovedSelectedCourseKeys: [],
            resultMoreCoursesVisible: false,
            resultOptionsVisible: false,
            selectedResultMoreCourseKey: '',
            resultDraftAdditionalCourseKeys: [],
            resultDraftQualityCriterionKeys: [],
            resultMoreOfferedCourseSelectionOverrides: {},
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
            return this.selectedCoursesForSummary().length
        },
        allCoursesSelectedByDefault() {
            return this.selectedCourseKeys.length === 0
        },
        allSelectableCoursesSelected() {
            if (this.allCoursesSelectedByDefault) {
                return true
            }

            return this.proposedCourses.every(course => this.courseSelected(course))
        },
        effectiveSelectedCourseCount() {
            return this.selectedCourseKeys.length || this.effectiveCourseSummaryTotal
        },
        effectiveCourseSummaryTotal() {
            return Number(this.courseSummary?.total || this.proposedCourses.length || 0)
        },
        courseSummaryTitle() {
            return this.courseSummary?.title || 'Negative Kurse + Vorgesehene Kurse'
        },
        displayedCourseSections() {
            return Array.isArray(this.courseSections) ? this.courseSections : []
        },
        regularCourseSections() {
            return this.displayedCourseSections
                .filter(section => String(section?.key || '') !== 'additional')
        },
        additionalCourseSections() {
            return this.displayedCourseSections
                .filter(section => String(section?.key || '') === 'additional')
                .map(section => ({
                    ...section,
                    items: this.sortedCourseItems(section?.items),
                }))
                .filter(section => section.items.length > 0)
        },
        additionalCourses() {
            return this.additionalCourseSections
                .flatMap(section => section.items)
        },
        resultMoreCourseItems() {
            return this.additionalCourses.map(course => this.resultMoreCourseItem(course))
        },
        selectedResultMoreCourseItem() {
            return this.resultMoreCourseItems
                .find(course => course.selectionKey === this.selectedResultMoreCourseKey) || null
        },
        selectedResultMoreCourseOfferedCourseItems() {
            return this.selectedResultMoreCourseItem
                ? this.resultMoreOfferedCourseItemsForSelectedCourse(this.selectedResultMoreCourseItem)
                : []
        },
        selectedVisibleAdditionalCourseKeys() {
            return this.additionalCourses
                .filter(course => this.courseSelectedFromKeys(course, this.selectedAdditionalCourseKeys))
                .map(course => this.backendCourseSelectionKey(course))
                .filter(Boolean)
        },
        hiddenGeneratedAdditionalCourseKeySet() {
            return new Set([
                ...(Array.isArray(this.timetableCounts?.conflicting_additional_course_keys)
                    ? this.timetableCounts.conflicting_additional_course_keys
                    : []),
                ...this.hiddenGeneratedAdditionalCourses()
                    .flatMap(course => this.courseComparisonKeys(course)),
            ]
                .map(courseKey => this.normalizedCourseCode(courseKey))
                .filter(Boolean))
        },
        additionalCoursePanelVisible() {
            return this.currentStep === 'result'
                && Boolean(this.generatedTimetable)
                && this.additionalCourses.length > 0
        },
        resultCoursePanelsVisible() {
            return SHOW_RESULT_COURSE_PANELS
                && (this.readOnlySelectedCourseSections.length > 0 || this.additionalCoursePanelVisible)
        },
        readOnlySelectedCourseSections() {
            return this.regularCourseSections
                .map(section => ({
                    ...section,
                    items: (Array.isArray(section?.items) ? section.items : [])
                        .filter(course => this.courseSelectedByDefault(course)),
                }))
                .filter(section => section.items.length > 0)
        },
        selectedCourseHoursLabel() {
            if (this.allCoursesSelectedByDefault && this.courseSummary?.hours_label) {
                return this.courseSummary.hours_label
            }

            const hours = this.selectedCoursesForSummary()
                .reduce((totalHours, course) => totalHours + this.courseHoursNumber(course), 0)

            if (!hours) {
                return ''
            }

            return `${new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(hours)} Std.`
        },
        resultSelectedCourseItems() {
            return this.resultSelectedCourses.map(course => this.resultSelectedCourseItem(course))
        },
        resultSelectedCourseSummary() {
            return this.courseItemsSummary(this.resultSelectedCourses)
        },
        resultSelectedCourses() {
            const pendingRemovedCourseKeys = new Set(this.pendingRemovedSelectedCourseKeys)

            return this.selectedCoursesForSummary()
                .filter(course => typeof this.courseSelectedFromKeys === 'function'
                    ? !this.courseSelectedFromKeys(course, [...pendingRemovedCourseKeys])
                    : !pendingRemovedCourseKeys.has(this.courseSelectionKey(course)))
        },
        resultSelectedCourseRemovalPending() {
            return this.pendingRemovedSelectedCourseKeys.length > 0
        },
        resultMoreCoursesUnavailable() {
            return !this.generatedTimetable || this.additionalCourses.length === 0
        },
        resultOptionsUnavailable() {
            return !this.generatedTimetable || this.activeGeneratedQualityCriteria.length === 0
        },
        selectedActiveQualityCriterionKeys() {
            const activeCriterionKeys = this.activeQualityCriterionKeys()

            return this.selectedQualityCriterionKeys
                .filter(criterionKey => activeCriterionKeys.includes(criterionKey))
        },
        resultAdditionalCoursesChanged() {
            return !this.normalizedCourseKeyListsEqual(
                this.resultDraftAdditionalCourseKeys,
                this.selectedAdditionalCourseKeys,
            )
                || this.resultMoreDeselectedOfferedCourseGroupKeys().join('|') !== this.currentResultMoreDeselectedOfferedCourseGroupKeys().join('|')
        },
        resultOptionsChanged() {
            return !this.normalizedCourseKeyListsEqual(
                this.resultDraftQualityCriterionKeys,
                this.selectedQualityCriterionKeys,
            )
        },
        resultInitialSelectedCourseKeys() {
            const initialSelectedCourseKeys = this.normalizedCourseKeys(this.initialSelectedCourseKeys)

            return initialSelectedCourseKeys.length
                ? this.selectedCourseKeysForCourses(this.proposedCourses, initialSelectedCourseKeys)
                : this.proposedCourses.map(course => this.courseSelectionKey(course)).filter(Boolean)
        },
        resultCalculationResetAvailable() {
            if (!this.generatedTimetable) {
                return false
            }

            const currentSelectedCourseKeys = typeof this.selectedCourseKeysForCourses === 'function'
                ? this.selectedCourseKeysForCourses(this.proposedCourses, this.selectedCourseKeys)
                : this.selectedCourseKeys

            return !this.normalizedCourseKeyListsEqual(
                currentSelectedCourseKeys,
                this.resultInitialSelectedCourseKeys,
            )
                || !this.normalizedCourseKeyListsEqual(this.selectedQualityCriterionKeys, this.initialSelectedQualityCriterionKeys)
                || this.selectedAdditionalCourseKeys.length > 0
                || this.pendingRemovedSelectedCourseKeys.length > 0
                || this.resultMoreCoursesVisible
                || this.resultOptionsVisible
                || Number(this.selectedTimetableNumber || 1) !== 1
        },
        selectedCourses() {
            return this.selectedCoursesForSummary()
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

            return Array.from({ length: (lastHour - firstHour) + 1 }, (value, index) => {
                const hour = firstHour + index
                const schoolHour = this.generatedTimetableSchoolHour(hour)

                return {
                    value: hour,
                    hourLabel: `${hour}.`,
                    timeFrom: this.generatedTimetableHourTimeFrom(schoolHour, hour),
                    timeUntil: this.generatedTimetableHourTimeUntil(schoolHour, hour),
                }
            })
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
            if (this.selectedActiveQualityCriterionKeys.length) {
                return this.selectedQualityCriteriaTimetableCount()
            }

            return this.generatedTimetableCountForType(this.generatedTimetableNavigationType)
        },
        generatedTimetableAbsoluteNumber() {
            return this.selectedTimetableNumber
        },
        generatedTimetablePositionLabel() {
            const totalCount = this.generatedTimetableTotalCount

            if (!totalCount) {
                return ''
            }

            return `${new Intl.NumberFormat('de-AT').format(this.generatedTimetableAbsoluteNumber)} / ${new Intl.NumberFormat('de-AT').format(totalCount)}`
        },
        generatedTimetableCounterLabel() {
            const totalCount = this.generatedTimetableTotalCount

            if (!totalCount) {
                return ''
            }

            return `${new Intl.NumberFormat('de-AT').format(this.generatedTimetableAbsoluteNumber)} / ${new Intl.NumberFormat('de-AT').format(totalCount)} ${this.generatedTimetableResultTypeCounterLabel}`
        },
        generatedTimetableResultTypeCounterLabel() {
            return this.generatedTimetableNavigationType === 'conflict' ? 'Konflikte' : 'gültige'
        },
        generatedTimetableNumberLimit() {
            return Math.max(1, this.generatedTimetableTotalCount || this.selectedTimetableNumber)
        },
        generatedTimetableTotalGeneratedCount() {
            const explicitTotalCount = Number(this.timetableCounts?.total_timetable_count || 0)

            if (Number.isFinite(explicitTotalCount) && explicitTotalCount > 0) {
                return explicitTotalCount
            }

            return this.generatedTimetableValidResultCount + this.generatedTimetableConflictResultCount
        },
        generatedTimetableTotalCountLabel() {
            return `${new Intl.NumberFormat('de-AT').format(this.generatedTimetableTotalGeneratedCount)} Stundenpläne gesamt`
        },
        generatedTimetableValidResultCount() {
            if (this.selectedActiveQualityCriterionKeys.length) {
                return this.selectedQualityCriteriaTimetableCount()
            }

            return this.generatedTimetableCountForType('full_green') + this.generatedTimetableCountForType('green')
        },
        generatedTimetableConflictResultCount() {
            return this.generatedTimetableCountForType('conflict')
        },
        generatedTimetableResultCountItems() {
            return [
                {
                    key: 'valid',
                    color: 'success',
                    count: this.generatedTimetableValidResultCount,
                    singular: 'gültig',
                    plural: 'gültig',
                },
                {
                    key: 'conflict',
                    color: 'error',
                    count: this.generatedTimetableConflictResultCount,
                    singular: 'Konflikt',
                    plural: 'Konflikte',
                },
            ].map(item => ({
                ...item,
                label: `${new Intl.NumberFormat('de-AT').format(item.count)} ${item.count === 1 ? item.singular : item.plural}`,
            }))
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
        generatedCriteriaVisible() {
            return SHOW_GENERATED_CRITERIA
                && (this.activeGeneratedQualityCriteria.length > 0 || this.selectedAdditionalCourseKeys.length > 0)
        },
        generatedTimetableConflictPairs() {
            const conflictPairsByKey = new Map()

            Object.values(this.generatedTimetable?.slots || {}).forEach(slot => {
                const slotBlock = this.generatedSlotBlock(slot)

                this.generatedSlotConflictBlocksIncludingRegular(slot).forEach(conflict => {
                    const conflictBlock = this.generatedSlotBlock(conflict)
                    const dateLabel = this.generatedTimetableConflictDateLabel(slotBlock, conflictBlock)
                    const pairKey = [
                        Number(slotBlock?.courseGroup?.weekday),
                        Number(slotBlock?.courseGroup?.hour),
                        ...[
                            this.generatedSlotBlockIdentity(slotBlock) || this.generatedSlotBlockKey(slotBlock),
                            this.generatedSlotBlockIdentity(conflictBlock) || this.generatedSlotBlockKey(conflictBlock),
                        ].sort(),
                        dateLabel,
                    ].filter(Boolean).join('|')

                    if (!pairKey || conflictPairsByKey.has(pairKey)) {
                        return
                    }

                    conflictPairsByKey.set(pairKey, {
                        conflict: conflictBlock,
                        key: pairKey,
                        slot: slotBlock,
                    })
                })
            })

            return Array.from(conflictPairsByKey.values())
        },
        generatedTimetableConflictSeverity() {
            return this.generatedTimetableConflictPairs.length > 0
                && this.generatedTimetableConflictPairs.every(pair => this.generatedTimetableConflictPairIsOccasional(pair))
                ? 'warning'
                : 'error'
        },
        generatedTimetableConflictIcon() {
            return this.generatedTimetableConflictSeverity === 'warning'
                ? 'mdi-alert-outline'
                : 'mdi-alert-circle-outline'
        },
        generatedTimetableConflictTitle() {
            return this.generatedTimetableConflictSeverity === 'warning'
                ? 'Überschneidungen'
                : 'Konflikte'
        },
        generatedTimetableConflictSummaryItems() {
            const conflictLabels = this.generatedTimetableConflictPairs
                .map(pair => this.generatedTimetableConflictSummaryLabel(pair.slot, pair.conflict))
                .map(conflict => String(conflict || '').trim())
                .filter(Boolean)
                .filter((conflict, index, conflicts) => conflicts.indexOf(conflict) === index)

            if (conflictLabels.length) {
                return conflictLabels
            }

            const problems = Array.isArray(this.generatedTimetable?.problems) ? this.generatedTimetable.problems : []

            return problems
                .map(problem => String(problem || '').trim())
                .filter(Boolean)
                .filter((problem, index, allProblems) => allProblems.indexOf(problem) === index)
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
                this.pendingRemovedSelectedCourseKeys = []
                this.resultDraftAdditionalCourseKeys = this.normalizedCourseKeys(this.selectedAdditionalCourseKeys)
            }
        },
        initialDeselectedCourseGroupKeys(courseGroupKeys) {
            this.deselectedCourseGroupKeys = this.normalizedCourseKeys(courseGroupKeys)
        },
        initialSelectedQualityCriterionKeys(criterionKeys) {
            const selectedCriterionKeys = this.normalizedCourseKeys(criterionKeys)

            if (selectedCriterionKeys.length || this.selectedQualityCriterionKeys.length) {
                this.selectedQualityCriterionKeys = selectedCriterionKeys
                this.resultDraftQualityCriterionKeys = this.normalizedCourseKeys(this.selectedQualityCriterionKeys)
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
                this.resultBackStep = 'courses'
                await this.createAutomaticTimetable()

                return
            }

            if (await this.saveSettings()) {
                this.ensureDefaultQualityCriterionSelection()
                this.selectAllProposedCourses()
                this.moveToStep('result')
                this.ensureAutomaticTimetableForResultStep()
            }
        },

        handleBack() {
            if (this.currentStep === 'result') {
                if (this.additionalCourseSelectionChangedAfterTimetable) {
                    this.resetAdditionalCourseSelection()

                    return
                }

                if (this.additionalCourseSelectionLocked) {
                    this.additionalCourseSelectionLocked = false
                    this.commitAdditionalCourseSelectionChange()

                    return
                }

                this.moveToStep(this.resultBackStep)

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
            const courseGroup = typeof this.courseSelectionGroup === 'function'
                ? this.courseSelectionGroup(course)
                : 'planned'
            const normalizedCode = typeof this.normalizedCourseCode === 'function'
                ? this.normalizedCourseCode(course?.code || course?.label || '')
                : String(course?.code || course?.label || '').trim().toUpperCase().replace(/\s+/g, '')

            if (courseGroup && normalizedCode) {
                return `${courseGroup}:${normalizedCode}`
            }

            return typeof this.backendCourseSelectionKey === 'function'
                ? this.backendCourseSelectionKey(course)
                : String(course?.key || course?.code || course?.label || '')
        },
        backendCourseSelectionKey(course) {
            return String(course?.key || [
                course?.code || '',
                course?.name || '',
                course?.semester || '',
                course?.branch || '',
                course?.hours ?? course?.hours_per_week ?? '',
            ].join('|'))
        },
        courseSelectionGroup(course) {
            const explicitCourseGroup = this.normalizedCourseSelectionGroupKey(
                course?.courseGroup || course?.course_group || course?.sectionKey || course?.section_key || '',
            )

            if (explicitCourseGroup) {
                return explicitCourseGroup
            }

            const backendKey = this.backendCourseSelectionKey(course)
            const normalizedCode = this.normalizedCourseCode(course?.code || course?.label || '')
            const displayedCourseSections = Array.isArray(this.displayedCourseSections)
                ? this.displayedCourseSections
                : []
            const section = displayedCourseSections.find(currentSection => (
                Array.isArray(currentSection?.items)
                    && currentSection.items.some(sectionCourse => (
                        this.backendCourseSelectionKey(sectionCourse) === backendKey
                            || (
                                normalizedCode
                                && this.normalizedCourseCode(sectionCourse?.code || sectionCourse?.label || '') === normalizedCode
                            )
                    ))
            ))
            const sectionCourseGroup = this.normalizedCourseSelectionGroupKey(section?.key || '')

            if (sectionCourseGroup) {
                return sectionCourseGroup
            }

            const additionalCourses = Array.isArray(this.additionalCourses) ? this.additionalCourses : []
            const matchesAdditionalCourse = additionalCourses.some(additionalCourse => (
                this.backendCourseSelectionKey(additionalCourse) === backendKey
                    || (
                        normalizedCode
                        && this.normalizedCourseCode(additionalCourse?.code || additionalCourse?.label || '') === normalizedCode
                    )
            ))

            return matchesAdditionalCourse ? 'additional' : 'planned'
        },
        normalizedCourseSelectionGroupKey(courseGroup) {
            const normalizedCourseGroup = String(courseGroup || '').trim()

            if (normalizedCourseGroup === 'proposed') {
                return 'planned'
            }

            return ['missing', 'planned', 'additional'].includes(normalizedCourseGroup)
                ? normalizedCourseGroup
                : ''
        },
        courseSelectedFromKeys(course, courseKeys) {
            return this.normalizedCourseKeys(courseKeys)
                .some(courseKey => this.selectionKeyMatchesCourse(courseKey, course))
        },
        selectedCourseKeysForCourses(courses, courseKeys) {
            const selectedCourseKeys = this.normalizedCourseKeys(courseKeys)

            return (Array.isArray(courses) ? courses : [])
                .filter(course => selectedCourseKeys.some(courseKey => this.selectionKeyMatchesCourse(courseKey, course)))
                .map(course => this.courseSelectionKey(course))
                .filter(Boolean)
                .filter((courseKey, index, courseKeyList) => courseKeyList.indexOf(courseKey) === index)
        },
        selectedBackendCourseKeys() {
            return this.selectedCoursesForSummary()
                .map(course => this.backendCourseSelectionKey(course))
                .filter(Boolean)
                .filter((courseKey, index, courseKeys) => courseKeys.indexOf(courseKey) === index)
        },
        selectionKeyMatchesCourse(selectionKey, course) {
            const value = String(selectionKey || '').trim()

            if (!value) {
                return false
            }

            if (value === this.courseSelectionKey(course) || value === this.backendCourseSelectionKey(course)) {
                return true
            }

            const compactMatch = value.match(/^(missing|planned|proposed|additional):(.+)$/u)

            if (compactMatch) {
                const courseGroup = this.normalizedCourseSelectionGroupKey(compactMatch[1])
                const normalizedCode = this.normalizedCourseCode(compactMatch[2])

                return courseGroup === this.courseSelectionGroup(course)
                    && normalizedCode !== ''
                    && normalizedCode === this.normalizedCourseCode(course?.code || course?.label || '')
            }

            const serializedParts = value.split('|')

            if (serializedParts.length >= 7) {
                const selectedCode = serializedParts[serializedParts.length - 1]

                return this.courseSelectionGroup(course) === 'planned'
                    && this.normalizedCourseCode(selectedCode) === this.normalizedCourseCode(course?.code || course?.label || '')
            }

            const normalizedValue = this.normalizedCourseCode(value)

            return normalizedValue !== ''
                && [
                    course?.code,
                    course?.label,
                    course?.name,
                    course?.json_code,
                ].some(courseValue => this.normalizedCourseCode(courseValue) === normalizedValue)
        },
        sortedCourseItems(courses) {
            return [...(Array.isArray(courses) ? courses : [])]
                .sort((firstCourse, secondCourse) => this.compareCourseItems(firstCourse, secondCourse))
        },
        compareCourseItems(firstCourse, secondCourse) {
            const labelComparison = this.courseSortLabel(firstCourse).localeCompare(this.courseSortLabel(secondCourse), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })

            if (labelComparison !== 0) {
                return labelComparison
            }

            const firstSemester = Number(firstCourse?.semester || 0)
            const secondSemester = Number(secondCourse?.semester || 0)

            return firstSemester - secondSemester
        },
        courseSortLabel(course) {
            return String(course?.label || course?.code || course?.name || '').trim()
        },
        resultSelectedCourseItem(course) {
            const hours = this.courseHoursNumber(course)
            const label = String(course?.label || course?.code || course?.name || '').trim()

            return {
                selectionKey: this.courseSelectionKey(course),
                label: label || '-',
                meta: String(course?.hoursMeta || (hours ? `${new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(hours)} Std.` : '') || course?.meta || '').trim(),
            }
        },
        resultMoreCourseItem(course) {
            const selectedCourseItem = this.resultSelectedCourseItem(course)

            return {
                ...course,
                ...selectedCourseItem,
                courseGroupLabel: 'Zusätzlich',
                distanceLearning: this.resultMoreCourseIsDistanceLearning(course),
            }
        },
        removeResultSelectedCourseItem(course) {
            if (this.generatingTimetable) {
                return
            }

            const courseKey = String(course?.selectionKey || '').trim()

            if (!courseKey || this.pendingRemovedSelectedCourseKeys.includes(courseKey)) {
                return
            }

            this.pendingRemovedSelectedCourseKeys = [...this.pendingRemovedSelectedCourseKeys, courseKey]
        },
        cancelResultSelectedCourseRemoval() {
            this.pendingRemovedSelectedCourseKeys = []
        },
        async applyResultSelectedCourseRemoval() {
            if (!this.resultSelectedCourseRemovalPending || this.generatingTimetable) {
                return
            }

            const selectedCourseKeys = this.resultSelectedCourseItems
                .map(course => course.selectionKey)
                .filter(Boolean)

            if (!selectedCourseKeys.length) {
                return
            }

            this.selectedCourseKeys = selectedCourseKeys
            this.pendingRemovedSelectedCourseKeys = []
            this.resetGeneratedTimetableSelection()
            await this.createAutomaticTimetable()
        },
        toggleResultMoreCourses() {
            if (this.generatingTimetable || this.resultMoreCoursesUnavailable) {
                return
            }

            if (this.resultMoreCoursesVisible) {
                this.closeResultMoreCourses()

                return
            }

            this.resultDraftAdditionalCourseKeys = this.normalizedCourseKeys(this.selectedAdditionalCourseKeys)
            this.resultMoreOfferedCourseSelectionOverrides = this.resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys()
            this.selectedResultMoreCourseKey = ''
            this.resultMoreCoursesVisible = true
            this.resultOptionsVisible = false
        },
        closeResultMoreCourses() {
            this.resultMoreCoursesVisible = false
            this.resultDraftAdditionalCourseKeys = this.normalizedCourseKeys(this.selectedAdditionalCourseKeys)
            this.selectedResultMoreCourseKey = ''
            this.resultMoreOfferedCourseSelectionOverrides = this.resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys()
        },
        resultDraftAdditionalCourseSelected(course) {
            return this.resultDraftAdditionalCourseKeys.includes(this.courseSelectionKey(course))
        },
        setResultDraftAdditionalCourseSelected(course, selected) {
            const courseKey = this.courseSelectionKey(course)

            if (!courseKey) {
                return
            }

            if (selected === true && !this.resultDraftAdditionalCourseKeys.includes(courseKey)) {
                this.resultDraftAdditionalCourseKeys = [...this.resultDraftAdditionalCourseKeys, courseKey]

                return
            }

            if (selected !== true) {
                this.resultDraftAdditionalCourseKeys = this.resultDraftAdditionalCourseKeys
                    .filter(selectedCourseKey => selectedCourseKey !== courseKey)
            }
        },
        toggleResultMoreCourseOffers(course) {
            if (this.generatingTimetable) {
                return
            }

            const courseKey = String(course?.selectionKey || this.courseSelectionKey(course) || '').trim()
            if (!courseKey) {
                return
            }

            this.selectedResultMoreCourseKey = courseKey
            this.setResultDraftAdditionalCourseSelected(course, true)
        },
        resultMoreOfferedCourseSelected(course) {
            return this.resultMoreOfferedCourseSelectionOverrides[course?.selectionKey] !== false
        },
        toggleResultMoreOfferedCourseItem(course) {
            const selectionKey = String(course?.selectionKey || '').trim()
            if (!selectionKey) {
                return
            }

            const resultMoreOfferedCourseSelectionOverrides = { ...this.resultMoreOfferedCourseSelectionOverrides }

            if (this.resultMoreOfferedCourseSelected(course)) {
                resultMoreOfferedCourseSelectionOverrides[selectionKey] = false
            } else {
                delete resultMoreOfferedCourseSelectionOverrides[selectionKey]
            }

            this.resultMoreOfferedCourseSelectionOverrides = resultMoreOfferedCourseSelectionOverrides
            if (this.selectedResultMoreCourseItem) {
                this.setResultDraftAdditionalCourseSelected(
                    this.selectedResultMoreCourseItem,
                    this.resultMoreOfferedCourseItemsAnySelected(this.selectedResultMoreCourseItem),
                )
            }
        },
        deselectSelectedResultMoreCourseOfferedCourses() {
            const offeredCourses = this.selectedResultMoreCourseOfferedCourseItems
            if (!offeredCourses.length) {
                return
            }

            this.resultMoreOfferedCourseSelectionOverrides = {
                ...this.resultMoreOfferedCourseSelectionOverrides,
                ...Object.fromEntries(offeredCourses.map(course => [course.selectionKey, false])),
            }

            if (this.selectedResultMoreCourseItem) {
                this.setResultDraftAdditionalCourseSelected(this.selectedResultMoreCourseItem, false)
            }
        },
        resultMoreOfferedCourseItemsAllDeselected(course) {
            const offeredCourses = this.resultMoreOfferedCourseItemsForSelectedCourse(course)

            return offeredCourses.length > 0 && offeredCourses.every(offeredCourse => !this.resultMoreOfferedCourseSelected(offeredCourse))
        },
        resultMoreOfferedCourseItemsAnySelected(course) {
            const offeredCourses = this.resultMoreOfferedCourseItemsForSelectedCourse(course)

            return !offeredCourses.length || offeredCourses.some(offeredCourse => this.resultMoreOfferedCourseSelected(offeredCourse))
        },
        resultMoreOfferedCourseItemsPartlySelected(course) {
            const offeredCourses = this.resultMoreOfferedCourseItemsForSelectedCourse(course)
            if (offeredCourses.length <= 1) {
                return false
            }

            const selectedOfferedCourseCount = offeredCourses
                .filter(offeredCourse => this.resultMoreOfferedCourseSelected(offeredCourse))
                .length

            return selectedOfferedCourseCount > 0 && selectedOfferedCourseCount < offeredCourses.length
        },
        resultMoreOfferedCourseItemsForSelectedCourse(course) {
            const courseGroups = this.resultMoreCourseGroupsForSelectedCourse(course)
            const offeredCourseItems = courseGroups
                .map(courseGroup => this.resultMoreOfferedCourseGroupItem(courseGroup, course))
                .sort((firstCourse, secondCourse) => this.compareResultMoreOfferedCourseItems(firstCourse, secondCourse))

            return this.uniqueResultMoreOfferedCourseItems(offeredCourseItems, course)
        },
        resultMoreCourseGroupsForSelectedCourse(course) {
            return Array.isArray(course?.course_groups) ? course.course_groups : []
        },
        resultMoreOfferedCourseGroupItem(courseGroup, selectedCourse) {
            const code = String(courseGroup?.course || courseGroup?.subject || courseGroup?.title || selectedCourse?.code || '').trim()
            const groupSelectionLabel = this.resultMoreOfferedCourseGroupSelectionLabel(courseGroup)

            return {
                key: this.resultMoreCourseGroupKey(courseGroup),
                selectionKey: [
                    selectedCourse?.selectionKey || this.courseSelectionKey(selectedCourse),
                    this.resultMoreCourseGroupKey(courseGroup),
                ].filter(Boolean).join('::'),
                code,
                name: this.resultMoreOfferedCourseName(courseGroup, code),
                groupSelectionLabel,
                backendSelectionKey: [
                    selectedCourse?.key || selectedCourse?.selectionKey || this.courseSelectionKey(selectedCourse),
                    groupSelectionLabel,
                ].filter(Boolean).join('|'),
                weekday: Number(courseGroup?.weekday || 0) || null,
                hour: Number(courseGroup?.hour || 0) || null,
                courseGroups: [courseGroup],
                scheduleLabel: this.resultMoreCourseGroupScheduleLabel(courseGroup),
                recurrenceLabel: this.resultMoreCourseGroupWeekMarker(courseGroup).replace(/[()]/gu, '').trim(),
                roomsLabel: (Array.isArray(courseGroup?.rooms) ? courseGroup.rooms : []).filter(Boolean).join(', '),
                distanceLearning: this.resultMoreOfferedCourseIsDistanceLearning([courseGroup], selectedCourse),
            }
        },
        uniqueResultMoreOfferedCourseItems(courseItems, selectedCourse) {
            const courseItemsByIdentity = new Map()
            const offeredCourseItems = Array.isArray(courseItems) ? courseItems : []

            offeredCourseItems.forEach((courseItem) => {
                const identityKey = this.resultMoreOfferedCourseIdentityKey(courseItem)
                const existingCourseItem = courseItemsByIdentity.get(identityKey)

                if (!existingCourseItem) {
                    courseItemsByIdentity.set(identityKey, { ...courseItem })

                    return
                }

                const courseGroups = [
                    ...(Array.isArray(existingCourseItem.courseGroups) ? existingCourseItem.courseGroups : []),
                    ...(Array.isArray(courseItem.courseGroups) ? courseItem.courseGroups : []),
                ]

                courseItemsByIdentity.set(identityKey, {
                    ...existingCourseItem,
                    courseGroups,
                    scheduleLabel: this.resultMoreMergedLabelList(existingCourseItem.scheduleLabel, courseItem.scheduleLabel),
                    recurrenceLabel: this.resultMoreMergedLabelList(existingCourseItem.recurrenceLabel, courseItem.recurrenceLabel),
                    roomsLabel: this.resultMoreMergedLabelList(existingCourseItem.roomsLabel, courseItem.roomsLabel),
                    distanceLearning: this.resultMoreOfferedCourseIsDistanceLearning(courseGroups, selectedCourse),
                })
            })

            return Array.from(courseItemsByIdentity.values())
        },
        resultMoreOfferedCourseIdentityKey(courseItem) {
            return [
                this.normalizedCourseCode(courseItem?.code),
                this.normalizedCourseCode(courseItem?.name),
            ].join('|')
        },
        resultMoreMergedLabelList(firstLabel, secondLabel) {
            return [
                ...String(firstLabel || '').split(','),
                ...String(secondLabel || '').split(','),
            ]
                .map(label => label.trim())
                .filter(Boolean)
                .filter((label, index, labels) => labels.indexOf(label) === index)
                .join(', ')
        },
        resultMoreOfferedCourseName(courseGroup, code) {
            return [
                code,
                courseGroup?.student_group || courseGroup?.class_name || courseGroup?.display_label,
                courseGroup?.teacher,
            ]
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
                .join(' - ')
                || courseGroup?.display_label
                || courseGroup?.title
                || 'Ohne Bezeichnung'
        },
        resultMoreOfferedCourseGroupSelectionLabel(courseGroup) {
            return String(
                courseGroup?.class_name
                || courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || '',
            ).trim()
        },
        resultMoreOfferedCourseIsDistanceLearning(courseGroups, selectedCourse) {
            const requiredSlotCount = Math.max(1, Math.round(this.courseHoursNumber(selectedCourse)))
            const scheduledWeeklyLoad = this.resultMoreCourseGroupsScheduledWeeklyLoad(courseGroups)

            return requiredSlotCount >= 2
                && scheduledWeeklyLoad > 0
                && Math.abs((scheduledWeeklyLoad * 2) - requiredSlotCount) < 0.001
        },
        resultMoreCourseIsDistanceLearning(course) {
            if (
                course?.distanceLearning === true
                || course?.distance_learning === true
                || course?.isDistanceLearningCourse === true
                || course?.is_distance_learning_course === true
            ) {
                return true
            }

            return this.resultMoreOfferedCourseIsDistanceLearning(this.resultMoreCourseGroupsForSelectedCourse(course), course)
        },
        compareResultMoreOfferedCourseItems(firstCourse, secondCourse) {
            const firstSlot = Number(firstCourse?.weekday || 0) * 100 + Number(firstCourse?.hour || 0)
            const secondSlot = Number(secondCourse?.weekday || 0) * 100 + Number(secondCourse?.hour || 0)

            if (firstSlot !== secondSlot) {
                return firstSlot - secondSlot
            }

            return String(firstCourse?.name || firstCourse?.code || '').localeCompare(
                String(secondCourse?.name || secondCourse?.code || ''),
                'de-AT',
                {
                    numeric: true,
                    sensitivity: 'base',
                },
            )
        },
        resultMoreOfferedCourseCodeVisible(course) {
            const code = String(course?.code || '').trim()
            const name = String(course?.name || '').trim()

            return Boolean(code && name && code !== name && !name.startsWith(`${code} -`))
        },
        resultMoreCourseGroupKey(courseGroup) {
            return String(courseGroup?.key || [
                courseGroup?.course || courseGroup?.subject || courseGroup?.title || '',
                courseGroup?.weekday || '',
                courseGroup?.hour || '',
                courseGroup?.teacher || '',
            ].join('|'))
        },
        resultMoreCourseGroupScheduleLabel(courseGroup) {
            return [
                this.resultMoreCourseGroupWeekdayLabel(courseGroup),
                this.resultMoreCourseGroupTimeRange(courseGroup),
                this.resultMoreCourseGroupWeekMarker(courseGroup),
            ]
                .filter(Boolean)
                .join(' ')
        },
        resultMoreCourseGroupWeekdayLabel(courseGroup) {
            const weekday = Number(courseGroup?.weekday || 0)
            const weekdayLabels = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']

            return weekdayLabels[weekday] || ''
        },
        resultMoreCourseGroupTimeRange(courseGroup) {
            const from = this.formatTimeValue(
                courseGroup?.time_from
                || courseGroup?.from
                || this.generatedTimetableConfiguredSchoolHour(courseGroup?.hour)?.from,
            )
            const until = this.formatTimeValue(
                courseGroup?.time_until
                || courseGroup?.until
                || this.generatedTimetableConfiguredSchoolHour(courseGroup?.hour)?.until,
            )

            return [from, until].filter(Boolean).join(' - ')
        },
        resultMoreCourseGroupWeekMarker(courseGroup) {
            if (courseGroup?.recurrence_label) {
                return `(${courseGroup.recurrence_label})`
            }

            const recurrenceInterval = Number(courseGroup?.recurrence_interval || 0)
            if (recurrenceInterval > 1) {
                return `(${recurrenceInterval}-w)`
            }

            if (courseGroup?.block_label) {
                return courseGroup.block_label
            }

            return ''
        },
        resultMoreCourseGroupsScheduledWeeklyLoad(courseGroups) {
            return (Array.isArray(courseGroups) ? courseGroups : [])
                .filter(courseGroup => !this.resultMoreCourseGroupIsOccasional(courseGroup))
                .reduce((load, courseGroup) => load + (1 / (this.courseGroupWeekInterval(courseGroup) || 1)), 0)
        },
        resultMoreCourseGroupIsOccasional(courseGroup) {
            const recurrenceType = String(courseGroup?.recurrence_type || '').toLowerCase()

            return recurrenceType === 'single'
                || recurrenceType === 'block'
                || Number(courseGroup?.dates_count || 0) === 1
        },
        resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys() {
            const deselectedCourseGroupKeys = new Set(this.currentResultMoreDeselectedOfferedCourseGroupKeys())

            return Object.fromEntries(this.resultMoreCourseItems
                .flatMap(course => this.resultMoreOfferedCourseItemsForSelectedCourse(course))
                .filter(offeredCourse => deselectedCourseGroupKeys.has(offeredCourse.backendSelectionKey))
                .map(offeredCourse => [offeredCourse.selectionKey, false]))
        },
        currentResultMoreDeselectedOfferedCourseGroupKeys() {
            const additionalOfferedCourseGroupKeys = new Set(this.resultMoreAllOfferedCourseBackendKeys())

            return this.deselectedCourseGroupKeys
                .filter(courseGroupKey => additionalOfferedCourseGroupKeys.has(courseGroupKey))
                .sort((firstKey, secondKey) => firstKey.localeCompare(secondKey, 'de-AT'))
        },
        resultMoreDeselectedOfferedCourseGroupKeys() {
            return this.resultMoreCourseItems
                .filter(course => this.resultDraftAdditionalCourseSelected(course))
                .flatMap(course => this.resultMoreOfferedCourseItemsForSelectedCourse(course))
                .filter(offeredCourse => !this.resultMoreOfferedCourseSelected(offeredCourse))
                .map(offeredCourse => offeredCourse.backendSelectionKey)
                .filter(Boolean)
                .filter((courseGroupKey, index, courseGroupKeys) => courseGroupKeys.indexOf(courseGroupKey) === index)
                .sort((firstKey, secondKey) => firstKey.localeCompare(secondKey, 'de-AT'))
        },
        resultMoreAllOfferedCourseBackendKeys() {
            return this.resultMoreCourseItems
                .flatMap(course => this.resultMoreOfferedCourseItemsForSelectedCourse(course))
                .map(offeredCourse => offeredCourse.backendSelectionKey)
                .filter(Boolean)
                .filter((courseGroupKey, index, courseGroupKeys) => courseGroupKeys.indexOf(courseGroupKey) === index)
        },
        resultMoreDeselectedCourseGroupKeysForApply() {
            const additionalOfferedCourseGroupKeys = new Set(this.resultMoreAllOfferedCourseBackendKeys())
            const preservedCourseGroupKeys = this.deselectedCourseGroupKeys
                .filter(courseGroupKey => !additionalOfferedCourseGroupKeys.has(courseGroupKey))

            return [
                ...preservedCourseGroupKeys,
                ...this.resultMoreDeselectedOfferedCourseGroupKeys(),
            ]
                .filter(Boolean)
                .filter((courseGroupKey, index, courseGroupKeys) => courseGroupKeys.indexOf(courseGroupKey) === index)
        },
        async applyResultMoreCourses() {
            if (!this.resultAdditionalCoursesChanged || this.generatingTimetable) {
                return
            }

            this.selectedAdditionalCourseKeys = this.normalizedCourseKeys(this.resultDraftAdditionalCourseKeys)
                .filter(courseKey => {
                    const course = this.resultMoreCourseItems.find(courseItem => courseItem.selectionKey === courseKey)

                    return !course || this.resultMoreOfferedCourseItemsAnySelected(course)
                })
            this.deselectedCourseGroupKeys = this.resultMoreDeselectedCourseGroupKeysForApply()
            this.resultMoreCoursesVisible = false
            this.selectedResultMoreCourseKey = ''
            this.resultDraftAdditionalCourseKeys = this.normalizedCourseKeys(this.selectedAdditionalCourseKeys)
            this.resultMoreOfferedCourseSelectionOverrides = this.resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys()
            this.additionalCourseSelectionChangedAfterTimetable = false
            this.additionalCourseSelectionLocked = false
            this.resetGeneratedTimetableSelection()
            await this.createAutomaticTimetable()
        },
        toggleResultOptions() {
            if (this.generatingTimetable || this.resultOptionsUnavailable) {
                return
            }

            if (this.resultOptionsVisible) {
                this.closeResultOptions()

                return
            }

            this.resultDraftQualityCriterionKeys = this.normalizedCourseKeys(this.selectedQualityCriterionKeys)
            this.resultOptionsVisible = true
            this.resultMoreCoursesVisible = false
            this.selectedResultMoreCourseKey = ''
            this.resultMoreOfferedCourseSelectionOverrides = this.resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys()
        },
        closeResultOptions() {
            this.resultOptionsVisible = false
            this.resultDraftQualityCriterionKeys = this.normalizedCourseKeys(this.selectedQualityCriterionKeys)
        },
        resultDraftQualityCriterionSelected(criterion) {
            return this.resultDraftQualityCriterionKeys.includes(String(criterion?.key || ''))
        },
        setResultDraftQualityCriterionSelected(criterion, selected) {
            const criterionKey = String(criterion?.key || '')

            if (!criterionKey) {
                return
            }

            if (selected === true && !this.resultDraftQualityCriterionKeys.includes(criterionKey)) {
                this.resultDraftQualityCriterionKeys = [...this.resultDraftQualityCriterionKeys, criterionKey]

                return
            }

            if (selected !== true) {
                this.resultDraftQualityCriterionKeys = this.resultDraftQualityCriterionKeys
                    .filter(selectedCriterionKey => selectedCriterionKey !== criterionKey)
            }
        },
        async applyResultOptions() {
            if (!this.resultOptionsChanged || this.generatingTimetable) {
                return
            }

            this.selectedQualityCriterionKeys = this.normalizedCourseKeys(this.resultDraftQualityCriterionKeys)
            this.resultOptionsVisible = false
            this.resultDraftQualityCriterionKeys = this.normalizedCourseKeys(this.selectedQualityCriterionKeys)
            this.emitQualityCriteriaSelectionChange()
            this.resetGeneratedTimetableSelection()
            await this.createAutomaticTimetable()
        },
        async resetResultCalculationChanges() {
            if (this.generatingTimetable || !this.resultCalculationResetAvailable) {
                return
            }

            this.resultMoreCoursesVisible = false
            this.resultOptionsVisible = false
            this.pendingRemovedSelectedCourseKeys = []
            this.selectedCourseKeys = this.normalizedCourseKeys(this.resultInitialSelectedCourseKeys)
            this.selectedAdditionalCourseKeys = []
            this.selectedQualityCriterionKeys = this.normalizedCourseKeys(this.initialSelectedQualityCriterionKeys)
            this.resultDraftAdditionalCourseKeys = []
            this.resultDraftQualityCriterionKeys = this.normalizedCourseKeys(this.selectedQualityCriterionKeys)
            this.selectedResultMoreCourseKey = ''
            this.resultMoreOfferedCourseSelectionOverrides = {}
            this.deselectedCourseGroupKeys = this.normalizedCourseKeys(this.initialDeselectedCourseGroupKeys)
            this.additionalCourseSelectionChangedAfterTimetable = false
            this.additionalCourseSelectionLocked = false
            this.emitCourseSelectionChange()
            this.emitQualityCriteriaSelectionChange()
            this.resetGeneratedTimetableSelection()
            await this.createAutomaticTimetable()
        },
        normalizedCourseCode(value) {
            return String(value || '').trim().toUpperCase().replace(/\s+/g, '')
        },
        courseCodeAliases(course) {
            return [
                course?.code,
                course?.ttCode,
                ...(Array.isArray(course?.ttCodes) ? course.ttCodes : []),
            ]
                .flatMap(courseCode => String(courseCode || '').split('/'))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean)
                .filter((courseCode, index, courseCodes) => courseCodes.indexOf(courseCode) === index)
        },
        courseComparisonKeys(course) {
            return [
                this.courseSelectionKey(course),
                course?.key,
                course?.code,
                ...(Array.isArray(course?.alternativeLabels) ? course.alternativeLabels : []),
                ...(Array.isArray(course?.ttCodes) ? course.ttCodes : []),
                ...this.courseCodeAliases(course),
            ]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .map(value => this.normalizedCourseCode(value) || value)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        additionalCourseHiddenForSelectedTimetable(course) {
            const hiddenCourseKeys = this.hiddenGeneratedAdditionalCourseKeySet

            return hiddenCourseKeys.size > 0
                && this.courseComparisonKeys(course).some(courseKey => hiddenCourseKeys.has(courseKey))
        },
        hiddenGeneratedAdditionalCourses() {
            const missingAdditionalCourses = Array.isArray(this.generatedTimetable?.missingAdditionalCourses)
                ? this.generatedTimetable.missingAdditionalCourses
                : []

            return [
                ...missingAdditionalCourses,
                ...this.generatedAdditionalConflictCourses(),
            ]
        },
        generatedAdditionalConflictCourses() {
            return Object.values(this.generatedTimetable?.slots || {})
                .flatMap(slot => this.generatedSlotVisualConflictBlocks(slot))
                .filter(block => block?.isAdditionalCourse === true)
        },

        courseSelected(course) {
            if (typeof this.courseSelectedFromKeys !== 'function') {
                const backendCourseKey = typeof this.backendCourseSelectionKey === 'function'
                    ? this.backendCourseSelectionKey(course)
                    : String(course?.key || '')

                return this.selectedCourseKeys.includes(this.courseSelectionKey(course))
                    || (backendCourseKey && this.selectedCourseKeys.includes(backendCourseKey))
            }

            return this.courseSelectedFromKeys(course, this.selectedCourseKeys)
        },
        courseSelectedByDefault(course) {
            return this.allCoursesSelectedByDefault || this.courseSelected(course)
        },

        setCourseSelected(course, selected) {
            if (this.currentStep === 'result') {
                return
            }

            const courseKey = this.courseSelectionKey(course)

            if (selected === true && !this.courseSelected(course)) {
                this.selectedCourseKeys = [...this.selectedCourseKeys, courseKey]

                return
            }

            if (selected !== true) {
                const selectedCourseKeys = this.selectedCourseKeys.length
                    ? this.selectedCourseKeys
                    : this.proposedCourses.map(proposedCourse => this.courseSelectionKey(proposedCourse))

                this.selectedCourseKeys = selectedCourseKeys
                    .filter(selectedCourseKey => typeof this.selectionKeyMatchesCourse === 'function'
                        ? !this.selectionKeyMatchesCourse(selectedCourseKey, course)
                        : selectedCourseKey !== courseKey)
            }

            this.emitCourseSelectionChange()
        },
        additionalCourseSelected(course) {
            if (typeof this.courseSelectedFromKeys !== 'function') {
                const backendCourseKey = typeof this.backendCourseSelectionKey === 'function'
                    ? this.backendCourseSelectionKey(course)
                    : String(course?.key || '')

                return this.selectedAdditionalCourseKeys.includes(this.courseSelectionKey(course))
                    || (backendCourseKey && this.selectedAdditionalCourseKeys.includes(backendCourseKey))
            }

            return this.courseSelectedFromKeys(course, this.selectedAdditionalCourseKeys)
        },
        setAdditionalCourseSelected(course, selected) {
            if (this.currentStep !== 'result' || !this.generatedTimetable || this.additionalCourseSelectionLocked) {
                return
            }

            const courseKey = this.courseSelectionKey(course)

            const additionalCourseSelected = typeof this.additionalCourseSelected === 'function'
                ? this.additionalCourseSelected(course)
                : this.selectedAdditionalCourseKeys.includes(courseKey)

            if (selected === true && !additionalCourseSelected) {
                this.selectedAdditionalCourseKeys = [...this.selectedAdditionalCourseKeys, courseKey]
            }

            if (selected !== true) {
                this.selectedAdditionalCourseKeys = this.selectedAdditionalCourseKeys
                    .filter(selectedCourseKey => typeof this.selectionKeyMatchesCourse === 'function'
                        ? !this.selectionKeyMatchesCourse(selectedCourseKey, course)
                        : selectedCourseKey !== courseKey)
            }

            this.commitAdditionalCourseSelectionChange()
        },
        commitAdditionalCourseSelectionChange() {
            this.additionalCourseSelectionChangedAfterTimetable = this.selectedAdditionalCourseKeys.length > 0
        },
        resetAdditionalCourseSelection() {
            this.selectedAdditionalCourseKeys = []
            this.additionalCourseSelectionChangedAfterTimetable = false
            this.additionalCourseSelectionLocked = false
        },
        additionalCourseSectionSelectedCount(section) {
            const courses = Array.isArray(section?.items) ? section.items : []

            return courses.filter(course => this.additionalCourseSelected(course)).length
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
        courseItemsSummary(courses) {
            const courseItems = Array.isArray(courses) ? courses : []
            const hours = courseItems.reduce((sum, course) => sum + this.courseHoursNumber(course), 0)
            const numberFormatter = new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 })

            return {
                count: courseItems.length,
                hours,
                countLabel: `${numberFormatter.format(courseItems.length)} ${courseItems.length === 1 ? 'Kurs' : 'Kurse'}`,
                hoursLabel: `${numberFormatter.format(hours)} Std.`,
            }
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
            if (course?.hours_value) {
                return course.hours_value
            }

            const hours = this.courseHoursNumber(course)

            if (!hours) {
                return '-'
            }

            return new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 }).format(hours)
        },
        courseBranchLabel(course) {
            if (course?.branch_label) {
                return course.branch_label
            }

            const branch = String(course?.branch || '').trim()

            return !branch || branch === 'common' ? 'alle' : branch
        },

        courseHoursLabel(course) {
            if (course?.hours_label) {
                return course.hours_label
            }

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
                    selected_course_keys: this.selectedBackendCourseKeys(),
                    deselected_course_group_keys: this.deselectedCourseGroupKeys,
                    selected_additional_course_keys: this.selectedVisibleAdditionalCourseKeys,
                    selected_additional_courses_required: this.selectedVisibleAdditionalCourseKeys.length > 0,
                    selected_quality_criterion_keys: [],
                    selected_timetable_type: this.selectedTimetableType,
                    selected_timetable_number: this.selectedTimetableNumber,
                    selection: this.selectionOverride,
                })

                this.generatedTimetable = response.data?.data?.selected_timetable || null
                this.schoolHours = response.data?.data?.school_hours || []
                this.timetableCounts = response.data?.data || {}
                this.selectedTimetableType = this.generatedTimetable?.type || this.selectedTimetableType
                this.selectedTimetableNumber = this.normalizedTimetableNumber(this.generatedTimetable?.number)
                this.removeHiddenAdditionalCourseSelections()
                this.resultDraftAdditionalCourseKeys = this.normalizedCourseKeys(this.selectedAdditionalCourseKeys)
                this.resultDraftQualityCriterionKeys = this.normalizedCourseKeys(this.selectedQualityCriterionKeys)
                this.selectedResultMoreCourseKey = ''
                this.resultMoreOfferedCourseSelectionOverrides = this.resultMoreOfferedCourseSelectionOverridesFromDeselectedKeys()
                this.additionalCourseSelectionChangedAfterTimetable = false
                this.moveToStep('result')
            } catch (error) {
                this.timetableError = error?.response?.data?.message || 'Der Stundenplan konnte nicht erstellt werden.'
                this.moveToStep('result')
            } finally {
                this.generatingTimetable = false
            }
        },

        async createExtendedAutomaticTimetable() {
            if (!this.selectedAdditionalCourseKeys.length) {
                this.additionalCourseSelectionChangedAfterTimetable = false
                this.additionalCourseSelectionLocked = false

                return
            }

            this.additionalCourseSelectionLocked = true
            this.resetGeneratedTimetableSelection()
            await this.createAutomaticTimetable()

            if (!this.generatedTimetable) {
                this.additionalCourseSelectionLocked = false
                this.commitAdditionalCourseSelectionChange()
            }
        },

        removeHiddenAdditionalCourseSelections() {
            if (!this.selectedAdditionalCourseKeys.length) {
                return
            }

            const visibleAdditionalCourseKeys = new Set(this.additionalCourses.map(course => this.courseSelectionKey(course)))

            this.selectedAdditionalCourseKeys = this.selectedAdditionalCourseKeys
                .filter(courseKey => visibleAdditionalCourseKeys.has(courseKey))
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

        generatedTimetableSchoolHour(hour) {
            return Object.values(this.generatedTimetable?.slots || {})
                .map(slot => slot?.courseGroup || {})
                .find(courseGroup => Number(courseGroup?.hour) === Number(hour)) || {}
        },

        generatedTimetableConfiguredSchoolHour(hour) {
            const schoolHours = Array.isArray(this.schoolHours) ? this.schoolHours : []

            return schoolHours.find(schoolHour => Number(schoolHour?.hour) === Number(hour)) || {}
        },

        generatedTimetableHourTimeFrom(courseGroup, hour = null) {
            return this.formatTimeValue(
                courseGroup?.time_from
                || courseGroup?.from
                || courseGroup?.timeFrom
                || this.generatedTimetableConfiguredSchoolHour(hour ?? courseGroup?.hour)?.from,
            )
        },

        generatedTimetableHourTimeUntil(courseGroup, hour = null) {
            return this.formatTimeValue(
                courseGroup?.time_until
                || courseGroup?.until
                || courseGroup?.timeUntil
                || this.generatedTimetableConfiguredSchoolHour(hour ?? courseGroup?.hour)?.until,
            )
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
            const nextTimetableNumber = this.selectedTimetableNumber + direction

            return this.generatedTimetable
                && this.generatedTimetableTotalCount > 0
                && nextTimetableNumber >= 1
                && nextTimetableNumber <= this.generatedTimetableTotalCount
        },

        async moveGeneratedTimetable(direction) {
            if (!this.canMoveGeneratedTimetable(direction)) {
                return
            }

            this.selectedTimetableType = this.generatedTimetableNavigationType
            this.selectedTimetableNumber += direction
            await this.createAutomaticTimetable()
        },

        async commitGeneratedTimetableNumber(value) {
            const timetableNumber = Number(value)

            if (!Number.isFinite(timetableNumber)) {
                return
            }

            const nextTimetableNumber = Math.min(
                this.generatedTimetableNumberLimit,
                Math.max(1, Math.round(timetableNumber)),
            )

            if (nextTimetableNumber === this.selectedTimetableNumber) {
                return
            }

            this.selectedTimetableType = this.generatedTimetableNavigationType
            this.selectedTimetableNumber = nextTimetableNumber
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

        selectedQualityCriteriaTimetableCount() {
            if (!(this.selectedQualityCriterionKeys || []).length) {
                return 0
            }

            if (Object.prototype.hasOwnProperty.call(this.timetableCounts || {}, 'selected_quality_criteria_count')) {
                return Math.max(0, Number(this.timetableCounts.selected_quality_criteria_count || 0))
            }

            return this.activeGeneratedQualityCriteria
                .filter(criterion => this.generatedQualityCriterionSelected(criterion))
                .reduce((minimumCount, criterion) => {
                    const criterionCount = Math.max(0, Number(criterion?.count || 0))

                    return minimumCount === null ? criterionCount : Math.min(minimumCount, criterionCount)
                }, null) ?? 0
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
            if (Object.prototype.hasOwnProperty.call(criterion || {}, 'selected_reached')) {
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

        generatedAdditionalCoursesAccepted() {
            return this.generatedTimetable?.additionalCoursesAccepted === true
        },

        generatedMissingAdditionalCourses() {
            return Array.isArray(this.generatedTimetable?.missingAdditionalCourses)
                ? this.generatedTimetable.missingAdditionalCourses
                : []
        },

        generatedAcceptedAdditionalCourseCount() {
            if (!this.selectedAdditionalCourseKeys.length) {
                return 0
            }

            if (this.generatedAdditionalCoursesAccepted()) {
                return this.selectedAdditionalCourseKeys.length
            }

            const acceptedAdditionalCourseCount = Number(this.generatedTimetable?.acceptedAdditionalCourseCount)
            if (Number.isFinite(acceptedAdditionalCourseCount) && acceptedAdditionalCourseCount > 0) {
                return acceptedAdditionalCourseCount
            }

            return Math.max(0, this.selectedAdditionalCourseKeys.length - this.generatedMissingAdditionalCourses().length)
        },

        generatedAdditionalCourseAcceptanceLabel() {
            if (!this.selectedAdditionalCourseKeys.length) {
                return '-'
            }

            return `${this.generatedAcceptedAdditionalCourseCount()} / ${this.selectedAdditionalCourseKeys.length}`
        },

        generatedAdditionalCourseAcceptanceIcon() {
            if (this.generatedAdditionalCoursesAccepted()) {
                return 'mdi-check-circle'
            }

            if (this.generatedMissingAdditionalCourses().length) {
                return 'mdi-alert-circle'
            }

            return 'mdi-close-circle'
        },

        generatedAdditionalCourseAcceptanceColor() {
            return this.generatedAdditionalCoursesAccepted() ? 'success' : 'error'
        },

        syncSelectedQualityCriterionKeys() {
            const activeCriterionKeys = this.activeQualityCriterionKeys()

            this.selectedQualityCriterionKeys = this.selectedQualityCriterionKeys
                .filter(criterionKey => activeCriterionKeys.includes(criterionKey))
            this.resultDraftQualityCriterionKeys = this.normalizedCourseKeys(this.selectedQualityCriterionKeys)

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

            return this.generatedSlotConflictBlocksIncludingRegular(slot)
                .find(block => !this.generatedSlotBlockIsOccasional(block))
                || slot
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

        generatedTimetableConflictPairIsOccasional(pair) {
            return this.generatedSlotBlockIsOccasional(pair?.slot)
                || this.generatedSlotBlockIsOccasional(pair?.conflict)
        },

        generatedTimetableConflictSummaryLabel(slot, conflict) {
            const oneDayCourseFirst = this.generatedSlotBlockIsOccasional(conflict)
                && !this.generatedSlotBlockIsOccasional(slot)
            const firstItem = oneDayCourseFirst ? conflict : slot
            const secondItem = oneDayCourseFirst ? slot : conflict
            const firstTitle = this.generatedTimetableConflictCourseLabel(firstItem)
            const secondTitle = this.generatedTimetableConflictLabel(secondItem, firstItem)

            return firstTitle && secondTitle
                ? `${firstTitle} überschneidet sich mit ${secondTitle}.`
                : secondTitle
        },

        generatedTimetableConflictLabel(conflict, slot = null) {
            return [
                this.generatedTimetableConflictCourseLabel(conflict),
                this.generatedTimetableSlotRecurrenceLabel(conflict),
                this.generatedTimetableConflictDateLabel(slot, conflict),
            ].filter(Boolean).join(' ')
        },

        generatedTimetableConflictCourseLabel(item) {
            const sourceLabel = String(item?.sourceLabel || '').trim()

            if (sourceLabel) {
                return sourceLabel
            }

            return [
                this.generatedSlotTitle(item),
                String(item?.name || '').trim(),
            ]
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
                .join(' ')
        },

        generatedTimetableSlotRecurrenceLabel(slot) {
            const interval = this.courseGroupWeekInterval(slot?.courseGroup || slot)

            if (Number.isInteger(interval) && interval > 1) {
                return `${interval}-wöchig`
            }

            const recurrenceLabel = String(
                slot?.courseGroup?.recurrenceLabel
                || slot?.courseGroup?.recurrence_label
                || slot?.recurrenceLabel
                || slot?.recurrence_label
                || '',
            ).trim()

            if (!recurrenceLabel || /^w[öo]chentlich$/iu.test(recurrenceLabel) || /^1\s*-\s*w[öo]chig$/iu.test(recurrenceLabel)) {
                return ''
            }

            return recurrenceLabel
        },

        generatedTimetableConflictDateLabel(slot, conflict) {
            const dateLabels = this.generatedTimetableConflictDateLabels(slot, conflict)

            return dateLabels.length ? `(${dateLabels.join(', ')})` : ''
        },

        generatedTimetableConflictDateLabels(slot, conflict) {
            const slotDates = this.courseGroupDates(slot?.courseGroup)
            const conflictDates = this.courseGroupDates(conflict?.courseGroup)

            if (!this.generatedSlotBlockIsOccasional(slot) && !this.generatedSlotBlockIsOccasional(conflict)) {
                return []
            }

            if (slotDates.length && conflictDates.length) {
                const conflictDateSet = new Set(conflictDates)
                const sharedDateLabels = slotDates
                    .filter(date => conflictDateSet.has(date))
                    .sort()
                    .map(date => this.formatShortDateValue(date))
                    .filter(Boolean)
                    .filter((date, index, dates) => dates.indexOf(date) === index)

                if (sharedDateLabels.length) {
                    return sharedDateLabels
                }
            }

            if (this.generatedSlotBlockIsOccasional(slot)) {
                return slotDates
                    .map(date => this.formatShortDateValue(date))
                    .filter(Boolean)
            }

            return conflictDates
                .map(date => this.formatShortDateValue(date))
                .filter(Boolean)
        },

        generatedTimetableCellHasAdditionalCourse(weekday, hour) {
            return this.generatedSlotDisplayBlocks(this.generatedTimetableDisplaySlot(weekday, hour))
                .some(block => block.isAdditionalCourse === true)
                || this.generatedTimetableOccasionalMarkers(weekday, hour)
                    .some(marker => marker.isAdditionalCourse === true)
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

        generatedSlotWeekMarker(slot) {
            return this.visibleTimetableEntryWeekMarker(
                this.generatedTimetable,
                slot,
                this.courseGroupWeekMarkerValue(slot?.courseGroup),
            )
        },

        generatedAppointmentWeekMarker(appointment) {
            return this.visibleTimetableEntryWeekMarker(
                this.generatedTimetable,
                appointment,
                this.courseGroupWeekMarkerValue(appointment?.courseGroup || appointment),
            )
        },

        visibleTimetableEntryWeekMarker(timetable, entry, weekMarker) {
            const marker = String(weekMarker || '').trim()

            if (!marker) return ''
            if (marker !== '1-w') return marker

            return this.timetableEntryWeekMarkers(timetable, entry)
                .some(entryMarker => entryMarker && entryMarker !== '1-w')
                ? marker
                : ''
        },

        timetableEntryWeekMarkers(timetable, entry) {
            return this.timetableWeekMarkerEntries(timetable)
                .filter(timetableEntry => this.timetableEntriesShareWeekMarkerContext(timetableEntry, entry))
                .map(timetableEntry => this.courseGroupWeekMarkerValue(timetableEntry?.courseGroup || timetableEntry))
                .filter(Boolean)
                .filter((marker, index, markers) => markers.indexOf(marker) === index)
        },

        timetableWeekMarkerEntries(timetable) {
            const slots = Object.values(timetable?.slots || {})

            return [
                ...slots,
                ...slots.flatMap(slot => Array.isArray(slot?.conflicts) ? slot.conflicts : []),
                ...(Array.isArray(timetable?.occasionalAppointments) ? timetable.occasionalAppointments : []),
            ]
        },

        timetableEntriesShareWeekMarkerContext(firstEntry, secondEntry) {
            const firstCourseKey = this.timetableEntryCourseContextKey(firstEntry)
            const secondCourseKey = this.timetableEntryCourseContextKey(secondEntry)

            if (!firstCourseKey || !secondCourseKey || firstCourseKey !== secondCourseKey) return false

            const firstSourceLabel = String(firstEntry?.sourceLabel || '').trim()
            const secondSourceLabel = String(secondEntry?.sourceLabel || '').trim()

            return firstSourceLabel && secondSourceLabel
                ? firstSourceLabel === secondSourceLabel
                : true
        },

        timetableEntryCourseContextKey(entry) {
            return String(entry?.courseKey || entry?.code || entry?.key || '').trim()
        },

        courseGroupWeekMarkerValue(courseGroup) {
            const interval = this.courseGroupWeekInterval(courseGroup)

            return interval ? `${interval}-w` : ''
        },

        courseGroupWeekInterval(courseGroup) {
            const explicitInterval = Number(courseGroup?.recurrence_interval)
            if (Number.isInteger(explicitInterval) && explicitInterval > 0) return explicitInterval

            const labelMatch = String(courseGroup?.recurrence_label || '').match(/(\d+)\s*-\s*w/iu)
            if (labelMatch) return Number(labelMatch[1])

            return this.weekIntervalFromDates(this.courseGroupDates(courseGroup))
        },

        weekIntervalFromDates(dates) {
            if (!Array.isArray(dates) || dates.length < 2) return null

            const parsedDates = dates
                .map(date => this.dateFromIsoValue(date))
                .filter(Boolean)
                .sort((firstDate, secondDate) => firstDate.getTime() - secondDate.getTime())

            if (parsedDates.length < 2) return null

            const weekGaps = parsedDates
                .slice(1)
                .map((date, index) => Math.round((date.getTime() - parsedDates[index].getTime()) / (7 * 24 * 60 * 60 * 1000)))
                .filter(gap => gap > 0)

            const uniqueGaps = weekGaps.filter((gap, index, gaps) => gaps.indexOf(gap) === index)

            return uniqueGaps.length === 1 ? uniqueGaps[0] : null
        },

        dateFromIsoValue(value) {
            const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/u)
            if (!match) return null

            const [, year, month, day] = match

            return new Date(Number(year), Number(month) - 1, Number(day))
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
                        sourceLabel: this.generatedOccasionalMarkerSourceLabel(appointment),
                        date: appointment?.date || this.courseGroupDates(appointment?.courseGroup)[0] || '',
                        isAdditionalCourse: appointment?.isAdditionalCourse === true,
                        isDistanceLearningCourse: appointment?.isDistanceLearningCourse === true,
                        weekMarker: this.generatedAppointmentWeekMarker(appointment),
                    }))
                : []

            const markers = this.uniqueGeneratedTimetableOccasionalMarkers([
                ...appointmentMarkers,
                ...this.generatedSlotOccasionalConflictMarkers(slot),
            ])

            return markers.filter(marker => !this.generatedOccasionalMarkerMatchesDisplayedSlot(marker, weekday, hour))
        },

        generatedOccasionalMarkerMatchesDisplayedSlot(marker, weekday, hour) {
            const displayedSlot = this.generatedTimetableDisplaySlot(weekday, hour)

            if (!displayedSlot || !this.generatedSlotBlockIsOccasional(displayedSlot)) {
                return false
            }

            return this.generatedOccasionalMarkerIdentity(marker) === this.generatedOccasionalMarkerIdentity({
                code: displayedSlot?.code || '',
                sourceLabel: this.generatedOccasionalMarkerSourceLabel(displayedSlot),
                date: this.courseGroupDates(displayedSlot?.courseGroup)[0] || '',
            })
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
                    sourceLabel: this.generatedOccasionalMarkerSourceLabel(block),
                    date: this.courseGroupDates(block.courseGroup)[0] || '',
                    isAdditionalCourse: block.isAdditionalCourse === true,
                    isDistanceLearningCourse: block.isDistanceLearningCourse === true,
                    weekMarker: this.generatedSlotWeekMarker(block),
                }))
        },

        uniqueGeneratedTimetableOccasionalMarkers(markers) {
            const uniqueMarkers = new Map()

            markers.forEach(marker => {
                const identity = this.generatedOccasionalMarkerIdentity(marker)

                if (!identity || uniqueMarkers.has(identity)) {
                    return
                }

                uniqueMarkers.set(identity, marker)
            })

            return Array.from(uniqueMarkers.values())
        },

        generatedOccasionalMarkerIdentity(marker) {
            return [
                marker?.code,
                this.generatedOccasionalMarkerSourceLabel(marker),
                marker?.date,
            ]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join('|')
        },

        courseGroupDates(courseGroup) {
            return Array.isArray(courseGroup?.dates)
                ? courseGroup.dates
                    .map(date => String(date || '').trim())
                    .filter(Boolean)
                : []
        },

        formatTimeValue(value) {
            const rawValue = String(value || '').trim()

            if (!rawValue) return ''

            return rawValue.slice(0, 5)
        },

        generatedOccasionalMarkerLabel(marker) {
            const code = this.generatedOccasionalMarkerSourceLabel(marker)
            const dateLabel = this.formatShortDateValue(marker?.date || this.courseGroupDates(marker?.courseGroup)[0])

            return [code, dateLabel].filter(Boolean).join(' ')
        },

        generatedOccasionalMarkerSourceLabel(source) {
            const courseGroup = source?.courseGroup || {}

            return this.normalizedGeneratedOccasionalMarkerLabel(
                source?.sourceLabel
                || courseGroup?.display_label
                || courseGroup?.title
                || source?.code
                || source?.courseKey,
            )
        },

        normalizedGeneratedOccasionalMarkerLabel(value) {
            return String(value || '').trim().replace(/\s*-\s*/gu, '-')
        },

        formatShortDateValue(value) {
            const date = this.dateFromIsoValue(value)

            if (!date) {
                return ''
            }

            return `${String(date.getDate()).padStart(2, '0')}.${String(date.getMonth() + 1).padStart(2, '0')}.`
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
            if (this.generatedSlotBlockIsOccasional(slot)) {
                return this.generatedOccasionalMarkerSourceLabel(slot)
            }

            return String(slot?.code || slot?.courseGroup?.display_label || slot?.sourceLabel || '-').trim()
        },

        generatedSlotDateLabel(slot) {
            if (!this.generatedSlotBlockIsOccasional(slot)) {
                return ''
            }

            return this.formatShortDateValue(this.courseGroupDates(slot?.courseGroup)[0])
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

            if (nextStep === 'result') {
                this.resultBackStep = this.normalizedResultBackStep(this.currentStep)
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

        normalizedResultBackStep(step) {
            return step === 'courses' ? 'courses' : 'criteria'
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
        normalizedCourseKeyListsEqual(firstKeys, secondKeys) {
            const firstNormalizedKeys = this.normalizedCourseKeys(firstKeys).sort()
            const secondNormalizedKeys = this.normalizedCourseKeys(secondKeys).sort()

            return JSON.stringify(firstNormalizedKeys) === JSON.stringify(secondNormalizedKeys)
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

.student-evaluation-settings__automatic-card {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    max-width: 100%;
    padding: 12px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
    box-shadow: none;
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
    color: rgb(var(--v-theme-primary));
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

.student-evaluation-settings__course-section-grid--readonly {
    padding: 8px 0 0;
}

.student-evaluation-settings__course-section-card {
    min-width: 0;
    border: 1px solid rgba(16, 38, 58, 0.14);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.82);
    overflow: hidden;
}

.student-evaluation-settings__course-section-card--additional {
    border-color: rgba(234, 88, 12, 0.28);
    background: #fed7aa;
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

.student-evaluation-settings__course-table-row--readonly {
    background: rgba(248, 250, 252, 0.64);
}

.student-evaluation-settings__course-table-row--selected {
    background: rgba(220, 252, 231, 0.7);
}

.student-evaluation-settings__course-table-row--readonly :deep(.v-selection-control) {
    pointer-events: none;
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

.student-selected-courses-card {
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: none;
}

.student-selected-courses-card__title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    color: #0f172a;
    font-weight: 900;
}

.student-selected-courses-card__summary {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-left: auto;
}

.student-selected-courses-card__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.student-selected-courses-card__course {
    cursor: default;
}

.student-selected-courses-card__course--active {
    box-shadow: 0 0 0 1px rgba(0, 137, 123, 0.26);
}

.student-selected-courses-card__course--offered-partial {
    border: 1px solid rgba(217, 119, 6, 0.36);
    background: rgba(255, 251, 235, 0.96) !important;
    color: #92400e !important;
}

.student-selected-courses-card__course--offered-deselected {
    border: 1px solid rgba(220, 38, 38, 0.42);
    background: rgba(254, 226, 226, 0.96) !important;
    color: #991b1b !important;
}

.student-selected-courses-card__meta {
    margin-left: 6px;
    font-weight: 800;
}

.student-selected-courses-card__actions {
    flex-wrap: wrap;
    gap: 8px;
    padding: 0 16px 16px;
}

.student-selected-courses-card__pending-copy {
    color: rgba(15, 23, 42, 0.74);
    font-size: 0.88rem;
    font-weight: 800;
}

.student-generated-timetable__toolbar {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-left: auto;
}

.student-generated-timetable__primary-actions {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.student-result-options-card {
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(248, 251, 255, 0.92);
    box-shadow: none;
}

.student-result-options-card__title {
    color: #0f172a;
    font-weight: 900;
}

.student-result-options-card__list {
    display: grid;
    gap: 8px;
}

.student-result-options-card__item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.88);
    color: #172d40;
    cursor: pointer;
}

.student-result-options-card__item--selected {
    border-color: rgba(0, 137, 123, 0.24);
    background: rgba(224, 242, 241, 0.92);
}

.student-result-options-card__content {
    display: grid;
    gap: 3px;
    min-width: 0;
    font-size: 0.88rem;
}

.student-result-options-card__content > span:not(.student-result-options-card__meta) {
    color: rgba(23, 45, 64, 0.72);
}

.student-result-options-card__meta {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.student-result-options-card__actions {
    flex-wrap: wrap;
    gap: 8px;
}

.student-result-more-courses-card,
.student-result-more-courses-actions-card {
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(248, 251, 255, 0.92);
    box-shadow: none;
}

.student-result-more-courses-card__title {
    color: #0f172a;
    font-weight: 900;
}

.student-result-more-courses-card__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.student-result-more-courses-card__course {
    max-width: 100%;
    cursor: pointer;
}

.student-result-more-courses-card__meta,
.student-result-more-courses-card__group {
    margin-left: 6px;
    font-weight: 800;
}

.student-result-more-courses-card__group {
    opacity: 0.72;
}

.student-result-more-courses-offered-card {
    border: 1px solid rgba(37, 99, 235, 0.14);
    background: rgba(248, 251, 255, 0.92);
    box-shadow: none;
}

.student-result-offered-courses-card__title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    color: #0f172a;
    font-weight: 900;
}

.student-result-offered-courses-card__actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
}

.student-result-offered-courses-card__list {
    display: grid;
    gap: 8px;
}

.student-result-offered-courses-card__item {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    border: 1px solid rgba(71, 85, 105, 0.2);
    border-radius: 8px;
    padding: 8px 10px;
    background: #ffffff;
}

.student-result-offered-courses-card__item--toggle {
    cursor: pointer;
}

.student-result-offered-courses-card__item--selected {
    border-color: rgba(22, 163, 74, 0.36);
    background: rgba(220, 252, 231, 0.9);
    box-shadow: inset 0 0 0 1px rgba(22, 163, 74, 0.12);
}

.student-result-offered-courses-card__item--deselected {
    border-color: rgba(220, 38, 38, 0.28);
    background: rgba(254, 242, 242, 0.9);
    color: #7f1d1d;
}

.student-result-offered-courses-card__item--deselected .student-result-offered-courses-card__name {
    color: #7f1d1d;
}

.student-result-offered-courses-card__name {
    color: #0f172a;
    font-weight: 800;
}

.student-result-offered-courses-card__code {
    color: rgba(15, 23, 42, 0.62);
    font-weight: 800;
}

.student-result-course-panels {
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 8px;
    background: rgba(248, 251, 255, 0.82);
    overflow: hidden;
}

.student-result-course-panel {
    background: transparent !important;
    box-shadow: none !important;
}

.student-result-course-panel__head {
    min-height: 46px;
    padding: 8px 12px;
}

.student-result-course-panel__title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
    color: #0f172a;
    font-size: 0.86rem;
    font-weight: 900;
}

.student-result-course-panel :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 12px 12px;
}

.student-generated-timetable__success-strip {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    border-radius: 4px;
    padding: 8px 12px;
    background: #dff3f1;
    color: #00877f;
}

.student-generated-timetable__success-message {
    color: #00877f;
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1.2;
}

.student-generated-timetable__calculation-alert {
    align-items: center;
}

.student-generated-timetable__summary,
.student-generated-timetable__selector {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.student-generated-timetable__summary {
    margin-left: 2px;
}

.student-generated-timetable__selector {
    gap: 6px;
    margin-left: auto;
}

.student-generated-timetable__selector :deep(.v-btn--icon) {
    width: 36px;
    height: 36px;
}

.student-generated-timetable__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 8px;
}

.student-generated-timetable__actions-spacer {
    flex: 1 1 auto;
}

.student-generated-conflicts {
    display: grid;
    gap: 6px;
    border-radius: 8px;
    padding: 9px 10px;
}

.student-generated-conflicts--error {
    border: 1px solid rgba(220, 38, 38, 0.18);
    background: rgba(254, 226, 226, 0.96);
    color: #7f1d1d;
}

.student-generated-conflicts--warning {
    border: 1px solid rgba(217, 119, 6, 0.22);
    background: rgba(254, 243, 199, 0.96);
    color: #78350f;
}

.student-generated-conflicts__title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    font-weight: 900;
}

.student-generated-conflicts__list {
    display: grid;
    gap: 4px;
    margin: 0;
    padding-left: 18px;
    font-size: 0.76rem;
    font-weight: 700;
    line-height: 1.3;
}

.student-generated-timetable__extension-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.student-generated-timetable__back-button {
    color: rgb(var(--v-theme-primary)) !important;
}

.student-generated-timetable__counter {
    min-width: 112px;
    color: #00877f;
    font-size: 0.92rem;
    font-weight: 800;
    text-align: center;
}

.student-generated-timetable__number-input {
    flex: 0 0 108px;
    width: 108px;
}

.student-generated-timetable__number-input :deep(.v-field) {
    min-height: 36px;
    border-radius: 4px;
}

.student-generated-timetable__number-input :deep(.v-field__field) {
    align-items: center;
    min-height: 36px;
}

.student-generated-timetable__number-input :deep(.v-field__input) {
    align-items: center;
    flex-wrap: nowrap;
    line-height: 1.1rem;
    min-height: 36px;
    padding-top: 0;
    padding-bottom: 0;
}

.student-generated-timetable__number-input :deep(.v-text-field__prefix) {
    align-items: center;
    line-height: 1.1rem;
    min-height: 36px;
    padding-inline-start: 10px;
    padding-top: 0;
    padding-bottom: 0;
}

.student-generated-timetable__number-input :deep(input) {
    text-align: center;
    font-size: 0.92rem;
    font-weight: 800;
    line-height: 1.1rem;
}

.student-generated-criteria {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
}

.student-generated-criteria__card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 66px;
    padding: 10px 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.86);
    color: #172d40;
    cursor: pointer;
    text-align: left;
    transition: border-color 140ms ease, background 140ms ease, box-shadow 140ms ease;
}

.student-generated-criteria__card[tabindex="-1"] {
    cursor: wait;
    opacity: 0.72;
}

.student-generated-criteria__card--static {
    cursor: default;
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
    border-color: rgba(16, 38, 58, 0.14);
}

.student-generated-criteria__card--additional {
    border-color: rgba(234, 88, 12, 0.28);
    background: #fff7ed;
    box-shadow: inset 3px 0 0 rgb(234, 88, 12);
}

.student-generated-criteria__card--additional.student-generated-criteria__card--reached {
    border-color: rgba(234, 88, 12, 0.42);
    background: #fff7ed;
}

.student-generated-criteria__card--additional.student-generated-criteria__card--missed {
    border-color: rgba(234, 88, 12, 0.32);
    background: #fff7ed;
}

.student-generated-criteria__controls {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 5px;
    margin-left: auto;
}

.student-generated-criteria__status {
    flex: 0 0 auto;
}

.student-generated-criteria__status--reached {
    color: rgb(var(--v-theme-success));
}

.student-generated-criteria__status--missed {
    color: rgb(var(--v-theme-error));
}

.student-generated-criteria__check {
    flex: 0 0 auto;
    margin: -4px -6px -6px 0;
}

.student-generated-criteria__content {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    flex: 1;
    gap: 6px;
    min-width: 0;
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

.student-generated-criteria__meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 24px;
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

.student-generated-timetable__cell--time {
    flex-direction: column;
    gap: 2px;
}

.student-generated-timetable__hour {
    font-weight: 900;
    line-height: 1;
}

.student-generated-timetable__time-range {
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.66rem;
    font-weight: 400;
    line-height: 1;
}

.student-generated-timetable__cell--filled {
    background: rgba(var(--v-theme-success), 0.09);
}

.student-generated-timetable__cell--conflict {
    background: rgba(var(--v-theme-error), 0.09);
}

.student-generated-timetable__cell--additional {
    background: #fed7aa;
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
    gap: 2px;
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
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 3px;
    color: rgb(var(--v-theme-success));
    font-size: 0.86rem;
    font-weight: 900;
}

.student-generated-timetable__badge {
    display: inline-flex;
    align-items: center;
    min-height: 14px;
    padding: 1px 4px;
    border-radius: 4px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 0.54rem;
    font-weight: 950;
    line-height: 1;
}

.student-generated-timetable__badge--week {
    background: #dcfce7;
    color: #166534;
}

.student-generated-timetable__details {
    white-space: pre-line;
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.76rem;
    line-height: 1.25;
}

.student-generated-timetable__date {
    color: rgba(23, 45, 64, 0.72);
    font-size: 0.76rem;
    font-weight: 400;
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

    .student-generated-timetable__success-strip,
    .student-generated-timetable__selector {
        justify-content: flex-start;
        flex-wrap: wrap;
        width: 100%;
    }

    .student-generated-timetable__actions {
        width: 100%;
        margin-left: 0;
    }

    .student-generated-timetable__toolbar,
    .student-generated-timetable__primary-actions {
        width: 100%;
    }

    .student-generated-timetable__toolbar {
        margin-left: 0;
    }

    .student-generated-timetable__toolbar .v-btn {
        flex: 1 1 170px;
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
