<template>
    <ItsGridBox variant="overview" color="primary" :title="assignGradingPartId ? 'Zuordnung' : 'Bereiche'" icon="mdi-layers-triple-outline" class="w-100">
        <template #header-actions>
            <v-btn v-if="!assignGradingPartId" color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-shape-square-plus" :disabled="isEditing" @click="openCreateAreaDialog">Neuer Bereich</v-btn>
        </template>

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
                <section v-if="assignGradingPartId" class="calculation-area-card mt-3">
                    <div class="text-subtitle-2 mb-2">Zulässige Eintragstypen</div>
                    <v-btn-toggle v-model="assignmentEntryTypeGroups" class="maximum-plus-grading-options mb-4"
                        aria-label="Zulässige Eintragstypen" color="primary" variant="outlined" multiple :disabled="isAssigningGradingEntry">
                        <v-btn value="signs" :disabled="assignmentEntryTypeGroups.length >= 2 && !assignmentEntryTypeGroups.includes('signs')">Plus-Minus-Typen</v-btn>
                        <v-btn value="points" :disabled="assignmentEntryTypeGroups.length >= 2 && !assignmentEntryTypeGroups.includes('points')">Punktetypen</v-btn>
                        <v-btn value="grades" :disabled="assignmentEntryTypeGroups.length >= 2 && !assignmentEntryTypeGroups.includes('grades')">Notentypen</v-btn>
                    </v-btn-toggle>
                    <p class="text-caption mb-3">Eine oder zwei Typengruppen auswählen. Bestehende Zuordnungen bleiben erhalten.</p>
                    <p v-if="assignmentAllowedEntryTypes === 'all'" class="text-caption mb-3">Bisher sind alle Typen zulässig. Wählen Sie die gewünschten Gruppen, um diese Einstellung zu ändern.</p>
                    <p v-if="assignmentAllowedEntryTypes === 'none'" class="text-caption mb-3">Bitte mindestens eine Typengruppe auswählen.</p>
                    <header class="calculation-area-header">
                        <span class="calculation-area-icon"><v-icon icon="mdi-format-list-checks" size="20" /></span>
                        <div class="calculation-area-heading">
                            <div class="calculation-area-name">Zuordnung: {{ gradingPartPendingAssignment?.name }}</div>
                            <div class="text-caption text-medium-emphasis">
                                {{ assignableCalculationEntries.length }}
                                {{ assignableCalculationEntries.length === 1 ? 'Eintragstyp' : 'Eintragstypen' }}
                            </div>
                        </div>
                    </header>

                    <div class="calculation-assignment-hint">
                        <v-icon icon="mdi-cursor-default-click-outline" size="20" />
                        <span>
                            Wählen Sie alle Einträge aus, die diesem Benotungsteil zugeordnet sein sollen.
                            Bestehende Zuordnungen sind vorausgewählt.
                            <template v-if="assignmentAllowedEntryTypes === 'points'">Hier sind nur Eintragstypen mit der Eigenschaft „Punkte“ zulässig.</template>
                        </span>
                    </div>

                    <div v-if="assignableCalculationEntries.length" class="calculation-entry-selection-grid">
                        <button
                            v-for="entry in assignableCalculationEntries"
                            :key="entry.id"
                            type="button"
                            class="calculation-entry-selection-card"
                            :class="{ 'calculation-entry-selection-card--selected': selectedGradingEntryIds.includes(entry.id) }"
                            :aria-pressed="selectedGradingEntryIds.includes(entry.id)"
                            :title="!canAssignGradingEntry(entry) ? 'Für die gewählten Typengruppen nicht zulässig' : entry.name"
                            :disabled="isAssigningGradingEntry || !canAssignGradingEntry(entry)"
                            @click="toggleGradingEntrySelection(entry)">
                            <span class="calculation-entry-selection-card-header">
                                <v-chip class="calculation-entry-code" color="primary" variant="tonal" size="x-small">{{ entry.short_name }}</v-chip>
                                <v-icon
                                    :icon="!canAssignGradingEntry(entry) ? 'mdi-lock-outline' : selectedGradingEntryIds.includes(entry.id) ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'"
                                    :color="selectedGradingEntryIds.includes(entry.id) ? 'primary' : undefined"
                                    size="22" />
                            </span>
                            <div class="calculation-entry-details">
                                <span class="calculation-entry-name" :title="entry.name">{{ entry.name }}</span>
                                <span v-if="!canAssignGradingEntry(entry)" class="text-caption">Nicht zulässig für die gewählten Typengruppen</span>
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
                                        v-for="(property, propertyIndex) in calculationProperties(entry)"
                                        v-else-if="entry.has_properties"
                                        :key="property"
                                        class="calculation-entry-value calculation-property">
                                        <span class="calculation-property-name">{{ property }}</span>
                                        <span v-if="propertyEvaluationLabel(entry, property)" class="calculation-evaluation" :class="propertyEvaluationClass(entry, property)">{{ propertyEvaluationLabel(entry, property) }}</span>
                                        <span v-if="propertyIndex < calculationProperties(entry).length - 1" aria-hidden="true">,</span>
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
                    <div v-else class="text-body-2 text-medium-emphasis">{{ assignmentAllowedEntryTypes === 'none' ? 'Bitte mindestens eine Typengruppe auswählen.' : 'Keine Benotungseinträge verfügbar.' }}</div>

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

                <div v-if="!assignGradingPartId" class="calculation-overviews mt-4">
                <section v-for="simulation in [false, true]" :key="String(simulation)"
                    class="calculation-overview" :class="{ 'calculation-simulation': simulation }"
                    :aria-label="simulation ? 'Simulation' : 'Semesternote'">
                <header class="calculation-semester-grade-header">
                    <div class="text-h6 font-weight-bold">{{ simulation ? 'Simulation' : 'Semesternote' }}</div>
                    <span v-if="simulation" class="simulation-grade" aria-label="Simulationsnote gesamt">Note {{ simulationCalculatedResults.total ?? '–' }}</span>
                    <v-btn v-if="!simulation && calculationBlocks.length >= 3" color="primary" variant="tonal" prepend-icon="mdi-group" :disabled="isEditing || isLoading" @click="openGradingGroupDialog()">Gruppe bilden</v-btn>
                </header>
                <p v-if="simulation && simulationCalculatedResults.totalStep" class="text-caption text-right mt-1">{{ simulationCalculatedResults.totalStep }}</p>
                <p v-if="simulation && simulationStorageError" class="text-error text-caption mt-2" role="alert">{{ simulationStorageError }}</p>
                <div v-if="calculationAreas.length" class="calculation-area-list mt-3">
                    <template v-for="rootBlock in calculationBlocks" :key="rootBlock.id">
                    <div v-if="gradingAdjustmentForBlock(rootBlock).target" class="grading-adjustment-connection" role="img"
                        :aria-label="`${rootBlock.parts[0].name} passt ${gradingAdjustmentForBlock(rootBlock).target} an`"
                        :title="`${rootBlock.parts[0].name} passt ${gradingAdjustmentForBlock(rootBlock).target} an`">
                        <v-icon icon="mdi-link-variant" size="24" />
                    </div>
                    <GradingGroupFrame :block="rootBlock">
                    <template #header="frame">
                    <template v-for="block in [frame?.block || rootBlock]" :key="block.id">
                        <header class="calculation-group-header">
                            <div class="calculation-group-heading">
                                <div class="grading-weight-heading">
                                    <button v-if="!simulation && !isGradingAdjustmentContext(block.group?.parent_group_id)" type="button" class="grading-block-weight" :class="gradingLevelBlockWeight(block) === null ? 'grading-weight-trigger' : 'grading-group-member-weight'"
                                        :aria-label="`${block.group?.parent_group_id ? 'Gewichtung innerhalb der Gruppe' : 'Gewichtung äußere Ebene'}: ${block.group?.name || block.parts[0]?.name}`" :disabled="isEditing" @click="openGradingBlockWeightDialog(block)">
                                        <v-icon icon="mdi-weight" :size="gradingLevelBlockWeight(block) === null ? 16 : 20" color="primary" aria-hidden="true" />
                                        <strong v-if="gradingLevelBlockWeight(block) !== null">{{ gradingNumberInput(gradingLevelBlockWeight(block)) }}</strong>
                                    </button>
                                    <h3>{{ gradingLevelBlockName(block) }}</h3>
                                    <div v-if="simulation && block.group" class="simulation-result">
                                        <span class="simulation-grade" :aria-label="`Simulationsnote: ${gradingLevelBlockName(block)}`">{{ block.group ? simulationCalculatedResults.groups[block.group.id] ?? '–' : simulationSummary(block.parts[0]) }}</span>
                                        <small>{{ block.group ? simulationCalculatedResults.groupSteps[block.group.id] : simulationExplanation(block.parts[0]) }}</small>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!simulation && block.group" class="calculation-group-actions">
                                <v-btn variant="tonal" color="primary" size="small" :disabled="isEditing" @click="openGradingGroupDialog(block.group)">Gruppe bearbeiten</v-btn>
                                <v-btn variant="text" color="error" size="small" :disabled="isEditing" @click="openDissolveGradingGroupDialog(block.group)">Gruppe auflösen</v-btn>
                            </div>
                        </header>
                    </template>
                    </template>
                    <template #default="frame">
                    <template v-for="block in [frame?.block || rootBlock]" :key="block.id">
                        <div :class="[block.group ? 'calculation-group-parts' : 'calculation-area-list', { 'calculation-group-parts--adjustment-pair': block.group && block.parts.some((part) => gradingAdjustmentForPart(part).target) }]">
                    <template v-for="area in block.parts" :key="area.id">
                        <div v-if="block.group && gradingAdjustmentForPart(area).target" class="grading-adjustment-connection" role="img"
                            :aria-label="`${area.name} passt ${gradingAdjustmentForPart(area).target} an`"
                            :title="`${area.name} passt ${gradingAdjustmentForPart(area).target} an`">
                            <v-icon icon="mdi-link-variant" size="24" />
                        </div>
                    <section
                        class="calculation-area-card calculation-part-card"
                        :class="{ 'calculation-part-card--has-entries': area.entries.length }">
                        <span v-if="gradingPartCalculationIssue(area)" class="calculation-incomplete-marker" role="img"
                            :aria-label="`Berechnung unvollständig: ${gradingPartCalculationIssue(area)}`"
                            :title="gradingPartCalculationIssue(area)">!</span>
                        <header class="calculation-area-header">
                            <div class="calculation-area-heading">
                                <div class="grading-weight-heading">
                                    <button v-if="!simulation && block.group && !isGradingAdjustmentContext(block.group.id)" type="button" :class="gradingGroupMemberWeight(block.group, area) === null ? 'grading-weight-trigger' : 'grading-group-member-weight'"
                                        :aria-label="gradingGroupMemberWeight(block.group, area) === null ? `Gewichtung: ${area.name}` : `Gewichtung ${gradingNumberInput(gradingGroupMemberWeight(block.group, area))}: ${area.name}`"
                                        :disabled="isEditing" @click="openGradingGroupWeightDialog(block.group)">
                                        <v-icon icon="mdi-weight" :size="gradingGroupMemberWeight(block.group, area) === null ? 16 : 20" color="primary" aria-hidden="true" />
                                        <strong v-if="gradingGroupMemberWeight(block.group, area) !== null">{{ gradingNumberInput(gradingGroupMemberWeight(block.group, area)) }}</strong>
                                    </button>
                                    <div class="grading-title-content">
                                    <div class="calculation-area-name grading-part-title">
                                    <span>{{ gradingPartDisplayName(area) }}</span>
                                    <div v-if="simulation" class="simulation-result">
                                        <span class="simulation-grade" :aria-label="`Simulationsnote: ${area.name}`">{{ simulationSummary(area) }}</span>
                                        <small>{{ simulationExplanation(area) }}</small>
                                    </div>
                                    <v-btn v-if="!simulation" icon="mdi-pencil-outline" color="primary" variant="text" size="x-small"
                                        title="Bezeichnung ändern" aria-label="Bezeichnung ändern" :disabled="isEditing"
                                        @click="openEditGradingPartDialog(area, 'name')" />
                                    </div>
                                <div class="calculation-part-summary text-caption text-medium-emphasis">
                                    <p v-if="gradingAdjustmentForPart(area).message" class="text-error">{{ gradingAdjustmentForPart(area).message }}</p>
                                    <p v-for="line in gradingPartSummary(area, !simulation)" :key="line">{{ line }}</p>
                                </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!simulation" class="calculation-part-actions">
                                <v-btn icon="mdi-link-plus" color="primary" variant="tonal" size="x-small"
                                    title="Zuordnung" aria-label="Zuordnung" :disabled="isEditing || isAssigningGradingEntry"
                                    @click="toggleGradingEntryAssignment(area)" />
                                <v-btn
                                    icon="mdi-cog-outline"
                                    color="primary"
                                    variant="tonal"
                                    size="x-small"
                                    title="Einstellungen"
                                    aria-label="Einstellungen des Benotungsteils"
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
                                <div class="calculation-entry-item calculation-entry-static">
                                <span v-if="entryCalculationIssue(entry)" class="calculation-incomplete-marker" role="img"
                                    :aria-label="`Berechnung unvollständig: ${entryCalculationIssue(entry)}`"
                                    :title="entryCalculationIssue(entry)">!</span>
                                <v-chip v-if="entry.short_name" class="calculation-entry-code" color="primary" variant="tonal" size="x-small">{{ entry.short_name }}</v-chip>
                                <div class="calculation-entry-details">
                                    <div class="simulation-entry-heading">
                                        <span class="calculation-entry-name" :title="entry.name">{{ entry.name }}</span>
                                    </div>
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
                                            v-for="(property, propertyIndex) in calculationProperties(entry)"
                                            v-else-if="entry.has_properties"
                                            :key="property"
                                            class="calculation-entry-value calculation-property">
                                            <span class="calculation-property-name">{{ property }}</span>
                                            <span v-if="propertyEvaluationLabel(entry, property)" class="calculation-evaluation" :class="propertyEvaluationClass(entry, property)">{{ propertyEvaluationLabel(entry, property) }}</span>
                                            <span v-if="propertyIndex < calculationProperties(entry).length - 1" aria-hidden="true">,</span>
                                        </span>
                                        <span v-else class="calculation-entry-no-values">Keine zusätzlichen Werte</span>
                                        <v-btn v-if="simulation" class="simulation-entry-add" icon="mdi-plus" color="primary" variant="tonal" size="x-small"
                                            :aria-label="`Simulationseintrag hinzufügen: ${entry.short_name || entry.name}`"
                                            :disabled="simulationEntryLimitReached(entry)"
                                            :title="simulationEntryLimitReached(entry) ? 'Konfigurierte Anzahl pro Semester erreicht' : 'Simulationseintrag hinzufügen'"
                                            @click="openSimulationEntry(entry)" />
                                    </div>
                                    <ul v-if="simulation && simulationEntries[entry.id]?.length" class="simulation-entry-values" aria-label="Simulationseinträge">
                                        <li v-for="(value, index) in simulationEntries[entry.id]" :key="index">
                                            <span>{{ value }}</span>
                                            <v-btn class="simulation-entry-remove" icon="mdi-close" color="error" variant="text" size="x-small" density="compact"
                                                :aria-label="`Simulationseintrag entfernen: ${entry.short_name || entry.name}, ${value}, Eintrag ${index + 1}`"
                                                title="Simulationseintrag entfernen" @click="removeSimulationEntry(entry.id, index)" />
                                        </li>
                                    </ul>
                                </div>
                                </div>
                            </li>
                        </ul>
                    </section>
                    </template>
                        <p v-if="block.group && !block.parts.length && !block.children?.length" class="text-body-2 text-medium-emphasis">Dieser Gruppe sind noch keine Benotungsteile zugeordnet.</p>
                        </div>
                    </template>
                    </template>
                    </GradingGroupFrame>
                    </template>
                </div>

                <div v-if="!simulation" class="entry-list-actions mt-4">
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
                </section>
                </div>
            </template>

            <v-dialog v-model="gradingGroupDialogOpen" persistent max-width="660">
                <v-card rounded="xl">
                    <v-card-title>{{ gradingGroupDissolving ? 'Gruppe auflösen' : editingGradingGroupId ? 'Gruppe bearbeiten' : 'Gruppe bilden' }}</v-card-title>
                    <v-card-text>
                        <template v-if="gradingGroupDissolving"><p>Gruppe „{{ gradingGroupForm.name }}“ auflösen? Alle Benotungsteile, Einträge und Bewertungen bleiben erhalten.</p></template>
                        <template v-else>
                            <v-text-field v-model="gradingGroupForm.name" label="Gruppenname" maxlength="100" :disabled="isSavingGradingGroup" />
                            <v-checkbox v-for="item in gradingGroupSelectionItems" :key="item.key" :model-value="item.group_id ? gradingGroupForm.child_group_ids.includes(item.group_id) : gradingGroupForm.part_ids.includes(item.part_id)"
                                :label="item.name" :disabled="isSavingGradingGroup" hide-details @update:model-value="toggleGradingGroupItem(item, $event)" />
                            <p v-if="gradingGroupForm.part_ids.length + gradingGroupForm.child_group_ids.length < 2" class="text-body-2 mt-2">Mindestens zwei Bausteine auswählen.</p>
                        </template>
                        <v-alert v-if="gradingGroupError" type="error" variant="tonal" class="mt-3">{{ gradingGroupError }}</v-alert>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer />
                        <v-btn variant="text" :disabled="isSavingGradingGroup" @click="closeGradingGroupDialog">Abbrechen</v-btn>
                        <v-btn :color="gradingGroupDissolving ? 'error' : 'primary'" variant="flat" :loading="isSavingGradingGroup" :disabled="!gradingGroupDissolving && (!gradingGroupForm.name.trim() || gradingGroupForm.part_ids.length + gradingGroupForm.child_group_ids.length < 2)" @click="saveGradingGroup">{{ gradingGroupDissolving ? 'Auflösen' : 'Speichern' }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <template v-if="activeCategory !== 'Berechnung'">
                <v-list class="bg-transparent pa-0 mt-2">
                    <v-list-item v-for="entry in filteredEntries" :key="entry.id" class="entry-row mb-2">
                        <div class="entry-row-content">
                            <div class="entry-main-row">
                                <span class="entry-short">{{ entry.short_name }}</span>
                                <div class="entry-title">
                                    <span class="entry-name" :title="entry.name">{{ entry.name }}<span
                                        v-if="entry.category === 'Benotung' && entry.has_table_marking && entry.table_marking_color"
                                        class="entry-marking-dot" :style="{ backgroundColor: tableMarkingColors.find((option) => option.value === entry.table_marking_color)?.swatch }"
                                        role="img" aria-label="Markierung in der Tabelle" title="Markierung in der Tabelle" /></span>
                                    <span
                                        v-if="entry.description"
                                        class="entry-description"
                                        :title="formatEntryDescription(entry.description)">
                                        {{ formatEntryDescription(entry.description) }}
                                    </span>
                                </div>
                                <div class="entry-actions">
                                    <v-btn :icon="['Benotung', 'Verhalten', 'Weitere'].includes(entry.category) ? 'mdi-cog-outline' : 'mdi-pencil-outline'" variant="tonal" color="primary" size="small" title="Einstellungen" aria-label="Einstellungen" :disabled="isEditing" @click="openEditDialog(entry)" />
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


        <v-dialog v-model="simulationEntryDialogOpen" persistent max-width="440" aria-labelledby="simulation-entry-title">
            <v-card rounded="xl">
                <v-card-title id="simulation-entry-title" class="text-wrap">Simulationseintrag · {{ simulationEntryDefinition?.short_name || simulationEntryDefinition?.name }}</v-card-title>
                <v-card-text>
                    <p class="mb-3">{{ simulationEntryDefinition?.name }}</p>
                    <div v-if="simulationEntryOptions.length" class="d-flex flex-wrap ga-1">
                        <v-btn v-for="value in simulationEntryOptions" :key="value" size="small" color="primary"
                            :variant="simulationEntryValue === value ? 'flat' : 'tonal'"
                            :aria-pressed="simulationEntryValue === value" @click="simulationEntryValue = value">{{ value }}</v-btn>
                    </div>
                    <v-text-field v-else-if="simulationEntryMode !== 'none'" v-model="simulationEntryValue"
                        label="Wert" maxlength="50" :inputmode="simulationEntryMode === 'points' ? 'decimal' : undefined"
                        :hint="simulationEntryHint" persistent-hint :error-messages="simulationEntryValue ? simulationEntryError : ''" />
                    <p v-if="simulationEntryError && simulationEntryOptions.length" class="text-error" role="alert">{{ simulationEntryError }}</p>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeSimulationEntry">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" aria-label="Simulationseintrag übernehmen" :disabled="Boolean(simulationEntryError)" @click="addSimulationEntry">Hinzufügen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

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
                        <p v-if="calculationDialogOverallPart.points_assessment_mode === 'sum_percent'" class="text-body-1">Alle Punkte werden im Benotungsteil „{{ calculationDialogOverallPart.name }}“ addiert. Die Note folgt der festen Prozentskala ab 50 %.</p>
                        <p v-else class="text-body-1">Die Notengrenzen werden gemeinsam im Benotungsteil „{{ calculationDialogOverallPart.name }}“ festgelegt.</p>
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

        <v-dialog v-model="gradingWeightDialogOpen" persistent max-width="520" aria-labelledby="grading-weight-dialog-title">
            <v-card rounded="xl">
                <v-card-title id="grading-weight-dialog-title" class="grading-weight-title">{{ gradingWeightLevel ? 'Gewichtung der äußeren Ebene' : 'Gewichtung innerhalb der Gruppe' }}</v-card-title>
                <v-card-text>
                    <div class="font-weight-bold mb-4">{{ gradingWeightGroup?.name }}</div>
                    <div class="grading-weight-fields">
                        <div v-for="(row, index) in gradingGroupWeightRows" :key="row.group_id || row.part_id">
                            <label :for="`grading-weight-${index}`" class="text-body-2">{{ row.name }}</label>
                            <v-text-field :id="`grading-weight-${index}`" :model-value="gradingNumberInput(row.weight)" :aria-label="row.name"
                                density="compact" hide-details inputmode="decimal" variant="outlined" :disabled="isSavingGroupWeights"
                                @update:model-value="row.weight = $event" />
                        </div>
                    </div>
                    <v-alert v-if="gradingGroupWeightError || gradingGroupWeightsValidationError" type="error" variant="tonal">{{ gradingGroupWeightError || gradingGroupWeightsValidationError }}</v-alert>
                </v-card-text>
                <v-card-actions class="grading-weight-actions">
                    <v-btn color="error" variant="text" :disabled="isSavingGroupWeights" @click="saveGradingGroupWeights(true)">Gewichtungen entfernen</v-btn>
                    <v-spacer />
                    <v-btn variant="text" aria-label="Gewichtungsdialog schließen" :disabled="isSavingGroupWeights" @click="closeGradingWeightDialog">Schließen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="isSavingGroupWeights" :disabled="isSavingGroupWeights || Boolean(gradingGroupWeightsValidationError)" @click="saveGradingGroupWeights()">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="gradingPartDialogOpen" persistent scrollable max-width="520">
            <v-card tag="form" rounded="xl" @submit.prevent="saveGradingPart(gradingPartUsesPointSum)">
                <v-card-title class="dialog-header">
                    <div class="dialog-title-group">
                        <span class="dialog-icon">
                            <v-icon :icon="gradingPartDialogMode === 'name' ? 'mdi-pencil-outline' : editingGradingPartId ? 'mdi-cog-outline' : 'mdi-folder-plus-outline'" />
                        </span>
                        <div>
                            <div class="dialog-eyebrow">Berechnung</div>
                            <div>{{ gradingPartDialogMode === 'name' ? 'Bezeichnung ändern' : editingGradingPartId ? 'Einstellungen des Benotungsteils' : 'Benotungsteil hinzufügen' }}</div>
                        </div>
                    </div>
                    <v-btn icon="mdi-close" variant="text" :disabled="isSavingGradingPart" @click="closeGradingPartDialog" />
                </v-card-title>
                <v-card-text class="pt-6">
                    <v-text-field
                        v-if="gradingPartDialogMode === 'name' || !editingGradingPartId"
                        v-model="gradingPartForm.name"
                        label="Name des Benotungsteils"
                        placeholder="z. B. Mündlich"
                        variant="outlined"
                        color="primary"
                        maxlength="100"
                        autofocus
                        :error-messages="gradingPartFormErrors.name" />
                    <template v-if="gradingPartDialogMode !== 'name'">
                        <div v-if="editingGradingPartId" class="text-subtitle-1 font-weight-bold">{{ gradingPartForm.name }}</div>
                        <section v-if="gradingPartUsesPointSum" class="mt-5" aria-labelledby="grading-part-calculation-title">
                            <section v-if="gradingPartOnlyStandardGrades" class="mb-5" aria-label="Verbindlichkeit der Standardnote">
                                <h3 class="text-subtitle-2 mb-2">Verbindlichkeit</h3>
                                <v-btn-toggle v-model="gradingPartForm.is_required" mandatory selected-class="choice-selected" class="choice-grid" :disabled="isSavingGradingPart">
                                    <v-btn :value="false" class="choice-card" variant="text">Optional</v-btn>
                                    <v-btn :value="true" class="choice-card" variant="text">Verpflichtend</v-btn>
                                </v-btn-toggle>
                            </section>
                            <section v-if="gradingPartOnlyStandardGrades" class="mb-5" aria-label="Anzahl der Leistungsfeststellungen">
                                <h3 class="text-subtitle-2 mb-2">Anzahl der Leistungsfeststellungen</h3>
                                <div v-for="occurrence in gradingPartStandardOccurrences" :key="occurrence.entry_definition_id" class="mb-3">
                                    <p v-if="gradingPartStandardOccurrences.length > 1" class="text-body-2 font-weight-bold mb-2">{{ occurrence.name }}</p>
                                    <v-btn-toggle :model-value="occurrence.mode" @update:model-value="setGradingPartOccurrenceMode(occurrence, $event)" :mandatory="Boolean(occurrence.mode)" selected-class="choice-selected" class="choice-grid" :disabled="isSavingGradingPart">
                                        <v-btn value="single" class="choice-card" variant="text">Eine</v-btn>
                                        <v-btn value="fixed" class="choice-card" variant="text">Mehrere mit fester Anzahl</v-btn>
                                        <v-btn value="unlimited" class="choice-card" variant="text">Beliebig viele</v-btn>
                                    </v-btn-toggle>
                                    <v-text-field v-if="occurrence.mode === 'fixed'" v-model="occurrence.count" label="Feste Anzahl" type="number" min="2" step="1" variant="outlined" class="mt-3" :disabled="isSavingGradingPart" :error-messages="gradingPartOccurrenceError(occurrence)" />
                                </div>
                                <p v-for="error in Object.values(gradingPartFormErrors).flat()" :key="error" class="text-caption text-error" role="alert">{{ error }}</p>
                            </section>
                            <p v-if="gradingPartOnlyPlusMinus" class="text-caption mb-3">Plus und Minus werden gegengerechnet. Jedes Plus zählt +1, jedes Minus −1, eine 0 ist neutral. ~ zählt +0,5.</p>
                            <h3 id="grading-part-calculation-title" class="text-subtitle-2 mb-2">{{ gradingPartOnlyPlusMinus ? 'Zweck' : 'Notenberechnung' }}</h3>
                            <p v-if="gradingPartSingleStandardNote" class="text-caption mt-2">Für diesen Benotungsteil ist eine Standardnote vorgesehen. Die erfasste Note wird direkt übernommen. {{ gradingPartForm.is_required ? 'Bei fehlender Bewertung bleibt die Berechnung offen.' : 'Ohne Bewertung wird dieser optionale Benotungsteil ausgelassen.' }} Mehrere Bewertungen bleiben nicht auswertbar.</p>
                            <v-btn-toggle v-if="gradingPartOnlyPlusMinus" v-model="gradingPartSignPurpose" class="maximum-plus-grading-options" aria-label="Verwendung des Plus-Minus-Saldos"
                                color="primary" variant="outlined" :mandatory="Boolean(gradingPartSignPurpose)" :disabled="isSavingGradingPart">
                                <v-btn value="sign_grade">Eigene Note berechnen</v-btn>
                                <v-btn value="sign_adjust">Bestehende Note anpassen</v-btn>
                            </v-btn-toggle>
                            <v-btn-toggle v-else-if="!gradingPartSingleStandardNote" v-model="gradingPartCalculationMethod" class="maximum-plus-grading-options" aria-label="Berechnungsmethode"
                                color="primary" variant="outlined" mandatory :disabled="isSavingGradingPart">
                                <v-btn v-if="!gradingPartOnlyPlusMinus && !gradingPartOnlyStandardGrades" value="sum_percent">Alle Punkte addieren</v-btn>
                                <v-btn v-if="!gradingPartOnlyPoints && !gradingPartOnlyStandardGrades" value="plus_minus">Plus und Minus gegenrechnen</v-btn>
                                <v-btn v-if="gradingPartOnlyStandardGrades" value="grade_each">Jede Note extra rechnen</v-btn>
                                <v-btn v-if="gradingPartOnlyStandardGrades" value="grade_mean">Notendurchschnitt</v-btn>
                            </v-btn-toggle>
                            <template v-if="gradingPartOnlyPlusMinus">
                                <p v-for="error in gradingPartFormErrors.points_assessment_mode || []" :key="error" class="text-caption text-error mt-2" role="alert">{{ error }}</p>
                            </template>
                            <div v-if="gradingPartOnlyPlusMinus && gradingPartSignPurpose === 'sign_adjust'" class="text-body-2 mt-3">
                                <p class="mb-2">Positiver Netto-Saldo: Note verbessern</p>
                                <div class="d-flex flex-wrap ga-2">
                                    <v-text-field v-model="gradingPartSignAdjustment.improvement_factor" label="Verbesserungsfaktor" inputmode="decimal" variant="outlined" density="compact" class="sign-adjustment-field" :disabled="isSavingGradingPart" :error-messages="gradingPartFormErrors['sign_adjustment.improvement_factor']" />
                                    <v-text-field v-model="gradingPartSignAdjustment.max_improvement" label="Max. Verbesserung" inputmode="decimal" variant="outlined" density="compact" class="sign-adjustment-field" :disabled="isSavingGradingPart" :error-messages="gradingPartFormErrors['sign_adjustment.max_improvement']" />
                                </div>
                                <p class="mb-3">Saldo 0: Notenwert beibehalten.</p>
                                <p class="mb-2">Negativer Netto-Saldo: Note verschlechtern</p>
                                <div class="d-flex flex-wrap ga-2">
                                    <v-text-field v-model="gradingPartSignAdjustment.deterioration_factor" label="Verschlechterungsfaktor" inputmode="decimal" variant="outlined" density="compact" class="sign-adjustment-field" :disabled="isSavingGradingPart" :error-messages="gradingPartFormErrors['sign_adjustment.deterioration_factor']" />
                                    <v-text-field v-model="gradingPartSignAdjustment.max_deterioration" label="Max. Verschlechterung" inputmode="decimal" variant="outlined" density="compact" class="sign-adjustment-field" :disabled="isSavingGradingPart" :error-messages="gradingPartFormErrors['sign_adjustment.max_deterioration']" />
                                </div>
                                <p class="text-caption">Notenwertänderung = Betrag des Netto-Saldos × Faktor, höchstens das jeweilige Maximum. Alle vier Werte sind frei festzulegen. Erst anschließend kaufmännisch runden; Note 1 bis 5.</p>
                                <p v-for="error in gradingPartFormErrors.sign_adjustment || []" :key="error" class="text-caption text-error" role="alert">{{ error }}</p>
                            </div>
                            <template v-if="gradingPartOnlyStandardGrades && gradingPartCalculationMethod === 'grade_mean'">
                                <section v-for="occurrence in gradingPartStandardOccurrences.filter((item) => item.mode === 'fixed')" :key="occurrence.entry_definition_id" class="mt-4" aria-label="Gewichtung der Arbeiten">
                                    <h4 class="text-subtitle-2 mb-2">Gewichtung der Arbeiten{{ gradingPartStandardOccurrences.length > 1 ? ` – ${occurrence.name}` : '' }}</h4>
                                    <v-btn-toggle :model-value="occurrence.mean_mode" @update:model-value="setGradingPartMeanMode(occurrence, $event)" class="maximum-plus-grading-options" mandatory :disabled="isSavingGradingPart">
                                        <v-btn value="equal">Alle Arbeiten zählen gleich</v-btn>
                                        <v-btn value="weighted">Gewichtung festlegen</v-btn>
                                    </v-btn-toggle>
                                    <div v-if="occurrence.mean_mode === 'weighted'" class="mt-3">
                                        <v-text-field v-for="index in gradingPartWeightSlots(occurrence)" :key="index" v-model="occurrence.mean_weights[index]" :label="`Arbeit ${index + 1}`" suffix="%" type="text" inputmode="decimal" variant="outlined" :disabled="isSavingGradingPart" />
                                        <p class="text-body-2">Summe: {{ gradingPartWeightTotal(occurrence) }} % · erforderlich: 100 %</p>
                                        <p v-if="gradingPartMeanError(occurrence)" class="text-error text-caption" role="alert">{{ gradingPartMeanError(occurrence) }}</p>
                                    </div>
                                </section>
                            </template>
                            <div v-if="gradingPartOnlyPlusMinus && gradingPartSignPurpose === 'sign_grade'" class="mt-4">
                                <p class="text-caption mb-3">Mindest-Saldo für jede Note. Die Grenzen müssen von Genügend bis Sehr gut streng steigen. Unter der Genügend-Grenze gilt Nicht genügend (5); genau ab einer Grenze gilt bereits die zugehörige Note.</p>
                                <v-text-field v-for="item in [{ grade: 4, label: 'Genügend (4)' }, { grade: 3, label: 'Befriedigend (3)' }, { grade: 2, label: 'Gut (2)' }, { grade: 1, label: 'Sehr gut (1)' }]"
                                    :key="item.grade" v-model="gradingPartSignThresholds[item.grade]" :label="`${item.label} – ab Saldo`" type="number" step="1" variant="outlined"
                                    :disabled="isSavingGradingPart" :error-messages="gradingPartFormErrors[`sign_grade_thresholds.${item.grade}`]" />
                                <p v-if="gradingPartSignThresholdError" class="text-caption text-error" role="alert">{{ gradingPartSignThresholdError }}</p>
                                <p v-for="error in gradingPartFormErrors.sign_grade_thresholds || []" :key="error" class="text-caption text-error" role="alert">{{ error }}</p>
                            </div>
                            <template v-else-if="gradingPartOnlyPlusMinus">
                                <p v-if="gradingPartSignPurpose !== 'sign_adjust'" class="text-caption mt-2">Regel wird noch festgelegt. Bis dahin wird ausschließlich der Saldo angezeigt; keine Note wird berechnet oder angepasst.</p>
                            </template>
                            <p v-else-if="gradingPartCalculationMethod === 'plus_minus'" class="text-caption mt-2">Jedes Plus zählt +1, jedes Minus −1; 0 zählt neutral. Die Umrechnung des Saldos in eine Note wird noch festgelegt.</p>
                            <template v-else-if="!gradingPartSingleStandardNote && !gradingPartOnlyStandardGrades">
                            <p class="text-caption mt-2">Erreichte und mögliche Punkte aller erfassten Punktebewertungen werden jeweils addiert. Andere Eintragstypen fließen in diese Methode nicht ein. Ab 50 % ist die Beurteilung positiv; 50 bis 100 % verteilen sich gleichmäßig auf die vier positiven Noten.</p>
                            <dl class="point-sum-grade-scale text-caption mt-3">
                                <div><dt>87,5–100 %</dt><dd>Sehr gut (1)</dd></div>
                                <div><dt>75 bis unter 87,5 %</dt><dd>Gut (2)</dd></div>
                                <div><dt>62,5 bis unter 75 %</dt><dd>Befriedigend (3)</dd></div>
                                <div><dt>50 bis unter 62,5 %</dt><dd>Genügend (4)</dd></div>
                                <div><dt>Unter 50 %</dt><dd>Nicht genügend (5)</dd></div>
                            </dl>
                            </template>
                        </section>
                    </template>
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" :disabled="isSavingGradingPart" @click="closeGradingPartDialog">Abbrechen</v-btn>
                    <v-btn
                        type="submit"
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        :loading="isSavingGradingPart"
                        :disabled="!gradingPartForm.name.trim() || (gradingPartDialogMode !== 'name' && !gradingPartUsesPointSum && (!gradingPartWeightValid || Boolean(gradingPartOverallThresholdError)))">
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
                            <strong>Ganzen Tag bei Verwendung dieses Eintrags markieren</strong>
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
                                    <v-btn value="plus_minus" class="choice-card" variant="text">
                                        <v-icon icon="mdi-plus-minus" />
                                        <span>Plus und Minus</span>
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
                                    Mehrere Pluszeichen (+, ++, +++, …), Minuszeichen (-, --, ---, …), 0 (neutral) oder ~ (+0,5) eingeben.
                                </p>
                                <p v-else-if="entryForm.properties_mode === 'plus'" class="text-body-2 mt-3 mb-0">
                                    Bestehender Typ „Nur Plus“: Nur Pluszeichen (+, ++, +++, …) eingeben. Der gespeicherte Typ bleibt erhalten, solange keine andere Eigenschaft ausgewählt wird.
                                </p>
                                <p v-else-if="selectedPropertyMode === 'free'" class="text-body-2 mt-3 mb-0">
                                    Bestehender Typ „Freie Eingabe“: Eigenschaften und Werte können weiter bearbeitet werden. Der gespeicherte Typ bleibt erhalten, solange keine andere Eigenschaft ausgewählt wird.
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
import GradingGroupFrame from './GradingGroupFrame.vue'
import { standardPercentageGrades } from '@/helpers/gradeCalculation'
import { isStoredSimulationValue, simulationEntryGrade, simulationPartGrade, simulationPartSummary, simulationPartExplanation, simulationStructure } from '@/helpers/gradingSimulation'
import { gradingAdjustmentState, gradingAdjustmentChangeError } from '@/helpers/gradingAdjustmentStructure'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

function isAdjustmentContext(component, context = null) {
    const parts = (component.gradingParts || []).filter((part) => part.teaching_entry_area_id === component.activeAreaId)
    return Object.values(gradingAdjustmentState(component.gradingGroups || [], parts)).some((state) => state.context === (context ?? 'root'))
}

const specialPropertyOptions = [
    { value: 'NA', label: 'NA – Nicht angetreten' },
    { value: 'VL', label: 'VL – Vorgetäuschte Leistung' },
    { value: 'F', label: 'F – Gefehlt' },
]

function gradingPartAllowsEntry(gradingPart, entry) {
    const mode = gradingPart?.allowed_entry_types ?? 'all'
    return mode === 'all' || entryTypeGroups(mode).includes(entryTypeGroup(entry))
}

function entryTypeGroup(entry) {
    if (entry.properties_mode === 'points') return 'points'
    if (['plus', 'plus_minus'].includes(entry.properties_mode)) return 'signs'
    const properties = entry.fixed_properties || []
    return properties.some((value) => typeof value === 'string' && /^(?:[+\-−]+|~)$/u.test(value.trim()))
        && properties.every((value) => typeof value === 'string' && (value.trim() === '0' || /^(?:[+\-−]+|~)$/u.test(value.trim()))) ? 'signs' : 'grades'
}

function entryTypeGroups(mode) {
    return { non_points: ['signs', 'grades'], signs_note: ['signs', 'grades'], signs_pts: ['signs', 'points'], pts_notes: ['points', 'grades'], none: [], all: [] }[mode] || [mode]
}

function entryPartWeightError(value) {
    const weight = parseGradingNumber(value)
    return Number.isInteger(weight) && weight >= 1 && weight <= 9999999 && /^\d+$/.test(String(value).trim())
        ? '' : 'Bitte eine positive ganze Zahl als Gewichtung eingeben.'
}

function standardGradeMeanError(occurrence) {
    if (occurrence.mean_mode !== 'weighted') return ''
    const count = Number(occurrence.count)
    if (!Number.isSafeInteger(count) || count < 2 || count > 1000) return 'Für die Prozentgewichtung sind 2 bis 1000 Arbeiten möglich.'
    const values = Array.from({ length: count }, (_, index) => occurrence.mean_weights[index])
    if (values.some((value) => value === null || value === undefined || String(value).trim() === '')) return 'Bitte für jede Arbeit einen Prozentwert eingeben.'
    const weights = values.map(parseGradingNumber)
    if (weights.some((weight, index) => !Number.isFinite(weight) || weight < 0 || weight > 100 || !/^\d+(?:[.,]\d{1,3})?$/.test(String(values[index]).trim()))) return 'Bitte endliche Prozentwerte von 0 bis 100 mit höchstens drei Nachkommastellen eingeben.'
    return weights.reduce((sum, weight) => sum + Math.round(weight * 1000), 0) === 100000 ? '' : 'Die Gewichte der Arbeiten müssen zusammen genau 100 % ergeben.'
}

function standardGradeOccurrenceConfiguration(occurrence, method) {
    const configuration = { mode: occurrence.mode, count: occurrence.mode === 'fixed' ? Number(occurrence.count) : null }
    if (occurrence.mode !== 'fixed') return configuration
    if (method === 'grade_mean') {
        configuration.mean = { mode: occurrence.mean_mode }
        if (occurrence.mean_mode === 'weighted' || (occurrence.mean_weights.length && !standardGradeMeanError({ ...occurrence, mean_mode: 'weighted' }))) {
            configuration.mean.weights = Array.from({ length: Number(occurrence.count) }, (_, index) => parseGradingNumber(occurrence.mean_weights[index]))
        }
    } else if (occurrence.saved_count === configuration.count && occurrence.saved_mean) configuration.mean = occurrence.saved_mean
    return configuration
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

function displaySignValue(value) {
    const normalized = String(value).trim().replaceAll('−', '-')
    if (normalized === '0') return 0
    if (/^\++$/.test(normalized)) return normalized.length
    if (/^-+$/.test(normalized)) return -normalized.length
    return null
}

function sortedCalculationProperties(properties) {
    const signs = properties.filter((property) => displaySignValue(property) !== null)
        .sort((left, right) => displaySignValue(right) - displaySignValue(left))
    let signIndex = 0
    return properties.map((property) => displaySignValue(property) === null ? property : signs[signIndex++])
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
    const pointSumPart = gradingParts.find((part) => part.id === entry.teaching_entry_grading_part_id && part.points_assessment_mode === 'sum_percent')
    if (pointSumPart) {
        if (entry.properties_mode !== 'points') return ''
        const maximum = parseGradingNumber(entry.maximum_points)
        return Number.isFinite(maximum) && maximum > 0 ? '' : 'Bitte unter Benotung eine gültige maximale Punktzahl festlegen.'
    }
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
    components: { ItsGridBox, GradingGroupFrame },

    data() {
        return {
            areas: [],
            entries: [],
            gradingParts: [],
            simulationEntries: {},
            simulationEntryDialogOpen: false,
            simulationEntryId: null,
            simulationEntryValue: '',
            simulationStorageError: '',
            simulationAdminStore: null,
            assignmentAllowedEntryTypes: null,
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
            gradingPartCalculationMethod: 'sum_percent',
            gradingPartOnlyPlusMinus: false,
            gradingPartSignPurpose: null,
            gradingPartSignAdjustment: { improvement_factor: null, max_improvement: null, deterioration_factor: null, max_deterioration: null },
            gradingPartSignThresholds: { 1: null, 2: null, 3: null, 4: null },
            gradingPartOnlyPoints: false,
            gradingPartOnlyStandardGrades: false,
            gradingPartSingleStandardNote: false,
            gradingPartStandardOccurrences: [],
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
            standardPercentageGrades,
            editDialogOpen: false,
            deleteDialogOpen: false,
            areaDialogOpen: false,
            areaDeleteDialogOpen: false,
            gradingPartDialogOpen: false,
            gradingWeightDialogOpen: false,
            gradingWeightGroup: null,
            gradingWeightLevel: false,
            gradingGroupWeightRows: [],
            gradingGroupWeightError: '',
            isSavingGroupWeights: false,
            gradingGroupDialogOpen: false,
            gradingGroupDissolving: false,
            editingGradingGroupId: null,
            gradingGroupForm: { name: '', part_ids: [], child_group_ids: [] },
            gradingGroupError: '',
            isSavingGradingGroup: false,
            gradingPartDialogMode: 'settings',
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
                properties_mode: 'fixed',
                maximum_points: null,
                fixed_properties: ['1', '2', '3', '4', '5'],
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
        simulationStorageKey() {
            const config = this.simulationAdminStore?.config
            const context = [config?.user?.id, config?.selected_school?.id, config?.selected_schoolyear?.id, this.activeAreaId]
            return context.every((id) => id !== null && id !== undefined && String(id) !== '')
                ? `schooltool:grading-simulation:v1:${context.map(encodeURIComponent).join(':')}` : null
        },
        simulationEntryDefinition() {
            return this.entries.find((entry) => entry.id === this.simulationEntryId
                && entry.teaching_entry_area_id === this.activeAreaId
                && this.calculationAreas.some((part) => part.entries.some((assigned) => assigned.id === entry.id))) || null
        },
        simulationEntryMode() {
            const entry = this.simulationEntryDefinition
            return entry?.has_properties ? entry.properties_mode : 'none'
        },
        simulationEntryOptions() {
            if (this.simulationEntryMode === 'grades') return ['1', '2', '3', '4', '5']
            return this.simulationEntryDefinition && ['fixed', 'free'].includes(this.simulationEntryMode)
                ? entryCalculationProperties(this.simulationEntryDefinition).map(String) : []
        },
        simulationEntryHint() {
            if (this.simulationEntryMode === 'points') return `Punkte von 0 bis ${this.simulationEntryDefinition?.maximum_points}; Dezimalstellen sind möglich.`
            if (this.simulationEntryMode === 'plus') return 'Nur Pluszeichen, z. B. +, ++, +++ (max. 50).'
            if (this.simulationEntryMode === 'plus_minus') return 'Pluszeichen, Minuszeichen, 0 oder ~, z. B. +++, --, 0, ~ (max. 50).'
            return ''
        },
        simulationEntryError() {
            if (!this.simulationEntryDefinition) return 'Dieser Eintragstyp ist hier nicht mehr verfügbar.'
            if (this.simulationEntryLimitReached(this.simulationEntryDefinition)) return 'Die konfigurierte Anzahl pro Semester ist erreicht.'
            if (this.simulationEntryMode === 'none') return ''
            const selectedValue = String(this.simulationEntryValue ?? '').trim()
            const value = selectedValue.replaceAll('−', '-')
            if (!value) return 'Bitte einen Wert eingeben.'
            if (this.simulationEntryOptions.length) return this.simulationEntryOptions.includes(selectedValue) ? '' : 'Bitte einen vorhandenen Wert auswählen.'
            if (this.simulationEntryMode === 'points') {
                const maximum = parseGradingNumber(this.simulationEntryDefinition.maximum_points)
                const points = parseGradingNumber(value)
                return /^\d+(?:[.,]\d+)?$/.test(value) && value.length <= 50 && Number.isFinite(maximum)
                    && maximum > 0 && Number.isFinite(points) && points >= 0 && points <= maximum ? '' : this.simulationEntryHint
            }
            if (['plus', 'plus_minus'].includes(this.simulationEntryMode)) {
                const pattern = this.simulationEntryMode === 'plus' ? /^\+{1,50}$/ : /^(?:\+{1,50}|-{1,50}|0|~)$/
                return pattern.test(value) ? '' : this.simulationEntryHint
            }
            return value.length <= 50 ? '' : 'Bitte höchstens 50 Zeichen eingeben.'
        },
        gradingPartUsesPointSum() {
            return Boolean(this.editingGradingPartId) && this.gradingPartDialogMode !== 'name'
        },
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
            if (this.gradingParts?.some((part) => part.id === this.calculationDialogEntry?.teaching_entry_grading_part_id && part.points_assessment_mode === 'sum_percent')) return false
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
        gradingPartSignThresholdError() {
            if (!this.gradingPartOnlyPlusMinus || this.gradingPartSignPurpose !== 'sign_grade') return ''
            const values = [4, 3, 2, 1].map((grade) => this.gradingPartSignThresholds[grade])
            if (values.some((value) => value === null || value === undefined || String(value).trim() === '')) return 'Bitte alle vier Saldo-Grenzen eingeben.'
            if (values.some((value) => !/^[+-]?\d+$/.test(String(value).trim()) || !Number.isSafeInteger(Number(value)))) return 'Bitte endliche ganze Saldo-Zahlen eingeben.'
            if (values.some((value, index) => index > 0 && Number(value) <= Number(values[index - 1]))) return 'Befriedigend braucht mehr Saldo als Genügend, Gut mehr als Befriedigend und Sehr gut mehr als Gut.'
            return ''
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
                && (part.points_assessment_mode === 'sum_percent' || (part.allowed_entry_types === 'points' && part.points_assessment_mode === 'overall'))) || null
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
        gradingGroupWeightsValidationError() {
            return !this.gradingGroupWeightRows.length || this.gradingGroupWeightRows.some((row) => !Number.isFinite(parseGradingNumber(row.weight)) || parseGradingNumber(row.weight) <= 0)
                ? 'Bitte für jeden Benotungsteil eine endliche positive Gewichtung eingeben.' : ''
        },
        activeEdit() {
            if (this.gradingWeightDialogOpen || this.gradingGroupDialogOpen || this.isSavingGradingGroup || this.calculationDialogOpen || this.editDialogOpen || this.deleteDialogOpen || this.areaDialogOpen
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
        assignableCalculationEntries() {
            return this.calculationEntries
        },
        assignmentEntryTypeGroups: {
            get() {
                const mode = this.assignmentAllowedEntryTypes ?? this.gradingPartPendingAssignment?.allowed_entry_types ?? 'all'
                return entryTypeGroups(mode)
            },
            set(groups) {
                if (groups.length > 2) return
                const ordered = ['signs', 'points', 'grades'].filter((group) => groups.includes(group))
                this.assignmentAllowedEntryTypes = ordered.length === 2
                    ? { 'signs,points': 'signs_pts', 'signs,grades': 'signs_note', 'points,grades': 'pts_notes' }[ordered.join(',')]
                    : ordered[0] || 'none'
            },
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
        gradingGroups() {
            return this.areas.find((area) => area.id === this.activeAreaId)?.grading_part_groups || []
        },
        simulationCalculatedResults() {
            return simulationStructure(this.calculationAreas, this.gradingGroups, this.areas.find((area) => area.id === this.activeAreaId)?.grading_level_weights, this.simulationEntries)
        },
        gradingAdjustmentStates() {
            return gradingAdjustmentState(this.gradingGroups, this.gradingParts.filter((part) => part.teaching_entry_area_id === this.activeAreaId))
        },
        calculationBlocks() {
            const makeBlock = (group, visited = []) => ({ id: group.id, group,
                  parts: this.calculationAreas.filter((part) => group.part_ids.includes(part.gradingPartId))
                      .sort((left, right) => Number(Boolean(this.gradingAdjustmentStates?.[left.gradingPartId]?.target)) - Number(Boolean(this.gradingAdjustmentStates?.[right.gradingPartId]?.target))),
                children: this.gradingGroups.filter((child) => child.parent_group_id === group.id && !visited.includes(child.id))
                    .map((child) => makeBlock(child, [...visited, group.id])),
            })
            const blocks = this.gradingGroups.filter((group) => !group.parent_group_id).map((group) => makeBlock(group))
            const assigned = new Set(this.gradingGroups.flatMap((group) => group.part_ids))
            const ungrouped = this.calculationAreas.filter((part) => !assigned.has(part.gradingPartId))
            for (const part of ungrouped) blocks.push({ id: `ungrouped-${part.gradingPartId}`, group: null, parts: [part] })
            return blocks.sort((left, right) => Number(Boolean(!left.group && this.gradingAdjustmentStates?.[left.parts[0]?.gradingPartId]?.target))
                - Number(Boolean(!right.group && this.gradingAdjustmentStates?.[right.parts[0]?.gradingPartId]?.target)))
        },
        gradingGroupSelectionItems() {
            const editing = this.gradingGroups.find((group) => group.id === this.editingGradingGroupId)
            const parentId = editing?.parent_group_id ?? null
            const eligibleGroups = this.gradingGroups.filter((group) => group.id !== this.editingGradingGroupId
                && ((group.parent_group_id ?? null) === parentId || group.parent_group_id === this.editingGradingGroupId))
            const eligibleParts = this.calculationAreas.filter((part) => {
                const owner = this.gradingGroups.find((group) => group.part_ids.includes(part.gradingPartId))
                return (owner?.id ?? null) === parentId || owner?.id === this.editingGradingGroupId
            })
            return [...eligibleGroups.map((group) => ({ key: `group:${group.id}`, group_id: group.id, name: group.name })),
                ...eligibleParts.map((part) => ({ key: `part:${part.gradingPartId}`, part_id: part.gradingPartId, name: part.name }))]
        },
        hasGradingEntryAssignmentChanges() {
            if (this.assignmentAllowedEntryTypes === 'none') return false
            if (!this.assignGradingPartId || this.isAssigningGradingEntry) return false
            if (this.assignmentAllowedEntryTypes && this.assignmentAllowedEntryTypes !== (this.gradingPartPendingAssignment?.allowed_entry_types ?? 'all')) return true

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
        simulationStorageKey(key, previousKey) {
            if (!key && !previousKey) return
            this.closeSimulationEntry()
            this.restoreSimulationEntries()
        },
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
        this.simulationAdminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.restoreCategoryFromRoute()
        this.loadData()
    },

    methods: {
        simulationEntryGrade,
        simulationPartGrade,
        simulationSummary(part) {
            return simulationPartSummary(part, this.simulationEntries, this.simulationCalculatedResults.parts[part.gradingPartId ?? part.id])
        },
        simulationExplanation(part) {
            return this.simulationCalculatedResults.partSteps[part.gradingPartId ?? part.id] || simulationPartExplanation(part, this.simulationEntries)
        },
        restoreSimulationEntries() {
            this.simulationEntries = {}
            this.simulationStorageError = ''
            if (!this.simulationStorageKey) return
            try {
                const saved = JSON.parse(localStorage.getItem(this.simulationStorageKey) || '{}')
                if (!saved || typeof saved !== 'object' || Array.isArray(saved)) return
                const restored = {}
                for (const entry of this.entries.filter((entry) => entry.category === 'Benotung' && entry.teaching_entry_area_id === this.activeAreaId)) {
                    const values = saved[entry.id]
                    if (!Array.isArray(values)) continue
                    restored[entry.id] = values.filter((value) => isStoredSimulationValue(entry, value))
                }
                this.simulationEntries = restored
            } catch (error) {
                if (!(error instanceof SyntaxError)) this.simulationStorageError = 'Die Simulation konnte nicht aus dem Browser geladen werden.'
            }
        },
        persistSimulationEntries() {
            if (!this.simulationStorageKey) return
            try {
                const saved = {}
                for (const entry of this.entries.filter((entry) => entry.category === 'Benotung' && entry.teaching_entry_area_id === this.activeAreaId)) {
                    if (this.simulationEntries[entry.id]) saved[entry.id] = this.simulationEntries[entry.id]
                }
                localStorage.setItem(this.simulationStorageKey, JSON.stringify(saved))
                this.simulationStorageError = ''
            } catch {
                this.simulationStorageError = 'Die Simulation konnte nicht im Browser gespeichert werden.'
            }
        },
        simulationEntryLimit(entry) {
            if (!entry?.has_properties || entryPropertyMode(entry) !== 'grades') return null
            const configuration = entry.standard_grade_occurrences
            if (configuration?.mode === 'single') return 1
            const count = Number(configuration?.count)
            return configuration?.mode === 'fixed' && Number.isSafeInteger(count) && count >= 2 ? count : null
        },
        simulationEntryLimitReached(entry) {
            const limit = this.simulationEntryLimit(entry)
            return limit !== null && (this.simulationEntries[entry.id]?.length || 0) >= limit
        },
        openSimulationEntry(entry) {
            if (this.simulationEntryLimitReached(entry)) return
            this.simulationEntryId = entry.id
            this.simulationEntryValue = ''
            this.simulationEntryDialogOpen = true
        },
        closeSimulationEntry() {
            this.simulationEntryDialogOpen = false
            this.simulationEntryId = null
            this.simulationEntryValue = ''
        },
        addSimulationEntry() {
            if (!this.simulationEntryDialogOpen || this.simulationEntryError) return
            const id = this.simulationEntryDefinition.id
            const value = this.simulationEntryMode === 'none' ? 'Eintrag'
                : String(this.simulationEntryValue).trim()
            this.simulationEntries[id] = [...(this.simulationEntries[id] || []), value]
            this.persistSimulationEntries()
            this.closeSimulationEntry()
        },
        removeSimulationEntry(entryId, index) {
            this.simulationEntries[entryId] = (this.simulationEntries[entryId] || [])
                .filter((value, valueIndex) => valueIndex !== index)
            this.persistSimulationEntries()
        },
        isGradingAdjustmentContext(context = null) {
            return isAdjustmentContext(this, context)
        },
        gradingGroupMemberWeight(group, part) {
            if (isAdjustmentContext(this, group.id)) return null
            return group.weights?.find((item) => item.part_id === part.gradingPartId)?.weight ?? null
        },
        gradingLevelBlockWeight(block) {
            if (isAdjustmentContext(this, block.group?.parent_group_id)) return null
            if (block.group?.parent_group_id) {
                return this.gradingGroups.find((group) => group.id === block.group.parent_group_id)?.weights?.find((row) => row.group_id === block.group.id)?.weight ?? null
            }
            const weights = this.areas.find((area) => area.id === this.activeAreaId)?.grading_level_weights
            return weights?.find((row) => block.group ? row.group_id === block.group.id : row.part_id === block.parts[0]?.gradingPartId)?.weight ?? null
        },
        openGradingBlockWeightDialog(block) {
            const parent = this.gradingGroups.find((group) => group.id === block.group?.parent_group_id)
            if (parent) this.openGradingGroupWeightDialog(parent)
            else this.openGradingLevelWeightDialog()
        },
        gradingLevelBlockName(block) {
            if (block.group) return block.group.name
            const name = block.parts[0]?.name || ''
            const label = /note$/i.test(name.trim()) ? name : `${name}-Note`
            return block.parts[0]?.is_required === false && !/\(optional\)/i.test(name) ? `${label} (optional)` : label
        },
        openGradingLevelWeightDialog() {
            if (isAdjustmentContext(this)) return
            this.gradingWeightLevel = true
            this.gradingWeightGroup = { name: 'Semesternote' }
            this.gradingGroupWeightRows = this.calculationBlocks.map((block) => ({
                ...(block.group ? { group_id: block.group.id } : { part_id: block.parts[0].gradingPartId }),
                name: this.gradingLevelBlockName(block), weight: this.gradingLevelBlockWeight(block) ?? 1,
            }))
            this.gradingGroupWeightError = ''
            this.gradingWeightDialogOpen = true
        },
        openGradingGroupWeightDialog(group) {
            if (isAdjustmentContext(this, group.id)) return
            const saved = this.gradingGroups.find((candidate) => candidate.id === group.id)
            if (!saved) return
            this.gradingWeightLevel = false
            this.gradingWeightGroup = { id: saved.id, name: saved.name }
            this.gradingGroupWeightRows = this.calculationAreas.filter((part) => saved.part_ids.includes(part.gradingPartId))
                .map((part) => ({ part_id: part.gradingPartId, name: part.name, weight: this.gradingGroupMemberWeight(saved, part) ?? 1 }))
            this.gradingGroupWeightRows.push(...this.gradingGroups.filter((child) => child.parent_group_id === saved.id)
                .map((child) => ({ group_id: child.id, name: child.name, weight: saved.weights?.find((row) => row.group_id === child.id)?.weight ?? 1 })))
            this.gradingGroupWeightError = ''
            this.gradingWeightDialogOpen = true
        },
        closeGradingWeightDialog() {
            this.gradingWeightDialogOpen = false
            this.gradingWeightGroup = null
            this.gradingWeightLevel = false
            this.gradingGroupWeightRows = []
            this.gradingGroupWeightError = ''
        },
        async saveGradingGroupWeights(remove = false) {
            if (this.isSavingGroupWeights || !this.gradingWeightGroup || (!remove && this.gradingGroupWeightsValidationError)) return
            const area = this.areas.find((area) => area.id === this.activeAreaId)
            if (!area) return
            if (isAdjustmentContext(this, this.gradingWeightLevel ? null : this.gradingWeightGroup.id)) {
                this.gradingGroupWeightError = 'Diese Ebene verwendet eine Notenanpassung und wird nicht gewichtet.'
                return
            }
            this.isSavingGroupWeights = true
            this.gradingGroupWeightError = ''
            try {
                const weights = remove ? null : this.gradingGroupWeightRows.map((row) => ({
                    ...(row.group_id ? { group_id: row.group_id } : { part_id: row.part_id }), weight: parseGradingNumber(row.weight),
                }))
                const response = await axios.put(updateEntryArea.url(area.id), { name: area.name,
                    ...(this.gradingWeightLevel ? { grading_level_weights: weights } : { grading_group_weights: { group_id: this.gradingWeightGroup.id, weights } }),
                })
                const index = this.areas.findIndex((candidate) => candidate.id === area.id)
                if (index !== -1) this.areas.splice(index, 1, response.data.data)
                this.closeGradingWeightDialog()
            } catch (error) {
                this.gradingGroupWeightError = Object.values(error?.response?.data?.errors || {}).flat().join(' ') || error?.response?.data?.message || 'Die Gewichtungen konnten nicht gespeichert werden.'
            } finally {
                this.isSavingGroupWeights = false
            }
        },
        gradingPartDisplayName(part) {
            return part.is_required === false && !/\(optional\)\s*$/i.test(part.name) ? `${part.name} (optional)` : part.name
        },
        gradingPartSummary(part, showWeights = true) {
            const entries = part.entries || []
            const method = part.points_assessment_mode
            if (!showWeights && (['sum_percent', 'overall', 'sign_adjust'].includes(method)
                || (entries.length && entries.every((entry) => entry.has_properties && entryPropertyMode(entry) === 'grades')))) return []
            if (entries.length && entries.every((entry) => entry.has_properties && entryPropertyMode(entry) === 'grades')) {
                const lines = entries.map((entry) => {
                    const configuration = entry.standard_grade_occurrences
                    const names = { Schularbeit: ['Schularbeit', 'Schularbeiten'], Prüfung: ['Prüfung', 'Prüfungen'] }[entry.name] || [`Leistungsfeststellung (${entry.name})`, `Leistungsfeststellungen (${entry.name})`]
                    if (!configuration) return `Anzahl für ${entry.name} noch nicht festgelegt.`
                    if (configuration.mode === 'single') return `Eine ${names[0]} pro Semester.`
                    let label = configuration.mode === 'unlimited' ? `Beliebig viele ${names[1]} pro Semester.`
                        : `${{ 2: 'Zwei', 3: 'Drei', 4: 'Vier', 5: 'Fünf' }[configuration.count] || configuration.count} ${names[1]} pro Semester.`
                    if (method === 'grade_each') label += ' Jede Note wird extra berechnet; die Verknüpfungsregel ist noch offen.'
                    else if (method === 'grade_mean') label += !showWeights ? ' Notendurchschnitt.' : configuration.mode === 'fixed' && configuration.mean
                        ? ` Notendurchschnitt · ${configuration.mean.mode === 'weighted' ? `gewichtet (${configuration.mean.weights.map((weight) => `${Number(weight).toLocaleString('de-AT')} %`).join(' / ')})` : 'alle Arbeiten zählen gleich'}.`
                        : ' Notendurchschnitt; Gewichtungsregel noch offen.'
                    else label += ' Berechnungsmethode noch nicht festgelegt.'
                    return label
                })
                if (entries.length > 1) lines.push('Die Verknüpfung dieser Eintragstypen zu einer Teilnote ist noch festzulegen.')
                return lines
            }
            if (['sum_percent', 'overall'].includes(method)) return showWeights ? ['Benotung aufgrund der addierten Punkte.'] : []
            if (method === 'sign_grade') return [part.sign_grade_thresholds ? 'Eigene Note aus dem Plus-Minus-Saldo anhand der gespeicherten Saldo-Grenzen.' : 'Eigene Note aus dem Plus-Minus-Saldo; die Saldo-Grenzen fehlen noch.']
            if (method === 'sign_adjust') return [part.sign_adjustment && Object.values(part.sign_adjustment).every((value) => value !== null && value !== '')
                ? 'Bestehende Note anhand des Netto-Saldos, der Faktoren und maximalen Notenwertänderungen anpassen.'
                : 'Bestehende Note anhand des Plus-Minus-Saldos anpassen; die Anpassungsregel ist noch offen.']
            if (method === 'plus_minus') return ['Plus und Minus gegenrechnen; die Umrechnung in eine Note ist noch offen.']
            return entries.length ? ['Beurteilung nach den gespeicherten Einzelbewertungen.'] : ['Noch keine Eintragstypen zugeordnet.']
        },
        setGradingPartOccurrenceMode(occurrence, mode) {
            occurrence.mode = mode
            if (mode === 'fixed' && (occurrence.count === null || occurrence.count === undefined || occurrence.count === '')) occurrence.count = 2
            this.gradingPartSingleStandardNote = this.gradingPartOnlyStandardGrades && this.gradingPartStandardOccurrences.length === 1 && mode === 'single'
        },
        gradingPartOccurrenceError(occurrence) {
            if (occurrence.mode !== 'fixed') return ''
            return occurrence.count !== null && String(occurrence.count).trim() !== '' && Number.isSafeInteger(Number(occurrence.count)) && Number(occurrence.count) >= 2
                ? '' : 'Bitte eine ganze Anzahl ab 2 eingeben.'
        },
        gradingPartWeightSlots(occurrence) {
            const count = Number(occurrence.count)
            return Number.isSafeInteger(count) && count >= 2 && count <= 1000 ? Array.from({ length: count }, (_, index) => index) : []
        },
        setGradingPartMeanMode(occurrence, mode) {
            occurrence.mean_mode = mode
            const count = Number(occurrence.count)
            if (mode !== 'weighted' || !Number.isSafeInteger(count) || count < 2 || count > 1000 || occurrence.mean_weights.some((weight) => weight !== null && weight !== undefined && String(weight).trim() !== '')) return
            const base = Math.floor(10000 / count)
            const remainder = 10000 % count
            occurrence.mean_weights = Array.from({ length: count }, (_, index) => (base + (index >= count - remainder ? 1 : 0)) / 100)
        },
        gradingPartWeightTotal(occurrence) {
            const weights = this.gradingPartWeightSlots(occurrence).map((index) => parseGradingNumber(occurrence.mean_weights[index]))
            return weights.length && weights.every(Number.isFinite) ? (weights.reduce((sum, weight) => sum + Math.round(weight * 1000), 0) / 1000).toLocaleString('de-AT') : '–'
        },
        gradingPartMeanError(occurrence) {
            return standardGradeMeanError(occurrence)
        },
        openGradingGroupDialog(group = null) {
            this.editingGradingGroupId = group?.id ?? null
            this.gradingGroupDissolving = false
            this.gradingGroupForm = { name: group?.name ?? 'Basisgruppe', part_ids: [...(group?.part_ids ?? [])], child_group_ids: group ? this.gradingGroups.filter((child) => child.parent_group_id === group.id).map((child) => child.id) : [] }
            this.gradingGroupError = ''
            this.gradingGroupDialogOpen = true
        },
        openDissolveGradingGroupDialog(group) {
            this.openGradingGroupDialog(group)
            this.gradingGroupDissolving = true
        },
        closeGradingGroupDialog() {
            this.gradingGroupDialogOpen = false
            this.gradingGroupError = ''
        },
        gradingGroupPartLabel(part) {
            const group = this.gradingGroups.find((group) => group.part_ids.includes(part.gradingPartId))
            return group ? `${part.name} · ${group.name}` : part.name
        },
        toggleGradingGroupItem(item, selected) {
            const field = item.group_id ? 'child_group_ids' : 'part_ids'
            const id = item.group_id || item.part_id
            this.gradingGroupForm[field] = this.gradingGroupForm[field].filter((candidate) => candidate !== id)
            if (selected) this.gradingGroupForm[field].push(id)
        },
        async saveGradingGroup() {
            if (this.isSavingGradingGroup || (!this.gradingGroupDissolving && (!this.gradingGroupForm.name.trim() || this.gradingGroupForm.part_ids.length + (this.gradingGroupForm.child_group_ids?.length || 0) < 2))) return
            const area = this.areas.find((area) => area.id === this.activeAreaId)
            if (!area) return
            const id = this.editingGradingGroupId || crypto.randomUUID()
            const current = this.gradingGroups.find((group) => group.id === id)
            const parentId = current?.parent_group_id ?? null
            const selected = new Set(this.gradingGroupForm.part_ids)
            const selectedGroups = new Set(this.gradingGroupForm.child_group_ids || [])
            const groups = this.gradingGroups.filter((group) => group.id !== id).map((group) => {
                const wasChild = group.parent_group_id === id
                const nextParent = this.gradingGroupDissolving && wasChild ? parentId
                    : !this.gradingGroupDissolving && selectedGroups.has(group.id) ? id
                        : !this.gradingGroupDissolving && wasChild ? parentId : group.parent_group_id ?? null
                let partIds = group.part_ids.filter((partId) => !selected.has(partId))
                if (this.gradingGroupDissolving) partIds = [...group.part_ids]
                if (group.id === parentId) partIds.push(...(this.gradingGroupDissolving ? current?.part_ids || [] : (current?.part_ids || []).filter((partId) => !selected.has(partId))))
                return { id: group.id, name: group.name, parent_group_id: nextParent, part_ids: [...new Set(partIds)] }
            })
            if (!this.gradingGroupDissolving) groups.push({ id, name: this.gradingGroupForm.name.trim(), parent_group_id: parentId, part_ids: [...selected] })
            const parts = (this.gradingParts || []).filter((part) => part.teaching_entry_area_id === this.activeAreaId)
            const structureError = gradingAdjustmentChangeError(this.gradingGroups, parts, groups, parts)
            if (structureError) {
                this.gradingGroupError = structureError
                return
            }
            this.isSavingGradingGroup = true
            this.gradingGroupError = ''
            try {
                const response = await axios.put(updateEntryArea.url(area.id), { name: area.name, grading_part_groups: groups })
                const index = this.areas.findIndex((candidate) => candidate.id === area.id)
                if (index !== -1) this.areas.splice(index, 1, response.data.data)
                this.closeGradingGroupDialog()
            } catch (error) {
                this.gradingGroupError = Object.values(error?.response?.data?.errors || {}).flat().join(' ') || error?.response?.data?.message || 'Die Gruppe konnte nicht gespeichert werden.'
            } finally {
                this.isSavingGradingGroup = false
            }
        },
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
            const structureIssue = this.gradingAdjustmentStates?.[area.gradingPartId ?? area.id]?.message
            if (structureIssue) return structureIssue
            const issue = overallCalculationIssue(area, this.calculationEntries)
            if (issue) return issue
            for (const entry of area.entries) {
                const entryIssue = calculationIssueForEntry(entry, this.gradingParts, this.calculationEntries)
                if (entryIssue) return `${entry.name}: ${entryIssue}`
            }
            return ''
        },
        gradingAdjustmentForPart(area) {
            return this.gradingAdjustmentStates?.[area.gradingPartId ?? area.id] || {}
        },
        gradingAdjustmentForBlock(block) {
            return !block.group && block.parts.length === 1 ? this.gradingAdjustmentForPart(block.parts[0]) : {}
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
            return { grades: 'Standardnoten 1 bis 5', plus: 'Nur Plus', plus_minus: 'Plus und Minus', points: 'Punkte' }[entryPropertyMode(entry)] || ''
        },
        calculationProperties(entry) {
            const properties = entryCalculationProperties(entry)

            const visibleProperties = ['plus_minus', 'grades'].includes(entry.calculation_mode) ? properties.filter((property) => !isStandardCalculationValue(entry.calculation_mode, property)
                && normalizePropertyEvaluation(entry.property_evaluations?.find((item) => item.property === property)?.evaluation) !== null) : properties
            return sortedCalculationProperties(visibleProperties)
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
                if (this.simulationStorageKey) this.restoreSimulationEntries()
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
                properties_mode: this.activeCategory === 'Benotung' ? 'fixed' : 'free',
                maximum_points: null,
                fixed_properties: this.activeCategory === 'Benotung' ? ['1', '2', '3', '4', '5'] : [],
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
            delete payload.standard_grade_occurrences
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
            this.gradingPartDialogMode = 'settings'
            this.gradingPartOverallThresholds = { 1: null, 2: null, 3: null, 4: null }

            this.editingGradingPartId = null
            this.gradingPartForm = { name: '', weight: 1, is_required: false, weighting_mode: 'relative', fixed_percentage: null, allowed_entry_types: 'all', points_assessment_mode: 'individual', individual_points_weighting_mode: 'weighted' }
            this.gradingPartFormErrors = {}
            this.gradingPartDialogOpen = true
        },
        openEditGradingPartDialog(gradingPartArea, mode = 'settings') {
            const entries = gradingPartArea.entries || (this.calculationEntries || []).filter((entry) => entry.teaching_entry_grading_part_id === gradingPartArea.gradingPartId)
            this.gradingPartOnlyPlusMinus = entries.length > 0 && entries.every((entry) => entryTypeGroup(entry) === 'signs')
            this.gradingPartSignPurpose = this.gradingPartOnlyPlusMinus && ['sign_grade', 'sign_adjust'].includes(gradingPartArea.points_assessment_mode) ? gradingPartArea.points_assessment_mode : null
            this.gradingPartSignThresholds = { 1: null, 2: null, 3: null, 4: null, ...gradingPartArea.sign_grade_thresholds }
            this.gradingPartSignAdjustment = { improvement_factor: null, max_improvement: null, deterioration_factor: null, max_deterioration: null, ...gradingPartArea.sign_adjustment }
            this.gradingPartOnlyPoints = entries.length > 0 && entries.every((entry) => entryTypeGroup(entry) === 'points')
            this.gradingPartOnlyStandardGrades = entries.length > 0 && entries.every((entry) => entry.has_properties && entryPropertyMode(entry) === 'grades')
            this.gradingPartSingleStandardNote = this.gradingPartOnlyStandardGrades && entries.length === 1 && (entries[0].standard_grade_occurrences?.mode ?? 'single') === 'single'
            this.gradingPartStandardOccurrences = this.gradingPartOnlyStandardGrades ? entries.map((entry) => ({
                entry_definition_id: entry.id, name: entry.name, mode: entry.standard_grade_occurrences?.mode ?? 'single', count: entry.standard_grade_occurrences?.count ?? null,
                mean_mode: entry.standard_grade_occurrences?.mean?.mode ?? 'equal', mean_weights: [...(entry.standard_grade_occurrences?.mean?.weights ?? [])],
                saved_count: entry.standard_grade_occurrences?.count ?? null, saved_mean: entry.standard_grade_occurrences?.mean ?? null,
            })) : []
            this.gradingPartCalculationMethod = this.gradingPartOnlyStandardGrades
                ? gradingPartArea.points_assessment_mode === 'grade_mean' ? 'grade_mean' : 'grade_each'
                : this.gradingPartOnlyPoints ? 'sum_percent' : this.gradingPartOnlyPlusMinus || gradingPartArea.points_assessment_mode === 'plus_minus' ? 'plus_minus' : 'sum_percent'
            this.gradingPartDialogMode = mode
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
            this.gradingPartDialogMode = 'settings'
            this.gradingPartOverallThresholds = { 1: null, 2: null, 3: null, 4: null }
            this.gradingPartDialogOpen = false
            this.editingGradingPartId = null
            this.gradingPartForm = { name: '', weight: 1, is_required: false, weighting_mode: 'relative', fixed_percentage: null, allowed_entry_types: 'all', points_assessment_mode: 'individual', individual_points_weighting_mode: 'weighted' }
            this.gradingPartFormErrors = {}
        },
        async saveGradingPart(usePointSum = false) {
            if (this.gradingPartDialogMode !== 'name' && this.gradingPartOnlyStandardGrades && this.gradingPartCalculationMethod === 'grade_mean' && (this.gradingPartStandardOccurrences || []).some((occurrence) => occurrence.mode === 'fixed' && standardGradeMeanError(occurrence))) return
            if (this.gradingPartDialogMode !== 'name' && this.gradingPartOnlyStandardGrades && (this.gradingPartStandardOccurrences || []).some((occurrence) => this.gradingPartOccurrenceError(occurrence))) return
            usePointSum = Boolean(this.editingGradingPartId) && usePointSum
            const isRenaming = Boolean(this.editingGradingPartId) && this.gradingPartDialogMode === 'name'
            if (!isRenaming && usePointSum && this.gradingPartSignThresholdError) {
                this.gradingPartFormErrors = { sign_grade_thresholds: [this.gradingPartSignThresholdError] }
                return
            }
            if (!this.activeAreaId || !this.gradingPartForm.name.trim() || (!isRenaming && !usePointSum && !this.gradingPartWeightValid) || this.isSavingGradingPart) return
            if (!isRenaming && !usePointSum && this.gradingPartOverallThresholdError) {
                this.gradingPartFormErrors = { overall_points_grade_thresholds: [this.gradingPartOverallThresholdError] }
                return
            }
            if (!isRenaming && !usePointSum && this.gradingPartForm.allowed_entry_types === 'points' && this.gradingPartHasNonPointEntries) {
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
                const method = this.gradingPartOnlyStandardGrades
                    ? this.gradingPartCalculationMethod === 'grade_mean' ? 'grade_mean' : 'grade_each'
                    : this.gradingPartOnlyPlusMinus ? this.gradingPartSignPurpose || 'plus_minus'
                        : this.gradingPartOnlyPoints ? 'sum_percent' : this.gradingPartCalculationMethod === 'plus_minus' ? 'plus_minus' : 'sum_percent'
                const payload = isRenaming ? { name } : usePointSum && this.editingGradingPartId ? { name, points_assessment_mode: method } : { name, is_required: isRequired, fixed_percentage: fixedPercentage, allowed_entry_types: this.gradingPartForm.allowed_entry_types ?? 'all' }
                if (!isRenaming && usePointSum && this.gradingPartOnlyStandardGrades) payload.is_required = isRequired
                if (!isRenaming && this.gradingPartOnlyStandardGrades) {
                    const occurrences = (this.gradingPartStandardOccurrences || []).filter((occurrence) => occurrence.mode).map((occurrence) => ({
                        entry_definition_id: occurrence.entry_definition_id, configuration: standardGradeOccurrenceConfiguration(occurrence, method),
                    }))
                    if (occurrences.length) payload.entry_standard_grade_occurrences = occurrences
                }
                if (!isRenaming && usePointSum) payload.points_assessment_mode = method
                if (!isRenaming && usePointSum && method === 'sign_grade') payload.sign_grade_thresholds = Object.fromEntries([4, 3, 2, 1].map((grade) => [grade, Number(this.gradingPartSignThresholds[grade])]))
                if (!isRenaming && usePointSum && method === 'sign_adjust') payload.sign_adjustment = Object.fromEntries(Object.entries(this.gradingPartSignAdjustment).map(([key, value]) => [key, value === null || String(value).trim() === '' ? null : String(value).trim().replace(',', '.')]))
                if (payload.allowed_entry_types === 'points') payload.points_assessment_mode = usePointSum ? method : this.gradingPartForm.points_assessment_mode ?? 'individual'
                if (payload.points_assessment_mode === 'individual') payload.individual_points_weighting_mode = this.gradingPartForm.individual_points_weighting_mode ?? 'weighted'
                if (payload.points_assessment_mode === 'overall') {
                    payload.overall_points_grade_thresholds = this.gradingPartOverallMaximumPoints > 0
                        ? Object.fromEntries([1, 2, 3, 4].map((grade) => [grade, parseGradingNumber(this.gradingPartOverallThresholds[grade])])) : null
                }
                if (!isRenaming && !(usePointSum && this.editingGradingPartId) && fixedPercentage === null) payload.weight = weight
                const parts = (this.gradingParts || []).filter((part) => part.teaching_entry_area_id === this.activeAreaId)
                const nextParts = this.editingGradingPartId ? parts.map((part) => part.id === this.editingGradingPartId ? { ...part, ...payload } : part)
                    : [...parts, { id: -1, ...payload }]
                const structureError = gradingAdjustmentChangeError(this.gradingGroups || [], parts, this.gradingGroups || [], nextParts)
                if (structureError) {
                    this.gradingPartFormErrors = { points_assessment_mode: [structureError] }
                    return
                }
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
                if (response.data.entry_area) {
                    const areaIndex = this.areas.findIndex((area) => area.id === response.data.entry_area.id)
                    if (areaIndex !== -1) this.areas.splice(areaIndex, 1, response.data.entry_area)
                }
                for (const entry of response.data.entry_definitions || []) this.replaceGradingEntry(entry)
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
                for (const area of this.areas || []) {
                    for (const group of area.grading_part_groups || []) {
                        group.part_ids = group.part_ids.filter((partId) => partId !== deletedGradingPartId)
                        if (group.weights?.some((row) => row.part_id === deletedGradingPartId)) delete group.weights
                    }
                    if (area.grading_level_weights?.some((row) => row.part_id === deletedGradingPartId)) area.grading_level_weights = null
                }
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
            this.assignmentAllowedEntryTypes = gradingPartArea.allowed_entry_types ?? 'all'
            this.selectedGradingEntryIds = this.calculationEntries
                .filter((entry) => entry.teaching_entry_grading_part_id === gradingPartArea.gradingPartId)
                .map((entry) => entry.id)
        },
        cancelGradingEntryAssignment() {
            this.assignGradingPartId = null
            this.assignmentAllowedEntryTypes = null
            this.selectedGradingEntryIds = []
        },
        toggleGradingEntrySelection(entry) {
            if (!entry?.id || this.isAssigningGradingEntry) return
            const gradingPart = this.gradingParts?.find((part) => part.id === this.assignGradingPartId)
            if (!gradingPartAllowsEntry(gradingPart ? { ...gradingPart, allowed_entry_types: this.assignmentAllowedEntryTypes ?? gradingPart.allowed_entry_types } : null, entry)) return

            if (this.selectedGradingEntryIds.includes(entry.id)) {
                this.selectedGradingEntryIds = this.selectedGradingEntryIds.filter((entryId) => entryId !== entry.id)
                return
            }

            this.selectedGradingEntryIds.push(entry.id)
        },
        canAssignGradingEntry(entry) {
            const part = this.gradingPartPendingAssignment
            return gradingPartAllowsEntry(part ? { ...part, allowed_entry_types: this.assignmentAllowedEntryTypes ?? part.allowed_entry_types } : null, entry)
        },
        gradingPartName(gradingPartId) {
            const gradingPart = this.gradingParts.find((part) => part.id === gradingPartId)

            return gradingPart?.name || 'anderer Benotungsteil'
        },
        async saveGradingEntryAssignments() {
            if (this.assignmentAllowedEntryTypes === 'none') return
            if (!this.assignGradingPartId || this.isAssigningGradingEntry) return

            const gradingPartId = this.assignGradingPartId
            const selectedEntryIds = new Set(this.selectedGradingEntryIds)
            const gradingPart = this.gradingParts?.find((part) => part.id === gradingPartId)
            const allowedTypes = this.assignmentAllowedEntryTypes ?? gradingPart?.allowed_entry_types
            const selection = gradingPart ? { ...gradingPart, allowed_entry_types: allowedTypes } : null
            if (this.calculationEntries.some((entry) => selectedEntryIds.has(entry.id) && !gradingPartAllowsEntry(selection, entry))) {
                this.notifyError(new Error('Ausgewählte Einträge passen nicht zu den Typengruppen. Bitte aktivieren Sie die passende Gruppe und entfernen Sie diese Auswahl ausdrücklich.'))
                return
            }
            const changedEntries = this.calculationEntries.filter(
                (entry) => selectedEntryIds.has(entry.id)
                    ? entry.teaching_entry_grading_part_id !== gradingPartId
                    : entry.teaching_entry_grading_part_id === gradingPartId,
            )
            const hasTypeChange = Boolean(gradingPart && this.assignmentAllowedEntryTypes) && allowedTypes !== (gradingPart?.allowed_entry_types ?? 'all')
            if (!changedEntries.length && !hasTypeChange) return

            this.isAssigningGradingEntry = true
            try {
                const saveAllowedTypes = async () => {
                    const response = await axios.put(updateGradingPart.url(gradingPartId), { name: gradingPart.name, allowed_entry_types: allowedTypes })
                    const partIndex = this.gradingParts.findIndex((part) => part.id === gradingPartId)
                    this.gradingParts.splice(partIndex, 1, response.data.data)
                }
                for (const entry of changedEntries) {
                    const currentGradingPartId = entry.teaching_entry_grading_part_id

                    if (currentGradingPartId) {
                        await axios.delete(removeGradingEntryAssignment.url({
                            entryGradingPart: currentGradingPartId,
                            entryDefinition: entry.id,
                        }))
                        this.replaceGradingEntry({ ...entry, teaching_entry_grading_part_id: null })
                    }

                }
                if (hasTypeChange) await saveAllowedTypes()
                for (const entry of changedEntries) {
                    if (!selectedEntryIds.has(entry.id)) continue
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
.calculation-overviews {
    --grading-overview-width: 100%;
    display: grid;
    grid-template-columns: repeat(2, max(360px, var(--grading-overview-width)));
    align-items: start;
    gap: 16px;
    overflow-x: auto;
}
/* Preserve the former settings column width, including its surrounding gutters. */
@media (min-width: 960px) {
    .calculation-overviews {
        --grading-overview-width: calc(50% - 31px);
    }
}
@media (min-width: 1280px) {
    .calculation-overviews {
        --grading-overview-width: calc(58.3333333333% - 25.8333333333px);
    }
}
@media (min-width: 1920px) {
    .calculation-overviews {
        --grading-overview-width: calc(33.3333333333% - 41.3333333333px);
    }
}
.calculation-overview {
    min-width: 0;
    padding: 16px;
    border: 1px solid rgba(var(--v-border-color), 0.16);
    border-radius: 16px;
    background: rgb(var(--v-theme-surface));
    overflow-wrap: anywhere;
}
.calculation-simulation .calculation-group-header {
    grid-template-columns: minmax(0, 1fr);
}
.simulation-entry-values {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    list-style: none;
    padding: 0;
    margin-top: 8px;
}
.simulation-entry-values li {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
    font-weight: 600;
}
.simulation-entry-remove {
    align-self: flex-start;
    transform: translateY(-4px);
}
.calculation-simulation .grading-weight-heading,
.calculation-simulation .grading-part-title,
.simulation-entry-heading {
    display: flex;
    align-items: baseline;
    gap: 8px;
    min-width: 0;
    width: 100%;
}
.simulation-entry-heading .calculation-entry-name {
    min-width: 0;
    overflow-wrap: anywhere;
}
.simulation-grade {
    margin-left: auto;
    flex-shrink: 1;
    min-width: 0;
    text-align: right;
    overflow-wrap: anywhere;
    font-weight: 400;
    color: rgb(var(--v-theme-primary));
}
.sign-adjustment-field {
    flex: 1 1 180px;
    min-width: 0;
}
.simulation-result {
    margin-left: auto;
    min-width: 0;
    text-align: right;
    flex: 0 1 65%;
    overflow-wrap: anywhere;
}
.simulation-result small {
    display: block;
    font-size: 0.75rem;
    font-weight: 400;
    line-height: 1.4;
    color: rgb(var(--v-theme-on-surface));
}
.simulation-entry-add {
    margin-left: auto;
}
@media (max-width: 600px) {
    .calculation-overviews {
        grid-template-columns: repeat(2, 100%);
    }
    .calculation-simulation .grading-part-title,
    .calculation-simulation .grading-weight-heading {
        flex-wrap: wrap;
    }
    .simulation-result {
        flex-basis: 100%;
    }
    .calculation-overview {
        padding: 8px;
    }
}
.calculation-semester-grade-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.calculation-group-header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 12px;
    margin-bottom: 14px;
}
.calculation-group-heading {
    min-width: 0;
    overflow-wrap: anywhere;
}
.calculation-group-actions {
    display: grid;
    justify-items: end;
    justify-self: end;
    gap: 4px;
}
@media (max-width: 600px) {
    .calculation-group-header {
        grid-template-columns: minmax(0, 1fr);
    }
    .calculation-group-actions {
        justify-self: end;
    }
}
.calculation-group-parts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
    gap: 12px;
}
.calculation-group-parts--adjustment-pair {
    grid-template-columns: minmax(0, 1fr);
}
.grading-adjustment-connection {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    color: rgb(var(--v-theme-primary));
}
.calculation-area-card {
    padding: 12px;
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
    flex-wrap: wrap;
    align-items: flex-start;
    margin-bottom: 0;
    padding-right: 20px;
}
.calculation-part-card .calculation-area-heading {
    flex-basis: 160px;
}
@media (max-width: 600px) {
    .calculation-part-card .calculation-area-header {
        padding-right: 0;
    }
}
.calculation-part-card,
.calculation-entry-static {
    position: relative;
}
.calculation-entry-item.calculation-entry-static {
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
    justify-content: flex-end;
    gap: 6px;
}
.point-sum-grade-scale > div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 4px 0;
    border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
}
.point-sum-grade-scale dd {
    margin: 0;
    font-weight: 600;
}
.grading-weight-heading {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
}
.grading-weight-heading > h3 {
    display: flex;
    align-items: center;
    min-height: 24px;
}
.grading-title-content {
    flex: 1;
    min-width: 0;
    overflow-wrap: anywhere;
}
.grading-part-title {
    display: flex;
    align-items: center;
    gap: 4px;
    min-height: 24px;
}
.calculation-part-summary {
    margin-top: 2px;
}
.grading-part-title > span {
    min-width: 0;
    overflow-wrap: anywhere;
}
.grading-part-title > .v-btn {
    flex-shrink: 0;
}
.grading-weight-heading > h3,
.grading-weight-heading > span {
    min-width: 0;
    overflow-wrap: anywhere;
}
.grading-weight-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px;
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
}
.grading-weight-trigger:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
    border-radius: 3px;
}
.grading-weight-trigger:disabled {
    cursor: default;
    opacity: 0.5;
}
.grading-group-member-weight {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    width: 80px;
    height: 56px;
    flex-shrink: 0;
    border: 1px solid rgba(var(--v-theme-primary), 0.2);
    border-radius: 12px;
    color: rgb(var(--v-theme-primary));
    background: color-mix(in srgb, rgb(var(--grading-level-color)) 24%, white);
    cursor: pointer;
}
.grading-group-member-weight strong {
    font-size: 1.4rem;
    line-height: 1;
}
.grading-block-weight.grading-group-member-weight {
    background: color-mix(in srgb, rgb(var(--grading-parent-color)) 24%, white);
}
.grading-weight-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.grading-weight-fields label {
    display: block;
    margin-bottom: 6px;
    overflow-wrap: anywhere;
}
.grading-weight-actions {
    flex-wrap: wrap;
}
.grading-weight-title {
    white-space: normal;
}
.calculation-entry-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0;
    list-style: none;
}
.calculation-entry-list > li {
    width: 280px;
    max-width: 100%;
}
.calculation-entry-item {
    display: flex;
    align-items: flex-start;
    min-width: 0;
    gap: 8px;
    padding: 8px;
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
.calculation-entry-static {
    width: 100%;
    text-align: left;
    color: inherit;
}
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
    background: rgba(var(--v-theme-primary), 0.28);
    box-shadow: 0 6px 16px rgba(var(--v-theme-primary), 0.12);
}
.calculation-entry-selection-card:focus-visible {
    outline: 2px solid rgba(var(--v-theme-primary), 0.5);
    outline-offset: 2px;
}
.calculation-entry-selection-card:disabled {
    cursor: not-allowed;
    opacity: 1;
    color: #475569;
    background: #d1d5db;
    border-color: #9ca3af;
    box-shadow: none;
    transform: none;
}
.entry-marking-dot {
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-left: 8px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.25);
    border-radius: 50%;
    vertical-align: middle;
}
.calculation-entry-selection-card:disabled * {
    color: inherit !important;
}
.calculation-entry-selection-card:disabled .v-chip__underlay,
.calculation-entry-selection-card:disabled .v-chip__overlay {
    opacity: 0.12;
}
.calculation-entry-selection-card:disabled .v-chip,
.calculation-entry-selection-card:disabled .v-icon {
    filter: grayscale(1);
}
.calculation-entry-selection-card:disabled .calculation-entry-code,
.calculation-entry-selection-card:disabled .calculation-evaluation {
    background: #c3c8cf !important;
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
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    min-width: 0;
    gap: 4px;
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
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0;
    padding: 0;
    min-height: 0;
    background: transparent;
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
