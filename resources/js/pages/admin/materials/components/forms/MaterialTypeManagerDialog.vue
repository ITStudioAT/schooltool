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
                            <v-icon icon="mdi-shape-outline" color="primary" class="mr-3" />
                        </template>

                        <template v-if="editingTypeId === option.id">
                            <v-text-field
                                v-model="editingTypeName"
                                variant="outlined"
                                density="compact"
                                hide-details="auto"
                                class="mt-1"
                                :disabled="isBusy"
                                @keyup.enter="saveTypeRename(option)"
                                @keyup.esc="cancelTypeRename" />
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
            editingTypeId: null,
            editingTypeName: '',
            deletingTypeId: null,
            isCreating: false,
            isRenaming: false,
            isDeleting: false,
            isImportingDefaults: false,
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
                    if (!Number.isFinite(id) || id <= 0 || !value) return null
                    return {
                        id,
                        value,
                        label,
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
                if (!value || !label) continue
                const key = value.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push({ value, label })
            }

            return result
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
    },
    methods: {
        normalizeName(value) {
            return String(value ?? '').trim().slice(0, 255)
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
            if (!name || this.isBusy) return

            this.isCreating = true
            const created = await this.materialCardStore.createType(name)
            this.isCreating = false

            if (created) {
                this.newTypeName = ''
            }
        },
        async importDefaultType(option) {
            const name = this.normalizeName(option?.value)
            if (!name || this.isBusy || this.isDefaultTypeAlreadyPresent({ value: name })) return

            this.isImportingDefaults = true
            await this.materialCardStore.importDefaultTypes([name])
            this.isImportingDefaults = false
        },
        async importAllDefaultTypes() {
            if (this.isBusy) return

            const names = this.availableDefaultTypeOptions.map((option) => this.normalizeName(option.value)).filter(Boolean)
            if (!names.length) return

            this.isImportingDefaults = true
            await this.materialCardStore.importDefaultTypes(names)
            this.isImportingDefaults = false
        },
        startTypeRename(option) {
            if (this.isBusy) return
            this.editingTypeId = option?.id ?? null
            this.editingTypeName = this.normalizeName(option?.label)
        },
        cancelTypeRename() {
            this.editingTypeId = null
            this.editingTypeName = ''
        },
        async saveTypeRename(option) {
            const optionId = Number(option?.id)
            const name = this.normalizeName(this.editingTypeName)

            if (!Number.isFinite(optionId) || optionId <= 0 || !name || this.isBusy) return

            this.isRenaming = true
            const updated = await this.materialCardStore.updateType(optionId, name)
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
</style>
