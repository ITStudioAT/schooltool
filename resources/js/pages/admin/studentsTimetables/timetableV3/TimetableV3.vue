<template>
    <v-sheet rounded="lg" class="timetable-v3 pa-5 pa-md-7">
        <div v-if="isLoadingState" class="timetable-v3__initializing" aria-live="polite">
            <v-progress-circular indeterminate color="primary" size="36" width="4" />
            <span>Gespeicherte Auswahl wird geladen …</span>
        </div>

        <div v-else-if="currentStep === 'selection'" class="timetable-v3__step">
            <div class="timetable-v3__page-header">
                <div>
                    <div class="text-overline text-primary">Version 3</div>
                    <h2 class="text-h5 font-weight-bold mb-2">Stundenplan erstellen</h2>
                    <p class="text-body-1 text-medium-emphasis mb-0">
                        Wie möchten Sie beginnen?
                    </p>
                </div>
                <div
                    v-if="currentPageLabel"
                    class="timetable-v3__page-number text-overline text-primary">
                    Seite {{ currentPageLabel }}
                </div>
            </div>

            <v-progress-linear v-if="isLoadingState" class="mt-6" color="primary" indeterminate rounded />

            <v-alert
                v-if="stateLoadFailed || stateSaveFailed"
                class="mt-6"
                type="warning"
                variant="tonal"
                icon="mdi-alert-outline">
                Die Auswahl konnte nicht gespeichert werden. Bitte versuche es erneut. Version 2 bleibt davon unberührt.
            </v-alert>

            <div class="timetable-v3__choice-grid mt-6" role="group" aria-label="Planungsart auswählen">
                <button
                    type="button"
                    class="timetable-v3__choice"
                    :class="{ 'timetable-v3__choice--selected': planningMode === 'with_student' }"
                    :disabled="isLoadingState || isSavingState"
                    @click="chooseWithStudent">
                    <span class="timetable-v3__choice-icon">
                        <v-icon icon="mdi-account-school-outline" size="32" />
                    </span>
                    <span class="timetable-v3__choice-copy">
                        <span class="timetable-v3__choice-title">Mit Studierendem</span>
                        <span class="timetable-v3__choice-description">Studierenden auswählen und persönlich planen</span>
                    </span>
                    <v-icon
                        :icon="planningMode === 'with_student' ? 'mdi-check-circle' : 'mdi-chevron-right'"
                        size="26" />
                </button>

                <button
                    type="button"
                    class="timetable-v3__choice"
                    :class="{ 'timetable-v3__choice--selected': planningMode === 'without_student' }"
                    :disabled="isLoadingState || isSavingState"
                    @click="chooseWithoutStudent">
                    <span class="timetable-v3__choice-icon">
                        <v-icon icon="mdi-account-off-outline" size="32" />
                    </span>
                    <span class="timetable-v3__choice-copy">
                        <span class="timetable-v3__choice-title">Ohne Studierenden</span>
                        <span class="timetable-v3__choice-description">Direkt mit einer freien Planung beginnen</span>
                    </span>
                    <v-icon
                        :icon="planningMode === 'without_student' ? 'mdi-check-circle' : 'mdi-chevron-right'"
                        size="26" />
                </button>
            </div>

            <div v-if="hasPlanningSelectionContext" class="timetable-v3__selection-summary mt-5">
                <span class="timetable-v3__selection-icon">
                    <v-icon
                        :icon="planningMode === 'with_student' ? 'mdi-account-check-outline' : 'mdi-account-off-outline'"
                        color="primary"
                        size="28" />
                </span>
                <div class="timetable-v3__selection-summary-copy">
                    <div class="timetable-v3__selection-label text-caption">Aktuelle Auswahl</div>
                    <div class="timetable-v3__selection-value">
                        <template v-if="planningMode === 'with_student'">
                            <span>{{ selectedStudentClass || '–' }} · {{ selectedStudentFullName || selectedStudentLabel }}</span>
                            <span v-if="selectedStudentReligion" class="timetable-v3__selection-religion">
                                · {{ selectedStudentReligion }}
                            </span>
                        </template>
                        <span v-else>Ohne Studierenden</span>
                        <v-icon
                            v-if="planningMode === 'with_student' && selectedStudentSexPresentation"
                            class="timetable-v3__student-sex-icon"
                            :icon="selectedStudentSexPresentation.icon"
                            :color="selectedStudentSexPresentation.color"
                            :title="selectedStudentSexPresentation.label"
                            :aria-label="selectedStudentSexPresentation.label"
                            size="28" />
                    </div>
                    <div v-if="selectedStudentEmail" class="timetable-v3__email-row">
                        <v-icon icon="mdi-email-outline" size="16" />
                        <span class="timetable-v3__email-address">{{ selectedStudentEmail }}</span>
                        <v-btn
                            class="timetable-v3__email-copy-button"
                            :icon="emailCopyStatus === 'copied'
                                ? 'mdi-check'
                                : emailCopyStatus === 'failed' ? 'mdi-alert-circle-outline' : 'mdi-content-copy'"
                            :color="emailCopyStatus === 'copied' ? 'success' : emailCopyStatus === 'failed' ? 'error' : 'primary'"
                            size="x-small"
                            variant="text"
                            :aria-label="emailCopyStatus === 'copied' ? 'E-Mail kopiert' : 'E-Mail kopieren'"
                            @click="copySelectedStudentEmail" />
                    </div>
                    <div
                        v-if="planningMode === 'with_student' && (selectedStudentSubjectPlanMismatch || selectedStudentSchoolLevelMismatch)"
                        class="timetable-v3__student-data-fields">
                        <span
                            v-if="selectedStudentSubjectPlanMismatch"
                            class="timetable-v3__student-data-field"
                            :class="{ 'timetable-v3__student-data-field--invalid': selectedStudentSubjectPlanMismatch }"
                            :aria-invalid="selectedStudentSubjectPlanMismatch ? 'true' : undefined"
                            :title="selectedStudentSubjectPlanMismatch ? 'Stundentafel passt nicht zur Klasse' : undefined">
                            <span class="timetable-v3__student-data-label">Stundentafel</span>
                            <span>{{ selectedStudentSubjectPlan }}</span>
                        </span>
                        <button
                            v-if="selectedStudentSchoolLevelMismatch"
                            type="button"
                            class="timetable-v3__student-data-field"
                            :class="{ 'timetable-v3__student-data-field--invalid': selectedStudentSchoolLevelMismatch }"
                            :aria-invalid="selectedStudentSchoolLevelMismatch ? 'true' : undefined"
                            :title="selectedStudentSchoolLevelMismatch ? 'Schulstufe ist für diese Studienform nicht gültig – zum Korrigieren anklicken' : undefined"
                            @click="openSchoolLevelDialog">
                            <span class="timetable-v3__student-data-label">Schulstufe</span>
                            <span>{{ selectedStudentImportedSchoolLevel }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="planningMode === 'with_student'" class="timetable-v3__student-info-actions">
                    <v-menu
                        open-on-hover
                        :open-on-click="false"
                        location="bottom end"
                        :open-delay="100"
                        :close-delay="150">
                        <template #activator="{ props }">
                            <v-btn
                                v-bind="props"
                                class="timetable-v3__student-info-button"
                                icon="mdi-information-outline"
                                size="large"
                                color="primary"
                                variant="tonal"
                                aria-label="Informationen zum Studierenden anzeigen"
                                @click="openStudentInfoDialog" />
                        </template>

                        <v-card rounded="lg" class="timetable-v3__info-hover-card" elevation="8">
                            <v-card-title class="timetable-v3__info-hover-title d-flex align-center ga-2">
                                <v-icon icon="mdi-information-outline" color="primary" size="22" />
                                Studierenden-Information
                            </v-card-title>
                            <v-card-text class="pa-4 pt-2">
                                <div class="timetable-v3__info-hover-grid">
                                    <div
                                        v-for="item in selectedStudentHoverInformationItems"
                                        :key="`hover-selection-${item.key}`"
                                        class="timetable-v3__info-hover-item">
                                        <span class="timetable-v3__info-label">{{ item.label }}</span>
                                        <span class="timetable-v3__info-value">
                                            {{ item.value }}
                                            <span
                                                v-if="item.key === 'semester'"
                                                class="timetable-v3__info-imported-school-level">
                                                ({{ selectedStudentImportedSchoolLevel }})
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                <div v-if="studentSelectionDetailsLoading" class="timetable-v3__info-hover-status">
                                    <v-progress-circular indeterminate color="primary" size="16" width="2" />
                                    <span>Berechnung wird geladen</span>
                                </div>
                                <div
                                    v-else-if="studentSelectionDetailsError"
                                    class="timetable-v3__info-hover-status timetable-v3__info-hover-status--error">
                                    Die berechnete Auswahl konnte nicht geladen werden.
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-menu>
                    <v-menu
                        open-on-hover
                        :open-on-click="false"
                        location="start center"
                        :offset="16"
                        :open-delay="100"
                        :close-delay="150">
                        <template #activator="{ props }">
                            <v-btn
                                v-bind="props"
                                class="timetable-v3__study-info-button"
                                icon="mdi-school-outline"
                                size="large"
                                color="teal-darken-1"
                                variant="tonal"
                                aria-label="Informationen zum Studium anzeigen"
                                @click="openStudyInfoDialog" />
                        </template>

                        <v-card rounded="lg" class="timetable-v3__study-info-hover-card" elevation="8">
                            <v-card-title class="timetable-v3__study-info-hover-title d-flex align-center ga-2">
                                <v-icon icon="mdi-school-outline" color="teal-darken-1" size="22" />
                                Informationen zum Studium
                            </v-card-title>
                            <v-card-text class="timetable-v3__study-info-hover-content pa-4 pt-2">
                                <div v-if="studentSelectionDetailsLoading" class="timetable-v3__study-info-feedback">
                                    <v-progress-circular indeterminate color="teal-darken-1" size="20" width="3" />
                                    <span>Moduldaten werden geladen</span>
                                </div>
                                <div
                                    v-else-if="studentSelectionDetailsError"
                                    class="timetable-v3__study-info-feedback timetable-v3__study-info-feedback--error">
                                    Die Moduldaten konnten nicht geladen werden.
                                </div>
                                <div v-else class="timetable-v3__study-module-groups">
                                    <section
                                        v-for="group in studentStudyModuleGroups"
                                        :key="`hover-selection-${group.key}`"
                                        class="timetable-v3__study-module-group"
                                        :class="`timetable-v3__study-module-group--${group.key}`"
                                        :aria-label="group.label">
                                        <div class="timetable-v3__study-module-group-header">
                                            <h3 class="timetable-v3__study-module-group-title">{{ group.label }}</h3>
                                            <div class="timetable-v3__study-module-group-count">
                                                {{ group.count }} {{ group.count === 1 ? 'Modul' : 'Module' }}
                                            </div>
                                        </div>
                                        <div v-if="group.modules.length" class="timetable-v3__study-module-list">
                                            <div
                                                v-for="module in group.modules"
                                                :key="`${module.code}-${module.grade}`"
                                                class="timetable-v3__study-module-row">
                                                <span class="timetable-v3__study-module-copy">
                                                    <span class="timetable-v3__study-module-code">{{ moduleDisplayCode(module) }}</span>
                                                    <span
                                                        v-if="moduleDisplayNameVisible(module)"
                                                        class="timetable-v3__study-module-name">
                                                        {{ moduleDisplayName(module) }}
                                                    </span>
                                                </span>
                                                <span class="timetable-v3__study-module-grades">
                                                    <span
                                                        v-for="(grade, gradeIndex) in module.grades"
                                                        :key="`${grade.status}-${grade.value}-${gradeIndex}`"
                                                        class="timetable-v3__study-module-grade"
                                                        :class="`timetable-v3__study-module-grade--${grade.status}`"
                                                        :aria-label="`Note ${grade.value}`">
                                                        {{ grade.value }}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                        <div v-else class="timetable-v3__study-module-empty">Keine Module</div>
                                    </section>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-menu>
                </div>
            </div>

            <v-card
                v-if="hasPlanningSelectionContext"
                class="timetable-v3__planning-selection mt-6 mx-auto"
                tag="section"
                max-width="960"
                width="100%"
                aria-labelledby="timetable-v3-planning-selection-title">
                <div class="timetable-v3__planning-selection-heading">
                    <span class="timetable-v3__planning-selection-icon">
                        <v-icon icon="mdi-tune-variant" color="primary" size="28" />
                    </span>
                    <div>
                        <h3 id="timetable-v3-planning-selection-title" class="timetable-v3__planning-selection-title">
                            Studienauswahl
                        </h3>
                        <p class="timetable-v3__planning-selection-description">
                            Erneut anklicken, um eine Auswahl abzuwählen.
                        </p>
                    </div>
                </div>

                <div v-if="studentSelectionDetailsLoading" class="timetable-v3__planning-selection-feedback">
                    <v-progress-circular indeterminate color="primary" size="22" width="3" />
                    <span>Auswahl wird geladen</span>
                </div>
                <div
                    v-else-if="studentSelectionDetailsError"
                    class="timetable-v3__planning-selection-feedback timetable-v3__planning-selection-feedback--error">
                    Die Auswahl konnte nicht geladen werden.
                </div>
                <div
                    v-if="planningSelectionFields.length && !studentSelectionDetailsError"
                    class="timetable-v3__planning-selection-grid">
                    <fieldset
                        v-for="field in planningSelectionFields"
                        :key="field.key"
                        class="timetable-v3__planning-selection-group">
                        <legend class="timetable-v3__planning-selection-label">{{ field.label }}</legend>
                        <div class="timetable-v3__planning-selection-options">
                            <button
                                v-for="option in field.options"
                                :key="`${field.key}-${option.value}`"
                                type="button"
                                class="timetable-v3__planning-selection-option"
                                :class="{
                                    'timetable-v3__planning-selection-option--selected': planningSelectionValues[field.key] === option.value,
                                }"
                                :aria-pressed="planningSelectionValues[field.key] === option.value"
                                :disabled="studentSelectionDetailsLoading || isSavingState"
                                @click="updatePlanningSelection(field.key, option.value)">
                                <span>{{ option.title }}</span>
                                <v-icon
                                    v-if="planningSelectionValues[field.key] === option.value"
                                    icon="mdi-check-circle"
                                    color="primary"
                                    size="21" />
                            </button>
                        </div>
                    </fieldset>
                </div>
            </v-card>

            <div class="timetable-v3__page-actions timetable-v3__page-actions--split">
                <v-btn
                    class="timetable-v3__restart-button"
                    size="large"
                    color="error"
                    variant="outlined"
                    prepend-icon="mdi-restart"
                    :disabled="!hasPlanningSelectionContext || studentSelectionDetailsLoading || isLoadingState || isSavingState"
                    @click="restartPlanning">
                    Neustart
                </v-btn>
                <v-btn
                    class="timetable-v3__selection-continue-button"
                    size="large"
                    color="primary"
                    append-icon="mdi-arrow-right"
                    :disabled="!hasPlanningSelectionContext || studentSelectionDetailsLoading || isLoadingState || isSavingState"
                    @click="continueToNextStep">
                    Weiter
                </v-btn>
            </div>
        </div>

        <div v-else class="timetable-v3__step timetable-v3__next-step">
            <div
                v-if="currentStep !== 'adoption' || currentPageLabel"
                class="timetable-v3__page-header">
                <div v-if="currentStep !== 'adoption'">
                    <div class="text-overline text-primary">Version 3</div>
                    <h2 class="text-h5 font-weight-bold mb-2">Stundenplan erstellen</h2>
                </div>
                <div
                    v-if="currentPageLabel"
                    class="timetable-v3__page-number text-overline text-primary">
                    Seite {{ currentPageLabel }}
                </div>
            </div>

            <div class="timetable-v3__selection-summary mt-6">
                <span class="timetable-v3__selection-icon">
                    <v-icon
                        :icon="planningMode === 'with_student' ? 'mdi-account-check-outline' : 'mdi-account-off-outline'"
                        color="primary"
                        size="28" />
                </span>
                <div class="timetable-v3__selection-summary-copy">
                    <div class="timetable-v3__selection-label text-caption">Aktuelle Auswahl</div>
                    <div class="timetable-v3__selection-value">
                        <template v-if="planningMode === 'with_student'">
                            <span>{{ selectedStudentClass || '–' }} · {{ selectedStudentFullName || selectedStudentLabel }}</span>
                            <span v-if="selectedStudentReligion" class="timetable-v3__selection-religion">
                                · {{ selectedStudentReligion }}
                            </span>
                        </template>
                        <span v-else>Ohne Studierenden</span>
                        <v-icon
                            v-if="planningMode === 'with_student' && selectedStudentSexPresentation"
                            class="timetable-v3__student-sex-icon"
                            :icon="selectedStudentSexPresentation.icon"
                            :color="selectedStudentSexPresentation.color"
                            :title="selectedStudentSexPresentation.label"
                            :aria-label="selectedStudentSexPresentation.label"
                            size="28" />
                    </div>
                    <div v-if="selectedStudentEmail" class="timetable-v3__email-row">
                        <v-icon icon="mdi-email-outline" size="16" />
                        <span class="timetable-v3__email-address">{{ selectedStudentEmail }}</span>
                        <v-btn
                            class="timetable-v3__email-copy-button"
                            :icon="emailCopyStatus === 'copied'
                                ? 'mdi-check'
                                : emailCopyStatus === 'failed' ? 'mdi-alert-circle-outline' : 'mdi-content-copy'"
                            :color="emailCopyStatus === 'copied' ? 'success' : emailCopyStatus === 'failed' ? 'error' : 'primary'"
                            size="x-small"
                            variant="text"
                            :aria-label="emailCopyStatus === 'copied' ? 'E-Mail kopiert' : 'E-Mail kopieren'"
                            @click="copySelectedStudentEmail" />
                    </div>
                    <div
                        v-if="planningMode === 'with_student' && (selectedStudentSubjectPlanMismatch || selectedStudentSchoolLevelMismatch)"
                        class="timetable-v3__student-data-fields">
                        <span
                            v-if="selectedStudentSubjectPlanMismatch"
                            class="timetable-v3__student-data-field"
                            :class="{ 'timetable-v3__student-data-field--invalid': selectedStudentSubjectPlanMismatch }"
                            :aria-invalid="selectedStudentSubjectPlanMismatch ? 'true' : undefined"
                            :title="selectedStudentSubjectPlanMismatch ? 'Stundentafel passt nicht zur Klasse' : undefined">
                            <span class="timetable-v3__student-data-label">Stundentafel</span>
                            <span>{{ selectedStudentSubjectPlan }}</span>
                        </span>
                        <button
                            v-if="selectedStudentSchoolLevelMismatch"
                            type="button"
                            class="timetable-v3__student-data-field"
                            :class="{ 'timetable-v3__student-data-field--invalid': selectedStudentSchoolLevelMismatch }"
                            :aria-invalid="selectedStudentSchoolLevelMismatch ? 'true' : undefined"
                            :title="selectedStudentSchoolLevelMismatch ? 'Schulstufe ist für diese Studienform nicht gültig – zum Korrigieren anklicken' : undefined"
                            @click="openSchoolLevelDialog">
                            <span class="timetable-v3__student-data-label">Schulstufe</span>
                            <span>{{ selectedStudentImportedSchoolLevel }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="planningMode === 'with_student'" class="timetable-v3__student-info-actions">
                    <v-menu
                        open-on-hover
                        :open-on-click="false"
                        location="bottom end"
                        :open-delay="100"
                        :close-delay="150">
                        <template #activator="{ props }">
                            <v-btn
                                v-bind="props"
                                class="timetable-v3__student-info-button"
                                icon="mdi-information-outline"
                                size="large"
                                color="primary"
                                variant="tonal"
                                aria-label="Informationen zum Studierenden anzeigen"
                                @click="openStudentInfoDialog" />
                        </template>

                        <v-card rounded="lg" class="timetable-v3__info-hover-card" elevation="8">
                            <v-card-title class="timetable-v3__info-hover-title d-flex align-center ga-2">
                                <v-icon icon="mdi-information-outline" color="primary" size="22" />
                                Studierenden-Information
                            </v-card-title>
                            <v-card-text class="pa-4 pt-2">
                                <div class="timetable-v3__info-hover-grid">
                                    <div
                                        v-for="item in selectedStudentHoverInformationItems"
                                        :key="`hover-modules-${item.key}`"
                                        class="timetable-v3__info-hover-item">
                                        <span class="timetable-v3__info-label">{{ item.label }}</span>
                                        <span class="timetable-v3__info-value">
                                            {{ item.value }}
                                            <span
                                                v-if="item.key === 'semester'"
                                                class="timetable-v3__info-imported-school-level">
                                                ({{ selectedStudentImportedSchoolLevel }})
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                <div v-if="studentSelectionDetailsLoading" class="timetable-v3__info-hover-status">
                                    <v-progress-circular indeterminate color="primary" size="16" width="2" />
                                    <span>Berechnung wird geladen</span>
                                </div>
                                <div
                                    v-else-if="studentSelectionDetailsError"
                                    class="timetable-v3__info-hover-status timetable-v3__info-hover-status--error">
                                    Die berechnete Auswahl konnte nicht geladen werden.
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-menu>
                    <v-menu
                        open-on-hover
                        :open-on-click="false"
                        location="start center"
                        :offset="16"
                        :open-delay="100"
                        :close-delay="150">
                        <template #activator="{ props }">
                            <v-btn
                                v-bind="props"
                                class="timetable-v3__study-info-button"
                                icon="mdi-school-outline"
                                size="large"
                                color="teal-darken-1"
                                variant="tonal"
                                aria-label="Informationen zum Studium anzeigen"
                                @click="openStudyInfoDialog" />
                        </template>

                        <v-card rounded="lg" class="timetable-v3__study-info-hover-card" elevation="8">
                            <v-card-title class="timetable-v3__study-info-hover-title d-flex align-center ga-2">
                                <v-icon icon="mdi-school-outline" color="teal-darken-1" size="22" />
                                Informationen zum Studium
                            </v-card-title>
                            <v-card-text class="timetable-v3__study-info-hover-content pa-4 pt-2">
                                <div v-if="studentSelectionDetailsLoading" class="timetable-v3__study-info-feedback">
                                    <v-progress-circular indeterminate color="teal-darken-1" size="20" width="3" />
                                    <span>Moduldaten werden geladen</span>
                                </div>
                                <div
                                    v-else-if="studentSelectionDetailsError"
                                    class="timetable-v3__study-info-feedback timetable-v3__study-info-feedback--error">
                                    Die Moduldaten konnten nicht geladen werden.
                                </div>
                                <div v-else class="timetable-v3__study-module-groups">
                                    <section
                                        v-for="group in studentStudyModuleGroups"
                                        :key="`hover-modules-${group.key}`"
                                        class="timetable-v3__study-module-group"
                                        :class="`timetable-v3__study-module-group--${group.key}`"
                                        :aria-label="group.label">
                                        <div class="timetable-v3__study-module-group-header">
                                            <h3 class="timetable-v3__study-module-group-title">{{ group.label }}</h3>
                                            <div class="timetable-v3__study-module-group-count">
                                                {{ group.count }} {{ group.count === 1 ? 'Modul' : 'Module' }}
                                            </div>
                                        </div>
                                        <div v-if="group.modules.length" class="timetable-v3__study-module-list">
                                            <div
                                                v-for="module in group.modules"
                                                :key="`${module.code}-${module.grade}`"
                                                class="timetable-v3__study-module-row">
                                                <span class="timetable-v3__study-module-copy">
                                                    <span class="timetable-v3__study-module-code">{{ moduleDisplayCode(module) }}</span>
                                                    <span
                                                        v-if="moduleDisplayNameVisible(module)"
                                                        class="timetable-v3__study-module-name">
                                                        {{ moduleDisplayName(module) }}
                                                    </span>
                                                </span>
                                                <span class="timetable-v3__study-module-grades">
                                                    <span
                                                        v-for="(grade, gradeIndex) in module.grades"
                                                        :key="`${grade.status}-${grade.value}-${gradeIndex}`"
                                                        class="timetable-v3__study-module-grade"
                                                        :class="`timetable-v3__study-module-grade--${grade.status}`"
                                                        :aria-label="`Note ${grade.value}`">
                                                        {{ grade.value }}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                        <div v-else class="timetable-v3__study-module-empty">Keine Module</div>
                                    </section>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-menu>
                </div>
            </div>

            <section
                v-if="currentStep === 'modules' || currentStep === 'adoption'"
                class="timetable-v3__compact-planning-card mt-4"
                aria-labelledby="timetable-v3-compact-planning-title">
                <div class="timetable-v3__compact-planning-heading">
                    <v-icon icon="mdi-tune-variant" color="primary" size="22" />
                    <h3 id="timetable-v3-compact-planning-title" class="timetable-v3__compact-planning-title">
                        Studienauswahl
                    </h3>
                </div>
                <div v-if="studentSelectionDetailsLoading" class="timetable-v3__compact-planning-feedback">
                    <v-progress-circular indeterminate color="primary" size="20" width="3" />
                    <span>Auswahl wird geladen</span>
                </div>
                <div
                    v-else-if="studentSelectionDetailsError"
                    class="timetable-v3__compact-planning-feedback timetable-v3__compact-planning-feedback--error">
                    Die Auswahl konnte nicht geladen werden.
                </div>
                <div v-else class="timetable-v3__compact-planning-grid">
                    <div
                        v-for="item in compactPlanningSelectionItems"
                        :key="item.key"
                        class="timetable-v3__compact-planning-item">
                        <span class="timetable-v3__compact-planning-label">{{ item.label }}</span>
                        <span class="timetable-v3__compact-planning-value">{{ item.value }}</span>
                    </div>
                </div>
            </section>

            <template v-if="currentStep === 'modules'">
                <section
                    v-if="!studentSelectionDetailsLoading && !studentSelectionDetailsError"
                    class="timetable-v3__module-workspace mt-4"
                    :aria-busy="timetablePageLoading ? 'true' : 'false'"
                    :inert="timetablePageLoading"
                    aria-labelledby="timetable-v3-module-selection-title">
                <div class="timetable-v3__module-workspace-heading">
                    <div>
                        <div class="timetable-v3__module-workspace-eyebrow">
                            {{ scheduleCreationMode === 'automatic' ? 'Modulauswahl' : 'Stundenplanerstellung' }}
                        </div>
                        <h3 id="timetable-v3-module-selection-title" class="timetable-v3__module-workspace-title">
                            {{
                                scheduleCreationMode === 'automatic'
                                    ? 'Welche Module sollen zur Stundenplanerstellung berücksichtigt werden?'
                                    : 'Soll der Stundenplan automatisch oder manuell erzeugt werden?'
                            }}
                        </h3>
                    </div>
                    <div
                        v-if="scheduleCreationMode === 'automatic'"
                        class="timetable-v3__module-selected-total"
                        aria-live="polite">
                        <strong>{{ selectedModuleCount }}</strong>
                        {{ selectedModuleCount === 1 ? 'Modul ausgewählt' : 'Module ausgewählt' }}
                    </div>
                </div>

                <div
                    class="timetable-v3__schedule-mode-options"
                    :class="{
                        'timetable-v3__schedule-mode-options--automatic-selected': scheduleCreationMode === 'automatic',
                        'timetable-v3__schedule-mode-options--manual-selected': scheduleCreationMode === 'manual',
                    }"
                    role="group"
                    aria-label="Art der Stundenplanerstellung">
                    <label
                        class="timetable-v3__schedule-mode-card timetable-v3__schedule-mode-card--automatic"
                        :class="{ 'timetable-v3__schedule-mode-card--selected': scheduleCreationMode === 'automatic' }">
                        <input
                            class="timetable-v3__schedule-mode-input"
                            type="radio"
                            name="timetable-v3-schedule-mode"
                            value="automatic"
                            :checked="scheduleCreationMode === 'automatic'"
                            :disabled="isSavingState"
                            @change="chooseScheduleCreationMode('automatic')" />
                        <span class="timetable-v3__schedule-mode-icon">
                            <v-icon icon="mdi-calendar-clock" size="30" />
                        </span>
                        <span class="timetable-v3__schedule-mode-copy">
                            <span class="timetable-v3__schedule-mode-kickers">
                                <span class="timetable-v3__schedule-mode-recommendation">
                                    <v-icon icon="mdi-star-four-points" size="14" />
                                    Empfohlen
                                </span>
                            </span>
                            <span class="timetable-v3__schedule-mode-title">Automatischer Stundenplan</span>
                            <span class="timetable-v3__schedule-mode-description">
                                Sie wählen die Module. Das System erstellt und optimiert daraus den Stundenplan.
                            </span>
                        </span>
                        <span class="timetable-v3__schedule-mode-status">
                            <v-icon
                                :icon="scheduleCreationMode === 'automatic' ? 'mdi-check-circle' : 'mdi-circle-outline'"
                                size="20" />
                            {{ scheduleCreationMode === 'automatic' ? 'Ausgewählt' : 'Automatisch wählen' }}
                        </span>

                        <div
                            v-if="scheduleCreationMode === 'automatic'"
                            class="timetable-v3__selected-modules timetable-v3__schedule-mode-selected-modules"
                            aria-live="polite">
                            <div class="timetable-v3__selected-modules-heading">
                                <span class="timetable-v3__selected-modules-heading-label">
                                    <v-icon icon="mdi-check-circle-outline" size="18" />
                                    Ausgewählte Module
                                </span>
                                <span class="timetable-v3__selected-modules-summary">
                                    {{ selectedModuleCount }}/{{ maximumSelectedModules }} Module
                                    · {{ selectedModuleHoursLabel }}/{{ maximumSelectedModuleHours }} Std.
                                </span>
                            </div>
                            <div class="timetable-v3__module-selection-limit-hint">
                                <v-icon icon="mdi-information-outline" size="16" />
                                Maximal {{ maximumSelectedModules }} Module und
                                {{ maximumSelectedModuleHours }} Stunden gleichzeitig.
                            </div>
                            <v-alert
                                v-if="moduleSelectionLimitMessage"
                                class="timetable-v3__module-selection-limit-alert"
                                type="warning"
                                density="compact"
                                variant="tonal"
                                closable
                                @click:close="moduleSelectionLimitMessage = ''">
                                {{ moduleSelectionLimitMessage }}
                            </v-alert>
                            <div v-if="selectedModules.length" class="timetable-v3__selected-modules-list">
                                <v-chip
                                    v-for="module in selectedModules"
                                    :key="module.selection_key"
                                    class="timetable-v3__selected-module-chip"
                                    color="primary"
                                    closable
                                    close-icon="mdi-close-circle"
                                    :close-label="`${moduleDisplayCode(module)} aus der Auswahl entfernen`"
                                    :disabled="isSavingState"
                                    label
                                    size="small"
                                    variant="tonal"
                                    @click:close.stop="removeSelectedModule(module)">
                                    <strong>{{ moduleDisplayCode(module) }}</strong>
                                    <span v-if="moduleDisplayNameVisible(module)">
                                        &nbsp;· {{ moduleDisplayName(module) }}
                                    </span>
                                </v-chip>
                            </div>
                            <div v-else class="timetable-v3__selected-modules-empty">
                                Keine Module ausgewählt.
                            </div>
                            <div
                                v-if="selectedModuleCount > 0"
                                class="timetable-v3__schedule-create-action">
                                <v-btn
                                    class="timetable-v3__deselect-all-modules-button"
                                    color="error"
                                    prepend-icon="mdi-checkbox-multiple-blank-outline"
                                    size="small"
                                    type="button"
                                    variant="tonal"
                                    :disabled="isSavingState"
                                    @click.prevent.stop="deselectAllSelectedModules">
                                    Alle abwählen
                                </v-btn>
                                <v-btn
                                    class="timetable-v3__schedule-create-button"
                                    color="primary"
                                    prepend-icon="mdi-calendar-check"
                                    append-icon="mdi-arrow-right"
                                    elevation="8"
                                    height="52"
                                    rounded="lg"
                                    size="large"
                                    type="button"
                                    variant="elevated"
                                    :disabled="isSavingState"
                                    @click.prevent.stop="openTimetableCreationPage">
                                    Stundenplan erstellen
                                </v-btn>
                            </div>
                        </div>
                    </label>

                    <button
                        class="timetable-v3__schedule-mode-card timetable-v3__schedule-mode-card--manual"
                        :class="{ 'timetable-v3__schedule-mode-card--selected': scheduleCreationMode === 'manual' }"
                        type="button"
                        :disabled="isLoadingState || isSavingState || timetablePageLoading"
                        @click="openManualTimetablePage">
                        <span class="timetable-v3__schedule-mode-icon">
                            <v-icon icon="mdi-calendar-edit" size="30" />
                        </span>
                        <span class="timetable-v3__schedule-mode-copy">
                            <span class="timetable-v3__schedule-mode-title">Manueller Stundenplan</span>
                            <span class="timetable-v3__schedule-mode-description">
                                Sie stellen den Stundenplan selbst zusammen und platzieren die Unterrichte manuell.
                            </span>
                        </span>
                        <span class="timetable-v3__schedule-mode-status">
                            <v-icon icon="mdi-arrow-right-circle-outline" size="20" />
                            Manuell öffnen
                        </span>

                        <div
                            v-if="scheduleCreationMode === 'manual'"
                            class="
                                timetable-v3__selected-modules
                                timetable-v3__schedule-mode-selected-modules
                                timetable-v3__schedule-mode-available-modules
                            ">
                            <div class="timetable-v3__selected-modules-heading">
                                <v-icon icon="mdi-book-open-variant" size="18" />
                                Verfügbare Module und Unterrichte
                            </div>
                            <div class="timetable-v3__selected-modules-empty">
                                Alle Module und Unterrichte stehen zur Verfügung.
                            </div>
                        </div>
                    </button>
                </div>

                <div
                    v-if="scheduleCreationMode === 'automatic' && usesMainModuleGroups"
                    class="timetable-v3__main-module-heading">
                    <div class="timetable-v3__main-module-heading-icon">
                        <v-icon icon="mdi-bookshelf" size="21" />
                    </div>
                    <div>
                        <h4>Hauptmodule</h4>
                        <p>Wählen Sie zuerst ein Hauptmodul. Danach stehen die zugehörigen Module und Unterrichte zur Auswahl.</p>
                    </div>
                </div>

                <div
                    v-if="scheduleCreationMode === 'automatic'"
                    class="timetable-v3__module-group-cards"
                    :class="{ 'timetable-v3__module-group-cards--main': usesMainModuleGroups }"
                    :aria-label="usesMainModuleGroups ? 'Hauptmodule' : 'Modularten'">
                    <button
                        v-for="group in moduleSelectionGroups"
                        :key="group.key"
                        type="button"
                        class="timetable-v3__module-group-card"
                        :class="[
                            usesMainModuleGroups
                                ? 'timetable-v3__module-group-card--main'
                                : `timetable-v3__module-group-card--${group.key}`,
                            { 'timetable-v3__module-group-card--active': moduleGroupActive(group) },
                        ]"
                        :aria-pressed="moduleGroupActive(group)"
                        aria-controls="timetable-v3-module-group-panel"
                        @click="toggleModuleGroup(group)">
                        <span class="timetable-v3__module-group-card-topline">
                            <span class="timetable-v3__module-group-card-icon">
                                <v-icon :icon="moduleGroupIcon(group)" size="19" />
                            </span>
                            <span class="timetable-v3__module-group-card-count">
                                {{ selectedModuleCountForGroup(group) }}/{{ group.count }}
                            </span>
                        </span>
                        <span
                            class="timetable-v3__module-group-card-title"
                            :class="{ 'timetable-v3__module-group-card-title--main': usesMainModuleGroups }">
                            <template v-if="usesMainModuleGroups">
                                <strong class="timetable-v3__main-module-code">{{ moduleDisplayCode(group) }}</strong>
                                <span v-if="moduleDisplayNameVisible(group)" class="timetable-v3__main-module-name">
                                    {{ moduleDisplayName(group) }}
                                </span>
                            </template>
                            <template v-else>{{ group.label }}</template>
                        </span>
                        <span class="timetable-v3__module-group-card-description">{{ group.description }}</span>
                        <span class="timetable-v3__module-group-card-active-mark" aria-hidden="true" />
                    </button>
                </div>

                <transition
                    v-if="scheduleCreationMode === 'automatic'"
                    name="timetable-v3-module-panel"
                    mode="out-in">
                    <section
                        v-if="activeModuleSelectionGroup"
                        :key="activeModuleSelectionGroup.key"
                        id="timetable-v3-module-group-panel"
                        class="timetable-v3__module-group-panel"
                        :class="usesMainModuleGroups
                            ? 'timetable-v3__module-group-panel--main'
                            : `timetable-v3__module-group-panel--${activeModuleSelectionGroup.key}`">
                        <div class="timetable-v3__module-group-panel-actions">
                            <v-btn
                                size="small"
                                variant="tonal"
                                color="primary"
                                prepend-icon="mdi-checkbox-multiple-marked-outline"
                                :disabled="isSavingState || allModulesSelectedForGroup(activeModuleSelectionGroup)"
                                @click="selectAllModulesInGroup(activeModuleSelectionGroup)">
                                Alle auswählen
                            </v-btn>
                            <v-btn
                                size="small"
                                variant="text"
                                color="error"
                                prepend-icon="mdi-checkbox-multiple-blank-outline"
                                :disabled="isSavingState || !hasSelectedModulesForGroup(activeModuleSelectionGroup)"
                                @click="deselectAllModulesInGroup(activeModuleSelectionGroup)">
                                Alle abwählen
                            </v-btn>
                        </div>

                        <div v-if="activeModuleSelectionGroup.modules.length" class="timetable-v3__module-grid">
                            <button
                                v-for="module in activeModuleSelectionGroup.modules"
                                :key="module.selection_key"
                                type="button"
                                class="timetable-v3__module-tile"
                                :class="{ 'timetable-v3__module-tile--selected': moduleSelected(module) }"
                                :aria-pressed="moduleSelected(module)"
                                @click="openModuleCoursesDialog(module)">
                                <span class="timetable-v3__module-check">
                                    <v-icon
                                        :icon="moduleSelected(module) ? 'mdi-check' : 'mdi-chevron-right'"
                                        size="16" />
                                </span>
                                <span class="timetable-v3__module-main">
                                    <span class="timetable-v3__module-code">{{ moduleDisplayCode(module) }}</span>
                                    <span v-if="moduleDisplayNameVisible(module)" class="timetable-v3__module-name">
                                        {{ moduleDisplayName(module) }}
                                    </span>
                                    <span class="timetable-v3__module-meta">
                                        <span v-if="module.status_label" class="timetable-v3__module-status">
                                            {{ module.status_label }}
                                        </span>
                                        <span v-if="module.semester_label">{{ module.semester_label }}</span>
                                        <span v-if="module.hours_label">{{ module.hours_label }}</span>
                                        <span class="timetable-v3__module-course-count">
                                            {{ selectedCourseCountForModule(module) }}/{{ moduleCourseCount(module) }} Unterrichte
                                        </span>
                                        <span
                                            v-for="(grade, gradeIndex) in module.grades"
                                            :key="`${module.selection_key}-${gradeIndex}`"
                                            class="timetable-v3__module-grade">
                                            {{ grade }}
                                        </span>
                                    </span>
                                </span>
                            </button>
                        </div>
                        <div v-else class="timetable-v3__module-list-empty">
                            Keine Module vorhanden.
                        </div>
                    </section>
                </transition>

                </section>

                <div class="timetable-v3__page-actions timetable-v3__page-actions--split">
                    <v-btn
                        class="timetable-v3__restart-button"
                        size="large"
                        color="error"
                        variant="outlined"
                        prepend-icon="mdi-restart"
                        :disabled="!hasPlanningSelectionContext || studentSelectionDetailsLoading || isLoadingState || isSavingState"
                        @click="restartPlanning">
                        Neustart
                    </v-btn>
                    <v-btn
                        class="timetable-v3__back-button"
                        size="large"
                        color="primary"
                        variant="outlined"
                        prepend-icon="mdi-arrow-left"
                        :disabled="studentSelectionDetailsLoading || isLoadingState || isSavingState"
                        @click="returnToSelectionStep">
                        Zurück
                    </v-btn>
                </div>
            </template>

            <template v-else-if="currentStep === 'creation'">
            <div class="timetable-v3__creation-summary-cards mt-4">
                <section
                    class="
                        timetable-v3__schedule-mode-card
                        timetable-v3__schedule-mode-card--automatic
                        timetable-v3__schedule-mode-card--selected
                        timetable-v3__creation-summary-card
                        timetable-v3__creation-summary-card--automatic
                    "
                    aria-label="Automatischer Stundenplan mit ausgewählten Modulen und Berechnung">
                    <span class="timetable-v3__schedule-mode-icon">
                        <v-icon icon="mdi-calendar-clock" size="30" />
                    </span>
                    <span class="timetable-v3__schedule-mode-copy">
                        <span class="timetable-v3__schedule-mode-title">Automatischer Stundenplan</span>
                        <span class="timetable-v3__schedule-mode-description">
                            Sie wählen die Module. Das System erstellt und optimiert daraus den Stundenplan.
                        </span>
                    </span>

                    <div class="timetable-v3__selected-modules timetable-v3__schedule-mode-selected-modules">
                        <div class="timetable-v3__selected-modules-heading">
                            <span class="timetable-v3__selected-modules-heading-label">
                                <v-icon icon="mdi-check-circle-outline" size="18" />
                                Ausgewählte Module
                            </span>
                            <span class="timetable-v3__selected-modules-summary">
                                {{ selectedModuleCount }}/{{ maximumSelectedModules }} Module
                                · {{ selectedModuleHoursLabel }}/{{ maximumSelectedModuleHours }} Std.
                            </span>
                        </div>
                        <div v-if="selectedModules.length" class="timetable-v3__selected-modules-list">
                            <v-chip
                                v-for="module in selectedModules"
                                :key="`creation-${module.selection_key}`"
                                class="timetable-v3__selected-module-chip"
                                color="primary"
                                label
                                size="small"
                                variant="tonal">
                                <strong>{{ moduleDisplayCode(module) }}</strong>
                                <span v-if="moduleDisplayNameVisible(module)">
                                    &nbsp;· {{ moduleDisplayName(module) }}
                                </span>
                            </v-chip>
                        </div>
                    </div>

                    <div class="timetable-v3__calculation-content">
                        <div class="timetable-v3__calculation-heading">
                            <span class="timetable-v3__calculation-icon">
                                <v-icon icon="mdi-calendar-search" size="27" />
                            </span>
                            <h3 id="timetable-v3-calculation-title">{{ timetableCalculationHeading }}</h3>
                        </div>

                        <div
                            v-if="timetableCalculationStatus === 'idle'"
                            class="timetable-v3__calculation-state"
                            aria-live="polite"
                            role="status">
                            <v-icon icon="mdi-calendar-clock-outline" color="teal-darken-1" size="34" />
                            <div>
                                <strong>Die Berechnung wurde noch nicht gestartet.</strong>
                                <span>Starten Sie die Berechnung über „Stundenplan erstellen“ in der Modulauswahl.</span>
                            </div>
                        </div>

                        <div
                            v-else-if="timetableCalculationStatus === 'calculating'"
                            class="timetable-v3__calculation-state timetable-v3__calculation-state--loading"
                            aria-busy="true"
                            aria-live="polite"
                            role="status">
                            <div class="timetable-v3__calculation-progress-copy">
                                <strong>{{ timetableCalculationProgressLabel }}</strong>
                                <span>{{ timetableCalculationProgressPercent }} % Gesamtfortschritt</span>
                            </div>
                            <div
                                class="timetable-v3__calculation-led-progress"
                                role="progressbar"
                                aria-label="Fortschritt der Stundenplanberechnung"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-valuenow="timetableCalculationProgressPercent">
                                <span
                                    v-for="segment in timetableCalculationProgressSegments"
                                    :key="segment.percent"
                                    class="timetable-v3__calculation-led-segment"
                                    :class="{ 'timetable-v3__calculation-led-segment--active': segment.active }"
                                    aria-hidden="true" />
                            </div>
                        </div>

                        <div
                            v-else-if="timetableCalculationStatus === 'error'"
                            class="timetable-v3__calculation-state timetable-v3__calculation-state--error"
                            role="alert">
                            <v-icon icon="mdi-alert-circle-outline" color="error" size="34" />
                            <div>
                                <strong>Die Berechnung konnte nicht abgeschlossen werden.</strong>
                                <span>{{ timetableCalculationError }}</span>
                            </div>
                            <v-btn
                                color="teal-darken-1"
                                prepend-icon="mdi-refresh"
                                variant="tonal"
                                @click="calculatePossibleTimetables">
                                Erneut versuchen
                            </v-btn>
                        </div>

                        <div
                            v-else-if="timetableCalculationStatus === 'success'"
                            class="timetable-v3__calculation-result"
                            aria-live="polite"
                            role="status">
                            <div class="timetable-v3__calculation-result-count">
                                <v-icon
                                    :icon="possibleTimetableCount > 0 ? 'mdi-check-decagram-outline' : 'mdi-calendar-remove-outline'"
                                    :color="possibleTimetableCount > 0 ? 'success' : 'warning'"
                                    size="42" />
                                <div>
                                    <strong>{{ possibleTimetableCountLabel }}</strong>
                                    <span v-if="possibleTimetableCount === 1">mögliche Variante</span>
                                    <span v-else-if="possibleTimetableCount > 1">mögliche Varianten</span>
                                    <span v-else-if="filteredOutAllTimetables">
                                        Keine Varianten entsprechen den gewählten Optionen
                                    </span>
                                    <span v-else>Keine möglichen Varianten gefunden</span>
                                </div>
                            </div>

                            <div
                                v-if="timetablesTruncated"
                                class="timetable-v3__calculation-truncated-notice"
                                role="note">
                                <v-icon icon="mdi-information-outline" size="23" />
                                <span>
                                    Insgesamt wurden <strong>{{ unfilteredMaterializedTimetableCountLabel }}</strong> von
                                    <strong>{{ totalPossibleTimetableCountLabel }}</strong> möglichen Stundenplänen
                                    gespeichert. Die Optionen verändern diesen Bestand nicht.
                                </span>
                            </div>

                            <div class="timetable-v3__calculation-counts" aria-label="Zusammenfassung der Berechnung">
                                <span>
                                    <strong>{{ checkedTimetableVariationCountLabel }}</strong>
                                    Kombinationen geprüft
                                </span>
                                <span>
                                    <strong>{{ conflictingTimetableVariationCountLabel }}</strong>
                                    Konfliktvarianten ausgeschlossen
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    class="
                        timetable-v3__schedule-mode-card
                        timetable-v3__schedule-mode-card--manual
                        timetable-v3__creation-summary-card
                        timetable-v3__creation-summary-card--manual
                    "
                    aria-label="Manueller Stundenplan">
                    <span class="timetable-v3__schedule-mode-icon">
                        <v-icon icon="mdi-calendar-edit" size="30" />
                    </span>
                    <span class="timetable-v3__schedule-mode-copy">
                        <span class="timetable-v3__schedule-mode-title">Manueller Stundenplan</span>
                        <span class="timetable-v3__schedule-mode-description">
                            Sie können jetzt den aktuell ausgewählten Stundenplan übernehmen, um ihn noch weiter
                            individuell anzupassen.
                        </span>
                    </span>
                    <v-btn
                        class="timetable-v3__manual-timetable-button"
                        block
                        color="#c2410c"
                        :disabled="timetableCalculationStatus !== 'success'
                            || isSavingState
                            || timetablePageLoading
                            || !selectedTimetableResult"
                        prepend-icon="mdi-calendar-import"
                        size="large"
                        type="button"
                        variant="elevated"
                        @click="openTimetableAdoptionPage">
                        Stundenplan übernehmen
                    </v-btn>
                </section>

                <section
                    class="
                        timetable-v3__schedule-mode-card
                        timetable-v3__schedule-mode-card--options
                        timetable-v3__creation-summary-card
                        timetable-v3__creation-summary-card--options
                    "
                    :aria-busy="timetablePageLoading ? 'true' : 'false'"
                    aria-labelledby="timetable-v3-options-title">
                    <span class="timetable-v3__schedule-mode-icon">
                        <v-icon icon="mdi-tune-variant" size="30" />
                    </span>
                    <span class="timetable-v3__schedule-mode-copy">
                        <span id="timetable-v3-options-title" class="timetable-v3__schedule-mode-title">Optionen</span>
                    </span>
                    <div class="timetable-v3__filter-options">
                        <div class="timetable-v3__filter-option">
                            <span id="timetable-v3-saturday-filter-label" class="timetable-v3__filter-option-label">
                                Samstag
                            </span>
                            <v-btn-toggle
                                :model-value="timetableFilters.include_saturday"
                                class="timetable-v3__filter-option-toggle"
                                color="teal-darken-1"
                                density="comfortable"
                                divided
                                mandatory
                                variant="outlined"
                                :disabled="isLoadingState
                                    || isSavingState
                                    || timetableCalculationStatus === 'calculating'
                                    || timetablePageLoading"
                                aria-labelledby="timetable-v3-saturday-filter-label"
                                @update:model-value="updateTimetableFilter('include_saturday', $event)">
                                <v-btn
                                    v-if="timetableCalculationStatus !== 'success'
                                        || timetablePageMeta.optionCounts.includeSaturday > 0"
                                    :value="true"
                                    prepend-icon="mdi-calendar-check-outline">
                                    <span class="timetable-v3__filter-option-button-content">
                                        <span>Ja</span>
                                        <span
                                            v-if="timetableCalculationStatus === 'success' && !timetablePageLoading"
                                            class="timetable-v3__filter-option-count">
                                            {{ timetableFilterOptionCountLabels.includeSaturday }}
                                        </span>
                                    </span>
                                </v-btn>
                                <v-btn
                                    v-if="timetableCalculationStatus !== 'success'
                                        || timetablePageMeta.optionCounts.excludeSaturday > 0"
                                    :value="false"
                                    prepend-icon="mdi-calendar-remove-outline">
                                    <span class="timetable-v3__filter-option-button-content">
                                        <span>Nein</span>
                                        <span
                                            v-if="timetableCalculationStatus === 'success' && !timetablePageLoading"
                                            class="timetable-v3__filter-option-count">
                                            {{ timetableFilterOptionCountLabels.excludeSaturday }}
                                        </span>
                                    </span>
                                </v-btn>
                            </v-btn-toggle>
                        </div>
                        <div
                            v-if="timetableCalculationStatus === 'success'
                                && timetableHasVisibleFreeDayOptions"
                            class="timetable-v3__filter-option">
                            <span id="timetable-v3-free-days-filter-label" class="timetable-v3__filter-option-label">
                                Freie Tage
                            </span>
                            <v-btn-toggle
                                :model-value="timetableFilters.free_days"
                                class="
                                    timetable-v3__filter-option-toggle
                                    timetable-v3__filter-option-toggle--free-days
                                "
                                color="teal-darken-1"
                                density="comfortable"
                                mandatory
                                variant="outlined"
                                :disabled="isLoadingState || isSavingState || timetablePageLoading"
                                aria-labelledby="timetable-v3-free-days-filter-label"
                                @update:model-value="updateTimetableFilter('free_days', $event)">
                                <v-btn
                                    v-if="timetableFreeDayOptionCounts.any > 0"
                                    :value="null"
                                    :aria-label="timetableFreeDayOptionAriaLabel(null, timetableFreeDayOptionCounts.any)">
                                    <span class="timetable-v3__filter-option-button-content">
                                        <span>Egal</span>
                                        <span
                                            v-if="!timetablePageLoading"
                                            class="timetable-v3__filter-option-count">
                                            {{ timetableVariantCountLabel(timetableFreeDayOptionCounts.any) }}
                                        </span>
                                    </span>
                                </v-btn>
                                <v-btn
                                    v-for="option in timetableVisibleFreeDayOptions"
                                    :key="option.value"
                                    :value="option.value"
                                    :aria-label="timetableFreeDayOptionAriaLabel(option.value, option.count)">
                                    <span class="timetable-v3__filter-option-button-content">
                                        <span>{{ option.value }}</span>
                                        <span
                                            v-if="!timetablePageLoading"
                                            class="timetable-v3__filter-option-count">
                                            {{ timetableVariantCountLabel(option.count) }}
                                        </span>
                                    </span>
                                </v-btn>
                            </v-btn-toggle>
                        </div>
                        <div
                            v-if="timetablePageLoading"
                            class="timetable-v3__filter-loading"
                            role="status"
                            aria-live="polite"
                            aria-atomic="true">
                            <v-progress-circular
                                color="teal-darken-1"
                                indeterminate
                                :size="22"
                                :width="3" />
                            <span>Stundenpläne werden mit den gewählten Optionen neu geladen …</span>
                        </div>
                    </div>
                </section>

                <TimetableV3PossibleTimetables
                    v-if="timetableCalculationStatus === 'success' && possibleTimetableCount > 0"
                    class="timetable-v3__calculation-output"
                    :allow-saturday-lessons="timetableFilters.include_saturday"
                    :error="timetablePageError"
                    :loading="timetablePageLoading"
                    :loading-direction="timetablePageLoadingDirection"
                    :page-offset="timetablePageMeta.offset"
                    :selected-index="timetableSelectedIndex"
                    :timetables="timetableCalculationResult?.timetables || []"
                    :total-count="timetablePageMeta.total"
                    @navigate="selectTimetable" />

                <section
                    v-if="timetableCalculationStatus === 'success'
                        && timetablePageMeta.unfilteredTotal === 0
                        && timetableSolutionPlanModuleRemovalScenarios.length"
                    class="timetable-v3__calculation-output timetable-v3__solution-plan"
                    aria-labelledby="timetable-v3-solution-plan-title">
                            <div class="timetable-v3__solution-plan-heading">
                                <span class="timetable-v3__solution-plan-icon">
                                    <v-icon icon="mdi-lightbulb-on-outline" size="25" />
                                </span>
                                <div>
                                    <h4 id="timetable-v3-solution-plan-title">Lösungsplan</h4>
                                    <p>
                                        Das System hat jedes ausgewählte Modul einzeln testweise entfernt und die
                                        übrigen Unterrichte mit denselben Optionen neu geprüft. Klicken Sie auf eine
                                        mögliche Lösung, um sie anzuwenden und den Stundenplan neu zu berechnen.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="timetable-v3__solution-plan-scenarios"
                                aria-label="Mögliche Stundenpläne beim Entfernen eines Moduls"
                                role="list">
                                <div
                                    v-for="scenario in timetableSolutionPlanModuleRemovalScenarios"
                                    :key="scenario.removed_module_selection_key"
                                    class="timetable-v3__solution-plan-scenario-item"
                                    role="listitem">
                                    <button
                                        type="button"
                                        class="timetable-v3__solution-plan-scenario"
                                        :class="{
                                            'timetable-v3__solution-plan-scenario--actionable':
                                                timetableSolutionPlanScenarioApplicable(scenario),
                                        }"
                                        :disabled="!timetableSolutionPlanScenarioApplicable(scenario)
                                            || isSavingState
                                            || timetableCalculationStatus === 'calculating'"
                                        :aria-label="timetableSolutionPlanScenarioApplicable(scenario)
                                            ? `${solutionModuleDisplayCode(scenario)} weglassen und neu berechnen`
                                            : undefined"
                                        @click="applyTimetableSolutionPlanScenario(scenario)">
                                        <div class="timetable-v3__solution-plan-module">
                                            <span class="timetable-v3__solution-plan-module-icon">
                                                <v-icon icon="mdi-book-minus-outline" size="22" />
                                            </span>
                                            <div>
                                                <span>Dieses Modul weglassen</span>
                                                <strong>
                                                    {{ solutionModuleDisplayCode(scenario) }}
                                                    <small
                                                        v-if="solutionModuleDisplayNameVisible(scenario)">
                                                        · {{ solutionModuleDisplayName(scenario) }}
                                                    </small>
                                                </strong>
                                                <span
                                                    v-if="timetableSolutionPlanScenarioApplicable(scenario)"
                                                    class="timetable-v3__solution-plan-action-label">
                                                    Anklicken und neu berechnen
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            v-if="scenario.status === 'calculated'
                                                && normalizedTimetableCalculationCount(
                                                    scenario.possible_timetable_count,
                                                ) > 0"
                                            class="timetable-v3__solution-plan-result">
                                            <strong>
                                                {{ timetableSolutionPlanCountLabel(scenario.possible_timetable_count) }}
                                            </strong>
                                            <span>
                                                {{ normalizedTimetableCalculationCount(
                                                    scenario.possible_timetable_count,
                                                ) === 1
                                                    ? 'möglicher Stundenplan'
                                                    : 'mögliche Stundenpläne' }}
                                            </span>
                                        </div>
                                        <div
                                            v-else-if="scenario.status === 'calculated'"
                                            class="
                                                timetable-v3__solution-plan-result
                                                timetable-v3__solution-plan-result--empty
                                            ">
                                            <v-icon icon="mdi-calendar-remove-outline" size="22" />
                                            <strong>Auch ohne dieses Modul keine mögliche Variante</strong>
                                        </div>
                                        <div
                                            v-else-if="scenario.status === 'combination_limit_exceeded'"
                                            class="
                                                timetable-v3__solution-plan-result
                                                timetable-v3__solution-plan-result--limited
                                            ">
                                            <v-icon icon="mdi-alert-outline" size="22" />
                                            <div>
                                                <strong>Berechnungslimit überschritten</strong>
                                                <span>Für dieses Szenario sind zu viele Kombinationen zu prüfen.</span>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <p
                                v-if="timetableSolutionPlanHasOnlyZeroCalculatedScenarios"
                                class="timetable-v3__solution-plan-zero-note">
                                Auch durch das Entfernen eines einzelnen Moduls entsteht noch kein möglicher Stundenplan.
                            </p>

                            <p class="timetable-v3__solution-plan-note">
                                <v-icon icon="mdi-lock-outline" size="18" />
                                Die Prüfung allein verändert Ihre Auswahl nicht. Erst durch Anklicken einer möglichen
                                Lösung wird das angezeigte Modul entfernt.
                            </p>
                </section>
            </div>
            </template>

            <template v-else-if="currentStep === 'adoption'">
                <div class="timetable-v3__adoption-cards mt-4">
                    <section
                        class="
                            timetable-v3__schedule-mode-card
                            timetable-v3__schedule-mode-card--manual
                            timetable-v3__schedule-mode-card--selected
                            timetable-v3__adoption-card
                            timetable-v3__adoption-card--manual
                        "
                        aria-label="Manueller Stundenplan">
                        <span class="timetable-v3__schedule-mode-icon">
                            <v-icon icon="mdi-calendar-edit" size="30" />
                        </span>
                        <span class="timetable-v3__schedule-mode-copy">
                            <span class="timetable-v3__adoption-heading">
                                <span class="timetable-v3__schedule-mode-title">Manueller Stundenplan</span>
                                <v-btn
                                    class="timetable-v3__adoption-pdf-button"
                                    color="#c2410c"
                                    :disabled="pdfExporting || timetablePageLoading || isLoadingState"
                                    :loading="pdfExporting"
                                    prepend-icon="mdi-file-pdf-box"
                                    size="large"
                                    type="button"
                                    variant="outlined"
                                    @click="downloadManualTimetablePdf">
                                    PDF
                                </v-btn>
                            </span>
                        </span>
                        <div
                            class="
                                timetable-v3__selected-modules
                                timetable-v3__schedule-mode-selected-modules
                                timetable-v3__adoption-selected-modules
                            "
                            aria-live="polite">
                            <div class="timetable-v3__selected-modules-heading">
                                <span class="timetable-v3__selected-modules-heading-label">
                                    <v-icon icon="mdi-check-circle-outline" size="18" />
                                    Ausgewählte Module
                                </span>
                                <span class="timetable-v3__selected-modules-summary">
                                    {{ adoptionSelectedModuleCount }}
                                    {{ adoptionSelectedModuleCount === 1 ? 'Modul' : 'Module' }}
                                    <template v-if="!isManualTimetableAdoption">
                                        · {{ adoptionSelectedModuleHoursLabel }} Std.
                                    </template>
                                </span>
                            </div>
                            <div
                                v-if="adoptionSelectedModules.length"
                                class="timetable-v3__selected-modules-list">
                                <v-chip
                                    v-for="module in adoptionSelectedModules"
                                    :key="module.code || module.selection_key"
                                    class="timetable-v3__selected-module-chip"
                                    color="#c2410c"
                                    closable
                                    close-icon="mdi-close-circle"
                                    :close-label="`${moduleDisplayCode(module)} aus dem manuellen Stundenplan entfernen`"
                                    label
                                    size="small"
                                    variant="tonal"
                                    @click:close.stop="removeAdoptionModule(module)">
                                    <strong>{{ moduleDisplayCode(module) }}</strong>
                                    <span v-if="moduleDisplayNameVisible(module)">
                                        &nbsp;· {{ moduleDisplayName(module) }}
                                    </span>
                                </v-chip>
                            </div>
                            <div v-else class="timetable-v3__selected-modules-empty">
                                Keine Module ausgewählt.
                            </div>
                        </div>
                    </section>
                </div>

                <section
                    v-if="!studentSelectionDetailsLoading && !studentSelectionDetailsError"
                    class="timetable-v3__manual-module-catalog mt-4"
                    aria-labelledby="timetable-v3-manual-module-catalog-title">
                    <div
                        class="timetable-v3__manual-module-catalog-headings"
                        :class="{
                            'timetable-v3__manual-module-catalog-headings--with-student': planningMode === 'with_student',
                        }">
                        <button
                            type="button"
                            class="
                                timetable-v3__main-module-heading
                                timetable-v3__manual-module-catalog-heading
                                timetable-v3__manual-module-catalog-heading-button
                            "
                            :class="{
                                'timetable-v3__manual-module-catalog-heading--selected': planningMode !== 'with_student'
                                    || manualModuleCatalogView === 'student',
                            }"
                            :disabled="planningMode !== 'with_student'"
                            :aria-pressed="planningMode === 'with_student'
                                ? manualModuleCatalogView === 'student'
                                : null"
                            @click="showManualModuleCatalog('student')">
                            <div class="timetable-v3__main-module-heading-icon">
                                <v-icon icon="mdi-bookshelf" size="21" />
                            </div>
                            <div>
                                <h4 id="timetable-v3-manual-module-catalog-title">
                                    {{ planningMode === 'with_student' ? 'Studierenden Module' : 'Hauptmodule' }}
                                </h4>
                                <p>
                                    {{ planningMode === 'with_student'
                                        ? 'Die mit dem ausgewählten Studierenden verbundenen Module stehen bereit.'
                                        : 'Alle verfügbaren Module und Unterrichte stehen für den manuellen Stundenplan bereit.' }}
                                </p>
                            </div>
                        </button>

                        <button
                            v-if="planningMode === 'with_student'"
                            type="button"
                            class="
                                timetable-v3__main-module-heading
                                timetable-v3__manual-module-catalog-heading
                                timetable-v3__manual-module-catalog-heading-button
                            "
                            :class="{
                                'timetable-v3__manual-module-catalog-heading--selected': manualModuleCatalogView === 'main',
                            }"
                            :aria-pressed="manualModuleCatalogView === 'main'"
                            aria-label="Hauptmodule"
                            @click="showManualModuleCatalog('main')">
                            <div class="timetable-v3__main-module-heading-icon">
                                <v-icon icon="mdi-bookshelf" size="21" />
                            </div>
                            <div>
                                <h4>Hauptmodule</h4>
                                <p>
                                    Alle verfügbaren Module und Unterrichte stehen für den manuellen Stundenplan bereit.
                                </p>
                            </div>
                        </button>
                    </div>

                    <div
                        class="timetable-v3__module-group-cards"
                        :class="{ 'timetable-v3__module-group-cards--main': manualModuleCatalogUsesMainGroups }"
                        :aria-label="manualModuleCatalogUsesMainGroups ? 'Hauptmodule' : 'Studierenden Module'">
                        <button
                            v-for="group in visibleManualModuleCatalogGroups"
                            :key="`manual-${manualModuleCatalogView}-${group.key}`"
                            type="button"
                            class="timetable-v3__module-group-card timetable-v3__module-group-card--read-only"
                            :class="[
                                manualModuleCatalogUsesMainGroups
                                    ? 'timetable-v3__module-group-card--main'
                                    : `timetable-v3__module-group-card--${group.key}`,
                                {
                                    'timetable-v3__module-group-card--active': manualModuleGroupActive(group),
                                    'timetable-v3__module-group-card--manual-main-active':
                                        manualModuleCatalogUsesMainGroups && manualModuleGroupActive(group),
                                },
                            ]"
                            :aria-expanded="manualModuleGroupActive(group)"
                            aria-controls="timetable-v3-manual-module-group-panel"
                            @click="toggleManualModuleGroup(group)">
                            <span class="timetable-v3__module-group-card-topline">
                                <span class="timetable-v3__module-group-card-icon">
                                    <v-icon :icon="moduleGroupIcon(group)" size="19" />
                                </span>
                                <span class="timetable-v3__module-group-card-count">
                                    {{ selectedModuleCountForGroup(group) }}/{{ group.count }}
                                </span>
                            </span>
                            <span
                                v-if="manualModuleGroupAlreadyPlanned(group)"
                                class="timetable-v3__module-planned">
                                Bereits verplant!
                            </span>
                            <span
                                v-if="manualModuleCatalogUsesMainGroups
                                    && manualModuleGroupNotIntended(group)"
                                class="timetable-v3__module-not-intended">
                                Nicht vorgesehen!
                            </span>
                            <span
                                class="timetable-v3__module-group-card-title"
                                :class="{ 'timetable-v3__module-group-card-title--main': manualModuleCatalogUsesMainGroups }">
                                <template v-if="manualModuleCatalogUsesMainGroups">
                                    <strong class="timetable-v3__main-module-code">{{ moduleDisplayCode(group) }}</strong>
                                    <span
                                        v-if="moduleDisplayNameVisible(group)"
                                        class="timetable-v3__main-module-name">
                                        {{ moduleDisplayName(group) }}
                                    </span>
                                </template>
                                <template v-else>{{ group.label }}</template>
                            </span>
                            <span class="timetable-v3__module-group-card-description">{{ group.description }}</span>
                            <span class="timetable-v3__module-group-card-active-mark" aria-hidden="true" />
                        </button>
                    </div>

                    <transition name="timetable-v3-module-panel" mode="out-in">
                        <section
                            v-if="activeManualModuleSelectionGroup"
                            :key="`manual-${manualModuleCatalogView}-${activeManualModuleSelectionGroup.key}`"
                            id="timetable-v3-manual-module-group-panel"
                            class="timetable-v3__module-group-panel timetable-v3__manual-module-group-panel"
                            :class="manualModuleCatalogUsesMainGroups
                                ? 'timetable-v3__module-group-panel--main'
                                : `timetable-v3__module-group-panel--${activeManualModuleSelectionGroup.key}`">
                            <div
                                v-if="activeManualModuleSelectionGroup.modules.length"
                                class="timetable-v3__module-grid">
                                <button
                                    v-for="module in activeManualModuleSelectionGroup.modules"
                                    :key="`manual-${manualModuleCatalogView}-${module.selection_key}`"
                                    type="button"
                                    class="timetable-v3__module-tile timetable-v3__module-tile--read-only"
                                    @click="openManualModuleCoursesDialog(module)">
                                    <span class="timetable-v3__module-check">
                                        <v-icon icon="mdi-chevron-right" size="16" />
                                    </span>
                                    <span class="timetable-v3__module-main">
                                        <span
                                            v-if="manualModuleCatalogUsesMainGroups
                                                && module.is_intended_for_selection === false"
                                            class="timetable-v3__module-not-intended">
                                            Nicht vorgesehen!
                                        </span>
                                        <span
                                            v-if="manualModuleAlreadyPlanned(module)"
                                            class="timetable-v3__module-planned">
                                            Bereits verplant!
                                        </span>
                                        <span class="timetable-v3__module-code">{{ moduleDisplayCode(module) }}</span>
                                        <span
                                            v-if="moduleDisplayNameVisible(module)"
                                            class="timetable-v3__module-name">
                                            {{ moduleDisplayName(module) }}
                                        </span>
                                        <span class="timetable-v3__module-meta">
                                            <span v-if="module.status_label" class="timetable-v3__module-status">
                                                {{ module.status_label }}
                                            </span>
                                            <span v-if="module.semester_label">{{ module.semester_label }}</span>
                                            <span v-if="module.hours_label">{{ module.hours_label }}</span>
                                            <span class="timetable-v3__module-course-count">
                                            {{ manualModuleCourseCount(module) }} Unterrichte
                                            </span>
                                            <span
                                                v-for="(grade, gradeIndex) in module.grades"
                                                :key="`${module.selection_key}-${gradeIndex}`"
                                                class="timetable-v3__module-grade">
                                                {{ grade }}
                                            </span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                            <div v-else class="timetable-v3__module-list-empty">
                                Keine Module vorhanden.
                            </div>
                        </section>
                    </transition>
                </section>

                <TimetableV3PossibleTimetables
                    v-if="isManualTimetableAdoption || selectedTimetableResult"
                    class="timetable-v3__adoption-timetable"
                    :allow-saturday-lessons="isManualTimetableAdoption || timetableFilters.include_saturday"
                    :empty-hour-rows="schoolHours"
                    :empty-timetable="isManualTimetableAdoption"
                    highlight-multiple-entries
                    :manual-timetable="manualTimetable"
                    :navigation-visible="false"
                    :page-offset="timetablePageMeta.offset"
                    :position-visible="false"
                    show-all-hours
                    :selected-index="timetableSelectedIndex"
                    :timetables="adoptionTimetables"
                    :total-count="timetablePageMeta.total" />

                <div class="timetable-v3__page-actions timetable-v3__page-actions--split">
                    <v-btn
                        class="timetable-v3__restart-button"
                        size="large"
                        color="error"
                        variant="outlined"
                        prepend-icon="mdi-restart"
                        :disabled="!hasPlanningSelectionContext || isLoadingState || isSavingState || timetablePageLoading"
                        @click="restartPlanning">
                        Neustart
                    </v-btn>
                    <v-btn
                        class="timetable-v3__back-button"
                        size="large"
                        color="primary"
                        variant="outlined"
                        prepend-icon="mdi-arrow-left"
                        :disabled="isLoadingState || isSavingState || timetablePageLoading"
                        @click="returnFromTimetableAdoptionStep">
                        Zurück
                    </v-btn>
                </div>
            </template>

            <div v-if="currentStep === 'creation'" class="timetable-v3__page-actions timetable-v3__page-actions--split">
                <v-btn
                    class="timetable-v3__restart-button"
                    size="large"
                    color="error"
                    variant="outlined"
                    prepend-icon="mdi-restart"
                    :disabled="!hasPlanningSelectionContext || isLoadingState || isSavingState || timetableCalculationStatus === 'calculating' || timetablePageLoading"
                    @click="restartPlanning">
                    Neustart
                </v-btn>
                <v-btn
                    class="timetable-v3__back-button"
                    size="large"
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-arrow-left"
                    :disabled="isLoadingState || isSavingState || timetableCalculationStatus === 'calculating' || timetablePageLoading"
                    @click="returnFromTimetableCreationStep">
                    Zurück
                </v-btn>
            </div>
        </div>

        <v-dialog v-model="moduleCoursesDialogOpen" max-width="820" persistent scrollable>
            <v-card rounded="lg" class="timetable-v3__module-courses-dialog">
                <v-card-title class="timetable-v3__module-courses-dialog-title d-flex align-center ga-3 pa-5 pb-2">
                    <span class="timetable-v3__module-courses-dialog-icon">
                        <v-icon icon="mdi-book-open-variant-outline" size="23" />
                    </span>
                    <span>
                        Unterrichte für {{ moduleDisplayCode(moduleCourseDialogModule) || 'Modul' }}
                    </span>
                </v-card-title>

                <v-card-text class="px-5 pt-3">
                    <div
                        v-if="moduleDisplayNameVisible(moduleCourseDialogModule)"
                        class="timetable-v3__module-courses-dialog-subtitle">
                        {{ moduleDisplayName(moduleCourseDialogModule) }}
                    </div>
                    <div
                        v-if="!moduleCoursesDialogReadOnly"
                        class="timetable-v3__module-courses-dialog-toolbar">
                        <div class="timetable-v3__module-courses-dialog-summary" aria-live="polite">
                            <strong>{{ selectedModuleCourseCount }}</strong>
                            von {{ moduleCourseDialogCourses.length }} Unterrichten ausgewählt
                        </div>
                        <div class="timetable-v3__module-courses-dialog-actions">
                            <v-btn
                                size="small"
                                variant="tonal"
                                color="primary"
                                prepend-icon="mdi-checkbox-multiple-marked-outline"
                                :disabled="!moduleCourseDialogCourses.length || allModuleCoursesSelected"
                                @click="selectAllModuleCourses">
                                Alle auswählen
                            </v-btn>
                            <v-btn
                                size="small"
                                variant="text"
                                color="primary"
                                prepend-icon="mdi-checkbox-multiple-blank-outline"
                                :disabled="!hasSelectedModuleCourses"
                                @click="deselectAllModuleCourses">
                                Alle abwählen
                            </v-btn>
                        </div>
                    </div>
                    <v-alert
                        v-if="!moduleCoursesDialogReadOnly && moduleSelectionLimitMessage"
                        class="mt-3"
                        type="warning"
                        density="compact"
                        variant="tonal"
                        closable
                        @click:close="moduleSelectionLimitMessage = ''">
                        {{ moduleSelectionLimitMessage }}
                    </v-alert>

                    <div v-if="moduleCourseDialogCourses.length" class="timetable-v3__module-course-list mt-4">
                        <component
                            :is="moduleCoursesDialogInteractive ? 'button' : 'article'"
                            v-for="course in moduleCourseDialogCourses"
                            :key="course.key"
                            :type="moduleCoursesDialogInteractive ? 'button' : null"
                            class="timetable-v3__module-course"
                            :class="{
                                'timetable-v3__module-course--read-only': !moduleCoursesDialogInteractive,
                                'timetable-v3__module-course--selected': displayedModuleCourseSelected(course),
                            }"
                            :role="moduleCoursesDialogInteractive ? 'checkbox' : null"
                            :aria-checked="moduleCoursesDialogInteractive ? displayedModuleCourseSelected(course) : null"
                            @click="moduleCoursesDialogInteractive && toggleDisplayedModuleCourse(course)">
                            <span v-if="moduleCoursesDialogInteractive" class="timetable-v3__module-course-check">
                                <v-icon
                                    :icon="displayedModuleCourseSelected(course) ? 'mdi-check' : 'mdi-checkbox-blank-outline'"
                                    size="18" />
                            </span>
                            <span class="timetable-v3__module-course-copy">
                                <span class="timetable-v3__module-course-title">{{ moduleCourseTitle(course) }}</span>
                                <span
                                    v-if="moduleCourseSubtitle(course)"
                                    class="timetable-v3__module-course-subtitle">
                                    {{ moduleCourseSubtitle(course) }}
                                </span>
                                <span class="timetable-v3__module-course-meta">
                                    <template
                                        v-for="scheduleRow in courseScheduleRows(course)"
                                        :key="scheduleRow.key">
                                        <span class="timetable-v3__module-course-schedule">
                                            {{ scheduleRow.label }}
                                        </span>
                                        <span
                                            v-for="overlapLabel in courseScheduleRowOverlapLabels(course, scheduleRow)"
                                            :key="`${scheduleRow.key}:${overlapLabel}`"
                                            class="timetable-v3__module-course-overlap">
                                            ({{ overlapLabel }})
                                        </span>
                                    </template>
                                    <span v-if="course.hours_label" class="timetable-v3__module-course-hours">
                                        <v-icon icon="mdi-clock-outline" size="12" />
                                        {{ course.hours_label }}
                                    </span>
                                    <span
                                        v-if="course.instruction_label"
                                        class="timetable-v3__module-course-instruction">
                                        <v-icon icon="mdi-laptop" size="12" />
                                        {{ course.instruction_label }}
                                    </span>
                                    <span v-if="course.teacher">{{ course.teacher }}</span>
                                    <span v-if="course.rooms_label">Raum {{ course.rooms_label }}</span>
                                    <span v-if="course.block_label">{{ course.block_label }}</span>
                                    <span v-if="course.dates_count">
                                        {{ course.dates_count }} {{ course.dates_count === 1 ? 'Termin' : 'Termine' }}
                                    </span>
                                </span>
                            </span>
                        </component>
                    </div>
                    <div v-else class="timetable-v3__module-course-empty mt-4">
                        <template v-if="moduleCoursesDialogReadOnly && moduleCourseCount(moduleCourseDialogModule) > 0">
                            Alle Unterrichte dieses Moduls sind bereits im manuellen Stundenplan.
                        </template>
                        <template v-else>
                            Für dieses Modul sind keine Unterrichte im importierten Stundenplan vorhanden.
                        </template>
                    </div>
                </v-card-text>

                <v-card-actions class="px-5 pb-5 pt-3">
                    <v-spacer />
                    <template v-if="moduleCoursesDialogReadOnly">
                        <v-btn
                            color="primary"
                            variant="text"
                            size="large"
                            @click="cancelManualModuleCoursesDialog">
                            Abbrechen
                        </v-btn>
                        <v-btn
                            color="primary"
                            variant="flat"
                            size="large"
                            :disabled="!manualPendingCourseKeys.length"
                            @click="planManualModuleCourses">
                            Verplanen
                        </v-btn>
                    </template>
                    <v-btn v-else color="primary" variant="flat" size="large" @click="closeModuleCoursesDialog">
                        Bestätigen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="studentInfoDialogOpen" max-width="620" persistent>
            <v-card rounded="lg" class="timetable-v3__info-dialog">
                <v-card-title class="timetable-v3__info-dialog-title d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-information-outline" color="primary" />
                    Studierenden-Information
                </v-card-title>

                <v-card-text class="px-5 pt-4">
                    <div class="timetable-v3__info-grid">
                        <div class="timetable-v3__info-primary-row">
                            <div class="timetable-v3__info-item">
                                <span class="timetable-v3__info-label">Klasse</span>
                                <span class="timetable-v3__info-value">{{ selectedStudentClass || '–' }}</span>
                            </div>
                            <div class="timetable-v3__info-item">
                                <span class="timetable-v3__info-label">Name</span>
                                <span class="timetable-v3__info-value">{{ selectedStudentFullName || '–' }}</span>
                            </div>
                            <div class="timetable-v3__info-item">
                                <span class="timetable-v3__info-label">Religion</span>
                                <span class="timetable-v3__info-value">{{ selectedStudentReligion || '–' }}</span>
                            </div>
                        </div>
                        <div class="timetable-v3__info-secondary-row">
                            <div class="timetable-v3__info-item">
                                <span class="timetable-v3__info-label">Unterrichtsart</span>
                                <span class="timetable-v3__info-value">{{ selectedStudentInstructionType }}</span>
                            </div>
                        </div>
                        <div class="timetable-v3__info-secondary-row">
                            <div class="timetable-v3__info-item">
                                <span class="timetable-v3__info-label">Stundentafel</span>
                                <span class="timetable-v3__info-value">{{ selectedStudentSubjectPlan }}</span>
                            </div>
                        </div>
                        <div class="timetable-v3__info-secondary-row timetable-v3__info-secondary-row--semester">
                            <div class="timetable-v3__info-item">
                                <span class="timetable-v3__info-label">Semester</span>
                                <span class="timetable-v3__info-value">
                                    {{ selectedStudentSemesterLabel }}
                                    <button
                                        v-if="selectedStudentOriginalSchoolLevel"
                                        type="button"
                                        class="timetable-v3__info-imported-school-level timetable-v3__info-imported-school-level--editable"
                                        title="Schulstufe ändern oder Originalwert wiederherstellen"
                                        @click="openSchoolLevelDialog">
                                        ({{ selectedStudentImportedSchoolLevel }})
                                    </button>
                                    <span v-else class="timetable-v3__info-imported-school-level">
                                        ({{ selectedStudentImportedSchoolLevel }})
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div v-if="studentSelectionDetailsLoading" class="timetable-v3__info-secondary-row timetable-v3__info-status">
                            <v-progress-circular indeterminate color="primary" size="18" width="2" />
                            <span>Berechnung wird geladen</span>
                        </div>
                        <div v-else-if="studentSelectionDetailsError" class="timetable-v3__info-secondary-row timetable-v3__info-status timetable-v3__info-status--error">
                            Die berechnete Auswahl konnte nicht geladen werden.
                        </div>
                        <template v-else>
                            <div
                                v-for="item in selectedStudentCalculationItems"
                                :key="item.key"
                                class="timetable-v3__info-secondary-row timetable-v3__info-secondary-row--calculation">
                                <div class="timetable-v3__info-item">
                                    <span class="timetable-v3__info-label">{{ item.label }}</span>
                                    <span class="timetable-v3__info-value">{{ item.value }}</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </v-card-text>

                <v-card-actions class="px-5 pb-5 pt-4">
                    <v-spacer />
                    <v-btn color="primary" variant="flat" size="large" @click="closeStudentInfoDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="studyInfoDialogOpen" max-width="960" persistent scrollable>
            <v-card rounded="lg" class="timetable-v3__info-dialog timetable-v3__study-info-dialog">
                <v-card-title class="timetable-v3__info-dialog-title d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-school-outline" color="teal-darken-1" />
                    Informationen zum Studium
                </v-card-title>

                <v-card-text class="timetable-v3__study-info-content px-5 pt-4">
                    <div v-if="studentSelectionDetailsLoading" class="timetable-v3__study-info-feedback">
                        <v-progress-circular indeterminate color="teal-darken-1" size="22" width="3" />
                        <span>Moduldaten werden geladen</span>
                    </div>
                    <div
                        v-else-if="studentSelectionDetailsError"
                        class="timetable-v3__study-info-feedback timetable-v3__study-info-feedback--error">
                        Die Moduldaten konnten nicht geladen werden.
                    </div>
                    <div v-else class="timetable-v3__study-module-groups">
                        <section
                            v-for="group in studentStudyModuleGroups"
                            :key="group.key"
                            class="timetable-v3__study-module-group"
                            :class="`timetable-v3__study-module-group--${group.key}`"
                            :aria-labelledby="`study-module-group-${group.key}`">
                            <div class="timetable-v3__study-module-group-header">
                                <div>
                                    <h3
                                        :id="`study-module-group-${group.key}`"
                                        class="timetable-v3__study-module-group-title">
                                        {{ group.label }}
                                    </h3>
                                    <div class="timetable-v3__study-module-group-count">
                                        {{ group.count }} {{ group.count === 1 ? 'Modul' : 'Module' }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="group.modules.length" class="timetable-v3__study-module-list">
                                <div
                                    v-for="module in group.modules"
                                    :key="`${module.code}-${module.grade}`"
                                    class="timetable-v3__study-module-row">
                                    <span class="timetable-v3__study-module-copy">
                                        <span class="timetable-v3__study-module-code">{{ moduleDisplayCode(module) }}</span>
                                        <span
                                            v-if="moduleDisplayNameVisible(module)"
                                            class="timetable-v3__study-module-name">
                                            {{ moduleDisplayName(module) }}
                                        </span>
                                    </span>
                                    <span class="timetable-v3__study-module-grades">
                                        <span
                                            v-for="(grade, gradeIndex) in module.grades"
                                            :key="`${grade.status}-${grade.value}-${gradeIndex}`"
                                            class="timetable-v3__study-module-grade"
                                            :class="`timetable-v3__study-module-grade--${grade.status}`"
                                            :aria-label="`Note ${grade.value}`">
                                            {{ grade.value }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div v-else class="timetable-v3__study-module-empty">
                                Keine Module
                            </div>
                        </section>
                    </div>
                </v-card-text>

                <v-card-actions class="px-5 pb-5 pt-4">
                    <v-spacer />
                    <v-btn color="teal-darken-1" variant="flat" size="large" @click="closeStudyInfoDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="schoolLevelDialogOpen" max-width="640" persistent>
            <v-card rounded="lg" class="timetable-v3__school-level-dialog">
                <v-card-title class="timetable-v3__school-level-dialog-title d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-school-outline" color="error" />
                    Schulstufe korrigieren
                </v-card-title>

                <v-card-text class="px-5 pt-3">
                    <p class="timetable-v3__school-level-dialog-description">
                        Wähle eine gültige Schulstufe. Der ursprünglich importierte Wert bleibt als Rückfalloption erhalten.
                    </p>

                    <div class="timetable-v3__school-level-section">
                        <div class="timetable-v3__school-level-section-label">Ursprünglich gespeichert</div>
                        <button
                            type="button"
                            class="timetable-v3__school-level-option timetable-v3__school-level-option--original"
                            :class="{ 'timetable-v3__school-level-option--selected': schoolLevelDialogSelection === schoolLevelDialogOriginalValue }"
                            role="radio"
                            :aria-checked="schoolLevelDialogSelection === schoolLevelDialogOriginalValue"
                            :disabled="schoolLevelDialogSaving"
                            @click="selectSchoolLevelDialogValue(schoolLevelDialogOriginalValue)">
                            <span class="timetable-v3__school-level-option-value">{{ schoolLevelDialogOriginalValue }}</span>
                            <span class="timetable-v3__school-level-option-meta">Importwert</span>
                        </button>
                    </div>

                    <div class="timetable-v3__school-level-section">
                        <div class="timetable-v3__school-level-section-label">Gültige Schulstufen</div>
                        <div class="timetable-v3__school-level-options" role="radiogroup" aria-label="Gültige Schulstufe auswählen">
                            <button
                                v-for="option in selectedStudentSchoolLevelOptions"
                                :key="option.value"
                                type="button"
                                class="timetable-v3__school-level-option"
                                :class="{ 'timetable-v3__school-level-option--selected': schoolLevelDialogSelection === option.value }"
                                role="radio"
                                :aria-checked="schoolLevelDialogSelection === option.value"
                                :disabled="schoolLevelDialogSaving"
                                @click="selectSchoolLevelDialogValue(option.value)">
                                <span class="timetable-v3__school-level-option-value">{{ option.value }}</span>
                                <span class="timetable-v3__school-level-option-meta">{{ option.semester }}. Semester</span>
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="schoolLevelDialogError"
                        class="timetable-v3__school-level-dialog-error"
                        role="alert">
                        {{ schoolLevelDialogError }}
                    </div>
                </v-card-text>

                <v-card-actions class="px-5 pb-5 pt-3">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        size="large"
                        :disabled="schoolLevelDialogSaving"
                        @click="closeSchoolLevelDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        size="large"
                        :loading="schoolLevelDialogSaving"
                        :disabled="!canSaveSchoolLevelDialog"
                        @click="saveSchoolLevelDialog">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="studentDialogOpen" max-width="620">
            <v-card rounded="lg" class="timetable-v3__student-dialog">
                <v-card-title class="timetable-v3__student-dialog-title d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-account-school-outline" />
                    Studierenden auswählen
                </v-card-title>

                <v-card-text class="px-5">
                    <div class="timetable-v3__student-total font-weight-medium mb-3">
                        {{ studentTotalCountLabel }}
                    </div>

                    <v-text-field
                        ref="studentSearchField"
                        v-model="studentSearch"
                        class="timetable-v3__student-search"
                        label="Name oder Klasse suchen"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        :loading="studentOptionsLoading"
                        clearable
                        hide-details="auto"
                        @keydown.enter.prevent="selectFirstFilteredStudent" />

                    <div v-if="studentOptionsError" class="timetable-v3__student-feedback text-error mt-3">
                        Die Studierenden konnten nicht geladen werden.
                    </div>

                    <v-list
                        v-else-if="studentSearchReady"
                        class="timetable-v3__student-list mt-3"
                        max-height="340"
                        density="comfortable">
                        <v-list-item
                            v-for="student in filteredStudents"
                            :key="student.student_code"
                            :title="studentDisplayLabel(student)"
                            :subtitle="student.email || undefined"
                            prepend-icon="mdi-account-outline"
                            append-icon="mdi-chevron-right"
                            rounded="lg"
                            @click="selectStudent(student)" />

                        <div v-if="!filteredStudents.length" class="timetable-v3__student-list-empty">
                            Keine Studierenden gefunden
                        </div>
                    </v-list>

                    <div v-else class="timetable-v3__student-list-empty mt-3">
                        Mindestens 2 Zeichen eingeben
                    </div>
                </v-card-text>

                <v-card-actions class="px-5 pb-5">
                    <v-spacer />
                    <v-btn
                        class="timetable-v3__student-dialog-action"
                        size="large"
                        variant="text"
                        @click="closeStudentDialog">
                        Abbrechen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-sheet>
</template>

<script>
import { mapWritableState } from 'pinia'
import {
    canonicalTimetableCourseLabel,
    canonicalTimetableModuleName,
} from './courseLabels'
import TimetableV3PossibleTimetables from './TimetableV3PossibleTimetables.vue'
import {
    overviewPdf as downloadStudentTimetableOverviewPdf,
    robotStudents as loadRobotStudents,
    schoolHours as loadStudentTimetableSchoolHours,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentsTimetablesController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import {
    show as loadV3StudentInformation,
    updateSchoolLevel as updateV3StudentSchoolLevel,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StudentInformationController'
import {
    show as showTimetableV3State,
    update as updateTimetableV3State,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StateController'
import {
    show as showTimetableV3Timetable,
    update as updateTimetableV3Timetable,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3TimetableController'

const WITH_STUDENT = 'with_student'
const WITHOUT_STUDENT = 'without_student'
const AUTOMATIC_TIMETABLE = 'automatic'
const MANUAL_TIMETABLE = 'manual'
const MANUAL_STUDENT_MODULE_CATALOG = 'student'
const MANUAL_MAIN_MODULE_CATALOG = 'main'
const BLANK_MANUAL_TIMETABLE_DRAFT = 'blank'
const AUTOMATIC_MANUAL_TIMETABLE_DRAFT = 'automatic'
const AUTOMATIC_TIMETABLE_ALLOWS_SATURDAY = true
const MANUAL_TIMETABLE_PDF_WEEKDAYS = Object.freeze([
    { label: 'Montag', value: 1 },
    { label: 'Dienstag', value: 2 },
    { label: 'Mittwoch', value: 3 },
    { label: 'Donnerstag', value: 4 },
    { label: 'Freitag', value: 5 },
    { label: 'Samstag', value: 6 },
])
const DEFAULT_TIMETABLE_FILTERS = Object.freeze({
    include_saturday: true,
    free_days: null,
})
const MAX_FREE_DAYS = 5
const MAX_SELECTED_MODULES = 10
const MAX_SELECTED_MODULE_HOURS = 30
const SELECTION_STEP = 'selection'
const MODULE_SELECTION_STEP = 'modules'
const TIMETABLE_CREATION_STEP = 'creation'
const TIMETABLE_ADOPTION_STEP = 'adoption'
const TIMETABLE_PAGE_LABELS = Object.freeze({
    [SELECTION_STEP]: '1',
    [TIMETABLE_CREATION_STEP]: '2B',
})
const TIMETABLE_RESULT_STEPS = [TIMETABLE_CREATION_STEP, TIMETABLE_ADOPTION_STEP]
const TIMETABLE_ADOPTION_RETURN_STEPS = [MODULE_SELECTION_STEP, TIMETABLE_CREATION_STEP]
const TIMETABLE_V3_SELECTION_PATH = '/admin/students-timetables/timetable-v3/overview'
const TIMETABLE_V3_MODULE_SELECTION_PATH = '/admin/students-timetables/timetable-v3/modules'
const TIMETABLE_V3_CREATION_PATH = '/admin/students-timetables/timetable-v3/creation'
const TIMETABLE_V3_ADOPTION_PATH = '/admin/students-timetables/timetable-v3/adoption'
const TIMETABLE_CALCULATION_PROGRESS_PHASES = [
    'preparing',
    'checking',
    'materializing',
    'analyzing_solutions',
    'compacting',
    'persisting',
    'complete',
]
const TIMETABLES_PER_PAGE = 100
const MAX_MATERIALIZED_TIMETABLES = 2000
const WORKSPACE_ID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

const normalizedWorkspaceId = workspaceId => {
    const normalizedValue = String(workspaceId || '').trim()

    return WORKSPACE_ID_PATTERN.test(normalizedValue) ? normalizedValue.toLowerCase() : ''
}

const createWorkspaceId = () => {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID()
    }

    const randomValues = new Uint8Array(16)
    if (typeof globalThis.crypto?.getRandomValues === 'function') {
        globalThis.crypto.getRandomValues(randomValues)
    } else {
        randomValues.forEach((_, index) => {
            randomValues[index] = Math.floor(Math.random() * 256)
        })
    }
    randomValues[6] = (randomValues[6] & 0x0f) | 0x40
    randomValues[8] = (randomValues[8] & 0x3f) | 0x80
    const hexadecimal = [...randomValues]
        .map(value => value.toString(16).padStart(2, '0'))
        .join('')

    return [
        hexadecimal.slice(0, 8),
        hexadecimal.slice(8, 12),
        hexadecimal.slice(12, 16),
        hexadecimal.slice(16, 20),
        hexadecimal.slice(20),
    ].join('-')
}

const normalizedPlanningContext = (planningMode, studentCode = '') => {
    const normalizedStudentCode = String(studentCode || '').trim()

    if (planningMode === WITHOUT_STUDENT) {
        return {
            planning_mode: WITHOUT_STUDENT,
            student_code: null,
        }
    }

    if (planningMode !== WITH_STUDENT || !normalizedStudentCode) return null

    return {
        planning_mode: WITH_STUDENT,
        student_code: normalizedStudentCode,
    }
}

const timetableV3RouteLocation = (path, planningContext, workspaceId) => {
    return {
        path,
        query: {
            ...(workspaceId ? { workspace_id: workspaceId } : {}),
            ...(planningContext ? { planning_mode: planningContext.planning_mode } : {}),
            ...(planningContext?.student_code ? { student_code: planningContext.student_code } : {}),
        },
    }
}

const normalizedTimetableAdoptionReturnStep = returnStep => (
    returnStep === MODULE_SELECTION_STEP ? MODULE_SELECTION_STEP : TIMETABLE_CREATION_STEP
)

const timetableV3AdoptionReturnPath = returnStep => (
    normalizedTimetableAdoptionReturnStep(returnStep) === MODULE_SELECTION_STEP
        ? TIMETABLE_V3_MODULE_SELECTION_PATH
        : TIMETABLE_V3_CREATION_PATH
)

const timetableV3AdoptionRouteLocation = (
    planningContext,
    workspaceId,
    fingerprint,
    timetableIndex,
    timetableKey,
) => {
    const routeLocation = timetableV3RouteLocation(
        TIMETABLE_V3_ADOPTION_PATH,
        planningContext,
        workspaceId,
    )

    routeLocation.query.fingerprint = fingerprint
    routeLocation.query.timetable_index = String(timetableIndex)
    routeLocation.query.timetable_key = timetableKey

    return routeLocation
}

function normalizedCourseKeyList(courseKeys) {
    return [...new Set((Array.isArray(courseKeys) ? courseKeys : [])
        .map(courseKey => String(courseKey || '').trim())
        .filter(Boolean))]
}

function manualTimetableDraftState({
    planningMode,
    studentCode,
    planningValues,
    returnStep,
    routeSelection,
    calculationResult,
    selectedTimetable,
    selectedTimetableIndex,
    selectedCourseKeys,
    removedCourseKeys,
}) {
    const source = normalizedTimetableAdoptionReturnStep(returnStep) === MODULE_SELECTION_STEP
        ? BLANK_MANUAL_TIMETABLE_DRAFT
        : AUTOMATIC_MANUAL_TIMETABLE_DRAFT
    const fingerprint = source === AUTOMATIC_MANUAL_TIMETABLE_DRAFT
        ? String(routeSelection?.fingerprint || calculationResult?.fingerprint || '').trim()
        : ''
    const timetableKey = source === AUTOMATIC_MANUAL_TIMETABLE_DRAFT
        ? String(routeSelection?.key || selectedTimetable?.key || '').trim()
        : ''
    const timetableIndex = source === AUTOMATIC_MANUAL_TIMETABLE_DRAFT
        ? Number(routeSelection?.index ?? selectedTimetableIndex)
        : null

    return {
        mode: planningMode,
        studentCode: planningMode === WITH_STUDENT ? String(studentCode || '').trim() : null,
        planningValues: planningValues || {},
        source,
        fingerprint: fingerprint || null,
        timetableKey: timetableKey || null,
        timetableIndex: Number.isInteger(timetableIndex) && timetableIndex >= 0 ? timetableIndex : null,
        selectedCourseKeys: normalizedCourseKeyList(selectedCourseKeys),
        removedCourseKeys: normalizedCourseKeyList(removedCourseKeys),
    }
}

function normalizedCourseSelectionKeys(course) {
    const courseKeys = Array.isArray(course?.keys) ? course.keys : [course?.key]

    return [...new Set(courseKeys
        .map(courseKey => String(courseKey || '').trim())
        .filter(Boolean))]
}

function courseUsesSelectedKey(course, selectedCourseKeys) {
    const selectedKeys = new Set(Array.isArray(selectedCourseKeys) ? selectedCourseKeys : [])

    return normalizedCourseSelectionKeys(course).some(courseKey => selectedKeys.has(courseKey))
}

function normalizedCourseScheduleLabels(course) {
    const displayScheduleLabels = Array.isArray(course?.display_schedule_labels)
        ? course.display_schedule_labels
        : []
    const scheduleLabels = displayScheduleLabels.length
        ? displayScheduleLabels
        : Array.isArray(course?.schedule_labels)
            ? course.schedule_labels
            : [course?.schedule_label]

    return [...new Set(scheduleLabels
        .map(scheduleLabel => String(scheduleLabel || '').trim())
        .filter(Boolean))]
}

function normalizedCourseScheduleRows(course) {
    const displayScheduleRows = Array.isArray(course?.display_schedule_rows)
        ? course.display_schedule_rows
        : []
    const normalizedRows = displayScheduleRows
        .map((scheduleRow, index) => {
            const label = String(scheduleRow?.label || '').trim()
            const entryKeys = [...new Set((Array.isArray(scheduleRow?.entry_keys) ? scheduleRow.entry_keys : [])
                .map(entryKey => String(entryKey || '').trim())
                .filter(Boolean))]

            if (!label) return null

            return {
                entryKeys,
                key: `${label}:${entryKeys.join('|') || index}`,
                label,
            }
        })
        .filter(Boolean)

    if (normalizedRows.length) return normalizedRows

    const fallbackEntryKeys = normalizedCourseTimetableEntries(course)
        .map(entry => String(entry.key || '').trim())
        .filter(Boolean)

    return normalizedCourseScheduleLabels(course).map((label, index) => ({
        entryKeys: fallbackEntryKeys,
        key: `${label}:${index}`,
        label,
    }))
}

function timetableEntryTimeInMinutes(value) {
    const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})/u)
    if (!match) return null

    const hours = Number(match[1])
    const minutes = Number(match[2])

    if (!Number.isInteger(hours) || !Number.isInteger(minutes) || hours > 23 || minutes > 59) return null

    return (hours * 60) + minutes
}

function timetableEntryDates(entry) {
    return [...new Set((Array.isArray(entry?.dates) ? entry.dates : [])
        .map(date => String(date || '').trim())
        .filter(Boolean))]
}

function timetableEntriesOverlap(firstEntry, secondEntry) {
    const firstEntryKey = String(firstEntry?.key || '').trim()
    const secondEntryKey = String(secondEntry?.key || '').trim()
    if (firstEntryKey && firstEntryKey === secondEntryKey) return false

    const firstDates = timetableEntryDates(firstEntry)
    const secondDates = new Set(timetableEntryDates(secondEntry))
    const datesOverlap = firstDates.length && secondDates.size
        ? firstDates.some(date => secondDates.has(date))
        : Number(firstEntry?.weekday) === Number(secondEntry?.weekday)
    if (!datesOverlap) return false

    const firstStartsAt = timetableEntryTimeInMinutes(firstEntry?.starts_at || firstEntry?.time_from)
    const firstEndsAt = timetableEntryTimeInMinutes(firstEntry?.ends_at || firstEntry?.time_until)
    const secondStartsAt = timetableEntryTimeInMinutes(secondEntry?.starts_at || secondEntry?.time_from)
    const secondEndsAt = timetableEntryTimeInMinutes(secondEntry?.ends_at || secondEntry?.time_until)

    if (
        firstStartsAt !== null
        && firstEndsAt !== null
        && secondStartsAt !== null
        && secondEndsAt !== null
        && firstEndsAt > firstStartsAt
        && secondEndsAt > secondStartsAt
    ) {
        return firstStartsAt < secondEndsAt && secondStartsAt < firstEndsAt
    }

    return Number(firstEntry?.hour) === Number(secondEntry?.hour)
}

function timetableCourseOverlapLabel(course, entry) {
    return canonicalTimetableCourseLabel(
        entry?.display_label || course?.title || course?.course_title || '',
        entry?.module_code || entry?.subject || '',
    )
}

function normalizedCourseTimetableEntries(course) {
    const entries = Array.isArray(course?.timetable_entries) ? course.timetable_entries : []
    const entryKeys = new Set()

    return entries
        .filter(entry => entry && typeof entry === 'object' && !Array.isArray(entry))
        .filter((entry) => {
            const entryKey = String(entry.key || '').trim()
            const weekday = Number(entry.weekday)
            const hour = Number(entry.hour)

            if (!entryKey || entryKeys.has(entryKey)) return false
            if (!Number.isInteger(weekday) || weekday < 1 || weekday > 6) return false
            if (!Number.isInteger(hour) || hour < 1) return false

            entryKeys.add(entryKey)

            return true
        })
}

function uniqueCoursesForModuleGroups(moduleGroups) {
    const courseIdentities = new Set()

    return (Array.isArray(moduleGroups) ? moduleGroups : [])
        .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
        .flatMap(module => Array.isArray(module?.courses) ? module.courses : [])
        .filter((course) => {
            const courseIdentity = normalizedCourseSelectionKeys(course).sort().join('|')

            if (!courseIdentity || courseIdentities.has(courseIdentity)) return false

            courseIdentities.add(courseIdentity)

            return true
        })
}

function manualSelectedModulesForKeys(moduleGroups, selectedCourseKeys) {
    const selectedKeys = new Set(Array.isArray(selectedCourseKeys) ? selectedCourseKeys : [])
    const moduleIdentities = new Set()
    const moduleCodeCollator = new Intl.Collator('de-AT', {
        numeric: true,
        sensitivity: 'base',
    })

    return (Array.isArray(moduleGroups) ? moduleGroups : [])
        .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
        .filter(module => (Array.isArray(module?.courses) ? module.courses : []).some((course) => {
            const courseKeys = normalizedCourseSelectionKeys(course)

            return courseKeys.length > 0 && courseKeys.every(courseKey => selectedKeys.has(courseKey))
        }))
        .filter((module) => {
            const moduleIdentity = String(module?.code || module?.selection_key || '').trim().toLocaleLowerCase('de-AT')

            if (!moduleIdentity || moduleIdentities.has(moduleIdentity)) return false

            moduleIdentities.add(moduleIdentity)

            return true
        })
        .sort((firstModule, secondModule) => moduleCodeCollator.compare(
            String(firstModule?.code || firstModule?.selection_key || ''),
            String(secondModule?.code || secondModule?.selection_key || ''),
        ))
}

function manualTimetableForCourses(courses) {
    const slots = {}
    const timetableItems = (Array.isArray(courses) ? courses : [])
        .flatMap(course => normalizedCourseTimetableEntries(course).map(entry => ({ course, entry })))

    timetableItems
        .sort((firstItem, secondItem) => {
            const firstSortKey = [
                Number(firstItem.entry.weekday),
                Number(firstItem.entry.hour),
                String(firstItem.entry.key || ''),
            ].join('|')
            const secondSortKey = [
                Number(secondItem.entry.weekday),
                Number(secondItem.entry.hour),
                String(secondItem.entry.key || ''),
            ].join('|')

            return firstSortKey.localeCompare(secondSortKey, 'de-AT', { numeric: true })
        })
        .forEach(({ course, entry }) => {
            const slotKey = `${Number(entry.weekday)}-${Number(entry.hour)}`
            const timetableEntry = {
                key: String(entry.key || '').trim(),
                code: canonicalTimetableCourseLabel(entry.module_code || entry.subject || ''),
                name: canonicalTimetableCourseLabel(
                    course?.course_title || course?.title || '',
                    entry.module_code || entry.subject || '',
                ),
                sourceLabel: canonicalTimetableCourseLabel(
                    entry.display_label || course?.title || '',
                    entry.module_code || entry.subject || '',
                ),
                courseGroup: entry,
                dateRangeLabel: '',
                conflicts: [],
                sameSlotEntries: [],
                isDistanceLearningCourse: course?.is_distance_learning === true,
            }

            if (slots[slotKey]) {
                slots[slotKey].sameSlotEntries.push(timetableEntry)

                return
            }

            slots[slotKey] = timetableEntry
        })

    return {
        key: 'manual-timetable',
        slots,
    }
}

function timetableEntryCourseKey(entry) {
    return String(entry?.courseGroup?.key || entry?.key || '').trim()
}

function timetableCourseKeys(timetable) {
    return [...new Set(Object.values(timetable?.slots || {})
        .flatMap((slot) => [
            slot,
            ...(Array.isArray(slot?.sameSlotEntries) ? slot.sameSlotEntries : []),
            ...(Array.isArray(slot?.conflicts) ? slot.conflicts : []),
        ])
        .map(entry => timetableEntryCourseKey(entry))
        .filter(Boolean))]
}

function timetableWithoutCourseKeys(timetable, removedCourseKeys) {
    const removedKeys = new Set(Array.isArray(removedCourseKeys) ? removedCourseKeys : [])
    if (!removedKeys.size || !timetable?.slots || typeof timetable.slots !== 'object') return timetable

    const slots = {}

    Object.entries(timetable.slots).forEach(([slotKey, slot]) => {
        const retainedEntries = [
            { entry: slot, relationship: 'primary' },
            ...(Array.isArray(slot?.sameSlotEntries)
                ? slot.sameSlotEntries.map(entry => ({ entry, relationship: 'same-slot' }))
                : []),
            ...(Array.isArray(slot?.conflicts)
                ? slot.conflicts.map(entry => ({ entry, relationship: 'overlap' }))
                : []),
        ].filter(({ entry }) => {
            const entryKey = timetableEntryCourseKey(entry)

            return !entryKey || !removedKeys.has(entryKey)
        })
        if (!retainedEntries.length) return

        const [primaryEntry, ...relatedEntries] = retainedEntries

        slots[slotKey] = {
            ...primaryEntry.entry,
            conflicts: relatedEntries
                .filter(({ relationship }) => relationship === 'overlap')
                .map(({ entry }) => entry),
            sameSlotEntries: relatedEntries
                .filter(({ relationship }) => relationship !== 'overlap')
                .map(({ entry }) => entry),
        }
    })

    return {
        ...timetable,
        slots,
    }
}

function timetableWithManualCourses(timetable, manualTimetable) {
    const timetableSlots = timetable?.slots && typeof timetable.slots === 'object' ? timetable.slots : {}
    const manualSlots = manualTimetable?.slots && typeof manualTimetable.slots === 'object' ? manualTimetable.slots : {}
    const slots = Object.fromEntries(Object.entries(timetableSlots).map(([slotKey, slot]) => [slotKey, {
        ...slot,
        conflicts: Array.isArray(slot?.conflicts) ? [...slot.conflicts] : [],
        sameSlotEntries: Array.isArray(slot?.sameSlotEntries) ? [...slot.sameSlotEntries] : [],
    }]))

    Object.entries(manualSlots).forEach(([slotKey, manualSlot]) => {
        if (!slots[slotKey]) {
            slots[slotKey] = {
                ...manualSlot,
                conflicts: Array.isArray(manualSlot?.conflicts) ? [...manualSlot.conflicts] : [],
                sameSlotEntries: Array.isArray(manualSlot?.sameSlotEntries) ? [...manualSlot.sameSlotEntries] : [],
            }

            return
        }

        const existingKeys = new Set([
            slots[slotKey],
            ...slots[slotKey].sameSlotEntries,
            ...slots[slotKey].conflicts,
        ].map(entry => timetableEntryCourseKey(entry)).filter(Boolean))
        const manualEntries = [
            manualSlot,
            ...(Array.isArray(manualSlot?.sameSlotEntries) ? manualSlot.sameSlotEntries : []),
        ]

        manualEntries.forEach((manualEntry) => {
            const manualEntryKey = timetableEntryCourseKey(manualEntry)
            if (!manualEntryKey || existingKeys.has(manualEntryKey)) return

            slots[slotKey].sameSlotEntries.push(manualEntry)
            existingKeys.add(manualEntryKey)
        })
    })

    return {
        ...timetable,
        slots,
    }
}

function uniqueModules(moduleGroups) {
    const moduleKeys = new Set()

    return (Array.isArray(moduleGroups) ? moduleGroups : [])
        .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
        .filter((module) => {
            const moduleKey = String(module?.selection_key || '').trim()

            if (!moduleKey || moduleKeys.has(moduleKey)) return false

            moduleKeys.add(moduleKey)

            return true
        })
}

function selectedModulesForKeys(moduleGroups, selectedModuleKeys) {
    const selectedKeys = new Set(Array.isArray(selectedModuleKeys) ? selectedModuleKeys : [])

    return uniqueModules(moduleGroups)
        .filter(module => selectedKeys.has(module.selection_key))
}

function moduleHours(module) {
    const hours = Number(module?.hours ?? 0)

    return Number.isFinite(hours) ? hours : 0
}

function normalizedTimetableCalculationCount(value) {
    const count = Number(value)

    return Number.isFinite(count) && count > 0 ? Math.trunc(count) : 0
}

function normalizedTimetableCalculationProgressPhase(value) {
    const phase = String(value || '').trim()

    return TIMETABLE_CALCULATION_PROGRESS_PHASES.includes(phase) ? phase : 'checking'
}

function planningSelectionValuesMatch(storedValues, currentValues) {
    const normalizedStoredValues = storedValues && typeof storedValues === 'object' && !Array.isArray(storedValues)
        ? storedValues
        : {}
    const normalizedCurrentValues = currentValues && typeof currentValues === 'object' && !Array.isArray(currentValues)
        ? currentValues
        : {}
    const storedKeys = Object.keys(normalizedStoredValues).sort()
    const currentKeys = Object.keys(normalizedCurrentValues).sort()

    return storedKeys.length === currentKeys.length
        && storedKeys.every((key, index) => (
            key === currentKeys[index]
            && normalizedStoredValues[key] === normalizedCurrentValues[key]
        ))
}

function normalizedTimetableFilters(filters) {
    const normalizedFilters = filters && typeof filters === 'object' && !Array.isArray(filters)
        ? filters
        : {}
    const freeDays = normalizedFilters.free_days

    return {
        include_saturday: typeof normalizedFilters.include_saturday === 'boolean'
            ? normalizedFilters.include_saturday
            : DEFAULT_TIMETABLE_FILTERS.include_saturday,
        free_days: Number.isInteger(freeDays) && freeDays >= 1 && freeDays <= MAX_FREE_DAYS
            ? freeDays
            : DEFAULT_TIMETABLE_FILTERS.free_days,
    }
}

function timetableVariantCountLabel(value) {
    const count = Number(value)
    const normalizedCount = Number.isInteger(count) && count >= 0 ? count : 0

    return `${normalizedCount.toLocaleString('de-AT')} ${normalizedCount === 1 ? 'Variante' : 'Varianten'}`
}

function timetableFiltersMatch(firstFilters, secondFilters) {
    const normalizedFirstFilters = normalizedTimetableFilters(firstFilters)
    const normalizedSecondFilters = normalizedTimetableFilters(secondFilters)

    return Object.keys(DEFAULT_TIMETABLE_FILTERS).every(
        filterKey => normalizedFirstFilters[filterKey] === normalizedSecondFilters[filterKey],
    )
}

function isTimetableCalculationResult(calculationResult) {
    const calculationSummary = calculationResult?.summary

    return calculationResult
        && typeof calculationResult === 'object'
        && !Array.isArray(calculationResult)
        && calculationSummary
        && typeof calculationSummary === 'object'
        && !Array.isArray(calculationSummary)
}

function normalizedTimetablePageMeta(calculationResult) {
    const meta = calculationResult?.timetables_meta
    const timetables = calculationResult?.timetables

    if (
        !meta
        || typeof meta !== 'object'
        || Array.isArray(meta)
        || !Array.isArray(timetables)
    ) return null

    const currentPage = Number(meta.current_page)
    const perPage = Number(meta.per_page)
    const lastPage = Number(meta.last_page)
    const total = Number(meta.total)
    const unfilteredTotal = Number(meta.unfiltered_total)
    const offset = Number(meta.offset)
    const optionCounts = meta.option_counts
    const includeSaturdayOptionCount = Number(optionCounts?.include_saturday)
    const excludeSaturdayOptionCount = Number(optionCounts?.exclude_saturday)
    const freeDayOptionCounts = optionCounts?.free_days
    const anyFreeDayOptionCount = Number(freeDayOptionCounts?.any)
    const maximumFreeDays = Number(freeDayOptionCounts?.maximum)
    const freeDayValues = Array.isArray(freeDayOptionCounts?.values)
        ? freeDayOptionCounts.values.map(option => ({
            value: Number(option?.value),
            count: Number(option?.count),
        }))
        : []
    const filters = meta.filters
    const freeDaysFilterIsValid = filters?.free_days === null
        || (Number.isInteger(filters?.free_days)
            && filters.free_days >= 1
            && filters.free_days <= MAX_FREE_DAYS)
    const expectedFreeDayValues = Number.isInteger(maximumFreeDays) && maximumFreeDays > 0
        ? Array.from({ length: maximumFreeDays }, (_, index) => maximumFreeDays - index)
        : []
    const freeDayValuesAreValid = freeDayValues.length === expectedFreeDayValues.length
        && freeDayValues.every((option, index) => (
            Number.isInteger(option.value)
            && option.value === expectedFreeDayValues[index]
            && Number.isInteger(option.count)
            && option.count >= 0
            && option.count <= anyFreeDayOptionCount
        ))
    const selectedFreeDayOptionCount = filters?.free_days === null
        ? anyFreeDayOptionCount
        : freeDayValues.find(option => option.value === filters?.free_days)?.count
    const expectedLastPage = Math.max(1, Math.ceil(total / TIMETABLES_PER_PAGE))
    const expectedItemCount = Math.min(TIMETABLES_PER_PAGE, Math.max(0, total - offset))
    const expectedFrom = expectedItemCount > 0 ? offset + 1 : null
    const expectedTo = expectedItemCount > 0 ? offset + expectedItemCount : null

    if (
        !Number.isInteger(currentPage)
        || !Number.isInteger(perPage)
        || !Number.isInteger(lastPage)
        || !Number.isInteger(total)
        || !Number.isInteger(unfilteredTotal)
        || !Number.isInteger(offset)
        || currentPage < 1
        || perPage !== TIMETABLES_PER_PAGE
        || lastPage !== expectedLastPage
        || currentPage > lastPage
        || total < 0
        || total > MAX_MATERIALIZED_TIMETABLES
        || unfilteredTotal < total
        || unfilteredTotal > MAX_MATERIALIZED_TIMETABLES
        || !optionCounts
        || typeof optionCounts !== 'object'
        || Array.isArray(optionCounts)
        || !Number.isInteger(includeSaturdayOptionCount)
        || !Number.isInteger(excludeSaturdayOptionCount)
        || includeSaturdayOptionCount < 0
        || includeSaturdayOptionCount > unfilteredTotal
        || excludeSaturdayOptionCount < 0
        || excludeSaturdayOptionCount > includeSaturdayOptionCount
        || !freeDayOptionCounts
        || typeof freeDayOptionCounts !== 'object'
        || Array.isArray(freeDayOptionCounts)
        || !Number.isInteger(anyFreeDayOptionCount)
        || anyFreeDayOptionCount < 0
        || anyFreeDayOptionCount > unfilteredTotal
        || !Number.isInteger(maximumFreeDays)
        || maximumFreeDays < 0
        || maximumFreeDays > MAX_FREE_DAYS
        || !freeDayValuesAreValid
        || !filters
        || typeof filters !== 'object'
        || Array.isArray(filters)
        || typeof filters.include_saturday !== 'boolean'
        || !freeDaysFilterIsValid
        || (filters.include_saturday
            ? total !== includeSaturdayOptionCount
            : total !== excludeSaturdayOptionCount)
        || total !== selectedFreeDayOptionCount
        || offset !== (currentPage - 1) * perPage
        || timetables.length !== expectedItemCount
        || (meta.from ?? null) !== expectedFrom
        || (meta.to ?? null) !== expectedTo
    ) return null

    return {
        currentPage,
        lastPage,
        offset,
        perPage,
        total,
        unfilteredTotal,
        optionCounts: {
            includeSaturday: includeSaturdayOptionCount,
            excludeSaturday: excludeSaturdayOptionCount,
            freeDays: {
                any: anyFreeDayOptionCount,
                maximum: maximumFreeDays,
                values: freeDayValues,
            },
        },
        filters: normalizedTimetableFilters(filters),
    }
}

function xsrfTokenFromCookie() {
    if (typeof document === 'undefined') return ''

    const cookiePrefix = 'XSRF-TOKEN='
    const encodedToken = document.cookie
        .split(';')
        .map(cookie => cookie.trim())
        .find(cookie => cookie.startsWith(cookiePrefix))
        ?.slice(cookiePrefix.length)

    if (!encodedToken) return ''

    try {
        return decodeURIComponent(encodedToken)
    } catch {
        return ''
    }
}

async function fetchTimetableCalculation(payload) {
    if (typeof window.ensureCsrfCookie === 'function') {
        await window.ensureCsrfCookie()
    }

    const request = () => {
        const xsrfToken = xsrfTokenFromCookie()

        return fetch(updateTimetableV3Timetable.url(), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json, application/x-ndjson',
                'Content-Type': 'application/json',
                'X-Timetable-Progress': 'stream',
                ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            },
            body: JSON.stringify(payload),
        })
    }

    let response = await request()

    if (response.status !== 419 || typeof window.axios?.get !== 'function') {
        return response
    }

    await window.axios.get('/sanctum/csrf-cookie', { __skipCsrfRetry: true })
    response = await request()

    return response
}

function timetableCalculationStreamError(data = {}, status = 0) {
    const error = new Error(String(data?.message || '').trim() || 'Bitte versuchen Sie die Berechnung erneut.')
    error.response = {
        data,
        status,
    }

    return error
}

async function consumeTimetableCalculationStream(response, onProgress) {
    if (!response?.ok) {
        const responseText = await response?.text?.() || ''
        let responseData = {}

        try {
            responseData = JSON.parse(responseText)
        } catch {
            responseData = { message: responseText }
        }

        throw timetableCalculationStreamError(responseData, Number(response?.status || 0))
    }

    let calculationResult = null
    const processLine = (line) => {
        const normalizedLine = String(line || '').trim()

        if (!normalizedLine) return

        const event = JSON.parse(normalizedLine)

        if (event?.type === 'error') {
            throw timetableCalculationStreamError({
                message: event.message,
                errors: event.errors,
            })
        }

        if (event?.type === 'complete') {
            calculationResult = event.data

            return
        }

        if (event?.type === 'progress') {
            onProgress(event)
        }
    }

    if (!response.body?.getReader) {
        const responseText = await response.text()
        responseText.split(/\r?\n/).forEach(processLine)
    } else {
        const reader = response.body.getReader()
        const decoder = new TextDecoder()
        let bufferedText = ''

        while (true) {
            const { done, value } = await reader.read()
            bufferedText += decoder.decode(value || new Uint8Array(), { stream: !done })
            const lines = bufferedText.split(/\r?\n/)
            bufferedText = lines.pop() || ''
            lines.forEach(processLine)

            if (done) break
        }

        processLine(bufferedText)
    }

    if (!calculationResult) {
        throw new TypeError('The timetable calculation stream did not contain a result.')
    }

    return calculationResult
}

function moduleSelectionLimitViolation(moduleGroups, selectedModuleKeys) {
    const modules = selectedModulesForKeys(moduleGroups, selectedModuleKeys)

    if (modules.length > MAX_SELECTED_MODULES) {
        return `Es können höchstens ${MAX_SELECTED_MODULES} Module gleichzeitig ausgewählt werden.`
    }

    const totalHours = modules.reduce((hours, module) => hours + moduleHours(module), 0)

    if (totalHours > MAX_SELECTED_MODULE_HOURS) {
        return `Es können höchstens ${MAX_SELECTED_MODULE_HOURS} Stunden gleichzeitig ausgewählt werden.`
    }

    return ''
}

function constrainedModuleSelection(moduleGroups, selectedModuleKeys, selectedCourseKeys) {
    const requestedModuleKeys = new Set(Array.isArray(selectedModuleKeys) ? selectedModuleKeys : [])
    const requestedCourseKeys = new Set(Array.isArray(selectedCourseKeys) ? selectedCourseKeys : [])
    const selectedModules = []
    let selectedHours = 0

    uniqueModules(moduleGroups)
        .filter(module => requestedModuleKeys.has(module.selection_key))
        .forEach((module) => {
            const nextHours = selectedHours + moduleHours(module)

            if (selectedModules.length >= MAX_SELECTED_MODULES || nextHours > MAX_SELECTED_MODULE_HOURS) return

            selectedModules.push(module)
            selectedHours = nextHours
        })

    const allowedCourseKeys = new Set(selectedModules
        .flatMap(module => Array.isArray(module?.courses) ? module.courses : [])
        .flatMap(course => normalizedCourseSelectionKeys(course)))
    const constrainedCourseKeys = [...requestedCourseKeys]
        .filter(courseKey => allowedCourseKeys.has(courseKey))

    return {
        selectedModuleKeys: selectedModules.map(module => module.selection_key),
        selectedCourseKeys: constrainedCourseKeys,
        wasConstrained: selectedModules.length < requestedModuleKeys.size,
    }
}

export default {
    name: 'TimetableV3',

    components: {
        TimetableV3PossibleTimetables,
    },

    data() {
        return {
            isLoadingState: true,
            isSavingState: false,
            pendingStateSaveCount: 0,
            stateSaveQueue: null,
            stateLoadFailed: false,
            stateSaveFailed: false,
            storedState: null,
            workspaceId: '',
            planningMode: null,
            selectedStudent: null,
            studentDialogOpen: false,
            studentInfoDialogOpen: false,
            studyInfoDialogOpen: false,
            schoolLevelDialogOpen: false,
            schoolLevelDialogSelection: '',
            schoolLevelDialogOriginalValue: '',
            schoolLevelDialogSaving: false,
            schoolLevelDialogError: '',
            studentSearch: '',
            students: [],
            studentOptionsLoading: false,
            studentOptionsError: false,
            schoolHours: [],
            schoolHoursLoaded: false,
            schoolHoursLoading: false,
            studentSelectionDetailsLoading: false,
            studentSelectionDetailsError: false,
            studentSelectionDetailsCode: '',
            studentSelectionDetailsRequestCode: '',
            studentSelectionItems: [],
            studentStudyModuleGroups: [],
            moduleSelectionGroups: [],
            mainModuleSelectionGroups: [],
            manualModuleCatalogView: MANUAL_STUDENT_MODULE_CATALOG,
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            manualSelectedCourseKeys: [],
            manualPendingCourseKeys: [],
            adoptionRemovedCourseKeys: [],
            maximumSelectedModules: MAX_SELECTED_MODULES,
            maximumSelectedModuleHours: MAX_SELECTED_MODULE_HOURS,
            moduleSelectionLimitMessage: '',
            scheduleCreationMode: null,
            timetableAdoptionReturnStep: TIMETABLE_CREATION_STEP,
            timetableFilters: { ...DEFAULT_TIMETABLE_FILTERS },
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            timetableCalculationRequestId: 0,
            timetableCalculationCombinationCount: 0,
            timetableCalculationCheckedCombinationCount: 0,
            timetableCalculationProgressPercent: 0,
            timetableCalculationProgressPhase: 'preparing',
            timetableSelectedIndex: 0,
            timetablePageRequestId: 0,
            timetablePageLoading: false,
            timetablePageLoadingDirection: '',
            timetablePageError: '',
            activeModuleGroupKey: '',
            activeManualModuleGroupKey: '',
            moduleCoursesDialogOpen: false,
            moduleCourseDialogModule: null,
            moduleCoursesDialogReadOnly: false,
            moduleSelectionResetPending: false,
            planningSelectionFields: [],
            planningSelectionValues: {},
            emailCopyStatus: 'idle',
            pdfExporting: false,
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
        currentStep() {
            const subsection = this.$route?.params?.subsection

            if (subsection === MODULE_SELECTION_STEP) return MODULE_SELECTION_STEP
            if (subsection === TIMETABLE_CREATION_STEP) return TIMETABLE_CREATION_STEP
            if (subsection === TIMETABLE_ADOPTION_STEP) return TIMETABLE_ADOPTION_STEP

            return SELECTION_STEP
        },
        currentPageLabel() {
            if (this.currentStep === MODULE_SELECTION_STEP) {
                return this.scheduleCreationMode === AUTOMATIC_TIMETABLE ? '2A' : '2'
            }

            if (this.currentStep === TIMETABLE_ADOPTION_STEP) {
                return this.isManualTimetableAdoption ? '3B' : '3A'
            }

            return TIMETABLE_PAGE_LABELS[this.currentStep] || ''
        },
        timetableAdoptionRouteSelection() {
            const fingerprint = String(this.$route?.query?.fingerprint || '').trim()
            const index = Number(this.$route?.query?.timetable_index)
            const key = String(this.$route?.query?.timetable_key || '').trim()

            if (
                !/^[a-f0-9]{64}$/.test(fingerprint)
                || !Number.isInteger(index)
                || index < 0
                || index >= MAX_MATERIALIZED_TIMETABLES
                || !key
            ) return null

            return {
                fingerprint,
                index,
                key,
                page: Math.floor(index / TIMETABLES_PER_PAGE) + 1,
            }
        },
        isManualTimetableAdoption() {
            return this.currentStep === TIMETABLE_ADOPTION_STEP
                && this.timetableAdoptionReturnStep === MODULE_SELECTION_STEP
        },
        normalizedStudentSearch() {
            return String(this.studentSearch || '').trim().toLocaleLowerCase('de-AT')
        },
        studentSearchReady() {
            return this.normalizedStudentSearch.length >= 2
        },
        filteredStudents() {
            if (!this.studentSearchReady) return []

            return this.students
                .filter(student => this.studentSearchText(student).includes(this.normalizedStudentSearch))
                .sort((studentA, studentB) => this.studentDisplayLabel(studentA).localeCompare(
                    this.studentDisplayLabel(studentB),
                    'de-AT',
                    { numeric: true },
                ))
                .slice(0, 80)
        },
        studentTotalCountLabel() {
            if (this.studentOptionsLoading) return 'Studierende werden geladen …'

            return `${this.students.length.toLocaleString('de-AT')} Studierende gesamt`
        },
        selectedStudentLabel() {
            return this.studentDisplayLabel(this.selectedStudent)
        },
        selectedStudentCode() {
            return String(
                this.selectedStudent?.studentCode || this.selectedStudent?.student_code || '',
            ).trim()
        },
        hasPlanningSelectionContext() {
            return this.planningMode === WITHOUT_STUDENT
                || (this.planningMode === WITH_STUDENT && Boolean(this.selectedStudentCode))
        },
        usesMainModuleGroups() {
            return this.planningMode === WITHOUT_STUDENT
        },
        manualModuleCatalogGroups() {
            if (
                this.planningMode === WITH_STUDENT
                && this.manualModuleCatalogView === MANUAL_MAIN_MODULE_CATALOG
            ) {
                return this.mainModuleSelectionGroups
            }

            return this.moduleSelectionGroups
        },
        visibleManualModuleCatalogGroups() {
            const groups = this.manualModuleCatalogGroups

            if (!this.manualModuleCatalogUsesMainGroups || !this.activeManualModuleGroupKey) return groups

            const activeGroup = groups.find(group => group.key === this.activeManualModuleGroupKey)

            return activeGroup ? [activeGroup] : groups
        },
        manualModuleCatalogUsesMainGroups() {
            return this.planningMode === WITHOUT_STUDENT
                || (
                    this.planningMode === WITH_STUDENT
                    && this.manualModuleCatalogView === MANUAL_MAIN_MODULE_CATALOG
                )
        },
        manualCatalogCourses() {
            return uniqueCoursesForModuleGroups([
                ...this.moduleSelectionGroups,
                ...this.mainModuleSelectionGroups,
            ])
        },
        manualSelectedCourses() {
            const selectedCourseKeys = new Set(this.manualSelectedCourseKeys)

            return this.manualCatalogCourses.filter((course) => {
                const courseKeys = normalizedCourseSelectionKeys(course)

                return courseKeys.length > 0
                    && courseKeys.every(courseKey => selectedCourseKeys.has(courseKey))
            })
        },
        manualSelectedModules() {
            return manualSelectedModulesForKeys([
                ...this.moduleSelectionGroups,
                ...this.mainModuleSelectionGroups,
            ], this.manualSelectedCourseKeys)
        },
        adoptionSelectedModules() {
            const modules = this.isManualTimetableAdoption
                ? this.manualSelectedModules
                : [...this.selectedModules, ...this.manualSelectedModules]
            const placedCourseKeys = new Set(this.adoptionPlacedCourseKeys)
            const moduleIdentities = new Set()
            const moduleCodeCollator = new Intl.Collator('de-AT', {
                numeric: true,
                sensitivity: 'base',
            })

            return modules
                .filter((module) => {
                    const moduleCourseKeys = (Array.isArray(module?.courses) ? module.courses : [])
                        .flatMap(course => normalizedCourseSelectionKeys(course))

                    return !moduleCourseKeys.length || moduleCourseKeys.some(courseKey => placedCourseKeys.has(courseKey))
                })
                .filter((module) => {
                    const moduleIdentity = String(module?.code || module?.selection_key || '')
                        .trim()
                        .toLocaleLowerCase('de-AT')

                    if (!moduleIdentity || moduleIdentities.has(moduleIdentity)) return false

                    moduleIdentities.add(moduleIdentity)

                    return true
                })
                .sort((firstModule, secondModule) => moduleCodeCollator.compare(
                    String(firstModule?.code || firstModule?.selection_key || ''),
                    String(secondModule?.code || secondModule?.selection_key || ''),
                ))
        },
        adoptionSelectedModuleCount() {
            return this.adoptionSelectedModules.length
        },
        adoptionSelectedModuleHoursLabel() {
            const hours = this.adoptionSelectedModules.reduce(
                (totalHours, module) => totalHours + moduleHours(module),
                0,
            )

            return hours.toLocaleString('de-AT', {
                maximumFractionDigits: 2,
                minimumFractionDigits: 0,
            })
        },
        manualTimetable() {
            return manualTimetableForCourses(this.manualSelectedCourses)
        },
        adoptionBaseTimetable() {
            return timetableWithoutCourseKeys(this.selectedTimetableResult, this.adoptionRemovedCourseKeys)
        },
        adoptionDisplayedTimetable() {
            if (this.isManualTimetableAdoption) return this.manualTimetable

            return timetableWithManualCourses(this.adoptionBaseTimetable, this.manualTimetable)
        },
        adoptionPlacedCourseKeys() {
            const transferredCourseKeys = this.isManualTimetableAdoption
                ? []
                : timetableCourseKeys(this.adoptionBaseTimetable)

            return [...new Set([...transferredCourseKeys, ...this.manualSelectedCourseKeys])]
        },
        adoptionTimetables() {
            const timetables = Array.isArray(this.timetableCalculationResult?.timetables)
                ? this.timetableCalculationResult.timetables
                : []
            if (this.isManualTimetableAdoption || !this.selectedTimetableResult) return timetables
            if (!Object.keys(this.manualTimetable.slots || {}).length && !this.adoptionRemovedCourseKeys.length) {
                return timetables
            }

            return timetables.map(timetable => (
                timetable === this.selectedTimetableResult
                    ? timetableWithManualCourses(this.adoptionBaseTimetable, this.manualTimetable)
                    : timetable
            ))
        },
        selectedStudentClass() {
            return String(this.selectedStudent?.className || this.selectedStudent?.class || '').trim()
        },
        selectedStudentFullName() {
            return [
                this.selectedStudent?.lastName || this.selectedStudent?.last_name,
                this.selectedStudent?.firstName || this.selectedStudent?.first_name,
            ]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join(' ')
        },
        selectedStudentReligion() {
            return String(this.selectedStudent?.religion || '').trim()
        },
        selectedStudentSex() {
            return this.normalizedStudentSex(this.selectedStudent)
        },
        selectedStudentSexPresentation() {
            if (this.selectedStudentSex === 'm') {
                return { icon: 'mdi-gender-male', color: 'blue', label: 'männlich' }
            }

            if (this.selectedStudentSex === 'w') {
                return { icon: 'mdi-gender-female', color: 'pink', label: 'weiblich' }
            }

            return null
        },
        selectedStudentInstructionType() {
            return String(
                this.selectedStudent?.instructionType || this.selectedStudent?.instruction_type || '',
            ).trim() || '–'
        },
        selectedStudentSubjectPlan() {
            return String(
                this.selectedStudent?.subjectPlan || this.selectedStudent?.subject_plan || '',
            ).trim() || '–'
        },
        selectedStudentSubjectPlanMismatch() {
            return this.selectedStudent?.subjectPlanMismatch === true
                || this.selectedStudent?.subject_plan_mismatch === true
        },
        selectedStudentSemesterLabel() {
            const semester = this.normalizedStudentSemester(this.selectedStudent)

            return semester ? `${semester}. Semester` : '–'
        },
        selectedStudentImportedSchoolLevel() {
            return String(
                this.selectedStudent?.schoolLevel || this.selectedStudent?.school_level || '',
            ).trim() || '–'
        },
        selectedStudentSchoolLevelMismatch() {
            return this.selectedStudent?.schoolLevelMismatch === true
                || this.selectedStudent?.school_level_mismatch === true
        },
        selectedStudentOriginalSchoolLevel() {
            return String(
                this.selectedStudent?.originalSchoolLevel || this.selectedStudent?.original_school_level || '',
            ).trim()
        },
        selectedStudentSchoolLevelOptions() {
            const options = this.selectedStudent?.schoolLevelOptions
                || this.selectedStudent?.school_level_options
                || []

            return (Array.isArray(options) ? options : [])
                .map(option => ({
                    value: String(option?.value || '').trim(),
                    semester: Number(option?.semester),
                }))
                .filter(option => option.value && Number.isInteger(option.semester) && option.semester > 0)
        },
        canSaveSchoolLevelDialog() {
            const selectedValue = String(this.schoolLevelDialogSelection || '').trim()
            const selectableValues = [
                this.schoolLevelDialogOriginalValue,
                ...this.selectedStudentSchoolLevelOptions.map(option => option.value),
            ]

            return !this.schoolLevelDialogSaving
                && selectedValue !== ''
                && selectedValue !== this.selectedStudentImportedSchoolLevel
                && selectableValues.includes(selectedValue)
        },
        selectedStudentCalculationItems() {
            const itemsByKey = new Map(
                this.studentSelectionItems.map(item => [String(item?.key || ''), item]),
            )

            return [
                { key: 'religion', label: 'Ethik / Religion' },
                { key: 'language', label: 'Sprache' },
                { key: 'branch', label: 'Zweig' },
                { key: 'arts_subject', label: 'ME / BE' },
            ].map(item => ({
                ...item,
                label: String(itemsByKey.get(item.key)?.label || item.label),
                value: String(itemsByKey.get(item.key)?.value || '').trim() || '–',
            }))
        },
        selectedStudentHoverInformationItems() {
            const items = [
                { key: 'class', label: 'Klasse', value: this.selectedStudentClass || '–' },
                { key: 'name', label: 'Name', value: this.selectedStudentFullName || '–' },
                { key: 'religion', label: 'Religion', value: this.selectedStudentReligion || '–' },
                { key: 'instruction_type', label: 'Unterrichtsart', value: this.selectedStudentInstructionType },
                { key: 'semester', label: 'Semester', value: this.selectedStudentSemesterLabel },
            ]

            if (this.studentSelectionDetailsLoading || this.studentSelectionDetailsError) return items

            return [...items, ...this.selectedStudentCalculationItems]
        },
        compactPlanningSelectionItems() {
            return this.planningSelectionFields.map((field) => {
                const selectedValue = this.planningSelectionValues[field.key]
                const selectedOption = (Array.isArray(field.options) ? field.options : [])
                    .find(option => option?.value === selectedValue)

                return {
                    key: field.key,
                    label: String(field.label || '').trim(),
                    value: String(selectedOption?.title || selectedValue || '').trim() || '–',
                }
            })
        },
        selectedModuleCount() {
            return this.selectedModules.length
        },
        selectedModules() {
            const moduleCodeCollator = new Intl.Collator('de-AT', {
                numeric: true,
                sensitivity: 'base',
            })

            return selectedModulesForKeys(this.moduleSelectionGroups, this.selectedModuleKeys)
                .sort((firstModule, secondModule) => {
                    const codeComparison = moduleCodeCollator.compare(
                        String(firstModule?.code || ''),
                        String(secondModule?.code || ''),
                    )

                    if (codeComparison !== 0) return codeComparison

                    return moduleCodeCollator.compare(
                        String(firstModule?.selection_key || ''),
                        String(secondModule?.selection_key || ''),
                    )
                })
        },
        selectedModuleHours() {
            return this.selectedModules.reduce((totalHours, module) => {
                const moduleHours = Number(module?.hours ?? 0)

                return Number.isFinite(moduleHours) ? totalHours + moduleHours : totalHours
            }, 0)
        },
        selectedModuleHoursLabel() {
            return this.selectedModuleHours.toLocaleString('de-AT', {
                maximumFractionDigits: 2,
                minimumFractionDigits: 0,
            })
        },
        timetableCalculationHeading() {
            return this.timetableCalculationStatus === 'success'
                ? 'Ergebnis der Stundenplanberechnung'
                : 'Berechnung der Stundenpläne'
        },
        timetableCalculationSummary() {
            const summary = this.timetableCalculationResult?.summary

            return summary && typeof summary === 'object' ? summary : {}
        },
        timetablePageMeta() {
            return normalizedTimetablePageMeta(this.timetableCalculationResult) || {
                currentPage: 1,
                lastPage: 1,
                offset: 0,
                perPage: TIMETABLES_PER_PAGE,
                total: Array.isArray(this.timetableCalculationResult?.timetables)
                    ? this.timetableCalculationResult.timetables.length
                    : 0,
                unfilteredTotal: Array.isArray(this.timetableCalculationResult?.timetables)
                    ? this.timetableCalculationResult.timetables.length
                    : 0,
                optionCounts: {
                    includeSaturday: 0,
                    excludeSaturday: 0,
                    freeDays: {
                        any: 0,
                        maximum: 0,
                        values: [],
                    },
                },
                filters: normalizedTimetableFilters(this.timetableFilters),
            }
        },
        selectedTimetableResult() {
            const timetables = Array.isArray(this.timetableCalculationResult?.timetables)
                ? this.timetableCalculationResult.timetables
                : []
            const pageIndex = Number(this.timetableSelectedIndex) - this.timetablePageMeta.offset
            const timetable = Number.isInteger(pageIndex) && pageIndex >= 0
                ? timetables[pageIndex]
                : null

            return timetable
                && typeof timetable === 'object'
                && !Array.isArray(timetable)
                && timetable.slots
                && typeof timetable.slots === 'object'
                && !Array.isArray(timetable.slots)
                ? timetable
                : null
        },
        timetableSolutionPlan() {
            const solutionPlan = this.timetableCalculationSummary.solution_plan

            return solutionPlan && typeof solutionPlan === 'object' && !Array.isArray(solutionPlan)
                ? solutionPlan
                : {}
        },
        timetableSolutionPlanModuleRemovalScenarios() {
            const scenarios = this.timetableSolutionPlan.module_removal_scenarios

            return Array.isArray(scenarios)
                ? scenarios.filter(scenario => scenario && typeof scenario === 'object' && !Array.isArray(scenario))
                : []
        },
        timetableSolutionPlanHasOnlyZeroCalculatedScenarios() {
            const scenarios = this.timetableSolutionPlanModuleRemovalScenarios

            return scenarios.length > 0 && scenarios.every(
                scenario => scenario.status === 'calculated'
                    && this.normalizedTimetableCalculationCount(scenario.possible_timetable_count) === 0,
            )
        },
        possibleTimetableCount() {
            return this.timetablePageMeta.total
        },
        possibleTimetableCountLabel() {
            return this.possibleTimetableCount.toLocaleString('de-AT')
        },
        timetableFilterOptionCountLabels() {
            const { includeSaturday, excludeSaturday } = this.timetablePageMeta.optionCounts

            return {
                includeSaturday: timetableVariantCountLabel(includeSaturday),
                excludeSaturday: timetableVariantCountLabel(excludeSaturday),
            }
        },
        timetableFreeDayOptionCounts() {
            return this.timetablePageMeta.optionCounts.freeDays
        },
        timetableVisibleFreeDayOptions() {
            return this.timetableFreeDayOptionCounts.values.filter(option => option.count > 0)
        },
        timetableHasVisibleFreeDayOptions() {
            return this.timetableFreeDayOptionCounts.any > 0
                || this.timetableVisibleFreeDayOptions.length > 0
        },
        filteredOutAllTimetables() {
            return this.timetablePageMeta.total === 0
                && this.timetablePageMeta.unfilteredTotal > 0
        },
        totalPossibleTimetableCount() {
            const explicitCount = this.normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.possible_timetable_count,
            )

            if (explicitCount > 0) return explicitCount

            return this.normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.full_green_timetable_count,
            ) + this.normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.green_timetable_count,
            ) || this.possibleTimetableCount
        },
        totalPossibleTimetableCountLabel() {
            return this.totalPossibleTimetableCount.toLocaleString('de-AT')
        },
        unfilteredMaterializedTimetableCount() {
            return this.normalizedTimetableCalculationCount(this.timetableCalculationSummary.timetable_count)
        },
        unfilteredMaterializedTimetableCountLabel() {
            return this.unfilteredMaterializedTimetableCount.toLocaleString('de-AT')
        },
        timetablesTruncated() {
            return this.timetableCalculationSummary.timetables_truncated === true
                || this.totalPossibleTimetableCount > this.unfilteredMaterializedTimetableCount
        },
        timetableCalculationCombinationCountLabel() {
            return this.normalizedTimetableCalculationCount(
                this.timetableCalculationCombinationCount,
            ).toLocaleString('de-AT')
        },
        timetableCalculationCheckedCombinationCountLabel() {
            return this.normalizedTimetableCalculationCount(
                this.timetableCalculationCheckedCombinationCount,
            ).toLocaleString('de-AT')
        },
        timetableCalculationProgressLabel() {
            if (this.timetableCalculationProgressPhase === 'checking') {
                if (this.timetableCalculationCombinationCount === 0) {
                    return 'Kombinationen werden vorbereitet …'
                }

                return `${this.timetableCalculationCheckedCombinationCountLabel} von ${this.timetableCalculationCombinationCountLabel} Kombinationen geprüft.`
            }

            return {
                preparing: 'Auswahl und Unterrichte werden vorbereitet …',
                materializing: 'Mögliche Stundenpläne werden aufbereitet …',
                analyzing_solutions: 'Lösungsvorschläge werden berechnet …',
                compacting: 'Stundenplandaten werden komprimiert …',
                persisting: 'Ergebnis wird gespeichert …',
                complete: 'Berechnung abgeschlossen.',
            }[this.timetableCalculationProgressPhase] || 'Berechnung wird vorbereitet …'
        },
        timetableCalculationProgressSegments() {
            const progressPercent = Math.min(
                100,
                Math.max(0, Number(this.timetableCalculationProgressPercent) || 0),
            )

            return Array.from({ length: 20 }, (_, index) => {
                const percent = (index + 1) * 5

                return {
                    percent,
                    active: progressPercent >= percent,
                }
            })
        },
        checkedTimetableVariationCountLabel() {
            return this.normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.timetable_variation_count,
            ).toLocaleString('de-AT')
        },
        conflictingTimetableVariationCountLabel() {
            return this.normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.conflict_timetable_count,
            ).toLocaleString('de-AT')
        },
        activeModuleSelectionGroup() {
            return this.moduleSelectionGroups
                .find(group => group.key === this.activeModuleGroupKey) || null
        },
        activeManualModuleSelectionGroup() {
            return this.manualModuleCatalogGroups
                .find(group => group.key === this.activeManualModuleGroupKey) || null
        },
        moduleCourseDialogCourses() {
            const courses = Array.isArray(this.moduleCourseDialogModule?.courses)
                ? this.moduleCourseDialogModule.courses
                : []

            if (!this.moduleCoursesDialogReadOnly) return courses

            return courses.filter(course => !courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys))
        },
        moduleCoursesDialogInteractive() {
            return !this.moduleCoursesDialogReadOnly || this.currentStep === TIMETABLE_ADOPTION_STEP
        },
        selectedModuleCourseCount() {
            return this.selectedCourseCountForModule(this.moduleCourseDialogModule)
        },
        allModuleCoursesSelected() {
            return this.moduleCourseDialogCourses.length > 0
                && this.selectedModuleCourseCount === this.moduleCourseDialogCourses.length
        },
        hasSelectedModuleCourses() {
            return this.selectedModuleCourseCount > 0
        },
        selectedStudentEmail() {
            if (this.planningMode !== WITH_STUDENT) return ''

            return String(this.selectedStudent?.email || '').trim()
        },
    },

    watch: {
        currentStep(currentStep, previousStep) {
            if (currentStep === MODULE_SELECTION_STEP && !TIMETABLE_RESULT_STEPS.includes(previousStep)) {
                this.scheduleCreationMode = null
            }

            if (
                TIMETABLE_RESULT_STEPS.includes(previousStep)
                && !TIMETABLE_RESULT_STEPS.includes(currentStep)
            ) {
                this.resetTimetableCalculation()
            }

            if (!this.isLoadingState) {
                void this.ensureValidCurrentStep()
            }

            if (currentStep === TIMETABLE_ADOPTION_STEP) {
                this.manualModuleCatalogView = MANUAL_STUDENT_MODULE_CATALOG
                void this.loadSchoolHours()
            }

            if (previousStep === TIMETABLE_ADOPTION_STEP && currentStep !== TIMETABLE_ADOPTION_STEP) {
                this.resetManualModuleCatalogDisclosure()
            }
        },
    },

    async created() {
        await this.initializeWorkspace()
        await this.loadState()

        if (this.currentStep === TIMETABLE_ADOPTION_STEP) {
            await this.loadSchoolHours()
        }
    },

    methods: {
        timetableVariantCountLabel,
        manualTimetablePdfHours(timetable) {
            const bookedHours = new Set()

            MANUAL_TIMETABLE_PDF_WEEKDAYS.forEach((weekday) => {
                for (let hour = 1; hour <= 20; hour += 1) {
                    if (this.manualTimetablePdfEntriesForCell(timetable, weekday.value, hour).length) {
                        bookedHours.add(hour)
                    }
                }
            })

            return [...bookedHours].sort((firstHour, secondHour) => firstHour - secondHour)
        },
        manualTimetablePdfEntriesForCell(timetable, weekday, hour) {
            const slot = timetable?.slots?.[`${weekday}-${hour}`]
            if (!slot || typeof slot !== 'object' || Array.isArray(slot)) return []

            const entries = [
                slot,
                ...(Array.isArray(slot.sameSlotEntries) ? slot.sameSlotEntries : []),
                ...(Array.isArray(slot.conflicts) ? slot.conflicts : []),
            ]
            const seenEntryKeys = new Set()

            return entries.filter((entry, index) => {
                if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return false

                const entryKey = String(entry.courseGroup?.key || entry.key || `entry-${index}`).trim()
                if (seenEntryKeys.has(entryKey)) return false

                seenEntryKeys.add(entryKey)

                return true
            })
        },
        manualTimetablePdfLessonDates(entry) {
            return [...new Set((Array.isArray(entry?.courseGroup?.dates) ? entry.courseGroup.dates : [])
                .map(date => String(date || '').trim())
                .filter(Boolean))]
                .sort((firstDate, secondDate) => firstDate.localeCompare(secondDate))
        },
        manualTimetablePdfTimeInMinutes(value) {
            const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})/u)
            if (!match) return null

            const hours = Number(match[1])
            const minutes = Number(match[2])
            if (!Number.isInteger(hours) || !Number.isInteger(minutes) || hours > 23 || minutes > 59) return null

            return (hours * 60) + minutes
        },
        manualTimetablePdfEntriesOverlapInTime(firstEntry, secondEntry) {
            const firstStartsAt = this.manualTimetablePdfTimeInMinutes(
                firstEntry?.courseGroup?.starts_at || firstEntry?.courseGroup?.time_from,
            )
            const firstEndsAt = this.manualTimetablePdfTimeInMinutes(
                firstEntry?.courseGroup?.ends_at || firstEntry?.courseGroup?.time_until,
            )
            const secondStartsAt = this.manualTimetablePdfTimeInMinutes(
                secondEntry?.courseGroup?.starts_at || secondEntry?.courseGroup?.time_from,
            )
            const secondEndsAt = this.manualTimetablePdfTimeInMinutes(
                secondEntry?.courseGroup?.ends_at || secondEntry?.courseGroup?.time_until,
            )

            if ([firstStartsAt, firstEndsAt, secondStartsAt, secondEndsAt].includes(null)) return true

            return firstStartsAt < secondEndsAt && secondStartsAt < firstEndsAt
        },
        manualTimetablePdfExactOverlapDates(entries) {
            const overlappingDates = new Set()

            entries.forEach((entry, entryIndex) => {
                const entryDates = this.manualTimetablePdfLessonDates(entry)

                entries.slice(entryIndex + 1).forEach((otherEntry) => {
                    if (!this.manualTimetablePdfEntriesOverlapInTime(entry, otherEntry)) return

                    const otherDates = new Set(this.manualTimetablePdfLessonDates(otherEntry))
                    entryDates.forEach((date) => {
                        if (otherDates.has(date)) overlappingDates.add(date)
                    })
                })
            })

            return [...overlappingDates].sort((firstDate, secondDate) => firstDate.localeCompare(secondDate))
        },
        manualTimetablePdfNumberedCells(timetable, hours) {
            const numberedCells = {}
            let cellNumber = 0

            hours.forEach((hour) => {
                MANUAL_TIMETABLE_PDF_WEEKDAYS.forEach((weekday) => {
                    const slotKey = `${weekday.value}-${hour}`
                    const entries = this.manualTimetablePdfEntriesForCell(timetable, weekday.value, hour)
                    if (entries.length <= 1) return

                    const exactOverlapDates = this.manualTimetablePdfExactOverlapDates(entries)
                    const hasExactOverlap = exactOverlapDates.length > 0
                    let label = 'Mehrfachbelegung'

                    if (hasExactOverlap) {
                        label = exactOverlapDates.length === 1
                            ? 'Einzeltermin-Überschneidung'
                            : 'Überschneidungen'
                    }

                    cellNumber += 1
                    numberedCells[slotKey] = {
                        hasExactOverlap,
                        label,
                        number: cellNumber,
                    }
                })
            })

            return numberedCells
        },
        manualTimetablePdfCourse(entry) {
            const courseGroup = entry?.courseGroup || {}
            const code = canonicalTimetableCourseLabel(
                entry?.code || courseGroup.module_code || courseGroup.subject || '',
            )
            const sourceLabel = canonicalTimetableCourseLabel(
                entry?.sourceLabel || courseGroup.display_label || courseGroup.class_name || '',
                code,
            )
            const compactSourceLabel = sourceLabel
                .replace(/\s*[-–—]\s*/gu, '-')
                .replace(/\s+/gu, '-')
                .replace(/^-+|-+$/gu, '')
            const sourceContext = code
                && compactSourceLabel.toLocaleLowerCase('de-AT').startsWith(code.toLocaleLowerCase('de-AT'))
                ? compactSourceLabel.slice(code.length).replace(/^-+/u, '')
                : compactSourceLabel
            const identifier = [code, sourceContext].filter(Boolean).join('-')
            const title = canonicalTimetableModuleName(
                entry?.name || courseGroup.course_title || courseGroup.title || code || 'Unterricht',
                code,
            ).toLocaleUpperCase('de-AT')
            const recurrenceLabel = String(courseGroup.recurrence_label || '').trim().slice(0, 80)
            const details = recurrenceLabel.toLocaleLowerCase('de-AT') === '1-wöchig'
                ? ''
                : recurrenceLabel

            return {
                label: title || identifier || code || 'UNTERRICHT',
                identifier: identifier || code,
                details,
                dates: this.manualTimetablePdfLessonDates(entry).slice(0, 120),
                is_fu: entry?.isDistanceLearningCourse === true,
                recurrence_label: recurrenceLabel,
                recurrence_interval: Number(courseGroup.recurrence_interval || 0) || null,
            }
        },
        manualTimetablePdfPayload() {
            const timetable = this.adoptionDisplayedTimetable || { slots: {} }
            const hours = this.manualTimetablePdfHours(timetable)
            const weekdays = MANUAL_TIMETABLE_PDF_WEEKDAYS.filter(weekday => weekday.value !== 6
                || hours.some(hour => this.manualTimetablePdfEntriesForCell(timetable, weekday.value, hour).length))
            const configuredHoursByNumber = new Map(
                (Array.isArray(this.schoolHours) ? this.schoolHours : [])
                    .map(row => [Number(row?.hour), row]),
            )
            const numberedCells = this.manualTimetablePdfNumberedCells(timetable, hours)
            const student = this.planningMode === WITH_STUDENT
                ? [this.selectedStudentClass, this.selectedStudentFullName || this.selectedStudentLabel]
                    .filter(Boolean)
                    .join(' · ')
                : 'Ohne Studierendenbezug'

            return {
                manual_cover: true,
                title: 'Stundenplan',
                subtitle: `${this.adoptionSelectedModuleCount} ${this.adoptionSelectedModuleCount === 1 ? 'Modul' : 'Module'}`,
                student,
                schoolyear: this.schoolyearName,
                generated_at: new Intl.DateTimeFormat('de-AT', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                }).format(new Date()),
                study_selections: this.compactPlanningSelectionItems.map(item => ({
                    label: String(item?.label || '').trim().slice(0, 80),
                    value: String(item?.value || '–').trim().slice(0, 120) || '–',
                })).filter(item => item.label),
                print_options: {
                    single_weeks: false,
                    course_list: false,
                    course_overview: false,
                },
                weekdays: weekdays.map(weekday => ({ label: weekday.label })),
                semesters: [{
                    label: 'Stundenplan',
                    date_range: '',
                    weeks: [{
                        label: '',
                        hours: hours.map((hour) => {
                            const configuredHour = configuredHoursByNumber.get(hour) || {}

                            return {
                                hour,
                                from: String(configuredHour.from || '').trim().slice(0, 5),
                                until: String(configuredHour.until || '').trim().slice(0, 5),
                                cells: weekdays.map((weekday) => {
                                    const slotKey = `${weekday.value}-${hour}`
                                    const entries = this.manualTimetablePdfEntriesForCell(
                                        timetable,
                                        weekday.value,
                                        hour,
                                    )
                                    const numberedCell = numberedCells[slotKey]

                                    return {
                                        status: numberedCell?.hasExactOverlap
                                            ? 'conflict'
                                            : entries.length ? 'filled' : 'empty',
                                        courses: entries.map(entry => this.manualTimetablePdfCourse(entry)),
                                        markers: numberedCell
                                            ? [{
                                                label: `${numberedCell.hasExactOverlap ? '!' : ''}${numberedCell.number}`,
                                                title: numberedCell.label,
                                            }]
                                            : [],
                                    }
                                }),
                            }
                        }),
                    }],
                }],
            }
        },
        async downloadManualTimetablePdf() {
            if (this.pdfExporting) return

            this.pdfExporting = true

            try {
                const response = await axios.post(
                    downloadStudentTimetableOverviewPdf.url(),
                    this.manualTimetablePdfPayload(),
                    { responseType: 'blob' },
                )
                const blob = response.data instanceof Blob
                    ? response.data
                    : new Blob([response.data], { type: 'application/pdf' })
                const filename = this.fileNameFromContentDisposition(response?.headers?.['content-disposition'])
                    || 'manueller-stundenplan.pdf'

                this.downloadBlob(blob, filename)
            } catch (error) {
                console.error(error)
                window.alert?.('Das PDF konnte nicht erstellt werden.')
            } finally {
                this.pdfExporting = false
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
        timetableFreeDayOptionAriaLabel(freeDays, count) {
            const freeDaysLabel = freeDays === null
                ? 'Freie Tage egal'
                : `${freeDays} ${freeDays === 1 ? 'freier Tag' : 'freie Tage'}`

            return `${freeDaysLabel}, ${timetableVariantCountLabel(count)}`
        },
        continueToNextStep() {
            if (!this.hasPlanningSelectionContext) return

            const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

            void this.$router.push(timetableV3RouteLocation(
                TIMETABLE_V3_MODULE_SELECTION_PATH,
                planningContext,
                this.workspaceId,
            ))
        },
        returnToSelectionStep() {
            const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

            void this.$router.push(timetableV3RouteLocation(
                TIMETABLE_V3_SELECTION_PATH,
                planningContext,
                this.workspaceId,
            ))
        },
        replaceRoutePlanningContext(planningContext) {
            if (!this.$router?.replace) return

            const path = String(this.$route?.path || TIMETABLE_V3_SELECTION_PATH)

            void this.$router.replace(timetableV3RouteLocation(path, planningContext, this.workspaceId))
        },
        returnFromTimetableCreationStep() {
            if (this.timetableCalculationStatus === 'calculating' || this.timetablePageLoading) return

            const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

            void this.$router.push(timetableV3RouteLocation(
                TIMETABLE_V3_MODULE_SELECTION_PATH,
                planningContext,
                this.workspaceId,
            ))
        },
        async openManualTimetablePage() {
            if (
                this.currentStep !== MODULE_SELECTION_STEP
                || this.isLoadingState
                || this.isSavingState
                || this.timetablePageLoading
            ) return

            const planningMode = this.planningMode
            const studentCode = planningMode === WITH_STUDENT ? this.selectedStudentCode : null
            const planningContext = normalizedPlanningContext(planningMode, studentCode)
            const workspaceId = this.workspaceId

            if (!planningContext || !workspaceId) return

            this.timetableAdoptionReturnStep = MODULE_SELECTION_STEP
            this.manualSelectedCourseKeys = []
            this.manualPendingCourseKeys = []
            this.adoptionRemovedCourseKeys = []
            await this.saveState()
            const savedStudentCode = this.planningMode === WITH_STUDENT ? this.selectedStudentCode : null

            if (
                this.stateSaveFailed
                || this.currentStep !== MODULE_SELECTION_STEP
                || this.planningMode !== planningMode
                || savedStudentCode !== studentCode
                || this.workspaceId !== workspaceId
            ) return

            await this.$router.push(timetableV3RouteLocation(
                TIMETABLE_V3_ADOPTION_PATH,
                planningContext,
                workspaceId,
            ))
        },
        returnFromTimetableAdoptionStep() {
            if (
                this.currentStep !== TIMETABLE_ADOPTION_STEP
                || this.isLoadingState
                || this.isSavingState
                || this.timetablePageLoading
            ) return

            const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

            void this.$router.push(timetableV3RouteLocation(
                timetableV3AdoptionReturnPath(this.timetableAdoptionReturnStep),
                planningContext,
                this.workspaceId,
            ))
        },
        async openTimetableAdoptionPage() {
            if (
                !TIMETABLE_ADOPTION_RETURN_STEPS.includes(this.currentStep)
                || this.timetableCalculationStatus !== 'success'
                || this.isLoadingState
                || this.isSavingState
                || this.timetablePageLoading
                || !this.selectedTimetableResult
            ) return

            const returnStep = this.currentStep
            const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)
            if (!planningContext) return

            this.timetableAdoptionReturnStep = returnStep
            this.manualSelectedCourseKeys = []
            this.manualPendingCourseKeys = []
            this.adoptionRemovedCourseKeys = []
            await this.saveState()

            const fingerprint = String(this.timetableCalculationResult?.fingerprint || '').trim()
            const timetableKey = String(this.selectedTimetableResult?.key || '').trim()

            if (
                this.stateSaveFailed
                || this.currentStep !== returnStep
                || this.timetableCalculationStatus !== 'success'
                || this.timetablePageLoading
                || !this.selectedTimetableResult
                || !/^[a-f0-9]{64}$/.test(fingerprint)
                || !timetableKey
            ) return

            await this.$router.push(timetableV3AdoptionRouteLocation(
                planningContext,
                this.workspaceId,
                fingerprint,
                this.timetableSelectedIndex,
                timetableKey,
            ))
        },
        async replaceInvalidTimetableAdoption() {
            if (this.currentStep !== TIMETABLE_ADOPTION_STEP || !this.$router?.replace) return

            const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

            await this.$router.replace(timetableV3RouteLocation(
                timetableV3AdoptionReturnPath(this.timetableAdoptionReturnStep),
                planningContext,
                this.workspaceId,
            ))
        },
        normalizedTimetableCalculationCount(value) {
            return normalizedTimetableCalculationCount(value)
        },
        timetableSolutionPlanCountLabel(value) {
            return this.normalizedTimetableCalculationCount(value).toLocaleString('de-AT')
        },
        timetableSolutionPlanScenarioApplicable(scenario) {
            const moduleSelectionKey = String(scenario?.removed_module_selection_key || '').trim()

            return scenario?.status === 'calculated'
                && this.normalizedTimetableCalculationCount(scenario?.possible_timetable_count) > 0
                && this.selectedModuleKeys.includes(moduleSelectionKey)
        },
        async applyTimetableSolutionPlanScenario(scenario) {
            if (
                this.currentStep !== TIMETABLE_CREATION_STEP
                || this.isSavingState
                || this.timetableCalculationStatus === 'calculating'
                || !this.timetableSolutionPlanScenarioApplicable(scenario)
                || !this.timetableCalculationResultMatchesCurrentDraft(this.timetableCalculationResult)
            ) return

            const moduleSelectionKey = String(scenario.removed_module_selection_key || '').trim()
            const module = uniqueModules(this.moduleSelectionGroups)
                .find(moduleOption => moduleOption.selection_key === moduleSelectionKey)
            const moduleCourseKeys = new Set(
                (Array.isArray(module?.courses) ? module.courses : [])
                    .flatMap(course => normalizedCourseSelectionKeys(course)),
            )

            if (!module || moduleCourseKeys.size === 0) return

            const nextSelectedCourseKeys = this.selectedCourseKeys
                .filter(courseKey => !moduleCourseKeys.has(courseKey))

            if (nextSelectedCourseKeys.length === this.selectedCourseKeys.length) return

            this.selectedModuleKeys = this.selectedModuleKeys
                .filter(selectedModuleKey => selectedModuleKey !== moduleSelectionKey)
            this.selectedCourseKeys = nextSelectedCourseKeys
            this.moduleSelectionLimitMessage = ''
            this.resetTimetableCalculation()
            await this.calculatePossibleTimetables()
        },
        resetTimetableCalculation() {
            this.timetableCalculationRequestId = Number(this.timetableCalculationRequestId || 0) + 1
            const requestId = Number(this.timetablePageRequestId || 0) + 1
            this.timetablePageRequestId = requestId
            this.timetableCalculationStatus = 'idle'
            this.timetableCalculationResult = null
            this.timetableCalculationError = ''
            this.timetableCalculationCombinationCount = 0
            this.timetableCalculationCheckedCombinationCount = 0
            this.timetableCalculationProgressPercent = 0
            this.timetableCalculationProgressPhase = 'preparing'
            this.timetableSelectedIndex = 0
            this.timetablePageLoadingDirection = ''
            this.timetablePageError = ''
        },
        timetableCalculationPayload() {
            return {
                modules: [...(this.selectedModuleKeys || [])],
                parameters: {
                    workspace_id: this.workspaceId,
                    planning_mode: this.planningMode,
                    student_code: this.planningMode === WITH_STUDENT ? this.selectedStudentCode : null,
                    selection: { ...(this.planningSelectionValues || {}) },
                    selected_course_keys: [...(this.selectedCourseKeys || [])],
                },
                options: {
                    allow_saturday_lessons: AUTOMATIC_TIMETABLE_ALLOWS_SATURDAY,
                },
            }
        },
        isTimetableCalculationResult(calculationResult) {
            return isTimetableCalculationResult(calculationResult)
        },
        timetableCalculationResultMatchesCurrentDraft(calculationResult) {
            if (!isTimetableCalculationResult(calculationResult)) return false

            const context = calculationResult.context
            const parameters = calculationResult.parameters
            const modules = calculationResult.modules
            const expectedStudentCode = this.planningMode === WITH_STUDENT ? this.selectedStudentCode : null

            if (
                !context
                || typeof context !== 'object'
                || Array.isArray(context)
                || !parameters
                || typeof parameters !== 'object'
                || Array.isArray(parameters)
                || !Array.isArray(modules)
                || context.workspace_id !== this.workspaceId
                || context.planning_mode !== this.planningMode
                || (context.student_code ?? null) !== expectedStudentCode
                || parameters.workspace_id !== this.workspaceId
                || parameters.planning_mode !== this.planningMode
                || (parameters.student_code ?? null) !== expectedStudentCode
            ) return false

            const exactStringListMatches = (firstValues, secondValues) => {
                if (!Array.isArray(firstValues) || !Array.isArray(secondValues)) return false

                const normalizedFirstValues = firstValues
                    .map(value => String(value || '').trim())
                    .filter(Boolean)
                    .sort()
                const normalizedSecondValues = secondValues
                    .map(value => String(value || '').trim())
                    .filter(Boolean)
                    .sort()

                return normalizedFirstValues.length === firstValues.length
                    && normalizedSecondValues.length === secondValues.length
                    && new Set(normalizedFirstValues).size === normalizedFirstValues.length
                    && new Set(normalizedSecondValues).size === normalizedSecondValues.length
                    && normalizedFirstValues.length === normalizedSecondValues.length
                    && normalizedFirstValues.every((value, index) => value === normalizedSecondValues[index])
            }
            const persistedModuleKeys = modules.map(module => module?.selection_key)
            const persistedModuleCourseKeys = modules
                .flatMap(module => Array.isArray(module?.selected_course_keys) ? module.selected_course_keys : [])

            if (
                modules.some(module => !Array.isArray(module?.selected_course_keys))
                || !exactStringListMatches(persistedModuleKeys, this.selectedModuleKeys)
                || !exactStringListMatches(persistedModuleCourseKeys, this.selectedCourseKeys)
                || !exactStringListMatches(parameters.selected_course_keys, this.selectedCourseKeys)
            ) return false

            const persistedSelection = parameters.selection

            if (!persistedSelection || typeof persistedSelection !== 'object' || Array.isArray(persistedSelection)) {
                return false
            }

            const comparablePersistedSelection = Object.fromEntries(
                Object.keys(this.planningSelectionValues || {})
                    .map(key => [key, persistedSelection[key]]),
            )

            if (!planningSelectionValuesMatch(comparablePersistedSelection, this.planningSelectionValues)) {
                return false
            }

            const currentSemester = this.planningMode === WITH_STUDENT
                ? this.normalizedStudentSemester(this.selectedStudent)
                : null

            if (
                currentSemester !== null
                && Number(persistedSelection.semester) !== currentSemester
            ) return false

            const expectedWeekdays = [1, 2, 3, 4, 5, 6]

            return Array.isArray(parameters.constraints?.availableWeekdays)
                && parameters.constraints.availableWeekdays.length === expectedWeekdays.length
                && parameters.constraints.availableWeekdays.every(
                    (weekday, index) => Number(weekday) === expectedWeekdays[index],
                )
        },
        async restorePersistedTimetableCalculation() {
            const resultStep = this.currentStep
            const isAdoptionStep = resultStep === TIMETABLE_ADOPTION_STEP

            if (isAdoptionStep && this.isManualTimetableAdoption) return false

            if (
                !TIMETABLE_RESULT_STEPS.includes(resultStep)
                || !this.hasPlanningSelectionContext
                || this.studentSelectionDetailsError
                || this.scheduleCreationMode !== AUTOMATIC_TIMETABLE
                || this.selectedModuleKeys.length === 0
                || this.selectedCourseKeys.length === 0
            ) return false

            const adoptionRouteSelection = isAdoptionStep ? this.timetableAdoptionRouteSelection : null

            if (isAdoptionStep && !adoptionRouteSelection) {
                await this.replaceInvalidTimetableAdoption()

                return false
            }

            const planningMode = this.planningMode
            const studentCode = planningMode === WITH_STUDENT ? this.selectedStudentCode : null
            const requestedPage = adoptionRouteSelection?.page || 1
            const url = showTimetableV3Timetable.url({
                query: {
                    workspace_id: this.workspaceId,
                    planning_mode: planningMode,
                    ...(studentCode ? { student_code: studentCode } : {}),
                    page: requestedPage,
                    ...(adoptionRouteSelection ? { fingerprint: adoptionRouteSelection.fingerprint } : {}),
                    filters: normalizedTimetableFilters(this.timetableFilters),
                },
            })

            const requestId = Number(this.timetablePageRequestId || 0) + 1
            this.timetablePageRequestId = requestId
            this.timetableCalculationStatus = 'idle'
            this.timetableCalculationResult = null
            this.timetableCalculationError = ''
            this.timetableCalculationCombinationCount = 0
            this.timetableCalculationCheckedCombinationCount = 0
            this.timetableCalculationProgressPercent = 0
            this.timetableCalculationProgressPhase = 'preparing'
            this.timetableSelectedIndex = 0
            this.timetablePageLoading = false
            this.timetablePageLoadingDirection = ''
            this.timetablePageError = ''
            let restored = false

            try {
                const response = await axios.get(url)
                const calculationResult = response.data?.data
                const currentStudentCode = this.planningMode === WITH_STUDENT ? this.selectedStudentCode : null
                const timetablePageMeta = normalizedTimetablePageMeta(calculationResult)
                const selectedPageIndex = adoptionRouteSelection
                    ? adoptionRouteSelection.index - Number(timetablePageMeta?.offset || 0)
                    : 0
                const selectedTimetable = Array.isArray(calculationResult?.timetables)
                    ? calculationResult.timetables[selectedPageIndex]
                    : null

                if (
                    requestId !== this.timetablePageRequestId
                    || this.currentStep !== resultStep
                    || this.planningMode !== planningMode
                    || currentStudentCode !== studentCode
                    || !calculationResult
                    || !this.timetableCalculationResultMatchesCurrentDraft(calculationResult)
                    || !timetablePageMeta
                    || timetablePageMeta.currentPage !== requestedPage
                    || (isAdoptionStep
                        && (
                            String(calculationResult.fingerprint || '') !== adoptionRouteSelection.fingerprint
                            || !selectedTimetable
                            || String(selectedTimetable.key || '') !== adoptionRouteSelection.key
                        ))
                    || !timetableFiltersMatch(timetablePageMeta.filters, this.timetableFilters)
                    || Number(calculationResult.summary?.timetable_count) !== timetablePageMeta.unfilteredTotal
                ) return false

                this.timetableCalculationResult = calculationResult
                this.timetableSelectedIndex = adoptionRouteSelection?.index || 0
                this.timetableCalculationStatus = 'success'
                restored = true
            } catch {
                // The draft remains usable when no persisted calculation can be restored.
            } finally {
                if (isAdoptionStep && !restored && this.currentStep === TIMETABLE_ADOPTION_STEP) {
                    await this.replaceInvalidTimetableAdoption()
                }
            }

            return restored
        },
        async updateTimetableFilter(filterKey, filterValue) {
            const filterValueIsValid = filterKey === 'include_saturday'
                ? typeof filterValue === 'boolean'
                : filterKey === 'free_days'
                    && (filterValue === null
                        || (Number.isInteger(filterValue)
                            && filterValue >= 1
                            && filterValue <= MAX_FREE_DAYS))

            if (
                !Object.prototype.hasOwnProperty.call(DEFAULT_TIMETABLE_FILTERS, filterKey)
                || !filterValueIsValid
                || this.timetableCalculationStatus === 'calculating'
            ) return

            const previousFilters = normalizedTimetableFilters(this.timetableFilters)
            const nextFilters = {
                ...previousFilters,
                [filterKey]: filterValue,
            }

            if (timetableFiltersMatch(previousFilters, nextFilters)) return

            this.timetableFilters = nextFilters

            if (
                this.timetableCalculationStatus !== 'success'
                || !isTimetableCalculationResult(this.timetableCalculationResult)
            ) {
                await this.saveState()

                return
            }

            const filtersApplied = await this.reloadTimetableResultsForFilters()

            if (!filtersApplied) {
                if (timetableFiltersMatch(this.timetableFilters, nextFilters)) {
                    this.timetableFilters = previousFilters
                    await this.saveState()
                }

                return
            }

            await this.saveState()
        },
        async reloadTimetableResultsForFilters() {
            const currentResult = this.timetableCalculationResult
            const resultId = Number(currentResult?.id)
            const fingerprint = String(currentResult?.fingerprint || '').trim()

            if (
                !isTimetableCalculationResult(currentResult)
                || !Number.isInteger(resultId)
                || resultId < 1
                || !/^[a-f0-9]{64}$/.test(fingerprint)
                || !this.timetableCalculationResultMatchesCurrentDraft(currentResult)
            ) return false

            const planningMode = this.planningMode
            const studentCode = planningMode === WITH_STUDENT ? this.selectedStudentCode : null
            const requestedFilters = normalizedTimetableFilters(this.timetableFilters)
            const requestId = Number(this.timetablePageRequestId || 0) + 1
            this.timetablePageRequestId = requestId
            this.timetablePageLoading = true
            this.timetablePageLoadingDirection = ''
            this.timetablePageError = ''

            const url = showTimetableV3Timetable.url({
                query: {
                    workspace_id: this.workspaceId,
                    planning_mode: planningMode,
                    ...(studentCode ? { student_code: studentCode } : {}),
                    page: 1,
                    fingerprint,
                    filters: requestedFilters,
                },
            })

            try {
                const response = await axios.get(url)

                if (requestId !== this.timetablePageRequestId) return false

                const currentStudentCode = this.planningMode === WITH_STUDENT ? this.selectedStudentCode : null
                const activeResult = this.timetableCalculationResult
                const calculationResult = response.data?.data
                const nextMeta = normalizedTimetablePageMeta(calculationResult)

                if (
                    this.currentStep !== TIMETABLE_CREATION_STEP
                    || this.planningMode !== planningMode
                    || currentStudentCode !== studentCode
                    || Number(activeResult?.id) !== resultId
                    || String(activeResult?.fingerprint || '') !== fingerprint
                    || !timetableFiltersMatch(this.timetableFilters, requestedFilters)
                    || !calculationResult
                    || Number(calculationResult.id) !== resultId
                    || String(calculationResult.fingerprint || '') !== fingerprint
                    || !nextMeta
                    || nextMeta.currentPage !== 1
                    || nextMeta.offset !== 0
                    || !timetableFiltersMatch(nextMeta.filters, requestedFilters)
                    || Number(calculationResult.summary?.timetable_count) !== nextMeta.unfilteredTotal
                    || !this.timetableCalculationResultMatchesCurrentDraft(calculationResult)
                ) {
                    throw new TypeError('The filtered timetable response is inconsistent.')
                }

                this.timetableCalculationResult = calculationResult
                this.timetableSelectedIndex = 0
                this.timetablePageError = ''

                return true
            } catch (error) {
                if (requestId !== this.timetablePageRequestId) return false

                if ([401, 403, 419].includes(Number(error?.response?.status || 0))) {
                    this.timetableCalculationResult = null
                    this.timetableCalculationStatus = 'error'
                    this.timetableCalculationError = 'Ihre Berechtigung für dieses Ergebnis ist abgelaufen. Bitte laden Sie die Seite neu.'
                    this.timetableSelectedIndex = 0
                    this.timetablePageError = ''

                    return false
                }

                this.timetablePageError = 'Die Stundenpläne konnten nicht mit den ausgewählten Optionen gefiltert werden. Bitte versuchen Sie es erneut.'

                return false
            } finally {
                if (requestId === this.timetablePageRequestId) {
                    this.timetablePageLoading = false
                    this.timetablePageLoadingDirection = ''
                }
            }
        },
        async selectTimetable(targetIndexValue) {
            const targetIndex = Number(targetIndexValue)
            const currentResult = this.timetableCalculationResult
            const currentMeta = normalizedTimetablePageMeta(currentResult)

            if (
                this.currentStep !== TIMETABLE_CREATION_STEP
                || this.timetableCalculationStatus !== 'success'
                || this.timetablePageLoading
                || !Number.isInteger(targetIndex)
                || !currentMeta
                || !timetableFiltersMatch(currentMeta.filters, this.timetableFilters)
                || targetIndex < 0
                || targetIndex >= currentMeta.total
            ) return

            if (!this.timetableCalculationResultMatchesCurrentDraft(currentResult)) {
                this.resetTimetableCalculation()

                return
            }

            const currentTimetables = currentResult.timetables
            const isCurrentPageTarget = targetIndex >= currentMeta.offset
                && targetIndex < currentMeta.offset + currentTimetables.length

            if (isCurrentPageTarget) {
                this.timetableSelectedIndex = targetIndex
                this.timetablePageError = ''

                return
            }

            const targetPage = Math.floor(targetIndex / TIMETABLES_PER_PAGE) + 1
            const resultId = Number(currentResult.id)
            const fingerprint = String(currentResult.fingerprint || '').trim()

            if (
                targetPage < 1
                || targetPage > currentMeta.lastPage
                || !Number.isInteger(resultId)
                || resultId < 1
                || !/^[a-f0-9]{64}$/.test(fingerprint)
            ) return

            const planningMode = this.planningMode
            const studentCode = planningMode === WITH_STUDENT ? this.selectedStudentCode : null
            const requestId = Number(this.timetablePageRequestId || 0) + 1
            this.timetablePageRequestId = requestId
            this.timetablePageLoading = true
            this.timetablePageLoadingDirection = targetIndex > this.timetableSelectedIndex ? 'next' : 'previous'
            this.timetablePageError = ''

            const url = showTimetableV3Timetable.url({
                query: {
                    workspace_id: this.workspaceId,
                    planning_mode: planningMode,
                    ...(studentCode ? { student_code: studentCode } : {}),
                    page: targetPage,
                    fingerprint,
                    filters: normalizedTimetableFilters(this.timetableFilters),
                },
            })

            try {
                const response = await axios.get(url)

                if (requestId !== this.timetablePageRequestId) return

                const currentStudentCode = this.planningMode === WITH_STUDENT ? this.selectedStudentCode : null
                const activeResult = this.timetableCalculationResult

                if (
                    this.currentStep !== TIMETABLE_CREATION_STEP
                    || this.planningMode !== planningMode
                    || currentStudentCode !== studentCode
                    || Number(activeResult?.id) !== resultId
                    || String(activeResult?.fingerprint || '') !== fingerprint
                ) return

                if (!this.timetableCalculationResultMatchesCurrentDraft(activeResult)) {
                    this.resetTimetableCalculation()

                    return
                }

                const calculationResult = response.data?.data

                if (
                    !calculationResult
                    || Number(calculationResult.id) !== resultId
                    || String(calculationResult.fingerprint || '') !== fingerprint
                ) {
                    this.timetableCalculationResult = null
                    this.timetableCalculationStatus = 'error'
                    this.timetableCalculationError = 'Das gespeicherte Ergebnis ist nicht mehr verfügbar. Bitte berechnen Sie die Stundenpläne erneut.'
                    this.timetableSelectedIndex = 0
                    this.timetablePageError = ''

                    return
                }

                const nextMeta = normalizedTimetablePageMeta(calculationResult)

                if (
                    !nextMeta
                    || nextMeta.currentPage !== targetPage
                    || nextMeta.total !== currentMeta.total
                    || targetIndex < nextMeta.offset
                    || targetIndex >= nextMeta.offset + calculationResult.timetables.length
                    || Number(calculationResult.summary?.timetable_count) !== nextMeta.unfilteredTotal
                    || !timetableFiltersMatch(nextMeta.filters, this.timetableFilters)
                ) {
                    throw new TypeError('The timetable page response is inconsistent.')
                }

                if (!this.timetableCalculationResultMatchesCurrentDraft(calculationResult)) {
                    this.resetTimetableCalculation()

                    return
                }

                this.timetableCalculationResult = calculationResult
                this.timetableSelectedIndex = targetIndex
                this.timetablePageError = ''
            } catch (error) {
                if (requestId !== this.timetablePageRequestId) return

                if ([401, 403, 419].includes(Number(error?.response?.status || 0))) {
                    this.timetableCalculationResult = null
                    this.timetableCalculationStatus = 'error'
                    this.timetableCalculationError = 'Ihre Berechtigung für dieses Ergebnis ist abgelaufen. Bitte laden Sie die Seite neu.'
                    this.timetableSelectedIndex = 0
                    this.timetablePageError = ''

                    return
                }

                this.timetablePageError = this.timetablePageLoadingDirection === 'previous'
                    ? 'Die vorherigen Stundenpläne konnten nicht geladen werden. Bitte versuchen Sie es erneut.'
                    : 'Die nächsten Stundenpläne konnten nicht geladen werden. Bitte versuchen Sie es erneut.'
            } finally {
                if (requestId === this.timetablePageRequestId) {
                    this.timetablePageLoading = false
                    this.timetablePageLoadingDirection = ''
                }
            }
        },
        timetableCalculationErrorMessage(error) {
            const validationErrors = error?.response?.data?.errors
            const firstValidationMessage = validationErrors && typeof validationErrors === 'object'
                ? Object.values(validationErrors)
                    .flatMap((messages) => Array.isArray(messages) ? messages : [messages])
                    .find((message) => String(message || '').trim() !== '')
                : ''

            return String(firstValidationMessage || error?.response?.data?.message || '').trim()
                || 'Bitte versuchen Sie die Berechnung erneut.'
        },
        async calculatePossibleTimetables() {
            if (
                this.timetableCalculationStatus === 'calculating'
                || this.currentStep !== TIMETABLE_CREATION_STEP
                || this.selectedModuleKeys.length === 0
                || this.selectedCourseKeys.length === 0
            ) return

            const requestId = Number(this.timetableCalculationRequestId || 0) + 1
            this.timetableCalculationRequestId = requestId
            this.timetablePageRequestId = Number(this.timetablePageRequestId || 0) + 1
            this.timetableCalculationStatus = 'calculating'
            this.timetableCalculationResult = null
            this.timetableCalculationError = ''
            this.timetableCalculationCombinationCount = 0
            this.timetableCalculationCheckedCombinationCount = 0
            this.timetableCalculationProgressPercent = 0
            this.timetableCalculationProgressPhase = 'preparing'
            this.timetableSelectedIndex = 0
            this.timetablePageLoading = false
            this.timetablePageLoadingDirection = ''
            this.timetablePageError = ''

            await this.saveState()

            if (requestId !== this.timetableCalculationRequestId) return

            if (this.stateSaveFailed) {
                this.timetableCalculationStatus = 'error'
                this.timetableCalculationError = 'Die Optionen konnten vor der Berechnung nicht gespeichert werden.'
                return
            }

            try {
                const response = await fetchTimetableCalculation(this.timetableCalculationPayload())
                const calculationResult = await consumeTimetableCalculationStream(response, (event) => {
                    if (requestId !== this.timetableCalculationRequestId) return

                    const combinationCount = normalizedTimetableCalculationCount(
                        event.combination_count,
                    )
                    const progressPercent = Math.min(
                        100,
                        Math.max(0, normalizedTimetableCalculationCount(event.progress_percent)),
                    )
                    const checkedCombinationCount = Math.min(
                        combinationCount,
                        normalizedTimetableCalculationCount(event.checked_combination_count),
                    )

                    this.timetableCalculationCombinationCount = combinationCount
                    this.timetableCalculationCheckedCombinationCount = checkedCombinationCount
                    this.timetableCalculationProgressPercent = Math.max(
                        normalizedTimetableCalculationCount(this.timetableCalculationProgressPercent),
                        progressPercent,
                    )
                    this.timetableCalculationProgressPhase = normalizedTimetableCalculationProgressPhase(
                        event.phase,
                    )
                })

                if (requestId !== this.timetableCalculationRequestId) return

                if (!isTimetableCalculationResult(calculationResult)) {
                    throw new TypeError('The timetable calculation response is missing its summary.')
                }

                const timetablePageMeta = normalizedTimetablePageMeta(calculationResult)

                if (
                    !timetablePageMeta
                    || timetablePageMeta.currentPage !== 1
                    || timetablePageMeta.offset !== 0
                    || !timetableFiltersMatch(timetablePageMeta.filters, DEFAULT_TIMETABLE_FILTERS)
                    || Number(calculationResult.summary?.timetable_count) !== timetablePageMeta.unfilteredTotal
                ) {
                    throw new TypeError('The timetable calculation response has invalid pagination data.')
                }

                this.timetableCalculationResult = calculationResult
                this.timetableSelectedIndex = 0

                if (!timetableFiltersMatch(this.timetableFilters, DEFAULT_TIMETABLE_FILTERS)) {
                    const filtersApplied = await this.reloadTimetableResultsForFilters()

                    if (requestId !== this.timetableCalculationRequestId) return

                    if (!filtersApplied) {
                        if (this.timetableCalculationStatus === 'error') return

                        this.timetableFilters = { ...DEFAULT_TIMETABLE_FILTERS }
                        this.timetableCalculationResult = calculationResult
                        this.timetableSelectedIndex = 0
                        this.timetablePageError = 'Die ausgewählten Optionen konnten nicht angewendet werden. Das vollständige Ergebnis wird angezeigt.'
                        await this.saveState()

                        if (requestId !== this.timetableCalculationRequestId) return
                    }
                }

                this.timetableCalculationCombinationCount = normalizedTimetableCalculationCount(
                    calculationResult.summary.timetable_variation_count,
                )
                this.timetableCalculationCheckedCombinationCount = this.timetableCalculationCombinationCount
                this.timetableCalculationProgressPercent = 100
                this.timetableCalculationProgressPhase = 'complete'
                this.timetableCalculationStatus = 'success'
            } catch (error) {
                if (requestId !== this.timetableCalculationRequestId) return

                this.timetableCalculationStatus = 'error'
                this.timetableCalculationError = this.timetableCalculationErrorMessage(error)
            }
        },
        async openTimetableCreationPage() {
            if (
                this.scheduleCreationMode !== AUTOMATIC_TIMETABLE
                || this.selectedModuleCount === 0
                || this.isSavingState
            ) return

            await this.saveState()
            if (this.stateSaveFailed) return

            const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

            await this.$router.push(timetableV3RouteLocation(
                TIMETABLE_V3_CREATION_PATH,
                planningContext,
                this.workspaceId,
            ))
            await this.calculatePossibleTimetables()
        },
        async ensureValidCurrentStep() {
            if (
                [MODULE_SELECTION_STEP, ...TIMETABLE_RESULT_STEPS].includes(this.currentStep)
                && !this.hasPlanningSelectionContext
            ) {
                await this.$router.replace(timetableV3RouteLocation(
                    TIMETABLE_V3_SELECTION_PATH,
                    null,
                    this.workspaceId,
                ))
                return
            }

            if (
                TIMETABLE_RESULT_STEPS.includes(this.currentStep)
                && !this.isManualTimetableAdoption
                && !this.studentSelectionDetailsError
                && (this.selectedModuleCount === 0 || this.scheduleCreationMode !== AUTOMATIC_TIMETABLE)
            ) {
                const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

                await this.$router.replace(timetableV3RouteLocation(
                    TIMETABLE_V3_MODULE_SELECTION_PATH,
                    planningContext,
                    this.workspaceId,
                ))
            }
        },
        async initializeWorkspace() {
            const routeWorkspaceId = normalizedWorkspaceId(this.$route?.query?.workspace_id)
            this.workspaceId = routeWorkspaceId || createWorkspaceId()

            if (routeWorkspaceId || !this.$router?.replace) return

            const routePlanningContext = normalizedPlanningContext(
                this.$route?.query?.planning_mode,
                this.$route?.query?.student_code,
            )
            const path = String(this.$route?.path || TIMETABLE_V3_SELECTION_PATH)

            await this.$router.replace(timetableV3RouteLocation(
                path,
                routePlanningContext,
                this.workspaceId,
            ))
        },
        async loadState() {
            this.isLoadingState = true
            this.stateLoadFailed = false

            try {
                const response = await axios.get(showTimetableV3State.url({
                    query: { workspace_id: this.workspaceId },
                }))
                this.storedState = response.data?.data?.state ?? null
                this.restoreCreationOptions()
                this.restoreAdoptionReturnStep()
                await this.restoreEntrySelection()
                this.restoreManualTimetableDraft()
            } catch {
                this.stateLoadFailed = true
            } finally {
                try {
                    await this.ensureValidCurrentStep()
                    await this.restorePersistedTimetableCalculation()
                } finally {
                    this.isLoadingState = false
                }
            }
        },
        restoreCreationOptions() {
            const storedCreationOptions = this.storedState?.creationOptions
            const storedFilters = storedCreationOptions?.filters

            this.timetableFilters = normalizedTimetableFilters(storedFilters)
        },
        restoreAdoptionReturnStep() {
            this.timetableAdoptionReturnStep = normalizedTimetableAdoptionReturnStep(
                this.storedState?.adoptionReturnStep,
            )
        },
        restoreManualTimetableDraft() {
            this.manualSelectedCourseKeys = []
            this.manualPendingCourseKeys = []
            this.adoptionRemovedCourseKeys = []

            if (this.currentStep !== TIMETABLE_ADOPTION_STEP) return

            const storedDraft = this.storedState?.manualTimetableDraft
            if (!storedDraft || typeof storedDraft !== 'object' || Array.isArray(storedDraft)) return

            const storedStudentCode = storedDraft.mode === WITH_STUDENT
                ? String(storedDraft.studentCode || '').trim()
                : null
            const currentStudentCode = this.planningMode === WITH_STUDENT
                ? String(this.selectedStudentCode || '').trim()
                : null
            const expectedSource = this.isManualTimetableAdoption
                ? BLANK_MANUAL_TIMETABLE_DRAFT
                : AUTOMATIC_MANUAL_TIMETABLE_DRAFT

            if (
                storedDraft.mode !== this.planningMode
                || storedStudentCode !== currentStudentCode
                || storedDraft.source !== expectedSource
                || !planningSelectionValuesMatch(storedDraft.planningValues || {}, this.planningSelectionValues || {})
            ) return

            if (expectedSource === AUTOMATIC_MANUAL_TIMETABLE_DRAFT) {
                const routeSelection = this.timetableAdoptionRouteSelection

                if (
                    !routeSelection
                    || String(storedDraft.fingerprint || '').trim() !== routeSelection.fingerprint
                    || String(storedDraft.timetableKey || '').trim() !== routeSelection.key
                    || Number(storedDraft.timetableIndex) !== routeSelection.index
                ) return
            }

            const courses = uniqueCoursesForModuleGroups([
                ...this.moduleSelectionGroups,
                ...this.mainModuleSelectionGroups,
            ])
            const validCourseKeys = new Set(courses.flatMap(course => normalizedCourseSelectionKeys(course)))
            const storedSelectedCourseKeys = new Set(
                normalizedCourseKeyList(storedDraft.selectedCourseKeys)
                    .filter(courseKey => validCourseKeys.has(courseKey)),
            )

            this.manualSelectedCourseKeys = [...new Set(courses
                .filter(course => normalizedCourseSelectionKeys(course)
                    .some(courseKey => storedSelectedCourseKeys.has(courseKey)))
                .flatMap(course => normalizedCourseSelectionKeys(course)))]
            this.adoptionRemovedCourseKeys = normalizedCourseKeyList(storedDraft.removedCourseKeys)
                .filter(courseKey => validCourseKeys.has(courseKey))
        },
        async restoreEntrySelection() {
            const entrySelection = this.storedState?.entrySelection

            if (entrySelection?.mode === WITH_STUDENT && entrySelection.student) {
                this.planningMode = WITH_STUDENT
                this.selectedStudent = entrySelection.student
                this.resetSelectedStudentSelectionDetails()
                await this.loadSelectedStudentSelection()
                await this.hydrateSelectedStudentDetails()
                this.restoreCreationScheduleMode()
                return
            }

            if (entrySelection?.mode === WITHOUT_STUDENT) {
                this.planningMode = WITHOUT_STUDENT
                this.selectedStudent = null
                this.resetSelectedStudentSelectionDetails()
                await this.loadSelectedStudentSelection()
                this.restoreCreationScheduleMode()
            }
        },
        restoreCreationScheduleMode() {
            if (TIMETABLE_RESULT_STEPS.includes(this.currentStep) && this.selectedModuleCount > 0) {
                this.scheduleCreationMode = AUTOMATIC_TIMETABLE
            }
        },
        chooseWithStudent() {
            this.studentSearch = ''
            this.studentOptionsError = false
            this.emailCopyStatus = 'idle'
            this.studentInfoDialogOpen = false
            this.studyInfoDialogOpen = false
            this.studentDialogOpen = true
            this.$nextTick(() => this.focusStudentSearchField())
            void this.loadStudents()
        },
        async chooseWithoutStudent() {
            this.planningMode = WITHOUT_STUDENT
            this.selectedStudent = null
            this.resetSelectedStudentSelectionDetails()
            this.emailCopyStatus = 'idle'
            this.studentInfoDialogOpen = false
            this.studyInfoDialogOpen = false
            await this.saveState()
            this.replaceRoutePlanningContext(normalizedPlanningContext(this.planningMode))
            void this.loadSelectedStudentSelection()
        },
        async restartPlanning() {
            if (this.timetablePageLoading) return

            const planningContext = normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

            this.planningMode = null
            this.selectedStudent = null
            this.studentDialogOpen = false
            this.studentSearch = ''
            this.emailCopyStatus = 'idle'
            this.studentInfoDialogOpen = false
            this.studyInfoDialogOpen = false
            this.resetSelectedStudentSelectionDetails()
            this.timetableAdoptionReturnStep = TIMETABLE_CREATION_STEP
            this.timetableFilters = { ...DEFAULT_TIMETABLE_FILTERS }
            this.resetTimetableCalculation()
            await this.saveState(planningContext)
            await this.$router.replace(timetableV3RouteLocation(
                TIMETABLE_V3_SELECTION_PATH,
                null,
                this.workspaceId,
            ))
        },
        closeStudentDialog() {
            this.studentDialogOpen = false
            this.studentSearch = ''
        },
        openStudentInfoDialog() {
            if (!this.selectedStudent) return

            this.studyInfoDialogOpen = false
            this.studentInfoDialogOpen = true
            void this.loadSelectedStudentSelection()
        },
        closeStudentInfoDialog() {
            this.studentInfoDialogOpen = false
        },
        openStudyInfoDialog() {
            if (!this.selectedStudent) return

            this.studentInfoDialogOpen = false
            this.studyInfoDialogOpen = true
            void this.loadSelectedStudentSelection()
        },
        closeStudyInfoDialog() {
            this.studyInfoDialogOpen = false
        },
        openSchoolLevelDialog() {
            if (!this.selectedStudent || (!this.selectedStudentSchoolLevelMismatch && !this.selectedStudentOriginalSchoolLevel)) return

            this.studentInfoDialogOpen = false
            this.studyInfoDialogOpen = false
            this.schoolLevelDialogOriginalValue = this.selectedStudentOriginalSchoolLevel
                || this.selectedStudentImportedSchoolLevel
            this.schoolLevelDialogSelection = this.selectedStudentImportedSchoolLevel
            this.schoolLevelDialogError = ''
            this.schoolLevelDialogOpen = true
        },
        closeSchoolLevelDialog() {
            if (this.schoolLevelDialogSaving) return

            this.schoolLevelDialogOpen = false
            this.schoolLevelDialogSelection = ''
            this.schoolLevelDialogOriginalValue = ''
            this.schoolLevelDialogError = ''
        },
        selectSchoolLevelDialogValue(schoolLevel) {
            if (this.schoolLevelDialogSaving) return

            const normalizedSchoolLevel = String(schoolLevel || '').trim()
            if (!normalizedSchoolLevel) return

            this.schoolLevelDialogSelection = normalizedSchoolLevel
            this.schoolLevelDialogError = ''
        },
        schoolLevelDialogErrorMessage(error) {
            const validationErrors = error?.response?.data?.errors
            const firstValidationMessage = validationErrors && typeof validationErrors === 'object'
                ? Object.values(validationErrors).flat().find(Boolean)
                : null

            return String(firstValidationMessage || 'Die Schulstufe konnte nicht gespeichert werden.').trim()
        },
        async saveSchoolLevelDialog() {
            if (!this.canSaveSchoolLevelDialog) return

            const studentCode = this.selectedStudentCode
            const schoolLevel = String(this.schoolLevelDialogSelection || '').trim()
            const selection = this.planningSelectionForRequest(studentCode)
            this.schoolLevelDialogSaving = true
            this.schoolLevelDialogError = ''

            try {
                const response = await axios.put(updateV3StudentSchoolLevel.url(), {
                    student_code: studentCode,
                    school_level: schoolLevel,
                    ...(Object.keys(selection).length ? { selection } : {}),
                })

                if (this.selectedStudentCode !== studentCode) return

                this.applySelectedStudentInformation(response.data?.data || {}, studentCode)
                this.resetTimetableCalculation()
                this.schoolLevelDialogOpen = false
                this.schoolLevelDialogSelection = ''
                this.schoolLevelDialogOriginalValue = ''
                await this.saveState()
            } catch (error) {
                this.schoolLevelDialogError = this.schoolLevelDialogErrorMessage(error)
            } finally {
                this.schoolLevelDialogSaving = false
            }
        },
        focusStudentSearchField() {
            const searchField = this.$refs.studentSearchField

            searchField?.focus?.()
            searchField?.$el?.querySelector?.('input')?.focus?.()
        },
        async loadStudents() {
            if (this.students.length || this.studentOptionsLoading) return

            this.studentOptionsLoading = true
            this.studentOptionsError = false

            try {
                const response = await axios.get(loadRobotStudents.url())
                this.students = Array.isArray(response.data?.data) ? response.data.data : []
            } catch {
                this.students = []
                this.studentOptionsError = true
            } finally {
                this.studentOptionsLoading = false
            }
        },
        async loadSchoolHours() {
            if (this.schoolHoursLoaded || this.schoolHoursLoading) return

            this.schoolHoursLoading = true

            try {
                const response = await axios.get(loadStudentTimetableSchoolHours.url())

                this.schoolHours = Array.isArray(response.data?.data) ? response.data.data : []
            } catch {
                this.schoolHours = []
            } finally {
                this.schoolHoursLoaded = true
                this.schoolHoursLoading = false
            }
        },
        resetSelectedStudentSelectionDetails() {
            this.studentSelectionDetailsLoading = false
            this.studentSelectionDetailsError = false
            this.studentSelectionDetailsCode = ''
            this.studentSelectionDetailsRequestCode = ''
            this.studentSelectionItems = []
            this.studentStudyModuleGroups = []
            this.moduleSelectionGroups = []
            this.mainModuleSelectionGroups = []
            this.manualModuleCatalogView = MANUAL_STUDENT_MODULE_CATALOG
            this.selectedModuleKeys = []
            this.selectedCourseKeys = []
            this.manualSelectedCourseKeys = []
            this.manualPendingCourseKeys = []
            this.adoptionRemovedCourseKeys = []
            this.moduleSelectionLimitMessage = ''
            this.scheduleCreationMode = null
            this.activeModuleGroupKey = ''
            this.activeManualModuleGroupKey = ''
            this.moduleCoursesDialogOpen = false
            this.moduleCourseDialogModule = null
            this.moduleCoursesDialogReadOnly = false
            this.planningSelectionFields = []
            this.planningSelectionValues = {}
        },
        planningSelectionForRequest(selectionContextCode) {
            const storedSelection = this.storedState?.planningSelection
            const storedContextCode = storedSelection?.mode === WITH_STUDENT
                ? String(storedSelection.studentCode || '').trim()
                : storedSelection?.mode === WITHOUT_STUDENT ? WITHOUT_STUDENT : ''
            const selection = Object.keys(this.planningSelectionValues).length
                ? this.planningSelectionValues
                : storedContextCode === selectionContextCode && storedSelection?.values
                    ? storedSelection.values
                    : {}

            return Object.fromEntries(Object.entries(selection)
                .map(([key, value]) => [key, value ?? '']))
        },
        applySelectedStudentInformation(studentInformation, selectionContextCode) {
            const studentCode = this.selectedStudentCode

            if (studentCode) {
                this.selectedStudent = {
                    ...this.selectedStudent,
                    religion: String(studentInformation.religion || '').trim(),
                    instructionType: String(studentInformation.instruction_type || '').trim(),
                    subjectPlan: String(studentInformation.subject_plan || '').trim(),
                    subjectPlanMismatch: studentInformation.subject_plan_mismatch === true,
                    schoolLevel: String(studentInformation.school_level || '').trim(),
                    schoolLevelMismatch: studentInformation.school_level_mismatch === true,
                    originalSchoolLevel: String(studentInformation.original_school_level || '').trim(),
                    schoolLevelOptions: Array.isArray(studentInformation.school_level_options)
                        ? studentInformation.school_level_options
                        : [],
                    semester: this.normalizedStudentSemester({ semester: studentInformation.semester }),
                }
            }
            this.studentSelectionItems = Array.isArray(studentInformation.items)
                ? studentInformation.items
                : []
            this.studentStudyModuleGroups = Array.isArray(studentInformation.module_groups)
                ? studentInformation.module_groups
                : []
            this.setPlanningSelectionFields(studentInformation.selection_fields, selectionContextCode)
            this.setModuleSelectionGroups(studentInformation.module_selection_groups, selectionContextCode)
            this.setMainModuleSelectionGroups(studentInformation.main_module_selection_groups)
            this.studentSelectionDetailsCode = selectionContextCode
        },
        async loadSelectedStudentSelection() {
            const studentCode = this.selectedStudentCode
            const selectionContextCode = this.planningMode === WITH_STUDENT
                ? studentCode
                : this.planningMode === WITHOUT_STUDENT ? WITHOUT_STUDENT : ''

            if (!selectionContextCode) {
                this.resetSelectedStudentSelectionDetails()
                return
            }

            if (this.studentSelectionDetailsCode === selectionContextCode) return
            if (this.studentSelectionDetailsLoading && this.studentSelectionDetailsRequestCode === selectionContextCode) return

            this.studentSelectionDetailsLoading = true
            this.studentSelectionDetailsError = false
            this.studentSelectionDetailsRequestCode = selectionContextCode

            try {
                const url = studentCode
                    ? loadV3StudentInformation.url({ query: { student_code: studentCode } })
                    : loadV3StudentInformation.url()
                const selection = this.planningSelectionForRequest(selectionContextCode)
                const response = Object.keys(selection).length
                    ? await axios.get(url, { params: { selection } })
                    : await axios.get(url)
                const currentSelectionContextCode = this.planningMode === WITH_STUDENT
                    ? this.selectedStudentCode
                    : this.planningMode === WITHOUT_STUDENT ? WITHOUT_STUDENT : ''

                if (currentSelectionContextCode !== selectionContextCode) return

                const studentInformation = response.data?.data || {}

                this.applySelectedStudentInformation(studentInformation, selectionContextCode)
            } catch {
                const currentSelectionContextCode = this.planningMode === WITH_STUDENT
                    ? this.selectedStudentCode
                    : this.planningMode === WITHOUT_STUDENT ? WITHOUT_STUDENT : ''

                if (currentSelectionContextCode === selectionContextCode) {
                    this.studentSelectionDetailsError = true
                }
            } finally {
                if (this.studentSelectionDetailsRequestCode === selectionContextCode) {
                    this.studentSelectionDetailsLoading = false
                    this.studentSelectionDetailsRequestCode = ''
                }
            }
        },
        setPlanningSelectionFields(selectionFields, selectionContextCode) {
            const fields = Array.isArray(selectionFields) ? selectionFields : []
            const values = Object.fromEntries(fields.map(field => [field.key, field.selected_value ?? null]))
            const storedSelection = this.storedState?.planningSelection
            const storedContextCode = storedSelection?.mode === WITH_STUDENT
                ? String(storedSelection.studentCode || '').trim()
                : storedSelection?.mode === WITHOUT_STUDENT ? WITHOUT_STUDENT : ''

            if (storedContextCode === selectionContextCode) {
                fields.forEach((field) => {
                    const storedValue = storedSelection?.values?.[field.key]
                    const allowedValues = (Array.isArray(field.options) ? field.options : [])
                        .map(option => option?.value)

                    if (allowedValues.includes(storedValue)) {
                        values[field.key] = storedValue
                    }
                })
            }

            this.planningSelectionFields = fields
            this.planningSelectionValues = values
        },
        setModuleSelectionGroups(moduleGroups, selectionContextCode) {
            const groups = Array.isArray(moduleGroups) ? moduleGroups : []
            const modules = groups
                .flatMap(group => Array.isArray(group.modules) ? group.modules : [])
            const validSelectionKeys = new Set(modules
                .map(module => String(module?.selection_key || '').trim())
                .filter(Boolean))
            const validCourseKeys = new Set(modules
                .flatMap(module => Array.isArray(module?.courses) ? module.courses : [])
                .flatMap(course => normalizedCourseSelectionKeys(course))
                .filter(Boolean))
            const storedSelection = this.storedState?.moduleSelection
            const storedContextCode = storedSelection?.mode === WITH_STUDENT
                ? String(storedSelection.studentCode || '').trim()
                : storedSelection?.mode === WITHOUT_STUDENT ? WITHOUT_STUDENT : ''
            const storedPlanningValues = storedSelection?.planningValues || {}
            const currentPlanningValues = this.planningSelectionValues || {}
            const storedValuesMatch = planningSelectionValuesMatch(storedPlanningValues, currentPlanningValues)
            const storedScheduleCreationMode = storedSelection?.scheduleCreationMode

            if (
                storedContextCode === selectionContextCode
                && storedValuesMatch
                && [AUTOMATIC_TIMETABLE, MANUAL_TIMETABLE].includes(storedScheduleCreationMode)
            ) {
                this.scheduleCreationMode = storedScheduleCreationMode
            }
            const selectedKeys = !this.moduleSelectionResetPending
                && storedContextCode === selectionContextCode
                && storedValuesMatch
                ? storedSelection?.selectedKeys || []
                : []
            const hasStoredCourseSelection = Array.isArray(storedSelection?.selectedCourseKeys)
            const selectedCourseKeys = !this.moduleSelectionResetPending
                && storedContextCode === selectionContextCode
                && storedValuesMatch
                && hasStoredCourseSelection
                ? storedSelection.selectedCourseKeys
                : []
            this.moduleSelectionGroups = groups
            this.selectedModuleKeys = [...new Set(selectedKeys)]
                .filter(selectionKey => validSelectionKeys.has(selectionKey))
            const storedSelectedCourseKeys = new Set(
                [...new Set(selectedCourseKeys)]
                    .filter(courseKey => validCourseKeys.has(courseKey)),
            )
            const restoredSelectedCourseKeys = modules
                .flatMap(module => Array.isArray(module?.courses) ? module.courses : [])
                .filter(course => normalizedCourseSelectionKeys(course)
                    .some(courseKey => storedSelectedCourseKeys.has(courseKey)))
                .flatMap(course => normalizedCourseSelectionKeys(course))
            this.selectedCourseKeys = [...new Set(restoredSelectedCourseKeys)]

            if (!hasStoredCourseSelection && this.selectedModuleKeys.length) {
                const selectedModuleKeys = new Set(this.selectedModuleKeys)

                const selectedCourseKeysForModules = modules
                    .filter(module => selectedModuleKeys.has(module.selection_key))
                    .flatMap(module => Array.isArray(module.courses) ? module.courses : [])
                    .flatMap(course => normalizedCourseSelectionKeys(course))
                    .filter(courseKey => validCourseKeys.has(courseKey))
                this.selectedCourseKeys = [...new Set(selectedCourseKeysForModules)]
            }
            if (hasStoredCourseSelection) {
                const selectedCourseKeys = new Set(this.selectedCourseKeys)

                this.selectedModuleKeys = modules
                    .filter(module => (Array.isArray(module.courses) ? module.courses : [])
                        .some(course => normalizedCourseSelectionKeys(course)
                            .some(courseKey => selectedCourseKeys.has(courseKey))))
                    .map(module => module.selection_key)
                    .filter(Boolean)
            }
            const constrainedSelection = constrainedModuleSelection(
                groups,
                this.selectedModuleKeys,
                this.selectedCourseKeys,
            )
            this.selectedModuleKeys = constrainedSelection.selectedModuleKeys
            this.selectedCourseKeys = constrainedSelection.selectedCourseKeys
            this.moduleSelectionLimitMessage = constrainedSelection.wasConstrained
                ? `Die gespeicherte Auswahl wurde auf maximal ${MAX_SELECTED_MODULES} Module und ${MAX_SELECTED_MODULE_HOURS} Stunden begrenzt.`
                : ''
            if (!groups.some(group => group.key === this.activeModuleGroupKey)) {
                this.activeModuleGroupKey = ''
            }
            this.moduleSelectionResetPending = false
        },
        setMainModuleSelectionGroups(moduleGroups) {
            this.mainModuleSelectionGroups = (Array.isArray(moduleGroups) ? moduleGroups : [])
                .filter(group => group && typeof group === 'object' && !Array.isArray(group))
        },
        showManualModuleCatalog(catalog) {
            if (this.planningMode !== WITH_STUDENT) return
            if (![MANUAL_STUDENT_MODULE_CATALOG, MANUAL_MAIN_MODULE_CATALOG].includes(catalog)) return
            if (this.manualModuleCatalogView === catalog) return

            this.manualModuleCatalogView = catalog
            this.resetManualModuleCatalogDisclosure()
        },
        selectedModuleCountForGroup(group) {
            const selectedKeys = new Set(this.selectedModuleKeys)

            return (Array.isArray(group?.modules) ? group.modules : [])
                .filter(module => selectedKeys.has(module.selection_key))
                .length
        },
        moduleSelected(module) {
            return this.selectedModuleKeys.includes(module?.selection_key)
        },
        selectableModulesForGroup(group) {
            return (Array.isArray(group?.modules) ? group.modules : [])
                .filter((module) => {
                    const moduleSelectionKey = String(module?.selection_key || '').trim()
                    const courseSelectionKeys = (Array.isArray(module?.courses) ? module.courses : [])
                        .flatMap(course => normalizedCourseSelectionKeys(course))

                    return moduleSelectionKey && courseSelectionKeys.length
                })
        },
        moduleSelectionKeysForGroup(group) {
            return this.selectableModulesForGroup(group)
                .map(module => String(module.selection_key).trim())
        },
        courseSelectionKeysForGroup(group) {
            return this.selectableModulesForGroup(group)
                .flatMap(module => module.courses)
                .flatMap(course => normalizedCourseSelectionKeys(course))
        },
        allModulesSelectedForGroup(group) {
            const moduleSelectionKeys = this.moduleSelectionKeysForGroup(group)
            const selectedModuleKeys = new Set(this.selectedModuleKeys)

            return moduleSelectionKeys.length > 0
                && moduleSelectionKeys.every(moduleSelectionKey => selectedModuleKeys.has(moduleSelectionKey))
        },
        hasSelectedModulesForGroup(group) {
            const moduleSelectionKeys = this.moduleSelectionKeysForGroup(group)
            const courseSelectionKeys = this.courseSelectionKeysForGroup(group)
            const selectedModuleKeys = new Set(this.selectedModuleKeys)
            const selectedCourseKeys = new Set(this.selectedCourseKeys)

            return moduleSelectionKeys.some(moduleSelectionKey => selectedModuleKeys.has(moduleSelectionKey))
                || courseSelectionKeys.some(courseSelectionKey => selectedCourseKeys.has(courseSelectionKey))
        },
        moduleCourseCount(module) {
            return Array.isArray(module?.courses) ? module.courses.length : 0
        },
        manualModuleCourseCount(module) {
            const courses = Array.isArray(module?.courses) ? module.courses : []

            return courses.filter(course => !courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys)).length
        },
        manualModuleAlreadyPlanned(module) {
            const courses = Array.isArray(module?.courses) ? module.courses : []

            return courses.some(course => courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys))
        },
        manualModuleGroupAlreadyPlanned(group) {
            const modules = Array.isArray(group?.modules) ? group.modules : []

            return modules.length > 0 && modules.every(module => this.manualModuleAlreadyPlanned(module))
        },
        manualModuleGroupNotIntended(group) {
            const modules = Array.isArray(group?.modules) ? group.modules : []

            return modules.length > 0
                && modules.every(module => module?.is_intended_for_selection === false)
        },
        selectedCourseCountForModule(module) {
            const selectedCourseKeys = new Set(this.selectedCourseKeys)

            return (Array.isArray(module?.courses) ? module.courses : [])
                .filter(course => {
                    const courseKeys = normalizedCourseSelectionKeys(course)

                    return courseKeys.length > 0 && courseKeys.every(courseKey => selectedCourseKeys.has(courseKey))
                })
                .length
        },
        moduleCourseSelected(course) {
            const selectedCourseKeys = new Set(this.selectedCourseKeys)
            const courseKeys = normalizedCourseSelectionKeys(course)

            return courseKeys.length > 0 && courseKeys.every(courseKey => selectedCourseKeys.has(courseKey))
        },
        manualModuleCourseSelected(course) {
            const selectedCourseKeys = new Set(this.manualSelectedCourseKeys)
            const courseKeys = normalizedCourseSelectionKeys(course)

            return courseKeys.length > 0 && courseKeys.every(courseKey => selectedCourseKeys.has(courseKey))
        },
        manualPendingCourseSelected(course) {
            const pendingCourseKeys = new Set(this.manualPendingCourseKeys)
            const courseKeys = normalizedCourseSelectionKeys(course)

            return courseKeys.length > 0 && courseKeys.every(courseKey => pendingCourseKeys.has(courseKey))
        },
        displayedModuleCourseSelected(course) {
            return this.moduleCoursesDialogReadOnly
                ? this.manualPendingCourseSelected(course)
                : this.moduleCourseSelected(course)
        },
        courseSelectionKeys(course) {
            return normalizedCourseSelectionKeys(course)
        },
        courseScheduleLabels(course) {
            return normalizedCourseScheduleLabels(course)
        },
        courseScheduleRows(course) {
            return normalizedCourseScheduleRows(course)
        },
        courseScheduleRowOverlapLabels(course, scheduleRow) {
            const activeCourseKeys = this.moduleCoursesDialogReadOnly
                ? [
                    ...(Array.isArray(this.adoptionPlacedCourseKeys) ? this.adoptionPlacedCourseKeys : []),
                    ...(Array.isArray(this.manualPendingCourseKeys) ? this.manualPendingCourseKeys : []),
                ]
                : (Array.isArray(this.selectedCourseKeys) ? this.selectedCourseKeys : [])
            if (!activeCourseKeys.length) return []

            const scheduleEntryKeys = new Set(Array.isArray(scheduleRow?.entryKeys) ? scheduleRow.entryKeys : [])
            const scheduleEntries = normalizedCourseTimetableEntries(course)
                .filter(entry => !scheduleEntryKeys.size || scheduleEntryKeys.has(String(entry.key || '').trim()))
            if (!scheduleEntries.length) return []

            const currentCourseKeys = new Set(normalizedCourseSelectionKeys(course))

            return [...new Set((Array.isArray(this.manualCatalogCourses) ? this.manualCatalogCourses : [])
                .filter(otherCourse => courseUsesSelectedKey(otherCourse, activeCourseKeys))
                .filter(otherCourse => !normalizedCourseSelectionKeys(otherCourse)
                    .some(courseKey => currentCourseKeys.has(courseKey)))
                .flatMap(otherCourse => normalizedCourseTimetableEntries(otherCourse)
                    .filter(otherEntry => scheduleEntries
                        .some(scheduleEntry => timetableEntriesOverlap(scheduleEntry, otherEntry)))
                    .map(otherEntry => timetableCourseOverlapLabel(otherCourse, otherEntry)))
                .filter(Boolean))]
        },
        moduleDisplayCode(module) {
            return canonicalTimetableCourseLabel(module?.code)
        },
        moduleDisplayName(module) {
            return canonicalTimetableModuleName(module?.name, module?.code)
        },
        moduleDisplayNameVisible(module) {
            const name = this.moduleDisplayName(module)

            return name !== '' && name !== this.moduleDisplayCode(module)
        },
        solutionModuleDisplayCode(scenario) {
            return canonicalTimetableCourseLabel(scenario?.removed_module_code)
        },
        solutionModuleDisplayName(scenario) {
            return canonicalTimetableModuleName(
                scenario?.removed_module_name,
                scenario?.removed_module_code,
            )
        },
        solutionModuleDisplayNameVisible(scenario) {
            const name = this.solutionModuleDisplayName(scenario)

            return name !== '' && name !== this.solutionModuleDisplayCode(scenario)
        },
        moduleCourseTitle(course) {
            return canonicalTimetableCourseLabel(
                course?.title,
                this.moduleCourseDialogModule?.code,
            )
        },
        moduleCourseSubtitle(course) {
            const subtitle = canonicalTimetableCourseLabel(
                course?.course_title,
                this.moduleCourseDialogModule?.code,
            )
            const title = this.moduleCourseTitle(course)
            const moduleBase = canonicalTimetableCourseLabel(this.moduleCourseDialogModule?.code)
                .replace(/\d+$/u, '')

            if (!subtitle || subtitle === title || subtitle === moduleBase) return ''
            if (title.startsWith(`${subtitle}-`) || title.startsWith(`${subtitle} -`)) return ''

            return subtitle
        },
        moduleGroupActive(group) {
            return group?.key === this.activeModuleGroupKey
        },
        moduleGroupIcon(group) {
            if (group?.code) return 'mdi-bookshelf'

            return {
                finished: 'mdi-check-decagram-outline',
                negative: 'mdi-alert-circle-outline',
                previous: 'mdi-history',
                current: 'mdi-calendar-star',
                additional: 'mdi-shape-plus-outline',
            }[group?.key] || 'mdi-view-grid-outline'
        },
        toggleModuleGroup(group) {
            const groupKey = String(group?.key || '').trim()
            if (!groupKey) return

            this.activeModuleGroupKey = this.activeModuleGroupKey === groupKey ? '' : groupKey
        },
        manualModuleGroupActive(group) {
            return group?.key === this.activeManualModuleGroupKey
        },
        toggleManualModuleGroup(group) {
            const groupKey = String(group?.key || '').trim()
            if (!groupKey) return

            this.activeManualModuleGroupKey = this.activeManualModuleGroupKey === groupKey ? '' : groupKey
        },
        resetManualModuleCatalogDisclosure() {
            this.activeManualModuleGroupKey = ''
            this.manualPendingCourseKeys = []

            if (!this.moduleCoursesDialogReadOnly) return

            this.moduleCoursesDialogOpen = false
            this.moduleCourseDialogModule = null
            this.moduleCoursesDialogReadOnly = false
        },
        chooseScheduleCreationMode(scheduleCreationMode) {
            if (![AUTOMATIC_TIMETABLE, MANUAL_TIMETABLE].includes(scheduleCreationMode)) return
            if (this.scheduleCreationMode === scheduleCreationMode) return

            this.scheduleCreationMode = scheduleCreationMode
            void this.saveState()
        },
        openModuleCoursesDialog(module) {
            if (!module?.selection_key) return

            this.moduleCourseDialogModule = module
            this.moduleCoursesDialogReadOnly = false
            this.moduleCoursesDialogOpen = true
        },
        openManualModuleCoursesDialog(module) {
            if (!module?.selection_key) return

            this.manualPendingCourseKeys = []
            this.moduleCourseDialogModule = module
            this.moduleCoursesDialogReadOnly = true
            this.moduleCoursesDialogOpen = true
        },
        closeModuleCoursesDialog() {
            this.manualPendingCourseKeys = []
            this.moduleCoursesDialogOpen = false
        },
        toggleManualModuleCourse(course) {
            const courseKeys = normalizedCourseSelectionKeys(course)
            if (!courseKeys.length || !normalizedCourseTimetableEntries(course).length) return
            if (courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys)) return

            this.manualPendingCourseKeys = this.manualPendingCourseSelected(course)
                ? this.manualPendingCourseKeys.filter(courseKey => !courseKeys.includes(courseKey))
                : [...new Set([...this.manualPendingCourseKeys, ...courseKeys])]
        },
        cancelManualModuleCoursesDialog() {
            this.manualPendingCourseKeys = []
            this.moduleCoursesDialogOpen = false
        },
        async planManualModuleCourses() {
            const pendingCourseKeys = new Set(this.manualPendingCourseKeys)
            const plannedCourseKeys = (Array.isArray(this.moduleCourseDialogModule?.courses)
                ? this.moduleCourseDialogModule.courses
                : [])
                .filter(course => !courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys))
                .filter(course => normalizedCourseTimetableEntries(course).length > 0)
                .filter((course) => {
                    const courseKeys = normalizedCourseSelectionKeys(course)

                    return courseKeys.length > 0 && courseKeys.every(courseKey => pendingCourseKeys.has(courseKey))
                })
                .flatMap(course => normalizedCourseSelectionKeys(course))
            if (!plannedCourseKeys.length) return

            this.manualSelectedCourseKeys = [...new Set([
                ...this.manualSelectedCourseKeys,
                ...plannedCourseKeys,
            ])]
            this.manualPendingCourseKeys = []
            this.moduleCoursesDialogOpen = false
            await this.saveState()
        },
        async removeAdoptionModule(module) {
            const courseKeys = (Array.isArray(module?.courses) ? module.courses : [])
                .flatMap(course => normalizedCourseSelectionKeys(course))
            if (!courseKeys.length) return

            this.manualSelectedCourseKeys = this.manualSelectedCourseKeys
                .filter(courseKey => !courseKeys.includes(courseKey))
            this.manualPendingCourseKeys = []
            this.adoptionRemovedCourseKeys = [...new Set([
                ...this.adoptionRemovedCourseKeys,
                ...courseKeys,
            ])]
            await this.saveState()
        },
        async toggleDisplayedModuleCourse(course) {
            if (this.moduleCoursesDialogReadOnly) {
                this.toggleManualModuleCourse(course)

                return
            }

            await this.toggleModuleCourse(course)
        },
        async toggleModuleCourse(course) {
            if (this.moduleCoursesDialogReadOnly) return

            const courseKeys = normalizedCourseSelectionKeys(course)
            const moduleSelectionKey = String(this.moduleCourseDialogModule?.selection_key || '').trim()
            if (!courseKeys.length || !moduleSelectionKey) return

            const courseIsSelected = this.moduleCourseSelected(course)
            const nextSelectedCourseKeys = courseIsSelected
                ? this.selectedCourseKeys.filter(courseKey => !courseKeys.includes(courseKey))
                : [...new Set([...this.selectedCourseKeys, ...courseKeys])]
            const hasSelectedCourse = courseIsSelected
                ? this.moduleCourseDialogCourses
                    .filter(moduleCourse => moduleCourse !== course)
                    .some((moduleCourse) => {
                        const moduleCourseKeys = normalizedCourseSelectionKeys(moduleCourse)

                        return moduleCourseKeys.length > 0
                            && moduleCourseKeys.every(courseKey => nextSelectedCourseKeys.includes(courseKey))
                    })
                : true
            const nextSelectedModuleKeys = hasSelectedCourse
                ? [...new Set([...this.selectedModuleKeys, moduleSelectionKey])]
                : this.selectedModuleKeys.filter(key => key !== moduleSelectionKey)

            const limitViolation = moduleSelectionLimitViolation(this.moduleSelectionGroups, nextSelectedModuleKeys)
            if (limitViolation) {
                this.moduleSelectionLimitMessage = limitViolation

                return
            }

            this.selectedCourseKeys = nextSelectedCourseKeys
            this.selectedModuleKeys = nextSelectedModuleKeys
            this.moduleSelectionLimitMessage = ''
            await this.saveState()
        },
        async selectAllModuleCourses() {
            if (this.moduleCoursesDialogReadOnly) return

            const moduleSelectionKey = String(this.moduleCourseDialogModule?.selection_key || '').trim()
            const courseKeys = this.moduleCourseDialogCourses.flatMap(course => normalizedCourseSelectionKeys(course))
            if (!moduleSelectionKey || !courseKeys.length) return

            const nextSelectedModuleKeys = [...new Set([...this.selectedModuleKeys, moduleSelectionKey])]
            const limitViolation = moduleSelectionLimitViolation(this.moduleSelectionGroups, nextSelectedModuleKeys)
            if (limitViolation) {
                this.moduleSelectionLimitMessage = limitViolation

                return
            }

            this.selectedCourseKeys = [...new Set([...this.selectedCourseKeys, ...courseKeys])]
            this.selectedModuleKeys = nextSelectedModuleKeys
            this.moduleSelectionLimitMessage = ''
            await this.saveState()
        },
        async deselectAllModuleCourses() {
            if (this.moduleCoursesDialogReadOnly) return

            const moduleSelectionKey = String(this.moduleCourseDialogModule?.selection_key || '').trim()
            const courseKeys = this.moduleCourseDialogCourses.flatMap(course => normalizedCourseSelectionKeys(course))
            if (!moduleSelectionKey || !courseKeys.length) return

            this.selectedCourseKeys = this.selectedCourseKeys
                .filter(courseKey => !courseKeys.includes(courseKey))
            this.selectedModuleKeys = this.selectedModuleKeys
                .filter(selectionKey => selectionKey !== moduleSelectionKey)
            this.moduleSelectionLimitMessage = ''
            await this.saveState()
        },
        async selectAllModulesInGroup(group) {
            const moduleSelectionKeys = this.moduleSelectionKeysForGroup(group)
            const courseSelectionKeys = this.courseSelectionKeysForGroup(group)
            if (!moduleSelectionKeys.length || !courseSelectionKeys.length) return

            const nextSelectedModuleKeys = [...new Set([...this.selectedModuleKeys, ...moduleSelectionKeys])]
            const limitViolation = moduleSelectionLimitViolation(this.moduleSelectionGroups, nextSelectedModuleKeys)
            if (limitViolation) {
                this.moduleSelectionLimitMessage = limitViolation

                return
            }

            this.selectedModuleKeys = nextSelectedModuleKeys
            this.selectedCourseKeys = [...new Set([...this.selectedCourseKeys, ...courseSelectionKeys])]
            this.moduleSelectionLimitMessage = ''
            await this.saveState()
        },
        async deselectAllModulesInGroup(group) {
            const moduleSelectionKeys = this.moduleSelectionKeysForGroup(group)
            const courseSelectionKeys = this.courseSelectionKeysForGroup(group)
            if (!moduleSelectionKeys.length && !courseSelectionKeys.length) return

            this.selectedModuleKeys = this.selectedModuleKeys
                .filter(moduleSelectionKey => !moduleSelectionKeys.includes(moduleSelectionKey))
            this.selectedCourseKeys = this.selectedCourseKeys
                .filter(courseSelectionKey => !courseSelectionKeys.includes(courseSelectionKey))
            this.moduleSelectionLimitMessage = ''
            await this.saveState()
        },
        async removeSelectedModule(module) {
            const moduleSelectionKey = String(module?.selection_key || '').trim()
            const courseSelectionKeys = (Array.isArray(module?.courses) ? module.courses : [])
                .flatMap(course => normalizedCourseSelectionKeys(course))
            if (!moduleSelectionKey) return

            this.selectedModuleKeys = this.selectedModuleKeys
                .filter(selectedModuleKey => selectedModuleKey !== moduleSelectionKey)
            this.selectedCourseKeys = this.selectedCourseKeys
                .filter(courseSelectionKey => !courseSelectionKeys.includes(courseSelectionKey))
            this.moduleSelectionLimitMessage = ''
            await this.saveState()
        },
        async deselectAllSelectedModules() {
            if (!this.selectedModuleKeys.length && !this.selectedCourseKeys.length) return

            this.selectedModuleKeys = []
            this.selectedCourseKeys = []
            this.moduleSelectionLimitMessage = ''
            await this.saveState()
        },
        async updatePlanningSelection(key, value) {
            const field = this.planningSelectionFields.find(selectionField => selectionField.key === key)
            const allowedValues = (Array.isArray(field?.options) ? field.options : [])
                .map(option => option?.value)

            if (!allowedValues.includes(value)) return

            const nextValue = this.planningSelectionValues[key] === value ? null : value
            this.planningSelectionValues = {
                ...this.planningSelectionValues,
                [key]: nextValue,
            }
            this.moduleSelectionResetPending = true
            await this.saveState()
            this.studentSelectionDetailsCode = ''
            await this.loadSelectedStudentSelection()
            await this.saveState()
        },
        async hydrateSelectedStudentDetails() {
            if (!this.selectedStudent) return

            const hasSex = Object.prototype.hasOwnProperty.call(this.selectedStudent, 'sex')
            const hasReligion = Object.prototype.hasOwnProperty.call(this.selectedStudent, 'religion')
            const hasSemester = Object.prototype.hasOwnProperty.call(this.selectedStudent, 'semester')
            const hasInstructionType = Object.prototype.hasOwnProperty.call(this.selectedStudent, 'instructionType')
                || Object.prototype.hasOwnProperty.call(this.selectedStudent, 'instruction_type')
            const hasSchoolLevel = Object.prototype.hasOwnProperty.call(this.selectedStudent, 'schoolLevel')
                || Object.prototype.hasOwnProperty.call(this.selectedStudent, 'school_level')

            if (hasSex && hasReligion && hasSemester && hasInstructionType && hasSchoolLevel) return

            await this.loadStudents()

            const selectedStudentCode = String(
                this.selectedStudent.studentCode || this.selectedStudent.student_code || '',
            ).trim()
            const student = this.students.find(studentOption => String(
                studentOption.student_code || studentOption.studentCode || '',
            ).trim() === selectedStudentCode)

            if (!student) return

            this.selectedStudent = {
                ...this.selectedStudent,
                ...(!hasSex ? { sex: this.normalizedStudentSex(student) } : {}),
                ...(!hasReligion ? { religion: String(student.religion || '').trim() } : {}),
                ...(!hasSemester ? { semester: this.normalizedStudentSemester(student) } : {}),
                ...(!hasInstructionType
                    ? { instructionType: String(student.instruction_type || '').trim() }
                    : {}),
                ...(!hasSchoolLevel
                    ? { schoolLevel: String(student.school_level || '').trim() }
                    : {}),
            }
            await this.saveState()
        },
        normalizedStudentSex(student) {
            return String(student?.sex || '').trim().toLowerCase()
        },
        normalizedStudentSemester(student) {
            const semester = Number(student?.semester)

            return Number.isInteger(semester) && semester > 0 ? semester : null
        },
        studentDisplayLabel(student) {
            if (!student) return 'Studierenden auswählen'

            const className = String(student.className || student.class || '').trim()
            const fullName = [student.lastName || student.last_name, student.firstName || student.first_name]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join(' ')

            return [className, fullName]
                .filter(Boolean)
                .join(' · ') || String(student.studentCode || student.student_code || '').trim()
        },
        studentSearchText(student) {
            return [
                this.studentDisplayLabel(student),
                student?.email,
                student?.student_code,
            ]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join(' ')
                .toLocaleLowerCase('de-AT')
        },
        selectFirstFilteredStudent() {
            const [student] = this.filteredStudents

            if (student) void this.selectStudent(student)
        },
        async selectStudent(student) {
            this.planningMode = WITH_STUDENT
            this.emailCopyStatus = 'idle'
            this.studentInfoDialogOpen = false
            this.studyInfoDialogOpen = false
            this.resetSelectedStudentSelectionDetails()
            this.selectedStudent = {
                id: Number(student.id) || null,
                studentCode: String(student.student_code || ''),
                className: String(student.class || ''),
                lastName: String(student.last_name || ''),
                firstName: String(student.first_name || ''),
                email: String(student.email || ''),
                sex: this.normalizedStudentSex(student),
                religion: String(student.religion || '').trim(),
                instructionType: String(student.instruction_type || '').trim(),
                semester: this.normalizedStudentSemester(student),
                schoolLevel: String(student.school_level || '').trim(),
            }
            this.closeStudentDialog()
            await this.saveState()
            this.replaceRoutePlanningContext(normalizedPlanningContext(this.planningMode, this.selectedStudentCode))
            void this.loadSelectedStudentSelection()
        },
        async copySelectedStudentEmail() {
            const email = this.selectedStudentEmail
            const clipboard = globalThis.navigator?.clipboard

            if (!email || !clipboard?.writeText) {
                this.emailCopyStatus = 'failed'
                return
            }

            try {
                await clipboard.writeText(email)
                this.emailCopyStatus = 'copied'
            } catch {
                this.emailCopyStatus = 'failed'
            }
        },
        async saveState(planningContext = null) {
            const stateContext = planningContext
                || normalizedPlanningContext(this.planningMode, this.selectedStudentCode)

            if (!stateContext || !this.workspaceId) return

            const currentState = this.storedState && typeof this.storedState === 'object'
                ? this.storedState
                : {}
            const state = {
                ...currentState,
                adoptionReturnStep: normalizedTimetableAdoptionReturnStep(this.timetableAdoptionReturnStep),
                entrySelection: {
                    mode: this.planningMode,
                    student: this.planningMode === WITH_STUDENT ? this.selectedStudent : null,
                },
                planningSelection: {
                    mode: this.planningMode,
                    studentCode: this.planningMode === WITH_STUDENT ? this.selectedStudentCode : null,
                    values: this.planningSelectionValues,
                },
                moduleSelection: {
                    mode: this.planningMode,
                    studentCode: this.planningMode === WITH_STUDENT ? this.selectedStudentCode : null,
                    planningValues: this.planningSelectionValues,
                    scheduleCreationMode: this.scheduleCreationMode,
                    selectedKeys: this.selectedModuleKeys || [],
                    selectedCourseKeys: this.selectedCourseKeys || [],
                },
                creationOptions: {
                    filters: normalizedTimetableFilters(this.timetableFilters),
                },
                manualTimetableDraft: manualTimetableDraftState({
                    planningMode: this.planningMode,
                    studentCode: this.selectedStudentCode,
                    planningValues: this.planningSelectionValues,
                    returnStep: this.timetableAdoptionReturnStep,
                    routeSelection: this.timetableAdoptionRouteSelection,
                    calculationResult: this.timetableCalculationResult,
                    selectedTimetable: this.selectedTimetableResult,
                    selectedTimetableIndex: this.timetableSelectedIndex,
                    selectedCourseKeys: this.manualSelectedCourseKeys,
                    removedCourseKeys: this.adoptionRemovedCourseKeys,
                }),
            }

            this.pendingStateSaveCount = Number(this.pendingStateSaveCount || 0) + 1
            this.isSavingState = true
            const precedingSave = this.stateSaveQueue || Promise.resolve()
            const queuedSave = precedingSave
                .catch(() => undefined)
                .then(async () => {
                    this.stateSaveFailed = false

                    try {
                        const response = await axios.put(updateTimetableV3State.url(), {
                            context: {
                                workspace_id: this.workspaceId,
                                ...stateContext,
                            },
                            state,
                        })
                        this.storedState = response.data?.data?.state ?? state
                    } catch {
                        this.stateSaveFailed = true
                    }
                })

            this.stateSaveQueue = queuedSave

            try {
                await queuedSave
            } finally {
                this.pendingStateSaveCount = Math.max(0, Number(this.pendingStateSaveCount || 0) - 1)
                this.isSavingState = this.pendingStateSaveCount > 0
            }
        },
    },
}
</script>

<style scoped>
.timetable-v3 {
    display: flex;
    flex-direction: column;
    min-height: clamp(560px, calc(100vh - 220px), 760px);
    border: 1px solid rgba(30, 36, 51, 0.1);
    background: linear-gradient(145deg, #ffffff 0%, #f6f7ff 100%);
}

.timetable-v3__step {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
}

.timetable-v3__page-header {
    display: flex;
    gap: 24px;
    align-items: flex-start;
    justify-content: space-between;
}

.timetable-v3__page-number {
    flex: 0 0 auto;
    margin-left: auto;
}

.timetable-v3__initializing {
    display: grid;
    flex: 1 1 auto;
    gap: 14px;
    place-items: center;
    place-content: center;
    min-height: 360px;
    color: #475569;
    font-weight: 700;
}

.timetable-v3__choice-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.timetable-v3__choice {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 16px;
    align-items: center;
    width: 100%;
    min-height: 126px;
    padding: 22px;
    color: #1e2433;
    text-align: left;
    cursor: pointer;
    background: #fff;
    border: 2px solid #e2e5ed;
    border-radius: 18px;
    transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.timetable-v3__choice:hover:not(:disabled),
.timetable-v3__choice:focus-visible {
    border-color: #4f46e5;
    box-shadow: 0 12px 30px rgba(79, 70, 229, 0.14);
    outline: none;
    transform: translateY(-2px);
}

.timetable-v3__choice:disabled {
    cursor: wait;
    opacity: 0.62;
}

.timetable-v3__choice--selected {
    color: #3730a3;
    background: #eef2ff;
    border-color: #4f46e5;
}

.timetable-v3__choice-icon {
    display: inline-grid;
    place-items: center;
    width: 58px;
    height: 58px;
    color: #4338ca;
    background: #eef2ff;
    border-radius: 16px;
}

.timetable-v3__choice-copy {
    display: grid;
    gap: 5px;
}

.timetable-v3__choice-title {
    font-size: 1.08rem;
    font-weight: 800;
}

.timetable-v3__choice-description {
    color: #667085;
    font-size: 0.9rem;
}

.timetable-v3__selection-summary {
    position: relative;
    isolation: isolate;
    display: flex;
    gap: 14px;
    align-items: center;
    padding: 18px 20px;
    overflow: hidden;
    background: linear-gradient(135deg, #eef2ff 0%, #ffffff 52%, #f5f3ff 100%);
    border: 1px solid rgba(79, 70, 229, 0.34);
    border-radius: 16px;
    box-shadow:
        0 14px 34px rgba(79, 70, 229, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.timetable-v3__selection-summary::before {
    position: absolute;
    top: -90px;
    right: -45px;
    z-index: -1;
    width: 220px;
    height: 220px;
    pointer-events: none;
    content: '';
    background: radial-gradient(circle, rgba(129, 140, 248, 0.3) 0%, rgba(255, 255, 255, 0) 68%);
}

.timetable-v3__selection-icon {
    display: inline-grid;
    flex: 0 0 auto;
    place-items: center;
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid rgba(79, 70, 229, 0.22);
    border-radius: 14px;
    box-shadow: 0 7px 18px rgba(79, 70, 229, 0.16);
}

.timetable-v3__selection-summary-copy {
    flex: 1 1 auto;
    min-width: 0;
}

.timetable-v3__student-data-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 9px;
}

.timetable-v3__student-data-field {
    display: inline-flex;
    gap: 6px;
    align-items: baseline;
    max-width: 100%;
    padding: 5px 8px;
    overflow-wrap: anywhere;
    color: #344054;
    background: rgba(255, 255, 255, 0.76);
    border: 1px solid rgba(79, 70, 229, 0.18);
    border-radius: 8px;
}

.timetable-v3__student-data-label {
    color: #667085;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.timetable-v3__student-data-field--invalid {
    color: #b42318;
    background: #fff4f2;
    border-color: #d92d20;
    box-shadow: 0 0 0 1px rgba(217, 45, 32, 0.2);
}

.timetable-v3__student-data-field--invalid .timetable-v3__student-data-label {
    color: #b42318;
}

button.timetable-v3__student-data-field {
    font: inherit;
    text-align: left;
    cursor: pointer;
}

button.timetable-v3__student-data-field:hover,
button.timetable-v3__student-data-field:focus-visible {
    background: #fee4e2;
    outline: 2px solid rgba(217, 45, 32, 0.35);
    outline-offset: 2px;
}

.timetable-v3__planning-selection {
    padding: 16px;
    background: rgba(255, 255, 255, 0.84);
    border: 1px solid #dfe3f0;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(30, 36, 51, 0.08);
}

.timetable-v3__planning-selection-heading {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 14px;
}

.timetable-v3__planning-selection-icon {
    display: inline-grid;
    flex: 0 0 auto;
    place-items: center;
    width: 46px;
    height: 46px;
    background: #eef2ff;
    border-radius: 13px;
}

.timetable-v3__planning-selection-title {
    color: #1e2433;
    font-size: 1.12rem;
    font-weight: 800;
    line-height: 1.3;
}

.timetable-v3__planning-selection-description {
    margin: 2px 0 0;
    color: #667085;
    font-size: 0.9rem;
}

.timetable-v3__planning-selection-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.timetable-v3__planning-selection-group {
    min-width: 0;
    padding: 12px;
    background: #fbfcff;
    border: 1px solid #dfe3f0;
    border-radius: 14px;
}

.timetable-v3__planning-selection-label {
    padding-inline: 7px;
    color: #344054;
    font-size: 0.86rem;
    font-weight: 800;
    letter-spacing: 0.035em;
}

.timetable-v3__planning-selection-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 3px;
}

.timetable-v3__planning-selection-option {
    display: flex;
    flex: 1 1 170px;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
    min-height: 42px;
    padding: 8px 11px;
    color: #344054;
    text-align: left;
    cursor: pointer;
    background: #fff;
    border: 1px solid #d0d5dd;
    border-radius: 11px;
    transition: color 150ms ease, background 150ms ease, border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
}

.timetable-v3__planning-selection-option:hover:not(:disabled),
.timetable-v3__planning-selection-option:focus-visible {
    color: #3730a3;
    border-color: #818cf8;
    box-shadow: 0 7px 18px rgba(79, 70, 229, 0.12);
    outline: none;
    transform: translateY(-1px);
}

.timetable-v3__planning-selection-option--selected {
    color: #3730a3;
    font-weight: 750;
    background: linear-gradient(135deg, #eef2ff 0%, #f7f7ff 100%);
    border-color: #6366f1;
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.14);
}

.timetable-v3__planning-selection-option:disabled {
    cursor: wait;
    opacity: 0.68;
}

.timetable-v3__planning-selection-feedback {
    display: flex;
    gap: 10px;
    align-items: center;
    min-height: 56px;
    color: #475467;
    font-weight: 650;
}

.timetable-v3__planning-selection-feedback--error {
    color: #b42318;
}

.timetable-v3__compact-planning-card {
    padding: 15px 18px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid #dfe3f0;
    border-radius: 16px;
    box-shadow: 0 8px 22px rgba(30, 36, 51, 0.07);
}

.timetable-v3__compact-planning-heading {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 10px;
}

.timetable-v3__compact-planning-title {
    margin: 0;
    color: #1e2433;
    font-size: 1rem;
    font-weight: 800;
}

.timetable-v3__compact-planning-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
    gap: 8px;
}

.timetable-v3__compact-planning-item {
    display: grid;
    gap: 2px;
    min-width: 0;
    padding: 9px 11px;
    background: #f8f9ff;
    border: 1px solid #e4e7f2;
    border-radius: 10px;
}

.timetable-v3__compact-planning-label {
    color: #667085;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.045em;
    text-transform: uppercase;
}

.timetable-v3__compact-planning-value {
    color: #1e1b4b;
    overflow-wrap: anywhere;
    font-size: 0.96rem;
    font-weight: 750;
}

.timetable-v3__compact-planning-feedback {
    display: flex;
    gap: 9px;
    align-items: center;
    min-height: 48px;
    color: #475467;
    font-size: 0.9rem;
    font-weight: 650;
}

.timetable-v3__compact-planning-feedback--error {
    color: #b42318;
}

.timetable-v3__module-workspace {
    padding: 18px;
    background:
        radial-gradient(circle at 96% 0%, rgba(99, 102, 241, 0.12), transparent 30%),
        linear-gradient(155deg, #ffffff 0%, #fafbff 52%, #f8fafc 100%);
    border: 1px solid rgba(203, 213, 225, 0.88);
    border-radius: 20px;
    box-shadow: 0 16px 42px rgba(30, 41, 59, 0.09);
}

.timetable-v3__module-workspace-heading {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    justify-content: space-between;
}

.timetable-v3__module-workspace-eyebrow {
    margin-bottom: 5px;
    color: #6366f1;
    font-size: 0.74rem;
    font-weight: 850;
    letter-spacing: 0.11em;
    text-transform: uppercase;
}

.timetable-v3__module-workspace-title {
    color: #15173b;
    font-size: clamp(1.28rem, 2.2vw, 1.7rem);
    font-weight: 850;
    line-height: 1.2;
}

.timetable-v3__module-selected-total {
    flex: 0 0 auto;
    padding: 7px 11px;
    color: #4338ca;
    background: rgba(238, 242, 255, 0.92);
    border: 1px solid #c7d2fe;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 750;
    white-space: nowrap;
}

.timetable-v3__module-selected-total strong {
    margin-right: 3px;
    font-size: 0.94rem;
}

.timetable-v3__schedule-mode-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 18px;
    transition: grid-template-columns 170ms ease;
}

.timetable-v3__schedule-mode-options--automatic-selected {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
}

.timetable-v3__schedule-mode-options--manual-selected {
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
}

.timetable-v3__schedule-mode-card {
    --schedule-mode-accent: #4338ca;
    --schedule-mode-accent-rgb: 67, 56, 202;
    --schedule-mode-soft: #eef2ff;
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 14px 16px;
    align-items: center;
    align-content: start;
    min-width: 0;
    min-height: 220px;
    padding: 22px;
    overflow: hidden;
    font: inherit;
    color: #344054;
    text-align: left;
    background: linear-gradient(145deg, var(--schedule-mode-soft), #fff 68%);
    border: 2px solid rgba(var(--schedule-mode-accent-rgb), 0.3);
    border-radius: 18px;
    cursor: pointer;
    transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.timetable-v3__schedule-mode-card::before {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    height: 6px;
    content: '';
    background: var(--schedule-mode-accent);
}

.timetable-v3__schedule-mode-input {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    opacity: 0;
}

.timetable-v3__schedule-mode-card--manual {
    --schedule-mode-accent: #c2410c;
    --schedule-mode-accent-rgb: 194, 65, 12;
    --schedule-mode-soft: #fff;
    background: #fff;
    border-color: #e2e8f0;
}

.timetable-v3__schedule-mode-card--manual::before {
    height: 4px;
}

.timetable-v3__schedule-mode-card--options {
    --schedule-mode-accent: #0f766e;
    --schedule-mode-accent-rgb: 15, 118, 110;
    --schedule-mode-soft: #f0fdfa;
}

.timetable-v3__schedule-mode-card:hover,
.timetable-v3__schedule-mode-card:focus-within {
    background: #fff;
    border-color: var(--schedule-mode-accent);
    outline: 3px solid rgba(var(--schedule-mode-accent-rgb), 0.16);
    outline-offset: 2px;
    box-shadow: 0 14px 32px rgba(var(--schedule-mode-accent-rgb), 0.18);
    transform: translateY(-2px);
}

.timetable-v3__schedule-mode-card--selected {
    color: var(--schedule-mode-accent);
    background: linear-gradient(
        135deg,
        rgba(var(--schedule-mode-accent-rgb), 0.2),
        var(--schedule-mode-soft) 62%,
        #fff
    );
    border-color: var(--schedule-mode-accent);
    box-shadow:
        inset 0 0 0 2px rgba(var(--schedule-mode-accent-rgb), 0.22),
        0 15px 34px rgba(var(--schedule-mode-accent-rgb), 0.22);
}

.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-title {
    color: var(--schedule-mode-accent);
}

.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-icon {
    color: #fff;
    background: var(--schedule-mode-accent);
    border-color: var(--schedule-mode-accent);
    box-shadow: 0 6px 16px rgba(var(--schedule-mode-accent-rgb), 0.24);
}

.timetable-v3__schedule-mode-card--manual.timetable-v3__schedule-mode-card--selected {
    background: #fff;
    border-color: var(--schedule-mode-accent);
    box-shadow:
        inset 0 0 0 1px rgba(var(--schedule-mode-accent-rgb), 0.12),
        0 12px 28px rgba(var(--schedule-mode-accent-rgb), 0.14);
}

.timetable-v3__creation-summary-cards {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 16px;
}

.timetable-v3__adoption-cards {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.timetable-v3__adoption-card {
    min-height: 220px;
    cursor: default;
    animation: none;
    transition: none;
}

.timetable-v3__adoption-heading {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    min-width: 0;
}

.timetable-v3__adoption-pdf-button {
    flex: 0 0 auto;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.timetable-v3__adoption-card:hover,
.timetable-v3__adoption-card:focus-within {
    outline: none;
    outline-offset: 0;
    transform: none;
    transition: none;
}

.timetable-v3__adoption-card--manual:hover,
.timetable-v3__adoption-card--manual:focus-within {
    background: #fff;
    border-color: var(--schedule-mode-accent);
    box-shadow:
        inset 0 0 0 1px rgba(var(--schedule-mode-accent-rgb), 0.12),
        0 12px 28px rgba(var(--schedule-mode-accent-rgb), 0.14);
}

.timetable-v3__adoption-timetable {
    margin-top: 16px;
    min-width: 0;
}

.timetable-v3__creation-summary-card {
    min-height: 260px;
    cursor: default;
    animation: none;
    transition: none;
}

.timetable-v3__creation-summary-card:hover,
.timetable-v3__creation-summary-card:focus-within {
    outline: none;
    outline-offset: 0;
    transform: none;
    transition: none;
}

.timetable-v3__creation-summary-card.timetable-v3__schedule-mode-card--automatic:hover,
.timetable-v3__creation-summary-card.timetable-v3__schedule-mode-card--automatic:focus-within {
    background: linear-gradient(
        135deg,
        rgba(var(--schedule-mode-accent-rgb), 0.2),
        var(--schedule-mode-soft) 62%,
        #fff
    );
    border-color: var(--schedule-mode-accent);
    box-shadow:
        inset 0 0 0 2px rgba(var(--schedule-mode-accent-rgb), 0.22),
        0 15px 34px rgba(var(--schedule-mode-accent-rgb), 0.22);
}

.timetable-v3__creation-summary-card--automatic {
    grid-column: 1;
    grid-row: 1 / span 2;
}

.timetable-v3__creation-summary-card--automatic .timetable-v3__calculation-content {
    grid-column: 1 / -1;
    min-height: 0;
    padding: 8px 0 0;
}

.timetable-v3__creation-summary-card--manual {
    grid-column: 2;
    grid-row: 1;
    grid-template-rows: auto 1fr;
}

.timetable-v3__creation-summary-card--options {
    grid-column: 2;
    grid-row: 2;
}

.timetable-v3__filter-options {
    display: grid;
    grid-column: 1 / -1;
    gap: 16px;
    align-self: end;
}

.timetable-v3__filter-option {
    display: grid;
    grid-column: 1 / -1;
    gap: 8px;
    align-self: end;
}

.timetable-v3__filter-option-label {
    color: #115e59;
    font-size: 0.82rem;
    font-weight: 850;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.timetable-v3__filter-option-toggle {
    display: flex;
    align-items: stretch;
    width: 100%;
    height: auto;
    min-height: 58px;
}

.timetable-v3__filter-option-toggle--free-days {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
    overflow: visible;
}

.timetable-v3__filter-option-toggle :deep(.v-btn) {
    flex: 1 1 0;
    height: auto;
    min-width: 0;
    min-height: 58px;
    padding-block: 7px;
    font-weight: 800;
    text-transform: none;
    white-space: normal;
}

.timetable-v3__filter-option-toggle--free-days :deep(.v-btn) {
    border: 1px solid currentColor;
    border-radius: 8px !important;
}

.timetable-v3__filter-option-button-content {
    display: grid;
    gap: 3px;
    min-width: 0;
    line-height: 1.15;
    text-align: left;
}

.timetable-v3__filter-option-count {
    display: block;
    min-width: 0;
    font-size: 0.7rem;
    font-weight: 750;
    line-height: 1.2;
    opacity: 0.82;
    white-space: nowrap;
}

.timetable-v3__filter-loading {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 9px 11px;
    color: #115e59;
    background: rgba(204, 251, 241, 0.72);
    border: 1px solid rgba(13, 148, 136, 0.3);
    border-radius: 10px;
}

.timetable-v3__filter-loading span {
    font-size: 0.82rem;
    font-weight: 750;
    line-height: 1.35;
}

.timetable-v3__creation-summary-card--options:hover,
.timetable-v3__creation-summary-card--options:focus-within {
    background: linear-gradient(145deg, var(--schedule-mode-soft), #fff 68%);
    border-color: rgba(var(--schedule-mode-accent-rgb), 0.3);
    box-shadow: none;
}

.timetable-v3__manual-timetable-button {
    grid-column: 1 / -1;
    align-self: end;
    font-weight: 850;
}

.timetable-v3__calculation-output {
    grid-column: 1 / -1;
}

.timetable-v3__calculation-content {
    display: grid;
    gap: 24px;
    min-height: 250px;
    padding: clamp(22px, 4vw, 34px);
}

.timetable-v3__calculation-heading {
    display: flex;
    gap: 12px;
    align-items: center;
    color: #115e59;
}

.timetable-v3__calculation-heading h3 {
    font-size: clamp(1.2rem, 2.3vw, 1.55rem);
    font-weight: 850;
}

.timetable-v3__calculation-icon {
    display: inline-grid;
    flex: 0 0 auto;
    place-items: center;
    width: 48px;
    height: 48px;
    color: #fff;
    background: #0f766e;
    border-radius: 14px;
    box-shadow: 0 7px 18px rgba(15, 118, 110, 0.22);
}

.timetable-v3__calculation-state {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    min-height: 120px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(15, 118, 110, 0.16);
    border-radius: 15px;
}

.timetable-v3__calculation-progress-copy,
.timetable-v3__calculation-state--error > div {
    display: grid;
    flex: 1 1 280px;
    gap: 5px;
}

.timetable-v3__calculation-state strong {
    color: #134e4a;
    font-size: 1rem;
}

.timetable-v3__calculation-state span {
    color: #64748b;
    font-size: 0.9rem;
}

.timetable-v3__calculation-state--loading {
    align-items: stretch;
}

.timetable-v3__calculation-progress-copy {
    align-content: center;
}

.timetable-v3__calculation-led-progress {
    display: grid;
    flex: 1 1 100%;
    grid-template-columns: repeat(20, minmax(4px, 1fr));
    gap: clamp(3px, 0.7vw, 7px) !important;
    width: 100%;
    padding: 13px;
    background: #082f2d;
    border: 1px solid rgba(13, 148, 136, 0.42);
    border-radius: 11px;
    box-shadow: inset 0 3px 12px rgba(0, 0, 0, 0.34);
}

.timetable-v3__calculation-led-segment {
    min-width: 0;
    height: 28px;
    background: linear-gradient(180deg, #284b49, #173b39);
    border: 1px solid rgba(153, 246, 228, 0.14);
    border-radius: 4px;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.4);
    transition: background-color 140ms ease, border-color 140ms ease, box-shadow 140ms ease;
}

.timetable-v3__calculation-led-segment--active {
    background: linear-gradient(180deg, #86efac, #16a34a 72%, #15803d);
    border-color: #bbf7d0;
    box-shadow:
        0 0 7px rgba(34, 197, 94, 0.9),
        0 0 15px rgba(34, 197, 94, 0.62),
        inset 0 1px 3px rgba(255, 255, 255, 0.7);
}

.timetable-v3__calculation-state--error {
    background: rgba(254, 242, 242, 0.82);
    border-color: rgba(220, 38, 38, 0.2);
}

.timetable-v3__calculation-state--error strong {
    color: #991b1b;
}

.timetable-v3__calculation-result {
    display: grid;
    gap: 18px;
}

.timetable-v3__calculation-result-count {
    display: flex;
    gap: 14px;
    align-items: center;
}

.timetable-v3__calculation-result-count > div {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: baseline;
}

.timetable-v3__calculation-result-count strong {
    color: #0f766e;
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 900;
    line-height: 1;
}

.timetable-v3__calculation-result-count span {
    color: #334155;
    font-size: 1rem;
    font-weight: 750;
}

.timetable-v3__calculation-truncated-notice {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    max-width: 860px;
    padding: 13px 15px;
    color: #92400e;
    font-size: 0.92rem;
    line-height: 1.5;
    background: #fffbeb;
    border: 1px solid rgba(217, 119, 6, 0.35);
    border-radius: 11px;
}

.timetable-v3__calculation-truncated-notice strong {
    color: #78350f;
}

.timetable-v3__calculation-counts {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.timetable-v3__calculation-counts span {
    padding: 8px 11px;
    color: #475569;
    font-size: 0.82rem;
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid rgba(15, 118, 110, 0.16);
    border-radius: 10px;
}

.timetable-v3__calculation-counts strong {
    margin-right: 4px;
    color: #0f766e;
}

.timetable-v3__solution-plan {
    display: grid;
    gap: 18px;
    margin-top: 4px;
    padding: clamp(18px, 3vw, 24px);
    background: linear-gradient(145deg, rgba(255, 251, 235, 0.96), rgba(255, 255, 255, 0.98));
    border: 1px solid rgba(217, 119, 6, 0.28);
    border-radius: 16px;
}

.timetable-v3__solution-plan-heading {
    display: flex;
    gap: 13px;
    align-items: flex-start;
}

.timetable-v3__solution-plan-icon {
    display: inline-grid;
    flex: 0 0 auto;
    place-items: center;
    width: 44px;
    height: 44px;
    color: #b45309;
    background: #fef3c7;
    border: 1px solid rgba(217, 119, 6, 0.24);
    border-radius: 13px;
}

.timetable-v3__solution-plan-heading h4 {
    margin: 0;
    color: #78350f;
    font-size: 1.12rem;
    font-weight: 900;
}

.timetable-v3__solution-plan-heading p {
    max-width: 850px;
    margin: 4px 0 0;
    color: #6b4f2b;
    font-size: 0.9rem;
    line-height: 1.55;
}

.timetable-v3__solution-plan-scenarios {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(285px, 1fr));
    gap: 10px;
}

.timetable-v3__solution-plan-scenario-item {
    min-width: 0;
}

.timetable-v3__solution-plan-scenario {
    display: flex;
    gap: 14px;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    height: 100%;
    min-width: 0;
    padding: 13px 14px;
    color: inherit;
    text-align: left;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(180, 83, 9, 0.18);
    border-radius: 13px;
    box-shadow: 0 5px 14px rgba(120, 53, 15, 0.05);
}

.timetable-v3__solution-plan-scenario--actionable {
    cursor: pointer;
    transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.timetable-v3__solution-plan-scenario--actionable:hover:not(:disabled),
.timetable-v3__solution-plan-scenario--actionable:focus-visible {
    border-color: rgba(5, 150, 105, 0.58);
    box-shadow: 0 10px 22px rgba(5, 150, 105, 0.15);
    outline: 3px solid rgba(16, 185, 129, 0.2);
    outline-offset: 2px;
    transform: translateY(-2px);
}

.timetable-v3__solution-plan-scenario:disabled {
    cursor: default;
    opacity: 1;
}

.timetable-v3__solution-plan-module {
    display: flex;
    gap: 10px;
    align-items: center;
    min-width: 0;
}

.timetable-v3__solution-plan-module-icon {
    display: inline-grid;
    flex: 0 0 auto;
    place-items: center;
    width: 38px;
    height: 38px;
    color: #b45309;
    background: #fffbeb;
    border: 1px solid rgba(217, 119, 6, 0.2);
    border-radius: 11px;
}

.timetable-v3__solution-plan-module > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.timetable-v3__solution-plan-module span {
    color: #78716c;
    font-size: 0.7rem;
    font-weight: 750;
}

.timetable-v3__solution-plan-module strong {
    overflow-wrap: anywhere;
    color: #292524;
    font-size: 0.92rem;
    font-weight: 900;
}

.timetable-v3__solution-plan-module small {
    color: #57534e;
    font-size: 0.82rem;
    font-weight: 700;
}

.timetable-v3__solution-plan-module .timetable-v3__solution-plan-action-label {
    margin-top: 3px;
    color: #047857;
    font-size: 0.69rem;
    font-weight: 850;
}

.timetable-v3__solution-plan-result {
    display: grid;
    flex: 0 0 auto;
    justify-items: end;
    min-width: 112px;
    color: #0f766e;
}

.timetable-v3__solution-plan-result > strong {
    font-size: 1.5rem;
    font-weight: 950;
    line-height: 1;
}

.timetable-v3__solution-plan-result > span {
    margin-top: 3px;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 750;
}

.timetable-v3__solution-plan-result--limited {
    display: flex;
    gap: 7px;
    align-items: center;
    justify-items: initial;
    max-width: 170px;
    color: #b45309;
}

.timetable-v3__solution-plan-result--empty {
    display: flex;
    gap: 7px;
    align-items: center;
    justify-items: initial;
    max-width: 175px;
    color: #b45309;
}

.timetable-v3__solution-plan-result--empty > strong {
    color: #92400e;
    font-size: 0.78rem;
    line-height: 1.3;
}

.timetable-v3__solution-plan-result--limited > div {
    display: grid;
    gap: 2px;
}

.timetable-v3__solution-plan-result--limited > div > strong {
    color: #92400e;
    font-size: 0.78rem;
    line-height: 1.25;
}

.timetable-v3__solution-plan-result--limited > div > span {
    margin: 0;
    color: #78716c;
    font-size: 0.68rem;
    line-height: 1.3;
}

.timetable-v3__solution-plan-zero-note {
    margin: 0;
    padding: 10px 12px;
    color: #92400e;
    background: rgba(254, 243, 199, 0.7);
    border-left: 3px solid #d97706;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 800;
}

.timetable-v3__solution-plan-note {
    display: flex;
    gap: 7px;
    align-items: center;
    margin: 0;
    color: #6b4f2b;
    font-size: 0.8rem;
    font-weight: 700;
}

.timetable-v3__schedule-mode-icon {
    display: inline-grid;
    place-items: center;
    width: 58px;
    height: 58px;
    color: var(--schedule-mode-accent);
    background: var(--schedule-mode-soft);
    border: 1px solid rgba(var(--schedule-mode-accent-rgb), 0.2);
    border-radius: 15px;
}

.timetable-v3__schedule-mode-copy {
    display: grid;
    gap: 5px;
    min-width: 0;
}

.timetable-v3__schedule-mode-options .timetable-v3__schedule-mode-copy {
    grid-template-rows: 27px auto auto;
}

.timetable-v3__schedule-mode-options .timetable-v3__schedule-mode-kickers {
    grid-row: 1;
}

.timetable-v3__schedule-mode-options .timetable-v3__schedule-mode-title {
    grid-row: 2;
}

.timetable-v3__schedule-mode-options .timetable-v3__schedule-mode-description {
    grid-row: 3;
}

.timetable-v3__schedule-mode-kickers {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
}

.timetable-v3__schedule-mode-label {
    width: fit-content;
    padding: 3px 8px;
    color: var(--schedule-mode-accent);
    background: rgba(var(--schedule-mode-accent-rgb), 0.1);
    border: 1px solid rgba(var(--schedule-mode-accent-rgb), 0.22);
    border-radius: 999px;
    font-size: 0.69rem;
    font-weight: 900;
    letter-spacing: 0.09em;
    line-height: 1.35;
    text-transform: uppercase;
}

.timetable-v3__schedule-mode-recommendation {
    display: inline-flex;
    gap: 5px;
    align-items: center;
    width: fit-content;
    padding: 4px 9px;
    color: #713f12;
    white-space: nowrap;
    background: linear-gradient(135deg, #fef08a, #facc15);
    border: 1px solid rgba(161, 98, 7, 0.4);
    border-radius: 999px;
    box-shadow: 0 5px 14px rgba(161, 98, 7, 0.22);
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    line-height: 1.35;
    text-transform: uppercase;
}

.timetable-v3__schedule-mode-title {
    color: #1e293b;
    font-size: clamp(1.05rem, 1.6vw, 1.3rem);
    font-weight: 850;
    line-height: 1.2;
}

.timetable-v3__schedule-mode-description {
    max-width: 48ch;
    color: #475569;
    font-size: 0.9rem;
    line-height: 1.5;
}

.timetable-v3__schedule-mode-status {
    display: inline-flex;
    grid-column: 1 / -1;
    gap: 8px;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 8px 12px;
    color: var(--schedule-mode-accent);
    background: rgba(255, 255, 255, 0.76);
    border: 1px solid rgba(var(--schedule-mode-accent-rgb), 0.36);
    border-radius: 11px;
    font-size: 0.86rem;
    font-weight: 850;
}

.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-status {
    color: #fff;
    background: var(--schedule-mode-accent);
    border-color: var(--schedule-mode-accent);
    box-shadow: 0 6px 16px rgba(var(--schedule-mode-accent-rgb), 0.22);
}

.timetable-v3__schedule-mode-selected-modules {
    grid-column: 1 / -1;
    margin-top: 2px;
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(var(--schedule-mode-accent-rgb), 0.2);
}

.timetable-v3__schedule-create-action {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    padding-top: 8px;
    border-top: 1px solid rgba(var(--schedule-mode-accent-rgb), 0.14);
}

.timetable-v3__schedule-create-button {
    position: relative;
    z-index: 1;
    min-width: min(100%, 250px);
    color: #fff;
    background: linear-gradient(135deg, #4338ca, #6366f1);
    box-shadow: 0 12px 26px rgba(67, 56, 202, 0.34);
    font-size: 0.94rem;
    font-weight: 900;
    letter-spacing: 0.015em;
    transition: transform 160ms ease, box-shadow 160ms ease, filter 160ms ease;
}

.timetable-v3__schedule-create-button:hover,
.timetable-v3__schedule-create-button:focus-visible {
    box-shadow: 0 16px 32px rgba(67, 56, 202, 0.42);
    filter: saturate(1.12) brightness(1.04);
    transform: translateY(-2px) scale(1.01);
}

.timetable-v3__selected-modules {
    display: grid;
    gap: 8px;
    padding: 11px 13px;
    background: rgba(248, 250, 252, 0.92);
    border: 1px solid #dbe3ef;
    border-radius: 12px;
}

.timetable-v3__selected-modules-heading {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
    justify-content: space-between;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 850;
}

.timetable-v3__selected-modules-heading-label {
    display: inline-flex;
    gap: 7px;
    align-items: center;
}

.timetable-v3__selected-modules-summary {
    padding: 3px 8px;
    color: var(--schedule-mode-accent);
    white-space: nowrap;
    background: rgba(var(--schedule-mode-accent-rgb), 0.1);
    border: 1px solid rgba(var(--schedule-mode-accent-rgb), 0.2);
    border-radius: 999px;
}

.timetable-v3__module-selection-limit-hint {
    display: flex;
    gap: 6px;
    align-items: center;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 700;
}

.timetable-v3__module-selection-limit-alert {
    cursor: default;
}

.timetable-v3__selected-modules-list {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.timetable-v3__selected-module-chip :deep(.v-chip__close) {
    color: #dc2626;
    opacity: 1;
}

.timetable-v3__selected-modules-empty {
    color: #64748b;
    font-size: 0.78rem;
}

.timetable-v3__module-group-cards {
    display: flex;
    gap: 9px;
    margin-top: 13px;
}

.timetable-v3__main-module-heading {
    display: flex;
    gap: 11px;
    align-items: center;
    margin-top: 14px;
    padding: 12px 14px;
    color: #1e3a8a;
    background: linear-gradient(135deg, #eff6ff, #eef2ff);
    border: 1px solid #bfdbfe;
    border-radius: 14px;
}

.timetable-v3__manual-module-catalog-headings {
    display: grid;
    gap: 12px;
}

.timetable-v3__manual-module-catalog-headings--with-student {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.timetable-v3__manual-module-catalog-heading-button {
    width: 100%;
    font: inherit;
    text-align: left;
    cursor: pointer;
    appearance: none;
    transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
}

.timetable-v3__manual-module-catalog-heading-button:hover:not(:disabled),
.timetable-v3__manual-module-catalog-heading-button:focus-visible {
    border-color: #60a5fa;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.14);
    outline: none;
    transform: translateY(-1px);
}

.timetable-v3__manual-module-catalog-heading--selected {
    color: #1e3a8a;
    background: linear-gradient(135deg, #dbeafe, #e0e7ff);
    border: 2px solid #60a5fa;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1), 0 6px 16px rgba(37, 99, 235, 0.12);
}

.timetable-v3__manual-module-catalog-heading--selected .timetable-v3__main-module-heading-icon {
    color: #1d4ed8;
    background: #fff;
    border-color: #93c5fd;
    box-shadow: 0 3px 8px rgba(37, 99, 235, 0.1);
}

.timetable-v3__manual-module-catalog-heading--selected h4 {
    color: #1e3a8a;
}

.timetable-v3__manual-module-catalog-heading--selected p {
    color: #475569;
}

.timetable-v3__manual-module-catalog-heading-button:focus-visible {
    outline: 3px solid #0f172a;
    outline-offset: 3px;
}

.timetable-v3__manual-module-catalog-heading-button:disabled {
    cursor: default;
    opacity: 1;
}

.timetable-v3__main-module-heading-icon {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    color: #2563eb;
    background: #fff;
    border: 1px solid #bfdbfe;
    border-radius: 11px;
}

.timetable-v3__main-module-heading h4 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 850;
}

.timetable-v3__main-module-heading p {
    margin: 2px 0 0;
    color: #475569;
    font-size: 0.74rem;
    line-height: 1.4;
}

.timetable-v3__module-group-cards--main {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
}

.timetable-v3__module-group-cards--main .timetable-v3__module-group-card {
    flex: none;
}

.timetable-v3__module-group-cards--main .timetable-v3__module-group-card--manual-main-active {
    grid-column: span 2;
}

.timetable-v3__module-group-card {
    --module-group-accent: #64748b;
    --module-group-accent-rgb: 100, 116, 139;
    --module-group-soft: #f1f5f9;
    position: relative;
    display: flex;
    flex: 1 1 0;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    min-height: 102px;
    padding: 10px 11px 11px;
    overflow: hidden;
    font: inherit;
    color: #344054;
    text-align: left;
    background: rgba(255, 255, 255, 0.84);
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: 14px;
    cursor: pointer;
    transition: flex-grow 170ms ease, transform 170ms ease, border-color 170ms ease, box-shadow 170ms ease, background 170ms ease;
}

.timetable-v3__module-group-card:hover,
.timetable-v3__module-group-card:focus-visible {
    background: #fff;
    border-color: var(--module-group-accent);
    outline: none;
    box-shadow: 0 10px 24px rgba(var(--module-group-accent-rgb), 0.16);
    transform: translateY(-2px);
}

.timetable-v3__module-group-card--active {
    flex-grow: 1.5;
    color: #15173b;
    background: linear-gradient(145deg, #fff 10%, var(--module-group-soft) 100%);
    border-color: var(--module-group-accent);
    box-shadow: 0 11px 26px rgba(var(--module-group-accent-rgb), 0.16);
}

.timetable-v3__module-group-card--finished,
.timetable-v3__module-group-panel--finished {
    --module-group-accent: #2563eb;
    --module-group-accent-rgb: 37, 99, 235;
    --module-group-soft: #eff6ff;
}

.timetable-v3__module-group-card--negative,
.timetable-v3__module-group-panel--negative {
    --module-group-accent: #dc2626;
    --module-group-accent-rgb: 220, 38, 38;
    --module-group-soft: #fef2f2;
}

.timetable-v3__module-group-card--previous,
.timetable-v3__module-group-panel--previous {
    --module-group-accent: #d97706;
    --module-group-accent-rgb: 217, 119, 6;
    --module-group-soft: #fffbeb;
}

.timetable-v3__module-group-card--current,
.timetable-v3__module-group-panel--current {
    --module-group-accent: #16a34a;
    --module-group-accent-rgb: 22, 163, 74;
    --module-group-soft: #f0fdf4;
}

.timetable-v3__module-group-card--additional,
.timetable-v3__module-group-panel--additional {
    --module-group-accent: #7c3aed;
    --module-group-accent-rgb: 124, 58, 237;
    --module-group-soft: #f5f3ff;
}

.timetable-v3__module-group-card--main,
.timetable-v3__module-group-panel--main {
    --module-group-accent: #2563eb;
    --module-group-accent-rgb: 37, 99, 235;
    --module-group-soft: #eff6ff;
}

.timetable-v3__module-group-card-topline {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
}

.timetable-v3__module-group-card-icon {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    color: var(--module-group-accent);
    background: var(--module-group-soft);
    border: 1px solid rgba(var(--module-group-accent-rgb), 0.15);
}

.timetable-v3__module-group-card-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    transition: transform 170ms ease;
}

.timetable-v3__module-group-card:hover .timetable-v3__module-group-card-icon,
.timetable-v3__module-group-card:focus-visible .timetable-v3__module-group-card-icon {
    transform: scale(1.06);
}

.timetable-v3__module-group-card-title {
    overflow: hidden;
    margin-top: 1px;
    color: #1e293b;
    font-size: 0.84rem;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timetable-v3__module-group-card-title--main {
    display: flex;
    gap: 7px;
    align-items: baseline;
    overflow: visible;
    text-overflow: clip;
    white-space: normal;
}

.timetable-v3__main-module-code {
    flex: 0 0 auto;
    color: var(--module-group-accent);
    font-size: 0.92rem;
    letter-spacing: 0.02em;
}

.timetable-v3__main-module-name {
    min-width: 0;
    overflow-wrap: anywhere;
}

.timetable-v3__module-group-card-count {
    flex: 0 0 auto;
    padding: 3px 7px;
    color: #475467;
    background: rgba(241, 245, 249, 0.95);
    border: 1px solid rgba(203, 213, 225, 0.85);
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 800;
}

.timetable-v3__module-group-card--active .timetable-v3__module-group-card-count {
    color: var(--module-group-accent);
    background: #fff;
    border-color: rgba(var(--module-group-accent-rgb), 0.28);
}

.timetable-v3__module-group-card-description {
    display: -webkit-box;
    overflow: hidden;
    color: #64748b;
    font-size: 0.7rem;
    line-height: 1.35;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.timetable-v3__module-group-card-active-mark {
    position: absolute;
    right: 11px;
    bottom: 7px;
    left: 11px;
    height: 3px;
    background: var(--module-group-accent);
    border-radius: 999px;
    opacity: 0;
    transform: scaleX(0.35);
    transition: opacity 170ms ease, transform 170ms ease;
}

.timetable-v3__module-group-card--active .timetable-v3__module-group-card-active-mark {
    opacity: 1;
    transform: scaleX(1);
}

.timetable-v3__module-group-panel {
    --module-group-accent: #64748b;
    --module-group-accent-rgb: 100, 116, 139;
    --module-group-soft: #f1f5f9;
    margin-top: 13px;
    padding: 13px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(var(--module-group-accent-rgb), 0.28);
    border-radius: 16px;
    box-shadow: 0 13px 32px rgba(15, 23, 42, 0.08);
}

.timetable-v3__module-group-panel-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
    justify-content: flex-end;
    margin-bottom: 12px;
}

.timetable-v3__module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 8px;
}

.timetable-v3__module-tile {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr);
    gap: 9px;
    align-items: center;
    min-height: 60px;
    padding: 9px 10px;
    font: inherit;
    color: #1f2937;
    text-align: left;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #dbe1eb;
    border-radius: 12px;
    cursor: pointer;
    transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.timetable-v3__module-tile:hover,
.timetable-v3__module-tile:focus-visible {
    background: #fff;
    border-color: var(--module-group-accent);
    outline: none;
    box-shadow: 0 9px 22px rgba(var(--module-group-accent-rgb), 0.13);
    transform: translateY(-1px);
}

.timetable-v3__module-tile--selected {
    background: linear-gradient(135deg, var(--module-group-soft), #fff);
    border-color: var(--module-group-accent);
    box-shadow: inset 0 0 0 1px rgba(var(--module-group-accent-rgb), 0.12);
}

.timetable-v3__module-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    color: var(--module-group-accent);
    background: var(--module-group-soft);
    border: 1px solid rgba(var(--module-group-accent-rgb), 0.24);
    border-radius: 9px;
}

.timetable-v3__module-tile--selected .timetable-v3__module-check {
    color: #fff;
    background: var(--module-group-accent);
    border-color: var(--module-group-accent);
}

.timetable-v3-module-panel-enter-active,
.timetable-v3-module-panel-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.timetable-v3-module-panel-enter-from {
    opacity: 0;
    transform: translateY(7px);
}

.timetable-v3-module-panel-leave-to {
    opacity: 0;
    transform: translateY(-3px);
}

@media (prefers-reduced-motion: reduce) {
    .timetable-v3__schedule-mode-options,
    .timetable-v3__manual-module-catalog-heading-button,
    .timetable-v3__module-group-card,
    .timetable-v3__module-group-card-icon,
    .timetable-v3__module-group-card-active-mark,
    .timetable-v3__schedule-mode-card,
    .timetable-v3__schedule-create-button,
    .timetable-v3__module-tile,
    .timetable-v3__module-course,
    .timetable-v3__school-level-option,
    .timetable-v3-module-panel-enter-active,
    .timetable-v3-module-panel-leave-active {
        transition: none;
    }
}

.timetable-v3__module-main {
    display: grid;
    min-width: 0;
}

.timetable-v3__module-code {
    color: #111827;
    font-size: 0.88rem;
    font-weight: 850;
}

.timetable-v3__module-planned {
    align-self: start;
    justify-self: start;
    padding: 1px 6px;
    color: #166534;
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 800;
    line-height: 1.35;
}

.timetable-v3__module-not-intended {
    align-self: start;
    justify-self: start;
    padding: 1px 6px;
    color: #b42318;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 800;
    line-height: 1.35;
}

.timetable-v3__module-name {
    overflow: hidden;
    color: #667085;
    font-size: 0.68rem;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timetable-v3__module-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 3px 6px;
    align-items: center;
    margin-top: 3px;
    color: #667085;
    font-size: 0.62rem;
    font-weight: 700;
}

.timetable-v3__module-status,
.timetable-v3__module-grade,
.timetable-v3__module-course-count {
    padding: 1px 5px;
    color: #3730a3;
    background: #e0e7ff;
    border-radius: 999px;
}

.timetable-v3__module-list-empty {
    padding: 8px 10px;
    color: #667085;
    background: #f9fafb;
    border-radius: 10px;
    font-size: 0.76rem;
}

.timetable-v3__module-courses-dialog {
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.timetable-v3__module-courses-dialog :deep(.v-card-text) {
    max-height: min(65vh, 620px);
}

.timetable-v3__module-courses-dialog-title {
    color: #1e1b4b;
    font-size: 1.2rem;
    font-weight: 850;
}

.timetable-v3__module-courses-dialog-icon {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    color: #4f46e5;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 12px;
}

.timetable-v3__module-courses-dialog-subtitle {
    margin-bottom: 8px;
    color: #475467;
    font-size: 0.92rem;
    font-weight: 700;
}

.timetable-v3__module-courses-dialog-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 14px;
    align-items: center;
    justify-content: space-between;
}

.timetable-v3__module-courses-dialog-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}

.timetable-v3__module-courses-dialog-summary {
    display: inline-flex;
    gap: 4px;
    align-items: baseline;
    padding: 6px 10px;
    color: #4338ca;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 750;
}

.timetable-v3__module-courses-dialog-summary strong {
    font-size: 0.95rem;
}

.timetable-v3__module-course-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 9px;
}

.timetable-v3__module-course {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    gap: 10px;
    align-items: start;
    min-height: 82px;
    padding: 12px;
    font: inherit;
    color: #1f2937;
    text-align: left;
    background: #fff;
    border: 1px solid #dbe1eb;
    border-radius: 13px;
    cursor: pointer;
    transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.timetable-v3__module-course:hover,
.timetable-v3__module-course:focus-visible {
    background: #fafaff;
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 9px 22px rgba(79, 70, 229, 0.12);
    transform: translateY(-1px);
}

.timetable-v3__module-course--selected {
    background: linear-gradient(135deg, #eef2ff, #fff);
    border-color: #4f46e5;
    box-shadow: inset 0 0 0 1px rgba(79, 70, 229, 0.1);
}

.timetable-v3__module-course--read-only {
    grid-template-columns: minmax(0, 1fr);
    cursor: default;
}

.timetable-v3__module-course--read-only:hover {
    background: #fff;
    border-color: #dbe1eb;
    box-shadow: none;
    transform: none;
}

.timetable-v3__module-course-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    color: #4f46e5;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
}

.timetable-v3__module-course--selected .timetable-v3__module-course-check {
    color: #fff;
    background: #4f46e5;
    border-color: #4f46e5;
}

.timetable-v3__module-course-copy {
    display: grid;
    min-width: 0;
}

.timetable-v3__module-course-title {
    color: #111827;
    font-size: 0.9rem;
    font-weight: 850;
    overflow-wrap: anywhere;
}

.timetable-v3__module-course-subtitle {
    margin-top: 1px;
    color: #667085;
    font-size: 0.72rem;
    font-weight: 700;
}

.timetable-v3__module-course-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 3px 8px;
    margin-top: 6px;
    color: #667085;
    font-size: 0.68rem;
    font-weight: 650;
}

.timetable-v3__module-course-schedule {
    flex-basis: 100%;
}

.timetable-v3__module-course-overlap {
    flex-basis: 100%;
    color: #b42318;
    font-weight: 400;
}

.timetable-v3__module-course-hours,
.timetable-v3__module-course-instruction {
    display: inline-flex;
    gap: 3px;
    align-items: center;
    padding: 2px 6px;
    border-radius: 999px;
}

.timetable-v3__module-course-hours {
    color: #344054;
    background: #f2f4f7;
}

.timetable-v3__module-course-instruction {
    color: #4338ca;
    background: #e0e7ff;
}

.timetable-v3__module-course-empty {
    padding: 22px 16px;
    color: #667085;
    text-align: center;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 13px;
}

.timetable-v3__selection-label {
    color: #4f46e5;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.timetable-v3__selection-value {
    display: flex;
    gap: 8px;
    align-items: center;
    color: #1e1b4b;
    overflow-wrap: anywhere;
    font-size: clamp(1.65rem, 3vw, 2.2rem);
    font-weight: 800;
    line-height: 1.25;
}

.timetable-v3__selection-value > span {
    min-width: 0;
}

.timetable-v3__selection-value > .timetable-v3__selection-religion {
    flex: 0 0 auto;
    color: #6366f1;
    font-size: clamp(0.9rem, 1.5vw, 1.1rem);
    font-weight: 700;
}

.timetable-v3__student-sex-icon {
    flex: 0 0 auto;
}

.timetable-v3__email-row {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    max-width: 100%;
    margin-top: 7px;
    color: #4338ca;
}

.timetable-v3__email-address {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.9rem;
}

.timetable-v3__email-copy-button {
    flex: 0 0 auto;
}

.timetable-v3__student-info-actions {
    display: flex;
    flex: 0 0 auto;
    gap: 8px;
    align-self: center;
}

.timetable-v3__student-info-button,
.timetable-v3__study-info-button {
    flex: 0 0 auto;
}

.timetable-v3__info-hover-card {
    width: min(560px, calc(100vw - 32px));
    overflow: hidden;
    border: 1px solid rgba(79, 70, 229, 0.18);
}

.timetable-v3__info-hover-title {
    padding: 16px 16px 10px;
    color: #1e1b4b;
    font-size: 1.05rem;
    font-weight: 800;
}

.timetable-v3__info-hover-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.timetable-v3__info-hover-item {
    display: grid;
    gap: 3px;
    min-width: 0;
    padding: 10px 11px;
    background: #f8f9ff;
    border: 1px solid rgba(79, 70, 229, 0.12);
    border-radius: 10px;
}

.timetable-v3__info-hover-item .timetable-v3__info-value {
    overflow-wrap: anywhere;
    white-space: normal;
}

.timetable-v3__info-hover-status {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 12px;
    color: #475467;
    font-size: 0.9rem;
    font-weight: 650;
}

.timetable-v3__info-hover-status--error {
    color: #b42318;
}

.timetable-v3__study-info-hover-card {
    width: min(900px, calc(100vw - 32px));
    overflow: hidden;
    border: 1px solid rgba(13, 148, 136, 0.22);
}

.timetable-v3__study-info-hover-title {
    padding: 16px 16px 10px;
    color: #134e4a;
    font-size: 1.05rem;
    font-weight: 800;
}

.timetable-v3__study-info-hover-content {
    max-height: min(68vh, 620px);
    overflow-y: auto;
}

.timetable-v3__info-dialog-title {
    font-size: 1.35rem;
    font-weight: 800;
}

.timetable-v3__info-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    padding: 12px 14px;
    overflow: hidden;
    background: #f8f9ff;
    border: 1px solid rgba(79, 70, 229, 0.16);
    border-radius: 12px;
}

.timetable-v3__info-item {
    display: inline-flex;
    gap: 7px;
    align-items: baseline;
    min-width: 0;
    white-space: nowrap;
}

.timetable-v3__info-primary-row {
    display: grid;
    flex: 1 0 100%;
    grid-template-columns: max-content max-content minmax(0, 1fr);
    column-gap: 32px;
    align-items: baseline;
}

.timetable-v3__info-primary-row > .timetable-v3__info-item + .timetable-v3__info-item {
    position: relative;
}

.timetable-v3__info-primary-row > .timetable-v3__info-item + .timetable-v3__info-item::before {
    position: absolute;
    top: 0;
    bottom: 0;
    left: -16px;
    width: 1px;
    content: '';
    background: rgba(79, 70, 229, 0.2);
}

.timetable-v3__info-secondary-row {
    display: flex;
    flex: 1 0 100%;
    gap: 16px;
    align-items: baseline;
    padding-top: 12px;
    border-top: 1px solid rgba(79, 70, 229, 0.2);
}

.timetable-v3__info-label {
    color: #667085;
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.timetable-v3__info-value {
    color: #1e1b4b;
    overflow: visible;
    font-size: 1rem;
    font-weight: 750;
    text-overflow: clip;
}

.timetable-v3__info-imported-school-level {
    margin-inline-start: 0.25rem;
    font-weight: 400;
}

.timetable-v3__info-imported-school-level--editable {
    padding: 1px 4px;
    font: inherit;
    color: #4338ca;
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 5px;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.timetable-v3__info-imported-school-level--editable:focus-visible {
    outline: 2px solid rgba(79, 70, 229, 0.4);
    outline-offset: 2px;
}

.timetable-v3__school-level-dialog-title {
    color: #7a271a;
    font-size: 1.3rem;
    font-weight: 800;
}

.timetable-v3__school-level-dialog-description {
    margin: 0 0 18px;
    color: #475467;
    line-height: 1.55;
}

.timetable-v3__school-level-section + .timetable-v3__school-level-section {
    margin-top: 20px;
}

.timetable-v3__school-level-section-label {
    margin-bottom: 8px;
    color: #344054;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.timetable-v3__school-level-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(116px, 1fr));
    gap: 10px;
}

.timetable-v3__school-level-option {
    display: grid;
    gap: 3px;
    min-width: 116px;
    padding: 11px 13px;
    font: inherit;
    text-align: left;
    cursor: pointer;
    background: #f8f9ff;
    border: 1px solid rgba(79, 70, 229, 0.22);
    border-radius: 10px;
    transition:
        background-color 160ms ease,
        border-color 160ms ease,
        box-shadow 160ms ease;
}

.timetable-v3__school-level-option:hover:not(:disabled),
.timetable-v3__school-level-option:focus-visible {
    background: #eef2ff;
    border-color: #6366f1;
    outline: none;
}

.timetable-v3__school-level-option:disabled {
    cursor: wait;
    opacity: 0.65;
}

.timetable-v3__school-level-option--selected {
    background: #eef2ff;
    border-color: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.timetable-v3__school-level-option--original {
    color: #b42318;
    background: #fff4f2;
    border-color: #f04438;
}

.timetable-v3__school-level-option--original.timetable-v3__school-level-option--selected {
    background: #fee4e2;
    border-color: #d92d20;
    box-shadow: 0 0 0 2px rgba(217, 45, 32, 0.2);
}

.timetable-v3__school-level-option-value {
    font-size: 1rem;
    font-weight: 850;
}

.timetable-v3__school-level-option-meta {
    color: #667085;
    font-size: 0.78rem;
    font-weight: 650;
}

.timetable-v3__school-level-dialog-error {
    margin-top: 16px;
    padding: 10px 12px;
    color: #b42318;
    background: #fff4f2;
    border: 1px solid #fecdca;
    border-radius: 9px;
    font-weight: 650;
}

.timetable-v3__info-status {
    color: #475467;
    font-size: 0.95rem;
    font-weight: 650;
}

.timetable-v3__info-status--error {
    color: #b42318;
}

.timetable-v3__study-info-content {
    max-height: min(68vh, 620px);
    overflow-y: auto;
}

.timetable-v3__study-info-feedback {
    display: flex;
    gap: 10px;
    align-items: center;
    min-height: 120px;
    color: #475467;
    font-size: 1rem;
    font-weight: 650;
}

.timetable-v3__study-info-feedback--error {
    color: #b42318;
}

.timetable-v3__study-module-groups {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.timetable-v3__study-module-group {
    --study-module-accent: #2563eb;
    --study-module-background: #eff6ff;
    --study-module-border: #bfdbfe;

    min-width: 0;
    overflow: hidden;
    background: var(--study-module-background);
    border: 1px solid var(--study-module-border);
    border-radius: 14px;
}

.timetable-v3__study-module-group--passed {
    --study-module-accent: #15803d;
    --study-module-background: #f0fdf4;
    --study-module-border: #bbf7d0;
}

.timetable-v3__study-module-group--failed {
    --study-module-accent: #b42318;
    --study-module-background: #fef3f2;
    --study-module-border: #fecdca;
}

.timetable-v3__study-module-group-header {
    padding: 16px;
    color: var(--study-module-accent);
    border-bottom: 1px solid var(--study-module-border);
}

.timetable-v3__study-module-group-title {
    font-size: 1.02rem;
    font-weight: 800;
    line-height: 1.3;
}

.timetable-v3__study-module-group-count {
    margin-top: 3px;
    font-size: 0.82rem;
    font-weight: 700;
}

.timetable-v3__study-module-list {
    display: grid;
    gap: 8px;
    padding: 12px;
}

.timetable-v3__study-module-row {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    padding: 9px 10px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid var(--study-module-border);
    border-radius: 9px;
}

.timetable-v3__study-module-copy {
    display: grid;
    flex: 1 1 auto;
    gap: 2px;
    min-width: 0;
}

.timetable-v3__study-module-code {
    overflow-wrap: anywhere;
    color: #1d2939;
    font-weight: 750;
}

.timetable-v3__study-module-name {
    color: #667085;
    overflow-wrap: anywhere;
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.25;
}

.timetable-v3__study-module-grades {
    display: flex;
    flex: 0 0 auto;
    gap: 6px;
    align-items: center;
}

.timetable-v3__study-module-grade {
    display: inline-grid;
    place-items: center;
    min-width: 30px;
    height: 30px;
    padding-inline: 8px;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 850;
    background: var(--study-module-accent);
    border-radius: 999px;
}

.timetable-v3__study-module-grade--exempt {
    background: #2563eb;
}

.timetable-v3__study-module-grade--passed {
    background: #15803d;
}

.timetable-v3__study-module-grade--failed {
    background: #b42318;
}

.timetable-v3__study-module-empty {
    padding: 20px 14px;
    color: #667085;
    font-size: 0.92rem;
    text-align: center;
}

.timetable-v3__page-actions {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: flex-end;
    margin-top: auto;
    padding-top: 32px;
}

.timetable-v3__page-actions--split {
    justify-content: space-between;
}

.timetable-v3__student-list {
    overflow-y: auto;
    border: 1px solid #e2e5ed;
    border-radius: 12px;
}

.timetable-v3__student-dialog-title {
    font-size: 1.5rem;
    line-height: 1.35;
}

.timetable-v3__student-total,
.timetable-v3__student-feedback {
    font-size: 1.08rem;
    line-height: 1.5;
}

.timetable-v3__student-search :deep(.v-field__input),
.timetable-v3__student-search :deep(.v-label) {
    font-size: 1.1rem;
}

.timetable-v3__student-list :deep(.v-list-item-title) {
    font-size: 1.1rem;
    font-weight: 600;
}

.timetable-v3__student-list :deep(.v-list-item-subtitle) {
    font-size: 1rem;
}

.timetable-v3__student-list-empty {
    padding: 18px 12px;
    color: #667085;
    font-size: 1.08rem;
    line-height: 1.5;
    text-align: center;
}

.timetable-v3__student-dialog-action {
    font-size: 1.05rem;
}

@media (max-width: 700px) {
    .timetable-v3__choice-grid {
        grid-template-columns: 1fr;
    }

    .timetable-v3__planning-selection-grid {
        grid-template-columns: 1fr;
    }

    .timetable-v3__manual-module-catalog-headings--with-student {
        grid-template-columns: 1fr;
    }

    .timetable-v3__schedule-mode-options,
    .timetable-v3__creation-summary-cards,
    .timetable-v3__adoption-cards {
        grid-template-columns: 1fr;
    }

    .timetable-v3__creation-summary-card--automatic,
    .timetable-v3__creation-summary-card--manual,
    .timetable-v3__creation-summary-card--options,
    .timetable-v3__adoption-card--manual {
        grid-column: 1;
        grid-row: auto;
    }

    .timetable-v3__adoption-heading {
        flex-wrap: wrap;
    }

    .timetable-v3__module-workspace {
        padding: 13px;
    }

    .timetable-v3__module-workspace-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .timetable-v3__module-selected-total {
        align-self: flex-start;
    }

    .timetable-v3__module-group-cards {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .timetable-v3__module-group-panel-actions {
        justify-content: flex-start;
    }

    .timetable-v3__module-grid {
        grid-template-columns: 1fr;
    }

    .timetable-v3__module-course-list {
        grid-template-columns: 1fr;
    }

    .timetable-v3__solution-plan-scenarios {
        grid-template-columns: 1fr;
    }

    .timetable-v3__solution-plan-scenario {
        align-items: flex-start;
        flex-direction: column;
    }

    .timetable-v3__solution-plan-result {
        justify-items: start;
        padding-left: 48px;
    }

    .timetable-v3__calculation-led-progress {
        gap: 3px;
        padding: 10px;
    }

    .timetable-v3__calculation-led-segment {
        height: 23px;
    }

    .timetable-v3__choice {
        min-height: 108px;
        padding: 17px;
    }

    .timetable-v3__page-actions > :deep(.v-btn) {
        flex: 1 1 0;
    }

    .timetable-v3__info-grid {
        gap: 10px;
        padding-inline: 11px;
    }

    .timetable-v3__info-secondary-row {
        gap: 10px;
    }

    .timetable-v3__info-primary-row {
        grid-template-columns: max-content minmax(0, 1fr);
        column-gap: 20px;
        row-gap: 12px;
    }

    .timetable-v3__info-primary-row > .timetable-v3__info-item:last-child {
        grid-column: 1 / -1;
        padding-top: 12px;
        border-top: 1px solid rgba(79, 70, 229, 0.2);
    }

    .timetable-v3__info-primary-row > .timetable-v3__info-item:last-child::before {
        display: none;
    }

    .timetable-v3__study-module-groups {
        grid-template-columns: 1fr;
    }
}

@media (prefers-reduced-motion: reduce) {
    .timetable-v3__calculation-led-segment {
        transition: none;
    }
}

</style>
