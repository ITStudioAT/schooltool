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
                    :disabled="isDeletingId !== null || isSavingEdit"
                    @click="loadCards">
                    Aktualisieren
                </v-btn>
            </v-col>
        </v-row>

        <div class="material-filters-wrap mb-4">
            <div class="filter-section mb-3">
                <div class="text-subtitle-2 mb-2">Fach filtern</div>

                <div class="d-flex flex-wrap ga-2">
                    <v-chip
                        size="small"
                        :variant="hasActiveSubjectFilter ? 'tonal' : 'flat'"
                        :color="hasActiveSubjectFilter ? undefined : 'primary'"
                        :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                        @click="clearSubjectFilter">
                        Alle
                    </v-chip>

                    <v-chip
                        v-for="subject in subjectFilterOptions"
                        :key="`subject-filter-${subject}`"
                        size="small"
                        color="primary"
                        :variant="isSubjectFilterActive(subject) ? 'flat' : 'tonal'"
                        :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                        @click="toggleSubjectFilter(subject)">
                        {{ subject }}
                    </v-chip>
                </div>
            </div>

            <div class="filter-section mb-3">
                <div class="text-subtitle-2 mb-2">Materialtyp filtern</div>

                <div class="d-flex flex-wrap ga-2">
                    <v-chip
                        size="small"
                        :variant="hasActiveTypeFilter ? 'tonal' : 'flat'"
                        :color="hasActiveTypeFilter ? undefined : 'primary'"
                        :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                        @click="clearTypeFilter">
                        Alle
                    </v-chip>

                    <v-chip
                        v-for="typeOption in typeFilterOptions"
                        :key="`type-filter-${typeOption.value}`"
                        size="small"
                        color="primary"
                        :variant="isTypeFilterActive(typeOption.value) ? 'flat' : 'tonal'"
                        :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                        @click="toggleTypeFilter(typeOption.value)">
                        {{ typeOption.label }}
                    </v-chip>
                </div>
            </div>

            <div class="filter-section">
                <div class="text-subtitle-2 mb-2">Status filtern</div>

                <div class="d-flex flex-wrap ga-2">
                    <v-chip
                        size="small"
                        :variant="hasActiveStatusFilter ? 'tonal' : 'flat'"
                        :color="hasActiveStatusFilter ? undefined : 'primary'"
                        :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                        @click="clearStatusFilter">
                        Alle
                    </v-chip>

                    <v-chip
                        v-for="statusOption in statusFilterOptions"
                        :key="`status-filter-${statusOption.value}`"
                        size="small"
                        :color="statusColor(statusOption.value)"
                        :variant="isStatusFilterActive(statusOption.value) ? 'flat' : 'tonal'"
                        :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                        @click="toggleStatusFilter(statusOption.value)">
                        {{ statusOption.label }}
                    </v-chip>
                </div>
            </div>
        </div>

        <v-alert type="info" variant="tonal" class="mb-4">
            {{ totalMaterials }} Material{{ totalMaterials === 1 ? '' : 'ien' }} gespeichert.
        </v-alert>

        <v-skeleton-loader v-if="isLoading && !hasCards" type="list-item-three-line@4" />

        <v-list v-else-if="hasCards" class="bg-transparent pa-0">
            <v-list-item v-for="card in cards" :key="card.id" class="overview-item mb-3 px-4 py-3" rounded="lg">
                <template #prepend>
                    <v-avatar :color="statusColor(card.status)" variant="tonal" size="38" class="mr-4">
                        <v-icon :icon="sourceIcon(card)" :color="statusColor(card.status)" />
                    </v-avatar>
                </template>

                <div class="material-header">
                    <div class="title-type-inline d-inline-flex align-center flex-wrap ga-2">
                        <div class="text-subtitle-1 font-weight-bold material-title">
                            {{ card.title || 'Ohne Titel' }}
                        </div>

                        <v-chip v-if="card.type" size="small" variant="tonal" color="primary" class="material-type-chip">
                            {{ card.type }}
                        </v-chip>
                    </div>

                    <v-chip size="small" :color="statusColor(card.status)" variant="flat" class="material-status-chip">
                        {{ statusLabel(card.status) }}
                    </v-chip>
                </div>

                <v-list-item-subtitle>
                    <div v-if="classificationLabels(card).length" class="d-flex flex-wrap ga-2 mt-2 mb-2">
                        <v-chip
                            v-for="(label, index) in classificationLabels(card)"
                            :key="`classification-label-${card.id}-${index}`"
                            size="small"
                            variant="tonal"
                            color="primary"
                            class="classification-chip"
                            :title="label">
                            {{ label }}
                        </v-chip>
                    </div>

                    <div class="d-flex flex-wrap ga-2 mt-2 mb-1">
                        <v-chip
                            v-if="card.attachments_count"
                            size="small"
                            variant="flat"
                            prepend-icon="mdi-paperclip"
                            class="attachments-count-chip attachments-count-chip-clickable"
                            @click="openAttachmentManager(card)">
                            {{ card.attachments_count }} Anhang{{ card.attachments_count === 1 ? '' : 'e' }}
                        </v-chip>
                    </div>

                    <div v-if="card.source_url" class="text-body-2 mb-1 source-link">
                        <a :href="card.source_url" target="_blank" rel="noopener noreferrer">{{ card.source_url }}</a>
                    </div>

                    <div v-if="card.source_text" class="text-body-2 text-medium-emphasis mb-1 preview-text">
                        {{ preview(card.source_text, 320) }}
                    </div>

                    <div v-else-if="card.notes" class="text-body-2 text-medium-emphasis mb-1 preview-text">
                        {{ preview(card.notes, 320) }}
                    </div>
                </v-list-item-subtitle>

                <div v-if="fileAttachments(card).length" class="attachment-block d-flex flex-column ga-2 mt-1 mb-2 pa-2">
                    <div class="attachment-chip-wrap d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="attachment in fileAttachments(card)"
                            :key="`file-attachment-${card.id}-${attachment.id}`"
                            size="small"
                            variant="flat"
                            color="primary"
                            prepend-icon="mdi-paperclip"
                            append-icon="mdi-download"
                            class="attachment-chip"
                            :disabled="isDownloadingAttachment(attachment.id)"
                            :title="attachmentChipLabel(attachment)"
                            @click.prevent="downloadAttachment(attachment)">
                            {{ attachmentChipLabel(attachment) }}
                        </v-chip>
                    </div>
                </div>

                <div class="text-caption text-medium-emphasis mt-1">
                    Aktualisiert: {{ formatDateTime(card.updated_at) }}
                </div>

                <template #append>
                    <div class="overview-actions d-flex flex-wrap justify-end ga-2">
                        <v-btn
                            size="small"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-eye-outline"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="openDetailDialog(card)">
                            Detail
                        </v-btn>
                    </div>
                </template>
            </v-list-item>
        </v-list>

        <v-alert v-else type="warning" variant="tonal" class="mb-0">
            Aktuell sind keine Materialien gespeichert.
        </v-alert>
    </v-card>

    <v-dialog v-model="attachmentDialogOpen" max-width="820" persistent>
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2">
                <span class="text-h5">Anhänge verwalten</span>
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" :disabled="attachmentDialogBusy" @click="closeAttachmentManager" />
            </v-card-title>

            <div class="px-6 pb-1 text-subtitle-1 font-weight-bold material-title">
                {{ attachmentDialogCardTitle || 'Material' }}
            </div>

            <v-card-text>
                <v-alert v-if="!attachmentRows.length" type="info" variant="tonal" class="mb-0">
                    Keine Anhänge vorhanden.
                </v-alert>

                <v-list v-else class="bg-transparent pa-0">
                    <v-list-item
                        v-for="row in attachmentRows"
                        :key="`attachment-manage-${row.id}`"
                        class="px-0 py-2">
                        <div class="attachment-manage-row d-flex flex-column ga-2 w-100">
                            <div class="d-flex flex-wrap align-center ga-2">
                                <v-chip size="x-small" variant="tonal" color="secondary">
                                    {{ attachmentTypeLabel(row) }}
                                </v-chip>

                                <div class="text-caption text-medium-emphasis attachment-meta-text">
                                    {{ attachmentMeta(row) }}
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-md-row ga-2">
                                <v-text-field
                                    :model-value="row.name"
                                    label="Titel"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    class="attachment-name-field flex-grow-1"
                                    :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                    @update:modelValue="updateAttachmentDraft(row.id, $event)"
                                    @keyup.enter="saveAttachmentName(row)" />

                                <div class="attachment-manage-actions d-flex flex-wrap ga-2 justify-end">
                                    <v-btn
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        prepend-icon="mdi-content-save"
                                        :loading="isAttachmentSaving(row.id)"
                                        :disabled="!canSaveAttachmentName(row) || isAttachmentDeleting(row.id)"
                                        @click="saveAttachmentName(row)">
                                        Speichern
                                    </v-btn>

                                    <v-btn
                                        v-if="row.attachment_type === 'file'"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        prepend-icon="mdi-download"
                                        :loading="isDownloadingAttachment(row.id)"
                                        :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                        @click="downloadAttachment(row)">
                                        Download
                                    </v-btn>

                                    <v-btn
                                        v-else-if="row.url"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        prepend-icon="mdi-open-in-new"
                                        :href="row.url"
                                        target="_blank"
                                        rel="noopener noreferrer">
                                        Öffnen
                                    </v-btn>

                                    <v-btn
                                        size="small"
                                        color="error"
                                        variant="flat"
                                        prepend-icon="mdi-delete"
                                        :loading="isAttachmentDeleting(row.id)"
                                        :disabled="isAttachmentSaving(row.id)"
                                        @click="removeAttachment(row)">
                                        Löschen
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>
    </v-dialog>

    <v-dialog v-model="detailDialogOpen" max-width="860" persistent>
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2">
                <span class="text-h5">Material-Details</span>
                <v-spacer />
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    :disabled="detailDialogLoading || isDeletingDetail"
                    @click="closeDetailDialog" />
            </v-card-title>

            <v-card-text>
                <v-skeleton-loader v-if="detailDialogLoading" type="article, list-item-two-line@3" />

                <template v-else-if="detailDialogCard">
                    <div class="material-header mb-3">
                        <div class="title-type-inline d-inline-flex align-center flex-wrap ga-2">
                            <div class="text-subtitle-1 font-weight-bold material-title">
                                {{ detailDialogCard.title || 'Material' }}
                            </div>

                            <v-chip v-if="detailDialogCard.type" size="small" variant="tonal" color="primary" class="material-type-chip">
                                {{ detailDialogCard.type }}
                            </v-chip>
                        </div>

                        <v-chip size="small" :color="statusColor(detailDialogCard.status)" variant="flat" class="material-status-chip">
                            {{ statusLabel(detailDialogCard.status) }}
                        </v-chip>
                    </div>

                    <div v-if="classificationLabels(detailDialogCard).length" class="mb-4">
                        <div class="text-subtitle-2 mb-2">Fach / Thema / Bereich</div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip
                                v-for="(label, index) in classificationLabels(detailDialogCard)"
                                :key="`detail-classification-${detailDialogCard.id}-${index}`"
                                size="small"
                                variant="tonal"
                                color="primary"
                                class="classification-chip"
                                :title="label">
                                {{ label }}
                            </v-chip>
                        </div>
                    </div>

                    <div v-if="detailDialogCard.source_url" class="mb-4 source-link">
                        <div class="text-subtitle-2 mb-1">Link</div>
                        <a :href="detailDialogCard.source_url" target="_blank" rel="noopener noreferrer">
                            {{ detailDialogCard.source_url }}
                        </a>
                    </div>

                    <div v-if="detailDialogCard.source_text" class="mb-4">
                        <div class="text-subtitle-2 mb-1">Beschreibung</div>
                        <div class="text-body-2 detail-text">{{ detailDialogCard.source_text }}</div>
                    </div>

                    <div v-if="detailDialogCard.notes" class="mb-4">
                        <div class="text-subtitle-2 mb-1">Notiz</div>
                        <div class="text-body-2 detail-text">{{ detailDialogCard.notes }}</div>
                    </div>

                    <div v-if="detailAttachments(detailDialogCard).length" class="mb-2">
                        <div class="text-subtitle-2 mb-2">Anhänge</div>

                        <v-list class="bg-transparent pa-0">
                            <v-list-item
                                v-for="attachment in detailAttachments(detailDialogCard)"
                                :key="`detail-attachment-${detailDialogCard.id}-${attachment.id}`"
                                class="px-0 py-2">
                                <div class="d-flex flex-column flex-md-row align-md-center ga-2 w-100">
                                    <div class="flex-grow-1">
                                        <div class="text-body-2 font-weight-medium">
                                            {{ attachmentDisplayName(attachment) }}
                                        </div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ attachmentTypeLabel(attachment) }}
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap ga-2 justify-end">
                                        <v-btn
                                            v-if="attachment.attachment_type === 'file' && attachment.download_url"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            prepend-icon="mdi-download"
                                            :loading="isDownloadingAttachment(attachment.id)"
                                            :disabled="isDeletingDetail"
                                            @click="downloadAttachment(attachment)">
                                            Download
                                        </v-btn>

                                        <v-btn
                                            v-else-if="attachment.url"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            prepend-icon="mdi-open-in-new"
                                            :href="attachment.url"
                                            target="_blank"
                                            rel="noopener noreferrer">
                                            Öffnen
                                        </v-btn>
                                    </div>
                                </div>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="text-caption text-medium-emphasis mt-3">
                        Aktualisiert: {{ formatDateTime(detailDialogCard.updated_at) }}
                    </div>
                </template>
            </v-card-text>

            <v-card-actions class="px-6 pb-6 pt-2 d-flex flex-wrap justify-end ga-2">
                <template v-if="detailDeleteStep === 0">
                    <v-btn
                        color="warning"
                        variant="tonal"
                        prepend-icon="mdi-delete"
                        :disabled="detailDialogLoading || isDeletingDetail || isSavingEdit"
                        @click="startDetailDeleteFlow">
                        Löschen
                    </v-btn>
                </template>

                <template v-else>
                    <v-btn
                        color="success"
                        variant="tonal"
                        prepend-icon="mdi-delete-off"
                        :disabled="isDeletingDetail"
                        @click="resetDetailDeleteFlow">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-delete"
                        :loading="isDeletingDetail"
                        :disabled="detailDialogLoading || isSavingEdit"
                        @click="confirmDeleteFromDetail">
                        Löschen
                    </v-btn>
                </template>

                <v-btn
                    variant="text"
                    :disabled="detailDialogLoading || isDeletingDetail || isSavingEdit"
                    @click="closeDetailDialog">
                    Schließen
                </v-btn>

                <v-btn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-pencil"
                    :disabled="detailDialogLoading || isDeletingDetail || isSavingEdit"
                    @click="openEditFromDetail">
                    Bearbeiten
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="editDialogOpen" max-width="640" persistent>
        <MaterialsCreateInlineForm
            :title="editForm.title"
            :description="editForm.description"
            :material-type="editForm.type"
            :pending-attachments="editForm.pendingAttachments"
            :max-upload-size-kb="maxUploadSizeKb"
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
            @update:pendingAttachments="editForm.pendingAttachments = $event"
            @remove-temp-upload="removeEditTempUpload"
            @upload-error="notifyUploadError"
            @add-files="addEditPendingAttachments"
            @update:status="editForm.status = $event"
            @update:classifications="editForm.classifications = $event"
            @update:classificationEditorVisible="editClassificationEditorVisible = $event"
            @manage-types="openTypeManager"
            @save="saveEdit"
            @cancel="closeEditDialog">
            <template #extra-content>
                <div class="mt-4">
                    <div class="text-subtitle-2 mb-2">Anhänge</div>

                    <v-alert v-if="!attachmentRows.length" type="info" variant="tonal" class="mb-0">
                        Keine Anhänge vorhanden.
                    </v-alert>

                    <v-list v-else class="bg-transparent pa-0">
                        <v-list-item
                            v-for="row in attachmentRows"
                            :key="`edit-attachment-manage-${row.id}`"
                            class="px-0 py-2">
                            <div class="attachment-manage-row d-flex flex-column ga-2 w-100">
                                <div class="d-flex flex-wrap align-center ga-2">
                                    <v-chip size="x-small" variant="tonal" color="secondary">
                                        {{ attachmentTypeLabel(row) }}
                                    </v-chip>

                                    <div class="text-caption text-medium-emphasis attachment-meta-text">
                                        {{ attachmentMeta(row) }}
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-md-row ga-2">
                                    <v-text-field
                                        :model-value="row.name"
                                        label="Titel"
                                        variant="outlined"
                                        density="comfortable"
                                        hide-details="auto"
                                        class="attachment-name-field flex-grow-1"
                                        :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                        @update:modelValue="updateAttachmentDraft(row.id, $event)"
                                        @keyup.enter="saveAttachmentName(row)" />

                                    <div class="attachment-manage-actions d-flex flex-wrap ga-2 justify-end">
                                        <v-btn
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            prepend-icon="mdi-content-save"
                                            :loading="isAttachmentSaving(row.id)"
                                            :disabled="isSavingEdit || !canSaveAttachmentName(row) || isAttachmentDeleting(row.id)"
                                            @click="saveAttachmentName(row)">
                                            Speichern
                                        </v-btn>

                                        <v-btn
                                            size="small"
                                            color="error"
                                            variant="flat"
                                            prepend-icon="mdi-delete"
                                            :loading="isAttachmentDeleting(row.id)"
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id)"
                                            @click="removeAttachment(row)">
                                            Löschen
                                        </v-btn>
                                    </div>
                                </div>
                            </div>
                        </v-list-item>
                    </v-list>
                </div>
            </template>
        </MaterialsCreateInlineForm>
    </v-dialog>

    <MaterialTypeManagerDialog v-model="typeManagerDialogOpen" />
</template>

<script>
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import MaterialsCreateInlineForm from '../forms/MaterialsCreateInlineForm.vue'
import MaterialTypeManagerDialog from '../forms/MaterialTypeManagerDialog.vue'

const createDefaultEditForm = () => ({
    id: null,
    title: '',
    description: '',
    classifications: [{ subject: '', topic: '', unit: '' }],
    pendingAttachments: [],
    source_url: '',
    area: '',
    unit: '',
    type: '',
    status: '',
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
            attachmentDialogOpen: false,
            attachmentDialogCardId: null,
            attachmentDialogCardTitle: '',
            attachmentRows: [],
            subjectFilter: '',
            typeFilter: '',
            statusFilter: '',
            typeManagerDialogOpen: false,
            editClassificationEditorVisible: false,
            editForm: createDefaultEditForm(),
            downloadingAttachmentIds: [],
            savingAttachmentIds: [],
            deletingAttachmentIds: [],
            detailDialogOpen: false,
            detailDialogLoading: false,
            detailDialogCard: null,
            detailDeleteStep: 0,
            returnToDetailOnEditCancel: false,
            detailCardForEditReturn: null,
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
                    { value: 'done', label: 'ok' },
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
        defaultStatusValue() {
            return String(this.statusOptions?.[0]?.value || '').trim() || 'inbox'
        },
        maxUploadSizeKb() {
            const value = Number(this.materialCardStore?.config?.file_settings?.max_upload_size_kb)
            if (!Number.isFinite(value) || value <= 0) return 20480
            return Math.max(1, Math.round(value))
        },
        attachmentDialogBusy() {
            return this.savingAttachmentIds.length > 0 || this.deletingAttachmentIds.length > 0
        },
        isDeletingDetail() {
            const id = Number(this.detailDialogCard?.id)
            if (!Number.isFinite(id) || id <= 0) return false
            return this.isDeletingId === id
        },
        subjectFilterOptions() {
            const options = this.classificationTree
                .map((entry) => String(entry?.name || '').trim())
                .filter((value) => value !== '')
                .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }))

            const selected = String(this.subjectFilter || '').trim()
            if (selected && !options.some((option) => option.toLocaleLowerCase() === selected.toLocaleLowerCase())) {
                options.unshift(selected)
            }

            return options
        },
        hasActiveSubjectFilter() {
            return String(this.subjectFilter || '').trim() !== ''
        },
        typeFilterOptions() {
            const result = []
            const seen = new Set()
            const list = Array.isArray(this.typeOptions) ? this.typeOptions : []

            for (const option of list) {
                const value = this.normalizeFilterText(option?.value)
                const label = this.normalizeFilterText(option?.label || value) || value
                if (!value || !label) continue
                const key = value.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push({ value, label })
            }

            const selected = this.normalizeFilterText(this.typeFilter)
            const selectedExists = selected !== '' && result.some((option) => option.value.toLocaleLowerCase() === selected.toLocaleLowerCase())
            if (selected !== '' && !selectedExists) {
                result.unshift({
                    value: selected,
                    label: selected,
                })
            }

            return result
        },
        hasActiveTypeFilter() {
            return this.normalizeFilterText(this.typeFilter) !== ''
        },
        statusFilterOptions() {
            const result = []
            const seen = new Set()
            const list = Array.isArray(this.statusOptions) ? this.statusOptions : []

            for (const option of list) {
                const value = this.normalizeFilterText(option?.value)
                const label = this.normalizeFilterText(option?.label || value) || value
                if (!value || !label) continue
                const key = value.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push({ value, label })
            }

            const selected = this.normalizeFilterText(this.statusFilter)
            const selectedExists = selected !== '' && result.some((option) => option.value.toLocaleLowerCase() === selected.toLocaleLowerCase())
            if (selected !== '' && !selectedExists) {
                result.unshift({
                    value: selected,
                    label: this.statusLabel(selected),
                })
            }

            return result
        },
        hasActiveStatusFilter() {
            return this.normalizeFilterText(this.statusFilter) !== ''
        },
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        if (!this.materialCardStore.config) {
            await this.materialCardStore.loadConfig()
        }
        this.subjectFilter = String(this.materialCardStore?.filters?.subject || '').trim()
        this.typeFilter = String(this.materialCardStore?.filters?.type || '').trim()
        this.statusFilter = String(this.materialCardStore?.filters?.status || '').trim()
        await this.loadCards()
    },
    methods: {
        async loadCards() {
            if (this.isLoading) return
            this.isLoading = true
            await this.materialCardStore.indexAll()
            this.isLoading = false
        },
        normalizeFilterText(value) {
            return String(value ?? '').trim().slice(0, 255)
        },
        isSubjectFilterActive(value) {
            const selected = this.normalizeFilterText(this.subjectFilter).toLocaleLowerCase()
            const subject = this.normalizeFilterText(value).toLocaleLowerCase()
            return selected !== '' && selected === subject
        },
        async applySubjectFilter(value) {
            const subject = this.normalizeFilterText(value)
            this.subjectFilter = subject
            this.materialCardStore.filters = {
                ...(this.materialCardStore.filters || {}),
                subject,
            }
            await this.loadCards()
        },
        async toggleSubjectFilter(value) {
            if (this.isSubjectFilterActive(value)) {
                await this.clearSubjectFilter()
                return
            }

            await this.applySubjectFilter(value)
        },
        async clearSubjectFilter() {
            await this.applySubjectFilter('')
        },
        isTypeFilterActive(value) {
            const selected = this.normalizeFilterText(this.typeFilter).toLocaleLowerCase()
            const type = this.normalizeFilterText(value).toLocaleLowerCase()
            return selected !== '' && selected === type
        },
        async applyTypeFilter(value) {
            const type = this.normalizeFilterText(value)
            this.typeFilter = type
            this.materialCardStore.filters = {
                ...(this.materialCardStore.filters || {}),
                type,
            }
            await this.loadCards()
        },
        async toggleTypeFilter(value) {
            if (this.isTypeFilterActive(value)) {
                await this.clearTypeFilter()
                return
            }

            await this.applyTypeFilter(value)
        },
        async clearTypeFilter() {
            await this.applyTypeFilter('')
        },
        isStatusFilterActive(value) {
            const selected = this.normalizeFilterText(this.statusFilter).toLocaleLowerCase()
            const status = this.normalizeFilterText(value).toLocaleLowerCase()
            return selected !== '' && selected === status
        },
        async applyStatusFilter(value) {
            const status = this.normalizeFilterText(value)
            this.statusFilter = status
            this.materialCardStore.filters = {
                ...(this.materialCardStore.filters || {}),
                status,
            }
            await this.loadCards()
        },
        async toggleStatusFilter(value) {
            if (this.isStatusFilterActive(value)) {
                await this.clearStatusFilter()
                return
            }

            await this.applyStatusFilter(value)
        },
        async clearStatusFilter() {
            await this.applyStatusFilter('')
        },
        toNullable(value) {
            const text = String(value ?? '').trim()
            return text === '' ? null : text
        },
        defaultAttachmentTitle(fileName) {
            const normalized = String(fileName || '').trim()
            if (!normalized) return 'Datei'

            const dot = normalized.lastIndexOf('.')
            const base = dot > 0 ? normalized.slice(0, dot) : normalized
            return base.slice(0, 255) || 'Datei'
        },
        toPendingAttachments(value) {
            const input = Array.isArray(value) ? value : []
            const result = []

            for (let index = 0; index < input.length; index += 1) {
                const item = input[index]
                const tempUpload = String(item?.tempUpload || '').trim()
                if (tempUpload) {
                    const fileName = String(item?.fileName || '').trim() || `Datei ${index + 1}`
                    const rawTitle = String(item?.title || '').trim()
                    const source = String(item?.source || '').trim()
                    const key = String(item?.key || '') || `temp|${tempUpload}|${index}`

                    result.push({
                        tempUpload,
                        file: null,
                        fileName,
                        title: rawTitle || this.defaultAttachmentTitle(fileName),
                        source: source || 'filepond',
                        key,
                    })
                    continue
                }

                const file = item instanceof File ? item : item?.file
                if (!(file instanceof File)) continue

                const fileName = String(file.name || '').trim() || `Datei ${index + 1}`
                const rawTitle = item instanceof File ? '' : String(item?.title || '').trim()
                const source = item instanceof File ? '' : String(item?.source || '').trim()
                const key = String(item instanceof File ? '' : item?.key || '') || `${fileName}|${file.size}|${file.lastModified}|${index}`

                result.push({
                    tempUpload: '',
                    file,
                    fileName,
                    title: rawTitle || this.defaultAttachmentTitle(fileName),
                    source: source || 'manual',
                    key,
                })
            }

            return result
        },
        mergeUniquePendingAttachments(existingAttachments, newFiles, source = 'manual') {
            const list = this.toPendingAttachments(existingAttachments)
            const getKey = (file) => `${file?.name || ''}|${file?.size || 0}|${file?.type || ''}|${file?.lastModified || 0}`
            const seen = new Set(list.map((item) => getKey(item.file)))

            for (const file of newFiles || []) {
                if (!(file instanceof File)) continue
                const key = getKey(file)
                if (!seen.has(key)) {
                    seen.add(key)
                    list.push({
                        file,
                        title: this.defaultAttachmentTitle(file.name),
                        source: String(source || 'manual').trim(),
                        key: `${key}|${seen.size}`,
                    })
                }
            }

            return list
        },
        addEditPendingAttachments(files) {
            const incoming = Array.isArray(files) ? files : []
            if (!incoming.length) return

            this.editForm.pendingAttachments = this.mergeUniquePendingAttachments(
                this.editForm.pendingAttachments,
                incoming,
                'picker'
            )
        },
        notifyUploadError(message) {
            const text = String(message || '').trim()
            const notification = useNotificationStore()
            notification.notify({
                message: text || 'Datei konnte nicht hochgeladen werden.',
                type: 'error',
                timeout: 3500,
            })
        },
        async removeEditTempUpload(uploadId) {
            const value = String(uploadId || '').trim()
            if (!value) return
            await this.materialCardStore.deleteTempUpload(value, false)
        },
        async cleanupPendingTempUploads(rows = null) {
            const list = this.toPendingAttachments(rows ?? this.editForm.pendingAttachments)
            const uploads = list
                .map((item) => String(item?.tempUpload || '').trim())
                .filter((value) => value !== '')

            for (const uploadId of uploads) {
                await this.materialCardStore.deleteTempUpload(uploadId, false)
            }
        },
        sanitizeDialogCard(card) {
            if (!card || typeof card !== 'object') return null

            return {
                ...card,
                classifications: Array.isArray(card.classifications)
                    ? card.classifications.map((row) => ({
                        subject: String(row?.subject || '').trim(),
                        topic: String(row?.topic || '').trim(),
                        unit: String(row?.unit || '').trim(),
                    }))
                    : [],
                attachments: Array.isArray(card.attachments)
                    ? card.attachments.map((attachment) => ({
                        ...attachment,
                    }))
                    : [],
            }
        },
        mergeCardIntoOverview(card) {
            const id = Number(card?.id)
            if (!Number.isFinite(id) || id <= 0) return

            const next = this.sanitizeDialogCard(card)
            if (!next) return

            this.materialCardStore.cards = this.cards.map((row) => {
                if (Number(row?.id) !== id) return row
                return {
                    ...row,
                    ...next,
                }
            })
        },
        async openDetailDialog(card) {
            if (this.isLoading || this.isSavingEdit || this.isDeletingId !== null) return

            const cardId = Number(card?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) return

            this.detailDialogCard = this.sanitizeDialogCard(card)
            this.detailDialogOpen = true
            this.detailDialogLoading = true
            this.detailDeleteStep = 0

            try {
                const loaded = await this.materialCardStore.show(cardId)
                if (!loaded || !this.detailDialogOpen) return
                if (Number(this.detailDialogCard?.id) !== cardId) return

                const selectedCard = this.materialCardStore?.selected_card
                if (Number(selectedCard?.id) !== cardId) return

                this.detailDialogCard = this.sanitizeDialogCard(selectedCard)
                this.mergeCardIntoOverview(selectedCard)
            } finally {
                this.detailDialogLoading = false
            }
        },
        closeDetailDialog() {
            if (this.isDeletingDetail) return
            this.detailDialogOpen = false
            this.detailDialogLoading = false
            this.detailDialogCard = null
            this.detailDeleteStep = 0
            this.returnToDetailOnEditCancel = false
            this.detailCardForEditReturn = null
        },
        detailAttachments(card) {
            return Array.isArray(card?.attachments) ? card.attachments : []
        },
        openEditFromDetail() {
            if (!this.detailDialogCard || this.detailDialogLoading || this.isDeletingDetail) return

            const card = this.sanitizeDialogCard(this.detailDialogCard)
            if (!card) return

            this.returnToDetailOnEditCancel = true
            this.detailCardForEditReturn = card
            this.detailDialogOpen = false
            this.detailDialogLoading = false
            this.detailDialogCard = null
            this.detailDeleteStep = 0
            this.openEditDialog(card)
        },
        startDetailDeleteFlow() {
            if (!this.detailDialogCard || this.detailDialogLoading || this.isDeletingDetail) return
            this.detailDeleteStep = 1
        },
        resetDetailDeleteFlow() {
            if (this.isDeletingDetail) return
            this.detailDeleteStep = 0
        },
        async confirmDeleteFromDetail() {
            if (this.isDeletingId !== null || this.detailDeleteStep !== 1) return

            const cardId = Number(this.detailDialogCard?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) return

            this.isDeletingId = cardId
            const deleted = await this.materialCardStore.destroy(cardId)
            this.isDeletingId = null
            this.detailDeleteStep = 0

            if (deleted) {
                this.closeDetailDialog()
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
                pendingAttachments: [],
                source_url: card?.source_url || '',
                area: card?.area || '',
                unit: card?.unit || '',
                type: card?.type || '',
                status: card?.status || this.defaultStatusValue,
                notes: card?.notes || '',
            }
            this.attachmentDialogCardId = Number(card?.id) || null
            this.attachmentRows = this.toAttachmentRows(card?.attachments)
            this.editClassificationEditorVisible = false
            this.editDialogOpen = true
        },
        async closeEditDialog(restoreDetail = true) {
            if (this.isSavingEdit) return

            await this.cleanupPendingTempUploads(this.editForm.pendingAttachments)

            const shouldRestoreDetail = restoreDetail
                && this.returnToDetailOnEditCancel
                && this.detailCardForEditReturn
            const restoreCard = shouldRestoreDetail
                ? this.sanitizeDialogCard(this.detailCardForEditReturn)
                : null

            this.editDialogOpen = false
            this.editClassificationEditorVisible = false
            this.editForm = createDefaultEditForm()
            this.attachmentDialogCardId = null
            this.attachmentRows = []
            this.returnToDetailOnEditCancel = false
            this.detailCardForEditReturn = null

            if (restoreCard) {
                await this.openDetailDialog(restoreCard)
            }
        },
        openTypeManager() {
            if (!this.canManageTypeValues) return
            this.typeManagerDialogOpen = true
        },
        async saveEdit() {
            if (!this.canSaveEdit || this.isSavingEdit || !this.editForm.id) return

            this.isSavingEdit = true
            const classifications = this.normalizeClassifications(this.editForm.classifications)
            const payload = {
                title: String(this.editForm.title || '').trim(),
                source_url: this.toNullable(this.editForm.source_url),
                source_text: this.toNullable(this.editForm.description),
                classifications,
                area: this.toNullable(this.editForm.area),
                unit: this.toNullable(this.editForm.unit),
                type: this.toNullable(this.editForm.type),
                status: this.toNullable(this.editForm.status) || this.defaultStatusValue,
                notes: this.toNullable(this.editForm.notes),
            }

            const updated = await this.materialCardStore.update(this.editForm.id, payload)

            if (updated) {
                const pendingAttachments = this.toPendingAttachments(this.editForm.pendingAttachments)
                for (const attachment of pendingAttachments) {
                    if (attachment.tempUpload) {
                        await this.materialCardStore.addTempFileAttachment(
                            this.editForm.id,
                            attachment.tempUpload,
                            this.toNullable(attachment.title) || attachment.fileName || ''
                        )
                        continue
                    }

                    if (attachment.file instanceof File) {
                        await this.materialCardStore.addFileAttachment(
                            this.editForm.id,
                            attachment.file,
                            this.toNullable(attachment.title) || attachment.file.name || ''
                        )
                    }
                }
            }

            this.isSavingEdit = false

            if (updated) {
                await this.closeEditDialog(false)
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
        sourceIcon(card) {
            const cardType = String(card?.type || '').trim()
            if (cardType) {
                const typeOption = this.typeOptions.find((option) => {
                    const value = String(option?.value || '').trim()
                    return value !== '' && value.toLocaleLowerCase() === cardType.toLocaleLowerCase()
                })

                const configuredIcon = String(typeOption?.icon || '').trim()
                if (configuredIcon) {
                    return configuredIcon
                }
            }

            const attachments = Array.isArray(card?.attachments) ? card.attachments : []
            const hasFileAttachment = attachments.some((attachment) => attachment?.attachment_type === 'file')
            if (hasFileAttachment) return 'mdi-file-upload-outline'

            const hasSourceUrl = String(card?.source_url || '').trim() !== ''
            if (hasSourceUrl) return 'mdi-link-variant'

            const hasTextContent = String(card?.source_text || '').trim() !== '' || String(card?.notes || '').trim() !== ''
            if (hasTextContent) return 'mdi-note-text-outline'

            if (attachments.length > 0) return 'mdi-paperclip'

            return 'mdi-file-document-outline'
        },
        statusLabel(status) {
            const normalized = String(status || '').trim().toLocaleLowerCase()
            const configured = this.statusOptions.find((option) => {
                const value = String(option?.value || '').trim().toLocaleLowerCase()
                return value !== '' && value === normalized
            })

            const configuredLabel = String(configured?.label || '').trim()
            if (configuredLabel) {
                return configuredLabel
            }

            const map = {
                inbox: 'Neu/Idee',
                in_progress: 'In Arbeit',
                done: 'ok',
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
        normalizeAttachmentName(value) {
            return String(value ?? '').trim().slice(0, 255)
        },
        toAttachmentRows(attachments) {
            const list = Array.isArray(attachments) ? attachments : []

            return list
                .map((attachment) => {
                    const id = Number(attachment?.id)
                    if (!Number.isFinite(id) || id <= 0) return null

                    const type = String(attachment?.attachment_type || '').trim() || 'file'
                    const baseName = this.normalizeAttachmentName(attachment?.name)
                    const fallbackName = this.normalizeAttachmentName(this.attachmentDisplayName(attachment))
                    const name = baseName || fallbackName || 'Anhang'

                    return {
                        id,
                        attachment_type: type,
                        name,
                        savedName: name,
                        download_url: String(attachment?.download_url || '').trim(),
                        url: String(attachment?.url || '').trim(),
                        file_path: String(attachment?.file_path || '').trim(),
                        mime_type: String(attachment?.mime_type || '').trim(),
                        size_bytes: Number(attachment?.size_bytes || 0),
                    }
                })
                .filter(Boolean)
        },
        openAttachmentManager(card) {
            this.attachmentDialogCardId = Number(card?.id) || null
            this.attachmentDialogCardTitle = String(card?.title || '').trim()
            this.attachmentRows = this.toAttachmentRows(card?.attachments)
            this.attachmentDialogOpen = true
        },
        closeAttachmentManager() {
            if (this.attachmentDialogBusy) return
            this.attachmentDialogOpen = false
            this.attachmentDialogCardId = null
            this.attachmentDialogCardTitle = ''
            this.attachmentRows = []
            this.savingAttachmentIds = []
            this.deletingAttachmentIds = []
        },
        attachmentExtension(row) {
            const extractFromPath = (value) => {
                const raw = String(value || '').trim()
                if (!raw) return ''
                const clean = raw.split('?')[0].split('#')[0]
                const fileName = clean.split('/').pop()?.split('\\').pop() || ''
                const dotIndex = fileName.lastIndexOf('.')
                if (dotIndex <= 0 || dotIndex >= fileName.length - 1) return ''
                return fileName.slice(dotIndex + 1).toLocaleLowerCase()
            }

            const fromName = extractFromPath(row?.name)
            if (fromName) return fromName

            const fromPath = extractFromPath(row?.file_path)
            if (fromPath) return fromPath

            const mime = String(row?.mime_type || '').trim().toLocaleLowerCase()
            if (mime === 'application/pdf') return 'pdf'
            if (mime === 'application/msword') return 'doc'
            if (mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') return 'docx'
            if (mime === 'application/vnd.ms-excel') return 'xls'
            if (mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') return 'xlsx'
            if (mime === 'application/vnd.ms-powerpoint') return 'ppt'
            if (mime === 'application/vnd.openxmlformats-officedocument.presentationml.presentation') return 'pptx'
            if (mime === 'image/jpeg') return 'jpg'
            if (mime === 'image/png') return 'png'
            if (mime === 'image/gif') return 'gif'
            if (mime === 'image/webp') return 'webp'
            if (mime === 'image/svg+xml') return 'svg'
            if (mime === 'text/plain') return 'txt'
            if (mime === 'text/markdown') return 'md'
            if (mime === 'text/csv') return 'csv'
            if (mime === 'application/zip') return 'zip'
            if (mime === 'application/x-7z-compressed') return '7z'
            if (mime === 'application/x-rar-compressed') return 'rar'

            return ''
        },
        attachmentTypeLabel(row) {
            if (String(row?.attachment_type || '').trim() === 'link') {
                return 'Link'
            }

            const ext = this.attachmentExtension(row)
            const imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tif', 'tiff']
            const wordExt = ['doc', 'docx', 'odt', 'rtf']
            const excelExt = ['xls', 'xlsx', 'csv', 'ods']
            const powerpointExt = ['ppt', 'pptx', 'odp']
            const textExt = ['txt', 'md', 'rtf']
            const archiveExt = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2']
            const audioExt = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac']
            const videoExt = ['mp4', 'mov', 'avi', 'mkv', 'webm']

            let baseLabel = 'Datei'
            if (ext === 'pdf') baseLabel = 'PDF-Datei'
            else if (wordExt.includes(ext)) baseLabel = 'Word-Datei'
            else if (excelExt.includes(ext)) baseLabel = 'Excel-Datei'
            else if (powerpointExt.includes(ext)) baseLabel = 'PowerPoint-Datei'
            else if (imageExt.includes(ext)) baseLabel = 'Bild-Datei'
            else if (textExt.includes(ext)) baseLabel = 'Text-Datei'
            else if (archiveExt.includes(ext)) baseLabel = 'Archiv-Datei'
            else if (audioExt.includes(ext)) baseLabel = 'Audio-Datei'
            else if (videoExt.includes(ext)) baseLabel = 'Video-Datei'

            return ext ? `${baseLabel} (${ext})` : baseLabel
        },
        attachmentMeta(row) {
            if (String(row?.attachment_type || '').trim() === 'link') {
                return String(row?.url || '').trim() || 'Link-Anhang'
            }

            const ext = this.attachmentExtension(row)
            if (ext) {
                return `Dateiformat: ${ext.toUpperCase()}`
            }

            return String(row?.file_path || '').trim() || 'Datei-Anhang'
        },
        updateAttachmentDraft(attachmentId, value) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return

            this.attachmentRows = this.attachmentRows.map((row) => {
                if (row.id !== id) return row
                return {
                    ...row,
                    name: this.normalizeAttachmentName(value),
                }
            })
        },
        isAttachmentSaving(attachmentId) {
            const id = Number(attachmentId)
            return this.savingAttachmentIds.includes(id)
        },
        isAttachmentDeleting(attachmentId) {
            const id = Number(attachmentId)
            return this.deletingAttachmentIds.includes(id)
        },
        markAttachmentSaving(attachmentId, isSaving) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return

            if (isSaving) {
                if (!this.savingAttachmentIds.includes(id)) {
                    this.savingAttachmentIds = [...this.savingAttachmentIds, id]
                }
                return
            }

            this.savingAttachmentIds = this.savingAttachmentIds.filter((item) => item !== id)
        },
        markAttachmentDeleting(attachmentId, isDeleting) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return

            if (isDeleting) {
                if (!this.deletingAttachmentIds.includes(id)) {
                    this.deletingAttachmentIds = [...this.deletingAttachmentIds, id]
                }
                return
            }

            this.deletingAttachmentIds = this.deletingAttachmentIds.filter((item) => item !== id)
        },
        canSaveAttachmentName(row) {
            const name = this.normalizeAttachmentName(row?.name)
            const savedName = this.normalizeAttachmentName(row?.savedName)
            return name !== '' && name !== savedName
        },
        async saveAttachmentName(row) {
            const id = Number(row?.id)
            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(id) || id <= 0 || !Number.isFinite(cardId) || cardId <= 0) return
            if (this.isAttachmentSaving(id) || this.isAttachmentDeleting(id)) return
            if (!this.canSaveAttachmentName(row)) return

            this.markAttachmentSaving(id, true)

            try {
                const updated = await this.materialCardStore.renameAttachment(id, cardId, row.name)
                if (!updated) return

                const nextName = this.normalizeAttachmentName(updated?.name) || this.normalizeAttachmentName(row.name)
                this.attachmentRows = this.attachmentRows.map((item) => {
                    if (item.id !== id) return item
                    return {
                        ...item,
                        name: nextName,
                        savedName: nextName,
                    }
                })

                this.applyAttachmentUpdateToCard(id, { name: nextName })
            } finally {
                this.markAttachmentSaving(id, false)
            }
        },
        async removeAttachment(row) {
            const id = Number(row?.id)
            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(id) || id <= 0 || !Number.isFinite(cardId) || cardId <= 0) return
            if (this.isAttachmentDeleting(id) || this.isAttachmentSaving(id)) return

            this.markAttachmentDeleting(id, true)

            try {
                const deleted = await this.materialCardStore.deleteAttachment(id, cardId)
                if (!deleted) return

                this.attachmentRows = this.attachmentRows.filter((item) => item.id !== id)
                this.removeAttachmentFromCard(id)
            } finally {
                this.markAttachmentDeleting(id, false)
            }
        },
        applyAttachmentUpdateToCard(attachmentId, changes) {
            const cardId = Number(this.attachmentDialogCardId)
            const id = Number(attachmentId)
            if (!Number.isFinite(cardId) || cardId <= 0 || !Number.isFinite(id) || id <= 0) return

            const card = this.cards.find((item) => Number(item?.id) === cardId)
            if (!card) return

            const attachments = Array.isArray(card.attachments) ? [...card.attachments] : []
            const index = attachments.findIndex((item) => Number(item?.id) === id)
            if (index < 0) return

            attachments[index] = {
                ...attachments[index],
                ...(changes || {}),
            }

            card.attachments = attachments
            card.attachments_count = attachments.length
        },
        removeAttachmentFromCard(attachmentId) {
            const cardId = Number(this.attachmentDialogCardId)
            const id = Number(attachmentId)
            if (!Number.isFinite(cardId) || cardId <= 0 || !Number.isFinite(id) || id <= 0) return

            const card = this.cards.find((item) => Number(item?.id) === cardId)
            if (!card) return

            const attachments = Array.isArray(card.attachments) ? card.attachments : []
            const nextAttachments = attachments.filter((item) => Number(item?.id) !== id)

            card.attachments = nextAttachments
            card.attachments_count = nextAttachments.length
        },
        isDownloadingAttachment(attachmentId) {
            const id = Number(attachmentId)
            return this.downloadingAttachmentIds.includes(id)
        },
        markAttachmentDownloading(attachmentId, isLoading) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return

            if (isLoading) {
                if (!this.downloadingAttachmentIds.includes(id)) {
                    this.downloadingAttachmentIds = [...this.downloadingAttachmentIds, id]
                }
                return
            }

            this.downloadingAttachmentIds = this.downloadingAttachmentIds.filter((item) => item !== id)
        },
        normalizeDownloadFileName(value) {
            const normalized = String(value || '').trim().replace(/[\\/:*?"<>|]/g, '_')
            return normalized.slice(0, 255) || 'Datei'
        },
        filenameFromContentDisposition(headerValue) {
            const header = String(headerValue || '').trim()
            if (!header) return ''

            const utf8Match = header.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
            if (utf8Match?.[1]) {
                try {
                    return decodeURIComponent(utf8Match[1]).trim()
                } catch {
                    return String(utf8Match[1]).trim()
                }
            }

            const plainMatch = header.match(/filename\s*=\s*\"?([^\";]+)\"?/i)
            return String(plainMatch?.[1] || '').trim()
        },
        async downloadAttachment(attachment) {
            const id = Number(attachment?.id)
            const downloadUrl = String(attachment?.download_url || '').trim()
            if (!Number.isFinite(id) || id <= 0 || !downloadUrl) return
            if (this.isDownloadingAttachment(id)) return

            this.markAttachmentDownloading(id, true)

            try {
                const response = await axios.get(downloadUrl, {
                    responseType: 'blob',
                })

                const disposition = response?.headers?.['content-disposition']
                const serverFileName = this.filenameFromContentDisposition(disposition)
                const fallbackName = this.attachmentDisplayName(attachment)
                const fileName = this.normalizeDownloadFileName(serverFileName || fallbackName)

                const blob = response?.data instanceof Blob ? response.data : new Blob([response?.data])
                const objectUrl = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = objectUrl
                link.download = fileName
                document.body.appendChild(link)
                link.click()
                link.remove()
                URL.revokeObjectURL(objectUrl)
            } catch (error) {
                const notification = useNotificationStore()
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Datei konnte nicht heruntergeladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.markAttachmentDownloading(id, false)
            }
        },
        fileAttachments(card) {
            const attachments = Array.isArray(card?.attachments) ? card.attachments : []
            return attachments.filter((attachment) =>
                String(attachment?.attachment_type || '').trim() === 'file'
                && String(attachment?.download_url || '').trim() !== ''
            )
        },
        attachmentDisplayName(attachment) {
            const name = String(attachment?.name || '').trim()
            if (name) return name

            const fallback = String(attachment?.file_path || '').trim()
            if (fallback) {
                const parts = fallback.split('/')
                return parts[parts.length - 1] || 'Datei'
            }

            return 'Datei'
        },
        attachmentChipLabel(attachment) {
            const name = this.attachmentDisplayName(attachment)
            const ext = this.attachmentExtension(attachment)
            if (!ext) return name
            return `${name} (${ext.toUpperCase()})`
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
    min-width: 140px;
}

.material-header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    column-gap: 8px;
    align-items: start;
    row-gap: 6px;
}

.title-type-inline {
    min-width: 0;
    max-width: 100%;
}

.material-title {
    min-width: 0;
    max-width: 100%;
    white-space: normal;
    word-break: break-word;
}

.material-type-chip,
.material-status-chip {
    max-width: 100%;
}

.material-status-chip {
    justify-self: end;
}

.attachments-count-chip {
    background-color: #1b4f82 !important;
    color: #ffffff !important;
    font-weight: 700;
    max-width: 100%;
}

.attachments-count-chip-clickable {
    cursor: pointer;
}

.attachment-block {
    border: 1px solid rgba(31, 95, 191, 0.3);
    border-radius: 10px;
    background: rgba(31, 95, 191, 0.08);
}

.classification-chip,
.attachment-chip {
    max-width: min(100%, 360px);
    font-weight: 600;
}

.classification-chip :deep(.v-chip__content),
.attachment-chip :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attachment-chip-wrap {
    min-width: 0;
}

.attachment-manage-row {
    border: 1px solid rgba(40, 58, 80, 0.14);
    border-radius: 10px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.75);
}

.attachment-name-field {
    min-width: 220px;
}

.attachment-manage-actions {
    min-width: 0;
}

.attachment-meta-text {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.source-link a {
    word-break: break-all;
}

.preview-text {
    white-space: pre-wrap;
    word-break: break-word;
}

.detail-text {
    white-space: pre-wrap;
    word-break: break-word;
}

:deep(.v-list-item__append) {
    align-self: flex-start;
    margin-top: 8px;
}

@media (max-width: 959px) {
    :deep(.overview-item.v-list-item) {
        grid-template-areas:
            "prepend content"
            "append append";
        grid-template-columns: max-content minmax(0, 1fr);
        align-items: start;
    }

    :deep(.overview-item .v-list-item__content) {
        min-width: 0;
    }

    :deep(.overview-item .v-list-item__append) {
        grid-area: append;
        margin-top: 10px;
        margin-inline-start: 0;
        width: 100%;
        justify-self: stretch;
    }

    .classification-chip,
    .attachment-chip {
        max-width: 100%;
    }

    .overview-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        margin-top: 8px;
        width: 100%;
        min-width: 0;
    }

    .overview-actions :deep(.v-btn) {
        width: 100%;
    }

    .attachment-manage-actions {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }

    .attachment-manage-actions :deep(.v-btn) {
        width: 100%;
    }
}
</style>
