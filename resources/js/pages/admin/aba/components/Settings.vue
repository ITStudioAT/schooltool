<template>
    <v-col cols="12">
        <v-row dense>
            <v-col cols="12" class="d-flex justify-center pa-8" v-if="isLoading">
                <v-progress-circular indeterminate color="primary" />
            </v-col>

            <v-col cols="12" v-else-if="loadError">
                <v-alert type="error" variant="tonal" rounded="lg">{{ loadError }}</v-alert>
            </v-col>

            <template v-else-if="settings">
                <!-- Analyse-Einstellungen -->
                <v-col cols="12" md="6">
                    <ItsGridBox variant="overview" icon="mdi-brain">
                        <template #title>Analyse</template>
                        <div class="pa-4">
                            <div class="settings-list">
                                <div class="settings-item">
                                    <div class="settings-item__label">OpenAI-Normalisierung</div>
                                    <div class="settings-item__value">
                                        <v-chip size="small" :color="settings.analysis.openai_normalization_enabled ? 'success' : 'grey'" variant="tonal">
                                            <v-icon start size="14">{{ settings.analysis.openai_normalization_enabled ? 'mdi-check-circle-outline' : 'mdi-close-circle-outline' }}</v-icon>
                                            {{ settings.analysis.openai_normalization_enabled ? 'Aktiv' : 'Inaktiv' }}
                                        </v-chip>
                                    </div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">OpenAI-Modell</div>
                                    <div class="settings-item__value">
                                        <v-chip size="small" color="blue" variant="tonal" v-if="settings.analysis.openai_model">{{ settings.analysis.openai_model }}</v-chip>
                                        <span v-else class="text-medium-emphasis">Nicht konfiguriert</span>
                                    </div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">Automatische Freigabe ab</div>
                                    <div class="settings-item__value">
                                        <v-chip size="small" color="primary" variant="tonal">{{ Math.round(settings.analysis.auto_approve_confidence * 100) }}% Konfidenz</v-chip>
                                    </div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">Max. Blocklänge</div>
                                    <div class="settings-item__value">{{ formatNumber(settings.analysis.max_block_text_length) }} Zeichen</div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">Max. Abschnittslänge</div>
                                    <div class="settings-item__value">{{ formatNumber(settings.analysis.max_section_text_length) }} Zeichen</div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">Debug-Protokoll</div>
                                    <div class="settings-item__value">
                                        <v-chip size="small" :color="settings.analysis.debug_log_enabled ? 'orange' : 'grey'" variant="tonal">
                                            {{ settings.analysis.debug_log_enabled ? 'Aktiv' : 'Inaktiv' }}
                                        </v-chip>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>

                <!-- Pandoc-Einstellungen -->
                <v-col cols="12" md="6">
                    <ItsGridBox variant="overview" icon="mdi-file-word-outline">
                        <template #title>Dokumentverarbeitung (Pandoc)</template>
                        <div class="pa-4">
                            <div class="settings-list">
                                <div class="settings-item">
                                    <div class="settings-item__label">Status</div>
                                    <div class="settings-item__value">
                                        <v-chip size="small" :color="settings.pandoc.enabled ? 'success' : 'error'" variant="tonal">
                                            <v-icon start size="14">{{ settings.pandoc.enabled ? 'mdi-check-circle-outline' : 'mdi-close-circle-outline' }}</v-icon>
                                            {{ settings.pandoc.enabled ? 'Aktiv' : 'Deaktiviert' }}
                                        </v-chip>
                                    </div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">Programm</div>
                                    <div class="settings-item__value">
                                        <code class="settings-code">{{ settings.pandoc.binary }}</code>
                                    </div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">Zeitlimit</div>
                                    <div class="settings-item__value">{{ settings.pandoc.timeout_seconds }} Sekunden</div>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>

                <!-- Regelwerk -->
                <v-col cols="12" md="6">
                    <ItsGridBox variant="overview" icon="mdi-book-cog-outline">
                        <template #title>Regelwerk</template>
                        <div class="pa-4">
                            <div class="settings-list">
                                <div class="settings-item">
                                    <div class="settings-item__label">Version</div>
                                    <div class="settings-item__value">
                                        <v-chip size="small" color="blue" variant="tonal">{{ settings.document_rules.version || '–' }}</v-chip>
                                    </div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">Bereich</div>
                                    <div class="settings-item__value">
                                        <v-chip size="small" color="primary" variant="tonal">{{ settings.document_rules.domain || '–' }}</v-chip>
                                    </div>
                                </div>
                                <div class="settings-item">
                                    <div class="settings-item__label">Schultyp</div>
                                    <div class="settings-item__value">{{ settings.document_rules.school_type || '–' }}</div>
                                </div>
                                <div class="settings-item" v-if="settings.document_rules.excluded_school_types?.length">
                                    <div class="settings-item__label">Ausgenommen</div>
                                    <div class="settings-item__value">
                                        <v-chip v-for="st in settings.document_rules.excluded_school_types" :key="st" size="x-small" color="grey" variant="tonal" class="mr-1">{{ st }}</v-chip>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>

                <!-- Regelstatistik -->
                <v-col cols="12" md="6">
                    <ItsGridBox variant="overview" icon="mdi-chart-bar">
                        <template #title>Aktive Regeln</template>
                        <div class="pa-4">
                            <div class="rule-stats">
                                <div class="rule-stat" v-for="stat in ruleStats" :key="stat.label">
                                    <div class="rule-stat__count">{{ stat.count }}</div>
                                    <div class="rule-stat__label">{{ stat.label }}</div>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
            </template>
        </v-row>

        <div class="text-caption text-disabled mt-3 px-1">
            Diese Einstellungen werden serverseitig konfiguriert. Wenden Sie sich an den Administrator, um Änderungen vorzunehmen.
        </div>
    </v-col>
</template>

<script>
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    name: 'AbaSettings',
    components: { ItsGridBox },

    async mounted() {
        await this.loadSettings()
    },

    data() {
        return {
            isLoading: false,
            loadError: null,
            settings: null,
        }
    },

    computed: {
        ruleStats() {
            if (!this.settings?.document_rules) return []
            const dr = this.settings.document_rules
            return [
                { label: 'Struktur-Abschnitte', count: dr.structure_sections_count },
                { label: 'Dokumentzonen', count: dr.document_zones_count },
                { label: 'Reihenfolge', count: dr.sequence_rules_count },
                { label: 'Formale Regeln', count: dr.formal_rules_count },
                { label: 'Sprachregeln', count: dr.language_rules_count },
                { label: 'Zitierregeln', count: dr.citation_rules_count },
                { label: 'Governance', count: dr.governance_rules_count },
            ]
        },
    },

    methods: {
        async loadSettings() {
            this.isLoading = true
            this.loadError = null
            try {
                const response = await axios.get('/api/admin/aba/settings')
                this.settings = response.data
            } catch (error) {
                this.loadError = error.response?.data?.message || 'Einstellungen konnten nicht geladen werden.'
            } finally {
                this.isLoading = false
            }
        },

        formatNumber(value) {
            if (typeof value !== 'number') return '–'
            return value.toLocaleString('de-AT')
        },
    },
}
</script>

<style scoped>
.settings-list {
    display: flex;
    flex-direction: column;
}

.settings-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 11px 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}

.settings-item:last-child {
    border-bottom: none;
}

.settings-item__label {
    font-size: 0.84rem;
    color: #475569;
    font-weight: 500;
}

.settings-item__value {
    font-size: 0.84rem;
    color: #0f172a;
    font-weight: 600;
    text-align: right;
}

.settings-code {
    font-size: 0.78rem;
    background: rgba(15, 23, 42, 0.06);
    padding: 3px 8px;
    border-radius: 6px;
    color: #334155;
    font-family: 'Consolas', 'Monaco', monospace;
}

.rule-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
}

.rule-stat {
    text-align: center;
    padding: 14px 8px;
    border-radius: 12px;
    background: rgba(59, 130, 246, 0.05);
    border: 1px solid rgba(59, 130, 246, 0.1);
    transition: transform 0.15s ease;
}

.rule-stat:hover {
    transform: translateY(-1px);
}

.rule-stat__count {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1d4ed8;
    line-height: 1;
    margin-bottom: 4px;
}

.rule-stat__label {
    font-size: 0.7rem;
    color: #64748b;
    font-weight: 500;
    line-height: 1.3;
}
</style>
