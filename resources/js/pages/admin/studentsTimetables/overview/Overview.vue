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
                    v-if="!wizardPanelOpen && !adoptedTimetableOverviewActive"
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
                    <div class="transferred-student-context">
                        <div class="transferred-student-context__header">
                            <div class="transferred-student-context__title">
                                <v-icon icon="mdi-account-school-outline" size="18" color="primary" />
                                <span>{{ transferredStudentLabel }}</span>
                                <button
                                    v-if="transferredStudentEmail"
                                    type="button"
                                    class="transferred-student-context__email"
                                    title="E-Mail kopieren"
                                    :aria-label="'E-Mail ' + transferredStudentEmail + ' kopieren'"
                                    @click.stop="copyTransferredStudentEmail">
                                    <v-icon icon="mdi-email-outline" size="15" />
                                    <span>{{ transferredStudentEmail }}</span>
                                    <v-icon
                                        :icon="transferredStudentEmailCopied ? 'mdi-check-circle-outline' : 'mdi-content-copy'"
                                        :class="{ 'transferred-student-context__email-copy-icon--copied': transferredStudentEmailCopied }"
                                        size="14" />
                                </button>
                                <span v-if="!wizardPanelOpen && !timetableContextLocked" class="overview-student-inline-actions">
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
                        </div>
                        <div
                            v-if="transferredStudentContext"
                            class="transferred-student-completed-card">
                            <div class="transferred-student-completed-card__header">
                                <v-icon
                                    :icon="completedTransferredStudentCourseSection.icon"
                                    size="20"
                                    :color="completedTransferredStudentCourseSection.color" />
                                <h3>{{ completedTransferredStudentCourseSection.title }}</h3>
                                <v-chip
                                    size="small"
                                    variant="flat"
                                    :color="completedTransferredStudentCourseSection.color">
                                    {{ completedTransferredStudentCourseSection.items.length }}
                                </v-chip>
                            </div>
                            <div
                                v-if="completedTransferredStudentCourseSection.items.length"
                                class="transferred-student-course-section__chips transferred-student-completed-card__chips">
                                <v-chip
                                    v-for="course in completedTransferredStudentCourseSection.items"
                                    :key="course.key"
                                    size="x-small"
                                    :color="completedTransferredStudentCourseSection.color"
                                    variant="tonal"
                                    class="transferred-student-course-chip">
                                    <span>{{ course.label }}</span>
                                    <span v-if="course.meta" class="transferred-student-course-chip__meta">
                                        {{ course.meta }}
                                    </span>
                                </v-chip>
                            </div>
                            <div v-else class="transferred-student-course-section__empty">Keine</div>
                        </div>
                    </div>
                </div>

                <div class="overview-selection">
                    <div class="overview-selected-cards">
                        <div
                            v-for="item in selectedSummary"
                            :key="item.key"
                            class="overview-selected-card">
                            <div v-if="item.meta" class="overview-selected-card__meta">{{ item.meta }}</div>
                            <div class="overview-selected-card__label">{{ item.label }}</div>
                            <div class="overview-selected-card__value">{{ item.value }}</div>
                        </div>
                    </div>
                    <v-btn
                        v-if="!wizardPanelOpen && !timetableContextLocked"
                        icon="mdi-pencil"
                        variant="tonal"
                        color="primary"
                        title="Auswahl bearbeiten"
                        @click="openSelectionDialog" />
                </div>

                <div v-if="manualCourseOverviewVisible" class="manual-course-overview">
                    <v-expansion-panels
                        v-model="expandedManualCourseOverviewPanels"
                        multiple
                        flat
                        class="manual-course-overview__panels">
                        <v-expansion-panel value="courses" class="manual-course-overview__panel">
                            <v-expansion-panel-title class="manual-course-overview__title">
                                <v-icon icon="mdi-book-open-page-variant-outline" size="22" />
                                <h3>Kurse</h3>
                                <v-chip size="small" variant="flat" color="primary">
                                    {{ transferredStudentCourseTotalCount }}
                                </v-chip>
                            </v-expansion-panel-title>

                            <v-expansion-panel-text>
                                <v-expansion-panels
                                    v-model="expandedTransferredStudentCourseSections"
                                    class="transferred-student-course-panels manual-course-overview__course-panels"
                                    multiple
                                    flat>
                                    <v-expansion-panel
                                        v-for="section in transferredStudentCourseSections"
                                        :key="section.key"
                                        :value="section.key"
                                        class="transferred-student-course-panel">
                                        <v-expansion-panel-title class="transferred-student-course-panel__title">
                                            <v-icon size="22">{{ section.icon }}</v-icon>
                                            <h3>{{ section.title }}</h3>
                                            <v-chip size="small" variant="flat" :color="section.color">
                                                {{ section.items.length }}
                                            </v-chip>
                                        </v-expansion-panel-title>

                                        <v-expansion-panel-text>
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
                                        </v-expansion-panel-text>
                                    </v-expansion-panel>
                                </v-expansion-panels>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </div>

                <div v-if="wizardPanelOpen" class="overview-automatic-heading">
                    <v-icon icon="mdi-calendar-clock" size="22" />
                    <h2>Automatischer Stundenplan</h2>
                    <span class="overview-active-label__stars">
                        <v-icon icon="mdi-star-four-points" size="10" class="overview-star overview-star--1" />
                        <v-icon icon="mdi-star-four-points" size="14" class="overview-star overview-star--2" />
                        <v-icon icon="mdi-star-four-points" size="8" class="overview-star overview-star--3" />
                    </span>
                </div>

                <div v-if="directManualPanelOpen || transferredTimetableContextLocked" class="overview-automatic-heading overview-automatic-heading--manual">
                    <v-icon icon="mdi-calendar-edit" size="22" />
                    <h2>Manueller Stundenplan</h2>
                </div>

                <div v-if="wizardPanelOpen && !wizardTimetableResultVisible" class="overview-wizard-settings-summary">
                    <div class="overview-wizard-settings-summary__header">
                        <div class="overview-wizard-settings-summary__title">
                            <v-icon icon="mdi-tune-variant" size="18" />
                            Bewertungskriterien
                        </div>
                        <v-btn
                            icon="mdi-cog-outline"
                            variant="text"
                            density="comfortable"
                            color="primary"
                            title="Einstellungen"
                            aria-label="Einstellungen"
                            @click="settingsDialogOpen = true" />
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

                <div
                    v-if="visibleTransferredStudentCourseSections.length"
                    class="transferred-student-course-overview">
                    <div class="transferred-student-course-panels">
                        <section
                            v-for="section in visibleTransferredStudentCourseSections"
                            :key="section.key"
                            class="transferred-student-course-panel">
                            <div class="transferred-student-course-panel__title">
                                <v-icon size="22">{{ section.icon }}</v-icon>
                                <h3>{{ section.title }}</h3>
                                <v-chip size="small" variant="flat" :color="section.color">
                                    {{ section.items.length }}
                                </v-chip>
                            </div>

                            <div class="transferred-student-course-panel__body">
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
                            </div>
                        </section>
                    </div>
                </div>

                <div
                    v-if="wizardPanelMounted"
                    v-show="wizardPanelOpen"
                    class="overview-wizard-course-cards-panel">
                    <RobotTimetable
                        ref="wizardCourseCards"
                        embedded-course-cards-only
                        :automatic-route-step="automaticTimetableRouteStep"
                        :evaluation-criteria-settings="evaluationCriteria"
                        :restore-generated-timetable="wizardTimetableResultRouteActive"
                        :student-code="transferredStudentContext?.student?.studentCode || null"
                        class="overview-wizard-course-cards"
                        @automatic-step-change="setAutomaticTimetableStep"
                        @generated-timetable-visibility-change="setWizardTimetableResultVisible"
                        @timetable-result-selection-loading-change="setTimetableResultSelectionLoading"
                        @timetable-overtaken="showOvertakenManualTimetable">
                        <template #timetable-selector-start>
                            <v-btn
                                class="overview-wizard-close-button overview-wizard-cancel-button overview-wizard-close-button--calm"
                                variant="tonal"
                                color="error"
                                size="large"
                                prepend-icon="mdi-close-circle-outline"
                                @click="endAndRestartAdoptedTimetable">
                                Ende/Neustart
                            </v-btn>
                        </template>
                        <template #course-card-action="{ ready, extending, createActionVisible, extensionActionVisible }">
                            <v-btn
                                v-if="ready && (extensionActionVisible || (createActionVisible && !wizardTimetableResultVisible))"
                                class="overview-wizard-create-button"
                                variant="flat"
                                color="success"
                                size="large"
                                style="font-weight: 400"
                                prepend-icon="mdi-calendar-clock"
                                :disabled="wizardTimetableCreating"
                                :loading="wizardTimetableCreating"
                                @click="createWizardTimetable(extending)">
                                {{ extending ? 'Stundenplan erweitern' : 'Stundenplan erstellen' }}
                            </v-btn>
                        </template>
                        <template #course-actions="{ ready, loading, extending, createActionVisible, extensionActionVisible }">
                            <div
                                v-if="ready && (extensionActionVisible || (createActionVisible && !wizardTimetableResultVisible))"
                                class="overview-wizard-footer">
                                <div
                                    v-if="wizardTimetableCreating"
                                    class="overview-wizard-loading overview-wizard-loading--creating"
                                    role="status"
                                    aria-live="polite">
                                    <v-progress-circular
                                        indeterminate
                                        size="18"
                                        width="2"
                                    color="success" />
                                    <span>{{ extending ? 'Stundenplan wird erweitert...' : 'Stundenplan wird erstellt...' }}</span>
                                </div>
                                <v-btn
                                    class="overview-wizard-close-button overview-wizard-cancel-button overview-wizard-close-button--calm"
                                    variant="tonal"
                                    color="error"
                                    size="large"
                                    prepend-icon="mdi-close-circle-outline"
                                    @click="endAndRestartAdoptedTimetable">
                                    Ende/Neustart
                                </v-btn>
                                <v-btn
                                    v-if="extending"
                                    class="overview-wizard-close-button overview-wizard-back-button overview-wizard-close-button--calm overview-wizard-footer-back-button"
                                    variant="tonal"
                                    size="large"
                                    prepend-icon="mdi-arrow-left"
                                    @click="handleWizardCourseActionBack(extending)">
                                    Zurück
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
                            <div
                                v-if="loading"
                                class="overview-wizard-screen-loading"
                                role="status"
                                aria-label="Kurse werden geladen"
                                aria-live="polite">
                                <span class="overview-wizard-screen-loading__dots" aria-hidden="true">
                                    <span
                                        v-for="dot in 3"
                                        :key="dot"
                                        class="overview-wizard-screen-loading__dot" />
                                </span>
                                <span class="overview-wizard-screen-loading__label">Kurse werden geladen...</span>
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
                            :color="filterChip.hasOverlap || filterChip.hasRelatedOverlap ? 'warning' : 'success'"
                            variant="tonal"
                            closable
                            class="selected-course-filter-chip"
                            @click:close="handleCourseMenuEntryFilterClick(filterChip.entry)">
                            <span>{{ filterChip.label }}</span>
                            <span
                                v-if="filterChip.studentCourseBadge"
                                :class="[
                                    'selected-course-filter-chip__source',
                                    `selected-course-filter-chip__source--${filterChip.studentCourseType}`,
                                ]">
                                {{ filterChip.studentCourseBadge }}
                            </span>
                        </v-chip>
                    </div>
                </div>

                <div v-if="!wizardPanelOpen" class="overview-context-card">
                    <div class="overview-wizard-row">
                        <v-btn
                            v-if="!wizardPanelOpen && !manualPanelOpen"
                            class="overview-wizard-button"
                            variant="flat"
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
                        <v-btn
                            v-if="!wizardPanelOpen && !manualPanelOpen"
                            class="overview-manual-button"
                            variant="flat"
                            size="large"
                            prepend-icon="mdi-calendar-edit"
                            :active="directManualPanelOpen"
                            @click="openManualPanel">
                            <span class="overview-manual-button__label">
                                <span>Manueller</span>
                                <span>Stundenplan</span>
                            </span>
                        </v-btn>
                        <v-btn
                            v-if="savedTimetableButtonVisible"
                            class="overview-saved-timetable-button"
                            variant="flat"
                            size="large"
                            prepend-icon="mdi-calendar-check-outline"
                            :disabled="loading || timetableUpdatePending || publishedTimetableLoading"
                            :loading="publishedTimetableLoading"
                            @click="loadPublishedStudentTimetable">
                            <span class="overview-manual-button__label">
                                <span>Gespeicherter</span>
                                <span>Plan</span>
                            </span>
                        </v-btn>
                        <v-btn
                            v-if="manualPanelBackToWizardVisible"
                            class="overview-wizard-close-button overview-wizard-cancel-button overview-wizard-close-button--calm"
                            variant="tonal"
                            color="error"
                            size="large"
                            prepend-icon="mdi-close-circle-outline"
                            @click="endAndRestartAdoptedTimetable">
                            Ende/Neustart
                        </v-btn>
                        <div
                            v-if="manualPanelBackToWizardVisible && studentTimetableSaveVisible"
                            class="overview-student-save-action">
                            <v-btn
                                class="overview-wizard-close-button overview-student-save-button overview-wizard-close-button--calm"
                                color="success"
                                variant="flat"
                                size="large"
                                prepend-icon="mdi-content-save-outline"
                                :title="`Stundenplan für ${publishedTimetableStudentName} speichern`"
                                :aria-label="`Stundenplan für ${publishedTimetableStudentName} speichern`"
                                :disabled="loading || timetableUpdatePending || publishedTimetableSaving"
                                :loading="publishedTimetableSaving"
                                @click="savePublishedStudentTimetable">
                                <span class="overview-student-save-button__label">
                                    <span>Speichern für</span>
                                    <span>{{ publishedTimetableStudentName }}</span>
                                </span>
                            </v-btn>
                            <v-alert
                                v-if="publishedTimetableReport.message"
                                class="overview-published-timetable-report"
                                :type="publishedTimetableReport.type"
                                variant="tonal"
                                closable
                                density="compact"
                                @click:close="clearPublishedTimetableReport">
                                {{ publishedTimetableReport.message }}
                            </v-alert>
                        </div>
                        <v-btn
                            v-if="manualPanelBackToWizardVisible && visibleTimetableSemesters.length"
                            class="overview-wizard-close-button overview-wizard-pdf-button overview-wizard-close-button--calm"
                            color="error"
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
                        <v-btn
                            v-if="manualPanelBackToWizardVisible"
                            class="overview-wizard-close-button overview-wizard-back-button overview-wizard-close-button--calm"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-arrow-left"
                            @click="returnToWizardTimetableResult">
                            Zurück
                        </v-btn>
                        <v-btn
                            v-if="directManualPanelOpen && selectedCourseCount > 0"
                            class="overview-wizard-close-button overview-wizard-cancel-button overview-wizard-close-button--calm"
                            variant="tonal"
                            color="error"
                            size="large"
                            prepend-icon="mdi-close-circle-outline"
                            @click="endAndRestartAdoptedTimetable">
                            Ende/Neustart
                        </v-btn>
                        <v-spacer v-if="directManualPanelOpen" />
                        <div
                            v-if="directManualPanelOpen && studentTimetableSaveVisible"
                            class="overview-student-save-action">
                            <v-btn
                                class="overview-wizard-close-button overview-student-save-button overview-wizard-close-button--calm"
                                color="success"
                                variant="flat"
                                size="large"
                                prepend-icon="mdi-content-save-outline"
                                :title="`Stundenplan für ${publishedTimetableStudentName} speichern`"
                                :aria-label="`Stundenplan für ${publishedTimetableStudentName} speichern`"
                                :disabled="loading || timetableUpdatePending || publishedTimetableSaving"
                                :loading="publishedTimetableSaving"
                                @click="savePublishedStudentTimetable">
                                <span class="overview-student-save-button__label">
                                    <span>Speichern für</span>
                                    <span>{{ publishedTimetableStudentName }}</span>
                                </span>
                            </v-btn>
                            <v-alert
                                v-if="publishedTimetableReport.message"
                                class="overview-published-timetable-report"
                                :type="publishedTimetableReport.type"
                                variant="tonal"
                                closable
                                density="compact"
                                @click:close="clearPublishedTimetableReport">
                                {{ publishedTimetableReport.message }}
                            </v-alert>
                        </div>
                        <v-btn
                            v-if="directManualPanelOpen && visibleTimetableSemesters.length"
                            class="overview-wizard-close-button overview-wizard-pdf-button overview-manual-pdf-button overview-wizard-close-button--calm"
                            color="error"
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
                        <v-btn
                            v-if="directManualPanelOpen"
                            class="overview-wizard-close-button overview-wizard-back-button overview-wizard-close-button--calm overview-manual-back-button"
                            variant="tonal"
                            size="large"
                            prepend-icon="mdi-arrow-left"
                            @click="closeActiveTimetablePanel">
                            Zurück
                        </v-btn>
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
                                v-if="!manualPanelBackToWizardVisible && !directManualPanelOpen"
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
                                            'timetable-generated-cell--conflict': cellShouldShowWarning(semester.value, weekday.value, hour.hour, timetableWeek),
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
                                                <span>{{ courseGroupDisplayLabel(courseGroup) }}</span>
                                                <sup v-if="courseGroupDistanceLearning(courseGroup)" class="timetable-course-fu">FU</sup>
                                                <span
                                                    v-if="courseGroupStudentCourseBadge(courseGroup)"
                                                    :class="[
                                                        'timetable-generated-cell__course-badge',
                                                        `timetable-generated-cell__course-badge--${courseGroupStudentCourseType(courseGroup)}`,
                                                    ]">
                                                    {{ courseGroupStudentCourseBadge(courseGroup) }}
                                                </span>
                                            </div>
                                            <div v-if="courseGroupDetailLabel(courseGroup)" class="timetable-generated-cell__details">
                                                {{ courseGroupDetailLabel(courseGroup) }}
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

                            <div
                                v-if="sameSlotDateOverviewGroupsForSemester(semester.value, timetableWeek).length"
                                class="timetable-date-overview">
                                <div class="timetable-date-overview__title">Termine in gleichen Zellen</div>
                                <div class="timetable-date-overview__groups">
                                    <div
                                        v-for="dateOverviewGroup in sameSlotDateOverviewGroupsForSemester(semester.value, timetableWeek)"
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
                        </div>

                        <div
                            v-if="singleDateOverviewGroupsForSemester(semester.value).length"
                            class="timetable-date-overview timetable-single-date-overview">
                            <div class="timetable-date-overview__title">Einzeltermine</div>
                            <div class="timetable-single-date-overview__items">
                                <div
                                    v-for="singleDateGroup in singleDateOverviewGroupsForSemester(semester.value)"
                                    :key="singleDateGroup.key"
                                    class="timetable-single-date-overview__item"
                                    role="button"
                                    tabindex="0"
                                    @click="openCourseGroupDialog(singleDateGroup.courseGroup)"
                                    @keydown.enter="openCourseGroupDialog(singleDateGroup.courseGroup)">
                                    <div
                                        v-if="singleDateGroup.summary"
                                        class="timetable-single-date-overview__summary">
                                        {{ singleDateGroup.summary }}
                                    </div>
                                    <template v-else>
                                        <div class="timetable-date-overview__slot">{{ singleDateGroup.slotTitle }}</div>
                                        <div class="timetable-date-overview__course-title">
                                            <span>{{ singleDateGroup.title }}</span>
                                        </div>
                                    </template>
                                    <div
                                        v-if="!singleDateGroup.summary"
                                        class="timetable-date-overview__dates">
                                        <span
                                            v-for="dateLabel in singleDateGroup.dateLabels"
                                            :key="dateLabel"
                                            class="timetable-date-overview__date">
                                            {{ dateLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

            </v-card-text>
        </v-card>

        <template v-if="overviewScreenLoadingVisible">
            <div class="overview-screen-loading-blocker" aria-hidden="true"></div>
            <div
                class="overview-screen-loading"
                role="status"
                aria-live="polite"
                :aria-label="overviewScreenLoadingLabel">
                <LoadingAnimation class="overview-screen-loading__dots" />
                <span>{{ overviewScreenLoadingLabel }}</span>
            </div>
        </template>

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
                            :model-value="visibleCourseChoiceRestrictionMode"
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
                                    :class="{ 'course-item-chip--disabled': entryOption.isDisabled }"
                                    :aria-disabled="entryOption.isDisabled ? 'true' : 'false'"
                                    @click="handleCourseMenuEntryFilterClick(entryOption.entry, entryOption)">
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
const TIMETABLE_OVERVIEW_LANDING_PATH = TIMETABLE_OVERVIEW_BASE_PATH
const TIMETABLE_OVERVIEW_ROUTE_MODES = ['automatic', 'manual', 'adopted']
const TIMETABLE_OVERVIEW_RESULT_ACTION = 'result'
const TIMETABLE_OVERVIEW_ROUTE_STEPS = ['additional-courses']
const COURSE_CHOICE_RESTRICTION_MISSING = 'missing'
const COURSE_CHOICE_RESTRICTION_PLANNED = 'planned'
const COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL = 'planned_additional'
const COURSE_CHOICE_RESTRICTION_ADDITIONAL = 'additional'
const COURSE_CHOICE_RESTRICTION_ALL = 'all'
const COURSE_CHOICE_RESTRICTION_ALL_AVAILABLE = 'all_available'
const COURSE_CHOICE_RESTRICTION_MODES = [
    COURSE_CHOICE_RESTRICTION_MISSING,
    COURSE_CHOICE_RESTRICTION_PLANNED,
    COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL,
    COURSE_CHOICE_RESTRICTION_ADDITIONAL,
    COURSE_CHOICE_RESTRICTION_ALL,
    COURSE_CHOICE_RESTRICTION_ALL_AVAILABLE,
]
const OVERVIEW_SECTION_COLORS = {
    completed: '#00897B',
    missing: '#FB8C00',
    planned: '#3949AB',
    additional: '#0288D1',
}

function normalizedCourseDisplayLabel(label) {
    return String(label || '')
        .replace(/^LET(?=\d|\s|-|$)/iu, 'LPT')
        .replace(/^GSGPB(?=\d|\s|-|$)/iu, 'GS')
        .replace(/^MEMU(?=\d|\s|-|$)/iu, 'ME')
        .replace(/^OKON(?=\d|\s|-|$)/iu, 'ÖKO')
}

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
            wizardTimetableResultVisible: false,
            manualPanelOpen: false,
            manualPanelSource: null,
            wizardTimetableCreating: false,
            timetableUpdatePending: false,
            pdfExporting: false,
            publishedTimetableLoading: false,
            publishedTimetableSaving: false,
            publishedTimetableReport: {
                type: 'success',
                message: '',
            },
            studentDialogOpen: false,
            studentOptionsLoading: false,
            transferredStudentCoursesLoading: false,
            studentSearch: '',
            transferredStudentEmailCopied: false,
            transferredStudentEmailCopiedTimeout: null,
            robotStudents: [],
            subjectRows: [],
            evaluationCriteria: [],
            studentSelectionDraft: {
                studentCode: null,
            },
            expandedManualCourseOverviewPanels: [],
            expandedTransferredStudentCourseSections: [],
            studentCompletedCoursesRequestId: 0,
            activeCourseGroupFilterKeys: [],
            activeDistanceLearningCourseGroupKeys: [],
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
        activeDistanceLearningCourseGroupKeySet() {
            return new Set(this.activeDistanceLearningCourseGroupKeys.filter(Boolean))
        },
        semesterCourseMenusBySemester() {
            return {
                1: this.buildSemesterCourseMenus(1),
                2: this.buildSemesterCourseMenus(2),
            }
        },
        courseChoiceCourseGroups() {
            const courseRestrictionContext = this.courseChoiceRestrictionContext
                || this.buildCourseChoiceRestrictionContext()
            const courseRestrictionMode = courseRestrictionContext.mode
            const availableCourseGroups = Array.isArray(this.availableCourseChoiceCourseGroups)
                ? this.availableCourseChoiceCourseGroups
                : Array.isArray(this.configuredCourseGroups)
                    ? this.configuredCourseGroups
                    : []

            if (courseRestrictionMode === COURSE_CHOICE_RESTRICTION_ALL_AVAILABLE) {
                return Array.isArray(this.configuredCourseGroups) ? this.configuredCourseGroups : []
            }

            if (courseRestrictionMode === COURSE_CHOICE_RESTRICTION_ALL) {
                return availableCourseGroups
                    .filter(courseGroup => this.courseGroupMatchesGeneralStudentTimetable(courseGroup))
                    .filter(courseGroup => this.courseGroupMatchesSelectedReligionChoice(courseGroup, courseRestrictionContext))
                    .filter(courseGroup => this.courseGroupMatchesSelectedLanguageChoice(courseGroup, courseRestrictionContext))
            }

            const restrictedCourseGroups = availableCourseGroups
                .filter(courseGroup => this.courseGroupMatchesCourseChoiceRestriction(courseGroup, courseRestrictionContext))

            if (
                this.adoptedTimetableOverviewActive
                && courseRestrictionMode === COURSE_CHOICE_RESTRICTION_ADDITIONAL
            ) {
                return this.uniqueCourseGroupsByKey([
                    ...restrictedCourseGroups,
                    ...this.adoptedAdditionalCourseChoiceCourseGroups(),
                ])
            }

            if (
                restrictedCourseGroups.length
                || courseRestrictionMode !== COURSE_CHOICE_RESTRICTION_ADDITIONAL
                || courseRestrictionContext.hasCourseChoiceRestrictions
            ) {
                return restrictedCourseGroups
            }

            if (this.adoptedTimetableOverviewActive) {
                return this.adoptedAdditionalCourseChoiceCourseGroups()
            }

            return this.additionalCourseChoiceFallbackGroups()
        },
        availableCourseChoiceCourseGroups() {
            const completedCourseCodes = this.completedStudentCourseCodes instanceof Set
                ? this.completedStudentCourseCodes
                : new Set()
            const courseGroups = Array.isArray(this.configuredCourseGroups) ? this.configuredCourseGroups : []

            if (!completedCourseCodes.size) return courseGroups

            return courseGroups.filter(courseGroup => !this.courseGroupMatchesRestrictedCourseCodes(
                this.courseGroupCodes(courseGroup),
                completedCourseCodes,
                courseGroup,
                { shouldRestrictByTimetableSemester: false },
            ))
        },
        completedStudentCourseCodes() {
            return this.studentCompletedCourseCodes(this.transferredStudentContext?.courses?.completed || [])
        },
        courseChoiceRestrictionContext() {
            return this.buildCourseChoiceRestrictionContext()
        },
        courseChoiceRestrictionOptions() {
            return [
                { value: COURSE_CHOICE_RESTRICTION_MISSING, label: 'Fehlende Kurse' },
                { value: COURSE_CHOICE_RESTRICTION_PLANNED, label: 'Vorgesehene Kurse' },
                { value: COURSE_CHOICE_RESTRICTION_ADDITIONAL, label: 'Zusätzliche Kurse' },
                { value: COURSE_CHOICE_RESTRICTION_ALL, label: 'Offene Kurse' },
                { value: COURSE_CHOICE_RESTRICTION_ALL_AVAILABLE, label: 'Alle' },
            ]
        },
        visibleCourseChoiceRestrictionMode() {
            if (this.courseChoiceRestrictionMode === COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL) {
                return COURSE_CHOICE_RESTRICTION_ADDITIONAL
            }

            return this.courseChoiceRestrictionMode
        },
        effectiveCourseChoiceRestrictionMode() {
            if (this.courseChoiceRestrictionMode === COURSE_CHOICE_RESTRICTION_PLANNED_ADDITIONAL) {
                return COURSE_CHOICE_RESTRICTION_ADDITIONAL
            }

            return this.normalizedCourseChoiceRestrictionMode(this.courseChoiceRestrictionMode, {
                restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
            })
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
            const courseRestrictionMode = typeof this.effectiveCourseChoiceRestrictionMode === 'string'
                ? this.effectiveCourseChoiceRestrictionMode
                : this.normalizedCourseChoiceRestrictionMode(this.courseChoiceRestrictionMode, {
                    restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
                })
            const courseHistory = this.transferredStudentContext?.courses || this.noStudentCourseHistory
            const studentCourses = Array.isArray(this.courseChoiceRestrictionCourses)
                ? this.courseChoiceRestrictionCourses
                : this.courseChoiceRestrictionItemsFromCourses(
                    courseHistory,
                    courseRestrictionMode,
                )
            const selectionCourseChoiceCodes = this.selectionCourseChoiceCodes instanceof Set
                ? this.selectionCourseChoiceCodes
                : new Set()
            const fallbackCourseCodes = courseHistory || courseRestrictionMode === COURSE_CHOICE_RESTRICTION_ADDITIONAL
                ? []
                : [...selectionCourseChoiceCodes]

            return new Set((studentCourses.length ? studentCourses : fallbackCourseCodes)
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
            const courseRestrictionMode = typeof this.effectiveCourseChoiceRestrictionMode === 'string'
                ? this.effectiveCourseChoiceRestrictionMode
                : this.normalizedCourseChoiceRestrictionMode(this.courseChoiceRestrictionMode, {
                    restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
                })

            return this.courseChoiceRestrictionItemsFromCourses(
                courses,
                courseRestrictionMode,
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
                {
                    key: 'religion',
                    label: 'Ethik / Religion',
                    value: this.selectedOptionTitle(this.religionOptions, this.selection.religion),
                    meta: this.transferredStudentReligionMeta,
                },
                { key: 'language', label: 'Sprache', value: this.selectedOptionTitle(this.languageOptions, this.selection.language) },
                { key: 'branch', label: 'Zweig', value: this.selectedOptionTitle(this.branchOptions, this.selection.branch) },
                { key: 'artsSubject', label: 'ME / BE', value: this.selectedOptionTitle(this.artsSubjectOptions, this.selection.artsSubject) },
            ]
        },
        studentSearchReady() {
            return this.normalizedStudentSearch.length >= 2
        },
        overviewScreenLoadingVisible() {
            return Boolean(
                this.loading
                || this.studentOptionsLoading
                || this.transferredStudentCoursesLoading
                || this.wizardTimetableCreating
                || this.timetableUpdatePending
                || this.pdfExporting
                || this.publishedTimetableLoading
                || this.publishedTimetableSaving,
            )
        },
        overviewScreenLoadingLabel() {
            if (this.pdfExporting) return 'PDF wird erstellt...'

            if (this.publishedTimetableLoading) return 'Gespeicherter Plan wird geladen...'

            if (this.publishedTimetableSaving) return 'Stundenplan wird gespeichert...'

            if (this.timetableUpdatePending) return 'Stundenplan wird aktualisiert...'

            if (this.wizardTimetableCreating) return 'Stundenplan wird berechnet...'

            if (this.transferredStudentCoursesLoading) return 'Schülerdaten werden geladen...'

            if (this.studentOptionsLoading) return 'Studenten werden geladen...'

            if (this.loading) return 'Stundenplandaten werden geladen...'

            return 'Bitte warten...'
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
        transferredStudentEmail() {
            const contextEmail = this.normalizedEmailValue(this.transferredStudentContext?.student?.email)
            if (contextEmail) return contextEmail

            const studentCode = this.normalizedStudentCode(this.transferredStudentContext?.student?.studentCode)
            if (!studentCode) return ''

            const selectedStudent = (Array.isArray(this.robotStudents) ? this.robotStudents : [])
                .find(student => this.normalizedStudentCode(student?.student_code) === studentCode)

            return this.normalizedEmailValue(selectedStudent?.email)
        },
        publishedTimetableStudentCode() {
            return this.normalizedStudentCode(this.transferredStudentContext?.student?.studentCode)
        },
        publishedTimetableStudentName() {
            const studentCode = this.publishedTimetableStudentCode
            const robotStudents = Array.isArray(this.robotStudents) ? this.robotStudents : []
            const selectedStudent = studentCode
                ? robotStudents.find(student => this.normalizedStudentCode(student?.student_code) === studentCode)
                : null
            const studentName = [selectedStudent?.last_name, selectedStudent?.first_name]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join(' ')

            if (studentName) return studentName

            const label = String(this.transferredStudentContext?.student?.label || '').trim()
            const labelParts = label
                .split('·')
                .map(part => part.trim())
                .filter(Boolean)

            if (labelParts.length >= 2) return labelParts[1]

            return label || 'Student'
        },
        selectedPublishedTimetableStudent() {
            const studentCode = this.publishedTimetableStudentCode
            if (!studentCode) return null

            const robotStudents = Array.isArray(this.robotStudents) ? this.robotStudents : []

            return robotStudents.find(student => this.normalizedStudentCode(student?.student_code) === studentCode) || null
        },
        savedTimetableButtonVisible() {
            return Boolean(
                !this.wizardPanelOpen
                && !this.manualPanelOpen
                && this.selectedPublishedTimetableStudent?.has_published_timetable,
            )
        },
        studentTimetableSaveVisible() {
            return Boolean(
                (this.directManualPanelOpen || this.manualPanelBackToWizardVisible)
                && this.visibleTimetableSemesters.length
                && this.publishedTimetableStudentCode,
            )
        },
        transferredStudentReligion() {
            const contextReligion = String(this.transferredStudentContext?.student?.religion || '').trim()
            if (contextReligion) return contextReligion

            const studentCode = this.normalizedStudentCode(this.transferredStudentContext?.student?.studentCode)
            if (!studentCode) return ''

            const selectedStudent = this.robotStudents
                .find(student => this.normalizedStudentCode(student?.student_code) === studentCode)

            return String(selectedStudent?.religion || '').trim()
        },
        transferredStudentReligionMeta() {
            return this.studentReligionMeta(this.transferredStudentReligion)
        },
        transferredStudentCourseSections() {
            const courses = this.transferredStudentContext?.courses || this.noStudentCourseHistory

            return [
                {
                    key: 'completed',
                    title: 'Abgeschlossene Kurse',
                    icon: 'mdi-check-circle-outline',
                    color: OVERVIEW_SECTION_COLORS.completed,
                    items: courses.completed || [],
                },
                {
                    key: 'missing',
                    title: 'Fehlende Kurse',
                    icon: 'mdi-alert-circle-outline',
                    color: OVERVIEW_SECTION_COLORS.missing,
                    items: courses.missing || [],
                },
                {
                    key: 'planned',
                    title: 'Vorgesehene Kurse',
                    icon: 'mdi-format-list-checks',
                    color: OVERVIEW_SECTION_COLORS.planned,
                    items: courses.planned || [],
                },
                {
                    key: 'additional',
                    title: 'Zusätzliche Kurse',
                    icon: 'mdi-plus-circle-outline',
                    color: OVERVIEW_SECTION_COLORS.additional,
                    items: courses.additional || [],
                },
            ]
        },
        completedTransferredStudentCourseSection() {
            return this.transferredStudentCourseSections
                .find(section => section.key === 'completed') || ({
                    key: 'completed',
                    title: 'Abgeschlossene Kurse',
                    icon: 'mdi-check-circle-outline',
                    color: OVERVIEW_SECTION_COLORS.completed,
                    items: [],
                })
        },
        transferredStudentCourseTotalCount() {
            return this.transferredStudentCourseSections
                .reduce((courseCount, section) => courseCount + section.items.length, 0)
        },
        manualCourseOverviewVisible() {
            return Boolean(
                (this.directManualPanelOpen || this.transferredTimetableContextLocked)
                && this.transferredStudentContext
                && this.transferredStudentCourseSections.length,
            )
        },
        visibleTransferredStudentCourseSections() {
            if (this.wizardPanelOpen || this.timetableContextLocked || this.adoptedTimetableOverviewActive) {
                return []
            }

            return this.transferredStudentCourseSections
                .filter(section => section.key !== 'completed')
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
                const isDisabled = hasBlockingOverlap && !isActive

                return {
                    ...entry,
                    entry,
                    hasBlockingOverlap,
                    hasRelatedOverlap,
                    isActive,
                    isDisabled,
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
        timetableContextLocked() {
            return this.manualPanelOpen
        },
        transferredTimetableContextLocked() {
            return this.manualPanelOpen && this.manualPanelSource === 'wizard'
        },
        adoptedTimetableOverviewActive() {
            return this.normalizedTimetableOverviewRouteMode() === 'adopted'
        },
        wizardTimetableResultRouteActive() {
            return this.normalizedTimetableOverviewRouteMode() === 'automatic'
                && this.normalizedTimetableOverviewRouteAction() === TIMETABLE_OVERVIEW_RESULT_ACTION
        },
        automaticTimetableRouteStep() {
            return this.normalizedTimetableOverviewRouteMode() === 'automatic'
                ? this.normalizedTimetableOverviewRouteStep()
                : ''
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
            return this.courseGroupDisplayLabel(this.selectedCourseGroup) || 'Termine'
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
            this.applyTimetableOverviewModeFromRoute(detail, this.$route.params.action)
        },
        '$route.params.action'(action) {
            this.applyTimetableOverviewModeFromRoute(this.$route.params.detail, action)
        },
        '$route.query.step'() {
            this.applyTimetableOverviewModeFromRoute()
        },
    },

    mounted() {
        this.restoreLastTimetableState()
        this.applyTimetableOverviewModeFromRoute()
        this.loadData()
    },

    beforeUnmount() {
        if (this.transferredStudentEmailCopiedTimeout) {
            window.clearTimeout(this.transferredStudentEmailCopiedTimeout)
        }
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
                await this.loadTransferredStudentCompletedCourses(this.transferredStudentContext.student.studentCode, {
                    includeSelection: true,
                    applySelectionDefaults: true,
                })
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
        setTimetableResultSelectionLoading(loading) {
            if (loading === true) {
                this.timetableUpdatePending = true

                return
            }

            const updateWindow = typeof window !== 'undefined' ? window : null
            const timerTarget = updateWindow || globalThis

            this.$nextTick(() => {
                timerTarget.setTimeout(() => {
                    this.timetableUpdatePending = false
                }, 0)
            })
        },
        normalizedTimetableOverviewRouteMode(mode = this.$route?.params?.detail) {
            const normalizedMode = String(mode || '').trim()

            return TIMETABLE_OVERVIEW_ROUTE_MODES.includes(normalizedMode) ? normalizedMode : ''
        },
        normalizedTimetableOverviewRouteAction(action = this.$route?.params?.action) {
            const normalizedAction = String(action || '').trim()

            return normalizedAction === TIMETABLE_OVERVIEW_RESULT_ACTION ? normalizedAction : ''
        },
        normalizedTimetableOverviewRouteStep(step = this.$route?.query?.step) {
            const normalizedStep = String(step || '').trim()

            return TIMETABLE_OVERVIEW_ROUTE_STEPS.includes(normalizedStep) ? normalizedStep : ''
        },
        timetableOverviewModePath(mode = '', action = '') {
            const normalizedMode = this.normalizedTimetableOverviewRouteMode(mode)
            const normalizedAction = normalizedMode === 'automatic'
                ? this.normalizedTimetableOverviewRouteAction(action)
                : ''

            if (!normalizedMode) return TIMETABLE_OVERVIEW_LANDING_PATH

            return normalizedAction
                ? `${TIMETABLE_OVERVIEW_BASE_PATH}/${normalizedMode}/${normalizedAction}`
                : `${TIMETABLE_OVERVIEW_BASE_PATH}/${normalizedMode}`
        },
        timetableOverviewModeLocation(mode = '', action = '', step = '') {
            const path = this.timetableOverviewModePath(mode, action)
            const normalizedStep = this.normalizedTimetableOverviewRouteMode(mode) === 'automatic'
                && !this.normalizedTimetableOverviewRouteAction(action)
                ? this.normalizedTimetableOverviewRouteStep(step)
                : ''

            if (!normalizedStep) return { path }

            return {
                path,
                query: {
                    step: normalizedStep,
                },
            }
        },
        timetableOverviewLocationMatches(location) {
            const expectedStep = location?.query?.step || ''
            const currentStep = this.normalizedTimetableOverviewRouteStep()

            return this.$route?.path === location.path && currentStep === expectedStep
        },
        navigateToTimetableOverviewMode(mode = '', action = '', step = '') {
            const location = this.timetableOverviewModeLocation(mode, action, step)
            if (this.timetableOverviewLocationMatches(location)) return

            const navigation = this.$router?.push?.(location)
            navigation?.catch?.(() => {})
        },
        replaceTimetableOverviewMode(mode = '', action = '', step = '') {
            const location = this.timetableOverviewModeLocation(mode, action, step)
            if (this.timetableOverviewLocationMatches(location)) return

            const navigation = this.$router?.replace
                ? this.$router.replace(location)
                : this.$router?.push?.(location)
            navigation?.catch?.(() => {})
        },
        applyTimetableOverviewModeFromRoute(
            mode = this.$route?.params?.detail,
            action = this.$route?.params?.action,
        ) {
            const normalizedMode = this.normalizedTimetableOverviewRouteMode(mode)
            if (!normalizedMode) {
                this.applyTimetableOverviewLandingState()

                return
            }

            if (normalizedMode === 'automatic') {
                const normalizedAction = this.normalizedTimetableOverviewRouteAction(action)
                const normalizedStep = this.normalizedTimetableOverviewRouteStep()
                const hasRouteStep = Boolean(this.$route?.query?.step)

                if (normalizedAction === TIMETABLE_OVERVIEW_RESULT_ACTION) {
                    if (hasRouteStep) {
                        this.replaceTimetableOverviewMode('automatic', TIMETABLE_OVERVIEW_RESULT_ACTION)
                    }

                    this.returnToWizardTimetableResult({ syncRoute: false })

                    return
                }

                if (action || (hasRouteStep && !normalizedStep)) {
                    this.replaceTimetableOverviewMode('automatic')
                }

                this.openWizardPanel({ syncRoute: false })
                if (normalizedStep) {
                    this.wizardPanelMounted = true
                }

                return
            }

            if (normalizedMode === 'manual') {
                if (action || this.normalizedTimetableOverviewRouteStep()) {
                    this.replaceTimetableOverviewMode('manual')
                }

                this.openManualPanel({ syncRoute: false })

                return
            }

            if (action || this.normalizedTimetableOverviewRouteStep()) {
                this.replaceTimetableOverviewMode('adopted')
            }

            this.wizardPanelMounted = this.savedRobotTimetableStateAvailable()
            this.wizardPanelOpen = false
            this.manualPanelOpen = true
            this.manualPanelSource = this.wizardPanelMounted ? 'wizard' : 'direct'
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
            this.wizardTimetableResultVisible = false
            this.manualPanelOpen = false
            this.manualPanelSource = null
            if (options?.syncRoute !== false) {
                this.navigateToTimetableOverviewMode?.('automatic')
            }
        },
        returnToWizardTimetableResult(options = {}) {
            this.wizardPanelMounted = true
            this.wizardPanelOpen = true
            this.wizardTimetableResultVisible = true
            this.manualPanelOpen = false
            this.manualPanelSource = null
            if (options?.syncRoute !== false) {
                this.navigateToTimetableOverviewMode?.('automatic', TIMETABLE_OVERVIEW_RESULT_ACTION)
            }
        },
        closeWizardPanel() {
            this.wizardPanelOpen = false
            this.wizardTimetableResultVisible = false
            this.navigateToTimetableOverviewMode?.()
        },
        closeActiveTimetablePanel() {
            if (this.wizardPanelOpen) {
                this.closeWizardPanel()

                return
            }

            this.closeManualPanel()
        },
        handleWizardCourseActionBack(extending = false) {
            if (extending === true) {
                const wizardCourseCards = this.$refs.wizardCourseCards

                if (wizardCourseCards?.returnToCourseSelectionFromGeneratedTimetable) {
                    wizardCourseCards.returnToCourseSelectionFromGeneratedTimetable({
                        regularCourseSelection: true,
                    })

                    return
                }

                wizardCourseCards?.resetAdditionalCourseSelection?.()

                return
            }

            this.closeActiveTimetablePanel()
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
                this.wizardTimetableResultVisible = Boolean(wizardCourseCards?.selectedRobotTimetable)
                if (this.wizardTimetableResultVisible) {
                    this.navigateToTimetableOverviewMode?.('automatic', TIMETABLE_OVERVIEW_RESULT_ACTION)
                }
            } finally {
                this.wizardTimetableCreating = false
            }
        },
        setWizardTimetableResultVisible(visible) {
            this.wizardTimetableResultVisible = visible === true
            if (this.wizardTimetableResultVisible) {
                this.navigateToTimetableOverviewMode?.('automatic', TIMETABLE_OVERVIEW_RESULT_ACTION)
            }
        },
        setAutomaticTimetableStep(step) {
            if (!this.wizardPanelOpen) return

            const normalizedStep = this.normalizedTimetableOverviewRouteStep(step)
            this.wizardTimetableResultVisible = false
            this.navigateToTimetableOverviewMode?.('automatic', '', normalizedStep)
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
            const previousEvaluationCriteriaSignature = this.evaluationCriteriaSignature(this.evaluationCriteria)

            this.evaluationCriteria = this.enabledEvaluationCriteriaFromSettings(criteria || [])

            if (previousEvaluationCriteriaSignature !== this.evaluationCriteriaSignature(this.evaluationCriteria)) {
                this.resetWizardPanel()
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
        cloneCriteria(criteria) {
            return JSON.parse(JSON.stringify(criteria || []))
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
        evaluationCriteriaSignature(criteria) {
            return JSON.stringify(this.enabledEvaluationCriteriaFromSettings(criteria || [])
                .map((criterion, index) => ({
                    key: criterion.key,
                    priority: index + 1,
                    option: criterion.option || null,
                })))
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
        handleCourseMenuEntryFilterClick(entry, entryOption = null) {
            if (entryOption?.isDisabled) {
                return
            }

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
            this.restrictCourseChoiceBySelection = ![
                COURSE_CHOICE_RESTRICTION_ALL,
                COURSE_CHOICE_RESTRICTION_ALL_AVAILABLE,
            ].includes(this.courseChoiceRestrictionMode)
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
        async savePublishedStudentTimetable() {
            if (this.publishedTimetableSaving || !this.publishedTimetableStudentCode) return

            this.publishedTimetableSaving = true
            this.clearPublishedTimetableReport()

            try {
                const response = await axios.post(
                    '/api/admin/students-timetables/overview/student-timetable',
                    this.publishedStudentTimetablePayload(),
                )

                this.publishedTimetableReport = {
                    type: 'success',
                    message: response?.data?.message || 'Stundenplan wurde gespeichert.',
                }
                this.markStudentPublishedTimetable(response?.data?.data || {})
            } catch (error) {
                this.publishedTimetableReport = {
                    type: 'error',
                    message: error?.response?.data?.message || 'Stundenplan konnte nicht gespeichert werden.',
                }
            } finally {
                this.publishedTimetableSaving = false
            }
        },
        clearPublishedTimetableReport() {
            this.publishedTimetableReport = {
                type: 'success',
                message: '',
            }
        },
        async loadPublishedStudentTimetable() {
            if (this.publishedTimetableLoading || !this.publishedTimetableStudentCode) return

            this.publishedTimetableLoading = true
            this.clearPublishedTimetableReport()

            try {
                const response = await axios.get('/api/admin/students-timetables/overview/student-timetable', {
                    params: {
                        student_code: this.publishedTimetableStudentCode,
                    },
                })
                const publishedTimetable = response?.data?.data || {}
                const savedState = publishedTimetable.state && typeof publishedTimetable.state === 'object'
                    ? publishedTimetable.state
                    : {}
                const transferredStudentContext = savedState.transferredStudentContext
                    || this.transferredStudentContext
                    || this.transferredStudentContextFromRobotStudent(this.selectedPublishedTimetableStudent)

                this.markStudentPublishedTimetable(publishedTimetable)
                this.runTimetableUpdate(() => {
                    this.applyTimetableState({
                        ...savedState,
                        transferredStudentContext,
                        manualPanelOpen: true,
                        manualPanelSource: 'direct',
                    })
                    this.wizardPanelOpen = false
                    this.wizardTimetableResultVisible = false
                    this.manualPanelOpen = true
                    this.manualPanelSource = 'direct'
                    this.navigateToTimetableOverviewMode?.('manual')
                    this.persistTimetableState()

                    if (this.transferredStudentContext?.student?.studentCode) {
                        this.loadTransferredStudentCompletedCourses(this.transferredStudentContext.student.studentCode, {
                            includeSelection: true,
                        })
                    }
                })
            } catch (error) {
                this.publishedTimetableReport = {
                    type: 'error',
                    message: error?.response?.data?.message || 'Gespeicherter Plan konnte nicht geladen werden.',
                }
            } finally {
                this.publishedTimetableLoading = false
            }
        },
        markStudentPublishedTimetable(publishedTimetable) {
            const studentCode = this.normalizedStudentCode(publishedTimetable?.student_code || this.publishedTimetableStudentCode)
            if (!studentCode) return

            this.robotStudents = (Array.isArray(this.robotStudents) ? this.robotStudents : []).map(student => {
                if (this.normalizedStudentCode(student?.student_code) !== studentCode) return student

                return {
                    ...student,
                    has_published_timetable: true,
                    published_timetable_id: publishedTimetable?.id || student.published_timetable_id || null,
                    published_timetable_at: publishedTimetable?.published_at || student.published_timetable_at || null,
                }
            })
        },
        publishedStudentTimetablePayload() {
            return {
                student_code: this.publishedTimetableStudentCode,
                student_label: this.publishedTimetableStudentName,
                timetable: this.timetablePdfPayload(),
                state: this.publishedStudentTimetableState(),
            }
        },
        publishedStudentTimetableState() {
            const state = this.currentTimetableState()
            const defaultState = this.defaultTimetableState()
            const recurrenceSemesterKeys = [
                ...Object.keys(defaultState.selectedRecurrenceWeeks || {}),
                ...Object.keys(state.selectedRecurrenceWeeks || {}),
            ]
            const selectedRecurrenceWeeks = Object.fromEntries(
                [...new Set(recurrenceSemesterKeys)].map(semester => [semester, ALL_DATES_OPTION_VALUE]),
            )
            const expandedRecurrenceWeeks = Object.fromEntries(
                [...new Set(recurrenceSemesterKeys)].map(semester => [semester, false]),
            )

            return {
                ...state,
                selectedRecurrenceWeeks,
                expandedRecurrenceWeeks,
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
                weeks: this.timetablePdfWeeksForSemester(semester)
                    .map(timetableWeek => this.timetablePdfWeekPayload(semester, timetableWeek)),
            }
        },
        timetablePdfWeeksForSemester(semester) {
            return [this.allDatesOption(semester.value)]
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
            if (courses.length > 1) {
                return this.timetablePdfCellHasDateTimeCollision(semester, weekday, hour, timetableWeek)
                    ? 'conflict'
                    : 'warning'
            }

            if (this.cellHasOverlap(semester, weekday, hour, timetableWeek)) return 'conflict'
            if (courses.length || markers.length) return 'filled'

            return 'empty'
        },
        timetablePdfCellHasDateTimeCollision(semester, weekday, hour, timetableWeek) {
            const displayCourseGroups = typeof this.displayCourseGroupsForCell === 'function'
                ? this.displayCourseGroupsForCell(semester, weekday, hour, timetableWeek)
                : this.courseGroupsForCell(semester, weekday, hour, timetableWeek)

            return displayCourseGroups.some((leftCourseGroup, leftIndex) => (
                displayCourseGroups.slice(leftIndex + 1).some((rightCourseGroup) => (
                    this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                        && this.courseGroupDatesHaveExplicitOverlap(leftCourseGroup, rightCourseGroup)
                        && !this.courseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup)
                ))
            ))
        },
        courseGroupDatesHaveExplicitOverlap(leftCourseGroup, rightCourseGroup) {
            const leftDates = this.courseGroupDates(leftCourseGroup)
            const rightDates = this.courseGroupDates(rightCourseGroup)

            if (!leftDates.length || !rightDates.length) return false

            return leftDates.some(date => rightDates.includes(date))
        },
        timetablePdfCoursePayload(courseGroup) {
            const studentCourseBadge = typeof this.courseGroupStudentCourseBadge === 'function'
                ? this.courseGroupStudentCourseBadge(courseGroup)
                : ''
            const studentCourseType = studentCourseBadge && typeof this.courseGroupStudentCourseType === 'function'
                ? this.courseGroupStudentCourseType(courseGroup)
                : ''

            return {
                label: this.courseGroupDisplayLabel(courseGroup),
                details: this.courseGroupDetailLabel(courseGroup),
                dates: this.courseGroupDates(courseGroup),
                is_fu: this.courseGroupDistanceLearning(courseGroup),
                student_course_type: studentCourseType,
                student_course_badge: studentCourseBadge,
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
            if (this.timetableContextLocked) return

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
            if (this.timetableContextLocked) return

            this.applyTransferredStudentSelection(this.studentSelectionDraft.studentCode)
        },
        clearTransferredStudentSelection() {
            if (this.timetableContextLocked) return
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

                this.transferredStudentContext = this.transferredStudentContextFromRobotStudent(selectedStudent)
                this.studentDialogOpen = false
                this.studentSearch = ''
                this.persistTimetableState()
                this.loadTransferredStudentCompletedCourses(nextStudentCode, { applySelectionDefaults: true })
            })
        },
        openSelectionDialog() {
            if (this.timetableContextLocked) return

            this.selectionDraft = { ...this.selection }
            this.selectionDialogOpen = true
        },
        closeSelectionDialog() {
            this.selectionDialogOpen = false
        },
        updateSelection() {
            if (this.timetableContextLocked) return

            this.runTimetableUpdate(() => {
                this.selection = this.normalizedSelection(this.selectionDraft)
                this.refreshTransferredStudentCourseHistory()

                if (!this.transferredStudentContext) {
                    this.persistTimetableState()
                }
            })
            this.selectionDialogOpen = false
        },
        deselectAllCourses() {
            this.runTimetableUpdate(() => {
                this.activeCourseGroupFilterKeys = []
                this.persistTimetableState()
            })
        },
        resetWizardPanel() {
            const wizardCourseCards = this.$refs.wizardCourseCards

            wizardCourseCards?.resetStudentCourseSelection?.()
            wizardCourseCards?.clearGeneratedTimetables?.()
            wizardCourseCards?.showCourseActionAfterCourseInteraction?.()
            this.wizardTimetableResultVisible = false
            this.activeCourseGroupFilterKeys = []
            this.activeDistanceLearningCourseGroupKeys = []
            this.removeSavedRobotTimetableState()
        },
        resetSavedTimetable() {
            this.runTimetableUpdate(() => {
                this.resetTimetablePanels()
                this.removeSavedTimetableState()
                this.removeSavedRobotTimetableState()
                this.applyTimetableState(this.defaultTimetableState())
            })
        },
        endAndRestartAdoptedTimetable() {
            this.runTimetableUpdate(() => {
                this.resetTimetablePanels()
                this.removeSavedRobotTimetableState()
                this.activeCourseGroupFilterKeys = []
                this.selectedRecurrenceWeeks = { ...this.defaultTimetableState().selectedRecurrenceWeeks }
                this.expandedRecurrenceWeeks = { ...this.defaultTimetableState().expandedRecurrenceWeeks }
                this.showExtraDatesInSelectedWeeks = { ...this.defaultTimetableState().showExtraDatesInSelectedWeeks }
                this.persistTimetableState()
                this.navigateToTimetableOverviewMode?.()
            })
        },
        resetTimetablePanels() {
            const wizardCourseCards = this.$refs.wizardCourseCards

            wizardCourseCards?.resetCourseSelection?.()
            wizardCourseCards?.resetAdditionalCourseSelection?.()
            wizardCourseCards?.clearGeneratedTimetables?.()
            this.wizardPanelOpen = false
            this.wizardPanelMounted = false
            this.wizardTimetableResultVisible = false
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
            this.activeDistanceLearningCourseGroupKeys = []
        },
        defaultTimetableState() {
            return {
                activeCourseGroupFilterKeys: [],
                activeDistanceLearningCourseGroupKeys: [],
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
            const defaultCourseRestrictionMode = typeof this.effectiveCourseChoiceRestrictionMode === 'string'
                ? this.effectiveCourseChoiceRestrictionMode
                : this.normalizedCourseChoiceRestrictionMode(this.courseChoiceRestrictionMode, {
                    restrictCourseChoiceBySelection: this.restrictCourseChoiceBySelection,
                })
            const courseRestrictionMode = mode === null
                ? defaultCourseRestrictionMode
                : this.normalizedCourseChoiceRestrictionMode(mode, {
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
                activeDistanceLearningCourseGroupKeys: [...new Set(this.activeDistanceLearningCourseGroupKeys || [])],
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
            }
        },
        applyTimetableState(state) {
            const defaults = this.defaultTimetableState()

            this.activeCourseGroupFilterKeys = Array.isArray(state?.activeCourseGroupFilterKeys)
                ? [...new Set(state.activeCourseGroupFilterKeys.filter(Boolean))]
                : defaults.activeCourseGroupFilterKeys
            this.activeDistanceLearningCourseGroupKeys = Array.isArray(state?.activeDistanceLearningCourseGroupKeys)
                ? [...new Set(state.activeDistanceLearningCourseGroupKeys.filter(Boolean))]
                : defaults.activeDistanceLearningCourseGroupKeys
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
            this.restrictCourseChoiceBySelection = ![
                COURSE_CHOICE_RESTRICTION_ALL,
                COURSE_CHOICE_RESTRICTION_ALL_AVAILABLE,
            ].includes(this.courseChoiceRestrictionMode)
            this.selection = this.normalizedSelection(state?.selection || defaults.selection)
            this.selectionDraft = { ...this.selection }
            this.transferredStudentContext = this.normalizedTransferredStudentContext(
                state?.transferredStudentContext ?? defaults.transferredStudentContext,
            )
        },
        async loadTransferredStudentCompletedCourses(studentCode, options = {}) {
            const normalizedStudentCode = this.normalizedStudentCode(studentCode)
            const requestId = this.studentCompletedCoursesRequestId + 1
            this.studentCompletedCoursesRequestId = requestId

            if (!normalizedStudentCode) {
                this.transferredStudentCoursesLoading = false

                return
            }

            this.transferredStudentCoursesLoading = true

            try {
                const response = await axios.get('/api/admin/students-timetables/robot/student-overview', {
                    params: {
                        student_code: normalizedStudentCode,
                        ...(options?.includeSelection ? { selection: this.studentOverviewSelectionPayload() } : {}),
                    },
                })

                if (requestId !== this.studentCompletedCoursesRequestId) return
                if (this.normalizedStudentCode(this.transferredStudentContext?.student?.studentCode) !== normalizedStudentCode) return

                const overviewSummary = response.data?.data || {}

                if (options?.applySelectionDefaults) {
                    this.applyTransferredStudentSelectionDefaultsFromOverview(overviewSummary)
                }

                this.transferredStudentContext = this.normalizedTransferredStudentContext({
                    ...this.transferredStudentContext,
                    student: {
                        ...this.transferredStudentContext?.student,
                        religion: overviewSummary?.student?.religion ?? this.transferredStudentContext?.student?.religion,
                    },
                    courses: this.overviewStudentCourseHistoryFromSummary(overviewSummary),
                })
                this.persistTimetableState()
            } catch {
                if (requestId !== this.studentCompletedCoursesRequestId) return
            } finally {
                if (requestId === this.studentCompletedCoursesRequestId) {
                    this.transferredStudentCoursesLoading = false
                }
            }
        },
        studentOverviewSelectionPayload() {
            return {
                semester: this.selection?.semester,
                religion: this.selection?.religion,
                language: this.selection?.language,
                branch: this.selection?.branch,
                artsSubject: this.selection?.artsSubject,
            }
        },
        applyTransferredStudentSelectionDefaultsFromOverview(overviewSummary) {
            const selection = overviewSummary?.selection || {}
            const completedCourseReligion = this.inferredSelectionOptionFromCourseCodes(
                this.religionOptions,
                this.studentVisitedCourseCodes(overviewSummary?.completed_courses || []),
            )
            const selectionDefaults = this.normalizedSelection({
                semester: selection.semester,
                religion: completedCourseReligion || selection.religion,
                language: selection.language,
                branch: selection.branch,
                artsSubject: selection.arts_subject,
            })

            if (JSON.stringify(selectionDefaults) === JSON.stringify(this.selection)) return

            this.selection = selectionDefaults
            this.selectionDraft = { ...selectionDefaults }
            this.selectedCourseMenuKey = ''
            this.selectedCourseGroup = null
        },
        overviewStudentCourseHistoryFromSummary(overviewSummary) {
            const automaticCourseSections = Array.isArray(overviewSummary?.automatic_course_selection?.sections)
                ? overviewSummary.automatic_course_selection.sections
                : []
            const automaticMissingCourses = automaticCourseSections.find(section => section?.key === 'missing')?.items
            const automaticPlannedCourses = automaticCourseSections.find(section => section?.key === 'proposed')?.items

            return {
                completed: this.overviewCompletedCourseItems(overviewSummary?.completed_courses || []),
                missing: this.overviewCourseItems(automaticMissingCourses || overviewSummary?.missing_courses || []),
                planned: this.overviewCourseItems(automaticPlannedCourses || overviewSummary?.proposed_courses || []),
                additional: this.overviewCourseItems(overviewSummary?.additional_courses || []),
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
                    || firstOption.optionIndex - secondOption.optionIndex
                    || secondOption.match.courseIndex - firstOption.match.courseIndex,
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
        normalizedStudentCode(value) {
            const studentCode = value === null || value === undefined ? '' : String(value).trim()

            return studentCode === '' ? null : studentCode
        },
        studentReligionMeta(religion) {
            const value = String(religion || '').trim()

            return value ? `Religion: ${value}` : ''
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
        transferredStudentContextFromRobotStudent(student) {
            if (!student) return null

            return this.normalizedTransferredStudentContext({
                student: {
                    studentCode: this.normalizedStudentCode(student.student_code),
                    label: this.studentOptionTitle(student),
                    semesterLabel: this.studentSemesterLabel(student),
                    religion: student.religion,
                    email: this.normalizedEmailValue(student.email),
                },
                courses: {
                    completed: [],
                    missing: [],
                    planned: [],
                    additional: [],
                },
            })
        },
        normalizedEmailValue(value) {
            return String(value || '').trim()
        },
        async copyTransferredStudentEmail() {
            const copied = await this.copyTextToClipboard(this.transferredStudentEmail)

            if (copied) {
                this.showTransferredStudentEmailCopied()
            }

            return copied
        },
        showTransferredStudentEmailCopied() {
            this.transferredStudentEmailCopied = true

            if (this.transferredStudentEmailCopiedTimeout) {
                window.clearTimeout(this.transferredStudentEmailCopiedTimeout)
            }

            this.transferredStudentEmailCopiedTimeout = window.setTimeout(() => {
                this.transferredStudentEmailCopied = false
                this.transferredStudentEmailCopiedTimeout = null
            }, 1800)
        },
        async copyTextToClipboard(value) {
            const text = String(value || '').trim()
            if (!text) return false

            if (typeof navigator !== 'undefined' && navigator?.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text)

                    return true
                } catch {
                    // Fall back to the textarea copy path below.
                }
            }

            if (typeof document === 'undefined') return false

            try {
                const textarea = document.createElement('textarea')
                textarea.value = text
                textarea.setAttribute('readonly', '')
                textarea.style.position = 'fixed'
                textarea.style.left = '-9999px'
                document.body.appendChild(textarea)
                textarea.select()
                textarea.setSelectionRange(0, text.length)
                const copied = document.execCommand('copy')
                document.body.removeChild(textarea)

                return copied
            } catch {
                return false
            }
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

            this.loadTransferredStudentCompletedCourses(this.transferredStudentContext.student.studentCode, {
                includeSelection: true,
                applySelectionDefaults: true,
            })
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
                .filter(course => this.completedCourseCountsAsDone(course?.grade ?? course?.meta))
                .flatMap(course => this.courseAliasesFromValues([
                    course?.subject,
                    course?.code,
                    course?.label,
                    course?.name,
                ]))
                .filter(Boolean))
        },
        studentVisitedCourseCodes(completedCourses) {
            return new Set((Array.isArray(completedCourses) ? completedCourses : [])
                .flatMap(course => this.courseAliasesFromValues([
                    course?.subject,
                    course?.code,
                    course?.label,
                    course?.name,
                ]))
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
                    religion: String(context.student?.religion || '').trim(),
                    email: String(context.student?.email || '').trim(),
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
            const label = [
                courseGroup?.course,
                courseGroup?.display_label,
                courseGroup?.title,
            ]
                .map(value => String(value || '').trim())
                .find(Boolean) || 'Einzeltermin'

            return normalizedCourseDisplayLabel(label)
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
        singleDateOverviewGroupsForSemester(semester) {
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])

            const singleDateCourseGroups = this.uniqueCourseGroupsByKey(this.configuredCourseGroups)
                .filter(courseGroup => activeCourseGroupFilterKeySet.has(courseGroup?.key))
                .filter(courseGroup => Number(courseGroup?.semester) === Number(semester))
                .filter(courseGroup => this.courseGroupIsSingleDate(courseGroup))

            return this.compactSingleDateOverviewCourseGroups(singleDateCourseGroups)
                .map(courseGroup => this.singleDateOverviewGroup(courseGroup))
                .sort((left, right) => left.sortValue.localeCompare(right.sortValue, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))
        },
        compactSingleDateOverviewCourseGroups(courseGroups) {
            return [...(Array.isArray(courseGroups) ? courseGroups : [])]
                .sort((left, right) => this.singleDateOverviewCourseGroupSortValue(left).localeCompare(
                    this.singleDateOverviewCourseGroupSortValue(right),
                    'de-AT',
                    { numeric: true, sensitivity: 'base' },
                ))
                .reduce((compactedCourseGroups, courseGroup) => {
                    const previousCourseGroup = compactedCourseGroups.at(-1)

                    if (this.singleDateOverviewCourseGroupsCanMerge(previousCourseGroup, courseGroup)) {
                        compactedCourseGroups[compactedCourseGroups.length - 1] = this.mergedSingleDateOverviewCourseGroup(
                            previousCourseGroup,
                            courseGroup,
                        )

                        return compactedCourseGroups
                    }

                    compactedCourseGroups.push(courseGroup)

                    return compactedCourseGroups
                }, [])
        },
        singleDateOverviewCourseGroupSortValue(courseGroup) {
            return [
                this.singleDateOverviewMergeSignature(courseGroup),
                String(Number(courseGroup?.hour) || '').padStart(2, '0'),
            ].join('|')
        },
        singleDateOverviewCourseGroupsCanMerge(leftCourseGroup, rightCourseGroup) {
            if (!leftCourseGroup || !rightCourseGroup) return false
            if (this.singleDateOverviewMergeSignature(leftCourseGroup) !== this.singleDateOverviewMergeSignature(rightCourseGroup)) return false

            return Number(this.singleDateOverviewEndHour(leftCourseGroup)) + 1 === Number(rightCourseGroup?.hour)
        },
        singleDateOverviewMergeSignature(courseGroup) {
            const dates = this.courseGroupDates(courseGroup)

            return [
                Number(courseGroup?.semester) || '',
                Number(courseGroup?.weekday) || '',
                dates.length === 1 ? dates[0] : '',
                this.sameSlotDateOverviewCourseTitle(courseGroup),
            ].join('|')
        },
        mergedSingleDateOverviewCourseGroup(leftCourseGroup, rightCourseGroup) {
            const rightTimeRange = this.courseGroupTimeRangeParts(rightCourseGroup)

            return {
                ...leftCourseGroup,
                key: [
                    leftCourseGroup?.key,
                    rightCourseGroup?.key,
                ].filter(Boolean).join('|'),
                end_hour: this.singleDateOverviewEndHour(rightCourseGroup),
                teacher: rightTimeRange.until || leftCourseGroup?.teacher,
            }
        },
        singleDateOverviewEndHour(courseGroup) {
            const startHour = Number(courseGroup?.hour)
            if (!Number.isFinite(startHour)) return startHour

            const explicitEndHour = [
                courseGroup?.end_hour,
                courseGroup?.last_hour,
                courseGroup?.until_hour,
            ]
                .map(value => Number(value))
                .find(value => Number.isFinite(value) && value >= startHour)
            const duration = [
                courseGroup?.hours_count,
                courseGroup?.duration_hours,
                courseGroup?.lessons_count,
            ]
                .map(value => Number(value))
                .find(value => Number.isFinite(value) && value > 1)

            return explicitEndHour || (duration ? startHour + duration - 1 : startHour)
        },
        singleDateOverviewGroup(courseGroup) {
            const dates = this.courseGroupDates(courseGroup)
            const title = this.sameSlotDateOverviewCourseTitle(courseGroup)

            return {
                key: courseGroup?.key || title,
                title,
                slotTitle: this.singleDateOverviewSlotTitle(courseGroup),
                dateLabels: dates.map(date => this.formatDateWithWeekdayLabel(date)),
                summary: dates.length === 1 ? this.singleDateOverviewSummary(courseGroup, dates[0], title) : '',
                courseGroup,
                sortValue: [
                    dates[0] || courseGroup?.first_date || courseGroup?.date || '9999-99-99',
                    String(Number(courseGroup?.weekday) || '').padStart(2, '0'),
                    String(Number(courseGroup?.hour) || '').padStart(2, '0'),
                    title,
                ].join('|'),
            }
        },
        singleDateOverviewSlotTitle(courseGroup) {
            const timeRange = this.courseGroupTimeRangeParts(courseGroup)

            return this.sameSlotDateOverviewSlotRangeTitle({
                weekday: Number(courseGroup?.weekday),
                startHour: Number(courseGroup?.hour),
                endHour: this.singleDateOverviewEndHour(courseGroup),
                from: timeRange.from,
                until: timeRange.until,
            })
        },
        singleDateOverviewSummary(courseGroup, date, title = null) {
            const dateLabel = this.singleDateOverviewDateLabel(date)
            const hourRangeLabel = this.singleDateOverviewHourRangeLabel(courseGroup)
            const timeRangeLabel = this.courseGroupTimeRangeParts(courseGroup).label.replace(/\s+-\s+/u, '-')
            const details = [
                dateLabel,
                hourRangeLabel,
                timeRangeLabel,
            ].filter(Boolean).join(', ')

            return `${title || this.sameSlotDateOverviewCourseTitle(courseGroup)}: ${details}`
        },
        singleDateOverviewDateLabel(date) {
            const weekdayLabel = this.weekdayLabelForDate(date)
            const compactDateLabel = this.formatCompactDateValue(date)

            return [weekdayLabel, compactDateLabel].filter(Boolean).join(', ')
        },
        singleDateOverviewHourRangeLabel(courseGroup) {
            const startHour = Number(courseGroup?.hour)
            if (!Number.isFinite(startHour)) return ''

            const endHour = this.singleDateOverviewEndHour(courseGroup)

            return startHour === endHour ? `${startHour}.` : `${startHour}.-${endHour}.`
        },
        sameSlotDateOverviewGroupsForSemester(semester, recurrenceWeek = null) {
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])

            const courseGroupsBySlot = (Array.isArray(this.configuredCourseGroups) ? this.configuredCourseGroups : [])
                .filter(courseGroup => activeCourseGroupFilterKeySet.has(courseGroup?.key))
                .filter(courseGroup => Number(courseGroup?.semester) === Number(semester))
                .filter(courseGroup => this.sameSlotDateOverviewCourseGroupMatchesView(courseGroup, semester, recurrenceWeek))
                .filter(courseGroup => !this.courseGroupIsSingleDate(courseGroup))
                .filter(courseGroup => this.courseGroupDates(courseGroup).length > 0)
                .reduce((groups, courseGroup) => {
                    const key = this.courseCellKey(semester, courseGroup?.weekday, courseGroup?.hour)
                    groups[key] ??= []
                    groups[key].push(courseGroup)

                    return groups
                }, {})

            const groups = Object.entries(courseGroupsBySlot)
                .map(([key, courseGroups]) => this.sameSlotDateOverviewGroup(key, courseGroups, semester, recurrenceWeek))
                .filter(Boolean)
                .sort((left, right) => left.sortValue.localeCompare(right.sortValue, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))

            return this.compactSameSlotDateOverviewGroups(groups)
        },
        sameSlotDateOverviewCourseGroupMatchesView(courseGroup, semester, recurrenceWeek = null) {
            if (recurrenceWeek?.type === 'recurrence') {
                return !this.courseGroupHasExtraDateWeek(courseGroup)
                    || this.shouldIncludeExtraDatesInRegularWeek(semester)
            }

            return this.courseGroupMatchesSelectedRecurrenceWeek(courseGroup, semester, recurrenceWeek)
        },
        sameSlotDateOverviewGroup(key, courseGroups, semester = null, recurrenceWeek = null) {
            const uniqueCourseGroups = this.uniqueCourseGroupsByKey(courseGroups)

            if (uniqueCourseGroups.length < 2) return null

            const firstCourseGroup = uniqueCourseGroups[0] || {}
            const weekday = Number(firstCourseGroup?.weekday)
            const hour = Number(firstCourseGroup?.hour)
            const timeRange = this.courseGroupTimeRangeParts(firstCourseGroup)
            const courses = uniqueCourseGroups
                .map(courseGroup => this.sameSlotDateOverviewCourse(courseGroup, semester, recurrenceWeek))
                .filter(course => course.dateLabels.length > 0)
                .sort((left, right) => left.title.localeCompare(right.title, 'de-AT', {
                    numeric: true,
                    sensitivity: 'base',
                }))
            if (!courses.length) return null

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
        sameSlotDateOverviewCourse(courseGroup, semester = null, recurrenceWeek = null) {
            const dates = this.sameSlotDateOverviewCourseDates(courseGroup, semester, recurrenceWeek)
            const title = this.sameSlotDateOverviewCourseTitle(courseGroup)

            return {
                key: courseGroup?.key || title,
                title,
                dateRangeLabel: this.courseGroupDateRangeLabelForDates(courseGroup, dates),
                dateLabels: dates.map(date => this.formatDateWithWeekdayLabel(date)),
            }
        },
        sameSlotDateOverviewCourseDates(courseGroup, semester = null, recurrenceWeek = null) {
            const dates = this.courseGroupDates(courseGroup)
            const hasNumericRecurrenceWeek = recurrenceWeek !== null
                && recurrenceWeek !== undefined
                && Number.isFinite(Number(recurrenceWeek))
            if (recurrenceWeek?.type !== 'recurrence' && !hasNumericRecurrenceWeek) {
                return dates
            }

            const targetWeek = recurrenceWeek?.type === 'recurrence'
                ? Number(recurrenceWeek.value)
                : Number(recurrenceWeek)
            if (!Number.isFinite(targetWeek) || !Number.isFinite(Number(semester))) {
                return dates
            }

            const maxWeek = Math.max(this.recurrenceWeekOptions(semester).length, targetWeek)
            const semesterStart = this.semesterStartDate(semester)
            if (!Number.isFinite(maxWeek) || maxWeek <= 1 || !semesterStart) {
                return dates
            }

            return dates.filter(date => this.dateBelongsToRecurrenceWeek(date, semesterStart, targetWeek, maxWeek))
        },
        dateBelongsToRecurrenceWeek(date, semesterStart, targetWeek, maxWeek) {
            const parsedDate = this.dateFromIsoValue(date)
            if (!parsedDate || !semesterStart) return true

            const dayDifference = Math.floor((parsedDate.getTime() - semesterStart.getTime()) / 86400000)
            const weekOffset = Math.floor(dayDifference / 7)
            const week = ((weekOffset % maxWeek) + maxWeek) % maxWeek + 1

            return week === targetWeek
        },
        courseGroupDateRangeLabelForDates(courseGroup, dates) {
            const selectedDates = Array.isArray(dates)
                ? [...dates].filter(Boolean).sort()
                : []

            if (!selectedDates.length) {
                return ''
            }

            return this.courseGroupDateRangeLabel({
                ...courseGroup,
                first_date: selectedDates[0],
                last_date: selectedDates[selectedDates.length - 1],
                dates: selectedDates,
            })
        },
        sameSlotDateOverviewCourseTitle(courseGroup) {
            const label = [
                courseGroup?.display_label,
                courseGroup?.title,
                courseGroup?.course,
            ]
                .map(value => String(value || '').trim())
                .find(Boolean) || 'Ohne Bezeichnung'

            return normalizedCourseDisplayLabel(label)
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
            return normalizedCourseDisplayLabel((
                courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || ''
            ).toString())
        },
        semesterCourseMenus(semester) {
            if (this.semesterCourseMenusBySemester) {
                return this.semesterCourseMenusBySemester[Number(semester)] || []
            }

            return this.buildSemesterCourseMenus(semester)
        },
        openCourseMenuDialog() {
            this.selectedCourseMenuKey = ''
            this.courseChoiceRestrictionMode = COURSE_CHOICE_RESTRICTION_PLANNED
            this.restrictCourseChoiceBySelection = true
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
                if (!this.courseGroupMatchesSelectedReligionChoice(courseGroup, courseRestrictionContext)) return false
                if (!this.courseGroupMatchesSelectedLanguageChoice(courseGroup, courseRestrictionContext)) return false

                return this.courseGroupMatchesRestrictedCourseCodes(courseGroupCodes, studentCourseCodes, courseGroup, {
                    allowBaseModuleMatch: false,
                    shouldRestrictByTimetableSemester,
                })
            }

            if (courseRestrictionContext.mode === COURSE_CHOICE_RESTRICTION_ADDITIONAL) return false

            if (
                shouldRestrictByTimetableSemester
                && !this.courseGroupMatchesSelectedTimetableSemester(courseGroup)
            ) return false
            if (!this.courseGroupMatchesSelectedChoiceOptions(courseGroup, courseRestrictionContext)) return false

            if (!studentCourseCodes.size) return false

            return this.courseGroupMatchesRestrictedCourseCodes(courseGroupCodes, studentCourseCodes)
        },
        courseChoiceRestrictionItemsFromCourses(courses = {}, mode = COURSE_CHOICE_RESTRICTION_PLANNED) {
            if ([COURSE_CHOICE_RESTRICTION_ALL, COURSE_CHOICE_RESTRICTION_ALL_AVAILABLE].includes(mode)) return []

            if (mode === COURSE_CHOICE_RESTRICTION_MISSING) {
                return Array.isArray(courses?.missing) ? courses.missing : []
            }

            const plannedCourses = Array.isArray(courses?.planned) ? courses.planned : []
            if (mode === COURSE_CHOICE_RESTRICTION_PLANNED) return plannedCourses
            if (mode === COURSE_CHOICE_RESTRICTION_ADDITIONAL) {
                return Array.isArray(courses?.additional) ? courses.additional : []
            }

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
            if (normalizedCourseGroupCodes.some(courseCode => restrictedCourseCodes.has(courseCode))) return true
            if (options?.allowBaseModuleMatch === false) return false

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
        adoptedAdditionalCourseChoiceCourseGroups() {
            const selectedCourseGroupKeys = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(Array.isArray(this.activeCourseGroupFilterKeys) ? this.activeCourseGroupFilterKeys : [])
            if (!selectedCourseGroupKeys.size) return []

            const regularCourseCodes = this.adoptedRegularStudentCourseCodes()

            return (Array.isArray(this.availableCourseChoiceCourseGroups) ? this.availableCourseChoiceCourseGroups : [])
                .filter(courseGroup => selectedCourseGroupKeys.has(courseGroup?.key))
                .filter(courseGroup => !this.courseGroupMatchesRestrictedCourseCodes(
                    this.courseGroupCodes(courseGroup),
                    regularCourseCodes,
                    courseGroup,
                    { shouldRestrictByTimetableSemester: false },
                ))
        },
        adoptedRegularStudentCourseCodes() {
            const courses = this.transferredStudentContext?.courses || {}
            const regularCourses = [
                ...(Array.isArray(courses?.missing) ? courses.missing : []),
                ...(Array.isArray(courses?.planned) ? courses.planned : []),
            ]

            return new Set(regularCourses
                .flatMap(course => this.courseAliasesFromValues([
                    course?.code,
                    course?.label,
                    course?.name,
                ]))
                .filter(Boolean))
        },
        additionalCourseChoiceFallbackGroups() {
            const courses = this.transferredStudentContext?.courses || this.noStudentCourseHistory
            const regularCourses = [
                ...(Array.isArray(courses?.missing) ? courses.missing : []),
                ...(Array.isArray(courses?.planned) ? courses.planned : []),
            ]
            const regularCourseCodes = new Set(regularCourses
                .flatMap(course => this.courseAliasesFromValues([
                    course?.code,
                    course?.label,
                    course?.name,
                ]))
                .filter(Boolean))

            if (!regularCourseCodes.size) return this.configuredCourseGroups

            return (Array.isArray(this.availableCourseChoiceCourseGroups) ? this.availableCourseChoiceCourseGroups : [])
                .filter(courseGroup => !this.courseGroupMatchesRestrictedCourseCodes(
                    this.courseGroupCodes(courseGroup),
                    regularCourseCodes,
                    courseGroup,
                    { shouldRestrictByTimetableSemester: false },
                ))
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
        courseGroupMatchesGeneralStudentTimetable(courseGroup) {
            if (!this.transferredStudentContext) return true

            const subjectRows = Array.isArray(this.subjectRows) ? this.subjectRows : []
            if (!subjectRows.length) return true

            const courseBases = this.courseGroupChoiceOptionCodes(courseGroup)
                .map(courseCode => this.courseCodeWithoutModule(courseCode))
                .filter(Boolean)

            if (!courseBases.length) return true

            return courseBases.some(courseBase => this.courseBaseEligibleForStudentAdditional(courseBase))
        },
        courseGroupMatchesSelectedLanguageChoice(courseGroup, context = null) {
            if (this.normalizedCourseCode(this.selection?.language) !== 'L') return true

            const courseBases = this.courseGroupChoiceOptionCodes(courseGroup)
                .map(courseCode => this.courseCodeWithoutModule(courseCode))
                .filter(Boolean)
            const courseRestrictionContext = context || {}

            return this.courseBasesMatchSelectedOption(
                courseBases,
                courseRestrictionContext.languageCourseBases || this.languageCourseBases(),
                this.selection?.language,
            )
        },
        courseGroupMatchesSelectedReligionChoice(courseGroup, context = null) {
            const courseBases = this.courseGroupChoiceOptionCodes(courseGroup)
                .map(courseCode => this.courseCodeWithoutModule(courseCode))
                .filter(Boolean)
            const courseRestrictionContext = context || {}

            return this.courseBasesMatchSelectedOption(
                courseBases,
                courseRestrictionContext.religionCourseBases || this.religionCourseBases(),
                this.selection?.religion,
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
                .map((entry) => {
                    const studentCourseType = typeof this.courseMenuEntryStudentCourseType === 'function'
                        ? this.courseMenuEntryStudentCourseType(entry)
                        : ''
                    const studentCourseBadge = typeof this.studentCourseTypeBadge === 'function'
                        ? this.studentCourseTypeBadge(studentCourseType)
                        : ''

                    return {
                        key: `selected-${entry.key}`,
                        label: entry.scheduleLabel ? `${entry.label} · ${entry.scheduleLabel}` : entry.label,
                        hasBlockingOverlap: this.courseMenuEntryHasBlockingOverlap(entry, semester),
                        hasRelatedOverlap: this.courseMenuEntryHasRelatedOverlap(entry, semester),
                        hasOverlap: this.courseMenuEntryHasOverlap(entry, semester),
                        studentCourseType,
                        studentCourseBadge,
                        entry,
                    }
                })
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
            return normalizedCourseDisplayLabel((
                courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || 'Ohne Bezeichnung'
            ).toString())
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
            const explicitInterval = Number(courseGroup?.recurrence_interval)
            if ([1, 2, 3, 4].includes(explicitInterval)) return true

            const labelMatch = String(courseGroup?.recurrence_label || '').match(/(\d+)\s*-\s*w/iu)
            if (labelMatch) return [1, 2, 3, 4].includes(Number(labelMatch[1]))

            return false
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
            const explicitInterval = Number(courseGroup?.recurrence_interval)
            if ([1, 2, 3, 4].includes(explicitInterval)) return false

            const labelMatch = String(courseGroup?.recurrence_label || '').match(/(\d+)\s*-\s*w/iu)
            if (labelMatch) return ![1, 2, 3, 4].includes(Number(labelMatch[1]))

            return true
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
            const directCourseGroups = this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)
            if (directCourseGroups.some((courseGroup) => this.courseGroupHasOverlap(courseGroup))) {
                return true
            }

            const displayCourseGroups = typeof this.displayCourseGroupsForCell === 'function'
                ? this.displayCourseGroupsForCell(semester, weekday, hour, recurrenceWeek)
                : directCourseGroups

            return displayCourseGroups.some((leftCourseGroup, leftIndex) => (
                displayCourseGroups.slice(leftIndex + 1).some((rightCourseGroup) => (
                    this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                        && this.courseGroupDatesOverlap(leftCourseGroup, rightCourseGroup)
                        && !this.courseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup)
                ))
            ))
        },
        cellHasMultipleDisplayCourses(semester, weekday, hour, recurrenceWeek = null) {
            return this.displayCourseGroupsForCell(semester, weekday, hour, recurrenceWeek).length > 1
        },
        cellShouldShowWarning(semester, weekday, hour, recurrenceWeek = null) {
            return this.cellHasOverlap(semester, weekday, hour, recurrenceWeek)
                || (
                    typeof this.cellHasAggregateTimeOverlap === 'function'
                    && this.cellHasAggregateTimeOverlap(semester, weekday, hour, recurrenceWeek)
                )
                || (this.adoptedTimetableOverviewActive && this.cellHasMultipleDisplayCourses(semester, weekday, hour, recurrenceWeek))
        },
        cellHasAggregateTimeOverlap(semester, weekday, hour, recurrenceWeek = null) {
            if (recurrenceWeek?.type !== 'all_dates') return false

            const displayCourseGroups = typeof this.displayCourseGroupsForCell === 'function'
                ? this.displayCourseGroupsForCell(semester, weekday, hour, recurrenceWeek)
                : this.courseGroupsForCell(semester, weekday, hour, recurrenceWeek)

            return displayCourseGroups.some((leftCourseGroup, leftIndex) => (
                displayCourseGroups.slice(leftIndex + 1).some((rightCourseGroup) => (
                    this.courseGroupsOverlap(leftCourseGroup, rightCourseGroup)
                        && !this.courseGroupOverlapIsSingleDateOnly(leftCourseGroup, rightCourseGroup)
                ))
            ))
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
            const sourceFromGroup = this.courseGroupCourseSource(courseGroup)
            const sourceFirstSegment = sourceFromGroup.split(/\s+-\s+/u)[0]?.trim() || ''
            const preferredSource = [
                courseGroup?.display_label,
                courseGroup?.title,
                courseGroup?.module_code,
                courseGroup?.moduleCode,
            ]
                .map(value => String(value || '').trim())
                .find((value) => {
                    const firstSegment = value.split(/\s+-\s+/u)[0]?.trim() || ''

                    return /^[^\d\s-]+\d+/u.test(firstSegment)
                })
            const source = /^[^\d\s-]+\d+/u.test(sourceFirstSegment)
                ? sourceFromGroup
                : preferredSource || sourceFromGroup
            if (!source) return ''

            const firstSegment = source.split(/\s+-\s+/u)[0]?.trim() || ''
            if (!firstSegment || this.isTimeOnlyValue(firstSegment)) return ''

            const match = firstSegment.match(/^([^\d\s-]+)\d*/u)

            return normalizedCourseDisplayLabel((match?.[1] || firstSegment).trim())
        },
        courseGroupDisplayLabel(courseGroup) {
            const label = (
                courseGroup?.display_label
                || courseGroup?.title
                || courseGroup?.course
                || courseGroup?.subject
                || ''
            ).toString()

            return normalizedCourseDisplayLabel(label)
        },
        courseGroupDetailLabel(courseGroup) {
            return [
                this.courseGroupDistanceLearning(courseGroup) ? 'FU' : '',
                courseGroup?.recurrence_label,
                courseGroup?.is_block ? this.courseGroupBlockLabel(courseGroup) : '',
            ].filter(Boolean).join(' · ')
        },
        courseMenuEntryStudentCourseType(entry) {
            return (Array.isArray(entry?.courseGroups) ? entry.courseGroups : [])
                .map(courseGroup => this.courseGroupStudentCourseType(courseGroup))
                .find(Boolean) || ''
        },
        courseGroupStudentCourseType(courseGroup) {
            const courseGroupCodes = this.courseGroupCodes(courseGroup)
            if (!courseGroupCodes.length) return ''

            return ['missing', 'planned', 'additional']
                .find(type => this.transferredStudentCoursesForType(type)
                    .some(course => this.studentCourseItemMatchesCourseGroup(course, courseGroupCodes))) || ''
        },
        transferredStudentCoursesForType(type) {
            const courses = this.transferredStudentContext?.courses || {}
            const courseItems = courses?.[type]

            return Array.isArray(courseItems) ? courseItems : []
        },
        studentCourseItemMatchesCourseGroup(course, courseGroupCodes) {
            const courseCodes = this.courseAliasesFromValues([
                course?.code,
                course?.label,
                course?.name,
                course?.subject,
                course?.ttCode,
                ...(Array.isArray(course?.ttCodes) ? course.ttCodes : []),
            ])

            return courseCodes.some(courseCode => courseGroupCodes.includes(courseCode))
        },
        courseGroupStudentCourseBadge(courseGroup) {
            return this.studentCourseTypeBadge(this.courseGroupStudentCourseType(courseGroup))
        },
        studentCourseTypeBadge(type) {
            if (type === 'missing') return 'Fehlend'
            if (type === 'additional') return 'Zusätzlich'

            return ''
        },
        courseGroupDistanceLearning(courseGroup) {
            if (
                courseGroup?.is_fu === true
                || courseGroup?.is_distance_learning === true
                || courseGroup?.distance_learning === true
            ) return true

            const activeDistanceLearningCourseGroupKeySet = this.activeDistanceLearningCourseGroupKeySet instanceof Set
                ? this.activeDistanceLearningCourseGroupKeySet
                : new Set(this.activeDistanceLearningCourseGroupKeys || [])

            if (activeDistanceLearningCourseGroupKeySet.has(courseGroup?.key)) return true

            const course = this.courseForCourseGroup(courseGroup)
            const optionLabel = this.courseGroupOptionLabel(courseGroup)
            const activeCourseGroupFilterKeySet = this.activeCourseGroupFilterKeySet instanceof Set
                ? this.activeCourseGroupFilterKeySet
                : new Set(this.activeCourseGroupFilterKeys || [])
            const matchingCourseGroups = this.configuredCourseGroups
                .filter(candidate => this.courseGroupMatchesCourse(candidate, course, courseGroup))
                .filter(candidate => this.courseGroupOptionLabel(candidate) === optionLabel)
                .filter(candidate => activeCourseGroupFilterKeySet.has(candidate?.key))

            return this.courseGroupsAreDistanceLearningCourse(course, matchingCourseGroups, courseGroup)
        },
        courseForCourseGroup(courseGroup) {
            return this.courseCandidatesForCourseGroup(courseGroup)
                .find(course => this.courseGroupMatchesCourse(courseGroup, course)) || null
        },
        courseCandidatesForCourseGroup(courseGroup) {
            const selectedSemester = Number(this.selection?.semester || 0)
            const selectedSemesterCourses = Number.isFinite(selectedSemester) && selectedSemester > 0
                ? this.coursesForSemester(selectedSemester)
                : []
            const transferredCourses = this.transferredStudentCourseRestrictionItems
                .map(course => this.courseFromTransferredCourseItem(course))
                .filter(Boolean)

            return [
                ...selectedSemesterCourses,
                ...transferredCourses,
            ].filter((course, index, courses) =>
                courses.findIndex(candidate => candidate.key === course.key) === index,
            )
        },
        courseFromTransferredCourseItem(course) {
            const code = String(course?.code || course?.label || '').trim()
            if (!code) return null

            const hoursMatch = String(course?.meta || '').match(/(\d+(?:[,.]\d+)?)\s*Std/iu)
            const hours = hoursMatch ? Number(hoursMatch[1].replace(',', '.')) : 0

            return {
                key: course?.key || code,
                code,
                name: course?.name || course?.label || '',
                hours,
            }
        },
        courseGroupMatchesCourse(courseGroup, course, fallbackCourseGroup = null) {
            const courseAliases = this.courseCodeAliases(course)
            const courseGroupAliases = this.courseGroupChoiceOptionCodes(courseGroup)

            if (courseAliases.length && courseGroupAliases.some(alias => courseAliases.includes(alias))) {
                return true
            }

            if (!fallbackCourseGroup) return false

            const fallbackAliases = this.courseGroupChoiceOptionCodes(fallbackCourseGroup)

            return fallbackAliases.length
                && courseGroupAliases.some(alias => fallbackAliases.includes(alias))
        },
        courseGroupsAreDistanceLearningCourse(course, courseGroups, courseGroup = null) {
            const requiredSlotCount = this.requiredSlotCountForCourse(course, courseGroup)
            const scheduledWeeklyLoad = this.courseGroupsScheduledWeeklyLoad(
                (Array.isArray(courseGroups) ? courseGroups : [])
                    .filter(candidate => !this.courseGroupIsSingleDate(candidate)),
            )

            return requiredSlotCount >= 2
                && scheduledWeeklyLoad > 0
                && Math.abs((scheduledWeeklyLoad * 2) - requiredSlotCount) < 0.001
        },
        requiredSlotCountForCourse(course, courseGroup = null) {
            const courseHours = Math.round(Number(course?.hours || 0) || 0)
            if (courseHours > 0) return courseHours

            return this.maximumWeeklyLoadForCourseGroup(courseGroup)
        },
        maximumWeeklyLoadForCourseGroup(courseGroup) {
            if (!courseGroup) return 1

            const optionLoads = this.configuredCourseGroups
                .filter(candidate => this.courseGroupMatchesCourse(candidate, courseGroup))
                .reduce((loads, candidate) => {
                    const optionLabel = this.courseGroupOptionLabel(candidate)

                    loads[optionLabel] ??= []
                    loads[optionLabel].push(candidate)

                    return loads
                }, {})

            return Math.max(
                1,
                ...Object.values(optionLoads)
                    .map(courseGroups => Math.round(this.courseGroupsScheduledWeeklyLoad(courseGroups))),
            )
        },
        courseGroupsScheduledWeeklyLoad(courseGroups) {
            return this.uniqueCourseGroupsBySlot(courseGroups)
                .reduce((total, courseGroup) => total + this.courseGroupWeeklySlotLoad(courseGroup), 0)
        },
        uniqueCourseGroupsBySlot(courseGroups) {
            return Object.values((Array.isArray(courseGroups) ? courseGroups : []).reduce((groups, courseGroup) => {
                const key = this.courseCellKey(courseGroup?.semester, courseGroup?.weekday, courseGroup?.hour)

                groups[key] ??= courseGroup

                return groups
            }, {}))
        },
        courseGroupWeeklySlotLoad(courseGroup) {
            const interval = this.courseGroupWeekInterval(courseGroup)

            return interval && interval > 0 ? 1 / interval : 1
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
        normalizedCourseDisplayLabel(label) {
            return normalizedCourseDisplayLabel(label)
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

.overview-screen-loading-blocker {
    position: fixed;
    inset: 0;
    z-index: 2390;
    background: rgba(255, 255, 255, 0.28);
    cursor: progress;
}

.overview-screen-loading {
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 2400;
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

.overview-screen-loading__dots {
    padding: 0;
}

.overview-screen-loading__dots :deep(.loading-animation) {
    padding: 0;
}

.overview-screen-loading__dots :deep(.loading-dots) {
    gap: 6px;
}

.overview-screen-loading__dots :deep(.dot) {
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

.overview-selected-card__meta {
    margin-bottom: 3px;
    color: #64748b;
    font-size: 0.64rem;
    font-weight: 850;
    line-height: 1.1;
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
    justify-content: flex-end;
    gap: 10px;
    min-width: 0;
}

.overview-wizard-button,
.overview-manual-button,
.overview-saved-timetable-button {
    flex: 0 0 auto;
    min-height: 48px;
    border-radius: 8px;
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 850;
    letter-spacing: 0;
    text-transform: none;
}

.overview-wizard-button {
    background: linear-gradient(135deg, #1d4ed8 0%, #6366f1 100%);
    animation: wizard-glow 2s ease-in-out infinite;
    box-shadow: 0 0 8px rgba(37, 99, 235, 0.4), 0 0 20px rgba(37, 99, 235, 0.2);
}

.overview-wizard-button:hover {
    animation: none;
    box-shadow: 0 0 12px rgba(37, 99, 235, 0.6), 0 0 28px rgba(37, 99, 235, 0.35);
}

.overview-manual-button {
    background: linear-gradient(135deg, #0f766e 0%, #16a34a 100%);
    box-shadow: 0 0 8px rgba(15, 118, 110, 0.28), 0 0 18px rgba(22, 163, 74, 0.16);
}

.overview-manual-button:hover {
    box-shadow: 0 0 12px rgba(15, 118, 110, 0.42), 0 0 24px rgba(22, 163, 74, 0.25);
}

.overview-saved-timetable-button {
    background: linear-gradient(135deg, #7c3aed 0%, #db2777 100%);
    box-shadow: 0 0 8px rgba(124, 58, 237, 0.28), 0 0 18px rgba(219, 39, 119, 0.16);
}

.overview-saved-timetable-button:hover {
    box-shadow: 0 0 12px rgba(124, 58, 237, 0.42), 0 0 24px rgba(219, 39, 119, 0.25);
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
    background: linear-gradient(135deg, #0f766e 0%, #16a34a 100%);
    color: #fff;
    box-shadow: 0 4px 16px rgba(15, 118, 110, 0.32), 0 0 24px rgba(22, 163, 74, 0.16);
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

.overview-wizard-close-button--calm {
    font-weight: 400;
}

.overview-wizard-back-button {
    color: rgba(var(--v-theme-on-surface), 0.78);
}

.overview-wizard-pdf-button {
    min-width: 96px;
}

.overview-student-save-action {
    display: grid;
    gap: 6px;
    flex: 0 1 240px;
    min-width: 160px;
    max-width: 280px;
}

.overview-student-save-button {
    width: 100%;
    min-width: 160px;
}

.overview-student-save-button :deep(.v-btn__content) {
    min-width: 0;
}

.overview-student-save-button__label {
    display: grid;
    gap: 1px;
    min-width: 0;
    line-height: 1.05;
    text-align: left;
}

.overview-student-save-button__label span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.overview-published-timetable-report {
    font-size: 0.78rem;
    line-height: 1.15;
}

.overview-wizard-cancel-button {
    margin-right: auto;
}

.overview-manual-back-button {
    margin-left: 0;
}

.overview-automatic-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 2px 0 12px;
    padding: 10px 14px;
    border-radius: 8px;
    background: linear-gradient(135deg, #1d4ed8 0%, #6366f1 100%);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
}

.overview-automatic-heading--manual {
    background: linear-gradient(135deg, #0f766e 0%, #16a34a 100%);
    box-shadow: 0 4px 16px rgba(15, 118, 110, 0.25);
}

.overview-automatic-heading h2 {
    flex: 0 1 auto;
    margin: 0;
    font-size: 1rem;
    font-weight: 850;
    letter-spacing: 0;
}

.overview-wizard-settings-summary {
    display: grid;
    gap: 10px;
    min-width: 0;
    margin-bottom: 14px;
    padding: 12px 14px;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
    border-radius: 8px;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(239, 246, 255, 0.86)),
        radial-gradient(circle at 100% 0%, rgba(99, 102, 241, 0.14), transparent 34%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.68);
}

.overview-wizard-settings-summary__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
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
    font-size: 0.86rem;
    font-weight: 850;
}

.overview-wizard-settings-summary__list {
    gap: 6px;
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
    margin-left: auto;
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
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
    margin: -6px 0 16px;
}

.overview-wizard-footer-back-button {
    margin-left: auto;
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

.overview-wizard-loading--creating {
    margin-right: auto;
    color: #166534;
}

.overview-wizard-screen-loading {
    position: fixed;
    inset: 0;
    z-index: 2400;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    pointer-events: none;
}

.overview-wizard-screen-loading::before {
    content: "";
    position: absolute;
    width: 240px;
    height: 92px;
    border: 1px solid rgba(22, 101, 52, 0.16);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.88);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.16);
}

.overview-wizard-screen-loading__dots {
    position: relative;
    z-index: 1;
    display: flex;
    gap: 10px;
}

.overview-wizard-screen-loading__dot {
    position: relative;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #16a34a;
    animation: overview-wizard-dot-pulse 0.9s ease-in-out infinite;
}

.overview-wizard-screen-loading__dot:nth-child(2) {
    animation-delay: 0.14s;
}

.overview-wizard-screen-loading__dot:nth-child(3) {
    animation-delay: 0.28s;
}

.overview-wizard-screen-loading__label {
    position: relative;
    z-index: 1;
    color: #166534;
    font-size: 0.82rem;
    font-weight: 800;
}

@keyframes overview-wizard-dot-pulse {
    0%,
    80%,
    100% {
        opacity: 0.35;
        transform: translateY(0) scale(0.82);
    }

    40% {
        opacity: 1;
        transform: translateY(-6px) scale(1);
    }
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

.course-item-chip--disabled {
    cursor: not-allowed;
    opacity: 0.82;
}

.course-item-chip--disabled :deep(.v-chip__content) {
    pointer-events: none;
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
    min-width: 0;
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

.selected-course-filter-chip__source {
    margin-left: 6px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 850;
    line-height: 1.25;
}

.selected-course-filter-chip__source--missing {
    background: rgba(251, 146, 60, 0.24);
    color: #9a3412;
}

.selected-course-filter-chip__source--additional {
    background: rgba(14, 165, 233, 0.22);
    color: #0369a1;
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
    margin-bottom: 0;
    padding: 10px;
    border: 1px solid rgba(14, 165, 233, 0.22);
    border-radius: 8px;
    background: #f0f9ff;
}

.transferred-student-context__header,
.transferred-student-context__title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.transferred-student-context__header {
    justify-content: space-between;
    flex-wrap: wrap;
}

.transferred-student-context__title {
    min-width: 0;
    flex-wrap: wrap;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 800;
}

.transferred-student-context__email {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    gap: 5px;
    padding: 3px 8px;
    border: 1px solid rgba(37, 99, 235, 0.22);
    border-radius: 999px;
    background: rgba(219, 234, 254, 0.78);
    color: #1d4ed8;
    cursor: pointer;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 800;
}

.transferred-student-context__email span {
    overflow: hidden;
    min-width: 0;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.transferred-student-context__email:hover {
    background: rgba(191, 219, 254, 0.95);
}

.transferred-student-context__email-copy-icon--copied {
    color: #16a34a;
}

.transferred-student-completed-card {
    overflow: hidden;
    border: 1px solid rgba(0, 137, 123, 0.18);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.9);
}

.transferred-student-completed-card__header {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 48px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(0, 137, 123, 0.12);
}

.transferred-student-completed-card__header h3 {
    flex: 1;
    margin: 0;
    color: #10263a;
    font-size: 0.96rem;
    font-weight: 500;
}

.transferred-student-completed-card__chips {
    padding: 12px 14px;
}

.transferred-student-course-overview {
    display: grid;
    margin-bottom: 14px;
}

.manual-course-overview {
    display: grid;
    margin: -4px 0 16px;
}

.manual-course-overview__panels :deep(.v-expansion-panel) {
    overflow: hidden;
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: rgba(255, 255, 255, 0.96) !important;
}

.manual-course-overview__title {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 58px;
    padding: 14px;
}

.manual-course-overview__title h3 {
    flex: 1;
    margin: 0;
    color: #10263a;
    font-size: 1rem;
    font-weight: 500;
}

.manual-course-overview__panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 14px 14px;
}

.manual-course-overview__course-panels {
    margin-top: 0;
}

.manual-course-overview__course-panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.transferred-student-course-panels {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px 16px;
    align-items: start;
}

@media (min-width: 900px) {
    .transferred-student-course-panels {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1200px) {
    .transferred-student-course-overview .transferred-student-course-panels {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.transferred-student-course-panels :deep(.v-expansion-panel) {
    margin-top: 0 !important;
    overflow: hidden;
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px !important;
    background: rgba(255, 255, 255, 0.92) !important;
}

.transferred-student-course-panel {
    overflow: hidden;
    border: 1px solid rgba(16, 38, 58, 0.08);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.92);
}

.transferred-student-course-panel__title {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 64px;
    padding: 16px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.07);
}

.transferred-student-course-panel__title h3 {
    flex: 1;
    margin: 0;
    color: #10263a;
    font-size: 1.08rem;
    font-weight: 500;
}

.transferred-student-course-panels :deep(.v-expansion-panel-text__wrapper) {
    padding: 0;
}

.transferred-student-course-section__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
    padding: 14px 16px;
}

.transferred-student-course-chip {
    max-width: 100%;
    min-height: 24px;
    font-size: 0.82rem;
    font-weight: 500;
}

.transferred-student-course-chip__meta {
    margin-left: 6px;
    opacity: 0.78;
}

.transferred-student-course-section__empty {
    padding: 16px;
    color: rgba(var(--v-theme-on-surface), 0.54);
    font-size: 0.86rem;
    font-weight: 500;
}

.recurrence-week-selector {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 6px;
    padding: 0 0 8px;
}

.recurrence-week-selector :deep(.v-btn-toggle) {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    max-width: 100%;
    height: auto;
    row-gap: 6px;
    overflow: visible;
}

.recurrence-week-selector :deep(.v-btn-toggle .v-btn) {
    flex: 0 0 auto;
    min-height: 32px;
    white-space: nowrap;
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

.timetable-single-date-overview__items {
    display: grid;
    gap: 5px;
}

.timetable-single-date-overview__item {
    display: grid;
    gap: 2px;
    padding-bottom: 5px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    cursor: pointer;
    outline: none;
}

.timetable-single-date-overview__item:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.timetable-single-date-overview__item:hover,
.timetable-single-date-overview__item:focus-visible {
    color: #1d4ed8;
}

.timetable-single-date-overview__summary {
    font-size: 0.72rem;
    font-weight: 650;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.timetable-single-date-overview .timetable-date-overview__date {
    padding: 0 4px;
    font-size: 0.67rem;
    line-height: 1.35;
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
    grid-template-columns: 64px repeat(var(--overview-timetable-weekdays, 5), minmax(62px, 1fr));
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
    flex-wrap: wrap;
    gap: 2px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.timetable-generated-cell__course-badge {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    padding: 0 4px;
    border-radius: 999px;
    font-size: 0.5rem;
    font-weight: 850;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
    white-space: nowrap;
}

.timetable-generated-cell__course-badge--missing {
    background: rgba(251, 146, 60, 0.24);
    color: #9a3412;
}

.timetable-generated-cell__course-badge--additional {
    background: rgba(14, 165, 233, 0.22);
    color: #0369a1;
}

.timetable-course-fu {
    font-size: 0.58em;
    font-weight: 850;
    line-height: 1;
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

@media (max-width: 700px) {
    .students-timetable-overview {
        padding-inline: 0 !important;
    }

    .students-timetable-overview > :deep(.v-card) {
        border: none !important;
        border-radius: 0 !important;
    }

    .timetable-card-body {
        padding-inline: 4px !important;
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
        align-self: flex-start;
        margin-right: 0;
        width: 100%;
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

    .timetable-generated-grid {
        grid-template-columns: 38px repeat(var(--overview-timetable-weekdays, 5), minmax(48px, 1fr));
        grid-template-rows: 28px;
        grid-auto-rows: minmax(44px, auto);
    }

    .timetable-generated-cell {
        padding: 3px;
        font-size: 0.68rem;
    }

    .timetable-generated-cell--has-single-date-markers {
        padding-top: 18px;
    }

    .timetable-generated-cell__content {
        min-height: 36px;
    }

    .timetable-generated-cell__code {
        font-size: 0.68rem;
    }

    .timetable-generated-cell__details {
        font-size: 0.58rem;
    }

    .timetable-hour-num {
        font-size: 0.68rem;
    }

    .timetable-hour-time {
        font-size: 0.5rem;
    }

    .semester-timetable__header {
        flex-direction: column;
        align-items: flex-start;
    }

    .overview-active-label {
        width: 100%;
        justify-content: center;
    }

    .overview-wizard-settings-summary {
        flex: 0 0 auto;
        max-width: 100%;
        margin-left: 0;
        justify-items: start;
        text-align: left;
    }

    .overview-wizard-settings-summary__list {
        justify-content: flex-start;
    }

    .recurrence-week-selector {
        overflow-x: visible;
        margin-inline: 0;
        padding-inline: 0;
    }

    .recurrence-week-selector :deep(.v-btn-toggle) {
        width: 100%;
    }

    .recurrence-week-selector :deep(.v-btn-toggle .v-btn) {
        font-size: 0.68rem;
        padding: 0 8px;
    }

    .recurrence-week-selector__pdf-btn {
        margin-left: 0;
    }

    .overview-wizard-button,
    .overview-manual-button,
    .overview-saved-timetable-button {
        width: 100%;
    }

    .course-choice-panel__header {
        flex-direction: column;
        align-items: flex-start;
    }

    .course-menu-dialog-options {
        justify-content: flex-start;
    }
}
</style>
