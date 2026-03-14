<template>
    <v-container fluid class="aba-ai-settings-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern"
            title="ABA"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#1d4ed8"
            right-orb-color="#93c5fd" />

        <!-- Navigation -->
        <v-sheet rounded="xl" class="aba-nav mb-2">
            <div class="aba-nav__buttons">
                <v-btn
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="aba-nav__button"
                    @click="$router.push('/admin/aba')">
                    <v-icon size="18" icon="mdi-view-dashboard-outline" class="mr-2" />
                    <span class="aba-nav__button-copy">
                        <span class="aba-nav__button-title">Überblick</span>
                        <span class="aba-nav__button-meta">Meine ABAs</span>
                    </span>
                </v-btn>
                <v-btn rounded="xl" color="primary" variant="flat" class="aba-nav__button">
                    <v-icon size="18" icon="mdi-brain" class="mr-2" />
                    <span class="aba-nav__button-copy">
                        <span class="aba-nav__button-title">KI-Einstellungen</span>
                        <span class="aba-nav__button-meta">Beta</span>
                    </span>
                </v-btn>
                <v-btn
                    rounded="xl"
                    color="teal"
                    variant="tonal"
                    class="aba-nav__button"
                    @click="$router.push('/admin/aba/ai-settings/seed-report')">
                    <v-icon size="18" icon="mdi-file-document-edit-outline" class="mr-2" />
                    <span class="aba-nav__button-copy">
                        <span class="aba-nav__button-title">Seed-Report</span>
                        <span class="aba-nav__button-meta">Hauptdatei</span>
                    </span>
                </v-btn>
                <v-spacer />
                <v-btn
                    size="small"
                    variant="outlined"
                    color="white"
                    prepend-icon="mdi-refresh"
                    :loading="isLoading"
                    @click="loadData">
                    Aktualisieren
                </v-btn>
            </div>
        </v-sheet>

        <!-- Zugriff verweigert -->
        <v-row v-if="accessDenied" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="error" variant="tonal" rounded="lg">
                    Zugriff nicht erlaubt. Diese Seite ist nur für Administratoren zugänglich.
                </v-alert>
            </v-col>
        </v-row>

        <!-- Ladestate -->
        <v-row v-else-if="isLoading && !pageData" class="w-100 ma-0" dense>
            <v-col cols="12" class="d-flex justify-center pa-8">
                <v-progress-circular indeterminate color="primary" />
            </v-col>
        </v-row>

        <!-- Ladefehler -->
        <v-row v-else-if="loadError" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="error" variant="tonal" rounded="lg">
                    {{ loadError }}
                </v-alert>
            </v-col>
        </v-row>

        <!-- Hauptinhalt -->
        <template v-else-if="pageData">
            <v-row class="w-100 ma-0" dense>
                <!-- Linke Spalte: Seed-Datei Meta + Aktionen + Hinweise -->
                <v-col cols="12" lg="5">
                    <!-- Seed-Datei Metadaten -->
                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-file-document-outline</v-icon>
                            Seed-Datei
                        </template>
                        <div class="pa-3">
                            <v-alert
                                v-if="seedReport.error"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="mb-3 text-caption">
                                {{ seedReport.error }}
                            </v-alert>
                            <div class="seed-meta-grid">
                                <div class="seed-meta-row">
                                    <span class="seed-meta-label">Datei</span>
                                    <span class="seed-meta-value seed-meta-mono">{{ seedReport.path }}</span>
                                </div>
                                <div v-if="seedMeta.title" class="seed-meta-row">
                                    <span class="seed-meta-label">Titel</span>
                                    <span class="seed-meta-value">{{ seedMeta.title }}</span>
                                </div>
                                <div class="seed-meta-row">
                                    <span class="seed-meta-label">Version</span>
                                    <span class="seed-meta-value">
                                        <v-chip size="x-small" color="blue" variant="tonal">
                                            {{ seedMeta.version || '–' }}
                                        </v-chip>
                                    </span>
                                </div>
                                <div class="seed-meta-row">
                                    <span class="seed-meta-label">Status</span>
                                    <span class="seed-meta-value">
                                        <v-chip
                                            size="x-small"
                                            :color="seedMeta.status === 'active' ? 'green' : 'grey'"
                                            variant="tonal">
                                            {{ seedMeta.status || 'unbekannt' }}
                                        </v-chip>
                                    </span>
                                </div>
                                <div class="seed-meta-row">
                                    <span class="seed-meta-label">Letztes Review</span>
                                    <span class="seed-meta-value">{{ seedMeta.last_reviewed_at || '–' }}</span>
                                </div>
                                <div class="seed-meta-row">
                                    <span class="seed-meta-label">Nächstes Review</span>
                                    <span class="seed-meta-value">
                                        <v-chip
                                            v-if="seedMeta.next_review_due_at"
                                            size="x-small"
                                            :color="isReviewOverdue ? 'error' : 'primary'"
                                            variant="tonal">
                                            {{ seedMeta.next_review_due_at }}
                                        </v-chip>
                                        <span v-else>–</span>
                                    </span>
                                </div>
                                <div v-if="seedReport.modified_at" class="seed-meta-row">
                                    <span class="seed-meta-label">Datei geändert</span>
                                    <span class="seed-meta-value">{{ seedReport.modified_at }}</span>
                                </div>
                                <div v-if="seedReport.size_bytes" class="seed-meta-row">
                                    <span class="seed-meta-label">Dateigröße</span>
                                    <span class="seed-meta-value">{{ formatBytes(seedReport.size_bytes) }}</span>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>

                    <!-- Aktionen -->
                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-cog-play-outline</v-icon>
                            Aktionen
                        </template>
                        <div class="pa-3">
                            <div class="d-flex flex-column ga-2">
                                <div>
                                    <v-btn
                                        block
                                        variant="tonal"
                                        color="blue"
                                        :loading="actionLoading.freshness"
                                        prepend-icon="mdi-magnify-scan"
                                        @click="runCheckFreshness">
                                        Freshness Check
                                    </v-btn>
                                    <transition name="fade-down">
                                        <div v-if="actionResults.freshness" class="action-result mt-2">
                                            <v-alert
                                                :type="actionResults.freshness.success ? 'success' : 'error'"
                                                variant="tonal"
                                                density="compact"
                                                class="text-caption">
                                                <template v-if="actionResults.freshness.success">
                                                    {{ actionResults.freshness.report.total_claims }} Claims ·
                                                    {{ actionResults.freshness.report.verified_count }} ✓ ·
                                                    {{ actionResults.freshness.report.needs_review_count }} ⚠ ·
                                                    {{ actionResults.freshness.report.stale_count }} ✗ stale
                                                </template>
                                                <template v-else>{{ actionResults.freshness.error }}</template>
                                            </v-alert>
                                        </div>
                                    </transition>
                                </div>

                                <div>
                                    <v-btn
                                        block
                                        variant="tonal"
                                        color="teal"
                                        :loading="actionLoading.onlineFreshness"
                                        prepend-icon="mdi-web-check"
                                        @click="runCheckFreshnessOnline">
                                        Online-Check (ahs-aba.at)
                                    </v-btn>
                                    <transition name="fade-down">
                                        <div v-if="actionResults.onlineFreshness" class="action-result mt-2">
                                            <v-alert
                                                :type="actionResults.onlineFreshness.success ? 'success' : 'error'"
                                                variant="tonal"
                                                density="compact"
                                                class="text-caption">
                                                <template v-if="actionResults.onlineFreshness.success">
                                                    {{ actionResults.onlineFreshness.result.sources_checked }} geprüft ·
                                                    {{ actionResults.onlineFreshness.result.sources_reachable }} erreichbar ·
                                                    <span :class="actionResults.onlineFreshness.result.sources_with_changes > 0 ? 'text-warning' : ''">
                                                        {{ actionResults.onlineFreshness.result.sources_with_changes }} Änderung(en)
                                                    </span>
                                                </template>
                                                <template v-else>{{ actionResults.onlineFreshness.error }}</template>
                                            </v-alert>
                                        </div>
                                    </transition>
                                </div>

                                <div>
                                    <v-btn
                                        block
                                        variant="tonal"
                                        color="orange"
                                        :loading="actionLoading.proposal"
                                        prepend-icon="mdi-file-document-edit-outline"
                                        @click="runProposeUpdate">
                                        Update-Proposal erzeugen
                                    </v-btn>
                                    <transition name="fade-down">
                                        <div v-if="actionResults.proposal" class="action-result mt-2">
                                            <v-alert
                                                :type="actionResults.proposal.success ? 'success' : 'error'"
                                                variant="tonal"
                                                density="compact"
                                                class="text-caption">
                                                <template v-if="actionResults.proposal.success">
                                                    Erzeugt: <code>{{ actionResults.proposal.proposal_filename }}</code>
                                                    ({{ actionResults.proposal.report.needs_review_count + actionResults.proposal.report.stale_count }} Claims enthalten)
                                                </template>
                                                <template v-else>{{ actionResults.proposal.error }}</template>
                                            </v-alert>
                                        </div>
                                    </transition>
                                </div>

                                <div>
                                    <v-btn
                                        block
                                        variant="tonal"
                                        color="green"
                                        :loading="actionLoading.rebuild"
                                        prepend-icon="mdi-database-refresh-outline"
                                        @click="runRebuild">
                                        Knowledge Base neu aufbauen
                                    </v-btn>
                                    <transition name="fade-down">
                                        <div v-if="actionResults.rebuild" class="action-result mt-2">
                                            <v-alert
                                                :type="actionResults.rebuild.success ? 'success' : 'error'"
                                                variant="tonal"
                                                density="compact"
                                                class="text-caption">
                                                <template v-if="actionResults.rebuild.success">
                                                    Rebuild abgeschlossen ·
                                                    {{ actionResults.rebuild.result.claims_count }} Claims ·
                                                    {{ actionResults.rebuild.result.chunks_count }} Chunks ·
                                                    {{ actionResults.rebuild.result.files_written_count }} Dateien
                                                </template>
                                                <template v-else>{{ actionResults.rebuild.error }}</template>
                                            </v-alert>
                                        </div>
                                    </transition>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>

                    <!-- Hinweisbereich -->
                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-information-outline</v-icon>
                            System-Hinweise
                        </template>
                        <div class="pa-3">
                            <div class="text-caption text-medium-emphasis d-flex flex-column ga-2">
                                <div>
                                    <v-chip size="x-small" color="green" variant="tonal" class="mr-1">Aktiv</v-chip>
                                    Freshness-Check, Online-Check, Proposal-Erzeugung und Rebuild voll implementiert.
                                </div>
                                <div>
                                    <v-chip size="x-small" color="teal" variant="tonal" class="mr-1">Online</v-chip>
                                    Online-Check prüft ahs-aba.at per HTTP und speichert Hash-Vergleich.
                                </div>
                                <div>
                                    <v-chip size="x-small" color="orange" variant="tonal" class="mr-1">TODO</v-chip>
                                    Claim-Inhalte müssen manuell in der Seed-Datei gepflegt werden.
                                </div>
                                <div>
                                    <v-chip size="x-small" color="blue" variant="tonal" class="mr-1">Policy</v-chip>
                                    <code>ai/knowledge/aba/prompts/seed-refresh-policy.md</code>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>

                <!-- Rechte Spalte: Refresh-Status + Vorschau -->
                <v-col cols="12" lg="7">
                    <!-- Refresh-Status -->
                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-shield-check-outline</v-icon>
                            Refresh-Status
                        </template>
                        <div class="pa-3">
                            <v-alert
                                v-if="reviewState.error"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="mb-3 text-caption">
                                {{ reviewState.error }}
                            </v-alert>

                            <div class="status-chips mb-3">
                                <v-chip size="small" color="grey" variant="outlined">
                                    <v-icon start size="14">mdi-database-outline</v-icon>
                                    {{ reviewState.total_claims }} Claims
                                </v-chip>
                                <v-chip size="small" color="green" variant="tonal">
                                    <v-icon start size="14">mdi-check-circle-outline</v-icon>
                                    {{ statusCount('verified') }} verifiziert
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="statusCount('needs_review') > 0 ? 'orange' : 'grey'"
                                    variant="tonal">
                                    <v-icon start size="14">mdi-alert-circle-outline</v-icon>
                                    {{ statusCount('needs_review') }} needs review
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="statusCount('stale') > 0 ? 'error' : 'grey'"
                                    variant="tonal">
                                    <v-icon start size="14">mdi-clock-alert-outline</v-icon>
                                    {{ statusCount('stale') }} stale
                                </v-chip>
                            </div>

                            <div class="seed-meta-grid mb-3">
                                <div class="seed-meta-row">
                                    <span class="seed-meta-label">Letzter Rebuild</span>
                                    <span class="seed-meta-value">{{ reviewState.last_rebuilt_at || '–' }}</span>
                                </div>
                            </div>

                            <div v-if="reviewState.high_risk_claims?.length">
                                <div class="text-caption text-medium-emphasis mb-1">
                                    High-Risk Claims (noch nicht verifiziert):
                                </div>
                                <v-chip
                                    v-for="claim in reviewState.high_risk_claims"
                                    :key="claim.claim_key"
                                    size="x-small"
                                    color="error"
                                    variant="tonal"
                                    class="mr-1 mb-1">
                                    {{ claim.claim_key }}
                                </v-chip>
                            </div>

                            <v-divider class="my-3" />

                            <div class="text-caption text-medium-emphasis mb-2">
                                <v-icon size="14" class="mr-1">mdi-book-open-outline</v-icon>
                                Quellenregister ({{ sourceRegistry.total_sources }} Quellen)
                                <v-chip
                                    v-if="sourceRegistry.due_for_refresh_count > 0"
                                    size="x-small"
                                    color="orange"
                                    variant="tonal"
                                    class="ml-1">
                                    {{ sourceRegistry.due_for_refresh_count }} fällig
                                </v-chip>
                            </div>
                            <div class="d-flex flex-column ga-1">
                                <div
                                    v-for="source in sourceRegistry.sources"
                                    :key="source.source_id"
                                    class="source-row">
                                    <div class="source-row__id">
                                        <v-chip size="x-small" :color="sourceTypeColor(source.type)" variant="tonal">
                                            {{ source.source_id }}
                                        </v-chip>
                                    </div>
                                    <div class="source-row__title text-caption text-medium-emphasis">
                                        {{ source.title }}
                                    </div>
                                    <div class="source-row__meta">
                                        <v-chip v-if="source.due_for_refresh" size="x-small" color="orange" variant="tonal">
                                            fällig
                                        </v-chip>
                                        <span v-else class="text-caption text-disabled">fällig {{ source.due_at }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>

                    <!-- Seed-Datei Vorschau -->
                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-text-box-outline</v-icon>
                            Inhalt der Seed-Datei (Vorschau)
                        </template>
                        <div class="pa-3">
                            <v-alert
                                v-if="!seedReport.found"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="text-caption">
                                Seed-Datei nicht gefunden.
                            </v-alert>
                            <div v-else class="seed-preview">
                                <pre class="seed-preview__text">{{ seedReport.preview }}</pre>
                                <div class="seed-preview__fade" />
                                <div class="text-caption text-disabled mt-1 text-right">
                                    Vorschau (erste 1.200 Zeichen) · Datei: <code>{{ seedReport.path }}</code>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>
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
        await this.loadData()
    },

    data() {
        return {
            adminStore: null,
            isLoading: false,
            loadError: null,
            accessDenied: false,
            pageData: null,
            actionLoading: { freshness: false, onlineFreshness: false, proposal: false, rebuild: false },
            actionResults: { freshness: null, onlineFreshness: null, proposal: null, rebuild: null },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule'
        },
        selectedRoleLabel() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            return roles.slice(0, 2).join(' / ') || 'Keine Rolle'
        },
        headerChips() {
            return [
                { key: 'school', text: this.selectedSchoolLabel, icon: 'mdi-domain' },
                { key: 'role', text: this.selectedRoleLabel, icon: 'mdi-shield-account' },
            ]
        },
        activeSection() {
            return { label: 'KI-Einstellungen', icon: 'mdi-brain', note: 'Beta – Knowledge-Governance & Seed-Refresh.' }
        },
        seedReport() {
            return this.pageData?.seed_report ?? { found: false, path: '–', meta: {}, preview: '', error: null, size_bytes: null, modified_at: null }
        },
        seedMeta() {
            return this.seedReport?.meta ?? {}
        },
        reviewState() {
            return this.pageData?.review_state ?? { total_claims: 0, by_status: {}, high_risk_claims: [], last_rebuilt_at: null, error: null }
        },
        sourceRegistry() {
            return this.pageData?.source_registry ?? { total_sources: 0, sources: [], due_for_refresh_count: 0, error: null }
        },
        isReviewOverdue() {
            const due = this.seedMeta?.next_review_due_at
            return due ? new Date(due) < new Date() : false
        },
    },

    methods: {
        async loadData() {
            this.isLoading = true
            this.loadError = null
            this.accessDenied = false
            try {
                const response = await axios.get('/api/admin/aba/ai-settings')
                this.pageData = response.data
            } catch (error) {
                if (error.response?.status === 403) {
                    this.accessDenied = true
                } else {
                    this.loadError = error.response?.data?.message || 'Daten konnten nicht geladen werden.'
                }
            } finally {
                this.isLoading = false
            }
        },

        async runCheckFreshness() {
            this.actionLoading.freshness = true
            this.actionResults.freshness = null
            try {
                const response = await axios.post('/api/admin/aba/ai-settings/check-freshness')
                this.actionResults.freshness = response.data
                await this.loadData()
            } catch (error) {
                this.actionResults.freshness = { success: false, error: error.response?.data?.error || 'Freshness-Check fehlgeschlagen.' }
            } finally {
                this.actionLoading.freshness = false
            }
        },

        async runCheckFreshnessOnline() {
            this.actionLoading.onlineFreshness = true
            this.actionResults.onlineFreshness = null
            try {
                const response = await axios.post('/api/admin/aba/ai-settings/check-freshness-online')
                this.actionResults.onlineFreshness = response.data
                await this.loadData()
            } catch (error) {
                this.actionResults.onlineFreshness = { success: false, error: error.response?.data?.error || 'Online-Check fehlgeschlagen.' }
            } finally {
                this.actionLoading.onlineFreshness = false
            }
        },

        async runProposeUpdate() {
            this.actionLoading.proposal = true
            this.actionResults.proposal = null
            try {
                const response = await axios.post('/api/admin/aba/ai-settings/propose-update')
                this.actionResults.proposal = response.data
            } catch (error) {
                this.actionResults.proposal = { success: false, error: error.response?.data?.error || 'Proposal-Erzeugung fehlgeschlagen.' }
            } finally {
                this.actionLoading.proposal = false
            }
        },

        async runRebuild() {
            this.actionLoading.rebuild = true
            this.actionResults.rebuild = null
            try {
                const response = await axios.post('/api/admin/aba/ai-settings/rebuild')
                this.actionResults.rebuild = response.data
                await this.loadData()
            } catch (error) {
                this.actionResults.rebuild = { success: false, error: error.response?.data?.error || 'Rebuild fehlgeschlagen.' }
            } finally {
                this.actionLoading.rebuild = false
            }
        },

        statusCount(status) {
            return this.reviewState?.by_status?.[status] ?? 0
        },

        sourceTypeColor(type) {
            const map = { legal: 'red', ministry_guidance: 'blue', official_portal: 'purple', comparative_best_practice: 'grey' }
            return map[type] ?? 'grey'
        },

        formatBytes(bytes) {
            if (bytes < 1024) return `${bytes} B`
            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        },
    },
}
</script>

<style scoped>
.aba-ai-settings-page {
    background: #0f172a;
    min-height: 100vh;
}

.aba-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    overflow: hidden;
}

.aba-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.aba-nav__button {
    min-height: 54px;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.aba-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.aba-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.aba-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.seed-meta-grid {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.seed-meta-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.seed-meta-label {
    font-size: 0.72rem;
    color: rgba(71, 85, 105, 0.8);
    width: 130px;
    flex-shrink: 0;
}

.seed-meta-value {
    font-size: 0.8rem;
    color: rgba(15, 23, 42, 0.88);
}

.seed-meta-mono {
    font-family: monospace;
    font-size: 0.72rem;
    word-break: break-all;
}

.status-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.source-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px solid rgba(148, 163, 184, 0.08);
}

.source-row:last-child {
    border-bottom: none;
}

.source-row__id {
    flex-shrink: 0;
}

.source-row__title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.source-row__meta {
    flex-shrink: 0;
}

.seed-preview {
    position: relative;
}

.seed-preview__text {
    font-size: 0.72rem;
    line-height: 1.55;
    color: rgba(148, 163, 184, 0.85);
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 340px;
    overflow: hidden;
    font-family: 'Consolas', 'Monaco', monospace;
    background: rgba(15, 23, 42, 0.7);
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 8px;
    padding: 12px;
    margin: 0;
}

.seed-preview__fade {
    position: absolute;
    bottom: 28px;
    left: 0;
    right: 0;
    height: 64px;
    background: linear-gradient(to bottom, transparent, rgba(30, 41, 59, 0.97));
    pointer-events: none;
    border-radius: 0 0 8px 8px;
}

.action-result {
    animation: fadeDown 0.18s ease;
}

.fade-down-enter-active {
    animation: fadeDown 0.18s ease;
}

@keyframes fadeDown {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
