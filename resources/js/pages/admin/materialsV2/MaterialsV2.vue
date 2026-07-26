<template>
    <v-container fluid class="materials-v2-page pa-3 pa-md-6">
        <div class="materials-v2-orb materials-v2-orb--one" />
        <div class="materials-v2-orb materials-v2-orb--two" />

        <div class="materials-v2-content">
            <header class="materials-v2-header">
                <div>
                    <div class="materials-v2-eyebrow">Einfach speichern. Intelligent finden.</div>
                    <h1 class="materials-v2-title">Materialien 2</h1>
                    <p class="materials-v2-subtitle">
                        Dein Material, seine Inhalte, flexible Kategorien und eine Suche, die mitdenkt.
                    </p>
                </div>

                <v-btn
                    color="primary"
                    size="large"
                    rounded="xl"
                    elevation="0"
                    prepend-icon="mdi-plus"
                    @click="openCreateDialog">
                    Material erstellen
                </v-btn>
            </header>

            <div class="materials-v2-filter-layout">
                <v-sheet rounded="xl" class="materials-v2-category-panel pa-4">
                    <div class="materials-v2-category-panel-heading">
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">Kategorien</div>
                            <div class="text-caption materials-v2-category-panel-subtitle">
                                Wähle eine Kategorie für deine Materialliste.
                            </div>
                        </div>
                        <div class="materials-v2-category-panel-actions">
                            <v-chip size="small" color="primary" variant="tonal">
                                {{ categoryOptions.length }}
                            </v-chip>
                            <v-btn
                                class="materials-v2-category-create-button"
                                color="primary"
                                size="small"
                                variant="tonal"
                                prepend-icon="mdi-plus"
                                @click="openCategoryDialog()">
                                Kategorie
                            </v-btn>
                        </div>
                    </div>

                    <v-list
                        bg-color="transparent"
                        density="compact"
                        class="materials-v2-category-list py-0"
                        aria-label="Materialien nach Kategorie filtern">
                        <v-list-item
                            class="materials-v2-category-item mb-2 px-3"
                            min-height="44"
                            rounded="lg"
                            :active="selectedCategory === allCategoriesValue"
                            color="primary"
                            @click="selectCategory(allCategoriesValue)">
                            <template #prepend>
                                <v-icon size="18" class="mr-2">mdi-view-grid-outline</v-icon>
                            </template>
                            <v-list-item-title class="text-body-2 font-weight-bold">
                                Alle Materialien
                            </v-list-item-title>
                            <template v-if="selectedCategory === allCategoriesValue" #append>
                                <v-icon size="18">mdi-check-circle</v-icon>
                            </template>
                        </v-list-item>

                        <v-list-item
                            v-for="category in categoryDetails"
                            :key="category.name"
                            class="materials-v2-category-item mb-2 px-3"
                            min-height="44"
                            rounded="lg"
                            :active="selectedCategory === category.name"
                            color="primary"
                            @click="selectCategory(category.name)">
                            <template #prepend>
                                <v-icon size="18" class="mr-2">mdi-shape-outline</v-icon>
                            </template>
                            <v-list-item-title class="text-body-2 font-weight-bold">
                                {{ category.name }}
                            </v-list-item-title>
                            <template #append>
                                <div class="materials-v2-category-item-actions">
                                    <v-chip
                                        class="materials-v2-category-item-count"
                                        size="x-small"
                                        color="secondary"
                                        variant="tonal"
                                        :title="`${category.items_count} Items`">
                                        {{ category.items_count }}
                                    </v-chip>
                                    <v-icon v-if="selectedCategory === category.name" size="18">
                                        mdi-check-circle
                                    </v-icon>
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
                </v-sheet>

                <section class="materials-v2-search-panel">
                    <v-text-field
                        v-model="search"
                        class="materials-v2-search"
                        variant="solo"
                        flat
                        rounded="xl"
                        clearable
                        hide-details
                        autocomplete="off"
                        prepend-inner-icon="mdi-magnify"
                        placeholder="Was suchst du? Mehrere Wörter, Wortteile und kleine Tippfehler sind erlaubt …"
                        :loading="loading"
                        @click:clear="clearSearch" />

                    <div class="materials-v2-search-hints">
                        <span><v-icon size="16">mdi-auto-fix</v-icon> flexibel & fehlertolerant</span>
                        <span><v-icon size="16">mdi-file-document-search-outline</v-icon> durchsucht Dokumentinhalte</span>
                        <span><v-icon size="16">mdi-sort-descending</v-icon> beste Treffer zuerst</span>
                    </div>
                </section>
            </div>

            <div class="materials-v2-toolbar">
                <div>
                    <span class="text-subtitle-1 font-weight-bold">
                        {{ hasActiveFilters ? 'Suchergebnisse' : 'Deine Materialien' }}
                    </span>
                    <span v-if="!loading" class="text-body-2 text-medium-emphasis ml-2">
                        {{ meta.total }} {{ meta.total === 1 ? 'Material' : 'Materialien' }}
                    </span>
                </div>

                <div class="materials-v2-toolbar-actions">
                    <v-btn-toggle
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

                    <v-chip v-if="hasProcessingItems" color="info" variant="tonal" prepend-icon="mdi-progress-clock">
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

            <v-row v-if="loading && !items.length" dense>
                <v-col v-for="index in 6" :key="index" v-bind="materialColumnProps">
                    <v-skeleton-loader
                        :class="['materials-v2-card', `materials-v2-card--${displayMode}`]"
                        type="article, actions" />
                </v-col>
            </v-row>

            <v-row v-else-if="items.length" dense>
                <v-col v-for="item in items" :key="item.id" v-bind="materialColumnProps">
                    <v-card
                        :class="['materials-v2-card', `materials-v2-card--${displayMode}`, 'h-100']"
                        :rounded="displayMode === 'large' ? 'xl' : 'lg'"
                        elevation="0">
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
                                            prepend-icon="mdi-shape-outline">
                                            {{ item.category }}
                                        </v-chip>
                                    </div>
                                </div>

                                <v-menu>
                                    <template #activator="{ props }">
                                        <v-btn v-bind="props" icon="mdi-dots-horizontal" size="small" variant="text" />
                                    </template>
                                    <v-list density="compact">
                                        <v-list-item prepend-icon="mdi-pencil-outline" title="Bearbeiten" @click="openEditDialog(item)" />
                                        <v-list-item prepend-icon="mdi-paperclip-plus" title="Anlagen hinzufügen" @click="openAttachmentDialog(item)" />
                                        <v-list-item
                                            prepend-icon="mdi-refresh"
                                            title="Automatische Tags neu berechnen"
                                            @click="recalculateAutomaticTags(item)" />
                                        <v-list-item
                                            v-if="['failed', 'partial'].includes(item.processing_status)"
                                            prepend-icon="mdi-file-refresh-outline"
                                            title="Dateiverarbeitung wiederholen"
                                            @click="retryProcessing(item)" />
                                        <v-divider />
                                        <v-list-item
                                            prepend-icon="mdi-delete-outline"
                                            title="Löschen"
                                            base-color="error"
                                            @click="openDeleteDialog(item)" />
                                    </v-list>
                                </v-menu>
                            </div>

                            <p v-if="displayMode !== 'compact' && item.description" class="materials-v2-description">
                                {{ item.description }}
                            </p>
                            <p
                                v-else-if="displayMode === 'large'"
                                class="materials-v2-description materials-v2-description--empty">
                                Keine Beschreibung
                            </p>

                            <v-alert
                                v-if="item.processing_error"
                                class="mb-4"
                                density="compact"
                                type="warning"
                                variant="tonal">
                                {{ item.processing_error }}
                            </v-alert>

                            <div
                                v-if="displayMode !== 'compact' && item.user_keywords?.length"
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
                                        v-if="displayMode === 'standard' && item.user_keywords.length > 4"
                                        size="small"
                                        color="secondary"
                                        variant="text">
                                        +{{ item.user_keywords.length - 4 }}
                                    </v-chip>
                                </div>
                            </div>

                            <div
                                v-if="displayMode === 'large' && item.automatic_tag_suggestions?.length"
                                class="materials-v2-keyword-block">
                                <div class="materials-v2-keyword-label">
                                    <v-icon size="15" class="mr-1">mdi-sparkles</v-icon>
                                    Aus dem Inhalt erkannt
                                </div>
                                <div class="d-flex flex-wrap ga-1">
                                    <v-chip
                                        v-for="suggestion in item.automatic_tag_suggestions.slice(0, 8)"
                                        :key="`generated-${item.id}-${suggestion.name}`"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        :title="`Rang ${suggestion.rank} · ${suggestion.score} Punkte`">
                                        {{ suggestion.name }}
                                    </v-chip>
                                </div>
                            </div>

                            <div class="materials-v2-attachments">
                                <div class="materials-v2-attachment-heading">
                                    <v-icon size="18">mdi-paperclip</v-icon>
                                    {{ item.attachments?.length || 0 }}
                                    {{ item.attachments?.length === 1 ? 'Anlage' : 'Anlagen' }}
                                </div>

                                <div
                                    v-if="displayMode === 'large' && item.attachments?.length"
                                    class="d-flex flex-column ga-2 mt-2">
                                    <div
                                        v-for="attachment in item.attachments"
                                        :key="attachment.id"
                                        class="materials-v2-attachment-row">
                                        <v-icon size="20" color="primary">{{ attachmentIcon(attachment) }}</v-icon>
                                        <button type="button" class="materials-v2-attachment-name" @click="previewAttachment(attachment)">
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
                    {{ hasActiveFilters ? 'mdi-file-search-outline' : 'mdi-folder-plus-outline' }}
                </v-icon>
                <h2>{{ hasActiveFilters ? 'Noch kein passender Treffer' : 'Deine neue Materialsammlung ist leer' }}</h2>
                <p>
                    {{
                        hasActiveFilters
                            ? 'Versuche andere Begriffe oder nur einen Wortteil.'
                            : 'Erstelle dein erstes Material – mit oder ohne Kategorie.'
                    }}
                </p>
                <v-btn v-if="!hasActiveFilters" color="primary" rounded="xl" prepend-icon="mdi-plus" @click="openCreateDialog">
                    Erstes Material erstellen
                </v-btn>
            </v-card>

            <div v-if="meta.last_page > 1" class="d-flex justify-center mt-7">
                <v-pagination
                    v-model="page"
                    :length="meta.last_page"
                    :total-visible="7"
                    rounded="circle"
                    @update:model-value="loadItems" />
            </div>
        </div>

        <v-dialog v-model="materialDialog.open" persistent max-width="720">
            <v-card rounded="xl">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="primary" class="mr-2">
                        {{ materialDialog.mode === 'create' ? 'mdi-file-plus-outline' : 'mdi-file-edit-outline' }}
                    </v-icon>
                    {{ materialDialog.mode === 'create' ? 'Material erstellen' : 'Material bearbeiten' }}
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
                                :error-messages="formErrors.title"
                                :disabled="materialDialog.saving" />
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-combobox
                                v-model="materialForm.category"
                                label="Kategorie (optional)"
                                variant="outlined"
                                maxlength="255"
                                clearable
                                :items="categoryOptions"
                                :return-object="false"
                                :error-messages="formErrors.category"
                                :disabled="materialDialog.saving"
                                hint="Bestehende Kategorie wählen oder eine neue eingeben"
                                persistent-hint />
                        </v-col>
                    </v-row>
                    <v-textarea
                        v-model="materialForm.description"
                        class="mt-2"
                        label="Beschreibung (optional)"
                        variant="outlined"
                        rows="3"
                        auto-grow
                        maxlength="10000"
                        :error-messages="formErrors.description"
                        :disabled="materialDialog.saving" />
                    <v-text-field
                        v-model="materialForm.keywords"
                        class="mt-2"
                        label="Eigene Suchwörter (optional)"
                        hint="Mit Komma trennen, z. B. Bruchrechnen, Übung, 2. Klasse"
                        persistent-hint
                        variant="outlined"
                        prepend-inner-icon="mdi-tag-multiple-outline"
                        :error-messages="formErrors.user_keywords"
                        :disabled="materialDialog.saving" />
                    <section
                        v-if="materialDialog.mode === 'edit'"
                        class="materials-v2-automatic-tag-review mt-5">
                        <div class="d-flex align-center justify-space-between ga-3 mb-3">
                            <div>
                                <div class="text-subtitle-2 font-weight-bold">Automatisch erkannte Tags</div>
                                <div class="text-caption text-medium-emphasis">
                                    Lokal aus dem Dokumentinhalt ermittelt. Eigene Suchwörter bleiben unverändert.
                                </div>
                            </div>
                            <v-btn
                                size="small"
                                variant="tonal"
                                color="primary"
                                prepend-icon="mdi-refresh"
                                :loading="tagAction.type === 'recalculate'"
                                @click="recalculateAutomaticTags(materialDialog.item)">
                                Neu berechnen
                            </v-btn>
                        </div>

                        <div
                            v-if="materialDialog.item?.automatic_tag_suggestions?.length"
                            class="d-flex flex-column ga-2">
                            <div
                                v-for="suggestion in materialDialog.item.automatic_tag_suggestions"
                                :key="`review-${suggestion.attachment_id}-${suggestion.name}`"
                                class="materials-v2-automatic-tag-row">
                                <v-chip color="primary" variant="tonal" size="small">
                                    {{ suggestion.rank }}. {{ suggestion.name }}
                                </v-chip>
                                <span class="text-caption text-medium-emphasis">
                                    {{ suggestion.score }} Punkte · {{ suggestion.language.toUpperCase() }}
                                </span>
                                <v-spacer />
                                <v-btn
                                    icon="mdi-tag-plus-outline"
                                    size="x-small"
                                    variant="text"
                                    color="secondary"
                                    title="Als eigenes Suchwort übernehmen"
                                    :loading="isTagAction('convert', suggestion.name)"
                                    @click="convertAutomaticTag(materialDialog.item, suggestion)" />
                                <v-btn
                                    icon="mdi-close"
                                    size="x-small"
                                    variant="text"
                                    color="error"
                                    title="Automatischen Tag entfernen"
                                    :loading="isTagAction('remove', suggestion.name)"
                                    @click="removeAutomaticTag(materialDialog.item, suggestion)" />
                            </div>
                        </div>
                        <v-alert v-else type="info" variant="tonal" density="compact">
                            Für dieses Material wurden keine ausreichend relevanten automatischen Tags gefunden.
                        </v-alert>

                        <div v-if="materialDialog.item?.attachments?.length" class="mt-4">
                            <div
                                v-for="attachment in materialDialog.item.attachments"
                                :key="`tag-status-${attachment.id}`"
                                class="materials-v2-tag-status-row">
                                <span class="text-body-2">{{ attachment.original_name }}</span>
                                <v-chip
                                    size="x-small"
                                    :color="keywordStatusMeta(attachment.keyword_extraction_status).color"
                                    variant="tonal">
                                    {{ keywordStatusMeta(attachment.keyword_extraction_status).label }}
                                </v-chip>
                                <span v-if="attachment.keywords_extracted_at" class="text-caption text-medium-emphasis">
                                    {{ formatDateTime(attachment.keywords_extracted_at) }}
                                </span>
                                <div
                                    v-if="attachment.keyword_extraction_error"
                                    class="text-caption text-error flex-1-1-100">
                                    {{ attachment.keyword_extraction_error }}
                                </div>
                            </div>
                        </div>
                    </section>
                    <v-file-input
                        v-if="materialDialog.mode === 'create'"
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
                    <v-alert class="mt-4" type="info" variant="tonal" density="compact">
                        Dokumente werden nach dem Speichern lokal gelesen. Nur ausreichend relevante Themenbegriffe
                        werden als zusätzliche Tags vorgeschlagen.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="materialDialog.saving" @click="closeMaterialDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        rounded="lg"
                        :loading="materialDialog.saving"
                        :disabled="!materialForm.title.trim()"
                        @click="saveMaterial()">
                        {{ materialDialog.mode === 'create' ? 'Erstellen' : 'Speichern' }}
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
                    <iframe
                        v-if="previewDialog.attachment?.preview_url"
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
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const notification = useNotificationStore()

const allCategoriesValue = '__all_categories__'
const displayModeStorageKey = 'materials-v2-display-mode'
const displayModeOptions = ['large', 'standard', 'compact']
const search = ref('')
const selectedCategory = ref(allCategoriesValue)
const displayMode = ref(loadStoredDisplayMode())
const page = ref(1)
const items = ref([])
const categoryDetails = ref([])
const loading = ref(false)
const loadError = ref('')
const meta = reactive({
    total: 0,
    current_page: 1,
    last_page: 1,
})
const materialDialog = reactive({
    open: false,
    mode: 'create',
    item: null,
    saving: false,
})
const materialForm = reactive({
    title: '',
    category: '',
    description: '',
    keywords: '',
    attachments: [],
})
const formErrors = reactive({
    title: [],
    category: [],
    description: [],
    user_keywords: [],
    attachments: [],
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
const categorySuggestionDialog = reactive({
    open: false,
    entered: '',
    existing: '',
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
let pollingTimer = null
let isResettingFiltersAfterCreate = false

const hasProcessingItems = computed(() =>
    items.value.some((item) => ['pending', 'processing'].includes(item.processing_status)),
)
const categoryOptions = computed(() => categoryDetails.value.map((category) => category.name))
const hasActiveFilters = computed(
    () => search.value.trim() !== '' || selectedCategory.value !== allCategoriesValue,
)
const materialColumnProps = computed(() => ({
    large: { cols: 12, md: 6, xl: 4 },
    standard: { cols: 12, sm: 6, lg: 4, xl: 3 },
    compact: { cols: 12, sm: 6, md: 4, lg: 3, xl: 2 },
}[displayMode.value]))
const materialCardPaddingClass = computed(() => ({
    large: 'pa-5',
    standard: 'pa-4',
    compact: 'pa-3',
}[displayMode.value]))

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

watch(selectedCategory, () => {
    if (isResettingFiltersAfterCreate) {
        return
    }

    page.value = 1
    loadItems()
})

watch(displayMode, (mode) => {
    persistDisplayMode(mode)
})

watch(hasProcessingItems, (isProcessing) => {
    configurePolling(isProcessing)
})

onMounted(async () => {
    await Promise.all([loadConfig(), loadItems()])
})

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer)
    window.clearInterval(pollingTimer)
})

async function loadItems() {
    loading.value = true
    loadError.value = ''

    try {
        const response = await axios.get('/api/admin/materials-v2/items', {
            params: {
                search: search.value.trim() || undefined,
                category: selectedCategory.value === allCategoriesValue ? undefined : selectedCategory.value,
                page: page.value,
                per_page: 18,
            },
        })

        items.value = response.data?.data || []
        if (materialDialog.open && materialDialog.mode === 'edit' && materialDialog.item) {
            const refreshedItem = items.value.find((item) => item.id === materialDialog.item.id)
            if (refreshedItem) {
                materialDialog.item = refreshedItem
            }
        }
        Object.assign(meta, response.data?.meta || {
            total: items.value.length,
            current_page: 1,
            last_page: 1,
        })
    } catch (error) {
        loadError.value = apiErrorMessage(error, 'Die Materialien konnten nicht geladen werden.')
    } finally {
        loading.value = false
    }
}

async function loadConfig() {
    try {
        const response = await axios.get('/api/admin/materials-v2/config')
        const categories = Array.isArray(response.data?.categories) ? response.data.categories : []
        categoryDetails.value = Array.isArray(response.data?.category_details)
            ? response.data.category_details
            : categories.map((name) => ({ name, items_count: null }))
    } catch {
        categoryDetails.value = []
    }
}

function clearSearch() {
    search.value = ''
    page.value = 1
    loadItems()
}

function selectCategory(category) {
    selectedCategory.value = category
}

function loadStoredDisplayMode() {
    try {
        const storedMode = window.localStorage.getItem(displayModeStorageKey)

        return displayModeOptions.includes(storedMode) ? storedMode : 'large'
    } catch {
        return 'large'
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

    return displayMode.value === 'large' ? keywords : keywords.slice(0, 4)
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
        await axios.delete('/api/admin/materials-v2/categories', {
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
            ? await axios.put('/api/admin/materials-v2/categories', {
                original_name: categoryDialog.originalName,
                name: categoryName,
            })
            : await axios.post('/api/admin/materials-v2/categories', {
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
    materialDialog.mode = 'create'
    materialDialog.item = null
    materialDialog.open = true
}

function openEditDialog(item) {
    resetForm()
    materialDialog.mode = 'edit'
    materialDialog.item = item
    materialForm.title = item.title || ''
    materialForm.category = item.category || ''
    materialForm.description = item.description || ''
    materialForm.keywords = (item.user_keywords || []).join(', ')
    materialDialog.open = true
}

function closeMaterialDialog() {
    if (!materialDialog.saving) {
        materialDialog.open = false
    }
}

async function saveMaterial({ forceNewCategory = false } = {}) {
    clearFormErrors()
    materialDialog.saving = true
    const isCreating = materialDialog.mode === 'create'

    try {
        if (isCreating) {
            const payload = new FormData()
            payload.append('title', materialForm.title.trim())

            if (normalizedCategory()) {
                payload.append('category', normalizedCategory())
            }

            if (forceNewCategory) {
                payload.append('force_new_category', '1')
            }

            if (materialForm.description.trim()) {
                payload.append('description', materialForm.description.trim())
            }

            normalizedKeywords().forEach((keyword) => payload.append('user_keywords[]', keyword))
            const attachments = materialForm.attachments || []
            attachments.forEach((file) => payload.append('attachments[]', file))

            await axios.post('/api/admin/materials-v2/items', payload)
        } else {
            await axios.put(`/api/admin/materials-v2/items/${materialDialog.item.id}`, {
                title: materialForm.title.trim(),
                category: normalizedCategory() || null,
                force_new_category: forceNewCategory,
                description: materialForm.description.trim() || null,
                user_keywords: normalizedKeywords(),
            })
        }

        materialDialog.open = false

        if (isCreating) {
            await resetFiltersAfterCreate()
        } else {
            page.value = 1
        }

        await Promise.all([loadItems(), loadConfig()])
        notify('Material gespeichert.')
    } catch (error) {
        if (showCategorySuggestion(error)) {
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

async function resetFiltersAfterCreate() {
    window.clearTimeout(searchTimer)
    isResettingFiltersAfterCreate = true
    search.value = ''
    selectedCategory.value = allCategoriesValue
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
        await axios.post(`/api/admin/materials-v2/items/${attachmentDialog.item.id}/attachments`, payload)

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
        await axios.delete(`/api/admin/materials-v2/attachments/${attachment.id}`)
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
        await axios.delete(`/api/admin/materials-v2/items/${deleteDialog.item.id}`)
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
        await axios.post(`/api/admin/materials-v2/items/${item.id}/retry-processing`)
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
        await axios.post(`/api/admin/materials-v2/items/${item.id}/recalculate-automatic-tags`)
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
        const response = await axios.delete(`/api/admin/materials-v2/items/${item.id}/automatic-tags`, {
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
        const response = await axios.post(`/api/admin/materials-v2/items/${item.id}/automatic-tags/convert`, {
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

function configurePolling(isProcessing) {
    window.clearInterval(pollingTimer)
    pollingTimer = null

    if (isProcessing) {
        pollingTimer = window.setInterval(() => {
            if (!loading.value) {
                loadItems()
            }
        }, 4000)
    }
}

function resetForm() {
    materialForm.title = ''
    materialForm.category = ''
    materialForm.description = ''
    materialForm.keywords = ''
    materialForm.attachments = []
    clearFormErrors()
}

function clearFormErrors() {
    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = []
    })
}

function applyValidationErrors(error) {
    formErrors.title = validationMessages(error, 'title')
    formErrors.category = validationMessages(error, 'category')
    formErrors.description = validationMessages(error, 'description')
    formErrors.user_keywords = validationMessages(error, 'user_keywords')
    formErrors.attachments = [
        ...validationMessages(error, 'attachments'),
        ...validationMessages(error, 'attachments.0'),
    ]
}

function normalizedCategory() {
    return String(materialForm.category || '').trim()
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

function statusMeta(status) {
    return {
        pending: { label: 'Wartet', color: 'info', icon: 'mdi-clock-outline' },
        processing: { label: 'Wird analysiert', color: 'info', icon: 'mdi-progress-clock' },
        ready: { label: 'Bereit', color: 'success', icon: 'mdi-check-circle-outline' },
        partial: { label: 'Teilweise gelesen', color: 'warning', icon: 'mdi-alert-circle-outline' },
        failed: { label: 'Fehlgeschlagen', color: 'error', icon: 'mdi-alert-outline' },
    }[status] || { label: status || 'Unbekannt', color: 'default', icon: 'mdi-help-circle-outline' }
}

function keywordStatusMeta(status) {
    return {
        pending: { label: 'Wartet auf Analyse', color: 'info' },
        processing: { label: 'Tags werden ermittelt', color: 'info' },
        ready: { label: 'Tags erkannt', color: 'success' },
        empty: { label: 'Keine relevanten Tags', color: 'warning' },
        skipped: { label: 'Nicht auswertbar', color: 'warning' },
        failed: { label: 'Tag-Erkennung fehlgeschlagen', color: 'error' },
    }[status] || { label: status || 'Noch nicht verarbeitet', color: 'default' }
}

function attachmentIcon(attachment) {
    const mimeType = String(attachment.mime_type || '').toLowerCase()
    const name = String(attachment.original_name || '').toLowerCase()

    if (mimeType.includes('pdf') || name.endsWith('.pdf')) return 'mdi-file-pdf-box'
    if (mimeType.includes('word') || name.endsWith('.docx')) return 'mdi-file-word-outline'
    if (mimeType.includes('sheet') || name.endsWith('.xlsx')) return 'mdi-file-excel-outline'
    if (mimeType.includes('presentation') || name.endsWith('.pptx')) return 'mdi-file-powerpoint-outline'
    if (mimeType.startsWith('image/')) return 'mdi-file-image-outline'
    return 'mdi-file-document-outline'
}

function formatFileSize(bytes) {
    const size = Number(bytes || 0)
    if (size < 1024) return `${size} B`
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
    return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

function formatDateTime(value) {
    const date = new Date(value)

    return Number.isNaN(date.getTime())
        ? ''
        : new Intl.DateTimeFormat('de-AT', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(date)
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
    margin: 0 auto;
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

.materials-v2-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 2rem;
    padding: 1.5rem 0 1.8rem;
}

.materials-v2-eyebrow {
    margin-bottom: 0.35rem;
    color: var(--materials-v2-accent);
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.materials-v2-title {
    margin: 0;
    font-size: clamp(2.2rem, 5vw, 4.4rem);
    font-weight: 850;
    letter-spacing: -0.055em;
    line-height: 1;
}

.materials-v2-subtitle {
    max-width: 780px;
    margin: 0.85rem 0 0;
    color: var(--materials-v2-muted);
    font-size: clamp(1rem, 1.7vw, 1.2rem);
    line-height: 1.55;
}

.materials-v2-filter-layout {
    display: grid;
    grid-template-columns: minmax(260px, 0.8fr) minmax(0, 2fr);
    gap: 1rem;
    align-items: start;
}

.materials-v2-category-panel {
    border: 1px solid rgba(23, 45, 59, 0.1);
    background:
        radial-gradient(circle at top right, rgba(47, 191, 145, 0.15), transparent 52%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.95), rgba(240, 253, 250, 0.92));
    box-shadow:
        0 16px 42px rgba(23, 45, 59, 0.09),
        inset 0 1px 0 rgba(255, 255, 255, 0.75);
}

.materials-v2-category-panel-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.8rem;
}

.materials-v2-category-panel-subtitle {
    color: var(--materials-v2-muted);
}

.materials-v2-category-panel-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.materials-v2-category-list {
    max-height: 320px;
    overflow-y: auto;
}

.materials-v2-category-item {
    border: 1px solid rgba(23, 45, 59, 0.08);
    background: rgba(255, 255, 255, 0.84);
    cursor: pointer;
}

.materials-v2-category-item.v-list-item--active {
    border-color: rgba(47, 191, 145, 0.42);
}

.materials-v2-category-item-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.materials-v2-search-panel {
    padding: 1rem;
    border: 1px solid rgba(23, 45, 59, 0.08);
    border-radius: 26px;
    background: rgba(255, 255, 255, 0.84);
    box-shadow: 0 22px 65px rgba(23, 45, 59, 0.09);
    backdrop-filter: blur(18px);
}

.materials-v2-search :deep(.v-field) {
    min-height: 64px;
    border: 2px solid rgba(23, 45, 59, 0.09);
    background: #fff;
    box-shadow: none;
    font-size: 1.05rem;
    transition: border-color 180ms ease, box-shadow 180ms ease;
}

.materials-v2-search :deep(.v-field--focused) {
    border-color: var(--materials-v2-accent);
    box-shadow: 0 0 0 5px rgba(255, 122, 50, 0.11);
}

.materials-v2-search-hints {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem 1.2rem;
    padding: 0.75rem 0.6rem 0.1rem;
    color: var(--materials-v2-muted);
    font-size: 0.78rem;
}

.materials-v2-search-hints span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.materials-v2-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 68px;
    gap: 1rem;
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
    border: 1px solid rgba(23, 45, 59, 0.08);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 12px 40px rgba(23, 45, 59, 0.07);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
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
    padding: 1rem;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
    border-radius: 14px;
    background: rgba(var(--v-theme-primary), 0.035);
}

.materials-v2-automatic-tag-row,
.materials-v2-tag-status-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.materials-v2-automatic-tag-row {
    min-height: 36px;
}

.materials-v2-tag-status-row {
    padding: 0.55rem 0;
    border-top: 1px solid rgba(23, 45, 59, 0.08);
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
    border-radius: 12px;
    background: #f6f8f8;
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

@media (max-width: 700px) {
    .materials-v2-filter-layout {
        grid-template-columns: minmax(0, 1fr);
    }

    .materials-v2-header {
        align-items: stretch;
        flex-direction: column;
        gap: 1.2rem;
    }

    .materials-v2-search-hints {
        align-items: flex-start;
        flex-direction: column;
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
