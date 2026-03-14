<template>
    <v-container fluid class="seed-report-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern · ABA"
            title="Seed-Report"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#0f766e"
            right-orb-color="#5eead4" />

        <!-- Navigation -->
        <v-sheet rounded="xl" class="sr-nav mb-2">
            <div class="sr-nav__buttons">
                <v-btn
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="sr-nav__button"
                    @click="$router.push('/admin/aba')">
                    <v-icon size="18" icon="mdi-view-dashboard-outline" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">Überblick</span>
                        <span class="sr-nav__button-meta">Meine ABAs</span>
                    </span>
                </v-btn>
                <v-btn
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="sr-nav__button"
                    @click="$router.push('/admin/aba/ai-settings')">
                    <v-icon size="18" icon="mdi-brain" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">KI-Einstellungen</span>
                        <span class="sr-nav__button-meta">Dashboard</span>
                    </span>
                </v-btn>
                <v-btn rounded="xl" color="teal" variant="flat" class="sr-nav__button">
                    <v-icon size="18" icon="mdi-file-document-edit-outline" class="mr-2" />
                    <span class="sr-nav__button-copy">
                        <span class="sr-nav__button-title">Seed-Report</span>
                        <span class="sr-nav__button-meta">Hauptdatei</span>
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
                <v-progress-circular indeterminate color="teal" />
            </v-col>
        </v-row>

        <!-- Ladefehler -->
        <v-row v-else-if="loadError" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="error" variant="tonal" rounded="lg">{{ loadError }}</v-alert>
            </v-col>
        </v-row>

        <!-- Datei nicht gefunden -->
        <v-row v-else-if="pageData && !contentData.found" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="warning" variant="tonal" rounded="lg">
                    <strong>Seed-Datei nicht gefunden:</strong>
                    <code class="ml-2">ai/knowledge/aba/sources/aba-knowledge-seed-report.md</code>
                </v-alert>
            </v-col>
        </v-row>

        <!-- Hauptinhalt -->
        <template v-else-if="pageData">
            <v-row class="w-100 ma-0" dense>
                <!-- Linke Spalte: Meta + Governance + Aktionen -->
                <v-col cols="12" lg="4">
                    <!-- Datei-Metadaten -->
                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-file-document-outline</v-icon>
                            Metadaten
                        </template>
                        <div class="pa-3">
                            <v-alert
                                v-if="metaData.error"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="mb-3 text-caption">
                                {{ metaData.error }}
                            </v-alert>
                            <div class="meta-grid">
                                <div class="meta-row">
                                    <span class="meta-label">Datei</span>
                                    <span class="meta-value meta-mono">aba-knowledge-seed-report.md</span>
                                </div>
                                <div v-if="seedMeta.title" class="meta-row">
                                    <span class="meta-label">Titel</span>
                                    <span class="meta-value">{{ seedMeta.title }}</span>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-label">Version</span>
                                    <span class="meta-value">
                                        <v-chip size="x-small" color="blue" variant="tonal">
                                            {{ seedMeta.version || '–' }}
                                        </v-chip>
                                    </span>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-label">Status</span>
                                    <span class="meta-value">
                                        <v-chip
                                            size="x-small"
                                            :color="seedMeta.status === 'active' ? 'green' : 'grey'"
                                            variant="tonal">
                                            {{ seedMeta.status || 'unbekannt' }}
                                        </v-chip>
                                    </span>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-label">Letztes Review</span>
                                    <span class="meta-value">{{ seedMeta.last_reviewed_at || '–' }}</span>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-label">Nächstes Review</span>
                                    <span class="meta-value">
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
                                <div v-if="metaData.modified_at" class="meta-row">
                                    <span class="meta-label">Datei geändert</span>
                                    <span class="meta-value">{{ metaData.modified_at }}</span>
                                </div>
                                <div v-if="metaData.size_bytes" class="meta-row">
                                    <span class="meta-label">Dateigröße</span>
                                    <span class="meta-value">{{ formatBytes(metaData.size_bytes) }}</span>
                                </div>
                                <div v-if="contentData.line_count" class="meta-row">
                                    <span class="meta-label">Zeilen</span>
                                    <span class="meta-value">{{ contentData.line_count }} gesamt · {{ contentData.body_line_count }} Body</span>
                                </div>
                                <div v-if="contentData.char_count" class="meta-row">
                                    <span class="meta-label">Zeichen</span>
                                    <span class="meta-value">{{ contentData.char_count.toLocaleString('de-AT') }}</span>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>

                    <!-- Governance / Review-Status -->
                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-shield-check-outline</v-icon>
                            Governance
                        </template>
                        <div class="pa-3">
                            <div class="status-chips mb-3">
                                <v-chip size="small" color="grey" variant="outlined">
                                    <v-icon start size="14">mdi-database-outline</v-icon>
                                    {{ reviewState.total_claims }} Claims
                                </v-chip>
                                <v-chip size="small" color="green" variant="tonal">
                                    <v-icon start size="14">mdi-check-circle-outline</v-icon>
                                    {{ statusCount('verified') }} ✓
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="statusCount('needs_review') > 0 ? 'orange' : 'grey'"
                                    variant="tonal">
                                    <v-icon start size="14">mdi-alert-circle-outline</v-icon>
                                    {{ statusCount('needs_review') }} ⚠
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="statusCount('stale') > 0 ? 'error' : 'grey'"
                                    variant="tonal">
                                    <v-icon start size="14">mdi-clock-alert-outline</v-icon>
                                    {{ statusCount('stale') }} stale
                                </v-chip>
                            </div>

                            <div class="meta-grid">
                                <div v-if="reviewState.last_rebuilt_at" class="meta-row">
                                    <span class="meta-label">Letzter Rebuild</span>
                                    <span class="meta-value">{{ reviewState.last_rebuilt_at }}</span>
                                </div>
                                <div v-if="freshnessData.found" class="meta-row">
                                    <span class="meta-label">Online-Check</span>
                                    <span class="meta-value">{{ freshnessData.last_run_at ? freshnessData.last_run_at.substring(0, 10) : '–' }}</span>
                                </div>
                            </div>

                            <div v-if="openClaimsData.total > 0" class="mt-2">
                                <div class="text-caption text-medium-emphasis mb-1">
                                    {{ openClaimsData.total }} offene Claim(s) – Überprüfung empfohlen
                                </div>
                                <v-chip
                                    v-for="claim in openClaimsData.claims.slice(0, 6)"
                                    :key="claim.claim_key"
                                    size="x-small"
                                    :color="claim.change_risk === 'high' ? 'error' : 'orange'"
                                    variant="tonal"
                                    class="mr-1 mb-1">
                                    {{ claim.claim_key }}
                                </v-chip>
                                <span v-if="openClaimsData.total > 6" class="text-caption text-disabled">
                                    + {{ openClaimsData.total - 6 }} weitere
                                </span>
                            </div>

                            <div v-else class="text-caption text-medium-emphasis mt-1">
                                <v-icon size="14" color="green" class="mr-1">mdi-check-circle-outline</v-icon>
                                Alle Claims verifiziert.
                            </div>
                        </div>
                    </ItsGridBox>

                    <!-- Aktionen -->
                    <ItsGridBox>
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
                                                    {{ actionResults.onlineFreshness.result.sources_with_changes }} Änderung(en)
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
                                                    {{ actionResults.rebuild.result.claims_count }} Claims ·
                                                    {{ actionResults.rebuild.result.chunks_count }} Chunks ·
                                                    {{ actionResults.rebuild.result.files_written_count }} Dateien
                                                </template>
                                                <template v-else>{{ actionResults.rebuild.error }}</template>
                                            </v-alert>
                                        </div>
                                    </transition>
                                </div>

                                <v-divider class="my-1" />

                                <v-btn
                                    block
                                    variant="outlined"
                                    color="white"
                                    size="small"
                                    prepend-icon="mdi-content-copy"
                                    @click="copyContent">
                                    Rohtext kopieren
                                </v-btn>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>

                <!-- Rechte Spalte: Dateiinhalt -->
                <v-col cols="12" lg="8">
                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-text-box-outline</v-icon>
                            Dateiinhalt
                            <v-chip size="x-small" color="grey" variant="outlined" class="ml-2">
                                {{ contentData.line_count }} Zeilen
                            </v-chip>
                        </template>

                        <template #header-actions>
                            <v-btn-toggle
                                v-model="viewMode"
                                density="compact"
                                rounded="lg"
                                color="teal"
                                variant="outlined">
                                <v-btn value="body" size="small">
                                    <v-icon size="14" class="mr-1">mdi-text</v-icon>
                                    Body
                                </v-btn>
                                <v-btn value="full" size="small">
                                    <v-icon size="14" class="mr-1">mdi-code-braces</v-icon>
                                    Komplett
                                </v-btn>
                            </v-btn-toggle>
                        </template>

                        <div class="pa-3">
                            <v-alert
                                v-if="contentData.error"
                                type="error"
                                variant="tonal"
                                density="compact"
                                class="mb-3 text-caption">
                                {{ contentData.error }}
                            </v-alert>

                            <!-- Frontmatter-Hinweis im Body-Modus -->
                            <div v-if="viewMode === 'body'" class="frontmatter-hint text-caption text-disabled mb-2">
                                <v-icon size="12" class="mr-1">mdi-information-outline</v-icon>
                                YAML-Frontmatter ausgeblendet · Wechsle zu „Komplett" für den vollständigen Rohtext.
                            </div>

                            <div class="content-wrapper">
                                <div class="line-numbers" aria-hidden="true">
                                    <span
                                        v-for="n in displayLineCount"
                                        :key="n"
                                        class="line-number">{{ n }}</span>
                                </div>
                                <pre class="content-text"><code>{{ displayContent }}</code></pre>
                            </div>

                            <div class="text-caption text-disabled mt-2 d-flex justify-space-between">
                                <span>
                                    <v-icon size="12" class="mr-1">mdi-file-document-outline</v-icon>
                                    ai/knowledge/aba/sources/aba-knowledge-seed-report.md
                                </span>
                                <span>
                                    {{ displayLineCount }} Zeilen ·
                                    {{ (displayContent || '').length.toLocaleString('de-AT') }} Zeichen
                                </span>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>
            </v-row>

            <!-- Hinweis -->
            <v-row class="w-100 ma-0 mt-1" dense>
                <v-col cols="12">
                    <v-alert
                        type="info"
                        variant="tonal"
                        density="compact"
                        rounded="lg"
                        class="text-caption">
                        <strong>Governed Knowledge Artifact:</strong>
                        Diese Datei ist die kuratierte Hauptdatei der AHS-ABA-Knowledge-Pipeline.
                        Änderungen sind redaktionell relevant. Online-Checks ändern die Datei nicht automatisch.
                        Review- und Rebuild-Prozesse hängen direkt an dieser Datei.
                    </v-alert>
                </v-col>
            </v-row>
        </template>

        <!-- Snackbar für Kopieren -->
        <v-snackbar v-model="copySnackbar" timeout="2000" color="success" location="bottom">
            Rohtext kopiert.
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
        await this.loadData()
    },

    data() {
        return {
            adminStore: null,
            isLoading: false,
            loadError: null,
            accessDenied: false,
            pageData: null,
            viewMode: 'body',
            copySnackbar: false,
            actionLoading: { onlineFreshness: false, proposal: false, rebuild: false },
            actionResults: { onlineFreshness: null, proposal: null, rebuild: null },
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
            return { label: 'Seed-Report', icon: 'mdi-file-document-edit-outline', note: 'Hauptdatei der AHS-ABA-Knowledge-Pipeline.' }
        },

        contentData() {
            return this.pageData?.content ?? { found: false, full_content: null, body_content: null, frontmatter_raw: null, line_count: 0, body_line_count: 0, char_count: 0, error: null }
        },

        metaData() {
            return this.pageData?.meta ?? { found: false, path: '–', size_bytes: null, modified_at: null, meta: {}, error: null }
        },

        seedMeta() {
            return this.metaData?.meta ?? {}
        },

        reviewState() {
            return this.pageData?.review_state ?? { total_claims: 0, by_status: {}, last_rebuilt_at: null, error: null }
        },

        freshnessData() {
            return this.pageData?.freshness_results ?? { found: false, last_run_at: null, summary: {} }
        },

        openClaimsData() {
            return this.pageData?.open_claims ?? { found: false, total: 0, claims: [] }
        },

        isReviewOverdue() {
            const due = this.seedMeta?.next_review_due_at
            return due ? new Date(due) < new Date() : false
        },

        displayContent() {
            if (this.viewMode === 'body') {
                return this.contentData.body_content || ''
            }
            return this.contentData.full_content || ''
        },

        displayLineCount() {
            if (!this.displayContent) {
                return 0
            }
            return this.displayContent.split('\n').length
        },
    },

    methods: {
        async loadData() {
            this.isLoading = true
            this.loadError = null
            this.accessDenied = false
            try {
                const response = await axios.get('/api/admin/aba/seed-report')
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

        async copyContent() {
            try {
                await navigator.clipboard.writeText(this.displayContent)
                this.copySnackbar = true
            } catch {
                // Fallback für Browser ohne Clipboard-API
            }
        },

        statusCount(status) {
            return this.reviewState?.by_status?.[status] ?? 0
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
.seed-report-page {
    background: #0f172a;
    min-height: 100vh;
}

.sr-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    overflow: hidden;
}

.sr-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.sr-nav__button {
    min-height: 54px;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.sr-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.sr-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.sr-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.meta-grid {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.meta-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.meta-label {
    font-size: 0.72rem;
    color: rgba(71, 85, 105, 0.8);
    width: 110px;
    flex-shrink: 0;
}

.meta-value {
    font-size: 0.8rem;
    color: rgba(15, 23, 42, 0.88);
    word-break: break-word;
}

.meta-mono {
    font-family: monospace;
    font-size: 0.72rem;
}

.status-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.frontmatter-hint {
    padding: 4px 8px;
    background: rgba(15, 23, 42, 0.3);
    border-radius: 4px;
}

.content-wrapper {
    display: flex;
    gap: 0;
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 8px;
    overflow: auto;
    max-height: 72vh;
    min-height: 320px;
}

.line-numbers {
    display: flex;
    flex-direction: column;
    padding: 12px 8px;
    background: rgba(15, 23, 42, 0.6);
    border-right: 1px solid rgba(148, 163, 184, 0.08);
    user-select: none;
    flex-shrink: 0;
    min-width: 40px;
    text-align: right;
}

.line-number {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.68rem;
    line-height: 1.55;
    color: rgba(148, 163, 184, 0.3);
}

.content-text {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.72rem;
    line-height: 1.55;
    color: rgba(203, 213, 225, 0.9);
    white-space: pre;
    word-break: normal;
    padding: 12px 16px;
    margin: 0;
    flex: 1;
    overflow: visible;
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
