<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
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
                                    v-for="subject in subjectTreeItems"
                                    :key="`subject-tree-subject-${subject.name}`"
                                    class="subjects-tree-item">
                                    <div class="subjects-tree-group" :style="subjectGroupStyle(subject)">
                                        <div class="subjects-tree-node subjects-tree-node--subject">
                                            <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                                            <span>{{ subject.name }}</span>
                                            <div class="subjects-tree-node-actions">
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
                                                v-for="topic in subject.topics"
                                                :key="`subject-tree-topic-${subject.name}-${topic.name}`"
                                                class="subjects-tree-item subjects-tree-topic-group"
                                                :style="topicGroupStyle(subject)">
                                                <div class="subjects-tree-node subjects-tree-node--topic">
                                                    <v-icon size="14" icon="mdi-book-open-page-variant-outline" class="mr-2" />
                                                    <span>{{ topic.name }}</span>
                                                    <div class="subjects-tree-node-actions">
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
                                                        v-for="unit in topic.units"
                                                        :key="`subject-tree-unit-${subject.name}-${topic.name}-${unit.id || unit.name}`"
                                                        class="subjects-tree-item">
                                                        <div class="subjects-tree-node subjects-tree-node--unit">
                                                            <v-icon size="12" icon="mdi-chevron-right" class="mr-1" />
                                                            <span>{{ unit.name }}</span>
                                                            <div class="subjects-tree-node-actions">
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
                { value: 'subjects', label: 'Fächer', icon: 'mdi-book-education-outline' },
                { value: 'materials_types', label: 'Materialtypen', icon: 'mdi-shape-outline' },
                { value: 'status_values', label: 'Statuswerte', icon: 'mdi-flag-outline' },
            ],
            subjectsMenuItems: [
                { value: 'subjects_catalog', label: 'Fachkatalog', icon: 'mdi-book-open-variant-outline' },
                { value: 'subjects_groups', label: 'Fachgruppen', icon: 'mdi-account-group-outline' },
                { value: 'subjects_mapping', label: 'Zuordnung', icon: 'mdi-link-variant' },
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
        }
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        if (!this.materialCardStore?.config) {
            await this.materialCardStore.loadConfig()
        }
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
        startEditFileSettings() {
            if (!this.canManageFileSettings || this.isSavingFileSettings || this.isAnySettingsEditActive) return
            this.syncFileSettingsForm()
            this.isEditingFileSettings = true
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
