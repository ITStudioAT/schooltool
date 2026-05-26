<template>
    <v-col cols="12" lg="8" xl="7">
        <v-card rounded="lg" border class="robot-timetable-card">
            <v-card-title class="robot-timetable-card__title d-flex align-center ga-2">
                <v-icon icon="mdi-robot-outline" />
                <span>Wizzard Stundenplan</span>
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
                    title="Hinweise zum Wizzard Stundenplan"
                    aria-label="Hinweise zum Wizzard Stundenplan"
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
                                            class="robot-student-completed-course">
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
                                            class="robot-student-completed-course">
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
                                            class="robot-student-completed-course">
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

                <div v-if="activeEvaluationCriteria.length" class="robot-eval-criteria-summary">
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
                            <div class="robot-course-list__title">Kurse</div>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ selectedCourses.length }} / {{ availableCourses.length }}
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
                                v-for="(courseColumn, columnIndex) in availableCourseColumns"
                                :key="`course-column-${columnIndex}`"
                                class="robot-course-item-list">
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
                                        v-for="course in courseColumn"
                                        :key="course.key"
                                        :value="course.key"
                                        class="robot-course-item-panel"
                                        :class="{
                                            'robot-course-item-panel--disabled': !courseSelected(course),
                                            'robot-course-item-panel--conflict': courseOverlapsInSelectedTimetable(course),
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
                                                            :class="{ 'robot-course-item-detail--disabled': !courseGroupSelected(course, group) }">
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
                    v-if="studentAdditionalCourses.length"
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
                                :disabled="!additionalCourseSelectionResettable"
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
                                            'robot-course-item-panel--missing-additional': additionalCourseMissingInSelectedTimetable(course),
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
                                                        :disabled="!additionalCourseSelectable(course)"
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
                                                            :class="{ 'robot-course-item-detail--disabled': !additionalCourseGroupSelected(course, group) }">
                                                            <div class="robot-course-item-detail__main">
                                                                <v-checkbox
                                                                    :model-value="additionalCourseGroupSelected(course, group)"
                                                                    :aria-label="`${group.title} auswählen`"
                                                                    density="compact"
                                                                    color="primary"
                                                                    :disabled="!additionalCourseSelectable(course)"
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
                            :disabled="loading || !selectedCourses.length"
                            :loading="fullGreenTimetableCountLoading"
                            @click="loadFullGreenTimetableCount">
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
                        <div class="robot-count-cards">
                            <div
                                class="robot-count-card"
                                :class="{ 'robot-count-card--selected': timetableResultCardSelected('full_green') }">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">Volle grüne Stundenpläne</div>
                                    <div class="robot-count-card__value">
                                        <v-progress-circular
                                            v-if="fullGreenTimetableCountLoading"
                                            indeterminate
                                            size="22"
                                            width="2"
                                            color="primary" />
                                        <template v-else>
                                            {{ fullGreenTimetableCountLabel }}
                                        </template>
                                    </div>
                                </div>
                                <div class="robot-count-card__actions">
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
                                </div>
                            </div>

                            <div
                                class="robot-count-card robot-count-card--green"
                                :class="{ 'robot-count-card--selected': timetableResultCardSelected('green') }">
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
                                <div class="robot-count-card__actions">
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
                                </div>
                            </div>

                            <div
                                v-if="showConflictTimetableResults"
                                class="robot-count-card robot-count-card--conflict"
                                :class="{ 'robot-count-card--selected': timetableResultCardSelected('conflict') }">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">Stundenpläne mit Konflikten</div>
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
                                <div class="robot-count-card__actions">
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
                                </div>
                            </div>

                            <div
                                v-if="selectedAdditionalCourses.length"
                                class="robot-count-card robot-count-card--additional"
                                :class="{ 'robot-count-card--selected': additionalCourseTimetableRequired }">
                                <div class="robot-count-card__content">
                                    <div class="robot-count-card__label">Stundenpläne mit Zusatzkursen</div>
                                    <div class="robot-count-card__value">
                                        <v-progress-circular
                                            v-if="fullGreenTimetableCountLoading"
                                            indeterminate
                                            size="22"
                                            width="2"
                                            color="primary" />
                                        <template v-else>
                                            {{ additionalCourseTimetableCountLabel }}
                                        </template>
                                    </div>
                                </div>
                                <div class="robot-count-card__actions">
                                    <v-switch
                                        :model-value="additionalCourseTimetableRequired"
                                        color="deep-orange"
                                        inset
                                        hide-details
                                        density="compact"
                                        :disabled="!isAdditionalCourseTimetableFilterSelectable()"
                                        aria-label="Stundenpläne mit Zusatzkursen auswählen"
                                        @update:modelValue="setAdditionalCourseTimetableRequired($event)" />
                                    <v-icon icon="mdi-plus-circle-outline" color="deep-orange" />
                                </div>
                            </div>
                        </div>

                        <v-alert
                            v-if="selectedOptionsNoResultAlertVisible"
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

                        <div class="robot-quality-card">
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

                    <div v-if="selectedRobotTimetable" class="robot-generated">
                        <div class="robot-course-list__header robot-generated-header">
                            <div class="robot-course-list__title">Stundenplan</div>
                            <div
                                v-if="selectedTimetableResultCount > 0"
                                class="robot-timetable-selector">
                                <v-btn
                                    icon="mdi-chevron-left"
                                    size="small"
                                    variant="tonal"
                                    color="primary"
                                    :disabled="timetableResultCounter(selectedTimetableResultType) <= 1"
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
                                    :disabled="timetableResultCounter(selectedTimetableResultType) >= selectedTimetableResultCount"
                                    :aria-label="`Nächster ${selectedTimetableResultTitle}`"
                                    @click="moveTimetableResultCounter(selectedTimetableResultType, 1)" />
                            </div>
                        </div>

                        <div
                            v-if="activeQualityCriterionRows.length || selectedAdditionalCourses.length"
                            class="robot-quality-summary">
                            <span
                                v-for="counter in activeQualityCriterionRows"
                                :key="`quality-summary-${counter.key}`"
                                class="robot-quality-summary__item">
                                <span class="robot-quality-summary__label">{{ counter.label }}:</span>
                                <v-icon
                                    :icon="qualityCounterReached(counter) ? 'mdi-check-circle' : 'mdi-close-circle'"
                                    :color="qualityCounterReached(counter) ? 'success' : 'error'"
                                    size="16" />
                            </span>
                            <span
                                v-if="selectedAdditionalCourses.length"
                                class="robot-quality-summary__item">
                                <span class="robot-quality-summary__label">Zusatzkurse: {{ additionalCourseAcceptanceLabel() }}</span>
                                <v-icon
                                    :icon="additionalCourseAcceptanceIcon()"
                                    :color="additionalCourseAcceptanceColor()"
                                    size="16" />
                            </span>
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
                                    {{ time.shortTitle }}
                                </div>
                                <div
                                    v-for="weekday in robotTimetableWeekdays"
                                    :key="`robot-${weekday.value}-${time.value}`"
                                    class="robot-generated-cell"
                                    :class="robotTimetableCellClasses(weekday.value, time.value)">
                                    <div
                                        v-if="robotTimetableSlot(weekday.value, time.value)"
                                        class="robot-generated-cell__content">
                                        <div class="robot-generated-cell__code">
                                            <span>{{ robotTimetableSlot(weekday.value, time.value).code }}</span>
                                            <sup
                                                v-if="robotTimetableSlot(weekday.value, time.value).isDistanceLearningCourse"
                                                class="robot-course-fu">
                                                FU
                                            </sup>
                                            <sup
                                                v-if="generatedSlotWeekMarker(robotTimetableSlot(weekday.value, time.value), selectedRobotTimetable)"
                                                class="robot-course-fu robot-course-week-marker">
                                                {{ generatedSlotWeekMarker(robotTimetableSlot(weekday.value, time.value), selectedRobotTimetable) }}
                                            </sup>
                                        </div>
                                        <div
                                            v-if="generatedSlotDateLabel(robotTimetableSlot(weekday.value, time.value))"
                                            class="robot-generated-cell__date">
                                            {{ generatedSlotDateLabel(robotTimetableSlot(weekday.value, time.value)) }}
                                        </div>
                                        <div class="robot-generated-cell__details">
                                            {{ generatedSlotDetails(robotTimetableSlot(weekday.value, time.value)) }}
                                        </div>
                                        <div
                                            v-if="generatedSlotConflicts(robotTimetableSlot(weekday.value, time.value)).length"
                                            class="robot-generated-cell__conflicts">
                                            <div
                                                v-for="conflictBlock in generatedSlotConflictBlocks(robotTimetableSlot(weekday.value, time.value))"
                                                :key="conflictBlock.key"
                                                class="robot-generated-cell__conflict-block">
                                                <div class="robot-generated-cell__code">
                                                    <span>{{ conflictBlock.code }}</span>
                                                    <sup v-if="conflictBlock.isDistanceLearningCourse" class="robot-course-fu">FU</sup>
                                                    <sup v-if="generatedSlotWeekMarker(conflictBlock, selectedRobotTimetable)" class="robot-course-fu robot-course-week-marker">
                                                        {{ generatedSlotWeekMarker(conflictBlock, selectedRobotTimetable) }}
                                                    </sup>
                                                </div>
                                                <div class="robot-generated-cell__details">
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
                            v-if="selectedRobotTimetable && groupedOccasionalAppointments(selectedRobotTimetable).length"
                            class="robot-generated-appointments">
                            <div class="robot-generated-appointments__title">Einzeltermine</div>
                            <div class="robot-generated-appointments__groups">
                                <div
                                    v-for="group in groupedOccasionalAppointments(selectedRobotTimetable)"
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
                    Hinweise zum Wizzard Stundenplan
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
            <EvaluationSettings :closable="true" @close="settingsDialogOpen = false" @saved="onSettingsSaved" />
        </v-dialog>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import EvaluationSettings from '../evaluationSettings/EvaluationSettings.vue'

const ROBOT_TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:robot:last-settings'

export default {
    components: { EvaluationSettings },
    data() {
        return {
            loading: false,
            error: '',
            generationError: '',
            generationProblems: [],
            generatedTimetables: [],
            fullGreenTimetableCount: null,
            greenTimetableCount: null,
            conflictTimetableCount: null,
            additionalCourseTimetableCount: null,
            qualityCounters: [],
            allQualityCriteriaCount: null,
            evaluationCriteria: [],
            fullGreenTimetableNumber: 1,
            greenTimetableNumber: 1,
            conflictTimetableNumber: 1,
            fullGreenTimetableCountError: '',
            fullGreenTimetableCountLoading: false,
            fullGreenTimetableCountRequestId: 0,
            selectedTimetableResultType: 'full_green',
            additionalCourseTimetableRequired: false,
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
        studentPlannedCourses() {
            const semester = this.selectedStudent ? this.studentSemester(this.selectedStudent) : null
            if (!semester) return []

            const completedCourseCodes = this.studentCompletedCourseCodes()

            return this.studentPlannedCoursesForSemester(semester, completedCourseCodes)
        },
        studentMissingCourses() {
            const semester = this.selectedStudent ? this.studentSemester(this.selectedStudent) : null
            if (!semester) return []

            const completedCourseCodes = this.studentCompletedCourseCodes()
            const visitedCourseCodes = this.studentVisitedCourseCodes()

            return this.pendingStudentCoursesBeforeSemester(semester, completedCourseCodes, visitedCourseCodes)
        },
        studentAdditionalCourses() {
            const semester = this.selectedStudent ? this.studentSemester(this.selectedStudent) : null
            if (!semester) return []

            const completedCourseCodes = this.studentCompletedCourseCodes()
            const visitedCourseCodes = this.studentVisitedCourseCodes()
            const missingCourses = this.pendingStudentCoursesBeforeSemester(semester, completedCourseCodes, visitedCourseCodes)
            const unavailableCourseCodes = this.studentUnavailableAdditionalCourseCodes(completedCourseCodes, [
                ...missingCourses,
                ...this.studentPlannedCoursesForSemester(semester, completedCourseCodes),
            ])

            return this.coursesAfterSemester(semester)
                .filter(course => !this.courseCompletedForStudentPlanning(course, unavailableCourseCodes))
                .filter(course => this.coursePossibleAsStudentAdditional(course, completedCourseCodes, visitedCourseCodes))
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
        availableCourses() {
            const semesterCourses = this.coursesForSemester(this.selection.semester)
            if (!this.selectedStudent) return semesterCourses

            const studentSemester = this.studentSemester(this.selectedStudent)
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
                ...semesterCourses,
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
        additionalCourseColumns() {
            const splitIndex = Math.ceil(this.studentAdditionalCourses.length / 2)

            return [
                this.studentAdditionalCourses.slice(0, splitIndex),
                this.studentAdditionalCourses.slice(splitIndex),
            ].filter(courseColumn => courseColumn.length)
        },
        selectedAdditionalCourses() {
            return this.studentAdditionalCourses.filter(course => this.additionalCourseSelected(course))
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
        additionalCourseSelectionResettable() {
            return this.additionalCourseSelectionStateSnapshot(
                this.currentAdditionalCourseSelectionState(),
            ) !== this.additionalCourseSelectionStateSnapshot(this.defaultAdditionalCourseSelectionState())
        },
        configuredCourseGroups() {
            return Array.isArray(this.courseGroups) ? this.courseGroups : []
        },
        qualityCriterionRows() {
            const countersByKey = new Map((this.qualityCounters || []).map(counter => [counter.key, counter]))
            const sourceCriteria = this.evaluationCriteria.length ? this.evaluationCriteria : this.qualityCounters

            return sourceCriteria.map(criterion => ({
                ...criterion,
                ...(countersByKey.get(criterion.key) || {}),
                enabled: criterion.enabled === true,
            }))
        },
        activeQualityCriterionRows() {
            return this.qualityCriterionRows.filter(criterion => criterion.enabled === true)
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
            return this.selectedRobotTimetable ? this.generatedWeekdays : this.emptyTimetableWeekdays
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
        hasGreenTimetableResults() {
            return this.timetableResultCount('full_green') > 0
                || this.timetableResultCount('green') > 0
        },
        showConflictTimetableResults() {
            return !this.hasGreenTimetableResults
                && this.timetableResultCount('conflict') > 0
        },
        timetableCountResultsAvailable() {
            return this.selectedCourses.length > 0
                && [
                    this.fullGreenTimetableCount,
                    this.greenTimetableCount,
                    this.conflictTimetableCount,
                ].some(count => count !== null)
        },
        selectedTimetableResultCount() {
            return this.timetableResultCounterLimit(this.selectedTimetableResultType)
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
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.loadSettings()
        },
        'selected_schoolyear.id'() {
            this.loadSettings()
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
                this.evaluationCriteria = this.enabledEvaluationCriteriaFromSettings(evaluationSettingsResponse.data?.data?.criteria || [])
                this.robotStudents = studentOptionsResponse.data?.data || []
                this.restoreLastRobotState()
                this.syncAvailableTimes()
                this.loadStudentCompletedCourses()
            } catch {
                this.schoolHours = []
                this.courseGroups = []
                this.subjectMappings = []
                this.subjectRows = []
                this.evaluationCriteria = []
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
                ['artsSubject', this.inferredSelectionOptionFromCourseCodes(this.artsSubjectOptions, visitedCourseCodes)],
            ].filter(([, value]) => Boolean(value)))
        },
        inferredSelectionOptionFromCourseCodes(options, courseCodes, aliases = {}) {
            if (!(courseCodes instanceof Set) || !courseCodes.size) return ''

            return (Array.isArray(options) ? options : [])
                .find(option => this.selectionOptionCodeAliases(option?.value, aliases)
                    .some(alias => this.courseCodesContainBase(courseCodes, alias)))?.value || ''
        },
        selectionOptionCodeAliases(value, aliases = {}) {
            const normalizedValue = this.normalizedCourseCode(value)

            return [
                normalizedValue,
                ...(aliases[normalizedValue] || []),
            ]
                .map(alias => this.normalizedCourseCode(alias))
                .filter(Boolean)
                .filter((alias, index, allAliases) => allAliases.indexOf(alias) === index)
        },
        courseCodesContainBase(courseCodes, base) {
            const normalizedBase = this.normalizedCourseCode(base)
            if (!normalizedBase) return false

            return [...courseCodes]
                .some(courseCode => this.courseCodeWithoutModule(courseCode) === normalizedBase)
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
        async onSettingsSaved() {
            this.settingsDialogOpen = false

            try {
                const response = await axios.get('/api/admin/students-timetables/evaluation-settings')
                this.evaluationCriteria = this.enabledEvaluationCriteriaFromSettings(response.data?.data?.criteria || [])
            } catch {
                // keep existing criteria on failure
            }
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
        async loadFullGreenTimetableCount() {
            this.fullGreenTimetableCountError = ''

            if (
                this.loading
                || !(this.subjectRows || []).length
                || !(this.courseGroups || []).length
                || !(this.selectedCourses || []).length
            ) {
                this.fullGreenTimetableCount = 0
                this.greenTimetableCount = 0
                this.conflictTimetableCount = 0
                this.additionalCourseTimetableCount = 0
                this.qualityCounters = []
                this.allQualityCriteriaCount = 0
                this.generatedTimetables = []
                this.additionalCourseTimetableRequired = false
                this.normalizeTimetableResultCounters()
                this.fullGreenTimetableCountLoading = false

                return
            }

            const requestId = this.fullGreenTimetableCountRequestId + 1
            this.fullGreenTimetableCountRequestId = requestId
            this.fullGreenTimetableCountLoading = true
            this.resetQualityCounterSelection()

            try {
                const response = await axios.post('/api/admin/students-timetables/robot/full-green-count', {
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
                    evaluation_criteria: this.storageEvaluationCriteria(this.evaluationCriteria),
                })

                if (requestId !== this.fullGreenTimetableCountRequestId) return

                this.fullGreenTimetableCount = Number(response.data?.data?.full_green_timetable_count || 0)
                this.greenTimetableCount = Number(response.data?.data?.green_timetable_count || 0)
                this.conflictTimetableCount = Number(response.data?.data?.conflict_timetable_count || 0)
                this.additionalCourseTimetableCount = Number(response.data?.data?.additional_course_timetable_count || 0)
                this.qualityCounters = response.data?.data?.quality_counters || []
                this.allQualityCriteriaCount = Number(response.data?.data?.all_quality_criteria_count || 0)
                const selectedResultTypeChanged = this.autoSelectTimetableResultType()
                this.normalizeTimetableResultCounters()

                if (selectedResultTypeChanged && this.timetableResultCount(this.selectedTimetableResultType) > 0) {
                    this.qualityCounters = []
                    this.allQualityCriteriaCount = null
                    this.generatedTimetables = []
                    this.loadFullGreenTimetableCount()

                    return
                }

                this.generatedTimetables = response.data?.data?.selected_timetable
                    ? [this.backendTimetableFromResponse(response.data.data.selected_timetable)]
                    : []
            } catch {
                if (requestId !== this.fullGreenTimetableCountRequestId) return

                this.fullGreenTimetableCount = null
                this.greenTimetableCount = null
                this.conflictTimetableCount = null
                this.additionalCourseTimetableCount = null
                this.qualityCounters = []
                this.allQualityCriteriaCount = null
                this.generatedTimetables = []
                this.normalizeTimetableResultCounters()
                this.fullGreenTimetableCountError = 'Die Anzahl der Stundenpläne konnte nicht berechnet werden.'
            } finally {
                if (requestId === this.fullGreenTimetableCountRequestId) {
                    this.fullGreenTimetableCountLoading = false
                }
            }
        },
        autoSelectTimetableResultType() {
            const selectableResultTypes = this.hasGreenTimetableResults
                ? ['full_green', 'green']
                : ['full_green', 'green', 'conflict']

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
                this.loadFullGreenTimetableCount()

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
                this.loadFullGreenTimetableCount()
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
            if (type === 'conflict') {
                return this.showConflictTimetableResults
                    && this.timetableResultCount(type) > 0
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
                this.loadFullGreenTimetableCount()
            }
        },
        setTimetableResultCounter(type, value) {
            const counter = this.normalizedTimetableResultCounter(value, this.timetableResultCounterLimit(type))

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
        robotTimetableOccasionalMarkers(weekday, time) {
            if (!this.selectedRobotTimetable) return []

            return this.generatedCellOccasionalMarkers(this.selectedRobotTimetable, weekday, time)
        },
        robotTimetableCellClasses(weekday, time) {
            const slot = this.robotTimetableSlot(weekday, time)
            const occasionalMarkers = this.robotTimetableOccasionalMarkers(weekday, time)
            const hasConflicts = slot && this.generatedSlotConflicts(slot).length > 0

            return {
                'robot-generated-cell--filled': Boolean(slot),
                'robot-generated-cell--additional': slot?.isAdditionalCourse === true,
                'robot-generated-cell--conflict': hasConflicts,
                'robot-generated-cell--has-occasional': occasionalMarkers.length > 0,
                'robot-generated-cell--unavailable': !this.weekdayTimeAvailable(weekday, time),
            }
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
        clearGeneratedTimetables() {
            this.generationError = ''
            this.generationProblems = []
            this.generatedTimetables = []
            this.clearTimetableCountResults()
        },
        clearTimetableCountResults() {
            this.fullGreenTimetableCount = null
            this.greenTimetableCount = null
            this.conflictTimetableCount = null
            this.additionalCourseTimetableCount = null
            if (!(this.selectedAdditionalCourses || []).length) {
                this.additionalCourseTimetableRequired = false
            }
            this.qualityCounters = []
            this.allQualityCriteriaCount = null
            this.normalizeTimetableResultCounters()
            this.fullGreenTimetableCountError = ''
        },
        allQualityCriteriaCountLabel() {
            if (!this.activeQualityCriterionRows.length) return '-'
            if (this.allQualityCriteriaCount === null) return '-'

            return `${this.formatNumber(this.allQualityCriteriaCount)} / ${
                this.formatNumber(this.selectedTimetableResultCount)
            }`
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
        courseOverlapsInSelectedTimetable(course) {
            const courseKeys = this.courseComparisonKeys(course)
            if (!courseKeys.length) return false

            const conflictKeys = this.selectedTimetableConflictCourses()
                .flatMap(conflictCourse => this.courseComparisonKeys(conflictCourse))

            return courseKeys.some(courseKey => conflictKeys.includes(courseKey))
        },
        selectedTimetableConflictCourses() {
            return Object.values(this.selectedRobotTimetable?.slots || {})
                .filter(slot => Array.isArray(slot?.conflicts) && slot.conflicts.length)
                .flatMap(slot => [
                    slot,
                    ...slot.conflicts,
                ])
        },
        additionalCourseMissingInSelectedTimetable(course) {
            const courseKeys = this.courseComparisonKeys(course)
            if (!courseKeys.length) return false

            const missingCourseKeys = this.selectedTimetableMissingAdditionalCourses()
                .flatMap(missingCourse => this.courseComparisonKeys(missingCourse))

            return courseKeys.some(courseKey => missingCourseKeys.includes(courseKey))
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
        qualityCounterDetail(counter) {
            if (counter?.enabled !== true) return 'Nicht berücksichtigt.'

            const bestLabel = String(counter?.best_label || '').trim()
            const selectedLabel = String(counter?.selected_label || '').trim()

            if (selectedLabel && selectedLabel !== '-' && bestLabel && bestLabel !== '-') {
                return `Ausgewählt: ${selectedLabel} · Bestwert: ${bestLabel}`
            }

            return bestLabel && bestLabel !== '-' ? `Bestwert: ${bestLabel}` : 'Noch keine Berechnung.'
        },
        resetQualityCounterSelection() {
            this.allQualityCriteriaCount = null
            this.qualityCounters = this.qualityCounters.map(counter => ({
                ...counter,
                selected_value: null,
                selected_label: '-',
                selected_reached: false,
            }))
        },
        normalizedEvaluationCriteria(criteria) {
            return this.cloneCriteria(criteria)
                .map((criterion, index) => ({
                    ...criterion,
                    enabled: criterion.enabled === true,
                    priority: Number(criterion.priority || index + 1),
                    option: criterion.option || null,
                    options: Array.isArray(criterion.options) ? criterion.options : [],
                }))
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
        setEvaluationCriterionEnabled(criterion, enabled) {
            this.evaluationCriteria = this.normalizedEvaluationCriteria(this.evaluationCriteria)
                .map(item => item.key === criterion.key ? { ...item, enabled: enabled === true } : item)

            this.clearTimetableCountResults()

            if (this.timetableCalculationReady()) {
                this.loadFullGreenTimetableCount()
            }
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
                isDistanceLearningCourse: source?.isDistanceLearningCourse === true,
            }
        },
        courseGroupItems(course) {
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
            const deselectedCourseGroupKeys = Array.isArray(this.deselectedCourseGroupKeys)
                ? this.deselectedCourseGroupKeys
                : []

            return !deselectedCourseGroupKeys.includes(this.courseGroupSelectionKey(course, group))
        },
        courseGroupSelectedByLabel(course, label) {
            const deselectedCourseGroupKeys = Array.isArray(this.deselectedCourseGroupKeys)
                ? this.deselectedCourseGroupKeys
                : []

            return !deselectedCourseGroupKeys.includes(this.courseGroupSelectionKey(course, { title: label }))
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
            const groups = this.courseGroupItems(course)

            if (!groups.length) {
                return !this.deselectedCourseKeys.includes(course.key)
            }

            return groups.some(group => this.courseGroupSelected(course, group))
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
            const selectedKeys = Array.isArray(this.additionalCourseSelectedKeys)
                ? this.additionalCourseSelectedKeys
                : []

            return selectedKeys.includes(course?.key)
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
            const prerequisiteCourse = this.additionalCoursePrerequisiteCourse(course)
            if (!prerequisiteCourse) return true

            const selectedKeys = selectedCourseKeys instanceof Set
                ? selectedCourseKeys
                : new Set(Array.isArray(this.additionalCourseSelectedKeys) ? this.additionalCourseSelectedKeys : [])

            return selectedKeys.has(prerequisiteCourse.key)
        },
        setAdditionalCourseSelected(course, selected) {
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
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            }
        },
        setAdditionalCourseGroupSelected(course, group, selected) {
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
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
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
        resetAdditionalCourseSelection() {
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
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
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

                const prerequisiteModuleNumber = moduleNumber - 1
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
        setCourseSelected(course, selected) {
            const selectionChanged = this.setCourseSelectedState(course, selected)
            const groupSelectionChanged = this.setCourseGroupsSelectedState(course, selected)

            if (selectionChanged || groupSelectionChanged) {
                this.clearGeneratedTimetables()
                this.saveLastRobotState()
            }
        },
        setCourseSelectedState(course, selected) {
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
            if (!slot?.isOccasional) return ''

            return this.courseGroupDatesLabel(slot.courseGroup)
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
            const appointments = this.sortedOccasionalAppointments(timetable?.occasionalAppointments || [])

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
            return this.sortedOccasionalAppointments(timetable?.occasionalAppointments || [])
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
                GS: 'GPB',
                GW: 'GWB',
                ME: 'MU',
                S: 'SPA',
                SPA: 'S',
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

                    nextAssignedSlots[key] = {
                        ...option.course,
                        sourceLabel: option.label,
                        alternativeLabels: this.optionAlternativeLabelsForSlot(option, key),
                        courseGroup,
                        isDistanceLearningCourse: this.optionIsDistanceLearningCourse(option),
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
                .map(([slotKey, slot]) => [
                    slotKey,
                    slot?.key || slot?.code || '',
                    slot?.sourceLabel || '',
                ].join(':'))
                .sort()
                .join('|')
        },
        scheduledCourseCountForSlots(slots) {
            return new Set(Object.values(slots || {})
                .filter(slot => slot && !slot.isConflictPreview)
                .map(slot => slot?.key || slot?.code)
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
                        isConflictPreview: true,
                        isDistanceLearningCourse: this.optionIsDistanceLearningCourse(option),
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
                        isDistanceLearningCourse: assignedSlot?.isDistanceLearningCourse === true,
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
        optionHasBlockingUsedSlot(option, assignedSlots, usedSlotKeys) {
            return option.courseGroups.some(courseGroup => {
                const key = this.slotKey(courseGroup.weekday, courseGroup.hour)
                if (!usedSlotKeys.has(key)) return false

                const assignedSlot = assignedSlots?.[key]
                if (!assignedSlot?.courseGroup) return true

                return this.courseGroupsBlockTimetableSlot(assignedSlot.courseGroup, courseGroup)
            })
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
        completedCourseCountsAsDone(grade) {
            const normalizedGrade = String(grade || '').trim().toLocaleUpperCase('de-AT')

            return normalizedGrade === 'B' || ['1', '2', '3', '4'].includes(normalizedGrade)
        },
        courseCompletedForStudentPlanning(course, completedCourseCodes) {
            if (!(completedCourseCodes instanceof Set) || !completedCourseCodes.size) return false

            return this.courseCodeAliases(course)
                .some(courseCode => completedCourseCodes.has(courseCode))
        },
        coursePossibleAsStudentAdditional(course, completedCourseCodes, visitedCourseCodes) {
            return this.courseModulePartsForStudentPlanning(course)
                .some(parts => this.courseModulePrerequisiteMet(parts, completedCourseCodes, visitedCourseCodes))
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

                return baseAliases.some(baseAlias => ['ME', 'MU', 'BE'].includes(baseAlias))
            }

            if (moduleNumber <= 1) return false

            const prerequisiteModuleNumber = moduleNumber === 2 ? 1 : moduleNumber - 2
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
                ET: ['ETH'],
                ETH: ['ET'],
                ME: ['MU'],
                MU: ['ME'],
                R: ['RK'],
                RK: ['R'],
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
    border-color: #15803d;
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

.robot-generator__actions {
    display: flex;
    justify-content: flex-end;
}

.robot-generated {
    margin-top: 14px;
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

.robot-count-card--conflict {
    border-color: rgba(var(--v-theme-warning), 0.34);
    background: rgba(var(--v-theme-warning), 0.1);
}

.robot-count-card--additional {
    border-color: rgba(234, 88, 12, 0.28);
    background: #fff7ed;
}

.robot-count-card--selected {
    box-shadow: inset 0 0 0 1px rgba(var(--v-theme-on-surface), 0.2);
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
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 12px;
    margin-bottom: 8px;
    font-size: 0.78rem;
    color: rgba(var(--v-theme-on-surface), 0.7);
}

.robot-quality-summary__item {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
}

.robot-quality-summary__label {
    font-weight: 600;
}

.robot-generated-grid {
    display: grid;
    grid-template-columns: 88px repeat(var(--robot-generated-weekdays, 6), minmax(72px, 1fr));
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
    gap: 2px;
    max-width: 100%;
    min-height: 13px;
    padding: 0 4px;
    border: 1px solid rgba(30, 64, 175, 0.2);
    border-radius: 4px;
    background: #bfdbfe;
    color: #1e3a8a;
    font-size: 0.58rem;
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
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.robot-timetable-selector {
    display: inline-grid;
    grid-template-columns: 34px minmax(104px, auto) 34px;
    gap: 8px;
    align-items: center;
    margin-left: auto;
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

.robot-course-item-panel--conflict,
.robot-course-item-panel--missing-additional {
    background: rgba(var(--v-theme-error), 0.12);
}

.robot-course-item-panel--conflict :deep(.v-expansion-panel-title),
.robot-course-item-panel--missing-additional :deep(.v-expansion-panel-title) {
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
    width: 100%;
    font-size: 0.86rem;
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
