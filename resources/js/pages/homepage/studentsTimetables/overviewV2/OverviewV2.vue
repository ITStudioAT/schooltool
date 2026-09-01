<template>
    <div class="lernportal-page students-timetables-overview-v2-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <div
            v-if="pageLoading"
            class="overview-v2-page-loader"
            aria-live="polite">
            <LoadingAnimation class="overview-v2-page-loader__dots" />
            <span>Stundenplan wird geladen …</span>
        </div>

        <template v-else>
            <StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="overview" />

            <section class="hero overview-v2-hero">
            <div class="overview-v2-panel">
                <nav class="overview-v2-navigation" aria-label="Seitennavigation">
                    <span class="overview-v2-wordmark">Schülerstundenpläne</span>

                    <div class="overview-v2-navigation__actions">
                        <v-btn
                            class="overview-v2-text-button overview-v2-logout-button"
                            variant="text"
                            prepend-icon="mdi-logout"
                            @click="handleLogout">
                            Abmelden
                        </v-btn>
                        <v-btn
                            class="overview-v2-menu-button"
                            variant="text"
                            icon="mdi-menu"
                            aria-label="Menü öffnen"
                            @click="showDrawer = true" />
                    </div>
                </nav>

                <template v-if="isTimetablePlanningPage">
                    <div
                        v-if="currentSelectionStudent"
                        class="overview-v2-current-selection overview-v2-current-selection--creation"
                        :class="{
                            'overview-v2-current-selection--results': !isTimetableCreationModePage,
                        }"
                        aria-label="Schülerdaten">
                        <span class="overview-v2-current-selection__icon">
                            <v-icon icon="mdi-account-check-outline" color="primary" size="28" />
                        </span>
                        <div class="overview-v2-current-selection__copy">
                            <div class="overview-v2-current-selection__value">
                                <span>{{ currentSelectionClass || '–' }} · {{ currentSelectionFullName }}</span>
                                <span
                                    v-if="currentSelectionReligion"
                                    class="overview-v2-current-selection__religion">
                                    · {{ currentSelectionReligion }}
                                </span>
                                <v-icon
                                    v-if="currentSelectionSexPresentation"
                                    class="overview-v2-current-selection__sex-icon"
                                    :icon="currentSelectionSexPresentation.icon"
                                    :color="currentSelectionSexPresentation.color"
                                    :title="currentSelectionSexPresentation.label"
                                    :aria-label="currentSelectionSexPresentation.label"
                                    size="28" />
                            </div>
                            <div v-if="currentSelectionEmail" class="overview-v2-current-selection__email-row">
                                <v-icon icon="mdi-email-outline" size="16" />
                                <span class="overview-v2-current-selection__email-address">{{ currentSelectionEmail }}</span>
                            </div>
                        </div>

                        <div class="overview-v2-student-info-actions">
                            <v-menu
                                open-on-hover
                                :open-on-click="false"
                                location="bottom end"
                                :open-delay="100"
                                :close-delay="150">
                                <template #activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        class="overview-v2-student-info-button"
                                        icon="mdi-information-outline"
                                        size="large"
                                        color="primary"
                                        variant="tonal"
                                        aria-label="Informationen zum Studierenden anzeigen"
                                        @click="openStudentInfoDialog" />
                                </template>

                                <v-card rounded="lg" class="overview-v2-info-hover-card" elevation="8">
                                    <v-card-title class="overview-v2-info-hover-title d-flex align-center ga-2">
                                        <v-icon icon="mdi-information-outline" color="primary" size="22" />
                                        Studierenden-Information
                                    </v-card-title>
                                    <v-card-text class="pa-4 pt-2">
                                        <div class="overview-v2-info-hover-grid">
                                            <div
                                                v-for="item in studentInfoHoverItems"
                                                :key="`creation-student-info-${item.key}`"
                                                class="overview-v2-info-hover-item">
                                                <span class="overview-v2-info-label">{{ item.label }}</span>
                                                <span class="overview-v2-info-value">
                                                    {{ item.value }}
                                                </span>
                                            </div>
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
                                        class="overview-v2-study-info-button"
                                        icon="mdi-school-outline"
                                        size="large"
                                        color="teal-darken-1"
                                        variant="tonal"
                                        aria-label="Informationen zum Studium anzeigen"
                                        @click="openStudyInfoDialog" />
                                </template>

                                <v-card rounded="lg" class="overview-v2-study-info-hover-card" elevation="8">
                                    <v-card-title class="overview-v2-study-info-hover-title d-flex align-center ga-2">
                                        <v-icon icon="mdi-school-outline" color="teal-darken-1" size="22" />
                                        Informationen zum Studium
                                    </v-card-title>
                                    <v-card-text class="overview-v2-study-info-hover-content pa-4 pt-2">
                                        <div class="overview-v2-study-module-groups">
                                            <section
                                                v-for="group in studentStudyModuleGroups"
                                                :key="`creation-student-study-hover-${group.key}`"
                                                class="overview-v2-study-module-group"
                                                :class="`overview-v2-study-module-group--${group.key}`"
                                                :aria-label="group.label">
                                                <div class="overview-v2-study-module-group-header">
                                                    <h3 class="overview-v2-study-module-group-title">{{ group.label }}</h3>
                                                    <div class="overview-v2-study-module-group-count">
                                                        {{ group.count }} {{ group.count === 1 ? 'Modul' : 'Module' }}
                                                    </div>
                                                </div>
                                                <div v-if="group.modules.length" class="overview-v2-study-module-list">
                                                    <div
                                                        v-for="module in group.modules"
                                                        :key="`creation-${module.code}-${module.grade}`"
                                                        class="overview-v2-study-module-row">
                                                        <span class="overview-v2-study-module-copy">
                                                            <span class="overview-v2-study-module-code">
                                                                {{ moduleDisplayCode(module) }}
                                                            </span>
                                                            <span
                                                                v-if="moduleDisplayNameVisible(module)"
                                                                class="overview-v2-study-module-name">
                                                                {{ moduleDisplayName(module) }}
                                                            </span>
                                                        </span>
                                                        <span class="overview-v2-study-module-grades">
                                                            <span
                                                                v-for="(grade, gradeIndex) in module.grades"
                                                                :key="`creation-${grade.status}-${grade.value}-${gradeIndex}`"
                                                                class="overview-v2-study-module-grade"
                                                                :class="`overview-v2-study-module-grade--${grade.status}`"
                                                                :aria-label="`Note ${grade.value}`">
                                                                {{ grade.value }}
                                                            </span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div v-else class="overview-v2-study-module-empty">Keine Module</div>
                                            </section>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-menu>
                        </div>
                    </div>
                </template>

                <section
                    v-if="
                        isTimetablePlanningPage && studentPlanningSelectionFields.length
                    "
                    class="overview-v2-compact-planning-card overview-v2-compact-planning-card--planning-readonly"
                    :class="{
                        'overview-v2-compact-planning-card--results-readonly': !isTimetableCreationModePage,
                    }"
                    aria-labelledby="overview-v2-planning-selection-title"
                    aria-readonly="true">
                    <div class="overview-v2-compact-planning-heading">
                        <v-icon icon="mdi-tune-variant" color="primary" size="22" />
                        <h3 id="overview-v2-planning-selection-title" class="overview-v2-compact-planning-title">
                            Studienauswahl
                        </h3>
                    </div>
                    <div class="overview-v2-compact-planning-grid">
                        <div
                            v-for="field in studentPlanningSelectionFields"
                            :key="`planning-study-selection-${field.key}`"
                            class="
                                overview-v2-compact-planning-item
                                overview-v2-compact-planning-item--readonly
                            ">
                            <span class="overview-v2-compact-planning-label">{{ field.label }}</span>
                            <span class="overview-v2-compact-planning-value">{{ field.value }}</span>
                        </div>
                    </div>
                </section>

                <div v-if="isTimetableCreationModePage" class="overview-v2-creation-mode-page">
                    <section
                        class="overview-v2-creation-mode-workspace"
                        aria-labelledby="overview-v2-creation-mode-title">
                        <div class="overview-v2-creation-mode-heading">
                            <span class="overview-v2-creation-mode-eyebrow">
                                {{ scheduleCreationMode === 'automatic' ? 'Modulauswahl' : 'Stundenplanerstellung' }}
                            </span>
                            <h1 id="overview-v2-creation-mode-title">
                                {{
                                    scheduleCreationMode === 'automatic'
                                        ? 'Welche Module sollen zur Stundenplanerstellung berücksichtigt werden?'
                                        : 'Soll der Stundenplan automatisch oder manuell erzeugt werden?'
                                }}
                            </h1>
                            <div
                                v-if="scheduleCreationMode === 'automatic'"
                                class="overview-v2-module-selected-total"
                                aria-live="polite">
                                <strong>{{ selectedModuleCount }}</strong>
                                {{ selectedModuleCount === 1 ? 'Modul ausgewählt' : 'Module ausgewählt' }}
                            </div>
                        </div>

                        <div
                            class="overview-v2-creation-mode-options"
                            :class="{
                                'overview-v2-creation-mode-options--automatic-selected': scheduleCreationMode === 'automatic',
                            }"
                            role="group"
                            aria-label="Art der Stundenplanerstellung">
                            <label
                                class="overview-v2-creation-mode-card overview-v2-creation-mode-card--automatic"
                                :class="{
                                    'overview-v2-creation-mode-card--selected': scheduleCreationMode === 'automatic',
                                }">
                                <input
                                    class="overview-v2-creation-mode-input"
                                    type="radio"
                                    name="overview-v2-creation-mode"
                                    value="automatic"
                                    :checked="scheduleCreationMode === 'automatic'"
                                    @change="chooseScheduleCreationMode('automatic')" />
                                <span class="overview-v2-creation-mode-icon">
                                    <v-icon icon="mdi-calendar-clock" size="30" />
                                </span>
                                <span class="overview-v2-creation-mode-copy">
                                    <span class="overview-v2-creation-mode-kickers">
                                        <span class="overview-v2-creation-mode-recommendation">
                                            <v-icon icon="mdi-star-four-points" size="14" />
                                            Empfohlen
                                        </span>
                                    </span>
                                    <span class="overview-v2-creation-mode-title">Automatischer Stundenplan</span>
                                    <span class="overview-v2-creation-mode-description">
                                        Du wählst die Module. Das System erstellt und optimiert daraus deinen Stundenplan.
                                    </span>
                                </span>
                                <span class="overview-v2-creation-mode-status">
                                    <v-icon
                                        :icon="scheduleCreationMode === 'automatic' ? 'mdi-check-circle' : 'mdi-circle-outline'"
                                        size="20" />
                                    {{ scheduleCreationMode === 'automatic' ? 'Ausgewählt' : 'Automatisch wählen' }}
                                </span>

                                <div
                                    v-if="scheduleCreationMode === 'automatic'"
                                    class="overview-v2-selected-modules overview-v2-creation-mode-selected-modules"
                                    aria-live="polite">
                                    <div class="overview-v2-selected-modules-heading">
                                        <span class="overview-v2-selected-modules-heading-label">
                                            <v-icon icon="mdi-check-circle-outline" size="18" />
                                            Ausgewählte Module
                                        </span>
                                        <span class="overview-v2-selected-modules-summary">
                                            {{ selectedModuleCount }}/{{ maximumSelectedModules }} Module
                                            · {{ selectedModuleHoursLabel }}/{{ maximumSelectedModuleHours }} Std.
                                        </span>
                                    </div>
                                    <div class="overview-v2-module-selection-limit-hint">
                                        <v-icon icon="mdi-information-outline" size="16" />
                                        Maximal {{ maximumSelectedModules }} Module und
                                        {{ maximumSelectedModuleHours }} Stunden gleichzeitig.
                                    </div>
                                    <v-alert
                                        v-if="moduleSelectionLimitMessage"
                                        class="overview-v2-module-selection-limit-alert"
                                        type="warning"
                                        density="compact"
                                        variant="tonal"
                                        closable
                                        @click:close="moduleSelectionLimitMessage = ''">
                                        {{ moduleSelectionLimitMessage }}
                                    </v-alert>
                                    <div v-if="selectedModules.length" class="overview-v2-selected-modules-list">
                                        <v-chip
                                            v-for="module in selectedModules"
                                            :key="module.selection_key"
                                            class="overview-v2-selected-module-chip"
                                            color="primary"
                                            closable
                                            close-icon="mdi-close-circle"
                                            :close-label="`${moduleDisplayCode(module)} aus der Auswahl entfernen`"
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
                                    <div v-else class="overview-v2-selected-modules-empty">
                                        Keine Module ausgewählt.
                                    </div>
                                    <div v-if="selectedModuleCount > 0" class="overview-v2-selected-modules-actions">
                                        <v-btn
                                            color="error"
                                            prepend-icon="mdi-checkbox-multiple-blank-outline"
                                            size="small"
                                            type="button"
                                            variant="tonal"
                                            @click.prevent.stop="deselectAllSelectedModules">
                                            Alle abwählen
                                        </v-btn>
                                        <v-btn
                                            class="overview-v2-schedule-create-button"
                                            color="primary"
                                            prepend-icon="mdi-calendar-check"
                                            append-icon="mdi-arrow-right"
                                            elevation="8"
                                            height="52"
                                            :disabled="timetableCalculationStatus === 'calculating'"
                                            :loading="timetableCalculationStatus === 'calculating'"
                                            rounded="lg"
                                            size="large"
                                            type="button"
                                            variant="elevated"
                                            @click.prevent.stop="openAutomaticTimetableResults">
                                            Stundenplan erstellen
                                        </v-btn>
                                    </div>
                                </div>
                            </label>

                            <button
                                class="overview-v2-creation-mode-card overview-v2-creation-mode-card--manual"
                                type="button"
                                disabled>
                                <span class="overview-v2-creation-mode-icon">
                                    <v-icon icon="mdi-calendar-edit" size="30" />
                                </span>
                                <span class="overview-v2-creation-mode-copy">
                                    <span class="overview-v2-creation-mode-title">Manueller Stundenplan</span>
                                    <span class="overview-v2-creation-mode-description">
                                        Du stellst deinen Stundenplan selbst zusammen und platzierst die Unterrichte manuell.
                                    </span>
                                </span>
                                <span class="overview-v2-creation-mode-status">
                                    <v-icon icon="mdi-arrow-right-circle-outline" size="20" />
                                    Manuell öffnen
                                </span>
                            </button>
                        </div>

                        <div
                            v-if="scheduleCreationMode === 'automatic'"
                            class="overview-v2-module-group-cards"
                            aria-label="Modularten">
                            <button
                                v-for="group in moduleSelectionGroups"
                                :key="group.key"
                                type="button"
                                class="overview-v2-module-group-card"
                                :class="[
                                    `overview-v2-module-group-card--${group.key}`,
                                    { 'overview-v2-module-group-card--active': moduleGroupActive(group) },
                                ]"
                                :aria-pressed="moduleGroupActive(group)"
                                aria-controls="overview-v2-module-group-panel"
                                @click="toggleModuleGroup(group)">
                                <span class="overview-v2-module-group-card-topline">
                                    <span class="overview-v2-module-group-card-icon">
                                        <v-icon :icon="moduleGroupIcon(group)" size="19" />
                                    </span>
                                    <span class="overview-v2-module-group-card-count">
                                        {{ selectedModuleCountForGroup(group) }}/{{ group.count }}
                                    </span>
                                </span>
                                <span class="overview-v2-module-group-card-title">{{ group.label }}</span>
                                <span class="overview-v2-module-group-card-description">{{ group.description }}</span>
                                <span class="overview-v2-module-group-card-active-mark" aria-hidden="true" />
                            </button>
                        </div>

                        <transition
                            v-if="scheduleCreationMode === 'automatic'"
                            name="overview-v2-module-panel"
                            mode="out-in">
                            <section
                                v-if="activeModuleSelectionGroup"
                                :key="activeModuleSelectionGroup.key"
                                id="overview-v2-module-group-panel"
                                class="overview-v2-module-group-panel"
                                :class="`overview-v2-module-group-panel--${activeModuleSelectionGroup.key}`">
                                <div class="overview-v2-module-group-panel-actions">
                                    <v-btn
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        prepend-icon="mdi-checkbox-multiple-marked-outline"
                                        :disabled="allModulesSelectedForGroup(activeModuleSelectionGroup)"
                                        @click="selectAllModulesInGroup(activeModuleSelectionGroup)">
                                        Alle auswählen
                                    </v-btn>
                                    <v-btn
                                        size="small"
                                        variant="text"
                                        color="error"
                                        prepend-icon="mdi-checkbox-multiple-blank-outline"
                                        :disabled="!hasSelectedModulesForGroup(activeModuleSelectionGroup)"
                                        @click="deselectAllModulesInGroup(activeModuleSelectionGroup)">
                                        Alle abwählen
                                    </v-btn>
                                </div>

                                <div
                                    v-if="activeModuleSelectionGroup.modules.length"
                                    class="overview-v2-module-grid">
                                    <button
                                        v-for="module in activeModuleSelectionGroup.modules"
                                        :key="module.selection_key"
                                        type="button"
                                        class="overview-v2-module-tile"
                                        :class="{ 'overview-v2-module-tile--selected': moduleSelected(module) }"
                                        :aria-pressed="moduleSelected(module)"
                                        @click="openModuleCoursesDialog(module)">
                                        <span class="overview-v2-module-check">
                                            <v-icon
                                                :icon="moduleSelected(module) ? 'mdi-check' : 'mdi-chevron-right'"
                                                size="16" />
                                        </span>
                                        <span class="overview-v2-module-main">
                                            <span class="overview-v2-module-code">{{ moduleDisplayCode(module) }}</span>
                                            <span
                                                v-if="moduleDisplayNameVisible(module)"
                                                class="overview-v2-module-name">
                                                {{ moduleDisplayName(module) }}
                                            </span>
                                            <span class="overview-v2-module-meta">
                                                <span v-if="module.status_label" class="overview-v2-module-status">
                                                    {{ module.status_label }}
                                                </span>
                                                <span v-if="module.semester_label">{{ module.semester_label }}</span>
                                                <span v-if="module.hours_label">{{ module.hours_label }}</span>
                                                <span class="overview-v2-module-course-count">
                                                    {{ selectedCourseCountForModule(module) }}/{{ moduleCourseCount(module) }}
                                                    Unterrichte
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                </div>
                                <div v-else class="overview-v2-module-list-empty">Keine Module vorhanden.</div>
                            </section>
                        </transition>
                    </section>

                    <div class="overview-v2-creation-mode-actions overview-v2-creation-mode-actions--split">
                        <v-btn
                            class="overview-v2-creation-mode-restart"
                            size="large"
                            color="error"
                            variant="outlined"
                            prepend-icon="mdi-restart"
                            :disabled="timetableCalculationStatus === 'calculating' || timetablePageLoading"
                            @click="restartStudentTimetablePlanning">
                            Neustart
                        </v-btn>
                        <v-btn
                            class="overview-v2-creation-mode-back"
                            size="large"
                            color="primary"
                            variant="outlined"
                            prepend-icon="mdi-arrow-left"
                            @click="goBackToOverview">
                            Zurück
                        </v-btn>
                    </div>
                </div>

                <div v-else-if="isTimetableResultsPage" class="overview-v2-results-page">
                    <div class="timetable-v3__creation-summary-cards">
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
                                    Du wählst die Module. Das System erstellt und optimiert daraus deinen Stundenplan.
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
                                    <h3 id="timetable-v3-student-calculation-title">{{ timetableCalculationHeading }}</h3>
                                </div>

                                <div
                                    v-if="timetableCalculationStatus === 'calculating'"
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
                                            :class="{
                                                'timetable-v3__calculation-led-segment--active': segment.active,
                                            }"
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
                                        v-if="selectedModuleKeys.length && selectedCourseKeys.length"
                                        color="teal-darken-1"
                                        prepend-icon="mdi-refresh"
                                        variant="tonal"
                                        @click="calculateStudentTimetables">
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
                                            :icon="possibleTimetableCount > 0
                                                ? 'mdi-check-decagram-outline'
                                                : 'mdi-calendar-remove-outline'"
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
                                            Insgesamt wurden
                                            <strong>{{ unfilteredMaterializedTimetableCountLabel }}</strong> von
                                            <strong>{{ totalPossibleTimetableCountLabel }}</strong> möglichen
                                            Stundenplänen gespeichert. Die Optionen verändern diesen Bestand nicht.
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
                                    Du kannst jetzt den aktuell ausgewählten Stundenplan übernehmen, um ihn noch
                                    weiter individuell anzupassen.
                                </span>
                            </span>
                            <v-btn
                                class="timetable-v3__manual-timetable-button"
                                block
                                color="#c2410c"
                                :disabled="
                                    timetableCalculationStatus !== 'success' ||
                                    timetablePageLoading ||
                                    !selectedTimetableResult
                                "
                                prepend-icon="mdi-calendar-import"
                                size="large"
                                type="button"
                                variant="elevated"
                                @click="openStudentTimetableAdoption">
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
                            aria-labelledby="timetable-v3-student-options-title">
                            <span class="timetable-v3__schedule-mode-icon">
                                <v-icon icon="mdi-tune-variant" size="30" />
                            </span>
                            <span class="timetable-v3__schedule-mode-copy">
                                <span id="timetable-v3-student-options-title" class="timetable-v3__schedule-mode-title">
                                    Optionen
                                </span>
                            </span>
                            <div class="timetable-v3__filter-options">
                                <div class="timetable-v3__filter-option">
                                    <span id="timetable-v3-student-saturday-filter-label" class="timetable-v3__filter-option-label">
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
                                        :disabled="timetableCalculationStatus === 'calculating' || timetablePageLoading"
                                        aria-labelledby="timetable-v3-student-saturday-filter-label"
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
                                    v-if="timetableCalculationStatus === 'success' && timetableHasVisibleFreeDayOptions"
                                    class="timetable-v3__filter-option">
                                    <span id="timetable-v3-student-free-days-filter-label" class="timetable-v3__filter-option-label">
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
                                        :disabled="timetablePageLoading"
                                        aria-labelledby="timetable-v3-student-free-days-filter-label"
                                        @update:model-value="updateTimetableFilter('free_days', $event)">
                                        <v-btn
                                            v-if="timetableFreeDayOptionCounts.any > 0"
                                            :value="null">
                                            <span class="timetable-v3__filter-option-button-content">
                                                <span>Egal</span>
                                                <span v-if="!timetablePageLoading" class="timetable-v3__filter-option-count">
                                                    {{ timetableVariantCountLabel(timetableFreeDayOptionCounts.any) }}
                                                </span>
                                            </span>
                                        </v-btn>
                                        <v-btn
                                            v-for="option in timetableVisibleFreeDayOptions"
                                            :key="option.value"
                                            :value="option.value">
                                            <span class="timetable-v3__filter-option-button-content">
                                                <span>{{ option.value }}</span>
                                                <span v-if="!timetablePageLoading" class="timetable-v3__filter-option-count">
                                                    {{ timetableVariantCountLabel(option.count) }}
                                                </span>
                                            </span>
                                        </v-btn>
                                    </v-btn-toggle>
                                </div>

                                <div
                                    v-if="timetablePageLoading"
                                    class="timetable-v3__filter-loading"
                                    aria-atomic="true"
                                    aria-live="polite"
                                    role="status">
                                    <v-progress-circular color="teal-darken-1" indeterminate :size="22" :width="3" />
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

                        <div
                            v-else-if="timetableCalculationStatus === 'success'"
                            class="timetable-v3__calculation-output overview-v2-results-empty"
                            role="status">
                            <v-icon icon="mdi-calendar-remove-outline" size="36" />
                            <div>
                                <strong>Keine passenden Stundenpläne gefunden.</strong>
                                <span>Gehe zurück und passe deine Modul- oder Unterrichtsauswahl an.</span>
                            </div>
                        </div>
                    </div>

                    <div class="overview-v2-creation-mode-actions overview-v2-creation-mode-actions--split">
                        <v-btn
                            class="overview-v2-creation-mode-restart"
                            size="large"
                            color="error"
                            variant="outlined"
                            prepend-icon="mdi-restart"
                            :disabled="timetableCalculationStatus === 'calculating' || timetablePageLoading"
                            @click="restartStudentTimetablePlanning">
                            Neustart
                        </v-btn>
                        <v-btn
                            class="overview-v2-creation-mode-back"
                            size="large"
                            color="primary"
                            variant="outlined"
                            prepend-icon="mdi-arrow-left"
                            :disabled="timetableCalculationStatus === 'calculating'"
                            @click="goBackToModuleSelection">
                            Zurück
                        </v-btn>
                    </div>
                </div>

                <div v-else-if="isTimetableAdoptionPage" class="overview-v2-adoption-page">
                    <div class="timetable-v3__adoption-cards">
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
                                    <span class="timetable-v3__adoption-title-copy">
                                        <span class="timetable-v3__adoption-title">
                                            <span class="timetable-v3__schedule-mode-title">Manueller Stundenplan</span>
                                            <v-chip
                                                v-if="publishedTimetableAdoptionName"
                                                class="timetable-v3__published-timetable-name"
                                                color="#c2410c"
                                                label
                                                size="small"
                                                variant="tonal">
                                                Nr. {{ publishedTimetableAdoptionName }}
                                            </v-chip>
                                        </span>
                                        <span
                                            v-if="personalTimetableAdoptionSavedAtLabel"
                                            class="timetable-v3__adoption-saved-at">
                                            Zuletzt gespeichert: {{ personalTimetableAdoptionSavedAtLabel }} Uhr
                                        </span>
                                    </span>
                                    <span class="timetable-v3__adoption-actions">
                                        <v-btn
                                            class="timetable-v3__adoption-pdf-button"
                                            color="#c2410c"
                                            :disabled="pdfExporting || !adoptionDisplayedTimetable"
                                            :loading="pdfExporting"
                                            prepend-icon="mdi-file-pdf-box"
                                            size="large"
                                            type="button"
                                            variant="outlined"
                                            @click="downloadManualTimetablePdf">
                                            PDF
                                        </v-btn>
                                        <v-btn
                                            class="timetable-v3__adoption-save-button"
                                            color="success"
                                            :disabled="personalTimetableSaving || !adoptionDisplayedTimetable"
                                            :loading="personalTimetableSaving"
                                            prepend-icon="mdi-content-save-outline"
                                            size="large"
                                            type="button"
                                            variant="flat"
                                            @click="savePersonalTimetable">
                                            Speichern
                                        </v-btn>
                                    </span>
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
                                        · {{ adoptionSelectedModuleHoursLabel }} Std.
                                    </span>
                                </div>
                                <div v-if="adoptionSelectedModules.length" class="timetable-v3__selected-modules-list">
                                    <v-chip
                                        v-for="module in adoptionSelectedModules"
                                        :key="`adoption-${module.selection_key || module.code}`"
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

                    <section class="overview-v2-manual-module-catalog" aria-label="Modulkatalog">
                        <div class="overview-v2-manual-module-catalog-headings">
                            <button
                                type="button"
                                class="overview-v2-manual-module-catalog-heading"
                                :class="{
                                    'overview-v2-manual-module-catalog-heading--selected': manualModuleCatalogView === 'student',
                                }"
                                :aria-pressed="manualModuleCatalogView === 'student'"
                                @click="showManualModuleCatalog('student')">
                                <span class="overview-v2-manual-module-catalog-heading-icon">
                                    <v-icon icon="mdi-bookshelf" size="21" />
                                </span>
                                <span>
                                    <strong>Studierenden Module</strong>
                                    <small>Die mit dir verbundenen Module stehen bereit.</small>
                                </span>
                            </button>
                            <button
                                type="button"
                                class="overview-v2-manual-module-catalog-heading"
                                :class="{
                                    'overview-v2-manual-module-catalog-heading--selected': manualModuleCatalogView === 'main',
                                }"
                                :aria-pressed="manualModuleCatalogView === 'main'"
                                @click="showManualModuleCatalog('main')">
                                <span class="overview-v2-manual-module-catalog-heading-icon">
                                    <v-icon icon="mdi-bookshelf" size="21" />
                                </span>
                                <span>
                                    <strong>Alle Module</strong>
                                    <small>Alle verfügbaren Module und Unterrichte stehen bereit.</small>
                                </span>
                            </button>
                        </div>

                        <div
                            class="overview-v2-module-group-cards"
                            :class="{ 'overview-v2-module-group-cards--main': manualModuleCatalogUsesMainGroups }">
                            <button
                                v-for="group in visibleManualModuleCatalogGroups"
                                :key="`manual-${manualModuleCatalogView}-${group.key}`"
                                type="button"
                                class="overview-v2-module-group-card"
                                :class="[
                                    manualModuleCatalogUsesMainGroups
                                        ? 'overview-v2-module-group-card--main'
                                        : `overview-v2-module-group-card--${group.key}`,
                                    { 'overview-v2-module-group-card--active': manualModuleGroupActive(group) },
                                ]"
                                :aria-expanded="manualModuleGroupActive(group)"
                                aria-controls="overview-v2-manual-module-group-panel"
                                @click="toggleManualModuleGroup(group)">
                                <span class="overview-v2-module-group-card-topline">
                                    <span class="overview-v2-module-group-card-icon">
                                        <v-icon :icon="moduleGroupIcon(group)" size="19" />
                                    </span>
                                    <span class="overview-v2-module-group-card-count">
                                        {{ manualSelectedModuleCountForGroup(group) }}/{{ group.count }}
                                    </span>
                                </span>
                                <span v-if="manualModuleGroupAlreadyPlanned(group)" class="overview-v2-module-planned">
                                    Bereits verplant!
                                </span>
                                <span
                                    v-if="manualModuleCatalogUsesMainGroups && manualModuleGroupNotIntended(group)"
                                    class="overview-v2-module-not-intended">
                                    Nicht vorgesehen!
                                </span>
                                <span class="overview-v2-module-group-card-title">
                                    <template v-if="manualModuleCatalogUsesMainGroups">
                                        <strong>{{ moduleDisplayCode(group) }}</strong>
                                        <span v-if="moduleDisplayNameVisible(group)">
                                            &nbsp;· {{ moduleDisplayName(group) }}
                                        </span>
                                    </template>
                                    <template v-else>{{ group.label }}</template>
                                </span>
                                <span class="overview-v2-module-group-card-description">{{ group.description }}</span>
                                <span class="overview-v2-module-group-card-active-mark" aria-hidden="true" />
                            </button>
                        </div>

                        <transition name="overview-v2-module-panel" mode="out-in">
                            <section
                                v-if="activeManualModuleSelectionGroup"
                                :key="`manual-${manualModuleCatalogView}-${activeManualModuleSelectionGroup.key}`"
                                id="overview-v2-manual-module-group-panel"
                                class="overview-v2-module-group-panel"
                                :class="manualModuleCatalogUsesMainGroups
                                    ? 'overview-v2-module-group-panel--main'
                                    : `overview-v2-module-group-panel--${activeManualModuleSelectionGroup.key}`">
                                <div
                                    v-if="activeManualModuleSelectionGroup.modules.length"
                                    class="overview-v2-module-grid">
                                    <button
                                        v-for="module in activeManualModuleSelectionGroup.modules"
                                        :key="`manual-${manualModuleCatalogView}-${module.selection_key}`"
                                        type="button"
                                        class="overview-v2-module-tile"
                                        @click="openManualModuleCoursesDialog(module)">
                                        <span class="overview-v2-module-check">
                                            <v-icon icon="mdi-chevron-right" size="16" />
                                        </span>
                                        <span class="overview-v2-module-main">
                                            <span
                                                v-if="manualModuleCatalogUsesMainGroups
                                                    && module.is_intended_for_selection === false"
                                                class="overview-v2-module-not-intended">
                                                Nicht vorgesehen!
                                            </span>
                                            <span v-if="manualModuleAlreadyPlanned(module)" class="overview-v2-module-planned">
                                                Bereits verplant!
                                            </span>
                                            <span class="overview-v2-module-code">{{ moduleDisplayCode(module) }}</span>
                                            <span v-if="moduleDisplayNameVisible(module)" class="overview-v2-module-name">
                                                {{ moduleDisplayName(module) }}
                                            </span>
                                            <span class="overview-v2-module-meta">
                                                <span v-if="module.status_label" class="overview-v2-module-status">
                                                    {{ module.status_label }}
                                                </span>
                                                <span v-if="module.semester_label">{{ module.semester_label }}</span>
                                                <span v-if="module.hours_label">{{ module.hours_label }}</span>
                                                <span class="overview-v2-module-course-count">
                                                    {{ manualModuleCourseCount(module) }} Unterrichte
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                </div>
                                <div v-else class="overview-v2-module-list-empty">Keine Module vorhanden.</div>
                            </section>
                        </transition>
                    </section>

                    <TimetableV3PossibleTimetables
                        v-if="adoptionDisplayedTimetable"
                        class="overview-v2-adoption-timetable"
                        :allow-saturday-lessons="true"
                        highlight-multiple-entries
                        :navigation-visible="false"
                        :page-offset="0"
                        :position-visible="false"
                        show-all-hours
                        :selected-index="0"
                        :timetables="[adoptionDisplayedTimetable]"
                        :total-count="1" />

                    <div class="overview-v2-creation-mode-actions overview-v2-creation-mode-actions--split">
                        <v-btn
                            class="overview-v2-creation-mode-restart"
                            size="large"
                            color="error"
                            variant="outlined"
                            prepend-icon="mdi-restart"
                            :disabled="timetableCalculationStatus === 'calculating' || timetablePageLoading"
                            @click="restartStudentTimetablePlanning">
                            Neustart
                        </v-btn>
                        <v-btn
                            class="overview-v2-creation-mode-back"
                            size="large"
                            color="primary"
                            variant="outlined"
                            prepend-icon="mdi-arrow-left"
                            :disabled="timetableCalculationStatus === 'calculating'"
                            @click="goBackToTimetableResults">
                            Zurück
                        </v-btn>
                    </div>
                </div>

                <div v-else class="overview-v2-introduction">
                    <div class="overview-v2-introduction__copy">
                        <span class="overview-v2-kicker">Mein Stundenplan</span>
                        <h1>Willkommen<span v-if="user?.first_name">, {{ user.first_name }}</span>.</h1>
                    </div>

                    <div
                        v-if="currentSelectionStudent"
                        class="overview-v2-current-selection"
                        aria-label="Schülerdaten">
                        <span class="overview-v2-current-selection__icon">
                            <v-icon icon="mdi-account-check-outline" color="primary" size="28" />
                        </span>
                        <div class="overview-v2-current-selection__copy">
                            <div class="overview-v2-current-selection__value">
                                <span>{{ currentSelectionClass || '–' }} · {{ currentSelectionFullName }}</span>
                                <span
                                    v-if="currentSelectionReligion"
                                    class="overview-v2-current-selection__religion">
                                    · {{ currentSelectionReligion }}
                                </span>
                                <v-icon
                                    v-if="currentSelectionSexPresentation"
                                    class="overview-v2-current-selection__sex-icon"
                                    :icon="currentSelectionSexPresentation.icon"
                                    :color="currentSelectionSexPresentation.color"
                                    :title="currentSelectionSexPresentation.label"
                                    :aria-label="currentSelectionSexPresentation.label"
                                    size="28" />
                            </div>
                            <div v-if="currentSelectionEmail" class="overview-v2-current-selection__email-row">
                                <v-icon icon="mdi-email-outline" size="16" />
                                <span class="overview-v2-current-selection__email-address">{{ currentSelectionEmail }}</span>
                            </div>
                        </div>

                        <div class="overview-v2-student-info-actions">
                            <v-menu
                                open-on-hover
                                :open-on-click="false"
                                location="bottom end"
                                :open-delay="100"
                                :close-delay="150">
                                <template #activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        class="overview-v2-student-info-button"
                                        icon="mdi-information-outline"
                                        size="large"
                                        color="primary"
                                        variant="tonal"
                                        aria-label="Informationen zum Studierenden anzeigen"
                                        @click="openStudentInfoDialog" />
                                </template>

                                <v-card rounded="lg" class="overview-v2-info-hover-card" elevation="8">
                                    <v-card-title class="overview-v2-info-hover-title d-flex align-center ga-2">
                                        <v-icon icon="mdi-information-outline" color="primary" size="22" />
                                        Studierenden-Information
                                    </v-card-title>
                                    <v-card-text class="pa-4 pt-2">
                                        <div class="overview-v2-info-hover-grid">
                                            <div
                                                v-for="item in studentInfoHoverItems"
                                                :key="`student-info-${item.key}`"
                                                class="overview-v2-info-hover-item">
                                                <span class="overview-v2-info-label">{{ item.label }}</span>
                                                <span class="overview-v2-info-value">
                                                    {{ item.value }}
                                                </span>
                                            </div>
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
                                        class="overview-v2-study-info-button"
                                        icon="mdi-school-outline"
                                        size="large"
                                        color="teal-darken-1"
                                        variant="tonal"
                                        aria-label="Informationen zum Studium anzeigen"
                                        @click="openStudyInfoDialog" />
                                </template>

                                <v-card rounded="lg" class="overview-v2-study-info-hover-card" elevation="8">
                                    <v-card-title class="overview-v2-study-info-hover-title d-flex align-center ga-2">
                                        <v-icon icon="mdi-school-outline" color="teal-darken-1" size="22" />
                                        Informationen zum Studium
                                    </v-card-title>
                                    <v-card-text class="overview-v2-study-info-hover-content pa-4 pt-2">
                                        <div class="overview-v2-study-module-groups">
                                            <section
                                                v-for="group in studentStudyModuleGroups"
                                                :key="`student-study-hover-${group.key}`"
                                                class="overview-v2-study-module-group"
                                                :class="`overview-v2-study-module-group--${group.key}`"
                                                :aria-label="group.label">
                                                <div class="overview-v2-study-module-group-header">
                                                    <h3 class="overview-v2-study-module-group-title">{{ group.label }}</h3>
                                                    <div class="overview-v2-study-module-group-count">
                                                        {{ group.count }} {{ group.count === 1 ? 'Modul' : 'Module' }}
                                                    </div>
                                                </div>
                                                <div v-if="group.modules.length" class="overview-v2-study-module-list">
                                                    <div
                                                        v-for="module in group.modules"
                                                        :key="`${module.code}-${module.grade}`"
                                                        class="overview-v2-study-module-row">
                                                        <span class="overview-v2-study-module-copy">
                                                            <span class="overview-v2-study-module-code">{{ moduleDisplayCode(module) }}</span>
                                                            <span
                                                                v-if="moduleDisplayNameVisible(module)"
                                                                class="overview-v2-study-module-name">
                                                                {{ moduleDisplayName(module) }}
                                                            </span>
                                                        </span>
                                                        <span class="overview-v2-study-module-grades">
                                                            <span
                                                                v-for="(grade, gradeIndex) in module.grades"
                                                                :key="`${grade.status}-${grade.value}-${gradeIndex}`"
                                                                class="overview-v2-study-module-grade"
                                                                :class="`overview-v2-study-module-grade--${grade.status}`"
                                                                :aria-label="`Note ${grade.value}`">
                                                                {{ grade.value }}
                                                            </span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div v-else class="overview-v2-study-module-empty">Keine Module</div>
                                            </section>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-menu>
                        </div>
                    </div>

                    <section
                        v-if="studentPlanningSelectionFields.length"
                        class="overview-v2-compact-planning-card"
                        aria-labelledby="overview-v2-compact-planning-title">
                        <div class="overview-v2-compact-planning-heading">
                            <v-icon icon="mdi-tune-variant" color="primary" size="22" />
                            <h3 id="overview-v2-compact-planning-title" class="overview-v2-compact-planning-title">
                                Studienauswahl
                            </h3>
                            <v-spacer />
                            <v-btn
                                class="overview-v2-compact-planning-reset"
                                prepend-icon="mdi-restore"
                                variant="text"
                                color="primary"
                                density="comfortable"
                                size="small"
                                :disabled="!hasStudentSelectionOverride || studentSelectionSaving"
                                @click="restoreStudentSelectionDefaults">
                                Zurücksetzen
                            </v-btn>
                        </div>
                        <div class="overview-v2-compact-planning-grid">
                            <button
                                v-for="field in studentPlanningSelectionFields"
                                :key="`study-selection-${field.key}`"
                                type="button"
                                class="overview-v2-compact-planning-item"
                                :class="{
                                    'overview-v2-compact-planning-item--overridden': field.overridden,
                                }"
                                :disabled="studentSelectionSaving"
                                :aria-label="`${field.label} bearbeiten`"
                                @click="openStudentSelectionDialog(field)">
                                <span class="overview-v2-compact-planning-item-heading">
                                    <span class="overview-v2-compact-planning-label">{{ field.label }}</span>
                                    <v-icon icon="mdi-pencil" color="primary" size="17" />
                                </span>
                                <span class="overview-v2-compact-planning-value">{{ field.value }}</span>
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="overview"
                        class="overview-v2-timetable-start"
                        aria-labelledby="overview-v2-timetable-start-title">
                        <div class="overview-v2-timetable-start-heading">
                            <span class="overview-v2-timetable-start-eyebrow">Stundenplanerstellung</span>
                            <h2 id="overview-v2-timetable-start-title">Wie möchtest du beginnen?</h2>
                        </div>

                        <div
                            class="overview-v2-timetable-start-options"
                            role="group"
                            aria-label="Ausgangspunkt für die Stundenplanerstellung">
                            <button
                                v-for="option in timetableStartOptions"
                                :key="`timetable-start-${option.key}`"
                                type="button"
                                class="overview-v2-timetable-start-card"
                                :class="`overview-v2-timetable-start-card--${option.key}`"
                                :disabled="!option.available"
                                :aria-label="option.available
                                    ? `${option.title}${option.timetableName
                                        ? `, Nummer ${option.timetableName}`
                                        : ''}: ${option.actionLabel}`
                                    : `${option.title}: nicht verfügbar`"
                                @click="startTimetableFrom(option.key)">
                                <span class="overview-v2-timetable-start-icon">
                                    <v-icon :icon="option.icon" size="30" />
                                </span>
                                <span class="overview-v2-timetable-start-copy">
                                    <span class="overview-v2-timetable-start-title">{{ option.title }}</span>
                                    <span class="overview-v2-timetable-start-description">{{ option.description }}</span>
                                    <span class="overview-v2-timetable-start-availability">
                                        {{ option.availabilityLabel }}
                                    </span>
                                </span>
                                <span class="overview-v2-timetable-start-action">
                                    <v-icon
                                        :icon="option.available ? 'mdi-arrow-right-circle-outline' : 'mdi-lock-outline'"
                                        size="20" />
                                    {{ option.available ? option.actionLabel : 'Nicht verfügbar' }}
                                </span>
                            </button>
                        </div>
                    </section>
                </div>
            </div>
            </section>

            <v-dialog v-model="studentSelectionDialogOpen" max-width="460" persistent>
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-tune-variant" color="primary" />
                    {{ studentSelectionDraftLabel }} bearbeiten
                </v-card-title>
                <v-card-text class="px-5 pt-4">
                    <p class="overview-v2-selection-dialog-hint">
                        Wähle die passende Option aus.
                    </p>
                    <v-chip-group
                        v-model="studentSelectionDraftValue"
                        class="overview-v2-selection-options"
                        color="primary"
                        selected-class="overview-v2-selection-option--selected"
                        column
                        filter
                        :disabled="studentSelectionSaving"
                        :aria-label="studentSelectionDraftLabel">
                        <v-chip
                            v-for="option in studentSelectionDraftOptions"
                            :key="option.value"
                            :value="option.value"
                            class="overview-v2-selection-option"
                            label
                            size="large"
                            variant="outlined">
                            {{ option.title }}
                        </v-chip>
                    </v-chip-group>
                </v-card-text>
                <v-card-actions class="px-5 pb-5 pt-4">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        :disabled="studentSelectionSaving"
                        @click="closeStudentSelectionDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        :loading="studentSelectionSaving"
                        :disabled="!studentSelectionDraftValid"
                        @click="saveStudentSelection">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
            </v-dialog>

            <v-dialog v-model="moduleCoursesDialogOpen" max-width="820" persistent scrollable>
            <v-card rounded="lg" class="overview-v2-module-courses-dialog">
                <v-card-title class="overview-v2-module-courses-dialog-title d-flex align-center ga-3 pa-5 pb-2">
                    <span class="overview-v2-module-courses-dialog-icon">
                        <v-icon icon="mdi-book-open-variant-outline" size="23" />
                    </span>
                    <span>Unterrichte für {{ moduleDisplayCode(moduleCourseDialogModule) || 'Modul' }}</span>
                </v-card-title>

                <v-card-text class="px-5 pt-3">
                    <div
                        v-if="moduleDisplayNameVisible(moduleCourseDialogModule)"
                        class="overview-v2-module-courses-dialog-subtitle">
                        {{ moduleDisplayName(moduleCourseDialogModule) }}
                    </div>
                    <div v-if="!moduleCoursesDialogManual" class="overview-v2-module-courses-dialog-toolbar">
                        <div class="overview-v2-module-courses-dialog-summary" aria-live="polite">
                            <strong>{{ selectedModuleCourseCount }}</strong>
                            von {{ moduleCourseDialogCourses.length }} Unterrichten ausgewählt
                        </div>
                        <div class="overview-v2-module-courses-dialog-actions">
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
                        v-if="moduleSelectionLimitMessage"
                        class="mt-3"
                        type="warning"
                        density="compact"
                        variant="tonal"
                        closable
                        @click:close="moduleSelectionLimitMessage = ''">
                        {{ moduleSelectionLimitMessage }}
                    </v-alert>

                    <div v-if="moduleCourseDialogCourses.length" class="overview-v2-module-course-list mt-4">
                        <button
                            v-for="course in moduleCourseDialogCourses"
                            :key="course.key"
                            type="button"
                            class="overview-v2-module-course"
                            :class="{
                                'overview-v2-module-course--planned': moduleCourseAlreadyPlanned(course),
                                'overview-v2-module-course--selected': displayedModuleCourseSelected(course),
                            }"
                            :disabled="moduleCourseAlreadyPlanned(course)"
                            role="checkbox"
                            :aria-checked="moduleCourseAlreadyPlanned(course) || displayedModuleCourseSelected(course)"
                            :aria-disabled="moduleCourseAlreadyPlanned(course) ? 'true' : null"
                            @click="!moduleCourseAlreadyPlanned(course) && toggleDisplayedModuleCourse(course)">
                            <span class="overview-v2-module-course-check">
                                <v-icon
                                    :icon="moduleCourseAlreadyPlanned(course)
                                        ? 'mdi-check-circle'
                                        : displayedModuleCourseSelected(course)
                                            ? 'mdi-check'
                                            : 'mdi-checkbox-blank-outline'"
                                    size="18" />
                            </span>
                            <span class="overview-v2-module-course-copy">
                                <span class="overview-v2-module-course-title">{{ moduleCourseTitle(course) }}</span>
                                <span
                                    v-if="moduleCourseAlreadyPlanned(course)"
                                    class="overview-v2-module-course-planned">
                                    Verplant
                                </span>
                                <span
                                    v-if="moduleCourseSubtitle(course)"
                                    class="overview-v2-module-course-subtitle">
                                    {{ moduleCourseSubtitle(course) }}
                                </span>
                                <span class="overview-v2-module-course-meta">
                                    <template
                                        v-for="scheduleRow in courseScheduleRows(course)"
                                        :key="scheduleRow.key">
                                        <span class="overview-v2-module-course-schedule">
                                            {{ scheduleRow.label }}
                                        </span>
                                        <span
                                            v-for="overlapLabel in courseScheduleRowOverlapLabels(course, scheduleRow)"
                                            :key="`${scheduleRow.key}:${overlapLabel}`"
                                            class="overview-v2-module-course-overlap">
                                            ({{ overlapLabel }})
                                        </span>
                                    </template>
                                    <span v-if="course.hours_label" class="overview-v2-module-course-hours">
                                        <v-icon icon="mdi-clock-outline" size="12" />
                                        {{ course.hours_label }}
                                    </span>
                                    <span
                                        v-if="course.instruction_label"
                                        class="overview-v2-module-course-instruction">
                                        <v-icon icon="mdi-laptop" size="12" />
                                        {{ course.instruction_label }}
                                    </span>
                                    <span v-if="course.block_label">{{ course.block_label }}</span>
                                    <span v-if="course.dates_count">
                                        {{ course.dates_count }} {{ course.dates_count === 1 ? 'Termin' : 'Termine' }}
                                    </span>
                                </span>
                            </span>
                        </button>
                    </div>
                    <div v-else class="overview-v2-module-course-empty mt-4">
                        Für dieses Modul sind keine Unterrichte im importierten Stundenplan vorhanden.
                    </div>
                </v-card-text>

                <v-card-actions class="px-5 pb-5 pt-3">
                    <v-spacer />
                    <template v-if="moduleCoursesDialogManual">
                        <v-btn color="primary" variant="text" size="large" @click="closeModuleCoursesDialog">
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
            <v-card rounded="lg" class="overview-v2-info-dialog">
                <v-card-title class="overview-v2-info-dialog-title d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-information-outline" color="primary" />
                    Studierenden-Information
                </v-card-title>

                <v-card-text class="px-5 pt-4">
                    <div class="overview-v2-info-grid">
                        <div class="overview-v2-info-primary-row">
                            <div class="overview-v2-info-item">
                                <span class="overview-v2-info-label">Klasse</span>
                                <span class="overview-v2-info-value">{{ currentSelectionClass || '–' }}</span>
                            </div>
                            <div class="overview-v2-info-item">
                                <span class="overview-v2-info-label">Name</span>
                                <span class="overview-v2-info-value">{{ currentSelectionFullName || '–' }}</span>
                            </div>
                            <div class="overview-v2-info-item">
                                <span class="overview-v2-info-label">Religion</span>
                                <span class="overview-v2-info-value">{{ currentSelectionReligion || '–' }}</span>
                            </div>
                        </div>
                        <div class="overview-v2-info-secondary-row">
                            <div class="overview-v2-info-item">
                                <span class="overview-v2-info-label">Unterrichtsart</span>
                                <span class="overview-v2-info-value">{{ studentInformationInstructionType }}</span>
                            </div>
                        </div>
                        <div class="overview-v2-info-secondary-row">
                            <div class="overview-v2-info-item">
                                <span class="overview-v2-info-label">Stundentafel</span>
                                <span class="overview-v2-info-value">{{ studentInformationSubjectPlan }}</span>
                            </div>
                        </div>
                        <div class="overview-v2-info-secondary-row">
                            <div class="overview-v2-info-item">
                                <span class="overview-v2-info-label">Semester</span>
                                <span class="overview-v2-info-value">
                                    {{ studentInformationSemesterLabel }}
                                </span>
                            </div>
                        </div>
                        <div
                            v-for="item in studentInformationCalculationItems"
                            :key="item.key"
                            class="overview-v2-info-secondary-row">
                            <div class="overview-v2-info-item">
                                <span class="overview-v2-info-label">{{ item.label }}</span>
                                <span class="overview-v2-info-value">{{ item.value }}</span>
                            </div>
                        </div>
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
            <v-card rounded="lg" class="overview-v2-info-dialog overview-v2-study-info-dialog">
                <v-card-title class="overview-v2-info-dialog-title d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon icon="mdi-school-outline" color="teal-darken-1" />
                    Informationen zum Studium
                </v-card-title>

                <v-card-text class="overview-v2-study-info-content px-5 pt-4">
                    <div class="overview-v2-study-module-groups">
                        <section
                            v-for="group in studentStudyModuleGroups"
                            :key="group.key"
                            class="overview-v2-study-module-group"
                            :class="`overview-v2-study-module-group--${group.key}`"
                            :aria-labelledby="`student-study-module-group-${group.key}`">
                            <div class="overview-v2-study-module-group-header">
                                <h3
                                    :id="`student-study-module-group-${group.key}`"
                                    class="overview-v2-study-module-group-title">
                                    {{ group.label }}
                                </h3>
                                <div class="overview-v2-study-module-group-count">
                                    {{ group.count }} {{ group.count === 1 ? 'Modul' : 'Module' }}
                                </div>
                            </div>

                            <div v-if="group.modules.length" class="overview-v2-study-module-list">
                                <div
                                    v-for="module in group.modules"
                                    :key="`${module.code}-${module.grade}`"
                                    class="overview-v2-study-module-row">
                                    <span class="overview-v2-study-module-copy">
                                        <span class="overview-v2-study-module-code">{{ moduleDisplayCode(module) }}</span>
                                        <span
                                            v-if="moduleDisplayNameVisible(module)"
                                            class="overview-v2-study-module-name">
                                            {{ moduleDisplayName(module) }}
                                        </span>
                                    </span>
                                    <span class="overview-v2-study-module-grades">
                                        <span
                                            v-for="(grade, gradeIndex) in module.grades"
                                            :key="`${grade.status}-${grade.value}-${gradeIndex}`"
                                            class="overview-v2-study-module-grade"
                                            :class="`overview-v2-study-module-grade--${grade.status}`"
                                            :aria-label="`Note ${grade.value}`">
                                            {{ grade.value }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div v-else class="overview-v2-study-module-empty">Keine Module</div>
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
        </template>
    </div>
</template>

<script>
import axios from 'axios'
import { mapWritableState } from 'pinia'
import {
    show as showStudentTimetableV3Timetable,
    update as updateStudentTimetableV3Timetable,
} from '@/actions/App/Http/Controllers/Homepage/StudentTimetableV3TimetableController'
import {
    show as showStudentTimetableV3State,
    update as updateStudentTimetableV3State,
} from '@/actions/App/Http/Controllers/Homepage/StudentTimetableV3StateController'
import {
    overviewPdf as downloadStudentTimetableOverviewPdf,
} from '@/actions/App/Http/Controllers/Homepage/StudentsTimetablesStudentController'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'
import TimetableV3PossibleTimetables from '@/pages/admin/studentsTimetables/timetableV3/TimetableV3PossibleTimetables.vue'
import LoadingAnimation from '@/pages/components/LoadingAnimation.vue'
import {
    canonicalTimetableCourseLabel,
    canonicalTimetableModuleName,
    sortTimetableModuleCourses,
} from '@/pages/admin/studentsTimetables/timetableV3/courseLabels'
import StudentTimetablesNavigationDrawer from '../components/StudentTimetablesNavigationDrawer.vue'
import '../../../../../css/student.css'

const MAXIMUM_SELECTED_MODULES = 10
const MAXIMUM_SELECTED_MODULE_HOURS = 30
const MAXIMUM_FREE_DAYS = 5
const MAXIMUM_MATERIALIZED_TIMETABLES = 2000
const TIMETABLES_PER_PAGE = 100
const AUTOMATIC_MANUAL_TIMETABLE_DRAFT = 'automatic'
const PERSONAL_TIMETABLE_WEEKDAYS = Object.freeze([
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
const TIMETABLE_CALCULATION_PROGRESS_PHASES = [
    'preparing',
    'checking',
    'materializing',
    'analyzing_solutions',
    'compacting',
    'persisting',
    'complete',
]
const WORKSPACE_ID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

function normalizedWorkspaceId(workspaceId) {
    const normalizedValue = String(workspaceId || '').trim()

    return WORKSPACE_ID_PATTERN.test(normalizedValue) ? normalizedValue.toLowerCase() : ''
}

function savedTimetableDraftRouteSelection(source, savedTimetable) {
    const storedDraft = savedTimetable?.state?.manualTimetableDraft
    const fingerprint = String(storedDraft?.fingerprint || '').trim().toLowerCase()
    const timetableKey = String(storedDraft?.timetableKey || '').trim()
    const timetableIndex = Number(storedDraft?.timetableIndex)

    if (
        !['personal', 'published'].includes(source)
        || !/^[a-f0-9]{64}$/.test(fingerprint)
        || !timetableKey
        || !Number.isInteger(timetableIndex)
        || timetableIndex < 0
    ) return null

    const sourceMarker = source === 'published' ? 'b' : 'a'
    const workspaceHex = `${fingerprint.slice(0, 31)}${sourceMarker}`
    const workspaceId = [
        workspaceHex.slice(0, 8),
        workspaceHex.slice(8, 12),
        `4${workspaceHex.slice(13, 16)}`,
        `8${workspaceHex.slice(17, 20)}`,
        workspaceHex.slice(20, 32),
    ].join('-')

    return { fingerprint, timetableIndex, timetableKey, workspaceId }
}

function formattedTimetableSavedAt(value) {
    const savedAt = new Date(String(value || ''))

    if (Number.isNaN(savedAt.getTime())) return ''

    return new Intl.DateTimeFormat('de-AT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: 'Europe/Vienna',
    }).format(savedAt)
}

function normalizedCourseKeyList(courseKeys) {
    return [...new Set((Array.isArray(courseKeys) ? courseKeys : [])
        .map(courseKey => String(courseKey || '').trim())
        .filter(Boolean))]
}

function createWorkspaceId() {
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

function normalizedTimetableCalculationCount(value) {
    const count = Number(value)

    return Number.isFinite(count) && count > 0 ? Math.trunc(count) : 0
}

function normalizedTimetableCalculationProgressPhase(value) {
    const phase = String(value || '').trim()

    return TIMETABLE_CALCULATION_PROGRESS_PHASES.includes(phase) ? phase : 'checking'
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
        free_days: Number.isInteger(freeDays) && freeDays >= 1 && freeDays <= MAXIMUM_FREE_DAYS
            ? freeDays
            : DEFAULT_TIMETABLE_FILTERS.free_days,
    }
}

function timetableFiltersMatch(firstFilters, secondFilters) {
    const normalizedFirstFilters = normalizedTimetableFilters(firstFilters)
    const normalizedSecondFilters = normalizedTimetableFilters(secondFilters)

    return Object.keys(DEFAULT_TIMETABLE_FILTERS).every(
        filterKey => normalizedFirstFilters[filterKey] === normalizedSecondFilters[filterKey],
    )
}

function isTimetableCalculationResult(calculationResult) {
    const summary = calculationResult?.summary

    return calculationResult
        && typeof calculationResult === 'object'
        && !Array.isArray(calculationResult)
        && summary
        && typeof summary === 'object'
        && !Array.isArray(summary)
        && Array.isArray(calculationResult.timetables)
        && /^[a-f0-9]{64}$/.test(String(calculationResult.fingerprint || ''))
}

function normalizedTimetablePageMeta(calculationResult) {
    const meta = calculationResult?.timetables_meta
    const timetables = calculationResult?.timetables

    if (!meta || typeof meta !== 'object' || Array.isArray(meta) || !Array.isArray(timetables)) return null

    const currentPage = Number(meta.current_page)
    const perPage = Number(meta.per_page)
    const lastPage = Number(meta.last_page)
    const total = Number(meta.total)
    const unfilteredTotal = Number(meta.unfiltered_total)
    const offset = Number(meta.offset)
    const optionCounts = meta.option_counts
    const includeSaturday = Number(optionCounts?.include_saturday)
    const excludeSaturday = Number(optionCounts?.exclude_saturday)
    const freeDayCounts = optionCounts?.free_days
    const freeDaysAny = Number(freeDayCounts?.any)
    const maximumFreeDays = Number(freeDayCounts?.maximum)
    const freeDayValues = Array.isArray(freeDayCounts?.values)
        ? freeDayCounts.values.map(option => ({
            value: Number(option?.value),
            count: Number(option?.count),
        }))
        : []
    const filters = normalizedTimetableFilters(meta.filters)
    const expectedLastPage = Math.max(1, Math.ceil(total / TIMETABLES_PER_PAGE))
    const expectedItemCount = Math.min(TIMETABLES_PER_PAGE, Math.max(0, total - offset))

    if (
        !Number.isInteger(currentPage)
        || !Number.isInteger(lastPage)
        || !Number.isInteger(total)
        || !Number.isInteger(unfilteredTotal)
        || !Number.isInteger(offset)
        || perPage !== TIMETABLES_PER_PAGE
        || currentPage < 1
        || lastPage !== expectedLastPage
        || currentPage > lastPage
        || total < 0
        || total > MAXIMUM_MATERIALIZED_TIMETABLES
        || unfilteredTotal < total
        || unfilteredTotal > MAXIMUM_MATERIALIZED_TIMETABLES
        || offset !== (currentPage - 1) * TIMETABLES_PER_PAGE
        || timetables.length !== expectedItemCount
        || !Number.isInteger(includeSaturday)
        || !Number.isInteger(excludeSaturday)
        || !Number.isInteger(freeDaysAny)
        || !Number.isInteger(maximumFreeDays)
        || maximumFreeDays < 0
        || maximumFreeDays > MAXIMUM_FREE_DAYS
        || freeDayValues.some(option => !Number.isInteger(option.value) || !Number.isInteger(option.count))
    ) return null

    return {
        currentPage,
        lastPage,
        offset,
        perPage,
        total,
        unfilteredTotal,
        optionCounts: {
            includeSaturday,
            excludeSaturday,
            freeDays: {
                any: freeDaysAny,
                maximum: maximumFreeDays,
                values: freeDayValues,
            },
        },
        filters,
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

async function fetchStudentTimetableCalculation(payload) {
    if (typeof window.ensureCsrfCookie === 'function') {
        await window.ensureCsrfCookie()
    }

    const request = () => {
        const xsrfToken = xsrfTokenFromCookie()

        return fetch(updateStudentTimetableV3Timetable.url(), {
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

    if (response.status !== 419 || typeof window.axios?.get !== 'function') return response

    await window.axios.get('/sanctum/csrf-cookie', { __skipCsrfRetry: true })

    response = await request()

    return response
}

function timetableCalculationStreamError(data = {}, status = 0) {
    const error = new Error(String(data?.message || '').trim() || 'Bitte versuche die Berechnung erneut.')
    error.response = { data, status }

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
            throw timetableCalculationStreamError({ message: event.message, errors: event.errors })
        }

        if (event?.type === 'complete') {
            calculationResult = event.data

            return
        }

        if (event?.type === 'progress') onProgress(event)
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
    const entryKeys = new Set()

    return (Array.isArray(course?.timetable_entries) ? course.timetable_entries : [])
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

function normalizedCourseTimetableKeys(course) {
    return [...new Set([
        ...normalizedCourseSelectionKeys(course),
        ...normalizedCourseTimetableEntries(course).map(entry => String(entry.key || '').trim()),
    ].filter(Boolean))]
}

function normalizedTimetableCourseIdentity(value) {
    return canonicalTimetableCourseLabel(value)
        .replace(/[^\p{L}\p{N}]+/gu, '')
        .toLocaleLowerCase('de-AT')
}

function catalogCourseTimetableIdentities(course) {
    return [...new Set([
        course?.title,
        ...(Array.isArray(course?.timetable_entries) ? course.timetable_entries : [])
            .map(entry => entry?.display_label),
    ].map(normalizedTimetableCourseIdentity).filter(Boolean))]
}

function savedTimetableCourseIdentities(timetable) {
    return new Set((Array.isArray(timetable?.semesters) ? timetable.semesters : [])
        .flatMap(semester => Array.isArray(semester?.weeks) ? semester.weeks : [])
        .flatMap(week => Array.isArray(week?.hours) ? week.hours : [])
        .flatMap(hour => Array.isArray(hour?.cells) ? hour.cells : [])
        .flatMap(cell => Array.isArray(cell?.courses) ? cell.courses : [])
        .map(course => normalizedTimetableCourseIdentity(course?.identifier))
        .filter(Boolean))
}

function normalizedTimetableModuleCode(value) {
    return canonicalTimetableCourseLabel(value)
        .replace(/[\s_-]+/gu, '')
        .toLocaleLowerCase('de-AT')
}

function uniqueCoursesForModuleGroups(moduleGroups) {
    const courseIdentities = new Set()

    return (Array.isArray(moduleGroups) ? moduleGroups : [])
        .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
        .flatMap(module => Array.isArray(module?.courses) ? module.courses : [])
        .filter((course) => {
            const identity = normalizedCourseSelectionKeys(course).sort().join('|')

            if (!identity || courseIdentities.has(identity)) return false

            courseIdentities.add(identity)

            return true
        })
}

function manualTimetableForCourses(courses) {
    const slots = {}
    const timetableItems = (Array.isArray(courses) ? courses : [])
        .flatMap(course => normalizedCourseTimetableEntries(course).map(entry => ({ course, entry })))

    timetableItems
        .sort((firstItem, secondItem) => [
            Number(firstItem.entry.weekday),
            Number(firstItem.entry.hour),
            String(firstItem.entry.key || ''),
        ].join('|').localeCompare([
            Number(secondItem.entry.weekday),
            Number(secondItem.entry.hour),
            String(secondItem.entry.key || ''),
        ].join('|'), 'de-AT', { numeric: true }))
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

    return { key: 'student-manual-timetable', slots }
}

function savedTimetableWeekdayValue(weekday, index) {
    const label = String(weekday?.label || '').trim().toLocaleLowerCase('de-AT')
    const weekdayByLabel = {
        mo: 1,
        montag: 1,
        di: 2,
        dienstag: 2,
        mi: 3,
        mittwoch: 3,
        do: 4,
        donnerstag: 4,
        fr: 5,
        freitag: 5,
        sa: 6,
        samstag: 6,
    }

    return weekdayByLabel[label] || index + 1
}

function savedTimetableForManualEditor(timetable, source) {
    if (
        timetable?.slots
        && typeof timetable.slots === 'object'
        && !Array.isArray(timetable.slots)
    ) return timetable

    const weekdays = (Array.isArray(timetable?.weekdays) ? timetable.weekdays : [])
        .map((weekday, index) => savedTimetableWeekdayValue(weekday, index))
    const slots = {}
    const seenEntriesBySlot = new Map()
    let entryIndex = 0
    const semesters = Array.isArray(timetable?.semesters) ? timetable.semesters : []

    semesters.forEach((semester) => {
        const weeks = Array.isArray(semester?.weeks) ? semester.weeks : []

        weeks.forEach((week) => {
            const hourRows = Array.isArray(week?.hours) ? week.hours : []

            hourRows.forEach((hourRow) => {
                const hour = Number(hourRow?.hour)
                if (!Number.isInteger(hour) || hour < 1) return

                const cells = Array.isArray(hourRow?.cells) ? hourRow.cells : []

                cells.forEach((cell, weekdayIndex) => {
                    const weekday = weekdays[weekdayIndex] || weekdayIndex + 1
                    if (!Number.isInteger(weekday) || weekday < 1 || weekday > 6) return

                    const courses = Array.isArray(cell?.courses) ? cell.courses : []

                    courses.forEach((course) => {
                        const identifier = String(course?.identifier || '').trim()
                        const label = String(course?.label || identifier || 'Unterricht').trim()
                        const code = canonicalTimetableCourseLabel(identifier.split('-')[0] || label)
                        const dates = (Array.isArray(course?.dates) ? course.dates : [])
                            .map(date => String(date || '').trim())
                            .filter(Boolean)
                        const startsAt = String(course?.time_from || hourRow?.from || '').trim().slice(0, 5)
                        const endsAt = String(course?.time_until || hourRow?.until || '').trim().slice(0, 5)
                        const slotKey = `${weekday}-${hour}`
                        const entrySignature = JSON.stringify([
                            identifier,
                            label,
                            dates,
                            startsAt,
                            endsAt,
                        ])
                        const seenEntries = seenEntriesBySlot.get(slotKey) || new Set()

                        if (seenEntries.has(entrySignature)) return

                        seenEntries.add(entrySignature)
                        seenEntriesBySlot.set(slotKey, seenEntries)
                        entryIndex += 1

                        const entryKey = `saved-${source}-${entryIndex}`
                        const courseGroup = {
                            key: entryKey,
                            dates,
                            display_label: identifier || label,
                            ends_at: endsAt,
                            hour,
                            module_code: code,
                            recurrence_interval: Number(course?.recurrence_interval || 0) || null,
                            recurrence_label: String(course?.recurrence_label || course?.details || '').trim(),
                            starts_at: startsAt,
                            subject: code,
                            weekday,
                        }
                        const timetableEntry = {
                            key: entryKey,
                            code,
                            name: label,
                            sourceLabel: identifier || label,
                            courseGroup,
                            dateRangeLabel: '',
                            conflicts: [],
                            sameSlotEntries: [],
                            isDistanceLearningCourse: course?.is_fu === true,
                        }

                        if (slots[slotKey]) {
                            slots[slotKey].sameSlotEntries.push(timetableEntry)

                            return
                        }

                        slots[slotKey] = timetableEntry
                    })
                })
            })
        })
    })

    return { key: `saved-${source}-timetable`, slots }
}

function timetableEntryCourseKey(entry) {
    return String(entry?.courseGroup?.key || entry?.key || '').trim()
}

function timetableEntries(timetable) {
    return Object.values(timetable?.slots || {})
        .flatMap(slot => [
            slot,
            ...(Array.isArray(slot?.sameSlotEntries) ? slot.sameSlotEntries : []),
            ...(Array.isArray(slot?.conflicts) ? slot.conflicts : []),
        ])
        .filter(entry => entry && typeof entry === 'object' && !Array.isArray(entry))
}

function timetableEntrySchedule(entry) {
    const courseGroup = entry?.courseGroup

    return courseGroup && typeof courseGroup === 'object' && !Array.isArray(courseGroup)
        ? { ...entry, ...courseGroup, key: timetableEntryCourseKey(entry) }
        : entry
}

function timetableEntryOverlapLabel(entry) {
    return canonicalTimetableCourseLabel(
        entry?.courseGroup?.display_label
            || entry?.sourceLabel
            || entry?.display_label
            || entry?.name
            || entry?.code
            || '',
        entry?.courseGroup?.module_code || entry?.code || '',
    )
}

function timetableCourseKeys(timetable) {
    return [...new Set(timetableEntries(timetable)
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
    const slots = Object.fromEntries(Object.entries(timetable?.slots || {}).map(([slotKey, slot]) => [slotKey, {
        ...slot,
        conflicts: Array.isArray(slot?.conflicts) ? [...slot.conflicts] : [],
        sameSlotEntries: Array.isArray(slot?.sameSlotEntries) ? [...slot.sameSlotEntries] : [],
    }]))

    Object.entries(manualTimetable?.slots || {}).forEach(([slotKey, manualSlot]) => {
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

    return { ...timetable, slots }
}

function personalTimetableEntriesForCell(timetable, weekday, hour) {
    const slot = timetable?.slots?.[`${weekday}-${hour}`]
    if (!slot || typeof slot !== 'object' || Array.isArray(slot)) return []

    const seenEntryKeys = new Set()

    return [
        slot,
        ...(Array.isArray(slot.sameSlotEntries) ? slot.sameSlotEntries : []),
        ...(Array.isArray(slot.conflicts) ? slot.conflicts : []),
    ].filter((entry, index) => {
        if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return false

        const entryKey = String(entry.courseGroup?.key || entry.key || `entry-${index}`).trim()
        if (seenEntryKeys.has(entryKey)) return false

        seenEntryKeys.add(entryKey)

        return true
    })
}

function personalTimetableEntryDates(entry) {
    return [...new Set((Array.isArray(entry?.courseGroup?.dates) ? entry.courseGroup.dates : [])
        .map(date => String(date || '').trim())
        .filter(Boolean))]
        .sort((firstDate, secondDate) => firstDate.localeCompare(secondDate))
}

function personalTimetableTimeInMinutes(value) {
    const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})/u)
    if (!match) return null

    const hours = Number(match[1])
    const minutes = Number(match[2])
    if (!Number.isInteger(hours) || !Number.isInteger(minutes) || hours > 23 || minutes > 59) return null

    return (hours * 60) + minutes
}

function personalTimetableEntriesOverlapInTime(firstEntry, secondEntry) {
    const firstStartsAt = personalTimetableTimeInMinutes(
        firstEntry?.courseGroup?.starts_at || firstEntry?.courseGroup?.time_from,
    )
    const firstEndsAt = personalTimetableTimeInMinutes(
        firstEntry?.courseGroup?.ends_at || firstEntry?.courseGroup?.time_until,
    )
    const secondStartsAt = personalTimetableTimeInMinutes(
        secondEntry?.courseGroup?.starts_at || secondEntry?.courseGroup?.time_from,
    )
    const secondEndsAt = personalTimetableTimeInMinutes(
        secondEntry?.courseGroup?.ends_at || secondEntry?.courseGroup?.time_until,
    )

    if ([firstStartsAt, firstEndsAt, secondStartsAt, secondEndsAt].includes(null)) return true

    return firstStartsAt < secondEndsAt && secondStartsAt < firstEndsAt
}

function personalTimetableEntryOverlapDates(entry, entries) {
    const entryDates = personalTimetableEntryDates(entry)
    const overlappingDates = new Set()

    entries.forEach((otherEntry) => {
        if (otherEntry === entry || !personalTimetableEntriesOverlapInTime(entry, otherEntry)) return

        const otherDates = new Set(personalTimetableEntryDates(otherEntry))
        entryDates.forEach((date) => {
            if (otherDates.has(date)) overlappingDates.add(date)
        })
    })

    return [...overlappingDates].sort((firstDate, secondDate) => firstDate.localeCompare(secondDate))
}

function personalTimetableOverlapDates(entries) {
    return [...new Set(entries.flatMap(entry => personalTimetableEntryOverlapDates(entry, entries)))]
        .sort((firstDate, secondDate) => firstDate.localeCompare(secondDate))
}

function personalTimetableCourse(entry, entries) {
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

    return {
        label: title || identifier || code || 'UNTERRICHT',
        identifier: identifier || code,
        details: recurrenceLabel.toLocaleLowerCase('de-AT') === '1-wöchig' ? '' : recurrenceLabel,
        dates: personalTimetableEntryDates(entry).slice(0, 120),
        overlap_dates: personalTimetableEntryOverlapDates(entry, entries).slice(0, 120),
        time_from: String(courseGroup.starts_at || courseGroup.time_from || '').trim().slice(0, 5),
        time_until: String(courseGroup.ends_at || courseGroup.time_until || '').trim().slice(0, 5),
        is_fu: entry?.isDistanceLearningCourse === true,
        is_kompaktunterricht: courseGroup.is_kompaktunterricht === true,
        is_block: courseGroup.is_block === true,
        recurrence_label: recurrenceLabel,
        recurrence_interval: Number(courseGroup.recurrence_interval || 0) || null,
    }
}

function personalTimetablePayload(timetable, schoolHours) {
    const hours = Array.from({ length: 20 }, (_, index) => index + 1)
        .filter(hour => PERSONAL_TIMETABLE_WEEKDAYS.some(weekday => (
            personalTimetableEntriesForCell(timetable, weekday.value, hour).length > 0
        )))
    const weekdays = PERSONAL_TIMETABLE_WEEKDAYS.filter(weekday => weekday.value !== 6
        || hours.some(hour => personalTimetableEntriesForCell(timetable, weekday.value, hour).length > 0))
    const configuredHoursByNumber = new Map((Array.isArray(schoolHours) ? schoolHours : [])
        .map(row => [Number(row?.hour), row]))
    let numberedCellCount = 0

    return {
        title: 'Stundenplan',
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
                            const entries = personalTimetableEntriesForCell(timetable, weekday.value, hour)
                            const overlapDates = personalTimetableOverlapDates(entries)
                            const hasMultipleEntries = entries.length > 1

                            if (hasMultipleEntries) numberedCellCount += 1

                            return {
                                status: overlapDates.length ? 'conflict' : entries.length ? 'filled' : 'empty',
                                courses: entries.map(entry => personalTimetableCourse(entry, entries)),
                                markers: hasMultipleEntries
                                    ? [{
                                        label: `${overlapDates.length ? '!' : ''}${numberedCellCount}`,
                                        title: overlapDates.length
                                            ? overlapDates.length === 1
                                                ? 'Einzeltermin-Überschneidung'
                                                : 'Überschneidungen'
                                            : 'Mehrfachbelegung',
                                    }]
                                    : [],
                            }
                        }),
                    }
                }),
            }],
        }],
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

function moduleHours(module) {
    const hours = Number(module?.hours ?? 0)

    return Number.isFinite(hours) ? hours : 0
}

function moduleSelectionLimitViolation(moduleGroups, selectedModuleKeys) {
    const selectedKeys = new Set(Array.isArray(selectedModuleKeys) ? selectedModuleKeys : [])
    const modules = uniqueModules(moduleGroups)
        .filter(module => selectedKeys.has(module.selection_key))

    if (modules.length > MAXIMUM_SELECTED_MODULES) {
        return `Es können höchstens ${MAXIMUM_SELECTED_MODULES} Module gleichzeitig ausgewählt werden.`
    }

    const totalHours = modules.reduce((hours, module) => hours + moduleHours(module), 0)

    if (totalHours > MAXIMUM_SELECTED_MODULE_HOURS) {
        return `Es können höchstens ${MAXIMUM_SELECTED_MODULE_HOURS} Stunden gleichzeitig ausgewählt werden.`
    }

    return ''
}

export default {
    components: {
        LoadingAnimation,
        StudentTimetablesNavigationDrawer,
        TimetableV3PossibleTimetables,
    },

    async beforeMount() {
        this.pageLoading = true

        try {
            this.studentTimetablesStore = useStudentTimetablesUserStore()
            const isAuthenticated = await this.studentTimetablesStore.getCurrentUser()

            if (!isAuthenticated || !this.user) {
                this.$router.push('/homepage/students-timetables')
                return
            }

            await this.studentTimetablesStore.loadOverview()

            if (this.isTimetableAdoptionPage && this.savedTimetableAdoptionSource) {
                const initialized = this.initializeSavedTimetableAdoption(this.savedTimetableAdoptionSource)

                if (initialized) await this.restoreManualTimetableDraft()

                return
            }

            if (this.isTimetableResultsPage || this.isTimetableAdoptionPage) {
                await this.restoreStudentTimetableResults()

                if (this.isTimetableAdoptionPage && this.timetableCalculationStatus === 'success') {
                    await this.restoreManualTimetableDraft()
                }
            }
        } finally {
            this.pageLoading = false
        }
    },

    data() {
        return {
            activeModuleGroupKey: '',
            activeManualModuleGroupKey: '',
            adoptionRemovedCourseKeys: [],
            manualModuleCatalogView: 'student',
            manualPendingCourseKeys: [],
            manualSelectedCourseKeys: [],
            manualTimetableDraftSaveQueue: null,
            moduleCourseDialogModule: null,
            moduleCoursesDialogManual: false,
            moduleCoursesDialogOpen: false,
            moduleSelectionLimitMessage: '',
            pageLoading: true,
            pdfExporting: false,
            personalTimetableSavedInEditor: false,
            personalTimetableSaving: false,
            savedTimetableAdoptionBase: null,
            savedTimetableAdoptionCourseKeys: [],
            savedTimetableAdoptionModuleKeys: [],
            scheduleCreationMode: null,
            selectedCourseKeys: [],
            selectedModuleKeys: [],
            studentInfoDialogOpen: false,
            studentSelectionDialogOpen: false,
            studentSelectionDraftKey: '',
            studentSelectionDraftLabel: '',
            studentSelectionDraftValue: null,
            studentSelectionSaving: false,
            studentTimetablesStore: null,
            studyInfoDialogOpen: false,
            showDrawer: false,
            timetableCalculationCheckedCombinationCount: 0,
            timetableCalculationCombinationCount: 0,
            timetableCalculationError: '',
            timetableCalculationProgressPercent: 0,
            timetableCalculationProgressPhase: 'preparing',
            timetableCalculationRequestId: 0,
            timetableCalculationResult: null,
            timetableCalculationStatus: 'idle',
            timetableFilters: { ...DEFAULT_TIMETABLE_FILTERS },
            timetablePageError: '',
            timetablePageLoading: false,
            timetablePageLoadingDirection: '',
            timetablePageRequestId: 0,
            timetableSelectedIndex: 0,
            timetableWorkspaceId: '',
        }
    },

    computed: {
        ...mapWritableState(useStudentTimetablesUserStore, ['user', 'overview']),

        isTimetableCreationModePage() {
            return this.$route.path === '/students-timetables/create'
        },

        isTimetableResultsPage() {
            return this.$route.path === '/students-timetables/create/results'
        },

        isTimetableAdoptionPage() {
            return this.$route.path === '/students-timetables/create/adoption'
        },

        isTimetablePlanningPage() {
            return this.isTimetableCreationModePage
                || this.isTimetableResultsPage
                || this.isTimetableAdoptionPage
        },

        currentSelectionStudent() {
            return this.overview?.student || this.user || null
        },

        currentSelectionClass() {
            return String(this.currentSelectionStudent?.class || this.user?.schoolclass || '').trim()
        },

        currentSelectionFullName() {
            const lastName = this.currentSelectionStudent?.last_name || this.user?.last_name
            const firstName = this.currentSelectionStudent?.first_name || this.user?.first_name

            return [lastName, firstName]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join(' ')
        },

        currentSelectionReligion() {
            return String(this.currentSelectionStudent?.religion || '').trim()
        },

        currentSelectionEmail() {
            return String(this.user?.email || '').trim()
        },

        currentSelectionSexPresentation() {
            const sex = String(this.user?.sex || '').trim().toLowerCase()

            if (sex === 'm') {
                return { icon: 'mdi-gender-male', color: 'blue', label: 'männlich' }
            }

            if (sex === 'w') {
                return { icon: 'mdi-gender-female', color: 'pink', label: 'weiblich' }
            }

            return null
        },

        studentInformation() {
            return this.overview?.student_information || null
        },

        studentInformationInstructionType() {
            return String(this.studentInformation?.instruction_type || '').trim() || '–'
        },

        studentInformationSubjectPlan() {
            return String(this.studentInformation?.subject_plan || '').trim() || '–'
        },

        studentInformationSemesterLabel() {
            const semester = Number(this.studentInformation?.semester)

            return Number.isInteger(semester) && semester > 0 ? `${semester}. Semester` : '–'
        },

        studentInformationCalculationItems() {
            const itemsByKey = new Map(
                (Array.isArray(this.studentInformation?.items) ? this.studentInformation.items : [])
                    .map(item => [String(item?.key || ''), item]),
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

        studentInfoHoverItems() {
            return [
                { key: 'class', label: 'Klasse', value: this.currentSelectionClass || '–' },
                { key: 'name', label: 'Name', value: this.currentSelectionFullName || '–' },
                { key: 'religion', label: 'Religion', value: this.currentSelectionReligion || '–' },
                { key: 'instruction_type', label: 'Unterrichtsart', value: this.studentInformationInstructionType },
                { key: 'semester', label: 'Semester', value: this.studentInformationSemesterLabel },
                ...this.studentInformationCalculationItems,
            ]
        },

        studentStudyModuleGroups() {
            return Array.isArray(this.studentInformation?.module_groups)
                ? this.studentInformation.module_groups
                : []
        },

        moduleSelectionGroups() {
            return Array.isArray(this.studentInformation?.module_selection_groups)
                ? this.studentInformation.module_selection_groups
                : []
        },

        mainModuleSelectionGroups() {
            return Array.isArray(this.studentInformation?.main_module_selection_groups)
                ? this.studentInformation.main_module_selection_groups
                : []
        },

        manualModuleCatalogUsesMainGroups() {
            return this.manualModuleCatalogView === 'main'
        },

        manualModuleCatalogGroups() {
            return this.manualModuleCatalogUsesMainGroups
                ? this.mainModuleSelectionGroups
                : this.moduleSelectionGroups
        },

        visibleManualModuleCatalogGroups() {
            if (!this.manualModuleCatalogUsesMainGroups || !this.activeManualModuleGroupKey) {
                return this.manualModuleCatalogGroups
            }

            const activeGroup = this.manualModuleCatalogGroups
                .find(group => group?.key === this.activeManualModuleGroupKey)

            return activeGroup ? [activeGroup] : this.manualModuleCatalogGroups
        },

        activeManualModuleSelectionGroup() {
            return this.manualModuleCatalogGroups
                .find(group => group?.key === this.activeManualModuleGroupKey) || null
        },

        activeModuleSelectionGroup() {
            return this.moduleSelectionGroups
                .find(group => group?.key === this.activeModuleGroupKey) || null
        },

        selectedModules() {
            const selectedKeys = new Set(this.selectedModuleKeys)
            const selectedCatalogModules = uniqueModules(this.moduleSelectionGroups)
                .filter(module => selectedKeys.has(module.selection_key))
            const modules = selectedCatalogModules.length > 0
                ? selectedCatalogModules
                : (this.isTimetableResultsPage || this.isTimetableAdoptionPage)
                    && Array.isArray(this.timetableCalculationResult?.modules)
                    ? this.timetableCalculationResult.modules
                    : []

            return [...modules].sort((firstModule, secondModule) => (
                this.moduleDisplayCode(firstModule).localeCompare(
                    this.moduleDisplayCode(secondModule),
                    'de-AT',
                    { numeric: true, sensitivity: 'base' },
                )
            ))
        },

        selectedModuleCount() {
            return this.selectedModules.length
        },

        selectedModuleHours() {
            return this.selectedModules.reduce((totalHours, module) => totalHours + moduleHours(module), 0)
        },

        selectedModuleHoursLabel() {
            return this.selectedModuleHours.toLocaleString('de-AT', {
                maximumFractionDigits: 1,
            })
        },

        manualCatalogCourses() {
            return uniqueCoursesForModuleGroups([
                ...this.moduleSelectionGroups,
                ...this.mainModuleSelectionGroups,
            ])
        },

        manualSelectedCourses() {
            const selectedKeys = new Set(this.manualSelectedCourseKeys)

            return this.manualCatalogCourses.filter((course) => {
                const courseKeys = normalizedCourseSelectionKeys(course)

                return courseKeys.length > 0 && courseKeys.every(courseKey => selectedKeys.has(courseKey))
            })
        },

        manualSelectedModules() {
            const selectedKeys = new Set(this.manualSelectedCourseKeys)
            const moduleIdentities = new Set()

            return [...this.moduleSelectionGroups, ...this.mainModuleSelectionGroups]
                .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
                .filter(module => (Array.isArray(module?.courses) ? module.courses : []).some((course) => {
                    const courseKeys = normalizedCourseSelectionKeys(course)

                    return courseKeys.length > 0 && courseKeys.every(courseKey => selectedKeys.has(courseKey))
                }))
                .filter((module) => {
                    const identity = String(module?.code || module?.selection_key || '').trim().toLocaleLowerCase('de-AT')

                    if (!identity || moduleIdentities.has(identity)) return false

                    moduleIdentities.add(identity)

                    return true
                })
        },

        savedTimetableAdoptionModules() {
            const selectedModuleKeys = new Set(this.savedTimetableAdoptionModuleKeys)
            const moduleIdentities = new Set()

            return [...this.moduleSelectionGroups, ...this.mainModuleSelectionGroups]
                .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
                .filter(module => selectedModuleKeys.has(String(module?.selection_key || '').trim()))
                .filter((module) => {
                    const identity = String(module?.code || module?.selection_key || '').trim().toLocaleLowerCase('de-AT')

                    if (!identity || moduleIdentities.has(identity)) return false

                    moduleIdentities.add(identity)

                    return true
                })
        },

        adoptionSelectedModules() {
            const placedCourseKeys = new Set(this.adoptionPlacedCourseKeys)
            const savedModuleKeys = new Set(this.savedTimetableAdoptionModuleKeys)
            const moduleIdentities = new Set()

            return [...this.savedTimetableAdoptionModules, ...this.selectedModules, ...this.manualSelectedModules]
                .filter((module) => {
                    if (savedModuleKeys.has(String(module?.selection_key || '').trim())) return true

                    const moduleCourseKeys = (Array.isArray(module?.courses) ? module.courses : [])
                        .flatMap(course => normalizedCourseSelectionKeys(course))

                    return !moduleCourseKeys.length || moduleCourseKeys.some(courseKey => placedCourseKeys.has(courseKey))
                })
                .filter((module) => {
                    const identity = String(module?.code || module?.selection_key || '').trim().toLocaleLowerCase('de-AT')

                    if (!identity || moduleIdentities.has(identity)) return false

                    moduleIdentities.add(identity)

                    return true
                })
                .sort((firstModule, secondModule) => this.moduleDisplayCode(firstModule).localeCompare(
                    this.moduleDisplayCode(secondModule),
                    'de-AT',
                    { numeric: true, sensitivity: 'base' },
                ))
        },

        adoptionSelectedModuleCount() {
            return this.adoptionSelectedModules.length
        },

        adoptionSelectedModuleHoursLabel() {
            return this.adoptionSelectedModules
                .reduce((hours, module) => hours + moduleHours(module), 0)
                .toLocaleString('de-AT', { maximumFractionDigits: 1 })
        },

        adoptionPlacedCourseKeys() {
            return [...new Set([
                ...timetableCourseKeys(this.adoptionBaseTimetable),
                ...this.savedTimetableAdoptionCourseKeys,
                ...this.manualSelectedCourseKeys,
            ])]
        },

        manualTimetable() {
            return manualTimetableForCourses(this.manualSelectedCourses)
        },

        adoptionBaseTimetable() {
            return timetableWithoutCourseKeys(this.selectedTimetableResult, this.adoptionRemovedCourseKeys)
        },

        adoptionDisplayedTimetable() {
            if (!this.adoptionBaseTimetable) return null

            return timetableWithManualCourses(this.adoptionBaseTimetable, this.manualTimetable)
        },

        personalTimetableSchoolHours() {
            return Array.isArray(this.overview?.school_hours) ? this.overview.school_hours : []
        },

        maximumSelectedModules() {
            return MAXIMUM_SELECTED_MODULES
        },

        maximumSelectedModuleHours() {
            return MAXIMUM_SELECTED_MODULE_HOURS
        },

        moduleCourseDialogCourses() {
            const courses = Array.isArray(this.moduleCourseDialogModule?.courses)
                ? this.moduleCourseDialogModule.courses
                : []

            return sortTimetableModuleCourses(
                courses,
                this.moduleCourseDialogModule?.code,
            )
        },

        selectedModuleCourseCount() {
            if (this.moduleCoursesDialogManual) {
                return this.moduleCourseDialogCourses
                    .filter(course => this.manualPendingCourseSelected(course))
                    .length
            }

            return this.selectedCourseCountForModule(this.moduleCourseDialogModule)
        },

        allModuleCoursesSelected() {
            return this.moduleCourseDialogCourses.length > 0
                && this.selectedModuleCourseCount === this.moduleCourseDialogCourses.length
        },

        hasSelectedModuleCourses() {
            return this.selectedModuleCourseCount > 0
        },

        studentPlanningSelectionFields() {
            const fields = Array.isArray(this.studentInformation?.selection_fields)
                ? this.studentInformation.selection_fields
                : []
            const selection = this.overview?.selection || {}
            const selectionOverride = this.overview?.selection_override || {}

            return fields
                .map((field) => {
                    const key = String(field?.key || '').trim()
                    const options = Array.isArray(field?.options) ? field.options : []
                    const selectedValue = selection[key] ?? field?.selected_value ?? null
                    const selectedOption = options.find(option => option?.value === selectedValue)

                    return {
                        key,
                        label: String(field?.label || '').trim(),
                        options,
                        overridden: Object.prototype.hasOwnProperty.call(selectionOverride, key),
                        selectedValue,
                        value: String(selectedOption?.title || selectedValue || '').trim() || '–',
                    }
                })
                .filter(field => field.key !== '' && field.label !== '' && field.options.length > 0)
        },

        hasStudentSelectionOverride() {
            return Object.keys(this.overview?.selection_override || {}).length > 0
        },

        hasPersonalTimetable() {
            return Boolean(this.overview?.personal_timetable?.id)
        },

        personalTimetableSavedAtLabel() {
            return formattedTimetableSavedAt(this.overview?.personal_timetable?.adopted_at)
        },

        personalTimetableAdoptionSavedAtLabel() {
            return this.savedTimetableAdoptionSource === 'personal' || this.personalTimetableSavedInEditor
                ? this.personalTimetableSavedAtLabel
                : ''
        },

        publishedTimetableAdoptionName() {
            if (this.savedTimetableAdoptionSource !== 'published') return ''

            return String(this.overview?.published_timetable?.name || '').trim()
        },

        hasPublishedTimetable() {
            return Boolean(this.overview?.published_timetable?.id)
        },

        savedTimetableAdoptionSource() {
            const source = String(this.$route?.query?.manual_timetable || '').trim()

            return this.isTimetableAdoptionPage && ['personal', 'published'].includes(source)
                ? source
                : ''
        },

        timetableStartOptions() {
            const publishedTimetableName = String(this.overview?.published_timetable?.name || '').trim()

            return [
                {
                    key: 'empty',
                    title: 'Leerer Stundenplan',
                    description: 'Beginne mit einem leeren Plan und stelle deine Unterrichte selbst zusammen.',
                    icon: 'mdi-calendar-blank-outline',
                    available: true,
                    timetableName: '',
                    availabilityLabel: 'Immer verfügbar',
                    actionLabel: 'Leer beginnen',
                },
                {
                    key: 'personal',
                    title: 'Mein gespeicherter Stundenplan',
                    description: 'Setze mit deinem zuletzt für dich gespeicherten Stundenplan fort.',
                    icon: 'mdi-account-calendar',
                    available: this.hasPersonalTimetable,
                    timetableName: '',
                    availabilityLabel: this.hasPersonalTimetable
                        ? this.personalTimetableSavedAtLabel
                            ? `Gespeichert am ${this.personalTimetableSavedAtLabel} Uhr`
                            : 'Eigener Stundenplan verfügbar'
                        : 'Noch kein eigener Stundenplan gespeichert',
                    actionLabel: 'Eigenen öffnen',
                },
                {
                    key: 'published',
                    title: 'Stundenplan der Lehrperson',
                    description: 'Starte mit dem Stundenplan, den eine Lehrperson für dich bereitgestellt hat.',
                    icon: 'mdi-calendar-check',
                    available: this.hasPublishedTimetable,
                    timetableName: publishedTimetableName,
                    availabilityLabel: this.hasPublishedTimetable
                        ? `Stundenplan der Lehrperson verfügbar${publishedTimetableName
                            ? ` · Nr. ${publishedTimetableName}`
                            : ''}`
                        : 'Noch kein Stundenplan bereitgestellt',
                    actionLabel: 'Vorlage öffnen',
                },
            ]
        },

        studentSelectionDraftOptions() {
            return this.studentPlanningSelectionFields
                .find(field => field.key === this.studentSelectionDraftKey)?.options || []
        },

        studentSelectionDraftValid() {
            return !this.studentSelectionSaving
                && (
                    this.studentSelectionDraftValue === null
                    || this.studentSelectionDraftValue === undefined
                    || this.studentSelectionDraftOptions
                        .some(option => option?.value === this.studentSelectionDraftValue)
                )
        },

        timetableCalculationSummary() {
            const summary = this.timetableCalculationResult?.summary

            return summary && typeof summary === 'object' && !Array.isArray(summary) ? summary : {}
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
                    freeDays: { any: 0, maximum: 0, values: [] },
                },
                filters: normalizedTimetableFilters(this.timetableFilters),
            }
        },

        selectedTimetableResult() {
            if (this.savedTimetableAdoptionSource) {
                const timetable = this.savedTimetableAdoptionBase

                return timetable
                    && typeof timetable === 'object'
                    && !Array.isArray(timetable)
                    && timetable.slots
                    && typeof timetable.slots === 'object'
                    && !Array.isArray(timetable.slots)
                    ? timetable
                    : null
            }

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

        possibleTimetableCount() {
            return this.timetablePageMeta.total
        },

        possibleTimetableCountLabel() {
            return this.possibleTimetableCount.toLocaleString('de-AT')
        },

        timetableCalculationHeading() {
            return this.timetableCalculationStatus === 'success'
                ? 'Ergebnis der Stundenplanberechnung'
                : 'Berechnung der Stundenpläne'
        },

        checkedTimetableVariationCountLabel() {
            return normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.timetable_variation_count,
            ).toLocaleString('de-AT')
        },

        conflictingTimetableVariationCountLabel() {
            return normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.conflict_timetable_count,
            ).toLocaleString('de-AT')
        },

        materializedTimetableCount() {
            return normalizedTimetableCalculationCount(this.timetableCalculationSummary.timetable_count)
        },

        materializedTimetableCountLabel() {
            return this.materializedTimetableCount.toLocaleString('de-AT')
        },

        unfilteredMaterializedTimetableCountLabel() {
            return this.materializedTimetableCountLabel
        },

        filteredOutAllTimetables() {
            return this.timetablePageMeta.total === 0
                && this.timetablePageMeta.unfilteredTotal > 0
        },

        totalPossibleTimetableCount() {
            const explicitCount = normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.possible_timetable_count,
            )

            if (explicitCount > 0) return explicitCount

            return normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.full_green_timetable_count,
            ) + normalizedTimetableCalculationCount(
                this.timetableCalculationSummary.green_timetable_count,
            ) || this.possibleTimetableCount
        },

        totalPossibleTimetableCountLabel() {
            return this.totalPossibleTimetableCount.toLocaleString('de-AT')
        },

        timetablesTruncated() {
            return this.timetableCalculationSummary.timetables_truncated === true
                || this.totalPossibleTimetableCount > this.materializedTimetableCount
        },

        timetableCalculationProgressLabel() {
            if (this.timetableCalculationProgressPhase === 'checking') {
                if (this.timetableCalculationCombinationCount === 0) {
                    return 'Kombinationen werden vorbereitet …'
                }

                return `${this.timetableCalculationCheckedCombinationCount.toLocaleString('de-AT')} von ${this.timetableCalculationCombinationCount.toLocaleString('de-AT')} Kombinationen geprüft.`
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

        timetableFilterOptionCountLabels() {
            return {
                includeSaturday: this.timetableVariantCountLabel(
                    this.timetablePageMeta.optionCounts.includeSaturday,
                ),
                excludeSaturday: this.timetableVariantCountLabel(
                    this.timetablePageMeta.optionCounts.excludeSaturday,
                ),
            }
        },

        timetableFreeDayOptionCounts() {
            return this.timetablePageMeta.optionCounts.freeDays
        },

        timetableVisibleFreeDayOptions() {
            return this.timetableFreeDayOptionCounts.values
                .filter(option => option.count > 0)
        },

        timetableHasVisibleFreeDayOptions() {
            return this.timetableFreeDayOptionCounts.any > 0
                || this.timetableVisibleFreeDayOptions.length > 0
        },
    },

    methods: {
        closeStudentInfoDialog() {
            this.studentInfoDialogOpen = false
        },

        closeStudyInfoDialog() {
            this.studyInfoDialogOpen = false
        },

        closeStudentSelectionDialog() {
            if (this.studentSelectionSaving) return

            this.studentSelectionDialogOpen = false
            this.studentSelectionDraftKey = ''
            this.studentSelectionDraftLabel = ''
            this.studentSelectionDraftValue = null
        },

        moduleDisplayCode(module) {
            return String(module?.code || '').trim()
        },

        moduleDisplayName(module) {
            return canonicalTimetableModuleName(module?.name, module?.code)
        },

        moduleDisplayNameVisible(module) {
            const name = this.moduleDisplayName(module)

            return name !== '' && name !== this.moduleDisplayCode(module)
        },

        timetableVariantCountLabel(value) {
            const count = Number(value)
            const normalizedCount = Number.isInteger(count) && count >= 0 ? count : 0

            return `${normalizedCount.toLocaleString('de-AT')} ${normalizedCount === 1 ? 'Variante' : 'Varianten'}`
        },

        chooseScheduleCreationMode(scheduleCreationMode) {
            if (scheduleCreationMode !== 'automatic') return

            this.scheduleCreationMode = scheduleCreationMode
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

        showManualModuleCatalog(catalog) {
            if (!['student', 'main'].includes(catalog) || this.manualModuleCatalogView === catalog) return

            this.manualModuleCatalogView = catalog
            this.activeManualModuleGroupKey = ''
            this.manualPendingCourseKeys = []
            if (this.moduleCoursesDialogManual) this.closeModuleCoursesDialog()
        },

        manualModuleGroupActive(group) {
            return group?.key === this.activeManualModuleGroupKey
        },

        toggleManualModuleGroup(group) {
            const groupKey = String(group?.key || '').trim()
            if (!groupKey) return

            this.activeManualModuleGroupKey = this.activeManualModuleGroupKey === groupKey ? '' : groupKey
        },

        manualModuleCourseCount(module) {
            return Array.isArray(module?.courses) ? module.courses.length : 0
        },

        moduleCourseAlreadyPlanned(course) {
            return Boolean(
                this.moduleCoursesDialogManual
                && courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys),
            )
        },

        manualModuleAlreadyPlanned(module) {
            return (Array.isArray(module?.courses) ? module.courses : [])
                .some(course => courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys))
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

        manualSelectedModuleCountForGroup(group) {
            return (Array.isArray(group?.modules) ? group.modules : [])
                .filter(module => this.manualModuleAlreadyPlanned(module))
                .length
        },

        moduleSelected(module) {
            return this.selectedModuleKeys.includes(module?.selection_key)
        },

        selectedModuleCountForGroup(group) {
            const selectedKeys = new Set(this.selectedModuleKeys)

            return (Array.isArray(group?.modules) ? group.modules : [])
                .filter(module => selectedKeys.has(module.selection_key))
                .length
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
                .filter((course) => {
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

        moduleCourseTitle(course) {
            return String(course?.title || this.moduleCourseDialogModule?.code || '').trim()
        },

        moduleCourseSubtitle(course) {
            const subtitle = String(course?.course_title || '').trim()
            const title = this.moduleCourseTitle(course)
            const moduleBase = this.moduleDisplayCode(this.moduleCourseDialogModule).replace(/\d+$/u, '')

            if (!subtitle || subtitle === title || subtitle === moduleBase) return ''
            if (title.startsWith(`${subtitle}-`) || title.startsWith(`${subtitle} -`)) return ''

            return subtitle
        },

        courseScheduleRows(course) {
            return normalizedCourseScheduleRows(course)
        },

        courseScheduleRowOverlapLabels(course, scheduleRow) {
            const scheduleEntryKeys = new Set(Array.isArray(scheduleRow?.entryKeys) ? scheduleRow.entryKeys : [])
            const scheduleEntries = normalizedCourseTimetableEntries(course)
                .filter(entry => !scheduleEntryKeys.size || scheduleEntryKeys.has(String(entry.key || '').trim()))
            if (!scheduleEntries.length) return []

            const currentCourseKeys = new Set(normalizedCourseSelectionKeys(course))
            const activeCourseKeys = this.moduleCoursesDialogManual
                ? [
                    ...(Array.isArray(this.manualSelectedCourseKeys) ? this.manualSelectedCourseKeys : []),
                    ...(Array.isArray(this.manualPendingCourseKeys) ? this.manualPendingCourseKeys : []),
                ]
                : (Array.isArray(this.selectedCourseKeys) ? this.selectedCourseKeys : [])
            const visibleTimetableOverlapLabels = this.moduleCoursesDialogManual
                ? timetableEntries(this.adoptionBaseTimetable)
                    .filter(entry => scheduleEntries
                        .some(scheduleEntry => timetableEntriesOverlap(scheduleEntry, timetableEntrySchedule(entry))))
                    .map(entry => timetableEntryOverlapLabel(entry))
                    .filter(Boolean)
                : []

            const selectedCourseOverlapLabels = (Array.isArray(this.manualCatalogCourses) ? this.manualCatalogCourses : [])
                .filter(otherCourse => courseUsesSelectedKey(otherCourse, activeCourseKeys))
                .filter(otherCourse => !normalizedCourseSelectionKeys(otherCourse)
                    .some(courseKey => currentCourseKeys.has(courseKey)))
                .flatMap(otherCourse => normalizedCourseTimetableEntries(otherCourse)
                    .filter(otherEntry => scheduleEntries
                        .some(scheduleEntry => timetableEntriesOverlap(scheduleEntry, otherEntry)))
                    .map(otherEntry => timetableCourseOverlapLabel(otherCourse, otherEntry)))
                .filter(Boolean)

            return [...new Set([...visibleTimetableOverlapLabels, ...selectedCourseOverlapLabels])]
        },

        openModuleCoursesDialog(module) {
            if (!module?.selection_key) return

            this.moduleCourseDialogModule = module
            this.moduleCoursesDialogManual = false
            this.moduleCoursesDialogOpen = true
        },

        openManualModuleCoursesDialog(module) {
            if (!module?.selection_key) return

            this.manualPendingCourseKeys = []
            this.moduleCourseDialogModule = module
            this.moduleCoursesDialogManual = true
            this.moduleCoursesDialogOpen = true
        },

        closeModuleCoursesDialog() {
            this.manualPendingCourseKeys = []
            this.moduleCoursesDialogOpen = false
        },

        manualPendingCourseSelected(course) {
            const pendingKeys = new Set(this.manualPendingCourseKeys)
            const courseKeys = normalizedCourseSelectionKeys(course)

            return courseKeys.length > 0 && courseKeys.every(courseKey => pendingKeys.has(courseKey))
        },

        displayedModuleCourseSelected(course) {
            return this.moduleCoursesDialogManual
                ? this.manualPendingCourseSelected(course)
                : this.moduleCourseSelected(course)
        },

        toggleDisplayedModuleCourse(course) {
            if (!this.moduleCoursesDialogManual) {
                this.toggleModuleCourse(course)

                return
            }

            const courseKeys = normalizedCourseSelectionKeys(course)
            if (!courseKeys.length || !normalizedCourseTimetableEntries(course).length) return
            if (courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys)) return

            this.manualPendingCourseKeys = this.manualPendingCourseSelected(course)
                ? this.manualPendingCourseKeys.filter(courseKey => !courseKeys.includes(courseKey))
                : [...new Set([...this.manualPendingCourseKeys, ...courseKeys])]
        },

        async planManualModuleCourses() {
            const pendingKeys = new Set(this.manualPendingCourseKeys)
            const plannedCourseKeys = this.moduleCourseDialogCourses
                .filter(course => !courseUsesSelectedKey(course, this.adoptionPlacedCourseKeys))
                .filter(course => normalizedCourseTimetableEntries(course).length > 0)
                .filter((course) => {
                    const courseKeys = normalizedCourseSelectionKeys(course)

                    return courseKeys.length > 0 && courseKeys.every(courseKey => pendingKeys.has(courseKey))
                })
                .flatMap(course => normalizedCourseSelectionKeys(course))

            if (!plannedCourseKeys.length) return

            this.manualSelectedCourseKeys = [...new Set([
                ...this.manualSelectedCourseKeys,
                ...plannedCourseKeys,
            ])]
            this.closeModuleCoursesDialog()
            await this.saveManualTimetableDraft()
        },

        async removeAdoptionModule(module) {
            const courseKeys = (Array.isArray(module?.courses) ? module.courses : [])
                .flatMap(course => normalizedCourseSelectionKeys(course))
            const savedTimetableEntryKeys = this.savedTimetableEntryKeysForModule(module)
            const savedTimetableModuleKeys = this.savedTimetableModuleKeysForModule(module)
            if (!courseKeys.length && !savedTimetableEntryKeys.length) return

            this.manualSelectedCourseKeys = this.manualSelectedCourseKeys
                .filter(courseKey => !courseKeys.includes(courseKey))
            this.savedTimetableAdoptionCourseKeys = this.savedTimetableAdoptionCourseKeys
                .filter(courseKey => !courseKeys.includes(courseKey))
            this.savedTimetableAdoptionModuleKeys = this.savedTimetableAdoptionModuleKeys
                .filter(moduleKey => !savedTimetableModuleKeys.includes(moduleKey))
            this.manualPendingCourseKeys = []
            this.adoptionRemovedCourseKeys = [...new Set([
                ...this.adoptionRemovedCourseKeys,
                ...courseKeys,
                ...savedTimetableEntryKeys,
            ])]
            await this.saveManualTimetableDraft()
        },

        savedTimetableModuleKeysForModule(module) {
            const courseKeys = new Set((Array.isArray(module?.courses) ? module.courses : [])
                .flatMap(course => normalizedCourseSelectionKeys(course)))
            const moduleCodes = new Set([
                module?.code,
                ...(Array.isArray(module?.courses) ? module.courses : [])
                    .flatMap(course => normalizedCourseTimetableEntries(course))
                    .flatMap(entry => [entry?.module_code, entry?.subject]),
            ].map(normalizedTimetableModuleCode).filter(Boolean))

            return [...new Set([...this.moduleSelectionGroups, ...this.mainModuleSelectionGroups]
                .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
                .filter((candidateModule) => {
                    const candidateModuleCodes = new Set([
                        candidateModule?.code,
                        ...(Array.isArray(candidateModule?.courses) ? candidateModule.courses : [])
                            .flatMap(course => normalizedCourseTimetableEntries(course))
                            .flatMap(entry => [entry?.module_code, entry?.subject]),
                    ].map(normalizedTimetableModuleCode).filter(Boolean))
                    const hasMatchingModuleCode = [...candidateModuleCodes]
                        .some(moduleCode => moduleCodes.has(moduleCode))
                    const hasMatchingCourseKey = (Array.isArray(candidateModule?.courses)
                        ? candidateModule.courses
                        : [])
                        .flatMap(course => normalizedCourseSelectionKeys(course))
                        .some(courseKey => courseKeys.has(courseKey))

                    return hasMatchingModuleCode || hasMatchingCourseKey
                })
                .map(candidateModule => String(candidateModule?.selection_key || '').trim())
                .filter(Boolean))]
        },

        savedTimetableEntryKeysForModule(module) {
            const moduleCodes = new Set([
                module?.code,
                ...(Array.isArray(module?.courses) ? module.courses : [])
                    .flatMap(course => normalizedCourseTimetableEntries(course))
                    .flatMap(entry => [entry?.module_code, entry?.subject]),
            ].map(normalizedTimetableModuleCode).filter(Boolean))
            if (!moduleCodes.size) return []

            return [...new Set(Object.values(this.savedTimetableAdoptionBase?.slots || {})
                .flatMap(slot => [
                    slot,
                    ...(Array.isArray(slot?.sameSlotEntries) ? slot.sameSlotEntries : []),
                    ...(Array.isArray(slot?.conflicts) ? slot.conflicts : []),
                ])
                .filter(entry => moduleCodes.has(normalizedTimetableModuleCode(
                    entry?.code || entry?.courseGroup?.module_code || entry?.courseGroup?.subject,
                )))
                .map(entry => timetableEntryCourseKey(entry))
                .filter(Boolean))]
        },

        manualTimetableDraftRouteSelection() {
            const savedTimetableSource = this.savedTimetableAdoptionSource
            const savedTimetable = {
                personal: this.overview?.personal_timetable,
                published: this.overview?.published_timetable,
            }[savedTimetableSource]
            const savedTimetableSelection = savedTimetableDraftRouteSelection(
                savedTimetableSource,
                savedTimetable,
            )

            if (savedTimetableSelection) return savedTimetableSelection

            const workspaceId = normalizedWorkspaceId(this.$route?.query?.workspace_id)
            const fingerprint = String(this.$route?.query?.fingerprint || '').trim()
            const timetableKey = String(this.$route?.query?.timetable_key || '').trim()
            const timetableIndex = Number(this.$route?.query?.timetable_index)

            if (
                !workspaceId
                || !/^[a-f0-9]{64}$/.test(fingerprint)
                || !timetableKey
                || !Number.isInteger(timetableIndex)
                || timetableIndex < 0
            ) return null

            return { fingerprint, timetableIndex, timetableKey, workspaceId }
        },

        async restoreManualTimetableDraft() {
            this.manualSelectedCourseKeys = []
            this.manualPendingCourseKeys = []
            this.adoptionRemovedCourseKeys = []

            const routeSelection = this.manualTimetableDraftRouteSelection()
            if (!this.isTimetableAdoptionPage || !routeSelection) return false

            try {
                const response = await axios.get(showStudentTimetableV3State.url({
                    query: { workspace_id: routeSelection.workspaceId },
                }))
                const storedDraft = response.data?.data?.manual_timetable_draft

                if (
                    !storedDraft
                    || typeof storedDraft !== 'object'
                    || Array.isArray(storedDraft)
                    || storedDraft.source !== AUTOMATIC_MANUAL_TIMETABLE_DRAFT
                    || String(storedDraft.fingerprint || '').trim() !== routeSelection.fingerprint
                    || String(storedDraft.timetableKey || '').trim() !== routeSelection.timetableKey
                    || Number(storedDraft.timetableIndex) !== routeSelection.timetableIndex
                ) return false

                const courses = Array.isArray(this.manualCatalogCourses) ? this.manualCatalogCourses : []
                const validCourseKeys = new Set([
                    ...courses.flatMap(course => normalizedCourseSelectionKeys(course)),
                    ...timetableCourseKeys(this.savedTimetableAdoptionBase),
                ])
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

                if (this.savedTimetableAdoptionBase) {
                    const removedCourseKeys = new Set(this.adoptionRemovedCourseKeys)
                    const catalogModules = [...this.moduleSelectionGroups, ...this.mainModuleSelectionGroups]
                        .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])

                    this.savedTimetableAdoptionCourseKeys = this.savedTimetableAdoptionCourseKeys
                        .filter(courseKey => !removedCourseKeys.has(courseKey))
                    this.savedTimetableAdoptionModuleKeys = this.savedTimetableAdoptionModuleKeys
                        .filter((moduleKey) => {
                            const module = catalogModules.find(
                                candidate => String(candidate?.selection_key || '').trim() === moduleKey,
                            )
                            if (!module) return false

                            const savedEntryKeys = this.savedTimetableEntryKeysForModule(module)
                            const catalogCourseKeys = (Array.isArray(module?.courses) ? module.courses : [])
                                .flatMap(course => normalizedCourseSelectionKeys(course))

                            return savedEntryKeys.some(courseKey => !removedCourseKeys.has(courseKey))
                                || catalogCourseKeys.some(courseKey => (
                                    this.savedTimetableAdoptionCourseKeys.includes(courseKey)
                                    && !removedCourseKeys.has(courseKey)
                                ))
                        })
                }

                return true
            } catch {
                return false
            }
        },

        async saveManualTimetableDraft() {
            const routeSelection = this.manualTimetableDraftRouteSelection()
            if (!this.isTimetableAdoptionPage || !routeSelection) return false

            const payload = {
                workspace_id: routeSelection.workspaceId,
                manual_timetable_draft: {
                    source: AUTOMATIC_MANUAL_TIMETABLE_DRAFT,
                    fingerprint: routeSelection.fingerprint,
                    timetable_key: routeSelection.timetableKey,
                    timetable_index: routeSelection.timetableIndex,
                    selected_course_keys: normalizedCourseKeyList(this.manualSelectedCourseKeys),
                    removed_course_keys: normalizedCourseKeyList(this.adoptionRemovedCourseKeys),
                },
            }
            const precedingSave = this.manualTimetableDraftSaveQueue || Promise.resolve()
            const queuedSave = precedingSave
                .catch(() => undefined)
                .then(() => axios.put(updateStudentTimetableV3State.url(), payload))

            this.manualTimetableDraftSaveQueue = queuedSave

            try {
                await queuedSave

                return true
            } catch {
                return false
            }
        },

        personalTimetablePayload() {
            return personalTimetablePayload(
                this.adoptionDisplayedTimetable || { slots: {} },
                this.personalTimetableSchoolHours,
            )
        },

        manualTimetablePdfPayload() {
            return {
                manual_cover: true,
                ...this.personalTimetablePayload(),
                subtitle: `${this.adoptionSelectedModuleCount} ${this.adoptionSelectedModuleCount === 1 ? 'Modul' : 'Module'}`,
                student: [this.currentSelectionClass, this.currentSelectionFullName]
                    .filter(Boolean)
                    .join(' · '),
                generated_at: new Intl.DateTimeFormat('de-AT', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                }).format(new Date()),
                study_selections: this.studentPlanningSelectionFields.map(field => ({
                    label: String(field?.label || '').trim().slice(0, 80),
                    value: String(field?.value || '–').trim().slice(0, 120) || '–',
                })).filter(field => field.label),
                print_options: {
                    single_weeks: false,
                    course_list: false,
                    course_overview: false,
                },
            }
        },

        async downloadManualTimetablePdf() {
            if (this.pdfExporting || !this.adoptionDisplayedTimetable) return

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

            const utf8Match = normalizedHeader.match(/filename\*=UTF-8''([^;]+)/iu)
            if (utf8Match?.[1]) {
                try {
                    return decodeURIComponent(utf8Match[1].replace(/^"|"$/gu, ''))
                } catch {
                    return utf8Match[1].replace(/^"|"$/gu, '')
                }
            }

            const filenameMatch = normalizedHeader.match(/filename="?([^";]+)"?/iu)

            return filenameMatch?.[1] || ''
        },

        async savePersonalTimetable() {
            if (this.personalTimetableSaving || !this.adoptionDisplayedTimetable) return false

            this.personalTimetableSaving = true

            try {
                const saved = await this.studentTimetablesStore.savePersonalTimetable({
                    timetable: this.personalTimetablePayload(),
                    state: {
                        source: 'student-timetable-v2-manual',
                        activeCourseGroupFilterKeys: this.adoptionPlacedCourseKeys,
                    },
                })

                if (saved) this.personalTimetableSavedInEditor = true

                return saved
            } finally {
                this.personalTimetableSaving = false
            }
        },

        toggleModuleCourse(course) {
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
        },

        selectAllModuleCourses() {
            const moduleSelectionKey = String(this.moduleCourseDialogModule?.selection_key || '').trim()
            const courseKeys = this.moduleCourseDialogCourses
                .flatMap(course => normalizedCourseSelectionKeys(course))
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
        },

        deselectAllModuleCourses() {
            const moduleSelectionKey = String(this.moduleCourseDialogModule?.selection_key || '').trim()
            const courseKeys = this.moduleCourseDialogCourses
                .flatMap(course => normalizedCourseSelectionKeys(course))
            if (!moduleSelectionKey || !courseKeys.length) return

            this.selectedCourseKeys = this.selectedCourseKeys
                .filter(courseKey => !courseKeys.includes(courseKey))
            this.selectedModuleKeys = this.selectedModuleKeys
                .filter(selectionKey => selectionKey !== moduleSelectionKey)
            this.moduleSelectionLimitMessage = ''
        },

        selectAllModulesInGroup(group) {
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
        },

        deselectAllModulesInGroup(group) {
            const moduleSelectionKeys = this.moduleSelectionKeysForGroup(group)
            const courseSelectionKeys = this.courseSelectionKeysForGroup(group)
            if (!moduleSelectionKeys.length && !courseSelectionKeys.length) return

            this.selectedModuleKeys = this.selectedModuleKeys
                .filter(moduleSelectionKey => !moduleSelectionKeys.includes(moduleSelectionKey))
            this.selectedCourseKeys = this.selectedCourseKeys
                .filter(courseSelectionKey => !courseSelectionKeys.includes(courseSelectionKey))
            this.moduleSelectionLimitMessage = ''
        },

        removeSelectedModule(module) {
            const moduleSelectionKey = String(module?.selection_key || '').trim()
            const courseKeys = (Array.isArray(module?.courses) ? module.courses : [])
                .flatMap(course => normalizedCourseSelectionKeys(course))
            if (!moduleSelectionKey) return

            this.selectedModuleKeys = this.selectedModuleKeys
                .filter(selectedModuleKey => selectedModuleKey !== moduleSelectionKey)
            this.selectedCourseKeys = this.selectedCourseKeys
                .filter(courseKey => !courseKeys.includes(courseKey))
            this.moduleSelectionLimitMessage = ''
        },

        deselectAllSelectedModules() {
            this.selectedModuleKeys = []
            this.selectedCourseKeys = []
            this.moduleSelectionLimitMessage = ''
        },

        openStudentInfoDialog() {
            if (!this.currentSelectionStudent) return

            this.studyInfoDialogOpen = false
            this.studentInfoDialogOpen = true
        },

        openStudyInfoDialog() {
            if (!this.currentSelectionStudent) return

            this.studentInfoDialogOpen = false
            this.studyInfoDialogOpen = true
        },

        openStudentSelectionDialog(field) {
            if (this.studentSelectionSaving || !field?.options?.length) return

            this.studentSelectionDraftKey = field.key
            this.studentSelectionDraftLabel = field.label
            this.studentSelectionDraftValue = field.selectedValue
            this.studentSelectionDialogOpen = true
        },

        startTimetableFrom(source) {
            const selectedOption = this.timetableStartOptions.find(option => option.key === source)

            if (!selectedOption?.available) return

            if (source === 'empty') {
                this.resetStudentTimetablePlanning()
                this.$router.push('/students-timetables/create')

                return
            }

            if (!['personal', 'published'].includes(source)) return

            this.initializeSavedTimetableAdoption(source)

            this.$router.push({
                path: '/students-timetables/create/adoption',
                query: {
                    manual_timetable: source,
                },
            })
        },

        initializeSavedTimetableAdoption(source) {
            const savedTimetable = {
                personal: this.overview?.personal_timetable,
                published: this.overview?.published_timetable,
            }[source]

            this.activeManualModuleGroupKey = ''
            this.adoptionRemovedCourseKeys = []
            this.manualPendingCourseKeys = []
            this.manualSelectedCourseKeys = []
            this.personalTimetableSavedInEditor = false
            this.savedTimetableAdoptionBase = null
            this.savedTimetableAdoptionCourseKeys = []
            this.savedTimetableAdoptionModuleKeys = []

            if (!savedTimetable?.id) {
                this.timetableCalculationStatus = 'error'
                this.timetableCalculationError = 'Dieser Stundenplan ist nicht mehr verfügbar.'

                return false
            }

            const savedState = savedTimetable.state
                && typeof savedTimetable.state === 'object'
                && !Array.isArray(savedTimetable.state)
                ? savedTimetable.state
                : {}
            const savedModuleSelection = savedState.moduleSelection
                && typeof savedState.moduleSelection === 'object'
                && !Array.isArray(savedState.moduleSelection)
                ? savedState.moduleSelection
                : {}
            const savedManualDraft = savedState.manualTimetableDraft
                && typeof savedState.manualTimetableDraft === 'object'
                && !Array.isArray(savedState.manualTimetableDraft)
                ? savedState.manualTimetableDraft
                : {}
            const removedSavedCourseKeys = new Set(normalizedCourseKeyList(savedManualDraft.removedCourseKeys))
            const explicitSavedCourseKeys = new Set(normalizedCourseKeyList([
                ...(Array.isArray(savedTimetable.active_course_group_keys)
                    ? savedTimetable.active_course_group_keys
                    : []),
                ...(Array.isArray(savedState.activeCourseGroupFilterKeys)
                    ? savedState.activeCourseGroupFilterKeys
                    : []),
                ...(Array.isArray(savedManualDraft.selectedCourseKeys)
                    ? savedManualDraft.selectedCourseKeys
                    : []),
            ]).filter(courseKey => !removedSavedCourseKeys.has(courseKey)))
            const savedCourseIdentities = savedTimetableCourseIdentities(savedTimetable.timetable)
            const matchingCourses = this.manualCatalogCourses.filter((course) => {
                const courseKeys = new Set(normalizedCourseTimetableKeys(course))
                const matchesExplicitCourseKey = [...courseKeys]
                    .some(courseKey => explicitSavedCourseKeys.has(courseKey))
                const matchesSavedCourse = catalogCourseTimetableIdentities(course)
                    .some(courseIdentity => savedCourseIdentities.has(courseIdentity))

                return savedCourseIdentities.size
                    ? matchesSavedCourse
                    : matchesExplicitCourseKey
            })
            const matchingCourseKeys = new Set(matchingCourses.flatMap(normalizedCourseTimetableKeys))
            const catalogModules = [...this.moduleSelectionGroups, ...this.mainModuleSelectionGroups]
                .flatMap(group => Array.isArray(group?.modules) ? group.modules : [])
            const catalogModuleKeys = new Set(catalogModules
                .map(module => String(module?.selection_key || '').trim())
                .filter(Boolean))
            const matchingModuleKeys = catalogModules
                .filter(module => (Array.isArray(module?.courses) ? module.courses : [])
                    .some(course => normalizedCourseTimetableKeys(course)
                        .some(courseKey => matchingCourseKeys.has(courseKey))))
                .map(module => String(module?.selection_key || '').trim())

            this.savedTimetableAdoptionCourseKeys = [...new Set(
                matchingCourses.flatMap(normalizedCourseSelectionKeys),
            )]
            this.savedTimetableAdoptionModuleKeys = normalizedCourseKeyList([
                ...(Array.isArray(savedModuleSelection.selectedKeys) ? savedModuleSelection.selectedKeys : []),
                ...matchingModuleKeys,
            ]).filter(moduleKey => catalogModuleKeys.has(moduleKey))
            this.savedTimetableAdoptionBase = savedTimetableForManualEditor(savedTimetable.timetable, source)

            this.timetableCalculationError = ''
            this.timetableCalculationStatus = this.savedTimetableAdoptionBase ? 'success' : 'error'

            return this.timetableCalculationStatus === 'success'
        },

        resetStudentTimetablePlanning() {
            this.timetableCalculationRequestId += 1
            this.timetablePageRequestId += 1
            this.activeModuleGroupKey = ''
            this.activeManualModuleGroupKey = ''
            this.adoptionRemovedCourseKeys = []
            this.manualModuleCatalogView = 'student'
            this.manualPendingCourseKeys = []
            this.manualSelectedCourseKeys = []
            this.moduleCourseDialogModule = null
            this.moduleCoursesDialogManual = false
            this.moduleCoursesDialogOpen = false
            this.moduleSelectionLimitMessage = ''
            this.personalTimetableSavedInEditor = false
            this.scheduleCreationMode = null
            this.savedTimetableAdoptionBase = null
            this.savedTimetableAdoptionCourseKeys = []
            this.savedTimetableAdoptionModuleKeys = []
            this.selectedCourseKeys = []
            this.selectedModuleKeys = []
            this.timetableCalculationCheckedCombinationCount = 0
            this.timetableCalculationCombinationCount = 0
            this.timetableCalculationError = ''
            this.timetableCalculationProgressPercent = 0
            this.timetableCalculationProgressPhase = 'preparing'
            this.timetableCalculationResult = null
            this.timetableCalculationStatus = 'idle'
            this.timetableFilters = { ...DEFAULT_TIMETABLE_FILTERS }
            this.timetablePageError = ''
            this.timetablePageLoading = false
            this.timetablePageLoadingDirection = ''
            this.timetableSelectedIndex = 0
            this.timetableWorkspaceId = ''
        },

        restartStudentTimetablePlanning() {
            if (this.timetableCalculationStatus === 'calculating' || this.timetablePageLoading) return

            this.resetStudentTimetablePlanning()
            this.$router.replace('/students-timetables/overview')
        },

        goBackToOverview() {
            this.$router.push('/students-timetables/overview')
        },

        goBackToModuleSelection() {
            this.timetablePageRequestId += 1
            this.$router.push('/students-timetables/create')
        },

        goBackToTimetableResults() {
            if (this.savedTimetableAdoptionSource) {
                this.$router.push('/students-timetables/overview')

                return
            }

            const workspaceId = normalizedWorkspaceId(
                this.timetableWorkspaceId || this.$route?.query?.workspace_id,
            )
            const fingerprint = String(
                this.timetableCalculationResult?.fingerprint || this.$route?.query?.fingerprint || '',
            ).trim()

            this.$router.push({
                path: '/students-timetables/create/results',
                query: {
                    ...(workspaceId ? { workspace_id: workspaceId } : {}),
                    ...(/^[a-f0-9]{64}$/.test(fingerprint) ? { fingerprint } : {}),
                },
            })
        },

        async openAutomaticTimetableResults() {
            if (
                this.timetableCalculationStatus === 'calculating'
                || this.selectedModuleKeys.length === 0
                || this.selectedCourseKeys.length === 0
            ) return

            this.timetableWorkspaceId = createWorkspaceId()
            this.timetableFilters = { ...DEFAULT_TIMETABLE_FILTERS }

            await this.$router.push({
                path: '/students-timetables/create/results',
                query: { workspace_id: this.timetableWorkspaceId },
            })
            await this.calculateStudentTimetables()
        },

        timetableCalculationErrorMessage(error) {
            const validationErrors = error?.response?.data?.errors
            const firstValidationMessage = validationErrors && typeof validationErrors === 'object'
                ? Object.values(validationErrors)
                    .flatMap(messages => Array.isArray(messages) ? messages : [messages])
                    .find(message => String(message || '').trim() !== '')
                : ''

            return String(firstValidationMessage || error?.response?.data?.message || error?.message || '').trim()
                || 'Bitte versuche die Berechnung erneut.'
        },

        async calculateStudentTimetables() {
            if (
                this.timetableCalculationStatus === 'calculating'
                || !this.isTimetableResultsPage
                || !normalizedWorkspaceId(this.timetableWorkspaceId)
                || this.selectedModuleKeys.length === 0
                || this.selectedCourseKeys.length === 0
            ) return

            const requestId = this.timetableCalculationRequestId + 1

            this.timetableCalculationRequestId = requestId
            this.timetablePageRequestId += 1
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

            try {
                const response = await fetchStudentTimetableCalculation({
                    workspace_id: this.timetableWorkspaceId,
                    modules: [...this.selectedModuleKeys],
                    selected_course_keys: [...this.selectedCourseKeys],
                })
                const calculationResult = await consumeTimetableCalculationStream(response, (event) => {
                    if (requestId !== this.timetableCalculationRequestId) return

                    const combinationCount = normalizedTimetableCalculationCount(event.combination_count)
                    const progressPercent = Math.min(
                        100,
                        Math.max(0, normalizedTimetableCalculationCount(event.progress_percent)),
                    )

                    this.timetableCalculationCombinationCount = combinationCount
                    this.timetableCalculationCheckedCombinationCount = Math.min(
                        combinationCount,
                        normalizedTimetableCalculationCount(event.checked_combination_count),
                    )
                    this.timetableCalculationProgressPercent = Math.max(
                        this.timetableCalculationProgressPercent,
                        progressPercent,
                    )
                    this.timetableCalculationProgressPhase = normalizedTimetableCalculationProgressPhase(event.phase)
                })

                if (requestId !== this.timetableCalculationRequestId) return

                const timetablePageMeta = normalizedTimetablePageMeta(calculationResult)

                if (
                    !isTimetableCalculationResult(calculationResult)
                    || !timetablePageMeta
                    || timetablePageMeta.currentPage !== 1
                    || timetablePageMeta.offset !== 0
                    || !timetableFiltersMatch(timetablePageMeta.filters, DEFAULT_TIMETABLE_FILTERS)
                ) {
                    throw new TypeError('The timetable calculation response is inconsistent.')
                }

                this.timetableCalculationResult = calculationResult
                this.timetableCalculationCombinationCount = normalizedTimetableCalculationCount(
                    calculationResult.summary.timetable_variation_count,
                )
                this.timetableCalculationCheckedCombinationCount = this.timetableCalculationCombinationCount
                this.timetableCalculationProgressPercent = 100
                this.timetableCalculationProgressPhase = 'complete'
                this.timetableCalculationStatus = 'success'

                await this.$router.replace({
                    path: '/students-timetables/create/results',
                    query: {
                        workspace_id: this.timetableWorkspaceId,
                        fingerprint: calculationResult.fingerprint,
                    },
                })
            } catch (error) {
                if (requestId !== this.timetableCalculationRequestId) return

                this.timetableCalculationStatus = 'error'
                this.timetableCalculationError = this.timetableCalculationErrorMessage(error)
            }
        },

        async openStudentTimetableAdoption() {
            if (
                this.timetableCalculationStatus !== 'success'
                || this.timetablePageLoading
                || !this.selectedTimetableResult
            ) return

            const workspaceId = normalizedWorkspaceId(this.timetableWorkspaceId)
            const fingerprint = String(this.timetableCalculationResult?.fingerprint || '').trim()
            const timetableIndex = Number(this.timetableSelectedIndex)
            const timetableKey = String(this.selectedTimetableResult?.key || '').trim()

            if (
                !workspaceId
                || !/^[a-f0-9]{64}$/.test(fingerprint)
                || !Number.isInteger(timetableIndex)
                || timetableIndex < 0
                || !timetableKey
            ) return

            await this.$router.push({
                path: '/students-timetables/create/adoption',
                query: {
                    workspace_id: workspaceId,
                    fingerprint,
                    timetable_index: String(timetableIndex),
                    timetable_key: timetableKey,
                },
            })
            await this.restoreManualTimetableDraft()
        },

        async restoreStudentTimetableResults() {
            const workspaceId = normalizedWorkspaceId(this.$route?.query?.workspace_id)

            if (!workspaceId) {
                this.timetableCalculationStatus = 'error'
                this.timetableCalculationError = 'Dieses Stundenplanergebnis ist nicht mehr verfügbar. Bitte starte eine neue Berechnung.'

                return
            }

            this.timetableWorkspaceId = workspaceId
            this.timetableCalculationStatus = 'calculating'
            this.timetableCalculationProgressPhase = 'preparing'
            this.timetableCalculationProgressPercent = 0

            const fingerprint = String(this.$route?.query?.fingerprint || '').trim()
            const timetableIndex = this.isTimetableAdoptionPage
                ? Number(this.$route?.query?.timetable_index)
                : 0
            const timetableKey = this.isTimetableAdoptionPage
                ? String(this.$route?.query?.timetable_key || '').trim()
                : ''
            const adoptionSelectionValid = !this.isTimetableAdoptionPage
                || (
                    /^[a-f0-9]{64}$/.test(fingerprint)
                    && Number.isInteger(timetableIndex)
                    && timetableIndex >= 0
                    && timetableKey !== ''
                )

            if (!adoptionSelectionValid) {
                this.timetableCalculationStatus = 'error'
                this.timetableCalculationError = 'Dieses Stundenplanergebnis ist nicht mehr verfügbar. Bitte starte eine neue Berechnung.'

                return
            }

            const page = Math.floor(timetableIndex / TIMETABLES_PER_PAGE) + 1
            const restored = await this.loadStudentTimetablePage(page, timetableIndex, '', fingerprint)
            const selectedTimetableMatches = !this.isTimetableAdoptionPage
                || String(this.selectedTimetableResult?.key || '').trim() === timetableKey

            if (restored && selectedTimetableMatches) {
                this.timetableCalculationProgressPercent = 100
                this.timetableCalculationProgressPhase = 'complete'
                this.timetableCalculationStatus = 'success'

                return
            }

            if (this.timetableCalculationStatus !== 'error') {
                this.timetableCalculationStatus = 'error'
                this.timetableCalculationError = 'Dieses Stundenplanergebnis ist nicht mehr verfügbar. Bitte starte eine neue Berechnung.'
            }
        },

        async updateTimetableFilter(filterKey, filterValue) {
            const filterValueIsValid = filterKey === 'include_saturday'
                ? typeof filterValue === 'boolean'
                : filterKey === 'free_days'
                    && (filterValue === null
                        || (Number.isInteger(filterValue)
                            && filterValue >= 1
                            && filterValue <= MAXIMUM_FREE_DAYS))

            if (
                !Object.prototype.hasOwnProperty.call(DEFAULT_TIMETABLE_FILTERS, filterKey)
                || !filterValueIsValid
                || this.timetableCalculationStatus !== 'success'
                || this.timetablePageLoading
            ) return

            const previousFilters = normalizedTimetableFilters(this.timetableFilters)
            const nextFilters = { ...previousFilters, [filterKey]: filterValue }

            if (timetableFiltersMatch(previousFilters, nextFilters)) return

            this.timetableFilters = nextFilters

            const filtersApplied = await this.loadStudentTimetablePage(1, 0)

            if (!filtersApplied) this.timetableFilters = previousFilters
        },

        async selectTimetable(targetIndexValue) {
            const targetIndex = Number(targetIndexValue)
            const currentMeta = normalizedTimetablePageMeta(this.timetableCalculationResult)

            if (
                this.timetableCalculationStatus !== 'success'
                || this.timetablePageLoading
                || !Number.isInteger(targetIndex)
                || !currentMeta
                || targetIndex < 0
                || targetIndex >= currentMeta.total
            ) return

            const currentTimetables = this.timetableCalculationResult.timetables
            const targetIsOnCurrentPage = targetIndex >= currentMeta.offset
                && targetIndex < currentMeta.offset + currentTimetables.length

            if (targetIsOnCurrentPage) {
                this.timetableSelectedIndex = targetIndex
                this.timetablePageError = ''

                return
            }

            const targetPage = Math.floor(targetIndex / TIMETABLES_PER_PAGE) + 1
            const direction = targetIndex > this.timetableSelectedIndex ? 'next' : 'previous'

            await this.loadStudentTimetablePage(targetPage, targetIndex, direction)
        },

        async loadStudentTimetablePage(page, targetIndex = 0, loadingDirection = '', routeFingerprint = '') {
            const workspaceId = normalizedWorkspaceId(this.timetableWorkspaceId)
            const activeFingerprint = String(
                routeFingerprint || this.timetableCalculationResult?.fingerprint || '',
            ).trim()

            if (!workspaceId || (page > 1 && !/^[a-f0-9]{64}$/.test(activeFingerprint))) return false

            const requestId = this.timetablePageRequestId + 1

            this.timetablePageRequestId = requestId
            this.timetablePageLoading = true
            this.timetablePageLoadingDirection = loadingDirection
            this.timetablePageError = ''

            try {
                const response = await axios.get(showStudentTimetableV3Timetable.url({
                    query: {
                        workspace_id: workspaceId,
                        page,
                        ...(activeFingerprint ? { fingerprint: activeFingerprint } : {}),
                        filters: normalizedTimetableFilters(this.timetableFilters),
                    },
                }))

                if (requestId !== this.timetablePageRequestId) return false

                const calculationResult = response.data?.data
                const timetablePageMeta = normalizedTimetablePageMeta(calculationResult)

                if (
                    !isTimetableCalculationResult(calculationResult)
                    || !timetablePageMeta
                    || timetablePageMeta.currentPage !== page
                    || targetIndex < timetablePageMeta.offset
                    || (timetablePageMeta.total > 0
                        && targetIndex >= timetablePageMeta.offset + calculationResult.timetables.length)
                    || !timetableFiltersMatch(timetablePageMeta.filters, this.timetableFilters)
                    || (activeFingerprint && calculationResult.fingerprint !== activeFingerprint)
                ) {
                    throw new TypeError('The timetable page response is inconsistent.')
                }

                this.timetableCalculationResult = calculationResult
                this.timetableSelectedIndex = timetablePageMeta.total > 0 ? targetIndex : 0
                this.timetablePageError = ''

                return true
            } catch (error) {
                if (requestId !== this.timetablePageRequestId) return false

                if ([401, 403, 419, 422].includes(Number(error?.response?.status || 0))) {
                    this.timetableCalculationResult = null
                    this.timetableCalculationStatus = 'error'
                    this.timetableCalculationError = 'Dieses Ergebnis ist nicht mehr verfügbar. Bitte starte eine neue Berechnung.'
                    this.timetableSelectedIndex = 0

                    return false
                }

                this.timetablePageError = loadingDirection === 'previous'
                    ? 'Die vorherigen Stundenpläne konnten nicht geladen werden. Bitte versuche es erneut.'
                    : loadingDirection === 'next'
                        ? 'Die nächsten Stundenpläne konnten nicht geladen werden. Bitte versuche es erneut.'
                        : 'Die Stundenpläne konnten nicht geladen werden. Bitte versuche es erneut.'

                return false
            } finally {
                if (requestId === this.timetablePageRequestId) {
                    this.timetablePageLoading = false
                    this.timetablePageLoadingDirection = ''
                }
            }
        },

        async restoreStudentSelectionDefaults() {
            if (this.studentSelectionSaving || !this.hasStudentSelectionOverride) return

            this.studentSelectionSaving = true

            try {
                await this.studentTimetablesStore.restoreProfileSelection()
            } finally {
                this.studentSelectionSaving = false
            }
        },

        async saveStudentSelection() {
            if (!this.studentSelectionDraftValid) return

            const selection = {
                ...(this.overview?.selection_override || {}),
                [this.studentSelectionDraftKey]: this.studentSelectionDraftValue ?? null,
            }

            this.studentSelectionSaving = true

            try {
                const saved = await this.studentTimetablesStore.updateProfileSelection(selection)

                if (!saved) return

                this.studentSelectionDialogOpen = false
                this.studentSelectionDraftKey = ''
                this.studentSelectionDraftLabel = ''
                this.studentSelectionDraftValue = null
            } finally {
                this.studentSelectionSaving = false
            }
        },

        async handleLogout() {
            this.showDrawer = false
            await this.studentTimetablesStore.logout()
            this.$router.push('/homepage/students-timetables')
        },
    },
}
</script>

<style scoped>
.students-timetables-overview-v2-page {
    width: 100%;
    padding-inline: clamp(12px, 2vw, 32px);
}

.students-timetables-overview-v2-page > .hero {
    width: 100%;
    max-width: none;
}

.overview-v2-page-loader {
    position: relative;
    z-index: 1;
    display: grid;
    min-height: calc(100dvh - 84px);
    place-content: center;
    justify-items: center;
    gap: 16px;
    color: #233d4c;
    font-size: 1rem;
    font-weight: 700;
}

.overview-v2-page-loader__dots {
    padding: 0;
}

.overview-v2-hero {
    padding-block: clamp(16px, 2vw, 28px);
}

.overview-v2-panel {
    position: relative;
    color: #163247;
    overflow: hidden;
    border-top: 5px solid #fd802e;
    border-radius: 24px;
    background: rgba(250, 252, 253, 0.97);
    box-shadow: 0 22px 60px rgba(2, 16, 26, 0.24);
    backdrop-filter: blur(12px);
}

.overview-v2-panel::after {
    position: absolute;
    top: -140px;
    right: -90px;
    width: 360px;
    height: 360px;
    content: '';
    border-radius: 999px;
    background: radial-gradient(circle, rgba(253, 128, 46, 0.15), rgba(253, 128, 46, 0));
    pointer-events: none;
}

.overview-v2-navigation {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 18px;
    padding: 12px 20px;
    border-bottom: 1px solid #e5eaee;
}

.overview-v2-wordmark {
    font-size: 0.86rem;
    font-weight: 850;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.overview-v2-text-button {
    justify-self: start;
    color: #163247;
    font-weight: 750;
    text-transform: none;
}

.overview-v2-navigation__actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 4px;
}

.overview-v2-menu-button {
    color: #163247;
    border-radius: 12px;
}

.overview-v2-introduction {
    position: relative;
    z-index: 1;
    padding: clamp(30px, 4vw, 56px) clamp(24px, 4vw, 58px) 28px;
}

.overview-v2-creation-mode-page {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    padding: 16px clamp(24px, 4vw, 58px) clamp(34px, 5vw, 64px);
}

.overview-v2-creation-mode-actions {
    display: flex;
    gap: 16px;
    align-items: center;
    justify-content: flex-end;
    padding-top: 24px;
}

.overview-v2-creation-mode-actions--split {
    justify-content: space-between;
}

.overview-v2-creation-mode-workspace {
    padding: clamp(22px, 3vw, 34px);
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid #dfe3f0;
    border-radius: 20px;
    box-shadow: 0 14px 36px rgba(30, 36, 51, 0.1);
}

.overview-v2-creation-mode-heading {
    display: grid;
    gap: 6px;
}

.overview-v2-module-selected-total {
    justify-self: end;
    padding: 5px 10px;
    color: #4338ca;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 750;
    white-space: nowrap;
}

.overview-v2-module-selected-total strong {
    margin-right: 3px;
    font-size: 0.94rem;
}

.overview-v2-creation-mode-eyebrow {
    color: #c2410c;
    font-size: 0.74rem;
    font-weight: 850;
    letter-spacing: 0.11em;
    text-transform: uppercase;
}

.overview-v2-creation-mode-heading h1 {
    max-width: 900px;
    margin: 0;
    color: #1e2433;
    font-size: clamp(1.55rem, 3vw, 2.25rem);
    line-height: 1.15;
    letter-spacing: -0.035em;
}

.overview-v2-creation-mode-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 24px;
    transition: grid-template-columns 170ms ease;
}

.overview-v2-creation-mode-options--automatic-selected {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
}

.overview-v2-creation-mode-card {
    --creation-mode-accent: #4338ca;
    --creation-mode-accent-rgb: 67, 56, 202;
    --creation-mode-soft: #eef2ff;
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 14px 16px;
    align-items: center;
    align-content: start;
    min-width: 0;
    min-height: 220px;
    padding: 22px;
    color: #1e293b;
    font: inherit;
    text-align: left;
    cursor: pointer;
    background: linear-gradient(145deg, var(--creation-mode-soft), #fff 68%);
    border: 2px solid rgba(var(--creation-mode-accent-rgb), 0.3);
    border-radius: 18px;
    overflow: hidden;
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        box-shadow 160ms ease,
        background 160ms ease;
}

.overview-v2-creation-mode-card::before {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    height: 6px;
    content: '';
    background: var(--creation-mode-accent);
}

.overview-v2-creation-mode-card--manual {
    --creation-mode-accent: #c2410c;
    --creation-mode-accent-rgb: 194, 65, 12;
    --creation-mode-soft: #fff7ed;
    background: #fff;
    border-color: #e2e8f0;
}

.overview-v2-creation-mode-card--manual:disabled {
    cursor: default;
    opacity: 1;
}

.overview-v2-creation-mode-input {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    opacity: 0;
}

.overview-v2-creation-mode-card:not(:disabled):hover,
.overview-v2-creation-mode-card:focus-within {
    background: #fff;
    border-color: var(--creation-mode-accent);
    outline: 3px solid rgba(var(--creation-mode-accent-rgb), 0.16);
    outline-offset: 2px;
    box-shadow: 0 14px 32px rgba(var(--creation-mode-accent-rgb), 0.18);
    transform: translateY(-2px);
}

.overview-v2-creation-mode-card--selected {
    color: var(--creation-mode-accent);
    background: linear-gradient(
        135deg,
        rgba(var(--creation-mode-accent-rgb), 0.2),
        var(--creation-mode-soft) 62%,
        #fff
    );
    border-color: var(--creation-mode-accent);
    box-shadow:
        inset 0 0 0 2px rgba(var(--creation-mode-accent-rgb), 0.22),
        0 15px 34px rgba(var(--creation-mode-accent-rgb), 0.22);
}

.overview-v2-creation-mode-card--selected .overview-v2-creation-mode-title {
    color: var(--creation-mode-accent);
}

.overview-v2-creation-mode-card--selected .overview-v2-creation-mode-icon {
    color: #fff;
    background: var(--creation-mode-accent);
    border-color: var(--creation-mode-accent);
    box-shadow: 0 6px 16px rgba(var(--creation-mode-accent-rgb), 0.24);
}

.overview-v2-creation-mode-icon {
    display: inline-grid;
    place-items: center;
    width: 58px;
    height: 58px;
    color: var(--creation-mode-accent);
    background: var(--creation-mode-soft);
    border: 1px solid rgba(var(--creation-mode-accent-rgb), 0.2);
    border-radius: 15px;
}

.overview-v2-creation-mode-copy {
    display: grid;
    grid-template-rows: 27px auto auto;
    gap: 5px;
    min-width: 0;
}

.overview-v2-creation-mode-kickers {
    display: flex;
    grid-row: 1;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
}

.overview-v2-creation-mode-recommendation {
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

.overview-v2-creation-mode-title {
    grid-row: 2;
    color: #1e293b;
    font-size: 1.15rem;
    font-weight: 850;
}

.overview-v2-creation-mode-description {
    grid-row: 3;
    max-width: 48ch;
    color: #475569;
    font-size: 0.92rem;
    line-height: 1.5;
}

.overview-v2-creation-mode-status {
    display: inline-flex;
    grid-column: 1 / -1;
    gap: 8px;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 8px 12px;
    color: var(--creation-mode-accent);
    background: rgba(255, 255, 255, 0.76);
    border: 1px solid rgba(var(--creation-mode-accent-rgb), 0.36);
    border-radius: 11px;
    font-size: 0.86rem;
    font-weight: 850;
}

.overview-v2-creation-mode-card--selected .overview-v2-creation-mode-status {
    color: #fff;
    background: var(--creation-mode-accent);
    border-color: var(--creation-mode-accent);
    box-shadow: 0 6px 16px rgba(var(--creation-mode-accent-rgb), 0.22);
}

.overview-v2-creation-mode-selected-modules {
    grid-column: 1 / -1;
    margin-top: 2px;
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(var(--creation-mode-accent-rgb), 0.2);
}

.overview-v2-selected-modules {
    display: grid;
    gap: 8px;
    padding: 11px 13px;
    background: rgba(248, 250, 252, 0.92);
    border: 1px solid #dbe3ef;
    border-radius: 12px;
}

.overview-v2-selected-modules-heading {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
    justify-content: space-between;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 850;
}

.overview-v2-selected-modules-heading-label {
    display: inline-flex;
    gap: 7px;
    align-items: center;
}

.overview-v2-selected-modules-summary {
    padding: 3px 8px;
    color: var(--creation-mode-accent);
    white-space: nowrap;
    background: rgba(var(--creation-mode-accent-rgb), 0.1);
    border: 1px solid rgba(var(--creation-mode-accent-rgb), 0.2);
    border-radius: 999px;
}

.overview-v2-module-selection-limit-hint {
    display: flex;
    gap: 6px;
    align-items: center;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 700;
}

.overview-v2-module-selection-limit-alert {
    cursor: default;
}

.overview-v2-selected-modules-list {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.overview-v2-selected-module-chip :deep(.v-chip__close) {
    color: #dc2626;
    opacity: 1;
}

.overview-v2-selected-modules-empty {
    color: #64748b;
    font-size: 0.78rem;
}

.overview-v2-selected-modules-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: flex-start;
    padding-top: 8px;
    border-top: 1px solid rgba(var(--creation-mode-accent-rgb), 0.14);
}

.overview-v2-schedule-create-button {
    position: relative;
    z-index: 1;
    min-width: min(100%, 250px);
    color: #fff;
    background: linear-gradient(135deg, #4338ca, #6366f1);
    box-shadow: 0 12px 26px rgba(67, 56, 202, 0.34);
    font-size: 0.94rem;
    font-weight: 900;
    letter-spacing: 0.015em;
}

@media (min-width: 681px) {
    .overview-v2-selected-modules-actions {
        justify-content: space-between;
    }
}

.overview-v2-module-group-cards {
    display: flex;
    gap: 9px;
    margin-top: 13px;
}

.overview-v2-module-group-card {
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
    color: #344054;
    font: inherit;
    text-align: left;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.84);
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: 14px;
    transition:
        flex-grow 170ms ease,
        transform 170ms ease,
        border-color 170ms ease,
        box-shadow 170ms ease,
        background 170ms ease;
}

.overview-v2-module-group-card:hover,
.overview-v2-module-group-card:focus-visible {
    background: #fff;
    border-color: var(--module-group-accent);
    outline: none;
    box-shadow: 0 10px 24px rgba(var(--module-group-accent-rgb), 0.16);
    transform: translateY(-2px);
}

.overview-v2-module-group-card--active {
    flex-grow: 1.5;
    color: #15173b;
    background: linear-gradient(145deg, #fff 10%, var(--module-group-soft) 100%);
    border-color: var(--module-group-accent);
    box-shadow: 0 11px 26px rgba(var(--module-group-accent-rgb), 0.16);
}

.overview-v2-module-group-card--finished,
.overview-v2-module-group-panel--finished {
    --module-group-accent: #2563eb;
    --module-group-accent-rgb: 37, 99, 235;
    --module-group-soft: #eff6ff;
}

.overview-v2-module-group-card--negative,
.overview-v2-module-group-panel--negative {
    --module-group-accent: #dc2626;
    --module-group-accent-rgb: 220, 38, 38;
    --module-group-soft: #fef2f2;
}

.overview-v2-module-group-card--previous,
.overview-v2-module-group-panel--previous {
    --module-group-accent: #d97706;
    --module-group-accent-rgb: 217, 119, 6;
    --module-group-soft: #fffbeb;
}

.overview-v2-module-group-card--current,
.overview-v2-module-group-panel--current {
    --module-group-accent: #16a34a;
    --module-group-accent-rgb: 22, 163, 74;
    --module-group-soft: #f0fdf4;
}

.overview-v2-module-group-card--additional,
.overview-v2-module-group-panel--additional {
    --module-group-accent: #7c3aed;
    --module-group-accent-rgb: 124, 58, 237;
    --module-group-soft: #f5f3ff;
}

.overview-v2-module-group-card-topline {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
}

.overview-v2-module-group-card-icon {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    color: var(--module-group-accent);
    background: var(--module-group-soft);
    border: 1px solid rgba(var(--module-group-accent-rgb), 0.15);
    border-radius: 10px;
    transition: transform 170ms ease;
}

.overview-v2-module-group-card:hover .overview-v2-module-group-card-icon,
.overview-v2-module-group-card:focus-visible .overview-v2-module-group-card-icon {
    transform: scale(1.06);
}

.overview-v2-module-group-card-title {
    overflow: hidden;
    margin-top: 1px;
    color: #1e293b;
    font-size: 0.84rem;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.overview-v2-module-group-card-count {
    flex: 0 0 auto;
    padding: 3px 7px;
    color: #475467;
    background: rgba(241, 245, 249, 0.95);
    border: 1px solid rgba(203, 213, 225, 0.85);
    border-radius: 999px;
    font-size: 0.67rem;
    font-weight: 800;
}

.overview-v2-module-group-card--active .overview-v2-module-group-card-count {
    color: var(--module-group-accent);
    background: #fff;
    border-color: rgba(var(--module-group-accent-rgb), 0.28);
}

.overview-v2-module-group-card-description {
    display: -webkit-box;
    overflow: hidden;
    color: #64748b;
    font-size: 0.7rem;
    line-height: 1.35;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.overview-v2-module-group-card-active-mark {
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

.overview-v2-module-group-card--active .overview-v2-module-group-card-active-mark {
    opacity: 1;
    transform: scaleX(1);
}

.overview-v2-module-group-panel {
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

.overview-v2-module-group-panel-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
    justify-content: flex-end;
    margin-bottom: 12px;
}

.overview-v2-module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 8px;
}

.overview-v2-module-tile {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr);
    gap: 9px;
    align-items: center;
    min-height: 60px;
    padding: 9px 10px;
    color: #1f2937;
    font: inherit;
    text-align: left;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #dbe1eb;
    border-radius: 12px;
    transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.overview-v2-module-tile:hover,
.overview-v2-module-tile:focus-visible {
    background: #fff;
    border-color: var(--module-group-accent);
    outline: none;
    box-shadow: 0 9px 22px rgba(var(--module-group-accent-rgb), 0.13);
    transform: translateY(-1px);
}

.overview-v2-module-tile--selected {
    background: linear-gradient(135deg, var(--module-group-soft), #fff);
    border-color: var(--module-group-accent);
    box-shadow: inset 0 0 0 1px rgba(var(--module-group-accent-rgb), 0.12);
}

.overview-v2-module-check {
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

.overview-v2-module-tile--selected .overview-v2-module-check {
    color: #fff;
    background: var(--module-group-accent);
    border-color: var(--module-group-accent);
}

.overview-v2-module-main {
    display: grid;
    min-width: 0;
}

.overview-v2-module-code {
    color: #111827;
    font-size: 0.88rem;
    font-weight: 850;
}

.overview-v2-module-name {
    overflow: hidden;
    color: #667085;
    font-size: 0.68rem;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.overview-v2-module-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 3px 6px;
    align-items: center;
    margin-top: 3px;
    color: #667085;
    font-size: 0.62rem;
    font-weight: 700;
}

.overview-v2-module-status,
.overview-v2-module-course-count {
    padding: 1px 5px;
    color: #3730a3;
    background: #e0e7ff;
    border-radius: 999px;
}

.overview-v2-module-list-empty {
    padding: 8px 10px;
    color: #667085;
    background: #f9fafb;
    border-radius: 10px;
    font-size: 0.76rem;
}

.overview-v2-module-panel-enter-active,
.overview-v2-module-panel-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.overview-v2-module-panel-enter-from {
    opacity: 0;
    transform: translateY(7px);
}

.overview-v2-module-panel-leave-to {
    opacity: 0;
    transform: translateY(-3px);
}

.overview-v2-module-courses-dialog {
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.overview-v2-module-courses-dialog :deep(.v-card-text) {
    max-height: min(65vh, 620px);
}

.overview-v2-module-courses-dialog-title {
    color: #1e1b4b;
    font-size: 1.2rem;
    font-weight: 850;
}

.overview-v2-module-courses-dialog-icon {
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

.overview-v2-module-courses-dialog-subtitle {
    margin-bottom: 8px;
    color: #475467;
    font-size: 0.92rem;
    font-weight: 700;
}

.overview-v2-module-courses-dialog-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 14px;
    align-items: center;
    justify-content: space-between;
}

.overview-v2-module-courses-dialog-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}

.overview-v2-module-courses-dialog-summary {
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

.overview-v2-module-courses-dialog-summary strong {
    font-size: 0.95rem;
}

.overview-v2-module-course-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 9px;
}

.overview-v2-module-course {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    gap: 10px;
    align-items: start;
    min-height: 82px;
    padding: 12px;
    color: #1f2937;
    font: inherit;
    text-align: left;
    cursor: pointer;
    background: #fff;
    border: 1px solid #dbe1eb;
    border-radius: 13px;
    transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.overview-v2-module-course:hover,
.overview-v2-module-course:focus-visible {
    background: #fafaff;
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 9px 22px rgba(79, 70, 229, 0.12);
    transform: translateY(-1px);
}

.overview-v2-module-course--selected {
    background: linear-gradient(135deg, #eef2ff, #fff);
    border-color: #4f46e5;
    box-shadow: inset 0 0 0 1px rgba(79, 70, 229, 0.1);
}

.overview-v2-module-course--planned,
.overview-v2-module-course--planned:hover,
.overview-v2-module-course--planned:focus-visible {
    color: #475569;
    cursor: not-allowed;
    background: #f8fafc;
    border-color: #cbd5e1;
    box-shadow: none;
    transform: none;
}

.overview-v2-module-course-check {
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

.overview-v2-module-course--selected .overview-v2-module-course-check {
    color: #fff;
    background: #4f46e5;
    border-color: #4f46e5;
}

.overview-v2-module-course--planned .overview-v2-module-course-check {
    color: #475569;
    background: #e2e8f0;
    border-color: #cbd5e1;
}

.overview-v2-module-course-copy {
    display: grid;
    min-width: 0;
}

.overview-v2-module-course-title {
    color: #111827;
    font-size: 0.9rem;
    font-weight: 850;
    overflow-wrap: anywhere;
}

.overview-v2-module-course-planned {
    justify-self: start;
    margin-top: 5px;
    padding: 2px 7px;
    color: #475569;
    font-size: 0.65rem;
    font-weight: 850;
    line-height: 1.4;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    background: #e2e8f0;
    border-radius: 999px;
}

.overview-v2-module-course-subtitle {
    margin-top: 1px;
    color: #667085;
    font-size: 0.72rem;
    font-weight: 700;
}

.overview-v2-module-course-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 3px 8px;
    margin-top: 6px;
    color: #667085;
    font-size: 0.68rem;
    font-weight: 650;
}

.overview-v2-module-course-schedule {
    flex-basis: 100%;
}

.overview-v2-module-course-overlap {
    flex-basis: 100%;
    color: #b42318;
    font-weight: 400;
}

.overview-v2-module-course-hours,
.overview-v2-module-course-instruction {
    display: inline-flex;
    gap: 3px;
    align-items: center;
    padding: 2px 6px;
    border-radius: 999px;
}

.overview-v2-module-course-hours {
    color: #344054;
    background: #f2f4f7;
}

.overview-v2-module-course-instruction {
    color: #4338ca;
    background: #e0e7ff;
}

.overview-v2-module-course-empty {
    padding: 22px 16px;
    color: #667085;
    text-align: center;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 13px;
}

.overview-v2-introduction__copy {
    max-width: 720px;
}

.overview-v2-kicker {
    color: #c84d0b;
    font-size: 0.73rem;
    font-weight: 850;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.overview-v2-introduction h1 {
    margin: 8px 0 0;
    font-size: clamp(2rem, 3.4vw, 3.5rem);
    line-height: 1.02;
    letter-spacing: -0.04em;
}

.overview-v2-current-selection {
    position: relative;
    isolation: isolate;
    display: flex;
    gap: 14px;
    align-items: center;
    margin-top: 24px;
    padding: 18px 20px;
    overflow: hidden;
    background: linear-gradient(135deg, #eef2ff 0%, #ffffff 52%, #f5f3ff 100%);
    border: 1px solid rgba(79, 70, 229, 0.34);
    border-radius: 16px;
    box-shadow:
        0 14px 34px rgba(79, 70, 229, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.overview-v2-current-selection::before {
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

.overview-v2-current-selection--creation {
    margin: clamp(24px, 3vw, 42px) clamp(24px, 4vw, 58px) 0;
}

.overview-v2-current-selection--results {
    margin: clamp(22px, 3vw, 40px) clamp(22px, 3vw, 40px) 0;
}

.overview-v2-current-selection__icon {
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

.overview-v2-current-selection__copy {
    flex: 1 1 auto;
    min-width: 0;
}

.overview-v2-current-selection__value {
    display: flex;
    gap: 8px;
    align-items: center;
    color: #1e1b4b;
    overflow-wrap: anywhere;
    font-size: clamp(1.65rem, 3vw, 2.2rem);
    font-weight: 800;
    line-height: 1.25;
}

.overview-v2-current-selection__value > span {
    min-width: 0;
}

.overview-v2-current-selection__value > .overview-v2-current-selection__religion {
    flex: 0 0 auto;
    color: #6366f1;
    font-size: clamp(0.9rem, 1.5vw, 1.1rem);
    font-weight: 700;
}

.overview-v2-current-selection__sex-icon {
    flex: 0 0 auto;
}

.overview-v2-current-selection__email-row {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    max-width: 100%;
    margin-top: 7px;
    color: #4338ca;
}

.overview-v2-current-selection__email-address {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.9rem;
}

.overview-v2-student-info-actions {
    display: flex;
    flex: 0 0 auto;
    gap: 8px;
    align-self: center;
}

.overview-v2-student-info-button,
.overview-v2-study-info-button {
    flex: 0 0 auto;
}

.overview-v2-info-hover-card {
    width: min(560px, calc(100vw - 32px));
    overflow: hidden;
    border: 1px solid rgba(79, 70, 229, 0.18);
}

.overview-v2-info-hover-title {
    padding: 16px 16px 10px;
    color: #1e1b4b;
    font-size: 1.05rem;
    font-weight: 800;
}

.overview-v2-info-hover-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.overview-v2-info-hover-item {
    display: grid;
    gap: 3px;
    min-width: 0;
    padding: 10px 11px;
    background: #f8f9ff;
    border: 1px solid rgba(79, 70, 229, 0.12);
    border-radius: 10px;
}

.overview-v2-info-hover-item .overview-v2-info-value {
    overflow-wrap: anywhere;
    white-space: normal;
}

.overview-v2-study-info-hover-card {
    width: min(900px, calc(100vw - 32px));
    overflow: hidden;
    border: 1px solid rgba(13, 148, 136, 0.22);
}

.overview-v2-study-info-hover-title {
    padding: 16px 16px 10px;
    color: #134e4a;
    font-size: 1.05rem;
    font-weight: 800;
}

.overview-v2-study-info-hover-content,
.overview-v2-study-info-content {
    max-height: min(68vh, 620px);
    overflow-y: auto;
}

.overview-v2-info-dialog-title {
    font-size: 1.35rem;
    font-weight: 800;
}

.overview-v2-info-grid {
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

.overview-v2-info-item {
    display: inline-flex;
    gap: 7px;
    align-items: baseline;
    min-width: 0;
    white-space: nowrap;
}

.overview-v2-info-primary-row {
    display: grid;
    flex: 1 0 100%;
    grid-template-columns: max-content max-content minmax(0, 1fr);
    column-gap: 32px;
    align-items: baseline;
}

.overview-v2-info-primary-row > .overview-v2-info-item + .overview-v2-info-item {
    position: relative;
}

.overview-v2-info-primary-row > .overview-v2-info-item + .overview-v2-info-item::before {
    position: absolute;
    top: 0;
    bottom: 0;
    left: -16px;
    width: 1px;
    content: '';
    background: rgba(79, 70, 229, 0.2);
}

.overview-v2-info-secondary-row {
    display: flex;
    flex: 1 0 100%;
    gap: 16px;
    align-items: baseline;
    padding-top: 12px;
    border-top: 1px solid rgba(79, 70, 229, 0.2);
}

.overview-v2-info-label {
    color: #667085;
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.overview-v2-info-value {
    color: #1e1b4b;
    overflow: visible;
    font-size: 1rem;
    font-weight: 750;
    text-overflow: clip;
}

.overview-v2-study-module-groups {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.overview-v2-study-module-group {
    --study-module-accent: #2563eb;
    --study-module-background: #eff6ff;
    --study-module-border: #bfdbfe;

    min-width: 0;
    overflow: hidden;
    background: var(--study-module-background);
    border: 1px solid var(--study-module-border);
    border-radius: 14px;
}

.overview-v2-study-module-group--passed {
    --study-module-accent: #15803d;
    --study-module-background: #f0fdf4;
    --study-module-border: #bbf7d0;
}

.overview-v2-study-module-group--failed {
    --study-module-accent: #b42318;
    --study-module-background: #fef3f2;
    --study-module-border: #fecdca;
}

.overview-v2-study-module-group-header {
    padding: 16px;
    color: var(--study-module-accent);
    border-bottom: 1px solid var(--study-module-border);
}

.overview-v2-study-module-group-title {
    font-size: 1.02rem;
    font-weight: 800;
    line-height: 1.3;
}

.overview-v2-study-module-group-count {
    margin-top: 3px;
    font-size: 0.82rem;
    font-weight: 700;
}

.overview-v2-study-module-list {
    display: grid;
    gap: 8px;
    padding: 12px;
}

.overview-v2-study-module-row {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    padding: 9px 10px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid var(--study-module-border);
    border-radius: 9px;
}

.overview-v2-study-module-copy {
    display: grid;
    flex: 1 1 auto;
    gap: 2px;
    min-width: 0;
}

.overview-v2-study-module-code {
    overflow-wrap: anywhere;
    color: #1d2939;
    font-weight: 750;
}

.overview-v2-study-module-name {
    color: #667085;
    overflow-wrap: anywhere;
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.25;
}

.overview-v2-study-module-grades {
    display: flex;
    flex: 0 0 auto;
    gap: 6px;
    align-items: center;
}

.overview-v2-study-module-grade {
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

.overview-v2-study-module-grade--exempt {
    background: #2563eb;
}

.overview-v2-study-module-grade--passed {
    background: #15803d;
}

.overview-v2-study-module-grade--failed {
    background: #b42318;
}

.overview-v2-study-module-empty {
    padding: 20px 14px;
    color: #667085;
    font-size: 0.92rem;
    text-align: center;
}

.overview-v2-compact-planning-card {
    margin-top: 16px;
    padding: 15px 18px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid #dfe3f0;
    border-radius: 16px;
    box-shadow: 0 8px 22px rgba(30, 36, 51, 0.07);
}

.overview-v2-compact-planning-card--planning-readonly {
    margin: 16px clamp(24px, 4vw, 58px) 0;
}

.overview-v2-compact-planning-card--results-readonly {
    margin-inline: clamp(22px, 3vw, 40px);
}

.overview-v2-compact-planning-heading {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 10px;
}

.overview-v2-compact-planning-title {
    margin: 0;
    color: #1e2433;
    font-size: 1rem;
    font-weight: 800;
}

.overview-v2-compact-planning-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
    gap: 8px;
}

.overview-v2-compact-planning-item {
    display: grid;
    gap: 2px;
    min-width: 0;
    width: 100%;
    padding: 9px 11px;
    font: inherit;
    text-align: left;
    cursor: pointer;
    background: #f8f9ff;
    border: 1px solid #e4e7f2;
    border-radius: 10px;
    transition:
        background-color 160ms ease,
        border-color 160ms ease,
        box-shadow 160ms ease;
}

.overview-v2-compact-planning-item:hover:not(:disabled),
.overview-v2-compact-planning-item:focus-visible {
    background: #eef2ff;
    border-color: #818cf8;
    outline: none;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.14);
}

.overview-v2-compact-planning-item:disabled {
    cursor: wait;
    opacity: 0.65;
}

.overview-v2-compact-planning-item--overridden {
    border-color: rgba(79, 70, 229, 0.42);
}

.overview-v2-compact-planning-item--readonly,
.overview-v2-compact-planning-item--readonly:hover {
    cursor: default;
    background: #f8f9ff;
    border-color: #e4e7f2;
    box-shadow: none;
}

.overview-v2-compact-planning-item-heading {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
}

.overview-v2-compact-planning-label {
    color: #667085;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.045em;
    text-transform: uppercase;
}

.overview-v2-compact-planning-value {
    color: #1e1b4b;
    overflow-wrap: anywhere;
    font-size: 0.96rem;
    font-weight: 750;
}

.overview-v2-compact-planning-reset {
    text-transform: none;
}

.overview-v2-timetable-start {
    margin-top: 16px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid #dfe3f0;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(30, 36, 51, 0.08);
}

.overview-v2-timetable-start-heading {
    display: grid;
    gap: 4px;
}

.overview-v2-timetable-start-eyebrow {
    color: #c2410c;
    font-size: 0.72rem;
    font-weight: 850;
    letter-spacing: 0.11em;
    text-transform: uppercase;
}

.overview-v2-timetable-start-heading h2 {
    margin: 0;
    color: #1e2433;
    font-size: clamp(1.2rem, 2vw, 1.55rem);
    letter-spacing: -0.02em;
}

.overview-v2-timetable-start-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}

.overview-v2-timetable-start-card {
    --timetable-start-accent: #4338ca;
    --timetable-start-accent-rgb: 67, 56, 202;
    --timetable-start-soft: #eef2ff;
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 14px;
    align-items: start;
    min-width: 0;
    min-height: 220px;
    padding: 22px 18px 18px;
    color: #1e293b;
    font: inherit;
    text-align: left;
    cursor: pointer;
    background: linear-gradient(145deg, var(--timetable-start-soft), #fff 68%);
    border: 2px solid rgba(var(--timetable-start-accent-rgb), 0.3);
    border-radius: 18px;
    overflow: hidden;
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        box-shadow 160ms ease,
        background 160ms ease;
}

.overview-v2-timetable-start-card::before {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    height: 5px;
    content: '';
    background: var(--timetable-start-accent);
}

.overview-v2-timetable-start-card--personal {
    --timetable-start-accent: #0f766e;
    --timetable-start-accent-rgb: 15, 118, 110;
    --timetable-start-soft: #f0fdfa;
}

.overview-v2-timetable-start-card--published {
    --timetable-start-accent: #c2410c;
    --timetable-start-accent-rgb: 194, 65, 12;
    --timetable-start-soft: #fff7ed;
}

.overview-v2-timetable-start-card:hover:not(:disabled),
.overview-v2-timetable-start-card:focus-visible {
    background: #fff;
    border-color: var(--timetable-start-accent);
    outline: 3px solid rgba(var(--timetable-start-accent-rgb), 0.16);
    outline-offset: 2px;
    box-shadow: 0 14px 32px rgba(var(--timetable-start-accent-rgb), 0.18);
    transform: translateY(-2px);
}

.overview-v2-timetable-start-card:disabled {
    cursor: not-allowed;
    filter: grayscale(0.35);
    opacity: 0.58;
}

.overview-v2-timetable-start-icon {
    display: inline-grid;
    place-items: center;
    width: 56px;
    height: 56px;
    color: var(--timetable-start-accent);
    background: var(--timetable-start-soft);
    border: 1px solid rgba(var(--timetable-start-accent-rgb), 0.2);
    border-radius: 15px;
}

.overview-v2-timetable-start-copy {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.overview-v2-timetable-start-title {
    color: #1e293b;
    font-size: 1rem;
    font-weight: 850;
    line-height: 1.25;
}

.overview-v2-timetable-start-description {
    color: #475569;
    font-size: 0.88rem;
    line-height: 1.45;
}

.overview-v2-timetable-start-availability {
    color: var(--timetable-start-accent);
    font-size: 0.76rem;
    font-weight: 800;
}

.overview-v2-timetable-start-action {
    display: inline-flex;
    grid-column: 1 / -1;
    gap: 8px;
    align-items: center;
    justify-content: center;
    align-self: end;
    min-height: 42px;
    padding: 8px 12px;
    color: #fff;
    background: var(--timetable-start-accent);
    border-radius: 11px;
    font-size: 0.86rem;
    font-weight: 850;
}

.overview-v2-selection-dialog-hint {
    margin: 0 0 12px;
    color: #667085;
    font-size: 0.9rem;
}

.overview-v2-selection-options {
    width: 100%;
}

.overview-v2-selection-options :deep(.v-slide-group__content) {
    display: grid;
    gap: 10px;
    width: 100%;
}

.overview-v2-selection-option {
    width: 100%;
    height: auto !important;
    min-height: 48px;
    justify-content: flex-start;
    padding: 10px 14px;
    font-weight: 700;
    border-color: #d9deec;
}

.overview-v2-selection-option :deep(.v-chip__content) {
    white-space: normal;
}

.overview-v2-selection-option--selected {
    color: #3730a3;
    background: #eef2ff;
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.12);
}

.overview-v2-results-page {
    display: grid;
    gap: 18px;
    padding: 16px clamp(22px, 3vw, 40px) clamp(22px, 3vw, 40px);
}

.overview-v2-adoption-page {
    display: grid;
    gap: 16px;
    padding: 16px clamp(22px, 3vw, 40px) clamp(22px, 3vw, 40px);
}

.timetable-v3__adoption-heading {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    min-width: 0;
}

.timetable-v3__adoption-title-copy {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.timetable-v3__adoption-title {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    min-width: 0;
}

.timetable-v3__published-timetable-name {
    flex: 0 0 auto;
    font-weight: 850;
    letter-spacing: 0.08em;
}

.timetable-v3__adoption-saved-at {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
}

.timetable-v3__adoption-actions {
    display: flex;
    flex: 0 0 auto;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
}

.timetable-v3__adoption-pdf-button {
    flex: 0 0 auto;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.timetable-v3__adoption-save-button {
    flex: 0 0 auto;
    font-weight: 800;
}

.overview-v2-manual-module-catalog {
    padding: 18px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid #dfe3f0;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(30, 36, 51, 0.08);
}

.overview-v2-manual-module-catalog-headings {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.overview-v2-manual-module-catalog-heading {
    display: flex;
    gap: 11px;
    align-items: center;
    width: 100%;
    margin: 0;
    padding: 12px 14px;
    color: #1e3a8a;
    font: inherit;
    text-align: left;
    cursor: pointer;
    appearance: none;
    background: linear-gradient(135deg, #eff6ff, #eef2ff);
    border: 1px solid #bfdbfe;
    border-radius: 14px;
    transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
}

.overview-v2-manual-module-catalog-heading:hover,
.overview-v2-manual-module-catalog-heading:focus-visible {
    border-color: #60a5fa;
    outline: none;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.14);
    transform: translateY(-1px);
}

.overview-v2-manual-module-catalog-heading--selected {
    background: linear-gradient(135deg, #dbeafe, #e0e7ff);
    border: 2px solid #60a5fa;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1), 0 6px 16px rgba(37, 99, 235, 0.12);
}

.overview-v2-manual-module-catalog-heading-icon {
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

.overview-v2-manual-module-catalog-heading > span:last-child {
    display: grid;
    gap: 2px;
}

.overview-v2-manual-module-catalog-heading strong {
    font-size: 0.9rem;
    font-weight: 850;
}

.overview-v2-manual-module-catalog-heading small {
    color: #475569;
    font-size: 0.76rem;
    line-height: 1.35;
}

.overview-v2-module-group-cards--main {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
}

.overview-v2-module-group-card--main {
    --module-group-accent: #2563eb;
    --module-group-accent-rgb: 37, 99, 235;
    --module-group-soft: #eff6ff;
    flex: none;
}

.overview-v2-module-group-cards--main .overview-v2-module-group-card--active {
    grid-column: span 2;
}

.overview-v2-module-group-panel--main {
    --module-group-accent: #2563eb;
    --module-group-accent-rgb: 37, 99, 235;
    --module-group-soft: #eff6ff;
}

.overview-v2-module-planned {
    width: fit-content;
    padding: 2px 7px;
    color: #166534;
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    border-radius: 999px;
    font-size: 0.63rem;
    font-weight: 850;
}

.overview-v2-module-not-intended {
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

.overview-v2-adoption-timetable {
    margin-top: 2px;
}

.timetable-v3__adoption-cards {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
}

.timetable-v3__adoption-card {
    min-height: 220px;
}

.timetable-v3__adoption-selected-modules {
    grid-column: 1 / -1;
}

.timetable-v3__creation-summary-cards {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 16px;
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
    color: #344054;
    font: inherit;
    text-align: left;
    cursor: default;
    background: linear-gradient(145deg, var(--schedule-mode-soft), #fff 68%);
    border: 2px solid rgba(var(--schedule-mode-accent-rgb), 0.3);
    border-radius: 18px;
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

.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-icon {
    color: #fff;
    background: var(--schedule-mode-accent);
    border-color: var(--schedule-mode-accent);
    box-shadow: 0 6px 16px rgba(var(--schedule-mode-accent-rgb), 0.24);
}

.timetable-v3__schedule-mode-copy {
    display: grid;
    gap: 5px;
    min-width: 0;
}

.timetable-v3__schedule-mode-title {
    color: #1e293b;
    font-size: clamp(1.05rem, 1.6vw, 1.3rem);
    font-weight: 850;
    line-height: 1.2;
}

.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-title {
    color: var(--schedule-mode-accent);
}

.timetable-v3__schedule-mode-description {
    max-width: 48ch;
    color: #475569;
    font-size: 0.9rem;
    line-height: 1.5;
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

.timetable-v3__creation-summary-card--options:hover,
.timetable-v3__creation-summary-card--options:focus-within {
    background: linear-gradient(145deg, var(--schedule-mode-soft), #fff 68%);
    border-color: rgba(var(--schedule-mode-accent-rgb), 0.3);
    box-shadow: none;
}

.timetable-v3__selected-modules {
    display: grid;
    gap: 8px;
    padding: 11px 13px;
    background: rgba(248, 250, 252, 0.92);
    border: 1px solid #dbe3ef;
    border-radius: 12px;
}

.timetable-v3__schedule-mode-selected-modules {
    grid-column: 1 / -1;
    margin-top: 2px;
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(var(--schedule-mode-accent-rgb), 0.2);
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

.timetable-v3__selected-modules-empty {
    color: #667085;
    font-size: 0.88rem;
}

.timetable-v3__manual-timetable-button {
    grid-column: 1 / -1;
    align-self: end;
    font-weight: 850;
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

.timetable-v3__calculation-output {
    grid-column: 1 / -1;
    min-width: 0;
}

.overview-v2-results-empty {
    display: flex;
    gap: 14px;
    align-items: center;
    padding: 22px;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 15px;
}

.overview-v2-results-empty div {
    display: grid;
    gap: 3px;
}

.overview-v2-results-empty span {
    color: #785d2d;
    font-size: 0.88rem;
}

@media (max-width: 960px) {
    .overview-v2-creation-mode-options,
    .overview-v2-timetable-start-options {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 680px) {
    .students-timetables-overview-v2-page {
        padding-inline: 8px;
    }

    .overview-v2-navigation {
        grid-template-columns: 1fr auto;
        padding-inline: 12px;
    }

    .overview-v2-wordmark {
        grid-column: 1;
        grid-row: 1;
        justify-self: start;
    }

    .overview-v2-navigation__actions {
        grid-column: 2;
        grid-row: 1;
    }

    .overview-v2-logout-button {
        display: none;
    }

    .overview-v2-introduction {
        padding: 28px 20px 24px;
    }

    .overview-v2-creation-mode-page {
        padding: 16px 20px 30px;
    }

    .overview-v2-results-page {
        padding: 16px 12px 30px;
    }

    .overview-v2-adoption-page {
        padding: 16px 12px 30px;
    }

    .overview-v2-current-selection--creation {
        margin: 24px 20px 0;
    }

    .overview-v2-current-selection--results {
        margin: 24px 12px 0;
    }

    .overview-v2-compact-planning-card--planning-readonly {
        margin: 16px 20px 0;
    }

    .overview-v2-compact-planning-card--results-readonly {
        margin-inline: 12px;
    }

    .timetable-v3__creation-summary-cards {
        grid-template-columns: 1fr;
    }

    .timetable-v3__creation-summary-card--automatic,
    .timetable-v3__creation-summary-card--manual,
    .timetable-v3__creation-summary-card--options {
        grid-column: 1;
        grid-row: auto;
    }

    .timetable-v3__calculation-led-progress {
        gap: 3px !important;
        padding: 10px;
    }

    .timetable-v3__calculation-led-segment {
        height: 23px;
    }

    .overview-v2-creation-mode-workspace {
        padding: 20px 16px;
    }

    .overview-v2-module-selected-total {
        justify-self: start;
    }

    .overview-v2-module-group-cards {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .overview-v2-manual-module-catalog {
        padding: 14px;
    }

    .overview-v2-manual-module-catalog-headings {
        grid-template-columns: 1fr;
    }

    .overview-v2-module-group-cards--main .overview-v2-module-group-card--active {
        grid-column: 1 / -1;
    }

    .overview-v2-module-group-panel-actions {
        justify-content: flex-start;
    }

    .overview-v2-module-grid,
    .overview-v2-module-course-list {
        grid-template-columns: 1fr;
    }

    .overview-v2-introduction h1 {
        font-size: clamp(1.9rem, 11vw, 2.7rem);
    }

    .overview-v2-timetable-start {
        padding: 16px;
    }

    .overview-v2-timetable-start-options {
        grid-template-columns: 1fr;
    }

    .overview-v2-current-selection {
        flex-wrap: wrap;
        align-items: flex-start;
        padding: 16px;
    }

    .overview-v2-current-selection__value {
        flex-wrap: wrap;
        font-size: clamp(1.25rem, 7vw, 1.75rem);
    }

    .overview-v2-student-info-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .overview-v2-info-hover-grid,
    .overview-v2-study-module-groups {
        grid-template-columns: 1fr;
    }

    .overview-v2-info-primary-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 12px;
    }

    .overview-v2-info-primary-row > .overview-v2-info-item:last-child {
        grid-column: 1 / -1;
        padding-top: 12px;
        border-top: 1px solid rgba(79, 70, 229, 0.2);
    }

    .overview-v2-info-primary-row > .overview-v2-info-item:last-child::before {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .timetable-v3__calculation-led-segment {
        transition: none;
    }
}
</style>
