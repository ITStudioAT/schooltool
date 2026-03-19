<template>
    <v-container fluid class="aba-results-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern"
            title="ABA Analyse-Ergebnisse"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#1d4ed8"
            right-orb-color="#93c5fd" />

        <v-sheet rounded="xl" class="aba-results-nav mb-2" :class="{ 'is-locked': controlsLocked }">
            <div class="aba-results-nav__buttons">
                <v-btn variant="tonal" color="white" prepend-icon="mdi-arrow-left" :disabled="controlsLocked" @click="goBack">
                    Alle ABAs
                </v-btn>
                <v-btn variant="flat" color="primary" prepend-icon="mdi-refresh" :loading="refreshing" :disabled="controlsLocked" @click="refreshNow">
                    Aktualisieren
                </v-btn>
                <v-btn
                    variant="flat"
                    color="warning"
                    prepend-icon="mdi-reload"
                    :loading="reanalysisStarting"
                    :disabled="controlsLocked || abaId <= 0"
                    @click="requestReanalysis">
                    Erneute Analyse
                </v-btn>
                <v-chip size="small" color="primary" variant="tonal">ABA #{{ abaId }}</v-chip>
                <v-chip v-if="analysisRun" size="small" :color="pendingRun ? 'warning' : 'success'" variant="tonal">
                    {{ analysisStatusLabel }}
                </v-chip>
            </div>
            <div class="aba-results-nav__toggles">
                <v-btn
                    v-for="card in cardToggleItems"
                    :key="card.key"
                    size="x-small"
                    :variant="visibleCards[card.key] ? 'flat' : 'tonal'"
                    :color="visibleCards[card.key] ? 'primary' : 'white'"
                    :prepend-icon="visibleCards[card.key] ? 'mdi-eye-outline' : 'mdi-eye-off-outline'"
                    :style="visibleCards[card.key] ? '' : 'opacity: 0.5'"
                    @click="toggleCard(card.key)"
                    @dblclick.prevent="soloCard(card.key)">
                    {{ card.label }}
                </v-btn>
            </div>
            <v-progress-linear v-if="loading || refreshing || reanalysisStarting" color="primary" indeterminate class="aba-results-nav__progress" />
        </v-sheet>

        <v-alert v-if="error" type="error" variant="tonal" rounded="lg" class="mb-3">{{ error }}</v-alert>

        <v-overlay
            :model-value="analysisLockActive"
            persistent
            class="aba-results-overlay">
            <div class="aba-results-overlay__content">
                <v-progress-circular indeterminate size="36" width="4" color="primary" />
                <div class="aba-results-overlay__text">{{ analysisLockLabel }}</div>
            </div>
        </v-overlay>

        <v-row class="w-100 ma-0" dense>
            <v-col v-if="leftColumnVisible" cols="12" lg="4">
                <ItsGridBox v-if="visibleCards.uebersicht" variant="overview" color="primary" title="Übersicht" subtitle="Gespeicherte Datensätze" icon="mdi-chart-box-outline">
                    <div class="summary-meta">
                        <div class="summary-meta__row"><span>Anzahl Seiten (echt gerendert)</span><strong>{{ renderedPageCountLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Zeichen gesamt</span><strong>{{ textLengthLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Zeichen ohne Leerzeichen</span><strong>{{ textLengthWithoutSpacesLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Dokumenttyp</span><strong>{{ documentTypeLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Extraktionskandidat</span><strong>{{ selectedCandidateLabel }}</strong></div>
                        <div v-if="showFinalConfidence" class="summary-meta__row"><span>Finale Konfidenz</span><strong>{{ formatPercent(finalConfidence) }}</strong></div>
                        <div class="summary-meta__row"><span>Qualitätswert</span><strong>{{ formatPercent(analysisQualityScore) }}</strong></div>
                        <div class="summary-meta__row"><span>Prüfstatus</span><strong>{{ reviewStateLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Extraktion-Start</span><strong>{{ analysisStartLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Extraktion-Ende</span><strong>{{ analysisEndLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Extraktion-Dauer</span><strong>{{ analysisDurationLabel }}</strong></div>
                    </div>
                </ItsGridBox>

                <ItsGridBox v-if="visibleCards.erkennungsstatus" variant="overview" color="primary" title="Erkennungsstatus" subtitle="Zentrale Dokumentteile" icon="mdi-text-box-check-outline">
                    <div class="status-list">
                        <div v-for="item in structureStatusItems" :key="item.key" class="status-list__row">
                            <span>{{ item.label }}</span>
                            <div class="status-list__right">
                                <v-chip size="x-small" :color="statusColor(item)" variant="tonal">
                                    {{ statusLabel(item) }}
                                </v-chip>
                            </div>
                        </div>
                    </div>

                </ItsGridBox>

                <ItsGridBox v-if="visibleCards.datensatzstatistik" variant="overview" color="primary" title="Datensatzstatistik" subtitle="Struktur und Typen" icon="mdi-file-tree-outline">
                    <div class="stats-grid">
                        <div v-for="item in typeStatisticItems" :key="item.key" class="stats-item">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                        </div>
                    </div>
                </ItsGridBox>

                <ItsGridBox v-if="visibleCards.qualitaetsmetriken" variant="overview" color="primary" title="Qualitätsmetriken" subtitle="Lokale Analysequalität" icon="mdi-speedometer">
                    <div class="stats-grid">
                        <div v-for="item in qualityItems" :key="item.key" class="stats-item">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                        </div>
                    </div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" :lg="leftColumnVisible ? 8 : 12" class="d-flex flex-column">
                <ItsGridBox
                    v-if="visibleCards.dokumentpruefung"
                    class="mb-3"
                    variant="overview"
                    color="primary"
                    title="Dokumentprüfung"
                    subtitle="Technische Detailanalyse in einem separaten Dialog"
                    icon="mdi-file-search-outline">
                    <template #header-actions>
                        <v-btn
                            size="x-small"
                            variant="flat"
                            color="primary"
                            prepend-icon="mdi-file-search-outline"
                            :disabled="documentReviewLoading || documentReviewRefreshing"
                            @click="openAdvancedAnalysisDialog">
                            Erweiterte Analyse
                        </v-btn>
                    </template>

                    <v-alert type="info" variant="tonal" density="compact" rounded="lg" class="mb-3">
                        Interne Dokumentprüfung zur Extraktionsqualität, keine Benotung der Schülerarbeit.
                    </v-alert>

                    <v-progress-linear
                        v-if="documentReviewLoading || documentReviewRefreshing"
                        color="primary"
                        indeterminate
                        class="mb-3" />

                    <v-alert v-if="documentReviewError" type="error" variant="tonal" density="compact" rounded="lg" class="mb-2">
                        {{ documentReviewError }}
                    </v-alert>
                    <v-alert v-else-if="!documentReviewAvailable" type="info" variant="tonal" density="compact" rounded="lg" class="mb-2">
                        {{ documentReviewMessage }}
                    </v-alert>
                    <v-sheet v-else class="review-item pa-2" rounded="lg">
                        <div class="review-item__chips mb-1">
                            <v-chip size="x-small" color="primary" variant="tonal">Pflichtzonen: {{ Number(documentReviewComparison.summary?.required_zone_count || 0) }}</v-chip>
                            <v-chip size="x-small" color="teal" variant="tonal">Pandoc erkannt: {{ Number(documentReviewComparison.summary?.pandoc_required_found || 0) }}</v-chip>
                            <v-chip size="x-small" color="orange" variant="tonal">Unsicher: {{ Number(documentReviewSummary.uncertain_or_heuristic_count || 0) }}</v-chip>
                        </div>
                        <div class="text-caption">
                            Technische Detailprüfung über <strong>Erweiterte Analyse</strong> öffnen.
                        </div>
                    </v-sheet>
                </ItsGridBox>

                <ItsGridBox
                    v-if="visibleCards.kapitel"
                    class="results-structure-card mt-3 order-local-structure"
                    variant="overview"
                    color="primary"
                    title="Erkannte Kapitel / Abschnitte (lokal)"
                    subtitle="Lokaler Analysepfad: Hauptkapitel als Collapsables, Unterstruktur innerhalb des Hauptkapitels"
                    icon="mdi-file-document-multiple-outline">
                    <template #header-actions>
                        <v-btn
                            size="x-small"
                            color="teal"
                            variant="flat"
                            :prepend-icon="chapterComparisonCopyButtonIcon"
                            :loading="copyChapterComparisonLoading"
                            @click="copyChapterComparisonReport">
                            {{ chapterComparisonCopyButtonLabel }}
                        </v-btn>
                    </template>

                    <v-alert v-if="!analysisRun" type="info" variant="tonal" rounded="lg" class="mb-3">
                        Keine Analyseergebnisse vorhanden.
                    </v-alert>

                    <template v-else>
                        <v-sheet rounded="lg" class="run-meta mb-3 pa-3">
                            <div class="run-meta__row">
                                <span>Status</span>
                                <strong>{{ analysisStatusLabel }}</strong>
                            </div>
                            <div class="run-meta__row">
                                <span>Hauptdokument</span>
                                <strong>{{ mainDocumentLabel }}</strong>
                            </div>

                        </v-sheet>

                        <div v-if="frontmatterPanelItems.length > 0" class="mb-4">
                            <v-expansion-panels multiple variant="accordion">
                                <v-expansion-panel v-for="panel in frontmatterPanelItems" :key="panel.id" :value="panel.id">
                                    <v-expansion-panel-title>
                                        <div class="chapter-panel-title">
                                            <div class="chapter-panel-title__main">
                                                <span>{{ panel.kind === 'content' ? 'Inhalt' : frontmatterPanelLabel(panel.record) }}</span>
                                                <v-chip v-if="panelPageLabel(panel)" size="x-small" color="success" variant="tonal">
                                                    {{ panelPageLabel(panel) }}
                                                </v-chip>
                                            </div>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <template v-if="panel.kind === 'content'">
                                            <v-expansion-panels v-if="chapterRootRecords.length > 0" v-model="openChapterPanels" multiple variant="accordion">
                                                <v-expansion-panel v-for="record in chapterRootRecords" :key="record.id" :value="record.id">
                                                    <v-expansion-panel-title>
                                                        <div class="chapter-panel-title">
                                                            <div class="chapter-panel-title__main">
                                                                <span>{{ record.section_title || 'Ohne Titel' }}</span>
                                                                <v-chip v-if="recordPageLabel(record)" size="x-small" color="success" variant="tonal">
                                                                    {{ recordPageLabel(record) }}
                                                                </v-chip>
                                                            </div>
                                                            <v-chip size="x-small" color="primary" variant="tonal" class="chapter-panel-title__meta">
                                                                {{ chapterDescendantCount(record.id) }} Unter-Datensätze
                                                            </v-chip>
                                                        </div>
                                                    </v-expansion-panel-title>
                                                    <v-expansion-panel-text>
                                                        <AbaRecordTree
                                                            :record="record"
                                                            :children-by-parent="childrenByParent"
                                                            :records-by-id="recordsById" />
                                                    </v-expansion-panel-text>
                                                </v-expansion-panel>
                                            </v-expansion-panels>

                                            <v-alert v-else type="info" variant="tonal" rounded="lg">
                                                Keine Hauptkapitel erkannt.
                                            </v-alert>
                                        </template>
                                        <AbaRecordTree
                                            v-else
                                            :record="panel.record"
                                            :children-by-parent="childrenByParent"
                                            :records-by-id="recordsById" />
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>

                        <div v-if="topLevelOtherRecords.length > 0" class="mt-4">
                            <div class="structure-heading mb-2">Weitere Top-Level-Datensätze</div>
                            <div class="frontmatter-grid">
                                <AbaRecordTree
                                    v-for="record in topLevelOtherRecords"
                                    :key="`other-${record.id}`"
                                    :record="record"
                                    :children-by-parent="childrenByParent"
                                    :records-by-id="recordsById" />
                            </div>
                        </div>
                    </template>
                </ItsGridBox>

                <ItsGridBox
                    v-if="visibleCards.kapitel"
                    class="results-structure-card order-pandoc-structure"
                    variant="overview"
                    color="primary"
                    title="Erkannte Kapitel / Abschnitte (Pandoc)"
                    subtitle="Pandoc-Primärpfad: Hauptabschnitte als Collapsables, Unterstruktur innerhalb des Hauptabschnitts"
                    icon="mdi-file-search-outline">
                    <v-progress-linear
                        v-if="documentReviewLoading || documentReviewRefreshing"
                        color="primary"
                        indeterminate
                        class="mb-3" />

                    <v-alert
                        v-else-if="!documentReviewAvailable"
                        type="info"
                        variant="tonal"
                        rounded="lg"
                        class="mb-3">
                        {{ documentReviewMessage }}
                    </v-alert>

                    <template v-else>
                        <div class="text-caption text-medium-emphasis mb-1">Strukturansicht (Pandoc)</div>
                        <v-alert v-if="pandocUnifiedStructureRoots.length === 0" type="warning" variant="tonal" density="compact" class="text-caption mb-3">
                            Keine belastbare Struktur erkannt.
                        </v-alert>

                        <v-expansion-panels v-else v-model="openPandocSectionPanels" multiple variant="accordion" class="mb-3">
                            <v-expansion-panel v-for="root in pandocUnifiedStructureRoots" :key="`pandoc-root-${root.id}`" :value="root.id">
                                <v-expansion-panel-title>
                                    <div class="chapter-panel-title">
                                        <div class="chapter-panel-title__main">
                                            <span>{{ pandocNodeDisplayTitle(root) }}</span>
                                            <v-chip size="x-small" color="teal" variant="tonal">{{ root.type_label }}</v-chip>
                                            <v-chip
                                                v-if="!isPandocUiContainerNode(root) && String(root.confidence || '').trim() !== ''"
                                                size="x-small"
                                                :color="confidenceColorByValue(root.confidence)"
                                                variant="tonal">
                                                {{ confidenceLabelGerman(root.confidence) }}
                                            </v-chip>
                                            <v-chip v-if="root.position_label" size="x-small" color="grey" variant="outlined">{{ root.position_label }}</v-chip>
                                        </div>
                                        <v-chip size="x-small" color="primary" variant="tonal" class="chapter-panel-title__meta">
                                            {{ pandocDescendantCount(root) }} Unter-Datensätze
                                        </v-chip>
                                    </div>
                                </v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <div v-if="root.detail_lines && root.detail_lines.length > 0" class="review-item__subtext mb-2">
                                        <div v-for="(line, lineIndex) in root.detail_lines.slice(0, 8)" :key="`pandoc-root-${root.id}-detail-${lineIndex}`" class="detail-line">
                                            <template v-if="line.includes(':')">
                                                <span class="detail-line__label">{{ line.substring(0, line.indexOf(':') + 1) }}</span>{{ line.substring(line.indexOf(':') + 1) }}
                                            </template>
                                            <template v-else>{{ line }}</template>
                                        </div>
                                    </div>

                                    <div v-if="isPandocTitlepageNode(root)" class="review-item__subtext mb-2">
                                        <div
                                            v-if="Array.isArray(root.additional_properties) && root.additional_properties.length > 0"
                                            class="mb-2">
                                            <div
                                                v-for="(property, propertyIndex) in root.additional_properties"
                                                :key="`pandoc-root-${root.id}-property-${propertyIndex}`"
                                                class="detail-line">
                                                <span class="detail-line__label">{{ property.label || property.normalized_label || 'Eigenschaft' }}:</span>
                                                {{ property.value }}
                                            </div>
                                        </div>

                                        <v-alert
                                            v-if="shouldShowTitlePageLogoSummary(root)"
                                            :type="titlePageLogoStatusColor(root)"
                                            variant="tonal"
                                            density="compact"
                                            rounded="lg"
                                            class="mb-2 text-caption">
                                            {{ titlePageLogoStatusText(root) }}
                                            <span v-if="root.logo_ui_display_note" class="d-block mt-1">{{ root.logo_ui_display_note }}</span>
                                        </v-alert>

                                        <div
                                            v-if="Array.isArray(root.logo_assets) && root.logo_assets.length > 0"
                                            class="titlepage-logo-grid">
                                            <div
                                                v-for="(asset, assetIndex) in root.logo_assets"
                                                :key="asset.id || titlePageLogoAssetNodeKey(root, asset, assetIndex)"
                                                class="titlepage-logo-card">
                                                <v-alert
                                                    :type="titlePageLogoAssetStatusColor(root, asset, assetIndex)"
                                                    variant="tonal"
                                                    density="compact"
                                                    rounded="lg"
                                                    class="mb-2 text-caption">
                                                    {{ titlePageLogoAssetStatusText(root, asset, assetIndex) }}
                                                    <span
                                                        v-if="asset.logo_ui_display_note"
                                                        class="d-block mt-1">
                                                        {{ asset.logo_ui_display_note }}
                                                    </span>
                                                </v-alert>

                                                <div
                                                    v-if="resolveTitlePageLogoAssetStatus(root, asset, assetIndex) === 'asset_ready'"
                                                    class="titlepage-logo-preview-wrap">
                                                    <img
                                                        :src="asset.logo_asset_url"
                                                        :alt="asset.logo_alt_text || 'Logo der Titelseite'"
                                                        class="titlepage-logo-preview"
                                                        @load="onTitlePageLogoLoaded(root, asset, assetIndex)"
                                                        @error="onTitlePageLogoError(root, asset, assetIndex)">
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else-if="resolveTitlePageLogoStatus(root) === 'asset_ready'" class="titlepage-logo-preview-wrap">
                                            <img
                                                :src="root.logo_asset_url"
                                                :alt="root.logo_alt_text || 'Logo der Titelseite'"
                                                class="titlepage-logo-preview"
                                                @load="onTitlePageLogoLoaded(root)"
                                                @error="onTitlePageLogoError(root)">
                                        </div>
                                    </div>

                                    <div v-if="shouldShowPandocRootContent(root) && pandocNodeDisplayLines(root, pandocNodeDisplayLineLimit(root)).length > 0" class="review-item__subtext mb-2">
                                        <div
                                            class="pandoc-content-block pandoc-root-content-block"
                                            :class="root.area === 'abstract' ? 'pandoc-root-content-block--abstract' : ''">
                                            <div
                                                v-for="(line, lineIndex) in pandocNodeDisplayLines(root, pandocNodeDisplayLineLimit(root))"
                                                :key="`pandoc-root-${root.id}-content-${lineIndex}`"
                                                class="pandoc-content-line"
                                                :class="`pandoc-content-line--${line.kind}`"
                                                :style="pandocContentLineStyle(line)">
                                                <span class="pandoc-content-line__text">{{ line.text }}</span>
                                                <span v-if="line.page" class="pandoc-content-line__page">{{ line.page }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="pandocNodeSecondaryDisplayLines(root).length > 0" class="review-item__subtext mb-2">
                                        <div class="text-caption font-weight-medium mb-1">Seitenindex-TOC</div>
                                        <div class="pandoc-content-block">
                                            <div
                                                v-for="(line, lineIndex) in pandocNodeSecondaryDisplayLines(root)"
                                                :key="`pandoc-root-${root.id}-secondary-${lineIndex}`"
                                                class="pandoc-content-line"
                                                :class="`pandoc-content-line--${line.kind}`"
                                                :style="pandocContentLineStyle(line)">
                                                <span class="pandoc-content-line__text">{{ line.text }}</span>
                                                <span v-if="line.page" class="pandoc-content-line__page">{{ line.page }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <v-alert
                                        v-if="(!root.children || root.children.length === 0) && (!root.detail_lines || root.detail_lines.length === 0)"
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                        class="text-caption">
                                        Keine Unterstruktur erkannt.
                                    </v-alert>

                                    <v-expansion-panels
                                        v-else
                                        v-model="openPandocChildPanels"
                                        multiple
                                        variant="accordion"
                                        class="pandoc-child-panels">
                                        <v-expansion-panel
                                            v-for="child in root.children"
                                            :key="`pandoc-child-${root.id}-${child.id}`"
                                            :value="child.id"
                                            elevation="0">
                                            <v-expansion-panel-title density="compact" class="pandoc-child-panel-title">
                                                <span class="pandoc-child-title-text review-item__text" :class="pandocNodeTitleClasses(child)">{{ pandocNodeDisplayTitle(child) }}</span>
                                                <div class="review-item__chips ml-2">
                                                    <v-chip size="x-small" color="teal" variant="tonal">{{ child.type_label }}</v-chip>
                                                    <v-chip size="x-small" :color="confidenceColorByValue(child.confidence)" variant="tonal">
                                                        {{ confidenceLabelGerman(child.confidence) }}
                                                    </v-chip>
                                                    <v-chip v-if="child.position_label" size="x-small" color="grey" variant="outlined">{{ child.position_label }}</v-chip>
                                                </div>
                                            </v-expansion-panel-title>
                                            <v-expansion-panel-text class="pandoc-child-panel-text">
                                                <div v-if="child.caption" class="review-item__subtext mb-2">{{ child.caption }}</div>
                                                <div v-if="pandocNodeDisplayLines(child, pandocNodeDisplayLineLimit(child, { descendant: true })).length > 0" class="review-item__subtext mb-2">
                                                    <div class="pandoc-content-block">
                                                        <div
                                                            v-for="(line, lineIndex) in pandocNodeDisplayLines(child, pandocNodeDisplayLineLimit(child, { descendant: true }))"
                                                            :key="`pandoc-child-${root.id}-${child.id}-content-${lineIndex}`"
                                                            class="pandoc-content-line"
                                                            :class="`pandoc-content-line--${line.kind}`"
                                                            :style="pandocContentLineStyle(line)">
                                                            <span class="pandoc-content-line__text">{{ line.text }}</span>
                                                            <span v-if="line.page" class="pandoc-content-line__page">{{ line.page }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-if="pandocDescendantRows(child).length > 0" class="review-list">
                                                    <v-sheet
                                                        v-for="desc in pandocDescendantRows(child)"
                                                        :key="`pandoc-desc-${root.id}-${child.id}-${desc.id}`"
                                                        class="review-item pa-2"
                                                        rounded="lg"
                                                        :style="pandocHierarchyIndentStyle(desc.depth)">
                                                        <div class="review-item__chips">
                                                            <v-chip size="x-small" color="teal" variant="tonal">{{ desc.type_label }}</v-chip>
                                                            <v-chip size="x-small" :color="confidenceColorByValue(desc.confidence)" variant="tonal">
                                                                {{ confidenceLabelGerman(desc.confidence) }}
                                                            </v-chip>
                                                            <v-chip v-if="desc.position_label" size="x-small" color="grey" variant="outlined">{{ desc.position_label }}</v-chip>
                                                        </div>
                                                        <div class="review-item__text" :class="pandocNodeTitleClasses(desc)">{{ pandocNodeDisplayTitle(desc) }}</div>
                                                        <div v-if="desc.caption" class="review-item__subtext mt-1">{{ desc.caption }}</div>
                                                        <div v-if="pandocNodeDisplayLines(desc, pandocNodeDisplayLineLimit(desc, { descendant: true })).length > 0" class="review-item__subtext mt-1">
                                                            <div class="pandoc-content-block">
                                                                <div
                                                                    v-for="(line, lineIndex) in pandocNodeDisplayLines(desc, pandocNodeDisplayLineLimit(desc, { descendant: true }))"
                                                                    :key="`pandoc-desc2-${root.id}-${child.id}-${desc.id}-content-${lineIndex}`"
                                                                    class="pandoc-content-line"
                                                                    :class="`pandoc-content-line--${line.kind}`"
                                                                    :style="pandocContentLineStyle(line)">
                                                                    <span class="pandoc-content-line__text">{{ line.text }}</span>
                                                                    <span v-if="line.page" class="pandoc-content-line__page">{{ line.page }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </v-sheet>
                                                </div>
                                            </v-expansion-panel-text>
                                        </v-expansion-panel>
                                    </v-expansion-panels>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </template>
                </ItsGridBox>
            </v-col>
        </v-row>
    </v-container>

    <v-dialog v-model="advancedAnalysisDialogOpen" persistent max-width="1280" scrollable>
        <v-card class="advanced-analysis-dialog">
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                <v-icon size="18" color="primary">mdi-file-search-outline</v-icon>
                Erweiterte Analyse
                <v-spacer />
                <v-btn icon size="x-small" variant="text" @click="closeAdvancedAnalysisDialog">
                    <v-icon size="18">mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="advanced-analysis-dialog__body">
                <v-alert type="info" variant="tonal" density="compact" rounded="lg" class="mb-3">
                    Interne Dokumentprüfung zur Extraktionsqualität, keine Benotung der Schülerarbeit.
                </v-alert>

                <div class="d-flex flex-wrap ga-2 mb-3">
                    <v-btn
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        prepend-icon="mdi-refresh"
                        :loading="documentReviewRefreshing"
                        :disabled="documentReviewLoading || documentReviewRefreshing"
                        @click="refreshDocumentReview">
                        Dokumentprüfung aktualisieren
                    </v-btn>
                    <v-btn
                        size="x-small"
                        color="teal"
                        variant="flat"
                        :prepend-icon="documentReviewCopyButtonIcon"
                        :loading="copyDocumentReviewLoading"
                        :disabled="!documentReviewAvailable"
                        @click="copyDocumentReviewReport">
                        {{ documentReviewCopyButtonLabel }}
                    </v-btn>
                </div>

                <v-progress-linear
                    v-if="documentReviewLoading || documentReviewRefreshing"
                    color="primary"
                    indeterminate
                    class="mb-3" />

                <v-alert
                    v-if="documentReviewError"
                    type="error"
                    variant="tonal"
                    density="compact"
                    rounded="lg"
                    class="mb-3">
                    {{ documentReviewError }}
                </v-alert>

                <v-alert
                    v-else-if="!documentReviewAvailable"
                    type="info"
                    variant="tonal"
                    density="compact"
                    rounded="lg"
                    class="mb-3">
                    {{ documentReviewMessage }}
                </v-alert>

                <template v-else>
                    <v-row class="w-100 ma-0 mb-2" dense>
                        <v-col cols="12" sm="6" md="4">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="text-caption text-medium-emphasis">Pflichtzonen gesamt</div>
                                <div class="text-subtitle-2">{{ Number(documentReviewComparison.summary?.required_zone_count || 0) }}</div>
                            </v-sheet>
                        </v-col>
                        <v-col cols="12" sm="6" md="4">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="text-caption text-medium-emphasis">Pandoc: Pflicht erkannt</div>
                                <div class="text-subtitle-2">{{ Number(documentReviewComparison.summary?.pandoc_required_found || 0) }}</div>
                            </v-sheet>
                        </v-col>
                        <v-col cols="12" sm="6" md="4">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="text-caption text-medium-emphasis">Pandoc: Pflicht fehlend</div>
                                <div class="text-subtitle-2">{{ Number(documentReviewComparison.summary?.pandoc_missing_required_count || 0) }}</div>
                            </v-sheet>
                        </v-col>
                        <v-col cols="12" sm="6" md="4">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="text-caption text-medium-emphasis">Unsichere Erkennung</div>
                                <div class="text-subtitle-2">{{ Number(documentReviewSummary.uncertain_or_heuristic_count || 0) }}</div>
                            </v-sheet>
                        </v-col>
                        <v-col cols="12" sm="6" md="4">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="text-caption text-medium-emphasis">TOC-Artefakte</div>
                                <div class="text-subtitle-2">{{ Number(documentReviewSummary.probable_toc_artifact_count || 0) }}</div>
                            </v-sheet>
                        </v-col>
                        <v-col cols="12" sm="6" md="4">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="text-caption text-medium-emphasis">Leere/Beschädigte Überschriften</div>
                                <div class="text-subtitle-2">
                                    {{ Number(documentReviewSummary.empty_heading_count || 0) + Number(documentReviewSummary.suspicious_heading_count || 0) }}
                                </div>
                            </v-sheet>
                        </v-col>
                    </v-row>

                    <v-row class="w-100 ma-0 mb-2" dense>
                        <v-col cols="12" md="6">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="review-item__chips mb-1">
                                    <v-chip size="x-small" color="blue" variant="tonal">{{ documentReviewLegacyPath.label || 'Lokaler Pfad' }}</v-chip>
                                    <v-chip size="x-small" :color="comparePathStatusColor(documentReviewLegacyPath)" variant="tonal">{{ comparePathStatusLabel(documentReviewLegacyPath) }}</v-chip>
                                </div>
                                <div class="text-caption">
                                    Pflicht erkannt: {{ Number(documentReviewLegacyPath.required_parts_found || 0) }} |
                                    Fehlend: {{ Array.isArray(documentReviewLegacyPath.missing_required_parts) ? documentReviewLegacyPath.missing_required_parts.length : 0 }} |
                                    TOC: {{ Number(documentReviewLegacyPath.toc_artifacts || 0) }}
                                </div>
                            </v-sheet>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-sheet class="review-item pa-2" rounded="lg">
                                <div class="review-item__chips mb-1">
                                    <v-chip size="x-small" color="teal" variant="tonal">{{ documentReviewPandocPath.label || 'Pandoc-Pfad' }}</v-chip>
                                    <v-chip size="x-small" :color="comparePathStatusColor(documentReviewPandocPath)" variant="tonal">{{ comparePathStatusLabel(documentReviewPandocPath) }}</v-chip>
                                </div>
                                <div class="text-caption">
                                    Pflicht erkannt: {{ Number(documentReviewPandocPath.required_parts_found || 0) }} |
                                    Fehlend: {{ Array.isArray(documentReviewPandocPath.missing_required_parts) ? documentReviewPandocPath.missing_required_parts.length : 0 }} |
                                    TOC: {{ Number(documentReviewPandocPath.toc_artifacts || 0) }}
                                </div>
                            </v-sheet>
                        </v-col>
                    </v-row>

                    <div class="review-list mb-2">
                        <v-sheet class="review-item pa-2" rounded="lg">
                            <div class="text-caption text-medium-emphasis mb-1">Fehlende Pflichtbestandteile</div>
                            <div class="review-item__chips">
                                <v-chip size="x-small" color="blue" variant="tonal">Lokal: {{ documentReviewLegacyMissingRequiredLabel }}</v-chip>
                                <v-chip size="x-small" color="teal" variant="tonal">Pandoc: {{ documentReviewPandocMissingRequiredLabel }}</v-chip>
                            </div>
                        </v-sheet>
                    </div>

                    <div class="text-caption text-medium-emphasis mb-1">Pflichtzonen im Pfadvergleich</div>
                    <v-alert v-if="documentReviewComparisonZones.length === 0" type="info" variant="tonal" density="compact" class="text-caption mb-2">
                        Keine Vergleichszonen verfügbar.
                    </v-alert>
                    <div v-else class="review-list mb-2">
                        <v-sheet v-for="zone in documentReviewComparisonZones.slice(0, 20)" :key="`product-compare-zone-${zone.zone_key}`" class="review-item pa-2" rounded="lg">
                            <div class="review-item__chips">
                                <v-chip size="x-small" color="indigo" variant="tonal">{{ zone.label || zone.zone_key }}</v-chip>
                                <v-chip size="x-small" color="grey" variant="outlined">{{ requirementLabel(zone.requirement) }}</v-chip>
                                <v-chip size="x-small" :color="compareBoolColor(zone.legacy_local)" variant="tonal">Lokal: {{ compareBoolLabel(zone.legacy_local) }}</v-chip>
                                <v-chip size="x-small" :color="compareBoolColor(zone.pandoc)" variant="tonal">Pandoc: {{ compareBoolLabel(zone.pandoc) }}</v-chip>
                            </div>
                        </v-sheet>
                    </div>

                    <v-row class="w-100 ma-0 mb-2" dense>
                        <v-col cols="12" lg="6">
                            <div class="text-caption text-medium-emphasis mb-1">Erkannte Kapitel / Abschnitte (lokal)</div>
                            <v-alert v-if="localSectionComparisonItems.length === 0" type="info" variant="tonal" density="compact" class="text-caption">
                                Keine lokalen Kapitel-/Abschnittseinträge erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in localSectionComparisonItems.slice(0, 120)" :key="`product-local-section-${item.id}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">{{ item.type_label }}</v-chip>
                                        <v-chip
                                            size="x-small"
                                            :color="sectionMatchStatusColor(localSectionMatchStatus(item))"
                                            variant="tonal">
                                            {{ sectionMatchStatusLabel(localSectionMatchStatus(item)) }}
                                        </v-chip>
                                        <v-chip v-if="item.page_label" size="x-small" color="success" variant="tonal">{{ item.page_label }}</v-chip>
                                        <v-chip v-if="item.numbering" size="x-small" color="grey" variant="outlined">{{ item.numbering }}</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ item.text }}</div>
                                </v-sheet>
                            </div>
                        </v-col>
                        <v-col cols="12" lg="6">
                            <div class="text-caption text-medium-emphasis mb-1">Erkannte Kapitel / Abschnitte (Pandoc)</div>
                            <v-alert v-if="pandocSectionComparisonItems.length === 0" type="info" variant="tonal" density="compact" class="text-caption">
                                Keine Pandoc-Kapitel-/Abschnittseinträge erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in pandocSectionComparisonItems.slice(0, 120)" :key="`product-pandoc-section-${item.id}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="teal" variant="tonal">{{ item.type_label }}</v-chip>
                                        <v-chip
                                            size="x-small"
                                            :color="sectionMatchStatusColor(pandocSectionMatchStatus(item))"
                                            variant="tonal">
                                            {{ sectionMatchStatusLabel(pandocSectionMatchStatus(item)) }}
                                        </v-chip>
                                        <v-chip v-if="item.position_label" size="x-small" color="grey" variant="outlined">{{ item.position_label }}</v-chip>
                                        <v-chip size="x-small" :color="confidenceColorByValue(item.confidence)" variant="tonal">{{ String(item.confidence || 'low').toUpperCase() }}</v-chip>
                                        <v-chip size="x-small" color="deep-purple" variant="tonal">{{ item.strategy || 'heuristic' }}</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ item.text }}</div>
                                </v-sheet>
                            </div>
                        </v-col>
                    </v-row>

                    <v-row class="w-100 ma-0 mb-2" dense>
                        <v-col cols="12" lg="6">
                            <div class="text-caption text-medium-emphasis mb-1">Unsichere Erkennung</div>
                            <v-alert v-if="documentReviewUncertainHeadings.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine unsicheren Überschriften erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in documentReviewUncertainHeadings" :key="`product-uncertain-${item.order}-${item.text}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" :color="confidenceColorByValue(item.confidence)" variant="tonal">{{ String(item.confidence || 'low').toUpperCase() }}</v-chip>
                                        <v-chip size="x-small" color="deep-purple" variant="tonal">{{ item.strategy || 'heuristic' }}</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </v-col>
                    </v-row>

                    <v-row class="w-100 ma-0 mb-2" dense>
                        <v-col cols="12" md="4">
                            <div class="text-caption text-medium-emphasis mb-1">Wahrscheinliche TOC-Artefakte</div>
                            <v-alert v-if="documentReviewTocArtifacts.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine TOC-Artefakte erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in documentReviewTocArtifacts" :key="`product-toc-${item.order}-${item.text}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" color="orange" variant="tonal">TOC</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </v-col>
                        <v-col cols="12" md="4">
                            <div class="text-caption text-medium-emphasis mb-1">Leere Überschriften</div>
                            <v-alert v-if="documentReviewEmptyHeadings.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine leeren Überschriften erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in documentReviewEmptyHeadings" :key="`product-empty-${item.order}-${item.id}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" color="red" variant="tonal">leer</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </v-col>
                        <v-col cols="12" md="4">
                            <div class="text-caption text-medium-emphasis mb-1">Auffällige Überschriftentexte</div>
                            <v-alert v-if="documentReviewSuspiciousHeadings.length === 0" type="success" variant="tonal" density="compact" class="text-caption">
                                Keine auffälligen Überschriftentexte erkannt.
                            </v-alert>
                            <div v-else class="review-list">
                                <v-sheet v-for="item in documentReviewSuspiciousHeadings" :key="`product-susp-${item.order}-${item.text}`" class="review-item pa-2" rounded="lg">
                                    <div class="review-item__chips">
                                        <v-chip size="x-small" color="blue" variant="tonal">#{{ item.order }}</v-chip>
                                        <v-chip size="x-small" color="red" variant="tonal">auffällig</v-chip>
                                    </div>
                                    <div class="review-item__text">{{ reviewItemText(item) }}</div>
                                </v-sheet>
                            </div>
                        </v-col>
                    </v-row>

                    <div class="text-caption text-medium-emphasis mb-1">Dokumentzonen / Hauptphasen</div>
                    <v-alert v-if="documentReviewZoneOverview.length === 0" type="info" variant="tonal" density="compact" class="text-caption">
                        Keine Zonen erkannt.
                    </v-alert>
                    <div v-else class="review-list">
                        <v-sheet v-for="zone in documentReviewZoneOverview" :key="`product-zone-${zone.zone_key}`" class="review-item pa-2" rounded="lg">
                            <div class="review-item__chips">
                                <v-chip size="x-small" color="indigo" variant="tonal">{{ zone.zone_label || zone.zone_key }}</v-chip>
                                <v-chip size="x-small" color="blue" variant="tonal">{{ zone.count || 0 }} Blöcke</v-chip>
                                <v-chip size="x-small" color="teal" variant="tonal">{{ zone.heading_count || 0 }} Überschriften</v-chip>
                                <v-chip size="x-small" color="grey" variant="outlined">#{{ zone.first_order || '?' }} - #{{ zone.last_order || '?' }}</v-chip>
                            </div>
                        </v-sheet>
                    </div>
                </template>
            </v-card-text>
            <v-divider />
            <v-card-actions class="justify-end">
                <v-btn variant="tonal" color="primary" @click="closeAdvancedAnalysisDialog">
                    Schließen
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="reanalysisConfirmDialogOpen" persistent max-width="480">
        <v-card>
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                <v-icon size="18" color="warning">mdi-reload</v-icon>
                Analyse wirklich starten?
            </v-card-title>
            <v-divider />
            <v-card-text>
                <div class="text-body-2 mb-2">
                    Für diese ABA wird eine erneute Analyse gestartet:
                </div>
                <div class="text-body-2 font-weight-bold">
                    {{ aba?.title || `ABA #${abaId}` }}
                </div>
            </v-card-text>
            <v-divider />
            <v-card-actions>
                <v-btn variant="tonal" color="warning" @click="reanalysisConfirmDialogOpen = false">
                    Abbrechen
                </v-btn>
                <v-spacer />
                <v-btn variant="flat" color="warning" @click="confirmReanalysis">
                    Analyse starten
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import AbaRecordTree from '@/pages/admin/aba/components/AbaRecordTree.vue'

export default {
    components: { ItsGridBox, AdminSectionHero, AbaRecordTree },

    data() {
        return {
            loading: true,
            refreshing: false,
            reanalysisStarting: false,
            reanalysisConfirmDialogOpen: false,
            advancedAnalysisDialogOpen: false,
            visibleCards: {
                uebersicht: true,
                erkennungsstatus: true,
                datensatzstatistik: true,
                qualitaetsmetriken: true,
                dokumentpruefung: true,
                kapitel: true,
            },
            error: '',
            payload: null,
            documentReviewPayload: null,
            documentReviewLoading: false,
            documentReviewRefreshing: false,
            documentReviewError: '',
            copyDocumentReviewLoading: false,
            copyDocumentReviewWasSuccessful: false,
            copyChapterComparisonLoading: false,
            copyChapterComparisonWasSuccessful: false,
            pollTimer: null,
            pollInFlight: false,
            openChapterPanels: [],
            openPandocSectionPanels: [],
            openPandocChildPanels: [],
            titlePageLogoRenderErrors: {},
            titlePageLogoRenderLoads: {},
        }
    },

    computed: {
        abaId() {
            const value = Number(this.$route?.params?.abaId || 0)
            return Number.isFinite(value) && value > 0 ? value : 0
        },
        aba() {
            return this.payload?.aba || null
        },
        mainDocument() {
            return this.payload?.main_document || null
        },
        analysisRun() {
            return this.payload?.analysis_run || null
        },
        sections() {
            const items = this.payload?.sections
            if (!Array.isArray(items)) {
                return []
            }

            return [...items].sort((left, right) => this.compareRecordsByPage(left, right))
        },
        safeSummary() {
            return this.analysisRun?.summary && typeof this.analysisRun.summary === 'object' ? this.analysisRun.summary : {}
        },
        analysisStats() {
            if (this.analysisRun?.analysis_stats && typeof this.analysisRun.analysis_stats === 'object') {
                return this.analysisRun.analysis_stats
            }

            if (this.safeSummary.analysis_stats && typeof this.safeSummary.analysis_stats === 'object') {
                return this.safeSummary.analysis_stats
            }

            return {}
        },
        recordCounts() {
            if (this.analysisRun?.record_counts && typeof this.analysisRun.record_counts === 'object') {
                return this.analysisRun.record_counts
            }

            if (this.safeSummary.record_counts && typeof this.safeSummary.record_counts === 'object') {
                return this.safeSummary.record_counts
            }

            return {}
        },
        displayValues() {
            if (this.analysisRun?.display_values && typeof this.analysisRun.display_values === 'object') {
                return this.analysisRun.display_values
            }

            if (this.safeSummary.display_values && typeof this.safeSummary.display_values === 'object') {
                return this.safeSummary.display_values
            }

            return {}
        },
        recordsById() {
            return this.sections.reduce((carry, record) => {
                const id = Number(record?.id || 0)
                if (id > 0) {
                    carry[id] = record
                }
                return carry
            }, {})
        },
        childrenByParent() {
            return this.sections.reduce((carry, record) => {
                const parentId = Number(record?.parent_result_id || 0)
                if (!parentId) {
                    return carry
                }

                if (!Array.isArray(carry[parentId])) {
                    carry[parentId] = []
                }

                carry[parentId].push(record)
                carry[parentId].sort((left, right) => this.compareRecordsByPage(left, right))
                return carry
            }, {})
        },
        frontmatterRecords() {
            return this.sections.filter((record) => this.isFrontmatterType(record?.section_type))
        },
        chapterRootRecords() {
            return this.sections.filter((record) => {
                if (record?.section_type !== 'chapter') {
                    return false
                }

                return !this.hasChapterAncestor(record)
            })
        },
        frontmatterPanelItems() {
            const items = this.frontmatterRecords.map((record) => ({
                id: `frontmatter-${record.id}`,
                kind: 'record',
                record,
            }))

            if (this.chapterRootRecords.length > 0) {
                const firstChapter = this.chapterRootRecords[0]
                items.push({
                    id: 'frontmatter-content',
                    kind: 'content',
                    anchor: {
                        start_page: firstChapter?.start_page ?? null,
                        start_line: firstChapter?.start_line ?? null,
                        sort_order: firstChapter?.sort_order ?? null,
                        id: Number(firstChapter?.id ?? 0) + 1000000,
                    },
                })
            }

            return items.sort((left, right) => this.compareRecordsByPage(this.panelSortReference(left), this.panelSortReference(right)))
        },
        topLevelOtherRecords() {
            return this.sections.filter((record) => {
                const parentId = Number(record?.parent_result_id || 0)
                if (parentId) {
                    return false
                }

                if (record?.section_type === 'chapter') {
                    return false
                }

                if (this.isFrontmatterType(record?.section_type)) {
                    return false
                }

                return true
            })
        },
        pendingRun() {
            const status = String(this.analysisRun?.status || '')
            return status === 'started' || status === 'running'
        },
        analysisLockActive() {
            return this.reanalysisStarting || this.pendingRun
        },
        controlsLocked() {
            return this.loading || this.refreshing || this.analysisLockActive
        },
        analysisLockLabel() {
            if (this.reanalysisStarting) {
                return 'Erneute Analyse wird gestartet...'
            }

            return 'Analyse läuft. Bitte warten...'
        },
        analysisStatusLabel() {
            const status = String(this.analysisRun?.status || '')
            if (status === 'started') return 'gestartet'
            if (status === 'running') return 'läuft'
            if (status === 'completed') return 'abgeschlossen'
            if (status === 'aborted') return 'abgebrochen'
            if (status === 'failed') return 'Fehler'
            return 'nicht gestartet'
        },
        analysisStartLabel() {
            const timestamp = this.displayValues.analysis_start_at
                || this.analysisRun?.started_at
                || this.analysisRun?.running_at
                || this.analysisRun?.created_at
                || null

            return timestamp ? this.formatDateTime(timestamp) : '-'
        },
        analysisEndLabel() {
            const timestamp = this.displayValues.analysis_end_at
                || this.analysisRun?.completed_at
                || this.analysisRun?.failed_at
                || this.analysisRun?.aborted_at
                || null

            return timestamp ? this.formatDateTime(timestamp) : '-'
        },
        analysisDurationLabel() {
            const durationFromBackend = Number(this.displayValues.analysis_duration_seconds ?? null)
            if (Number.isFinite(durationFromBackend) && durationFromBackend >= 0) {
                return this.formatDuration(durationFromBackend)
            }

            const start = this.parseTimestamp(this.analysisRun?.started_at || this.analysisRun?.running_at || this.analysisRun?.created_at || null)
            if (start === null) {
                return '-'
            }

            const end = this.parseTimestamp(this.analysisRun?.completed_at || this.analysisRun?.failed_at || this.analysisRun?.aborted_at || null)
            const durationSeconds = Math.max(0, Math.floor(((end ?? new Date()).getTime() - start.getTime()) / 1000))

            return this.formatDuration(durationSeconds)
        },
        totalRecordCount() {
            return Number(this.displayValues.total_record_count ?? this.persistedRecordCount ?? this.sections.length ?? 0)
        },
        detectedRecordCount() {
            return Number(this.recordCounts.detected_record_count ?? this.analysisStats.detected_record_count ?? this.sections.length ?? 0)
        },
        normalizedRecordCount() {
            return Number(this.recordCounts.normalized_record_count ?? this.analysisStats.normalized_record_count ?? 0)
        },
        validatedRecordCount() {
            return Number(this.recordCounts.validated_record_count ?? this.analysisStats.validated_record_count ?? 0)
        },
        persistedRecordCount() {
            return Number(this.recordCounts.persisted_record_count ?? this.analysisStats.persisted_record_count ?? this.analysisRun?.persisted_record_count ?? this.sections.length ?? 0)
        },
        mismatchDetected() {
            return Boolean(this.analysisStats.count_mismatch_detected)
        },
        mismatchReason() {
            const value = String(this.analysisStats.count_mismatch_reason || '').trim()
            return value || null
        },
        documentTypeLabel() {
            return String(this.displayValues.document_type || this.analysisStats.document_type || this.safeSummary?.normalized_json?.document_type || 'unbekannt')
        },
        selectedCandidateLabel() {
            return String(this.displayValues.selected_candidate || this.analysisStats.selected_candidate || this.safeSummary?.extraction?.selected_candidate || '-').trim() || '-'
        },
        finalConfidence() {
            return this.displayValues.final_confidence ?? this.analysisStats.final_confidence
        },
        analysisQualityScore() {
            return this.displayValues.analysis_quality_score ?? this.analysisStats.analysis_quality_score
        },
        textLengthValue() {
            return Number(
                this.displayValues.text_length
                ?? this.analysisStats.text_length
                ?? this.analysisRun?.text_length
                ?? this.safeSummary?.extraction?.text_length
                ?? 0
            )
        },
        textLengthWithoutSpacesValue() {
            const storedValue = Number(
                this.displayValues.text_length_without_spaces
                ?? this.analysisStats.text_length_without_spaces
                ?? this.analysisRun?.text_length_without_spaces
                ?? this.safeSummary?.extraction?.text_length_without_spaces
                ?? 0
            )

            if (Number.isFinite(storedValue) && storedValue > 0) {
                return storedValue
            }

            const fallbackFromSections = this.sections.reduce((total, record) => {
                const text = String(record?.extracted_text || '')
                return total + text.replace(/\s+/g, '').length
            }, 0)

            return Number.isFinite(fallbackFromSections) ? fallbackFromSections : 0
        },
        textLengthLabel() {
            if (!Number.isFinite(this.textLengthValue) || this.textLengthValue <= 0) {
                return '-'
            }

            return this.formatInteger(this.textLengthValue)
        },
        textLengthWithoutSpacesLabel() {
            if (!Number.isFinite(this.textLengthWithoutSpacesValue) || this.textLengthWithoutSpacesValue <= 0) {
                return '-'
            }

            return this.formatInteger(this.textLengthWithoutSpacesValue)
        },
        renderedPageCountLabel() {
            const hasReal = Boolean(this.analysisStats.has_real_pagination ?? this.analysisRun?.has_real_pagination ?? false)
            const totalPages = Number(this.analysisStats.page_count_total ?? this.analysisRun?.page_count_total ?? 0)

            if (!hasReal || !Number.isFinite(totalPages) || totalPages <= 0) {
                return '--'
            }

            return this.formatInteger(totalPages)
        },
        showFinalConfidence() {
            const analysisQuality = Number(this.analysisQualityScore)
            const finalConfidence = Number(this.finalConfidence)

            if (!Number.isFinite(finalConfidence)) {
                return false
            }

            if (!Number.isFinite(analysisQuality)) {
                return true
            }

            const finalDisplayPercent = (finalConfidence * 100).toFixed(1)
            const analysisDisplayPercent = (analysisQuality * 100).toFixed(1)

            return finalDisplayPercent !== analysisDisplayPercent
        },
        reviewStateLabel() {
            const state = String(this.displayValues.review_state || this.analysisStats.review_state || this.safeSummary?.review?.state || 'unbekannt')
            if (state === 'auto_approved') {
                const thresholdPercent = this.autoApproveThresholdPercentLabel
                if (thresholdPercent) {
                    return `automatisch freigegeben (ab ${thresholdPercent})`
                }

                return 'automatisch freigegeben'
            }
            if (state === 'review_required') {
                return 'manuelle Prüfung erforderlich'
            }

            return state
        },
        autoApproveThreshold() {
            const threshold = Number(
                this.displayValues.auto_approve_confidence_threshold
                ?? this.analysisStats.auto_approve_confidence_threshold
                ?? this.safeSummary?.review?.threshold
                ?? NaN,
            )
            if (!Number.isFinite(threshold)) {
                return null
            }

            return Math.max(0, Math.min(1, threshold))
        },
        autoApproveThresholdPercentLabel() {
            if (this.autoApproveThreshold === null) {
                return ''
            }

            return `${Math.round(this.autoApproveThreshold * 100)}%`
        },
        autoApproved() {
            return Boolean(this.displayValues.auto_approved ?? this.analysisStats.auto_approved ?? this.safeSummary?.review?.auto_approved ?? false)
        },
        hasRealPagination() {
            return Boolean(this.analysisRun?.has_real_pagination ?? this.analysisStats.has_real_pagination ?? false)
        },
        paginationSource() {
            return String(this.analysisRun?.pagination_source ?? this.analysisStats.pagination_source ?? 'not_available')
        },
        pageCountTotal() {
            return Number(this.analysisRun?.page_count_total ?? this.analysisStats.page_count_total ?? 0)
        },
        recordsWithPageMapping() {
            return Number(this.analysisRun?.records_with_page_mapping_count ?? this.analysisStats.records_with_page_mapping_count ?? 0)
        },
        recordsWithoutPageMapping() {
            return Number(this.analysisRun?.records_without_page_mapping_count ?? this.analysisStats.records_without_page_mapping_count ?? 0)
        },
        pageMappingCoverageLabel() {
            const coverage = Number(this.analysisStats.page_mapping_coverage ?? this.analysisRun?.page_mapping_coverage ?? 0)
            if (!Number.isFinite(coverage)) {
                return '—'
            }

            return `${Math.round(coverage * 100)} %`
        },
        mainDocumentLabel() {
            if (!this.mainDocument) {
                return 'nicht vorhanden'
            }

            return String(this.mainDocument.original_name || `Dokument #${this.mainDocument.id}`)
        },
        structureStatusItems() {
            const stats = this.analysisStats
            return [
                this.makeStatusItem('title_page_detected', 'Titelseite', stats.title_page_detected, stats.title_page_start_line, stats.title_page_end_line),
                this.makeStatusItem('abstract_detected', 'Zusammenfassung', stats.abstract_detected, null, null),
                this.makeStatusItem('abstract_de_detected', 'Deutsche Zusammenfassung', stats.abstract_de_detected, stats.abstract_de_start_line, stats.abstract_de_end_line),
                this.makeStatusItem('abstract_en_detected', 'Englische Zusammenfassung (optional)', stats.abstract_en_detected, stats.abstract_en_start_line, stats.abstract_en_end_line, true),
                this.makeStatusItem('foreword_detected', 'Vorwort', stats.foreword_detected, stats.foreword_start_line, stats.foreword_end_line),
                this.makeStatusItem('table_of_contents_detected', 'Inhaltsverzeichnis', stats.table_of_contents_detected, stats.toc_start_line, stats.toc_end_line),
                this.makeStatusItem('body_detected', 'Hauptteil', stats.body_detected, stats.body_start_line, null),
                this.makeStatusItem('bibliography_detected', 'Bibliographie', stats.bibliography_detected, stats.bibliography_start_line, stats.bibliography_end_line),
                this.makeStatusItem('figure_index_detected', 'Abbildungsverzeichnis', stats.figure_index_detected, stats.figure_index_start_line, stats.figure_index_end_line),
                this.makeStatusItem('consent_declaration_detected', 'Eigenständigkeitserklärung', stats.consent_declaration_detected, stats.consent_declaration_start_line, stats.consent_declaration_end_line),
            ]
        },
        typeStatisticItems() {
            const stats = this.analysisStats
            return [
                { key: 'chapter_count', label: 'Hauptkapitel', value: Number(stats.chapter_count || 0) },
                { key: 'subchapter_count', label: 'Unterkapitel', value: Number(stats.subchapter_count || 0) },
                { key: 'max_hierarchy_level', label: 'Max. Hierarchietiefe', value: Number(stats.max_hierarchy_level || 1) },
                { key: 'figure_count', label: 'Abbildungen', value: Number(stats.figure_count || 0) },
                { key: 'bibliography_count', label: 'Bibliographie-Datensätze', value: Number(stats.bibliography_count || 0) },
                { key: 'figure_index_count', label: 'Datensätze im Abbildungsverzeichnis', value: Number(stats.figure_index_count || 0) },
                { key: 'other_section_count', label: 'Sonstige Datensätze', value: Number(stats.other_section_count || 0) },
                { key: 'bibliography_entry_count', label: 'Literatureinträge erkannt', value: Number(stats.bibliography_entry_count || 0) },
                { key: 'multi_line_caption_count', label: 'Mehrzeilige Abbildungsbeschriftungen', value: Number(stats.multi_line_caption_count || 0) },
                { key: 'toc_special_entries_count', label: 'Sondereinträge im Inhaltsverzeichnis', value: Number(stats.toc_special_entries_count || 0) },
                { key: 'dataset_boundary_adjustments_count', label: 'Korrigierte Datensatzgrenzen', value: Number(stats.dataset_boundary_adjustments_count || 0) },
                { key: 'hierarchy_anomaly_count', label: 'Hierarchie-Auffälligkeiten', value: Number(stats.hierarchy_anomaly_count || 0) },
                { key: 'orphan_candidate_count', label: 'Unzugeordnete Überschriftenkandidaten', value: Number(stats.orphan_candidate_count || 0) },
                { key: 'unresolved_heading_candidates_count', label: 'Nicht übernommene Überschriftenkandidaten', value: Number(stats.unresolved_heading_candidates_count || 0) },
            ]
        },
        qualityItems() {
            const stats = this.analysisStats
            const items = [
                { key: 'analysis_quality_score', label: 'Gesamtqualität der Analyse', value: this.formatPercent(stats.analysis_quality_score) },
                { key: 'extraction_consistency_score', label: 'Stimmigkeit der Erkennung', value: this.formatPercent(stats.extraction_consistency_score) },
                { key: 'local_extraction_confidence', label: 'Sicherheit der lokalen Erkennung', value: this.formatPercent(stats.local_extraction_confidence) },
                { key: 'structure_confidence', label: 'Sicherheit der Strukturerkennung', value: this.formatPercent(stats.structure_confidence) },
                { key: 'toc_detection_confidence', label: 'Sicherheit beim Inhaltsverzeichnis', value: this.formatPercent(stats.toc_detection_confidence) },
                { key: 'hierarchy_confidence', label: 'Sicherheit der Kapitelhierarchie', value: this.formatPercent(stats.hierarchy_confidence) },
                { key: 'frontmatter_boundary_confidence', label: 'Sicherheit der Frontmatter-Abgrenzung', value: this.formatPercent(stats.frontmatter_boundary_confidence) },
                { key: 'body_reentry_confidence', label: 'Sicherheit beim Übergang in den Hauptteil', value: this.formatPercent(stats.body_reentry_confidence) },
                { key: 'heading_assignment_confidence', label: 'Sicherheit der Überschriftenzuordnung', value: this.formatPercent(stats.heading_assignment_confidence) },
                { key: 'bibliography_context_confidence', label: 'Sicherheit im Bibliographie-Kontext', value: this.formatPercent(stats.bibliography_context_confidence) },
                { key: 'figure_mapping_confidence', label: 'Sicherheit der Abbildungszuordnung', value: this.formatPercent(stats.figure_mapping_confidence) },
            ]

            if (this.showFinalConfidence) {
                items.push({
                    key: 'final_confidence',
                    label: 'Gesamtzuverlässigkeit',
                    value: this.formatPercent(stats.final_confidence),
                })
            }

            items.push(
                { key: 'validation_error_count', label: 'Validierungsfehler', value: Number(stats.validation_error_count || 0) },
                { key: 'validation_warning_count', label: 'Validierungswarnungen', value: Number(stats.validation_warning_count || 0) },
                { key: 'missing_fields_count', label: 'Fehlende Felder', value: Number(stats.missing_fields_count || 0) },
            )

            return items
        },
        documentReview() {
            return this.documentReviewPayload && typeof this.documentReviewPayload === 'object'
                ? this.documentReviewPayload
                : {}
        },
        documentReviewAvailable() {
            return Boolean(this.documentReview.available)
        },
        documentReviewMessage() {
            const value = String(this.documentReview.message || '').trim()
            if (value !== '') {
                return value
            }

            return 'Dokumentprüfung ist aktuell nicht verfügbar.'
        },
        documentReviewSummary() {
            return this.documentReview.summary && typeof this.documentReview.summary === 'object'
                ? this.documentReview.summary
                : {
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
                }
        },
        documentReviewData() {
            return this.documentReview.review && typeof this.documentReview.review === 'object'
                ? this.documentReview.review
                : {}
        },
        documentReviewNormalizationBlocks() {
            const blocks = this.documentReviewPayload?.normalization?.blocks
            return Array.isArray(blocks) ? blocks : []
        },
        documentReviewTitlePageProcessing() {
            return this.documentReviewData.title_page_processing && typeof this.documentReviewData.title_page_processing === 'object'
                ? this.documentReviewData.title_page_processing
                : {}
        },
        documentReviewTitlePageNormalized() {
            return this.documentReviewTitlePageProcessing.normalized_output && typeof this.documentReviewTitlePageProcessing.normalized_output === 'object'
                ? this.documentReviewTitlePageProcessing.normalized_output
                : {}
        },
        documentReviewTitlePageLogo() {
            return this.documentReviewTitlePageProcessing.logo && typeof this.documentReviewTitlePageProcessing.logo === 'object'
                ? this.documentReviewTitlePageProcessing.logo
                : {}
        },
        documentReviewTitlePageLogos() {
            return Array.isArray(this.documentReviewTitlePageProcessing.logos)
                ? this.documentReviewTitlePageProcessing.logos
                : []
        },
        documentReviewTitlePageUiModel() {
            return this.documentReviewTitlePageProcessing.ui_model && typeof this.documentReviewTitlePageProcessing.ui_model === 'object'
                ? this.documentReviewTitlePageProcessing.ui_model
                : {}
        },
        documentReviewTitlePageNormalizedDetailLines() {
            return this.buildTitlePageDetailLinesFromNormalized(this.documentReviewTitlePageNormalized, {
                aba: this.aba,
                normalizationBlocks: this.documentReviewNormalizationBlocks,
            })
        },
        documentReviewTitlePageAdditionalProperties() {
            const normalized = this.normalizeTitlePageAdditionalProperties(
                this.documentReviewTitlePageNormalized?.additional_properties
            )

            const withRecoveredDocumentType = this.ensureRecoveredDocumentTypeProperty(normalized, {
                documentTypeLabel: this.documentTypeLabel,
                normalizationBlocks: this.documentReviewNormalizationBlocks,
            })

            return this.ensureRecoveredSchoolProperties(withRecoveredDocumentType, {
                aba: this.aba,
                normalizationBlocks: this.documentReviewNormalizationBlocks,
            })
        },
        documentReviewComparison() {
            return this.documentReview.comparison && typeof this.documentReview.comparison === 'object'
                ? this.documentReview.comparison
                : {
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
        documentReviewLegacyPath() {
            return this.documentReviewComparison.paths?.legacy_local && typeof this.documentReviewComparison.paths.legacy_local === 'object'
                ? this.documentReviewComparison.paths.legacy_local
                : {}
        },
        documentReviewPandocPath() {
            return this.documentReviewComparison.paths?.pandoc && typeof this.documentReviewComparison.paths.pandoc === 'object'
                ? this.documentReviewComparison.paths.pandoc
                : {}
        },
        documentReviewComparisonZones() {
            return Array.isArray(this.documentReviewComparison.matrix?.zones)
                ? this.documentReviewComparison.matrix.zones
                : []
        },
        documentReviewMainSections() {
            return Array.isArray(this.documentReviewData.recognized_main_sections)
                ? this.documentReviewData.recognized_main_sections.slice(0, 20)
                : []
        },
        documentReviewSpecialSections() {
            return Array.isArray(this.documentReviewData.special_sections)
                ? this.documentReviewData.special_sections.slice(0, 80)
                : []
        },
        pandocFigureIndexEntries() {
            const items = Array.isArray(this.documentReviewData.figure_index_entries)
                ? this.documentReviewData.figure_index_entries
                : []

            return items
                .map((item, index) => {
                    const text = String(item?.text || '').trim()
                    if (text === '') {
                        return null
                    }

                    const type = String(item?.section_type || item?.type || 'figure').trim().toLowerCase()
                    const semanticType = this.normalizeSectionSemanticType(type, String(item?.document_zone || 'bibliography_area'))
                    const numbering = this.resolveComparableNumbering(text, type)
                    const numberingDepth = this.numberingDepth(numbering)
                    const previewLines = Array.isArray(item?.content_preview_lines)
                        ? this.normalizeProjectionLines(item.content_preview_lines, true)
                        : []
                    const contentText = String(item?.content_text || '').trim()
                    const contentExcerpt = String(item?.content_excerpt || '').trim()
                    const fallbackExcerpt = String(item?.caption || item?.source_text || '').trim()

                    return {
                        id: item?.id ?? `pandoc-figure-index-${index}-${item?.order || 0}`,
                        source: 'pandoc',
                        text,
                        caption: String(item?.caption || '').trim(),
                        type,
                        type_label: String(item?.section_type_label || this.localSectionTypeLabel(type || 'figure')),
                        confidence: String(item?.confidence || 'medium'),
                        strategy: String(item?.strategy || 'heuristic'),
                        order: Number(item?.order || (index + 1)),
                        heading_level: this.normalizePandocHeadingLevel(item?.heading_level ?? item?.outline_level ?? 4),
                        outline_level: this.normalizePandocHeadingLevel(item?.outline_level ?? item?.heading_level ?? 4),
                        is_usable_heading: Boolean(item?.is_usable_heading ?? true),
                        problem_tags: Array.isArray(item?.problem_tags) ? item.problem_tags.map((value) => String(value || '')) : [],
                        compare_key: this.figureCompareKey(text, String(item?.caption || item?.source_text || '')),
                        semantic_type: semanticType,
                        semantic_compare_key: this.sectionSemanticCompareKey(text, semanticType),
                        zone_context: String(item?.document_zone || 'bibliography_area'),
                        parent_text: 'Abbildungsverzeichnis',
                        parent_compare_key: this.sectionCompareKey('Abbildungsverzeichnis'),
                        parent_numbering: '',
                        numbering,
                        numbering_depth: numberingDepth,
                        structure_level: this.resolveStructureLevel({
                            numberingDepth,
                            fallbackLevel: Number(item?.outline_level || item?.heading_level || 4),
                            semanticType,
                            itemType: type,
                        }),
                        content_text: contentText !== '' ? contentText : null,
                        content_excerpt: contentExcerpt !== '' ? contentExcerpt : (fallbackExcerpt !== '' ? fallbackExcerpt : null),
                        content_preview_lines: previewLines.length > 0 ? previewLines : (fallbackExcerpt !== '' ? [fallbackExcerpt] : []),
                        content_line_count: Number(item?.content_line_count || previewLines.length || (fallbackExcerpt !== '' ? 1 : 0)),
                        position_label: String(item?.position_label || (Number(item?.order || 0) > 0 ? `Block #${Number(item?.order || 0)}` : '')),
                        depth: 0,
                        children: [],
                    }
                })
                .filter((item) => item !== null)
                .slice(0, 40)
        },
        localSectionComparisonItems() {
            const items = []
            const seen = new Set()
            const supportedTypes = new Set([
                'title_page',
                'abstract',
                'foreword',
                'table_of_contents',
                'bibliography',
                'figure_index',
                'consent_declaration',
                'chapter',
                'subchapter',
                'figure',
            ])

            this.sections.forEach((record, index) => {
                const type = String(record?.section_type || '').trim().toLowerCase()
                if (!supportedTypes.has(type)) {
                    return
                }

                const text = String(this.localRecordDisplayTitle(record) || '').trim()
                if (text === '') {
                    return
                }

                const recordId = Number(record?.id || 0)
                const identifier = recordId > 0 ? `local-${recordId}` : `local-${index}-${type}`
                if (seen.has(identifier)) {
                    return
                }
                seen.add(identifier)

                const zoneContext = this.resolveLocalZoneContext(type)
                const semanticType = this.normalizeSectionSemanticType(type, zoneContext)
                const parentId = Number(record?.parent_result_id || 0)
                const parentRecord = parentId > 0 ? this.recordsById[parentId] || null : null
                const parentText = parentRecord ? String(this.localRecordDisplayTitle(parentRecord) || '').trim() : ''
                const numbering = this.resolveComparableNumbering(text, type)
                const numberingDepth = this.numberingDepth(numbering)
                const hierarchyLevel = Number(record?.hierarchy_level || 0)
                const compareKey = type === 'figure'
                    ? this.figureCompareKey(text)
                    : this.sectionCompareKey(text)

                items.push({
                    id: identifier,
                    source: 'local',
                    source_id: recordId > 0 ? recordId : null,
                    text,
                    type,
                    type_label: this.localSectionTypeLabel(type),
                    zone_context: zoneContext,
                    semantic_type: semanticType,
                    page_label: this.recordPageLabel(record),
                    position_label: this.recordPageLabel(record),
                    compare_key: compareKey,
                    semantic_compare_key: this.sectionSemanticCompareKey(text, semanticType),
                    parent_id: parentId > 0 ? parentId : null,
                    parent_text: parentText,
                    parent_compare_key: this.sectionCompareKey(parentText),
                    parent_numbering: this.extractSectionNumbering(parentText),
                    numbering,
                    numbering_depth: numberingDepth,
                    structure_level: this.resolveStructureLevel({
                        numberingDepth,
                        fallbackLevel: hierarchyLevel,
                        semanticType,
                        itemType: type,
                    }),
                    sort_order: Number(record?.sort_order || (index + 1)),
                })
            })

            return items
                .sort((left, right) => Number(left.sort_order || 0) - Number(right.sort_order || 0))
                .slice(0, 220)
        },
        pandocOutline() {
            return this.documentReviewData?.outline && typeof this.documentReviewData.outline === 'object'
                ? this.documentReviewData.outline
                : {}
        },
        pandocFrontmatterSections() {
            return Array.isArray(this.pandocOutline.frontmatter_sections)
                ? this.pandocOutline.frontmatter_sections.slice(0, 40)
                : []
        },
        pandocEndmatterSections() {
            return Array.isArray(this.pandocOutline.endmatter_sections)
                ? this.pandocOutline.endmatter_sections.slice(0, 40)
                : []
        },
        pandocExcludedHeadings() {
            return Array.isArray(this.pandocOutline.excluded_headings)
                ? this.pandocOutline.excluded_headings.slice(0, 40)
                : []
        },
        pandocSectionHierarchyRoots() {
            const roots = Array.isArray(this.pandocOutline.main_content_outline)
                ? this.pandocOutline.main_content_outline
                : []

            return roots
                .map((node, index) => this.normalizePandocOutlineNode(node, 0, index, null))
                .filter((node) => node !== null)
                .slice(0, 30)
        },
        pandocUnifiedStructureRoots() {
            const specialRoots = (Array.isArray(this.pandocSpecialSections) ? this.pandocSpecialSections : [])
                .map((item) => ({
                    ...item,
                    root_kind: 'special',
                    children: [],
                }))
            const mainRoots = (Array.isArray(this.pandocSectionHierarchyRoots) ? this.pandocSectionHierarchyRoots : [])
                .map((item) => ({
                    ...item,
                    root_kind: 'main',
                }))
            const localFigurePlacementIndex = this.buildLocalFigurePlacementIndex()
            const figureIndexChildren = []
            ;(Array.isArray(this.pandocFigureIndexEntries) ? this.pandocFigureIndexEntries : []).forEach((item) => {
                const attached = this.attachFigureEntryToPandocMainTree(mainRoots, item, localFigurePlacementIndex)
                if (attached) {
                    return
                }

                figureIndexChildren.push({
                    ...item,
                    root_kind: 'figure_index_entry',
                    children: Array.isArray(item?.children) ? item.children : [],
                })
            })

            const orphanFigures = (Array.isArray(this.pandocOutline.main_content_orphan_figures) ? this.pandocOutline.main_content_orphan_figures : [])
                .map((item, index) => {
                    const text = String(item?.text || '').trim()
                    if (text === '') {
                        return null
                    }

                    const type = String(item?.section_type || item?.type || 'figure').trim().toLowerCase()
                    const semanticType = this.normalizeSectionSemanticType(type, String(item?.document_zone || 'main_content'))
                    const numbering = this.resolveComparableNumbering(text, type)
                    const numberingDepth = this.numberingDepth(numbering)
                    const previewLines = Array.isArray(item?.content_preview_lines)
                        ? this.normalizeProjectionLines(item.content_preview_lines, true)
                        : []
                    const contentText = String(item?.content_text || '').trim()
                    const contentExcerpt = String(item?.content_excerpt || '').trim()

                    return {
                        id: item?.id ?? `pandoc-unified-orphan-${index}-${item?.order || 0}`,
                        source: 'pandoc',
                        text,
                        type,
                        type_label: String(item?.section_type_label || this.localSectionTypeLabel(type || 'figure')),
                        confidence: String(item?.confidence || 'medium'),
                        strategy: String(item?.strategy || 'heuristic'),
                        order: Number(item?.order || (index + 1)),
                        heading_level: this.normalizePandocHeadingLevel(item?.heading_level ?? item?.outline_level ?? 4),
                        outline_level: this.normalizePandocHeadingLevel(item?.outline_level ?? item?.heading_level ?? 4),
                        is_usable_heading: Boolean(item?.is_usable_heading ?? true),
                        problem_tags: Array.isArray(item?.problem_tags) ? item.problem_tags.map((value) => String(value || '')) : [],
                        compare_key: type === 'figure'
                            ? this.figureCompareKey(text, String(item?.caption || item?.source_text || ''))
                            : String(item?.compare_key || this.sectionCompareKey(text)),
                        semantic_type: semanticType,
                        semantic_compare_key: this.sectionSemanticCompareKey(text, semanticType),
                        zone_context: String(item?.document_zone || 'main_content'),
                        parent_text: '',
                        parent_compare_key: '',
                        parent_numbering: '',
                        numbering,
                        numbering_depth: numberingDepth,
                        structure_level: this.resolveStructureLevel({
                            numberingDepth,
                            fallbackLevel: Number(item?.outline_level || item?.heading_level || 4),
                            semanticType,
                            itemType: type,
                        }),
                        content_text: contentText !== '' ? contentText : null,
                        content_excerpt: contentExcerpt !== '' ? contentExcerpt : null,
                        content_preview_lines: previewLines,
                        content_line_count: Number(item?.content_line_count || previewLines.length),
                        position_label: String(item?.position_label || (Number(item?.order || 0) > 0 ? `Block #${Number(item?.order || 0)}` : '')),
                        depth: 1,
                        children: [],
                        root_kind: 'orphan_figure',
                    }
                })
                .filter((item) => item !== null)

            if (figureIndexChildren.length > 0 || orphanFigures.length > 0) {
                const mergedChildren = [...figureIndexChildren]
                const dedupeKey = new Set(
                    mergedChildren.map((item) => `${String(item?.compare_key || '')}|${String(item?.position_label || '')}`)
                )
                orphanFigures.forEach((item) => {
                    const key = `${String(item?.compare_key || '')}|${String(item?.position_label || '')}`
                    if (dedupeKey.has(key)) {
                        return
                    }
                    dedupeKey.add(key)
                    mergedChildren.push(item)
                })

                const figureIndexRoot = specialRoots.find((item) => String(item?.area || '') === 'figure_index')
                if (figureIndexRoot) {
                    figureIndexRoot.children = mergedChildren
                } else if (mergedChildren.length > 0) {
                    specialRoots.push({
                        id: 'pandoc-figure-bucket',
                        root_kind: 'special',
                        area: 'figure_index',
                        area_label: 'Abbildungsverzeichnis',
                        text: 'Abbildungsverzeichnis',
                        display_text: 'Abbildungsverzeichnis',
                        type: 'figure_index',
                        type_label: 'Abbildungsverzeichnis',
                        confidence: 'medium',
                        strategy: 'heuristic',
                        compare_key: this.sectionCompareKey('Abbildungsverzeichnis'),
                        semantic_type: 'figure_index',
                        semantic_compare_key: this.sectionSemanticCompareKey('Abbildungsverzeichnis', 'figure_index'),
                        zone_context: 'bibliography_area',
                        parent_text: '',
                        parent_compare_key: '',
                        parent_numbering: '',
                        numbering: '',
                        numbering_depth: 0,
                        structure_level: 0,
                        order: mergedChildren[0]?.order || 999999,
                        position_label: '',
                        problem_tags: [],
                        detail_lines: [`Einträge: ${mergedChildren.length}`],
                        content_text: null,
                        content_excerpt: null,
                        content_preview_lines: [],
                        content_line_count: 0,
                        children: mergedChildren,
                    })
                }
            }

            this.sortPandocNodeTreeByOrder(mainRoots)
            const contentRoot = this.buildPandocContentContainerRoot(mainRoots)
            const topLevelRoots = [...specialRoots]
            if (contentRoot) {
                topLevelRoots.push(contentRoot)
            }

            return topLevelRoots
                .sort((left, right) => {
                    const leftRank = this.pandocUnifiedTopLevelRank(left)
                    const rightRank = this.pandocUnifiedTopLevelRank(right)
                    if (leftRank !== rightRank) {
                        return leftRank - rightRank
                    }

                    const leftOrder = Number(left?.order || 0)
                    const rightOrder = Number(right?.order || 0)
                    if (leftOrder !== rightOrder) {
                        return leftOrder - rightOrder
                    }

                    const leftKey = String(left?.compare_key || left?.text || '')
                    const rightKey = String(right?.compare_key || right?.text || '')
                    return leftKey.localeCompare(rightKey, 'de')
                })
                .slice(0, 120)
        },
        pandocSectionComparisonItems() {
            const rows = []
            const append = (nodes) => {
                if (!Array.isArray(nodes) || nodes.length === 0) {
                    return
                }

                nodes.forEach((node) => {
                    if (!node || typeof node !== 'object') {
                        return
                    }

                    rows.push(node)
                    append(node.children)
                })
            }

            append(this.pandocSectionHierarchyRoots)

            const orphanFigures = Array.isArray(this.pandocOutline.main_content_orphan_figures)
                ? this.pandocOutline.main_content_orphan_figures
                : []
            orphanFigures.forEach((item, index) => {
                const text = String(item?.text || '').trim()
                if (text === '') {
                    return
                }

                const type = String(item?.section_type || item?.type || 'figure').trim().toLowerCase()
                const semanticType = this.normalizeSectionSemanticType(type, String(item?.document_zone || 'main_content'))
                const numbering = this.resolveComparableNumbering(text, type)
                const numberingDepth = this.numberingDepth(numbering)
                const previewLines = Array.isArray(item?.content_preview_lines)
                    ? this.normalizeProjectionLines(item.content_preview_lines, true)
                    : []
                const contentText = String(item?.content_text || '').trim()
                const contentExcerpt = String(item?.content_excerpt || '').trim()
                const fallbackLevel = this.resolveStructureLevel({
                    numberingDepth,
                    fallbackLevel: Number(item?.outline_level || item?.heading_level || 2),
                    semanticType,
                    itemType: type,
                })

                rows.push({
                    id: item?.id ?? `pandoc-main-orphan-${index}-${item?.order || 0}`,
                    source: 'pandoc',
                    text,
                    type,
                    type_label: String(item?.section_type_label || this.localSectionTypeLabel(type || 'figure')),
                    confidence: String(item?.confidence || 'medium'),
                    strategy: String(item?.strategy || 'heuristic'),
                    order: Number(item?.order || (index + 1)),
                    heading_level: this.normalizePandocHeadingLevel(item?.heading_level ?? item?.outline_level ?? 2),
                    outline_level: this.normalizePandocHeadingLevel(item?.outline_level ?? item?.heading_level ?? 2),
                    is_usable_heading: Boolean(item?.is_usable_heading ?? true),
                    problem_tags: Array.isArray(item?.problem_tags) ? item.problem_tags.map((value) => String(value || '')) : [],
                    compare_key: type === 'figure'
                        ? this.figureCompareKey(text, String(item?.caption || item?.source_text || ''))
                        : String(item?.compare_key || this.sectionCompareKey(text)),
                    semantic_type: semanticType,
                    semantic_compare_key: this.sectionSemanticCompareKey(text, semanticType),
                    zone_context: String(item?.document_zone || 'main_content'),
                    parent_text: '',
                    parent_compare_key: '',
                    parent_numbering: '',
                    numbering,
                    numbering_depth: numberingDepth,
                    structure_level: fallbackLevel,
                    content_text: contentText !== '' ? contentText : null,
                    content_excerpt: contentExcerpt !== '' ? contentExcerpt : null,
                    content_preview_lines: previewLines,
                    content_line_count: Number(item?.content_line_count || previewLines.length),
                    position_label: String(item?.position_label || (Number(item?.order || 0) > 0 ? `Block #${Number(item?.order || 0)}` : '')),
                    depth: 0,
                    children: [],
                })
            })

            this.pandocFigureIndexEntries.forEach((item) => {
                rows.push({
                    ...item,
                })
            })

            const normalizedRows = rows
                .filter((item) => String(item?.text || '').trim() !== '')
                .sort((left, right) => Number(left.order || 0) - Number(right.order || 0))

            const dedupedRows = []
            const seenKeys = new Set()
            normalizedRows.forEach((item) => {
                const key = [
                    String(item?.source || ''),
                    String(item?.type || ''),
                    String(item?.compare_key || ''),
                    String(item?.position_label || ''),
                ].join('|')

                if (seenKeys.has(key)) {
                    return
                }
                seenKeys.add(key)
                dedupedRows.push(item)
            })

            return dedupedRows.slice(0, 220)
        },
        pandocSpecialSections() {
            if (this.documentReviewSpecialSections.length > 0) {
                const sections = this.documentReviewSpecialSections
                    .map((item, index) => {
                        const text = String(item?.text || '').trim()
                        const sectionType = String(item?.section_type || '')
                        const areaKey = this.normalizeSectionSemanticType(
                            String(item?.special_area_key || sectionType || item?.document_zone || ''),
                            String(item?.document_zone || '')
                        )
                        const previewLines = Array.isArray(item?.content_preview_lines)
                            ? this.normalizeProjectionLines(item.content_preview_lines, true)
                            : []
                        const tocOutlineLines = Array.isArray(item?.toc_outline_lines)
                            ? this.normalizeProjectionLines(item.toc_outline_lines, true)
                            : []
                        const tocPageIndexLines = Array.isArray(item?.toc_page_index_lines)
                            ? this.normalizeProjectionLines(item.toc_page_index_lines, true)
                            : []
                        const tocPrimaryKind = String(item?.toc_primary_kind || '').trim().toLowerCase()
                        const contentText = String(item?.content_text || '').trim()
                        const contentExcerpt = String(item?.content_excerpt || '').trim()

                        return {
                            id: item?.id ?? `special-${index}`,
                            area: areaKey || 'special',
                            area_label: String(item?.special_area_label || this.sectionAreaLabel(areaKey || 'special')),
                            text,
                            display_text: String(item?.display_text || text),
                            detail_lines: Array.isArray(item?.detail_lines) ? item.detail_lines.map((line) => String(line || '').trim()).filter((line) => line !== '') : [],
                            logo_asset_url: null,
                            logo_alt_text: null,
                            logo_ui_display_note: null,
                            logo_detected: false,
                            logo_count: 0,
                            logo_detected_count: 0,
                            logo_asset_available: false,
                            logo_asset_available_count: 0,
                            logo_ui_displayable: false,
                            logo_ui_displayable_count: 0,
                            logo_assets: [],
                            additional_properties: [],
                            type_label: String(item?.section_type_label || this.localSectionTypeLabel(sectionType || 'abschnitt')),
                            confidence: String(item?.confidence || 'low'),
                            strategy: String(item?.strategy || 'heuristic'),
                            compare_key: String(item?.compare_key || this.sectionCompareKey(text)),
                            semantic_type: areaKey || 'frontmatter',
                            semantic_compare_key: this.sectionSemanticCompareKey(text, areaKey || 'frontmatter'),
                            zone_context: String(item?.document_zone || ''),
                            parent_text: '',
                            parent_compare_key: '',
                            parent_numbering: '',
                            numbering: this.resolveComparableNumbering(text, sectionType),
                            numbering_depth: this.numberingDepth(this.resolveComparableNumbering(text, sectionType)),
                            structure_level: 0,
                            content_text: contentText !== '' ? contentText : null,
                            content_excerpt: contentExcerpt !== '' ? contentExcerpt : null,
                            content_preview_lines: previewLines,
                            content_line_count: Number(item?.content_line_count || previewLines.length),
                            toc_outline_lines: tocOutlineLines,
                            toc_page_index_lines: tocPageIndexLines,
                            toc_primary_kind: tocPrimaryKind !== '' ? tocPrimaryKind : 'outline',
                            position_label: String(item?.position_label || (Number(item?.order || 0) > 0 ? `Block #${Number(item?.order || 0)}` : '')),
                            problem_tags: Array.isArray(item?.problem_tags) ? item.problem_tags.map((value) => String(value || '')) : [],
                        }
                    })
                    .map((item) => {
                        if (item.area === 'titlepage') {
                            const normalizedDetailLines = this.documentReviewTitlePageNormalizedDetailLines
                            if (normalizedDetailLines.length > 0) {
                                item.detail_lines = normalizedDetailLines
                            }
                            item.additional_properties = this.documentReviewTitlePageAdditionalProperties

                            const logo = this.documentReviewTitlePageLogo
                            const uiModel = this.documentReviewTitlePageUiModel
                            const logos = this.normalizeTitlePageLogoAssets(this.documentReviewTitlePageLogos, uiModel)
                                .map((entry, index) => ({
                                    ...entry,
                                    id: this.titlePageLogoAssetNodeKey(item, entry, index),
                                }))
                            const displayableLogos = logos.filter((entry) => entry.logo_ui_displayable && String(entry.logo_asset_url || '').trim() !== '')
                            const primaryLogo = displayableLogos[0] || logos[0] || null
                            item.logo_assets = logos
                            item.logo_count = logos.length
                            item.logo_detected_count = logos.filter((entry) => entry.logo_detected).length
                            item.logo_asset_available_count = logos.filter((entry) => entry.logo_asset_available).length
                            item.logo_ui_displayable_count = displayableLogos.length
                            item.logo_detected = item.logo_detected_count > 0 || Boolean(logo.logo_detected)
                            item.logo_asset_available = item.logo_asset_available_count > 0 || Boolean(logo.logo_asset_available)
                            item.logo_ui_displayable = item.logo_ui_displayable_count > 0 || Boolean(logo.logo_ui_displayable)
                            item.logo_asset_url = primaryLogo
                                ? String(primaryLogo.logo_asset_url || '').trim() || null
                                : (String(logo.logo_asset_url || uiModel.logo_asset_url || '').trim() || null)
                            item.logo_alt_text = primaryLogo
                                ? String(primaryLogo.logo_alt_text || 'Logo der Titelseite').trim()
                                : String(logo.logo_alt_text || uiModel.logo_alt_text || 'Logo der Titelseite').trim()
                            item.logo_ui_display_note = String(logo.logo_ui_display_note || '').trim() || null
                        }

                        if (item.area === 'figure_index' && item.detail_lines.length === 0 && this.pandocFigureIndexEntries.length > 0) {
                            const figureLines = this.pandocFigureIndexEntries.slice(0, 6).map((entry) => {
                                const caption = String(entry?.caption || '').trim()
                                return caption !== '' ? `${entry.text}: ${caption}` : entry.text
                            })
                            item.detail_lines = [`Einträge: ${this.pandocFigureIndexEntries.length}`, ...figureLines]
                        }

                        return item
                    })
                    .filter((item) => item.text !== '')
                    .slice(0, 60)

                return this.ensurePandocAbstractSpecialSection(sections)
            }

            const sections = []
            this.pandocFrontmatterSections.forEach((item, index) => {
                const text = String(item?.text || '').trim()
                const semanticType = this.normalizeSectionSemanticType(String(item?.section_type || ''), String(item?.document_zone || 'front_matter'))
                sections.push({
                    id: `front-${item?.id ?? index}`,
                    area: semanticType || 'frontmatter',
                    area_label: this.sectionAreaLabel(semanticType || 'frontmatter'),
                    text,
                    display_text: text,
                    detail_lines: [],
                    type_label: String(item?.section_type_label || item?.section_type || 'Abschnitt'),
                    confidence: String(item?.confidence || 'low'),
                    strategy: String(item?.strategy || 'heuristic'),
                    compare_key: String(item?.compare_key || this.sectionCompareKey(text)),
                    semantic_type: semanticType || 'frontmatter',
                    semantic_compare_key: this.sectionSemanticCompareKey(text, semanticType || 'frontmatter'),
                    zone_context: String(item?.document_zone || ''),
                    parent_text: '',
                    parent_compare_key: '',
                    parent_numbering: '',
                    numbering: this.resolveComparableNumbering(text, String(item?.section_type || '')),
                    numbering_depth: this.numberingDepth(this.resolveComparableNumbering(text, String(item?.section_type || ''))),
                    structure_level: 0,
                    content_text: String(item?.content_text || '').trim() || null,
                    content_excerpt: String(item?.content_excerpt || '').trim() || null,
                    content_preview_lines: Array.isArray(item?.content_preview_lines)
                        ? this.normalizeProjectionLines(item.content_preview_lines, true)
                        : [],
                    content_line_count: Number(item?.content_line_count || 0),
                    toc_outline_lines: [],
                    toc_page_index_lines: [],
                    toc_primary_kind: 'outline',
                    position_label: String(item?.position_label || (Number(item?.order || 0) > 0 ? `Block #${Number(item?.order || 0)}` : '')),
                    problem_tags: Array.isArray(item?.problem_tags) ? item.problem_tags.map((value) => String(value || '')) : [],
                })
            })
            this.pandocEndmatterSections.forEach((item, index) => {
                const text = String(item?.text || '').trim()
                const semanticType = this.normalizeSectionSemanticType(String(item?.section_type || ''), String(item?.document_zone || 'end_matter'))
                sections.push({
                    id: `end-${item?.id ?? index}`,
                    area: semanticType || 'endmatter',
                    area_label: this.sectionAreaLabel(semanticType || 'endmatter'),
                    text,
                    display_text: text,
                    detail_lines: [],
                    type_label: String(item?.section_type_label || item?.section_type || 'Abschnitt'),
                    confidence: String(item?.confidence || 'low'),
                    strategy: String(item?.strategy || 'heuristic'),
                    compare_key: String(item?.compare_key || this.sectionCompareKey(text)),
                    semantic_type: semanticType || 'endmatter',
                    semantic_compare_key: this.sectionSemanticCompareKey(text, semanticType || 'endmatter'),
                    zone_context: String(item?.document_zone || ''),
                    parent_text: '',
                    parent_compare_key: '',
                    parent_numbering: '',
                    numbering: this.resolveComparableNumbering(text, String(item?.section_type || '')),
                    numbering_depth: this.numberingDepth(this.resolveComparableNumbering(text, String(item?.section_type || ''))),
                    structure_level: 0,
                    content_text: String(item?.content_text || '').trim() || null,
                    content_excerpt: String(item?.content_excerpt || '').trim() || null,
                    content_preview_lines: Array.isArray(item?.content_preview_lines)
                        ? this.normalizeProjectionLines(item.content_preview_lines, true)
                        : [],
                    content_line_count: Number(item?.content_line_count || 0),
                    toc_outline_lines: [],
                    toc_page_index_lines: [],
                    toc_primary_kind: 'outline',
                    position_label: String(item?.position_label || (Number(item?.order || 0) > 0 ? `Block #${Number(item?.order || 0)}` : '')),
                    problem_tags: Array.isArray(item?.problem_tags) ? item.problem_tags.map((value) => String(value || '')) : [],
                })
            })

            const filteredSections = sections.filter((item) => item.text !== '').slice(0, 60)
            return this.ensurePandocAbstractSpecialSection(filteredSections)
        },
        chapterMatchingMatrix() {
            return this.buildChapterMatchingMatrix(
                Array.isArray(this.localSectionComparisonItems) ? this.localSectionComparisonItems : [],
                Array.isArray(this.pandocSectionComparisonItems) ? this.pandocSectionComparisonItems : [],
                Array.isArray(this.pandocSpecialSections) ? this.pandocSpecialSections : []
            )
        },
        documentReviewUncertainHeadings() {
            return Array.isArray(this.documentReviewData.uncertain_headings)
                ? this.documentReviewData.uncertain_headings.slice(0, 20)
                : []
        },
        documentReviewEmptyHeadings() {
            return Array.isArray(this.documentReviewData.empty_or_problematic_headings)
                ? this.documentReviewData.empty_or_problematic_headings.slice(0, 20)
                : []
        },
        documentReviewTocArtifacts() {
            return Array.isArray(this.documentReviewData.probable_toc_artifacts)
                ? this.documentReviewData.probable_toc_artifacts.slice(0, 20)
                : []
        },
        documentReviewSuspiciousHeadings() {
            return Array.isArray(this.documentReviewData.suspicious_heading_texts)
                ? this.documentReviewData.suspicious_heading_texts.slice(0, 20)
                : []
        },
        documentReviewZoneOverview() {
            return Array.isArray(this.documentReviewData.zone_overview)
                ? this.documentReviewData.zone_overview.slice(0, 20)
                : []
        },
        documentReviewBibliographyGroups() {
            return Array.isArray(this.documentReviewData.bibliography_groups)
                ? this.documentReviewData.bibliography_groups.slice(0, 20)
                : []
        },
        documentReviewLegacyMissingRequiredLabel() {
            const values = Array.isArray(this.documentReviewLegacyPath.missing_required_parts)
                ? this.documentReviewLegacyPath.missing_required_parts
                : []

            if (values.length === 0) {
                return 'keine'
            }

            return values.join(', ')
        },
        documentReviewPandocMissingRequiredLabel() {
            const values = Array.isArray(this.documentReviewPandocPath.missing_required_parts)
                ? this.documentReviewPandocPath.missing_required_parts
                : []

            if (values.length === 0) {
                return 'keine'
            }

            return values.join(', ')
        },
        documentReviewCopyButtonIcon() {
            if (this.copyDocumentReviewWasSuccessful) {
                return 'mdi-check'
            }

            return 'mdi-content-copy'
        },
        documentReviewCopyButtonLabel() {
            if (this.copyDocumentReviewWasSuccessful) {
                return 'Prüfbericht kopiert'
            }

            return 'Prüfbericht kopieren'
        },
        chapterComparisonCopyButtonIcon() {
            if (this.copyChapterComparisonWasSuccessful) {
                return 'mdi-check'
            }

            return 'mdi-content-copy'
        },
        chapterComparisonCopyButtonLabel() {
            if (this.copyChapterComparisonWasSuccessful) {
                return 'Kapitelvergleich kopiert'
            }

            return 'Kapitelvergleich kopieren'
        },
        leftColumnVisible() {
            return this.visibleCards.uebersicht
                || this.visibleCards.erkennungsstatus
                || this.visibleCards.datensatzstatistik
                || this.visibleCards.qualitaetsmetriken
        },
        cardToggleItems() {
            return [
                { key: 'uebersicht', label: 'Übersicht' },
                { key: 'erkennungsstatus', label: 'Erkennungsstatus' },
                { key: 'datensatzstatistik', label: 'Datensatzstatistik' },
                { key: 'qualitaetsmetriken', label: 'Qualitätsmetriken' },
                { key: 'dokumentpruefung', label: 'Dokumentprüfung' },
                { key: 'kapitel', label: 'Erkannte Kapitel / Abschnitte (lokal)' },
            ]
        },
        activeSection() {
            return {
                label: 'Analyse-Ergebnisse',
                icon: 'mdi-poll',
                note: this.aba?.title ? `${this.aba.title}` : 'Lokale Analyseausgabe',
            }
        },
        headerChips() {
            const chips = []
            if (this.aba?.title) {
                chips.push({ key: 'title', text: this.aba.title, icon: 'mdi-text-box-outline' })
            }
            if (this.aba?.student_name) {
                chips.push({ key: 'student', text: this.aba.student_name, icon: 'mdi-account-outline' })
            }
            if (this.aba?.student_class) {
                chips.push({ key: 'class', text: this.aba.student_class, icon: 'mdi-google-classroom' })
            }
            return chips
        },
    },

    async mounted() {
        await this.loadResults()
        this.startPolling()
    },

    beforeUnmount() {
        this.stopPolling()
    },

    methods: {
        async loadResults(silent = false) {
            if (this.abaId <= 0) {
                this.error = 'Ungültige ABA-ID.'
                this.loading = false
                return
            }

            if (!silent) {
                this.loading = true
            }
            this.error = ''

            try {
                const response = await axios.get(`/api/admin/abas/${this.abaId}/analysis/results`)
                this.payload = response?.data?.data || {
                    aba: null,
                    main_document: null,
                    analysis_run: null,
                    sections: [],
                }

                if (!this.mainDocument) {
                    this.documentReviewPayload = null
                    this.documentReviewError = ''
                } else if (!this.pendingRun) {
                    await this.loadDocumentReview(true)
                }
            } catch (error) {
                this.error = error?.response?.data?.message || 'Analyseergebnisse konnten nicht geladen werden.'
            } finally {
                this.loading = false
                this.refreshing = false
            }
        },
        async refreshNow() {
            this.refreshing = true
            await this.loadResults(true)
        },
        async loadDocumentReview(silent = false) {
            if (this.abaId <= 0 || !this.mainDocument) {
                this.documentReviewPayload = null
                this.documentReviewError = ''
                this.titlePageLogoRenderErrors = {}
                this.titlePageLogoRenderLoads = {}
                return
            }

            if (silent) {
                this.documentReviewRefreshing = true
            } else {
                this.documentReviewLoading = true
            }
            this.documentReviewError = ''

            try {
                this.titlePageLogoRenderErrors = {}
                this.titlePageLogoRenderLoads = {}
                const response = await axios.get(`/api/admin/abas/${this.abaId}/analysis/document-review`)
                this.documentReviewPayload = response?.data?.data || null
            } catch (error) {
                this.documentReviewError = error?.response?.data?.message || 'Dokumentprüfung konnte nicht geladen werden.'
            } finally {
                this.documentReviewLoading = false
                this.documentReviewRefreshing = false
            }
        },
        async refreshDocumentReview() {
            if (this.documentReviewLoading || this.documentReviewRefreshing) {
                return
            }

            await this.loadDocumentReview(true)
        },
        openAdvancedAnalysisDialog() {
            this.advancedAnalysisDialogOpen = true
        },
        closeAdvancedAnalysisDialog() {
            this.advancedAnalysisDialogOpen = false
        },
        async copyDocumentReviewReport() {
            if (!this.documentReviewAvailable || this.copyDocumentReviewLoading) {
                return
            }

            const report = this.buildDocumentReviewCopyText()
            if ((report || '').trim() === '') {
                return
            }

            this.copyDocumentReviewLoading = true
            this.copyDocumentReviewWasSuccessful = false

            try {
                await this.writeTextToClipboard(report)
                this.copyDocumentReviewWasSuccessful = true
                window.setTimeout(() => {
                    this.copyDocumentReviewWasSuccessful = false
                }, 1800)
            } catch (_error) {
                this.copyDocumentReviewWasSuccessful = false
            } finally {
                this.copyDocumentReviewLoading = false
            }
        },
        async copyChapterComparisonReport() {
            if (this.copyChapterComparisonLoading) {
                return
            }

            const report = this.buildChapterComparisonCopyText()
            if ((report || '').trim() === '') {
                return
            }

            this.copyChapterComparisonLoading = true
            this.copyChapterComparisonWasSuccessful = false

            try {
                await this.writeTextToClipboard(report)
                this.copyChapterComparisonWasSuccessful = true
                window.setTimeout(() => {
                    this.copyChapterComparisonWasSuccessful = false
                }, 1800)
            } catch (_error) {
                this.copyChapterComparisonWasSuccessful = false
            } finally {
                this.copyChapterComparisonLoading = false
            }
        },
        buildChapterComparisonCopyText() {
            const lines = []
            const generatedAt = new Date().toLocaleString('de-AT')
            const documentName = this.mainDocument?.original_name || `ABA #${this.abaId}`

            lines.push('AHS-ABA · Kapitelvergleich')
            lines.push(`Erstellt: ${generatedAt}`)
            lines.push(`Dokument: ${documentName}`)

            this.appendLocalChapterComparisonSection(lines)
            this.appendPandocChapterComparisonSection(lines)
            this.appendOnlyDetectedChapterSection(lines)

            return lines.join('\n').trim()
        },
        appendLocalChapterComparisonSection(lines) {
            lines.push('')
            lines.push('Erkannte Kapitel / Abschnitte (lokal)')

            const frontmatter = Array.isArray(this.frontmatterRecords) ? this.frontmatterRecords : []
            const roots = Array.isArray(this.chapterRootRecords) ? this.chapterRootRecords : []
            if (frontmatter.length === 0 && roots.length === 0) {
                lines.push('- Keine Einträge.')
                return
            }

            if (frontmatter.length > 0) {
                lines.push('Sonderbereiche:')
                frontmatter.slice(0, 30).forEach((record) => {
                    const title = this.localRecordDisplayTitle(record)
                    const type = this.localSectionTypeLabel(String(record?.section_type || ''))
                    const page = this.recordPageLabel(record)
                    lines.push(`- ${title} [${type}]${page ? ` | ${page}` : ''}`)
                })
            }

            if (roots.length > 0) {
                lines.push('Kapitelbaum:')
                roots.forEach((record) => {
                    this.appendLocalRecordTreeLines(lines, record, 0)
                })
            }
        },
        appendLocalRecordTreeLines(lines, record, depth) {
            const indent = '  '.repeat(Math.max(0, depth))
            const title = this.localRecordDisplayTitle(record)
            const type = this.localSectionTypeLabel(String(record?.section_type || 'chapter'))
            const page = this.recordPageLabel(record)
            lines.push(`${indent}- ${title} [${type}]${page ? ` | ${page}` : ''}`)

            this.localSortedChildRecords(record?.id).forEach((child) => {
                this.appendLocalRecordTreeLines(lines, child, depth + 1)
            })
        },
        localSortedChildRecords(recordId) {
            const id = Number(recordId || 0)
            if (!id) {
                return []
            }

            const items = Array.isArray(this.childrenByParent[id]) ? [...this.childrenByParent[id]] : []
            return items.sort((left, right) => this.compareRecordsByPage(left, right))
        },
        localRecordDisplayTitle(record) {
            const title = String(record?.section_title || '').trim()
            if (title !== '') {
                return title
            }

            return this.frontmatterPanelLabel(record)
        },
        appendPandocChapterComparisonSection(lines) {
            lines.push('')
            lines.push('Erkannte Kapitel / Abschnitte (Pandoc)')

            const roots = Array.isArray(this.pandocUnifiedStructureRoots) ? this.pandocUnifiedStructureRoots : []

            if (roots.length === 0) {
                lines.push('- Keine Einträge.')
                return
            }

            lines.push('Strukturbaum:')
            roots.forEach((node) => {
                this.appendPandocOutlineNodeLines(lines, node, 0)
            })
        },
        appendPandocOutlineNodeLines(lines, node, depth) {
            const indent = '  '.repeat(Math.max(0, depth))
            const isUiContainer = this.isPandocUiContainerNode(node)
            const confidence = String(node?.confidence || 'low').toUpperCase()
            const strategy = String(node?.strategy || 'heuristic')
            const position = String(node?.position_label || '').trim()
            const usableFlag = node?.is_usable_heading === false ? ' | unsicher' : ''
            const displayText = String(node?.display_text || node?.text || 'Ohne Titel')
            if (isUiContainer) {
                lines.push(`${indent}- ${displayText}`)
            } else {
                const status = this.sectionMatchStatusLabel(this.pandocNodeMatchStatus(node))
                lines.push(`${indent}- ${displayText} [${node?.type_label || 'Kapitel'}] | ${confidence} | ${strategy}${position ? ` | ${position}` : ''} | ${status}${usableFlag}`)
            }

            const detailLines = Array.isArray(node?.detail_lines) ? node.detail_lines : []
            if (!isUiContainer) {
                detailLines.slice(0, 6).forEach((detailLine) => {
                    lines.push(`${indent}  - ${detailLine}`)
                })
                const contentLines = this.pandocNodeContentLines(node, 4)
                if (contentLines.length > 0) {
                    lines.push(`${indent}  - Textinhalt:`)
                    contentLines.forEach((contentLine) => {
                        lines.push(`${indent}    ${contentLine}`)
                    })
                } else {
                    const childSummaryLines = this.pandocNodeChildSummaryLines(node, 4)
                    if (childSummaryLines.length > 0) {
                        lines.push(`${indent}  - Unterabschnitte:`)
                        childSummaryLines.forEach((summaryLine) => {
                            lines.push(`${indent}    ${summaryLine}`)
                        })
                    }
                }
            }

            const children = Array.isArray(node?.children) ? node.children : []
            children.forEach((child) => {
                this.appendPandocOutlineNodeLines(lines, child, depth + 1)
            })
        },
        appendOnlyDetectedChapterSection(lines) {
            const allPandoc = [
                ...(Array.isArray(this.pandocSectionComparisonItems) ? this.pandocSectionComparisonItems : []),
                ...(Array.isArray(this.pandocSpecialSections) ? this.pandocSpecialSections : []),
            ]
            const pandocStatus = (item) => {
                if (this.isSpecialSemanticType(String(item?.semantic_type || ''))) {
                    return this.pandocSpecialMatchStatus(item)
                }

                return this.pandocSectionMatchStatus(item)
            }

            const strongCount = allPandoc.filter((item) => pandocStatus(item) === 'matched_strong').length
            const likelyCount = allPandoc.filter((item) => pandocStatus(item) === 'matched_likely').length
            const structuralCount = allPandoc.filter((item) => pandocStatus(item) === 'matched_structural').length

            const localOnly = this.localSectionComparisonItems
                .filter((item) => this.localSectionMatchStatus(item) === 'local_only')
                .slice(0, 30)
            const pandocOnlyCandidates = [
                ...(Array.isArray(this.pandocSectionComparisonItems) ? this.pandocSectionComparisonItems : []),
                ...(Array.isArray(this.pandocSpecialSections) ? this.pandocSpecialSections : []),
            ]
            const pandocOnly = pandocOnlyCandidates
                .filter((item) => pandocStatus(item) === 'pandoc_only')
                .slice(0, 30)

            if (localOnly.length === 0 && pandocOnly.length === 0) {
                return
            }

            lines.push('')
            lines.push('Nur lokal / nur Pandoc erkannt')
            lines.push(`Sicher gematcht: ${strongCount}`)
            lines.push(`Wahrscheinlich gematcht: ${likelyCount}`)
            lines.push(`Strukturell ähnlich: ${structuralCount}`)
            lines.push(`Nur lokal erkannt: ${localOnly.length}`)
            localOnly.forEach((item) => {
                lines.push(`- ${item.text}`)
            })
            lines.push(`Nur Pandoc erkannt: ${pandocOnly.length}`)
            pandocOnly.forEach((item) => {
                lines.push(`- ${item.text}`)
            })
        },
        buildDocumentReviewCopyText() {
            const lines = []
            const generatedAt = new Date().toLocaleString('de-AT')
            const documentName = this.mainDocument?.original_name || `ABA #${this.abaId}`

            lines.push('AHS-ABA · Dokumentprüfung (Ergebnisansicht)')
            lines.push(`Erstellt: ${generatedAt}`)
            lines.push(`Dokument: ${documentName}`)
            lines.push('Hinweis: Vergleich der Dokumentaufbereitung, keine Benotung der Schülerarbeit.')
            lines.push('')
            lines.push('Kennzahlen')
            lines.push(`- Pflichtzonen gesamt: ${Number(this.documentReviewComparison.summary?.required_zone_count || 0)}`)
            lines.push(`- Pandoc Pflicht erkannt: ${Number(this.documentReviewComparison.summary?.pandoc_required_found || 0)}`)
            lines.push(`- Pandoc Pflicht fehlend: ${Number(this.documentReviewComparison.summary?.pandoc_missing_required_count || 0)}`)
            lines.push(`- Unsichere Erkennung: ${Number(this.documentReviewSummary.uncertain_or_heuristic_count || 0)}`)
            lines.push(`- TOC-Artefakte: ${Number(this.documentReviewSummary.probable_toc_artifact_count || 0)}`)
            lines.push(`- Leere Überschriften: ${Number(this.documentReviewSummary.empty_heading_count || 0)}`)
            lines.push(`- Auffällige Überschriftentexte: ${Number(this.documentReviewSummary.suspicious_heading_count || 0)}`)

            this.appendDocumentReviewPathComparisonSection(lines)
            this.appendDocumentReviewZoneOverviewSection(lines)

            this.appendDocumentReviewHeadingSection(
                lines,
                'Erkannte Hauptabschnitte',
                this.documentReviewMainSections,
                (item) => `- ${item.section_type_label || item.section_type || 'Abschnitt'} | ${String(item.confidence || 'low').toUpperCase()} | ${this.reviewItemText(item)}`
            )
            this.appendDocumentReviewHeadingSection(
                lines,
                'Unsichere Überschriften',
                this.documentReviewUncertainHeadings,
                (item) => `- #${item.order || '?'} | ${String(item.confidence || 'low').toUpperCase()} | ${item.strategy || 'heuristic'} | ${this.reviewItemText(item)}`
            )
            this.appendDocumentReviewHeadingSection(
                lines,
                'Wahrscheinliche TOC-Artefakte',
                this.documentReviewTocArtifacts,
                (item) => `- #${item.order || '?'} | ${this.reviewItemText(item)}`
            )
            this.appendDocumentReviewHeadingSection(
                lines,
                'Leere Überschriften',
                this.documentReviewEmptyHeadings,
                (item) => `- #${item.order || '?'} | ${this.reviewItemText(item)}`
            )
            this.appendDocumentReviewHeadingSection(
                lines,
                'Auffällige Überschriftentexte',
                this.documentReviewSuspiciousHeadings,
                (item) => `- #${item.order || '?'} | ${this.reviewItemText(item)}`
            )
            this.appendDocumentReviewBibliographySection(lines)

            return lines.join('\n').trim()
        },
        appendDocumentReviewHeadingSection(lines, title, items, formatter) {
            lines.push('')
            lines.push(title)

            const collection = Array.isArray(items) ? items : []
            if (collection.length === 0) {
                lines.push('- Keine Einträge.')
                return
            }

            collection.slice(0, 40).forEach((item) => {
                lines.push(formatter(item))
            })
        },
        appendDocumentReviewBibliographySection(lines) {
            lines.push('')
            lines.push('Quellen-/Verzeichnisbereich')

            if (!Array.isArray(this.documentReviewBibliographyGroups) || this.documentReviewBibliographyGroups.length === 0) {
                lines.push('- Keine gruppierten Bereiche erkannt.')
                return
            }

            this.documentReviewBibliographyGroups.forEach((group) => {
                lines.push(`- ${group.group_label || group.group_key || 'Bereich'}`)
                const subtypes = Array.isArray(group.subtypes) ? group.subtypes : []
                if (subtypes.length === 0) {
                    lines.push('  - Keine Untertypen erkannt.')
                    return
                }

                subtypes.slice(0, 20).forEach((subtype) => {
                    lines.push(`  - ${subtype.subtype_label || subtype.subtype_key || 'Untertyp'}: ${Number(subtype.count || 0)}`)
                })
            })
        },
        appendDocumentReviewZoneOverviewSection(lines) {
            lines.push('')
            lines.push('Dokumentzonen / Hauptphasen')

            if (!Array.isArray(this.documentReviewZoneOverview) || this.documentReviewZoneOverview.length === 0) {
                lines.push('- Keine Zonen erkannt.')
                return
            }

            this.documentReviewZoneOverview.forEach((zone) => {
                lines.push(`- ${zone.zone_label || zone.zone_key || 'Zone'}: ${Number(zone.count || 0)} Blöcke, ${Number(zone.heading_count || 0)} Überschriften`)
            })
        },
        appendDocumentReviewPathComparisonSection(lines) {
            lines.push('')
            lines.push('Pfadvergleich (lokal vs. Pandoc)')
            lines.push(`- Lokal: ${this.comparePathStatusLabel(this.documentReviewLegacyPath)}`)
            lines.push(`- Pandoc: ${this.comparePathStatusLabel(this.documentReviewPandocPath)}`)
            lines.push(`- Fehlende Pflichtbestandteile (Lokal): ${this.documentReviewLegacyMissingRequiredLabel}`)
            lines.push(`- Fehlende Pflichtbestandteile (Pandoc): ${this.documentReviewPandocMissingRequiredLabel}`)

            if (!Array.isArray(this.documentReviewComparisonZones) || this.documentReviewComparisonZones.length === 0) {
                lines.push('- Keine Zonenmatrix verfügbar.')
                return
            }

            this.documentReviewComparisonZones.slice(0, 30).forEach((zone) => {
                lines.push(`- ${zone.label || zone.zone_key || 'Zone'} [${this.requirementLabel(zone.requirement)}] -> Lokal: ${this.compareBoolLabel(Boolean(zone.legacy_local))}, Pandoc: ${this.compareBoolLabel(Boolean(zone.pandoc))}`)
            })
        },
        toggleCard(key) {
            this.visibleCards[key] = !this.visibleCards[key]
        },
        soloCard(key) {
            for (const k of Object.keys(this.visibleCards)) {
                this.visibleCards[k] = k === key
            }
        },
        requestReanalysis() {
            if (this.abaId <= 0 || this.controlsLocked) {
                return
            }
            this.reanalysisConfirmDialogOpen = true
        },
        async confirmReanalysis() {
            this.reanalysisConfirmDialogOpen = false
            await this.restartAnalysis()
        },
        async restartAnalysis() {
            if (this.abaId <= 0 || this.controlsLocked) {
                return
            }

            this.reanalysisStarting = true
            this.error = ''

            try {
                await axios.post(`/api/admin/abas/${this.abaId}/analysis`)
                await this.loadResults(true)
            } catch (error) {
                this.error = error?.response?.data?.message || 'Erneute Analyse konnte nicht gestartet werden.'
            } finally {
                this.reanalysisStarting = false
            }
        },
        startPolling() {
            this.stopPolling()
            this.pollTimer = window.setInterval(() => {
                this.pollResults()
            }, 4000)
        },
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer)
                this.pollTimer = null
            }
        },
        async pollResults() {
            if (!this.pendingRun || this.pollInFlight) {
                return
            }

            this.pollInFlight = true
            try {
                await this.loadResults(true)
            } finally {
                this.pollInFlight = false
            }
        },
        goBack() {
            this.$router.push('/admin/aba')
        },
        formatDateTime(value) {
            if (!value) {
                return '-'
            }

            const date = new Date(value)
            if (Number.isNaN(date.getTime())) {
                return String(value)
            }

            return date.toLocaleString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            })
        },
        formatScore(value) {
            const numeric = Number(value)
            if (!Number.isFinite(numeric)) {
                return '-'
            }

            return numeric.toFixed(4)
        },
        parseTimestamp(value) {
            if (!value) {
                return null
            }

            const parsed = new Date(value)
            if (Number.isNaN(parsed.getTime())) {
                return null
            }

            return parsed
        },
        formatDuration(totalSeconds) {
            const seconds = Number(totalSeconds)
            if (!Number.isFinite(seconds) || seconds < 0) {
                return '-'
            }

            const days = Math.floor(seconds / 86400)
            const hours = Math.floor((seconds % 86400) / 3600)
            const minutes = Math.floor((seconds % 3600) / 60)
            const secs = seconds % 60

            const parts = []
            if (days > 0) {
                parts.push(`${days}d`)
            }
            if (hours > 0 || days > 0) {
                parts.push(`${hours}h`)
            }
            parts.push(`${minutes}m`)
            parts.push(`${secs}s`)

            return parts.join(' ')
        },
        formatInteger(value) {
            const number = Number(value)
            if (!Number.isFinite(number)) {
                return '-'
            }

            return new Intl.NumberFormat('de-AT').format(Math.trunc(number))
        },
        formatPercent(value) {
            const numeric = Number(value)
            if (!Number.isFinite(numeric)) {
                return '-'
            }

            return `${(numeric * 100).toFixed(1)} %`
        },
        makeStatusItem(key, label, detected, startLine = null, endLine = null, optional = false) {
            return {
                key,
                label,
                detected: Boolean(detected),
                startLine,
                endLine,
                optional: Boolean(optional),
            }
        },
        statusColor(item) {
            if (item?.detected) {
                return 'success'
            }

            if (item?.optional) {
                return 'info'
            }

            return 'error'
        },
        statusLabel(item) {
            if (item?.detected) {
                return 'Erkannt'
            }

            if (item?.optional) {
                return 'Optional nicht erkannt'
            }

            return 'Nicht erkannt'
        },
        isFrontmatterType(type) {
            return [
                'title_page',
                'abstract',
                'foreword',
                'table_of_contents',
                'bibliography',
                'figure_index',
                'consent_declaration',
            ].includes(String(type || ''))
        },
        hasChapterAncestor(record) {
            const seen = new Set()
            let parentId = Number(record?.parent_result_id || 0)

            while (parentId > 0) {
                if (seen.has(parentId)) {
                    return false
                }
                seen.add(parentId)

                const parent = this.recordsById[parentId]
                if (!parent) {
                    return false
                }

                if (String(parent.section_type || '') === 'chapter') {
                    return true
                }

                parentId = Number(parent.parent_result_id || 0)
            }

            return false
        },
        localRecordAncestorChain(recordId, includeSelf = false) {
            const chain = []
            const seen = new Set()
            let currentId = Number(recordId || 0)
            if (!includeSelf && currentId > 0) {
                const currentRecord = this.recordsById[currentId] || null
                currentId = Number(currentRecord?.parent_result_id || 0)
            }

            while (currentId > 0) {
                if (seen.has(currentId)) {
                    break
                }
                seen.add(currentId)

                const record = this.recordsById[currentId]
                if (!record || typeof record !== 'object') {
                    break
                }

                chain.push(record)
                currentId = Number(record?.parent_result_id || 0)
            }

            return chain
        },
        chapterDescendantCount(chapterId) {
            const stack = [...(Array.isArray(this.childrenByParent[chapterId]) ? this.childrenByParent[chapterId] : [])]
            let count = 0

            while (stack.length > 0) {
                const current = stack.pop()
                if (!current) {
                    continue
                }

                count += 1
                const currentId = Number(current.id || 0)
                if (!currentId) {
                    continue
                }

                const children = Array.isArray(this.childrenByParent[currentId]) ? this.childrenByParent[currentId] : []
                children.forEach((child) => stack.push(child))
            }

            return count
        },
        compareRecordsByPage(left, right) {
            const leftPageRaw = Number(left?.start_page ?? 0)
            const rightPageRaw = Number(right?.start_page ?? 0)
            const leftPage = leftPageRaw > 0 ? leftPageRaw : Number.MAX_SAFE_INTEGER
            const rightPage = rightPageRaw > 0 ? rightPageRaw : Number.MAX_SAFE_INTEGER

            if (leftPage !== rightPage) {
                return leftPage - rightPage
            }

            const leftLineRaw = Number(left?.start_line ?? 0)
            const rightLineRaw = Number(right?.start_line ?? 0)
            const leftLine = leftLineRaw > 0 ? leftLineRaw : Number.MAX_SAFE_INTEGER
            const rightLine = rightLineRaw > 0 ? rightLineRaw : Number.MAX_SAFE_INTEGER

            if (leftLine !== rightLine) {
                return leftLine - rightLine
            }

            const leftOrder = Number(left?.sort_order ?? 0)
            const rightOrder = Number(right?.sort_order ?? 0)
            if (leftOrder !== rightOrder) {
                return leftOrder - rightOrder
            }

            return Number(left?.id ?? 0) - Number(right?.id ?? 0)
        },
        recordPageLabel(record) {
            const startPage = Number(record?.start_page ?? 0)
            const endPage = Number(record?.end_page ?? 0)

            if (startPage > 0 && endPage > startPage) {
                return `Seiten ${startPage}–${endPage}`
            }
            if (startPage > 0) {
                return `Seite ${startPage}`
            }

            return null
        },
        contentPanelPageLabel() {
            const pages = []
            this.chapterRootRecords.forEach((record) => {
                const startPage = Number(record?.start_page ?? 0)
                const endPage = Number(record?.end_page ?? 0)
                if (startPage > 0) {
                    pages.push(startPage)
                }
                if (endPage > 0) {
                    pages.push(endPage)
                }
            })

            if (pages.length === 0) {
                return null
            }

            const start = Math.min(...pages)
            const end = Math.max(...pages)
            if (end > start) {
                return `Seiten ${start}–${end}`
            }

            return `Seite ${start}`
        },
        panelPageLabel(panel) {
            if (panel?.kind === 'content') {
                return this.contentPanelPageLabel()
            }

            return this.recordPageLabel(panel?.record || null)
        },
        panelSortReference(panel) {
            if (panel?.kind === 'content') {
                return panel.anchor || {}
            }

            return panel?.record || {}
        },
        frontmatterPanelLabel(record) {
            const title = String(record?.section_title || '').trim()
            if (title !== '') {
                return title
            }

            const type = String(record?.section_type || '')
            if (type === 'title_page') return 'Titelseite'
            if (type === 'abstract') return 'Abstract'
            if (type === 'table_of_contents') return 'Inhaltsverzeichnis'
            if (type === 'bibliography') return 'Literaturverzeichnis'
            if (type === 'figure_index') return 'Abbildungsverzeichnis'
            if (type === 'consent_declaration') return 'Eigenständigkeitserklärung'
            if (type === 'foreword') return 'Vorwort'

            return 'Datensatz'
        },
        localSectionTypeLabel(sectionType) {
            if (sectionType === 'title_page') {
                return 'Titelblatt'
            }
            if (sectionType === 'abstract') {
                return 'Abstract'
            }
            if (sectionType === 'table_of_contents') {
                return 'Inhaltsverzeichnis'
            }
            if (sectionType === 'bibliography') {
                return 'Literaturverzeichnis'
            }
            if (sectionType === 'figure_index') {
                return 'Abbildungsverzeichnis'
            }
            if (sectionType === 'consent_declaration') {
                return 'Eigenständigkeitserklärung'
            }
            if (sectionType === 'foreword') {
                return 'Vorwort'
            }
            if (sectionType === 'chapter') {
                return 'Kapitel'
            }
            if (sectionType === 'subchapter') {
                return 'Unterkapitel'
            }
            if (sectionType === 'figure') {
                return 'Abbildung'
            }

            return sectionType || 'Abschnitt'
        },
        resolveLocalZoneContext(sectionType) {
            const type = String(sectionType || '').trim().toLowerCase()
            if (type === 'chapter' || type === 'subchapter' || type === 'figure') {
                return 'main_content'
            }
            if (['bibliography', 'figure_index', 'consent_declaration'].includes(type)) {
                return type === 'consent_declaration' ? 'declaration_area' : 'end_matter'
            }
            if (['title_page', 'abstract', 'foreword', 'table_of_contents'].includes(type)) {
                return type === 'title_page' ? 'title_page' : (type === 'table_of_contents' ? 'table_of_contents' : 'front_matter')
            }

            return ''
        },
        sectionCompareKey(text) {
            const raw = String(text || '').trim().toLowerCase()
            if (raw === '') {
                return ''
            }

            const normalized = raw
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/gu, '')
                .replace(/[“”„«»‚‘’`´]/gu, '\'')
                .replace(/[‐‑‒–—―]/gu, '-')
                .replace(/ä/gu, 'ae')
                .replace(/ö/gu, 'oe')
                .replace(/ü/gu, 'ue')
                .replace(/ß/gu, 'ss')

            const withoutLeadingNumbering = normalized.replace(/^\s*\d+(?:\.\d+){0,8}(?:\.(?=\p{L})|[.):\s])\s*/u, '')
            const withoutTrailingPage = withoutLeadingNumbering.replace(/\s+[-–—]?\s*\d{1,4}\s*$/u, '')
            const withoutPunctuation = withoutTrailingPage.replace(/[^\p{L}\p{N}\s]/gu, ' ')

            return withoutPunctuation.replace(/\s+/gu, ' ').trim()
        },
        figureCompareKey(text, fallbackText = '') {
            const source = `${String(text || '')} ${String(fallbackText || '')}`.trim()
            const figureNumber = this.extractFigureNumber(source)
            if (figureNumber !== '') {
                return this.sectionCompareKey(`Abbildung ${figureNumber}`)
            }

            return this.sectionCompareKey(String(text || ''))
        },
        extractSectionNumbering(text) {
            const value = String(text || '').trim()
            if (value === '') {
                return ''
            }

            const match = value.match(/^(\d+(?:\.\d+){0,8})(?:\.(?=\p{L})|[.):\s]|$)/u)
            if (!match) {
                return ''
            }

            return String(match?.[1] || '').replace(/\.+$/u, '').trim()
        },
        extractFigureNumber(text) {
            const value = String(text || '').trim()
            if (value === '') {
                return ''
            }

            const match = value.match(/\b(?:abbildung|figure)\s*([0-9]{1,4})\b/iu)
            if (!match) {
                return ''
            }

            return String(match?.[1] || '').trim()
        },
        resolveComparableNumbering(text, itemType = '') {
            const chapterNumbering = this.extractSectionNumbering(text)
            if (chapterNumbering !== '') {
                return chapterNumbering
            }

            if (String(itemType || '').trim().toLowerCase() !== 'figure') {
                return ''
            }

            const figureNumber = this.extractFigureNumber(text)
            if (figureNumber === '') {
                return ''
            }

            return `fig-${figureNumber}`
        },
        buildLocalFigurePlacementIndex() {
            const placementsByFigureNumber = {}
            const localItems = Array.isArray(this.localSectionComparisonItems) ? this.localSectionComparisonItems : []

            localItems.forEach((item) => {
                const type = String(item?.type || '').trim().toLowerCase()
                const semanticType = String(item?.semantic_type || '').trim().toLowerCase()
                if (type !== 'figure' && semanticType !== 'figure') {
                    return
                }

                const figureText = String(item?.text || '').trim()
                const caption = String(item?.caption || '').trim()
                const figureNumber = this.extractFigureNumber(`${figureText} ${caption}`.trim())
                if (figureNumber === '') {
                    return
                }

                let parentText = String(item?.parent_text || '').trim()
                let parentCompareKey = String(item?.parent_compare_key || '').trim()
                let parentNumbering = String(item?.parent_numbering || '').trim()
                let parentSemanticType = ''
                let chapterText = ''
                let chapterCompareKey = ''
                let chapterNumbering = ''

                const sourceId = Number(item?.source_id || 0)
                if (sourceId > 0) {
                    const ancestorChain = this.localRecordAncestorChain(sourceId)
                    const nearestStructuredAncestor = ancestorChain.find((record) => {
                        const sectionType = String(record?.section_type || '').trim().toLowerCase()
                        return sectionType === 'chapter' || sectionType === 'subchapter'
                    }) || null
                    if (nearestStructuredAncestor) {
                        parentText = String(this.localRecordDisplayTitle(nearestStructuredAncestor) || '').trim()
                        parentCompareKey = this.sectionCompareKey(parentText)
                        parentNumbering = this.extractSectionNumbering(parentText)
                        parentSemanticType = this.normalizeSectionSemanticType(
                            String(nearestStructuredAncestor?.section_type || ''),
                            this.resolveLocalZoneContext(String(nearestStructuredAncestor?.section_type || ''))
                        )
                    }

                    const chapterAncestor = [...ancestorChain].reverse().find((record) => {
                        const sectionType = String(record?.section_type || '').trim().toLowerCase()
                        return sectionType === 'chapter'
                    }) || null
                    if (chapterAncestor) {
                        chapterText = String(this.localRecordDisplayTitle(chapterAncestor) || '').trim()
                        chapterCompareKey = this.sectionCompareKey(chapterText)
                        chapterNumbering = this.extractSectionNumbering(chapterText)
                    }
                }

                if (parentCompareKey === '' && parentText !== '') {
                    parentCompareKey = this.sectionCompareKey(parentText)
                }
                if (parentNumbering === '' && parentText !== '') {
                    parentNumbering = this.extractSectionNumbering(parentText)
                }

                if (chapterCompareKey === '' && parentCompareKey !== '') {
                    chapterText = parentText
                    chapterCompareKey = parentCompareKey
                    chapterNumbering = parentNumbering
                }

                const localOrder = Number(item?.sort_order || item?.order || 0)
                const placement = {
                    figure_number: figureNumber,
                    figure_text: figureText,
                    local_order: Number.isFinite(localOrder) ? localOrder : 0,
                    parent_text: parentText,
                    parent_compare_key: parentCompareKey,
                    parent_numbering: parentNumbering,
                    parent_semantic_type: parentSemanticType,
                    chapter_text: chapterText,
                    chapter_compare_key: chapterCompareKey,
                    chapter_numbering: chapterNumbering,
                }

                if (!Array.isArray(placementsByFigureNumber[figureNumber])) {
                    placementsByFigureNumber[figureNumber] = []
                }
                placementsByFigureNumber[figureNumber].push(placement)
            })

            Object.keys(placementsByFigureNumber).forEach((figureNumber) => {
                placementsByFigureNumber[figureNumber] = placementsByFigureNumber[figureNumber]
                    .sort((left, right) => Number(left.local_order || 0) - Number(right.local_order || 0))
                    .slice(0, 8)
            })

            return placementsByFigureNumber
        },
        collectPandocMainSectionCandidates(mainRoots) {
            const candidates = []

            const append = (nodes, ancestry) => {
                if (!Array.isArray(nodes) || nodes.length === 0) {
                    return
                }

                nodes.forEach((node) => {
                    if (!node || typeof node !== 'object') {
                        return
                    }

                    const semanticType = String(node?.semantic_type || '').trim().toLowerCase()
                    const type = String(node?.type || '').trim().toLowerCase()
                    const isStructuredSection = semanticType === 'chapter'
                        || semanticType === 'subchapter'
                        || type === 'chapter'
                        || type === 'subchapter'
                    if (isStructuredSection) {
                        const chapterAncestor = [...ancestry].reverse().find((ancestor) => {
                            const ancestorSemantic = String(ancestor?.semantic_type || '').trim().toLowerCase()
                            const ancestorType = String(ancestor?.type || '').trim().toLowerCase()
                            return ancestorSemantic === 'chapter' || ancestorType === 'chapter'
                        }) || (semanticType === 'chapter' || type === 'chapter' ? node : null)

                        candidates.push({
                            node,
                            compare_key: String(node?.compare_key || ''),
                            numbering: String(node?.numbering || ''),
                            semantic_type: semanticType || type,
                            order: Number(node?.order || 0),
                            chapter_compare_key: chapterAncestor ? String(chapterAncestor?.compare_key || '') : '',
                            chapter_numbering: chapterAncestor ? String(chapterAncestor?.numbering || '') : '',
                        })
                    }

                    append(node.children, [...ancestry, node])
                })
            }

            append(Array.isArray(mainRoots) ? mainRoots : [], [])

            return candidates
        },
        attachFigureEntryToPandocMainTree(mainRoots, figureEntry, localFigurePlacementIndex = {}) {
            if (!figureEntry || typeof figureEntry !== 'object') {
                return false
            }

            const figureText = String(figureEntry?.text || '').trim()
            const caption = String(figureEntry?.caption || '').trim()
            const figureNumber = this.extractFigureNumber(`${figureText} ${caption}`.trim())
            if (figureNumber === '') {
                return false
            }

            const placements = Array.isArray(localFigurePlacementIndex?.[figureNumber])
                ? localFigurePlacementIndex[figureNumber]
                : []
            if (placements.length === 0) {
                return false
            }

            const sectionCandidates = this.collectPandocMainSectionCandidates(mainRoots)
            if (sectionCandidates.length === 0) {
                return false
            }

            let bestCandidate = null
            let secondCandidate = null

            placements.forEach((placement) => {
                sectionCandidates.forEach((candidate) => {
                    let score = 0
                    let conflictingNumbering = false

                    const parentNumbering = String(placement?.parent_numbering || '')
                    const chapterNumbering = String(placement?.chapter_numbering || '')
                    const candidateNumbering = String(candidate?.numbering || '')
                    const candidateChapterNumbering = String(candidate?.chapter_numbering || '')

                    if (parentNumbering !== '' && candidateNumbering !== '') {
                        if (parentNumbering === candidateNumbering) {
                            score += 0.62
                        } else {
                            const leftFirst = this.firstNumberSegment(parentNumbering)
                            const rightFirst = this.firstNumberSegment(candidateNumbering)
                            if (leftFirst !== '' && rightFirst !== '' && leftFirst !== rightFirst) {
                                conflictingNumbering = true
                            } else {
                                score -= 0.12
                            }
                        }
                    }
                    if (chapterNumbering !== '' && candidateChapterNumbering !== '') {
                        if (chapterNumbering === candidateChapterNumbering) {
                            score += 0.36
                        } else {
                            const leftFirst = this.firstNumberSegment(chapterNumbering)
                            const rightFirst = this.firstNumberSegment(candidateChapterNumbering)
                            if (leftFirst !== '' && rightFirst !== '' && leftFirst !== rightFirst) {
                                conflictingNumbering = true
                            }
                        }
                    }

                    if (conflictingNumbering) {
                        return
                    }

                    const parentCompareKey = String(placement?.parent_compare_key || '')
                    const chapterCompareKey = String(placement?.chapter_compare_key || '')
                    const candidateCompareKey = String(candidate?.compare_key || '')
                    const candidateChapterCompareKey = String(candidate?.chapter_compare_key || '')
                    const parentSemanticType = String(placement?.parent_semantic_type || '').trim().toLowerCase()
                    const candidateSemanticType = String(candidate?.semantic_type || '').trim().toLowerCase()

                    if (parentCompareKey !== '' && candidateCompareKey !== '') {
                        if (parentCompareKey === candidateCompareKey) {
                            score += 0.34
                        } else {
                            score += this.tokenSimilarity(parentCompareKey, candidateCompareKey) * 0.22
                        }
                    }
                    if (chapterCompareKey !== '' && candidateChapterCompareKey !== '') {
                        if (chapterCompareKey === candidateChapterCompareKey) {
                            score += 0.26
                        } else {
                            score += this.tokenSimilarity(chapterCompareKey, candidateChapterCompareKey) * 0.16
                        }
                    }
                    if (
                        parentCompareKey !== ''
                        && chapterCompareKey !== ''
                        && candidateCompareKey !== ''
                        && candidateChapterCompareKey !== ''
                        && parentCompareKey === candidateCompareKey
                        && chapterCompareKey === candidateChapterCompareKey
                    ) {
                        score += 0.12
                    }
                    if (parentSemanticType !== '' && candidateSemanticType !== '' && parentSemanticType === candidateSemanticType) {
                        score += 0.1
                    }

                    const localOrder = Number(placement?.local_order || 0)
                    const pandocOrder = Number(candidate?.order || 0)
                    if (localOrder > 0 && pandocOrder > 0) {
                        const orderDiff = Math.abs(localOrder - pandocOrder)
                        if (orderDiff <= 20) {
                            score += 0.08
                        } else if (orderDiff <= 80) {
                            score += 0.04
                        } else if (orderDiff > 220) {
                            score -= 0.06
                        }
                    }

                    if (!Number.isFinite(score) || score <= 0) {
                        return
                    }

                    const scoredCandidate = {
                        score: Math.max(0, Math.min(1, score)),
                        candidate,
                    }
                    if (bestCandidate === null || scoredCandidate.score > bestCandidate.score) {
                        secondCandidate = bestCandidate
                        bestCandidate = scoredCandidate
                        return
                    }
                    if (secondCandidate === null || scoredCandidate.score > secondCandidate.score) {
                        secondCandidate = scoredCandidate
                    }
                })
            })

            if (bestCandidate === null) {
                return false
            }

            if (bestCandidate.score < 0.66) {
                return false
            }

            if (
                secondCandidate !== null
                && secondCandidate.score > 0
                && (bestCandidate.score - secondCandidate.score) < 0.08
            ) {
                return false
            }

            const targetNode = bestCandidate.candidate?.node
            if (!targetNode || typeof targetNode !== 'object') {
                return false
            }

            const existingChildren = Array.isArray(targetNode.children) ? targetNode.children : []
            const normalizedFigureKey = this.sectionCompareKey(figureText)
            const duplicateExists = existingChildren.some((child) => {
                const childType = String(child?.type || '').trim().toLowerCase()
                if (childType !== 'figure') {
                    return false
                }

                const childNumber = this.extractFigureNumber(String(child?.text || '').trim())
                const childKey = String(child?.compare_key || this.sectionCompareKey(child?.text || ''))
                return (childNumber !== '' && childNumber === figureNumber)
                    || (normalizedFigureKey !== '' && childKey === normalizedFigureKey)
            })
            if (duplicateExists) {
                return true
            }

            const targetHeadingLevel = Number(targetNode?.heading_level || targetNode?.outline_level || 1)
            const targetStructureLevel = Number(targetNode?.structure_level || 1)
            const attachedFigureEntry = {
                ...figureEntry,
                id: `${String(figureEntry?.id || `pandoc-figure-${figureNumber}`)}-attached-${String(targetNode?.id || 'root')}`,
                parent_text: String(targetNode?.text || ''),
                parent_compare_key: String(targetNode?.compare_key || ''),
                parent_numbering: String(targetNode?.numbering || ''),
                heading_level: Math.max(2, targetHeadingLevel + 1),
                outline_level: Math.max(2, targetHeadingLevel + 1),
                structure_level: Math.max(2, targetStructureLevel + 1),
                semantic_type: 'figure',
                zone_context: 'main_content',
                root_kind: 'figure_attached',
                detail_lines: [
                    `Zuordnung: lokaler Figure-Anker Abbildung ${figureNumber}`,
                ],
                children: Array.isArray(figureEntry?.children) ? figureEntry.children : [],
            }

            targetNode.children = [...existingChildren, attachedFigureEntry]
            this.sortPandocNodeTreeByOrder(targetNode.children)

            return true
        },
        sortPandocNodeTreeByOrder(nodes) {
            if (!Array.isArray(nodes) || nodes.length === 0) {
                return
            }

            nodes.sort((left, right) => {
                const leftOrder = Number(left?.order || 0)
                const rightOrder = Number(right?.order || 0)
                if (leftOrder !== rightOrder) {
                    return leftOrder - rightOrder
                }

                const leftLevel = Number(left?.structure_level || 0)
                const rightLevel = Number(right?.structure_level || 0)
                if (leftLevel !== rightLevel) {
                    return leftLevel - rightLevel
                }

                const leftKey = String(left?.compare_key || left?.text || '')
                const rightKey = String(right?.compare_key || right?.text || '')
                return leftKey.localeCompare(rightKey, 'de')
            })

            nodes.forEach((node) => {
                if (Array.isArray(node?.children) && node.children.length > 0) {
                    this.sortPandocNodeTreeByOrder(node.children)
                }
            })
        },
        buildPandocContentContainerRoot(mainRoots) {
            if (!Array.isArray(mainRoots) || mainRoots.length === 0) {
                return null
            }

            const rootItems = mainRoots.filter((item) => item && typeof item === 'object')
            if (rootItems.length === 0) {
                return null
            }

            const orders = rootItems
                .map((item) => Number(item?.order || 0))
                .filter((value) => Number.isFinite(value) && value > 0)
            const firstOrder = orders.length > 0 ? Math.min(...orders) : 0

            return {
                id: 'pandoc-content-root',
                source: 'pandoc',
                root_kind: 'content',
                area: 'content',
                area_label: 'Inhalt',
                text: 'Inhalt',
                display_text: 'Inhalt',
                type: 'ui_container',
                type_label: 'Container',
                confidence: '',
                strategy: '',
                compare_key: '',
                semantic_type: 'ui_container',
                semantic_compare_key: '',
                zone_context: 'main_content',
                parent_text: '',
                parent_compare_key: '',
                parent_numbering: '',
                numbering: '',
                numbering_depth: 0,
                structure_level: 0,
                order: firstOrder > 0 ? firstOrder : 500000,
                position_label: '',
                problem_tags: [],
                detail_lines: [],
                content_text: null,
                content_excerpt: null,
                content_preview_lines: [],
                content_line_count: 0,
                heading_level: 1,
                outline_level: 1,
                depth: 0,
                children: rootItems,
            }
        },
        pandocUnifiedTopLevelRank(node) {
            const rootKind = String(node?.root_kind || '').trim().toLowerCase()
            if (rootKind === 'content') return 50

            const semantic = String(node?.semantic_type || node?.area || node?.type || '').trim().toLowerCase()
            if (semantic === 'titlepage') return 10
            if (semantic === 'abstract') return 20
            if (semantic === 'foreword') return 30
            if (semantic === 'toc') return 40
            if (semantic === 'bibliography') return 60
            if (semantic === 'figure_index') return 70
            if (semantic === 'declaration') return 80
            if (semantic === 'appendix') return 90
            if (semantic === 'frontmatter') return 35
            if (semantic === 'endmatter') return 85

            return 500
        },
        numberingDepth(numbering) {
            const value = String(numbering || '').trim()
            if (value === '') {
                return 0
            }

            return value
                .split('.')
                .map((part) => String(part || '').trim())
                .filter((part) => part !== '').length
        },
        resolveStructureLevel({ numberingDepth = 0, fallbackLevel = 0, semanticType = '', itemType = '' }) {
            const semantic = String(semanticType || '').trim().toLowerCase()
            const type = String(itemType || '').trim().toLowerCase()
            const numbering = Number(numberingDepth || 0)
            if (numbering > 0) {
                return numbering
            }

            const fallback = Number(fallbackLevel || 0)
            if (Number.isFinite(fallback) && fallback > 0) {
                return Math.round(fallback)
            }

            if (semantic === 'figure' || type === 'figure') {
                return 4
            }
            if (semantic === 'subchapter' || type === 'subchapter') {
                return 2
            }

            return 1
        },
        normalizeSectionSemanticType(type, area = '') {
            const value = String(type || '').trim().toLowerCase()
            const zone = String(area || '').trim().toLowerCase()

            if (['title_page', 'titlepage'].includes(value) || zone === 'title_page') {
                return 'titlepage'
            }
            if (value === 'abstract') {
                return 'abstract'
            }
            if (value === 'foreword') {
                return 'foreword'
            }
            if (['table_of_contents', 'toc'].includes(value) || zone === 'table_of_contents') {
                return 'toc'
            }
            if (value === 'figure') {
                return 'figure'
            }
            if (value === 'figure_index') {
                return 'figure_index'
            }
            if (value === 'bibliography' || zone === 'bibliography_area') {
                return 'bibliography'
            }
            if (value === 'consent_declaration' || zone === 'declaration_area') {
                return 'declaration'
            }
            if (zone === 'appendix_area' || value === 'appendix') {
                return 'appendix'
            }
            if (value === 'subchapter') {
                return 'subchapter'
            }
            if (value === 'chapter' || zone === 'main_content' || zone === 'chapter') {
                return 'chapter'
            }
            if (zone === 'front_matter') {
                return 'frontmatter'
            }
            if (zone === 'end_matter') {
                return 'endmatter'
            }

            return value || zone || ''
        },
        sectionAreaLabel(semanticType) {
            const key = String(semanticType || '')
            if (key === 'titlepage') return 'Titelseite / Titelblatt'
            if (key === 'abstract') return 'Abstract'
            if (key === 'foreword') return 'Vorwort'
            if (key === 'toc') return 'Inhaltsverzeichnis'
            if (key === 'bibliography') return 'Literatur-/Quellenverzeichnis'
            if (key === 'figure_index') return 'Abbildungsverzeichnis'
            if (key === 'declaration') return 'Eigenständigkeitserklärung'
            if (key === 'appendix') return 'Anhang'
            if (key === 'frontmatter') return 'Frontmatter'
            if (key === 'endmatter') return 'Endbereich'

            return 'Sonderbereich'
        },
        ensurePandocAbstractSpecialSection(sections) {
            const items = Array.isArray(sections) ? [...sections] : []
            const hasAbstract = items.some((item) => {
                const area = String(item?.area || item?.semantic_type || '').trim().toLowerCase()
                return area === 'abstract'
            })
            if (hasAbstract) {
                return items
            }

            if (!this.pandocAbstractDetectedByPath()) {
                return items
            }

            const fallback = this.buildPandocAbstractFallbackSection()
            if (!fallback) {
                return items
            }

            const merged = [...items, fallback]

            return merged
                .filter((item) => String(item?.text || '').trim() !== '')
                .sort((left, right) => {
                    const leftRank = this.pandocUnifiedTopLevelRank(left)
                    const rightRank = this.pandocUnifiedTopLevelRank(right)
                    if (leftRank !== rightRank) {
                        return leftRank - rightRank
                    }

                    const leftOrder = Number(left?.order || 0)
                    const rightOrder = Number(right?.order || 0)
                    if (leftOrder !== rightOrder) {
                        return leftOrder - rightOrder
                    }

                    const leftKey = String(left?.compare_key || left?.text || '')
                    const rightKey = String(right?.compare_key || right?.text || '')
                    return leftKey.localeCompare(rightKey, 'de')
                })
                .slice(0, 60)
        },
        pandocAbstractDetectedByPath() {
            const path = this.documentReviewPandocPath && typeof this.documentReviewPandocPath === 'object'
                ? this.documentReviewPandocPath
                : {}
            const zoneFlags = path?.zone_flags && typeof path.zone_flags === 'object'
                ? path.zone_flags
                : {}

            return Boolean(
                path?.abstract_found
                || zoneFlags?.abstract
                || zoneFlags?.abstract_de
                || zoneFlags?.abstract_en
            )
        },
        buildPandocAbstractFallbackSection() {
            const blocks = this.documentReview?.normalization?.blocks
            const normalizedBlocks = Array.isArray(blocks)
                ? blocks.filter((block) => block && typeof block === 'object')
                : []
            if (normalizedBlocks.length === 0) {
                return null
            }

            const sortedBlocks = [...normalizedBlocks].sort((left, right) => Number(left?.order || 0) - Number(right?.order || 0))
            const candidates = sortedBlocks
                .filter((block) => String(block?.type || '').trim().toLowerCase() === 'heading')
                .filter((block) => String(block?.section_hint?.section_type || '').trim().toLowerCase() === 'abstract')
                .map((block) => ({
                    block,
                    score: this.scorePandocAbstractFallbackCandidate(block),
                }))
                .sort((left, right) => {
                    if (left.score !== right.score) {
                        return right.score - left.score
                    }
                    return Number(left.block?.order || 0) - Number(right.block?.order || 0)
                })

            if (candidates.length === 0) {
                return null
            }

            const selected = candidates[0].block
            const startOrder = Number(selected?.order || 0)
            const sectionType = String(selected?.section_hint?.section_type || '').trim().toLowerCase()
            if (sectionType !== 'abstract') {
                return null
            }

            let endOrder = null
            for (const block of sortedBlocks) {
                const order = Number(block?.order || 0)
                if (!Number.isFinite(order) || order <= startOrder) {
                    continue
                }
                if (String(block?.type || '').trim().toLowerCase() === 'heading') {
                    endOrder = order
                    break
                }
            }

            const contentLines = []
            for (const block of sortedBlocks) {
                const order = Number(block?.order || 0)
                if (!Number.isFinite(order) || order <= startOrder) {
                    continue
                }
                if (endOrder !== null && order >= endOrder) {
                    break
                }

                const blockType = String(block?.type || '').trim().toLowerCase()
                if (blockType === 'heading') {
                    continue
                }

                const text = this.extractPandocNormalizationBlockText(block)
                if (text === '') {
                    continue
                }
                if (this.isLikelyTocArtifactProjectionLine(text)) {
                    continue
                }

                contentLines.push(text)
                if (contentLines.length >= 48) {
                    break
                }
            }

            const contentText = contentLines.length > 0 ? contentLines.join('\n') : null
            const contentExcerpt = contentText ? String(contentText).replace(/\s+/gu, ' ').trim().slice(0, 1600) : null
            const previewLines = contentLines.length > 0
                ? this.normalizeProjectionLines(contentLines, true).slice(0, 24)
                : []
            const confidence = String(selected?.classification?.confidence || selected?.confidence || 'medium')
            const strategy = String(selected?.classification?.strategy || selected?.strategy || 'heuristic')
            const zoneContext = String(selected?.document_zone?.zone || selected?.document_zone || 'front_matter')
            const positionLabel = startOrder > 0 ? `Block #${startOrder}` : ''
            const problemTags = Array.isArray(selected?.problem_tags)
                ? selected.problem_tags.map((tag) => String(tag || '')).filter((tag) => tag !== '')
                : []

            return {
                id: `pandoc-abstract-fallback-${startOrder > 0 ? startOrder : 'x'}`,
                area: 'abstract',
                area_label: 'Abstract',
                text: 'Abstract',
                display_text: 'Abstract',
                detail_lines: [],
                type_label: 'Abstract',
                confidence,
                strategy,
                compare_key: this.sectionCompareKey('Abstract'),
                semantic_type: 'abstract',
                semantic_compare_key: this.sectionSemanticCompareKey('Abstract', 'abstract'),
                zone_context: zoneContext,
                parent_text: '',
                parent_compare_key: '',
                parent_numbering: '',
                numbering: '',
                numbering_depth: 0,
                structure_level: 0,
                content_text: contentText,
                content_excerpt: contentExcerpt,
                content_preview_lines: previewLines,
                content_line_count: previewLines.length,
                toc_outline_lines: [],
                toc_page_index_lines: [],
                toc_primary_kind: 'outline',
                position_label: positionLabel,
                problem_tags: problemTags,
                order: startOrder > 0 ? startOrder : 50000,
            }
        },
        scorePandocAbstractFallbackCandidate(block) {
            const text = this.extractPandocNormalizationBlockText(block)
            if (text === '') {
                return -1000
            }

            let score = 0
            const compareKey = this.sectionCompareKey(text)
            if (['abstract', 'zusammenfassung', 'kurzfassung'].includes(compareKey)) {
                score += 120
            }

            if (/\s+\d{1,4}\s*$/u.test(text)) {
                score -= 90
            } else {
                score += 30
            }

            const zone = String(block?.document_zone?.zone || block?.document_zone || '').trim().toLowerCase()
            if (zone !== 'table_of_contents') {
                score += 40
            }

            if (!this.isLikelyTocArtifactBlock(block)) {
                score += 20
            }

            const headingLevel = Number(block?.heading_level || 0)
            if (headingLevel === 1) {
                score += 20
            }

            const order = Number(block?.order || 0)
            if (Number.isFinite(order) && order > 0) {
                score += Math.min(300, order)
            }

            return score
        },
        isLikelyTocArtifactBlock(block) {
            const zone = String(block?.document_zone?.zone || block?.document_zone || '').trim().toLowerCase()
            if (zone === 'table_of_contents') {
                return true
            }

            const tags = Array.isArray(block?.problem_tags) ? block.problem_tags : []
            return tags.map((tag) => String(tag || '')).includes('probable_toc_artifact')
        },
        extractPandocNormalizationBlockText(block) {
            const candidates = [
                String(block?.plain_text || '').trim(),
                String(block?.text || '').trim(),
                String(block?.image?.alt_text || '').trim(),
            ]
            const first = candidates.find((value) => value !== '')
            return first ? String(first) : ''
        },
        isLikelyTocArtifactProjectionLine(text) {
            const value = String(text || '').trim()
            if (value === '') {
                return false
            }

            if (/^\s*abstract\s+\d{1,4}\s*$/iu.test(value)) {
                return true
            }

            const hasPageSuffix = /\s+\d{1,4}\s*$/u.test(value)
            const hasSectionKeyword = /\b(inhaltsverzeichnis|einleitung|fazit|literaturverzeichnis|abbildungsverzeichnis|eigenständigkeitserklärung|abstract)\b/iu.test(value)
            return hasPageSuffix && hasSectionKeyword
        },
        isSpecialSemanticType(semanticType) {
            return [
                'titlepage',
                'abstract',
                'foreword',
                'toc',
                'bibliography',
                'figure_index',
                'declaration',
                'appendix',
                'frontmatter',
                'endmatter',
            ].includes(String(semanticType || ''))
        },
        isCanonicalSpecialSemantic(semanticType) {
            return [
                'titlepage',
                'abstract',
                'foreword',
                'toc',
                'bibliography',
                'figure_index',
                'declaration',
                'appendix',
            ].includes(String(semanticType || '').trim().toLowerCase())
        },
        sectionSemanticCompareKey(text, semanticType) {
            const base = this.sectionCompareKey(text)
            const semantic = String(semanticType || '').trim().toLowerCase()

            if (semantic !== '' && base !== '') {
                return `${semantic}|${base}`
            }
            if (semantic !== '') {
                return semantic
            }

            return base
        },
        normalizeMatchCategory(item) {
            const semanticType = String(item?.semantic_type || '').trim().toLowerCase()
            const type = String(item?.type || '').trim().toLowerCase()
            if (semanticType === 'figure' || type === 'figure') {
                return 'figure'
            }
            if (this.isSpecialSemanticType(semanticType)) {
                return 'special'
            }

            return 'section'
        },
        areSpecialSemanticsCompatible(localSemantic, pandocSemantic) {
            const left = String(localSemantic || '').trim().toLowerCase()
            const right = String(pandocSemantic || '').trim().toLowerCase()
            if (left === '' || right === '') {
                return false
            }
            if (left === right) {
                return true
            }

            const frontmatterGroup = ['titlepage', 'abstract', 'foreword', 'toc', 'frontmatter']
            const endmatterGroup = ['bibliography', 'figure_index', 'declaration', 'appendix', 'endmatter']
            if ((left === 'frontmatter' || right === 'frontmatter') && frontmatterGroup.includes(left) && frontmatterGroup.includes(right)) {
                return true
            }
            if ((left === 'endmatter' || right === 'endmatter') && endmatterGroup.includes(left) && endmatterGroup.includes(right)) {
                return true
            }

            return false
        },
        areMainSemanticsCompatible(localSemantic, pandocSemantic) {
            const left = String(localSemantic || '').trim().toLowerCase()
            const right = String(pandocSemantic || '').trim().toLowerCase()
            if (left === '' || right === '') {
                return true
            }
            if (left === right) {
                return true
            }
            if (['chapter', 'subchapter'].includes(left) && ['chapter', 'subchapter'].includes(right)) {
                return true
            }

            return false
        },
        firstNumberSegment(numbering) {
            const value = String(numbering || '').trim()
            if (value === '') {
                return ''
            }

            return value.split('.').map((part) => String(part || '').trim()).find((part) => part !== '') || ''
        },
        tokenSimilarity(leftText, rightText) {
            const leftTokens = new Set(String(leftText || '').split(/\s+/u).filter((token) => token !== ''))
            const rightTokens = new Set(String(rightText || '').split(/\s+/u).filter((token) => token !== ''))
            if (leftTokens.size === 0 || rightTokens.size === 0) {
                return 0
            }

            let intersection = 0
            leftTokens.forEach((token) => {
                if (rightTokens.has(token)) {
                    intersection += 1
                }
            })

            const union = leftTokens.size + rightTokens.size - intersection
            if (union <= 0) {
                return 0
            }

            return intersection / union
        },
        scorePotentialMatch(localItem, pandocItem) {
            const localSemantic = String(localItem?.semantic_type || '').trim().toLowerCase()
            const pandocSemantic = String(pandocItem?.semantic_type || '').trim().toLowerCase()
            const localCategory = this.normalizeMatchCategory(localItem)
            const pandocCategory = this.normalizeMatchCategory(pandocItem)

            if (localCategory !== pandocCategory) {
                if (!(localCategory === 'section' && pandocCategory === 'section')) {
                    return null
                }
            }

            if (localCategory === 'special' && !this.areSpecialSemanticsCompatible(localSemantic, pandocSemantic)) {
                return null
            }
            if (localCategory !== 'special' && !this.areMainSemanticsCompatible(localSemantic, pandocSemantic)) {
                return null
            }

            const localTextKey = String(localItem?.compare_key || '')
            const pandocTextKey = String(pandocItem?.compare_key || '')
            const specialSemanticExact = localCategory === 'special'
                && localSemantic !== ''
                && pandocSemantic !== ''
                && localSemantic === pandocSemantic
            if ((localTextKey === '' || pandocTextKey === '') && !specialSemanticExact) {
                return null
            }

            const localNumbering = String(localItem?.numbering || '')
            const pandocNumbering = String(pandocItem?.numbering || '')
            const localParentKey = String(localItem?.parent_compare_key || '')
            const pandocParentKey = String(pandocItem?.parent_compare_key || '')
            const localParentNumbering = String(localItem?.parent_numbering || '')
            const pandocParentNumbering = String(pandocItem?.parent_numbering || '')
            const localLevel = Number(localItem?.structure_level || 0)
            const pandocLevel = Number(pandocItem?.structure_level || 0)
            const localOrder = Number(localItem?.sort_order || localItem?.order || 0)
            const pandocOrder = Number(pandocItem?.sort_order || pandocItem?.order || 0)
            const localZone = String(localItem?.zone_context || '').trim().toLowerCase()
            const pandocZone = String(pandocItem?.zone_context || '').trim().toLowerCase()
            const localFigureNumber = this.extractFigureNumber(`${String(localItem?.text || '')} ${String(localItem?.caption || '')}`.trim())
            const pandocFigureNumber = this.extractFigureNumber(`${String(pandocItem?.text || '')} ${String(pandocItem?.caption || '')}`.trim())

            const titleExact = localTextKey !== '' && pandocTextKey !== '' && localTextKey === pandocTextKey
            const numberingExact = localNumbering !== '' && pandocNumbering !== '' && localNumbering === pandocNumbering
            const parentTitleExact = localParentKey !== '' && pandocParentKey !== '' && localParentKey === pandocParentKey
            const parentNumberingExact = localParentNumbering !== '' && pandocParentNumbering !== '' && localParentNumbering === pandocParentNumbering
            const tokenSimilarity = this.tokenSimilarity(localTextKey, pandocTextKey)
            const levelDiff = localLevel > 0 && pandocLevel > 0 ? Math.abs(localLevel - pandocLevel) : null
            const zoneExact = localZone !== '' && pandocZone !== '' && localZone === pandocZone
            const orderDiff = localOrder > 0 && pandocOrder > 0 ? Math.abs(localOrder - pandocOrder) : null
            const orderNear = orderDiff !== null && orderDiff <= 36
            const canonicalSpecialExact = specialSemanticExact && this.isCanonicalSpecialSemantic(localSemantic)
            const figureNumberExact = localFigureNumber !== '' && pandocFigureNumber !== '' && localFigureNumber === pandocFigureNumber

            if (localCategory === 'section' && levelDiff !== null && levelDiff > 1) {
                return null
            }

            let score = 0
            if (titleExact) {
                score += localCategory === 'special' ? 0.24 : 0.52
            } else {
                score += tokenSimilarity * (localCategory === 'special' ? 0.2 : 0.34)
            }

            if (specialSemanticExact) {
                score += 0.42
                if (canonicalSpecialExact) {
                    score += 0.18
                }
            } else if (localCategory === 'special') {
                score += 0.1
            }

            if (numberingExact) {
                score += 0.24
            } else if (localNumbering !== '' && pandocNumbering !== '') {
                const leftFirst = this.firstNumberSegment(localNumbering)
                const rightFirst = this.firstNumberSegment(pandocNumbering)
                if (leftFirst !== '' && rightFirst !== '' && leftFirst !== rightFirst) {
                    score -= 0.24
                } else {
                    score -= 0.12
                }
            }

            if (parentTitleExact) {
                score += 0.12
            }
            if (parentNumberingExact) {
                score += 0.08
            }

            if (levelDiff !== null) {
                if (levelDiff === 0) {
                    score += 0.08
                } else if (levelDiff === 1) {
                    score += 0.04
                } else if (levelDiff >= 3) {
                    score -= 0.1
                }
            }

            if (localSemantic !== '' && localSemantic === pandocSemantic) {
                score += 0.06
            }
            if (zoneExact) {
                score += 0.08
            }
            if (localCategory === 'special' && orderDiff !== null) {
                if (orderDiff <= 10) {
                    score += 0.12
                } else if (orderDiff <= 35) {
                    score += 0.08
                } else if (orderDiff <= 90) {
                    score += 0.04
                } else if (orderDiff > 180) {
                    score -= 0.06
                }
            }
            if (localCategory === 'figure' && titleExact) {
                score += 0.07
            }
            if (localCategory === 'figure' && figureNumberExact) {
                score += 0.36
            }

            score = Math.max(0, Math.min(1, score))

            const hasCoreSignal = titleExact || numberingExact || tokenSimilarity >= 0.88 || specialSemanticExact || figureNumberExact
            if (!hasCoreSignal) {
                return null
            }

            if (localCategory === 'figure' && (figureNumberExact || numberingExact)) {
                const anchoredByContext = titleExact
                    || tokenSimilarity >= 0.28
                    || parentTitleExact
                    || parentNumberingExact
                    || orderNear
                if (anchoredByContext && score >= 0.5) {
                    const calibratedScore = Math.max(
                        score,
                        figureNumberExact
                            ? (titleExact || tokenSimilarity >= 0.6 ? 0.93 : 0.87)
                            : 0.78
                    )

                    return {
                        score: Math.max(0, Math.min(1, calibratedScore)),
                        status: calibratedScore >= 0.9 ? 'matched_strong' : 'matched_likely',
                        titleExact,
                        numberingExact,
                        parentTitleExact,
                        tokenSimilarity,
                        levelDiff,
                    }
                }
            }

            if (localCategory === 'special' && canonicalSpecialExact) {
                const anchoredByContext = zoneExact || orderNear || titleExact || tokenSimilarity >= 0.2
                if (anchoredByContext && score >= 0.56) {
                    const calibratedScore = Math.max(
                        score,
                        zoneExact
                            ? 0.88
                            : (titleExact || tokenSimilarity >= 0.62 || orderDiff === 0 ? 0.84 : 0.76)
                    )

                    return {
                        score: Math.max(0, Math.min(1, calibratedScore)),
                        status: calibratedScore >= 0.86 ? 'matched_strong' : 'matched_likely',
                        titleExact,
                        numberingExact,
                        parentTitleExact,
                        tokenSimilarity,
                        levelDiff,
                    }
                }
            }

            let status = null
            if (localCategory === 'special' && specialSemanticExact && score >= 0.82) {
                status = tokenSimilarity >= 0.78 || titleExact ? 'matched_strong' : 'matched_likely'
            } else
            if (
                score >= 0.88
                && titleExact
                && (numberingExact || parentTitleExact || localCategory !== 'section' || tokenSimilarity >= 0.95)
            ) {
                status = 'matched_strong'
            } else if (
                score >= 0.74
                && (titleExact || numberingExact || tokenSimilarity >= 0.9)
            ) {
                status = 'matched_likely'
            } else if (
                score >= 0.6
                && (tokenSimilarity >= 0.72 || parentTitleExact || (levelDiff !== null && levelDiff <= 1) || orderNear)
            ) {
                status = 'matched_structural'
            }

            if (status === null) {
                return null
            }

            return {
                score,
                status,
                titleExact,
                numberingExact,
                parentTitleExact,
                tokenSimilarity,
                levelDiff,
            }
        },
        buildChapterMatchingMatrix(localItems, pandocMainItems, pandocSpecialItems) {
            const localById = {}
            const pandocById = {}
            const matches = []
            const usedLocalIds = new Set()
            const usedPandocIds = new Set()
            const candidates = []

            const pandocItems = [
                ...(Array.isArray(pandocMainItems) ? pandocMainItems : []),
                ...(Array.isArray(pandocSpecialItems) ? pandocSpecialItems : []),
            ]

            const localList = Array.isArray(localItems) ? localItems : []
            localList.forEach((localItem) => {
                pandocItems.forEach((pandocItem) => {
                    const localId = String(localItem?.id || '')
                    const pandocId = String(pandocItem?.id || '')
                    if (localId === '' || pandocId === '') {
                        return
                    }

                    const score = this.scorePotentialMatch(localItem, pandocItem)
                    if (!score) {
                        return
                    }

                    candidates.push({
                        localId,
                        pandocId,
                        localItem,
                        pandocItem,
                        ...score,
                    })
                })
            })

            const statusPriority = ['matched_strong', 'matched_likely', 'matched_structural']
            statusPriority.forEach((status) => {
                candidates
                    .filter((candidate) => candidate.status === status)
                    .sort((left, right) => {
                        if (right.score !== left.score) {
                            return right.score - left.score
                        }
                        if (right.tokenSimilarity !== left.tokenSimilarity) {
                            return right.tokenSimilarity - left.tokenSimilarity
                        }
                        return Number(left.pandocItem?.order || 0) - Number(right.pandocItem?.order || 0)
                    })
                    .forEach((candidate) => {
                        if (usedLocalIds.has(candidate.localId) || usedPandocIds.has(candidate.pandocId)) {
                            return
                        }

                        usedLocalIds.add(candidate.localId)
                        usedPandocIds.add(candidate.pandocId)

                        localById[candidate.localId] = {
                            status: candidate.status,
                            score: candidate.score,
                            matched_id: candidate.pandocId,
                        }
                        pandocById[candidate.pandocId] = {
                            status: candidate.status,
                            score: candidate.score,
                            matched_id: candidate.localId,
                        }
                        matches.push({
                            local_id: candidate.localId,
                            pandoc_id: candidate.pandocId,
                            status: candidate.status,
                            score: candidate.score,
                        })
                    })
            })

            localList.forEach((item) => {
                const key = String(item?.id || '')
                if (key === '' || localById[key]) {
                    return
                }

                localById[key] = {
                    status: 'local_only',
                    score: 0,
                    matched_id: null,
                }
            })
            pandocItems.forEach((item) => {
                const key = String(item?.id || '')
                if (key === '' || pandocById[key]) {
                    return
                }

                pandocById[key] = {
                    status: 'pandoc_only',
                    score: 0,
                    matched_id: null,
                }
            })

            return {
                local_by_id: localById,
                pandoc_by_id: pandocById,
                matches,
            }
        },
        localSectionMatchStatus(item) {
            const key = String(item?.id || '')
            const map = this.chapterMatchingMatrix?.local_by_id || {}
            if (key !== '' && map?.[key]?.status) {
                return String(map[key].status)
            }

            return 'local_only'
        },
        pandocSectionMatchStatus(item) {
            const key = String(item?.id || '')
            const map = this.chapterMatchingMatrix?.pandoc_by_id || {}
            if (key !== '' && map?.[key]?.status) {
                return String(map[key].status)
            }

            return 'pandoc_only'
        },
        pandocSpecialMatchStatus(item) {
            const key = String(item?.id || '')
            const map = this.chapterMatchingMatrix?.pandoc_by_id || {}
            if (key !== '' && map?.[key]?.status) {
                return String(map[key].status)
            }

            return 'pandoc_only'
        },
        pandocNodeMatchStatus(item) {
            if (this.isPandocUiContainerNode(item)) {
                return 'container'
            }

            if (this.isSpecialSemanticType(String(item?.semantic_type || ''))) {
                return this.pandocSpecialMatchStatus(item)
            }

            return this.pandocSectionMatchStatus(item)
        },
        sectionMatchStatusLabel(status) {
            if (status === 'container') {
                return 'Container'
            }
            if (status === 'both') {
                return 'sicher gematcht'
            }
            if (status === 'matched_strong') {
                return 'sicher gematcht'
            }
            if (status === 'matched_likely') {
                return 'wahrscheinlich gematcht'
            }
            if (status === 'matched_structural') {
                return 'strukturell ähnlich'
            }
            if (status === 'pandoc_only') {
                return 'nur Pandoc'
            }

            return 'nur lokal'
        },
        sectionMatchStatusColor(status) {
            if (status === 'container') {
                return 'grey'
            }
            if (status === 'both') {
                return 'success'
            }
            if (status === 'matched_strong') {
                return 'success'
            }
            if (status === 'matched_likely') {
                return 'light-green'
            }
            if (status === 'matched_structural') {
                return 'amber'
            }
            if (status === 'pandoc_only') {
                return 'teal'
            }

            return 'orange'
        },
        normalizePandocHeadingLevel(value) {
            const numeric = Number(value)
            if (!Number.isFinite(numeric) || numeric <= 0) {
                return 1
            }

            return Math.min(9, Math.max(1, Math.round(numeric)))
        },
        normalizePandocOutlineNode(node, depth = 0, fallbackIndex = 0, parentNode = null) {
            if (!node || typeof node !== 'object') {
                return null
            }

            const text = String(node.text || '').trim()
            if (text === '') {
                return null
            }

            const id = node.id ?? `pandoc-outline-${fallbackIndex}-${node.order || 0}`
            const type = String(node.section_type || node.type || '').trim().toLowerCase()
            const zoneContext = String(node.document_zone || 'main_content')
            const semanticType = this.normalizeSectionSemanticType(type, zoneContext)
            const parentText = parentNode ? String(parentNode.text || '').trim() : ''
            const parentNumbering = this.resolveComparableNumbering(parentText, String(parentNode?.type || ''))
            const numbering = this.resolveComparableNumbering(text, type)
            const numberingDepth = this.numberingDepth(numbering)
            const fallbackLevel = this.normalizePandocHeadingLevel(node.outline_level ?? node.heading_level ?? (depth + 1))
            const previewLines = Array.isArray(node.content_preview_lines)
                ? this.normalizeProjectionLines(node.content_preview_lines, true)
                : []
            const contentText = String(node.content_text || '').trim()
            const contentExcerpt = String(node.content_excerpt || '').trim()
            const directPreviewLines = Array.isArray(node.content_direct_preview_lines)
                ? this.normalizeProjectionLines(node.content_direct_preview_lines, true)
                : []
            const directContentText = String(node.content_direct_text || '').trim()
            const directContentExcerpt = String(node.content_direct_excerpt || '').trim()
            const contentWithChildrenText = String(node.content_with_children_text || '').trim()
            const contentWithChildrenExcerpt = String(node.content_with_children_excerpt || '').trim()
            const contentWithChildrenPreviewLines = Array.isArray(node.content_with_children_preview_lines)
                ? this.normalizeProjectionLines(node.content_with_children_preview_lines, true)
                : []
            const contentOwnText = String(node.content_own_text || '').trim()
            const contentOwnExcerpt = String(node.content_own_excerpt || '').trim()
            const contentOwnPreviewLines = Array.isArray(node.content_own_preview_lines)
                ? this.normalizeProjectionLines(node.content_own_preview_lines, true)
                : []
            const normalized = {
                id,
                source: 'pandoc',
                text,
                type,
                type_label: String(node.section_type_label || node.section_type || this.localSectionTypeLabel(type || 'chapter')),
                confidence: String(node.confidence || 'low'),
                strategy: String(node.strategy || 'heuristic'),
                order: Number(node.order || 0),
                heading_level: this.normalizePandocHeadingLevel(node.heading_level ?? node.outline_level ?? 1),
                outline_level: fallbackLevel,
                is_usable_heading: Boolean(node.is_usable_heading),
                problem_tags: Array.isArray(node.problem_tags) ? node.problem_tags.map((value) => String(value || '')) : [],
                compare_key: type === 'figure'
                    ? this.figureCompareKey(text, String(node?.caption || ''))
                    : String(node.compare_key || this.sectionCompareKey(text)),
                semantic_type: semanticType,
                semantic_compare_key: this.sectionSemanticCompareKey(text, semanticType),
                zone_context: zoneContext,
                parent_text: parentText,
                parent_compare_key: this.sectionCompareKey(parentText),
                parent_numbering: parentNumbering,
                numbering,
                numbering_depth: numberingDepth,
                structure_level: this.resolveStructureLevel({
                    numberingDepth,
                    fallbackLevel,
                    semanticType,
                    itemType: type,
                }),
                content_text: contentText !== '' ? contentText : null,
                content_excerpt: contentExcerpt !== '' ? contentExcerpt : null,
                content_preview_lines: previewLines,
                content_line_count: Number(node.content_line_count || previewLines.length),
                content_direct_text: directContentText !== '' ? directContentText : null,
                content_direct_excerpt: directContentExcerpt !== '' ? directContentExcerpt : null,
                content_direct_preview_lines: directPreviewLines,
                content_direct_line_count: Number(node.content_direct_line_count || directPreviewLines.length),
                content_with_children_text: contentWithChildrenText !== '' ? contentWithChildrenText : null,
                content_with_children_excerpt: contentWithChildrenExcerpt !== '' ? contentWithChildrenExcerpt : null,
                content_with_children_preview_lines: contentWithChildrenPreviewLines,
                content_with_children_line_count: Number(node.content_with_children_line_count || contentWithChildrenPreviewLines.length),
                content_own_text: contentOwnText !== '' ? contentOwnText : null,
                content_own_excerpt: contentOwnExcerpt !== '' ? contentOwnExcerpt : null,
                content_own_preview_lines: contentOwnPreviewLines,
                content_own_line_count: Number(node.content_own_line_count || contentOwnPreviewLines.length),
                content_scope: String(node.content_scope || ''),
                position_label: String(node.position_label || (Number(node.order || 0) > 0 ? `Block #${Number(node.order || 0)}` : '')),
                depth,
                children: [],
            }

            const children = Array.isArray(node.children) ? node.children : []
            normalized.children = children
                .map((child, index) => this.normalizePandocOutlineNode(child, depth + 1, index, normalized))
                .filter((child) => child !== null)

            return normalized
        },
        pandocDescendantRows(rootNode) {
            const rows = []
            const append = (nodes, depth) => {
                if (!Array.isArray(nodes) || nodes.length === 0) {
                    return
                }

                nodes.forEach((node) => {
                    rows.push({
                        ...node,
                        depth,
                    })
                    append(node.children, depth + 1)
                })
            }

            append(rootNode?.children, 1)

            return rows
        },
        pandocDescendantCount(rootNode) {
            return this.pandocDescendantRows(rootNode).length
        },
        normalizeProjectionLine(line, preserveIndentation = false) {
            const raw = String(line || '')
                .replace(/\r/gu, '')
                .replace(/\t/gu, '  ')
            if (preserveIndentation) {
                return raw.replace(/\s+$/gu, '')
            }

            return raw.trim()
        },
        normalizeProjectionLines(lines, preserveIndentation = false) {
            if (!Array.isArray(lines) || lines.length === 0) {
                return []
            }

            return lines
                .map((line) => this.normalizeProjectionLine(line, preserveIndentation))
                .filter((line) => String(line || '').trim() !== '')
        },
        isPandocTocNode(node) {
            const semantic = String(node?.semantic_type || node?.area || '').trim().toLowerCase()
            const type = String(node?.type || '').trim().toLowerCase()
            const text = String(node?.text || '').trim().toLowerCase()

            if (semantic === 'toc') {
                return true
            }
            if (['toc', 'table_of_contents'].includes(type)) {
                return true
            }

            return text === 'inhaltsverzeichnis'
        },
        isPandocTitlepageNode(node) {
            const semantic = String(node?.semantic_type || node?.area || '').trim().toLowerCase()
            const type = String(node?.type || '').trim().toLowerCase()
            const zone = String(node?.zone_context || '').trim().toLowerCase()
            return semantic === 'titlepage'
                || semantic === 'title_page'
                || type === 'titlepage'
                || type === 'title_page'
                || zone === 'title_page'
        },
        buildTitlePageDetailLinesFromNormalized(normalized, options = {}) {
            const output = normalized && typeof normalized === 'object'
                ? normalized
                : {}
            const recoveryContext = this.buildTitlePageRecoveryContext({
                ...options,
                normalizedOutput: output,
            })
            const lines = []
            const append = (label, field, value) => {
                const text = this.normalizeTitlePageCoreFieldValue(field, value)
                if (text === '') {
                    return
                }
                lines.push(`${label}: ${text}`)
            }

            const title = this.normalizeTitlePageCoreFieldValue('title', output.title)
                || this.normalizeTitlePageCoreFieldValue('title', this.recoverMissingCoreTitle(recoveryContext))
            const author = this.normalizeTitlePageCoreFieldValue('author', output.author)
                || this.normalizeTitlePageCoreFieldValue('author', this.recoverMissingAuthorFromFinalProperties(recoveryContext))
            const classValue = this.normalizeTitlePageCoreFieldValue('class', output.class)
                || this.normalizeTitlePageCoreFieldValue('class', this.recoverMissingClassFromFinalProperties(recoveryContext))

            append('Titel', 'title', title)
            append('Untertitel', 'subtitle', output.subtitle)
            append('Verfasser*in', 'author', author)
            append('Betreuer', 'advisor', output.advisor)
            append('Klasse', 'class', classValue)
            append('Datum', 'date', output.date)

            return lines
        },
        normalizeTitlePageCoreFieldValue(field, value) {
            let text = this.cleanLabelArtifactValue(value, { role: field })
            if (text === '') {
                return ''
            }

            if (this.isPlaceholderMetadataValue(text, field)) {
                return ''
            }
            if (field === 'title' && this.isLocationDatePseudoTitle(text)) {
                return ''
            }
            if (field === 'class' && this.isMergedMultiFieldValue(text, field)) {
                const recoveredClass = this.recoverClassToken(text)
                return recoveredClass !== '' ? recoveredClass : ''
            }

            return text
        },
        buildTitlePageRecoveryContext(options = {}) {
            const providedAba = options?.aba && typeof options.aba === 'object' ? options.aba : null
            const providedBlocks = Array.isArray(options?.normalizationBlocks) ? options.normalizationBlocks : []
            const providedDocumentType = String(options?.documentTypeLabel || '').trim()
            const providedNormalizedOutput = options?.normalizedOutput && typeof options.normalizedOutput === 'object'
                ? options.normalizedOutput
                : null
            const providedTitlePageDetails = options?.titlePageDetails && typeof options.titlePageDetails === 'object'
                ? options.titlePageDetails
                : null
            const providedDisplayValues = options?.displayValues && typeof options.displayValues === 'object'
                ? options.displayValues
                : null
            const providedAnalysisStats = options?.analysisStats && typeof options.analysisStats === 'object'
                ? options.analysisStats
                : null

            return {
                aba: providedAba || (this.aba && typeof this.aba === 'object' ? this.aba : null),
                normalizationBlocks: providedBlocks.length > 0
                    ? providedBlocks
                    : (Array.isArray(this.documentReviewNormalizationBlocks) ? this.documentReviewNormalizationBlocks : []),
                documentTypeLabel: providedDocumentType !== ''
                    ? providedDocumentType
                    : String(this.documentTypeLabel || '').trim(),
                normalizedOutput: providedNormalizedOutput
                    || (this.documentReviewTitlePageNormalized && typeof this.documentReviewTitlePageNormalized === 'object'
                        ? this.documentReviewTitlePageNormalized
                        : null),
                titlePageDetails: providedTitlePageDetails
                    || (this.documentReviewData?.title_page_details && typeof this.documentReviewData.title_page_details === 'object'
                        ? this.documentReviewData.title_page_details
                        : null),
                displayValues: providedDisplayValues
                    || (this.displayValues && typeof this.displayValues === 'object' ? this.displayValues : null),
                analysisStats: providedAnalysisStats
                    || (this.analysisStats && typeof this.analysisStats === 'object' ? this.analysisStats : null),
            }
        },
        recoverMissingCoreTitle(context = {}) {
            const titleCandidates = [
                context?.aba?.title,
                context?.displayValues?.title_page_title,
                context?.analysisStats?.title_page_title,
                context?.displayValues?.title_page_details?.title,
                context?.analysisStats?.title_page_details?.title,
                context?.titlePageDetails?.title,
            ]
            for (const candidate of titleCandidates) {
                const value = String(candidate || '').trim()
                if (value === '') {
                    continue
                }
                if (this.isLocationDatePseudoTitle(value) || this.isPlaceholderMetadataValue(value, 'title')) {
                    continue
                }

                return value
            }

            return this.recoverTitleFromNormalizationBlocks(context?.normalizationBlocks)
        },
        recoverMissingAuthorFromFinalProperties(context = {}) {
            const authorCandidates = [
                context?.aba?.student_name,
                context?.displayValues?.title_page_submitter,
                context?.analysisStats?.title_page_submitter,
                context?.displayValues?.title_page_details?.submitter,
                context?.analysisStats?.title_page_details?.submitter,
                context?.titlePageDetails?.submitter,
            ]
            for (const candidate of authorCandidates) {
                const value = this.normalizeTitlePageCoreFieldValue('author', candidate)
                if (value !== '') {
                    return value
                }
            }

            const lines = this.titlePageTextLinesFromNormalizationBlocks(context?.normalizationBlocks)
            for (let index = 0; index < lines.length; index++) {
                const line = String(lines[index] || '').trim()
                if (line === '' || this.isArchiveNavigationNoise(line, 'other')) {
                    continue
                }

                const labeledMatch = line.match(/\b(verfasser(?:\s*\/\s*in)?|verfasser\*in|eingereicht von|verfasst von)\b\s*[:\-]?\s*(.+)$/iu)
                if (labeledMatch) {
                    const inlineCandidate = this.normalizeTitlePageCoreFieldValue('author', String(labeledMatch[2] || '').trim())
                    if (this.isLikelyAuthorName(inlineCandidate)) {
                        return inlineCandidate
                    }
                }

                const normalizedLine = this.comparableTitlePageText(line)
                const introducesAuthorOnNextLine = normalizedLine.endsWith('verfasst von')
                    || normalizedLine.endsWith('eingereicht von')
                    || /^(verfasser(?:\s*\/\s*in)?|verfasser\*in)\s*:?$/iu.test(line)
                if (!introducesAuthorOnNextLine) {
                    continue
                }

                for (let lookahead = index + 1; lookahead < lines.length && lookahead <= index + 2; lookahead++) {
                    const nextLine = this.normalizeTitlePageCoreFieldValue('author', String(lines[lookahead] || '').trim())
                    if (this.isLikelyAuthorName(nextLine)) {
                        return nextLine
                    }
                }
            }

            return ''
        },
        recoverMissingClassFromFinalProperties(context = {}) {
            const studentClass = this.recoverClassToken(String(context?.aba?.student_class || '').trim())
            if (studentClass !== '') {
                return studentClass
            }

            const classCandidates = [
                context?.displayValues?.title_page_class,
                context?.analysisStats?.title_page_class,
                context?.displayValues?.title_page_details?.class,
                context?.analysisStats?.title_page_details?.class,
                context?.titlePageDetails?.class,
            ]
            for (const candidate of classCandidates) {
                const value = this.normalizeTitlePageCoreFieldValue('class', candidate)
                if (value !== '') {
                    return value
                }
            }

            const lines = this.titlePageTextLinesFromNormalizationBlocks(context?.normalizationBlocks)
            for (const line of lines) {
                const direct = this.recoverClassToken(line)
                if (direct !== '') {
                    return direct
                }

                const labeledMatch = line.match(/\bklasse\b\s*[:\-]?\s*([0-9]{1,2}[A-Za-z]{1,4}[0-9]?)\b/iu)
                if (labeledMatch) {
                    return this.recoverClassToken(String(labeledMatch[1] || ''))
                }
            }

            return ''
        },
        recoverMissingDocumentTypeFromFinalProperties(context = {}) {
            const directDocumentTypeCandidates = [
                context?.normalizedOutput?.document_type,
                context?.titlePageDetails?.document_type,
                context?.displayValues?.title_page_details?.document_type,
                context?.analysisStats?.title_page_details?.document_type,
                context?.documentTypeLabel,
            ]
            for (const candidate of directDocumentTypeCandidates) {
                const normalizedDocumentType = this.normalizeDocumentTypeValue(String(candidate || '').trim())
                const normalizedLookupValue = this.comparableTitlePageText(normalizedDocumentType)
                if (
                    normalizedDocumentType !== ''
                    && !['unbekannt', 'unknown', '-', 'aba', 'other'].includes(normalizedLookupValue)
                ) {
                    return normalizedDocumentType
                }
            }

            const lines = this.titlePageTextLinesFromNormalizationBlocks(context?.normalizationBlocks)
            for (const line of lines) {
                const normalized = this.comparableTitlePageText(line)
                if (normalized.includes('abschliessende arbeit')) {
                    return 'Abschließende Arbeit'
                }
                if (normalized.includes('vorwissenschaftliche arbeit')) {
                    return 'Vorwissenschaftliche Arbeit'
                }

                const labeledMatch = line.match(/\bdokumenttyp\b\s*[:\-]\s*(.+)$/iu)
                if (labeledMatch) {
                    const labeledValue = this.normalizeDocumentTypeValue(String(labeledMatch[1] || '').trim())
                    if (labeledValue !== '') {
                        return labeledValue
                    }
                }
            }

            return ''
        },
        recoverMissingSchoolFromFinalProperties(context = {}) {
            const schoolCandidates = [
                context?.normalizedOutput?.school,
                context?.titlePageDetails?.school,
                context?.displayValues?.title_page_details?.school,
                context?.analysisStats?.title_page_details?.school,
            ]
            for (const candidate of schoolCandidates) {
                const value = this.cleanLabelArtifactValue(candidate, { role: 'school' })
                if (value === '' || this.isPlaceholderMetadataValue(value, 'school') || this.isArchiveNavigationNoise(value, 'school')) {
                    continue
                }

                return value
            }

            return this.recoverSchoolPartsFromNormalizationBlocks(context?.normalizationBlocks).school
        },
        recoverMissingSchoolFullFromFinalProperties(context = {}) {
            const schoolFullCandidates = [
                context?.normalizedOutput?.school_full,
                context?.titlePageDetails?.school_full,
                context?.displayValues?.title_page_details?.school_full,
                context?.analysisStats?.title_page_details?.school_full,
            ]
            for (const candidate of schoolFullCandidates) {
                const value = this.cleanLabelArtifactValue(candidate, { role: 'school_full' })
                if (value === '' || this.isPlaceholderMetadataValue(value, 'school_full') || this.isArchiveNavigationNoise(value, 'school_full')) {
                    continue
                }

                return value
            }

            return this.recoverSchoolPartsFromNormalizationBlocks(context?.normalizationBlocks).school_full
        },
        ensureRecoveredSchoolProperties(properties, options = {}) {
            const entries = Array.isArray(properties) ? [...properties] : []
            const hasSchool = entries.some((property) =>
                this.titlePagePropertyRole(property) === 'school'
                    && String(property?.value || '').trim() !== ''
                    && !this.isArchiveNavigationNoise(String(property?.value || ''), 'school')
            )
            const hasSchoolFull = entries.some((property) =>
                this.titlePagePropertyRole(property) === 'school_full'
                    && String(property?.value || '').trim() !== ''
                    && !this.isArchiveNavigationNoise(String(property?.value || ''), 'school_full')
            )
            if (hasSchool && hasSchoolFull) {
                return entries
            }

            const context = this.buildTitlePageRecoveryContext(options)
            const recoveredSchool = hasSchool ? '' : this.recoverMissingSchoolFromFinalProperties(context)
            const recoveredSchoolFull = hasSchoolFull ? '' : this.recoverMissingSchoolFullFromFinalProperties(context)
            if (recoveredSchool === '' && recoveredSchoolFull === '') {
                return entries
            }

            if (!hasSchool && recoveredSchool !== '') {
                entries.push({
                    label: 'Schule',
                    value: recoveredSchool,
                    source_label: 'Schule',
                    normalized_label: 'schule',
                    order: null,
                })
            }
            if (!hasSchoolFull && recoveredSchoolFull !== '') {
                entries.push({
                    label: 'Schule (vollständig)',
                    value: recoveredSchoolFull,
                    source_label: 'Schule (vollständig)',
                    normalized_label: 'schule_vollstaendig',
                    order: null,
                })
            }

            return entries
        },
        ensureRecoveredDocumentTypeProperty(properties, options = {}) {
            const entries = Array.isArray(properties) ? properties : []
            const hasDocumentType = entries.some((property) =>
                this.titlePagePropertyRole(property) === 'document_type'
                    && String(property?.value || '').trim() !== ''
            )
            if (hasDocumentType) {
                return entries
            }

            const context = this.buildTitlePageRecoveryContext(options)
            const recoveredValue = this.recoverMissingDocumentTypeFromFinalProperties(context)
            if (recoveredValue === '') {
                return entries
            }

            return [
                {
                    label: 'Dokumenttyp',
                    value: recoveredValue,
                    source_label: 'Dokumenttyp',
                    normalized_label: 'dokumenttyp',
                    order: null,
                },
                ...entries,
            ]
        },
        recoverSchoolPartsFromNormalizationBlocks(blocks) {
            const lines = this.titlePageTextLinesFromNormalizationBlocks(blocks)
            if (lines.length === 0) {
                return {
                    school: '',
                    school_address: '',
                    school_city: '',
                    school_full: '',
                }
            }

            let school = ''
            let schoolAddress = ''
            let schoolCity = ''
            for (let index = 0; index < lines.length; index++) {
                const line = String(lines[index] || '').trim()
                if (!this.isLikelySchoolNameCandidate(line) || this.isArchiveNavigationNoise(line, 'school')) {
                    continue
                }

                school = line
                for (let nextIndex = index + 1; nextIndex < lines.length && nextIndex <= index + 4; nextIndex++) {
                    const next = String(lines[nextIndex] || '').trim()
                    if (next === '' || this.isArchiveNavigationNoise(next, 'other')) {
                        continue
                    }
                    if (this.isLikelyTitlePageMetadataLine(next) || this.isLocationDatePseudoTitle(next)) {
                        break
                    }
                    if (this.isLikelySchoolCityLine(next)) {
                        if (schoolCity === '') {
                            schoolCity = next
                        }
                        continue
                    }
                    if (this.isLikelySchoolAddressLine(next)) {
                        schoolAddress = schoolAddress === '' ? next : `${schoolAddress}, ${next}`
                        continue
                    }

                    if (schoolAddress === '' && schoolCity === '') {
                        break
                    }

                    break
                }

                break
            }

            const schoolFullParts = [school, schoolAddress, schoolCity].filter((part) => String(part || '').trim() !== '')

            return {
                school,
                school_address: schoolAddress,
                school_city: schoolCity,
                school_full: schoolFullParts.join(', '),
            }
        },
        recoverTitleFromNormalizationBlocks(blocks) {
            const lines = this.titlePageTextLinesFromNormalizationBlocks(blocks)
            for (const line of lines) {
                if (!this.isRecoverableTitleCandidate(line)) {
                    continue
                }

                return line
            }

            return ''
        },
        isRecoverableTitleCandidate(value) {
            const text = String(value || '').trim()
            if (text === '') {
                return false
            }
            if (this.isPlaceholderMetadataValue(text, 'title') || this.isLocationDatePseudoTitle(text)) {
                return false
            }

            const normalized = this.comparableTitlePageText(text)
            if (normalized === '') {
                return false
            }

            const looksLikeLabelLine = /(verfasst von|eingereicht von|betreuer(?:\s*\/\s*in)?|klasse|schuljahr|dokumenttyp|schule|schuladresse|schulort|datum)\s*[:\-]/iu.test(text)
            if (looksLikeLabelLine) {
                return false
            }

            if (normalized.includes('inhaltsverzeichnis') || normalized.includes('archiv') || normalized.includes('tag der offenen tur')) {
                return false
            }
            if (normalized.includes('gymnasium') || normalized.includes('schule vollstandig')) {
                return false
            }
            if (normalized.includes('abschliessende arbeit') || normalized.includes('vorwissenschaftliche arbeit')) {
                return false
            }

            if (text.includes('|') && this.titlePageUppercaseRatio(text) >= 0.5) {
                return false
            }

            return text.length >= 12
        },
        titlePageTextLinesFromNormalizationBlocks(blocks) {
            if (!Array.isArray(blocks) || blocks.length === 0) {
                return []
            }

            const unique = new Set()
            const lines = []
            const titlePageBlocks = [...blocks]
                .filter((block) => this.isTitlePageNormalizationBlock(block))
                .sort((left, right) => Number(left?.order || 0) - Number(right?.order || 0))

            const appendLine = (value) => {
                const line = String(value || '').trim()
                if (line === '') {
                    return
                }
                const key = line.toLowerCase()
                if (unique.has(key)) {
                    return
                }
                unique.add(key)
                lines.push(line)
            }

            titlePageBlocks.forEach((block) => {
                const candidates = [
                    block?.plain_text,
                    block?.text,
                    block?.content_text,
                ]
                candidates.forEach((candidate) => {
                    String(candidate || '')
                        .split(/\r?\n/gu)
                        .forEach((line) => appendLine(line))
                })
            })

            return lines
        },
        isTitlePageNormalizationBlock(block) {
            if (!block || typeof block !== 'object') {
                return false
            }

            const zone = String(block?.document_zone?.zone || block?.zone_context || block?.zone || '').trim().toLowerCase()
            return zone === 'title_page' || zone === 'titlepage'
        },
        isLikelySchoolNameCandidate(value) {
            const text = String(value || '').trim()
            if (text === '') {
                return false
            }

            const normalized = this.comparableTitlePageText(text)
            if (normalized === '') {
                return false
            }
            if (normalized.includes('inhaltsverzeichnis')) {
                return false
            }

            return /\b(gymnasium|schule|lyzeum|college|akademie|htl|hak|hblw|berufsschule)\b/iu.test(text)
        },
        isLikelySchoolAddressLine(value) {
            const text = String(value || '').trim()
            if (text === '') {
                return false
            }

            const hasStreetToken = /\b(kai|strasse|straße|gasse|platz|allee|weg|ring|ufer|promenade|street|road)\b/iu.test(text)
            const hasHouseNumber = /\d{1,4}[A-Za-z]?/u.test(text)

            return hasStreetToken && hasHouseNumber
        },
        isLikelySchoolCityLine(value) {
            return /^\s*\d{4}\s+[\p{L}][\p{L}\s\-().]{1,80}$/u.test(String(value || '').trim())
        },
        isLikelyTitlePageMetadataLine(value) {
            const text = String(value || '').trim()
            if (text === '') {
                return false
            }

            return /^(eingereicht von|verfasst von|vorgelegt von|verfasser(?:\s*\/\s*in)?|verfasser\*in|betreuer(?:\s*\/\s*in)?|betreuer\*in|klasse|schuljahr|ort,?\s*datum|datum|unterschrift|titel|thema|dokumenttyp)\b/iu.test(text)
        },
        isLikelyAuthorName(value) {
            const text = String(value || '').trim()
            if (text === '') {
                return false
            }
            if (/[0-9]/u.test(text)) {
                return false
            }
            if (this.isPlaceholderMetadataValue(text, 'author')) {
                return false
            }
            if (this.isLocationDatePseudoTitle(text)) {
                return false
            }
            if (this.isArchiveNavigationNoise(text, 'other')) {
                return false
            }

            const normalized = this.comparableTitlePageText(text)
            if (normalized === '') {
                return false
            }
            if (/(klasse|schuljahr|betreuer|dokumenttyp|schule|abgabe|datum|inhaltsverzeichnis|archiv)/u.test(normalized)) {
                return false
            }

            const parts = text.split(/\s+/u).filter((part) => part !== '')

            return parts.length >= 2 && parts.length <= 6
        },
        recoverClassToken(value) {
            const text = String(value || '').trim()
            if (text === '') {
                return ''
            }

            const match = text.match(/\b([0-9]{1,2}[A-Za-z]{1,4}[0-9]?)\b/u)
            return match ? String(match[1] || '').trim() : ''
        },
        normalizeDocumentTypeValue(value) {
            const text = String(value || '').trim()
            if (text === '') {
                return ''
            }

            const normalized = this.comparableTitlePageText(text)
            const startsWithVwa = normalized.startsWith('vorwissenschaftliche arbeit')
            const hasMergedTail = normalized.includes('verfasst von')
            if (startsWithVwa && hasMergedTail) {
                return 'Vorwissenschaftliche Arbeit'
            }
            if (!startsWithVwa && hasMergedTail) {
                return ''
            }

            return text
        },
        cleanLabelArtifactValue(value, options = {}) {
            let text = String(value || '').trim()
            if (text === '') {
                return ''
            }

            const role = String(options?.role || '').trim().toLowerCase()
            if (['advisor', 'author'].includes(role)) {
                text = text
                    .replace(/^\s*(?:betreuer(?:\s*\/\s*in)?|betreuer\*in|verfasser(?:\s*\/\s*in)?|verfasser\*in)\s*[:\-]\s*/iu, '')
                    .replace(/^\s*\/\s*in\s*[:\-]\s*/iu, '')
                    .trim()
            }

            return text
        },
        isLocationDatePseudoTitle(value) {
            const text = String(value || '').trim()
            if (text === '') {
                return false
            }

            const normalized = this.comparableTitlePageText(text)
            const hasDateFormula = normalized.includes('abgabedatum')
                || normalized.includes('abgabe datum')
                || normalized.includes('abgabe')
                || normalized.includes('datum')
            if (!hasDateFormula) {
                return false
            }

            const looksLikeLocationPrefix = /^[\p{L}][\p{L}\-.\s]{1,40},\s*/u.test(text)

            return looksLikeLocationPrefix
        },
        isArchiveNavigationNoise(value, role = 'other') {
            const text = String(value || '').trim()
            if (text === '') {
                return false
            }

            const normalized = this.comparableTitlePageText(text)
            const hasArchiveMarker = text.includes('Archiv]') || normalized.startsWith('archiv ')
            const hasOpenHouseMarker = normalized.includes('tag der offenen tur')
            if (!hasArchiveMarker && !hasOpenHouseMarker) {
                return false
            }

            return ['school', 'school_full', 'other'].includes(role)
        },
        isPlaceholderMetadataValue(value, role = 'other') {
            const text = String(value || '').trim()
            if (text === '') {
                return true
            }
            if (/^[:\-–—|,.;/\\]+$/u.test(text)) {
                return true
            }

            const normalized = this.comparableTitlePageText(text)
            if (normalized === 'inhaltsverzeichnis' && ['subtitle', 'title', 'other'].includes(role)) {
                return true
            }

            return false
        },
        isMergedMultiFieldValue(value, role = 'other') {
            const text = String(value || '').trim()
            if (text === '') {
                return false
            }
            if (role !== 'class') {
                return false
            }

            const hasClassToken = /\b\d{1,2}[a-zA-Z]{1,4}\d?\b/u.test(text)
            const hasMergedCanonicalLabels = /(schuljahr|betreuer(?:\s*\/\s*in)?|betreuer\*in|verfasser(?:\s*\/\s*in)?|datum|dokumenttyp)\s*[:\-]/iu.test(text)

            return hasClassToken && hasMergedCanonicalLabels
        },
        normalizeTitlePageAdditionalProperty(property) {
            if (!property || typeof property !== 'object') {
                return null
            }

            const role = this.titlePagePropertyRole(property)
            const normalizedRole = String(role || 'other').trim().toLowerCase()
            let value = this.cleanLabelArtifactValue(property.value, { role: normalizedRole })
            if (normalizedRole === 'document_type') {
                value = this.normalizeDocumentTypeValue(value)
            }
            if (value === '') {
                return null
            }
            if (this.isPlaceholderMetadataValue(value, normalizedRole)) {
                return null
            }
            const label = String(property.label || property.source_label || '').trim()
            const archiveCandidate = `${label} ${value}`.trim()
            if (
                this.isArchiveNavigationNoise(value, normalizedRole)
                || this.isArchiveNavigationNoise(label, normalizedRole)
                || this.isArchiveNavigationNoise(archiveCandidate, normalizedRole)
            ) {
                return null
            }
            if (normalizedRole === 'title' && this.isLocationDatePseudoTitle(value)) {
                return null
            }
            if (normalizedRole === 'class' && this.isMergedMultiFieldValue(value, normalizedRole)) {
                return null
            }

            return {
                ...property,
                value,
            }
        },
        normalizeTitlePageAdditionalProperties(properties) {
            if (!Array.isArray(properties)) {
                return []
            }

            const normalized = []
            const seen = new Set()
            properties.forEach((property, index) => {
                if (!property || typeof property !== 'object') {
                    return
                }

                const label = String(property.label || property.source_label || property.normalized_label || '').trim()
                const value = String(property.value || '').trim()
                if (label === '' || value === '') {
                    return
                }
                if (this.isTitlePageAdditionalPropertyUiMetaText(label) || this.isTitlePageAdditionalPropertyUiMetaText(value)) {
                    return
                }

                const dedupeKey = `${String(property.normalized_label || label).trim().toLowerCase()}|${value.toLowerCase()}`
                if (seen.has(dedupeKey)) {
                    return
                }
                seen.add(dedupeKey)

                normalized.push({
                    label,
                    value,
                    source_label: String(property.source_label || label).trim(),
                    normalized_label: String(property.normalized_label || '').trim() || null,
                    order: Number.isFinite(Number(property.order)) ? Number(property.order) : null,
                    _index: index,
                })
            })

            normalized.sort((left, right) => {
                const leftOrder = Number.isFinite(Number(left.order)) ? Number(left.order) : Number.MAX_SAFE_INTEGER
                const rightOrder = Number.isFinite(Number(right.order)) ? Number(right.order) : Number.MAX_SAFE_INTEGER
                if (leftOrder !== rightOrder) {
                    return leftOrder - rightOrder
                }

                return Number(left._index || 0) - Number(right._index || 0)
            })

            const filtered = this.filterTitlePageAdditionalProperties(normalized)

            return filtered.map(({ _index, ...property }) => property)
        },
        isTitlePageAdditionalPropertyUiMetaText(value) {
            return String(value || '').trim() === 'Weitere Eigenschaften'
        },
        filterTitlePageAdditionalProperties(properties) {
            if (!Array.isArray(properties) || properties.length === 0) {
                return []
            }

            const context = this.buildTitlePageAdditionalPropertyContext(properties)
            const filtered = []

            properties.forEach((property) => {
                if (!property || typeof property !== 'object') {
                    return
                }

                const normalizedProperty = this.normalizeTitlePageAdditionalProperty(property)
                if (!normalizedProperty) {
                    return
                }

                if (!this.isCanonicalTitlePagePropertyLabel(normalizedProperty) && this.isNoisyAiImageDescriptionProperty(normalizedProperty)) {
                    return
                }
                if (this.isSplitArtifactProperty(normalizedProperty, context)) {
                    return
                }
                if (this.isRedundantAgainstFullSchoolField(normalizedProperty, context)) {
                    return
                }

                filtered.push(normalizedProperty)
            })

            return filtered
        },
        buildTitlePageAdditionalPropertyContext(properties) {
            const context = {
                school_full: '',
                school: '',
                school_address: '',
            }

            if (!Array.isArray(properties)) {
                return context
            }

            properties.forEach((property) => {
                const role = this.titlePagePropertyRole(property)
                const value = String(property?.value || '').trim()
                if (value === '') {
                    return
                }

                if (role === 'school_full' && context.school_full === '') {
                    context.school_full = value
                }
                if (role === 'school' && context.school === '') {
                    context.school = value
                }
                if (role === 'school_address' && context.school_address === '') {
                    context.school_address = value
                }
            })

            return context
        },
        normalizeTitlePagePropertyLabelKey(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/gu, '')
                .replace(/ß/gu, 'ss')
                .replace(/[^a-z0-9]+/gu, '_')
                .replace(/^_+|_+$/gu, '')
        },
        titlePagePropertyRole(property) {
            const normalizedLabel = this.normalizeTitlePagePropertyLabelKey(property?.normalized_label || '')
            const label = this.normalizeTitlePagePropertyLabelKey(property?.label || property?.source_label || '')
            const keys = [normalizedLabel, label]

            if (keys.some((key) => ['titel', 'title'].includes(key))) {
                return 'title'
            }
            if (keys.some((key) => ['untertitel', 'subtitle'].includes(key))) {
                return 'subtitle'
            }
            if (keys.some((key) => ['verfasser', 'verfasser_in', 'verfasserin', 'author'].includes(key))) {
                return 'author'
            }
            if (keys.some((key) => ['betreuer', 'betreuer_in', 'betreuerin', 'advisor'].includes(key))) {
                return 'advisor'
            }
            if (keys.some((key) => ['klasse', 'class'].includes(key))) {
                return 'class'
            }
            if (keys.some((key) => ['datum', 'date'].includes(key))) {
                return 'date'
            }
            if (keys.some((key) => ['dokumenttyp', 'document_type'].includes(key))) {
                return 'document_type'
            }
            if (keys.some((key) => ['schule_vollstaendig', 'school_full'].includes(key))) {
                return 'school_full'
            }
            if (keys.some((key) => ['schuladresse', 'school_address'].includes(key))) {
                return 'school_address'
            }
            if (keys.some((key) => ['schulort', 'school_city', 'school_location'].includes(key))) {
                return 'school_city'
            }
            if (keys.some((key) => ['schule', 'school'].includes(key))) {
                return 'school'
            }

            return 'other'
        },
        isCanonicalTitlePagePropertyLabel(property) {
            return this.titlePagePropertyRole(property) !== 'other'
        },
        comparableTitlePageText(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/gu, '')
                .replace(/ß/gu, 'ss')
                .replace(/[^a-z0-9]+/gu, ' ')
                .replace(/\s+/gu, ' ')
                .trim()
        },
        comparableTitlePageContains(haystack, needle) {
            const normalizedHaystack = this.comparableTitlePageText(haystack)
            const normalizedNeedle = this.comparableTitlePageText(needle)
            if (normalizedHaystack === '' || normalizedNeedle === '') {
                return false
            }

            return normalizedHaystack.includes(normalizedNeedle)
        },
        titlePageUppercaseRatio(value) {
            const text = String(value || '')
            const letters = text.match(/\p{L}/gu) || []
            if (letters.length === 0) {
                return 0
            }
            const uppercaseLetters = letters.filter((char) => char.toUpperCase() === char && char.toLowerCase() !== char)

            return uppercaseLetters.length / letters.length
        },
        isNoisyAiImageDescription(value) {
            const normalized = this.comparableTitlePageText(value)
            if (normalized === '') {
                return false
            }

            const startsAsImageDescription = normalized.startsWith('ein bild das')
                || normalized.startsWith('ein bild')
                || normalized.includes('bild das')
            const hasContainsVerb = normalized.includes('enthalt') || normalized.includes('zeigt')
            const genericVisualTerms = ['schrift', 'grafik', 'grafiken', 'text', 'kreis', 'logo', 'symbol', 'symbole']
            const visualTermCount = genericVisualTerms.filter((term) => normalized.includes(term)).length
            const hasAiToken = normalized.includes('ki') || normalized.includes('ai')
            const hasGeneratedToken = normalized.includes('generierte inhalte')
                || normalized.includes('generiert')
                || normalized.includes('generated content')
            const hasErrorHint = normalized.includes('fehlerhaft')
                || normalized.includes('ungenau')
                || normalized.includes('unvollstandig')
            const hasAiDisclaimer = hasAiToken && hasGeneratedToken && hasErrorHint

            return hasAiDisclaimer || (startsAsImageDescription && hasContainsVerb && visualTermCount >= 2 && (hasAiToken || hasGeneratedToken))
        },
        isNoisyAiImageDescriptionProperty(property) {
            const candidates = [
                property?.value,
                property?.label,
                property?.source_label,
                `${String(property?.label || '').trim()} ${String(property?.value || '').trim()}`.trim(),
                `${String(property?.source_label || '').trim()} ${String(property?.label || '').trim()} ${String(property?.value || '').trim()}`.trim(),
            ]

            return candidates.some((candidate) => this.isNoisyAiImageDescription(candidate))
        },
        isSplitArtifactProperty(property, context = {}) {
            const label = String(property?.label || '').trim()
            const value = String(property?.value || '').trim()
            if (label === '' || value === '') {
                return false
            }
            if (this.isCanonicalTitlePagePropertyLabel(property)) {
                return false
            }

            const labelWords = label.split(/\s+/u).filter((word) => word !== '')
            const valueWords = value.split(/\s+/u).filter((word) => word !== '')
            const looksHeadlineSplit = this.titlePageUppercaseRatio(label) >= 0.6
                && labelWords.length >= 3
                && (value.includes('|') || value.includes('?'))

            const strongerValues = [context.school_full, context.school_address, context.school]
                .map((entry) => String(entry || '').trim())
                .filter((entry) => entry !== '')
            const combinedVariants = [
                `${label} ${value}`,
                `${label}-${value}`,
                `${label}: ${value}`,
            ]
            const coveredByStrongerField = strongerValues.some((strongerValue) =>
                combinedVariants.some((variant) => this.comparableTitlePageContains(strongerValue, variant))
            )

            const looksLikeFragmentPair = labelWords.length <= 3
                && valueWords.length <= 6
                && label.length <= 30
                && value.length <= 60

            return looksHeadlineSplit || (looksLikeFragmentPair && coveredByStrongerField)
        },
        isRedundantAgainstFullSchoolField(property, context = {}) {
            const fullSchool = String(context.school_full || '').trim()
            if (fullSchool === '') {
                return false
            }

            const role = this.titlePagePropertyRole(property)
            if (!['school_city', 'school_address'].includes(role)) {
                return false
            }

            const value = String(property?.value || '').trim()
            if (value === '' || !this.comparableTitlePageContains(fullSchool, value)) {
                return false
            }

            if (role === 'school_city') {
                return true
            }

            return role === 'school_address'
        },
        normalizeTitlePageLogoStatusMessage(value) {
            return String(value || '')
                .trim()
                .replace(/\s+/gu, ' ')
                .toLowerCase()
        },
        titlePageLogoAssetIdentity(asset) {
            const path = String(asset?.logo_asset_path || '').trim()
            if (path !== '') {
                const disk = String(asset?.logo_asset_disk || 'local').trim().toLowerCase() || 'local'
                return `${disk}:${path.toLowerCase()}`
            }

            const candidates = [
                asset?.asset_id,
                asset?.rel_id,
                asset?.target,
                asset?.logo_asset_filename,
                asset?.logo_extraction_filename,
                asset?.hash,
                asset?.filename,
                asset?.logo_asset_url,
            ]
            for (const candidate of candidates) {
                const normalized = String(candidate || '').trim()
                if (normalized !== '') {
                    return normalized.toLowerCase()
                }
            }

            return null
        },
        titlePageLogoSuccessDedupeKey(asset) {
            const identity = this.titlePageLogoAssetIdentity(asset)
            if (identity !== null) {
                return `asset:${identity}`
            }

            const logoType = String(asset?.logo_type || '').trim().toLowerCase()
            const successText = String(asset?.logo_ui_display_note || '').trim() !== ''
                ? `Logo-Asset verfügbar und renderbar. ${String(asset?.logo_ui_display_note || '').trim()}`
                : 'Logo-Asset verfügbar und renderbar.'

            return `fallback:${logoType}|${this.normalizeTitlePageLogoStatusMessage(successText)}`
        },
        normalizeTitlePageLogoAssets(logos, uiModel = {}) {
            const sourceLogos = Array.isArray(logos) ? logos : []
            const uiLogoAssets = Array.isArray(uiModel?.logo_assets) ? uiModel.logo_assets : []

            const normalizedAssets = sourceLogos
                .map((entry, index) => {
                    const logo = entry && typeof entry === 'object'
                        ? entry
                        : {}
                    const matchingUiAsset = uiLogoAssets.find((asset) => {
                        if (!asset || typeof asset !== 'object') {
                            return false
                        }

                        const logoIndex = Number(logo.asset_index ?? -1)
                        const uiIndex = Number(asset.asset_index ?? -2)
                        if (logoIndex >= 0 && uiIndex >= 0 && logoIndex === uiIndex) {
                            return true
                        }

                        const logoPath = String(logo.logo_asset_path || '').trim()
                        const uiPath = String(asset.logo_asset_path || '').trim()

                        return logoPath !== '' && logoPath === uiPath
                    }) || {}
                    const detected = Boolean(logo.logo_detected ?? true)
                    const assetAvailable = Boolean(logo.logo_asset_available)
                    const uiDisplayable = Boolean(logo.logo_ui_displayable)
                    const assetUrl = String(logo.logo_asset_url || matchingUiAsset.logo_asset_url || '').trim() || null
                    const altText = String(logo.logo_alt_text || matchingUiAsset.logo_alt_text || logo.logo_description || `Titelseitenbild ${index + 1}`).trim()

                    return {
                        id: this.titlePageLogoAssetNodeKey({ id: 'titlepage' }, logo, index),
                        asset_index: Number(logo.asset_index ?? matchingUiAsset.asset_index ?? index),
                        logo_detected: detected,
                        logo_description: String(logo.logo_description || matchingUiAsset.logo_description || '').trim() || null,
                        logo_position: String(logo.logo_position || matchingUiAsset.logo_position || '').trim() || null,
                        logo_type: String(logo.logo_type || '').trim() || null,
                        logo_asset_available: assetAvailable,
                        logo_ui_displayable: uiDisplayable,
                        logo_asset_path: String(logo.logo_asset_path || matchingUiAsset.logo_asset_path || '').trim() || null,
                        logo_asset_disk: String(logo.logo_asset_disk || matchingUiAsset.logo_asset_disk || 'local').trim() || 'local',
                        logo_asset_mime_type: String(logo.logo_asset_mime_type || matchingUiAsset.logo_asset_mime_type || '').trim() || null,
                        logo_asset_url: assetUrl,
                        logo_alt_text: altText !== '' ? altText : `Titelseitenbild ${index + 1}`,
                        logo_ui_display_note: String(logo.logo_ui_display_note || matchingUiAsset.logo_ui_display_note || '').trim() || null,
                    }
                })
                .sort((left, right) => Number(left.asset_index || 0) - Number(right.asset_index || 0))

            const seenSuccess = new Set()
            const deduped = []
            normalizedAssets.forEach((asset) => {
                const isSuccess = Boolean(asset.logo_detected)
                    && Boolean(asset.logo_asset_available)
                    && Boolean(asset.logo_ui_displayable)

                if (!isSuccess) {
                    deduped.push(asset)
                    return
                }

                const dedupeKey = this.titlePageLogoSuccessDedupeKey(asset)
                if (seenSuccess.has(dedupeKey)) {
                    return
                }

                seenSuccess.add(dedupeKey)
                deduped.push(asset)
            })

            return deduped
        },
        titlePageLogoNodeKey(node) {
            return String(node?.id || 'titlepage')
        },
        titlePageLogoAssetNodeKey(node, asset, index = 0) {
            const nodeKey = this.titlePageLogoNodeKey(node)
            const assetIndex = Number(asset?.asset_index ?? index)
            return `${nodeKey}-asset-${assetIndex}`
        },
        shouldShowTitlePageLogoSummary(node) {
            const assets = Array.isArray(node?.logo_assets) ? node.logo_assets : []
            const status = this.resolveTitlePageLogoStatus(node)
            return !(status === 'asset_ready' && assets.length > 0)
        },
        resolveTitlePageLogoAssetStatus(node, asset, index = 0) {
            const detected = Boolean(asset?.logo_detected)
            const assetAvailable = Boolean(asset?.logo_asset_available)
            const uiDisplayable = Boolean(asset?.logo_ui_displayable)
            const assetUrl = String(asset?.logo_asset_url || '').trim()

            if (!detected) {
                return 'no_logo_detected'
            }

            if (!assetAvailable || !uiDisplayable || assetUrl === '') {
                return 'detected_without_asset'
            }

            const key = this.titlePageLogoAssetNodeKey(node, asset, index)
            if (Boolean(this.titlePageLogoRenderErrors[key])) {
                return 'asset_render_failed'
            }

            return 'asset_ready'
        },
        resolveTitlePageLogoStatus(node) {
            const assets = Array.isArray(node?.logo_assets) ? node.logo_assets : []
            if (assets.length > 0) {
                const statuses = assets.map((asset, index) => this.resolveTitlePageLogoAssetStatus(node, asset, index))
                if (statuses.includes('asset_render_failed')) {
                    return 'asset_render_failed'
                }
                if (statuses.includes('detected_without_asset')) {
                    return 'detected_without_asset'
                }
                if (statuses.includes('asset_ready')) {
                    return 'asset_ready'
                }

                return 'no_logo_detected'
            }

            const detected = Boolean(node?.logo_detected)
            if (!detected) {
                return 'no_logo_detected'
            }

            const assetAvailable = Boolean(node?.logo_asset_available)
            const uiDisplayable = Boolean(node?.logo_ui_displayable)
            const assetUrl = String(node?.logo_asset_url || '').trim()
            if (!assetAvailable || !uiDisplayable || assetUrl === '') {
                return 'detected_without_asset'
            }

            const key = this.titlePageLogoNodeKey(node)
            if (Boolean(this.titlePageLogoRenderErrors[key])) {
                return 'asset_render_failed'
            }

            return 'asset_ready'
        },
        titlePageLogoStatusText(node) {
            const status = this.resolveTitlePageLogoStatus(node)
            const detectedCount = Number(node?.logo_detected_count || 0)
            const availableCount = Number(node?.logo_asset_available_count || 0)
            if (status === 'no_logo_detected') {
                return 'Kein Logo erkannt.'
            }
            if (status === 'detected_without_asset') {
                if (detectedCount > 1) {
                    return `${detectedCount} Logos/Bilder erkannt, aber nicht alle sind renderbar (${availableCount} Asset verfügbar).`
                }

                return 'Logo erkannt, aber kein renderbares Asset verfügbar.'
            }
            if (status === 'asset_render_failed') {
                return 'Logo-Asset vorhanden, aber Rendering fehlgeschlagen.'
            }

            return 'Logo-Asset verfügbar und renderbar.'
        },
        titlePageLogoStatusColor(node) {
            const status = this.resolveTitlePageLogoStatus(node)
            if (status === 'asset_ready') {
                return 'success'
            }
            if (status === 'asset_render_failed') {
                return 'error'
            }
            if (status === 'detected_without_asset') {
                return 'warning'
            }

            return 'info'
        },
        titlePageLogoAssetStatusText(node, asset, index = 0) {
            const status = this.resolveTitlePageLogoAssetStatus(node, asset, index)
            if (status === 'no_logo_detected') {
                return 'Kein Logo erkannt.'
            }
            if (status === 'detected_without_asset') {
                return 'Logo erkannt, aber kein renderbares Asset verfügbar.'
            }
            if (status === 'asset_render_failed') {
                return 'Logo-Asset vorhanden, aber Rendering fehlgeschlagen.'
            }

            return 'Logo-Asset verfügbar und renderbar.'
        },
        titlePageLogoAssetStatusColor(node, asset, index = 0) {
            const status = this.resolveTitlePageLogoAssetStatus(node, asset, index)
            if (status === 'asset_ready') {
                return 'success'
            }
            if (status === 'asset_render_failed') {
                return 'error'
            }
            if (status === 'detected_without_asset') {
                return 'warning'
            }

            return 'info'
        },
        onTitlePageLogoLoaded(node, asset = null, index = 0) {
            const key = asset
                ? this.titlePageLogoAssetNodeKey(node, asset, index)
                : this.titlePageLogoNodeKey(node)
            this.titlePageLogoRenderErrors = {
                ...this.titlePageLogoRenderErrors,
                [key]: false,
            }
            this.titlePageLogoRenderLoads = {
                ...this.titlePageLogoRenderLoads,
                [key]: true,
            }
        },
        onTitlePageLogoError(node, asset = null, index = 0) {
            const key = asset
                ? this.titlePageLogoAssetNodeKey(node, asset, index)
                : this.titlePageLogoNodeKey(node)
            this.titlePageLogoRenderErrors = {
                ...this.titlePageLogoRenderErrors,
                [key]: true,
            }
            this.titlePageLogoRenderLoads = {
                ...this.titlePageLogoRenderLoads,
                [key]: false,
            }
        },
        shouldShowPandocRootContent(node) {
            return !this.isPandocTitlepageNode(node)
        },
        isPandocUiContainerNode(node) {
            const rootKind = String(node?.root_kind || '').trim().toLowerCase()
            const semantic = String(node?.semantic_type || '').trim().toLowerCase()
            const type = String(node?.type || '').trim().toLowerCase()
            return rootKind === 'content' || semantic === 'ui_container' || type === 'ui_container'
        },
        isNumericSectionNumbering(numbering) {
            return /^\d+(?:\.\d+){0,8}$/u.test(String(numbering || '').trim())
        },
        pandocSectionTextWithNumbering(text, numbering) {
            const title = String(text || '').trim()
            if (title === '') {
                return ''
            }

            const normalizedNumbering = String(numbering || '').trim()
            if (!this.isNumericSectionNumbering(normalizedNumbering)) {
                return title
            }
            if (this.extractSectionNumbering(title) !== '') {
                return title
            }

            return `${normalizedNumbering}. ${title}`
        },
        pandocMainSectionNumberingLookup() {
            const lookup = {}
            const append = (nodes) => {
                if (!Array.isArray(nodes) || nodes.length === 0) {
                    return
                }

                nodes.forEach((node) => {
                    const text = String(node?.text || '').trim()
                    const numbering = String(node?.numbering || '').trim()
                    const key = this.sectionCompareKey(text)
                    if (key !== '' && this.isNumericSectionNumbering(numbering) && !lookup[key]) {
                        lookup[key] = numbering
                    }

                    append(node?.children)
                })
            }

            append(this.pandocSectionHierarchyRoots)
            return lookup
        },
        parsePandocTocNumberingEntry(rawLine) {
            const value = String(rawLine || '').trim()
            if (value === '') {
                return null
            }

            let normalized = value.replace(/^-\s*/u, '').trim()
            if (normalized === '') {
                return null
            }

            normalized = normalized
                .replace(/\s*[-–—]\s*\d{1,4}\s*$/u, '')
                .replace(/\s+\d{1,4}\s*$/u, '')
                .replace(/\s*\.{2,}\s*$/u, '')
                .trim()
            if (normalized === '') {
                return null
            }

            const numbering = this.extractSectionNumbering(normalized)
            if (!this.isNumericSectionNumbering(numbering)) {
                return null
            }

            const title = normalized
                .replace(/^(\d+(?:\.\d+){0,8})(?:\.(?=\p{L})|[.):\s]|$)\s*/u, '')
                .trim()
            if (title === '') {
                return null
            }

            const key = this.sectionCompareKey(title)
            if (key === '') {
                return null
            }

            return {
                key,
                numbering: String(numbering || '').trim(),
            }
        },
        parsePandocTocOutlineEntry(rawLine) {
            const value = String(rawLine || '')
            if (value.trim() === '') {
                return null
            }

            const leadingSpacesMatch = value.match(/^(\s*)/u)
            const leadingSpaces = leadingSpacesMatch ? leadingSpacesMatch[1].length : 0
            let depth = Math.floor(leadingSpaces / 2)
            let text = value.trim()

            if (text.startsWith('- ')) {
                text = text.slice(2).trim()
                depth += 1
            }

            text = text
                .replace(/\s*[-–—]\s*\d{1,4}\s*$/u, '')
                .replace(/\s+\d{1,4}\s*$/u, '')
                .replace(/\s*\.{2,}\s*$/u, '')
                .trim()
            if (text === '') {
                return null
            }

            const numbering = this.extractSectionNumbering(text)
            if (this.isNumericSectionNumbering(numbering)) {
                depth = Math.max(depth, this.numberingDepth(numbering) - 1)
            }

            const plainTitle = this.isNumericSectionNumbering(numbering)
                ? text.replace(/^(\d+(?:\.\d+){0,8})(?:\.(?=\p{L})|[.):\s]|$)\s*/u, '').trim()
                : text
            const key = this.sectionCompareKey(plainTitle)
            if (key === '') {
                return null
            }

            return {
                key,
                depth: Math.max(0, depth),
                numbering: this.isNumericSectionNumbering(numbering) ? String(numbering || '').trim() : '',
            }
        },
        inferPandocTocTopLevelNumbers(entries, lookup) {
            if (!Array.isArray(entries) || entries.length === 0) {
                return
            }

            const topLevelEntries = entries.filter((entry) => Number(entry?.depth || 0) === 0 && String(entry?.key || '') !== '')
            if (topLevelEntries.length === 0) {
                return
            }

            const resolvedNumbers = topLevelEntries.map((entry) => {
                const key = String(entry?.key || '')
                const fromLookup = String(lookup?.[key] || '').trim()
                if (this.isNumericSectionNumbering(fromLookup)) {
                    return Number(fromLookup.split('.')[0] || 0)
                }

                const own = String(entry?.numbering || '').trim()
                if (this.isNumericSectionNumbering(own)) {
                    return Number(own.split('.')[0] || 0)
                }

                return 0
            })

            for (let index = 0; index < topLevelEntries.length; index += 1) {
                const entry = topLevelEntries[index]
                const key = String(entry?.key || '')
                if (key === '' || this.isNumericSectionNumbering(String(lookup?.[key] || '').trim())) {
                    continue
                }
                if (resolvedNumbers[index] > 0) {
                    continue
                }

                let previousIndex = index - 1
                while (previousIndex >= 0 && resolvedNumbers[previousIndex] <= 0) {
                    previousIndex -= 1
                }

                let nextIndex = index + 1
                while (nextIndex < resolvedNumbers.length && resolvedNumbers[nextIndex] <= 0) {
                    nextIndex += 1
                }

                let inferred = 0
                if (previousIndex < 0 && nextIndex < resolvedNumbers.length) {
                    const nextValue = resolvedNumbers[nextIndex]
                    const distance = nextIndex - index
                    inferred = nextValue - distance
                } else if (previousIndex >= 0 && nextIndex >= resolvedNumbers.length) {
                    const previousValue = resolvedNumbers[previousIndex]
                    const distance = index - previousIndex
                    inferred = previousValue + distance
                } else if (previousIndex >= 0 && nextIndex < resolvedNumbers.length) {
                    const previousValue = resolvedNumbers[previousIndex]
                    const nextValue = resolvedNumbers[nextIndex]
                    const span = nextIndex - previousIndex
                    const range = nextValue - previousValue
                    if (range === span) {
                        inferred = previousValue + (index - previousIndex)
                    }
                }

                if (!Number.isFinite(inferred) || inferred < 1) {
                    continue
                }

                const inferredNumbering = String(Math.trunc(inferred))
                if (!this.isNumericSectionNumbering(inferredNumbering)) {
                    continue
                }

                lookup[key] = inferredNumbering
                resolvedNumbers[index] = Number(inferredNumbering)
            }
        },
        pandocTocSectionNumberingLookup() {
            const lookup = {}
            const tocSection = (Array.isArray(this.pandocSpecialSections) ? this.pandocSpecialSections : [])
                .find((item) => this.isPandocTocNode(item)) || null
            if (!tocSection) {
                return lookup
            }

            const outlineLines = this.normalizeProjectionLines(tocSection?.toc_outline_lines, true)
            const pageIndexLines = this.normalizeProjectionLines(tocSection?.toc_page_index_lines, true)
            const sourceLines = [...outlineLines, ...pageIndexLines]

            sourceLines.forEach((line) => {
                const entry = this.parsePandocTocNumberingEntry(line)
                if (!entry) {
                    return
                }
                if (!lookup[entry.key]) {
                    lookup[entry.key] = entry.numbering
                }
            })

            const outlineEntries = outlineLines
                .map((line) => this.parsePandocTocOutlineEntry(line))
                .filter((entry) => entry && typeof entry === 'object')
            this.inferPandocTocTopLevelNumbers(outlineEntries, lookup)

            return lookup
        },
        pandocResolvedSectionNumbering(text, explicitNumbering = '') {
            const direct = String(explicitNumbering || '').trim()
            if (this.isNumericSectionNumbering(direct)) {
                return direct
            }

            const key = this.sectionCompareKey(text)
            if (key === '') {
                return ''
            }

            const mainLookup = this.pandocMainSectionNumberingLookup()
            const mainNumbering = String(mainLookup?.[key] || '').trim()
            if (this.isNumericSectionNumbering(mainNumbering)) {
                return mainNumbering
            }

            const tocLookup = this.pandocTocSectionNumberingLookup()
            const tocNumbering = String(tocLookup?.[key] || '').trim()
            if (this.isNumericSectionNumbering(tocNumbering)) {
                return tocNumbering
            }

            return ''
        },
        pandocNormalizeTocEntryText(text) {
            const value = String(text || '').trim()
            if (value === '' || this.extractSectionNumbering(value) !== '') {
                return value
            }

            const numbering = this.pandocResolvedSectionNumbering(value)
            if (!this.isNumericSectionNumbering(numbering)) {
                return value
            }

            return this.pandocSectionTextWithNumbering(value, numbering)
        },
        pandocNodeDisplayTitle(node) {
            const baseTitle = String(node?.display_text || node?.text || '').trim()
            if (baseTitle === '') {
                return ''
            }

            if (!this.pandocNodeIsMainSection(node)) {
                return baseTitle
            }

            const numbering = this.pandocResolvedSectionNumbering(baseTitle, String(node?.numbering || ''))
            return this.pandocSectionTextWithNumbering(baseTitle, numbering)
        },
        pandocNodeTitleClasses(node) {
            if (!this.pandocNodeIsMainSection(node)) {
                return []
            }

            const level = this.normalizePandocHeadingLevel(node?.heading_level ?? node?.outline_level ?? 2)
            if (level <= 1) {
                return ['review-item__text--chapter']
            }
            if (level === 2) {
                return ['review-item__text--subchapter']
            }

            return []
        },
        pandocContentLineStyle(line) {
            const depth = Math.max(0, Math.min(7, Number(line?.depth || 0)))

            return {
                marginLeft: `${depth * 18}px`,
            }
        },
        normalizePandocContentLine(rawLine, { tocMode = false } = {}) {
            const value = String(rawLine || '')
            if (value.trim() === '') {
                return null
            }

            const leadingSpacesMatch = value.match(/^(\s*)/u)
            const leadingSpaces = leadingSpacesMatch ? leadingSpacesMatch[1].length : 0
            let depth = Math.floor(leadingSpaces / 2)
            let text = value.trim()
            let page = ''
            let kind = 'paragraph'

            if (text.startsWith('- ')) {
                text = text.slice(2).trim()
                depth += 1
                kind = tocMode ? 'toc-subentry' : 'bullet'
            }

            if (tocMode) {
                const tocMatch = text.match(/^(.*\p{L}.*?)\s+(\d{1,4})$/u)
                if (tocMatch) {
                    text = String(tocMatch[1] || '').trim()
                    page = String(tocMatch[2] || '').trim()
                }
                text = this.pandocNormalizeTocEntryText(text)

                const numbering = this.extractSectionNumbering(text)
                const numberingDepth = this.numberingDepth(numbering)
                if (numberingDepth > 0) {
                    depth = Math.max(depth, numberingDepth - 1)
                }

                if (kind !== 'toc-subentry') {
                    kind = depth > 0 ? 'toc-subentry' : 'toc-entry'
                }

                return {
                    text,
                    page,
                    depth: Math.max(0, depth),
                    kind,
                }
            }

            const numbering = this.extractSectionNumbering(text)
            const numberingDepth = this.numberingDepth(numbering)
            const keywordHeading = /^(einleitung|fazit|schluss|zusammenfassung|abstract|vorwort|literaturverzeichnis|abbildungsverzeichnis|eigenständigkeitserklärung)\b/iu.test(text)
            const looksLikeHeading = numberingDepth > 0 || keywordHeading

            if (looksLikeHeading) {
                depth = Math.max(depth, Math.max(0, numberingDepth - 1))
                kind = depth > 0 ? 'subheading' : 'heading'
            } else if (this.shouldRenderAsSubheadingCandidate(text)) {
                kind = 'subheading'
            }

            return {
                text,
                page: '',
                depth: Math.max(0, depth),
                kind,
            }
        },
        pandocNodeDisplayLines(node, maxLines = 18) {
            const tocMode = this.isPandocTocNode(node)
            const requestedLimit = Math.max(4, Number(maxLines || 18))
            const effectiveLimit = tocMode ? Math.max(requestedLimit, 120) : requestedLimit
            const lines = this.pandocNodeContentLines(node, effectiveLimit)
            if (lines.length === 0) {
                return []
            }

            return lines
                .map((line) => this.normalizePandocContentLine(line, { tocMode }))
                .filter((line) => line && String(line.text || '').trim() !== '')
                .slice(0, effectiveLimit)
        },
        pandocNodeDisplayLineLimit(node, { descendant = false } = {}) {
            const fallback = descendant ? 28 : 20
            if (this.isPandocTocNode(node)) {
                return 180
            }

            const candidates = [
                Number(node?.content_direct_line_count || 0),
                Number(node?.content_line_count || 0),
                Number(node?.content_with_children_line_count || 0),
                Number(node?.content_own_line_count || 0),
            ].filter((value) => Number.isFinite(value) && value > 0)
            const discoveredLineCount = candidates.length > 0 ? Math.max(...candidates) : 0

            if (this.pandocNodeIsMainSection(node)) {
                const limit = discoveredLineCount > 0 ? Math.max(fallback, discoveredLineCount) : fallback
                return Math.min(140, limit)
            }

            if (descendant) {
                const limit = discoveredLineCount > 0
                    ? Math.max(fallback, Math.min(discoveredLineCount, 90))
                    : fallback
                return Math.min(90, limit)
            }

            const limit = discoveredLineCount > 0
                ? Math.max(fallback, Math.min(discoveredLineCount, 70))
                : fallback
            return Math.min(90, limit)
        },
        pandocNodeSecondaryDisplayLines(node, maxLines = 40) {
            if (!this.isPandocTocNode(node)) {
                return []
            }

            const primaryKind = String(node?.toc_primary_kind || '').trim().toLowerCase()
            if (primaryKind === 'page_index') {
                return []
            }

            const lines = this.normalizeProjectionLines(node?.toc_page_index_lines, true)
            if (lines.length === 0) {
                return []
            }

            return lines
                .map((line) => this.normalizePandocContentLine(line, { tocMode: true }))
                .filter((line) => line && String(line.text || '').trim() !== '')
                .slice(0, Math.max(12, Number(maxLines || 12)))
        },
        pandocNodeDirectContentText(node) {
            const direct = String(node?.content_direct_text || '').trim()
            if (direct !== '') {
                return direct
            }

            const excerpt = String(node?.content_direct_excerpt || '').trim()
            if (excerpt !== '') {
                return excerpt
            }

            return ''
        },
        pandocNodeIsMainSection(node) {
            const semantic = String(node?.semantic_type || '').trim().toLowerCase()
            const type = String(node?.type || '').trim().toLowerCase()
            return semantic === 'chapter' || semantic === 'subchapter' || type === 'chapter' || type === 'subchapter'
        },
        pandocNodeContentText(node) {
            const direct = String(node?.content_text || '').trim()
            if (direct !== '') {
                return direct
            }

            const excerpt = String(node?.content_excerpt || '').trim()
            if (excerpt !== '') {
                return excerpt
            }

            const caption = String(node?.caption || '').trim()
            if (caption !== '') {
                return caption
            }

            return ''
        },
        pandocNodeContentWithChildrenText(node) {
            const withChildren = String(node?.content_with_children_text || '').trim()
            if (withChildren !== '') {
                return withChildren
            }

            const withChildrenExcerpt = String(node?.content_with_children_excerpt || '').trim()
            if (withChildrenExcerpt !== '') {
                return withChildrenExcerpt
            }

            return ''
        },
        pandocNodeRenderedContent(node) {
            const hasChildren = Array.isArray(node?.children) && node.children.length > 0
            const directContent = this.pandocNodeDirectContentText(node)
            if (!hasChildren) {
                return this.pandocNodeContentText(node)
            }

            if (this.pandocNodeIsMainSection(node)) {
                if (directContent !== '') {
                    return directContent
                }

                const withChildrenContent = this.pandocNodeContentWithChildrenText(node)
                if (withChildrenContent !== '') {
                    return withChildrenContent
                }

                return directContent
            }

            if (directContent !== '') {
                return directContent
            }

            return this.pandocNodeContentText(node)
        },
        pandocNodeChildSummaryLines(node, maxItems = 6) {
            const children = Array.isArray(node?.children) ? node.children : []
            if (children.length === 0) {
                return []
            }

            return children
                .slice(0, Math.max(1, Number(maxItems || 6)))
                .map((child) => {
                    const title = String(child?.display_text || child?.text || '').trim()
                    const directExcerpt = String(child?.content_direct_excerpt || '').trim()
                    const excerpt = directExcerpt !== ''
                        ? directExcerpt
                        : String(child?.content_excerpt || child?.caption || '').trim()
                    if (title === '') {
                        return ''
                    }
                    if (excerpt === '') {
                        return title
                    }

                    return `${title}: ${this.snippet(excerpt)}`
                })
                .filter((line) => line !== '')
        },
        shouldRenderAsSubheadingCandidate(text) {
            const value = String(text || '').trim()
            if (value === '') {
                return false
            }
            if (this.extractSectionNumbering(value) !== '') {
                return false
            }
            if (/[.!?:;]$/u.test(value)) {
                return false
            }
            if (value.length > 72) {
                return false
            }
            if (!/^\p{Lu}/u.test(value)) {
                return false
            }

            const words = value.split(/\s+/u).filter((word) => word !== '')
            if (words.length < 2 || words.length > 8) {
                return false
            }

            if (/\b(ich|wir|sie|er|es|du|man|dass|wird|werden|ist|sind|war|waren|habe|haben|steht|stehen|fasse|widmet|besch[aä]ftigt|zeigt)\b/iu.test(value)) {
                return false
            }
            if (/\b(im|in|am|an|auf|mit|von|fuer|für|zu|zur|zum|und|oder|den|dem|des|einer|einem|einen|eines|kapitel|abschnitt)\s*$/iu.test(value)) {
                return false
            }

            return true
        },
        shouldMergePandocFlowLines(previousLine, nextLine) {
            const previous = String(previousLine || '').trim()
            const next = String(nextLine || '').trim()
            if (previous === '' || next === '') {
                return false
            }
            if (/[.!?:;]$/u.test(previous)) {
                return false
            }
            if (/^\s*[-*•]\s+/u.test(next)) {
                return false
            }
            const nextNumbering = this.extractSectionNumbering(next)
            if (nextNumbering !== '' && !/^\d{1,3}\s+\p{Ll}/u.test(next)) {
                return false
            }
            if (this.shouldRenderAsSubheadingCandidate(previous) && /^\p{Lu}/u.test(next)) {
                return false
            }
            if (/^\p{Ll}/u.test(next)) {
                return true
            }
            if (/^\d{1,3}\b/u.test(next) && /\b(kapitel|abschnitt|teil|abbildung|seite)\s*$/iu.test(previous)) {
                return true
            }
            if (/\b(im|in|am|an|auf|mit|von|fuer|für|zu|zur|zum|und|oder|der|die|das|den|dem|des|einer|einem|einen|eines|kapitel|abschnitt|teil)\s*$/iu.test(previous)) {
                return true
            }

            return false
        },
        mergePandocFlowLines(lines) {
            if (!Array.isArray(lines) || lines.length === 0) {
                return []
            }

            const merged = []
            lines.forEach((line) => {
                const currentRaw = String(line || '').replace(/\s+$/u, '')
                const currentTrimmed = currentRaw.trim()
                if (currentTrimmed === '') {
                    return
                }

                const previousIndex = merged.length - 1
                if (previousIndex >= 0 && this.shouldMergePandocFlowLines(merged[previousIndex], currentTrimmed)) {
                    merged[previousIndex] = `${String(merged[previousIndex] || '').trimEnd()} ${currentTrimmed}`
                    return
                }

                merged.push(currentRaw)
            })

            return merged
        },
        pandocContentTextLines(text) {
            if (String(text || '').trim() === '') {
                return []
            }

            const lines = String(text || '')
                .split('\n')
                .map((line) => this.normalizeProjectionLine(line, true))
                .filter((line) => String(line || '').trim() !== '')

            return this.mergePandocFlowLines(lines)
        },
        pandocNodeContentLines(node, maxLines = 10) {
            const tocNode = this.isPandocTocNode(node)
            const hasChildren = Array.isArray(node?.children) && node.children.length > 0
            const preferDirectLines = hasChildren && this.pandocNodeIsMainSection(node)
            const maxLineCount = Math.max(1, Number(maxLines || 10))

            const directLines = this.normalizeProjectionLines(
                Array.isArray(node?.content_direct_preview_lines) ? node.content_direct_preview_lines : [],
                true
            )
            const withChildrenLines = this.normalizeProjectionLines(
                Array.isArray(node?.content_with_children_preview_lines) ? node.content_with_children_preview_lines : [],
                true
            )
            const defaultLines = this.normalizeProjectionLines(
                Array.isArray(node?.content_preview_lines) ? node.content_preview_lines : [],
                true
            )
            const directTextLines = this.pandocContentTextLines(this.pandocNodeDirectContentText(node))
            const withChildrenTextLines = this.pandocContentTextLines(this.pandocNodeContentWithChildrenText(node))
            const renderedContentLines = this.pandocContentTextLines(this.pandocNodeRenderedContent(node))
            const contentTextLines = this.pandocContentTextLines(this.pandocNodeContentText(node))

            if (tocNode) {
                if (defaultLines.length > 0) {
                    return defaultLines.slice(0, maxLineCount)
                }
                if (withChildrenLines.length > 0) {
                    return withChildrenLines.slice(0, maxLineCount)
                }
                if (renderedContentLines.length > 0) {
                    return renderedContentLines.slice(0, maxLineCount)
                }

                return []
            }

            if (preferDirectLines) {
                if (directTextLines.length > 0) {
                    return directTextLines.slice(0, maxLineCount)
                }
                if (directLines.length > 0) {
                    return directLines.slice(0, maxLineCount)
                }
                if (withChildrenTextLines.length > 0) {
                    return withChildrenTextLines.slice(0, maxLineCount)
                }
                if (withChildrenLines.length > 0) {
                    return withChildrenLines.slice(0, maxLineCount)
                }
                if (defaultLines.length > 0) {
                    return defaultLines.slice(0, maxLineCount)
                }
            }

            const preferredTextLines = renderedContentLines.length > 0
                ? renderedContentLines
                : contentTextLines
            if (defaultLines.length > 0 && preferredTextLines.length === 0) {
                return defaultLines.slice(0, maxLineCount)
            }
            if (preferredTextLines.length > 0) {
                const keepStructuredPreview = defaultLines.length > 0
                    && defaultLines.length >= preferredTextLines.length
                    && defaultLines.length >= Math.min(maxLineCount, 8)
                if (keepStructuredPreview) {
                    return defaultLines.slice(0, maxLineCount)
                }

                return preferredTextLines.slice(0, maxLineCount)
            }
            if (defaultLines.length > 0) {
                return defaultLines.slice(0, maxLineCount)
            }
            if (withChildrenLines.length > 0) {
                return withChildrenLines.slice(0, maxLineCount)
            }

            return []
        },
        pandocHierarchyIndentStyle(depth) {
            const safeDepth = Math.max(0, Math.min(6, Number(depth || 0)))

            return {
                marginLeft: `${safeDepth * 14}px`,
            }
        },
        pandocHeadingLevelLabel(level) {
            const normalized = this.normalizePandocHeadingLevel(level)
            return `H${normalized}`
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
        snippet(text) {
            const value = (text || '').trim()
            if (value.length <= 220) {
                return value
            }

            return `${value.slice(0, 220)}...`
        },
        reviewItemText(item) {
            const value = (item?.text || '').trim()
            if (value !== '') {
                return this.snippet(value)
            }

            return 'Leere Überschrift'
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
        confidenceLabelGerman(confidence) {
            if (confidence === 'high') {
                return 'hoch'
            }
            if (confidence === 'medium') {
                return 'mittel'
            }

            return 'niedrig'
        },
    },
}
</script>

<style scoped>
.aba-results-page {
    background: #0f172a;
    min-height: 100vh;
}

.aba-results-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    overflow: hidden;
}

.aba-results-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.aba-results-nav__toggles {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    padding-top: 8px;
    border-top: 1px solid rgba(148, 163, 184, 0.12);
    margin-top: 8px;
}

.aba-results-nav__progress {
    margin: 8px -10px -10px;
    width: calc(100% + 20px);
}

.aba-results-overlay__content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(30, 41, 59, 0.16);
    border-radius: 14px;
    padding: 18px 22px;
}

.aba-results-overlay__text {
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 600;
}

.advanced-analysis-dialog {
    max-height: 88vh;
}

.advanced-analysis-dialog__body {
    max-height: calc(88vh - 120px);
    overflow-y: auto;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.summary-item {
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.88);
    padding: 8px;
}

.summary-item__label {
    font-size: 0.72rem;
    color: rgba(30, 41, 59, 0.78);
}

.summary-item__value {
    font-size: 1.08rem;
    font-weight: 700;
    color: #0f172a;
}

.summary-meta {
    display: grid;
    gap: 4px;
}

.summary-meta__row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 0.84rem;
    color: rgba(15, 23, 42, 0.86);
}

.summary-meta__row strong {
    color: #0f172a;
}

.status-list {
    display: grid;
    gap: 7px;
}

.status-list__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.84rem;
    color: rgba(15, 23, 42, 0.9);
}

.status-list__right {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.stats-grid {
    display: grid;
    gap: 6px;
}

.stats-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border-bottom: 1px dashed rgba(15, 23, 42, 0.12);
    padding-bottom: 4px;
    font-size: 0.84rem;
}

.stats-item:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.stats-item strong {
    font-weight: 700;
    color: #0f172a;
}

.run-meta {
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: rgba(255, 255, 255, 0.88);
}

.run-meta__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 0.86rem;
    color: rgba(15, 23, 42, 0.9);
    margin-bottom: 4px;
}

.run-meta__row:last-child {
    margin-bottom: 0;
}

.structure-heading {
    font-size: 0.86rem;
    font-weight: 700;
    color: #0f172a;
}

.frontmatter-grid {
    display: grid;
    gap: 10px;
}

.chapter-panel-title {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #0f172a;
}

.chapter-panel-title__main {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.chapter-panel-title__meta {
    margin-left: auto;
}

.review-list {
    display: grid;
    gap: 8px;
}

.pandoc-child-panels {
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 8px;
    overflow: hidden;
}

.pandoc-child-panel-title {
    min-height: 40px !important;
    padding: 6px 12px !important;
    gap: 4px;
}

.pandoc-child-panel-title :deep(.v-expansion-panel-title__overlay),
.pandoc-child-panel-title :deep(.v-expansion-panel-title__icon) {
    flex-shrink: 0;
}

.pandoc-child-title-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pandoc-child-panel-text :deep(.v-expansion-panel-text__wrapper) {
    padding: 8px 12px 12px;
}

.review-item {
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: rgba(255, 255, 255, 0.88);
}

.review-item__chips {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
}

.review-item__text {
    font-size: 0.86rem;
    font-weight: 500;
    line-height: 1.4;
    color: rgba(15, 23, 42, 0.9);
    margin-top: 6px;
}

.review-item__text--chapter {
    font-size: 1.06rem;
    font-weight: 800;
    line-height: 1.35;
    color: rgba(15, 23, 42, 0.98);
}

.review-item__text--subchapter {
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1.35;
    color: rgba(15, 23, 42, 0.96);
}

.review-item__subtext {
    font-size: 0.76rem;
    color: rgba(15, 23, 42, 0.72);
    display: grid;
    gap: 2px;
}

/* Titelseite und Abstract teilen dieselbe Schriftquelle: --aba-document-font aus admin.css */
.detail-line {
    font-size: 0.875rem;
    font-family: var(--aba-document-font, 'Roboto', sans-serif);
}

.pandoc-root-content-block .pandoc-content-line__text {
    font-size: 0.875rem;
    font-weight: 400;
}

.pandoc-root-content-block--abstract .pandoc-content-line__text {
    font-family: var(--aba-document-font, 'Roboto', sans-serif);
}

.detail-line__label {
    font-weight: 600;
}

.pandoc-content-block {
    white-space: pre-wrap;
    line-height: 1.45;
    color: rgba(15, 23, 42, 0.82);
    display: grid;
    gap: 4px;
}

.pandoc-content-line {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    padding: 2px 0;
}

.pandoc-content-line__text {
    white-space: normal;
    word-break: break-word;
    font-size: 0.875rem;
    line-height: 1.42;
}

.pandoc-content-line__page {
    color: rgba(15, 23, 42, 0.54);
    font-variant-numeric: tabular-nums;
    min-width: 30px;
    text-align: right;
}

.pandoc-content-line--heading .pandoc-content-line__text {
    font-size: 0.96rem;
    font-weight: 760;
    color: rgba(15, 23, 42, 0.96);
}

.pandoc-content-line--subheading .pandoc-content-line__text {
    font-size: 0.9rem;
    font-weight: 700;
    color: rgba(15, 23, 42, 0.92);
}

.pandoc-content-line--paragraph .pandoc-content-line__text {
    font-size: 0.84rem;
    font-weight: 450;
    color: rgba(15, 23, 42, 0.84);
}

.pandoc-content-line--bullet .pandoc-content-line__text::before {
    content: '• ';
    color: rgba(15, 23, 42, 0.62);
}

.pandoc-content-line--toc-entry .pandoc-content-line__text {
    font-weight: 650;
    color: rgba(15, 23, 42, 0.92);
}

.pandoc-content-line--toc-subentry .pandoc-content-line__text {
    color: rgba(15, 23, 42, 0.82);
}

.order-pandoc-structure {
    order: 1;
}

.order-local-structure {
    order: 2;
}

.titlepage-logo-preview-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    max-width: 220px;
    border: 1px solid rgba(15, 118, 110, 0.25);
    border-radius: 10px;
    background: rgba(15, 118, 110, 0.03);
    padding: 8px;
}

.titlepage-logo-preview {
    display: block;
    max-width: 200px;
    max-height: 120px;
    width: auto;
    height: auto;
    object-fit: contain;
}

.titlepage-logo-grid {
    display: grid;
    gap: 10px;
}

.titlepage-logo-card {
    border: 1px dashed rgba(15, 118, 110, 0.22);
    border-radius: 10px;
    padding: 8px;
    background: rgba(15, 118, 110, 0.02);
}

@media (min-width: 1280px) {
    .results-structure-card {
        max-width: 980px;
    }
}

@media (max-width: 680px) {
    .summary-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .status-list__row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
