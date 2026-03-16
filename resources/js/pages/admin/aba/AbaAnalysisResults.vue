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

            <v-col cols="12" :lg="leftColumnVisible ? 8 : 12">
                <ItsGridBox
                    v-if="visibleCards.kapitel"
                    class="results-structure-card"
                    variant="overview"
                    color="primary"
                    title="Erkannte Kapitel / Abschnitte"
                    subtitle="Nur Hauptkapitel als Collapsables, Unterstruktur innerhalb des Hauptkapitels"
                    icon="mdi-file-document-multiple-outline">
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
            </v-col>
        </v-row>
    </v-container>

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
            visibleCards: {
                uebersicht: true,
                erkennungsstatus: true,
                datensatzstatistik: true,
                qualitaetsmetriken: true,
                kapitel: true,
            },
            error: '',
            payload: null,
            pollTimer: null,
            pollInFlight: false,
            openChapterPanels: [],
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
                { key: 'kapitel', label: 'Erkannte Kapitel / Abschnitte' },
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
