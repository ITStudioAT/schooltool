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
            </v-row>

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
            copySnackbar: {
                visible: false,
                message: '',
                color: 'success',
            },
            activeFilter: 'all',
            showAllBlocks: false,
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
                counts: {},
            }
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
