<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <MaterialsOverviewHeader
            :hide-overview-mode-toggle="hideOverviewModeToggle"
            :overview-view-mode="overviewViewMode"
            :is-loading="isLoading"
            :action-disabled="isDeletingId !== null || isSavingEdit || isUnlinkingId !== null || isUnlinkingUnitId !== null || isUnlinkingTopicId !== null"
            @update:overview-view-mode="setOverviewMode"
            @refresh="loadCards" />

        <MaterialsOverviewFilters
            v-if="!isSharedSubjectsContentsSource"
            :action-disabled="isLoading || isDeletingId !== null || isSavingEdit || isUnlinkingId !== null || isUnlinkingUnitId !== null || isUnlinkingTopicId !== null"
            :badge-count-content="badgeCountContent"
            :subject-all-count="subjectAllCount"
            :has-active-subject-filter="hasActiveSubjectFilter"
            :clear-subject-filter="clearSubjectFilter"
            :subject-filter-options="subjectFilterOptions"
            :subject-filter-count="subjectFilterCount"
            :is-subject-filter-active="isSubjectFilterActive"
            :toggle-subject-filter="toggleSubjectFilter"
            :topic-all-count="topicAllCount"
            :has-active-topic-filter="hasActiveTopicFilter"
            :clear-topic-filter="clearTopicFilter"
            :topic-filter-options="topicFilterOptions"
            :topic-filter-count="topicFilterCount"
            :is-topic-filter-active="isTopicFilterActive"
            :toggle-topic-filter="toggleTopicFilter"
            :unit-all-count="unitAllCount"
            :has-active-unit-filter="hasActiveUnitFilter"
            :clear-unit-filter="clearUnitFilter"
            :unit-filter-options="unitFilterOptions"
            :unit-filter-count="unitFilterCount"
            :is-unit-filter-active="isUnitFilterActive"
            :toggle-unit-filter="toggleUnitFilter"
            :show-secondary-filters="showSecondaryFilters"
            :toggle-secondary-filters="toggleSecondaryFilters"
            :has-active-type-filter="hasActiveTypeFilter"
            :clear-type-filter="clearTypeFilter"
            :type-filter-options="typeFilterOptions"
            :type-color="typeColor"
            :is-type-filter-active="isTypeFilterActive"
            :toggle-type-filter="toggleTypeFilter"
            :has-active-status-filter="hasActiveStatusFilter"
            :clear-status-filter="clearStatusFilter"
            :status-filter-options="statusFilterOptions"
            :status-color="statusColor"
            :is-status-filter-active="isStatusFilterActive"
            :toggle-status-filter="toggleStatusFilter" />

        <v-alert v-if="!isSharedSubjectsContentsSource" type="info" variant="tonal" class="mb-4">
            {{ displayedMaterials }}/{{ totalMaterials }} Material{{ totalMaterials === 1 ? '' : 'ien' }} angezeigt.
            <span class="ml-2">• Speicher: angezeigt {{ shownListedAttachmentSizeLabel }} / alle {{ allListedAttachmentSizeLabel }}</span>
        </v-alert>
        <v-alert
            v-if="!isSharedSubjectsContentsSource && deletedMaterialRestoreItems.length > 0 && !deletedMaterialRestoreHidden"
            type="warning"
            variant="tonal"
            class="mb-4">
            <div class="d-flex flex-column ga-3">
                <div class="d-flex flex-column flex-md-row align-md-center ga-2">
                    <div class="flex-grow-1">
                        Gelöschte Materialien (wiederherstellbar, max. {{ deletedMaterialRestoreLimit }}).
                    </div>
                    <div class="d-flex ga-2">
                        <v-btn
                            size="small"
                            variant="text"
                            :disabled="isRestoringLastDeletedMaterial || isPurgingDeletedMaterial"
                            @click="hideDeletedMaterialRestoreList">
                            Ausblenden
                        </v-btn>
                    </div>
                </div>

                <v-list class="bg-transparent pa-0">
                    <v-list-item
                        v-for="item in deletedMaterialRestoreItems"
                        :key="`deleted-restore-item-${item.id}`"
                        class="px-0 py-2">
                        <div class="d-flex flex-column flex-md-row align-md-center ga-2 w-100">
                            <div class="flex-grow-1">
                                <div class="text-body-2 font-weight-medium">
                                    {{ item.title || 'Material' }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    Gelöscht: {{ item.deletedAt ? formatDateTime(item.deletedAt) : 'unbekannt' }}
                                    <span v-if="Number(item.attachmentsCount || 0) > 0">
                                        • {{ Number(item.attachmentsCount || 0) }} Anhang{{ Number(item.attachmentsCount || 0) === 1 ? '' : 'e' }}
                                    </span>
                                    <span v-else>• keine Anhänge</span>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex flex-wrap ga-2 justify-end">
                                    <v-btn
                                        size="small"
                                        color="warning"
                                        variant="flat"
                                        prepend-icon="mdi-restore"
                                        :loading="isRestoringLastDeletedMaterial && Number(restoringDeletedMaterialId || 0) === Number(item.id || 0)"
                                        :disabled="isLoading || isSavingEdit || isDeletingId !== null || isPurgingDeletedMaterial || (isRestoringLastDeletedMaterial && Number(restoringDeletedMaterialId || 0) !== Number(item.id || 0))"
                                        @click="restoreDeletedMaterial(item)">
                                        Wiederherstellen
                                    </v-btn>
                                    <v-btn
                                        size="small"
                                        color="error"
                                        variant="outlined"
                                        prepend-icon="mdi-delete-forever-outline"
                                        :loading="isPurgingDeletedMaterial && Number(purgingDeletedMaterialId || 0) === Number(item.id || 0)"
                                        :disabled="isLoading || isSavingEdit || isDeletingId !== null || isRestoringLastDeletedMaterial || (isPurgingDeletedMaterial && Number(purgingDeletedMaterialId || 0) !== Number(item.id || 0))"
                                        @click="purgeDeletedMaterial(item)">
                                        Endgültig löschen
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                </v-list>
            </div>
        </v-alert>
        <div v-if="!isSharedSubjectsContentsSource && deletedMaterialRestoreItems.length > 0 && deletedMaterialRestoreHidden" class="mb-4 d-flex justify-end">
            <v-btn
                size="small"
                color="warning"
                variant="outlined"
                prepend-icon="mdi-eye-outline"
                :disabled="isRestoringLastDeletedMaterial || isPurgingDeletedMaterial"
                @click="showDeletedMaterialRestoreList">
                Restore-Liste einblenden
            </v-btn>
        </div>
        <MaterialsOverviewSortBar v-if="!isSubjectsContentsOverview" :overview-sort-mode="overviewSortMode" @update:overview-sort-mode="setOverviewSortMode" />

        <v-skeleton-loader v-if="!isSharedSubjectsContentsSource && isLoading && !hasCards" type="list-item-three-line@4" />

        <template v-else-if="isSubjectsContentsOverview">
            <div class="d-flex flex-wrap align-center ga-2 mb-3">
                <template v-if="showSubjectsContentsSourceToggle">
                    <div class="subjects-source-switch">
                    <v-btn
                        class="subjects-source-switch__btn"
                        :class="{ 'subjects-source-switch__btn--active': isWorkspaceSubjectsContentsSource }"
                        size="small"
                        color="primary"
                        prepend-icon="mdi-briefcase-outline"
                        :variant="isWorkspaceSubjectsContentsSource ? 'flat' : 'text'"
                        :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                        @click="setSubjectsContentsSource('workspace')">
                        Mein Workspace
                    </v-btn>
                    <v-btn
                        class="subjects-source-switch__btn"
                        :class="{ 'subjects-source-switch__btn--active': !isWorkspaceSubjectsContentsSource }"
                        size="small"
                        color="primary"
                        prepend-icon="mdi-account-multiple-outline"
                        :variant="!isWorkspaceSubjectsContentsSource ? 'flat' : 'text'"
                        :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                        @click="setSubjectsContentsSource('shared')">
                        Für mich freigegeben
                    </v-btn>
                    </div>
                </template>
                <v-spacer />
                <v-btn
                    v-if="isWorkspaceSubjectsContentsSource && !hideSubjectsOverviewPrintButton"
                    size="small"
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-printer-outline"
                    :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                    @click="openSubjectsOverviewScreen">
                    Übersicht / Druck
                </v-btn>
            </div>

            <template v-if="!isWorkspaceSubjectsContentsSource">
                <v-progress-linear v-if="isLoadingSharedObjectsForMe" indeterminate color="primary" rounded class="mb-3" />

                <v-alert v-else-if="sharedObjectsForMeError" type="error" variant="tonal" class="mb-0">
                    {{ sharedObjectsForMeError }}
                </v-alert>

                <v-alert v-else-if="sharedObjectsForMeCards.length === 0" type="info" variant="tonal" class="mb-0">
                    Es sind aktuell keine Freigaben für dich vorhanden.
                </v-alert>

                <v-row v-else dense class="shared-objects-grid">
                    <v-col
                        v-for="item in sharedObjectsForMeCards"
                        :key="`shared-object-${item.ruleId}`"
                        cols="12"
                        :sm="6"
                        :md="4"
                        :xl="3">
                        <v-card variant="outlined" class="shared-object-card h-100">
                            <v-card-text class="d-flex flex-column ga-2 pa-4">
                                <div class="d-flex align-start justify-space-between ga-2">
                                    <div>
                                        <div class="text-subtitle-1 font-weight-bold">
                                            {{ item.scopeObjectLabel }}
                                        </div>
                                        <div class="text-caption text-medium-emphasis">
                                            {{ item.scopePathLabel }}
                                        </div>
                                    </div>
                                    <v-chip size="x-small" variant="tonal" color="primary">
                                        <v-icon start size="14">{{ sharedScopeIcon(item.scopeType) }}</v-icon>
                                        {{ item.scopeLabel }}
                                    </v-chip>
                                </div>

                                <div class="d-flex flex-wrap align-center ga-2">
                                    <v-chip size="x-small" variant="flat" :color="sharedPermissionColor(item.permission)">
                                        {{ item.permissionLabel }}
                                    </v-chip>
                                </div>

                                <div class="text-body-2">
                                    <span class="font-weight-medium">Von:</span>
                                    {{ item.fromUserLabel }}
                                    <span v-if="item.fromSchoolLabel" class="text-medium-emphasis"> · {{ item.fromSchoolLabel }}</span>
                                </div>

                                <div class="text-caption text-medium-emphasis">
                                    Freigegeben: {{ formatDateTime(item.sharedAt) || '-' }}
                                </div>

                                <div v-if="canExpandSharedHierarchy(item)" class="d-flex justify-end">
                                    <v-btn
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        @click.stop="toggleSharedHierarchy(item.ruleId)">
                                        {{ isSharedHierarchyOpen(item.ruleId) ? 'Schließen' : 'Anzeigen' }}
                                    </v-btn>
                                </div>

                                <v-expand-transition>
                                    <div v-if="isSharedHierarchyOpen(item.ruleId)" class="inbox-hierarchy-card">
                                        <div class="inbox-shared-object-title inbox-shared-object-title--all">Alle Materialien ({{ item.materialsCount }})</div>
                                        <div v-if="item.hierarchy.length === 0" class="text-caption text-medium-emphasis">
                                            Keine Hierarchie für diese Freigabe verfügbar.
                                        </div>
                                        <div v-else class="inbox-shared-hierarchy">
                                            <div
                                                v-for="subject in item.hierarchy"
                                                :key="`shared-hier-subject-${item.ruleId}-${subject.id || subject.name}`"
                                                class="inbox-hierarchy-subject">
                                                <div class="inbox-hierarchy-subject-head inbox-hierarchy-level-subject">
                                                    <div class="inbox-hierarchy-context-line">{{ subject.name }}</div>
                                                </div>
                                                <div
                                                    v-if="Array.isArray(subject.materials) && subject.materials.length > 0"
                                                    class="inbox-hierarchy-material-lines">
                                                    <div
                                                        v-for="material in subject.materials"
                                                        :key="`shared-hier-subject-material-${item.ruleId}-${material.id || material.title}`"
                                                        class="inbox-hierarchy-material-line inbox-hierarchy-level-material">
                                                        <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                        <span
                                                            class="inbox-hierarchy-material-title inbox-hierarchy-material-title-clickable"
                                                            role="button"
                                                            tabindex="0"
                                                            @click.stop="openSharedMaterialDetail(item.ruleId, material)"
                                                            @keydown.enter.stop.prevent="openSharedMaterialDetail(item.ruleId, material)"
                                                            @keydown.space.stop.prevent="openSharedMaterialDetail(item.ruleId, material)">
                                                            {{ material.title }}
                                                        </span>
                                                        <v-chip
                                                            v-if="material.typeLabel"
                                                            size="x-small"
                                                            variant="outlined"
                                                            :color="material.typeColor || 'primary'">
                                                            {{ material.typeLabel }}
                                                        </v-chip>
                                                        <span
                                                            v-if="material.attachmentsCount > 0"
                                                            class="inbox-hierarchy-material-count inbox-hierarchy-material-count-clickable"
                                                            role="button"
                                                            tabindex="0"
                                                            @click.stop="openSharedMaterialAttachments(item.ruleId, material)"
                                                            @keydown.enter.stop.prevent="openSharedMaterialAttachments(item.ruleId, material)"
                                                            @keydown.space.stop.prevent="openSharedMaterialAttachments(item.ruleId, material)">
                                                            <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                            {{ material.attachmentsCount }}
                                                        </span>
                                                        <v-chip
                                                            size="x-small"
                                                            variant="tonal"
                                                            :color="material.statusColor || statusColor(material.status)">
                                                            {{ material.statusLabel || statusLabel(material.status) }}
                                                        </v-chip>
                                                    </div>
                                                </div>
                                                <div
                                                    v-for="topic in subject.topics"
                                                    :key="`shared-hier-topic-${item.ruleId}-${topic.id || topic.name}`"
                                                    class="inbox-hierarchy-topic inbox-hierarchy-level-topic">
                                                    <div class="inbox-hierarchy-topic-head">
                                                        <div class="inbox-hierarchy-topic-title">{{ topic.name }}</div>
                                                    </div>
                                                    <div
                                                        v-if="Array.isArray(topic.materials) && topic.materials.length > 0"
                                                        class="inbox-hierarchy-material-lines">
                                                        <div
                                                            v-for="material in topic.materials"
                                                            :key="`shared-hier-topic-material-${item.ruleId}-${material.id || material.title}`"
                                                            class="inbox-hierarchy-material-line inbox-hierarchy-level-material">
                                                            <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                            <span
                                                                class="inbox-hierarchy-material-title inbox-hierarchy-material-title-clickable"
                                                                role="button"
                                                                tabindex="0"
                                                                @click.stop="openSharedMaterialDetail(item.ruleId, material)"
                                                                @keydown.enter.stop.prevent="openSharedMaterialDetail(item.ruleId, material)"
                                                                @keydown.space.stop.prevent="openSharedMaterialDetail(item.ruleId, material)">
                                                                {{ material.title }}
                                                            </span>
                                                            <v-chip
                                                                v-if="material.typeLabel"
                                                                size="x-small"
                                                                variant="outlined"
                                                                :color="material.typeColor || 'primary'">
                                                                {{ material.typeLabel }}
                                                            </v-chip>
                                                            <span
                                                                v-if="material.attachmentsCount > 0"
                                                                class="inbox-hierarchy-material-count inbox-hierarchy-material-count-clickable"
                                                                role="button"
                                                                tabindex="0"
                                                                @click.stop="openSharedMaterialAttachments(item.ruleId, material)"
                                                                @keydown.enter.stop.prevent="openSharedMaterialAttachments(item.ruleId, material)"
                                                                @keydown.space.stop.prevent="openSharedMaterialAttachments(item.ruleId, material)">
                                                                <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                                {{ material.attachmentsCount }}
                                                            </span>
                                                            <v-chip
                                                                size="x-small"
                                                                variant="tonal"
                                                                :color="material.statusColor || statusColor(material.status)">
                                                                {{ material.statusLabel || statusLabel(material.status) }}
                                                            </v-chip>
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-for="unit in topic.units"
                                                        :key="`shared-hier-unit-${item.ruleId}-${unit.id || unit.name}`"
                                                        class="inbox-hierarchy-unit inbox-hierarchy-level-unit">
                                                        <div class="inbox-hierarchy-unit-head">
                                                            <div class="inbox-hierarchy-unit-title">{{ unit.name }}</div>
                                                        </div>
                                                        <div class="inbox-hierarchy-material-lines">
                                                            <div
                                                                v-for="material in unit.materials"
                                                                :key="`shared-hier-material-${item.ruleId}-${material.id || material.title}`"
                                                                class="inbox-hierarchy-material-line inbox-hierarchy-level-material">
                                                                <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" :color="material.typeColor || undefined" />
                                                                <span
                                                                    class="inbox-hierarchy-material-title inbox-hierarchy-material-title-clickable"
                                                                    role="button"
                                                                    tabindex="0"
                                                                    @click.stop="openSharedMaterialDetail(item.ruleId, material)"
                                                                    @keydown.enter.stop.prevent="openSharedMaterialDetail(item.ruleId, material)"
                                                                    @keydown.space.stop.prevent="openSharedMaterialDetail(item.ruleId, material)">
                                                                    {{ material.title }}
                                                                </span>
                                                                <v-chip
                                                                    v-if="material.typeLabel"
                                                                    size="x-small"
                                                                    variant="outlined"
                                                                    :color="material.typeColor || 'primary'">
                                                                    {{ material.typeLabel }}
                                                                </v-chip>
                                                                <span
                                                                    v-if="material.attachmentsCount > 0"
                                                                    class="inbox-hierarchy-material-count inbox-hierarchy-material-count-clickable"
                                                                    role="button"
                                                                    tabindex="0"
                                                                    @click.stop="openSharedMaterialAttachments(item.ruleId, material)"
                                                                    @keydown.enter.stop.prevent="openSharedMaterialAttachments(item.ruleId, material)"
                                                                    @keydown.space.stop.prevent="openSharedMaterialAttachments(item.ruleId, material)">
                                                                    <v-icon size="12" icon="mdi-paperclip" class="mr-1" />
                                                                    {{ material.attachmentsCount }}
                                                                </span>
                                                                <v-chip
                                                                    size="x-small"
                                                                    variant="tonal"
                                                                    :color="material.statusColor || statusColor(material.status)">
                                                                    {{ material.statusLabel || statusLabel(material.status) }}
                                                                </v-chip>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </v-expand-transition>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </template>
            <template v-else>
                <v-progress-linear
                    v-if="isLoadingSubjectsContentsOverview && subjectsContentsOverviewItems.length"
                    indeterminate
                    color="primary"
                    rounded
                    class="mb-3" />

                <v-progress-linear
                    v-if="isLoadingSubjectsContentsOverview && !subjectsContentsOverviewItems.length"
                    indeterminate
                    color="primary"
                    rounded
                    class="mb-3" />

                <v-alert v-else-if="!subjectsContentsOverviewItems.length" type="info" variant="tonal" class="mb-0">Keine Fachstruktur mit Materialien gefunden.</v-alert>

                <MaterialsSubjectsContentsTree
                    v-else
                    :items="subjectsContentsOverviewItems"
                    :action-busy="isLoading || isDeletingId !== null || isSavingEdit || isSavingCreate || isRemovingTreeClassification || isUnlinkingId !== null || isUnlinkingUnitId !== null || isUnlinkingTopicId !== null"
                    :enable-share-buttons="enableShareButtons"
                    :enable-create-buttons="!readOnlyMaterialActions"
                    :enable-remove-buttons="!readOnlyMaterialActions"
                    :show-share-indicators="enableShareButtons"
                    :share-indicator-color-fn="shareIndicatorColor"
                    :status-color-fn="statusColor"
                    :status-label-fn="statusLabel"
                    :subject-group-style-fn="subjectGroupStyle"
                    :topic-group-style-fn="topicGroupStyle"
                    :shared-objects-for-me="sharedObjectsForMeCards"
                    :shared-objects-for-me-loading="isLoadingSharedObjectsForMe"
                    :shared-objects-for-me-error="sharedObjectsForMeError"
                    :shared-for-me-expanded="subjectsTreeSharedForMeExpanded"
                    :expanded-shared-items="subjectsTreeExpandedSharedItems"
                    @open-material="openDetailDialog"
                    @open-shared-material="openSharedMaterialFromTree"
                    @open-share="openShareDialog"
                    @open-create="openCreateDialogFromTree"
                    @open-attachments="openAttachmentManager"
                    @open-shared-attachments="openSharedMaterialAttachmentsFromTree"
                    @toggle-shared-for-me-expanded="toggleSubjectsTreeSharedForMeExpanded"
                    @toggle-shared-item-expanded="toggleSubjectsTreeSharedItemExpanded"
                    @unlink-linked-material="unlinkLinkedCard"
                    @unlink-linked-topic="unlinkLinkedTopic"
                    @unlink-linked-unit="unlinkLinkedUnit" />
            </template>
        </template>

        <template v-else-if="hasCards">
            <MaterialsOverviewGrid
                v-if="isCompactOverview"
                :cards="sortedCards"
                :action-disabled="isLoading || isDeletingId !== null || isSavingEdit || isUnlinkingId !== null || isUnlinkingUnitId !== null || isUnlinkingTopicId !== null"
                :show-edit-action="!readOnlyMaterialActions"
                :card-background-style-fn="cardBackgroundStyle"
                :status-color-fn="statusColor"
                :status-label-fn="statusLabel"
                :source-icon-fn="sourceIcon"
                :attachment-count-compact-label-fn="attachmentCountCompactLabel"
                :classification-labels-fn="classificationLabels"
                :preview-fn="preview"
                :format-date-time-fn="formatDateTime"
                @open-detail="openDetailDialog"
                @open-edit="openEditDialog"
                @open-attachments="openAttachmentManager"
                @unlink-link="unlinkLinkedCard" />

            <MaterialsOverviewAlphaList
                v-else-if="isAlphabeticOverview"
                :cards="sortedCards"
                :action-disabled="isLoading || isDeletingId !== null || isSavingEdit || isUnlinkingId !== null || isUnlinkingUnitId !== null || isUnlinkingTopicId !== null"
                :show-edit-action="!readOnlyMaterialActions"
                :card-background-style-fn="cardBackgroundStyle"
                :status-color-fn="statusColor"
                :status-label-fn="statusLabel"
                :source-icon-fn="sourceIcon"
                :attachment-count-compact-label-fn="attachmentCountCompactLabel"
                :alphabetic-assignment-line-fn="alphabeticAssignmentLine"
                @open-detail="openDetailDialog"
                @open-edit="openEditDialog"
                @open-attachments="openAttachmentManager"
                @unlink-link="unlinkLinkedCard" />

            <MaterialsOverviewList
                v-else
                :cards="sortedCards"
                :action-disabled="isLoading || isDeletingId !== null || isSavingEdit || isUnlinkingId !== null || isUnlinkingUnitId !== null || isUnlinkingTopicId !== null"
                :show-edit-action="!readOnlyMaterialActions"
                :card-background-style-fn="cardBackgroundStyle"
                :status-color-fn="statusColor"
                :status-label-fn="statusLabel"
                :source-icon-fn="sourceIcon"
                :classification-labels-fn="classificationLabels"
                :preview-fn="preview"
                :attachment-count-label-fn="attachmentCountLabel"
                :file-attachments-fn="fileAttachments"
                :is-downloading-attachment-fn="isDownloadingAttachment"
                :attachment-chip-label-fn="attachmentChipLabel"
                :format-date-time-fn="formatDateTime"
                @open-detail="openDetailDialog"
                @open-edit="openEditDialog"
                @open-attachments="openAttachmentManager"
                @download-attachment="downloadAttachment"
                @unlink-link="unlinkLinkedCard" />

            <MaterialsOverviewPagination
                :current-page="currentMetaPage"
                :last-page="lastMetaPage"
                :has-previous-page="hasPreviousPage"
                :has-next-page="hasNextPage"
                :action-disabled="isLoading || isDeletingId !== null || isSavingEdit || isUnlinkingId !== null || isUnlinkingUnitId !== null || isUnlinkingTopicId !== null"
                @first="goToFirstPage"
                @previous="goToPreviousPage"
                @next="goToNextPage"
                @last="goToLastPage" />
        </template>

        <v-alert v-else type="warning" variant="tonal" class="mb-0">Aktuell sind keine Materialien gespeichert.</v-alert>
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
                <div class="attachment-pond-wrap mb-3" @dragover.capture="onAttachmentDragOver" @drop.capture="onAttachmentDrop">
                    <file-pond
                        v-if="csrfToken && attachmentDialogCanAppendContent"
                        ref="attachmentPond"
                        name="file"
                        :allow-multiple="false"
                        :chunk-uploads="true"
                        :chunk-force="true"
                        :allow-revert="false"
                        :allow-remove="true"
                        :instant-upload="true"
                        :before-add-file="beforeAttachmentAddFile"
                        :disabled="attachmentDialogBusy"
                        :label-idle="'<strong>Datei hierher ziehen oder <i>klicken</i></strong>'"
                        :label-file-processing-complete="'OK'"
                        :server="attachmentPondServerConfig"
                        @processfile="onAttachmentPondProcessFile"
                        @processfileerror="onAttachmentPondProcessFileError"
                        @error="onAttachmentPondProcessFileError" />

                    <v-alert v-else-if="attachmentDialogCanAppendContent" type="warning" variant="tonal" class="mb-0">Upload-Token fehlt. Bitte Seite neu laden.</v-alert>
                </div>

                <template v-if="attachmentDialogCanAppendContent">
                    <div class="text-caption text-medium-emphasis mb-1">Maximale Uploadgröße je Datei: {{ maxUploadSizeLabel }}</div>
                    <div class="text-caption text-medium-emphasis mb-3">Du kannst auch einen Web-Link oder ein Web-Bild hierher ziehen.</div>
                </template>
                <v-alert
                    v-else
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-3">
                    Anhänge hinzufügen ist für dieses verlinkte Material nicht erlaubt.
                </v-alert>

                <v-alert v-if="!attachmentRows.length" type="info" variant="tonal" class="mb-0">Keine Anhänge vorhanden.</v-alert>

                <v-list v-else class="bg-transparent pa-0">
                    <v-list-item v-for="row in attachmentRows" :key="`attachment-manage-${row.id}`" class="px-0 py-2">
                        <div class="attachment-manage-row d-flex flex-column ga-2 w-100">
                            <div class="d-flex flex-wrap align-center ga-2">
                                <v-tooltip location="top">
                                    <template #activator="{ props }">
                                        <v-chip v-bind="props" size="x-small" variant="tonal" color="secondary" class="link-copy-chip" @click="copyAttachmentChipToClipboard(row)">
                                            {{ attachmentTypeLabel(row) }}
                                        </v-chip>
                                    </template>
                                    <div class="d-flex align-center ga-1">
                                        <v-icon icon="mdi-content-copy" size="14" />
                                        <span>In Zwischenablage kopieren</span>
                                    </div>
                                </v-tooltip>

                                <div class="text-caption text-medium-emphasis attachment-meta-text">
                                    {{ attachmentMeta(row) }}
                                </div>
                            </div>

                            <div v-if="attachmentSourceUrl(row)" class="text-caption attachment-source-text source-link">
                                Quelle:
                                <a :href="attachmentSourceUrl(row)" target="_blank" rel="noopener noreferrer">
                                    {{ preview(attachmentSourceUrl(row), 110) }}
                                </a>
                            </div>
                            <div v-if="attachmentDownloadedAtLabel(row)" class="text-caption text-medium-emphasis attachment-source-text">
                                Heruntergeladen: {{ attachmentDownloadedAtLabel(row) }}
                            </div>

                            <div class="d-flex flex-column flex-md-row ga-2">
                                <div class="attachment-name-field flex-grow-1">
                                    <v-text-field
                                        v-if="isAttachmentNameEditing(row.id)"
                                        :model-value="row.name"
                                        label="Titel"
                                        variant="outlined"
                                        density="comfortable"
                                        hide-details="auto"
                                        :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id) || !attachmentDialogCanEditFields"
                                        @update:modelValue="updateAttachmentDraft(row.id, $event)"
                                        @keyup.enter="saveAttachmentName(row)" />
                                    <div v-else class="attachment-name-readonly-row">
                                        <div class="attachment-name-readonly" :title="row.name">
                                            {{ row.name }}
                                        </div>
                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            density="comfortable"
                                            color="primary"
                                            variant="text"
                                            :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id) || !attachmentDialogCanEditFields"
                                            @click="startAttachmentNameEdit(row.id)" />
                                    </div>
                                </div>

                                <div class="attachment-manage-actions d-flex flex-wrap ga-2 justify-end">
                                    <v-btn
                                        v-if="isAttachmentNameEditing(row.id)"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        prepend-icon="mdi-content-save"
                                        :loading="isAttachmentSaving(row.id)"
                                        :disabled="!canSaveAttachmentName(row) || isAttachmentDeleting(row.id) || !attachmentDialogCanEditFields"
                                        @click="saveAttachmentName(row)">
                                        Speichern
                                    </v-btn>

                                    <v-btn
                                        v-if="isEditableTextAttachment(row)"
                                        icon="mdi-text-box-edit-outline"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :title="'Text bearbeiten'"
                                        :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id) || textAttachmentEditorSaving || !attachmentDialogCanEditFields"
                                        @click="openTextAttachmentEditor(row)" />

                                    <v-btn
                                        v-if="row.attachment_type === 'file' && (row.preview_url || row.download_url)"
                                        icon="mdi-eye-outline"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :title="'Vorschau'"
                                        :loading="isPreviewingAttachment(row.id)"
                                        :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                        @click="previewAttachment(row)" />

                                    <v-btn
                                        v-if="row.attachment_type === 'file'"
                                        icon="mdi-download"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :title="'Download'"
                                        :loading="isDownloadingAttachment(row.id)"
                                        :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                        @click="downloadAttachment(row)" />

                                    <v-btn
                                        v-if="isEditableTextAttachment(row)"
                                        icon="mdi-file-word-outline"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :title="'DOCX'"
                                        :loading="isDownloadingAttachment(row.id)"
                                        :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                        @click="downloadAttachmentDocx(row)" />

                                    <v-btn
                                        v-else-if="row.url"
                                        icon="mdi-open-in-new"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :title="'Öffnen'"
                                        :href="row.url"
                                        target="_blank"
                                        rel="noopener noreferrer" />

                                    <v-btn
                                        :icon="isAttachmentDeleteArmed(row.id) ? 'mdi-delete' : 'mdi-delete-outline'"
                                        size="small"
                                        :color="isAttachmentDeleteArmed(row.id) ? 'error' : 'warning'"
                                        :variant="isAttachmentDeleteArmed(row.id) ? 'flat' : 'tonal'"
                                        :title="isAttachmentDeleteArmed(row.id) ? 'Jetzt löschen' : 'Löschen'"
                                        :loading="isAttachmentDeleting(row.id)"
                                        :disabled="isAttachmentSaving(row.id) || !attachmentDialogCanDeleteAttachments"
                                        @click="removeAttachment(row)" />
                                    <v-btn
                                        v-if="isAttachmentDeleteArmed(row.id)"
                                        icon="mdi-undo"
                                        size="small"
                                        color="success"
                                        variant="text"
                                        :title="'Widerrufen'"
                                        :disabled="isAttachmentDeleting(row.id) || isAttachmentSaving(row.id) || !attachmentDialogCanDeleteAttachments"
                                        @click="cancelAttachmentDelete(row.id)" />
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>
    </v-dialog>

    <MaterialDetailDialog
        v-model="detailDialogOpen"
        :loading="detailDialogLoading"
        :card="detailDialogCard"
        :is-deleting="isDeletingDetail"
        :is-saving-edit="isSavingEdit"
        :read-only-material-actions="detailDialogReadOnlyActions"
        :delete-step="detailDeleteStep"
        :close-dialog-fn="closeDetailDialog"
        :status-color-fn="statusColor"
        :status-label-fn="statusLabel"
        :classification-labels-fn="classificationLabels"
        :detail-attachments-fn="detailAttachments"
        :attachment-display-name-fn="attachmentDisplayName"
        :copy-attachment-chip-to-clipboard-fn="copyAttachmentChipToClipboard"
        :attachment-type-label-fn="attachmentTypeLabel"
        :attachment-size-bytes-fn="attachmentSizeBytes"
        :format-bytes-fn="formatBytes"
        :attachment-source-url-fn="attachmentSourceUrl"
        :preview-fn="preview"
        :attachment-downloaded-at-label-fn="attachmentDownloadedAtLabel"
        :is-previewing-attachment-fn="isPreviewingAttachment"
        :preview-attachment-fn="previewAttachment"
        :is-downloading-attachment-fn="isDownloadingAttachment"
        :download-attachment-fn="downloadAttachment"
        :is-editable-text-attachment-fn="isEditableTextAttachment"
        :download-attachment-docx-fn="downloadAttachmentDocx"
        :format-date-time-fn="formatDateTime"
        :start-delete-flow-fn="startDetailDeleteFlow"
        :reset-delete-flow-fn="resetDetailDeleteFlow"
        :confirm-delete-fn="confirmDeleteFromDetail"
        :open-edit-fn="openEditFromDetail" />

    <v-dialog v-model="createDialogOpen" max-width="640" persistent>
        <MaterialsCreateInlineForm
            :title="createForm.title"
            :description="createForm.description"
            :material-type="createForm.type"
            :pending-attachments="createForm.pendingAttachments"
            :max-upload-size-kb="maxUploadSizeKb"
            :type-options="typeOptions"
            :can-manage-types="canManageTypeValues"
            :status="createForm.status"
            :status-options="statusOptions"
            :classifications="createForm.classifications"
            :classification-tree="classificationTree"
            :classification-editor-visible="createClassificationEditorVisible"
            :classification-toggleable="true"
            :is-saving="isSavingCreate"
            form-title="Neues Material"
            :form-subline="createDialogSubline"
            save-label="Speichern"
            cancel-label="Abbrechen"
            @update:title="createForm.title = $event"
            @update:description="createForm.description = $event"
            @update:materialType="createForm.type = $event"
            @update:pendingAttachments="createForm.pendingAttachments = $event"
            @remove-temp-upload="removeCreateTempUpload"
            @upload-error="notifyUploadError"
            @add-files="addCreatePendingAttachments"
            @update:status="createForm.status = $event"
            @update:classifications="createForm.classifications = $event"
            @update:classificationEditorVisible="createClassificationEditorVisible = $event"
            @manage-types="openTypeManager"
            @save="saveCreate"
            @cancel="closeCreateDialog" />
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
            :is-read-only="isEditLinkedReadOnly"
            :classification-read-only="isEditSharedInboxMaterial"
            :show-content-tools="canEditLinkedAppendContent"
            form-title="Material bearbeiten"
            form-subline="Titel und Beschreibung bearbeiten."
            :save-label="editSaveButtonLabel"
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
            @save="handleEditSave"
            @cancel="closeEditDialog">
            <template #extra-content>
                <div class="mt-4 d-flex flex-wrap justify-end ga-2">
                    <v-btn v-if="canEditLinkedDeleteMaterial" color="warning" variant="tonal" prepend-icon="mdi-delete" :disabled="isSavingEdit || isDeletingEditedMaterial" @click="startEditDeleteFlow">
                        Material löschen
                    </v-btn>
                </div>

                <div class="mt-4">
                    <div class="text-subtitle-2 mb-2">Anhänge</div>

                    <v-alert v-if="!attachmentRows.length" type="info" variant="tonal" class="mb-0">Keine Anhänge vorhanden.</v-alert>

                    <v-list v-else class="bg-transparent pa-0">
                        <v-list-item v-for="row in attachmentRows" :key="`edit-attachment-manage-${row.id}`" class="px-0 py-2">
                            <div class="attachment-manage-row d-flex flex-column ga-2 w-100">
                                <div class="d-flex flex-wrap align-center ga-2">
                                    <v-tooltip location="top">
                                        <template #activator="{ props }">
                                            <v-chip
                                                v-bind="props"
                                                size="x-small"
                                                variant="tonal"
                                                color="secondary"
                                                class="link-copy-chip"
                                                @click="copyAttachmentChipToClipboard(row)">
                                                {{ attachmentTypeLabel(row) }}
                                            </v-chip>
                                        </template>
                                        <div class="d-flex align-center ga-1">
                                            <v-icon icon="mdi-content-copy" size="14" />
                                            <span>In Zwischenablage kopieren</span>
                                        </div>
                                    </v-tooltip>

                                    <div class="text-caption text-medium-emphasis attachment-meta-text">
                                        {{ attachmentMeta(row) }}
                                    </div>
                                </div>

                                <div v-if="attachmentSourceUrl(row)" class="text-caption attachment-source-text source-link">
                                    Quelle:
                                    <a :href="attachmentSourceUrl(row)" target="_blank" rel="noopener noreferrer">
                                        {{ preview(attachmentSourceUrl(row), 110) }}
                                    </a>
                                </div>
                                <div v-if="attachmentDownloadedAtLabel(row)" class="text-caption text-medium-emphasis attachment-source-text">
                                    Heruntergeladen: {{ attachmentDownloadedAtLabel(row) }}
                                </div>

                                <div class="d-flex flex-column flex-md-row ga-2">
                                    <div class="attachment-name-field flex-grow-1">
                                        <v-text-field
                                            v-if="isAttachmentNameEditing(row.id)"
                                            :model-value="row.name"
                                            label="Titel"
                                            variant="outlined"
                                            density="comfortable"
                                            hide-details="auto"
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id) || isEditLinkedReadOnly"
                                            @update:modelValue="updateAttachmentDraft(row.id, $event)"
                                            @keyup.enter="saveAttachmentName(row)" />
                                        <div v-else class="attachment-name-readonly-row">
                                            <div class="attachment-name-readonly" :title="row.name">
                                                {{ row.name }}
                                            </div>
                                            <v-btn
                                                icon="mdi-pencil"
                                                size="x-small"
                                                density="comfortable"
                                                color="primary"
                                                variant="text"
                                                :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id) || isEditLinkedReadOnly"
                                                @click="startAttachmentNameEdit(row.id)" />
                                        </div>
                                    </div>

                                    <div class="attachment-manage-actions d-flex flex-wrap ga-2 justify-end">
                                        <v-btn
                                            v-if="isAttachmentNameEditing(row.id)"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            prepend-icon="mdi-content-save"
                                            :loading="isAttachmentSaving(row.id)"
                                            :disabled="isSavingEdit || !canSaveAttachmentName(row) || isAttachmentDeleting(row.id) || isEditLinkedReadOnly"
                                            @click="saveAttachmentName(row)">
                                            Speichern
                                        </v-btn>

                                        <v-btn
                                            v-if="isEditableTextAttachment(row)"
                                            icon="mdi-text-box-edit-outline"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'Text bearbeiten'"
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id) || textAttachmentEditorSaving || isEditLinkedReadOnly"
                                            @click="openTextAttachmentEditor(row)" />

                                        <v-btn
                                            v-if="isEditableTextAttachment(row)"
                                            icon="mdi-file-word-outline"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'DOCX'"
                                            :loading="isDownloadingAttachment(row.id)"
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                            @click="downloadAttachmentDocx(row)" />

                                        <v-btn
                                            v-if="row.attachment_type === 'file' && (row.preview_url || row.download_url)"
                                            icon="mdi-eye-outline"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'Vorschau'"
                                            :loading="isPreviewingAttachment(row.id)"
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                            @click="previewAttachment(row)" />

                                        <v-btn
                                            v-if="row.attachment_type === 'file'"
                                            icon="mdi-download"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'Download'"
                                            :loading="isDownloadingAttachment(row.id)"
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                            @click="downloadAttachment(row)" />

                                        <v-btn
                                            v-else-if="row.url"
                                            icon="mdi-open-in-new"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'Öffnen'"
                                            :href="row.url"
                                            target="_blank"
                                            rel="noopener noreferrer" />

                                        <v-btn
                                            v-if="canEditLinkedDeleteAttachments"
                                            :icon="isAttachmentDeleteArmed(row.id) ? 'mdi-delete' : 'mdi-delete-outline'"
                                            size="small"
                                            :color="isAttachmentDeleteArmed(row.id) ? 'error' : 'warning'"
                                            :variant="isAttachmentDeleteArmed(row.id) ? 'flat' : 'tonal'"
                                            :title="isAttachmentDeleteArmed(row.id) ? 'Jetzt löschen' : 'Löschen'"
                                            :loading="isAttachmentDeleting(row.id)"
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id)"
                                            @click="removeAttachment(row)" />
                                        <v-btn
                                            v-if="canEditLinkedDeleteAttachments && isAttachmentDeleteArmed(row.id)"
                                            icon="mdi-undo"
                                            size="small"
                                            color="success"
                                            variant="text"
                                            :title="'Widerrufen'"
                                            :disabled="isSavingEdit || isAttachmentDeleting(row.id) || isAttachmentSaving(row.id)"
                                            @click="cancelAttachmentDelete(row.id)" />
                                    </div>
                                </div>
                            </div>
                        </v-list-item>
                    </v-list>
                </div>
            </template>
        </MaterialsCreateInlineForm>
    </v-dialog>

    <v-dialog v-model="editDeleteConfirmDialogOpen" max-width="760" persistent>
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2">
                <span class="text-h6">Material wirklich löschen?</span>
                <v-spacer />
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    :disabled="isDeletingEditedMaterial || editDeleteConfirmDialogLoading"
                    @click="resetEditDeleteFlow" />
            </v-card-title>

            <v-card-text>
                <v-alert type="warning" variant="tonal" class="mb-4">
                    Das Material wird dauerhaft gelöscht. Vorhandene Anhänge werden ebenfalls gelöscht.
                </v-alert>

                <div class="text-subtitle-2 mb-1">Material</div>
                <div class="text-body-2 mb-4">{{ editDeleteConfirmMaterialTitle || editForm.title || 'Ohne Titel' }}</div>

                <div class="text-subtitle-2 mb-2">Anhänge, die mit gelöscht werden</div>

                <v-progress-linear v-if="editDeleteConfirmDialogLoading" indeterminate color="warning" rounded class="mb-3" />

                <v-alert v-else-if="!editDeleteConfirmAttachmentRows.length" type="info" variant="tonal" class="mb-0">
                    Keine Anhänge vorhanden.
                </v-alert>

                <v-list v-else class="bg-transparent pa-0">
                    <v-list-item
                        v-for="row in editDeleteConfirmAttachmentRows"
                        :key="`edit-delete-confirm-attachment-${row.id}`"
                        class="px-0 py-2">
                        <div class="d-flex flex-column ga-1 w-100">
                            <div class="text-body-2 font-weight-medium">
                                {{ attachmentDisplayName(row) }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ attachmentTypeAndSizeLabel(row) }}
                            </div>
                        </div>
                    </v-list-item>
                </v-list>
            </v-card-text>

            <v-card-actions class="px-6 pb-6 pt-2 d-flex flex-wrap justify-end ga-2">
                <v-btn
                    variant="text"
                    :disabled="isDeletingEditedMaterial || editDeleteConfirmDialogLoading"
                    @click="resetEditDeleteFlow">
                    Abbrechen
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    prepend-icon="mdi-delete"
                    :loading="isDeletingEditedMaterial"
                    :disabled="isSavingEdit || editDeleteConfirmDialogLoading"
                    @click="confirmDeleteFromEdit">
                    Ja, Material und Anhänge löschen
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="textAttachmentEditorOpen" max-width="920" persistent>
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2">
                <span class="text-h6">Text-Anhang bearbeiten</span>
                <v-spacer />
                <v-btn icon="mdi-close" variant="text" :disabled="textAttachmentEditorSaving" @click="closeTextAttachmentEditor" />
            </v-card-title>

            <v-card-text>
                <v-skeleton-loader v-if="textAttachmentEditorLoading" type="article" />

                <template v-else>
                    <v-text-field
                        v-model="textAttachmentEditorTitle"
                        label="Dokumenttitel"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-3"
                        :disabled="textAttachmentEditorSaving" />

                    <ItsRichTextEditor v-model="textAttachmentEditorBodyHtml" />

                    <v-alert v-if="textAttachmentEditorError" type="warning" variant="tonal" density="compact" class="mt-3">
                        {{ textAttachmentEditorError }}
                    </v-alert>
                </template>
            </v-card-text>

            <v-card-actions class="px-6 pb-5">
                <v-spacer />
                <v-btn variant="text" :disabled="textAttachmentEditorSaving" @click="closeTextAttachmentEditor">Abbrechen</v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-content-save-outline"
                    :loading="textAttachmentEditorSaving"
                    :disabled="textAttachmentEditorLoading || !canSaveTextAttachmentEditor"
                    @click="saveTextAttachmentEditor">
                    Speichern
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <MaterialShareDraftDialog
        v-model="shareDummyDialogOpen"
        :target="shareTarget"
        @shares-changed="loadShareIndicators" />

    <MaterialShareDialog
        v-model="shareDialogOpen"
        :target="shareTarget"
        :assignments="shareAssignments"
        :loading="shareAssignmentsLoading"
        :error="shareAssignmentsError"
        @reload-assignments="loadShareAssignments"
        @shares-changed="loadShareIndicators" />

    <MaterialTypeManagerDialog v-model="typeManagerDialogOpen" />
</template>

<script>
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'
import MaterialsCreateInlineForm from '../forms/MaterialsCreateInlineForm.vue'
import MaterialTypeManagerDialog from '../forms/MaterialTypeManagerDialog.vue'
import MaterialsOverviewAlphaList from '../overview/MaterialsOverviewAlphaList.vue'
import MaterialsOverviewFilters from '../overview/MaterialsOverviewFilters.vue'
import MaterialsOverviewGrid from '../overview/MaterialsOverviewGrid.vue'
import MaterialsOverviewHeader from '../overview/MaterialsOverviewHeader.vue'
import MaterialsOverviewList from '../overview/MaterialsOverviewList.vue'
import MaterialsOverviewPagination from '../overview/MaterialsOverviewPagination.vue'
import MaterialsOverviewSortBar from '../overview/MaterialsOverviewSortBar.vue'
import MaterialsSubjectsContentsTree from '../overview/MaterialsSubjectsContentsTree.vue'
import MaterialDetailDialog from '../overview/dialogs/MaterialDetailDialog.vue'
import MaterialShareDraftDialog from '../overview/dialogs/MaterialShareDraftDialog.vue'
import MaterialShareDialog from '../overview/dialogs/MaterialShareDialog.vue'

const FilePond = vueFilePond(FilePondPluginFileValidateType)

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
    is_linked: false,
    linked_permission: '',
    linked_permission_label: '',
    shared_rule_id: null,
    shared_material_id: null,
})

export default {
    name: 'MaterialsOverviewView',
    props: {
        forcedOverviewMode: {
            type: String,
            default: null,
        },
        hideOverviewModeToggle: {
            type: Boolean,
            default: false,
        },
        hideSubjectsOverviewPrintButton: {
            type: Boolean,
            default: false,
        },
        readOnlyMaterialActions: {
            type: Boolean,
            default: false,
        },
        enableShareButtons: {
            type: Boolean,
            default: false,
        },
        disableSharingFeatures: {
            type: Boolean,
            default: false,
        },
    },
    components: {
        FilePond,
        ItsRichTextEditor,
        MaterialsCreateInlineForm,
        MaterialTypeManagerDialog,
        MaterialsOverviewAlphaList,
        MaterialsOverviewFilters,
        MaterialsOverviewGrid,
        MaterialsOverviewHeader,
        MaterialsOverviewList,
        MaterialsOverviewPagination,
        MaterialsOverviewSortBar,
        MaterialsSubjectsContentsTree,
        MaterialDetailDialog,
        MaterialShareDraftDialog,
        MaterialShareDialog,
    },
    data() {
        return {
            materialCardStore: null,
            isLoading: false,
            isSavingCreate: false,
            isSavingEdit: false,
            isRemovingTreeClassification: false,
            isDeletingId: null,
            isUnlinkingId: null,
            isUnlinkingUnitId: null,
            isUnlinkingTopicId: null,
            createDialogOpen: false,
            editDialogOpen: false,
            attachmentDialogOpen: false,
            attachmentDialogCardId: null,
            attachmentDialogCardTitle: '',
            attachmentDialogCardContext: null,
            attachmentRows: [],
            isUploadingAttachment: false,
            csrfToken: null,
            overviewViewMode: 'list',
            overviewSortMode: 'date',
            subjectsContentsSource: 'workspace',
            isLoadingSharedObjectsForMe: false,
            sharedObjectsForMeError: '',
            sharedObjectsForMeCards: [],
            subjectsTreeSharedForMeExpanded: false,
            subjectsTreeExpandedSharedItems: {},
            openSharedHierarchyCards: {},
            isLoadingSubjectsContentsOverview: false,
            subjectsContentsOverviewItems: [],
            subjectsContentsOverviewSnapshotKey: '',
            subjectsContentsOverviewRequestId: 0,
            showSecondaryFilters: false,
            currentPage: 1,
            subjectFilter: '',
            topicFilter: '',
            unitFilter: '',
            typeFilter: '',
            statusFilter: '',
            typeManagerDialogOpen: false,
            createClassificationEditorVisible: false,
            editClassificationEditorVisible: false,
            createForm: createDefaultEditForm(),
            editForm: createDefaultEditForm(),
            downloadingAttachmentIds: [],
            previewingAttachmentIds: [],
            savingAttachmentIds: [],
            deletingAttachmentIds: [],
            detailDialogOpen: false,
            detailDialogLoading: false,
            detailDialogCard: null,
            detailDialogReadOnlyMode: false,
            detailDeleteStep: 0,
            editDeleteStep: 0,
            editDeleteConfirmDialogOpen: false,
            editDeleteConfirmDialogLoading: false,
            editDeleteConfirmMaterialTitle: '',
            editDeleteConfirmAttachmentRows: [],
            deletedMaterialRestoreItems: [],
            deletedMaterialRestoreHidden: true,
            deletedMaterialRestoreLimit: 5,
            isRestoringLastDeletedMaterial: false,
            restoringDeletedMaterialId: null,
            isPurgingDeletedMaterial: false,
            purgingDeletedMaterialId: null,
            returnToDetailOnEditCancel: false,
            detailCardForEditReturn: null,
            attachmentDeleteArmedIds: [],
            attachmentNameEditingIds: [],
            allListedAttachmentBytes: null,
            allListedAttachmentBytesLoaded: false,
            allListedAttachmentBytesLoading: false,
            allListedAttachmentBytesRequestId: 0,
            filterCountCards: [],
            filterCountCardsLoaded: false,
            filterCountCardsLoading: false,
            filterCountCardsRequestId: 0,
            filterCountSnapshotKey: '',
            textAttachmentEditorOpen: false,
            textAttachmentEditorLoading: false,
            textAttachmentEditorSaving: false,
            textAttachmentEditorAttachmentId: null,
            textAttachmentEditorTitle: '',
            textAttachmentEditorBodyHtml: '',
            textAttachmentEditorError: '',
            shareDialogOpen: false,
            shareDummyDialogOpen: false,
            shareAssignmentsLoading: false,
            shareAssignmentsError: '',
            shareAssignments: [],
            shareIndicatorsLoading: false,
            shareIndicatorMap: {},
            shareTarget: {
                level: '',
                id: null,
                label: '',
                parentLabel: '',
            },
        }
    },
    watch: {
        attachmentRows() {
            const validIds = this.attachmentRows.map((row) => Number(row?.id)).filter((id) => Number.isFinite(id) && id > 0)
            this.resetAttachmentDeleteArmed(validIds)
            this.resetAttachmentNameEditing(validIds)
        },
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
                      { value: 'inbox', label: 'Neu/Idee', color: '#607d8b' },
                      { value: 'in_progress', label: 'In Arbeit', color: '#f9a825' },
                      { value: 'done', label: 'ok', color: '#2e7d32' },
                      { value: 'update_needed', label: 'Änderung nötig', color: '#c62828' },
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
        detailDialogReadOnlyActions() {
            return this.readOnlyMaterialActions || this.detailDialogReadOnlyMode
        },
        sortedCards() {
            const list = Array.isArray(this.cards) ? [...this.cards] : []
            if (this.overviewSortMode === 'name') {
                list.sort((a, b) => {
                    const titleA = this.materialSortTitle(a)
                    const titleB = this.materialSortTitle(b)
                    const byTitle = titleA.localeCompare(titleB, 'de', { sensitivity: 'base' })
                    if (byTitle !== 0) {
                        return byTitle
                    }

                    const byUpdatedAt = this.cardUpdatedTimestamp(b) - this.cardUpdatedTimestamp(a)
                    if (byUpdatedAt !== 0) {
                        return byUpdatedAt
                    }

                    return Number(b?.id || 0) - Number(a?.id || 0)
                })
                return list
            }

            list.sort((a, b) => {
                const byUpdatedAt = this.cardUpdatedTimestamp(b) - this.cardUpdatedTimestamp(a)
                if (byUpdatedAt !== 0) {
                    return byUpdatedAt
                }

                return Number(b?.id || 0) - Number(a?.id || 0)
            })

            return list
        },
        isCompactOverview() {
            return this.overviewViewMode === 'grid'
        },
        isAlphabeticOverview() {
            return this.overviewViewMode === 'alpha'
        },
        isSubjectsContentsOverview() {
            return this.overviewViewMode === 'subjects_contents'
        },
        showSubjectsContentsSourceToggle() {
            return this.isSubjectsContentsOverview && this.canSelectSubjectsContentsSource()
        },
        isWorkspaceSubjectsContentsSource() {
            if (!this.canSelectSubjectsContentsSource()) {
                return true
            }
            return this.subjectsContentsSource === 'workspace'
        },
        isSharedSubjectsContentsSource() {
            return this.isSubjectsContentsOverview && !this.isWorkspaceSubjectsContentsSource
        },
        currentMetaPage() {
            const value = Number(this.materialCardStore?.meta?.current_page || this.currentPage)
            if (!Number.isFinite(value) || value <= 0) return 1
            return Math.round(value)
        },
        lastMetaPage() {
            const value = Number(this.materialCardStore?.meta?.last_page || 1)
            if (!Number.isFinite(value) || value <= 0) return 1
            return Math.round(value)
        },
        hasPreviousPage() {
            return this.currentMetaPage > 1
        },
        hasNextPage() {
            return this.currentMetaPage < this.lastMetaPage
        },
        totalMaterials() {
            const total = Number(this.materialCardStore?.meta?.total)
            if (Number.isFinite(total) && total > 0) {
                return total
            }
            return this.cards.length
        },
        displayedMaterials() {
            return this.cards.length
        },
        totalListedAttachmentBytes() {
            const list = Array.isArray(this.cards) ? this.cards : []
            return list.reduce((sum, card) => {
                const attachments = Array.isArray(card?.attachments) ? card.attachments : []
                const bytes = attachments.reduce((attachmentSum, attachment) => {
                    return attachmentSum + this.attachmentSizeBytes(attachment)
                }, 0)
                return sum + bytes
            }, 0)
        },
        shownListedAttachmentSizeLabel() {
            return this.formatBytes(this.totalListedAttachmentBytes)
        },
        allListedAttachmentSizeLabel() {
            if (this.allListedAttachmentBytesLoading && this.allListedAttachmentBytes === null) {
                return '...'
            }
            const bytes = Number(this.allListedAttachmentBytes)
            if (Number.isFinite(bytes) && bytes >= 0) {
                return this.formatBytes(bytes)
            }
            return this.shownListedAttachmentSizeLabel
        },
        canSaveCreate() {
            return String(this.createForm.title || '').trim().length > 0
        },
        canSaveEdit() {
            return String(this.editForm.title || '').trim().length > 0
        },
        normalizedEditLinkedPermission() {
            return this.normalizeLinkedPermission(this.editForm?.linked_permission)
        },
        isEditLinkedMaterial() {
            return this.normalizeLinkedPermission(this.editForm?.linked_permission) !== ''
                || this.editForm?.is_linked === true
        },
        isEditSharedInboxMaterial() {
            const ruleId = Number(this.editForm?.shared_rule_id || 0)
            const materialId = Number(this.editForm?.shared_material_id || 0)
            return Number.isFinite(ruleId) && ruleId > 0 && Number.isFinite(materialId) && materialId > 0
        },
        isEditLinkedReadOnly() {
            return this.isEditLinkedMaterial && this.normalizedEditLinkedPermission === 'read_only'
        },
        canEditLinkedAppendContent() {
            if (!this.isEditLinkedMaterial) return true
            return this.normalizedEditLinkedPermission === 'read_write'
                || this.normalizedEditLinkedPermission === 'full_access'
        },
        canEditLinkedDeleteAttachments() {
            if (!this.isEditLinkedMaterial) return true
            return this.normalizedEditLinkedPermission === 'full_access'
        },
        canEditLinkedDeleteMaterial() {
            return !this.isEditLinkedMaterial
        },
        editSaveButtonLabel() {
            return this.isEditLinkedReadOnly ? 'Ende' : 'Speichern'
        },
        attachmentDialogCard() {
            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(cardId) || cardId <= 0) return null
            const cardFromOverview = this.cards.find((card) => Number(card?.id) === cardId)
            if (cardFromOverview) {
                return cardFromOverview
            }

            const contextCardId = Number(this.attachmentDialogCardContext?.id)
            if (Number.isFinite(contextCardId) && contextCardId === cardId) {
                return this.attachmentDialogCardContext
            }

            return null
        },
        attachmentDialogCanEditFields() {
            return this.cardAllowsFieldEditing(this.attachmentDialogCard)
        },
        attachmentDialogCanAppendContent() {
            return this.cardAllowsAttachmentAppend(this.attachmentDialogCard)
        },
        attachmentDialogCanDeleteAttachments() {
            return this.cardAllowsAttachmentDelete(this.attachmentDialogCard)
        },
        createDialogSubline() {
            const rows = this.normalizeClassifications(this.createForm.classifications)
            if (!rows.length) return 'Gib einen Titel ein, dann kann gespeichert werden.'
            const row = rows[0]
            const parts = [row.subject, row.topic, row.unit].filter((value) => String(value || '').trim() !== '')
            if (!parts.length) return 'Gib einen Titel ein, dann kann gespeichert werden.'
            return `Vorausgewählte Zuordnung: ${parts.join(' / ')}`
        },
        defaultStatusValue() {
            return String(this.statusOptions?.[0]?.value || '').trim() || 'inbox'
        },
        maxUploadSizeKb() {
            const value = Number(this.materialCardStore?.config?.file_settings?.max_upload_size_kb)
            if (!Number.isFinite(value) || value <= 0) return 20480
            return Math.max(1, Math.round(value))
        },
        maxUploadSizeBytes() {
            return this.maxUploadSizeKb * 1024
        },
        maxUploadSizeLabel() {
            const mb = this.maxUploadSizeBytes / (1024 * 1024)
            const rounded = Math.round(mb * 100) / 100
            return `${rounded} MB`
        },
        canSaveTextAttachmentEditor() {
            if (this.textAttachmentEditorLoading || this.textAttachmentEditorSaving) return false
            return this.editorHtmlHasVisibleText(this.textAttachmentEditorBodyHtml)
        },
        attachmentPondServerConfig() {
            return {
                process: {
                    url: '/api/admin/materials/uploads/chunk',
                    method: 'POST',
                    timeout: 120000,
                    withCredentials: true,
                    headers: this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {},
                },
                patch: {
                    url: '/api/admin/materials/uploads/chunk?patch=',
                    method: 'PATCH',
                    timeout: 120000,
                    withCredentials: true,
                    headers: this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {},
                },
                revert: null,
                restore: null,
                load: null,
                fetch: null,
            }
        },
        attachmentDialogBusy() {
            return this.savingAttachmentIds.length > 0 || this.deletingAttachmentIds.length > 0 || this.isUploadingAttachment || this.textAttachmentEditorSaving
        },
        isDeletingDetail() {
            const id = Number(this.detailDialogCard?.id)
            if (!Number.isFinite(id) || id <= 0) return false
            return this.isDeletingId === id
        },
        isDeletingEditedMaterial() {
            const id = Number(this.editForm?.id)
            if (!Number.isFinite(id) || id <= 0) return false
            return this.isDeletingId === id
        },
        subjectFilterOptions() {
            const options = this.classificationTree.map((entry) => String(entry?.name || '').trim()).filter((value) => value !== '')

            const selected = String(this.subjectFilter || '').trim()
            if (selected && !options.some((option) => option.toLocaleLowerCase() === selected.toLocaleLowerCase())) {
                options.unshift(selected)
            }

            return options
        },
        hasActiveSubjectFilter() {
            return String(this.subjectFilter || '').trim() !== ''
        },
        topicFilterOptions() {
            const selectedSubject = this.normalizeFilterText(this.subjectFilter)
            if (selectedSubject === '') return []

            const subjectNode = this.classificationTree.find((entry) => this.normalizeFilterText(entry?.name).toLocaleLowerCase() === selectedSubject.toLocaleLowerCase())
            const topics = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []

            const options = topics.map((entry) => this.normalizeFilterText(entry?.name)).filter((value) => value !== '')

            const selectedTopic = this.normalizeFilterText(this.topicFilter)
            if (selectedTopic && !options.some((option) => option.toLocaleLowerCase() === selectedTopic.toLocaleLowerCase())) {
                options.unshift(selectedTopic)
            }

            return options
        },
        hasActiveTopicFilter() {
            return this.normalizeFilterText(this.topicFilter) !== ''
        },
        unitFilterOptions() {
            const selectedSubject = this.normalizeFilterText(this.subjectFilter)
            const selectedTopic = this.normalizeFilterText(this.topicFilter)
            if (selectedSubject === '' || selectedTopic === '') return []

            const subjectNode = this.classificationTree.find((entry) => this.normalizeFilterText(entry?.name).toLocaleLowerCase() === selectedSubject.toLocaleLowerCase())
            const topics = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []
            const topicNode = topics.find((entry) => this.normalizeFilterText(entry?.name).toLocaleLowerCase() === selectedTopic.toLocaleLowerCase())
            const units = Array.isArray(topicNode?.units) ? topicNode.units : []

            const options = units.map((entry) => this.normalizeFilterText(entry?.name)).filter((value) => value !== '')

            const selectedUnit = this.normalizeFilterText(this.unitFilter)
            if (selectedUnit && !options.some((option) => option.toLocaleLowerCase() === selectedUnit.toLocaleLowerCase())) {
                options.unshift(selectedUnit)
            }

            return options
        },
        hasActiveUnitFilter() {
            return this.normalizeFilterText(this.unitFilter) !== ''
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
        filterCountSourceCards() {
            if (this.filterCountCardsLoaded && Array.isArray(this.filterCountCards)) {
                return this.filterCountCards
            }

            return Array.isArray(this.cards) ? this.cards : []
        },
        subjectFilterCountMap() {
            const result = {}
            for (const subject of this.subjectFilterOptions) {
                const key = this.normalizeFilterText(subject).toLocaleLowerCase()
                if (!key || Object.prototype.hasOwnProperty.call(result, key)) continue

                result[key] = this.countCardsForFilterSet({
                    subject,
                    topic: '',
                    unit: '',
                    type: this.typeFilter,
                    status: this.statusFilter,
                })
            }
            return result
        },
        subjectAllCount() {
            return this.countCardsForFilterSet({
                subject: '',
                topic: '',
                unit: '',
                type: this.typeFilter,
                status: this.statusFilter,
            })
        },
        topicFilterCountMap() {
            const result = {}
            if (!this.hasActiveSubjectFilter) return result

            for (const topic of this.topicFilterOptions) {
                const key = this.normalizeFilterText(topic).toLocaleLowerCase()
                if (!key || Object.prototype.hasOwnProperty.call(result, key)) continue

                result[key] = this.countCardsForFilterSet({
                    subject: this.subjectFilter,
                    topic,
                    unit: '',
                    type: this.typeFilter,
                    status: this.statusFilter,
                })
            }
            return result
        },
        topicAllCount() {
            if (!this.hasActiveSubjectFilter) return 0

            return this.countCardsForFilterSet({
                subject: this.subjectFilter,
                topic: '',
                unit: '',
                type: this.typeFilter,
                status: this.statusFilter,
            })
        },
        unitFilterCountMap() {
            const result = {}
            if (!this.hasActiveTopicFilter) return result

            for (const unit of this.unitFilterOptions) {
                const key = this.normalizeFilterText(unit).toLocaleLowerCase()
                if (!key || Object.prototype.hasOwnProperty.call(result, key)) continue

                result[key] = this.countCardsForFilterSet({
                    subject: this.subjectFilter,
                    topic: this.topicFilter,
                    unit,
                    type: this.typeFilter,
                    status: this.statusFilter,
                })
            }
            return result
        },
        unitAllCount() {
            if (!this.hasActiveTopicFilter) return 0

            return this.countCardsForFilterSet({
                subject: this.subjectFilter,
                topic: this.topicFilter,
                unit: '',
                type: this.typeFilter,
                status: this.statusFilter,
            })
        },
        typeFilterCountMap() {
            const result = {}
            for (const option of this.typeFilterOptions) {
                const value = this.normalizeFilterText(option?.value)
                const key = value.toLocaleLowerCase()
                if (!key || Object.prototype.hasOwnProperty.call(result, key)) continue

                result[key] = this.countCardsForFilterSet({
                    subject: this.subjectFilter,
                    topic: this.topicFilter,
                    unit: this.unitFilter,
                    type: value,
                    status: this.statusFilter,
                })
            }
            return result
        },
        typeAllCount() {
            return this.countCardsForFilterSet({
                subject: this.subjectFilter,
                topic: this.topicFilter,
                unit: this.unitFilter,
                type: '',
                status: this.statusFilter,
            })
        },
        statusFilterCountMap() {
            const result = {}
            for (const option of this.statusFilterOptions) {
                const value = this.normalizeFilterText(option?.value)
                const key = value.toLocaleLowerCase()
                if (!key || Object.prototype.hasOwnProperty.call(result, key)) continue

                result[key] = this.countCardsForFilterSet({
                    subject: this.subjectFilter,
                    topic: this.topicFilter,
                    unit: this.unitFilter,
                    type: this.typeFilter,
                    status: value,
                })
            }
            return result
        },
        statusAllCount() {
            return this.countCardsForFilterSet({
                subject: this.subjectFilter,
                topic: this.topicFilter,
                unit: this.unitFilter,
                type: this.typeFilter,
                status: '',
            })
        },
    },
    async beforeMount() {
        const metaToken = document?.head?.querySelector?.('meta[name="csrf-token"]')?.content
        this.csrfToken = String(metaToken || '').trim() || null
        try {
            const storedMode = window?.localStorage?.getItem?.('materials.overview.mode')
            const allowedModes = ['list', 'grid', 'alpha', 'subjects_contents']
            this.overviewViewMode = allowedModes.includes(storedMode) ? storedMode : 'list'
        } catch {
            this.overviewViewMode = 'list'
        }
        const forcedMode = String(this.forcedOverviewMode || '').trim()
        if (['list', 'grid', 'alpha', 'subjects_contents'].includes(forcedMode)) {
            this.overviewViewMode = forcedMode
        }
        try {
            const storedSortMode = window?.localStorage?.getItem?.('materials.overview.sort')
            const allowedSortModes = ['date', 'name']
            this.overviewSortMode = allowedSortModes.includes(storedSortMode) ? storedSortMode : 'date'
        } catch {
            this.overviewSortMode = 'date'
        }
        try {
            const storedSecondaryFilters = window?.localStorage?.getItem?.('materials.overview.secondary_filters')
            this.showSecondaryFilters = storedSecondaryFilters === '1'
        } catch {
            this.showSecondaryFilters = false
        }
        try {
            const response = await axios.get('/api/admin/token')
            const token = String(response?.data || '').trim()
            if (token) {
                this.csrfToken = token
            }
        } catch {
            // Falls Token-Refresh fehlschlägt, wird der vorhandene Meta-Token verwendet.
        }

        this.materialCardStore = useMaterialCardStore()
        await this.materialCardStore.loadConfig()
        this.subjectFilter = String(this.materialCardStore?.filters?.subject || '').trim()
        this.topicFilter = String(this.materialCardStore?.filters?.topic || '').trim()
        this.unitFilter = String(this.materialCardStore?.filters?.unit || '').trim()
        if (this.subjectFilter === '') {
            this.topicFilter = ''
            this.unitFilter = ''
        } else if (this.topicFilter === '') {
            this.unitFilter = ''
        }
        this.typeFilter = String(this.materialCardStore?.filters?.type || '').trim()
        this.statusFilter = String(this.materialCardStore?.filters?.status || '').trim()
        await this.loadCards()
        await this.refreshLastDeletedMaterialRestoreInfo()
    },
    methods: {
        openShareDialog(target = {}) {
            this.shareTarget = {
                level: String(target?.level || '').trim(),
                id: Number.isFinite(Number(target?.id)) ? Number(target.id) : null,
                label: String(target?.label || '').trim(),
                parentLabel: String(target?.parentLabel || '').trim(),
                kindLabel: String(target?.kindLabel || '').trim(),
                kindColor: String(target?.kindColor || '').trim(),
                statusLabel: String(target?.statusLabel || '').trim(),
                statusColor: String(target?.statusColor || '').trim(),
                attachmentsCount: Number.isFinite(Number(target?.attachmentsCount)) ? Number(target.attachmentsCount) : null,
            }

            if (!this.enableShareButtons) {
                this.shareDialogOpen = false
                this.shareDummyDialogOpen = true
                return
            }

            this.shareDummyDialogOpen = false
            this.shareAssignments = []
            this.shareAssignmentsError = ''
            this.shareDialogOpen = true
            this.loadShareAssignments()
        },
        openCreateDialogFromTree(payload = {}) {
            if (this.readOnlyMaterialActions) return
            if (this.isLoading || this.isSavingCreate || this.isSavingEdit || this.isDeletingId !== null) return

            const subject = String(payload?.subject || '').trim()
            const topic = String(payload?.topic || '').trim()
            const unit = String(payload?.unit || '').trim()

            this.createForm = createDefaultEditForm()
            this.createForm.status = this.defaultStatusValue
            this.createForm.classifications = [
                {
                    subject,
                    topic,
                    unit,
                },
            ]
            this.createClassificationEditorVisible = true
            this.createDialogOpen = true
        },
        shareIndicatorKey(scopeType, scopeId) {
            const type = String(scopeType || '').trim()
            const id = Number(scopeId || 0)
            if (!type || id <= 0) return ''
            return `${type}:${id}`
        },
        sharePermissionRank(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 3
            if (normalized === 'read_write') return 2
            if (normalized === 'read_only') return 1
            return 1
        },
        sharePermissionColor(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'error'
            if (normalized === 'read_write') return 'warning'
            if (normalized === 'read_only') return 'primary'
            return 'primary'
        },
        shareIndicatorColor(level, id) {
            const key = this.shareIndicatorKey(level, id)
            if (!key) return ''
            return String(this.shareIndicatorMap?.[key]?.color || '')
        },
        normalizeLinkedPermission(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'full_access'
            if (normalized === 'read_write') return 'read_write'
            if (normalized === 'read_only') return 'read_only'
            return ''
        },
        linkedPermissionForCard(card) {
            if (!card || typeof card !== 'object') return ''
            const normalized = this.normalizeLinkedPermission(card?.linked_permission)
            if (normalized !== '') return normalized
            return card?.is_linked === true ? 'read_only' : ''
        },
        linkedPermissionLabelForPermission(permission) {
            const normalized = this.normalizeLinkedPermission(permission)
            if (normalized === 'full_access') return 'VOLLZUGRIFF'
            if (normalized === 'read_write') return 'LESEN/SCHREIBEN'
            return 'NUR LESEN'
        },
        sharedRuleCard(ruleId) {
            const id = Number(ruleId)
            if (!Number.isFinite(id) || id <= 0) return null
            const cards = Array.isArray(this.sharedObjectsForMeCards) ? this.sharedObjectsForMeCards : []
            return cards.find((card) => Number(card?.ruleId || 0) === id) || null
        },
        sharedInboxContextForCard(card, fallbackRuleId = null) {
            const ruleId = Number(card?.shared_rule_id || fallbackRuleId || 0)
            const materialId = Number(card?.shared_material_id || card?.id || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return null
            if (!Number.isFinite(materialId) || materialId <= 0) return null

            const sharedRuleCard = this.sharedRuleCard(ruleId)
            const permission = this.normalizeLinkedPermission(
                card?.linked_permission ?? card?.linkedPermission ?? sharedRuleCard?.permission
            ) || 'read_only'
            const permissionLabel = String(
                card?.linked_permission_label
                || card?.linkedPermissionLabel
                || sharedRuleCard?.permissionLabel
                || this.linkedPermissionLabelForPermission(permission)
            ).trim() || this.linkedPermissionLabelForPermission(permission)

            return {
                ruleId,
                materialId,
                permission,
                permissionLabel,
            }
        },
        sharedInboxContextForAttachment(attachment) {
            return this.sharedInboxContextForCard({
                ...(this.attachmentDialogCardContext || this.attachmentDialogCard || {}),
                shared_rule_id: attachment?.shared_rule_id ?? this.attachmentDialogCardContext?.shared_rule_id,
                shared_material_id: attachment?.shared_material_id ?? this.attachmentDialogCardContext?.shared_material_id,
            })
        },
        cardAllowsFieldEditing(card) {
            const permission = this.linkedPermissionForCard(card)
            return permission === '' || permission === 'read_write' || permission === 'full_access'
        },
        cardAllowsAttachmentAppend(card) {
            return this.cardAllowsFieldEditing(card)
        },
        cardAllowsAttachmentDelete(card) {
            const permission = this.linkedPermissionForCard(card)
            return permission === '' || permission === 'full_access'
        },
        notifyLinkedPermissionRestriction(message) {
            const notification = useNotificationStore()
            notification.notify({
                message: String(message || '').trim() || 'Für dieses verlinkte Material ist diese Aktion nicht erlaubt.',
                type: 'warning',
                timeout: 3000,
            })
        },
        async loadShareIndicators() {
            if (!this.enableShareButtons) {
                this.shareIndicatorMap = {}
                return
            }

            this.shareIndicatorsLoading = true
            try {
                const response = await axios.get('/api/admin/materials/shares')
                const rows = Array.isArray(response.data?.data) ? response.data.data : []
                const nextMap = {}

                for (const row of rows) {
                    const key = this.shareIndicatorKey(row?.scope_type, row?.scope_id)
                    if (!key) continue

                    const targets = Array.isArray(row?.targets) ? row.targets : []
                    let bestRank = 0
                    let bestPermission = 'read_only'
                    for (const target of targets) {
                        const permission = String(target?.permission || 'read_only').trim()
                        const rank = this.sharePermissionRank(permission)
                        if (rank > bestRank) {
                            bestRank = rank
                            bestPermission = permission
                        }
                    }
                    if (bestRank <= 0) continue

                    const existingRank = Number(nextMap[key]?.rank || 0)
                    if (bestRank > existingRank) {
                        nextMap[key] = {
                            rank: bestRank,
                            permission: bestPermission,
                            color: this.sharePermissionColor(bestPermission),
                        }
                    }
                }

                this.shareIndicatorMap = nextMap
            } catch {
                this.shareIndicatorMap = {}
            } finally {
                this.shareIndicatorsLoading = false
            }
        },
        shareScopeType(level) {
            return (
                {
                    all: 'all',
                    subject: 'subject',
                    topic: 'topic',
                    unit: 'unit',
                    material: 'material',
                }[String(level || '').trim()] || null
            )
        },
        async loadShareAssignments() {
            const scopeType = this.shareScopeType(this.shareTarget.level)
            const scopeId = Number(this.shareTarget.id || 0)
            if (!scopeType) {
                this.shareAssignments = []
                return
            }

            if (scopeType === 'all') {
                this.shareAssignmentsLoading = true
                this.shareAssignmentsError = ''
                try {
                    const response = await axios.get('/api/admin/materials/shares', {
                        params: {
                            scope_type: 'all',
                        },
                    })
                    this.shareAssignments = Array.isArray(response.data?.data) ? response.data.data : []
                } catch (error) {
                    this.shareAssignments = []
                    this.shareAssignmentsError = error?.response?.data?.message || 'Freigaben konnten nicht geladen werden.'
                } finally {
                    this.shareAssignmentsLoading = false
                }
                return
            }

            if (scopeId <= 0) {
                this.shareAssignments = []
                return
            }

            this.shareAssignmentsLoading = true
            this.shareAssignmentsError = ''
            try {
                const response = await axios.get('/api/admin/materials/shares', {
                    params: {
                        scope_type: scopeType,
                        scope_id: scopeId,
                    },
                })
                const rows = Array.isArray(response.data?.data) ? response.data.data : []
                this.shareAssignments = rows.filter((row) => String(row?.scope_type || '') === scopeType && Number(row?.scope_id || 0) === scopeId)
            } catch (error) {
                this.shareAssignments = []
                this.shareAssignmentsError = error?.response?.data?.message || 'Vorhandene Freigaben konnten nicht geladen werden.'
            } finally {
                this.shareAssignmentsLoading = false
            }
        },
        setOverviewMode(value) {
            const nextMode = ['list', 'grid', 'alpha', 'subjects_contents'].includes(String(value)) ? String(value) : 'list'
            if (nextMode === this.overviewViewMode) {
                if (nextMode === 'subjects_contents') {
                    this.loadSubjectsContentsOverview({ force: true })
                    this.loadSharedObjectsForMe()
                }
                return
            }
            this.overviewViewMode = nextMode
            if (nextMode === 'subjects_contents' && this.canSelectSubjectsContentsSource()) {
                this.subjectsContentsSource = 'workspace'
            }
            try {
                window?.localStorage?.setItem?.('materials.overview.mode', nextMode)
            } catch {
                // Falls localStorage nicht verfügbar ist, nur im aktuellen Zustand bleiben.
            }
            if (nextMode === 'subjects_contents') {
                this.loadSubjectsContentsOverview({ force: true })
                this.loadSharedObjectsForMe()
            }
        },
        canSelectSubjectsContentsSource() {
            if (this.disableSharingFeatures) {
                return false
            }

            return String(this.forcedOverviewMode || '').trim() === ''
        },
        setSubjectsContentsSource(value) {
            if (!this.canSelectSubjectsContentsSource()) {
                this.subjectsContentsSource = 'workspace'
                return
            }

            if (value !== 'workspace' && value !== 'shared') {
                return
            }

            const nextSource = String(value)
            if (nextSource === this.subjectsContentsSource) {
                return
            }

            this.subjectsContentsSource = nextSource

            if (nextSource === 'workspace' && this.isSubjectsContentsOverview) {
                this.loadSubjectsContentsOverview({ force: true })
                this.loadSharedObjectsForMe()
                return
            }
            if (nextSource === 'shared' && this.isSubjectsContentsOverview) {
                this.loadSharedObjectsForMe()
            }
        },
        sharedScopeIcon(scopeType) {
            const normalized = String(scopeType || '').trim().toLocaleLowerCase()
            if (normalized === 'all') return 'mdi-briefcase-outline'
            if (normalized === 'subject') return 'mdi-book-open-page-variant-outline'
            if (normalized === 'topic') return 'mdi-shape-outline'
            if (normalized === 'unit') return 'mdi-bookmark-outline'
            if (normalized === 'material') return 'mdi-file-document-outline'
            return 'mdi-share-variant-outline'
        },
        sharedPermissionColor(permission) {
            const normalized = String(permission || '').trim().toLocaleLowerCase()
            if (normalized === 'full_access') return 'error'
            if (normalized === 'read_write') return 'warning'
            return 'primary'
        },
        activeWorkspaceName() {
            return String(this.materialCardStore?.config?.workspace?.name || '').trim()
        },
        resolveSharedScopePathLabel(scopeType, scopePathLabel) {
            const normalizedScopeType = String(scopeType || '').trim().toLocaleLowerCase()
            if (normalizedScopeType !== 'all') {
                return scopePathLabel
            }

            const workspaceName = this.activeWorkspaceName()

            return workspaceName || scopePathLabel
        },
        normalizeSharedObjectsForMeResponse(rows) {
            if (!Array.isArray(rows)) return []

            const cards = []
            for (const userRow of rows) {
                const fromUserLabel =
                    String(userRow?.label || '').trim() ||
                    String(userRow?.email || '').trim() ||
                    'Benutzer'
                const fromSchoolLabel = String(userRow?.school_label || '').trim()
                const fallbackSharedAt = String(userRow?.last_shared_at || '').trim()
                const sharedItems = Array.isArray(userRow?.shared_items) ? userRow.shared_items : []

                for (const item of sharedItems) {
                    const ruleId = Number(item?.rule_id || 0)
                    if (!Number.isFinite(ruleId) || ruleId <= 0 || item?.is_archived) {
                        continue
                    }

                    const scopeType = String(item?.scope_type || '').trim() || 'all'
                    const scopeLabel = String(item?.scope_label || '').trim() || 'Bereich'
                    const scopeObjectLabel = String(item?.scope_object_label || '').trim() || 'Freigabe'
                    const rawScopePathLabel = String(item?.scope_path_label || '').trim() || ''
                    const scopePathLabel = this.resolveSharedScopePathLabel(scopeType, rawScopePathLabel)
                    const permission = String(item?.permission || '').trim() || 'read_only'
                    const permissionLabel = String(item?.permission_label || '').trim() || 'NUR LESEN'
                    const sharedAt = String(item?.updated_at || '').trim() || fallbackSharedAt
                    const hierarchy = this.normalizeSharedHierarchy(item?.hierarchy)
                    const materialsCount = this.sharedHierarchyMaterialCount(hierarchy)

                    cards.push({
                        ruleId,
                        scopeType,
                        scopeLabel,
                        scopeObjectLabel,
                        scopePathLabel,
                        permission,
                        permissionLabel,
                        sharedAt,
                        fromUserLabel,
                        fromSchoolLabel,
                        hierarchy,
                        materialsCount,
                    })
                }
            }

            cards.sort((left, right) => {
                const leftTime = Date.parse(String(left?.sharedAt || '')) || 0
                const rightTime = Date.parse(String(right?.sharedAt || '')) || 0
                if (leftTime !== rightTime) {
                    return rightTime - leftTime
                }
                return Number(right?.ruleId || 0) - Number(left?.ruleId || 0)
            })

            return cards
        },
        normalizeSharedHierarchy(hierarchy) {
            if (!Array.isArray(hierarchy)) return []

            return hierarchy.map((subject) => ({
                id: Number(subject?.id || 0),
                name: String(subject?.name || '').trim() || 'Ohne Fach',
                materials: Array.isArray(subject?.materials) ? subject.materials.map((material) => this.normalizeSharedHierarchyMaterial(material)) : [],
                topics: Array.isArray(subject?.topics)
                    ? subject.topics.map((topic) => ({
                        id: Number(topic?.id || 0),
                        name: String(topic?.name || '').trim() || 'Ohne Thema',
                        materials: Array.isArray(topic?.materials) ? topic.materials.map((material) => this.normalizeSharedHierarchyMaterial(material)) : [],
                        units: Array.isArray(topic?.units)
                            ? topic.units.map((unit) => ({
                                id: Number(unit?.id || 0),
                                name: String(unit?.name || '').trim() || 'Ohne Einheit',
                                materials: Array.isArray(unit?.materials) ? unit.materials.map((material) => this.normalizeSharedHierarchyMaterial(material)) : [],
                            }))
                            : [],
                    }))
                    : [],
            }))
        },
        normalizeSharedHierarchyMaterial(material) {
            return {
                id: Number(material?.id || 0),
                title: String(material?.title || '').trim() || 'Material',
                icon: String(material?.icon || '').trim(),
                typeLabel: String(material?.type_label || material?.typeLabel || material?.type || '').trim(),
                typeColor: String(material?.type_color || material?.typeColor || '').trim(),
                status: String(material?.status || '').trim(),
                statusLabel: String(material?.status_label || material?.statusLabel || '').trim(),
                statusColor: String(material?.status_color || material?.statusColor || '').trim(),
                attachmentsCount: Math.max(0, Number(material?.attachments_count ?? material?.attachmentsCount ?? 0) || 0),
                attachments: Array.isArray(material?.attachments) ? material.attachments.map((attachment) => ({ ...attachment })) : null,
            }
        },
        async fetchSharedMaterialAttachments(ruleId, materialId) {
            const response = await axios.get('/api/admin/materials/shares/inbox/material-attachments', {
                params: {
                    rule_id: Number(ruleId),
                    material_id: Number(materialId),
                },
            })
            return Array.isArray(response?.data?.data) ? response.data.data : []
        },
        async fetchSharedMaterialDetail(ruleId, materialId) {
            const response = await axios.get('/api/admin/materials/shares/inbox/material-detail', {
                params: {
                    rule_id: Number(ruleId),
                    material_id: Number(materialId),
                },
            })
            return response?.data?.data || null
        },
        async performSharedInboxMutation(config = {}) {
            const notification = useNotificationStore()

            try {
                const response = await axios(config)
                if (config.successMessage) {
                    notification.notify({
                        message: config.successMessage,
                        type: 'success',
                        timeout: 2000,
                    })
                }

                return response?.data?.data ?? response?.data ?? null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || config.errorMessage || 'Aktion konnte nicht ausgeführt werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return null
            }
        },
        async updateSharedMaterial(context, data) {
            return this.performSharedInboxMutation({
                method: 'put',
                url: '/api/admin/materials/shares/inbox/material-detail',
                data: {
                    rule_id: Number(context?.ruleId || 0),
                    material_id: Number(context?.materialId || 0),
                    data,
                },
                successMessage: 'Materialkarte aktualisiert.',
                errorMessage: 'Fehler beim Aktualisieren der Materialkarte.',
            })
        },
        async addSharedLinkAttachment(context, data) {
            return this.performSharedInboxMutation({
                method: 'post',
                url: '/api/admin/materials/shares/inbox/material-attachments/link',
                data: {
                    rule_id: Number(context?.ruleId || 0),
                    material_id: Number(context?.materialId || 0),
                    data,
                },
                successMessage: 'Link-Anhang hinzugefügt.',
                errorMessage: 'Fehler beim Hinzufügen des Link-Anhangs.',
            })
        },
        async addSharedImageUrlAttachment(context, url, name = '') {
            const normalizedUrl = String(url ?? '').trim().slice(0, 2048)
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!normalizedUrl) {
                const notification = useNotificationStore()
                notification.notify({
                    message: 'Ungültige Bild-URL.',
                    type: 'warning',
                    timeout: 2500,
                })

                return null
            }

            return this.performSharedInboxMutation({
                method: 'post',
                url: '/api/admin/materials/shares/inbox/material-attachments/image-url',
                data: {
                    rule_id: Number(context?.ruleId || 0),
                    material_id: Number(context?.materialId || 0),
                    data: {
                        url: normalizedUrl,
                        name: normalizedName || null,
                    },
                },
                successMessage: 'Bild-Anhang hinzugefügt.',
                errorMessage: 'Fehler beim Importieren des Bildes.',
            })
        },
        async addSharedFileAttachment(context, file, name = '') {
            const formData = new FormData()
            formData.append('rule_id', String(Number(context?.ruleId || 0)))
            formData.append('material_id', String(Number(context?.materialId || 0)))
            formData.append('file', file)
            if (name) {
                formData.append('name', name)
            }

            return this.performSharedInboxMutation({
                method: 'post',
                url: '/api/admin/materials/shares/inbox/material-attachments/file',
                data: formData,
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                successMessage: 'Datei-Anhang hinzugefügt.',
                errorMessage: 'Fehler beim Datei-Upload.',
            })
        },
        async addSharedTempFileAttachment(context, uploadId, name = '') {
            return this.performSharedInboxMutation({
                method: 'post',
                url: '/api/admin/materials/shares/inbox/material-attachments/file-temp',
                data: {
                    rule_id: Number(context?.ruleId || 0),
                    material_id: Number(context?.materialId || 0),
                    data: {
                        upload_id: String(uploadId ?? '').trim(),
                        name: String(name ?? '').trim().slice(0, 255) || null,
                    },
                },
                successMessage: 'Datei-Anhang hinzugefügt.',
                errorMessage: 'Fehler beim Datei-Upload.',
            })
        },
        async renameSharedAttachment(context, attachmentId, name) {
            return this.performSharedInboxMutation({
                method: 'patch',
                url: `/api/admin/materials/shares/inbox/material-attachments/${Number(attachmentId || 0)}`,
                data: {
                    rule_id: Number(context?.ruleId || 0),
                    material_id: Number(context?.materialId || 0),
                    data: {
                        name: String(name ?? '').trim().slice(0, 255),
                    },
                },
                successMessage: 'Anhang umbenannt.',
                errorMessage: 'Fehler beim Umbenennen des Anhangs.',
            })
        },
        async fetchSharedTextAttachmentContent(context, attachmentId) {
            return this.performSharedInboxMutation({
                method: 'get',
                url: `/api/admin/materials/shares/inbox/material-attachments/${Number(attachmentId || 0)}/text-content`,
                params: {
                    rule_id: Number(context?.ruleId || 0),
                    material_id: Number(context?.materialId || 0),
                },
                errorMessage: 'Text-Anhang konnte nicht geladen werden.',
            })
        },
        async updateSharedTextAttachmentContent(context, attachmentId, contentHtml, name = '') {
            return this.performSharedInboxMutation({
                method: 'patch',
                url: `/api/admin/materials/shares/inbox/material-attachments/${Number(attachmentId || 0)}/text-content`,
                data: {
                    rule_id: Number(context?.ruleId || 0),
                    material_id: Number(context?.materialId || 0),
                    data: {
                        content_html: String(contentHtml ?? '').trim(),
                        name: String(name ?? '').trim().slice(0, 255) || null,
                    },
                },
                successMessage: 'Text-Anhang aktualisiert.',
                errorMessage: 'Text-Anhang konnte nicht gespeichert werden.',
            })
        },
        async openSharedMaterialDetail(ruleId, material) {
            const normalizedRuleId = Number(ruleId)
            const cardId = Number(material?.id)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) return
            if (!Number.isFinite(cardId) || cardId <= 0) return

            const sharedContext = this.sharedInboxContextForCard(material, normalizedRuleId)
            const permission = sharedContext?.permission || 'read_only'
            const permissionLabel = sharedContext?.permissionLabel || this.linkedPermissionLabelForPermission(permission)

            this.detailDialogCard = this.sanitizeDialogCard({
                id: cardId,
                shared_rule_id: normalizedRuleId,
                shared_material_id: cardId,
                title: String(material?.title || '').trim() || 'Material',
                attachments: [],
                classifications: [],
                is_linked: true,
                linked_permission: permission,
                linked_permission_label: permissionLabel,
            })
            this.detailDialogReadOnlyMode = permission === 'read_only'
            this.detailDialogOpen = true
            this.detailDialogLoading = true
            this.detailDeleteStep = 0

            try {
                const detail = await this.fetchSharedMaterialDetail(normalizedRuleId, cardId)
                if (!this.detailDialogOpen) return
                if (Number(this.detailDialogCard?.id || 0) !== cardId) return
                if (detail && typeof detail === 'object') {
                    this.detailDialogCard = this.sanitizeDialogCard(detail)
                    this.detailDialogReadOnlyMode = this.normalizeLinkedPermission(detail?.linked_permission) === 'read_only'
                }
            } catch (error) {
                const notification = useNotificationStore()
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Material-Details konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.detailDialogLoading = false
            }
        },
        openSharedMaterialFromTree(payload = {}) {
            const ruleId = Number(payload?.ruleId)
            const material = payload?.material || null
            if (!Number.isFinite(ruleId) || ruleId <= 0 || !material) return

            return this.openSharedMaterialDetail(ruleId, material)
        },
        async openSharedMaterialAttachments(ruleId, material) {
            const normalizedRuleId = Number(ruleId)
            const cardId = Number(material?.id)
            if (!Number.isFinite(normalizedRuleId) || normalizedRuleId <= 0) return
            if (!Number.isFinite(cardId) || cardId <= 0) return

            const sharedContext = this.sharedInboxContextForCard(material, normalizedRuleId)

            let attachments = []
            try {
                attachments = await this.fetchSharedMaterialAttachments(normalizedRuleId, cardId)
            } catch (error) {
                const notification = useNotificationStore()
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Anhänge konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
                return
            }

            await this.openAttachmentManager({
                id: cardId,
                shared_rule_id: normalizedRuleId,
                shared_material_id: cardId,
                title: String(material?.title || '').trim() || 'Material',
                attachments,
                is_linked: true,
                linked_permission: sharedContext?.permission || 'read_only',
                linked_permission_label: sharedContext?.permissionLabel || 'NUR LESEN',
            })
        },
        openSharedMaterialAttachmentsFromTree(payload = {}) {
            const ruleId = Number(payload?.ruleId)
            const material = payload?.material || null
            if (!Number.isFinite(ruleId) || ruleId <= 0 || !material) return

            return this.openSharedMaterialAttachments(ruleId, material)
        },
        sharedHierarchyMaterialCount(hierarchy) {
            if (!Array.isArray(hierarchy)) return 0

            let count = 0
            for (const subject of hierarchy) {
                const subjectMaterials = Array.isArray(subject?.materials) ? subject.materials : []
                count += subjectMaterials.length
                const topics = Array.isArray(subject?.topics) ? subject.topics : []
                for (const topic of topics) {
                    const topicMaterials = Array.isArray(topic?.materials) ? topic.materials : []
                    count += topicMaterials.length
                    const units = Array.isArray(topic?.units) ? topic.units : []
                    for (const unit of units) {
                        const materials = Array.isArray(unit?.materials) ? unit.materials : []
                        count += materials.length
                    }
                }
            }

            return count
        },
        sharedHierarchyKey(ruleId) {
            return `shared-rule-${Number(ruleId || 0)}`
        },
        subjectsTreeSharedItemKey(ruleId) {
            const id = Number(ruleId || 0)
            if (!Number.isFinite(id) || id <= 0) return ''
            return `shared-item-${id}`
        },
        toggleSubjectsTreeSharedForMeExpanded() {
            this.subjectsTreeSharedForMeExpanded = !this.subjectsTreeSharedForMeExpanded
        },
        toggleSubjectsTreeSharedItemExpanded(ruleId) {
            const key = this.subjectsTreeSharedItemKey(ruleId)
            if (!key) return

            this.subjectsTreeExpandedSharedItems = {
                ...this.subjectsTreeExpandedSharedItems,
                [key]: this.subjectsTreeExpandedSharedItems[key] !== true,
            }
        },
        canExpandSharedHierarchy(item) {
            return Array.isArray(item?.hierarchy) && item.hierarchy.length > 0
        },
        isSharedHierarchyOpen(ruleId) {
            const key = this.sharedHierarchyKey(ruleId)
            return key ? !!this.openSharedHierarchyCards[key] : false
        },
        toggleSharedHierarchy(ruleId) {
            const key = this.sharedHierarchyKey(ruleId)
            if (!key) return
            const isOpen = !!this.openSharedHierarchyCards[key]
            const next = { ...this.openSharedHierarchyCards }
            if (isOpen) {
                delete next[key]
            } else {
                next[key] = true
            }
            this.openSharedHierarchyCards = next
        },
        async loadSharedObjectsForMe() {
            this.isLoadingSharedObjectsForMe = true
            this.sharedObjectsForMeError = ''
            const previousOpenHierarchyCards = { ...this.openSharedHierarchyCards }
            try {
                const response = await axios.get('/api/admin/materials/shares/inbox-users')
                const rows = Array.isArray(response?.data?.data) ? response.data.data : []
                const nextCards = this.normalizeSharedObjectsForMeResponse(rows)
                const nextOpenHierarchyCards = {}
                const nextExpandedSharedItems = {}
                for (const card of nextCards) {
                    const key = this.sharedHierarchyKey(card?.ruleId)
                    if (key && previousOpenHierarchyCards[key]) {
                        nextOpenHierarchyCards[key] = true
                    }

                    const sharedItemKey = this.subjectsTreeSharedItemKey(card?.ruleId)
                    if (sharedItemKey && this.subjectsTreeExpandedSharedItems[sharedItemKey] === true) {
                        nextExpandedSharedItems[sharedItemKey] = true
                    }
                }
                this.sharedObjectsForMeCards = nextCards
                this.openSharedHierarchyCards = nextOpenHierarchyCards
                this.subjectsTreeExpandedSharedItems = nextExpandedSharedItems
            } catch (error) {
                this.sharedObjectsForMeCards = []
                this.openSharedHierarchyCards = {}
                this.subjectsTreeExpandedSharedItems = {}
                this.sharedObjectsForMeError = error?.response?.data?.message || 'Freigaben konnten nicht geladen werden.'
            } finally {
                this.isLoadingSharedObjectsForMe = false
            }
        },
        openSubjectsOverviewScreen() {
            const filters = this.buildSubjectsContentsOverviewFilters()
            const query = {
                source: 'overview',
            }
            for (const [key, value] of Object.entries(filters || {})) {
                const filterKey = String(key || '').trim()
                if (!filterKey || filterKey === 'page') continue
                const normalizedValue = this.normalizeFilterText(value)
                if (normalizedValue === '') continue
                query[filterKey] = normalizedValue
            }

            this.$router.push({
                path: '/admin/materials/subjects-overview',
                query,
            })
        },
        setOverviewSortMode(value) {
            const nextSortMode = ['date', 'name'].includes(String(value)) ? String(value) : 'date'
            if (nextSortMode === this.overviewSortMode) return
            this.overviewSortMode = nextSortMode
            try {
                window?.localStorage?.setItem?.('materials.overview.sort', nextSortMode)
            } catch {
                // Falls localStorage nicht verfügbar ist, nur im aktuellen Zustand bleiben.
            }
        },
        toggleSecondaryFilters() {
            this.showSecondaryFilters = !this.showSecondaryFilters
            try {
                window?.localStorage?.setItem?.('materials.overview.secondary_filters', this.showSecondaryFilters ? '1' : '0')
            } catch {
                // Falls localStorage nicht verfügbar ist, nur im aktuellen Zustand bleiben.
            }
        },
        materialSortTitle(card) {
            const title = String(card?.title || '').trim()
            return title !== '' ? title : 'Ohne Titel'
        },
        cardUpdatedTimestamp(card) {
            const raw = String(card?.updated_at || '').trim()
            if (!raw) return 0

            const parsed = new Date(raw.replace(' ', 'T'))
            const time = parsed.getTime()
            return Number.isFinite(time) ? time : 0
        },
        alphabeticAssignmentLine(card) {
            const labels = this.classificationLabels(card)
            if (!Array.isArray(labels) || labels.length === 0) {
                return 'Ohne Zuordnung'
            }

            const first = String(labels[0] || '').trim()
            if (!first) {
                return 'Ohne Zuordnung'
            }

            if (labels.length === 1) {
                return first
            }

            return `${first} (+${labels.length - 1})`
        },
        async loadCards(page = null, options = {}) {
            if (this.isLoading) return
            let targetPage = Number(page ?? this.currentPage)
            if (!Number.isFinite(targetPage) || targetPage <= 0) {
                targetPage = 1
            }
            targetPage = Math.max(1, Math.round(targetPage))

            this.isLoading = true
            const loaded = await this.materialCardStore.index(targetPage)
            if (loaded) {
                let currentPage = Number(this.materialCardStore?.meta?.current_page || targetPage)
                let lastPage = Number(this.materialCardStore?.meta?.last_page || currentPage)

                if (Number.isFinite(lastPage) && lastPage > 0 && targetPage > lastPage) {
                    await this.materialCardStore.index(lastPage)
                    currentPage = Number(this.materialCardStore?.meta?.current_page || lastPage)
                    lastPage = Number(this.materialCardStore?.meta?.last_page || currentPage)
                }

                if (!Number.isFinite(currentPage) || currentPage <= 0) {
                    currentPage = 1
                }
                this.currentPage = Math.max(1, Math.round(currentPage))
                const forceFilterCountRefresh = options?.forceFilterCountRefresh === true
                this.refreshFilterCountCards({ force: forceFilterCountRefresh })
                this.refreshAllListedAttachmentBytes({ force: forceFilterCountRefresh })
                if (this.isSubjectsContentsOverview) {
                    await this.loadSubjectsContentsOverview({ force: true })
                    await this.loadSharedObjectsForMe()
                }
            }
            this.isLoading = false
        },
        buildOverviewClassificationTreeItems() {
            const tree = Array.isArray(this.classificationTree) ? this.classificationTree : []

            return tree
                .map((subjectNode) => {
                    const subjectId = Number(subjectNode?.id)
                    const subjectName = this.normalizeFilterText(subjectNode?.name)
                    if (!subjectName) return null

                    const topics = (Array.isArray(subjectNode?.topics) ? subjectNode.topics : [])
                        .map((topicNode) => {
                            const topicId = Number(topicNode?.id)
                            const topicName = this.normalizeFilterText(topicNode?.name)
                            if (!topicName) return null
                            const topicLinkedPermission = this.normalizeLinkedPermission(topicNode?.linked_permission)
                            const topicIsLinked = topicNode?.is_linked === true || topicLinkedPermission !== ''

                            const units = (Array.isArray(topicNode?.units) ? topicNode.units : [])
                                .map((unitNode) => {
                                    const unitId = Number(unitNode?.id)
                                    const unitName = this.normalizeFilterText(unitNode?.name)
                                    if (!unitName) return null
                                    const unitLinkedPermission = this.normalizeLinkedPermission(unitNode?.linked_permission)
                                    const unitIsLinked = unitNode?.is_linked === true || unitLinkedPermission !== ''
                                    return {
                                        id: Number.isFinite(unitId) && unitId > 0 ? unitId : null,
                                        name: unitName,
                                        isLinked: unitIsLinked,
                                        linkedPermission: unitLinkedPermission,
                                        linkedPermissionLabel: String(unitNode?.linked_permission_label || '').trim(),
                                    }
                                })
                                .filter(Boolean)

                            return {
                                id: Number.isFinite(topicId) && topicId > 0 ? topicId : null,
                                name: topicName,
                                isLinked: topicIsLinked,
                                linkedPermission: topicLinkedPermission,
                                linkedPermissionLabel: String(topicNode?.linked_permission_label || '').trim(),
                                units,
                            }
                        })
                        .filter(Boolean)

                    return {
                        id: Number.isFinite(subjectId) && subjectId > 0 ? subjectId : null,
                        name: subjectName,
                        topics,
                    }
                })
                .filter(Boolean)
        },
        normalizeMaterialAttachmentCount(card) {
            const countFromField = Number(card?.attachments_count)
            if (Number.isFinite(countFromField) && countFromField > 0) {
                return Math.round(countFromField)
            }

            const attachments = Array.isArray(card?.attachments) ? card.attachments : []
            return attachments.length > 0 ? attachments.length : 0
        },
        normalizeMaterialTypeLabel(card) {
            const rawType = this.normalizeFilterText(card?.type)
            if (!rawType) return ''

            const option = this.typeOptions.find((entry) => this.normalizeFilterText(entry?.value).toLocaleLowerCase() === rawType.toLocaleLowerCase())
            const configuredLabel = this.normalizeFilterText(option?.label)
            return configuredLabel || rawType
        },
        buildSubjectsContentsOverviewItems(cards, filters = {}) {
            const cardList = Array.isArray(cards) ? cards : []
            const normalizedFilters = this.normalizeFilterSelection(filters)
            const hasActiveFilters = this.hasActiveFilterValues(filters)
            const subjects = this.buildOverviewClassificationTreeItems().map((subject) => ({
                ...subject,
                materials: [],
                _materialIds: new Set(),
                topics: (Array.isArray(subject.topics) ? subject.topics : []).map((topic) => ({
                    ...topic,
                    materials: [],
                    _materialIds: new Set(),
                    units: (Array.isArray(topic.units) ? topic.units : []).map((unit) => ({
                        ...unit,
                        materials: [],
                        _materialIds: new Set(),
                    })),
                })),
            }))

            const subjectMap = new Map(subjects.map((subject) => [this.normalizeFilterText(subject?.name).toLocaleLowerCase(), subject]))
            const ensureSubject = (name) => {
                const normalizedName = this.normalizeFilterText(name)
                if (!normalizedName) return null
                const key = normalizedName.toLocaleLowerCase()
                const existing = subjectMap.get(key)
                if (existing) return existing

                const created = {
                    id: null,
                    name: normalizedName,
                    materials: [],
                    _materialIds: new Set(),
                    topics: [],
                }
                subjects.push(created)
                subjectMap.set(key, created)
                return created
            }
            const ensureTopic = (subject, name) => {
                const normalizedName = this.normalizeFilterText(name)
                if (!subject || !normalizedName) return null
                const topics = Array.isArray(subject.topics) ? subject.topics : []
                const key = normalizedName.toLocaleLowerCase()
                const existing = topics.find((topic) => this.normalizeFilterText(topic?.name).toLocaleLowerCase() === key)
                if (existing) return existing

                const created = {
                    id: null,
                    name: normalizedName,
                    isLinked: false,
                    linkedPermission: '',
                    linkedPermissionLabel: '',
                    materials: [],
                    _materialIds: new Set(),
                    units: [],
                }
                topics.push(created)
                subject.topics = topics
                return created
            }
            const ensureUnit = (topic, name, unitId = null) => {
                const normalizedName = this.normalizeFilterText(name)
                if (!topic || !normalizedName) return null
                const units = Array.isArray(topic.units) ? topic.units : []
                const normalizedUnitId = Number(unitId)
                if (Number.isFinite(normalizedUnitId) && normalizedUnitId > 0) {
                    const existingById = units.find((unit) => Number(unit?.id || 0) === normalizedUnitId)
                    if (existingById) return existingById
                    const existingPlaceholder = units.find((unit) => {
                        const currentId = Number(unit?.id || 0)
                        if (Number.isFinite(currentId) && currentId > 0) return false
                        return this.normalizeFilterText(unit?.name).toLocaleLowerCase() === normalizedName.toLocaleLowerCase()
                    })
                    if (existingPlaceholder) {
                        existingPlaceholder.id = normalizedUnitId
                        return existingPlaceholder
                    }
                } else {
                    const key = normalizedName.toLocaleLowerCase()
                    const sameNameUnits = units.filter((unit) => this.normalizeFilterText(unit?.name).toLocaleLowerCase() === key)
                    if (sameNameUnits.length === 1) {
                        return sameNameUnits[0]
                    }
                    if (sameNameUnits.length > 1) {
                        const preferred = [...sameNameUnits].sort((left, right) => {
                            const leftCount = Array.isArray(left?.materials) ? left.materials.length : 0
                            const rightCount = Array.isArray(right?.materials) ? right.materials.length : 0
                            if (leftCount !== rightCount) return leftCount - rightCount
                            return Number(left?.id || 0) - Number(right?.id || 0)
                        })[0]
                        if (preferred) return preferred
                    }
                }

                const created = {
                    id: Number.isFinite(normalizedUnitId) && normalizedUnitId > 0 ? normalizedUnitId : null,
                    name: normalizedName,
                    materials: [],
                    _materialIds: new Set(),
                    isLinked: false,
                    linkedPermission: '',
                    linkedPermissionLabel: '',
                }
                units.push(created)
                topic.units = units
                return created
            }
            const pushMaterial = (node, material) => {
                if (!node || !material) return
                const id = Number(material?.id)
                if (!Number.isFinite(id) || id <= 0) return
                if (node._materialIds.has(id)) return
                node._materialIds.add(id)
                node.materials.push(material)
            }

            for (const card of cardList) {
                const cardId = Number(card?.id)
                if (!Number.isFinite(cardId) || cardId <= 0) continue
                if (hasActiveFilters && !this.cardMatchesFilterSet(card, normalizedFilters)) continue

                const materialBase = {
                    id: cardId,
                    title: this.materialSortTitle(card),
                    icon: this.sourceIcon(card),
                    isLinked: !!card?.is_linked,
                    linkedPermission: String(card?.linked_permission || '').trim(),
                    linkedPermissionLabel: String(card?.linked_permission_label || '').trim(),
                    typeLabel: this.normalizeMaterialTypeLabel(card),
                    typeColor: this.typeColor(card?.type),
                    status: String(card?.status || this.defaultStatusValue || '').trim(),
                    attachmentsCount: this.normalizeMaterialAttachmentCount(card),
                }

                const persistedRows = this.normalizeOverviewClassifications(card?.classifications)
                const rows = persistedRows.length > 0 ? persistedRows : this.fallbackClassificationsForOverviewCard(card)
                const classificationsCount = persistedRows.length
                const hasPersistedClassifications = classificationsCount > 0
                const linkedPermission = this.linkedPermissionForCard(card)
                const canUnlinkLinkedMaterial = materialBase.isLinked && (linkedPermission === 'read_write' || linkedPermission === 'full_access')
                for (const row of rows) {
                    const classificationId = Number(row?.id ?? row?.classificationId ?? row?.classification_id ?? 0)
                    const subjectId = Number(row?.subjectId ?? row?.subject_id ?? 0)
                    const topicId = Number(row?.topicId ?? row?.topic_id ?? 0)
                    const unitId = Number(row?.unitId ?? row?.unit_id ?? 0)
                    const subjectName = this.normalizeFilterText(row?.subject)
                    const topicName = this.normalizeFilterText(row?.topic)
                    const unitName = this.normalizeFilterText(row?.unit)
                    if (!subjectName) continue
                    const canRemoveClassification = hasPersistedClassifications && (classificationsCount >= 2 || canUnlinkLinkedMaterial)
                    const material = {
                        ...materialBase,
                        classificationRow: {
                            id: Number.isFinite(classificationId) && classificationId > 0 ? classificationId : null,
                            subjectId: Number.isFinite(subjectId) && subjectId > 0 ? subjectId : null,
                            topicId: Number.isFinite(topicId) && topicId > 0 ? topicId : null,
                            unitId: Number.isFinite(unitId) && unitId > 0 ? unitId : null,
                            subject: subjectName,
                            topic: topicName,
                            unit: unitName,
                        },
                        classificationsCount,
                        canRemoveClassification,
                        removeClassificationTitle: canRemoveClassification
                            ? 'Diese Zuordnung entfernen'
                            : canUnlinkLinkedMaterial
                              ? 'Verlinktes Material kann über die letzte Zuordnung entkoppelt werden.'
                            : hasPersistedClassifications
                              ? 'Nicht möglich: Material hat nur 1 Zuordnung.'
                              : 'Nicht möglich: Material hat keine gespeicherte Zuordnung.',
                    }

                    const subjectNode = ensureSubject(subjectName)
                    if (!topicName) {
                        pushMaterial(subjectNode, material)
                        continue
                    }

                    const topicNode = ensureTopic(subjectNode, topicName)
                    if (!unitName) {
                        pushMaterial(topicNode, material)
                        continue
                    }

                    const unitNode = ensureUnit(topicNode, unitName, unitId)
                    pushMaterial(unitNode, material)
                }
            }

            const byTitle = (a, b) => String(a?.title || '').localeCompare(String(b?.title || ''), 'de', { sensitivity: 'base' })
            for (const subject of subjects) {
                subject.materials.sort(byTitle)
                for (const topic of subject.topics) {
                    topic.materials.sort(byTitle)
                    for (const unit of topic.units) {
                        unit.materials.sort(byTitle)
                        delete unit._materialIds
                    }
                    delete topic._materialIds
                }
                delete subject._materialIds
            }

            if (hasActiveFilters) {
                const subjectFilter = this.normalizeFilterText(normalizedFilters?.subject).toLocaleLowerCase()
                const topicFilter = this.normalizeFilterText(normalizedFilters?.topic).toLocaleLowerCase()
                const unitFilter = this.normalizeFilterText(normalizedFilters?.unit).toLocaleLowerCase()

                return subjects
                    .map((subject) => {
                        const subjectName = this.normalizeFilterText(subject?.name).toLocaleLowerCase()
                        if (subjectFilter !== '' && subjectName !== subjectFilter) {
                            return null
                        }

                        const nextTopics = (Array.isArray(subject?.topics) ? subject.topics : [])
                            .map((topic) => {
                                const topicName = this.normalizeFilterText(topic?.name).toLocaleLowerCase()
                                if (topicFilter !== '' && topicName !== topicFilter) {
                                    return null
                                }

                                const nextUnits = (Array.isArray(topic?.units) ? topic.units : []).filter((unit) => {
                                    const unitName = this.normalizeFilterText(unit?.name).toLocaleLowerCase()
                                    if (unitFilter !== '' && unitName !== unitFilter) {
                                        return false
                                    }
                                    return Array.isArray(unit?.materials) && unit.materials.length > 0
                                })

                                const topicHasMaterials = Array.isArray(topic?.materials) && topic.materials.length > 0
                                if (!topicHasMaterials && nextUnits.length === 0) {
                                    return null
                                }

                                return {
                                    ...topic,
                                    units: nextUnits,
                                }
                            })
                            .filter(Boolean)

                        const subjectHasMaterials = Array.isArray(subject?.materials) && subject.materials.length > 0
                        if (!subjectHasMaterials && nextTopics.length === 0) {
                            return null
                        }

                        return {
                            ...subject,
                            topics: nextTopics,
                        }
                    })
                    .filter(Boolean)
            }

            return subjects
        },
        fallbackClassificationsForOverviewCard(card) {
            const subject = this.normalizeFilterText(card?.subject)
            const topic = this.normalizeFilterText(card?.area)
            let unit = this.normalizeFilterText(card?.unit)

            if (subject !== '') {
                if (topic === '') {
                    unit = ''
                }

                return [{ id: null, subjectId: null, topicId: null, unitId: null, subject, topic, unit }]
            }

            return [{ id: null, subjectId: null, topicId: null, unitId: null, subject: 'Nicht zugeordnet', topic: '', unit: '' }]
        },
        classificationRowKey(row) {
            const classificationId = Number(row?.id ?? row?.classificationId ?? row?.classification_id ?? 0)
            if (Number.isFinite(classificationId) && classificationId > 0) {
                return `classification:${classificationId}`
            }

            const subjectId = Number(row?.subjectId ?? row?.subject_id ?? 0)
            const topicId = Number(row?.topicId ?? row?.topic_id ?? 0)
            const unitId = Number(row?.unitId ?? row?.unit_id ?? 0)
            const subject = this.normalizeFilterText(row?.subject).toLocaleLowerCase()
            const topic = this.normalizeFilterText(row?.topic).toLocaleLowerCase()
            const unit = this.normalizeFilterText(row?.unit).toLocaleLowerCase()
            if (!subject) return ''

            if ((Number.isFinite(subjectId) && subjectId > 0) || (Number.isFinite(topicId) && topicId > 0) || (Number.isFinite(unitId) && unitId > 0)) {
                return `ids:${subjectId}|${topicId}|${unitId}|${subject}|${topic}|${unit}`
            }

            return `names:${subject}|${topic}|${unit}`
        },
        buildMaterialUpdatePayload(card, classifications) {
            return {
                title: String(card?.title || '').trim(),
                source_url: this.toNullable(card?.source_url),
                source_text: this.toNullable(card?.source_text),
                classifications: this.normalizeClassifications(classifications),
                area: this.toNullable(card?.area),
                unit: this.toNullable(card?.unit),
                type: this.toNullable(card?.type),
                status: this.toNullable(card?.status) || this.defaultStatusValue,
                notes: this.toNullable(card?.notes),
            }
        },
        notifyTreeClassificationRemoval(message, type = 'warning') {
            const notification = useNotificationStore()
            notification.notify({
                message: String(message || 'Zuordnung konnte nicht entfernt werden.'),
                type,
                timeout: 3000,
            })
        },
        async removeClassificationFromTree(material) {
            if (this.readOnlyMaterialActions) return
            if (this.isLoading || this.isSavingEdit || this.isSavingCreate || this.isDeletingId !== null || this.isRemovingTreeClassification) return

            const cardId = Number(material?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) return

            const targetRow = {
                id: Number(material?.classificationRow?.id || 0),
                subjectId: Number(material?.classificationRow?.subjectId || 0),
                topicId: Number(material?.classificationRow?.topicId || 0),
                unitId: Number(material?.classificationRow?.unitId || 0),
                subject: this.normalizeFilterText(material?.classificationRow?.subject),
                topic: this.normalizeFilterText(material?.classificationRow?.topic),
                unit: this.normalizeFilterText(material?.classificationRow?.unit),
            }
            if (!targetRow.subject) return

            const hintedCount = Number(material?.classificationsCount || 0)
            const allowLinkedUnlink = material?.isLinked === true && this.cardAllowsFieldEditing(material)
            if (Number.isFinite(hintedCount) && hintedCount < 2 && !allowLinkedUnlink) {
                this.notifyTreeClassificationRemoval('Zuordnung entfernen geht nur, wenn mindestens 2 Zuordnungen vorhanden sind.')
                return
            }

            this.isRemovingTreeClassification = true
            try {
                const loaded = await this.materialCardStore.show(cardId)
                if (!loaded) return

                const card = this.materialCardStore?.selected_card
                if (Number(card?.id) !== cardId) return

                const rows = this.normalizeClassifications(card?.classifications)
                const allowLoadedLinkedUnlink = card?.is_linked === true && this.cardAllowsFieldEditing(card)
                if (rows.length < 2 && !allowLoadedLinkedUnlink) {
                    this.notifyTreeClassificationRemoval('Zuordnung entfernen geht nur, wenn mindestens 2 Zuordnungen vorhanden sind.')
                    return
                }

                const targetKey = this.classificationRowKey(targetRow)
                const nextRows = rows.filter((row) => this.classificationRowKey(row) !== targetKey)

                if (nextRows.length === rows.length) {
                    this.notifyTreeClassificationRemoval('Die ausgewählte Zuordnung wurde nicht gefunden.', 'error')
                    return
                }
                if (nextRows.length < 1 && !allowLoadedLinkedUnlink) {
                    this.notifyTreeClassificationRemoval('Die letzte Zuordnung kann nicht entfernt werden.')
                    return
                }

                const updated = await this.materialCardStore.update(cardId, this.buildMaterialUpdatePayload(card, nextRows))
                if (!updated) return

                if (this.detailDialogOpen && Number(this.detailDialogCard?.id) === cardId) {
                    this.detailDialogCard = this.sanitizeDialogCard(updated)
                }
                this.mergeCardIntoOverview(updated)
                await this.loadCards(null, { forceFilterCountRefresh: true })
            } finally {
                this.isRemovingTreeClassification = false
            }
        },
        buildSubjectsContentsOverviewSnapshotKey() {
            const filters = this.buildSubjectsContentsOverviewFilters()
            return this.filterCountSnapshotKeyFor(filters)
        },
        buildSubjectsContentsOverviewFilters() {
            const sourceFilters = { ...(this.materialCardStore?.filters || {}) }
            const normalizedSelection = this.normalizeFilterSelection(sourceFilters)

            return {
                ...sourceFilters,
                subject: normalizedSelection.subject,
                topic: normalizedSelection.topic,
                unit: normalizedSelection.unit,
                type: normalizedSelection.type,
                status: normalizedSelection.status,
            }
        },
        hasActiveFilterValues(filters = {}) {
            for (const [key, value] of Object.entries(filters || {})) {
                const filterKey = String(key || '').trim()
                if (!filterKey || filterKey === 'page') continue
                if (this.normalizeFilterText(value) !== '') return true
            }
            return false
        },
        async loadSubjectsContentsOverview({ force = false } = {}) {
            if (this.isLoadingSubjectsContentsOverview) return

            const snapshotKey = this.buildSubjectsContentsOverviewSnapshotKey()
            if (!force && snapshotKey === this.subjectsContentsOverviewSnapshotKey && this.subjectsContentsOverviewItems.length > 0) {
                return
            }

            const requestId = this.subjectsContentsOverviewRequestId + 1
            this.subjectsContentsOverviewRequestId = requestId
            this.isLoadingSubjectsContentsOverview = true

            try {
                await this.materialCardStore.loadConfig()
                if (requestId !== this.subjectsContentsOverviewRequestId) return
                const filters = this.buildSubjectsContentsOverviewFilters()
                const cards = await this.materialCardStore.listAllCardsSnapshot(filters)
                if (requestId !== this.subjectsContentsOverviewRequestId) return
                if (!Array.isArray(cards)) return

                this.subjectsContentsOverviewItems = this.buildSubjectsContentsOverviewItems(cards, filters)
                this.subjectsContentsOverviewSnapshotKey = snapshotKey
                if (this.enableShareButtons) {
                    this.loadShareIndicators()
                }
            } finally {
                if (requestId === this.subjectsContentsOverviewRequestId) {
                    this.isLoadingSubjectsContentsOverview = false
                }
            }
        },
        calculateAttachmentBytesForCards(cards) {
            const list = Array.isArray(cards) ? cards : []
            return list.reduce((sum, card) => {
                const attachments = Array.isArray(card?.attachments) ? card.attachments : []
                const bytes = attachments.reduce((attachmentSum, attachment) => {
                    return attachmentSum + this.attachmentSizeBytes(attachment)
                }, 0)
                return sum + bytes
            }, 0)
        },
        async refreshAllListedAttachmentBytes({ force = false } = {}) {
            if (!force && this.allListedAttachmentBytesLoaded) {
                return
            }

            const requestId = this.allListedAttachmentBytesRequestId + 1
            this.allListedAttachmentBytesRequestId = requestId

            this.allListedAttachmentBytesLoading = true
            const snapshot = await this.materialCardStore.listAllCardsSnapshot({})
            if (requestId !== this.allListedAttachmentBytesRequestId) return

            if (Array.isArray(snapshot)) {
                this.allListedAttachmentBytes = this.calculateAttachmentBytesForCards(snapshot)
                this.allListedAttachmentBytesLoaded = true
            } else {
                this.allListedAttachmentBytes = null
                this.allListedAttachmentBytesLoaded = false
            }
            this.allListedAttachmentBytesLoading = false
        },
        async goToFirstPage() {
            if (!this.hasPreviousPage) return
            await this.loadCards(1)
        },
        async goToPreviousPage() {
            if (!this.hasPreviousPage) return
            await this.loadCards(this.currentMetaPage - 1)
        },
        async goToNextPage() {
            if (!this.hasNextPage) return
            await this.loadCards(this.currentMetaPage + 1)
        },
        async goToLastPage() {
            if (!this.hasNextPage) return
            await this.loadCards(this.lastMetaPage)
        },
        normalizeFilterText(value) {
            return String(value ?? '')
                .trim()
                .slice(0, 255)
        },
        stringHash(value) {
            const input = String(value ?? '')
            let hash = 0
            for (let index = 0; index < input.length; index += 1) {
                hash = (hash << 5) - hash + input.charCodeAt(index)
                hash |= 0
            }
            return Math.abs(hash)
        },
        subjectColorSeed(subject) {
            const subjectId = Number(subject?.id)
            const subjectName = this.normalizeFilterText(subject?.name).toLocaleLowerCase()
            return Number.isFinite(subjectId) && subjectId > 0 ? `subject-${subjectId}` : `subject-${subjectName}`
        },
        subjectColorTokens(subject) {
            const seed = this.subjectColorSeed(subject)
            const variant = this.stringHash(seed) % 2
            if (variant === 0) {
                return {
                    base: '#1f6f8b',
                    soft: 'rgba(31, 111, 139, 0.14)',
                }
            }
            return {
                base: '#296f5d',
                soft: 'rgba(41, 111, 93, 0.13)',
            }
        },
        subjectGroupStyle(subject) {
            const tokens = this.subjectColorTokens(subject)
            return {
                backgroundColor: tokens.soft,
                borderColor: tokens.base,
            }
        },
        topicGroupStyle(subject) {
            const tokens = this.subjectColorTokens(subject)
            return {
                '--overview-topic-accent-color': tokens.base,
            }
        },
        isSubjectFilterActive(value) {
            const selected = this.normalizeFilterText(this.subjectFilter).toLocaleLowerCase()
            const subject = this.normalizeFilterText(value).toLocaleLowerCase()
            return selected !== '' && selected === subject
        },
        async applySubjectFilter(value) {
            const subject = this.normalizeFilterText(value)
            this.subjectFilter = subject
            this.topicFilter = ''
            this.unitFilter = ''
            this.currentPage = 1
            this.materialCardStore.filters = {
                ...(this.materialCardStore.filters || {}),
                subject,
                topic: '',
                unit: '',
            }
            await this.loadCards(1)
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
        isTopicFilterActive(value) {
            const selected = this.normalizeFilterText(this.topicFilter).toLocaleLowerCase()
            const topic = this.normalizeFilterText(value).toLocaleLowerCase()
            return selected !== '' && selected === topic
        },
        async applyTopicFilter(value) {
            if (!this.hasActiveSubjectFilter) return

            const topic = this.normalizeFilterText(value)
            this.topicFilter = topic
            this.unitFilter = ''
            this.currentPage = 1
            this.materialCardStore.filters = {
                ...(this.materialCardStore.filters || {}),
                topic,
                unit: '',
            }
            await this.loadCards(1)
        },
        async toggleTopicFilter(value) {
            if (this.isTopicFilterActive(value)) {
                await this.clearTopicFilter()
                return
            }

            await this.applyTopicFilter(value)
        },
        async clearTopicFilter() {
            await this.applyTopicFilter('')
        },
        isUnitFilterActive(value) {
            const selected = this.normalizeFilterText(this.unitFilter).toLocaleLowerCase()
            const unit = this.normalizeFilterText(value).toLocaleLowerCase()
            return selected !== '' && selected === unit
        },
        async applyUnitFilter(value) {
            if (!this.hasActiveTopicFilter) return

            const unit = this.normalizeFilterText(value)
            this.unitFilter = unit
            this.currentPage = 1
            this.materialCardStore.filters = {
                ...(this.materialCardStore.filters || {}),
                unit,
            }
            await this.loadCards(1)
        },
        async toggleUnitFilter(value) {
            if (this.isUnitFilterActive(value)) {
                await this.clearUnitFilter()
                return
            }

            await this.applyUnitFilter(value)
        },
        async clearUnitFilter() {
            await this.applyUnitFilter('')
        },
        isTypeFilterActive(value) {
            const selected = this.normalizeFilterText(this.typeFilter).toLocaleLowerCase()
            const type = this.normalizeFilterText(value).toLocaleLowerCase()
            return selected !== '' && selected === type
        },
        async applyTypeFilter(value) {
            const type = this.normalizeFilterText(value)
            this.typeFilter = type
            this.currentPage = 1
            this.materialCardStore.filters = {
                ...(this.materialCardStore.filters || {}),
                type,
            }
            await this.loadCards(1)
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
            this.currentPage = 1
            this.materialCardStore.filters = {
                ...(this.materialCardStore.filters || {}),
                status,
            }
            await this.loadCards(1)
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
        badgeCountContent(value) {
            const count = Number(value)
            if (!Number.isFinite(count) || count < 0) return '0'
            return String(Math.round(count))
        },
        filterCountSnapshotKeyFor(filters = {}) {
            const entries = Object.entries(filters || {})
                .map(([key, value]) => [String(key), this.normalizeFilterText(value)])
                .filter(([key, value]) => key !== '' && value !== '')
                .sort((a, b) => a[0].localeCompare(b[0], undefined, { sensitivity: 'base' }))

            return entries.map(([key, value]) => `${key}:${value.toLocaleLowerCase()}`).join('|')
        },
        buildFilterCountSnapshotFilters() {
            const source = { ...(this.materialCardStore?.filters || {}) }
            const excluded = new Set(['subject', 'topic', 'unit', 'type', 'status', 'page'])
            const result = {}

            for (const [key, value] of Object.entries(source)) {
                const filterKey = String(key || '').trim()
                if (!filterKey || excluded.has(filterKey)) continue
                const normalizedValue = this.normalizeFilterText(value)
                if (!normalizedValue) continue
                result[filterKey] = normalizedValue
            }

            return result
        },
        async refreshFilterCountCards({ force = false } = {}) {
            const filters = this.buildFilterCountSnapshotFilters()
            const snapshotKey = this.filterCountSnapshotKeyFor(filters)

            if (!force && this.filterCountCardsLoaded && snapshotKey === this.filterCountSnapshotKey) {
                return
            }

            const requestId = this.filterCountCardsRequestId + 1
            this.filterCountCardsRequestId = requestId
            this.filterCountCardsLoading = true

            const snapshot = await this.materialCardStore.listAllCardsSnapshot(filters)
            if (requestId !== this.filterCountCardsRequestId) return

            if (Array.isArray(snapshot)) {
                this.filterCountCards = snapshot
                this.filterCountCardsLoaded = true
                this.filterCountSnapshotKey = snapshotKey
            }

            this.filterCountCardsLoading = false
        },
        normalizeFilterSelection(filters = {}) {
            const normalized = {
                subject: this.normalizeFilterText(filters?.subject),
                topic: this.normalizeFilterText(filters?.topic),
                unit: this.normalizeFilterText(filters?.unit),
                type: this.normalizeFilterText(filters?.type),
                status: this.normalizeFilterText(filters?.status),
            }

            if (normalized.subject === '') {
                normalized.topic = ''
                normalized.unit = ''
            } else if (normalized.topic === '') {
                normalized.unit = ''
            }

            return normalized
        },
        normalizedCardClassifications(card) {
            return this.normalizeClassifications(card?.classifications)
        },
        cardHasClassificationValue(card, field, value) {
            const normalizedValue = this.normalizeFilterText(value).toLocaleLowerCase()
            if (normalizedValue === '') return true

            const rows = this.normalizedCardClassifications(card)
            return rows.some((row) => this.normalizeFilterText(row?.[field]).toLocaleLowerCase() === normalizedValue)
        },
        cardMatchesTypeFilterValue(card, typeValue) {
            const normalizedType = this.normalizeFilterText(typeValue).toLocaleLowerCase()
            if (normalizedType === '') return true

            const cardType = this.normalizeFilterText(card?.type).toLocaleLowerCase()
            return cardType !== '' && cardType === normalizedType
        },
        cardMatchesStatusFilterValue(card, statusValue) {
            const normalizedStatus = this.normalizeFilterText(statusValue).toLocaleLowerCase()
            if (normalizedStatus === '') return true

            const cardStatus = this.normalizeFilterText(card?.status).toLocaleLowerCase()
            return cardStatus !== '' && cardStatus === normalizedStatus
        },
        cardMatchesFilterSet(card, normalizedFilters = null) {
            const filters = normalizedFilters || this.normalizeFilterSelection({})

            return (
                this.cardHasClassificationValue(card, 'subject', filters.subject) &&
                this.cardHasClassificationValue(card, 'topic', filters.topic) &&
                this.cardHasClassificationValue(card, 'unit', filters.unit) &&
                this.cardMatchesTypeFilterValue(card, filters.type) &&
                this.cardMatchesStatusFilterValue(card, filters.status)
            )
        },
        countCardsForFilterSet(filters = {}) {
            const normalized = this.normalizeFilterSelection(filters)
            const cards = Array.isArray(this.filterCountSourceCards) ? this.filterCountSourceCards : []
            if (!Array.isArray(cards) || cards.length === 0) return 0

            let count = 0
            for (const card of cards) {
                if (this.cardMatchesFilterSet(card, normalized)) {
                    count += 1
                }
            }

            return count
        },
        countFromMap(map, value) {
            const key = this.normalizeFilterText(value).toLocaleLowerCase()
            if (!key) return 0
            const count = Number(map?.[key] ?? 0)
            if (!Number.isFinite(count) || count < 0) return 0
            return Math.round(count)
        },
        subjectFilterCount(value) {
            return this.countFromMap(this.subjectFilterCountMap, value)
        },
        topicFilterCount(value) {
            return this.countFromMap(this.topicFilterCountMap, value)
        },
        unitFilterCount(value) {
            return this.countFromMap(this.unitFilterCountMap, value)
        },
        typeFilterCount(value) {
            return this.countFromMap(this.typeFilterCountMap, value)
        },
        statusFilterCount(value) {
            return this.countFromMap(this.statusFilterCountMap, value)
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
        normalizeUrl(value) {
            const raw = String(value ?? '').trim()
            if (!raw) return ''

            try {
                const parsed = new URL(raw)
                const protocol = String(parsed.protocol || '').toLowerCase()
                if (protocol !== 'http:' && protocol !== 'https:') {
                    return ''
                }

                return String(parsed.href || '')
                    .trim()
                    .slice(0, 2048)
            } catch {
                return ''
            }
        },
        defaultLinkTitle(url) {
            const normalizedUrl = this.normalizeUrl(url)
            if (!normalizedUrl) return 'Link'

            try {
                const parsed = new URL(normalizedUrl)
                const lastPath = decodeURIComponent(
                    parsed.pathname
                        .split('/')
                        .filter((segment) => segment !== '')
                        .pop() || ''
                )
                if (lastPath) {
                    return this.defaultAttachmentTitle(lastPath)
                }

                const host = String(parsed.hostname || '').replace(/^www\./i, '')
                return String(host || 'Link').slice(0, 255)
            } catch {
                return 'Link'
            }
        },
        extractUrlsFromText(text) {
            const raw = String(text || '').trim()
            if (!raw) return []

            const matches = raw.match(/https?:\/\/[^\s<>"'`]+/gi)
            return Array.isArray(matches) ? matches : []
        },
        extractUrlsFromHtml(html) {
            const raw = String(html || '').trim()
            if (!raw) return []

            try {
                const parser = new DOMParser()
                const doc = parser.parseFromString(raw, 'text/html')
                const urls = []

                doc.querySelectorAll('a[href], img[src]').forEach((node) => {
                    if (node instanceof HTMLAnchorElement) {
                        urls.push(node.getAttribute('href') || '')
                        return
                    }
                    if (node instanceof HTMLImageElement) {
                        urls.push(node.getAttribute('src') || '')
                    }
                })

                return urls.map((entry) => String(entry || '').trim()).filter((entry) => entry !== '')
            } catch {
                return []
            }
        },
        extractDropUrls(dataTransfer) {
            const dt = dataTransfer
            if (!dt) return []

            const candidates = []
            const uriList = String(dt.getData?.('text/uri-list') || '').trim()
            if (uriList) {
                uriList
                    .split('\n')
                    .map((line) => line.trim())
                    .filter((line) => line !== '' && !line.startsWith('#'))
                    .forEach((line) => candidates.push(line))
            }

            const plain = String(dt.getData?.('text/plain') || '').trim()
            this.extractUrlsFromText(plain).forEach((url) => candidates.push(url))

            const html = String(dt.getData?.('text/html') || '').trim()
            this.extractUrlsFromHtml(html).forEach((url) => candidates.push(url))

            const result = []
            const seen = new Set()

            for (const candidate of candidates) {
                const normalized = this.normalizeUrl(candidate)
                if (!normalized) continue
                const key = normalized.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                result.push(normalized)
            }

            const imageUrls = result.filter((url) => this.isLikelyImageUrl(url))
            if (imageUrls.length > 0) {
                return imageUrls
            }

            return result
        },
        isLikelyImageUrl(url) {
            const normalized = this.normalizeUrl(url)
            if (!normalized) return false

            try {
                const parsed = new URL(normalized)
                const path = String(parsed.pathname || '').toLocaleLowerCase()
                return /\.(png|jpe?g|gif|webp|svg|bmp|tiff?|avif|heic)$/i.test(path)
            } catch {
                return false
            }
        },
        onAttachmentDragOver(event) {
            if (!this.attachmentDialogCanAppendContent) return
            const dt = event?.dataTransfer
            if (!dt) return

            const hasFiles = (dt.files && dt.files.length > 0) || Array.from(dt.items || []).some((item) => item?.kind === 'file')
            if (hasFiles) return

            const urls = this.extractDropUrls(dt)
            if (urls.length > 0 && typeof event.preventDefault === 'function') {
                event.preventDefault()
            }
        },
        async onAttachmentDrop(event) {
            if (!this.attachmentDialogCanAppendContent) return
            const dt = event?.dataTransfer
            if (!dt) return

            const hasFiles = (dt.files && dt.files.length > 0) || Array.from(dt.items || []).some((item) => item?.kind === 'file')
            if (hasFiles) return

            if (typeof event.preventDefault === 'function') {
                event.preventDefault()
            }
            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation()
            }

            if (this.attachmentDialogBusy) return

            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(cardId) || cardId <= 0) return
            const sharedContext = this.sharedInboxContextForCard(this.attachmentDialogCardContext || { id: cardId })

            const urls = this.extractDropUrls(dt)
            if (!urls.length) return

            const knownLinks = new Set(
                this.attachmentRows
                    .filter(
                        (row) =>
                            String(row?.attachment_type || '')
                                .trim()
                                .toLocaleLowerCase() === 'link'
                    )
                    .map((row) => this.normalizeUrl(row?.url).toLocaleLowerCase())
                    .filter((url) => url !== '')
            )

            let changed = false
            this.isUploadingAttachment = true

            try {
                for (const url of urls) {
                    const normalizedUrl = this.normalizeUrl(url)
                    if (!normalizedUrl) continue

                    const key = normalizedUrl.toLocaleLowerCase()
                    if (knownLinks.has(key)) continue
                    knownLinks.add(key)

                    const attachmentTitle = this.defaultLinkTitle(normalizedUrl)
                    const linkAdded = sharedContext
                        ? await this.addSharedLinkAttachment(sharedContext, {
                            url: normalizedUrl,
                            name: attachmentTitle,
                        })
                        : await this.materialCardStore.addLinkAttachment(cardId, {
                            url: normalizedUrl,
                            name: attachmentTitle,
                        })

                    if (!linkAdded) continue
                    changed = true

                    if (this.isLikelyImageUrl(normalizedUrl)) {
                        const imageStored = sharedContext
                            ? await this.addSharedImageUrlAttachment(sharedContext, normalizedUrl, attachmentTitle)
                            : await this.materialCardStore.addImageUrlAttachment(cardId, normalizedUrl, attachmentTitle)
                        if (imageStored) {
                            changed = true
                        }
                    }
                }

                if (changed) {
                    await this.refreshAttachmentDialogCard(cardId)
                    this.refreshAllListedAttachmentBytes()
                }
            } finally {
                this.isUploadingAttachment = false
                this.clearAttachmentPondFiles()
            }
        },
        toPendingAttachments(value) {
            const input = Array.isArray(value) ? value : []
            const result = []

            for (let index = 0; index < input.length; index += 1) {
                const item = input[index]
                const attachmentType = String(item?.attachmentType || '')
                    .trim()
                    .toLocaleLowerCase()
                const linkUrl = this.normalizeUrl(item?.url)
                if ((attachmentType === 'link' || linkUrl) && linkUrl) {
                    const rawTitle = String(item?.title || '').trim()
                    const source = String(item?.source || '').trim()
                    const key = String(item?.key || '') || `link|${linkUrl}|${index}`

                    result.push({
                        attachmentType: 'link',
                        tempUpload: '',
                        file: null,
                        fileName: '',
                        title: rawTitle || this.defaultLinkTitle(linkUrl),
                        url: linkUrl,
                        storeImageFile: item?.storeImageFile === true,
                        source: source || 'link',
                        key,
                    })
                    continue
                }

                const tempUpload = String(item?.tempUpload || '').trim()
                if (tempUpload) {
                    const fileName = String(item?.fileName || '').trim() || `Datei ${index + 1}`
                    const rawTitle = String(item?.title || '').trim()
                    const source = String(item?.source || '').trim()
                    const key = String(item?.key || '') || `temp|${tempUpload}|${index}`

                    result.push({
                        attachmentType: 'file',
                        tempUpload,
                        file: null,
                        fileName,
                        title: rawTitle || this.defaultAttachmentTitle(fileName),
                        url: '',
                        storeImageFile: false,
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
                    attachmentType: 'file',
                    tempUpload: '',
                    file,
                    fileName,
                    title: rawTitle || this.defaultAttachmentTitle(fileName),
                    url: '',
                    storeImageFile: false,
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
            if (!this.canEditLinkedAppendContent) {
                this.notifyLinkedPermissionRestriction('Bei NUR LESEN können keine neuen Inhalte hinzugefügt werden.')
                return
            }
            const incoming = Array.isArray(files) ? files : []
            if (!incoming.length) return

            this.editForm.pendingAttachments = this.mergeUniquePendingAttachments(this.editForm.pendingAttachments, incoming, 'picker')
        },
        addCreatePendingAttachments(files) {
            const incoming = Array.isArray(files) ? files : []
            if (!incoming.length) return

            this.createForm.pendingAttachments = this.mergeUniquePendingAttachments(this.createForm.pendingAttachments, incoming, 'picker')
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
        async removeCreateTempUpload(uploadId) {
            const value = String(uploadId || '').trim()
            if (!value) return
            await this.materialCardStore.deleteTempUpload(value, false)
        },
        async cleanupPendingTempUploads(rows = null) {
            const list = this.toPendingAttachments(rows ?? this.editForm.pendingAttachments)
            const uploads = list.map((item) => String(item?.tempUpload || '').trim()).filter((value) => value !== '')

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
                          id: Number(row?.id || 0) > 0 ? Number(row.id) : null,
                          subject_id: Number(row?.subject_id || row?.subjectId || 0) > 0 ? Number(row?.subject_id || row?.subjectId) : null,
                          topic_id: Number(row?.topic_id || row?.topicId || 0) > 0 ? Number(row?.topic_id || row?.topicId) : null,
                          unit_id: Number(row?.unit_id || row?.unitId || 0) > 0 ? Number(row?.unit_id || row?.unitId) : null,
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

            this.detailDialogReadOnlyMode = false
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
            this.detailDialogReadOnlyMode = false
            this.detailDeleteStep = 0
            this.returnToDetailOnEditCancel = false
            this.detailCardForEditReturn = null
        },
        detailAttachments(card) {
            return Array.isArray(card?.attachments) ? card.attachments : []
        },
        openEditFromDetail() {
            if (!this.detailDialogCard || this.detailDialogLoading || this.isDeletingDetail) return
            if (this.detailDialogReadOnlyActions) return

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
                await this.loadCards(null, { forceFilterCountRefresh: true })
            }
        },
        async unlinkLinkedCard(card) {
            if (this.readOnlyMaterialActions) return
            if (
                this.isLoading ||
                this.isSavingCreate ||
                this.isSavingEdit ||
                this.isDeletingId !== null ||
                this.isRemovingTreeClassification ||
                this.isUnlinkingId !== null ||
                this.isUnlinkingUnitId !== null ||
                this.isUnlinkingTopicId !== null
            ) return

            const cardId = Number(card?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) return

            const linkedPermission = this.normalizeLinkedPermission(card?.linked_permission ?? card?.linkedPermission)
            const isLinked = card?.is_linked === true || card?.isLinked === true || linkedPermission !== ''
            if (!isLinked) return

            this.isUnlinkingId = cardId
            try {
                const unlinkedCard = await this.materialCardStore.unlink(cardId)
                if (!unlinkedCard) return

                if (this.detailDialogOpen && Number(this.detailDialogCard?.id || 0) === cardId) {
                    this.closeDetailDialog()
                }
                if (this.editDialogOpen && Number(this.editForm?.id || 0) === cardId) {
                    await this.closeEditDialog()
                }
                if (this.isSubjectsContentsOverview) {
                    this.subjectsContentsOverviewItems = this.buildSubjectsContentsOverviewItems(this.cards, this.buildSubjectsContentsOverviewFilters())
                }
            } finally {
                this.isUnlinkingId = null
            }
        },
        async unlinkLinkedUnit(unit) {
            if (this.readOnlyMaterialActions) return
            if (
                this.isLoading ||
                this.isSavingCreate ||
                this.isSavingEdit ||
                this.isDeletingId !== null ||
                this.isRemovingTreeClassification ||
                this.isUnlinkingId !== null ||
                this.isUnlinkingUnitId !== null ||
                this.isUnlinkingTopicId !== null
            ) return

            const unitId = Number(unit?.id)
            if (!Number.isFinite(unitId) || unitId <= 0) return

            const linkedPermission = this.normalizeLinkedPermission(unit?.linkedPermission ?? unit?.linked_permission)
            const isLinked = unit?.isLinked === true || unit?.is_linked === true || linkedPermission !== ''
            if (!isLinked) return

            this.isUnlinkingUnitId = unitId
            try {
                const result = await this.materialCardStore.unlinkUnit(unitId)
                if (!result) return

                const removedCardIds = Array.isArray(result.removedCardIds)
                    ? result.removedCardIds.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
                    : []

                if (removedCardIds.length > 0) {
                    if (this.detailDialogOpen && removedCardIds.includes(Number(this.detailDialogCard?.id || 0))) {
                        this.closeDetailDialog()
                    }
                    if (this.editDialogOpen && removedCardIds.includes(Number(this.editForm?.id || 0))) {
                        await this.closeEditDialog()
                    }
                }

                await this.loadCards(null, { forceFilterCountRefresh: true })
            } finally {
                this.isUnlinkingUnitId = null
            }
        },
        async unlinkLinkedTopic(topic) {
            if (this.readOnlyMaterialActions) return
            if (
                this.isLoading ||
                this.isSavingCreate ||
                this.isSavingEdit ||
                this.isDeletingId !== null ||
                this.isRemovingTreeClassification ||
                this.isUnlinkingId !== null ||
                this.isUnlinkingUnitId !== null ||
                this.isUnlinkingTopicId !== null
            ) return

            const topicId = Number(topic?.id)
            if (!Number.isFinite(topicId) || topicId <= 0) return

            const linkedPermission = this.normalizeLinkedPermission(topic?.linkedPermission ?? topic?.linked_permission)
            const isLinked = topic?.isLinked === true || topic?.is_linked === true || linkedPermission !== ''
            if (!isLinked) return

            this.isUnlinkingTopicId = topicId
            try {
                const result = await this.materialCardStore.unlinkTopic(topicId)
                if (!result) return

                const removedCardIds = Array.isArray(result.removedCardIds)
                    ? result.removedCardIds.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
                    : []

                if (removedCardIds.length > 0) {
                    if (this.detailDialogOpen && removedCardIds.includes(Number(this.detailDialogCard?.id || 0))) {
                        this.closeDetailDialog()
                    }
                    if (this.editDialogOpen && removedCardIds.includes(Number(this.editForm?.id || 0))) {
                        await this.closeEditDialog()
                    }
                }

                await this.loadCards(null, { forceFilterCountRefresh: true })
            } finally {
                this.isUnlinkingTopicId = null
            }
        },
        openEditDialog(card) {
            this.editForm = {
                id: card?.id ?? null,
                title: card?.title || '',
                description: card?.source_text || card?.notes || '',
                classifications:
                    Array.isArray(card?.classifications) && card.classifications.length > 0
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
                is_linked: card?.is_linked === true,
                linked_permission: this.normalizeLinkedPermission(card?.linked_permission),
                linked_permission_label: String(card?.linked_permission_label || '').trim(),
                shared_rule_id: Number(card?.shared_rule_id || 0) || null,
                shared_material_id: Number(card?.shared_material_id || card?.id || 0) || null,
            }
            this.attachmentDialogCardId = Number(card?.id) || null
            this.attachmentRows = this.toAttachmentRows(card?.attachments)
            this.attachmentDeleteArmedIds = []
            this.attachmentNameEditingIds = []
            this.editDeleteStep = 0
            this.editDeleteConfirmDialogOpen = false
            this.editDeleteConfirmDialogLoading = false
            this.editDeleteConfirmMaterialTitle = ''
            this.editDeleteConfirmAttachmentRows = []
            this.editClassificationEditorVisible = false
            this.editDialogOpen = true
        },
        async closeCreateDialog() {
            if (this.isSavingCreate) return

            await this.cleanupPendingTempUploads(this.createForm.pendingAttachments)
            this.createDialogOpen = false
            this.createClassificationEditorVisible = false
            this.createForm = createDefaultEditForm()
            this.createForm.status = this.defaultStatusValue
        },
        async startEditDeleteFlow() {
            const cardId = Number(this.editForm?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) return
            if (this.isSavingEdit || this.isDeletingEditedMaterial) return
            if (!this.canEditLinkedDeleteMaterial) {
                this.notifyLinkedPermissionRestriction('Verlinkte Materialien können nicht gelöscht werden.')
                return
            }

            this.editDeleteStep = 1
            this.editDeleteConfirmDialogOpen = true
            this.editDeleteConfirmDialogLoading = true
            this.editDeleteConfirmMaterialTitle = String(this.editForm?.title || '').trim()
            this.editDeleteConfirmAttachmentRows = this.toAttachmentRows(this.attachmentRows)

            try {
                const loaded = await this.materialCardStore.show(cardId)
                if (!loaded) return
                if (!this.editDeleteConfirmDialogOpen) return
                if (Number(this.editForm?.id) !== cardId) return

                const selectedCard = this.materialCardStore?.selected_card
                if (Number(selectedCard?.id) !== cardId) return

                this.mergeCardIntoOverview(selectedCard)
                this.attachmentRows = this.toAttachmentRows(selectedCard?.attachments)
                this.editDeleteConfirmMaterialTitle = String(selectedCard?.title || '').trim()
                this.editDeleteConfirmAttachmentRows = this.toAttachmentRows(selectedCard?.attachments)
            } finally {
                this.editDeleteConfirmDialogLoading = false
            }
        },
        resetEditDeleteFlow() {
            if (this.isDeletingEditedMaterial) return
            this.editDeleteStep = 0
            this.editDeleteConfirmDialogOpen = false
            this.editDeleteConfirmDialogLoading = false
        },
        async refreshLastDeletedMaterialRestoreInfo() {
            if (!this.materialCardStore || typeof this.materialCardStore.getDeletedRestoreList !== 'function') return

            const result = await this.materialCardStore.getDeletedRestoreList()
            const items = Array.isArray(result?.items) ? result.items : []
            this.deletedMaterialRestoreLimit = Math.max(1, Number(result?.limit || this.deletedMaterialRestoreLimit || 5) || 5)

            if (items.length === 0) {
                this.deletedMaterialRestoreItems = []
                this.deletedMaterialRestoreHidden = true
                return
            }

            this.deletedMaterialRestoreItems = items.map((item) => ({
                id: Number(item?.id || 0) || null,
                title: String(item?.title || '').trim(),
                attachmentsCount: Math.max(0, Number(item?.attachments_count ?? item?.attachmentsCount ?? 0) || 0),
                deletedAt: String(item?.deleted_at ?? item?.deletedAt ?? '').trim(),
            })).filter((item) => Number.isFinite(Number(item.id)) && Number(item.id) > 0)
        },
        hideDeletedMaterialRestoreList() {
            if (this.isRestoringLastDeletedMaterial || this.isPurgingDeletedMaterial) return
            if (!Array.isArray(this.deletedMaterialRestoreItems) || this.deletedMaterialRestoreItems.length === 0) return
            this.deletedMaterialRestoreHidden = true
        },
        showDeletedMaterialRestoreList() {
            if (this.isRestoringLastDeletedMaterial || this.isPurgingDeletedMaterial) return
            if (!Array.isArray(this.deletedMaterialRestoreItems) || this.deletedMaterialRestoreItems.length === 0) return
            this.deletedMaterialRestoreHidden = false
        },
        async restoreDeletedMaterial(item = null) {
            if (this.isRestoringLastDeletedMaterial || this.isPurgingDeletedMaterial) return
            if (!Array.isArray(this.deletedMaterialRestoreItems) || this.deletedMaterialRestoreItems.length === 0) return
            if (this.isLoading || this.isSavingEdit || this.isDeletingId !== null) return

            const targetId = Number(item?.id || this.deletedMaterialRestoreItems[0]?.id || 0)
            if (!Number.isFinite(targetId) || targetId <= 0) return

            this.isRestoringLastDeletedMaterial = true
            this.restoringDeletedMaterialId = targetId
            try {
                const restored = await this.materialCardStore.restoreDeletedById(targetId)
                if (!restored) {
                    await this.refreshLastDeletedMaterialRestoreInfo()
                    return
                }

                this.mergeCardIntoOverview(restored)
                await this.loadCards(null, { forceFilterCountRefresh: true })
                await this.refreshLastDeletedMaterialRestoreInfo()
            } finally {
                this.isRestoringLastDeletedMaterial = false
                this.restoringDeletedMaterialId = null
            }
        },
        async purgeDeletedMaterial(item = null) {
            if (this.isRestoringLastDeletedMaterial || this.isPurgingDeletedMaterial) return
            if (!Array.isArray(this.deletedMaterialRestoreItems) || this.deletedMaterialRestoreItems.length === 0) return
            if (this.isLoading || this.isSavingEdit || this.isDeletingId !== null) return

            const targetId = Number(item?.id || this.deletedMaterialRestoreItems[0]?.id || 0)
            if (!Number.isFinite(targetId) || targetId <= 0) return

            this.isPurgingDeletedMaterial = true
            this.purgingDeletedMaterialId = targetId
            try {
                const purged = await this.materialCardStore.purgeDeletedById(targetId)
                if (!purged) {
                    await this.refreshLastDeletedMaterialRestoreInfo()
                    return
                }

                await this.refreshLastDeletedMaterialRestoreInfo()
            } finally {
                this.isPurgingDeletedMaterial = false
                this.purgingDeletedMaterialId = null
            }
        },
        async confirmDeleteFromEdit() {
            const cardId = Number(this.editForm?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) return
            if (this.isSavingEdit || this.isDeletingEditedMaterial || !this.editDeleteConfirmDialogOpen) return
            if (!this.canEditLinkedDeleteMaterial) return

            this.isDeletingId = cardId
            const deleted = await this.materialCardStore.destroy(cardId)
            this.isDeletingId = null
            this.editDeleteStep = 0
            this.editDeleteConfirmDialogOpen = false
            this.editDeleteConfirmDialogLoading = false

            if (deleted) {
                await this.closeEditDialog(false)
                await this.loadCards(null, { forceFilterCountRefresh: true })
                await this.refreshLastDeletedMaterialRestoreInfo()
            }
        },
        async closeEditDialog(restoreDetail = true) {
            if (this.isSavingEdit || this.isDeletingEditedMaterial) return

            await this.cleanupPendingTempUploads(this.editForm.pendingAttachments)
            this.closeTextAttachmentEditor()

            const shouldRestoreDetail = restoreDetail && this.returnToDetailOnEditCancel && this.detailCardForEditReturn
            const restoreCard = shouldRestoreDetail ? this.sanitizeDialogCard(this.detailCardForEditReturn) : null
            const restoreSharedContext = restoreCard ? this.sharedInboxContextForCard(restoreCard) : null

            this.editDialogOpen = false
            this.editClassificationEditorVisible = false
            this.editForm = createDefaultEditForm()
            this.attachmentDialogCardId = null
            this.attachmentRows = []
            this.attachmentDeleteArmedIds = []
            this.attachmentNameEditingIds = []
            this.editDeleteStep = 0
            this.editDeleteConfirmDialogOpen = false
            this.editDeleteConfirmDialogLoading = false
            this.editDeleteConfirmMaterialTitle = ''
            this.editDeleteConfirmAttachmentRows = []
            this.returnToDetailOnEditCancel = false
            this.detailCardForEditReturn = null

            if (restoreCard) {
                if (restoreSharedContext) {
                    await this.openSharedMaterialDetail(restoreSharedContext.ruleId, restoreCard)
                } else {
                    await this.openDetailDialog(restoreCard)
                }
            }
        },
        openTypeManager() {
            if (!this.canManageTypeValues) return
            this.typeManagerDialogOpen = true
        },
        async handleEditSave() {
            if (this.isEditLinkedReadOnly) {
                await this.closeEditDialog(true)
                return
            }

            await this.saveEdit()
        },
        async saveEdit() {
            if (!this.canSaveEdit || this.isSavingEdit || this.isDeletingEditedMaterial || !this.editForm.id) return
            if (this.isEditLinkedReadOnly) {
                await this.closeEditDialog(true)
                return
            }

            this.isSavingEdit = true

            try {
                const sharedContext = this.sharedInboxContextForCard(this.editForm)
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

                const updated = sharedContext
                    ? await this.updateSharedMaterial(sharedContext, payload)
                    : await this.materialCardStore.update(this.editForm.id, payload)

                if (!updated) {
                    return
                }

                const pendingAttachments = this.toPendingAttachments(this.editForm.pendingAttachments)
                for (const attachment of pendingAttachments) {
                    if (attachment.attachmentType === 'link' && this.normalizeUrl(attachment.url)) {
                        const normalizedUrl = this.normalizeUrl(attachment.url)
                        const normalizedName = this.toNullable(attachment.title) || this.defaultLinkTitle(attachment.url)

                        if (sharedContext) {
                            await this.addSharedLinkAttachment(sharedContext, {
                                url: normalizedUrl,
                                name: normalizedName,
                            })

                            if (attachment.storeImageFile === true) {
                                await this.addSharedImageUrlAttachment(sharedContext, normalizedUrl, normalizedName)
                            }
                        } else {
                            await this.materialCardStore.addLinkAttachment(this.editForm.id, {
                                url: normalizedUrl,
                                name: normalizedName,
                            })

                            if (attachment.storeImageFile === true) {
                                await this.materialCardStore.addImageUrlAttachment(this.editForm.id, normalizedUrl, normalizedName)
                            }
                        }

                        continue
                    }

                    if (attachment.tempUpload) {
                        if (sharedContext) {
                            await this.addSharedTempFileAttachment(
                                sharedContext,
                                attachment.tempUpload,
                                this.toNullable(attachment.title) || attachment.fileName || ''
                            )
                        } else {
                            await this.materialCardStore.addTempFileAttachment(
                                this.editForm.id,
                                attachment.tempUpload,
                                this.toNullable(attachment.title) || attachment.fileName || ''
                            )
                        }

                        continue
                    }

                    if (attachment.file instanceof File) {
                        if (sharedContext) {
                            await this.addSharedFileAttachment(
                                sharedContext,
                                attachment.file,
                                this.toNullable(attachment.title) || attachment.file.name || ''
                            )
                        } else {
                            await this.materialCardStore.addFileAttachment(
                                this.editForm.id,
                                attachment.file,
                                this.toNullable(attachment.title) || attachment.file.name || ''
                            )
                        }
                    }
                }

                if (sharedContext) {
                    await this.loadSharedObjectsForMe()
                }

                this.isSavingEdit = false
                await this.closeEditDialog(true)
                await this.loadCards(null, { forceFilterCountRefresh: true })
            } finally {
                this.isSavingEdit = false
            }
        },
        async saveCreate() {
            if (!this.canSaveCreate || this.isSavingCreate || this.isSavingEdit || this.isDeletingId !== null) return

            this.isSavingCreate = true
            const title = String(this.createForm.title || '').trim()
            const classifications = this.normalizeClassifications(this.createForm.classifications)

            const saved = await this.materialCardStore.quickStore({
                title,
                source_text: this.toNullable(this.createForm.description),
                type: this.toNullable(this.createForm.type),
                status: this.toNullable(this.createForm.status) || this.defaultStatusValue,
                classifications,
            })

            if (saved?.id) {
                const pendingAttachments = this.toPendingAttachments(this.createForm.pendingAttachments)
                for (const attachment of pendingAttachments) {
                    if (attachment.attachmentType === 'link' && this.normalizeUrl(attachment.url)) {
                        const normalizedUrl = this.normalizeUrl(attachment.url)
                        const normalizedName = this.toNullable(attachment.title) || this.defaultLinkTitle(attachment.url)
                        await this.materialCardStore.addLinkAttachment(saved.id, {
                            url: normalizedUrl,
                            name: normalizedName,
                        })

                        if (attachment.storeImageFile === true) {
                            await this.materialCardStore.addImageUrlAttachment(saved.id, normalizedUrl, normalizedName)
                        }
                        continue
                    }

                    if (attachment.tempUpload) {
                        await this.materialCardStore.addTempFileAttachment(saved.id, attachment.tempUpload, this.toNullable(attachment.title) || attachment.fileName || '')
                        continue
                    }

                    if (attachment.file instanceof File) {
                        await this.materialCardStore.addFileAttachment(saved.id, attachment.file, this.toNullable(attachment.title) || attachment.file.name || '')
                    }
                }
            }

            this.isSavingCreate = false

            if (saved) {
                await this.closeCreateDialog()
                await this.loadCards(null, { forceFilterCountRefresh: true })
            }
        },
        normalizeClassifications(value) {
            const input = Array.isArray(value) ? value : []
            const result = []
            const seen = new Set()

            for (const row of input) {
                if (!row || typeof row !== 'object') continue
                const subject = String(row.subject ?? '')
                    .trim()
                    .slice(0, 255)
                const topic = String(row.topic ?? '')
                    .trim()
                    .slice(0, 255)
                let unit = String(row.unit ?? '')
                    .trim()
                    .slice(0, 255)
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
        normalizeOverviewClassifications(value) {
            const input = Array.isArray(value) ? value : []
            const result = []
            const seen = new Set()

            for (const row of input) {
                if (!row || typeof row !== 'object') continue

                const classificationId = Number(row?.id ?? row?.classificationId ?? row?.classification_id ?? 0)
                const subjectId = Number(row?.subjectId ?? row?.subject_id ?? 0)
                let topicId = Number(row?.topicId ?? row?.topic_id ?? 0)
                let unitId = Number(row?.unitId ?? row?.unit_id ?? 0)

                const subject = String(row?.subject ?? '')
                    .trim()
                    .slice(0, 255)
                const topic = String(row?.topic ?? '')
                    .trim()
                    .slice(0, 255)
                let unit = String(row?.unit ?? '')
                    .trim()
                    .slice(0, 255)
                if (!subject) continue
                if (!topic) {
                    topicId = 0
                    unitId = 0
                    unit = ''
                } else if (!unit) {
                    unitId = 0
                }

                const normalizedSubjectId = Number.isFinite(subjectId) && subjectId > 0 ? Math.round(subjectId) : null
                const normalizedTopicId = Number.isFinite(topicId) && topicId > 0 ? Math.round(topicId) : null
                const normalizedUnitId = Number.isFinite(unitId) && unitId > 0 ? Math.round(unitId) : null
                const normalizedClassificationId = Number.isFinite(classificationId) && classificationId > 0 ? Math.round(classificationId) : null

                const key = normalizedClassificationId !== null
                    ? `classification:${normalizedClassificationId}`
                    : normalizedSubjectId !== null || normalizedTopicId !== null || normalizedUnitId !== null
                      ? `ids:${normalizedSubjectId || 0}|${normalizedTopicId || 0}|${normalizedUnitId || 0}`
                      : `names:${subject.toLocaleLowerCase()}|${topic.toLocaleLowerCase()}|${unit.toLocaleLowerCase()}`
                if (seen.has(key)) continue
                seen.add(key)

                result.push({
                    id: normalizedClassificationId,
                    subject_id: normalizedSubjectId,
                    topic_id: normalizedTopicId,
                    unit_id: normalizedUnitId,
                    subject,
                    topic,
                    unit,
                })
            }

            return result
        },
        sourceIcon(card) {
            if (card?.is_linked) {
                return 'mdi-link-variant'
            }

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
        normalizeTypeColor(value) {
            const text = String(value ?? '').trim()
            if (!/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(text)) return ''
            if (text.length === 4) {
                return `#${text[1]}${text[1]}${text[2]}${text[2]}${text[3]}${text[3]}`.toLowerCase()
            }
            return text.toLowerCase()
        },
        normalizeStatusColor(value) {
            return this.normalizeTypeColor(value)
        },
        typeColor(typeValue) {
            const value = String(typeValue || '')
                .trim()
                .toLocaleLowerCase()
            if (!value) return ''

            const option = this.typeOptions.find((entry) => {
                const entryValue = String(entry?.value || '')
                    .trim()
                    .toLocaleLowerCase()
                return entryValue !== '' && entryValue === value
            })

            return this.normalizeTypeColor(option?.color)
        },
        hexToRgba(hexColor, alpha = 1) {
            const color = this.normalizeTypeColor(hexColor)
            if (!color) return ''

            const r = parseInt(color.slice(1, 3), 16)
            const g = parseInt(color.slice(3, 5), 16)
            const b = parseInt(color.slice(5, 7), 16)
            if ([r, g, b].some((value) => Number.isNaN(value))) return ''

            const normalizedAlpha = Number.isFinite(alpha) ? Math.min(1, Math.max(0, alpha)) : 1
            return `rgba(${r}, ${g}, ${b}, ${normalizedAlpha})`
        },
        cardBackgroundStyle(card) {
            const color = this.typeColor(card?.type)
            if (!color) return null

            const background = this.hexToRgba(color, 0.16)
            const border = this.hexToRgba(color, 0.45)

            if (!background) return null

            return {
                backgroundColor: background,
                borderColor: border || 'rgba(40, 58, 80, 0.12)',
            }
        },
        statusLabel(status) {
            const normalized = String(status || '')
                .trim()
                .toLocaleLowerCase()
            const configured = this.statusOptions.find((option) => {
                const value = String(option?.value || '')
                    .trim()
                    .toLocaleLowerCase()
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
            const normalized = String(status || '')
                .trim()
                .toLocaleLowerCase()
            const configured = this.statusOptions.find((option) => {
                const value = String(option?.value || '')
                    .trim()
                    .toLocaleLowerCase()
                return value !== '' && value === normalized
            })

            const configuredColor = this.normalizeStatusColor(configured?.color)
            if (configuredColor) {
                return configuredColor
            }

            const map = {
                inbox: 'secondary',
                in_progress: 'warning',
                done: 'success',
                update_needed: 'error',
            }
            return map[normalized] || 'primary'
        },
        classificationLabels(card) {
            const rows = Array.isArray(card?.classifications) ? card.classifications : []
            const result = []
            const seen = new Set()

            for (const row of rows) {
                const subject = String(row?.subject || '')
                    .trim()
                    .slice(0, 255)
                const topic = String(row?.topic || '')
                    .trim()
                    .slice(0, 255)
                let unit = String(row?.unit || '')
                    .trim()
                    .slice(0, 255)
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
            return String(value ?? '')
                .trim()
                .slice(0, 255)
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
                        preview_url: String(attachment?.preview_url || '').trim(),
                        download_url: String(attachment?.download_url || '').trim(),
                        url: String(attachment?.url || '').trim(),
                        source_url: String(attachment?.source_url || '').trim(),
                        downloaded_at: String(attachment?.downloaded_at || '').trim(),
                        file_path: String(attachment?.file_path || '').trim(),
                        mime_type: String(attachment?.mime_type || '').trim(),
                        size_bytes: Number(attachment?.size_bytes || 0),
                        shared_rule_id: Number(attachment?.shared_rule_id || 0) || null,
                        shared_material_id: Number(attachment?.shared_material_id || 0) || null,
                        download_docx_url: String(attachment?.download_docx_url || '').trim(),
                    }
                })
                .filter(Boolean)
        },
        isEditableTextAttachment(row) {
            if (
                String(row?.attachment_type || '')
                    .trim()
                    .toLocaleLowerCase() !== 'file'
            )
                return false

            const ext = this.attachmentExtension(row)
            const mimeType = String(row?.mime_type || '')
                .trim()
                .toLocaleLowerCase()
            return ext === 'html' || ext === 'htm' || mimeType === 'text/html' || mimeType === 'application/xhtml+xml'
        },
        escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;')
        },
        editorHtmlHasVisibleText(value) {
            const raw = String(value || '').trim()
            if (!raw) return false

            if (typeof DOMParser !== 'undefined') {
                try {
                    const doc = new DOMParser().parseFromString(raw, 'text/html')
                    const text = String(doc?.body?.textContent || '')
                        .replace(/\u00a0/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim()
                    if (text !== '') return true
                } catch {
                    // Fallback below.
                }
            }

            const plain = raw
                .replace(/<[^>]*>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .trim()
            return plain !== ''
        },
        extractEditorBodyFromDocumentHtml(value) {
            const raw = String(value || '').trim()
            if (!raw) return ''

            if (typeof DOMParser !== 'undefined') {
                try {
                    const doc = new DOMParser().parseFromString(raw, 'text/html')
                    const bodyHtml = String(doc?.body?.innerHTML || '').trim()
                    if (bodyHtml !== '') return bodyHtml
                } catch {
                    // Fallback below.
                }
            }

            return raw
        },
        buildTextAttachmentDocumentHtml(title, bodyHtml) {
            const safeTitle = this.escapeHtml(title || 'Text')
            const content = String(bodyHtml || '').trim() || '<p></p>'
            return `<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${safeTitle}</title>
<style>
body { font-family: Arial, sans-serif; line-height: 1.55; color: #1a2b3b; margin: 14px; }
p { margin: 0 0 0.65rem 0; }
ul, ol { margin: 0.45rem 0 0.75rem 0; padding-inline-start: 1.4rem; }
li { margin: 0.2rem 0; }
blockquote {
  margin: 0.75rem 0;
  padding: 0.5rem 0.75rem;
  border-left: 3px solid #fd802e;
  background: rgba(253, 128, 46, 0.10);
  border-radius: 0 6px 6px 0;
}
pre {
  background: #f5f7fb;
  border: 1px solid #d9e1f3;
  border-radius: 8px;
  padding: 10px 12px;
  overflow: auto;
}
code {
  background: #f5f7fb;
  border: 1px solid #d9e1f3;
  border-radius: 4px;
  padding: 1px 4px;
}
</style>
</head>
<body>
${content}
</body>
</html>`
        },
        ensureHtmlAttachmentName(value) {
            const normalized = this.normalizeAttachmentName(value) || 'Text'
            return /\.(html?|HTML?)$/.test(normalized) ? normalized : `${normalized}.html`
        },
        async openTextAttachmentEditor(row) {
            const id = Number(row?.id)
            if (!Number.isFinite(id) || id <= 0) return
            if (!this.isEditableTextAttachment(row)) return
            if (this.textAttachmentEditorSaving) return
            if (!this.attachmentDialogCanEditFields) {
                this.notifyLinkedPermissionRestriction('Dieses verlinkte Material ist auf NUR LESEN gesetzt.')
                return
            }

            this.textAttachmentEditorOpen = true
            this.textAttachmentEditorLoading = true
            this.textAttachmentEditorError = ''
            this.textAttachmentEditorAttachmentId = id
            this.textAttachmentEditorTitle = this.ensureHtmlAttachmentName(this.normalizeAttachmentName(row?.name) || 'Text')
            this.textAttachmentEditorBodyHtml = ''

            try {
                const sharedContext = this.sharedInboxContextForAttachment(row)
                const payload = sharedContext
                    ? await this.fetchSharedTextAttachmentContent(sharedContext, id)
                    : await this.materialCardStore.fetchTextAttachmentContent(id)
                if (!payload) {
                    this.textAttachmentEditorError = 'Text-Anhang konnte nicht geladen werden.'
                    return
                }

                const loadedTitle = this.normalizeAttachmentName(payload?.name)
                if (loadedTitle) {
                    this.textAttachmentEditorTitle = this.ensureHtmlAttachmentName(loadedTitle)
                }

                const loadedHtml = String(payload?.content_html || '')
                const bodyHtml = this.extractEditorBodyFromDocumentHtml(loadedHtml)
                this.textAttachmentEditorBodyHtml = bodyHtml || '<p></p>'
            } finally {
                this.textAttachmentEditorLoading = false
            }
        },
        closeTextAttachmentEditor(force = false) {
            if (this.textAttachmentEditorSaving && !force) return

            this.textAttachmentEditorOpen = false
            this.textAttachmentEditorLoading = false
            this.textAttachmentEditorAttachmentId = null
            this.textAttachmentEditorTitle = ''
            this.textAttachmentEditorBodyHtml = ''
            this.textAttachmentEditorError = ''
        },
        async saveTextAttachmentEditor() {
            const attachmentId = Number(this.textAttachmentEditorAttachmentId)
            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(attachmentId) || attachmentId <= 0) return
            if (!Number.isFinite(cardId) || cardId <= 0) return
            if (this.textAttachmentEditorSaving || this.textAttachmentEditorLoading) return
            if (!this.attachmentDialogCanEditFields) return

            if (!this.editorHtmlHasVisibleText(this.textAttachmentEditorBodyHtml)) {
                this.textAttachmentEditorError = 'Bitte zuerst Text eingeben.'
                return
            }

            const title = this.ensureHtmlAttachmentName(this.textAttachmentEditorTitle)
            const documentHtml = this.buildTextAttachmentDocumentHtml(title, this.textAttachmentEditorBodyHtml)

            this.textAttachmentEditorSaving = true
            this.textAttachmentEditorError = ''

            try {
                const sharedContext = this.sharedInboxContextForAttachment({
                    id: attachmentId,
                    shared_rule_id: this.attachmentDialogCardContext?.shared_rule_id,
                    shared_material_id: this.attachmentDialogCardContext?.shared_material_id,
                })
                const updated = sharedContext
                    ? await this.updateSharedTextAttachmentContent(sharedContext, attachmentId, documentHtml, title)
                    : await this.materialCardStore.updateTextAttachmentContent(attachmentId, cardId, documentHtml, title)
                if (!updated) return

                await this.refreshAttachmentDialogCard(cardId)
                this.refreshAllListedAttachmentBytes()
                this.closeTextAttachmentEditor(true)
            } finally {
                this.textAttachmentEditorSaving = false
            }
        },
        async openAttachmentManager(card) {
            const cardId = Number(card?.id)
            if (!Number.isFinite(cardId) || cardId <= 0) return

            this.attachmentDialogCardContext = this.sanitizeDialogCard(card)
            this.attachmentDialogCardId = cardId
            this.attachmentDialogCardTitle = String(card?.title || '').trim()
            this.attachmentRows = this.toAttachmentRows(card?.attachments)
            this.attachmentDeleteArmedIds = []
            this.attachmentNameEditingIds = []
            this.isUploadingAttachment = false
            this.attachmentDialogOpen = true

            if (!Array.isArray(card?.attachments)) {
                await this.refreshAttachmentDialogCard(cardId)
            }
        },
        closeAttachmentManager() {
            if (this.attachmentDialogBusy) return
            this.attachmentDialogOpen = false
            this.closeTextAttachmentEditor()
            this.attachmentDialogCardId = null
            this.attachmentDialogCardTitle = ''
            this.attachmentDialogCardContext = null
            this.attachmentRows = []
            this.attachmentDeleteArmedIds = []
            this.attachmentNameEditingIds = []
            this.isUploadingAttachment = false
            this.savingAttachmentIds = []
            this.deletingAttachmentIds = []
            this.clearAttachmentPondFiles()
        },
        clearAttachmentPondFiles() {
            const pond = this.$refs.attachmentPond
            if (pond && typeof pond.removeFiles === 'function') {
                pond.removeFiles()
            }
        },
        beforeAttachmentAddFile(fileItem) {
            const size = Number(fileItem?.file?.size || fileItem?.size || 0)
            if (!Number.isFinite(size) || size <= 0) return true

            if (size > this.maxUploadSizeBytes) {
                this.notifyUploadError(`Datei ist zu groß. Maximal erlaubt: ${this.maxUploadSizeLabel}.`)
                return false
            }

            return true
        },
        async refreshAttachmentDialogCard(cardId) {
            const id = Number(cardId)
            if (!Number.isFinite(id) || id <= 0) return

            const sharedContext = this.sharedInboxContextForCard(this.attachmentDialogCardContext || { id })
            if (sharedContext && sharedContext.materialId === id) {
                const refreshedCard = await this.fetchSharedMaterialDetail(sharedContext.ruleId, sharedContext.materialId)
                if (!refreshedCard) return

                await this.loadSharedObjectsForMe()

                const sanitizedCard = this.sanitizeDialogCard(refreshedCard)
                this.attachmentDialogCardContext = sanitizedCard
                this.attachmentRows = this.toAttachmentRows(refreshedCard?.attachments)
                if (this.detailDialogOpen && Number(this.detailDialogCard?.id || 0) === id) {
                    this.detailDialogCard = sanitizedCard
                    this.detailDialogReadOnlyMode = this.normalizeLinkedPermission(refreshedCard?.linked_permission) === 'read_only'
                }

                const nextTitle = String(refreshedCard?.title || '').trim()
                if (nextTitle) {
                    this.attachmentDialogCardTitle = nextTitle
                }

                return
            }

            const selectedCard = this.materialCardStore?.selected_card
            if (Number(selectedCard?.id) === id) {
                this.attachmentDialogCardContext = this.sanitizeDialogCard(selectedCard)
                this.mergeCardIntoOverview(selectedCard)
                this.attachmentRows = this.toAttachmentRows(selectedCard?.attachments)
                const nextTitle = String(selectedCard?.title || '').trim()
                if (nextTitle) {
                    this.attachmentDialogCardTitle = nextTitle
                }
                return
            }

            const loaded = await this.materialCardStore.show(id)
            if (!loaded) return

            const refreshedCard = this.materialCardStore?.selected_card
            if (Number(refreshedCard?.id) !== id) return
            this.attachmentDialogCardContext = this.sanitizeDialogCard(refreshedCard)
            this.mergeCardIntoOverview(refreshedCard)
            this.attachmentRows = this.toAttachmentRows(refreshedCard?.attachments)
            const nextTitle = String(refreshedCard?.title || '').trim()
            if (nextTitle) {
                this.attachmentDialogCardTitle = nextTitle
            }
        },
        async onAttachmentPondProcessFile(error, fileItem) {
            if (error) {
                this.onAttachmentPondProcessFileError(error)
                return
            }
            if (!this.attachmentDialogCanAppendContent) return

            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(cardId) || cardId <= 0) return
            if (this.attachmentDialogBusy) return
            const sharedContext = this.sharedInboxContextForCard(this.attachmentDialogCardContext || { id: cardId })

            const uploadId = String(fileItem?.serverId || '').trim()
            if (!uploadId) {
                this.onAttachmentPondProcessFileError()
                return
            }

            const fileName = String(fileItem?.filename || fileItem?.file?.name || '').trim() || 'Datei'
            const title = this.defaultAttachmentTitle(fileName)

            this.isUploadingAttachment = true
            try {
                const attached = sharedContext
                    ? await this.addSharedTempFileAttachment(sharedContext, uploadId, title)
                    : await this.materialCardStore.addTempFileAttachment(cardId, uploadId, title)
                if (!attached) {
                    await this.materialCardStore.deleteTempUpload(uploadId, false)
                    return
                }

                await this.refreshAttachmentDialogCard(cardId)
                this.refreshAllListedAttachmentBytes()
            } finally {
                this.isUploadingAttachment = false
                const pond = this.$refs.attachmentPond
                if (pond && typeof pond.removeFile === 'function') {
                    pond.removeFile(fileItem?.id)
                }
            }
        },
        onAttachmentPondProcessFileError(error) {
            const message = String(error?.main || error?.body || error?.message || '').trim()
            this.notifyUploadError(message || 'Datei konnte nicht hochgeladen werden.')
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

            const mime = String(row?.mime_type || '')
                .trim()
                .toLocaleLowerCase()
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
        attachmentSizeBytes(attachment) {
            const value = Number(attachment?.size_bytes || 0)
            if (!Number.isFinite(value) || value <= 0) return 0
            return Math.round(value)
        },
        formatBytes(bytes) {
            const value = Number(bytes || 0)
            if (!Number.isFinite(value) || value <= 0) return '0 B'

            const units = ['B', 'KB', 'MB', 'GB', 'TB']
            let size = value
            let unitIndex = 0
            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024
                unitIndex += 1
            }

            const rounded = size >= 100 || unitIndex === 0 ? Math.round(size) : Math.round(size * 10) / 10
            return `${rounded} ${units[unitIndex]}`
        },
        attachmentTypeAndSizeLabel(attachment) {
            const typeLabel = this.attachmentTypeLabel(attachment)
            const bytes = this.attachmentSizeBytes(attachment)
            if (bytes <= 0) return typeLabel
            return `${typeLabel} • ${this.formatBytes(bytes)}`
        },
        attachmentMeta(row) {
            if (String(row?.attachment_type || '').trim() === 'link') {
                return String(row?.url || '').trim() || 'Link-Anhang'
            }

            const sizeLabel = this.attachmentSizeBytes(row) > 0 ? this.formatBytes(this.attachmentSizeBytes(row)) : ''
            const ext = this.attachmentExtension(row)
            if (ext) {
                return sizeLabel ? `Dateiformat: ${ext.toUpperCase()} • ${sizeLabel}` : `Dateiformat: ${ext.toUpperCase()}`
            }

            const fallback = String(row?.file_path || '').trim() || 'Datei-Anhang'
            return sizeLabel ? `${fallback} • ${sizeLabel}` : fallback
        },
        attachmentSourceUrl(attachment) {
            const sourceUrl = String(attachment?.source_url || '').trim()
            return this.normalizeUrl(sourceUrl)
        },
        attachmentDownloadedAtLabel(attachment) {
            const value = String(attachment?.downloaded_at || '').trim()
            if (!value) return ''
            return this.formatDateTime(value)
        },
        updateAttachmentDraft(attachmentId, value) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return
            if (!this.isAttachmentNameEditing(id)) return

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
        isAttachmentDeleteArmed(attachmentId) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return false
            return this.attachmentDeleteArmedIds.includes(id)
        },
        isAttachmentNameEditing(attachmentId) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return false
            return this.attachmentNameEditingIds.includes(id)
        },
        markAttachmentNameEditing(attachmentId, isEditing) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return

            if (isEditing) {
                if (!this.attachmentNameEditingIds.includes(id)) {
                    this.attachmentNameEditingIds = [...this.attachmentNameEditingIds, id]
                }
                return
            }

            this.attachmentNameEditingIds = this.attachmentNameEditingIds.filter((item) => item !== id)
        },
        startAttachmentNameEdit(attachmentId) {
            if (!this.attachmentDialogCanEditFields) return
            this.markAttachmentNameEditing(attachmentId, true)
        },
        markAttachmentDeleteArmed(attachmentId, isArmed) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return

            if (isArmed) {
                if (!this.attachmentDeleteArmedIds.includes(id)) {
                    this.attachmentDeleteArmedIds = [...this.attachmentDeleteArmedIds, id]
                }
                return
            }

            this.attachmentDeleteArmedIds = this.attachmentDeleteArmedIds.filter((item) => item !== id)
        },
        cancelAttachmentDelete(attachmentId) {
            if (this.isAttachmentDeleting(attachmentId)) return
            this.markAttachmentDeleteArmed(attachmentId, false)
        },
        resetAttachmentDeleteArmed(validIds = []) {
            const ids = Array.isArray(validIds) ? validIds.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0) : []

            if (ids.length === 0) {
                if (this.attachmentDeleteArmedIds.length) {
                    this.attachmentDeleteArmedIds = []
                }
                return
            }

            const validSet = new Set(ids)
            this.attachmentDeleteArmedIds = this.attachmentDeleteArmedIds.filter((id) => validSet.has(id))
        },
        resetAttachmentNameEditing(validIds = []) {
            const ids = Array.isArray(validIds) ? validIds.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0) : []

            if (ids.length === 0) {
                if (this.attachmentNameEditingIds.length) {
                    this.attachmentNameEditingIds = []
                }
                return
            }

            const validSet = new Set(ids)
            this.attachmentNameEditingIds = this.attachmentNameEditingIds.filter((id) => validSet.has(id))
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
                this.markAttachmentDeleteArmed(id, false)
                this.markAttachmentNameEditing(id, false)
                if (!this.deletingAttachmentIds.includes(id)) {
                    this.deletingAttachmentIds = [...this.deletingAttachmentIds, id]
                }
                return
            }

            this.deletingAttachmentIds = this.deletingAttachmentIds.filter((item) => item !== id)
        },
        hasAttachmentNameChanged(row) {
            const name = this.normalizeAttachmentName(row?.name)
            const savedName = this.normalizeAttachmentName(row?.savedName)
            return name !== savedName
        },
        canSaveAttachmentName(row) {
            const name = this.normalizeAttachmentName(row?.name)
            return name !== ''
        },
        async saveAttachmentName(row) {
            const id = Number(row?.id)
            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(id) || id <= 0 || !Number.isFinite(cardId) || cardId <= 0) return
            if (!this.attachmentDialogCanEditFields) return
            if (this.isAttachmentSaving(id) || this.isAttachmentDeleting(id)) return
            if (!this.isAttachmentNameEditing(id)) return
            if (!this.canSaveAttachmentName(row)) return
            if (!this.hasAttachmentNameChanged(row)) {
                this.markAttachmentNameEditing(id, false)
                return
            }

            this.markAttachmentSaving(id, true)

            try {
                const sharedContext = this.sharedInboxContextForAttachment(row)
                const updated = sharedContext
                    ? await this.renameSharedAttachment(sharedContext, id, row.name)
                    : await this.materialCardStore.renameAttachment(id, cardId, row.name)
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
                this.markAttachmentNameEditing(id, false)
            } finally {
                this.markAttachmentSaving(id, false)
            }
        },
        async removeAttachment(row) {
            const id = Number(row?.id)
            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(id) || id <= 0 || !Number.isFinite(cardId) || cardId <= 0) return
            if (!this.attachmentDialogCanDeleteAttachments) {
                this.notifyLinkedPermissionRestriction('Anhänge dürfen nur mit VOLLZUGRIFF gelöscht werden.')
                return
            }
            if (this.isAttachmentDeleting(id) || this.isAttachmentSaving(id)) return
            if (!this.isAttachmentDeleteArmed(id)) {
                this.markAttachmentDeleteArmed(id, true)
                return
            }

            this.markAttachmentDeleting(id, true)

            try {
                const deleted = await this.materialCardStore.deleteAttachment(id, cardId)
                if (!deleted) return

                this.attachmentRows = this.attachmentRows.filter((item) => item.id !== id)
                this.removeAttachmentFromCard(id)
                this.refreshAllListedAttachmentBytes()
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

            if (Number(this.attachmentDialogCardContext?.id || 0) === cardId) {
                this.attachmentDialogCardContext = {
                    ...this.attachmentDialogCardContext,
                    attachments: attachments.map((attachment) => ({ ...attachment })),
                    attachments_count: attachments.length,
                }
            }

            if (Number(this.detailDialogCard?.id || 0) === cardId) {
                this.detailDialogCard = {
                    ...this.detailDialogCard,
                    attachments: attachments.map((attachment) => ({ ...attachment })),
                    attachments_count: attachments.length,
                }
            }
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

            if (Number(this.attachmentDialogCardContext?.id || 0) === cardId) {
                this.attachmentDialogCardContext = {
                    ...this.attachmentDialogCardContext,
                    attachments: nextAttachments.map((attachment) => ({ ...attachment })),
                    attachments_count: nextAttachments.length,
                }
            }

            if (Number(this.detailDialogCard?.id || 0) === cardId) {
                this.detailDialogCard = {
                    ...this.detailDialogCard,
                    attachments: nextAttachments.map((attachment) => ({ ...attachment })),
                    attachments_count: nextAttachments.length,
                }
            }
        },
        async copyTextToClipboard(value) {
            const text = String(value || '').trim()
            if (!text) return false

            if (navigator?.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text)
                    return true
                } catch {
                    // Fallback below.
                }
            }

            try {
                const textarea = document.createElement('textarea')
                textarea.value = text
                textarea.setAttribute('readonly', '')
                textarea.style.position = 'fixed'
                textarea.style.left = '-9999px'
                document.body.appendChild(textarea)
                textarea.select()
                textarea.setSelectionRange(0, text.length)
                const copied = document.execCommand('copy')
                document.body.removeChild(textarea)
                return copied
            } catch {
                return false
            }
        },
        async copyAttachmentChipToClipboard(row) {
            const isLink =
                String(row?.attachment_type || '')
                    .trim()
                    .toLocaleLowerCase() === 'link'
            const valueToCopy = isLink ? this.normalizeUrl(row?.url) : this.normalizeAttachmentName(row?.name) || this.attachmentDisplayName(row)
            if (!valueToCopy) return

            const notification = useNotificationStore()
            const copied = await this.copyTextToClipboard(valueToCopy)
            if (copied) {
                notification.notify({
                    message: isLink ? 'Link wurde in die Zwischenablage kopiert.' : 'Dateiname wurde in die Zwischenablage kopiert.',
                    type: 'success',
                    timeout: 2200,
                })
                return
            }

            notification.notify({
                message: isLink ? 'Link konnte nicht kopiert werden.' : 'Dateiname konnte nicht kopiert werden.',
                type: 'warning',
                timeout: 2800,
            })
        },
        isDownloadingAttachment(attachmentId) {
            const id = Number(attachmentId)
            return this.downloadingAttachmentIds.includes(id)
        },
        isPreviewingAttachment(attachmentId) {
            const id = Number(attachmentId)
            return this.previewingAttachmentIds.includes(id)
        },
        markAttachmentPreviewing(attachmentId, isLoading) {
            const id = Number(attachmentId)
            if (!Number.isFinite(id) || id <= 0) return

            if (isLoading) {
                if (!this.previewingAttachmentIds.includes(id)) {
                    this.previewingAttachmentIds = [...this.previewingAttachmentIds, id]
                }
                return
            }

            this.previewingAttachmentIds = this.previewingAttachmentIds.filter((item) => item !== id)
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
            const normalized = String(value || '')
                .trim()
                .replace(/[\\/:*?"<>|]/g, '_')
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
            const initialDownloadUrl = String(attachment?.download_url || '').trim()
            if (!Number.isFinite(id) || id <= 0) return
            if (!initialDownloadUrl) {
                const notification = useNotificationStore()
                notification.notify({
                    message: 'Datei ist derzeit nicht verfügbar.',
                    type: 'warning',
                    timeout: 3000,
                })
                return
            }
            if (this.isDownloadingAttachment(id)) return

            this.markAttachmentDownloading(id, true)

            try {
                let response = null
                let downloadUrl = initialDownloadUrl

                const downloadOnce = async (url) => axios.get(url, {
                    responseType: 'blob',
                })

                try {
                    response = await downloadOnce(downloadUrl)
                } catch (error) {
                    const isNotFound = Number(error?.response?.status || 0) === 404
                    if (!isNotFound) {
                        throw error
                    }

                    const refreshedDownloadUrl = await this.refreshSharedAttachmentDownloadUrl(attachment)
                    if (!refreshedDownloadUrl) {
                        throw error
                    }

                    downloadUrl = refreshedDownloadUrl
                    response = await downloadOnce(downloadUrl)
                }

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
                const status = Number(error?.response?.status || 0)
                const isNotFound = status === 404
                notification.notify({
                    status: status || error.response?.status,
                    message: isNotFound
                        ? 'Datei ist derzeit nicht verfügbar.'
                        : error.response?.data?.message || 'Datei konnte nicht heruntergeladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.markAttachmentDownloading(id, false)
            }
        },
        async refreshSharedAttachmentDownloadUrl(attachment) {
            const ruleId = Number(attachment?.shared_rule_id || 0)
            const materialId = Number(attachment?.shared_material_id || 0)
            const currentId = Number(attachment?.id || 0)
            if (!Number.isFinite(ruleId) || ruleId <= 0) return ''
            if (!Number.isFinite(materialId) || materialId <= 0) return ''
            if (!Number.isFinite(currentId) || currentId <= 0) return ''

            const freshAttachments = await this.fetchSharedMaterialAttachments(ruleId, materialId)
            if (!Array.isArray(freshAttachments) || freshAttachments.length === 0) {
                return ''
            }

            const directMatch = freshAttachments.find((row) => Number(row?.id || 0) === currentId) || null
            const nameFallback = String(attachment?.name || '').trim()
            const fallbackMatch = !directMatch && nameFallback !== ''
                ? freshAttachments.find((row) => String(row?.name || '').trim() === nameFallback) || null
                : null
            const target = directMatch || fallbackMatch
            if (!target) {
                return ''
            }

            const nextDownloadUrl = String(target?.download_url || '').trim()
            const nextPreviewUrl = String(target?.preview_url || '').trim()
            const nextDocxUrl = String(target?.download_docx_url || '').trim()

            attachment.download_url = nextDownloadUrl
            attachment.preview_url = nextPreviewUrl
            attachment.download_docx_url = nextDocxUrl

            return nextDownloadUrl
        },
        async downloadAttachmentDocx(attachment) {
            const id = Number(attachment?.id)
            if (!Number.isFinite(id) || id <= 0) return
            if (this.isDownloadingAttachment(id)) return

            const downloadUrl = String(attachment?.download_docx_url || '').trim() || `/api/admin/materials/attachments/${id}/download-docx`
            this.markAttachmentDownloading(id, true)

            try {
                const response = await axios.get(downloadUrl, {
                    responseType: 'blob',
                })

                const disposition = response?.headers?.['content-disposition']
                const serverFileName = this.filenameFromContentDisposition(disposition)
                const attachmentName = this.attachmentDisplayName(attachment).replace(/\.(html?|HTML?)$/, '')
                const fallbackName = `${attachmentName || 'Text'}.docx`
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
                    message: error.response?.data?.message || 'DOCX konnte nicht heruntergeladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.markAttachmentDownloading(id, false)
            }
        },
        async previewAttachment(attachment) {
            const id = Number(attachment?.id)
            let previewUrl = String(attachment?.preview_url || attachment?.download_url || '').trim()
            if (!Number.isFinite(id) || id <= 0) return
            if (!previewUrl) {
                await this.refreshSharedAttachmentDownloadUrl(attachment)
                previewUrl = String(attachment?.preview_url || attachment?.download_url || '').trim()
            }
            if (!previewUrl) {
                const notification = useNotificationStore()
                notification.notify({
                    message: 'Datei ist derzeit nicht verfügbar.',
                    type: 'warning',
                    timeout: 3000,
                })
                return
            }
            if (this.isPreviewingAttachment(id)) return

            const previewWindow = window.open('about:blank', '_blank')
            if (!previewWindow) {
                const notification = useNotificationStore()
                notification.notify({
                    message: 'Pop-up blockiert. Bitte Pop-ups für Vorschau erlauben.',
                    type: 'warning',
                    timeout: 3000,
                })
                return
            }

            this.markAttachmentPreviewing(id, true)

            try {
                try {
                    previewWindow.document.title = 'Vorschau wird geladen...'
                    previewWindow.document.body.innerHTML = '<p style="font-family: sans-serif; padding: 16px;">Vorschau wird geladen...</p>'
                } catch {
                    // noop
                }

                let response = null
                const previewOnce = async (url) =>
                    axios.get(url, {
                        responseType: 'blob',
                    })

                try {
                    response = await previewOnce(previewUrl)
                } catch (error) {
                    const isNotFound = Number(error?.response?.status || 0) === 404
                    if (!isNotFound) {
                        throw error
                    }

                    await this.refreshSharedAttachmentDownloadUrl(attachment)
                    const refreshedPreviewUrl = String(attachment?.preview_url || attachment?.download_url || '').trim()
                    if (!refreshedPreviewUrl) {
                        throw error
                    }

                    previewUrl = refreshedPreviewUrl
                    response = await previewOnce(previewUrl)
                }

                const contentType = String(response?.headers?.['content-type'] || '').trim()
                const blob =
                    response?.data instanceof Blob
                        ? response.data
                        : new Blob([response?.data], {
                              type: contentType || 'application/octet-stream',
                          })

                const objectUrl = URL.createObjectURL(blob)
                previewWindow.location.replace(objectUrl)
                window.setTimeout(() => {
                    URL.revokeObjectURL(objectUrl)
                }, 120000)
            } catch (error) {
                try {
                    previewWindow.close()
                } catch {
                    // noop
                }

                const notification = useNotificationStore()
                const status = Number(error?.response?.status || 0)
                notification.notify({
                    status: status || error.response?.status,
                    message:
                        status === 404
                            ? 'Datei ist derzeit nicht verfügbar.'
                            : error.response?.data?.message || 'Vorschau konnte nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.markAttachmentPreviewing(id, false)
            }
        },
        fileAttachments(card) {
            const attachments = Array.isArray(card?.attachments) ? card.attachments : []
            return attachments.filter((attachment) => {
                const isFile = String(attachment?.attachment_type || '').trim() === 'file'
                if (!isFile) {
                    return false
                }

                const hasDownloadUrl = String(attachment?.download_url || '').trim() !== ''
                const hasPreviewUrl = String(attachment?.preview_url || '').trim() !== ''
                return hasDownloadUrl || hasPreviewUrl
            })
        },
        cardAttachmentTotalBytes(card) {
            const attachments = Array.isArray(card?.attachments) ? card.attachments : []
            return attachments.reduce((sum, attachment) => sum + this.attachmentSizeBytes(attachment), 0)
        },
        attachmentCountLabel(card) {
            const count = Number(card?.attachments_count || 0)
            if (!Number.isFinite(count) || count <= 0) return '0 Anhänge'

            const sizeBytes = this.cardAttachmentTotalBytes(card)
            const countLabel = count === 1 ? '1 Anhang' : `${count} Anhänge`
            if (sizeBytes <= 0) return countLabel
            return `${countLabel} • ${this.formatBytes(sizeBytes)}`
        },
        attachmentCountCompactLabel(card) {
            const count = Number(card?.attachments_count || 0)
            if (!Number.isFinite(count) || count <= 0) return '0'

            const sizeBytes = this.cardAttachmentTotalBytes(card)
            if (sizeBytes <= 0) return `${count}`
            return `${count} • ${this.formatBytes(sizeBytes)}`
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
            const sizeLabel = this.attachmentSizeBytes(attachment) > 0 ? this.formatBytes(this.attachmentSizeBytes(attachment)) : ''
            const nameWithType = ext ? `${name} (${ext.toUpperCase()})` : name
            return sizeLabel ? `${nameWithType} • ${sizeLabel}` : nameWithType
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
.material-filters-wrap {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 12px;
    align-items: start;
}

.filter-section {
    min-width: 0;
}

.subject-dependent-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    align-items: start;
}

.subject-dependent-filter {
    min-width: 0;
}

.filter-chip-badge {
    display: inline-flex;
}

.filter-chip-badge :deep(.v-badge__badge) {
    top: -12px;
    background: rgba(35, 61, 76, 0.16) !important;
    color: rgba(35, 61, 76, 0.9) !important;
    font-weight: 600;
    box-shadow: none;
}

.overview-item {
    border: 1px solid rgba(40, 58, 80, 0.12);
    background-color: rgba(255, 255, 255, 0.72);
}

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

.overview-mode-toggle {
    max-width: 100%;
}

.overview-sort-toggle {
    max-width: 100%;
}

.subjects-source-switch {
    display: inline-flex;
    gap: 4px;
    padding: 4px;
    border-radius: 999px;
    border: 1px solid rgba(35, 61, 76, 0.14);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.84) 0%, rgba(245, 236, 228, 0.74) 100%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
}

.subjects-source-switch__btn {
    border-radius: 999px;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
}

.subjects-source-switch__btn--active {
    box-shadow: 0 6px 16px rgba(35, 61, 76, 0.2);
}

.shared-objects-grid {
    margin: 0;
}

.shared-object-card {
    border-color: rgba(35, 61, 76, 0.18) !important;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.66) 100%);
}

.materials-shell {
    --inbox-font-all-materials: clamp(18px, 1rem + 1vw, 24px);
    --inbox-font-subject: clamp(16px, 0.9rem + 0.65vw, 20px);
    --inbox-font-topic: clamp(14px, 0.82rem + 0.45vw, 16px);
    --inbox-font-unit: clamp(11px, 0.64rem + 0.2vw, 12px);
    --inbox-font-material: var(--inbox-font-unit);
}

.inbox-shared-object-title {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    font-size: var(--inbox-font-topic);
    font-weight: 700;
    color: #233d4c;
    line-height: 1.25;
    margin-top: 2px;
}

.inbox-shared-object-title--all {
    font-size: var(--inbox-font-all-materials);
}

.inbox-shared-hierarchy {
    margin-top: 8px;
    padding: 10px;
    --inbox-hierarchy-indent-step: clamp(0.7rem, 1.2vw, 1.1rem);
    --inbox-hierarchy-guide-color: rgba(31, 111, 139, 0.22);
}

.inbox-hierarchy-card {
    margin-top: 8px;
    background: rgba(255, 255, 255, 0.68);
    border: 1px solid rgba(35, 61, 76, 0.16);
    border-radius: 10px;
}

.inbox-hierarchy-subject + .inbox-hierarchy-subject {
    margin-top: 10px;
}

.inbox-hierarchy-topic {
    margin-top: 6px;
}

.inbox-hierarchy-subject-head {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    flex-wrap: wrap;
}

.inbox-hierarchy-context-line {
    font-size: var(--inbox-font-subject);
    font-weight: 400;
    color: #3a5668;
    line-height: 1.25;
}

.inbox-hierarchy-unit {
    margin-top: 6px;
}

.inbox-hierarchy-level-subject {
    margin-left: 0;
}

.inbox-hierarchy-level-topic {
    margin-left: var(--inbox-hierarchy-indent-step);
    padding-left: 8px;
    border-left: 1px solid var(--inbox-hierarchy-guide-color);
}

.inbox-hierarchy-level-unit {
    margin-left: calc(var(--inbox-hierarchy-indent-step) * 2);
    padding-left: 8px;
    border-left: 1px solid var(--inbox-hierarchy-guide-color);
}

.inbox-hierarchy-level-material {
    margin-left: calc(var(--inbox-hierarchy-indent-step) * 3);
    padding-left: 6px;
    border-left: 1px dotted rgba(31, 111, 139, 0.26);
}

.inbox-hierarchy-unit-title {
    font-size: var(--inbox-font-unit);
    font-weight: 600;
    color: #3a5668;
}

.inbox-hierarchy-topic-title {
    margin-top: 2px;
    font-size: var(--inbox-font-topic);
    font-weight: 700;
    color: #2f4b5c;
}

.inbox-hierarchy-topic-head {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    flex-wrap: wrap;
}

.inbox-hierarchy-unit-head {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    flex-wrap: wrap;
}

.inbox-hierarchy-material-lines {
    margin-top: 4px;
    display: grid;
    gap: 3px;
}

.inbox-hierarchy-material-line {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    font-size: var(--inbox-font-material);
    font-weight: 400;
    color: #233d4c;
}

.inbox-hierarchy-material-title {
    font-size: var(--inbox-font-material);
    font-weight: 400;
    line-height: 1.2;
}

.inbox-hierarchy-material-title-clickable {
    cursor: pointer;
}

.inbox-hierarchy-material-count {
    display: inline-flex;
    align-items: center;
    padding: 0 6px;
    border-radius: 999px;
    border: 1px solid rgba(35, 61, 76, 0.2);
    background: rgba(35, 61, 76, 0.06);
    font-size: 0.72rem;
}

.inbox-hierarchy-material-count-clickable {
    cursor: pointer;
}

.overview-subjects-tree {
    border: 1px solid rgba(35, 61, 76, 0.18);
    border-radius: 14px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.72) 0%, rgba(255, 255, 255, 0.6) 100%);
    padding: 14px;
}

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
    font: inherit;
}

.overview-subjects-material-count--clickable {
    cursor: pointer;
}

.overview-subjects-material-count--clickable:disabled {
    cursor: default;
    opacity: 0.7;
}

.overview-subjects-material-count--clickable:not(:disabled):hover {
    background: rgba(35, 61, 76, 0.1);
}

.overview-grid {
    margin-left: -8px;
    margin-right: -8px;
}

.overview-grid-item {
    border: 1px solid rgba(40, 58, 80, 0.12);
    background-color: rgba(255, 255, 255, 0.72);
    min-height: 100%;
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

.overview-actions {
    min-width: 140px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.overview-pagination {
    width: 100%;
}

.page-indicator {
    margin-right: 4px;
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

.link-copy-chip {
    cursor: pointer;
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

.attachment-name-readonly {
    min-height: 24px;
    padding: 2px 0;
    color: rgba(26, 43, 59, 0.92);
    font-size: 0.95rem;
    line-height: 1.4;
    display: flex;
    align-items: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attachment-name-readonly-row {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
    max-width: 100%;
}

.attachment-name-readonly-row .attachment-name-readonly {
    flex: 0 1 auto;
    min-width: 0;
    max-width: 100%;
}

.attachment-name-readonly-row :deep(.v-btn) {
    flex: 0 0 auto;
}

.attachment-manage-actions {
    min-width: 0;
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

.attachment-meta-text {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
    .classification-chip,
    .attachment-chip {
        max-width: 100%;
    }

    .attachment-manage-actions {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }

    .attachment-manage-actions :deep(.v-btn) {
        width: 100%;
    }

    .detail-attachment-actions {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>
