<template>
    <v-dialog
        :model-value="modelValue"
        max-width="720"
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
                            <v-icon :icon="option.icon || defaultTypeIcon" color="primary" class="mr-3" />
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
            editingTypeId: null,
            editingTypeName: '',
            editingTypeIcon: '',
            deletingTypeId: null,
            isCreating: false,
            isRenaming: false,
            isDeleting: false,
            isImportingDefaults: false,
            iconPickerOpen: false,
            iconPickerTarget: 'new',
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
                    if (!Number.isFinite(id) || id <= 0 || !value) return null
                    return {
                        id,
                        value,
                        label,
                        icon: icon || this.defaultTypeIcon,
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
                if (!value || !label) continue
                const key = value.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push({
                    value,
                    label,
                    icon: icon || this.defaultTypeIcon,
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
            if (!name || this.isBusy) return

            this.isCreating = true
            const created = await this.materialCardStore.createType(name, icon)
            this.isCreating = false

            if (created) {
                this.newTypeName = ''
                this.newTypeIcon = this.defaultTypeIcon
            }
        },
        async importDefaultType(option) {
            const name = this.normalizeName(option?.value)
            const icon = this.normalizeTypeIcon(option?.icon)
            if (!name || this.isBusy || this.isDefaultTypeAlreadyPresent({ value: name })) return

            this.isImportingDefaults = true
            await this.materialCardStore.importDefaultTypes([{ name, icon }])
            this.isImportingDefaults = false
        },
        async importAllDefaultTypes() {
            if (this.isBusy) return

            const types = this.availableDefaultTypeOptions
                .map((option) => ({
                    name: this.normalizeName(option.value),
                    icon: this.normalizeTypeIcon(option.icon),
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
        },
        cancelTypeRename() {
            this.editingTypeId = null
            this.editingTypeName = ''
            this.editingTypeIcon = ''
        },
        async saveTypeRename(option) {
            const optionId = Number(option?.id)
            const name = this.normalizeName(this.editingTypeName)
            const icon = this.normalizeTypeIcon(this.editingTypeIcon)

            if (!Number.isFinite(optionId) || optionId <= 0 || !name || this.isBusy) return

            this.isRenaming = true
            const updated = await this.materialCardStore.updateType(optionId, name, icon)
            this.isRenaming = false

            if (updated) {
                this.cancelTypeRename()
            }
        },
        async deleteType(option) {
            const optionId = Number(option?.id)
            if (!Number.isFinite(optionId) || optionId <= 0 || this.isBusy) return

            const label = this.normalizeName(option?.label) || 'Diesen Typ'
            const confirmed = window.confirm(`${label} wirklich löschen?`)
            if (!confirmed) return

            this.deletingTypeId = optionId
            this.isDeleting = true
            const deleted = await this.materialCardStore.deleteType(optionId)
            this.isDeleting = false
            this.deletingTypeId = null

            if (deleted && this.editingTypeId === optionId) {
                this.cancelTypeRename()
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

.icon-option-btn {
    min-width: 46px;
    width: 46px;
    height: 46px;
    padding: 0;
}
</style>
