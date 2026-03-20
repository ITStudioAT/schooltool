<template>
    <v-container fluid class="pandoc-debug-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern · ABA"
            title="DOCX-Prüfansicht"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#0f766e"
            right-orb-color="#5eead4" />

        <v-sheet rounded="xl" class="pd-nav mb-2">
            <div class="pd-nav__buttons">
                <v-btn rounded="xl" color="secondary" variant="tonal" class="pd-nav__button" @click="$router.push('/admin/aba')">
                    <v-icon size="18" icon="mdi-view-dashboard-outline" class="mr-2" />
                    <span class="pd-nav__button-copy">
                        <span class="pd-nav__button-title">Überblick</span>
                        <span class="pd-nav__button-meta">Meine ABAs</span>
                    </span>
                </v-btn>
                <v-btn rounded="xl" color="secondary" variant="tonal" class="pd-nav__button" @click="$router.push('/admin/aba/ai-settings')">
                    <v-icon size="18" icon="mdi-brain" class="mr-2" />
                    <span class="pd-nav__button-copy">
                        <span class="pd-nav__button-title">KI-Einstellungen</span>
                        <span class="pd-nav__button-meta">Dashboard</span>
                    </span>
                </v-btn>
                <v-btn rounded="xl" color="teal" variant="flat" class="pd-nav__button">
                    <v-icon size="18" icon="mdi-file-search-outline" class="mr-2" />
                    <span class="pd-nav__button-copy">
                        <span class="pd-nav__button-title">DOCX prüfen</span>
                        <span class="pd-nav__button-meta">Pandoc-Pfad</span>
                    </span>
                </v-btn>
            </div>
        </v-sheet>

        <v-alert type="info" variant="tonal" density="comfortable" rounded="lg" class="mb-2 text-caption">
            Diese Ansicht ist eine interne Prüfansicht für Extraktion und Normalisierung.
            Sie ersetzt keine finale ABA-Bewertung.
        </v-alert>

        <ItsGridBox class="mb-2">
            <template #title>
                <v-icon size="16" class="mr-1">mdi-file-upload-outline</v-icon>
                Dokument prüfen
            </template>

            <div class="pa-3">
                <v-row dense>
                    <v-col cols="12" md="8">
                        <v-file-input
                            v-model="selectedFile"
                            accept=".docx"
                            label="DOCX-Datei auswählen"
                            prepend-icon="mdi-file-word-box"
                            variant="outlined"
                            density="comfortable"
                            show-size
                            :disabled="isRunning"
                            clearable />
                    </v-col>
                    <v-col cols="12" md="4" class="d-flex align-end">
                        <v-btn
                            block
                            color="teal"
                            variant="flat"
                            prepend-icon="mdi-play-circle-outline"
                            :loading="isRunning"
                            :disabled="!selectedUploadFile || isRunning"
                            @click="runDebug">
                            Dokument prüfen
                        </v-btn>
                    </v-col>
                </v-row>

                <v-alert v-if="runError" type="error" variant="tonal" density="compact" class="mt-2 text-caption">
                    {{ runError }}
                </v-alert>
            </div>
        </ItsGridBox>

        <template v-if="result">
            <v-sheet rounded="lg" class="mb-2 pa-2 d-flex justify-end align-center ga-2">
                <v-btn
                    color="teal"
                    variant="flat"
                    :prepend-icon="copyButtonIcon"
                    :loading="isCopying"
                    @click="copyReviewReport">
                    {{ copyButtonLabel }}
                </v-btn>
            </v-sheet>

            <v-row class="w-100 ma-0 mb-2" dense>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Normalisierte Blöcke</div>
                        <div class="metric-value">{{ summary.normalized_block_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Erkannte Überschriften</div>
                        <div class="metric-value">{{ summary.heading_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Erkannte Bilder</div>
                        <div class="metric-value">{{ summary.image_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Blöcke mit Abschnittshinweis</div>
                        <div class="metric-value">{{ summary.section_hint_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Unsichere Erkennung</div>
                        <div class="metric-value">{{ summary.uncertain_or_heuristic_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Dokumenttitel-Kandidaten</div>
                        <div class="metric-value">{{ summary.document_title_candidate_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Leere Überschriften</div>
                        <div class="metric-value">{{ summary.empty_heading_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">TOC-Artefakte</div>
                        <div class="metric-value">{{ summary.probable_toc_artifact_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Auffällige Titeltexte</div>
                        <div class="metric-value">{{ summary.suspicious_heading_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Erkannte Dokumentzonen</div>
                        <div class="metric-value">{{ summary.zone_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Hauptteil-Blöcke</div>
                        <div class="metric-value">{{ summary.zone_main_content_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">TOC-Blöcke</div>
                        <div class="metric-value">{{ summary.zone_table_of_contents_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Verzeichnis-/Bibliographie-Blöcke</div>
                        <div class="metric-value">{{ summary.zone_bibliography_area_count }}</div>
                    </v-sheet>
                </v-col>
                <v-col cols="12" md="6" lg="4">
                    <v-sheet rounded="lg" class="metric-card pa-3">
                        <div class="metric-label">Erklärungsbereich-Blöcke</div>
                        <div class="metric-value">{{ summary.zone_declaration_area_count }}</div>
                    </v-sheet>
                </v-col>
            </v-row>

            <ItsGridBox class="mb-2">
                <template #title>
                    <v-icon size="16" class="mr-1">mdi-compare-horizontal</v-icon>
                    Pfadvergleich: Lokal vs. Pandoc
                </template>
                <div class="pa-3">
                    <div class="d-flex justify-end mb-2">
                        <v-btn
                            color="blue"
                            variant="tonal"
                            size="small"
                            :prepend-icon="comparisonCopyButtonIcon"
                            :loading="isComparisonCopying"
                            @click="copyComparisonReport">
                            {{ comparisonCopyButtonLabel }}
                        </v-btn>
                    </div>

                    <v-alert type="info" variant="tonal" density="compact" class="text-caption mb-2">
                        Vergleich der Dokumentaufbereitung, keine Benotung der Schülerarbeit.
                    </v-alert>

                    <v-row dense class="mb-1">
                        <v-col cols="12" md="6">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="review-item__chips mb-1">
                                    <v-chip size="x-small" color="blue" variant="tonal">{{ legacyComparePath.label || 'Lokaler Pfad' }}</v-chip>
                                    <v-chip size="x-small" :color="comparePathStatusColor(legacyComparePath)" variant="tonal">{{ comparePathStatusLabel(legacyComparePath) }}</v-chip>
                                </div>
                                <div class="text-caption">
                                    Pflichtteile erkannt: {{ Number(legacyComparePath.required_parts_found || 0) }} |
                                    Fehlende Pflichtteile: {{ Array.isArray(legacyComparePath.missing_required_parts) ? legacyComparePath.missing_required_parts.length : 0 }} |
                                    TOC-Artefakte: {{ Number(legacyComparePath.toc_artifacts || 0) }}
                                </div>
                            </v-sheet>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="review-item__chips mb-1">
                                    <v-chip size="x-small" color="teal" variant="tonal">{{ pandocComparePath.label || 'Pandoc-Pfad' }}</v-chip>
                                    <v-chip size="x-small" :color="comparePathStatusColor(pandocComparePath)" variant="tonal">{{ comparePathStatusLabel(pandocComparePath) }}</v-chip>
                                </div>
                                <div class="text-caption">
                                    Pflichtteile erkannt: {{ Number(pandocComparePath.required_parts_found || 0) }} |
                                    Fehlende Pflichtteile: {{ Array.isArray(pandocComparePath.missing_required_parts) ? pandocComparePath.missing_required_parts.length : 0 }} |
                                    TOC-Artefakte: {{ Number(pandocComparePath.toc_artifacts || 0) }}
                                </div>
                            </v-sheet>
                        </v-col>
                    </v-row>

                    <v-alert v-if="comparisonZones.length === 0" type="info" variant="tonal" density="compact" class="text-caption">
                        Keine Vergleichsdaten verfügbar.
                    </v-alert>
                    <div v-else class="review-list">
                        <v-sheet v-for="zone in comparisonZones" :key="`compare-${zone.zone_key}`" class="review-item pa-2" rounded="lg">
                            <div class="review-item__chips">
                                <v-chip size="x-small" color="indigo" variant="tonal">{{ zone.label || zone.zone_key }}</v-chip>
                                <v-chip size="x-small" color="grey" variant="outlined">{{ requirementLabel(zone.requirement) }}</v-chip>
                                <v-chip size="x-small" :color="compareBoolColor(zone.legacy_local)" variant="tonal">Lokal: {{ compareBoolLabel(zone.legacy_local) }}</v-chip>
                                <v-chip size="x-small" :color="compareBoolColor(zone.pandoc)" variant="tonal">Pandoc: {{ compareBoolLabel(zone.pandoc) }}</v-chip>
                            </div>
                        </v-sheet>
                    </div>
                </div>
            </ItsGridBox>

            <ItsGridBox class="mb-2">
                <template #title>
                    <v-icon size="16" class="mr-1">mdi-map-outline</v-icon>
                    Dokumentphasen / Zonen
                </template>
                <div class="pa-3">
                    <v-alert v-if="zoneOverview.length === 0" type="info" variant="tonal" density="compact" class="text-caption">
                        Keine Zonen erkannt.
                    </v-alert>
                    <div v-else class="review-list">
                        <v-sheet v-for="zone in zoneOverview" :key="zone.zone_key" class="review-item pa-2" rounded="lg">
                            <div class="review-item__chips">
                                <v-chip size="x-small" color="indigo" variant="tonal">{{ zone.zone_label || zone.zone_key }}</v-chip>
                                <v-chip size="x-small" color="blue" variant="tonal">{{ zone.count || 0 }} Blöcke</v-chip>
                                <v-chip size="x-small" color="teal" variant="tonal">{{ zone.heading_count || 0 }} Überschriften</v-chip>
                                <v-chip size="x-small" color="grey" variant="outlined">#{{ zone.first_order || '?' }} - #{{ zone.last_order || '?' }}</v-chip>
                            </div>
                        </v-sheet>
                    </div>
                </div>
            </ItsGridBox>

            <v-row class="w-100 ma-0 mb-2" dense>
                <v-col cols="12" lg="6">
                    <ItsGridBox class="h-100">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-file-tree-outline</v-icon>
                            Erkannte Hauptabschnitte
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="recognizedMainSections.length === 0" type="info" variant="tonal" density="compact" class="text-caption">
                                Keine klaren Hauptabschnitte erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in recognizedMainSections" :key="`main-${item.order}-${item.text}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="teal" variant="tonal">{{ item.section_type_label || item.section_type || 'Abschnitt' }}</v-chip>
                                        <v-chip size="x-small" :color="confidenceColorByValue(item.confidence)" variant="tonal">{{ (item.confidence || 'low').toUpperCase() }}</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>

                <v-col cols="12" lg="6">
                    <ItsGridBox class="h-100">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-alert-outline</v-icon>
                            Unsichere Überschriften
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="uncertainHeadings.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine unsicheren Überschriften erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in uncertainHeadings" :key="`uncertain-${item.order}-${item.text}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" :color="confidenceColorByValue(item.confidence)" variant="tonal">{{ (item.confidence || 'low').toUpperCase() }}</v-chip>
                                        <v-chip size="x-small" color="deep-purple" variant="tonal">{{ item.strategy || 'heuristic' }}</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                    <div class="text-caption text-medium-emphasis mt-1">Grund: {{ item.reason || 'kein_signal' }}</div>
                                </v-sheet>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>

            <v-row class="w-100 ma-0 mb-2" dense>
                <v-col cols="12" lg="6">
                    <ItsGridBox class="h-100">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-format-title</v-icon>
                            Dokumenttitel-Kandidaten
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="documentTitleCandidates.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine auffälligen Dokumenttitel-Kandidaten erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in documentTitleCandidates" :key="`title-${item.order}-${item.text}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" color="amber" variant="tonal">Titelblatt?</v-chip>
                                        <v-chip size="x-small" :color="confidenceColorByValue(item.confidence)" variant="tonal">{{ (item.confidence || 'low').toUpperCase() }}</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
                <v-col cols="12" lg="6">
                    <ItsGridBox class="h-100">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-bookshelf</v-icon>
                            Quellen-/Verzeichnisbereich
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="bibliographyGroups.length === 0" type="info" variant="tonal" density="compact" class="text-caption">
                                Keine gruppierten Quellen-/Verzeichnisbereiche erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="group in bibliographyGroups" :key="group.group_key" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__text mb-1"><strong>{{ group.group_label || group.group_key }}</strong></div>
                                    <div class="review-item__chips">
                                        <v-chip
                                            v-for="subtype in (Array.isArray(group.subtypes) ? group.subtypes : [])"
                                            :key="`${group.group_key}-${subtype.subtype_key}`"
                                            size="x-small"
                                            color="teal"
                                            variant="tonal">
                                            {{ subtype.subtype_label || subtype.subtype_key }} ({{ subtype.count || 0 }})
                                        </v-chip>
                                    </div>
                                </v-sheet>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>

            <v-row class="w-100 ma-0 mb-2" dense>
                <v-col cols="12" lg="4">
                    <ItsGridBox class="h-100">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-text-box-remove-outline</v-icon>
                            Leere Überschriften
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="emptyHeadings.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine leeren Überschriften erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in emptyHeadings" :key="`empty-${item.order}-${item.id}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" color="red" variant="tonal">leer</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
                <v-col cols="12" lg="4">
                    <ItsGridBox class="h-100">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-format-list-numbered</v-icon>
                            Wahrscheinliche Inhaltsverzeichnis-Einträge
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="probableTocArtifacts.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine klaren TOC-Artefakte erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in probableTocArtifacts" :key="`toc-${item.order}-${item.text}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" color="orange" variant="tonal">TOC</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
                <v-col cols="12" lg="4">
                    <ItsGridBox class="h-100">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-alert-decagram-outline</v-icon>
                            Auffällige Überschriftentexte
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="suspiciousHeadings.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine auffälligen Überschriftentexte erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in suspiciousHeadings" :key="`suspicious-${item.order}-${item.text}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" color="red" variant="tonal">auffällig</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>

            <ItsGridBox class="mb-2">
                <template #title>
                    <v-icon size="16" class="mr-1">mdi-filter-outline</v-icon>
                    Filter
                </template>
                <div class="pa-3 d-flex flex-wrap ga-2">
                    <v-btn
                        v-for="filter in filters"
                        :key="filter.key"
                        size="small"
                        :variant="activeFilter === filter.key ? 'flat' : 'tonal'"
                        :color="activeFilter === filter.key ? 'primary' : 'secondary'"
                        @click="activeFilter = filter.key">
                        {{ filter.label }}
                    </v-btn>
                </div>
            </ItsGridBox>

            <ItsGridBox>
                <template #title>
                    <v-icon size="16" class="mr-1">mdi-format-list-bulleted</v-icon>
                    Blockliste
                </template>
                <template #header-actions>
                    <v-chip size="x-small" color="primary" variant="tonal">
                        {{ displayedBlocks.length }} / {{ filteredBlocks.length }} sichtbar
                    </v-chip>
                    <v-btn
                        v-if="hiddenBlockCount > 0"
                        size="x-small"
                        variant="text"
                        prepend-icon="mdi-plus"
                        @click="showAllBlocks = true">
                        Alle anzeigen
                    </v-btn>
                </template>

                <div class="pa-3">
                    <v-alert v-if="filteredBlocks.length === 0" type="info" variant="tonal" density="compact" class="text-caption">
                        Für den gewählten Filter wurden keine Blöcke gefunden.
                    </v-alert>

                    <div v-else class="block-list">
                        <v-sheet
                            v-for="block in displayedBlocks"
                            :key="block.id || block.order"
                            rounded="lg"
                            class="block-item pa-3">
                            <div class="block-item__chips">
                                <v-chip size="x-small" color="blue" variant="tonal">{{ block.type || 'unbekannt' }}</v-chip>
                                <v-chip size="x-small" color="grey" variant="outlined">#{{ block.order }}</v-chip>
                                <v-chip size="x-small" :color="confidenceColor(block)" variant="tonal">
                                    {{ (block.classification?.confidence || 'low').toUpperCase() }}
                                </v-chip>
                                <v-chip size="x-small" color="deep-purple" variant="tonal">
                                    {{ block.classification?.strategy || 'heuristic' }}
                                </v-chip>
                                <v-chip
                                    v-if="block.document_zone?.zone"
                                    size="x-small"
                                    color="indigo"
                                    variant="tonal">
                                    {{ block.document_zone?.label || documentZoneLabel(block.document_zone?.zone) }}
                                </v-chip>
                                <v-chip
                                    v-if="block.section_hint"
                                    size="x-small"
                                    color="teal"
                                    variant="tonal">
                                    Abschnittshinweis
                                </v-chip>
                                <v-chip
                                    v-for="tag in (Array.isArray(block.problem_tags) ? block.problem_tags : [])"
                                    :key="`${block.id || block.order}-${tag}`"
                                    size="x-small"
                                    color="red"
                                    variant="tonal">
                                    {{ problemTagLabel(tag) }}
                                </v-chip>
                                <v-chip
                                    v-if="block.type === 'heading' && block.is_usable_heading === false"
                                    size="x-small"
                                    color="warning"
                                    variant="tonal">
                                    nicht verwendbar
                                </v-chip>
                            </div>

                            <div class="block-item__text">
                                {{ block.type === 'heading' && !((block.plain_text || block.text || '').trim()) ? 'Leere Überschrift' : (snippet(block.plain_text || block.text || '') || 'Kein Textinhalt') }}
                            </div>

                            <div class="block-item__meta text-caption text-medium-emphasis">
                                <div><strong>Hinweis:</strong> {{ sectionHintLabel(block) }}</div>
                                <div><strong>Grund:</strong> {{ reasonLabel(block) }}</div>
                            </div>
                        </v-sheet>
                    </div>
                </div>
            </ItsGridBox>
        </template>

        <v-divider class="my-4" />

        <v-alert type="info" variant="tonal" density="comfortable" rounded="lg" class="mb-2 text-caption">
            <strong>PDF → OpenAI Debug</strong> – Strukturerkennung via gpt-4o. Nur für Vergleichs- und Testzwecke.
        </v-alert>

        <ItsGridBox class="mb-2">
            <template #title>
                <v-icon size="16" class="mr-1">mdi-file-pdf-box</v-icon>
                PDF-Strukturanalyse (OpenAI)
            </template>

            <div class="pa-3">
                <v-row dense>
                    <v-col cols="12" md="8">
                        <v-file-input
                            v-model="selectedPdfFile"
                            accept=".pdf"
                            label="PDF-Datei auswählen"
                            prepend-icon="mdi-file-pdf-box"
                            variant="outlined"
                            density="comfortable"
                            show-size
                            :disabled="isPdfRunning"
                            clearable />
                    </v-col>
                    <v-col cols="12" md="4" class="d-flex align-end">
                        <v-btn
                            block
                            color="deep-purple"
                            variant="flat"
                            prepend-icon="mdi-brain"
                            :loading="isPdfRunning"
                            :disabled="!selectedPdfUploadFile || isPdfRunning"
                            @click="runPdfDebug">
                            PDF analysieren
                        </v-btn>
                    </v-col>
                </v-row>

                <v-alert v-if="pdfRunError" type="error" variant="tonal" density="compact" class="mt-2 text-caption">
                    {{ pdfRunError }}
                </v-alert>
            </div>
        </ItsGridBox>

        <template v-if="pdfResult">
            <v-row class="w-100 ma-0 mb-2" dense>
                <v-col cols="12">
                    <v-sheet rounded="lg" class="pa-3">
                        <div class="d-flex align-center ga-2 mb-2">
                            <span class="text-caption text-medium-emphasis">Gesamtvertrauen:</span>
                            <v-chip :color="confidenceColorForPdf(pdfResult.result?.overall_confidence)" size="small" variant="tonal">
                                {{ pdfResult.result?.overall_confidence }}
                            </v-chip>
                            <span class="text-caption text-medium-emphasis ml-auto">{{ pdfResult.filename }} · {{ pdfResult.model }}</span>
                        </div>

                        <div class="d-flex flex-wrap ga-2">
                            <v-chip v-for="zone in ['titlepage', 'abstract_de', 'abstract_en', 'toc', 'introduction', 'main_part', 'conclusion', 'bibliography', 'declaration', 'appendix', 'figure_index']" :key="zone"
                                :color="pdfResult.result?.[zone + '_detected'] ? 'green' : 'red'"
                                size="small"
                                variant="tonal">
                                {{ zone }}
                            </v-chip>
                        </div>
                    </v-sheet>
                </v-col>
            </v-row>

            <ItsGridBox v-if="pdfZonesFound.length > 0" class="mb-2">
                <template #title>
                    <v-icon size="16" class="mr-1">mdi-layers-outline</v-icon>
                    Erkannte Zonen ({{ pdfZonesFound.length }})
                </template>
                <div class="pa-3">
                    <v-table density="compact">
                        <thead>
                            <tr>
                                <th>Zone</th>
                                <th>Konfidenz</th>
                                <th>Seite</th>
                                <th>Evidenz</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(zone, i) in pdfZonesFound" :key="i">
                                <td class="text-caption font-weight-medium">{{ zone.zone }}</td>
                                <td>
                                    <v-chip :color="confidenceColorForPdf(zone.confidence)" size="x-small" variant="tonal">
                                        {{ zone.confidence }}
                                    </v-chip>
                                </td>
                                <td class="text-caption text-medium-emphasis">{{ zone.page_hint || '–' }}</td>
                                <td class="text-caption">{{ zone.evidence }}</td>
                            </tr>
                        </tbody>
                    </v-table>
                </div>
            </ItsGridBox>

            <ItsGridBox v-if="pdfMissingParts.length > 0 || pdfSuspiciousItems.length > 0 || pdfSequenceObservations.length > 0 || pdfNotes.length > 0" class="mb-2">
                <template #title>
                    <v-icon size="16" class="mr-1">mdi-comment-alert-outline</v-icon>
                    Beobachtungen
                </template>
                <div class="pa-3">
                    <div v-if="pdfMissingParts.length > 0" class="mb-3">
                        <div class="text-caption text-medium-emphasis mb-1">Fehlende Pflichtteile:</div>
                        <v-chip v-for="(part, i) in pdfMissingParts" :key="i" color="red" size="small" variant="tonal" class="mr-1 mb-1">{{ part }}</v-chip>
                    </div>
                    <div v-if="pdfSuspiciousItems.length > 0" class="mb-3">
                        <div class="text-caption text-medium-emphasis mb-1">Auffälligkeiten:</div>
                        <div v-for="(item, i) in pdfSuspiciousItems" :key="i" class="text-caption mb-1">• {{ item }}</div>
                    </div>
                    <div v-if="pdfSequenceObservations.length > 0" class="mb-3">
                        <div class="text-caption text-medium-emphasis mb-1">Reihenfolge-Beobachtungen:</div>
                        <div v-for="(obs, i) in pdfSequenceObservations" :key="i" class="text-caption mb-1">• {{ obs }}</div>
                    </div>
                    <div v-if="pdfNotes.length > 0">
                        <div class="text-caption text-medium-emphasis mb-1">Notizen:</div>
                        <div v-for="(note, i) in pdfNotes" :key="i" class="text-caption mb-1">• {{ note }}</div>
                    </div>
                </div>
            </ItsGridBox>
        </template>

        <v-snackbar
            v-model="copySnackbar.visible"
            location="bottom right"
            :color="copySnackbar.color"
            timeout="2200">
            {{ copySnackbar.message }}
        </v-snackbar>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { AdminSectionHero, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    data() {
        return {
            adminStore: null,
            selectedFile: null,
            isRunning: false,
            runError: null,
            result: null,
            isCopying: false,
            copyWasSuccessful: false,
            isComparisonCopying: false,
            comparisonCopyWasSuccessful: false,
            copySnackbar: {
                visible: false,
                message: '',
                color: 'success',
            },
            activeFilter: 'all',
            showAllBlocks: false,
            selectedPdfFile: null,
            isPdfRunning: false,
            pdfRunError: null,
            pdfResult: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),

        activeSection() {
            return {
                label: 'DOCX-Prüfansicht',
                icon: 'mdi-file-search-outline',
                note: 'Interner Testlauf für Pandoc-Extraktion und Normalisierung.',
            }
        },

        headerChips() {
            return [
                {
                    key: 'school',
                    text: this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule',
                    icon: 'mdi-domain',
                },
                {
                    key: 'role',
                    text: Array.isArray(this.config?.roles) ? this.config.roles.slice(0, 2).join(' / ') : 'Keine Rolle',
                    icon: 'mdi-shield-account',
                },
            ]
        },

        filters() {
            return [
                { key: 'all', label: 'Alle' },
                { key: 'heading', label: 'Nur Überschriften' },
                { key: 'image', label: 'Nur Bilder' },
                { key: 'section_hint', label: 'Nur Abschnittshinweise' },
                { key: 'uncertain', label: 'Nur unsicher/heuristisch' },
                { key: 'document_title', label: 'Nur Titelblatt-Kandidaten' },
                { key: 'toc_artifact', label: 'Nur TOC-Artefakte' },
                { key: 'empty_heading', label: 'Nur leere Überschriften' },
                { key: 'suspicious_heading', label: 'Nur auffällige Titel' },
                { key: 'zone_main_content', label: 'Nur Hauptteil-Zone' },
                { key: 'zone_toc', label: 'Nur TOC-Zone' },
            ]
        },

        summary() {
            return this.result?.summary ?? {
                normalized_block_count: 0,
                heading_count: 0,
                image_count: 0,
                section_hint_count: 0,
                uncertain_or_heuristic_count: 0,
                document_title_candidate_count: 0,
                empty_heading_count: 0,
                probable_toc_artifact_count: 0,
                suspicious_heading_count: 0,
                zone_count: 0,
                zone_title_page_count: 0,
                zone_front_matter_count: 0,
                zone_table_of_contents_count: 0,
                zone_main_content_count: 0,
                zone_bibliography_area_count: 0,
                zone_appendix_area_count: 0,
                zone_declaration_area_count: 0,
                zone_end_matter_count: 0,
            }
        },

        review() {
            return this.result?.review ?? {
                recognized_main_sections: [],
                document_title_candidates: [],
                uncertain_headings: [],
                empty_or_problematic_headings: [],
                probable_toc_artifacts: [],
                suspicious_heading_texts: [],
                bibliography_groups: [],
                zone_overview: [],
                counts: {},
            }
        },

        comparison() {
            return this.result?.comparison ?? {
                note: '',
                paths: {
                    legacy_local: {},
                    pandoc: {},
                    openai_pdf: {},
                },
                matrix: {
                    zones: [],
                },
                summary: {},
            }
        },

        legacyComparePath() {
            return typeof this.comparison?.paths?.legacy_local === 'object' && this.comparison?.paths?.legacy_local !== null
                ? this.comparison.paths.legacy_local
                : {}
        },

        pandocComparePath() {
            return typeof this.comparison?.paths?.pandoc === 'object' && this.comparison?.paths?.pandoc !== null
                ? this.comparison.paths.pandoc
                : {}
        },

        comparisonZones() {
            return Array.isArray(this.comparison?.matrix?.zones) ? this.comparison.matrix.zones : []
        },

        recognizedMainSections() {
            return Array.isArray(this.review?.recognized_main_sections) ? this.review.recognized_main_sections.slice(0, 20) : []
        },

        documentTitleCandidates() {
            return Array.isArray(this.review?.document_title_candidates) ? this.review.document_title_candidates.slice(0, 20) : []
        },

        bibliographyGroups() {
            return Array.isArray(this.review?.bibliography_groups) ? this.review.bibliography_groups : []
        },

        zoneOverview() {
            return Array.isArray(this.review?.zone_overview) ? this.review.zone_overview : []
        },

        uncertainHeadings() {
            return Array.isArray(this.review?.uncertain_headings) ? this.review.uncertain_headings.slice(0, 30) : []
        },

        emptyHeadings() {
            return Array.isArray(this.review?.empty_or_problematic_headings) ? this.review.empty_or_problematic_headings.slice(0, 30) : []
        },

        probableTocArtifacts() {
            return Array.isArray(this.review?.probable_toc_artifacts) ? this.review.probable_toc_artifacts.slice(0, 30) : []
        },

        suspiciousHeadings() {
            return Array.isArray(this.review?.suspicious_heading_texts) ? this.review.suspicious_heading_texts.slice(0, 30) : []
        },

        allBlocks() {
            return Array.isArray(this.result?.normalization?.blocks) ? this.result.normalization.blocks : []
        },

        selectedUploadFile() {
            if (Array.isArray(this.selectedFile)) {
                return this.selectedFile[0] || null
            }

            return this.selectedFile || null
        },

        selectedPdfUploadFile() {
            if (Array.isArray(this.selectedPdfFile)) {
                return this.selectedPdfFile[0] || null
            }

            return this.selectedPdfFile || null
        },

        pdfZonesFound() {
            return Array.isArray(this.pdfResult?.result?.zones_found) ? this.pdfResult.result.zones_found : []
        },

        pdfMissingParts() {
            return Array.isArray(this.pdfResult?.result?.missing_required_parts) ? this.pdfResult.result.missing_required_parts : []
        },

        pdfSuspiciousItems() {
            return Array.isArray(this.pdfResult?.result?.suspicious_items) ? this.pdfResult.result.suspicious_items : []
        },

        pdfSequenceObservations() {
            return Array.isArray(this.pdfResult?.result?.sequence_observations) ? this.pdfResult.result.sequence_observations : []
        },

        pdfNotes() {
            return Array.isArray(this.pdfResult?.result?.notes) ? this.pdfResult.result.notes : []
        },

        filteredBlocks() {
            if (this.activeFilter === 'heading') {
                return this.allBlocks.filter((block) => block?.type === 'heading')
            }

            if (this.activeFilter === 'image') {
                return this.allBlocks.filter((block) => block?.type === 'image')
            }

            if (this.activeFilter === 'section_hint') {
                return this.allBlocks.filter((block) => !!block?.section_hint)
            }

            if (this.activeFilter === 'uncertain') {
                return this.allBlocks.filter((block) => {
                    const confidence = block?.classification?.confidence || ''
                    const strategy = block?.classification?.strategy || ''
                    return confidence === 'low' || strategy === 'heuristic' || block?.is_usable_heading === false
                })
            }

            if (this.activeFilter === 'document_title') {
                return this.allBlocks.filter((block) => this.blockHasProblemTag(block, 'document_title_candidate'))
            }

            if (this.activeFilter === 'toc_artifact') {
                return this.allBlocks.filter((block) => this.blockHasProblemTag(block, 'probable_toc_artifact'))
            }

            if (this.activeFilter === 'empty_heading') {
                return this.allBlocks.filter((block) => this.blockHasProblemTag(block, 'empty_heading'))
            }

            if (this.activeFilter === 'suspicious_heading') {
                return this.allBlocks.filter((block) => this.blockHasProblemTag(block, 'suspicious_heading_text'))
            }

            if (this.activeFilter === 'zone_main_content') {
                return this.allBlocks.filter((block) => this.blockZoneKey(block) === 'main_content')
            }

            if (this.activeFilter === 'zone_toc') {
                return this.allBlocks.filter((block) => this.blockZoneKey(block) === 'table_of_contents')
            }

            return this.allBlocks
        },

        displayedBlocks() {
            if (this.showAllBlocks) {
                return this.filteredBlocks
            }

            return this.filteredBlocks.slice(0, 200)
        },

        hiddenBlockCount() {
            return Math.max(0, this.filteredBlocks.length - this.displayedBlocks.length)
        },

        copyButtonIcon() {
            if (this.copyWasSuccessful) {
                return 'mdi-check'
            }

            return 'mdi-content-copy'
        },

        copyButtonLabel() {
            if (this.copyWasSuccessful) {
                return 'Prüfbericht kopiert'
            }

            return 'Prüfbericht kopieren'
        },

        comparisonCopyButtonIcon() {
            if (this.comparisonCopyWasSuccessful) {
                return 'mdi-check'
            }

            return 'mdi-content-copy'
        },

        comparisonCopyButtonLabel() {
            if (this.comparisonCopyWasSuccessful) {
                return 'Vergleich kopiert'
            }

            return 'Vergleich kopieren'
        },
    },

    methods: {
        async runDebug() {
            if (!this.selectedUploadFile) {
                this.runError = 'Bitte wählen Sie eine DOCX-Datei aus.'
                return
            }

            this.isRunning = true
            this.runError = null
            this.result = null
            this.copyWasSuccessful = false
            this.comparisonCopyWasSuccessful = false
            this.showAllBlocks = false

            try {
                const formData = new FormData()
                formData.append('file', this.selectedUploadFile)

                const response = await axios.post('/api/admin/aba/ai-settings/pandoc-debug/run', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                })

                this.result = response.data
                this.activeFilter = 'all'
            } catch (error) {
                this.runError = this.extractErrorMessage(error)
            } finally {
                this.isRunning = false
            }
        },

        async runPdfDebug() {
            if (!this.selectedPdfUploadFile) {
                this.pdfRunError = 'Bitte wählen Sie eine PDF-Datei aus.'
                return
            }

            this.isPdfRunning = true
            this.pdfRunError = null
            this.pdfResult = null

            try {
                const formData = new FormData()
                formData.append('file', this.selectedPdfUploadFile)

                const response = await axios.post('/api/admin/aba/ai-settings/pdf-openai-debug/run', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                })

                this.pdfResult = response.data
            } catch (error) {
                this.pdfRunError = this.extractErrorMessage(error)
            } finally {
                this.isPdfRunning = false
            }
        },

        confidenceColorForPdf(confidence) {
            if (confidence === 'high') return 'green'
            if (confidence === 'medium') return 'orange'
            return 'red'
        },

        async copyReviewReport() {
            if (!this.result || this.isCopying) {
                return
            }

            const report = this.buildReviewCopyText()
            if ((report || '').trim() === '') {
                this.showCopySnackbar('Kein Prüfbericht zum Kopieren verfügbar.', 'warning')
                return
            }

            this.isCopying = true
            this.copyWasSuccessful = false

            try {
                await this.writeTextToClipboard(report)
                this.copyWasSuccessful = true
                this.showCopySnackbar('Prüfbericht kopiert.', 'success')
                window.setTimeout(() => {
                    this.copyWasSuccessful = false
                }, 1800)
            } catch (error) {
                this.showCopySnackbar('Kopieren fehlgeschlagen.', 'error')
            } finally {
                this.isCopying = false
            }
        },

        async copyComparisonReport() {
            if (!this.result || this.isComparisonCopying) {
                return
            }

            const comparisonReport = this.buildComparisonCopyText()
            if ((comparisonReport || '').trim() === '') {
                this.showCopySnackbar('Kein Vergleich zum Kopieren verfügbar.', 'warning')
                return
            }

            this.isComparisonCopying = true
            this.comparisonCopyWasSuccessful = false

            try {
                await this.writeTextToClipboard(comparisonReport)
                this.comparisonCopyWasSuccessful = true
                this.showCopySnackbar('Vergleich kopiert.', 'success')
                window.setTimeout(() => {
                    this.comparisonCopyWasSuccessful = false
                }, 1800)
            } catch (error) {
                this.showCopySnackbar('Kopieren fehlgeschlagen.', 'error')
            } finally {
                this.isComparisonCopying = false
            }
        },

        async writeTextToClipboard(text) {
            if (navigator?.clipboard?.writeText) {
                await navigator.clipboard.writeText(text)
                return
            }

            const textarea = document.createElement('textarea')
            textarea.value = text
            textarea.setAttribute('readonly', 'readonly')
            textarea.style.position = 'fixed'
            textarea.style.left = '-9999px'
            document.body.appendChild(textarea)
            textarea.select()
            const wasCopied = document.execCommand('copy')
            document.body.removeChild(textarea)

            if (!wasCopied) {
                throw new Error('clipboard_copy_failed')
            }
        },

        buildReviewCopyText() {
            const lines = []
            const generatedAt = new Date().toLocaleString('de-AT')
            const documentName = this.result?.document?.original_name || this.selectedUploadFile?.name || 'Unbekanntes Dokument'
            const documentSize = Number(this.result?.document?.size_bytes || 0)

            lines.push('AHS-ABA · Pandoc-Prüfbericht')
            lines.push(`Erstellt: ${generatedAt}`)
            lines.push(`Dokument: ${documentName}`)
            if (documentSize > 0) {
                lines.push(`Dateigröße: ${documentSize} Bytes`)
            }

            lines.push('')
            lines.push('Kennzahlen')
            lines.push(`- Normalisierte Blöcke: ${this.summary.normalized_block_count}`)
            lines.push(`- Erkannte Überschriften: ${this.summary.heading_count}`)
            lines.push(`- Erkannte Bilder: ${this.summary.image_count}`)
            lines.push(`- Blöcke mit Abschnittshinweis: ${this.summary.section_hint_count}`)
            lines.push(`- Unsichere Erkennung: ${this.summary.uncertain_or_heuristic_count}`)
            lines.push(`- Dokumenttitel-Kandidaten: ${this.summary.document_title_candidate_count}`)
            lines.push(`- Leere Überschriften: ${this.summary.empty_heading_count}`)
            lines.push(`- TOC-Artefakte: ${this.summary.probable_toc_artifact_count}`)
            lines.push(`- Auffällige Titeltexte: ${this.summary.suspicious_heading_count}`)
            lines.push(`- Erkannte Dokumentzonen: ${this.summary.zone_count}`)
            lines.push(`- Hauptteil-Blöcke: ${this.summary.zone_main_content_count}`)
            lines.push(`- TOC-Blöcke: ${this.summary.zone_table_of_contents_count}`)
            lines.push(`- Verzeichnis-/Bibliographie-Blöcke: ${this.summary.zone_bibliography_area_count}`)
            lines.push(`- Erklärungsbereich-Blöcke: ${this.summary.zone_declaration_area_count}`)

            this.appendPathComparisonSection(lines)
            this.appendZoneOverviewSection(lines)

            this.appendHeadingSection(
                lines,
                'Erkannte Hauptabschnitte',
                this.recognizedMainSections,
                (item) => `- ${item.section_type_label || item.section_type || 'Abschnitt'} | ${String(item.confidence || 'low').toUpperCase()} | ${this.reviewItemText(item)}`
            )

            this.appendHeadingSection(
                lines,
                'Unsichere Überschriften',
                this.uncertainHeadings,
                (item) => `- #${item.order || '?'} | ${String(item.confidence || 'low').toUpperCase()} | ${item.strategy || 'heuristic'} | ${this.reviewItemText(item)} | Grund: ${item.reason || 'kein_signal'}`
            )

            this.appendHeadingSection(
                lines,
                'Dokumenttitel-Kandidaten',
                this.documentTitleCandidates,
                (item) => `- #${item.order || '?'} | ${String(item.confidence || 'low').toUpperCase()} | ${this.reviewItemText(item)}`
            )

            this.appendBibliographySection(lines)

            this.appendHeadingSection(
                lines,
                'Leere Überschriften',
                this.emptyHeadings,
                (item) => `- #${item.order || '?'} | ${this.reviewItemText(item)}`
            )

            this.appendHeadingSection(
                lines,
                'Wahrscheinliche Inhaltsverzeichnis-Einträge',
                this.probableTocArtifacts,
                (item) => `- #${item.order || '?'} | ${this.reviewItemText(item)}`
            )

            this.appendHeadingSection(
                lines,
                'Auffällige Überschriftentexte',
                this.suspiciousHeadings,
                (item) => `- #${item.order || '?'} | ${this.reviewItemText(item)}`
            )

            return lines.join('\n').trim()
        },

        buildComparisonCopyText() {
            const lines = []
            const generatedAt = new Date().toLocaleString('de-AT')
            const documentName = this.result?.document?.original_name || this.selectedUploadFile?.name || 'Unbekanntes Dokument'

            lines.push('AHS-ABA · Pfadvergleich')
            lines.push(`Erstellt: ${generatedAt}`)
            lines.push(`Dokument: ${documentName}`)
            lines.push('Hinweis: Vergleich der Dokumentaufbereitung, keine Benotung der Schülerarbeit.')

            this.appendPathComparisonSection(lines)

            const legacyMissing = Array.isArray(this.legacyComparePath?.missing_required_parts) ? this.legacyComparePath.missing_required_parts : []
            const pandocMissing = Array.isArray(this.pandocComparePath?.missing_required_parts) ? this.pandocComparePath.missing_required_parts : []

            lines.push('')
            lines.push('Fehlende Pflichtzonen je Pfad')
            lines.push(`- Lokal: ${legacyMissing.length > 0 ? legacyMissing.join(', ') : 'keine'}`)
            lines.push(`- Pandoc: ${pandocMissing.length > 0 ? pandocMissing.join(', ') : 'keine'}`)

            return lines.join('\n').trim()
        },

        appendHeadingSection(lines, title, items, formatter) {
            lines.push('')
            lines.push(title)

            const collection = Array.isArray(items) ? items : []
            if (collection.length === 0) {
                lines.push('- Keine Einträge.')
                return
            }

            collection.slice(0, 50).forEach((item) => {
                lines.push(formatter(item))
            })
        },

        appendBibliographySection(lines) {
            lines.push('')
            lines.push('Quellen-/Verzeichnisbereich')

            if (!Array.isArray(this.bibliographyGroups) || this.bibliographyGroups.length === 0) {
                lines.push('- Keine gruppierten Bereiche erkannt.')
                return
            }

            this.bibliographyGroups.slice(0, 20).forEach((group) => {
                lines.push(`- ${group.group_label || group.group_key || 'Bereich'}`)
                const subtypes = Array.isArray(group.subtypes) ? group.subtypes : []
                if (subtypes.length === 0) {
                    lines.push('  - Keine Untertypen erkannt.')
                    return
                }

                subtypes.slice(0, 20).forEach((subtype) => {
                    const label = subtype.subtype_label || subtype.subtype_key || 'Untertyp'
                    const count = Number(subtype.count || 0)
                    lines.push(`  - ${label}: ${count}`)
                })
            })
        },

        appendZoneOverviewSection(lines) {
            lines.push('')
            lines.push('Dokumentphasen / Zonen')

            if (!Array.isArray(this.zoneOverview) || this.zoneOverview.length === 0) {
                lines.push('- Keine Zonen erkannt.')
                return
            }

            this.zoneOverview.slice(0, 20).forEach((zone) => {
                const label = zone.zone_label || zone.zone_key || 'Unklare Zone'
                const count = Number(zone.count || 0)
                const headingCount = Number(zone.heading_count || 0)
                const firstOrder = zone.first_order || '?'
                const lastOrder = zone.last_order || '?'
                lines.push(`- ${label}: ${count} Blöcke, ${headingCount} Überschriften (#${firstOrder} - #${lastOrder})`)
            })
        },

        appendPathComparisonSection(lines) {
            lines.push('')
            lines.push('Pfadvergleich (lokal vs. Pandoc)')

            const requiredZoneCount = Number(this.comparison?.summary?.required_zone_count || 0)
            const legacyRequiredFound = Number(this.comparison?.summary?.legacy_required_found || 0)
            const legacyMissingRequired = Number(this.comparison?.summary?.legacy_missing_required_count || 0)
            const pandocRequiredFound = Number(this.comparison?.summary?.pandoc_required_found || 0)
            const pandocMissingRequired = Number(this.comparison?.summary?.pandoc_missing_required_count || 0)

            lines.push(`- Pflichtzonen gesamt: ${requiredZoneCount}`)
            lines.push(`- Lokal: ${legacyRequiredFound} erkannt, ${legacyMissingRequired} fehlend`)
            lines.push(`- Pandoc: ${pandocRequiredFound} erkannt, ${pandocMissingRequired} fehlend`)

            if (!Array.isArray(this.comparisonZones) || this.comparisonZones.length === 0) {
                lines.push('- Keine Zonenmatrix verfügbar.')
                return
            }

            this.comparisonZones.slice(0, 30).forEach((zone) => {
                const label = zone.label || zone.zone_key || 'Zone'
                const requirement = this.requirementLabel(zone.requirement)
                const legacy = this.compareBoolLabel(Boolean(zone.legacy_local))
                const pandoc = this.compareBoolLabel(Boolean(zone.pandoc))
                lines.push(`- ${label} [${requirement}] -> Lokal: ${legacy}, Pandoc: ${pandoc}`)
            })
        },

        showCopySnackbar(message, color) {
            this.copySnackbar.message = message
            this.copySnackbar.color = color
            this.copySnackbar.visible = true
        },

        extractErrorMessage(error) {
            const responseData = error?.response?.data
            const validationErrors = responseData?.errors
            if (validationErrors && typeof validationErrors === 'object') {
                const firstErrorKey = Object.keys(validationErrors)[0]
                const firstError = Array.isArray(validationErrors[firstErrorKey]) ? validationErrors[firstErrorKey][0] : null
                if (firstError) {
                    return firstError
                }
            }

            return responseData?.message || responseData?.error?.message || responseData?.message || 'Prüfung fehlgeschlagen.'
        },

        snippet(text) {
            const value = (text || '').trim()
            if (value.length <= 220) {
                return value
            }

            return `${value.slice(0, 220)}...`
        },

        sectionHintLabel(block) {
            const hint = block?.section_hint
            if (!hint) {
                return 'Kein Abschnittshinweis'
            }

            const sectionType = hint?.section_type || 'nicht eindeutig'
            const confidence = hint?.confidence ? ` (${hint.confidence})` : ''
            const subtype = hint?.subtype_label || hint?.subtype || ''
            const subtypeText = subtype ? ` · ${subtype}` : ''
            return `${sectionType}${subtypeText}${confidence}`
        },

        reasonLabel(block) {
            const problemTags = Array.isArray(block?.problem_tags) ? block.problem_tags : []
            if (problemTags.length > 0) {
                return this.problemTagLabel(problemTags[0])
            }

            const sectionReason = block?.section_hint?.reason
            if (sectionReason) {
                return sectionReason
            }

            const signals = Array.isArray(block?.classification?.signals) ? block.classification.signals : []
            if (signals.length > 0) {
                return signals[0]
            }

            return 'Kein Grundsignal'
        },

        blockHasProblemTag(block, tag) {
            const tags = Array.isArray(block?.problem_tags) ? block.problem_tags : []
            return tags.includes(tag)
        },

        blockZoneKey(block) {
            return block?.document_zone?.zone || ''
        },

        comparePathStatusLabel(path) {
            if ((path?.status || '') === 'ok') {
                return 'ok'
            }

            if ((path?.status || '') === 'not_connected') {
                return 'nicht verbunden'
            }

            return 'fehler'
        },

        comparePathStatusColor(path) {
            if ((path?.status || '') === 'ok') {
                return 'success'
            }

            if ((path?.status || '') === 'not_connected') {
                return 'grey'
            }

            return 'warning'
        },

        compareBoolLabel(value) {
            return value ? 'erkannt' : 'nicht erkannt'
        },

        compareBoolColor(value) {
            return value ? 'success' : 'grey'
        },

        requirementLabel(requirement) {
            if (requirement === 'required' || requirement === 'pflicht') {
                return 'Pflicht'
            }
            if (requirement === 'school_specific' || requirement === 'schulspezifisch') {
                return 'schulspezifisch'
            }
            if (requirement === 'recommended' || requirement === 'empfohlen') {
                return 'empfohlen'
            }

            return 'optional'
        },

        documentZoneLabel(zone) {
            if (zone === 'title_page') {
                return 'Titelblatt'
            }
            if (zone === 'front_matter') {
                return 'Frontmatter'
            }
            if (zone === 'table_of_contents') {
                return 'Inhaltsverzeichnis'
            }
            if (zone === 'main_content') {
                return 'Hauptteil'
            }
            if (zone === 'bibliography_area') {
                return 'Verzeichnisse / Bibliographie'
            }
            if (zone === 'appendix_area') {
                return 'Anhang'
            }
            if (zone === 'declaration_area') {
                return 'Erklärungsbereich'
            }
            if (zone === 'end_matter') {
                return 'Endmatter'
            }

            return zone || 'Unklare Zone'
        },

        problemTagLabel(tag) {
            if (tag === 'empty_heading') {
                return 'Leere Überschrift'
            }
            if (tag === 'probable_toc_artifact') {
                return 'Wahrscheinlicher Inhaltsverzeichnis-Eintrag'
            }
            if (tag === 'suspicious_heading_text') {
                return 'Auffälliger Überschriftentext'
            }
            if (tag === 'document_title_candidate') {
                return 'Dokumenttitel-Kandidat'
            }
            if (tag === 'toc_duplicate_of_content_heading') {
                return 'TOC-Duplikat zu Fließtext-Heading'
            }

            return tag || 'Unbekanntes Problem'
        },

        reviewItemText(item) {
            const value = (item?.text || '').trim()
            if (value !== '') {
                return this.snippet(value)
            }

            return 'Leere Überschrift'
        },

        confidenceColor(block) {
            const confidence = block?.classification?.confidence
            return this.confidenceColorByValue(confidence)
        },

        confidenceColorByValue(confidence) {
            if (confidence === 'high') {
                return 'green'
            }
            if (confidence === 'medium') {
                return 'orange'
            }

            return 'red'
        },
    },
}
</script>

<style scoped>
.pandoc-debug-page {
    background: #0f172a;
    min-height: 100vh;
}

.pd-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.82);
    padding: 10px;
}

.pd-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.pd-nav__button {
    min-height: 54px;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.pd-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.pd-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.pd-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.metric-card {
    border: 1px solid rgba(45, 212, 191, 0.2);
    background: rgba(15, 23, 42, 0.66);
}

.metric-label {
    font-size: 0.75rem;
    color: rgba(226, 232, 240, 0.82);
    margin-bottom: 4px;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f8fafc;
}

.review-list {
    display: grid;
    gap: 8px;
}

.review-item {
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(30, 41, 59, 0.56);
}

.review-item__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 6px;
}

.review-item__text {
    color: #e2e8f0;
    line-height: 1.3;
    white-space: pre-wrap;
}

.block-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 10px;
}

.block-item {
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(30, 41, 59, 0.56);
}

.block-item__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}

.block-item__text {
    color: #e2e8f0;
    font-size: 0.9rem;
    line-height: 1.35;
    margin-bottom: 8px;
    white-space: pre-wrap;
}

.block-item__meta {
    display: grid;
    gap: 2px;
}
</style>
