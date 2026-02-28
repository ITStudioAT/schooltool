<template>
    <v-list class="bg-transparent pa-0">
        <v-list-item
            v-for="card in cards"
            :key="`alpha-card-${card.id}`"
            class="overview-alpha-item mb-2 px-3 py-2"
            rounded="lg"
            :style="cardBackgroundStyleFn(card)">
            <template #prepend>
                <v-avatar :color="statusColorFn(card.status)" variant="tonal" size="34" class="mr-3">
                    <v-icon :icon="sourceIconFn(card)" :color="statusColorFn(card.status)" />
                </v-avatar>
            </template>

            <div class="overview-alpha-line">
                <v-list-item-title class="overview-alpha-title">
                    {{ card.title || 'Ohne Titel' }}
                </v-list-item-title>

                <v-chip
                    v-if="card.type"
                    size="small"
                    variant="tonal"
                    color="primary"
                    class="material-type-chip">
                    {{ card.type }}
                </v-chip>

                <v-chip
                    v-if="card.is_linked"
                    size="small"
                    variant="outlined"
                    :color="linkedPermissionChipColor(card.linked_permission)">
                    {{ linkedPermissionLabel(card) }}
                </v-chip>

                <v-chip
                    v-if="card.attachments_count"
                    size="small"
                    variant="flat"
                    color="primary"
                    prepend-icon="mdi-paperclip"
                    class="attachments-count-chip attachments-count-chip-clickable"
                    @click="$emit('open-attachments', card)">
                    {{ attachmentCountCompactLabelFn(card) }}
                </v-chip>

                <v-chip size="small" :color="statusColorFn(card.status)" variant="flat" class="material-status-chip">
                    {{ statusLabelFn(card.status) }}
                </v-chip>
            </div>

            <v-list-item-subtitle class="overview-alpha-subtitle">
                {{ alphabeticAssignmentLineFn(card) }}
            </v-list-item-subtitle>

            <template #append>
                <div class="overview-alpha-actions">
                    <v-btn
                        icon="mdi-eye-outline"
                        size="small"
                        color="primary"
                        variant="text"
                        :disabled="actionDisabled"
                        @click="$emit('open-detail', card)" />
                    <v-btn
                        v-if="showEditAction"
                        icon="mdi-pencil-outline"
                        size="small"
                        color="primary"
                        variant="text"
                        :disabled="actionDisabled"
                        @click="$emit('open-edit', card)" />
                </div>
            </template>
        </v-list-item>
    </v-list>
</template>

<script>
const functionProp = {
    type: Function,
    required: true,
}

export default {
    name: 'MaterialsOverviewAlphaList',
    props: {
        cards: {
            type: Array,
            default: () => [],
        },
        actionDisabled: {
            type: Boolean,
            default: false,
        },
        showEditAction: {
            type: Boolean,
            default: true,
        },
        cardBackgroundStyleFn: functionProp,
        statusColorFn: functionProp,
        statusLabelFn: functionProp,
        sourceIconFn: functionProp,
        attachmentCountCompactLabelFn: functionProp,
        alphabeticAssignmentLineFn: functionProp,
    },
    emits: ['open-detail', 'open-edit', 'open-attachments'],
    methods: {
        linkedPermissionChipColor(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'error'
            if (normalized === 'read_write') return 'warning'
            return 'primary'
        },
        linkedPermissionLabel(card) {
            const normalizedLabel = String(card?.linked_permission_label || '').trim()
            if (normalizedLabel !== '') return normalizedLabel

            const permission = String(card?.linked_permission || '').trim()
            if (permission === 'full_access') return 'VOLLZUGRIFF'
            if (permission === 'read_write') return 'LESEN/SCHREIBEN'
            return 'NUR LESEN'
        },
    },
}
</script>

<style scoped>
.overview-alpha-item {
    border: 1px solid rgba(40, 58, 80, 0.12);
    background-color: rgba(255, 255, 255, 0.72);
}

.overview-alpha-line {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 4px 6px;
    min-width: 0;
}

.overview-alpha-title {
    font-weight: 700;
    flex: 0 1 auto;
    max-width: min(100%, 460px);
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-right: 2px;
}

.overview-alpha-subtitle {
    margin-top: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.overview-alpha-actions {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.material-type-chip,
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

:deep(.v-list-item__append) {
    align-self: flex-start;
    margin-top: 8px;
}
</style>
