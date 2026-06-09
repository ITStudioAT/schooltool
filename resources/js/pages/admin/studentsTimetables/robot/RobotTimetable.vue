<template>
    <div
        v-if="embeddedCourseCardsOnly"
        class="robot-timetable-embedded-course-cards"
        :class="{
            'robot-timetable-embedded-course-cards--with-additional': additionalCoursePanelVisible,
            'robot-timetable-embedded-course-cards--locked': embeddedCourseSelectionLocked,
        }"
        @click="showCourseActionAfterCourseInteraction">
        <div class="robot-course-list robot-course-panel">
            <div class="robot-course-panel__title">
                <div class="robot-course-list__header robot-course-list__header--panel">
                    <div class="robot-course-list__title">{{ regularCourseListTitle }}</div>
                    <v-chip size="x-small" color="primary" variant="tonal">
                        {{ regularCourseCountLabel }}
                    </v-chip>
                    <v-chip size="x-small" color="secondary" variant="tonal">
                        {{ formatHours(selectedCoursesHours) }} Std.
                    </v-chip>
                    <v-btn
                        class="robot-course-list__reset"
                        size="small"
                        variant="text"
                        color="primary"
                        prepend-icon="mdi-restore"
                        :disabled="embeddedCourseSelectionLocked || !courseSelectionResettable"
                        @click="resetCourseSelection">
                        Zurücksetzen
                    </v-btn>
                    <v-btn
                        v-if="embeddedCourseSelectionLocked"
                        class="robot-course-list__toggle"
                        size="small"
                        variant="text"
                        color="primary"
                        :icon="embeddedCourseSelectionExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                        :title="embeddedCourseSelectionExpanded ? 'Kurse einklappen' : 'Kurse ausklappen'"
                        :aria-label="embeddedCourseSelectionExpanded ? 'Kurse einklappen' : 'Kurse ausklappen'"
                        @click.stop="toggleEmbeddedCourseSelection" />
                </div>
            </div>
            <div
                v-show="!embeddedCourseSelectionLocked || embeddedCourseSelectionExpanded"
                class="robot-course-panel__body">
                <v-alert v-if="!loading && !availableCourses.length" type="info" variant="tonal" class="mb-0">
                    Keine passenden Kurse gefunden.
                </v-alert>

                <v-alert v-else-if="!selectedCourses.length" type="warning" variant="tonal" class="mb-3">
                    Keine Kurse ausgewählt.
                </v-alert>

                <div v-if="availableCourses.length" class="robot-course-columns">
                    <div
                        v-for="(courseColumn, columnIndex) in regularCourseColumns"
                        :key="`embedded-course-column-${courseColumn.key || columnIndex}`"
                        class="robot-course-item-list">
                        <div v-if="courseColumn.title" class="robot-regular-course-column-title">
                            <span>{{ courseColumn.title }}</span>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ courseColumn.courses.length }}
                            </v-chip>
                        </div>
                        <div class="robot-course-item-header robot-course-item-header--expandable">
                            <div>Aktiv</div>
                            <div>Code</div>
                            <div>Bezeichnung</div>
                            <div>Zweig</div>
                            <div class="text-right">Std.</div>
                        </div>
                        <v-expansion-panels
                            v-model="courseItemPanels"
                            multiple
                            variant="accordion"
                            class="robot-course-item-panels">
                            <v-expansion-panel
                                v-for="course in courseColumn.courses"
                                :key="course.key"
                                :value="course.key"
                                class="robot-course-item-panel"
                                :class="{
                                    'robot-course-item-panel--disabled': !courseSelected(course),
                                    'robot-course-item-panel--no-timetable-hours': !courseSelectable(course),
                                    'robot-course-item-panel--used': courseUsedInSelectedTimetable(course),
                                    'robot-course-item-panel--conflict': courseOverlapsInSelectedTimetable(course) || courseAllGroupsNoLongerFitSelectedTimetable(course),
                                }">
                                <v-expansion-panel-title class="robot-course-item-panel__title">
                                    <div class="robot-course-item-row">
                                        <div class="robot-course-item-row__select">
                                            <v-checkbox
                                                :model-value="courseFullySelected(course)"
                                                :indeterminate="coursePartiallySelected(course)"
                                                :aria-label="`${course.code} auswählen`"
                                                density="compact"
                                                color="primary"
                                                :disabled="embeddedCourseSelectionLocked || !courseSelectable(course)"
                                                hide-details
                                                @click.stop="showCourseActionAfterCourseInteraction"
                                                @update:model-value="setCourseSelected(course, $event)" />
                                        </div>
                                        <div class="robot-course-item-row__code">
                                            <span>{{ course.code }}</span>
                                        </div>
                                        <div>{{ course.name }}</div>
                                        <div>{{ course.branch }}</div>
                                        <div class="text-right">{{ formatHours(course.hours) }}</div>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <div class="robot-course-item-details">
                                        <div class="robot-course-item-details__section">
                                            <div class="robot-course-item-details__title">Stundenplan</div>
                                            <div
                                                v-if="courseGroupItems(course).length"
                                                class="robot-course-item-detail-list">
                                                <div
                                                    v-for="group in courseGroupItems(course)"
                                                    :key="group.key"
                                                    class="robot-course-item-detail"
                                                    :class="{
                                                        'robot-course-item-detail--disabled': !courseGroupSelected(course, group),
                                                        'robot-course-item-detail--used': courseGroupUsedInSelectedTimetable(course, group),
                                                        'robot-course-item-detail--conflict': courseGroupNoLongerFitsSelectedTimetable(course, group),
                                                    }">
                                                    <div class="robot-course-item-detail__main">
                                                        <v-checkbox
                                                            :model-value="courseGroupSelected(course, group)"
                                                            :aria-label="`${group.title} auswählen`"
                                                            density="compact"
                                                            color="primary"
                                                            :disabled="embeddedCourseSelectionLocked"
                                                            hide-details
                                                            class="robot-course-item-detail__check"
                                                            @click.stop="showCourseActionAfterCourseInteraction"
                                                            @update:model-value="setCourseGroupSelected(course, group, $event)" />
                                                        <span>
                                                            {{ group.title }}<sup v-if="courseGroupDistanceLearning(course, group)" class="robot-course-fu">FU</sup>:
                                                        </span>
                                                    </div>
                                                    <div class="robot-course-item-detail__meta">
                                                        {{ group.meta }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="robot-course-item-details__empty">
                                                Keine TT-Stunden.
                                            </div>
                                        </div>
                                    </div>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="additionalCoursePanelVisible"
            class="robot-course-list robot-course-panel robot-course-panel--additional">
            <div class="robot-course-panel__title">
                <div class="robot-course-list__header robot-course-list__header--panel">
                    <div class="robot-course-list__title">Zusätzliche Kurse</div>
                    <v-chip size="x-small" color="primary" variant="tonal">
                        {{ studentAdditionalCourses.length }}
                    </v-chip>
                    <v-btn
                        class="robot-course-list__reset"
                        size="small"
                        variant="text"
                        color="primary"
                        prepend-icon="mdi-restore"
                        :disabled="additionalCourseSelectionLocked || !additionalCourseSelectionResettable"
                        @click="resetAdditionalCourseSelection">
                        Zurücksetzen
                    </v-btn>
                    <v-btn
                        v-if="embeddedCourseSelectionLocked"
                        class="robot-course-list__toggle"
                        size="small"
                        variant="text"
                        color="primary"
                        :icon="embeddedAdditionalCourseSelectionExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                        :title="embeddedAdditionalCourseSelectionExpanded ? 'Zusätzliche Kurse einklappen' : 'Zusätzliche Kurse ausklappen'"
                        :aria-label="embeddedAdditionalCourseSelectionExpanded ? 'Zusätzliche Kurse einklappen' : 'Zusätzliche Kurse ausklappen'"
                        @click.stop="toggleEmbeddedAdditionalCourseSelection" />
                </div>
            </div>
            <div
                v-show="!embeddedCourseSelectionLocked || embeddedAdditionalCourseSelectionExpanded"
                class="robot-course-panel__body">
                <div class="robot-course-columns">
                    <div
                        v-for="(courseColumn, columnIndex) in additionalCourseColumns"
                        :key="`embedded-additional-course-column-${columnIndex}`"
                        class="robot-course-item-list">
                        <div class="robot-course-item-header robot-course-item-header--expandable">
                            <div>Aktiv</div>
                            <div>Code</div>
                            <div>Bezeichnung</div>
                            <div>Zweig</div>
                            <div class="text-right">Std.</div>
                        </div>
                        <v-expansion-panels
                            multiple
                            variant="accordion"
                            class="robot-course-item-panels">
                            <v-expansion-panel
                                v-for="course in courseColumn"
                                :key="course.key"
                                :value="course.key"
                                class="robot-course-item-panel"
                                :class="{
                                    'robot-course-item-panel--disabled': !additionalCourseSelectable(course),
                                    'robot-course-item-panel--no-timetable-hours': !courseSelectable(course),
                                    'robot-course-item-panel--used': courseUsedInSelectedTimetable(course),
                                    'robot-course-item-panel--missing-additional': additionalCourseInteractionDisabled(course),
                                }">
                                <v-expansion-panel-title class="robot-course-item-panel__title">
                                    <div class="robot-course-item-row">
                                        <div class="robot-course-item-row__select">
                                            <v-checkbox
                                                :model-value="additionalCourseFullySelected(course)"
                                                :indeterminate="additionalCoursePartiallySelected(course)"
                                                :aria-label="`${course.code} auswählen`"
                                                density="compact"
                                                color="primary"
                                                :disabled="additionalCourseSelectionLocked || !additionalCourseSelectable(course) || additionalCourseInteractionDisabled(course)"
                                                hide-details
                                                @click.stop="showCourseActionAfterCourseInteraction"
                                                @update:model-value="setAdditionalCourseSelected(course, $event)" />
                                        </div>
                                        <div class="robot-course-item-row__code">
                                            <span>{{ course.code }}</span>
                                        </div>
                                        <div>{{ course.name }}</div>
                                        <div>{{ course.branch }}</div>
                                        <div class="text-right">{{ formatHours(course.hours) }}</div>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <div class="robot-course-item-details">
                                        <div class="robot-course-item-details__section">
                                            <div class="robot-course-item-details__title">Stundenplan</div>
                                            <div
                                                v-if="courseGroupItems(course).length"
                                                class="robot-course-item-detail-list">
                                                <div
                                                    v-for="group in courseGroupItems(course)"
                                                    :key="group.key"
                                                    class="robot-course-item-detail"
                                                    :class="{
                                                        'robot-course-item-detail--disabled': !additionalCourseGroupSelected(course, group),
                                                        'robot-course-item-detail--used': courseGroupUsedInSelectedTimetable(course, group),
                                                        'robot-course-item-detail--conflict': courseGroupNoLongerFitsSelectedTimetable(course, group),
                                                    }">
                                                    <div class="robot-course-item-detail__main">
                                                        <v-checkbox
                                                            :model-value="additionalCourseGroupSelected(course, group)"
                                                            :aria-label="`${group.title} auswählen`"
                                                            density="compact"
                                                            color="primary"
                                                            :disabled="additionalCourseSelectionLocked || !additionalCourseSelectable(course) || additionalCourseInteractionDisabled(course)"
                                                            hide-details
                                                            class="robot-course-item-detail__check"
                                                            @click.stop="showCourseActionAfterCourseInteraction"
                                                            @update:model-value="setAdditionalCourseGroupSelected(course, group, $event)" />
                                                        <span>
                                                            {{ group.title }}<sup v-if="courseGroupDistanceLearning(course, group)" class="robot-course-fu">FU</sup>:
                                                        </span>
                                                    </div>
                                                    <div class="robot-course-item-detail__meta">
                                                        {{ group.meta }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="robot-course-item-details__empty">
                                                Keine TT-Stunden.
                                            </div>
                                        </div>
                                    </div>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <slot
        v-if="embeddedCourseCardsOnly"
        name="course-actions"
        :loading="courseCardsLoading"
        :ready="courseCardsReady"
        :extending="additionalCourseExtensionMode"
        :create-action-visible="courseActionVisible"
        :has-selected-additional-courses="selectedAdditionalCourses.length > 0"
        :extension-action-visible="additionalCourseExtensionActionVisible" />

    <v-col v-else cols="12" lg="8" xl="7">
        <v-card rounded="lg" border class="robot-timetable-card">
            <v-card-title class="robot-timetable-card__title d-flex align-center ga-2">
                <v-icon icon="mdi-robot-outline" />
                <span>Stundenplan Wizzard</span>
                <v-spacer />
                <v-btn
                    icon="mdi-cog-outline"
                    variant="text"
                    density="comfortable"
                    color="primary"
                    title="Einstellungen"
                    aria-label="Einstellungen"
                    @click="settingsDialogOpen = true" />
                <v-btn
                    icon="mdi-information-outline"
                    variant="text"
                    density="comfortable"
                    color="primary"
                    title="Hinweise zum Stundenplan Wizzard"
                    aria-label="Hinweise zum Stundenplan Wizzard"
                    @click="infoDialogOpen = true" />
            </v-card-title>
            <v-card-text class="robot-timetable-card__text">
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3" />

                <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
                    {{ error }}
                </v-alert>

                <div class="robot-student-selection">
                    <div class="robot-student-selection__content">
                        <div class="robot-selected-cards robot-selected-cards--student">
                            <div
                                class="robot-selected-card robot-selected-card--button"
                                role="button"
                                tabindex="0"
                                @click="toggleStudentCompletedCourses"
                                @keydown.enter.prevent="toggleStudentCompletedCourses"
                                @keydown.space.prevent="toggleStudentCompletedCourses">
                                <div class="robot-selected-card__header">
                                    <div class="robot-selected-card__label">Student</div>
                                    <v-icon
                                        v-if="selectedStudent"
                                        :icon="studentCompletedCoursesExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                        size="18"
                                        color="primary" />
                                </div>
                                <div class="robot-selected-card__value">{{ selectedStudentLabel }}</div>
                            </div>
                        </div>
                        <v-expand-transition>
                            <div
                                v-if="selectedStudent && studentCompletedCoursesExpanded"
                                class="robot-student-course-overview">
                                <div class="robot-student-course-section robot-student-course-section--completed">
                                    <div class="robot-student-course-section__title">
                                        <v-icon icon="mdi-school-outline" />
                                        <span>Abgeschlossene Kurse</span>
                                        <v-chip size="x-small" color="primary" variant="tonal">
                                            {{ studentCompletedCourses.length }}
                                        </v-chip>
                                    </div>
                                    <v-progress-linear
                                        v-if="studentCompletedCoursesLoading"
                                        indeterminate
                                        color="primary"
                                        class="mt-3 mb-0" />
                                    <v-alert
                                        v-else-if="studentCompletedCoursesError"
                                        type="error"
                                        variant="tonal"
                                        density="compact"
                                        class="mt-3 mb-0">
                                        {{ studentCompletedCoursesError }}
                                    </v-alert>
                                    <v-alert
                                        v-else-if="!studentCompletedCourses.length"
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                        class="mt-3 mb-0">
                                        Keine abgeschlossenen Kurse mit Note gefunden.
                                    </v-alert>
                                    <div v-else class="robot-student-completed-course-list">
                                        <div
                                            v-for="course in studentCompletedCourses"
                                            :key="`${course.subject}-${course.grade}`"
                                            class="robot-student-completed-course">
                                            <span class="robot-student-completed-course__subject">{{ course.subject }}</span>
                                            <v-chip size="x-small" color="primary" variant="tonal">
                                                {{ course.grade }}
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="!studentCompletedCoursesLoading && !studentCompletedCoursesError"
                                    class="robot-student-course-section robot-student-course-section--missing">
                                    <div class="robot-student-course-section__title">
                                        <v-icon icon="mdi-alert-circle-outline" />
                                        <span>Fehlende Kurse</span>
                                        <v-chip size="x-small" color="primary" variant="tonal">
                                            {{ studentMissingCourses.length }}
                                        </v-chip>
                                    </div>
                                    <v-alert
                                        v-if="!studentMissingCourses.length"
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                        class="mt-3 mb-0">
                                        Keine fehlenden Kurse aus früheren Semestern gefunden.
                                    </v-alert>
                                    <div v-else class="robot-student-completed-course-list">
                                        <div
                                            v-for="course in studentMissingCourses"
                                            :key="course.key"
                                            class="robot-student-completed-course"
                                            :class="{ 'robot-student-completed-course--no-timetable-hours': !courseSelectable(course) }">
                                            <span class="robot-student-completed-course__subject">
                                                <span>{{ course.code }}</span>
                                            </span>
                                            <v-chip size="x-small" color="primary" variant="tonal">
                                                {{ formatHours(course.hours) }}
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="!studentCompletedCoursesLoading && !studentCompletedCoursesError"
                                    class="robot-student-course-section robot-student-course-section--planned">
                                    <div class="robot-student-course-section__title">
                                        <v-icon icon="mdi-calendar-check-outline" />
                                        <span>Vorgesehene Kurse</span>
                                        <v-chip size="x-small" color="primary" variant="tonal">
                                            {{ studentPlannedCourses.length }}
                                        </v-chip>
                                    </div>
                                    <v-alert
                                        v-if="!studentPlannedCourses.length"
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                        class="mt-3 mb-0">
                                        Keine offenen Kurse für das Semester gefunden.
                                    </v-alert>
                                    <div v-else class="robot-student-completed-course-list">
                                        <div
                                            v-for="course in studentPlannedCourses"
                                            :key="course.key"
                                            class="robot-student-completed-course"
                                            :class="{ 'robot-student-completed-course--no-timetable-hours': !courseSelectable(course) }">
                                            <span class="robot-student-completed-course__subject">
                                                <span>{{ course.code }}</span>
                                            </span>
                                            <v-chip size="x-small" color="primary" variant="tonal">
                                                {{ formatHours(course.hours) }}
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="!studentCompletedCoursesLoading && !studentCompletedCoursesError"
                                    class="robot-student-course-section robot-student-course-section--additional">
                                    <div class="robot-student-course-section__title">
                                        <v-icon icon="mdi-plus-circle-outline" />
                                        <span>Zusätzliche Kurse</span>
                                        <v-chip size="x-small" color="primary" variant="tonal">
                                            {{ studentAdditionalCourses.length }}
                                        </v-chip>
                                    </div>
                                    <v-alert
                                        v-if="!studentAdditionalCourses.length"
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                        class="mt-3 mb-0">
                                        Keine zusätzlichen Kurse nach den bisherigen Abschlüssen gefunden.
                                    </v-alert>
                                    <div v-else class="robot-student-completed-course-list">
                                        <div
                                            v-for="course in studentAdditionalCourses"
                                            :key="course.key"
                                            class="robot-student-completed-course"
                                            :class="{ 'robot-student-completed-course--no-timetable-hours': !courseSelectable(course) }">
                                            <span class="robot-student-completed-course__subject">
                                                <span>{{ course.code }}</span>
                                            </span>
                                            <v-chip size="x-small" color="primary" variant="tonal">
                                                {{ formatHours(course.hours) }}
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </v-expand-transition>
                    </div>
                    <div class="robot-student-selection__actions">
                        <v-btn
                            v-if="selectedStudent"
                            prepend-icon="mdi-restore"
                            variant="tonal"
                            color="primary"
                            size="small"
                            title="Student-Einstellungen zurücksetzen"
                            :disabled="!studentCourseSelectionResettable"
                            @click="resetStudentCourseSelection">
                            Zurücksetzen
                        </v-btn>
                        <v-btn
                            icon="mdi-pencil"
                            variant="tonal"
                            color="primary"
                            density="comfortable"
                            title="Student bearbeiten"
                            @click="openStudentDialog" />
                        <v-btn
                            v-if="selectedStudent"
                            icon="mdi-close-circle-outline"
                            variant="text"
                            color="error"
                            density="comfortable"
                            title="Student löschen"
                            aria-label="Student löschen"
                            @click="clearStudentSelection" />
                    </div>
                </div>

                <div v-if="activeEvaluationCriteria.length && !selectedRobotTimetable" class="robot-eval-criteria-summary">
                    <div class="robot-eval-criteria-summary__title">
                        <v-icon icon="mdi-tune-variant" size="14" />
                        Bewertungskriterien
                    </div>
                    <div class="robot-eval-criteria-summary__list">
                        <span
                            v-for="(criterion, index) in activeEvaluationCriteria"
                            :key="criterion.key"
                            class="robot-eval-criteria-summary__item">
                            <span class="robot-eval-criteria-summary__rank">{{ index + 1 }}</span>
                            <span class="robot-eval-criteria-summary__label">{{ criterion.label }}</span>
                            <span v-if="criterion.optionLabel" class="robot-eval-criteria-summary__option">{{ criterion.optionLabel }}</span>
                        </span>
                    </div>
                </div>

                <div class="robot-selection">
                    <div class="robot-selected-cards">
                        <div
                            v-for="item in selectedSummary"
                            :key="item.key"
                            class="robot-selected-card">
                            <div class="robot-selected-card__label">{{ item.label }}</div>
                            <div class="robot-selected-card__value">{{ item.value }}</div>
                        </div>
                    </div>
                    <v-btn
                        icon="mdi-pencil"
                        variant="tonal"
                        color="primary"
                        title="Auswahl bearbeiten"
                        @click="openSelectionDialog" />
                </div>

                <div class="robot-constraints">
                    <div class="robot-selected-cards robot-selected-cards--constraints">
                        <div
                            v-for="item in displayedConstraintSummary"
                            :key="item.key"
                            class="robot-selected-card">
                            <div class="robot-selected-card__label">{{ item.label }}</div>
                            <div class="robot-selected-card__value">{{ item.value }}</div>
                        </div>
                    </div>
                    <v-btn
                        icon="mdi-pencil"
                        variant="tonal"
                        color="primary"
                        title="Einschränkungen bearbeiten"
                        @click="openConstraintsDialog" />
                </div>

                <div class="robot-course-list robot-course-panel">
                    <div class="robot-course-panel__title">
                        <div class="robot-course-list__header robot-course-list__header--panel">
                            <div class="robot-course-list__title">{{ regularCourseListTitle }}</div>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ regularCourseCountLabel }}
                            </v-chip>
                            <v-chip size="x-small" color="secondary" variant="tonal">
                                {{ formatHours(selectedCoursesHours) }} Std.
                            </v-chip>
                            <v-btn
                                class="robot-course-list__reset"
                                size="small"
                                variant="text"
                                color="primary"
                                prepend-icon="mdi-restore"
                                :disabled="!courseSelectionResettable"
                                @click="resetCourseSelection">
                                Zurücksetzen
                            </v-btn>
                        </div>
                    </div>
                    <div class="robot-course-panel__body">
                        <v-alert v-if="!loading && !availableCourses.length" type="info" variant="tonal" class="mb-0">
                            Keine passenden Kurse gefunden.
                        </v-alert>

                        <v-alert v-else-if="!selectedCourses.length" type="warning" variant="tonal" class="mb-3">
                            Keine Kurse ausgewählt.
                        </v-alert>

                        <div v-if="availableCourses.length" class="robot-course-columns">
                            <div
                                v-for="(courseColumn, columnIndex) in regularCourseColumns"
                                :key="`course-column-${courseColumn.key || columnIndex}`"
                                class="robot-course-item-list">
                                <div v-if="courseColumn.title" class="robot-regular-course-column-title">
                                    <span>{{ courseColumn.title }}</span>
                                    <v-chip size="x-small" color="primary" variant="tonal">
                                        {{ courseColumn.courses.length }}
                                    </v-chip>
                                </div>
                                <div class="robot-course-item-header robot-course-item-header--expandable">
                                    <div>Aktiv</div>
                                    <div>Code</div>
                                    <div>Bezeichnung</div>
                                    <div>Zweig</div>
                                    <div class="text-right">Std.</div>
                                </div>
                                <v-expansion-panels
                                    v-model="courseItemPanels"
                                    multiple
                                    variant="accordion"
                                    class="robot-course-item-panels">
                                    <v-expansion-panel
                                        v-for="course in courseColumn.courses"
                                        :key="course.key"
                                        :value="course.key"
                                        class="robot-course-item-panel"
                                        :class="{
                                            'robot-course-item-panel--disabled': !courseSelected(course),
                                            'robot-course-item-panel--no-timetable-hours': !courseSelectable(course),
                                            'robot-course-item-panel--used': courseUsedInSelectedTimetable(course),
                                            'robot-course-item-panel--conflict': courseOverlapsInSelectedTimetable(course) || courseAllGroupsNoLongerFitSelectedTimetable(course),
                                        }">
                                        <v-expansion-panel-title class="robot-course-item-panel__title">
                                            <div class="robot-course-item-row">
                                                <div class="robot-course-item-row__select">
                                                    <v-checkbox
                                                        :model-value="courseFullySelected(course)"
                                                        :indeterminate="coursePartiallySelected(course)"
                                                        :aria-label="`${course.code} auswählen`"
                                                        density="compact"
                                                        color="primary"
                                                        :disabled="!courseSelectable(course)"
                                                        hide-details
                                                        @click.stop
                                                        @update:model-value="setCourseSelected(course, $event)" />
                                                </div>
                                                <div class="robot-course-item-row__code">
                                                    <span>{{ course.code }}</span>
                                                </div>
                                                <div>{{ course.name }}</div>
                                                <div>{{ course.branch }}</div>
                                                <div class="text-right">{{ formatHours(course.hours) }}</div>
                                            </div>
                                        </v-expansion-panel-title>
                                        <v-expansion-panel-text>
                                            <div class="robot-course-item-details">
                                                <div class="robot-course-item-details__section">
                                                    <div class="robot-course-item-details__title">Stundenplan</div>
                                                    <div
                                                        v-if="courseGroupItems(course).length"
                                                        class="robot-course-item-detail-list">
                                                        <div
                                                            v-for="group in courseGroupItems(course)"
                                                            :key="group.key"
                                                            class="robot-course-item-detail"
                                                            :class="{
                                                                'robot-course-item-detail--disabled': !courseGroupSelected(course, group),
                                                                'robot-course-item-detail--used': courseGroupUsedInSelectedTimetable(course, group),
                                                                'robot-course-item-detail--conflict': courseGroupNoLongerFitsSelectedTimetable(course, group),
                                                            }">
                                                            <div class="robot-course-item-detail__main">
                                                                <v-checkbox
                                                                    :model-value="courseGroupSelected(course, group)"
                                                                    :aria-label="`${group.title} auswählen`"
                                                                    density="compact"
                                                                    color="primary"
                                                                    hide-details
                                                                    class="robot-course-item-detail__check"
                                                                    @click.stop
                                                                    @update:model-value="setCourseGroupSelected(course, group, $event)" />
                                                                <span>
                                                                    {{ group.title }}<sup v-if="courseGroupDistanceLearning(course, group)" class="robot-course-fu">FU</sup>:
                                                                </span>
                                                            </div>
                                                            <div class="robot-course-item-detail__meta">
                                                                {{ group.meta }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-else class="robot-course-item-details__empty">
                                                        Keine TT-Stunden.
                                                    </div>
                                                </div>
                                            </div>
                                        </v-expansion-panel-text>
                                    </v-expansion-panel>
                                </v-expansion-panels>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="additionalCoursePanelVisible"
                    class="robot-course-list robot-course-panel">
                    <div class="robot-course-panel__title">
                        <div class="robot-course-list__header robot-course-list__header--panel">
                            <div class="robot-course-list__title">Zusätzliche Kurse</div>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ studentAdditionalCourses.length }}
                            </v-chip>
                            <v-btn
                                class="robot-course-list__reset"
                                size="small"
                                variant="text"
                                color="primary"
                                prepend-icon="mdi-restore"
                                :disabled="additionalCourseSelectionLocked || !additionalCourseSelectionResettable"
                                @click="resetAdditionalCourseSelection">
                                Zurücksetzen
                            </v-btn>
                        </div>
                    </div>
                    <div class="robot-course-panel__body">
                        <div class="robot-course-columns">
                            <div
                                v-for="(courseColumn, columnIndex) in additionalCourseColumns"
                                :key="`additional-course-column-${columnIndex}`"
                                class="robot-course-item-list">
                                <div class="robot-course-item-header robot-course-item-header--expandable">
                                    <div>Aktiv</div>
                                    <div>Code</div>
                                    <div>Bezeichnung</div>
                                    <div>Zweig</div>
                                    <div class="text-right">Std.</div>
                                </div>
                                <v-expansion-panels
                                    multiple
                                    variant="accordion"
                                    class="robot-course-item-panels">
                                    <v-expansion-panel
                                        v-for="course in courseColumn"
                                        :key="course.key"
                                        :value="course.key"
                                        class="robot-course-item-panel"
                                        :class="{
                                            'robot-course-item-panel--disabled': !additionalCourseSelectable(course),
                                            'robot-course-item-panel--no-timetable-hours': !courseSelectable(course),
                                            'robot-course-item-panel--used': courseUsedInSelectedTimetable(course),
                                            'robot-course-item-panel--missing-additional': additionalCourseInteractionDisabled(course),
                                        }">
                                        <v-expansion-panel-title class="robot-course-item-panel__title">
                                            <div class="robot-course-item-row">
                                                <div class="robot-course-item-row__select">
                                                    <v-checkbox
                                                        :model-value="additionalCourseFullySelected(course)"
                                                        :indeterminate="additionalCoursePartiallySelected(course)"
                                                        :aria-label="`${course.code} auswählen`"
                                                        density="compact"
                                                        color="primary"
                                                        :disabled="additionalCourseSelectionLocked || !additionalCourseSelectable(course) || additionalCourseInteractionDisabled(course)"
                                                        hide-details
                                                        @click.stop
                                                        @update:model-value="setAdditionalCourseSelected(course, $event)" />
                                                </div>
                                                <div class="robot-course-item-row__code">
                                                    <span>{{ course.code }}</span>
                                                </div>
                                                <div>{{ course.name }}</div>
                                                <div>{{ course.branch }}</div>
                                                <div class="text-right">{{ formatHours(course.hours) }}</div>
                                            </div>
                                        </v-expansion-panel-title>
                                        <v-expansion-panel-text>
                                            <div class="robot-course-item-details">
                                                <div class="robot-course-item-details__section">
                                                    <div class="robot-course-item-details__title">Stundenplan</div>
                                                    <div
                                                        v-if="courseGroupItems(course).length"
                                                        class="robot-course-item-detail-list">
                                                        <div
                                                            v-for="group in courseGroupItems(course)"
                                                            :key="group.key"
                                                            class="robot-course-item-detail"
                                                            :class="{
                                                                'robot-course-item-detail--disabled': !additionalCourseGroupSelected(course, group),
                                                                'robot-course-item-detail--used': courseGroupUsedInSelectedTimetable(course, group),
                                                                'robot-course-item-detail--conflict': courseGroupNoLongerFitsSelectedTimetable(course, group),
                                                            }">
                                                            <div class="robot-course-item-detail__main">
                                                                <v-checkbox
                                                                    :model-value="additionalCourseGroupSelected(course, group)"
                                                                    :aria-label="`${group.title} auswählen`"
                                                                    density="compact"
                                                                    color="primary"
                                                                    :disabled="additionalCourseSelectionLocked || !additionalCourseSelectable(course) || additionalCourseInteractionDisabled(course)"
                                                                    hide-details
                                                                    class="robot-course-item-detail__check"
                                                                    @click.stop
                                                                    @update:model-value="setAdditionalCourseGroupSelected(course, group, $event)" />
                                                                <span>
                                                                    {{ group.title }}<sup v-if="courseGroupDistanceLearning(course, group)" class="robot-course-fu">FU</sup>:
                                                                </span>
                                                            </div>
                                                            <div class="robot-course-item-detail__meta">
                                                                {{ group.meta }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-else class="robot-course-item-details__empty">
                                                        Keine TT-Stunden.
                                                    </div>
                                                </div>
                                            </div>
                                        </v-expansion-panel-text>
                                    </v-expansion-panel>
                                </v-expansion-panels>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="robot-generator">
                    <div class="robot-generator__actions">
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-calendar-clock"
                            :disabled="loading || timetableGenerationLoading || !selectedCourses.length"
                            :loading="timetableGenerationLoading"
                            @click="createTimetables">
                            Stundenpläne erstellen
                        </v-btn>
                    </div>

                    <v-alert
                        v-if="fullGreenTimetableCountError"
                        type="error"
                        variant="tonal"
                        class="mt-3 mb-0">
                        {{ fullGreenTimetableCountError }}
                    </v-alert>

                    <template v-if="timetableCountResultsAvailable">
                        <div v-if="false" class="robot-count-cards">
                            <div
                                class="robot-count-card"
                                :class="{ 'robot-count-card--selected': !backendVariationCountsAvailable && timetableResultCardSelected('full_green') }">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">
                                        {{ backendVariationCountsAvailable ? 'Variationen gesamt' : 'Volle grüne Stundenpläne' }}
                                    </div>
                                    <div class="robot-count-card__value">
                                        <v-progress-circular
                                            v-if="fullGreenTimetableCountLoading"
                                            indeterminate
                                            size="22"
                                            width="2"
                                            color="primary" />
                                        <template v-else>
                                            {{ backendVariationCountsAvailable ? totalTimetableVariationCountLabel : fullGreenTimetableCountLabel }}
                                        </template>
                                    </div>
                                </div>
                                <div v-if="!backendVariationCountsAvailable" class="robot-count-card__actions">
                                    <v-switch
                                        :model-value="timetableResultCardSelected('full_green')"
                                        color="success"
                                        inset
                                        hide-details
                                        density="compact"
                                        :disabled="!isTimetableResultTypeSelectable('full_green')"
                                        aria-label="Volle grüne Stundenpläne auswählen"
                                        @update:modelValue="setSelectedTimetableResultType('full_green', $event)" />
                                    <v-icon icon="mdi-check-circle-outline" color="success" />
                                    <v-checkbox-btn
                                        v-if="timetableResultCardSelected('full_green')"
                                        :model-value="timetableResultCardSelected('full_green')"
                                        color="success"
                                        density="compact"
                                        readonly
                                        tabindex="-1"
                                        aria-label="Volle grüne Stundenpläne ausgewählt"
                                        class="robot-count-card__selected-checkbox" />
                                </div>
                            </div>

                            <div
                                class="robot-count-card robot-count-card--green"
                                :class="{
                                    'robot-count-card--selected': backendVariationCountsAvailable ? timetableResultCardSelected('full_green') : timetableResultCardSelected('green'),
                                    'robot-count-card--clickable': backendCountCardSelectable('full_green'),
                                }"
                                :role="backendCountCardSelectable('full_green') ? 'button' : null"
                                :tabindex="backendCountCardSelectable('full_green') ? 0 : null"
                                @click="selectBackendCountCard('full_green')"
                                @keydown.enter.prevent="selectBackendCountCard('full_green')"
                                @keydown.space.prevent="selectBackendCountCard('full_green')">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">
                                        {{ backendVariationCountsAvailable ? 'Volle grüne Stundenpläne' : 'Grüne Stundenpläne' }}
                                    </div>
                                    <div class="robot-count-card__value">
                                        <v-progress-circular
                                            v-if="fullGreenTimetableCountLoading"
                                            indeterminate
                                            size="22"
                                            width="2"
                                            color="primary" />
                                        <template v-else>
                                            {{ backendVariationCountsAvailable ? fullGreenTimetableCountLabel : greenTimetableCountLabel }}
                                        </template>
                                    </div>
                                </div>
                                <div v-if="!backendVariationCountsAvailable" class="robot-count-card__actions">
                                    <v-switch
                                        :model-value="timetableResultCardSelected('green')"
                                        color="primary"
                                        inset
                                        hide-details
                                        density="compact"
                                        :disabled="!isTimetableResultTypeSelectable('green')"
                                        aria-label="Grüne Stundenpläne auswählen"
                                        @update:modelValue="setSelectedTimetableResultType('green', $event)" />
                                    <v-icon icon="mdi-calendar-check-outline" color="primary" />
                                    <v-checkbox-btn
                                        v-if="timetableResultCardSelected('green')"
                                        :model-value="timetableResultCardSelected('green')"
                                        color="primary"
                                        density="compact"
                                        readonly
                                        tabindex="-1"
                                        aria-label="Grüne Stundenpläne ausgewählt"
                                        class="robot-count-card__selected-checkbox" />
                                </div>
                                <div
                                    v-else-if="backendCountCardSelectable('full_green') || timetableResultCardSelected('full_green')"
                                    class="robot-count-card__actions">
                                    <v-checkbox-btn
                                        :model-value="timetableResultCardSelected('full_green')"
                                        color="success"
                                        density="compact"
                                        readonly
                                        tabindex="-1"
                                        :aria-label="timetableResultCardSelected('full_green') ? 'Volle grüne Stundenpläne ausgewählt' : 'Volle grüne Stundenpläne auswählbar'"
                                        class="robot-count-card__selected-checkbox" />
                                </div>
                            </div>

                            <div
                                v-if="backendVariationCountsAvailable"
                                class="robot-count-card robot-count-card--green"
                                :class="{
                                    'robot-count-card--selected': timetableResultCardSelected('green'),
                                    'robot-count-card--clickable': backendCountCardSelectable('green'),
                                }"
                                :role="backendCountCardSelectable('green') ? 'button' : null"
                                :tabindex="backendCountCardSelectable('green') ? 0 : null"
                                @click="selectBackendCountCard('green')"
                                @keydown.enter.prevent="selectBackendCountCard('green')"
                                @keydown.space.prevent="selectBackendCountCard('green')">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">Grüne Stundenpläne</div>
                                    <div class="robot-count-card__value">
                                        <v-progress-circular
                                            v-if="fullGreenTimetableCountLoading"
                                            indeterminate
                                            size="22"
                                            width="2"
                                            color="primary" />
                                        <template v-else>
                                            {{ greenTimetableCountLabel }}
                                        </template>
                                    </div>
                                </div>
                                <div
                                    v-if="backendCountCardSelectable('green') || timetableResultCardSelected('green')"
                                    class="robot-count-card__actions">
                                    <v-checkbox-btn
                                        :model-value="timetableResultCardSelected('green')"
                                        color="primary"
                                        density="compact"
                                        readonly
                                        tabindex="-1"
                                        :aria-label="timetableResultCardSelected('green') ? 'Grüne Stundenpläne ausgewählt' : 'Grüne Stundenpläne auswählbar'"
                                        class="robot-count-card__selected-checkbox" />
                                </div>
                            </div>

                            <div
                                v-if="additionalCourseTimetableCountCardVisible"
                                class="robot-count-card robot-count-card--additional"
                                :class="{
                                    'robot-count-card--selected': additionalCourseTimetableRequired,
                                    'robot-count-card--clickable': isAdditionalCourseTimetableFilterSelectable(),
                                }"
                                :role="isAdditionalCourseTimetableFilterSelectable() ? 'button' : null"
                                :tabindex="isAdditionalCourseTimetableFilterSelectable() ? 0 : null"
                                @click="setAdditionalCourseTimetableRequired(true)"
                                @keydown.enter.prevent="setAdditionalCourseTimetableRequired(true)"
                                @keydown.space.prevent="setAdditionalCourseTimetableRequired(true)">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">{{ additionalCourseTimetableCountCardTitle }}</div>
                                    <div class="robot-count-card__meta">inkl. Zusätzliche Stunden</div>
                                    <div class="robot-count-card__value">
                                        <v-progress-circular
                                            v-if="fullGreenTimetableCountLoading"
                                            indeterminate
                                            size="22"
                                            width="2"
                                            color="primary" />
                                        <template v-else>
                                            {{ additionalCourseTimetableCountCardValue }}
                                        </template>
                                    </div>
                                </div>
                                <div
                                    v-if="isAdditionalCourseTimetableFilterSelectable() || additionalCourseTimetableRequired"
                                    class="robot-count-card__actions">
                                    <v-checkbox-btn
                                        :model-value="additionalCourseTimetableRequired"
                                        color="deep-orange"
                                        density="compact"
                                        readonly
                                        tabindex="-1"
                                        :aria-label="additionalCourseTimetableRequired ? 'Stundenpläne mit Zusatzkursen ausgewählt' : 'Stundenpläne mit Zusatzkursen auswählbar'"
                                        class="robot-count-card__selected-checkbox" />
                                </div>
                            </div>

                            <div
                                v-if="backendVariationCountsAvailable || showConflictTimetableResults"
                                class="robot-count-card robot-count-card--conflict"
                                :class="{
                                    'robot-count-card--selected': timetableResultCardSelected('conflict'),
                                    'robot-count-card--clickable': backendCountCardSelectable('conflict'),
                                }"
                                :role="backendCountCardSelectable('conflict') ? 'button' : null"
                                :tabindex="backendCountCardSelectable('conflict') ? 0 : null"
                                @click="selectBackendCountCard('conflict')"
                                @keydown.enter.prevent="selectBackendCountCard('conflict')"
                                @keydown.space.prevent="selectBackendCountCard('conflict')">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">
                                        {{ backendVariationCountsAvailable ? 'Rote Stundenpläne' : 'Stundenpläne mit Konflikten' }}
                                    </div>
                                    <div class="robot-count-card__value">
                                        <v-progress-circular
                                            v-if="fullGreenTimetableCountLoading"
                                            indeterminate
                                            size="22"
                                            width="2"
                                            color="primary" />
                                        <template v-else>
                                            {{ conflictTimetableCountLabel }}
                                        </template>
                                    </div>
                                </div>
                                <div v-if="!backendVariationCountsAvailable" class="robot-count-card__actions">
                                    <v-switch
                                        :model-value="timetableResultCardSelected('conflict')"
                                        color="warning"
                                        inset
                                        hide-details
                                        density="compact"
                                        :disabled="!isTimetableResultTypeSelectable('conflict')"
                                        aria-label="Stundenpläne mit Konflikten auswählen"
                                        @update:modelValue="setSelectedTimetableResultType('conflict', $event)" />
                                    <v-icon icon="mdi-alert-circle-outline" color="warning" />
                                    <v-checkbox-btn
                                        v-if="timetableResultCardSelected('conflict')"
                                        :model-value="timetableResultCardSelected('conflict')"
                                        color="warning"
                                        density="compact"
                                        readonly
                                        tabindex="-1"
                                        aria-label="Rote Stundenpläne ausgewählt"
                                        class="robot-count-card__selected-checkbox" />
                                </div>
                                <div
                                    v-else-if="backendCountCardSelectable('conflict') || timetableResultCardSelected('conflict')"
                                    class="robot-count-card__actions">
                                    <v-checkbox-btn
                                        :model-value="timetableResultCardSelected('conflict')"
                                        color="warning"
                                        density="compact"
                                        readonly
                                        tabindex="-1"
                                        :aria-label="timetableResultCardSelected('conflict') ? 'Rote Stundenpläne ausgewählt' : 'Rote Stundenpläne auswählbar'"
                                        class="robot-count-card__selected-checkbox" />
                                </div>
                            </div>

                        </div>

                        <v-alert
                            v-if="!backendVariationCountsAvailable && selectedOptionsNoResultAlertVisible"
                            type="info"
                            variant="tonal"
                            density="compact"
                            class="robot-no-result-alert">
                            <div class="robot-no-result-alert__title">
                                Keine passenden Stundenpläne für die aktuelle Auswahl.
                            </div>
                            <ul
                                v-if="selectedOptionsNoResultReasons.length"
                                class="robot-no-result-alert__reasons">
                                <li
                                    v-for="reason in selectedOptionsNoResultReasons"
                                    :key="reason">
                                    {{ reason }}
                                </li>
                            </ul>
                        </v-alert>

                        <div v-if="!backendVariationCountsAvailable && !selectedRobotTimetable" class="robot-quality-card">
                            <div class="robot-quality-card__header">
                                <div>
                                    <div class="robot-quality-card__title">Qualitätskriterien</div>
                                    <div class="robot-quality-card__meta">
                                        Erreichte aktive Bewertungskriterien
                                    </div>
                                </div>
                                <v-icon icon="mdi-chart-box-outline" color="primary" />
                            </div>

                            <div v-if="qualityCriterionRows.length" class="robot-quality-card__items">
                                <div class="robot-quality-card__item robot-quality-card__item--summary">
                                    <div class="robot-quality-card__item-copy">
                                        <div class="robot-quality-card__item-label">
                                            Alle Qualitätskriterien erfüllt
                                        </div>
                                        <div class="robot-quality-card__item-meta">
                                            {{ allQualityCriteriaCountDetail() }}
                                        </div>
                                    </div>
                                    <div class="robot-quality-card__item-count">
                                        {{ allQualityCriteriaCountLabel() }}
                                    </div>
                                </div>

                                <div
                                    v-for="(counter, counterIndex) in qualityCriterionRows"
                                    :key="counter.key"
                                    class="robot-quality-card__item">
                                    <div class="robot-quality-card__item-copy">
                                        <div class="robot-quality-card__item-label">
                                            {{ counterIndex + 1 }}. {{ counter.label }}
                                        </div>
                                        <div class="robot-quality-card__item-meta">
                                            {{ qualityCounterDetail(counter) }}
                                        </div>
                                    </div>
                                    <v-checkbox-btn
                                        :model-value="counter.enabled === true"
                                        color="primary"
                                        density="compact"
                                        :disabled="fullGreenTimetableCountLoading"
                                        :aria-label="`${counter.label} für diese Berechnung verwenden`"
                                        @update:model-value="setEvaluationCriterionEnabled(counter, $event)"
                                        class="robot-quality-card__item-check" />
                                    <div class="robot-quality-card__item-count">
                                        {{ qualityCounterCountLabel(counter) }}
                                    </div>
                                </div>
                            </div>

                            <v-alert v-else type="info" variant="tonal" density="compact" class="mb-0">
                                Keine aktiven Bewertungskriterien gespeichert.
                            </v-alert>
                        </div>
                    </template>

                    <div v-if="generatedTimetableDisplayVisible" class="robot-generated">
                        <div class="robot-course-list__header robot-generated-header">
                            <div class="robot-generated-header__actions">
                                <v-btn
                                    v-if="generatedTimetableActionButtonsVisible"
                                    class="robot-generated-overtake-button"
                                    color="success"
                                    variant="flat"
                                    size="large"
                                    prepend-icon="mdi-calendar-clock"
                                    :disabled="timetableGenerationLoading || !selectedRobotTimetableCourseGroupKeys().length"
                                    @click="overtakeSelectedTimetableToOverview">
                                    Übernehmen
                                </v-btn>
                                <div
                                    v-if="selectedTimetableResultCount > 0"
                                    class="robot-timetable-selector">
                                    <v-btn
                                        icon="mdi-chevron-left"
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        :disabled="timetableGenerationLoading || timetableResultCounter(selectedTimetableResultType) <= 1"
                                        :aria-label="`Vorheriger ${selectedTimetableResultTitle}`"
                                        @click="moveTimetableResultCounter(selectedTimetableResultType, -1)" />
                                    <div class="robot-timetable-selector__value" aria-live="polite">
                                        {{ timetableResultCounter(selectedTimetableResultType) }} / {{ selectedTimetableResultCount }}
                                    </div>
                                    <v-btn
                                        icon="mdi-chevron-right"
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        :disabled="timetableGenerationLoading || timetableResultCounter(selectedTimetableResultType) >= selectedTimetableResultCount"
                                        :aria-label="`Nächster ${selectedTimetableResultTitle}`"
                                        @click="moveTimetableResultCounter(selectedTimetableResultType, 1)" />
                                    <v-chip
                                        size="small"
                                        :color="selectedTimetableResultStatusColor"
                                        variant="tonal"
                                        class="robot-timetable-selector__status">
                                        {{ selectedTimetableResultStatusLabel }}
                                    </v-chip>
                                </div>
                            </div>
                        </div>

                        <div v-if="false" class="robot-generated-criteria-count">
                            <div
                                class="robot-count-card robot-count-card--green robot-count-card--criteria"
                                :class="{
                                    'robot-count-card--selected': qualityCriteriaResultFilterActive,
                                    'robot-count-card--clickable': qualityCriteriaResultFilterAvailable(),
                                }"
                                :role="qualityCriteriaResultFilterAvailable() ? 'button' : null"
                                :tabindex="qualityCriteriaResultFilterAvailable() ? 0 : null"
                                @click="toggleQualityCriteriaResultFilter"
                                @keydown.enter.prevent="toggleQualityCriteriaResultFilter"
                                @keydown.space.prevent="toggleQualityCriteriaResultFilter">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">Stundenpläne mit Kriterien</div>
                                    <div class="robot-count-card__value">{{ selectedCriteriaTimetableCountLabel }}</div>
                                </div>
                                <v-checkbox-btn
                                    :model-value="qualityCriteriaResultFilterActive"
                                    color="primary"
                                    density="compact"
                                    :disabled="!qualityCriteriaResultFilterAvailable() || fullGreenTimetableCountLoading || timetableGenerationLoading"
                                    readonly
                                    tabindex="-1"
                                    :aria-label="qualityCriteriaResultFilterActive ? 'Stundenpläne mit Kriterien ausgewählt' : 'Stundenpläne mit Kriterien auswählbar'"
                                    class="robot-count-card__check" />
                            </div>
                        </div>

                        <div
                            v-if="activeQualityCriterionRows.length || selectedAdditionalCourses.length"
                            class="robot-quality-summary">
                            <div
                                v-for="counter in activeQualityCriterionRows"
                                :key="`quality-summary-${counter.key}`"
                                class="robot-quality-summary__item"
                                :class="{
                                    'robot-quality-summary__item--selected': qualitySummaryCheckboxChecked(counter),
                                    'robot-quality-summary__item--reached': qualitySummaryCheckboxChecked(counter) && qualityCounterReached(counter),
                                    'robot-quality-summary__item--missed': !qualityCounterReached(counter),
                                }">
                                <span class="robot-quality-summary__content">
                                    <span class="robot-quality-summary__label">{{ counter.label }}</span>
                                    <span class="robot-quality-summary__meta-row">
                                        <span class="robot-quality-summary__meta">
                                            {{ qualityCounterCardMeta(counter) }}
                                        </span>
                                        <span class="robot-quality-summary__controls">
                                            <v-icon
                                                class="robot-quality-summary__status"
                                                :class="{
                                                    'robot-quality-summary__status--reached': qualityCounterReached(counter),
                                                    'robot-quality-summary__status--missed': !qualityCounterReached(counter),
                                                }"
                                                :icon="qualityCounterReached(counter) ? 'mdi-check-circle' : 'mdi-close-circle'"
                                                size="18" />
                                            <v-checkbox-btn
                                                :model-value="qualitySummaryCheckboxChecked(counter)"
                                                density="compact"
                                                :aria-label="qualityCounterSummaryLabel(counter)"
                                                class="robot-quality-summary__check"
                                                @update:model-value="setQualitySummaryCheckboxChecked(counter, $event)" />
                                        </span>
                                    </span>
                                </span>
                            </div>
                            <div
                                v-if="selectedAdditionalCourses.length"
                                class="robot-quality-summary__item robot-quality-summary__item--static robot-quality-summary__item--additional"
                                :class="{
                                    'robot-quality-summary__item--reached': additionalCoursesAcceptedBySelectedTimetable(),
                                    'robot-quality-summary__item--missed': !additionalCoursesAcceptedBySelectedTimetable(),
                                }">
                                <span class="robot-quality-summary__content">
                                    <span class="robot-quality-summary__label">Zusatzkurse</span>
                                    <span class="robot-quality-summary__meta-row">
                                        <span class="robot-quality-summary__meta">{{ additionalCourseAcceptanceLabel() }}</span>
                                        <span class="robot-quality-summary__controls">
                                            <v-icon
                                                class="robot-quality-summary__status"
                                                :icon="additionalCourseAcceptanceIcon()"
                                                :color="additionalCourseAcceptanceColor()"
                                                size="18" />
                                        </span>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <v-alert
                            v-if="selectedTimetableMissingAdditionalCourses().length"
                            type="error"
                            variant="tonal"
                            density="compact"
                            class="robot-no-result-alert">
                            Nicht alle gewählten Zusatzkurse konnten berücksichtigt werden:
                            {{ selectedTimetableMissingAdditionalCourseLabels() }}.
                        </v-alert>

                        <div
                            v-if="displayedTimetableProblems(selectedRobotTimetable).length"
                            class="robot-generated-timetable__problems robot-problems">
                            <div class="robot-problems__title">Konflikte</div>
                            <ul class="robot-problems__details">
                                <li
                                    v-for="problem in displayedTimetableProblems(selectedRobotTimetable)"
                                    :key="problem">
                                    {{ problemSummary(problem) }}
                                </li>
                            </ul>
                        </div>

                        <div
                            class="robot-generated-grid"
                            :style="{ '--robot-generated-weekdays': robotTimetableWeekdays.length }">
                            <div class="robot-generated-cell robot-generated-cell--header">Std.</div>
                            <div
                                v-for="weekday in robotTimetableWeekdays"
                                :key="`robot-header-${weekday.value}`"
                                class="robot-generated-cell robot-generated-cell--header">
                                {{ weekday.shortTitle }}
                            </div>

                            <template
                                v-for="time in robotTimetableTimes"
                                :key="`robot-time-${time.value}`">
                                <div class="robot-generated-cell robot-generated-cell--time">
                                    <span class="robot-generated-cell__hour">{{ time.hourLabel }}</span>
                                    <span v-if="time.timeFrom" class="robot-generated-cell__time-range">{{ time.timeFrom }}</span>
                                    <span v-if="time.timeUntil" class="robot-generated-cell__time-range">{{ time.timeUntil }}</span>
                                </div>
                                <div
                                    v-for="weekday in robotTimetableWeekdays"
                                    :key="`robot-${weekday.value}-${time.value}`"
                                    class="robot-generated-cell"
                                    :class="robotTimetableCellClasses(weekday.value, time.value)">
                                    <div
                                        v-if="robotTimetableDisplaySlot(weekday.value, time.value)"
                                        class="robot-generated-cell__content">
                                        <div class="robot-generated-cell__code">
                                            <span>{{ generatedSlotTitle(robotTimetableDisplaySlot(weekday.value, time.value)) }}</span>
                                            <sup
                                                v-if="robotTimetableDisplaySlot(weekday.value, time.value).isDistanceLearningCourse"
                                                class="robot-course-fu">
                                                FU
                                            </sup>
                                            <sup
                                                v-if="generatedSlotWeekMarker(robotTimetableDisplaySlot(weekday.value, time.value), selectedRobotTimetable)"
                                                class="robot-course-fu robot-course-week-marker">
                                                {{ generatedSlotWeekMarker(robotTimetableDisplaySlot(weekday.value, time.value), selectedRobotTimetable) }}
                                            </sup>
                                        </div>
                                        <div
                                            v-if="generatedSlotDateLabel(robotTimetableDisplaySlot(weekday.value, time.value))"
                                            class="robot-generated-cell__date">
                                            {{ generatedSlotDateLabel(robotTimetableDisplaySlot(weekday.value, time.value)) }}
                                        </div>
                                        <div
                                            v-if="generatedSlotDetails(robotTimetableDisplaySlot(weekday.value, time.value))"
                                            class="robot-generated-cell__details">
                                            {{ generatedSlotDetails(robotTimetableDisplaySlot(weekday.value, time.value)) }}
                                        </div>
                                        <div
                                            v-if="generatedSlotSameSlotBlocks(robotTimetableDisplaySlot(weekday.value, time.value)).length"
                                            class="robot-generated-cell__same-slots">
                                            <div
                                                v-for="sameSlotBlock in generatedSlotSameSlotBlocks(robotTimetableDisplaySlot(weekday.value, time.value))"
                                                :key="sameSlotBlock.key"
                                                class="robot-generated-cell__same-slot-block">
                                                <div class="robot-generated-cell__code">
                                                    <span>{{ generatedSlotTitle(sameSlotBlock) }}</span>
                                                    <sup v-if="sameSlotBlock.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                                    <sup v-if="generatedSlotWeekMarker(sameSlotBlock, selectedRobotTimetable)" class="robot-course-fu robot-course-week-marker">
                                                        {{ generatedSlotWeekMarker(sameSlotBlock, selectedRobotTimetable) }}
                                                    </sup>
                                                </div>
                                                <div
                                                    v-if="generatedSlotDateLabel(sameSlotBlock)"
                                                    class="robot-generated-cell__date">
                                                    {{ generatedSlotDateLabel(sameSlotBlock) }}
                                                </div>
                                                <div
                                                    v-if="generatedSlotDetails(sameSlotBlock)"
                                                    class="robot-generated-cell__details">
                                                    {{ generatedSlotDetails(sameSlotBlock) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="generatedSlotConflictBlocks(robotTimetableDisplaySlot(weekday.value, time.value)).length"
                                            class="robot-generated-cell__conflicts">
                                            <div
                                                v-for="conflictBlock in generatedSlotConflictBlocks(robotTimetableDisplaySlot(weekday.value, time.value))"
                                                :key="conflictBlock.key"
                                                class="robot-generated-cell__conflict-block">
                                                <div class="robot-generated-cell__code">
                                                    <span>{{ generatedSlotTitle(conflictBlock) }}</span>
                                                    <sup v-if="conflictBlock.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                                    <sup v-if="generatedSlotWeekMarker(conflictBlock, selectedRobotTimetable)" class="robot-course-fu robot-course-week-marker">
                                                        {{ generatedSlotWeekMarker(conflictBlock, selectedRobotTimetable) }}
                                                    </sup>
                                                </div>
                                                <div
                                                    v-if="generatedSlotDetails(conflictBlock)"
                                                    class="robot-generated-cell__details">
                                                    {{ generatedSlotDetails(conflictBlock) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="robotTimetableOccasionalMarkers(weekday.value, time.value).length"
                                        class="robot-generated-cell__occasional-markers">
                                        <span
                                            v-for="marker in robotTimetableOccasionalMarkers(weekday.value, time.value)"
                                            :key="marker.key"
                                            class="robot-generated-cell__occasional-marker"
                                            :class="{ 'robot-generated-cell__occasional-marker--additional': marker.isAdditionalCourse }">
                                            <span>{{ marker.code }}</span>
                                            <sup v-if="marker.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                            <sup v-if="marker.weekMarker" class="robot-course-fu robot-course-week-marker">
                                                {{ marker.weekMarker }}
                                            </sup>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div
                            v-if="selectedRobotTimetable && sameSlotDateOverviewGroups(selectedRobotTimetable).length"
                            class="robot-generated-date-overview">
                            <div class="robot-generated-date-overview__title">Termine in gleichen Zellen</div>
                            <div class="robot-generated-date-overview__groups">
                                <div
                                    v-for="group in sameSlotDateOverviewGroups(selectedRobotTimetable)"
                                    :key="group.key"
                                    class="robot-generated-date-overview__group">
                                    <div class="robot-generated-date-overview__slot">{{ group.title }}</div>
                                    <div class="robot-generated-date-overview__courses">
                                        <div
                                            v-for="course in group.courses"
                                            :key="course.key"
                                            class="robot-generated-date-overview__course">
                                            <div class="robot-generated-date-overview__course-title">
                                                <span>{{ course.title }}</span>
                                                <sup v-if="course.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                                <sup v-if="course.weekMarker" class="robot-course-fu robot-course-week-marker">
                                                    {{ course.weekMarker }}
                                                </sup>
                                                <span
                                                    v-if="course.dateRangeLabel"
                                                    class="robot-generated-date-overview__range">
                                                    {{ course.dateRangeLabel }}
                                                </span>
                                            </div>
                                            <div class="robot-generated-date-overview__dates">
                                                <span
                                                    v-for="dateLabel in course.dateLabels"
                                                    :key="dateLabel"
                                                    class="robot-generated-date-overview__date">
                                                    {{ dateLabel }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="selectedRobotTimetable && displayedOccasionalAppointmentGroups(selectedRobotTimetable).length"
                            class="robot-generated-appointments">
                            <div class="robot-generated-appointments__title">Einzeltermine</div>
                            <div class="robot-generated-appointments__groups">
                                <div
                                    v-for="group in displayedOccasionalAppointmentGroups(selectedRobotTimetable)"
                                    :key="group.key"
                                    class="robot-generated-appointment-group">
                                    <div class="robot-generated-appointment-group__title">
                                        <span>{{ group.title }}</span>
                                        <sup v-if="group.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                        <sup v-if="group.weekMarker" class="robot-course-fu robot-course-week-marker">
                                            {{ group.weekMarker }}
                                        </sup>
                                    </div>
                                    <ul class="robot-generated-appointments__list">
                                        <li
                                            v-for="row in group.rows"
                                            :key="row.key"
                                            class="robot-generated-appointment"
                                            :class="{
                                                'robot-generated-appointment--clear': !row.hasConflict,
                                                'robot-generated-appointment--conflict': row.hasConflict,
                                            }">
                                            <v-icon
                                                :icon="row.hasConflict ? 'mdi-alert-circle-outline' : 'mdi-check-circle-outline'"
                                                :color="row.hasConflict ? 'error' : 'success'"
                                                size="15"
                                                class="robot-generated-appointment__icon" />
                                            <span class="robot-generated-appointment__text">
                                                <span class="robot-generated-appointment__date">
                                                    {{ row.dateTimeLabel }}
                                                </span>
                                                <span
                                                    v-if="row.metaLabel"
                                                    class="robot-generated-appointment__meta">
                                                    {{ row.metaLabel }}
                                                </span>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-dialog v-model="infoDialogOpen" persistent max-width="680">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-information-outline" />
                    Hinweise zum Stundenplan Wizzard
                </v-card-title>
                <v-card-text>
                    <div class="robot-info-dialog">
                        <div class="robot-info-dialog__section">
                            <div class="robot-info-dialog__title">Noten</div>
                            <p>
                                Die Note B bedeutet befreit. Das Modul wurde also angerechnet und gilt für die Planung
                                wie ein positiv abgeschlossenes Modul.
                            </p>
                        </div>

                        <div class="robot-info-dialog__section">
                            <div class="robot-info-dialog__title">Grundregel für Folgemodule</div>
                            <p>
                                In Deutsch, Englisch, Mathematik, Französisch, Latein, Spanisch und Informatik darf ein
                                Modul erst gebucht werden, wenn das Modul zwei Stufen darunter positiv abgeschlossen
                                oder mit B angerechnet wurde. Beispiel: M5 darf erst gebucht werden, wenn M3 positiv
                                oder angerechnet ist.
                            </p>
                        </div>

                        <div class="robot-info-dialog__section">
                            <div class="robot-info-dialog__title">Letzte Module gemeinsam buchen</div>
                            <p>
                                In Englisch und Mathematik dürfen die letzten beiden Module nur gemeinsam gebucht
                                werden, wenn das vorletzte Modul schon einmal besucht wurde. Dafür reicht auch ein
                                negativer Abschluss.
                            </p>
                            <p>
                                In Deutsch gilt diese Regel ebenfalls. Zusätzlich dürfen D7 und D8 gemeinsam gebucht
                                werden, weil diese Module im Kompaktstudium zusammengehören.
                            </p>
                            <p>
                                In Französisch, Latein und Spanisch dürfen die letzten beiden Module gemeinsam gebucht
                                werden, wenn das Modul davor positiv abgeschlossen oder mit B angerechnet wurde.
                            </p>
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="infoDialogOpen = false">Schließen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="selectionDialogOpen" persistent max-width="640">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-pencil-outline" />
                    Auswahl bearbeiten
                </v-card-title>
                <v-card-text>
                    <div class="robot-timetable-grid">
                        <v-select
                            v-model="selectionDraft.semester"
                            :items="semesterOptions"
                            item-title="title"
                            item-value="value"
                            label="Semester"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                        <v-select
                            v-model="selectionDraft.religion"
                            :items="religionOptions"
                            item-title="title"
                            item-value="value"
                            label="Ethik / Religion"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                        <v-select
                            v-model="selectionDraft.language"
                            :items="languageOptions"
                            item-title="title"
                            item-value="value"
                            label="Sprache"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                        <v-select
                            v-model="selectionDraft.branch"
                            :items="branchOptions"
                            item-title="title"
                            item-value="value"
                            label="Zweig"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                        <v-select
                            v-model="selectionDraft.artsSubject"
                            :items="artsSubjectOptions"
                            item-title="title"
                            item-value="value"
                            label="ME / BE"
                            variant="outlined"
                            density="compact"
                            hide-details="auto" />
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeSelectionDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="updateSelection">Aktualisieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="studentDialogOpen" persistent max-width="560">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-account-school-outline" />
                    Student bearbeiten
                </v-card-title>
                <v-card-text>
                    <div class="robot-student-dialog__meta">
                        {{ studentTotalCountLabel }}
                    </div>
                    <v-text-field
                        ref="studentSearchField"
                        v-model="studentSearch"
                        label="Student suchen"
                        variant="outlined"
                        density="compact"
                        :loading="studentOptionsLoading"
                        clearable
                        hide-details="auto"
                        @keydown.enter.prevent="submitStudentSearch" />
                    <div class="robot-student-search-results">
                        <v-btn
                            size="small"
                            variant="tonal"
                            :color="studentSelectionDraft.studentCode === null ? 'primary' : 'secondary'"
                            class="robot-student-search-results__item"
                            block
                            @click="selectStudentDraft(null)">
                            Kein Student
                        </v-btn>
                        <template v-if="studentSearchReady">
                            <v-btn
                                v-for="student in filteredStudentResults"
                                :key="student.student_code"
                                size="small"
                                variant="tonal"
                                :color="String(studentSelectionDraft.studentCode) === String(student.student_code) ? 'primary' : 'secondary'"
                                class="robot-student-search-results__item"
                                block
                                @click="selectStudentDraft(student.student_code)">
                                {{ studentOptionTitle(student) }}
                            </v-btn>
                            <div v-if="!filteredStudentResults.length" class="robot-student-search-results__empty">
                                Keine Schüler gefunden
                            </div>
                        </template>
                        <div v-else class="robot-student-search-results__empty">
                            Mindestens 2 Zeichen eingeben
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeStudentDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="updateStudentSelection">Aktualisieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="constraintsDialogOpen" persistent max-width="760">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-calendar-edit" />
                    Zeitvorgaben bearbeiten
                </v-card-title>
                <v-card-text>
                    <div class="robot-constraint-block">
                        <div class="robot-constraint-block__label">Tage, an denen ich kann</div>
                        <div class="robot-chip-row">
                            <v-chip
                                v-for="weekday in weekdayOptions"
                                :key="`draft-available-weekday-${weekday.value}`"
                                size="small"
                                :color="draftConstraintSelected('availableWeekdays', weekday.value) ? 'success' : 'secondary'"
                                :variant="draftConstraintSelected('availableWeekdays', weekday.value) ? 'flat' : 'tonal'"
                                filter
                                @click="toggleDraftConstraint('availableWeekdays', weekday.value)">
                                {{ weekday.shortTitle }}
                            </v-chip>
                        </div>
                    </div>

                    <div class="robot-constraint-block">
                        <div class="robot-constraint-block__label">Diese Stunden verwenden</div>
                        <div class="robot-chip-row">
                            <v-chip
                                v-for="time in timeOptions"
                                :key="`draft-available-time-${time.value}`"
                                size="small"
                                :color="draftConstraintSelected('availableTimes', time.value) ? 'success' : 'secondary'"
                                :variant="draftConstraintSelected('availableTimes', time.value) ? 'flat' : 'tonal'"
                                filter
                                @click="toggleDraftConstraint('availableTimes', time.value)">
                                {{ time.shortTitle }}
                            </v-chip>
                        </div>
                    </div>

                    <div class="robot-constraint-block">
                        <div class="robot-constraint-block__label">Einzelne Zeiten sperren</div>
                        <div class="robot-time-matrix">
                            <template
                                v-for="weekday in weekdayOptions"
                                :key="`draft-weekday-time-row-${weekday.value}`">
                                <div class="robot-time-matrix__weekday">{{ weekday.shortTitle }}</div>
                                <div class="robot-chip-row robot-chip-row--times">
                                    <v-chip
                                        v-for="time in timeOptions"
                                        :key="`draft-excluded-weekday-time-${weekday.value}-${time.value}`"
                                        size="x-small"
                                        :color="draftWeekdayTimeAvailable(weekday.value, time.value) ? 'success' : 'error'"
                                        variant="flat"
                                        filter
                                        @click="toggleDraftWeekdayTime(weekday.value, time.value)">
                                        {{ time.value }}
                                    </v-chip>
                                </div>
                            </template>
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeConstraintsDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="updateConstraints">Aktualisieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="settingsDialogOpen" persistent max-width="1120" scrollable>
            <EvaluationSettings
                :closable="true"
                @changed="onSettingsChanged"
                @close="settingsDialogOpen = false"
                @saved="onSettingsSaved" />
        </v-dialog>
    </v-col>

    <div
        v-if="embeddedGeneratorVisible"
        class="robot-generator robot-generator--embedded">
        <v-alert
            v-if="fullGreenTimetableCountError"
            type="error"
            variant="tonal"
            class="mb-0">
            {{ fullGreenTimetableCountError }}
        </v-alert>

        <template v-if="timetableCountResultsAvailable">
            <div v-if="false" class="robot-count-cards">
                <div
                    class="robot-count-card"
                    :class="{ 'robot-count-card--selected': !backendVariationCountsAvailable && timetableResultCardSelected('full_green') }">
                    <div class="robot-count-card__content">
                        <div class="robot-count-card__label">
                            {{ backendVariationCountsAvailable ? 'Variationen gesamt' : 'Volle grüne Stundenpläne' }}
                        </div>
                        <div class="robot-count-card__value">
                            <v-progress-circular
                                v-if="fullGreenTimetableCountLoading"
                                indeterminate
                                size="22"
                                width="2"
                                color="primary" />
                            <template v-else>
                                {{ backendVariationCountsAvailable ? totalTimetableVariationCountLabel : fullGreenTimetableCountLabel }}
                            </template>
                        </div>
                    </div>
                    <div v-if="!backendVariationCountsAvailable" class="robot-count-card__actions">
                        <v-switch
                            :model-value="timetableResultCardSelected('full_green')"
                            color="success"
                            inset
                            hide-details
                            density="compact"
                            :disabled="!isTimetableResultTypeSelectable('full_green')"
                            aria-label="Volle grüne Stundenpläne auswählen"
                            @update:modelValue="setSelectedTimetableResultType('full_green', $event)" />
                        <v-icon icon="mdi-check-circle-outline" color="success" />
                        <v-checkbox-btn
                            v-if="timetableResultCardSelected('full_green')"
                            :model-value="timetableResultCardSelected('full_green')"
                            color="success"
                            density="compact"
                            readonly
                            tabindex="-1"
                            aria-label="Volle grüne Stundenpläne ausgewählt"
                            class="robot-count-card__selected-checkbox" />
                    </div>
                </div>

                <div
                    class="robot-count-card robot-count-card--green"
                    :class="{
                        'robot-count-card--selected': backendVariationCountsAvailable ? timetableResultCardSelected('full_green') : timetableResultCardSelected('green'),
                        'robot-count-card--clickable': backendCountCardSelectable('full_green'),
                    }"
                    :role="backendCountCardSelectable('full_green') ? 'button' : null"
                    :tabindex="backendCountCardSelectable('full_green') ? 0 : null"
                    @click="selectBackendCountCard('full_green')"
                    @keydown.enter.prevent="selectBackendCountCard('full_green')"
                    @keydown.space.prevent="selectBackendCountCard('full_green')">
                    <div class="robot-count-card__content">
                        <div class="robot-count-card__label">
                            {{ backendVariationCountsAvailable ? 'Volle grüne Stundenpläne' : 'Grüne Stundenpläne' }}
                        </div>
                        <div class="robot-count-card__value">
                            <v-progress-circular
                                v-if="fullGreenTimetableCountLoading"
                                indeterminate
                                size="22"
                                width="2"
                                color="primary" />
                            <template v-else>
                                {{ backendVariationCountsAvailable ? fullGreenTimetableCountLabel : greenTimetableCountLabel }}
                            </template>
                        </div>
                    </div>
                    <div v-if="!backendVariationCountsAvailable" class="robot-count-card__actions">
                        <v-switch
                            :model-value="timetableResultCardSelected('green')"
                            color="primary"
                            inset
                            hide-details
                            density="compact"
                            :disabled="!isTimetableResultTypeSelectable('green')"
                            aria-label="Grüne Stundenpläne auswählen"
                            @update:modelValue="setSelectedTimetableResultType('green', $event)" />
                        <v-icon icon="mdi-calendar-check-outline" color="primary" />
                        <v-checkbox-btn
                            v-if="timetableResultCardSelected('green')"
                            :model-value="timetableResultCardSelected('green')"
                            color="primary"
                            density="compact"
                            readonly
                            tabindex="-1"
                            aria-label="Grüne Stundenpläne ausgewählt"
                            class="robot-count-card__selected-checkbox" />
                    </div>
                    <div
                        v-else-if="backendCountCardSelectable('full_green') || timetableResultCardSelected('full_green')"
                        class="robot-count-card__actions">
                        <v-checkbox-btn
                            :model-value="timetableResultCardSelected('full_green')"
                            color="success"
                            density="compact"
                            readonly
                            tabindex="-1"
                            :aria-label="timetableResultCardSelected('full_green') ? 'Volle grüne Stundenpläne ausgewählt' : 'Volle grüne Stundenpläne auswählbar'"
                            class="robot-count-card__selected-checkbox" />
                    </div>
                </div>

                <div
                    v-if="backendVariationCountsAvailable"
                    class="robot-count-card robot-count-card--green"
                    :class="{
                        'robot-count-card--selected': timetableResultCardSelected('green'),
                        'robot-count-card--clickable': backendCountCardSelectable('green'),
                    }"
                    :role="backendCountCardSelectable('green') ? 'button' : null"
                    :tabindex="backendCountCardSelectable('green') ? 0 : null"
                    @click="selectBackendCountCard('green')"
                    @keydown.enter.prevent="selectBackendCountCard('green')"
                    @keydown.space.prevent="selectBackendCountCard('green')">
                    <div class="robot-count-card__content">
                        <div class="robot-count-card__label">Grüne Stundenpläne</div>
                        <div class="robot-count-card__value">
                            <v-progress-circular
                                v-if="fullGreenTimetableCountLoading"
                                indeterminate
                                size="22"
                                width="2"
                                color="primary" />
                            <template v-else>
                                {{ greenTimetableCountLabel }}
                            </template>
                        </div>
                    </div>
                    <div
                        v-if="backendCountCardSelectable('green') || timetableResultCardSelected('green')"
                        class="robot-count-card__actions">
                        <v-checkbox-btn
                            :model-value="timetableResultCardSelected('green')"
                            color="primary"
                            density="compact"
                            readonly
                            tabindex="-1"
                            :aria-label="timetableResultCardSelected('green') ? 'Grüne Stundenpläne ausgewählt' : 'Grüne Stundenpläne auswählbar'"
                            class="robot-count-card__selected-checkbox" />
                    </div>
                </div>

                <div
                    v-if="additionalCourseTimetableCountCardVisible"
                    class="robot-count-card robot-count-card--additional"
                    :class="{
                        'robot-count-card--selected': additionalCourseTimetableRequired,
                        'robot-count-card--clickable': isAdditionalCourseTimetableFilterSelectable(),
                    }"
                    :role="isAdditionalCourseTimetableFilterSelectable() ? 'button' : null"
                    :tabindex="isAdditionalCourseTimetableFilterSelectable() ? 0 : null"
                    @click="setAdditionalCourseTimetableRequired(true)"
                    @keydown.enter.prevent="setAdditionalCourseTimetableRequired(true)"
                    @keydown.space.prevent="setAdditionalCourseTimetableRequired(true)">
                    <div class="robot-count-card__content">
                        <div class="robot-count-card__label">{{ additionalCourseTimetableCountCardTitle }}</div>
                        <div class="robot-count-card__meta">inkl. Zusätzliche Stunden</div>
                        <div class="robot-count-card__value">
                            <v-progress-circular
                                v-if="fullGreenTimetableCountLoading"
                                indeterminate
                                size="22"
                                width="2"
                                color="primary" />
                            <template v-else>
                                {{ additionalCourseTimetableCountCardValue }}
                            </template>
                        </div>
                    </div>
                    <div
                        v-if="isAdditionalCourseTimetableFilterSelectable() || additionalCourseTimetableRequired"
                        class="robot-count-card__actions">
                        <v-checkbox-btn
                            :model-value="additionalCourseTimetableRequired"
                            color="deep-orange"
                            density="compact"
                            readonly
                            tabindex="-1"
                            :aria-label="additionalCourseTimetableRequired ? 'Stundenpläne mit Zusatzkursen ausgewählt' : 'Stundenpläne mit Zusatzkursen auswählbar'"
                            class="robot-count-card__selected-checkbox" />
                    </div>
                </div>

                <div
                    v-if="backendVariationCountsAvailable || showConflictTimetableResults"
                    class="robot-count-card robot-count-card--conflict"
                    :class="{
                        'robot-count-card--selected': timetableResultCardSelected('conflict'),
                        'robot-count-card--clickable': backendCountCardSelectable('conflict'),
                    }"
                    :role="backendCountCardSelectable('conflict') ? 'button' : null"
                    :tabindex="backendCountCardSelectable('conflict') ? 0 : null"
                    @click="selectBackendCountCard('conflict')"
                    @keydown.enter.prevent="selectBackendCountCard('conflict')"
                    @keydown.space.prevent="selectBackendCountCard('conflict')">
                    <div class="robot-count-card__content">
                        <div class="robot-count-card__label">
                            {{ backendVariationCountsAvailable ? 'Rote Stundenpläne' : 'Stundenpläne mit Konflikten' }}
                        </div>
                        <div class="robot-count-card__value">
                            <v-progress-circular
                                v-if="fullGreenTimetableCountLoading"
                                indeterminate
                                size="22"
                                width="2"
                                color="primary" />
                            <template v-else>
                                {{ conflictTimetableCountLabel }}
                            </template>
                        </div>
                    </div>
                    <div v-if="!backendVariationCountsAvailable" class="robot-count-card__actions">
                        <v-switch
                            :model-value="timetableResultCardSelected('conflict')"
                            color="warning"
                            inset
                            hide-details
                            density="compact"
                            :disabled="!isTimetableResultTypeSelectable('conflict')"
                            aria-label="Stundenpläne mit Konflikten auswählen"
                            @update:modelValue="setSelectedTimetableResultType('conflict', $event)" />
                        <v-icon icon="mdi-alert-circle-outline" color="warning" />
                        <v-checkbox-btn
                            v-if="timetableResultCardSelected('conflict')"
                            :model-value="timetableResultCardSelected('conflict')"
                            color="warning"
                            density="compact"
                            readonly
                            tabindex="-1"
                            aria-label="Rote Stundenpläne ausgewählt"
                            class="robot-count-card__selected-checkbox" />
                    </div>
                    <div
                        v-else-if="backendCountCardSelectable('conflict') || timetableResultCardSelected('conflict')"
                        class="robot-count-card__actions">
                        <v-checkbox-btn
                            :model-value="timetableResultCardSelected('conflict')"
                            color="warning"
                            density="compact"
                            readonly
                            tabindex="-1"
                            :aria-label="timetableResultCardSelected('conflict') ? 'Rote Stundenpläne ausgewählt' : 'Rote Stundenpläne auswählbar'"
                            class="robot-count-card__selected-checkbox" />
                    </div>
                </div>

            </div>

            <v-alert
                v-if="!backendVariationCountsAvailable && selectedOptionsNoResultAlertVisible"
                type="info"
                variant="tonal"
                density="compact"
                class="robot-no-result-alert">
                <div class="robot-no-result-alert__title">
                    Keine passenden Stundenpläne für die aktuelle Auswahl.
                </div>
                <ul
                    v-if="selectedOptionsNoResultReasons.length"
                    class="robot-no-result-alert__reasons">
                    <li
                        v-for="reason in selectedOptionsNoResultReasons"
                        :key="reason">
                        {{ reason }}
                    </li>
                </ul>
            </v-alert>

            <div v-if="!backendVariationCountsAvailable && !selectedRobotTimetable" class="robot-quality-card">
                <div class="robot-quality-card__header">
                    <div>
                        <div class="robot-quality-card__title">Qualitätskriterien</div>
                        <div class="robot-quality-card__meta">
                            Erreichte aktive Bewertungskriterien
                        </div>
                    </div>
                    <v-icon icon="mdi-chart-box-outline" color="primary" />
                </div>

                <div v-if="qualityCriterionRows.length" class="robot-quality-card__items">
                    <div class="robot-quality-card__item robot-quality-card__item--summary">
                        <div class="robot-quality-card__item-copy">
                            <div class="robot-quality-card__item-label">
                                Alle Qualitätskriterien erfüllt
                            </div>
                            <div class="robot-quality-card__item-meta">
                                {{ allQualityCriteriaCountDetail() }}
                            </div>
                        </div>
                        <div class="robot-quality-card__item-count">
                            {{ allQualityCriteriaCountLabel() }}
                        </div>
                    </div>

                    <div
                        v-for="(counter, counterIndex) in qualityCriterionRows"
                        :key="`embedded-quality-${counter.key}`"
                        class="robot-quality-card__item">
                        <div class="robot-quality-card__item-copy">
                            <div class="robot-quality-card__item-label">
                                {{ counterIndex + 1 }}. {{ counter.label }}
                            </div>
                            <div class="robot-quality-card__item-meta">
                                {{ qualityCounterDetail(counter) }}
                            </div>
                        </div>
                        <v-checkbox-btn
                            :model-value="counter.enabled === true"
                            color="primary"
                            density="compact"
                            :disabled="fullGreenTimetableCountLoading"
                            :aria-label="`${counter.label} für diese Berechnung verwenden`"
                            class="robot-quality-card__item-check"
                            @update:model-value="setEvaluationCriterionEnabled(counter, $event)" />
                        <div class="robot-quality-card__item-count">
                            {{ qualityCounterCountLabel(counter) }}
                        </div>
                    </div>
                </div>

                <v-alert v-else type="info" variant="tonal" density="compact" class="mb-0">
                    Keine aktiven Bewertungskriterien gespeichert.
                </v-alert>
            </div>
        </template>

        <div v-if="generatedTimetableDisplayVisible" class="robot-generated">
            <div class="robot-course-list__header robot-generated-header">
                <div class="robot-generated-header__actions">
                    <v-btn
                        v-if="generatedTimetableActionButtonsVisible"
                        class="robot-generated-overtake-button"
                        color="success"
                        variant="flat"
                        size="large"
                        prepend-icon="mdi-calendar-clock"
                        :disabled="timetableGenerationLoading || !selectedRobotTimetableCourseGroupKeys().length"
                        @click="overtakeSelectedTimetableToOverview">
                        Übernehmen
                    </v-btn>
                    <v-btn
                        v-if="generatedTimetableActionButtonsVisible"
                        class="robot-generated-back-button"
                        color="grey-darken-1"
                        variant="tonal"
                        size="large"
                        prepend-icon="mdi-arrow-left"
                        :disabled="timetableGenerationLoading"
                        @click="returnToCourseSelectionFromGeneratedTimetable">
                        Zurück
                    </v-btn>
                    <div
                        v-if="selectedTimetableResultCount > 0"
                        class="robot-timetable-selector">
                        <v-btn
                            icon="mdi-chevron-left"
                            size="small"
                            variant="tonal"
                            color="primary"
                            :disabled="timetableGenerationLoading || timetableResultCounter(selectedTimetableResultType) <= 1"
                            :aria-label="`Vorheriger ${selectedTimetableResultTitle}`"
                            @click="moveTimetableResultCounter(selectedTimetableResultType, -1)" />
                        <div class="robot-timetable-selector__value" aria-live="polite">
                            {{ timetableResultCounter(selectedTimetableResultType) }} / {{ selectedTimetableResultCount }}
                        </div>
                        <v-btn
                            icon="mdi-chevron-right"
                            size="small"
                            variant="tonal"
                            color="primary"
                            :disabled="timetableGenerationLoading || timetableResultCounter(selectedTimetableResultType) >= selectedTimetableResultCount"
                            :aria-label="`Nächster ${selectedTimetableResultTitle}`"
                            @click="moveTimetableResultCounter(selectedTimetableResultType, 1)" />
                        <v-chip
                            size="small"
                            :color="selectedTimetableResultStatusColor"
                            variant="tonal"
                            class="robot-timetable-selector__status">
                            {{ selectedTimetableResultStatusLabel }}
                        </v-chip>
                    </div>
                </div>
            </div>

            <div v-if="false" class="robot-generated-criteria-count">
                <div
                    class="robot-count-card robot-count-card--green robot-count-card--criteria"
                    :class="{
                        'robot-count-card--selected': qualityCriteriaResultFilterActive,
                        'robot-count-card--clickable': qualityCriteriaResultFilterAvailable(),
                    }"
                    :role="qualityCriteriaResultFilterAvailable() ? 'button' : null"
                    :tabindex="qualityCriteriaResultFilterAvailable() ? 0 : null"
                    @click="toggleQualityCriteriaResultFilter"
                    @keydown.enter.prevent="toggleQualityCriteriaResultFilter"
                    @keydown.space.prevent="toggleQualityCriteriaResultFilter">
                    <div class="robot-count-card__content">
                        <div class="robot-count-card__label">Stundenpläne mit Kriterien</div>
                        <div class="robot-count-card__value">{{ selectedCriteriaTimetableCountLabel }}</div>
                    </div>
                    <v-checkbox-btn
                        :model-value="qualityCriteriaResultFilterActive"
                        color="primary"
                        density="compact"
                        :disabled="!qualityCriteriaResultFilterAvailable() || fullGreenTimetableCountLoading || timetableGenerationLoading"
                        readonly
                        tabindex="-1"
                        :aria-label="qualityCriteriaResultFilterActive ? 'Stundenpläne mit Kriterien ausgewählt' : 'Stundenpläne mit Kriterien auswählbar'"
                        class="robot-count-card__check" />
                </div>
            </div>

            <div
                v-if="activeQualityCriterionRows.length || selectedAdditionalCourses.length"
                class="robot-quality-summary">
                <div
                    v-for="counter in activeQualityCriterionRows"
                    :key="`embedded-quality-summary-${counter.key}`"
                    class="robot-quality-summary__item"
                    :class="{
                        'robot-quality-summary__item--selected': qualitySummaryCheckboxChecked(counter),
                        'robot-quality-summary__item--reached': qualitySummaryCheckboxChecked(counter) && qualityCounterReached(counter),
                        'robot-quality-summary__item--missed': !qualityCounterReached(counter),
                    }">
                    <span class="robot-quality-summary__content">
                        <span class="robot-quality-summary__label">{{ counter.label }}</span>
                        <span class="robot-quality-summary__meta-row">
                            <span class="robot-quality-summary__meta">
                                {{ qualityCounterCardMeta(counter) }}
                            </span>
                            <span class="robot-quality-summary__controls">
                                <v-icon
                                    class="robot-quality-summary__status"
                                    :class="{
                                        'robot-quality-summary__status--reached': qualityCounterReached(counter),
                                        'robot-quality-summary__status--missed': !qualityCounterReached(counter),
                                    }"
                                    :icon="qualityCounterReached(counter) ? 'mdi-check-circle' : 'mdi-close-circle'"
                                    size="18" />
                                <v-checkbox-btn
                                    :model-value="qualitySummaryCheckboxChecked(counter)"
                                    density="compact"
                                    :aria-label="qualityCounterSummaryLabel(counter)"
                                    class="robot-quality-summary__check"
                                    @update:model-value="setQualitySummaryCheckboxChecked(counter, $event)" />
                            </span>
                        </span>
                    </span>
                </div>
                <div
                    v-if="selectedAdditionalCourses.length"
                    class="robot-quality-summary__item robot-quality-summary__item--static robot-quality-summary__item--additional"
                    :class="{
                        'robot-quality-summary__item--reached': additionalCoursesAcceptedBySelectedTimetable(),
                        'robot-quality-summary__item--missed': !additionalCoursesAcceptedBySelectedTimetable(),
                    }">
                    <span class="robot-quality-summary__content">
                        <span class="robot-quality-summary__label">Zusatzkurse</span>
                        <span class="robot-quality-summary__meta-row">
                            <span class="robot-quality-summary__meta">{{ additionalCourseAcceptanceLabel() }}</span>
                            <span class="robot-quality-summary__controls">
                                <v-icon
                                    class="robot-quality-summary__status"
                                    :icon="additionalCourseAcceptanceIcon()"
                                    :color="additionalCourseAcceptanceColor()"
                                    size="18" />
                            </span>
                        </span>
                    </span>
                </div>
            </div>

            <v-alert
                v-if="selectedTimetableMissingAdditionalCourses().length"
                type="error"
                variant="tonal"
                density="compact"
                class="robot-no-result-alert">
                Nicht alle gewählten Zusatzkurse konnten berücksichtigt werden:
                {{ selectedTimetableMissingAdditionalCourseLabels() }}.
            </v-alert>

            <div
                v-if="displayedTimetableProblems(selectedRobotTimetable).length"
                class="robot-generated-timetable__problems robot-problems">
                <div class="robot-problems__title">Konflikte</div>
                <ul class="robot-problems__details">
                    <li
                        v-for="problem in displayedTimetableProblems(selectedRobotTimetable)"
                        :key="problem">
                        {{ problemSummary(problem) }}
                    </li>
                </ul>
            </div>

            <div
                class="robot-generated-grid"
                :style="{ '--robot-generated-weekdays': robotTimetableWeekdays.length }">
                <div class="robot-generated-cell robot-generated-cell--header">Std.</div>
                <div
                    v-for="weekday in robotTimetableWeekdays"
                    :key="`embedded-robot-header-${weekday.value}`"
                    class="robot-generated-cell robot-generated-cell--header">
                    {{ weekday.shortTitle }}
                </div>

                <template
                    v-for="time in robotTimetableTimes"
                    :key="`embedded-robot-time-${time.value}`">
                    <div class="robot-generated-cell robot-generated-cell--time">
                        <span class="robot-generated-cell__hour">{{ time.hourLabel }}</span>
                        <span v-if="time.timeFrom" class="robot-generated-cell__time-range">{{ time.timeFrom }}</span>
                        <span v-if="time.timeUntil" class="robot-generated-cell__time-range">{{ time.timeUntil }}</span>
                    </div>
                    <div
                        v-for="weekday in robotTimetableWeekdays"
                        :key="`embedded-robot-${weekday.value}-${time.value}`"
                        class="robot-generated-cell"
                        :class="robotTimetableCellClasses(weekday.value, time.value)">
                        <div
                            v-if="robotTimetableDisplaySlot(weekday.value, time.value)"
                            class="robot-generated-cell__content">
                            <div class="robot-generated-cell__code">
                                <span>{{ generatedSlotTitle(robotTimetableDisplaySlot(weekday.value, time.value)) }}</span>
                                <sup
                                    v-if="robotTimetableDisplaySlot(weekday.value, time.value).isDistanceLearningCourse"
                                    class="robot-course-fu">
                                    FU
                                </sup>
                                <sup
                                    v-if="generatedSlotWeekMarker(robotTimetableDisplaySlot(weekday.value, time.value), selectedRobotTimetable)"
                                    class="robot-course-fu robot-course-week-marker">
                                    {{ generatedSlotWeekMarker(robotTimetableDisplaySlot(weekday.value, time.value), selectedRobotTimetable) }}
                                </sup>
                            </div>
                            <div
                                v-if="generatedSlotDateLabel(robotTimetableDisplaySlot(weekday.value, time.value))"
                                class="robot-generated-cell__date">
                                {{ generatedSlotDateLabel(robotTimetableDisplaySlot(weekday.value, time.value)) }}
                            </div>
                            <div
                                v-if="generatedSlotDetails(robotTimetableDisplaySlot(weekday.value, time.value))"
                                class="robot-generated-cell__details">
                                {{ generatedSlotDetails(robotTimetableDisplaySlot(weekday.value, time.value)) }}
                            </div>
                            <div
                                v-if="generatedSlotSameSlotBlocks(robotTimetableDisplaySlot(weekday.value, time.value)).length"
                                class="robot-generated-cell__same-slots">
                                <div
                                    v-for="sameSlotBlock in generatedSlotSameSlotBlocks(robotTimetableDisplaySlot(weekday.value, time.value))"
                                    :key="sameSlotBlock.key"
                                    class="robot-generated-cell__same-slot-block">
                                    <div class="robot-generated-cell__code">
                                        <span>{{ generatedSlotTitle(sameSlotBlock) }}</span>
                                        <sup v-if="sameSlotBlock.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                        <sup v-if="generatedSlotWeekMarker(sameSlotBlock, selectedRobotTimetable)" class="robot-course-fu robot-course-week-marker">
                                            {{ generatedSlotWeekMarker(sameSlotBlock, selectedRobotTimetable) }}
                                        </sup>
                                    </div>
                                    <div
                                        v-if="generatedSlotDateLabel(sameSlotBlock)"
                                        class="robot-generated-cell__date">
                                        {{ generatedSlotDateLabel(sameSlotBlock) }}
                                    </div>
                                    <div
                                        v-if="generatedSlotDetails(sameSlotBlock)"
                                        class="robot-generated-cell__details">
                                        {{ generatedSlotDetails(sameSlotBlock) }}
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="generatedSlotConflictBlocks(robotTimetableDisplaySlot(weekday.value, time.value)).length"
                                class="robot-generated-cell__conflicts">
                                <div
                                    v-for="conflictBlock in generatedSlotConflictBlocks(robotTimetableDisplaySlot(weekday.value, time.value))"
                                    :key="conflictBlock.key"
                                    class="robot-generated-cell__conflict-block">
                                    <div class="robot-generated-cell__code">
                                        <span>{{ generatedSlotTitle(conflictBlock) }}</span>
                                        <sup v-if="conflictBlock.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                        <sup v-if="generatedSlotWeekMarker(conflictBlock, selectedRobotTimetable)" class="robot-course-fu robot-course-week-marker">
                                            {{ generatedSlotWeekMarker(conflictBlock, selectedRobotTimetable) }}
                                        </sup>
                                    </div>
                                    <div
                                        v-if="generatedSlotDetails(conflictBlock)"
                                        class="robot-generated-cell__details">
                                        {{ generatedSlotDetails(conflictBlock) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="robotTimetableOccasionalMarkers(weekday.value, time.value).length"
                            class="robot-generated-cell__occasional-markers">
                            <span
                                v-for="marker in robotTimetableOccasionalMarkers(weekday.value, time.value)"
                                :key="marker.key"
                                class="robot-generated-cell__occasional-marker"
                                :class="{ 'robot-generated-cell__occasional-marker--additional': marker.isAdditionalCourse }">
                                <span>{{ marker.code }}</span>
                                <sup v-if="marker.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                <sup v-if="marker.weekMarker" class="robot-course-fu robot-course-week-marker">
                                    {{ marker.weekMarker }}
                                </sup>
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <div
                v-if="selectedRobotTimetable && sameSlotDateOverviewGroups(selectedRobotTimetable).length"
                class="robot-generated-date-overview">
                <div class="robot-generated-date-overview__title">Termine in gleichen Zellen</div>
                <div class="robot-generated-date-overview__groups">
                    <div
                        v-for="group in sameSlotDateOverviewGroups(selectedRobotTimetable)"
                        :key="`embedded-date-overview-${group.key}`"
                        class="robot-generated-date-overview__group">
                        <div class="robot-generated-date-overview__slot">{{ group.title }}</div>
                        <div class="robot-generated-date-overview__courses">
                            <div
                                v-for="course in group.courses"
                                :key="course.key"
                                class="robot-generated-date-overview__course">
                                <div class="robot-generated-date-overview__course-title">
                                    <span>{{ course.title }}</span>
                                    <sup v-if="course.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                    <sup v-if="course.weekMarker" class="robot-course-fu robot-course-week-marker">
                                        {{ course.weekMarker }}
                                    </sup>
                                    <span
                                        v-if="course.dateRangeLabel"
                                        class="robot-generated-date-overview__range">
                                        {{ course.dateRangeLabel }}
                                    </span>
                                </div>
                                <div class="robot-generated-date-overview__dates">
                                    <span
                                        v-for="dateLabel in course.dateLabels"
                                        :key="dateLabel"
                                        class="robot-generated-date-overview__date">
                                        {{ dateLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="selectedRobotTimetable && displayedOccasionalAppointmentGroups(selectedRobotTimetable).length"
                class="robot-generated-appointments">
                <div class="robot-generated-appointments__title">Einzeltermine</div>
                <div class="robot-generated-appointments__groups">
                    <div
                        v-for="group in displayedOccasionalAppointmentGroups(selectedRobotTimetable)"
                        :key="`embedded-appointment-${group.key}`"
                        class="robot-generated-appointment-group">
                        <div class="robot-generated-appointment-group__title">
                            <span>{{ group.title }}</span>
                            <sup v-if="group.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                            <sup v-if="group.weekMarker" class="robot-course-fu robot-course-week-marker">
                                {{ group.weekMarker }}
                            </sup>
                        </div>
                        <ul class="robot-generated-appointments__list">
                            <li
                                v-for="row in group.rows"
                                :key="row.key"
                                class="robot-generated-appointment"
                                :class="{
                                    'robot-generated-appointment--clear': !row.hasConflict,
                                    'robot-generated-appointment--conflict': row.hasConflict,
                                }">
                                <v-icon
                                    :icon="row.hasConflict ? 'mdi-alert-circle-outline' : 'mdi-check-circle-outline'"
                                    :color="row.hasConflict ? 'error' : 'success'"
                                    size="15"
                                    class="robot-generated-appointment__icon" />
                                <span class="robot-generated-appointment__text">
                                    <span class="robot-generated-appointment__date">
                                        {{ row.dateTimeLabel }}
                                    </span>
                                    <span
                                        v-if="row.metaLabel"
                                        class="robot-generated-appointment__meta">
                                        {{ row.metaLabel }}
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import EvaluationSettings from '../evaluationSettings/EvaluationSettings.vue'

const ROBOT_TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:robot:last-settings'
const OVERVIEW_TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:overview:last-timetable'
const OVERVIEW_TIMETABLE_PATH = '/admin/students-timetables/timetable/overview'
const ALL_DATES_OPTION_VALUE = 'all_dates'

export default {
    components: { EvaluationSettings },
    emits: ['generated-timetable-visibility-change', 'timetable-overtaken'],
    props: {
        embeddedCourseCardsOnly: {
            type: Boolean,
            default: false,
        },
        studentCode: {
            type: [String, Number],
            default: null,
        },
        evaluationCriteriaSettings: {
            type: Array,
            default: null,
        },
    },
    data() {
        return {
            loading: false,
            error: '',
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            totalTimetableVariationCount: null,
            fullGreenTimetableCount: null,
            greenTimetableCount: null,
            conflictTimetableCount: null,
            additionalCourseTimetableCount: null,
            qualityCounters: [],
            qualitySummaryCheckedKeys: [],
            allQualityCriteriaCount: null,
            selectedQualityCriteriaCount: null,
            qualityCriteriaResultFilterEnabled: false,
            evaluationCriteria: [],
            evaluationCriteriaLoaded: false,
            fullGreenTimetableNumber: 1,
            greenTimetableNumber: 1,
            conflictTimetableNumber: 1,
            fullGreenTimetableCountError: '',
            fullGreenTimetableCountLoading: false,
            qualityCountersLoading: false,
            timetableCreateLoading: false,
            fullGreenTimetableCountRequestId: 0,
            qualityCountersRequestId: 0,
            selectedTimetableResultType: 'full_green',
            restoredTimetableResultType: null,
            restoredQualityCriteriaFilterEnabled: false,
            restoredQualitySummaryCheckedKeys: [],
            additionalCourseTimetableRequired: false,
            additionalCourseExtensionActionHidden: false,
            courseActionHiddenUntilCourseInteraction: false,
            additionalCoursePanelRetained: false,
            additionalCourseSelectionChangedAfterTimetable: false,
            embeddedCourseSelectionExpanded: true,
            embeddedAdditionalCourseSelectionExpanded: true,
            courseItemPanels: [],
            schoolHours: [],
            courseGroups: [],
            deselectedCourseKeys: [],
            deselectedCourseGroupKeys: [],
            additionalCourseSelectedKeys: [],
            subjectMappings: [],
            subjectRows: [],
            infoDialogOpen: false,
            settingsDialogOpen: false,
            selectionDialogOpen: false,
            studentDialogOpen: false,
            constraintsDialogOpen: false,
            robotStateRestoring: false,
            studentOptionsLoading: false,
            studentSearch: '',
            robotStudents: [],
            studentCompletedCourses: [],
            studentCompletedCoursesLoading: false,
            studentCompletedCoursesError: '',
            studentCompletedCoursesRequestId: 0,
            studentCompletedCoursesExpanded: false,
            studentSelectionDefaultsPendingCode: null,
            selection: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            selectionDraft: {
                semester: 1,
                religion: 'ETH',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
                language: 'L',
            },
            studentSelection: {
                studentCode: null,
            },
            studentSelectionDraft: {
                studentCode: null,
            },
            constraints: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            },
            constraintsDraft: {
                availableWeekdays: [1, 2, 3, 4, 5, 6],
                excludedWeekdayTimes: [],
                availableTimes: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            },
        }
    },
    async mounted() {
        await this.ensureRobotConfigLoaded()
        this.loadSettings()
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_schoolyear']),
        activeEvaluationCriteria() {
            return this.evaluationCriteria
                .filter(criterion => criterion.enabled)
                .map(criterion => ({
                    key: criterion.key,
                    label: criterion.label,
                    optionLabel: criterion.option && criterion.options.length
                        ? (criterion.options.find(o => o.value === criterion.option)?.label || null)
                        : null,
                }))
        },
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
        timetableGenerationLoading() {
            return this.timetableCreateLoading || this.fullGreenTimetableCountLoading
        },
        courseCardsReady() {
            return this.embeddedCourseCardsOnly
                && !this.loading
                && this.availableCourses.length > 0
        },
        courseCardsLoading() {
            return this.embeddedCourseCardsOnly
                && this.loading
        },
        embeddedCourseSelectionLocked() {
            return this.embeddedCourseCardsOnly && Boolean(this.selectedRobotTimetable)
        },
        embeddedGeneratorVisible() {
            return this.embeddedCourseCardsOnly
                && (
                    Boolean(this.fullGreenTimetableCountError)
                    || (
                        this.additionalCourseSelectionChangedAfterTimetable !== true
                        && (this.timetableCountResultsAvailable || Boolean(this.selectedRobotTimetable))
                    )
                )
        },
        timeOptions() {
            const configuredHours = Array.isArray(this.schoolHours) ? this.schoolHours : []

            if (!configuredHours.length) return this.defaultTimeOptions()

            return configuredHours
                .map(schoolHour => ({
                    title: this.timeOptionTitle(schoolHour),
                    shortTitle: this.timeOptionShortTitle(schoolHour),
                    hourLabel: `${Number(schoolHour.hour)}.`,
                    timeFrom: this.formatTimeValue(schoolHour?.from),
                    timeUntil: this.formatTimeValue(schoolHour?.until),
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
                { key: 'semester', label: 'Semester', value: this.selectedOptionTitle(this.semesterOptions, this.selection.semester) },
                { key: 'religion', label: 'Ethik / Religion', value: this.selectedOptionTitle(this.religionOptions, this.selection.religion) },
                { key: 'language', label: 'Sprache', value: this.selectedOptionTitle(this.languageOptions, this.selection.language) },
                { key: 'branch', label: 'Zweig', value: this.selectedOptionTitle(this.branchOptions, this.selection.branch) },
                { key: 'artsSubject', label: 'ME / BE', value: this.selectedOptionTitle(this.artsSubjectOptions, this.selection.artsSubject) },
            ]
        },
        studentSearchReady() {
            return this.normalizedStudentSearch.length >= 2
        },
        normalizedStudentSearch() {
            return String(this.studentSearch || '').trim().toLowerCase()
        },
        filteredStudentResults() {
            if (!this.studentSearchReady) return []

            return this.robotStudents
                .filter(student => this.studentOptionTitle(student).toLowerCase().includes(this.normalizedStudentSearch))
        },
        selectedStudent() {
            const studentCode = this.normalizedStudentCode(this.studentSelection.studentCode)
            if (!studentCode) return null

            return this.robotStudents.find(student => String(student.student_code) === studentCode) || null
        },
        selectedStudentLabel() {
            return this.selectedStudent ? this.studentOptionTitle(this.selectedStudent) : 'Kein Student'
        },
        selectedStudentPlanningSemester() {
            return this.selectedStudentPlanningSemesterValue()
        },
        studentPlannedCourses() {
            const semester = this.selectedStudentPlanningSemesterValue()
            if (!semester) return []

            const completedCourseCodes = this.studentCompletedCourseCodes()

            return this.studentPlannedCoursesForSemester(semester, completedCourseCodes)
        },
        studentMissingCourses() {
            const semester = this.selectedStudentPlanningSemesterValue()
            if (!semester) return []

            const completedCourseCodes = this.studentCompletedCourseCodes()
            const visitedCourseCodes = this.studentVisitedCourseCodes()

            return this.pendingStudentCoursesBeforeSemester(semester, completedCourseCodes, visitedCourseCodes)
        },
        studentAdditionalCourses() {
            const semester = this.selectedStudentPlanningSemesterValue()
            if (!semester) return []

            const completedCourseCodes = this.studentCompletedCourseCodes()
            const visitedCourseCodes = this.studentVisitedCourseCodes()
            const missingCourses = this.pendingStudentCoursesBeforeSemester(semester, completedCourseCodes, visitedCourseCodes)
            const plannedCourses = this.studentPlannedCoursesForSemester(semester, completedCourseCodes)
            const plannedCourseCodes = this.studentPlannedCourseCodes(plannedCourses)
            const regularCourseCodes = this.studentPlannedCourseCodes(this.availableCourses)
            const unavailableCourseCodes = this.studentUnavailableAdditionalCourseCodes(completedCourseCodes, [
                ...missingCourses,
                ...plannedCourses,
            ])

            return this.coursesAfterSemester(semester)
                .filter(course => !this.courseCompletedForStudentPlanning(course, unavailableCourseCodes))
                .filter(course => !this.courseCompletedForStudentPlanning(course, regularCourseCodes))
                .filter(course => this.coursePossibleAsStudentAdditional(course, completedCourseCodes, visitedCourseCodes, plannedCourseCodes))
        },
        studentTotalCountLabel() {
            const count = Array.isArray(this.robotStudents) ? this.robotStudents.length : 0

            return `${this.formatNumber(count)} Studenten gesamt`
        },
        selectedConstraintSummary() {
            return [
                {
                    key: 'unavailableWeekdays',
                    label: 'Zeitliche Einschränkungen',
                    value: this.selectedOptionTitles(this.weekdayOptions, this.unavailableWeekdays, ', '),
                },
                {
                    key: 'excludedWeekdayTimes',
                    label: 'Keine Zeiten',
                    value: this.selectedOptionTitles(this.weekdayTimeOptions, this.constraints.excludedWeekdayTimes),
                },
                {
                    key: 'unavailableTimes',
                    label: 'Nicht verwendete Stunden',
                    value: this.selectedTimeOptionTitles(this.timeOptions, this.unavailableTimes),
                },
            ].filter(item => item.value)
        },
        displayedConstraintSummary() {
            if (this.selectedConstraintSummary.length) return this.selectedConstraintSummary

            return [{
                key: 'none',
                label: 'Zeitliche Einschränkungen',
                value: 'Keine Einschränkungen',
            }]
        },
        regularCourseListTitle() {
            return this.selectedStudent ? 'Fehlende Kurse + Vorgesehene Kurse' : 'Vorgesehene Kurse'
        },
        regularCourseCountLabel() {
            return this.selectedStudent
                ? `${this.selectedCourses.length} / ${this.availableCourses.length}`
                : this.availableCourses.length
        },
        availableCourses() {
            const semesterCourses = this.coursesForSemester(this.selection.semester)
            if (!this.selectedStudent) return semesterCourses

            const studentSemester = this.selectedStudentPlanningSemesterValue()
            const plannedCourses = studentSemester
                ? this.studentPlannedCoursesForSemester(studentSemester, this.studentCompletedCourseCodes())
                : []
            const missingCourses = studentSemester
                ? this.pendingStudentCoursesBeforeSemester(
                    studentSemester,
                    this.studentCompletedCourseCodes(),
                    this.studentVisitedCourseCodes(),
                )
                : []

            return [
                ...missingCourses,
                ...plannedCourses,
            ]
                .filter((course, index, courses) =>
                    courses.findIndex(candidate => candidate.key === course.key) === index,
                )
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        availableCourseColumns() {
            const splitIndex = Math.ceil(this.availableCourses.length / 2)

            return [
                this.availableCourses.slice(0, splitIndex),
                this.availableCourses.slice(splitIndex),
            ].filter(courseColumn => courseColumn.length)
        },
        regularCourseColumns() {
            if (!this.selectedStudent) {
                return this.availableCourseColumns.map((courses, index) => ({
                    key: `courses-${index}`,
                    title: index === 0 ? 'Vorgesehene Kurse' : '',
                    courses,
                }))
            }

            const studentCourseColumns = [
                {
                    key: 'missing',
                    title: 'Fehlende Kurse',
                    courses: this.studentMissingCourses,
                },
                {
                    key: 'planned',
                    title: 'Vorgesehene Kurse',
                    courses: this.studentPlannedCourses,
                },
            ].filter(courseColumn => courseColumn.courses.length)

            if (studentCourseColumns.length !== 1) return studentCourseColumns

            const [studentCourseColumn] = studentCourseColumns
            const splitIndex = Math.ceil(studentCourseColumn.courses.length / 2)

            return [
                {
                    ...studentCourseColumn,
                    courses: studentCourseColumn.courses.slice(0, splitIndex),
                },
                {
                    ...studentCourseColumn,
                    key: `${studentCourseColumn.key}-overflow`,
                    title: '',
                    courses: studentCourseColumn.courses.slice(splitIndex),
                },
            ].filter(courseColumn => courseColumn.courses.length)
        },
        additionalCourseColumns() {
            const splitIndex = Math.ceil(this.studentAdditionalCourses.length / 2)

            return [
                this.studentAdditionalCourses.slice(0, splitIndex),
                this.studentAdditionalCourses.slice(splitIndex),
            ].filter(courseColumn => courseColumn.length)
        },
        additionalCoursePanelVisible() {
            const additionalCourses = Array.isArray(this.studentAdditionalCourses) ? this.studentAdditionalCourses : []

            return additionalCourses.length > 0
                && (Boolean(this.selectedRobotTimetable) || this.additionalCoursePanelRetained === true)
        },
        additionalCourseExtensionMode() {
            return this.additionalCoursePanelVisible
                && this.selectedAdditionalCourses.length > 0
        },
        additionalCourseExtensionActionVisible() {
            return this.selectedAdditionalCourses.length > 0
                && this.additionalCourseExtensionActionHidden !== true
        },
        additionalCourseSelectionLocked() {
            return this.additionalCourseTimetableRequired === true
                && this.additionalCourseExtensionActionHidden === true
        },
        generatedTimetableActionButtonsVisible() {
            return this.additionalCourseSelectionChangedAfterTimetable !== true
        },
        generatedTimetableDisplayVisible() {
            return Boolean(this.selectedRobotTimetable)
                && this.additionalCourseSelectionChangedAfterTimetable !== true
        },
        courseGroupItemsByCourseKey() {
            const courseItems = [
                ...(Array.isArray(this.availableCourses) ? this.availableCourses : []),
                ...(Array.isArray(this.studentAdditionalCourses) ? this.studentAdditionalCourses : []),
            ]
                .filter(course => course?.key)
                .filter((course, index, courses) =>
                    courses.findIndex(candidate => candidate?.key === course.key) === index,
                )

            return new Map(courseItems.map(course => [
                course.key,
                this.uncachedCourseGroupItems(course),
            ]))
        },
        deselectedCourseKeySet() {
            return new Set(Array.isArray(this.deselectedCourseKeys) ? this.deselectedCourseKeys : [])
        },
        deselectedCourseGroupKeySet() {
            return new Set(Array.isArray(this.deselectedCourseGroupKeys) ? this.deselectedCourseGroupKeys : [])
        },
        additionalCourseSelectedKeySet() {
            return new Set(Array.isArray(this.additionalCourseSelectedKeys) ? this.additionalCourseSelectedKeys : [])
        },
        selectedAdditionalCourses() {
            return this.studentAdditionalCourses.filter(course => this.additionalCourseSelected(course))
        },
        selectedTimetableScheduledCourseKeySet() {
            return new Set(this.selectedTimetableScheduledCourses()
                .flatMap(scheduledCourse => this.courseComparisonKeys(scheduledCourse)))
        },
        selectedTimetableConflictCourseKeySet() {
            return new Set(this.selectedTimetableConflictCourses()
                .flatMap(conflictCourse => this.courseComparisonKeys(conflictCourse)))
        },
        selectedTimetableMissingAdditionalCourseKeySet() {
            return new Set(this.selectedTimetableMissingAdditionalCourses()
                .flatMap(missingCourse => this.courseComparisonKeys(missingCourse)))
        },
        additionalCourseInteractionDisabledKeySet() {
            const disabledKeys = new Set()
            const additionalCourses = Array.isArray(this.studentAdditionalCourses)
                ? this.studentAdditionalCourses
                : []

            additionalCourses.forEach(course => {
                const courseKey = course?.key
                if (!courseKey) return

                if (
                    this.additionalCourseMissingInSelectedTimetable(course)
                    || this.courseAllGroupsNoLongerFitSelectedTimetable(course)
                ) {
                    disabledKeys.add(courseKey)
                }
            })

            return disabledKeys
        },
        selectedCourses() {
            return this.availableCourses.filter(course => this.courseSelected(course))
        },
        selectedCoursesHours() {
            return this.selectedCourses.reduce((total, course) => total + Number(course.hours || 0), 0)
        },
        courseSelectionResettable() {
            return this.courseSelectionStateSnapshot(
                this.currentCourseSelectionState(this.availableCourses),
            ) !== this.courseSelectionStateSnapshot(this.defaultCourseSelectionState())
        },
        plannedCourseSelectionChanged() {
            const plannedCourses = this.availablePlannedCourses()
            if (!plannedCourses.length) return false

            return this.courseSelectionStateSnapshot(
                this.currentCourseSelectionState(plannedCourses),
            ) !== this.courseSelectionStateSnapshot({
                deselectedCourseKeys: [],
                deselectedCourseGroupKeys: [],
            })
        },
        courseActionVisible() {
            if (!this.selectedStudent && this.courseActionHiddenUntilCourseInteraction === true) return false

            return !this.selectedStudent
                || !this.studentPlannedCourses.length
                || !this.selectedRobotTimetable
                || this.plannedCourseSelectionChanged
                || this.additionalCourseSelectionChangedAfterTimetable === true
        },
        additionalCourseSelectionResettable() {
            return this.additionalCourseSelectionStateSnapshot(
                this.currentAdditionalCourseSelectionState(),
            ) !== this.additionalCourseSelectionStateSnapshot(this.defaultAdditionalCourseSelectionState())
        },
        studentSettingsResettable() {
            if (!this.selectedStudent) return false

            return this.selectionStateSnapshot(this.selection) !== this.selectionStateSnapshot(
                this.selectedStudentDefaultSelection(this.selectedStudent, { includeCourseHistory: true }),
            )
        },
        studentCourseSelectionResettable() {
            return this.studentSettingsResettable || this.courseSelectionResettable || this.additionalCourseSelectionResettable
        },
        configuredCourseGroups() {
            return Array.isArray(this.courseGroups) ? this.courseGroups : []
        },
        qualityCriterionRows() {
            const countersByKey = new Map((this.qualityCounters || []).map(counter => [counter.key, counter]))
            const sourceCriteria = this.evaluationCriteriaLoaded || this.evaluationCriteria.length
                ? this.evaluationCriteria
                : this.qualityCounters

            return sourceCriteria.map(criterion => ({
                ...criterion,
                ...(countersByKey.get(criterion.key) || {}),
                enabled: criterion.enabled === true,
            }))
        },
        activeQualityCriterionRows() {
            return this.qualityCriterionRows.filter(criterion => criterion.enabled === true)
        },
        selectedCriteriaTimetableCountLabel() {
            return this.formatNumber(this.selectedCriteriaTimetableCount())
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
            const generatedTimeValues = new Set(this.generatedTimetables.flatMap(timetable => [
                ...Object.values(timetable.slots).map(slot => Number(slot?.courseGroup?.hour)),
                ...(timetable.occasionalAppointments || []).map(appointment => Number(appointment?.hour)),
            ]).filter(Number.isFinite))
            if (this.generatedTimetables.length && !generatedTimeValues.size) {
                return this.timeOptions.filter(time => this.constraintSelected('availableTimes', time.value))
            }

            if (!generatedTimeValues.size) return []

            const firstGeneratedTime = Math.min(...generatedTimeValues)
            const lastGeneratedTime = Math.max(...generatedTimeValues)
            const configuredTimesByValue = new Map(this.timeOptions.map(time => [Number(time.value), time]))

            return Array.from(
                { length: (lastGeneratedTime - firstGeneratedTime) + 1 },
                (value, index) => firstGeneratedTime + index,
            )
                .map(time => configuredTimesByValue.get(time) || {
                    title: `${time}. Stunde`,
                    shortTitle: `${time}. Stunde`,
                    value: time,
                })
        },
        selectedRobotTimetable() {
            return this.generatedTimetables[0] || null
        },
        robotTimetableWeekdays() {
            if (!this.selectedRobotTimetable) {
                return this.emptyTimetableWeekdays
            }

            return this.weekdayOptions.filter(weekday =>
                Number(weekday.value) <= 5
                    || (Number(weekday.value) === 6 && this.selectedRobotTimetableHasSaturday()),
            )
        },
        robotTimetableTimes() {
            return this.selectedRobotTimetable ? this.generatedTimes : this.emptyTimetableTimes
        },
        emptyTimetableWeekdays() {
            return this.weekdayOptions.filter(weekday =>
                Number(weekday.value) <= 5
                    || (Number(weekday.value) === 6 && this.constraintSelected('availableWeekdays', weekday.value)),
            )
        },
        emptyTimetableTimes() {
            const availableTimes = this.timeOptions.filter(time => this.constraintSelected('availableTimes', time.value))

            return availableTimes.length ? availableTimes : this.timeOptions
        },
        fullGreenTimetableCountLabel() {
            if (!this.selectedCourses.length) return '0'
            if (this.fullGreenTimetableCount === null) return '-'

            return new Intl.NumberFormat('de-AT').format(this.fullGreenTimetableCount)
        },
        totalTimetableVariationCountLabel() {
            if (!this.selectedCourses.length) return '0'
            if (this.totalTimetableVariationCount === null) return '-'

            return new Intl.NumberFormat('de-AT').format(this.totalTimetableVariationCount)
        },
        greenTimetableCountLabel() {
            if (!this.selectedCourses.length) return '0'
            if (this.greenTimetableCount === null) return '-'

            return new Intl.NumberFormat('de-AT').format(this.greenTimetableCount)
        },
        conflictTimetableCountLabel() {
            if (!this.selectedCourses.length) return '0'
            if (this.conflictTimetableCount === null) return '-'

            return new Intl.NumberFormat('de-AT').format(this.conflictTimetableCount)
        },
        additionalCourseTimetableCountLabel() {
            if (!this.selectedAdditionalCourses.length) return '-'
            if (this.additionalCourseTimetableCount === null) return '-'

            return `${this.formatNumber(this.additionalCourseTimetableCount)} / ${
                this.formatNumber(this.timetableResultCount(this.selectedTimetableResultType))
            }`
        },
        additionalCourseTimetableCountCardValue() {
            if (!this.selectedAdditionalCourses.length) return '-'
            if (this.additionalCourseTimetableCount === null) return '-'

            return this.formatNumber(this.additionalCourseTimetableCount)
        },
        additionalCourseTimetableCountCardVisible() {
            return this.selectedAdditionalCourses.length > 0
                && this.additionalCourseTimetableCount !== null
                && this.timetableResultCount(this.selectedTimetableResultType) > 0
        },
        additionalCourseTimetableCountCardTitle() {
            return {
                full_green: 'Volle grüne Stundenpläne',
                green: 'Grüne Stundenpläne',
                conflict: 'Stundenpläne mit Konflikten',
            }[this.selectedTimetableResultType] || 'Stundenpläne'
        },
        hasGreenTimetableResults() {
            return this.timetableResultCount('full_green') > 0
                || this.timetableResultCount('green') > 0
        },
        showConflictTimetableResults() {
            return false
        },
        backendVariationCountsAvailable() {
            return this.totalTimetableVariationCount !== null
        },
        timetableCountResultsAvailable() {
            return this.selectedCourses.length > 0
                && [
                    this.totalTimetableVariationCount,
                    this.fullGreenTimetableCount,
                    this.greenTimetableCount,
                    this.conflictTimetableCount,
                ].some(count => count !== null)
        },
        selectedTimetableResultCount() {
            if (this.qualityCriteriaResultFilterActive) {
                return this.selectedCriteriaTimetableCount()
            }

            return this.timetableResultCounterLimit(this.selectedTimetableResultType)
        },
        qualityCriteriaResultFilterActive() {
            return this.qualityCriteriaResultFilterEnabled === true
                && this.qualityCriteriaResultFilterAvailable()
        },
        selectedOptionsNoResultAlertVisible() {
            return this.timetableCountResultsAvailable
                && !this.fullGreenTimetableCountLoading
                && !this.fullGreenTimetableCountError
                && this.selectedOptionsNoResultReasons.length > 0
        },
        selectedOptionsNoResultReasons() {
            const reasons = []

            if (this.timetableResultCount('full_green') <= 0
                && this.timetableResultCount('green') <= 0
                && this.timetableResultCount('conflict') <= 0) {
                reasons.push(...this.noBaseTimetableResultReasons())
            }

            if (
                this.additionalCourseTimetableRequired === true
                && this.selectedAdditionalCourses.length > 0
                && this.additionalCourseTimetableCount !== null
                && Number(this.additionalCourseTimetableCount || 0) <= 0
            ) {
                reasons.push(
                    `Der Zusatzkurs-Filter ist aktiv, aber kein ${this.selectedTimetableResultPluralTitle()} enthält alle gewählten Zusatzkurse: ${this.selectedAdditionalCourseLabels()}.`,
                )
            }

            if (
                this.activeQualityCriterionRows.length > 0
                && this.allQualityCriteriaCount !== null
                && Number(this.allQualityCriteriaCount || 0) <= 0
                && this.selectedTimetableResultCount > 0
            ) {
                reasons.push(
                    `Kein ${this.selectedTimetableResultPluralTitle()} erfüllt alle aktiven Bewertungskriterien: ${this.activeQualityCriterionLabels()}.`,
                )
            }

            return this.uniqueValues(reasons).slice(0, 6)
        },
        selectedTimetableResultTitle() {
            return {
                full_green: 'voller grüner Stundenplan',
                green: 'grüner Stundenplan',
                conflict: 'Stundenplan mit Konflikten',
            }[this.selectedTimetableResultType] || 'Stundenplan'
        },
        selectedTimetableResultStatusLabel() {
            return this.selectedTimetableResultType === 'full_green' ? 'Voll grün' : 'Grün'
        },
        selectedTimetableResultStatusColor() {
            return this.selectedTimetableResultType === 'full_green' ? 'success' : 'primary'
        },
    },
    watch: {
        evaluationCriteriaSettings: {
            deep: true,
            immediate: true,
            handler(criteria) {
                if (!Array.isArray(criteria)) return

                this.applyEvaluationCriteriaSettings(criteria)
            },
        },
        'config.selected_schoolyear.id'() {
            this.loadSettings()
        },
        'selected_schoolyear.id'() {
            this.loadSettings()
        },
        studentCode() {
            this.syncExternalStudentSelection()
        },
        selectedRobotTimetable: {
            immediate: true,
            handler(timetable) {
                if (!this.embeddedCourseCardsOnly) return

                this.embeddedCourseSelectionExpanded = !timetable
                this.embeddedAdditionalCourseSelectionExpanded = true
                this.$emit('generated-timetable-visibility-change', Boolean(timetable))
            },
        },
        selection: {
            deep: true,
            handler() {
                if (this.loading || this.robotStateRestoring) return

                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            },
        },
        studentSelection: {
            deep: true,
            handler() {
                if (this.loading || this.robotStateRestoring) return

                this.clearGeneratedTimetables()
                this.loadStudentCompletedCourses()
                this.saveLastRobotState()
            },
        },
        constraints: {
            deep: true,
            handler() {
                if (this.loading || this.robotStateRestoring) return

                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            },
        },
        deselectedCourseKeys: {
            deep: true,
            handler() {
                if (this.loading || this.robotStateRestoring) return

                this.saveLastRobotState()
            },
        },
        deselectedCourseGroupKeys: {
            deep: true,
            handler() {
                if (this.loading || this.robotStateRestoring) return

                this.saveLastRobotState()
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
            try {
                this.studentOptionsLoading = true
                const [
                    settingsResponse,
                    schoolHoursResponse,
                    courseGroupsResponse,
                    evaluationSettingsResponse,
                    studentOptionsResponse,
                ] = await Promise.all([
                    axios.get('/api/admin/students-timetables/subjects-overview-settings'),
                    axios.get('/api/admin/students-timetables/school-hours'),
                    axios.get('/api/admin/students-timetables/course-groups'),
                    axios.get('/api/admin/students-timetables/evaluation-settings'),
                    axios.get('/api/admin/students-timetables/robot/students'),
                ])

                this.subjectRows = settingsResponse.data.data?.subjects || []
                this.subjectMappings = settingsResponse.data.data?.mappings || []
                this.schoolHours = schoolHoursResponse.data?.data || []
                this.courseGroups = courseGroupsResponse.data?.data || []
                this.applyEvaluationCriteriaSettings(
                    Array.isArray(this.evaluationCriteriaSettings)
                        ? this.evaluationCriteriaSettings
                        : evaluationSettingsResponse.data?.data?.criteria || [],
                )
                this.robotStudents = studentOptionsResponse.data?.data || []
                this.restoreLastRobotState()
                this.syncExternalStudentSelection()
                this.syncAvailableTimes()
                this.loadStudentCompletedCourses()
                this.restoreTimetablePreferencesAfterRestore()
            } catch (error) {
                this.schoolHours = []
                this.courseGroups = []
                this.subjectMappings = []
                this.subjectRows = []
                this.evaluationCriteria = []
                this.evaluationCriteriaLoaded = false
                this.robotStudents = []
                this.studentCompletedCourses = []
                this.error = 'Die Kurse konnten nicht geladen werden.'
            } finally {
                this.studentOptionsLoading = false
                this.loading = false
            }
        },
        async ensureRobotConfigLoaded() {
            if (this.robotSchoolyearId() !== 'default') return

            try {
                await useAdminStore().loadConfig?.()
            } catch {
                // Keep loading robot settings even if the shared admin config is unavailable.
            }
        },
        selectedOptionTitle(options, value) {
            return options.find(option => option.value === value)?.title || value
        },
        studentSemesterBySchoolLevel() {
            return {
                '09_1': 1,
                '09_2': 2,
                '10_1': 3,
                '10_2': 4,
                '11_1': 5,
                '11_2': 6,
                '12_1': 7,
                '12_2': 8,
            }
        },
        studentSemesterLabel(student) {
            const semester = this.studentSemester(student)

            return semester ? `Semester ${semester}` : ''
        },
        studentSemester(student) {
            const schoolLevel = this.studentSchoolLevelKey(student)

            return this.studentSemesterBySchoolLevel()[schoolLevel] || null
        },
        selectedStudentPlanningSemesterValue() {
            if (!this.selectedStudent) return Number(this.selection?.semester) || null

            return this.studentSemester(this.selectedStudent) || Number(this.selection?.semester) || null
        },
        applySelectedStudentDefaultSelection(options = {}) {
            const nextSelection = this.selectedStudentDefaultSelection(this.selectedStudent, options)

            this.selection = nextSelection
            this.selectionDraft = { ...nextSelection }
        },
        selectedStudentDefaultSelection(student, options = {}) {
            const defaultSelection = { ...this.defaultRobotState().selection }
            const semester = this.studentSemester(student)

            return {
                ...defaultSelection,
                ...(semester ? { semester } : {}),
                ...(options?.includeCourseHistory ? this.selectedStudentCourseHistoryDefaults() : {}),
            }
        },
        selectedStudentCourseHistoryDefaults() {
            const visitedCourseCodes = this.studentVisitedCourseCodes()

            return Object.fromEntries([
                ['religion', this.inferredSelectionOptionFromCourseCodes(this.religionOptions, visitedCourseCodes)],
                ['language', this.inferredSelectionOptionFromCourseCodes(this.languageOptions, visitedCourseCodes, {
                    S: ['S', 'SPA'],
                })],
                ['branch', this.inferredBranchFromCourseCodes(visitedCourseCodes)],
                ['artsSubject', this.inferredSelectionOptionFromCourseCodes(this.artsSubjectOptions, visitedCourseCodes)],
            ].filter(([, value]) => Boolean(value)))
        },
        inferredSelectionOptionFromCourseCodes(options, courseCodes, aliases = {}) {
            if (!(courseCodes instanceof Set) || !courseCodes.size) return ''

            return (Array.isArray(options) ? options : [])
                .map((option, optionIndex) => ({
                    option,
                    optionIndex,
                    match: this.bestSelectionOptionCourseCodeMatch(option?.value, courseCodes, aliases),
                }))
                .filter(({ match }) => match)
                .sort((firstOption, secondOption) =>
                    secondOption.match.module - firstOption.match.module
                    || secondOption.match.courseIndex - firstOption.match.courseIndex
                    || firstOption.optionIndex - secondOption.optionIndex,
                )[0]?.option?.value || ''
        },
        selectionOptionCodeAliases(value, aliases = {}) {
            const normalizedValue = this.normalizedCourseCode(value)
            const mappedAliases = {
                ET: ['ETH', 'ET'],
                ETH: ['ETH', 'ET'],
                L: ['L', 'LET', 'LPT'],
                LET: ['L', 'LET', 'LPT'],
                LPT: ['L', 'LET', 'LPT'],
                ME: ['ME', 'MU'],
                MU: ['ME', 'MU'],
                R: ['RK', 'R'],
                RK: ['RK', 'R'],
                S: ['S', 'SPA'],
                SPA: ['S', 'SPA'],
            }

            return [
                normalizedValue,
                ...(mappedAliases[normalizedValue] || []),
                ...(aliases[normalizedValue] || []),
            ]
                .map(alias => this.normalizedCourseCode(alias))
                .filter(Boolean)
                .filter((alias, index, allAliases) => allAliases.indexOf(alias) === index)
        },
        bestSelectionOptionCourseCodeMatch(value, courseCodes, aliases = {}) {
            const optionAliases = this.selectionOptionCodeAliases(value, aliases)
                .map(alias => this.courseCodeWithoutModule(alias))
            if (!optionAliases.length) return null

            return [...courseCodes]
                .map((courseCode, courseIndex) => ({
                    ...this.courseCodeModuleParts(courseCode),
                    courseIndex,
                }))
                .filter(parts => optionAliases.includes(parts.base))
                .map(parts => ({
                    module: Number(parts.module || 0),
                    courseIndex: parts.courseIndex,
                }))
                .sort((firstMatch, secondMatch) =>
                    secondMatch.module - firstMatch.module
                    || secondMatch.courseIndex - firstMatch.courseIndex,
                )[0] || null
        },
        inferredBranchFromCourseCodes(courseCodes) {
            const branchOptionValues = (Array.isArray(this.branchOptions) ? this.branchOptions : [])
                .map(option => option?.value)
                .filter(Boolean)

            if (!(courseCodes instanceof Set) || !courseCodes.size || !branchOptionValues.length) return ''

            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter(subject => subject?.is_active !== false)
                .filter(subject => branchOptionValues.includes(subject?.branch))
                .map((subject, subjectIndex) => ({
                    subject,
                    subjectIndex,
                    match: this.bestSubjectRowCourseCodeMatch(subject, courseCodes),
                }))
                .filter(({ match }) => match)
                .sort((firstSubject, secondSubject) =>
                    secondSubject.match.module - firstSubject.match.module
                    || secondSubject.match.semester - firstSubject.match.semester
                    || secondSubject.match.courseIndex - firstSubject.match.courseIndex
                    || firstSubject.subjectIndex - secondSubject.subjectIndex,
                )[0]?.subject?.branch || ''
        },
        bestSubjectRowCourseCodeMatch(subject, courseCodes) {
            const subjectParts = this.subjectCourseCodesForBranchInference(subject)
                .flatMap(courseCode => this.courseCodeAliasParts(courseCode))
                .flatMap(courseCode => [
                    courseCode,
                    this.defaultTimetableCodeAlias(courseCode),
                ])
                .map(courseCode => this.courseCodeModuleParts(courseCode))
                .filter(parts => parts.base)
                .filter((parts, index, allParts) =>
                    allParts.findIndex(candidate => candidate.base === parts.base && candidate.module === parts.module) === index,
                )

            if (!subjectParts.length) return null

            return [...courseCodes]
                .map((courseCode, courseIndex) => ({
                    ...this.courseCodeModuleParts(courseCode),
                    courseIndex,
                }))
                .flatMap(courseParts => subjectParts
                    .filter(subjectPart => this.subjectCoursePartMatchesCompletedCoursePart(subjectPart, courseParts))
                    .map(() => ({
                        module: Number(courseParts.module || 0),
                        semester: Number(subject?.semester || 0),
                        courseIndex: courseParts.courseIndex,
                    })))
                .sort((firstMatch, secondMatch) =>
                    secondMatch.module - firstMatch.module
                    || secondMatch.semester - firstMatch.semester
                    || secondMatch.courseIndex - firstMatch.courseIndex,
                )[0] || null
        },
        subjectCourseCodesForBranchInference(subject) {
            return [
                subject?.json_code,
                subject?.json_subject,
                subject?.name,
            ].filter(Boolean)
        },
        subjectCoursePartMatchesCompletedCoursePart(subjectPart, completedPart) {
            if (!this.studentCourseBaseAliases(subjectPart.base).includes(completedPart.base)) return false

            const subjectModule = String(subjectPart.module || '')
            const completedModule = String(completedPart.module || '')

            return !subjectModule || !completedModule || subjectModule === completedModule
        },
        selectionStateSnapshot(selection) {
            return [
                Number(selection?.semester || 0),
                String(selection?.religion || ''),
                String(selection?.branch || ''),
                String(selection?.artsSubject || ''),
                String(selection?.language || ''),
            ].join('|')
        },
        studentSchoolLevelKey(student) {
            const importedSchoolLevel = this.normalizedStudentSchoolLevel(
                student?.school_level,
                student?.attendance_year,
            )
            if (importedSchoolLevel) return importedSchoolLevel

            return this.normalizedStudentSchoolLevel(student?.class)
        },
        normalizedStudentSchoolLevel(schoolLevel, attendanceYear = null) {
            const normalizedAttendanceYear = String(attendanceYear || '').trim()
            if (normalizedAttendanceYear !== '') {
                const normalizedSchoolLevel = this.normalizedStudentSchoolLevelToken(schoolLevel)
                if (normalizedSchoolLevel && ['1', '2'].includes(normalizedAttendanceYear)) {
                    return `${normalizedSchoolLevel}_${normalizedAttendanceYear}`
                }
            }

            const value = String(schoolLevel || '').trim()
            if (!value) return ''

            const normalizedValue = value.replace(/[.\-\s]+/g, '_')
            const schoolLevelMatch = normalizedValue.match(/(?:^|[^0-9])(0?9|1[0-2])_?([12])(?:$|[^0-9])/)
            if (!schoolLevelMatch) return ''

            return `${schoolLevelMatch[1].padStart(2, '0')}_${schoolLevelMatch[2]}`
        },
        normalizedStudentSchoolLevelToken(value) {
            const schoolLevelMatch = String(value || '').trim().match(/^(0?9|1[0-2])$/)

            return schoolLevelMatch ? schoolLevelMatch[1].padStart(2, '0') : ''
        },
        studentOptionTitle(student) {
            const schoolClass = String(student?.class || '').trim()
            const lastName = String(student?.last_name || '').trim()
            const firstName = String(student?.first_name || '').trim()
            const semester = this.studentSemesterLabel(student)
            const name = [lastName, firstName].filter(Boolean).join(' ')

            return [schoolClass, name, semester].filter(Boolean).join(' · ')
        },
        normalizedStudentCode(value) {
            const studentCode = value === null || value === undefined ? '' : String(value).trim()

            return studentCode === '' ? null : studentCode
        },
        openSelectionDialog() {
            this.selectionDraft = { ...this.selection }
            this.selectionDialogOpen = true
        },
        closeSelectionDialog() {
            this.selectionDialogOpen = false
        },
        updateSelection() {
            this.studentSelectionDefaultsPendingCode = null
            this.selection = { ...this.selectionDraft }
            this.applyStudentPlannedCourseSelection()
            this.clearGeneratedTimetables()
            this.selectionDialogOpen = false
            this.saveLastRobotState()
        },
        openStudentDialog() {
            this.studentSelectionDraft = { ...this.studentSelection }
            this.studentSearch = ''
            this.studentDialogOpen = true
            this.$nextTick(() => this.focusStudentSearchField())
        },
        closeStudentDialog() {
            this.studentDialogOpen = false
            this.studentSearch = ''
        },
        focusStudentSearchField() {
            const searchField = this.$refs.studentSearchField

            searchField?.focus?.()
            searchField?.$el?.querySelector?.('input')?.focus?.()
        },
        selectStudentDraft(studentCode) {
            this.studentSelectionDraft.studentCode = this.normalizedStudentCode(studentCode)
        },
        submitStudentSearch() {
            if (this.studentSearchReady && this.filteredStudentResults.length === 1) {
                this.selectStudentDraft(this.filteredStudentResults[0].student_code)
            }

            this.updateStudentSelection()
        },
        updateStudentSelection() {
            this.applyStudentSelection(this.studentSelectionDraft.studentCode)
        },
        clearStudentSelection() {
            if (!this.selectedStudent) return

            this.studentSelectionDraft = {
                studentCode: null,
            }
            this.applyStudentSelection(null)
        },
        applyStudentSelection(studentCode) {
            const previousStudentCode = this.normalizedStudentCode(this.studentSelection.studentCode)
            const nextStudentCode = this.normalizedStudentCode(studentCode)

            this.studentSelection = {
                studentCode: nextStudentCode,
            }
            if (previousStudentCode !== nextStudentCode) {
                this.studentSelectionDefaultsPendingCode = nextStudentCode
                this.applySelectedStudentDefaultSelection()
            }
            this.additionalCourseSelectedKeys = []
            this.studentCompletedCoursesExpanded = false
            this.clearGeneratedTimetables()
            this.studentDialogOpen = false
            this.studentSearch = ''
            this.saveLastRobotState()
        },
        syncExternalStudentSelection() {
            if (!this.embeddedCourseCardsOnly) return

            const nextStudentCode = this.normalizedStudentCode(this.studentCode)
            if (nextStudentCode && !this.studentByCode(nextStudentCode)) return
            if (this.normalizedStudentCode(this.studentSelection.studentCode) === nextStudentCode) return

            this.applyStudentSelection(nextStudentCode)
        },
        toggleStudentCompletedCourses() {
            if (!this.selectedStudent) return

            this.studentCompletedCoursesExpanded = !this.studentCompletedCoursesExpanded
        },
        async loadStudentCompletedCourses() {
            const studentCode = this.normalizedStudentCode(this.studentSelection.studentCode)
            const requestId = this.studentCompletedCoursesRequestId + 1
            this.studentCompletedCoursesRequestId = requestId

            if (!studentCode) {
                this.studentCompletedCourses = []
                this.studentCompletedCoursesError = ''
                this.studentCompletedCoursesLoading = false
                this.studentCompletedCoursesExpanded = false
                this.additionalCourseSelectedKeys = []
                this.selectAllAvailableCourses()

                return
            }

            this.studentCompletedCoursesLoading = true
            this.studentCompletedCoursesError = ''

            try {
                const response = await axios.get('/api/admin/students-timetables/robot/student-completed-courses', {
                    params: {
                        student_code: studentCode,
                    },
                })

                if (requestId !== this.studentCompletedCoursesRequestId) return

                this.studentCompletedCourses = response.data?.data || []
                if (this.studentSelectionDefaultsPendingCode === studentCode) {
                    this.applySelectedStudentDefaultSelection({ includeCourseHistory: true })
                    this.studentSelectionDefaultsPendingCode = null
                }
            } catch {
                if (requestId !== this.studentCompletedCoursesRequestId) return

                this.studentCompletedCourses = []
                if (this.studentSelectionDefaultsPendingCode === studentCode) {
                    this.studentSelectionDefaultsPendingCode = null
                }
                this.studentCompletedCoursesError = 'Die abgeschlossenen Kurse konnten nicht geladen werden.'
            } finally {
                if (requestId === this.studentCompletedCoursesRequestId) {
                    this.studentCompletedCoursesLoading = false

                    if (!this.studentCompletedCoursesError) {
                        this.applyStudentPlannedCourseSelection()
                        this.pruneAdditionalCourseSelections()
                    }
                }
            }
        },
        openConstraintsDialog() {
            this.constraintsDraft = this.copyConstraints(this.constraints)
            this.constraintsDialogOpen = true
        },
        closeConstraintsDialog() {
            this.constraintsDialogOpen = false
        },
        onSettingsChanged(criteria) {
            const previousEvaluationCriteriaSignature = this.evaluationCriteriaSignature(this.evaluationCriteria)

            this.applyEvaluationCriteriaSettings(criteria || [])

            if (previousEvaluationCriteriaSignature !== this.evaluationCriteriaSignature(this.evaluationCriteria)) {
                this.clearGeneratedTimetables()
                this.showCourseActionAfterCourseInteraction()
                this.saveLastRobotState()
            }
        },
        async onSettingsSaved(criteria = []) {
            this.settingsDialogOpen = false

            if (criteria.length) {
                this.onSettingsChanged(criteria)

                return
            }

            try {
                const response = await axios.get('/api/admin/students-timetables/evaluation-settings')
                this.onSettingsChanged(response.data?.data?.criteria || [])
            } catch {
                // keep existing criteria on failure
            }
        },
        applyEvaluationCriteriaSettings(criteria) {
            this.evaluationCriteria = this.enabledEvaluationCriteriaFromSettings(criteria || [])
            this.evaluationCriteriaLoaded = true
        },
        updateConstraints() {
            this.constraints = this.copyConstraints(this.constraintsDraft)
            this.clearGeneratedTimetables()
            this.constraintsDialogOpen = false
            this.saveLastRobotState()
        },
        copyConstraints(source) {
            return {
                availableWeekdays: [...(source?.availableWeekdays || [])],
                excludedWeekdayTimes: [...(source?.excludedWeekdayTimes || [])],
                availableTimes: [...(source?.availableTimes || [])],
            }
        },
        async createTimetables(options = {}) {
            if (
                this.loading
                || this.timetableGenerationLoading
                || !(this.selectedCourses || []).length
            ) {
                return
            }

            this.hideCourseActionUntilCourseInteraction()
            this.timetableCreateLoading = true

            try {
                this.prepareTimetableCreationOptions(options)

                await this.loadFullGreenTimetableCount({ preferFullGreen: true })
            } finally {
                this.timetableCreateLoading = false
            }
        },
        prepareTimetableCreationOptions(options = {}) {
            if (options?.requireAdditionalCourses !== true) {
                this.clearAdditionalCourseSelectionForTimetableCreation()

                return
            }

            if (!this.selectedAdditionalCourses.length) return

            this.additionalCourseExtensionActionHidden = true
            const requiredStateChanged = this.additionalCourseTimetableRequired !== true
            this.additionalCourseTimetableRequired = true

            if (requiredStateChanged) {
                this.setTimetableResultCounter(this.selectedTimetableResultType, 1)
            }

            this.normalizeTimetableResultCounters()
        },
        clearAdditionalCourseSelectionForTimetableCreation() {
            const hasSelectedAdditionalCourses = Array.isArray(this.additionalCourseSelectedKeys)
                && this.additionalCourseSelectedKeys.length > 0

            if (!hasSelectedAdditionalCourses && this.additionalCourseTimetableRequired !== true) return

            this.resetAdditionalCourseSelection({ clearGeneratedTimetables: true })
        },
        hideCourseActionUntilCourseInteraction() {
            if (this.selectedStudent) return

            this.courseActionHiddenUntilCourseInteraction = true
        },
        showCourseActionAfterCourseInteraction() {
            if (this.embeddedCourseSelectionLocked) return

            this.courseActionHiddenUntilCourseInteraction = false
        },
        toggleEmbeddedCourseSelection() {
            this.embeddedCourseSelectionExpanded = !this.embeddedCourseSelectionExpanded
        },
        toggleEmbeddedAdditionalCourseSelection() {
            this.embeddedAdditionalCourseSelectionExpanded = !this.embeddedAdditionalCourseSelectionExpanded
        },
        backendTimetableRequestPayload(options = {}) {
            const evaluationCriteria = this.storageEvaluationCriteria(this.evaluationCriteria)

            return {
                selection: this.selection,
                constraints: this.constraints,
                student: this.studentSelection,
                selected_course_keys: this.selectedCourses.map(course => course.key),
                deselected_course_keys: this.deselectedCourseKeys,
                deselected_course_group_keys: this.deselectedCourseGroupKeys,
                selected_additional_course_keys: this.selectedAdditionalCourses.map(course => course.key),
                selected_additional_courses_required: this.additionalCourseTimetableRequired === true,
                selected_timetable_type: this.selectedTimetableResultType,
                selected_timetable_number: this.timetableResultCounter(this.selectedTimetableResultType),
                selected_quality_criterion_keys: this.selectedQualityCriterionKeys(),
                selected_quality_criteria_required: options?.forceQualityCriteriaRequired === true || this.qualityCriteriaResultFilterActive,
                include_quality_counters: options?.calculateQualityCounters === true,
                evaluation_criteria: evaluationCriteria,
            }
        },
        async loadFullGreenTimetableCount(options = {}) {
            this.fullGreenTimetableCountError = ''

            if (
                this.loading
                || !(this.subjectRows || []).length
                || !(this.courseGroups || []).length
                || !(this.selectedCourses || []).length
            ) {
                this.totalTimetableVariationCount = 0
                this.fullGreenTimetableCount = 0
                this.greenTimetableCount = 0
                this.conflictTimetableCount = 0
                this.additionalCourseTimetableCount = 0
                this.qualityCounters = []
                this.allQualityCriteriaCount = 0
                this.selectedQualityCriteriaCount = 0
                this.qualityCriteriaResultFilterEnabled = false
                this.generatedTimetables = []
                this.additionalCourseTimetableRequired = false
                this.additionalCourseSelectionChangedAfterTimetable = false
                this.normalizeTimetableResultCounters()
                this.fullGreenTimetableCountLoading = false

                return
            }

            const requestId = this.fullGreenTimetableCountRequestId + 1
            this.fullGreenTimetableCountRequestId = requestId
            this.fullGreenTimetableCountLoading = true
            if (options?.preserveGeneratedTimetable !== true) {
                this.generatedTimetables = []
            }
            this.resetQualityCounterSelection()

            try {
                const response = await axios.post(
                    '/api/admin/students-timetables/robot/backend-timetable',
                    this.backendTimetableRequestPayload(options),
                )

                if (requestId !== this.fullGreenTimetableCountRequestId) return

                this.totalTimetableVariationCount = Number(response.data?.data?.timetable_variation_count || 0)
                this.fullGreenTimetableCount = Number(response.data?.data?.full_green_timetable_count || 0)
                this.greenTimetableCount = Number(response.data?.data?.green_timetable_count || 0)
                this.conflictTimetableCount = Number(response.data?.data?.conflict_timetable_count || 0)
                this.additionalCourseTimetableCount = Number(response.data?.data?.additional_course_timetable_count || 0)
                if (options?.calculateQualityCounters === true) {
                    this.qualityCounters = response.data?.data?.quality_counters || []
                    this.allQualityCriteriaCount = Number(response.data?.data?.all_quality_criteria_count || 0)
                    this.selectedQualityCriteriaCount = this.selectedQualityCriteriaCountFromResponse(response.data?.data || {})
                } else if (options?.preserveQualityCounters !== true) {
                    this.qualityCounters = []
                    this.allQualityCriteriaCount = null
                    this.selectedQualityCriteriaCount = null
                }
                const selectedResultTypeChanged = this.autoSelectTimetableResultType(options)
                this.normalizeTimetableResultCounters()

                if (selectedResultTypeChanged && this.timetableResultCount(this.selectedTimetableResultType) > 0) {
                    this.qualityCounters = []
                    this.allQualityCriteriaCount = null
                    this.selectedQualityCriteriaCount = null
                    this.generatedTimetables = []
                    await this.loadFullGreenTimetableCount()

                    return
                }

                const additionalCourseFilterChanged = this.autoSelectAdditionalCourseTimetableFilter(options)
                this.normalizeTimetableResultCounters()

                if (additionalCourseFilterChanged && this.timetableResultCount(this.selectedTimetableResultType) > 0) {
                    this.qualityCounters = []
                    this.allQualityCriteriaCount = null
                    this.selectedQualityCriteriaCount = null
                    this.generatedTimetables = []
                    await this.loadFullGreenTimetableCount()

                    return
                }

                const selectedTimetable = response.data?.data?.selected_timetable
                    ? this.backendTimetableFromResponse(response.data.data.selected_timetable)
                    : null

                if (this.shouldLoadConflictTimetableForRequiredAdditionalCourses(selectedTimetable)) {
                    this.selectedTimetableResultType = 'conflict'
                    this.setTimetableResultCounter('conflict', 1)
                    this.qualityCounters = []
                    this.allQualityCriteriaCount = null
                    this.selectedQualityCriteriaCount = null
                    this.generatedTimetables = []
                    await this.loadFullGreenTimetableCount()

                    return
                }

                this.generatedTimetables = selectedTimetable ? [selectedTimetable] : []
                this.additionalCourseSelectionChangedAfterTimetable = false
                this.applySelectedQualityMetricsToCounters()
                this.refreshQualityCountersAfterTimetableLoad(options)
            } catch {
                if (requestId !== this.fullGreenTimetableCountRequestId) return

                this.totalTimetableVariationCount = null
                this.fullGreenTimetableCount = null
                this.greenTimetableCount = null
                this.conflictTimetableCount = null
                this.additionalCourseTimetableCount = null
                this.qualityCounters = []
                this.allQualityCriteriaCount = null
                this.selectedQualityCriteriaCount = null
                this.generatedTimetables = []
                this.normalizeTimetableResultCounters()
                this.fullGreenTimetableCountError = this.timetableCalculationErrorMessage(error)
            } finally {
                if (requestId === this.fullGreenTimetableCountRequestId) {
                    this.fullGreenTimetableCountLoading = false
                }
            }
        },
        timetableCalculationErrorMessage(error) {
            const errors = error?.response?.data?.errors || {}
            const firstError = Object.values(errors)
                .flat()
                .find(message => String(message || '').trim())
            const responseMessage = error?.response?.data?.message

            return firstError || responseMessage || 'Die Anzahl der Stundenpläne konnte nicht berechnet werden.'
        },
        async loadQualityCountersForSelectedTimetableType() {
            if (!this.timetableCalculationReady() || !this.activeQualityCriterionRows.length) {
                this.qualityCounters = []
                this.allQualityCriteriaCount = null
                this.selectedQualityCriteriaCount = null
                this.qualityCountersLoading = false

                return
            }

            const requestId = this.qualityCountersRequestId + 1
            this.qualityCountersRequestId = requestId
            this.qualityCountersLoading = true

            try {
                const response = await axios.post(
                    '/api/admin/students-timetables/robot/quality-counters',
                    this.backendTimetableRequestPayload(),
                )

                if (requestId !== this.qualityCountersRequestId) return

                this.qualityCounters = response.data?.data?.quality_counters || []
                this.allQualityCriteriaCount = Number(response.data?.data?.all_quality_criteria_count || 0)
                this.selectedQualityCriteriaCount = this.selectedQualityCriteriaCountFromResponse(response.data?.data || {})
                this.applySelectedQualityMetricsToCounters()
            } catch {
                if (requestId !== this.qualityCountersRequestId) return

                this.qualityCounters = []
                this.allQualityCriteriaCount = null
                this.selectedQualityCriteriaCount = null
            } finally {
                if (requestId === this.qualityCountersRequestId) {
                    this.qualityCountersLoading = false
                }
            }
        },
        refreshQualityCountersAfterTimetableLoad(options = {}) {
            if (options?.calculateQualityCounters === true || options?.preserveQualityCounters === true) {
                return
            }

            if (!this.activeQualityCriterionRows.length) {
                this.qualityCounters = []
                this.allQualityCriteriaCount = null
                this.selectedQualityCriteriaCount = null

                return
            }

            this.loadQualityCountersForSelectedTimetableType()
        },
        autoSelectTimetableResultType(options = {}) {
            const selectableResultTypes = ['full_green', 'green']

            if (
                options?.preferFullGreen === true
                && selectableResultTypes.includes('full_green')
                && this.timetableResultCount('full_green') > 0
                && this.selectedTimetableResultType !== 'full_green'
            ) {
                this.selectedTimetableResultType = 'full_green'

                return true
            }

            if (
                selectableResultTypes.includes(this.selectedTimetableResultType)
                && this.timetableResultCount(this.selectedTimetableResultType) > 0
            ) {
                return false
            }

            const nextResultType = selectableResultTypes
                .find(type => this.timetableResultCount(type) > 0)

            if (nextResultType) {
                this.selectedTimetableResultType = nextResultType

                return true
            }

            const wasFullGreenSelected = this.selectedTimetableResultType === 'full_green'
            this.selectedTimetableResultType = 'full_green'

            return !wasFullGreenSelected
        },
        autoSelectAdditionalCourseTimetableFilter(options = {}) {
            if (options?.skipAdditionalCourseDefaultSelection === true) return false
            if (this.additionalCourseTimetableRequired === true) return false
            if (!this.selectedAdditionalCourses.length) return false
            if (this.additionalCourseTimetableCount === null) return false
            if (Number(this.additionalCourseTimetableCount || 0) <= 0) return false
            if (this.timetableResultCount(this.selectedTimetableResultType) <= 0) return false

            this.additionalCourseTimetableRequired = true
            this.setTimetableResultCounter(this.selectedTimetableResultType, 1)

            return true
        },
        shouldLoadConflictTimetableForRequiredAdditionalCourses(timetable) {
            return false
        },
        timetableIncludesSelectedAdditionalCourses(timetable) {
            if (!this.selectedAdditionalCourses.length) return true
            if (!timetable) return false

            const scheduledKeys = this.timetableScheduledCourseComparisonKeys(timetable)

            return this.selectedAdditionalCourses.every(course =>
                this.courseComparisonKeys(course).some(courseKey => scheduledKeys.has(courseKey)),
            )
        },
        timetableScheduledCourseComparisonKeys(timetable) {
            return new Set([
                ...Object.values(timetable?.slots || {}),
                ...Object.values(timetable?.slots || {}).flatMap(slot => [
                    ...(Array.isArray(slot?.sameSlotEntries) ? slot.sameSlotEntries : []),
                    ...(Array.isArray(slot?.conflicts) ? slot.conflicts : []),
                ]),
                ...(Array.isArray(timetable?.occasionalAppointments) ? timetable.occasionalAppointments : []),
            ].flatMap(course => this.courseComparisonKeys(course)))
        },
        backendCountCardSelectable(type) {
            return this.backendVariationCountsAvailable
                && !this.fullGreenTimetableCountLoading
                && ['full_green', 'green'].includes(type)
                && this.isTimetableResultTypeSelectable(type)
        },
        selectBackendCountCard(type) {
            if (!this.backendCountCardSelectable(type)) return

            this.setSelectedTimetableResultType(type, true)
        },
        setSelectedTimetableResultType(type, selected) {
            if (selected === false) return
            if (!this.isTimetableResultTypeSelectable(type)) return

            const resultTypeChanged = this.selectedTimetableResultType !== type
            const filterChanged = this.additionalCourseTimetableRequired === true
            this.selectedTimetableResultType = type
            this.additionalCourseTimetableRequired = false
            if (resultTypeChanged || filterChanged) {
                this.setTimetableResultCounter(type, 1)
            }

            if (this.timetableResultCount(type) > 0) {
                this.loadFullGreenTimetableCount({ skipAdditionalCourseDefaultSelection: true })

                return
            }

            this.generatedTimetables = []
        },
        setAdditionalCourseTimetableRequired(selected) {
            if (selected === true && !this.isAdditionalCourseTimetableFilterSelectable()) return

            const requiredStateChanged = this.additionalCourseTimetableRequired !== (selected === true)
            this.additionalCourseTimetableRequired = selected === true
            if (requiredStateChanged) {
                this.setTimetableResultCounter(this.selectedTimetableResultType, 1)
            }
            this.normalizeTimetableResultCounters()
            this.saveLastRobotState()

            if (this.timetableCalculationReady()) {
                this.loadFullGreenTimetableCount({
                    skipAdditionalCourseDefaultSelection: selected !== true,
                })
            }
        },
        timetableResultCardSelected(type) {
            return this.additionalCourseTimetableRequired !== true
                && this.selectedTimetableResultType === type
        },
        noBaseTimetableResultReasons() {
            if (!this.configuredCourseGroups.length) {
                return ['Es sind keine TT-Stunden für diese Schule und dieses Schuljahr importiert.']
            }

            const courseReasons = this.timetableCandidateSets(this.selectedCourses)
                .filter(candidateSet => !candidateSet.options?.length)
                .map(candidateSet => this.candidateSetProblemMessage(candidateSet))
                .filter(Boolean)

            if (courseReasons.length) {
                const visibleReasons = courseReasons.slice(0, 4)
                const hiddenReasonsCount = courseReasons.length - visibleReasons.length

                if (hiddenReasonsCount > 0) {
                    visibleReasons.push(`Weitere ${this.formatNumber(hiddenReasonsCount)} Kurse haben keine passende TT-Stunde.`)
                }

                return visibleReasons
            }

            return ['Die ausgewählten Kurse passen mit den gewählten Tagen, Stunden und gesperrten Zeiten in keiner Kombination zusammen.']
        },
        selectedTimetableResultPluralTitle() {
            return {
                full_green: 'voller grüner Stundenplan',
                green: 'grüner Stundenplan',
                conflict: 'Stundenplan mit Konflikten',
            }[this.selectedTimetableResultType] || 'Stundenplan'
        },
        selectedAdditionalCourseLabels() {
            return this.selectedAdditionalCourses
                .map(course => [course.code, course.name].filter(Boolean).join(' '))
                .filter(Boolean)
                .join(', ')
        },
        activeQualityCriterionLabels() {
            return this.activeQualityCriterionRows
                .map(criterion => criterion.label)
                .filter(Boolean)
                .join(', ')
        },
        isTimetableResultTypeSelectable(type) {
            if (this.backendVariationCountsAvailable) {
                return ['full_green', 'green'].includes(type)
                    && this.timetableResultCount(type) > 0
            }

            if (type === 'conflict') {
                return false
            }

            return this.timetableResultCount(type) > 0
        },
        isAdditionalCourseTimetableFilterSelectable() {
            if (this.additionalCourseTimetableRequired === true) {
                return this.selectedAdditionalCourses.length > 0
                    && !this.fullGreenTimetableCountLoading
            }

            return this.selectedAdditionalCourses.length > 0
                && !this.fullGreenTimetableCountLoading
                && this.additionalCourseTimetableCount !== null
                && Number(this.additionalCourseTimetableCount || 0) > 0
        },
        moveTimetableResultCounter(type, direction) {
            const previousCounter = this.timetableResultCounter(type)
            this.setTimetableResultCounter(type, this.timetableResultCounter(type) + direction)

            if (type === this.selectedTimetableResultType && previousCounter !== this.timetableResultCounter(type)) {
                this.loadFullGreenTimetableCount({
                    preserveGeneratedTimetable: true,
                    preserveQualityCounters: true,
                })

                if (!(this.qualityCounters || []).length && (this.activeQualityCriterionRows || []).length) {
                    this.loadQualityCountersForSelectedTimetableType()
                }
            }
        },
        setTimetableResultCounter(type, value) {
            const counter = this.normalizedTimetableResultCounter(value, this.timetableResultCounterLimitForCounter(type))

            if (type === 'conflict') {
                this.conflictTimetableNumber = counter

                return
            }

            if (type === 'green') {
                this.greenTimetableNumber = counter

                return
            }

            this.fullGreenTimetableNumber = counter
        },
        timetableResultCounterLimitForCounter(type) {
            if (type === this.selectedTimetableResultType && this.qualityCriteriaResultFilterActive) {
                return this.selectedTimetableResultCount
            }

            return this.timetableResultCounterLimit(type)
        },
        normalizeTimetableResultCounters() {
            this.setTimetableResultCounter('full_green', this.fullGreenTimetableNumber)
            this.setTimetableResultCounter('green', this.greenTimetableNumber)
            this.setTimetableResultCounter('conflict', this.conflictTimetableNumber)
        },
        timetableResultCounter(type) {
            if (type === 'conflict') return this.conflictTimetableNumber

            return type === 'green' ? this.greenTimetableNumber : this.fullGreenTimetableNumber
        },
        timetableResultCount(type) {
            const count = {
                full_green: this.fullGreenTimetableCount,
                green: this.greenTimetableCount,
                conflict: this.conflictTimetableCount,
            }[type]

            return Math.max(0, Number(count || 0))
        },
        timetableResultCounterLimit(type) {
            if (
                this.additionalCourseTimetableRequired === true
                && type === this.selectedTimetableResultType
                && this.selectedAdditionalCourses.length > 0
                && this.additionalCourseTimetableCount !== null
            ) {
                return Math.max(0, Number(this.additionalCourseTimetableCount || 0))
            }

            return this.timetableResultCount(type)
        },
        normalizedTimetableResultCounter(value, count) {
            if (count <= 0) return 1

            const numericValue = Number(value)
            if (!Number.isFinite(numericValue)) return 1

            return Math.min(Math.max(Math.trunc(numericValue), 1), count)
        },
        backendTimetableFromResponse(timetable) {
            const normalizedTimetable = {
                key: timetable?.key || `backend-${this.selectedTimetableResultType}`,
                number: Number(timetable?.number || this.timetableResultCounter(this.selectedTimetableResultType)),
                type: timetable?.type || this.selectedTimetableResultType,
                metrics: timetable?.metrics || {},
                statusMessage: String(timetable?.statusMessage || '').trim(),
                additionalCoursesAccepted: timetable?.additionalCoursesAccepted === true,
                qualityCriteria: Array.isArray(timetable?.qualityCriteria) ? timetable.qualityCriteria : [],
                slots: timetable?.slots || {},
                occasionalAppointments: Array.isArray(timetable?.occasionalAppointments)
                    ? timetable.occasionalAppointments
                    : [],
                problems: Array.isArray(timetable?.problems) ? timetable.problems : [],
                acceptedAdditionalCourseCount: Number(timetable?.acceptedAdditionalCourseCount || 0),
                missingAdditionalCourses: Array.isArray(timetable?.missingAdditionalCourses)
                    ? timetable.missingAdditionalCourses
                    : [],
            }

            return {
                ...normalizedTimetable,
                selectedOccasionalAppointmentGroups: this.selectedOccasionalAppointmentGroupsForTimetable(
                    normalizedTimetable,
                ),
            }
        },
        selectedOccasionalAppointmentGroupsForTimetable(timetable) {
            return this.groupedOccasionalAppointments(timetable)
                .reduce((selections, group) => {
                    const courseKey = this.occasionalAppointmentGroupCourseKey(group)

                    if (courseKey && !selections[courseKey]) {
                        selections[courseKey] = group.key
                    }

                    return selections
                }, {})
        },
        defaultRobotState() {
            return {
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
                student: {
                    studentCode: null,
                },
                deselectedCourseKeys: [],
                deselectedCourseGroupKeys: [],
                selectedAdditionalCourseKeys: [],
                additionalCourseTimetableRequired: false,
                selectedCourseKeys: null,
            }
        },
        currentRobotState() {
            return {
                selection: { ...this.selection },
                constraints: this.copyConstraints(this.constraints),
                student: { ...this.studentSelection },
                deselectedCourseKeys: this.uniqueValues(this.deselectedCourseKeys),
                deselectedCourseGroupKeys: this.uniqueValues(this.deselectedCourseGroupKeys),
                selectedAdditionalCourseKeys: this.uniqueValues(this.additionalCourseSelectedKeys),
                additionalCourseTimetableRequired: this.additionalCourseTimetableRequired === true,
                selectedCourseKeys: this.selectedCourseKeysForState(),
                selectedCourseCodes: this.selectedCourseCodesForState(),
                selectedTimetableResultType: this.selectedTimetableResultType,
                qualityCriteriaResultFilterEnabled: this.qualityCriteriaResultFilterEnabled === true,
                qualitySummaryCheckedKeys: [...this.qualitySummaryCheckedKeys],
            }
        },
        applyRobotState(state) {
            const defaults = this.defaultRobotState()
            const studentCode = this.normalizedStudentCode(state?.student?.studentCode)
            const selectedStudent = this.studentByCode(studentCode)
            const studentDefaults = selectedStudent
                ? this.selectedStudentDefaultSelection(selectedStudent, { includeCourseHistory: true })
                : null

            this.robotStateRestoring = true
            try {
                this.selection = {
                    ...defaults.selection,
                    ...(state?.selection || {}),
                    semester: this.numberOrDefault(state?.selection?.semester, defaults.selection.semester),
                    ...(studentDefaults || {}),
                }
                this.constraints = {
                    availableWeekdays: this.numberValuesOrDefault(
                        state?.constraints?.availableWeekdays,
                        defaults.constraints.availableWeekdays,
                    ),
                    excludedWeekdayTimes: this.uniqueValues(state?.constraints?.excludedWeekdayTimes),
                    availableTimes: this.numberValuesOrDefault(
                        state?.constraints?.availableTimes,
                        defaults.constraints.availableTimes,
                    ),
                }
                this.studentSelection = {
                    studentCode,
                }
                this.deselectedCourseKeys = this.restoredDeselectedCourseKeys(state)
                this.deselectedCourseGroupKeys = this.uniqueValues(state?.deselectedCourseGroupKeys)
                this.additionalCourseSelectedKeys = this.uniqueValues(state?.selectedAdditionalCourseKeys)
                this.additionalCourseTimetableRequired = state?.additionalCourseTimetableRequired === true
                this.studentSelectionDefaultsPendingCode = selectedStudent ? studentCode : null
                this.restoredTimetableResultType = state?.selectedTimetableResultType || null
                this.restoredQualityCriteriaFilterEnabled = state?.qualityCriteriaResultFilterEnabled === true
                this.restoredQualitySummaryCheckedKeys = Array.isArray(state?.qualitySummaryCheckedKeys) ? [...state.qualitySummaryCheckedKeys] : []
            } finally {
                this.robotStateRestoring = false
            }
        },
        restoredDeselectedCourseKeys(state) {
            const selectedCourseKeys = this.uniqueValues(state?.selectedCourseKeys)
            const selectedCourseCodes = this.uniqueValues(state?.selectedCourseCodes)

            if (Array.isArray(state?.selectedCourseKeys) || Array.isArray(state?.selectedCourseCodes)) {
                const courses = Array.isArray(this.availableCourses) ? this.availableCourses : []

                return courses
                    .filter(course =>
                        !selectedCourseKeys.includes(course.key)
                            && !selectedCourseCodes.includes(course.code),
                    )
                    .map(course => course.key)
            }

            return this.uniqueValues(state?.deselectedCourseKeys)
        },
        selectedCourseKeysForState() {
            const courses = Array.isArray(this.availableCourses) ? this.availableCourses : []

            return courses
                .filter(course => this.courseSelected(course))
                .map(course => course.key)
        },
        selectedCourseCodesForState() {
            const courses = Array.isArray(this.availableCourses) ? this.availableCourses : []

            return courses
                .filter(course => this.courseSelected(course))
                .map(course => course.code)
        },
        robotSchoolyearId() {
            return this.config?.selected_schoolyear?.id || this.selected_schoolyear?.id || 'default'
        },
        robotStorageKey(schoolyearId = this.robotSchoolyearId()) {
            return `${ROBOT_TIMETABLE_STORAGE_KEY_PREFIX}:${schoolyearId || 'default'}`
        },
        robotStorageKeys() {
            return [
                this.robotStorageKey(),
                this.robotStorageKey('default'),
            ].filter((key, index, keys) => keys.indexOf(key) === index)
        },
        robotStorage() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return null
            }

            return window.localStorage
        },
        overviewTimetableStorageKey() {
            return `${OVERVIEW_TIMETABLE_STORAGE_KEY_PREFIX}:${this.robotSchoolyearId() || 'default'}`
        },
        overviewTimetableStateForSelectedRobotTimetable() {
            const courseGroupKeys = this.selectedRobotTimetableCourseGroupKeys()
            const distanceLearningCourseGroupKeys = this.selectedRobotTimetableDistanceLearningCourseGroupKeys()

            return {
                activeCourseGroupFilterKeys: courseGroupKeys,
                activeDistanceLearningCourseGroupKeys: distanceLearningCourseGroupKeys,
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
                showSaturday: this.selectedRobotTimetableHasSaturday(),
                manualPanelOpen: true,
                selection: this.overviewSelectionState(),
                transferredStudentContext: this.overviewStudentContext(),
            }
        },
        overviewSelectionState() {
            return {
                semester: Number(this.selection?.semester || 1),
                religion: this.selection?.religion || 'ETH',
                language: this.selection?.language || 'L',
                branch: this.selection?.branch || 'wirtschaftskundlich',
                artsSubject: this.selection?.artsSubject || 'ME',
            }
        },
        overviewStudentContext() {
            if (!this.selectedStudent) return null

            return {
                student: {
                    studentCode: this.normalizedStudentCode(this.selectedStudent?.student_code),
                    label: this.selectedStudentLabel,
                    semesterLabel: this.studentSemesterLabel(this.selectedStudent),
                },
                courses: {
                    completed: this.overviewCompletedCourseItems(),
                    missing: this.overviewCourseItems(this.studentMissingCourses),
                    planned: this.overviewCourseItems(this.studentPlannedCourses),
                    additional: this.overviewCourseItems(this.studentAdditionalCourses),
                },
            }
        },
        overviewCompletedCourseItems() {
            return (Array.isArray(this.studentCompletedCourses) ? this.studentCompletedCourses : [])
                .map((course, index) => {
                    const code = String(course?.subject || course?.code || '').trim()
                    const grade = String(course?.grade || '').trim()

                    return {
                        key: `completed-${code || index}-${grade || index}`,
                        code,
                        label: code,
                        meta: grade,
                    }
                })
                .filter(course => course.label)
        },
        overviewCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const code = String(course?.code || course?.subject || '').trim()
                    const name = String(course?.name || course?.title || '').trim()
                    const hours = Number(course?.hours || 0)

                    return {
                        key: course?.key || `${code || index}-${index}`,
                        code,
                        name,
                        label: code || name,
                        meta: hours ? `${this.formatHours(hours)} Std.` : '',
                    }
                })
                .filter(course => course.label)
        },
        overtakeSelectedTimetableToOverview() {
            const overviewTimetableState = this.overviewTimetableStateForSelectedRobotTimetable()

            if (!overviewTimetableState.activeCourseGroupFilterKeys.length) return

            try {
                this.robotStorage()?.setItem(
                    this.overviewTimetableStorageKey(),
                    JSON.stringify(overviewTimetableState),
                )
            } catch {
                this.generationError = 'Der Stundenplan konnte nicht für die Übersicht übernommen werden.'

                return
            }

            this.$emit('timetable-overtaken', overviewTimetableState)

            if (this.embeddedCourseCardsOnly) {
                return
            }

            this.openOverviewTimetable()
        },
        openOverviewTimetable() {
            if (this.$router?.push) {
                this.$router.push({ path: OVERVIEW_TIMETABLE_PATH })

                return
            }

            if (typeof window !== 'undefined') {
                window.location.href = OVERVIEW_TIMETABLE_PATH
            }
        },
        selectedRobotTimetableCourseGroupKeys() {
            return this.selectedRobotTimetableCourseGroups()
                .map(courseGroup => courseGroup?.key)
                .filter(Boolean)
        },
        selectedRobotTimetableDistanceLearningCourseGroupKeys() {
            const courses = [
                ...(Array.isArray(this.selectedCourses) ? this.selectedCourses : []),
                ...(Array.isArray(this.selectedAdditionalCourses) ? this.selectedAdditionalCourses : []),
            ].filter((course, index, courses) =>
                course?.key && courses.findIndex(candidate => candidate?.key === course.key) === index,
            )

            return this.selectedRobotTimetableCourseGroups()
                .filter((courseGroup) => {
                    const course = courses.find(candidate => this.courseGroupMatchesCourse(courseGroup, candidate))
                    if (!course) return false

                    return this.courseGroupDistanceLearning(course, {
                        title: this.courseGroupOptionLabel(courseGroup),
                    })
                })
                .map(courseGroup => courseGroup?.key)
                .filter(Boolean)
        },
        selectedRobotTimetableHasSaturday() {
            return this.selectedRobotTimetableCourseGroups()
                .some(courseGroup => Number(courseGroup?.weekday) === 6)
        },
        selectedRobotTimetableCourseGroups() {
            const timetable = this.selectedRobotTimetable
            if (!timetable) return []

            const slotCourseGroups = Object.values(timetable.slots || {})
                .flatMap(slot => [
                    slot?.courseGroup,
                    ...(Array.isArray(slot?.sameSlotEntries)
                        ? slot.sameSlotEntries.map(entry => entry?.courseGroup)
                        : []),
                    ...(Array.isArray(slot?.conflicts)
                        ? slot.conflicts.map(conflict => conflict?.courseGroup)
                        : []),
                ])

            const appointmentCourseGroups = (Array.isArray(timetable.occasionalAppointments)
                ? timetable.occasionalAppointments
                : [])
                .filter(appointment => this.occasionalAppointmentSelectedForTimetable(timetable, appointment))
                .flatMap(appointment => this.courseGroupsForRobotAppointment(appointment))

            return this.uniqueCourseGroupsByKey([
                ...slotCourseGroups,
                ...appointmentCourseGroups,
            ])
        },
        courseGroupsForRobotAppointment(appointment) {
            if (appointment?.courseGroup?.key) return [appointment.courseGroup]

            const configuredCourseGroups = Array.isArray(this.configuredCourseGroups)
                ? this.configuredCourseGroups
                : []
            const appointmentLabel = String(appointment?.sourceLabel || '').trim()
            const appointmentWeekday = Number(appointment?.weekday)
            const appointmentHour = Number(appointment?.hour)
            const appointmentCourseCodes = this.courseCodeAliases({
                code: appointment?.courseKey || appointment?.code,
            })

            return configuredCourseGroups.filter(courseGroup => {
                if (appointmentWeekday && Number(courseGroup?.weekday) !== appointmentWeekday) return false
                if (appointmentHour && Number(courseGroup?.hour) !== appointmentHour) return false

                const courseGroupLabels = [
                    this.courseGroupSourceLabel(courseGroup),
                    this.courseGroupOptionLabel(courseGroup),
                    courseGroup?.class_name,
                    courseGroup?.display_label,
                    courseGroup?.title,
                ]
                    .map(label => String(label || '').trim())
                    .filter(Boolean)

                const labelMatches = appointmentLabel && courseGroupLabels.includes(appointmentLabel)
                const codeMatches = appointmentCourseCodes.length
                    && this.courseGroupCodes(courseGroup).some(courseGroupCode =>
                        appointmentCourseCodes.includes(courseGroupCode),
                    )

                return labelMatches || codeMatches
            })
        },
        uniqueCourseGroupsByKey(courseGroups) {
            return Object.values((Array.isArray(courseGroups) ? courseGroups : []).reduce((groups, courseGroup) => {
                const key = String(courseGroup?.key || '').trim()
                if (!key) return groups

                groups[key] ??= courseGroup

                return groups
            }, {}))
        },
        saveLastRobotState() {
            try {
                const storage = this.robotStorage()
                const robotState = JSON.stringify(this.currentRobotState())

                this.robotStorageKeys().forEach(key => {
                    storage?.setItem(key, robotState)
                })
            } catch {
                // Ignore unavailable or full browser storage.
            }
        },
        restoreLastRobotState() {
            try {
                const storage = this.robotStorage()
                const storedState = this.robotStorageKeys()
                    .map(key => storage?.getItem(key))
                    .find(Boolean)

                if (!storedState) {
                    this.applyRobotState(this.defaultRobotState())

                    return
                }

                this.applyRobotState(JSON.parse(storedState))
            } catch {
                this.applyRobotState(this.defaultRobotState())
            }
        },
        restoreTimetablePreferencesAfterRestore() {
            if (!this.selectedStudent || !(this.selectedCourses || []).length) return

            const savedResultType = this.restoredTimetableResultType
            const savedFilterEnabled = this.restoredQualityCriteriaFilterEnabled
            const savedCheckedKeys = this.restoredQualitySummaryCheckedKeys

            if (Array.isArray(savedCheckedKeys) && savedCheckedKeys.length) {
                this.qualitySummaryCheckedKeys = savedCheckedKeys
            }
            if (savedFilterEnabled) {
                this.qualityCriteriaResultFilterEnabled = true
            }
            if (savedResultType) {
                this.selectedTimetableResultType = savedResultType
            }

        },
        studentByCode(studentCode) {
            const normalizedStudentCode = this.normalizedStudentCode(studentCode)
            if (!normalizedStudentCode) return null

            return (Array.isArray(this.robotStudents) ? this.robotStudents : [])
                .find(student => String(student.student_code) === normalizedStudentCode) || null
        },
        numberValuesOrDefault(values, defaultValues) {
            if (!Array.isArray(values)) return [...defaultValues]

            const numberValues = values
                .map(value => Number(value))
                .filter(Number.isFinite)

            return this.uniqueValues(numberValues)
        },
        numberOrDefault(value, defaultValue) {
            const numberValue = Number(value)

            return Number.isFinite(numberValue) ? numberValue : defaultValue
        },
        uniqueValues(values) {
            if (!Array.isArray(values)) return []

            return [...new Set(values.filter(value => value !== null && value !== undefined && value !== ''))]
        },
        selectedOptionTitles(options, values, separator = '\n') {
            if (!values.length) return ''

            return values
                .map(value => this.selectedOptionTitle(options, value))
                .join(separator)
        },
        selectedTimeOptionTitles(options, values, separator = '\n') {
            if (!values.length) return ''

            return this.compactTimeOptionRanges(options, values).join(separator)
        },
        compactTimeOptionRanges(options, values) {
            const selectedTimes = values
                .map(value => options.find(option => String(option.value) === String(value)))
                .filter(Boolean)
                .sort((firstTime, secondTime) => Number(firstTime.value) - Number(secondTime.value))

            return selectedTimes
                .reduce((ranges, time) => {
                    const currentRange = ranges.at(-1)
                    const previousTime = currentRange?.at(-1)

                    if (previousTime && Number(previousTime.value) + 1 === Number(time.value)) {
                        currentRange.push(time)

                        return ranges
                    }

                    ranges.push([time])

                    return ranges
                }, [])
                .map(range => this.timeOptionRangeTitle(range))
        },
        timeOptionRangeTitle(range) {
            if (range.length < 3) {
                return range
                    .map(time => time.title)
                    .join('\n')
            }

            const firstTime = range[0]
            const lastTime = range.at(-1)
            const from = this.timeOptionStartLabel(firstTime)
            const until = this.timeOptionEndLabel(lastTime)
            const timeRange = from && until ? ` (${from} - ${until})` : ''

            return `${firstTime.value}.-${lastTime.value}. Stunde${timeRange}`
        },
        timeOptionStartLabel(time) {
            return this.timeOptionRangeParts(time)[0] || ''
        },
        timeOptionEndLabel(time) {
            return this.timeOptionRangeParts(time)[1] || ''
        },
        timeOptionRangeParts(time) {
            const [, range] = String(time?.title || '').match(/\(([^)]+)\)/u) || []

            return String(range || '')
                .split('-')
                .map(value => value.trim())
        },
        constraintSelected(key, value) {
            return this.constraintValueSelected(this.constraints, key, value)
        },
        draftConstraintSelected(key, value) {
            return this.constraintValueSelected(this.constraintsDraft, key, value)
        },
        constraintValueSelected(source, key, value) {
            const values = Array.isArray(source?.[key]) ? source[key] : []

            return values.some(item => String(item) === String(value))
        },
        toggleConstraint(key, value) {
            this.toggleConstraintValue(this.constraints, key, value)
        },
        toggleDraftConstraint(key, value) {
            this.toggleConstraintValue(this.constraintsDraft, key, value)
        },
        toggleConstraintValue(source, key, value) {
            source[key] ??= []

            const selectedIndex = source[key].findIndex(item => String(item) === String(value))

            if (selectedIndex === -1) {
                source[key].push(value)

                return
            }

            source[key].splice(selectedIndex, 1)
            this.removeOverlappingExcludedWeekdayTimes(source, key, value)
        },
        removeOverlappingExcludedWeekdayTimes(source, key, value) {
            if (key !== 'availableTimes' || !Array.isArray(source?.excludedWeekdayTimes)) {
                return
            }

            source.excludedWeekdayTimes = source.excludedWeekdayTimes.filter(weekdayTime => {
                const [, time] = String(weekdayTime || '').split('-')

                return String(time) !== String(value)
            })
        },
        weekdayTimeValue(weekday, time) {
            return `${weekday}-${time}`
        },
        slotKey(weekday, time) {
            return `${weekday}-${time}`
        },
        robotTimetableSlot(weekday, time) {
            return this.selectedRobotTimetable?.slots?.[this.slotKey(weekday, time)] || null
        },
        robotTimetableDisplaySlot(weekday, time) {
            return this.displaySlotForGeneratedSlot(this.robotTimetableSlot(weekday, time))
        },
        displaySlotForGeneratedSlot(slot) {
            if (slot?.isOccasional !== true) return slot

            return this.generatedSlotConflictBlocksIncludingRegular(slot)[0] || null
        },
        robotTimetableOccasionalMarkers(weekday, time) {
            if (!this.selectedRobotTimetable) return []

            return this.generatedCellOccasionalMarkers(this.selectedRobotTimetable, weekday, time)
        },
        robotTimetableCellClasses(weekday, time) {
            const slot = this.robotTimetableDisplaySlot(weekday, time)
            const occasionalMarkers = this.robotTimetableOccasionalMarkers(weekday, time)

            return {
                'robot-generated-cell--filled': Boolean(slot),
                'robot-generated-cell--additional': slot?.isAdditionalCourse === true,
                'robot-generated-cell--conflict': this.slotHasVisualConflict(slot),
                'robot-generated-cell--has-occasional': occasionalMarkers.length > 0,
                'robot-generated-cell--unavailable': !this.weekdayTimeAvailable(weekday, time),
            }
        },
        slotHasVisualConflict(slot) {
            return Boolean(slot)
                && this.generatedSlotConflictBlocks(slot).length > 0
        },
        weekdayTimeAvailable(weekday, time) {
            return this.weekdayTimeAvailableFor(this.constraints, weekday, time)
        },
        draftWeekdayTimeAvailable(weekday, time) {
            return this.weekdayTimeAvailableFor(this.constraintsDraft, weekday, time)
        },
        weekdayTimeAvailableFor(source, weekday, time) {
            return this.constraintValueSelected(source, 'availableWeekdays', weekday)
                && this.constraintValueSelected(source, 'availableTimes', time)
                && !this.constraintValueSelected(source, 'excludedWeekdayTimes', this.weekdayTimeValue(weekday, time))
        },
        toggleWeekdayTime(weekday, time) {
            if (!this.constraintSelected('availableWeekdays', weekday) || !this.constraintSelected('availableTimes', time)) {
                return
            }

            this.toggleConstraint('excludedWeekdayTimes', this.weekdayTimeValue(weekday, time))
        },
        toggleDraftWeekdayTime(weekday, time) {
            if (!this.draftConstraintSelected('availableWeekdays', weekday) || !this.draftConstraintSelected('availableTimes', time)) {
                return
            }

            this.toggleDraftConstraint('excludedWeekdayTimes', this.weekdayTimeValue(weekday, time))
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
                    occasionalAppointments: [],
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

            const bestCombination = rankedCombinations[0]
            const bestTierCombinations = rankedCombinations.filter(combination =>
                this.timetableCombinationMatchesBestTier(combination, bestCombination),
            )
            const bestCombinations = this.preferredTimetableCombinations([
                ...bestTierCombinations,
                ...this.completeRegularGreenTimetableCombinations(rankedCombinations, selectedCourses.length),
            ], selectedCourses.length)

            this.generatedTimetables = bestCombinations.map((combination, index) => {
                const timetable = {
                    key: `generated-${index + 1}`,
                    number: index + 1,
                    ...this.timetableWithOccasionalSlots(combination, candidateSets),
                }

                return {
                    ...timetable,
                    selectedOccasionalAppointmentGroups: this.defaultOccasionalAppointmentGroupSelections(timetable),
                }
            })
            this.generationProblems = this.uniqueProblems(this.generatedTimetables.flatMap(timetable => timetable.problems))
        },
        clearGeneratedTimetables(options = {}) {
            this.generationError = ''
            this.generationProblems = []
            this.generatedTimetables = []
            this.embeddedCourseSelectionExpanded = true
            this.embeddedAdditionalCourseSelectionExpanded = true
            this.additionalCourseSelectionChangedAfterTimetable = false
            this.additionalCoursePanelRetained = options?.keepAdditionalCoursePanelVisible === true
                && this.studentAdditionalCourses.length > 0
            this.clearTimetableCountResults()
        },
        returnToCourseSelectionFromGeneratedTimetable() {
            const selectedAdditionalCourseCount = new Set(
                Array.isArray(this.additionalCourseSelectedKeys) ? this.additionalCourseSelectedKeys : [],
            ).size

            this.clearGeneratedTimetables({
                keepAdditionalCoursePanelVisible: this.additionalCourseTimetableRequired === true
                    && selectedAdditionalCourseCount > 0,
            })
            this.additionalCourseTimetableRequired = false
            this.additionalCourseExtensionActionHidden = false
            this.additionalCourseSelectionChangedAfterTimetable = selectedAdditionalCourseCount > 0
            this.showCourseActionAfterCourseInteraction()
            this.saveLastRobotState()
        },
        clearTimetableCountResults() {
            this.totalTimetableVariationCount = null
            this.fullGreenTimetableCount = null
            this.greenTimetableCount = null
            this.conflictTimetableCount = null
            this.additionalCourseTimetableCount = null
            if (!(this.additionalCourseSelectedKeys || []).length) {
                this.additionalCourseTimetableRequired = false
            }
            this.qualityCounters = []
            this.allQualityCriteriaCount = null
            this.selectedQualityCriteriaCount = null
            this.qualityCriteriaResultFilterEnabled = false
            this.normalizeTimetableResultCounters()
            this.fullGreenTimetableCountError = ''
        },
        allQualityCriteriaCountLabel() {
            if (!this.activeQualityCriterionRows.length) return '-'
            if (this.allQualityCriteriaCount === null) return '-'

            return `${this.formatNumber(this.allQualityCriteriaCount)} / ${
                this.formatNumber(this.timetableResultCounterLimit(this.selectedTimetableResultType))
            }`
        },
        selectedQualityCriteriaCountFromResponse(responseData) {
            if (!Object.prototype.hasOwnProperty.call(responseData || {}, 'selected_quality_criteria_count')) {
                return null
            }

            return Number(responseData.selected_quality_criteria_count || 0)
        },
        selectedCriteriaTimetableCount() {
            if (!this.qualitySummaryCheckedKeys.length) return 0
            if (this.selectedQualityCriteriaCount !== null) {
                return Math.max(0, Number(this.selectedQualityCriteriaCount || 0))
            }

            const selectedCriterion = this.selectedQualitySummaryCriterion()

            return Math.max(0, Number(selectedCriterion?.count || 0))
        },
        qualityCriteriaResultFilterAvailable() {
            return this.selectedCriteriaTimetableCount() > 0
        },
        toggleQualityCriteriaResultFilter() {
            if (!this.qualityCriteriaResultFilterAvailable()) return

            this.setQualityCriteriaResultFilterEnabled(!this.qualityCriteriaResultFilterEnabled)
        },
        setQualityCriteriaResultFilterEnabled(enabled) {
            this.qualityCriteriaResultFilterEnabled = enabled === true && this.qualityCriteriaResultFilterAvailable()
            this.setTimetableResultCounter(this.selectedTimetableResultType, 1)
            this.normalizeTimetableResultCounters()

            if (this.timetableCalculationReady()) {
                this.loadFullGreenTimetableCount({
                    preserveGeneratedTimetable: true,
                    preserveQualityCounters: true,
                })
            }
        },
        allQualityCriteriaCountDetail() {
            if (!this.activeQualityCriterionRows.length) {
                return 'Keine Kriterien für diese Berechnung ausgewählt.'
            }

            if (this.allQualityCriteriaCount === null) {
                return 'Noch keine Berechnung.'
            }

            const selectedLabel = this.activeQualityCriterionRows.every(counter => this.qualityCounterReached(counter))
                ? 'erfüllt'
                : 'nicht erfüllt'

            return `Ausgewählt: ${selectedLabel}`
        },
        additionalCoursesAcceptedBySelectedTimetable() {
            return this.selectedRobotTimetable?.additionalCoursesAccepted === true
        },
        selectedTimetableMissingAdditionalCourses() {
            return Array.isArray(this.selectedRobotTimetable?.missingAdditionalCourses)
                ? this.selectedRobotTimetable.missingAdditionalCourses
                : []
        },
        selectedTimetableMissingAdditionalCourseLabels() {
            return this.selectedTimetableMissingAdditionalCourses()
                .map(course => [course.code, course.name].filter(Boolean).join(' '))
                .filter(Boolean)
                .join(', ')
        },
        courseUsedInSelectedTimetable(course) {
            const comparisonKeySet = this.selectedTimetableScheduledCourseKeySet instanceof Set
                ? this.selectedTimetableScheduledCourseKeySet
                : new Set(this.selectedTimetableScheduledCourses()
                    .flatMap(scheduledCourse => this.courseComparisonKeys(scheduledCourse)))

            return this.courseMatchesComparisonKeySet(course, comparisonKeySet)
        },
        selectedTimetableScheduledCourses() {
            const timetable = this.selectedRobotTimetable
            if (!timetable) return []

            const scheduledSlots = this.selectedTimetableScheduledSlotEntries(timetable)
                .filter(slot => slot?.code || slot?.key)

            const selectedAppointments = (Array.isArray(timetable.occasionalAppointments)
                ? timetable.occasionalAppointments
                : [])
                .filter(appointment => this.occasionalAppointmentSelectedForTimetable(timetable, appointment))

            return [
                ...scheduledSlots,
                ...selectedAppointments,
            ]
        },
        selectedTimetableScheduledSlotEntries(timetable) {
            return Object.values(timetable?.slots || {})
                .flatMap(slot => {
                    const displaySlot = this.displaySlotForGeneratedSlot(slot)

                    return [
                        slot,
                        displaySlot && displaySlot !== slot ? displaySlot : null,
                        ...(Array.isArray(slot?.sameSlotEntries) ? slot.sameSlotEntries : []),
                    ]
                })
                .filter(Boolean)
        },
        courseGroupUsedInSelectedTimetable(course, group) {
            const courseGroups = this.courseGroupsForCourseGroupItem(course, group)
            if (!courseGroups.length) return false

            return this.selectedTimetableScheduledCourseGroups()
                .some(scheduledCourseGroup => courseGroups
                    .some(courseGroup => this.courseGroupsRepresentSameTimetableGroup(courseGroup, scheduledCourseGroup)))
        },
        courseGroupNoLongerFitsSelectedTimetable(course, group) {
            if (!this.selectedRobotTimetable || this.courseGroupUsedInSelectedTimetable(course, group)) return false

            const courseGroups = this.courseGroupsForCourseGroupItem(course, group)
            if (!courseGroups.length) return false

            const assignedSlots = this.selectedRobotTimetable.slots || {}

            return courseGroups.some(courseGroup => this.assignedSlotConfrontsCourseGroup(assignedSlots, courseGroup))
        },
        selectedTimetableScheduledCourseGroups() {
            const timetable = this.selectedRobotTimetable
            if (!timetable) return []

            const slotCourseGroups = this.selectedTimetableScheduledSlotEntries(timetable)
                .map(slot => slot?.courseGroup)
                .filter(Boolean)

            const appointmentCourseGroups = (Array.isArray(timetable.occasionalAppointments)
                ? timetable.occasionalAppointments
                : [])
                .filter(appointment => this.occasionalAppointmentSelectedForTimetable(timetable, appointment))
                .flatMap(appointment => this.courseGroupsForRobotAppointment(appointment))

            return [
                ...slotCourseGroups,
                ...appointmentCourseGroups,
            ].filter(Boolean)
        },
        courseGroupsForCourseGroupItem(course, group) {
            if (Array.isArray(group?.courseGroups) && group.courseGroups.length) return group.courseGroups

            const configuredCourseGroups = Array.isArray(this.configuredCourseGroups)
                ? this.configuredCourseGroups
                : []

            return configuredCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourse(courseGroup, course))
                .filter(courseGroup => this.courseGroupOptionLabel(courseGroup) === group?.title)
        },
        courseGroupsRepresentSameTimetableGroup(firstCourseGroup, secondCourseGroup) {
            if (!firstCourseGroup || !secondCourseGroup) return false

            const firstKey = String(firstCourseGroup?.key || '').trim()
            const secondKey = String(secondCourseGroup?.key || '').trim()

            if (firstKey && secondKey && firstKey === secondKey) return true

            return this.courseGroupSourceLabel(firstCourseGroup) !== ''
                && this.courseGroupSourceLabel(firstCourseGroup) === this.courseGroupSourceLabel(secondCourseGroup)
        },
        courseOverlapsInSelectedTimetable(course) {
            const comparisonKeySet = this.selectedTimetableConflictCourseKeySet instanceof Set
                ? this.selectedTimetableConflictCourseKeySet
                : new Set(this.selectedTimetableConflictCourses()
                    .flatMap(conflictCourse => this.courseComparisonKeys(conflictCourse)))

            return this.courseMatchesComparisonKeySet(course, comparisonKeySet)
        },
        selectedTimetableConflictCourses() {
            return Object.values(this.selectedRobotTimetable?.slots || {})
                .map(slot => this.displaySlotForGeneratedSlot(slot) || slot)
                .filter(slot => this.slotHasVisualConflict(slot))
                .flatMap(slot => [
                    slot,
                    ...(Array.isArray(slot.sameSlotEntries) ? slot.sameSlotEntries : []),
                    ...(Array.isArray(slot.conflicts) ? slot.conflicts : []),
                ])
        },
        additionalCourseMissingInSelectedTimetable(course) {
            if (!this.additionalCourseSelected(course)) return false

            const comparisonKeySet = this.selectedTimetableMissingAdditionalCourseKeySet instanceof Set
                ? this.selectedTimetableMissingAdditionalCourseKeySet
                : new Set(this.selectedTimetableMissingAdditionalCourses()
                    .flatMap(missingCourse => this.courseComparisonKeys(missingCourse)))

            return this.courseMatchesComparisonKeySet(course, comparisonKeySet)
        },
        courseAllGroupsNoLongerFitSelectedTimetable(course) {
            if (!this.selectedRobotTimetable || this.courseUsedInSelectedTimetable(course)) return false

            const groups = this.courseGroupItems(course)

            return groups.length > 0
                && groups.every(group => this.courseGroupNoLongerFitsSelectedTimetable(course, group))
        },
        courseComparisonKeys(course) {
            return [
                course?.key,
                course?.code,
                ...(Array.isArray(course?.ttCodes) ? course.ttCodes : []),
                ...this.courseCodeAliases(course),
            ]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .map(value => this.normalizedCourseCode(value) || value)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        courseMatchesComparisonKeySet(course, comparisonKeySet) {
            if (!(comparisonKeySet instanceof Set) || !comparisonKeySet.size) return false

            return this.courseComparisonKeys(course)
                .some(courseKey => comparisonKeySet.has(courseKey))
        },
        selectedTimetableAcceptedAdditionalCourseCount() {
            if (!this.selectedAdditionalCourses.length) return 0
            if (this.additionalCoursesAcceptedBySelectedTimetable()) return this.selectedAdditionalCourses.length

            const acceptedCount = Number(this.selectedRobotTimetable?.acceptedAdditionalCourseCount)
            if (Number.isFinite(acceptedCount) && acceptedCount > 0) return acceptedCount

            return Math.max(0, this.selectedAdditionalCourses.length - this.selectedTimetableMissingAdditionalCourses().length)
        },
        additionalCourseAcceptanceLabel() {
            if (!this.selectedAdditionalCourses.length) return '-'

            return `${this.selectedTimetableAcceptedAdditionalCourseCount()} / ${this.selectedAdditionalCourses.length}`
        },
        additionalCourseAcceptanceIcon() {
            if (this.additionalCoursesAcceptedBySelectedTimetable()) return 'mdi-check-circle'
            if (this.selectedTimetableMissingAdditionalCourses().length) return 'mdi-alert-circle'

            return 'mdi-close-circle'
        },
        additionalCourseAcceptanceColor() {
            if (this.additionalCoursesAcceptedBySelectedTimetable()) return 'success'
            if (this.selectedTimetableMissingAdditionalCourses().length) return 'error'

            return 'error'
        },
        qualityCounterCountLabel(counter) {
            if (counter?.enabled !== true) return '-'

            return `${this.formatNumber(counter?.count || 0)} / ${this.formatNumber(counter?.total || 0)}`
        },
        qualityCounterFulfilledCountLabel(counter) {
            if (counter?.enabled !== true) return '-'
            if (!Object.prototype.hasOwnProperty.call(counter || {}, 'count')) return '-'

            return `${this.formatNumber(counter?.count || 0)} / ${this.formatNumber(counter?.total || 0)}`
        },
        qualityCounterSummaryLabel(counter) {
            const countLabel = this.qualityCounterFulfilledCountLabel(counter)
            const bestValueLabel = this.qualityCounterBestValueLabel(counter)
            const suffix = bestValueLabel ? ` (${bestValueLabel})` : ''

            return `${counter?.label || '-'}: ${countLabel}${suffix}`
        },
        qualityCounterCardMeta(counter) {
            if (counter?.enabled !== true) return '-'

            if (Object.prototype.hasOwnProperty.call(counter || {}, 'count')) {
                return `${this.formatNumber(counter?.count || 0)} Stundenpläne`
            }

            const selectedLabel = String(counter?.selected_label || '').trim()
            if (selectedLabel && selectedLabel !== '-') return selectedLabel

            return this.qualityCounterBestValueLabel(counter) || 'Noch keine Berechnung.'
        },
        qualityCounterBestValueLabel(counter) {
            if (counter?.enabled !== true) return ''

            const bestLabel = String(counter?.best_label || '').trim()

            if (!bestLabel || bestLabel === '-') return ''

            return bestLabel
        },
        qualityCounterDetail(counter) {
            if (counter?.enabled !== true) return 'Nicht berücksichtigt.'

            const bestLabel = String(counter?.best_label || '').trim()
            const selectedLabel = String(counter?.selected_label || '').trim()

            if (selectedLabel && selectedLabel !== '-' && bestLabel && bestLabel !== '-') {
                return `Ausgewählt: ${selectedLabel} · Bestwert: ${bestLabel}`
            }

            return bestLabel && bestLabel !== '-' ? `Bestwert: ${bestLabel}` : 'Noch keine Berechnung.'
        },
        applySelectedQualityMetricsToCounters() {
            this.qualityCounters = this.qualityCountersWithSelectedTimetableMetrics(this.qualityCounters)
        },
        qualityCountersWithSelectedTimetableMetrics(counters) {
            const metrics = this.selectedRobotTimetable?.metrics || null

            if (!metrics) return counters

            return (Array.isArray(counters) ? counters : [])
                .map(counter => this.qualityCounterWithSelectedTimetableMetrics(counter, metrics))
        },
        qualityCounterWithSelectedTimetableMetrics(counter, metrics) {
            const selectedValue = this.selectedQualityMetricValue(counter, metrics)

            return {
                ...counter,
                selected_value: selectedValue,
                selected_label: this.selectedQualityMetricLabel(counter, selectedValue),
                selected_reached: this.selectedQualityMetricReachedBest(selectedValue, counter?.best_value),
            }
        },
        selectedQualityMetricValue(counter, metrics) {
            if (!counter?.key || !metrics) return null

            if (counter.key === 'saturday_free') {
                return counter.option === 'ignore_single_date_appointments'
                    ? metrics.saturday_free_ignore_single_date_appointments === true
                    : metrics.saturday_free_all_appointments === true
            }

            if (counter.key === 'free_days') return Number(metrics.free_days || 0)
            if (counter.key === 'few_gaps') return Number(metrics.gap_count || 0)
            if (counter.key === 'starts_from_period_10') return metrics.starts_from_period_10 === true
            if (counter.key === 'ends_by_period_13') return metrics.ends_by_period_13 === true
            if (counter.key === 'prefer_distance_learning') return Number(metrics.distance_learning_count || 0)
            if (counter.key === 'avoid_distance_learning') return Number(metrics.distance_learning_count || 0)

            return null
        },
        selectedQualityMetricLabel(counter, value) {
            if (typeof value === 'boolean') return value ? 'erfüllt' : 'nicht erfüllt'
            if (!Number.isFinite(Number(value))) return '-'

            if (counter?.key === 'free_days') return `${Number(value)} freie Tage`
            if (counter?.key === 'few_gaps') return `${Number(value)} Lücken`
            if (counter?.key === 'prefer_distance_learning') return `${Number(value)} FU-Kurse`
            if (counter?.key === 'avoid_distance_learning') return `${Number(value)} FU-Kurse`

            return String(value)
        },
        selectedQualityMetricReachedBest(value, bestValue) {
            if (typeof value === 'boolean') return value === true && bestValue === true
            if (!Number.isFinite(Number(value)) || !Number.isFinite(Number(bestValue))) return false

            return Number(value) === Number(bestValue)
        },
        qualitySummaryCheckboxKey(counter) {
            return [counter?.key || '', counter?.option || ''].join('|')
        },
        selectedQualityCriterionKeys() {
            const qualityCriterionRows = this.qualityCriterionRows || []

            return this.qualitySummaryCheckedKeys
                .map(selectedKey => qualityCriterionRows.find(counter => this.qualitySummaryCheckboxKey(counter) === selectedKey)?.key)
                .filter(Boolean)
                .filter((key, index, keys) => keys.indexOf(key) === index)
        },
        selectedQualitySummaryCriterion() {
            const selectedKeys = [...this.qualitySummaryCheckedKeys].reverse()

            return selectedKeys
                .map(selectedKey => this.qualityCriterionRows.find(counter => this.qualitySummaryCheckboxKey(counter) === selectedKey))
                .find(Boolean) || null
        },
        qualitySummaryCheckboxChecked(counter) {
            return this.qualitySummaryCheckedKeys.includes(this.qualitySummaryCheckboxKey(counter))
        },
        setQualitySummaryCheckboxChecked(counter, checked) {
            const checkedKeys = new Set(this.qualitySummaryCheckedKeys)
            const checkboxKey = this.qualitySummaryCheckboxKey(counter)

            if (checked === true) {
                checkedKeys.add(checkboxKey)
            } else {
                checkedKeys.delete(checkboxKey)
            }

            this.qualitySummaryCheckedKeys = Array.from(checkedKeys)
            this.selectedQualityCriteriaCount = null
            if (!this.qualitySummaryCheckedKeys.length) {
                this.qualityCriteriaResultFilterEnabled = false
            } else if (checked === true && this.qualityCriteriaResultFilterAvailable()) {
                this.qualityCriteriaResultFilterEnabled = true
            }
            this.setTimetableResultCounter(this.selectedTimetableResultType, 1)

            if (typeof this.timetableCalculationReady === 'function' && this.timetableCalculationReady()) {
                if (this.qualityCriteriaResultFilterActive) {
                    this.loadFullGreenTimetableCount({
                        calculateQualityCounters: true,
                        preserveGeneratedTimetable: true,
                    })

                    return
                }

                this.loadQualityCountersForSelectedTimetableType()
            }
        },
        resetQualityCounterSelection() {
            this.allQualityCriteriaCount = null
            this.selectedQualityCriteriaCount = null
            this.qualityCounters = this.qualityCounters.map(counter => ({
                ...counter,
                selected_value: null,
                selected_label: '-',
                selected_reached: false,
            }))
        },
        normalizedEvaluationCriteria(criteria) {
            const normalizedCriteria = this.cloneCriteria(criteria)
                .map((criterion, index) => ({
                    ...criterion,
                    enabled: criterion.enabled === true,
                    priority: Number(criterion.priority || index + 1),
                    option: criterion.option || null,
                    options: Array.isArray(criterion.options) ? criterion.options : [],
                }))

            return this.withExclusiveDistanceLearningPreference(normalizedCriteria)
        },
        enabledEvaluationCriteriaFromSettings(criteria) {
            return this.normalizedEvaluationCriteria(criteria)
                .filter(criterion => criterion.enabled === true)
        },
        storageEvaluationCriteria(criteria) {
            return this.normalizedEvaluationCriteria(criteria)
                .map((criterion, index) => ({
                    key: criterion.key,
                    enabled: criterion.enabled === true,
                    priority: index + 1,
                    option: criterion.option || null,
                }))
        },
        evaluationCriteriaSignature(criteria) {
            return JSON.stringify(this.storageEvaluationCriteria(this.enabledEvaluationCriteriaFromSettings(criteria || [])))
        },
        withExclusiveDistanceLearningPreference(criteria) {
            const preferenceKeys = ['prefer_distance_learning', 'avoid_distance_learning']
            const enabledPreferenceKey = (criteria || [])
                .filter(criterion => preferenceKeys.includes(criterion.key) && criterion.enabled === true)
                .sort((firstCriterion, secondCriterion) => firstCriterion.priority - secondCriterion.priority)
                .map(criterion => criterion.key)
                .shift()

            if (!enabledPreferenceKey) return criteria

            return (criteria || []).map(criterion => preferenceKeys.includes(criterion.key)
                ? {
                    ...criterion,
                    enabled: criterion.key === enabledPreferenceKey,
                }
                : criterion)
        },
        setEvaluationCriterionEnabled(criterion, enabled) {
            this.evaluationCriteria = this.normalizedEvaluationCriteria(this.evaluationCriteria)
                .map((item) => {
                    if (item.key === criterion.key) {
                        return { ...item, enabled: enabled === true }
                    }

                    if (enabled === true && this.oppositeDistanceLearningPreferenceKey(criterion.key) === item.key) {
                        return { ...item, enabled: false }
                    }

                    return item
                })

            this.clearTimetableCountResults()

            if (this.timetableCalculationReady()) {
                this.loadFullGreenTimetableCount()
            }
        },
        oppositeDistanceLearningPreferenceKey(key) {
            if (key === 'prefer_distance_learning') return 'avoid_distance_learning'
            if (key === 'avoid_distance_learning') return 'prefer_distance_learning'

            return ''
        },
        timetableCalculationReady() {
            return !this.loading
                && (this.subjectRows || []).length > 0
                && (this.courseGroups || []).length > 0
                && (this.selectedCourses || []).length > 0
        },
        cloneCriteria(criteria) {
            return JSON.parse(JSON.stringify(criteria || []))
        },
        qualityCounterReached(counter) {
            if (counter?.enabled !== true) return false

            if (Object.prototype.hasOwnProperty.call(counter || {}, 'selected_reached')) {
                return counter?.selected_reached === true
            }

            return Number(counter?.count || 0) > 0
        },
        formatNumber(value) {
            return new Intl.NumberFormat('de-AT').format(Number(value || 0))
        },
        generatedSlotTitle(slot) {
            const code = String(slot?.code || '').trim()
            const sourceLabel = String(slot?.sourceLabel || this.courseGroupSourceLabel(slot?.courseGroup) || '').trim()

            if (!this.generatedSlotShowsDateRange(slot) || !sourceLabel || sourceLabel === code) {
                return code
            }

            return [sourceLabel, code].filter(Boolean).join(' / ')
        },
        generatedSlotDetails(slot) {
            const courseGroup = slot?.courseGroup || {}
            const alternativeLabels = this.generatedSlotAlternativeLabels(slot)

            if (alternativeLabels.length > 1) {
                return alternativeLabels.join('\noder\n')
            }

            const label = this.generatedSlotShowsDateRange(slot)
                ? ''
                : String(slot?.sourceLabel || this.courseGroupOptionLabel(courseGroup) || '').trim()
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

            return this.generatedSlotConflictBlocksIncludingRegular(slot)
                .filter(block => !this.generatedSlotBlockIsOccasional(block))
        },
        generatedSlotConflictBlocksIncludingRegular(slot) {
            if (!Array.isArray(slot?.conflicts) || !slot.conflicts.length) return []

            const slotBlock = this.generatedSlotConflictBlock(slot)

            return this.uniqueGeneratedSlotBlocks(slot.conflicts
                .toSorted((firstConflict, secondConflict) =>
                    String(firstConflict.sortValue || '').localeCompare(String(secondConflict.sortValue || '')),
                )
                .map(conflict => this.generatedSlotConflictBlock({
                    ...conflict,
                    showDateRangeLabel: !this.generatedSlotBlockIsOccasional(slotBlock),
                }))
                .filter(block => block.code || this.generatedSlotDetails(block))
                .filter(block => !this.generatedSlotBlocksMatch(block, slotBlock)))
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
                dateRangeLabel: source?.dateRangeLabel || '',
                showDateRangeLabel: source?.showDateRangeLabel === true,
                isOccasional: Boolean(source?.isOccasional),
                isAdditionalCourse: source?.isAdditionalCourse === true,
                isDistanceLearningCourse: source?.isDistanceLearningCourse === true,
            }
        },
        generatedSlotBlocksMatch(firstBlock, secondBlock) {
            const firstIdentity = this.generatedSlotBlockIdentity(firstBlock)
            const secondIdentity = this.generatedSlotBlockIdentity(secondBlock)

            return Boolean(firstIdentity && secondIdentity && firstIdentity === secondIdentity)
        },
        generatedSlotBlockIsOccasional(block) {
            return block?.isOccasional === true || this.isOccasionalCourseGroup(block?.courseGroup)
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
                courseGroup?.recurrence_label,
                courseGroup?.recurrence_interval,
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
        generatedSlotSameSlotBlocks(slot) {
            return Array.isArray(slot?.sameSlotEntries)
                ? this.uniqueGeneratedSlotBlocks(slot.sameSlotEntries
                    .toSorted((firstEntry, secondEntry) =>
                        String(firstEntry.sortValue || '').localeCompare(String(secondEntry.sortValue || '')),
                    )
                    .map(entry => this.generatedSlotConflictBlock(entry))
                    .map(block => ({
                        ...block,
                        showDateRangeLabel: !this.generatedSlotBlockIsOccasional(block),
                    }))
                    .filter(block => block.code || this.generatedSlotDetails(block)))
                : []
        },
        courseGroupItems(course) {
            if (this.courseGroupItemsByCourseKey instanceof Map) {
                return this.courseGroupItemsByCourseKey.get(course?.key) || []
            }

            return this.uncachedCourseGroupItems(course)
        },
        uncachedCourseGroupItems(course) {
            const configuredCourseGroups = Array.isArray(this.configuredCourseGroups)
                ? this.configuredCourseGroups
                : []
            const courseGroups = configuredCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourse(courseGroup, course))

            return this.groupedCourseGroupDetailItems(courseGroups)
        },
        courseRegularGroupItems(course) {
            return this.courseGroupDetailItems(course, false)
        },
        courseOccasionalGroupItems(course) {
            return this.courseGroupDetailItems(course, true)
        },
        courseGroupDetailItems(course, occasional) {
            const configuredCourseGroups = Array.isArray(this.configuredCourseGroups)
                ? this.configuredCourseGroups
                : []
            const courseGroups = configuredCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourse(courseGroup, course))
                .filter(courseGroup => this.isOccasionalCourseGroup(courseGroup) === occasional)

            return this.groupedCourseGroupDetailItems(courseGroups)
        },
        groupedCourseGroupDetailItems(courseGroups) {
            return Object.values(courseGroups.reduce((items, courseGroup) => {
                const title = this.courseGroupOptionLabel(courseGroup)

                items[title] ??= {
                    key: title,
                    title,
                    scheduleEntries: [],
                    courseGroups: [],
                    sortValue: this.courseGroupDetailSortValue(courseGroup),
                }

                items[title].scheduleEntries.push(this.courseGroupDetailScheduleEntry(courseGroup))
                items[title].courseGroups.push(courseGroup)

                if (this.courseGroupDetailSortValue(courseGroup).localeCompare(items[title].sortValue) < 0) {
                    items[title].sortValue = this.courseGroupDetailSortValue(courseGroup)
                }

                return items
            }, {}))
                .map(item => ({
                    ...item,
                    hasOccasional: item.scheduleEntries.some(entry => entry.occasional),
                    weekMarker: this.courseGroupCollectionWeekMarker(item.courseGroups),
                    meta: this.courseGroupDetailCombinedMeta(item.scheduleEntries),
                }))
                .sort((firstItem, secondItem) => firstItem.sortValue.localeCompare(secondItem.sortValue))
        },
        courseGroupSelected(course, group) {
            const deselectedCourseGroupKeys = this.deselectedCourseGroupKeySet instanceof Set
                ? this.deselectedCourseGroupKeySet
                : new Set(Array.isArray(this.deselectedCourseGroupKeys) ? this.deselectedCourseGroupKeys : [])

            return !deselectedCourseGroupKeys.has(this.courseGroupSelectionKey(course, group))
        },
        courseGroupSelectedByLabel(course, label) {
            const deselectedCourseGroupKeys = this.deselectedCourseGroupKeySet instanceof Set
                ? this.deselectedCourseGroupKeySet
                : new Set(Array.isArray(this.deselectedCourseGroupKeys) ? this.deselectedCourseGroupKeys : [])

            return !deselectedCourseGroupKeys.has(this.courseGroupSelectionKey(course, { title: label }))
        },
        courseDistanceLearning(course) {
            const groups = this.courseGroupItems(course)

            return groups.length > 0 && groups.every(group => this.courseGroupDistanceLearning(course, group))
        },
        courseGroupDistanceLearning(course, group) {
            const configuredCourseGroups = Array.isArray(this.configuredCourseGroups)
                ? this.configuredCourseGroups
                : []
            const matchingCourseGroups = configuredCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourse(courseGroup, course))
                .filter(courseGroup => this.courseGroupOptionLabel(courseGroup) === group?.title)

            return this.courseGroupsAreDistanceLearningCourse(course, matchingCourseGroups)
        },
        courseGroupsAreDistanceLearningCourse(course, courseGroups) {
            const requiredSlotCount = this.requiredSlotCountForCourse(course)
            const scheduledWeeklyLoad = this.courseGroupsScheduledWeeklyLoad(
                (Array.isArray(courseGroups) ? courseGroups : [])
                    .filter(courseGroup => !this.isOccasionalCourseGroup(courseGroup)),
            )

            return requiredSlotCount >= 2
                && scheduledWeeklyLoad > 0
                && Math.abs((scheduledWeeklyLoad * 2) - requiredSlotCount) < 0.001
        },
        courseGroupsScheduledWeeklyLoad(courseGroups) {
            return this.uniqueCourseGroupsBySlot(courseGroups)
                .reduce((total, courseGroup) => total + this.courseGroupWeeklySlotLoad(courseGroup), 0)
        },
        courseGroupWeeklySlotLoad(courseGroup) {
            const interval = this.courseGroupWeekInterval(courseGroup)

            return interval && interval > 0 ? 1 / interval : 1
        },
        courseWeekMarker(course) {
            const configuredCourseGroups = Array.isArray(this.configuredCourseGroups)
                ? this.configuredCourseGroups
                : []
            const matchingCourseGroups = configuredCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourse(courseGroup, course))

            return this.courseGroupCollectionWeekMarker(matchingCourseGroups)
        },
        courseGroupWeekMarker(group) {
            return String(group?.weekMarker || '').trim()
        },
        slotWeekMarker(slot) {
            return this.courseGroupWeekMarkerValue(slot?.courseGroup)
        },
        appointmentWeekMarker(appointment) {
            return this.courseGroupWeekMarkerValue(appointment?.courseGroup || appointment)
        },
        generatedSlotWeekMarker(slot, timetable = null) {
            return this.visibleTimetableEntryWeekMarker(timetable, slot, this.slotWeekMarker(slot))
        },
        generatedAppointmentWeekMarker(timetable, appointment) {
            return this.visibleTimetableEntryWeekMarker(timetable, appointment, this.appointmentWeekMarker(appointment))
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
        courseGroupCollectionWeekMarker(courseGroups) {
            const weekMarkers = (Array.isArray(courseGroups) ? courseGroups : [])
                .map(courseGroup => this.courseGroupWeekMarkerValue(courseGroup))
                .filter(Boolean)
                .filter((marker, index, markers) => markers.indexOf(marker) === index)

            return weekMarkers.length === 1 ? weekMarkers[0] : ''
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
        optionIsDistanceLearningCourse(option) {
            return this.courseGroupsAreDistanceLearningCourse(option?.course, option?.courseGroups)
        },
        courseFullySelected(course) {
            const groups = this.courseGroupItems(course)

            if (!groups.length) return this.courseSelected(course)

            return groups.every(group => this.courseGroupSelected(course, group))
        },
        coursePartiallySelected(course) {
            const groups = this.courseGroupItems(course)

            if (!groups.length) return false

            const selectedGroupsCount = groups.filter(group => this.courseGroupSelected(course, group)).length

            return selectedGroupsCount > 0 && selectedGroupsCount < groups.length
        },
        setCourseGroupSelected(course, group, selected) {
            if (this.embeddedCourseSelectionLocked) return

            this.deselectedCourseGroupKeys = Array.isArray(this.deselectedCourseGroupKeys)
                ? this.deselectedCourseGroupKeys
                : []

            const selectionKey = this.courseGroupSelectionKey(course, group)
            const deselectedIndex = this.deselectedCourseGroupKeys.indexOf(selectionKey)
            let selectionChanged = false

            if (selected && deselectedIndex !== -1) {
                this.deselectedCourseGroupKeys.splice(deselectedIndex, 1)
                selectionChanged = true
            }

            if (selected) {
                selectionChanged = this.setCourseSelectedState(course, true) || selectionChanged
            }

            if (!selected && deselectedIndex === -1) {
                this.deselectedCourseGroupKeys.push(selectionKey)
                selectionChanged = true
            }

            if (!selected && !this.courseGroupItems(course).some(courseGroup => this.courseGroupSelected(course, courseGroup))) {
                selectionChanged = this.setCourseSelectedState(course, false) || selectionChanged
            }

            if (selectionChanged) {
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            }
        },
        courseGroupSelectionKey(course, group) {
            return [
                course?.key || course?.code,
                group?.title || group?.label || '',
            ].filter(Boolean).join('|')
        },
        courseGroupSelectionKeys(course) {
            return this.courseGroupItems(course)
                .map(group => this.courseGroupSelectionKey(course, group))
        },
        courseGroupDetailScheduleEntry(courseGroup) {
            const occasional = this.isOccasionalCourseGroup(courseGroup)

            return {
                label: occasional
                    ? this.courseGroupDetailOccasionalLabel(courseGroup)
                    : this.courseGroupWeekdayTimeLabel(courseGroup),
                sortValue: occasional
                    ? this.courseGroupDetailSortValue(courseGroup)
                    : this.courseGroupRegularScheduleSortValue(courseGroup),
                weekday: Number(courseGroup?.weekday),
                hour: Number(courseGroup?.hour),
                date: this.courseGroupDates(courseGroup)[0] || '',
                dateLabel: this.courseGroupDetailDateLabel(courseGroup),
                timeFrom: this.courseGroupStartTime(courseGroup),
                timeUntil: this.courseGroupEndTime(courseGroup),
                weekMarker: this.courseGroupWeekMarkerValue(courseGroup),
                occasional,
            }
        },
        courseGroupDetailCombinedMeta(entries) {
            const regularMeta = this.compactCourseGroupScheduleEntries(entries.filter(entry => !entry.occasional))
            const occasionalLabels = this.compactCourseGroupScheduleEntryLabels(entries.filter(entry => entry.occasional))

            return [
                regularMeta,
                this.courseGroupDetailOccasionalMeta(occasionalLabels),
            ].filter(Boolean).join('\n')
        },
        courseGroupDetailOccasionalMeta(labels) {
            if (!labels.length) return ''
            if (labels.length === 1) return `Einzeltermine: ${labels[0]}`

            return `Einzeltermine:\n${labels.join('\n')}`
        },
        compactCourseGroupScheduleEntries(entries, separator = ', ') {
            return this.compactCourseGroupScheduleEntryLabels(entries).join(separator)
        },
        compactCourseGroupScheduleEntryLabels(entries) {
            const uniqueEntries = entries
                .filter(entry => entry.label)
                .filter((entry, index, values) =>
                    values.findIndex(value =>
                        value.label === entry.label
                            && value.weekMarker === entry.weekMarker,
                    ) === index,
                )
                .sort((firstEntry, secondEntry) => firstEntry.sortValue.localeCompare(secondEntry.sortValue))

            return this.courseGroupScheduleEntriesWithoutRedundantWeekMarkers(uniqueEntries)
                .reduce((ranges, entry) => {
                    const currentRange = ranges.at(-1)
                    const previousEntry = currentRange?.at(-1)

                    if (this.courseGroupScheduleEntriesAreConsecutive(previousEntry, entry)) {
                        currentRange.push(entry)

                        return ranges
                    }

                    ranges.push([entry])

                    return ranges
                }, [])
                .map(range => this.courseGroupScheduleRangeLabel(range))
        },
        courseGroupScheduleEntriesWithoutRedundantWeekMarkers(entries) {
            const weekMarkers = entries
                .map(entry => String(entry.weekMarker || '').trim())
                .filter(Boolean)
            const uniqueWeekMarkers = weekMarkers.filter((marker, index, markers) => markers.indexOf(marker) === index)

            if (
                entries.length
                && weekMarkers.length === entries.length
                && uniqueWeekMarkers.length === 1
                && uniqueWeekMarkers[0] === '1-w'
            ) {
                return entries.map(entry => ({
                    ...entry,
                    weekMarker: '',
                }))
            }

            return entries
        },
        courseGroupScheduleEntriesAreConsecutive(previousEntry, entry) {
            return previousEntry
                && Number(previousEntry.weekday) === Number(entry.weekday)
                && Number(previousEntry.hour) + 1 === Number(entry.hour)
                && (!previousEntry.occasional || previousEntry.date === entry.date)
        },
        courseGroupScheduleRangeLabel(range) {
            if (range.length === 1) return this.scheduleEntryLabelWithWeekMarker(range[0])

            const firstEntry = range[0]
            const lastEntry = range.at(-1)
            const weekdayLabel = firstEntry.occasional
                ? firstEntry.dateLabel
                : this.courseGroupWeekdayLabel(firstEntry.weekday)
            const timeLabel = firstEntry.timeFrom && lastEntry.timeUntil
                ? `${firstEntry.timeFrom}-${lastEntry.timeUntil}`
                : ''
            const hoursLabel = firstEntry.occasional && !timeLabel
                ? `${firstEntry.hour}.-${lastEntry.hour}. Stunde`
                : `${firstEntry.hour}.-${lastEntry.hour}.`

            const weekMarkers = range
                .map(entry => entry.weekMarker || '')
                .filter((marker, index, markers) => markers.indexOf(marker) === index)

            if (weekMarkers.length > 1) {
                return [
                    `${weekdayLabel} ${hoursLabel}`,
                    ...this.courseGroupScheduleRangeSegments(range),
                ].filter(Boolean).join(', ')
            }

            const weekMarker = weekMarkers[0] || ''

            if (timeLabel) return this.labelWithWeekMarker(`${weekdayLabel} ${hoursLabel}, ${timeLabel}`, weekMarker)

            return this.labelWithWeekMarker(`${weekdayLabel} ${hoursLabel}`, weekMarker)
        },
        courseGroupScheduleRangeSegments(range) {
            return range
                .reduce((segments, entry) => {
                    const currentSegment = segments.at(-1)
                    const previousEntry = currentSegment?.at(-1)

                    if (
                        previousEntry
                        && previousEntry.weekMarker === entry.weekMarker
                        && this.courseGroupScheduleEntriesAreConsecutive(previousEntry, entry)
                    ) {
                        currentSegment.push(entry)

                        return segments
                    }

                    segments.push([entry])

                    return segments
                }, [])
                .map(segment => this.courseGroupScheduleRangeSegmentLabel(segment))
        },
        courseGroupScheduleRangeSegmentLabel(segment) {
            const firstEntry = segment[0]
            const lastEntry = segment.at(-1)
            const timeLabel = firstEntry.timeFrom && lastEntry.timeUntil
                ? `${firstEntry.timeFrom}-${lastEntry.timeUntil}`
                : ''
            const hoursLabel = segment.length > 1
                ? `${firstEntry.hour}.-${lastEntry.hour}.`
                : firstEntry.label
            const label = timeLabel || hoursLabel

            return this.labelWithWeekMarker(label, firstEntry.weekMarker)
        },
        scheduleEntryLabelWithWeekMarker(entry) {
            return this.labelWithWeekMarker(entry.label, entry.weekMarker)
        },
        labelWithWeekMarker(label, weekMarker) {
            if (!weekMarker) return label

            return `${label} (${weekMarker})`
        },
        courseGroupDetailMetaEntries(courseGroup) {
            return [
                String(courseGroup?.teacher || '').trim(),
                this.courseGroupRoomsLabel(courseGroup),
            ].filter(Boolean)
        },
        courseGroupDetailSortValue(courseGroup) {
            return [
                this.courseGroupDates(courseGroup)[0] || '',
                String(courseGroup?.weekday || '').padStart(2, '0'),
                String(courseGroup?.hour || '').padStart(2, '0'),
                this.courseGroupOptionLabel(courseGroup),
            ].join('|')
        },
        courseGroupRegularScheduleSortValue(courseGroup) {
            return [
                String(courseGroup?.weekday || '').padStart(2, '0'),
                String(courseGroup?.hour || '').padStart(2, '0'),
                this.courseGroupDates(courseGroup)[0] || '',
                this.courseGroupOptionLabel(courseGroup),
            ].join('|')
        },
        courseGroupDetailMeta(courseGroup, occasional) {
            const scheduleEntry = {
                ...this.courseGroupDetailScheduleEntry(courseGroup),
                label: occasional
                    ? this.courseGroupDateTimeLabel(courseGroup)
                    : this.courseGroupWeekdayTimeLabel(courseGroup),
            }

            return [
                scheduleEntry.label,
                ...this.courseGroupDetailMetaEntries(courseGroup),
            ]
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
                .join(' · ')
        },
        courseGroupDetailItem(courseGroup, occasional) {
            return {
                key: [
                    courseGroup?.key,
                    courseGroup?.weekday,
                    courseGroup?.hour,
                    this.courseGroupDates(courseGroup).join('|'),
                    this.courseGroupOptionLabel(courseGroup),
                ].filter(Boolean).join('|'),
                title: this.courseGroupOptionLabel(courseGroup),
                meta: this.courseGroupDetailMeta(courseGroup, occasional),
                sortValue: this.courseGroupDetailSortValue(courseGroup),
            }
        },
        courseGroupWeekdayTimeLabel(courseGroup) {
            const timeOptions = Array.isArray(this.timeOptions) ? this.timeOptions : this.defaultTimeOptions()
            const time = timeOptions.find(item => Number(item.value) === Number(courseGroup?.hour))

            return [
                this.courseGroupWeekdayLabel(courseGroup?.weekday),
                time?.shortTitle || this.courseGroupTimeRangeLabel(courseGroup),
            ].filter(Boolean).join(' ')
        },
        courseGroupDetailOccasionalLabel(courseGroup) {
            const dateLabel = this.courseGroupDateTimeLabel(courseGroup)
            const hour = Number(courseGroup?.hour)

            if (!Number.isFinite(hour)) return dateLabel

            return dateLabel.replace(
                /(\d{2}:\d{2}-\d{2}:\d{2})$/u,
                `${hour}. $1`,
            )
        },
        courseGroupDetailDateLabel(courseGroup) {
            const date = this.courseGroupDates(courseGroup)[0] || ''

            return date ? this.formatDateWithWeekdayLabel(date) : this.courseGroupWeekdayLabel(courseGroup?.weekday)
        },
        courseGroupWeekdayLabel(weekday) {
            const weekdayOptions = Array.isArray(this.weekdayOptions)
                ? this.weekdayOptions
                : [
                    { shortTitle: 'Mo', value: 1 },
                    { shortTitle: 'Di', value: 2 },
                    { shortTitle: 'Mi', value: 3 },
                    { shortTitle: 'Do', value: 4 },
                    { shortTitle: 'Fr', value: 5 },
                    { shortTitle: 'Sa', value: 6 },
                ]

            return weekdayOptions.find(item => Number(item.value) === Number(weekday))?.shortTitle || weekday
        },
        courseGroupSchoolHour(courseGroup) {
            const schoolHours = Array.isArray(this.schoolHours) ? this.schoolHours : []

            return schoolHours.find(schoolHour => Number(schoolHour?.hour) === Number(courseGroup?.hour)) || null
        },
        courseGroupStartTime(courseGroup) {
            return this.formatTimeValue(this.courseGroupSchoolHour(courseGroup)?.from)
        },
        courseGroupEndTime(courseGroup) {
            return this.formatTimeValue(this.courseGroupSchoolHour(courseGroup)?.until)
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
            if (!this.courseSelectable(course)) return false

            const deselectedCourseKeys = this.deselectedCourseKeySet instanceof Set
                ? this.deselectedCourseKeySet
                : new Set(Array.isArray(this.deselectedCourseKeys) ? this.deselectedCourseKeys : [])

            if (deselectedCourseKeys.has(course.key)) return false

            const groups = this.courseGroupItems(course)

            return groups.some(group => this.courseGroupSelected(course, group))
        },
        courseSelectable(course) {
            return this.courseHasTimetableHours(course)
        },
        courseHasTimetableHours(course) {
            return this.courseGroupItems(course).length > 0
        },
        applyStudentPlannedCourseSelection() {
            if (!this.selectedStudent) return false

            const studentRequiredCourses = [
                ...(Array.isArray(this.studentMissingCourses) ? this.studentMissingCourses : []),
                ...(Array.isArray(this.studentPlannedCourses) ? this.studentPlannedCourses : []),
            ]
            const plannedCourseCodes = new Set(studentRequiredCourses
                .flatMap(course => this.courseCodeAliases(course)))
            const courses = Array.isArray(this.availableCourses) ? this.availableCourses : []
            let selectionChanged = false

            courses.forEach(course => {
                const selected = this.courseMatchesStudentPlannedCourse(course, plannedCourseCodes)

                selectionChanged = this.setCourseSelectedState(course, selected) || selectionChanged
                selectionChanged = this.setCourseGroupsSelectedState(course, selected) || selectionChanged
            })

            if (selectionChanged) {
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            }

            return selectionChanged
        },
        selectAllAvailableCourses() {
            const courses = Array.isArray(this.availableCourses) ? this.availableCourses : []
            let selectionChanged = false

            courses.forEach(course => {
                selectionChanged = this.setCourseSelectedState(course, true) || selectionChanged
                selectionChanged = this.setCourseGroupsSelectedState(course, true) || selectionChanged
            })

            if (selectionChanged) {
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            }

            return selectionChanged
        },
        resetCourseSelection() {
            if (this.selectedStudent) {
                this.applyStudentPlannedCourseSelection()

                return
            }

            this.selectAllAvailableCourses()
        },
        resetStudentCourseSelection() {
            const previousSelectionSnapshot = this.selectionStateSnapshot(this.selection)

            if (this.selectedStudent) {
                this.studentSelectionDefaultsPendingCode = null
                this.applySelectedStudentDefaultSelection({ includeCourseHistory: true })
            }

            this.resetCourseSelection()
            this.resetAdditionalCourseSelection()

            if (previousSelectionSnapshot !== this.selectionStateSnapshot(this.selection)) {
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            }
        },
        currentCourseSelectionState(courses) {
            const courseItems = Array.isArray(courses) ? courses : []
            const courseKeys = courseItems
                .map(course => course?.key)
                .filter(Boolean)
            const groupKeys = courseItems
                .flatMap(course => this.courseGroupSelectionKeys(course))

            return {
                deselectedCourseKeys: this.uniqueValues(this.deselectedCourseKeys)
                    .filter(courseKey => courseKeys.includes(courseKey))
                    .toSorted(),
                deselectedCourseGroupKeys: this.uniqueValues(this.deselectedCourseGroupKeys)
                    .filter(groupKey => groupKeys.includes(groupKey))
                    .toSorted(),
            }
        },
        defaultCourseSelectionState() {
            const courses = Array.isArray(this.availableCourses) ? this.availableCourses : []

            if (!this.selectedStudent) {
                return {
                    deselectedCourseKeys: [],
                    deselectedCourseGroupKeys: [],
                }
            }

            const studentRequiredCourses = [
                ...(Array.isArray(this.studentMissingCourses) ? this.studentMissingCourses : []),
                ...(Array.isArray(this.studentPlannedCourses) ? this.studentPlannedCourses : []),
            ]
            const plannedCourseCodes = new Set(studentRequiredCourses
                .flatMap(course => this.courseCodeAliases(course)))
            const deselectedCourses = courses
                .filter(course => !this.courseMatchesStudentPlannedCourse(course, plannedCourseCodes))

            return {
                deselectedCourseKeys: this.uniqueValues(deselectedCourses
                    .map(course => course?.key)
                    .filter(Boolean))
                    .toSorted(),
                deselectedCourseGroupKeys: this.uniqueValues(deselectedCourses
                    .flatMap(course => this.courseGroupSelectionKeys(course)))
                    .toSorted(),
            }
        },
        availablePlannedCourses() {
            const plannedCourseCodes = new Set((Array.isArray(this.studentPlannedCourses) ? this.studentPlannedCourses : [])
                .flatMap(course => this.courseCodeAliases(course)))
            const courses = Array.isArray(this.availableCourses) ? this.availableCourses : []

            return courses.filter(course => this.courseMatchesStudentPlannedCourse(course, plannedCourseCodes))
        },
        courseSelectionStateSnapshot(state) {
            const deselectedCourseKeys = this.uniqueValues(state?.deselectedCourseKeys)
                .toSorted()
                .join('|')
            const deselectedCourseGroupKeys = this.uniqueValues(state?.deselectedCourseGroupKeys)
                .toSorted()
                .join('|')

            return `${deselectedCourseKeys}::${deselectedCourseGroupKeys}`
        },
        courseMatchesStudentPlannedCourse(course, plannedCourseCodes) {
            if (!(plannedCourseCodes instanceof Set) || !plannedCourseCodes.size) return false

            return this.courseCodeAliases(course)
                .some(courseCode => plannedCourseCodes.has(courseCode))
        },
        additionalCourseSelected(course) {
            if (!this.additionalCourseSelectable(course)) return false

            const selectedKeys = this.additionalCourseSelectedKeySet instanceof Set
                ? this.additionalCourseSelectedKeySet
                : new Set(Array.isArray(this.additionalCourseSelectedKeys) ? this.additionalCourseSelectedKeys : [])

            return selectedKeys.has(course?.key)
        },
        additionalCourseGroupSelected(course, group) {
            if (!this.additionalCourseSelected(course)) return false

            return this.courseGroupSelected(course, group)
        },
        additionalCourseFullySelected(course) {
            if (!this.additionalCourseSelected(course)) return false

            const groups = this.courseGroupItems(course)
            if (!groups.length) return true

            return groups.every(group => this.additionalCourseGroupSelected(course, group))
        },
        additionalCoursePartiallySelected(course) {
            if (!this.additionalCourseSelected(course)) return false

            const groups = this.courseGroupItems(course)
            if (!groups.length) return false

            const selectedGroupsCount = groups.filter(group => this.additionalCourseGroupSelected(course, group)).length

            return selectedGroupsCount > 0 && selectedGroupsCount < groups.length
        },
        additionalCourseSelectable(course, selectedCourseKeys = null) {
            if (!this.courseSelectable(course)) return false

            const prerequisiteCourse = this.additionalCoursePrerequisiteCourse(course)
            if (!prerequisiteCourse) return true

            const selectedKeys = selectedCourseKeys instanceof Set
                ? selectedCourseKeys
                : new Set(Array.isArray(this.additionalCourseSelectedKeys) ? this.additionalCourseSelectedKeys : [])

            return selectedKeys.has(prerequisiteCourse.key) && this.courseSelectable(prerequisiteCourse)
        },
        additionalCourseInteractionDisabled(course) {
            const courseKey = course?.key

            if (courseKey && this.additionalCourseInteractionDisabledKeySet instanceof Set) {
                return this.additionalCourseInteractionDisabledKeySet.has(courseKey)
            }

            return this.additionalCourseMissingInSelectedTimetable(course)
                || this.courseAllGroupsNoLongerFitSelectedTimetable(course)
        },
        commitAdditionalCourseSelectionChange(options = {}) {
            const keepAdditionalCoursePanelVisible = this.additionalCoursePanelVisible

            this.additionalCourseExtensionActionHidden = false
            this.generationError = ''
            this.generationProblems = []

            if (options?.clearGeneratedTimetables === true) {
                this.clearGeneratedTimetables({ keepAdditionalCoursePanelVisible })
                this.saveLastRobotState()

                return
            }

            this.additionalCoursePanelRetained = keepAdditionalCoursePanelVisible
                && this.studentAdditionalCourses.length > 0
            this.additionalCourseSelectionChangedAfterTimetable = this.uniqueValues(this.additionalCourseSelectedKeys).length > 0
            this.fullGreenTimetableCountError = ''
            this.saveLastRobotState()
        },
        setAdditionalCourseSelected(course, selected) {
            if (this.additionalCourseSelectionLocked) return
            if (this.additionalCourseInteractionDisabled(course)) return
            if (selected && !this.additionalCourseSelectable(course)) return

            const courseKey = course?.key
            if (!courseKey) return

            const previousSnapshot = this.additionalCourseSelectionSnapshot()
            const selectedKeys = new Set(Array.isArray(this.additionalCourseSelectedKeys)
                ? this.additionalCourseSelectedKeys
                : [])

            if (selected) {
                selectedKeys.add(courseKey)
            } else {
                selectedKeys.delete(courseKey)
            }

            this.additionalCourseSelectedKeys = [...selectedKeys]
            this.clearCourseGroupDeselections(course)
            this.pruneAdditionalCourseSelections()

            if (previousSnapshot !== this.additionalCourseSelectionSnapshot()) {
                this.commitAdditionalCourseSelectionChange()
            }
        },
        setAdditionalCourseGroupSelected(course, group, selected) {
            if (this.additionalCourseSelectionLocked) return
            if (this.additionalCourseInteractionDisabled(course)) return
            if (selected && !this.additionalCourseSelectable(course)) return

            const courseKey = course?.key
            if (!courseKey) return

            this.deselectedCourseGroupKeys = Array.isArray(this.deselectedCourseGroupKeys)
                ? this.deselectedCourseGroupKeys
                : []

            const previousSnapshot = this.additionalCourseSelectionSnapshot()
            const selectedKeys = new Set(Array.isArray(this.additionalCourseSelectedKeys)
                ? this.additionalCourseSelectedKeys
                : [])
            const groupKeys = this.courseGroupSelectionKeys(course)
            const selectionKey = this.courseGroupSelectionKey(course, group)
            const wasCourseSelected = selectedKeys.has(courseKey)

            if (selected) {
                selectedKeys.add(courseKey)

                if (!wasCourseSelected) {
                    this.deselectedCourseGroupKeys = this.uniqueValues([
                        ...this.deselectedCourseGroupKeys,
                        ...groupKeys.filter(groupKey => groupKey !== selectionKey),
                    ])
                }

                this.deselectedCourseGroupKeys = this.deselectedCourseGroupKeys
                    .filter(groupKey => groupKey !== selectionKey)
            } else if (wasCourseSelected) {
                this.deselectedCourseGroupKeys = this.uniqueValues([
                    ...this.deselectedCourseGroupKeys,
                    selectionKey,
                ])

                const hasSelectedGroup = groupKeys
                    .some(groupKey => !this.deselectedCourseGroupKeys.includes(groupKey))

                if (!hasSelectedGroup) {
                    selectedKeys.delete(courseKey)
                    this.clearCourseGroupDeselections(course)
                }
            }

            this.additionalCourseSelectedKeys = [...selectedKeys]
            this.pruneAdditionalCourseSelections()

            if (previousSnapshot !== this.additionalCourseSelectionSnapshot()) {
                this.commitAdditionalCourseSelectionChange()
            }
        },
        pruneAdditionalCourseSelections() {
            const additionalCourses = Array.isArray(this.studentAdditionalCourses) ? this.studentAdditionalCourses : []
            let selectedKeys = this.uniqueValues(this.additionalCourseSelectedKeys)
                .filter(courseKey => additionalCourses.some(course => course.key === courseKey))
            let changed = true

            while (changed) {
                const selectedKeySet = new Set(selectedKeys)
                const filteredKeys = selectedKeys.filter(courseKey => {
                    const course = additionalCourses.find(additionalCourse => additionalCourse.key === courseKey)

                    return course && this.additionalCourseSelectable(course, selectedKeySet)
                })

                changed = filteredKeys.length !== selectedKeys.length
                selectedKeys = filteredKeys
            }

            this.additionalCourseSelectedKeys = selectedKeys

            if (!selectedKeys.length) {
                this.additionalCourseTimetableRequired = false
            }

            additionalCourses
                .filter(course => !selectedKeys.includes(course.key))
                .forEach(course => this.clearCourseGroupDeselections(course))
        },
        additionalCourseSelectionSnapshot() {
            const selectedKeys = this.uniqueValues(this.additionalCourseSelectedKeys)
                .toSorted()
                .join('|')
            const deselectedGroupKeys = this.uniqueValues(this.deselectedCourseGroupKeys)
                .toSorted()
                .join('|')

            return `${selectedKeys}::${deselectedGroupKeys}`
        },
        resetAdditionalCourseSelection(options = {}) {
            const previousSnapshot = this.additionalCourseSelectionStateSnapshot(
                this.currentAdditionalCourseSelectionState(),
            )
            const additionalGroupKeys = this.additionalCourseGroupSelectionKeys()

            this.additionalCourseSelectedKeys = []
            this.additionalCourseTimetableRequired = false
            this.deselectedCourseGroupKeys = Array.isArray(this.deselectedCourseGroupKeys)
                ? this.deselectedCourseGroupKeys.filter(groupKey => !additionalGroupKeys.includes(groupKey))
                : []

            const nextSnapshot = this.additionalCourseSelectionStateSnapshot(
                this.currentAdditionalCourseSelectionState(),
            )

            if (previousSnapshot !== nextSnapshot) {
                this.commitAdditionalCourseSelectionChange(options)
            }
        },
        currentAdditionalCourseSelectionState() {
            const additionalCourses = Array.isArray(this.studentAdditionalCourses) ? this.studentAdditionalCourses : []
            const additionalCourseKeys = additionalCourses.map(course => course?.key).filter(Boolean)
            const additionalGroupKeys = this.additionalCourseGroupSelectionKeys()

            return {
                selectedAdditionalCourseKeys: this.uniqueValues(this.additionalCourseSelectedKeys)
                    .filter(courseKey => additionalCourseKeys.includes(courseKey))
                    .toSorted(),
                deselectedCourseGroupKeys: this.uniqueValues(this.deselectedCourseGroupKeys)
                    .filter(groupKey => additionalGroupKeys.includes(groupKey))
                    .toSorted(),
                additionalCourseTimetableRequired: this.additionalCourseTimetableRequired === true,
            }
        },
        defaultAdditionalCourseSelectionState() {
            return {
                selectedAdditionalCourseKeys: [],
                deselectedCourseGroupKeys: [],
                additionalCourseTimetableRequired: false,
            }
        },
        additionalCourseSelectionStateSnapshot(state) {
            const selectedAdditionalCourseKeys = this.uniqueValues(state?.selectedAdditionalCourseKeys)
                .toSorted()
                .join('|')
            const deselectedCourseGroupKeys = this.uniqueValues(state?.deselectedCourseGroupKeys)
                .toSorted()
                .join('|')
            const additionalCourseTimetableRequired = state?.additionalCourseTimetableRequired === true
                ? '1'
                : '0'

            return `${selectedAdditionalCourseKeys}::${deselectedCourseGroupKeys}::${additionalCourseTimetableRequired}`
        },
        additionalCourseGroupSelectionKeys() {
            return (Array.isArray(this.studentAdditionalCourses) ? this.studentAdditionalCourses : [])
                .flatMap(course => this.courseGroupSelectionKeys(course))
        },
        clearCourseGroupDeselections(course) {
            const groupKeys = this.courseGroupSelectionKeys(course)
            if (!groupKeys.length) return

            this.deselectedCourseGroupKeys = Array.isArray(this.deselectedCourseGroupKeys)
                ? this.deselectedCourseGroupKeys.filter(groupKey => !groupKeys.includes(groupKey))
                : []
        },
        additionalCoursePrerequisiteCourse(course) {
            const additionalCourses = Array.isArray(this.studentAdditionalCourses) ? this.studentAdditionalCourses : []
            const courseParts = this.courseModulePartsForStudentPlanning(course)

            for (const coursePart of courseParts) {
                const moduleNumber = Number(coursePart.module)
                if (!Number.isInteger(moduleNumber) || moduleNumber <= 1) continue

                const prerequisiteStep = this.courseUsesTwoLevelAdditionalPrerequisite(coursePart.base) && moduleNumber > 2
                    ? 2
                    : 1
                const prerequisiteModuleNumber = moduleNumber - prerequisiteStep
                if (prerequisiteModuleNumber <= 0) continue

                const baseAliases = this.studentCourseBaseAliases(coursePart.base)
                const prerequisiteCourse = additionalCourses.find(additionalCourse =>
                    additionalCourse.key !== course?.key
                        && this.courseModulePartsForStudentPlanning(additionalCourse)
                            .some(additionalCoursePart =>
                                Number(additionalCoursePart.module) === prerequisiteModuleNumber
                                    && this.studentCourseBaseAliases(additionalCoursePart.base)
                                        .some(baseAlias => baseAliases.includes(baseAlias)),
                            ),
                )

                if (prerequisiteCourse) return prerequisiteCourse
            }

            return null
        },
        courseUsesTwoLevelAdditionalPrerequisite(base) {
            return this.studentCourseBaseAliases(base)
                .some(baseAlias => ['D', 'E', 'M', 'F', 'L', 'S', 'SPA', 'INF'].includes(baseAlias))
        },
        setCourseSelected(course, selected) {
            if (this.embeddedCourseSelectionLocked) return

            const selectionChanged = this.setCourseSelectedState(course, selected)
            const groupSelectionChanged = this.setCourseGroupsSelectedState(course, selected)

            if (selectionChanged || groupSelectionChanged) {
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            }
        },
        setCourseSelectedState(course, selected) {
            if (selected && !this.courseSelectable(course)) return false

            const courseKey = course.key
            const deselectedIndex = this.deselectedCourseKeys.indexOf(courseKey)

            if (selected && deselectedIndex !== -1) {
                this.deselectedCourseKeys.splice(deselectedIndex, 1)

                return true
            }

            if (!selected && deselectedIndex === -1) {
                this.deselectedCourseKeys.push(courseKey)

                return true
            }

            return false
        },
        setCourseGroupsSelectedState(course, selected) {
            this.deselectedCourseGroupKeys = Array.isArray(this.deselectedCourseGroupKeys)
                ? this.deselectedCourseGroupKeys
                : []

            const groupKeys = this.courseGroupSelectionKeys(course)

            if (selected) {
                const previousCount = this.deselectedCourseGroupKeys.length
                this.deselectedCourseGroupKeys = this.deselectedCourseGroupKeys
                    .filter(groupKey => !groupKeys.includes(groupKey))

                return previousCount !== this.deselectedCourseGroupKeys.length
            }

            const previousCount = this.deselectedCourseGroupKeys.length
            this.deselectedCourseGroupKeys = this.uniqueValues([
                ...this.deselectedCourseGroupKeys,
                ...groupKeys,
            ])

            return previousCount !== this.deselectedCourseGroupKeys.length
        },
        generatedSlotDateLabel(slot) {
            if (this.generatedSlotShowsDateRange(slot)) return slot.dateRangeLabel

            if (!slot?.isOccasional) return ''

            return this.courseGroupDatesLabel(slot.courseGroup)
        },
        generatedSlotShowsDateRange(slot) {
            if (!slot?.dateRangeLabel) return false

            return slot?.showDateRangeLabel === true
                || this.generatedSlotDateRangeSameSlotBlocks(slot).length > 0
                || this.generatedSlotConflictBlocks(slot).length > 0
        },
        generatedSlotDateRangeSameSlotBlocks(slot) {
            return this.generatedSlotSameSlotBlocks(slot)
                .filter(block => !this.generatedSlotBlockIsOccasional(block))
        },
        sameSlotDateOverviewGroups(timetable) {
            const groups = Object.values(timetable?.slots || {})
                .map(slot => this.sameSlotDateOverviewGroup(timetable, slot))
                .filter(Boolean)
                .toSorted((firstGroup, secondGroup) =>
                    firstGroup.sortValue.localeCompare(secondGroup.sortValue, 'de-AT', {
                        numeric: true,
                        sensitivity: 'base',
                    }),
                )

            return this.compactSameSlotDateOverviewGroups(groups)
        },
        sameSlotDateOverviewGroup(timetable, slot) {
            const courses = this.sameSlotDateOverviewCourses(timetable, slot)

            if (courses.length < 2) return null

            const courseGroup = slot?.courseGroup || {}
            const weekday = Number(courseGroup?.weekday)
            const hour = Number(courseGroup?.hour)
            const timeRange = this.sameSlotDateOverviewGroupTimeRange(courseGroup)

            return {
                key: this.slotKey(courseGroup?.weekday, courseGroup?.hour),
                title: this.sameSlotDateOverviewSlotTitle(courseGroup),
                courses,
                weekday,
                startHour: hour,
                endHour: hour,
                from: timeRange.from,
                until: timeRange.until,
                courseSignature: this.sameSlotDateOverviewCourseSignature(courses),
                sortValue: `${String(weekday || '').padStart(2, '0')}-${String(hour || '').padStart(2, '0')}`,
            }
        },
        compactSameSlotDateOverviewGroups(groups) {
            return (Array.isArray(groups) ? groups : []).reduce((compactedGroups, group) => {
                const previousGroup = compactedGroups.at(-1)

                if (this.sameSlotDateOverviewGroupsCanMerge(previousGroup, group)) {
                    compactedGroups[compactedGroups.length - 1] = this.mergedSameSlotDateOverviewGroup(previousGroup, group)

                    return compactedGroups
                }

                compactedGroups.push(group)

                return compactedGroups
            }, [])
        },
        sameSlotDateOverviewGroupsCanMerge(previousGroup, group) {
            return previousGroup
                && group
                && previousGroup.courseSignature === group.courseSignature
                && Number(previousGroup.weekday) === Number(group.weekday)
                && Number(previousGroup.endHour) + 1 === Number(group.startHour)
        },
        mergedSameSlotDateOverviewGroup(previousGroup, group) {
            const mergedGroup = {
                ...previousGroup,
                key: `${previousGroup.key}|${group.key}`,
                endHour: group.endHour,
                until: group.until || previousGroup.until,
            }

            return {
                ...mergedGroup,
                title: this.sameSlotDateOverviewSlotRangeTitle(mergedGroup),
            }
        },
        sameSlotDateOverviewCourseSignature(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map(course => [
                    course?.title,
                    course?.dateRangeLabel,
                    (course?.dateLabels || []).join(','),
                    course?.isDistanceLearningCourse === true ? 'fu' : '',
                    course?.weekMarker || '',
                ].join('::'))
                .sort((left, right) => left.localeCompare(right, 'de-AT', { numeric: true, sensitivity: 'base' }))
                .join('||')
        },
        sameSlotDateOverviewCourses(timetable, slot) {
            if (!slot) return []

            return this.uniqueGeneratedSlotBlocks([
                this.generatedSlotConflictBlock(slot),
                ...(Array.isArray(slot?.sameSlotEntries)
                    ? slot.sameSlotEntries.map(entry => this.generatedSlotConflictBlock(entry))
                    : []),
                ...(Array.isArray(slot?.conflicts)
                    ? slot.conflicts.map(entry => this.generatedSlotConflictBlock(entry))
                    : []),
            ])
                .filter(block => !this.generatedSlotBlockIsOccasional(block))
                .map(block => this.sameSlotDateOverviewCourse(timetable, block))
                .filter(course => course.dateLabels.length > 0)
        },
        sameSlotDateOverviewCourse(timetable, block) {
            const dates = this.courseGroupDates(block?.courseGroup)
            const title = this.sameSlotDateOverviewCourseTitle(block)

            return {
                key: this.generatedSlotBlockIdentity(block) || block?.key || title,
                title,
                dateRangeLabel: block?.dateRangeLabel || this.courseGroupDateRangeLabel(block?.courseGroup),
                dateLabels: dates.map(date => this.formatDateWithWeekdayLabel(date)),
                isDistanceLearningCourse: block?.isDistanceLearningCourse === true,
                weekMarker: this.generatedSlotWeekMarker(block, timetable),
            }
        },
        sameSlotDateOverviewCourseTitle(block) {
            const code = String(block?.code || '').trim()
            const sourceLabel = String(block?.sourceLabel || this.courseGroupSourceLabel(block?.courseGroup) || '').trim()

            if (!sourceLabel || sourceLabel === code) return code

            return [sourceLabel, code].filter(Boolean).join(' / ')
        },
        sameSlotDateOverviewSlotTitle(courseGroup) {
            const timeRange = this.sameSlotDateOverviewGroupTimeRange(courseGroup)

            return this.sameSlotDateOverviewSlotRangeTitle({
                weekday: Number(courseGroup?.weekday),
                startHour: Number(courseGroup?.hour),
                endHour: Number(courseGroup?.hour),
                from: timeRange.from,
                until: timeRange.until,
            })
        },
        sameSlotDateOverviewSlotRangeTitle(group) {
            const weekday = this.courseGroupWeekdayLabel(group?.weekday)
            const startHour = Number(group?.startHour)
            const endHour = Number(group?.endHour)
            const hourLabel = Number.isFinite(startHour) && Number.isFinite(endHour)
                ? (startHour === endHour ? `${startHour}.` : `${startHour}.-${endHour}.`)
                : ''
            const timeRange = group?.from && group?.until ? `${group.from} - ${group.until}` : ''

            return [weekday, hourLabel, timeRange].filter(Boolean).join(' ')
        },
        sameSlotDateOverviewGroupTimeRange(courseGroup) {
            const schoolHours = Array.isArray(this.schoolHours) ? this.schoolHours : []
            const schoolHour = schoolHours.find(configuredSchoolHour =>
                Number(configuredSchoolHour?.hour) === Number(courseGroup?.hour),
            )

            return {
                from: this.formatTimeValue(schoolHour?.from),
                until: this.formatTimeValue(schoolHour?.until),
            }
        },
        appointmentMetaLabel(appointment) {
            return [appointment?.details, appointment?.conflictLabel]
                .filter(Boolean)
                .join(' · ')
        },
        displayedTimetableProblems(timetable) {
            return this.uniqueProblems(timetable?.problems || [])
                .filter(problem => !this.problemIsCoveredByOccasionalAppointments(problem))
        },
        problemIsCoveredByOccasionalAppointments(problem) {
            const problemText = String(problem || '')

            return /: nur Einzeltermine(?: \(|\.)/u.test(problemText)
                || /: Einzeltermin .* überschneidet sich mit .*?\.$/u.test(problemText)
        },
        groupedOccasionalAppointments(timetable) {
            return this.groupOccasionalAppointments(timetable, timetable?.occasionalAppointments || [])
        },
        displayedOccasionalAppointmentGroups(timetable) {
            return this.groupOccasionalAppointments(timetable, this.uniqueOccasionalAppointments([
                ...(Array.isArray(timetable?.occasionalAppointments) ? timetable.occasionalAppointments : []),
                ...this.timetableSlotOccasionalAppointments(timetable),
            ]))
        },
        groupOccasionalAppointments(timetable, appointmentItems) {
            const appointments = this.sortedOccasionalAppointments(appointmentItems)

            return Object.values(appointments.reduce((groups, appointment) => {
                const title = this.occasionalAppointmentGroupTitle(appointment)
                const key = this.occasionalAppointmentGroupKey(appointment, title)

                groups[key] ??= {
                    key,
                    title,
                    code: appointment.code || '',
                    name: appointment.name || '',
                    courseKey: appointment.courseKey || appointment.code || '',
                    sourceLabel: appointment.sourceLabel || '',
                    isDistanceLearningCourse: appointment.isDistanceLearningCourse === true,
                    weekMarker: this.generatedAppointmentWeekMarker(timetable, appointment),
                    appointments: [],
                    sortValue: [
                        appointment.code || '',
                        appointment.name || '',
                        appointment.sourceLabel || '',
                    ].join('|'),
                }

                groups[key].appointments.push(appointment)

                return groups
            }, {}))
                .map(group => ({
                    ...group,
                    appointments: this.sortedOccasionalAppointments(group.appointments),
                    rows: this.compactOccasionalAppointmentRows(group.appointments),
                }))
                .toSorted((firstGroup, secondGroup) =>
                    firstGroup.sortValue.localeCompare(secondGroup.sortValue, 'de-AT', {
                        numeric: true,
                    sensitivity: 'base',
                }),
            )
        },
        timetableSlotOccasionalAppointments(timetable) {
            return Object.values(timetable?.slots || {})
                .flatMap(slot => this.slotOccasionalAppointments(slot))
        },
        slotOccasionalAppointments(slot) {
            if (!slot) return []

            const slotBlock = this.generatedSlotConflictBlock(slot)
            const regularConflictBlock = this.generatedSlotConflictBlocksIncludingRegular(slot)
                .find(block => !this.generatedSlotBlockIsOccasional(block))
            const conflictBlocks = [
                ...(Array.isArray(slot?.conflicts) ? slot.conflicts : []),
                ...(Array.isArray(slot?.sameSlotEntries) ? slot.sameSlotEntries : []),
            ]
                .map(entry => this.generatedSlotConflictBlock(entry))
                .filter(block => this.generatedSlotBlockIsOccasional(block))
                .filter(block => !this.generatedSlotBlocksMatch(block, slotBlock))

            return [
                ...(slot?.isOccasional === true
                    ? [this.occasionalAppointmentFromSlotBlock(slotBlock, regularConflictBlock)]
                    : []),
                ...conflictBlocks.map(block => this.occasionalAppointmentFromSlotBlock(block, slot)),
            ]
        },
        occasionalAppointmentFromSlotBlock(block, existingSlot = null) {
            const courseGroup = block?.courseGroup || {}
            const firstDate = this.courseGroupDates(courseGroup)[0] || ''
            const sourceLabel = block?.sourceLabel || this.courseGroupSourceLabel(courseGroup)
            const conflictLabel = existingSlot
                ? `überschneidet sich mit ${this.courseProblemLabel(existingSlot)}`
                : ''

            return {
                key: [
                    'slot-occasional',
                    block?.key,
                    block?.code,
                    sourceLabel,
                    firstDate,
                    courseGroup?.weekday,
                    courseGroup?.hour,
                ].filter(Boolean).join('|'),
                courseKey: courseGroup?.course || block?.code || '',
                code: block?.code || '',
                name: block?.name || '',
                sourceLabel,
                isDistanceLearningCourse: block?.isDistanceLearningCourse === true,
                courseGroup,
                dateTimeLabel: this.courseGroupDetailOccasionalLabel(courseGroup)
                    || this.courseGroupDateTimeLabel(courseGroup),
                date: firstDate,
                dateLabel: this.courseGroupDetailDateLabel(courseGroup),
                weekday: Number(courseGroup?.weekday),
                hour: Number(courseGroup?.hour),
                timeFrom: this.courseGroupStartTime(courseGroup),
                timeUntil: this.courseGroupEndTime(courseGroup),
                details: '',
                conflictLabel,
                sortValue: [
                    firstDate,
                    String(courseGroup?.weekday || '').padStart(2, '0'),
                    String(courseGroup?.hour || '').padStart(2, '0'),
                    block?.code || '',
                ].join('|'),
            }
        },
        uniqueOccasionalAppointments(appointments) {
            const uniqueAppointments = new Map()

            appointments
                .filter(appointment => appointment?.dateTimeLabel)
                .forEach(appointment => {
                    const identity = [
                        appointment?.courseKey || appointment?.code,
                        appointment?.sourceLabel,
                        appointment?.date,
                        appointment?.weekday,
                        appointment?.hour,
                    ]
                        .map(value => String(value || '').trim())
                        .filter(Boolean)
                        .join('|')

                    if (!identity || uniqueAppointments.has(identity)) {
                        return
                    }

                    uniqueAppointments.set(identity, appointment)
                })

            return Array.from(uniqueAppointments.values())
        },
        defaultOccasionalAppointmentGroupSelections(timetable) {
            const selections = {}
            const groupsByCourse = this.groupedOccasionalAppointments(timetable)
                .reduce((groups, group) => {
                    const groupCourseKey = this.occasionalAppointmentGroupCourseKey(group)

                    if (!groupCourseKey) return groups

                    groups[groupCourseKey] ??= []
                    groups[groupCourseKey].push(group)

                    return groups
                }, {})

            Object.entries(groupsByCourse).forEach(([courseKey, groups]) => {
                if (groups.length > 1) {
                    selections[courseKey] = this.lowestConflictOccasionalAppointmentGroup(groups)?.key

                    return
                }

                const group = groups[0]

                if (this.occasionalAppointmentGroupMatchesScheduledCourse(timetable, group)) {
                    selections[courseKey] = group.key
                }
            })

            return Object.fromEntries(
                Object.entries(selections).filter(([, groupKey]) => Boolean(groupKey)),
            )
        },
        lowestConflictOccasionalAppointmentGroup(groups) {
            return [...groups].toSorted((firstGroup, secondGroup) => {
                const conflictDifference = this.occasionalAppointmentGroupConflictCount(firstGroup)
                    - this.occasionalAppointmentGroupConflictCount(secondGroup)

                if (conflictDifference !== 0) return conflictDifference

                return String(firstGroup.sortValue || firstGroup.title || firstGroup.key || '')
                    .localeCompare(String(secondGroup.sortValue || secondGroup.title || secondGroup.key || ''), 'de-AT', {
                        numeric: true,
                        sensitivity: 'base',
                    })
            })[0]
        },
        occasionalAppointmentGroupConflictCount(group) {
            return (group?.appointments || [])
                .filter(appointment => String(appointment?.conflictLabel || '').trim())
                .length
        },
        occasionalAppointmentGroupSelectable(timetable, group) {
            return this.occasionalAppointmentGroupHasAlternatives(timetable, group)
                || this.occasionalAppointmentGroupMatchesScheduledCourse(timetable, group)
        },
        occasionalAppointmentGroupHasAlternatives(timetable, group) {
            const groupCourseKey = this.occasionalAppointmentGroupCourseKey(group)

            if (!groupCourseKey) return false

            return this.groupedOccasionalAppointments(timetable)
                .filter(appointmentGroup =>
                    this.occasionalAppointmentGroupCourseKey(appointmentGroup) === groupCourseKey,
                ).length > 1
        },
        occasionalAppointmentGroupMatchesScheduledCourse(timetable, group) {
            const groupCourseKey = this.occasionalAppointmentGroupCourseKey(group)
            const scheduledLabels = this.scheduledCourseOptionLabels({
                key: groupCourseKey,
                code: groupCourseKey,
            }, timetable?.slots || {})

            return scheduledLabels.includes(String(group?.sourceLabel || '').trim())
        },
        occasionalAppointmentGroupSelected(timetable, group) {
            const groupCourseKey = this.occasionalAppointmentGroupCourseKey(group)

            return Boolean(
                groupCourseKey
                    && timetable?.selectedOccasionalAppointmentGroups?.[groupCourseKey] === group.key,
            )
        },
        setOccasionalAppointmentGroupSelected(timetable, group, selected) {
            const groupCourseKey = this.occasionalAppointmentGroupCourseKey(group)

            if (!groupCourseKey) return

            timetable.selectedOccasionalAppointmentGroups ??= {}

            if (!selected && timetable.selectedOccasionalAppointmentGroups[groupCourseKey] === group.key) {
                delete timetable.selectedOccasionalAppointmentGroups[groupCourseKey]

                return
            }

            if (selected) {
                timetable.selectedOccasionalAppointmentGroups[groupCourseKey] = group.key
            }
        },
        occasionalAppointmentGroupCourseKey(group) {
            return String(group?.courseKey || '').trim()
        },
        generatedCellOccasionalMarkers(timetable, weekday, time) {
            const appointmentMarkers = this.sortedOccasionalAppointments(timetable?.occasionalAppointments || [])
                .filter(appointment =>
                    Number(appointment?.weekday) === Number(weekday)
                    && Number(appointment?.hour) === Number(time)
                    && this.occasionalAppointmentSelectedForTimetable(timetable, appointment)
                    && this.occasionalAppointmentVisibleInTimetableCell(timetable, appointment),
                )
                .map(appointment => ({
                    key: appointment.key,
                    code: appointment.code,
                    ...(appointment.isAdditionalCourse === true ? { isAdditionalCourse: true } : {}),
                    ...(appointment.isDistanceLearningCourse === true ? { isDistanceLearningCourse: true } : {}),
                    ...(this.generatedAppointmentWeekMarker(timetable, appointment)
                        ? { weekMarker: this.generatedAppointmentWeekMarker(timetable, appointment) }
                        : {}),
                }))

            return this.uniqueGeneratedCellOccasionalMarkers([
                ...appointmentMarkers,
                ...this.generatedSlotOccasionalConflictMarkers(timetable, weekday, time),
            ])
        },
        generatedSlotOccasionalConflictMarkers(timetable, weekday, time) {
            const slot = timetable?.slots?.[this.slotKey(weekday, time)]

            if (!slot) return []

            const slotBlock = this.generatedSlotConflictBlock(slot)
            const slotBlocks = slotBlock.isOccasional === true ? [slotBlock] : []
            const conflictBlocks = Array.isArray(slot?.conflicts) && slot.conflicts.length
                ? slot.conflicts
                    .toSorted((firstConflict, secondConflict) =>
                        String(firstConflict.sortValue || '').localeCompare(String(secondConflict.sortValue || '')),
                    )
                    .map(conflict => this.generatedSlotConflictBlock(conflict))
                    .filter(block => this.generatedSlotBlockIsOccasional(block))
                    .filter(block => !this.generatedSlotBlocksMatch(block, slotBlock))
                : []

            return this.uniqueGeneratedSlotBlocks([
                ...slotBlocks,
                ...conflictBlocks,
            ])
                .map(block => ({
                    key: `conflict-${block.key}`,
                    code: block.code,
                    sourceLabel: block.sourceLabel,
                    date: this.courseGroupDates(block.courseGroup)[0] || '',
                    ...(block.isDistanceLearningCourse === true ? { isDistanceLearningCourse: true } : {}),
                    ...(this.generatedSlotWeekMarker(block, timetable)
                        ? { weekMarker: this.generatedSlotWeekMarker(block, timetable) }
                        : {}),
                }))
        },
        uniqueGeneratedCellOccasionalMarkers(markers) {
            const uniqueMarkers = new Map()

            markers.forEach(marker => {
                const identity = [
                    marker?.code,
                    marker?.sourceLabel,
                    marker?.date,
                    marker?.weekMarker,
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
        occasionalAppointmentVisibleInTimetableCell(timetable, appointment) {
            const slot = timetable?.slots?.[this.slotKey(appointment?.weekday, appointment?.hour)]

            if (!slot) return true

            return this.occasionalAppointmentConflictsWithSlot(appointment, slot)
        },
        occasionalAppointmentConflictsWithSlot(appointment, slot) {
            if (String(appointment?.conflictLabel || '').trim()) return true

            const appointmentDates = this.dateValuesForOccasionalAppointment(appointment)
            const slotDates = this.dateValuesForCourseGroup(slot?.courseGroup)

            if (!appointmentDates.length || !slotDates.length) return true

            return appointmentDates.some(date => slotDates.includes(date))
        },
        dateValuesForOccasionalAppointment(appointment) {
            if (appointment?.date) return [String(appointment.date)]

            return this.dateValuesForCourseGroup(appointment)
        },
        dateValuesForCourseGroup(courseGroup) {
            return Array.isArray(courseGroup?.dates)
                ? courseGroup.dates
                    .map(date => String(date || '').trim())
                    .filter(Boolean)
                : []
        },
        occasionalAppointmentSelectedForTimetable(timetable, appointment) {
            const courseKey = String(appointment?.courseKey || appointment?.code || '').trim()

            if (!courseKey) return false

            const selectedGroupKey = timetable?.selectedOccasionalAppointmentGroups?.[courseKey]

            if (!selectedGroupKey) return false

            return selectedGroupKey === this.occasionalAppointmentGroupKey(
                appointment,
                this.occasionalAppointmentGroupTitle(appointment),
            )
        },
        compactOccasionalAppointmentRows(appointments) {
            return this.sortedOccasionalAppointments(appointments)
                .map(appointment => this.occasionalAppointmentScheduleEntry(appointment))
                .reduce((ranges, entry) => {
                    const currentRange = ranges.at(-1)
                    const previousEntry = currentRange?.at(-1)

                    if (
                        previousEntry
                        && previousEntry.metaLabel === entry.metaLabel
                        && this.courseGroupScheduleEntriesAreConsecutive(previousEntry, entry)
                    ) {
                        currentRange.push(entry)

                        return ranges
                    }

                    ranges.push([entry])

                    return ranges
                }, [])
                .map(range => {
                    const firstEntry = range[0]

                    return {
                        key: range.map(entry => entry.key).join('|'),
                        dateTimeLabel: this.courseGroupScheduleRangeLabel(range),
                        hasConflict: Boolean(firstEntry.metaLabel),
                        metaLabel: firstEntry.metaLabel,
                    }
                })
        },
        occasionalAppointmentScheduleEntry(appointment) {
            return {
                key: appointment?.key || appointment?.dateTimeLabel || '',
                label: appointment?.dateTimeLabel || '',
                date: appointment?.date || '',
                dateLabel: appointment?.dateLabel || '',
                weekday: Number(appointment?.weekday),
                hour: Number(appointment?.hour),
                timeFrom: appointment?.timeFrom || '',
                timeUntil: appointment?.timeUntil || '',
                occasional: true,
                metaLabel: this.appointmentMetaLabel(appointment),
            }
        },
        occasionalAppointmentGroupTitle(appointment) {
            return [
                [appointment?.code, appointment?.name].filter(Boolean).join(' '),
                this.occasionalAppointmentGroupSourceLabel(appointment),
            ]
                .filter(Boolean)
                .join(' - ')
        },
        occasionalAppointmentGroupSourceLabel(appointment) {
            const sourceLabel = String(appointment?.sourceLabel || '').trim()
            const code = String(appointment?.code || '').trim()

            if (!sourceLabel || !code) return sourceLabel

            return sourceLabel.replace(new RegExp(`^${this.escapeRegex(code)}-?`, 'iu'), '')
        },
        occasionalAppointmentGroupKey(appointment, title) {
            return [
                appointment?.courseKey || appointment?.code,
                appointment?.sourceLabel,
                title,
            ].filter(Boolean).join('|')
        },
        escapeRegex(value) {
            return String(value || '').replace(/[.*+?^${}()|[\]\\]/gu, '\\$&')
        },
        timetableCandidateSets(courses) {
            return courses.map(course => {
                const matchingCourseGroups = this.configuredCourseGroups
                    .filter(courseGroup => this.courseGroupMatchesCourse(courseGroup, course))
                    .filter(courseGroup => this.courseGroupSelectedByLabel(course, this.courseGroupOptionLabel(courseGroup)))
                const regularCourseGroups = matchingCourseGroups
                    .filter(courseGroup => !this.isOccasionalCourseGroup(courseGroup))
                const occasionalCourseGroups = matchingCourseGroups
                    .filter(courseGroup => this.isOccasionalCourseGroup(courseGroup))

                const occasionalOptions = this.timetableOptionsForCourse(course, occasionalCourseGroups, false)

                return {
                    course,
                    options: this.timetableRegularOptionsForCourse(course, regularCourseGroups, occasionalOptions),
                    occasionalOptions,
                    occasionalCourseGroups,
                    hasOnlyOccasionalMatches: matchingCourseGroups.length > 0 && regularCourseGroups.length === 0,
                    matchingCourseGroupsCount: matchingCourseGroups.length,
                    regularCourseGroupsCount: regularCourseGroups.length,
                }
            })
        },
        timetableRegularOptionsForCourse(course, regularCourseGroups, occasionalOptions) {
            const completeOptions = this.timetableOptionsForCourse(course, regularCourseGroups)
            const shorterRegularOptions = this.shorterRegularOptionsForCourse(course, regularCourseGroups)
            const partialOptions = this.partialRegularOptionsCompletedByOccasionalSlots(
                course,
                regularCourseGroups,
                occasionalOptions,
            )

            return this.mergeTimetableOptionsBySlots([
                ...completeOptions,
                ...shorterRegularOptions,
                ...partialOptions,
            ])
        },
        shorterRegularOptionsForCourse(course, regularCourseGroups) {
            const requiredSlotCount = this.requiredSlotCountForCourse(course)

            return this.timetableOptionsForCourse(course, regularCourseGroups, false)
                .filter(option => option.courseGroups.length < requiredSlotCount)
        },
        partialRegularOptionsCompletedByOccasionalSlots(course, regularCourseGroups, occasionalOptions) {
            const requiredSlotCount = this.requiredSlotCountForCourse(course)
            const completeOptionSignatures = new Set(
                this.timetableOptionsForCourse(course, regularCourseGroups)
                    .map(option => this.timetableOptionSlotSignature(option)),
            )

            return this.timetableOptionsForCourse(course, regularCourseGroups, false)
                .filter(option => option.courseGroups.length < requiredSlotCount)
                .filter(option => !completeOptionSignatures.has(this.timetableOptionSlotSignature(option)))
                .filter(option => this.optionHasOccasionalCompletion(option, occasionalOptions, requiredSlotCount))
        },
        optionHasOccasionalCompletion(option, occasionalOptions, requiredSlotCount) {
            const matchingOccasionalOptions = occasionalOptions.filter(occasionalOption =>
                this.courseOptionLabelsMatch(option, occasionalOption),
            )

            return matchingOccasionalOptions.some(occasionalOption => {
                const slotKeys = new Set([
                    ...option.courseGroups.map(courseGroup => this.slotKey(courseGroup.weekday, courseGroup.hour)),
                    ...occasionalOption.courseGroups.map(courseGroup => this.slotKey(courseGroup.weekday, courseGroup.hour)),
                ])

                return slotKeys.size >= requiredSlotCount
            })
        },
        courseOptionLabelsMatch(firstOption, secondOption) {
            return String(firstOption?.label || '').trim() === String(secondOption?.label || '').trim()
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

            if (!completeOptions) return entries

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
            const requiredSlotCount = this.requiredSlotCountForCourse(course)
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

            const entriesWithEnoughSlots = entries
                .filter(entry => entry.courseGroups.length >= requiredSlotCount)
                .sort((firstOption, secondOption) => this.compareTimetableOptions(firstOption, secondOption))

            if (entriesWithEnoughSlots.length) return entriesWithEnoughSlots

            return entries.sort((firstOption, secondOption) => this.compareTimetableOptions(firstOption, secondOption))
        },
        requiredSlotCountForCourse(course) {
            return Math.max(1, Math.round(Number(course?.hours || 0) || 0))
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
                ET: 'ETH',
                ETH: 'ET',
                GS: 'GPB',
                GW: 'GWB',
                ME: 'MU',
                R: 'RK',
                RK: 'R',
                S: 'SPA',
                SPA: 'S',
                LPT: 'LET',
                LET: 'LPT',
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
                    scheduledCourseCount: this.scheduledCourseCountForSlots(assignedSlots),
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
                if (this.optionHasBlockingUsedSlot(option, assignedSlots, usedSlotKeys)) {
                    return
                }

                placedOption = true
                const nextAssignedSlots = { ...assignedSlots }
                const nextUsedSlotKeys = new Set(usedSlotKeys)
                const nextScheduledCourseKeys = new Set(scheduledCourseKeys)
                nextScheduledCourseKeys.add(candidateSet.course.key || candidateSet.course.code)

                option.courseGroups.forEach(courseGroup => {
                    const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                    const timetableSlot = {
                        ...option.course,
                        sourceLabel: option.label,
                        alternativeLabels: this.optionAlternativeLabelsForSlot(option, key),
                        courseGroup,
                        dateRangeLabel: this.courseGroupDateRangeLabel(courseGroup),
                        isDistanceLearningCourse: this.optionIsDistanceLearningCourse(option),
                    }
                    const existingSlot = nextAssignedSlots[key]

                    if (existingSlot && !existingSlot.isConflictPreview) {
                        nextAssignedSlots[key] = this.assignedSlotWithSameSlotEntry(existingSlot, timetableSlot)
                        nextUsedSlotKeys.add(key)

                        return
                    }

                    nextAssignedSlots[key] = timetableSlot
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

                    const freeWeekdayDifference = this.timetableCombinationFreeWeekdayCount(secondCombination)
                        - this.timetableCombinationFreeWeekdayCount(firstCombination)
                    if (freeWeekdayDifference !== 0) return freeWeekdayDifference

                    if (firstCombination.scheduledSlotCount !== secondCombination.scheduledSlotCount) {
                        return secondCombination.scheduledSlotCount - firstCombination.scheduledSlotCount
                    }

                    return firstCombination.problems.length - secondCombination.problems.length
                })
        },
        timetableCombinationMatchesBestTier(combination, bestCombination) {
            return combination.scheduledCourseCount === bestCombination.scheduledCourseCount
                && this.timetableCombinationFreeWeekdayCount(combination) === this.timetableCombinationFreeWeekdayCount(bestCombination)
                && combination.scheduledSlotCount === bestCombination.scheduledSlotCount
        },
        completeRegularGreenTimetableCombinations(combinations, selectedCourseCount) {
            return combinations.filter(combination =>
                combination.scheduledCourseCount === selectedCourseCount
                    && this.uniqueProblems(combination.problems).length === 0,
            )
        },
        preferredTimetableCombinations(combinations, selectedCourseCount) {
            const uniqueCombinations = this.uniqueTimetableCombinations(combinations)
            if (uniqueCombinations.length <= 10) return uniqueCombinations

            const greenCombinations = this.completeRegularGreenTimetableCombinations(
                uniqueCombinations,
                selectedCourseCount,
            )
            if (!greenCombinations.length) return uniqueCombinations

            const minimumUsedDayCount = Math.min(...greenCombinations.map(combination =>
                this.timetableCombinationUsedDayCount(combination),
            ))

            return greenCombinations.filter(combination =>
                this.timetableCombinationUsedDayCount(combination) === minimumUsedDayCount,
            )
        },
        uniqueTimetableCombinations(combinations) {
            const signatures = new Set()

            return combinations.filter(combination => {
                const signature = this.timetableCombinationSignature(combination)
                if (signatures.has(signature)) return false

                signatures.add(signature)

                return true
            })
        },
        timetableCombinationSignature(combination) {
            return Object.entries(combination?.slots || {})
                .filter(([, slot]) => slot && !slot.isConflictPreview)
                .flatMap(([slotKey, slot]) => this.assignedSlotEntries(slot).map(entry => [
                    slotKey,
                    entry?.key || entry?.code || '',
                    entry?.sourceLabel || '',
                    this.courseGroupDates(entry?.courseGroup).join(','),
                ].join(':')))
                .sort()
                .join('|')
        },
        scheduledCourseCountForSlots(slots) {
            return new Set(Object.values(slots || {})
                .filter(slot => slot && !slot.isConflictPreview)
                .flatMap(slot => this.assignedSlotEntries(slot))
                .map(entry => entry?.key || entry?.code)
                .filter(Boolean))
                .size
        },
        timetableCombinationUsedDayCount(combination) {
            return new Set(Object.values(combination?.slots || {})
                .filter(slot => slot && !slot.isConflictPreview)
                .map(slot => Number(slot?.courseGroup?.weekday))
                .filter(Number.isFinite))
                .size
        },
        timetableCombinationFreeWeekdayCount(combination) {
            const usedWeekdays = new Set(Object.values(combination?.slots || {})
                .filter(slot => slot && !slot.isConflictPreview)
                .map(slot => Number(slot?.courseGroup?.weekday))
                .filter(Number.isFinite))

            return this.timetableFreeWeekdayOptions()
                .filter(weekday => !usedWeekdays.has(Number(weekday.value)))
                .length
        },
        timetableFreeWeekdayOptions() {
            const weekdayOptions = Array.isArray(this.weekdayOptions)
                ? this.weekdayOptions
                : this.defaultWeekdayOptions()

            return weekdayOptions
                .filter(weekday => Number(weekday.value) <= 5)
                .filter(weekday => this.constraintSelected('availableWeekdays', weekday.value))
        },
        defaultWeekdayOptions() {
            return [
                { title: 'Montag', shortTitle: 'Mo', value: 1 },
                { title: 'Dienstag', shortTitle: 'Di', value: 2 },
                { title: 'Mittwoch', shortTitle: 'Mi', value: 3 },
                { title: 'Donnerstag', shortTitle: 'Do', value: 4 },
                { title: 'Freitag', shortTitle: 'Fr', value: 5 },
            ]
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
            const occasionalAppointments = []

            candidateSets.forEach(candidateSet => {
                this.visibleOccasionalOptionsForCombination(candidateSet, slots).forEach(option => {
                    option.courseGroups.forEach(courseGroup => {
                        const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                        const existingSlot = slots[key]
                        const appointment = this.occasionalAppointmentItem(
                            candidateSet.course,
                            option,
                            courseGroup,
                            existingSlot,
                        )
                        occasionalAppointments.push(appointment)

                        if (existingSlot) {
                            this.trackOccasionalCourseConflict(conflicts, candidateSet.course, courseGroup, existingSlot)

                            return
                        }
                    })
                })
            })

            return {
                slots,
                occasionalAppointments: this.sortedOccasionalAppointments(occasionalAppointments),
                problems: this.uniqueProblems([
                    ...problems,
                    ...Object.values(conflicts)
                        .map(conflict => this.occasionalCourseConflictProblemMessage(conflict)),
                ]),
            }
        },
        visibleOccasionalOptionsForCombination(candidateSet, slots) {
            const occasionalOptions = candidateSet.occasionalOptions || []
            const scheduledLabels = this.scheduledCourseOptionLabels(candidateSet.course, slots)

            if (!scheduledLabels.length) {
                return candidateSet.hasOnlyOccasionalMatches || !candidateSet.options?.length ? occasionalOptions : []
            }

            return occasionalOptions.filter(option => scheduledLabels.includes(option.label))
        },
        scheduledCourseOptionLabels(course, slots) {
            return this.uniqueProblems(Object.values(slots || {})
                .filter(slot => this.slotsBelongToSameCourse(slot, course))
                .flatMap(slot => [
                    slot?.sourceLabel,
                    ...(Array.isArray(slot?.alternativeLabels) ? slot.alternativeLabels : []),
                ])
                .map(label => String(label || '').trim())
                .filter(Boolean))
        },
        slotsBelongToSameCourse(slot, course) {
            return Boolean(
                slot
                    && (
                        (slot.key && course?.key && slot.key === course.key)
                        || (slot.code && course?.code && slot.code === course.code)
                    ),
            )
        },
        candidateSetProblemMessage(candidateSet) {
            const courseLabel = this.courseProblemLabel(candidateSet.course)

            if (candidateSet.hasOnlyOccasionalMatches) {
                const datesLabel = this.candidateSetOccasionalDatesLabel(candidateSet)

                return `${courseLabel}: nur Einzeltermine${datesLabel ? ` (${datesLabel})` : ''}.`
            }

            if (!candidateSet.matchingCourseGroupsCount) {
                return `${courseLabel}: keine passenden TT-Stunden gefunden.`
            }

            if (candidateSet.regularCourseGroupsCount > 0) {
                const blockedGroupsLabel = this.candidateSetBlockedRegularGroupsLabel(candidateSet)

                if (blockedGroupsLabel) {
                    return `${courseLabel}: ${blockedGroupsLabel}`
                }

                return `${courseLabel}: ausgewählte TT-Stunden passen nicht zu deinen Zeitvorgaben.`
            }

            return `${courseLabel}: konnte nicht eingeplant werden.`
        },
        candidateSetBlockedRegularGroupsLabel(candidateSet) {
            const blockedGroups = this.candidateSetBlockedRegularGroupItems(candidateSet)

            if (!blockedGroups.length) return ''

            if (blockedGroups.length === 1) {
                const group = blockedGroups[0]
                const groupLabel = [group.title, group.meta ? `(${group.meta})` : ''].filter(Boolean).join(' ')

                return `${groupLabel} ist in deinen Zeitvorgaben nicht erlaubt.`
            }

            return `diese TT-Stunden sind in deinen Zeitvorgaben nicht erlaubt: ${this.candidateSetRegularGroupsLabel(blockedGroups)}.`
        },
        candidateSetBlockedRegularGroupItems(candidateSet) {
            const regularCourseGroups = Array.isArray(candidateSet.regularCourseGroups)
                ? candidateSet.regularCourseGroups
                : []
            const unavailableCourseGroups = regularCourseGroups.filter(courseGroup =>
                !this.weekdayTimeAvailable(Number(courseGroup.weekday), Number(courseGroup.hour)),
            )

            return this.groupedCourseGroupDetailItems(
                unavailableCourseGroups.length ? unavailableCourseGroups : regularCourseGroups,
            )
        },
        candidateSetRegularGroupsLabel(candidateSet) {
            const groupItems = Array.isArray(candidateSet)
                ? candidateSet
                : this.groupedCourseGroupDetailItems(candidateSet.regularCourseGroups || [])

            return groupItems
                .map(group => [group.title, group.meta].filter(Boolean).join(': '))
                .filter(Boolean)
                .join('; ')
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
        trackGeneratedSlotConflict(slot, course, courseGroup, date, option = null) {
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
                dateRangeLabel: this.courseGroupDateRangeLabel(courseGroup),
                isDistanceLearningCourse: option ? this.optionIsDistanceLearningCourse(option) : false,
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
                        this.trackGeneratedSlotConflict(nextAssignedSlots[key], candidateSet.course, courseGroup, null, option)

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
                        dateRangeLabel: this.courseGroupDateRangeLabel(courseGroup),
                        isConflictPreview: true,
                        isDistanceLearningCourse: this.optionIsDistanceLearningCourse(option),
                        conflicts: [],
                    }
                })
            })

            return nextAssignedSlots
        },
        assignedSlotWithSameSlotEntry(slot, sameSlotEntry) {
            const sameSlotEntries = [
                ...(Array.isArray(slot.sameSlotEntries) ? slot.sameSlotEntries : []),
                sameSlotEntry,
            ]
                .filter(entry => (entry?.key || entry?.code) !== (slot?.key || slot?.code))
                .filter((entry, index, entries) => entries.findIndex(existingEntry =>
                    (existingEntry?.key || existingEntry?.code) === (entry?.key || entry?.code)
                        && String(existingEntry?.sourceLabel || '') === String(entry?.sourceLabel || ''),
                ) === index)

            return {
                ...slot,
                sameSlotEntries,
            }
        },
        optionCourseConflictEntries(option, assignedSlots, usedSlotKeys) {
            return option.courseGroups
                .filter(courseGroup => usedSlotKeys.has(this.slotKey(courseGroup.weekday, courseGroup.hour)))
                .filter(courseGroup => this.assignedSlotConfrontsCourseGroup(assignedSlots, courseGroup))
                .flatMap(courseGroup => {
                    const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                    const assignedSlot = assignedSlots[key]
                    return this.assignedSlotEntries(assignedSlot)
                        .filter(entry => this.courseGroupsConfront(entry.courseGroup, courseGroup))
                        .map(entry => {
                            const label = [
                                this.courseProblemLabel(entry),
                                this.courseGroupTimeRangeLabel(entry?.courseGroup || courseGroup),
                            ]
                                .filter(Boolean)
                                .join(' ')

                            return {
                                label,
                                sortValue: `${String(courseGroup?.weekday || '').padStart(2, '0')}-${String(courseGroup?.hour || '').padStart(2, '0')}-${label}`,
                                code: entry?.code || '',
                                name: entry?.name || '',
                                sourceLabel: entry?.sourceLabel || this.courseGroupSourceLabel(entry?.courseGroup),
                                alternativeLabels: entry?.alternativeLabels || [],
                                courseGroup: entry?.courseGroup,
                                isDistanceLearningCourse: entry?.isDistanceLearningCourse === true,
                            }
                        })
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
                    .flatMap(courseGroup => {
                        const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                        const assignedSlot = assignedSlots[key]
                        const weekday = this.weekdayOptions.find(item => Number(item.value) === Number(courseGroup.weekday))
                        const time = this.timeOptions.find(item => Number(item.value) === Number(courseGroup.hour))
                        const slotLabel = `${weekday?.shortTitle || courseGroup.weekday} ${time?.shortTitle || `${courseGroup.hour}. Stunde`}`

                        return this.assignedSlotEntries(assignedSlot)
                            .filter(entry => this.courseGroupsConfront(entry.courseGroup, courseGroup))
                            .map(entry => entry?.code ? `${entry.code} (${slotLabel})` : slotLabel)
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
        occasionalAppointmentItem(course, option, courseGroup, existingSlot = null) {
            const key = [
                course?.key || course?.code,
                option?.key || option?.label,
                courseGroup?.key,
                courseGroup?.weekday,
                courseGroup?.hour,
                this.courseGroupDates(courseGroup).join('|'),
            ].filter(Boolean).join('|')
            const details = [
                String(courseGroup?.teacher || '').trim(),
                this.courseGroupRoomsLabel(courseGroup),
            ]
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
                .join(' · ')
            const conflictLabel = existingSlot
                ? `überschneidet sich mit ${this.courseProblemLabel(existingSlot)}`
                : ''

            return {
                key,
                courseKey: course?.key || '',
                code: course?.code || '',
                name: course?.name || '',
                sourceLabel: String(option?.label || this.courseGroupSourceLabel(courseGroup) || '').trim(),
                isDistanceLearningCourse: this.optionIsDistanceLearningCourse(option),
                courseGroup,
                dateTimeLabel: this.courseGroupDetailOccasionalLabel(courseGroup),
                date: this.courseGroupDates(courseGroup)[0] || '',
                dateLabel: this.courseGroupDetailDateLabel(courseGroup),
                weekday: Number(courseGroup?.weekday),
                hour: Number(courseGroup?.hour),
                timeFrom: this.courseGroupStartTime(courseGroup),
                timeUntil: this.courseGroupEndTime(courseGroup),
                details,
                conflictLabel,
                sortValue: [
                    this.courseGroupDates(courseGroup)[0] || '',
                    String(courseGroup?.weekday || '').padStart(2, '0'),
                    String(courseGroup?.hour || '').padStart(2, '0'),
                    course?.code || '',
                ].join('|'),
            }
        },
        sortedOccasionalAppointments(appointments) {
            return appointments
                .filter(appointment => appointment.dateTimeLabel)
                .filter((appointment, index, values) =>
                    values.findIndex(value => value.key === appointment.key) === index,
                )
                .toSorted((firstAppointment, secondAppointment) =>
                    firstAppointment.sortValue.localeCompare(secondAppointment.sortValue, 'de-AT', {
                        numeric: true,
                        sensitivity: 'base',
                    }),
                )
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
        courseGroupDateRangeLabel(courseGroup) {
            const dates = this.courseGroupDates(courseGroup)

            if (!dates.length) return ''

            if (dates[0] === dates[dates.length - 1]) {
                return this.formatCompactDateLabel(dates[0], true)
            }

            const firstDate = this.formatCompactDateLabel(dates[0], true)
            const lastDate = this.formatCompactDateLabel(dates[dates.length - 1])

            return `${firstDate}-${lastDate}`
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
        formatCompactDateLabel(value, preserveEarlyMonthPadding = false) {
            const dateValue = String(value || '').trim()
            const match = dateValue.match(/^(\d{4})-(\d{2})-(\d{2})$/u)

            if (match) {
                const month = Number(match[2])

                return `${Number(match[3])}.${preserveEarlyMonthPadding && month <= 4 ? match[2] : month}.`
            }

            return dateValue
        },
        optionHasUsedSlot(option, usedSlotKeys) {
            return option.courseGroups.some(courseGroup =>
                usedSlotKeys.has(this.slotKey(courseGroup.weekday, courseGroup.hour)),
            )
        },
        optionHasBlockingUsedSlot(option, assignedSlots, usedSlotKeys) {
            return option.courseGroups.some(courseGroup => {
                const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                if (!usedSlotKeys.has(key)) return false

                const assignedSlot = assignedSlots?.[key]
                if (!assignedSlot?.courseGroup) return true

                return this.assignedSlotEntries(assignedSlot).some(entry =>
                    this.courseGroupsBlockTimetableSlot(entry.courseGroup, courseGroup),
                )
            })
        },
        assignedSlotConfrontsCourseGroup(assignedSlots, courseGroup) {
            const assignedSlot = assignedSlots[this.slotKey(courseGroup.weekday, courseGroup.hour)]
            if (!assignedSlot) return false

            return this.assignedSlotEntries(assignedSlot).some(entry =>
                this.courseGroupsConfront(entry.courseGroup, courseGroup),
            )
        },
        assignedSlotEntries(slot) {
            if (!slot) return []

            return [
                slot,
                ...(Array.isArray(slot.sameSlotEntries) ? slot.sameSlotEntries : []),
            ].filter(entry => entry?.courseGroup)
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
        courseGroupsBlockTimetableSlot(leftCourseGroup, rightCourseGroup) {
            if (!this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)) return false

            return !this.courseGroupsMixRegularAndOccasional(leftCourseGroup, rightCourseGroup)
                && this.courseGroupDatesOverlap(leftCourseGroup, rightCourseGroup)
        },
        courseGroupsMixRegularAndOccasional(leftCourseGroup, rightCourseGroup) {
            return this.isOccasionalCourseGroup(leftCourseGroup) !== this.isOccasionalCourseGroup(rightCourseGroup)
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
            const onlyOccasionalMatch = problemText.match(/^(.*?): nur Einzeltermine(?: \((.*?)\))?\.$/u)

            if (onlyOccasionalMatch) {
                return `${onlyOccasionalMatch[1]}: nur Einzeltermine.`
            }

            const outsideConstraintsMatch = problemText.match(/^(.*?): TT-Stunden liegen außerhalb deiner Zeitvorgaben(?: \((.*?)\))?\.$/u)

            if (outsideConstraintsMatch) {
                return `${outsideConstraintsMatch[1]}: TT-Stunden liegen außerhalb deiner Zeitvorgaben.`
            }

            const conflictMatch = problemText.match(/^(.*?): Einzeltermin (.*?) überschneidet sich mit (.*?)\.$/u)

            if (conflictMatch) {
                return `${conflictMatch[1]}: Einzeltermin überschneidet sich mit ${conflictMatch[3]}.`
            }

            return problemText
        },
        problemDetails(problem) {
            const problemText = String(problem || '')
            const onlyOccasionalMatch = problemText.match(/: nur Einzeltermine \((.*?)\)\.$/u)
            const outsideConstraintsMatch = problemText.match(/: TT-Stunden liegen außerhalb deiner Zeitvorgaben \((.*?)\)\.$/u)
            const conflictMatch = problemText.match(/: Einzeltermin (.*?) überschneidet sich mit .*?\.$/u)

            if (outsideConstraintsMatch?.[1]) {
                return outsideConstraintsMatch[1]
                    .split('; ')
                    .map(detail => detail.trim())
                    .filter(Boolean)
            }

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
        coursesForSemester(semester) {
            return this.subjectRows
                .filter(subject => subject.is_active !== false)
                .filter(subject => Number(subject.semester) === Number(semester))
                .filter(subject => this.subjectMatchesSelectedBranch(subject))
                .filter(subject => this.subjectMatchesSelectedChoices(subject))
                .flatMap(subject => this.selectedCoursesFromSubject(subject))
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        coursesAfterSemester(semester) {
            return this.subjectRows
                .map(subject => Number(subject.semester))
                .filter(subjectSemester => Number.isFinite(subjectSemester) && subjectSemester > Number(semester))
                .filter((subjectSemester, index, subjectSemesters) => subjectSemesters.indexOf(subjectSemester) === index)
                .sort((firstSemester, secondSemester) => firstSemester - secondSemester)
                .flatMap(subjectSemester => this.coursesForSemester(subjectSemester))
        },
        studentPlannedCoursesForSemester(semester, completedCourseCodes) {
            return this.coursesForSemester(semester)
                .filter(course => !this.courseCompletedForStudentPlanning(course, completedCourseCodes))
                .filter((course, index, courses) =>
                    courses.findIndex(candidate => candidate.key === course.key) === index,
                )
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        pendingStudentCoursesBeforeSemester(semester, completedCourseCodes, visitedCourseCodes) {
            return this.subjectRows
                .map(subject => Number(subject.semester))
                .filter(subjectSemester => Number.isFinite(subjectSemester) && subjectSemester < Number(semester))
                .filter((subjectSemester, index, subjectSemesters) => subjectSemesters.indexOf(subjectSemester) === index)
                .sort((firstSemester, secondSemester) => firstSemester - secondSemester)
                .flatMap(subjectSemester => this.coursesForSemester(subjectSemester))
                .filter(course => !this.courseCompletedForStudentPlanning(course, completedCourseCodes))
                .filter(course => this.coursePossibleAsStudentMissing(course, completedCourseCodes, visitedCourseCodes))
                .filter((course, index, courses) =>
                    courses.findIndex(candidate => candidate.key === course.key) === index,
                )
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        studentCompletedCourseCodes() {
            const completedCourses = Array.isArray(this.studentCompletedCourses)
                ? this.studentCompletedCourses
                : []

            return new Set(completedCourses
                .filter(course => this.completedCourseCountsAsDone(course?.grade))
                .flatMap(course => this.courseCodeAliasParts(course?.subject))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
        },
        studentVisitedCourseCodes() {
            const completedCourses = Array.isArray(this.studentCompletedCourses)
                ? this.studentCompletedCourses
                : []

            return new Set(completedCourses
                .flatMap(course => this.courseCodeAliasParts(course?.subject))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
        },
        studentUnavailableAdditionalCourseCodes(completedCourseCodes, plannedCourses) {
            const unavailableCourseCodes = new Set(completedCourseCodes)

            plannedCourses
                .flatMap(course => this.courseCodeAliases(course))
                .forEach(courseCode => unavailableCourseCodes.add(courseCode))

            return unavailableCourseCodes
        },
        studentPlannedCourseCodes(plannedCourses) {
            return new Set((Array.isArray(plannedCourses) ? plannedCourses : [])
                .flatMap(course => this.courseCodeAliases(course))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
        },
        completedCourseCountsAsDone(grade) {
            const normalizedGrade = String(grade || '').trim().toLocaleUpperCase('de-AT')

            return normalizedGrade === 'B' || ['1', '2', '3', '4'].includes(normalizedGrade)
        },
        courseCompletedForStudentPlanning(course, completedCourseCodes) {
            if (this.courseMatchesCourseCodeSet(course, completedCourseCodes)) return true

            return this.courseModulePartsForStudentPlanning(course)
                .some(parts => this.studentCourseCodesContainEquivalentModule(completedCourseCodes, parts))
        },
        courseMatchesCourseCodeSet(course, courseCodes) {
            if (!(courseCodes instanceof Set) || !courseCodes.size) return false

            return this.courseCodeAliases(course)
                .some(courseCode => courseCodes.has(courseCode))
        },
        studentCourseCodesContainEquivalentModule(courseCodes, courseParts) {
            if (!(courseCodes instanceof Set) || !courseCodes.size) return false

            const moduleNumber = String(courseParts?.module || '')
            if (!moduleNumber) return false

            const baseAliases = this.studentCourseBaseAliases(courseParts.base)

            return [...courseCodes]
                .map(courseCode => this.courseCodeModuleParts(courseCode))
                .some(parts =>
                    String(parts.module || '') === moduleNumber
                    && baseAliases.includes(parts.base),
                )
        },
        coursePossibleAsStudentAdditional(course, completedCourseCodes, visitedCourseCodes, plannedCourseCodes = new Set()) {
            return this.courseModulePartsForStudentPlanning(course)
                .some(parts => this.courseModulePrerequisiteMet(parts, completedCourseCodes, visitedCourseCodes, {
                    plannedCourseCodes,
                }))
        },
        coursePossibleAsStudentMissing(course, completedCourseCodes, visitedCourseCodes) {
            return this.courseModulePartsForStudentPlanning(course)
                .some(parts => this.courseModulePrerequisiteMet(parts, completedCourseCodes, visitedCourseCodes, {
                    allowInitialModules: true,
                }))
        },
        courseModulePartsForStudentPlanning(course) {
            return this.courseCodeAliases(course)
                .map(courseCode => this.courseCodeModuleParts(courseCode))
                .filter(parts => parts.module)
                .filter(parts => this.courseBaseEligibleForStudentAdditional(parts.base))
                .filter((parts, index, allParts) =>
                    allParts.findIndex(candidate => candidate.base === parts.base && candidate.module === parts.module) === index,
                )
        },
        courseBaseEligibleForStudentAdditional(base) {
            const baseAliases = this.studentCourseBaseAliases(base)
            const subjectRows = Array.isArray(this.subjectRows) ? this.subjectRows : []

            if (!subjectRows.length) {
                return baseAliases
                    .some(baseAlias => [
                        'BE',
                        'BU',
                        'CH',
                        'D',
                        'E',
                        'ET',
                        'ETH',
                        'F',
                        'GPB',
                        'GS',
                        'GW',
                        'GWB',
                        'INF',
                        'L',
                        'M',
                        'ME',
                        'MU',
                        'PH',
                        'PP',
                        'R',
                        'RK',
                        'S',
                        'SPA',
                        'ÖKO',
                    ].includes(baseAlias))
            }

            return subjectRows
                .filter(subject => subject?.is_active !== false)
                .some(subject => this.subjectRowMatchesStudentCourseBase(subject, baseAliases))
        },
        subjectRowMatchesStudentCourseBase(subject, baseAliases) {
            return [
                subject?.json_code,
                subject?.json_subject,
            ]
                .flatMap(value => this.courseCodeAliasParts(value))
                .map(value => this.courseCodeModuleParts(value).base)
                .flatMap(base => this.studentCourseBaseAliases(base))
                .some(baseAlias => baseAliases.includes(baseAlias))
        },
        courseModulePrerequisiteMet(parts, completedCourseCodes, visitedCourseCodes, options = {}) {
            const moduleNumber = Number(parts.module)
            if (!Number.isInteger(moduleNumber)) return false

            const baseAliases = this.studentCourseBaseAliases(parts.base)
            if (moduleNumber === 1) {
                if (options?.allowInitialModules) {
                    return !this.courseBaseHasVisitedLaterModule(baseAliases, visitedCourseCodes, moduleNumber)
                }

                return true
            }

            if (moduleNumber === 2) {
                const plannedCourseCodes = options?.plannedCourseCodes instanceof Set
                    ? options.plannedCourseCodes
                    : new Set()

                return baseAliases.some(baseAlias =>
                    completedCourseCodes.has(`${baseAlias}1`) || plannedCourseCodes.has(`${baseAlias}1`),
                )
            }

            const prerequisiteModuleNumber = moduleNumber - 2
            const hasPositivePrerequisite = baseAliases
                .some(baseAlias => completedCourseCodes.has(`${baseAlias}${prerequisiteModuleNumber}`))

            if (!hasPositivePrerequisite) return false

            if (['E', 'M'].includes(this.normalizedCourseCode(parts.base)) && moduleNumber === 8) {
                return baseAliases.some(baseAlias => visitedCourseCodes.has(`${baseAlias}7`))
            }

            return true
        },
        courseBaseHasVisitedLaterModule(baseAliases, visitedCourseCodes, moduleNumber) {
            if (!(visitedCourseCodes instanceof Set) || !visitedCourseCodes.size) return false

            return [...visitedCourseCodes]
                .map(courseCode => this.courseCodeModuleParts(courseCode))
                .some(parts => {
                    const visitedModuleNumber = Number(parts.module)

                    return baseAliases.includes(parts.base)
                        && Number.isInteger(visitedModuleNumber)
                        && visitedModuleNumber > moduleNumber
                })
        },
        studentCourseBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const aliases = [normalizedBase]
            const mappedAliases = {
                GS: ['GPB'],
                GPB: ['GS'],
                GW: ['GWB'],
                GWB: ['GW'],
                ET: ['ETH', 'R', 'RK'],
                ETH: ['ET', 'R', 'RK'],
                ME: ['MU'],
                MU: ['ME'],
                R: ['RK', 'ET', 'ETH'],
                RK: ['R', 'ET', 'ETH'],
                S: ['SPA'],
                SPA: ['S'],
                LPT: ['LET'],
                LET: ['LPT'],
            }

            return [
                ...aliases,
                ...(mappedAliases[normalizedBase] || []),
            ]
                .filter(Boolean)
                .filter((alias, index, allAliases) => allAliases.indexOf(alias) === index)
        },
        subjectMatchesSelectedBranch(subject) {
            if (this.isArtsSubject(subject)) {
                return subject.branch === 'gymnasial' && this.selection.branch === 'gymnasial'
            }

            return !subject.branch || subject.branch === 'common' || subject.branch === this.selection.branch
        },
        subjectMatchesSelectedChoices(subject) {
            if (this.isArtsSubject(subject)) return this.subjectBaseKey(subject) === this.selection.artsSubject
            if (this.isLanguageSubject(subject)) return this.languageSubjectMatchesSelection(subject)

            return true
        },
        languageSubjectMatchesSelection(subject) {
            const languageCode = this.languageSubjectCode(subject)

            return !languageCode || languageCode === this.selection.language
        },
        languageSubjectCode(subject) {
            const baseKey = this.normalizedCourseCode(this.subjectBaseKey(subject))

            if (['L', 'F', 'S'].includes(baseKey)) return baseKey

            if (baseKey !== 'L/F/S') return ''

            const jsonCodeBase = this.courseCodeWithoutModule(subject.json_code)
            const jsonCodeParts = this.courseCodeAliasParts(jsonCodeBase)
                .map(value => this.normalizedCourseCode(value))

            return jsonCodeParts.length === 1 && ['L', 'F', 'S'].includes(jsonCodeParts[0])
                ? jsonCodeParts[0]
                : ''
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
            const fallbackCodes = this.isLanguageSubject(subject)
                ? [this.selectedCourseCode(subject)]
                : this.timetableCodesForMappedSubject(subject.tt_subject, moduleNumber)

            return [
                ...mappingCodes,
                ...fallbackCodes,
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
            const baseKey = this.normalizedCourseCode(this.subjectBaseKey(subject))

            return baseKey === 'L/F/S' || ['L', 'F', 'S'].includes(baseKey)
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

.robot-timetable-embedded-course-cards {
    display: grid;
    gap: 10px;
    margin-bottom: 16px;
}

.robot-timetable-embedded-course-cards .robot-course-list {
    margin-top: 0;
}

.robot-timetable-embedded-course-cards .robot-course-panel {
    border-color: rgba(37, 99, 235, 0.32);
    background: #f8fbff;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
}

.robot-timetable-embedded-course-cards .robot-course-panel + .robot-course-panel {
    border-color: rgba(234, 88, 12, 0.32);
    background: #fff7ed;
}

.robot-timetable-embedded-course-cards .robot-course-panel__title {
    min-height: 34px;
    padding: 6px 10px;
    border-bottom: 1px solid rgba(37, 99, 235, 0.14);
    border-radius: 8px 8px 0 0;
    background: rgba(219, 234, 254, 0.72);
}

.robot-timetable-embedded-course-cards .robot-course-panel + .robot-course-panel .robot-course-panel__title {
    border-bottom-color: rgba(234, 88, 12, 0.16);
    background: rgba(255, 237, 213, 0.86);
}

.robot-timetable-embedded-course-cards .robot-course-panel__body {
    padding: 8px;
}

.robot-timetable-embedded-course-cards .robot-course-list__title {
    font-size: 0.82rem;
    font-weight: 850;
}

.robot-timetable-embedded-course-cards .robot-course-list__reset {
    min-height: 28px;
}

.robot-timetable-embedded-course-cards .robot-course-list__toggle {
    min-width: 28px;
    min-height: 28px;
}

.robot-timetable-embedded-course-cards--locked .robot-course-panel {
    background: rgba(248, 251, 255, 0.78);
}

.robot-timetable-embedded-course-cards--locked .robot-course-panel__title {
    border-bottom-color: rgba(37, 99, 235, 0.1);
}

.robot-timetable-embedded-course-cards--locked .robot-course-item-row__select,
.robot-timetable-embedded-course-cards--locked .robot-course-item-detail__check {
    pointer-events: none;
}

.robot-timetable-embedded-course-cards--locked .robot-course-panel--additional .robot-course-item-row__select,
.robot-timetable-embedded-course-cards--locked .robot-course-panel--additional .robot-course-item-detail__check {
    pointer-events: auto;
}

.robot-timetable-embedded-course-cards .robot-course-columns {
    gap: 8px;
}

.robot-timetable-embedded-course-cards .robot-course-item-list {
    border-color: rgba(15, 23, 42, 0.14);
    background: #ffffff;
}

.robot-timetable-embedded-course-cards .robot-course-item-header,
.robot-timetable-embedded-course-cards .robot-course-item-row {
    grid-template-columns: 42px minmax(42px, 0.7fr) minmax(96px, 1.6fr) minmax(58px, 0.72fr) minmax(34px, 0.45fr);
    gap: 6px;
}

.robot-timetable-embedded-course-cards .robot-course-item-header {
    padding: 5px 8px;
    background: #f8fafc;
    font-size: 0.68rem;
}

.robot-timetable-embedded-course-cards .robot-course-item-header--expandable {
    padding-right: 34px;
}

.robot-timetable-embedded-course-cards .robot-course-item-panel__title {
    min-height: 39px;
    padding: 0 8px;
}

.robot-timetable-embedded-course-cards .robot-course-item-row {
    font-size: 0.77rem;
}

.robot-timetable-embedded-course-cards .robot-course-item-row > :nth-child(3),
.robot-timetable-embedded-course-cards .robot-course-item-row > :nth-child(4) {
    font-size: 0.72rem;
}

.robot-timetable-embedded-course-cards .robot-course-item-panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 8px 8px;
}

.robot-timetable-embedded-course-cards .robot-course-item-details {
    gap: 5px;
}

.robot-timetable-embedded-course-cards .robot-course-item-details__section {
    padding: 6px;
}

.robot-timetable-embedded-course-cards .robot-course-item-detail-list {
    gap: 4px;
}

.robot-timetable-embedded-course-cards .robot-course-item-detail {
    padding: 4px 6px;
}

.robot-timetable-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.robot-selection {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: start;
}

.robot-selected-cards {
    display: grid;
    grid-template-columns: repeat(5, minmax(92px, 1fr));
    gap: 8px;
}

.robot-selected-cards--constraints {
    grid-template-columns: repeat(3, minmax(120px, 1fr));
}

.robot-selected-cards--student {
    grid-template-columns: minmax(180px, 1fr);
}

.robot-student-selection__content {
    min-width: 0;
}

.robot-student-selection__actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.robot-selected-card {
    min-height: 58px;
    padding: 8px 10px;
    border: 1px solid rgba(57, 73, 171, 0.2);
    border-radius: 8px;
    background: #f8fafc;
}

.robot-selected-card--button {
    cursor: pointer;
    transition:
        border-color 0.16s ease,
        background-color 0.16s ease;
}

.robot-selected-card--button:hover,
.robot-selected-card--button:focus-visible {
    border-color: rgba(57, 73, 171, 0.42);
    background: #eef2ff;
    outline: none;
}

.robot-selected-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.robot-selected-card__label {
    color: #475569;
    font-size: 0.68rem;
    font-weight: 750;
    line-height: 1.15;
}

.robot-selected-card__value {
    margin-top: 4px;
    color: #0f172a;
    font-size: 0.78rem;
    font-weight: 750;
    line-height: 1.18;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

.robot-student-dialog__meta {
    margin-bottom: 10px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.78rem;
    font-weight: 700;
}

.robot-student-search-results {
    display: grid;
    gap: 6px;
    margin-top: 10px;
    max-height: 260px;
    overflow-y: auto;
}

.robot-student-search-results__item {
    justify-content: flex-start;
    min-height: 32px;
}

.robot-student-search-results__empty {
    padding: 8px 2px;
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.78rem;
}

.robot-student-course-overview {
    display: grid;
    gap: 10px;
    margin-top: 10px;
}

.robot-student-course-section {
    padding: 10px;
    border: 1px solid rgba(57, 73, 171, 0.14);
    border-radius: 8px;
}

.robot-student-course-section--completed {
    border-color: rgba(5, 150, 105, 0.22);
    background: #ecfdf5;
}

.robot-student-course-section--planned {
    border-color: rgba(var(--v-theme-success), 0.42);
    background: rgba(var(--v-theme-success), 0.18);
}

.robot-student-course-section--missing {
    border-color: rgba(var(--v-theme-error), 0.3);
    background: rgba(var(--v-theme-error), 0.1);
}

.robot-student-course-section--additional {
    border-color: rgba(234, 88, 12, 0.82);
    background: #bbf7d0;
}

.robot-student-course-section__title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #172554;
    font-size: 0.82rem;
    font-weight: 800;
}

.robot-student-completed-course-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
    margin-top: 10px;
}

.robot-student-completed-course {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 34px;
    padding: 7px 9px;
    border: 1px solid rgba(57, 73, 171, 0.16);
    border-radius: 8px;
    background: #f8fafc;
}

.robot-student-completed-course__subject {
    display: inline-flex;
    align-items: flex-start;
    gap: 2px;
    color: #0f172a;
    font-size: 0.78rem;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.robot-student-completed-course--no-timetable-hours {
    color: #64748b;
    opacity: 0.66;
}

.robot-student-completed-course--no-timetable-hours .robot-student-completed-course__subject,
.robot-student-completed-course--no-timetable-hours :deep(.v-chip__content) {
    text-decoration: line-through;
    text-decoration-thickness: 2px;
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

.robot-info-dialog {
    display: grid;
    gap: 14px;
    color: rgba(var(--v-theme-on-surface), 0.82);
    font-size: 0.9rem;
    line-height: 1.45;
}

.robot-info-dialog__section {
    display: grid;
    gap: 6px;
}

.robot-info-dialog__title {
    color: rgba(var(--v-theme-on-surface), 0.94);
    font-size: 0.92rem;
    font-weight: 800;
}

.robot-info-dialog p {
    margin: 0;
}

.robot-course-list {
    margin-top: 14px;
}

.robot-course-panel {
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 8px;
    background: #ffffff;
}

.robot-course-panel__title {
    min-height: 42px;
    padding: 8px 12px;
}

.robot-course-panel__body {
    padding: 0 12px 12px;
}

.robot-generator {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid rgba(15, 23, 42, 0.1);
}

.robot-generator--embedded {
    width: 100%;
    margin-top: 0;
    padding: 12px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 8px;
    background: #ffffff;
}

@media (min-width: 1280px) {
    .robot-generator--embedded .robot-quality-card {
        gap: 8px;
        padding: 10px;
    }

    .robot-generator--embedded .robot-quality-card__items {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 5px;
    }

    .robot-generator--embedded .robot-quality-card__item {
        gap: 3px 8px;
        padding: 6px 8px;
    }

    .robot-generator--embedded .robot-quality-card__item-label {
        font-size: 0.76rem;
        line-height: 1.18;
    }

    .robot-generator--embedded .robot-quality-card__item-meta {
        font-size: 0.68rem;
        line-height: 1.18;
    }

    .robot-generator--embedded .robot-quality-card__item-count {
        min-width: 58px;
        padding: 3px 9px;
        font-size: 0.7rem;
    }
}

.robot-generator__actions {
    display: flex;
    justify-content: flex-end;
}

.robot-generated {
    margin-top: 14px;
}

.robot-generated-criteria-count {
    display: flex;
    margin: 8px 0 10px;
}

.robot-count-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.robot-count-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    border: 1px solid rgba(var(--v-theme-success), 0.28);
    border-radius: 8px;
    background: rgba(var(--v-theme-success), 0.08);
}

.robot-count-card--green {
    border-color: rgba(var(--v-theme-primary), 0.24);
    background: rgba(var(--v-theme-primary), 0.06);
}

.robot-count-card--criteria {
    width: min(100%, 286px);
}

.robot-count-card__check {
    flex: 0 0 auto;
}

.robot-count-card--conflict {
    border-color: rgba(var(--v-theme-warning), 0.34);
    background: rgba(var(--v-theme-warning), 0.1);
}

.robot-count-card--additional {
    border-color: rgba(234, 88, 12, 0.28);
    background: #fff7ed;
}

.robot-count-card--clickable {
    cursor: pointer;
    transition:
        border-color 0.16s ease,
        box-shadow 0.16s ease,
        transform 0.16s ease;
}

.robot-count-card--clickable:hover,
.robot-count-card--clickable:focus-visible {
    border-color: rgba(var(--v-theme-primary), 0.48);
    box-shadow: inset 0 0 0 1px rgba(var(--v-theme-primary), 0.26);
    outline: none;
    transform: translateY(-1px);
}

.robot-count-card--selected,
.robot-count-card--selected:hover,
.robot-count-card--selected:focus-visible {
    border-color: rgba(var(--v-theme-success), 0.8);
    box-shadow:
        0 0 0 3px rgba(var(--v-theme-success), 0.2),
        inset 0 0 0 1px rgba(var(--v-theme-on-surface), 0.12);
}

.robot-count-card--green.robot-count-card--selected,
.robot-count-card--green.robot-count-card--selected:hover,
.robot-count-card--green.robot-count-card--selected:focus-visible {
    border-color: rgba(var(--v-theme-primary), 0.78);
    box-shadow:
        0 0 0 3px rgba(var(--v-theme-primary), 0.22),
        inset 0 0 0 1px rgba(var(--v-theme-on-surface), 0.12);
}

.robot-count-card--conflict.robot-count-card--selected,
.robot-count-card--conflict.robot-count-card--selected:hover,
.robot-count-card--conflict.robot-count-card--selected:focus-visible {
    border-color: rgba(var(--v-theme-warning), 0.82);
    box-shadow:
        0 0 0 3px rgba(var(--v-theme-warning), 0.24),
        inset 0 0 0 1px rgba(var(--v-theme-on-surface), 0.12);
}

.robot-count-card--additional.robot-count-card--selected,
.robot-count-card--additional.robot-count-card--selected:hover,
.robot-count-card--additional.robot-count-card--selected:focus-visible {
    border-color: rgba(234, 88, 12, 0.82);
    box-shadow:
        0 0 0 3px rgba(234, 88, 12, 0.22),
        inset 0 0 0 1px rgba(var(--v-theme-on-surface), 0.12);
}

.robot-count-card__content {
    min-width: 0;
}

.robot-count-card__label {
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.2;
    text-transform: uppercase;
}

.robot-count-card__meta {
    margin-top: 3px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.74rem;
    font-weight: 650;
    line-height: 1.2;
}

.robot-count-card__value {
    display: flex;
    align-items: center;
    min-height: 32px;
    color: rgba(var(--v-theme-on-surface), 0.92);
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.1;
}

.robot-count-card__actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 8px;
}

.robot-count-card__actions :deep(.v-input) {
    flex: 0 0 auto;
}

.robot-count-card__selected-checkbox {
    flex: 0 0 auto;
    pointer-events: none;
}

.robot-count-card__selected-checkbox :deep(.v-selection-control) {
    min-height: 32px;
}

@media (max-width: 720px) {
    .robot-count-cards {
        grid-template-columns: 1fr;
    }
}

.robot-quality-card {
    display: grid;
    gap: 10px;
    margin-top: 12px;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
    border-radius: 8px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.82);
}

.robot-no-result-alert {
    margin-top: 12px;
}

.robot-no-result-alert__title {
    font-weight: 700;
}

.robot-no-result-alert__reasons {
    margin: 6px 0 0;
    padding-left: 18px;
}

.robot-quality-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.robot-quality-card__title {
    font-size: 0.92rem;
    font-weight: 750;
}

.robot-quality-card__meta {
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.78rem;
}

.robot-quality-card__items {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
}

.robot-quality-card__item--summary {
    grid-column: 1 / -1;
}

.robot-quality-card__item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-rows: auto auto;
    align-items: start;
    gap: 4px 10px;
    border: 1px solid rgba(var(--v-theme-primary), 0.1);
    border-radius: 8px;
    padding: 8px 10px;
    background: rgba(var(--v-theme-primary), 0.04);
}

.robot-quality-card__item-check {
    grid-column: 2;
    grid-row: 1;
    justify-self: end;
    align-self: start;
}

.robot-quality-card__item-copy {
    grid-column: 1;
    grid-row: 1;
    min-width: 0;
}

.robot-quality-card__item-label {
    font-size: 0.84rem;
    font-weight: 700;
    line-height: 1.25;
    overflow-wrap: break-word;
}

.robot-quality-card__item-meta {
    color: rgba(var(--v-theme-on-surface), 0.6);
    font-size: 0.74rem;
}

.robot-quality-card__item-count {
    grid-column: 1 / -1;
    grid-row: 2;
    justify-self: start;
    min-width: 72px;
    border-radius: 999px;
    padding: 4px 12px;
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));
    font-size: 0.78rem;
    font-weight: 800;
    text-align: center;
    white-space: nowrap;
    width: fit-content;
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

.robot-generated-date-overview {
    margin-bottom: 8px;
    padding: 8px 10px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
}

.robot-generated-date-overview__title {
    margin-bottom: 5px;
    font-size: 0.78rem;
    font-weight: 750;
}

.robot-generated-date-overview__groups {
    display: grid;
    gap: 8px;
}

.robot-generated-date-overview__group {
    display: grid;
    gap: 5px;
}

.robot-generated-date-overview__slot {
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
}

.robot-generated-date-overview__courses {
    display: grid;
    gap: 6px;
}

.robot-generated-date-overview__course {
    display: grid;
    gap: 3px;
}

.robot-generated-date-overview__course-title {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px;
    font-size: 0.74rem;
    font-weight: 700;
}

.robot-generated-date-overview__range {
    color: #64748b;
    font-weight: 650;
}

.robot-generated-date-overview__dates {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.robot-generated-date-overview__date {
    padding: 1px 5px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.69rem;
    line-height: 1.45;
    white-space: nowrap;
}

.robot-generated-appointments {
    margin-bottom: 8px;
    padding: 8px 10px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
}

.robot-generated-appointments__title {
    margin-bottom: 5px;
    font-size: 0.78rem;
    font-weight: 750;
}

.robot-generated-appointments__groups {
    display: grid;
    gap: 7px;
}

.robot-generated-appointment-group {
    display: grid;
    gap: 3px;
}

.robot-generated-appointment-group__title {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #334155;
    font-size: 0.76rem;
    font-weight: 800;
}

.robot-generated-appointment-group__check {
    flex: 0 0 auto;
    margin: -6px 0;
}

.robot-generated-appointments__list {
    display: grid;
    gap: 2px;
    margin: 0;
    padding-left: 0;
    list-style: none;
}

.robot-generated-appointment {
    display: grid;
    grid-template-columns: 16px minmax(0, 1fr);
    gap: 6px;
    align-items: start;
    padding: 4px 6px;
    border: 1px solid transparent;
    border-radius: 6px;
    font-size: 0.76rem;
    line-height: 1.28;
}

.robot-generated-appointment--clear {
    border-color: rgba(var(--v-theme-success), 0.2);
    background: rgba(var(--v-theme-success), 0.08);
    color: #14532d;
}

.robot-generated-appointment--conflict {
    border-color: rgba(var(--v-theme-error), 0.24);
    background: rgba(var(--v-theme-error), 0.08);
    color: #7f1d1d;
}

.robot-generated-appointment__icon {
    margin-top: 1px;
}

.robot-generated-appointment__text {
    min-width: 0;
}

.robot-generated-appointment__date {
    font-weight: 650;
}

.robot-generated-appointment__meta {
    margin-left: 4px;
    color: #991b1b;
    font-size: 0.7rem;
    font-weight: 750;
}

.robot-quality-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
    margin-bottom: 8px;
}

.robot-quality-summary__item {
    position: relative;
    display: block;
    min-width: 0;
    min-height: 66px;
    padding: 10px 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.86);
    color: #172d40;
    text-align: left;
    transition: border-color 140ms ease, background 140ms ease, box-shadow 140ms ease;
}

.robot-quality-summary__item--selected {
    border-color: rgba(var(--v-theme-primary), 0.42);
    background: rgba(var(--v-theme-primary), 0.08);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-primary));
}

.robot-quality-summary__item--reached {
    border-color: rgba(var(--v-theme-success), 0.42);
    background: rgba(var(--v-theme-success), 0.1);
    box-shadow: inset 3px 0 0 rgb(var(--v-theme-success));
}

.robot-quality-summary__item--missed {
    border-color: rgba(16, 38, 58, 0.14);
}

.robot-quality-summary__item--additional,
.robot-quality-summary__item--additional.robot-quality-summary__item--reached,
.robot-quality-summary__item--additional.robot-quality-summary__item--missed {
    border-color: rgba(234, 88, 12, 0.82);
    background: #fff7ed;
    box-shadow: inset 3px 0 0 rgb(234, 88, 12);
}

.robot-quality-summary__content {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.robot-quality-summary__label {
    font-size: 0.78rem;
    font-weight: 850;
    line-height: 1.15;
}

.robot-quality-summary__meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 24px;
}

.robot-quality-summary__meta {
    color: rgba(23, 45, 64, 0.68);
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1.15;
}

.robot-quality-summary__controls {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 5px;
    margin-left: auto;
}

.robot-quality-summary__status {
    flex: 0 0 auto;
}

.robot-quality-summary__status--reached {
    color: rgb(var(--v-theme-success));
}

.robot-quality-summary__status--missed {
    color: rgb(var(--v-theme-error));
}

.robot-quality-summary__check {
    flex: 0 0 auto;
    margin: -4px -6px -6px 0;
}

.robot-quality-summary__check :deep(.v-selection-control) {
    min-height: 24px;
}

.robot-quality-summary__check :deep(.v-selection-control__wrapper) {
    width: 24px;
    height: 24px;
}

.robot-generated-grid {
    display: grid;
    grid-template-columns: 64px repeat(var(--robot-generated-weekdays, 6), minmax(62px, 1fr));
    gap: 2px;
    overflow-x: auto;
}

.robot-generated-cell {
    position: relative;
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

.robot-generated-cell--time {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.robot-generated-cell__time-range {
    font-weight: 400;
}

.robot-generated-cell--filled {
    background: #bbf7d0;
    color: #052e16;
}

.robot-generated-cell--additional {
    background: #fed7aa;
    color: #7c2d12;
}

.robot-generated-cell--has-occasional {
    padding-top: 18px;
}

.robot-generated-cell--unavailable {
    background: rgba(var(--v-theme-error), 0.06);
}

.robot-generated-cell__occasional-markers {
    position: absolute;
    top: 3px;
    right: 3px;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 2px;
    max-width: calc(100% - 6px);
    pointer-events: none;
}

.robot-generated-cell__occasional-marker {
    display: inline-flex;
    align-items: flex-start;
    gap: 1px;
    max-width: 100%;
    min-height: 11px;
    padding: 0 3px;
    border: 1px solid rgba(30, 64, 175, 0.2);
    border-radius: 3px;
    background: #bfdbfe;
    color: #1e3a8a;
    font-size: 0.5rem;
    font-weight: 850;
    line-height: 1;
}

.robot-generated-cell__occasional-marker--additional {
    border-color: rgba(234, 88, 12, 0.3);
    background: #fed7aa;
    color: #7c2d12;
}

.robot-generated-cell--conflict {
    background: #fecaca;
    color: #7f1d1d;
}

.robot-generated-cell__code {
    display: inline-flex;
    align-items: flex-start;
    gap: 2px;
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

.robot-generated-cell__same-slots {
    margin-top: 5px;
    font-size: 0.64rem;
    line-height: 1.12;
    overflow-wrap: anywhere;
}

.robot-generated-cell__same-slot-block {
    padding-top: 5px;
    border-top: 1px solid rgba(127, 29, 29, 0.24);
}

.robot-generated-cell__same-slot-block + .robot-generated-cell__same-slot-block {
    margin-top: 5px;
}

.robot-generated-cell__conflict-block + .robot-generated-cell__conflict-block {
    margin-top: 6px;
    padding-top: 5px;
    border-top: 1px solid rgba(127, 29, 29, 0.24);
}

.robot-constraints,
.robot-student-selection {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: start;
    margin-top: 8px;
}

.robot-student-selection {
    padding: 10px;
    border: 1px solid rgba(57, 73, 171, 0.18);
    border-radius: 8px;
    background: #f8fafc;
    margin-bottom: 12px;
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

.robot-generated-header {
    justify-content: flex-start;
    gap: 12px;
    flex-wrap: wrap;
}

.robot-generated-header__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: 0;
    flex-wrap: wrap;
    justify-content: flex-start;
    width: 100%;
}

.robot-generated-back-button {
    flex: 0 0 auto;
    min-height: 48px;
    border-radius: 8px;
    color: #263238 !important;
    font-weight: 400;
    letter-spacing: 0;
    text-transform: none;
}

.robot-generated-overtake-button {
    flex: 0 0 auto;
    min-height: 48px;
    border-radius: 8px;
    font-weight: 400;
    letter-spacing: 0;
    text-transform: none;
}

.robot-timetable-selector {
    display: inline-grid;
    order: -1;
    margin-right: auto;
    grid-template-columns: 34px minmax(104px, auto) 34px auto;
    gap: 8px;
    align-items: center;
    border: 1px solid rgba(var(--v-theme-primary), 0.28);
    border-radius: 8px;
    padding: 5px 8px;
    background: rgba(var(--v-theme-primary), 0.07);
}

.robot-timetable-selector :deep(.v-btn) {
    width: 34px;
    height: 34px;
}

.robot-timetable-selector__value {
    min-width: 104px;
    color: rgba(var(--v-theme-on-surface), 0.92);
    font-size: 1.02rem;
    font-weight: 850;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
}

.robot-timetable-selector__status {
    justify-self: end;
    font-weight: 400;
}

.robot-course-list__header--panel {
    flex-wrap: wrap;
    margin-bottom: 0;
}

.robot-course-list__title {
    font-size: 0.9rem;
    font-weight: 750;
}

.robot-course-list__reset {
    margin-left: auto;
}

.robot-course-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    align-items: start;
}

.robot-course-item-list {
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

.robot-regular-course-column-title {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.1);
    background: #f8fafc;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 800;
}

.robot-course-item-header,
.robot-course-item-row {
    display: grid;
    grid-template-columns: 52px minmax(54px, 0.8fr) minmax(120px, 1.8fr) minmax(70px, 0.8fr) minmax(42px, 0.5fr);
    gap: 8px;
    align-items: center;
}

.robot-course-item-header {
    padding: 8px 10px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.1);
    color: #334155;
    font-size: 0.76rem;
    font-weight: 750;
}

.robot-course-item-header--expandable {
    padding-right: 42px;
}

.robot-course-item-header > :first-child {
    justify-self: center;
}

.robot-course-item-header > :last-child,
.robot-course-item-row > :last-child {
    justify-self: end;
}

.robot-course-item-panels {
    border-radius: 0;
}

.robot-course-item-panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 10px 10px;
}

.robot-course-item-panel {
    border-radius: 0 !important;
    box-shadow: none !important;
}

.robot-course-item-panel + .robot-course-item-panel {
    border-top: 1px solid rgba(15, 23, 42, 0.1);
}

.robot-course-item-panel--disabled {
    color: #64748b;
    opacity: 0.62;
}

.robot-course-item-panel--no-timetable-hours .robot-course-item-row > :not(:first-child) {
    text-decoration: line-through;
    text-decoration-thickness: 2px;
}

.robot-course-item-panel--used {
    background: rgba(var(--v-theme-success), 0.16);
}

.robot-course-item-panel--used :deep(.v-expansion-panel-title) {
    background: rgba(var(--v-theme-success), 0.16);
    color: rgb(var(--v-theme-success));
}

.robot-course-item-panel--used + .robot-course-item-panel,
.robot-course-item-panel + .robot-course-item-panel--used {
    border-top-color: rgba(var(--v-theme-success), 0.42);
}

.robot-course-item-panel--conflict,
.robot-course-item-panel--missing-additional {
    background: rgba(var(--v-theme-error), 0.12);
}

.robot-course-item-panel--conflict :deep(.v-expansion-panel-title),
.robot-course-item-panel--missing-additional :deep(.v-expansion-panel-title) {
    background: rgba(var(--v-theme-error), 0.12);
    color: rgb(var(--v-theme-error));
}

.robot-course-item-panel--conflict + .robot-course-item-panel,
.robot-course-item-panel + .robot-course-item-panel--conflict,
.robot-course-item-panel--missing-additional + .robot-course-item-panel,
.robot-course-item-panel + .robot-course-item-panel--missing-additional {
    border-top-color: rgba(var(--v-theme-error), 0.45);
}

.robot-course-item-panel__title {
    min-height: 53px;
    padding: 0 10px;
}

.robot-course-item-row {
    flex: 1;
    min-width: 0;
    font-size: 0.86rem;
}

.robot-course-item-panels :deep(.v-expansion-panel-title__icon) {
    flex: 0 0 auto;
    min-width: 24px;
    color: rgba(var(--v-theme-primary), 0.72);
}

.robot-course-item-row > :nth-child(3),
.robot-course-item-row > :nth-child(4) {
    font-size: 0.78rem;
    line-height: 1.25;
}

.robot-course-item-row__select {
    display: flex;
    align-items: center;
    justify-content: center;
}

.robot-course-item-row__code {
    display: inline-flex;
    align-items: flex-start;
    gap: 2px;
    font-weight: 750;
}

.robot-course-fu {
    font-size: 0.58em;
    font-weight: 900;
    line-height: 1;
    vertical-align: super;
}

@media (min-width: 701px) {
    .robot-course-week-marker {
        font-size: 0.72em;
        font-weight: 400;
    }
}

.robot-course-item-details {
    display: grid;
    gap: 8px;
    padding-top: 2px;
}

.robot-course-item-details__section {
    padding: 8px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 7px;
    background: #f8fafc;
}

.robot-course-item-details__title {
    margin-bottom: 5px;
    color: #334155;
    font-size: 0.74rem;
    font-weight: 800;
}

.robot-course-item-detail-list {
    display: grid;
    gap: 5px;
}

.robot-course-item-detail {
    padding: 6px 8px;
    border: 1px solid rgba(37, 99, 235, 0.18);
    border-radius: 6px;
    background: #eff6ff;
}

.robot-course-item-detail__main {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #0f172a;
    font-size: 0.76rem;
    font-weight: 750;
    line-height: 1.25;
}

.robot-course-item-detail__check {
    flex: 0 0 auto;
    margin: -4px 0;
}

.robot-course-item-detail--disabled {
    color: #64748b;
    opacity: 0.62;
}

.robot-course-item-detail--used {
    border-color: rgba(var(--v-theme-success), 0.38);
    background: rgba(var(--v-theme-success), 0.14);
}

.robot-course-item-detail--used .robot-course-item-detail__main {
    color: rgb(var(--v-theme-success));
}

.robot-course-item-detail--conflict {
    border-color: rgba(var(--v-theme-error), 0.38);
    background: rgba(var(--v-theme-error), 0.12);
}

.robot-course-item-detail--conflict .robot-course-item-detail__main {
    color: rgb(var(--v-theme-error));
}

.robot-course-item-detail__meta,
.robot-course-item-details__empty {
    margin-top: 2px;
    color: #64748b;
    font-size: 0.7rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

@media (max-width: 700px) {
    .robot-timetable-card {
        border: none !important;
        border-radius: 0 !important;
    }

    .robot-timetable-card__title {
        padding-inline: 4px;
    }

    .robot-timetable-card__text {
        padding-inline: 4px;
    }

    .robot-generator--embedded {
        padding-inline: 4px;
        border: none;
        border-radius: 0;
    }

    .robot-quality-card__items {
        grid-template-columns: minmax(0, 1fr);
    }

    .robot-quality-card__item {
        grid-template-columns: minmax(0, 1fr);
    }

    .robot-quality-card__item-count {
        grid-column: 1 / -1;
    }

    .robot-timetable-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .robot-selection {
        grid-template-columns: minmax(0, 1fr);
    }

    .robot-constraints,
    .robot-student-selection {
        grid-template-columns: minmax(0, 1fr);
    }

    .robot-selected-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .robot-selected-cards--constraints {
        grid-template-columns: minmax(0, 1fr);
    }

    .robot-course-columns {
        grid-template-columns: minmax(0, 1fr);
    }

    .robot-course-item-header,
    .robot-course-item-row {
        grid-template-columns: 44px minmax(46px, 0.8fr) minmax(92px, 1.5fr) minmax(52px, 0.7fr) minmax(34px, 0.4fr);
        gap: 6px;
    }

    .robot-generated-grid {
        grid-template-columns: 38px repeat(var(--robot-generated-weekdays, 6), minmax(48px, 1fr));
    }

    .robot-generated-cell__time-range {
        font-size: 0.6rem;
    }

    .robot-generated-cell {
        padding: 3px;
        font-size: 0.68rem;
    }

    .robot-generated-cell--has-occasional {
        padding-top: 14px;
    }

    .robot-generated-cell__code {
        font-size: 0.68rem;
    }

    .robot-generated-cell__details {
        font-size: 0.58rem;
    }

    .robot-generated-cell__date {
        font-size: 0.6rem;
    }

    .robot-generated-timetable__header {
        flex-direction: column;
        align-items: flex-start;
    }

    .robot-generated-header__actions {
        margin-left: 0;
        width: 100%;
    }

    .robot-generated-overtake-button {
        width: 100%;
    }
}

.robot-eval-criteria-summary {
    margin-top: 8px;
    padding: 8px 10px;
    border: 1px solid rgba(37, 99, 235, 0.10);
    border-radius: 8px;
    background: rgba(248, 251, 255, 0.7);
}

.robot-eval-criteria-summary__title {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 4px;
    color: rgba(15, 23, 42, 0.5);
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.robot-eval-criteria-summary__list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.robot-eval-criteria-summary__item {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(37, 99, 235, 0.08);
    font-size: 0.72rem;
    line-height: 1.5;
}

.robot-eval-criteria-summary__rank {
    color: #1d4ed8;
    font-weight: 700;
    font-size: 0.68rem;
}

.robot-eval-criteria-summary__label {
    color: rgba(15, 23, 42, 0.78);
}

.robot-eval-criteria-summary__option {
    color: rgba(15, 23, 42, 0.5);
    font-size: 0.68rem;
}

.robot-eval-criteria-summary__option::before {
    content: '·';
    margin-right: 2px;
}
</style>
