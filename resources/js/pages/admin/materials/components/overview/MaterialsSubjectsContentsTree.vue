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
                v-for="subject in items"
                :key="`overview-subjects-subject-${subject.id || subject.name}`"
                class="overview-subjects-item">
                <div class="overview-subjects-group" :style="subjectGroupStyleFn(subject)">
                        <div class="overview-subjects-node-row">
                            <div class="overview-subjects-node overview-subjects-node--subject">
                                <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                                <span>{{ subject.name }}</span>
                            <v-icon
                                v-if="hasPersistedNodeId(subject.id) && showShareIndicator('subject', subject.id)"
                                size="16"
                                icon="mdi-share-variant"
                                :color="shareIndicatorColorFn('subject', subject.id)"
                                class="ml-1" />
                        </div>
                        <v-btn
                            v-if="hasPersistedNodeId(subject.id)"
                            size="x-small"
                            color="primary"
                            variant="tonal"
                            icon="mdi-share-variant-outline"
                            class="overview-share-btn"
                            :title="'Teilen'"
                            :disabled="actionBusy"
                            @click.stop="handleShareClick({ level: 'subject', id: subject.id, label: subject.name })" />
                        <v-btn
                            v-if="enableCreateButtons && hasPersistedNodeId(subject.id)"
                            size="x-small"
                            color="primary"
                            variant="tonal"
                            icon="mdi-file-plus-outline"
                            :title="'Neues Material in Fach anlegen'"
                            :disabled="actionBusy"
                            @click="$emit('open-create', {
                                level: 'subject',
                                subject: String(subject.name || '').trim(),
                                topic: '',
                                unit: '',
                            })" />
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
                                    parentLabel: subject.name,
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

                    <ul v-if="subject.topics.length" class="overview-subjects-list overview-subjects-list--child">
                        <li
                            v-for="topic in subject.topics"
                            :key="`overview-subjects-topic-${topic.id || `${subject.id || subject.name}-${topic.name}`}`"
                            class="overview-subjects-item overview-subjects-topic-group"
                            :style="topicGroupStyleFn(subject)">
                            <div class="overview-subjects-node-row">
                                <div class="overview-subjects-node overview-subjects-node--topic">
                                    <v-icon
                                        size="14"
                                        :icon="topic.isLinked ? 'mdi-link-variant' : 'mdi-book-open-page-variant-outline'"
                                        :color="topic.isLinked ? linkedPermissionChipColor(topic.linkedPermission) : undefined"
                                        class="mr-2" />
                                    <span>{{ topic.name }}</span>
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
                                </div>
                                <v-btn
                                    v-if="hasPersistedNodeId(topic.id)"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    icon="mdi-share-variant-outline"
                                    class="overview-share-btn"
                                    :title="'Teilen'"
                                    :disabled="actionBusy"
                                    @click.stop="handleShareClick({ level: 'topic', id: topic.id, label: topic.name, parentLabel: subject.name })" />
                                <v-btn
                                    v-if="enableCreateButtons && hasPersistedNodeId(topic.id) && canCreateMaterialInTopic(topic)"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    icon="mdi-file-plus-outline"
                                    :title="'Neues Material in Thema anlegen'"
                                    :disabled="actionBusy"
                                    @click="$emit('open-create', {
                                        level: 'topic',
                                        subject: String(subject.name || '').trim(),
                                        topic: String(topic.name || '').trim(),
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
                                            parentLabel: `${subject.name} / ${topic.name}`,
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

                            <ul v-if="topic.units.length" class="overview-subjects-list overview-subjects-list--child">
                                <li
                                    v-for="unit in topic.units"
                                    :key="`overview-subjects-unit-${unit.id || `${topic.id || topic.name}-${unit.name}`}`"
                                    class="overview-subjects-item">
                                    <div class="overview-subjects-node-row">
                                        <div class="overview-subjects-node overview-subjects-node--unit">
                                            <v-icon
                                                size="13"
                                                :icon="unit.isLinked ? 'mdi-link-variant' : 'mdi-circle-medium'"
                                                :color="unit.isLinked ? linkedPermissionChipColor(unit.linkedPermission) : undefined"
                                                class="mr-1" />
                                            <span>{{ unit.name }}</span>
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
                                        </div>
                                        <v-btn
                                            v-if="hasPersistedNodeId(unit.id)"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal"
                                            icon="mdi-share-variant-outline"
                                            class="overview-share-btn"
                                            :title="'Teilen'"
                                            :disabled="actionBusy"
                                            @click.stop="handleShareClick({ level: 'unit', id: unit.id, label: unit.name, parentLabel: `${subject.name} / ${topic.name}` })" />
                                        <v-btn
                                            v-if="enableCreateButtons && hasPersistedNodeId(unit.id) && canCreateMaterialInUnit(unit)"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal"
                                            icon="mdi-file-plus-outline"
                                            :title="'Neues Material in Unterpunkt anlegen'"
                                            :disabled="actionBusy"
                                            @click="$emit('open-create', {
                                                level: 'unit',
                                                subject: String(subject.name || '').trim(),
                                                topic: String(topic.name || '').trim(),
                                                unit: String(unit.name || '').trim(),
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
                                                    parentLabel: `${subject.name} / ${topic.name} / ${unit.name}`,
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
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>

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
                    :class="{ 'overview-shared-item--expanded': isSharedItemExpanded(item.ruleId) }"
                    :style="sharedItemCardStyle(item.ruleId)">
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
                    <div v-if="item.scopePathLabel" class="overview-shared-item-path">
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
                            :disabled="actionBusy"
                            @click="toggleSharedItemExpanded(item.ruleId)">
                            {{ isSharedItemExpanded(item.ruleId) ? 'Schließen' : 'Anzeigen' }}
                        </v-btn>
                    </div>
                    <div v-if="isSharedItemExpanded(item.ruleId)" class="overview-shared-hierarchy">
                        <div v-if="!sharedItemHierarchy(item).length" class="overview-shared-state">
                            Keine Fachstruktur für diese Freigabe vorhanden.
                        </div>
                        <div
                            v-if="sharedItemHierarchy(item).length && sharedItemHasFullAccess(item)"
                            class="overview-shared-structure-toggle-row">
                            <v-btn
                                size="small"
                                variant="tonal"
                                color="primary"
                                :disabled="actionBusy"
                                @click="toggleSharedStructureButtons(item.ruleId)">
                                {{ isSharedStructureButtonsVisible(item.ruleId) ? 'Fach/Themen schließen' : 'Struktur ändern' }}
                            </v-btn>
                        </div>
                        <ul v-if="sharedItemHierarchy(item).length" class="overview-shared-hierarchy-list">
                            <li
                                v-for="subject in sharedItemHierarchy(item)"
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
                                <div class="overview-subjects-node overview-subjects-node--subject overview-shared-hierarchy-node">
                                    <div class="overview-shared-hierarchy-node-main">
                                        <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                                        <span>{{ sharedNodeTitle(item.ruleId, 'subject', subject) }}</span>
                                        <div
                                            v-if="sharedItemHasFullAccess(item) && isSharedStructureButtonsVisible(item.ruleId)"
                                            class="overview-shared-hierarchy-node-inline-actions">
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
                                                v-if="sharedSubjectCanDelete(subject)"
                                                icon="mdi-delete-outline"
                                                size="x-small"
                                                density="comfortable"
                                                variant="text"
                                                color="warning"
                                                :disabled="actionBusy"
                                                title="Fach löschen"
                                                @click.stop="openSharedDeleteDialog(item, 'subject', subject)" />
                                        </div>
                                    </div>
                                    <div
                                        v-if="sharedItemHasFullAccess(item) && !isSharedStructureButtonsVisible(item.ruleId)"
                                        class="overview-shared-node-actions">
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
                                <ul
                                    v-if="Array.isArray(subject.materials) && subject.materials.length && !isSharedStructureButtonsVisible(item.ruleId)"
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
                                    </li>
                                </ul>

                                <ul v-if="subject.topics.length" class="overview-subjects-list overview-subjects-list--child">
                                    <li
                                        v-for="topic in subject.topics"
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
                                        <div class="overview-subjects-node overview-subjects-node--topic overview-shared-hierarchy-node">
                                            <div class="overview-shared-hierarchy-node-main">
                                                <v-icon size="14" icon="mdi-book-open-page-variant-outline" class="mr-2" />
                                                <span>{{ sharedNodeTitle(item.ruleId, 'topic', topic) }}</span>
                                                <div
                                                    v-if="sharedItemHasFullAccess(item) && isSharedStructureButtonsVisible(item.ruleId)"
                                                    class="overview-shared-hierarchy-node-inline-actions">
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
                                                        v-if="sharedTopicCanDelete(topic)"
                                                        icon="mdi-delete-outline"
                                                        size="x-small"
                                                        density="comfortable"
                                                        variant="text"
                                                        color="warning"
                                                        :disabled="actionBusy"
                                                        title="Thema löschen"
                                                        @click.stop="openSharedDeleteDialog(item, 'topic', topic)" />
                                                </div>
                                            </div>
                                            <div
                                                v-if="sharedItemHasFullAccess(item) && !isSharedStructureButtonsVisible(item.ruleId)"
                                                class="overview-shared-node-actions">
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
                                        <ul
                                            v-if="Array.isArray(topic.materials) && topic.materials.length && !isSharedStructureButtonsVisible(item.ruleId)"
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
                                            </li>
                                        </ul>

                                        <ul v-if="topic.units.length" class="overview-subjects-list overview-subjects-list--child">
                                            <li
                                                v-for="unit in topic.units"
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
                                                <div class="overview-subjects-node overview-subjects-node--unit overview-shared-hierarchy-node">
                                                    <div class="overview-shared-hierarchy-node-main">
                                                        <v-icon size="13" icon="mdi-bookmark-outline" class="mr-2" />
                                                        <span>{{ sharedNodeTitle(item.ruleId, 'unit', unit) }}</span>
                                                        <div
                                                            v-if="sharedItemHasFullAccess(item) && isSharedStructureButtonsVisible(item.ruleId)"
                                                            class="overview-shared-hierarchy-node-inline-actions">
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
                                                                v-if="sharedUnitCanDelete(unit)"
                                                                icon="mdi-delete-outline"
                                                                size="x-small"
                                                                density="comfortable"
                                                                variant="text"
                                                                color="warning"
                                                                :disabled="actionBusy"
                                                                title="Bereich löschen"
                                                                @click.stop="openSharedDeleteDialog(item, 'unit', unit)" />
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-if="sharedItemHasFullAccess(item) && !isSharedStructureButtonsVisible(item.ruleId)"
                                                        class="overview-shared-node-actions">
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

                                                <ul
                                                    v-if="unit.materials.length && !isSharedStructureButtonsVisible(item.ruleId)"
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
                                <div v-if="sharedItemSupportsTopicCreate(item) && isSharedStructureButtonsVisible(item.ruleId)" class="overview-shared-structure-create-row overview-shared-structure-create-row--subject-bottom">
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
                            v-if="sharedItemSupportsSubjectCreate(item) && sharedItemHierarchy(item).length && isSharedStructureButtonsVisible(item.ruleId)"
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
        ruleId: null,
        beforeSubjectId: null,
        title: '',
    }
}

function createSharedCreateTopicDialogState() {
    return {
        open: false,
        ruleId: null,
        subjectId: null,
        beforeTopicId: null,
        title: '',
    }
}

function createSharedCreateUnitDialogState() {
    return {
        open: false,
        ruleId: null,
        topicId: null,
        beforeUnitId: null,
        title: '',
    }
}

function createSharedDeleteDialogState() {
    return {
        open: false,
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
        expandedSharedItems: {
            type: Object,
            default: () => ({}),
        },
    },
    emits: ['open-material', 'open-share', 'open-create', 'open-attachments', 'open-shared-material', 'open-shared-attachments', 'unlink-linked-material', 'unlink-linked-topic', 'unlink-linked-unit', 'toggle-shared-for-me-expanded', 'toggle-shared-item-expanded', 'shared-node-created', 'shared-node-renamed', 'shared-node-deleted'],
    data() {
        return {
            workspaceExpanded: true,
            sharedNodeTitleOverrides: {},
            sharedStructureButtonsVisible: {},
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
        }
    },
    methods: {
        linkedPermissionChipColor(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'error'
            if (normalized === 'read_write') return 'warning'
            return 'primary'
        },
        toggleWorkspaceExpanded() {
            if (this.actionBusy) return

            this.workspaceExpanded = !this.workspaceExpanded
        },
        toggleSharedForMeExpanded() {
            if (this.actionBusy) return

            this.$emit('toggle-shared-for-me-expanded')
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
        toggleSharedItemExpanded(ruleId) {
            if (this.actionBusy) return

            const key = this.sharedItemKey(ruleId)
            if (key === '') return

            this.$emit('toggle-shared-item-expanded', ruleId)
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
            return this.sharedItemHierarchy(item).length > 0
        },
        sharedItemHasFullAccess(item) {
            return String(item?.permission || '').trim() === 'full_access'
        },
        sharedItemScopeType(item) {
            return String(item?.scopeType || item?.scope_type || 'all').trim().toLowerCase()
        },
        sharedItemSupportsSubjectCreate(item) {
            return this.sharedItemHasFullAccess(item) && this.sharedItemScopeType(item) === 'all'
        },
        sharedItemSupportsTopicCreate(item) {
            const scopeType = this.sharedItemScopeType(item)
            return this.sharedItemHasFullAccess(item) && (scopeType === 'all' || scopeType === 'subject')
        },
        sharedItemSupportsUnitCreate(item) {
            const scopeType = this.sharedItemScopeType(item)
            return this.sharedItemHasFullAccess(item) && (scopeType === 'all' || scopeType === 'subject')
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
            if (!this.sharedItemHasFullAccess(item)) return

            const overrideKey = this.sharedNodeOverrideKey(item?.ruleId, level, node)
            if (overrideKey === '') return

            this.sharedRenameDialog = {
                open: true,
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
            if (!this.sharedItemHasFullAccess(item)) return

            const ruleId = Number(item?.ruleId || 0)
            const nodeId = Number(node?.id || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!Number.isFinite(nodeId) || nodeId <= 0) return

            this.sharedDeleteDialog = {
                open: true,
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
        async submitSharedCreateSubjectDialog() {
            if (!this.validateSharedCreateSubjectDialog()) return
            if (this.sharedCreateSubjectDialogSaving) return

            const normalizedTitle = String(this.sharedCreateSubjectDialog?.title || '').trim()
            const ruleId = Number(this.sharedCreateSubjectDialog?.ruleId || 0)
            const beforeSubjectId = Number(this.sharedCreateSubjectDialog?.beforeSubjectId || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) {
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

                const response = await axios.post('/api/admin/materials/shares/inbox/subjects', {
                    rule_id: ruleId,
                    data: subjectPayload,
                })
                const subjectId = Number(response?.data?.data?.id || 0)
                const savedTitle = String(response?.data?.data?.name || normalizedTitle).trim() || normalizedTitle

                this.$emit('shared-node-created', {
                    ruleId,
                    level: 'subject',
                    nodeId: subjectId,
                    name: savedTitle,
                })
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
            if (!Number.isFinite(ruleId) || ruleId <= 0 || !Number.isFinite(subjectId) || subjectId <= 0) {
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

                const response = await axios.post('/api/admin/materials/shares/inbox/topics', {
                    rule_id: ruleId,
                    data: topicPayload,
                })
                const topicId = Number(response?.data?.data?.id || 0)
                const savedTitle = String(response?.data?.data?.name || normalizedTitle).trim() || normalizedTitle

                this.$emit('shared-node-created', {
                    ruleId,
                    level: 'topic',
                    nodeId: topicId,
                    name: savedTitle,
                    parentSubjectId: subjectId,
                })
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
            if (!Number.isFinite(ruleId) || ruleId <= 0 || !Number.isFinite(topicId) || topicId <= 0) {
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

                const response = await axios.post('/api/admin/materials/shares/inbox/units', {
                    rule_id: ruleId,
                    data: unitPayload,
                })
                const unitId = Number(response?.data?.data?.id || 0)
                const savedTitle = String(response?.data?.data?.name || normalizedTitle).trim() || normalizedTitle

                this.$emit('shared-node-created', {
                    ruleId,
                    level: 'unit',
                    nodeId: unitId,
                    name: savedTitle,
                    parentTopicId: topicId,
                })
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
            const endpoint = this.sharedRenameEndpoint(this.sharedRenameDialog?.level, this.sharedRenameDialog?.nodeId)
            const ruleId = Number(this.sharedRenameDialog?.ruleId || 0)
            if (endpoint === '' || !Number.isFinite(ruleId) || ruleId <= 0) {
                this.sharedRenameDialogError = 'Element konnte nicht gespeichert werden.'
                return
            }

            this.sharedRenameDialogSaving = true

            try {
                const response = await axios.put(endpoint, {
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

                this.$emit('shared-node-renamed', {
                    ruleId,
                    level: String(this.sharedRenameDialog?.level || ''),
                    nodeId: Number(this.sharedRenameDialog?.nodeId || 0),
                    name: savedTitle,
                })
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

            const endpoint = this.sharedDeleteEndpoint(this.sharedDeleteDialog?.level, this.sharedDeleteDialog?.nodeId)
            const ruleId = Number(this.sharedDeleteDialog?.ruleId || 0)
            const nodeId = Number(this.sharedDeleteDialog?.nodeId || 0)
            if (endpoint === '' || !Number.isFinite(ruleId) || ruleId <= 0 || !Number.isFinite(nodeId) || nodeId <= 0) {
                this.sharedDeleteDialogError = 'Element konnte nicht gelöscht werden.'
                return
            }

            this.sharedDeleteDialogDeleting = true

            try {
                await axios.delete(endpoint, {
                    data: {
                        rule_id: ruleId,
                    },
                })

                this.$emit('shared-node-deleted', {
                    ruleId,
                    level: String(this.sharedDeleteDialog?.level || ''),
                    nodeId,
                })
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
    border: 1px solid rgba(20, 93, 120, 0.46);
    background: linear-gradient(90deg, rgba(20, 93, 120, 0.24) 0%, rgba(20, 93, 120, 0.14) 56%, rgba(255, 255, 255, 0.78) 100%);
    box-shadow: 0 3px 10px rgba(20, 56, 74, 0.14);
}

.overview-shared-row {
    margin-bottom: 0;
    background: linear-gradient(90deg, rgba(53, 84, 117, 0.18) 0%, rgba(53, 84, 117, 0.1) 56%, rgba(255, 255, 255, 0.78) 100%);
}

.overview-shared-row--spaced {
    margin-top: 40px;
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
    background: rgba(35, 61, 76, 0.08);
}

.overview-subjects-node--workspace {
    font-weight: 800;
    font-size: 1.02rem;
    letter-spacing: 0.01em;
    color: #0f3140;
    background: rgba(20, 93, 120, 0.26);
    border: 1px solid rgba(20, 93, 120, 0.48);
}

.overview-subjects-node--workspace-toggle {
    border: 1px solid rgba(20, 93, 120, 0.48);
    cursor: pointer;
}

.overview-subjects-node--workspace-toggle:disabled {
    cursor: default;
    opacity: 0.7;
}

.overview-subjects-node--shared-toggle {
    background: rgba(53, 84, 117, 0.2);
    border-color: rgba(53, 84, 117, 0.4);
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

.overview-shared-node-actions {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    flex-shrink: 0;
    justify-content: flex-end;
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
