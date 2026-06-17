<template>
    <div class="overview-subjects-tree">
        <div class="overview-subjects-top-nav d-flex align-center flex-wrap ga-2 mb-6">
            <v-btn-toggle :model-value="activeSection" density="comfortable" color="primary" variant="outlined" divided mandatory class="overview-top-toggle">
                <v-btn
                    value="workspace"
                    prepend-icon="mdi-briefcase-outline"
                    :disabled="actionBusy"
                    @click="toggleWorkspaceExpanded">
                    Workspace
                    <span v-if="workspaceMaterialsCount > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--toggle">
                        {{ workspaceMaterialsCount }}
                    </span>
                </v-btn>
                <v-btn
                    value="shared"
                    prepend-icon="mdi-account-group-outline"
                    :disabled="actionBusy"
                    @click="toggleSharedForMeExpanded">
                    Für mich geteilt
                    <span v-if="sharedMaterialsCount > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--toggle">
                        {{ sharedMaterialsCount }}
                    </span>
                </v-btn>
                <v-btn
                    value="archive"
                    prepend-icon="mdi-archive-outline"
                    :disabled="actionBusy"
                    @click="toggleSharedForMeArchiveExpanded">
                    Archiv
                    <span v-if="archivedMaterialsCount > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--toggle">
                        {{ archivedMaterialsCount }}
                    </span>
                </v-btn>
            </v-btn-toggle>

        </div>

        <v-card v-if="isWorkspaceSectionActive" variant="flat" rounded="lg" class="overview-unit-card overview-unit-card--workspace mb-4 pa-4 pt-0">
        <div class="overview-unit-card__header d-flex align-center ga-2">
            <v-icon size="20" icon="mdi-briefcase-outline" color="primary" />
            <span class="overview-selected-subject__label">Workspace</span>
            <v-btn
                v-if="enableCreateButtons && hasPersistedNodeId(activeWorkspace?.id) && workspaceNodeHasContents('workspace', activeWorkspace)"
                size="x-small"
                color="warning"
                variant="tonal"
                icon="mdi-delete-outline"
                :title="'Workspace leeren'"
                :disabled="actionBusy"
                @click.stop="openWorkspaceDeleteDialog('workspace', activeWorkspace)" />
            <v-btn
                size="x-small"
                color="primary"
                variant="tonal"
                icon="mdi-share-variant-outline"
                :title="'Teilen'"
                :disabled="actionBusy"
                @click.stop="handleWorkspaceShareClick" />
        </div>

        <div class="overview-subjects-tabbar mb-10">
            <div class="overview-subjects-tablist" role="tablist" aria-label="Fächer">
                <div
                    v-for="subject in items"
                    :key="`overview-subjects-nav-${subject.id || subject.name}`"
                    role="tab"
                    :aria-selected="isWorkspaceSubjectExpanded(subject) ? 'true' : 'false'"
                    :class="[
                        'overview-subjects-tab',
                        'overview-subjects-tab--subject',
                        'overview-subjects-tab--hierarchy',
                        {
                            'overview-subjects-tab--active': isWorkspaceSubjectExpanded(subject),
                            'overview-subjects-tab--muted': selectedSubjectItem && !isWorkspaceSubjectExpanded(subject),
                        },
                    ]">
                    <v-btn
                        :value="workspaceSubjectKey(subject)"
                        size="default"
                        :variant="isWorkspaceSubjectExpanded(subject) ? 'tonal' : 'outlined'"
                        :color="isWorkspaceSubjectExpanded(subject) ? 'primary' : undefined"
                        :disabled="actionBusy"
                        class="overview-subjects-nav-btn overview-subjects-tab-button"
                        @click="toggleWorkspaceSubjectExpanded(subject)">
                        {{ workspaceNodeTitle('subject', subject) }}
                    </v-btn>

                    <v-menu
                        v-if="isWorkspaceSubjectExpanded(subject) && hasPersistedNodeId(subject.id) && hasWorkspaceSubjectTabActions(subject)"
                        location="bottom end">
                        <template #activator="{ props: subjectMenuActivatorProps }">
                            <v-btn
                                v-bind="subjectMenuActivatorProps"
                                size="small"
                                variant="tonal"
                                color="primary"
                                icon="mdi-dots-vertical"
                                :title="'Fach-Aktionen'"
                                :disabled="actionBusy"
                                class="overview-subjects-tab-menu-btn"
                                @click.stop />
                        </template>

                        <v-list density="comfortable" class="overview-subjects-tab-menu">
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-pencil"
                                :disabled="actionBusy"
                                title="Fach bearbeiten"
                                @click="openWorkspaceRenameDialog('subject', subject)" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-arrow-left"
                                :disabled="actionBusy || selectedSubjectIndex <= 0"
                                title="Nach links"
                                @click="moveWorkspaceNode('subject', subject, 'up')" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-arrow-right"
                                :disabled="actionBusy || selectedSubjectIndex >= items.length - 1"
                                title="Nach rechts"
                                @click="moveWorkspaceNode('subject', subject, 'down')" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-delete-outline"
                                :disabled="actionBusy"
                                title="Fach löschen"
                                @click="openWorkspaceDeleteDialog('subject', subject)" />
                            <v-list-item
                                v-if="!isWorkspaceStructureButtonsVisible()"
                                prepend-icon="mdi-share-variant-outline"
                                :disabled="actionBusy"
                                title="Teilen"
                                @click="handleShareClick({ level: 'subject', id: subject.id, label: workspaceNodeTitle('subject', subject) })" />
                        </v-list>
                    </v-menu>
                </div>
                <div
                    v-if="enableCreateButtons"
                    role="tab"
                    aria-selected="false"
                    class="overview-subjects-tab overview-subjects-tab--add">
                    <v-btn
                        size="default"
                        variant="outlined"
                        color="primary"
                        icon="mdi-plus"
                        :title="'Fach hinzufügen'"
                        :disabled="actionBusy"
                        class="overview-subjects-nav-btn overview-subjects-tab-button overview-subjects-add-tab-button"
                        @click.stop="openWorkspaceCreateSubjectDialog()" />
                </div>
            </div>
        </div>

        <v-card v-if="selectedSubjectItem" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
        <div class="overview-unit-card__header overview-unit-card__header--subject overview-unit-card__header--workspace-node d-flex align-center ga-2">
            <span class="overview-selected-subject__label">{{ workspaceNodeTitle('subject', selectedSubjectItem) }}</span>
        </div>

        <div v-if="selectedSubjectItem.materials.length && !isWorkspaceStructureButtonsVisible()" class="overview-materials-cards d-flex flex-wrap ga-3 mb-4">
            <v-card
                v-for="material in selectedSubjectItem.materials"
                :key="`overview-material-card-subject-${material.id}`"
                class="overview-material-card"
                variant="outlined"
                rounded="lg"
                @click="$emit('open-material', { id: material.id })">
                <v-card-text class="pa-3">
                    <div class="d-flex align-center ga-2 mb-2">
                        <v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" />
                        <span class="overview-material-card__title">{{ material.title }}</span>
                    </div>
                    <div v-if="material.description" class="overview-material-card__description text-medium-emphasis mb-1">{{ material.description }}</div>
                    <div class="d-flex align-center flex-wrap ga-2">
                        <v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip>
                        <v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip>
                        <span v-if="material.attachmentsCount > 0" class="d-flex align-center text-caption text-medium-emphasis">
                            <v-icon size="12" icon="mdi-paperclip" class="mr-1" />{{ material.attachmentsCount }}
                        </span>
                    </div>
                </v-card-text>
            </v-card>
        </div>

        <div class="overview-subjects-tabbar mb-10">
            <div class="overview-subjects-tablist" role="tablist" aria-label="Themen">
                <div
                    v-for="topic in selectedSubjectItem.topics"
                    :key="`overview-topics-nav-${topic.id || topic.name}`"
                    role="tab"
                    :aria-selected="isWorkspaceTopicExpanded(topic) ? 'true' : 'false'"
                    :class="[
                        'overview-subjects-tab',
                        'overview-subjects-tab--topic',
                        'overview-subjects-tab--hierarchy',
                        {
                            'overview-subjects-tab--active': isWorkspaceTopicExpanded(topic),
                            'overview-subjects-tab--muted': selectedTopicItem && !isWorkspaceTopicExpanded(topic),
                        },
                    ]">
                    <v-btn
                        :value="workspaceTopicKey(topic)"
                        size="default"
                        :variant="isWorkspaceTopicExpanded(topic) ? 'tonal' : 'outlined'"
                        :color="isWorkspaceTopicExpanded(topic) ? 'primary' : undefined"
                        :disabled="actionBusy"
                        class="overview-subjects-nav-btn overview-subjects-tab-button"
                        @click="toggleWorkspaceTopicExpanded(topic)">
                        {{ workspaceNodeTitle('topic', topic) }}
                    </v-btn>

                    <v-menu
                        v-if="isWorkspaceTopicExpanded(topic) && hasPersistedNodeId(topic.id) && hasWorkspaceTopicTabActions(topic)"
                        location="bottom end">
                        <template #activator="{ props: topicMenuActivatorProps }">
                            <v-btn
                                v-bind="topicMenuActivatorProps"
                                size="small"
                                variant="tonal"
                                color="primary"
                                icon="mdi-dots-vertical"
                                :title="'Thema-Aktionen'"
                                :disabled="actionBusy"
                                class="overview-subjects-tab-menu-btn"
                                @click.stop />
                        </template>

                        <v-list density="comfortable" class="overview-subjects-tab-menu">
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-pencil"
                                :disabled="actionBusy"
                                title="Thema bearbeiten"
                                @click="openWorkspaceRenameDialog('topic', topic)" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-arrow-left"
                                :disabled="actionBusy || selectedTopicIndex <= 0"
                                title="Nach links"
                                @click="moveWorkspaceNode('topic', topic, 'up')" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-arrow-right"
                                :disabled="actionBusy || selectedTopicIndex >= selectedSubjectItem.topics.length - 1"
                                title="Nach rechts"
                                @click="moveWorkspaceNode('topic', topic, 'down')" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-delete-outline"
                                :disabled="actionBusy"
                                title="Thema löschen"
                                @click="openWorkspaceDeleteDialog('topic', topic)" />
                            <v-list-item
                                v-if="!isWorkspaceStructureButtonsVisible()"
                                prepend-icon="mdi-share-variant-outline"
                                :disabled="actionBusy"
                                title="Teilen"
                                @click="handleShareClick({ level: 'topic', id: topic.id, label: workspaceNodeTitle('topic', topic), parentLabel: selectedSubjectItem ? workspaceNodeTitle('subject', selectedSubjectItem) : '' })" />
                        </v-list>
                    </v-menu>
                </div>
                <div
                    v-if="enableCreateButtons"
                    role="tab"
                    aria-selected="false"
                    class="overview-subjects-tab overview-subjects-tab--add">
                    <v-btn
                        size="default"
                        variant="outlined"
                        color="primary"
                        icon="mdi-plus"
                        :title="'Thema hinzufügen'"
                        :disabled="actionBusy"
                        class="overview-subjects-nav-btn overview-subjects-tab-button overview-subjects-add-tab-button"
                        @click.stop="openWorkspaceCreateTopicDialog(selectedSubjectItem)" />
                </div>
            </div>
        </div>

        <v-card v-if="selectedTopicItem" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
        <div class="overview-unit-card__header overview-unit-card__header--subject overview-unit-card__header--workspace-node d-flex align-center ga-2">
            <span class="overview-selected-subject__label">{{ workspaceNodeTitle('topic', selectedTopicItem) }}</span>
        </div>

        <div v-if="selectedTopicItem.materials.length && !isWorkspaceStructureButtonsVisible()" class="overview-materials-cards d-flex flex-wrap ga-3 mb-4">
            <v-card
                v-for="material in selectedTopicItem.materials"
                :key="`overview-material-card-topic-${material.id}`"
                class="overview-material-card"
                variant="outlined"
                rounded="lg"
                @click="$emit('open-material', { id: material.id })">
                <v-card-text class="pa-3">
                    <div class="d-flex align-center ga-2 mb-2">
                        <v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" />
                        <span class="overview-material-card__title">{{ material.title }}</span>
                    </div>
                    <div v-if="material.description" class="overview-material-card__description text-medium-emphasis mb-1">{{ material.description }}</div>
                    <div class="d-flex align-center flex-wrap ga-2">
                        <v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip>
                        <v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip>
                        <span v-if="material.attachmentsCount > 0" class="d-flex align-center text-caption text-medium-emphasis">
                            <v-icon size="12" icon="mdi-paperclip" class="mr-1" />{{ material.attachmentsCount }}
                        </span>
                    </div>
                </v-card-text>
            </v-card>
        </div>

        <div v-if="selectedTopicItem" class="overview-subjects-tabbar mb-10">
            <div class="overview-subjects-tablist" role="tablist" aria-label="Bereiche">
                <div
                    v-for="unit in selectedTopicItem.units"
                    :key="`overview-units-nav-${unit.id || unit.name}`"
                    role="tab"
                    :aria-selected="isWorkspaceUnitExpanded(unit) ? 'true' : 'false'"
                    :class="[
                        'overview-subjects-tab',
                        'overview-subjects-tab--unit',
                        'overview-subjects-tab--hierarchy',
                        {
                            'overview-subjects-tab--active': isWorkspaceUnitExpanded(unit),
                            'overview-subjects-tab--muted': selectedUnitItem && !isWorkspaceUnitExpanded(unit),
                        },
                    ]">
                    <v-btn
                        :value="workspaceUnitKey(unit)"
                        size="default"
                        :variant="isWorkspaceUnitExpanded(unit) ? 'tonal' : 'outlined'"
                        :color="isWorkspaceUnitExpanded(unit) ? 'primary' : undefined"
                        :disabled="actionBusy"
                        class="overview-subjects-nav-btn overview-subjects-tab-button"
                        @click="toggleWorkspaceUnitExpanded(unit)">
                        {{ workspaceNodeTitle('unit', unit) }}
                    </v-btn>

                    <v-menu
                        v-if="isWorkspaceUnitExpanded(unit) && hasPersistedNodeId(unit.id) && hasWorkspaceUnitTabActions(unit)"
                        location="bottom end">
                        <template #activator="{ props: unitMenuActivatorProps }">
                            <v-btn
                                v-bind="unitMenuActivatorProps"
                                size="small"
                                variant="tonal"
                                color="primary"
                                icon="mdi-dots-vertical"
                                :title="'Bereich-Aktionen'"
                                :disabled="actionBusy"
                                class="overview-subjects-tab-menu-btn"
                                @click.stop />
                        </template>

                        <v-list density="comfortable" class="overview-subjects-tab-menu">
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-pencil"
                                :disabled="actionBusy"
                                title="Bereich bearbeiten"
                                @click="openWorkspaceRenameDialog('unit', unit)" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-arrow-left"
                                :disabled="actionBusy || selectedUnitIndex <= 0"
                                title="Nach links"
                                @click="moveWorkspaceNode('unit', unit, 'up')" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-arrow-right"
                                :disabled="actionBusy || selectedUnitIndex >= selectedTopicItem.units.length - 1"
                                title="Nach rechts"
                                @click="moveWorkspaceNode('unit', unit, 'down')" />
                            <v-list-item
                                v-if="enableCreateButtons"
                                prepend-icon="mdi-delete-outline"
                                :disabled="actionBusy"
                                title="Bereich löschen"
                                @click="openWorkspaceDeleteDialog('unit', unit)" />
                            <v-list-item
                                v-if="!isWorkspaceStructureButtonsVisible()"
                                prepend-icon="mdi-share-variant-outline"
                                :disabled="actionBusy"
                                title="Teilen"
                                @click="handleShareClick({ level: 'unit', id: unit.id, label: workspaceNodeTitle('unit', unit), parentLabel: `${selectedSubjectItem ? workspaceNodeTitle('subject', selectedSubjectItem) : ''} / ${selectedTopicItem ? workspaceNodeTitle('topic', selectedTopicItem) : ''}` })" />
                        </v-list>
                    </v-menu>
                </div>
                <div
                    v-if="enableCreateButtons"
                    role="tab"
                    aria-selected="false"
                    class="overview-subjects-tab overview-subjects-tab--add">
                    <v-btn
                        size="default"
                        variant="outlined"
                        color="primary"
                        icon="mdi-plus"
                        :title="'Bereich hinzufügen'"
                        :disabled="actionBusy"
                        class="overview-subjects-nav-btn overview-subjects-tab-button overview-subjects-add-tab-button"
                        @click.stop="openWorkspaceCreateUnitDialog(selectedTopicItem)" />
                </div>
            </div>
        </div>

        <v-card v-if="selectedUnitItem" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
        <div class="overview-unit-card__header overview-unit-card__header--subject overview-unit-card__header--workspace-node d-flex align-center ga-2">
            <span class="overview-selected-subject__label">{{ workspaceNodeTitle('unit', selectedUnitItem) }}</span>
        </div>

        <div v-if="!isWorkspaceStructureButtonsVisible() && (selectedUnitItem.materials.length || canShowUnitMaterialCreateCard)" class="overview-materials-cards d-flex flex-wrap ga-3">
            <v-card
                v-for="material in selectedUnitItem.materials"
                :key="`overview-material-card-unit-${material.id}`"
                class="overview-material-card"
                variant="outlined"
                rounded="lg"
                @click="$emit('open-material', { id: material.id })">
                <v-card-text class="pa-3">
                    <div class="d-flex align-center ga-2 mb-2">
                        <v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" />
                        <span class="overview-material-card__title">{{ material.title }}</span>
                    </div>
                    <div v-if="material.description" class="overview-material-card__description text-medium-emphasis mb-1">{{ material.description }}</div>
                    <div class="d-flex align-center flex-wrap ga-2">
                        <v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip>
                        <v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip>
                        <span v-if="material.attachmentsCount > 0" class="d-flex align-center text-caption text-medium-emphasis">
                            <v-icon size="12" icon="mdi-paperclip" class="mr-1" />{{ material.attachmentsCount }}
                        </span>
                    </div>
                </v-card-text>
            </v-card>
            <v-card
                v-if="canShowUnitMaterialCreateCard"
                class="overview-material-card overview-material-card--create"
                :class="{ 'overview-material-card--disabled': actionBusy }"
                variant="outlined"
                rounded="lg"
                role="button"
                :tabindex="actionBusy ? -1 : 0"
                :aria-disabled="actionBusy ? 'true' : 'false'"
                aria-label="Neues Material in Bereich anlegen"
                @click="emitWorkspaceUnitMaterialCreate(selectedUnitItem)"
                @keydown.enter.prevent="emitWorkspaceUnitMaterialCreate(selectedUnitItem)"
                @keydown.space.prevent="emitWorkspaceUnitMaterialCreate(selectedUnitItem)">
                <v-card-text class="overview-material-card-create__content">
                    <v-icon icon="mdi-plus" size="32" color="primary" />
                </v-card-text>
            </v-card>
        </div>
        </v-card>
        </v-card>
        </v-card>
        </v-card>


        <div v-if="isSharedSectionActive" class="overview-shared-content">
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
                        Von: {{ sharedItemSenderLabel(item) }}
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
                            v-if="sharedItemCanInsert(item)"
                            size="small"
                            variant="flat"
                            color="primary"
                            prepend-icon="mdi-tray-arrow-down"
                            class="overview-shared-insert-btn"
                            :disabled="actionBusy || sharedItemIsDisabled(item.ruleId)"
                            @click.stop="emitSharedItemInsertDraft(item)">
                            Einordnen
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
                            v-if="sharedItemCanInsert(item)"
                            size="small"
                            variant="flat"
                            color="primary"
                            prepend-icon="mdi-tray-arrow-down"
                            class="overview-shared-insert-btn"
                            :disabled="actionBusy || sharedItemIsDisabled(item.ruleId)"
                            @click.stop="emitSharedItemInsertDraft(item)">
                            Einordnen
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
                    <div v-if="isSharedItemExpanded(item.ruleId)" class="overview-shared-hierarchy">
                        <div v-if="!sharedItemHierarchy(item).length" class="overview-shared-state">
                            Keine Fachstruktur für diese Freigabe vorhanden.
                        </div>
                        <div v-if="sharedItemHierarchy(item).length" class="overview-subjects-nav d-flex align-center flex-wrap ga-2 mb-12">
                            <v-btn
                                v-for="subject in sharedItemHierarchy(item)"
                                :key="`shared-subject-nav-${item.ruleId}-${subject.id || subject.name}`"
                                size="default"
                                :variant="isSharedSubjectSelected(item.ruleId, subject) ? 'tonal' : 'outlined'"
                                :color="isSharedSubjectSelected(item.ruleId, subject) ? 'primary' : undefined"
                                prepend-icon="mdi-book-education-outline"
                                :disabled="actionBusy"
                                class="overview-subjects-nav-btn"
                                @click="selectSharedSubject(item.ruleId, subject)">
                                {{ sharedNodeTitle(item.ruleId, 'subject', subject) }}<span v-if="nodeMaterialCount(subject) > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--button">{{ nodeMaterialCount(subject) }}</span>
                            </v-btn>
                            <v-btn
                                v-if="sharedItemSupportsSubjectCreate(item)"
                                size="default"
                                variant="text"
                                color="primary"
                                icon="mdi-plus"
                                :title="'Fach hinzufügen'"
                                :disabled="actionBusy"
                                class="overview-subjects-nav-btn"
                                @click.stop="openSharedCreateSubjectDialog(item)" />
                        </div>

                        <v-card v-if="selectedSharedSubjectObj(item.ruleId, item)" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
                            <div class="overview-unit-card__header overview-unit-card__header--subject d-flex align-center ga-2">
                                <span class="text-caption text-medium-emphasis font-weight-regular">Fach:</span>
                                <v-icon size="20" icon="mdi-book-education-outline" color="primary" />
                                <span class="overview-selected-subject__label">{{ sharedNodeTitle(item.ruleId, 'subject', selectedSharedSubjectObj(item.ruleId, item)) }}</span>
                                <template v-if="sharedNodeCanStructureEdit(item, 'subject')">
                                    <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-pencil" :title="'Fach bearbeiten'" :disabled="actionBusy" @click.stop="openSharedRenameDialog(item, 'subject', selectedSharedSubjectObj(item.ruleId, item))" />
                                    <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-arrow-left" :title="'Nach links'" :disabled="actionBusy || selectedSharedSubjectIdx(item.ruleId, item) <= 0" @click.stop="moveSharedNode(item, 'subject', selectedSharedSubjectObj(item.ruleId, item), 'up')" />
                                    <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-arrow-right" :title="'Nach rechts'" :disabled="actionBusy || selectedSharedSubjectIdx(item.ruleId, item) >= sharedItemHierarchy(item).length - 1" @click.stop="moveSharedNode(item, 'subject', selectedSharedSubjectObj(item.ruleId, item), 'down')" />
                                    <v-btn v-if="sharedNodeCanStructureDelete(item, 'subject')" size="x-small" color="warning" variant="tonal" icon="mdi-delete-outline" :title="'Fach löschen'" :disabled="actionBusy" @click.stop="openSharedDeleteDialog(item, 'subject', selectedSharedSubjectObj(item.ruleId, item))" />
                                </template>
                                <v-btn v-if="canShowSharedInsertButton('subject')" size="x-small" color="primary" variant="flat" prepend-icon="mdi-tray-arrow-down" class="overview-shared-insert-btn" :disabled="actionBusy" @click.stop="emitSharedInsertDraft(item, 'subject', selectedSharedSubjectObj(item.ruleId, item))">Einordnen</v-btn>
                            </div>

                            <div v-if="selectedSharedSubjectObj(item.ruleId, item).materials.length" class="overview-materials-cards d-flex flex-wrap ga-3 mb-4">
                                <v-card v-for="material in selectedSharedSubjectObj(item.ruleId, item).materials" :key="`shared-material-card-subject-${material.id}`" class="overview-material-card" variant="outlined" rounded="lg" @click="openSharedMaterial(item, material)">
                                    <v-card-text class="pa-3">
                                        <div class="d-flex align-center ga-2 mb-2"><v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" /><span class="overview-material-card__title">{{ material.title }}</span></div>
                                        <div class="d-flex align-center flex-wrap ga-2"><v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip><v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip><v-btn v-if="canShowSharedInsertButton('material')" size="x-small" color="primary" variant="tonal" prepend-icon="mdi-tray-arrow-down" @click.stop="emitSharedMaterialInsertDraft(item, material, { subject: selectedSharedSubjectObj(item.ruleId, item) })">Einordnen</v-btn></div>
                                    </v-card-text>
                                </v-card>
                            </div>

                            <div v-if="selectedSharedSubjectObj(item.ruleId, item).topics.length" class="overview-subjects-nav d-flex align-center flex-wrap ga-2 mb-12">
                                <v-btn v-for="topic in selectedSharedSubjectObj(item.ruleId, item).topics" :key="`shared-topic-nav-${item.ruleId}-${topic.id || topic.name}`" size="default" :variant="isSharedTopicSelected(item.ruleId, topic) ? 'tonal' : 'outlined'" :color="isSharedTopicSelected(item.ruleId, topic) ? 'primary' : undefined" prepend-icon="mdi-book-open-page-variant-outline" :disabled="actionBusy" class="overview-subjects-nav-btn" @click="selectSharedTopic(item.ruleId, topic)">{{ sharedNodeTitle(item.ruleId, 'topic', topic) }}<span v-if="nodeMaterialCount(topic) > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--button">{{ nodeMaterialCount(topic) }}</span></v-btn>
                                <v-btn v-if="sharedItemSupportsTopicCreate(item)" size="default" variant="text" color="primary" icon="mdi-plus" :title="'Thema hinzufügen'" :disabled="actionBusy" class="overview-subjects-nav-btn" @click.stop="openSharedCreateTopicDialog(item, selectedSharedSubjectObj(item.ruleId, item))" />
                            </div>

                            <v-card v-if="selectedSharedTopicObj(item.ruleId, item)" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
                                <div class="overview-unit-card__header overview-unit-card__header--subject d-flex align-center ga-2">
                                    <span class="text-caption text-medium-emphasis font-weight-regular">Thema:</span>
                                    <v-icon size="20" icon="mdi-book-open-page-variant-outline" color="primary" />
                                    <span class="overview-selected-subject__label">{{ sharedNodeTitle(item.ruleId, 'topic', selectedSharedTopicObj(item.ruleId, item)) }}</span>
                                    <template v-if="sharedNodeCanStructureEdit(item, 'topic')">
                                        <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-pencil" :title="'Thema bearbeiten'" :disabled="actionBusy" @click.stop="openSharedRenameDialog(item, 'topic', selectedSharedTopicObj(item.ruleId, item))" />
                                        <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-arrow-left" :title="'Nach links'" :disabled="actionBusy || selectedSharedTopicIdx(item.ruleId, item) <= 0" @click.stop="moveSharedNode(item, 'topic', selectedSharedTopicObj(item.ruleId, item), 'up')" />
                                        <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-arrow-right" :title="'Nach rechts'" :disabled="actionBusy || selectedSharedTopicIdx(item.ruleId, item) >= selectedSharedSubjectObj(item.ruleId, item).topics.length - 1" @click.stop="moveSharedNode(item, 'topic', selectedSharedTopicObj(item.ruleId, item), 'down')" />
                                        <v-btn v-if="sharedNodeCanStructureDelete(item, 'topic')" size="x-small" color="warning" variant="tonal" icon="mdi-delete-outline" :title="'Thema löschen'" :disabled="actionBusy" @click.stop="openSharedDeleteDialog(item, 'topic', selectedSharedTopicObj(item.ruleId, item))" />
                                    </template>
                                    <v-btn v-if="canShowSharedInsertButton('topic')" size="x-small" color="primary" variant="flat" prepend-icon="mdi-tray-arrow-down" class="overview-shared-insert-btn" :disabled="actionBusy" @click.stop="emitSharedInsertDraft(item, 'topic', selectedSharedTopicObj(item.ruleId, item), { subject: selectedSharedSubjectObj(item.ruleId, item) })">Einordnen</v-btn>
                                </div>

                                <div v-if="selectedSharedTopicObj(item.ruleId, item).materials.length" class="overview-materials-cards d-flex flex-wrap ga-3 mb-4">
                                    <v-card v-for="material in selectedSharedTopicObj(item.ruleId, item).materials" :key="`shared-material-card-topic-${material.id}`" class="overview-material-card" variant="outlined" rounded="lg" @click="openSharedMaterial(item, material)">
                                        <v-card-text class="pa-3">
                                            <div class="d-flex align-center ga-2 mb-2"><v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" /><span class="overview-material-card__title">{{ material.title }}</span></div>
                                            <div class="d-flex align-center flex-wrap ga-2"><v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip><v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip><v-btn v-if="canShowSharedInsertButton('material')" size="x-small" color="primary" variant="tonal" prepend-icon="mdi-tray-arrow-down" @click.stop="emitSharedMaterialInsertDraft(item, material, { subject: selectedSharedSubjectObj(item.ruleId, item), topic: selectedSharedTopicObj(item.ruleId, item) })">Einordnen</v-btn></div>
                                        </v-card-text>
                                    </v-card>
                                </div>

                                <div v-if="selectedSharedTopicObj(item.ruleId, item).units.length" class="overview-subjects-nav d-flex align-center flex-wrap ga-2 mb-12">
                                <v-btn v-for="unit in selectedSharedTopicObj(item.ruleId, item).units" :key="`shared-unit-nav-${item.ruleId}-${unit.id || unit.name}`" size="default" :variant="isSharedUnitSelected(item.ruleId, unit) ? 'tonal' : 'outlined'" :color="isSharedUnitSelected(item.ruleId, unit) ? 'primary' : undefined" prepend-icon="mdi-bookmark-outline" :disabled="actionBusy" class="overview-subjects-nav-btn" @click="selectSharedUnit(item.ruleId, unit)">{{ sharedNodeTitle(item.ruleId, 'unit', unit) }}<span v-if="nodeMaterialCount(unit) > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--button">{{ nodeMaterialCount(unit) }}</span></v-btn>
                                    <v-btn v-if="sharedItemSupportsUnitCreate(item)" size="default" variant="text" color="primary" icon="mdi-plus" :title="'Bereich hinzufügen'" :disabled="actionBusy" class="overview-subjects-nav-btn" @click.stop="openSharedCreateUnitDialog(item, selectedSharedTopicObj(item.ruleId, item))" />
                                </div>

                                <v-card v-if="selectedSharedUnitObj(item.ruleId, item)" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
                                    <div class="overview-unit-card__header overview-unit-card__header--subject d-flex align-center ga-2">
                                        <span class="text-caption text-medium-emphasis font-weight-regular">Einheit:</span>
                                        <span class="overview-selected-subject__label">{{ sharedNodeTitle(item.ruleId, 'unit', selectedSharedUnitObj(item.ruleId, item)) }}</span>
                                        <template v-if="sharedNodeCanStructureEdit(item, 'unit')">
                                            <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-pencil" :title="'Bereich bearbeiten'" :disabled="actionBusy" @click.stop="openSharedRenameDialog(item, 'unit', selectedSharedUnitObj(item.ruleId, item))" />
                                            <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-arrow-left" :title="'Nach links'" :disabled="actionBusy || selectedSharedUnitIdx(item.ruleId, item) <= 0" @click.stop="moveSharedNode(item, 'unit', selectedSharedUnitObj(item.ruleId, item), 'up')" />
                                            <v-btn size="x-small" color="primary" variant="tonal" icon="mdi-arrow-right" :title="'Nach rechts'" :disabled="actionBusy || selectedSharedUnitIdx(item.ruleId, item) >= selectedSharedTopicObj(item.ruleId, item).units.length - 1" @click.stop="moveSharedNode(item, 'unit', selectedSharedUnitObj(item.ruleId, item), 'down')" />
                                            <v-btn v-if="sharedNodeCanStructureDelete(item, 'unit')" size="x-small" color="warning" variant="tonal" icon="mdi-delete-outline" :title="'Bereich löschen'" :disabled="actionBusy" @click.stop="openSharedDeleteDialog(item, 'unit', selectedSharedUnitObj(item.ruleId, item))" />
                                        </template>
                                        <v-btn v-if="sharedNodeCanAddMaterial(item, 'unit')" size="x-small" color="primary" variant="tonal" icon="mdi-file-plus-outline" :title="'Neues Material in Bereich anlegen'" :disabled="actionBusy" @click.stop="emitSharedCreate(item, 'unit', selectedSharedUnitObj(item.ruleId, item), { subject: selectedSharedSubjectObj(item.ruleId, item), topic: selectedSharedTopicObj(item.ruleId, item) })" />
                                        <v-btn v-if="canShowSharedInsertButton('unit')" size="x-small" color="primary" variant="flat" prepend-icon="mdi-tray-arrow-down" class="overview-shared-insert-btn" :disabled="actionBusy" @click.stop="emitSharedInsertDraft(item, 'unit', selectedSharedUnitObj(item.ruleId, item), { subject: selectedSharedSubjectObj(item.ruleId, item), topic: selectedSharedTopicObj(item.ruleId, item) })">Einordnen</v-btn>
                                    </div>

                                    <div v-if="selectedSharedUnitObj(item.ruleId, item).materials.length" class="overview-materials-cards d-flex flex-wrap ga-3 mb-4">
                                        <v-card v-for="material in selectedSharedUnitObj(item.ruleId, item).materials" :key="`shared-material-card-unit-${material.id}`" class="overview-material-card" variant="outlined" rounded="lg" @click="openSharedMaterial(item, material)">
                                            <v-card-text class="pa-3">
                                                <div class="d-flex align-center ga-2 mb-2"><v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" /><span class="overview-material-card__title">{{ material.title }}</span></div>
                                                <div class="d-flex align-center flex-wrap ga-2"><v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip><v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip><v-btn v-if="canShowSharedInsertButton('material')" size="x-small" color="primary" variant="tonal" prepend-icon="mdi-tray-arrow-down" @click.stop="emitSharedMaterialInsertDraft(item, material, { subject: selectedSharedSubjectObj(item.ruleId, item), topic: selectedSharedTopicObj(item.ruleId, item), unit: selectedSharedUnitObj(item.ruleId, item) })">Einordnen</v-btn></div>
                                            </v-card-text>
                                        </v-card>
                                    </div>
                                </v-card>
                            </v-card>
                        </v-card>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isArchiveSectionActive" class="overview-shared-content overview-shared-content--archive">
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
                        Von: {{ sharedItemSenderLabel(item) }}
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
                        <div v-if="sharedItemHierarchy(item).length" class="overview-subjects-nav d-flex align-center flex-wrap ga-2 mb-12">
                            <v-btn
                                v-for="subject in sharedItemHierarchy(item)"
                                :key="`archived-subject-nav-${item.ruleId}-${subject.id || subject.name}`"
                                size="default"
                                :variant="isArchivedSubjectSelected(item.ruleId, subject) ? 'tonal' : 'outlined'"
                                :color="isArchivedSubjectSelected(item.ruleId, subject) ? 'primary' : undefined"
                                prepend-icon="mdi-book-education-outline"
                                :disabled="actionBusy"
                                class="overview-subjects-nav-btn"
                                @click="selectArchivedSubject(item.ruleId, subject)">
                                {{ sharedNodeTitle(item.ruleId, 'subject', subject) }}<span v-if="nodeMaterialCount(subject) > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--button">{{ nodeMaterialCount(subject) }}</span>
                            </v-btn>
                        </div>

                        <v-card v-if="selectedArchivedSubjectObj(item.ruleId, item)" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
                            <div class="overview-unit-card__header overview-unit-card__header--subject d-flex align-center ga-2">
                                <span class="text-caption text-medium-emphasis font-weight-regular">Fach:</span>
                                <v-icon size="20" icon="mdi-book-education-outline" color="primary" />
                                <span class="overview-selected-subject__label">{{ sharedNodeTitle(item.ruleId, 'subject', selectedArchivedSubjectObj(item.ruleId, item)) }}</span>
                            </div>

                            <div v-if="selectedArchivedSubjectObj(item.ruleId, item).materials.length" class="overview-materials-cards d-flex flex-wrap ga-3 mb-4">
                                <v-card v-for="material in selectedArchivedSubjectObj(item.ruleId, item).materials" :key="`archived-material-card-subject-${material.id}`" class="overview-material-card" variant="outlined" rounded="lg">
                                    <v-card-text class="pa-3">
                                        <div class="d-flex align-center ga-2 mb-2"><v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" /><span class="overview-material-card__title">{{ material.title }}</span></div>
                                        <div class="d-flex align-center flex-wrap ga-2"><v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip><v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip></div>
                                    </v-card-text>
                                </v-card>
                            </div>

                            <div v-if="selectedArchivedSubjectObj(item.ruleId, item).topics.length" class="overview-subjects-nav d-flex align-center flex-wrap ga-2 mb-12">
                                <v-btn v-for="topic in selectedArchivedSubjectObj(item.ruleId, item).topics" :key="`archived-topic-nav-${item.ruleId}-${topic.id || topic.name}`" size="default" :variant="isArchivedTopicSelected(item.ruleId, topic) ? 'tonal' : 'outlined'" :color="isArchivedTopicSelected(item.ruleId, topic) ? 'primary' : undefined" prepend-icon="mdi-book-open-page-variant-outline" :disabled="actionBusy" class="overview-subjects-nav-btn" @click="selectArchivedTopic(item.ruleId, topic)">{{ sharedNodeTitle(item.ruleId, 'topic', topic) }}<span v-if="nodeMaterialCount(topic) > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--button">{{ nodeMaterialCount(topic) }}</span></v-btn>
                            </div>

                            <v-card v-if="selectedArchivedTopicObj(item.ruleId, item)" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
                                <div class="overview-unit-card__header overview-unit-card__header--subject d-flex align-center ga-2">
                                    <span class="text-caption text-medium-emphasis font-weight-regular">Thema:</span>
                                    <v-icon size="20" icon="mdi-book-open-page-variant-outline" color="primary" />
                                    <span class="overview-selected-subject__label">{{ sharedNodeTitle(item.ruleId, 'topic', selectedArchivedTopicObj(item.ruleId, item)) }}</span>
                                </div>

                                <div v-if="selectedArchivedTopicObj(item.ruleId, item).materials.length" class="overview-materials-cards d-flex flex-wrap ga-3 mb-4">
                                    <v-card v-for="material in selectedArchivedTopicObj(item.ruleId, item).materials" :key="`archived-material-card-topic-${material.id}`" class="overview-material-card" variant="outlined" rounded="lg">
                                        <v-card-text class="pa-3">
                                            <div class="d-flex align-center ga-2 mb-2"><v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" /><span class="overview-material-card__title">{{ material.title }}</span></div>
                                            <div class="d-flex align-center flex-wrap ga-2"><v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip><v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip></div>
                                        </v-card-text>
                                    </v-card>
                                </div>

                                <div v-if="selectedArchivedTopicObj(item.ruleId, item).units.length" class="overview-subjects-nav d-flex align-center flex-wrap ga-2 mb-12">
                                <v-btn v-for="unit in selectedArchivedTopicObj(item.ruleId, item).units" :key="`archived-unit-nav-${item.ruleId}-${unit.id || unit.name}`" size="default" :variant="isArchivedUnitSelected(item.ruleId, unit) ? 'tonal' : 'outlined'" :color="isArchivedUnitSelected(item.ruleId, unit) ? 'primary' : undefined" prepend-icon="mdi-bookmark-outline" :disabled="actionBusy" class="overview-subjects-nav-btn" @click="selectArchivedUnit(item.ruleId, unit)">{{ sharedNodeTitle(item.ruleId, 'unit', unit) }}<span v-if="nodeMaterialCount(unit) > 0" aria-hidden="true" class="overview-subjects-material-count overview-subjects-material-count--button">{{ nodeMaterialCount(unit) }}</span></v-btn>
                                </div>

                                <v-card v-if="selectedArchivedUnitObj(item.ruleId, item)" variant="outlined" rounded="lg" class="overview-unit-card mb-4 pa-4 pt-0">
                                    <div class="overview-unit-card__header overview-unit-card__header--subject d-flex align-center ga-2">
                                        <span class="text-caption text-medium-emphasis font-weight-regular">Einheit:</span>
                                        <span class="overview-selected-subject__label">{{ sharedNodeTitle(item.ruleId, 'unit', selectedArchivedUnitObj(item.ruleId, item)) }}</span>
                                    </div>

                                    <div v-if="selectedArchivedUnitObj(item.ruleId, item).materials.length" class="overview-materials-cards d-flex flex-wrap ga-3 mb-4">
                                        <v-card v-for="material in selectedArchivedUnitObj(item.ruleId, item).materials" :key="`archived-material-card-unit-${material.id}`" class="overview-material-card" variant="outlined" rounded="lg">
                                            <v-card-text class="pa-3">
                                                <div class="d-flex align-center ga-2 mb-2"><v-icon size="20" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || 'primary'" /><span class="overview-material-card__title">{{ material.title }}</span></div>
                                                <div class="d-flex align-center flex-wrap ga-2"><v-chip v-if="material.typeLabel" size="x-small" variant="outlined" :color="material.typeColor || 'primary'">{{ material.typeLabel }}</v-chip><v-chip size="x-small" variant="tonal" :color="statusColorFn(material.status)">{{ statusLabelFn(material.status) }}</v-chip></div>
                                            </v-card-text>
                                        </v-card>
                                    </div>
                                </v-card>
                            </v-card>
                        </v-card>
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
                    <div v-if="sharedDeleteDialog.hasContents" class="text-body-2 text-medium-emphasis">
                        Dieses Element enthält Unterelemente oder Materialien. Diese werden beim Löschen unwiderruflich mitgelöscht.
                    </div>
                    <div v-else class="text-body-2 text-medium-emphasis">
                        Wirklich löschen?
                    </div>
                    <v-checkbox
                        v-if="sharedDeleteDialog.hasContents"
                        v-model="sharedDeleteDialog.cascade"
                        color="error"
                        density="comfortable"
                        hide-details
                        class="mt-2"
                        :disabled="actionBusy || sharedDeleteDialogDeleting"
                        label="Ja, alle enthaltenen Elemente und Materialien ebenfalls löschen" />
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
                        :disabled="actionBusy || (sharedDeleteDialog.hasContents && !sharedDeleteDialog.cascade)"
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
        hasContents: false,
        cascade: false,
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
        activeWorkspace: {
            type: Object,
            default: null,
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
        workspaceSelection: {
            type: Object,
            default: null,
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
            collapsedWorkspaceSubjects: {},
            selectedSubjectKey: null,
            selectedTopicKey: null,
            selectedUnitKey: null,
            collapsedWorkspaceTopics: {},
            collapsedWorkspaceUnits: {},
            collapsedSharedSubjects: {},
            collapsedSharedTopics: {},
            collapsedSharedUnits: {},
            collapsedArchivedSubjects: {},
            collapsedArchivedTopics: {},
            collapsedArchivedUnits: {},
            selectedSharedSubjectKeys: {},
            selectedSharedTopicKeys: {},
            selectedSharedUnitKeys: {},
            selectedArchivedSubjectKeys: {},
            selectedArchivedTopicKeys: {},
            selectedArchivedUnitKeys: {},
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
    computed: {
        activeSection() {
            if (this.sharedForMeExpanded) return 'shared'
            if (this.sharedForMeArchiveExpanded) return 'archive'
            if (this.workspaceExpanded) return 'workspace'
            return undefined
        },
        isWorkspaceSectionActive() {
            return this.activeSection === 'workspace'
        },
        isSharedSectionActive() {
            return this.activeSection === 'shared'
        },
        isArchiveSectionActive() {
            return this.activeSection === 'archive'
        },
        workspaceMaterialsCount() {
            return this.sectionMaterialCount(this.items)
        },
        sharedMaterialsCount() {
            return this.cardsMaterialCount(this.sharedObjectsForMe)
        },
        archivedMaterialsCount() {
            return this.cardsMaterialCount(this.archivedSharedObjectsForMe)
        },
        selectedSubjectItem() {
            if (!this.items?.length) return null
            return this.items.find(s => this.isWorkspaceSubjectExpanded(s)) || null
        },
        selectedSubjectIndex() {
            if (!this.items?.length || !this.selectedSubjectItem) return -1
            return this.items.indexOf(this.selectedSubjectItem)
        },
        selectedTopicItem() {
            const subject = this.selectedSubjectItem
            if (!subject?.topics?.length) return null
            return subject.topics.find(t => this.isWorkspaceTopicExpanded(t)) || null
        },
        selectedTopicIndex() {
            const subject = this.selectedSubjectItem
            if (!subject?.topics?.length || !this.selectedTopicItem) return -1
            return subject.topics.indexOf(this.selectedTopicItem)
        },
        selectedUnitItem() {
            const topic = this.selectedTopicItem
            if (!topic?.units?.length) return null
            return topic.units.find(u => this.isWorkspaceUnitExpanded(u)) || null
        },
        selectedUnitIndex() {
            const topic = this.selectedTopicItem
            if (!topic?.units?.length || !this.selectedUnitItem) return -1
            return topic.units.indexOf(this.selectedUnitItem)
        },
        canShowUnitMaterialCreateCard() {
            return this.enableCreateButtons === true
                && this.selectedUnitItem
                && this.canCreateMaterialInUnit(this.selectedUnitItem)
        },
    },
    watch: {
        sharedForMeExpanded(value) {
            if (value) {
                this.workspaceExpanded = false
                return
            }

            if (!this.sharedForMeArchiveExpanded) {
                this.workspaceExpanded = true
            }
        },
        sharedForMeArchiveExpanded(value) {
            if (value) {
                this.workspaceExpanded = false
                return
            }

            if (!this.sharedForMeExpanded) {
                this.workspaceExpanded = true
            }
        },
        workspaceSelection: {
            deep: true,
            immediate: true,
            handler(value) {
                this.applyWorkspaceSelection(value)
            },
        },
    },
    methods: {
        subjectHasChildren(subject) {
            const materials = Array.isArray(subject?.materials) ? subject.materials : []
            const topics = Array.isArray(subject?.topics) ? subject.topics : []

            return materials.length > 0 || topics.length > 0
        },
        sectionMaterialCount(items) {
            const list = Array.isArray(items) ? items : []
            let count = 0

            for (const item of list) {
                count += this.nodeMaterialCount(item)
            }

            return count
        },
        cardsMaterialCount(items) {
            const list = Array.isArray(items) ? items : []
            let count = 0

            for (const item of list) {
                count += this.sharedCardMaterialCount(item)
            }

            return count
        },
        nodeMaterialCount(node) {
            if (!node || typeof node !== 'object') return 0

            let count = Array.isArray(node.materials) ? node.materials.length : 0

            if (Array.isArray(node.topics)) {
                for (const topic of node.topics) {
                    count += this.nodeMaterialCount(topic)
                }
            }

            if (Array.isArray(node.units)) {
                for (const unit of node.units) {
                    count += this.nodeMaterialCount(unit)
                }
            }

            return count
        },
        sharedCardMaterialCount(item) {
            const directCount = Number(item?.materialsCount ?? 0)
            if (Number.isFinite(directCount) && directCount > 0) {
                return Math.round(directCount)
            }

            const hierarchy = Array.isArray(item?.hierarchy) ? item.hierarchy : []
            return this.sectionMaterialCount(hierarchy)
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
            if (this.activeSection === 'workspace') return

            this.workspaceExpanded = true
            if (this.sharedForMeExpanded) this.$emit('toggle-shared-for-me-expanded')
            if (this.sharedForMeArchiveExpanded) this.$emit('toggle-shared-for-me-archive-expanded')
        },
        workspaceSubjectKey(subject) {
            return this.workspaceNodeOverrideKey('subject', subject)
        },
        isWorkspaceSubjectExpanded(subject) {
            const key = this.workspaceSubjectKey(subject)
            if (this.selectedSubjectKey === null) return false
            return key !== '' && key === this.selectedSubjectKey
        },
        hasWorkspaceSubjectTabActions() {
            return this.enableCreateButtons === true || this.isWorkspaceStructureButtonsVisible() !== true
        },
        toggleWorkspaceSubjectExpanded(subject) {
            if (this.actionBusy) return

            const key = this.workspaceSubjectKey(subject)
            if (key === '') return

            if (this.selectedSubjectKey === key) {
                this.selectedSubjectKey = null
                this.selectedTopicKey = null
                this.selectedUnitKey = null
                return
            }
            this.selectedSubjectKey = key
            this.selectedTopicKey = null
            this.selectedUnitKey = null
        },
        workspaceTopicKey(topic) {
            return this.workspaceNodeOverrideKey('topic', topic)
        },
        isWorkspaceTopicExpanded(topic) {
            const key = this.workspaceTopicKey(topic)
            if (this.selectedTopicKey === null) return false
            return key !== '' && key === this.selectedTopicKey
        },
        hasWorkspaceTopicTabActions() {
            return this.enableCreateButtons === true || this.isWorkspaceStructureButtonsVisible() !== true
        },
        toggleWorkspaceTopicExpanded(topic) {
            if (this.actionBusy) return

            const key = this.workspaceTopicKey(topic)
            if (key === '') return

            if (this.selectedTopicKey === key) {
                this.selectedTopicKey = null
                this.selectedUnitKey = null
                return
            }
            this.selectedTopicKey = key
            this.selectedUnitKey = null
        },
        workspaceUnitKey(unit) {
            return this.workspaceNodeOverrideKey('unit', unit)
        },
        workspaceSelectionKey(level, nodeId) {
            const normalizedId = Number(nodeId || 0)
            if (!Number.isFinite(normalizedId) || normalizedId <= 0) return ''
            return this.workspaceNodeOverrideKey(level, { id: normalizedId })
        },
        workspaceSubjectForTopicId(topicId) {
            const normalizedTopicId = Number(topicId || 0)
            if (!Number.isFinite(normalizedTopicId) || normalizedTopicId <= 0) return null

            const subjects = Array.isArray(this.items) ? this.items : []
            for (const subject of subjects) {
                const topics = Array.isArray(subject?.topics) ? subject.topics : []
                if (topics.some((topic) => Number(topic?.id || 0) === normalizedTopicId)) {
                    return subject
                }
            }

            return null
        },
        selectWorkspacePath({ subjectId = null, topicId = null, unitId = null } = {}) {
            this.workspaceExpanded = true

            const normalizedSubjectKey = this.workspaceSelectionKey('subject', subjectId)
            if (normalizedSubjectKey !== '') {
                this.selectedSubjectKey = normalizedSubjectKey
            }

            const normalizedTopicKey = this.workspaceSelectionKey('topic', topicId)
            if (normalizedTopicKey !== '') {
                this.selectedTopicKey = normalizedTopicKey
            } else if (topicId === null) {
                this.selectedTopicKey = null
            }

            const normalizedUnitKey = this.workspaceSelectionKey('unit', unitId)
            if (normalizedUnitKey !== '') {
                this.selectedUnitKey = normalizedUnitKey
            } else if (unitId === null) {
                this.selectedUnitKey = null
            }
        },
        applyWorkspaceSelection(selection) {
            if (!selection || typeof selection !== 'object') return

            const subjectId = Number(selection?.subjectId || 0)
            const topicId = Number(selection?.topicId || 0)
            const unitId = Number(selection?.unitId || 0)
            const hasSelection = subjectId > 0 || topicId > 0 || unitId > 0

            if (!hasSelection) return

            this.selectWorkspacePath({
                subjectId: subjectId > 0 ? subjectId : null,
                topicId: topicId > 0 ? topicId : null,
                unitId: unitId > 0 ? unitId : null,
            })
        },
        isWorkspaceUnitExpanded(unit) {
            const key = this.workspaceUnitKey(unit)
            if (this.selectedUnitKey === null) return false
            return key !== '' && key === this.selectedUnitKey
        },
        hasWorkspaceUnitTabActions() {
            return this.enableCreateButtons === true || this.isWorkspaceStructureButtonsVisible() !== true
        },
        toggleWorkspaceUnitExpanded(unit) {
            if (this.actionBusy) return

            const key = this.workspaceUnitKey(unit)
            if (key === '') return

            if (this.selectedUnitKey === key) {
                this.selectedUnitKey = null
                return
            }
            this.selectedUnitKey = key
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
            if (this.activeSection === 'shared') return

            if (this.workspaceExpanded) this.workspaceExpanded = false
            if (this.sharedForMeArchiveExpanded) this.$emit('toggle-shared-for-me-archive-expanded')
            this.$emit('toggle-shared-for-me-expanded')
        },
        toggleSharedForMeArchiveExpanded() {
            if (this.actionBusy) return
            if (this.activeSection === 'archive') return

            if (this.workspaceExpanded) this.workspaceExpanded = false
            if (this.sharedForMeExpanded) this.$emit('toggle-shared-for-me-expanded')
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
        // --- Shared single-selection (button-nav pattern) ---
        isSharedSubjectSelected(ruleId, subject) {
            const key = this.sharedSubjectKey(ruleId, subject, 'shared')
            return key !== '' && key === (this.selectedSharedSubjectKeys[ruleId] || null)
        },
        selectSharedSubject(ruleId, subject) {
            if (this.actionBusy) return
            const key = this.sharedSubjectKey(ruleId, subject, 'shared')
            if (key === '' || key === (this.selectedSharedSubjectKeys[ruleId] || null)) return
            this.selectedSharedSubjectKeys = { ...this.selectedSharedSubjectKeys, [ruleId]: key }
            const { [ruleId]: _t, ...restTopics } = this.selectedSharedTopicKeys
            this.selectedSharedTopicKeys = restTopics
            const { [ruleId]: _u, ...restUnits } = this.selectedSharedUnitKeys
            this.selectedSharedUnitKeys = restUnits
        },
        selectedSharedSubjectObj(ruleId, item) {
            const hierarchy = this.sharedItemHierarchy(item)
            return hierarchy.find(s => this.isSharedSubjectSelected(ruleId, s)) || null
        },
        selectedSharedSubjectIdx(ruleId, item) {
            const hierarchy = this.sharedItemHierarchy(item)
            const selected = this.selectedSharedSubjectObj(ruleId, item)
            return selected ? hierarchy.indexOf(selected) : -1
        },
        isSharedTopicSelected(ruleId, topic) {
            const key = this.sharedTopicKey(ruleId, topic, 'shared')
            return key !== '' && key === (this.selectedSharedTopicKeys[ruleId] || null)
        },
        selectSharedTopic(ruleId, topic) {
            if (this.actionBusy) return
            const key = this.sharedTopicKey(ruleId, topic, 'shared')
            if (key === '' || key === (this.selectedSharedTopicKeys[ruleId] || null)) return
            this.selectedSharedTopicKeys = { ...this.selectedSharedTopicKeys, [ruleId]: key }
            const { [ruleId]: _u, ...restUnits } = this.selectedSharedUnitKeys
            this.selectedSharedUnitKeys = restUnits
        },
        selectedSharedTopicObj(ruleId, item) {
            const subject = this.selectedSharedSubjectObj(ruleId, item)
            if (!subject?.topics?.length) return null
            return subject.topics.find(t => this.isSharedTopicSelected(ruleId, t)) || null
        },
        selectedSharedTopicIdx(ruleId, item) {
            const subject = this.selectedSharedSubjectObj(ruleId, item)
            if (!subject?.topics?.length) return -1
            const selected = this.selectedSharedTopicObj(ruleId, item)
            return selected ? subject.topics.indexOf(selected) : -1
        },
        isSharedUnitSelected(ruleId, unit) {
            const key = this.sharedUnitKey(ruleId, unit, 'shared')
            return key !== '' && key === (this.selectedSharedUnitKeys[ruleId] || null)
        },
        selectSharedUnit(ruleId, unit) {
            if (this.actionBusy) return
            const key = this.sharedUnitKey(ruleId, unit, 'shared')
            if (key === '' || key === (this.selectedSharedUnitKeys[ruleId] || null)) return
            this.selectedSharedUnitKeys = { ...this.selectedSharedUnitKeys, [ruleId]: key }
        },
        selectedSharedUnitObj(ruleId, item) {
            const topic = this.selectedSharedTopicObj(ruleId, item)
            if (!topic?.units?.length) return null
            return topic.units.find(u => this.isSharedUnitSelected(ruleId, u)) || null
        },
        selectedSharedUnitIdx(ruleId, item) {
            const topic = this.selectedSharedTopicObj(ruleId, item)
            if (!topic?.units?.length) return -1
            const selected = this.selectedSharedUnitObj(ruleId, item)
            return selected ? topic.units.indexOf(selected) : -1
        },
        // --- Archived single-selection (button-nav pattern) ---
        isArchivedSubjectSelected(ruleId, subject) {
            const key = this.sharedSubjectKey(ruleId, subject, 'archived')
            return key !== '' && key === (this.selectedArchivedSubjectKeys[ruleId] || null)
        },
        selectArchivedSubject(ruleId, subject) {
            if (this.actionBusy) return
            const key = this.sharedSubjectKey(ruleId, subject, 'archived')
            if (key === '' || key === (this.selectedArchivedSubjectKeys[ruleId] || null)) return
            this.selectedArchivedSubjectKeys = { ...this.selectedArchivedSubjectKeys, [ruleId]: key }
            const { [ruleId]: _t, ...restTopics } = this.selectedArchivedTopicKeys
            this.selectedArchivedTopicKeys = restTopics
            const { [ruleId]: _u, ...restUnits } = this.selectedArchivedUnitKeys
            this.selectedArchivedUnitKeys = restUnits
        },
        selectedArchivedSubjectObj(ruleId, item) {
            const hierarchy = this.sharedItemHierarchy(item)
            return hierarchy.find(s => this.isArchivedSubjectSelected(ruleId, s)) || null
        },
        isArchivedTopicSelected(ruleId, topic) {
            const key = this.sharedTopicKey(ruleId, topic, 'archived')
            return key !== '' && key === (this.selectedArchivedTopicKeys[ruleId] || null)
        },
        selectArchivedTopic(ruleId, topic) {
            if (this.actionBusy) return
            const key = this.sharedTopicKey(ruleId, topic, 'archived')
            if (key === '' || key === (this.selectedArchivedTopicKeys[ruleId] || null)) return
            this.selectedArchivedTopicKeys = { ...this.selectedArchivedTopicKeys, [ruleId]: key }
            const { [ruleId]: _u, ...restUnits } = this.selectedArchivedUnitKeys
            this.selectedArchivedUnitKeys = restUnits
        },
        selectedArchivedTopicObj(ruleId, item) {
            const subject = this.selectedArchivedSubjectObj(ruleId, item)
            if (!subject?.topics?.length) return null
            return subject.topics.find(t => this.isArchivedTopicSelected(ruleId, t)) || null
        },
        isArchivedUnitSelected(ruleId, unit) {
            const key = this.sharedUnitKey(ruleId, unit, 'archived')
            return key !== '' && key === (this.selectedArchivedUnitKeys[ruleId] || null)
        },
        selectArchivedUnit(ruleId, unit) {
            if (this.actionBusy) return
            const key = this.sharedUnitKey(ruleId, unit, 'archived')
            if (key === '' || key === (this.selectedArchivedUnitKeys[ruleId] || null)) return
            this.selectedArchivedUnitKeys = { ...this.selectedArchivedUnitKeys, [ruleId]: key }
        },
        selectedArchivedUnitObj(ruleId, item) {
            const topic = this.selectedArchivedTopicObj(ruleId, item)
            if (!topic?.units?.length) return null
            return topic.units.find(u => this.isArchivedUnitSelected(ruleId, u)) || null
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
        sharedItemSenderLabel(item) {
            const label = String(item?.fromUserLabel || '').trim() || 'Benutzer'
            const email = String(item?.fromUserEmail || '').trim()

            if (email !== '') {
                return `${label} (${email})`
            }

            return label
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
                hasContents: this.workspaceNodeHasContents(level, node),
                cascade: false,
            }
            this.sharedDeleteDialogError = ''
        },
        workspaceNodeHasContents(level, node) {
            if (!node) return false
            if (level === 'workspace') {
                const items = Array.isArray(this.items) ? this.items : []
                return items.length > 0
            }
            if (level === 'subject') {
                const topics = Array.isArray(node.topics) ? node.topics : []
                const materials = Array.isArray(node.materials) ? node.materials : []
                return topics.length > 0 || materials.length > 0
            }
            if (level === 'topic') {
                const units = Array.isArray(node.units) ? node.units : []
                const materials = Array.isArray(node.materials) ? node.materials : []
                return units.length > 0 || materials.length > 0
            }
            if (level === 'unit') {
                const materials = Array.isArray(node.materials) ? node.materials : []
                return materials.length > 0
            }
            return false
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
            if (level === 'workspace') return `/api/admin/materials/workspaces/${id}`
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
            if (level === 'workspace') return 'Workspace leeren'
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
                hasContents: this.sharedNodeHasContents(level, node),
                cascade: false,
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
                    this.selectWorkspacePath({
                        subjectId,
                        topicId: null,
                        unitId: null,
                    })
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
                    this.selectWorkspacePath({
                        subjectId,
                        topicId,
                        unitId: null,
                    })
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
                const parentSubjectId = Number(this.workspaceSubjectForTopicId(topicId)?.id || this.selectedSubjectItem?.id || 0)

                if (isWorkspaceSource) {
                    this.selectWorkspacePath({
                        subjectId: Number.isFinite(parentSubjectId) && parentSubjectId > 0 ? parentSubjectId : null,
                        topicId,
                        unitId,
                    })
                    this.$emit('workspace-node-created', {
                        level: 'unit',
                        nodeId: unitId,
                        name: savedTitle,
                        parentSubjectId: Number.isFinite(parentSubjectId) && parentSubjectId > 0 ? parentSubjectId : null,
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
            const hasContents = this.sharedDeleteDialog?.hasContents === true
            const cascade = this.sharedDeleteDialog?.cascade === true
            const endpoint = isWorkspaceSource
                ? this.workspaceDeleteEndpoint(this.sharedDeleteDialog?.level, nodeId)
                : this.sharedDeleteEndpoint(this.sharedDeleteDialog?.level, nodeId)
            if (endpoint === '' || (!isWorkspaceSource && (!Number.isFinite(ruleId) || ruleId <= 0)) || !Number.isFinite(nodeId) || nodeId <= 0) {
                this.sharedDeleteDialogError = 'Element konnte nicht gelöscht werden.'
                return
            }

            if (hasContents && !cascade) {
                this.sharedDeleteDialogError = 'Bitte bestätige, dass alle enthaltenen Elemente ebenfalls gelöscht werden.'
                return
            }

            this.sharedDeleteDialogDeleting = true

            try {
                if (isWorkspaceSource) {
                    await axios.delete(endpoint, {
                        data: {
                            data: {
                                cascade: hasContents && cascade,
                            },
                        },
                    })
                } else {
                    await axios.delete(endpoint, {
                        data: {
                            rule_id: ruleId,
                            data: {
                                cascade: hasContents && cascade,
                            },
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
        sharedNodeHasContents(level, node) {
            if (level === 'subject') {
                return (Array.isArray(node?.topics) && node.topics.length > 0)
                    || (Array.isArray(node?.materials) && node.materials.length > 0)
            }

            if (level === 'topic') {
                return (Array.isArray(node?.units) && node.units.length > 0)
                    || (Array.isArray(node?.materials) && node.materials.length > 0)
            }

            if (level === 'unit') {
                return Array.isArray(node?.materials) && node.materials.length > 0
            }

            return false
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
        emitWorkspaceUnitMaterialCreate(unit) {
            if (this.actionBusy) return
            if (!this.canCreateMaterialInUnit(unit)) return

            this.$emit('open-create', {
                level: 'unit',
                subject: this.selectedSubjectItem ? String(this.workspaceNodeTitle('subject', this.selectedSubjectItem) || '').trim() : '',
                topic: this.selectedTopicItem ? String(this.workspaceNodeTitle('topic', this.selectedTopicItem) || '').trim() : '',
                unit: String(this.workspaceNodeTitle('unit', unit) || '').trim(),
            })
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
            if (normalizedLevel === 'workspace') return true
            if (normalizedLevel === 'subject') return true
            if (normalizedLevel === 'topic') return true
            if (normalizedLevel === 'unit') return true
            if (normalizedLevel === 'material') return true
            return false
        },
        sharedItemInsertLevel(item) {
            const scopeType = this.sharedItemScopeType(item)
            if (scopeType === 'all') return 'workspace'
            if (scopeType === 'subject') return 'subject'
            if (scopeType === 'topic') return 'topic'
            if (scopeType === 'unit') return 'unit'
            if (scopeType === 'material') return 'material'
            return ''
        },
        sharedItemCanInsert(item) {
            const level = this.sharedItemInsertLevel(item)
            if (level === '') return false
            if (!this.canShowSharedInsertButton(level)) return false
            if (level === 'workspace') return true

            const scopeId = Number(item?.scopeId || item?.scope_id || 0)
            return Number.isFinite(scopeId) && scopeId > 0
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
        emitSharedItemInsertDraft(item) {
            const level = this.sharedItemInsertLevel(item)
            if (!this.sharedItemCanInsert(item)) return

            const ruleId = Number(item?.ruleId || 0)
            const scopeId = Number(item?.scopeId || item?.scope_id || 0)
            const label = String(item?.scopeObjectLabel || item?.scopeLabel || this.sharedItemTypeLabel(item) || 'Element').trim() || 'Element'
            const parentLabel = String(item?.scopePathLabel || '').trim()

            this.$emit('open-shared-insert-draft', {
                ruleId,
                level,
                targetId: level === 'workspace' ? null : scopeId,
                label,
                parentLabel,
            })
        },
        emitSharedInsertDraft(item, level, node, lineage = {}) {
            const ruleId = Number(item?.ruleId || 0)
            const normalizedLevel = String(level || '').trim().toLowerCase()
            if (!Number.isFinite(ruleId) || ruleId <= 0) return
            if (!['workspace', 'subject', 'topic', 'unit'].includes(normalizedLevel)) return
            if (!this.canShowSharedInsertButton(normalizedLevel)) return

            const nodeId = Number(node?.id || 0)
            const label = normalizedLevel === 'workspace'
                ? String(item?.scopeObjectLabel || item?.scopeLabel || 'Workspace').trim() || 'Workspace'
                : this.sharedNodeTitle(ruleId, normalizedLevel, node)

            this.$emit('open-shared-insert-draft', {
                ruleId,
                level: normalizedLevel,
                targetId: normalizedLevel === 'workspace' ? null : (Number.isFinite(nodeId) && nodeId > 0 ? nodeId : null),
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
            if (normalizedLevel !== 'unit') return
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
            const normalizedLevel = String(level || '').trim()
            if (normalizedLevel !== 'unit') return false
            return this.sharedItemCanAddMaterial(item) && this.sharedNodeWithinScope(item, normalizedLevel)
        },
        sharedNodeCanStructureDelete(item, level) {
            return this.sharedItemHasFullAccess(item) && this.sharedNodeWithinScope(item, level)
        },
        sharedNodeIsContextOnly(item, level) {
            return !this.sharedNodeWithinScope(item, level)
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

.overview-selected-subject__label {
    font-weight: 700;
    font-size: 1.05rem;
    color: #233d4c;
}

.overview-material-card {
    width: 220px;
    flex: 0 0 220px;
    cursor: pointer;
    transition: box-shadow 0.2s ease, transform 0.15s ease;
}

.overview-material-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    transform: translateY(-1px);
}

.overview-material-card--create {
    min-height: 104px;
    border-style: dashed !important;
    border-color: rgba(var(--v-theme-primary), 0.42) !important;
    background: rgba(var(--v-theme-primary), 0.06) !important;
    display: flex;
    align-items: stretch;
    justify-content: center;
}

.overview-material-card--create:hover {
    border-color: rgba(var(--v-theme-primary), 0.72) !important;
    background: rgba(var(--v-theme-primary), 0.1) !important;
}

.overview-material-card--disabled {
    cursor: default;
    opacity: 0.55;
    pointer-events: none;
}

.overview-material-card-create__content {
    min-height: 104px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.overview-material-card__title {
    font-weight: 600;
    font-size: 0.9rem;
    color: #233d4c;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.overview-material-card__description {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.75rem;
    line-height: 1.3;
    margin-top: -2px;
}

.overview-unit-card {
    position: relative;
    overflow: visible;
    border: 1px dotted #888 !important;
}

.overview-unit-card--workspace {
    background-color: #e1f5fe !important;
}

.overview-unit-card--workspace :deep(.v-card__overlay) {
    opacity: 0 !important;
}

.overview-unit-card__header {
    position: relative;
    top: -14px;
    margin-bottom: -6px;
    margin-left: 12px;
    width: fit-content;
    background: rgba(248, 239, 231, 0.96);
    padding: 2px 10px;
    border-radius: 8px;
}

.overview-unit-card__header--subject {
    background: #e1f5fe !important;
    border: 1px dotted #888;
}

.overview-unit-card__header--workspace-subject,
.overview-unit-card__header--workspace-node {
    border: 0 !important;
}

.overview-unit-card__header--workspace-subject .overview-selected-subject__label,
.overview-unit-card__header--workspace-node .overview-selected-subject__label {
    color: rgb(var(--v-theme-primary));
    letter-spacing: 0.04em;
}

.overview-subjects-nav-btn {
    text-transform: none;
    letter-spacing: 0.01em;
    font-weight: 400;
    font-size: 0.95rem !important;
}

.overview-subjects-nav-btn.v-btn--variant-outlined {
    border-color: #ccc !important;
}

.overview-subjects-nav-btn :deep(.v-btn__content) {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.overview-subjects-tabbar {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 8px 10px;
    min-width: 0;
    border-bottom: 1px solid rgba(35, 61, 76, 0.16);
    padding-bottom: 0;
}

.overview-subjects-tablist {
    display: flex;
    flex: 1 1 280px;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
}

.overview-subjects-tab {
    display: inline-flex;
    align-items: stretch;
    border-radius: 8px 8px 0 0 !important;
    height: 42px;
    min-height: 42px;
    max-height: 42px;
    margin-bottom: -1px;
    max-width: 100%;
    overflow: hidden;
}

.overview-subjects-tab-button {
    border-radius: inherit !important;
    height: 42px !important;
    min-height: 42px;
    max-height: 42px !important;
    max-width: 100%;
    padding-inline: 14px !important;
}

.overview-subjects-tab--active {
    background: rgba(var(--v-theme-primary), 0.12) !important;
    border: 1px solid rgba(var(--v-theme-primary), 0.28) !important;
    border-bottom-color: rgba(248, 239, 231, 0.96) !important;
}

.overview-subjects-tab--active .overview-subjects-tab-button {
    background: transparent !important;
    border-color: transparent !important;
    border-radius: 8px 0 0 0 !important;
}

.overview-subjects-tab--subject .overview-subjects-tab-button,
.overview-subjects-tab--hierarchy .overview-subjects-tab-button {
    font-size: 0.95rem;
    line-height: 1.1;
    transition: font-size 0.15s ease;
}

.overview-subjects-tab--subject.overview-subjects-tab--active .overview-subjects-tab-button,
.overview-subjects-tab--hierarchy.overview-subjects-tab--active .overview-subjects-tab-button {
    font-size: 1.03rem;
    font-weight: 700;
}

.overview-subjects-tab--muted .overview-subjects-tab-button {
    font-size: 0.88rem;
    opacity: 0.58;
    filter: saturate(0.65);
    transition:
        font-size 0.15s ease,
        opacity 0.15s ease,
        filter 0.15s ease;
}

.overview-subjects-tab--muted:hover .overview-subjects-tab-button,
.overview-subjects-tab--muted:focus-within .overview-subjects-tab-button {
    opacity: 0.82;
    filter: saturate(0.85);
}

.overview-subjects-tab-menu-btn {
    align-self: stretch;
    border-radius: 0 8px 0 0 !important;
    height: 42px !important;
    min-height: 42px;
    max-height: 42px !important;
    min-width: 36px !important;
    border-left: 1px solid rgba(var(--v-theme-primary), 0.16);
}

.overview-subjects-tab-menu {
    min-width: 240px;
}

.overview-subjects-tab--add .overview-subjects-add-tab-button {
    height: 42px !important;
    min-height: 42px !important;
    max-height: 42px !important;
    min-width: 42px !important;
    padding-inline: 0 !important;
}

.overview-subjects-nav :deep(.v-btn--variant-text) {
    align-self: center;
    min-width: auto;
}

.overview-subjects-nav :deep(.v-btn--variant-text .v-icon) {
    font-size: 1.8rem;
    font-weight: 900;
    -webkit-text-stroke: 1px currentColor;
}

.overview-top-toggle {
    border-color: rgba(140, 30, 55, 0.35);
}

.overview-top-toggle :deep(.v-btn) {
    text-transform: none;
    letter-spacing: 0.01em;
    font-weight: 600;
}

.overview-top-toggle :deep(.v-btn__content) {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
}

.overview-top-toggle :deep(.v-btn--active) {
    background: rgb(var(--v-theme-primary)) !important;
    color: #fff !important;
}

.overview-top-toggle :deep(.v-btn--active .v-btn__overlay) {
    opacity: 0 !important;
}

.overview-top-toggle :deep(.v-btn--active .v-icon) {
    color: #fff !important;
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
    background: transparent;
    border: none;
}

.overview-subjects-node--workspace-toggle {
    border: none;
    cursor: pointer;
}

.overview-subjects-node--workspace-toggle:disabled {
    cursor: default;
    opacity: 0.7;
}

.overview-subjects-node--shared-toggle {
    background: transparent;
    border: none;
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
    justify-content: center;
    margin-left: 6px;
    min-width: 1.5rem;
    height: 1.5rem;
    padding: 0 0.45rem;
    border-radius: 999px;
    border: 1px solid rgba(35, 61, 76, 0.2);
    background: rgba(35, 61, 76, 0.06);
    font-size: 0.74rem;
    line-height: 1;
    color: rgba(35, 61, 76, 0.85);
    flex-shrink: 0;
    vertical-align: middle;
}

.overview-subjects-material-count--button {
    background: rgba(35, 61, 76, 0.06);
    margin-left: 0;
    cursor: pointer;
}

.overview-subjects-nav-btn .overview-subjects-material-count--button {
    margin-left: 0.45rem;
    padding: 0;
    min-width: auto;
    height: auto;
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 0.75rem;
    line-height: 1;
    opacity: 0.85;
}

.overview-top-toggle .overview-subjects-material-count--toggle {
    margin-left: 0.45rem;
    padding: 0;
    min-width: auto;
    height: auto;
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 0.75rem;
    line-height: 1;
    opacity: 0.92;
}

.overview-top-toggle .overview-subjects-material-count--button {
    margin-left: 0;
    background: rgba(255, 255, 255, 0.22);
    border-color: rgba(255, 255, 255, 0.34);
    color: #fff;
}

.overview-subjects-material-count--button:hover:not(:disabled) {
    background: rgba(35, 61, 76, 0.12);
}

.overview-subjects-nav-btn .overview-subjects-material-count--button:hover:not(:disabled) {
    background: transparent;
}

.overview-top-toggle .overview-subjects-material-count--button:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.32);
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
    border: 1px dotted #888;
    background: #e1f5fe;
    color: #2e4a5a;
    font-weight: 400;
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
    border: 1px dotted #888;
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
    border-top: 1px dotted #888;
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
    margin-left: 24px;
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
    font-weight: 400;
}

.overview-shared-insert-btn {
    text-transform: none;
    letter-spacing: 0.01em;
    font-weight: 500;
    background: #2e6ea4 !important;
    color: #ffffff !important;
}

.overview-shared-insert-btn :deep(.v-btn__content),
.overview-shared-insert-btn :deep(.v-icon) {
    color: #ffffff !important;
}

.overview-shared-item-title {
    font-weight: 600;
    color: #17384a;
}

.overview-shared-item-path,
.overview-shared-item-meta,
.overview-shared-state {
    margin-top: 4px;
    color: #3a5668;
    font-size: 0.92rem;
    font-weight: 400;
}

.overview-shared-state--error {
    color: #a52626;
}
</style>
