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

        <v-card v-if="subject_action === 'import'" rounded="lg" border class="subject-section-card mb-4">
            <v-card-title class="subject-section-card__title d-flex align-center ga-2">
                <v-icon icon="mdi-upload" />
                Import
            </v-card-title>
            <v-card-text class="subject-section-card__text">
                <v-alert v-if="uploadError" type="error" variant="tonal" class="mb-3">
                    {{ uploadError }}
                </v-alert>
                <v-alert v-if="uploadedFilename" type="success" variant="tonal" class="mb-3">
                    JSON-Datei gespeichert: <strong>{{ uploadedFilename }}</strong>
                </v-alert>
                <FileUpload
                    :path="'/api/admin/students-timetables/subjects-overview-json'"
                    fileLabel
                    :allowedFileTypes="['application/json']"
                    :refreshFilePond="refreshFilePond"
                    @uploadStart="onUploadStart"
                    @fileUploadFinished="onUploadFinished"
                    @error="onUploadError" />
            </v-card-text>
        </v-card>

        <v-card v-if="['overview', 'subject-plan'].includes(subject_action)" rounded="lg" border class="subject-overview-card mb-4">
            <v-card-title class="subject-overview-card__title d-flex align-center ga-2">
                <v-icon :icon="subject_action === 'subject-plan' ? 'mdi-table-large' : 'mdi-view-dashboard-outline'" />
                {{ subject_action === 'subject-plan' ? 'Fächerübersicht' : 'Übersicht' }}
                <v-chip size="x-small" color="primary" variant="tonal">
                    {{ subject_action === 'subject-plan' ? subjectRows.length : imports.length }}
                </v-chip>
            </v-card-title>
            <v-card-text class="subject-overview-card__text">
                <v-progress-linear v-if="loading || settingsLoading" indeterminate color="primary" class="mb-2" />

                <v-alert v-if="!loading && !imports.length" type="info" variant="tonal">
                    Noch keine JSON-Datei importiert.
                </v-alert>

                <v-alert
                    v-if="subject_action === 'subject-plan' && !settingsLoading && imports.length && !activeSubjectRows.length"
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
                                <div v-if="columnGroup.showSum" class="subject-plan-cell subject-plan-cell--footer subject-plan-cell--sum"></div>
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

                <div v-if="subject_action === 'overview' && imports.length" class="d-flex align-center ga-2 mb-3">
                    <v-icon icon="mdi-history" size="18" color="primary" />
                    <span class="text-subtitle-2 font-weight-bold">Importverlauf</span>
                    <v-chip size="x-small" color="primary" variant="tonal">
                        {{ imports.length }}
                    </v-chip>
                </div>

                <v-expansion-panels v-if="subject_action === 'overview' && imports.length" variant="accordion" multiple>
                    <v-expansion-panel
                        v-for="importItem in imports"
                        :key="importItem.filename"
                        elevation="0"
                        class="subject-import-panel">
                        <v-expansion-panel-title>
                            <div class="subject-import-title">
                                <v-icon icon="mdi-code-json" color="primary" size="18" />
                                <div class="flex-grow-1">
                                    <div class="font-weight-medium">{{ importItem.filename }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ formatDate(importItem.uploaded_at) }} · {{ formatFileSize(importItem.size) }}
                                    </div>
                                </div>
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ importSubjectCountLabel(importItem.analysis) }}
                                </v-chip>
                            </div>
                        </v-expansion-panel-title>
                        <v-expansion-panel-text>
                            <div class="d-flex align-center ga-2 mb-3">
                                <v-icon icon="mdi-chart-box-outline" size="18" color="primary" />
                                <span class="text-subtitle-2 font-weight-bold">Analyse</span>
                            </div>

                            <v-alert
                                v-if="!importItem.analysis?.semesters?.length"
                                type="info"
                                variant="tonal"
                                class="mb-0">
                                Keine Fächer oder Semester in der JSON-Datei erkannt.
                            </v-alert>

                            <div v-else class="subject-analysis-grid">
                                <section
                                    v-for="semester in importItem.analysis.semesters"
                                    :key="`${importItem.filename}-${semester.key}`"
                                    class="subject-analysis-block">
                                    <div class="subject-analysis-block__header">
                                        <span>{{ semester.label }}</span>
                                        <v-chip size="x-small" color="primary" variant="tonal">
                                            {{ semester.subjects_count }}
                                        </v-chip>
                                    </div>

                                    <div v-if="semester.branch_variants?.length" class="branch-variant-list">
                                        <div
                                            v-for="branch in semester.branch_variants"
                                            :key="`${semester.key}-${branch.key}`"
                                            class="branch-variant-block"
                                            :class="branchVariantClass(branch.key)">
                                            <div class="branch-variant-block__header">
                                                <span>{{ displayBranchVariantLabel(branch) }}</span>
                                                <v-chip size="x-small" color="primary" variant="tonal">
                                                    {{ branch.subjects_count }}
                                                </v-chip>
                                            </div>
                                            <div v-if="branch.different_subjects?.length" class="branch-subject-groups">
                                                <div v-if="branch.common_subjects?.length" class="subject-chip-row">
                                                    <v-chip
                                                        v-for="subject in branch.common_subjects"
                                                        :key="`${semester.key}-${branch.key}-common-${subject.name}`"
                                                        size="small"
                                                        color="primary"
                                                        variant="tonal"
                                                        class="subject-chip">
                                                        <strong v-if="subject.short_name">{{
                                                            alternativeDisplay(subject.short_name)
                                                        }}</strong>
                                                        <span v-if="subject.short_name" class="mx-1">·</span>
                                                        <span>{{ alternativeDisplay(subject.name) }}</span>
                                                    </v-chip>
                                                </div>
                                                <div
                                                    class="subject-chip-row"
                                                    :class="{
                                                        'branch-different-subjects': branch.common_subjects?.length,
                                                    }">
                                                    <v-chip
                                                        v-for="subject in branch.different_subjects"
                                                        :key="`${semester.key}-${branch.key}-different-${subject.name}`"
                                                        size="small"
                                                        color="primary"
                                                        variant="tonal"
                                                        class="subject-chip">
                                                        <strong v-if="subject.short_name">{{
                                                            alternativeDisplay(subject.short_name)
                                                        }}</strong>
                                                        <span v-if="subject.short_name" class="mx-1">·</span>
                                                        <span>{{ alternativeDisplay(subject.name) }}</span>
                                                    </v-chip>
                                                </div>
                                            </div>
                                            <div v-else class="subject-chip-row">
                                                <v-chip
                                                    v-for="subject in branch.subjects"
                                                    :key="`${semester.key}-${branch.key}-${subject.name}`"
                                                    size="small"
                                                    color="primary"
                                                    variant="tonal"
                                                    class="subject-chip">
                                                    <strong v-if="subject.short_name">{{
                                                        alternativeDisplay(subject.short_name)
                                                    }}</strong>
                                                    <span v-if="subject.short_name" class="mx-1">·</span>
                                                    <span>{{ alternativeDisplay(subject.name) }}</span>
                                                </v-chip>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else class="subject-chip-row">
                                        <v-chip
                                            v-for="subject in semester.subjects"
                                            :key="`${semester.key}-${subject.name}`"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            class="subject-chip">
                                            <strong v-if="subject.short_name">{{ alternativeDisplay(subject.short_name) }}</strong>
                                            <span v-if="subject.short_name" class="mx-1">·</span>
                                            <span>{{ alternativeDisplay(subject.name) }}</span>
                                        </v-chip>
                                    </div>
                                </section>
                            </div>
                        </v-expansion-panel-text>
                    </v-expansion-panel>
                </v-expansion-panels>
            </v-card-text>
        </v-card>

        <v-alert v-if="settingsError && ['subjects', 'mapping'].includes(subject_action)" type="error" variant="tonal" class="mb-4">
            {{ settingsError }}
        </v-alert>
        <v-alert v-if="settingsMessage && ['subjects', 'mapping'].includes(subject_action)" type="success" variant="tonal" class="mb-4">
            {{ settingsMessage }}
        </v-alert>

        <v-card v-if="subject_action === 'subjects'" rounded="lg" border class="subject-section-card mb-4">
            <v-card-title class="subject-section-card__title d-flex flex-wrap align-center ga-2">
                <v-icon icon="mdi-table-edit" />
                Importierte Fächer
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
                <v-alert v-if="!settingsLoading && !subjectRows.length" type="info" variant="tonal">
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
                <v-alert v-if="!settingsLoading && !subjectMappings.length" type="info" variant="tonal">
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
import FileUpload from '@/pages/components/FileUpload.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    name: 'StudentsTimetablesSubjectsOverview',
    components: { FileUpload },
    data() {
        return {
            imports: [],
            loading: false,
            subject_action: this.normalizedSubjectAction(this.$route.params.subsection),
            uploadError: '',
            uploadedFilename: '',
            refreshFilePond: 0,
            subjectRows: [],
            subjectMappings: [],
            settingsLoading: false,
            subjectsSaving: false,
            mappingsSaving: false,
            subjectsEditMode: false,
            mappingsEditMode: false,
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
        this.loadImports()
        this.loadSettings()
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        subjectNavigationItems() {
            return [
                {
                    key: 'overview',
                    label: 'Übersicht',
                    icon: 'mdi-view-dashboard-outline',
                },
                {
                    key: 'subject-plan',
                    label: 'Grafik',
                    icon: 'mdi-table-large',
                },
                {
                    key: 'import',
                    label: 'Import',
                    icon: 'mdi-upload',
                },
                {
                    key: 'subjects',
                    label: 'Fächer',
                    icon: 'mdi-table-edit',
                },
                {
                    key: 'mapping',
                    label: 'Zuordnung',
                    icon: 'mdi-transit-connection-variant',
                },
            ]
        },
        sortedSubjectRows() {
            const directionMultiplier = this.subjectSort.direction === 'desc' ? -1 : 1

            return [...this.subjectRows].sort((firstSubject, secondSubject) => {
                const comparison = this.compareSubjectRows(firstSubject, secondSubject, this.subjectSort.key)

                if (comparison !== 0) return comparison * directionMultiplier

                return (firstSubject.sort_index || 0) - (secondSubject.sort_index || 0)
            })
        },
        activeSubjectRows() {
            return this.subjectRows.filter(subject => subject.is_active !== false)
        },
        subjectOverviewColumns() {
            return [
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
                { key: 'LPT/VWA', label: 'LPT/VWA', subjectKeys: ['LPT', 'VWA'] },
                { key: 'R/ET', label: 'R/ET', subjectKeys: ['R/ET'] },
                { key: 'L/F/S', label: 'L/F/S', subjectKeys: ['L/F/S'] },
                { key: 'D', label: 'D', subjectKeys: ['D'] },
                { key: 'E', label: 'E', subjectKeys: ['E'] },
                { key: 'M', label: 'M', subjectKeys: ['M'] },
            ].map(column => ({
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
                        branches: [...new Set(subjects.map(subject => subject.branch || 'common'))],
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
                8,
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
        subjectOverviewHasBranches() {
            return this.activeSubjectRows.some(subject => this.isBranchSubject(subject))
        },
        subjectOverviewHasChoiceSubjects() {
            return this.activeSubjectRows.some(subject => this.isSubjectOverviewChoiceSubject(subject))
        },
    },
    watch: {
        '$route.params.subsection'(subsection) {
            this.subject_action = this.normalizedSubjectAction(subsection)
        },
        'config.selected_schoolyear.id'() {
            this.refreshForSchoolyearChange()
        },
    },
    methods: {
        normalizedSubjectAction(subsection) {
            const allowedActions = ['overview', 'subject-plan', 'import', 'subjects', 'mapping']

            return allowedActions.includes(subsection) ? subsection : 'overview'
        },
        handleSubjectNavigation(key) {
            this.subject_action = this.normalizedSubjectAction(key)
            this.$router.replace({ path: `/admin/students-timetables/subjects-overview/${this.subject_action}` })
        },
        refreshForSchoolyearChange() {
            this.uploadError = ''
            this.uploadedFilename = ''
            this.settingsError = ''
            this.settingsMessage = ''
            this.subjectsEditMode = false
            this.mappingsEditMode = false
            this.subjectRows = []
            this.subjectMappings = []
            this.subjectRowsSnapshot = []
            this.subjectMappingsSnapshot = []
            this.imports = []
            this.refreshFilePond++
            this.loadImports()
            this.loadSettings()
        },
        async loadImports() {
            this.loading = true
            try {
                const response = await axios.get('/api/admin/students-timetables/subjects-overview-json')
                this.imports = response.data.data || []
            } catch {
                this.imports = []
            } finally {
                this.loading = false
            }
        },
        async loadSettings() {
            this.settingsLoading = true
            this.settingsError = ''
            try {
                const response = await axios.get('/api/admin/students-timetables/subjects-overview-settings')
                this.applySettings(response.data.data || {})
            } catch {
                this.subjectRows = []
                this.subjectMappings = []
                this.settingsError = 'Die Fächer-Einstellungen konnten nicht geladen werden.'
            } finally {
                this.settingsLoading = false
            }
        },
        onUploadStart() {
            this.uploadError = ''
            this.uploadedFilename = ''
            this.settingsMessage = ''
        },
        onUploadFinished(file) {
            this.uploadError = ''
            this.uploadedFilename = file?.name || 'gespeichert'
            this.refreshFilePond++
            this.loadImports()
            this.loadSettings()
        },
        onUploadError() {
            this.uploadedFilename = ''
            this.uploadError = 'Die JSON-Datei konnte nicht gespeichert werden.'
            this.refreshFilePond++
        },
        formatDate(dateString) {
            if (!dateString) return '-'

            return new Date(dateString).toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })
        },
        formatFileSize(size) {
            const bytes = Number(size || 0)
            if (bytes < 1024) return `${bytes} B`

            return `${(bytes / 1024).toFixed(1)} KB`
        },
        branchVariantClass(branchKey) {
            if (branchKey === 'common') return 'branch-variant-block--common'
            if (branchKey === 'wirtschaftskundlich') return 'branch-variant-block--wirtschaft'
            if (branchKey === 'gymnasial') return 'branch-variant-block--gymnasial'

            return ''
        },
        importSubjectCountLabel(analysis = {}) {
            const subjectsTotal = analysis?.subjects_total || 0
            const courseRowsTotal = analysis?.subject_rows?.length || 0

            if (!courseRowsTotal || courseRowsTotal === subjectsTotal) return `${subjectsTotal} Fächer`

            return `${subjectsTotal} Fächer / ${courseRowsTotal} Kurse`
        },
        subjectOverviewGridStyle(columnGroup) {
            const sumColumnCount = columnGroup.showSum ? 1 : 0

            return {
                '--subject-plan-columns': columnGroup.columns.length + 1 + sumColumnCount,
            }
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
            const displayCode = this.alternativeDisplay(subject.json_code)

            if (!this.isSubjectOverviewChoiceSubject(subject)) return displayCode

            return `${displayCode}*`
        },
        subjectOverviewCourseItems(subjects) {
            return subjects.flatMap(subject => {
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
        subjectOverviewDisplayCodes(subject) {
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
                Number(subject.semester) === group.semester && group.codes.includes(String(subject.json_code || '')),
            )
        },
        subjectMatchesSubjectOverviewChoiceGroup(subject, group) {
            return (
                Number(subject.semester) === group.semester
                && subject.branch === group.branch
                && group.codes.includes(String(subject.json_code || ''))
            )
        },
        subjectOverviewChoiceGroups() {
            return [
                {
                    semester: 1,
                    branch: 'common',
                    codes: ['R/ET1'],
                    choices: ['Rev', 'Ris', 'Rk', 'Ror', 'ET'],
                },
                {
                    semester: 2,
                    branch: 'common',
                    codes: ['R/ET2'],
                    choices: ['Rev', 'Ris', 'Rk', 'Ror', 'ET'],
                },
                {
                    semester: 3,
                    branch: 'common',
                    codes: ['R/ET3'],
                    choices: ['Rev', 'Ris', 'Rk', 'Ror', 'ET'],
                },
                {
                    semester: 4,
                    branch: 'common',
                    codes: ['R/ET4'],
                    choices: ['Rev', 'Ris', 'Rk', 'Ror', 'ET'],
                },
                {
                    semester: 7,
                    branch: 'wirtschaftskundlich',
                    codes: ['BE1', 'ME1'],
                },
                {
                    semester: 8,
                    branch: 'gymnasial',
                    codes: ['BE2', 'ME2'],
                },
            ]
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
            const branchTotals = [...new Set(subjects.map(subject => subject.branch).filter(branch => branch && branch !== 'common'))]
                .sort((firstBranch, secondBranch) => this.compareText(this.displayBranch(firstBranch), this.displayBranch(secondBranch)))
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

                const choiceHours = choiceSubjects.map(subject => Number(subject.hours_per_week || 0))
                const countedHours = Math.max(...choiceHours)

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

            if (!this.subjectsEditMode) {
                this.subjectRowsSnapshot = this.cloneRows(this.subjectRows)
            }

            if (!this.mappingsEditMode) {
                this.subjectMappingsSnapshot = this.cloneRows(this.subjectMappings)
            }
        },
        normalizeSubjectRow(subject = {}) {
            return {
                local_id: this.nextLocalId++,
                id: subject.id || null,
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
        displayBranchVariantLabel(branch) {
            return branch.label || this.displayBranch(branch.key)
        },
        async saveSubjectRows() {
            this.subjectsSaving = true
            this.settingsError = ''
            this.settingsMessage = ''
            try {
                const response = await axios.put('/api/admin/students-timetables/subjects-overview-settings/subjects', {
                    subjects: this.subjectRows.map(subject => ({
                        semester: subject.semester || null,
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
                const response = await axios.put('/api/admin/students-timetables/subjects-overview-settings/mappings', {
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

.subject-import-panel {
    border: 1px solid rgba(25, 118, 210, 0.16);
}

.subject-section-card {
    max-width: 920px;
}

.subject-overview-card {
    width: 100%;
    max-width: none;
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

.subject-plan-legend-swatch {
    display: inline-block;
    width: 22px;
    height: 14px;
    border-radius: 5px;
}

.subject-plan-legend-swatch--wirtschaftskundlich {
    background: #8bd47c;
}

.subject-plan-legend-swatch--gymnasial {
    background: #72d2e8;
}

.subject-import-panel + .subject-import-panel {
    margin-top: 4px;
}

.subject-import-title {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    min-width: 0;
}

.subject-import-title .font-weight-medium {
    font-size: 0.86rem;
    line-height: 1.2;
}

.subject-import-title .text-caption {
    font-size: 0.68rem !important;
}

.subject-analysis-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
}

.subject-analysis-block {
    border: 1px solid rgba(25, 118, 210, 0.16);
    border-radius: 6px;
    padding: 6px;
    background: #f8fbff;
}

.subject-analysis-block__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 5px;
}

.subject-chip-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.subject-chip {
    min-height: 20px;
    height: auto;
    width: 100%;
    font-size: 0.7rem;
    justify-content: flex-start;
}

.subject-chip :deep(.v-chip__content) {
    display: block;
    line-height: 1.15;
    overflow: hidden;
    padding: 2px 6px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.branch-subject-groups {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.branch-different-subjects {
    border-top: 1px solid rgba(15, 23, 42, 0.18);
    padding-top: 5px;
}

.branch-variant-list {
    display: grid;
    gap: 5px;
    grid-template-columns: minmax(0, 1fr);
}

.branch-variant-block {
    border: 1px solid rgba(25, 118, 210, 0.18);
    border-radius: 6px;
    padding: 6px;
}

.branch-variant-block--common {
    background: #f8fafc;
    border-color: rgba(100, 116, 139, 0.24);
}

.branch-variant-block--wirtschaft {
    background: #bbf7d0;
    border-color: rgba(22, 101, 52, 0.42);
}

.branch-variant-block--gymnasial {
    background: #bfdbfe;
    border-color: rgba(30, 64, 175, 0.42);
}

.branch-variant-block__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    font-size: 0.76rem;
    font-weight: 750;
    line-height: 1.2;
    margin-bottom: 4px;
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

    .subject-analysis-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 599px) {
    .subject-plan-wrap {
        overflow-x: auto;
    }

    .subject-plan-layout--split .subject-plan-grid {
        min-width: 560px;
    }
}
</style>
