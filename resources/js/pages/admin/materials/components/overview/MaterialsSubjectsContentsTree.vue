<template>
    <div class="overview-subjects-tree">
        <ul class="overview-subjects-list">
            <li
                v-for="subject in items"
                :key="`overview-subjects-subject-${subject.id || subject.name}`"
                class="overview-subjects-item">
                <div class="overview-subjects-group" :style="subjectGroupStyleFn(subject)">
                        <div class="overview-subjects-node-row">
                            <div class="overview-subjects-node overview-subjects-node--subject">
                                <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                                <span>{{ subject.name }}</span>
                            <v-icon
                                v-if="showShareIndicator('subject', subject.id)"
                                size="16"
                                icon="mdi-share-variant"
                                :color="shareIndicatorColorFn('subject', subject.id)"
                                class="ml-1" />
                        </div>
                        <v-btn
                            v-if="enableCreateButtons"
                            size="x-small"
                            color="primary"
                            variant="tonal"
                            icon="mdi-plus"
                            :title="'Neues Material in Fach anlegen'"
                            :disabled="actionBusy"
                            @click="$emit('open-create', {
                                level: 'subject',
                                subject: String(subject.name || '').trim(),
                                topic: '',
                                unit: '',
                            })" />
                        <v-btn
                            v-if="enableShareButtons"
                            size="x-small"
                            color="primary"
                            variant="outlined"
                            prepend-icon="mdi-share-variant-outline"
                            @click="$emit('open-share', { level: 'subject', id: subject.id, label: subject.name })">
                            Freigabe
                        </v-btn>
                    </div>

                    <ul v-if="subject.materials.length" class="overview-subjects-material-list">
                        <li
                            v-for="material in subject.materials"
                            :key="`overview-subjects-subject-material-${subject.id || subject.name}-${material.id}`"
                            class="overview-subjects-material-item">
                            <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                            <button
                                type="button"
                                class="overview-subjects-material-link"
                                :disabled="actionBusy"
                                @click="$emit('open-material', { id: material.id })">
                                {{ material.title }}
                            </button>
                            <v-chip
                                v-if="material.typeLabel"
                                size="x-small"
                                variant="outlined"
                                :color="material.typeColor || 'primary'"
                                class="overview-subjects-material-type">
                                {{ material.typeLabel }}
                            </v-chip>
                            <button
                                v-if="material.attachmentsCount > 0"
                                type="button"
                                class="overview-subjects-material-count overview-subjects-material-count--button"
                                :disabled="actionBusy"
                                title="Anhänge verwalten"
                                @click="openAttachments(material)">
                                <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                {{ material.attachmentsCount }}
                            </button>
                            <v-chip
                                size="x-small"
                                variant="tonal"
                                :color="statusColorFn(material.status)"
                                class="overview-subjects-material-status">
                                {{ statusLabelFn(material.status) }}
                            </v-chip>
                            <v-btn
                                v-if="enableRemoveButtons && material.canRemoveClassification !== false"
                                size="x-small"
                                color="warning"
                                variant="text"
                                density="comfortable"
                                prepend-icon="mdi-link-off"
                                :disabled="actionBusy"
                                title="Zuordnung entfernen"
                                @click="removeClassification(material)">
                                Entfernen
                            </v-btn>
                            <v-icon
                                v-if="showShareIndicator('material', material.id)"
                                size="14"
                                icon="mdi-share-variant"
                                :color="shareIndicatorColorFn('material', material.id)"
                                class="overview-subjects-share-icon" />
                            <v-btn
                                v-if="enableShareButtons"
                                size="x-small"
                                color="primary"
                                variant="text"
                                density="comfortable"
                                prepend-icon="mdi-share-variant-outline"
                                @click="$emit('open-share', {
                                    level: 'material',
                                    id: material.id,
                                    label: material.title,
                                    parentLabel: subject.name,
                                    kindLabel: material.typeLabel || '',
                                    kindColor: material.typeColor || 'primary',
                                    statusLabel: statusLabelFn(material.status),
                                    statusColor: statusColorFn(material.status),
                                    attachmentsCount: Number(material.attachmentsCount || 0),
                                })">
                                Freigabe
                            </v-btn>
                        </li>
                    </ul>

                    <ul v-if="subject.topics.length" class="overview-subjects-list overview-subjects-list--child">
                        <li
                            v-for="topic in subject.topics"
                            :key="`overview-subjects-topic-${topic.id || `${subject.id || subject.name}-${topic.name}`}`"
                            class="overview-subjects-item overview-subjects-topic-group"
                            :style="topicGroupStyleFn(subject)">
                            <div class="overview-subjects-node-row">
                                <div class="overview-subjects-node overview-subjects-node--topic">
                                    <v-icon size="14" icon="mdi-book-open-page-variant-outline" class="mr-2" />
                                    <span>{{ topic.name }}</span>
                                    <v-icon
                                        v-if="showShareIndicator('topic', topic.id)"
                                        size="15"
                                        icon="mdi-share-variant"
                                        :color="shareIndicatorColorFn('topic', topic.id)"
                                        class="ml-1" />
                                </div>
                                <v-btn
                                    v-if="enableCreateButtons"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    icon="mdi-plus"
                                    :title="'Neues Material in Thema anlegen'"
                                    :disabled="actionBusy"
                                    @click="$emit('open-create', {
                                        level: 'topic',
                                        subject: String(subject.name || '').trim(),
                                        topic: String(topic.name || '').trim(),
                                        unit: '',
                                    })" />
                                <v-btn
                                    v-if="enableShareButtons"
                                    size="x-small"
                                    color="primary"
                                    variant="outlined"
                                    prepend-icon="mdi-share-variant-outline"
                                    @click="$emit('open-share', { level: 'topic', id: topic.id, label: topic.name, parentLabel: subject.name })">
                                    Freigabe
                                </v-btn>
                            </div>

                            <ul v-if="topic.materials.length" class="overview-subjects-material-list">
                                <li
                                    v-for="material in topic.materials"
                                    :key="`overview-subjects-topic-material-${topic.id || topic.name}-${material.id}`"
                                    class="overview-subjects-material-item">
                                    <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                    <button
                                        type="button"
                                        class="overview-subjects-material-link"
                                        :disabled="actionBusy"
                                        @click="$emit('open-material', { id: material.id })">
                                        {{ material.title }}
                                    </button>
                                    <v-chip
                                        v-if="material.typeLabel"
                                        size="x-small"
                                        variant="outlined"
                                        :color="material.typeColor || 'primary'"
                                        class="overview-subjects-material-type">
                                        {{ material.typeLabel }}
                                    </v-chip>
                                    <button
                                        v-if="material.attachmentsCount > 0"
                                        type="button"
                                        class="overview-subjects-material-count overview-subjects-material-count--button"
                                        :disabled="actionBusy"
                                        title="Anhänge verwalten"
                                        @click="openAttachments(material)">
                                        <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                        {{ material.attachmentsCount }}
                                    </button>
                                    <v-chip
                                        size="x-small"
                                        variant="tonal"
                                        :color="statusColorFn(material.status)"
                                        class="overview-subjects-material-status">
                                        {{ statusLabelFn(material.status) }}
                                    </v-chip>
                                    <v-btn
                                        v-if="enableRemoveButtons && material.canRemoveClassification !== false"
                                        size="x-small"
                                        color="warning"
                                        variant="text"
                                        density="comfortable"
                                        prepend-icon="mdi-link-off"
                                        :disabled="actionBusy"
                                        title="Zuordnung entfernen"
                                        @click="removeClassification(material)">
                                        Entfernen
                                    </v-btn>
                                    <v-icon
                                        v-if="showShareIndicator('material', material.id)"
                                        size="14"
                                        icon="mdi-share-variant"
                                        :color="shareIndicatorColorFn('material', material.id)"
                                        class="overview-subjects-share-icon" />
                                    <v-btn
                                        v-if="enableShareButtons"
                                        size="x-small"
                                        color="primary"
                                        variant="text"
                                        density="comfortable"
                                        prepend-icon="mdi-share-variant-outline"
                                        @click="$emit('open-share', {
                                            level: 'material',
                                            id: material.id,
                                            label: material.title,
                                            parentLabel: `${subject.name} / ${topic.name}`,
                                            kindLabel: material.typeLabel || '',
                                            kindColor: material.typeColor || 'primary',
                                            statusLabel: statusLabelFn(material.status),
                                            statusColor: statusColorFn(material.status),
                                            attachmentsCount: Number(material.attachmentsCount || 0),
                                        })">
                                        Freigabe
                                    </v-btn>
                                </li>
                            </ul>

                            <ul v-if="topic.units.length" class="overview-subjects-list overview-subjects-list--child">
                                <li
                                    v-for="unit in topic.units"
                                    :key="`overview-subjects-unit-${unit.id || `${topic.id || topic.name}-${unit.name}`}`"
                                    class="overview-subjects-item">
                                    <div class="overview-subjects-node-row">
                                        <div class="overview-subjects-node overview-subjects-node--unit">
                                            <v-icon size="13" icon="mdi-circle-medium" class="mr-1" />
                                            <span>{{ unit.name }}</span>
                                            <v-icon
                                                v-if="showShareIndicator('unit', unit.id)"
                                                size="14"
                                                icon="mdi-share-variant"
                                                :color="shareIndicatorColorFn('unit', unit.id)"
                                                class="ml-1" />
                                        </div>
                                        <v-btn
                                            v-if="enableCreateButtons"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal"
                                            icon="mdi-plus"
                                            :title="'Neues Material in Unterpunkt anlegen'"
                                            :disabled="actionBusy"
                                            @click="$emit('open-create', {
                                                level: 'unit',
                                                subject: String(subject.name || '').trim(),
                                                topic: String(topic.name || '').trim(),
                                                unit: String(unit.name || '').trim(),
                                            })" />
                                        <v-btn
                                            v-if="enableShareButtons"
                                            size="x-small"
                                            color="primary"
                                            variant="outlined"
                                            prepend-icon="mdi-share-variant-outline"
                                            @click="$emit('open-share', { level: 'unit', id: unit.id, label: unit.name, parentLabel: `${subject.name} / ${topic.name}` })">
                                            Freigabe
                                        </v-btn>
                                    </div>

                                    <ul v-if="unit.materials.length" class="overview-subjects-material-list">
                                        <li
                                            v-for="material in unit.materials"
                                            :key="`overview-subjects-unit-material-${unit.id || unit.name}-${material.id}`"
                                            class="overview-subjects-material-item">
                                            <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                            <button
                                                type="button"
                                                class="overview-subjects-material-link"
                                                :disabled="actionBusy"
                                                @click="$emit('open-material', { id: material.id })">
                                                {{ material.title }}
                                            </button>
                                            <v-chip
                                                v-if="material.typeLabel"
                                                size="x-small"
                                                variant="outlined"
                                                :color="material.typeColor || 'primary'"
                                                class="overview-subjects-material-type">
                                                {{ material.typeLabel }}
                                            </v-chip>
                                            <button
                                                v-if="material.attachmentsCount > 0"
                                                type="button"
                                                class="overview-subjects-material-count overview-subjects-material-count--button"
                                                :disabled="actionBusy"
                                                title="Anhänge verwalten"
                                                @click="openAttachments(material)">
                                                <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                {{ material.attachmentsCount }}
                                            </button>
                                            <v-chip
                                                size="x-small"
                                                variant="tonal"
                                                :color="statusColorFn(material.status)"
                                                class="overview-subjects-material-status">
                                                {{ statusLabelFn(material.status) }}
                                            </v-chip>
                                            <v-btn
                                                v-if="enableRemoveButtons && material.canRemoveClassification !== false"
                                                size="x-small"
                                                color="warning"
                                                variant="text"
                                                density="comfortable"
                                                prepend-icon="mdi-link-off"
                                                :disabled="actionBusy"
                                                title="Zuordnung entfernen"
                                                @click="removeClassification(material)">
                                                Entfernen
                                            </v-btn>
                                            <v-icon
                                                v-if="showShareIndicator('material', material.id)"
                                                size="14"
                                                icon="mdi-share-variant"
                                                :color="shareIndicatorColorFn('material', material.id)"
                                                class="overview-subjects-share-icon" />
                                            <v-btn
                                                v-if="enableShareButtons"
                                                size="x-small"
                                                color="primary"
                                                variant="text"
                                                density="comfortable"
                                                prepend-icon="mdi-share-variant-outline"
                                                @click="$emit('open-share', {
                                                    level: 'material',
                                                    id: material.id,
                                                    label: material.title,
                                                    parentLabel: `${subject.name} / ${topic.name} / ${unit.name}`,
                                                    kindLabel: material.typeLabel || '',
                                                    kindColor: material.typeColor || 'primary',
                                                    statusLabel: statusLabelFn(material.status),
                                                    statusColor: statusColorFn(material.status),
                                                    attachmentsCount: Number(material.attachmentsCount || 0),
                                                })">
                                                Freigabe
                                            </v-btn>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</template>

<script>
export default {
    name: 'MaterialsSubjectsContentsTree',
    props: {
        items: {
            type: Array,
            required: true,
        },
        actionBusy: {
            type: Boolean,
            default: false,
        },
        enableShareButtons: {
            type: Boolean,
            default: false,
        },
        enableCreateButtons: {
            type: Boolean,
            default: true,
        },
        enableRemoveButtons: {
            type: Boolean,
            default: false,
        },
        showShareIndicators: {
            type: Boolean,
            default: false,
        },
        shareIndicatorColorFn: {
            type: Function,
            default: () => '',
        },
        statusColorFn: {
            type: Function,
            required: true,
        },
        statusLabelFn: {
            type: Function,
            required: true,
        },
        subjectGroupStyleFn: {
            type: Function,
            required: true,
        },
        topicGroupStyleFn: {
            type: Function,
            required: true,
        },
    },
    emits: ['open-material', 'open-share', 'open-create', 'open-attachments', 'remove-classification'],
    methods: {
        showShareIndicator(level, id) {
            if (!this.showShareIndicators) return false
            const color = String(this.shareIndicatorColorFn?.(level, id) || '').trim()
            return color !== ''
        },
        openAttachments(material) {
            const cardId = Number(material?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) return

            this.$emit('open-attachments', {
                id: cardId,
                title: String(material?.title || '').trim(),
                attachments: Array.isArray(material?.attachments) ? material.attachments : undefined,
            })
        },
        removeClassification(material) {
            if (material?.canRemoveClassification === false) return
            this.$emit('remove-classification', material)
        },
    },
}
</script>

<style scoped>
.overview-subjects-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 8px;
}

.overview-subjects-tree > .overview-subjects-list {
    gap: 32px;
}

.overview-subjects-list--child {
    margin-top: 6px;
    margin-left: 34px;
    padding-left: 20px;
    border-left: 1px dashed rgba(35, 61, 76, 0.25);
}

.overview-subjects-group {
    border: 1px solid rgba(35, 61, 76, 0.24);
    border-radius: 12px;
    padding: 10px 12px;
}

.overview-subjects-topic-group {
    position: relative;
    padding-left: 12px;
    border-radius: 8px;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.52) 0%, rgba(255, 255, 255, 0.24) 34%, transparent 62%);
}

.overview-subjects-topic-group::before {
    content: '';
    position: absolute;
    left: 0;
    top: 5px;
    bottom: 5px;
    width: 2px;
    border-radius: 999px;
    background: var(--overview-topic-accent-color, #1f6f8b);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.32);
}

.overview-subjects-topic-group + .overview-subjects-topic-group {
    margin-top: 24px;
}

.overview-subjects-node {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 8px;
}

.overview-subjects-node-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.overview-subjects-node--subject {
    font-weight: 700;
    background: rgba(35, 61, 76, 0.08);
}

.overview-subjects-node--topic {
    font-weight: 600;
    color: #2e4a5a;
    background: rgba(35, 61, 76, 0.05);
}

.overview-subjects-node--unit {
    font-weight: 700;
    color: #3c5a6d;
    background: rgba(35, 61, 76, 0.03);
}

.overview-subjects-material-list {
    list-style: none;
    margin: 6px 0 0 0;
    padding: 0 0 0 30px;
    display: grid;
    gap: 4px;
}

.overview-subjects-material-item {
    display: inline-flex;
    align-items: flex-start;
    gap: 6px;
    color: rgba(35, 61, 76, 0.92);
    font-size: 0.92rem;
    line-height: 1.32;
    flex-wrap: wrap;
}

.overview-subjects-material-link {
    border: 0;
    background: transparent;
    padding: 0;
    margin: 0;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.overview-subjects-material-link:disabled {
    cursor: default;
    opacity: 0.7;
}

.overview-subjects-material-link:not(:disabled):hover {
    text-decoration: underline;
}

.overview-subjects-material-status {
    margin-left: 2px;
}

.overview-subjects-material-type {
    margin-left: 2px;
}

.overview-subjects-material-count {
    display: inline-flex;
    align-items: center;
    margin-left: 6px;
    padding: 0 6px;
    border-radius: 999px;
    border: 1px solid rgba(35, 61, 76, 0.2);
    background: rgba(35, 61, 76, 0.06);
    font-size: 0.74rem;
    line-height: 1.2;
    color: rgba(35, 61, 76, 0.85);
}

.overview-subjects-material-count--button {
    background: rgba(35, 61, 76, 0.06);
    cursor: pointer;
}

.overview-subjects-material-count--button:hover:not(:disabled) {
    background: rgba(35, 61, 76, 0.12);
}

.overview-subjects-material-count--button:focus-visible {
    outline: 2px solid rgba(31, 111, 139, 0.45);
    outline-offset: 1px;
}

.overview-subjects-material-count--button:disabled {
    cursor: default;
    opacity: 0.7;
}

.overview-subjects-share-icon {
    margin-left: 2px;
    opacity: 0.95;
}
</style>
