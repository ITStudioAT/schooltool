<template>
    <v-row class="overview-grid ma-0">
        <v-col
            v-for="card in cards"
            :key="`grid-card-${card.id}`"
            cols="12"
            sm="6"
            md="4"
            lg="3"
            xl="2"
            class="pa-2 d-flex">
            <v-card
                class="overview-grid-item d-flex flex-column flex-grow-1"
                rounded="lg"
                elevation="0"
                :style="cardBackgroundStyleFn(card)">
                <v-card-text class="pa-3 d-flex flex-column ga-2">
                    <div class="overview-grid-header">
                        <v-avatar :color="statusColorFn(card.status)" variant="tonal" size="32">
                            <v-icon :icon="sourceIconFn(card)" :color="statusColorFn(card.status)" />
                        </v-avatar>

                        <v-chip size="x-small" :color="statusColorFn(card.status)" variant="flat" class="material-status-chip">
                            {{ statusLabelFn(card.status) }}
                        </v-chip>
                    </div>

                    <div class="text-subtitle-2 font-weight-bold overview-grid-title">
                        {{ card.title || 'Ohne Titel' }}
                    </div>

                    <div class="d-flex flex-wrap ga-1">
                        <v-chip v-if="card.type" size="x-small" variant="tonal" color="primary">
                            {{ card.type }}
                        </v-chip>
                        <v-chip
                            v-if="card.is_linked"
                            size="x-small"
                            variant="outlined"
                            :color="linkedPermissionChipColor(card.linked_permission)">
                            {{ linkedPermissionLabel(card) }}
                        </v-chip>

                        <v-chip
                            v-if="card.attachments_count"
                            size="x-small"
                            variant="flat"
                            color="primary"
                            prepend-icon="mdi-paperclip"
                            class="attachments-count-chip attachments-count-chip-clickable"
                            @click="$emit('open-attachments', card)">
                            {{ attachmentCountCompactLabelFn(card) }}
                        </v-chip>
                    </div>

                    <div v-if="classificationLabelsFn(card).length" class="d-flex flex-wrap ga-1">
                        <v-chip
                            v-for="(label, index) in classificationLabelsFn(card).slice(0, 2)"
                            :key="`grid-classification-label-${card.id}-${index}`"
                            size="x-small"
                            variant="tonal"
                            color="primary"
                            class="classification-chip"
                            :title="label">
                            {{ label }}
                        </v-chip>
                    </div>

                    <div v-if="card.source_text" class="text-caption text-medium-emphasis overview-grid-preview">
                        {{ previewFn(card.source_text, 160) }}
                    </div>

                    <div v-else-if="card.notes" class="text-caption text-medium-emphasis overview-grid-preview">
                        {{ previewFn(card.notes, 160) }}
                    </div>

                    <div v-else-if="card.source_url" class="text-caption source-link overview-grid-link">
                        <a :href="card.source_url" target="_blank" rel="noopener noreferrer">
                            {{ previewFn(card.source_url, 80) }}
                        </a>
                    </div>

                    <div class="text-caption text-medium-emphasis mt-auto">
                        {{ formatDateTimeFn(card.updated_at) }}
                    </div>
                </v-card-text>

                <v-card-actions class="px-3 pb-3 pt-0 overview-grid-actions">
                    <v-btn
                        icon
                        size="small"
                        rounded="circle"
                        color="primary"
                        variant="tonal"
                        class="overview-grid-action-btn"
                        :title="'Detail'"
                        :disabled="actionDisabled"
                        @click="$emit('open-detail', card)">
                        <v-icon icon="mdi-eye-outline" />
                    </v-btn>

                    <v-btn
                        v-if="showEditAction"
                        icon
                        size="small"
                        rounded="circle"
                        color="primary"
                        variant="flat"
                        class="overview-grid-action-btn"
                        :title="'Bearbeiten'"
                        :disabled="actionDisabled"
                        @click="$emit('open-edit', card)">
                        <v-icon icon="mdi-pencil-outline" />
                    </v-btn>

                    <v-btn
                        v-if="showEditAction && card.is_linked"
                        icon
                        size="small"
                        rounded="circle"
                        color="warning"
                        variant="outlined"
                        class="overview-grid-action-btn"
                        :title="'Link entfernen'"
                        :disabled="actionDisabled"
                        @click="$emit('unlink-link', card)">
                        <v-icon icon="mdi-link-off" />
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-col>
    </v-row>
</template>

<script>
const functionProp = { type: Function, required: true }

export default {
    name: 'MaterialsOverviewGrid',
    props: {
        cards: { type: Array, default: () => [] },
        actionDisabled: { type: Boolean, default: false },
        showEditAction: { type: Boolean, default: true },
        cardBackgroundStyleFn: functionProp,
        statusColorFn: functionProp,
        statusLabelFn: functionProp,
        sourceIconFn: functionProp,
        attachmentCountCompactLabelFn: functionProp,
        classificationLabelsFn: functionProp,
        previewFn: functionProp,
        formatDateTimeFn: functionProp,
    },
    emits: ['open-detail', 'open-edit', 'open-attachments', 'unlink-link'],
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
.overview-grid {
    margin-left: -8px;
    margin-right: -8px;
}

.overview-grid-item {
    border: 1.5px solid rgba(40, 58, 80, 0.18);
    background-color: rgba(255, 255, 255, 0.96);
    box-shadow: 0 2px 8px rgba(40, 58, 80, 0.08);
    min-height: 100%;
    transition: box-shadow 0.18s ease, border-color 0.18s ease;
}

.overview-grid-item:hover {
    box-shadow: 0 4px 16px rgba(40, 58, 80, 0.14);
    border-color: rgba(40, 58, 80, 0.28);
}

.overview-grid-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}

.overview-grid-title {
    min-height: 2.8em;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.overview-grid-preview {
    white-space: pre-wrap;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: 3.6em;
}

.overview-grid-link a {
    word-break: break-all;
}

.overview-grid-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
}

.overview-grid-actions :deep(.v-btn) {
    width: auto;
}

.overview-grid-action-btn {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    padding: 0 !important;
    border-radius: 50% !important;
}

.material-status-chip {
    max-width: 100%;
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

.classification-chip {
    max-width: min(100%, 360px);
    font-weight: 600;
}

.classification-chip :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>

