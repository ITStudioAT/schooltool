<template>
    <v-sheet rounded="lg" class="timetable-v3 pa-5 pa-md-7">
        <div v-if="currentStep === 'selection'" class="timetable-v3__step">
            <div>
                <div>
                    <div class="text-overline text-primary">Version 3</div>
                    <h2 class="text-h5 font-weight-bold mb-2">Stundenplan erstellen</h2>
                    <p class="text-body-1 text-medium-emphasis mb-0">
                        Wie möchten Sie beginnen?
                    </p>
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
                </div>

                <div v-if="planningMode === 'with_student'" class="timetable-v3__student-info-actions">
                    <v-btn
                        class="timetable-v3__student-info-button"
                        icon="mdi-information-outline"
                        size="large"
                        color="primary"
                        variant="tonal"
                        aria-label="Informationen zum Studierenden anzeigen"
                        @click="openStudentInfoDialog" />
                    <v-btn
                        class="timetable-v3__study-info-button"
                        icon="mdi-school-outline"
                        size="large"
                        color="teal-darken-1"
                        variant="tonal"
                        aria-label="Informationen zum Studium anzeigen"
                        @click="openStudyInfoDialog" />
                </div>
            </div>

            <section
                v-if="hasPlanningSelectionContext"
                class="timetable-v3__planning-selection mt-6"
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
            </section>

            <div class="timetable-v3__page-actions timetable-v3__page-actions--split">
                <v-btn
                    class="timetable-v3__restart-button"
                    size="large"
                    color="error"
                    variant="outlined"
                    prepend-icon="mdi-restart"
                    :disabled="!hasPlanningSelectionContext || isLoadingState || isSavingState"
                    @click="restartPlanning">
                    Neustart
                </v-btn>
                <v-btn
                    class="timetable-v3__selection-continue-button"
                    size="large"
                    color="primary"
                    append-icon="mdi-arrow-right"
                    :disabled="!hasPlanningSelectionContext || isLoadingState || isSavingState"
                    @click="continueToNextStep">
                    Weiter
                </v-btn>
            </div>
        </div>

        <div v-else class="timetable-v3__step timetable-v3__next-step">
            <div>
                <div class="text-overline text-primary">Version 3</div>
                <h2 class="text-h5 font-weight-bold mb-2">Stundenplan erstellen</h2>
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
                </div>

                <div v-if="planningMode === 'with_student'" class="timetable-v3__student-info-actions">
                    <v-btn
                        class="timetable-v3__student-info-button"
                        icon="mdi-information-outline"
                        size="large"
                        color="primary"
                        variant="tonal"
                        aria-label="Informationen zum Studierenden anzeigen"
                        @click="openStudentInfoDialog" />
                    <v-btn
                        class="timetable-v3__study-info-button"
                        icon="mdi-school-outline"
                        size="large"
                        color="teal-darken-1"
                        variant="tonal"
                        aria-label="Informationen zum Studium anzeigen"
                        @click="openStudyInfoDialog" />
                </div>
            </div>

            <section
                class="timetable-v3__compact-planning-card mt-4"
                aria-label="Studienauswahl">
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

            <section
                v-if="!studentSelectionDetailsLoading && !studentSelectionDetailsError"
                class="timetable-v3__module-workspace mt-4"
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
                    role="radiogroup"
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
                            @change="chooseScheduleCreationMode('automatic')" />
                        <span class="timetable-v3__schedule-mode-icon">
                            <v-icon icon="mdi-calendar-clock" size="22" />
                        </span>
                        <span class="timetable-v3__schedule-mode-copy">
                            <span class="timetable-v3__schedule-mode-title">Automatischer Stundenplan</span>
                            <span class="timetable-v3__schedule-mode-description">
                                Stundenplan automatisch optimieren
                            </span>
                        </span>
                        <v-icon
                            :icon="scheduleCreationMode === 'automatic' ? 'mdi-check-circle' : 'mdi-circle-outline'"
                            size="22" />

                        <div
                            class="timetable-v3__selected-modules timetable-v3__schedule-mode-selected-modules"
                            aria-live="polite">
                            <div class="timetable-v3__selected-modules-heading">
                                <span class="timetable-v3__selected-modules-heading-label">
                                    <v-icon icon="mdi-check-circle-outline" size="18" />
                                    Ausgewählte Module
                                </span>
                                <span class="timetable-v3__selected-modules-summary">
                                    {{ selectedModuleCount }}
                                    {{ selectedModuleCount === 1 ? 'Modul' : 'Module' }}
                                    · {{ selectedModuleHoursLabel }} Std.
                                </span>
                            </div>
                            <div v-if="selectedModules.length" class="timetable-v3__selected-modules-list">
                                <v-chip
                                    v-for="module in selectedModules"
                                    :key="module.selection_key"
                                    class="timetable-v3__selected-module-chip"
                                    color="primary"
                                    closable
                                    close-icon="mdi-close-circle"
                                    :close-label="`${module.code} aus der Auswahl entfernen`"
                                    :disabled="isSavingState"
                                    label
                                    size="small"
                                    variant="tonal"
                                    @click:close.stop="removeSelectedModule(module)">
                                    <strong>{{ module.code }}</strong>
                                    <span v-if="module.name && module.name !== module.code">
                                        &nbsp;· {{ module.name }}
                                    </span>
                                </v-chip>
                            </div>
                            <div v-else class="timetable-v3__selected-modules-empty">
                                Keine Module ausgewählt.
                            </div>
                        </div>
                    </label>

                    <label
                        class="timetable-v3__schedule-mode-card timetable-v3__schedule-mode-card--manual"
                        :class="{ 'timetable-v3__schedule-mode-card--selected': scheduleCreationMode === 'manual' }">
                        <input
                            class="timetable-v3__schedule-mode-input"
                            type="radio"
                            name="timetable-v3-schedule-mode"
                            value="manual"
                            :checked="scheduleCreationMode === 'manual'"
                            @change="chooseScheduleCreationMode('manual')" />
                        <span class="timetable-v3__schedule-mode-icon">
                            <v-icon icon="mdi-calendar-edit" size="22" />
                        </span>
                        <span class="timetable-v3__schedule-mode-copy">
                            <span class="timetable-v3__schedule-mode-title">Manueller Stundenplan</span>
                            <span class="timetable-v3__schedule-mode-description">
                                Stundenplan selbst zusammenstellen
                            </span>
                        </span>
                        <v-icon
                            :icon="scheduleCreationMode === 'manual' ? 'mdi-check-circle' : 'mdi-circle-outline'"
                            size="22" />

                        <div
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
                    </label>
                </div>

                <div
                    v-if="scheduleCreationMode === 'automatic'"
                    class="timetable-v3__module-group-cards"
                    aria-label="Modularten">
                    <button
                        v-for="group in moduleSelectionGroups"
                        :key="group.key"
                        type="button"
                        class="timetable-v3__module-group-card"
                        :class="[
                            `timetable-v3__module-group-card--${group.key}`,
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
                        <span class="timetable-v3__module-group-card-title">{{ group.label }}</span>
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
                        :class="`timetable-v3__module-group-panel--${activeModuleSelectionGroup.key}`">
                        <div class="timetable-v3__module-group-panel-heading">
                            <div class="timetable-v3__module-group-panel-heading-main">
                                <span class="timetable-v3__module-group-panel-icon">
                                    <v-icon :icon="moduleGroupIcon(activeModuleSelectionGroup)" size="22" />
                                </span>
                                <div class="timetable-v3__module-group-panel-heading-copy">
                                    <div class="timetable-v3__module-group-panel-title-row">
                                        <h4 class="timetable-v3__module-group-panel-title">
                                            {{ activeModuleSelectionGroup.label }} Module
                                        </h4>
                                        <v-btn
                                            class="timetable-v3__module-group-panel-close"
                                            color="orange-darken-2"
                                            height="34"
                                            min-width="34"
                                            rounded="sm"
                                            size="small"
                                            variant="flat"
                                            width="34"
                                            aria-label="Modulart schließen"
                                            title="Modulart schließen"
                                            @click="closeModuleGroup">
                                            <v-icon icon="mdi-close" size="20" />
                                        </v-btn>
                                    </div>
                                    <div class="timetable-v3__module-group-panel-description">
                                        {{ activeModuleSelectionGroup.description }}
                                    </div>
                                </div>
                            </div>
                            <div class="timetable-v3__module-group-panel-controls">
                                <div class="timetable-v3__module-group-panel-actions">
                                    <v-btn
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        prepend-icon="mdi-checkbox-multiple-marked-outline"
                                        :disabled="isSavingState || allModulesSelectedForGroup(activeModuleSelectionGroup)"
                                        @click="selectAllModulesInGroup(activeModuleSelectionGroup)">
                                        {{ activeModuleSelectionGroup.label }} Module auswählen
                                    </v-btn>
                                    <v-btn
                                        size="small"
                                        variant="text"
                                        color="primary"
                                        prepend-icon="mdi-checkbox-multiple-blank-outline"
                                        :disabled="isSavingState || !hasSelectedModulesForGroup(activeModuleSelectionGroup)"
                                        @click="deselectAllModulesInGroup(activeModuleSelectionGroup)">
                                        {{ activeModuleSelectionGroup.label }} Module abwählen
                                    </v-btn>
                                </div>
                                <span class="timetable-v3__module-group-panel-count">
                                    <strong>{{ selectedModuleCountForGroup(activeModuleSelectionGroup) }}</strong>
                                    von {{ activeModuleSelectionGroup.count }} gewählt
                                </span>
                            </div>
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
                                    <span class="timetable-v3__module-code">{{ module.code }}</span>
                                    <span v-if="module.name && module.name !== module.code" class="timetable-v3__module-name">
                                        {{ module.name }}
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

                <div
                    v-if="scheduleCreationMode === 'automatic' && planningMode === 'without_student'"
                    class="timetable-v3__module-without-student-hint">
                    Ohne Studierenden werden alle verfügbaren Module unter „Zusätzliche“ angeboten.
                </div>
            </section>

            <div class="timetable-v3__page-actions timetable-v3__page-actions--split">
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
                <v-btn
                    class="timetable-v3__next-continue-button"
                    size="large"
                    color="primary"
                    append-icon="mdi-arrow-right"
                    :disabled="studentSelectionDetailsLoading || isLoadingState || isSavingState">
                    Weiter
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
                        Unterrichte für {{ moduleCourseDialogModule?.code || 'Modul' }}
                    </span>
                </v-card-title>

                <v-card-text class="px-5 pt-3">
                    <div
                        v-if="moduleCourseDialogModule?.name && moduleCourseDialogModule.name !== moduleCourseDialogModule.code"
                        class="timetable-v3__module-courses-dialog-subtitle">
                        {{ moduleCourseDialogModule.name }}
                    </div>
                    <div class="timetable-v3__module-courses-dialog-toolbar">
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

                    <div v-if="moduleCourseDialogCourses.length" class="timetable-v3__module-course-list mt-4">
                        <button
                            v-for="course in moduleCourseDialogCourses"
                            :key="course.key"
                            type="button"
                            class="timetable-v3__module-course"
                            :class="{ 'timetable-v3__module-course--selected': moduleCourseSelected(course) }"
                            role="checkbox"
                            :aria-checked="moduleCourseSelected(course)"
                            @click="toggleModuleCourse(course)">
                            <span class="timetable-v3__module-course-check">
                                <v-icon
                                    :icon="moduleCourseSelected(course) ? 'mdi-check' : 'mdi-checkbox-blank-outline'"
                                    size="18" />
                            </span>
                            <span class="timetable-v3__module-course-copy">
                                <span class="timetable-v3__module-course-title">{{ course.title }}</span>
                                <span
                                    v-if="course.course_title && course.course_title !== course.title"
                                    class="timetable-v3__module-course-subtitle">
                                    {{ course.course_title }}
                                </span>
                                <span class="timetable-v3__module-course-meta">
                                    <span
                                        v-for="scheduleLabel in courseScheduleLabels(course)"
                                        :key="scheduleLabel"
                                        class="timetable-v3__module-course-schedule">
                                        {{ scheduleLabel }}
                                    </span>
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
                        </button>
                    </div>
                    <div v-else class="timetable-v3__module-course-empty mt-4">
                        Für dieses Modul sind keine Unterrichte im importierten Stundenplan vorhanden.
                    </div>
                </v-card-text>

                <v-card-actions class="px-5 pb-5 pt-3">
                    <v-spacer />
                    <v-btn color="primary" variant="flat" size="large" @click="closeModuleCoursesDialog">
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
                        <div class="timetable-v3__info-secondary-row timetable-v3__info-secondary-row--semester">
                            <div class="timetable-v3__info-item">
                                <span class="timetable-v3__info-label">Semester</span>
                                <span class="timetable-v3__info-value">{{ selectedStudentSemesterLabel }}</span>
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
                                        <span class="timetable-v3__study-module-code">{{ module.code }}</span>
                                        <span
                                            v-if="module.name && module.name !== module.code"
                                            class="timetable-v3__study-module-name">
                                            {{ module.name }}
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
import { robotStudents as loadRobotStudents } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentsTimetablesController'
import { show as loadV3StudentInformation } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StudentInformationController'
import {
    show as showTimetableV3State,
    update as updateTimetableV3State,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StateController'

const WITH_STUDENT = 'with_student'
const WITHOUT_STUDENT = 'without_student'
const AUTOMATIC_TIMETABLE = 'automatic'
const MANUAL_TIMETABLE = 'manual'
const SELECTION_STEP = 'selection'
const MODULE_SELECTION_STEP = 'modules'
const TIMETABLE_V3_SELECTION_PATH = '/admin/students-timetables/timetable-v3/overview'
const TIMETABLE_V3_MODULE_SELECTION_PATH = '/admin/students-timetables/timetable-v3/modules'

function normalizedCourseSelectionKeys(course) {
    const courseKeys = Array.isArray(course?.keys) ? course.keys : [course?.key]

    return [...new Set(courseKeys
        .map(courseKey => String(courseKey || '').trim())
        .filter(Boolean))]
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

export default {
    name: 'TimetableV3',

    data() {
        return {
            isLoadingState: true,
            isSavingState: false,
            stateLoadFailed: false,
            stateSaveFailed: false,
            storedState: null,
            planningMode: null,
            selectedStudent: null,
            studentDialogOpen: false,
            studentInfoDialogOpen: false,
            studyInfoDialogOpen: false,
            studentSearch: '',
            students: [],
            studentOptionsLoading: false,
            studentOptionsError: false,
            studentSelectionDetailsLoading: false,
            studentSelectionDetailsError: false,
            studentSelectionDetailsCode: '',
            studentSelectionDetailsRequestCode: '',
            studentSelectionItems: [],
            studentStudyModuleGroups: [],
            moduleSelectionGroups: [],
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            scheduleCreationMode: null,
            activeModuleGroupKey: '',
            moduleCoursesDialogOpen: false,
            moduleCourseDialogModule: null,
            moduleSelectionResetPending: false,
            planningSelectionFields: [],
            planningSelectionValues: {},
            emailCopyStatus: 'idle',
        }
    },

    computed: {
        currentStep() {
            return this.$route?.params?.subsection === MODULE_SELECTION_STEP
                ? MODULE_SELECTION_STEP
                : SELECTION_STEP
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
        selectedStudentSemesterLabel() {
            const semester = this.normalizedStudentSemester(this.selectedStudent)

            return semester ? `${semester}. Semester` : '–'
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
            return this.selectedModuleKeys.length
        },
        selectedModules() {
            const selectedModuleKeys = new Set(this.selectedModuleKeys)

            return this.moduleSelectionGroups
                .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
                .filter(module => selectedModuleKeys.has(module.selection_key))
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
        activeModuleSelectionGroup() {
            return this.moduleSelectionGroups
                .find(group => group.key === this.activeModuleGroupKey) || null
        },
        moduleCourseDialogCourses() {
            return Array.isArray(this.moduleCourseDialogModule?.courses)
                ? this.moduleCourseDialogModule.courses
                : []
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
        currentStep(currentStep) {
            if (currentStep === MODULE_SELECTION_STEP) {
                this.scheduleCreationMode = null
            }
        },
    },

    async created() {
        await this.loadState()

        if (this.currentStep === MODULE_SELECTION_STEP && !this.hasPlanningSelectionContext) {
            await this.$router.replace({ path: TIMETABLE_V3_SELECTION_PATH })
        }
    },

    methods: {
        continueToNextStep() {
            if (!this.hasPlanningSelectionContext) return

            void this.$router.push({ path: TIMETABLE_V3_MODULE_SELECTION_PATH })
        },
        returnToSelectionStep() {
            void this.$router.push({ path: TIMETABLE_V3_SELECTION_PATH })
        },
        async loadState() {
            this.isLoadingState = true
            this.stateLoadFailed = false

            try {
                const response = await axios.get(showTimetableV3State.url())
                this.storedState = response.data?.data?.state ?? null
                this.restoreEntrySelection()
            } catch {
                this.stateLoadFailed = true
            } finally {
                this.isLoadingState = false
            }
        },
        restoreEntrySelection() {
            const entrySelection = this.storedState?.entrySelection

            if (entrySelection?.mode === WITH_STUDENT && entrySelection.student) {
                this.planningMode = WITH_STUDENT
                this.selectedStudent = entrySelection.student
                this.resetSelectedStudentSelectionDetails()
                void this.hydrateSelectedStudentDetails()
                void this.loadSelectedStudentSelection()
                return
            }

            if (entrySelection?.mode === WITHOUT_STUDENT) {
                this.planningMode = WITHOUT_STUDENT
                this.selectedStudent = null
                this.resetSelectedStudentSelectionDetails()
                void this.loadSelectedStudentSelection()
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
            void this.loadSelectedStudentSelection()
        },
        async restartPlanning() {
            this.planningMode = null
            this.selectedStudent = null
            this.studentDialogOpen = false
            this.studentSearch = ''
            this.emailCopyStatus = 'idle'
            this.studentInfoDialogOpen = false
            this.studyInfoDialogOpen = false
            this.resetSelectedStudentSelectionDetails()
            await this.saveState()
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
        resetSelectedStudentSelectionDetails() {
            this.studentSelectionDetailsLoading = false
            this.studentSelectionDetailsError = false
            this.studentSelectionDetailsCode = ''
            this.studentSelectionDetailsRequestCode = ''
            this.studentSelectionItems = []
            this.studentStudyModuleGroups = []
            this.moduleSelectionGroups = []
            this.selectedModuleKeys = []
            this.selectedCourseKeys = []
            this.scheduleCreationMode = null
            this.activeModuleGroupKey = ''
            this.moduleCoursesDialogOpen = false
            this.moduleCourseDialogModule = null
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

                if (studentCode) {
                    this.selectedStudent = {
                        ...this.selectedStudent,
                        religion: String(studentInformation.religion || '').trim(),
                        instructionType: String(studentInformation.instruction_type || '').trim(),
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
                this.studentSelectionDetailsCode = selectionContextCode
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
            const storedValuesMatch = JSON.stringify(storedPlanningValues) === JSON.stringify(currentPlanningValues)
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
            if (!groups.some(group => group.key === this.activeModuleGroupKey)) {
                this.activeModuleGroupKey = ''
            }
            this.moduleSelectionResetPending = false
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
        courseSelectionKeys(course) {
            return normalizedCourseSelectionKeys(course)
        },
        courseScheduleLabels(course) {
            return normalizedCourseScheduleLabels(course)
        },
        moduleGroupActive(group) {
            return group?.key === this.activeModuleGroupKey
        },
        moduleGroupIcon(group) {
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
        closeModuleGroup() {
            this.activeModuleGroupKey = ''
        },
        chooseScheduleCreationMode(scheduleCreationMode) {
            if (![AUTOMATIC_TIMETABLE, MANUAL_TIMETABLE].includes(scheduleCreationMode)) return
            if (this.scheduleCreationMode === scheduleCreationMode) return

            this.scheduleCreationMode = scheduleCreationMode
        },
        openModuleCoursesDialog(module) {
            if (!module?.selection_key) return

            this.moduleCourseDialogModule = module
            this.moduleCoursesDialogOpen = true
        },
        closeModuleCoursesDialog() {
            this.moduleCoursesDialogOpen = false
        },
        async toggleModuleCourse(course) {
            const courseKeys = normalizedCourseSelectionKeys(course)
            const moduleSelectionKey = String(this.moduleCourseDialogModule?.selection_key || '').trim()
            if (!courseKeys.length || !moduleSelectionKey) return

            this.selectedCourseKeys = this.moduleCourseSelected(course)
                ? this.selectedCourseKeys.filter(courseKey => !courseKeys.includes(courseKey))
                : [...new Set([...this.selectedCourseKeys, ...courseKeys])]

            const hasSelectedCourse = this.selectedCourseCountForModule(this.moduleCourseDialogModule) > 0
            this.selectedModuleKeys = hasSelectedCourse
                ? [...new Set([...this.selectedModuleKeys, moduleSelectionKey])]
                : this.selectedModuleKeys.filter(key => key !== moduleSelectionKey)
            await this.saveState()
        },
        async selectAllModuleCourses() {
            const moduleSelectionKey = String(this.moduleCourseDialogModule?.selection_key || '').trim()
            const courseKeys = this.moduleCourseDialogCourses.flatMap(course => normalizedCourseSelectionKeys(course))
            if (!moduleSelectionKey || !courseKeys.length) return

            this.selectedCourseKeys = [...new Set([...this.selectedCourseKeys, ...courseKeys])]
            this.selectedModuleKeys = [...new Set([...this.selectedModuleKeys, moduleSelectionKey])]
            await this.saveState()
        },
        async deselectAllModuleCourses() {
            const moduleSelectionKey = String(this.moduleCourseDialogModule?.selection_key || '').trim()
            const courseKeys = this.moduleCourseDialogCourses.flatMap(course => normalizedCourseSelectionKeys(course))
            if (!moduleSelectionKey || !courseKeys.length) return

            this.selectedCourseKeys = this.selectedCourseKeys
                .filter(courseKey => !courseKeys.includes(courseKey))
            this.selectedModuleKeys = this.selectedModuleKeys
                .filter(selectionKey => selectionKey !== moduleSelectionKey)
            await this.saveState()
        },
        async selectAllModulesInGroup(group) {
            const moduleSelectionKeys = this.moduleSelectionKeysForGroup(group)
            const courseSelectionKeys = this.courseSelectionKeysForGroup(group)
            if (!moduleSelectionKeys.length || !courseSelectionKeys.length) return

            this.selectedModuleKeys = [...new Set([...this.selectedModuleKeys, ...moduleSelectionKeys])]
            this.selectedCourseKeys = [...new Set([...this.selectedCourseKeys, ...courseSelectionKeys])]
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

            if (hasSex && hasReligion && hasSemester && hasInstructionType) return

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
            }
            this.closeStudentDialog()
            await this.saveState()
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
        async saveState() {
            this.isSavingState = true
            this.stateSaveFailed = false

            const currentState = this.storedState && typeof this.storedState === 'object'
                ? this.storedState
                : {}
            const state = {
                ...currentState,
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
                    selectedKeys: this.selectedModuleKeys || [],
                    selectedCourseKeys: this.selectedCourseKeys || [],
                },
            }

            try {
                const response = await axios.put(updateTimetableV3State.url(), { state })
                this.storedState = response.data?.data?.state ?? state
            } catch {
                this.stateSaveFailed = true
            } finally {
                this.isSavingState = false
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

.timetable-v3__planning-selection {
    padding: 20px;
    background: rgba(255, 255, 255, 0.84);
    border: 1px solid #dfe3f0;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(30, 36, 51, 0.08);
}

.timetable-v3__planning-selection-heading {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 18px;
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
    gap: 14px;
}

.timetable-v3__planning-selection-group {
    min-width: 0;
    padding: 15px;
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
    min-height: 46px;
    padding: 10px 13px;
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
    margin-bottom: 3px;
    color: #6366f1;
    font-size: 0.66rem;
    font-weight: 850;
    letter-spacing: 0.11em;
    text-transform: uppercase;
}

.timetable-v3__module-workspace-title {
    color: #15173b;
    font-size: clamp(1.08rem, 1.8vw, 1.38rem);
    font-weight: 850;
    line-height: 1.25;
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
    gap: 10px;
    margin-top: 13px;
    transition: grid-template-columns 170ms ease;
}

.timetable-v3__schedule-mode-options--automatic-selected {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
}

.timetable-v3__schedule-mode-options--manual-selected {
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
}

.timetable-v3__schedule-mode-card {
    --schedule-mode-accent: #4f46e5;
    --schedule-mode-accent-rgb: 79, 70, 229;
    --schedule-mode-soft: #eef2ff;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 11px;
    align-items: center;
    align-content: start;
    min-width: 0;
    min-height: 78px;
    padding: 12px 14px;
    font: inherit;
    color: #344054;
    text-align: left;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid #dbe3ef;
    border-radius: 14px;
    cursor: pointer;
    transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.timetable-v3__schedule-mode-input {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    opacity: 0;
}

.timetable-v3__schedule-mode-card--manual {
    --schedule-mode-accent: #b45309;
    --schedule-mode-accent-rgb: 180, 83, 9;
    --schedule-mode-soft: #fffbeb;
}

.timetable-v3__schedule-mode-card:hover,
.timetable-v3__schedule-mode-card:focus-within {
    background: #fff;
    border-color: var(--schedule-mode-accent);
    outline: none;
    box-shadow: 0 9px 22px rgba(var(--schedule-mode-accent-rgb), 0.14);
    transform: translateY(-1px);
}

.timetable-v3__schedule-mode-card--selected {
    color: var(--schedule-mode-accent);
    background: linear-gradient(
        135deg,
        rgba(var(--schedule-mode-accent-rgb), 0.18),
        var(--schedule-mode-soft) 58%,
        #fff
    );
    border-color: var(--schedule-mode-accent);
    box-shadow:
        inset 0 0 0 2px rgba(var(--schedule-mode-accent-rgb), 0.2),
        0 10px 26px rgba(var(--schedule-mode-accent-rgb), 0.18);
}

.timetable-v3__schedule-mode-card--selected > .v-icon,
.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-title {
    color: var(--schedule-mode-accent);
}

.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-icon {
    color: #fff;
    background: var(--schedule-mode-accent);
    border-color: var(--schedule-mode-accent);
    box-shadow: 0 6px 16px rgba(var(--schedule-mode-accent-rgb), 0.24);
}

.timetable-v3__schedule-mode-icon {
    display: inline-grid;
    place-items: center;
    width: 40px;
    height: 40px;
    color: var(--schedule-mode-accent);
    background: var(--schedule-mode-soft);
    border: 1px solid rgba(var(--schedule-mode-accent-rgb), 0.2);
    border-radius: 11px;
}

.timetable-v3__schedule-mode-copy {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.timetable-v3__schedule-mode-title {
    color: #1e293b;
    font-size: 0.88rem;
    font-weight: 850;
}

.timetable-v3__schedule-mode-description {
    color: #64748b;
    font-size: 0.7rem;
    line-height: 1.35;
}

.timetable-v3__schedule-mode-selected-modules {
    grid-column: 1 / -1;
    margin-top: 2px;
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(var(--schedule-mode-accent-rgb), 0.2);
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

.timetable-v3__module-group-card-topline {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
}

.timetable-v3__module-group-card-icon,
.timetable-v3__module-group-panel-icon {
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

.timetable-v3__module-group-card-count,
.timetable-v3__module-group-panel-count {
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

.timetable-v3__module-group-panel-heading {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    justify-content: space-between;
    margin: -13px -13px 12px;
    padding: 13px 14px;
    background: linear-gradient(125deg, var(--module-group-soft), rgba(255, 255, 255, 0.9));
    border-bottom: 1px solid rgba(var(--module-group-accent-rgb), 0.14);
}

.timetable-v3__module-group-panel-heading-main {
    display: flex;
    gap: 10px;
    align-items: center;
    min-width: 0;
}

.timetable-v3__module-group-panel-heading-copy {
    min-width: 0;
}

.timetable-v3__module-group-panel-title-row {
    display: flex;
    flex-wrap: wrap;
    gap: 7px 10px;
    align-items: center;
}

.timetable-v3__module-group-panel-close {
    padding: 0;
}

.timetable-v3__module-group-panel-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
}

.timetable-v3__module-group-panel-title {
    color: #172033;
    font-size: 1rem;
    font-weight: 850;
}

.timetable-v3__module-group-panel-description {
    margin-top: 2px;
    color: #5f6b7d;
    font-size: 0.76rem;
}

.timetable-v3__module-group-panel-controls,
.timetable-v3__module-group-panel-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
    justify-content: flex-end;
}

.timetable-v3__module-group-panel-controls {
    margin-left: auto;
}

.timetable-v3__module-group-panel-count strong {
    color: var(--module-group-accent);
    font-size: 0.78rem;
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
    .timetable-v3__module-group-card,
    .timetable-v3__module-group-card-icon,
    .timetable-v3__module-group-card-active-mark,
    .timetable-v3__schedule-mode-card,
    .timetable-v3__module-tile,
    .timetable-v3__module-course,
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

.timetable-v3__module-without-student-hint {
    margin-top: 9px;
    color: #5b21b6;
    font-size: 0.72rem;
    font-weight: 650;
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

    .timetable-v3__schedule-mode-options {
        grid-template-columns: 1fr;
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

    .timetable-v3__module-group-panel-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .timetable-v3__module-group-panel-controls,
    .timetable-v3__module-group-panel-actions {
        justify-content: flex-start;
    }

    .timetable-v3__module-group-panel-controls {
        width: 100%;
        margin-left: 0;
    }

    .timetable-v3__module-grid {
        grid-template-columns: 1fr;
    }

    .timetable-v3__module-course-list {
        grid-template-columns: 1fr;
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

</style>
