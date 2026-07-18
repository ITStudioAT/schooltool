<template>
    <div class="curriculum-detail">
        <div class="curriculum-detail__header mb-4">
            <div class="curriculum-detail__header-actions">
                <v-btn
                    variant="tonal"
                    color="secondary"
                    size="small"
                    rounded="xl"
                    prepend-icon="mdi-arrow-left"
                    class="text-none"
                    :disabled="isPageActionLocked"
                    @click="$emit('back')">
                    Zurück zur Übersicht
                </v-btn>
                <v-btn
                    size="small"
                    variant="tonal"
                    color="primary"
                    rounded="xl"
                    prepend-icon="mdi-download-outline"
                    class="text-none"
                    :loading="isExportingCurriculum"
                    :disabled="isExportingCurriculum"
                    @click="exportCurriculum">
                    Curriculum exportieren
                </v-btn>
            </div>
            <div class="curriculum-detail__title-row">
                <div>
                    <div class="curriculum-detail__eyebrow">Curriculum</div>
                    <h2 class="curriculum-detail__title">{{ curriculum.title }}</h2>
                    <p v-if="curriculum.description" class="curriculum-detail__desc">{{ curriculum.description }}</p>
                </div>
                <div class="curriculum-detail__summary" aria-label="Curriculum-Umfang">
                    <v-icon size="18" icon="mdi-format-list-numbered" />
                    {{ curriculumTopics.length }} Themen · {{ curriculumUnitCount }} Einheiten
                </div>
            </div>
        </div>

        <div class="curriculum-detail__body">
            <v-sheet rounded="xl" class="curriculum-detail__side-card curriculum-detail__side-card--content">
                <div class="curriculum-detail__side-card-inner">
                    <div class="curriculum-detail__side-card-header">
                        <v-icon size="20" color="#a5b4fc" class="mr-2">mdi-text-box-outline</v-icon>
                        Inhalte
                    </div>
                    <div class="curriculum-detail__side-card-body">
                        <div class="curriculum-detail__content-toolbar">
                            <div>
                                <div class="curriculum-detail__content-count">Curriculuminhalte</div>
                                <div class="curriculum-detail__content-hint">
                                    Themen und Einheiten bearbeiten, ergänzen oder neu anordnen.
                                </div>
                            </div>
                            <v-btn
                                variant="flat"
                                color="primary"
                                size="small"
                                rounded="lg"
                                prepend-icon="mdi-plus"
                                class="text-none curriculum-detail__content-add-btn"
                                :disabled="isPageActionLocked"
                                @click="openTopicForm()">
                                Thema
                            </v-btn>
                        </div>

                        <div class="curriculum-detail__preview">
                            <div class="curriculum-detail__preview-summary">
                                {{ curriculumTopics.length }} Themen · {{ curriculumUnitCount }} Einheiten
                            </div>

                        <div v-if="curriculumTopics.length" class="curriculum-detail__topic-list">
                            <div
                                v-for="(topic, topicIndex) in curriculumTopics"
                                :key="topic.id"
                                class="curriculum-detail__topic-entry">
                                <div
                                    class="curriculum-detail__topic-item"
                                    :class="{ 'curriculum-detail__topic-item--selected': isTopicSelected(topic.id) }">
                                    <div
                                        class="curriculum-detail__topic-row"
                                        :class="{ 'curriculum-detail__topic-row--collapsible': topic.units.length }"
                                        :role="topic.units.length ? 'button' : undefined"
                                        :tabindex="topic.units.length ? 0 : undefined"
                                        :aria-expanded="topic.units.length ? !isTopicCollapsed(topic.id) : undefined"
                                        @click="topic.units.length && toggleTopicCollapse(topic.id)"
                                        @keydown.enter.prevent="topic.units.length && toggleTopicCollapse(topic.id)"
                                        @keydown.space.prevent="topic.units.length && toggleTopicCollapse(topic.id)">
                                    <div class="curriculum-detail__topic-main">
                                        <div class="curriculum-detail__topic-title-row">
                                            <div class="curriculum-detail__topic-title">
                                                {{ topicIndex + 1 }}. {{ topic.title }}
                                            </div>
                                            <div class="curriculum-detail__topic-unit-count">
                                                {{ topic.units.length }} {{ topic.units.length === 1 ? 'Einheit' : 'Einheiten' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="curriculum-detail__topic-header-actions">
                                        <div class="curriculum-detail__topic-actions" @click.stop>
                                        <v-btn
                                            icon="mdi-arrow-up"
                                            variant="text"
                                            color="secondary"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked || topicIndex === 0"
                                            title="Nach oben verschieben"
                                            @click="moveTopic(topic.id, -1)" />
                                        <v-btn
                                            icon="mdi-arrow-down"
                                            variant="text"
                                            color="secondary"
                                            size="x-small"
                                            :disabled="topicSaving || isPageActionLocked || topicIndex === curriculumTopics.length - 1"
                                            title="Nach unten verschieben"
                                            @click="moveTopic(topic.id, 1)" />
                                        <v-menu location="bottom end">
                                            <template #activator="{ props: topicMenuActivatorProps }">
                                                <v-btn
                                                    v-bind="topicMenuActivatorProps"
                                                    icon="mdi-dots-vertical"
                                                    variant="text"
                                                    color="primary"
                                                    size="x-small"
                                                    density="compact"
                                                    :disabled="topicSaving || isPageActionLocked"
                                                    title="Themenaktionen"
                                                    @click.stop />
                                            </template>
                                            <v-list
                                                density="compact"
                                                min-width="210"
                                                class="curriculum-detail__topic-action-menu">
                                                <v-list-item
                                                    prepend-icon="mdi-pencil-outline"
                                                    title="Bearbeiten"
                                                    :disabled="topicSaving || isPageActionLocked"
                                                    @click="openTopicForm(topic)" />
                                                <v-list-item
                                                    prepend-icon="mdi-plus"
                                                    title="Einheit hinzufügen"
                                                    :disabled="topicSaving || isPageActionLocked"
                                                    @click="openUnitForm(topic.id)" />
                                                <v-divider />
                                                <v-list-item
                                                    prepend-icon="mdi-delete-outline"
                                                    title="Löschen"
                                                    base-color="error"
                                                    :disabled="topicSaving || isPageActionLocked"
                                                    @click="promptDeleteTopic(topic)" />
                                            </v-list>
                                        </v-menu>
                                        </div>
                                    </div>
                                </div>

                                    <div v-if="!isTopicCollapsed(topic.id)" class="curriculum-detail__unit-section">
                                    <div v-if="topic.units.length" class="curriculum-detail__unit-list">
                                        <div
                                            v-for="(unit, unitIndex) in topic.units"
                                            :key="unit.id"
                                            class="curriculum-detail__unit-item"
                                            :class="{
                                                'curriculum-detail__unit-item--selected': isUnitSelected(topic.id, unit.id),
                                                'curriculum-detail__unit-item--exam': unit.is_exam,
                                            }"
                                            role="button"
                                            tabindex="0"
                                            :aria-pressed="isUnitSelected(topic.id, unit.id)"
                                            @click="openSelectedUnitForm(topic.id, unit)"
                                            @keydown.enter.prevent="openSelectedUnitForm(topic.id, unit)"
                                            @keydown.space.prevent="openSelectedUnitForm(topic.id, unit)">
                                            <div class="curriculum-detail__topic-row">
                                                <div class="curriculum-detail__topic-main">
                                                    <div class="curriculum-detail__unit-title-row">
                                                        <div class="curriculum-detail__unit-title">
                                                            {{ topicIndex + 1 }}.{{ unitIndex + 1 }} {{ unit.title }}
                                                        </div>
                                                        <v-chip
                                                            v-if="unit.is_exam"
                                                            size="x-small"
                                                            color="error"
                                                            variant="flat"
                                                            tile
                                                            prepend-icon="mdi-clipboard-text-outline"
                                                            class="curriculum-detail__unit-exam-chip">
                                                            Prüfung
                                                        </v-chip>
                                                    </div>
                                                    <div v-if="unit.materials.length" class="curriculum-detail__attached-materials curriculum-detail__attached-materials--unit">
                                                        <div
                                                            v-for="material in unit.materials"
                                                            :key="`unit-${unit.id}-material-${material.id}`"
                                                            class="curriculum-detail__attached-material">
                                                            <div class="curriculum-detail__attached-material-copy">
                                                                <div class="curriculum-detail__attached-material-title">{{ material.title }}</div>
                                                                <div class="curriculum-detail__attached-material-subtitle">
                                                                    {{ attachedMaterialSubtitle(material) }}
                                                                </div>
                                                            </div>
                                                            <div class="curriculum-detail__attached-material-actions">
                                                                <v-btn
                                                                    v-if="materialFileAttachmentCount(material) > 0"
                                                                    variant="tonal"
                                                                    color="primary"
                                                                    size="x-small"
                                                                    class="text-none curriculum-detail__attached-material-preview-btn"
                                                                    :disabled="topicSaving || isPageActionLocked"
                                                                    @click.stop="openAttachedMaterialDialog(material)">
                                                                    <v-icon size="14" start>mdi-paperclip</v-icon>
                                                                    {{ materialAttachmentCountLabel(material) }}
                                                                </v-btn>
                                                                <v-btn
                                                                    icon="mdi-close"
                                                                    variant="text"
                                                                    color="error"
                                                                    size="x-small"
                                                                    :disabled="topicSaving || isPageActionLocked"
                                                                    title="Material entfernen"
                                                                    @click.stop="removeUnitMaterial(topic.id, unit.id, material.id)" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="curriculum-detail__topic-actions" @click.stop>
                                                    <v-btn
                                                        icon="mdi-arrow-up"
                                                        variant="text"
                                                        color="secondary"
                                                        size="x-small"
                                                        :disabled="topicSaving || isPageActionLocked || unitIndex === 0"
                                                        title="Nach oben verschieben"
                                                        @click="moveUnit(topic.id, unit.id, -1)" />
                                                    <v-btn
                                                        icon="mdi-arrow-down"
                                                        variant="text"
                                                        color="secondary"
                                                        size="x-small"
                                                        :disabled="topicSaving || isPageActionLocked || unitIndex === topic.units.length - 1"
                                                        title="Nach unten verschieben"
                                                        @click="moveUnit(topic.id, unit.id, 1)" />
                                                </div>
                                            </div>

                                        </div>

                                        <div
                                            v-if="showUnitFormForTopicId === topic.id"
                                            class="curriculum-detail__unit-form mt-3">
                                            <v-btn
                                                v-if="!isEditingUnit"
                                                variant="tonal"
                                                color="secondary"
                                                size="x-small"
                                                rounded="lg"
                                                class="text-none"
                                                :disabled="topicSaving"
                                                @click="cancelUnitForm">
                                                Formular schließen
                                            </v-btn>
                                        </div>

                                        <div v-else-if="!topic.units.length" class="curriculum-detail__unit-empty mt-3">
                                            Noch keine Einheiten angelegt.
                                        </div>
                                        </div>
                                </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="curriculum-detail__topic-empty">
                            Noch keine Themen definiert.
                        </div>
                        </div>

                        <div class="curriculum-detail__content-footer">
                            <v-btn
                                variant="flat"
                                color="primary"
                                size="small"
                                rounded="lg"
                                prepend-icon="mdi-plus"
                                class="text-none curriculum-detail__content-add-btn"
                                :disabled="isPageActionLocked"
                                @click="openTopicForm()">
                                Thema
                            </v-btn>
                        </div>
                    </div>
                </div>
            </v-sheet>

            <v-dialog v-model="contentDeleteDialogOpen" max-width="420" persistent>
                <v-card rounded="xl">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                        <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                        {{ contentToDelete?.type === 'unit' ? 'Einheit löschen' : 'Thema löschen' }}
                    </v-card-title>
                    <v-card-text class="px-4 pb-2">
                        <div class="text-body-2" style="color: #475569">
                            Soll {{ contentToDelete?.type === 'unit' ? 'die Einheit' : 'das Thema' }}
                            <strong>{{ contentToDelete?.title }}</strong>
                            wirklich gelöscht werden?
                        </div>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-spacer />
                        <v-btn
                            variant="text"
                            color="secondary"
                            :disabled="topicSaving"
                            @click="closeTopicDeleteDialog">
                            Abbrechen
                        </v-btn>
                        <v-btn
                            color="error"
                            variant="flat"
                            :loading="topicSaving"
                            @click="confirmTopicDelete">
                            Löschen
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-dialog v-model="showTopicForm" max-width="520" persistent>
                <v-card rounded="xl" class="curriculum-detail__editor-dialog-card">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                        <v-icon color="primary" size="20">mdi-text-box-edit-outline</v-icon>
                        {{ topicForm.id ? 'Thema bearbeiten' : 'Thema anlegen' }}
                    </v-card-title>
                    <v-card-text class="px-4 pt-2 pb-2">
                        <div class="curriculum-detail__topic-form curriculum-detail__editor-dialog-form">
                            <v-text-field
                                v-model="topicForm.title"
                                label="Thema"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-3" />
                            <div v-if="topicFormError" class="curriculum-detail__topic-form-error mb-3">
                                {{ topicFormError }}
                            </div>
                            <div class="curriculum-detail__topic-form-actions">
                                <v-btn
                                    variant="flat"
                                    color="primary"
                                    size="small"
                                    rounded="lg"
                                    class="text-none curriculum-detail__topic-save-btn curriculum-detail__editor-dialog-save-btn"
                                    :loading="topicSaving"
                                    @click="saveTopic">
                                    {{ topicForm.id ? 'Thema speichern' : 'Thema anlegen' }}
                                </v-btn>
                                <v-btn
                                    variant="text"
                                    color="secondary"
                                    size="small"
                                    class="text-none curriculum-detail__editor-dialog-cancel-btn"
                                    :disabled="topicSaving"
                                    @click="cancelTopicForm">
                                    Abbrechen
                                </v-btn>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-dialog>

            <v-dialog v-model="showUnitForm" max-width="520" persistent>
                <v-card rounded="xl" class="curriculum-detail__editor-dialog-card">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                        <v-icon color="primary" size="20">mdi-text-box-edit-outline</v-icon>
                        {{ unitForm.id ? 'Einheit bearbeiten' : 'Einheit anlegen' }}
                    </v-card-title>
                    <v-card-text class="px-4 pt-2 pb-2">
                        <div class="curriculum-detail__topic-form curriculum-detail__unit-form curriculum-detail__editor-dialog-form">
                            <v-text-field
                                v-model="unitForm.title"
                                label="Einheit"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-3" />
                            <v-checkbox
                                v-model="unitForm.is_exam"
                                label="Prüfung"
                                color="warning"
                                density="comfortable"
                                hide-details
                                class="curriculum-detail__unit-exam-checkbox mb-3" />
                            <div v-if="unitFormError" class="curriculum-detail__topic-form-error mb-3">
                                {{ unitFormError }}
                            </div>
                            <div class="curriculum-detail__unit-dialog-actions">
                                <div
                                    v-if="unitForm.id"
                                    class="curriculum-detail__unit-dialog-secondary-actions">
                                    <v-btn
                                        prepend-icon="mdi-book-plus-outline"
                                        variant="tonal"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none curriculum-detail__unit-dialog-material-btn"
                                        :disabled="topicSaving"
                                        @click="openUnitMaterialDialog">
                                        Material hinzufügen
                                    </v-btn>
                                    <v-btn
                                        prepend-icon="mdi-delete-outline"
                                        variant="text"
                                        color="error"
                                        size="small"
                                        rounded="lg"
                                        class="text-none curriculum-detail__unit-dialog-delete-btn"
                                        :disabled="topicSaving"
                                        @click="promptDeleteEditingUnit">
                                        Einheit löschen
                                    </v-btn>
                                </div>
                                <div class="curriculum-detail__topic-form-actions">
                                    <v-btn
                                        variant="flat"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none curriculum-detail__topic-save-btn curriculum-detail__editor-dialog-save-btn"
                                        :loading="topicSaving"
                                        @click="saveUnit">
                                        {{ unitForm.id ? 'Einheit speichern' : 'Einheit anlegen' }}
                                    </v-btn>
                                    <v-btn
                                        variant="text"
                                        color="secondary"
                                        size="small"
                                        class="text-none curriculum-detail__editor-dialog-cancel-btn"
                                        :disabled="topicSaving"
                                        @click="cancelUnitForm">
                                        Abbrechen
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-dialog>

            <v-dialog v-model="contentMaterialDialogOpen" max-width="980" persistent>
                <v-card rounded="xl" class="curriculum-detail__editor-dialog-card">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                        <v-icon color="primary" size="20">mdi-book-plus-outline</v-icon>
                        Materialien zu {{ contentMaterialDialogLabel }} hinzufügen
                    </v-card-title>
                    <v-card-text class="px-4 pt-2 pb-2">
                        <div class="curriculum-detail__material-dialog">
                            <div class="curriculum-detail__material-dialog-toolbar">
                                <div>
                                    <div class="curriculum-detail__material-dialog-title">{{ contentMaterialDialogTitle }}</div>
                                    <div class="curriculum-detail__material-dialog-subtitle">
                                        Mehrere Materialien können nacheinander hinzugefügt werden.
                                    </div>
                                </div>
                                <div class="curriculum-detail__material-mode-toggle">
                                    <v-btn
                                        :variant="contentMaterialDialogMode === 'search' ? 'flat' : 'tonal'"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none"
                                        @click="setContentMaterialDialogMode('search')">
                                        Suche
                                    </v-btn>
                                    <v-btn
                                        :variant="contentMaterialDialogMode === 'workspace' ? 'flat' : 'tonal'"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none"
                                        @click="setContentMaterialDialogMode('workspace')">
                                        Arbeitsbereich
                                    </v-btn>
                                    <v-btn
                                        :variant="contentMaterialDialogMode === 'shared' ? 'flat' : 'tonal'"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none"
                                        @click="setContentMaterialDialogMode('shared')">
                                        Für mich geteilt
                                    </v-btn>
                                </div>
                            </div>

                            <div v-if="contentMaterialDialogMode === 'search'">
                                <v-text-field
                                    v-model="contentMaterialSearch"
                                    label="Material suchen"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    clearable
                                    prepend-inner-icon="mdi-magnify"
                                    class="mb-3"
                                    @update:modelValue="searchContentMaterials" />
                                <div class="curriculum-detail__material-selection-layout">
                                    <div class="curriculum-detail__material-browser-results">
                                        <div class="curriculum-detail__material-browser-heading">Trefferliste</div>
                                        <div v-if="contentMaterialsLoading" class="text-center py-4">
                                            <v-progress-circular indeterminate color="primary" size="24" />
                                        </div>
                                        <v-list
                                            v-else-if="contentMaterialResults.length"
                                            bg-color="transparent"
                                            density="compact"
                                            class="py-0 curriculum-detail__material-results">
                                            <v-list-item
                                                v-for="card in contentMaterialResults"
                                                :key="`search-material-${card.id}`"
                                                class="curriculum-detail__material-result-item mb-1 px-3"
                                                :class="{ 'curriculum-detail__material-result-item--active': contentMaterialPreviewCard?.id === card.id }"
                                                rounded="lg"
                                                @click="selectContentMaterialPreview(card)">
                                                <template #prepend>
                                                    <v-icon size="18" color="#a5b4fc" class="mr-2">mdi-package-variant-closed</v-icon>
                                                </template>
                                                <v-list-item-title class="text-body-2">{{ card.title }}</v-list-item-title>
                                                <v-list-item-subtitle class="text-caption">{{ attachedMaterialSubtitle(card) }}</v-list-item-subtitle>
                                                <template #append>
                                                    <v-chip
                                                        v-if="isContentMaterialAttached(card)"
                                                        size="x-small"
                                                        color="success"
                                                        variant="tonal">
                                                        Hinzugefügt
                                                    </v-chip>
                                                    <v-btn
                                                        v-else
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        :disabled="topicSaving"
                                                        @click.stop="attachContentMaterial(card)">
                                                        Hinzufügen
                                                    </v-btn>
                                                </template>
                                            </v-list-item>
                                        </v-list>
                                        <div v-else class="text-center py-4 text-caption curriculum-detail__material-dialog-empty">
                                            Keine Materialien gefunden.
                                        </div>
                                    </div>

                                    <div class="curriculum-detail__material-preview-panel">
                                        <div class="curriculum-detail__material-browser-heading">Anhänge</div>
                                        <div v-if="contentMaterialPreviewCard" class="curriculum-detail__material-preview-copy">
                                            <div class="curriculum-detail__material-preview-title">{{ contentMaterialPreviewCard.title }}</div>
                                            <div class="curriculum-detail__material-preview-subtitle">
                                                {{ attachedMaterialSubtitle(contentMaterialPreviewCard) }}
                                            </div>
                                        </div>
                                        <div v-if="contentMaterialPreviewAttachments.length" class="curriculum-detail__material-preview-list">
                                            <div
                                                v-for="attachment in contentMaterialPreviewAttachments"
                                                :key="`preview-attachment-${attachment.id}`"
                                                class="curriculum-detail__material-preview-list-item"
                                                @click="openContentMaterialPreview(attachment)">
                                                <div class="curriculum-detail__material-preview-list-copy">
                                                    <div class="curriculum-detail__material-preview-list-title">{{ attachment.name }}</div>
                                                    <div class="curriculum-detail__material-preview-list-subtitle">
                                                        {{ attachment.mime_type || 'Datei' }}
                                                    </div>
                                                </div>
                                                <div class="curriculum-detail__material-preview-list-actions">
                                                    <v-btn
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        @click.stop="openContentMaterialPreview(attachment)">
                                                        Vorschau
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="attachment.download_url"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        @click.stop="downloadContentMaterialAttachment(attachment)">
                                                        Herunterladen
                                                    </v-btn>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="curriculum-detail__material-preview-empty">
                                            <v-icon size="36" color="#94a3b8" class="mb-2">mdi-paperclip</v-icon>
                                            <div class="text-caption">Material auswählen, um die verbundenen Dateien zu sehen.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="curriculum-detail__material-browser">
                                <div class="curriculum-detail__material-browser-panel">
                                    <div class="curriculum-detail__material-browser-heading">
                                        {{ contentMaterialDialogMode === 'shared' ? 'Für mich geteilte Materialien filtern' : 'Arbeitsbereich filtern' }}
                                    </div>
                                    <div class="curriculum-detail__material-dialog-subtitle mb-3">
                                        {{ contentMaterialDialogMode === 'shared'
                                            ? 'Zuerst eine Quelle wählen, danach Fach, Thema und Einheit eingrenzen.'
                                            : 'Zuerst ein Fach wählen, danach bei Bedarf Thema und Einheit eingrenzen.' }}
                                    </div>

                                    <div class="curriculum-detail__material-filter-grid">
                                        <v-autocomplete
                                            v-if="contentMaterialDialogMode === 'shared'"
                                            :model-value="selectedContentMaterialSource"
                                            :items="sharedMaterialClassificationTree"
                                            item-title="label"
                                            item-value="user_id"
                                            label="Quelle"
                                            variant="outlined"
                                            density="comfortable"
                                            hide-details
                                            return-object
                                            clearable
                                            :disabled="contentMaterialClassificationLoading"
                                            @update:modelValue="selectContentMaterialSource" />

                                        <v-autocomplete
                                            :model-value="selectedContentMaterialSubject"
                                            :items="activeClassificationTree"
                                            item-title="name"
                                            item-value="id"
                                            label="Fach"
                                            variant="outlined"
                                            density="comfortable"
                                            hide-details
                                            return-object
                                            clearable
                                            :disabled="contentMaterialDialogMode === 'shared' ? !selectedContentMaterialSource : contentMaterialClassificationLoading"
                                            @update:modelValue="selectContentMaterialSubject" />

                                        <v-autocomplete
                                            :model-value="selectedContentMaterialTopic"
                                            :items="contentMaterialTopicSelectOptions"
                                            item-title="name"
                                            item-value="id"
                                            label="Thema"
                                            variant="outlined"
                                            density="comfortable"
                                            hide-details
                                            return-object
                                            clearable
                                            :disabled="!selectedContentMaterialSubject"
                                            @update:modelValue="selectContentMaterialTopic" />

                                        <v-autocomplete
                                            :model-value="selectedContentMaterialUnit"
                                            :items="contentMaterialUnitSelectOptions"
                                            item-title="name"
                                            item-value="id"
                                            label="Einheit"
                                            variant="outlined"
                                            density="comfortable"
                                            hide-details
                                            return-object
                                            clearable
                                            :disabled="!selectedContentMaterialTopic"
                                            @update:modelValue="selectContentMaterialUnit" />
                                    </div>

                                    <div class="curriculum-detail__material-browser-summary">
                                        <div class="curriculum-detail__material-browser-summary-card">
                                            <div class="curriculum-detail__material-browser-summary-label">Auswahl</div>
                                            <div class="curriculum-detail__material-browser-summary-value">
                                                {{ contentMaterialWorkspaceSelectionLabel }}
                                            </div>
                                        </div>
                                        <div class="curriculum-detail__material-browser-summary-card">
                                            <div class="curriculum-detail__material-browser-summary-label">Treffer</div>
                                            <div class="curriculum-detail__material-browser-summary-value">
                                                {{ contentMaterialWorkspaceResultSummary }}
                                            </div>
                                        </div>
                                        <v-btn
                                            variant="text"
                                            color="secondary"
                                            size="small"
                                            class="text-none curriculum-detail__material-browser-reset"
                                            :disabled="!selectedContentMaterialSource && !selectedContentMaterialSubject && !selectedContentMaterialTopic && !selectedContentMaterialUnit"
                                            @click="resetContentMaterialWorkspaceSelection">
                                            Auswahl zurücksetzen
                                        </v-btn>
                                    </div>
                                </div>

                                <div class="curriculum-detail__material-selection-layout">
                                    <div class="curriculum-detail__material-browser-results">
                                        <div class="curriculum-detail__material-browser-heading">Trefferliste</div>
                                        <div v-if="contentMaterialWorkspaceLoading" class="text-center py-4">
                                            <v-progress-circular indeterminate color="primary" size="24" />
                                        </div>
                                        <div v-else-if="contentMaterialWorkspaceError" class="text-caption py-4 curriculum-detail__material-dialog-error">
                                            {{ contentMaterialWorkspaceError }}
                                        </div>
                                        <div
                                            v-else-if="!selectedContentMaterialSubject"
                                            class="text-center py-4 text-caption curriculum-detail__material-dialog-empty">
                                            {{ contentMaterialDialogMode === 'shared' && !selectedContentMaterialSource
                                                ? 'Bitte zuerst eine Quelle wählen.'
                                                : 'Bitte zuerst ein Fach auswählen.' }}
                                        </div>
                                        <v-list
                                            v-else-if="contentMaterialWorkspaceResults.length"
                                            bg-color="transparent"
                                            density="compact"
                                            class="py-0 curriculum-detail__material-results">
                                            <v-list-item
                                                v-for="card in contentMaterialWorkspaceResults"
                                                :key="`workspace-material-${card.id}`"
                                                class="curriculum-detail__material-result-item mb-1 px-3"
                                                :class="{ 'curriculum-detail__material-result-item--active': contentMaterialPreviewCard?.id === card.id }"
                                                rounded="lg"
                                                @click="selectContentMaterialPreview(card)">
                                                <template #prepend>
                                                    <v-icon size="18" color="#a5b4fc" class="mr-2">mdi-package-variant-closed</v-icon>
                                                </template>
                                                <v-list-item-title class="text-body-2">{{ card.title }}</v-list-item-title>
                                                <v-list-item-subtitle class="text-caption">{{ attachedMaterialSubtitle(card) }}</v-list-item-subtitle>
                                                <template #append>
                                                    <v-chip
                                                        v-if="isContentMaterialAttached(card)"
                                                        size="x-small"
                                                        color="success"
                                                        variant="tonal">
                                                        Hinzugefügt
                                                    </v-chip>
                                                    <v-btn
                                                        v-else
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        :disabled="topicSaving"
                                                        @click.stop="attachContentMaterial(card)">
                                                        Hinzufügen
                                                    </v-btn>
                                                </template>
                                            </v-list-item>
                                        </v-list>
                                        <div v-else class="text-center py-4 text-caption curriculum-detail__material-dialog-empty">
                                            Keine Materialien in dieser Auswahl gefunden.
                                        </div>
                                    </div>

                                    <div class="curriculum-detail__material-preview-panel">
                                        <div class="curriculum-detail__material-browser-heading">Anhänge</div>
                                        <div v-if="contentMaterialPreviewCard" class="curriculum-detail__material-preview-copy">
                                            <div class="curriculum-detail__material-preview-title">{{ contentMaterialPreviewCard.title }}</div>
                                            <div class="curriculum-detail__material-preview-subtitle">
                                                {{ attachedMaterialSubtitle(contentMaterialPreviewCard) }}
                                            </div>
                                        </div>
                                        <div v-if="contentMaterialPreviewAttachments.length" class="curriculum-detail__material-preview-list">
                                            <div
                                                v-for="attachment in contentMaterialPreviewAttachments"
                                                :key="`workspace-preview-attachment-${attachment.id}`"
                                                class="curriculum-detail__material-preview-list-item"
                                                @click="openContentMaterialPreview(attachment)">
                                                <div class="curriculum-detail__material-preview-list-copy">
                                                    <div class="curriculum-detail__material-preview-list-title">{{ attachment.name }}</div>
                                                    <div class="curriculum-detail__material-preview-list-subtitle">
                                                        {{ attachment.mime_type || 'Datei' }}
                                                    </div>
                                                </div>
                                                <div class="curriculum-detail__material-preview-list-actions">
                                                    <v-btn
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        @click.stop="openContentMaterialPreview(attachment)">
                                                        Vorschau
                                                    </v-btn>
                                                    <v-btn
                                                        v-if="attachment.download_url"
                                                        variant="text"
                                                        color="primary"
                                                        size="x-small"
                                                        class="text-none"
                                                        @click.stop="downloadContentMaterialAttachment(attachment)">
                                                        Herunterladen
                                                    </v-btn>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="curriculum-detail__material-preview-empty">
                                            <v-icon size="36" color="#94a3b8" class="mb-2">mdi-paperclip</v-icon>
                                            <div class="text-caption">Material auswählen, um die verbundenen Dateien zu sehen.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-spacer />
                        <v-btn
                            variant="text"
                            color="secondary"
                            :disabled="topicSaving"
                            @click="closeContentMaterialDialog">
                            Schließen
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-dialog
                v-model="contentMaterialPreviewDialogOpen"
                :fullscreen="contentMaterialPreviewFullscreen"
                :max-width="contentMaterialPreviewFullscreen ? undefined : 1180"
                persistent>
                <v-card
                    :class="contentMaterialPreviewFullscreen
                        ? 'curriculum-detail__fullscreen-preview'
                        : 'curriculum-detail__modal-preview'">
                    <v-card-title class="curriculum-detail__fullscreen-preview-header">
                        <div>
                            <div class="curriculum-detail__fullscreen-preview-title">
                                {{ contentMaterialPreviewAttachment?.name || 'Dateivorschau' }}
                            </div>
                            <div class="curriculum-detail__fullscreen-preview-subtitle">
                                {{ contentMaterialPreviewCard?.title || 'Material' }}
                            </div>
                        </div>
                        <div class="curriculum-detail__fullscreen-preview-actions">
                            <v-btn
                                v-if="contentMaterialPreviewDownloadUrl"
                                prepend-icon="mdi-download"
                                variant="flat"
                                color="primary"
                                rounded="lg"
                                class="text-none"
                                @click="downloadContentMaterialAttachment(contentMaterialPreviewAttachment)">
                                Herunterladen
                            </v-btn>
                            <v-btn
                                prepend-icon="mdi-close"
                                variant="flat"
                                color="error"
                                rounded="lg"
                                class="text-none curriculum-detail__fullscreen-preview-close-btn"
                                @click="closeContentMaterialPreview">
                                Schließen
                            </v-btn>
                        </div>
                    </v-card-title>
                    <v-card-text class="curriculum-detail__fullscreen-preview-body">
                        <iframe
                            v-if="contentMaterialPreviewAttachment && contentMaterialPreviewUsesIframe"
                            :src="contentMaterialPreviewUrl"
                            :class="contentMaterialPreviewFullscreen
                                ? 'curriculum-detail__fullscreen-preview-iframe'
                                : 'curriculum-detail__modal-preview-iframe'" />
                        <img
                            v-else-if="contentMaterialPreviewAttachment && contentMaterialPreviewIsImage"
                            :src="contentMaterialPreviewUrl"
                            :class="contentMaterialPreviewFullscreen
                                ? 'curriculum-detail__fullscreen-preview-image'
                                : 'curriculum-detail__modal-preview-image'" />
                        <div v-else class="curriculum-detail__fullscreen-preview-empty">
                            <v-icon size="42" color="#64748b" class="mb-3">mdi-file-document-outline</v-icon>
                            <div class="text-body-2 mb-3">Für diesen Dateityp ist keine direkte Vorschau verfügbar.</div>
                            <v-btn
                                v-if="contentMaterialPreviewDownloadUrl"
                                variant="flat"
                                color="primary"
                                class="text-none"
                                @click="downloadContentMaterialAttachment(contentMaterialPreviewAttachment)">
                                Herunterladen
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </v-dialog>

            <v-dialog v-model="attachedMaterialDialogOpen" max-width="720" persistent>
                <v-card rounded="xl">
                    <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                        <v-icon color="primary" size="22">mdi-paperclip</v-icon>
                        Anhänge des Materials
                    </v-card-title>
                    <v-card-text class="px-4 pb-2">
                        <div class="curriculum-detail__material-preview-copy">
                            <div class="curriculum-detail__material-preview-title">
                                {{ contentMaterialPreviewCard?.title || 'Material' }}
                            </div>
                            <div class="curriculum-detail__material-preview-subtitle">
                                {{ attachedMaterialSubtitle(contentMaterialPreviewCard) }}
                            </div>
                        </div>
                        <div
                            v-if="!attachedMaterialDialogLoading && contentMaterialPreviewAttachments.length"
                            class="text-caption mb-3"
                            style="color: #475569">
                            Für jeden Anhang stehen Vorschau und Download zur Verfügung.
                        </div>
                        <div v-if="attachedMaterialDialogLoading" class="text-center py-6">
                            <v-progress-circular indeterminate color="primary" size="24" />
                        </div>
                        <div v-else-if="attachedMaterialDialogError" class="text-caption py-4" style="color: #b91c1c">
                            {{ attachedMaterialDialogError }}
                        </div>
                        <v-list
                            v-else-if="contentMaterialPreviewAttachments.length"
                            bg-color="transparent"
                            density="compact"
                            class="py-0"
                            style="max-height: 360px; overflow-y: auto">
                            <v-list-item
                                v-for="attachment in contentMaterialPreviewAttachments"
                                :key="`attached-material-attachment-${attachment.id}`"
                                class="lehrplaene__material-item lehrplaene__attachment-item mb-1 px-3"
                                rounded="lg"
                                @click="openContentMaterialPreview(attachment, { fullscreen: false })">
                                <template #prepend>
                                    <v-icon size="18" color="#a5b4fc" class="mr-2">
                                        {{ contentMaterialPreviewAttachmentIcon(attachment) }}
                                    </v-icon>
                                </template>
                                <v-list-item-title class="text-body-2">{{ attachment.name }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption">
                                    {{ attachment.mime_type || 'Datei' }}<span v-if="attachment.size_bytes"> · {{ formatBytes(attachment.size_bytes) }}</span>
                                </v-list-item-subtitle>
                                <template #append>
                                    <div class="curriculum-detail__material-preview-list-actions">
                                        <v-btn
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            class="text-none"
                                            @click.stop="openContentMaterialPreview(attachment, { fullscreen: false })">
                                            Vorschau
                                        </v-btn>
                                        <v-btn
                                            v-if="attachment.download_url"
                                            variant="text"
                                            color="primary"
                                            size="x-small"
                                            class="text-none"
                                            @click.stop="downloadContentMaterialAttachment(attachment)">
                                            Herunterladen
                                        </v-btn>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                        <div v-else class="text-center py-6 text-caption" style="color: #64748b">
                            Dieses Material hat keine Anhänge.
                        </div>
                    </v-card-text>
                    <v-card-actions class="px-4 pb-4">
                        <v-btn variant="tonal" @click="closeAttachedMaterialDialog">Schließen</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-sheet rounded="xl" class="curriculum-detail__side-card curriculum-detail__side-card--documents curriculum-detail__side-card--scrollable">
                <div class="curriculum-detail__side-card-inner">
                    <div class="curriculum-detail__side-card-header">
                        <v-icon size="20" color="#a5b4fc" class="mr-2">mdi-book-open-page-variant-outline</v-icon>
                        Lehrpläne
                    </div>
                    <div class="curriculum-detail__side-card-body">
                        <div v-if="docsLoading && !documents.length" class="text-center py-4">
                            <v-progress-circular indeterminate color="primary" size="24" />
                        </div>

                        <div v-if="documents.length" class="lehrplaene__list mb-3">
                            <div
                                v-for="doc in documents"
                                :key="doc.id"
                                class="lehrplaene__item"
                                :class="{ 'lehrplaene__item--active': previewDoc?.id === doc.id, 'lehrplaene__item--clickable': ['upload', 'material'].includes(doc.source_type) }"
                                @click="selectPreview(doc)">
                                <v-icon
                                    size="16"
                                    :color="doc.source_type === 'material' ? '#818cf8' : '#94a3b8'"
                                    class="mr-2 flex-shrink-0">
                                    {{ doc.source_type === 'material' ? 'mdi-package-variant-closed' : 'mdi-file-document-outline' }}
                                </v-icon>
                                <div class="lehrplaene__item-copy">
                                    <span class="lehrplaene__item-name text-truncate">{{ doc.name }}</span>
                                    <span v-if="doc.source_type === 'material'" class="lehrplaene__item-subtitle text-truncate">
                                        {{ doc.selected_attachment_name || 'Anhang auswählen' }}
                                    </span>
                                </div>
                                <v-chip
                                    v-if="doc.source_type === 'material'"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    class="ml-1 flex-shrink-0">
                                    Material
                                </v-chip>
                                <v-btn
                                    v-if="doc.source_type === 'material'"
                                    icon="mdi-paperclip"
                                    variant="text"
                                    size="x-small"
                                    color="primary"
                                    class="ml-1 flex-shrink-0"
                                    :disabled="isPageActionLocked"
                                    title="Anhang auswählen"
                                    @click.stop="openMaterialAttachmentDialog(doc)" />
                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    size="x-small"
                                    color="error"
                                    class="ml-auto flex-shrink-0"
                                    :disabled="isPageActionLocked"
                                    title="Entfernen"
                                    @click.stop="removeDocument(doc)" />
                            </div>
                        </div>

                        <div v-if="previewDoc" class="lehrplaene__preview mb-3">
                            <div class="lehrplaene__preview-header">
                                <span class="text-truncate">{{ previewDoc.selected_attachment_name || previewDoc.name }}</span>
                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    size="x-small"
                                    color="secondary"
                                    :disabled="isPageActionLocked"
                                    @click="previewDoc = null" />
                            </div>
                            <div class="lehrplaene__preview-body">
                                <iframe
                                    v-if="previewUsesIframe && previewUrl"
                                    :src="previewUrl"
                                    :title="`Vorschau: ${previewDoc.selected_attachment_name || previewDoc.name}`"
                                    class="lehrplaene__preview-iframe" />
                                <img
                                    v-else-if="previewIsImage && previewUrl"
                                    :src="previewUrl"
                                    :alt="`Vorschau: ${previewDoc.selected_attachment_name || previewDoc.name}`"
                                    class="lehrplaene__preview-image" />
                                <div v-else class="text-center py-6">
                                    <v-icon size="40" color="#475569" class="mb-2">mdi-file-document-outline</v-icon>
                                    <div class="text-caption" style="color: #64748b">Vorschau nicht verfügbar.</div>
                                    <v-btn
                                        v-if="previewDownloadUrl"
                                        variant="tonal"
                                        color="primary"
                                        size="small"
                                        rounded="lg"
                                        class="text-none mt-2"
                                        :disabled="isPageActionLocked"
                                        :href="previewDownloadUrl"
                                        target="_blank">
                                        Herunterladen
                                    </v-btn>
                                </div>
                            </div>
                        </div>

                        <div v-if="!documents.length && !docsLoading" class="text-center py-3">
                            <v-icon size="32" color="#475569" class="mb-1">mdi-file-plus-outline</v-icon>
                            <div class="text-caption" style="color: #64748b">Noch keine Lehrpläne hinzugefügt.</div>
                        </div>

                        <v-divider class="my-2" style="border-color: rgba(148,163,184,0.12)" />

                        <div class="lehrplaene__actions">
                            <v-btn
                                v-if="!showUploadOptions"
                                variant="tonal"
                                color="primary"
                                size="small"
                                rounded="lg"
                                block
                                prepend-icon="mdi-plus"
                                class="text-none"
                                :disabled="isPageActionLocked"
                                @click="showUploadOptions = true">
                                Hinzufügen
                            </v-btn>

                            <div v-if="showUploadOptions" class="lehrplaene__upload-options">
                                <FileUpload
                                    :path="`/api/admin/teaching/curricula/${curriculum.id}/documents/upload`"
                                    :file-label="true"
                                    :short-label="true"
                                    :allowed-file-types="['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*']"
                                    @fileUploadFinished="onFileUploaded"
                                    @uploadStart="docsLoading = true" />

                                <v-btn
                                    v-if="hasMaterialsAccess"
                                    variant="tonal"
                                    color="primary"
                                    size="small"
                                    rounded="lg"
                                    block
                                    prepend-icon="mdi-package-variant-closed"
                                    class="text-none mt-2"
                                    :disabled="isPageActionLocked"
                                    @click="materialDialogOpen = true">
                                    Aus Materialien wählen
                                </v-btn>

                                <v-btn
                                    variant="text"
                                    size="x-small"
                                    color="secondary"
                                    class="text-none mt-1"
                                    block
                                    :disabled="isPageActionLocked"
                                    @click="showUploadOptions = false">
                                    Abbrechen
                                </v-btn>
                            </div>
                        </div>
                    </div>
                </div>

                <v-dialog v-model="materialDialogOpen" max-width="560" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="primary" size="22">mdi-package-variant-closed</v-icon>
                            Material auswählen
                        </v-card-title>
                        <v-card-text class="px-4">
                            <v-text-field
                                v-model="materialSearch"
                                label="Material suchen..."
                                variant="outlined"
                                density="compact"
                                hide-details
                                clearable
                                prepend-inner-icon="mdi-magnify"
                                class="mb-3"
                                @update:modelValue="searchMaterials" />
                            <div v-if="materialsLoading" class="text-center py-4">
                                <v-progress-circular indeterminate color="primary" size="24" />
                            </div>
                            <v-list v-else-if="materialResults.length" bg-color="transparent" density="compact" class="py-0" style="max-height: 320px; overflow-y: auto">
                                <v-list-item
                                    v-for="card in materialResults"
                                    :key="card.id"
                                    class="lehrplaene__material-item mb-1 px-3"
                                    rounded="lg"
                                    @click="attachMaterial(card)">
                                    <template #prepend>
                                        <v-icon size="18" color="#a5b4fc" class="mr-2">mdi-package-variant-closed</v-icon>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ card.title }}</v-list-item-title>
                                    <v-list-item-subtitle class="text-caption">{{ materialPickerSubtitle(card) }}</v-list-item-subtitle>
                                    <template #append>
                                        <v-chip
                                            v-if="materialFileAttachmentCount(card) > 0"
                                            size="x-small"
                                            color="primary"
                                            variant="tonal">
                                            {{ materialAttachmentCountLabel(card) }}
                                        </v-chip>
                                    </template>
                                </v-list-item>
                            </v-list>
                            <div v-else class="text-center py-4 text-caption" style="color: #64748b">
                                Keine Materialien gefunden.
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-btn variant="tonal" :disabled="isPageActionLocked" @click="materialDialogOpen = false">Schließen</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="materialAttachmentDialogOpen" max-width="640" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="primary" size="22">mdi-paperclip</v-icon>
                            Anhang auswählen
                        </v-card-title>
                        <v-card-text class="px-4 pb-2">
                            <div class="text-body-2 mb-3" style="color: #475569">
                                {{ materialAttachmentDocument?.name || 'Material' }}
                            </div>
                            <div
                                v-if="materialAttachmentOptions.length > 1"
                                class="text-caption mb-3"
                                style="color: #475569">
                                Dieses Material hat mehrere Anhänge. Bitte den Anhang auswählen, der angezeigt werden soll.
                            </div>
                            <div v-if="materialAttachmentLoading" class="text-center py-6">
                                <v-progress-circular indeterminate color="primary" size="24" />
                            </div>
                            <div v-else-if="materialAttachmentError" class="text-caption py-4" style="color: #b91c1c">
                                {{ materialAttachmentError }}
                            </div>
                            <v-list
                                v-else-if="materialAttachmentOptions.length"
                                bg-color="transparent"
                                density="compact"
                                class="py-0"
                                style="max-height: 360px; overflow-y: auto">
                                <v-list-item
                                    v-for="attachment in materialAttachmentOptions"
                                    :key="attachment.id"
                                    class="lehrplaene__material-item lehrplaene__attachment-item mb-1 px-3"
                                    rounded="lg"
                                    :active="selectedMaterialAttachmentId === attachment.id"
                                    :disabled="savingMaterialAttachment"
                                    @click="selectMaterialAttachment(attachment)">
                                    <template #prepend>
                                        <v-icon size="18" color="#a5b4fc" class="mr-2">
                                            {{ attachment.mime_type?.startsWith('image/') ? 'mdi-file-image-outline' : 'mdi-file-document-outline' }}
                                        </v-icon>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ attachment.name }}</v-list-item-title>
                                    <v-list-item-subtitle class="text-caption">
                                        {{ attachment.mime_type || 'Datei' }}<span v-if="attachment.size_bytes"> · {{ formatBytes(attachment.size_bytes) }}</span>
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <v-icon v-if="selectedMaterialAttachmentId === attachment.id" size="18" color="primary">
                                            mdi-check-circle
                                        </v-icon>
                                    </template>
                                </v-list-item>
                            </v-list>
                            <div v-else class="text-center py-6 text-caption" style="color: #64748b">
                                Dieses Material hat keine auswählbaren Anhänge.
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-btn variant="tonal" :disabled="savingMaterialAttachment" @click="closeMaterialAttachmentDialog">Schließen</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="documentDeleteDialogOpen" max-width="420" persistent>
                    <v-card rounded="xl">
                        <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                            <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                            Lehrplan entfernen
                        </v-card-title>
                        <v-card-text class="px-4 pb-2">
                            <div class="text-body-2" style="color: #475569">
                                Soll
                                <strong>{{ documentToDelete?.selected_attachment_name || documentToDelete?.name }}</strong>
                                wirklich aus den Lehrplänen entfernt werden?
                            </div>
                        </v-card-text>
                        <v-card-actions class="px-4 pb-4">
                            <v-spacer />
                            <v-btn
                                variant="text"
                                color="secondary"
                                :disabled="documentDeleteLoading"
                                @click="closeDocumentDeleteDialog">
                                Abbrechen
                            </v-btn>
                            <v-btn
                                color="error"
                                variant="flat"
                                :loading="documentDeleteLoading"
                                @click="confirmDocumentDelete">
                                Entfernen
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </v-sheet>
        </div>
    </div>
</template>

<script>
import { mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import FileUpload from '@/pages/components/FileUpload.vue'

const DAY_NAMES_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
const MONTH_NAMES = [
    'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
]
export default {
    name: 'CurriculumDetail',
    components: { FileUpload },
    props: {
        curriculum: { type: Object, required: true },
    },
    emits: ['back', 'updated'],

    mounted() {
        this.loadDocuments()
    },

    beforeUnmount() {
        if (this._materialSearchTimer) {
            clearTimeout(this._materialSearchTimer)
        }

        if (this._contentMaterialSearchTimer) {
            clearTimeout(this._contentMaterialSearchTimer)
        }
    },

    data() {
        const adminStore = useAdminStore()
        const sy = adminStore.config?.selected_schoolyear
        let initYear
        if (sy?.from) {
            const y = parseInt(sy.from.substring(0, 4), 10)
            initYear = Number.isFinite(y) ? y : null
        }
        if (!initYear) {
            const now = new Date()
            initYear = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1
        }
        return {
            selectedHalf: 'first',
            selectedYear: initYear,
            weekDisplayMode: 'days',
            isExportingCurriculum: false,
            collapseFullMonths: true,
            topicCollapseStates: {},
            manualMonthCollapseStates: {},
            selectedTopicId: null,
            selectedUnitTopicId: null,
            selectedUnitId: null,
            documents: [],
            docsLoading: false,
            contentMaterialDialogOpen: false,
            contentMaterialDialogMode: 'search',
            contentMaterialTarget: null,
            contentMaterialSearch: '',
            contentMaterialResults: [],
            contentMaterialsLoading: false,
            _contentMaterialSearchTimer: null,
            contentMaterialClassificationTree: [],
            sharedMaterialClassificationTree: [],
            contentMaterialClassificationLoading: false,
            selectedContentMaterialSource: null,
            selectedContentMaterialSubject: null,
            selectedContentMaterialTopic: null,
            selectedContentMaterialUnit: null,
            contentMaterialWorkspaceResults: [],
            contentMaterialWorkspaceLoading: false,
            contentMaterialWorkspaceError: null,
            contentMaterialPreviewCard: null,
            contentMaterialPreviewAttachmentId: null,
            contentMaterialPreviewDialogOpen: false,
            contentMaterialPreviewFullscreen: true,
            attachedMaterialDialogOpen: false,
            attachedMaterialDialogLoading: false,
            attachedMaterialDialogError: null,
            materialDialogOpen: false,
            materialAttachmentDialogOpen: false,
            materialAttachmentDocument: null,
            materialAttachmentOptions: [],
            materialAttachmentLoading: false,
            materialAttachmentError: null,
            savingMaterialAttachment: false,
            selectedMaterialAttachmentId: null,
            materialSearch: '',
            materialResults: [],
            materialsLoading: false,
            _materialSearchTimer: null,
            showUploadOptions: false,
            previewDoc: null,
            topicSaving: false,
            topicFormError: null,
            showTopicForm: false,
            unitFormError: null,
            showUnitFormForTopicId: null,
            activeTopicAssignmentId: null,
            activeTopicAssignmentUnitId: null,
            activeTopicAssignmentType: null,
            contentDeleteDialogOpen: false,
            contentToDelete: null,
            overloadedMonthShiftDialogOpen: false,
            overloadedMonthShiftMonthKey: null,
            overloadedMonthShiftSaving: false,
            documentDeleteDialogOpen: false,
            documentDeleteLoading: false,
            documentToDelete: null,
            topicForm: {
                id: null,
                title: '',
            },
            unitForm: {
                topicId: null,
                id: null,
                title: '',
            },
        }
    },

    watch: {
        'curriculum.id'() {
            this.topicCollapseStates = {}
            this.manualMonthCollapseStates = {}
        },
    },

    computed: {
        ...mapState(useAdminStore, ['config']),

        hasMaterialsAccess() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            return ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((r) => roles.includes(r))
        },

        previewUrl() {
            return this.previewDoc?.preview_url || null
        },

        previewDownloadUrl() {
            return this.previewDoc?.download_url || null
        },

        previewIsPdf() {
            if (!this.previewDoc) return false
            const mime = (this.previewDoc.preview_mime_type || this.previewDoc.mime_type || '').toLowerCase()
            return mime === 'application/pdf'
        },

        previewIsImage() {
            if (!this.previewDoc) return false
            const mime = (this.previewDoc.preview_mime_type || this.previewDoc.mime_type || '').toLowerCase()
            return mime.startsWith('image/')
        },

        previewUsesIframe() {
            return Boolean(this.previewUrl) && !this.previewIsImage
        },

        curriculumTopics() {
            return (Array.isArray(this.curriculum.topics) ? this.curriculum.topics : [])
                .map((topic, index) => this.normalizeTopic(topic, index))
        },

        curriculumUnitCount() {
            return this.curriculumTopics.reduce((total, topic) => total + topic.units.length, 0)
        },

        curriculumScopeLabel() {
            return (this.curriculum.semester_count ?? 2) === 1 ? 'Ganzes Semester' : 'Ganzes Jahr'
        },

        assignableMonths() {
            return this.visibleMonths.map((month) => ({
                assignmentKey: month.assignmentKey,
                name: `${month.name} ${month.year}`,
            }))
        },

        monthWeekCountOptions() {
            return [1, 2, 3, 4, 0]
        },

        isEditingTopic() {
            return this.showTopicForm && this.topicForm.id !== null
        },

        isEditingTopicDates() {
            return this.activeTopicAssignmentId !== null && this.activeTopicAssignmentUnitId === null
        },

        isEditingUnit() {
            return this.showUnitFormForTopicId !== null && this.unitForm.id !== null
        },

        isEditingUnitDates() {
            return this.activeTopicAssignmentUnitId !== null
        },

        showUnitForm: {
            get() {
                return this.showUnitFormForTopicId !== null
            },
            set(value) {
                if (!value && this.showUnitFormForTopicId !== null) {
                    this.cancelUnitForm()
                }
            },
        },

        isPageActionLocked() {
            return this.isEditingTopic || this.isEditingUnit || this.activeTopicAssignmentId !== null
        },

        showWeekdays() {
            return this.weekDisplayMode === 'days'
        },

        isCompactWeekView() {
            return !this.showWeekdays
        },

        activeTopicAssignmentTopic() {
            if (!this.activeTopicAssignmentId) return null

            return this.curriculumTopics.find((topic) => topic.id === this.activeTopicAssignmentId) ?? null
        },

        activeTopicAssignmentUnit() {
            if (!this.activeTopicAssignmentTopic || !this.activeTopicAssignmentUnitId) return null

            return this.activeTopicAssignmentTopic.units.find((unit) => unit.id === this.activeTopicAssignmentUnitId) ?? null
        },

        activeAssignmentItem() {
            return this.activeTopicAssignmentUnit ?? this.activeTopicAssignmentTopic
        },

        selectedTopic() {
            if (!this.selectedTopicId) {
                return null
            }

            return this.curriculumTopics.find((topic) => topic.id === this.selectedTopicId) ?? null
        },

        selectedAssignmentItems() {
            if (this.selectedTopic && this.selectedUnitTopicId === this.selectedTopic.id && this.selectedUnitId) {
                const selectedUnit = this.selectedTopic.units.find((unit) => unit.id === this.selectedUnitId) ?? null

                return selectedUnit ? [selectedUnit] : []
            }

            if (this.selectedTopic && this.isTopicSelected(this.selectedTopic.id)) {
                return [this.selectedTopic, ...this.selectedTopic.units]
            }

            return []
        },

        highlightedAssignmentItems() {
            return this.activeAssignmentItem ? [this.activeAssignmentItem] : this.selectedAssignmentItems
        },

        highlightedTopic() {
            return this.activeTopicAssignmentTopic ?? this.selectedTopic
        },

        highlightedTopicAccentColor() {
            if (!this.highlightedTopic) {
                return null
            }

            const topicIndex = this.curriculumTopics.findIndex((topic) => topic.id === this.highlightedTopic.id)

            if (topicIndex === -1) {
                return null
            }

            return `hsl(${this.topicHue(topicIndex)}, 70%, 48%)`
        },

        calendarHighlightStyle() {
            return this.highlightedTopicAccentColor
                ? { '--calendar-highlight-accent': this.highlightedTopicAccentColor }
                : {}
        },

        activeAssignmentItemLabel() {
            return this.activeTopicAssignmentUnit ? 'Einheit' : 'Thema'
        },

        contentMaterialDialogTarget() {
            if (
                this.contentMaterialTarget?.type !== 'unit'
                || !this.contentMaterialTarget.topicId
                || !this.contentMaterialTarget.unitId
            ) {
                return null
            }

            return this.findUnit(this.contentMaterialTarget.topicId, this.contentMaterialTarget.unitId)
        },

        contentMaterialDialogLabel() {
            return 'der Einheit'
        },

        contentMaterialDialogTitle() {
            return this.contentMaterialDialogTarget?.title || 'Inhalt'
        },

        contentMaterialTopicOptions() {
            return Array.isArray(this.selectedContentMaterialSubject?.topics)
                ? this.selectedContentMaterialSubject.topics
                : []
        },

        activeClassificationTree() {
            if (this.contentMaterialDialogMode === 'shared') {
                return this.selectedContentMaterialSource?.subjects || []
            }
            return this.contentMaterialClassificationTree
        },

        contentMaterialTopicSelectOptions() {
            if (!this.selectedContentMaterialSubject) {
                return []
            }

            return [
                { id: null, name: 'Alle Themen' },
                ...this.contentMaterialTopicOptions,
            ]
        },

        contentMaterialUnitOptions() {
            return Array.isArray(this.selectedContentMaterialTopic?.units)
                ? this.selectedContentMaterialTopic.units
                : []
        },

        contentMaterialUnitSelectOptions() {
            if (!this.selectedContentMaterialTopic) {
                return []
            }

            return [
                { id: null, name: 'Alle Einheiten' },
                ...this.contentMaterialUnitOptions,
            ]
        },

        contentMaterialWorkspaceSelectionLabel() {
            const parts = []

            if (this.contentMaterialDialogMode === 'shared' && this.selectedContentMaterialSource?.user_name) {
                parts.push(this.selectedContentMaterialSource.user_name)
            }

            if (this.selectedContentMaterialSubject?.name) {
                parts.push(this.selectedContentMaterialSubject.name)
            }

            if (this.selectedContentMaterialTopic?.name) {
                parts.push(this.selectedContentMaterialTopic.name)
            }

            if (this.selectedContentMaterialUnit?.name) {
                parts.push(this.selectedContentMaterialUnit.name)
            }

            if (parts.length) {
                return parts.join(' · ')
            }

            if (this.contentMaterialClassificationLoading) {
                return 'Wird geladen...'
            }

            if (this.contentMaterialDialogMode === 'shared') {
                return 'Bitte zuerst eine Quelle wählen.'
            }

            return 'Bitte zuerst ein Fach auswählen.'
        },

        contentMaterialWorkspaceResultSummary() {
            if (!this.selectedContentMaterialSubject) {
                return 'Noch keine Auswahl'
            }

            if (this.contentMaterialWorkspaceLoading) {
                return 'Wird geladen...'
            }

            return `${this.contentMaterialWorkspaceResults.length} Material${this.contentMaterialWorkspaceResults.length === 1 ? '' : 'ien'}`
        },

        contentMaterialPreviewAttachments() {
            return Array.isArray(this.contentMaterialPreviewCard?.attachments)
                ? this.contentMaterialPreviewCard.attachments
                : []
        },

        contentMaterialPreviewAttachment() {
            if (!this.contentMaterialPreviewAttachments.length || !this.contentMaterialPreviewAttachmentId) {
                return null
            }

            return this.contentMaterialPreviewAttachments.find((attachment) => attachment.id === this.contentMaterialPreviewAttachmentId)
                || null
        },

        contentMaterialPreviewUrl() {
            return this.contentMaterialPreviewAttachment?.preview_url || this.contentMaterialPreviewAttachment?.download_url || null
        },

        contentMaterialPreviewDownloadUrl() {
            return this.contentMaterialPreviewAttachment?.download_url || null
        },

        contentMaterialPreviewIsImage() {
            const mime = String(this.contentMaterialPreviewAttachment?.mime_type || '').toLowerCase()
            return mime.startsWith('image/')
        },

        contentMaterialPreviewUsesIframe() {
            return Boolean(this.contentMaterialPreviewUrl) && !this.contentMaterialPreviewIsImage
        },

        allMonths() {
            const months = []
            const startYear = this.selectedYear
            // September (8) to July (6) next year
            const monthSequence = [
                { m: 8, y: startYear },     // Sep
                { m: 9, y: startYear },      // Okt
                { m: 10, y: startYear },     // Nov
                { m: 11, y: startYear },     // Dez
                { m: 0, y: startYear + 1 },  // Jan
                { m: 1, y: startYear + 1 },  // Feb
                { m: 2, y: startYear + 1 },  // Mar
                { m: 3, y: startYear + 1 },  // Apr
                { m: 4, y: startYear + 1 },  // Mai
                { m: 5, y: startYear + 1 },  // Jun
                { m: 6, y: startYear + 1 },  // Jul
            ]

            const today = new Date()
            today.setHours(0, 0, 0, 0)

            for (const entry of monthSequence) {
                const weeks = this.buildWeeks(entry.y, entry.m, today)
                months.push({
                    key: `${entry.y}-${entry.m}`,
                    assignmentKey: this.formatMonthKey(entry.y, entry.m),
                    name: MONTH_NAMES[entry.m],
                    year: entry.y,
                    month: entry.m,
                    weeks,
                })
            }
            return months
        },

        visibleMonths() {
            const semCount = this.curriculum.semester_count ?? 2
            if (semCount === 2) return this.allMonths

            // 1 semester: first half = Sep-Feb (indices 0-5), second half = Feb-Jul (indices 5-10)
            if (this.selectedHalf === 'first') {
                return this.allMonths.slice(0, 6)
            }
            return this.allMonths.slice(5, 11)
        },

    },

    methods: {
        newTopicForm(topic = null) {
            return {
                id: topic?.id || null,
                title: topic?.title || '',
            }
        },

        newUnitForm(topicId = null, unit = null) {
            return {
                topicId,
                id: unit?.id || null,
                title: unit?.title || '',
                is_exam: Boolean(unit?.is_exam),
            }
        },

        normalizeAssignmentEntry(entry = null, index = 0, prefix = 'entry') {
            const normalizedEntry = entry && typeof entry === 'object' ? entry : {}
            const assignmentType = ['none', 'all_weeks', 'month', 'weeks'].includes(normalizedEntry.assignment_type)
                ? normalizedEntry.assignment_type
                : 'none'
            const monthKeys = [...new Set(
                (
                    Array.isArray(normalizedEntry.month_keys)
                        ? normalizedEntry.month_keys
                        : [normalizedEntry.month_key]
                )
                    .filter(Boolean)
                    .map((monthKey) => this.normalizeMonthAssignmentKey(monthKey))
                    .filter(Boolean)
            )].sort()
            const weekKeys = [...new Set(
                (Array.isArray(normalizedEntry.week_keys) ? normalizedEntry.week_keys : [])
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean)
            )].sort()
            const monthWeekCounts = this.normalizeMonthWeekCounts(normalizedEntry.month_week_counts, monthKeys)

            return {
                id: normalizedEntry.id || `${prefix}-${index}`,
                title: typeof normalizedEntry.title === 'string' ? normalizedEntry.title.trim() : '',
                assignment_type: assignmentType,
                month_key: assignmentType === 'month' ? (monthKeys[0] ?? null) : null,
                month_keys: assignmentType === 'month' ? monthKeys : [],
                month_week_counts: assignmentType === 'month' ? monthWeekCounts : {},
                week_keys: assignmentType === 'weeks' ? weekKeys : [],
            }
        },

        normalizeMonthWeekCounts(monthWeekCounts = {}, monthKeys = []) {
            const selectedMonthKeys = (Array.isArray(monthKeys) ? monthKeys : [])
                .map((monthKey) => this.normalizeMonthAssignmentKey(monthKey))
                .filter(Boolean)

            return selectedMonthKeys.reduce((normalizedMonthWeekCounts, monthKey) => {
                const normalizedWeeks = Number.parseInt(monthWeekCounts?.[monthKey] ?? 1, 10)

                return {
                    ...normalizedMonthWeekCounts,
                    [monthKey]: Number.isFinite(normalizedWeeks) && normalizedWeeks >= 0 && normalizedWeeks <= 4
                        ? normalizedWeeks
                        : 1,
                }
            }, {})
        },

        normalizeAttachedMaterial(material = null) {
            const normalizedMaterial = material && typeof material === 'object' ? material : {}
            const id = Number(normalizedMaterial.id ?? 0)
            const title = typeof normalizedMaterial.title === 'string' ? normalizedMaterial.title.trim() : ''
            const subject = typeof normalizedMaterial.subject === 'string' ? normalizedMaterial.subject.trim() : ''
            const topic = typeof normalizedMaterial.topic === 'string'
                ? normalizedMaterial.topic.trim()
                : (typeof normalizedMaterial.area === 'string' ? normalizedMaterial.area.trim() : '')
            const unit = typeof normalizedMaterial.unit === 'string' ? normalizedMaterial.unit.trim() : ''
            const type = typeof normalizedMaterial.type === 'string' ? normalizedMaterial.type.trim() : ''
            const status = typeof normalizedMaterial.status === 'string' ? normalizedMaterial.status.trim() : ''
            const attachmentsCount = Number(normalizedMaterial.attachments_count ?? 0)
            const sourceSchoolId = Number(normalizedMaterial.source_school_id ?? normalizedMaterial.school_id ?? 0)
            const sourceUserId = Number(normalizedMaterial.source_user_id ?? normalizedMaterial.user_id ?? 0)
            const sourceSchoolLabel = typeof normalizedMaterial.source_school_label === 'string'
                ? normalizedMaterial.source_school_label.trim()
                : ''
            const sourceUserLabel = typeof normalizedMaterial.source_user_label === 'string'
                ? normalizedMaterial.source_user_label.trim()
                : ''

            return {
                id: Number.isFinite(id) && id > 0 ? id : null,
                title,
                subject,
                topic,
                unit,
                type,
                status,
                attachments_count: Number.isFinite(attachmentsCount) && attachmentsCount > 0 ? attachmentsCount : 0,
                source_school_id: Number.isFinite(sourceSchoolId) && sourceSchoolId > 0 ? sourceSchoolId : null,
                source_school_label: sourceSchoolLabel,
                source_user_id: Number.isFinite(sourceUserId) && sourceUserId > 0 ? sourceUserId : null,
                source_user_label: sourceUserLabel,
                is_hopper_material: Boolean(normalizedMaterial.is_hopper_material),
                is_shared_material: Boolean(normalizedMaterial.is_shared_material),
                shared_rule_id: Number.isFinite(Number(normalizedMaterial.shared_rule_id ?? 0)) && Number(normalizedMaterial.shared_rule_id ?? 0) > 0
                    ? Number(normalizedMaterial.shared_rule_id)
                    : null,
                attachments: (Array.isArray(normalizedMaterial.attachments) ? normalizedMaterial.attachments : [])
                    .map((attachment) => this.normalizeMaterialPreviewAttachment(attachment))
                    .filter((attachment) => attachment.id),
            }
        },

        normalizeMaterialPreviewAttachment(attachment = null) {
            const normalizedAttachment = attachment && typeof attachment === 'object' ? attachment : {}
            const id = Number(normalizedAttachment.id ?? 0)
            const mimeType = typeof normalizedAttachment.mime_type === 'string' ? normalizedAttachment.mime_type.trim() : ''
            const sizeBytes = Number(normalizedAttachment.size_bytes ?? 0)

            return {
                id: Number.isFinite(id) && id > 0 ? id : null,
                name: typeof normalizedAttachment.name === 'string' ? normalizedAttachment.name.trim() : 'Anhang',
                mime_type: mimeType,
                size_bytes: Number.isFinite(sizeBytes) && sizeBytes > 0 ? sizeBytes : null,
                preview_url: Number.isFinite(id) && id > 0
                    ? `/api/admin/teaching/curricula/${this.curriculum.id}/materials/attachments/${id}/preview`
                    : '',
                download_url: Number.isFinite(id) && id > 0
                    ? `/api/admin/teaching/curricula/${this.curriculum.id}/materials/attachments/${id}/download`
                    : '',
            }
        },

        normalizeAttachedMaterials(materials = []) {
            const seen = new Set()

            return (Array.isArray(materials) ? materials : [])
                .map((material) => this.normalizeAttachedMaterial(material))
                .filter((material) => {
                    if (!material.id || material.title === '' || seen.has(material.id)) {
                        return false
                    }

                    seen.add(material.id)

                    return true
                })
                .map(({ attachments, ...material }) => material)
        },

        normalizeTopic(topic = null, index = 0) {
            const normalizedTopic = topic && typeof topic === 'object' ? topic : {}
            return {
                id: normalizedTopic.id || `topic-${index}`,
                title: typeof normalizedTopic.title === 'string' ? normalizedTopic.title.trim() : '',
                units: (Array.isArray(normalizedTopic.units) ? normalizedTopic.units : [])
                    .map((unit, unitIndex) => this.normalizeUnit(unit, unitIndex)),
            }
        },

        buildTopicPayload(topic = null, overrides = {}) {
            return this.normalizeTopic({
                ...(topic && typeof topic === 'object' ? topic : {}),
                ...overrides,
            })
        },

        normalizeUnit(unit = null, index = 0) {
            const normalizedUnit = unit && typeof unit === 'object' ? unit : {}

            return {
                id: normalizedUnit.id || `unit-${index}`,
                title: typeof normalizedUnit.title === 'string' ? normalizedUnit.title.trim() : '',
                is_exam: Boolean(normalizedUnit.is_exam),
                materials: this.normalizeAttachedMaterials(normalizedUnit.materials),
            }
        },

        buildUnitPayload(unit = null, overrides = {}) {
            return this.normalizeUnit({
                ...(unit && typeof unit === 'object' ? unit : {}),
                ...overrides,
            })
        },

        assignmentState(item = null) {
            const normalizedItem = item && typeof item === 'object' ? item : {}
            const assignmentType = ['none', 'all_weeks', 'month', 'weeks'].includes(normalizedItem.assignment_type)
                ? normalizedItem.assignment_type
                : 'none'

            return {
                assignment_type: assignmentType,
                month_keys: assignmentType === 'month' ? [...normalizedItem.month_keys] : [],
                month_week_counts: assignmentType === 'month'
                    ? this.normalizeMonthWeekCounts(normalizedItem.month_week_counts, normalizedItem.month_keys)
                    : {},
                week_keys: assignmentType === 'weeks' ? [...normalizedItem.week_keys] : [],
            }
        },

        clearAssignment(item = null) {
            return {
                ...(item && typeof item === 'object' ? item : {}),
                assignment_type: 'none',
                month_key: null,
                month_keys: [],
                month_week_counts: {},
                week_keys: [],
            }
        },

        applyMonthAssignment(item = null, monthKeys = []) {
            const nextMonthKeys = [...new Set((Array.isArray(monthKeys) ? monthKeys : []).filter(Boolean).map((monthKey) => String(monthKey).trim()))].sort()

            return {
                ...(item && typeof item === 'object' ? item : {}),
                assignment_type: 'month',
                month_key: nextMonthKeys[0] ?? null,
                month_keys: nextMonthKeys,
                month_week_counts: this.normalizeMonthWeekCounts(item?.month_week_counts, nextMonthKeys),
                week_keys: [],
            }
        },

        applyWeekAssignment(item = null, weekKeys = []) {
            const nextWeekKeys = [...new Set(
                (Array.isArray(weekKeys) ? weekKeys : [])
                    .filter(Boolean)
                    .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                    .filter(Boolean),
            )].sort()

            return {
                ...(item && typeof item === 'object' ? item : {}),
                assignment_type: 'weeks',
                month_key: null,
                month_keys: [],
                month_week_counts: {},
                week_keys: nextWeekKeys,
            }
        },

        monthWeekCount(item = null, monthKey = null) {
            const normalizedMonthKey = this.normalizeMonthAssignmentKey(monthKey)
            const normalizedItem = item && typeof item === 'object' ? item : {}
            const monthWeekCounts = this.normalizeMonthWeekCounts(
                normalizedItem.month_week_counts,
                Array.isArray(normalizedItem.month_keys) ? normalizedItem.month_keys : [],
            )

            return normalizedMonthKey ? (monthWeekCounts[normalizedMonthKey] ?? null) : null
        },

        weekCountLabel(weeks = null) {
            const normalizedWeeks = Number.parseInt(weeks, 10)

            if (normalizedWeeks === 0) {
                return 'Ganzer Monat'
            }

            if (!Number.isFinite(normalizedWeeks) || normalizedWeeks < 1) {
                return ''
            }

            return normalizedWeeks === 1 ? '1 Woche' : `${normalizedWeeks} Wochen`
        },

        monthTeachingWeekCount(month = null) {
            return this.monthTeachingWeeks(month).length
        },

        monthTeachingWeekCountLabel(month = null) {
            const weekCount = this.monthTeachingWeekCount(month)
            const assignedWeekCount = this.monthAssignedTeachingWeekCount(month)

            return weekCount === 1 ? `${assignedWeekCount}/1 Woche` : `${assignedWeekCount}/${weekCount} Wochen`
        },

        monthTeachingWeekCountIsOverassigned(month = null) {
            return this.monthAssignedTeachingWeekCount(month) > this.monthTeachingWeekCount(month)
        },

        monthTeachingWeeks(month = null) {
            if (!Array.isArray(month?.weeks)) {
                return []
            }

            return month.weeks.filter((week) => (
                week?.weekKey
                && !this.isFreeTeachingWeek(week)
                && this.weekBelongsToMonth(week, month)
            ))
        },

        monthAssignedTeachingWeekCount(month = null) {
            const monthKey = this.normalizeMonthAssignmentKey(month?.assignmentKey)
            const teachingWeekKeys = this.monthTeachingWeeks(month)
                .map((week) => String(week?.weekKey || '').trim())
                .filter(Boolean)
            const teachingWeekKeySet = new Set(teachingWeekKeys)
            const topics = Array.isArray(this.curriculumTopics) ? this.curriculumTopics : []
            const assignedWeekKeys = new Set()
            let assignedWeekCount = 0

            if (!monthKey || teachingWeekKeys.length === 0) {
                return 0
            }

            const addAssignmentUsage = (entry = null, parentTopic = null) => {
                if (!entry?.title) {
                    return
                }

                if (entry.assignment_type === 'weeks') {
                    const weekKeys = parentTopic
                        ? this.effectiveUnitWeekKeys(parentTopic, entry)
                        : (Array.isArray(entry.week_keys) ? entry.week_keys : [])
                            .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                            .filter(Boolean)

                    weekKeys.forEach((weekKey) => {
                        if (teachingWeekKeySet.has(weekKey)) {
                            assignedWeekKeys.add(weekKey)
                        }
                    })

                    return
                }

                assignedWeekCount += this.assignmentWeekUsageForMonth(entry, monthKey, teachingWeekKeys.length)
            }

            topics.forEach((topic) => {
                const units = Array.isArray(topic.units) ? topic.units : []

                if (!this.topicHasUnitAssignmentUsageForMonth(topic, monthKey, teachingWeekKeySet, teachingWeekKeys.length)) {
                    addAssignmentUsage(topic)
                }

                units.forEach((unit) => {
                    addAssignmentUsage(unit, topic)
                })
            })

            return assignedWeekCount + assignedWeekKeys.size
        },

        assignmentWeekUsageForMonth(entry = null, monthKey = null, totalWeekCount = 0) {
            const assignmentType = String(entry?.assignment_type || 'none')

            if (assignmentType === 'all_weeks') {
                return 0
            }

            const monthKeys = Array.isArray(entry?.month_keys) ? entry.month_keys : []

            if (assignmentType !== 'month' || !monthKeys.includes(monthKey)) {
                return 0
            }

            const monthWeekCount = this.monthWeekCount(entry, monthKey)

            if (monthWeekCount === 0) {
                return totalWeekCount
            }

            const assignedWeekCount = Number.parseInt(monthWeekCount, 10)

            return Number.isFinite(assignedWeekCount) && assignedWeekCount > 0
                ? Math.min(assignedWeekCount, totalWeekCount)
                : 1
        },

        topicHasUnitAssignmentUsageForMonth(topic = null, monthKey = null, teachingWeekKeySet = new Set(), totalWeekCount = 0) {
            return (Array.isArray(topic?.units) ? topic.units : []).some((unit) => (
                this.assignmentHasUsageForMonth(unit, monthKey, teachingWeekKeySet, totalWeekCount, topic)
            ))
        },

        assignmentHasUsageForMonth(
            entry = null,
            monthKey = null,
            teachingWeekKeySet = new Set(),
            totalWeekCount = 0,
            parentTopic = null,
        ) {
            if (!entry?.title) {
                return false
            }

            if (entry.assignment_type === 'weeks') {
                const weekKeys = parentTopic
                    ? this.effectiveUnitWeekKeys(parentTopic, entry)
                    : (Array.isArray(entry.week_keys) ? entry.week_keys : [])
                        .map((weekKey) => this.normalizeWeekAssignmentKey(weekKey))
                        .filter(Boolean)

                return weekKeys.some((weekKey) => teachingWeekKeySet.has(weekKey))
            }

            return this.assignmentWeekUsageForMonth(entry, monthKey, totalWeekCount) > 0
        },

        openOverloadedMonthShiftDialog(month = null) {
            if (!this.monthTeachingWeekCountIsOverassigned(month) || this.isPageActionLocked || this.topicSaving) {
                return
            }

            this.overloadedMonthShiftMonthKey = this.normalizeMonthAssignmentKey(month?.assignmentKey)
            this.overloadedMonthShiftDialogOpen = Boolean(this.overloadedMonthShiftMonthKey)
        },

        closeOverloadedMonthShiftDialog(force = false) {
            if (this.overloadedMonthShiftSaving && !force) {
                return
            }

            this.overloadedMonthShiftDialogOpen = false
            this.overloadedMonthShiftMonthKey = null
        },

        async confirmOverloadedMonthShift() {
            if (this.overloadedMonthShiftSaving || !this.overloadedMonthShiftMonthKey) {
                return
            }

            const month = this.visibleMonths.find((entry) => entry.assignmentKey === this.overloadedMonthShiftMonthKey)
            const topics = this.shiftOverloadedMonthAssignments(month)

            if (!topics) {
                this.closeOverloadedMonthShiftDialog()
                return
            }

            this.overloadedMonthShiftSaving = true
            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics,
                }, 'Termine konnten nicht nach unten geschoben werden.')

                if (updatedCurriculum) {
                    this.closeOverloadedMonthShiftDialog(true)
                }
            } finally {
                this.topicSaving = false
                this.overloadedMonthShiftSaving = false
            }
        },

        shiftOverloadedMonthAssignments(month = null) {
            const monthKey = this.normalizeMonthAssignmentKey(month?.assignmentKey)

            if (!monthKey || !this.monthTeachingWeekCountIsOverassigned(month)) {
                return null
            }

            const shiftMonths = this.visibleMonthsFrom(monthKey)
            const shiftMonthKeys = shiftMonths.map((entry) => entry.assignmentKey)
            const shiftMonthKeySet = new Set(shiftMonthKeys)
            const topics = this.curriculumTopics.map((topic) => this.buildTopicPayload(topic))
            const chunks = this.monthAssignmentShiftChunks(topics, shiftMonths)

            if (chunks.length === 0) {
                return null
            }

            chunks.forEach((chunk) => {
                this.updateMonthAssignmentAtPath(topics, chunk, (item) => (
                    this.removeMonthAssignment(item, chunk.monthKey)
                ))
            })

            let monthIndex = 0
            let remainingMonthCapacity = this.monthTeachingWeekCount(shiftMonths[monthIndex])

            for (const chunk of chunks) {
                let remainingWeeks = chunk.weekCount

                while (remainingWeeks > 0) {
                    while (remainingMonthCapacity <= 0 && monthIndex < shiftMonths.length - 1) {
                        monthIndex++
                        remainingMonthCapacity = this.monthTeachingWeekCount(shiftMonths[monthIndex])
                    }

                    if (remainingMonthCapacity <= 0 || monthIndex >= shiftMonths.length) {
                        return null
                    }

                    const assignedWeeks = Math.min(remainingWeeks, remainingMonthCapacity)
                    const targetMonthKey = shiftMonths[monthIndex].assignmentKey

                    this.updateMonthAssignmentAtPath(topics, chunk, (item) => (
                        this.addMonthAssignment(item, targetMonthKey, assignedWeeks)
                    ))

                    remainingWeeks -= assignedWeeks
                    remainingMonthCapacity -= assignedWeeks
                }
            }

            return shiftMonthKeySet.size ? topics.map((topic) => this.buildTopicPayload(topic)) : null
        },

        visibleMonthsFrom(monthKey = null) {
            const normalizedMonthKey = this.normalizeMonthAssignmentKey(monthKey)
            const startIndex = this.visibleMonths.findIndex((month) => month.assignmentKey === normalizedMonthKey)

            return startIndex >= 0 ? this.visibleMonths.slice(startIndex) : []
        },

        monthAssignmentShiftChunks(topics = [], months = []) {
            return months.flatMap((month) => {
                const monthKey = this.normalizeMonthAssignmentKey(month?.assignmentKey)
                const teachingWeekKeys = this.monthTeachingWeeks(month)
                    .map((week) => String(week?.weekKey || '').trim())
                    .filter(Boolean)
                const teachingWeekKeySet = new Set(teachingWeekKeys)
                const totalWeekCount = teachingWeekKeys.length

                if (!monthKey || totalWeekCount === 0) {
                    return []
                }

                return topics.flatMap((topic, topicIndex) => {
                    const chunks = []
                    const units = Array.isArray(topic.units) ? topic.units : []

                    if (
                        topic.title
                        && !this.topicHasUnitAssignmentUsageForMonth(topic, monthKey, teachingWeekKeySet, totalWeekCount)
                    ) {
                        const weekCount = this.assignmentWeekUsageForMonth(topic, monthKey, totalWeekCount)

                        if (weekCount > 0) {
                            chunks.push({
                                topicIndex,
                                unitIndex: null,
                                monthKey,
                                weekCount,
                            })
                        }
                    }

                    units.forEach((unit, unitIndex) => {
                        const weekCount = this.assignmentWeekUsageForMonth(unit, monthKey, totalWeekCount)

                        if (unit.title && weekCount > 0) {
                            chunks.push({
                                topicIndex,
                                unitIndex,
                                monthKey,
                                weekCount,
                            })
                        }
                    })

                    return chunks
                })
            })
        },

        updateMonthAssignmentAtPath(topics = [], chunk = null, updater = null) {
            if (!chunk || typeof updater !== 'function' || !topics[chunk.topicIndex]) {
                return
            }

            if (chunk.unitIndex === null) {
                topics[chunk.topicIndex] = updater(topics[chunk.topicIndex])
                return
            }

            const topic = topics[chunk.topicIndex]
            const units = Array.isArray(topic.units) ? [...topic.units] : []

            if (!units[chunk.unitIndex]) {
                return
            }

            units[chunk.unitIndex] = updater(units[chunk.unitIndex])
            topics[chunk.topicIndex] = {
                ...topic,
                units,
            }
        },

        removeMonthAssignment(item = null, monthKey = null) {
            const normalizedMonthKey = this.normalizeMonthAssignmentKey(monthKey)

            if (item?.assignment_type !== 'month' || !normalizedMonthKey) {
                return item
            }

            const nextMonthKeys = (Array.isArray(item.month_keys) ? item.month_keys : [])
                .filter((entry) => entry !== normalizedMonthKey)
            const nextMonthWeekCounts = { ...(item.month_week_counts || {}) }
            delete nextMonthWeekCounts[normalizedMonthKey]

            if (nextMonthKeys.length === 0) {
                return this.clearAssignment(item)
            }

            return {
                ...item,
                month_key: nextMonthKeys[0] ?? null,
                month_keys: nextMonthKeys,
                month_week_counts: this.normalizeMonthWeekCounts(nextMonthWeekCounts, nextMonthKeys),
            }
        },

        addMonthAssignment(item = null, monthKey = null, weekCount = 1) {
            const normalizedMonthKey = this.normalizeMonthAssignmentKey(monthKey)
            const normalizedWeekCount = Number.parseInt(weekCount, 10)

            if (!normalizedMonthKey || !Number.isFinite(normalizedWeekCount) || normalizedWeekCount <= 0) {
                return item
            }

            const existingMonthKeys = item?.assignment_type === 'month' && Array.isArray(item.month_keys)
                ? item.month_keys
                : []
            const monthKeys = [...new Set([...existingMonthKeys, normalizedMonthKey])].sort()
            const monthWeekCounts = this.normalizeMonthWeekCounts(item?.month_week_counts, monthKeys)
            const existingWeekCount = Object.prototype.hasOwnProperty.call(item?.month_week_counts || {}, normalizedMonthKey)
                ? Number.parseInt(monthWeekCounts[normalizedMonthKey] ?? 0, 10)
                : 0
            monthWeekCounts[normalizedMonthKey] = Math.min(4, Math.max(1, existingWeekCount + normalizedWeekCount))

            return {
                ...(item && typeof item === 'object' ? item : {}),
                assignment_type: 'month',
                month_key: monthKeys[0] ?? null,
                month_keys: monthKeys,
                month_week_counts: this.normalizeMonthWeekCounts(monthWeekCounts, monthKeys),
                week_keys: [],
            }
        },

        weekHasAssignmentsForMonth(week = null, month = null) {
            const weekKey = String(week?.weekKey || '').trim()
            const monthKey = String(month?.assignmentKey || '').trim()

            if (!weekKey || !monthKey) {
                return false
            }

            return this.curriculumTopics.some((topic) => {
                if (!topic.title) {
                    return false
                }

                if (
                    topic.assignment_type === 'all_weeks'
                    || (topic.assignment_type === 'month' && topic.month_keys.includes(monthKey))
                    || (topic.assignment_type === 'weeks' && topic.week_keys.includes(weekKey))
                ) {
                    return true
                }

                return topic.units.some((unit) => (
                    Boolean(unit.title)
                    && (
                        unit.assignment_type === 'all_weeks'
                        || (unit.assignment_type === 'month' && unit.month_keys.includes(monthKey))
                        || this.effectiveUnitWeekKeys(topic, unit).includes(weekKey)
                    )
                ))
            })
        },

        isFreeTeachingWeek(week = null) {
            return this.weekdayKeysForWeek(week).some((weekDayKey) => this.isFreeWeek(weekDayKey))
        },

        weekdayKeysForWeek(week = null) {
            const weekStart = new Date(`${String(week?.weekKey || '').trim()}T00:00:00`)

            if (Number.isNaN(weekStart.getTime())) {
                return []
            }

            return Array.from({ length: 5 }, (_, dayOffset) => {
                const date = new Date(weekStart)
                date.setDate(date.getDate() + dayOffset)

                return this.formatDateKey(date)
            })
        },

        weekBelongsToMonth(week = null, month = null) {
            const monthIndex = Number.parseInt(month?.month, 10)
            const year = Number.parseInt(month?.year, 10)
            const weekStart = new Date(`${String(week?.weekKey || '').trim()}T00:00:00`)

            if (!Number.isInteger(monthIndex) || !Number.isInteger(year) || Number.isNaN(weekStart.getTime())) {
                return false
            }

            let matchingWeekdays = 0

            for (let dayOffset = 0; dayOffset < 5; dayOffset++) {
                const date = new Date(weekStart)
                date.setDate(date.getDate() + dayOffset)

                if (date.getMonth() === monthIndex && date.getFullYear() === year) {
                    matchingWeekdays++
                }
            }

            return matchingWeekdays >= 3
        },

        hasWeekCount(weeks = null) {
            const normalizedWeeks = Number.parseInt(weeks, 10)

            return Number.isFinite(normalizedWeeks) && normalizedWeeks >= 0 && normalizedWeeks <= 4
        },

        weekCountOptionLabel(weeks = null) {
            const normalizedWeeks = Number.parseInt(weeks, 10)

            return normalizedWeeks === 0 ? 'Ganzer Monat' : String(normalizedWeeks)
        },

        applyMonthWeekCount(item = null, monthKey = null, weeks = null) {
            const normalizedMonthKey = this.normalizeMonthAssignmentKey(monthKey)
            const normalizedItem = item && typeof item === 'object' ? item : {}
            const monthKeys = Array.isArray(normalizedItem.month_keys) ? normalizedItem.month_keys : []
            const monthWeekCounts = this.normalizeMonthWeekCounts(normalizedItem.month_week_counts, monthKeys)
            const normalizedWeeks = Number.parseInt(weeks, 10)

            if (!normalizedMonthKey || !monthKeys.includes(normalizedMonthKey)) {
                return {
                    ...normalizedItem,
                    month_week_counts: monthWeekCounts,
                }
            }

            monthWeekCounts[normalizedMonthKey] = Number.isFinite(normalizedWeeks) && normalizedWeeks >= 0 && normalizedWeeks <= 4
                ? normalizedWeeks
                : 1

            return {
                ...normalizedItem,
                month_week_counts: this.normalizeMonthWeekCounts(monthWeekCounts, monthKeys),
            }
        },

        buildTopicUnitDistribution() {
            return {
                units: [],
                assignedCount: 0,
            }
        },

        monthKeyFromWeekKey(weekKey) {
            return String(weekKey).slice(0, 7)
        },

        visibleWeekKeys() {
            return this.visibleMonths.flatMap((month) => (
                Array.isArray(month.weeks)
                    ? month.weeks.map((week) => week.weekKey).filter(Boolean)
                    : []
            ))
        },

        shiftWeekKeyByWeeks(weekKey, delta) {
            const visibleWeekKeys = this.visibleWeekKeys()
            const currentIndex = visibleWeekKeys.indexOf(weekKey)

            if (currentIndex === -1) {
                return null
            }

            const shiftedIndex = currentIndex + delta
            if (shiftedIndex < 0 || shiftedIndex >= visibleWeekKeys.length) {
                return null
            }

            return visibleWeekKeys[shiftedIndex] ?? null
        },

        assignmentStatesOverlap(first, second) {
            if (!first || !second || first.assignment_type === 'none' || second.assignment_type === 'none') {
                return false
            }

            if (first.assignment_type === 'all_weeks' || second.assignment_type === 'all_weeks') {
                return true
            }

            if (first.assignment_type === 'month' && second.assignment_type === 'month') {
                return first.month_keys.some((monthKey) => second.month_keys.includes(monthKey))
            }

            return false
        },

        assignmentSequenceEntries() {
            return this.curriculumTopics.flatMap((topic) => {
                const entries = [{
                    topicId: topic.id,
                    unitId: null,
                }]

                topic.units.forEach((unit) => {
                    entries.push({
                        topicId: topic.id,
                        unitId: unit.id,
                    })
                })

                return entries
            })
        },

        removeAssignmentOverlap(target, blocking) {
            const targetState = this.assignmentState(target)
            const blockingState = this.assignmentState(blocking)

            if (targetState.assignment_type === 'none' || blockingState.assignment_type === 'none') {
                return target
            }

            if (targetState.assignment_type === 'all_weeks' || blockingState.assignment_type === 'all_weeks') {
                return this.clearAssignment(target)
            }

            if (targetState.assignment_type === 'month' && blockingState.assignment_type === 'month') {
                const remainingMonthKeys = targetState.month_keys.filter((monthKey) => !blockingState.month_keys.includes(monthKey))

                return remainingMonthKeys.length
                    ? this.applyMonthAssignment(target, remainingMonthKeys)
                    : this.clearAssignment(target)
            }

            return target
        },

        reconcileTopicUnitAssignments(topic, winner = null) {
            const normalizedTopic = this.buildTopicPayload(topic)
            const units = normalizedTopic.units.map((unit) => this.buildUnitPayload(unit))

            if (winner === 'topic') {
                return {
                    ...normalizedTopic,
                    units: units.map((unit) => this.removeAssignmentOverlap(unit, normalizedTopic)),
                }
            }

            if (winner === 'units') {
                let nextTopic = normalizedTopic

                units.forEach((unit) => {
                    nextTopic = this.removeAssignmentOverlap(nextTopic, unit)
                })

                return {
                    ...nextTopic,
                    units,
                }
            }

            return {
                ...normalizedTopic,
                units,
            }
        },

        findTopic(topicId) {
            return this.curriculumTopics.find((topic) => topic.id === topicId) ?? null
        },

        findUnit(topicId, unitId) {
            const topic = this.findTopic(topicId)
            if (!topic) return null

            return topic.units.find((unit) => unit.id === unitId) ?? null
        },

        attachedMaterialSubtitle(material) {
            const parts = [
                this.materialSourceLabel(material),
                typeof material?.subject === 'string' ? material.subject.trim() : '',
                typeof material?.topic === 'string' ? material.topic.trim() : '',
                typeof material?.unit === 'string' ? material.unit.trim() : '',
                typeof material?.type === 'string' ? material.type.trim() : '',
            ].filter(Boolean)

            if (parts.length) {
                return parts.join(' · ')
            }

            return typeof material?.status === 'string' && material.status.trim() !== ''
                ? material.status.trim()
                : 'Material'
        },

        materialSourceLabel(material) {
            if (!material?.is_hopper_material && !material?.is_shared_material) {
                return ''
            }

            const schoolLabel = typeof material.source_school_label === 'string' ? material.source_school_label.trim() : ''
            const userLabel = typeof material.source_user_label === 'string' ? material.source_user_label.trim() : ''

            if (schoolLabel !== '' && userLabel !== '') {
                return `${schoolLabel} · ${userLabel}`
            }

            return schoolLabel || userLabel || (material?.is_shared_material ? 'Geteiltes Material' : 'Hopper-Material')
        },

        setContentMaterialDialogMode(mode) {
            this.contentMaterialDialogMode = mode

            if (mode === 'search') {
                this.searchContentMaterials(this.contentMaterialSearch)
                return
            }

            this.resetContentMaterialWorkspaceSelection()
            this.ensureContentMaterialClassificationTree()
        },

        async openContentMaterialDialog(target) {
            if (this.topicSaving || target?.type !== 'unit' || !target.topicId || !target.unitId) return

            this.contentMaterialTarget = {
                type: 'unit',
                topicId: target.topicId,
                unitId: target.unitId,
            }
            this.contentMaterialDialogOpen = true
            this.contentMaterialDialogMode = 'search'
            this.contentMaterialSearch = ''
            this.contentMaterialResults = []
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true

            await this.doSearchContentMaterials('')
        },

        closeContentMaterialDialog() {
            if (this.topicSaving) return

            this.contentMaterialDialogOpen = false
            this.contentMaterialDialogMode = 'search'
            this.contentMaterialTarget = null
            this.contentMaterialSearch = ''
            this.contentMaterialResults = []
            this.selectedContentMaterialSource = null
            this.selectedContentMaterialSubject = null
            this.selectedContentMaterialTopic = null
            this.selectedContentMaterialUnit = null
            this.contentMaterialWorkspaceResults = []
            this.contentMaterialWorkspaceError = null
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        searchContentMaterials(value) {
            if (this._contentMaterialSearchTimer) {
                clearTimeout(this._contentMaterialSearchTimer)
            }

            this._contentMaterialSearchTimer = setTimeout(() => {
                this.doSearchContentMaterials(value || '')
            }, 300)
        },

        async doSearchContentMaterials(search) {
            this.contentMaterialsLoading = true

            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/cards`, {
                    params: {
                        search,
                        per_page: 20,
                        shared_only: this.contentMaterialDialogMode === 'shared' ? 1 : undefined,
                    },
                })
                this.contentMaterialResults = Array.isArray(res.data?.data)
                    ? res.data.data.map((card) => this.normalizeAttachedMaterial(card)).filter((card) => card.id)
                    : []
                this.syncContentMaterialPreviewSelection(this.contentMaterialResults)
            } catch {
                this.contentMaterialResults = []
                this.syncContentMaterialPreviewSelection([])
            } finally {
                this.contentMaterialsLoading = false
            }
        },

        async ensureContentMaterialClassificationTree() {
            if (this.contentMaterialClassificationLoading) return

            if (this.contentMaterialClassificationTree.length || this.sharedMaterialClassificationTree.length) {
                return
            }

            this.contentMaterialClassificationLoading = true

            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/config`)
                this.contentMaterialClassificationTree = Array.isArray(res.data?.classification_tree)
                    ? res.data.classification_tree
                    : []
                this.sharedMaterialClassificationTree = Array.isArray(res.data?.shared_classification_tree)
                    ? res.data.shared_classification_tree
                    : []
            } catch {
                this.contentMaterialClassificationTree = []
                this.sharedMaterialClassificationTree = []
                this.contentMaterialWorkspaceError = 'Die Filteroptionen konnten nicht geladen werden.'
            } finally {
                this.contentMaterialClassificationLoading = false
            }
        },

        selectContentMaterialSource(source) {
            this.selectedContentMaterialSource = source || null
            this.selectedContentMaterialSubject = null
            this.selectedContentMaterialTopic = null
            this.selectedContentMaterialUnit = null
            this.contentMaterialWorkspaceResults = []
            this.contentMaterialWorkspaceError = null
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
        },

        async selectContentMaterialSubject(subject) {
            if (!subject?.name) {
                this.resetContentMaterialWorkspaceSelection()
                return
            }

            this.selectedContentMaterialSubject = subject
            this.selectedContentMaterialTopic = null
            this.selectedContentMaterialUnit = null

            await this.loadContentMaterialWorkspaceResults({
                subject: subject?.name || '',
            })
        },

        async selectContentMaterialTopic(topic) {
            if (!this.selectedContentMaterialSubject) {
                return
            }

            this.selectedContentMaterialTopic = topic
            this.selectedContentMaterialUnit = null

            await this.loadContentMaterialWorkspaceResults({
                subject: this.selectedContentMaterialSubject?.name || '',
                topic: topic?.name || '',
            })
        },

        async selectContentMaterialUnit(unit) {
            if (!this.selectedContentMaterialTopic) {
                return
            }

            this.selectedContentMaterialUnit = unit

            await this.loadContentMaterialWorkspaceResults({
                subject: this.selectedContentMaterialSubject?.name || '',
                topic: this.selectedContentMaterialTopic?.name || '',
                unit: unit?.name || '',
            })
        },

        resetContentMaterialWorkspaceSelection() {
            this.selectedContentMaterialSource = null
            this.selectedContentMaterialSubject = null
            this.selectedContentMaterialTopic = null
            this.selectedContentMaterialUnit = null
            this.contentMaterialWorkspaceResults = []
            this.contentMaterialWorkspaceError = null
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        syncContentMaterialPreviewSelection(cards = []) {
            const list = Array.isArray(cards) ? cards : []
            if (!list.length) {
                this.contentMaterialPreviewCard = null
                this.contentMaterialPreviewAttachmentId = null
                return
            }

            const nextCard = list.find((card) => card.id === this.contentMaterialPreviewCard?.id) || list[0]
            this.selectContentMaterialPreview(nextCard)
        },

        selectContentMaterialPreview(card) {
            const normalizedCard = this.normalizeAttachedMaterial(card)
            if (!normalizedCard?.id) {
                this.contentMaterialPreviewCard = null
                this.contentMaterialPreviewAttachmentId = null
                return
            }

            this.contentMaterialPreviewCard = normalizedCard
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        openContentMaterialPreview(attachment, options = {}) {
            const attachmentId = Number(attachment?.id || 0)
            this.contentMaterialPreviewFullscreen = options.fullscreen !== false
            this.contentMaterialPreviewAttachmentId = Number.isFinite(attachmentId) && attachmentId > 0 ? attachmentId : null
            this.contentMaterialPreviewDialogOpen = this.contentMaterialPreviewAttachmentId !== null
        },

        closeContentMaterialPreview() {
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        async fetchCurriculumMaterialCard(materialId) {
            const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/cards/${materialId}`)

            return this.normalizeAttachedMaterial(res.data?.data)
        },

        async openAttachedMaterialDialog(material) {
            if (this.isPageActionLocked || this.topicSaving) return

            const normalizedMaterial = this.normalizeAttachedMaterial(material)
            if (!normalizedMaterial?.id) return

            this.attachedMaterialDialogOpen = true
            this.attachedMaterialDialogLoading = true
            this.attachedMaterialDialogError = null
            this.selectContentMaterialPreview(normalizedMaterial)

            try {
                const card = await this.fetchCurriculumMaterialCard(normalizedMaterial.id)
                if (!card?.id) {
                    throw new Error('missing material card')
                }

                this.selectContentMaterialPreview(card)
            } catch {
                this.attachedMaterialDialogError = 'Die Anhänge des Materials konnten nicht geladen werden.'
            } finally {
                this.attachedMaterialDialogLoading = false
            }
        },

        closeAttachedMaterialDialog() {
            this.attachedMaterialDialogOpen = false
            this.attachedMaterialDialogLoading = false
            this.attachedMaterialDialogError = null
            this.contentMaterialPreviewCard = null
            this.contentMaterialPreviewAttachmentId = null
            this.contentMaterialPreviewDialogOpen = false
            this.contentMaterialPreviewFullscreen = true
        },

        async loadContentMaterialWorkspaceResults(filters = {}) {
            const subject = typeof filters.subject === 'string' ? filters.subject.trim() : ''

            if (subject === '') {
                this.contentMaterialWorkspaceResults = []
                this.contentMaterialWorkspaceError = null

                return
            }

            this.contentMaterialWorkspaceLoading = true
            this.contentMaterialWorkspaceError = null

            try {
                const params = {
                    subject,
                    topic: typeof filters.topic === 'string' ? filters.topic.trim() : '',
                    unit: typeof filters.unit === 'string' ? filters.unit.trim() : '',
                    per_page: 20,
                    shared_only: this.contentMaterialDialogMode === 'shared' ? 1 : undefined,
                    source_user_id: this.contentMaterialDialogMode === 'shared'
                        ? this.selectedContentMaterialSource?.user_id
                        : undefined,
                }
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/cards`, {
                    params,
                })

                this.contentMaterialWorkspaceResults = Array.isArray(res.data?.data)
                    ? res.data.data.map((card) => this.normalizeAttachedMaterial(card)).filter((card) => card.id)
                    : []
                this.syncContentMaterialPreviewSelection(this.contentMaterialWorkspaceResults)
            } catch {
                this.contentMaterialWorkspaceResults = []
                this.contentMaterialWorkspaceError = 'Die Materialien für diese Auswahl konnten nicht geladen werden.'
                this.syncContentMaterialPreviewSelection([])
            } finally {
                this.contentMaterialWorkspaceLoading = false
            }
        },

        currentContentMaterialIds() {
            return (Array.isArray(this.contentMaterialDialogTarget?.materials) ? this.contentMaterialDialogTarget.materials : [])
                .map((material) => Number(material?.id || 0))
                .filter((id) => Number.isFinite(id) && id > 0)
        },

        isContentMaterialAttached(material) {
            const materialId = Number(material?.id || 0)

            return materialId > 0 && this.currentContentMaterialIds().includes(materialId)
        },

        async attachContentMaterial(material) {
            if (
                this.contentMaterialTarget?.type !== 'unit'
                || !this.contentMaterialTarget.topicId
                || !this.contentMaterialTarget.unitId
                || this.topicSaving
            ) return

            const normalizedMaterial = this.normalizeAttachedMaterial(material)
            if (!normalizedMaterial.id || normalizedMaterial.title === '') return
            if (this.isContentMaterialAttached(normalizedMaterial)) return

            const targetTopic = this.findTopic(this.contentMaterialTarget.topicId)
            if (!targetTopic) return

            const nextTopics = this.curriculumTopics.map((topic) => {
                if (topic.id !== targetTopic.id) {
                    return this.buildTopicPayload(topic)
                }

                return this.buildTopicPayload(topic, {
                    units: topic.units.map((unit) => (
                        unit.id === this.contentMaterialTarget.unitId
                            ? this.buildUnitPayload(unit, {
                                materials: [...unit.materials, normalizedMaterial],
                            })
                            : this.buildUnitPayload(unit)
                    )),
                })
            })

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Material konnte nicht hinzugefügt werden.')
            } finally {
                this.topicSaving = false
            }
        },

        async removeUnitMaterial(topicId, unitId, materialId) {
            if (this.topicSaving || this.isPageActionLocked) return

            const nextTopics = this.curriculumTopics.map((topic) => {
                if (topic.id !== topicId) {
                    return this.buildTopicPayload(topic)
                }

                return this.buildTopicPayload(topic, {
                    units: topic.units.map((unit) => (
                        unit.id === unitId
                            ? this.buildUnitPayload(unit, {
                                materials: unit.materials.filter((material) => material.id !== materialId),
                            })
                            : this.buildUnitPayload(unit)
                    )),
                })
            })

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Material konnte nicht entfernt werden.')
            } finally {
                this.topicSaving = false
            }
        },

        isTopicAssignmentEditorOpen(topicId) {
            return this.activeTopicAssignmentId === topicId && this.activeTopicAssignmentUnitId === null
        },

        isUnitAssignmentEditorOpen(topicId, unitId) {
            return this.activeTopicAssignmentId === topicId && this.activeTopicAssignmentUnitId === unitId
        },

        toggleTopicAssignmentEditor(topic) {
            if (this.isTopicAssignmentEditorOpen(topic.id)) {
                this.closeTopicAssignmentEditor()
                return
            }

            this.openTopicAssignmentEditor(topic, this.defaultAssignmentEditorType(topic.assignment_type))
        },

        canDistributeTopicUnits(topic) {
            return this.buildTopicUnitDistribution(topic).assignedCount > 0
        },

        async distributeTopicUnits(topic) {
            if (this.isPageActionLocked || this.topicSaving) {
                return
            }

            const distribution = this.buildTopicUnitDistribution(topic)

            if (!distribution.assignedCount) {
                return
            }

            const nextTopics = this.curriculumTopics.map((entry) => {
                if (entry.id !== topic.id) {
                    return this.buildTopicPayload(entry)
                }

                return this.reconcileTopicUnitAssignments(this.buildTopicPayload(entry, {
                    units: distribution.units,
                }), 'units')
            })

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Einheiten konnten nicht verteilt werden.')
            } finally {
                this.topicSaving = false
            }
        },

        toggleUnitAssignmentEditor(topic, unit) {
            if (this.isUnitAssignmentEditorOpen(topic.id, unit.id)) {
                this.closeTopicAssignmentEditor()
                return
            }

            this.openUnitAssignmentEditor(topic, unit, this.defaultAssignmentEditorType(unit.assignment_type))
        },

        defaultAssignmentEditorType(assignmentType) {
            if (assignmentType === 'all_weeks') {
                return 'all_weeks'
            }

            if (assignmentType === 'none') {
                return 'none'
            }

            return assignmentType === 'month' ? 'month' : 'none'
        },

        openTopicAssignmentEditor(topic, assignmentType = null) {
            if (
                !topic
                || this.topicSaving
                || this.isEditingTopic
                || this.isEditingUnit
                || (this.activeTopicAssignmentId !== null && !this.isTopicAssignmentEditorOpen(topic.id))
            ) {
                return
            }

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = null
            this.activeTopicAssignmentType = assignmentType ?? topic.assignment_type
        },

        openUnitAssignmentEditor(topic, unit, assignmentType = null) {
            if (
                !topic
                || !unit
                || this.topicSaving
                || this.isEditingTopic
                || this.isEditingUnit
                || (this.activeTopicAssignmentId !== null && !this.isUnitAssignmentEditorOpen(topic.id, unit.id))
            ) {
                return
            }

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = unit.id
            this.activeTopicAssignmentType = assignmentType ?? unit.assignment_type
        },

        promptDeleteTopic(topic) {
            if (this.isPageActionLocked || this.topicSaving) return

            this.contentToDelete = {
                type: 'topic',
                title: topic.title,
                topicId: topic.id,
                unitId: null,
            }
            this.contentDeleteDialogOpen = true
        },

        closeTopicDeleteDialog() {
            if (this.topicSaving) return

            this.contentDeleteDialogOpen = false
            this.contentToDelete = null
        },

        async confirmTopicDelete() {
            if (!this.contentToDelete) return

            const deleted = this.contentToDelete.type === 'topic'
                ? await this.deleteTopic(this.contentToDelete.topicId)
                : await this.deleteUnit(this.contentToDelete.topicId, this.contentToDelete.unitId)

            if (deleted) {
                this.closeTopicDeleteDialog()
            }
        },

        closeTopicAssignmentEditor() {
            if (this.topicSaving) return

            this.activeTopicAssignmentId = null
            this.activeTopicAssignmentUnitId = null
            this.activeTopicAssignmentType = null
        },

        monthChipLabel(monthKey) {
            const [year, month] = String(monthKey).split('-')
            const monthIndex = Number.parseInt(month, 10) - 1
            const monthName = MONTH_NAMES[monthIndex] ?? monthKey

            return `${monthName} ${year}`
        },

        monthByAssignmentKey(monthKey = null) {
            const normalizedMonthKey = this.normalizeMonthAssignmentKey(monthKey)

            return this.visibleMonths.find((month) => month.assignmentKey === normalizedMonthKey) ?? null
        },

        isMonthKeyOverassigned(monthKey = null) {
            const month = this.monthByAssignmentKey(monthKey)

            return month ? this.monthTeachingWeekCountIsOverassigned(month) : false
        },

        monthAssignmentChipColor(monthKey = null, item = null, parentTopic = null) {
            return this.isMonthAssignmentChipOverassigned(monthKey, item, parentTopic) ? 'error' : 'primary'
        },

        monthAssignmentChipVariant(monthKey = null, item = null, parentTopic = null) {
            return this.isMonthAssignmentChipOverassigned(monthKey, item, parentTopic) ? 'flat' : 'tonal'
        },

        monthAssignmentChipIcon(monthKey = null, item = null, parentTopic = null) {
            return this.isMonthAssignmentChipOverassigned(monthKey, item, parentTopic) ? 'mdi-alert-circle-outline' : null
        },

        isMonthAssignmentChipOverassigned(monthKey = null, item = null, parentTopic = null) {
            const normalizedMonthKey = this.normalizeMonthAssignmentKey(monthKey)

            if (!item) {
                return this.isMonthKeyOverassigned(normalizedMonthKey)
            }

            return this.monthAssignmentOverflowChunks(normalizedMonthKey)
                .some((chunk) => (
                    chunk.isOverassigned
                    && this.monthAssignmentChunkMatchesItem(chunk, item, parentTopic)
                ))
        },

        monthAssignmentOverflowChunks(monthKey = null) {
            const month = this.monthByAssignmentKey(monthKey)

            if (!month) {
                return []
            }

            const topics = this.curriculumTopics
            const capacity = this.monthTeachingWeekCount(month)
            let assignedWeeks = 0

            return this.monthAssignmentShiftChunks(topics, [month]).map((chunk) => {
                assignedWeeks += chunk.weekCount

                return {
                    ...chunk,
                    isOverassigned: assignedWeeks > capacity,
                }
            })
        },

        monthAssignmentChunkMatchesItem(chunk = null, item = null, parentTopic = null) {
            const topics = this.curriculumTopics
            const topic = topics[chunk?.topicIndex]

            if (!chunk || !item || !topic) {
                return false
            }

            if (chunk.unitIndex === null) {
                return this.assignmentItemsMatch(topic, item)
            }

            const unit = Array.isArray(topic.units) ? topic.units[chunk.unitIndex] : null

            if (parentTopic && !this.assignmentItemsMatch(topic, parentTopic)) {
                return false
            }

            return this.assignmentItemsMatch(unit, item)
        },

        assignmentItemsMatch(first = null, second = null) {
            if (!first || !second) {
                return false
            }

            if (first === second) {
                return true
            }

            return Boolean(first.id && second.id && first.id === second.id)
        },

        isInheritedMonthChipOverassigned(topic = null, monthKey = null) {
            return (Array.isArray(topic?.units) ? topic.units : [])
                .some((unit) => this.isMonthAssignmentChipOverassigned(monthKey, unit, topic))
        },

        topicAssignmentSummary(topic) {
            if (topic.assignment_type === 'none') {
                return 'Keine Zuordnung'
            }

            if (topic.assignment_type === 'month') {
                if (topic.month_keys.length === 1) {
                    return this.monthChipLabel(topic.month_keys[0])
                }

                return `${topic.month_keys.length} Monate`
            }

            return this.curriculumScopeLabel
        },

        assignmentSummaryVariant() {
            return 'tonal'
        },


        topicInheritedAssignmentChips(topic) {
            if (!topic || topic.assignment_type !== 'none' || !Array.isArray(topic.units)) {
                return []
            }

            const monthKeys = new Set()
            let hasAllWeeksAssignment = false

            topic.units.forEach((unit) => {
                const assignment = this.assignmentState(unit)

                if (assignment.assignment_type === 'all_weeks') {
                    hasAllWeeksAssignment = true
                    return
                }

                if (assignment.assignment_type === 'month') {
                    assignment.month_keys.forEach((monthKey) => monthKeys.add(monthKey))
                }
            })

            const chips = []

            if (hasAllWeeksAssignment) {
                chips.push({
                    key: 'all-weeks',
                    label: this.curriculumScopeLabel,
                })
            }

            ;[...monthKeys].sort().forEach((monthKey) => {
                chips.push({
                    key: `month-${monthKey}`,
                    label: this.monthChipLabel(monthKey),
                    color: this.isInheritedMonthChipOverassigned(topic, monthKey) ? 'error' : 'primary',
                    variant: this.isInheritedMonthChipOverassigned(topic, monthKey) ? 'flat' : 'tonal',
                    icon: this.isInheritedMonthChipOverassigned(topic, monthKey) ? 'mdi-alert-circle-outline' : null,
                })
            })

            return chips
        },

        unitInheritedWeekKeys(topic, unit) {
            if (!topic || !unit || unit.assignment_type !== 'none' || topic.assignment_type !== 'weeks') {
                return []
            }

            return [...topic.week_keys]
        },

        effectiveUnitWeekKeys(topic, unit) {
            if (!topic || !unit) {
                return []
            }

            if (unit.assignment_type === 'weeks') {
                return [...unit.week_keys]
            }

            return this.unitInheritedWeekKeys(topic, unit)
        },

        shouldShowAssignmentSummaryChip(item) {
            if (!item) {
                return false
            }

            if (item.assignment_type === 'month' || item.assignment_type === 'weeks') {
                return false
            }

            if (item.assignment_type === 'none' && this.topicInheritedAssignmentChips(item).length) {
                return false
            }

            return true
        },

        topicsForMonth(month) {
            const monthKey = month?.assignmentKey
            if (!monthKey) {
                return []
            }

            return this.curriculumTopics.flatMap((topic) => {
                const entries = []

                if (!topic.title) {
                    return entries
                }

                if (topic.assignment_type === 'all_weeks') {
                    entries.push({
                        id: topic.id,
                        topicId: topic.id,
                        topicTitle: topic.title,
                        title: topic.title,
                        isExam: false,
                        weeksCount: null,
                    })
                } else if (topic.assignment_type === 'month' && topic.month_keys.includes(monthKey)) {
                    entries.push({
                        id: topic.id,
                        topicId: topic.id,
                        topicTitle: topic.title,
                        title: topic.title,
                        isExam: false,
                        weeksCount: this.monthWeekCount(topic, monthKey),
                    })
                } else if (
                    topic.assignment_type === 'weeks'
                    && topic.week_keys.some((weekKey) => this.monthKeyFromWeekKey(weekKey) === monthKey)
                ) {
                    entries.push({
                        id: topic.id,
                        topicId: topic.id,
                        topicTitle: topic.title,
                        title: topic.title,
                        isExam: false,
                        weeksCount: null,
                    })
                }

                topic.units.forEach((unit) => {
                    if (!unit.title) {
                        return
                    }

                    if (unit.assignment_type === 'all_weeks') {
                        entries.push({
                            id: `${topic.id}-${unit.id}`,
                            topicId: topic.id,
                            topicTitle: topic.title,
                            unitTitle: unit.title,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                            weeksCount: null,
                        })
                        return
                    }

                    if (unit.assignment_type === 'month' && unit.month_keys.includes(monthKey)) {
                        entries.push({
                            id: `${topic.id}-${unit.id}`,
                            topicId: topic.id,
                            topicTitle: topic.title,
                            unitTitle: unit.title,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                            weeksCount: this.monthWeekCount(unit, monthKey),
                        })
                        return
                    }

                    if (
                        this.effectiveUnitWeekKeys(topic, unit)
                            .some((weekKey) => this.monthKeyFromWeekKey(weekKey) === monthKey)
                    ) {
                        entries.push({
                            id: `${topic.id}-${unit.id}`,
                            topicId: topic.id,
                            topicTitle: topic.title,
                            unitTitle: unit.title,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                            weeksCount: null,
                        })
                    }
                })

                return entries
            })
        },

        monthHasExamEntries(month) {
            return this.monthOverviewEntries(month).some((entry) => entry.isExam)
        },

        topicsForWeek(weekKey) {
            if (!weekKey) {
                return []
            }

            const weekMonthKey = this.monthKeyFromWeekKey(weekKey)

            return this.curriculumTopics.flatMap((topic) => {
                const entries = []

                if (!topic.title) {
                    return entries
                }

                const hasTopicWeekEntry = topic.assignment_type === 'weeks' && topic.week_keys.includes(weekKey)
                const topicMatchesWeek = topic.assignment_type === 'all_weeks'
                    || (topic.assignment_type === 'month' && topic.month_keys.includes(weekMonthKey))

                if (topicMatchesWeek) {
                    entries.push({
                        id: topic.id,
                        topicId: topic.id,
                        topicTitle: topic.title,
                        title: topic.title,
                        isExam: false,
                    })
                }

                const unitEntries = []

                topic.units.forEach((unit) => {
                    if (!unit.title) {
                        return
                    }

                    const unitMatchesWeek = unit.assignment_type === 'all_weeks'
                        || (unit.assignment_type === 'month' && unit.month_keys.includes(weekMonthKey))
                        || this.effectiveUnitWeekKeys(topic, unit).includes(weekKey)

                    if (unitMatchesWeek) {
                        unitEntries.push({
                            id: `${topic.id}-${unit.id}`,
                            topicId: topic.id,
                            unitId: unit.id,
                            topicTitle: topic.title,
                            unitTitle: unit.title,
                            title: this.overviewUnitTitle(topic, unit),
                            isExam: Boolean(unit.is_exam),
                            showTopicPrefix: !hasTopicWeekEntry,
                        })
                    }
                })

                if (hasTopicWeekEntry) {
                    if (unitEntries.length === 0) {
                        entries.push({
                            id: topic.id,
                            topicId: topic.id,
                            topicTitle: topic.title,
                            title: topic.title,
                            isExam: false,
                        })

                        return entries
                    }

                    const mergedUnitTitle = unitEntries.map((entry) => entry.unitTitle).join(', ')

                    entries.push({
                        id: topic.id,
                        topicId: topic.id,
                        topicTitle: topic.title,
                        unitTitle: mergedUnitTitle,
                        showTopicPrefix: true,
                        title: `${topic.title}: ${mergedUnitTitle}`,
                        isExam: unitEntries.some((entry) => entry.isExam),
                    })

                    return entries
                }

                entries.push(...unitEntries)

                return entries
            })
        },

        weekEntriesForMonth(month) {
            if (!Array.isArray(month?.weeks)) {
                return []
            }

            const entryMap = new Map()

            month.weeks.forEach((week) => {
                this.topicsForWeek(week.weekKey).forEach((entry) => {
                    if (!entryMap.has(entry.id)) {
                        entryMap.set(entry.id, entry)
                    }
                })
            })

            return [...entryMap.values()]
        },

        isMonthFullyAssigned(month) {
            if (!Array.isArray(month?.weeks) || month.weeks.length === 0) {
                return false
            }

            return month.weeks.every((week) => this.topicsForWeek(week.weekKey).length > 0)
        },

        monthCollapseState(monthKey) {
            if (!monthKey || !Object.prototype.hasOwnProperty.call(this.manualMonthCollapseStates, monthKey)) {
                return null
            }

            return Boolean(this.manualMonthCollapseStates[monthKey])
        },

        isMonthFullyAssignedForCurriculum(month, curriculum = this.curriculum) {
            if (!Array.isArray(month?.weeks) || month.weeks.length === 0) {
                return false
            }

            const topics = (Array.isArray(curriculum?.topics) ? curriculum.topics : [])
                .map((topic, index) => this.normalizeTopic(topic, index))

            return month.weeks.every((week) => {
                const weekMonthKey = this.monthKeyFromWeekKey(week.weekKey)

                return topics.some((topic) => {
                    if (
                        Boolean(topic.title)
                        && (
                            topic.assignment_type === 'all_weeks'
                            || (topic.assignment_type === 'month' && topic.month_keys.includes(weekMonthKey))
                            || (topic.assignment_type === 'weeks' && topic.week_keys.includes(week.weekKey))
                        )
                    ) {
                        return true
                    }

                    return topic.units.some((unit) => (
                        Boolean(unit.title)
                        && (
                            unit.assignment_type === 'all_weeks'
                            || (unit.assignment_type === 'month' && unit.month_keys.includes(weekMonthKey))
                            || this.effectiveUnitWeekKeys(topic, unit).includes(week.weekKey)
                        )
                    ))
                })
            })
        },

        closeAssignmentEditorForCompletedMonth(weekKey, curriculum = this.curriculum) {
            if (!this.collapseFullMonths || !weekKey) {
                return
            }

            const month = this.visibleMonths.find((entry) => entry.assignmentKey === this.monthKeyFromWeekKey(weekKey))

            if (!month || !this.isMonthFullyAssignedForCurriculum(month, curriculum)) {
                return
            }

            this.closeTopicAssignmentEditor()
        },

        shouldAutoCollapseMonth(month) {
            if (!this.collapseFullMonths || !this.isMonthFullyAssigned(month)) {
                return false
            }

            return !this.isMonthAssignedToHighlightedItem(month)
        },

        shouldCollapseMonth(month) {
            const monthKey = month?.assignmentKey
            const manualState = this.monthCollapseState(monthKey)

            if (manualState !== null) {
                return manualState
            }

            return this.shouldAutoCollapseMonth(month)
        },

        toggleMonthCollapse(month) {
            const monthKey = month?.assignmentKey

            if (!monthKey) {
                return
            }

            const currentState = this.shouldCollapseMonth(month)
            const nextState = !currentState
            const defaultState = this.shouldAutoCollapseMonth(month)
            const nextCollapseStates = { ...this.manualMonthCollapseStates }

            if (nextState === defaultState) {
                delete nextCollapseStates[monthKey]
            } else {
                nextCollapseStates[monthKey] = nextState
            }

            this.manualMonthCollapseStates = nextCollapseStates
        },

        monthOverviewEntries(month) {
            return this.topicsForMonth(month)
        },

        monthOverviewGroups(month) {
            const groups = new Map()

            this.monthOverviewEntries(month).forEach((entry) => {
                const groupId = entry.topicId ?? entry.id
                const topicTitle = entry.topicTitle ?? entry.title
                const currentGroup = groups.get(groupId) ?? {
                    id: groupId,
                    topicTitle,
                    weeksCount: null,
                    units: [],
                }

                if (entry.unitTitle) {
                    if (!currentGroup.units.some((unit) => unit.id === entry.id)) {
                        currentGroup.units.push({
                            id: entry.id,
                            title: entry.unitTitle,
                            isExam: entry.isExam,
                            weeksCount: entry.weeksCount,
                        })
                    }
                } else {
                    currentGroup.weeksCount = entry.weeksCount
                }

                groups.set(groupId, currentGroup)
            })

            return [...groups.values()]
        },

        weekHasExamEntries(weekKey) {
            return this.topicsForWeek(weekKey).some((entry) => entry.isExam)
        },

        overviewUnitTitle(topic, unit) {
            const topicTitle = topic?.title?.trim()
            const unitTitle = unit?.title?.trim()

            if (!unitTitle) {
                return ''
            }

            if (!topicTitle) {
                return unitTitle
            }

            return `${topicTitle}: ${unitTitle}`
        },

        isMonthAssignedToHighlightedItem(month) {
            const monthKey = month?.assignmentKey
            const assignmentItems = this.highlightedAssignmentItems

            if (this.shouldSuppressActiveAssignmentCalendarHighlight()) {
                return false
            }

            if (!monthKey || assignmentItems.length === 0) {
                return false
            }

            return assignmentItems.some((assignmentItem) => {
                if (assignmentItem.assignment_type === 'all_weeks') {
                    return true
                }

                if (assignmentItem.assignment_type === 'month') {
                    return assignmentItem.month_keys.includes(monthKey)
                }

                return false
            })
        },

        isWeekAssignedToHighlightedItem(weekKey) {
            const assignmentItems = this.highlightedAssignmentItems

            if (this.shouldSuppressActiveAssignmentCalendarHighlight()) {
                return false
            }

            if (assignmentItems.length === 0) {
                return false
            }

            return assignmentItems.some((assignmentItem) => {
                if (assignmentItem.assignment_type === 'all_weeks') {
                    return true
                }

                if (assignmentItem.assignment_type === 'month') {
                    return assignmentItem.month_keys.includes(this.monthKeyFromWeekKey(weekKey))
                }

                return false
            })
        },

        shouldSuppressActiveAssignmentCalendarHighlight() {
            return this.activeTopicAssignmentId !== null
                && this.activeTopicAssignmentType === 'all_weeks'
        },

        buildWeeks(year, month, today) {
            const weeks = []
            const firstDay = new Date(year, month, 1)
            const lastDay = new Date(year, month + 1, 0)

            // Find Monday of the week containing the 1st
            let cursor = new Date(firstDay)
            const dow = cursor.getDay()
            const mondayOffset = dow === 0 ? -6 : 1 - dow
            cursor.setDate(cursor.getDate() + mondayOffset)

            while (cursor <= lastDay || cursor.getDay() !== 1) {
                const days = []
                let weekHasMonthDay = false
                const weekStart = new Date(cursor)

                for (let d = 0; d < 7; d++) {
                    const date = new Date(cursor)
                    const inMonth = date.getMonth() === month && date.getFullYear() === year
                    if (inMonth) weekHasMonthDay = true

                    if (d < 5) {
                        days.push({
                            date: date.toISOString().slice(0, 10),
                            dayNum: date.getDate(),
                            dayName: DAY_NAMES_SHORT[d],
                            isToday: date.getTime() === today.getTime(),
                            outsideMonth: !inMonth,
                        })
                    }
                    cursor.setDate(cursor.getDate() + 1)
                }

                if (!weekHasMonthDay) break

                const friday = new Date(weekStart)
                friday.setDate(friday.getDate() + 4)

                const kw = this.getISOWeek(weekStart)
                const isCurrent = today >= weekStart && today <= friday
                const weekKey = this.formatDateKey(weekStart)

                weeks.push({
                    kw,
                    days,
                    isCurrent,
                    weekKey,
                    rangeLabel: `${weekStart.getDate()}.${weekStart.getMonth() + 1}. – ${friday.getDate()}.${friday.getMonth() + 1}.`,
                })
            }

            return weeks
        },

        formatDateKey(date) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')

            return `${year}-${month}-${day}`
        },

        formatMonthKey(year, monthIndex) {
            return `${year}-${String(monthIndex + 1).padStart(2, '0')}`
        },

        monthKeyForSelectedYear(monthNumber) {
            const normalizedMonth = Number.parseInt(String(monthNumber), 10)

            if (!Number.isInteger(normalizedMonth) || normalizedMonth < 1 || normalizedMonth > 12) {
                return null
            }

            const year = normalizedMonth >= 9 ? this.selectedYear : this.selectedYear + 1

            return `${year}-${String(normalizedMonth).padStart(2, '0')}`
        },

        normalizeMonthAssignmentKey(monthKey) {
            const rawMonthKey = String(monthKey).trim()
            const match = rawMonthKey.match(/^(?:\d{4}-)?(\d{2})$/)

            if (!match) {
                return rawMonthKey
            }

            return this.monthKeyForSelectedYear(match[1]) ?? rawMonthKey
        },

        weekKeyForSelectedYear(isoWeek) {
            const normalizedWeek = Number.parseInt(String(isoWeek), 10)

            if (!Number.isInteger(normalizedWeek) || normalizedWeek < 1 || normalizedWeek > 53) {
                return null
            }

            for (const month of this.allMonths) {
                const matchingWeek = month.weeks.find((week) => week.kw === normalizedWeek)

                if (matchingWeek?.weekKey) {
                    return matchingWeek.weekKey
                }
            }

            return null
        },

        normalizeWeekAssignmentKey(weekKey) {
            const rawWeekKey = String(weekKey).trim()
            const weekStart = new Date(`${rawWeekKey}T00:00:00`)

            if (Number.isNaN(weekStart.getTime())) {
                return rawWeekKey
            }

            return this.weekKeyForSelectedYear(this.getISOWeek(weekStart)) ?? rawWeekKey
        },

        getISOWeek(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
            d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7))
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7)
        },

        monthHue(idx) {
            const base = 220
            return (base + idx * 28) % 360
        },

        topicHue(idx) {
            const base = 200
            return (base + idx * 32) % 360
        },

        calendarScrollElement() {
            const calendarScrollRef = this.$refs.calendarScroll

            if (calendarScrollRef instanceof HTMLElement) {
                return calendarScrollRef
            }

            if (calendarScrollRef?.$el instanceof HTMLElement) {
                return calendarScrollRef.$el
            }

            return null
        },

        firstHighlightedCalendarElement() {
            const calendarScrollElement = this.calendarScrollElement()

            if (!calendarScrollElement) {
                return null
            }

            for (const month of this.visibleMonths) {
                if (!this.isMonthAssignedToHighlightedItem(month)) {
                    continue
                }

                if (!this.shouldCollapseMonth(month)) {
                    const firstAssignedWeek = month.weeks.find((week) => this.isWeekAssignedToHighlightedItem(week.weekKey))

                    if (firstAssignedWeek) {
                        const weekElement = calendarScrollElement.querySelector(`[data-week-key="${firstAssignedWeek.weekKey}"]`)

                        if (weekElement instanceof HTMLElement) {
                            return weekElement
                        }
                    }
                }

                const monthElement = calendarScrollElement.querySelector(`[data-month-key="${month.assignmentKey}"]`)

                if (monthElement instanceof HTMLElement) {
                    return monthElement
                }
            }

            return null
        },

        scrollHighlightedCalendarIntoView() {
            const calendarScrollElement = this.calendarScrollElement()
            const targetElement = this.firstHighlightedCalendarElement()

            if (!calendarScrollElement || !targetElement) {
                return
            }

            const containerRect = calendarScrollElement.getBoundingClientRect()
            const targetRect = targetElement.getBoundingClientRect()
            const topOffset = Math.min(Math.round(containerRect.height * 0.22), 180)
            const top = Math.max(
                0,
                calendarScrollElement.scrollTop + (targetRect.top - containerRect.top) - topOffset,
            )

            if (typeof calendarScrollElement.scrollTo === 'function') {
                calendarScrollElement.scrollTo({
                    top,
                    behavior: 'smooth',
                })
                return
            }

            calendarScrollElement.scrollTop = top
        },

        isTopicSelected(topicId) {
            return this.selectedTopicId === topicId && this.selectedUnitId === null
        },

        isTopicCollapsed(topicId) {
            return Boolean(this.topicCollapseStates?.[topicId])
        },

        toggleTopicCollapse(topicId) {
            if (!topicId) {
                return
            }

            const isCollapsed = this.isTopicCollapsed(topicId)
            const nextTopicCollapseStates = { ...this.topicCollapseStates }

            if (isCollapsed) {
                delete nextTopicCollapseStates[topicId]
                this.topicCollapseStates = nextTopicCollapseStates
                return
            }

            if (this.activeTopicAssignmentId === topicId) {
                this.closeTopicAssignmentEditor()
            }

            if (this.showUnitFormForTopicId === topicId) {
                this.cancelUnitForm()
            }

            nextTopicCollapseStates[topicId] = true
            this.topicCollapseStates = nextTopicCollapseStates
        },

        toggleSelectedTopic(topicId) {
            const shouldDeselectTopic = this.isTopicSelected(topicId)

            this.selectedTopicId = shouldDeselectTopic ? null : topicId
            this.selectedUnitTopicId = null
            this.selectedUnitId = null

        },

        isUnitSelected(topicId, unitId) {
            return this.selectedUnitTopicId === topicId && this.selectedUnitId === unitId
        },

        toggleSelectedUnit(topicId, unitId) {
            const shouldDeselectUnit = this.isUnitSelected(topicId, unitId)

            this.selectedTopicId = shouldDeselectUnit ? null : topicId
            this.selectedUnitTopicId = shouldDeselectUnit ? null : topicId
            this.selectedUnitId = shouldDeselectUnit ? null : unitId

        },

        buildCurriculumPayload(overrides = {}) {
            return {
                title: this.curriculum.title,
                description: this.curriculum.description,
                topics: this.curriculumTopics,
                ...overrides,
            }
        },

        async persistCurriculum(overrides, fallbackMessage) {
            try {
                const response = await axios.put(
                    `/api/admin/teaching/curricula/${this.curriculum.id}`,
                    this.buildCurriculumPayload(overrides),
                )

                const updatedCurriculum = response.data?.data || { ...this.curriculum, ...overrides }
                this.$emit('updated', updatedCurriculum)

                return updatedCurriculum
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || fallbackMessage,
                    type: 'error',
                    timeout: 3000,
                })

                return null
            }
        },

        async persistTopicAssignment(topicId, overrides, fallbackMessage) {
            const nextTopics = this.curriculumTopics.map((topic) => (
                topic.id === topicId
                    ? this.reconcileTopicUnitAssignments(this.buildTopicPayload(topic, overrides), 'topic')
                    : this.buildTopicPayload(topic)
            ))

            this.topicSaving = true

            try {
                return await this.persistCurriculum({
                    topics: nextTopics,
                }, fallbackMessage)
            } finally {
                this.topicSaving = false
            }
        },

        async persistUnitAssignment(topicId, unitId, overrides, fallbackMessage) {
            const nextTopics = this.curriculumTopics.map((topic) => {
                if (topic.id !== topicId) {
                    return this.buildTopicPayload(topic)
                }

                return this.reconcileTopicUnitAssignments(this.buildTopicPayload(topic, {
                    units: topic.units.map((unit) => (
                        unit.id === unitId
                            ? this.buildUnitPayload(unit, overrides)
                            : this.buildUnitPayload(unit)
                    )),
                }), 'units')
            })

            this.topicSaving = true

            try {
                return await this.persistCurriculum({
                    topics: nextTopics,
                }, fallbackMessage)
            } finally {
                this.topicSaving = false
            }
        },

        openTopicForm(topic = null) {
            this.topicFormError = null
            this.showTopicForm = true
            this.topicForm = this.newTopicForm(topic)
        },

        cancelTopicForm() {
            if (this.topicSaving) return

            this.topicFormError = null
            this.showTopicForm = false
            this.topicForm = this.newTopicForm()
        },

        openUnitForm(topicId, unit = null) {
            this.unitFormError = null
            this.showUnitFormForTopicId = topicId
            this.unitForm = this.newUnitForm(topicId, unit)
        },

        openSelectedUnitForm(topicId, unit) {
            if (this.topicSaving || this.isPageActionLocked) return

            if (!this.isUnitSelected(topicId, unit.id)) {
                this.toggleSelectedUnit(topicId, unit.id)
            }

            this.openUnitForm(topicId, unit)
        },

        openUnitMaterialDialog() {
            if (!this.unitForm.topicId || !this.unitForm.id) return

            return this.openContentMaterialDialog({
                type: 'unit',
                topicId: this.unitForm.topicId,
                unitId: this.unitForm.id,
            })
        },

        promptDeleteEditingUnit() {
            if (this.topicSaving || !this.unitForm.topicId || !this.unitForm.id) return

            const topic = this.findTopic(this.unitForm.topicId)
            const unit = topic?.units.find((topicUnit) => topicUnit.id === this.unitForm.id)

            if (!topic || !unit) return

            this.contentToDelete = {
                type: 'unit',
                title: unit.title,
                topicId: topic.id,
                unitId: unit.id,
            }
            this.contentDeleteDialogOpen = true
        },

        cancelUnitForm() {
            if (this.topicSaving) return

            this.unitFormError = null
            this.showUnitFormForTopicId = null
            this.unitForm = this.newUnitForm()
        },

        async activateTopicAssignmentMode(topic, assignmentType) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = null
            this.activeTopicAssignmentType = assignmentType

            if (!['none', 'all_weeks'].includes(assignmentType)) {
                return
            }

            await this.persistTopicAssignment(topic.id, {
                assignment_type: assignmentType,
                month_keys: [],
                week_keys: [],
            }, 'Datumszuordnung konnte nicht gespeichert werden.')
        },

        async activateUnitAssignmentMode(topic, unit, assignmentType) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = unit.id
            this.activeTopicAssignmentType = assignmentType

            if (!['none', 'all_weeks'].includes(assignmentType)) {
                return
            }

            await this.persistUnitAssignment(topic.id, unit.id, {
                assignment_type: assignmentType,
                month_keys: [],
                week_keys: [],
            }, 'Datumszuordnung der Einheit konnte nicht gespeichert werden.')
        },

        async toggleTopicMonthAssignment(topic, monthKey) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = null
            this.activeTopicAssignmentType = 'month'

            const monthKeys = topic.assignment_type === 'month'
                ? [...topic.month_keys]
                : []

            const nextMonthKeys = monthKeys.includes(monthKey)
                ? (monthKeys.length === 1 ? monthKeys : monthKeys.filter((value) => value !== monthKey))
                : [...monthKeys, monthKey].sort()

            await this.persistTopicAssignment(topic.id, {
                assignment_type: 'month',
                month_keys: nextMonthKeys,
                week_keys: [],
            }, 'Monatszuordnung konnte nicht gespeichert werden.')
        },

        async updateTopicMonthWeekCount(topic, monthKey, weeks) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return
            if (topic.assignment_type !== 'month') return

            const updatedTopic = this.applyMonthWeekCount(topic, monthKey, weeks)

            await this.persistTopicAssignment(topic.id, {
                assignment_type: 'month',
                month_keys: updatedTopic.month_keys,
                month_week_counts: updatedTopic.month_week_counts,
                week_keys: [],
            }, 'Wochenanzahl konnte nicht gespeichert werden.')
        },

        async toggleUnitMonthAssignment(topic, unit, monthKey) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return

            this.activeTopicAssignmentId = topic.id
            this.activeTopicAssignmentUnitId = unit.id
            this.activeTopicAssignmentType = 'month'

            const monthKeys = unit.assignment_type === 'month'
                ? [...unit.month_keys]
                : []

            const nextMonthKeys = monthKeys.includes(monthKey)
                ? (monthKeys.length === 1 ? monthKeys : monthKeys.filter((value) => value !== monthKey))
                : [...monthKeys, monthKey].sort()

            await this.persistUnitAssignment(topic.id, unit.id, {
                assignment_type: 'month',
                month_keys: nextMonthKeys,
                week_keys: [],
            }, 'Monatszuordnung der Einheit konnte nicht gespeichert werden.')
        },

        async updateUnitMonthWeekCount(topic, unit, monthKey, weeks) {
            if (this.isEditingTopic || this.isEditingUnit) return
            if (this.topicSaving) return
            if (unit.assignment_type !== 'month') return

            const updatedUnit = this.applyMonthWeekCount(unit, monthKey, weeks)

            await this.persistUnitAssignment(topic.id, unit.id, {
                assignment_type: 'month',
                month_keys: updatedUnit.month_keys,
                month_week_counts: updatedUnit.month_week_counts,
                week_keys: [],
            }, 'Wochenanzahl der Einheit konnte nicht gespeichert werden.')
        },

        async saveTopic() {
            this.topicFormError = null

            const title = this.topicForm.title.trim()
            if (!title) {
                this.topicFormError = 'Bitte einen Thementitel eingeben.'
                return
            }

            const topicId = this.topicForm.id || `topic-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
            const existingTopic = this.findTopic(topicId)
            const normalizedTopic = this.buildTopicPayload(existingTopic, {
                id: topicId,
                title,
                assignment_type: existingTopic?.assignment_type ?? 'none',
            })

            const nextTopics = this.curriculumTopics.some((topic) => topic.id === topicId)
                ? this.curriculumTopics.map((topic) => (
                    topic.id === topicId ? normalizedTopic : this.buildTopicPayload(topic)
                ))
                : [...this.curriculumTopics.map((topic) => this.buildTopicPayload(topic)), normalizedTopic]

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Thema konnte nicht gespeichert werden.')

                if (updatedCurriculum) {
                    this.showTopicForm = false
                    this.topicForm = this.newTopicForm()
                }
            } finally {
                this.topicSaving = false
            }
        },

        async saveUnit() {
            this.unitFormError = null

            const title = this.unitForm.title.trim()
            if (!title) {
                this.unitFormError = 'Bitte einen Einheitentitel eingeben.'
                return
            }

            const topic = this.findTopic(this.unitForm.topicId)
            if (!topic) {
                this.unitFormError = 'Das zugehörige Thema wurde nicht gefunden.'
                return
            }

            const unitId = this.unitForm.id || `unit-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
            const existingUnit = this.findUnit(topic.id, unitId)
            const normalizedUnit = this.buildUnitPayload(existingUnit, {
                id: unitId,
                title,
                is_exam: this.unitForm.is_exam,
                assignment_type: existingUnit?.assignment_type ?? 'none',
            })

            const nextTopics = this.curriculumTopics.map((entry) => {
                if (entry.id !== topic.id) {
                    return this.buildTopicPayload(entry)
                }

                const nextUnits = entry.units.some((unit) => unit.id === unitId)
                    ? entry.units.map((unit) => (
                        unit.id === unitId ? normalizedUnit : this.buildUnitPayload(unit)
                    ))
                    : [...entry.units.map((unit) => this.buildUnitPayload(unit)), normalizedUnit]

                return this.buildTopicPayload(entry, {
                    units: nextUnits,
                })
            })

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Einheit konnte nicht gespeichert werden.')

                if (updatedCurriculum) {
                    this.showUnitFormForTopicId = null
                    this.unitForm = this.newUnitForm()
                }
            } finally {
                this.topicSaving = false
            }
        },

        moveArrayItem(items, fromIndex, direction) {
            const targetIndex = fromIndex + direction
            if (fromIndex < 0 || targetIndex < 0 || fromIndex >= items.length || targetIndex >= items.length) {
                return items
            }

            const nextItems = [...items]
            const [movedItem] = nextItems.splice(fromIndex, 1)
            nextItems.splice(targetIndex, 0, movedItem)

            return nextItems
        },

        async moveTopic(topicId, direction) {
            if (this.topicSaving || this.isPageActionLocked) return

            const topicIndex = this.curriculumTopics.findIndex((topic) => topic.id === topicId)
            const nextTopics = this.moveArrayItem(
                this.curriculumTopics.map((topic) => this.buildTopicPayload(topic)),
                topicIndex,
                direction,
            )

            if (nextTopics === this.curriculumTopics || topicIndex === -1) {
                return
            }

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Reihenfolge der Themen konnte nicht gespeichert werden.')
            } finally {
                this.topicSaving = false
            }
        },

        async moveUnit(topicId, unitId, direction) {
            if (this.topicSaving || this.isPageActionLocked) return

            const topic = this.findTopic(topicId)
            if (!topic) return

            const unitIndex = topic.units.findIndex((unit) => unit.id === unitId)
            if (unitIndex === -1) return

            const reorderedUnits = this.moveArrayItem(
                topic.units.map((unit) => this.buildUnitPayload(unit)),
                unitIndex,
                direction,
            )

            if (reorderedUnits === topic.units) {
                return
            }

            const nextTopics = this.curriculumTopics.map((entry) => (
                entry.id === topicId
                    ? this.buildTopicPayload(entry, { units: reorderedUnits })
                    : this.buildTopicPayload(entry)
            ))

            this.topicSaving = true

            try {
                await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Reihenfolge der Einheiten konnte nicht gespeichert werden.')
            } finally {
                this.topicSaving = false
            }
        },

        async deleteTopic(topicId) {
            const nextTopics = this.curriculumTopics.filter((topic) => topic.id !== topicId)

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Thema konnte nicht entfernt werden.')

                if (updatedCurriculum && this.topicForm.id === topicId) {
                    this.showTopicForm = false
                    this.topicForm = this.newTopicForm()
                }

                if (updatedCurriculum && this.activeTopicAssignmentId === topicId) {
                    this.activeTopicAssignmentId = null
                    this.activeTopicAssignmentUnitId = null
                    this.activeTopicAssignmentType = null
                }

                return updatedCurriculum !== null
            } finally {
                this.topicSaving = false
            }
        },

        async deleteUnit(topicId, unitId) {
            const nextTopics = this.curriculumTopics.map((topic) => {
                if (topic.id !== topicId) {
                    return this.buildTopicPayload(topic)
                }

                return this.buildTopicPayload(topic, {
                    units: topic.units
                        .filter((unit) => unit.id !== unitId)
                        .map((unit) => this.buildUnitPayload(unit)),
                })
            })

            this.topicSaving = true

            try {
                const updatedCurriculum = await this.persistCurriculum({
                    topics: nextTopics,
                }, 'Einheit konnte nicht entfernt werden.')

                if (updatedCurriculum && this.unitForm.id === unitId) {
                    this.showUnitFormForTopicId = null
                    this.unitForm = this.newUnitForm()
                }

                if (updatedCurriculum && this.activeTopicAssignmentUnitId === unitId) {
                    this.activeTopicAssignmentId = null
                    this.activeTopicAssignmentUnitId = null
                    this.activeTopicAssignmentType = null
                }

                return updatedCurriculum !== null
            } finally {
                this.topicSaving = false
            }
        },

        async loadDocuments() {
            this.docsLoading = true
            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/documents`)
                this.documents = res.data?.data || []
            } catch {
                this.documents = []
            } finally {
                this.docsLoading = false
            }
        },

        async onFileUploaded() {
            this.showUploadOptions = false
            await this.loadDocuments()
        },

        selectPreview(doc) {
            if (this.isPageActionLocked) return
            if (doc.source_type === 'material') {
                if (doc.material_card_attachment_id && doc.preview_url) {
                    this.previewDoc = this.previewDoc?.id === doc.id ? null : doc
                    return
                }

                this.openMaterialAttachmentDialog(doc)
                return
            }

            if (doc.source_type !== 'upload') return
            this.previewDoc = this.previewDoc?.id === doc.id ? null : doc
        },

        removeDocument(doc) {
            if (this.isPageActionLocked || !doc) return
            this.documentToDelete = doc
            this.documentDeleteDialogOpen = true
        },

        closeDocumentDeleteDialog() {
            if (this.documentDeleteLoading) return
            this.documentDeleteDialogOpen = false
            this.documentToDelete = null
        },

        async confirmDocumentDelete() {
            if (this.isPageActionLocked) return
            if (!this.documentToDelete?.id) {
                this.closeDocumentDeleteDialog()
                return
            }

            this.documentDeleteLoading = true
            let wasDeleted = false

            try {
                await axios.delete(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/${this.documentToDelete.id}`)
                if (this.previewDoc?.id === this.documentToDelete.id) this.previewDoc = null
                this.documents = this.documents.filter((d) => d.id !== this.documentToDelete.id)
                wasDeleted = true
            } catch {
                // silent
            } finally {
                this.documentDeleteLoading = false
                if (wasDeleted) {
                    this.closeDocumentDeleteDialog()
                }
            }
        },

        searchMaterials(value) {
            if (this._materialSearchTimer) clearTimeout(this._materialSearchTimer)
            this._materialSearchTimer = setTimeout(() => {
                this.doSearchMaterials(value || '')
            }, 300)
        },

        async doSearchMaterials(search) {
            this.materialsLoading = true
            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/materials/cards`, {
                    params: { search, per_page: 20 },
                })
                this.materialResults = Array.isArray(res.data?.data)
                    ? res.data.data.map((card) => this.normalizeAttachedMaterial(card)).filter((card) => card.id)
                    : []
            } catch {
                this.materialResults = []
            } finally {
                this.materialsLoading = false
            }
        },

        async attachMaterial(card) {
            if (this.isPageActionLocked) return
            try {
                const res = await axios.post(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/attach-material`, {
                    material_card_id: card.id,
                })
                const attachedDocument = res.data?.data || null
                const attachmentCount = this.materialFileAttachmentCount(card)
                this.materialDialogOpen = false
                this.showUploadOptions = false
                await this.loadDocuments()
                if (attachedDocument?.id) {
                    const nextDocument = this.documents.find((document) => document.id === attachedDocument.id) || attachedDocument
                    if (attachmentCount === 1) {
                        await this.openMaterialAttachmentDialog(nextDocument)
                        if (this.materialAttachmentOptions.length === 1) {
                            await this.selectMaterialAttachment(this.materialAttachmentOptions[0])
                        }
                        return
                    }

                    await this.openMaterialAttachmentDialog(nextDocument)
                }
            } catch {
                // silent
            }
        },

        async openMaterialAttachmentDialog(doc) {
            if (this.isPageActionLocked || !doc || doc.source_type !== 'material') return

            this.materialAttachmentDialogOpen = true
            this.materialAttachmentDocument = doc
            this.materialAttachmentOptions = []
            this.materialAttachmentError = null
            this.selectedMaterialAttachmentId = doc.material_card_attachment_id || null
            this.materialAttachmentLoading = true

            try {
                const res = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/documents/${doc.id}/material-attachments`)
                this.materialAttachmentOptions = res.data?.data || []
                this.selectedMaterialAttachmentId = res.data?.meta?.selected_attachment_id ?? doc.material_card_attachment_id ?? null
            } catch {
                this.materialAttachmentOptions = []
                this.materialAttachmentError = 'Die Anhänge des Materials konnten nicht geladen werden.'
            } finally {
                this.materialAttachmentLoading = false
            }
        },

        closeMaterialAttachmentDialog() {
            this.materialAttachmentDialogOpen = false
            this.materialAttachmentDocument = null
            this.materialAttachmentOptions = []
            this.materialAttachmentError = null
            this.selectedMaterialAttachmentId = null
        },

        async selectMaterialAttachment(attachment) {
            if (
                this.isPageActionLocked
                || this.savingMaterialAttachment
                || !this.materialAttachmentDocument
                || !attachment?.id
            ) {
                return
            }

            this.savingMaterialAttachment = true

            try {
                const res = await axios.patch(
                    `/api/admin/teaching/curricula/${this.curriculum.id}/documents/${this.materialAttachmentDocument.id}/material-attachment`,
                    {
                        material_card_attachment_id: attachment.id,
                    },
                )

                const updatedDocument = res.data?.data || null
                if (!updatedDocument) {
                    return
                }

                this.documents = this.documents.map((document) => (
                    document.id === updatedDocument.id ? updatedDocument : document
                ))
                this.previewDoc = updatedDocument
                this.savingMaterialAttachment = false
                this.closeMaterialAttachmentDialog()
            } catch {
                this.materialAttachmentError = 'Der ausgewählte Anhang konnte nicht gespeichert werden.'
            } finally {
                this.savingMaterialAttachment = false
            }
        },

        formatBytes(bytes) {
            const size = Number(bytes)
            if (!Number.isFinite(size) || size <= 0) {
                return ''
            }

            if (size < 1024) {
                return `${size} B`
            }

            const units = ['KB', 'MB', 'GB']
            let value = size / 1024
            let unitIndex = 0

            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024
                unitIndex += 1
            }

            return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unitIndex]}`
        },

        contentMaterialPreviewAttachmentIcon(attachment) {
            const mimeType = String(attachment?.mime_type || '').toLowerCase()

            return mimeType.startsWith('image/')
                ? 'mdi-file-image-outline'
                : 'mdi-file-document-outline'
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

        async exportCurriculum() {
            if (this.isExportingCurriculum) {
                return
            }

            this.isExportingCurriculum = true

            try {
                const response = await axios.get(`/api/admin/teaching/curricula/${this.curriculum.id}/export/json`, {
                    responseType: 'blob',
                })

                const disposition = response?.headers?.['content-disposition']
                const serverFileName = this.filenameFromContentDisposition(disposition)
                const fallbackFileName = `Curriculum_${this.curriculum.title}.json`
                const fileName = this.normalizeDownloadFileName(serverFileName || fallbackFileName)
                const blob = response?.data instanceof Blob ? response.data : new Blob([response?.data], {
                    type: 'application/json',
                })
                const objectUrl = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = objectUrl
                link.download = fileName
                document.body.appendChild(link)
                link.click()
                link.remove()
                URL.revokeObjectURL(objectUrl)
            } catch (error) {
                useNotificationStore().notify({
                    status: error?.response?.status,
                    message: error?.response?.data?.message || 'Curriculum konnte nicht exportiert werden.',
                    type: 'error',
                    timeout: 3000,
                })
            } finally {
                this.isExportingCurriculum = false
            }
        },

        async downloadContentMaterialAttachment(attachment) {
            const downloadUrl = String(attachment?.download_url || '').trim()
            if (downloadUrl === '') {
                useNotificationStore().notify({
                    message: 'Datei ist derzeit nicht verfügbar.',
                    type: 'warning',
                    timeout: 3000,
                })
                return
            }

            try {
                const response = await axios.get(downloadUrl, {
                    responseType: 'blob',
                })

                const disposition = response?.headers?.['content-disposition']
                const serverFileName = this.filenameFromContentDisposition(disposition)
                const fallbackName = String(attachment?.name || '').trim()
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
                useNotificationStore().notify({
                    status: error?.response?.status,
                    message: error?.response?.data?.message || 'Datei konnte nicht heruntergeladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
            }
        },

        materialFileAttachmentCount(card) {
            const count = Number(card?.attachments_count ?? 0)
            return Number.isFinite(count) && count > 0 ? count : 0
        },

        materialAttachmentCountLabel(card) {
            const count = this.materialFileAttachmentCount(card)
            if (count === 1) {
                return '1 Anhang'
            }

            return `${count} Anhänge`
        },

        materialPickerSubtitle(card) {
            const parts = []
            const subject = typeof card?.subject === 'string' ? card.subject.trim() : ''
            const attachmentCount = this.materialFileAttachmentCount(card)

            if (subject !== '') {
                parts.push(subject)
            }

            const sourceLabel = this.materialSourceLabel(card)
            if (sourceLabel !== '') {
                parts.push(sourceLabel)
            }

            if (attachmentCount > 0) {
                parts.push(this.materialAttachmentCountLabel(card))
            } else {
                parts.push('Keine Anhänge')
            }

            return parts.join(' · ')
        },
    },
}
</script>

<style scoped>
.curriculum-detail {
    width: 100%;
}

.curriculum-detail__header {
    padding: 16px 18px;
    border: 1px solid rgba(37, 99, 235, 0.22);
    border-radius: 18px;
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.14), transparent 46%),
        rgba(219, 234, 254, 0.62);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}

.curriculum-detail__header-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}

.curriculum-detail__title-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
}

.curriculum-detail__eyebrow {
    margin-bottom: 4px;
    color: #2563eb;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.curriculum-detail__title {
    font-size: clamp(1.45rem, 3vw, 2rem);
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
    margin: 0;
}

.curriculum-detail__desc {
    font-size: 0.88rem;
    color: #475569;
    margin: 4px 0 0;
}

.curriculum-detail__summary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    padding: 8px 12px;
    border: 1px solid rgba(37, 99, 235, 0.14);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: #1e3a8a;
    font-size: 0.78rem;
    font-weight: 700;
}

.curriculum-detail__picker-sheet {
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(15, 23, 42, 0.7);
}

.semester-toggle__btn {
    color: #c7d2fe !important;
    border-color: rgba(148, 163, 184, 0.35) !important;
}

.semester-toggle .v-btn--active.semester-toggle__btn {
    color: #fff !important;
}

/* ---------- Body layout ---------- */
.curriculum-detail__body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 420px);
    align-items: start;
    gap: 16px;
}

.curriculum-detail__body--compact-calendar {
    grid-template-columns: minmax(0, 1fr) minmax(320px, 420px);
}

/* ---------- Calendar ---------- */
.curriculum-detail__calendar-scroll {
    position: sticky;
    top: 72px;
    align-self: flex-start;
    max-height: calc(100vh - 84px);
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    background: rgba(15, 23, 42, 0.32);
    border: 1px solid rgba(148, 163, 184, 0.12);
}

.curriculum-detail__calendar {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    flex-shrink: 0;
    width: clamp(430px, 29vw, 500px);
}

.curriculum-detail__calendar--compact {
    width: clamp(250px, 18vw, 300px);
}

.curriculum-detail__calendar--compact .curriculum-detail__month {
    width: min(100%, 300px);
}

.curriculum-detail__month {
    --accent: hsl(var(--month-hue), 70%, 42%);
    --accent-dim: hsl(var(--month-hue), 50%, 88%);
    --accent-glow: hsl(var(--month-hue), 70%, 50%);
    --month-tint: hsla(var(--month-hue), 80%, 55%, 0.12);
    border-radius: 20px;
    border: 1px solid hsla(var(--month-hue), 55%, 45%, 0.3);
    background: rgba(255, 255, 255, 0.84);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

.curriculum-detail__month-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 14px 20px 8px;
    background:
        linear-gradient(135deg, var(--month-tint), transparent 70%),
        rgba(255, 255, 255, 0.6);
    border: none;
    border-bottom: 1px solid hsla(var(--month-hue), 55%, 45%, 0.28);
    cursor: pointer;
    text-align: left;
}

.curriculum-detail__month-name {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--accent);
    letter-spacing: 0.02em;
}

.curriculum-detail__month-week-meta {
    flex: 0 0 auto;
    margin-left: auto;
    max-width: min(58%, 240px);
    text-align: right;
}

.curriculum-detail__month-week-count {
    display: inline-flex;
    align-items: center;
    gap: 0.24rem;
    padding: 0.16rem 0.48rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.72);
    color: #475569;
    font-size: 0.74rem;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
}

.curriculum-detail__month-week-count--overassigned {
    background: rgba(254, 226, 226, 0.92);
    color: #b91c1c;
}

.curriculum-detail__month-week-warning {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 900;
    line-height: 1;
}

.curriculum-detail__month--with-topics {
    box-shadow: 0 0 0 1px rgba(165, 180, 252, 0.08), 0 14px 30px rgba(15, 23, 42, 0.24);
}

.curriculum-detail__month--with-exams {
    box-shadow: 0 0 0 1px rgba(220, 38, 38, 0.22), 0 14px 30px rgba(127, 29, 29, 0.22);
}

.curriculum-detail__month--topic-selected {
    border-color: rgba(79, 70, 229, 0.62);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.28), 0 16px 34px rgba(79, 70, 229, 0.2);
}

.curriculum-detail__month--collapsed .curriculum-detail__month-topics {
    padding-bottom: 14px;
    border-bottom: none;
}

.curriculum-detail__month--collapsed .curriculum-detail__month-topic-units,
.curriculum-detail__month--collapsed .curriculum-detail__month-topic-unit {
    font-weight: 400;
}

.curriculum-detail__month-topics {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px 18px 4px;
    border-bottom: 1px solid hsl(var(--month-hue), 50%, 30%, 0.18);
}

.curriculum-detail__month-topics-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #000;
}

.curriculum-detail__month-topics-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    color: #000;
    font-size: 0.78rem;
    font-weight: 500;
    line-height: 1.3;
}

.curriculum-detail__month-topic-line {
    display: block;
}

.curriculum-detail__month-topic-name {
    font-weight: 700;
}

.curriculum-detail__month-topic-units {
    font-weight: 500;
}

.curriculum-detail__month-topic-units .curriculum-detail__overview-entry:not(:last-child) {
    margin-right: 0.3rem;
}

.curriculum-detail__month-topic-unit {
    font-weight: 500;
}

.curriculum-detail__month-topic-weeks {
    font-weight: 600;
    opacity: 0.82;
}


.curriculum-detail__overview-entry {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
    max-width: 100%;
    color: inherit;
    white-space: normal;
    overflow-wrap: anywhere;
}

.curriculum-detail__overview-entry--exam {
    color: #4f46e5;
    text-shadow: none;
}

.curriculum-detail__overview-entry-icon {
    color: currentColor;
    flex-shrink: 0;
}

/* ---------- Weeks ---------- */
.curriculum-detail__weeks {
    padding: 10px 12px 14px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    width: 100%;
}

.curriculum-detail__week {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 6px 10px;
    border-radius: 12px;
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent 60%),
        linear-gradient(180deg, rgba(238, 242, 255, 0.95), rgba(224, 231, 255, 0.85)) !important;
    border: 1px solid rgba(99, 102, 241, 0.18) !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    transition: background 0.2s, border-color 0.2s;
    position: relative;
    width: 100%;
    box-sizing: border-box;
}

.curriculum-detail__week--compact {
    gap: 8px;
    padding: 6px 8px;
}

.curriculum-detail__week:hover {
    border-color: rgba(99, 102, 241, 0.3) !important;
}

.curriculum-detail__week .curriculum-detail__week-kw,
.curriculum-detail__week .curriculum-detail__day-name {
    color: #475569;
}

.curriculum-detail__week .curriculum-detail__week-num,
.curriculum-detail__week .curriculum-detail__day-num {
    color: #1e293b;
}

.curriculum-detail__week .curriculum-detail__week-range {
    color: #475569 !important;
}

.curriculum-detail__week .curriculum-detail__week-topics {
    color: #1e293b !important;
    text-shadow: none !important;
}

.curriculum-detail__week .curriculum-detail__day {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.12);
}

.curriculum-detail__week--topic-selectable {
    cursor: pointer;
    border-color: rgba(129, 140, 248, 0.28);
}

.curriculum-detail__week--topic-selectable:hover {
    background: rgba(49, 46, 129, 0.18);
    border-color: rgba(129, 140, 248, 0.42);
}

.curriculum-detail__week--topic-selected {
    background: rgba(79, 70, 229, 0.28) !important;
    border-color: rgba(99, 102, 241, 0.72) !important;
    box-shadow: 0 0 0 1px rgba(129, 140, 248, 0.24), 0 0 22px rgba(79, 70, 229, 0.24);
}

.curriculum-detail__week--current {
    background: rgba(99, 102, 241, 0.12) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
    box-shadow: 0 0 16px rgba(99, 102, 241, 0.15);
}

.curriculum-detail__week--free {
    background: rgba(34, 197, 94, 0.12) !important;
    border-color: rgba(34, 197, 94, 0.35) !important;
    box-shadow: 0 0 16px rgba(34, 197, 94, 0.12);
}

.curriculum-detail__week--with-topics {
    border-color: rgba(96, 165, 250, 0.48);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.2), rgba(30, 41, 59, 0.9) 42%, rgba(15, 23, 42, 0.82));
    box-shadow: 0 0 0 1px rgba(147, 197, 253, 0.14), 0 0 18px rgba(59, 130, 246, 0.16);
}

.curriculum-detail__week--with-topics:hover {
    border-color: rgba(96, 165, 250, 0.48);
    box-shadow: 0 0 0 1px rgba(147, 197, 253, 0.14), 0 0 18px rgba(59, 130, 246, 0.16);
}

.curriculum-detail__week-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.curriculum-detail__week-status-icon {
    color: #16a34a !important;
}

/* ---------- Week number ---------- */
.curriculum-detail__week-number {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 32px;
    flex-shrink: 0;
}

.curriculum-detail__week-kw {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.08em;
}

.curriculum-detail__week-num {
    font-size: 1rem;
    font-weight: 800;
    color: var(--accent);
    line-height: 1;
}

/* ---------- Days grid ---------- */
.curriculum-detail__week-days {
    display: flex;
    gap: 2px;
    flex: 1;
}

.curriculum-detail__day {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 38px;
    border-radius: 10px;
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.06);
    transition: all 0.15s;
}

.curriculum-detail__day--outside {
    opacity: 0.2;
}

.curriculum-detail__day--today {
    background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
    border-color: #818cf8 !important;
    opacity: 1 !important;
    box-shadow: 0 0 14px rgba(99, 102, 241, 0.5);
}

.curriculum-detail__day-name {
    font-size: 0.54rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1;
}

.curriculum-detail__day--today .curriculum-detail__day-name {
    color: #c7d2fe;
}

.curriculum-detail__day-num {
    font-size: 0.82rem;
    font-weight: 700;
    color: #cbd5e1;
    line-height: 1.2;
}

.curriculum-detail__day--today .curriculum-detail__day-num {
    color: #fff;
}

/* ---------- Week range label ---------- */
.curriculum-detail__week-range {
    font-size: 0.7rem;
    color: #475569;
    font-weight: 500;
    white-space: nowrap;
    min-width: 64px;
    text-align: right;
}

.curriculum-detail__week--compact .curriculum-detail__week-range {
    min-width: auto;
}

.curriculum-detail__week--free .curriculum-detail__week-range {
    color: #86efac;
}

.curriculum-detail__week--with-topics .curriculum-detail__week-range {
    color: #bfdbfe;
}

.curriculum-detail__week-range-label {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0;
    border-radius: 999px;
    transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, padding 0.18s ease, transform 0.18s ease;
}

.curriculum-detail__week-range-label--selected {
    padding: 0.2rem 0.62rem;
    background: rgba(79, 70, 229, 0.3);
    color: #1e1b4b !important;
    font-weight: 800;
    letter-spacing: 0.02em;
    box-shadow: 0 8px 16px rgba(79, 70, 229, 0.22);
}

.curriculum-detail__week-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    margin-left: auto;
}

.curriculum-detail__week-topics {
    order: 4;
    flex-basis: 100%;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    column-gap: 10px;
    row-gap: 4px;
    margin-top: -2px;
    color: #dbeafe;
    font-size: 0.74rem;
    font-weight: 600;
    line-height: 1.2;
    text-align: left;
}

.curriculum-detail__week--with-topics .curriculum-detail__week-topics {
    color: #eff6ff;
    text-shadow: 0 1px 10px rgba(37, 99, 235, 0.22);
}

.curriculum-detail__week-topic-label {
    display: inline-block;
    padding: 0.24rem 0.38rem;
    border-radius: 0.36rem;
    background: rgba(250, 204, 21, 0.82);
    color: #1f2937;
    line-height: 1;
    vertical-align: middle;
    text-shadow: none;
}

.curriculum-detail__week-topic-label-topic {
    font-weight: 700;
}

.curriculum-detail__week-topic-label-unit {
    margin-left: 0.22rem;
    font-weight: 400;
}

.curriculum-detail__week-topic-label-unit--no-prefix {
    margin-left: 0;
}

.curriculum-detail__week--with-exams {
    border-color: rgba(220, 38, 38, 0.45) !important;
    box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.1), 0 8px 18px rgba(127, 29, 29, 0.14);
}

.curriculum-detail__week--with-exams .curriculum-detail__week-topics {
    color: #4f46e5 !important;
    text-shadow: none !important;
}

.curriculum-detail__week--topic-selected,
.curriculum-detail__week--topic-selected:hover {
    border-color: rgba(129, 140, 248, 0.52) !important;
}

.curriculum-detail__week-chip {
    font-weight: 700;
    letter-spacing: 0.02em;
}

/* ---------- Side cards ---------- */
.curriculum-detail__side-card {
    position: static;
}

.curriculum-detail__side-card--scrollable {
    max-height: calc(100vh - 24px);
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    background: transparent;
}

.curriculum-detail__side-card-inner {
    border-radius: 20px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.85) 100%);
    backdrop-filter: blur(12px);
    min-height: 200px;
}

.curriculum-detail__side-card-header {
    display: flex;
    align-items: center;
    padding: 14px 20px 10px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.12);
    font-size: 1.15rem;
    font-weight: 700;
    color: #e2e8f0;
}

.curriculum-detail__side-card-body {
    padding: 14px 20px;
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-inner {
    border: 1px solid rgba(15, 23, 42, 0.12);
    background:
        radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 52%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.93));
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.11),
        inset 0 1px 0 rgba(255, 255, 255, 0.7);
    color: #0f172a;
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-header {
    padding: 10px 14px 8px;
    color: #0f172a;
    font-size: 1.05rem;
    border-bottom-color: rgba(30, 41, 59, 0.1);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(219, 234, 254, 0.36));
}

.curriculum-detail__side-card--content {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.curriculum-detail__side-card--documents {
    position: sticky;
    top: 12px;
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.curriculum-detail__side-card--documents .curriculum-detail__side-card-inner {
    border: 1px solid rgba(15, 23, 42, 0.12);
    background:
        radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 52%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.93));
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.11),
        inset 0 1px 0 rgba(255, 255, 255, 0.7);
    color: #0f172a;
}

.curriculum-detail__side-card--documents .curriculum-detail__side-card-header {
    color: #0f172a;
    border-bottom-color: rgba(30, 41, 59, 0.1);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(219, 234, 254, 0.36));
}

.curriculum-detail__side-card--documents .curriculum-detail__side-card-body {
    color: #1e293b;
}

.curriculum-detail__side-card--content .curriculum-detail__side-card-body {
    padding: 10px 14px;
    color: #1e293b;
}

.curriculum-detail__content-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.curriculum-detail__content-count {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
}

.curriculum-detail__content-hint {
    font-size: 0.8rem;
    color: #475569;
    line-height: 1.35;
    margin-top: 2px;
}

.curriculum-detail__preview {
    margin-top: 8px;
    padding: 7px 8px;
    border: 1px solid rgba(37, 99, 235, 0.22);
    border-radius: 10px;
    background: rgba(219, 234, 254, 0.45);
}

.curriculum-detail__preview-summary {
    margin-bottom: 5px;
    color: #0f172a;
    font-size: 0.75rem;
    font-weight: 500;
}

.curriculum-detail__content-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
}

.curriculum-detail__topic-form {
    border-radius: 14px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.96));
    padding: 14px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

.curriculum-detail__topic-form-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__unit-dialog-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.curriculum-detail__unit-dialog-secondary-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__topic-form-error {
    font-size: 0.8rem;
    color: #fecaca;
}

.curriculum-detail__material-dialog {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.curriculum-detail__material-dialog-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.curriculum-detail__material-dialog-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}

.curriculum-detail__material-dialog-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.45;
}

.curriculum-detail__material-dialog-empty {
    color: #64748b;
}

.curriculum-detail__material-dialog-error {
    color: #b91c1c;
}

.curriculum-detail__material-mode-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__material-results {
    max-height: 380px;
    overflow-y: auto;
}

.curriculum-detail__material-selection-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(300px, 0.85fr);
    gap: 14px;
}

.curriculum-detail__material-result-item {
    border: 1px solid rgba(99, 102, 241, 0.14);
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 60%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(238, 242, 255, 0.92));
    cursor: pointer;
}

.curriculum-detail__material-result-item--active {
    border-color: rgba(79, 70, 229, 0.38);
    box-shadow: 0 0 0 1px rgba(129, 140, 248, 0.18);
}

.curriculum-detail__material-browser {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.curriculum-detail__material-browser-panel,
.curriculum-detail__material-browser-results {
    padding: 12px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.94));
}

.curriculum-detail__material-filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.curriculum-detail__material-browser-summary {
    display: flex;
    align-items: stretch;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.curriculum-detail__material-browser-summary-card {
    min-width: 180px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(255, 255, 255, 0.76);
}

.curriculum-detail__material-browser-summary-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 4px;
}

.curriculum-detail__material-browser-summary-value {
    font-size: 0.88rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.4;
}

.curriculum-detail__material-browser-reset {
    margin-left: auto;
    align-self: center;
}

.curriculum-detail__material-preview-panel {
    padding: 12px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.94));
    display: flex;
    flex-direction: column;
    min-height: 420px;
}

.curriculum-detail__material-preview-copy {
    margin-bottom: 10px;
}

.curriculum-detail__material-preview-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
}

.curriculum-detail__material-preview-subtitle {
    margin-top: 4px;
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.45;
}

.curriculum-detail__material-preview-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.curriculum-detail__material-preview-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(255, 255, 255, 0.82);
    cursor: pointer;
}

.curriculum-detail__material-preview-list-item:hover {
    border-color: rgba(99, 102, 241, 0.28);
    background: rgba(238, 242, 255, 0.92);
}

.curriculum-detail__material-preview-list-copy {
    min-width: 0;
    flex: 1;
}

.curriculum-detail__material-preview-list-title {
    font-size: 0.84rem;
    font-weight: 600;
    color: #1e293b;
}

.curriculum-detail__material-preview-list-subtitle {
    margin-top: 2px;
    font-size: 0.74rem;
    color: #64748b;
}

.curriculum-detail__material-preview-list-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.curriculum-detail__material-preview-empty {
    flex: 1;
    min-height: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #64748b;
    border-radius: 12px;
    border: 1px dashed rgba(148, 163, 184, 0.26);
    background: rgba(248, 250, 252, 0.86);
    padding: 16px;
}

.curriculum-detail__fullscreen-preview {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: rgba(248, 250, 252, 0.98) !important;
}

.curriculum-detail__modal-preview {
    display: flex;
    flex-direction: column;
    min-height: min(78vh, 820px);
    background: rgba(248, 250, 252, 0.98) !important;
}

.curriculum-detail__fullscreen-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
}

.curriculum-detail__fullscreen-preview-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.curriculum-detail__fullscreen-preview-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.curriculum-detail__fullscreen-preview-subtitle {
    margin-top: 4px;
    font-size: 0.82rem;
    color: #64748b;
}

.curriculum-detail__fullscreen-preview-close-btn {
    font-weight: 700;
    letter-spacing: 0.01em;
    min-width: 130px;
    box-shadow: 0 10px 24px rgba(185, 28, 28, 0.18);
}

.curriculum-detail__fullscreen-preview-body {
    flex: 1;
    padding: 0 !important;
    background: rgba(226, 232, 240, 0.55);
}

.curriculum-detail__fullscreen-preview-iframe,
.curriculum-detail__fullscreen-preview-image {
    width: 100%;
    height: calc(100vh - 82px);
    border: none;
    display: block;
    background: #fff;
}

.curriculum-detail__fullscreen-preview-image {
    object-fit: contain;
}

.curriculum-detail__modal-preview-iframe,
.curriculum-detail__modal-preview-image {
    width: 100%;
    height: min(68vh, 720px);
    border: none;
    display: block;
    background: #fff;
}

.curriculum-detail__modal-preview-image {
    object-fit: contain;
}

.curriculum-detail__fullscreen-preview-empty {
    height: calc(100vh - 82px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #475569;
    padding: 24px;
}

.curriculum-detail__material-browser-heading {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 10px;
}

.curriculum-detail__editor-dialog-card {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%) !important;
    color: #0f172a;
    border: 1px solid rgba(148, 163, 184, 0.22);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
}

.curriculum-detail__editor-dialog-form {
    border-color: rgba(148, 163, 184, 0.18);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.96));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

.curriculum-detail__editor-dialog-card :deep(.v-card-title) {
    color: #0f172a;
}

.curriculum-detail__editor-dialog-card :deep(.v-field) {
    background: rgba(255, 255, 255, 0.96);
    border-radius: 12px;
}

.curriculum-detail__editor-dialog-card :deep(.v-field__overlay) {
    background: transparent;
}

.curriculum-detail__editor-dialog-card :deep(.v-field__outline) {
    --v-field-border-opacity: 1;
    color: rgba(148, 163, 184, 0.4);
}

.curriculum-detail__editor-dialog-card :deep(.v-label),
.curriculum-detail__editor-dialog-card :deep(.v-field-label) {
    color: #64748b !important;
    opacity: 1;
}

.curriculum-detail__editor-dialog-card :deep(input),
.curriculum-detail__editor-dialog-card :deep(textarea),
.curriculum-detail__editor-dialog-card :deep(.v-field__input) {
    color: #0f172a !important;
}

.curriculum-detail__editor-dialog-save-btn {
    background: #334155 !important;
    color: #f8fafc !important;
    box-shadow: 0 8px 18px rgba(51, 65, 85, 0.18);
}

.curriculum-detail__editor-dialog-cancel-btn {
    color: #64748b !important;
}

.curriculum-detail__topic-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.curriculum-detail__topic-entry {
    display: block;
}

.curriculum-detail__topic-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.75);
    overflow: hidden;
}

.curriculum-detail__topic-item > .curriculum-detail__topic-row {
    padding: 4px 6px;
    cursor: default;
    background: transparent;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    transition: background 0.15s, box-shadow 0.15s;
}

.curriculum-detail__topic-item > .curriculum-detail__topic-row--collapsible {
    cursor: pointer;
}

.curriculum-detail__topic-item--selected {
    border-color: rgba(37, 99, 235, 0.44);
    box-shadow:
        0 0 0 2px rgba(59, 130, 246, 0.14),
        0 6px 14px rgba(15, 23, 42, 0.06);
}

.curriculum-detail__topic-item--selected > .curriculum-detail__topic-row {
    background: rgba(219, 234, 254, 0.48);
}

.curriculum-detail__topic-item > .curriculum-detail__topic-row .curriculum-detail__topic-title {
    color: #0f172a;
}

.curriculum-detail__topic-item > .curriculum-detail__unit-section {
    padding: 4px 6px 6px;
    border-top: none;
}

.curriculum-detail__topic-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.curriculum-detail__topic-main {
    min-width: 0;
    flex: 1;
}

.curriculum-detail__topic-title-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__topic-title {
    font-size: 0.875rem;
    font-weight: 500;
    color: #0f172a;
    line-height: 1.35;
}

.curriculum-detail__topic-unit-count {
    padding: 1px 6px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.82);
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    white-space: nowrap;
}

.curriculum-detail__topic-summary-chip {
    font-weight: 600;
}

.curriculum-detail__topic-meta-chip--interactive {
    cursor: pointer;
}

.curriculum-detail__topic-meta-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.curriculum-detail__topic-meta-text {
    margin-top: 8px;
    font-size: 0.8rem;
    color: #475569;
}

.curriculum-detail__attached-materials {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 10px;
}

.curriculum-detail__attached-materials--unit {
    margin-top: 8px;
}

.curriculum-detail__attached-material {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid rgba(99, 102, 241, 0.16);
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 60%),
        linear-gradient(180deg, rgba(238, 242, 255, 0.94), rgba(224, 231, 255, 0.82));
}

.curriculum-detail__attached-material-copy {
    min-width: 0;
    flex: 1;
}

.curriculum-detail__attached-material-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.curriculum-detail__attached-material-preview-btn {
    white-space: nowrap;
}

.curriculum-detail__attached-material-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #312e81;
}

.curriculum-detail__attached-material-subtitle {
    font-size: 0.72rem;
    color: #475569;
    margin-top: 2px;
}

.curriculum-detail__topic-actions {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 0;
    padding: 0;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.7);
}

.curriculum-detail__topic-actions :deep(.v-btn) {
    width: 28px;
    min-width: 28px;
    height: 28px;
}

.curriculum-detail__topic-header-actions {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.curriculum-detail__unit-section {
    border-top: 0;
    padding-top: 0;
}

.curriculum-detail__unit-form {
    margin-left: 14px;
}

.curriculum-detail__unit-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-left: 0;
    margin-top: 4px;
}

.curriculum-detail__unit-item {
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    box-shadow: none;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}

.curriculum-detail__unit-item > .curriculum-detail__topic-row {
    padding: 2px 4px;
    transition: background 0.15s, box-shadow 0.15s;
    border-radius: 8px;
}

.curriculum-detail__unit-item--selected {
    background: rgba(219, 234, 254, 0.72);
    box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.16);
}

.curriculum-detail__unit-item--exam {
    background: transparent;
    box-shadow: none;
}

.curriculum-detail__unit-item--exam > .curriculum-detail__topic-row {
    align-items: center;
    border-radius: 0;
    background: linear-gradient(90deg, rgba(254, 226, 226, 0.82), rgba(255, 255, 255, 0.42));
    box-shadow:
        inset 2px 0 0 rgba(220, 38, 38, 0.72),
        inset 0 0 0 1px rgba(220, 38, 38, 0.08);
}

.curriculum-detail__unit-item--exam:hover > .curriculum-detail__topic-row {
    background: linear-gradient(90deg, rgba(254, 202, 202, 0.68), rgba(255, 255, 255, 0.58));
}

.curriculum-detail__unit-item--exam.curriculum-detail__unit-item--selected {
    background: transparent;
    box-shadow: none;
}

.curriculum-detail__unit-item--exam.curriculum-detail__unit-item--selected > .curriculum-detail__topic-row {
    background: linear-gradient(90deg, rgba(254, 202, 202, 0.62), rgba(219, 234, 254, 0.74));
    box-shadow:
        inset 2px 0 0 rgba(220, 38, 38, 0.82),
        0 0 0 1px rgba(37, 99, 235, 0.16);
}

.curriculum-detail__unit-title {
    font-size: 0.75rem;
    font-weight: 500;
    color: #0f172a;
    line-height: 1.35;
}

.curriculum-detail__unit-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.curriculum-detail__unit-exam-chip {
    height: 20px;
    border: 0 !important;
    border-radius: 0 !important;
    background: #dc2626 !important;
    color: #ffffff !important;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.015em;
    box-shadow: none !important;
}

.curriculum-detail__unit-exam-chip :deep(.v-icon) {
    font-size: 13px;
}

.curriculum-detail__unit-exam-checkbox :deep(.v-selection-control) {
    min-height: 34px;
}

.curriculum-detail__unit-exam-checkbox :deep(.v-label) {
    color: #334155;
    font-weight: 600;
}

.curriculum-detail__unit-empty {
    margin-left: 14px;
    font-size: 0.8rem;
    color: #94a3b8;
}

.curriculum-detail__topic-assignment-panel {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid rgba(129, 140, 248, 0.34);
    background:
        linear-gradient(180deg, rgba(49, 46, 129, 0.18), rgba(15, 23, 42, 0.48)),
        rgba(15, 23, 42, 0.72);
    box-shadow:
        inset 0 0 0 1px rgba(165, 180, 252, 0.06),
        0 10px 24px rgba(15, 23, 42, 0.28);
}

.curriculum-detail__topic-assignment-options {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(129, 140, 248, 0.16);
}

.curriculum-detail__topic-assignment-options :deep(.v-btn) {
    color: #cbd5e1 !important;
}

.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-tonal) {
    background: rgba(148, 163, 184, 0.14) !important;
    border: 1px solid rgba(148, 163, 184, 0.16) !important;
}

.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-flat) {
    color: #eff6ff !important;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.92), rgba(99, 102, 241, 0.82)) !important;
    border: 1px solid rgba(199, 210, 254, 0.42) !important;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.18),
        0 0 0 1px rgba(99, 102, 241, 0.26),
        0 8px 18px rgba(79, 70, 229, 0.28);
}

.curriculum-detail__topic-assignment-options :deep(.v-btn--variant-text) {
    color: #e2e8f0 !important;
}

.curriculum-detail__topic-assignment-months,
.curriculum-detail__topic-assignment-weeks {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
}

.curriculum-detail__topic-assignment-weeks {
    flex-direction: column;
    align-items: flex-start;
}

.curriculum-detail__assignment-month-row {
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    flex-wrap: wrap;
}

.curriculum-detail__assignment-month-row--selected {
    flex: 1 0 100%;
}

.curriculum-detail__assignment-month-week-options {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.curriculum-detail__assignment-month-week-chip {
    cursor: pointer;
}

.curriculum-detail__assignment-week-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.curriculum-detail__assignment-week-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    flex-wrap: wrap;
}

.curriculum-detail__assignment-week-controls {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.curriculum-detail__assignment-week-controls :deep(.v-btn) {
    color: #cbd5e1 !important;
}

.curriculum-detail__topic-assignment-hint {
    font-size: 0.8rem;
    color: #dbeafe;
    font-weight: 600;
    letter-spacing: 0.01em;
    padding: 6px 10px;
    border-radius: 10px;
    background: rgba(59, 130, 246, 0.12);
    border: 1px solid rgba(96, 165, 250, 0.18);
}

.curriculum-detail__assignment-chip {
    cursor: pointer;
}

.curriculum-detail__topic-empty {
    font-size: 0.84rem;
    color: #475569;
}

.curriculum-detail__content-add-btn,
.curriculum-detail__topic-save-btn {
    color: #eff6ff !important;
    font-weight: 700;
    box-shadow: 0 10px 24px rgba(79, 70, 229, 0.26);
}

.curriculum-detail__side-card--content :deep(.v-field) {
    background: rgba(248, 250, 252, 0.97);
    border-radius: 12px;
}

.curriculum-detail__side-card--content :deep(.v-field__overlay) {
    background: transparent;
}

.curriculum-detail__side-card--content :deep(.v-field__outline) {
    --v-field-border-opacity: 1;
    color: rgba(148, 163, 184, 0.38);
}

.curriculum-detail__side-card--content :deep(.v-label),
.curriculum-detail__side-card--content :deep(.v-field-label) {
    color: #475569 !important;
    opacity: 1;
}

.curriculum-detail__side-card--content :deep(input),
.curriculum-detail__side-card--content :deep(textarea),
.curriculum-detail__side-card--content :deep(.v-field__input),
.curriculum-detail__side-card--content :deep(.v-select__selection-text),
.curriculum-detail__side-card--content :deep(.v-autocomplete__selection) {
    color: #0f172a !important;
}

.curriculum-detail__side-card--content :deep(.v-field__append-inner .v-icon),
.curriculum-detail__side-card--content :deep(.v-field__clearable .v-icon),
.curriculum-detail__side-card--content :deep(.v-select__menu-icon) {
    color: #475569 !important;
}

.curriculum-detail__side-card--content :deep(.v-chip.v-chip--size-small),
.curriculum-detail__side-card--content :deep(.v-chip.v-chip--size-x-small) {
    color: #e2e8f0;
}

/* ---------- Responsive ---------- */
/* ---------- Lehrpläne list ---------- */
.lehrplaene__list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lehrplaene__item {
    display: flex;
    align-items: center;
    padding: 6px 8px;
    border-radius: 8px;
    background:
        radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent 60%),
        linear-gradient(180deg, rgba(238, 242, 255, 0.95), rgba(224, 231, 255, 0.85));
    border: 1px solid rgba(99, 102, 241, 0.18);
}

.lehrplaene__item-copy {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.lehrplaene__item-name {
    font-size: 0.82rem;
    color: #0f172a;
    font-weight: 500;
}

.lehrplaene__item-subtitle {
    font-size: 0.72rem;
    color: #475569;
}

.lehrplaene__item--clickable {
    cursor: pointer;
}

.lehrplaene__item--clickable:hover {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.2);
}

.lehrplaene__item--active {
    background: rgba(99, 102, 241, 0.15) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
}

/* ---------- Preview ---------- */
.lehrplaene__preview {
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: rgba(255, 255, 255, 0.84);
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
}

.lehrplaene__preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.1);
    font-size: 0.78rem;
    font-weight: 600;
    color: #4338ca;
}

.lehrplaene__preview-body {
    min-height: 200px;
}

.lehrplaene__preview-iframe {
    width: 100%;
    aspect-ratio: 1 / 1.4142;
    border: none;
    background: #fff;
}

.lehrplaene__preview-image {
    width: 100%;
    aspect-ratio: 1 / 1.4142;
    object-fit: contain;
    display: block;
    background: rgba(15, 23, 42, 0.8);
}

.lehrplaene__material-item {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.15);
    cursor: pointer;
}

.lehrplaene__material-item:hover {
    background: rgba(99, 102, 241, 0.12);
}

.lehrplaene__attachment-item.v-list-item--active {
    background: rgba(99, 102, 241, 0.18);
    border-color: rgba(129, 140, 248, 0.34);
}

@media (max-width: 900px) {
    .curriculum-detail__title-row {
        flex-direction: column;
    }

    .curriculum-detail__meta {
        justify-content: flex-start;
    }

    .curriculum-detail__body {
        grid-template-columns: 1fr;
        width: auto;
    }

    .curriculum-detail__calendar,
    .curriculum-detail__calendar--compact {
        width: 100%;
    }

    .curriculum-detail__side-card--documents {
        min-width: 0;
    }

    .curriculum-detail__side-card {
        position: static;
    }

    .curriculum-detail__side-card--content {
        width: 100%;
    }
}

@media (max-width: 700px) {
    .curriculum-detail__header {
        padding: 14px;
    }

    .curriculum-detail__header-actions,
    .curriculum-detail__title-row {
        align-items: stretch;
    }

    .curriculum-detail__summary {
        align-self: flex-start;
    }

    .curriculum-detail__day {
        width: 30px;
        height: 34px;
    }

    .curriculum-detail__week-range {
        display: none;
    }

    .curriculum-detail__month-header {
        padding: 10px 14px 6px;
    }

    .curriculum-detail__week-actions {
        margin-left: auto;
    }

    .curriculum-detail__content-toolbar,
    .curriculum-detail__topic-assignment-options,
    .curriculum-detail__material-dialog-toolbar {
        flex-direction: column;
    }

    .curriculum-detail__material-filter-grid {
        grid-template-columns: 1fr;
    }

    .curriculum-detail__material-selection-layout {
        grid-template-columns: 1fr;
    }

    .curriculum-detail__material-browser-reset {
        margin-left: 0;
        align-self: flex-start;
    }
}
</style>
