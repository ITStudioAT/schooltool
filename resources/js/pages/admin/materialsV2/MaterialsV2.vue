<template>
    <v-container fluid class="materials-v2-page pa-0">
        <div class="materials-v2-orb materials-v2-orb--one" />
        <div class="materials-v2-orb materials-v2-orb--two" />

        <div class="materials-v2-content">
            <MaterialsV2Header
                :search="search"
                :loading="loading"
                :school-name="schoolName"
                :school-logo-src="schoolLogoSrc"
                @update:search="search = $event"
                @clear="clearSearch" />

            <nav class="materials-v2-system-navigation" aria-label="Systemkategorien">
                <v-tabs
                    :model-value="isAdminView ? null : selectedCategory"
                    class="materials-v2-system-tabs"
                    color="#ff7a32"
                    slider-color="#ff7a32"
                    height="56"
                    :mandatory="false"
                    :disabled="administrationLocked"
                    show-arrows>
                    <template v-for="category in systemCategoryDetails" :key="category.name">
                        <v-tab
                            class="materials-v2-system-tab"
                            :value="category.name"
                            :prepend-icon="category.icon"
                            @click="selectCategory(category.name)">
                            <span>{{ category.label }}</span>
                            <span class="materials-v2-system-tab-count">{{ category.items_count }}</span>
                        </v-tab>
                        <v-btn
                            v-if="isDefaultCategory(category.name)"
                            class="materials-v2-system-tab-create-button"
                            color="#ff7a32"
                            icon="mdi-plus"
                            size="x-small"
                            variant="text"
                            :disabled="administrationLocked"
                            :title="createActionLabel(category.name, 'Material erstellen')"
                            :aria-label="createActionLabel(category.name, 'Material erstellen')"
                            @click.stop="openSystemCategoryCreateDialog(category.name)" />
                    </template>
                </v-tabs>
                <v-btn
                    v-if="canManageAdministration"
                    class="materials-v2-admin-button"
                    :class="{ 'materials-v2-admin-button--active': isAdminView }"
                    :aria-pressed="isAdminView"
                    variant="text"
                    height="56"
                    rounded="0"
                    prepend-icon="mdi-shield-account-outline"
                    @click="openAdministration()">
                    Admin
                </v-btn>
            </nav>

            <section v-if="isAdminView" class="materials-v2-administration">
                <nav class="materials-v2-admin-panels" aria-label="Materialien Administration">
                    <v-btn-toggle
                        :model-value="selectedAdministrationPanel"
                        mandatory
                        color="primary"
                        class="materials-v2-admin-panel-switcher"
                        :disabled="administrationLocked"
                        @update:model-value="openAdministration">
                        <v-btn
                            v-for="panel in administrationPanels"
                            :key="panel.id"
                            :value="panel.id"
                            :prepend-icon="panel.icon"
                            :aria-pressed="selectedAdministrationPanel === panel.id"
                            class="materials-v2-admin-panel-button">
                            <span class="materials-v2-admin-panel-copy">
                                <span>{{ panel.label }}</span>
                                <span class="materials-v2-admin-panel-meta">{{ activeSchoolyearLabel }}</span>
                            </span>
                        </v-btn>
                    </v-btn-toggle>
                </nav>
                <div v-if="selectedAdministrationPanel === 'material_settings'" class="materials-v2-admin-settings">
                    <MaterialsSettingsView @menu-lock-change="administrationLocked = $event" />
                </div>
                <Groups v-else :embedded="true" embedded-filter="materials" />
            </section>

            <div v-else class="materials-v2-workspace">
                <aside class="materials-v2-category-panel">
                    <div class="materials-v2-category-panel-heading">
                        <span>{{ isClusterView ? 'Clusters' : 'Eigene Kategorien' }}</span>
                        <v-btn
                            v-if="!isClusterView"
                            class="materials-v2-category-create-button"
                            color="primary"
                            icon="mdi-plus"
                            size="small"
                            variant="text"
                            title="Kategorie hinzufügen"
                            aria-label="Kategorie hinzufügen"
                            @click="openCategoryDialog()" />
                    </div>

                    <v-list
                        v-if="isClusterView"
                        bg-color="transparent"
                        density="compact"
                        class="materials-v2-category-list py-0"
                        aria-label="Materialien nach Cluster filtern">
                        <v-list-item
                            v-for="cluster in clusterDetails"
                            :key="cluster.id"
                            class="materials-v2-category-item mb-1 px-2"
                            min-height="42"
                            rounded="lg"
                            :active="selectedClusterId === cluster.id"
                            color="primary"
                            @click="selectCluster(cluster.id)">
                            <template #prepend>
                                <v-icon size="18" class="mr-2">mdi-folder-outline</v-icon>
                            </template>
                            <v-list-item-title class="text-body-2">
                                {{ cluster.name }}
                            </v-list-item-title>
                            <template #append>
                                <span
                                    class="materials-v2-category-item-count"
                                    :title="`${cluster.items_count} Items`">
                                    {{ cluster.items_count }}
                                </span>
                            </template>
                        </v-list-item>
                        <p v-if="!clusterDetails.length" class="materials-v2-cluster-list-empty">
                            Noch keine Cluster vorhanden.
                        </p>
                    </v-list>

                    <v-list
                        v-else
                        bg-color="transparent"
                        density="compact"
                        class="materials-v2-category-list py-0"
                        aria-label="Materialien nach eigener Kategorie filtern">
                        <v-list-item
                            v-for="category in customCategoryDetails"
                            :key="category.name"
                            class="materials-v2-category-item mb-1 px-2"
                            min-height="42"
                            rounded="lg"
                            :active="selectedCategory === category.name"
                            color="primary"
                            @click="selectCategory(category.name)">
                            <template #prepend>
                                <v-icon size="18" class="mr-2">mdi-shape-outline</v-icon>
                            </template>
                            <v-list-item-title class="text-body-2">
                                {{ category.name }}
                            </v-list-item-title>
                            <template #append>
                                <div class="materials-v2-category-item-actions">
                                    <span
                                        class="materials-v2-category-item-count"
                                        :title="`${category.items_count} Items`">
                                        {{ category.items_count }}
                                    </span>
                                    <v-btn
                                        class="materials-v2-category-edit-button"
                                        icon="mdi-pencil-outline"
                                        size="x-small"
                                        variant="text"
                                        title="Kategorie bearbeiten"
                                        @click.stop="openCategoryDialog(category.name)" />
                                    <v-btn
                                        v-if="category.items_count === 0"
                                        class="materials-v2-category-delete-button"
                                        icon="mdi-delete-outline"
                                        size="x-small"
                                        variant="text"
                                        color="error"
                                        title="Kategorie löschen"
                                        @click.stop="openCategoryDeleteDialog(category.name)" />
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                </aside>

                <main class="materials-v2-main">
                    <div class="materials-v2-toolbar">
                        <div class="materials-v2-result-heading">
                            <span>{{ selectedCategoryLabel }}</span>
                            <span v-if="!loading">
                                · {{ meta.total }} {{ meta.total === 1 ? 'Material' : 'Materialien' }}
                            </span>
                        </div>

                        <div class="materials-v2-toolbar-actions">
                            <v-btn
                                v-if="!isReminderCategory(selectedCategory) && !isClusterView"
                                class="materials-v2-material-create-button"
                                color="primary"
                                size="small"
                                variant="tonal"
                                :prepend-icon="createActionIcon(selectedCategory)"
                                @click="openCreateDialog">
                                {{ createActionLabel(selectedCategory, 'Material erstellen') }}
                            </v-btn>

                            <v-btn-toggle
                                v-if="isCustomCategorySelected"
                                v-model="displayMode"
                                class="materials-v2-display-toggle"
                                color="primary"
                                variant="outlined"
                                density="compact"
                                rounded="lg"
                                divided
                                mandatory
                                aria-label="Darstellungsgröße der Materialien">
                                <v-btn value="large" size="small">Groß</v-btn>
                                <v-btn value="standard" size="small">Standard</v-btn>
                                <v-btn value="compact" size="small">Kompakt</v-btn>
                            </v-btn-toggle>

                            <v-btn-toggle
                                v-else-if="isReminderCategorySelected"
                                v-model="reminderViewSelection"
                                class="materials-v2-display-toggle materials-v2-reminder-display-toggle"
                                color="primary"
                                variant="outlined"
                                density="compact"
                                rounded="lg"
                                divided
                                mandatory
                                aria-label="Darstellung der Termine">
                                <v-btn value="standard" size="small">Standard</v-btn>
                                <v-btn value="month" size="small">Monat</v-btn>
                                <v-btn value="week" size="small">Woche</v-btn>
                            </v-btn-toggle>

                            <v-chip
                                v-if="hasProcessingItems"
                                color="info"
                                variant="tonal"
                                prepend-icon="mdi-progress-clock">
                                Inhalte werden analysiert
                            </v-chip>
                        </div>
                    </div>

            <v-alert
                v-if="loadError"
                class="mb-5"
                type="error"
                variant="tonal"
                rounded="xl"
                closable
                @click:close="loadError = ''">
                {{ loadError }}
            </v-alert>

            <MaterialsV2Calendar
                v-if="isReminderCalendarView"
                :loading="loading"
                :period-label="calendarPeriodLabel"
                :previous-period-label="previousCalendarPeriodLabel"
                :next-period-label="nextCalendarPeriodLabel"
                :adjacent-reminder-loading="adjacentReminderLoading"
                :weekdays="calendarWeekdays"
                :display-mode="calendarDisplayMode"
                :days="calendarDays"
                @move="moveCalendar"
                @today="showToday"
                @jump-adjacent="jumpToAdjacentReminder"
                @edit="openEditDialog" />

            <v-row v-else-if="loading && !items.length" dense>
                <v-col v-for="index in 6" :key="index" v-bind="materialColumnProps">
                    <v-skeleton-loader
                        :class="['materials-v2-card', `materials-v2-card--${activeCardDisplayMode}`]"
                        type="article, actions" />
                </v-col>
            </v-row>

            <v-row v-else-if="items.length" dense>
                <v-col v-for="item in items" :key="item.id" v-bind="materialColumnProps">
                    <v-card
                        :class="[
                            'materials-v2-card',
                            `materials-v2-card--${activeCardDisplayMode}`,
                            { 'materials-v2-card--reminder': isReminderCategory(item.category) },
                            { 'materials-v2-card--screenshot': isScreenshotCategory(item.category) },
                            { 'materials-v2-card--link': isLinkCategory(item.category) },
                            { 'materials-v2-card--file': isFileCategory(item.category) },
                            { 'materials-v2-card--note': isNoteCategory(item.category) },
                            'h-100',
                        ]"
                        :rounded="activeCardDisplayMode === 'large' ? 'xl' : 'lg'"
                        elevation="0"
                        role="button"
                        tabindex="0"
                        :aria-label="`Details zu ${item.title} öffnen`"
                        @click="openMaterialCard(item, $event)"
                        @keydown.enter="openMaterialCard(item, $event)"
                        @keydown.space.prevent="openMaterialCard(item, $event)">
                        <v-card-text :class="materialCardPaddingClass">
                            <div class="d-flex align-start justify-space-between ga-3">
                                <div class="min-width-0">
                                    <div class="d-flex align-center flex-wrap ga-2 mb-2">
                                        <v-chip
                                            v-if="search.trim() && item.search_score"
                                            size="x-small"
                                            color="primary"
                                            variant="flat">
                                            {{ Math.round(item.search_score) }} Punkte
                                        </v-chip>
                                        <v-chip
                                            v-if="supportsDocumentProcessing(item.category)"
                                            size="x-small"
                                            :color="statusMeta(item.processing_status).color"
                                            variant="tonal"
                                            :prepend-icon="statusMeta(item.processing_status).icon">
                                            {{ statusMeta(item.processing_status).label }}
                                        </v-chip>
                                    </div>

                                    <div class="d-flex align-center flex-wrap ga-2">
                                        <h2 class="materials-v2-card-title">{{ item.title }}</h2>
                                        <v-chip
                                            v-if="item.category"
                                            size="x-small"
                                            color="secondary"
                                            variant="tonal"
                                            :prepend-icon="categoryIcon(item.category)">
                                            {{ item.category }}
                                        </v-chip>
                                        <v-chip
                                            v-if="item.cluster"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal"
                                            prepend-icon="mdi-folder-outline">
                                            {{ item.cluster.name }}
                                        </v-chip>
                                    </div>
                                </div>

                                <v-menu>
                                    <template #activator="{ props }">
                                        <v-btn
                                            v-bind="props"
                                            icon="mdi-dots-horizontal"
                                            size="small"
                                            variant="text"
                                            @click.stop />
                                    </template>
                                    <v-list density="compact">
                                        <v-list-item
                                            prepend-icon="mdi-pencil-outline"
                                            title="Bearbeiten"
                                            @click.stop="openEditDialog(item)" />
                                        <v-list-item
                                            v-if="supportsDocumentProcessing(item.category)"
                                            prepend-icon="mdi-paperclip-plus"
                                            title="Anlagen hinzufügen"
                                            @click.stop="openAttachmentDialog(item)" />
                                        <v-list-item
                                            v-if="supportsDocumentProcessing(item.category)"
                                            prepend-icon="mdi-refresh"
                                            title="Automatische Tags neu berechnen"
                                            @click.stop="recalculateAutomaticTags(item)" />
                                        <v-list-item
                                            v-if="
                                                supportsDocumentProcessing(item.category)
                                                && ['failed', 'partial'].includes(item.processing_status)
                                            "
                                            prepend-icon="mdi-file-refresh-outline"
                                            title="Dateiverarbeitung wiederholen"
                                            @click.stop="retryProcessing(item)" />
                                        <v-divider />
                                        <v-list-item
                                            prepend-icon="mdi-delete-outline"
                                            title="Löschen"
                                            base-color="error"
                                            @click.stop="openDeleteDialog(item)" />
                                    </v-list>
                                </v-menu>
                            </div>

                            <button
                                v-if="isScreenshotCategory(item.category) && item.attachments?.[0]?.preview_url"
                                type="button"
                                class="materials-v2-screenshot-thumbnail-button"
                                :aria-label="`${item.title} in der Vorschau öffnen`"
                                @click="previewAttachment(item.attachments[0])">
                                <span class="materials-v2-screenshot-frame-bar" aria-hidden="true">
                                    <i />
                                    <i />
                                    <i />
                                    <span>Screenshot</span>
                                </span>
                                <img
                                    class="materials-v2-screenshot-thumbnail"
                                    :src="item.attachments[0].preview_url"
                                    :alt="`Screenshot: ${item.title}`"
                                    loading="lazy" />
                            </button>

                            <div
                                v-if="isReminderCategory(item.category) && item.reminder_date"
                                class="materials-v2-reminder-date">
                                <div class="materials-v2-reminder-calendar-sheet" aria-hidden="true">
                                    <span>{{ reminderMonthLabel(item.reminder_date) }}</span>
                                    <strong>{{ reminderDayLabel(item.reminder_date) }}</strong>
                                </div>
                                <div class="materials-v2-reminder-date-copy">
                                    <strong>{{ formatReminderDate(item.reminder_date) }}</strong>
                                    <span v-if="item.reminder_time">{{ item.reminder_time }} Uhr</span>
                                    <span v-else>Ganztägig</span>
                                </div>
                                <v-chip
                                    v-if="reminderBadge(item.reminder_date)"
                                    size="x-small"
                                    :color="reminderBadge(item.reminder_date).color"
                                    variant="tonal">
                                    {{ reminderBadge(item.reminder_date).label }}
                                </v-chip>
                            </div>

                            <a
                                v-if="isLinkCategory(item.category) && isHttpUrl(item.link_url)"
                                class="materials-v2-link-target"
                                :href="normalizedHttpUrl(item.link_url)"
                                target="_blank"
                                rel="noopener noreferrer"
                                :title="item.link_url">
                                <span class="materials-v2-link-icon" aria-hidden="true">
                                    <v-icon size="22">mdi-web</v-icon>
                                </span>
                                <span class="materials-v2-link-copy">
                                    <small>Web-Link</small>
                                    <strong>{{ linkDisplay(item.link_url) }}</strong>
                                </span>
                                <v-icon class="materials-v2-link-open-icon" size="18">mdi-arrow-top-right</v-icon>
                            </a>

                            <div
                                v-if="isNoteCategory(item.category) && activeCardDisplayMode !== 'compact'"
                                class="materials-v2-note-sheet">
                                <div class="materials-v2-note-sheet-label">
                                    <v-icon size="16">mdi-note-text-outline</v-icon>
                                    Notiz
                                </div>
                                <p>{{ item.description || 'Leere Notiz' }}</p>
                            </div>
                            <p
                                v-else-if="activeCardDisplayMode !== 'compact' && item.description"
                                class="materials-v2-description">
                                {{ item.description }}
                            </p>
                            <p
                                v-else-if="activeCardDisplayMode === 'large'"
                                class="materials-v2-description materials-v2-description--empty">
                                {{ isReminderCategory(item.category) || isLinkCategory(item.category) ? 'Keine Notiz' : 'Keine Beschreibung' }}
                            </p>

                            <v-alert
                                v-if="supportsDocumentProcessing(item.category) && item.processing_error"
                                class="mb-4"
                                density="compact"
                                type="warning"
                                variant="tonal">
                                {{ item.processing_error }}
                            </v-alert>

                            <div
                                v-if="
                                    supportsDocumentProcessing(item.category)
                                    && activeCardDisplayMode !== 'compact'
                                    && item.user_keywords?.length
                                "
                                class="materials-v2-keyword-block">
                                <div class="d-flex flex-wrap ga-1">
                                    <v-chip
                                        v-for="keyword in visibleUserKeywords(item)"
                                        :key="`user-${item.id}-${keyword}`"
                                        size="small"
                                        color="secondary"
                                        variant="outlined">
                                        {{ keyword }}
                                    </v-chip>
                                    <v-chip
                                        v-if="activeCardDisplayMode === 'standard' && item.user_keywords.length > 4"
                                        size="small"
                                        color="secondary"
                                        variant="text">
                                        +{{ item.user_keywords.length - 4 }}
                                    </v-chip>
                                </div>
                            </div>

                            <div
                                v-if="
                                    !isReminderCategory(item.category)
                                    && !isLinkCategory(item.category)
                                    && !isNoteCategory(item.category)
                                "
                                :class="[
                                    'materials-v2-attachments',
                                    { 'materials-v2-file-browser': isFileCategory(item.category) },
                                ]">
                                <div class="materials-v2-attachment-heading">
                                    <v-icon size="18">
                                        {{ isFileCategory(item.category) ? 'mdi-folder-open-outline' : 'mdi-paperclip' }}
                                    </v-icon>
                                    {{ item.attachments?.length || 0 }}
                                    {{
                                        isFileCategory(item.category)
                                            ? item.attachments?.length === 1 ? 'Datei' : 'Dateien'
                                            : item.attachments?.length === 1 ? 'Anlage' : 'Anlagen'
                                    }}
                                </div>

                                <div
                                    v-if="
                                        (activeCardDisplayMode === 'large' || isFileCategory(item.category))
                                        && item.attachments?.length
                                    "
                                    class="d-flex flex-column ga-2 mt-2">
                                    <div
                                        v-for="attachment in item.attachments"
                                        :key="attachment.id"
                                        class="materials-v2-attachment-row">
                                        <span class="materials-v2-attachment-icon" aria-hidden="true">
                                            <v-icon size="22" color="primary">{{ attachmentIcon(attachment) }}</v-icon>
                                        </span>
                                        <button type="button" class="materials-v2-attachment-name" @click="previewAttachment(attachment)">
                                            {{ attachment.original_name }}
                                        </button>
                                        <span v-if="isFileCategory(item.category)" class="materials-v2-file-kind">
                                            {{ fileExtension(attachment.original_name) }}
                                        </span>
                                        <span class="materials-v2-file-size">{{ formatFileSize(attachment.size_bytes) }}</span>
                                        <v-btn
                                            icon="mdi-eye-outline"
                                            size="x-small"
                                            variant="text"
                                            title="Vorschau"
                                            :aria-label="`${attachment.original_name} in der Vorschau öffnen`"
                                            @click="previewAttachment(attachment)" />
                                        <v-btn
                                            :href="attachment.download_url"
                                            target="_blank"
                                            icon="mdi-download"
                                            size="x-small"
                                            variant="text"
                                            title="Herunterladen" />
                                        <v-btn
                                            icon="mdi-close"
                                            size="x-small"
                                            variant="text"
                                            color="error"
                                            title="Anlage entfernen"
                                            @click="removeAttachment(item, attachment)" />
                                    </div>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-card v-else class="materials-v2-empty" rounded="xl" elevation="0">
                <v-icon size="54" color="primary">
                    {{
                        isClusterView
                            ? 'mdi-folder-open-outline'
                            : hasActiveFilters
                              ? 'mdi-file-search-outline'
                              : 'mdi-folder-plus-outline'
                    }}
                </v-icon>
                <h2>
                    {{
                        isClusterView
                            ? (selectedCluster ? 'Dieser Cluster enthält noch keine Items' : 'Noch keine Cluster vorhanden')
                            : hasActiveFilters
                              ? 'Noch kein passender Treffer'
                              : 'Deine neue Materialsammlung ist leer'
                    }}
                </h2>
                <p>
                    {{
                        isClusterView
                            ? (
                                selectedCluster
                                    ? 'Weise einem Termin, Screenshot, Link oder einer Notiz diesen Cluster zu.'
                                    : 'Cluster entstehen, sobald du sie einem Termin, Screenshot, Link oder einer Notiz zuweist.'
                            )
                            : hasActiveFilters
                              ? 'Versuche andere Begriffe oder nur einen Wortteil.'
                              : 'Erstelle dein erstes Material – mit oder ohne Kategorie.'
                    }}
                </p>
                <v-btn
                    v-if="!isClusterView && (!hasActiveFilters || isDefaultCategory(selectedCategory))"
                    color="primary"
                    rounded="xl"
                    :prepend-icon="createActionIcon(selectedCategory)"
                    @click="openCreateDialog">
                    {{ createActionLabel(selectedCategory, 'Erstes Material erstellen') }}
                </v-btn>
            </v-card>

            <div v-if="!isReminderCalendarView && meta.last_page > 1" class="d-flex justify-center mt-7">
                <v-pagination
                    v-model="page"
                    :length="meta.last_page"
                    :total-visible="7"
                    rounded="circle"
                    @update:model-value="loadItems" />
            </div>
                </main>
            </div>
        </div>

        <v-dialog v-model="materialDialog.open" persistent max-width="720">
            <v-card rounded="xl" @paste="handleSpecializedPaste">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="primary" class="mr-2">
                        {{ materialDialogIcon }}
                    </v-icon>
                    {{ materialDialogTitle }}
                    <v-spacer />
                    <v-btn
                        v-if="materialDialog.mode === 'edit'"
                        class="materials-v2-material-dialog-close"
                        icon="mdi-close"
                        size="small"
                        variant="text"
                        title="Schließen"
                        aria-label="Material bearbeiten schließen"
                        :disabled="materialDialog.saving"
                        @click="closeMaterialDialog" />
                </v-card-title>
                <v-card-text class="pa-6 pt-3">
                    <v-row dense>
                        <v-col cols="12" md="7">
                            <v-text-field
                                v-model="materialForm.title"
                                label="Titel"
                                variant="outlined"
                                maxlength="255"
                                counter
                                autofocus
                                :error-messages="materialErrorMessages('title')"
                                :disabled="materialDialog.saving"
                                @blur="validateMaterialField('title')" />
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-combobox
                                v-if="!isDefaultForm"
                                v-model="materialForm.category"
                                class="materials-v2-category-chooser"
                                label="Kategorie (optional)"
                                variant="outlined"
                                maxlength="255"
                                clearable
                                :items="categoryOptions"
                                :return-object="false"
                                :error-messages="materialErrorMessages('category')"
                                :disabled="materialDialog.saving"
                                hint="Bestehende Kategorie wählen oder eine neue eingeben"
                                persistent-hint
                                @blur="validateMaterialField('category')" />
                            <v-text-field
                                v-else
                                class="materials-v2-fixed-category"
                                :model-value="materialForm.category"
                                label="Kategorie"
                                variant="outlined"
                                :prepend-inner-icon="categoryIcon(materialForm.category)"
                                readonly
                                hide-details />
                        </v-col>
                    </v-row>
                    <v-combobox
                        v-if="isClusterableForm"
                        v-model="materialForm.clusterName"
                        class="materials-v2-cluster-chooser mb-3"
                        label="Cluster (optional)"
                        variant="outlined"
                        maxlength="255"
                        clearable
                        always-filter
                        :items="clusterOptions"
                        :return-object="false"
                        :custom-filter="clusterSearchFilter"
                        :error-messages="materialErrorMessages('cluster_name')"
                        :disabled="materialDialog.saving"
                        prepend-inner-icon="mdi-folder-search-outline"
                        no-data-text="Kein ähnlicher Cluster gefunden"
                        :hide-no-data="false"
                        hint="Tippen, um Cluster auch über Wortteile oder kleine Tippfehler zu finden"
                        persistent-hint
                        @blur="validateMaterialField('cluster_name')" />
                    <v-row v-if="isReminderForm" dense class="materials-v2-reminder-fields">
                        <v-col cols="12" sm="7">
                            <v-text-field
                                v-model="materialForm.reminderDate"
                                type="date"
                                label="Datum"
                                variant="outlined"
                                prepend-inner-icon="mdi-calendar-blank-outline"
                                :error-messages="materialErrorMessages('reminder_date')"
                                :disabled="materialDialog.saving"
                                @blur="validateMaterialField('reminder_date')" />
                        </v-col>
                        <v-col cols="12" sm="5">
                            <v-text-field
                                v-model="materialForm.reminderTime"
                                type="time"
                                label="Uhrzeit (optional)"
                                variant="outlined"
                                clearable
                                prepend-inner-icon="mdi-clock-outline"
                                :error-messages="materialErrorMessages('reminder_time')"
                                :disabled="materialDialog.saving"
                                @blur="validateMaterialField('reminder_time')" />
                        </v-col>
                    </v-row>
                    <section
                        v-if="isScreenshotForm && materialDialog.mode === 'create'"
                        class="materials-v2-screenshot-input">
                        <div
                            class="materials-v2-screenshot-paste-zone"
                            tabindex="0"
                            role="button"
                            aria-label="Screenshot aus der Zwischenablage einfügen"
                            @click="focusScreenshotPasteZone">
                            <img
                                v-if="screenshotPreviewUrl"
                                class="materials-v2-screenshot-preview"
                                :src="screenshotPreviewUrl"
                                alt="Vorschau des ausgewählten Screenshots" />
                            <template v-else>
                                <v-icon size="42" color="primary">mdi-content-paste</v-icon>
                                <strong>Screenshot mit Strg+V einfügen</strong>
                                <span>Klicke hier und füge das Bild aus der Zwischenablage ein.</span>
                            </template>
                        </div>
                        <v-file-input
                            :model-value="materialForm.attachments"
                            class="mt-3"
                            label="Oder Bild auswählen"
                            variant="outlined"
                            accept="image/png,image/jpeg,image/gif,image/webp"
                            chips
                            show-size
                            prepend-icon=""
                            prepend-inner-icon="mdi-image-plus-outline"
                            :error-messages="materialErrorMessages('attachments')"
                            :disabled="materialDialog.saving"
                            @update:model-value="setScreenshotFiles" />
                    </section>
                    <section v-if="isLinkForm" class="materials-v2-link-input">
                        <div
                            v-if="materialDialog.mode === 'create'"
                            class="materials-v2-link-paste-zone"
                            tabindex="0"
                            role="button"
                            aria-label="Link aus der Zwischenablage einfügen"
                            @click="focusLinkPasteZone">
                            <v-icon size="36" color="primary">
                                {{ materialForm.linkUrl ? 'mdi-link-check' : 'mdi-content-paste' }}
                            </v-icon>
                            <strong v-if="materialForm.linkUrl">{{ linkDisplay(materialForm.linkUrl) }}</strong>
                            <strong v-else>Link mit Strg+V einfügen</strong>
                            <span>
                                {{
                                    materialForm.linkUrl
                                        ? 'Der Link wurde aus der Zwischenablage übernommen.'
                                        : 'Klicke hier und füge eine Webadresse aus der Zwischenablage ein.'
                                }}
                            </span>
                        </div>
                        <v-text-field
                            v-model="materialForm.linkUrl"
                            class="mt-3"
                            type="url"
                            label="Webadresse"
                            placeholder="https://example.com"
                            variant="outlined"
                            clearable
                            prepend-inner-icon="mdi-link-variant"
                            :error-messages="materialErrorMessages('link_url')"
                            :loading="linkPreview.state === 'checking'"
                            :disabled="materialDialog.saving"
                            @blur="validateMaterialField('link_url'); inspectLinkUrl({ force: true })" />
                        <v-alert
                            v-if="linkPreview.state !== 'idle'"
                            class="materials-v2-link-status"
                            :type="linkPreviewAlertType"
                            :title="linkPreviewAlertTitle"
                            :text="linkPreview.message"
                            density="compact"
                            variant="tonal" />
                        <a
                            v-if="isHttpUrl(materialForm.linkUrl)"
                            class="materials-v2-link-preview"
                            :href="normalizedHttpUrl(materialForm.linkUrl)"
                            target="_blank"
                            rel="noopener noreferrer">
                            Link in neuem Tab testen
                            <v-icon size="16">mdi-open-in-new</v-icon>
                        </a>
                    </section>
                    <v-file-input
                        v-if="isFileForm && materialDialog.mode === 'create'"
                        v-model="materialForm.attachments"
                        class="materials-v2-file-input mt-3"
                        label="Dateien auswählen"
                        variant="outlined"
                        multiple
                        chips
                        :show-size="1024"
                        counter
                        prepend-icon=""
                        prepend-inner-icon="mdi-file-multiple-outline"
                        :accept="fileInputAccept"
                        :hint="`Bis zu 10 Dateien · maximal ${maxUploadSizeLabel} je Datei`"
                        persistent-hint
                        :error-messages="materialErrorMessages('attachments')"
                        :disabled="materialDialog.saving" />
                    <v-textarea
                        v-model="materialForm.description"
                        :class="['mt-2', { 'materials-v2-note-body': isNoteForm }]"
                        :label="
                            isNoteForm
                                ? 'Notiz'
                                : isReminderForm || isLinkForm
                                  ? 'Notiz (optional)'
                                  : 'Beschreibung (optional)'
                        "
                        variant="outlined"
                        rows="3"
                        auto-grow
                        maxlength="10000"
                        :error-messages="materialErrorMessages('description')"
                        :disabled="materialDialog.saving"
                        @blur="validateMaterialField('description')" />
                    <v-text-field
                        v-if="!isDefaultForm || isFileForm"
                        v-model="materialForm.keywords"
                        class="materials-v2-keywords-field mt-2"
                        label="Eigene Suchwörter (optional)"
                        hint="Mit Komma trennen, z. B. Bruchrechnen, Übung, 2. Klasse"
                        persistent-hint
                        variant="outlined"
                        prepend-inner-icon="mdi-tag-multiple-outline"
                        :error-messages="materialErrorMessages('user_keywords')"
                        :disabled="materialDialog.saving"
                        @blur="validateMaterialField('user_keywords')" />
                    <section
                        v-if="materialDialog.mode === 'edit' && !isReminderForm && !isLinkForm && !isNoteForm"
                        class="materials-v2-edit-attachments">
                        <div class="materials-v2-edit-attachments-heading">
                            <div class="materials-v2-attachment-heading">
                                <v-icon size="18">mdi-paperclip</v-icon>
                                Anlagen
                                <span>({{ materialDialog.item?.attachments?.length || 0 }})</span>
                            </div>
                            <v-btn
                                v-if="!isScreenshotForm"
                                icon="mdi-paperclip-plus"
                                size="x-small"
                                variant="plain"
                                title="Anlagen hinzufügen"
                                aria-label="Anlagen hinzufügen"
                                @click="openAttachmentDialog(materialDialog.item)" />
                        </div>

                        <div
                            v-if="materialDialog.item?.attachments?.length"
                            class="materials-v2-edit-attachment-list">
                            <div
                                v-for="attachment in materialDialog.item.attachments"
                                :key="`edit-attachment-${attachment.id}`"
                                class="materials-v2-attachment-row">
                                <v-icon size="20" color="primary">{{ attachmentIcon(attachment) }}</v-icon>
                                <button
                                    type="button"
                                    class="materials-v2-attachment-name materials-v2-edit-attachment-name"
                                    @click="previewAttachment(attachment)">
                                    {{ attachment.original_name }}
                                </button>
                                <span class="materials-v2-file-size">{{ formatFileSize(attachment.size_bytes) }}</span>
                                <v-btn
                                    icon="mdi-eye-outline"
                                    size="x-small"
                                    variant="text"
                                    title="Vorschau"
                                    :aria-label="`${attachment.original_name} in der Vorschau öffnen`"
                                    @click="previewAttachment(attachment)" />
                                <v-btn
                                    :href="attachment.download_url"
                                    target="_blank"
                                    icon="mdi-download"
                                    size="x-small"
                                    variant="text"
                                    title="Herunterladen" />
                                <v-btn
                                    icon="mdi-close"
                                    size="x-small"
                                    variant="text"
                                    color="error"
                                    title="Anlage entfernen"
                                    @click="removeAttachment(materialDialog.item, attachment)" />
                            </div>
                        </div>
                        <p v-else class="materials-v2-edit-attachments-empty">Keine Anlagen vorhanden.</p>
                    </section>
                    <section
                        v-if="materialDialog.mode === 'edit' && (!isDefaultForm || isFileForm)"
                        class="materials-v2-automatic-tag-review mt-3">
                        <div class="materials-v2-automatic-tag-heading">
                            <div class="materials-v2-automatic-tag-summary">
                                <span class="materials-v2-automatic-tag-label">Automatisch erkannte Tags:</span>
                                <span class="materials-v2-automatic-tag-names">{{ automaticTagNames || 'Keine' }}</span>
                            </div>
                            <v-btn
                                v-if="!isAutomaticTagEditing"
                                class="materials-v2-automatic-tag-edit"
                                icon="mdi-pencil-outline"
                                size="x-small"
                                variant="plain"
                                title="Automatische Tags bearbeiten"
                                aria-label="Automatische Tags bearbeiten"
                                @click="isAutomaticTagEditing = true" />
                            <v-btn
                                v-else
                                class="materials-v2-automatic-tag-edit"
                                icon="mdi-check"
                                size="x-small"
                                variant="plain"
                                title="Bearbeitung beenden"
                                aria-label="Bearbeitung beenden"
                                @click="isAutomaticTagEditing = false" />
                        </div>

                        <div v-if="isAutomaticTagEditing" class="materials-v2-automatic-tag-editor">
                            <div class="materials-v2-automatic-tag-editor-heading">
                                <span>Tag-Verwaltung</span>
                                <v-btn
                                    icon="mdi-refresh"
                                    size="x-small"
                                    variant="plain"
                                    title="Automatische Tags neu berechnen"
                                    aria-label="Automatische Tags neu berechnen"
                                    :loading="tagAction.type === 'recalculate'"
                                    @click="recalculateAutomaticTags(materialDialog.item)" />
                            </div>

                            <div
                                v-if="materialDialog.item?.automatic_tag_suggestions?.length"
                                class="materials-v2-automatic-tag-list">
                                <div
                                    v-for="suggestion in materialDialog.item.automatic_tag_suggestions"
                                    :key="`review-${suggestion.attachment_id}-${suggestion.name}`"
                                    class="materials-v2-automatic-tag-row">
                                    <v-chip color="primary" variant="tonal" size="x-small">
                                        {{ suggestion.rank }}. {{ suggestion.name }}
                                    </v-chip>
                                    <span class="materials-v2-automatic-tag-meta">
                                        {{ suggestion.score }} Punkte · {{ suggestion.language.toUpperCase() }}
                                    </span>
                                    <v-btn
                                        icon="mdi-tag-plus-outline"
                                        size="x-small"
                                        variant="plain"
                                        color="secondary"
                                        title="Als eigenes Suchwort übernehmen"
                                        :loading="isTagAction('convert', suggestion.name)"
                                        @click="convertAutomaticTag(materialDialog.item, suggestion)" />
                                    <v-btn
                                        icon="mdi-close"
                                        size="x-small"
                                        variant="plain"
                                        color="error"
                                        title="Automatischen Tag entfernen"
                                        :loading="isTagAction('remove', suggestion.name)"
                                        @click="removeAutomaticTag(materialDialog.item, suggestion)" />
                                </div>
                            </div>
                            <p v-else class="materials-v2-automatic-tag-empty">
                                Für dieses Material wurden keine ausreichend relevanten automatischen Tags gefunden.
                            </p>

                            <div
                                v-if="materialDialog.item?.attachments?.length"
                                class="materials-v2-tag-status-list">
                                <div
                                    v-for="attachment in materialDialog.item.attachments"
                                    :key="`tag-status-${attachment.id}`"
                                    class="materials-v2-tag-status-row">
                                    <span class="materials-v2-tag-status-name">{{ attachment.original_name }}</span>
                                    <v-chip
                                        size="x-small"
                                        :color="keywordStatusMeta(attachment.keyword_extraction_status).color"
                                        variant="tonal">
                                        {{ keywordStatusMeta(attachment.keyword_extraction_status).label }}
                                    </v-chip>
                                    <span
                                        v-if="attachment.keywords_extracted_at"
                                        class="text-caption text-medium-emphasis">
                                        {{ formatDateTime(attachment.keywords_extracted_at) }}
                                    </span>
                                    <div
                                        v-if="attachment.keyword_extraction_error"
                                        class="text-caption text-error flex-1-1-100">
                                        {{ attachment.keyword_extraction_error }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <v-file-input
                        v-if="materialDialog.mode === 'create' && !isDefaultForm"
                        v-model="materialForm.attachments"
                        class="mt-5"
                        label="Anlagen (optional)"
                        variant="outlined"
                        multiple
                        chips
                        show-size
                        counter
                        prepend-icon=""
                        prepend-inner-icon="mdi-paperclip"
                        :error-messages="formErrors.attachments"
                        :disabled="materialDialog.saving" />
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="materialDialog.saving" @click="closeMaterialDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        rounded="lg"
                        :loading="materialDialog.saving"
                        :disabled="
                            !materialForm.title.trim()
                            || (isReminderForm && !materialForm.reminderDate)
                            || (isScreenshotForm && materialDialog.mode === 'create' && !screenshotFile)
                            || (isFileForm && materialDialog.mode === 'create' && !materialFiles.length)
                            || (isLinkForm && !materialForm.linkUrl.trim())
                            || (isLinkForm && linkPreview.state === 'checking')
                            || (isNoteForm && !materialForm.description.trim())
                        "
                        @click="saveMaterial()">
                        {{ materialDialog.mode === 'create' ? (isDefaultForm ? 'Hinzufügen' : 'Erstellen') : 'Speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog
            v-model="categoryDialog.open"
            class="materials-v2-category-dialog"
            persistent
            max-width="520">
            <v-card rounded="xl">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="primary" class="mr-2">mdi-shape-plus-outline</v-icon>
                    {{ categoryDialog.mode === 'create' ? 'Kategorie erstellen' : 'Kategorie bearbeiten' }}
                </v-card-title>
                <v-card-text class="px-6 pb-2">
                    <v-text-field
                        v-model="categoryDialog.name"
                        label="Name"
                        variant="outlined"
                        maxlength="255"
                        counter
                        autofocus
                        :error-messages="categoryDialog.error"
                        :disabled="categoryDialog.saving"
                        @update:model-value="categoryDialog.warning = ''"
                        @keydown.enter.prevent="saveCategory" />
                    <v-alert
                        v-if="categoryDialog.warning"
                        class="materials-v2-category-warning mt-3"
                        type="warning"
                        variant="tonal"
                        density="compact">
                        {{ categoryDialog.warning }}
                    </v-alert>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        :disabled="categoryDialog.saving"
                        @click="closeCategoryDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="primary"
                        rounded="lg"
                        :loading="categoryDialog.saving"
                        :disabled="!categoryDialog.name.trim()"
                        @click="saveCategory">
                        {{ categoryDialog.mode === 'create' ? 'Erstellen' : 'Speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="categoryDeleteDialog.open" persistent max-width="500">
            <v-card rounded="xl">
                <v-card-title>Kategorie löschen?</v-card-title>
                <v-card-text>
                    Die leere Kategorie <strong>{{ categoryDeleteDialog.name }}</strong> wird dauerhaft gelöscht.
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        :disabled="categoryDeleteDialog.saving"
                        @click="closeCategoryDeleteDialog">
                        Abbrechen
                    </v-btn>
                    <v-btn
                        color="error"
                        :loading="categoryDeleteDialog.saving"
                        @click="deleteCategory">
                        Löschen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="categorySuggestionDialog.open" persistent max-width="560">
            <v-card rounded="xl">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="warning" class="mr-2">mdi-spellcheck</v-icon>
                    Ähnliche Kategorie gefunden
                </v-card-title>
                <v-card-text class="px-6 pb-2">
                    Du hast <strong>„{{ categorySuggestionDialog.entered }}“</strong> eingegeben.
                    Es gibt bereits die ähnliche Kategorie
                    <strong>„{{ categorySuggestionDialog.existing }}“</strong>.
                    Welche möchtest du verwenden?
                </v-card-text>
                <v-card-actions class="px-6 pb-5 flex-wrap ga-2">
                    <v-btn variant="text" @click="categorySuggestionDialog.open = false">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn variant="tonal" color="secondary" @click="keepNewCategory">
                        Neue Kategorie anlegen
                    </v-btn>
                    <v-btn color="primary" @click="useSuggestedCategory">
                        Bestehende übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="clusterSuggestionDialog.open" persistent max-width="560">
            <v-card rounded="xl">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="warning" class="mr-2">mdi-folder-search-outline</v-icon>
                    Ähnlicher Cluster gefunden
                </v-card-title>
                <v-card-text class="px-6 pb-2">
                    Du hast <strong>„{{ clusterSuggestionDialog.entered }}“</strong> eingegeben.
                    Es gibt bereits den ähnlichen Cluster
                    <strong>„{{ clusterSuggestionDialog.existing }}“</strong>.
                    Welchen möchtest du verwenden?
                </v-card-text>
                <v-card-actions class="px-6 pb-5 flex-wrap ga-2">
                    <v-btn variant="text" @click="clusterSuggestionDialog.open = false">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn variant="tonal" color="secondary" @click="keepNewCluster">
                        Neuen Cluster anlegen
                    </v-btn>
                    <v-btn color="primary" @click="useSuggestedCluster">
                        Bestehenden übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="previewDialog.open" max-width="1200" scrollable>
            <v-card rounded="xl" class="materials-v2-preview-card">
                <v-card-title class="materials-v2-dialog-title materials-v2-preview-title">
                    <v-icon color="primary" class="mr-2">{{ attachmentIcon(previewDialog.attachment || {}) }}</v-icon>
                    <span class="materials-v2-preview-name">
                        {{ previewDialog.attachment?.original_name || 'Anlage' }}
                    </span>
                    <v-spacer />
                    <v-btn
                        v-if="previewDialog.attachment?.preview_url"
                        :href="previewDialog.attachment.preview_url"
                        target="_blank"
                        icon="mdi-open-in-new"
                        size="small"
                        variant="text"
                        title="In neuem Tab öffnen" />
                    <v-btn
                        v-if="previewDialog.attachment?.download_url"
                        :href="previewDialog.attachment.download_url"
                        target="_blank"
                        icon="mdi-download"
                        size="small"
                        variant="text"
                        title="Herunterladen" />
                    <v-btn
                        icon="mdi-close"
                        size="small"
                        variant="text"
                        title="Vorschau schließen"
                        @click="closePreviewDialog" />
                </v-card-title>
                <v-card-text class="materials-v2-preview-body">
                    <CurriculumPdfPreview
                        v-if="previewDialog.open && isPdfPreview && previewDialog.attachment?.preview_url"
                        :key="previewDialog.attachment.id"
                        class="materials-v2-preview-frame"
                        :document-id="previewDialog.attachment.id"
                        :src="previewDialog.attachment.preview_url" />
                    <iframe
                        v-else-if="previewDialog.open && previewDialog.attachment?.preview_url"
                        class="materials-v2-preview-frame"
                        :src="previewDialog.attachment.preview_url"
                        :title="`Vorschau: ${previewDialog.attachment.original_name}`"
                        sandbox="allow-downloads allow-same-origin" />
                </v-card-text>
            </v-card>
        </v-dialog>

        <v-dialog v-model="attachmentDialog.open" persistent max-width="600">
            <v-card rounded="xl">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="primary" class="mr-2">mdi-paperclip-plus</v-icon>
                    Anlagen hinzufügen
                </v-card-title>
                <v-card-text class="pa-6 pt-3">
                    <div class="text-body-2 text-medium-emphasis mb-4">{{ attachmentDialog.item?.title }}</div>
                    <v-file-input
                        v-model="attachmentDialog.files"
                        label="Dateien auswählen"
                        variant="outlined"
                        multiple
                        chips
                        show-size
                        counter
                        prepend-icon=""
                        prepend-inner-icon="mdi-paperclip"
                        :error-messages="attachmentDialog.error"
                        :disabled="attachmentDialog.saving" />
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="attachmentDialog.saving" @click="closeAttachmentDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        :loading="attachmentDialog.saving"
                        :disabled="!(attachmentDialog.files || []).length"
                        @click="saveAttachments">
                        Hochladen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog.open" persistent max-width="500">
            <v-card rounded="xl">
                <v-card-title>Material löschen?</v-card-title>
                <v-card-text>
                    <strong>{{ deleteDialog.item?.title }}</strong>
                    wird aus Materialien 2 entfernt. Die bestehende Materialien-Version bleibt davon unberührt.
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="deleteDialog.saving" @click="deleteDialog.open = false">Abbrechen</v-btn>
                    <v-btn color="error" :loading="deleteDialog.saving" @click="deleteMaterial">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { materialsV2Api } from '@/domains/materialsV2/api'
import {
    addCalendarDays,
    buildCalendarDays,
    calendarPeriodLabel as buildCalendarPeriodLabel,
    calendarVisibleRange as buildCalendarVisibleRange,
    formatCalendarDate,
    isCalendarDate,
    moveCalendarDate,
    parseCalendarDate,
} from '@/domains/materialsV2/calendar'
import {
    allCategoriesValue,
    attachmentIcon,
    categoryIcon,
    createActionIcon,
    createActionLabel,
    formatDateTime,
    formatFileSize,
    formatReminderDate,
    fuzzyTextMatch,
    fileCategoryName,
    isDefaultCategory,
    isFileCategory,
    isLinkCategory,
    isNoteCategory,
    isReminderCategory,
    isScreenshotCategory,
    keywordStatusMeta,
    linkCategoryName,
    noteCategoryName,
    reminderBadge,
    reminderCategoryName,
    reminderDate,
    screenshotCategoryName,
    statusMeta,
    supportsDocumentProcessing,
} from '@/domains/materialsV2/presentation'
import { useMaterialV2Precognition } from '@/domains/materialsV2/useMaterialV2Precognition'
import { useMaterialsV2Polling } from '@/domains/materialsV2/useMaterialsV2Polling'
import { resolveSelectedSchoolLogoSrc } from '@/helpers/adminSchoolLogo'
import MaterialsV2Calendar from '@/pages/admin/materialsV2/components/MaterialsV2Calendar.vue'
import MaterialsV2Header from '@/pages/admin/materialsV2/components/MaterialsV2Header.vue'
import CurriculumPdfPreview from '@/pages/admin/teaching/curricula/CurriculumPdfPreview.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useMaterialsV2Store } from '@/stores/admin/materialsV2/MaterialsV2Store'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const adminStore = useAdminStore()
const materialsStore = useMaterialsV2Store()
const notification = useNotificationStore()
const route = useRoute()
const router = useRouter()
const administrationLocked = ref(false)
const activeSchoolyearLabel = computed(() => adminStore.config?.selected_schoolyear?.name
    || adminStore.config?.selected_schoolyear?.concerns
    || 'Kein Schuljahr gewählt')
const MaterialsSettingsView = defineAsyncComponent(() => import('@/pages/admin/materials/components/views/MaterialsSettingsView.vue'))
const Groups = defineAsyncComponent(() => import('@/pages/admin/groups/Groups.vue'))
const administrationPanels = [
    { id: 'material_settings', label: 'Einstellungen', icon: 'mdi-cog-outline' },
    { id: 'material_groups', label: 'Materialgruppen', icon: 'mdi-folder-multiple-outline' },
]
const canManageAdministration = computed(() => ['super_admin', 'admin', 'materials_admin', 'materials_moderator']
    .some((role) => adminStore.config?.roles?.includes(role)))
const isAdminView = computed(() => canManageAdministration.value && route.query.section === 'admin')
const selectedAdministrationPanel = computed(() => administrationPanels.some((panel) => panel.id === route.query.panel)
    ? route.query.panel : 'material_settings')
const {
    items,
    categoryDetails,
    clusterDetails,
    maxUploadSizeKb,
    loading,
    loadError,
    meta,
} = storeToRefs(materialsStore)

const screenshotMimeTypes = ['image/gif', 'image/jpeg', 'image/png', 'image/webp']
const fileInputAccept = '.csv,.doc,.docx,.gif,.htm,.html,.jpeg,.jpg,.md,.odp,.ods,.odt,.pdf,.png,.ppt,.pptx,.rtf,.txt,.webp,.xls,.xlsx'
const displayModeStorageKey = 'materials-v2-display-mode'
const displayModeOptions = ['large', 'standard', 'compact']
const reminderDisplayModeOptions = ['standard', 'calendar']
const calendarDisplayModeOptions = ['month', 'week']
const calendarWeekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
const clustersViewValue = '__clusters__'
const search = ref('')
const selectedCategory = ref(categoryFromRoute(route.query.category, route.query.section))
const selectedClusterId = ref(clusterIdFromQuery(route.query.cluster))
const displayMode = ref(displayModeFromQuery(route.query.view) || loadStoredDisplayMode())
const reminderDisplayMode = ref(reminderDisplayModeFromQuery(route.query.view))
const calendarDisplayMode = ref(calendarDisplayModeFromQuery(route.query.calendar))
const calendarFocusDate = ref(calendarDateFromQuery(route.query.date))
const page = ref(1)
const isAutomaticTagEditing = ref(false)
const screenshotPreviewUrl = ref('')
const adjacentReminderLoading = ref('')
const materialDialog = reactive({
    open: false,
    mode: 'create',
    item: null,
    saving: false,
})
const materialForm = reactive({
    title: '',
    category: '',
    clusterName: '',
    description: '',
    reminderDate: '',
    reminderTime: '',
    linkUrl: '',
    keywords: '',
    attachments: [],
})
const formErrors = reactive({
    title: [],
    category: [],
    cluster_name: [],
    description: [],
    reminder_date: [],
    reminder_time: [],
    link_url: [],
    user_keywords: [],
    attachments: [],
})
const materialPrecognition = useMaterialV2Precognition({
    dialog: materialDialog,
    source: materialForm,
})
const linkPreview = reactive({
    state: 'idle',
    message: '',
    checkedUrl: '',
    suggestedTitle: '',
})
const attachmentDialog = reactive({
    open: false,
    item: null,
    files: [],
    error: [],
    saving: false,
})
const previewDialog = reactive({
    open: false,
    attachment: null,
})
const isPdfPreview = computed(() => {
    const attachment = previewDialog.attachment

    return String(attachment?.mime_type || '').toLowerCase() === 'application/pdf'
        || /\.pdf$/i.test(String(attachment?.original_name || ''))
})
const categorySuggestionDialog = reactive({
    open: false,
    entered: '',
    existing: '',
})
const clusterSuggestionDialog = reactive({
    open: false,
    entered: '',
    existing: '',
    forceNewCategory: false,
})
const categoryDialog = reactive({
    open: false,
    mode: 'create',
    originalName: '',
    name: '',
    error: [],
    warning: '',
    saving: false,
})
const categoryDeleteDialog = reactive({
    open: false,
    name: '',
    saving: false,
})
const deleteDialog = reactive({
    open: false,
    item: null,
    saving: false,
})
const tagAction = reactive({
    type: '',
    itemId: null,
    tagName: '',
})

let searchTimer = null
let linkPreviewTimer = null
let linkPreviewRequestId = 0
let isResettingFiltersAfterCreate = false
let isInitialLoad = true
const { configurePolling } = useMaterialsV2Polling(() => {
    if (!loading.value) {
        loadItems()
    }
}, 4000)

const hasProcessingItems = computed(() =>
    items.value.some((item) => ['pending', 'processing'].includes(item.processing_status)),
)
const categoryOptions = computed(() => categoryDetails.value.map((category) => category.name))
const clusterOptions = computed(() => clusterDetails.value.map((cluster) => cluster.name))
const customCategoryDetails = computed(() =>
    categoryDetails.value.filter((category) => !isDefaultCategory(category.name)),
)
const systemCategoryDetails = computed(() => [
    {
        name: allCategoriesValue,
        label: 'Alle Materialien',
        icon: 'mdi-view-grid-outline',
        items_count: categoryDetails.value.reduce((total, category) => total + Number(category.items_count || 0), 0),
    },
    {
        name: reminderCategoryName,
        label: reminderCategoryName,
        icon: categoryIcon(reminderCategoryName),
        items_count: categoryItemCount(reminderCategoryName),
    },
    {
        name: screenshotCategoryName,
        label: screenshotCategoryName,
        icon: categoryIcon(screenshotCategoryName),
        items_count: categoryItemCount(screenshotCategoryName),
    },
    {
        name: linkCategoryName,
        label: linkCategoryName,
        icon: categoryIcon(linkCategoryName),
        items_count: categoryItemCount(linkCategoryName),
    },
    {
        name: fileCategoryName,
        label: fileCategoryName,
        icon: categoryIcon(fileCategoryName),
        items_count: categoryItemCount(fileCategoryName),
    },
    {
        name: noteCategoryName,
        label: noteCategoryName,
        icon: categoryIcon(noteCategoryName),
        items_count: categoryItemCount(noteCategoryName),
    },
    {
        name: clustersViewValue,
        label: 'Clusters',
        icon: 'mdi-folder-multiple-outline',
        items_count: clusterDetails.value.length,
    },
])
const isClusterView = computed(() => selectedCategory.value === clustersViewValue)
const selectedCluster = computed(() =>
    clusterDetails.value.find((cluster) => cluster.id === selectedClusterId.value) || null,
)
const selectedCategoryLabel = computed(() => {
    if (isClusterView.value) {
        return selectedCluster.value ? `Clusters · ${selectedCluster.value.name}` : 'Clusters'
    }

    return selectedCategory.value === allCategoriesValue ? 'Alle Materialien' : selectedCategory.value
})
const isCustomCategorySelected = computed(
    () =>
        selectedCategory.value !== allCategoriesValue
        && !isDefaultCategory(selectedCategory.value)
        && !isClusterView.value,
)
const isReminderCategorySelected = computed(() => isReminderCategory(selectedCategory.value))
const isReminderCalendarView = computed(
    () => isReminderCategorySelected.value && reminderDisplayMode.value === 'calendar',
)
const reminderViewSelection = computed({
    get() {
        return reminderDisplayMode.value === 'calendar' ? calendarDisplayMode.value : 'standard'
    },
    set(selection) {
        if (selection === 'standard') {
            reminderDisplayMode.value = 'standard'

            return
        }

        if (!calendarDisplayModeOptions.includes(selection)) {
            return
        }

        calendarDisplayMode.value = selection
        reminderDisplayMode.value = 'calendar'
    },
})
const activeCardDisplayMode = computed(() => (isCustomCategorySelected.value ? displayMode.value : 'standard'))
const calendarVisibleRange = computed(() => {
    const focusDate = parseCalendarDate(calendarFocusDate.value)

    return buildCalendarVisibleRange(focusDate, calendarDisplayMode.value)
})
const calendarDays = computed(() => {
    const focusDate = parseCalendarDate(calendarFocusDate.value)

    return buildCalendarDays({
        focusDate,
        range: calendarVisibleRange.value,
        items: items.value,
    })
})
const calendarPeriodLabel = computed(() => {
    const focusDate = parseCalendarDate(calendarFocusDate.value)

    return buildCalendarPeriodLabel(focusDate, calendarDisplayMode.value, calendarVisibleRange.value)
})
const previousCalendarPeriodLabel = computed(
    () => (calendarDisplayMode.value === 'month' ? 'Vorheriger Monat' : 'Vorherige Woche'),
)
const nextCalendarPeriodLabel = computed(
    () => (calendarDisplayMode.value === 'month' ? 'Nächster Monat' : 'Nächste Woche'),
)
const schoolName = computed(() =>
    adminStore.config?.selected_school?.long_name
    || adminStore.config?.selected_school?.short_name
    || 'Schule',
)
const schoolLogoSrc = computed(() =>
    resolveSelectedSchoolLogoSrc(adminStore.config?.selected_school?.logo),
)
const isReminderForm = computed(() => isReminderCategory(materialForm.category))
const isScreenshotForm = computed(() => isScreenshotCategory(materialForm.category))
const isLinkForm = computed(() => isLinkCategory(materialForm.category))
const isFileForm = computed(() => isFileCategory(materialForm.category))
const isNoteForm = computed(() => isNoteCategory(materialForm.category))
const isDefaultForm = computed(
    () =>
        isReminderForm.value
        || isScreenshotForm.value
        || isLinkForm.value
        || isFileForm.value
        || isNoteForm.value,
)
const isClusterableForm = computed(
    () =>
        isReminderForm.value
        || isScreenshotForm.value
        || isLinkForm.value
        || isFileForm.value
        || isNoteForm.value,
)
const screenshotFile = computed(() => normalizedFiles(materialForm.attachments)[0] || null)
const materialFiles = computed(() => normalizedFiles(materialForm.attachments))
const maxUploadSizeLabel = computed(() => formatFileSize(maxUploadSizeKb.value * 1024))
const linkPreviewAlertType = computed(() => ({
    checking: 'info',
    success: 'success',
    warning: 'warning',
}[linkPreview.state] || 'info'))
const linkPreviewAlertTitle = computed(() => ({
    checking: 'Zieladresse wird geprüft',
    success: 'Zieladresse bestätigt',
    warning: 'Zieladresse nicht bestätigt',
}[linkPreview.state] || ''))
const materialDialogIcon = computed(() => {
    if (isReminderForm.value) {
        return materialDialog.mode === 'create' ? 'mdi-calendar-plus' : 'mdi-calendar-edit'
    }

    if (isScreenshotForm.value) {
        return materialDialog.mode === 'create' ? 'mdi-image-plus-outline' : 'mdi-image-edit-outline'
    }

    if (isLinkForm.value) {
        return materialDialog.mode === 'create' ? 'mdi-link-plus' : 'mdi-link-variant'
    }

    if (isFileForm.value) {
        return materialDialog.mode === 'create' ? 'mdi-file-plus-outline' : 'mdi-file-edit-outline'
    }

    if (isNoteForm.value) {
        return 'mdi-note-text-outline'
    }

    return materialDialog.mode === 'create' ? 'mdi-file-plus-outline' : 'mdi-file-edit-outline'
})
const materialDialogTitle = computed(() => {
    if (isReminderForm.value) {
        return materialDialog.mode === 'create' ? 'Termin hinzufügen' : 'Termin bearbeiten'
    }

    if (isScreenshotForm.value) {
        return materialDialog.mode === 'create' ? 'Screenshot hinzufügen' : 'Screenshot bearbeiten'
    }

    if (isLinkForm.value) {
        return materialDialog.mode === 'create' ? 'Link hinzufügen' : 'Link bearbeiten'
    }

    if (isFileForm.value) {
        return materialDialog.mode === 'create' ? 'Dateien hinzufügen' : 'Dateien bearbeiten'
    }

    if (isNoteForm.value) {
        return materialDialog.mode === 'create' ? 'Notiz hinzufügen' : 'Notiz bearbeiten'
    }

    return materialDialog.mode === 'create' ? 'Material erstellen' : 'Material bearbeiten'
})
const hasActiveFilters = computed(
    () =>
        search.value.trim() !== ''
        || (
            selectedCategory.value !== allCategoriesValue
            && !isClusterView.value
        ),
)
const materialColumnProps = computed(() => ({
    large: { cols: 12, md: 6, xl: 4 },
    standard: { cols: 12, sm: 6, lg: 4, xl: 3 },
    compact: { cols: 12, sm: 6, md: 4, lg: 3, xl: 2 },
}[activeCardDisplayMode.value]))
const materialCardPaddingClass = computed(() => ({
    large: 'pa-5',
    standard: 'pa-4',
    compact: 'pa-3',
}[activeCardDisplayMode.value]))
const automaticTagNames = computed(() =>
    (materialDialog.item?.automatic_tag_suggestions || []).map(({ name }) => name).join(', '),
)

function clusterSearchFilter(value, query) {
    return fuzzyTextMatch(value, query)
}

watch(search, () => {
    window.clearTimeout(searchTimer)

    if (isResettingFiltersAfterCreate) {
        return
    }

    searchTimer = window.setTimeout(() => {
        page.value = 1
        loadItems()
    }, 350)
})

watch(
    () => materialForm.linkUrl,
    () => {
        if (!materialDialog.open || !isLinkForm.value) {
            return
        }

        scheduleLinkInspection()
    },
)

watch(selectedCategory, (category) => {
    if (category === clustersViewValue) {
        const selectedClusterChanged = ensureSelectedCluster()
        syncRouteQuery(category)

        if (isResettingFiltersAfterCreate || selectedClusterChanged) {
            return
        }

        page.value = 1
        loadItems()

        return
    }

    syncRouteQuery(category)

    if (isResettingFiltersAfterCreate) {
        return
    }

    page.value = 1
    loadItems()
})

watch(
    [() => route.query.category, () => route.query.section],
    ([category, section]) => {
        if (section === 'admin') {
            return
        }

        const routeCategory = categoryFromRoute(category, section)

        if (routeCategory !== selectedCategory.value) {
            selectedCategory.value = routeCategory
        }
    },
)

watch(
    () => route.query.cluster,
    (cluster) => {
        const routeClusterId = clusterIdFromQuery(cluster)

        if (routeClusterId !== selectedClusterId.value) {
            selectedClusterId.value = routeClusterId
        }
    },
)

watch(selectedClusterId, () => {
    if (!isClusterView.value || isResettingFiltersAfterCreate || isInitialLoad) {
        return
    }

    page.value = 1
    syncRouteQuery(clustersViewValue)
    loadItems()
})

watch(displayMode, (mode) => {
    persistDisplayMode(mode)

    if (isCustomCategorySelected.value) {
        syncRouteQuery(selectedCategory.value)
    }
})

watch(reminderDisplayMode, () => {
    if (!isReminderCategorySelected.value) {
        return
    }

    page.value = 1
    syncRouteQuery(selectedCategory.value)
    loadItems()
})

watch([calendarDisplayMode, calendarFocusDate], () => {
    if (!isReminderCalendarView.value) {
        return
    }

    page.value = 1
    syncRouteQuery(selectedCategory.value)
    loadItems()
})

watch(
    () => route.query.view,
    (view) => {
        const routeDisplayMode = displayModeFromQuery(view)
        if (routeDisplayMode && routeDisplayMode !== displayMode.value) {
            displayMode.value = routeDisplayMode
        }

        const routeReminderDisplayMode = reminderDisplayModeFromQuery(view)
        if (routeReminderDisplayMode !== reminderDisplayMode.value) {
            reminderDisplayMode.value = routeReminderDisplayMode
        }
    },
)

watch(
    () => route.query.calendar,
    (calendar) => {
        const routeCalendarDisplayMode = calendarDisplayModeFromQuery(calendar)
        if (routeCalendarDisplayMode !== calendarDisplayMode.value) {
            calendarDisplayMode.value = routeCalendarDisplayMode
        }
    },
)

watch(
    () => route.query.date,
    (date) => {
        const routeCalendarDate = calendarDateFromQuery(date)
        if (routeCalendarDate !== calendarFocusDate.value) {
            calendarFocusDate.value = routeCalendarDate
        }
    },
)

watch(hasProcessingItems, (isProcessing) => {
    configurePolling(isProcessing)
})

onMounted(async () => {
    await loadConfig()
    ensureSelectedCluster()
    await loadItems()
    isInitialLoad = false
})

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer)
    window.clearTimeout(linkPreviewTimer)
    linkPreviewRequestId += 1
    revokeScreenshotPreview()
    materialsStore.resetPage()
})

async function loadItems() {
    if (isClusterView.value && !selectedClusterId.value) {
        materialsStore.clearItems()

        return
    }

    const range = isReminderCalendarView.value
        ? {
            start: formatCalendarDate(calendarVisibleRange.value.start),
            end: formatCalendarDate(calendarVisibleRange.value.end),
        }
        : null

    await materialsStore.loadItems({
        search: search.value,
        category:
            selectedCategory.value === allCategoriesValue || isClusterView.value
                ? undefined
                : selectedCategory.value,
        clusterId: isClusterView.value ? selectedClusterId.value : undefined,
        page: page.value,
        calendarRange: range,
        reminderCategory: reminderCategoryName,
    })

    if (materialDialog.open && materialDialog.mode === 'edit' && materialDialog.item) {
        const refreshedItem = items.value.find((item) => item.id === materialDialog.item.id)
        if (refreshedItem) {
            materialDialog.item = refreshedItem
        }
    }
}

async function loadConfig() {
    await materialsStore.loadConfig()
    ensureSelectedCluster()
}

function clearSearch() {
    search.value = ''
    page.value = 1
    loadItems()
}

function selectCategory(category) {
    if (administrationLocked.value) {
        return
    }

    selectedCategory.value = category
    if (route.query.section === 'admin') {
        syncRouteQuery(category, true)
    }
}

function openAdministration(panel = 'material_settings') {
    if (administrationLocked.value || !canManageAdministration.value || !administrationPanels.some((item) => item.id === panel)) {
        return
    }

    router.replace({ query: { ...route.query, section: 'admin', panel } }).catch(() => {})
}

watch([() => route.query.section, () => route.query.panel, canManageAdministration], ([section]) => {
    if (section !== 'admin') {
        return
    }

    if (!canManageAdministration.value) {
        const query = { ...route.query }
        delete query.section
        delete query.panel
        router.replace({ query }).catch(() => {})
    } else if (route.query.panel !== selectedAdministrationPanel.value) {
        openAdministration(selectedAdministrationPanel.value)
    }
}, { immediate: true })

function selectCluster(clusterId) {
    selectedClusterId.value = clusterId
}

function ensureSelectedCluster() {
    if (!isClusterView.value) {
        return false
    }

    const hasSelectedCluster = clusterDetails.value.some(
        (cluster) => cluster.id === selectedClusterId.value,
    )
    const nextClusterId = hasSelectedCluster ? selectedClusterId.value : clusterDetails.value[0]?.id || null

    if (nextClusterId === selectedClusterId.value) {
        return false
    }

    selectedClusterId.value = nextClusterId

    return true
}

function categoryFromQuery(category) {
    const normalizedCategory = queryString(category)

    return normalizedCategory || allCategoriesValue
}

function categoryFromRoute(category, section) {
    return queryString(section) === 'clusters' ? clustersViewValue : categoryFromQuery(category)
}

function clusterIdFromQuery(cluster) {
    const clusterId = Number.parseInt(queryString(cluster), 10)

    return Number.isInteger(clusterId) && clusterId > 0 ? clusterId : null
}

function syncRouteQuery(category, leaveAdministration = false) {
    if (route.query.section === 'admin' && !leaveAdministration) {
        return
    }

    const query = { ...route.query }
    delete query.panel

    if (category === clustersViewValue) {
        delete query.category
        query.section = 'clusters'

        if (selectedClusterId.value) {
            query.cluster = String(selectedClusterId.value)
        } else {
            delete query.cluster
        }
    } else if (category === allCategoriesValue) {
        delete query.category
        delete query.section
        delete query.cluster
    } else {
        query.category = category
        delete query.section
        delete query.cluster
    }

    delete query.calendar
    delete query.date

    if (category === clustersViewValue) {
        delete query.view
    } else if (category !== allCategoriesValue && !isDefaultCategory(category)) {
        query.view = displayMode.value
    } else if (isReminderCategory(category)) {
        query.view = reminderDisplayMode.value

        if (reminderDisplayMode.value === 'calendar') {
            query.calendar = calendarDisplayMode.value
            query.date = calendarFocusDate.value
        }
    } else {
        delete query.view
    }

    if (JSON.stringify(query) === JSON.stringify(route.query)) {
        return
    }

    router.replace({ query }).catch(() => {})
}

function queryString(value) {
    const queryValue = Array.isArray(value) ? value[0] : value

    return String(queryValue || '').trim()
}

function displayModeFromQuery(view) {
    const mode = queryString(view)

    return displayModeOptions.includes(mode) ? mode : ''
}

function reminderDisplayModeFromQuery(view) {
    const mode = queryString(view)

    return reminderDisplayModeOptions.includes(mode) ? mode : 'standard'
}

function calendarDisplayModeFromQuery(calendar) {
    const mode = queryString(calendar)

    return calendarDisplayModeOptions.includes(mode) ? mode : 'month'
}

function calendarDateFromQuery(date) {
    const normalizedDate = queryString(date)

    return isCalendarDate(normalizedDate) ? normalizedDate : formatCalendarDate(new Date())
}

function moveCalendar(direction) {
    calendarFocusDate.value = moveCalendarDate(calendarFocusDate.value, calendarDisplayMode.value, direction)
}

function showToday() {
    calendarFocusDate.value = formatCalendarDate(new Date())
}

async function jumpToAdjacentReminder(direction) {
    if (!['previous', 'next'].includes(direction) || adjacentReminderLoading.value !== '') {
        return
    }

    adjacentReminderLoading.value = direction
    const isNext = direction === 'next'
    const referenceDate = parseCalendarDate(calendarFocusDate.value)
    const boundaryDate = formatCalendarDate(addCalendarDays(referenceDate, isNext ? 1 : -1))

    try {
        const response = await axios.get(materialsV2Api.items(), {
            params: {
                category: reminderCategoryName,
                reminder_from: isNext ? boundaryDate : undefined,
                reminder_to: isNext ? undefined : boundaryDate,
                reminder_order: isNext ? 'asc' : 'desc',
                page: 1,
                per_page: 6,
            },
        })
        const adjacentReminder = (response.data?.data || []).find((item) => item.reminder_date)

        if (!adjacentReminder) {
            notify(isNext ? 'Kein späterer Termin gefunden.' : 'Kein früherer Termin gefunden.', 'info')

            return
        }

        calendarFocusDate.value = adjacentReminder.reminder_date
    } catch (error) {
        notify(apiErrorMessage(error, 'Der nächste Termin konnte nicht geladen werden.'), 'error')
    } finally {
        adjacentReminderLoading.value = ''
    }
}

function categoryItemCount(categoryName) {
    return categoryDetails.value.find((category) => category.name === categoryName)?.items_count || 0
}

function loadStoredDisplayMode() {
    try {
        const storedMode = window.localStorage.getItem(displayModeStorageKey)

        return displayModeOptions.includes(storedMode) ? storedMode : 'compact'
    } catch {
        return 'compact'
    }
}

function persistDisplayMode(mode) {
    if (!displayModeOptions.includes(mode)) {
        return
    }

    try {
        window.localStorage.setItem(displayModeStorageKey, mode)
    } catch {
        return
    }
}

function visibleUserKeywords(item) {
    const keywords = item.user_keywords || []

    return activeCardDisplayMode.value === 'large' ? keywords : keywords.slice(0, 4)
}

function openCategoryDialog(category = '') {
    categoryDialog.mode = category ? 'edit' : 'create'
    categoryDialog.originalName = category
    categoryDialog.name = category
    categoryDialog.error = []
    categoryDialog.warning = ''
    categoryDialog.open = true
}

function closeCategoryDialog() {
    if (!categoryDialog.saving) {
        categoryDialog.open = false
    }
}

function openCategoryDeleteDialog(categoryName) {
    categoryDeleteDialog.name = categoryName
    categoryDeleteDialog.open = true
}

function closeCategoryDeleteDialog() {
    if (categoryDeleteDialog.saving) {
        return
    }

    categoryDeleteDialog.open = false
    categoryDeleteDialog.name = ''
}

async function deleteCategory() {
    if (!categoryDeleteDialog.name || categoryDeleteDialog.saving) {
        return
    }

    categoryDeleteDialog.saving = true

    try {
        const categoryName = categoryDeleteDialog.name
        await axios.delete(materialsV2Api.destroyCategory(), {
            data: {
                name: categoryName,
            },
        })

        if (selectedCategory.value === categoryName) {
            selectedCategory.value = allCategoriesValue
        }

        await loadConfig()
        categoryDeleteDialog.open = false
        categoryDeleteDialog.name = ''
        notify('Kategorie gelöscht.')
    } catch (error) {
        notify(apiErrorMessage(error, 'Die Kategorie konnte nicht gelöscht werden.'), 'error')
    } finally {
        categoryDeleteDialog.saving = false
    }
}

async function saveCategory() {
    const categoryName = categoryDialog.name.trim()
    if (!categoryName || categoryDialog.saving) {
        return
    }

    categoryDialog.error = []
    categoryDialog.warning = ''
    categoryDialog.saving = true

    try {
        const isEditing = categoryDialog.mode === 'edit'
        const response = isEditing
            ? await axios.put(materialsV2Api.updateCategory(), {
                original_name: categoryDialog.originalName,
                name: categoryName,
            })
            : await axios.post(materialsV2Api.storeCategory(), {
                name: categoryName,
            })
        const savedCategoryName = response.data?.data?.name || categoryName

        if (isEditing && selectedCategory.value === categoryDialog.originalName) {
            selectedCategory.value = savedCategoryName
        }

        await Promise.all([loadConfig(), isEditing ? loadItems() : Promise.resolve()])
        categoryDialog.open = false
        notify(isEditing ? 'Kategorie aktualisiert.' : 'Kategorie gespeichert.')
    } catch (error) {
        const conflict = error.response?.data?.category_conflict
        if (error.response?.status === 409 && conflict?.existing) {
            categoryDialog.warning = `Die Kategorie „${conflict.existing}“ existiert bereits. Bitte wähle einen anderen Namen.`

            return
        }

        categoryDialog.error = error.response?.data?.errors?.name || []
        if (error.response?.status !== 422) {
            notify(apiErrorMessage(error, 'Die Kategorie konnte nicht gespeichert werden.'), 'error')
        }
    } finally {
        categoryDialog.saving = false
    }
}

function openCreateDialog() {
    resetForm()
    isAutomaticTagEditing.value = false
    materialDialog.mode = 'create'
    materialDialog.item = null
    materialForm.category = selectedCategory.value === allCategoriesValue ? '' : selectedCategory.value
    if (isScreenshotCategory(materialForm.category)) {
        materialForm.title = 'Screenshot'
    }
    if (isLinkCategory(materialForm.category)) {
        materialForm.title = 'Link'
    }
    if (isFileCategory(materialForm.category)) {
        materialForm.title = 'Dateien'
    }
    if (isNoteCategory(materialForm.category)) {
        materialForm.title = 'Notiz'
    }
    materialDialog.open = true
}

function openReminderDialog() {
    resetForm()
    isAutomaticTagEditing.value = false
    materialDialog.mode = 'create'
    materialDialog.item = null
    materialForm.category = reminderCategoryName
    materialDialog.open = true
}

function openSystemCategoryCreateDialog(category) {
    if (isReminderCategory(category)) {
        openReminderDialog()

        return
    }

    if (isScreenshotCategory(category)) {
        openScreenshotDialog()

        return
    }

    if (isLinkCategory(category)) {
        openLinkDialog()

        return
    }

    if (isFileCategory(category)) {
        openFileDialog()

        return
    }

    if (isNoteCategory(category)) {
        openNoteDialog()
    }
}

function openScreenshotDialog() {
    resetForm()
    isAutomaticTagEditing.value = false
    materialDialog.mode = 'create'
    materialDialog.item = null
    materialForm.title = 'Screenshot'
    materialForm.category = screenshotCategoryName
    materialDialog.open = true
}

function openLinkDialog() {
    resetForm()
    isAutomaticTagEditing.value = false
    materialDialog.mode = 'create'
    materialDialog.item = null
    materialForm.title = 'Link'
    materialForm.category = linkCategoryName
    materialDialog.open = true
}

function openFileDialog() {
    resetForm()
    isAutomaticTagEditing.value = false
    materialDialog.mode = 'create'
    materialDialog.item = null
    materialForm.title = 'Dateien'
    materialForm.category = fileCategoryName
    materialDialog.open = true
}

function openNoteDialog() {
    resetForm()
    isAutomaticTagEditing.value = false
    materialDialog.mode = 'create'
    materialDialog.item = null
    materialForm.title = 'Notiz'
    materialForm.category = noteCategoryName
    materialDialog.open = true
}

function handleSpecializedPaste(event) {
    if (isScreenshotForm.value) {
        handleScreenshotPaste(event)

        return
    }

    if (isLinkForm.value) {
        handleLinkPaste(event)
    }
}

function handleScreenshotPaste(event) {
    if (!materialDialog.open || materialDialog.mode !== 'create' || !isScreenshotForm.value) {
        return
    }

    const imageItem = Array.from(event.clipboardData?.items || [])
        .find((item) => item.kind === 'file' && String(item.type).startsWith('image/'))
    const imageFile = imageItem?.getAsFile?.()

    if (!imageFile) {
        return
    }

    event.preventDefault()
    setScreenshotFiles(imageFile)
}

function handleLinkPaste(event) {
    if (!materialDialog.open || materialDialog.mode !== 'create' || !isLinkForm.value) {
        return
    }

    const pastedText = String(event.clipboardData?.getData?.('text/plain') || '').trim()
    if (!pastedText) {
        return
    }

    event.preventDefault()
    return setLinkUrl(pastedText)
}

async function setLinkUrl(value) {
    const url = normalizedHttpUrl(value)
    formErrors.link_url = []

    if (!url) {
        materialForm.linkUrl = String(value || '').trim()
        formErrors.link_url = ['Bitte füge eine gültige Webadresse ein.']
        resetLinkPreview()

        return
    }

    materialForm.linkUrl = url
    await nextTick()
    await inspectLinkUrl({ force: true })
}

function scheduleLinkInspection() {
    window.clearTimeout(linkPreviewTimer)

    const url = normalizedHttpUrl(materialForm.linkUrl)
    if (
        url
        && linkPreview.checkedUrl === url
        && ['success', 'warning'].includes(linkPreview.state)
    ) {
        return
    }

    linkPreviewRequestId += 1
    linkPreview.state = 'idle'
    linkPreview.message = ''
    linkPreview.checkedUrl = ''

    if (!materialForm.linkUrl.trim()) {
        return
    }

    linkPreviewTimer = window.setTimeout(() => {
        inspectLinkUrl()
    }, 650)
}

async function inspectLinkUrl({ force = false } = {}) {
    window.clearTimeout(linkPreviewTimer)

    const requestedUrl = normalizedHttpUrl(materialForm.linkUrl)
    if (!requestedUrl) {
        linkPreviewRequestId += 1
        linkPreview.state = materialForm.linkUrl.trim() ? 'warning' : 'idle'
        linkPreview.message = materialForm.linkUrl.trim()
            ? 'Bitte gib eine vollständige HTTP- oder HTTPS-Adresse ein.'
            : ''
        linkPreview.checkedUrl = ''

        return false
    }

    if (
        !force
        && linkPreview.checkedUrl === requestedUrl
        && ['success', 'warning'].includes(linkPreview.state)
    ) {
        return linkPreview.state === 'success'
    }

    applySuggestedLinkTitle(linkDisplay(requestedUrl))

    const requestId = ++linkPreviewRequestId
    linkPreview.state = 'checking'
    linkPreview.message = 'Die Webseite wird sicher auf Erreichbarkeit und Seitentitel geprüft.'
    linkPreview.checkedUrl = ''

    try {
        const response = await axios.post(materialsV2Api.linkPreview(), {
            url: requestedUrl,
        })

        if (requestId !== linkPreviewRequestId) {
            return false
        }

        const result = response.data?.data || {}
        const resolvedUrl = normalizedHttpUrl(result.url) || requestedUrl

        linkPreview.state = result.status === 'success' ? 'success' : 'warning'
        linkPreview.message = String(result.message || 'Die Zieladresse konnte nicht bestätigt werden.')
        linkPreview.checkedUrl = resolvedUrl

        if (normalizedHttpUrl(materialForm.linkUrl) === requestedUrl && resolvedUrl !== requestedUrl) {
            materialForm.linkUrl = resolvedUrl
        }

        if (result.title) {
            applySuggestedLinkTitle(String(result.title))
        }

        return linkPreview.state === 'success'
    } catch (error) {
        if (requestId !== linkPreviewRequestId) {
            return false
        }

        linkPreview.state = 'warning'
        linkPreview.message = validationMessages(error, 'url')[0]
            || apiErrorMessage(error, 'Die Zieladresse konnte nicht bestätigt werden.')
        linkPreview.checkedUrl = requestedUrl

        return false
    }
}

function applySuggestedLinkTitle(title) {
    const suggestedTitle = String(title || '').trim()
    if (!suggestedTitle) {
        return
    }

    const currentTitle = materialForm.title.trim()
    if (!currentTitle || currentTitle === 'Link' || currentTitle === linkPreview.suggestedTitle) {
        materialForm.title = suggestedTitle
    }

    linkPreview.suggestedTitle = suggestedTitle
}

function resetLinkPreview() {
    window.clearTimeout(linkPreviewTimer)
    linkPreviewRequestId += 1
    linkPreview.state = 'idle'
    linkPreview.message = ''
    linkPreview.checkedUrl = ''
    linkPreview.suggestedTitle = ''
}

function normalizedHttpUrl(value) {
    const trimmedValue = String(value || '').trim()
    if (!trimmedValue) {
        return ''
    }

    const candidate = /^[a-z][a-z\d+.-]*:/iu.test(trimmedValue)
        ? trimmedValue
        : `https://${trimmedValue}`

    try {
        const url = new URL(candidate)

        return ['http:', 'https:'].includes(url.protocol) ? url.toString() : ''
    } catch {
        return ''
    }
}

function isHttpUrl(value) {
    return normalizedHttpUrl(value) !== ''
}

function linkDisplay(value) {
    const url = normalizedHttpUrl(value)
    if (!url) {
        return String(value || '')
    }

    const parsedUrl = new URL(url)
    const hostname = parsedUrl.hostname.replace(/^www\./iu, '')
    const path = parsedUrl.pathname === '/' ? '' : parsedUrl.pathname.replace(/\/$/u, '')

    return `${hostname}${path}`
}

function reminderDayLabel(value) {
    const date = reminderDate(value)

    return date
        ? new Intl.DateTimeFormat('de-AT', { day: '2-digit' }).format(date)
        : ''
}

function reminderMonthLabel(value) {
    const date = reminderDate(value)

    return date
        ? new Intl.DateTimeFormat('de-AT', { month: 'short' })
            .format(date)
            .replace('.', '')
            .toLocaleUpperCase('de-AT')
        : ''
}

function fileExtension(value) {
    const match = String(value || '').trim().match(/\.([^.]+)$/u)

    return match ? match[1].slice(0, 5).toLocaleUpperCase('de-AT') : 'DATEI'
}

function setScreenshotFiles(files) {
    const file = normalizedFiles(files)[0] || null
    revokeScreenshotPreview()
    formErrors.attachments = []

    if (!file) {
        materialForm.attachments = []

        return
    }

    if (!screenshotMimeTypes.includes(file.type)) {
        materialForm.attachments = []
        formErrors.attachments = ['Bitte füge ein Bild im Format PNG, JPG, GIF oder WebP ein.']

        return
    }

    materialForm.attachments = [file]
    if (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
        screenshotPreviewUrl.value = URL.createObjectURL(file)
    }
}

function focusScreenshotPasteZone(event) {
    event.currentTarget?.focus?.()
}

function focusLinkPasteZone(event) {
    event.currentTarget?.focus?.()
}

function revokeScreenshotPreview() {
    if (
        screenshotPreviewUrl.value
        && typeof URL !== 'undefined'
        && typeof URL.revokeObjectURL === 'function'
    ) {
        URL.revokeObjectURL(screenshotPreviewUrl.value)
    }

    screenshotPreviewUrl.value = ''
}

function openEditDialog(item) {
    resetForm()
    isAutomaticTagEditing.value = false
    materialDialog.mode = 'edit'
    materialDialog.item = item
    materialForm.title = item.title || ''
    materialForm.category = item.category || ''
    materialForm.clusterName = item.cluster?.name || ''
    materialForm.description = item.description || ''
    materialForm.reminderDate = item.reminder_date || ''
    materialForm.reminderTime = item.reminder_time || ''
    materialForm.linkUrl = item.link_url || ''
    materialForm.keywords = (item.user_keywords || []).join(', ')
    materialDialog.open = true
}

function openMaterialCard(item, event) {
    const interactiveTarget = event.target?.closest?.(
        'a, button, input, select, textarea, [contenteditable="true"], [role="link"], [role="button"], .v-list-item',
    )

    if (interactiveTarget && interactiveTarget !== event.currentTarget) {
        return
    }

    openEditDialog(item)
}

function closeMaterialDialog() {
    if (!materialDialog.saving) {
        isAutomaticTagEditing.value = false
        materialDialog.open = false
        resetLinkPreview()
        revokeScreenshotPreview()
    }
}

async function saveMaterial({ forceNewCategory = false, forceNewCluster = false } = {}) {
    clearFormErrors()
    materialDialog.saving = true
    const isCreating = materialDialog.mode === 'create'
    const isSavingReminder = isReminderForm.value
    const isSavingScreenshot = isScreenshotForm.value
    const isSavingLink = isLinkForm.value
    const isSavingFile = isFileForm.value
    const isSavingNote = isNoteForm.value

    try {
        const initialLinkUrl = normalizedHttpUrl(materialForm.linkUrl)
        if (isSavingLink && initialLinkUrl && linkPreview.checkedUrl !== initialLinkUrl) {
            await inspectLinkUrl({ force: true })
        }

        const linkUrl = normalizedHttpUrl(materialForm.linkUrl) || materialForm.linkUrl.trim()

        if (isCreating) {
            const payload = new FormData()
            payload.append('title', materialForm.title.trim())

            if (normalizedCategory()) {
                payload.append('category', normalizedCategory())
            }

            if (forceNewCategory) {
                payload.append('force_new_category', '1')
            }

            if (normalizedClusterName()) {
                payload.append('cluster_name', normalizedClusterName())
            }

            if (forceNewCluster) {
                payload.append('force_new_cluster', '1')
            }

            if (materialForm.description.trim()) {
                payload.append('description', materialForm.description.trim())
            }

            if (isSavingReminder) {
                payload.append('reminder_date', materialForm.reminderDate)
                if (materialForm.reminderTime) {
                    payload.append('reminder_time', materialForm.reminderTime)
                }
            } else if (isSavingLink) {
                payload.append('link_url', linkUrl)
            } else {
                if (!isSavingScreenshot) {
                    normalizedKeywords().forEach((keyword) => payload.append('user_keywords[]', keyword))
                }
                normalizedFiles(materialForm.attachments).forEach((file) => payload.append('attachments[]', file))
            }

            await axios.post(materialsV2Api.storeItem(), payload)
        } else {
            await axios.put(materialsV2Api.updateItem(materialDialog.item), {
                title: materialForm.title.trim(),
                category: normalizedCategory() || null,
                force_new_category: forceNewCategory,
                cluster_name: normalizedClusterName() || null,
                force_new_cluster: forceNewCluster,
                description: materialForm.description.trim() || null,
                reminder_date: isSavingReminder ? materialForm.reminderDate : null,
                reminder_time: isSavingReminder ? materialForm.reminderTime || null : null,
                link_url: isSavingLink ? linkUrl : null,
                user_keywords: isDefaultForm.value && !isSavingFile ? [] : normalizedKeywords(),
            })
        }

        materialDialog.open = false
        revokeScreenshotPreview()

        if (isCreating) {
            await resetFiltersAfterCreate(
                isSavingReminder
                    ? reminderCategoryName
                    : isSavingScreenshot
                      ? screenshotCategoryName
                      : isSavingLink
                        ? linkCategoryName
                        : isSavingFile
                          ? fileCategoryName
                        : isSavingNote
                          ? noteCategoryName
                        : null,
            )
        } else {
            page.value = 1
        }

        await Promise.all([loadItems(), loadConfig()])
        notify(
            isSavingReminder
                ? 'Termin gespeichert.'
                : isSavingScreenshot
                  ? 'Screenshot gespeichert.'
                  : isSavingLink
                    ? 'Link gespeichert.'
                    : isSavingFile
                      ? 'Dateien gespeichert.'
                    : isSavingNote
                      ? 'Notiz gespeichert.'
                    : 'Material gespeichert.',
        )
    } catch (error) {
        if (showCategorySuggestion(error)) {
            return
        }

        if (showClusterSuggestion(error, { forceNewCategory })) {
            return
        }

        applyValidationErrors(error)
        if (error.response?.status !== 422) {
            notify(apiErrorMessage(error, 'Das Material konnte nicht gespeichert werden.'), 'error')
        }
    } finally {
        materialDialog.saving = false
    }
}

async function resetFiltersAfterCreate(category = null) {
    window.clearTimeout(searchTimer)
    isResettingFiltersAfterCreate = true
    search.value = ''
    selectedCategory.value = category || allCategoriesValue
    page.value = 1

    await nextTick()

    isResettingFiltersAfterCreate = false
}

function showCategorySuggestion(error) {
    const suggestion = error.response?.data?.category_suggestion
    if (error.response?.status !== 409 || !suggestion?.entered || !suggestion?.existing) {
        return false
    }

    categorySuggestionDialog.entered = String(suggestion.entered)
    categorySuggestionDialog.existing = String(suggestion.existing)
    categorySuggestionDialog.open = true

    return true
}

async function useSuggestedCategory() {
    materialForm.category = categorySuggestionDialog.existing
    categorySuggestionDialog.open = false
    await saveMaterial()
}

async function keepNewCategory() {
    materialForm.category = categorySuggestionDialog.entered
    categorySuggestionDialog.open = false
    await saveMaterial({ forceNewCategory: true })
}

function showClusterSuggestion(error, { forceNewCategory = false } = {}) {
    const suggestion = error.response?.data?.cluster_suggestion
    if (error.response?.status !== 409 || !suggestion?.entered || !suggestion?.existing) {
        return false
    }

    clusterSuggestionDialog.entered = String(suggestion.entered)
    clusterSuggestionDialog.existing = String(suggestion.existing)
    clusterSuggestionDialog.forceNewCategory = forceNewCategory
    clusterSuggestionDialog.open = true

    return true
}

async function useSuggestedCluster() {
    materialForm.clusterName = clusterSuggestionDialog.existing
    clusterSuggestionDialog.open = false
    await saveMaterial({
        forceNewCategory: clusterSuggestionDialog.forceNewCategory,
    })
}

async function keepNewCluster() {
    materialForm.clusterName = clusterSuggestionDialog.entered
    clusterSuggestionDialog.open = false
    await saveMaterial({
        forceNewCategory: clusterSuggestionDialog.forceNewCategory,
        forceNewCluster: true,
    })
}

function openAttachmentDialog(item) {
    attachmentDialog.item = item
    attachmentDialog.files = []
    attachmentDialog.error = []
    attachmentDialog.open = true
}

function closeAttachmentDialog() {
    if (!attachmentDialog.saving) {
        attachmentDialog.open = false
    }
}

async function saveAttachments() {
    attachmentDialog.saving = true
    attachmentDialog.error = []

    try {
        const payload = new FormData()
        const attachments = attachmentDialog.files || []
        attachments.forEach((file) => payload.append('attachments[]', file))
        await axios.post(materialsV2Api.storeAttachments(attachmentDialog.item), payload)

        attachmentDialog.open = false
        await loadItems()
        notify('Anlagen hinzugefügt.')
    } catch (error) {
        attachmentDialog.error = validationMessages(error, 'attachments')
        if (!attachmentDialog.error.length) {
            notify(apiErrorMessage(error, 'Die Anlagen konnten nicht gespeichert werden.'), 'error')
        }
    } finally {
        attachmentDialog.saving = false
    }
}

async function removeAttachment(item, attachment) {
    try {
        await axios.delete(materialsV2Api.destroyAttachment(attachment))
        await loadItems()
        notify('Anlage entfernt.')
    } catch (error) {
        notify(apiErrorMessage(error, 'Die Anlage konnte nicht entfernt werden.'), 'error')
    }
}

function openDeleteDialog(item) {
    deleteDialog.item = item
    deleteDialog.open = true
}

async function deleteMaterial() {
    deleteDialog.saving = true

    try {
        await axios.delete(materialsV2Api.destroyItem(deleteDialog.item))
        deleteDialog.open = false
        await loadItems()
        notify('Material gelöscht.')
    } catch (error) {
        notify(apiErrorMessage(error, 'Das Material konnte nicht gelöscht werden.'), 'error')
    } finally {
        deleteDialog.saving = false
    }
}

async function retryProcessing(item) {
    try {
        await axios.post(materialsV2Api.retryProcessing(item))
        await loadItems()
        notify('Verarbeitung neu gestartet.', 'info')
    } catch (error) {
        notify(apiErrorMessage(error, 'Die Verarbeitung konnte nicht neu gestartet werden.'), 'error')
    }
}

async function recalculateAutomaticTags(item) {
    tagAction.type = 'recalculate'
    tagAction.itemId = item.id
    tagAction.tagName = ''

    try {
        await axios.post(materialsV2Api.recalculateAutomaticTags(item))
        item.processing_status = 'pending'
        await loadItems()
        notify('Automatische Tag-Erkennung gestartet.', 'info')
    } catch (error) {
        notify(apiErrorMessage(error, 'Die automatischen Tags konnten nicht neu berechnet werden.'), 'error')
    } finally {
        resetTagAction()
    }
}

async function removeAutomaticTag(item, suggestion) {
    tagAction.type = 'remove'
    tagAction.itemId = item.id
    tagAction.tagName = suggestion.name

    try {
        const response = await axios.delete(materialsV2Api.destroyAutomaticTag(item), {
            data: {
                tag_name: suggestion.name,
            },
        })
        applyUpdatedMaterial(response.data?.data)
        notify('Automatischer Tag entfernt.')
    } catch (error) {
        notify(apiErrorMessage(error, 'Der automatische Tag konnte nicht entfernt werden.'), 'error')
    } finally {
        resetTagAction()
    }
}

async function convertAutomaticTag(item, suggestion) {
    tagAction.type = 'convert'
    tagAction.itemId = item.id
    tagAction.tagName = suggestion.name

    try {
        const response = await axios.post(materialsV2Api.convertAutomaticTag(item), {
            tag_name: suggestion.name,
        })
        applyUpdatedMaterial(response.data?.data)
        materialForm.keywords = (materialDialog.item?.user_keywords || []).join(', ')
        notify('Tag als eigenes Suchwort übernommen.')
    } catch (error) {
        notify(apiErrorMessage(error, 'Der Tag konnte nicht übernommen werden.'), 'error')
    } finally {
        resetTagAction()
    }
}

function applyUpdatedMaterial(updatedItem) {
    if (!updatedItem) {
        return
    }

    const index = items.value.findIndex((item) => item.id === updatedItem.id)
    if (index !== -1) {
        items.value[index] = updatedItem
    }

    if (materialDialog.item?.id === updatedItem.id) {
        materialDialog.item = updatedItem
    }
}

function isTagAction(type, tagName) {
    return tagAction.type === type && tagAction.tagName === tagName
}

function resetTagAction() {
    tagAction.type = ''
    tagAction.itemId = null
    tagAction.tagName = ''
}

function previewAttachment(attachment) {
    previewDialog.attachment = attachment
    previewDialog.open = true
}

function closePreviewDialog() {
    previewDialog.open = false
}

function resetForm() {
    revokeScreenshotPreview()
    resetLinkPreview()
    materialForm.title = ''
    materialForm.category = ''
    materialForm.clusterName = ''
    materialForm.description = ''
    materialForm.reminderDate = ''
    materialForm.reminderTime = ''
    materialForm.linkUrl = ''
    materialForm.keywords = ''
    materialForm.attachments = []
    clearFormErrors()
    materialPrecognition.reset()
}

function clearFormErrors() {
    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = []
    })
}

function validateMaterialField(field) {
    materialPrecognition.validate(field)
}

function materialErrorMessages(field) {
    return materialPrecognition.errorMessages(field, formErrors[field])
}

function applyValidationErrors(error) {
    formErrors.title = validationMessages(error, 'title')
    formErrors.category = validationMessages(error, 'category')
    formErrors.cluster_name = validationMessages(error, 'cluster_name')
    formErrors.description = validationMessages(error, 'description')
    formErrors.reminder_date = validationMessages(error, 'reminder_date')
    formErrors.reminder_time = validationMessages(error, 'reminder_time')
    formErrors.link_url = validationMessages(error, 'link_url')
    formErrors.user_keywords = validationMessages(error, 'user_keywords')
    formErrors.attachments = [
        ...validationMessages(error, 'attachments'),
        ...validationMessages(error, 'attachments.0'),
    ]
}

function normalizedCategory() {
    return String(materialForm.category || '').trim()
}

function normalizedClusterName() {
    return String(materialForm.clusterName || '').trim()
}

function normalizedFiles(files) {
    if (!files) {
        return []
    }

    return Array.isArray(files) ? files : [files]
}

function validationMessages(error, key) {
    return error.response?.data?.errors?.[key] || []
}

function normalizedKeywords() {
    return materialForm.keywords
        .split(/[,;\n]+/u)
        .map((keyword) => keyword.trim())
        .filter(Boolean)
        .filter((keyword, index, keywords) => keywords.findIndex((candidate) => candidate.toLowerCase() === keyword.toLowerCase()) === index)
        .slice(0, 20)
}

function apiErrorMessage(error, fallback) {
    return error.response?.data?.message || fallback
}

function notify(message, type = 'success') {
    notification.notify({
        message,
        type,
        timeout: 3200,
    })
}
</script>

<style scoped>
.materials-v2-page {
    --materials-v2-ink: #172d3b;
    --materials-v2-muted: #617482;
    --materials-v2-accent: #ff7a32;
    --materials-v2-mint: #2fbf91;
    min-height: 100%;
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 15% 10%, rgba(47, 191, 145, 0.12), transparent 32rem),
        linear-gradient(145deg, #f8fbfa 0%, #f4f7f9 52%, #fff8f2 100%);
    color: var(--materials-v2-ink);
}

.materials-v2-content {
    position: relative;
    z-index: 1;
    width: min(1500px, 100%);
    margin: 0;
}

.materials-v2-orb {
    position: absolute;
    border-radius: 999px;
    filter: blur(5px);
    pointer-events: none;
}

.materials-v2-orb--one {
    width: 320px;
    height: 320px;
    top: -160px;
    right: -70px;
    background: rgba(255, 122, 50, 0.13);
}

.materials-v2-orb--two {
    width: 250px;
    height: 250px;
    bottom: -120px;
    left: -90px;
    background: rgba(47, 191, 145, 0.13);
}

.materials-v2-system-navigation {
    display: flex;
    align-items: center;
    border-bottom: 1px solid rgba(23, 45, 59, 0.08);
    background: rgba(248, 250, 253, 0.94);
}

.materials-v2-system-tabs {
    flex: 1;
    min-width: 0;
    padding: 0 22px;
}

.materials-v2-admin-button {
    flex-shrink: 0;
    margin-left: auto;
    margin-right: 22px;
    color: var(--materials-v2-muted);
    text-transform: none;
    letter-spacing: 0;
    font-size: 0.78rem;
    font-weight: 650;
    border-bottom: 2px solid transparent;
}

.materials-v2-admin-button--active {
    color: var(--materials-v2-accent);
    border-bottom-color: var(--materials-v2-accent);
}

.materials-v2-administration {
    padding: 22px;
}

.materials-v2-admin-panels {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 8px;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    margin-bottom: 16px;
}

.materials-v2-admin-panel-switcher {
    width: 100%;
    flex-wrap: wrap;
    row-gap: 6px;
    height: auto !important;
}

.materials-v2-admin-panel-button {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
    height: auto !important;
    min-height: 56px !important;
}

.materials-v2-admin-panel-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.materials-v2-admin-panel-meta {
    color: rgba(255, 255, 255, 0.98);
    font-size: 0.76rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 2px 8px;
}

.materials-v2-admin-settings {
    width: 520px;
    max-width: 100%;
}

.materials-v2-system-tab {
    min-width: auto;
    padding: 0 1.05rem;
    color: var(--materials-v2-muted);
    font-size: 0.78rem;
    font-weight: 650;
    letter-spacing: 0;
    text-transform: none;
}

.materials-v2-system-tab :deep(.v-btn__content) {
    gap: 0.35rem;
}

.materials-v2-system-tab.v-tab--selected {
    color: var(--materials-v2-accent);
    font-weight: 800;
}

.materials-v2-system-tab-count {
    color: inherit;
    font-size: 0.7rem;
    opacity: 0.72;
}

.materials-v2-system-tab-create-button {
    align-self: center;
    margin-inline: -0.45rem 0.15rem;
}

.materials-v2-workspace {
    display: grid;
    min-height: calc(100vh - 184px);
    grid-template-columns: 250px minmax(0, 1fr);
}

.materials-v2-category-panel {
    padding: 1.25rem 0.85rem;
    border-right: 1px solid rgba(23, 45, 59, 0.08);
    background: rgba(247, 249, 252, 0.78);
}

.materials-v2-category-panel-heading {
    display: flex;
    min-height: 32px;
    padding: 0 0.35rem 0.65rem;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    color: var(--materials-v2-muted);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.materials-v2-category-list {
    max-height: calc(100vh - 265px);
    overflow-y: auto;
}

.materials-v2-category-item {
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
}

.materials-v2-category-item.v-list-item--active {
    border-color: rgba(var(--v-theme-primary), 0.14);
    background: rgba(var(--v-theme-primary), 0.06);
}

.materials-v2-category-item-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.materials-v2-category-item-count {
    color: var(--materials-v2-muted);
    font-size: 0.72rem;
    font-variant-numeric: tabular-nums;
}

.materials-v2-cluster-list-empty {
    margin: 0.75rem 0.35rem;
    color: var(--materials-v2-muted);
    font-size: 0.78rem;
    line-height: 1.45;
}

.materials-v2-main {
    min-width: 0;
    padding: 0.85rem 1.8rem 2rem;
}

.materials-v2-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 48px;
    gap: 1rem;
}

.materials-v2-result-heading {
    font-size: 0.9rem;
    font-weight: 800;
}

.materials-v2-toolbar-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.materials-v2-display-toggle {
    border-color: rgba(23, 45, 59, 0.14);
    background: rgba(255, 255, 255, 0.88);
}

.materials-v2-display-toggle :deep(.v-btn) {
    min-width: 78px;
    text-transform: none;
}

.materials-v2-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(23, 45, 59, 0.08);
    border-top: 3px solid rgba(97, 116, 130, 0.3);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 12px 40px rgba(23, 45, 59, 0.07);
    cursor: pointer;
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.materials-v2-card:focus-visible {
    outline: 3px solid rgba(255, 122, 50, 0.55);
    outline-offset: 3px;
}

.materials-v2-card:hover {
    transform: translateY(-3px);
    border-color: rgba(255, 122, 50, 0.3);
    box-shadow: 0 18px 50px rgba(23, 45, 59, 0.12);
}

.materials-v2-card--standard {
    box-shadow: 0 9px 28px rgba(23, 45, 59, 0.065);
}

.materials-v2-card--compact {
    box-shadow: 0 6px 20px rgba(23, 45, 59, 0.055);
}

.materials-v2-card--compact:hover {
    transform: translateY(-1px);
}

.materials-v2-card--reminder {
    border-color: rgba(255, 122, 50, 0.22);
    border-top-color: #ff7a32;
    background: linear-gradient(145deg, rgba(255, 248, 242, 0.96), rgba(255, 255, 255, 0.96));
}

.materials-v2-card--screenshot {
    border-color: rgba(115, 90, 171, 0.22);
    border-top-color: #735aab;
    background: linear-gradient(145deg, rgba(248, 246, 253, 0.98), rgba(255, 255, 255, 0.96));
}

.materials-v2-card--link {
    border-color: rgba(0, 137, 123, 0.2);
    border-top-color: #00897b;
    background: linear-gradient(145deg, rgba(241, 253, 251, 0.96), rgba(255, 255, 255, 0.96));
}

.materials-v2-card--file {
    border-color: rgba(49, 105, 171, 0.2);
    border-top-color: #3169ab;
    background: linear-gradient(145deg, rgba(244, 249, 255, 0.98), rgba(255, 255, 255, 0.96));
}

.materials-v2-card--note {
    border-color: rgba(206, 155, 30, 0.24);
    border-top-color: #ce9b1e;
    background: linear-gradient(145deg, rgba(255, 251, 235, 0.98), rgba(255, 255, 255, 0.96));
}

.materials-v2-screenshot-thumbnail-button {
    display: flex;
    width: 100%;
    height: 176px;
    margin-top: 0.9rem;
    padding: 0;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(73, 57, 110, 0.2);
    border-radius: 12px;
    background: #eef0f3;
    cursor: zoom-in;
}

.materials-v2-screenshot-thumbnail-button:focus-visible {
    border-color: rgb(var(--v-theme-primary));
    outline: 2px solid rgba(var(--v-theme-primary), 0.25);
    outline-offset: 2px;
}

.materials-v2-screenshot-frame-bar {
    display: flex;
    width: 100%;
    height: 28px;
    padding: 0 0.65rem;
    align-items: center;
    flex: 0 0 28px;
    gap: 0.3rem;
    border-bottom: 1px solid rgba(73, 57, 110, 0.14);
    background: rgba(73, 57, 110, 0.08);
}

.materials-v2-screenshot-frame-bar i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: rgba(73, 57, 110, 0.38);
}

.materials-v2-screenshot-frame-bar span {
    margin-left: auto;
    color: rgba(73, 57, 110, 0.8);
    font-size: 0.62rem;
    font-style: normal;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.materials-v2-screenshot-thumbnail {
    display: block;
    width: 100%;
    min-height: 0;
    flex: 1 1 auto;
    object-fit: contain;
}

.materials-v2-link-target {
    display: flex;
    min-width: 0;
    margin-top: 0.9rem;
    padding: 0.7rem;
    align-items: center;
    gap: 0.7rem;
    overflow: hidden;
    border: 1px solid rgba(0, 137, 123, 0.16);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.74);
    color: var(--materials-v2-ink);
    text-decoration: none;
    transition: border-color 160ms ease, background 160ms ease;
}

.materials-v2-link-icon {
    display: grid;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    place-items: center;
    border-radius: 10px;
    background: rgba(0, 137, 123, 0.11);
    color: #00897b;
}

.materials-v2-link-copy {
    display: flex;
    min-width: 0;
    flex: 1 1 auto;
    flex-direction: column;
}

.materials-v2-link-copy small {
    color: rgba(0, 105, 92, 0.78);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.materials-v2-link-copy strong {
    overflow: hidden;
    margin-top: 0.08rem;
    font-size: 0.84rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-link-open-icon {
    flex: 0 0 auto;
    color: #00897b;
}

.materials-v2-link-target:hover {
    border-color: rgba(0, 137, 123, 0.38);
    background: rgba(255, 255, 255, 0.94);
}

.materials-v2-link-target:hover .materials-v2-link-copy strong {
    text-decoration: underline;
}

.materials-v2-reminder-date {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-top: 0.9rem;
    padding: 0.7rem 0.8rem;
    border: 1px solid rgba(255, 122, 50, 0.14);
    border-radius: 12px;
    background: rgba(255, 122, 50, 0.075);
}

.materials-v2-reminder-calendar-sheet {
    display: flex;
    width: 48px;
    height: 51px;
    flex: 0 0 48px;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(255, 122, 50, 0.2);
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.9);
    text-align: center;
}

.materials-v2-reminder-calendar-sheet span {
    padding: 0.18rem 0.2rem;
    background: #ff7a32;
    color: #fff;
    font-size: 0.56rem;
    font-weight: 850;
    letter-spacing: 0.08em;
    line-height: 1;
}

.materials-v2-reminder-calendar-sheet strong {
    display: grid;
    flex: 1 1 auto;
    place-items: center;
    color: #b74d14;
    font-size: 1.15rem;
    line-height: 1;
}

.materials-v2-reminder-date-copy {
    display: flex;
    min-width: 0;
    flex: 1 1 auto;
    flex-direction: column;
    line-height: 1.25;
}

.materials-v2-reminder-date-copy strong {
    font-size: 0.86rem;
}

.materials-v2-reminder-date-copy span {
    margin-top: 0.12rem;
    color: var(--materials-v2-muted);
    font-size: 0.74rem;
}

.materials-v2-card-title {
    overflow: hidden;
    margin: 0;
    font-size: 1.2rem;
    font-weight: 780;
    letter-spacing: -0.02em;
    line-height: 1.3;
    text-overflow: ellipsis;
}

.materials-v2-card--standard .materials-v2-card-title {
    font-size: 1.05rem;
}

.materials-v2-card--compact .materials-v2-card-title {
    display: -webkit-box;
    font-size: 0.95rem;
    line-height: 1.25;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.materials-v2-description {
    display: -webkit-box;
    min-height: 3.1rem;
    margin: 1rem 0;
    overflow: hidden;
    color: var(--materials-v2-muted);
    font-size: 0.92rem;
    line-height: 1.55;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.materials-v2-card--standard .materials-v2-description {
    min-height: 2.6rem;
    margin: 0.75rem 0;
    font-size: 0.86rem;
    line-height: 1.45;
}

.materials-v2-description--empty {
    font-style: italic;
    opacity: 0.65;
}

.materials-v2-note-sheet {
    position: relative;
    min-height: 108px;
    margin-top: 0.9rem;
    padding: 0.75rem 0.85rem 0.75rem 1.25rem;
    overflow: hidden;
    border: 1px solid rgba(206, 155, 30, 0.19);
    border-radius: 12px;
    background:
        linear-gradient(90deg, transparent 0 13px, rgba(221, 92, 92, 0.2) 13px 14px, transparent 14px),
        repeating-linear-gradient(
            180deg,
            rgba(255, 255, 255, 0.76) 0,
            rgba(255, 255, 255, 0.76) 25px,
            rgba(73, 126, 166, 0.12) 25px,
            rgba(73, 126, 166, 0.12) 26px
        );
}

.materials-v2-note-sheet::after {
    position: absolute;
    top: 0;
    right: 1rem;
    width: 24px;
    height: 7px;
    border-radius: 0 0 4px 4px;
    background: rgba(206, 155, 30, 0.48);
    content: '';
}

.materials-v2-note-sheet-label {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: #946b08;
    font-size: 0.64rem;
    font-weight: 850;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.materials-v2-note-sheet p {
    display: -webkit-box;
    margin: 0.55rem 0 0;
    overflow: hidden;
    color: var(--materials-v2-ink);
    font-size: 0.88rem;
    line-height: 1.6;
    white-space: pre-line;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}

.materials-v2-keyword-block + .materials-v2-keyword-block {
    margin-top: 0.85rem;
}

.materials-v2-keyword-label {
    display: flex;
    align-items: center;
    margin-bottom: 0.4rem;
    color: var(--materials-v2-muted);
    font-size: 0.72rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.materials-v2-automatic-tag-review {
    padding: 0.55rem 0.7rem;
    border-radius: 10px;
    background: rgba(23, 45, 59, 0.035);
}

.materials-v2-automatic-tag-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-height: 24px;
    color: var(--materials-v2-muted);
    font-size: 0.72rem;
    font-weight: 700;
}

.materials-v2-automatic-tag-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    min-width: 0;
}

.materials-v2-automatic-tag-label {
    white-space: nowrap;
}

.materials-v2-automatic-tag-names {
    font-weight: 400;
}

.materials-v2-automatic-tag-editor {
    margin-top: 0.35rem;
    padding-top: 0.35rem;
    border-top: 1px solid rgba(23, 45, 59, 0.06);
}

.materials-v2-automatic-tag-editor-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: var(--materials-v2-muted);
    font-size: 0.65rem;
    font-weight: 650;
}

.materials-v2-automatic-tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.3rem;
}

.materials-v2-automatic-tag-row {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    min-height: 28px;
    padding: 0.1rem 0.15rem 0.1rem 0.25rem;
    border: 1px solid rgba(23, 45, 59, 0.07);
    border-radius: 999px;
    background: rgba(var(--v-theme-surface), 0.65);
}

.materials-v2-automatic-tag-meta {
    color: var(--materials-v2-muted);
    font-size: 0.65rem;
    white-space: nowrap;
}

.materials-v2-automatic-tag-empty {
    margin: 0.2rem 0 0;
    color: var(--materials-v2-muted);
    font-size: 0.7rem;
}

.materials-v2-tag-status-list {
    margin-top: 0.4rem;
    padding-top: 0.25rem;
    border-top: 1px solid rgba(23, 45, 59, 0.06);
}

.materials-v2-tag-status-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.3rem;
    min-height: 24px;
    color: var(--materials-v2-muted);
    font-size: 0.7rem;
}

.materials-v2-tag-status-name {
    overflow: hidden;
    max-width: 240px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-screenshot-input {
    margin-top: 0.75rem;
}

.materials-v2-screenshot-paste-zone {
    display: flex;
    min-height: 180px;
    padding: 1rem;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 0.45rem;
    overflow: hidden;
    border: 2px dashed rgba(var(--v-theme-primary), 0.45);
    border-radius: 16px;
    background: rgba(var(--v-theme-primary), 0.045);
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
    text-align: center;
}

.materials-v2-screenshot-paste-zone:focus-visible {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.14);
    outline: none;
}

.materials-v2-screenshot-paste-zone span {
    color: var(--materials-v2-muted);
    font-size: 0.8rem;
}

.materials-v2-screenshot-preview {
    display: block;
    max-width: 100%;
    max-height: 320px;
    border-radius: 10px;
    object-fit: contain;
}

.materials-v2-link-input {
    margin-top: 0.75rem;
}

.materials-v2-link-paste-zone {
    display: flex;
    min-height: 140px;
    padding: 1rem;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 0.45rem;
    overflow: hidden;
    border: 2px dashed rgba(0, 137, 123, 0.42);
    border-radius: 16px;
    background: rgba(0, 137, 123, 0.045);
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
    text-align: center;
}

.materials-v2-link-paste-zone:focus-visible {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.14);
    outline: none;
}

.materials-v2-link-paste-zone strong {
    overflow: hidden;
    max-width: 100%;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-link-paste-zone span {
    color: var(--materials-v2-muted);
    font-size: 0.8rem;
}

.materials-v2-link-preview {
    display: inline-flex;
    margin-top: 0.25rem;
    align-items: center;
    gap: 0.25rem;
    color: rgb(var(--v-theme-primary));
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
}

.materials-v2-link-preview:hover {
    text-decoration: underline;
}

.materials-v2-link-status {
    margin-top: -0.5rem;
}

.materials-v2-edit-attachments {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(23, 45, 59, 0.08);
}

.materials-v2-edit-attachments-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.materials-v2-edit-attachment-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-top: 0.4rem;
}

.materials-v2-edit-attachments-empty {
    margin: 0.25rem 0 0;
    color: var(--materials-v2-muted);
    font-size: 0.72rem;
}

.materials-v2-attachments {
    margin-top: 1.15rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(23, 45, 59, 0.08);
}

.materials-v2-card--standard .materials-v2-attachments {
    margin-top: 0.85rem;
    padding-top: 0.75rem;
}

.materials-v2-card--compact .materials-v2-attachments {
    margin-top: 0.65rem;
    padding-top: 0.6rem;
}

.materials-v2-file-browser {
    margin-top: 0.9rem;
    padding: 0.75rem;
    border: 1px solid rgba(49, 105, 171, 0.14);
    border-radius: 14px;
    background: rgba(49, 105, 171, 0.045);
}

.materials-v2-card--standard .materials-v2-file-browser,
.materials-v2-card--compact .materials-v2-file-browser {
    padding: 0.7rem;
}

.materials-v2-file-browser .materials-v2-attachment-heading {
    color: #315f94;
}

.materials-v2-attachment-heading {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--materials-v2-muted);
    font-size: 0.78rem;
    font-weight: 700;
}

.materials-v2-attachment-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    padding: 0.5rem 0.6rem;
    border: 1px solid rgba(23, 45, 59, 0.055);
    border-radius: 12px;
    background: rgba(246, 248, 248, 0.9);
}

.materials-v2-file-browser .materials-v2-attachment-row {
    padding: 0.55rem;
    border-color: rgba(49, 105, 171, 0.11);
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 3px 10px rgba(49, 105, 171, 0.055);
}

.materials-v2-attachment-icon {
    display: grid;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    place-items: center;
    border-radius: 9px;
    background: rgba(var(--v-theme-primary), 0.08);
}

.materials-v2-attachment-name {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    color: var(--materials-v2-ink);
    font-size: 0.82rem;
    font-weight: 650;
    text-align: left;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-attachment-name:hover {
    color: var(--materials-v2-accent);
    text-decoration: underline;
}

.materials-v2-file-size {
    color: var(--materials-v2-muted);
    font-size: 0.7rem;
    white-space: nowrap;
}

.materials-v2-file-kind {
    padding: 0.12rem 0.35rem;
    border-radius: 5px;
    background: rgba(49, 105, 171, 0.09);
    color: #315f94;
    font-size: 0.58rem;
    font-weight: 850;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.materials-v2-empty {
    display: flex;
    min-height: 330px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 3rem;
    border: 1px dashed rgba(23, 45, 59, 0.2);
    background: rgba(255, 255, 255, 0.7);
    text-align: center;
}

.materials-v2-empty h2 {
    margin: 1rem 0 0.4rem;
    font-size: 1.35rem;
}

.materials-v2-empty p {
    max-width: 520px;
    margin: 0 0 1.25rem;
    color: var(--materials-v2-muted);
}

.materials-v2-dialog-title {
    display: flex;
    align-items: center;
    padding: 1.4rem 1.5rem 1rem;
    font-weight: 760;
}

.materials-v2-preview-card {
    height: min(88vh, 900px);
}

.materials-v2-preview-title {
    flex: 0 0 auto;
    gap: 0.25rem;
}

.materials-v2-preview-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-preview-body {
    display: flex;
    min-height: 0;
    padding: 0 !important;
    background: #eef2f4;
}

.materials-v2-preview-frame {
    width: 100%;
    height: 100%;
    min-height: 65vh;
    border: 0;
    background: #fff;
}

.min-width-0 {
    min-width: 0;
}

@media (max-width: 900px) {
    .materials-v2-workspace {
        grid-template-columns: 220px minmax(0, 1fr);
    }
}

@media (max-width: 700px) {
    .materials-v2-system-tabs {
        padding: 0 6px;
    }

    .materials-v2-system-tab {
        padding: 0 0.75rem;
    }

    .materials-v2-workspace {
        grid-template-columns: minmax(0, 1fr);
    }

    .materials-v2-category-panel {
        padding: 0.8rem;
        border-right: 0;
        border-bottom: 1px solid rgba(23, 45, 59, 0.08);
    }

    .materials-v2-category-list {
        max-height: 190px;
    }

    .materials-v2-main {
        padding: 0.75rem 0.85rem 1.5rem;
    }

    .materials-v2-toolbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 1rem 0;
    }

    .materials-v2-toolbar-actions {
        width: 100%;
        align-items: stretch;
        justify-content: flex-start;
        flex-direction: column;
    }

    .materials-v2-display-toggle {
        width: 100%;
    }

    .materials-v2-display-toggle :deep(.v-btn) {
        min-width: 0;
        flex: 1 1 0;
    }

    .materials-v2-file-size {
        display: none;
    }
}
</style>
