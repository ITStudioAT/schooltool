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
                        <v-chip
                            v-if="item.permissionLabel"
                            size="x-small"
                            variant="flat"
                            :color="linkedPermissionChipColor(item.permission)">
                            {{ item.permissionLabel }}
                        </v-chip>
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
                        <ul v-else class="overview-shared-hierarchy-list">
                            <li
                                v-for="subject in sharedItemHierarchy(item)"
                                :key="`overview-shared-subject-${item.ruleId}-${subject.id || subject.name}`"
                                class="overview-shared-hierarchy-item">
                                <div class="overview-subjects-node overview-subjects-node--subject overview-shared-hierarchy-node">
                                    <v-icon size="16" icon="mdi-book-education-outline" class="mr-2" />
                                    <span>{{ subject.name }}</span>
                                </div>
                                <ul v-if="Array.isArray(subject.materials) && subject.materials.length" class="overview-subjects-material-list">
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
                                        <div class="overview-subjects-node overview-subjects-node--topic">
                                            <v-icon size="14" icon="mdi-book-open-page-variant-outline" class="mr-2" />
                                            <span>{{ topic.name }}</span>
                                        </div>
                                        <ul v-if="Array.isArray(topic.materials) && topic.materials.length" class="overview-subjects-material-list">
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
                                                <div class="overview-subjects-node overview-subjects-node--unit">
                                                    <v-icon size="13" icon="mdi-bookmark-outline" class="mr-2" />
                                                    <span>{{ unit.name }}</span>
                                                </div>

                                                <ul v-if="unit.materials.length" class="overview-subjects-material-list">
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
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
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
    },
    emits: ['open-material', 'open-share', 'open-create', 'open-attachments', 'open-shared-material', 'open-shared-attachments', 'unlink-linked-material', 'unlink-linked-topic', 'unlink-linked-unit'],
    data() {
        return {
            workspaceExpanded: true,
            sharedForMeExpanded: false,
            expandedSharedItems: {},
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

            this.sharedForMeExpanded = !this.sharedForMeExpanded
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

            this.expandedSharedItems = {
                ...this.expandedSharedItems,
                [key]: !this.isSharedItemExpanded(ruleId),
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
    display: inline-flex;
}

.overview-shared-item-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
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
