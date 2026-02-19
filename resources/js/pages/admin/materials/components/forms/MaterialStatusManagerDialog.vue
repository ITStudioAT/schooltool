<template>
    <v-dialog
        :model-value="modelValue"
        max-width="720"
        persistent
        @update:modelValue="$emit('update:modelValue', $event)">
        <v-card rounded="xl">
            <v-card-title class="text-h6 font-weight-bold">Statuswerte verwalten</v-card-title>
            <v-card-text>
                <div class="text-body-2 text-medium-emphasis mb-3">
                    Diese Statuswerte gelten für alle Materialien deiner Schule.
                </div>

                <div class="d-flex flex-wrap align-start ga-2 mb-4">
                    <v-text-field
                        v-model="newStatusLabel"
                        label="Neuer Status"
                        placeholder="z. B. Zur Freigabe"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="flex-grow-1"
                        :disabled="isBusy"
                        @keyup.enter="createStatus" />
                    <v-btn
                        variant="outlined"
                        class="color-picker-trigger"
                        :title="newStatusColor || 'Keine Farbe'"
                        :disabled="isBusy"
                        @click="openColorPicker('new')">
                        <span class="color-dot" :style="colorPreviewStyle(newStatusColor)" />
                    </v-btn>
                    <v-text-field
                        v-model="newStatusColor"
                        label="HEX"
                        placeholder="#1f77b4"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="color-hex-field"
                        :disabled="isBusy"
                        @blur="normalizeColorField('new')"
                        @keyup.enter="normalizeColorField('new')" />
                    <v-btn
                        v-if="newStatusColor"
                        icon="mdi-close"
                        variant="text"
                        size="small"
                        :disabled="isBusy"
                        @click="clearStatusColor('new')" />
                    <v-btn
                        color="primary"
                        variant="flat"
                        :loading="isCreating"
                        :disabled="isBusy || !canCreateStatus"
                        @click="createStatus">
                        Hinzufügen
                    </v-btn>
                </div>

                <v-alert v-if="!normalizedStatusOptions.length" type="info" variant="tonal" class="mb-0">
                    Noch keine Statuswerte vorhanden.
                </v-alert>

                <v-list v-else class="bg-transparent pa-0">
                    <v-list-item
                        v-for="option in normalizedStatusOptions"
                        :key="`material-status-${option.id}`"
                        class="status-row mb-2"
                        rounded="lg">
                        <template #prepend>
                            <div class="d-flex align-center ga-2 mr-3">
                                <v-icon icon="mdi-flag-outline" :color="option.color || 'primary'" />
                                <span class="color-dot color-dot--small" :style="colorPreviewStyle(option.color)" />
                            </div>
                        </template>

                        <template v-if="editingStatusId === option.id">
                            <div class="d-flex flex-wrap align-start ga-2 mt-1 w-100">
                                <v-text-field
                                    v-model="editingStatusLabel"
                                    variant="outlined"
                                    density="compact"
                                    hide-details="auto"
                                    class="flex-grow-1"
                                    :disabled="isBusy"
                                    @keyup.enter="saveStatusRename(option)"
                                    @keyup.esc="cancelStatusRename" />
                                <v-btn
                                    variant="outlined"
                                    class="color-picker-trigger color-picker-trigger--compact"
                                    :title="editingStatusColor || 'Keine Farbe'"
                                    :disabled="isBusy"
                                    @click="openColorPicker('edit')">
                                    <span class="color-dot" :style="colorPreviewStyle(editingStatusColor)" />
                                </v-btn>
                                <v-text-field
                                    v-model="editingStatusColor"
                                    label="HEX"
                                    placeholder="#1f77b4"
                                    variant="outlined"
                                    density="compact"
                                    hide-details="auto"
                                    class="color-hex-field color-hex-field--compact"
                                    :disabled="isBusy"
                                    @blur="normalizeColorField('edit')"
                                    @keyup.enter="normalizeColorField('edit')" />
                                <v-btn
                                    v-if="editingStatusColor"
                                    icon="mdi-close"
                                    variant="text"
                                    size="small"
                                    :disabled="isBusy"
                                    @click="clearStatusColor('edit')" />
                            </div>
                        </template>
                        <template v-else>
                            <v-list-item-title>{{ option.label }}</v-list-item-title>
                        </template>

                        <template #append>
                            <div class="d-flex align-center ga-1">
                                <template v-if="editingStatusId === option.id">
                                    <v-btn
                                        icon="mdi-check"
                                        size="small"
                                        variant="text"
                                        color="success"
                                        :disabled="isBusy || !canSaveStatusRename"
                                        :loading="isRenaming"
                                        @click="saveStatusRename(option)" />
                                    <v-btn
                                        icon="mdi-close"
                                        size="small"
                                        variant="text"
                                        :disabled="isBusy"
                                        @click="cancelStatusRename" />
                                </template>
                                <template v-else>
                                    <v-btn
                                        icon="mdi-pencil"
                                        size="small"
                                        variant="text"
                                        color="primary"
                                        :disabled="isBusy"
                                        @click="startStatusRename(option)" />
                                    <v-btn
                                        icon="mdi-delete"
                                        size="small"
                                        variant="text"
                                        color="error"
                                        :loading="isDeleting && deletingStatusId === option.id"
                                        :disabled="isBusy"
                                        @click="deleteStatus(option)" />
                                </template>
                            </div>
                        </template>
                    </v-list-item>
                </v-list>
            </v-card-text>

            <v-card-actions class="justify-end px-4 pb-4">
                <v-btn variant="text" :disabled="isBusy" @click="$emit('update:modelValue', false)">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="deleteStatusDialog.open" max-width="520" persistent>
        <v-card rounded="xl">
            <v-card-title class="text-h6 font-weight-bold">Löschen bestätigen</v-card-title>
            <v-card-text>
                <div class="text-body-1 mb-1">{{ deleteStatusDialog.label || 'Diesen Status' }}</div>
                <div class="text-body-2 text-medium-emphasis">
                    Wirklich löschen?
                </div>
            </v-card-text>
            <v-card-actions class="justify-end px-4 pb-4">
                <v-btn
                    variant="text"
                    :disabled="isDeleting"
                    @click="cancelDeleteStatusDialog">
                    Abbrechen
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    prepend-icon="mdi-delete-outline"
                    :loading="isDeleting"
                    :disabled="isDeleting"
                    @click="confirmDeleteStatusDialog">
                    Löschen
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <input
        ref="colorInput"
        type="color"
        class="d-none"
        :value="pickerInputColor"
        @input="onColorPicked" />
</template>

<script>
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'

export default {
    name: 'MaterialStatusManagerDialog',
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            materialCardStore: null,
            newStatusLabel: '',
            newStatusColor: '',
            editingStatusId: null,
            editingStatusLabel: '',
            editingStatusColor: '',
            deletingStatusId: null,
            isCreating: false,
            isRenaming: false,
            isDeleting: false,
            colorPickerTarget: 'new',
            deleteStatusDialog: {
                open: false,
                id: null,
                label: '',
            },
        }
    },
    computed: {
        normalizedStatusOptions() {
            const list = Array.isArray(this.materialCardStore?.config?.status_values)
                ? this.materialCardStore.config.status_values
                : []

            return list
                .map((option) => {
                    if (!option || typeof option !== 'object') return null
                    const id = Number(option.id)
                    const value = this.normalizeLabel(option.value)
                    const label = this.normalizeLabel(option.label) || value
                    const color = this.normalizeColor(option.color)
                    if (!Number.isFinite(id) || id <= 0 || !value || !label) return null
                    return {
                        id,
                        value,
                        label,
                        color,
                    }
                })
                .filter(Boolean)
        },
        pickerInputColor() {
            const current = this.colorPickerTarget === 'edit'
                ? this.normalizeColor(this.editingStatusColor)
                : this.normalizeColor(this.newStatusColor)

            return current || '#4f6fb3'
        },
        canCreateStatus() {
            return this.normalizeLabel(this.newStatusLabel) !== ''
        },
        canSaveStatusRename() {
            return this.editingStatusId !== null && this.normalizeLabel(this.editingStatusLabel) !== ''
        },
        isBusy() {
            return this.isCreating || this.isRenaming || this.isDeleting
        },
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        if (!this.materialCardStore?.config) {
            await this.materialCardStore.loadConfig()
        }
    },
    methods: {
        normalizeLabel(value) {
            return String(value ?? '').trim().slice(0, 255)
        },
        normalizeColor(value) {
            const raw = String(value ?? '').trim()
            if (!raw) return ''
            const text = raw.startsWith('#') ? raw : `#${raw}`
            if (!text) return ''
            if (!/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(text)) return ''
            if (text.length === 4) {
                return `#${text[1]}${text[1]}${text[2]}${text[2]}${text[3]}${text[3]}`.toLowerCase()
            }
            return text.toLowerCase()
        },
        normalizeColorField(target) {
            if (target === 'edit') {
                this.editingStatusColor = this.normalizeColor(this.editingStatusColor) || String(this.editingStatusColor || '').trim()
                return
            }
            this.newStatusColor = this.normalizeColor(this.newStatusColor) || String(this.newStatusColor || '').trim()
        },
        colorPreviewStyle(value) {
            const color = this.normalizeColor(value)
            if (!color) {
                return {}
            }

            return {
                backgroundColor: color,
                backgroundImage: 'none',
            }
        },
        openColorPicker(target) {
            if (this.isBusy) return
            this.colorPickerTarget = target === 'edit' ? 'edit' : 'new'
            this.$nextTick(() => {
                const colorInput = this.$refs.colorInput
                if (colorInput && typeof colorInput.click === 'function') {
                    colorInput.click()
                }
            })
        },
        onColorPicked(event) {
            const value = this.normalizeColor(event?.target?.value)
            if (!value) return

            if (this.colorPickerTarget === 'edit') {
                this.editingStatusColor = value
            } else {
                this.newStatusColor = value
            }
        },
        clearStatusColor(target) {
            if (target === 'edit') {
                this.editingStatusColor = ''
                return
            }
            this.newStatusColor = ''
        },
        async createStatus() {
            const label = this.normalizeLabel(this.newStatusLabel)
            const color = this.normalizeColor(this.newStatusColor)
            if (!label || this.isBusy) return

            this.isCreating = true
            const created = await this.materialCardStore.createStatus(label, color || null)
            this.isCreating = false

            if (created) {
                this.newStatusLabel = ''
                this.newStatusColor = ''
            }
        },
        startStatusRename(option) {
            if (this.isBusy) return
            this.editingStatusId = option?.id ?? null
            this.editingStatusLabel = this.normalizeLabel(option?.label)
            this.editingStatusColor = this.normalizeColor(option?.color)
        },
        cancelStatusRename() {
            this.editingStatusId = null
            this.editingStatusLabel = ''
            this.editingStatusColor = ''
        },
        async saveStatusRename(option) {
            const optionId = Number(option?.id)
            const label = this.normalizeLabel(this.editingStatusLabel)
            const color = this.normalizeColor(this.editingStatusColor)

            if (!Number.isFinite(optionId) || optionId <= 0 || !label || this.isBusy) return

            this.isRenaming = true
            const updated = await this.materialCardStore.updateStatus(optionId, label, color || null)
            this.isRenaming = false

            if (updated) {
                this.cancelStatusRename()
            }
        },
        async deleteStatus(option) {
            const optionId = Number(option?.id)
            if (!Number.isFinite(optionId) || optionId <= 0 || this.isBusy) return

            this.deleteStatusDialog = {
                open: true,
                id: optionId,
                label: this.normalizeLabel(option?.label) || 'Diesen Status',
            }
        },
        cancelDeleteStatusDialog() {
            if (this.isDeleting) return
            this.deleteStatusDialog = {
                open: false,
                id: null,
                label: '',
            }
        },
        async confirmDeleteStatusDialog() {
            const optionId = Number(this.deleteStatusDialog?.id)
            if (!Number.isFinite(optionId) || optionId <= 0 || this.isBusy) return

            this.deletingStatusId = optionId
            this.isDeleting = true
            const deleted = await this.materialCardStore.deleteStatus(optionId)
            this.isDeleting = false
            this.deletingStatusId = null

            if (deleted && this.editingStatusId === optionId) {
                this.cancelStatusRename()
            }
            if (deleted) {
                this.cancelDeleteStatusDialog()
            }
        },
    },
}
</script>

<style scoped>
.status-row {
    border: 1px solid rgba(40, 58, 80, 0.12);
    background-color: rgba(255, 255, 255, 0.72);
}

.color-picker-trigger {
    min-width: 44px;
    width: 44px;
    height: 44px;
    padding: 0;
}

.color-picker-trigger--compact {
    min-width: 40px;
    width: 40px;
    height: 40px;
}

.color-hex-field {
    max-width: 140px;
}

.color-hex-field--compact {
    max-width: 130px;
}

.color-dot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 1px solid rgba(40, 58, 80, 0.4);
    background-image: repeating-conic-gradient(#d9dce2 0% 25%, #ffffff 0% 50%);
    background-size: 8px 8px;
    background-position: center;
}

.color-dot--small {
    width: 14px;
    height: 14px;
}
</style>
