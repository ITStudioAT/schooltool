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

        <v-sheet rounded="xl" class="aba-results-nav mb-2" :class="{ 'is-locked': loading || refreshing }">
            <div class="aba-results-nav__buttons">
                <v-btn variant="tonal" color="primary" prepend-icon="mdi-arrow-left" :disabled="loading || refreshing" @click="goBack">
                    Zurück
                </v-btn>
                <v-btn variant="flat" color="primary" prepend-icon="mdi-refresh" :loading="refreshing" :disabled="loading" @click="refreshNow">
                    Aktualisieren
                </v-btn>
                <v-chip size="small" color="primary" variant="tonal">ABA #{{ abaId }}</v-chip>
                <v-chip v-if="analysisRun" size="small" :color="pendingRun ? 'warning' : 'success'" variant="tonal">
                    {{ analysisStatusLabel }}
                </v-chip>
            </div>
            <v-progress-linear v-if="loading || refreshing" color="primary" indeterminate class="aba-results-nav__progress" />
        </v-sheet>

        <v-alert v-if="error" type="error" variant="tonal" rounded="lg" class="mb-3">{{ error }}</v-alert>

        <v-row class="w-100 ma-0" dense>
            <v-col cols="12" lg="4">
                <ItsGridBox variant="overview" color="primary" title="Übersicht" subtitle="Persistierte Datensätze" icon="mdi-chart-box-outline">
                    <div class="summary-meta">
                        <div class="summary-meta__row"><span>Anzahl Datensätze</span><strong>{{ totalRecordCount }}</strong></div>
                        <div class="summary-meta__row"><span>Dokumenttyp</span><strong>{{ documentTypeLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Extraktionskandidat</span><strong>{{ selectedCandidateLabel }}</strong></div>
                        <div v-if="showFinalConfidence" class="summary-meta__row"><span>Finale Konfidenz</span><strong>{{ formatPercent(finalConfidence) }}</strong></div>
                        <div class="summary-meta__row"><span>Qualitätswert</span><strong>{{ formatPercent(analysisQualityScore) }}</strong></div>
                        <div class="summary-meta__row"><span>Prüfstatus</span><strong>{{ reviewStateLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Start</span><strong>{{ analysisStartLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Ende</span><strong>{{ analysisEndLabel }}</strong></div>
                        <div class="summary-meta__row"><span>Dauer</span><strong>{{ analysisDurationLabel }}</strong></div>
                    </div>
                </ItsGridBox>

                <ItsGridBox variant="overview" color="primary" title="Erkennungsstatus" subtitle="Zentrale Dokumentteile" icon="mdi-text-box-check-outline">
                    <div class="status-list">
                        <div v-for="item in structureStatusItems" :key="item.key" class="status-list__row">
                            <span>{{ item.label }}</span>
                            <div class="status-list__right">
                                <v-chip size="x-small" :color="item.detected ? 'success' : 'error'" variant="tonal">
                                    {{ item.detected ? 'Erkannt' : 'Nicht erkannt' }}
                                </v-chip>
                            </div>
                        </div>
                    </div>

                    <v-alert type="info" variant="tonal" density="compact" class="mt-3">
                        {{ abstractLanguageSummary }}
                    </v-alert>
                </ItsGridBox>

                <ItsGridBox variant="overview" color="primary" title="Datensatzstatistik" subtitle="Struktur und Typen" icon="mdi-file-tree-outline">
                    <div class="stats-grid">
                        <div v-for="item in typeStatisticItems" :key="item.key" class="stats-item">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                        </div>
                    </div>
                </ItsGridBox>

                <ItsGridBox variant="overview" color="primary" title="Qualitätsmetriken" subtitle="Lokale Analysequalität" icon="mdi-speedometer">
                    <div class="stats-grid">
                        <div v-for="item in qualityItems" :key="item.key" class="stats-item">
                            <span>{{ item.label }}</span>
                            <strong>{{ item.value }}</strong>
                        </div>
                    </div>
                </ItsGridBox>
            </v-col>

            <v-col cols="12" lg="8">
                <ItsGridBox
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
                            <div class="run-meta__row">
                                <span>Datensätze</span>
                                <strong>{{ persistedRecordCount }}</strong>
                            </div>
                        </v-sheet>

                        <div v-if="frontmatterRecords.length > 0" class="mb-4">
                            <div class="structure-heading mb-2">Datensätze am Dokumentanfang</div>
                            <v-expansion-panels multiple variant="accordion">
                                <v-expansion-panel v-for="record in frontmatterRecords" :key="`frontmatter-${record.id}`" :value="record.id">
                                    <v-expansion-panel-title>
                                        <div class="chapter-panel-title">
                                            <span>{{ frontmatterPanelLabel(record) }}</span>
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
                        </div>

                        <div>
                            <div class="structure-heading mb-2">Hauptkapitel-Datensätze</div>
                            <v-expansion-panels v-if="chapterRootRecords.length > 0" v-model="openChapterPanels" multiple variant="accordion">
                                <v-expansion-panel v-for="record in chapterRootRecords" :key="record.id" :value="record.id">
                                    <v-expansion-panel-title>
                                        <div class="chapter-panel-title">
                                            <span>{{ record.section_title || 'Ohne Titel' }}</span>
                                            <v-chip size="x-small" color="primary" variant="tonal">
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
                                Keine Hauptkapitel-Datensätze erkannt.
                            </v-alert>
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
            const timestamp = this.analysisRun?.started_at
                || this.analysisRun?.running_at
                || this.analysisRun?.created_at
                || null

            return timestamp ? this.formatDateTime(timestamp) : '-'
        },
        analysisEndLabel() {
            const timestamp = this.analysisRun?.completed_at
                || this.analysisRun?.failed_at
                || this.analysisRun?.aborted_at
                || null

            return timestamp ? this.formatDateTime(timestamp) : '-'
        },
        analysisDurationLabel() {
            const start = this.parseTimestamp(this.analysisRun?.started_at || this.analysisRun?.running_at || this.analysisRun?.created_at || null)
            if (start === null) {
                return '-'
            }

            const end = this.parseTimestamp(this.analysisRun?.completed_at || this.analysisRun?.failed_at || this.analysisRun?.aborted_at || null)
            const durationSeconds = Math.max(0, Math.floor(((end ?? new Date()).getTime() - start.getTime()) / 1000))

            return this.formatDuration(durationSeconds)
        },
        totalRecordCount() {
            return this.persistedRecordCount || this.sections.length
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
            return String(this.analysisStats.document_type || this.safeSummary?.normalized_json?.document_type || 'unbekannt')
        },
        selectedCandidateLabel() {
            return String(this.analysisStats.selected_candidate || this.safeSummary?.extraction?.selected_candidate || '-').trim() || '-'
        },
        finalConfidence() {
            return this.analysisStats.final_confidence
        },
        analysisQualityScore() {
            return this.analysisStats.analysis_quality_score
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

            return Math.abs(finalConfidence - analysisQuality) > 0.0001
        },
        reviewStateLabel() {
            const state = String(this.analysisStats.review_state || this.safeSummary?.review?.state || 'unbekannt')
            if (state === 'auto_approved') {
                return 'automatisch freigegeben'
            }
            if (state === 'review_required') {
                return 'manuelle Prüfung erforderlich'
            }

            return state
        },
        autoApproved() {
            return Boolean(this.analysisStats.auto_approved ?? this.safeSummary?.review?.auto_approved ?? false)
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
                this.makeStatusItem('title_page_detected', 'Titelseite erkannt', stats.title_page_detected, stats.title_page_start_line, stats.title_page_end_line),
                this.makeStatusItem('abstract_detected', 'Zusammenfassung erkannt', stats.abstract_detected, null, null),
                this.makeStatusItem('abstract_de_detected', 'Deutsche Zusammenfassung erkannt', stats.abstract_de_detected, stats.abstract_de_start_line, stats.abstract_de_end_line),
                this.makeStatusItem('abstract_en_detected', 'Englische Zusammenfassung erkannt', stats.abstract_en_detected, stats.abstract_en_start_line, stats.abstract_en_end_line),
                this.makeStatusItem('foreword_detected', 'Vorwort erkannt', stats.foreword_detected, stats.foreword_start_line, stats.foreword_end_line),
                this.makeStatusItem('table_of_contents_detected', 'Inhaltsverzeichnis erkannt', stats.table_of_contents_detected, stats.toc_start_line, stats.toc_end_line),
                this.makeStatusItem('bibliography_detected', 'Bibliographie erkannt', stats.bibliography_detected, stats.bibliography_start_line, stats.bibliography_end_line),
                this.makeStatusItem('figure_index_detected', 'Abbildungsverzeichnis erkannt', stats.figure_index_detected, stats.figure_index_start_line, stats.figure_index_end_line),
                this.makeStatusItem('consent_declaration_detected', 'Eigenständigkeitserklärung erkannt', stats.consent_declaration_detected, stats.consent_declaration_start_line, stats.consent_declaration_end_line),
                this.makeStatusItem('body_detected', 'Body erkannt', stats.body_detected, stats.body_start_line, null),
            ]
        },
        abstractLanguageSummary() {
            const deDetected = Boolean(this.analysisStats.abstract_de_detected)
            const enDetected = Boolean(this.analysisStats.abstract_en_detected)

            if (deDetected && enDetected) {
                return 'Deutsche und englische Zusammenfassung wurden erkannt.'
            }

            if (deDetected && !enDetected) {
                return 'Deutsche Zusammenfassung erkannt, englische Zusammenfassung fehlt.'
            }

            if (!deDetected && enDetected) {
                return 'Englische Zusammenfassung erkannt, deutsche Zusammenfassung fehlt.'
            }

            return 'Keine verlässliche Zusammenfassung erkannt.'
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
        activeSection() {
            return {
                label: 'Analyse-Ergebnisse',
                icon: 'mdi-poll',
                note: this.aba?.title ? `${this.aba.title}` : 'Lokale Analyseausgabe',
            }
        },
        headerChips() {
            return [
                {
                    key: 'datensaetze',
                    text: `${this.persistedRecordCount} Datensätze`,
                    icon: 'mdi-database-outline',
                },
                {
                    key: 'review',
                    text: `Prüfstatus: ${this.reviewStateLabel}`,
                    icon: 'mdi-shield-check-outline',
                },
            ]
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
        formatPercent(value) {
            const numeric = Number(value)
            if (!Number.isFinite(numeric)) {
                return '-'
            }

            return `${(numeric * 100).toFixed(1)} %`
        },
        makeStatusItem(key, label, detected) {
            return {
                key,
                label,
                detected: Boolean(detected),
            }
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

.aba-results-nav__progress {
    margin: 8px -10px -10px;
    width: calc(100% + 20px);
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
    justify-content: space-between;
    gap: 8px;
    font-weight: 600;
    color: #0f172a;
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
