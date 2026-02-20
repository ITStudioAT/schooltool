<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <v-row align="center" class="mb-4">
            <v-col cols="12" md="8">
                <div class="text-h4 font-weight-bold mb-2">Übersicht</div>
                <div class="text-subtitle-1 subline">Hier siehst du alle aktuell gespeicherten Materialien.</div>
            </v-col>

            <v-col cols="12" md="4" class="d-flex justify-md-end align-center flex-wrap ga-2">
                <v-btn-toggle
                    :model-value="overviewViewMode"
                    mandatory
                    color="primary"
                    variant="tonal"
                    density="comfortable"
                    class="overview-mode-toggle"
                    @update:modelValue="setOverviewMode">
                    <v-btn value="list" prepend-icon="mdi-format-list-bulleted">
                        Liste
                    </v-btn>
                    <v-btn value="grid" prepend-icon="mdi-view-grid-outline">
                        Karten
                    </v-btn>
                    <v-btn value="alpha" prepend-icon="mdi-sort-alphabetical-ascending">
                        A-Z
                    </v-btn>
                </v-btn-toggle>

                <v-btn
                    prepend-icon="mdi-refresh"
                    color="primary"
                    variant="flat"
                    :loading="isLoading"
                    :disabled="isDeletingId !== null || isSavingEdit"
                    @click="loadCards">
                    Aktualisieren
                </v-btn>
            </v-col>
        </v-row>

        <div class="material-filters-wrap mb-4">
            <div class="filter-section">
                <div class="text-subtitle-2 mb-2">Fach filtern</div>

                <div class="d-flex flex-wrap ga-2">
                    <v-badge class="filter-chip-badge" inline :content="badgeCountContent(subjectAllCount)">
                        <v-chip
                            size="small"
                            :variant="hasActiveSubjectFilter ? 'tonal' : 'flat'"
                            :color="hasActiveSubjectFilter ? undefined : 'primary'"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="clearSubjectFilter">
                            Alle
                        </v-chip>
                    </v-badge>

                    <v-badge
                        v-for="subject in subjectFilterOptions"
                        :key="`subject-filter-${subject}`"
                        class="filter-chip-badge"
                        inline
                        :content="badgeCountContent(subjectFilterCount(subject))">
                        <v-chip
                            size="small"
                            color="primary"
                            :variant="isSubjectFilterActive(subject) ? 'flat' : 'tonal'"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="toggleSubjectFilter(subject)">
                            {{ subject }}
                        </v-chip>
                    </v-badge>
                </div>

                <div v-if="hasActiveSubjectFilter" class="subject-dependent-filters mt-3">
                    <div class="subject-dependent-filter">
                        <div class="text-subtitle-2 mb-2">Thema filtern</div>

                        <div class="d-flex flex-wrap ga-2">
                            <v-badge class="filter-chip-badge" inline :content="badgeCountContent(topicAllCount)">
                                <v-chip
                                    size="small"
                                    :variant="hasActiveTopicFilter ? 'tonal' : 'flat'"
                                    :color="hasActiveTopicFilter ? undefined : 'primary'"
                                    :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                                    @click="clearTopicFilter">
                                    Alle
                                </v-chip>
                            </v-badge>

                            <v-badge
                                v-for="topic in topicFilterOptions"
                                :key="`topic-filter-${topic}`"
                                class="filter-chip-badge"
                                inline
                                :content="badgeCountContent(topicFilterCount(topic))">
                                <v-chip
                                    size="small"
                                    color="primary"
                                    :variant="isTopicFilterActive(topic) ? 'flat' : 'tonal'"
                                    :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                                    @click="toggleTopicFilter(topic)">
                                    {{ topic }}
                                </v-chip>
                            </v-badge>
                        </div>
                    </div>

                    <div v-if="hasActiveTopicFilter" class="subject-dependent-filter">
                        <div class="text-subtitle-2 mb-2">Bereich filtern</div>

                        <div class="d-flex flex-wrap ga-2">
                            <v-badge class="filter-chip-badge" inline :content="badgeCountContent(unitAllCount)">
                                <v-chip
                                    size="small"
                                    :variant="hasActiveUnitFilter ? 'tonal' : 'flat'"
                                    :color="hasActiveUnitFilter ? undefined : 'primary'"
                                    :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                                    @click="clearUnitFilter">
                                    Alle
                                </v-chip>
                            </v-badge>

                            <v-badge
                                v-for="unit in unitFilterOptions"
                                :key="`unit-filter-${unit}`"
                                class="filter-chip-badge"
                                inline
                                :content="badgeCountContent(unitFilterCount(unit))">
                                <v-chip
                                    size="small"
                                    color="primary"
                                    :variant="isUnitFilterActive(unit) ? 'flat' : 'tonal'"
                                    :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                                    @click="toggleUnitFilter(unit)">
                                    {{ unit }}
                                </v-chip>
                            </v-badge>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-end mb-2">
                <v-tooltip location="top">
                    <template #activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :icon="showSecondaryFilters ? 'mdi-filter-variant-minus' : 'mdi-filter-variant-plus'"
                            size="small"
                            variant="text"
                            color="primary"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="toggleSecondaryFilters" />
                    </template>
                    <span>
                        {{ showSecondaryFilters ? 'Materialtyp- und Statusfilter ausblenden' : 'Materialtyp- und Statusfilter einblenden' }}
                    </span>
                </v-tooltip>
            </div>

            <template v-if="showSecondaryFilters">
                <div class="filter-section">
                    <div class="text-subtitle-2 mb-2">Materialtyp filtern</div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-chip
                            size="small"
                            :variant="hasActiveTypeFilter ? 'tonal' : 'flat'"
                            :color="hasActiveTypeFilter ? undefined : 'primary'"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="clearTypeFilter">
                            Alle
                        </v-chip>

                        <v-chip
                            v-for="typeOption in typeFilterOptions"
                            :key="`type-filter-${typeOption.value}`"
                            size="small"
                            :color="typeColor(typeOption.value) || 'primary'"
                            :variant="isTypeFilterActive(typeOption.value) ? 'flat' : 'tonal'"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="toggleTypeFilter(typeOption.value)">
                            {{ typeOption.label }}
                        </v-chip>
                    </div>
                </div>

                <div class="filter-section">
                    <div class="text-subtitle-2 mb-2">Status filtern</div>

                    <div class="d-flex flex-wrap ga-2">
                        <v-chip
                            size="small"
                            :variant="hasActiveStatusFilter ? 'tonal' : 'flat'"
                            :color="hasActiveStatusFilter ? undefined : 'primary'"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="clearStatusFilter">
                            Alle
                        </v-chip>

                        <v-chip
                            v-for="statusOption in statusFilterOptions"
                            :key="`status-filter-${statusOption.value}`"
                            size="small"
                            :color="statusColor(statusOption.value)"
                            :variant="isStatusFilterActive(statusOption.value) ? 'flat' : 'tonal'"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="toggleStatusFilter(statusOption.value)">
                            {{ statusOption.label }}
                        </v-chip>
                    </div>
                </div>
            </template>
        </div>

        <v-alert type="info" variant="tonal" class="mb-4">
            {{ displayedMaterials }}/{{ totalMaterials }} Material{{ totalMaterials === 1 ? '' : 'ien' }} angezeigt.
            <span class="ml-2">
                • Speicher: angezeigt {{ shownListedAttachmentSizeLabel }} / alle {{ allListedAttachmentSizeLabel }}
            </span>
        </v-alert>
        <div class="d-flex flex-wrap align-center ga-2 mb-4">
            <div class="text-caption text-medium-emphasis">Sortierung:</div>
            <v-btn-toggle
                :model-value="overviewSortMode"
                mandatory
                color="primary"
                variant="tonal"
                density="comfortable"
                class="overview-sort-toggle"
                @update:modelValue="setOverviewSortMode">
                <v-btn value="date" prepend-icon="mdi-calendar-clock">
                    Datum
                </v-btn>
                <v-btn value="name" prepend-icon="mdi-sort-alphabetical-ascending">
                    Name
                </v-btn>
            </v-btn-toggle>
        </div>

        <v-skeleton-loader v-if="isLoading && !hasCards" type="list-item-three-line@4" />

        <template v-else-if="hasCards">
            <v-row v-if="isCompactOverview" class="overview-grid ma-0">
                <v-col
                    v-for="card in sortedCards"
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
                        :style="cardBackgroundStyle(card)">
                        <v-card-text class="pa-3 d-flex flex-column ga-2">
                            <div class="overview-grid-header">
                                <v-avatar :color="statusColor(card.status)" variant="tonal" size="32">
                                    <v-icon :icon="sourceIcon(card)" :color="statusColor(card.status)" />
                                </v-avatar>

                                <v-chip size="x-small" :color="statusColor(card.status)" variant="flat" class="material-status-chip">
                                    {{ statusLabel(card.status) }}
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
                                    v-if="card.attachments_count"
                                    size="x-small"
                                    variant="flat"
                                    color="primary"
                                    prepend-icon="mdi-paperclip"
                                    class="attachments-count-chip attachments-count-chip-clickable"
                                    @click="openAttachmentManager(card)">
                                    {{ attachmentCountCompactLabel(card) }}
                                </v-chip>
                            </div>

                            <div v-if="classificationLabels(card).length" class="d-flex flex-wrap ga-1">
                                <v-chip
                                    v-for="(label, index) in classificationLabels(card).slice(0, 2)"
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
                                {{ preview(card.source_text, 160) }}
                            </div>

                            <div v-else-if="card.notes" class="text-caption text-medium-emphasis overview-grid-preview">
                                {{ preview(card.notes, 160) }}
                            </div>

                            <div v-else-if="card.source_url" class="text-caption source-link overview-grid-link">
                                <a :href="card.source_url" target="_blank" rel="noopener noreferrer">
                                    {{ preview(card.source_url, 80) }}
                                </a>
                            </div>

                            <div class="text-caption text-medium-emphasis mt-auto">
                                {{ formatDateTime(card.updated_at) }}
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
                                :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                                @click="openDetailDialog(card)">
                                <v-icon icon="mdi-eye-outline" />
                            </v-btn>

                            <v-btn
                                icon
                                size="small"
                                rounded="circle"
                                color="primary"
                                variant="flat"
                                class="overview-grid-action-btn"
                                :title="'Bearbeiten'"
                                :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                                @click="openEditDialog(card)">
                                <v-icon icon="mdi-pencil-outline" />
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>

            <v-list v-else-if="isAlphabeticOverview" class="bg-transparent pa-0">
                <v-list-item
                    v-for="card in sortedCards"
                    :key="`alpha-card-${card.id}`"
                    class="overview-alpha-item mb-2 px-3 py-2"
                    rounded="lg"
                    :style="cardBackgroundStyle(card)">
                    <template #prepend>
                        <v-avatar :color="statusColor(card.status)" variant="tonal" size="34" class="mr-3">
                            <v-icon :icon="sourceIcon(card)" :color="statusColor(card.status)" />
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
                            v-if="card.attachments_count"
                            size="small"
                            variant="flat"
                            color="primary"
                            prepend-icon="mdi-paperclip"
                            class="attachments-count-chip attachments-count-chip-clickable"
                            @click="openAttachmentManager(card)">
                            {{ attachmentCountCompactLabel(card) }}
                        </v-chip>

                        <v-chip size="small" :color="statusColor(card.status)" variant="flat" class="material-status-chip">
                            {{ statusLabel(card.status) }}
                        </v-chip>
                    </div>

                    <v-list-item-subtitle class="overview-alpha-subtitle">
                        {{ alphabeticAssignmentLine(card) }}
                    </v-list-item-subtitle>

                    <template #append>
                        <div class="overview-alpha-actions">
                            <v-btn
                                icon="mdi-eye-outline"
                                size="small"
                                color="primary"
                                variant="text"
                                :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                                @click="openDetailDialog(card)" />
                            <v-btn
                                icon="mdi-pencil-outline"
                                size="small"
                                color="primary"
                                variant="text"
                                :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                                @click="openEditDialog(card)" />
                        </div>
                    </template>
                </v-list-item>
            </v-list>

            <v-list v-else class="bg-transparent pa-0">
            <v-list-item
                v-for="card in sortedCards"
                :key="card.id"
                class="overview-item mb-3 px-4 py-3"
                rounded="lg"
                :style="cardBackgroundStyle(card)">
                <template #prepend>
                    <v-avatar :color="statusColor(card.status)" variant="tonal" size="38" class="mr-4">
                        <v-icon :icon="sourceIcon(card)" :color="statusColor(card.status)" />
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
                    </div>

                    <v-chip size="small" :color="statusColor(card.status)" variant="flat" class="material-status-chip">
                        {{ statusLabel(card.status) }}
                    </v-chip>
                </div>

                <v-list-item-subtitle>
                    <div v-if="classificationLabels(card).length" class="d-flex flex-wrap ga-2 mt-2 mb-2">
                        <v-chip
                            v-for="(label, index) in classificationLabels(card)"
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
                        {{ preview(card.source_text, 320) }}
                    </div>

                    <div v-else-if="card.notes" class="text-body-2 text-medium-emphasis mb-1 preview-text">
                        {{ preview(card.notes, 320) }}
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
                        @click="openAttachmentManager(card)">
                        {{ attachmentCountLabel(card) }}
                    </v-chip>
                </div>

                <div v-if="fileAttachments(card).length" class="attachment-block d-flex flex-column ga-2 mt-1 mb-2 pa-2">
                    <div class="attachment-chip-wrap d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="attachment in fileAttachments(card)"
                            :key="`file-attachment-${card.id}-${attachment.id}`"
                            size="small"
                            variant="outlined"
                            color="primary"
                            prepend-icon="mdi-paperclip"
                            append-icon="mdi-download"
                            class="attachment-chip"
                            :disabled="isDownloadingAttachment(attachment.id)"
                            :title="attachmentChipLabel(attachment)"
                            @click.prevent="downloadAttachment(attachment)">
                            {{ attachmentChipLabel(attachment) }}
                        </v-chip>
                    </div>
                </div>

                <div class="text-caption text-medium-emphasis mt-1">
                    Aktualisiert: {{ formatDateTime(card.updated_at) }}
                </div>

                <template #append>
                    <div class="overview-actions d-flex flex-column align-end ga-2">
                        <v-btn
                            icon="mdi-eye-outline"
                            size="small"
                            color="primary"
                            variant="tonal"
                            :title="'Detail'"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="openDetailDialog(card)" />

                        <v-btn
                            icon="mdi-pencil-outline"
                            size="small"
                            color="primary"
                            variant="flat"
                            :title="'Bearbeiten'"
                            :disabled="isLoading || isDeletingId !== null || isSavingEdit"
                            @click="openEditDialog(card)" />
                    </div>
                </template>
            </v-list-item>
            </v-list>

            <div class="overview-pagination d-flex flex-wrap align-center justify-end ga-2 mt-2">
                <div class="text-caption text-medium-emphasis page-indicator">
                    Seite {{ currentMetaPage }} von {{ lastMetaPage }}
                </div>

                <v-btn
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-page-first"
                    :disabled="isLoading || isDeletingId !== null || isSavingEdit || !hasPreviousPage"
                    @click="goToFirstPage">
                    Erste
                </v-btn>

                <v-btn
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-chevron-left"
                    :disabled="isLoading || isDeletingId !== null || isSavingEdit || !hasPreviousPage"
                    @click="goToPreviousPage">
                    Zurück
                </v-btn>

                <v-btn
                    size="small"
                    variant="tonal"
                    color="primary"
                    append-icon="mdi-chevron-right"
                    :disabled="isLoading || isDeletingId !== null || isSavingEdit || !hasNextPage"
                    @click="goToNextPage">
                    Weiter
                </v-btn>

                <v-btn
                    size="small"
                    variant="tonal"
                    color="primary"
                    append-icon="mdi-page-last"
                    :disabled="isLoading || isDeletingId !== null || isSavingEdit || !hasNextPage"
                    @click="goToLastPage">
                    Letzte
                </v-btn>
            </div>
        </template>

        <v-alert v-else type="warning" variant="tonal" class="mb-0">
            Aktuell sind keine Materialien gespeichert.
        </v-alert>
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
                <div
                    class="attachment-pond-wrap mb-3"
                    @dragover.capture="onAttachmentDragOver"
                    @drop.capture="onAttachmentDrop">
                    <file-pond
                        v-if="csrfToken"
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

                    <v-alert
                        v-else
                        type="warning"
                        variant="tonal"
                        class="mb-0">
                        Upload-Token fehlt. Bitte Seite neu laden.
                    </v-alert>
                </div>

                <div class="text-caption text-medium-emphasis mb-1">
                    Maximale Uploadgröße je Datei: {{ maxUploadSizeLabel }}
                </div>
                <div class="text-caption text-medium-emphasis mb-3">
                    Du kannst auch einen Web-Link oder ein Web-Bild hierher ziehen.
                </div>

                <v-alert v-if="!attachmentRows.length" type="info" variant="tonal" class="mb-0">
                    Keine Anhänge vorhanden.
                </v-alert>

                <v-list v-else class="bg-transparent pa-0">
                    <v-list-item
                        v-for="row in attachmentRows"
                        :key="`attachment-manage-${row.id}`"
                        class="px-0 py-2">
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

                            <div
                                v-if="attachmentSourceUrl(row)"
                                class="text-caption attachment-source-text source-link">
                                Quelle:
                                <a :href="attachmentSourceUrl(row)" target="_blank" rel="noopener noreferrer">
                                    {{ preview(attachmentSourceUrl(row), 110) }}
                                </a>
                            </div>
                            <div
                                v-if="attachmentDownloadedAtLabel(row)"
                                class="text-caption text-medium-emphasis attachment-source-text">
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
                                        :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                        @update:modelValue="updateAttachmentDraft(row.id, $event)"
                                        @keyup.enter="saveAttachmentName(row)" />
                                    <div
                                        v-else
                                        class="attachment-name-readonly-row">
                                        <div
                                            class="attachment-name-readonly"
                                            :title="row.name">
                                            {{ row.name }}
                                        </div>
                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            density="comfortable"
                                            color="primary"
                                            variant="text"
                                            :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
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
                                        :disabled="!canSaveAttachmentName(row) || isAttachmentDeleting(row.id)"
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
                                        :disabled="isAttachmentSaving(row.id) || isAttachmentDeleting(row.id) || textAttachmentEditorSaving"
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
                                        :disabled="isAttachmentSaving(row.id)"
                                        @click="removeAttachment(row)" />
                                    <v-btn
                                        v-if="isAttachmentDeleteArmed(row.id)"
                                        icon="mdi-undo"
                                        size="small"
                                        color="success"
                                        variant="text"
                                        :title="'Widerrufen'"
                                        :disabled="isAttachmentDeleting(row.id) || isAttachmentSaving(row.id)"
                                        @click="cancelAttachmentDelete(row.id)" />
                                </div>
                            </div>
                        </div>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>
    </v-dialog>

    <v-dialog v-model="detailDialogOpen" max-width="860" persistent>
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2">
                <span class="text-h5">Material-Details</span>
                <v-spacer />
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    :disabled="detailDialogLoading || isDeletingDetail"
                    @click="closeDetailDialog" />
            </v-card-title>

            <v-card-text>
                <v-skeleton-loader v-if="detailDialogLoading" type="article, list-item-two-line@3" />

                <template v-else-if="detailDialogCard">
                    <div class="material-header mb-3">
                        <div class="title-type-inline d-inline-flex align-center flex-wrap ga-2">
                            <div class="text-subtitle-1 font-weight-bold material-title">
                                {{ detailDialogCard.title || 'Material' }}
                            </div>

                            <v-chip v-if="detailDialogCard.type" size="small" variant="tonal" color="primary" class="material-type-chip">
                                {{ detailDialogCard.type }}
                            </v-chip>
                        </div>

                        <v-chip size="small" :color="statusColor(detailDialogCard.status)" variant="flat" class="material-status-chip">
                            {{ statusLabel(detailDialogCard.status) }}
                        </v-chip>
                    </div>

                    <div v-if="classificationLabels(detailDialogCard).length" class="mb-4">
                        <div class="text-subtitle-2 mb-2">Fach / Thema / Bereich</div>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip
                                v-for="(label, index) in classificationLabels(detailDialogCard)"
                                :key="`detail-classification-${detailDialogCard.id}-${index}`"
                                size="small"
                                variant="tonal"
                                color="primary"
                                class="classification-chip"
                                :title="label">
                                {{ label }}
                            </v-chip>
                        </div>
                    </div>

                    <div v-if="detailDialogCard.source_url" class="mb-4 source-link">
                        <div class="text-subtitle-2 mb-1">Link</div>
                        <a :href="detailDialogCard.source_url" target="_blank" rel="noopener noreferrer">
                            {{ detailDialogCard.source_url }}
                        </a>
                    </div>

                    <div v-if="detailDialogCard.source_text" class="mb-4">
                        <div class="text-subtitle-2 mb-1">Beschreibung</div>
                        <div class="text-body-2 detail-text">{{ detailDialogCard.source_text }}</div>
                    </div>

                    <div v-if="detailDialogCard.notes" class="mb-4">
                        <div class="text-subtitle-2 mb-1">Notiz</div>
                        <div class="text-body-2 detail-text">{{ detailDialogCard.notes }}</div>
                    </div>

                    <div v-if="detailAttachments(detailDialogCard).length" class="mb-2">
                        <div class="text-subtitle-2 mb-2">Anhänge</div>

                        <v-list class="bg-transparent pa-0">
                            <v-list-item
                                v-for="attachment in detailAttachments(detailDialogCard)"
                                :key="`detail-attachment-${detailDialogCard.id}-${attachment.id}`"
                                class="px-0 py-2">
                                <div class="d-flex flex-column flex-md-row align-md-center ga-2 w-100 detail-attachment-row">
                                    <div class="flex-grow-1 detail-attachment-content">
                                        <div class="text-body-2 font-weight-medium detail-attachment-name">
                                            {{ attachmentDisplayName(attachment) }}
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
                                                        @click="copyAttachmentChipToClipboard(attachment)">
                                                        {{ attachmentTypeLabel(attachment) }}
                                                    </v-chip>
                                                </template>
                                                <div class="d-flex align-center ga-1">
                                                    <v-icon icon="mdi-content-copy" size="14" />
                                                    <span>In Zwischenablage kopieren</span>
                                                </div>
                                            </v-tooltip>
                                            <div class="text-caption text-medium-emphasis">
                                                {{ attachmentSizeBytes(attachment) > 0 ? formatBytes(attachmentSizeBytes(attachment)) : '' }}
                                            </div>
                                        </div>
                                        <div
                                            v-if="attachmentSourceUrl(attachment)"
                                            class="text-caption attachment-source-text source-link">
                                            Quelle:
                                            <a :href="attachmentSourceUrl(attachment)" target="_blank" rel="noopener noreferrer">
                                                {{ preview(attachmentSourceUrl(attachment), 110) }}
                                            </a>
                                        </div>
                                        <div
                                            v-if="attachmentDownloadedAtLabel(attachment)"
                                            class="text-caption text-medium-emphasis attachment-source-text">
                                            Heruntergeladen: {{ attachmentDownloadedAtLabel(attachment) }}
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
                                        :loading="isPreviewingAttachment(attachment.id)"
                                        :disabled="isDeletingDetail"
                                        @click="previewAttachment(attachment)" />

                                    <v-btn
                                        v-if="attachment.attachment_type === 'file' && attachment.download_url"
                                        icon="mdi-download"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :title="'Download'"
                                        :loading="isDownloadingAttachment(attachment.id)"
                                        :disabled="isDeletingDetail"
                                        @click="downloadAttachment(attachment)" />

                                        <v-btn
                                            v-if="isEditableTextAttachment(attachment)"
                                            icon="mdi-file-word-outline"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                            :title="'DOCX'"
                                            :loading="isDownloadingAttachment(attachment.id)"
                                            :disabled="isDeletingDetail"
                                            @click="downloadAttachmentDocx(attachment)" />

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
                        Aktualisiert: {{ formatDateTime(detailDialogCard.updated_at) }}
                    </div>
                </template>
            </v-card-text>

            <v-card-actions class="px-6 pb-6 pt-2 d-flex flex-wrap justify-end ga-2">
                <template v-if="detailDeleteStep === 0">
                    <v-btn
                        color="warning"
                        variant="tonal"
                        prepend-icon="mdi-delete"
                        :disabled="detailDialogLoading || isDeletingDetail || isSavingEdit"
                        @click="startDetailDeleteFlow">
                        Löschen
                    </v-btn>
                </template>

                <template v-else>
                    <v-btn
                        color="success"
                        variant="tonal"
                        prepend-icon="mdi-delete-off"
                        :disabled="isDeletingDetail"
                        @click="resetDetailDeleteFlow">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-delete"
                        :loading="isDeletingDetail"
                        :disabled="detailDialogLoading || isSavingEdit"
                        @click="confirmDeleteFromDetail">
                        Löschen
                    </v-btn>
                </template>

                <v-btn
                    variant="text"
                    :disabled="detailDialogLoading || isDeletingDetail || isSavingEdit"
                    @click="closeDetailDialog">
                    Schließen
                </v-btn>

                <v-btn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-pencil"
                    :disabled="detailDialogLoading || isDeletingDetail || isSavingEdit"
                    @click="openEditFromDetail">
                    Bearbeiten
                </v-btn>
            </v-card-actions>
        </v-card>
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
            form-title="Material bearbeiten"
            form-subline="Titel und Beschreibung bearbeiten."
            save-label="Speichern"
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
            @save="saveEdit"
            @cancel="closeEditDialog">
            <template #extra-content>
                <div class="mt-4">
                    <div class="text-subtitle-2 mb-2">Anhänge</div>

                    <v-alert v-if="!attachmentRows.length" type="info" variant="tonal" class="mb-0">
                        Keine Anhänge vorhanden.
                    </v-alert>

                    <v-list v-else class="bg-transparent pa-0">
                        <v-list-item
                            v-for="row in attachmentRows"
                            :key="`edit-attachment-manage-${row.id}`"
                            class="px-0 py-2">
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

                                <div
                                    v-if="attachmentSourceUrl(row)"
                                    class="text-caption attachment-source-text source-link">
                                    Quelle:
                                    <a :href="attachmentSourceUrl(row)" target="_blank" rel="noopener noreferrer">
                                        {{ preview(attachmentSourceUrl(row), 110) }}
                                    </a>
                                </div>
                                <div
                                    v-if="attachmentDownloadedAtLabel(row)"
                                    class="text-caption text-medium-emphasis attachment-source-text">
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
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
                                            @update:modelValue="updateAttachmentDraft(row.id, $event)"
                                            @keyup.enter="saveAttachmentName(row)" />
                                        <div
                                            v-else
                                            class="attachment-name-readonly-row">
                                            <div
                                                class="attachment-name-readonly"
                                                :title="row.name">
                                                {{ row.name }}
                                            </div>
                                            <v-btn
                                                icon="mdi-pencil"
                                                size="x-small"
                                                density="comfortable"
                                                color="primary"
                                                variant="text"
                                                :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id)"
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
                                        :disabled="isSavingEdit || !canSaveAttachmentName(row) || isAttachmentDeleting(row.id)"
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
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id) || isAttachmentDeleting(row.id) || textAttachmentEditorSaving"
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
                                            :icon="isAttachmentDeleteArmed(row.id) ? 'mdi-delete' : 'mdi-delete-outline'"
                                            size="small"
                                            :color="isAttachmentDeleteArmed(row.id) ? 'error' : 'warning'"
                                            :variant="isAttachmentDeleteArmed(row.id) ? 'flat' : 'tonal'"
                                            :title="isAttachmentDeleteArmed(row.id) ? 'Jetzt löschen' : 'Löschen'"
                                            :loading="isAttachmentDeleting(row.id)"
                                            :disabled="isSavingEdit || isAttachmentSaving(row.id)"
                                            @click="removeAttachment(row)" />
                                        <v-btn
                                            v-if="isAttachmentDeleteArmed(row.id)"
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

    <v-dialog v-model="textAttachmentEditorOpen" max-width="920" persistent>
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2">
                <span class="text-h6">Text-Anhang bearbeiten</span>
                <v-spacer />
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    :disabled="textAttachmentEditorSaving"
                    @click="closeTextAttachmentEditor" />
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

                    <v-alert
                        v-if="textAttachmentEditorError"
                        type="warning"
                        variant="tonal"
                        density="compact"
                        class="mt-3">
                        {{ textAttachmentEditorError }}
                    </v-alert>
                </template>
            </v-card-text>

            <v-card-actions class="px-6 pb-5">
                <v-spacer />
                <v-btn
                    variant="text"
                    :disabled="textAttachmentEditorSaving"
                    @click="closeTextAttachmentEditor">
                    Abbrechen
                </v-btn>
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
})

export default {
    name: 'MaterialsOverviewView',
    components: {
        FilePond,
        ItsRichTextEditor,
        MaterialsCreateInlineForm,
        MaterialTypeManagerDialog,
    },
    data() {
        return {
            materialCardStore: null,
            isLoading: false,
            isSavingEdit: false,
            isDeletingId: null,
            editDialogOpen: false,
            attachmentDialogOpen: false,
            attachmentDialogCardId: null,
            attachmentDialogCardTitle: '',
            attachmentRows: [],
            isUploadingAttachment: false,
            csrfToken: null,
            overviewViewMode: 'list',
            overviewSortMode: 'date',
            showSecondaryFilters: false,
            currentPage: 1,
            subjectFilter: '',
            topicFilter: '',
            unitFilter: '',
            typeFilter: '',
            statusFilter: '',
            typeManagerDialogOpen: false,
            editClassificationEditorVisible: false,
            editForm: createDefaultEditForm(),
            downloadingAttachmentIds: [],
            previewingAttachmentIds: [],
            savingAttachmentIds: [],
            deletingAttachmentIds: [],
            detailDialogOpen: false,
            detailDialogLoading: false,
            detailDialogCard: null,
            detailDeleteStep: 0,
            returnToDetailOnEditCancel: false,
            detailCardForEditReturn: null,
            attachmentDeleteArmedIds: [],
            attachmentNameEditingIds: [],
            allListedAttachmentBytes: null,
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
        }
    },
    watch: {
        attachmentRows() {
            const validIds = this.attachmentRows
                .map((row) => Number(row?.id))
                .filter((id) => Number.isFinite(id) && id > 0)
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
        canSaveEdit() {
            return String(this.editForm.title || '').trim().length > 0
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
            return this.savingAttachmentIds.length > 0
                || this.deletingAttachmentIds.length > 0
                || this.isUploadingAttachment
                || this.textAttachmentEditorSaving
        },
        isDeletingDetail() {
            const id = Number(this.detailDialogCard?.id)
            if (!Number.isFinite(id) || id <= 0) return false
            return this.isDeletingId === id
        },
        subjectFilterOptions() {
            const options = this.classificationTree
                .map((entry) => String(entry?.name || '').trim())
                .filter((value) => value !== '')
                .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }))

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

            const subjectNode = this.classificationTree.find((entry) =>
                this.normalizeFilterText(entry?.name).toLocaleLowerCase() === selectedSubject.toLocaleLowerCase()
            )
            const topics = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []

            const options = topics
                .map((entry) => this.normalizeFilterText(entry?.name))
                .filter((value) => value !== '')
                .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }))

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

            const subjectNode = this.classificationTree.find((entry) =>
                this.normalizeFilterText(entry?.name).toLocaleLowerCase() === selectedSubject.toLocaleLowerCase()
            )
            const topics = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []
            const topicNode = topics.find((entry) =>
                this.normalizeFilterText(entry?.name).toLocaleLowerCase() === selectedTopic.toLocaleLowerCase()
            )
            const units = Array.isArray(topicNode?.units) ? topicNode.units : []

            const options = units
                .map((entry) => this.normalizeFilterText(entry?.name))
                .filter((value) => value !== '')
                .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }))

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
            const allowedModes = ['list', 'grid', 'alpha']
            this.overviewViewMode = allowedModes.includes(storedMode) ? storedMode : 'list'
        } catch {
            this.overviewViewMode = 'list'
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
        if (!this.materialCardStore.config) {
            await this.materialCardStore.loadConfig()
        }
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
    },
    methods: {
        setOverviewMode(value) {
            const nextMode = ['list', 'grid', 'alpha'].includes(String(value)) ? String(value) : 'list'
            if (nextMode === this.overviewViewMode) return
            this.overviewViewMode = nextMode
            try {
                window?.localStorage?.setItem?.('materials.overview.mode', nextMode)
            } catch {
                // Falls localStorage nicht verfügbar ist, nur im aktuellen Zustand bleiben.
            }
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
                this.refreshAllListedAttachmentBytes()
            }
            this.isLoading = false
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
        async refreshAllListedAttachmentBytes() {
            const shownBytes = this.totalListedAttachmentBytes
            const totalMaterials = Number(this.totalMaterials)
            const displayedMaterials = Number(this.displayedMaterials)
            const requestId = this.allListedAttachmentBytesRequestId + 1
            this.allListedAttachmentBytesRequestId = requestId

            if (!Number.isFinite(totalMaterials) || totalMaterials <= 0) {
                this.allListedAttachmentBytes = 0
                this.allListedAttachmentBytesLoading = false
                return
            }

            if (totalMaterials <= displayedMaterials) {
                this.allListedAttachmentBytes = shownBytes
                this.allListedAttachmentBytesLoading = false
                return
            }

            this.allListedAttachmentBytesLoading = true
            const filters = { ...(this.materialCardStore?.filters || {}) }
            const snapshot = await this.materialCardStore.listAllCardsSnapshot(filters)
            if (requestId !== this.allListedAttachmentBytesRequestId) return

            if (Array.isArray(snapshot)) {
                this.allListedAttachmentBytes = this.calculateAttachmentBytesForCards(snapshot)
            } else {
                this.allListedAttachmentBytes = null
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
            return String(value ?? '').trim().slice(0, 255)
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

            return this.cardHasClassificationValue(card, 'subject', filters.subject)
                && this.cardHasClassificationValue(card, 'topic', filters.topic)
                && this.cardHasClassificationValue(card, 'unit', filters.unit)
                && this.cardMatchesTypeFilterValue(card, filters.type)
                && this.cardMatchesStatusFilterValue(card, filters.status)
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

                return String(parsed.href || '').trim().slice(0, 2048)
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
            const dt = event?.dataTransfer
            if (!dt) return

            const hasFiles = (dt.files && dt.files.length > 0)
                || Array.from(dt.items || []).some((item) => item?.kind === 'file')
            if (hasFiles) return

            const urls = this.extractDropUrls(dt)
            if (urls.length > 0 && typeof event.preventDefault === 'function') {
                event.preventDefault()
            }
        },
        async onAttachmentDrop(event) {
            const dt = event?.dataTransfer
            if (!dt) return

            const hasFiles = (dt.files && dt.files.length > 0)
                || Array.from(dt.items || []).some((item) => item?.kind === 'file')
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

            const urls = this.extractDropUrls(dt)
            if (!urls.length) return

            const knownLinks = new Set(
                this.attachmentRows
                    .filter((row) => String(row?.attachment_type || '').trim().toLocaleLowerCase() === 'link')
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
                    const linkAdded = await this.materialCardStore.addLinkAttachment(cardId, {
                        url: normalizedUrl,
                        name: attachmentTitle,
                    })

                    if (!linkAdded) continue
                    changed = true

                    if (this.isLikelyImageUrl(normalizedUrl)) {
                        const imageStored = await this.materialCardStore.addImageUrlAttachment(
                            cardId,
                            normalizedUrl,
                            attachmentTitle
                        )
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
                const attachmentType = String(item?.attachmentType || '').trim().toLocaleLowerCase()
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
            const incoming = Array.isArray(files) ? files : []
            if (!incoming.length) return

            this.editForm.pendingAttachments = this.mergeUniquePendingAttachments(
                this.editForm.pendingAttachments,
                incoming,
                'picker'
            )
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
        async cleanupPendingTempUploads(rows = null) {
            const list = this.toPendingAttachments(rows ?? this.editForm.pendingAttachments)
            const uploads = list
                .map((item) => String(item?.tempUpload || '').trim())
                .filter((value) => value !== '')

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
            this.detailDeleteStep = 0
            this.returnToDetailOnEditCancel = false
            this.detailCardForEditReturn = null
        },
        detailAttachments(card) {
            return Array.isArray(card?.attachments) ? card.attachments : []
        },
        openEditFromDetail() {
            if (!this.detailDialogCard || this.detailDialogLoading || this.isDeletingDetail) return

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
        openEditDialog(card) {
            this.editForm = {
                id: card?.id ?? null,
                title: card?.title || '',
                description: card?.source_text || card?.notes || '',
                classifications: Array.isArray(card?.classifications) && card.classifications.length > 0
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
            }
            this.attachmentDialogCardId = Number(card?.id) || null
            this.attachmentRows = this.toAttachmentRows(card?.attachments)
            this.attachmentDeleteArmedIds = []
            this.attachmentNameEditingIds = []
            this.editClassificationEditorVisible = false
            this.editDialogOpen = true
        },
        async closeEditDialog(restoreDetail = true) {
            if (this.isSavingEdit) return

            await this.cleanupPendingTempUploads(this.editForm.pendingAttachments)
            this.closeTextAttachmentEditor()

            const shouldRestoreDetail = restoreDetail
                && this.returnToDetailOnEditCancel
                && this.detailCardForEditReturn
            const restoreCard = shouldRestoreDetail
                ? this.sanitizeDialogCard(this.detailCardForEditReturn)
                : null

            this.editDialogOpen = false
            this.editClassificationEditorVisible = false
            this.editForm = createDefaultEditForm()
            this.attachmentDialogCardId = null
            this.attachmentRows = []
            this.attachmentDeleteArmedIds = []
            this.attachmentNameEditingIds = []
            this.returnToDetailOnEditCancel = false
            this.detailCardForEditReturn = null

            if (restoreCard) {
                await this.openDetailDialog(restoreCard)
            }
        },
        openTypeManager() {
            if (!this.canManageTypeValues) return
            this.typeManagerDialogOpen = true
        },
        async saveEdit() {
            if (!this.canSaveEdit || this.isSavingEdit || !this.editForm.id) return

            this.isSavingEdit = true
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

            const updated = await this.materialCardStore.update(this.editForm.id, payload)

            if (updated) {
                const pendingAttachments = this.toPendingAttachments(this.editForm.pendingAttachments)
                for (const attachment of pendingAttachments) {
                    if (attachment.attachmentType === 'link' && this.normalizeUrl(attachment.url)) {
                        const normalizedUrl = this.normalizeUrl(attachment.url)
                        const normalizedName = this.toNullable(attachment.title) || this.defaultLinkTitle(attachment.url)
                        await this.materialCardStore.addLinkAttachment(this.editForm.id, {
                            url: normalizedUrl,
                            name: normalizedName,
                        })

                        if (attachment.storeImageFile === true) {
                            await this.materialCardStore.addImageUrlAttachment(this.editForm.id, normalizedUrl, normalizedName)
                        }
                        continue
                    }

                    if (attachment.tempUpload) {
                        await this.materialCardStore.addTempFileAttachment(
                            this.editForm.id,
                            attachment.tempUpload,
                            this.toNullable(attachment.title) || attachment.fileName || ''
                        )
                        continue
                    }

                    if (attachment.file instanceof File) {
                        await this.materialCardStore.addFileAttachment(
                            this.editForm.id,
                            attachment.file,
                            this.toNullable(attachment.title) || attachment.file.name || ''
                        )
                    }
                }
            }

            this.isSavingEdit = false

            if (updated) {
                await this.closeEditDialog(true)
                await this.loadCards(null, { forceFilterCountRefresh: true })
            }
        },
        normalizeClassifications(value) {
            const input = Array.isArray(value) ? value : []
            const result = []
            const seen = new Set()

            for (const row of input) {
                if (!row || typeof row !== 'object') continue
                const subject = String(row.subject ?? '').trim().slice(0, 255)
                const topic = String(row.topic ?? '').trim().slice(0, 255)
                let unit = String(row.unit ?? '').trim().slice(0, 255)
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
        sourceIcon(card) {
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
            const value = String(typeValue || '').trim().toLocaleLowerCase()
            if (!value) return ''

            const option = this.typeOptions.find((entry) => {
                const entryValue = String(entry?.value || '').trim().toLocaleLowerCase()
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
            const normalized = String(status || '').trim().toLocaleLowerCase()
            const configured = this.statusOptions.find((option) => {
                const value = String(option?.value || '').trim().toLocaleLowerCase()
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
            const normalized = String(status || '').trim().toLocaleLowerCase()
            const configured = this.statusOptions.find((option) => {
                const value = String(option?.value || '').trim().toLocaleLowerCase()
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
                const subject = String(row?.subject || '').trim().slice(0, 255)
                const topic = String(row?.topic || '').trim().slice(0, 255)
                let unit = String(row?.unit || '').trim().slice(0, 255)
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
            return String(value ?? '').trim().slice(0, 255)
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
                    }
                })
                .filter(Boolean)
        },
        isEditableTextAttachment(row) {
            if (String(row?.attachment_type || '').trim().toLocaleLowerCase() !== 'file') return false

            const ext = this.attachmentExtension(row)
            const mimeType = String(row?.mime_type || '').trim().toLocaleLowerCase()
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
            return /\.(html?|HTML?)$/.test(normalized)
                ? normalized
                : `${normalized}.html`
        },
        async openTextAttachmentEditor(row) {
            const id = Number(row?.id)
            if (!Number.isFinite(id) || id <= 0) return
            if (!this.isEditableTextAttachment(row)) return
            if (this.textAttachmentEditorSaving) return

            this.textAttachmentEditorOpen = true
            this.textAttachmentEditorLoading = true
            this.textAttachmentEditorError = ''
            this.textAttachmentEditorAttachmentId = id
            this.textAttachmentEditorTitle = this.ensureHtmlAttachmentName(this.normalizeAttachmentName(row?.name) || 'Text')
            this.textAttachmentEditorBodyHtml = ''

            try {
                const payload = await this.materialCardStore.fetchTextAttachmentContent(id)
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

            if (!this.editorHtmlHasVisibleText(this.textAttachmentEditorBodyHtml)) {
                this.textAttachmentEditorError = 'Bitte zuerst Text eingeben.'
                return
            }

            const title = this.ensureHtmlAttachmentName(this.textAttachmentEditorTitle)
            const documentHtml = this.buildTextAttachmentDocumentHtml(title, this.textAttachmentEditorBodyHtml)

            this.textAttachmentEditorSaving = true
            this.textAttachmentEditorError = ''

            try {
                const updated = await this.materialCardStore.updateTextAttachmentContent(
                    attachmentId,
                    cardId,
                    documentHtml,
                    title
                )
                if (!updated) return

                await this.refreshAttachmentDialogCard(cardId)
                this.refreshAllListedAttachmentBytes()
                this.closeTextAttachmentEditor(true)
            } finally {
                this.textAttachmentEditorSaving = false
            }
        },
        openAttachmentManager(card) {
            this.attachmentDialogCardId = Number(card?.id) || null
            this.attachmentDialogCardTitle = String(card?.title || '').trim()
            this.attachmentRows = this.toAttachmentRows(card?.attachments)
            this.attachmentDeleteArmedIds = []
            this.attachmentNameEditingIds = []
            this.isUploadingAttachment = false
            this.attachmentDialogOpen = true
        },
        closeAttachmentManager() {
            if (this.attachmentDialogBusy) return
            this.attachmentDialogOpen = false
            this.closeTextAttachmentEditor()
            this.attachmentDialogCardId = null
            this.attachmentDialogCardTitle = ''
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

            const selectedCard = this.materialCardStore?.selected_card
            if (Number(selectedCard?.id) === id) {
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

            const cardId = Number(this.attachmentDialogCardId)
            if (!Number.isFinite(cardId) || cardId <= 0) return
            if (this.attachmentDialogBusy) return

            const uploadId = String(fileItem?.serverId || '').trim()
            if (!uploadId) {
                this.onAttachmentPondProcessFileError()
                return
            }

            const fileName = String(fileItem?.filename || fileItem?.file?.name || '').trim() || 'Datei'
            const title = this.defaultAttachmentTitle(fileName)

            this.isUploadingAttachment = true
            try {
                const attached = await this.materialCardStore.addTempFileAttachment(cardId, uploadId, title)
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

            const mime = String(row?.mime_type || '').trim().toLocaleLowerCase()
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

            const rounded = size >= 100 || unitIndex === 0
                ? Math.round(size)
                : Math.round(size * 10) / 10
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
                return sizeLabel
                    ? `Dateiformat: ${ext.toUpperCase()} • ${sizeLabel}`
                    : `Dateiformat: ${ext.toUpperCase()}`
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
            const ids = Array.isArray(validIds)
                ? validIds.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0)
                : []

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
            const ids = Array.isArray(validIds)
                ? validIds.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0)
                : []

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
            if (this.isAttachmentSaving(id) || this.isAttachmentDeleting(id)) return
            if (!this.isAttachmentNameEditing(id)) return
            if (!this.canSaveAttachmentName(row)) return
            if (!this.hasAttachmentNameChanged(row)) {
                this.markAttachmentNameEditing(id, false)
                return
            }

            this.markAttachmentSaving(id, true)

            try {
                const updated = await this.materialCardStore.renameAttachment(id, cardId, row.name)
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
            const isLink = String(row?.attachment_type || '').trim().toLocaleLowerCase() === 'link'
            const valueToCopy = isLink
                ? this.normalizeUrl(row?.url)
                : this.normalizeAttachmentName(row?.name) || this.attachmentDisplayName(row)
            if (!valueToCopy) return

            const notification = useNotificationStore()
            const copied = await this.copyTextToClipboard(valueToCopy)
            if (copied) {
                notification.notify({
                    message: isLink
                        ? 'Link wurde in die Zwischenablage kopiert.'
                        : 'Dateiname wurde in die Zwischenablage kopiert.',
                    type: 'success',
                    timeout: 2200,
                })
                return
            }

            notification.notify({
                message: isLink
                    ? 'Link konnte nicht kopiert werden.'
                    : 'Dateiname konnte nicht kopiert werden.',
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
            const normalized = String(value || '').trim().replace(/[\\/:*?"<>|]/g, '_')
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
            const downloadUrl = String(attachment?.download_url || '').trim()
            if (!Number.isFinite(id) || id <= 0 || !downloadUrl) return
            if (this.isDownloadingAttachment(id)) return

            this.markAttachmentDownloading(id, true)

            try {
                const response = await axios.get(downloadUrl, {
                    responseType: 'blob',
                })

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
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Datei konnte nicht heruntergeladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.markAttachmentDownloading(id, false)
            }
        },
        async downloadAttachmentDocx(attachment) {
            const id = Number(attachment?.id)
            if (!Number.isFinite(id) || id <= 0) return
            if (this.isDownloadingAttachment(id)) return

            const downloadUrl = `/api/admin/materials/attachments/${id}/download-docx`
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
            const previewUrl = String(attachment?.preview_url || attachment?.download_url || '').trim()
            if (!Number.isFinite(id) || id <= 0 || !previewUrl) return
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

                const response = await axios.get(previewUrl, {
                    responseType: 'blob',
                })

                const contentType = String(response?.headers?.['content-type'] || '').trim()
                const blob = response?.data instanceof Blob
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
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Vorschau konnte nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.markAttachmentPreviewing(id, false)
            }
        },
        fileAttachments(card) {
            const attachments = Array.isArray(card?.attachments) ? card.attachments : []
            return attachments.filter((attachment) =>
                String(attachment?.attachment_type || '').trim() === 'file'
                && String(attachment?.download_url || '').trim() !== ''
            )
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
            const sizeLabel = this.attachmentSizeBytes(attachment) > 0
                ? this.formatBytes(this.attachmentSizeBytes(attachment))
                : ''
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
    .overview-mode-toggle {
        width: 100%;
    }

    .overview-mode-toggle :deep(.v-btn) {
        flex: 1 1 0;
    }

    .overview-sort-toggle {
        width: 100%;
    }

    .overview-sort-toggle :deep(.v-btn) {
        flex: 1 1 0;
    }

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
        justify-self: stretch;
    }

    .classification-chip,
    .attachment-chip {
        max-width: 100%;
    }

    .overview-actions {
        margin-top: 8px;
        width: 100%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }

    .overview-actions :deep(.v-btn) {
        width: 100%;
    }

    .overview-pagination {
        justify-content: stretch;
    }

    .overview-pagination :deep(.v-btn) {
        flex: 1 1 auto;
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
