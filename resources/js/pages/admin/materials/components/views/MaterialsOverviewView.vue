<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <v-row align="center" class="mb-4">
            <v-col cols="12" md="8">
                <div class="text-h4 font-weight-bold mb-2">Übersicht</div>
                <div class="text-subtitle-1 subline">Hier siehst du alle aktuell gespeicherten Materialien.</div>
            </v-col>

            <v-col cols="12" md="4" class="d-flex justify-md-end">
                <v-btn
                    prepend-icon="mdi-refresh"
                    color="primary"
                    variant="flat"
                    :loading="isLoading"
                    :disabled="isDeletingId !== null || isSavingEdit || deleteModeActive"
                    @click="loadCards">
                    Aktualisieren
                </v-btn>
            </v-col>
        </v-row>

        <v-alert type="info" variant="tonal" class="mb-4">
            {{ totalMaterials }} Material{{ totalMaterials === 1 ? '' : 'ien' }} gespeichert.
        </v-alert>

        <v-skeleton-loader v-if="isLoading && !hasCards" type="list-item-three-line@4" />

        <v-list v-else-if="hasCards" class="bg-transparent pa-0">
            <v-list-item v-for="card in cards" :key="card.id" class="overview-item mb-3 px-4 py-3" rounded="lg">
                <template #prepend>
                    <v-avatar color="primary" variant="tonal" size="38" class="mr-4">
                        <v-icon :icon="sourceIcon(card.source_type)" />
                    </v-avatar>
                </template>

                <div class="d-flex align-center flex-wrap ga-2">
                    <v-list-item-title class="text-subtitle-1 font-weight-bold">
                        {{ card.title || 'Ohne Titel' }}
                    </v-list-item-title>

                    <v-chip v-if="card.type" size="small" variant="tonal" color="primary">
                        {{ card.type }}
                    </v-chip>
                </div>

                <v-list-item-subtitle>
                    <div v-if="classificationLabels(card).length" class="d-flex flex-wrap ga-2 mt-2 mb-2">
                        <v-chip
                            v-for="(label, index) in classificationLabels(card)"
                            :key="`classification-label-${card.id}-${index}`"
                            size="small"
                            variant="tonal"
                            color="primary">
                            {{ label }}
                        </v-chip>
                    </div>

                    <div class="d-flex flex-wrap ga-2 mt-2 mb-2">
                        <v-chip size="small" :color="statusColor(card.status)" variant="flat">
                            {{ statusLabel(card.status) }}
                        </v-chip>

                        <v-chip v-if="card.attachments_count" size="small" variant="tonal" color="secondary">
                            {{ card.attachments_count }} Anhang{{ card.attachments_count === 1 ? '' : 'e' }}
                        </v-chip>
                    </div>

                    <div v-if="card.source_url" class="text-body-2 mb-1">
                        <a :href="card.source_url" target="_blank" rel="noopener noreferrer">{{ card.source_url }}</a>
                    </div>

                    <div v-if="card.source_text" class="text-body-2 text-medium-emphasis mb-1 preview-text">
                        {{ preview(card.source_text, 320) }}
                    </div>

                    <div v-else-if="card.notes" class="text-body-2 text-medium-emphasis mb-1 preview-text">
                        {{ preview(card.notes, 320) }}
                    </div>

                    <div class="text-caption text-medium-emphasis mt-1">
                        Aktualisiert: {{ formatDateTime(card.updated_at) }}
                    </div>
                </v-list-item-subtitle>

                <template #append>
                    <div class="overview-actions d-flex flex-wrap justify-end ga-2">
                        <v-btn
                            size="small"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-pencil"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit || deleteModeActive"
                            @click="openEditDialog(card)">
                            Bearbeiten
                        </v-btn>

                        <v-btn
                            v-if="deleteStep(card.id) === 0"
                            size="small"
                            color="warning"
                            variant="tonal"
                            prepend-icon="mdi-delete"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit || deleteModeActive"
                            @click="startDeleteFlow(card.id)">
                            Löschen
                        </v-btn>

                        <template v-else-if="deleteStep(card.id) === 1">
                            <v-btn
                                size="small"
                                color="success"
                                variant="tonal"
                                prepend-icon="mdi-delete-off"
                                :disabled="isDeletingId !== null"
                                @click="resetDeleteStep(card.id)">
                                Abbrechen
                            </v-btn>
                            <v-btn
                                size="small"
                                color="error"
                                variant="flat"
                                prepend-icon="mdi-delete"
                                :loading="isDeletingId === card.id"
                                :disabled="isDeletingId !== null && isDeletingId !== card.id"
                                @click="confirmDelete(card.id)">
                                Löschen
                            </v-btn>
                        </template>
                    </div>
                </template>
            </v-list-item>
        </v-list>

        <v-alert v-else type="warning" variant="tonal" class="mb-0">
            Aktuell sind keine Materialien gespeichert.
        </v-alert>
    </v-card>

    <v-dialog v-model="editDialogOpen" max-width="640" persistent>
        <MaterialsCreateInlineForm
            :title="editForm.title"
            :description="editForm.description"
            :material-type="editForm.type"
            :type-options="typeOptions"
            :can-manage-types="canManageTypeValues"
            :status="editForm.status"
            :status-options="statusOptions"
            :classifications="editForm.classifications"
            :classification-tree="classificationTree"
            :classification-editor-visible="editClassificationEditorVisible"
            :classification-toggleable="true"
            :is-saving="isSavingEdit"
            form-title="Material bearbeiten"
            form-subline="Titel und Beschreibung bearbeiten."
            save-label="Speichern"
            cancel-label="Abbrechen"
            @update:title="editForm.title = $event"
            @update:description="editForm.description = $event"
            @update:materialType="editForm.type = $event"
            @update:status="editForm.status = $event"
            @update:classifications="editForm.classifications = $event"
            @update:classificationEditorVisible="editClassificationEditorVisible = $event"
            @manage-types="openTypeManager"
            @save="saveEdit"
            @cancel="closeEditDialog" />
    </v-dialog>

    <MaterialTypeManagerDialog v-model="typeManagerDialogOpen" />
</template>

<script>
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import MaterialsCreateInlineForm from '../forms/MaterialsCreateInlineForm.vue'
import MaterialTypeManagerDialog from '../forms/MaterialTypeManagerDialog.vue'

const createDefaultEditForm = () => ({
    id: null,
    title: '',
    description: '',
    classifications: [{ subject: '', topic: '', unit: '' }],
    source_type: 'note',
    source_url: '',
    area: '',
    unit: '',
    type: '',
    status: 'inbox',
    notes: '',
})

export default {
    name: 'MaterialsOverviewView',
    components: {
        MaterialsCreateInlineForm,
        MaterialTypeManagerDialog,
    },
    data() {
        return {
            materialCardStore: null,
            isLoading: false,
            isSavingEdit: false,
            isDeletingId: null,
            editDialogOpen: false,
            typeManagerDialogOpen: false,
            editClassificationEditorVisible: false,
            editForm: createDefaultEditForm(),
            deleteSteps: {},
        }
    },
    computed: {
        cards() {
            return Array.isArray(this.materialCardStore?.cards) ? this.materialCardStore.cards : []
        },
        classificationTree() {
            const items = this.materialCardStore?.config?.classification_tree
            return Array.isArray(items) ? items : []
        },
        statusOptions() {
            const items = this.materialCardStore?.config?.status_values
            return Array.isArray(items) && items.length
                ? items
                : [
                    { value: 'inbox', label: 'Neu/Idee' },
                    { value: 'in_progress', label: 'In Arbeit' },
                    { value: 'done', label: 'Fertig' },
                    { value: 'update_needed', label: 'Änderung nötig' },
                ]
        },
        typeOptions() {
            const items = this.materialCardStore?.config?.type_values
            return Array.isArray(items) ? items : []
        },
        canManageTypeValues() {
            return this.materialCardStore?.config?.can_manage_type_values === true
        },
        hasCards() {
            return this.cards.length > 0
        },
        totalMaterials() {
            const total = Number(this.materialCardStore?.meta?.total)
            if (Number.isFinite(total) && total > 0) {
                return total
            }
            return this.cards.length
        },
        canSaveEdit() {
            return String(this.editForm.title || '').trim().length > 0
        },
        activeDeleteCardId() {
            return Object.keys(this.deleteSteps).find((id) => Number(this.deleteSteps[id]) === 1) || null
        },
        deleteModeActive() {
            return this.activeDeleteCardId !== null
        },
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        if (!this.materialCardStore.config) {
            await this.materialCardStore.loadConfig()
        }
        await this.loadCards()
    },
    methods: {
        async loadCards() {
            if (this.isLoading) return
            this.isLoading = true
            await this.materialCardStore.indexAll()
            this.resetDeleteSteps()
            this.isLoading = false
        },
        toNullable(value) {
            const text = String(value ?? '').trim()
            return text === '' ? null : text
        },
        deleteStep(cardId) {
            return Number(this.deleteSteps[String(cardId)] || 0)
        },
        setDeleteStep(cardId, step) {
            this.deleteSteps = {
                ...this.deleteSteps,
                [String(cardId)]: step,
            }
        },
        resetDeleteStep(cardId) {
            const next = { ...this.deleteSteps }
            delete next[String(cardId)]
            this.deleteSteps = next
        },
        resetDeleteSteps() {
            this.deleteSteps = {}
        },
        startDeleteFlow(cardId) {
            if (this.isLoading || this.isSavingEdit || this.isDeletingId !== null) return
            this.resetDeleteSteps()
            this.setDeleteStep(cardId, 1)
        },
        async confirmDelete(cardId) {
            if (this.isDeletingId !== null) return
            if (this.deleteStep(cardId) !== 1) return

            this.isDeletingId = cardId
            const deleted = await this.materialCardStore.destroy(cardId)
            this.isDeletingId = null
            this.resetDeleteStep(cardId)

            if (deleted) {
                await this.loadCards()
            }
        },
        openEditDialog(card) {
            this.editForm = {
                id: card?.id ?? null,
                title: card?.title || '',
                description: card?.source_text || card?.notes || '',
                classifications: Array.isArray(card?.classifications) && card.classifications.length > 0
                    ? card.classifications.map((row) => ({
                        subject: String(row?.subject || '').trim(),
                        topic: String(row?.topic || '').trim(),
                        unit: String(row?.unit || '').trim(),
                    }))
                    : [{ subject: '', topic: '', unit: '' }],
                source_type: card?.source_type || 'note',
                source_url: card?.source_url || '',
                area: card?.area || '',
                unit: card?.unit || '',
                type: card?.type || '',
                status: card?.status || 'inbox',
                notes: card?.notes || '',
            }
            this.editClassificationEditorVisible = false
            this.editDialogOpen = true
        },
        closeEditDialog() {
            if (this.isSavingEdit) return
            this.editDialogOpen = false
            this.editClassificationEditorVisible = false
            this.editForm = createDefaultEditForm()
        },
        openTypeManager() {
            if (!this.canManageTypeValues) return
            this.typeManagerDialogOpen = true
        },
        async saveEdit() {
            if (!this.canSaveEdit || this.isSavingEdit || !this.editForm.id) return

            this.isSavingEdit = true
            const sourceType = String(this.editForm.source_type || 'note').trim() || 'note'
            const classifications = this.normalizeClassifications(this.editForm.classifications)
            const payload = {
                title: String(this.editForm.title || '').trim(),
                source_type: sourceType,
                source_url: this.toNullable(this.editForm.source_url),
                source_text: this.toNullable(this.editForm.description),
                classifications,
                area: this.toNullable(this.editForm.area),
                unit: this.toNullable(this.editForm.unit),
                type: this.toNullable(this.editForm.type),
                status: this.toNullable(this.editForm.status) || 'inbox',
                notes: this.toNullable(this.editForm.notes),
            }

            const updated = await this.materialCardStore.update(this.editForm.id, payload)
            this.isSavingEdit = false

            if (updated) {
                this.closeEditDialog()
                await this.loadCards()
            }
        },
        normalizeClassifications(value) {
            const input = Array.isArray(value) ? value : []
            const result = []
            const seen = new Set()

            for (const row of input) {
                if (!row || typeof row !== 'object') continue
                const subject = String(row.subject ?? '').trim().slice(0, 255)
                const topic = String(row.topic ?? '').trim().slice(0, 255)
                let unit = String(row.unit ?? '').trim().slice(0, 255)
                if (!subject) continue
                if (!topic) {
                    unit = ''
                }
                const key = `${subject.toLocaleLowerCase()}|${topic.toLocaleLowerCase()}|${unit.toLocaleLowerCase()}`
                if (seen.has(key)) continue
                seen.add(key)
                result.push({ subject, topic, unit })
            }

            return result
        },
        sourceIcon(sourceType) {
            const map = {
                upload: 'mdi-file-upload-outline',
                link: 'mdi-link-variant',
                note: 'mdi-note-text-outline',
            }
            return map[sourceType] || 'mdi-file-document-outline'
        },
        statusLabel(status) {
            const map = {
                inbox: 'Neu/Idee',
                in_progress: 'In Arbeit',
                done: 'Fertig',
                update_needed: 'Änderung nötig',
            }
            return map[status] || 'Unbekannt'
        },
        statusColor(status) {
            const map = {
                inbox: 'secondary',
                in_progress: 'warning',
                done: 'success',
                update_needed: 'error',
            }
            return map[status] || 'primary'
        },
        classificationLabels(card) {
            const rows = Array.isArray(card?.classifications) ? card.classifications : []
            const result = []
            const seen = new Set()

            for (const row of rows) {
                const subject = String(row?.subject || '').trim().slice(0, 255)
                const topic = String(row?.topic || '').trim().slice(0, 255)
                let unit = String(row?.unit || '').trim().slice(0, 255)
                if (!subject) continue
                if (!topic) {
                    unit = ''
                }

                let label = subject
                if (topic) label += ` / ${topic}`
                if (unit) label += ` / ${unit}`

                const key = label.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push(label)
            }

            return result
        },
        preview(value, limit = 320) {
            const text = String(value || '').trim()
            if (text.length <= limit) return text
            return text.slice(0, limit).trim() + '...'
        },
        formatDateTime(value) {
            const text = String(value || '').trim()
            if (!text) return '-'

            const parsed = new Date(text.replace(' ', 'T'))
            if (Number.isNaN(parsed.getTime())) return text

            return new Intl.DateTimeFormat('de-AT', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(parsed)
        },
    },
}
</script>

<style scoped>
.overview-item {
    border: 1px solid rgba(40, 58, 80, 0.12);
    background-color: rgba(255, 255, 255, 0.72);
}

.overview-actions {
    min-width: 260px;
}

.preview-text {
    white-space: pre-wrap;
    word-break: break-word;
}

:deep(.v-list-item__append) {
    align-self: flex-start;
    margin-top: 8px;
}

@media (max-width: 959px) {
    .overview-actions {
        width: 100%;
        min-width: 0;
        margin-top: 8px;
    }
}
</style>
