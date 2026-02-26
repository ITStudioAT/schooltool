<template>
    <v-dialog :model-value="modelValue" max-width="860" persistent @update:modelValue="$emit('update:modelValue', $event)">
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2">
                <span class="text-h5">Material-Details</span>
                <v-spacer />
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    :disabled="loading || isDeleting"
                    @click="closeDialogFn" />
            </v-card-title>

            <v-card-text>
                <v-skeleton-loader v-if="loading" type="article, list-item-two-line@3" />

                <template v-else-if="card">
                    <div class="material-header mb-3">
                        <div class="title-type-inline d-inline-flex align-center flex-wrap ga-2">
                            <div class="text-subtitle-1 font-weight-bold material-title">
                                {{ card.title || 'Material' }}
                            </div>

                            <v-chip v-if="card.type" size="small" variant="tonal" color="primary" class="material-type-chip">
                                {{ card.type }}
                            </v-chip>
                        </div>

                        <v-chip size="small" :color="statusColorFn(card.status)" variant="flat" class="material-status-chip">
                            {{ statusLabelFn(card.status) }}
                        </v-chip>
                    </div>

                    <div v-if="classificationLabelsFn(card).length" class="mb-4">
                        <div class="text-subtitle-2 mb-2">Fach / Thema / Bereich</div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip
                                v-for="(label, index) in classificationLabelsFn(card)"
                                :key="`detail-classification-${card.id}-${index}`"
                                size="small"
                                variant="tonal"
                                color="primary"
                                class="classification-chip"
                                :title="label">
                                {{ label }}
                            </v-chip>
                        </div>
                    </div>

                    <div v-if="card.source_url" class="mb-4 source-link">
                        <div class="text-subtitle-2 mb-1">Link</div>
                        <a :href="card.source_url" target="_blank" rel="noopener noreferrer">
                            {{ card.source_url }}
                        </a>
                    </div>

                    <div v-if="card.source_text" class="mb-4">
                        <div class="text-subtitle-2 mb-1">Beschreibung</div>
                        <div class="text-body-2 detail-text">{{ card.source_text }}</div>
                    </div>

                    <div v-if="card.notes" class="mb-4">
                        <div class="text-subtitle-2 mb-1">Notiz</div>
                        <div class="text-body-2 detail-text">{{ card.notes }}</div>
                    </div>

                    <div v-if="detailAttachmentsFn(card).length" class="mb-2">
                        <div class="text-subtitle-2 mb-2">Anhänge</div>

                        <v-list class="bg-transparent pa-0">
                            <v-list-item
                                v-for="attachment in detailAttachmentsFn(card)"
                                :key="`detail-attachment-${card.id}-${attachment.id}`"
                                class="px-0 py-2">
                                <div class="d-flex flex-column flex-md-row align-md-center ga-2 w-100 detail-attachment-row">
                                    <div class="flex-grow-1 detail-attachment-content">
                                        <div class="text-body-2 font-weight-medium detail-attachment-name">
                                            {{ attachmentDisplayNameFn(attachment) }}
                                        </div>
                                        <div class="d-flex flex-wrap align-center ga-2">
                                            <v-tooltip location="top">
                                                <template #activator="{ props }">
                                                    <v-chip
                                                        v-bind="props"
                                                        size="x-small"
                                                        variant="tonal"
                                                        color="secondary"
                                                        class="link-copy-chip"
                                                        @click="copyAttachmentChipToClipboardFn(attachment)">
                                                        {{ attachmentTypeLabelFn(attachment) }}
                                                    </v-chip>
                                                </template>
                                                <div class="d-flex align-center ga-1">
                                                    <v-icon icon="mdi-content-copy" size="14" />
                                                    <span>In Zwischenablage kopieren</span>
                                                </div>
                                            </v-tooltip>
                                            <div class="text-caption text-medium-emphasis">
                                                {{ attachmentSizeBytesFn(attachment) > 0 ? formatBytesFn(attachmentSizeBytesFn(attachment)) : '' }}
                                            </div>
                                        </div>
                                        <div
                                            v-if="attachmentSourceUrlFn(attachment)"
                                            class="text-caption attachment-source-text source-link">
                                            Quelle:
                                            <a :href="attachmentSourceUrlFn(attachment)" target="_blank" rel="noopener noreferrer">
                                                {{ previewFn(attachmentSourceUrlFn(attachment), 110) }}
                                            </a>
                                        </div>
                                        <div
                                            v-if="attachmentDownloadedAtLabelFn(attachment)"
                                            class="text-caption text-medium-emphasis attachment-source-text">
                                            Heruntergeladen: {{ attachmentDownloadedAtLabelFn(attachment) }}
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap ga-2 justify-end detail-attachment-actions">
                                        <v-btn
                                            v-if="attachment.attachment_type === 'file' && (attachment.preview_url || attachment.download_url)"
                                            icon="mdi-eye-outline"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'Vorschau'"
                                            :loading="isPreviewingAttachmentFn(attachment.id)"
                                            :disabled="isDeleting"
                                            @click="previewAttachmentFn(attachment)" />

                                        <v-btn
                                            v-if="attachment.attachment_type === 'file' && attachment.download_url"
                                            icon="mdi-download"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'Download'"
                                            :loading="isDownloadingAttachmentFn(attachment.id)"
                                            :disabled="isDeleting"
                                            @click="downloadAttachmentFn(attachment)" />

                                        <v-btn
                                            v-if="isEditableTextAttachmentFn(attachment)"
                                            icon="mdi-file-word-outline"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'DOCX'"
                                            :loading="isDownloadingAttachmentFn(attachment.id)"
                                            :disabled="isDeleting"
                                            @click="downloadAttachmentDocxFn(attachment)" />

                                        <v-btn
                                            v-else-if="attachment.url"
                                            icon="mdi-open-in-new"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'Öffnen'"
                                            :href="attachment.url"
                                            target="_blank"
                                            rel="noopener noreferrer" />
                                    </div>
                                </div>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="text-caption text-medium-emphasis mt-3">
                        Aktualisiert: {{ formatDateTimeFn(card.updated_at) }}
                    </div>
                </template>
            </v-card-text>

            <v-card-actions class="px-6 pb-6 pt-2 d-flex flex-wrap justify-end ga-2">
                <template v-if="!readOnlyMaterialActions && deleteStep === 0">
                    <v-btn
                        color="warning"
                        variant="tonal"
                        prepend-icon="mdi-delete"
                        :disabled="loading || isDeleting || isSavingEdit"
                        @click="startDeleteFlowFn">
                        Löschen
                    </v-btn>
                </template>

                <template v-else-if="!readOnlyMaterialActions">
                    <v-btn
                        color="success"
                        variant="tonal"
                        prepend-icon="mdi-delete-off"
                        :disabled="isDeleting"
                        @click="resetDeleteFlowFn">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-delete"
                        :loading="isDeleting"
                        :disabled="loading || isSavingEdit"
                        @click="confirmDeleteFn">
                        Löschen
                    </v-btn>
                </template>

                <v-btn
                    variant="text"
                    :disabled="loading || isDeleting || isSavingEdit"
                    @click="closeDialogFn">
                    Schließen
                </v-btn>

                <v-btn
                    v-if="!readOnlyMaterialActions"
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-pencil"
                    :disabled="loading || isDeleting || isSavingEdit"
                    @click="openEditFn">
                    Bearbeiten
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
const functionProp = { type: Function, required: true }

export default {
    name: 'MaterialDetailDialog',
    props: {
        modelValue: { type: Boolean, required: true },
        loading: { type: Boolean, default: false },
        card: { type: Object, default: null },
        isDeleting: { type: Boolean, default: false },
        isSavingEdit: { type: Boolean, default: false },
        readOnlyMaterialActions: { type: Boolean, default: false },
        deleteStep: { type: Number, default: 0 },
        closeDialogFn: functionProp,
        statusColorFn: functionProp,
        statusLabelFn: functionProp,
        classificationLabelsFn: functionProp,
        detailAttachmentsFn: functionProp,
        attachmentDisplayNameFn: functionProp,
        copyAttachmentChipToClipboardFn: functionProp,
        attachmentTypeLabelFn: functionProp,
        attachmentSizeBytesFn: functionProp,
        formatBytesFn: functionProp,
        attachmentSourceUrlFn: functionProp,
        previewFn: functionProp,
        attachmentDownloadedAtLabelFn: functionProp,
        isPreviewingAttachmentFn: functionProp,
        previewAttachmentFn: functionProp,
        isDownloadingAttachmentFn: functionProp,
        downloadAttachmentFn: functionProp,
        isEditableTextAttachmentFn: functionProp,
        downloadAttachmentDocxFn: functionProp,
        formatDateTimeFn: functionProp,
        startDeleteFlowFn: functionProp,
        resetDeleteFlowFn: functionProp,
        confirmDeleteFn: functionProp,
        openEditFn: functionProp,
    },
    emits: ['update:modelValue'],
}
</script>

<style scoped>
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

.classification-chip,
.attachment-chip {
    max-width: min(100%, 360px);
}

.classification-chip {
    font-weight: 600;
}

.classification-chip :deep(.v-chip__content),
.attachment-chip :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.link-copy-chip {
    cursor: pointer;
}

.detail-attachment-row {
    min-width: 0;
}

.detail-attachment-content {
    min-width: 0;
    flex: 1 1 auto;
}

.detail-attachment-name {
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.detail-attachment-actions {
    flex: 0 0 auto;
    align-self: flex-start;
    max-width: 100%;
}

.attachment-source-text {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.source-link a {
    word-break: break-all;
}

.detail-text {
    white-space: pre-wrap;
    word-break: break-word;
}

@media (max-width: 959px) {
    .classification-chip,
    .attachment-chip {
        max-width: 100%;
    }

    .detail-attachment-actions {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>
