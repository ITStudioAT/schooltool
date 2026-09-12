<template>
    <ItsGridBox variant="overview" color="primary" :title="assignGradingPartId ? 'Zuordnung' : 'Bereiche'" icon="mdi-layers-triple-outline" class="w-100">
        <template #header-actions>
            <v-btn v-if="!assignGradingPartId" color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-shape-square-plus" :disabled="isEditing" @click="openCreateAreaDialog">Neuer Bereich</v-btn>
        </template>

        <div v-if="!assignGradingPartId" class="text-body-2 text-medium-emphasis mt-2">Gruppieren Sie Einträge passend zu Schulstufe oder Fach.</div>

        <v-progress-linear v-if="isLoading" indeterminate color="primary" class="mt-4" />

        <div v-if="areas.length" v-show="!assignGradingPartId" class="entry-area-grid mt-4">
            <template v-for="area in areas" :key="area.id">
                <div class="entry-area-card" :class="{ 'entry-area-card-active': activeAreaId === area.id }">
                    <button type="button" class="entry-area-select" :disabled="isEditing" :aria-pressed="activeAreaId === area.id" @click="activeAreaId = area.id">
                        <span class="entry-area-icon"><v-icon icon="mdi-layers-triple-outline" size="24" /></span>
                        <span class="entry-area-content">
                            <span class="entry-area-name">{{ area.name }}</span>
                        </span>
                        <v-icon v-if="activeAreaId === area.id" class="entry-area-check" icon="mdi-check-circle" color="primary" size="21" />
                    </button>
                    <div class="entry-area-actions">
                        <v-btn
                            class="entry-area-action-button entry-area-action-button--edit"
                            height="42"
                            rounded="0"
                            variant="text"
                            :disabled="isEditing"
                            @click="openEditAreaDialog(area)">
                            <v-icon icon="mdi-pencil-outline" size="18" />
                            <span>Bearbeiten</span>
                        </v-btn>
                        <v-btn
                            class="entry-area-action-button entry-area-action-button--delete"
                            height="42"
                            rounded="0"
                            variant="text"
                            color="error"
                            :disabled="isEditing || entryCountForArea(area.id) > 0"
                            :title="entryCountForArea(area.id) ? 'Zuerst alle Einträge entfernen' : 'Bereich löschen'"
                            @click="openDeleteAreaDialog(area)">
                            <v-icon icon="mdi-delete-outline" size="18" />
                            <span>Löschen</span>
                        </v-btn>
                    </div>
                </div>
            </template>
        </div>

        <div v-else-if="!isLoading" class="entry-empty-area mt-4">
            <div class="entry-empty-icon"><v-icon icon="mdi-shape-square-plus" size="32" /></div>
            <div>
                <div class="text-subtitle-1 font-weight-bold">Ersten Bereich anlegen</div>
                <div class="text-body-2 text-medium-emphasis">Zum Beispiel Unterstufe, Oberstufe oder Wahlpflichtfach.</div>
            </div>
            <div class="entry-empty-actions">
                <v-btn
                    v-if="previousYearImportOffer"
                    color="primary"
                    variant="tonal"
                    rounded="lg"
                    prepend-icon="mdi-calendar-import"
                    @click="openPreviousYearImport">
                    Aus Vorjahr übernehmen
                </v-btn>
                <v-btn color="primary" variant="flat" rounded="lg" @click="openCreateAreaDialog">Bereich erstellen</v-btn>
            </div>
        </div>

        <v-dialog v-model="previousYearImportDialogOpen" persistent max-width="560">
            <v-card rounded="xl">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon"><v-icon icon="mdi-calendar-import" /></span>
                        <div>
                            <div class="dialog-eyebrow">Bereiche</div>
                            <div>Aus dem Vorjahr übernehmen?</div>
                        </div>
                    </div>
                </v-card-title>
                <v-card-text class="dialog-body">
                    <v-alert type="info" variant="tonal">
                        Für das aktuelle Schuljahr sind noch keine Bereiche vorhanden. Möchten Sie
                        <strong>
                            {{ previousYearImportOffer?.area_count }}
                            {{ previousYearImportOffer?.area_count === 1 ? 'Bereich' : 'Bereiche' }}
                        </strong>
                        mit
                        <strong>
                            {{ previousYearImportOffer?.entry_count }}
                            {{ previousYearImportOffer?.entry_count === 1 ? 'Eintrag' : 'Einträgen' }}
                        </strong>
                        aus dem Schuljahr
                        <strong>{{ previousYearImportOffer?.schoolyear?.label }}</strong>
                        übernehmen?
                    </v-alert>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" :disabled="isImportingPreviousYear" @click="declinePreviousYearImport">Nein</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        prepend-icon="mdi-calendar-import"
                        :loading="isImportingPreviousYear"
                        @click="importPreviousYearAreas">
                        Übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <template v-if="areas.length">
            <div v-show="!assignGradingPartId" class="entry-section-header mt-7">
                <div>
                    <div class="text-h6 font-weight-bold">{{ activeAreaName }}</div>
                </div>
                <div v-if="activeCategory !== 'Berechnung'" class="entry-section-actions">
                    <v-btn
                        v-if="entryCountForArea(activeAreaId) === 0"
                        color="primary"
                        variant="tonal"
                        rounded="lg"
                        prepend-icon="mdi-content-copy"
                        :disabled="isEditing || isLoading || !availableSourceAreas.length"
                        @click="openEntryCopyDialog">
                        Übernehmen
                    </v-btn>
                </div>
            </div>

            <v-tabs v-if="!assignGradingPartId" v-model="activeCategory" class="entry-tabs mt-3" color="primary" show-arrows>
                <v-tab v-for="category in categoryOptions" :key="category" :value="category" :disabled="isEditing">{{ category }}</v-tab>
            </v-tabs>

            <template v-if="activeCategory === 'Berechnung'">
                <section v-if="!assignGradingPartId" class="calculation-area-card mt-4" aria-label="Semester-Einstellungen">
                    <div class="text-subtitle-1 font-weight-bold mb-4">Semester</div>
                    <v-btn-toggle
                        :model-value="semesterForm.semester_count"
                        aria-label="Anzahl der Semester"
                        class="semester-count-toggle mb-4"
                        color="primary"
                        variant="outlined"
                        mandatory
                        :disabled="isSavingSemesters || (isEditing && activeEdit !== 'semesters')"
                        @update:model-value="updateSemesterField('semester_count', $event)">
                        <v-btn :value="1">1 Semester</v-btn>
                        <v-btn :value="2">2 Semester</v-btn>
                    </v-btn-toggle>
                    <div v-if="semesterForm.semester_count === 2" class="semester-weight-inputs">
                        <v-text-field
                            v-for="semester in [1, 2]"
                            :key="semester"
                            class="semester-percentage"
                            :style="{ '--semester-value-width': `${Math.max(1, String(semesterForm[`semester_${semester}_weight`] ?? '').length)}ch` }"
                            :model-value="semesterForm[`semester_${semester}_weight`]"
                            :label="`${semester}. Semester`"
                            type="number"
                            inputmode="numeric"
                            min="0"
                            max="100"
                            step="1"
                            suffix="%"
                            variant="outlined"
                            :readonly="semester === 1"
                            :hint="semester === 1 ? 'Automatisch: 100 % minus 2. Semester' : 'Anteil eingeben'"
                            persistent-hint
                            :disabled="isSavingSemesters || (isEditing && activeEdit !== 'semesters')"
                            @update:model-value="updateSemesterField(`semester_${semester}_weight`, $event)" />
                    </div>
                    <div v-if="semesterValidationMessage" class="text-error text-body-2 mb-3" role="status">
                        {{ semesterValidationMessage }}
                    </div>
                    <v-btn
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        :loading="isSavingSemesters"
                        :disabled="Boolean(semesterValidationMessage) || !semesterDrafts[activeAreaId] || (isEditing && activeEdit !== 'semesters')"
                        @click="saveSemesterSettings">
                        Speichern
                    </v-btn>
                    <v-btn v-if="activeEdit === 'semesters'" class="ml-2" variant="text" :disabled="isSavingSemesters" @click="delete semesterDrafts[activeAreaId]">Abbrechen</v-btn>
                </section>
                <header v-if="!assignGradingPartId" class="calculation-semester-grade-header mt-4">
                    <div class="text-h6 font-weight-bold">Semesternote</div>
                </header>

                <section v-if="assignGradingPartId" class="calculation-area-card mt-3">
                    <header class="calculation-area-header">
                        <span class="calculation-area-icon"><v-icon icon="mdi-format-list-checks" size="20" /></span>
                        <div class="calculation-area-heading">
                            <div class="calculation-area-name">Zuordnung: {{ gradingPartPendingAssignment?.name }}</div>
                            <div class="text-caption text-medium-emphasis">
                                {{ calculationEntries.length }}
                                {{ calculationEntries.length === 1 ? 'Benotungseintrag verfügbar' : 'Benotungseinträge verfügbar' }}
                            </div>
                        </div>
                    </header>

                    <div class="calculation-assignment-hint">
                        <v-icon icon="mdi-cursor-default-click-outline" size="20" />
                        <span>
                            Wählen Sie alle Einträge aus, die diesem Benotungsteil zugeordnet sein sollen.
                            Bestehende Zuordnungen sind vorausgewählt.
                            <template v-if="gradingPartPendingAssignment?.allowed_entry_types === 'points'">Hier sind nur Eintragstypen mit der Eigenschaft „Punkte“ zulässig.</template>
                        </span>
                    </div>

                    <div v-if="calculationEntries.length" class="calculation-entry-selection-grid">
                        <button
                            v-for="entry in calculationEntries"
                            :key="entry.id"
                            type="button"
                            class="calculation-entry-selection-card"
                            :class="{ 'calculation-entry-selection-card--selected': selectedGradingEntryIds.includes(entry.id) }"
                            :aria-pressed="selectedGradingEntryIds.includes(entry.id)"
                            :disabled="isAssigningGradingEntry || !canAssignGradingEntry(entry)"
                            @click="toggleGradingEntrySelection(entry)">
                            <span class="calculation-entry-selection-card-header">
                                <v-chip class="calculation-entry-code" color="primary" variant="tonal" size="x-small">{{ entry.short_name }}</v-chip>
                                <v-icon
                                    :icon="selectedGradingEntryIds.includes(entry.id) ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'"
                                    :color="selectedGradingEntryIds.includes(entry.id) ? 'primary' : undefined"
                                    size="22" />
                            </span>
                            <div class="calculation-entry-details">
                                <span class="calculation-entry-name" :title="entry.name">{{ entry.name }}</span>
                                <span v-if="!canAssignGradingEntry(entry)" class="text-caption text-medium-emphasis">Nur Punktetypen zulässig</span>
                                <div class="calculation-entry-values">
                                    <span v-if="standardCalculationLabel(entry)" class="calculation-evaluation calculation-evaluation--neutral">{{ standardCalculationLabel(entry) }}</span>
                                    <v-chip
                                        v-if="entry.has_properties && entry.properties_mode !== 'fixed' && !standardCalculationLabel(entry) && !calculationProperties(entry).length"
                                        class="calculation-entry-value"
                                        color="info"
                                        variant="tonal"
                                        size="x-small">
                                        {{ propertyModeLabel(entry.properties_mode) }}
                                    </v-chip>
                                    <span
                                        v-for="property in calculationProperties(entry)"
                                        v-else-if="entry.has_properties"
                                        :key="property"
                                        class="calculation-entry-value calculation-property">
                                        <span class="calculation-property-name">{{ property }}</span>
                                        <span v-if="propertyEvaluationLabel(entry, property)" class="calculation-evaluation" :class="propertyEvaluationClass(entry, property)">{{ propertyEvaluationLabel(entry, property) }}</span>
                                    </span>
                                    <span v-else class="calculation-entry-no-values">Keine zusätzlichen Werte</span>
                                </div>
                                <span
                                    v-if="entry.teaching_entry_grading_part_id && entry.teaching_entry_grading_part_id !== assignGradingPartId"
                                    class="calculation-entry-current-assignment">
                                    Aktuell: {{ gradingPartName(entry.teaching_entry_grading_part_id) }}
                                </span>
                            </div>
                        </button>
                    </div>
                    <div v-else class="text-body-2 text-medium-emphasis">Keine Benotungseinträge verfügbar.</div>

                    <div class="calculation-assignment-actions">
                        <v-btn variant="text" :disabled="isAssigningGradingEntry" @click="cancelGradingEntryAssignment">Abbrechen</v-btn>
                        <v-btn
                            color="primary"
                            variant="flat"
                            prepend-icon="mdi-content-save-outline"
                            :disabled="!hasGradingEntryAssignmentChanges"
                            :loading="isAssigningGradingEntry"
                            @click="saveGradingEntryAssignments">
                            Zuordnung speichern
                        </v-btn>
                    </div>
                </section>

                <div v-if="calculationAreas.length && !assignGradingPartId" class="calculation-area-list mt-3">
                    <section
                        v-for="area in calculationAreas"
                        :key="area.id"
                        class="calculation-area-card calculation-part-card"
                        :class="{ 'calculation-part-card--has-entries': area.entries.length }">
                        <span v-if="gradingPartCalculationIssue(area)" class="calculation-incomplete-marker" role="img"
                            :aria-label="`Berechnung unvollständig: ${gradingPartCalculationIssue(area)}`"
                            :title="gradingPartCalculationIssue(area)">!</span>
                        <header class="calculation-area-header">
                            <div class="grading-weight-badge" :aria-label="gradingPartWeightLabel(area)">
                                <v-icon icon="mdi-weight" size="24" aria-hidden="true" />
                                <strong>{{ gradingPartWeightValue(area) }}</strong>
                                <span>{{ area.fixed_percentage !== null && area.fixed_percentage !== undefined ? 'Fester Anteil' : 'Gewichtung' }}</span>
                            </div>
                            <div class="calculation-area-heading">
                                <div class="calculation-area-name">{{ area.name }}</div>
                                <div class="text-caption text-medium-emphasis">{{ area.is_required ? 'Verpflichtend' : 'Optional' }}</div>
                                <div v-if="area.allowed_entry_types === 'points'" class="text-caption text-medium-emphasis">Nur Punktetypen</div>
                            </div>
                            <div class="calculation-part-actions">
                                <v-btn
                                    :color="assignGradingPartId === area.gradingPartId ? 'secondary' : 'primary'"
                                    variant="tonal"
                                    size="small"
                                    :prepend-icon="assignGradingPartId === area.gradingPartId ? 'mdi-close' : 'mdi-link-plus'"
                                    :disabled="isAssigningGradingEntry || (isEditing && assignGradingPartId !== area.gradingPartId) || (!calculationEntries.length && assignGradingPartId !== area.gradingPartId)"
                                    @click="toggleGradingEntryAssignment(area)">
                                    {{ assignGradingPartId === area.gradingPartId ? 'Auswahl abbrechen' : 'Zuordnung' }}
                                </v-btn>
                                <v-btn
                                    icon="mdi-pencil-outline"
                                    color="primary"
                                    variant="tonal"
                                    size="x-small"
                                    title="Benotungsteil bearbeiten"
                                    :disabled="isEditing"
                                    @click="openEditGradingPartDialog(area)" />
                                <v-btn
                                    icon="mdi-delete-outline"
                                    color="error"
                                    variant="tonal"
                                    size="x-small"
                                    title="Benotungsteil löschen"
                                    :disabled="isEditing"
                                    @click="openDeleteGradingPartDialog(area)" />
                            </div>
                        </header>

                        <ul v-if="area.entries.length" class="calculation-entry-list">
                            <li v-for="entry in area.entries" :key="entry.id">
                                <button
                                    type="button"
                                    class="calculation-entry-item calculation-entry-open"
                                    :disabled="isEditing"
                                    :aria-label="`Berechnung für ${entry.name} öffnen`"
                                    @click="openCalculationDialog(entry)">
                                <span v-if="entryCalculationIssue(entry)" class="calculation-incomplete-marker" role="img"
                                    :aria-label="`Berechnung unvollständig: ${entryCalculationIssue(entry)}`"
                                    :title="entryCalculationIssue(entry)">!</span>
                                <span v-if="entryWeightLabel(entry, area.entries.length)" class="calculation-entry-weight"
                                    :aria-label="`Gewichtung innerhalb des Benotungsteils: ${entryWeightLabel(entry, area.entries.length)}`"
                                    :title="`Gewichtung innerhalb des Benotungsteils: ${entryWeightLabel(entry, area.entries.length)}`">
                                    <v-icon icon="mdi-weight" size="16" aria-hidden="true" />
                                    <strong>{{ entryWeightLabel(entry, area.entries.length) }}</strong>
                                </span>
                                <span v-else-if="entryAssessmentSymbol(entry)" class="calculation-entry-assessment-symbol" role="img"
                                    :aria-label="entryAssessmentSymbol(entry).label" :title="entryAssessmentSymbol(entry).label">
                                    <v-icon :icon="entryAssessmentSymbol(entry).icon" size="20" aria-hidden="true" />
                                </span>
                                <v-chip v-else class="calculation-entry-code" color="primary" variant="tonal" size="x-small">{{ entry.short_name }}</v-chip>
                                <div class="calculation-entry-details">
                                    <span class="calculation-entry-name" :title="entry.name">{{ entry.name }}</span>
                                    <div class="calculation-entry-values">
                                        <span v-if="standardCalculationLabel(entry)" class="calculation-evaluation calculation-evaluation--neutral">{{ standardCalculationLabel(entry) }}</span>
                                        <v-chip
                                            v-if="entry.has_properties && entry.properties_mode !== 'fixed' && !standardCalculationLabel(entry) && !calculationProperties(entry).length"
                                            class="calculation-entry-value"
                                            color="info"
                                            variant="tonal"
                                            size="x-small">
                                            {{ propertyModeLabel(entry.properties_mode) }}
                                        </v-chip>
                                        <span
                                            v-for="property in calculationProperties(entry)"
                                            v-else-if="entry.has_properties"
                                            :key="property"
                                            class="calculation-entry-value calculation-property">
                                            <span class="calculation-property-name">{{ property }}</span>
                                            <span v-if="propertyEvaluationLabel(entry, property)" class="calculation-evaluation" :class="propertyEvaluationClass(entry, property)">{{ propertyEvaluationLabel(entry, property) }}</span>
                                        </span>
                                        <span v-else class="calculation-entry-no-values">Keine zusätzlichen Werte</span>
                                    </div>
                                </div>
                                </button>
                            </li>
                        </ul>
                    </section>
                </div>

                <div v-if="!assignGradingPartId" class="entry-list-actions mt-4">
                    <v-btn
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        prepend-icon="mdi-shape-square-plus"
                        :disabled="isEditing || isLoading"
                        @click="openCreateGradingPartDialog">
                        Benotungsteil hinzufügen
                    </v-btn>
                </div>
            </template>

            <template v-if="activeCategory !== 'Berechnung'">
                <v-list class="bg-transparent pa-0 mt-2">
                    <v-list-item v-for="entry in filteredEntries" :key="entry.id" class="entry-row mb-2">
                        <div class="entry-row-content">
                            <div class="entry-main-row">
                                <span class="entry-short">{{ entry.short_name }}</span>
                                <div class="entry-title">
                                    <span class="entry-name" :title="entry.name">{{ entry.name }}</span>
                                    <span
                                        v-if="entry.description"
                                        class="entry-description"
                                        :title="formatEntryDescription(entry.description)">
                                        {{ formatEntryDescription(entry.description) }}
                                    </span>
                                </div>
                                <div class="entry-actions">
                                    <v-btn icon="mdi-pencil-outline" variant="tonal" color="primary" size="small" title="Eintrag bearbeiten" :disabled="isEditing" @click="openEditDialog(entry)" />
                                    <v-btn icon="mdi-delete-outline" variant="tonal" color="error" size="small" title="Eintrag löschen" :disabled="isEditing" @click="openDeleteDialog(entry)" />
                                </div>
                            </div>
                            <div v-if="entry.category === 'Benotung' && entry.has_properties" class="entry-properties">
                                <div class="entry-property-type"><span>Eigenschaftstyp</span><strong>{{ entryPropertyTypeLabel(entry) }}</strong></div>
                                <div v-if="['fixed', 'free'].includes(entry.properties_mode) && entry.fixed_properties?.length" class="entry-property-values">
                                    <span class="entry-property-caption">Werte</span>
                                    <v-chip
                                        v-for="property in entry.fixed_properties"
                                        :key="property"
                                        class="entry-property-chip"
                                        color="primary"
                                        variant="tonal"
                                        size="x-small">
                                        {{ property }}
                                    </v-chip>
                                </div>
                                <div v-if="enabledSpecialPropertyOptions(entry).length" class="entry-property-values">
                                    <span class="entry-property-caption">Zusatzoptionen</span>
                                    <v-chip v-for="option in enabledSpecialPropertyOptions(entry)" :key="option.value" variant="outlined" size="x-small">{{ option.label }}</v-chip>
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                </v-list>

                <v-alert v-if="!isLoading && !filteredEntries.length" type="info" variant="tonal" class="mt-3">
                    In diesem Bereich gibt es noch keine Einträge der Kategorie {{ activeCategory }}.
                </v-alert>

                <div class="entry-list-actions mt-4">
                    <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" :disabled="isEditing || isLoading" @click="openCreateDialog">Eintrag</v-btn>
                </div>
            </template>
        </template>


        <v-dialog v-model="calculationDialogOpen" persistent scrollable max-width="620" aria-labelledby="calculation-dialog-title">
            <v-card rounded="xl">
                <v-card-title id="calculation-dialog-title" class="text-wrap">
                    {{ calculationDialogEntry?.name }} – Berechnung
                </v-card-title>
                <v-card-text>
                    <div class="d-flex align-center ga-3 mb-3">
                        <v-chip color="primary" variant="tonal">{{ calculationDialogEntry?.short_name }}</v-chip>
                        <span class="text-body-2">{{ calculationDialogPropertyLabel }}</span>
                    </div>
                    <section v-if="calculationDialogUsesStandardGrades" class="plus-grade-editor mb-3" aria-label="Standardnoten und Zusatzoptionen">
                        <header class="plus-grade-editor-heading"><div><h3>Standardnoten</h3><p>Unter Benotung festgelegte Noten und Zusatzoptionen.</p></div></header>
                        <div class="plus-grade-rows">
                            <div v-for="band in standardPercentageGrades" :key="band.grade" class="plus-grade-name">
                                <span class="plus-grade-badge">{{ band.grade }}</span><strong>{{ band.label }}</strong>
                            </div>
                        </div>
                        <h4 class="text-subtitle-2 mt-5 mb-2">Zusatzoptionen</h4>
                        <div v-for="property in calculationDialogAdditionalProperties" :key="property.value" class="calculation-extra-property">
                            <span>{{ property.label }}</span>
                            <span>{{ property.evaluation || 'Kein Wert zugeordnet' }}</span>
                        </div>
                        <p v-if="!calculationDialogAdditionalProperties.length" class="text-body-2">Keine weiteren Optionen aktiviert.</p>
                    </section>
                    <section v-if="calculationDialogHasPartAssessment" class="plus-grade-editor mb-4" aria-label="Beurteilung innerhalb des Benotungsteils">
                        <header class="plus-grade-editor-heading"><div><h3>Innerhalb des Benotungsteils</h3></div></header>
                        <p v-if="calculationDialogIndividualPointWeighting" class="text-body-2 mb-3">{{ calculationDialogIndividualPointWeighting === 'points' ? 'Nach Punkten' : 'Nach Gewichtung' }} – im Benotungsteil festgelegt.</p>
                        <v-btn-toggle v-if="!calculationDialogIndividualPointWeighting" v-model="calculationDialogPartAssessmentMode" class="maximum-plus-grading-options"
                            aria-label="Beurteilung innerhalb des Benotungsteils" color="primary" variant="outlined" mandatory :disabled="isSavingCalculationDialog">
                            <v-btn value="weighted">Gewichtung</v-btn>
                            <v-btn value="other">Andere Beurteilung</v-btn>
                        </v-btn-toggle>
                        <v-btn-toggle v-if="!calculationDialogIndividualPointWeighting && calculationDialogPartAssessmentMode === 'other' && calculationDialogEntry?.properties_mode === 'points'"
                            v-model="calculationDialogPartOtherAssessmentMode" class="maximum-plus-grading-options mt-4"
                            aria-label="Andere Beurteilung" color="primary" variant="outlined" mandatory :disabled="isSavingCalculationDialog">
                            <v-btn value="points">Nach Punkten</v-btn>
                            <v-btn value="weighted">Gewichtung</v-btn>
                        </v-btn-toggle>
                        <v-text-field v-if="calculationDialogUsesPartWeight"
                            :model-value="gradingNumberInput(calculationDialogPartWeight)" @update:model-value="calculationDialogPartWeight = $event"
                            label="Gewichtung innerhalb des Benotungsteils" prepend-inner-icon="mdi-weight" class="mt-4"
                            type="text" inputmode="numeric" variant="outlined" hide-details="auto" :disabled="isSavingCalculationDialog"
                            :error-messages="calculationDialogErrors.grading_part_weight || calculationDialogPartWeightError" />
                        <v-btn-toggle v-if="calculationDialogPartAssessmentMode === 'other' && calculationDialogEntry?.properties_mode === 'plus_minus'"
                            v-model="calculationDialogPartOtherAssessmentMode" class="maximum-plus-grading-options balance-assessment-options mt-4"
                            aria-label="Andere Beurteilung mit Plus und Minus" color="primary" variant="outlined" mandatory :disabled="isSavingCalculationDialog">
                            <v-btn value="balance_rounding">
                                <span class="balance-assessment-label">
                                    <span>Mehr Plus als Minus: Gesamtbeurteilung aufrunden</span>
                                    <span>Mehr Minus als Plus: Gesamtbeurteilung abrunden</span>
                                </span>
                            </v-btn>
                            <v-btn value="balance_adjustment">Notenanpassung pro überschüssigem Plus oder Minus</v-btn>
                        </v-btn-toggle>
                        <div v-if="calculationDialogUsesBalanceAdjustment" class="balance-adjustment-values mt-4">
                            <v-text-field
                                :model-value="gradingNumberInput(calculationDialogPlusAdjustment)" @update:model-value="calculationDialogPlusAdjustment = $event"
                                label="Abzug pro Plus" hint="Je überschüssigem Plus wird die Note um diesen Wert kleiner und besser." persistent-hint
                                type="text" inputmode="decimal" variant="outlined" :disabled="isSavingCalculationDialog"
                                :error-messages="calculationDialogErrors.grading_part_plus_adjustment" />
                            <v-text-field
                                :model-value="gradingNumberInput(calculationDialogMinusAdjustment)" @update:model-value="calculationDialogMinusAdjustment = $event"
                                label="Zuschlag pro Minus" hint="Je überschüssigem Minus wird die Note um diesen Wert größer und schlechter." persistent-hint
                                type="text" inputmode="decimal" variant="outlined" :disabled="isSavingCalculationDialog"
                                :error-messages="calculationDialogErrors.grading_part_minus_adjustment" />
                            <p v-if="calculationDialogAdjustmentError" class="text-error text-body-2 mt-2" role="status">{{ calculationDialogAdjustmentError }}</p>
                        </div>
                        <p v-for="error in calculationDialogErrors.grading_part_other_assessment_mode" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                        <p v-for="error in calculationDialogErrors.grading_part_assessment_mode" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                    </section>
                    <v-checkbox
                        v-if="calculationDialogEntry?.properties_mode === 'plus'"
                        v-model="calculationDialogAllowsMaximumPlus"
                        label="Lehrperson gibt bei Erstellung maximale Plusanzahl ein"
                        color="primary"
                        hide-details="auto"
                        :disabled="isSavingCalculationDialog"
                        :error-messages="calculationDialogErrors.allows_maximum_plus" />
                    <div v-if="calculationDialogEntry?.properties_mode === 'plus'" class="mt-3">
                        <v-btn-toggle
                            v-model="calculationDialogSumPlusEvaluations"
                            class="maximum-plus-grading-options"
                            aria-label="Auswertung der Einzelbewertungen"
                            color="primary"
                            variant="outlined"
                            mandatory
                            :disabled="isSavingCalculationDialog">
                            <v-btn :value="true">Einzelbewertungen zusammenzählen</v-btn>
                            <v-btn :value="false">Jede Einzelbewertung extra werten</v-btn>
                        </v-btn-toggle>
                        <p v-for="error in calculationDialogErrors.sum_plus_evaluations" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                    </div>
                    <div v-if="calculationDialogEntry?.properties_mode === 'plus' && calculationDialogAllowsMaximumPlus" class="mt-3">
                        <v-btn-toggle
                            v-model="calculationDialogMaximumPlusGradingMode"
                            class="maximum-plus-grading-options"
                            aria-label="Benotung"
                            color="primary"
                            variant="outlined"
                            mandatory
                            :disabled="isSavingCalculationDialog">
                            <v-btn value="standard_percentage">Benotung durch Standardprozent</v-btn>
                            <v-btn value="other">Andere Benotung</v-btn>
                        </v-btn-toggle>
                        <p v-for="error in calculationDialogErrors.maximum_plus_grading_mode" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                        <table v-if="calculationDialogMaximumPlusGradingMode === 'standard_percentage'" class="standard-percentage-grades mt-4">
                            <caption>Benotung durch Standardprozent</caption>
                            <thead><tr><th scope="col">Erreichte Prozent</th><th scope="col">Note</th></tr></thead>
                            <tbody>
                                <tr v-for="band in standardPercentageGrades" :key="band.grade">
                                    <td>{{ standardPercentageRange(band) }}</td>
                                    <td><strong>{{ band.grade }}</strong> – {{ band.label }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <section v-if="calculationDialogUsesOtherGrading" class="plus-grade-editor mt-4" aria-labelledby="other-plus-grading-title">
                        <header class="plus-grade-editor-heading">
                            <span class="plus-grade-editor-icon" aria-hidden="true">+</span>
                            <div>
                                <h3 id="other-plus-grading-title">Andere Benotung</h3>
                                <p>Ab wie vielen Plus wird die Note erreicht?</p>
                            </div>
                        </header>
                        <div class="plus-grade-rows">
                            <div v-for="band in standardPercentageGrades.slice(0, 4)" :key="band.grade" class="plus-grade-row">
                                <div class="plus-grade-name">
                                    <span class="plus-grade-badge">{{ band.grade }}</span>
                                    <strong>{{ band.label }}</strong>
                                </div>
                                <v-text-field
                                    v-model="calculationDialogGradeThresholds[band.grade]"
                                    :aria-label="`${band.label}: Mindestanzahl Plus`"
                                    prefix="ab"
                                    type="text"
                                    inputmode="numeric"
                                    class="grade-threshold-input"
                                    suffix="Plus"
                                    variant="outlined"
                                    density="compact"
                                    hide-details="auto"
                                    :disabled="isSavingCalculationDialog"
                                    :error-messages="calculationDialogErrors[`maximum_plus_grade_thresholds.${band.grade}`]" />
                            </div>
                            <div class="plus-grade-row plus-grade-row--automatic">
                                <div class="plus-grade-name"><span class="plus-grade-badge">5</span><strong>Nicht genügend</strong></div>
                                <span class="plus-grade-fallback">{{ calculationDialogFailingGradeLabel }}</span>
                            </div>
                        </div>
                        <p class="plus-grade-order-hint">Sehr gut &gt; Gut &gt; Befriedigend &gt; Genügend</p>
                        <p v-if="calculationDialogThresholdError" class="text-error text-body-2 mt-2" role="status">{{ calculationDialogThresholdError }}</p>
                        <p v-for="error in calculationDialogErrors.maximum_plus_grade_thresholds" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                    </section>
                    <section v-if="calculationDialogOverallPart" class="plus-grade-editor mt-4" aria-labelledby="entry-overall-grading-title">
                        <header class="plus-grade-editor-heading">
                            <span class="plus-grade-editor-icon" aria-hidden="true">Σ</span>
                            <div><h3 id="entry-overall-grading-title" class="text-h5 font-weight-bold">Gesamtbeurteilung</h3></div>
                        </header>
                        <p class="text-body-1">Die Notengrenzen werden gemeinsam im Benotungsteil „{{ calculationDialogOverallPart.name }}“ festgelegt.</p>
                    </section>
                    <section v-else-if="calculationDialogUsesPoints" class="plus-grade-editor mt-4" aria-labelledby="points-grading-title">
                        <header class="plus-grade-editor-heading">
                            <span class="plus-grade-editor-icon" aria-hidden="true">Σ</span>
                            <div><h3 id="points-grading-title">Punkte in Noten umrechnen</h3><p>Ab welcher Punktzahl wird die jeweilige Note erreicht?</p></div>
                        </header>
                        <v-btn
                            color="primary"
                            variant="tonal"
                            class="standard-points-button mb-4"
                            prepend-icon="mdi-calculator"
                            :disabled="isSavingCalculationDialog"
                            @click="applyStandardPointThresholds">Berechnung durch Standardprozent</v-btn>
                        <p class="text-body-2 mb-4">Die Standardgrenzen werden aus der maximalen Punktzahl berechnet und auf ganze Punkte gerundet. Anschließend können Sie alle Werte ändern.</p>
                        <div class="plus-grade-rows">
                            <div v-for="band in standardPercentageGrades.slice(0, 4)" :key="band.grade" class="plus-grade-row">
                                <div class="plus-grade-name"><span class="plus-grade-badge">{{ band.grade }}</span><strong>{{ band.label }}</strong></div>
                                <v-text-field
                                    :model-value="gradingNumberInput(calculationDialogPointThresholds[band.grade])"
                                    @update:model-value="calculationDialogPointThresholds[band.grade] = $event"
                                    :aria-label="`${band.label}: Mindestpunkte`"
                                    prefix="ab"
                                    type="text"
                                    inputmode="decimal"
                                    class="grade-threshold-input"
                                    suffix="Punkte"
                                    variant="outlined"
                                    density="compact"
                                    hide-details="auto"
                                    :disabled="isSavingCalculationDialog"
                                    :error-messages="calculationDialogErrors[`points_grade_thresholds.${band.grade}`]" />
                            </div>
                            <div class="plus-grade-row plus-grade-row--automatic">
                                <div class="plus-grade-name"><span class="plus-grade-badge">5</span><strong>Nicht genügend</strong></div>
                                <span class="plus-grade-fallback">{{ calculationDialogPointFailingGradeLabel }}</span>
                            </div>
                        </div>
                        <p v-if="calculationDialogPointThresholdError" class="text-error text-body-2 mt-2" role="status">{{ calculationDialogPointThresholdError }}</p>
                        <p v-for="error in calculationDialogErrors.points_grade_thresholds" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                    </section>
                    <section v-if="calculationDialogUsesFreeGrading" class="mt-4">
                        <v-btn-toggle
                            v-model="calculationDialogFreeGradingMode"
                            class="maximum-plus-grading-options"
                            aria-label="Berechnung freier Eigenschaften"
                            color="primary"
                            variant="outlined"
                            mandatory
                            :disabled="isSavingCalculationDialog">
                            <v-btn value="deficit_points">Abstand zum Maximum</v-btn>
                            <v-btn value="points">Punktesumme</v-btn>
                        </v-btn-toggle>
                        <div class="plus-grade-editor mt-4">
                            <header class="plus-grade-editor-heading">
                                <span class="plus-grade-editor-icon" aria-hidden="true">{{ calculationDialogFreeGradingMode === 'deficit_points' ? 'Δ' : 'Σ' }}</span>
                                <div>
                                    <h3>{{ calculationDialogFreeGradingMode === 'deficit_points' ? 'Vom möglichen Maximum ausgehend' : 'Noten nach Punktesumme' }}</h3>
                                    <p>{{ calculationDialogFreeGradingMode === 'deficit_points' ? 'Wie viele Punkte dürfen zur maximal möglichen Summe fehlen?' : 'Die unter Benotung zugeordneten Werte werden addiert.' }}</p>
                                </div>
                            </header>
                            <div class="plus-grade-rows">
                                <div v-for="band in standardPercentageGrades.slice(0, 4)" :key="band.grade" class="plus-grade-row">
                                    <div class="plus-grade-name"><span class="plus-grade-badge">{{ band.grade }}</span><strong>{{ band.label }}</strong></div>
                                    <v-text-field
                                        :model-value="gradingNumberInput(calculationDialogFreeThresholds[band.grade])"
                                        @update:model-value="calculationDialogFreeThresholds[band.grade] = $event"
                                        :aria-label="`${band.label}: ${calculationDialogFreeGradingMode === 'deficit_points' ? 'höchstens fehlende Punkte' : 'Mindestpunkte'}`"
                                        :prefix="calculationDialogFreeGradingMode === 'deficit_points' ? 'bis' : 'ab'"
                                        type="text"
                                        inputmode="decimal"
                                        class="grade-threshold-input"
                                        suffix="Punkte"
                                        variant="outlined"
                                        density="compact"
                                        hide-details="auto"
                                        :disabled="isSavingCalculationDialog"
                                        :error-messages="calculationDialogErrors[`${calculationDialogFreeThresholdKey}.${band.grade}`]" />
                                </div>
                                <div class="plus-grade-row plus-grade-row--automatic">
                                    <div class="plus-grade-name"><span class="plus-grade-badge">5</span><strong>Nicht genügend</strong></div>
                                    <span class="plus-grade-fallback">{{ calculationDialogFreeFailingGradeLabel }}</span>
                                </div>
                            </div>
                            <p class="plus-grade-order-hint">{{ calculationDialogFreeGradingMode === 'deficit_points' ? 'Fehlende Punkte: Sehr gut < Gut < Befriedigend < Genügend' : 'Mindestpunkte: Sehr gut > Gut > Befriedigend > Genügend' }}</p>
                            <p v-if="calculationDialogFreeThresholdError" class="text-error text-body-2 mt-2" role="status">{{ calculationDialogFreeThresholdError }}</p>
                            <p v-for="error in calculationDialogErrors[calculationDialogFreeThresholdKey]" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                            <p v-for="error in calculationDialogErrors.free_grading_mode" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                        </div>
                    </section>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" :disabled="isSavingCalculationDialog" @click="closeCalculationDialog">Schließen</v-btn>
                    <v-btn
                        v-if="calculationDialogHasPartAssessment || calculationDialogEntry?.properties_mode === 'plus' || calculationDialogUsesFreeGrading || calculationDialogUsesPoints"
                        color="primary"
                        variant="flat"
                        :loading="isSavingCalculationDialog"
                        :disabled="Boolean(calculationDialogAdjustmentError || calculationDialogPartWeightError || calculationDialogThresholdError || calculationDialogFreeThresholdError || calculationDialogPointThresholdError)"
                        @click="saveCalculationDialog">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="entryCopyDialogOpen" persistent max-width="620">
            <v-card rounded="xl" class="entry-copy-dialog">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon"><v-icon icon="mdi-content-copy" /></span>
                        <div>
                            <div class="dialog-eyebrow">Bereiche</div>
                            <div>Einträge übernehmen</div>
                        </div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" :disabled="isCopyingEntries" @click="closeEntryCopyDialog" />
                </v-card-title>
                <v-card-text class="dialog-body">
                    <v-alert type="info" variant="tonal" class="mb-4">
                        Alle Einträge des Quellbereichs werden nach
                        <strong>{{ activeAreaName }}</strong>
                        kopiert. Der Quellbereich bleibt unverändert.
                    </v-alert>
                    <div class="text-subtitle-2 font-weight-bold mb-2">Quellbereich auswählen</div>
                    <div class="entry-copy-area-grid">
                        <button
                            v-for="area in availableSourceAreas"
                            :key="area.id"
                            type="button"
                            class="entry-copy-area-choice"
                            :class="{ 'entry-copy-area-choice-active': selectedSourceAreaId === area.id }"
                            :aria-pressed="selectedSourceAreaId === area.id"
                            @click="selectedSourceAreaId = area.id">
                            <span class="entry-copy-area-icon"><v-icon icon="mdi-layers-outline" /></span>
                            <span>
                                <strong>{{ area.name }}</strong>
                                <small>{{ entryCountForArea(area.id) }} {{ entryCountForArea(area.id) === 1 ? 'Eintrag' : 'Einträge' }}</small>
                            </span>
                            <v-icon v-if="selectedSourceAreaId === area.id" icon="mdi-check-circle" color="primary" />
                        </button>
                    </div>
                    <div v-if="entryCopyErrors.source_area_id" class="form-error mt-3">{{ entryCopyErrors.source_area_id[0] }}</div>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" :disabled="isCopyingEntries" @click="closeEntryCopyDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        prepend-icon="mdi-content-copy"
                        :loading="isCopyingEntries"
                        :disabled="!selectedSourceAreaId"
                        @click="copyEntriesFromArea">
                        Alle übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="areaDialogOpen" persistent max-width="520">
            <v-card tag="form" rounded="xl" class="area-dialog" @submit.prevent="saveArea">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon"><v-icon icon="mdi-layers-edit" /></span>
                        <div>
                            <div class="dialog-eyebrow">Organisation</div>
                            <div>{{ areaDialogTitle }}</div>
                        </div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" :disabled="isSavingArea" @click="closeAreaDialog" />
                </v-card-title>
                <v-card-text class="pt-6">
                    <v-text-field
                        v-model="areaForm.name"
                        label="Name des Bereichs"
                        placeholder="z. B. Unterstufe"
                        variant="outlined"
                        color="primary"
                        maxlength="100"
                        autofocus
                        :error-messages="areaFormErrors.name" />
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="closeAreaDialog">Abbrechen</v-btn>
                    <v-btn type="submit" color="primary" variant="flat" rounded="lg" :loading="isSavingArea" :disabled="!areaForm.name.trim()">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="areaDeleteDialogOpen" persistent max-width="480">
            <v-card rounded="xl">
                <v-card-title>Bereich löschen</v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal">
                        Soll
                        <strong>{{ areaPendingDeletion?.name }}</strong>
                        endgültig gelöscht werden?
                    </v-alert>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="closeAreaDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="isDeletingArea" @click="confirmAreaDelete">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="gradingPartDialogOpen" persistent scrollable max-width="520">
            <v-card tag="form" rounded="xl" @submit.prevent="saveGradingPart">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon">
                            <v-icon :icon="editingGradingPartId ? 'mdi-folder-edit-outline' : 'mdi-folder-plus-outline'" />
                        </span>
                        <div>
                            <div class="dialog-eyebrow">Berechnung</div>
                            <div>{{ editingGradingPartId ? 'Benotungsteil bearbeiten' : 'Benotungsteil hinzufügen' }}</div>
                        </div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" :disabled="isSavingGradingPart" @click="closeGradingPartDialog" />
                </v-card-title>
                <v-card-text class="pt-6">
                    <v-text-field
                        v-model="gradingPartForm.name"
                        label="Name des Benotungsteils"
                        placeholder="z. B. Mündlich"
                        variant="outlined"
                        color="primary"
                        maxlength="100"
                        autofocus
                        :error-messages="gradingPartFormErrors.name" />
                    <v-btn-toggle
                        v-model="gradingPartForm.weighting_mode"
                        class="grading-weight-mode-toggle"
                        aria-label="Art der Gewichtung"
                        color="primary"
                        variant="outlined"
                        mandatory
                        :disabled="isSavingGradingPart">
                        <v-btn value="relative" :aria-pressed="gradingPartForm.weighting_mode === 'relative'" :variant="gradingPartForm.weighting_mode === 'relative' ? 'flat' : 'outlined'">Gewichtung</v-btn>
                        <v-btn value="fixed" :aria-pressed="gradingPartForm.weighting_mode === 'fixed'" :variant="gradingPartForm.weighting_mode === 'fixed' ? 'flat' : 'outlined'">Fester Prozentanteil</v-btn>
                    </v-btn-toggle>
                    <p class="text-caption text-medium-emphasis text-center mt-1 mb-4">bezogen auf alle Benotungsteile</p>
                    <v-text-field
                        v-if="gradingPartForm.weighting_mode === 'relative'"
                        v-model="gradingPartForm.weight"
                        label="Gewichtung"
                        type="number"
                        inputmode="decimal"
                        min="0.001"
                        max="9999999.999"
                        step="0.001"
                        variant="outlined"
                        color="primary"
                        hint="Der verbleibende Anteil wird nach diesen Gewichten verteilt, z. B. 6 : 4."
                        persistent-hint
                        :disabled="isSavingGradingPart"
                        :error-messages="gradingPartFormErrors.weight || (gradingPartWeightValid ? [] : ['Bitte eine positive Zahl mit höchstens drei Nachkommastellen eingeben.'])" />
                    <v-text-field
                        v-else
                        v-model="gradingPartForm.fixed_percentage"
                        label="Fester Anteil"
                        type="number"
                        inputmode="decimal"
                        min="0.001"
                        max="100"
                        step="0.001"
                        suffix="%"
                        variant="outlined"
                        color="primary"
                        hint="Dieser Anteil bleibt fest, sobald eine Bewertung vorliegt."
                        persistent-hint
                        :disabled="isSavingGradingPart"
                        :error-messages="gradingPartFormErrors.fixed_percentage || gradingPartPercentageError" />
                    <v-btn-toggle
                        v-model="gradingPartForm.is_required"
                        class="grading-requirement-toggle mt-4"
                        aria-label="Teilnahme am Benotungsteil"
                        color="primary"
                        variant="outlined"
                        mandatory
                        :disabled="isSavingGradingPart">
                        <v-btn :value="false" :aria-pressed="!gradingPartForm.is_required" :variant="!gradingPartForm.is_required ? 'flat' : 'outlined'">Optional</v-btn>
                        <v-btn :value="true" :aria-pressed="gradingPartForm.is_required" :variant="gradingPartForm.is_required ? 'flat' : 'outlined'">Verpflichtend</v-btn>
                    </v-btn-toggle>
                    <div class="text-caption text-medium-emphasis mt-2">
                        {{ gradingPartForm.is_required ? 'Eine Bewertung ist erforderlich. Fehlend bedeutet offen, nicht automatisch negativ.' : 'Ohne Bewertung wird dieser Teil nicht berücksichtigt.' }}
                    </div>
                    <div v-if="gradingPartFormErrors.is_required" class="text-caption text-error mt-1" role="alert">{{ gradingPartFormErrors.is_required.join(' ') }}</div>
                    <div class="text-subtitle-2 mt-5 mb-2">Zulässige Eintragstypen</div>
                    <v-btn-toggle
                        v-model="gradingPartForm.allowed_entry_types"
                        class="maximum-plus-grading-options"
                        aria-label="Zulässige Eintragstypen"
                        color="primary"
                        variant="outlined"
                        mandatory
                        :disabled="isSavingGradingPart">
                        <v-btn value="all">Alle Eintragstypen</v-btn>
                        <v-btn value="points" :disabled="gradingPartHasNonPointEntries">Nur Punktetypen</v-btn>
                    </v-btn-toggle>
                    <p class="text-caption text-medium-emphasis mt-2">„Nur Punktetypen“ erlaubt ausschließlich Einträge mit der Eigenschaft „Punkte“.</p>
                    <p v-if="gradingPartHasNonPointEntries" class="text-caption text-medium-emphasis mt-2" role="status">Ein Wechsel zu „Nur Punktetypen“ ist nicht möglich, solange diesem Benotungsteil andere Eintragstypen zugeordnet sind.</p>
                    <div v-if="gradingPartFormErrors.allowed_entry_types" class="text-caption text-error mt-1" role="alert">{{ gradingPartFormErrors.allowed_entry_types.join(' ') }}</div>
                    <section v-if="gradingPartForm.allowed_entry_types === 'points'" class="mt-5" aria-label="Beurteilung der Punktetypen">
                        <div class="text-subtitle-2 mb-2">Art der Beurteilung</div>
                        <v-btn-toggle
                            v-model="gradingPartForm.points_assessment_mode"
                            class="maximum-plus-grading-options"
                            aria-label="Art der Beurteilung"
                            color="primary"
                            variant="outlined"
                            mandatory
                            :disabled="isSavingGradingPart">
                            <v-btn value="overall">Gesamtbeurteilung</v-btn>
                            <v-btn value="individual">Einzelbeurteilungen</v-btn>
                        </v-btn-toggle>
                        <div v-if="gradingPartFormErrors.points_assessment_mode" class="text-caption text-error mt-1" role="alert">{{ gradingPartFormErrors.points_assessment_mode.join(' ') }}</div>
                        <section v-if="gradingPartForm.points_assessment_mode === 'individual'" class="mt-5" aria-label="Gewichtung der Einzelbeurteilungen">
                            <div class="text-subtitle-2 mb-2">Gewichtung der Einzelbeurteilungen</div>
                            <v-btn-toggle v-model="gradingPartForm.individual_points_weighting_mode" class="maximum-plus-grading-options"
                                aria-label="Gewichtung der Einzelbeurteilungen" color="primary" variant="outlined" mandatory :disabled="isSavingGradingPart">
                                <v-btn value="points">Nach Punkten</v-btn>
                                <v-btn value="weighted">Nach Gewichtung</v-btn>
                            </v-btn-toggle>
                            <div v-if="gradingPartFormErrors.individual_points_weighting_mode" class="text-caption text-error mt-1" role="alert">{{ gradingPartFormErrors.individual_points_weighting_mode.join(' ') }}</div>
                        </section>
                        <section v-if="gradingPartForm.points_assessment_mode === 'overall'" class="plus-grade-editor mt-4" aria-labelledby="overall-points-grading-title">
                            <header class="plus-grade-editor-heading">
                                <span class="plus-grade-editor-icon" aria-hidden="true">Σ</span>
                                <div><h3 id="overall-points-grading-title">Gesamtbeurteilung</h3><p>Maximal {{ gradingPartOverallMaximumPoints.toLocaleString('de-AT') }} Punkte aus den zugeordneten Punktetypen.</p></div>
                            </header>
                            <template v-if="gradingPartOverallMaximumPoints > 0">
                                <v-btn color="primary" variant="tonal" class="standard-points-button mb-4" prepend-icon="mdi-calculator"
                                    :disabled="isSavingGradingPart" @click="applyStandardOverallPointThresholds">Berechnung durch Standardprozent</v-btn>
                                <p class="text-body-2 mb-4">Die Standardgrenzen werden aus der Gesamtpunktzahl berechnet und auf ganze Punkte gerundet. Anschließend können Sie alle Werte ändern.</p>
                                <div class="plus-grade-rows">
                                    <div v-for="band in standardPercentageGrades.slice(0, 4)" :key="band.grade" class="plus-grade-row">
                                        <div class="plus-grade-name"><span class="plus-grade-badge">{{ band.grade }}</span><strong>{{ band.label }}</strong></div>
                                        <v-text-field
                                            :model-value="gradingNumberInput(gradingPartOverallThresholds[band.grade])"
                                            @update:model-value="gradingPartOverallThresholds[band.grade] = $event"
                                            :aria-label="`${band.label}: Mindestpunkte gesamt`"
                                            prefix="ab" type="text" inputmode="decimal" class="grade-threshold-input" suffix="Punkte"
                                            variant="outlined" density="compact" hide-details="auto" :disabled="isSavingGradingPart"
                                            :error-messages="gradingPartFormErrors[`overall_points_grade_thresholds.${band.grade}`]" />
                                    </div>
                                    <div class="plus-grade-row plus-grade-row--automatic">
                                        <div class="plus-grade-name"><span class="plus-grade-badge">5</span><strong>Nicht genügend</strong></div>
                                        <span class="plus-grade-fallback">{{ gradingPartOverallFailingGradeLabel }}</span>
                                    </div>
                                </div>
                                <p class="plus-grade-order-hint">Sehr gut &gt; Gut &gt; Befriedigend &gt; Genügend</p>
                                <p v-if="gradingPartOverallThresholdError" class="text-error text-body-2 mt-2" role="status">{{ gradingPartOverallThresholdError }}</p>
                            </template>
                            <p v-else class="text-body-2">Ordnen Sie diesem Benotungsteil zuerst Punktetypen zu. Danach können Sie die Notengrenzen festlegen.</p>
                            <p v-for="error in gradingPartFormErrors.overall_points_grade_thresholds" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                        </section>
                    </section>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" :disabled="isSavingGradingPart" @click="closeGradingPartDialog">Abbrechen</v-btn>
                    <v-btn
                        type="submit"
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        :loading="isSavingGradingPart"
                        :disabled="!gradingPartForm.name.trim() || !gradingPartWeightValid || Boolean(gradingPartOverallThresholdError)">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="gradingPartDeleteDialogOpen" persistent max-width="480">
            <v-card rounded="xl">
                <v-card-title>Benotungsteil löschen</v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal">
                        Soll das Benotungsteil
                        <strong>{{ gradingPartPendingDeletion?.name }}</strong>
                        gelöscht werden? Die Benotungseinträge bleiben erhalten.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" :disabled="isDeletingGradingPart" @click="closeDeleteGradingPartDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="isDeletingGradingPart" @click="confirmGradingPartDelete">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="editDialogOpen" persistent max-width="680">
            <v-card tag="form" rounded="xl" class="entry-dialog" @submit.prevent="saveEntry">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon"><v-icon icon="mdi-playlist-edit" /></span>
                        <div>
                            <div class="dialog-eyebrow">Eintragsvorlage</div>
                            <div>{{ entryDialogTitle }}</div>
                        </div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" :disabled="isSaving" @click="closeEditDialog" />
                </v-card-title>
                <v-card-text class="dialog-body">
                    <section class="form-section">
                        <div class="form-heading">
                            <v-icon icon="mdi-text-box-edit-outline" color="primary" />
                            <strong>Bezeichnung</strong>
                        </div>
                        <v-row dense>
                            <v-col cols="12" sm="4">
                                <v-text-field
                                    v-model="entryForm.short_name"
                                    label="Kürzel"
                                    maxlength="2"
                                    counter="2"
                                    variant="outlined"
                                    :error-messages="formErrors.short_name"
                                    @update:model-value="normalizeShortName(entryForm)" />
                            </v-col>
                            <v-col cols="12" sm="8">
                                <v-text-field v-model="entryForm.name" label="Name" maxlength="255" variant="outlined" :error-messages="formErrors.name" />
                            </v-col>
                        </v-row>
                        <v-textarea
                            v-model="entryForm.description"
                            label="Beschreibung"
                            rows="3"
                            auto-grow
                            :maxlength="1024"
                            :counter="1024"
                            variant="outlined"
                            :error-messages="formErrors.description" />
                    </section>

                    <section v-if="entryForm.category === 'Benotung'" class="form-section">
                        <div class="form-heading">
                            <v-icon icon="mdi-format-color-fill" color="primary" />
                            <strong>Markierung in Tabelle</strong>
                        </div>
                        <v-btn-toggle v-model="entryForm.has_table_marking" mandatory selected-class="choice-selected" class="choice-grid">
                            <v-btn :value="false" class="choice-card" variant="text">
                                <v-icon icon="mdi-close-circle-outline" />
                                <span>Nein</span>
                            </v-btn>
                            <v-btn :value="true" class="choice-card" variant="text">
                                <v-icon icon="mdi-check-circle-outline" />
                                <span>Ja</span>
                            </v-btn>
                        </v-btn-toggle>
                        <v-expand-transition>
                            <div v-if="entryForm.has_table_marking" class="mt-4">
                                <div class="text-caption text-medium-emphasis mb-2">Farbe auswählen</div>
                                <v-btn-toggle
                                    v-model="entryForm.table_marking_color"
                                    mandatory
                                    selected-class="table-marking-color-selected"
                                    class="table-marking-colors"
                                    aria-label="Farbe für die Tabellenmarkierung auswählen">
                                    <v-btn
                                        v-for="colorOption in tableMarkingColors"
                                        :key="colorOption.value"
                                        :value="colorOption.value"
                                        :aria-label="colorOption.label"
                                        :title="colorOption.label"
                                        class="table-marking-color"
                                        variant="text">
                                        <span class="table-marking-swatch" :style="{ backgroundColor: colorOption.swatch }" />
                                        <span>{{ colorOption.label }}</span>
                                    </v-btn>
                                </v-btn-toggle>
                                <div v-if="formErrors.table_marking_color" class="form-error mt-2">{{ formErrors.table_marking_color[0] }}</div>
                            </div>
                        </v-expand-transition>
                    </section>

                    <section v-if="entryForm.category === 'Benotung'" class="form-section">
                        <div class="properties-heading">
                            <div class="form-heading mb-0">
                                <v-icon icon="mdi-tune-variant" color="primary" />
                                <strong>Eigenschaften</strong>
                            </div>
                        </div>
                        <v-expand-transition>
                            <div class="mt-4">
                                <v-btn-toggle :model-value="selectedPropertyMode" mandatory selected-class="choice-selected" class="choice-grid" @update:model-value="selectPropertyMode">
                                    <v-btn value="grades" class="choice-card" variant="text">
                                        <v-icon icon="mdi-format-list-checks" />
                                        <span>Standardnoten</span>
                                    </v-btn>
                                    <v-btn value="free" class="choice-card" variant="text">
                                        <v-icon icon="mdi-pencil-outline" />
                                        <span>Freie Eingabe</span>
                                    </v-btn>
                                    <v-btn value="plus_minus" class="choice-card" variant="text">
                                        <v-icon icon="mdi-plus-minus" />
                                        <span>Plus und Minus</span>
                                    </v-btn>
                                    <v-btn value="plus" class="choice-card" variant="text">
                                        <v-icon icon="mdi-plus" />
                                        <span>Nur Plus</span>
                                    </v-btn>
                                    <v-btn value="points" class="choice-card" variant="text">
                                        <v-icon icon="mdi-counter" />
                                        <span>Punkte</span>
                                    </v-btn>
                                </v-btn-toggle>
                                <v-text-field
                                    v-if="entryForm.properties_mode === 'points'"
                                    :model-value="gradingNumberInput(entryForm.maximum_points)"
                                    @update:model-value="entryForm.maximum_points = $event"
                                    label="Maximale Punktzahl"
                                    type="text"
                                    inputmode="decimal"
                                    suffix="Punkte"
                                    class="mt-4"
                                    variant="outlined"
                                    hint="Die Punktzahl muss größer als 0 sein. Kommazahlen sind möglich."
                                    persistent-hint
                                    :error-messages="formErrors.maximum_points || entryMaximumPointsError" />
                                <p v-if="entryForm.properties_mode === 'plus_minus'" class="text-body-2 mt-3 mb-0">
                                    Mehrere Pluszeichen (+, ++, +++, …) oder Minuszeichen (-, --, ---, …) eingeben.
                                </p>
                                <p v-else-if="entryForm.properties_mode === 'plus'" class="text-body-2 mt-3 mb-0">
                                    Nur Pluszeichen (+, ++, +++, …) eingeben.
                                </p>
                                <p v-else-if="selectedPropertyMode === 'grades'" class="text-body-2 mt-3 mb-0">
                                    Noten 1, 2, 3, 4 und 5 auswählen.
                                </p>
                                <v-combobox
                                    v-if="['fixed', 'free'].includes(entryForm.properties_mode) && selectedPropertyMode !== 'grades'"
                                    v-model="entryForm.fixed_properties"
                                    class="entry-properties-combobox mt-4"
                                    label="Eigenschaften"
                                    hint="Eigenschaft eingeben und mit Enter bestätigen. Den Wert können Sie direkt darunter zuordnen."
                                    persistent-hint
                                    variant="outlined"
                                    multiple
                                    chips
                                    closable-chips
                                    clearable
                                    :error-messages="formErrors.fixed_properties" />
                                <div v-if="selectedPropertyMode === 'free' && editableEntryProperties.length" class="mt-4">
                                    <div class="text-subtitle-2 mb-3">Wertezuordnung</div>
                                    <fieldset v-for="property in editableEntryProperties" :key="property" class="calculation-score-choice" :disabled="isSaving">
                                        <legend>{{ property }}</legend>
                                        <div class="d-flex ga-2 align-start">
                                            <v-text-field
                                                :model-value="entryPropertyValue(property) === 'ignored' ? null : gradingNumberInput(entryPropertyValue(property))"
                                                label="Wert"
                                                type="text"
                                                inputmode="decimal"
                                                density="compact"
                                                variant="outlined"
                                                hide-details="auto"
                                                clearable
                                                :disabled="isSaving"
                                                @update:model-value="setEntryPropertyValue(property, $event)" />
                                            <v-btn
                                                :color="entryPropertyValue(property) === 'ignored' ? 'primary' : undefined"
                                                :variant="entryPropertyValue(property) === 'ignored' ? 'flat' : 'outlined'"
                                                :aria-pressed="entryPropertyValue(property) === 'ignored'"
                                                :disabled="isSaving"
                                                @click="setEntryPropertyValue(property, entryPropertyValue(property) === 'ignored' ? null : 'ignored')">Nicht berücksichtigen</v-btn>
                                        </div>
                                    </fieldset>
                                    <p class="text-caption">Ein leeres Feld lässt die Zuordnung offen. 0 zählt nicht zur Quote; „Nicht berücksichtigen“ nimmt die Eigenschaft aus der Auswertung.</p>
                                </div>
                                <p v-if="entryPropertyValuesError" class="text-error text-body-2 mt-2" role="status">{{ entryPropertyValuesError }}</p>
                                <p v-for="error in entryPropertyServerErrors" :key="error" class="text-error text-body-2 mt-2" role="status">{{ error }}</p>
                                <fieldset class="mt-4 pa-3 rounded-lg">
                                    <legend>Zusatzoptionen</legend>
                                    <v-checkbox
                                        v-for="option in specialPropertyOptions"
                                        :key="option.value"
                                        v-model="entryForm.enabled_special_properties"
                                        :value="option.value"
                                        :label="option.label"
                                        :disabled="isSaving"
                                        color="primary"
                                        hide-details />
                                    <p v-for="error in formErrors.enabled_special_properties" :key="error" class="text-error text-body-2" role="status">{{ error }}</p>
                                </fieldset>
                            </div>
                        </v-expand-transition>
                    </section>

                    <section v-else class="form-section">
                        <div class="properties-heading">
                            <div class="form-heading mb-0">
                                <v-icon icon="mdi-bell-outline" color="primary" />
                                <strong>Verständigungen</strong>
                            </div>
                            <v-switch v-model="entryForm.has_notifications" color="primary" hide-details inset aria-label="Verständigungen aktivieren" />
                        </div>
                        <v-expand-transition>
                            <div v-if="entryForm.has_notifications" class="notification-options mt-4">
                                <v-checkbox
                                    v-model="entryForm.notification_recipients"
                                    value="class_teacher"
                                    label="Klassenvorstand"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    multiple />
                                <v-checkbox
                                    v-model="entryForm.notification_recipients"
                                    value="parents"
                                    label="Eltern"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    multiple />
                                <v-checkbox
                                    v-model="entryForm.notification_recipients"
                                    value="student"
                                    label="Schüler:in"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    multiple />
                            </div>
                        </v-expand-transition>
                    </section>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="closeEditDialog">Abbrechen</v-btn>
                    <v-btn type="submit" color="primary" variant="flat" rounded="lg" :loading="isSaving" :disabled="!canSaveEntry">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialogOpen" persistent max-width="480">
            <v-card rounded="xl">
                <v-card-title>Eintrag löschen</v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal">
                        <strong>{{ entryPendingDeletion?.short_name }} – {{ entryPendingDeletion?.name }}</strong>
                        wirklich löschen?
                    </v-alert>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="closeDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" :loading="isDeleting" @click="confirmDelete">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>
</template>

<script>
import {
    destroy as removeGradingEntryAssignment,
    store as storeGradingEntryAssignment,
} from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingEntryGradingPartEntryController'
import { update as updateGradingPart } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingEntryGradingPartController'
import { update as updateEntryArea } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingEntryAreaController'
import { update as updateCalculationSettings } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingEntryCalculationSettingsController'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const specialPropertyOptions = [
    { value: 'NA', label: 'NA – Nicht angetreten' },
    { value: 'VL', label: 'VL – Vorgetäuschte Leistung' },
    { value: 'F', label: 'F – Gefehlt' },
]

function gradingPartAllowsEntry(gradingPart, entry) {
    return gradingPart?.allowed_entry_types !== 'points' || entry.properties_mode === 'points'
}

function entryPartWeightError(value) {
    const weight = parseGradingNumber(value)
    return Number.isInteger(weight) && weight >= 1 && weight <= 9999999 && /^\d+$/.test(String(value).trim())
        ? '' : 'Bitte eine positive ganze Zahl als Gewichtung eingeben.'
}

function entryOtherAssessmentMode(entry, value = entry?.grading_part_other_assessment_mode) {
    const allowed = { points: ['points', 'weighted'], plus_minus: ['balance_rounding', 'balance_adjustment'] }[entry?.properties_mode] || []
    return allowed.includes(value) ? value : null
}

function entryUsesPartWeight(entry, mode = entry?.grading_part_assessment_mode ?? 'weighted', otherMode = entry?.grading_part_other_assessment_mode) {
    return mode === 'weighted' || (mode === 'other' && entryOtherAssessmentMode(entry, otherMode) === 'weighted')
}

function individualPointWeighting(entry, parts = []) {
    if (entry?.properties_mode !== 'points') return null
    const part = parts.find((item) => item.id === entry.teaching_entry_grading_part_id)
    return part?.allowed_entry_types === 'points' && (part.points_assessment_mode ?? 'individual') === 'individual'
        ? part.individual_points_weighting_mode ?? 'weighted' : null
}

function entryAdjustmentError(plus, minus) {
    return [plus, minus].some((value) => !Number.isFinite(parseGradingNumber(value)) || parseGradingNumber(value) < 0)
        ? 'Bitte für Plus und Minus jeweils eine Zahl ab 0 eingeben. Kommazahlen sind erlaubt.' : ''
}

function entrySpecialProperties(entry) {
    const codes = specialPropertyOptions.map((option) => option.value)
    return codes.filter((code) => (entry.enabled_special_properties ?? codes).includes(code))
}

function parseGradingNumber(value) {
    if (value === null || value === undefined || typeof value === 'boolean' || String(value).trim() === '') return NaN
    return Number(String(value).trim().replace(',', '.'))
}

function usesOtherPlusGrading(entry, allowsMaximum, mode) {
    return entry?.properties_mode === 'plus' && (!allowsMaximum || mode === 'other')
}

function plusGradeThresholdError(thresholds) {
    const values = [1, 2, 3, 4].map((grade) => thresholds?.[grade])
    if (values.some((value) => value === null || value === undefined || String(value).trim() === '')) {
        return 'Bitte die Mindestanzahl für alle vier Noten eingeben.'
    }
    const numbers = values.map(parseGradingNumber)
    if (numbers.some((value) => !Number.isSafeInteger(value) || value < 0)) {
        return 'Bitte ganze Zahlen ab 0 eingeben.'
    }
    if (numbers.some((value, index) => index < 3 && value <= numbers[index + 1])) {
        return 'Die Mindestanzahl muss von Sehr gut bis Genügend jeweils kleiner werden.'
    }
    return ''
}

function usesFreeGrading(entry) {
    return entry?.category === 'Benotung' && entry.has_properties && entryPropertyMode(entry) === 'free'
}

function pointsMaximumLabel(entry) {
    const maximum = parseGradingNumber(entry.maximum_points)
    return Number.isFinite(maximum) && maximum > 0 ? `Punkte · maximal ${maximum.toLocaleString('de-AT')}` : 'Punkte'
}

function pointThresholdError(thresholds, maximum) {
    const error = freeGradeThresholdError('points', thresholds)
    if (error) return error
    const maximumPoints = parseGradingNumber(maximum)
    if (!Number.isFinite(maximumPoints) || maximumPoints <= 0) return 'Bitte unter Benotung eine gültige maximale Punktzahl festlegen.'
    return Object.values(thresholds).some((value) => parseGradingNumber(value) < 0 || parseGradingNumber(value) > maximumPoints)
        ? `Die Notengrenzen müssen zwischen 0 und ${maximumPoints.toLocaleString('de-AT')} Punkten liegen.` : ''
}

function freeGradeThresholdError(mode, thresholds) {
    const values = [1, 2, 3, 4].map((grade) => thresholds?.[grade])
    if (values.some((value) => value === null || value === undefined || String(value).trim() === '')) {
        return 'Bitte die Grenzen für alle vier Noten eingeben.'
    }
    const numbers = values.map(parseGradingNumber)
    if (numbers.some((value) => !Number.isFinite(value))) return 'Bitte gültige Zahlen eingeben.'
    if (mode === 'deficit_points') {
        if (numbers.some((value) => value < 0)) return 'Bitte Punktdifferenzen ab 0 eingeben.'
        if (numbers.some((value, index) => index < 3 && value >= numbers[index + 1])) return 'Die erlaubten fehlenden Punkte müssen von Sehr gut bis Genügend jeweils größer werden.'
    } else if (numbers.some((value, index) => index < 3 && value <= numbers[index + 1])) {
        return 'Die Mindestpunkte müssen von Sehr gut bis Genügend jeweils kleiner werden.'
    }
    return ''
}

const tableMarkingColors = [
    { value: 'blue', label: 'Blau', swatch: '#3b82f6' },
    { value: 'green', label: 'Grün', swatch: '#22c55e' },
    { value: 'orange', label: 'Orange', swatch: '#f97316' },
    { value: 'purple', label: 'Violett', swatch: '#8b5cf6' },
    { value: 'red', label: 'Rot', swatch: '#ef4444' },
]

function entryPropertyMode(entry) {
    if (entry.properties_mode !== 'fixed') return entry.properties_mode
    const properties = entry.fixed_properties || []
    return properties.length === 5 && ['1', '2', '3', '4', '5'].every((grade) => properties.includes(grade)) ? 'grades' : 'free'
}

function entryCalculationProperties(entry) {
    if (!entry.has_properties) return []
    if (entry.properties_mode === 'fixed') return entry.fixed_properties || []

    const properties = entry.properties_mode === 'free' ? entry.fixed_properties || [] : []
    return [...new Set([...properties, ...(entry.property_evaluations || []).map((item) => item.property)])]
}

function isStandardCalculationValue(mode, value) {
    if (mode === 'grades') return /^[1-5]$/.test(String(value).trim())
    if (mode !== 'plus_minus') return false

    const normalized = String(value).trim().replaceAll('−', '-')

    return normalized === '0' || /^[+-]+$/.test(normalized)
}

function normalizePropertyEvaluation(value) {
    if (value === 'positive') return 1
    if (value === 'negative') return -1
    if (value === 'neutral') return 0
    if (value === 'ignored' || (typeof value === 'number' && Number.isFinite(value))) return value

    return null
}

function overallCalculationIssue(part, entries) {
    if (part?.allowed_entry_types !== 'points' || part.points_assessment_mode !== 'overall') return ''
    const assigned = entries.filter((entry) => entry.teaching_entry_grading_part_id === (part.gradingPartId ?? part.id))
    if (!assigned.length) return 'Für die Gesamtbeurteilung fehlen zugeordnete Punktetypen.'
    if (assigned.some((entry) => entry.properties_mode !== 'points')) return 'Diesem Benotungsteil sind andere Eintragstypen als Punktetypen zugeordnet.'
    const maxima = assigned.map((entry) => parseGradingNumber(entry.maximum_points))
    if (maxima.some((maximum) => !Number.isFinite(maximum) || maximum <= 0)) return 'Bei einem zugeordneten Punktetyp fehlt eine gültige Höchstpunktzahl.'
    return pointThresholdError(part.overall_points_grade_thresholds, Number(maxima.reduce((sum, maximum) => sum + maximum, 0).toPrecision(15)))
}

function calculationIssueForEntry(entry, gradingParts, entries) {
    const inheritedWeighting = individualPointWeighting(entry, gradingParts)
    if (inheritedWeighting === 'weighted') {
        const weightError = entryPartWeightError(entry.grading_part_weight)
        if (weightError) return weightError
    }
    if (entry.grading_part_assessment_mode === 'other' && entryOtherAssessmentMode(entry) === 'balance_adjustment') {
        const adjustmentError = entryAdjustmentError(entry.grading_part_plus_adjustment, entry.grading_part_minus_adjustment)
        if (adjustmentError) return adjustmentError
    }
    if (!inheritedWeighting && entry.teaching_entry_grading_part_id && entryUsesPartWeight(entry)
        && entries.filter((item) => item.teaching_entry_grading_part_id === entry.teaching_entry_grading_part_id).length > 1) {
        const weightError = entryPartWeightError(entry.grading_part_weight ?? 1)
        if (weightError) return weightError
    }
    if (entry.properties_mode === 'points') {
        const maximum = parseGradingNumber(entry.maximum_points)
        if (!Number.isFinite(maximum) || maximum <= 0) return 'Bitte unter Benotung eine gültige maximale Punktzahl festlegen.'
        const part = gradingParts.find((item) => item.id === entry.teaching_entry_grading_part_id)
        return part?.allowed_entry_types === 'points' && part.points_assessment_mode === 'overall'
            ? overallCalculationIssue(part, entries) : pointThresholdError(entry.points_grade_thresholds, maximum)
    }
    if (entry.properties_mode === 'plus') {
        return usesOtherPlusGrading(entry, Boolean(entry.allows_maximum_plus), entry.maximum_plus_grading_mode ?? 'standard_percentage')
            ? plusGradeThresholdError(entry.maximum_plus_grade_thresholds) : ''
    }
    if (usesFreeGrading(entry)) {
        const properties = entryCalculationProperties(entry).filter((property) => !specialPropertyOptions.some((option) => option.value === property))
        if (properties.some((property) => normalizePropertyEvaluation(entry.property_evaluations?.find((item) => item.property === property)?.evaluation) === null)) {
            return 'Für eine Eigenschaft fehlt die Wertezuordnung unter Benotung.'
        }
        const mode = entry.free_grading_mode === 'points' ? 'points' : 'deficit_points'
        return freeGradeThresholdError(mode, mode === 'points' ? entry.free_points_grade_thresholds : entry.free_deficit_grade_thresholds)
    }
    return ''
}

export default {
    components: { ItsGridBox },

    data() {
        return {
            areas: [],
            entries: [],
            gradingParts: [],
            calculationDialogOpen: false,
            calculationDialogEntry: null,
            calculationDialogAllowsMaximumPlus: false,
            calculationDialogPartAssessmentMode: 'weighted',
            calculationDialogPartOtherAssessmentMode: null,
            calculationDialogPlusAdjustment: null,
            calculationDialogMinusAdjustment: null,
            calculationDialogPartWeight: 1,
            calculationDialogSumPlusEvaluations: false,
            calculationDialogMaximumPlusGradingMode: 'standard_percentage',
            calculationDialogGradeThresholds: { 1: null, 2: null, 3: null, 4: null },
            calculationDialogFreeGradingMode: 'deficit_points',
            calculationDialogFreeDeficitThresholds: { 1: null, 2: null, 3: null, 4: null },
            calculationDialogFreePointThresholds: { 1: null, 2: null, 3: null, 4: null },
            calculationDialogPointThresholds: { 1: null, 2: null, 3: null, 4: null },
            calculationDialogErrors: {},
            isSavingCalculationDialog: false,
            semesterDrafts: {},
            isSavingSemesters: false,
            courseStore: null,
            activeAreaId: null,
            activeCategory: 'Benotung',
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            tableMarkingColors,
            specialPropertyOptions,
            standardPercentageGrades: [
                { min: 87.5, max: 100, grade: 1, label: 'Sehr gut' },
                { min: 75, max: 87.5, grade: 2, label: 'Gut' },
                { min: 62.5, max: 75, grade: 3, label: 'Befriedigend' },
                { min: 50, max: 62.5, grade: 4, label: 'Genügend' },
                { min: 0, max: 50, grade: 5, label: 'Nicht genügend' },
            ],
            editDialogOpen: false,
            deleteDialogOpen: false,
            areaDialogOpen: false,
            areaDeleteDialogOpen: false,
            gradingPartDialogOpen: false,
            gradingPartDeleteDialogOpen: false,
            entryCopyDialogOpen: false,
            previousYearImportDialogOpen: false,
            previousYearImportOffer: null,
            isLoading: false,
            isSaving: false,
            isDeleting: false,
            isSavingArea: false,
            isDeletingArea: false,
            isSavingGradingPart: false,
            isDeletingGradingPart: false,
            isAssigningGradingEntry: false,
            isCopyingEntries: false,
            isImportingPreviousYear: false,
            selectedEntryId: null,
            deleteEntryId: null,
            editingAreaId: null,
            editingGradingPartId: null,
            deleteAreaId: null,
            deleteGradingPartId: null,
            assignGradingPartId: null,
            selectedGradingEntryIds: [],
            selectedSourceAreaId: null,
            formErrors: {},
            areaFormErrors: {},
            gradingPartFormErrors: {},
            gradingPartOverallThresholds: { 1: null, 2: null, 3: null, 4: null },
            entryCopyErrors: {},
            areaForm: { name: '' },
            gradingPartForm: { name: '', weight: 1, is_required: false, weighting_mode: 'relative', fixed_percentage: null, allowed_entry_types: 'all', points_assessment_mode: 'individual', individual_points_weighting_mode: 'weighted' },
            entryForm: {
                teaching_entry_area_id: null,
                short_name: '',
                name: '',
                description: '',
                category: 'Benotung',
                has_properties: true,
                properties_mode: 'free',
                maximum_points: null,
                fixed_properties: [],
                property_evaluations: [],
                enabled_special_properties: specialPropertyOptions.map((option) => option.value),
                has_notifications: false,
                notification_recipients: [],
                has_table_marking: false,
                table_marking_color: null,
            },
        }
    },

    computed: {
        calculationDialogIndividualPointWeighting() {
            return individualPointWeighting(this.calculationDialogEntry, this.gradingParts)
        },
        calculationDialogUsesBalanceAdjustment() {
            return this.calculationDialogHasPartAssessment && this.calculationDialogPartAssessmentMode === 'other'
                && this.calculationDialogEntry?.properties_mode === 'plus_minus' && this.calculationDialogPartOtherAssessmentMode === 'balance_adjustment'
        },
        calculationDialogAdjustmentError() {
            return this.calculationDialogUsesBalanceAdjustment ? entryAdjustmentError(this.calculationDialogPlusAdjustment, this.calculationDialogMinusAdjustment) : ''
        },
        calculationDialogHasPartAssessment() {
            if (individualPointWeighting(this.calculationDialogEntry, this.gradingParts)) return true
            const partId = this.calculationDialogEntry?.teaching_entry_grading_part_id
            return Boolean(partId) && this.calculationEntries.filter((entry) => entry.teaching_entry_grading_part_id === partId).length > 1
        },
        calculationDialogUsesPartWeight() {
            const inheritedWeighting = individualPointWeighting(this.calculationDialogEntry, this.gradingParts)
            if (inheritedWeighting) return inheritedWeighting === 'weighted'
            return entryUsesPartWeight(this.calculationDialogEntry, this.calculationDialogPartAssessmentMode, this.calculationDialogPartOtherAssessmentMode)
        },
        calculationDialogPartWeightError() {
            const inheritedWeighting = individualPointWeighting(this.calculationDialogEntry, this.gradingParts)
            if (inheritedWeighting) return inheritedWeighting === 'weighted' ? entryPartWeightError(this.calculationDialogPartWeight) : ''
            return this.calculationDialogHasPartAssessment && entryUsesPartWeight(this.calculationDialogEntry, this.calculationDialogPartAssessmentMode, this.calculationDialogPartOtherAssessmentMode)
                ? entryPartWeightError(this.calculationDialogPartWeight) : ''
        },
        gradingPartOverallMaximumPoints() {
            if (!this.editingGradingPartId) return 0
            const total = this.calculationEntries
                .filter((entry) => entry.teaching_entry_grading_part_id === this.editingGradingPartId && entry.properties_mode === 'points')
                .reduce((sum, entry) => sum + parseGradingNumber(entry.maximum_points), 0)
            return Number.isFinite(total) && total > 0 ? Number(total.toPrecision(15)) : 0
        },
        gradingPartOverallThresholdError() {
            if (this.gradingPartForm.allowed_entry_types !== 'points' || this.gradingPartForm.points_assessment_mode !== 'overall' || this.gradingPartOverallMaximumPoints <= 0) return ''
            return pointThresholdError(this.gradingPartOverallThresholds, this.gradingPartOverallMaximumPoints)
        },
        gradingPartOverallFailingGradeLabel() {
            const value = parseGradingNumber(this.gradingPartOverallThresholds[4])
            return Number.isFinite(value) ? `Weniger als ${value.toLocaleString('de-AT')} Punkte` : 'Unter der Grenze für Genügend'
        },
        calculationDialogOverallPart() {
            if (this.calculationDialogEntry?.properties_mode !== 'points') return null
            return this.gradingParts.find((part) => part.id === this.calculationDialogEntry.teaching_entry_grading_part_id
                && part.allowed_entry_types === 'points' && part.points_assessment_mode === 'overall') || null
        },
        calculationDialogUsesPoints() {
            return this.calculationDialogEntry?.properties_mode === 'points' && !this.calculationDialogOverallPart
        },
        calculationDialogPointThresholdError() {
            return this.calculationDialogUsesPoints ? pointThresholdError(this.calculationDialogPointThresholds, this.calculationDialogEntry.maximum_points) : ''
        },
        calculationDialogPointFailingGradeLabel() {
            const value = parseGradingNumber(this.calculationDialogPointThresholds[4])
            return Number.isFinite(value) ? `Weniger als ${value.toLocaleString('de-AT')} Punkte` : 'Unter der Grenze für Genügend'
        },
        entryMaximumPointsError() {
            if (this.entryForm.category !== 'Benotung' || this.entryForm.properties_mode !== 'points') return ''
            const maximum = parseGradingNumber(this.entryForm.maximum_points)
            return Number.isFinite(maximum) && maximum > 0 ? '' : 'Bitte eine maximale Punktzahl größer als 0 eingeben.'
        },
        calculationDialogUsesStandardGrades() {
            return this.calculationDialogEntry && entryPropertyMode(this.calculationDialogEntry) === 'grades'
        },
        calculationDialogAdditionalProperties() {
            const entry = this.calculationDialogEntry
            if (!entry) return []
            const specials = entrySpecialProperties(entry)
            const properties = [...new Set([...specials, ...(entry.fixed_properties || []), ...(entry.property_evaluations || []).map((item) => item.property)])]
                .filter((property) => !['1', '2', '3', '4', '5'].includes(property))
                .filter((property) => !specialPropertyOptions.some((option) => option.value === property) || specials.includes(property))
            return properties.map((property) => ({ value: property,
                label: specialPropertyOptions.find((option) => option.value === property)?.label ?? property,
                evaluation: normalizePropertyEvaluation(entry.property_evaluations?.find((item) => item.property === property)?.evaluation) === 'ignored'
                    ? 'Nicht berücksichtigen' : this.propertyEvaluationLabel(entry, property),
            }))
        },
        calculationDialogUsesFreeGrading() {
            return usesFreeGrading(this.calculationDialogEntry)
        },
        calculationDialogFreeThresholds() {
            return this.calculationDialogFreeGradingMode === 'deficit_points' ? this.calculationDialogFreeDeficitThresholds : this.calculationDialogFreePointThresholds
        },
        calculationDialogFreeThresholdKey() {
            return this.calculationDialogFreeGradingMode === 'deficit_points' ? 'free_deficit_grade_thresholds' : 'free_points_grade_thresholds'
        },
        calculationDialogFreeThresholdError() {
            return this.calculationDialogUsesFreeGrading ? freeGradeThresholdError(this.calculationDialogFreeGradingMode, this.calculationDialogFreeThresholds) : ''
        },
        calculationDialogFreeFailingGradeLabel() {
            const value = this.calculationDialogFreeThresholds[4]
            if (!Number.isFinite(parseGradingNumber(value))) return 'Außerhalb der Grenze für Genügend'
            const number = parseGradingNumber(value).toLocaleString('de-AT')
            return this.calculationDialogFreeGradingMode === 'deficit_points' ? `Mehr als ${number} Punkte fehlen` : `Weniger als ${number} Punkte`
        },
        calculationDialogUsesOtherGrading() {
            return usesOtherPlusGrading(this.calculationDialogEntry, this.calculationDialogAllowsMaximumPlus, this.calculationDialogMaximumPlusGradingMode)
        },
        calculationDialogThresholdError() {
            return this.calculationDialogUsesOtherGrading ? plusGradeThresholdError(this.calculationDialogGradeThresholds) : ''
        },
        calculationDialogFailingGradeLabel() {
            const value = this.calculationDialogGradeThresholds[4]
            if (value === null || value === undefined || String(value).trim() === '' || !Number.isSafeInteger(Number(value)) || Number(value) < 0) {
                return 'Unter der Grenze für Genügend'
            }
            return `Weniger als ${Number(value).toLocaleString('de-AT')} Plus`
        },
        calculationDialogPropertyLabel() {
            if (!this.calculationDialogEntry) return ''
            if (this.calculationDialogEntry.properties_mode === 'points') return pointsMaximumLabel(this.calculationDialogEntry)
            const mode = entryPropertyMode(this.calculationDialogEntry)
            return { grades: 'Standardnoten', free: 'Freie Eingabe', plus: 'Nur Plus', plus_minus: 'Plus und Minus' }[mode] || ''
        },
        selectedPropertyMode() {
            return entryPropertyMode(this.entryForm)
        },
        editableEntryProperties() {
            return [...new Set((this.entryForm.fixed_properties || []).map((property) => String(property).trim()).filter(Boolean))]
        },
        entryPropertyValuesError() {
            if (this.entryForm.category !== 'Benotung' || entryPropertyMode(this.entryForm) !== 'free') return ''
            if ((this.entryForm.property_evaluations || []).some((item) => this.editableEntryProperties.includes(item.property) && item.evaluation !== null && item.evaluation !== 'ignored'
                && !Number.isFinite(parseGradingNumber(item.evaluation)))) return 'Bitte gültige Zahlenwerte eingeben.'
            return ''
        },
        entryPropertyServerErrors() {
            return Object.entries(this.formErrors).filter(([key]) => key.startsWith('property_evaluations')).flatMap(([, errors]) => errors)
        },
        gradingPartWeightValid() {
            if (this.gradingPartForm.weighting_mode === 'fixed') return !this.gradingPartPercentageError
            const weight = Number(this.gradingPartForm.weight)
            return Number.isFinite(weight) && weight >= 0.001 && weight <= 9999999.999
                && /^\d+(\.\d{1,3})?$/.test(String(this.gradingPartForm.weight))
        },
        gradingPartHasNonPointEntries() {
            return Boolean(this.editingGradingPartId) && this.calculationEntries.some((entry) =>
                entry.teaching_entry_grading_part_id === this.editingGradingPartId && entry.properties_mode !== 'points')
        },
        gradingPartPercentageError() {
            if (this.gradingPartForm.weighting_mode !== 'fixed') return ''
            const percentage = Number(this.gradingPartForm.fixed_percentage)
            if (!Number.isFinite(percentage) || percentage < 0.001 || percentage > 100
                || !/^\d+(\.\d{1,3})?$/.test(String(this.gradingPartForm.fixed_percentage))) {
                return 'Bitte einen Anteil über 0 bis 100 mit höchstens drei Nachkommastellen eingeben.'
            }
            const otherPartsTotal = this.gradingParts
                .filter((part) => part.teaching_entry_area_id === this.activeAreaId && part.id !== this.editingGradingPartId)
                .reduce((total, part) => total + Math.round(Number(part.fixed_percentage ?? 0) * 1000), 0)
            return otherPartsTotal + Math.round(percentage * 1000) > 100000
                ? 'Die festen Anteile dieses Bereichs dürfen zusammen höchstens 100 % ergeben.' : ''
        },
        activeEdit() {
            if (this.calculationDialogOpen || this.editDialogOpen || this.deleteDialogOpen || this.areaDialogOpen
                || this.areaDeleteDialogOpen || this.gradingPartDialogOpen || this.gradingPartDeleteDialogOpen
                || this.entryCopyDialogOpen || this.previousYearImportDialogOpen
                || this.isSaving || this.isDeleting || this.isSavingArea
                || this.isDeletingArea || this.isSavingGradingPart || this.isDeletingGradingPart
                || this.isCopyingEntries || this.isImportingPreviousYear) return 'dialog'
            if (this.semesterDrafts[this.activeAreaId] || this.isSavingSemesters) return 'semesters'
            if (this.assignGradingPartId || this.isAssigningGradingEntry) return 'assignment'

            return null
        },
        isEditing() {
            return this.activeEdit !== null
        },
        semesterForm() {
            const area = this.areas.find((area) => area.id === this.activeAreaId)

            return this.semesterDrafts[this.activeAreaId] || {
                semester_count: area?.semester_count ?? 1,
                semester_1_weight: area?.semester_count === 2 ? area.semester_1_weight : 50,
                semester_2_weight: area?.semester_count === 2 ? area.semester_2_weight : 50,
            }
        },
        semesterValidationMessage() {
            if (this.semesterForm.semester_count === 1) return ''

            const weights = [this.semesterForm.semester_1_weight, this.semesterForm.semester_2_weight]
            if (weights.some((weight) => weight === '' || weight === null || !Number.isInteger(Number(weight)) || Number(weight) < 0 || Number(weight) > 100)) {
                return 'Bitte für beide Semester ganze Prozentwerte zwischen 0 und 100 eingeben.'
            }

            return Number(weights[0]) + Number(weights[1]) === 100 ? '' : 'Die Anteile beider Semester müssen zusammen 100 % ergeben.'
        },
        calculationEntries() {
            return this.entries.filter((entry) => entry.teaching_entry_area_id === this.activeAreaId && entry.category === 'Benotung')
        },
        calculationAreas() {
            return this.gradingParts
                .filter((gradingPart) => gradingPart.teaching_entry_area_id === this.activeAreaId)
                .sort((left, right) => {
                    const leftIsFixed = left.fixed_percentage !== null && left.fixed_percentage !== undefined
                    const rightIsFixed = right.fixed_percentage !== null && right.fixed_percentage !== undefined
                    if (leftIsFixed !== rightIsFixed) return leftIsFixed ? 1 : -1
                    return leftIsFixed ? 0 : Number(right.weight ?? 1) - Number(left.weight ?? 1)
                })
                .map((gradingPart) => ({
                    ...gradingPart,
                    id: `grading-part-${gradingPart.id}`,
                    gradingPartId: gradingPart.id,
                    entries: this.calculationEntries.filter((entry) => entry.teaching_entry_grading_part_id === gradingPart.id),
                }))
        },
        hasGradingEntryAssignmentChanges() {
            if (!this.assignGradingPartId || this.isAssigningGradingEntry) return false

            const assignedEntryIds = this.calculationEntries
                .filter((entry) => entry.teaching_entry_grading_part_id === this.assignGradingPartId)
                .map((entry) => entry.id)

            return assignedEntryIds.length !== this.selectedGradingEntryIds.length
                || assignedEntryIds.some((entryId) => !this.selectedGradingEntryIds.includes(entryId))
        },
        filteredEntries() {
            const entries = this.entries.filter(
                (entry) => entry.teaching_entry_area_id === this.activeAreaId && entry.category === this.activeCategory,
            )

            return entries.sort((firstEntry, secondEntry) => {
                const shortNameComparison = String(firstEntry.short_name).localeCompare(String(secondEntry.short_name), 'de', {
                    numeric: true,
                    sensitivity: 'base',
                })

                if (shortNameComparison !== 0) return shortNameComparison

                return String(firstEntry.name).localeCompare(String(secondEntry.name), 'de', {
                    numeric: true,
                    sensitivity: 'base',
                })
            })
        },
        entryDialogTitle() {
            return this.selectedEntryId ? 'Eintrag bearbeiten' : 'Eintrag hinzufügen'
        },
        areaDialogTitle() {
            return this.editingAreaId ? 'Bereich bearbeiten' : 'Neuen Bereich erstellen'
        },
        activeAreaName() {
            return this.areas.find((area) => area.id === this.activeAreaId)?.name || ''
        },
        availableSourceAreas() {
            return this.areas.filter((area) => area.id !== this.activeAreaId && this.entries.some((entry) => entry.teaching_entry_area_id === area.id))
        },
        canSaveEntry() {
            if (this.entryMaximumPointsError) return false
            if (this.entryPropertyValuesError) return false
            return Boolean(
                String(this.entryForm.short_name).trim() &&
                String(this.entryForm.name).trim() &&
                this.areas.some((area) => area.id === this.entryForm.teaching_entry_area_id) &&
                (this.entryForm.category !== 'Benotung' ||
                    !this.entryForm.has_properties ||
                    this.entryForm.properties_mode !== 'fixed' ||
                    this.entryForm.fixed_properties.length) &&
                (this.entryForm.category !== 'Benotung' ||
                    !this.entryForm.has_table_marking ||
                    tableMarkingColors.some((colorOption) => colorOption.value === this.entryForm.table_marking_color))
            )
        },
        entryPendingDeletion() {
            return this.entries.find((entry) => entry.id === this.deleteEntryId) || null
        },
        areaPendingDeletion() {
            return this.areas.find((area) => area.id === this.deleteAreaId) || null
        },
        gradingPartPendingDeletion() {
            return this.gradingParts.find((gradingPart) => gradingPart.id === this.deleteGradingPartId) || null
        },
        gradingPartPendingAssignment() {
            return this.gradingParts.find((gradingPart) => gradingPart.id === this.assignGradingPartId) || null
        },
    },

    watch: {
        activeCategory(category) {
            this.syncCategoryQuery(category)
        },
        '$route.query.entry_category'(category) {
            this.restoreCategoryFromRoute(category)
        },
        '$route.query.panel'(panel) {
            if (!this.isEntriesPanelActive(panel)) return

            const routeCategory = this.$route?.query?.entry_category
            this.restoreCategoryFromRoute(routeCategory ?? this.activeCategory)
        },
    },

    beforeMount() {
        this.courseStore = useCourseStore()
        this.restoreCategoryFromRoute()
        this.loadData()
    },

    methods: {
        entryAssessmentSymbol(entry) {
            if (entry.grading_part_assessment_mode !== 'other' || entry.properties_mode !== 'plus_minus') return null
            return {
                balance_rounding: {
                    icon: 'mdi-arrow-up-down-bold',
                    label: 'Mehr Plus als Minus: Gesamtbeurteilung aufrunden. Mehr Minus als Plus: Gesamtbeurteilung abrunden.',
                },
                balance_adjustment: {
                    icon: 'mdi-plus-minus',
                    label: 'Notenanpassung pro überschüssigem Plus oder Minus',
                },
            }[entryOtherAssessmentMode(entry)] ?? null
        },
        entryWeightLabel(entry, entryCount) {
            const inheritedWeighting = individualPointWeighting(entry, this.gradingParts)
            if (inheritedWeighting) return inheritedWeighting === 'weighted' && !entryPartWeightError(entry.grading_part_weight) ? Number(entry.grading_part_weight).toLocaleString('de-AT') : ''
            if (entryCount <= 1 || !entryUsesPartWeight(entry)) return ''
            return Number(entry.grading_part_weight ?? 1).toLocaleString('de-AT')
        },
        entryCalculationIssue(entry) {
            return calculationIssueForEntry(entry, this.gradingParts, this.calculationEntries)
        },
        gradingPartCalculationIssue(area) {
            const issue = overallCalculationIssue(area, this.calculationEntries)
            if (issue) return issue
            for (const entry of area.entries) {
                const entryIssue = calculationIssueForEntry(entry, this.gradingParts, this.calculationEntries)
                if (entryIssue) return `${entry.name}: ${entryIssue}`
            }
            return ''
        },
        gradingNumberInput(value) {
            return typeof value === 'number' ? String(value).replace('.', ',') : value
        },
        openCalculationDialog(entry) {
            this.calculationDialogEntry = entry
            this.calculationDialogAllowsMaximumPlus = Boolean(entry.allows_maximum_plus)
            this.calculationDialogPartAssessmentMode = entry.grading_part_assessment_mode ?? 'weighted'
            this.calculationDialogPartOtherAssessmentMode = entryOtherAssessmentMode(entry)
            this.calculationDialogPlusAdjustment = entry.grading_part_plus_adjustment ?? null
            this.calculationDialogMinusAdjustment = entry.grading_part_minus_adjustment ?? null
            this.calculationDialogPartWeight = entry.grading_part_weight ?? (individualPointWeighting(entry, this.gradingParts) === 'weighted' ? null : 1)
            this.calculationDialogSumPlusEvaluations = Boolean(entry.sum_plus_evaluations)
            this.calculationDialogMaximumPlusGradingMode = entry.maximum_plus_grading_mode ?? 'standard_percentage'
            this.calculationDialogGradeThresholds = { 1: null, 2: null, 3: null, 4: null, ...entry.maximum_plus_grade_thresholds }
            this.calculationDialogFreeGradingMode = entry.free_grading_mode === 'points' ? 'points' : 'deficit_points'
            this.calculationDialogFreeDeficitThresholds = { 1: null, 2: null, 3: null, 4: null, ...entry.free_deficit_grade_thresholds }
            this.calculationDialogFreePointThresholds = { 1: null, 2: null, 3: null, 4: null, ...entry.free_points_grade_thresholds }
            this.calculationDialogPointThresholds = { 1: null, 2: null, 3: null, 4: null, ...entry.points_grade_thresholds }
            this.calculationDialogErrors = {}
            this.calculationDialogOpen = true
        },
        standardPercentageRange(band) {
            const min = band.min.toLocaleString('de-AT')
            const max = band.max.toLocaleString('de-AT')
            if (band.grade === 5) return `Unter ${max} %`
            return band.grade === 1 ? `${min} % bis ${max} %` : `${min} % bis unter ${max} %`
        },
        closeCalculationDialog() {
            this.calculationDialogOpen = false
            this.calculationDialogEntry = null
            this.calculationDialogErrors = {}
        },
        async saveCalculationDialog() {
            const hasPartAssessment = Boolean(this.calculationDialogHasPartAssessment)
            const usesBalanceAdjustment = hasPartAssessment && this.calculationDialogPartAssessmentMode === 'other'
                && entryOtherAssessmentMode(this.calculationDialogEntry, this.calculationDialogPartOtherAssessmentMode) === 'balance_adjustment'
            const adjustmentError = usesBalanceAdjustment ? entryAdjustmentError(this.calculationDialogPlusAdjustment, this.calculationDialogMinusAdjustment) : ''
            if (adjustmentError) {
                this.calculationDialogErrors = { grading_part_plus_adjustment: [adjustmentError], grading_part_minus_adjustment: [adjustmentError] }
                return
            }
            if (this.calculationDialogOverallPart && !hasPartAssessment) return
            const inheritedWeighting = individualPointWeighting(this.calculationDialogEntry, this.gradingParts)
            const usesPartWeight = hasPartAssessment && (inheritedWeighting ? inheritedWeighting === 'weighted' : entryUsesPartWeight(this.calculationDialogEntry, this.calculationDialogPartAssessmentMode, this.calculationDialogPartOtherAssessmentMode))
            const weightError = usesPartWeight ? entryPartWeightError(this.calculationDialogPartWeight) : ''
            if (weightError) {
                this.calculationDialogErrors = { grading_part_weight: [weightError] }
                return
            }
            const isFreeGrading = usesFreeGrading(this.calculationDialogEntry)
            const isPoints = this.calculationDialogEntry?.properties_mode === 'points' && !this.calculationDialogOverallPart
            if ((!hasPartAssessment && !isFreeGrading && !isPoints && this.calculationDialogEntry?.properties_mode !== 'plus') || this.isSavingCalculationDialog) return
            const usesOtherGrading = usesOtherPlusGrading(this.calculationDialogEntry, this.calculationDialogAllowsMaximumPlus, this.calculationDialogMaximumPlusGradingMode)
            const freeThresholdKey = this.calculationDialogFreeGradingMode === 'deficit_points' ? 'free_deficit_grade_thresholds' : 'free_points_grade_thresholds'
            const freeThresholds = this.calculationDialogFreeGradingMode === 'deficit_points' ? this.calculationDialogFreeDeficitThresholds : this.calculationDialogFreePointThresholds
            const thresholdError = isPoints ? pointThresholdError(this.calculationDialogPointThresholds, this.calculationDialogEntry.maximum_points)
                : isFreeGrading ? freeGradeThresholdError(this.calculationDialogFreeGradingMode, freeThresholds)
                : usesOtherGrading ? plusGradeThresholdError(this.calculationDialogGradeThresholds) : ''
            if (thresholdError) {
                this.calculationDialogErrors = { [isPoints ? 'points_grade_thresholds' : isFreeGrading ? freeThresholdKey : 'maximum_plus_grade_thresholds']: [thresholdError] }
                return
            }
            this.isSavingCalculationDialog = true
            this.calculationDialogErrors = {}
            try {
                const payload = isPoints ? {
                    points_grade_thresholds: Object.fromEntries([1, 2, 3, 4].map((grade) => [grade, parseGradingNumber(this.calculationDialogPointThresholds[grade])])),
                } : isFreeGrading ? {
                    free_grading_mode: this.calculationDialogFreeGradingMode,
                    [freeThresholdKey]: Object.fromEntries([1, 2, 3, 4].map((grade) => [grade, parseGradingNumber(freeThresholds[grade])])),
                } : this.calculationDialogEntry?.properties_mode === 'plus' ? {
                    allows_maximum_plus: this.calculationDialogAllowsMaximumPlus,
                    sum_plus_evaluations: this.calculationDialogSumPlusEvaluations,
                    maximum_plus_grading_mode: usesOtherGrading ? 'other' : this.calculationDialogMaximumPlusGradingMode,
                } : {}
                if (usesPartWeight) payload.grading_part_weight = parseGradingNumber(this.calculationDialogPartWeight)
                if (hasPartAssessment && !inheritedWeighting) {
                    payload.grading_part_assessment_mode = this.calculationDialogPartAssessmentMode
                    const otherAssessment = entryOtherAssessmentMode(this.calculationDialogEntry, this.calculationDialogPartOtherAssessmentMode)
                    if (this.calculationDialogPartAssessmentMode === 'other' && otherAssessment) {
                        payload.grading_part_other_assessment_mode = otherAssessment
                    }
                    if (usesBalanceAdjustment) {
                        payload.grading_part_plus_adjustment = parseGradingNumber(this.calculationDialogPlusAdjustment)
                        payload.grading_part_minus_adjustment = parseGradingNumber(this.calculationDialogMinusAdjustment)
                    }
                }
                if (usesOtherGrading) {
                    payload.maximum_plus_grade_thresholds = Object.fromEntries([1, 2, 3, 4].map((grade) => [grade, parseGradingNumber(this.calculationDialogGradeThresholds[grade])]))
                }
                const response = await axios.put(updateCalculationSettings.url(this.calculationDialogEntry.id), payload)
                this.replaceGradingEntry(response.data.data)
                this.closeCalculationDialog()
            } catch (error) {
                this.calculationDialogErrors = error?.response?.data?.errors || {}
                this.notifyError(error)
            } finally {
                this.isSavingCalculationDialog = false
            }
        },
        gradingPartWeightLabel(part) {
            return part.fixed_percentage !== null && part.fixed_percentage !== undefined
                ? `${Number(part.fixed_percentage).toLocaleString('de-AT')} % fest`
                : `Gewicht ${Number(part.weight ?? 1).toLocaleString('de-AT')}`
        },
        applyStandardPointThresholds() {
            if (this.calculationDialogOverallPart) return
            const maximum = parseGradingNumber(this.calculationDialogEntry?.maximum_points)
            if (this.calculationDialogEntry?.properties_mode !== 'points' || !Number.isFinite(maximum) || maximum <= 0) return
            this.calculationDialogPointThresholds = Object.fromEntries([0.875, 0.75, 0.625, 0.5]
                .map((ratio, index) => [index + 1, Math.round(Number((maximum * ratio).toPrecision(15)))]))
            this.calculationDialogErrors = {}
        },
        applyStandardOverallPointThresholds() {
            const maximum = this.gradingPartOverallMaximumPoints
            if (this.gradingPartForm.allowed_entry_types !== 'points' || this.gradingPartForm.points_assessment_mode !== 'overall' || !Number.isFinite(maximum) || maximum <= 0) return
            this.gradingPartOverallThresholds = Object.fromEntries([0.875, 0.75, 0.625, 0.5]
                .map((ratio, index) => [index + 1, Math.round(Number((maximum * ratio).toPrecision(15)))]))
            this.gradingPartFormErrors = {}
        },
        gradingPartWeightValue(part) {
            return part.fixed_percentage !== null && part.fixed_percentage !== undefined
                ? `${Number(part.fixed_percentage).toLocaleString('de-AT')} %`
                : Number(part.weight ?? 1).toLocaleString('de-AT')
        },
        standardCalculationLabel(entry) {
            if (!entry.has_properties) return ''
            if (entry.properties_mode === 'points') return pointsMaximumLabel(entry)
            return { grades: 'Standardnoten', plus: 'Nur Plus', plus_minus: 'Plus und Minus', points: 'Punkte' }[entryPropertyMode(entry)] || ''
        },
        calculationProperties(entry) {
            const properties = entryCalculationProperties(entry)

            return ['plus_minus', 'grades'].includes(entry.calculation_mode) ? properties.filter((property) => !isStandardCalculationValue(entry.calculation_mode, property)
                && normalizePropertyEvaluation(entry.property_evaluations?.find((item) => item.property === property)?.evaluation) !== null) : properties
        },
        propertyEvaluationClass(entry, property) {
            const evaluation = normalizePropertyEvaluation(entry.property_evaluations?.find((item) => item.property === property)?.evaluation)
            if (typeof evaluation !== 'number') return ''
            if (entry.calculation_mode === 'grades') {
                if (evaluation === 5) return 'calculation-evaluation--negative'
                if (evaluation === 1 || evaluation === 2) return 'calculation-evaluation--positive'
                if (evaluation === 3 || evaluation === 4) return 'calculation-evaluation--grade-middle'
                return 'calculation-evaluation--neutral'
            }

            return `calculation-evaluation--${evaluation > 0 ? 'positive' : evaluation < 0 ? 'negative' : 'neutral'}`
        },
        propertyEvaluationLabel(entry, property) {
            const evaluation = normalizePropertyEvaluation(entry.property_evaluations?.find((item) => item.property === property)?.evaluation)
            if (evaluation === 'ignored') return 'NB'
            if (typeof evaluation !== 'number') return ''
            if (entry.calculation_mode === 'grades' && evaluation >= 1 && evaluation <= 5) return `Note ${evaluation.toLocaleString('de-AT')}`

            return `${evaluation > 0 && entry.calculation_mode !== 'grades' ? '+' : ''}${evaluation.toLocaleString('de-AT')}`
        },
        propertyModeLabel(mode) {
            return { grades: 'Standardnoten', fixed: 'Feste Auswahl', free: 'Freie Eingabe', plus_minus: 'Plus und Minus', plus: 'Nur Plus', points: 'Punkte' }[mode] || ''
        },
        entryPropertyTypeLabel(entry) {
            if (entry.properties_mode === 'points') return pointsMaximumLabel(entry)
            return this.propertyModeLabel(entryPropertyMode(entry))
        },
        enabledSpecialPropertyOptions(entry) {
            return specialPropertyOptions.filter((option) => entrySpecialProperties(entry).includes(option.value))
        },
        selectPropertyMode(mode) {
            if (mode === 'grades') {
                this.entryForm.properties_mode = 'fixed'
                this.entryForm.fixed_properties = ['1', '2', '3', '4', '5']
                return
            }
            this.entryForm.properties_mode = mode
        },
        entryPropertyValue(property) {
            return this.entryForm.property_evaluations?.find((item) => item.property === property)?.evaluation ?? null
        },
        setEntryPropertyValue(property, value) {
            const evaluation = value === '' || value === null ? null : value
            const mappings = (this.entryForm.property_evaluations || []).filter((item) => item.property !== property)
            this.entryForm.property_evaluations = [...mappings, { property, evaluation }]
        },
        updateSemesterField(field, value) {
            const form = { ...this.semesterForm, [field]: value }
            if (field === 'semester_2_weight') {
                const weight = Number(value)
                form.semester_1_weight = value !== '' && value !== null && Number.isInteger(weight) && weight >= 0 && weight <= 100
                    ? 100 - weight
                    : ''
            }
            this.semesterDrafts[this.activeAreaId] = form
        },
        async saveSemesterSettings() {
            if (this.semesterValidationMessage || this.isSavingSemesters) return

            const areaId = this.activeAreaId
            const area = this.areas.find((area) => area.id === areaId)
            if (!area) return

            const form = this.semesterForm
            this.isSavingSemesters = true
            try {
                const response = await axios.put(updateEntryArea.url(areaId), {
                    name: area.name,
                    semester_count: form.semester_count,
                    semester_1_weight: form.semester_count === 1 ? 100 : Number(form.semester_1_weight),
                    semester_2_weight: form.semester_count === 1 ? 0 : Number(form.semester_2_weight),
                })
                const index = this.areas.findIndex((area) => area.id === areaId)
                if (index !== -1) this.areas.splice(index, 1, response.data.data)
                delete this.semesterDrafts[areaId]
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isSavingSemesters = false
            }
        },
        formatEntryDescription(description) {
            return String(description || '').replace(/<br\s*\/?>/gi, '\n')
        },

        normalizeCategoryQuery(category) {
            const categoryValue = Array.isArray(category) ? category[0] : category

            return this.categoryOptions.includes(categoryValue) ? categoryValue : 'Benotung'
        },
        isEntriesPanelActive(panel = this.$route?.query?.panel) {
            const panelValue = Array.isArray(panel) ? panel[0] : panel

            return panelValue === 'entries'
        },
        restoreCategoryFromRoute(category = this.$route?.query?.entry_category) {
            if (!this.isEntriesPanelActive()) return

            const normalizedCategory = this.normalizeCategoryQuery(category)

            if (this.activeCategory !== normalizedCategory) this.activeCategory = normalizedCategory
            this.syncCategoryQuery(normalizedCategory)
        },
        syncCategoryQuery(category) {
            if (!this.$router || !this.$route || !this.isEntriesPanelActive()) return

            const normalizedCategory = this.normalizeCategoryQuery(category)
            if (this.$route.query?.entry_category === normalizedCategory) return

            this.$router.replace({
                query: {
                    ...this.$route.query,
                    entry_category: normalizedCategory,
                },
            }).catch(() => {})
        },
        async loadData() {
            this.isLoading = true
            try {
                const [areaResponse, entryResponse, gradingPartResponse] = await Promise.all([
                    axios.get('/api/admin/teaching/entry_areas'),
                    axios.get('/api/admin/teaching/entry_definitions'),
                    axios.get('/api/admin/teaching/entry_grading_parts'),
                ])
                this.areas = areaResponse.data?.data || []
                this.entries = entryResponse.data?.data || []
                this.gradingParts = gradingPartResponse.data?.data || []
                this.previousYearImportOffer = areaResponse.data?.meta?.previous_year_import || null
                if (!this.areas.some((area) => area.id === this.activeAreaId)) this.activeAreaId = this.areas[0]?.id || null
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isLoading = false
            }
        },
        entryCountForArea(areaId) {
            return this.entries.filter((entry) => entry.teaching_entry_area_id === areaId).length
        },
        normalizeShortName(entry) {
            entry.short_name = String(entry.short_name || '')
                .trim()
                .toUpperCase()
                .slice(0, 2)
        },
        createEmptyEntry() {
            return {
                teaching_entry_area_id: this.activeAreaId || this.areas[0]?.id || null,
                short_name: '',
                name: '',
                description: '',
                category: this.activeCategory,
                has_properties: this.activeCategory === 'Benotung',
                properties_mode: 'free',
                maximum_points: null,
                fixed_properties: [],
                property_evaluations: [],
                enabled_special_properties: specialPropertyOptions.map((option) => option.value),
                has_notifications: false,
                notification_recipients: [],
                has_table_marking: false,
                table_marking_color: null,
            }
        },
        openCreateDialog() {
            this.selectedEntryId = null
            this.entryForm = this.createEmptyEntry()
            this.formErrors = {}
            this.editDialogOpen = true
        },
        openEditDialog(entry) {
            this.selectedEntryId = entry.id
            this.entryForm = {
                ...entry,
                enabled_special_properties: entrySpecialProperties(entry),
                maximum_points: entry.maximum_points ?? null,
                has_properties: entry.category === 'Benotung',
                properties_mode: entryPropertyMode(entry) === 'grades' ? 'fixed' : entryPropertyMode(entry),
                description: String(entry.description || ''),
                fixed_properties: [...entryCalculationProperties({ ...entry, has_properties: entry.category === 'Benotung' })],
                property_evaluations: (entry.property_evaluations || []).map((item) => ({ ...item, evaluation: normalizePropertyEvaluation(item.evaluation) })),
                notification_recipients: [...(entry.notification_recipients || [])],
                has_table_marking: Boolean(entry.has_table_marking),
                table_marking_color: entry.table_marking_color || null,
            }
            this.formErrors = {}
            this.editDialogOpen = true
        },
        closeEditDialog() {
            this.editDialogOpen = false
            this.selectedEntryId = null
            this.formErrors = {}
        },
        async saveEntry() {
            if (!this.canSaveEntry) return
            if (this.entryForm.category === 'Benotung' && this.entryForm.properties_mode === 'points'
                && (!Number.isFinite(parseGradingNumber(this.entryForm.maximum_points)) || parseGradingNumber(this.entryForm.maximum_points) <= 0)) {
                this.formErrors = { maximum_points: ['Bitte eine maximale Punktzahl größer als 0 eingeben.'] }
                return
            }
            this.normalizeShortName(this.entryForm)
            const hasProperties = this.entryForm.category === 'Benotung'
            const hasNotifications = this.entryForm.category !== 'Benotung' && this.entryForm.has_notifications
            const hasTableMarking = this.entryForm.category === 'Benotung' && this.entryForm.has_table_marking
            const allowedTableMarkingColors = tableMarkingColors.map((colorOption) => colorOption.value)
            const allowedNotificationRecipients = ['class_teacher', 'parents', 'student']
            const payload = {
                ...this.entryForm,
                name: this.entryForm.name.trim(),
                description: String(this.entryForm.description || '').trim() || null,
                has_properties: hasProperties,
                maximum_points: hasProperties && this.entryForm.properties_mode === 'points' ? parseGradingNumber(this.entryForm.maximum_points) : null,
                enabled_special_properties: hasProperties ? entrySpecialProperties(this.entryForm) : [],
                fixed_properties:
                    hasProperties && ['fixed', 'free'].includes(this.entryForm.properties_mode)
                        ? [...new Set(this.entryForm.fixed_properties.map((value) => String(value).trim()).filter(Boolean))]
                        : [],
                properties_mode: hasProperties ? this.entryForm.properties_mode : 'free',
                has_notifications: hasNotifications,
                notification_recipients: hasNotifications
                    ? [...new Set(this.entryForm.notification_recipients.filter((recipient) => allowedNotificationRecipients.includes(recipient)))]
                    : [],
                has_table_marking: hasTableMarking,
                table_marking_color:
                    hasTableMarking && allowedTableMarkingColors.includes(this.entryForm.table_marking_color)
                        ? this.entryForm.table_marking_color
                        : null,
            }
            if (hasProperties && entryPropertyMode(this.entryForm) === 'free') {
                payload.property_evaluations = (this.entryForm.property_evaluations || [])
                    .filter((item) => payload.fixed_properties.includes(item.property) && item.evaluation !== null)
                    .filter((item) => !specialPropertyOptions.some((option) => option.value === item.property) || payload.enabled_special_properties.includes(item.property))
                    .map((item) => ({ property: item.property, evaluation: item.evaluation === 'ignored' ? 'ignored' : parseGradingNumber(item.evaluation) }))
            } else {
                delete payload.property_evaluations
            }
            this.isSaving = true
            this.formErrors = {}
            try {
                const response = this.selectedEntryId
                    ? await axios.put(`/api/admin/teaching/entry_definitions/${this.selectedEntryId}`, payload)
                    : await axios.post('/api/admin/teaching/entry_definitions', payload)
                const saved = response.data.data
                const index = this.entries.findIndex((entry) => entry.id === saved.id)
                if (index === -1) this.entries.push(saved)
                else this.entries.splice(index, 1, saved)
                this.courseStore?.syncEntryDefinition(saved)
                this.activeAreaId = saved.teaching_entry_area_id
                this.activeCategory = saved.category
                this.closeEditDialog()
            } catch (error) {
                this.formErrors = error?.response?.data?.errors || {}
                this.notifyError(error)
            } finally {
                this.isSaving = false
            }
        },
        openDeleteDialog(entry) {
            this.deleteEntryId = entry.id
            this.deleteDialogOpen = true
        },
        closeDeleteDialog() {
            this.deleteDialogOpen = false
            this.deleteEntryId = null
        },
        async confirmDelete() {
            if (!this.deleteEntryId) return
            this.isDeleting = true
            try {
                await axios.delete(`/api/admin/teaching/entry_definitions/${this.deleteEntryId}`)
                this.entries = this.entries.filter((entry) => entry.id !== this.deleteEntryId)
                this.closeDeleteDialog()
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isDeleting = false
            }
        },
        openEntryCopyDialog() {
            this.selectedSourceAreaId = this.availableSourceAreas[0]?.id || null
            this.entryCopyErrors = {}
            this.entryCopyDialogOpen = true
        },
        closeEntryCopyDialog() {
            this.entryCopyDialogOpen = false
            this.selectedSourceAreaId = null
            this.entryCopyErrors = {}
        },
        async copyEntriesFromArea() {
            if (!this.selectedSourceAreaId || !this.activeAreaId) return

            this.isCopyingEntries = true
            this.entryCopyErrors = {}
            try {
                const response = await axios.post(`/api/admin/teaching/entry_areas/${this.activeAreaId}/entry-copies`, {
                    source_area_id: this.selectedSourceAreaId,
                })
                this.entries.push(...response.data.data)
                this.closeEntryCopyDialog()
                this.notifySuccess(`${response.data.copied_count} Einträge wurden übernommen.`)
            } catch (error) {
                this.entryCopyErrors = error?.response?.data?.errors || {}
                this.notifyError(error)
            } finally {
                this.isCopyingEntries = false
            }
        },
        openPreviousYearImport() {
            if (!this.previousYearImportOffer) return
            this.previousYearImportDialogOpen = true
        },
        declinePreviousYearImport() {
            if (this.isImportingPreviousYear) return
            this.previousYearImportDialogOpen = false
        },
        async importPreviousYearAreas() {
            if (!this.previousYearImportOffer) return

            this.isImportingPreviousYear = true
            try {
                const response = await axios.post('/api/admin/teaching/entry-area-imports')
                this.areas = response.data?.data?.areas || []
                this.entries = response.data?.data?.entries || []
                this.gradingParts = response.data?.data?.grading_parts || []
                this.activeAreaId = this.areas[0]?.id || null
                this.previousYearImportOffer = null
                this.previousYearImportDialogOpen = false
                const importedAreaCount = Number(response.data.imported_area_count || 0)
                const importedEntryCount = Number(response.data.imported_entry_count || 0)
                this.notifySuccess(
                    `${importedAreaCount} ${importedAreaCount === 1 ? 'Bereich' : 'Bereiche'} und ${importedEntryCount} ${importedEntryCount === 1 ? 'Eintrag' : 'Einträge'} wurden übernommen.`,
                )
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isImportingPreviousYear = false
            }
        },
        openCreateAreaDialog() {
            this.editingAreaId = null
            this.areaForm = { name: '' }
            this.areaFormErrors = {}
            this.areaDialogOpen = true
        },
        openCreateGradingPartDialog() {
            if (!this.activeAreaId) return
            this.gradingPartOverallThresholds = { 1: null, 2: null, 3: null, 4: null }

            this.editingGradingPartId = null
            this.gradingPartForm = { name: '', weight: 1, is_required: false, weighting_mode: 'relative', fixed_percentage: null, allowed_entry_types: 'all', points_assessment_mode: 'individual', individual_points_weighting_mode: 'weighted' }
            this.gradingPartFormErrors = {}
            this.gradingPartDialogOpen = true
        },
        openEditGradingPartDialog(gradingPartArea) {
            this.gradingPartOverallThresholds = { 1: null, 2: null, 3: null, 4: null, ...gradingPartArea.overall_points_grade_thresholds }
            this.editingGradingPartId = gradingPartArea.gradingPartId
            this.gradingPartForm = {
                name: gradingPartArea.name,
                weight: gradingPartArea.weight ?? 1,
                is_required: gradingPartArea.is_required ?? false,
                allowed_entry_types: gradingPartArea.allowed_entry_types ?? 'all',
                points_assessment_mode: gradingPartArea.points_assessment_mode ?? 'individual',
                individual_points_weighting_mode: gradingPartArea.individual_points_weighting_mode ?? 'weighted',
                weighting_mode: gradingPartArea.fixed_percentage !== null && gradingPartArea.fixed_percentage !== undefined ? 'fixed' : 'relative',
                fixed_percentage: gradingPartArea.fixed_percentage ?? null,
            }
            this.gradingPartFormErrors = {}
            this.gradingPartDialogOpen = true
        },
        closeGradingPartDialog() {
            this.gradingPartOverallThresholds = { 1: null, 2: null, 3: null, 4: null }
            this.gradingPartDialogOpen = false
            this.editingGradingPartId = null
            this.gradingPartForm = { name: '', weight: 1, is_required: false, weighting_mode: 'relative', fixed_percentage: null, allowed_entry_types: 'all', points_assessment_mode: 'individual', individual_points_weighting_mode: 'weighted' }
            this.gradingPartFormErrors = {}
        },
        async saveGradingPart() {
            if (!this.activeAreaId || !this.gradingPartForm.name.trim() || !this.gradingPartWeightValid || this.isSavingGradingPart) return
            if (this.gradingPartOverallThresholdError) {
                this.gradingPartFormErrors = { overall_points_grade_thresholds: [this.gradingPartOverallThresholdError] }
                return
            }
            if (this.gradingPartForm.allowed_entry_types === 'points' && this.gradingPartHasNonPointEntries) {
                this.gradingPartFormErrors = { allowed_entry_types: ['Ein Wechsel ist nicht möglich, solange andere Eintragstypen zugeordnet sind.'] }
                return
            }

            this.isSavingGradingPart = true
            this.gradingPartFormErrors = {}
            try {
                const name = this.gradingPartForm.name.trim()
                const weight = Number(this.gradingPartForm.weight)
                const isRequired = this.gradingPartForm.is_required
                const fixedPercentage = this.gradingPartForm.weighting_mode === 'fixed' ? Number(this.gradingPartForm.fixed_percentage) : null
                const payload = { name, is_required: isRequired, fixed_percentage: fixedPercentage, allowed_entry_types: this.gradingPartForm.allowed_entry_types ?? 'all' }
                if (payload.allowed_entry_types === 'points') payload.points_assessment_mode = this.gradingPartForm.points_assessment_mode ?? 'individual'
                if (payload.points_assessment_mode === 'individual') payload.individual_points_weighting_mode = this.gradingPartForm.individual_points_weighting_mode ?? 'weighted'
                if (payload.points_assessment_mode === 'overall') {
                    payload.overall_points_grade_thresholds = this.gradingPartOverallMaximumPoints > 0
                        ? Object.fromEntries([1, 2, 3, 4].map((grade) => [grade, parseGradingNumber(this.gradingPartOverallThresholds[grade])])) : null
                }
                if (fixedPercentage === null) payload.weight = weight
                const response = this.editingGradingPartId
                    ? await axios.put(updateGradingPart.url(this.editingGradingPartId), payload)
                    : await axios.post('/api/admin/teaching/entry_grading_parts', {
                        teaching_entry_area_id: this.activeAreaId,
                        ...payload,
                    })

                const gradingPartIndex = this.gradingParts.findIndex((gradingPart) => gradingPart.id === response.data.data.id)

                if (gradingPartIndex >= 0) {
                    this.gradingParts.splice(gradingPartIndex, 1, response.data.data)
                } else {
                    this.gradingParts.push(response.data.data)
                }

                this.gradingParts.sort((a, b) => a.name.localeCompare(b.name, 'de'))
                this.closeGradingPartDialog()
            } catch (error) {
                this.gradingPartFormErrors = error?.response?.data?.errors || {}
                this.notifyError(error)
            } finally {
                this.isSavingGradingPart = false
            }
        },
        openDeleteGradingPartDialog(gradingPartArea) {
            this.deleteGradingPartId = gradingPartArea.gradingPartId
            this.gradingPartDeleteDialogOpen = true
        },
        closeDeleteGradingPartDialog() {
            this.gradingPartDeleteDialogOpen = false
            this.deleteGradingPartId = null
        },
        async confirmGradingPartDelete() {
            if (!this.deleteGradingPartId) return

            this.isDeletingGradingPart = true
            try {
                const deletedGradingPartId = this.deleteGradingPartId
                await axios.delete(`/api/admin/teaching/entry_grading_parts/${deletedGradingPartId}`)
                this.gradingParts = this.gradingParts.filter((gradingPart) => gradingPart.id !== deletedGradingPartId)
                this.entries = this.entries.map((entry) => entry.teaching_entry_grading_part_id === deletedGradingPartId
                    ? { ...entry, teaching_entry_grading_part_id: null }
                    : entry)
                this.closeDeleteGradingPartDialog()
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isDeletingGradingPart = false
            }
        },
        toggleGradingEntryAssignment(gradingPartArea) {
            if (this.assignGradingPartId === gradingPartArea.gradingPartId) {
                this.cancelGradingEntryAssignment()
                return
            }

            this.assignGradingPartId = gradingPartArea.gradingPartId
            this.selectedGradingEntryIds = this.calculationEntries
                .filter((entry) => entry.teaching_entry_grading_part_id === gradingPartArea.gradingPartId)
                .map((entry) => entry.id)
        },
        cancelGradingEntryAssignment() {
            this.assignGradingPartId = null
            this.selectedGradingEntryIds = []
        },
        toggleGradingEntrySelection(entry) {
            if (!entry?.id || this.isAssigningGradingEntry) return
            const gradingPart = this.gradingParts?.find((part) => part.id === this.assignGradingPartId)
            if (!gradingPartAllowsEntry(gradingPart, entry)) return

            if (this.selectedGradingEntryIds.includes(entry.id)) {
                this.selectedGradingEntryIds = this.selectedGradingEntryIds.filter((entryId) => entryId !== entry.id)
                return
            }

            this.selectedGradingEntryIds.push(entry.id)
        },
        canAssignGradingEntry(entry) {
            return gradingPartAllowsEntry(this.gradingPartPendingAssignment, entry)
        },
        gradingPartName(gradingPartId) {
            const gradingPart = this.gradingParts.find((part) => part.id === gradingPartId)

            return gradingPart?.name || 'anderer Benotungsteil'
        },
        async saveGradingEntryAssignments() {
            if (!this.assignGradingPartId || this.isAssigningGradingEntry) return

            const gradingPartId = this.assignGradingPartId
            const selectedEntryIds = new Set(this.selectedGradingEntryIds)
            const gradingPart = this.gradingParts?.find((part) => part.id === gradingPartId)
            if (this.calculationEntries.some((entry) => selectedEntryIds.has(entry.id) && !gradingPartAllowsEntry(gradingPart, entry))) {
                this.notifyError(new Error('Diesem Benotungsteil können nur Punktetypen zugeordnet werden.'))
                return
            }
            const changedEntries = this.calculationEntries.filter(
                (entry) => selectedEntryIds.has(entry.id)
                    ? entry.teaching_entry_grading_part_id !== gradingPartId
                    : entry.teaching_entry_grading_part_id === gradingPartId,
            )
            if (!changedEntries.length) return

            this.isAssigningGradingEntry = true
            try {
                for (const entry of changedEntries) {
                    const shouldBeAssigned = selectedEntryIds.has(entry.id)
                    const currentGradingPartId = entry.teaching_entry_grading_part_id

                    if (currentGradingPartId) {
                        await axios.delete(removeGradingEntryAssignment.url({
                            entryGradingPart: currentGradingPartId,
                            entryDefinition: entry.id,
                        }))
                        this.replaceGradingEntry({ ...entry, teaching_entry_grading_part_id: null })
                    }

                    if (!shouldBeAssigned) continue

                    const response = await axios.post(storeGradingEntryAssignment.url(gradingPartId), {
                        teaching_entry_definition_id: entry.id,
                    })
                    this.replaceGradingEntry(response.data.data)
                }

                this.cancelGradingEntryAssignment()
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isAssigningGradingEntry = false
            }
        },
        replaceGradingEntry(entry) {
            const entryIndex = this.entries.findIndex((existingEntry) => existingEntry.id === entry.id)
            if (entryIndex !== -1) this.entries.splice(entryIndex, 1, entry)
        },
        openEditAreaDialog(area) {
            this.editingAreaId = area.id
            this.areaForm = { name: area.name }
            this.areaFormErrors = {}
            this.areaDialogOpen = true
        },
        closeAreaDialog() {
            this.areaDialogOpen = false
            this.editingAreaId = null
            this.areaFormErrors = {}
        },
        async saveArea() {
            if (!this.areaForm.name.trim()) return
            this.isSavingArea = true
            this.areaFormErrors = {}
            try {
                const payload = { name: this.areaForm.name.trim() }
                const response = this.editingAreaId
                    ? await axios.put(`/api/admin/teaching/entry_areas/${this.editingAreaId}`, payload)
                    : await axios.post('/api/admin/teaching/entry_areas', payload)
                const saved = response.data.data
                const initialGradingPart = response.data.grading_part
                const index = this.areas.findIndex((area) => area.id === saved.id)
                if (index === -1) this.areas.push(saved)
                else this.areas.splice(index, 1, saved)
                if (initialGradingPart) this.gradingParts.push(initialGradingPart)
                this.areas.sort((a, b) => a.name.localeCompare(b.name))
                this.activeAreaId = saved.id
                this.closeAreaDialog()
            } catch (error) {
                this.areaFormErrors = error?.response?.data?.errors || {}
                this.notifyError(error)
            } finally {
                this.isSavingArea = false
            }
        },
        openDeleteAreaDialog(area) {
            if (this.entryCountForArea(area.id)) return
            this.deleteAreaId = area.id
            this.areaDeleteDialogOpen = true
        },
        closeAreaDeleteDialog() {
            this.areaDeleteDialogOpen = false
            this.deleteAreaId = null
        },
        async confirmAreaDelete() {
            if (!this.deleteAreaId) return
            this.isDeletingArea = true
            try {
                await axios.delete(`/api/admin/teaching/entry_areas/${this.deleteAreaId}`)
                this.areas = this.areas.filter((area) => area.id !== this.deleteAreaId)
                this.activeAreaId = this.areas[0]?.id || null
                this.closeAreaDeleteDialog()
            } catch (error) {
                this.notifyError(error)
            } finally {
                this.isDeletingArea = false
            }
        },
        notifyError(error) {
            useNotificationStore().notify({ status: error?.response?.status, message: error?.response?.data?.message || 'Fehler passiert.', type: 'error', timeout: 3000 })
        },
        notifySuccess(message) {
            useNotificationStore().notify({ message, type: 'success', timeout: 3000 })
        },
    },
}
</script>

<style scoped>
.entry-section-header,
.entry-section-actions,
.entry-row-content,
.dialog-header,
.dialog-title-group,
.dialog-actions,
.properties-heading {
    display: flex;
    align-items: center;
}
.entry-section-header,
.dialog-header,
.properties-heading {
    justify-content: space-between;
    gap: 16px;
}
.entry-section-actions {
    gap: 8px;
}
.entry-list-actions {
    display: flex;
    justify-content: flex-end;
}
.semester-count-toggle,
.grading-requirement-toggle,
.grading-weight-mode-toggle {
    display: flex;
    max-width: 320px;
}

.semester-count-toggle :deep(.v-btn),
.grading-requirement-toggle :deep(.v-btn),
.grading-weight-mode-toggle :deep(.v-btn) {
    flex: 1;
    min-width: 0;
    padding-inline: 8px;
}
.grading-weight-mode-toggle {
    max-width: 100%;
}
.grading-weight-mode-toggle :deep(.v-btn) {
    font-size: 0.75rem;
    letter-spacing: 0;
    white-space: normal;
}

.semester-weight-inputs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.semester-percentage :deep(.v-field) {
    background: rgba(var(--v-theme-primary), 0.06);
    border-radius: 12px;
}

.semester-percentage :deep(input),
.semester-percentage :deep(.v-text-field__suffix) {
    color: rgb(var(--v-theme-primary));
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.3;
}
.semester-percentage :deep(input) {
    flex: none;
    width: calc(var(--semester-value-width) + var(--v-field-padding-start, 16px) + 4px);
    padding-inline-end: 0;
    appearance: textfield;
}
.semester-percentage :deep(input::-webkit-inner-spin-button),
.semester-percentage :deep(input::-webkit-outer-spin-button) {
    appearance: none;
    margin: 0;
}
.semester-percentage :deep(.v-text-field__suffix) {
    margin-inline-start: 4px;
}

.calculation-semester-grade-header {
    display: flex;
    align-items: flex-start;
    flex-direction: column;
    gap: 8px;
}
.entry-area-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    width: 100%;
}
.entry-area-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(var(--v-border-color), 0.2);
    border-radius: 18px;
    background: rgb(var(--v-theme-surface));
    color: inherit;
    box-shadow: 0 6px 20px rgba(20, 32, 60, 0.06);
    transition: 0.2s ease;
}
.entry-area-select {
    position: relative;
    display: flex;
    flex: 1 1 auto;
    align-items: center;
    gap: 13px;
    width: 100%;
    padding: 18px 16px;
    border: 0;
    background: transparent;
    color: inherit;
    text-align: left;
    cursor: pointer;
}
.entry-area-card:hover {
    transform: translateY(-2px);
    border-color: rgba(var(--v-theme-primary), 0.35);
    box-shadow: 0 10px 28px rgba(var(--v-theme-primary), 0.12);
}
.entry-area-card-active {
    border-color: rgb(var(--v-theme-primary));
    background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.12), rgba(var(--v-theme-primary), 0.03));
}
.entry-area-icon,
.dialog-icon,
.entry-empty-icon {
    display: grid;
    place-items: center;
    flex: 0 0 46px;
    height: 46px;
    border-radius: 14px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.12);
}
.entry-area-content {
    display: flex;
    flex-direction: column;
    min-width: 85px;
    flex: 1;
}
.entry-area-name {
    font-weight: 750;
    font-size: 0.98rem;
}
.entry-area-actions {
    display: grid;
    flex: 0 0 auto;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: auto;
    border-top: 1px solid rgba(var(--v-border-color), 0.12);
    overflow: hidden;
}
.entry-area-action-button {
    min-width: 0 !important;
    border-radius: 0 !important;
    font-size: 0.78rem;
    font-weight: 650;
    letter-spacing: 0;
    text-transform: none;
    transition:
        color 150ms ease,
        background-color 150ms ease;
}
.entry-area-action-button :deep(.v-btn__content) {
    gap: 7px;
}
.entry-area-action-button--edit {
    border-right: 1px solid rgba(var(--v-border-color), 0.12);
}
.entry-area-action-button--edit:hover {
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.08);
}
.entry-area-action-button--delete:hover:not(.v-btn--disabled) {
    background: rgba(var(--v-theme-error), 0.08);
}
.entry-area-check {
    position: absolute;
    top: 9px;
    right: 9px;
    background: rgb(var(--v-theme-surface));
    border-radius: 50%;
}
.entry-empty-area {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 24px;
    border: 1px dashed rgba(var(--v-theme-primary), 0.35);
    border-radius: 20px;
    background: rgba(var(--v-theme-primary), 0.04);
}
.entry-empty-area > div:nth-child(2) {
    flex: 1;
}
.entry-empty-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}
.entry-tabs {
    border-bottom: 1px solid rgba(var(--v-border-color), 0.15);
}
.calculation-area-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.calculation-area-card {
    padding: 14px;
    border: 1px solid rgba(var(--v-border-color), 0.16);
    border-radius: 16px;
    background: rgb(var(--v-theme-surface));
}
.calculation-area-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.calculation-area-heading {
    flex: 1;
    min-width: 0;
}
.calculation-area-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    color: rgb(var(--v-theme-primary));
    border-radius: 10px;
    background: rgba(var(--v-theme-primary), 0.1);
}
.calculation-area-name {
    font-size: 0.95rem;
    font-weight: 700;
}
.calculation-assignment-hint {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding: 8px 10px;
    color: rgb(var(--v-theme-primary));
    border: 1px solid rgba(var(--v-theme-primary), 0.3);
    border-radius: 10px;
    background: rgba(var(--v-theme-primary), 0.08);
}
.calculation-assignment-hint span {
    flex: 1;
}
.calculation-part-card .calculation-area-header {
    margin-bottom: 0;
    padding-right: 20px;
}
.calculation-part-card,
.calculation-entry-open {
    position: relative;
}
.calculation-entry-item.calculation-entry-open {
    padding-right: 30px;
}
.calculation-incomplete-marker {
    position: absolute;
    top: 6px;
    right: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: rgb(var(--v-theme-error));
    color: rgb(var(--v-theme-on-error));
    font-size: 13px;
    font-weight: 800;
    line-height: 1;
}
.calculation-part-card--has-entries .calculation-area-header {
    margin-bottom: 12px;
}
.calculation-part-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
}
.grading-weight-badge {
    display: grid;
    grid-template-columns: 24px auto;
    align-items: center;
    gap: 4px 10px;
    min-width: 104px;
    padding: 12px 14px;
    flex-shrink: 0;
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
    border-radius: 12px;
    color: rgb(var(--v-theme-primary));
    background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.14), rgba(var(--v-theme-primary), 0.04));
}
.grading-weight-badge strong {
    font-size: 1.8rem;
    font-weight: 750;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.grading-weight-badge > span {
    grid-column: 1 / -1;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.04em;
}
.calculation-entry-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 6px;
    padding: 0;
    list-style: none;
}
.calculation-entry-item {
    display: flex;
    align-items: flex-start;
    min-width: 0;
    gap: 8px;
    padding: 10px;
    border: 1px solid rgba(var(--v-border-color), 0.14);
    border-radius: 10px;
    background: rgba(var(--v-theme-on-surface), 0.025);
    transition:
        transform 150ms ease,
        border-color 150ms ease,
        background-color 150ms ease,
        box-shadow 150ms ease,
        opacity 150ms ease;
}
.calculation-entry-open {
    width: 100%;
    text-align: left;
    color: inherit;
    cursor: pointer;
}
.calculation-entry-open:hover,
.calculation-entry-open:focus-visible {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.06);
}
.calculation-entry-open:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
}
.calculation-entry-open:disabled,
.entry-area-select:disabled {
    opacity: 0.5;
    cursor: default;
}
.calculation-entry-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
}
.calculation-entry-selection-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 10px;
    padding: 12px;
    color: inherit;
    border: 1px solid rgba(var(--v-border-color), 0.2);
    border-radius: 12px;
    background: rgb(var(--v-theme-surface));
    text-align: left;
    cursor: pointer;
    transition:
        transform 150ms ease,
        border-color 150ms ease,
        background-color 150ms ease,
        box-shadow 150ms ease;
}
.calculation-entry-selection-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.calculation-entry-selection-card:hover,
.calculation-entry-selection-card:focus-visible {
    transform: translateY(-2px);
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 8px 20px rgba(var(--v-theme-primary), 0.14);
}
.calculation-entry-selection-card--selected {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.12);
    box-shadow: 0 6px 16px rgba(var(--v-theme-primary), 0.12);
}
.calculation-entry-selection-card:focus-visible {
    outline: 2px solid rgba(var(--v-theme-primary), 0.5);
    outline-offset: 2px;
}
.calculation-entry-selection-card:disabled {
    cursor: wait;
    opacity: 0.7;
}
.calculation-assignment-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
}
.calculation-entry-code {
    min-width: 34px;
    margin-top: 1px;
    font-weight: 700;
}
.calculation-entry-code :deep(.v-chip__content) {
    width: 100%;
    justify-content: center;
}
.calculation-entry-details {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    gap: 6px;
}
.calculation-entry-weight,
.calculation-entry-assessment-symbol {
    display: inline-flex;
    align-self: flex-start;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    padding: 2px 6px;
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
    border-radius: 6px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.08);
    font-size: 0.8rem;
    font-variant-numeric: tabular-nums;
}
.maximum-plus-grading-options.balance-assessment-options {
    grid-template-columns: minmax(0, 1fr);
}
.balance-adjustment-values {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    gap: 16px;
}
.balance-adjustment-values > p {
    grid-column: 1 / -1;
}
.balance-assessment-label {
    display: grid;
    gap: 8px;
    padding: 12px 0;
    text-align: left;
}
.calculation-entry-name {
    min-width: 0;
    overflow: hidden;
    font-size: 0.82rem;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.calculation-entry-current-assignment {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.72rem;
}
.calculation-entry-values {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr));
    align-items: center;
    flex-wrap: wrap;
    min-width: 0;
    gap: 6px 16px;
    width: 100%;
    margin-top: 6px;
}
.calculation-entry-no-values {
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.72rem;
}
.calculation-entry-value {
    font-weight: 400;
    max-width: 100%;
    height: auto;
    min-height: 20px;
    padding-block: 3px;
}
.calculation-entry-value :deep(.v-chip__content) {
    white-space: normal;
    overflow-wrap: anywhere;
}
.calculation-score-choice {
    min-width: 0;
    margin-bottom: 12px;
    padding: 8px;
    border: 1px solid rgba(var(--v-border-color), 0.2);
    border-radius: 12px;
}
.calculation-score-choice legend {
    max-width: 100%;
    padding-inline: 6px;
    overflow-wrap: anywhere;
}
.calculation-score-choice :deep(.v-btn__content) {
    white-space: normal;
}
.calculation-property {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 6px 10px;
    padding: 8px 10px;
    border-radius: 8px;
    background: rgb(var(--v-theme-surface));
}
.calculation-property-name {
    font-size: 0.82rem;
    overflow-wrap: anywhere;
}
.calculation-evaluation {
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    line-height: 1.4;
    color: rgba(var(--v-theme-on-surface), 0.65);
    background: rgba(var(--v-theme-on-surface), 0.06);
}
.calculation-evaluation--positive {
    color: rgb(var(--v-theme-success));
    background: rgba(var(--v-theme-success), 0.1);
}
.calculation-evaluation--negative {
    color: rgb(var(--v-theme-error));
    background: rgba(var(--v-theme-error), 0.08);
}
.calculation-evaluation--grade-middle {
    color: rgb(var(--v-theme-warning));
    background: rgba(var(--v-theme-warning), 0.08);
}
.calculation-evaluation--neutral {
    color: rgb(var(--v-theme-info));
    background: rgba(var(--v-theme-info), 0.08);
}
.entry-row {
    border: 1px solid rgba(var(--v-border-color), 0.16);
    border-radius: 16px !important;
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 4px 16px rgba(20, 32, 60, 0.04);
}
.entry-row-content {
    width: 100%;
    min-width: 0;
    align-items: stretch;
    flex-direction: column;
    gap: 6px;
}
.entry-main-row {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 14px;
}
.entry-short {
    min-width: 50px;
    font-size: 0.95rem;
    font-weight: 400;
}
.entry-title {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 2px;
}
.entry-name {
    min-width: 150px;
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 650;
}
.entry-description {
    max-width: 420px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.78rem;
    font-weight: 400;
    line-height: 1.25;
    white-space: pre-line;
}
.entry-properties {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    margin-left: 64px;
    gap: 6px;
    color: rgba(var(--v-theme-on-surface), 0.72);
    font-size: 0.9rem;
    font-weight: 400;
}
.maximum-plus-grading-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
    height: auto;
}
.plus-grade-editor {
    padding: 18px;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
    border-radius: 18px;
    background: linear-gradient(145deg, rgba(var(--v-theme-primary), 0.06), rgba(var(--v-theme-surface), 1) 65%);
}
.calculation-extra-property {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 4px 12px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    font-size: 0.8rem;
}
.calculation-extra-property > span:last-child {
    color: rgba(var(--v-theme-on-surface), 0.65);
}
.plus-grade-editor-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}
.plus-grade-editor-icon {
    display: grid;
    place-items: center;
    flex: 0 0 40px;
    height: 40px;
    border-radius: 12px;
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    font-size: 1.75rem;
}
.plus-grade-editor-heading h3 {
    font-size: 1rem;
    font-weight: 700;
}
.plus-grade-editor-heading p,
.plus-grade-order-hint {
    color: rgba(var(--v-theme-on-surface), 0.65);
    font-size: 0.78rem;
}
.plus-grade-rows {
    display: grid;
    gap: 8px;
}
.plus-grade-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(140px, 170px);
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    border-radius: 12px;
    background: rgb(var(--v-theme-surface));
}
.plus-grade-name {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
}
.plus-grade-badge {
    display: grid;
    place-items: center;
    flex: 0 0 30px;
    height: 30px;
    border-radius: 9px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.1);
    font-weight: 750;
}
.plus-grade-row--automatic {
    background: rgba(var(--v-theme-on-surface), 0.035);
    border-style: dashed;
}
.plus-grade-row--automatic .plus-grade-badge {
    color: rgba(var(--v-theme-on-surface), 0.65);
    background: rgba(var(--v-theme-on-surface), 0.07);
}
.plus-grade-fallback {
    font-size: 0.78rem;
    color: rgba(var(--v-theme-on-surface), 0.65);
}
.plus-grade-order-hint {
    margin-top: 12px;
}
.grade-threshold-input :deep(input) {
    text-align: right;
    font-variant-numeric: tabular-nums;
    min-width: 0;
}
.standard-points-button {
    max-width: 100%;
    height: auto !important;
    min-height: 40px;
    padding-block: 10px;
    text-transform: none;
    letter-spacing: normal;
}
.standard-points-button :deep(.v-btn__content) {
    white-space: normal;
}
.grade-threshold-input :deep(.v-text-field__suffix) {
    padding-left: 8px;
}
@media (max-width: 480px) {
    .plus-grade-editor { padding: 12px; }
    .plus-grade-row { grid-template-columns: minmax(0, 1fr); gap: 12px; padding: 10px; }
    .plus-grade-name { gap: 6px; font-size: 0.78rem; }
}
.standard-percentage-grades {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.standard-percentage-grades caption {
    text-align: left;
    font-weight: 700;
    padding-bottom: 8px;
}
.standard-percentage-grades th,
.standard-percentage-grades td {
    padding: 10px 8px;
    text-align: left;
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.standard-percentage-grades th {
    background: rgba(var(--v-theme-primary), 0.06);
}
.maximum-plus-grading-options > .v-btn {
    min-width: 0;
    min-height: 80px;
    height: auto;
    padding: 12px;
    text-transform: none;
    letter-spacing: normal;
}
.maximum-plus-grading-options :deep(.v-btn__content) {
    white-space: normal;
    overflow-wrap: anywhere;
    line-height: 1.4;
}
.maximum-plus-grading-options :deep(.v-btn--active) {
    background: rgba(var(--v-theme-primary), 0.12);
    font-weight: 700;
}
.entry-property-type,
.entry-property-values {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}
.entry-property-type,
.entry-property-caption {
    font-size: 0.75rem;
}
.entry-property-type strong {
    color: rgb(var(--v-theme-on-surface));
}
.entry-copy-area-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.entry-copy-area-choice {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 74px;
    padding: 13px;
    border: 1px solid rgba(var(--v-border-color), 0.2);
    border-radius: 15px;
    background: rgb(var(--v-theme-surface));
    color: inherit;
    text-align: left;
    cursor: pointer;
    transition: 0.2s ease;
}
.entry-copy-area-choice:hover,
.entry-copy-area-choice-active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.09);
}
.entry-copy-area-choice > span:nth-child(2) {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}
.entry-copy-area-choice small {
    color: rgba(var(--v-theme-on-surface), 0.62);
}
.entry-copy-area-icon {
    display: grid;
    place-items: center;
    flex: 0 0 40px;
    height: 40px;
    border-radius: 12px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.11);
}
.entry-properties-combobox :deep(.v-field__input) {
    min-height: 78px;
    gap: 8px;
    padding-top: 20px;
    padding-bottom: 10px;
}
.entry-properties-combobox :deep(.v-chip) {
    height: 42px !important;
    padding-inline: 14px !important;
    font-size: 1rem;
    font-weight: 700;
}
.entry-properties-combobox :deep(.v-chip__close) {
    margin-inline-start: 8px;
    font-size: 20px;
}
.entry-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}
.dialog-header {
    padding: 22px 24px;
    border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
    font-weight: 750;
    white-space: normal;
}
.dialog-title-group {
    flex: 1;
    gap: 13px;
    min-width: 0;
}
.dialog-title-group > div {
    flex: 1;
    min-width: 0;
    overflow-wrap: normal;
    word-break: normal;
    hyphens: none;
}
.dialog-header > .v-btn {
    flex-shrink: 0;
}
.dialog-eyebrow {
    color: rgb(var(--v-theme-primary));
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.dialog-body {
    padding: 24px;
    background: rgba(var(--v-theme-primary), 0.025);
}
.dialog-actions {
    justify-content: flex-end;
    gap: 8px;
    padding: 16px 24px 22px;
}
.form-section {
    padding: 18px;
    margin-bottom: 15px;
    border: 1px solid rgba(var(--v-border-color), 0.15);
    border-radius: 16px;
    background: rgb(var(--v-theme-surface));
}
.form-heading {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 16px;
}
.choice-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
    height: auto !important;
    gap: 10px;
}
.notification-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}
.choice-card {
    display: flex !important;
    align-items: center;
    justify-content: flex-start !important;
    gap: 10px;
    min-height: 58px;
    height: auto !important;
    padding: 12px 14px !important;
    border: 1px solid rgba(var(--v-border-color), 0.2) !important;
    border-radius: 14px !important;
    text-transform: none !important;
}
.choice-selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.1) !important;
}
.table-marking-colors {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
    width: 100%;
    height: auto !important;
    gap: 8px;
}
.table-marking-color {
    display: flex !important;
    flex-direction: column;
    gap: 5px;
    min-width: 0 !important;
    min-height: 62px;
    border: 2px solid transparent !important;
    border-radius: 12px !important;
    text-transform: none !important;
}
.table-marking-color-selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08) !important;
}
.table-marking-swatch {
    width: 28px;
    height: 28px;
    border: 2px solid rgba(var(--v-theme-on-surface), 0.14);
    border-radius: 50%;
    box-shadow: 0 2px 7px rgba(20, 32, 60, 0.18);
}
.form-error {
    margin-top: 8px;
    color: rgb(var(--v-theme-error));
    font-size: 0.8rem;
}
@media (max-width: 700px) {
    .entry-section-header,
    .entry-empty-area {
        align-items: stretch;
        flex-direction: column;
    }
    .entry-section-actions {
        flex-wrap: wrap;
    }
    .entry-area-grid {
        grid-template-columns: 1fr;
    }
    .entry-name {
        min-width: 100px;
    }
    .choice-grid,
    .notification-options,
    .entry-copy-area-grid {
        grid-template-columns: 1fr;
    }
}
</style>
