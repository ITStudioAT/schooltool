<template>
    <v-container fluid class="aba-ai-settings-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern"
            title="KI-Einstellungen"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#1d4ed8"
            right-orb-color="#93c5fd" />

        <v-sheet rounded="xl" class="aba-nav mb-2">
            <div class="aba-nav__buttons">
                <v-btn rounded="xl" color="secondary" variant="tonal" class="aba-nav__button" @click="$router.push('/admin/aba')">
                    <v-icon size="18" icon="mdi-view-dashboard-outline" class="mr-2" />
                    <span class="aba-nav__button-copy">
                        <span class="aba-nav__button-title">Überblick</span>
                        <span class="aba-nav__button-meta">Meine ABAs</span>
                    </span>
                </v-btn>
                <v-btn rounded="xl" color="teal" variant="tonal" class="aba-nav__button" @click="$router.push('/admin/aba/ai-settings/seed-report')">
                    <v-icon size="18" icon="mdi-file-document-edit-outline" class="mr-2" />
                    <span class="aba-nav__button-copy">
                        <span class="aba-nav__button-title">Hauptdatei prüfen</span>
                        <span class="aba-nav__button-meta">Arbeitsbereich</span>
                    </span>
                </v-btn>
                <v-btn rounded="xl" color="secondary" variant="tonal" class="aba-nav__button" @click="$router.push('/admin/aba/ai-settings/seed-report/review')">
                    <v-icon size="18" icon="mdi-compare" class="mr-2" />
                    <span class="aba-nav__button-copy">
                        <span class="aba-nav__button-title">Vorschlag prüfen</span>
                        <span class="aba-nav__button-meta">Vergleich</span>
                    </span>
                </v-btn>
                <v-spacer />
                <v-btn size="small" variant="outlined" color="white" prepend-icon="mdi-refresh" :loading="isLoading" @click="loadData">
                    Aktualisieren
                </v-btn>
            </div>
        </v-sheet>

        <v-row v-if="accessDenied" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="error" variant="tonal" rounded="lg">
                    Zugriff nicht erlaubt. Diese Seite ist nur für Administratoren zugänglich.
                </v-alert>
            </v-col>
        </v-row>

        <v-row v-else-if="isLoading && !pageData" class="w-100 ma-0" dense>
            <v-col cols="12" class="d-flex justify-center pa-8">
                <v-progress-circular indeterminate color="primary" />
            </v-col>
        </v-row>

        <v-row v-else-if="loadError" class="w-100 ma-0" dense>
            <v-col cols="12">
                <v-alert type="error" variant="tonal" rounded="lg">{{ loadError }}</v-alert>
            </v-col>
        </v-row>

        <template v-else-if="pageData">
            <v-row class="w-100 ma-0" dense>
                <v-col cols="12" lg="5">
                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-compass-outline</v-icon>
                            Einstieg
                        </template>
                        <div class="pa-3">
                            <v-alert
                                :type="nextStep.key === 'proposal' ? 'success' : (nextStep.key === 'online' ? 'info' : 'warning')"
                                variant="tonal"
                                density="compact"
                                class="mb-3 text-caption">
                                <strong>Nächster sinnvoller Schritt:</strong> {{ nextStep.title }}<br>
                                <span class="opacity-80">{{ nextStep.description }}</span>
                            </v-alert>

                            <div class="entry-actions">
                                <v-btn block color="teal" variant="flat" prepend-icon="mdi-file-document-edit-outline" @click="$router.push('/admin/aba/ai-settings/seed-report')">
                                    Hauptdatei prüfen
                                </v-btn>
                                <v-btn block :color="hasValidProposal ? 'green' : 'secondary'" variant="tonal" prepend-icon="mdi-compare" :disabled="!hasProposal" @click="$router.push('/admin/aba/ai-settings/seed-report/review')">
                                    Vorschlag prüfen
                                </v-btn>
                                <v-btn block color="blue" variant="tonal" prepend-icon="mdi-web-check" :loading="actionLoading.onlineFreshness" @click="runCheckFreshnessOnline">
                                    Änderungen online prüfen
                                </v-btn>
                            </div>

                            <transition name="fade-down">
                                <v-alert v-if="actionResults.onlineFreshness" :type="actionResults.onlineFreshness.success ? 'success' : 'error'" variant="tonal" density="compact" class="text-caption mt-2">
                                    <template v-if="actionResults.onlineFreshness.success">
                                        {{ actionResults.onlineFreshness.result.sources_checked }} geprüft ·
                                        {{ actionResults.onlineFreshness.result.sources_reachable }} erreichbar ·
                                        {{ actionResults.onlineFreshness.result.sources_with_changes }} Änderung(en)
                                    </template>
                                    <template v-else>{{ actionResults.onlineFreshness.error }}</template>
                                </v-alert>
                            </transition>

                            <div class="text-caption text-disabled mt-2">
                                Die operative Bearbeitung erfolgt auf der Hauptdatei-Seite und auf der Prüfseite.
                            </div>
                        </div>
                    </ItsGridBox>

                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-file-document-outline</v-icon>
                            Status der Hauptdatei
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="seedReport.error" type="warning" variant="tonal" density="compact" class="mb-3 text-caption">
                                {{ seedReport.error }}
                            </v-alert>
                            <div class="seed-meta-grid">
                                <div class="seed-meta-row"><span class="seed-meta-label">Datei</span><span class="seed-meta-value seed-meta-mono">{{ seedReport.path }}</span></div>
                                <div v-if="seedMeta.title" class="seed-meta-row"><span class="seed-meta-label">Titel</span><span class="seed-meta-value">{{ seedMeta.title }}</span></div>
                                <div class="seed-meta-row"><span class="seed-meta-label">Version</span><span class="seed-meta-value"><v-chip size="x-small" color="blue" variant="tonal">{{ seedMeta.version || '–' }}</v-chip></span></div>
                                <div class="seed-meta-row"><span class="seed-meta-label">Letztes Review</span><span class="seed-meta-value">{{ formatDateTime(seedMeta.last_reviewed_at) }}</span></div>
                                <div class="seed-meta-row">
                                    <span class="seed-meta-label">Nächstes Review</span>
                                    <span class="seed-meta-value">
                                        <v-chip v-if="seedMeta.next_review_due_at" size="x-small" :color="isReviewOverdue ? 'error' : 'primary'" variant="tonal">{{ seedMeta.next_review_due_at }}</v-chip>
                                        <span v-else>–</span>
                                    </span>
                                </div>
                                <div class="seed-meta-row"><span class="seed-meta-label">Letzte Online-Prüfung</span><span class="seed-meta-value">{{ formatDateTime(freshnessData.last_run_at) }}</span></div>
                                <div class="seed-meta-row">
                                    <span class="seed-meta-label">Vorschlagsstatus</span>
                                    <span class="seed-meta-value"><v-chip size="x-small" :color="hasValidProposal ? 'green' : (hasProposal ? 'orange' : 'grey')" variant="tonal">{{ hasValidProposal ? 'Neuer Vorschlag vorhanden' : (hasProposal ? 'Vorschlag vorhanden' : 'Kein Vorschlag') }}</v-chip></span>
                                </div>
                                <div class="seed-meta-row"><span class="seed-meta-label">Offene Punkte</span><span class="seed-meta-value"><v-chip size="x-small" :color="openPointsTotal > 0 ? 'orange' : 'green'" variant="tonal">{{ openPointsTotal }}</v-chip></span></div>
                                <div class="seed-meta-row"><span class="seed-meta-label">Neuaufbau</span><span class="seed-meta-value"><v-chip size="x-small" :color="rebuildRecommended ? 'amber' : 'green'" variant="tonal">{{ rebuildRecommended ? 'Empfohlen' : 'Nicht erforderlich' }}</v-chip></span></div>
                                <div class="seed-meta-row"><span class="seed-meta-label">Letzter Aufbau</span><span class="seed-meta-value">{{ formatDateTime(reviewState.last_rebuilt_at) }}</span></div>
                            </div>
                        </div>
                    </ItsGridBox>

                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-map-marker-path</v-icon>
                            Arbeitsorte
                        </template>
                        <div class="pa-3">
                            <div class="workspace-list text-caption text-medium-emphasis">
                                <div><strong>Hauptdatei-Seite:</strong> Prüfung, Analyse und Vorschlags-Erstellung.</div>
                                <div><strong>Prüfseite:</strong> Vergleich, Vorschlag bearbeiten und bewusst übernehmen.</div>
                                <div><strong>Diese Seite:</strong> Überblick, Status und Einstieg.</div>
                            </div>
                        </div>
                    </ItsGridBox>
                </v-col>

                <v-col cols="12" lg="7">
                    <ItsGridBox class="mb-2">
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-view-dashboard-outline</v-icon>
                            Aktueller Stand
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="reviewState.error" type="warning" variant="tonal" density="compact" class="mb-3 text-caption">
                                {{ reviewState.error }}
                            </v-alert>

                            <div class="status-chips mb-3">
                                <v-chip size="small" color="grey" variant="outlined"><v-icon start size="14">mdi-database-outline</v-icon>{{ reviewState.total_claims }} Aussagen</v-chip>
                                <v-chip size="small" color="green" variant="tonal"><v-icon start size="14">mdi-check-circle-outline</v-icon>{{ statusCount('verified') }} verifiziert</v-chip>
                                <v-chip size="small" :color="statusCount('needs_review') > 0 ? 'orange' : 'grey'" variant="tonal"><v-icon start size="14">mdi-alert-circle-outline</v-icon>{{ statusCount('needs_review') }} zu prüfen</v-chip>
                                <v-chip size="small" :color="statusCount('stale') > 0 ? 'error' : 'grey'" variant="tonal"><v-icon start size="14">mdi-clock-alert-outline</v-icon>{{ statusCount('stale') }} veraltet</v-chip>
                                <v-chip size="small" :color="hasValidProposal ? 'green' : (hasProposal ? 'orange' : 'grey')" variant="tonal"><v-icon start size="14">mdi-file-document-check-outline</v-icon>{{ hasValidProposal ? 'Vorschlag liegt vor' : (hasProposal ? 'Vorschlag vorhanden' : 'Kein Vorschlag') }}</v-chip>
                                <v-chip size="small" :color="openPointsTotal > 0 ? 'orange' : 'green'" variant="tonal"><v-icon start size="14">mdi-bug-outline</v-icon>{{ openPointsTotal }} offene Punkte</v-chip>
                                <v-chip size="small" :color="rebuildRecommended ? 'amber' : 'grey'" variant="tonal"><v-icon start size="14">mdi-database-refresh-outline</v-icon>{{ rebuildRecommended ? 'Neuaufbau empfohlen' : 'Neuaufbau aktuell' }}</v-chip>
                            </div>

                            <div class="seed-meta-grid mb-3">
                                <div class="seed-meta-row"><span class="seed-meta-label">Letzter Analyse-Lauf</span><span class="seed-meta-value">{{ formatDateTime(hardeningStatus.last_run?.generated_at) }}</span></div>
                                <div class="seed-meta-row"><span class="seed-meta-label">Neuester Vorschlag</span><span class="seed-meta-value seed-meta-mono">{{ latestProposal?.filename || '–' }}</span></div>
                                <div class="seed-meta-row"><span class="seed-meta-label">Erkennung offener Punkte</span><span class="seed-meta-value">{{ formatDateTime(hardeningStatus.open_issues?.generated_at) }}</span></div>
                            </div>

                            <div v-if="reviewState.high_risk_claims?.length" class="mb-3">
                                <div class="text-caption text-medium-emphasis mb-1">Kritische Aussagen (noch nicht verifiziert):</div>
                                <v-chip v-for="claim in reviewState.high_risk_claims.slice(0, 8)" :key="claim.claim_key" size="x-small" color="error" variant="tonal" class="mr-1 mb-1">
                                    {{ claim.claim_key }}
                                </v-chip>
                            </div>

                            <v-divider class="my-3" />

                            <div class="text-caption text-medium-emphasis mb-2">
                                <v-icon size="14" class="mr-1">mdi-book-open-outline</v-icon>
                                Quellenregister ({{ sourceRegistry.total_sources }} Quellen)
                                <v-chip v-if="sourceRegistry.due_for_refresh_count > 0" size="x-small" color="orange" variant="tonal" class="ml-1">{{ sourceRegistry.due_for_refresh_count }} fällig</v-chip>
                            </div>
                            <div class="d-flex flex-column ga-1">
                                <div v-for="source in sourceRegistry.sources" :key="source.source_id" class="source-row">
                                    <div class="source-row__id"><v-chip size="x-small" :color="sourceTypeColor(source.type)" variant="tonal">{{ source.source_id }}</v-chip></div>
                                    <div class="source-row__title text-caption text-medium-emphasis">{{ source.title }}</div>
                                    <div class="source-row__meta">
                                        <v-chip v-if="source.due_for_refresh" size="x-small" color="orange" variant="tonal">fällig</v-chip>
                                        <span v-else class="text-caption text-disabled">fällig {{ source.due_at }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ItsGridBox>

                    <ItsGridBox>
                        <template #title>
                            <v-icon size="16" class="mr-1">mdi-text-box-outline</v-icon>
                            Hauptdatei (Vorschau)
                        </template>
                        <template #header-actions>
                            <v-btn size="small" variant="tonal" color="teal" prepend-icon="mdi-file-document-edit-outline" @click="$router.push('/admin/aba/ai-settings/seed-report')">
                                Hauptdatei prüfen
                            </v-btn>
                        </template>
                        <div class="pa-3">
                            <v-alert v-if="!seedReport.found" type="warning" variant="tonal" density="compact" class="text-caption">
                                Hauptdatei nicht gefunden.
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
            actionLoading: { onlineFreshness: false },
            actionResults: { onlineFreshness: null },
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
            return { label: 'KI-Einstellungen', icon: 'mdi-brain', note: 'Übersicht und Einstieg für Hauptdatei, Vorschläge und Wissensbasis.' }
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

        freshnessData() {
            return this.pageData?.freshness_results ?? { found: false, last_run_at: null, summary: {}, sources_list: [] }
        },

        hardeningStatus() {
            return this.pageData?.hardening_status ?? { open_issues: { found: false, total_issues: 0, by_type: {}, generated_at: null }, last_run: null }
        },

        proposalList() {
            return Array.isArray(this.pageData?.proposals) ? this.pageData.proposals : []
        },

        hasProposal() {
            return this.proposalList.length > 0
        },

        hasValidProposal() {
            return this.proposalList.some((proposal) => proposal.is_seed_replacement && proposal.is_valid_replacement !== false)
        },

        latestProposal() {
            return this.proposalList[0] ?? null
        },

        openPointsTotal() {
            const fromHardening = this.hardeningStatus?.open_issues?.total_issues
            if (typeof fromHardening === 'number') return fromHardening
            return this.statusCount('needs_review') + this.statusCount('stale')
        },

        rebuildRecommended() {
            const lastAnalysis = this.hardeningStatus?.last_run?.generated_at
            const lastRebuild = this.reviewState?.last_rebuilt_at
            if (!lastAnalysis) return false
            if (!lastRebuild) return true
            return new Date(lastAnalysis) > new Date(lastRebuild)
        },

        isReviewOverdue() {
            const due = this.seedMeta?.next_review_due_at
            return due ? new Date(due) < new Date() : false
        },

        nextStep() {
            if (this.hasValidProposal) {
                return {
                    key: 'proposal',
                    title: 'Vorschlag prüfen',
                    description: 'Es liegt eine vorgeschlagene neue Fassung vor. Bitte Vergleich prüfen und bei Bedarf bewusst übernehmen.',
                }
            }

            if (this.openPointsTotal > 0 || this.isReviewOverdue) {
                return {
                    key: 'main',
                    title: 'Hauptdatei prüfen',
                    description: 'Es gibt offenen Prüfbedarf. Starten Sie auf der Hauptdatei-Seite mit Analyse und redaktioneller Prüfung.',
                }
            }

            if (!this.freshnessData.found || this.sourceRegistry.due_for_refresh_count > 0) {
                return {
                    key: 'online',
                    title: 'Änderungen online prüfen',
                    description: 'Für den aktuellen Stand wird eine Online-Prüfung der Quellen empfohlen.',
                }
            }

            return {
                key: 'main',
                title: 'Hauptdatei prüfen',
                description: 'Der Stand wirkt aktuell. Für redaktionelle Anpassungen arbeiten Sie direkt auf der Hauptdatei-Seite.',
            }
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

        async runCheckFreshnessOnline() {
            this.actionLoading.onlineFreshness = true
            this.actionResults.onlineFreshness = null
            try {
                const response = await axios.post('/api/admin/aba/ai-settings/check-freshness-online')
                this.actionResults.onlineFreshness = response.data
                await this.loadData()
            } catch (error) {
                this.actionResults.onlineFreshness = { success: false, error: error.response?.data?.error || 'Online-Prüfung fehlgeschlagen.' }
            } finally {
                this.actionLoading.onlineFreshness = false
            }
        },

        statusCount(status) {
            return this.reviewState?.by_status?.[status] ?? 0
        },

        sourceTypeColor(type) {
            const map = { legal: 'red', ministry_guidance: 'blue', official_portal: 'purple', comparative_best_practice: 'grey' }
            return map[type] ?? 'grey'
        },

        formatDateTime(value) {
            if (!value) return '–'
            const d = new Date(value)
            if (isNaN(d.getTime())) return value
            const hasTime = /T|\d{2}:\d{2}:\d{2}/.test(value)
            const date = d.toLocaleDateString('de-AT', { year: 'numeric', month: '2-digit', day: '2-digit' })
            if (!hasTime) return date
            const time = d.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' })
            return `${date} ${time}`
        },
    },
}
</script>

<style scoped>
.aba-ai-settings-page { background: #0f172a; min-height: 100vh; }
.aba-nav { border: 1px solid rgba(148, 163, 184, 0.16); background: rgba(30, 41, 59, 0.8); padding: 10px; overflow: hidden; }
.aba-nav__buttons { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.aba-nav__button { min-height: 54px; padding: 0 14px; text-transform: none; letter-spacing: 0; justify-content: flex-start; }
.aba-nav__button-copy { display: inline-flex; flex-direction: column; align-items: flex-start; line-height: 1.2; }
.aba-nav__button-title { font-weight: 650; font-size: 0.92rem; }
.aba-nav__button-meta { font-size: 0.72rem; opacity: 0.85; }
.entry-actions, .workspace-list, .seed-meta-grid { display: flex; flex-direction: column; gap: 8px; }
.seed-meta-row { display: flex; align-items: baseline; gap: 8px; }
.seed-meta-label { font-size: 0.72rem; color: rgba(71, 85, 105, 0.8); width: 140px; flex-shrink: 0; }
.seed-meta-value { font-size: 0.8rem; color: rgba(15, 23, 42, 0.88); }
.seed-meta-mono { font-family: monospace; font-size: 0.72rem; word-break: break-all; }
.status-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.source-row { display: flex; align-items: center; gap: 8px; padding: 4px 0; border-bottom: 1px solid rgba(148, 163, 184, 0.08); }
.source-row:last-child { border-bottom: none; }
.source-row__id, .source-row__meta { flex-shrink: 0; }
.source-row__title { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.seed-preview { position: relative; }
.seed-preview__text { font-size: 0.72rem; line-height: 1.55; color: rgba(148, 163, 184, 0.85); white-space: pre-wrap; word-break: break-word; max-height: 340px; overflow: hidden; font-family: 'Consolas', 'Monaco', monospace; background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(148, 163, 184, 0.12); border-radius: 8px; padding: 12px; margin: 0; }
.seed-preview__fade { position: absolute; bottom: 28px; left: 0; right: 0; height: 64px; background: linear-gradient(to bottom, transparent, rgba(30, 41, 59, 0.97)); pointer-events: none; border-radius: 0 0 8px 8px; }
.action-result { animation: fadeDown 0.18s ease; }
.fade-down-enter-active { animation: fadeDown 0.18s ease; }
@keyframes fadeDown { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
</style>
