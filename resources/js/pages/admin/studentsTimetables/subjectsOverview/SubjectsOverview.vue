<template>
    <v-col cols="12">
        <v-sheet rounded="lg" class="subject-subnav mb-4">
            <v-btn
                v-for="item in subjectNavigationItems"
                :key="item.key"
                size="small"
                :color="subject_action === item.key ? 'primary' : 'secondary'"
                :variant="subject_action === item.key ? 'flat' : 'tonal'"
                :prepend-icon="item.icon"
                class="subject-subnav__button"
                @click="handleSubjectNavigation(item.key)">
                {{ item.label }}
            </v-btn>
        </v-sheet>

        <div class="d-flex flex-wrap align-center ga-3 mb-4">
            <v-btn-toggle
                class="subject-study-program-toggle"
                :model-value="studyProgram"
                mandatory
                density="compact"
                color="primary"
                variant="outlined"
                :disabled="studyProgramSwitchDisabled"
                @update:model-value="changeStudyProgram">
                <v-btn
                    v-for="option in studyProgramOptions"
                    :key="option.value"
                    :value="option.value">
                    {{ option.label }}
                </v-btn>
            </v-btn-toggle>

            <strong class="text-primary">{{ personalSchoolyearLabel }}</strong>
        </div>

        <v-alert v-if="settingsError && ['subject-plan', 'subject-plan-v2'].includes(subject_action)" type="error" variant="tonal" class="mb-4">
            {{ settingsError }}
        </v-alert>
        <v-alert v-if="settingsMessage && ['subject-plan', 'subject-plan-v2'].includes(subject_action)" type="success" variant="tonal" class="mb-4">
            {{ settingsMessage }}
        </v-alert>

        <v-card v-if="subject_action === 'subject-plan'" rounded="lg" border class="subject-overview-card mb-4">
            <v-card-title class="subject-overview-card__title d-flex align-center ga-2">
                <v-icon icon="mdi-table-large" />
                Fächerübersicht · {{ studyProgramLabel }}
                <v-chip size="x-small" color="primary" variant="tonal">
                    {{ subjectRows.length }}
                </v-chip>
            </v-card-title>
            <v-card-text class="subject-overview-card__text">
                <v-progress-linear v-if="settingsLoading" indeterminate color="primary" class="mb-2" />

                <v-alert
                    v-if="!settingsLoading && !activeSubjectRows.length && !previousSchoolyear"
                    type="info"
                    variant="tonal"
                    class="mb-3">
                    Keine aktiven Fächer vorhanden.
                </v-alert>

                <div v-if="subject_action === 'subject-plan' && activeSubjectRows.length" class="subject-plan-wrap mb-4">
                    <div
                        v-for="layout in subjectOverviewLayouts"
                        :key="`subject-plan-layout-${layout.key}`"
                        class="subject-plan-layout"
                        :class="`subject-plan-layout--${layout.key}`">
                        <div
                            v-for="columnGroup in layout.columnGroups"
                            :key="`subject-plan-table-${layout.key}-${columnGroup.key}`"
                            class="subject-plan-table">
                            <div
                                class="subject-plan-grid"
                                :style="subjectOverviewGridStyle(columnGroup)">
                                <div class="subject-plan-cell subject-plan-cell--header subject-plan-cell--semester">
                                    SEMESTER
                                </div>
                                <div
                                    v-for="column in columnGroup.columns"
                                    :key="`subject-plan-header-${layout.key}-${columnGroup.key}-${column.key}`"
                                    class="subject-plan-cell subject-plan-cell--header">
                                    <div class="subject-plan-header-code">{{ column.label }}</div>
                                    <div class="subject-plan-header-name">{{ column.subtitle }}</div>
                                </div>
                                <div v-if="columnGroup.showSum" class="subject-plan-cell subject-plan-cell--header subject-plan-cell--sum">
                                    SUMME
                                </div>

                                <template v-for="row in subjectOverviewRows" :key="`subject-plan-row-${layout.key}-${columnGroup.key}-${row.semester}`">
                                    <div class="subject-plan-cell subject-plan-cell--semester subject-plan-cell--semester-number">
                                        {{ row.semester }}
                                    </div>
                                    <div
                                        v-for="cell in subjectOverviewCellsForColumns(row, columnGroup.columns)"
                                        :key="`subject-plan-cell-${layout.key}-${columnGroup.key}-${row.semester}-${cell.column.key}`"
                                        class="subject-plan-cell"
                                        :class="[subjectOverviewCellClass(cell), { 'subject-plan-cell--multi': cell.subjects.length > 1 }]">
                                        <div
                                            v-for="subject in cell.subjects"
                                            :key="`subject-plan-item-${layout.key}-${columnGroup.key}-${row.semester}-${cell.column.key}-${subject.display_key || subject.local_id || subject.id || subject.json_code}`"
                                            class="subject-plan-item"
                                            :class="{ 'subject-plan-item--course': cell.subjects.length > 1 }">
                                            <div class="subject-plan-code">{{ subject.display_code || subjectOverviewDisplayCode(subject) }}</div>
                                            <div class="subject-plan-hours">{{ formatSubjectHours(Number(subject.hours_per_week || 0)) }}</div>
                                        </div>
                                    </div>
                                    <div v-if="columnGroup.showSum" class="subject-plan-cell subject-plan-cell--sum">
                                        <span
                                            v-for="total in row.totals"
                                            :key="`subject-plan-row-total-${layout.key}-${columnGroup.key}-${row.semester}-${total.value}`"
                                            class="subject-plan-total"
                                            :class="total.class">
                                            {{ total.value }}
                                        </span>
                                    </div>
                                </template>

                                <div class="subject-plan-cell subject-plan-cell--footer subject-plan-cell--semester">
                                    SUMME
                                </div>
                                <div
                                    v-for="column in subjectOverviewFooterForColumns(columnGroup.columns)"
                                    :key="`subject-plan-footer-${layout.key}-${columnGroup.key}-${column.key}`"
                                    class="subject-plan-cell subject-plan-cell--footer">
                                    <span
                                        v-for="total in column.totals"
                                        :key="`subject-plan-footer-total-${layout.key}-${columnGroup.key}-${column.key}-${total.value}-${total.class}`"
                                        class="subject-plan-total"
                                        :class="total.class">
                                        {{ total.value }}
                                    </span>
                                </div>
                                <div v-if="columnGroup.showSum" class="subject-plan-cell subject-plan-cell--footer subject-plan-cell--sum">
                                    <span
                                        v-for="total in subjectOverviewGrandTotals"
                                        :key="`subject-plan-grand-total-${layout.key}-${columnGroup.key}-${total.value}-${total.class}`"
                                        class="subject-plan-total"
                                        :class="total.class">
                                        {{ total.value }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="subjectOverviewHasChoiceSubjects" class="subject-plan-choice-note">
                        * wahlweise
                    </div>

                    <div v-if="subjectOverviewHasBranches" class="subject-plan-legend">
                        <div class="subject-plan-legend-item">
                            <span class="subject-plan-legend-swatch subject-plan-legend-swatch--wirtschaftskundlich"></span>
                            <span>Wirtschaftskundlicher Zweig</span>
                        </div>
                        <div class="subject-plan-legend-item">
                            <span class="subject-plan-legend-swatch subject-plan-legend-swatch--gymnasial"></span>
                            <span>Gymnasialer Zweig</span>
                        </div>
                    </div>
                </div>

            </v-card-text>
        </v-card>

        <v-card v-if="subject_action === 'subject-plan-v2'" rounded="lg" border class="subject-overview-card mb-4">
            <v-card-title class="subject-overview-card__title d-flex align-center ga-2">
                <v-icon icon="mdi-view-dashboard-outline" />
                Fächerübersicht v2 · {{ studyProgramLabel }}
            </v-card-title>
            <v-card-text class="subject-overview-v2-card__text">
                <div class="subject-overview-v2-card__canvas">
                    <v-progress-linear v-if="settingsLoading" indeterminate color="primary" class="mb-2" />

                    <v-alert
                        v-if="!settingsLoading && !activeSubjectRows.length && !previousSchoolyear"
                        type="info"
                        variant="tonal"
                        class="mb-3">
                        Keine aktiven Fächer vorhanden.
                    </v-alert>

                    <div
                        v-if="!settingsLoading && subjectOverviewV2Columns.length"
                        class="subject-plan-wrap subject-plan-v2-wrap mb-4">
                        <div
                            class="subject-plan-grid subject-plan-v2-grid"
                            :style="subjectOverviewV2GridStyle">
                            <div class="subject-plan-cell subject-plan-cell--header subject-plan-cell--semester">
                                SEMESTER
                            </div>
                            <div
                                v-for="column in subjectOverviewV2Columns"
                                :key="`subject-plan-v2-header-${column.key}`"
                                class="subject-plan-cell subject-plan-cell--header">
                                <div class="subject-plan-header-code">{{ column.label }}</div>
                                <div class="subject-plan-header-name">{{ column.subtitle }}</div>
                            </div>
                            <div
                                v-if="subjectOverviewV2ShowsHoursAndTotals"
                                class="subject-plan-cell subject-plan-cell--header subject-plan-cell--sum">
                                SUMME
                            </div>

                            <template v-for="row in subjectOverviewV2Rows" :key="`subject-plan-v2-row-${row.semester}`">
                                <div class="subject-plan-cell subject-plan-cell--semester subject-plan-cell--semester-number">
                                    {{ row.semester }}
                                </div>
                                <div
                                    v-for="cell in row.cells"
                                    :key="`subject-plan-v2-cell-${row.semester}-${cell.column.key}`"
                                    class="subject-plan-cell"
                                    :class="[
                                        subjectOverviewCellClass(cell),
                                        { 'subject-plan-cell--v2-course': cell.subjects.length },
                                        { 'subject-plan-cell--v2-branches': cell.groups.length > 1 },
                                    ]">
                                    <template v-if="cell.groups.length === 1">
                                        <div class="subject-plan-code">{{ cell.display_code }}</div>
                                        <div v-if="subjectOverviewV2ShowsHoursAndTotals" class="subject-plan-hours">
                                            {{ cell.hours }}
                                        </div>
                                    </template>
                                    <template v-else-if="cell.groups.length > 1">
                                        <div
                                            v-for="group in cell.groups"
                                            :key="`subject-plan-v2-cell-${row.semester}-${cell.column.key}-${group.branch}`"
                                            class="subject-plan-v2-course-group"
                                            :class="group.class">
                                            <div class="subject-plan-code">{{ group.display_code }}</div>
                                            <div v-if="subjectOverviewV2ShowsHoursAndTotals" class="subject-plan-hours">
                                                {{ group.hours }}
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <div
                                    v-if="subjectOverviewV2ShowsHoursAndTotals"
                                    class="subject-plan-cell subject-plan-cell--sum">
                                    <span
                                        v-for="total in row.totals"
                                        :key="`subject-plan-v2-row-total-${row.semester}-${total.value}-${total.class}`"
                                        class="subject-plan-total"
                                        :class="total.class">
                                        {{ total.value }}
                                    </span>
                                </div>
                            </template>

                            <template v-if="subjectOverviewV2ShowsHoursAndTotals">
                                <div class="subject-plan-cell subject-plan-cell--footer subject-plan-cell--semester">
                                    SUMME
                                </div>
                                <div
                                    v-for="column in subjectOverviewV2Footer"
                                    :key="`subject-plan-v2-footer-${column.key}`"
                                    class="subject-plan-cell subject-plan-cell--footer">
                                    <span
                                        v-for="total in column.totals"
                                        :key="`subject-plan-v2-footer-total-${column.key}-${total.value}-${total.class}`"
                                        class="subject-plan-total"
                                        :class="total.class">
                                        {{ total.value }}
                                    </span>
                                </div>
                                <div class="subject-plan-cell subject-plan-cell--footer subject-plan-cell--sum">
                                    <span
                                        v-for="total in subjectOverviewV2GrandTotals"
                                        :key="`subject-plan-v2-grand-total-${total.value}-${total.class}`"
                                        class="subject-plan-total"
                                        :class="total.class">
                                        {{ total.value }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="subject-overview-v2-card__footer">
                    <div class="subject-plan-choice-note">* wahlweise</div>
                    <div class="subject-plan-legend subject-plan-legend--detailed">
                        <div
                            v-for="legendItem in subjectOverviewV2BranchLegendItems"
                            :key="`subject-plan-v2-legend-${legendItem.branch}`"
                            class="subject-plan-legend-item subject-plan-legend-item--detailed">
                            <span
                                class="subject-plan-legend-swatch subject-plan-legend-swatch--detailed"
                                :class="`subject-plan-legend-swatch--${legendItem.branch}`"></span>
                            <div class="subject-plan-legend-copy">
                                <div class="subject-plan-legend-title">{{ legendItem.label }}</div>
                                <div
                                    v-for="line in legendItem.lines"
                                    :key="`${legendItem.branch}-${line}`"
                                    class="subject-plan-legend-line">
                                    {{ line }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-alert v-if="settingsError && ['subjects', 'rules', 'mapping'].includes(subject_action)" type="error" variant="tonal" class="mb-4">
            {{ settingsError }}
        </v-alert>
        <v-alert v-if="settingsMessage && ['subjects', 'rules', 'mapping'].includes(subject_action)" type="success" variant="tonal" class="mb-4">
            {{ settingsMessage }}
        </v-alert>

        <v-card v-if="subject_action === 'subjects'" rounded="lg" border class="subject-section-card mb-4">
            <v-card-title class="subject-section-card__title d-flex flex-wrap align-center ga-2">
                <v-icon icon="mdi-table-edit" />
                Fächer
                <v-chip size="x-small" color="primary" variant="tonal">
                    {{ subjectRows.length }}
                </v-chip>
                <v-spacer />
                <v-btn
                    v-if="!subjectsEditMode"
                    size="small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="mdi-pencil"
                    @click="startSubjectsEdit">
                    Ändern
                </v-btn>
                <v-btn
                    v-if="subjectsEditMode"
                    size="small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="mdi-plus"
                    @click="addSubjectRow">
                    Fach
                </v-btn>
                <v-btn
                    v-if="subjectsEditMode"
                    size="small"
                    color="primary"
                    :loading="subjectsSaving"
                    prepend-icon="mdi-content-save"
                    @click="saveSubjectRows">
                    Speichern
                </v-btn>
                <v-btn
                    v-if="subjectsEditMode"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-close"
                    :disabled="subjectsSaving"
                    @click="cancelSubjectsEdit">
                    Abbrechen
                </v-btn>
            </v-card-title>
            <v-card-text class="subject-section-card__text">
                <v-progress-linear v-if="settingsLoading" indeterminate color="primary" class="mb-2" />
                <v-alert v-if="!settingsLoading && !subjectRows.length && !previousSchoolyear" type="info" variant="tonal">
                    Keine Fächer vorhanden.
                </v-alert>
                <div v-else class="subjects-settings-table-wrap">
                    <table class="subjects-settings-table subjects-settings-table--subjects">
                        <thead>
                            <tr>
                                <th>
                                    <button type="button" class="subjects-sort-button" @click="sortSubjectRows('semester')">
                                        <span>Sem.</span>
                                        <v-icon :icon="subjectSortIcon('semester')" size="14" />
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="subjects-sort-button" @click="sortSubjectRows('branch')">
                                        <span>Zweig</span>
                                        <v-icon :icon="subjectSortIcon('branch')" size="14" />
                                    </button>
                                </th>
                                <th>JSON-Code</th>
                                <th>JSON-Fach</th>
                                <th>
                                    <button type="button" class="subjects-sort-button" @click="sortSubjectRows('name')">
                                        <span>Bezeichnung</span>
                                        <v-icon :icon="subjectSortIcon('name')" size="14" />
                                    </button>
                                </th>
                                <th>Std.</th>
                                <th>Aktiv</th>
                                <th v-if="subjectsEditMode"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(subject, index) in sortedSubjectRows"
                                :key="subject.local_id || subject.id || `subject-${index}`">
                                <td class="subjects-settings-table__semester">
                                    <v-text-field
                                        v-if="subjectsEditMode"
                                        v-model.number="subject.semester"
                                        type="number"
                                        min="1"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else>{{ displayValue(subject.semester) }}</span>
                                </td>
                                <td class="subjects-settings-table__branch">
                                    <v-text-field
                                        v-if="subjectsEditMode"
                                        v-model="subject.branch"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else>{{ displayBranch(subject.branch) }}</span>
                                </td>
                                <td class="subjects-settings-table__code">
                                    <v-text-field
                                        v-if="subjectsEditMode"
                                        v-model="subject.json_code"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else class="font-weight-medium">{{
                                        alternativeDisplay(subject.json_code)
                                    }}</span>
                                </td>
                                <td class="subjects-settings-table__subject">
                                    <v-text-field
                                        v-if="subjectsEditMode"
                                        v-model="subject.json_subject"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else>{{ alternativeDisplay(subject.json_subject) }}</span>
                                </td>
                                <td class="subjects-settings-table__name">
                                    <v-text-field
                                        v-if="subjectsEditMode"
                                        v-model="subject.name"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else class="subjects-settings-table__name-lines">
                                        <span
                                            v-for="namePart in subjectNameLines(subject)"
                                            :key="`${subject.local_id || subject.id || subject.json_code}-${namePart}`">
                                            {{ namePart }}
                                        </span>
                                    </span>
                                </td>
                                <td class="subjects-settings-table__hours">
                                    <v-text-field
                                        v-if="subjectsEditMode"
                                        v-model.number="subject.hours_per_week"
                                        type="number"
                                        min="0"
                                        step="0.5"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else>{{ displayValue(subject.hours_per_week) }}</span>
                                </td>
                                <td class="subjects-settings-table__active">
                                    <v-switch
                                        v-if="subjectsEditMode"
                                        v-model="subject.is_active"
                                        color="success"
                                        class="subject-active-switch"
                                        density="compact"
                                        hide-details
                                        inset />
                                    <v-chip
                                        v-else
                                        size="x-small"
                                        :color="subject.is_active ? 'success' : 'default'"
                                        variant="tonal">
                                        {{ subject.is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </v-chip>
                                </td>
                                <td v-if="subjectsEditMode" class="subjects-settings-table__actions">
                                    <v-btn
                                        icon="mdi-delete-outline"
                                        size="small"
                                        color="error"
                                        variant="text"
                                        title="Entfernen"
                                        @click="removeSubjectRow(subject)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </v-card-text>
        </v-card>

        <v-card v-if="subject_action === 'rules'" rounded="lg" border class="subject-section-card mb-4">
            <v-card-title class="subject-section-card__title d-flex flex-wrap align-center ga-2">
                <v-icon icon="mdi-source-branch" />
                Regeln
                <v-chip size="x-small" color="primary" variant="tonal">Version {{ subjectRulesVersion }}</v-chip>
                <v-spacer />
                <v-btn
                    v-if="rulesEditMode"
                    size="small"
                    color="primary"
                    :loading="rulesSaving"
                    prepend-icon="mdi-content-save"
                    @click="saveSubjectRules">
                    Speichern und anwenden
                </v-btn>
                <v-btn
                    v-if="rulesEditMode"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-close"
                    :disabled="rulesSaving"
                    @click="cancelRulesEdit">
                    Abbrechen
                </v-btn>
            </v-card-title>
            <v-card-text class="subject-section-card__text">
                <v-alert v-if="!subjectRules.length" type="info" variant="tonal">
                    Keine Regeln vorhanden. Fächer ohne Regel gelten immer.
                </v-alert>

                <section v-else class="subject-rule-impact-preview mb-6">
                    <div class="subject-rule-impact-preview__header">
                        <div>
                            <div class="text-h6">So wirken die Regeln</div>
                            <div class="text-body-2 text-medium-emphasis">
                                Pflichtfächer gelten gemeinsam. Unter Auswahl steht, woraus gewählt werden kann.
                            </div>
                        </div>
                        <v-chip
                            v-if="rulesEditMode"
                            color="warning"
                            size="small"
                            variant="tonal"
                            prepend-icon="mdi-eye-outline">
                            Live-Vorschau – noch nicht gespeichert
                        </v-chip>
                    </div>

                    <div class="subject-rule-impact-preview__grid">
                        <v-card
                            v-for="impact in subjectRuleImpactCards"
                            :key="impact.branch"
                            variant="outlined"
                            class="subject-rule-impact-card"
                            :class="`subject-rule-impact-card--${impact.branch}`">
                            <v-card-title class="subject-rule-impact-card__title">
                                <span
                                    class="subject-plan-legend-swatch"
                                    :class="`subject-plan-legend-swatch--${impact.branch}`"></span>
                                {{ impact.label }}
                            </v-card-title>
                            <v-card-text class="subject-rule-impact-card__content">
                                <div class="subject-rule-impact-row">
                                    <div class="subject-rule-impact-row__label">
                                        <v-icon icon="mdi-check-circle-outline" size="small" />
                                        Pflicht
                                    </div>
                                    <div v-if="impact.required.length" class="subject-rule-impact-values">
                                        <v-chip
                                            v-for="description in impact.required"
                                            :key="description"
                                            size="small"
                                            variant="tonal">
                                            {{ description }}
                                        </v-chip>
                                    </div>
                                    <span v-else class="text-body-2 text-medium-emphasis">
                                        Keine zusätzlichen Zweig-Pflichtfächer
                                    </span>
                                </div>

                                <div class="subject-rule-impact-row">
                                    <div class="subject-rule-impact-row__label">
                                        <v-icon icon="mdi-call-split" size="small" />
                                        Auswahl
                                    </div>
                                    <div v-if="impact.choices.length" class="subject-rule-impact-choices">
                                        <div
                                            v-for="choice in impact.choices"
                                            :key="`${choice.label}-${choice.description}`"
                                            class="subject-rule-impact-choice">
                                            <strong>{{ choice.label }}:</strong>
                                            {{ choice.description }}
                                        </div>
                                    </div>
                                    <span v-else class="text-body-2 text-medium-emphasis">
                                        Keine zusätzliche Auswahl
                                    </span>
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>
                </section>

                <v-expansion-panels v-if="subjectRules.length" v-model="subjectRuleOpenPanels" variant="accordion" multiple>
                    <v-expansion-panel
                        v-for="rule in subjectRules"
                        :key="rule.stable_key"
                        :value="rule.stable_key">
                        <v-expansion-panel-title>
                            <div class="d-flex align-center ga-2 w-100">
                                <strong>{{ subjectRuleSelectionTitle(rule.selection_key) }}</strong>
                                <v-chip v-if="!rule.is_active" size="x-small" color="warning" variant="tonal">ausgeschaltet</v-chip>
                            </div>
                        </v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <div v-if="!rulesEditMode" class="d-flex justify-end mb-3">
                                <v-btn
                                    size="small"
                                    color="primary"
                                    variant="tonal"
                                    prepend-icon="mdi-pencil"
                                    @click="startRulesEdit(rule)">
                                    Ändern
                                </v-btn>
                            </div>
                            <div class="subject-rule-grid mb-3">
                                <v-switch
                                    v-model="rule.is_active"
                                    :label="rule.is_active ? 'Diese Regel wird angewendet' : 'Diese Regel ist ausgeschaltet'"
                                    color="primary"
                                    hide-details
                                    :readonly="!isSubjectRuleEditing(rule)" />
                            </div>

                            <div v-if="rule.conditions.length" class="mb-3">
                                <div class="text-subtitle-2 mb-2">Bedingungen (alle müssen zutreffen)</div>
                                <div v-for="(condition, conditionIndex) in rule.conditions" :key="`${rule.stable_key}-condition-${conditionIndex}`" class="subject-rule-condition-grid">
                                    <v-select v-model="condition.field" :items="subjectRuleConditionFields" item-title="title" item-value="value" label="Feld" variant="outlined" density="compact" :readonly="!isSubjectRuleEditing(rule)" />
                                    <v-select v-model="condition.operator" :items="subjectRuleConditionOperators" item-title="title" item-value="value" label="Vergleich" variant="outlined" density="compact" :readonly="!isSubjectRuleEditing(rule)" />
                                    <v-text-field v-model="condition.value" label="Wert" variant="outlined" density="compact" :readonly="!isSubjectRuleEditing(rule)" />
                                    <v-btn v-if="isSubjectRuleEditing(rule)" icon="mdi-delete-outline" color="error" variant="text" @click="removeRuleCondition(rule, conditionIndex)" />
                                </div>
                            </div>

                            <v-card v-for="option in rule.options" :key="option.stable_key" variant="outlined" class="mb-3">
                                <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                                    <span
                                        v-if="subjectRuleOptionBranchClass(rule, option)"
                                        class="subject-plan-legend-swatch subject-rule-option-heading__swatch"
                                        :class="subjectRuleOptionBranchClass(rule, option)"></span>
                                    {{ subjectRuleOptionHeading(rule, option) }}
                                </v-card-title>
                                <v-card-subtitle>{{ subjectRuleOptionImpactLabel(rule) }}</v-card-subtitle>
                                <v-card-text>
                                    <div
                                        v-for="subjectGroup in subjectRuleSelectedSubjectGroups(rule, option)"
                                        :key="subjectGroup.branch"
                                        class="subject-rule-subject-group">
                                        <div
                                            v-if="rule.selection_key !== 'branch'"
                                            class="subject-rule-subject-group__title">
                                            <span
                                                v-if="subjectGroup.branch !== 'common'"
                                                class="subject-plan-legend-swatch subject-rule-subject-group__swatch"
                                                :class="`subject-plan-legend-swatch--${subjectGroup.branch}`"></span>
                                            {{ subjectGroup.label }}
                                        </div>
                                        <div
                                            v-for="family in subjectRuleSelectedSubjectFamilies(rule, subjectGroup)"
                                            :key="family.key"
                                            class="subject-rule-subject-family"
                                            :class="{ 'subject-rule-subject-family--labelled': family.label }">
                                            <div v-if="family.label" class="subject-rule-subject-family__label">
                                                {{ family.label }}
                                            </div>
                                            <div class="subject-rule-subject-chips">
                                                <v-chip
                                                    v-for="subject in family.subjects"
                                                    :key="subject.value"
                                                    size="small"
                                                    variant="tonal"
                                                    :closable="isSubjectRuleEditing(rule)"
                                                    @click:close="removeSubjectRuleOptionSubject(option, subject.value)">
                                                    {{ subject.title }}
                                                </v-chip>
                                            </div>
                                        </div>
                                    </div>
                                    <span
                                        v-if="!subjectRuleSelectedSubjectGroups(rule, option).length"
                                        class="text-medium-emphasis text-body-2">
                                        Keine Fächer / Module zugeordnet.
                                    </span>
                                    <v-autocomplete
                                        v-if="isSubjectRuleEditing(rule) && subjectRuleAvailableSubjectOptions(rule, option).length"
                                        :model-value="null"
                                        :items="subjectRuleAvailableSubjectOptions(rule, option)"
                                        item-title="title"
                                        item-value="value"
                                        label="Fach / Modul hinzufügen"
                                        no-data-text="Keine weiteren Fächer / Module"
                                        variant="outlined"
                                        density="compact"
                                        clearable
                                        hide-details
                                        class="mt-3"
                                        @update:model-value="subjectKey => addSubjectRuleOptionSubject(option, subjectKey)" />
                                </v-card-text>
                            </v-card>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>
            </v-card-text>
        </v-card>

        <v-card v-if="subject_action === 'mapping'" rounded="lg" border class="subject-section-card">
            <v-card-title class="subject-section-card__title d-flex flex-wrap align-center ga-2">
                <v-icon icon="mdi-transit-connection-variant" />
                Fach-Zuordnung
                <v-chip size="x-small" color="primary" variant="tonal">
                    {{ subjectMappings.length }}
                </v-chip>
                <v-spacer />
                <v-btn
                    v-if="!mappingsEditMode"
                    size="small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="mdi-pencil"
                    @click="startMappingsEdit">
                    Ändern
                </v-btn>
                <v-btn
                    v-if="mappingsEditMode"
                    size="small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="mdi-plus"
                    @click="addMappingRow">
                    Zuordnung
                </v-btn>
                <v-btn
                    v-if="mappingsEditMode"
                    size="small"
                    color="primary"
                    :loading="mappingsSaving"
                    prepend-icon="mdi-content-save"
                    @click="saveMappings">
                    Speichern
                </v-btn>
                <v-btn
                    v-if="mappingsEditMode"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-close"
                    :disabled="mappingsSaving"
                    @click="cancelMappingsEdit">
                    Abbrechen
                </v-btn>
            </v-card-title>
            <v-card-text class="subject-section-card__text">
                <v-progress-linear v-if="settingsLoading" indeterminate color="primary" class="mb-2" />
                <v-alert v-if="!settingsLoading && !subjectMappings.length && !previousSchoolyear" type="info" variant="tonal">
                    Keine Zuordnungen vorhanden.
                </v-alert>
                <div v-else class="subjects-settings-table-wrap">
                    <table class="subjects-settings-table subjects-settings-table--mappings">
                        <thead>
                            <tr>
                                <th>JSON-Fach</th>
                                <th>TT-Fach</th>
                                <th>Notiz</th>
                                <th>Aktiv</th>
                                <th v-if="mappingsEditMode"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(mapping, index) in subjectMappings"
                                :key="mapping.local_id || mapping.id || `mapping-${index}`">
                                <td class="subjects-settings-table__mapping-json">
                                    <v-text-field
                                        v-if="mappingsEditMode"
                                        v-model="mapping.json_subject"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else class="font-weight-medium">{{ displayValue(mapping.json_subject) }}</span>
                                </td>
                                <td class="subjects-settings-table__mapping-tt">
                                    <v-text-field
                                        v-if="mappingsEditMode"
                                        v-model="mapping.tt_subject"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else>{{ displayValue(mapping.tt_subject) }}</span>
                                </td>
                                <td class="subjects-settings-table__mapping-note">
                                    <v-text-field
                                        v-if="mappingsEditMode"
                                        v-model="mapping.note"
                                        density="compact"
                                        variant="outlined"
                                        hide-details />
                                    <span v-else>{{ displayValue(mapping.note) }}</span>
                                </td>
                                <td class="subjects-settings-table__active">
                                    <v-switch
                                        v-if="mappingsEditMode"
                                        v-model="mapping.is_active"
                                        color="success"
                                        class="subject-active-switch"
                                        density="compact"
                                        hide-details
                                        inset />
                                    <v-chip
                                        v-else
                                        size="x-small"
                                        :color="mapping.is_active ? 'success' : 'default'"
                                        variant="tonal">
                                        {{ mapping.is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </v-chip>
                                </td>
                                <td v-if="mappingsEditMode" class="subjects-settings-table__actions">
                                    <v-btn
                                        icon="mdi-delete-outline"
                                        size="small"
                                        color="error"
                                        variant="text"
                                        title="Entfernen"
                                        @click="removeMappingRow(index)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import {
    settings as subjectSettingsRoute,
    updateMappings as updateSubjectMappingsRoute,
    updateRules as updateRulesRoute,
    updateSubjects as updateSubjectsRoute,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/SubjectOverviewJsonUploadController'

const NORMAL_STUDY_PROGRAM = 'normalstudium'
const COMPACT_STUDY_PROGRAM = 'kompaktstudium'
const PERSONAL_SCHOOLYEAR_SCOPE = 'personal'
const STUDY_PROGRAM_OPTIONS = [
    { value: NORMAL_STUDY_PROGRAM, label: 'Normalstudium' },
    { value: COMPACT_STUDY_PROGRAM, label: 'Kompaktstudium' },
]

const normalizeStudyProgram = value => (
    value === COMPACT_STUDY_PROGRAM ? COMPACT_STUDY_PROGRAM : NORMAL_STUDY_PROGRAM
)

export default {
    name: 'StudentsTimetablesSubjectsOverview',
    props: {
        embedded: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            studyProgram: normalizeStudyProgram(this.$route.query.study_program),
            subject_action: this.embedded ? 'subject-plan' : this.normalizedSubjectAction(this.$route.params.subsection),
            subjectRows: [],
            subjectMappings: [],
            previousSchoolyear: null,
            settingsLoading: false,
            subjectsSaving: false,
            mappingsSaving: false,
            subjectsEditMode: false,
            mappingsEditMode: false,
            rulesEditMode: false,
            editingSubjectRuleKey: null,
            subjectRuleOpenPanels: [],
            rulesSaving: false,
            subjectRulesVersion: 0,
            subjectRules: [],
            subjectRulesSnapshot: [],
            subjectRowsSnapshot: [],
            subjectMappingsSnapshot: [],
            settingsError: '',
            settingsMessage: '',
            nextLocalId: 1,
            subjectSort: {
                key: 'semester',
                direction: 'asc',
            },
        }
    },
    mounted() {
        if (!this.redirectRemovedSubjectImportRoute()) {
            this.redirectMissingSubjectRoute()
            this.redirectUnauthorizedSubjectRoute()
        }
        this.loadSettings()
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        configuredRoleNames() {
            return Array.isArray(this.config?.roles) ? this.config.roles : []
        },
        canManageSubjectSettings() {
            return ['super_admin', 'admin', 'studentstimetables_admin'].some(roleName => this.configuredRoleNames.includes(roleName))
        },
        studyProgramOptions() {
            return STUDY_PROGRAM_OPTIONS
        },
        studyProgramLabel() {
            return STUDY_PROGRAM_OPTIONS.find(option => option.value === this.studyProgram)?.label || 'Normalstudium'
        },
        personalSchoolyearLabel() {
            return this.config?.selected_schoolyear?.concerns
                || this.config?.selected_schoolyear?.name
                || 'nicht festgelegt'
        },
        personalSchoolyearRouteOptions() {
            return {
                query: {
                    schoolyear_scope: PERSONAL_SCHOOLYEAR_SCOPE,
                },
            }
        },
        studyProgramSwitchDisabled() {
            return this.settingsLoading
                || this.subjectsSaving
                || this.subjectsEditMode
                || this.mappingsSaving
                || this.mappingsEditMode
                || this.rulesSaving
                || this.rulesEditMode
        },
        subjectNavigationItems() {
            return [
                {
                    key: 'subject-plan-v2',
                    label: 'Grafik',
                    icon: 'mdi-view-dashboard-outline',
                },
                {
                    key: 'subjects',
                    label: 'Fächer',
                    icon: 'mdi-table-edit',
                },
                {
                    key: 'rules',
                    label: 'Regeln',
                    icon: 'mdi-source-branch',
                },
                {
                    key: 'mapping',
                    label: 'Zuordnung',
                    icon: 'mdi-transit-connection-variant',
                },
            ].filter(item => item.key === 'subject-plan-v2' || this.canManageSubjectSettings)
        },
        sortedSubjectRows() {
            const directionMultiplier = this.subjectSort.direction === 'desc' ? -1 : 1

            return [...this.subjectRows].sort((firstSubject, secondSubject) => {
                const comparison = this.compareSubjectRows(firstSubject, secondSubject, this.subjectSort.key)

                if (comparison !== 0) return comparison * directionMultiplier

                return this.compareSubjectRowDisplayOrder(firstSubject, secondSubject)
            })
        },
        activeSubjectRows() {
            return this.subjectRows.filter(subject => subject.is_active !== false)
        },
        subjectOverviewV2Columns() {
            const activeSubjectKeys = new Set(
                this.activeSubjectRows
                    .map(subject => this.subjectOverviewSubjectKey(subject))
                    .filter(Boolean),
            )
            const assignedSubjectKeys = new Set()
            const preferredColumns = this.subjectOverviewColumns.flatMap(column => {
                const isReligionColumn = column.key === 'R/ET'
                const subjectKeys = isReligionColumn
                    ? [...new Set([...column.subjectKeys, 'R/ETH', 'ETH'])]
                    : column.subjectKeys
                const availableSubjectKeys = subjectKeys.filter(subjectKey => activeSubjectKeys.has(subjectKey))

                if (!availableSubjectKeys.length) return []

                availableSubjectKeys.forEach(subjectKey => assignedSubjectKeys.add(subjectKey))
                const preferredColumn = {
                    ...column,
                    key: isReligionColumn ? 'R/ETH' : column.key,
                    label: isReligionColumn ? 'R/ETH' : column.label,
                    subjectKeys,
                }

                return [{
                    ...preferredColumn,
                    subtitle: this.subjectOverviewV2ColumnSubtitle(preferredColumn),
                }]
            })
            const additionalColumnKeys = new Set()
            const additionalColumns = this.activeSubjectRows.reduce((columns, subject) => {
                const subjectKey = this.subjectOverviewSubjectKey(subject)

                if (!subjectKey || assignedSubjectKeys.has(subjectKey) || additionalColumnKeys.has(subjectKey)) {
                    return columns
                }

                additionalColumnKeys.add(subjectKey)
                const column = {
                    key: subjectKey,
                    label: subjectKey,
                    subjectKeys: [subjectKey],
                }

                columns.push({
                    ...column,
                    subtitle: this.subjectOverviewV2ColumnSubtitle(column),
                })

                return columns
            }, [])

            return [...preferredColumns, ...additionalColumns]
        },
        subjectOverviewV2Semesters() {
            const semesterCount = this.studyProgram === COMPACT_STUDY_PROGRAM ? 5 : 8

            return Array.from({ length: semesterCount }, (value, index) => index + 1)
        },
        subjectOverviewV2PopulatedSubjects() {
            const semesterCount = this.studyProgram === COMPACT_STUDY_PROGRAM ? 5 : 8

            return this.activeSubjectRows.filter(subject => {
                const semester = Number(subject.semester)

                return semester >= 1 && semester <= semesterCount
            })
        },
        subjectOverviewV2Rows() {
            return this.subjectOverviewV2Semesters.map(semester => {
                const semesterSubjects = this.subjectOverviewV2PopulatedSubjects
                    .filter(subject => Number(subject.semester) === semester)

                return {
                    semester,
                    cells: this.subjectOverviewV2Columns.map(column =>
                        this.subjectOverviewV2Cell(semesterSubjects, column)),
                    totals: this.subjectOverviewTotals(semesterSubjects),
                }
            })
        },
        subjectOverviewV2Footer() {
            return this.subjectOverviewV2Columns.map(column => ({
                key: column.key,
                totals: this.subjectOverviewFooterTotals(
                    this.subjectOverviewV2SubjectsForColumn(this.subjectOverviewV2PopulatedSubjects, column),
                ),
            }))
        },
        subjectOverviewV2GrandTotals() {
            return this.subjectOverviewTotals(this.subjectOverviewV2PopulatedSubjects)
        },
        subjectOverviewV2ShowsHoursAndTotals() {
            return this.studyProgram !== COMPACT_STUDY_PROGRAM
        },
        subjectOverviewV2BranchLegendItems() {
            const branchLabels = {
                wirtschaftskundlich: 'Wirtschaftskundlicher Zweig',
                gymnasial: 'Gymnasialer Zweig',
            }

            return this.subjectOverviewBranchKeys().map(branch => ({
                branch,
                label: branchLabels[branch] || this.displayBranch(branch),
                lines: this.subjectOverviewV2BranchLegendLines(branch),
            }))
        },
        subjectRuleImpactCards() {
            const branchLabels = {
                wirtschaftskundlich: 'Wirtschaftskundlicher Zweig',
                gymnasial: 'Gymnasialer Zweig',
            }

            return this.subjectOverviewBranchKeys().map(branch => ({
                branch,
                label: branchLabels[branch] || this.displayBranch(branch),
                required: this.subjectRuleImpactRequiredDescriptions(branch),
                choices: this.subjectRuleImpactChoiceDescriptions(branch),
            }))
        },
        subjectOverviewV2GridStyle() {
            return {
                '--subject-plan-columns': this.subjectOverviewV2Columns.length
                    + (this.subjectOverviewV2ShowsHoursAndTotals ? 2 : 1),
            }
        },
        subjectRuleSelectionKeys() {
            return [
                { title: 'Zweig', value: 'branch' },
                { title: 'Sprache', value: 'language' },
                { title: 'Künstlerisches Fach', value: 'arts_subject' },
                { title: 'Ethik / Religion', value: 'religion' },
            ]
        },
        subjectRuleConditionFields() {
            return [
                ...this.subjectRuleSelectionKeys,
                { title: 'Religion laut Stammdaten', value: 'student_religion' },
            ]
        },
        subjectRuleConditionOperators() {
            return [
                { title: 'ist gleich', value: 'equals' },
                { title: 'ist nicht gleich', value: 'not_equals' },
                { title: 'ist einer von (Komma)', value: 'in' },
                { title: 'ist keiner von (Komma)', value: 'not_in' },
            ]
        },
        subjectOverviewColumns() {
            const columns = [
                { key: 'ÖKO', label: 'ÖKO', subjectKeys: ['ÖKO'] },
                { key: 'INF', label: 'INF', subjectKeys: ['INF'] },
                { key: 'ME', label: 'ME', subjectKeys: ['ME'] },
                { key: 'BE', label: 'BE', subjectKeys: ['BE'] },
                { key: 'PP', label: 'PP', subjectKeys: ['PP'] },
                { key: 'PH', label: 'PH', subjectKeys: ['PH'] },
                { key: 'CH', label: 'CH', subjectKeys: ['CH'] },
                { key: 'BU', label: 'BU', subjectKeys: ['BU'] },
                { key: 'GS', label: 'GS', subjectKeys: ['GS'] },
                { key: 'GW', label: 'GW', subjectKeys: ['GW'] },
                {
                    key: 'LPT/VWA',
                    label: this.studyProgram === COMPACT_STUDY_PROGRAM ? 'VWA' : 'LPT/VWA',
                    subjectKeys: ['LPT', 'VWA'],
                },
                { key: 'R/ET', label: 'R/ET', subjectKeys: ['R/ET', 'R', 'ET'] },
                { key: 'L/F/S', label: 'L/F/S', subjectKeys: ['L/F/S', 'L', 'F', 'S'] },
                { key: 'D', label: 'D', subjectKeys: ['D'] },
                { key: 'E', label: 'E', subjectKeys: ['E'] },
                { key: 'M', label: 'M', subjectKeys: ['M'] },
            ]
            const compactColumnOrder = ['R/ET', 'L/F/S', 'BU', 'GS', 'GW', 'PP', 'PH', 'INF', 'ÖKO', 'CH', 'ME', 'BE', 'LPT/VWA', 'D', 'E', 'M']
            const orderedColumns = this.studyProgram === COMPACT_STUDY_PROGRAM
                ? compactColumnOrder.map(key => columns.find(column => column.key === key))
                : columns

            return orderedColumns.map(column => ({
                ...column,
                subtitle: this.subjectOverviewColumnSubtitle(column),
            }))
        },
        subjectOverviewLayouts() {
            return [
                {
                    key: 'desktop',
                    columnGroups: [
                        {
                            key: 'all',
                            columns: this.subjectOverviewColumns,
                            showSum: true,
                        },
                    ],
                },
                {
                    key: 'split',
                    columnGroups: this.subjectOverviewSplitColumnGroups,
                },
            ]
        },
        subjectOverviewSplitColumnGroups() {
            const splitIndex = Math.ceil(this.subjectOverviewColumns.length / 2)

            return [
                {
                    key: 'first',
                    columns: this.subjectOverviewColumns.slice(0, splitIndex),
                    showSum: false,
                },
                {
                    key: 'second',
                    columns: this.subjectOverviewColumns.slice(splitIndex),
                    showSum: true,
                },
            ]
        },
        subjectOverviewRows() {
            return this.subjectOverviewSemesters.map(semester => {
                const semesterSubjects = this.activeSubjectRows.filter(subject => Number(subject.semester) === semester)
                const cells = this.subjectOverviewColumns.map(column => {
                    const matchingSubjects = semesterSubjects
                        .filter(subject => column.subjectKeys.includes(this.subjectOverviewSubjectKey(subject)))
                        .sort((firstSubject, secondSubject) => this.compareText(firstSubject.json_code, secondSubject.json_code))
                    const subjects = this.subjectOverviewCourseItems(this.uniqueSubjectOverviewSubjects(matchingSubjects))

                    return {
                        column,
                        branches: [...new Set(matchingSubjects.map(subject => subject.branch || 'common'))],
                        subjects,
                    }
                })

                return {
                    semester,
                    cells,
                    totals: this.subjectOverviewTotals(this.uniqueSubjectOverviewTotalSubjects(semesterSubjects)),
                }
            })
        },
        subjectOverviewSemesters() {
            const maximumSemester = Math.max(
                this.studyProgram === COMPACT_STUDY_PROGRAM ? 5 : 8,
                ...this.activeSubjectRows
                    .map(subject => Number(subject.semester || 0))
                    .filter(semester => semester > 0),
            )

            return Array.from({ length: maximumSemester }, (value, index) => index + 1)
        },
        subjectOverviewFooter() {
            return this.subjectOverviewColumns.map(column => ({
                key: column.key,
                totals: this.subjectOverviewFooterTotals(
                    this.uniqueSubjectOverviewTotalSubjects(
                        this.activeSubjectRows.filter(subject =>
                            column.subjectKeys.includes(this.subjectOverviewSubjectKey(subject)),
                        ),
                    ),
                ),
            }))
        },
        subjectOverviewGrandTotals() {
            return this.subjectOverviewTotals(
                this.uniqueSubjectOverviewTotalSubjects(this.activeSubjectRows),
            )
        },
        subjectOverviewHasBranches() {
            return this.activeSubjectRows.some(subject => this.isBranchSubject(subject))
        },
        subjectOverviewHasChoiceSubjects() {
            return this.activeSubjectRows.some(subject => this.isSubjectOverviewChoiceSubject(subject))
        },
    },
    watch: {
        '$route.params.subsection'(subsection) {
            if (this.embedded) {
                return
            }

            this.subject_action = this.normalizedSubjectAction(subsection)
            if (this.redirectRemovedSubjectImportRoute()) {
                return
            }

            this.redirectMissingSubjectRoute()
            this.redirectUnauthorizedSubjectRoute()
        },
        '$route.query.study_program'(value) {
            const studyProgram = normalizeStudyProgram(value)
            if (studyProgram === this.studyProgram) return

            this.applyStudyProgram(studyProgram)
        },
        'config.selected_schoolyear.id'() {
            this.refreshForSchoolyearChange()
        },
    },
    methods: {
        subjectRuleSelectionTitle(selectionKey) {
            return this.subjectRuleSelectionKeys.find(item => item.value === selectionKey)?.title || 'Regel'
        },
        subjectRuleOptionHeading(rule, option) {
            const optionLabel = option.label || option.value
            const headings = {
                branch: `Wenn „${optionLabel}“ gewählt ist`,
                language: `Wenn „${optionLabel}“ als Sprache gewählt ist`,
                arts_subject: `Wenn „${optionLabel}“ als künstlerisches Fach gewählt ist`,
                religion: `Wenn „${optionLabel}“ als Religion oder Ethik gewählt ist`,
            }

            return headings[rule.selection_key] || `Wenn „${optionLabel}“ gewählt ist`
        },
        subjectRuleOptionBranchClass(rule, option) {
            if (rule.selection_key !== 'branch') return null

            const branch = String(option.value || '').trim()

            return ['wirtschaftskundlich', 'gymnasial'].includes(branch)
                ? `subject-plan-legend-swatch--${branch}`
                : null
        },
        subjectRuleOptionImpactLabel(rule) {
            const labels = {
                branch: 'Diese Zweig-Fächer gelten dann:',
                language: 'Diese Sprachmodule gelten dann:',
                arts_subject: 'Diese Kunstmodule gelten dann:',
                religion: 'Diese Religions- oder Ethikmodule gelten dann:',
            }

            return labels[rule.selection_key] || 'Diese Fächer / Module gelten dann:'
        },
        subjectRuleSelectedSubjectGroups(rule, option) {
            const selectedSubjectKeys = new Set(option.subject_keys)
            const groups = new Map()
            const selectedSubjects = this.subjectRows
                .filter(subject => subject.stable_key && selectedSubjectKeys.has(subject.stable_key))

            if (rule.selection_key === 'branch') {
                selectedSubjects.sort((firstSubject, secondSubject) =>
                    this.compareText(
                        this.subjectOverviewSubjectKey(firstSubject),
                        this.subjectOverviewSubjectKey(secondSubject),
                    )
                    || this.compareText(firstSubject.json_code, secondSubject.json_code)
                    || this.compareNullableNumbers(firstSubject.semester, secondSubject.semester))
            }

            selectedSubjects.forEach(subject => {
                const branch = rule.selection_key === 'branch'
                    ? option.value
                    : subject.branch || 'common'
                const group = groups.get(branch) || {
                    branch,
                    label: this.subjectRuleSubjectGroupLabel(branch),
                    subjects: [],
                }

                group.subjects.push({
                    value: subject.stable_key,
                    title: [
                        `Sem. ${subject.semester || '–'}`,
                        subject.json_code || subject.json_subject || subject.name || 'Ohne Code',
                        rule.selection_key === 'branch' && this.isBranchSubject(subject)
                            ? this.displayBranch(subject.branch)
                            : null,
                    ].filter(Boolean).join(' · '),
                })
                groups.set(branch, group)
            })

            const branchOrder = ['common', ...this.subjectOverviewBranchKeys()]

            return [...groups.values()].sort((firstGroup, secondGroup) =>
                branchOrder.indexOf(firstGroup.branch) - branchOrder.indexOf(secondGroup.branch))
        },
        subjectRuleSubjectGroupLabel(branch) {
            if (branch === 'wirtschaftskundlich') return 'Wirtschaftskundlicher Zweig'
            if (branch === 'gymnasial') return 'Gymnasialer Zweig'

            return 'Für beide Zweige'
        },
        subjectRuleSelectedSubjectFamilies(rule, subjectGroup) {
            if (rule.selection_key !== 'branch') {
                return [{ key: 'all', label: '', subjects: subjectGroup.subjects }]
            }

            const subjectsByKey = new Map(this.subjectRows.map(subject => [subject.stable_key, subject]))
            const families = new Map()

            subjectGroup.subjects.forEach(subjectOption => {
                const subject = subjectsByKey.get(subjectOption.value)
                const subjectKey = this.subjectOverviewSubjectKey(subject)
                const isLanguage = ['L', 'F', 'S', 'L/F/S'].includes(subjectKey)
                const familyKey = isLanguage ? 'languages' : subjectKey || 'other'
                const family = families.get(familyKey) || {
                    key: familyKey,
                    label: isLanguage ? 'Sprachen' : subjectKey || 'Weitere',
                    subjects: [],
                }

                family.subjects.push(subjectOption)
                families.set(familyKey, family)
            })

            const orderedFamilies = [...families.values()]
            const musicFamilyIndex = orderedFamilies.findIndex(family => family.key === 'ME')
            const artFamilyIndex = orderedFamilies.findIndex(family => family.key === 'BE')

            if (artFamilyIndex !== -1 && musicFamilyIndex !== -1) {
                const [musicFamily] = orderedFamilies.splice(musicFamilyIndex, 1)
                const updatedArtFamilyIndex = orderedFamilies.findIndex(family => family.key === 'BE')

                orderedFamilies.splice(updatedArtFamilyIndex + 1, 0, musicFamily)
            }

            return orderedFamilies
        },
        subjectRuleSubjectOptions(rule, option) {
            return this.subjectRows
                .filter(subject => subject.stable_key)
                .map(subject => ({
                    value: subject.stable_key,
                    title: [
                        `Sem. ${subject.semester || '–'}`,
                        subject.json_code || subject.json_subject || subject.name || 'Ohne Code',
                        rule.selection_key !== 'branch' || subject.branch !== option.value
                            ? this.displayBranch(subject.branch)
                            : null,
                    ].filter(Boolean).join(' · '),
                }))
        },
        subjectRuleSelectedSubjectOptions(rule, option) {
            const subjectsByKey = new Map(
                this.subjectRuleSubjectOptions(rule, option)
                    .map(subject => [subject.value, subject]),
            )

            return option.subject_keys
                .map(subjectKey => subjectsByKey.get(subjectKey))
                .filter(Boolean)
        },
        subjectRuleAvailableSubjectOptions(rule, option) {
            const selectedSubjectKeys = new Set(option.subject_keys)

            return this.subjectRuleSubjectOptions(rule, option)
                .filter(subject => !selectedSubjectKeys.has(subject.value))
        },
        addSubjectRuleOptionSubject(option, subjectKey) {
            if (!subjectKey || option.subject_keys.includes(subjectKey)) return

            option.subject_keys.push(subjectKey)
        },
        removeSubjectRuleOptionSubject(option, subjectKey) {
            option.subject_keys = option.subject_keys.filter(key => key !== subjectKey)
        },
        normalizedSubjectAction(subsection) {
            const allowedActions = this.canManageSubjectSettings
                ? ['subject-plan-v2', 'subjects', 'rules', 'mapping']
                : ['subject-plan-v2']

            return allowedActions.includes(subsection) ? subsection : 'subject-plan-v2'
        },
        handleSubjectNavigation(key) {
            this.subject_action = this.normalizedSubjectAction(key)
            if (this.embedded) {
                return
            }

            this.$router.push({
                path: `/admin/students-timetables/subjects-overview/${this.subject_action}`,
                query: { ...this.$route.query, study_program: this.studyProgram },
            })
        },
        redirectMissingSubjectRoute() {
            if (
                this.embedded
                || this.$route.params.section !== 'subjects-overview'
                || this.$route.params.subsection
            ) {
                return false
            }

            this.subject_action = 'subject-plan-v2'
            this.$router.replace({
                path: '/admin/students-timetables/subjects-overview/subject-plan-v2',
                query: { ...this.$route.query, study_program: this.studyProgram },
            })

            return true
        },
        redirectRemovedSubjectImportRoute() {
            if (this.embedded || this.$route.params.subsection !== 'import') return false

            this.subject_action = 'subject-plan-v2'
            this.$router.replace({
                path: '/admin/students-timetables/subjects-overview/subject-plan-v2',
                query: { ...this.$route.query, study_program: this.studyProgram },
            })

            return true
        },
        redirectUnauthorizedSubjectRoute() {
            if (this.embedded) return

            const subsection = this.$route.params.subsection
            if (!subsection || this.normalizedSubjectAction(subsection) === subsection) return

            this.subject_action = 'subject-plan-v2'
            this.$router.replace({
                path: '/admin/students-timetables/subjects-overview/subject-plan-v2',
                query: { ...this.$route.query, study_program: this.studyProgram },
            })
        },
        changeStudyProgram(value) {
            const studyProgram = normalizeStudyProgram(value)
            if (studyProgram === this.studyProgram) return

            this.applyStudyProgram(studyProgram)
            if (this.embedded) return

            this.$router.replace({
                path: this.$route.path,
                query: { ...this.$route.query, study_program: studyProgram },
            })
        },
        applyStudyProgram(studyProgram) {
            this.studyProgram = studyProgram
            this.refreshForSchoolyearChange()
        },
        refreshForSchoolyearChange() {
            this.settingsError = ''
            this.settingsMessage = ''
            this.subjectsEditMode = false
            this.mappingsEditMode = false
            this.rulesEditMode = false
            this.editingSubjectRuleKey = null
            this.subjectRuleOpenPanels = []
            this.subjectRows = []
            this.subjectMappings = []
            this.subjectRowsSnapshot = []
            this.subjectMappingsSnapshot = []
            this.subjectRules = []
            this.subjectRulesSnapshot = []
            this.previousSchoolyear = null
            this.loadSettings()
        },
        async loadSettings() {
            this.settingsLoading = true
            this.settingsError = ''
            try {
                const response = await axios.get(subjectSettingsRoute.url(
                    { studyProgram: this.studyProgram },
                    this.personalSchoolyearRouteOptions,
                ))
                this.applySettings(response.data.data || {})
            } catch {
                this.subjectRows = []
                this.subjectMappings = []
                this.previousSchoolyear = null
                this.settingsError = 'Die Fächer-Einstellungen konnten nicht geladen werden.'
            } finally {
                this.settingsLoading = false
            }
        },
        subjectOverviewGridStyle(columnGroup) {
            const sumColumnCount = columnGroup.showSum ? 1 : 0

            return {
                '--subject-plan-columns': columnGroup.columns.length + 1 + sumColumnCount,
            }
        },
        subjectOverviewV2SubjectsForColumn(subjects, column) {
            return this.uniqueSubjectOverviewTotalSubjects(subjects.filter(subject =>
                column.subjectKeys.includes(this.subjectOverviewSubjectKey(subject)),
            )).sort((firstSubject, secondSubject) => {
                const firstSubjectIndex = column.subjectKeys.indexOf(this.subjectOverviewSubjectKey(firstSubject))
                const secondSubjectIndex = column.subjectKeys.indexOf(this.subjectOverviewSubjectKey(secondSubject))

                return firstSubjectIndex - secondSubjectIndex
                    || this.compareText(firstSubject.json_code, secondSubject.json_code)
            })
        },
        subjectOverviewV2Cell(subjects, column) {
            const matchingSubjects = this.subjectOverviewV2SubjectsForColumn(subjects, column)
            const groups = this.subjectOverviewV2CellGroups(matchingSubjects)
            const singleGroup = groups.length === 1 ? groups[0] : null

            return {
                column,
                subjects: matchingSubjects,
                groups,
                branches: [...new Set(groups.map(group => group.branch))],
                display_code: singleGroup?.display_code || this.subjectOverviewV2CellDisplayCode(matchingSubjects),
                hours: singleGroup?.hours || '',
            }
        },
        subjectOverviewV2CellGroups(subjects) {
            const subjectsByBranch = new Map()

            subjects.forEach(subject => {
                const branch = this.subjectOverviewV2SubjectBranch(subject)
                const branchSubjects = subjectsByBranch.get(branch) || []

                branchSubjects.push(subject)
                subjectsByBranch.set(branch, branchSubjects)
            })

            const sharedBranchSubjects = this.subjectOverviewV2SharedBranchSubjects(subjectsByBranch)

            if (sharedBranchSubjects.subjects.length) {
                const commonSubjects = this.uniqueSubjectOverviewSubjects([
                    ...(subjectsByBranch.get('common') || []),
                    ...sharedBranchSubjects.subjects,
                ])

                subjectsByBranch.set('common', commonSubjects)
                this.subjectOverviewBranchKeys().forEach(branch => {
                    const remainingSubjects = (subjectsByBranch.get(branch) || [])
                        .filter(subject => !sharedBranchSubjects.consumed.has(subject))

                    if (remainingSubjects.length) {
                        subjectsByBranch.set(branch, remainingSubjects)
                    } else {
                        subjectsByBranch.delete(branch)
                    }
                })
            }

            const branchOrder = ['common', ...this.subjectOverviewBranchKeys()]

            return [...subjectsByBranch.entries()]
                .sort(([firstBranch], [secondBranch]) =>
                    branchOrder.indexOf(firstBranch) - branchOrder.indexOf(secondBranch))
                .map(([branch, branchSubjects]) => ({
                    branch,
                    subjects: branchSubjects,
                    display_code: this.subjectOverviewV2CellDisplayCode(branchSubjects),
                    hours: this.formatSubjectHours(this.sumSubjectHours(branchSubjects)),
                    class: branch === 'common'
                        ? 'subject-plan-cell--filled'
                        : `subject-plan-cell--${branch}`,
                }))
        },
        subjectOverviewV2SharedBranchSubjects(subjectsByBranch) {
            const [firstBranch, secondBranch] = this.subjectOverviewBranchKeys()
            const firstBranchSubjects = subjectsByBranch.get(firstBranch) || []
            const secondBranchSubjects = subjectsByBranch.get(secondBranch) || []
            const secondSubjectsByKey = new Map()

            secondBranchSubjects.forEach(subject => {
                const subjectKey = this.subjectOverviewV2SharedBranchSubjectKey(subject)
                const matchingSubjects = secondSubjectsByKey.get(subjectKey) || []

                matchingSubjects.push(subject)
                secondSubjectsByKey.set(subjectKey, matchingSubjects)
            })

            const sharedSubjects = []
            const consumedSubjects = new Set()

            firstBranchSubjects.forEach(subject => {
                const subjectKey = this.subjectOverviewV2SharedBranchSubjectKey(subject)
                const [matchingSubject, ...remainingMatchingSubjects] = secondSubjectsByKey.get(subjectKey) || []

                if (!matchingSubject) return

                secondSubjectsByKey.set(subjectKey, remainingMatchingSubjects)
                sharedSubjects.push(subject)
                consumedSubjects.add(subject)
                consumedSubjects.add(matchingSubject)
            })

            return {
                subjects: sharedSubjects,
                consumed: consumedSubjects,
            }
        },
        subjectOverviewV2SharedBranchSubjectKey(subject) {
            return [
                this.subjectOverviewSubjectKey(subject),
                String(subject.json_code || '').trim(),
                this.subjectOverviewV2SubjectDisplayCodes(subject).join('|'),
                Number(subject.hours_per_week || 0),
                this.isSubjectOverviewChoiceSubject(subject) ? 'choice' : 'required',
            ].join('|')
        },
        subjectOverviewV2CellDisplayCode(subjects) {
            const displayCodes = [...new Set(subjects.flatMap(subject =>
                this.subjectOverviewV2SubjectDisplayCodes(subject),
            ))]
            const combinedDisplayCodes = this.subjectOverviewCombinedCompactChoiceCodes(displayCodes)
            const isChoice = subjects.some(subject => this.isSubjectOverviewChoiceSubject(subject))

            return `${combinedDisplayCodes.join(' | ')}${isChoice ? ' *' : ''}`
        },
        subjectOverviewV2SubjectDisplayCodes(subject) {
            const moduleNumber = String(subject.json_code || '').match(/\d+$/u)?.[0] || ''
            const ruleDisplayCodes = (this.subjectRules || [])
                .filter(rule => rule.is_active !== false)
                .flatMap(rule => rule.options
                    .filter(option =>
                        option.course_code_prefix
                        && option.subject_keys.includes(subject.stable_key),
                    )
                    .map(option => {
                        const courseCodePrefix = String(option.course_code_prefix)
                        const displayPrefix = rule.selection_key === 'religion'
                            && courseCodePrefix.toUpperCase() !== 'ETH'
                            ? 'R'
                            : courseCodePrefix

                        return `${displayPrefix}${moduleNumber}`
                    }))

            if (!ruleDisplayCodes.length) return [this.alternativeDisplay(subject.json_code)]

            return [...new Set(ruleDisplayCodes)].sort((firstCode, secondCode) =>
                this.subjectOverviewV2CourseCodePriority(firstCode)
                - this.subjectOverviewV2CourseCodePriority(secondCode)
                || this.compareText(firstCode, secondCode),
            )
        },
        subjectOverviewV2CourseCodePriority(courseCode) {
            if (/^R\d/u.test(courseCode)) return 0
            if (/^ETH\d/u.test(courseCode)) return 1

            return 2
        },
        subjectOverviewV2SubjectBranch(subject) {
            const ruleBranches = (this.subjectRules || [])
                .filter(rule => rule.is_active !== false && rule.selection_key === 'branch')
                .flatMap(rule => rule.options
                    .filter(option => option.subject_keys.includes(subject.stable_key))
                    .map(option => option.value))
                .filter(branch => this.subjectOverviewBranchKeys().includes(branch))
            const distinctRuleBranches = [...new Set(ruleBranches)]

            if (distinctRuleBranches.length === 1) return distinctRuleBranches[0]

            return subject.branch || 'common'
        },
        subjectOverviewV2BranchLegendLines(branch) {
            const languageLines = this.subjectOverviewV2LanguageLegendLines(branch)
            const branchRequirements = [
                ...this.subjectOverviewV2ArtsLegendDescriptions(branch),
                ...this.subjectOverviewV2OtherBranchLegendDescriptions(branch),
            ]

            return [
                ...languageLines,
                ...(branchRequirements.length ? [branchRequirements.join(' / ')] : []),
            ]
        },
        subjectRuleImpactRequiredDescriptions(branch) {
            const requiredGroups = new Map()

            this.activeSubjectRows
                .filter(subject => this.subjectOverviewV2SubjectBranch(subject) === branch)
                .filter(subject => !this.isSubjectOverviewChoiceSubject(subject))
                .filter(subject => !['L', 'F', 'S', 'L/F/S'].includes(this.subjectOverviewSubjectKey(subject)))
                .forEach(subject => {
                    const subjectKey = this.subjectOverviewSubjectKey(subject)
                    const moduleNumber = this.subjectOverviewV2SubjectModuleNumber(subject)
                    const groupKey = ['BE', 'ME'].includes(subjectKey)
                        ? `arts-${moduleNumber}`
                        : subjectKey
                    const group = requiredGroups.get(groupKey) || {
                        firstSemester: Number(subject.semester || 0),
                        key: groupKey,
                        codes: [],
                    }

                    group.firstSemester = Math.min(group.firstSemester, Number(subject.semester || 0))
                    group.codes.push(String(subject.json_code || ''))
                    requiredGroups.set(groupKey, group)
                })

            return [...requiredGroups.values()]
                .sort((firstGroup, secondGroup) =>
                    firstGroup.firstSemester - secondGroup.firstSemester
                    || this.compareText(firstGroup.key, secondGroup.key))
                .map(group => this.subjectOverviewV2GermanList(
                    [...new Set(group.codes)].sort((firstCode, secondCode) => this.compareText(firstCode, secondCode)),
                    'und',
                ))
                .filter(Boolean)
        },
        subjectRuleImpactChoiceDescriptions(branch) {
            return [
                ...this.subjectOverviewV2LanguageLegendLines(branch).map(description => ({
                    label: 'Sprache',
                    description,
                })),
                ...this.subjectRuleImpactArtChoiceDescriptions(branch).map(description => ({
                    label: 'Künstlerisches Fach',
                    description,
                })),
            ]
        },
        subjectRuleImpactArtChoiceDescriptions(branch) {
            const artChoicesByModule = new Map()

            this.activeSubjectRows
                .filter(subject => this.subjectOverviewV2SubjectBranch(subject) === branch)
                .filter(subject => ['BE', 'ME'].includes(this.subjectOverviewSubjectKey(subject)))
                .filter(subject => this.isSubjectOverviewChoiceSubject(subject))
                .forEach(subject => {
                    const moduleNumber = this.subjectOverviewV2SubjectModuleNumber(subject)
                    const codes = artChoicesByModule.get(moduleNumber) || []

                    codes.push(String(subject.json_code || ''))
                    artChoicesByModule.set(moduleNumber, codes)
                })

            return [...artChoicesByModule.entries()]
                .sort(([firstModule], [secondModule]) => firstModule - secondModule)
                .map(([, codes]) => this.subjectOverviewV2GermanList(
                    [...new Set(codes)].sort((firstCode, secondCode) => this.compareText(firstCode, secondCode)),
                    'oder',
                ))
                .filter(Boolean)
        },
        subjectOverviewV2LanguageLegendLines(branch) {
            const languageRule = (this.subjectRules || [])
                .find(rule => rule.is_active !== false && rule.selection_key === 'language')

            if (!languageRule) return []

            const optionsByLastModule = new Map()

            languageRule.options.forEach(option => {
                const subjectKeys = new Set(option.subject_keys || [])
                const moduleNumbers = this.activeSubjectRows
                    .filter(subject => subjectKeys.has(subject.stable_key))
                    .filter(subject => ['common', branch].includes(this.subjectOverviewV2SubjectBranch(subject)))
                    .map(subject => this.subjectOverviewV2SubjectModuleNumber(subject))
                    .filter(moduleNumber => moduleNumber > 0)

                if (!moduleNumbers.length) return

                const lastModule = Math.max(...moduleNumbers)
                const optionLabels = optionsByLastModule.get(lastModule) || []

                optionLabels.push(this.subjectOverviewV2RuleOptionShortLabel(option))
                optionsByLastModule.set(lastModule, optionLabels)
            })

            return [...optionsByLastModule.entries()]
                .sort(([firstModule], [secondModule]) => firstModule - secondModule)
                .map(([lastModule, optionLabels]) =>
                    `${this.subjectOverviewV2GermanList(optionLabels, 'oder')} bis zum Modul ${lastModule}`)
        },
        subjectOverviewV2ArtsLegendDescriptions(branch) {
            const artSubjectsByModuleAndChoice = new Map()

            this.activeSubjectRows
                .filter(subject => this.subjectOverviewV2SubjectBranch(subject) === branch)
                .filter(subject => ['BE', 'ME'].includes(this.subjectOverviewSubjectKey(subject)))
                .forEach(subject => {
                    const moduleNumber = this.subjectOverviewV2SubjectModuleNumber(subject)
                    const isChoice = this.isSubjectOverviewChoiceSubject(subject)
                    const groupKey = `${moduleNumber}|${isChoice ? 'choice' : 'required'}`
                    const group = artSubjectsByModuleAndChoice.get(groupKey) || {
                        moduleNumber,
                        isChoice,
                        codes: [],
                    }

                    group.codes.push(String(subject.json_code || ''))
                    artSubjectsByModuleAndChoice.set(groupKey, group)
                })

            return [...artSubjectsByModuleAndChoice.values()]
                .sort((firstGroup, secondGroup) =>
                    firstGroup.moduleNumber - secondGroup.moduleNumber
                    || Number(firstGroup.isChoice) - Number(secondGroup.isChoice))
                .map(group => this.subjectOverviewV2GermanList(
                    [...new Set(group.codes)].sort((firstCode, secondCode) => this.compareText(firstCode, secondCode)),
                    group.isChoice ? 'oder' : 'und',
                ))
                .filter(Boolean)
        },
        subjectOverviewV2OtherBranchLegendDescriptions(branch) {
            const excludedSubjectKeys = ['BE', 'ME', 'L', 'F', 'S', 'L/F/S']
            const subjectsByFamily = new Map()

            this.activeSubjectRows
                .filter(subject => this.subjectOverviewV2SubjectBranch(subject) === branch)
                .filter(subject => !excludedSubjectKeys.includes(this.subjectOverviewSubjectKey(subject)))
                .forEach(subject => {
                    const subjectKey = this.subjectOverviewSubjectKey(subject)
                    const familySubjects = subjectsByFamily.get(subjectKey) || []

                    familySubjects.push(String(subject.json_code || ''))
                    subjectsByFamily.set(subjectKey, familySubjects)
                })

            return [...subjectsByFamily.entries()]
                .sort(([firstSubjectKey], [secondSubjectKey]) => this.compareText(firstSubjectKey, secondSubjectKey))
                .map(([, subjectCodes]) => this.subjectOverviewV2GermanList(
                    [...new Set(subjectCodes)].sort((firstCode, secondCode) => this.compareText(firstCode, secondCode)),
                    'und',
                ))
                .filter(Boolean)
        },
        subjectOverviewV2RuleOptionShortLabel(option) {
            return String(option.label || option.value || '')
                .replace(/^[^-–]+[-–]\s*/u, '')
                .trim()
        },
        subjectOverviewV2SubjectModuleNumber(subject) {
            return Number(String(subject.json_code || '').match(/(\d+)$/u)?.[1] || 0)
        },
        subjectOverviewV2GermanList(values, conjunction) {
            const uniqueValues = [...new Set(values.filter(Boolean))]

            if (uniqueValues.length < 2) return uniqueValues[0] || ''
            if (uniqueValues.length === 2) return `${uniqueValues[0]} ${conjunction} ${uniqueValues[1]}`

            return `${uniqueValues.slice(0, -1).join(', ')} ${conjunction} ${uniqueValues.at(-1)}`
        },
        subjectOverviewCellsForColumns(row, columns) {
            const columnKeys = columns.map(column => column.key)

            return row.cells.filter(cell => columnKeys.includes(cell.column.key))
        },
        subjectOverviewFooterForColumns(columns) {
            const columnKeys = columns.map(column => column.key)

            return this.subjectOverviewFooter.filter(column => columnKeys.includes(column.key))
        },
        subjectOverviewSubjectKey(subject = {}) {
            const jsonSubject = String(subject.json_subject || '').trim()

            if (jsonSubject) return jsonSubject

            return String(subject.json_code || '').replace(/\d+$/u, '')
        },
        subjectOverviewColumnSubtitle(column) {
            const subjectNames = this.activeSubjectRows
                .filter(subject => column.subjectKeys.includes(this.subjectOverviewSubjectKey(subject)))
                .map(subject => this.subjectOverviewBaseName(subject))
                .filter(Boolean)

            if (!subjectNames.length) {
                return column.label.replace('/', ' / ')
            }

            return [...new Set(subjectNames)].join(' / ')
        },
        subjectOverviewV2ColumnSubtitle(column) {
            const subjectNames = this.activeSubjectRows
                .filter(subject => column.subjectKeys.includes(this.subjectOverviewSubjectKey(subject)))
                .map(subject => String(subject.name || subject.json_subject || subject.json_code || '')
                    .replace(/\s+\d+$/u, '')
                    .trim())
                .filter(Boolean)

            if (!subjectNames.length) {
                return column.label.replace('/', ' / ')
            }

            return [...new Set(subjectNames)].join(' / ')
        },
        subjectOverviewBaseName(subject = {}) {
            const firstName = this.subjectNameLines(subject)[0] || subject.name || subject.json_subject || subject.json_code

            return String(firstName || '').replace(/\s+\d+$/u, '').trim()
        },
        subjectOverviewCellClass(cell) {
            if (!cell.subjects.length) return 'subject-plan-cell--empty'

            const branches = cell.branches?.length
                ? cell.branches
                : [...new Set(cell.subjects.map(subject => subject.branch || 'common'))]

            if (branches.length === 1 && branches[0] !== 'common') {
                return `subject-plan-cell--${branches[0]}`
            }

            return 'subject-plan-cell--filled'
        },
        subjectOverviewDisplayCode(subject) {
            if (subject.display_code) return subject.display_code

            const displayCode = this.alternativeDisplay(subject.json_code)

            if (!this.isSubjectOverviewChoiceSubject(subject)) return displayCode

            return `${displayCode}*`
        },
        subjectOverviewCourseItems(subjects) {
            const mergedChoiceSubjects = this.subjectOverviewMergedChoiceSubjects(subjects)
            const mergedSubjects = this.subjectOverviewMergedCompactModuleSubjects(
                this.subjectOverviewMergedCompactChoiceModuleSubjects(mergedChoiceSubjects),
            )

            return mergedSubjects.flatMap(subject => {
                const displayCodes = this.subjectOverviewDisplayCodes(subject)

                if (displayCodes.length <= 1) {
                    return [
                        {
                            ...subject,
                            display_code: this.subjectOverviewDisplayCode(subject),
                            display_key: `${subject.local_id || subject.id || subject.json_code || ''}`,
                        },
                    ]
                }

                const subjectHours = Number(subject.hours_per_week || 0)
                const splitHours = subjectHours / displayCodes.length

                return displayCodes.map((displayCode, index) => ({
                    ...subject,
                    json_code: displayCode,
                    hours_per_week: Number.isFinite(splitHours) ? splitHours : subject.hours_per_week,
                    display_code: this.isSubjectOverviewChoiceSubject(subject) ? `${displayCode}*` : displayCode,
                    display_key: `${subject.local_id || subject.id || subject.json_code || ''}-${displayCode}-${index}`,
                }))
            })
        },
        subjectOverviewMergedChoiceSubjects(subjects) {
            const usedSubjectIndexes = new Set()

            return subjects.flatMap((subject, subjectIndex) => {
                if (usedSubjectIndexes.has(subjectIndex)) return []

                const choiceGroup = this.subjectOverviewMergeableChoiceGroupForSubject(subject)
                if (!choiceGroup) return [subject]

                const choiceSubjects = subjects
                    .map((candidate, candidateIndex) => ({ candidate, candidateIndex }))
                    .filter(({ candidate, candidateIndex }) =>
                        !usedSubjectIndexes.has(candidateIndex)
                        && this.subjectMatchesSubjectOverviewChoiceGroup(candidate, choiceGroup),
                    )

                if (choiceSubjects.length < 2) return [subject]

                choiceSubjects.forEach(({ candidateIndex }) => usedSubjectIndexes.add(candidateIndex))

                const sortedSubjects = choiceSubjects
                    .map(({ candidate }) => candidate)
                    .sort((firstSubject, secondSubject) => {
                        const orderedCodes = choiceGroup.orderedCodes || []
                        const firstIndex = orderedCodes.indexOf(String(firstSubject.json_code || ''))
                        const secondIndex = orderedCodes.indexOf(String(secondSubject.json_code || ''))

                        if (firstIndex >= 0 && secondIndex >= 0) return firstIndex - secondIndex

                        return this.compareText(firstSubject.json_code, secondSubject.json_code)
                    })
                const displayCodes = this.subjectOverviewCombinedCompactChoiceCodes(sortedSubjects
                    .map(candidate => this.alternativeDisplay(candidate.json_code))
                    .filter(displayCode => displayCode !== '-'))
                const optionHours = (choiceGroup.options || []).map(option => this.rawSubjectHours(
                    sortedSubjects.filter(candidate => option.subject_keys.includes(candidate.stable_key)),
                ))
                const subjectHours = optionHours.length
                    ? Math.max(...optionHours, 0)
                    : Math.max(...sortedSubjects.map(candidate => Number(candidate.hours_per_week || 0)), 0)

                return [
                    {
                        ...sortedSubjects[0],
                        branch: choiceGroup.branch === 'common' ? null : choiceGroup.branch,
                        json_code: displayCodes.join('/'),
                        hours_per_week: Number.isFinite(subjectHours) ? subjectHours : sortedSubjects[0].hours_per_week,
                        display_code: `${displayCodes.join('/')}*`,
                        display_key: [
                            'choice',
                            choiceGroup.semester,
                            choiceGroup.branch,
                            displayCodes.join('/'),
                        ].join('-'),
                    },
                ]
            })
        },
        subjectOverviewMergedCompactChoiceModuleSubjects(subjects) {
            if (this.studyProgram !== COMPACT_STUDY_PROGRAM) return subjects

            const usedSubjectIndexes = new Set()

            return subjects.flatMap((subject, subjectIndex) => {
                if (usedSubjectIndexes.has(subjectIndex)) return []

                const parsedCodes = String(subject.display_code || '')
                    .replace(/\*$/u, '')
                    .split('/')
                    .map(code => code.match(/^(.*?)(\d+)$/u))

                if (parsedCodes.length < 2 || parsedCodes.some(code => !code)) return [subject]

                const prefixSignature = parsedCodes.map(code => code[1]).join('|')
                const matchingSubjects = subjects
                    .map((candidate, candidateIndex) => ({ candidate, candidateIndex }))
                    .filter(({ candidate, candidateIndex }) => {
                        if (usedSubjectIndexes.has(candidateIndex)) return false
                        if ((candidate.branch || 'common') !== (subject.branch || 'common')) return false

                        const candidateCodes = String(candidate.display_code || '')
                            .replace(/\*$/u, '')
                            .split('/')
                            .map(code => code.match(/^(.*?)(\d+)$/u))

                        return candidateCodes.length === parsedCodes.length
                            && candidateCodes.every(code => code)
                            && candidateCodes.map(code => code[1]).join('|') === prefixSignature
                    })

                if (matchingSubjects.length < 2) return [subject]

                matchingSubjects.forEach(({ candidateIndex }) => usedSubjectIndexes.add(candidateIndex))

                const combinedCodes = parsedCodes.map((parsedCode, codeIndex) => {
                    const moduleNumbers = matchingSubjects
                        .map(({ candidate }) => String(candidate.display_code || '')
                            .replace(/\*$/u, '')
                            .split('/')[codeIndex]
                            .match(/\d+$/u)?.[0])
                        .filter(Boolean)
                        .sort((firstNumber, secondNumber) => Number(firstNumber) - Number(secondNumber))

                    return `${parsedCode[1]}${moduleNumbers.join('+')}`
                })

                return [{
                    ...subject,
                    json_code: combinedCodes.join('/'),
                    hours_per_week: matchingSubjects.reduce(
                        (total, item) => total + Number(item.candidate.hours_per_week || 0),
                        0,
                    ),
                    display_code: `${combinedCodes.join('/')}*`,
                    display_key: [
                        'compact-choice-modules',
                        subject.semester,
                        subject.branch || 'common',
                        combinedCodes.join('-'),
                    ].join('-'),
                }]
            })
        },
        subjectOverviewMergedCompactModuleSubjects(subjects) {
            if (this.studyProgram !== COMPACT_STUDY_PROGRAM) return subjects

            const groupedSubjects = new Map()

            subjects.forEach((subject, index) => {
                const code = String(subject.json_code || '')
                const moduleMatch = code.match(/^(.*?)(\d+)$/u)
                const groupKey = subject.display_code || !moduleMatch
                    ? `single-${index}`
                    : [
                        subject.semester || '',
                        subject.branch || 'common',
                        this.subjectOverviewSubjectKey(subject),
                        moduleMatch[1],
                    ].join('|')
                const group = groupedSubjects.get(groupKey) || []

                group.push({ subject, moduleMatch })
                groupedSubjects.set(groupKey, group)
            })

            return [...groupedSubjects.values()].map(group => {
                if (group.length < 2 || group.some(item => !item.moduleMatch)) return group[0].subject

                const sortedGroup = [...group].sort((firstItem, secondItem) =>
                    Number(firstItem.moduleMatch[2]) - Number(secondItem.moduleMatch[2]))
                const moduleNumbers = sortedGroup.map(item => item.moduleMatch[2])
                const subjectHours = sortedGroup.reduce(
                    (total, item) => total + Number(item.subject.hours_per_week || 0),
                    0,
                )

                return {
                    ...sortedGroup[0].subject,
                    json_code: `${sortedGroup[0].moduleMatch[1]}${moduleNumbers.join('+')}`,
                    hours_per_week: subjectHours,
                    display_key: [
                        'compact-modules',
                        sortedGroup[0].subject.semester,
                        sortedGroup[0].subject.branch || 'common',
                        sortedGroup[0].moduleMatch[1],
                        moduleNumbers.join('-'),
                    ].join('-'),
                }
            })
        },
        subjectOverviewCombinedCompactChoiceCodes(displayCodes) {
            if (this.studyProgram !== COMPACT_STUDY_PROGRAM) return displayCodes

            const codesByPrefix = new Map()

            displayCodes.forEach(displayCode => {
                const moduleMatch = String(displayCode).match(/^(.*?)(\d+)$/u)
                if (!moduleMatch) {
                    codesByPrefix.set(`single-${displayCode}`, { prefix: displayCode, moduleNumbers: [] })

                    return
                }

                const group = codesByPrefix.get(moduleMatch[1]) || {
                    prefix: moduleMatch[1],
                    moduleNumbers: [],
                }

                group.moduleNumbers.push(moduleMatch[2])
                codesByPrefix.set(moduleMatch[1], group)
            })

            return [...codesByPrefix.values()].map(group => {
                if (!group.moduleNumbers.length) return group.prefix

                const moduleNumbers = [...group.moduleNumbers].sort((firstNumber, secondNumber) =>
                    Number(firstNumber) - Number(secondNumber))

                return `${group.prefix}${moduleNumbers.join('+')}`
            })
        },
        subjectOverviewMergeableChoiceGroupForSubject(subject) {
            return this.subjectOverviewChoiceGroups().find(group =>
                !group.choices
                && this.subjectMatchesSubjectOverviewChoiceGroup(subject, group),
            ) || null
        },
        subjectOverviewDisplayCodes(subject) {
            if (subject.display_code) return [subject.display_code]

            const displayCode = this.alternativeDisplay(subject.json_code)

            if (displayCode === '-') return [displayCode]
            if (this.isAlternativeSubjectCode(subject.json_code, subject.json_subject)) return [displayCode]

            return displayCode
                .split('/')
                .map(code => code.trim())
                .filter(Boolean)
        },
        isSubjectOverviewChoiceSubject(subject) {
            return this.subjectOverviewChoiceGroups().some(group =>
                this.subjectMatchesSubjectOverviewChoiceGroup(subject, group),
            )
        },
        subjectMatchesSubjectOverviewChoiceGroup(subject, group) {
            return (
                Number(subject.semester) === group.semester
                && this.subjectOverviewChoiceGroupBranch(subject) === group.branch
                && (
                    (group.options || []).some(option => option.subject_keys.includes(subject.stable_key))
                    || group.codes.includes(String(subject.json_code || ''))
                )
            )
        },
        subjectOverviewChoiceGroups() {
            if (Number(this.subjectRulesVersion || 0) === 0 || !Array.isArray(this.subjectRules)) {
                return [
                    ...this.subjectOverviewReligionChoiceGroups(),
                    ...this.subjectOverviewLanguageChoiceGroups(),
                    ...this.subjectOverviewArtChoiceGroups(),
                ]
            }

            const subjectsByKey = new Map((this.activeSubjectRows || []).map(subject => [subject.stable_key, subject]))

            return this.subjectRules
                .filter(rule => rule.is_active !== false)
                .flatMap(rule => {
                    const contexts = new Map()

                    rule.options.forEach(option => {
                        const optionSubjects = option.subject_keys
                            .map(subjectKey => subjectsByKey.get(subjectKey))
                            .filter(Boolean)

                        optionSubjects.forEach(subject => {
                            const semester = Number(subject.semester) || 0
                            const branch = this.subjectOverviewChoiceGroupBranch(subject)
                            const contextKey = `${semester}|${branch}`
                            const context = contexts.get(contextKey) || {
                                semester,
                                branch,
                                options: [],
                            }
                            let contextOption = context.options.find(item => item.value === option.value)

                            if (!contextOption) {
                                contextOption = {
                                    value: option.value,
                                    label: option.label,
                                    subject_keys: [],
                                    codes: [],
                                }
                                context.options.push(contextOption)
                            }

                            contextOption.subject_keys.push(subject.stable_key)
                            contextOption.codes.push(String(subject.json_code || ''))
                            contexts.set(contextKey, context)
                        })
                    })

                    return [...contexts.values()]
                        .filter(context => context.semester > 0 && context.options.filter(option => option.subject_keys.length).length > 1)
                        .map(context => ({
                            ...context,
                            rule_key: rule.stable_key,
                            codes: [...new Set(context.options.flatMap(option => option.codes))],
                            orderedCodes: context.options.flatMap(option => option.codes),
                        }))
                })
        },
        subjectOverviewReligionChoiceGroups() {
            const groups = new Map()
            const subjectRows = this.activeSubjectRows || []

            subjectRows
                .filter(subject => this.subjectOverviewSubjectKey(subject) === 'R/ET')
                .forEach(subject => {
                    const semester = Number(subject.semester) || 0
                    const branch = this.subjectOverviewChoiceGroupBranch(subject)
                    const code = String(subject.json_code || '')
                    const groupKey = [semester, branch, code].join('|')

                    if (semester < 1 || !code || groups.has(groupKey)) return

                    groups.set(groupKey, {
                        semester,
                        branch,
                        codes: [code],
                        choices: ['Rev', 'Ris', 'Rk', 'Ror', 'ET'],
                    })
                })

            const separateReligionSubjects = subjectRows.filter(subject =>
                ['R', 'ET'].includes(this.subjectOverviewSubjectKey(subject)),
            )
            const separateGroups = new Map()

            separateReligionSubjects.forEach(subject => {
                const semester = Number(subject.semester) || 0
                const branch = this.subjectOverviewChoiceGroupBranch(subject)
                const code = String(subject.json_code || '')
                const moduleNumber = code.match(/\d+$/u)?.[0] || code
                const groupKey = [semester, branch, moduleNumber].join('|')
                const group = separateGroups.get(groupKey) || {
                    semester,
                    branch,
                    codes: new Set(),
                    subjects: new Set(),
                }

                group.codes.add(String(subject.json_code || ''))
                group.subjects.add(this.subjectOverviewSubjectKey(subject))
                separateGroups.set(groupKey, group)
            })

            separateGroups.forEach((group, groupKey) => {
                if (group.semester < 1 || group.subjects.size < 2) return

                const codes = [...group.codes]
                const orderedCodes = [
                    ...codes.filter(code => code.startsWith('R')),
                    ...codes.filter(code => code.startsWith('ET')),
                ]

                groups.set(`separate|${groupKey}`, {
                    semester: group.semester,
                    branch: group.branch,
                    codes,
                    orderedCodes,
                })
            })

            return [...groups.values()]
        },
        subjectOverviewLanguageChoiceGroups() {
            return this.subjectOverviewAlternativeChoiceGroups(
                ['L', 'F', 'S'],
                this.studyProgram === COMPACT_STUDY_PROGRAM,
            )
        },
        subjectOverviewArtChoiceGroups() {
            const groups = this.subjectOverviewAlternativeChoiceGroups(['BE', 'ME'])
            if (this.studyProgram !== COMPACT_STUDY_PROGRAM) return groups

            return groups.filter(group => {
                const codes = new Set(group.codes)

                return (
                    group.branch === 'wirtschaftskundlich'
                    && codes.has('BE1')
                    && codes.has('ME1')
                ) || (
                    group.branch === 'gymnasial'
                    && codes.has('BE2')
                    && codes.has('ME2')
                )
            })
        },
        subjectOverviewAlternativeChoiceGroups(subjectKeys, groupByModule = false) {
            const alternativeSubjects = (this.activeSubjectRows || []).filter(subject =>
                subjectKeys.includes(this.subjectOverviewSubjectKey(subject)),
            )
            const groups = new Map()

            alternativeSubjects.forEach(subject => {
                const subjectCode = String(subject.json_code || '')
                const moduleNumber = groupByModule ? subjectCode.match(/\d+$/u)?.[0] || subjectCode : ''
                const groupKey = [
                    Number(subject.semester) || 0,
                    this.subjectOverviewChoiceGroupBranch(subject),
                    moduleNumber,
                ].join('|')
                const group = groups.get(groupKey) || {
                    semester: Number(subject.semester) || 0,
                    branch: this.subjectOverviewChoiceGroupBranch(subject),
                    codes: new Set(),
                    subjects: new Set(),
                }

                group.codes.add(String(subject.json_code || ''))
                group.subjects.add(this.subjectOverviewSubjectKey(subject))
                groups.set(groupKey, group)
            })

            return [...groups.values()]
                .filter(group => group.semester > 0 && group.subjects.size > 1)
                .map(group => {
                    const codes = [...group.codes].sort((firstCode, secondCode) => {
                        const firstSubjectIndex = subjectKeys.findIndex(subjectKey => firstCode.startsWith(subjectKey))
                        const secondSubjectIndex = subjectKeys.findIndex(subjectKey => secondCode.startsWith(subjectKey))

                        return firstSubjectIndex - secondSubjectIndex
                            || this.compareText(firstCode, secondCode)
                    })

                    return {
                        semester: group.semester,
                        branch: group.branch,
                        codes,
                        orderedCodes: codes,
                    }
                })
        },
        subjectOverviewChoiceGroupBranch(subject = {}) {
            return subject.branch || 'common'
        },
        uniqueSubjectOverviewSubjects(subjects) {
            const seenSubjectKeys = new Set()

            return subjects.filter(subject => {
                const subjectKey = [
                    subject.semester || '',
                    this.subjectOverviewSubjectKey(subject),
                    subject.json_code || '',
                    subject.hours_per_week || '',
                ].join('|')

                if (seenSubjectKeys.has(subjectKey)) return false

                seenSubjectKeys.add(subjectKey)

                return true
            })
        },
        uniqueSubjectOverviewTotalSubjects(subjects) {
            const seenSubjectKeys = new Set()

            return subjects.filter(subject => {
                const subjectKey = [
                    subject.semester || '',
                    subject.branch || 'common',
                    this.subjectOverviewSubjectKey(subject),
                    subject.json_code || '',
                    subject.json_subject || '',
                    subject.name || '',
                    subject.hours_per_week || '',
                ].join('|')

                if (seenSubjectKeys.has(subjectKey)) return false

                seenSubjectKeys.add(subjectKey)

                return true
            })
        },
        subjectOverviewFooterTotals(subjects) {
            const commonTotal = this.sumSubjectHours(subjects.filter(subject => this.isCommonSubject(subject)))
            const hasBranchSubjects = subjects.some(subject => this.isBranchSubject(subject))

            if (!hasBranchSubjects) {
                if (commonTotal <= 0) return []

                return [{ value: this.formatSubjectHours(commonTotal), class: '' }]
            }

            const branchTotals = this.subjectOverviewBranchKeys().map(branch => ({
                branch,
                total: commonTotal + this.sumSubjectHours(subjects.filter(subject => subject.branch === branch)),
            }))
            const distinctTotals = [...new Set(branchTotals.map(total => total.total))]

            if (distinctTotals.length === 1) {
                const [total] = distinctTotals

                if (total <= 0) return []

                return [{ value: this.formatSubjectHours(total), class: '' }]
            }

            return branchTotals.map(total => ({
                value: this.formatSubjectHours(total.total),
                class: `subject-plan-total--${total.branch}`,
            }))
        },
        subjectOverviewTotals(subjects) {
            const commonTotal = this.sumSubjectHours(subjects.filter(subject => this.isCommonSubject(subject)))
            const branchTotals = this.subjectOverviewBranchTotals(subjects, commonTotal)

            if (branchTotals.length) {
                return branchTotals
            }

            if (commonTotal <= 0) return []

            return [{ value: this.formatSubjectHours(commonTotal), class: '' }]
        },
        subjectOverviewBranchTotals(subjects, commonTotal) {
            const availableBranches = new Set(subjects
                .map(subject => subject.branch)
                .filter(branch => branch && branch !== 'common'))
            const branchOrder = [
                ...this.subjectOverviewBranchKeys().filter(branch => availableBranches.has(branch)),
                ...[...availableBranches]
                    .filter(branch => !this.subjectOverviewBranchKeys().includes(branch))
                    .sort((firstBranch, secondBranch) =>
                        this.compareText(this.displayBranch(firstBranch), this.displayBranch(secondBranch))),
            ]
            const branchTotals = branchOrder
                .map(branch => ({
                    value: this.formatSubjectHours(commonTotal + this.sumSubjectHours(subjects.filter(subject => subject.branch === branch))),
                    class: `subject-plan-total--${branch}`,
                }))

            return branchTotals.filter((total, index, totals) => totals.findIndex(item => item.value === total.value) === index)
        },
        subjectOverviewBranchKeys() {
            return ['wirtschaftskundlich', 'gymnasial']
        },
        isCommonSubject(subject) {
            return !subject.branch || subject.branch === 'common'
        },
        isBranchSubject(subject) {
            return Boolean(subject.branch && subject.branch !== 'common')
        },
        sumSubjectHours(subjects) {
            return this.rawSubjectHours(subjects) - this.subjectOverviewChoiceDuplicateHours(subjects)
        },
        rawSubjectHours(subjects) {
            return subjects.reduce((total, subject) => total + Number(subject.hours_per_week || 0), 0)
        },
        subjectOverviewChoiceDuplicateHours(subjects) {
            return this.subjectOverviewChoiceGroups().reduce((duplicateHours, group) => {
                const choiceSubjects = subjects.filter(subject => this.subjectMatchesSubjectOverviewChoiceGroup(subject, group))

                if (choiceSubjects.length < 2) return duplicateHours

                if (!group.options?.length) {
                    const countedHours = Math.max(
                        ...choiceSubjects.map(subject => Number(subject.hours_per_week || 0)),
                        0,
                    )

                    return duplicateHours + this.rawSubjectHours(choiceSubjects) - countedHours
                }

                const optionHours = (group.options || []).map(option => this.rawSubjectHours(
                    choiceSubjects.filter(subject => option.subject_keys.includes(subject.stable_key)),
                ))
                const countedHours = Math.max(...optionHours, 0)

                return duplicateHours + this.rawSubjectHours(choiceSubjects) - countedHours
            }, 0)
        },
        formatSubjectHours(value) {
            if (Number.isInteger(value)) return String(value)

            return String(value).replace('.', ',')
        },
        applySettings(settings) {
            this.subjectRows = (settings.subjects || []).map(subject => this.normalizeSubjectRow(subject))
            this.subjectMappings = (settings.mappings || []).map(mapping => this.normalizeMappingRow(mapping))
            this.subjectRulesVersion = Number(settings.rule_set?.version || 0)
            this.subjectRules = (settings.rule_set?.rules || []).map(rule => this.normalizeSubjectRule(rule))
            this.previousSchoolyear = settings.previous_schoolyear || null

            if (!this.subjectsEditMode) {
                this.subjectRowsSnapshot = this.cloneRows(this.subjectRows)
            }

            if (!this.mappingsEditMode) {
                this.subjectMappingsSnapshot = this.cloneRows(this.subjectMappings)
            }

            if (!this.rulesEditMode) {
                this.subjectRulesSnapshot = this.deepClone(this.subjectRules)
            }
        },
        normalizeSubjectRow(subject = {}) {
            return {
                local_id: this.nextLocalId++,
                id: subject.id || null,
                stable_key: subject.stable_key || null,
                semester: subject.semester ?? null,
                branch: subject.branch || null,
                json_code: subject.json_code || '',
                json_subject: subject.json_subject || '',
                name: subject.name || '',
                hours_per_week: subject.hours_per_week ?? null,
                is_active: subject.is_active !== false,
                sort_index: this.nextLocalId,
            }
        },
        normalizeMappingRow(mapping = {}) {
            return {
                local_id: this.nextLocalId++,
                id: mapping.id || null,
                json_subject: mapping.json_subject || '',
                tt_subject: mapping.tt_subject || '',
                note: mapping.note || null,
                is_active: mapping.is_active !== false,
            }
        },
        normalizeSubjectRule(rule = {}) {
            return {
                stable_key: rule.stable_key || this.createStableKey(),
                name: rule.name || '',
                label: rule.label || '',
                selection_key: rule.selection_key || 'branch',
                selection_mode: rule.selection_mode || 'single',
                min_selections: Number(rule.min_selections ?? 1),
                max_selections: Number(rule.max_selections ?? 1),
                conditions: (rule.conditions || []).map(condition => ({
                    field: condition.field || 'branch',
                    operator: condition.operator || 'equals',
                    value: Array.isArray(condition.value) ? condition.value.join(', ') : condition.value || '',
                })),
                is_active: rule.is_active !== false,
                options: (rule.options || []).map(option => this.normalizeSubjectRuleOption(option)),
            }
        },
        normalizeSubjectRuleOption(option = {}) {
            return {
                stable_key: option.stable_key || this.createStableKey(),
                value: option.value || '',
                label: option.label || '',
                course_code_prefix: option.course_code_prefix || '',
                subject_keys: [...new Set(option.subject_keys || [])],
            }
        },
        createStableKey() {
            if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID()

            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
                const randomValue = Math.floor(Math.random() * 16)
                const value = character === 'x' ? randomValue : (randomValue & 0x3) | 0x8

                return value.toString(16)
            })
        },
        addSubjectRow() {
            this.subjectRows.push(
                this.normalizeSubjectRow({
                    branch: null,
                    is_active: true,
                }),
            )
        },
        removeSubjectRow(subject) {
            const subjectIndex = this.subjectRows.findIndex(row => row.local_id === subject.local_id)

            if (subjectIndex === -1) return

            this.subjectRows.splice(subjectIndex, 1)
        },
        addMappingRow() {
            this.subjectMappings.push(this.normalizeMappingRow({ is_active: true }))
        },
        removeMappingRow(index) {
            this.subjectMappings.splice(index, 1)
        },
        startSubjectsEdit() {
            this.settingsMessage = ''
            this.subjectRowsSnapshot = this.cloneRows(this.subjectRows)
            this.subjectsEditMode = true
        },
        cancelSubjectsEdit() {
            this.subjectRows = this.cloneRows(this.subjectRowsSnapshot)
            this.subjectsEditMode = false
            this.settingsError = ''
        },
        startMappingsEdit() {
            this.settingsMessage = ''
            this.subjectMappingsSnapshot = this.cloneRows(this.subjectMappings)
            this.mappingsEditMode = true
        },
        cancelMappingsEdit() {
            this.subjectMappings = this.cloneRows(this.subjectMappingsSnapshot)
            this.mappingsEditMode = false
            this.settingsError = ''
        },
        startRulesEdit(rule) {
            this.settingsMessage = ''
            this.subjectRulesSnapshot = this.deepClone(this.subjectRules)
            this.rulesEditMode = true
            this.editingSubjectRuleKey = rule.stable_key
            this.subjectRuleOpenPanels = [...new Set([...this.subjectRuleOpenPanels, rule.stable_key])]
        },
        cancelRulesEdit() {
            this.subjectRules = this.deepClone(this.subjectRulesSnapshot)
            this.rulesEditMode = false
            this.editingSubjectRuleKey = null
            this.settingsError = ''
        },
        isSubjectRuleEditing(rule) {
            return this.rulesEditMode && this.editingSubjectRuleKey === rule.stable_key
        },
        removeRuleCondition(rule, conditionIndex) {
            rule.conditions.splice(conditionIndex, 1)
        },
        deepClone(value) {
            return JSON.parse(JSON.stringify(value))
        },
        cloneRows(rows) {
            return rows.map(row => ({ ...row }))
        },
        sortSubjectRows(key) {
            if (this.subjectSort.key === key) {
                this.subjectSort.direction = this.subjectSort.direction === 'asc' ? 'desc' : 'asc'

                return
            }

            this.subjectSort = {
                key,
                direction: 'asc',
            }
        },
        subjectSortIcon(key) {
            if (this.subjectSort.key !== key) return 'mdi-swap-vertical'

            return this.subjectSort.direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down'
        },
        compareSubjectRows(firstSubject, secondSubject, key) {
            if (key === 'semester') {
                return this.compareNullableNumbers(firstSubject.semester, secondSubject.semester)
            }

            if (key === 'branch') {
                return this.compareText(this.displayBranch(firstSubject.branch), this.displayBranch(secondSubject.branch))
            }

            if (key === 'name') {
                return this.compareText(
                    this.subjectNameLines(firstSubject).join(' '),
                    this.subjectNameLines(secondSubject).join(' '),
                )
            }

            return 0
        },
        compareSubjectRowDisplayOrder(firstSubject, secondSubject) {
            const semesterComparison = this.compareNullableNumbers(firstSubject.semester, secondSubject.semester)

            if (semesterComparison !== 0) return semesterComparison

            const branchComparison = this.compareText(
                this.displayBranch(firstSubject.branch),
                this.displayBranch(secondSubject.branch),
            )

            if (branchComparison !== 0) return branchComparison

            const codeComparison = this.compareText(firstSubject.json_code, secondSubject.json_code)

            if (codeComparison !== 0) return codeComparison

            const subjectComparison = this.compareText(firstSubject.json_subject, secondSubject.json_subject)

            if (subjectComparison !== 0) return subjectComparison

            return (firstSubject.sort_index || 0) - (secondSubject.sort_index || 0)
        },
        compareNullableNumbers(firstValue, secondValue) {
            const firstNumber = Number(firstValue || 9999)
            const secondNumber = Number(secondValue || 9999)

            return firstNumber - secondNumber
        },
        compareText(firstValue, secondValue) {
            return String(firstValue || '').localeCompare(String(secondValue || ''), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        displayValue(value) {
            if (value === null || value === undefined || value === '') return '-'

            return value
        },
        alternativeDisplay(value) {
            const displayValue = this.displayValue(value)

            if (displayValue === '-') return displayValue

            return this.alternativeParts(String(displayValue)).join(' / ')
        },
        alternativeLines(value) {
            const displayValue = this.displayValue(value)

            if (displayValue === '-') return [displayValue]

            return this.alternativeParts(String(displayValue))
        },
        subjectNameLines(subject = {}) {
            const displayName = this.displayValue(subject.name)

            if (displayName === '-') return [displayName]

            const explicitModuleNumbers = this.explicitModuleNumbers(subject.json_code, subject.json_subject)

            if (!String(displayName).match(/\d$/) && explicitModuleNumbers.length) {
                return explicitModuleNumbers.map(moduleNumber => `${displayName} ${moduleNumber}`)
            }

            if (String(displayName).includes('/') && !this.isAlternativeSubjectCode(subject.json_code, subject.json_subject)) {
                return [displayName]
            }

            return this.alternativeLines(displayName)
        },
        isAlternativeSubjectCode(code, abbreviation) {
            const normalizedAbbreviation = String(abbreviation || '').trim()
            const normalizedCode = String(code || '').trim()

            if (normalizedAbbreviation.includes('/')) return true

            return normalizedCode.includes('/') && !this.explicitModuleNumbers(normalizedCode, normalizedAbbreviation).length
        },
        explicitModuleNumbers(code, abbreviation) {
            const normalizedAbbreviation = String(abbreviation || '').trim()
            const normalizedCode = String(code || '').trim()

            if (!normalizedAbbreviation || !normalizedCode.includes('/')) return []

            const moduleNumbers = normalizedCode
                .split('/')
                .map(part => part.trim())
                .filter(Boolean)
                .map(part => {
                    const moduleMatch = part.match(new RegExp(`^${this.escapeRegExp(normalizedAbbreviation)}(\\d+)$`, 'u'))

                    return moduleMatch?.[1] || null
                })

            if (!moduleNumbers.length || moduleNumbers.some(moduleNumber => !moduleNumber)) return []

            return moduleNumbers
        },
        escapeRegExp(value) {
            return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
        },
        alternativeParts(value) {
            const normalizedValue = String(value || '').trim()

            const suffixMatch = normalizedValue.match(/^(.*?)(\d+)$/)

            if (suffixMatch?.[1]?.includes('/')) {
                const suffixSeparator = suffixMatch[1].endsWith(' ') ? ' ' : ''

                return suffixMatch[1]
                    .split('/')
                    .map(part => part.trim())
                    .filter(Boolean)
                    .map(part => (part.match(/\d$/) ? part : `${part}${suffixSeparator}${suffixMatch[2]}`))
            }

            const compactModuleMatch = normalizedValue.match(/^(.*?)([1-9]{2,})$/)

            if (compactModuleMatch) {
                const suffixSeparator = compactModuleMatch[1].endsWith(' ') ? ' ' : ''

                return compactModuleMatch[2]
                    .split('')
                    .map(moduleNumber => `${compactModuleMatch[1].trim()}${suffixSeparator}${moduleNumber}`)
            }

            if (!normalizedValue.includes('/')) return [normalizedValue]

            return normalizedValue
                .split('/')
                .map(part => part.trim())
                .filter(Boolean)
        },
        displayBranch(branch) {
            if (!branch || branch === 'common') return 'alle'
            if (branch === 'wirtschaftskundlich') return 'Wirtschaftskundlich'
            if (branch === 'gymnasial') return 'Gymnasial'

            return this.displayValue(branch)
        },
        async saveSubjectRows() {
            this.subjectsSaving = true
            this.settingsError = ''
            this.settingsMessage = ''
            try {
                const response = await axios.put(updateSubjectsRoute.url(
                    { studyProgram: this.studyProgram },
                    this.personalSchoolyearRouteOptions,
                ), {
                    subjects: this.subjectRows.map(subject => ({
                        semester: subject.semester || null,
                        stable_key: subject.stable_key || null,
                        branch: subject.branch === 'common' ? null : subject.branch || null,
                        json_code: subject.json_code || null,
                        json_subject: subject.json_subject || null,
                        name: subject.name || null,
                        hours_per_week: subject.hours_per_week || null,
                        is_active: subject.is_active !== false,
                    })),
                })
                this.applySettings(response.data.data || {})
                this.subjectsEditMode = false
                this.subjectRowsSnapshot = this.cloneRows(this.subjectRows)
                this.settingsMessage = 'Fächer gespeichert.'
            } catch {
                this.settingsError = 'Die Fächer konnten nicht gespeichert werden.'
            } finally {
                this.subjectsSaving = false
            }
        },
        async saveMappings() {
            this.mappingsSaving = true
            this.settingsError = ''
            this.settingsMessage = ''
            try {
                const response = await axios.put(updateSubjectMappingsRoute.url(
                    { studyProgram: this.studyProgram },
                    this.personalSchoolyearRouteOptions,
                ), {
                    mappings: this.subjectMappings.map(mapping => ({
                        json_subject: mapping.json_subject || null,
                        tt_subject: mapping.tt_subject || null,
                        note: mapping.note || null,
                        is_active: mapping.is_active !== false,
                    })),
                })
                this.applySettings(response.data.data || {})
                this.mappingsEditMode = false
                this.subjectMappingsSnapshot = this.cloneRows(this.subjectMappings)
                this.settingsMessage = 'Zuordnungen gespeichert.'
            } catch {
                this.settingsError = 'Die Zuordnungen konnten nicht gespeichert werden.'
            } finally {
                this.mappingsSaving = false
            }
        },
        async saveSubjectRules() {
            this.rulesSaving = true
            this.settingsError = ''
            this.settingsMessage = ''

            try {
                const response = await axios.put(updateRulesRoute.url(
                    { studyProgram: this.studyProgram },
                    this.personalSchoolyearRouteOptions,
                ), {
                    version: this.subjectRulesVersion,
                    rules: this.subjectRules.map(rule => ({
                        ...rule,
                        conditions: rule.conditions.map(condition => ({
                            ...condition,
                            value: ['in', 'not_in'].includes(condition.operator)
                                ? String(condition.value || '').split(',').map(value => value.trim()).filter(Boolean)
                                : condition.value,
                        })),
                        options: rule.options.map(option => ({
                            ...option,
                            course_code_prefix: option.course_code_prefix || null,
                            subject_keys: [...new Set(option.subject_keys || [])],
                        })),
                    })),
                })
                this.subjectRulesVersion = Number(response.data.data?.version || this.subjectRulesVersion + 1)
                this.subjectRules = (response.data.data?.rules || []).map(rule => this.normalizeSubjectRule(rule))
                this.subjectRulesSnapshot = this.deepClone(this.subjectRules)
                this.rulesEditMode = false
                this.editingSubjectRuleKey = null
                this.settingsMessage = response.data.message || 'Regeln gespeichert und angewendet.'
            } catch (error) {
                this.settingsError = error?.response?.status === 409
                    ? 'Die Regeln wurden inzwischen geändert. Ihre Eingaben bleiben erhalten; laden Sie die Seite neu, bevor Sie erneut speichern.'
                    : error?.response?.data?.message || 'Die Regeln konnten nicht gespeichert werden.'
            } finally {
                this.rulesSaving = false
            }
        },
    },
}
</script>

<style scoped>
.subject-subnav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px;
    border: 1px solid rgba(37, 99, 235, 0.16);
    background: rgba(255, 255, 255, 0.88);
}

.subject-subnav__button {
    background: #dbeafe !important;
    border: 1px solid rgba(37, 99, 235, 0.22) !important;
    color: #1e3a8a !important;
    text-transform: none;
    letter-spacing: 0;
}

.subject-subnav__button.v-btn--variant-flat {
    background: rgb(var(--v-theme-primary)) !important;
    border-color: rgba(30, 64, 175, 0.52) !important;
    color: rgb(var(--v-theme-on-primary)) !important;
}

.subject-section-card {
    max-width: 920px;
}

.subject-rule-grid,
.subject-rule-condition-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    align-items: start;
}

.subject-rule-condition-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
}

.subject-rule-subject-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.subject-rule-option-heading__swatch {
    flex: 0 0 22px;
}

.subject-rule-impact-preview__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
}

.subject-rule-impact-preview__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.subject-rule-impact-card {
    overflow: hidden;
}

.subject-rule-impact-card--wirtschaftskundlich {
    border-color: rgba(55, 146, 94, 0.42) !important;
}

.subject-rule-impact-card--gymnasial {
    border-color: rgba(31, 153, 198, 0.42) !important;
}

.subject-rule-impact-card__title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
}

.subject-rule-impact-card__content {
    display: grid;
    gap: 16px;
}

.subject-rule-impact-row {
    display: grid;
    gap: 8px;
}

.subject-rule-impact-row__label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.subject-rule-impact-values {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.subject-rule-impact-choices {
    display: grid;
    gap: 6px;
}

.subject-rule-impact-choice {
    padding: 8px 10px;
    border-radius: 8px;
    background: rgba(37, 99, 235, 0.06);
    font-size: 0.9rem;
}

.subject-rule-subject-group + .subject-rule-subject-group {
    margin-top: 14px;
}

.subject-rule-subject-group__title {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 7px;
    color: rgba(var(--v-theme-on-surface), 0.72);
    font-size: 0.82rem;
    font-weight: 700;
}

.subject-rule-subject-group__swatch {
    width: 18px;
    height: 18px;
    flex: 0 0 18px;
    border-radius: 6px;
}

.subject-rule-subject-family {
    display: grid;
    gap: 8px;
}

.subject-rule-subject-family + .subject-rule-subject-family {
    margin-top: 10px;
}

.subject-rule-subject-family--labelled {
    grid-template-columns: minmax(86px, 120px) minmax(0, 1fr);
    align-items: start;
}

.subject-rule-subject-family__label {
    padding-top: 4px;
    font-size: 0.82rem;
    font-weight: 800;
}

@media (max-width: 760px) {
    .subject-rule-grid,
    .subject-rule-condition-grid,
    .subject-rule-impact-preview__grid {
        grid-template-columns: 1fr;
    }

    .subject-rule-impact-preview__header {
        align-items: stretch;
        flex-direction: column;
    }

    .subject-rule-subject-family--labelled {
        grid-template-columns: 1fr;
    }
}

.subject-overview-card {
    width: 100%;
    max-width: none;
}

.subject-overview-v2-card__text {
    display: flex;
    min-height: 320px;
    flex-direction: column;
}

.subject-overview-v2-card__canvas {
    min-height: 220px;
    flex: 1;
}

.subject-plan-wrap.subject-plan-v2-wrap {
    overflow-x: auto;
}

.subject-plan-v2-grid {
    min-width: 920px;
}

.subject-plan-cell--v2-course {
    flex-direction: column;
    gap: 2px;
}

.subject-plan-cell--v2-branches {
    align-items: stretch;
    padding: 2px;
    background: #cfdde0;
}

.subject-plan-v2-course-group {
    display: flex;
    min-height: 34px;
    flex: 1;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3px;
    border-radius: 5px;
}

.subject-overview-v2-card__footer {
    padding-top: 12px;
    border-top: 1px solid rgba(15, 23, 42, 0.12);
}

.subject-section-card__title,
.subject-overview-card__title {
    min-height: 40px;
    padding: 8px 12px;
    font-size: 0.95rem;
}

.subject-section-card__text,
.subject-overview-card__text {
    padding: 8px 12px 12px;
}

.subject-section-card :deep(.v-btn) {
    min-height: 30px;
}

.subject-section-card :deep(.v-card-title .v-icon) {
    font-size: 18px;
}

.subject-overview-card :deep(.v-expansion-panel-title) {
    min-height: 42px;
    padding: 8px 12px;
}

.subject-overview-card :deep(.v-expansion-panel-text__wrapper) {
    padding: 8px 12px 12px;
}

.subject-plan-wrap {
    overflow-x: auto;
    padding-bottom: 4px;
}

.subject-plan-layout--split {
    display: none;
}

.subject-plan-table + .subject-plan-table {
    margin-top: 8px;
}

.subject-plan-grid {
    display: grid;
    width: 100%;
    min-width: 920px;
    grid-template-columns: repeat(var(--subject-plan-columns), minmax(48px, 1fr));
    gap: 2px;
}

.subject-plan-cell {
    display: flex;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    padding: 3px;
    border-radius: 6px;
    background: #cfdde0;
    color: #020617;
    text-align: center;
}

.subject-plan-cell--header {
    min-height: 50px;
    flex-direction: column;
    background: #bfced1;
}

.subject-plan-cell--footer {
    min-height: 36px;
    background: #bfced1;
    gap: 2px;
}

.subject-plan-cell--semester,
.subject-plan-cell--sum {
    background: #cfdde0;
}

.subject-plan-cell--header.subject-plan-cell--semester,
.subject-plan-cell--footer.subject-plan-cell--semester {
    font-size: 0.72rem;
}

.subject-plan-cell--semester-number {
    font-size: 0.9rem;
    font-weight: 750;
}

.subject-plan-cell--empty {
    background: #cfdde0;
}

.subject-plan-cell--filled {
    background: linear-gradient(135deg, #fde85a, #f9bd3e);
}

.subject-plan-cell--wirtschaftskundlich {
    background: linear-gradient(135deg, #b7ead6, #8bd47c);
}

.subject-plan-cell--gymnasial {
    background: linear-gradient(135deg, #a8e6f4, #72d2e8);
}

.subject-plan-cell--multi {
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    gap: 0;
    min-height: 58px;
    padding: 0 4px;
}

.subject-plan-header-code {
    font-size: 0.78rem;
    font-weight: 850;
    line-height: 1.1;
}

.subject-plan-header-name {
    max-width: 100%;
    margin-top: 2px;
    overflow: hidden;
    font-size: 0.52rem;
    line-height: 1.08;
    text-overflow: ellipsis;
}

.subject-plan-item + .subject-plan-item {
    margin-top: 3px;
}

.subject-plan-cell--multi .subject-plan-item + .subject-plan-item {
    margin-top: 0;
}

.subject-plan-item--course {
    position: relative;
    display: flex;
    min-height: 28px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px;
    padding: 3px 0;
}

.subject-plan-item--course + .subject-plan-item--course {
    border-top: 1px solid rgba(15, 23, 42, 0.42);
}

.subject-plan-code {
    font-size: 0.78rem;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

.subject-plan-hours {
    margin-top: 1px;
    font-size: 0.76rem;
    line-height: 1.1;
}

.subject-plan-item--course .subject-plan-code {
    font-weight: 400;
    text-align: center;
}

.subject-plan-item--course .subject-plan-hours {
    min-width: 18px;
    margin-top: 0;
    font-weight: 400;
    text-align: center;
}

.subject-plan-total {
    display: inline-flex;
    min-width: 18px;
    min-height: 18px;
    align-items: center;
    justify-content: center;
    padding: 1px 3px;
    border-radius: 5px;
    font-size: 0.74rem;
    line-height: 1;
}

.subject-plan-total--wirtschaftskundlich {
    background: #8bd47c;
}

.subject-plan-total--gymnasial {
    background: #72d2e8;
}

.subject-plan-choice-note {
    margin-top: 6px;
    font-size: 0.76rem;
    font-weight: 650;
}

.subject-plan-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 8px;
}

.subject-plan-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.74rem;
    font-weight: 650;
}

.subject-plan-legend--detailed {
    align-items: stretch;
    gap: 28px;
}

.subject-plan-legend-item--detailed {
    min-width: 320px;
    flex: 1 1 420px;
    align-items: center;
    font-weight: 400;
}

.subject-plan-legend-copy {
    min-width: 0;
}

.subject-plan-legend-title {
    margin-bottom: 1px;
    font-size: 0.8rem;
    font-variant: small-caps;
    font-weight: 850;
    line-height: 1.1;
}

.subject-plan-legend-line {
    font-size: 0.78rem;
    line-height: 1.25;
}

.subject-plan-legend-swatch {
    display: inline-block;
    width: 22px;
    height: 14px;
    border-radius: 5px;
}

.subject-plan-legend-swatch--detailed {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    border-radius: 12px;
}

.subject-plan-legend-swatch--wirtschaftskundlich {
    background: #8bd47c;
}

.subject-plan-legend-swatch--gymnasial {
    background: #72d2e8;
}

.subjects-settings-table-wrap {
    overflow-x: auto;
}

.subjects-settings-table {
    width: 100%;
    min-width: 960px;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.82rem;
}

.subjects-settings-table th,
.subjects-settings-table td {
    border-bottom: 1px solid rgba(15, 23, 42, 0.1);
    padding: 6px;
    text-align: left;
    vertical-align: middle;
}

.subjects-settings-table th {
    color: #334155;
    font-weight: 750;
    white-space: nowrap;
}

.subjects-sort-button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 24px;
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    font: inherit;
    font-weight: inherit;
    letter-spacing: 0;
}

.subjects-sort-button:hover {
    color: rgb(var(--v-theme-primary));
}

.subjects-settings-table--subjects,
.subjects-settings-table--mappings {
    width: auto;
    min-width: 0;
    table-layout: fixed;
}

.subjects-settings-table--subjects th,
.subjects-settings-table--subjects td,
.subjects-settings-table--mappings th,
.subjects-settings-table--mappings td {
    padding: 4px 6px;
}

.subjects-settings-table--subjects td,
.subjects-settings-table--mappings td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.subjects-settings-table--subjects :deep(.v-field__input),
.subjects-settings-table--mappings :deep(.v-field__input) {
    min-height: 32px;
    padding-inline: 8px;
}

.subjects-settings-table__semester {
    width: 54px;
}

.subjects-settings-table__branch {
    width: 128px;
}

.subjects-settings-table__code {
    width: 92px;
}

.subjects-settings-table__subject {
    width: 82px;
}

.subjects-settings-table__name {
    width: 160px;
}

.subjects-settings-table__name-lines {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.subjects-settings-table__hours {
    width: 92px;
}

.subjects-settings-table__active {
    width: 72px;
}

.subject-active-switch {
    transform: scale(0.78);
    transform-origin: left center;
}

.subjects-settings-table__actions {
    width: 40px;
    text-align: center;
}

.subjects-settings-table__mapping-json {
    width: 92px;
}

.subjects-settings-table__mapping-tt {
    width: 82px;
}

.subjects-settings-table__mapping-note {
    width: 180px;
}

@media (max-width: 1279px) {
    .subject-plan-wrap {
        overflow-x: visible;
    }

    .subject-plan-layout--desktop {
        display: none;
    }

    .subject-plan-layout--split {
        display: block;
    }

    .subject-plan-layout--split .subject-plan-grid {
        min-width: 0;
    }
}

@media (max-width: 700px) {
    .subject-plan-cell--header.subject-plan-cell--semester,
    .subject-plan-cell--footer.subject-plan-cell--semester {
        font-size: 0.62rem;
    }
}

@media (max-width: 599px) {
    .subject-study-program-toggle {
        display: flex;
        flex-wrap: wrap;
        width: 100%;
        height: auto;
    }

    .subject-study-program-toggle > :deep(.v-btn) {
        flex: 1 1 180px;
        min-height: 44px;
    }

    .subject-overview-card__title {
        flex-wrap: wrap;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .subject-plan-wrap {
        overflow-x: auto;
    }

    .subject-plan-layout--split .subject-plan-grid {
        min-width: 560px;
    }
}
</style>
