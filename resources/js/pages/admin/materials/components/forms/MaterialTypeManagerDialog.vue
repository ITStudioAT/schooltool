<template>
    <v-dialog
        :model-value="modelValue"
        max-width="720"
        persistent
        @update:modelValue="$emit('update:modelValue', $event)">
        <v-card rounded="xl">
            <v-card-title class="text-h6 font-weight-bold">Materialtypen verwalten</v-card-title>
            <v-card-text>
                <div class="text-body-2 text-medium-emphasis mb-3">
                    Diese Typen gelten nur für deine eigenen Materialien.
                </div>

                <div class="d-flex flex-wrap align-start ga-2 mb-4">
                    <v-text-field
                        v-model="newTypeName"
                        label="Neuer Typ"
                        placeholder="z. B. Arbeitsblatt"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="flex-grow-1"
                        :disabled="isBusy"
                        @keyup.enter="createType" />
                    <v-btn
                        variant="outlined"
                        class="icon-picker-trigger"
                        :title="iconLabel(normalizeTypeIcon(newTypeIcon))"
                        :disabled="isBusy"
                        @click="openIconPicker('new')">
                        <v-icon :icon="normalizeTypeIcon(newTypeIcon)" />
                    </v-btn>
                    <v-btn
                        variant="outlined"
                        class="color-picker-trigger"
                        :title="newTypeColor || 'Keine Farbe'"
                        :disabled="isBusy"
                        @click="openColorPicker('new')">
                        <span class="color-dot" :style="colorPreviewStyle(newTypeColor)" />
                    </v-btn>
                    <v-text-field
                        v-model="newTypeColor"
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
                        v-if="newTypeColor"
                        icon="mdi-close"
                        variant="text"
                        size="small"
                        :disabled="isBusy"
                        @click="clearTypeColor('new')" />
                    <v-btn
                        color="primary"
                        variant="flat"
                        :loading="isCreating"
                        :disabled="isBusy || !canCreateType"
                        @click="createType">
                        Hinzufügen
                    </v-btn>
                </div>

                <v-card
                    v-if="normalizedDefaultTypeOptions.length"
                    variant="tonal"
                    color="primary"
                    class="pa-3 mb-4">
                    <div class="d-flex flex-wrap align-center ga-2 mb-2">
                        <div class="text-body-2 font-weight-bold">Standardtypen</div>
                        <v-spacer />
                        <v-btn
                            size="small"
                            variant="flat"
                            color="primary"
                            :disabled="isBusy || !availableDefaultTypeOptions.length"
                            :loading="isImportingDefaults"
                            prepend-icon="mdi-plus-box-multiple-outline"
                            @click="importAllDefaultTypes">
                            Alle übernehmen
                        </v-btn>
                    </div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="option in normalizedDefaultTypeOptions"
                            :key="`material-type-default-${option.value}`"
                            size="small"
                            :variant="isDefaultTypeAlreadyPresent(option) ? 'outlined' : 'tonal'"
                            :color="isDefaultTypeAlreadyPresent(option) ? 'grey' : 'primary'"
                            :append-icon="isDefaultTypeAlreadyPresent(option) ? 'mdi-check' : 'mdi-plus'"
                            :disabled="isBusy || isDefaultTypeAlreadyPresent(option)"
                            @click="importDefaultType(option)">
                            {{ option.label }}
                        </v-chip>
                    </div>
                </v-card>

                <v-alert v-if="!normalizedTypeOptions.length" type="info" variant="tonal" class="mb-0">
                    Noch keine Typen vorhanden.
                </v-alert>

                <v-list v-else class="bg-transparent pa-0">
                    <v-list-item
                        v-for="option in normalizedTypeOptions"
                        :key="`material-type-${option.id}`"
                        class="type-row mb-2"
                        rounded="lg">
                        <template #prepend>
                            <div class="d-flex align-center ga-2 mr-3">
                                <v-icon :icon="option.icon || defaultTypeIcon" :color="option.color || 'primary'" />
                                <span class="color-dot color-dot--small" :style="colorPreviewStyle(option.color)" />
                            </div>
                        </template>

                        <template v-if="editingTypeId === option.id">
                            <div class="d-flex flex-wrap align-start ga-2 mt-1 w-100">
                                <v-text-field
                                    v-model="editingTypeName"
                                    variant="outlined"
                                    density="compact"
                                    hide-details="auto"
                                    class="flex-grow-1"
                                    :disabled="isBusy"
                                    @keyup.enter="saveTypeRename(option)"
                                    @keyup.esc="cancelTypeRename" />
                                <v-btn
                                    variant="outlined"
                                    class="icon-picker-trigger icon-picker-trigger--compact"
                                    :title="iconLabel(normalizeTypeIcon(editingTypeIcon))"
                                    :disabled="isBusy"
                                    @click="openIconPicker('edit')">
                                    <v-icon :icon="normalizeTypeIcon(editingTypeIcon)" />
                                </v-btn>
                                <v-btn
                                    variant="outlined"
                                    class="color-picker-trigger color-picker-trigger--compact"
                                    :title="editingTypeColor || 'Keine Farbe'"
                                    :disabled="isBusy"
                                    @click="openColorPicker('edit')">
                                    <span class="color-dot" :style="colorPreviewStyle(editingTypeColor)" />
                                </v-btn>
                                <v-text-field
                                    v-model="editingTypeColor"
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
                                    v-if="editingTypeColor"
                                    icon="mdi-close"
                                    variant="text"
                                    size="small"
                                    :disabled="isBusy"
                                    @click="clearTypeColor('edit')" />
                            </div>
                        </template>
                        <template v-else>
                            <v-list-item-title>{{ option.label }}</v-list-item-title>
                        </template>

                        <template #append>
                            <div class="d-flex align-center ga-1">
                                <template v-if="editingTypeId === option.id">
                                    <v-btn
                                        icon="mdi-check"
                                        size="small"
                                        variant="text"
                                        color="success"
                                        :disabled="isBusy || !canSaveTypeRename"
                                        :loading="isRenaming"
                                        @click="saveTypeRename(option)" />
                                    <v-btn
                                        icon="mdi-close"
                                        size="small"
                                        variant="text"
                                        :disabled="isBusy"
                                        @click="cancelTypeRename" />
                                </template>
                                <template v-else>
                                    <v-btn
                                        icon="mdi-pencil"
                                        size="small"
                                        variant="text"
                                        color="primary"
                                        :disabled="isBusy"
                                        @click="startTypeRename(option)" />
                                    <v-btn
                                        icon="mdi-delete"
                                        size="small"
                                        variant="text"
                                        color="error"
                                        :loading="isDeleting && deletingTypeId === option.id"
                                        :disabled="isBusy"
                                        @click="deleteType(option)" />
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

    <v-dialog v-model="iconPickerOpen" max-width="760" persistent>
        <v-card rounded="xl">
            <v-card-title class="text-h6 font-weight-bold">Icon auswählen</v-card-title>
            <v-card-text>
                <div class="d-flex flex-wrap ga-2">
                    <v-btn
                        v-for="option in typeIconOptions"
                        :key="`type-icon-option-${option.value}`"
                        :variant="isPickerOptionActive(option.value) ? 'flat' : 'tonal'"
                        :color="isPickerOptionActive(option.value) ? 'primary' : undefined"
                        class="icon-option-btn"
                        :title="option.label"
                        :aria-label="option.label"
                        @click="selectIconFromPicker(option.value)">
                        <v-icon :icon="option.value" />
                    </v-btn>
                </div>
            </v-card-text>
            <v-card-actions class="justify-end px-4 pb-4">
                <v-btn variant="text" @click="iconPickerOpen = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="deleteTypeDialog.open" max-width="520" persistent>
        <v-card rounded="xl">
            <v-card-title class="text-h6 font-weight-bold">Löschen bestätigen</v-card-title>
            <v-card-text>
                <div class="text-body-1 mb-1">{{ deleteTypeDialog.label || 'Diesen Typ' }}</div>
                <div class="text-body-2 text-medium-emphasis">
                    Wirklich löschen?
                </div>
            </v-card-text>
            <v-card-actions class="justify-end px-4 pb-4">
                <v-btn
                    variant="text"
                    :disabled="isDeleting"
                    @click="cancelDeleteTypeDialog">
                    Abbrechen
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    prepend-icon="mdi-delete-outline"
                    :loading="isDeleting"
                    :disabled="isDeleting"
                    @click="confirmDeleteTypeDialog">
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
    name: 'MaterialTypeManagerDialog',
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
            newTypeName: '',
            newTypeIcon: 'mdi-file-document-outline',
            newTypeColor: '',
            editingTypeId: null,
            editingTypeName: '',
            editingTypeIcon: '',
            editingTypeColor: '',
            deletingTypeId: null,
            isCreating: false,
            isRenaming: false,
            isDeleting: false,
            isImportingDefaults: false,
            iconPickerOpen: false,
            iconPickerTarget: 'new',
            colorPickerTarget: 'new',
            deleteTypeDialog: {
                open: false,
                id: null,
                label: '',
            },
        }
    },
    computed: {
        normalizedTypeOptions() {
            const list = Array.isArray(this.materialCardStore?.config?.type_values)
                ? this.materialCardStore.config.type_values
                : []

            return list
                .map((option) => {
                    if (!option || typeof option !== 'object') return null
                    const id = Number(option.id)
                    const value = this.normalizeName(option.value)
                    const label = this.normalizeName(option.label) || value
                    const icon = this.normalizeIcon(option.icon)
                    const color = this.normalizeColor(option.color)
                    if (!Number.isFinite(id) || id <= 0 || !value) return null
                    return {
                        id,
                        value,
                        label,
                        icon: icon || this.defaultTypeIcon,
                        color,
                    }
                })
                .filter(Boolean)
        },
        normalizedDefaultTypeOptions() {
            const list = Array.isArray(this.materialCardStore?.config?.default_type_values)
                ? this.materialCardStore.config.default_type_values
                : []

            const result = []
            const seen = new Set()

            for (const option of list) {
                if (!option || typeof option !== 'object') continue
                const value = this.normalizeName(option.value)
                const label = this.normalizeName(option.label) || value
                const icon = this.normalizeIcon(option.icon)
                const color = this.normalizeColor(option.color)
                if (!value || !label) continue
                const key = value.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push({
                    value,
                    label,
                    icon: icon || this.defaultTypeIcon,
                    color,
                })
            }

            return result
        },
        typeIconOptions() {
            const list = Array.isArray(this.materialCardStore?.config?.type_icon_options)
                ? this.materialCardStore.config.type_icon_options
                : []

            const result = []
            const seen = new Set()

            for (const option of list) {
                if (!option || typeof option !== 'object') continue
                const value = this.normalizeIcon(option.value)
                const label = this.normalizeName(option.label || value)
                if (!value || !label) continue
                const key = value.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push({ value, label })
            }

            if (result.length) return result

            return [{
                value: 'mdi-file-document-outline',
                label: 'Dokument',
            }]
        },
        defaultTypeIcon() {
            return this.typeIconOptions[0]?.value || 'mdi-file-document-outline'
        },
        pickerInputColor() {
            const current = this.colorPickerTarget === 'edit'
                ? this.normalizeColor(this.editingTypeColor)
                : this.normalizeColor(this.newTypeColor)

            return current || '#4f6fb3'
        },
        availableDefaultTypeOptions() {
            return this.normalizedDefaultTypeOptions.filter((option) => !this.isDefaultTypeAlreadyPresent(option))
        },
        canCreateType() {
            return this.normalizeName(this.newTypeName) !== ''
        },
        canSaveTypeRename() {
            return this.editingTypeId !== null && this.normalizeName(this.editingTypeName) !== ''
        },
        isBusy() {
            return this.isCreating || this.isRenaming || this.isDeleting || this.isImportingDefaults
        },
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        if (!this.materialCardStore?.config) {
            await this.materialCardStore.loadConfig()
        }
        this.newTypeIcon = this.defaultTypeIcon
    },
    methods: {
        normalizeName(value) {
            return String(value ?? '').trim().slice(0, 255)
        },
        normalizeIcon(value) {
            return String(value ?? '').trim().slice(0, 100)
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
                this.editingTypeColor = this.normalizeColor(this.editingTypeColor) || String(this.editingTypeColor || '').trim()
                return
            }
            this.newTypeColor = this.normalizeColor(this.newTypeColor) || String(this.newTypeColor || '').trim()
        },
        normalizeTypeIcon(value) {
            const normalized = this.normalizeIcon(value)
            if (!normalized) return this.defaultTypeIcon

            const exists = this.typeIconOptions.some(
                (option) => this.normalizeIcon(option?.value).toLocaleLowerCase() === normalized.toLocaleLowerCase()
            )
            if (!exists) return this.defaultTypeIcon

            const matching = this.typeIconOptions.find(
                (option) => this.normalizeIcon(option?.value).toLocaleLowerCase() === normalized.toLocaleLowerCase()
            )
            return this.normalizeIcon(matching?.value) || this.defaultTypeIcon
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
        iconLabel(value) {
            const normalized = this.normalizeTypeIcon(value)
            const option = this.typeIconOptions.find(
                (row) => this.normalizeIcon(row?.value).toLocaleLowerCase() === normalized.toLocaleLowerCase()
            )

            return this.normalizeName(option?.label || 'Icon')
        },
        openIconPicker(target) {
            if (this.isBusy) return
            this.iconPickerTarget = target === 'edit' ? 'edit' : 'new'
            this.iconPickerOpen = true
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
                this.editingTypeColor = value
            } else {
                this.newTypeColor = value
            }
        },
        clearTypeColor(target) {
            if (target === 'edit') {
                this.editingTypeColor = ''
                return
            }
            this.newTypeColor = ''
        },
        pickerSelectedIcon() {
            if (this.iconPickerTarget === 'edit') {
                return this.normalizeTypeIcon(this.editingTypeIcon)
            }

            return this.normalizeTypeIcon(this.newTypeIcon)
        },
        isPickerOptionActive(value) {
            return this.normalizeTypeIcon(value) === this.pickerSelectedIcon()
        },
        selectIconFromPicker(value) {
            const icon = this.normalizeTypeIcon(value)
            if (this.iconPickerTarget === 'edit') {
                this.editingTypeIcon = icon
            } else {
                this.newTypeIcon = icon
            }
        },
        isDefaultTypeAlreadyPresent(option) {
            const value = this.normalizeName(option?.value)
            if (!value) return false
            return this.normalizedTypeOptions.some(
                (typeOption) => this.normalizeName(typeOption?.value).toLocaleLowerCase() === value.toLocaleLowerCase()
            )
        },
        async createType() {
            const name = this.normalizeName(this.newTypeName)
            const icon = this.normalizeTypeIcon(this.newTypeIcon)
            const color = this.normalizeColor(this.newTypeColor)
            if (!name || this.isBusy) return

            this.isCreating = true
            const created = await this.materialCardStore.createType(name, icon, color || null)
            this.isCreating = false

            if (created) {
                this.newTypeName = ''
                this.newTypeIcon = this.defaultTypeIcon
                this.newTypeColor = ''
            }
        },
        async importDefaultType(option) {
            const name = this.normalizeName(option?.value)
            const icon = this.normalizeTypeIcon(option?.icon)
            const color = this.normalizeColor(option?.color)
            if (!name || this.isBusy || this.isDefaultTypeAlreadyPresent({ value: name })) return

            this.isImportingDefaults = true
            await this.materialCardStore.importDefaultTypes([{ name, icon, color: color || null }])
            this.isImportingDefaults = false
        },
        async importAllDefaultTypes() {
            if (this.isBusy) return

            const types = this.availableDefaultTypeOptions
                .map((option) => ({
                    name: this.normalizeName(option.value),
                    icon: this.normalizeTypeIcon(option.icon),
                    color: this.normalizeColor(option.color) || null,
                }))
                .filter((option) => option.name)
            if (!types.length) return

            this.isImportingDefaults = true
            await this.materialCardStore.importDefaultTypes(types)
            this.isImportingDefaults = false
        },
        startTypeRename(option) {
            if (this.isBusy) return
            this.editingTypeId = option?.id ?? null
            this.editingTypeName = this.normalizeName(option?.label)
            this.editingTypeIcon = this.normalizeTypeIcon(option?.icon)
            this.editingTypeColor = this.normalizeColor(option?.color)
        },
        cancelTypeRename() {
            this.editingTypeId = null
            this.editingTypeName = ''
            this.editingTypeIcon = ''
            this.editingTypeColor = ''
        },
        async saveTypeRename(option) {
            const optionId = Number(option?.id)
            const name = this.normalizeName(this.editingTypeName)
            const icon = this.normalizeTypeIcon(this.editingTypeIcon)
            const color = this.normalizeColor(this.editingTypeColor)

            if (!Number.isFinite(optionId) || optionId <= 0 || !name || this.isBusy) return

            this.isRenaming = true
            const updated = await this.materialCardStore.updateType(optionId, name, icon, color || null)
            this.isRenaming = false

            if (updated) {
                this.cancelTypeRename()
            }
        },
        async deleteType(option) {
            const optionId = Number(option?.id)
            if (!Number.isFinite(optionId) || optionId <= 0 || this.isBusy) return

            this.deleteTypeDialog = {
                open: true,
                id: optionId,
                label: this.normalizeName(option?.label) || 'Diesen Typ',
            }
        },
        cancelDeleteTypeDialog() {
            if (this.isDeleting) return
            this.deleteTypeDialog = {
                open: false,
                id: null,
                label: '',
            }
        },
        async confirmDeleteTypeDialog() {
            const optionId = Number(this.deleteTypeDialog?.id)
            if (!Number.isFinite(optionId) || optionId <= 0 || this.isBusy) return

            this.deletingTypeId = optionId
            this.isDeleting = true
            const deleted = await this.materialCardStore.deleteType(optionId)
            this.isDeleting = false
            this.deletingTypeId = null

            if (deleted && this.editingTypeId === optionId) {
                this.cancelTypeRename()
            }
            if (deleted) {
                this.cancelDeleteTypeDialog()
            }
        },
    },
}
</script>

<style scoped>
.type-row {
    border: 1px solid rgba(40, 58, 80, 0.12);
    background-color: rgba(255, 255, 255, 0.72);
}

.icon-picker-trigger {
    min-width: 44px;
    width: 44px;
    height: 44px;
    padding: 0;
}

.icon-picker-trigger--compact {
    min-width: 40px;
    width: 40px;
    height: 40px;
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

.icon-option-btn {
    min-width: 46px;
    width: 46px;
    height: 46px;
    padding: 0;
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
