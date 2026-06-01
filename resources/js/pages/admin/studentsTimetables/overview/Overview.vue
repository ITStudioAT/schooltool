<template>
    <v-col cols="12" xl="10" class="students-timetable-overview">
        <v-card rounded="lg" border>
            <v-card-title class="d-flex flex-wrap align-center ga-2">
                <v-icon icon="mdi-calendar-clock" />
                Stundenplan
                <v-chip v-if="schoolyearName" size="small" variant="tonal" color="light-blue">
                    {{ schoolyearName }}
                </v-chip>
                <v-spacer />
                <v-btn
                    class="overview-print-hidden"
                    color="grey-darken-1"
                    variant="outlined"
                    size="small"
                    prepend-icon="mdi-restore"
                    :disabled="loading || timetableUpdatePending"
                    @click="resetSavedTimetable">
                    Zurücksetzen
                </v-btn>
            </v-card-title>
            <v-card-text class="timetable-card-body">
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3" />

                <v-alert v-if="!loading && !configuredSchoolHours.length" type="info" variant="tonal" class="mb-3">
                    Es sind noch keine Schulstunden hinterlegt. Die Übersicht zeigt vorläufig 10 Stunden.
                </v-alert>

                <div class="overview-student-selection">
                    <div
                        class="transferred-student-context"
                        :class="{ 'transferred-student-context--collapsed': !transferredStudentContextExpanded }">
                        <div
                            class="transferred-student-context__header"
                            role="button"
                            tabindex="0"
                            @click="toggleTransferredStudentContext"
                            @keydown.enter.prevent="toggleTransferredStudentContext"
                            @keydown.space.prevent="toggleTransferredStudentContext">
                            <div class="transferred-student-context__title">
                                <v-icon icon="mdi-account-school-outline" size="18" color="primary" />
                                <span>{{ transferredStudentLabel }}</span>
                                <span class="overview-student-inline-actions">
                                    <v-btn
                                        icon="mdi-pencil"
                                        variant="tonal"
                                        color="primary"
                                        density="comfortable"
                                        size="small"
                                        title="Student bearbeiten"
                                        aria-label="Student bearbeiten"
                                        @click.stop="openStudentDialog" />
                                    <v-btn
                                        v-if="transferredStudentContext"
                                        icon="mdi-close-circle-outline"
                                        variant="text"
                                        color="error"
                                        density="comfortable"
                                        size="small"
                                        title="Student löschen"
                                        aria-label="Student löschen"
                                        @click.stop="clearTransferredStudentSelection" />
                                </span>
                            </div>
                            <div class="transferred-student-context__header-actions">
                                <v-btn
                                    :icon="transferredStudentContextExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                    variant="text"
                                    density="comfortable"
                                    color="primary"
                                    :aria-label="transferredStudentContextExpanded ? 'Studentenkurse einklappen' : 'Studentenkurse ausklappen'"
                                    @click.stop="toggleTransferredStudentContext" />
                            </div>
                        </div>
                        <v-expand-transition>
                            <div
                                v-if="transferredStudentContextExpanded"
                                class="transferred-student-context__sections">
                                <section
                                    v-for="section in visibleTransferredStudentCourseSections"
                                    :key="section.key"
                                    class="transferred-student-course-section">
                                    <div class="transferred-student-course-section__title">
                                        <span>{{ section.title }}</span>
                                        <v-chip size="x-small" :color="section.color" variant="tonal">
                                            {{ section.items.length }}
                                        </v-chip>
                                    </div>
                                    <div
                                        v-if="section.items.length"
                                        class="transferred-student-course-section__chips">
                                        <v-chip
                                            v-for="course in section.items"
                                            :key="course.key"
                                            size="x-small"
                                            :color="section.color"
                                            variant="tonal"
                                            class="transferred-student-course-chip">
                                            <span>{{ course.label }}</span>
                                            <span v-if="course.meta" class="transferred-student-course-chip__meta">
                                                {{ course.meta }}
                                            </span>
                                        </v-chip>
                                    </div>
                                    <div v-else class="transferred-student-course-section__empty">Keine</div>
                                </section>
                            </div>
                        </v-expand-transition>
                    </div>
                </div>

                <div class="overview-selection">
                    <div class="overview-selected-cards">
                        <div
                            v-for="item in selectedSummary"
                            :key="item.key"
                            class="overview-selected-card">
                            <div class="overview-selected-card__label">{{ item.label }}</div>
                            <div class="overview-selected-card__value">{{ item.value }}</div>
                        </div>
                    </div>
                    <v-btn
                        icon="mdi-pencil"
                        variant="tonal"
                        color="primary"
                        title="Auswahl bearbeiten"
                        @click="openSelectionDialog" />
                </div>

                <div class="overview-context-card">
                    <div class="overview-wizard-row">
                        <v-btn
                            v-if="!wizardPanelOpen && !manualPanelOpen"
                            class="overview-wizard-button"
                            variant="tonal"
                            color="primary"
                            size="large"
                            prepend-icon="mdi-calendar-clock"
                            :active="wizardPanelOpen"
                            @click="openWizardPanel">
                            <span class="overview-manual-button__label">
                                <span>Automatischer</span>
                                <span>Stundenplan</span>
                            </span>
                            <span class="overview-wizard-button__stars">
                                <v-icon icon="mdi-star-four-points" size="10" class="overview-star overview-star--1" />
                                <v-icon icon="mdi-star-four-points" size="14" class="overview-star overview-star--2" />
                                <v-icon icon="mdi-star-four-points" size="8" class="overview-star overview-star--3" />
                            </span>
                        </v-btn>
                        <div v-else-if="wizardPanelOpen" class="overview-active-label overview-active-label--auto">
                            <v-icon icon="mdi-calendar-clock" size="20" />
                            <span class="overview-active-label__title">Automatischer Stundenplan</span>
                            <span class="overview-active-label__stars">
                                <v-icon icon="mdi-star-four-points" size="10" class="overview-star overview-star--1" />
                                <v-icon icon="mdi-star-four-points" size="14" class="overview-star overview-star--2" />
                                <v-icon icon="mdi-star-four-points" size="8" class="overview-star overview-star--3" />
                            </span>
                        </div>
                        <v-btn
                            v-if="!wizardPanelOpen && !manualPanelOpen"
                            class="overview-manual-button"
                            variant="tonal"
                            color="primary"
                            size="large"
                            prepend-icon="mdi-calendar-edit"
                            :active="directManualPanelOpen"
                            @click="openManualPanel">
                            <span class="overview-manual-button__label">
                                <span>Manueller</span>
                                <span>Stundenplan</span>
                            </span>
                        </v-btn>
                        <div v-if="directManualPanelOpen" class="overview-active-label overview-active-label--manual">
                            <v-icon icon="mdi-calendar-edit" size="20" />
                            <span class="overview-active-label__title">Manueller Stundenplan</span>
                        </div>
                        <v-btn
                            v-if="manualPanelBackToWizardVisible"
                            class="overview-wizard-close-button"
                            variant="tonal"
                            color="primary"
                            size="large"
                            prepend-icon="mdi-arrow-left"
                            @click="openWizardPanel">
                            Zurück
                        </v-btn>
                        <v-btn
                            v-if="manualPanelBackToWizardVisible"
                            class="overview-wizard-close-button overview-wizard-cancel-button"
                            variant="tonal"
                            color="error"
                            size="large"
                            prepend-icon="mdi-close-circle-outline"
                            @click="resetSavedTimetable">
                            Abbruch
                        </v-btn>
                        <v-btn
                            v-if="wizardPanelOpen || directManualPanelOpen"
                            class="overview-wizard-close-button"
                            variant="tonal"
                            color="primary"
                            size="large"
                            prepend-icon="mdi-close"
                            @click="closeActiveTimetablePanel">
                            Schließen
                        </v-btn>

                        <div v-if="wizardPanelOpen" class="overview-wizard-settings-summary">
                            <div class="overview-wizard-settings-summary__title">
                                <v-icon icon="mdi-tune-variant" size="14" />
                                Bewertungskriterien
                            </div>
                            <div v-if="activeEvaluationCriteria.length" class="overview-wizard-settings-summary__list">
                                <span
                                    v-for="(criterion, index) in activeEvaluationCriteria"
                                    :key="criterion.key"
                                    class="overview-wizard-settings-summary__item">
                                    <span class="overview-wizard-settings-summary__rank">{{ index + 1 }}</span>
                                    <span class="overview-wizard-settings-summary__label">{{ criterion.label }}</span>
                                    <span v-if="criterion.optionLabel" class="overview-wizard-settings-summary__option">
                                        {{ criterion.optionLabel }}
                                    </span>
                                </span>
                            </div>
                            <div v-else class="overview-wizard-settings-summary__empty">
                                Keine Bewertungskriterien aktiv
                            </div>
                        </div>

                        <div v-if="wizardPanelOpen" class="overview-wizard-actions">
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
                        </div>
                    </div>
                </div>

                <div
                    v-if="wizardPanelMounted"
                    v-show="wizardPanelOpen"
                    class="overview-wizard-course-cards-panel">
                    <RobotTimetable
                        ref="wizardCourseCards"
                        embedded-course-cards-only
                        :evaluation-criteria-settings="evaluationCriteria"
                        :student-code="transferredStudentContext?.student?.studentCode || null"
                        class="overview-wizard-course-cards"
                        @timetable-overtaken="showOvertakenManualTimetable">
                        <template #course-actions="{ ready, loading, extending, createActionVisible, hasSelectedAdditionalCourses, extensionActionVisible }">
                            <div
                                v-if="ready && createActionVisible && (!extending || (hasSelectedAdditionalCourses && extensionActionVisible))"
                                class="overview-wizard-footer">
                                <v-btn
                                    class="overview-wizard-create-button"
                                    variant="flat"
                                    color="success"
                                    size="large"
                                    prepend-icon="mdi-calendar-clock"
                                    :disabled="wizardTimetableCreating"
                                    :loading="wizardTimetableCreating"
                                    @click="createWizardTimetable(extending)">
                                    {{ extending ? 'Stundenplan erweitern' : 'Stundenplan erstellen' }}
                                </v-btn>
                            </div>
                            <div
                                v-else-if="loading"
                                class="overview-wizard-footer">
                                <div class="overview-wizard-loading">
                                    <v-progress-circular
                                        indeterminate
                                        size="18"
                                        width="2"
                                        color="primary" />
                                    <span>Kurse werden geladen</span>
                                </div>
                            </div>
                        </template>
                    </RobotTimetable>
                </div>

                <div v-if="manualPanelOpen" class="course-choice-panel">
                    <div class="course-choice-panel__header">
                        <div class="course-choice-panel__title">
                            <v-icon icon="mdi-format-list-checks" size="18" color="primary" />
                            <span>Kursauswahl</span>
                            <v-tooltip text="Kurs wählen">
                                <template #activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        icon="mdi-plus"
                                        color="primary"
                                        variant="flat"
                                        size="small"
                                        aria-label="Kurs wählen"
                                        @click="openCourseMenuDialog" />
                                </template>
                            </v-tooltip>
                        </div>
                        <div class="course-choice-panel__actions">
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ selectedCourseCount }} ausgewählt
                            </v-chip>
                            <v-btn
                                v-if="selectedCourseCount > 0"
                                size="x-small"
                                variant="tonal"
                                color="error"
                                prepend-icon="mdi-close-circle-outline"
                                @click="deselectAllCourses">
                                Alle abwählen
                            </v-btn>
                        </div>
                    </div>

                    <div v-if="selectedCourseFilterChipsAll.length" class="selected-course-filter-chips">
                        <v-chip
                            v-for="filterChip in selectedCourseFilterChipsAll"
                            :key="filterChip.key"
                            size="small"
                            :color="filterChip.hasBlockingOverlap ? 'error' : filterChip.hasRelatedOverlap ? 'warning' : 'success'"
                            variant="tonal"
                            closable
                            class="selected-course-filter-chip"
                            @click:close="handleCourseMenuEntryFilterClick(filterChip.entry)">
                            {{ filterChip.label }}
                        </v-chip>
                    </div>
                </div>

                <div v-if="!wizardPanelOpen && visibleTimetableSemesters.length" class="semester-grid">
                    <section v-for="semester in visibleTimetableSemesters" :key="semester.value" class="semester-timetable">
                        <div class="semester-timetable__header">
                            <div class="d-flex flex-wrap align-center ga-2">
                                <v-icon icon="mdi-calendar-range" size="18" color="primary" />
                                <span class="text-subtitle-2 font-weight-bold">{{ semester.label }}</span>
                                <span class="semester-timetable__dates">{{ semester.dateRangeLabel }}</span>
                            </div>
                            <v-chip size="x-small" color="primary" variant="tonal">{{ weekdayRangeLabel }}</v-chip>
                        </div>

                        <div v-if="recurrenceWeekOptions(semester.value).length > 1" class="recurrence-week-selector">
                            <v-btn-toggle
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
                                class="recurrence-week-selector__btn recurrence-week-selector__pdf-btn overview-print-hidden"
                                color="error"
                                density="compact"
                                variant="flat"
                                size="large"
                                prepend-icon="mdi-file-pdf-box"
                                title="Stundenplan als PDF speichern"
                                aria-label="Stundenplan als PDF speichern"
                                :disabled="loading || timetableUpdatePending"
                                :loading="pdfExporting"
                                @click="downloadTimetablePdf">
                                PDF
                            </v-btn>
                        </div>

                        <div
                            v-for="timetableWeek in visibleTimetableWeeks(semester.value)"
                            :key="timetableWeek.key"
                            class="timetable-week">
                            <div v-if="timetableWeek.showLabel" class="timetable-week__title">
                                {{ timetableWeek.label }}
                            </div>

                            <div
                                class="timetable-generated-grid"
                                :style="timetableGridStyle">
                                <div class="timetable-generated-cell timetable-generated-cell--header">Std.</div>
                                <div
                                    v-for="weekday in displayedWeekdays"
                                    :key="`${semester.value}-${timetableWeek.key}-${weekday.key}`"
                                    class="timetable-generated-cell timetable-generated-cell--header">
                                    {{ weekday.label }}
                                </div>

                                <template
                                    v-for="hour in timetableHoursForSemester(semester.value, timetableWeek)"
                                    :key="`${semester.value}-${timetableWeek.key}-${hour.hour}`">
                                    <div class="timetable-generated-cell timetable-generated-cell--time">
                                        <div class="timetable-hour-num">{{ hour.hour }}.</div>
                                        <div v-if="hour.from || hour.until" class="timetable-hour-time">
                                            {{ hour.from }}<br>{{ hour.until }}
                                        </div>
                                    </div>
                                    <div
                                        v-for="weekday in displayedWeekdays"
                                        :key="`${semester.value}-${timetableWeek.key}-${weekday.key}-${hour.hour}`"
                                        class="timetable-generated-cell"
                                        :class="{
                                            'timetable-generated-cell--filled': displayCourseGroupsForCell(semester.value, weekday.value, hour.hour, timetableWeek).length,
                                            'timetable-generated-cell--conflict': cellHasOverlap(semester.value, weekday.value, hour.hour, timetableWeek),
                                            'timetable-generated-cell--related-overlap': cellHasRelatedOverlap(semester.value, weekday.value, hour.hour, timetableWeek),
                                            'timetable-generated-cell--has-single-date-markers': courseGroupSingleDateOverlapMarkersForCell(semester.value, weekday.value, hour.hour, timetableWeek).length,
                                        }">
                                        <div
                                            v-for="courseGroup in displayCourseGroupsForCell(semester.value, weekday.value, hour.hour, timetableWeek)"
                                            :key="courseGroup.key"
                                            class="timetable-generated-cell__content"
                                            role="button"
                                            tabindex="0"
                                            @click="openCourseGroupDialog(courseGroup)"
                                            @keydown.enter="openCourseGroupDialog(courseGroup)">
                                            <div class="timetable-generated-cell__code">
                                                {{ courseGroup.display_label || courseGroup.title }}
                                            </div>
                                            <div v-if="courseGroup.recurrence_label || courseGroup.is_block" class="timetable-generated-cell__details">
                                                <span v-if="courseGroup.recurrence_label">{{ courseGroup.recurrence_label }}</span>
                                                <span v-if="courseGroup.recurrence_label && courseGroup.is_block"> · </span>
                                                <span v-if="courseGroup.is_block">{{ courseGroupBlockLabel(courseGroup) }}</span>
                                            </div>
                                        </div>
                                        <div
                                            v-if="courseGroupSingleDateOverlapMarkersForCell(semester.value, weekday.value, hour.hour, timetableWeek).length"
                                            class="timetable-generated-cell__single-date-markers">
                                            <button
                                                v-for="marker in courseGroupSingleDateOverlapMarkersForCell(semester.value, weekday.value, hour.hour, timetableWeek)"
                                                :key="marker.key"
                                                type="button"
                                                :title="marker.title"
                                                class="timetable-generated-cell__single-date-marker"
                                                @click="openCourseGroupDialog(marker.courseGroup)">
                                                {{ marker.label }}
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <div
                                    v-if="!timetableHoursForSemester(semester.value, timetableWeek).length"
                                    class="timetable-generated-cell timetable-generated-cell--empty">
                                    Keine Einträge in den sichtbaren Wochentagen.
                                </div>
                            </div>

                            <div v-if="shouldShowExtraDatesNotice(semester.value, timetableWeek)" class="extra-dates-notice">
                                <span class="extra-dates-notice__text">Zusatzwochen vorhanden</span>
                            </div>
                        </div>

                        <div
                            v-if="sameSlotDateOverviewGroupsForSemester(semester.value).length"
                            class="timetable-date-overview">
                            <div class="timetable-date-overview__title">Termine in gleichen Zellen</div>
                            <div class="timetable-date-overview__groups">
                                <div
                                    v-for="dateOverviewGroup in sameSlotDateOverviewGroupsForSemester(semester.value)"
                                    :key="dateOverviewGroup.key"
                                    class="timetable-date-overview__group">
                                    <div class="timetable-date-overview__slot">{{ dateOverviewGroup.title }}</div>
                                    <div class="timetable-date-overview__courses">
                                        <div
                                            v-for="course in dateOverviewGroup.courses"
                                            :key="course.key"
                                            class="timetable-date-overview__course">
                                            <div class="timetable-date-overview__course-title">
                                                <span>{{ course.title }}</span>
                                                <span
                                                    v-if="course.dateRangeLabel"
                                                    class="timetable-date-overview__range">
                                                    {{ course.dateRangeLabel }}
                                                </span>
                                            </div>
                                            <div class="timetable-date-overview__dates">
                                                <span
                                                    v-for="dateLabel in course.dateLabels"
                                                    :key="dateLabel"
                                                    class="timetable-date-overview__date">
                                                    {{ dateLabel }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

        <v-dialog v-model="selectionDialogOpen" persistent max-width="640">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-pencil-outline" />
                    Auswahl bearbeiten
                </v-card-title>
                <v-card-text>
                    <div class="overview-selection-dialog-grid">
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
                    <div class="overview-student-dialog__meta">
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
                    <div class="overview-student-search-results">
                        <v-btn
                            size="small"
                            variant="tonal"
                            :color="studentSelectionDraft.studentCode === null ? 'primary' : 'secondary'"
                            class="overview-student-search-results__item"
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
                                class="overview-student-search-results__item"
                                block
                                @click="selectStudentDraft(student.student_code)">
                                {{ studentOptionTitle(student) }}
                            </v-btn>
                            <div v-if="!filteredStudentResults.length" class="overview-student-search-results__empty">
                                Keine Schüler gefunden
                            </div>
                        </template>
                        <div v-else class="overview-student-search-results__empty">
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

        <v-dialog v-model="courseMenuDialog" persistent max-width="760">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-plus-circle" color="primary" />
                    Kurs wählen
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" size="small" @click="courseMenuDialog = false" />
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="course-menu-dialog-options">
                        <v-btn-toggle
                            :model-value="courseChoiceRestrictionMode"
                            density="compact"
                            variant="text"
                            rounded="lg"
                            selected-class="course-choice-restriction-option--active"
                            mandatory
                            class="course-choice-restriction-switch"
                            @update:model-value="handleCourseChoiceRestrictionUpdate">
                            <v-btn
                                v-for="option in courseChoiceRestrictionOptions"
                                :key="option.value"
                                :value="option.value"
                                class="course-choice-restriction-option"
                                size="small">
                                {{ option.label }}
                            </v-btn>
                        </v-btn-toggle>
                    </div>

                    <div class="course-menu-dialog-chips">
                        <v-chip
                            v-for="courseMenu in allCourseChoiceMenus"
                            :key="courseMenu.key"
                            size="small"
                            :color="courseMenuHasActiveSelection(courseMenu) ? 'success' : 'primary'"
                            :variant="selectedCourseMenuKey === courseMenu.key ? 'flat' : 'tonal'"
                            class="course-menu-dialog-chip"
                            @click="selectCourseMenu(courseMenu)">
                            {{ courseMenu.label }}
                        </v-chip>
                    </div>

                    <div v-if="selectedCourseMenu" class="course-choice-panel__semesters course-menu-dialog-items">
                        <section
                            :key="selectedCourseMenu.key"
                            class="course-choice-semester">
                            <div class="course-choice-semester__label">
                                <span>{{ selectedCourseMenu.semesterLabel }}</span>
                                <span class="course-choice-semester__dates">{{ selectedCourseMenu.semesterDateRangeLabel }}</span>
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ selectedCourseMenu.label }}
                                </v-chip>
                            </div>
                            <div class="course-item-chips">
                                <v-chip
                                    v-for="entryOption in selectedCourseMenuEntryOptions"
                                    :key="entryOption.key"
                                    size="small"
                                    :color="entryOption.color"
                                    :variant="entryOption.isActive ? 'flat' : 'tonal'"
                                    class="course-item-chip"
                                    @click="handleCourseMenuEntryFilterClick(entryOption.entry)">
                                    <v-icon
                                        :icon="entryOption.isActive ? 'mdi-check' : 'mdi-calendar-blank'"
                                        size="16"
                                        start />
                                    <span>{{ entryOption.label }}</span>
                                    <span v-if="entryOption.scheduleLabel" class="course-item-chip__schedule">
                                        {{ entryOption.scheduleLabel }}
                                    </span>
                                </v-chip>
                            </div>
                        </section>
                    </div>
                </v-card-text>
            </v-card>
        </v-dialog>

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

        <v-dialog v-model="infoDialogOpen" persistent max-width="680">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon icon="mdi-information-outline" />
                    Hinweise zum Stundenplan Wizzard
                </v-card-title>
                <v-card-text>
                    <div class="overview-wizard-info-dialog">
                        <div class="overview-wizard-info-dialog__section">
                            <div class="overview-wizard-info-dialog__title">Noten</div>
                            <p>
                                Die Note B bedeutet befreit. Das Modul wurde also angerechnet und gilt für die Planung
                                wie ein positiv abgeschlossenes Modul.
                            </p>
                        </div>

                        <div class="overview-wizard-info-dialog__section">
                            <div class="overview-wizard-info-dialog__title">Grundregel für Folgemodule</div>
                            <p>
                                In Deutsch, Englisch, Mathematik, Französisch, Latein, Spanisch und Informatik darf ein
                                Modul erst gebucht werden, wenn das Modul zwei Stufen darunter positiv abgeschlossen
                                oder mit B angerechnet wurde. Beispiel: M5 darf erst gebucht werden, wenn M3 positiv
                                oder angerechnet ist.
                            </p>
                        </div>

                        <div class="overview-wizard-info-dialog__section">
                            <div class="overview-wizard-info-dialog__title">Letzte Module gemeinsam buchen</div>
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

        <v-dialog v-model="settingsDialogOpen" persistent max-width="1120" scrollable>
            <EvaluationSettings
                :closable="true"
                @changed="onSettingsChanged"
                @close="settingsDialogOpen = false"
                @saved="onSettingsSaved" />
        </v-dialog>
    </v-col>
</template>

<script>
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import LoadingAnimation from '@/pages/components/LoadingAnimation.vue'
import EvaluationSettings from '../evaluationSettings/EvaluationSettings.vue'
import RobotTimetable from '../robot/RobotTimetable.vue'

const FALLBACK_HOUR_COUNT = 10
const ALL_DATES_OPTION_VALUE = 'all_dates'
const ALL_WEEKS_OPTION_VALUE = 'all_weeks'
const TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:overview:last-timetable'
const ROBOT_TIMETABLE_STORAGE_KEY_PREFIX = 'students-timetables:robot:last-settings'
const TIMETABLE_OVERVIEW_BASE_PATH = '/admin/students-timetables/timetable/overview'
const TIMETABLE_OVERVIEW_LANDING_PATH = '/admin/students-timetables'
const TIMETABLE_OVERVIEW_ROUTE_MODES = ['automatic', 'manual', 'adopted']
const COURSE_CHOICE_RESTRICTION_PLANNED = 'planned'
const COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL = 'planned_additional'
const COURSE_CHOICE_RESTRICTION_ALL = 'all'
const COURSE_CHOICE_RESTRICTION_MODES = [
    COURSE_CHOICE_RESTRICTION_PLANNED,
    COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL,
    COURSE_CHOICE_RESTRICTION_ALL,
]

export default {
    name: 'StudentsTimetablesOverview',
    components: {
        EvaluationSettings,
        LoadingAnimation,
        RobotTimetable,
    },

    data() {
        return {
            loading: false,
            schoolHours: [],
            courseGroups: [],
            courseMenuDialog: false,
            selectedCourseMenuKey: '',
            courseGroupDialog: false,
            selectedCourseGroup: null,
            infoDialogOpen: false,
            settingsDialogOpen: false,
            wizardPanelOpen: false,
            wizardPanelMounted: false,
            manualPanelOpen: false,
            manualPanelSource: null,
            wizardTimetableCreating: false,
            timetableUpdatePending: false,
            pdfExporting: false,
            studentDialogOpen: false,
            studentOptionsLoading: false,
            studentSearch: '',
            robotStudents: [],
            subjectRows: [],
            evaluationCriteria: [],
            studentSelectionDraft: {
                studentCode: null,
            },
            studentCompletedCoursesRequestId: 0,
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
            restrictCourseChoiceBySelection: false,
            courseChoiceRestrictionMode: COURSE_CHOICE_RESTRICTION_ALL,
            transferredStudentContext: null,
            transferredStudentContextExpanded: true,
            selectionDialogOpen: false,
            selection: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
            selectionDraft: {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            },
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
        activeEvaluationCriteria() {
            return this.evaluationCriteria
                .filter(criterion => criterion.enabled)
                .map(criterion => ({
                    key: criterion.key,
                    label: criterion.label,
                    optionLabel: criterion.option && criterion.options.length
                        ? (criterion.options.find(option => option.value === criterion.option)?.label || null)
                        : null,
                }))
        },
        semesters() {
            return [
                {
                    value: 1,
                    label: 'Semester',
                    dateRangeLabel: this.semesterDateRange(1),
                },
                {
                    value: 2,
                    label: 'Semester',
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
            if (this.hasVisibleSaturdayCourses) {
                return this.weekdays
            }

            return this.weekdays.filter((weekday) => weekday.value !== 6)
        },
        timetableGridStyle() {
            return {
                '--overview-timetable-weekdays': this.displayedWeekdays.length,
            }
        },
        weekdayRangeLabel() {
            return this.hasVisibleSaturdayCourses ? 'Mo-Sa' : 'Mo-Fr'
        },
        hasVisibleSaturdayCourses() {
            return this.configuredCourseGroups.some((courseGroup) => (
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
        courseChoiceCourseGroups() {
            const courseRestrictionContext = this.courseChoiceRestrictionContext || this.buildCourseChoiceRestrictionContext()
            const courseRestrictionMode = courseRestrictionContext.mode

            if (courseRestrictionMode === COURSE_CHOICE_RESTRICTION_ALL) return this.configuredCourseGroups

            return this.configuredCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourseChoiceRestriction(courseGroup, courseRestrictionContext))
        },
        courseChoiceRestrictionContext() {
            return this.buildCourseChoiceRestrictionContext()
        },
        courseChoiceRestrictionOptions() {
            return [
                { value: COURSE_CHOICE_RESTRICTION_PLANNED, label: 'Vorgesehene Kurse' },
                { value: COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL, label: 'Zusätzliche Kurse' },
                { value: COURSE_CHOICE_RESTRICTION_ALL, label: 'Alle' },
            ]
        },
        selectionCourseChoiceCodes() {
            const semester = Number(this.selection?.semester || 0)
            if (!Number.isFinite(semester) || semester <= 0 || !Array.isArray(this.subjectRows) || !this.subjectRows.length) {
                return new Set()
            }

            return new Set(this.coursesForSemester(semester)
                .flatMap(course => this.courseAliasesFromValues([
                    course?.code,
                    course?.label,
                    course?.name,
                ])))
        },
        restrictedStudentCourseCodes() {
            const courseRestrictionMode = this.normalizedCourseChoiceRestrictionMode(this.courseChoiceRestrictionMode, {
                restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
            })
            const studentCourses = Array.isArray(this.courseChoiceRestrictionCourses)
                ? this.courseChoiceRestrictionCourses
                : this.courseChoiceRestrictionItemsFromCourses(
                    this.transferredStudentContext?.courses || this.noStudentCourseHistory,
                    courseRestrictionMode,
                )
            const selectionCourseChoiceCodes = this.selectionCourseChoiceCodes instanceof Set
                ? this.selectionCourseChoiceCodes
                : new Set()

            return new Set((studentCourses.length ? studentCourses : [...selectionCourseChoiceCodes])
                .flatMap(course => (
                    typeof course === 'string'
                        ? [course]
                        : this.courseAliasesFromValues([
                            course?.code,
                            course?.label,
                            course?.name,
                        ])
                ))
                .filter(Boolean))
        },
        courseChoiceRestrictionCourses() {
            const courses = this.transferredStudentContext?.courses || this.noStudentCourseHistory

            return this.courseChoiceRestrictionItemsFromCourses(
                courses,
                this.normalizedCourseChoiceRestrictionMode(this.courseChoiceRestrictionMode, {
                    restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
                }),
            )
        },
        transferredStudentCourseRestrictionItems() {
            return this.courseChoiceRestrictionItemsFromCourses(
                this.transferredStudentContext?.courses,
                COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL,
            )
        },
        selectedCourseMenuEntriesBySemester() {
            return {
                1: this.buildSelectedCourseMenuEntries(1),
                2: this.buildSelectedCourseMenuEntries(2),
            }
        },
        selectedCourseFilterChipsAll() {
            return this.semesters.flatMap((semester) => this.selectedCourseFilterChips(semester.value))
        },
        selectedCourseCount() {
            return this.selectedCourseMenuEntriesBySemester[1].length
                + this.selectedCourseMenuEntriesBySemester[2].length
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
        studentTotalCountLabel() {
            const count = Array.isArray(this.robotStudents) ? this.robotStudents.length : 0

            return `${this.formatNumber(count)} Studenten gesamt`
        },
        transferredStudentLabel() {
            return this.transferredStudentContext?.student?.label || 'Kein Student'
        },
        transferredStudentCourseSections() {
            const courses = this.transferredStudentContext?.courses || this.noStudentCourseHistory

            return [
                { key: 'completed', title: 'Abgeschlossene Kurse', color: 'primary', items: courses.completed || [] },
                { key: 'missing', title: 'Fehlende Kurse', color: 'error', items: courses.missing || [] },
                { key: 'planned', title: 'Vorgesehene Kurse', color: 'info', items: courses.planned || [] },
                { key: 'additional', title: 'Zusätzliche Kurse', color: 'success', items: courses.additional || [] },
            ]
        },
        visibleTransferredStudentCourseSections() {
            return this.transferredStudentCourseSections
        },
        noStudentCourseHistory() {
            return this.overviewStudentCourseHistory([])
        },
        visibleCourseChoiceSemesters() {
            return this.semesters.filter((semester) => this.semesterCourseMenus(semester.value).length > 0)
        },
        allCourseChoiceMenus() {
            return this.visibleCourseChoiceSemesters.flatMap((semester) => (
                this.semesterCourseMenus(semester.value).map((courseMenu) => ({
                    ...courseMenu,
                    semesterValue: semester.value,
                    semesterLabel: semester.label,
                    semesterDateRangeLabel: semester.dateRangeLabel,
                }))
            ))
        },
        selectedCourseMenu() {
            return this.allCourseChoiceMenus.find((courseMenu) => courseMenu.key === this.selectedCourseMenuKey) || null
        },
        selectedCourseMenuEntryOptions() {
            const selectedCourseMenu = this.selectedCourseMenu
            if (!selectedCourseMenu) return []

            const semester = selectedCourseMenu.semesterValue

            return (selectedCourseMenu.entries || []).map((entry) => {
                const hasBlockingOverlap = this.courseMenuEntryHasBlockingOverlap(entry, semester)
                const hasRelatedOverlap = this.courseMenuEntryHasRelatedOverlap(entry, semester, { hasBlockingOverlap })
                const isActive = this.isCourseMenuEntryFilterActive(entry)

                return {
                    ...entry,
                    entry,
                    hasBlockingOverlap,
                    hasRelatedOverlap,
                    isActive,
                    color: hasBlockingOverlap ? 'error' : hasRelatedOverlap ? 'warning' : isActive ? 'success' : 'primary',
                }
            })
        },
        visibleTimetableSemesters() {
            return this.semesters.filter((semester) => this.selectedCourseMenuEntries(semester.value).length > 0)
        },
        canReturnToWizardPanel() {
            return this.wizardPanelMounted || this.savedRobotTimetableStateAvailable()
        },
        directManualPanelOpen() {
            return this.manualPanelOpen && this.manualPanelSource === 'direct'
        },
        manualPanelBackToWizardVisible() {
            return this.manualPanelOpen
                && this.manualPanelSource === 'wizard'
                && this.canReturnToWizardPanel
                && !this.wizardPanelOpen
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
            this.applyTimetableOverviewModeFromRoute()
            this.loadData()
        },
        '$route.params.detail'(detail) {
            this.applyTimetableOverviewModeFromRoute(detail)
        },
    },

    mounted() {
        this.restoreLastTimetableState()
        this.applyTimetableOverviewModeFromRoute()
        this.loadData()
    },

    methods: {
        async loadData() {
            this.loading = true
            try {
                const [schoolHoursResponse, courseGroupsResponse] = await Promise.all([
                    axios.get('/api/admin/students-timetables/school-hours'),
                    axios.get('/api/admin/students-timetables/course-groups'),
                ])

                this.schoolHours = schoolHoursResponse.data?.data || []
                this.courseGroups = courseGroupsResponse.data?.data || []
            } catch {
                this.schoolHours = []
                this.courseGroups = []
                this.activeCourseGroupFilterKeys = []
            } finally {
                this.loading = false
            }

            this.studentOptionsLoading = true
            try {
                const studentsResponse = await axios.get('/api/admin/students-timetables/robot/students')

                this.robotStudents = studentsResponse.data?.data || []
            } catch {
                this.robotStudents = []
            } finally {
                this.studentOptionsLoading = false
            }

            try {
                const settingsResponse = await axios.get('/api/admin/students-timetables/subjects-overview-settings')

                this.subjectRows = settingsResponse.data?.data?.subjects || []
            } catch {
                this.subjectRows = []
            }

            try {
                const evaluationSettingsResponse = await axios.get('/api/admin/students-timetables/evaluation-settings')

                this.evaluationCriteria = this.enabledEvaluationCriteriaFromSettings(evaluationSettingsResponse.data?.data?.criteria || [])
            } catch {
                this.evaluationCriteria = []
            }

            if (this.transferredStudentContext?.student?.studentCode) {
                await this.loadTransferredStudentCompletedCourses(this.transferredStudentContext.student.studentCode)
            } else {
                this.refreshTransferredStudentCourseHistory()
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
        normalizedTimetableOverviewRouteMode(mode = this.$route?.params?.detail) {
            const normalizedMode = String(mode || '').trim()

            return TIMETABLE_OVERVIEW_ROUTE_MODES.includes(normalizedMode) ? normalizedMode : ''
        },
        timetableOverviewModePath(mode = '') {
            const normalizedMode = this.normalizedTimetableOverviewRouteMode(mode)

            return normalizedMode ? `${TIMETABLE_OVERVIEW_BASE_PATH}/${normalizedMode}` : TIMETABLE_OVERVIEW_LANDING_PATH
        },
        navigateToTimetableOverviewMode(mode = '') {
            const path = this.timetableOverviewModePath(mode)
            if (this.$route?.path === path) return

            const navigation = this.$router?.push?.({ path })
            navigation?.catch?.(() => {})
        },
        applyTimetableOverviewModeFromRoute(mode = this.$route?.params?.detail) {
            const normalizedMode = this.normalizedTimetableOverviewRouteMode(mode)
            if (!normalizedMode) {
                this.applyTimetableOverviewLandingState()

                return
            }

            if (normalizedMode === 'automatic') {
                this.openWizardPanel({ syncRoute: false })

                return
            }

            if (normalizedMode === 'manual') {
                this.openManualPanel({ syncRoute: false })

                return
            }

            this.wizardPanelMounted = true
            this.wizardPanelOpen = false
            this.manualPanelOpen = true
            this.manualPanelSource = 'wizard'
        },
        applyTimetableOverviewLandingState() {
            const defaults = this.defaultTimetableState()
            const currentState = this.currentTimetableState()

            this.applyTimetableState({
                ...currentState,
                activeCourseGroupFilterKeys: defaults.activeCourseGroupFilterKeys,
                selectedRecurrenceWeeks: defaults.selectedRecurrenceWeeks,
                expandedRecurrenceWeeks: defaults.expandedRecurrenceWeeks,
                showExtraDatesInSelectedWeeks: defaults.showExtraDatesInSelectedWeeks,
                manualPanelOpen: false,
                manualPanelSource: null,
            })
            this.wizardPanelOpen = false
            this.wizardPanelMounted = false
            this.manualPanelOpen = false
            this.manualPanelSource = null
        },
        openWizardPanel(options = {}) {
            this.wizardPanelMounted = true
            this.wizardPanelOpen = true
            this.manualPanelOpen = false
            this.manualPanelSource = null
            if (options?.syncRoute !== false) {
                this.navigateToTimetableOverviewMode?.('automatic')
            }
        },
        closeWizardPanel() {
            this.wizardPanelOpen = false
            this.navigateToTimetableOverviewMode?.()
        },
        closeActiveTimetablePanel() {
            if (this.wizardPanelOpen) {
                this.closeWizardPanel()

                return
            }

            this.closeManualPanel()
        },
        async createWizardTimetable(requireAdditionalCourses = false) {
            if (this.wizardTimetableCreating) return

            const wizardCourseCards = this.$refs.wizardCourseCards
            const createTimetables = wizardCourseCards?.createTimetables || wizardCourseCards?.loadFullGreenTimetableCount

            if (!createTimetables) return

            this.wizardTimetableCreating = true

            try {
                await createTimetables.call(wizardCourseCards, {
                    requireAdditionalCourses: requireAdditionalCourses === true,
                })
            } finally {
                this.wizardTimetableCreating = false
            }
        },
        openManualPanel(options = {}) {
            this.manualPanelOpen = true
            this.manualPanelSource = 'direct'
            this.wizardPanelOpen = false
            if (options?.syncRoute !== false) {
                this.navigateToTimetableOverviewMode?.('manual')
            }
        },
        closeManualPanel() {
            this.manualPanelOpen = false
            this.manualPanelSource = null
            this.navigateToTimetableOverviewMode?.()
        },
        toggleManualPanel() {
            if (this.manualPanelOpen && this.manualPanelSource === 'direct') {
                this.closeManualPanel()

                return
            }

            this.openManualPanel()
        },
        showOvertakenManualTimetable(timetableState) {
            this.runTimetableUpdate(() => {
                this.applyTimetableState({
                    ...(timetableState || {}),
                    manualPanelOpen: true,
                    manualPanelSource: 'wizard',
                })
                this.wizardPanelOpen = false
                this.manualPanelOpen = true
                this.manualPanelSource = 'wizard'
                this.navigateToTimetableOverviewMode?.('adopted')
                this.saveLastTimetableState()
            })
        },
        onSettingsChanged(criteria) {
            this.evaluationCriteria = this.enabledEvaluationCriteriaFromSettings(criteria || [])
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
        cloneCriteria(criteria) {
            return JSON.parse(JSON.stringify(criteria || []))
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
        handleCourseMenuEntryFilterClick(entry) {
            this.toggleCourseMenuEntryFilter(entry)
        },
        handleSelectedTimetableOptionValueUpdate(semester, value) {
            this.runTimetableUpdate(() => {
                this.setSelectedTimetableOptionValue(semester, value)
            })
        },
        handleCourseChoiceRestrictionUpdate(value) {
            const courseChoiceRestrictionMode = this.normalizedCourseChoiceRestrictionMode(value)
            if (courseChoiceRestrictionMode === this.courseChoiceRestrictionMode) return

            this.courseChoiceRestrictionMode = courseChoiceRestrictionMode
            this.restrictCourseChoiceBySelection = this.courseChoiceRestrictionMode !== COURSE_CHOICE_RESTRICTION_ALL
        },
        async downloadTimetablePdf() {
            if (this.pdfExporting) return

            this.pdfExporting = true

            try {
                const response = await axios.post(
                    '/api/admin/students-timetables/overview/pdf',
                    this.timetablePdfPayload(),
                    { responseType: 'blob' },
                )
                const fileName = this.fileNameFromContentDisposition(response?.headers?.['content-disposition'])
                    || 'stundenplan.pdf'
                const blob = response?.data instanceof Blob
                    ? response.data
                    : new Blob([response?.data], { type: 'application/pdf' })

                this.downloadBlob(blob, fileName)
            } catch (error) {
                console.error(error)
                window.alert?.('PDF konnte nicht erstellt werden.')
            } finally {
                this.pdfExporting = false
            }
        },
        timetablePdfPayload() {
            return {
                title: 'Stundenplan',
                schoolyear: this.schoolyearName || '',
                student: this.transferredStudentContext?.student?.label || '',
                generated_at: new Intl.DateTimeFormat('de-AT', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                }).format(new Date()),
                weekdays: this.displayedWeekdays.map(weekday => ({
                    label: weekday.label,
                })),
                semesters: this.visibleTimetableSemesters.map(semester => this.timetablePdfSemesterPayload(semester)),
            }
        },
        timetablePdfSemesterPayload(semester) {
            return {
                label: semester.label,
                date_range: semester.dateRangeLabel,
                weeks: this.visibleTimetableWeeks(semester.value)
                    .map(timetableWeek => this.timetablePdfWeekPayload(semester, timetableWeek)),
            }
        },
        timetablePdfWeekPayload(semester, timetableWeek) {
            return {
                label: timetableWeek.showLabel ? timetableWeek.label : '',
                hours: this.timetableHoursForSemester(semester.value, timetableWeek)
                    .map(hour => this.timetablePdfHourPayload(semester, timetableWeek, hour)),
            }
        },
        timetablePdfHourPayload(semester, timetableWeek, hour) {
            return {
                hour: Number(hour.hour),
                from: hour.from || '',
                until: hour.until || '',
                cells: this.displayedWeekdays.map(weekday => (
                    this.timetablePdfCellPayload(semester.value, weekday.value, hour.hour, timetableWeek)
                )),
            }
        },
        timetablePdfCellPayload(semester, weekday, hour, timetableWeek) {
            const courses = this.displayCourseGroupsForCell(semester, weekday, hour, timetableWeek)
                .map(courseGroup => this.timetablePdfCoursePayload(courseGroup))
            const markers = this.courseGroupSingleDateOverlapMarkersForCell(semester, weekday, hour, timetableWeek)
                .map(marker => ({
                    label: marker.label || '',
                    title: marker.title || '',
                }))

            return {
                status: this.timetablePdfCellStatus(semester, weekday, hour, timetableWeek, courses, markers),
                courses,
                markers,
            }
        },
        timetablePdfCellStatus(semester, weekday, hour, timetableWeek, courses, markers) {
            if (this.cellHasOverlap(semester, weekday, hour, timetableWeek)) return 'conflict'
            if (this.cellHasRelatedOverlap(semester, weekday, hour, timetableWeek)) return 'related'
            if (courses.length || markers.length) return 'filled'

            return 'empty'
        },
        timetablePdfCoursePayload(courseGroup) {
            return {
                label: (courseGroup?.display_label || courseGroup?.title || courseGroup?.course || '').toString(),
                details: [
                    courseGroup?.recurrence_label,
                    courseGroup?.is_block ? this.courseGroupBlockLabel(courseGroup) : '',
                ].filter(Boolean).join(' · '),
            }
        },
        downloadBlob(blob, filename) {
            const objectUrl = URL.createObjectURL(blob)
            const link = document.createElement('a')

            link.href = objectUrl
            link.download = filename
            document.body.appendChild(link)
            link.click()
            link.remove()
            URL.revokeObjectURL(objectUrl)
        },
        fileNameFromContentDisposition(headerValue) {
            const normalizedHeader = String(headerValue || '').trim()
            if (!normalizedHeader) return ''

            const utf8Match = normalizedHeader.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
            if (utf8Match?.[1]) {
                try {
                    return decodeURIComponent(utf8Match[1]).replace(/["']/g, '').trim()
                } catch {
                    return utf8Match[1].replace(/["']/g, '').trim()
                }
            }

            const plainMatch = normalizedHeader.match(/filename\s*=\s*"?(?<file>[^";]+)"?/i)

            return plainMatch?.groups?.file?.trim() || ''
        },
        openStudentDialog() {
            this.studentSelectionDraft = {
                studentCode: this.normalizedStudentCode(this.transferredStudentContext?.student?.studentCode),
            }
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
            this.applyTransferredStudentSelection(this.studentSelectionDraft.studentCode)
        },
        clearTransferredStudentSelection() {
            if (!this.transferredStudentContext) return

            const defaultSelection = this.defaultSelection()

            this.studentSelectionDraft = {
                studentCode: null,
            }
            this.selection = defaultSelection
            this.selectionDraft = { ...defaultSelection }
            this.applyTransferredStudentSelection(null)
        },
        applyTransferredStudentSelection(studentCode) {
            const nextStudentCode = this.normalizedStudentCode(studentCode)
            const selectedStudent = nextStudentCode
                ? this.robotStudents.find(student => String(student.student_code) === nextStudentCode)
                : null

            this.runTimetableUpdate(() => {
                if (!selectedStudent) {
                    this.transferredStudentContext = null
                    this.transferredStudentContextExpanded = true
                    this.studentDialogOpen = false
                    this.studentSearch = ''
                    this.persistTimetableState()

                    return
                }

                const semester = this.studentSemester(selectedStudent)

                if (semester) {
                    this.selection = this.normalizedSelection({
                        ...this.selection,
                        semester,
                    })
                    this.selectionDraft = { ...this.selection }
                }

                this.transferredStudentContext = this.normalizedTransferredStudentContext({
                    student: {
                        studentCode: nextStudentCode,
                        label: this.studentOptionTitle(selectedStudent),
                        semesterLabel: this.studentSemesterLabel(selectedStudent),
                    },
                    courses: {
                        completed: [],
                        missing: [],
                        planned: [],
                        additional: [],
                    },
                })
                this.transferredStudentContextExpanded = false
                this.studentDialogOpen = false
                this.studentSearch = ''
                this.persistTimetableState()
                this.loadTransferredStudentCompletedCourses(nextStudentCode, { applySelectionDefaults: true })
            })
        },
        openSelectionDialog() {
            this.selectionDraft = { ...this.selection }
            this.selectionDialogOpen = true
        },
        closeSelectionDialog() {
            this.selectionDialogOpen = false
        },
        updateSelection() {
            this.runTimetableUpdate(() => {
                this.selection = this.normalizedSelection(this.selectionDraft)
                this.refreshTransferredStudentCourseHistory()

                if (!this.transferredStudentContext) {
                    this.persistTimetableState()
                }
            })
            this.selectionDialogOpen = false
        },
        toggleTransferredStudentContext() {
            this.transferredStudentContextExpanded = !this.transferredStudentContextExpanded
            this.persistTimetableState()
        },
        deselectAllCourses() {
            this.runTimetableUpdate(() => {
                this.activeCourseGroupFilterKeys = []
                this.persistTimetableState()
            })
        },
        resetSavedTimetable() {
            this.runTimetableUpdate(() => {
                this.resetTimetablePanels()
                this.removeSavedTimetableState()
                this.removeSavedRobotTimetableState()
                this.applyTimetableState(this.defaultTimetableState())
            })
        },
        resetTimetablePanels() {
            const wizardCourseCards = this.$refs.wizardCourseCards

            wizardCourseCards?.resetCourseSelection?.()
            wizardCourseCards?.resetAdditionalCourseSelection?.()
            wizardCourseCards?.clearGeneratedTimetables?.()
            this.wizardPanelOpen = false
            this.wizardPanelMounted = false
            this.manualPanelOpen = false
            this.manualPanelSource = null
            this.infoDialogOpen = false
            this.settingsDialogOpen = false
            this.courseMenuDialog = false
            this.courseGroupDialog = false
            this.studentDialogOpen = false
            this.selectionDialogOpen = false
            this.selectedCourseMenuKey = ''
            this.selectedCourseGroup = null
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
                manualPanelOpen: false,
                manualPanelSource: null,
                restrictCourseChoiceBySelection: false,
                courseChoiceRestrictionMode: COURSE_CHOICE_RESTRICTION_ALL,
                selection: this.defaultSelection(),
                transferredStudentContext: null,
                transferredStudentContextExpanded: true,
            }
        },
        defaultSelection() {
            return {
                semester: 1,
                religion: 'ETH',
                language: 'L',
                branch: 'wirtschaftskundlich',
                artsSubject: 'ME',
            }
        },
        normalizedCourseChoiceRestrictionMode(value, options = {}) {
            const normalizedMode = String(value || '').trim()
            if (COURSE_CHOICE_RESTRICTION_MODES.includes(normalizedMode)) return normalizedMode

            return options?.restrictCourseChoiceBySelection === true
                ? COURSE_CHOICE_RESTRICTION_PLANNED
                : COURSE_CHOICE_RESTRICTION_ALL
        },
        buildCourseChoiceRestrictionContext(mode = null) {
            const courseRestrictionMode = this.normalizedCourseChoiceRestrictionMode(mode || this.courseChoiceRestrictionMode, {
                restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
            })
            const courseChoiceRestrictionCourses = Array.isArray(this.courseChoiceRestrictionCourses)
                ? this.courseChoiceRestrictionCourses
                : this.courseChoiceRestrictionItemsFromCourses(
                    this.transferredStudentContext?.courses || this.noStudentCourseHistory,
                    courseRestrictionMode,
                )
            const studentCourseCodes = this.restrictedStudentCourseCodes instanceof Set
                ? this.restrictedStudentCourseCodes
                : new Set()

            return {
                mode: courseRestrictionMode,
                courseChoiceRestrictionCourses,
                hasCourseChoiceRestrictions: courseChoiceRestrictionCourses.length > 0,
                studentCourseCodes,
                shouldRestrictByTimetableSemester: this.shouldRestrictCourseChoiceByTimetableSemester(),
                religionCourseBases: this.religionCourseBases(),
                languageCourseBases: this.languageCourseBases(),
                artsCourseBases: this.artsCourseBases(),
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
                manualPanelOpen: Boolean(this.manualPanelOpen),
                manualPanelSource: this.manualPanelOpen ? (this.manualPanelSource || 'direct') : null,
                restrictCourseChoiceBySelection: Boolean(this.restrictCourseChoiceBySelection),
                courseChoiceRestrictionMode: this.normalizedCourseChoiceRestrictionMode(this.courseChoiceRestrictionMode, {
                    restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
                }),
                selection: this.normalizedSelection(this.selection || defaults.selection),
                transferredStudentContext: this.normalizedTransferredStudentContext(this.transferredStudentContext),
                transferredStudentContextExpanded: Boolean(this.transferredStudentContextExpanded),
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
            this.manualPanelOpen = Boolean(state?.manualPanelOpen ?? defaults.manualPanelOpen)
            this.manualPanelSource = this.manualPanelOpen
                ? (state?.manualPanelSource || defaults.manualPanelSource || 'direct')
                : null
            if (this.manualPanelOpen) {
                this.wizardPanelOpen = false
            }
            this.restrictCourseChoiceBySelection = Boolean(
                state?.restrictCourseChoiceBySelection ?? defaults.restrictCourseChoiceBySelection,
            )
            this.courseChoiceRestrictionMode = this.normalizedCourseChoiceRestrictionMode(state?.courseChoiceRestrictionMode, {
                restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
            })
            this.restrictCourseChoiceBySelection = this.courseChoiceRestrictionMode !== COURSE_CHOICE_RESTRICTION_ALL
            this.selection = this.normalizedSelection(state?.selection || defaults.selection)
            this.selectionDraft = { ...this.selection }
            this.transferredStudentContext = this.normalizedTransferredStudentContext(
                state?.transferredStudentContext ?? defaults.transferredStudentContext,
            )
            const transferredStudentContextExpanded = Boolean(
                state?.transferredStudentContextExpanded ?? defaults.transferredStudentContextExpanded,
            )
            this.transferredStudentContextExpanded = this.transferredStudentContext
                ? transferredStudentContextExpanded
                : true
        },
        async loadTransferredStudentCompletedCourses(studentCode, options = {}) {
            const normalizedStudentCode = this.normalizedStudentCode(studentCode)
            const requestId = this.studentCompletedCoursesRequestId + 1
            this.studentCompletedCoursesRequestId = requestId

            if (!normalizedStudentCode) return

            try {
                const response = await axios.get('/api/admin/students-timetables/robot/student-completed-courses', {
                    params: {
                        student_code: normalizedStudentCode,
                    },
                })

                if (requestId !== this.studentCompletedCoursesRequestId) return
                if (this.normalizedStudentCode(this.transferredStudentContext?.student?.studentCode) !== normalizedStudentCode) return

                const completedCourses = response.data?.data || []

                if (options?.applySelectionDefaults) {
                    this.applyTransferredStudentSelectionDefaultsFromCourses(completedCourses)
                }

                this.transferredStudentContext = this.normalizedTransferredStudentContext({
                    ...this.transferredStudentContext,
                    courses: this.overviewStudentCourseHistory(completedCourses),
                })
                this.persistTimetableState()
            } catch {
                if (requestId !== this.studentCompletedCoursesRequestId) return
            }
        },
        normalizedSelection(selection) {
            const defaults = this.defaultSelection()
            const semester = Number(selection?.semester ?? defaults.semester)

            return {
                semester: Number.isFinite(semester) ? semester : defaults.semester,
                religion: selection?.religion || defaults.religion,
                language: selection?.language || defaults.language,
                branch: selection?.branch || defaults.branch,
                artsSubject: selection?.artsSubject || defaults.artsSubject,
            }
        },
        selectedOptionTitle(options, value) {
            return options.find(option => option.value === value)?.title || String(value || '')
        },
        applyTransferredStudentSelectionDefaultsFromCourses(completedCourses) {
            const selectionDefaults = this.selectedStudentCourseHistoryDefaults(completedCourses)
            if (!Object.keys(selectionDefaults).length) return

            const nextSelection = this.normalizedSelection({
                ...this.selection,
                ...selectionDefaults,
            })

            if (JSON.stringify(nextSelection) === JSON.stringify(this.selection)) return

            this.selection = nextSelection
            this.selectionDraft = { ...nextSelection }
            this.selectedCourseMenuKey = ''
            this.selectedCourseGroup = null
        },
        selectedStudentCourseHistoryDefaults(completedCourses) {
            const visitedCourseCodes = this.studentVisitedCourseCodes(completedCourses)

            return Object.fromEntries([
                ['religion', this.inferredSelectionOptionFromCourseCodes(this.religionOptions, visitedCourseCodes)],
                ['language', this.inferredSelectionOptionFromCourseCodes(this.languageOptions, visitedCourseCodes)],
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

            return this.uniqueValues([
                normalizedValue,
                ...this.selectionCourseAliases(normalizedValue),
                ...(aliases[normalizedValue] || []),
            ]
                .map(alias => this.normalizedCourseCode(alias))
                .filter(Boolean))
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
        normalizedStudentCode(value) {
            const studentCode = value === null || value === undefined ? '' : String(value).trim()

            return studentCode === '' ? null : studentCode
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
        formatNumber(value) {
            return new Intl.NumberFormat('de-AT').format(Number(value || 0))
        },
        overviewCompletedCourseItems(courses) {
            return (Array.isArray(courses) ? courses : [])
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
        overviewStudentCourseHistory(completedCourses) {
            const completedCourseItems = Array.isArray(completedCourses) ? completedCourses : []
            const semester = Number(this.selection?.semester || 0)
            const completedCourseCodes = this.studentCompletedCourseCodes(completedCourseItems)
            const visitedCourseCodes = this.studentVisitedCourseCodes(completedCourseItems)
            const missingCourses = semester
                ? this.pendingStudentCoursesBeforeSemester(semester, completedCourseCodes, visitedCourseCodes)
                : []
            const plannedCourses = semester
                ? this.studentPlannedCoursesForSemester(semester, completedCourseCodes)
                : []
            const plannedCourseCodes = this.studentPlannedCourseCodes(plannedCourses)
            const unavailableAdditionalCourseCodes = this.studentUnavailableAdditionalCourseCodes(completedCourseCodes, [
                ...missingCourses,
                ...plannedCourses,
            ])
            const additionalCourses = semester
                ? this.coursesAfterSemester(semester)
                    .filter(course => !this.courseCompletedForStudentPlanning(course, unavailableAdditionalCourseCodes))
                    .filter(course => this.coursePossibleAsStudentAdditional(course, completedCourseCodes, visitedCourseCodes, plannedCourseCodes))
                : []

            return {
                completed: this.overviewCompletedCourseItems(completedCourseItems),
                missing: this.overviewCourseItems(missingCourses),
                planned: this.overviewCourseItems(plannedCourses),
                additional: this.overviewCourseItems(additionalCourses),
            }
        },
        refreshTransferredStudentCourseHistory() {
            if (!this.transferredStudentContext) return

            const completedCourses = (this.transferredStudentContext.courses?.completed || [])
                .map(course => ({
                    subject: course?.code || course?.label,
                    code: course?.code || course?.label,
                    grade: course?.grade || course?.meta,
                }))

            this.transferredStudentContext = this.normalizedTransferredStudentContext({
                ...this.transferredStudentContext,
                courses: this.overviewStudentCourseHistory(completedCourses),
            })
            this.persistTimetableState()
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
        coursesForSemester(semester) {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter(subject => subject?.is_active !== false)
                .filter(subject => Number(subject?.semester) === Number(semester))
                .filter(subject => this.subjectMatchesSelectedBranch(subject))
                .filter(subject => this.subjectMatchesSelectedChoices(subject))
                .flatMap(subject => this.selectedCoursesFromSubject(subject))
                .sort((firstCourse, secondCourse) => this.compareCourses(firstCourse, secondCourse))
        },
        coursesAfterSemester(semester) {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .map(subject => Number(subject?.semester))
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
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .map(subject => Number(subject?.semester))
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
        studentCompletedCourseCodes(completedCourses) {
            return new Set((Array.isArray(completedCourses) ? completedCourses : [])
                .filter(course => this.completedCourseCountsAsDone(course?.grade))
                .flatMap(course => this.courseCodeAliasParts(course?.subject || course?.code))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
        },
        studentVisitedCourseCodes(completedCourses) {
            return new Set((Array.isArray(completedCourses) ? completedCourses : [])
                .flatMap(course => this.courseCodeAliasParts(course?.subject || course?.code))
                .map(courseCode => this.normalizedCourseCode(courseCode))
                .filter(Boolean))
        },
        studentUnavailableAdditionalCourseCodes(completedCourseCodes, plannedCourses) {
            const unavailableCourseCodes = new Set(completedCourseCodes)

            ;(Array.isArray(plannedCourses) ? plannedCourses : [])
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
            if (!(completedCourseCodes instanceof Set) || !completedCourseCodes.size) return false

            if (this.courseCodeAliases(course)
                .some(courseCode => completedCourseCodes.has(courseCode))
            ) return true

            return this.courseModulePartsForStudentPlanning(course)
                .some(parts => this.studentCourseCodesContainEquivalentModule(completedCourseCodes, parts))
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

            if (!subjectRows.length) return true

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
            const mappedAliases = {
                ET: ['ETH', 'R', 'RK'],
                ETH: ['ET', 'R', 'RK'],
                GPB: ['GS'],
                GS: ['GPB'],
                GW: ['GWB'],
                GWB: ['GW'],
                LPT: ['LET'],
                LET: ['LPT'],
                ME: ['MU'],
                MU: ['ME'],
                R: ['RK', 'ET', 'ETH'],
                RK: ['R', 'ET', 'ETH'],
                S: ['SPA'],
                SPA: ['S'],
            }

            return this.uniqueValues([
                normalizedBase,
                ...(mappedAliases[normalizedBase] || []),
            ])
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

            const jsonCodeParts = this.courseCodeAliasParts(this.courseCodeWithoutModule(subject.json_code))
                .map(value => this.normalizedCourseCode(value))

            return jsonCodeParts.length === 1 && ['L', 'F', 'S'].includes(jsonCodeParts[0])
                ? jsonCodeParts[0]
                : ''
        },
        selectedCoursesFromSubject(subject) {
            return this.subjectCourseVariants(subject)
                .map(courseSubject => this.selectedCourseFromSubject(courseSubject))
        },
        subjectCourseVariants(subject) {
            if (this.isReligionSubject(subject) || this.isLanguageSubject(subject)) return [subject]

            const courseCodes = this.courseCodeAliasParts(subject.json_code)
            if (courseCodes.length <= 1) return [subject]

            const splitHours = Number(subject.hours_per_week || 0) / courseCodes.length

            return courseCodes.map(courseCode => ({
                ...subject,
                json_code: courseCode,
                hours_per_week: Number.isFinite(splitHours) ? splitHours : subject.hours_per_week,
            }))
        },
        selectedCourseFromSubject(subject) {
            const selectedCourseCode = this.selectedCourseCode(subject)

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
                name: this.selectedCourseName(subject),
                branch: this.selectedCourseBranch(subject),
                hours: Number(subject.hours_per_week || 0),
            }
        },
        selectedCourseCode(subject) {
            if (this.isReligionSubject(subject)) return `${this.selection.religion}${this.subjectModuleNumber(subject)}`
            if (this.isLanguageSubject(subject)) return `${this.selection.language}${this.subjectModuleNumber(subject)}`

            return this.alternativeDisplay(subject.json_code || subject.json_subject || subject.name)
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
        selectedOptionDescription(options, value) {
            return String(this.selectedOptionTitle(options, value)).split(' - ').pop()
        },
        selectedCourseBranch(subject) {
            if (!subject.branch || subject.branch === 'common') return 'alle'

            return this.selectedOptionTitle(this.branchOptions, subject.branch)
        },
        subjectBaseKey(subject) {
            const jsonSubject = String(subject?.json_subject || '').trim()

            return jsonSubject || String(subject?.json_code || '').replace(/\d+$/u, '')
        },
        subjectModuleNumber(subject) {
            const moduleMatch = String(subject?.json_code || '').match(/(\d+)$/u)

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
            return String(firstCourse?.code || '').localeCompare(String(secondCourse?.code || ''), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        formatHours(value) {
            const hours = Number(value || 0)

            if (Number.isInteger(hours)) return String(hours)

            return String(hours).replace('.', ',')
        },
        normalizedTransferredStudentContext(context) {
            if (!context?.student) return null

            return {
                student: {
                    studentCode: String(context.student?.studentCode || '').trim(),
                    label: String(context.student?.label || '').trim(),
                    semesterLabel: String(context.student?.semesterLabel || '').trim(),
                },
                courses: {
                    completed: this.normalizedTransferredStudentCourses(context.courses?.completed),
                    missing: this.normalizedTransferredStudentCourses(context.courses?.missing),
                    planned: this.normalizedTransferredStudentCourses(context.courses?.planned),
                    additional: this.normalizedTransferredStudentCourses(context.courses?.additional),
                },
            }
        },
        normalizedTransferredStudentCourses(courses) {
            return (Array.isArray(courses) ? courses : [])
                .map((course, index) => {
                    const label = String(course?.label || course?.code || course?.subject || course?.name || '').trim()

                    return {
                        key: String(course?.key || `${label || 'course'}-${index}`).trim(),
                        code: String(course?.code || course?.subject || '').trim(),
                        name: String(course?.name || '').trim(),
                        label,
                        meta: String(course?.meta || course?.grade || '').trim(),
                    }
                })
                .filter(course => course.label)
        },
        timetableStorageKey() {
            return `${TIMETABLE_STORAGE_KEY_PREFIX}:${this.selectedSchoolyear?.id || 'default'}`
        },
        robotTimetableStorageKey(schoolyearId = this.selectedSchoolyear?.id || 'default') {
            return `${ROBOT_TIMETABLE_STORAGE_KEY_PREFIX}:${schoolyearId || 'default'}`
        },
        robotTimetableStorageKeys() {
            return [
                this.robotTimetableStorageKey(),
                this.robotTimetableStorageKey('default'),
            ].filter((key, index, keys) => keys.indexOf(key) === index)
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
        removeSavedRobotTimetableState() {
            try {
                const storage = this.timetableStorage()

                this.robotTimetableStorageKeys().forEach(key => {
                    storage?.removeItem(key)
                })
            } catch {
                // Ignore unavailable browser storage.
            }
        },
        savedRobotTimetableStateAvailable() {
            try {
                const storage = this.timetableStorage()

                return this.robotTimetableStorageKeys().some(key => Boolean(storage?.getItem(key)))
            } catch {
                return false
            }
        },
        persistTimetableState() {
            this.saveLastTimetableState()
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
        displayCourseGroupsForCell(semester, weekday, hour, recurrenceWeek = null) {
            const directCourseGroups = this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)
            if (!directCourseGroups.length) return []

            const displayDirectCourseGroups = directCourseGroups.some(courseGroup => !this.courseGroupIsSingleDate(courseGroup))
                ? directCourseGroups.filter(courseGroup => !this.courseGroupIsSingleDate(courseGroup))
                : directCourseGroups
            const directCourseGroupKeys = directCourseGroups
                .map(courseGroup => courseGroup?.key)
                .filter(Boolean)
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])
            const overlappingCourseGroups = this.configuredCourseGroups
                .filter(courseGroup => activeCourseGroupFilterKeySet.has(courseGroup?.key))
                .filter(courseGroup => !directCourseGroupKeys.includes(courseGroup?.key))
                .filter(courseGroup => this.courseGroupMatchesSelectedRecurrenceWeek(courseGroup, semester, recurrenceWeek))
                .filter(courseGroup => directCourseGroups.some(directCourseGroup =>
                    this.courseGroupsOverlap(directCourseGroup, courseGroup)
                        && !this.courseGroupOverlapIsSingleDateOnly(directCourseGroup, courseGroup),
                ))

            return this.uniqueDisplayCourseGroups([
                ...displayDirectCourseGroups,
                ...overlappingCourseGroups,
            ]).sort((left, right) => this.courseGroupSortLabel(left).localeCompare(
                this.courseGroupSortLabel(right),
                'de',
                { sensitivity: 'base' },
            ))
        },
        courseGroupSingleDateOverlapMarkersForCell(semester, weekday, hour, recurrenceWeek = null) {
            const directCourseGroups = this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)
            const regularCourseGroups = directCourseGroups.filter(courseGroup => !this.courseGroupIsSingleDate(courseGroup))
            if (!regularCourseGroups.length) return []

            const directCourseGroupKeys = directCourseGroups
                .map(courseGroup => courseGroup?.key)
                .filter(Boolean)
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])
            const directSingleDateCourseGroups = directCourseGroups
                .filter(courseGroup => this.courseGroupIsSingleDate(courseGroup))
                .filter(courseGroup => regularCourseGroups.some(regularCourseGroup =>
                    this.courseGroupsOverlap(regularCourseGroup, courseGroup),
                ))
            const overlappingSingleDateCourseGroups = this.configuredCourseGroups
                .filter(courseGroup => activeCourseGroupFilterKeySet.has(courseGroup?.key))
                .filter(courseGroup => !directCourseGroupKeys.includes(courseGroup?.key))
                .filter(courseGroup => this.courseGroupIsSingleDate(courseGroup))
                .filter(courseGroup => this.courseGroupMatchesSelectedRecurrenceWeek(courseGroup, semester, recurrenceWeek))
                .filter(courseGroup => regularCourseGroups.some(regularCourseGroup =>
                    this.courseGroupsOverlap(regularCourseGroup, courseGroup),
                ))

            return this.uniqueCourseGroupsByKey([
                ...directSingleDateCourseGroups,
                ...overlappingSingleDateCourseGroups,
            ])
                .sort((left, right) => this.courseGroupSortLabel(left).localeCompare(
                    this.courseGroupSortLabel(right),
                    'de',
                    { sensitivity: 'base' },
                ))
                .map(courseGroup => ({
                    key: courseGroup?.key,
                    label: this.courseGroupSingleDateMarkerLabel(courseGroup),
                    title: this.courseGroupSingleDateMarkerTitle(courseGroup),
                    courseGroup,
                }))
        },
        courseGroupSingleDateMarkerLabel(courseGroup) {
            return [
                courseGroup?.course,
                courseGroup?.display_label,
                courseGroup?.title,
            ]
                .map(value => String(value || '').trim())
                .find(Boolean) || 'Einzeltermin'
        },
        courseGroupSingleDateMarkerTitle(courseGroup) {
            return [
                courseGroup?.display_label,
                courseGroup?.title,
                courseGroup?.course,
            ]
                .map(value => String(value || '').trim())
                .find(Boolean) || 'Einzeltermin'
        },
        sameSlotDateOverviewGroupsForSemester(semester) {
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])

            const courseGroupsBySlot = (Array.isArray(this.configuredCourseGroups) ? this.configuredCourseGroups : [])
                .filter(courseGroup => activeCourseGroupFilterKeySet.has(courseGroup?.key))
                .filter(courseGroup => Number(courseGroup?.semester) === Number(semester))
                .filter(courseGroup => !this.courseGroupIsSingleDate(courseGroup))
                .filter(courseGroup => this.courseGroupDates(courseGroup).length > 0)
                .reduce((groups, courseGroup) => {
                    const key = this.courseCellKey(semester, courseGroup?.weekday, courseGroup?.hour)
                    groups[key] ??= []
                    groups[key].push(courseGroup)

                    return groups
                }, {})

            const groups = Object.entries(courseGroupsBySlot)
                .map(([key, courseGroups]) => this.sameSlotDateOverviewGroup(key, courseGroups))
                .filter(Boolean)
                .sort((left, right) => left.sortValue.localeCompare(right.sortValue, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))

            return this.compactSameSlotDateOverviewGroups(groups)
        },
        sameSlotDateOverviewGroup(key, courseGroups) {
            const uniqueCourseGroups = this.uniqueCourseGroupsByKey(courseGroups)

            if (uniqueCourseGroups.length < 2) return null

            const firstCourseGroup = uniqueCourseGroups[0] || {}
            const weekday = Number(firstCourseGroup?.weekday)
            const hour = Number(firstCourseGroup?.hour)
            const timeRange = this.courseGroupTimeRangeParts(firstCourseGroup)
            const courses = uniqueCourseGroups
                .map(courseGroup => this.sameSlotDateOverviewCourse(courseGroup))
                .sort((left, right) => left.title.localeCompare(right.title, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))

            return {
                key,
                title: this.sameSlotDateOverviewSlotTitle(firstCourseGroup),
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
                ].join('::'))
                .sort((left, right) => left.localeCompare(right, 'de-AT', { numeric: true, sensitivity: 'base' }))
                .join('||')
        },
        sameSlotDateOverviewCourse(courseGroup) {
            const dates = this.courseGroupDates(courseGroup)
            const title = this.sameSlotDateOverviewCourseTitle(courseGroup)

            return {
                key: courseGroup?.key || title,
                title,
                dateRangeLabel: this.courseGroupDateRangeLabel(courseGroup),
                dateLabels: dates.map(date => this.formatDateWithWeekdayLabel(date)),
            }
        },
        sameSlotDateOverviewCourseTitle(courseGroup) {
            return [
                courseGroup?.display_label,
                courseGroup?.title,
                courseGroup?.course,
            ]
                .map(value => String(value || '').trim())
                .find(Boolean) || 'Ohne Bezeichnung'
        },
        sameSlotDateOverviewSlotTitle(courseGroup) {
            const timeRange = this.courseGroupTimeRangeParts(courseGroup)

            return this.sameSlotDateOverviewSlotRangeTitle({
                weekday: Number(courseGroup?.weekday),
                startHour: Number(courseGroup?.hour),
                endHour: Number(courseGroup?.hour),
                from: timeRange.from,
                until: timeRange.until,
            })
        },
        sameSlotDateOverviewSlotRangeTitle(group) {
            const weekday = this.weekdays.find(weekdayItem => Number(weekdayItem.value) === Number(group?.weekday))?.label || ''
            const startHour = Number(group?.startHour)
            const endHour = Number(group?.endHour)
            const hourLabel = Number.isFinite(startHour) && Number.isFinite(endHour)
                ? (startHour === endHour ? `${startHour}.` : `${startHour}.-${endHour}.`)
                : ''
            const timeRange = group?.from && group?.until ? `${group.from} - ${group.until}` : ''

            return [weekday, hourLabel, timeRange].filter(Boolean).join(' ')
        },
        uniqueCourseGroupsByKey(courseGroups) {
            return Object.values((Array.isArray(courseGroups) ? courseGroups : []).reduce((groups, courseGroup) => {
                const key = String(courseGroup?.key || '').trim()
                if (!key) return groups

                groups[key] ??= courseGroup

                return groups
            }, {}))
        },
        uniqueDisplayCourseGroups(courseGroups) {
            return Object.values((Array.isArray(courseGroups) ? courseGroups : []).reduce((groups, courseGroup) => {
                const key = this.courseGroupDisplayIdentityKey(courseGroup)
                if (!key) return groups

                groups[key] ??= courseGroup

                return groups
            }, {}))
        },
        courseGroupDisplayIdentityKey(courseGroup) {
            return [
                courseGroup?.semester,
                courseGroup?.weekday,
                courseGroup?.course,
                courseGroup?.display_label,
                courseGroup?.title,
                courseGroup?.recurrence_label,
                courseGroup?.recurrence_interval,
            ]
                .map(value => String(value || '').trim())
                .join('|')
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
        openCourseMenuDialog() {
            this.selectedCourseMenuKey = ''
            this.courseMenuDialog = true
        },
        selectCourseMenu(courseMenu) {
            this.selectedCourseMenuKey = courseMenu?.key || ''
        },
        courseMenuHasActiveSelection(courseMenu) {
            return (courseMenu?.entries || []).some((entry) => this.isCourseMenuEntryFilterActive(entry))
        },
        buildSemesterCourseMenus(semester, sourceCourseGroups = null) {
            const courseGroups = Array.isArray(sourceCourseGroups)
                ? sourceCourseGroups
                : Array.isArray(this.courseChoiceCourseGroups)
                    ? this.courseChoiceCourseGroups
                    : Array.isArray(this.configuredCourseGroups)
                        ? this.configuredCourseGroups
                        : []
            const courseMenusByLabel = courseGroups
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
        courseGroupMatchesCourseChoiceRestriction(courseGroup, context = null) {
            const courseRestrictionContext = context || this.courseChoiceRestrictionContext || {}
            const courseGroupCodes = this.courseGroupCodes(courseGroup)
            const studentCourseCodes = courseRestrictionContext.studentCourseCodes instanceof Set
                ? courseRestrictionContext.studentCourseCodes
                : this.restrictedStudentCourseCodes instanceof Set
                    ? this.restrictedStudentCourseCodes
                    : new Set()
            const courseChoiceRestrictionCourses = Array.isArray(courseRestrictionContext.courseChoiceRestrictionCourses)
                ? courseRestrictionContext.courseChoiceRestrictionCourses
                : Array.isArray(this.courseChoiceRestrictionCourses)
                    ? this.courseChoiceRestrictionCourses
                    : []
            const hasCourseChoiceRestrictions = Boolean(
                courseRestrictionContext.hasCourseChoiceRestrictions ?? courseChoiceRestrictionCourses.length > 0,
            )
            const shouldRestrictByTimetableSemester = Boolean(
                courseRestrictionContext.shouldRestrictByTimetableSemester ?? this.shouldRestrictCourseChoiceByTimetableSemester(),
            )

            if (hasCourseChoiceRestrictions && studentCourseCodes.size) {
                return this.courseGroupMatchesRestrictedCourseCodes(courseGroupCodes, studentCourseCodes, courseGroup, {
                    shouldRestrictByTimetableSemester,
                })
            }

            if (
                shouldRestrictByTimetableSemester
                && !this.courseGroupMatchesSelectedTimetableSemester(courseGroup)
            ) return false
            if (!this.courseGroupMatchesSelectedChoiceOptions(courseGroup, courseRestrictionContext)) return false

            if (!studentCourseCodes.size) return true

            return this.courseGroupMatchesRestrictedCourseCodes(courseGroupCodes, studentCourseCodes)
        },
        courseChoiceRestrictionItemsFromCourses(courses = {}, mode = COURSE_CHOICE_RESTRICTION_PLANNED) {
            if (mode === COURSE_CHOICE_RESTRICTION_ALL) return []

            const plannedCourses = Array.isArray(courses?.planned) ? courses.planned : []
            if (mode === COURSE_CHOICE_RESTRICTION_PLANNED) return plannedCourses

            return [
                ...plannedCourses,
                ...(Array.isArray(courses?.additional) ? courses.additional : []),
            ]
        },
        transferredStudentCourseRestrictionItemsFromCourses(courses = {}) {
            return [
                ...(Array.isArray(courses?.missing) ? courses.missing : []),
                ...this.courseChoiceRestrictionItemsFromCourses(courses, COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL),
            ]
        },
        courseGroupMatchesRestrictedCourseCodes(
            courseGroupCodes,
            restrictedCourseCodes,
            courseGroup = null,
            options = {},
        ) {
            if (!(restrictedCourseCodes instanceof Set) || !restrictedCourseCodes.size) return false

            const shouldRestrictByTimetableSemester = Boolean(
                options?.shouldRestrictByTimetableSemester ?? this.shouldRestrictCourseChoiceByTimetableSemester(),
            )
            const normalizedCourseGroupCodes = this.uniqueValues(courseGroupCodes)
            if (normalizedCourseGroupCodes.some(courseCode => (
                restrictedCourseCodes.has(courseCode)
                && this.courseGroupCodeMatchesTimetableSemester(courseCode, courseGroup, {
                    shouldRestrictByTimetableSemester,
                })
            ))) return true

            return normalizedCourseGroupCodes.some(courseGroupCode => {
                const courseGroupParts = this.courseCodeModuleParts(courseGroupCode)
                if (courseGroupParts.module) return false

                const courseGroupBaseAliases = this.courseBaseAliasesForCourseChoiceRestriction(courseGroupParts.base)
                if (this.courseGroupHasDifferentModuleForRestrictedBase(
                    normalizedCourseGroupCodes,
                    courseGroupBaseAliases,
                    restrictedCourseCodes,
                )) return false

                return [...restrictedCourseCodes].some(restrictedCourseCode => {
                    const restrictedCourseParts = this.courseCodeModuleParts(restrictedCourseCode)
                    if (!restrictedCourseParts.module) return false

                    const restrictedTimetableSemester = this.courseCodeTimetableSemester(restrictedCourseParts.module)
                    if (
                        shouldRestrictByTimetableSemester
                        && restrictedTimetableSemester
                        && !this.courseGroupMatchesTimetableSemester(courseGroup, restrictedTimetableSemester)
                    ) return false

                    return this.courseBaseAliasesForCourseChoiceRestriction(restrictedCourseParts.base)
                        .some(baseAlias => courseGroupBaseAliases.includes(baseAlias))
                })
            })
        },
        courseGroupHasDifferentModuleForRestrictedBase(courseGroupCodes, courseGroupBaseAliases, restrictedCourseCodes) {
            const restrictedModules = [...restrictedCourseCodes]
                .map(courseCode => this.courseCodeModuleParts(courseCode))
                .filter(parts => parts.module)
                .filter(parts => this.courseBaseAliasesForCourseChoiceRestriction(parts.base)
                    .some(baseAlias => courseGroupBaseAliases.includes(baseAlias)))

            if (!restrictedModules.length) return false

            return courseGroupCodes
                .map(courseCode => this.courseCodeModuleParts(courseCode))
                .filter(parts => parts.module)
                .some(parts => (
                    this.courseBaseAliasesForCourseChoiceRestriction(parts.base)
                        .some(baseAlias => courseGroupBaseAliases.includes(baseAlias))
                    && !restrictedModules.some(restrictedParts => restrictedParts.module === parts.module)
                ))
        },
        courseBaseAliasesForCourseChoiceRestriction(base) {
            const normalizedBase = this.courseCodeWithoutModule(base)

            return this.uniqueValues([
                normalizedBase,
                this.courseCodeWithoutModule(this.defaultTimetableCodeAlias(normalizedBase)),
            ].filter(Boolean))
        },
        courseGroupCodeMatchesTimetableSemester(courseCode, courseGroup, options = {}) {
            const shouldRestrictByTimetableSemester = Boolean(
                options?.shouldRestrictByTimetableSemester ?? this.shouldRestrictCourseChoiceByTimetableSemester(),
            )
            if (!shouldRestrictByTimetableSemester) return true

            const courseGroupParts = this.courseCodeModuleParts(courseCode)
            const timetableSemester = this.courseCodeTimetableSemester(courseGroupParts.module)
            if (!timetableSemester) return true

            return this.courseGroupMatchesTimetableSemester(courseGroup, timetableSemester)
        },
        courseGroupMatchesTimetableSemester(courseGroup, timetableSemester) {
            if (!courseGroup) return true

            return Number(courseGroup?.semester) === Number(timetableSemester)
        },
        courseCodeTimetableSemester(module) {
            const moduleNumber = Number(module)
            if (!Number.isInteger(moduleNumber) || moduleNumber <= 0) return null

            return moduleNumber % 2 === 0 ? 2 : 1
        },
        shouldRestrictCourseChoiceByTimetableSemester() {
            const selectedTimetableSemester = this.selectedTimetableSemesterForSelection()
            if (!selectedTimetableSemester) return false

            return (Array.isArray(this.configuredCourseGroups) ? this.configuredCourseGroups : [])
                .some(courseGroup => Number(courseGroup?.semester) === selectedTimetableSemester)
        },
        selectedTimetableSemesterForSelection() {
            const selectedSemester = Number(this.selection?.semester || 0)
            if (!Number.isFinite(selectedSemester) || selectedSemester <= 0) return null

            return selectedSemester % 2 === 0 ? 2 : 1
        },
        courseGroupMatchesSelectedTimetableSemester(courseGroup) {
            const timetableSemester = this.selectedTimetableSemesterForSelection()
            if (!timetableSemester) return true

            return Number(courseGroup?.semester) === timetableSemester
        },
        courseGroupMatchesSelectedChoiceOptions(courseGroup, context = null) {
            const courseBases = this.courseGroupChoiceOptionCodes(courseGroup)
                .map(courseCode => this.courseCodeWithoutModule(courseCode))
                .filter(Boolean)
            const courseRestrictionContext = context || {}

            return this.courseBasesMatchSelectedOption(
                courseBases,
                courseRestrictionContext.religionCourseBases || this.religionCourseBases(),
                this.selection?.religion,
            )
                && this.courseBasesMatchSelectedOption(
                    courseBases,
                    courseRestrictionContext.languageCourseBases || this.languageCourseBases(),
                    this.selection?.language,
                )
                && this.courseBasesMatchSelectedOption(
                    courseBases,
                    courseRestrictionContext.artsCourseBases || this.artsCourseBases(),
                    this.selection?.artsSubject,
                )
        },
        courseBasesMatchSelectedOption(courseBases, optionBases, selectedOption) {
            const matchingOptionBases = courseBases
                .filter(courseBase => optionBases.includes(courseBase))

            if (!matchingOptionBases.length) return true

            const selectedAliases = this.selectionCourseAliases(selectedOption)
                .map(alias => this.courseCodeWithoutModule(alias))

            return matchingOptionBases.some(courseBase => selectedAliases.includes(courseBase))
        },
        religionCourseBases() {
            return this.uniqueValues(this.religionOptions
                .flatMap(option => this.selectionCourseAliases(option.value))
                .map(alias => this.courseCodeWithoutModule(alias)))
        },
        languageCourseBases() {
            return this.uniqueValues([
                ...this.languageOptions.flatMap(option => this.selectionCourseAliases(option.value)),
                'SPA',
                'LET',
                'LPT',
            ].map(alias => this.courseCodeWithoutModule(alias)))
        },
        artsCourseBases() {
            return this.uniqueValues([
                ...this.artsSubjectOptions.flatMap(option => this.selectionCourseAliases(option.value)),
                'MU',
            ].map(alias => this.courseCodeWithoutModule(alias)))
        },
        selectionCourseAliases(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const aliases = {
                ET: ['ETH', 'ET'],
                ETH: ['ETH', 'ET'],
                L: ['L', 'LET', 'LPT'],
                LPT: ['L', 'LET', 'LPT'],
                LET: ['L', 'LET', 'LPT'],
                ME: ['ME', 'MU'],
                MU: ['ME', 'MU'],
                R: ['RK', 'R'],
                RK: ['RK', 'R'],
                S: ['S', 'SPA'],
                SPA: ['S', 'SPA'],
            }

            return this.uniqueValues([
                normalizedValue,
                ...(aliases[normalizedValue] || []),
                this.defaultTimetableCodeAlias(normalizedValue),
            ].filter(Boolean))
        },
        courseGroupChoiceOptionCodes(courseGroup) {
            return this.courseAliasesFromValues([
                courseGroup?.course,
                courseGroup?.module_code,
                courseGroup?.moduleCode,
                this.courseGroupPrimaryLabelSegment(courseGroup?.title),
                this.courseGroupPrimaryLabelSegment(courseGroup?.display_label),
                courseGroup?.subject,
            ])
        },
        courseGroupPrimaryLabelSegment(value) {
            return String(value || '').split(/\s+-\s+/u)[0]?.trim() || ''
        },
        courseGroupCodes(courseGroup) {
            return this.courseAliasesFromValues([
                courseGroup?.course,
                courseGroup?.module_code,
                courseGroup?.moduleCode,
                courseGroup?.title,
                courseGroup?.display_label,
                courseGroup?.subject,
            ])
        },
        courseAliasesFromValues(values) {
            return this.uniqueValues((Array.isArray(values) ? values : [])
                .flatMap(value => this.courseCodeTokensFromValue(value))
                .flatMap(value => [
                    value,
                    this.defaultTimetableCodeAlias(value),
                ])
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean))
        },
        courseCodeAliases(course) {
            return this.uniqueValues([
                course?.code,
                course?.ttCode,
                ...(Array.isArray(course?.ttCodes) ? course.ttCodes : []),
                course?.subject,
            ]
                .flatMap(value => this.courseCodeAliasParts(value))
                .flatMap(value => [
                    value,
                    this.defaultTimetableCodeAlias(value),
                ])
                .map(value => this.normalizedCourseCode(value))
                .filter(Boolean))
        },
        courseCodeAliasParts(value) {
            return String(value || '')
                .split('/')
                .map(part => part.trim())
                .filter(Boolean)
        },
        courseCodeModuleParts(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const moduleMatch = normalizedValue.match(/^([A-ZÄÖÜ]+)(\d+)$/u)

            if (!moduleMatch) {
                return {
                    base: this.courseCodeWithoutModule(normalizedValue),
                    module: '',
                }
            }

            return {
                base: this.courseCodeWithoutModule(moduleMatch[1]),
                module: moduleMatch[2],
            }
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
                LPT: 'LET',
                LET: 'LPT',
                ME: 'MU',
                MU: 'ME',
                R: 'RK',
                RK: 'R',
                S: 'SPA',
                SPA: 'S',
            }
            const mappedBase = aliases[match[1]]

            return mappedBase ? `${mappedBase}${match[2] || ''}` : ''
        },
        courseCodeTokensFromValue(value) {
            return [...String(value || '').matchAll(/[A-Za-zÄÖÜäöüß]+[0-9]*/gu)]
                .map(match => match[0])
        },
        courseCodeWithoutModule(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-ZÄÖÜ]+)\d*$/u)

            return match?.[1] || normalizedValue
        },
        normalizedCourseCode(value) {
            return String(value || '')
                .trim()
                .toLocaleUpperCase('de-AT')
                .replace(/\s+/gu, '')
        },
        uniqueValues(values) {
            return [...new Set((Array.isArray(values) ? values : []).filter(Boolean))]
        },
        semesterCourseChips(semester) {
            return this.semesterCourseMenus(semester).map((courseMenu) => ({
                key: courseMenu.key,
                label: courseMenu.label,
            }))
        },
        selectedCourseFilterChips(semester) {
            return this.selectedCourseMenuEntries(semester)
                .map((entry) => ({
                    key: `selected-${entry.key}`,
                    label: entry.scheduleLabel ? `${entry.label} · ${entry.scheduleLabel}` : entry.label,
                    hasBlockingOverlap: this.courseMenuEntryHasBlockingOverlap(entry, semester),
                    hasRelatedOverlap: this.courseMenuEntryHasRelatedOverlap(entry, semester),
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
            return this.buildSemesterCourseMenus(semester, this.configuredCourseGroups)
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
            const mergeGapMinutes = 15
            const timeLabelToMinutes = (value) => {
                const match = String(value || '').match(/^(\d{1,2}):(\d{2})$/u)
                if (!match) return null

                return Number(match[1]) * 60 + Number(match[2])
            }
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
                        ranges: [],
                        fallbackLabels: new Set(),
                    })
                }

                const schedule = schedules.get(key)
                if (timeRange.from && timeRange.until) {
                    schedule.ranges.push({
                        from: timeRange.from,
                        until: timeRange.until,
                        fromMinutes: timeLabelToMinutes(timeRange.from),
                        untilMinutes: timeLabelToMinutes(timeRange.until),
                    })

                    return schedules
                }

                schedule.fallbackLabels.add([weekday.label, timeRange.label].filter(Boolean).join(' '))

                return schedules
            }, new Map())

            return [...schedulesByWeekday.values()]
                .sort((left, right) => left.weekdayOrder - right.weekdayOrder)
                .flatMap((schedule) => {
                    const mergedRanges = schedule.ranges
                        .sort((left, right) => (left.fromMinutes ?? 0) - (right.fromMinutes ?? 0))
                        .reduce((ranges, range) => {
                            const lastRange = ranges[ranges.length - 1]
                            const gapMinutes = lastRange && lastRange.untilMinutes !== null && range.fromMinutes !== null
                                ? range.fromMinutes - lastRange.untilMinutes
                                : null

                            if (lastRange && gapMinutes !== null && gapMinutes <= mergeGapMinutes) {
                                if ((range.untilMinutes ?? 0) > (lastRange.untilMinutes ?? 0)) {
                                    lastRange.until = range.until
                                    lastRange.untilMinutes = range.untilMinutes
                                }

                                return ranges
                            }

                            ranges.push({ ...range })

                            return ranges
                        }, [])
                        .map((range) => [schedule.weekdayLabel, `${range.from} - ${range.until}`].filter(Boolean).join(' '))

                    return [
                        ...mergedRanges,
                        ...schedule.fallbackLabels,
                    ]
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
                this.allWeeksOption(semester),
            ]
        },
        allDatesOption(semester) {
            return {
                key: `semester-${semester}-all-dates`,
                type: 'all_dates',
                value: ALL_DATES_OPTION_VALUE,
                label: 'Stundenplan',
                showLabel: false,
            }
        },
        allWeeksOption(semester) {
            return {
                key: `semester-${semester}-all-weeks`,
                type: 'all_weeks',
                value: ALL_WEEKS_OPTION_VALUE,
                label: 'Alle Wochen',
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
            if (this.areRecurrenceWeeksExpanded(semester)) {
                return ALL_WEEKS_OPTION_VALUE
            }

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
            const semesterNumber = Number(semester)

            if (value === ALL_WEEKS_OPTION_VALUE) {
                this.expandedRecurrenceWeeks = {
                    ...this.expandedRecurrenceWeeks,
                    [semesterNumber]: true,
                }
                this.saveLastTimetableState?.()

                return
            }

            this.expandedRecurrenceWeeks = {
                ...this.expandedRecurrenceWeeks,
                [semesterNumber]: false,
            }

            if ([ALL_DATES_OPTION_VALUE, 'extra_dates'].includes(value)) {
                this.selectedRecurrenceWeeks = {
                    ...this.selectedRecurrenceWeeks,
                    [semesterNumber]: value,
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
        shouldIncludeExtraDatesInRegularWeek(semester) {
            return this.selectedTimetableOptionValue(semester) !== 'extra_dates'
                && !this.areRecurrenceWeeksExpanded(semester)
                && this.extraDatesOptions(semester).length > 0
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
            return this.courseMenuEntryHasBlockingOverlap(entry, semester)
                || this.courseMenuEntryHasRelatedOverlap(entry, semester)
        },
        courseMenuEntryHasBlockingOverlap(entry, semester) {
            return this.selectedCourseMenuEntries(semester)
                .some((selectedEntry) => (
                    selectedEntry.key !== entry?.key
                    && this.courseMenuEntriesHaveBlockingOverlap(entry, selectedEntry)
                ))
        },
        courseMenuEntryHasRelatedOverlap(entry, semester, options = {}) {
            if (options?.hasBlockingOverlap ?? this.courseMenuEntryHasBlockingOverlap(entry, semester)) {
                return false
            }

            return this.selectedCourseMenuEntries(semester)
                .some((selectedEntry) => (
                    selectedEntry.key !== entry?.key
                    && this.courseMenuEntriesOverlap(entry, selectedEntry)
                ))
        },
        courseGroupHasOverlap(courseGroup) {
            return this.courseGroupHasBlockingOverlap(courseGroup)
        },
        courseGroupHasBlockingOverlap(courseGroup) {
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
                            && this.courseGroupDatesOverlap(courseGroup, selectedCourseGroup)
                            && !this.courseGroupOverlapIsSingleDateOnly(courseGroup, selectedCourseGroup)
                    ))
                ))
        },
        courseGroupHasSingleDateOverlap(courseGroup) {
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
                            && this.courseGroupOverlapIsSingleDateOnly(courseGroup, selectedCourseGroup)
                    ))
                ))
        },
        courseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup) {
            return this.courseGroupIsSingleDate(leftCourseGroup)
                || this.courseGroupIsSingleDate(rightCourseGroup)
        },
        courseGroupIsSingleDate(courseGroup) {
            return ![1, 2, 3, 4].includes(Number(courseGroup?.recurrence_interval))
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
        cellHasOverlap(semester, weekday, hour, recurrenceWeek = null) {
            return this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)
                .some((courseGroup) => this.courseGroupHasOverlap(courseGroup))
        },
        cellHasRelatedOverlap(semester, weekday, hour, recurrenceWeek = null) {
            if (this.cellHasOverlap(semester, weekday, hour, recurrenceWeek)) {
                return false
            }

            return this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)
                .some((courseGroup) => this.courseGroupHasRelatedOverlap(courseGroup))
        },
        courseGroupSingleDateOverlapMarker(courseGroup) {
            return this.courseGroupHasSingleDateOverlap(courseGroup) ? 'Auch Einzeltermine' : ''
        },
        courseMenuEntriesHaveBlockingOverlap(leftEntry, rightEntry) {
            return (leftEntry?.courseGroups || []).some((leftCourseGroup) => (
                (rightEntry?.courseGroups || []).some((rightCourseGroup) => (
                    this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                        && this.courseGroupDatesOverlap(leftCourseGroup, rightCourseGroup)
                        && !this.courseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup)
                ))
            ))
        },
        courseMenuEntriesOverlap(leftEntry, rightEntry) {
            return (leftEntry?.courseGroups || []).some((leftCourseGroup) => (
                (rightEntry?.courseGroups || []).some((rightCourseGroup) => (
                    this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                        && !this.courseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup)
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
        courseGroupDatesOverlap(leftCourseGroup, rightCourseGroup) {
            const leftDates = this.courseGroupDates(leftCourseGroup)
            const rightDates = this.courseGroupDates(rightCourseGroup)

            if (!leftDates.length || !rightDates.length) return true

            return leftDates.some(date => rightDates.includes(date))
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
            const normalizedCourseCode = value => String(value || '')
                .trim()
                .toLocaleUpperCase('de-AT')
                .replace(/\s+/gu, '')
            const defaultTimetableCodeAlias = value => {
                const normalizedValue = normalizedCourseCode(value)
                const match = normalizedValue.match(/^([A-ZÄÖÜ]+)(\d*)$/u)
                if (!match) return ''

                const aliases = {
                    ET: 'ETH',
                    ETH: 'ET',
                    GS: 'GPB',
                    GW: 'GWB',
                    LPT: 'LET',
                    LET: 'LPT',
                    ME: 'MU',
                    MU: 'ME',
                    R: 'RK',
                    RK: 'R',
                    S: 'SPA',
                    SPA: 'S',
                }
                const mappedBase = aliases[match[1]]

                return mappedBase ? `${mappedBase}${match[2] || ''}` : ''
            }
            const course = (courseGroup?.course || '').toString().trim()
            const title = (courseGroup?.title || '').toString().trim()
            const titleMatchesCourseAlias = title
                && course
                && normalizedCourseCode(title) !== normalizedCourseCode(course)
                && (
                    defaultTimetableCodeAlias(title) === normalizedCourseCode(course)
                    || defaultTimetableCodeAlias(course) === normalizedCourseCode(title)
                )

            if (titleMatchesCourseAlias) {
                return title
            }

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
        formatDateWithWeekdayLabel(value) {
            const weekdayLabel = this.weekdayLabelForDate(value)
            const dateLabel = this.formatDateValue(value)

            return [weekdayLabel, dateLabel].filter(Boolean).join(', ')
        },
        weekdayLabelForDate(value) {
            const date = this.normalizeDate(value)
            if (!date) return ''

            const weekday = date.getDay()
            const isoWeekday = weekday === 0 ? 7 : weekday

            return (this.weekdays || [])
                .find(configuredWeekday => Number(configuredWeekday.value) === isoWeekday)
                ?.label || ''
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
    gap: 18px;
}

.course-choice-panel {
    display: grid;
    gap: 10px;
    margin-bottom: 16px;
    padding: 10px 12px;
    border: 1px solid rgba(57, 73, 171, 0.16);
    border-radius: 8px;
    background: #f8fafc;
}

.overview-selection {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.overview-selected-cards {
    display: grid;
    grid-template-columns: repeat(5, minmax(128px, 1fr));
    gap: 8px;
    flex: 1 1 auto;
    min-width: 0;
}

.overview-selected-card {
    min-width: 0;
    padding: 9px 11px;
    border: 1px solid rgba(var(--v-theme-primary), 0.26);
    border-radius: 6px;
    background: #f8fafc;
}

.overview-selected-card__label {
    color: #172554;
    font-size: 0.7rem;
    font-weight: 850;
}

.overview-selected-card__value {
    margin-top: 4px;
    color: #020617;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

.overview-context-card {
    margin-bottom: 14px;
    padding: 10px;
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
    border-radius: 8px;
    background:
        radial-gradient(circle at 10% 18%, rgba(250, 204, 21, 0.26), transparent 18%),
        radial-gradient(circle at 92% 22%, rgba(14, 165, 233, 0.2), transparent 19%),
        linear-gradient(135deg, rgba(236, 72, 153, 0.1), rgba(34, 197, 94, 0.12) 46%, rgba(249, 115, 22, 0.13));
}

.overview-wizard-row {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.overview-wizard-button,
.overview-manual-button {
    flex: 0 0 auto;
    min-height: 48px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 850;
    letter-spacing: 0;
    text-transform: none;
}

.overview-wizard-button {
    animation: wizard-glow 2s ease-in-out infinite;
    box-shadow: 0 0 8px rgba(37, 99, 235, 0.4), 0 0 20px rgba(37, 99, 235, 0.2);
}

.overview-wizard-button:hover {
    animation: none;
    box-shadow: 0 0 12px rgba(37, 99, 235, 0.6), 0 0 28px rgba(37, 99, 235, 0.35);
}

@keyframes wizard-glow {
    0%, 100% {
        box-shadow: 0 0 8px rgba(37, 99, 235, 0.4), 0 0 20px rgba(37, 99, 235, 0.2);
    }
    50% {
        box-shadow: 0 0 16px rgba(37, 99, 235, 0.7), 0 0 36px rgba(37, 99, 235, 0.35);
    }
}

.overview-manual-button__label {
    display: grid;
    gap: 1px;
    line-height: 1.05;
    text-align: left;
}

.overview-active-label {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex: 0 0 auto;
    padding: 8px 18px 8px 14px;
    border-radius: 10px;
    letter-spacing: 0;
}

.overview-active-label--auto {
    background: linear-gradient(135deg, #1d4ed8 0%, #6366f1 100%);
    color: #fff;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35), 0 0 24px rgba(99, 102, 241, 0.15);
}

.overview-active-label--manual {
    background: linear-gradient(135deg, #0d9488 0%, #2563eb 100%);
    color: #fff;
    box-shadow: 0 4px 16px rgba(13, 148, 136, 0.35), 0 0 24px rgba(37, 99, 235, 0.15);
}

.overview-active-label__title {
    font-size: 0.88rem;
    font-weight: 800;
}

.overview-wizard-button__stars {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 2px;
}

.overview-active-label__stars {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: -4px;
}

.overview-star {
    color: rgba(255, 255, 255, 0.9);
    animation: star-twinkle 1.8s ease-in-out infinite;
}

.overview-star--1 { animation-delay: 0s; }
.overview-star--2 { animation-delay: 0.5s; }
.overview-star--3 { animation-delay: 1.1s; }

@keyframes star-twinkle {
    0%, 100% { opacity: 0.35; transform: scale(0.8); }
    50% { opacity: 1; transform: scale(1.2); }
}

.overview-wizard-close-button,
.overview-wizard-create-button {
    flex: 0 0 auto;
    min-height: 48px;
    border-radius: 8px;
    font-weight: 850;
    letter-spacing: 0;
    text-transform: none;
}

.overview-wizard-cancel-button {
    margin-left: auto;
}

.overview-wizard-settings-summary {
    display: grid;
    gap: 5px;
    flex: 0 1 720px;
    justify-items: end;
    margin-left: auto;
    min-width: 0;
    max-width: 720px;
    padding: 8px 10px;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.72);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.68);
    text-align: right;
}

.overview-wizard-settings-summary__title,
.overview-wizard-settings-summary__list,
.overview-wizard-actions {
    display: flex;
    align-items: center;
}

.overview-wizard-settings-summary__title {
    gap: 6px;
    color: #172554;
    font-size: 0.72rem;
    font-weight: 850;
}

.overview-wizard-settings-summary__list {
    gap: 6px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.overview-wizard-settings-summary__item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
    padding: 3px 7px 3px 4px;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: #172554;
    font-size: 0.68rem;
    font-weight: 800;
}

.overview-wizard-settings-summary__rank {
    display: inline-grid;
    place-items: center;
    width: 16px;
    height: 16px;
    border-radius: 999px;
    background: #2563eb;
    color: #ffffff;
    font-size: 0.62rem;
}

.overview-wizard-settings-summary__label,
.overview-wizard-settings-summary__option {
    overflow-wrap: anywhere;
}

.overview-wizard-settings-summary__option {
    color: #475569;
}

.overview-wizard-settings-summary__option::before {
    content: "· ";
}

.overview-wizard-settings-summary__empty {
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
}

.overview-wizard-actions {
    flex: 0 0 auto;
    gap: 2px;
}

.overview-wizard-info-dialog {
    display: grid;
    gap: 14px;
}

.overview-wizard-info-dialog__section {
    display: grid;
    gap: 6px;
}

.overview-wizard-info-dialog__title {
    color: #172554;
    font-weight: 850;
}

.overview-wizard-info-dialog p {
    margin: 0;
    color: #334155;
    line-height: 1.45;
}

.overview-wizard-course-cards {
    margin-bottom: 16px;
}

.overview-wizard-footer {
    display: flex;
    justify-content: flex-end;
    margin: -6px 0 16px;
}

.overview-wizard-loading {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 48px;
    padding: 0 12px;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 800;
}

.overview-selection-dialog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.course-choice-panel__header,
.course-choice-panel__title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.course-choice-panel__header {
    justify-content: space-between;
}

.course-choice-panel__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.course-choice-restriction-switch {
    display: inline-flex;
    flex: 0 1 auto;
    flex-wrap: wrap;
    height: auto;
    max-width: 100%;
    padding: 3px;
    background: #eef2f7;
    border: 1px solid #dbe4f0;
    border-radius: 10px;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.06);
    gap: 2px;
}

.course-choice-restriction-switch :deep(.v-btn) {
    flex: 1 1 auto;
    min-width: 0;
}

.course-choice-restriction-option {
    min-height: 28px;
    padding: 0 14px;
    border-radius: 7px !important;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: none;
}

.course-choice-restriction-option :deep(.v-btn__content) {
    white-space: nowrap;
}

.course-choice-restriction-option--active {
    background: #ffffff;
    color: #1d4ed8;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.16);
}

.course-menu-dialog-options {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 14px;
}

.course-choice-panel__title {
    color: #172554;
    font-size: 0.86rem;
    font-weight: 800;
}

.course-choice-panel__semesters {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
}

.course-choice-semester {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.course-choice-semester__label {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 800;
}

.course-choice-semester__dates {
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-weight: 700;
}

.course-item-chips,
.course-menu-dialog-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.course-menu-dialog-items .course-item-chips {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
}

.course-menu-dialog-items .course-item-chip {
    justify-content: flex-start;
    width: 100%;
}

.course-item-chip,
.course-menu-dialog-chip {
    font-weight: 650;
}

.course-item-chip__schedule {
    margin-left: 6px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.72rem;
    font-weight: 700;
}

.course-menu-dialog-chips {
    max-height: 52vh;
    overflow-y: auto;
}

.course-menu-dialog-items {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(57, 73, 171, 0.16);
}

.semester-timetable {
    overflow: hidden;
}

.semester-timetable__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 0 0 8px;
}

.semester-timetable__dates {
    font-size: 0.75rem;
    color: rgba(0, 0, 0, 0.6);
    white-space: nowrap;
}

.selected-course-filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding-top: 2px;
}

.selected-course-filter-chip {
    font-weight: 650;
}

.overview-student-selection {
    margin-bottom: 14px;
}

.overview-student-selection .transferred-student-context {
    margin-bottom: 0;
}

.overview-student-inline-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex: 0 0 auto;
}

.overview-student-dialog__meta {
    margin-bottom: 10px;
    color: rgba(var(--v-theme-on-surface), 0.68);
    font-size: 0.78rem;
    font-weight: 700;
}

.overview-student-search-results {
    display: grid;
    gap: 6px;
    margin-top: 10px;
    max-height: 260px;
    overflow-y: auto;
}

.overview-student-search-results__item {
    justify-content: flex-start;
    min-height: 32px;
}

.overview-student-search-results__empty {
    padding: 8px 2px;
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.78rem;
}

.transferred-student-context {
    display: grid;
    gap: 10px;
    margin-bottom: 14px;
    padding: 10px;
    border: 1px solid rgba(14, 165, 233, 0.22);
    border-radius: 8px;
    background: #f0f9ff;
}

.transferred-student-context--collapsed {
    gap: 0;
}

.transferred-student-context__header,
.transferred-student-context__title,
.transferred-student-context__header-actions,
.transferred-student-course-section,
.transferred-student-course-section__title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.transferred-student-context__header {
    justify-content: space-between;
    flex-wrap: wrap;
    cursor: pointer;
    outline: none;
}

.transferred-student-context__header:focus-visible {
    box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.22);
    border-radius: 6px;
}

.transferred-student-context__title {
    min-width: 0;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 800;
}

.transferred-student-context__header-actions {
    flex: 0 0 auto;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.transferred-student-context__sections {
    display: grid;
    gap: 7px;
    padding-top: 2px;
}

.transferred-student-course-section {
    align-items: flex-start;
    min-width: 0;
    padding-top: 7px;
    border-top: 1px solid rgba(14, 165, 233, 0.18);
}

.transferred-student-course-section__title {
    flex: 0 0 164px;
    justify-content: flex-start;
    color: rgba(var(--v-theme-on-surface), 0.74);
    font-size: 0.7rem;
    font-weight: 800;
}

.transferred-student-course-section__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    min-width: 0;
}

.transferred-student-course-chip {
    max-width: 100%;
    font-weight: 750;
}

.transferred-student-course-chip__meta {
    margin-left: 5px;
    opacity: 0.78;
}

.transferred-student-course-section__empty {
    color: rgba(var(--v-theme-on-surface), 0.54);
    font-size: 0.72rem;
    font-weight: 650;
}

.recurrence-week-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 0 8px;
}

.recurrence-week-selector__btn {
    min-width: 76px;
}

.recurrence-week-selector__pdf-btn {
    align-self: stretch;
    margin-left: auto;
    min-width: 96px;
}

.timetable-week + .timetable-week {
    margin-top: 12px;
}

.timetable-week__title {
    padding: 6px 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: rgba(0, 0, 0, 0.72);
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

.timetable-date-overview {
    margin-top: 8px;
    padding: 8px 10px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
}

.timetable-date-overview__title {
    margin-bottom: 5px;
    font-size: 0.78rem;
    font-weight: 750;
}

.timetable-date-overview__groups,
.timetable-date-overview__group,
.timetable-date-overview__courses,
.timetable-date-overview__course {
    display: grid;
}

.timetable-date-overview__groups {
    gap: 8px;
}

.timetable-date-overview__group {
    gap: 5px;
}

.timetable-date-overview__slot {
    color: #475569;
    font-size: 0.76rem;
    font-weight: 700;
}

.timetable-date-overview__courses {
    gap: 6px;
}

.timetable-date-overview__course {
    gap: 3px;
}

.timetable-date-overview__course-title {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 4px;
    font-size: 0.74rem;
    font-weight: 700;
}

.timetable-date-overview__range {
    color: #64748b;
    font-weight: 650;
}

.timetable-date-overview__dates {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.timetable-date-overview__date {
    padding: 1px 5px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.69rem;
    line-height: 1.45;
    white-space: nowrap;
}

.timetable-hour-num {
    font-weight: 750;
    font-size: 0.76rem;
}

.timetable-hour-time {
    margin-top: 1px;
    font-size: 0.66rem;
    font-weight: 400;
    line-height: 1.12;
    opacity: 0.78;
}

.timetable-generated-grid {
    display: grid;
    grid-template-columns: 88px repeat(var(--overview-timetable-weekdays, 5), minmax(72px, 1fr));
    grid-template-rows: 34px;
    grid-auto-rows: minmax(58px, auto);
    gap: 2px;
    overflow-x: auto;
}

.timetable-generated-cell {
    position: relative;
    min-height: 0;
    padding: 5px;
    border-radius: 5px;
    background: #eef2f7;
    color: #0f172a;
    font-size: 0.76rem;
    line-height: 1.15;
    text-align: center;
    overflow: hidden;
}

.timetable-generated-cell--header,
.timetable-generated-cell--time {
    background: #dbeafe;
    color: #1e3a8a;
    font-weight: 750;
}

.timetable-generated-cell--filled {
    background: #bbf7d0;
    color: #052e16;
}

.timetable-generated-cell--has-single-date-markers {
    padding-top: 24px;
}

.timetable-generated-cell--conflict {
    background: #fecaca;
    color: #7f1d1d;
}

.timetable-generated-cell--related-overlap {
    background: #fed7aa;
    color: #7c2d12;
}

.timetable-generated-cell--empty {
    grid-column: 1 / -1;
    color: #475569;
    font-weight: 650;
}

.timetable-generated-cell__content {
    display: grid;
    gap: 2px;
    align-content: center;
    min-height: 48px;
    cursor: pointer;
    outline: none;
    overflow: hidden;
}

.timetable-generated-cell__content + .timetable-generated-cell__content {
    margin-top: 6px;
    padding-top: 5px;
    border-top: 1px solid rgba(15, 23, 42, 0.14);
}

.timetable-generated-cell__content:hover .timetable-generated-cell__code,
.timetable-generated-cell__content:focus-visible .timetable-generated-cell__code {
    text-decoration: underline;
}

.timetable-generated-cell__code {
    display: inline-flex;
    justify-content: center;
    align-items: flex-start;
    gap: 2px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.timetable-generated-cell__details {
    margin-top: 2px;
    font-size: 0.66rem;
    font-weight: 400;
    line-height: 1.12;
    opacity: 0.78;
    overflow-wrap: anywhere;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.timetable-generated-cell__single-date-markers {
    position: absolute;
    top: 3px;
    right: 3px;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 2px;
    max-width: calc(100% - 6px);
}

.timetable-generated-cell__single-date-marker {
    display: inline-flex;
    align-items: flex-start;
    max-width: 100%;
    min-height: 14px;
    padding: 0 4px;
    border: 1px solid rgba(30, 64, 175, 0.2);
    border-radius: 999px;
    background: #bfdbfe;
    color: #1e3a8a;
    font-size: 0.58rem;
    font-weight: 850;
    line-height: 1.15;
    cursor: pointer;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timetable-generated-cell__single-date-marker:hover,
.timetable-generated-cell__single-date-marker:focus-visible {
    border-color: rgba(30, 64, 175, 0.42);
    text-decoration: underline;
    outline: none;
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

.overview-print-hidden {
    flex: 0 0 auto;
}

@media (max-width: 900px) {
    .overview-selection {
        align-items: stretch;
    }

    .overview-selected-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .overview-selection {
        flex-direction: column;
    }

    .overview-wizard-row {
        align-items: stretch;
        flex-direction: column;
    }

    .overview-wizard-cancel-button {
        align-self: flex-end;
        margin-left: 0;
    }

    .overview-wizard-actions {
        justify-content: flex-end;
    }

    .overview-selected-cards,
    .overview-selection-dialog-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .transferred-student-course-section {
        display: grid;
        gap: 6px;
    }

    .transferred-student-course-section__title {
        flex: initial;
    }
}
</style>
