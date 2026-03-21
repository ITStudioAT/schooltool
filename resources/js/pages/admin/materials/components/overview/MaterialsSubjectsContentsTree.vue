<template>
    <div class="overview-subjects-tree">
        <div class="overview-subjects-node-row overview-workspace-row">
            <button
                type="button"
                class="overview-subjects-node overview-subjects-node--workspace overview-subjects-node--workspace-toggle"
                :disabled="actionBusy"
                :aria-expanded="workspaceExpanded ? 'true' : 'false'"
                @click="toggleWorkspaceExpanded">
                <v-icon size="18" :icon="workspaceExpanded ? 'mdi-chevron-down' : 'mdi-chevron-right'" class="mr-1" />
                <v-icon size="20" icon="mdi-briefcase-outline" class="mr-2" />
                <span>Workspace</span>
            </button>
            <v-btn
                v-if="workspaceExpanded && enableCreateButtons"
                size="small"
                variant="tonal"
                color="primary"
                class="overview-workspace-structure-btn"
                :disabled="actionBusy"
                @click="toggleWorkspaceStructureButtons">
                {{ isWorkspaceStructureButtonsVisible() ? 'Struktur schließen' : 'Struktur ändern' }}
            </v-btn>
            <v-btn
                v-if="!isWorkspaceStructureButtonsVisible()"
                size="x-small"
                color="primary"
                variant="tonal"
                icon="mdi-share-variant-outline"
                class="overview-share-btn"
                :title="'Teilen'"
                :disabled="actionBusy"
                @click.stop="handleWorkspaceShareClick" />
        </div>

        <ul v-if="workspaceExpanded" class="overview-subjects-list">
            <li
                v-for="(subject, subjectIndex) in items"
                :key="`overview-subjects-subject-${subject.id || subject.name}`"
                class="overview-subjects-item">
                <div v-if="enableCreateButtons && isWorkspaceStructureButtonsVisible()" class="overview-shared-structure-create-row">
                    <v-btn
                        prepend-icon="mdi-plus"
                        size="small"
                        density="comfortable"
                        variant="outlined"
                        color="primary"
                        class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                        :disabled="actionBusy"
                        title="Fach hinzufügen"
                        aria-label="Fach hinzufügen"
                        @click.stop="openWorkspaceCreateSubjectDialog({ beforeSubjectId: subject?.id })">
                        Fach
                    </v-btn>
                </div>
                <div class="overview-subjects-group" :style="subjectGroupStyleFn(subject)">
                    <div class="overview-subjects-node-row">
                        <div
                            class="overview-subjects-node overview-subjects-node--subject"
                            :class="{ 'overview-subjects-node--clickable': subjectHasChildren(subject) }"
                            @click.stop="subjectHasChildren(subject) && !actionBusy ? toggleWorkspaceSubjectExpanded(subject) : undefined">
                            <button
                                v-if="subjectHasChildren(subject)"
                                type="button"
                                class="overview-subjects-node-toggle"
                                :aria-expanded="isWorkspaceSubjectExpanded(subject) ? 'true' : 'false'"
                                :aria-label="`Fach ${workspaceNodeTitle('subject', subject)} ein- oder ausklappen`"
                                :disabled="actionBusy"
                                @click.stop="toggleWorkspaceSubjectExpanded(subject)">
                                <v-icon size="16" :icon="isWorkspaceSubjectExpanded(subject) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                            </button>
                            <span v-else class="overview-subjects-node-toggle overview-subjects-node-toggle--empty" aria-hidden="true">
                                <v-icon size="14" icon="mdi-minus" />
                            </span>
                            <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                            <span>{{ workspaceNodeTitle('subject', subject) }}</span>
                            <v-icon
                                v-if="hasPersistedNodeId(subject.id) && showShareIndicator('subject', subject.id)"
                                size="16"
                                icon="mdi-share-variant"
                                :color="shareIndicatorColorFn('subject', subject.id)"
                                class="ml-1" />
                            <div
                                v-if="enableCreateButtons && isWorkspaceStructureButtonsVisible()"
                                class="overview-shared-hierarchy-node-inline-actions">
                                <v-btn
                                    icon="mdi-arrow-up"
                                    size="x-small"
                                    density="comfortable"
                                    variant="text"
                                    color="primary"
                                    :disabled="actionBusy || subjectIndex === 0"
                                    title="Fach nach oben"
                                    @click.stop="moveWorkspaceNode('subject', subject, 'up')" />
                                <v-btn
                                    icon="mdi-arrow-down"
                                    size="x-small"
                                    density="comfortable"
                                    variant="text"
                                    color="primary"
                                    :disabled="actionBusy || subjectIndex >= (items.length - 1)"
                                    title="Fach nach unten"
                                    @click.stop="moveWorkspaceNode('subject', subject, 'down')" />
                                <v-btn
                                    icon="mdi-pencil"
                                    size="x-small"
                                    density="comfortable"
                                    variant="text"
                                    color="primary"
                                    :disabled="actionBusy"
                                    title="Fach bearbeiten"
                                    @click.stop="openWorkspaceRenameDialog('subject', subject)" />
                                <v-btn
                                    v-if="sharedSubjectCanDelete(subject)"
                                    icon="mdi-delete-outline"
                                    size="x-small"
                                    density="comfortable"
                                    variant="text"
                                    color="warning"
                                    :disabled="actionBusy"
                                    title="Fach löschen"
                                    @click.stop="openWorkspaceDeleteDialog('subject', subject)" />
                            </div>
                        </div>
                        <v-btn
                            v-if="hasPersistedNodeId(subject.id) && !isWorkspaceStructureButtonsVisible()"
                            size="x-small"
                            color="primary"
                            variant="tonal"
                            icon="mdi-share-variant-outline"
                            class="overview-share-btn"
                            :title="'Teilen'"
                            :disabled="actionBusy"
                            @click.stop="handleShareClick({ level: 'subject', id: subject.id, label: workspaceNodeTitle('subject', subject) })" />
                        <v-btn
                            v-if="enableCreateButtons && hasPersistedNodeId(subject.id) && !isWorkspaceStructureButtonsVisible()"
                            size="x-small"
                            color="primary"
                            variant="tonal"
                            icon="mdi-file-plus-outline"
                            :title="'Neues Material in Fach anlegen'"
                            :disabled="actionBusy"
                            @click="$emit('open-create', {
                                level: 'subject',
                                subject: String(workspaceNodeTitle('subject', subject) || '').trim(),
                                topic: '',
                                unit: '',
                            })" />
                    </div>

                    <ul v-if="isWorkspaceSubjectExpanded(subject) && subject.materials.length && !isWorkspaceStructureButtonsVisible()" class="overview-subjects-material-list">
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
                            <v-btn
                                v-if="hasPersistedNodeId(material.id)"
                                size="x-small"
                                color="primary"
                                variant="tonal"
                                icon="mdi-share-variant-outline"
                                class="overview-share-btn overview-share-btn--material"
                                :title="'Teilen'"
                                :disabled="actionBusy"
                                @click.stop="handleShareClick({
                                    level: 'material',
                                    id: material.id,
                                    label: material.title,
                                    parentLabel: workspaceNodeTitle('subject', subject),
                                    kindLabel: material.typeLabel || '',
                                    kindColor: material.typeColor || 'primary',
                                    statusLabel: statusLabelFn(material.status),
                                    statusColor: statusColorFn(material.status),
                                    attachmentsCount: Number(material.attachmentsCount || 0),
                                })" />
                            <v-chip
                                v-if="material.isLinked"
                                size="x-small"
                                variant="outlined"
                                :color="linkedPermissionChipColor(material.linkedPermission)"
                                class="overview-subjects-material-type">
                                {{ linkedPermissionLabel(material) }}
                            </v-chip>
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
                                v-if="enableRemoveButtons && material.isLinked"
                                size="x-small"
                                color="warning"
                                variant="text"
                                density="comfortable"
                                prepend-icon="mdi-link-off"
                                :disabled="actionBusy"
                                title="Link entfernen"
                                @click="$emit('unlink-linked-material', material)">
                                Link entfernen
                            </v-btn>
                            <v-icon
                                v-if="showShareIndicator('material', material.id)"
                                size="14"
                                icon="mdi-share-variant"
                                :color="shareIndicatorColorFn('material', material.id)"
                                class="overview-subjects-share-icon" />
                        </li>
                    </ul>

                    <ul v-if="isWorkspaceSubjectExpanded(subject) && subject.topics.length" class="overview-subjects-list overview-subjects-list--child">
                        <li
                            v-for="(topic, topicIndex) in subject.topics"
                            :key="`overview-subjects-topic-${topic.id || `${subject.id || subject.name}-${topic.name}`}`"
                            class="overview-subjects-item overview-subjects-topic-group"
                            :style="topicGroupStyleFn(subject)">
                            <div v-if="enableCreateButtons && isWorkspaceStructureButtonsVisible()" class="overview-shared-structure-create-row overview-shared-structure-create-row--topic">
                                <v-btn
                                    prepend-icon="mdi-plus"
                                    size="small"
                                    density="comfortable"
                                    variant="outlined"
                                    color="primary"
                                    class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                    :disabled="actionBusy"
                                    title="Thema hinzufügen"
                                    aria-label="Thema hinzufügen"
                                    @click.stop="openWorkspaceCreateTopicDialog(subject, { beforeTopicId: topic?.id })">
                                    Thema
                                </v-btn>
                            </div>
                            <div class="overview-subjects-node-row">
                                <div
                                    class="overview-subjects-node overview-subjects-node--topic"
                                    :class="{ 'overview-subjects-node--clickable': topicHasChildren(topic) }"
                                    @click.stop="topicHasChildren(topic) && !actionBusy ? toggleWorkspaceTopicExpanded(topic) : undefined">
                                    <button
                                        v-if="topicHasChildren(topic)"
                                        type="button"
                                        class="overview-subjects-node-toggle"
                                        :aria-expanded="isWorkspaceTopicExpanded(topic) ? 'true' : 'false'"
                                        :aria-label="`Thema ${workspaceNodeTitle('topic', topic)} ein- oder ausklappen`"
                                        :disabled="actionBusy"
                                        @click.stop="toggleWorkspaceTopicExpanded(topic)">
                                        <v-icon size="14" :icon="isWorkspaceTopicExpanded(topic) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                                    </button>
                                    <v-icon
                                        size="14"
                                        :icon="topic.isLinked ? 'mdi-link-variant' : 'mdi-book-open-page-variant-outline'"
                                        :color="topic.isLinked ? linkedPermissionChipColor(topic.linkedPermission) : undefined"
                                        class="mr-2" />
                                    <span>{{ workspaceNodeTitle('topic', topic) }}</span>
                                    <v-chip
                                        v-if="topic.isLinked"
                                        size="x-small"
                                        variant="outlined"
                                        :color="linkedPermissionChipColor(topic.linkedPermission)"
                                        class="overview-subjects-material-type">
                                        {{ linkedPermissionLabel(topic) }}
                                    </v-chip>
                                    <v-icon
                                        v-if="hasPersistedNodeId(topic.id) && showShareIndicator('topic', topic.id)"
                                        size="15"
                                        icon="mdi-share-variant"
                                        :color="shareIndicatorColorFn('topic', topic.id)"
                                        class="ml-1" />
                                    <div
                                        v-if="enableCreateButtons && isWorkspaceStructureButtonsVisible()"
                                        class="overview-shared-hierarchy-node-inline-actions">
                                        <v-btn
                                            icon="mdi-arrow-up"
                                            size="x-small"
                                            density="comfortable"
                                            variant="text"
                                            color="primary"
                                            :disabled="actionBusy || topicIndex === 0"
                                            title="Thema nach oben"
                                            @click.stop="moveWorkspaceNode('topic', topic, 'up')" />
                                        <v-btn
                                            icon="mdi-arrow-down"
                                            size="x-small"
                                            density="comfortable"
                                            variant="text"
                                            color="primary"
                                            :disabled="actionBusy || topicIndex >= (subject.topics.length - 1)"
                                            title="Thema nach unten"
                                            @click.stop="moveWorkspaceNode('topic', topic, 'down')" />
                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            density="comfortable"
                                            variant="text"
                                            color="primary"
                                            :disabled="actionBusy"
                                            title="Thema bearbeiten"
                                            @click.stop="openWorkspaceRenameDialog('topic', topic)" />
                                        <v-btn
                                            v-if="sharedTopicCanDelete(topic)"
                                            icon="mdi-delete-outline"
                                            size="x-small"
                                            density="comfortable"
                                            variant="text"
                                            color="warning"
                                            :disabled="actionBusy"
                                            title="Thema löschen"
                                            @click.stop="openWorkspaceDeleteDialog('topic', topic)" />
                                    </div>
                                </div>
                                <v-btn
                                    v-if="hasPersistedNodeId(topic.id) && !isWorkspaceStructureButtonsVisible()"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    icon="mdi-share-variant-outline"
                                    class="overview-share-btn"
                                    :title="'Teilen'"
                                    :disabled="actionBusy"
                                    @click.stop="handleShareClick({ level: 'topic', id: topic.id, label: workspaceNodeTitle('topic', topic), parentLabel: workspaceNodeTitle('subject', subject) })" />
                                <v-btn
                                    v-if="enableCreateButtons && hasPersistedNodeId(topic.id) && canCreateMaterialInTopic(topic) && !isWorkspaceStructureButtonsVisible()"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    icon="mdi-file-plus-outline"
                                    :title="'Neues Material in Thema anlegen'"
                                    :disabled="actionBusy"
                                    @click="$emit('open-create', {
                                        level: 'topic',
                                        subject: String(workspaceNodeTitle('subject', subject) || '').trim(),
                                        topic: String(workspaceNodeTitle('topic', topic) || '').trim(),
                                        unit: '',
                                    })" />
                                <v-btn
                                    v-if="enableRemoveButtons && hasPersistedNodeId(topic.id) && topic.isLinked"
                                    size="x-small"
                                    color="warning"
                                    variant="text"
                                    density="comfortable"
                                    prepend-icon="mdi-link-off"
                                    :disabled="actionBusy"
                                    title="Link entfernen (ganzes Thema)"
                                    @click="$emit('unlink-linked-topic', topic)">
                                    Link entfernen
                                </v-btn>
                            </div>

                            <ul v-if="isWorkspaceTopicExpanded(topic) && topic.materials.length && !isWorkspaceStructureButtonsVisible()" class="overview-subjects-material-list">
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
                                    <v-btn
                                        v-if="hasPersistedNodeId(material.id)"
                                        size="x-small"
                                        color="primary"
                                        variant="tonal"
                                        icon="mdi-share-variant-outline"
                                        class="overview-share-btn overview-share-btn--material"
                                        :title="'Teilen'"
                                        :disabled="actionBusy"
                                        @click.stop="handleShareClick({
                                            level: 'material',
                                            id: material.id,
                                            label: material.title,
                                            parentLabel: `${workspaceNodeTitle('subject', subject)} / ${workspaceNodeTitle('topic', topic)}`,
                                            kindLabel: material.typeLabel || '',
                                            kindColor: material.typeColor || 'primary',
                                            statusLabel: statusLabelFn(material.status),
                                            statusColor: statusColorFn(material.status),
                                            attachmentsCount: Number(material.attachmentsCount || 0),
                                        })" />
                                    <v-chip
                                        v-if="material.isLinked"
                                        size="x-small"
                                        variant="outlined"
                                        :color="linkedPermissionChipColor(material.linkedPermission)"
                                        class="overview-subjects-material-type">
                                        {{ linkedPermissionLabel(material) }}
                                    </v-chip>
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
                                        v-if="enableRemoveButtons && material.isLinked"
                                        size="x-small"
                                        color="warning"
                                        variant="text"
                                        density="comfortable"
                                        prepend-icon="mdi-link-off"
                                        :disabled="actionBusy"
                                        title="Link entfernen"
                                        @click="$emit('unlink-linked-material', material)">
                                        Link entfernen
                                    </v-btn>
                                    <v-icon
                                        v-if="showShareIndicator('material', material.id)"
                                        size="14"
                                        icon="mdi-share-variant"
                                        :color="shareIndicatorColorFn('material', material.id)"
                                        class="overview-subjects-share-icon" />
                                </li>
                            </ul>

                            <ul v-if="isWorkspaceTopicExpanded(topic) && topic.units.length" class="overview-subjects-list overview-subjects-list--child">
                                <li
                                    v-for="(unit, unitIndex) in topic.units"
                                    :key="`overview-subjects-unit-${unit.id || `${topic.id || topic.name}-${unit.name}`}`"
                                    class="overview-subjects-item">
                                    <div
                                        v-if="enableCreateButtons && isWorkspaceStructureButtonsVisible()"
                                        class="overview-shared-structure-create-row overview-shared-structure-create-row--unit">
                                        <v-btn
                                            prepend-icon="mdi-plus"
                                            size="small"
                                            density="comfortable"
                                            variant="outlined"
                                            color="primary"
                                            class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                            :disabled="actionBusy"
                                            title="Bereich hinzufügen"
                                            aria-label="Bereich hinzufügen"
                                            @click.stop="openWorkspaceCreateUnitDialog(topic, { beforeUnitId: unit?.id })">
                                            Bereich
                                        </v-btn>
                                    </div>
                                    <div class="overview-subjects-node-row">
                                        <div
                                            class="overview-subjects-node overview-subjects-node--unit"
                                            :class="{ 'overview-subjects-node--clickable': unitHasChildren(unit) }"
                                            @click.stop="unitHasChildren(unit) && !actionBusy ? toggleWorkspaceUnitExpanded(unit) : undefined">
                                            <button
                                                v-if="unitHasChildren(unit)"
                                                type="button"
                                                class="overview-subjects-node-toggle"
                                                :aria-expanded="isWorkspaceUnitExpanded(unit) ? 'true' : 'false'"
                                                :aria-label="`Bereich ${workspaceNodeTitle('unit', unit)} ein- oder ausklappen`"
                                                :disabled="actionBusy"
                                                @click.stop="toggleWorkspaceUnitExpanded(unit)">
                                                <v-icon size="13" :icon="isWorkspaceUnitExpanded(unit) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                                            </button>
                                            <v-icon
                                                size="13"
                                                :icon="unit.isLinked ? 'mdi-link-variant' : 'mdi-circle-medium'"
                                                :color="unit.isLinked ? linkedPermissionChipColor(unit.linkedPermission) : undefined"
                                                class="mr-1" />
                                            <span>{{ workspaceNodeTitle('unit', unit) }}</span>
                                            <v-chip
                                                v-if="unit.isLinked"
                                                size="x-small"
                                                variant="outlined"
                                                :color="linkedPermissionChipColor(unit.linkedPermission)"
                                                class="overview-subjects-material-type">
                                                {{ linkedPermissionLabel(unit) }}
                                            </v-chip>
                                            <v-icon
                                                v-if="hasPersistedNodeId(unit.id) && showShareIndicator('unit', unit.id)"
                                                size="14"
                                                icon="mdi-share-variant"
                                                :color="shareIndicatorColorFn('unit', unit.id)"
                                                class="ml-1" />
                                            <div
                                                v-if="enableCreateButtons && isWorkspaceStructureButtonsVisible()"
                                                class="overview-shared-hierarchy-node-inline-actions">
                                                <v-btn
                                                    icon="mdi-arrow-up"
                                                    size="x-small"
                                                    density="comfortable"
                                                    variant="text"
                                                    color="primary"
                                                    :disabled="actionBusy || unitIndex === 0"
                                                    title="Bereich nach oben"
                                                    @click.stop="moveWorkspaceNode('unit', unit, 'up')" />
                                                <v-btn
                                                    icon="mdi-arrow-down"
                                                    size="x-small"
                                                    density="comfortable"
                                                    variant="text"
                                                    color="primary"
                                                    :disabled="actionBusy || unitIndex >= (topic.units.length - 1)"
                                                    title="Bereich nach unten"
                                                    @click.stop="moveWorkspaceNode('unit', unit, 'down')" />
                                                <v-btn
                                                    icon="mdi-pencil"
                                                    size="x-small"
                                                    density="comfortable"
                                                    variant="text"
                                                    color="primary"
                                                    :disabled="actionBusy"
                                                    title="Bereich bearbeiten"
                                                    @click.stop="openWorkspaceRenameDialog('unit', unit)" />
                                                <v-btn
                                                    v-if="sharedUnitCanDelete(unit)"
                                                    icon="mdi-delete-outline"
                                                    size="x-small"
                                                    density="comfortable"
                                                    variant="text"
                                                    color="warning"
                                                    :disabled="actionBusy"
                                                    title="Bereich löschen"
                                                    @click.stop="openWorkspaceDeleteDialog('unit', unit)" />
                                            </div>
                                        </div>
                                        <v-btn
                                            v-if="hasPersistedNodeId(unit.id) && !isWorkspaceStructureButtonsVisible()"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal"
                                            icon="mdi-share-variant-outline"
                                            class="overview-share-btn"
                                            :title="'Teilen'"
                                            :disabled="actionBusy"
                                            @click.stop="handleShareClick({ level: 'unit', id: unit.id, label: workspaceNodeTitle('unit', unit), parentLabel: `${workspaceNodeTitle('subject', subject)} / ${workspaceNodeTitle('topic', topic)}` })" />
                                        <v-btn
                                            v-if="enableCreateButtons && hasPersistedNodeId(unit.id) && canCreateMaterialInUnit(unit) && !isWorkspaceStructureButtonsVisible()"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal"
                                            icon="mdi-file-plus-outline"
                                            :title="'Neues Material in Unterpunkt anlegen'"
                                            :disabled="actionBusy"
                                            @click="$emit('open-create', {
                                                level: 'unit',
                                                subject: String(workspaceNodeTitle('subject', subject) || '').trim(),
                                                topic: String(workspaceNodeTitle('topic', topic) || '').trim(),
                                                unit: String(workspaceNodeTitle('unit', unit) || '').trim(),
                                            })" />
                                        <v-btn
                                            v-if="enableRemoveButtons && hasPersistedNodeId(unit.id) && unit.isLinked"
                                            size="x-small"
                                            color="warning"
                                            variant="text"
                                            density="comfortable"
                                            prepend-icon="mdi-link-off"
                                            :disabled="actionBusy"
                                            title="Link entfernen (ganze Einheit)"
                                            @click="$emit('unlink-linked-unit', unit)">
                                            Link entfernen
                                        </v-btn>
                                    </div>

                                    <ul v-if="isWorkspaceUnitExpanded(unit) && unit.materials.length && !isWorkspaceStructureButtonsVisible()" class="overview-subjects-material-list">
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
                                            <v-btn
                                                v-if="hasPersistedNodeId(material.id)"
                                                size="x-small"
                                                color="primary"
                                                variant="tonal"
                                                icon="mdi-share-variant-outline"
                                                class="overview-share-btn overview-share-btn--material"
                                                :title="'Teilen'"
                                                :disabled="actionBusy"
                                                @click.stop="handleShareClick({
                                                    level: 'material',
                                                    id: material.id,
                                                    label: material.title,
                                                    parentLabel: `${workspaceNodeTitle('subject', subject)} / ${workspaceNodeTitle('topic', topic)} / ${workspaceNodeTitle('unit', unit)}`,
                                                    kindLabel: material.typeLabel || '',
                                                    kindColor: material.typeColor || 'primary',
                                                    statusLabel: statusLabelFn(material.status),
                                                    statusColor: statusColorFn(material.status),
                                                    attachmentsCount: Number(material.attachmentsCount || 0),
                                                })" />
                                            <v-chip
                                                v-if="material.isLinked"
                                                size="x-small"
                                                variant="outlined"
                                                :color="linkedPermissionChipColor(material.linkedPermission)"
                                                class="overview-subjects-material-type">
                                                {{ linkedPermissionLabel(material) }}
                                            </v-chip>
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
                                                v-if="enableRemoveButtons && material.isLinked"
                                                size="x-small"
                                                color="warning"
                                                variant="text"
                                                density="comfortable"
                                                prepend-icon="mdi-link-off"
                                                :disabled="actionBusy"
                                                title="Link entfernen"
                                                @click="$emit('unlink-linked-material', material)">
                                                Link entfernen
                                            </v-btn>
                                            <v-icon
                                                v-if="showShareIndicator('material', material.id)"
                                                size="14"
                                                icon="mdi-share-variant"
                                                :color="shareIndicatorColorFn('material', material.id)"
                                                class="overview-subjects-share-icon" />
                                        </li>
                                    </ul>
                                    <div
                                        v-if="enableCreateButtons && isWorkspaceStructureButtonsVisible()"
                                        class="overview-shared-structure-create-row overview-shared-structure-create-row--topic-bottom">
                                        <v-btn
                                            prepend-icon="mdi-plus"
                                            size="small"
                                            density="comfortable"
                                            variant="outlined"
                                            color="primary"
                                            class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                            :disabled="actionBusy"
                                            title="Bereich hinzufügen"
                                            aria-label="Bereich hinzufügen"
                                            @click.stop="openWorkspaceCreateUnitDialog(topic)">
                                            Bereich
                                        </v-btn>
                                    </div>
                                </li>
                            </ul>
                            <div
                                v-if="enableCreateButtons && isWorkspaceStructureButtonsVisible()"
                                class="overview-shared-structure-create-row overview-shared-structure-create-row--subject-bottom">
                                <v-btn
                                    prepend-icon="mdi-plus"
                                    size="small"
                                    density="comfortable"
                                    variant="outlined"
                                    color="primary"
                                    class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                    :disabled="actionBusy"
                                    title="Thema hinzufügen"
                                    aria-label="Thema hinzufügen"
                                    @click.stop="openWorkspaceCreateTopicDialog(subject)">
                                    Thema
                                </v-btn>
                            </div>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        <div
            v-if="workspaceExpanded && enableCreateButtons && isWorkspaceStructureButtonsVisible()"
            class="overview-shared-structure-create-row overview-shared-structure-create-row--bottom">
            <v-btn
                prepend-icon="mdi-plus"
                size="small"
                density="comfortable"
                variant="outlined"
                color="primary"
                class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                :disabled="actionBusy"
                title="Fach hinzufügen"
                aria-label="Fach hinzufügen"
                @click.stop="openWorkspaceCreateSubjectDialog()">
                Fach
            </v-btn>
        </div>

        <div
            class="overview-subjects-node-row overview-workspace-row overview-shared-row"
            :class="{ 'overview-shared-row--spaced': workspaceExpanded }">
            <button
                type="button"
                class="overview-subjects-node overview-subjects-node--workspace overview-subjects-node--workspace-toggle overview-subjects-node--shared-toggle"
                :disabled="actionBusy"
                :aria-expanded="sharedForMeExpanded ? 'true' : 'false'"
                @click="toggleSharedForMeExpanded">
                <v-icon size="18" :icon="sharedForMeExpanded ? 'mdi-chevron-down' : 'mdi-chevron-right'" class="mr-1" />
                <v-icon size="20" icon="mdi-account-group-outline" class="mr-2" />
                <span>Für mich geteilt</span>
            </button>
        </div>

        <div v-if="sharedForMeExpanded" class="overview-shared-content">
            <div v-if="sharedObjectsForMeLoading" class="overview-shared-state">
                Freigaben werden geladen...
            </div>
            <div v-else-if="sharedObjectsForMeError" class="overview-shared-state overview-shared-state--error">
                {{ sharedObjectsForMeError }}
            </div>
            <div v-else-if="!sharedObjectsForMe.length" class="overview-shared-state">
                Keine Freigaben für dich vorhanden.
            </div>
            <div v-else class="overview-shared-items">
                <div
                    v-for="item in sharedObjectsForMe"
                    :key="`overview-shared-item-${item.ruleId || item.scopeObjectLabel || item.scopePathLabel}`"
                    class="overview-shared-item"
                    :class="{
                        'overview-shared-item--expanded': isSharedItemExpanded(item.ruleId),
                        'overview-shared-item--disabled': sharedItemIsDisabled(item.ruleId),
                    }"
                    :style="sharedItemCardStyle(item.ruleId)">
                    <div class="overview-shared-item-head">
                        <div class="overview-shared-item-title">
                            {{ item.scopeObjectLabel || item.scopeLabel || 'Freigabe' }}
                        </div>
                        <div class="overview-shared-item-head-actions">
                            <v-btn
                                v-if="allowSharedShareButtons && sharedItemHasFullAccess(item) && !isSharedStructureButtonsVisible(item.ruleId)"
                                size="x-small"
                                color="primary"
                                variant="tonal"
                                icon="mdi-share-variant-outline"
                                class="overview-share-btn"
                                :title="'Teilen'"
                                :disabled="actionBusy"
                                @click.stop="handleSharedItemShareClick(item)" />
                            <v-chip
                                v-if="item.permissionLabel"
                                size="x-small"
                                variant="flat"
                                :color="linkedPermissionChipColor(item.permission)">
                                {{ item.permissionLabel }}
                            </v-chip>
                        </div>
                    </div>
                    <div class="overview-shared-item-path">
                        {{ sharedItemTypeLabel(item) }}
                    </div>
                    <div
                        v-if="!['topic', 'material', 'unit'].includes(sharedItemScopeType(item)) && item.scopePathLabel && String(item.scopePathLabel || '').trim() !== sharedItemTypeLabel(item)"
                        class="overview-shared-item-meta">
                        {{ item.scopePathLabel }}
                    </div>
                    <div class="overview-shared-item-meta">
                        Von: {{ item.fromUserLabel || 'Benutzer' }}
                        <span v-if="item.fromSchoolLabel"> · {{ item.fromSchoolLabel }}</span>
                    </div>
                    <div v-if="Number(item.materialsCount || 0) > 0" class="overview-shared-item-meta">
                        {{ Number(item.materialsCount || 0) }} Material{{ Number(item.materialsCount || 0) === 1 ? '' : 'ien' }}
                    </div>
                    <div v-if="sharedItemCanExpand(item)" class="overview-shared-item-actions">
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="primary"
                            :disabled="actionBusy || sharedItemIsDisabled(item.ruleId)"
                            @click="toggleSharedItemExpanded(item.ruleId)">
                            {{ isSharedItemExpanded(item.ruleId) ? 'Schließen' : 'Anzeigen' }}
                        </v-btn>
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="warning"
                            :loading="Number(archivingSharedRuleId || 0) === Number(item.ruleId || 0)"
                            :disabled="actionBusy || sharedItemIsDisabled(item.ruleId)"
                            @click="archiveSharedItem(item.ruleId)">
                            Archivieren
                        </v-btn>
                    </div>
                    <div v-else class="overview-shared-item-actions">
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="warning"
                            :loading="Number(archivingSharedRuleId || 0) === Number(item.ruleId || 0)"
                            :disabled="actionBusy || sharedItemIsDisabled(item.ruleId)"
                            @click="archiveSharedItem(item.ruleId)">
                            Archivieren
                        </v-btn>
                    </div>
                    <div v-if="isSharedItemExpanded(item.ruleId)" class="overview-shared-hierarchy">
                        <div v-if="!sharedItemHierarchy(item).length" class="overview-shared-state">
                            Keine Fachstruktur für diese Freigabe vorhanden.
                        </div>
                        <div
                            v-if="sharedItemCanStructureEdit(item)"
                            class="overview-shared-structure-toggle-row">
                            <v-btn
                                size="small"
                                variant="tonal"
                                color="primary"
                                :disabled="actionBusy"
                                @click="toggleSharedStructureButtons(item.ruleId)">
                                {{ isSharedStructureButtonsVisible(item.ruleId) ? 'Struktur schließen' : 'Struktur ändern' }}
                            </v-btn>
                        </div>
                        <ul v-if="sharedItemHierarchy(item).length" class="overview-shared-hierarchy-list">
                            <li
                                v-for="(subject, subjectIndex) in sharedItemHierarchy(item)"
                                :key="`overview-shared-subject-${item.ruleId}-${subject.id || subject.name}`"
                                class="overview-shared-hierarchy-item">
                                <div v-if="sharedItemSupportsSubjectCreate(item) && isSharedStructureButtonsVisible(item.ruleId)" class="overview-shared-structure-create-row">
                                    <v-btn
                                        prepend-icon="mdi-plus"
                                        size="small"
                                        density="comfortable"
                                        variant="outlined"
                                        color="primary"
                                        class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                        :disabled="actionBusy"
                                        title="Fach hinzufügen"
                                        aria-label="Fach hinzufügen"
                                        @click.stop="openSharedCreateSubjectDialog(item, { beforeSubjectId: subject?.id })">
                                        Fach
                                    </v-btn>
                                </div>
                                <div
                                    class="overview-subjects-node overview-subjects-node--subject overview-shared-hierarchy-node"
                                    :class="{
                                        'overview-shared-hierarchy-node--context': sharedNodeIsContextOnly(item, 'subject'),
                                        'overview-subjects-node--clickable': subjectHasChildren(subject),
                                    }"
                                    @click.stop="subjectHasChildren(subject) && !actionBusy ? toggleSharedSubjectExpanded(item.ruleId, subject) : undefined">
                                    <div class="overview-shared-hierarchy-node-main">
                                        <button
                                            v-if="subjectHasChildren(subject)"
                                            type="button"
                                            class="overview-subjects-node-toggle"
                                            :aria-expanded="isSharedSubjectExpanded(item.ruleId, subject) ? 'true' : 'false'"
                                            :aria-label="`Fach ${sharedNodeTitle(item.ruleId, 'subject', subject)} ein- oder ausklappen`"
                                            :disabled="actionBusy"
                                            @click.stop="toggleSharedSubjectExpanded(item.ruleId, subject)">
                                            <v-icon size="16" :icon="isSharedSubjectExpanded(item.ruleId, subject) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                                        </button>
                                        <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                                        <span>{{ sharedNodeTitle(item.ruleId, 'subject', subject) }}</span>
                                        <v-btn
                                            v-if="!isSharedStructureButtonsVisible(item.ruleId) && canShowSharedInsertButton('subject')"
                                            size="x-small"
                                            color="secondary"
                                            variant="tonal"
                                            prepend-icon="mdi-tray-arrow-down"
                                            class="overview-shared-insert-btn"
                                            :disabled="actionBusy"
                                            @click.stop="emitSharedInsertDraft(item, 'subject', subject)">
                                            Einordnen
                                        </v-btn>
                                        <div
                                            v-if="sharedNodeCanStructureEdit(item, 'subject') && isSharedStructureButtonsVisible(item.ruleId)"
                                            class="overview-shared-hierarchy-node-inline-actions">
                                            <v-btn
                                                icon="mdi-arrow-up"
                                                size="x-small"
                                                density="comfortable"
                                                variant="text"
                                                color="primary"
                                                :disabled="actionBusy || subjectIndex === 0"
                                                title="Fach nach oben"
                                                @click.stop="moveSharedNode(item, 'subject', subject, 'up')" />
                                            <v-btn
                                                icon="mdi-arrow-down"
                                                size="x-small"
                                                density="comfortable"
                                                variant="text"
                                                color="primary"
                                                :disabled="actionBusy || subjectIndex >= (sharedItemHierarchy(item).length - 1)"
                                                title="Fach nach unten"
                                                @click.stop="moveSharedNode(item, 'subject', subject, 'down')" />
                                            <v-btn
                                                icon="mdi-pencil"
                                                size="x-small"
                                                density="comfortable"
                                                variant="text"
                                                color="primary"
                                                :disabled="actionBusy"
                                                title="Fach bearbeiten"
                                                @click.stop="openSharedRenameDialog(item, 'subject', subject)" />
                                            <v-btn
                                                v-if="sharedNodeCanStructureDelete(item, 'subject') && sharedSubjectCanDelete(subject)"
                                                icon="mdi-delete-outline"
                                                size="x-small"
                                                density="comfortable"
                                                variant="text"
                                                color="warning"
                                                :disabled="actionBusy"
                                                title="Fach löschen"
                                                @click.stop="openSharedDeleteDialog(item, 'subject', subject)" />
                                        </div>
                                        <div
                                            v-if="sharedNodeCanAddMaterial(item, 'subject') && !isSharedStructureButtonsVisible(item.ruleId)"
                                            class="overview-shared-hierarchy-node-inline-actions overview-shared-hierarchy-node-inline-actions--material">
                                            <v-btn
                                                v-if="allowSharedShareButtons && hasPersistedNodeId(subject.id)"
                                                icon="mdi-share-variant-outline"
                                                size="x-small"
                                                density="comfortable"
                                                variant="tonal"
                                                color="primary"
                                                class="overview-share-btn"
                                                :disabled="actionBusy"
                                                :title="'Teilen'"
                                                @click.stop="handleShareClick({
                                                    level: 'subject',
                                                    id: subject.id,
                                                    label: sharedNodeTitle(item.ruleId, 'subject', subject),
                                                })" />
                                            <v-btn
                                                icon="mdi-file-plus-outline"
                                                size="x-small"
                                                density="comfortable"
                                                variant="tonal"
                                                color="primary"
                                                class="overview-shared-node-action overview-shared-node-action--material"
                                                :disabled="actionBusy"
                                                title="Neues Material in Fach anlegen"
                                                @click.stop="emitSharedCreate(item, 'subject', subject)" />
                                        </div>
                                    </div>
                                </div>
                                <ul
                                    v-if="isSharedSubjectExpanded(item.ruleId, subject) && Array.isArray(subject.materials) && subject.materials.length && !isSharedStructureButtonsVisible(item.ruleId)"
                                    class="overview-subjects-material-list">
                                    <li
                                        v-for="material in subject.materials"
                                        :key="`overview-shared-subject-material-${item.ruleId}-${material.id || material.title}`"
                                        class="overview-subjects-material-item">
                                        <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                        <button
                                            type="button"
                                            class="overview-subjects-material-link"
                                            :disabled="actionBusy"
                                            @click="openSharedMaterial(item, material)">
                                            {{ material.title }}
                                        </button>
                                        <v-btn
                                            v-if="allowSharedShareButtons && sharedItemHasFullAccess(item) && hasPersistedNodeId(material.id)"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal"
                                            icon="mdi-share-variant-outline"
                                            class="overview-share-btn overview-share-btn--material"
                                            :title="'Teilen'"
                                            :disabled="actionBusy"
                                            @click.stop="handleShareClick({
                                                level: 'material',
                                                id: material.id,
                                                label: material.title,
                                                parentLabel: sharedNodeTitle(item.ruleId, 'subject', subject),
                                                kindLabel: material.typeLabel || '',
                                                kindColor: material.typeColor || 'primary',
                                                statusLabel: material.statusLabel || statusLabelFn(material.status),
                                                statusColor: material.statusColor || statusColorFn(material.status),
                                                attachmentsCount: Number(material.attachmentsCount || 0),
                                            })" />
                                        <v-chip
                                            v-if="material.typeLabel"
                                            size="x-small"
                                            variant="outlined"
                                            :color="material.typeColor || 'primary'"
                                            class="overview-subjects-material-type">
                                            {{ material.typeLabel }}
                                        </v-chip>
                                        <button
                                            v-if="Number(material.attachmentsCount || 0) > 0"
                                            type="button"
                                            class="overview-subjects-material-count overview-subjects-material-count--button"
                                            :disabled="actionBusy"
                                            title="Anhänge anzeigen"
                                            @click="openSharedAttachments(item, material)">
                                            <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                            {{ Number(material.attachmentsCount || 0) }}
                                        </button>
                                        <v-chip
                                            size="x-small"
                                            variant="tonal"
                                            :color="material.statusColor || statusColorFn(material.status)"
                                            class="overview-subjects-material-status">
                                            {{ material.statusLabel || statusLabelFn(material.status) }}
                                        </v-chip>
                                        <v-btn
                                            v-if="canShowSharedInsertButton('material')"
                                            size="x-small"
                                            color="secondary"
                                            variant="tonal"
                                            prepend-icon="mdi-tray-arrow-down"
                                            class="overview-shared-insert-btn"
                                            :disabled="actionBusy"
                                            @click.stop="emitSharedMaterialInsertDraft(item, material, { subject })">
                                            Einordnen
                                        </v-btn>
                                    </li>
                                </ul>

                                <ul v-if="isSharedSubjectExpanded(item.ruleId, subject) && subject.topics.length" class="overview-subjects-list overview-subjects-list--child">
                                    <li
                                        v-for="(topic, topicIndex) in subject.topics"
                                        :key="`overview-shared-topic-${item.ruleId}-${topic.id || topic.name}`"
                                        class="overview-subjects-item overview-subjects-topic-group">
                                        <div v-if="sharedItemSupportsTopicCreate(item) && isSharedStructureButtonsVisible(item.ruleId)" class="overview-shared-structure-create-row overview-shared-structure-create-row--topic">
                                            <v-btn
                                                prepend-icon="mdi-plus"
                                                size="small"
                                                density="comfortable"
                                                variant="outlined"
                                                color="primary"
                                                class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                                :disabled="actionBusy"
                                                title="Thema hinzufügen"
                                                aria-label="Thema hinzufügen"
                                                @click.stop="openSharedCreateTopicDialog(item, subject, { beforeTopicId: topic?.id })">
                                                Thema
                                            </v-btn>
                                        </div>
                                        <div
                                            class="overview-subjects-node overview-subjects-node--topic overview-shared-hierarchy-node"
                                            :class="{
                                                'overview-shared-hierarchy-node--context': sharedNodeIsContextOnly(item, 'topic'),
                                                'overview-subjects-node--clickable': topicHasChildren(topic),
                                            }"
                                            @click.stop="topicHasChildren(topic) && !actionBusy ? toggleSharedTopicExpanded(item.ruleId, topic) : undefined">
                                            <div class="overview-shared-hierarchy-node-main">
                                                <button
                                                    v-if="topicHasChildren(topic)"
                                                    type="button"
                                                    class="overview-subjects-node-toggle"
                                                    :aria-expanded="isSharedTopicExpanded(item.ruleId, topic) ? 'true' : 'false'"
                                                    :aria-label="`Thema ${sharedNodeTitle(item.ruleId, 'topic', topic)} ein- oder ausklappen`"
                                                    :disabled="actionBusy"
                                                    @click.stop="toggleSharedTopicExpanded(item.ruleId, topic)">
                                                    <v-icon size="14" :icon="isSharedTopicExpanded(item.ruleId, topic) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                                                </button>
                                                <v-icon size="14" icon="mdi-book-open-page-variant-outline" class="mr-2" />
                                                <span>{{ sharedNodeTitle(item.ruleId, 'topic', topic) }}</span>
                                                <v-btn
                                                    v-if="!isSharedStructureButtonsVisible(item.ruleId) && canShowSharedInsertButton('topic')"
                                                    size="x-small"
                                                    color="secondary"
                                                    variant="tonal"
                                                    prepend-icon="mdi-tray-arrow-down"
                                                    class="overview-shared-insert-btn"
                                                    :disabled="actionBusy"
                                                    @click.stop="emitSharedInsertDraft(item, 'topic', topic, { subject })">
                                                    Einordnen
                                                </v-btn>
                                                <div
                                                    v-if="sharedNodeCanStructureEdit(item, 'topic') && isSharedStructureButtonsVisible(item.ruleId)"
                                                    class="overview-shared-hierarchy-node-inline-actions">
                                                    <v-btn
                                                        icon="mdi-arrow-up"
                                                        size="x-small"
                                                        density="comfortable"
                                                        variant="text"
                                                        color="primary"
                                                        :disabled="actionBusy || topicIndex === 0"
                                                        title="Thema nach oben"
                                                        @click.stop="moveSharedNode(item, 'topic', topic, 'up')" />
                                                    <v-btn
                                                        icon="mdi-arrow-down"
                                                        size="x-small"
                                                        density="comfortable"
                                                        variant="text"
                                                        color="primary"
                                                        :disabled="actionBusy || topicIndex >= (subject.topics.length - 1)"
                                                        title="Thema nach unten"
                                                        @click.stop="moveSharedNode(item, 'topic', topic, 'down')" />
                                                    <v-btn
                                                        icon="mdi-pencil"
                                                        size="x-small"
                                                        density="comfortable"
                                                        variant="text"
                                                        color="primary"
                                                        :disabled="actionBusy"
                                                        title="Thema bearbeiten"
                                                        @click.stop="openSharedRenameDialog(item, 'topic', topic)" />
                                                    <v-btn
                                                        v-if="sharedNodeCanStructureDelete(item, 'topic') && sharedTopicCanDelete(topic)"
                                                        icon="mdi-delete-outline"
                                                        size="x-small"
                                                        density="comfortable"
                                                        variant="text"
                                                        color="warning"
                                                        :disabled="actionBusy"
                                                        title="Thema löschen"
                                                        @click.stop="openSharedDeleteDialog(item, 'topic', topic)" />
                                                </div>
                                                <div
                                                    v-if="sharedNodeCanAddMaterial(item, 'topic') && !isSharedStructureButtonsVisible(item.ruleId)"
                                                    class="overview-shared-hierarchy-node-inline-actions overview-shared-hierarchy-node-inline-actions--material">
                                                    <v-btn
                                                        v-if="allowSharedShareButtons && hasPersistedNodeId(topic.id)"
                                                        icon="mdi-share-variant-outline"
                                                        size="x-small"
                                                        density="comfortable"
                                                        variant="tonal"
                                                        color="primary"
                                                        class="overview-share-btn"
                                                        :disabled="actionBusy"
                                                        :title="'Teilen'"
                                                        @click.stop="handleShareClick({
                                                            level: 'topic',
                                                            id: topic.id,
                                                            label: sharedNodeTitle(item.ruleId, 'topic', topic),
                                                            parentLabel: sharedNodeTitle(item.ruleId, 'subject', subject),
                                                        })" />
                                                    <v-btn
                                                        icon="mdi-file-plus-outline"
                                                        size="x-small"
                                                        density="comfortable"
                                                        variant="tonal"
                                                        color="primary"
                                                        class="overview-shared-node-action overview-shared-node-action--material"
                                                        :disabled="actionBusy"
                                                        title="Neues Material in Thema anlegen"
                                                        @click.stop="emitSharedCreate(item, 'topic', topic, { subject })" />
                                                </div>
                                            </div>
                                        </div>
                                        <ul
                                            v-if="isSharedTopicExpanded(item.ruleId, topic) && Array.isArray(topic.materials) && topic.materials.length && !isSharedStructureButtonsVisible(item.ruleId)"
                                            class="overview-subjects-material-list">
                                            <li
                                                v-for="material in topic.materials"
                                                :key="`overview-shared-topic-material-${item.ruleId}-${material.id || material.title}`"
                                                class="overview-subjects-material-item">
                                                <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                <button
                                                    type="button"
                                                    class="overview-subjects-material-link"
                                                    :disabled="actionBusy"
                                                    @click="openSharedMaterial(item, material)">
                                                    {{ material.title }}
                                                </button>
                                                <v-btn
                                                    v-if="allowSharedShareButtons && sharedItemHasFullAccess(item) && hasPersistedNodeId(material.id)"
                                                    size="x-small"
                                                    color="primary"
                                                    variant="tonal"
                                                    icon="mdi-share-variant-outline"
                                                    class="overview-share-btn overview-share-btn--material"
                                                    :title="'Teilen'"
                                                    :disabled="actionBusy"
                                                    @click.stop="handleShareClick({
                                                        level: 'material',
                                                        id: material.id,
                                                        label: material.title,
                                                        parentLabel: `${sharedNodeTitle(item.ruleId, 'subject', subject)} / ${sharedNodeTitle(item.ruleId, 'topic', topic)}`,
                                                        kindLabel: material.typeLabel || '',
                                                        kindColor: material.typeColor || 'primary',
                                                        statusLabel: material.statusLabel || statusLabelFn(material.status),
                                                        statusColor: material.statusColor || statusColorFn(material.status),
                                                        attachmentsCount: Number(material.attachmentsCount || 0),
                                                    })" />
                                                <v-chip
                                                    v-if="material.typeLabel"
                                                    size="x-small"
                                                    variant="outlined"
                                                    :color="material.typeColor || 'primary'"
                                                    class="overview-subjects-material-type">
                                                    {{ material.typeLabel }}
                                                </v-chip>
                                                <button
                                                    v-if="Number(material.attachmentsCount || 0) > 0"
                                                    type="button"
                                                    class="overview-subjects-material-count overview-subjects-material-count--button"
                                                    :disabled="actionBusy"
                                                    title="Anhänge anzeigen"
                                                    @click="openSharedAttachments(item, material)">
                                                    <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                    {{ Number(material.attachmentsCount || 0) }}
                                                </button>
                                                <v-chip
                                                    size="x-small"
                                                    variant="tonal"
                                                    :color="material.statusColor || statusColorFn(material.status)"
                                                    class="overview-subjects-material-status">
                                                    {{ material.statusLabel || statusLabelFn(material.status) }}
                                                </v-chip>
                                                <v-btn
                                                    v-if="canShowSharedInsertButton('material')"
                                                    size="x-small"
                                                    color="secondary"
                                                    variant="tonal"
                                                    prepend-icon="mdi-tray-arrow-down"
                                                    class="overview-shared-insert-btn"
                                                    :disabled="actionBusy"
                                                    @click.stop="emitSharedMaterialInsertDraft(item, material, { subject, topic })">
                                                    Einordnen
                                                </v-btn>
                                            </li>
                                        </ul>

                                        <ul v-if="isSharedTopicExpanded(item.ruleId, topic) && topic.units.length" class="overview-subjects-list overview-subjects-list--child">
                                            <li
                                                v-for="(unit, unitIndex) in topic.units"
                                                :key="`overview-shared-unit-${item.ruleId}-${unit.id || unit.name}`"
                                                class="overview-subjects-item">
                                                <div
                                                    v-if="sharedItemSupportsUnitCreate(item) && isSharedStructureButtonsVisible(item.ruleId)"
                                                    class="overview-shared-structure-create-row overview-shared-structure-create-row--unit">
                                                    <v-btn
                                                        prepend-icon="mdi-plus"
                                                        size="small"
                                                        density="comfortable"
                                                        variant="outlined"
                                                        color="primary"
                                                        class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                                        :disabled="actionBusy"
                                                        title="Bereich hinzufügen"
                                                        aria-label="Bereich hinzufügen"
                                                        @click.stop="openSharedCreateUnitDialog(item, topic, { beforeUnitId: unit?.id })">
                                                        Bereich
                                                    </v-btn>
                                                </div>
                                                <div
                                                    class="overview-subjects-node overview-subjects-node--unit overview-shared-hierarchy-node"
                                                    :class="{
                                                        'overview-shared-hierarchy-node--context': sharedNodeIsContextOnly(item, 'unit'),
                                                        'overview-subjects-node--clickable': unitHasChildren(unit),
                                                    }"
                                                    @click.stop="unitHasChildren(unit) && !actionBusy ? toggleSharedUnitExpanded(item.ruleId, unit) : undefined">
                                                    <div class="overview-shared-hierarchy-node-main">
                                                        <button
                                                            v-if="unitHasChildren(unit)"
                                                            type="button"
                                                            class="overview-subjects-node-toggle"
                                                            :aria-expanded="isSharedUnitExpanded(item.ruleId, unit) ? 'true' : 'false'"
                                                            :aria-label="`Bereich ${sharedNodeTitle(item.ruleId, 'unit', unit)} ein- oder ausklappen`"
                                                            :disabled="actionBusy"
                                                            @click.stop="toggleSharedUnitExpanded(item.ruleId, unit)">
                                                            <v-icon size="13" :icon="isSharedUnitExpanded(item.ruleId, unit) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                                                        </button>
                                                        <v-icon size="13" icon="mdi-bookmark-outline" class="mr-2" />
                                                        <span>{{ sharedNodeTitle(item.ruleId, 'unit', unit) }}</span>
                                                        <v-btn
                                                            v-if="!isSharedStructureButtonsVisible(item.ruleId) && canShowSharedInsertButton('unit')"
                                                            size="x-small"
                                                            color="secondary"
                                                            variant="tonal"
                                                            prepend-icon="mdi-tray-arrow-down"
                                                            class="overview-shared-insert-btn"
                                                            :disabled="actionBusy"
                                                            @click.stop="emitSharedInsertDraft(item, 'unit', unit, { subject, topic })">
                                                            Einordnen
                                                        </v-btn>
                                                        <div
                                                            v-if="sharedNodeCanStructureEdit(item, 'unit') && isSharedStructureButtonsVisible(item.ruleId)"
                                                            class="overview-shared-hierarchy-node-inline-actions">
                                                            <v-btn
                                                                icon="mdi-arrow-up"
                                                                size="x-small"
                                                                density="comfortable"
                                                                variant="text"
                                                                color="primary"
                                                                :disabled="actionBusy || unitIndex === 0"
                                                                title="Bereich nach oben"
                                                                @click.stop="moveSharedNode(item, 'unit', unit, 'up')" />
                                                            <v-btn
                                                                icon="mdi-arrow-down"
                                                                size="x-small"
                                                                density="comfortable"
                                                                variant="text"
                                                                color="primary"
                                                                :disabled="actionBusy || unitIndex >= (topic.units.length - 1)"
                                                                title="Bereich nach unten"
                                                                @click.stop="moveSharedNode(item, 'unit', unit, 'down')" />
                                                            <v-btn
                                                                icon="mdi-pencil"
                                                                size="x-small"
                                                                density="comfortable"
                                                                variant="text"
                                                                color="primary"
                                                                :disabled="actionBusy"
                                                                title="Bereich bearbeiten"
                                                                @click.stop="openSharedRenameDialog(item, 'unit', unit)" />
                                                            <v-btn
                                                                v-if="sharedNodeCanStructureDelete(item, 'unit') && sharedUnitCanDelete(unit)"
                                                                icon="mdi-delete-outline"
                                                                size="x-small"
                                                                density="comfortable"
                                                                variant="text"
                                                                color="warning"
                                                                :disabled="actionBusy"
                                                                title="Bereich löschen"
                                                                @click.stop="openSharedDeleteDialog(item, 'unit', unit)" />
                                                        </div>
                                                        <div
                                                            v-if="sharedNodeCanAddMaterial(item, 'unit') && !isSharedStructureButtonsVisible(item.ruleId)"
                                                            class="overview-shared-hierarchy-node-inline-actions overview-shared-hierarchy-node-inline-actions--material">
                                                            <v-btn
                                                                v-if="allowSharedShareButtons && hasPersistedNodeId(unit.id)"
                                                                icon="mdi-share-variant-outline"
                                                                size="x-small"
                                                                density="comfortable"
                                                                variant="tonal"
                                                                color="primary"
                                                                class="overview-share-btn"
                                                                :disabled="actionBusy"
                                                                :title="'Teilen'"
                                                                @click.stop="handleShareClick({
                                                                    level: 'unit',
                                                                    id: unit.id,
                                                                    label: sharedNodeTitle(item.ruleId, 'unit', unit),
                                                                    parentLabel: `${sharedNodeTitle(item.ruleId, 'subject', subject)} / ${sharedNodeTitle(item.ruleId, 'topic', topic)}`,
                                                                })" />
                                                            <v-btn
                                                                icon="mdi-file-plus-outline"
                                                                size="x-small"
                                                                density="comfortable"
                                                                variant="tonal"
                                                                color="primary"
                                                                class="overview-shared-node-action overview-shared-node-action--material"
                                                                :disabled="actionBusy"
                                                                title="Neues Material in Unterpunkt anlegen"
                                                                @click.stop="emitSharedCreate(item, 'unit', unit, { subject, topic })" />
                                                        </div>
                                                    </div>
                                                </div>

                                                <ul
                                                    v-if="isSharedUnitExpanded(item.ruleId, unit) && unit.materials.length && !isSharedStructureButtonsVisible(item.ruleId)"
                                                    class="overview-subjects-material-list">
                                                    <li
                                                        v-for="material in unit.materials"
                                                        :key="`overview-shared-material-${item.ruleId}-${material.id || material.title}`"
                                                        class="overview-subjects-material-item">
                                                        <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                        <button
                                                            type="button"
                                                            class="overview-subjects-material-link"
                                                            :disabled="actionBusy"
                                                            @click="openSharedMaterial(item, material)">
                                                            {{ material.title }}
                                                        </button>
                                                        <v-btn
                                                            v-if="allowSharedShareButtons && sharedItemHasFullAccess(item) && hasPersistedNodeId(material.id)"
                                                            size="x-small"
                                                            color="primary"
                                                            variant="tonal"
                                                            icon="mdi-share-variant-outline"
                                                            class="overview-share-btn overview-share-btn--material"
                                                            :title="'Teilen'"
                                                            :disabled="actionBusy"
                                                            @click.stop="handleShareClick({
                                                                level: 'material',
                                                                id: material.id,
                                                                label: material.title,
                                                                parentLabel: `${sharedNodeTitle(item.ruleId, 'subject', subject)} / ${sharedNodeTitle(item.ruleId, 'topic', topic)} / ${sharedNodeTitle(item.ruleId, 'unit', unit)}`,
                                                                kindLabel: material.typeLabel || '',
                                                                kindColor: material.typeColor || 'primary',
                                                                statusLabel: material.statusLabel || statusLabelFn(material.status),
                                                                statusColor: material.statusColor || statusColorFn(material.status),
                                                                attachmentsCount: Number(material.attachmentsCount || 0),
                                                            })" />
                                                        <v-chip
                                                            v-if="material.typeLabel"
                                                            size="x-small"
                                                            variant="outlined"
                                                            :color="material.typeColor || 'primary'"
                                                            class="overview-subjects-material-type">
                                                            {{ material.typeLabel }}
                                                        </v-chip>
                                                        <button
                                                            v-if="Number(material.attachmentsCount || 0) > 0"
                                                            type="button"
                                                            class="overview-subjects-material-count overview-subjects-material-count--button"
                                                            :disabled="actionBusy"
                                                            title="Anhänge anzeigen"
                                                            @click="openSharedAttachments(item, material)">
                                                            <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                            {{ Number(material.attachmentsCount || 0) }}
                                                        </button>
                                                        <v-chip
                                                            size="x-small"
                                                            variant="tonal"
                                                            :color="material.statusColor || statusColorFn(material.status)"
                                                            class="overview-subjects-material-status">
                                                            {{ material.statusLabel || statusLabelFn(material.status) }}
                                                        </v-chip>
                                                        <v-btn
                                                            v-if="canShowSharedInsertButton('material')"
                                                            size="x-small"
                                                            color="secondary"
                                                            variant="tonal"
                                                            prepend-icon="mdi-tray-arrow-down"
                                                            class="overview-shared-insert-btn"
                                                            :disabled="actionBusy"
                                                            @click.stop="emitSharedMaterialInsertDraft(item, material, { subject, topic, unit })">
                                                            Einordnen
                                                        </v-btn>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                        <div
                                            v-if="sharedItemSupportsUnitCreate(item) && isSharedStructureButtonsVisible(item.ruleId)"
                                            class="overview-shared-structure-create-row overview-shared-structure-create-row--topic-bottom">
                                            <v-btn
                                                prepend-icon="mdi-plus"
                                                size="small"
                                                density="comfortable"
                                                variant="outlined"
                                                color="primary"
                                                class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                                :disabled="actionBusy"
                                                title="Bereich hinzufügen"
                                                aria-label="Bereich hinzufügen"
                                                @click.stop="openSharedCreateUnitDialog(item, topic)">
                                                Bereich
                                            </v-btn>
                                        </div>
                                    </li>
                                </ul>
                                <div v-if="isSharedSubjectExpanded(item.ruleId, subject) && sharedItemSupportsTopicCreate(item) && isSharedStructureButtonsVisible(item.ruleId)" class="overview-shared-structure-create-row overview-shared-structure-create-row--subject-bottom">
                                    <v-btn
                                        prepend-icon="mdi-plus"
                                        size="small"
                                        density="comfortable"
                                        variant="outlined"
                                        color="primary"
                                        class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                        :disabled="actionBusy"
                                        title="Thema hinzufügen"
                                        aria-label="Thema hinzufügen"
                                        @click.stop="openSharedCreateTopicDialog(item, subject)">
                                        Thema
                                    </v-btn>
                                </div>
                            </li>
                        </ul>
                        <div
                            v-if="sharedItemSupportsSubjectCreate(item) && isSharedStructureButtonsVisible(item.ruleId)"
                            class="overview-shared-structure-create-row overview-shared-structure-create-row--bottom">
                            <v-btn
                                prepend-icon="mdi-plus"
                                size="small"
                                density="comfortable"
                                variant="outlined"
                                color="primary"
                                class="overview-shared-structure-create-button overview-shared-structure-create-button--subject"
                                :disabled="actionBusy"
                                title="Fach hinzufügen"
                                aria-label="Fach hinzufügen"
                                @click.stop="openSharedCreateSubjectDialog(item)">
                                Fach
                            </v-btn>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="overview-subjects-node-row overview-workspace-row overview-shared-row overview-shared-row--archive">
            <button
                type="button"
                class="overview-subjects-node overview-subjects-node--workspace overview-subjects-node--workspace-toggle overview-subjects-node--shared-toggle"
                :disabled="actionBusy"
                :aria-expanded="sharedForMeArchiveExpanded ? 'true' : 'false'"
                @click="toggleSharedForMeArchiveExpanded">
                <v-icon size="18" :icon="sharedForMeArchiveExpanded ? 'mdi-chevron-down' : 'mdi-chevron-right'" class="mr-1" />
                <v-icon size="20" icon="mdi-archive-outline" class="mr-2" />
                <span>Für mich geteilt - Archiv</span>
            </button>
        </div>

        <div v-if="sharedForMeArchiveExpanded" class="overview-shared-content overview-shared-content--archive">
            <div v-if="sharedObjectsForMeLoading" class="overview-shared-state">
                Freigaben werden geladen...
            </div>
            <div v-else-if="sharedObjectsForMeError" class="overview-shared-state overview-shared-state--error">
                {{ sharedObjectsForMeError }}
            </div>
            <div v-else-if="!archivedSharedObjectsForMe.length" class="overview-shared-state">
                Keine archivierten Freigaben vorhanden.
            </div>
            <div v-else class="overview-shared-items">
                <div
                    v-for="item in archivedSharedObjectsForMe"
                    :key="`overview-shared-archived-item-${item.ruleId || item.scopeObjectLabel || item.scopePathLabel}`"
                    class="overview-shared-item"
                    :class="{ 'overview-shared-item--expanded': isArchivedItemExpanded(item.ruleId) }"
                    :style="archivedItemCardStyle(item.ruleId)">
                    <div class="overview-shared-item-head">
                        <div class="overview-shared-item-title">
                            {{ item.scopeObjectLabel || item.scopeLabel || 'Freigabe' }}
                        </div>
                        <div class="overview-shared-item-head-actions">
                            <v-chip
                                v-if="item.permissionLabel"
                                size="x-small"
                                variant="flat"
                                :color="linkedPermissionChipColor(item.permission)">
                                {{ item.permissionLabel }}
                            </v-chip>
                        </div>
                    </div>
                    <div class="overview-shared-item-path">
                        {{ sharedItemTypeLabel(item) }}
                    </div>
                    <div class="overview-shared-item-meta">
                        Von: {{ item.fromUserLabel || 'Benutzer' }}
                        <span v-if="item.fromSchoolLabel"> · {{ item.fromSchoolLabel }}</span>
                    </div>
                    <div v-if="Number(item.materialsCount || 0) > 0" class="overview-shared-item-meta">
                        {{ Number(item.materialsCount || 0) }} Material{{ Number(item.materialsCount || 0) === 1 ? '' : 'ien' }}
                    </div>
                    <div class="overview-shared-item-actions">
                        <v-btn
                            v-if="sharedItemCanExpand(item)"
                            size="small"
                            variant="tonal"
                            color="primary"
                            :disabled="actionBusy"
                            @click="toggleArchivedItemExpanded(item.ruleId)">
                            {{ isArchivedItemExpanded(item.ruleId) ? 'Schließen' : 'Anzeigen' }}
                        </v-btn>
                        <v-btn
                            v-if="!isArchivedItemExpanded(item.ruleId)"
                            size="small"
                            variant="tonal"
                            color="success"
                            :loading="Number(unarchivingSharedRuleId || 0) === Number(item.ruleId || 0)"
                            :disabled="actionBusy"
                            @click="activateSharedItem(item.ruleId)">
                            Aktivieren
                        </v-btn>
                    </div>
                    <div v-if="isArchivedItemExpanded(item.ruleId)" class="overview-shared-hierarchy overview-shared-hierarchy--readonly">
                        <ul v-if="sharedItemHierarchy(item).length" class="overview-shared-hierarchy-list">
                            <li
                                v-for="subject in sharedItemHierarchy(item)"
                                :key="`overview-archived-subject-${item.ruleId}-${subject.id || subject.name}`"
                                class="overview-shared-hierarchy-item">
                                <div
                                    class="overview-subjects-node overview-subjects-node--subject overview-shared-hierarchy-node overview-shared-hierarchy-node--readonly"
                                    :class="{ 'overview-subjects-node--clickable': subjectHasChildren(subject) }"
                                    @click.stop="subjectHasChildren(subject) && !actionBusy ? toggleArchivedSubjectExpanded(item.ruleId, subject) : undefined">
                                    <div class="overview-shared-hierarchy-node-main">
                                        <button
                                            v-if="subjectHasChildren(subject)"
                                            type="button"
                                            class="overview-subjects-node-toggle"
                                            :aria-expanded="isArchivedSubjectExpanded(item.ruleId, subject) ? 'true' : 'false'"
                                            :aria-label="`Fach ${sharedNodeTitle(item.ruleId, 'subject', subject)} ein- oder ausklappen`"
                                            :disabled="actionBusy"
                                            @click.stop="toggleArchivedSubjectExpanded(item.ruleId, subject)">
                                            <v-icon size="16" :icon="isArchivedSubjectExpanded(item.ruleId, subject) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                                        </button>
                                        <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                                        <span>{{ sharedNodeTitle(item.ruleId, 'subject', subject) }}</span>
                                    </div>
                                </div>
                                <ul v-if="isArchivedSubjectExpanded(item.ruleId, subject) && Array.isArray(subject.materials) && subject.materials.length" class="overview-subjects-material-list">
                                    <li
                                        v-for="material in subject.materials"
                                        :key="`overview-archived-subject-material-${item.ruleId}-${material.id || material.title}`"
                                        class="overview-subjects-material-item">
                                        <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                        <span class="overview-subjects-material-link overview-subjects-material-link--readonly">
                                            {{ material.title }}
                                        </span>
                                    </li>
                                </ul>
                                <ul v-if="isArchivedSubjectExpanded(item.ruleId, subject) && subject.topics.length" class="overview-subjects-list overview-subjects-list--child">
                                    <li
                                        v-for="topic in subject.topics"
                                        :key="`overview-archived-topic-${item.ruleId}-${topic.id || topic.name}`"
                                        class="overview-subjects-item overview-subjects-topic-group">
                                        <div
                                            class="overview-subjects-node overview-subjects-node--topic overview-shared-hierarchy-node overview-shared-hierarchy-node--readonly"
                                            :class="{ 'overview-subjects-node--clickable': topicHasChildren(topic) }"
                                            @click.stop="topicHasChildren(topic) && !actionBusy ? toggleArchivedTopicExpanded(item.ruleId, topic) : undefined">
                                            <div class="overview-shared-hierarchy-node-main">
                                                <button
                                                    v-if="topicHasChildren(topic)"
                                                    type="button"
                                                    class="overview-subjects-node-toggle"
                                                    :aria-expanded="isArchivedTopicExpanded(item.ruleId, topic) ? 'true' : 'false'"
                                                    :aria-label="`Thema ${sharedNodeTitle(item.ruleId, 'topic', topic)} ein- oder ausklappen`"
                                                    :disabled="actionBusy"
                                                    @click.stop="toggleArchivedTopicExpanded(item.ruleId, topic)">
                                                    <v-icon size="14" :icon="isArchivedTopicExpanded(item.ruleId, topic) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                                                </button>
                                                <v-icon size="14" icon="mdi-book-open-page-variant-outline" class="mr-2" />
                                                <span>{{ sharedNodeTitle(item.ruleId, 'topic', topic) }}</span>
                                            </div>
                                        </div>
                                        <ul v-if="isArchivedTopicExpanded(item.ruleId, topic) && Array.isArray(topic.materials) && topic.materials.length" class="overview-subjects-material-list">
                                            <li
                                                v-for="material in topic.materials"
                                                :key="`overview-archived-topic-material-${item.ruleId}-${material.id || material.title}`"
                                                class="overview-subjects-material-item">
                                                <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                <span class="overview-subjects-material-link overview-subjects-material-link--readonly">
                                                    {{ material.title }}
                                                </span>
                                            </li>
                                        </ul>
                                        <ul v-if="isArchivedTopicExpanded(item.ruleId, topic) && topic.units.length" class="overview-subjects-list overview-subjects-list--child">
                                            <li
                                                v-for="unit in topic.units"
                                                :key="`overview-archived-unit-${item.ruleId}-${unit.id || unit.name}`"
                                                class="overview-subjects-item">
                                                <div
                                                    class="overview-subjects-node overview-subjects-node--unit overview-shared-hierarchy-node overview-shared-hierarchy-node--readonly"
                                                    :class="{ 'overview-subjects-node--clickable': unitHasChildren(unit) }"
                                                    @click.stop="unitHasChildren(unit) && !actionBusy ? toggleArchivedUnitExpanded(item.ruleId, unit) : undefined">
                                                    <div class="overview-shared-hierarchy-node-main">
                                                        <button
                                                            v-if="unitHasChildren(unit)"
                                                            type="button"
                                                            class="overview-subjects-node-toggle"
                                                            :aria-expanded="isArchivedUnitExpanded(item.ruleId, unit) ? 'true' : 'false'"
                                                            :aria-label="`Bereich ${sharedNodeTitle(item.ruleId, 'unit', unit)} ein- oder ausklappen`"
                                                            :disabled="actionBusy"
                                                            @click.stop="toggleArchivedUnitExpanded(item.ruleId, unit)">
                                                            <v-icon size="13" :icon="isArchivedUnitExpanded(item.ruleId, unit) ? 'mdi-chevron-down' : 'mdi-chevron-right'" />
                                                        </button>
                                                        <v-icon size="13" icon="mdi-bookmark-outline" class="mr-2" />
                                                        <span>{{ sharedNodeTitle(item.ruleId, 'unit', unit) }}</span>
                                                    </div>
                                                </div>
                                                <ul v-if="isArchivedUnitExpanded(item.ruleId, unit) && unit.materials.length" class="overview-subjects-material-list">
                                                    <li
                                                        v-for="material in unit.materials"
                                                        :key="`overview-archived-unit-material-${item.ruleId}-${material.id || material.title}`"
                                                        class="overview-subjects-material-item">
                                                        <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                        <span class="overview-subjects-material-link overview-subjects-material-link--readonly">
                                                            {{ material.title }}
                                                        </span>
                                                    </li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <v-dialog v-model="sharedCreateSubjectDialog.open" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">Fach hinzufügen</v-card-title>
                <v-card-text>
                    <div class="text-body-2 text-medium-emphasis mb-3">Neues Fach im Original-Workspace anlegen</div>
                    <v-text-field
                        :model-value="sharedCreateSubjectDialog.title"
                        label="Titel"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        autofocus
                        :disabled="actionBusy || sharedCreateSubjectDialogSaving"
                        :rules="[required(), maxLength(255)]"
                        :error-messages="sharedCreateSubjectDialogError ? [sharedCreateSubjectDialogError] : []"
                        @update:modelValue="handleSharedCreateSubjectTitleInput"
                        @blur="validateSharedCreateSubjectDialog"
                        @keydown.enter.prevent="submitSharedCreateSubjectDialog" />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" :disabled="actionBusy || sharedCreateSubjectDialogSaving" @click="closeSharedCreateSubjectDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="sharedCreateSubjectDialogSaving" :disabled="actionBusy" @click="submitSharedCreateSubjectDialog">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="sharedCreateTopicDialog.open" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">Thema hinzufügen</v-card-title>
                <v-card-text>
                    <div class="text-body-2 text-medium-emphasis mb-3">Neues Thema im Original-Workspace anlegen</div>
                    <v-text-field
                        :model-value="sharedCreateTopicDialog.title"
                        label="Titel"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        autofocus
                        :disabled="actionBusy || sharedCreateTopicDialogSaving"
                        :rules="[required(), maxLength(255)]"
                        :error-messages="sharedCreateTopicDialogError ? [sharedCreateTopicDialogError] : []"
                        @update:modelValue="handleSharedCreateTopicTitleInput"
                        @blur="validateSharedCreateTopicDialog"
                        @keydown.enter.prevent="submitSharedCreateTopicDialog" />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" :disabled="actionBusy || sharedCreateTopicDialogSaving" @click="closeSharedCreateTopicDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="sharedCreateTopicDialogSaving" :disabled="actionBusy" @click="submitSharedCreateTopicDialog">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="sharedCreateUnitDialog.open" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">Bereich hinzufügen</v-card-title>
                <v-card-text>
                    <div class="text-body-2 text-medium-emphasis mb-3">Neuen Bereich im Original-Workspace anlegen</div>
                    <v-text-field
                        :model-value="sharedCreateUnitDialog.title"
                        label="Titel"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        autofocus
                        :disabled="actionBusy || sharedCreateUnitDialogSaving"
                        :rules="[required(), maxLength(255)]"
                        :error-messages="sharedCreateUnitDialogError ? [sharedCreateUnitDialogError] : []"
                        @update:modelValue="handleSharedCreateUnitTitleInput"
                        @blur="validateSharedCreateUnitDialog"
                        @keydown.enter.prevent="submitSharedCreateUnitDialog" />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" :disabled="actionBusy || sharedCreateUnitDialogSaving" @click="closeSharedCreateUnitDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="sharedCreateUnitDialogSaving" :disabled="actionBusy" @click="submitSharedCreateUnitDialog">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="sharedRenameDialog.open" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">{{ sharedRenameDialog.levelLabel }} umbenennen</v-card-title>
                <v-card-text>
                    <div class="text-body-2 text-medium-emphasis mb-3">Titel ändern</div>
                    <v-text-field
                        :model-value="sharedRenameDialog.title"
                        label="Titel"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        autofocus
                        :disabled="actionBusy || sharedRenameDialogSaving"
                        :rules="[required(), maxLength(255)]"
                        :error-messages="sharedRenameDialogError ? [sharedRenameDialogError] : []"
                        @update:modelValue="handleSharedRenameTitleInput"
                        @blur="validateSharedRenameDialog"
                        @keydown.enter.prevent="submitSharedRenameDialog" />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" :disabled="actionBusy || sharedRenameDialogSaving" @click="closeSharedRenameDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" :loading="sharedRenameDialogSaving" :disabled="actionBusy" @click="submitSharedRenameDialog">Speichern</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="sharedDeleteDialog.open" max-width="520" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">{{ sharedDeleteTitle(sharedDeleteDialog.level) }}</v-card-title>
                <v-card-text>
                    <div class="text-body-1 mb-1">{{ sharedDeleteDialog.label || 'Diesen Eintrag' }}</div>
                    <div class="text-body-2 text-medium-emphasis">
                        Wirklich löschen? Das ist nur möglich, wenn keine Materialien zugeordnet sind.
                    </div>
                    <div v-if="sharedDeleteDialogError" class="text-body-2 text-error mt-3">
                        {{ sharedDeleteDialogError }}
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end px-4 pb-4">
                    <v-btn
                        variant="text"
                        :disabled="actionBusy || sharedDeleteDialogDeleting"
                        @click="closeSharedDeleteDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-delete-outline"
                        :loading="sharedDeleteDialogDeleting"
                        :disabled="actionBusy"
                        @click="confirmSharedDeleteDialog">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios'
import { useValidationRulesSetup } from '@/helpers/rules'

function createSharedRenameDialogState() {
    return {
        open: false,
        source: 'shared',
        level: '',
        levelLabel: '',
        key: '',
        ruleId: null,
        nodeId: null,
        title: '',
    }
}

function createSharedCreateSubjectDialogState() {
    return {
        open: false,
        source: 'shared',
        ruleId: null,
        beforeSubjectId: null,
        title: '',
    }
}

function createSharedCreateTopicDialogState() {
    return {
        open: false,
        source: 'shared',
        ruleId: null,
        subjectId: null,
        beforeTopicId: null,
        title: '',
    }
}

function createSharedCreateUnitDialogState() {
    return {
        open: false,
        source: 'shared',
        ruleId: null,
        topicId: null,
        beforeUnitId: null,
        title: '',
    }
}

function createSharedDeleteDialogState() {
    return {
        open: false,
        source: 'shared',
        level: '',
        levelLabel: '',
        ruleId: null,
        nodeId: null,
        label: '',
    }
}

export default {
    name: 'MaterialsSubjectsContentsTree',
    setup() {
        return useValidationRulesSetup()
    },
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
        allowSharedShareButtons: {
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
        sharedObjectsForMe: {
            type: Array,
            default: () => [],
        },
        archivedSharedObjectsForMe: {
            type: Array,
            default: () => [],
        },
        sharedObjectsForMeLoading: {
            type: Boolean,
            default: false,
        },
        sharedObjectsForMeError: {
            type: String,
            default: '',
        },
        sharedForMeExpanded: {
            type: Boolean,
            default: false,
        },
        sharedForMeArchiveExpanded: {
            type: Boolean,
            default: false,
        },
        archivingSharedRuleId: {
            type: Number,
            default: null,
        },
        unarchivingSharedRuleId: {
            type: Number,
            default: null,
        },
        workspaceStructureExpanded: {
            type: Boolean,
            default: false,
        },
        expandedSharedItems: {
            type: Object,
            default: () => ({}),
        },
        initiallyCollapseHierarchy: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['open-material', 'open-share', 'open-create', 'open-attachments', 'open-shared-material', 'open-shared-attachments', 'open-shared-insert-draft', 'unlink-linked-material', 'unlink-linked-topic', 'unlink-linked-unit', 'toggle-shared-for-me-expanded', 'toggle-shared-for-me-archive-expanded', 'toggle-shared-item-expanded', 'toggle-workspace-structure-expanded', 'archive-shared-item', 'activate-shared-item', 'shared-node-created', 'shared-node-renamed', 'shared-node-deleted', 'shared-node-moved', 'workspace-node-created', 'workspace-node-renamed', 'workspace-node-deleted', 'workspace-node-moved'],
    data() {
        return {
            workspaceExpanded: true,
            sharedNodeTitleOverrides: {},
            sharedStructureButtonsVisible: {},
            collapsedWorkspaceSubjects: {},
            collapsedWorkspaceTopics: {},
            collapsedWorkspaceUnits: {},
            collapsedSharedSubjects: {},
            collapsedSharedTopics: {},
            collapsedSharedUnits: {},
            collapsedArchivedSubjects: {},
            collapsedArchivedTopics: {},
            collapsedArchivedUnits: {},
            sharedCreateSubjectDialog: createSharedCreateSubjectDialogState(),
            sharedCreateSubjectDialogError: '',
            sharedCreateSubjectDialogSaving: false,
            sharedCreateTopicDialog: createSharedCreateTopicDialogState(),
            sharedCreateTopicDialogError: '',
            sharedCreateTopicDialogSaving: false,
            sharedCreateUnitDialog: createSharedCreateUnitDialogState(),
            sharedCreateUnitDialogError: '',
            sharedCreateUnitDialogSaving: false,
            sharedRenameDialog: createSharedRenameDialogState(),
            sharedRenameDialogError: '',
            sharedRenameDialogSaving: false,
            sharedDeleteDialog: createSharedDeleteDialogState(),
            sharedDeleteDialogError: '',
            sharedDeleteDialogDeleting: false,
            expandedArchivedItems: {},
        }
    },
    methods: {
        subjectHasChildren(subject) {
            const materials = Array.isArray(subject?.materials) ? subject.materials : []
            const topics = Array.isArray(subject?.topics) ? subject.topics : []

            return materials.length > 0 || topics.length > 0
        },
        topicHasChildren(topic) {
            const materials = Array.isArray(topic?.materials) ? topic.materials : []
            const units = Array.isArray(topic?.units) ? topic.units : []

            return materials.length > 0 || units.length > 0
        },
        unitHasChildren(unit) {
            const materials = Array.isArray(unit?.materials) ? unit.materials : []

            return materials.length > 0
        },
        linkedPermissionChipColor(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'error'
            if (normalized === 'read_write') return 'warning'
            if (normalized === 'read_append') return 'info'
            return 'primary'
        },
        toggleWorkspaceExpanded() {
            if (this.actionBusy) return

            this.workspaceExpanded = !this.workspaceExpanded
        },
        workspaceSubjectKey(subject) {
            return this.workspaceNodeOverrideKey('subject', subject)
        },
        isWorkspaceSubjectExpanded(subject) {
            const key = this.workspaceSubjectKey(subject)
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedWorkspaceSubjects, key)
                ? this.collapsedWorkspaceSubjects[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleWorkspaceSubjectExpanded(subject) {
            if (this.actionBusy) return

            const key = this.workspaceSubjectKey(subject)
            if (key === '') return

            this.collapsedWorkspaceSubjects = {
                ...this.collapsedWorkspaceSubjects,
                [key]: !this.isWorkspaceSubjectExpanded(subject),
            }
        },
        workspaceTopicKey(topic) {
            return this.workspaceNodeOverrideKey('topic', topic)
        },
        isWorkspaceTopicExpanded(topic) {
            const key = this.workspaceTopicKey(topic)
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedWorkspaceTopics, key)
                ? this.collapsedWorkspaceTopics[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleWorkspaceTopicExpanded(topic) {
            if (this.actionBusy) return

            const key = this.workspaceTopicKey(topic)
            if (key === '') return

            this.collapsedWorkspaceTopics = {
                ...this.collapsedWorkspaceTopics,
                [key]: !this.isWorkspaceTopicExpanded(topic),
            }
        },
        workspaceUnitKey(unit) {
            return this.workspaceNodeOverrideKey('unit', unit)
        },
        isWorkspaceUnitExpanded(unit) {
            const key = this.workspaceUnitKey(unit)
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedWorkspaceUnits, key)
                ? this.collapsedWorkspaceUnits[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleWorkspaceUnitExpanded(unit) {
            if (this.actionBusy) return

            const key = this.workspaceUnitKey(unit)
            if (key === '') return

            this.collapsedWorkspaceUnits = {
                ...this.collapsedWorkspaceUnits,
                [key]: !this.isWorkspaceUnitExpanded(unit),
            }
        },
        isWorkspaceStructureButtonsVisible() {
            return this.workspaceStructureExpanded === true
        },
        toggleWorkspaceStructureButtons() {
            if (this.actionBusy) return
            this.$emit('toggle-workspace-structure-expanded')
        },
        toggleSharedForMeExpanded() {
            if (this.actionBusy) return

            this.$emit('toggle-shared-for-me-expanded')
        },
        toggleSharedForMeArchiveExpanded() {
            if (this.actionBusy) return

            this.$emit('toggle-shared-for-me-archive-expanded')
        },
        sharedSubjectKey(ruleId, subject, bucket = 'shared') {
            const key = this.sharedNodeOverrideKey(ruleId, 'subject', subject)
            return key !== '' ? `${bucket}:${key}` : ''
        },
        isSharedSubjectExpanded(ruleId, subject) {
            const key = this.sharedSubjectKey(ruleId, subject, 'shared')
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedSharedSubjects, key)
                ? this.collapsedSharedSubjects[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleSharedSubjectExpanded(ruleId, subject) {
            if (this.actionBusy) return

            const key = this.sharedSubjectKey(ruleId, subject, 'shared')
            if (key === '') return

            this.collapsedSharedSubjects = {
                ...this.collapsedSharedSubjects,
                [key]: !this.isSharedSubjectExpanded(ruleId, subject),
            }
        },
        sharedTopicKey(ruleId, topic, bucket = 'shared') {
            const key = this.sharedNodeOverrideKey(ruleId, 'topic', topic)
            return key !== '' ? `${bucket}:${key}` : ''
        },
        isSharedTopicExpanded(ruleId, topic) {
            const key = this.sharedTopicKey(ruleId, topic, 'shared')
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedSharedTopics, key)
                ? this.collapsedSharedTopics[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleSharedTopicExpanded(ruleId, topic) {
            if (this.actionBusy) return

            const key = this.sharedTopicKey(ruleId, topic, 'shared')
            if (key === '') return

            this.collapsedSharedTopics = {
                ...this.collapsedSharedTopics,
                [key]: !this.isSharedTopicExpanded(ruleId, topic),
            }
        },
        sharedUnitKey(ruleId, unit, bucket = 'shared') {
            const key = this.sharedNodeOverrideKey(ruleId, 'unit', unit)
            return key !== '' ? `${bucket}:${key}` : ''
        },
        isSharedUnitExpanded(ruleId, unit) {
            const key = this.sharedUnitKey(ruleId, unit, 'shared')
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedSharedUnits, key)
                ? this.collapsedSharedUnits[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleSharedUnitExpanded(ruleId, unit) {
            if (this.actionBusy) return

            const key = this.sharedUnitKey(ruleId, unit, 'shared')
            if (key === '') return

            this.collapsedSharedUnits = {
                ...this.collapsedSharedUnits,
                [key]: !this.isSharedUnitExpanded(ruleId, unit),
            }
        },
        isArchivedSubjectExpanded(ruleId, subject) {
            const key = this.sharedSubjectKey(ruleId, subject, 'archived')
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedArchivedSubjects, key)
                ? this.collapsedArchivedSubjects[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleArchivedSubjectExpanded(ruleId, subject) {
            if (this.actionBusy) return

            const key = this.sharedSubjectKey(ruleId, subject, 'archived')
            if (key === '') return

            this.collapsedArchivedSubjects = {
                ...this.collapsedArchivedSubjects,
                [key]: !this.isArchivedSubjectExpanded(ruleId, subject),
            }
        },
        isArchivedTopicExpanded(ruleId, topic) {
            const key = this.sharedTopicKey(ruleId, topic, 'archived')
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedArchivedTopics, key)
                ? this.collapsedArchivedTopics[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleArchivedTopicExpanded(ruleId, topic) {
            if (this.actionBusy) return

            const key = this.sharedTopicKey(ruleId, topic, 'archived')
            if (key === '') return

            this.collapsedArchivedTopics = {
                ...this.collapsedArchivedTopics,
                [key]: !this.isArchivedTopicExpanded(ruleId, topic),
            }
        },
        isArchivedUnitExpanded(ruleId, unit) {
            const key = this.sharedUnitKey(ruleId, unit, 'archived')
            if (key === '') {
                return this.initiallyCollapseHierarchy !== true
            }

            return Object.prototype.hasOwnProperty.call(this.collapsedArchivedUnits, key)
                ? this.collapsedArchivedUnits[key] === true
                : this.initiallyCollapseHierarchy !== true
        },
        toggleArchivedUnitExpanded(ruleId, unit) {
            if (this.actionBusy) return

            const key = this.sharedUnitKey(ruleId, unit, 'archived')
            if (key === '') return

            this.collapsedArchivedUnits = {
                ...this.collapsedArchivedUnits,
                [key]: !this.isArchivedUnitExpanded(ruleId, unit),
            }
        },
        archivedItemKey(ruleId) {
            const normalizedRuleId = Number(ruleId)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) return ''
            return `archived-item-${normalizedRuleId}`
        },
        isArchivedItemExpanded(ruleId) {
            const key = this.archivedItemKey(ruleId)
            return key !== '' ? this.expandedArchivedItems[key] === true : false
        },
        toggleArchivedItemExpanded(ruleId) {
            if (this.actionBusy) return

            const key = this.archivedItemKey(ruleId)
            if (key === '') return

            if (this.expandedArchivedItems[key] === true) {
                this.expandedArchivedItems = {}
                return
            }

            this.expandedArchivedItems = {
                [key]: true,
            }
        },
        archivedItemCardStyle(ruleId) {
            if (this.isArchivedItemExpanded(ruleId)) {
                return {
                    flex: '1 1 100%',
                    maxWidth: '100%',
                }
            }

            return {
                flex: '1 1 24rem',
                maxWidth: '28rem',
            }
        },
        sharedItemKey(ruleId) {
            const normalizedRuleId = Number(ruleId)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) return ''
            return `shared-item-${normalizedRuleId}`
        },
        isSharedItemExpanded(ruleId) {
            const key = this.sharedItemKey(ruleId)
            return key !== '' ? this.expandedSharedItems[key] === true : false
        },
        activeSharedItemRuleId() {
            const items = Array.isArray(this.sharedObjectsForMe) ? this.sharedObjectsForMe : []
            const active = items.find((item) => this.isSharedItemExpanded(item?.ruleId))
            const activeRuleId = Number(active?.ruleId || 0)
            return Number.isFinite(activeRuleId) && activeRuleId > 0 ? activeRuleId : null
        },
        sharedItemIsDisabled(ruleId) {
            const activeRuleId = this.activeSharedItemRuleId()
            if (!Number.isFinite(activeRuleId) || activeRuleId <= 0) {
                return false
            }

            const normalizedRuleId = Number(ruleId || 0)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) {
                return true
            }

            return normalizedRuleId !== activeRuleId
        },
        toggleSharedItemExpanded(ruleId) {
            if (this.actionBusy) return

            const key = this.sharedItemKey(ruleId)
            if (key === '') return

            this.$emit('toggle-shared-item-expanded', ruleId)
        },
        archiveSharedItem(ruleId) {
            if (this.actionBusy) return
            if (this.sharedItemIsDisabled(ruleId)) return

            const normalizedRuleId = Number(ruleId || 0)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) return

            this.$emit('archive-shared-item', normalizedRuleId)
        },
        activateSharedItem(ruleId) {
            if (this.actionBusy) return

            const normalizedRuleId = Number(ruleId || 0)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) return

            this.$emit('activate-shared-item', normalizedRuleId)
        },
        sharedStructureButtonsKey(ruleId) {
            const normalizedRuleId = Number(ruleId)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) return ''
            return `shared-structure-buttons-${normalizedRuleId}`
        },
        isSharedStructureButtonsVisible(ruleId) {
            const key = this.sharedStructureButtonsKey(ruleId)
            return key !== '' ? this.sharedStructureButtonsVisible[key] === true : false
        },
        toggleSharedStructureButtons(ruleId) {
            if (this.actionBusy) return

            const key = this.sharedStructureButtonsKey(ruleId)
            if (key === '') return

            this.sharedStructureButtonsVisible = {
                ...this.sharedStructureButtonsVisible,
                [key]: !this.isSharedStructureButtonsVisible(ruleId),
            }
        },
        sharedItemCardStyle(ruleId) {
            if (this.isSharedItemExpanded(ruleId)) {
                return {
                    flex: '1 1 100%',
                    maxWidth: '100%',
                }
            }

            return {
                flex: '1 1 24rem',
                maxWidth: '28rem',
            }
        },
        sharedItemHierarchy(item) {
            return Array.isArray(item?.hierarchy) ? item.hierarchy : []
        },
        sharedItemCanExpand(item) {
            return this.sharedItemHierarchy(item).length > 0 || this.sharedItemCanStructureEdit(item)
        },
        sharedItemHasFullAccess(item) {
            return String(item?.permission || '').trim() === 'full_access'
        },
        sharedItemCanStructureEdit(item) {
            const permission = String(item?.permission || '').trim()
            return permission === 'read_write' || permission === 'full_access'
        },
        sharedItemCanAddMaterial(item) {
            const permission = String(item?.permission || '').trim()
            return permission === 'read_append' || permission === 'read_write' || permission === 'full_access'
        },
        sharedItemTypeLabel(item) {
            const scopeType = String(item?.scopeType || item?.scope_type || '').trim().toLowerCase()
            if (scopeType === 'all') return 'Workspace'
            if (scopeType === 'subject') return 'Fach'
            if (scopeType === 'topic') return 'Thema'
            if (scopeType === 'unit') return 'Bereich'
            if (scopeType === 'material') return 'Material'

            const scopeLabel = String(item?.scopeLabel || item?.scope_label || '').trim()
            if (scopeLabel !== '') {
                return scopeLabel
            }

            const fallbackPath = String(item?.scopePathLabel || item?.scope_path_label || '').trim()
            if (fallbackPath !== '') {
                return fallbackPath
            }

            return 'Freigabe'
        },
        sharedItemScopeType(item) {
            return String(item?.scopeType || item?.scope_type || 'all').trim().toLowerCase()
        },
        sharedItemSupportsSubjectCreate(item) {
            return this.sharedItemCanStructureEdit(item) && this.sharedItemScopeType(item) === 'all'
        },
        sharedItemSupportsTopicCreate(item) {
            const scopeType = this.sharedItemScopeType(item)
            return this.sharedItemCanStructureEdit(item) && (scopeType === 'all' || scopeType === 'subject')
        },
        sharedItemSupportsUnitCreate(item) {
            const scopeType = this.sharedItemScopeType(item)
            return this.sharedItemCanStructureEdit(item) && (scopeType === 'all' || scopeType === 'subject')
        },
        sharedRenameLevelLabel(level) {
            if (level === 'subject') return 'Fach'
            if (level === 'topic') return 'Thema'
            if (level === 'unit') return 'Bereich'
            return 'Element'
        },
        openSharedCreateSubjectDialog(item, options = {}) {
            if (this.actionBusy) return
            if (!this.sharedItemSupportsSubjectCreate(item)) return

            const ruleId = Number(item?.ruleId || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            const beforeSubjectId = Number(options?.beforeSubjectId || 0)

            this.sharedCreateSubjectDialog = {
                open: true,
                source: 'shared',
                ruleId,
                beforeSubjectId: Number.isFinite(beforeSubjectId) && beforeSubjectId > 0 ? beforeSubjectId : null,
                title: '',
            }
            this.sharedCreateSubjectDialogError = ''
        },
        closeSharedCreateSubjectDialog() {
            if (this.sharedCreateSubjectDialogSaving) return

            this.sharedCreateSubjectDialog = createSharedCreateSubjectDialogState()
            this.sharedCreateSubjectDialogError = ''
        },
        openSharedCreateTopicDialog(item, subject, options = {}) {
            if (this.actionBusy) return
            if (!this.sharedItemSupportsTopicCreate(item)) return

            const ruleId = Number(item?.ruleId || 0)
            const subjectId = Number(subject?.id || 0)
            const beforeTopicId = Number(options?.beforeTopicId || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!Number.isFinite(subjectId) || subjectId <= 0) return

            this.sharedCreateTopicDialog = {
                open: true,
                source: 'shared',
                ruleId,
                subjectId,
                beforeTopicId: Number.isFinite(beforeTopicId) && beforeTopicId > 0 ? beforeTopicId : null,
                title: '',
            }
            this.sharedCreateTopicDialogError = ''
        },
        closeSharedCreateTopicDialog() {
            if (this.sharedCreateTopicDialogSaving) return

            this.sharedCreateTopicDialog = createSharedCreateTopicDialogState()
            this.sharedCreateTopicDialogError = ''
        },
        openSharedCreateUnitDialog(item, topic, options = {}) {
            if (this.actionBusy) return
            if (!this.sharedItemSupportsUnitCreate(item)) return

            const ruleId = Number(item?.ruleId || 0)
            const topicId = Number(topic?.id || 0)
            const beforeUnitId = Number(options?.beforeUnitId || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!Number.isFinite(topicId) || topicId <= 0) return

            this.sharedCreateUnitDialog = {
                open: true,
                source: 'shared',
                ruleId,
                topicId,
                beforeUnitId: Number.isFinite(beforeUnitId) && beforeUnitId > 0 ? beforeUnitId : null,
                title: '',
            }
            this.sharedCreateUnitDialogError = ''
        },
        closeSharedCreateUnitDialog() {
            if (this.sharedCreateUnitDialogSaving) return

            this.sharedCreateUnitDialog = createSharedCreateUnitDialogState()
            this.sharedCreateUnitDialogError = ''
        },
        openWorkspaceCreateSubjectDialog(options = {}) {
            if (this.actionBusy) return
            if (!this.enableCreateButtons) return

            const beforeSubjectId = Number(options?.beforeSubjectId || 0)
            this.sharedCreateSubjectDialog = {
                open: true,
                source: 'workspace',
                ruleId: null,
                beforeSubjectId: Number.isFinite(beforeSubjectId) && beforeSubjectId > 0 ? beforeSubjectId : null,
                title: '',
            }
            this.sharedCreateSubjectDialogError = ''
        },
        openWorkspaceCreateTopicDialog(subject, options = {}) {
            if (this.actionBusy) return
            if (!this.enableCreateButtons) return

            const subjectId = Number(subject?.id || 0)
            const beforeTopicId = Number(options?.beforeTopicId || 0)
            if (!Number.isFinite(subjectId) || subjectId <= 0) return

            this.sharedCreateTopicDialog = {
                open: true,
                source: 'workspace',
                ruleId: null,
                subjectId,
                beforeTopicId: Number.isFinite(beforeTopicId) && beforeTopicId > 0 ? beforeTopicId : null,
                title: '',
            }
            this.sharedCreateTopicDialogError = ''
        },
        openWorkspaceCreateUnitDialog(topic, options = {}) {
            if (this.actionBusy) return
            if (!this.enableCreateButtons) return

            const topicId = Number(topic?.id || 0)
            const beforeUnitId = Number(options?.beforeUnitId || 0)
            if (!Number.isFinite(topicId) || topicId <= 0) return

            this.sharedCreateUnitDialog = {
                open: true,
                source: 'workspace',
                ruleId: null,
                topicId,
                beforeUnitId: Number.isFinite(beforeUnitId) && beforeUnitId > 0 ? beforeUnitId : null,
                title: '',
            }
            this.sharedCreateUnitDialogError = ''
        },
        openWorkspaceRenameDialog(level, node) {
            if (this.actionBusy) return
            if (!this.enableCreateButtons) return

            const overrideKey = this.workspaceNodeOverrideKey(level, node)
            if (overrideKey === '') return

            this.sharedRenameDialog = {
                open: true,
                source: 'workspace',
                level,
                levelLabel: this.sharedRenameLevelLabel(level),
                key: overrideKey,
                ruleId: null,
                nodeId: Number(node?.id || 0),
                title: this.workspaceNodeTitle(level, node),
            }
            this.sharedRenameDialogError = ''
        },
        openWorkspaceDeleteDialog(level, node) {
            if (this.actionBusy) return
            if (!this.enableCreateButtons) return

            const nodeId = Number(node?.id || 0)
            if (!Number.isFinite(nodeId) || nodeId <= 0) return

            this.sharedDeleteDialog = {
                open: true,
                source: 'workspace',
                level,
                levelLabel: this.sharedRenameLevelLabel(level),
                ruleId: null,
                nodeId,
                label: this.workspaceNodeTitle(level, node),
            }
            this.sharedDeleteDialogError = ''
        },
        workspaceMoveEndpoint(level, nodeId) {
            const id = Number(nodeId || 0)
            if (!Number.isFinite(id) || id <= 0) return ''
            if (level === 'subject') return `/api/admin/materials/subjects/${id}/move`
            if (level === 'topic') return `/api/admin/materials/topics/${id}/move`
            if (level === 'unit') return `/api/admin/materials/units/${id}/move`
            return ''
        },
        workspaceRenameEndpoint(level, nodeId) {
            const id = Number(nodeId || 0)
            if (!Number.isFinite(id) || id <= 0) return ''
            if (level === 'subject') return `/api/admin/materials/subjects/${id}`
            if (level === 'topic') return `/api/admin/materials/topics/${id}`
            if (level === 'unit') return `/api/admin/materials/units/${id}`
            return ''
        },
        workspaceDeleteEndpoint(level, nodeId) {
            return this.workspaceRenameEndpoint(level, nodeId)
        },
        async moveWorkspaceNode(level, node, direction) {
            if (this.actionBusy) return
            if (!this.enableCreateButtons) return

            const nodeId = Number(node?.id || 0)
            const endpoint = this.workspaceMoveEndpoint(level, nodeId)
            const normalizedDirection = String(direction || '').trim().toLowerCase()
            if (endpoint === '') return
            if (normalizedDirection !== 'up' && normalizedDirection !== 'down') return

            try {
                await axios.post(endpoint, {
                    data: {
                        direction: normalizedDirection,
                    },
                })

                this.$emit('workspace-node-moved', {
                    level: String(level || ''),
                    nodeId,
                    direction: normalizedDirection,
                })
            } catch {
                // Fehler werden bewusst still behandelt; parent refresh bleibt source of truth.
            }
        },
        sharedDeleteTitle(level) {
            if (level === 'subject') return 'Fach löschen'
            if (level === 'topic') return 'Thema löschen'
            if (level === 'unit') return 'Bereich löschen'
            return 'Element löschen'
        },
        sharedNodeOverrideKey(ruleId, level, node) {
            const normalizedRuleId = Number(ruleId)
            const normalizedId = Number(node?.id)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) return ''

            if (Number.isFinite(normalizedId) && normalizedId > 0) {
                return `${normalizedRuleId}:${level}:${normalizedId}`
            }

            const fallbackName = String(node?.name || '').trim()
            if (fallbackName === '') return ''

            return `${normalizedRuleId}:${level}:${fallbackName}`
        },
        workspaceNodeOverrideKey(level, node) {
            const normalizedId = Number(node?.id)
            if (Number.isFinite(normalizedId) && normalizedId > 0) {
                return `workspace:${level}:${normalizedId}`
            }

            const fallbackName = String(node?.name || '').trim()
            if (fallbackName === '') return ''

            return `workspace:${level}:${fallbackName}`
        },
        workspaceNodeTitle(level, node) {
            const overrideKey = this.workspaceNodeOverrideKey(level, node)
            const overriddenTitle = overrideKey !== '' ? String(this.sharedNodeTitleOverrides?.[overrideKey] || '').trim() : ''
            if (overriddenTitle !== '') {
                return overriddenTitle
            }

            return String(node?.name || '').trim()
        },
        sharedNodeTitle(ruleId, level, node) {
            const overrideKey = this.sharedNodeOverrideKey(ruleId, level, node)
            const overriddenTitle = overrideKey !== '' ? String(this.sharedNodeTitleOverrides?.[overrideKey] || '').trim() : ''
            if (overriddenTitle !== '') {
                return overriddenTitle
            }

            return String(node?.name || '').trim()
        },
        openSharedRenameDialog(item, level, node) {
            if (this.actionBusy) return
            if (!this.sharedNodeCanStructureEdit(item, level)) return

            const overrideKey = this.sharedNodeOverrideKey(item?.ruleId, level, node)
            if (overrideKey === '') return

            this.sharedRenameDialog = {
                open: true,
                source: 'shared',
                level,
                levelLabel: this.sharedRenameLevelLabel(level),
                key: overrideKey,
                ruleId: Number(item?.ruleId || 0),
                nodeId: Number(node?.id || 0),
                title: this.sharedNodeTitle(item?.ruleId, level, node),
            }
            this.sharedRenameDialogError = ''
        },
        closeSharedRenameDialog() {
            if (this.sharedRenameDialogSaving) return
            this.sharedRenameDialog = createSharedRenameDialogState()
            this.sharedRenameDialogError = ''
        },
        openSharedDeleteDialog(item, level, node) {
            if (this.actionBusy) return
            if (!this.sharedNodeCanStructureDelete(item, level)) return

            const ruleId = Number(item?.ruleId || 0)
            const nodeId = Number(node?.id || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!Number.isFinite(nodeId) || nodeId <= 0) return

            this.sharedDeleteDialog = {
                open: true,
                source: 'shared',
                level,
                levelLabel: this.sharedRenameLevelLabel(level),
                ruleId,
                nodeId,
                label: this.sharedNodeTitle(item?.ruleId, level, node),
            }
            this.sharedDeleteDialogError = ''
        },
        closeSharedDeleteDialog() {
            if (this.sharedDeleteDialogDeleting) return
            this.sharedDeleteDialog = createSharedDeleteDialogState()
            this.sharedDeleteDialogError = ''
        },
        sharedRenameTitleValidationMessage(value) {
            const normalizedValue = String(value ?? '').trim()
            const rules = [
                this.required(),
                this.maxLength(255),
            ]

            for (const rule of rules) {
                const result = rule(normalizedValue)
                if (result !== true) {
                    return String(result)
                }
            }

            return ''
        },
        validateSharedRenameDialog() {
            const validationMessage = this.sharedRenameTitleValidationMessage(this.sharedRenameDialog?.title)
            this.sharedRenameDialogError = validationMessage
            return validationMessage === ''
        },
        validateSharedCreateSubjectDialog() {
            const validationMessage = this.sharedRenameTitleValidationMessage(this.sharedCreateSubjectDialog?.title)
            this.sharedCreateSubjectDialogError = validationMessage
            return validationMessage === ''
        },
        validateSharedCreateTopicDialog() {
            const validationMessage = this.sharedRenameTitleValidationMessage(this.sharedCreateTopicDialog?.title)
            this.sharedCreateTopicDialogError = validationMessage
            return validationMessage === ''
        },
        validateSharedCreateUnitDialog() {
            const validationMessage = this.sharedRenameTitleValidationMessage(this.sharedCreateUnitDialog?.title)
            this.sharedCreateUnitDialogError = validationMessage
            return validationMessage === ''
        },
        handleSharedCreateSubjectTitleInput(value) {
            this.sharedCreateSubjectDialog = {
                ...this.sharedCreateSubjectDialog,
                title: value,
            }

            if (this.sharedCreateSubjectDialogError !== '') {
                this.sharedCreateSubjectDialogError = this.sharedRenameTitleValidationMessage(value)
            }
        },
        handleSharedCreateTopicTitleInput(value) {
            this.sharedCreateTopicDialog = {
                ...this.sharedCreateTopicDialog,
                title: value,
            }

            if (this.sharedCreateTopicDialogError !== '') {
                this.sharedCreateTopicDialogError = this.sharedRenameTitleValidationMessage(value)
            }
        },
        handleSharedCreateUnitTitleInput(value) {
            this.sharedCreateUnitDialog = {
                ...this.sharedCreateUnitDialog,
                title: value,
            }

            if (this.sharedCreateUnitDialogError !== '') {
                this.sharedCreateUnitDialogError = this.sharedRenameTitleValidationMessage(value)
            }
        },
        handleSharedRenameTitleInput(value) {
            this.sharedRenameDialog = {
                ...this.sharedRenameDialog,
                title: value,
            }

            if (this.sharedRenameDialogError !== '') {
                this.sharedRenameDialogError = this.sharedRenameTitleValidationMessage(value)
            }
        },
        sharedRenameEndpoint(level, nodeId) {
            const id = Number(nodeId || 0)
            if (!Number.isFinite(id) || id <= 0) return ''
            if (level === 'subject') return `/api/admin/materials/shares/inbox/subjects/${id}`
            if (level === 'topic') return `/api/admin/materials/shares/inbox/topics/${id}`
            if (level === 'unit') return `/api/admin/materials/shares/inbox/units/${id}`
            return ''
        },
        sharedDeleteEndpoint(level, nodeId) {
            const id = Number(nodeId || 0)
            if (!Number.isFinite(id) || id <= 0) return ''
            if (level === 'subject') return `/api/admin/materials/shares/inbox/subjects/${id}`
            if (level === 'topic') return `/api/admin/materials/shares/inbox/topics/${id}`
            if (level === 'unit') return `/api/admin/materials/shares/inbox/units/${id}`
            return ''
        },
        sharedMoveEndpoint(level, nodeId) {
            const id = Number(nodeId || 0)
            if (!Number.isFinite(id) || id <= 0) return ''
            if (level === 'subject') return `/api/admin/materials/shares/inbox/subjects/${id}/move`
            if (level === 'topic') return `/api/admin/materials/shares/inbox/topics/${id}/move`
            if (level === 'unit') return `/api/admin/materials/shares/inbox/units/${id}/move`
            return ''
        },
        async moveSharedNode(item, level, node, direction) {
            if (this.actionBusy) return
            if (!this.sharedNodeCanStructureEdit(item, level)) return

            const ruleId = Number(item?.ruleId || 0)
            const nodeId = Number(node?.id || 0)
            const endpoint = this.sharedMoveEndpoint(level, nodeId)
            const normalizedDirection = String(direction || '').trim().toLowerCase()
            if (endpoint === '') return
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (normalizedDirection !== 'up' && normalizedDirection !== 'down') return

            try {
                await axios.post(endpoint, {
                    rule_id: ruleId,
                    data: {
                        direction: normalizedDirection,
                    },
                })

                this.$emit('shared-node-moved', {
                    ruleId,
                    level: String(level || ''),
                    nodeId,
                    direction: normalizedDirection,
                })
            } catch {
                // Fehler werden bewusst still behandelt; parent refresh bleibt source of truth.
            }
        },
        async submitSharedCreateSubjectDialog() {
            if (!this.validateSharedCreateSubjectDialog()) return
            if (this.sharedCreateSubjectDialogSaving) return

            const normalizedTitle = String(this.sharedCreateSubjectDialog?.title || '').trim()
            const ruleId = Number(this.sharedCreateSubjectDialog?.ruleId || 0)
            const beforeSubjectId = Number(this.sharedCreateSubjectDialog?.beforeSubjectId || 0)
            const isWorkspaceSource = String(this.sharedCreateSubjectDialog?.source || '') === 'workspace'
            if (!isWorkspaceSource && (!Number.isFinite(ruleId) || ruleId <= 0)) {
                this.sharedCreateSubjectDialogError = 'Fach konnte nicht erstellt werden.'
                return
            }

            this.sharedCreateSubjectDialogSaving = true

            try {
                const subjectPayload = {
                    name: normalizedTitle,
                }
                if (Number.isFinite(beforeSubjectId) && beforeSubjectId > 0) {
                    subjectPayload.before_subject_id = beforeSubjectId
                }

                const response = isWorkspaceSource
                    ? await axios.post('/api/admin/materials/subjects', {
                        data: subjectPayload,
                    })
                    : await axios.post('/api/admin/materials/shares/inbox/subjects', {
                        rule_id: ruleId,
                        data: subjectPayload,
                    })
                const subjectId = Number(response?.data?.data?.id || 0)
                const savedTitle = String(response?.data?.data?.name || normalizedTitle).trim() || normalizedTitle

                if (isWorkspaceSource) {
                    this.$emit('workspace-node-created', {
                        level: 'subject',
                        nodeId: subjectId,
                        name: savedTitle,
                    })
                } else {
                    this.$emit('shared-node-created', {
                        ruleId,
                        level: 'subject',
                        nodeId: subjectId,
                        name: savedTitle,
                    })
                }
                this.sharedCreateSubjectDialog = createSharedCreateSubjectDialogState()
                this.sharedCreateSubjectDialogError = ''
            } catch (error) {
                this.sharedCreateSubjectDialogError = String(error?.response?.data?.message || 'Fach konnte nicht erstellt werden.')
            } finally {
                this.sharedCreateSubjectDialogSaving = false
            }
        },
        async submitSharedCreateTopicDialog() {
            if (!this.validateSharedCreateTopicDialog()) return
            if (this.sharedCreateTopicDialogSaving) return

            const normalizedTitle = String(this.sharedCreateTopicDialog?.title || '').trim()
            const ruleId = Number(this.sharedCreateTopicDialog?.ruleId || 0)
            const subjectId = Number(this.sharedCreateTopicDialog?.subjectId || 0)
            const beforeTopicId = Number(this.sharedCreateTopicDialog?.beforeTopicId || 0)
            const isWorkspaceSource = String(this.sharedCreateTopicDialog?.source || '') === 'workspace'
            if ((!isWorkspaceSource && (!Number.isFinite(ruleId) || ruleId <= 0)) || !Number.isFinite(subjectId) || subjectId <= 0) {
                this.sharedCreateTopicDialogError = 'Thema konnte nicht erstellt werden.'
                return
            }

            this.sharedCreateTopicDialogSaving = true

            try {
                const topicPayload = {
                    subject_id: subjectId,
                    name: normalizedTitle,
                }
                if (Number.isFinite(beforeTopicId) && beforeTopicId > 0) {
                    topicPayload.before_topic_id = beforeTopicId
                }

                const response = isWorkspaceSource
                    ? await axios.post('/api/admin/materials/topics', {
                        data: topicPayload,
                    })
                    : await axios.post('/api/admin/materials/shares/inbox/topics', {
                        rule_id: ruleId,
                        data: topicPayload,
                    })
                const topicId = Number(response?.data?.data?.id || 0)
                const savedTitle = String(response?.data?.data?.name || normalizedTitle).trim() || normalizedTitle

                if (isWorkspaceSource) {
                    this.$emit('workspace-node-created', {
                        level: 'topic',
                        nodeId: topicId,
                        name: savedTitle,
                        parentSubjectId: subjectId,
                    })
                } else {
                    this.$emit('shared-node-created', {
                        ruleId,
                        level: 'topic',
                        nodeId: topicId,
                        name: savedTitle,
                        parentSubjectId: subjectId,
                    })
                }
                this.sharedCreateTopicDialog = createSharedCreateTopicDialogState()
                this.sharedCreateTopicDialogError = ''
            } catch (error) {
                this.sharedCreateTopicDialogError = String(error?.response?.data?.message || 'Thema konnte nicht erstellt werden.')
            } finally {
                this.sharedCreateTopicDialogSaving = false
            }
        },
        async submitSharedCreateUnitDialog() {
            if (!this.validateSharedCreateUnitDialog()) return
            if (this.sharedCreateUnitDialogSaving) return

            const normalizedTitle = String(this.sharedCreateUnitDialog?.title || '').trim()
            const ruleId = Number(this.sharedCreateUnitDialog?.ruleId || 0)
            const topicId = Number(this.sharedCreateUnitDialog?.topicId || 0)
            const beforeUnitId = Number(this.sharedCreateUnitDialog?.beforeUnitId || 0)
            const isWorkspaceSource = String(this.sharedCreateUnitDialog?.source || '') === 'workspace'
            if ((!isWorkspaceSource && (!Number.isFinite(ruleId) || ruleId <= 0)) || !Number.isFinite(topicId) || topicId <= 0) {
                this.sharedCreateUnitDialogError = 'Bereich konnte nicht erstellt werden.'
                return
            }

            this.sharedCreateUnitDialogSaving = true

            try {
                const unitPayload = {
                    topic_id: topicId,
                    name: normalizedTitle,
                }
                if (Number.isFinite(beforeUnitId) && beforeUnitId > 0) {
                    unitPayload.before_unit_id = beforeUnitId
                }

                const response = isWorkspaceSource
                    ? await axios.post('/api/admin/materials/units', {
                        data: unitPayload,
                    })
                    : await axios.post('/api/admin/materials/shares/inbox/units', {
                        rule_id: ruleId,
                        data: unitPayload,
                    })
                const unitId = Number(response?.data?.data?.id || 0)
                const savedTitle = String(response?.data?.data?.name || normalizedTitle).trim() || normalizedTitle

                if (isWorkspaceSource) {
                    this.$emit('workspace-node-created', {
                        level: 'unit',
                        nodeId: unitId,
                        name: savedTitle,
                        parentTopicId: topicId,
                    })
                } else {
                    this.$emit('shared-node-created', {
                        ruleId,
                        level: 'unit',
                        nodeId: unitId,
                        name: savedTitle,
                        parentTopicId: topicId,
                    })
                }
                this.sharedCreateUnitDialog = createSharedCreateUnitDialogState()
                this.sharedCreateUnitDialogError = ''
            } catch (error) {
                this.sharedCreateUnitDialogError = String(error?.response?.data?.message || 'Bereich konnte nicht erstellt werden.')
            } finally {
                this.sharedCreateUnitDialogSaving = false
            }
        },
        async submitSharedRenameDialog() {
            if (!this.validateSharedRenameDialog()) return
            if (this.sharedRenameDialogSaving) return

            const normalizedTitle = String(this.sharedRenameDialog?.title || '').trim()
            const ruleId = Number(this.sharedRenameDialog?.ruleId || 0)
            const isWorkspaceSource = String(this.sharedRenameDialog?.source || '') === 'workspace'
            const endpoint = isWorkspaceSource
                ? this.workspaceRenameEndpoint(this.sharedRenameDialog?.level, this.sharedRenameDialog?.nodeId)
                : this.sharedRenameEndpoint(this.sharedRenameDialog?.level, this.sharedRenameDialog?.nodeId)
            if (endpoint === '' || (!isWorkspaceSource && (!Number.isFinite(ruleId) || ruleId <= 0))) {
                this.sharedRenameDialogError = 'Element konnte nicht gespeichert werden.'
                return
            }

            this.sharedRenameDialogSaving = true

            try {
                const response = isWorkspaceSource
                    ? await axios.put(endpoint, {
                        data: {
                            name: normalizedTitle,
                        },
                    })
                    : await axios.put(endpoint, {
                        rule_id: ruleId,
                        data: {
                            name: normalizedTitle,
                        },
                    })
                const savedTitle = String(response?.data?.data?.name || normalizedTitle).trim() || normalizedTitle

                this.sharedNodeTitleOverrides = {
                    ...this.sharedNodeTitleOverrides,
                    [this.sharedRenameDialog.key]: savedTitle,
                }

                if (isWorkspaceSource) {
                    this.$emit('workspace-node-renamed', {
                        level: String(this.sharedRenameDialog?.level || ''),
                        nodeId: Number(this.sharedRenameDialog?.nodeId || 0),
                        name: savedTitle,
                    })
                } else {
                    this.$emit('shared-node-renamed', {
                        ruleId,
                        level: String(this.sharedRenameDialog?.level || ''),
                        nodeId: Number(this.sharedRenameDialog?.nodeId || 0),
                        name: savedTitle,
                    })
                }
                this.sharedRenameDialog = createSharedRenameDialogState()
                this.sharedRenameDialogError = ''
            } catch (error) {
                this.sharedRenameDialogError = String(error?.response?.data?.message || 'Element konnte nicht gespeichert werden.')
            } finally {
                this.sharedRenameDialogSaving = false
            }
        },
        async confirmSharedDeleteDialog() {
            if (this.sharedDeleteDialogDeleting) return

            const ruleId = Number(this.sharedDeleteDialog?.ruleId || 0)
            const nodeId = Number(this.sharedDeleteDialog?.nodeId || 0)
            const isWorkspaceSource = String(this.sharedDeleteDialog?.source || '') === 'workspace'
            const endpoint = isWorkspaceSource
                ? this.workspaceDeleteEndpoint(this.sharedDeleteDialog?.level, nodeId)
                : this.sharedDeleteEndpoint(this.sharedDeleteDialog?.level, nodeId)
            if (endpoint === '' || (!isWorkspaceSource && (!Number.isFinite(ruleId) || ruleId <= 0)) || !Number.isFinite(nodeId) || nodeId <= 0) {
                this.sharedDeleteDialogError = 'Element konnte nicht gelöscht werden.'
                return
            }

            this.sharedDeleteDialogDeleting = true

            try {
                if (isWorkspaceSource) {
                    await axios.delete(endpoint)
                } else {
                    await axios.delete(endpoint, {
                        data: {
                            rule_id: ruleId,
                        },
                    })
                }

                if (isWorkspaceSource) {
                    this.$emit('workspace-node-deleted', {
                        level: String(this.sharedDeleteDialog?.level || ''),
                        nodeId,
                    })
                } else {
                    this.$emit('shared-node-deleted', {
                        ruleId,
                        level: String(this.sharedDeleteDialog?.level || ''),
                        nodeId,
                    })
                }
                this.sharedDeleteDialog = createSharedDeleteDialogState()
                this.sharedDeleteDialogError = ''
            } catch (error) {
                this.sharedDeleteDialogError = String(error?.response?.data?.message || 'Element konnte nicht gelöscht werden.')
            } finally {
                this.sharedDeleteDialogDeleting = false
            }
        },
        sharedUnitHasMaterials(unit) {
            return Array.isArray(unit?.materials) && unit.materials.length > 0
        },
        sharedTopicHasMaterials(topic) {
            if (Array.isArray(topic?.materials) && topic.materials.length > 0) {
                return true
            }

            const units = Array.isArray(topic?.units) ? topic.units : []
            return units.some((unit) => this.sharedUnitHasMaterials(unit))
        },
        sharedSubjectHasMaterials(subject) {
            if (Array.isArray(subject?.materials) && subject.materials.length > 0) {
                return true
            }

            const topics = Array.isArray(subject?.topics) ? subject.topics : []
            return topics.some((topic) => this.sharedTopicHasMaterials(topic))
        },
        sharedSubjectCanDelete(subject) {
            return !this.sharedSubjectHasMaterials(subject)
        },
        sharedTopicCanDelete(topic) {
            return !this.sharedTopicHasMaterials(topic)
        },
        sharedUnitCanDelete(unit) {
            return !this.sharedUnitHasMaterials(unit)
        },
        normalizeLinkedPermission(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'full_access'
            if (normalized === 'read_write') return 'read_write'
            if (normalized === 'read_append') return 'read_append'
            if (normalized === 'read_only') return 'read_only'
            return ''
        },
        canCreateMaterialInUnit(unit) {
            if (unit?.isLinked !== true) return true
            const permission = this.normalizeLinkedPermission(unit?.linkedPermission)
            if (permission === '') return false
            return permission !== 'read_only'
        },
        canCreateMaterialInTopic(topic) {
            if (topic?.isLinked !== true) return true
            const permission = this.normalizeLinkedPermission(topic?.linkedPermission)
            if (permission === '') return false
            return permission !== 'read_only'
        },
        linkedPermissionLabel(material) {
            const normalizedLabel = String(material?.linkedPermissionLabel || '').trim()
            if (normalizedLabel !== '') return normalizedLabel

            const permission = String(material?.linkedPermission || '').trim()
            if (permission === 'full_access') return 'VOLLZUGRIFF'
            if (permission === 'read_write') return 'LESEN/SCHREIBEN'
            if (permission === 'read_append') return 'LESEN/HINZUFÜGEN'
            return 'NUR LESEN'
        },
        hasPersistedNodeId(id) {
            const nodeId = Number(id)
            return Number.isFinite(nodeId) && nodeId > 0
        },
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
        workspaceHasInsertSubjectTarget() {
            const subjects = Array.isArray(this.items) ? this.items : []
            return subjects.length > 0
        },
        workspaceHasInsertTopicTarget() {
            const subjects = Array.isArray(this.items) ? this.items : []
            return subjects.some((subject) => Array.isArray(subject?.topics) && subject.topics.length > 0)
        },
        canShowSharedInsertButton(level) {
            const normalizedLevel = String(level || '').trim().toLowerCase()
            if (normalizedLevel === 'subject') return true
            if (normalizedLevel === 'topic') return this.workspaceHasInsertSubjectTarget()
            if (normalizedLevel === 'unit') return this.workspaceHasInsertTopicTarget()
            if (normalizedLevel === 'material') return this.workspaceHasInsertSubjectTarget()
            return false
        },
        sharedInsertParentLabel(item, lineage = {}) {
            const ruleId = Number(item?.ruleId || 0)
            const segments = []

            if (lineage?.subject) {
                segments.push(this.sharedNodeTitle(ruleId, 'subject', lineage.subject))
            }
            if (lineage?.topic) {
                segments.push(this.sharedNodeTitle(ruleId, 'topic', lineage.topic))
            }
            if (lineage?.unit) {
                segments.push(this.sharedNodeTitle(ruleId, 'unit', lineage.unit))
            }

            return segments
                .map((value) => String(value || '').trim())
                .filter((value) => value !== '')
                .join(' / ')
        },
        emitSharedInsertDraft(item, level, node, lineage = {}) {
            const ruleId = Number(item?.ruleId || 0)
            const normalizedLevel = String(level || '').trim().toLowerCase()
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!['subject', 'topic', 'unit'].includes(normalizedLevel)) return
            if (!this.canShowSharedInsertButton(normalizedLevel)) return

            const nodeId = Number(node?.id || 0)
            const label = this.sharedNodeTitle(ruleId, normalizedLevel, node)

            this.$emit('open-shared-insert-draft', {
                ruleId,
                level: normalizedLevel,
                targetId: Number.isFinite(nodeId) && nodeId > 0 ? nodeId : null,
                label: String(label || node?.name || 'Element').trim() || 'Element',
                parentLabel: this.sharedInsertParentLabel(item, lineage),
                nodeData: node,
            })
        },
        emitSharedMaterialInsertDraft(item, material, lineage = {}) {
            const ruleId = Number(item?.ruleId || 0)
            const materialId = Number(material?.id || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!this.canShowSharedInsertButton('material')) return

            const sourceTopicId = Number(lineage?.topic?.id || 0)
            const sourceUnitId = Number(lineage?.unit?.id || 0)

            this.$emit('open-shared-insert-draft', {
                ruleId,
                level: 'material',
                targetId: Number.isFinite(materialId) && materialId > 0 ? materialId : null,
                label: String(material?.title || 'Material').trim() || 'Material',
                parentLabel: this.sharedInsertParentLabel(item, lineage),
                sourceTopicId: sourceTopicId > 0 ? sourceTopicId : null,
                sourceUnitId: sourceUnitId > 0 ? sourceUnitId : null,
            })
        },
        openSharedMaterial(item, material) {
            const ruleId = Number(item?.ruleId)
            const materialId = Number(material?.id)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!Number.isFinite(materialId) || materialId <= 0) return

            this.$emit('open-shared-material', {
                ruleId,
                material,
            })
        },
        openSharedAttachments(item, material) {
            const ruleId = Number(item?.ruleId)
            const materialId = Number(material?.id)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!Number.isFinite(materialId) || materialId <= 0) return

            this.$emit('open-shared-attachments', {
                ruleId,
                material,
            })
        },
        previewStructureCreate() {
            return null
        },
        emitSharedCreate(item, level, node, lineage = {}) {
            const ruleId = Number(item?.ruleId)
            const nodeId = Number(node?.id)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!Number.isFinite(nodeId) || nodeId <= 0) return

            const normalizedLevel = String(level || '').trim()
            if (!this.sharedNodeCanAddMaterial(item, normalizedLevel)) return

            const subject = normalizedLevel === 'subject' ? node : lineage?.subject
            const topic = normalizedLevel === 'topic' ? node : lineage?.topic
            const unit = normalizedLevel === 'unit' ? node : null

            this.$emit('open-create', {
                level: normalizedLevel,
                subject: String(subject?.name || '').trim(),
                topic: String(topic?.name || '').trim(),
                unit: String(unit?.name || '').trim(),
                sharedRuleId: ruleId,
                sharedNodeLevel: normalizedLevel,
                sharedNodeId: nodeId,
            })
        },
        sharedScopeRank(scopeType) {
            const normalized = String(scopeType || '').trim().toLowerCase()
            if (normalized === 'all') return 1
            if (normalized === 'subject') return 2
            if (normalized === 'topic') return 3
            if (normalized === 'unit') return 4
            if (normalized === 'material') return 5
            return 0
        },
        sharedScopeLevel(scopeType) {
            const normalized = String(scopeType || '').trim().toLowerCase()
            if (normalized === 'all') return 'all'
            if (normalized === 'subject') return 'subject'
            if (normalized === 'topic') return 'topic'
            if (normalized === 'unit') return 'unit'
            if (normalized === 'material') return 'material'
            return ''
        },
        sharedNodeWithinScope(item, level) {
            const shareScopeLevel = this.sharedScopeLevel(this.sharedItemScopeType(item))
            const shareScopeRank = this.sharedScopeRank(shareScopeLevel)
            const nodeScopeRank = this.sharedScopeRank(level)
            if (shareScopeRank <= 0 || nodeScopeRank <= 0) return false
            return nodeScopeRank >= shareScopeRank
        },
        sharedNodeCanStructureEdit(item, level) {
            return this.sharedItemCanStructureEdit(item) && this.sharedNodeWithinScope(item, level)
        },
        sharedNodeCanAddMaterial(item, level) {
            return this.sharedItemCanAddMaterial(item) && this.sharedNodeWithinScope(item, level)
        },
        sharedNodeCanStructureDelete(item, level) {
            return this.sharedItemHasFullAccess(item) && this.sharedNodeWithinScope(item, level)
        },
        sharedNodeIsContextOnly(item, level) {
            return !this.sharedNodeWithinScope(item, level)
        },
        handleSharedItemShareClick(item) {
            const level = this.sharedScopeLevel(this.sharedItemScopeType(item))
            if (level === '') return

            const label = String(item?.scopeObjectLabel || item?.scopeLabel || 'Freigabe').trim()
            if (level === 'all') {
                this.handleShareClick({
                    level: 'all',
                    id: null,
                    label: label || 'Workspace',
                    parentLabel: '',
                })
                return
            }

            const scopeId = Number(item?.scopeId || item?.scope_id || 0)
            if (!Number.isFinite(scopeId) || scopeId <= 0) return

            this.handleShareClick({
                level,
                id: scopeId,
                label,
                parentLabel: String(item?.scopePathLabel || '').trim(),
            })
        },
        handleShareClick(target) {
            this.$emit('open-share', target)
        },
        handleWorkspaceShareClick() {
            this.$emit('open-share', {
                level: 'all',
                id: null,
                label: 'Workspace',
                parentLabel: '',
            })
        },
    },
}
</script>

<style scoped>
.overview-subjects-tree {
    --overview-root-gap: 32px;
}

.overview-subjects-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 8px;
}

.overview-subjects-tree > .overview-subjects-list {
    gap: var(--overview-root-gap);
}

.overview-workspace-row {
    margin-bottom: var(--overview-root-gap);
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(140, 30, 55, 0.40);
    border-left: 4px solid rgba(140, 30, 55, 0.65);
    background: linear-gradient(90deg, rgba(140, 30, 55, 0.18) 0%, rgba(140, 30, 55, 0.09) 56%, rgba(255, 255, 255, 0.80) 100%);
    box-shadow: 0 3px 12px rgba(100, 20, 40, 0.14);
}

.overview-shared-row {
    margin-bottom: 0;
    background: linear-gradient(90deg, rgba(140, 30, 55, 0.14) 0%, rgba(140, 30, 55, 0.07) 56%, rgba(255, 255, 255, 0.78) 100%);
}

.overview-shared-row--spaced {
    margin-top: 40px;
}

.overview-shared-row--archive {
    margin-top: 16px;
}

.overview-subjects-list--child {
    margin-top: 6px;
    margin-left: 34px;
    padding-left: 20px;
    border-left: 1px dashed rgba(35, 61, 76, 0.25);
}

.overview-subjects-group {
    margin-left: 20px;
    border: 1px solid rgba(38, 128, 75, 0.25);
    border-left: 4px solid rgba(38, 128, 75, 0.55);
    border-radius: 8px;
    padding: 10px 12px;
    background: rgba(235, 248, 241, 0.55);
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

.overview-subjects-node--clickable {
    cursor: pointer;
}

.overview-subjects-node-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
}

.overview-subjects-node-toggle:disabled {
    cursor: default;
    opacity: 0.6;
}

.overview-subjects-node-toggle--empty {
    cursor: default;
    opacity: 0.35;
    pointer-events: none;
}

.overview-share-btn {
    min-width: auto;
}

.overview-share-btn--material {
    min-width: 22px !important;
    width: 22px;
    height: 22px;
}

.overview-subjects-node--subject {
    font-weight: 700;
    background: rgba(38, 128, 75, 0.13);
    color: #1a5c38;
    border: 1px solid rgba(38, 128, 75, 0.25);
}

.overview-subjects-node--workspace {
    font-weight: 800;
    font-size: 1.02rem;
    letter-spacing: 0.01em;
    color: #5a0d1e;
    background: rgba(140, 30, 55, 0.18);
    border: 1px solid rgba(140, 30, 55, 0.40);
}

.overview-subjects-node--workspace-toggle {
    border: 1px solid rgba(140, 30, 55, 0.40);
    cursor: pointer;
}

.overview-subjects-node--workspace-toggle:disabled {
    cursor: default;
    opacity: 0.7;
}

.overview-subjects-node--shared-toggle {
    background: rgba(140, 30, 55, 0.15);
    border-color: rgba(140, 30, 55, 0.36);
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
    gap: 5px;
}

.overview-subjects-material-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(35, 61, 76, 0.92);
    font-size: 0.92rem;
    line-height: 1.32;
    flex-wrap: wrap;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(40, 58, 80, 0.15);
    border-left: 3px solid rgba(31, 95, 191, 0.35);
    border-radius: 7px;
    padding: 5px 10px 5px 9px;
    box-shadow: 0 1px 3px rgba(40, 58, 80, 0.07);
    transition: box-shadow 0.15s ease;
}

.overview-subjects-material-item:hover {
    box-shadow: 0 2px 8px rgba(40, 58, 80, 0.12);
    border-left-color: rgba(31, 95, 191, 0.6);
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

.overview-subjects-material-link--readonly {
    cursor: default;
    text-decoration: none;
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

.overview-shared-content {
    margin-top: 10px;
    padding: 16px 18px;
    border-radius: 12px;
    border: 1px dashed rgba(53, 84, 117, 0.28);
    background: rgba(53, 84, 117, 0.06);
    color: #2e4a5a;
    font-weight: 600;
}

.overview-shared-items {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    justify-content: flex-start;
    gap: 12px;
}

.overview-shared-item {
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(53, 84, 117, 0.16);
    background: rgba(255, 255, 255, 0.72);
}

.overview-shared-item--disabled {
    opacity: 0.48;
    filter: saturate(0.3);
    pointer-events: none;
}

.overview-shared-item-actions {
    margin-top: 10px;
    display: flex;
    justify-content: flex-end;
}

.overview-shared-hierarchy {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed rgba(53, 84, 117, 0.24);
}

.overview-shared-structure-toggle-row {
    margin-bottom: 10px;
}

.overview-shared-structure-create-row {
    margin: 6px 0 8px 8px;
}

.overview-shared-structure-create-row--bottom {
    margin-top: 10px;
}

.overview-shared-structure-create-row--topic {
    margin-left: 24px;
}

.overview-shared-structure-create-row--unit {
    margin-left: 40px;
}

.overview-shared-structure-create-row--topic-bottom {
    margin-top: 12px;
    margin-left: 88px;
}

.overview-shared-structure-create-row--subject-bottom {
    margin-top: 8px;
    margin-left: 88px;
}

.overview-shared-structure-create-button {
    min-width: 32px;
    width: 32px;
    height: 32px;
    border-radius: 8px;
}

.overview-shared-structure-create-button--subject {
    min-width: 76px;
    width: auto;
    padding-inline: 10px;
}

.overview-shared-hierarchy-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 10px;
}

.overview-shared-hierarchy-item {
    min-width: 0;
}

.overview-shared-hierarchy-node {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 10px;
}

.overview-shared-hierarchy-node--context {
    opacity: 0.72;
}

.overview-shared-hierarchy-node--readonly {
    opacity: 0.82;
}

.overview-shared-hierarchy-node-main {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
}

.overview-shared-hierarchy-node-inline-actions {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-left: 4px;
}

.overview-shared-hierarchy-node-inline-actions--material {
    gap: 4px;
    margin-left: 8px;
    flex-wrap: wrap;
}

.overview-shared-item-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}

.overview-shared-item-head-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.overview-shared-node-action {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
}

.overview-shared-insert-btn {
    text-transform: none;
    letter-spacing: 0.01em;
    font-weight: 600;
    color: #2e6ea4 !important;
}

.overview-shared-insert-btn :deep(.v-btn__content),
.overview-shared-insert-btn :deep(.v-icon) {
    color: #2e6ea4 !important;
}

.overview-shared-item-title {
    font-weight: 700;
    color: #17384a;
}

.overview-shared-item-path,
.overview-shared-item-meta,
.overview-shared-state {
    margin-top: 4px;
    color: #3a5668;
    font-size: 0.92rem;
    font-weight: 500;
}

.overview-shared-state--error {
    color: #a52626;
}
</style>
