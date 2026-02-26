<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <template v-if="!standalone">
            <div class="text-h4 font-weight-bold mb-2">Einstellungen</div>
            <div class="text-subtitle-1 subline mb-4">Wähle einen Bereich aus.</div>

            <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 w-100 mb-5">
                <v-btn
                    v-for="item in visibleMenuItems"
                    :key="`materials-settings-${item.value}`"
                    rounded="pill"
                    size="small"
                    variant="flat"
                    :prepend-icon="item.icon"
                    :disabled="isAnySettingsEditActive && selectedAction !== item.value"
                    :class="[
                        'settings-menu-btn',
                        { 'settings-menu-btn--active': selectedAction === item.value },
                    ]"
                    @click="selectedAction = item.value">
                    {{ item.label }}
                </v-btn>
            </v-card>
        </template>

        <v-card variant="outlined" class="pa-4">
            <div class="text-h6 font-weight-bold mb-2">{{ selectedItemLabel }}</div>
            <template v-if="selectedAction === 'materials_types'">
                <div class="text-body-2 text-medium-emphasis mb-3">
                    Diese Typen gelten nur für deine eigenen Materialien.
                </div>

                <v-alert v-if="!normalizedTypeOptions.length" type="info" variant="tonal" class="mb-3">
                    Noch keine Materialtypen vorhanden.
                </v-alert>

                <div v-else class="d-flex flex-wrap ga-2 mb-3">
                    <v-chip
                        v-for="option in normalizedTypeOptions"
                        :key="`settings-material-type-${option.id || option.value}`"
                        size="small"
                        variant="tonal"
                        :color="option.color || 'primary'"
                        :prepend-icon="option.icon || 'mdi-file-document-outline'">
                        {{ option.label }}
                    </v-chip>
                </div>

                <v-btn
                    v-if="canManageTypeValues"
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-shape-outline"
                    @click="typeManagerDialogOpen = true">
                    Materialtypen verwalten
                </v-btn>
            </template>

            <template v-else-if="selectedAction === 'status_values'">
                <div class="text-body-2 text-medium-emphasis mb-3">
                    Diese Statuswerte gelten für alle Materialien deiner Schule.
                </div>

                <v-alert v-if="!normalizedStatusOptions.length" type="info" variant="tonal" class="mb-3">
                    Noch keine Statuswerte vorhanden.
                </v-alert>

                <div v-else class="d-flex flex-wrap ga-2 mb-3">
                    <v-chip
                        v-for="option in normalizedStatusOptions"
                        :key="`settings-material-status-${option.id || option.value}`"
                        size="small"
                        variant="tonal"
                        :color="option.color || 'primary'"
                        prepend-icon="mdi-flag-outline">
                        {{ option.label }}
                    </v-chip>
                </div>

                <v-btn
                    v-if="canManageStatusValues"
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-flag-outline"
                    @click="statusManagerDialogOpen = true">
                    Statuswerte verwalten
                </v-btn>
            </template>

            <template v-else-if="selectedAction === 'overview_settings'">
                <div class="text-body-2 text-medium-emphasis mb-3">
                    Dateieinstellung für die gesamte Schule.
                </div>

                <div class="d-flex flex-wrap align-center ga-2 mb-3">
                    <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-upload">
                        Max. Uploadgröße: {{ currentMaxUploadSizeMb }} MB
                    </v-chip>

                    <v-btn
                        v-if="canManageFileSettings && !isEditingFileSettings"
                        size="small"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-pencil"
                        :disabled="isAnySettingsEditActive || isSavingFileSettings"
                        @click="startEditFileSettings">
                        Bearbeiten
                    </v-btn>
                </div>

                <div v-if="canManageFileSettings && isEditingFileSettings" class="d-flex flex-wrap align-start ga-2 mb-4">
                    <v-text-field
                        ref="fileSettingsMaxUploadSizeField"
                        v-model="fileSettingsForm.maxUploadSizeMb"
                        type="number"
                        step="0.5"
                        min="0.1"
                        label="Maximale Uploadgröße (MB)"
                        variant="outlined"
                        density="comfortable"
                        class="flex-grow-1"
                        hide-details="auto"
                        :disabled="isSavingFileSettings" />

                    <v-btn
                        variant="text"
                        :disabled="isSavingFileSettings"
                        @click="cancelEditFileSettings">
                        Abbrechen
                    </v-btn>

                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-content-save-outline"
                        :loading="isSavingFileSettings"
                        :disabled="isSavingFileSettings || !canSaveFileSettings"
                        @click="saveFileSettings">
                        Speichern
                    </v-btn>
                </div>

                <v-divider class="my-3" />

                <div class="text-body-2 text-medium-emphasis mb-3">
                    Diese Einstellung gilt nur für deine eigene Material-Übersicht.
                </div>

                <div class="d-flex flex-wrap align-center ga-2 mb-3">
                    <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-format-list-numbered">
                        Materialien pro Seite: {{ currentMaterialsPaginationNumber }}
                    </v-chip>

                    <v-btn
                        v-if="canManageUserSettings && !isEditingUserSettings"
                        size="small"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-pencil"
                        :disabled="isAnySettingsEditActive || isSavingUserSettings"
                        @click="startEditUserSettings">
                        Bearbeiten
                    </v-btn>
                </div>

                <div v-if="canManageUserSettings && isEditingUserSettings" class="d-flex flex-wrap align-start ga-2">
                    <v-text-field
                        ref="userSettingsPaginationField"
                        v-model="userSettingsForm.materialsPaginationNumber"
                        type="number"
                        step="1"
                        min="1"
                        max="200"
                        label="Materialien pro Seite"
                        variant="outlined"
                        density="comfortable"
                        class="flex-grow-1"
                        hide-details="auto"
                        :disabled="isSavingUserSettings" />

                    <v-btn
                        variant="text"
                        :disabled="isSavingUserSettings"
                        @click="cancelEditUserSettings">
                        Abbrechen
                    </v-btn>

                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-content-save-outline"
                        :loading="isSavingUserSettings"
                        :disabled="isSavingUserSettings || !canSaveUserSettings"
                        @click="saveUserSettings">
                        Speichern
                    </v-btn>
                </div>
            </template>

            <template v-else-if="selectedAction === 'subjects'">
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 w-100 mb-4">
                    <v-btn
                        v-for="item in subjectsMenuItems"
                        :key="`materials-subjects-settings-${item.value}`"
                        rounded="pill"
                        size="small"
                        variant="flat"
                        :prepend-icon="item.icon"
                        :class="[
                            'settings-submenu-btn',
                            { 'settings-submenu-btn--active': selectedSubjectAction === item.value },
                        ]"
                        @click="selectedSubjectAction = item.value">
                        {{ item.label }}
                    </v-btn>
                </v-card>

                <v-card variant="outlined" class="pa-4">
                    <div class="text-subtitle-1 font-weight-bold mb-2">{{ selectedSubjectItemLabel }}</div>
                    <template v-if="selectedSubjectAction === 'subjects_catalog'">
                        <div class="text-body-2 text-medium-emphasis mb-3">
                            Alle verfügbaren Fächer mit zugehörigen Themen und Bereichen.
                        </div>

                        <div class="d-flex align-center mb-3">
                            <v-btn
                                v-if="isSubjectCatalogEditorOpen"
                                size="small"
                                variant="text"
                                :disabled="isSavingSubjectCatalog"
                                @click="cancelSubjectCatalogEditor">
                                Abbrechen
                            </v-btn>
                        </div>

                        <v-card v-if="isSubjectCatalogEditorOpen" variant="tonal" color="primary" class="pa-3 mb-3">
                            <div class="text-body-2 font-weight-bold mb-2">{{ subjectCatalogEditorTitle }}</div>
                            <div class="d-flex flex-wrap align-start ga-2">
                                <v-text-field
                                    ref="subjectCatalogEditorNameField"
                                    v-model="subjectCatalogEditor.name"
                                    label="Name"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    class="flex-grow-1"
                                    :disabled="isSavingSubjectCatalog"
                                    @keyup.enter="saveSubjectCatalogEditor" />
                                <v-btn
                                    size="small"
                                    color="primary"
                                    variant="flat"
                                    prepend-icon="mdi-content-save-outline"
                                    :loading="isSavingSubjectCatalog"
                                    :disabled="isSavingSubjectCatalog || !canSaveSubjectCatalogEditor"
                                    @click="saveSubjectCatalogEditor">
                                    Speichern
                                </v-btn>
                            </div>
                        </v-card>

                        <div class="subjects-tree">
                            <div v-if="!subjectTreeItems.length" class="text-body-2 text-medium-emphasis mb-2">
                                Noch keine Fachstruktur vorhanden.
                            </div>
                            <ul class="subjects-tree-list">
                                <li
                                    v-for="(subject, subjectIndex) in subjectTreeItems"
                                    :key="`subject-tree-subject-${subject.id || subject.name}`"
                                    class="subjects-tree-item">
                                    <div class="subjects-tree-group" :style="subjectGroupStyle(subject)">
                                        <div class="subjects-tree-node subjects-tree-node--subject">
                                            <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                                            <span>{{ subject.name }}</span>
                                            <div class="subjects-tree-node-actions">
                                                <v-btn
                                                    icon="mdi-file-tree-outline"
                                                    size="x-small"
                                                    variant="text"
                                                    color="primary"
                                                    :disabled="isSavingSubjectCatalog || !subject.id"
                                                    @click="openReclassifyDialogForSubject(subject)" />
                                                <v-btn
                                                    icon="mdi-chevron-up"
                                                    size="x-small"
                                                    variant="text"
                                                    :disabled="isSavingSubjectCatalog || !canMoveSubjectUp(subject, subjectIndex)"
                                                    @click="moveSubject(subject, 'up')" />
                                                <v-btn
                                                    icon="mdi-chevron-down"
                                                    size="x-small"
                                                    variant="text"
                                                    :disabled="isSavingSubjectCatalog || !canMoveSubjectDown(subject, subjectIndex)"
                                                    @click="moveSubject(subject, 'down')" />
                                                <v-btn
                                                    icon="mdi-pencil"
                                                    size="x-small"
                                                    variant="text"
                                                    color="primary"
                                                    :disabled="isSavingSubjectCatalog || !subject.id"
                                                    @click="startRenameSubject(subject)" />
                                                <v-btn
                                                    v-if="subject.canDelete"
                                                    icon="mdi-delete-outline"
                                                    size="x-small"
                                                    variant="text"
                                                    color="error"
                                                    :disabled="isSavingSubjectCatalog || !subject.id"
                                                    @click="openDeleteConfirm('subject', subject)" />
                                            </div>
                                        </div>

                                        <ul v-if="subject.id" class="subjects-tree-list subjects-tree-list--child">
                                            <li
                                                v-for="(topic, topicIndex) in subject.topics"
                                                :key="topic.id || `subject-tree-topic-${subject.id || subject.name}-${topic.name}`"
                                                class="subjects-tree-item subjects-tree-topic-group"
                                                :style="topicGroupStyle(subject)">
                                                <div class="subjects-tree-node subjects-tree-node--topic">
                                                    <v-icon size="14" icon="mdi-book-open-page-variant-outline" class="mr-2" />
                                                    <span>{{ topic.name }}</span>
                                                    <div class="subjects-tree-node-actions">
                                                        <v-btn
                                                            icon="mdi-file-tree-outline"
                                                            size="x-small"
                                                            variant="text"
                                                            color="primary"
                                                            :disabled="isSavingSubjectCatalog || !topic.id"
                                                            @click="openReclassifyDialogForTopic(subject, topic)" />
                                                        <v-btn
                                                            icon="mdi-chevron-up"
                                                            size="x-small"
                                                            variant="text"
                                                            :disabled="isSavingSubjectCatalog || !canMoveTopicUp(subject, topic, topicIndex)"
                                                            @click="moveTopic(topic, 'up')" />
                                                        <v-btn
                                                            icon="mdi-chevron-down"
                                                            size="x-small"
                                                            variant="text"
                                                            :disabled="isSavingSubjectCatalog || !canMoveTopicDown(subject, topic, topicIndex)"
                                                            @click="moveTopic(topic, 'down')" />
                                                        <v-btn
                                                            icon="mdi-pencil"
                                                            size="x-small"
                                                            variant="text"
                                                            color="primary"
                                                            :disabled="isSavingSubjectCatalog || !topic.id"
                                                            @click="startRenameTopic(topic)" />
                                                        <v-btn
                                                            v-if="topic.canDelete"
                                                            icon="mdi-delete-outline"
                                                            size="x-small"
                                                            variant="text"
                                                            color="error"
                                                            :disabled="isSavingSubjectCatalog || !topic.id"
                                                            @click="openDeleteConfirm('topic', topic)" />
                                                    </div>
                                                </div>

                                                <ul v-if="topic.id" class="subjects-tree-list subjects-tree-list--child">
                                                    <li
                                                        v-for="(unit, unitIndex) in topic.units"
                                                        :key="unit.id || `subject-tree-unit-${subject.id || subject.name}-${topic.id || topic.name}-${unit.name}`"
                                                        class="subjects-tree-item">
                                                        <div class="subjects-tree-node subjects-tree-node--unit">
                                                            <v-icon size="13" icon="mdi-circle-medium" class="mr-1" />
                                                            <span>{{ unit.name }}</span>
                                                            <div class="subjects-tree-node-actions">
                                                                <v-btn
                                                                    icon="mdi-file-tree-outline"
                                                                    size="x-small"
                                                                    variant="text"
                                                                    color="primary"
                                                                    :disabled="isSavingSubjectCatalog || !unit.id"
                                                                    @click="openReclassifyDialogForUnit(subject, topic, unit)" />
                                                                <v-btn
                                                                    icon="mdi-chevron-up"
                                                                    size="x-small"
                                                                    variant="text"
                                                                    :disabled="isSavingSubjectCatalog || !canMoveUnitUp(topic, unit, unitIndex)"
                                                                    @click="moveUnit(unit, 'up')" />
                                                                <v-btn
                                                                    icon="mdi-chevron-down"
                                                                    size="x-small"
                                                                    variant="text"
                                                                    :disabled="isSavingSubjectCatalog || !canMoveUnitDown(topic, unit, unitIndex)"
                                                                    @click="moveUnit(unit, 'down')" />
                                                                <v-btn
                                                                    icon="mdi-pencil"
                                                                    size="x-small"
                                                                    variant="text"
                                                                    color="primary"
                                                                    :disabled="isSavingSubjectCatalog || !unit.id"
                                                                    @click="startRenameUnit(unit)" />
                                                                <v-btn
                                                                    v-if="unit.canDelete"
                                                                    icon="mdi-delete-outline"
                                                                    size="x-small"
                                                                    variant="text"
                                                                    color="error"
                                                                    :disabled="isSavingSubjectCatalog || !unit.id"
                                                                    @click="openDeleteConfirm('unit', unit)" />
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <li class="subjects-tree-item">
                                                        <button
                                                            type="button"
                                                            class="subjects-tree-new-btn"
                                                            :disabled="isSavingSubjectCatalog"
                                                            @click="startCreateUnit(topic)">
                                                            <span class="subjects-tree-node subjects-tree-node--new">
                                                                <v-icon size="12" icon="mdi-plus" class="mr-1" />
                                                                <span>Neue Einheit</span>
                                                            </span>
                                                        </button>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li class="subjects-tree-item subjects-tree-new-topic-item">
                                                <button
                                                    type="button"
                                                    class="subjects-tree-new-btn"
                                                    :disabled="isSavingSubjectCatalog"
                                                    @click="startCreateTopic(subject)">
                                                    <span class="subjects-tree-node subjects-tree-node--new">
                                                        <v-icon size="14" icon="mdi-plus" class="mr-1" />
                                                        <span>Neues Thema</span>
                                                    </span>
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                                <li class="subjects-tree-item">
                                    <button
                                        type="button"
                                        class="subjects-tree-new-btn"
                                        :disabled="isSavingSubjectCatalog"
                                        @click="startCreateSubject">
                                        <span class="subjects-tree-node subjects-tree-node--new">
                                            <v-icon size="16" icon="mdi-plus" class="mr-1" />
                                            <span>Neues Fach</span>
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </template>

                    <template v-else-if="selectedSubjectAction === 'subjects_groups'">
                        <div class="text-body-2 text-medium-emphasis mb-3">
                            Alle Fächer, Themen und Einheiten mit zugeordneten Materialien.
                            Materialien können hier in eine andere Zuordnung verschoben oder kopiert werden.
                        </div>

                        <div class="d-flex flex-wrap align-center ga-2 mb-3">
                            <v-btn
                                size="small"
                                color="primary"
                                variant="flat"
                                prepend-icon="mdi-refresh"
                                :loading="isLoadingSubjectAssignments"
                                :disabled="isSavingSubjectAssignment"
                                @click="loadSubjectAssignments(true)">
                                Aktualisieren
                            </v-btn>
                            <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-graph-outline">
                                Einträge mit Materialien: {{ subjectAssignmentGroups.length }}
                            </v-chip>
                        </div>

                        <v-progress-linear
                            v-if="isLoadingSubjectAssignments"
                            indeterminate
                            color="primary"
                            rounded
                            class="mb-3" />

                        <v-alert
                            v-else-if="!subjectAssignmentGroups.length"
                            type="info"
                            variant="tonal"
                            class="mb-2">
                            Keine Zuordnungen mit Materialien gefunden.
                        </v-alert>

                        <v-expansion-panels
                            v-else
                            variant="accordion"
                            multiple
                            class="subject-assignments-panels">
                            <v-expansion-panel
                                v-for="group in subjectAssignmentGroups"
                                :key="`subject-assignment-group-${group.key}`">
                                <v-expansion-panel-title>
                                    <div class="d-flex align-center flex-wrap ga-2 w-100">
                                        <v-chip size="x-small" variant="tonal" color="primary">
                                            {{ group.levelLabel }}
                                        </v-chip>
                                        <span class="font-weight-medium">{{ group.label }}</span>
                                        <v-chip size="x-small" variant="tonal" color="primary" class="ml-auto">
                                            {{ group.materials.length }}
                                        </v-chip>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <div
                                        v-for="material in group.materials"
                                        :key="`subject-assignment-material-${group.key}-${material.cardId}`"
                                        class="subject-assignment-material-row">
                                        <div class="subject-assignment-material-meta">
                                            <div class="text-body-2 font-weight-medium">{{ material.title }}</div>
                                        </div>

                                        <div class="d-flex flex-wrap ga-2">
                                            <v-btn
                                                size="small"
                                                color="primary"
                                                variant="outlined"
                                                prepend-icon="mdi-swap-horizontal"
                                                class="subject-assignment-action-btn"
                                                :disabled="isSavingSubjectAssignment"
                                                @click="openSubjectAssignmentDialog('move', group, material)">
                                                Verschieben
                                            </v-btn>
                                            <v-btn
                                                size="small"
                                                color="primary"
                                                variant="outlined"
                                                prepend-icon="mdi-content-copy"
                                                class="subject-assignment-action-btn"
                                                :disabled="isSavingSubjectAssignment"
                                                @click="openSubjectAssignmentDialog('copy', group, material)">
                                                Kopieren
                                            </v-btn>
                                            <v-btn
                                                size="small"
                                                color="error"
                                                variant="outlined"
                                                prepend-icon="mdi-delete-outline"
                                                class="subject-assignment-action-btn"
                                                :disabled="isSavingSubjectAssignment || !canDeleteSubjectAssignment(material, group)"
                                                @click="openSubjectAssignmentDeleteDialog(group, material)">
                                                Zuordnung löschen
                                            </v-btn>
                                        </div>
                                    </div>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </template>

                    <template v-else>
                        <div class="settings-empty-card" />
                    </template>
                </v-card>
            </template>
        </v-card>

        <v-dialog v-model="deleteConfirmDialog.open" max-width="520" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">Löschen bestätigen</v-card-title>
                <v-card-text>
                    <div class="text-body-1 mb-1">{{ deleteConfirmDialog.label || 'Diesen Eintrag' }}</div>
                    <div class="text-body-2 text-medium-emphasis">
                        Wirklich löschen? Das ist nur möglich, wenn keine Materialien zugeordnet sind.
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end px-4 pb-4">
                    <v-btn
                        variant="text"
                        :disabled="isSavingSubjectCatalog"
                        @click="cancelDeleteConfirm">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-delete-outline"
                        :loading="isSavingSubjectCatalog"
                        :disabled="isSavingSubjectCatalog"
                        @click="confirmDeleteEntry">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="reclassifyDialog.open" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">Eintrag verschieben</v-card-title>
                <v-card-text class="pb-2">
                    <div class="text-body-2 mb-2">
                        <strong>{{ reclassifyDialog.sourceName || 'Eintrag' }}</strong>
                    </div>
                    <div class="text-body-2 text-medium-emphasis mb-4">
                        {{ reclassifyDialogHint }}
                    </div>

                    <div v-if="reclassifyDialog.kind === 'topic'" class="mb-4">
                        <div class="text-caption text-medium-emphasis mb-1">Zieltyp</div>
                        <v-btn-toggle
                            :model-value="reclassifyDialog.mode"
                            color="primary"
                            density="comfortable"
                            mandatory
                            variant="outlined"
                            @update:modelValue="setReclassifyMode">
                            <v-btn value="to_subject" size="small">Als Thema</v-btn>
                            <v-btn value="to_unit" size="small">Als Einheit</v-btn>
                            <v-btn value="to_new_subject" size="small">Als neues Fach</v-btn>
                        </v-btn-toggle>
                    </div>

                    <div v-if="reclassifyDialog.kind === 'unit'" class="mb-4">
                        <div class="text-caption text-medium-emphasis mb-1">Zieltyp</div>
                        <v-btn-toggle
                            :model-value="reclassifyDialog.mode"
                            color="primary"
                            density="comfortable"
                            mandatory
                            variant="outlined"
                            @update:modelValue="setReclassifyMode">
                            <v-btn value="to_topic" size="small">Als Einheit</v-btn>
                            <v-btn value="to_new_topic" size="small">Als neues Thema</v-btn>
                        </v-btn-toggle>
                    </div>

                    <v-select
                        v-if="reclassifyUsesTargetSubject"
                        v-model="reclassifyDialog.targetSubjectId"
                        :items="reclassifyTargetSubjectItems"
                        item-title="name"
                        item-value="id"
                        label="Zielfach"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isSavingSubjectCatalog"
                        @update:modelValue="onReclassifyTargetSubjectChange" />

                    <v-text-field
                        v-if="reclassifyNeedsNewSubjectName"
                        v-model="reclassifyDialog.newSubjectName"
                        label="Neues Fach"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isSavingSubjectCatalog" />

                    <v-text-field
                        v-if="reclassifyNeedsNewTopicName"
                        v-model="reclassifyDialog.newTopicName"
                        label="Neues Thema"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="isSavingSubjectCatalog" />

                    <v-select
                        v-if="reclassifyNeedsTargetTopic"
                        v-model="reclassifyDialog.targetTopicId"
                        :items="reclassifyTargetTopicItems"
                        item-title="name"
                        item-value="id"
                        label="Zielthema"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :disabled="isSavingSubjectCatalog || !reclassifyDialog.targetSubjectId" />
                </v-card-text>
                <v-card-actions class="justify-end px-4 pb-4">
                    <v-btn
                        variant="text"
                        :disabled="isSavingSubjectCatalog"
                        @click="closeReclassifyDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-content-save-outline"
                        :loading="isSavingSubjectCatalog"
                        :disabled="isSavingSubjectCatalog || !canSaveReclassifyDialog"
                        @click="saveReclassifyDialog">
                        Verschieben
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="subjectAssignmentDialog.open" max-width="640" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">{{ subjectAssignmentDialogTitle }}</v-card-title>
                <v-card-text class="pb-2">
                    <div class="subject-assignment-info mb-3">
                        <div class="subject-assignment-info-label">Material</div>
                        <div class="subject-assignment-info-value">
                            {{ subjectAssignmentDialog.card?.title || 'Ohne Titel' }}
                        </div>
                    </div>

                    <div class="subject-assignment-info mb-3">
                        <div class="subject-assignment-info-label">Aktuelle Zuordnung</div>
                        <div class="subject-assignment-info-value">{{ subjectAssignmentDialogSourceLabel }}</div>
                    </div>

                    <div class="subject-assignment-chip-section mb-3">
                        <div class="subject-assignment-chip-label mb-2">Fach</div>
                        <v-chip-group
                            :model-value="subjectAssignmentDialog.targetRow.subject"
                            column
                            :disabled="isSavingSubjectAssignment"
                            selected-class="subject-assignment-chip--selected"
                            @update:modelValue="updateSubjectAssignmentTargetSubject">
                            <v-chip
                                v-for="subject in subjectAssignmentSubjectOptions"
                                :key="`subject-assignment-subject-chip-${subject}`"
                                :value="subject"
                                size="small"
                                variant="outlined"
                                filter>
                                {{ subject }}
                            </v-chip>
                        </v-chip-group>
                    </div>

                    <div class="subject-assignment-chip-section mb-3">
                        <div class="subject-assignment-chip-label mb-2">Thema</div>
                        <v-chip-group
                            :model-value="subjectAssignmentDialog.targetRow.topic"
                            column
                            :disabled="isSavingSubjectAssignment || !subjectAssignmentDialog.targetRow.subject"
                            selected-class="subject-assignment-chip--selected"
                            @update:modelValue="updateSubjectAssignmentTargetTopic">
                            <v-chip
                                value=""
                                size="small"
                                variant="outlined"
                                filter>
                                Ohne Thema
                            </v-chip>
                            <v-chip
                                v-for="topic in subjectAssignmentTopicOptions"
                                :key="`subject-assignment-topic-chip-${topic}`"
                                :value="topic"
                                size="small"
                                variant="outlined"
                                filter>
                                {{ topic }}
                            </v-chip>
                        </v-chip-group>
                    </div>

                    <div class="subject-assignment-chip-section">
                        <div class="subject-assignment-chip-label mb-2">Einheit</div>
                        <v-chip-group
                            :model-value="subjectAssignmentDialog.targetRow.unit"
                            column
                            :disabled="isSavingSubjectAssignment || !subjectAssignmentDialog.targetRow.topic"
                            selected-class="subject-assignment-chip--selected"
                            @update:modelValue="updateSubjectAssignmentTargetUnit">
                            <v-chip
                                value=""
                                size="small"
                                variant="outlined"
                                filter>
                                Ohne Einheit
                            </v-chip>
                            <v-chip
                                v-for="unit in subjectAssignmentUnitOptions"
                                :key="`subject-assignment-unit-chip-${unit}`"
                                :value="unit"
                                size="small"
                                variant="outlined"
                                filter>
                                {{ unit }}
                            </v-chip>
                        </v-chip-group>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end px-4 pb-4">
                    <v-btn
                        variant="text"
                        :disabled="isSavingSubjectAssignment"
                        @click="closeSubjectAssignmentDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-content-save-outline"
                        :loading="isSavingSubjectAssignment"
                        :disabled="!canSaveSubjectAssignmentDialog"
                        @click="saveSubjectAssignmentDialog">
                        {{ subjectAssignmentDialogActionLabel }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="subjectAssignmentDeleteDialog.open" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">Zuordnung löschen</v-card-title>
                <v-card-text>
                    <div class="text-body-1 mb-2">
                        {{ subjectAssignmentDeleteDialogLabel }}
                    </div>
                    <div class="text-body-2 text-medium-emphasis">
                        Diese Zuordnung wird vom Material entfernt. Die letzte verbleibende Zuordnung kann nicht gelöscht werden.
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end px-4 pb-4">
                    <v-btn
                        variant="text"
                        :disabled="isSavingSubjectAssignment"
                        @click="closeSubjectAssignmentDeleteDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-delete-outline"
                        :loading="isSavingSubjectAssignment"
                        :disabled="isSavingSubjectAssignment"
                        @click="confirmSubjectAssignmentDelete">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <MaterialTypeManagerDialog v-model="typeManagerDialogOpen" />
        <MaterialStatusManagerDialog v-model="statusManagerDialogOpen" />
    </v-card>
</template>

<script>
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import MaterialTypeManagerDialog from '../forms/MaterialTypeManagerDialog.vue'
import MaterialStatusManagerDialog from '../forms/MaterialStatusManagerDialog.vue'

export default {
    name: 'MaterialsSettingsView',
    emits: ['menu-lock-change'],
    props: {
        initialSelectedAction: {
            type: String,
            default: null,
        },
        initialSelectedSubjectAction: {
            type: String,
            default: null,
        },
        standalone: {
            type: Boolean,
            default: false,
        },
    },
    components: {
        MaterialTypeManagerDialog,
        MaterialStatusManagerDialog,
    },
    data() {
        return {
            materialCardStore: null,
            selectedAction: 'overview_settings',
            selectedSubjectAction: 'subjects_catalog',
            typeManagerDialogOpen: false,
            statusManagerDialogOpen: false,
            menuItems: [
                { value: 'overview_settings', label: 'Übersicht', icon: 'mdi-view-dashboard-outline' },
                { value: 'materials_types', label: 'Materialtypen', icon: 'mdi-shape-outline' },
                { value: 'status_values', label: 'Statuswerte', icon: 'mdi-flag-outline' },
            ],
            subjectsMenuItems: [
                { value: 'subjects_catalog', label: 'Fachkatalog', icon: 'mdi-book-open-variant-outline' },
                { value: 'subjects_groups', label: 'Zuordnung', icon: 'mdi-account-group-outline' },
            ],
            fileSettingsForm: {
                maxUploadSizeMb: '',
            },
            isSavingFileSettings: false,
            isEditingFileSettings: false,
            userSettingsForm: {
                materialsPaginationNumber: '',
            },
            isSavingUserSettings: false,
            isEditingUserSettings: false,
            isSavingSubjectCatalog: false,
            subjectCatalogEditor: {
                mode: '',
                name: '',
                subjectId: null,
                topicId: null,
                unitId: null,
                parentLabel: '',
            },
            deleteConfirmDialog: {
                open: false,
                kind: '',
                id: null,
                label: '',
            },
            reclassifyDialog: {
                open: false,
                kind: '',
                mode: '',
                sourceId: null,
                sourceName: '',
                sourceSubjectId: null,
                sourceTopicId: null,
                targetSubjectId: null,
                targetTopicId: null,
                newSubjectName: '',
                newTopicName: '',
            },
            isLoadingSubjectAssignments: false,
            isSavingSubjectAssignment: false,
            subjectAssignmentGroups: [],
            subjectAssignmentDialog: {
                open: false,
                mode: '',
                card: null,
                sourceRow: {
                    subject: '',
                    topic: '',
                    unit: '',
                },
                targetRow: {
                    subject: '',
                    topic: '',
                    unit: '',
                },
            },
            subjectAssignmentDeleteDialog: {
                open: false,
                card: null,
                sourceRow: {
                    subject: '',
                    topic: '',
                    unit: '',
                },
            },
        }
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        if (!this.materialCardStore?.config) {
            await this.materialCardStore.loadConfig()
        }
        this.applyInitialRouteSelection()
        this.syncFileSettingsForm()
        this.syncUserSettingsForm()
    },
    unmounted() {
        this.$emit('menu-lock-change', false)
    },
    computed: {
        visibleMenuItems() {
            return this.menuItems
        },
        selectedItemLabel() {
            const selected = this.visibleMenuItems.find((item) => item.value === this.selectedAction)
            if (selected) return selected.label
            return this.visibleMenuItems[0]?.label || 'Einstellungen'
        },
        selectedSubjectItemLabel() {
            const selected = this.subjectsMenuItems.find((item) => item.value === this.selectedSubjectAction)
            if (selected) return selected.label
            return this.subjectsMenuItems[0]?.label || 'Fächer'
        },
        subjectTreeItems() {
            const tree = Array.isArray(this.materialCardStore?.config?.classification_tree)
                ? this.materialCardStore.config.classification_tree
                : []

            return tree
                .map((subjectNode) => {
                    const subjectId = Number(subjectNode?.id)
                    const subjectName = this.normalizeTreeName(subjectNode?.name)
                    if (!subjectName) return null
                    const subjectCanDelete = subjectNode?.can_delete !== false

                    const topicNodes = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []
                    const topics = topicNodes
                        .map((topicNode) => {
                            const topicId = Number(topicNode?.id)
                            const topicName = this.normalizeTreeName(topicNode?.name)
                            if (!topicName) return null
                            const topicCanDelete = topicNode?.can_delete !== false

                            const unitNodes = Array.isArray(topicNode?.units) ? topicNode.units : []
                            const units = unitNodes
                                .map((unitNode) => {
                                    const unitId = Number(unitNode?.id)
                                    const unitName = this.normalizeTreeName(unitNode?.name)
                                    if (!unitName) return null
                                    const unitCanDelete = unitNode?.can_delete !== false
                                    return {
                                        id: Number.isFinite(unitId) && unitId > 0 ? unitId : null,
                                        name: unitName,
                                        canDelete: unitCanDelete,
                                    }
                                })
                                .filter(Boolean)

                            return {
                                id: Number.isFinite(topicId) && topicId > 0 ? topicId : null,
                                name: topicName,
                                canDelete: topicCanDelete,
                                units,
                            }
                        })
                        .filter(Boolean)

                    return {
                        id: Number.isFinite(subjectId) && subjectId > 0 ? subjectId : null,
                        name: subjectName,
                        canDelete: subjectCanDelete,
                        topics,
                    }
                })
                .filter(Boolean)
        },
        normalizedTypeOptions() {
            const list = Array.isArray(this.materialCardStore?.config?.type_values)
                ? this.materialCardStore.config.type_values
                : []

            return list
                .map((option) => {
                    if (!option || typeof option !== 'object') return null
                    const id = Number(option.id)
                    const value = String(option.value || '').trim()
                    const label = String(option.label || value).trim()
                    const icon = String(option.icon || '').trim()
                    const color = String(option.color || '').trim()
                    if (!value || !label) return null
                    return {
                        id: Number.isFinite(id) && id > 0 ? id : null,
                        value,
                        label,
                        icon: icon || 'mdi-file-document-outline',
                        color,
                    }
                })
                .filter(Boolean)
        },
        normalizedStatusOptions() {
            const list = Array.isArray(this.materialCardStore?.config?.status_values)
                ? this.materialCardStore.config.status_values
                : []

            return list
                .map((option) => {
                    if (!option || typeof option !== 'object') return null
                    const id = Number(option.id)
                    const value = String(option.value || '').trim()
                    const label = String(option.label || value).trim()
                    const color = String(option.color || '').trim()
                    if (!value || !label) return null
                    return {
                        id: Number.isFinite(id) && id > 0 ? id : null,
                        value,
                        label,
                        color,
                    }
                })
                .filter(Boolean)
        },
        canManageTypeValues() {
            return this.materialCardStore?.config?.can_manage_type_values === true
        },
        canManageStatusValues() {
            return this.materialCardStore?.config?.can_manage_status_values === true
        },
        canManageFileSettings() {
            return this.materialCardStore?.config?.can_manage_file_settings === true
        },
        canManageUserSettings() {
            return this.materialCardStore?.config?.can_manage_user_settings === true
        },
        currentMaxUploadSizeMb() {
            const value = Number(this.materialCardStore?.config?.file_settings?.max_upload_size_mb)
            if (!Number.isFinite(value) || value <= 0) {
                return '20'
            }
            return String(value)
        },
        currentMaterialsPaginationNumber() {
            const value = Number(this.materialCardStore?.config?.user_settings?.materials_pagination_number)
            if (!Number.isFinite(value) || value <= 0) {
                return '30'
            }
            return String(Math.round(value))
        },
        isSubjectCatalogEditorOpen() {
            return this.subjectCatalogEditor.mode !== ''
        },
        subjectCatalogEditorTitle() {
            const parent = this.normalizeTreeName(this.subjectCatalogEditor.parentLabel)
            switch (this.subjectCatalogEditor.mode) {
            case 'create_subject':
                return 'Neues Fach anlegen'
            case 'rename_subject':
                return 'Fach umbenennen'
            case 'create_topic':
                return parent ? `Neues Thema in "${parent}"` : 'Neues Thema anlegen'
            case 'rename_topic':
                return 'Thema umbenennen'
            case 'create_unit':
                return parent ? `Neuen Bereich in "${parent}"` : 'Neuen Bereich anlegen'
            case 'rename_unit':
                return 'Bereich umbenennen'
            default:
                return ''
            }
        },
        canSaveSubjectCatalogEditor() {
            return this.normalizeTreeName(this.subjectCatalogEditor.name) !== ''
        },
        reclassifyUsesTargetSubject() {
            if (this.reclassifyDialog.kind !== 'topic') return true
            return this.reclassifyDialog.mode !== 'to_new_subject'
        },
        reclassifyNeedsNewSubjectName() {
            return this.reclassifyDialog.kind === 'topic' && this.reclassifyDialog.mode === 'to_new_subject'
        },
        reclassifyNeedsNewTopicName() {
            return this.reclassifyDialog.kind === 'unit' && this.reclassifyDialog.mode === 'to_new_topic'
        },
        reclassifyNeedsTargetTopic() {
            if (this.reclassifyDialog.kind === 'unit') {
                return this.reclassifyDialog.mode !== 'to_new_topic'
            }
            if (this.reclassifyDialog.kind === 'topic' && this.reclassifyDialog.mode === 'to_unit') return true
            return false
        },
        reclassifyDialogHint() {
            if (this.reclassifyDialog.kind === 'subject') {
                return 'Das Fach wird als Thema im Zielfach angelegt.'
            }
            if (this.reclassifyDialog.kind === 'topic') {
                if (this.reclassifyDialog.mode === 'to_new_subject') {
                    return 'Das Thema wird als neues Fach angelegt. Einheiten werden dabei zu Themen.'
                }
                return this.reclassifyDialog.mode === 'to_unit'
                    ? 'Das Thema wird als Einheit im Zielthema angelegt.'
                    : 'Das Thema wird in ein anderes Fach verschoben.'
            }
            if (this.reclassifyDialog.kind === 'unit') {
                return this.reclassifyDialog.mode === 'to_new_topic'
                    ? 'Die Einheit wird als neues Thema angelegt.'
                    : 'Die Einheit wird in ein anderes Thema verschoben.'
            }
            return ''
        },
        reclassifyTargetSubjectItems() {
            const sourceKind = String(this.reclassifyDialog.kind || '')
            const sourceSubjectId = Number(this.reclassifyDialog.sourceSubjectId)
            const sourceId = Number(this.reclassifyDialog.sourceId)
            const subjects = Array.isArray(this.subjectTreeItems) ? this.subjectTreeItems : []

            return subjects
                .filter((subject) => {
                    const subjectId = Number(subject?.id)
                    if (!Number.isFinite(subjectId) || subjectId <= 0) return false
                    if (sourceKind === 'subject' && subjectId === sourceId) return false
                    return true
                })
                .map((subject) => ({
                    id: Number(subject.id),
                    name: subject.name,
                    _isCurrentSubject: sourceKind !== 'subject' && Number(subject.id) === sourceSubjectId,
                }))
        },
        reclassifyTargetTopicItems() {
            const subjectId = Number(this.reclassifyDialog.targetSubjectId)
            if (!Number.isFinite(subjectId) || subjectId <= 0) return []

            const subject = this.subjectTreeItems.find((entry) => Number(entry?.id) === subjectId)
            const topics = Array.isArray(subject?.topics) ? subject.topics : []
            const sourceKind = String(this.reclassifyDialog.kind || '')
            const mode = String(this.reclassifyDialog.mode || '')
            const sourceTopicId = sourceKind === 'unit'
                ? Number(this.reclassifyDialog.sourceTopicId)
                : Number(this.reclassifyDialog.sourceId)

            return topics
                .filter((topic) => {
                    const topicId = Number(topic?.id)
                    if (!Number.isFinite(topicId) || topicId <= 0) return false
                    if (sourceKind === 'topic' && mode === 'to_unit' && topicId === sourceTopicId) return false
                    if (sourceKind === 'unit' && mode === 'to_topic' && topicId === sourceTopicId) return false
                    return true
                })
                .map((topic) => ({
                    id: Number(topic.id),
                    name: topic.name,
                }))
        },
        canSaveReclassifyDialog() {
            if (!this.reclassifyDialog.open) return false
            const sourceId = Number(this.reclassifyDialog.sourceId)
            const targetSubjectId = Number(this.reclassifyDialog.targetSubjectId)
            const targetTopicId = Number(this.reclassifyDialog.targetTopicId)
            const newSubjectName = this.normalizeTreeName(this.reclassifyDialog.newSubjectName)
            const newTopicName = this.normalizeTreeName(this.reclassifyDialog.newTopicName)
            const kind = String(this.reclassifyDialog.kind || '')
            const mode = String(this.reclassifyDialog.mode || '')

            if (!Number.isFinite(sourceId) || sourceId <= 0) return false

            if (kind === 'subject') {
                if (!Number.isFinite(targetSubjectId) || targetSubjectId <= 0) return false
                return sourceId !== targetSubjectId
            }

            if (kind === 'topic') {
                if (mode === 'to_new_subject') {
                    return newSubjectName !== ''
                }
                if (mode === 'to_unit') {
                    return Number.isFinite(targetTopicId) && targetTopicId > 0 && targetTopicId !== sourceId
                }

                if (mode === 'to_subject') {
                    if (!Number.isFinite(targetSubjectId) || targetSubjectId <= 0) return false
                    const sourceSubjectId = Number(this.reclassifyDialog.sourceSubjectId)
                    return sourceSubjectId > 0 && sourceSubjectId !== targetSubjectId
                }
            }

            if (kind === 'unit') {
                if (mode === 'to_new_topic') {
                    return Number.isFinite(targetSubjectId) && targetSubjectId > 0 && newTopicName !== ''
                }
                const sourceTopicId = Number(this.reclassifyDialog.sourceTopicId)
                return Number.isFinite(targetTopicId) && targetTopicId > 0
                    && sourceTopicId > 0
                    && targetTopicId !== sourceTopicId
            }

            return false
        },
        subjectAssignmentDialogTitle() {
            if (this.subjectAssignmentDialog.mode === 'move') {
                return 'Material verschieben'
            }
            if (this.subjectAssignmentDialog.mode === 'copy') {
                return 'Material kopieren'
            }
            return 'Zuordnung bearbeiten'
        },
        subjectAssignmentDialogActionLabel() {
            return this.subjectAssignmentDialog.mode === 'move' ? 'Verschieben' : 'Kopieren'
        },
        subjectAssignmentDialogSourceLabel() {
            return this.classificationLabel(this.subjectAssignmentDialog.sourceRow)
        },
        subjectAssignmentDeleteDialogLabel() {
            return this.classificationLabel(this.subjectAssignmentDeleteDialog.sourceRow)
        },
        subjectAssignmentSubjectOptions() {
            return this.subjectTreeItems
                .map((subject) => this.normalizeTreeName(subject?.name))
                .filter((name) => name !== '')
        },
        subjectAssignmentTopicOptions() {
            const subjectName = this.normalizeTreeName(this.subjectAssignmentDialog.targetRow.subject)
            if (!subjectName) return []

            const subjectNode = this.subjectNodeByName(subjectName)
            const topics = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []
            return topics
                .map((topic) => this.normalizeTreeName(topic?.name))
                .filter((name) => name !== '')
        },
        subjectAssignmentUnitOptions() {
            const subjectName = this.normalizeTreeName(this.subjectAssignmentDialog.targetRow.subject)
            const topicName = this.normalizeTreeName(this.subjectAssignmentDialog.targetRow.topic)
            if (!subjectName || !topicName) return []

            const topicNode = this.topicNodeByNames(subjectName, topicName)
            const units = Array.isArray(topicNode?.units) ? topicNode.units : []
            return units
                .map((unit) => this.normalizeTreeName(unit?.name))
                .filter((name) => name !== '')
        },
        canSaveSubjectAssignmentDialog() {
            if (!this.subjectAssignmentDialog.open || this.isSavingSubjectAssignment) {
                return false
            }

            const mode = String(this.subjectAssignmentDialog.mode || '')
            if (mode !== 'move' && mode !== 'copy') {
                return false
            }

            const cardId = Number(this.subjectAssignmentDialog.card?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) {
                return false
            }

            const sourceRow = this.normalizeClassificationRow(this.subjectAssignmentDialog.sourceRow)
            const targetRow = this.normalizeClassificationRow(this.subjectAssignmentDialog.targetRow)
            if (!targetRow.subject) {
                return false
            }

            if (this.classificationRowsEqual(sourceRow, targetRow)) {
                return false
            }

            const currentRows = this.normalizeClassificationRows(this.subjectAssignmentDialog.card?.classifications)
            const hasSource = currentRows.some((row) => this.classificationRowsEqual(row, sourceRow))
            if (!hasSource) {
                return false
            }

            if (mode === 'copy') {
                const targetExists = currentRows.some((row) => this.classificationRowsEqual(row, targetRow))
                if (targetExists) {
                    return false
                }
            }

            return true
        },
        isAnySettingsEditActive() {
            return this.isEditingFileSettings
                || this.isSavingFileSettings
                || this.isEditingUserSettings
                || this.isSavingUserSettings
        },
        canSaveFileSettings() {
            const value = Number(String(this.fileSettingsForm.maxUploadSizeMb || '').replace(',', '.'))
            return Number.isFinite(value) && value > 0 && this.hasFileSettingsChanges
        },
        hasFileSettingsChanges() {
            const value = Number(String(this.fileSettingsForm.maxUploadSizeMb || '').replace(',', '.'))
            const current = Number(this.currentMaxUploadSizeMb)
            if (!Number.isFinite(value) || value <= 0 || !Number.isFinite(current) || current <= 0) {
                return false
            }

            return Math.abs(value - current) > 0.0001
        },
        canSaveUserSettings() {
            const value = Number(String(this.userSettingsForm.materialsPaginationNumber || '').replace(',', '.'))
            return Number.isFinite(value)
                && value >= 1
                && value <= 200
                && this.hasUserSettingsChanges
        },
        hasUserSettingsChanges() {
            const value = Number(String(this.userSettingsForm.materialsPaginationNumber || '').replace(',', '.'))
            const current = Number(this.currentMaterialsPaginationNumber)
            if (!Number.isFinite(value) || value < 1 || value > 200 || !Number.isFinite(current) || current <= 0) {
                return false
            }

            return Math.round(value) !== Math.round(current)
        },
    },
    watch: {
        isAnySettingsEditActive: {
            immediate: true,
            handler(value) {
                this.$emit('menu-lock-change', !!value)
            },
        },
        visibleMenuItems: {
            immediate: true,
            handler(items) {
                if (!Array.isArray(items) || !items.length) {
                    this.selectedAction = ''
                    return
                }
                if (!items.some((item) => item.value === this.selectedAction)) {
                    this.selectedAction = items[0].value
                }
            },
        },
        selectedAction(value) {
            if (value !== 'subjects') return
            if (!this.subjectsMenuItems.some((item) => item.value === this.selectedSubjectAction)) {
                this.selectedSubjectAction = this.subjectsMenuItems[0]?.value || ''
            }
            if (this.selectedSubjectAction === 'subjects_groups') {
                this.loadSubjectAssignments()
            }
        },
        selectedSubjectAction(value) {
            if (value === 'subjects_groups') {
                this.loadSubjectAssignments()
            }
        },
        initialSelectedAction: {
            immediate: true,
            handler() {
                this.applyInitialRouteSelection()
            },
        },
        initialSelectedSubjectAction: {
            immediate: true,
            handler() {
                this.applyInitialRouteSelection()
            },
        },
        'materialCardStore.config.file_settings': {
            deep: true,
            handler() {
                this.syncFileSettingsForm()
            },
        },
        'materialCardStore.config.user_settings': {
            deep: true,
            handler() {
                this.syncUserSettingsForm()
            },
        },
    },
    methods: {
        applyInitialRouteSelection() {
            const action = this.normalizeTreeName(this.initialSelectedAction)
            const subjectAction = this.normalizeTreeName(this.initialSelectedSubjectAction)

            if (this.standalone && action) {
                this.selectedAction = action
            } else {
                const availableActions = Array.isArray(this.visibleMenuItems) ? this.visibleMenuItems.map((item) => item.value) : []
                if (action && availableActions.includes(action)) {
                    this.selectedAction = action
                }
            }

            if (this.selectedAction !== 'subjects') {
                return
            }

            const availableSubjectActions = Array.isArray(this.subjectsMenuItems)
                ? this.subjectsMenuItems.map((item) => item.value)
                : []
            if (subjectAction && availableSubjectActions.includes(subjectAction)) {
                this.selectedSubjectAction = subjectAction
            }
        },
        normalizeTreeName(value) {
            return String(value ?? '').trim()
        },
        stringHash(value) {
            const input = String(value ?? '')
            let hash = 0
            for (let index = 0; index < input.length; index += 1) {
                hash = ((hash << 5) - hash) + input.charCodeAt(index)
                hash |= 0
            }
            return Math.abs(hash)
        },
        subjectColorSeed(subject) {
            const subjectId = Number(subject?.id)
            const subjectName = this.normalizeTreeName(subject?.name).toLowerCase()
            return Number.isFinite(subjectId) && subjectId > 0
                ? `subject-${subjectId}`
                : `subject-${subjectName}`
        },
        subjectColorHue(seed) {
            // Deliberately excludes violet/purple tones.
            const hues = [12, 22, 34, 46, 58, 74, 96, 122, 148, 176, 198, 214]
            const hash = this.stringHash(seed)
            return hues[hash % hues.length]
        },
        subjectColorTokens() {
            return {
                base: '#1f6f8b',
                soft: 'rgba(31, 111, 139, 0.14)',
            }
        },
        subjectGroupStyle(subject) {
            const tokens = this.subjectColorTokens(subject)

            return {
                backgroundColor: tokens.soft,
                borderColor: tokens.base,
            }
        },
        topicGroupStyle(subject) {
            const tokens = this.subjectColorTokens(subject)

            return {
                '--topic-accent-color': tokens.base,
            }
        },
        normalizeClassificationRow(row) {
            const subject = this.normalizeTreeName(row?.subject).slice(0, 255)
            const topic = this.normalizeTreeName(row?.topic).slice(0, 255)
            let unit = this.normalizeTreeName(row?.unit).slice(0, 255)
            if (!topic) {
                unit = ''
            }

            return { subject, topic, unit }
        },
        normalizeClassificationRows(rows) {
            const list = Array.isArray(rows) ? rows : []
            const result = []
            const seen = new Set()

            for (const row of list) {
                const normalizedRow = this.normalizeClassificationRow(row)
                if (!normalizedRow.subject) continue
                const key = this.classificationKey(normalizedRow)
                if (seen.has(key)) continue
                seen.add(key)
                result.push(normalizedRow)
            }

            return result
        },
        classificationKey(row) {
            const normalizedRow = this.normalizeClassificationRow(row)
            return `${normalizedRow.subject.toLocaleLowerCase()}|${normalizedRow.topic.toLocaleLowerCase()}|${normalizedRow.unit.toLocaleLowerCase()}`
        },
        classificationRowsEqual(a, b) {
            return this.classificationKey(a) === this.classificationKey(b)
        },
        classificationLabel(row) {
            const normalizedRow = this.normalizeClassificationRow(row)
            let label = normalizedRow.subject
            if (normalizedRow.topic) label += ` / ${normalizedRow.topic}`
            if (normalizedRow.unit) label += ` / ${normalizedRow.unit}`
            return label
        },
        subjectNodeByName(name) {
            const normalizedName = this.normalizeTreeName(name)
            if (!normalizedName) return null

            return this.subjectTreeItems.find(
                (subjectNode) => this.normalizeTreeName(subjectNode?.name).toLocaleLowerCase() === normalizedName.toLocaleLowerCase()
            ) || null
        },
        topicNodeByNames(subjectName, topicName) {
            const subjectNode = this.subjectNodeByName(subjectName)
            const normalizedTopicName = this.normalizeTreeName(topicName)
            if (!subjectNode || !normalizedTopicName) return null

            const topicNodes = Array.isArray(subjectNode.topics) ? subjectNode.topics : []
            return topicNodes.find(
                (topicNode) => this.normalizeTreeName(topicNode?.name).toLocaleLowerCase() === normalizedTopicName.toLocaleLowerCase()
            ) || null
        },
        buildSubjectAssignmentGroups(cards) {
            const groupsByKey = new Map()
            const cardList = Array.isArray(cards) ? cards : []

            for (const card of cardList) {
                const cardId = Number(card?.id)
                if (!Number.isFinite(cardId) || cardId <= 0) continue

                const title = this.normalizeTreeName(card?.title) || 'Ohne Titel'
                const rows = this.normalizeClassificationRows(card?.classifications)

                for (const row of rows) {
                    const key = this.classificationKey(row)
                    if (!groupsByKey.has(key)) {
                        const level = row.unit ? 'unit' : row.topic ? 'topic' : 'subject'
                        const levelLabel = level === 'unit' ? 'Einheit' : level === 'topic' ? 'Thema' : 'Fach'
                        groupsByKey.set(key, {
                            key,
                            level,
                            levelLabel,
                            subject: row.subject,
                            topic: row.topic,
                            unit: row.unit,
                            label: this.classificationLabel(row),
                            materials: [],
                        })
                    }

                    const group = groupsByKey.get(key)
                    if (!group.materials.some((item) => item.cardId === cardId)) {
                        group.materials.push({
                            cardId,
                            title,
                            card,
                            sourceRow: row,
                        })
                    }
                }
            }

            const groups = Array.from(groupsByKey.values())
            groups.sort((a, b) => a.label.localeCompare(b.label, undefined, { sensitivity: 'base' }))
            for (const group of groups) {
                group.materials.sort((a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }))
            }

            return groups
        },
        async loadSubjectAssignments(force = false) {
            if (this.isLoadingSubjectAssignments) return
            if (!force && Array.isArray(this.subjectAssignmentGroups) && this.subjectAssignmentGroups.length > 0) {
                return
            }

            this.isLoadingSubjectAssignments = true
            try {
                const cards = await this.materialCardStore.listAllCardsSnapshot({})
                if (!Array.isArray(cards)) return
                this.subjectAssignmentGroups = this.buildSubjectAssignmentGroups(cards)
            } finally {
                this.isLoadingSubjectAssignments = false
            }
        },
        canDeleteSubjectAssignment(material, group) {
            const card = material?.card
            const sourceRow = this.normalizeClassificationRow(material?.sourceRow || group)
            const cardId = Number(card?.id)
            if (!Number.isFinite(cardId) || cardId <= 0 || !sourceRow.subject) {
                return false
            }

            const currentRows = this.normalizeClassificationRows(card?.classifications)
            if (currentRows.length <= 1) {
                return false
            }

            return currentRows.some((row) => this.classificationRowsEqual(row, sourceRow))
        },
        openSubjectAssignmentDialog(mode, group, material) {
            const normalizedMode = String(mode || '') === 'move' ? 'move' : 'copy'
            const card = material?.card
            const sourceRow = this.normalizeClassificationRow(material?.sourceRow || group)
            const cardId = Number(card?.id)
            if (!Number.isFinite(cardId) || cardId <= 0 || !sourceRow.subject) {
                return
            }

            this.subjectAssignmentDialog = {
                open: true,
                mode: normalizedMode,
                card,
                sourceRow: { ...sourceRow },
                targetRow: { ...sourceRow },
            }
        },
        openSubjectAssignmentDeleteDialog(group, material) {
            if (!this.canDeleteSubjectAssignment(material, group) || this.isSavingSubjectAssignment) {
                return
            }

            const sourceRow = this.normalizeClassificationRow(material?.sourceRow || group)
            this.subjectAssignmentDeleteDialog = {
                open: true,
                card: material?.card || null,
                sourceRow: { ...sourceRow },
            }
        },
        closeSubjectAssignmentDeleteDialog() {
            if (this.isSavingSubjectAssignment) return
            this.subjectAssignmentDeleteDialog = {
                open: false,
                card: null,
                sourceRow: {
                    subject: '',
                    topic: '',
                    unit: '',
                },
            }
        },
        async confirmSubjectAssignmentDelete() {
            if (this.isSavingSubjectAssignment) return

            const card = this.subjectAssignmentDeleteDialog.card
            const sourceRow = this.normalizeClassificationRow(this.subjectAssignmentDeleteDialog.sourceRow)
            const cardId = Number(card?.id)
            if (!Number.isFinite(cardId) || cardId <= 0 || !sourceRow.subject) {
                return
            }

            const currentRows = this.normalizeClassificationRows(card?.classifications)
            if (currentRows.length <= 1) {
                return
            }

            let removed = false
            const nextRows = currentRows.filter((row) => {
                if (!removed && this.classificationRowsEqual(row, sourceRow)) {
                    removed = true
                    return false
                }
                return true
            })

            if (!removed || nextRows.length < 1) {
                return
            }

            const payload = this.buildMaterialUpdatePayload(card, nextRows)
            if (!payload) {
                return
            }

            this.isSavingSubjectAssignment = true
            const updated = await this.materialCardStore.update(cardId, payload)
            this.isSavingSubjectAssignment = false

            if (updated) {
                this.closeSubjectAssignmentDeleteDialog()
                await this.loadSubjectAssignments(true)
            }
        },
        closeSubjectAssignmentDialog() {
            if (this.isSavingSubjectAssignment) return
            this.subjectAssignmentDialog = {
                open: false,
                mode: '',
                card: null,
                sourceRow: {
                    subject: '',
                    topic: '',
                    unit: '',
                },
                targetRow: {
                    subject: '',
                    topic: '',
                    unit: '',
                },
            }
        },
        updateSubjectAssignmentTargetSubject(value) {
            const subject = this.normalizeTreeName(value).slice(0, 255)
            this.subjectAssignmentDialog.targetRow.subject = subject
            this.subjectAssignmentDialog.targetRow.topic = ''
            this.subjectAssignmentDialog.targetRow.unit = ''
        },
        updateSubjectAssignmentTargetTopic(value) {
            const topic = this.normalizeTreeName(value).slice(0, 255)
            this.subjectAssignmentDialog.targetRow.topic = topic
            this.subjectAssignmentDialog.targetRow.unit = ''
        },
        updateSubjectAssignmentTargetUnit(value) {
            const unit = this.normalizeTreeName(value).slice(0, 255)
            this.subjectAssignmentDialog.targetRow.unit = unit
        },
        nullableText(value, maxLength = 255) {
            const text = this.normalizeTreeName(value).slice(0, maxLength)
            return text !== '' ? text : null
        },
        buildMaterialUpdatePayload(card, classifications) {
            const title = this.normalizeTreeName(card?.title).slice(0, 255)
            if (!title) return null

            const status = this.normalizeTreeName(card?.status).slice(0, 255)
            const fallbackStatus = this.normalizeTreeName(this.normalizedStatusOptions?.[0]?.value).slice(0, 255)

            return {
                title,
                source_url: this.nullableText(card?.source_url, 2048),
                source_text: this.nullableText(card?.source_text, 10000),
                subject: this.nullableText(card?.subject, 255),
                area: this.nullableText(card?.area, 255),
                unit: this.nullableText(card?.unit, 255),
                type: this.nullableText(card?.type, 255),
                status: status || fallbackStatus || null,
                notes: this.nullableText(card?.notes, 4000),
                classifications: this.normalizeClassificationRows(classifications),
            }
        },
        async saveSubjectAssignmentDialog() {
            if (!this.canSaveSubjectAssignmentDialog || this.isSavingSubjectAssignment) return

            const mode = String(this.subjectAssignmentDialog.mode || '')
            const card = this.subjectAssignmentDialog.card
            const sourceRow = this.normalizeClassificationRow(this.subjectAssignmentDialog.sourceRow)
            const targetRow = this.normalizeClassificationRow(this.subjectAssignmentDialog.targetRow)
            const currentRows = this.normalizeClassificationRows(card?.classifications)

            let nextRows = currentRows.map((row) => ({ ...row }))

            if (mode === 'copy') {
                nextRows.push(targetRow)
            } else {
                let replaced = false
                nextRows = nextRows.map((row) => {
                    if (!replaced && this.classificationRowsEqual(row, sourceRow)) {
                        replaced = true
                        return { ...targetRow }
                    }
                    return row
                })

                if (!replaced) {
                    return
                }
            }

            nextRows = this.normalizeClassificationRows(nextRows)
            const payload = this.buildMaterialUpdatePayload(card, nextRows)
            const cardId = Number(card?.id)
            if (!payload || !Number.isFinite(cardId) || cardId <= 0) {
                return
            }

            this.isSavingSubjectAssignment = true
            const updated = await this.materialCardStore.update(cardId, payload)
            this.isSavingSubjectAssignment = false

            if (updated) {
                this.closeSubjectAssignmentDialog()
                await this.loadSubjectAssignments(true)
            }
        },
        resetSubjectCatalogEditor() {
            this.subjectCatalogEditor = {
                mode: '',
                name: '',
                subjectId: null,
                topicId: null,
                unitId: null,
                parentLabel: '',
            }
        },
        focusSubjectCatalogEditorInput() {
            this.$nextTick(() => {
                const field = this.$refs.subjectCatalogEditorNameField
                if (field && typeof field.focus === 'function') {
                    field.focus()
                    return
                }

                const input = field?.$el?.querySelector?.('input')
                if (input && typeof input.focus === 'function') {
                    input.focus()
                }
            })
        },
        startCreateSubject() {
            this.subjectCatalogEditor = {
                mode: 'create_subject',
                name: '',
                subjectId: null,
                topicId: null,
                unitId: null,
                parentLabel: '',
            }
            this.focusSubjectCatalogEditorInput()
        },
        startRenameSubject(subject) {
            const id = Number(subject?.id)
            if (!Number.isFinite(id) || id <= 0) return
            this.subjectCatalogEditor = {
                mode: 'rename_subject',
                name: this.normalizeTreeName(subject?.name),
                subjectId: id,
                topicId: null,
                unitId: null,
                parentLabel: '',
            }
            this.focusSubjectCatalogEditorInput()
        },
        startCreateTopic(subject) {
            const subjectId = Number(subject?.id)
            if (!Number.isFinite(subjectId) || subjectId <= 0) return
            this.subjectCatalogEditor = {
                mode: 'create_topic',
                name: '',
                subjectId,
                topicId: null,
                unitId: null,
                parentLabel: this.normalizeTreeName(subject?.name),
            }
            this.focusSubjectCatalogEditorInput()
        },
        startRenameTopic(topic) {
            const topicId = Number(topic?.id)
            if (!Number.isFinite(topicId) || topicId <= 0) return
            this.subjectCatalogEditor = {
                mode: 'rename_topic',
                name: this.normalizeTreeName(topic?.name),
                subjectId: null,
                topicId,
                unitId: null,
                parentLabel: '',
            }
            this.focusSubjectCatalogEditorInput()
        },
        startCreateUnit(topic) {
            const topicId = Number(topic?.id)
            if (!Number.isFinite(topicId) || topicId <= 0) return
            this.subjectCatalogEditor = {
                mode: 'create_unit',
                name: '',
                subjectId: null,
                topicId,
                unitId: null,
                parentLabel: this.normalizeTreeName(topic?.name),
            }
            this.focusSubjectCatalogEditorInput()
        },
        startRenameUnit(unit) {
            const unitId = Number(unit?.id)
            if (!Number.isFinite(unitId) || unitId <= 0) return
            this.subjectCatalogEditor = {
                mode: 'rename_unit',
                name: this.normalizeTreeName(unit?.name),
                subjectId: null,
                topicId: null,
                unitId,
                parentLabel: '',
            }
            this.focusSubjectCatalogEditorInput()
        },
        canMoveSubjectUp(subject, subjectIndex) {
            const subjectId = Number(subject?.id)
            return Number.isFinite(subjectId) && subjectId > 0 && Number(subjectIndex) > 0
        },
        canMoveSubjectDown(subject, subjectIndex) {
            const subjectId = Number(subject?.id)
            const index = Number(subjectIndex)
            const subjectCount = Array.isArray(this.subjectTreeItems) ? this.subjectTreeItems.length : 0
            return Number.isFinite(subjectId) && subjectId > 0
                && Number.isInteger(index)
                && index >= 0
                && index < subjectCount - 1
        },
        canMoveTopicUp(subject, topic, topicIndex) {
            const topicId = Number(topic?.id)
            const topics = Array.isArray(subject?.topics) ? subject.topics : []
            return Number.isFinite(topicId) && topicId > 0
                && topics.length > 1
                && Number(topicIndex) > 0
        },
        canMoveTopicDown(subject, topic, topicIndex) {
            const topicId = Number(topic?.id)
            const index = Number(topicIndex)
            const topics = Array.isArray(subject?.topics) ? subject.topics : []
            return Number.isFinite(topicId) && topicId > 0
                && topics.length > 1
                && Number.isInteger(index)
                && index >= 0
                && index < topics.length - 1
        },
        canMoveUnitUp(topic, unit, unitIndex) {
            const unitId = Number(unit?.id)
            const units = Array.isArray(topic?.units) ? topic.units : []
            return Number.isFinite(unitId) && unitId > 0
                && units.length > 1
                && Number(unitIndex) > 0
        },
        canMoveUnitDown(topic, unit, unitIndex) {
            const unitId = Number(unit?.id)
            const index = Number(unitIndex)
            const units = Array.isArray(topic?.units) ? topic.units : []
            return Number.isFinite(unitId) && unitId > 0
                && units.length > 1
                && Number.isInteger(index)
                && index >= 0
                && index < units.length - 1
        },
        async moveSubject(subject, direction) {
            if (this.isSavingSubjectCatalog) return
            const subjectId = Number(subject?.id)
            const normalizedDirection = String(direction || '').toLowerCase()
            if (!Number.isFinite(subjectId) || subjectId <= 0) return
            if (!['up', 'down'].includes(normalizedDirection)) return

            await this.withSubjectCatalogSaving(() =>
                this.materialCardStore.moveSubject(subjectId, normalizedDirection)
            )
        },
        async moveTopic(topic, direction) {
            if (this.isSavingSubjectCatalog) return
            const topicId = Number(topic?.id)
            const normalizedDirection = String(direction || '').toLowerCase()
            if (!Number.isFinite(topicId) || topicId <= 0) return
            if (!['up', 'down'].includes(normalizedDirection)) return

            await this.withSubjectCatalogSaving(() =>
                this.materialCardStore.moveTopic(topicId, normalizedDirection)
            )
        },
        async moveUnit(unit, direction) {
            if (this.isSavingSubjectCatalog) return
            const unitId = Number(unit?.id)
            const normalizedDirection = String(direction || '').toLowerCase()
            if (!Number.isFinite(unitId) || unitId <= 0) return
            if (!['up', 'down'].includes(normalizedDirection)) return

            await this.withSubjectCatalogSaving(() =>
                this.materialCardStore.moveUnit(unitId, normalizedDirection)
            )
        },
        resetReclassifyDialog() {
            this.reclassifyDialog = {
                open: false,
                kind: '',
                mode: '',
                sourceId: null,
                sourceName: '',
                sourceSubjectId: null,
                sourceTopicId: null,
                targetSubjectId: null,
                targetTopicId: null,
                newSubjectName: '',
                newTopicName: '',
            }
        },
        closeReclassifyDialog() {
            if (this.isSavingSubjectCatalog) return
            this.resetReclassifyDialog()
        },
        openReclassifyDialogForSubject(subject) {
            const sourceId = Number(subject?.id)
            if (!Number.isFinite(sourceId) || sourceId <= 0 || this.isSavingSubjectCatalog) return

            const targetSubject = this.reclassifyTargetSubjectItems.find((entry) => Number(entry.id) !== sourceId)

            this.reclassifyDialog = {
                open: true,
                kind: 'subject',
                mode: 'to_topic',
                sourceId,
                sourceName: this.normalizeTreeName(subject?.name),
                sourceSubjectId: sourceId,
                sourceTopicId: null,
                targetSubjectId: targetSubject?.id || null,
                targetTopicId: null,
                newSubjectName: '',
                newTopicName: '',
            }
        },
        openReclassifyDialogForTopic(subject, topic) {
            const sourceId = Number(topic?.id)
            const sourceSubjectId = Number(subject?.id)
            if (!Number.isFinite(sourceId) || sourceId <= 0 || this.isSavingSubjectCatalog) return
            if (!Number.isFinite(sourceSubjectId) || sourceSubjectId <= 0) return

            const targetSubject = this.subjectTreeItems.find((entry) => Number(entry?.id) === sourceSubjectId)
                || this.reclassifyTargetSubjectItems[0]

            this.reclassifyDialog = {
                open: true,
                kind: 'topic',
                mode: 'to_subject',
                sourceId,
                sourceName: this.normalizeTreeName(topic?.name),
                sourceSubjectId,
                sourceTopicId: sourceId,
                targetSubjectId: targetSubject?.id || null,
                targetTopicId: null,
                newSubjectName: this.normalizeTreeName(topic?.name),
                newTopicName: '',
            }
        },
        openReclassifyDialogForUnit(subject, topic, unit) {
            const sourceId = Number(unit?.id)
            const sourceSubjectId = Number(subject?.id)
            const sourceTopicId = Number(topic?.id)
            if (!Number.isFinite(sourceId) || sourceId <= 0 || this.isSavingSubjectCatalog) return
            if (!Number.isFinite(sourceSubjectId) || sourceSubjectId <= 0) return
            if (!Number.isFinite(sourceTopicId) || sourceTopicId <= 0) return

            this.reclassifyDialog = {
                open: true,
                kind: 'unit',
                mode: 'to_topic',
                sourceId,
                sourceName: this.normalizeTreeName(unit?.name),
                sourceSubjectId,
                sourceTopicId,
                targetSubjectId: sourceSubjectId,
                targetTopicId: null,
                newSubjectName: '',
                newTopicName: this.normalizeTreeName(unit?.name),
            }

            const firstTopic = this.reclassifyTargetTopicItems[0]
            this.reclassifyDialog.targetTopicId = firstTopic?.id || null
        },
        setReclassifyMode(mode) {
            const normalizedMode = String(mode || '')
            if (!['to_subject', 'to_unit', 'to_new_subject', 'to_topic', 'to_new_topic'].includes(normalizedMode)) return
            this.reclassifyDialog.mode = normalizedMode
            this.reclassifyDialog.targetTopicId = null
            if (normalizedMode === 'to_unit' || (this.reclassifyDialog.kind === 'unit' && normalizedMode === 'to_topic')) {
                const firstTopic = this.reclassifyTargetTopicItems[0]
                this.reclassifyDialog.targetTopicId = firstTopic?.id || null
                return
            }
            if (normalizedMode === 'to_new_subject' && !this.normalizeTreeName(this.reclassifyDialog.newSubjectName)) {
                this.reclassifyDialog.newSubjectName = this.normalizeTreeName(this.reclassifyDialog.sourceName)
                return
            }
            if (normalizedMode === 'to_new_topic' && !this.normalizeTreeName(this.reclassifyDialog.newTopicName)) {
                this.reclassifyDialog.newTopicName = this.normalizeTreeName(this.reclassifyDialog.sourceName)
            }
        },
        onReclassifyTargetSubjectChange() {
            if (!this.reclassifyNeedsTargetTopic) return
            const firstTopic = this.reclassifyTargetTopicItems[0]
            this.reclassifyDialog.targetTopicId = firstTopic?.id || null
        },
        async saveReclassifyDialog() {
            if (!this.canSaveReclassifyDialog || this.isSavingSubjectCatalog) return

            const kind = String(this.reclassifyDialog.kind || '')
            const mode = String(this.reclassifyDialog.mode || '')
            const sourceId = Number(this.reclassifyDialog.sourceId)
            const targetSubjectId = Number(this.reclassifyDialog.targetSubjectId)
            const targetTopicId = Number(this.reclassifyDialog.targetTopicId)
            const newSubjectName = this.normalizeTreeName(this.reclassifyDialog.newSubjectName).slice(0, 255)
            const newTopicName = this.normalizeTreeName(this.reclassifyDialog.newTopicName).slice(0, 255)

            const result = await this.withSubjectCatalogSaving(async () => {
                if (kind === 'subject') {
                    return this.materialCardStore.convertSubjectToTopic(sourceId, targetSubjectId)
                }
                if (kind === 'topic' && mode === 'to_subject') {
                    return this.materialCardStore.moveTopicToSubject(sourceId, targetSubjectId)
                }
                if (kind === 'topic' && mode === 'to_new_subject') {
                    return this.materialCardStore.convertTopicToSubject(sourceId, newSubjectName)
                }
                if (kind === 'topic' && mode === 'to_unit') {
                    return this.materialCardStore.convertTopicToUnit(sourceId, targetTopicId)
                }
                if (kind === 'unit') {
                    if (mode === 'to_new_topic') {
                        return this.materialCardStore.convertUnitToTopic(sourceId, targetSubjectId, newTopicName)
                    }
                    return this.materialCardStore.moveUnitToTopic(sourceId, targetTopicId)
                }
                return null
            })

            if (result) {
                this.resetReclassifyDialog()
            }
        },
        cancelSubjectCatalogEditor() {
            if (this.isSavingSubjectCatalog) return
            this.resetSubjectCatalogEditor()
        },
        openDeleteConfirm(kind, row) {
            const id = Number(row?.id)
            if (!Number.isFinite(id) || id <= 0 || this.isSavingSubjectCatalog) return

            const allowedKinds = ['subject', 'topic', 'unit']
            const normalizedKind = allowedKinds.includes(String(kind)) ? String(kind) : ''
            if (!normalizedKind) return

            const label = this.normalizeTreeName(row?.name)
            this.deleteConfirmDialog = {
                open: true,
                kind: normalizedKind,
                id,
                label,
            }
        },
        cancelDeleteConfirm() {
            if (this.isSavingSubjectCatalog) return
            this.deleteConfirmDialog = {
                open: false,
                kind: '',
                id: null,
                label: '',
            }
        },
        async confirmDeleteEntry() {
            const id = Number(this.deleteConfirmDialog.id)
            const kind = String(this.deleteConfirmDialog.kind || '')
            if (!Number.isFinite(id) || id <= 0 || !kind || this.isSavingSubjectCatalog) return

            if (kind === 'subject' && this.subjectCatalogEditor.subjectId === id) {
                this.resetSubjectCatalogEditor()
            }
            if (kind === 'topic' && this.subjectCatalogEditor.topicId === id) {
                this.resetSubjectCatalogEditor()
            }
            if (kind === 'unit' && this.subjectCatalogEditor.unitId === id) {
                this.resetSubjectCatalogEditor()
            }

            const result = await this.withSubjectCatalogSaving(async () => {
                if (kind === 'subject') return this.materialCardStore.deleteSubject(id)
                if (kind === 'topic') return this.materialCardStore.deleteTopic(id)
                if (kind === 'unit') return this.materialCardStore.deleteUnit(id)
                return false
            })

            if (result) {
                this.cancelDeleteConfirm()
            }
        },
        async withSubjectCatalogSaving(task) {
            if (this.isSavingSubjectCatalog) return null
            this.isSavingSubjectCatalog = true
            try {
                return await task()
            } finally {
                this.isSavingSubjectCatalog = false
            }
        },
        async saveSubjectCatalogEditor() {
            if (!this.canSaveSubjectCatalogEditor || this.isSavingSubjectCatalog) return

            const mode = this.subjectCatalogEditor.mode
            const name = this.normalizeTreeName(this.subjectCatalogEditor.name).slice(0, 255)
            if (!name) return

            const subjectId = Number(this.subjectCatalogEditor.subjectId)
            const topicId = Number(this.subjectCatalogEditor.topicId)
            const unitId = Number(this.subjectCatalogEditor.unitId)

            const result = await this.withSubjectCatalogSaving(async () => {
                if (mode === 'create_subject') {
                    return this.materialCardStore.createSubject(name)
                }
                if (mode === 'rename_subject' && Number.isFinite(subjectId) && subjectId > 0) {
                    return this.materialCardStore.updateSubject(subjectId, name)
                }
                if (mode === 'create_topic' && Number.isFinite(subjectId) && subjectId > 0) {
                    return this.materialCardStore.createTopic(subjectId, name)
                }
                if (mode === 'rename_topic' && Number.isFinite(topicId) && topicId > 0) {
                    return this.materialCardStore.updateTopic(topicId, name)
                }
                if (mode === 'create_unit' && Number.isFinite(topicId) && topicId > 0) {
                    return this.materialCardStore.createUnit(topicId, name)
                }
                if (mode === 'rename_unit' && Number.isFinite(unitId) && unitId > 0) {
                    return this.materialCardStore.updateUnit(unitId, name)
                }
                return null
            })

            if (result) {
                this.resetSubjectCatalogEditor()
            }
        },
        syncFileSettingsForm() {
            const value = Number(this.materialCardStore?.config?.file_settings?.max_upload_size_mb)
            if (!Number.isFinite(value) || value <= 0) {
                this.fileSettingsForm.maxUploadSizeMb = '20'
                return
            }
            this.fileSettingsForm.maxUploadSizeMb = String(value)
        },
        syncUserSettingsForm() {
            const value = Number(this.materialCardStore?.config?.user_settings?.materials_pagination_number)
            if (!Number.isFinite(value) || value <= 0) {
                this.userSettingsForm.materialsPaginationNumber = '30'
                return
            }
            this.userSettingsForm.materialsPaginationNumber = String(Math.round(value))
        },
        focusFileSettingsInput() {
            this.$nextTick(() => {
                const field = this.$refs.fileSettingsMaxUploadSizeField
                if (field && typeof field.focus === 'function') {
                    field.focus()
                }
                const input = field?.$el?.querySelector?.('input')
                if (input && typeof input.focus === 'function') {
                    input.focus()
                    input.select?.()
                }
            })
        },
        focusUserSettingsInput() {
            this.$nextTick(() => {
                const field = this.$refs.userSettingsPaginationField
                if (field && typeof field.focus === 'function') {
                    field.focus()
                }
                const input = field?.$el?.querySelector?.('input')
                if (input && typeof input.focus === 'function') {
                    input.focus()
                    input.select?.()
                }
            })
        },
        startEditFileSettings() {
            if (!this.canManageFileSettings || this.isSavingFileSettings || this.isAnySettingsEditActive) return
            this.syncFileSettingsForm()
            this.isEditingFileSettings = true
            this.focusFileSettingsInput()
        },
        cancelEditFileSettings() {
            if (this.isSavingFileSettings) return
            this.syncFileSettingsForm()
            this.isEditingFileSettings = false
        },
        async saveFileSettings() {
            if (!this.canManageFileSettings || !this.canSaveFileSettings || this.isSavingFileSettings) return

            const valueMb = Number(String(this.fileSettingsForm.maxUploadSizeMb || '').replace(',', '.'))
            const valueKb = Math.max(1, Math.round(valueMb * 1024))

            this.isSavingFileSettings = true
            const saved = await this.materialCardStore.updateFileSettings(valueKb)
            this.isSavingFileSettings = false

            if (saved) {
                this.syncFileSettingsForm()
                this.isEditingFileSettings = false
            }
        },
        startEditUserSettings() {
            if (!this.canManageUserSettings || this.isSavingUserSettings || this.isAnySettingsEditActive) return
            this.syncUserSettingsForm()
            this.isEditingUserSettings = true
            this.focusUserSettingsInput()
        },
        cancelEditUserSettings() {
            if (this.isSavingUserSettings) return
            this.syncUserSettingsForm()
            this.isEditingUserSettings = false
        },
        async saveUserSettings() {
            if (!this.canManageUserSettings || !this.canSaveUserSettings || this.isSavingUserSettings) return

            const value = Number(String(this.userSettingsForm.materialsPaginationNumber || '').replace(',', '.'))
            const normalized = Math.max(1, Math.min(200, Math.round(value)))

            this.isSavingUserSettings = true
            const saved = await this.materialCardStore.updateUserSettings(normalized)
            this.isSavingUserSettings = false

            if (saved) {
                this.syncUserSettingsForm()
                this.isEditingUserSettings = false
            }
        },
    },
}
</script>

<style scoped>
.settings-menu-btn {
    border: 1px solid rgba(35, 61, 76, 0.22);
    background: rgba(248, 239, 231, 0.65);
    color: #233d4c;
    font-weight: 700;
}

.settings-menu-btn--active {
    border-color: rgba(253, 128, 46, 0.9);
    background: linear-gradient(155deg, #fd802e 0%, #ff8f42 100%);
    color: #233d4c;
}

.settings-submenu-btn {
    border: 1px solid rgba(35, 61, 76, 0.18);
    background: rgba(248, 239, 231, 0.6);
    color: #233d4c;
    font-weight: 700;
}

.settings-submenu-btn--active {
    border-color: rgba(253, 128, 46, 0.9);
    background: linear-gradient(155deg, #fd802e 0%, #ff8f42 100%);
    color: #233d4c;
}

.settings-empty-card {
    min-height: 120px;
}

.subject-assignments-panels {
    display: grid;
    gap: 8px;
}

.subject-assignment-material-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 8px 12px;
    padding: 8px 0;
    border-top: 1px solid rgba(35, 61, 76, 0.12);
}

.subject-assignment-material-row:first-child {
    border-top: 0;
    padding-top: 0;
}

.subject-assignment-material-meta {
    min-width: 220px;
    flex: 1 1 auto;
}

.subject-assignment-action-btn {
    text-transform: none;
    letter-spacing: normal;
}

.subject-assignment-info {
    border: 1px solid rgba(35, 61, 76, 0.16);
    border-radius: 10px;
    background: rgba(35, 61, 76, 0.04);
    padding: 10px 12px;
}

.subject-assignment-info-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(35, 61, 76, 0.76);
    margin-bottom: 4px;
}

.subject-assignment-info-value {
    font-size: 0.95rem;
    font-weight: 500;
    color: #233d4c;
    word-break: break-word;
}

.subject-assignment-chip-section {
    border-top: 1px solid rgba(35, 61, 76, 0.12);
    padding-top: 12px;
}

.subject-assignment-chip-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: rgba(35, 61, 76, 0.8);
}

.subject-assignment-chip--selected {
    background: rgba(31, 111, 139, 0.14);
    border-color: rgba(31, 111, 139, 0.85);
    color: #1f6f8b;
}

.subjects-tree {
    border: 1px solid rgba(35, 61, 76, 0.18);
    border-radius: 14px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.72) 0%, rgba(255, 255, 255, 0.6) 100%);
    padding: 14px;
}

.subjects-tree-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 8px;
}

.subjects-tree > .subjects-tree-list {
    gap: 32px;
}

.subjects-tree-list--child {
    margin-top: 6px;
    margin-left: 34px;
    padding-left: 20px;
    border-left: 1px dashed rgba(35, 61, 76, 0.25);
}

.subjects-tree-group {
    border: 1px solid rgba(35, 61, 76, 0.24);
    border-radius: 12px;
    padding: 10px 12px;
}

.subjects-tree-topic-group {
    position: relative;
    padding-left: 12px;
    border-radius: 8px;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.52) 0%, rgba(255, 255, 255, 0.24) 34%, transparent 62%);
}

.subjects-tree-topic-group::before {
    content: '';
    position: absolute;
    left: 0;
    top: 5px;
    bottom: 5px;
    width: 2px;
    border-radius: 999px;
    background: var(--topic-accent-color, #1f6f8b);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.32);
}

.subjects-tree-topic-group + .subjects-tree-topic-group {
    margin-top: 24px;
}

.subjects-tree-new-topic-item {
    margin-top: 24px;
}

.subjects-tree-node {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 8px;
}

.subjects-tree-node--subject {
    font-weight: 700;
    background: rgba(35, 61, 76, 0.08);
}

.subjects-tree-node--topic {
    font-weight: 600;
    color: #2e4a5a;
    background: rgba(35, 61, 76, 0.05);
}

.subjects-tree-node--unit {
    color: #3c5a6d;
    background: rgba(35, 61, 76, 0.03);
}

.subjects-tree-node--new {
    color: #1f4f89;
    font-weight: 600;
    border: 1px dashed rgba(31, 79, 137, 0.35);
    background: rgba(31, 79, 137, 0.08);
}

.subjects-tree-node-actions {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-left: 10px;
}

.subjects-tree-new-btn {
    border: 0;
    background: transparent;
    padding: 0;
    cursor: pointer;
}

.subjects-tree-new-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.subjects-tree-new-btn:not(:disabled):hover .subjects-tree-node--new {
    border-color: rgba(31, 79, 137, 0.6);
    background: rgba(31, 79, 137, 0.15);
}
</style>
