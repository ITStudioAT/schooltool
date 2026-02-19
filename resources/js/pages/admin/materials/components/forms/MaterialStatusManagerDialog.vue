<template>
    <v-dialog
        :model-value="modelValue"
        max-width="720"
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
                            <v-icon icon="mdi-flag-outline" color="primary" class="mr-3" />
                        </template>

                        <template v-if="editingStatusId === option.id">
                            <v-text-field
                                v-model="editingStatusLabel"
                                variant="outlined"
                                density="compact"
                                hide-details="auto"
                                class="mt-1"
                                :disabled="isBusy"
                                @keyup.enter="saveStatusRename(option)"
                                @keyup.esc="cancelStatusRename" />
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
            editingStatusId: null,
            editingStatusLabel: '',
            deletingStatusId: null,
            isCreating: false,
            isRenaming: false,
            isDeleting: false,
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
                    if (!Number.isFinite(id) || id <= 0 || !value || !label) return null
                    return {
                        id,
                        value,
                        label,
                    }
                })
                .filter(Boolean)
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
        async createStatus() {
            const label = this.normalizeLabel(this.newStatusLabel)
            if (!label || this.isBusy) return

            this.isCreating = true
            const created = await this.materialCardStore.createStatus(label)
            this.isCreating = false

            if (created) {
                this.newStatusLabel = ''
            }
        },
        startStatusRename(option) {
            if (this.isBusy) return
            this.editingStatusId = option?.id ?? null
            this.editingStatusLabel = this.normalizeLabel(option?.label)
        },
        cancelStatusRename() {
            this.editingStatusId = null
            this.editingStatusLabel = ''
        },
        async saveStatusRename(option) {
            const optionId = Number(option?.id)
            const label = this.normalizeLabel(this.editingStatusLabel)

            if (!Number.isFinite(optionId) || optionId <= 0 || !label || this.isBusy) return

            this.isRenaming = true
            const updated = await this.materialCardStore.updateStatus(optionId, label)
            this.isRenaming = false

            if (updated) {
                this.cancelStatusRename()
            }
        },
        async deleteStatus(option) {
            const optionId = Number(option?.id)
            if (!Number.isFinite(optionId) || optionId <= 0 || this.isBusy) return

            const label = this.normalizeLabel(option?.label) || 'Diesen Status'
            const confirmed = window.confirm(`${label} wirklich löschen?`)
            if (!confirmed) return

            this.deletingStatusId = optionId
            this.isDeleting = true
            const deleted = await this.materialCardStore.deleteStatus(optionId)
            this.isDeleting = false
            this.deletingStatusId = null

            if (deleted && this.editingStatusId === optionId) {
                this.cancelStatusRename()
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
</style>
