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
                            </div>

                            <div class="block-item__text">
                                {{ snippet(block.plain_text || block.text || '') || 'Kein Textinhalt' }}
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
            ]
        },

        summary() {
            return this.result?.summary ?? {
                normalized_block_count: 0,
                heading_count: 0,
                image_count: 0,
                section_hint_count: 0,
                uncertain_or_heuristic_count: 0,
            }
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
                    return confidence === 'low' || strategy === 'heuristic'
                })
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
            return `${sectionType}${confidence}`
        },

        reasonLabel(block) {
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

        confidenceColor(block) {
            const confidence = block?.classification?.confidence
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
