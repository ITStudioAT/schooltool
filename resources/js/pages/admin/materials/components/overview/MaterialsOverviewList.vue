<template>
    <v-list class="bg-transparent pa-0">
        <v-list-item
            v-for="card in cards"
            :key="card.id"
            class="overview-item mb-3 px-4 py-3"
            rounded="lg"
            :style="cardBackgroundStyleFn(card)">
            <template #prepend>
                <v-avatar :color="statusColorFn(card.status)" variant="tonal" size="38" class="mr-4">
                    <v-icon :icon="sourceIconFn(card)" :color="statusColorFn(card.status)" />
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

                    <v-chip
                        v-if="card.is_linked"
                        size="small"
                        variant="outlined"
                        :color="linkedPermissionChipColor(card.linked_permission)">
                        {{ linkedPermissionLabel(card) }}
                    </v-chip>
                </div>

                <v-chip size="small" :color="statusColorFn(card.status)" variant="flat" class="material-status-chip">
                    {{ statusLabelFn(card.status) }}
                </v-chip>
            </div>

            <v-list-item-subtitle>
                <div v-if="classificationLabelsFn(card).length" class="d-flex flex-wrap ga-2 mt-2 mb-2">
                    <v-chip
                        v-for="(label, index) in classificationLabelsFn(card)"
                        :key="`classification-label-${card.id}-${index}`"
                        size="small"
                        variant="tonal"
                        color="primary"
                        class="classification-chip"
                        :title="label">
                        {{ label }}
                    </v-chip>
                </div>

                <div v-if="card.source_url" class="text-body-2 mb-1 source-link">
                    <a :href="card.source_url" target="_blank" rel="noopener noreferrer">{{ card.source_url }}</a>
                </div>

                <div v-if="card.source_text" class="text-body-2 text-medium-emphasis mb-1 preview-text">
                    {{ previewFn(card.source_text, 320) }}
                </div>

                <div v-else-if="card.notes" class="text-body-2 text-medium-emphasis mb-1 preview-text">
                    {{ previewFn(card.notes, 320) }}
                </div>
            </v-list-item-subtitle>

            <div class="d-flex flex-wrap ga-2 mt-2 mb-1 attachment-count-row">
                <v-chip
                    v-if="card.attachments_count"
                    size="small"
                    variant="flat"
                    color="primary"
                    prepend-icon="mdi-paperclip"
                    class="attachments-count-chip attachments-count-chip-clickable"
                    @click="$emit('open-attachments', card)">
                    {{ attachmentCountLabelFn(card) }}
                </v-chip>
            </div>

            <div v-if="fileAttachmentsFn(card).length" class="attachment-block d-flex flex-column ga-2 mt-1 mb-2 pa-2">
                <div class="attachment-chip-wrap d-flex flex-wrap ga-2">
                    <v-chip
                        v-for="attachment in fileAttachmentsFn(card)"
                        :key="`file-attachment-${card.id}-${attachment.id}`"
                        size="small"
                        variant="outlined"
                        color="primary"
                        prepend-icon="mdi-paperclip"
                        append-icon="mdi-download"
                        class="attachment-chip"
                        :disabled="isDownloadingAttachmentFn(attachment.id)"
                        :title="attachmentChipLabelFn(attachment)"
                        @click.prevent="$emit('download-attachment', attachment)">
                        {{ attachmentChipLabelFn(attachment) }}
                    </v-chip>
                </div>
            </div>

            <div class="text-caption text-medium-emphasis mt-1">
                Aktualisiert: {{ formatDateTimeFn(card.updated_at) }}
            </div>

            <template #append>
                <div class="overview-actions d-flex flex-column align-end ga-2">
                    <v-btn
                        icon="mdi-eye-outline"
                        size="small"
                        color="primary"
                        variant="tonal"
                        :title="'Detail'"
                        :disabled="actionDisabled"
                        @click="$emit('open-detail', card)" />

                    <v-btn
                        v-if="showEditAction"
                        icon="mdi-pencil-outline"
                        size="small"
                        color="primary"
                        variant="flat"
                        :title="'Bearbeiten'"
                        :disabled="actionDisabled"
                        @click="$emit('open-edit', card)" />

                    <v-btn
                        v-if="showEditAction && card.is_linked"
                        icon="mdi-link-off"
                        size="small"
                        color="warning"
                        variant="outlined"
                        :title="'Link entfernen'"
                        :disabled="actionDisabled"
                        @click="$emit('unlink-link', card)" />
                </div>
            </template>
        </v-list-item>
    </v-list>
</template>

<script>
const functionProp = { type: Function, required: true }

export default {
    name: 'MaterialsOverviewList',
    props: {
        cards: { type: Array, default: () => [] },
        actionDisabled: { type: Boolean, default: false },
        showEditAction: { type: Boolean, default: true },
        cardBackgroundStyleFn: functionProp,
        statusColorFn: functionProp,
        statusLabelFn: functionProp,
        sourceIconFn: functionProp,
        classificationLabelsFn: functionProp,
        previewFn: functionProp,
        attachmentCountLabelFn: functionProp,
        fileAttachmentsFn: functionProp,
        isDownloadingAttachmentFn: functionProp,
        attachmentChipLabelFn: functionProp,
        formatDateTimeFn: functionProp,
    },
    emits: ['open-detail', 'open-edit', 'open-attachments', 'download-attachment', 'unlink-link'],
    methods: {
        linkedPermissionChipColor(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'error'
            if (normalized === 'read_write') return 'warning'
            if (normalized === 'read_append') return 'info'
            return 'primary'
        },
        linkedPermissionLabel(card) {
            const normalizedLabel = String(card?.linked_permission_label || '').trim()
            if (normalizedLabel !== '') return normalizedLabel

            const permission = String(card?.linked_permission || '').trim()
            if (permission === 'full_access') return 'VOLLZUGRIFF'
            if (permission === 'read_write') return 'LESEN/SCHREIBEN'
            if (permission === 'read_append') return 'LESEN/HINZUFÜGEN'
            return 'NUR LESEN'
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
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
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
    background-color: #6f87c1 !important;
    color: #ffffff !important;
    font-weight: 700;
    max-width: 100%;
}

.attachments-count-chip-clickable {
    cursor: pointer;
}

.attachment-count-row {
    opacity: 1 !important;
}

.attachment-block {
    border: 1px solid rgba(31, 95, 191, 0.3);
    border-radius: 10px;
    background: rgba(31, 95, 191, 0.08);
}

.classification-chip,
.attachment-chip {
    max-width: min(100%, 360px);
}

.classification-chip {
    font-weight: 600;
}

.attachment-chip {
    color: #6f87c1 !important;
    border-color: #6f87c1 !important;
    font-weight: 400;
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

.source-link a {
    word-break: break-all;
}

.preview-text {
    white-space: pre-wrap;
    word-break: break-word;
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
    }

    .overview-actions {
        width: 100%;
        min-width: 0;
        flex-direction: row;
        justify-content: flex-end;
        align-items: center;
    }

    .overview-actions :deep(.v-btn) {
        flex: 0 0 auto;
    }
}
</style>

