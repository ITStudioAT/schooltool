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

            <template v-else-if="selectedAction === 'file_settings'">
                <div class="text-body-2 text-medium-emphasis mb-3">
                    Diese Dateieinstellung gilt für alle Materialien deiner Schule.
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
                        :disabled="isSavingFileSettings"
                        @click="startEditFileSettings">
                        Bearbeiten
                    </v-btn>
                </div>

                <div v-if="canManageFileSettings && isEditingFileSettings" class="d-flex flex-wrap align-start ga-2">
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
            </template>

            <template v-else-if="selectedAction === 'overview_settings'">
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
                        :disabled="isSavingUserSettings"
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
        </v-card>

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
    components: {
        MaterialTypeManagerDialog,
        MaterialStatusManagerDialog,
    },
    data() {
        return {
            materialCardStore: null,
            selectedAction: 'overview_settings',
            typeManagerDialogOpen: false,
            statusManagerDialogOpen: false,
            menuItems: [
                { value: 'overview_settings', label: 'Übersicht', icon: 'mdi-view-dashboard-outline' },
                { value: 'materials_types', label: 'Materialtypen', icon: 'mdi-shape-outline' },
                { value: 'status_values', label: 'Statuswerte', icon: 'mdi-flag-outline' },
                { value: 'file_settings', label: 'Dateien', icon: 'mdi-file-cog-outline' },
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
    computed: {
        visibleMenuItems() {
            return this.menuItems
        },
        selectedItemLabel() {
            const selected = this.visibleMenuItems.find((item) => item.value === this.selectedAction)
            if (selected) return selected.label
            return this.visibleMenuItems[0]?.label || 'Einstellungen'
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
            if (!this.canManageFileSettings || this.isSavingFileSettings) return
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
            if (!this.canManageUserSettings || this.isSavingUserSettings) return
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
</style>
