<template>
    <div v-if="config && config.is_auth" class="admin-dashboard-page">
        <v-container fluid class="admin-dashboard-page__content">
            <header class="admin-dashboard-page__onebar">
                <div class="admin-dashboard-page__brand">
                    <div class="admin-dashboard-page__brand-mark">
                        <v-icon icon="mdi-school-outline" size="21" />
                    </div>
                    <div>
                        <div class="admin-dashboard-page__brand-name">SchoolTool</div>
                        <div class="admin-dashboard-page__eyebrow">Admin Dashboard</div>
                    </div>
                </div>

                <div class="admin-dashboard-page__health">
                    <div
                        class="admin-dashboard-page__health-icon"
                        :class="{
                            'admin-dashboard-page__health-icon--healthy': health_loaded && healthStore.is_healthy,
                            'admin-dashboard-page__health-icon--error': health_loaded && !healthStore.is_healthy,
                        }">
                        <v-icon
                            :icon="health_loaded ? (healthStore.is_healthy ? 'mdi-check' : 'mdi-alert-outline') : 'mdi-progress-clock'"
                            size="18" />
                    </div>
                    <div>
                        <div class="admin-dashboard-page__health-title">
                            {{ !health_loaded ? 'Status wird geprüft' : healthStore.is_healthy ? 'Alles in Ordnung' : 'Prüfung erforderlich' }}
                        </div>
                        <div class="admin-dashboard-page__health-detail">
                            {{ !health_loaded ? 'Scheduler und Worker werden geprüft' : healthStore.is_healthy ? 'Scheduler und Worker laufen' : 'Mindestens ein Dienst antwortet nicht' }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="config?.selected_school?.long_name || config?.selected_school?.name"
                    class="admin-dashboard-page__metadata">
                    <span class="admin-dashboard-page__meta-chip">
                        {{ config?.selected_school?.long_name || config?.selected_school?.name }}
                    </span>
                </div>

                <v-btn
                    v-if="health_loaded && healthStore.is_healthy === false && isAllowed(['admin', 'super_admin'])"
                    class="admin-dashboard-page__diagnosis-trigger"
                    size="small"
                    variant="tonal"
                    color="error"
                    prepend-icon="mdi-stethoscope"
                    :aria-expanded="diagnostics_visible"
                    aria-controls="system-diagnostics"
                    @click="diagnostics_visible = true">
                    Diagnose starten
                </v-btn>
            </header>

            <main class="admin-dashboard-page__main">
                <section
                    v-if="diagnostics_visible && health_loaded && healthStore.is_healthy === false && isAllowed(['admin', 'super_admin'])"
                    id="system-diagnostics"
                    class="admin-dashboard-page__diagnostics"
                    aria-labelledby="system-diagnostics-heading">
                    <div class="admin-dashboard-page__diagnostics-heading">
                        <div>
                            <h2 id="system-diagnostics-heading">Systemdiagnose</h2>
                            <p>Scheduler und Queue gezielt prüfen.</p>
                        </div>
                        <v-btn
                            icon="mdi-close"
                            size="small"
                            variant="text"
                            aria-label="Systemdiagnose schließen"
                            @click="diagnostics_visible = false" />
                    </div>

                    <div class="admin-dashboard-page__diagnostics-statuses">
                        <div>
                            <span>Scheduler</span>
                            <strong :class="{ 'admin-dashboard-page__diagnostics-error': !healthStore.scheduler?.is_healthy }">
                                {{ healthStore.scheduler?.is_healthy ? 'Läuft' : 'Fehler' }}
                            </strong>
                            <small>Heartbeat: {{ formatHeartbeat(healthStore.scheduler?.last_heartbeat) }}</small>
                        </div>
                        <div>
                            <span>Queue-Worker</span>
                            <strong :class="{ 'admin-dashboard-page__diagnostics-error': !healthStore.worker?.is_healthy }">
                                {{ healthStore.worker?.is_healthy ? 'Läuft' : 'Fehler' }}
                            </strong>
                            <small>Heartbeat: {{ formatHeartbeat(healthStore.worker?.last_heartbeat) }}</small>
                        </div>
                    </div>

                    <div
                        v-if="queue_test_visible"
                        class="admin-dashboard-page__queue-result"
                        :class="{ 'admin-dashboard-page__queue-result--success': healthStore.queue_test?.is_completed }"
                        role="status">
                        <v-icon
                            :icon="queue_test_running ? 'mdi-progress-clock' : healthStore.queue_test?.is_completed ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'"
                            size="18" />
                        <span v-if="queue_test_running">Queue-Test läuft …</span>
                        <span v-else-if="healthStore.queue_test?.is_completed">
                            Queue arbeitet
                            <template v-if="healthStore.queue_test?.duration_seconds != null">
                                ({{ healthStore.queue_test.duration_seconds }} s)
                            </template>
                        </span>
                        <span v-else>Queue-Test konnte nicht abgeschlossen werden.</span>
                    </div>

                    <div class="admin-dashboard-page__diagnostics-actions">
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="mdi-play-circle-outline"
                            :loading="queue_test_running"
                            :disabled="queue_test_running || restart_queues_loading"
                            @click="runQueueTest">
                            Queue testen
                        </v-btn>
                        <v-btn
                            size="small"
                            variant="outlined"
                            color="warning"
                            prepend-icon="mdi-restart"
                            :loading="restart_queues_loading"
                            :disabled="restart_queues_loading || queue_test_running"
                            @click="restartQueues">
                            Queues neu starten
                        </v-btn>
                    </div>
                </section>

                <div class="admin-dashboard-page__overview-grid">
                    <v-card flat border rounded="xl" class="admin-dashboard-page__panel admin-dashboard-page__version-panel">
                        <v-card-text class="pa-0">
                            <div class="admin-dashboard-page__version-heading">
                                <div>
                                    <h1 class="admin-dashboard-page__section-title">Aktuelle Version</h1>
                                    <div class="admin-dashboard-page__app-version" data-testid="app-version">
                                        v{{ appVersion }}
                                    </div>
                                </div>
                                <v-btn
                                    size="small"
                                    variant="text"
                                    color="primary"
                                    :append-icon="version_details_visible ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                    :aria-expanded="version_details_visible"
                                    aria-controls="version-details"
                                    @click="version_details_visible = !version_details_visible">
                                    {{ version_details_visible ? 'Weniger anzeigen' : 'Mehr anzeigen' }}
                                </v-btn>
                            </div>

                            <div
                                v-show="version_details_visible"
                                id="version-details"
                                class="admin-dashboard-page__version-details">
                                <div class="admin-dashboard-page__tech-grid">
                                    <div v-for="item in versionItems" :key="item.key" class="admin-dashboard-page__tech-item">
                                        <span>{{ item.label }}</span>
                                        <strong :title="item.value">
                                            {{ item.value }}
                                        </strong>
                                    </div>
                                </div>

                                <div class="admin-dashboard-page__about-grid">
                                    <section
                                        v-for="section in aboutSections"
                                        :key="section.key"
                                        class="admin-dashboard-page__about-section">
                                        <h2>{{ section.label }}</h2>
                                        <dl>
                                            <div v-for="item in section.items" :key="item.key">
                                                <dt>{{ item.label }}</dt>
                                                <dd :class="item.tone ? `admin-dashboard-page__about-value--${item.tone}` : null">
                                                    {{ item.value }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </section>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>

                    <v-card flat border rounded="xl" class="admin-dashboard-page__panel admin-dashboard-page__account-panel">
                        <div class="admin-dashboard-page__account-heading">
                            <h2 class="admin-dashboard-page__section-title">Angemeldeter Benutzer</h2>
                            <v-btn
                                size="small"
                                variant="text"
                                color="primary"
                                :append-icon="account_details_visible ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                :aria-expanded="account_details_visible"
                                aria-controls="account-details"
                                @click="account_details_visible = !account_details_visible">
                                {{ account_details_visible ? 'Weniger anzeigen' : 'Mehr anzeigen' }}
                            </v-btn>
                        </div>

                        <div class="admin-dashboard-page__account">
                            <div class="admin-dashboard-page__account-copy">
                                <strong class="admin-dashboard-page__account-name">
                                    {{ config?.user?.last_name }} {{ config?.user?.first_name }}
                                </strong>
                                <span>{{ config?.user?.email }}</span>
                            </div>
                        </div>

                        <div v-show="account_details_visible" id="account-details" class="admin-dashboard-page__account-details">
                            <div class="admin-dashboard-page__eyebrow">Rollen</div>
                            <div class="admin-dashboard-page__roles">
                                <span
                                    v-for="role in config?.user?.roles || []"
                                    :key="role"
                                    class="admin-dashboard-page__role">
                                    {{ role }}
                                </span>
                            </div>
                        </div>
                    </v-card>
                </div>

                <section class="admin-dashboard-page__licences" aria-labelledby="licences-heading">
                <div class="admin-dashboard-page__licence-heading">
                    <div>
                        <h2 id="licences-heading" class="admin-dashboard-page__licence-title">Lizenzen</h2>
                        <p>Verfügbare Tools für die Schule und deinen Account.</p>
                    </div>
                    <div class="admin-dashboard-page__licence-summary">
                        <span>{{ activeLicenceCount }} aktiv</span>
                        <span v-if="expiredLicenceCount > 0" class="admin-dashboard-page__summary-expired">
                            {{ expiredLicenceCount }} abgelaufen
                        </span>
                    </div>
                </div>

                <div class="admin-dashboard-page__licence-grid">
                    <v-card flat border rounded="xl" class="admin-dashboard-page__panel admin-dashboard-page__licence-panel">
                        <div class="admin-dashboard-page__licence-panel-heading">
                            <div>
                                <div class="admin-dashboard-page__eyebrow">Schule</div>
                                <h3>Schullizenzen</h3>
                            </div>
                            <span>{{ schoolLicencesWithSchoolLicence.length }}</span>
                        </div>

                        <div v-if="schoolLicencesWithSchoolLicence.length > 0" class="admin-dashboard-page__licence-list">
                            <div
                                v-for="licence in schoolLicencesWithSchoolLicence"
                                :key="licence.id"
                                class="admin-dashboard-page__licence-row">
                                <div class="admin-dashboard-page__licence-symbol" aria-hidden="true">
                                    <v-icon icon="mdi-certificate-outline" size="18" />
                                </div>
                                <div class="admin-dashboard-page__licence-copy">
                                    <strong>{{ licence.name }}</strong>
                                    <span v-if="licence.long_name">{{ licence.long_name }}</span>
                                </div>
                                <span
                                    class="admin-dashboard-page__licence-status"
                                    :class="{ 'admin-dashboard-page__licence-status--expired': !isLicenceActive(licence) }">
                                    <template v-if="isLicenceActive(licence) && licence.valid_until">
                                        bis {{ formatDateDisplay(licence.valid_until) }}
                                    </template>
                                    <template v-else-if="isLicenceActive(licence)">unbegrenzt</template>
                                    <template v-else>abgelaufen</template>
                                </span>
                            </div>
                        </div>

                        <div v-else class="admin-dashboard-page__empty">
                            <v-icon icon="mdi-certificate-outline" size="30" />
                            <span>Keine Schullizenzen vorhanden.</span>
                        </div>
                    </v-card>

                    <v-card flat border rounded="xl" class="admin-dashboard-page__panel admin-dashboard-page__licence-panel">
                        <div class="admin-dashboard-page__licence-panel-heading">
                            <div>
                                <div class="admin-dashboard-page__eyebrow">Persönlich</div>
                                <h3>Meine Lizenzen</h3>
                            </div>
                            <span>{{ myLicenceEntries.length }}</span>
                        </div>

                        <div v-if="myLicenceEntries.length > 0" class="admin-dashboard-page__licence-list">
                            <div v-for="entry in myLicenceEntries" :key="entry.key" class="admin-dashboard-page__licence-row">
                                <div class="admin-dashboard-page__licence-symbol" aria-hidden="true">
                                    <v-icon icon="mdi-account-key-outline" size="18" />
                                </div>
                                <div class="admin-dashboard-page__licence-copy">
                                    <strong>{{ entry.licence_name }}</strong>
                                    <span>{{ entry.type_label }}</span>
                                </div>
                                <span
                                    class="admin-dashboard-page__licence-status"
                                    :class="{ 'admin-dashboard-page__licence-status--expired': !entry.is_active }">
                                    <template v-if="entry.is_active && entry.valid_until">
                                        bis {{ formatDateDisplay(entry.valid_until) }}
                                    </template>
                                    <template v-else-if="entry.is_active">unbegrenzt</template>
                                    <template v-else>abgelaufen</template>
                                </span>
                            </div>
                        </div>

                        <div v-else class="admin-dashboard-page__empty">
                            <v-icon icon="mdi-account-key-outline" size="30" />
                            <strong>Noch keine persönlichen Lizenzen</strong>
                            <span>Für diesen Account sind aktuell keine eigenen Tools freigeschaltet.</span>
                        </div>
                    </v-card>
                </div>
                </section>
            </main>
        </v-container>

        <v-dialog v-model="activation_dialog_open" max-width="560">
            <v-card>
                <v-card-title>Benutzerlizenz aktivieren</v-card-title>
                <v-card-text>
                    <div class="text-body-2 mb-3" v-if="activation_dialog_licence">
                        <strong>{{ activation_dialog_licence.name }}</strong>
                        <span v-if="activation_dialog_role_name"> · Rolle: {{ activation_dialog_role_name }}</span>
                    </div>

                    <div class="text-body-2 mb-2" v-if="!activation_dialog_payment_active">
                        Nur kostenlose Optionen sind auswählbar.
                    </div>

                    <v-radio-group v-model="activation_dialog_selected_plan_id" density="compact" hide-details>
                        <v-radio
                            v-for="plan in activation_dialog_plans"
                            :key="`activation-plan-${plan.id}`"
                            :value="plan.id"
                            :disabled="!plan.is_selectable">
                            <template #label>
                                <div class="activation-plan-option">
                                    <div class="activation-plan-title">{{ plan.text || 'Plan' }}</div>
                                    <div class="activation-plan-price">
                                        {{ formatPlanPrice(plan.price_per_year) }}
                                        <span v-if="!plan.is_selectable" class="activation-plan-disabled-note"> (nicht auswählbar)</span>
                                    </div>
                                </div>
                            </template>
                        </v-radio>
                    </v-radio-group>

                    <div v-if="activation_dialog_plans.length === 0" class="text-body-2 text-medium-emphasis mt-2">
                        Keine passenden Optionen verfügbar.
                    </div>
                    <div v-else-if="!activation_dialog_plans.some((plan) => plan.is_selectable)" class="text-body-2 text-medium-emphasis mt-2">
                        Aktuell ist keine auswählbare Option verfügbar.
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeActivateUserLicenceDialog" :disabled="activation_dialog_loading">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        @click="commitActivateUserLicence"
                        :loading="activation_dialog_loading"
                        :disabled="activation_dialog_loading || !activation_dialog_selected_plan_id">
                        Bestätigen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deactivation_dialog_open" max-width="520">
            <v-card class="deactivation-dialog-card">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon color="error" size="20">mdi-alert-outline</v-icon>
                    <span>Benutzerlizenz deaktivieren</span>
                </v-card-title>
                <v-card-text>
                    <div class="text-body-2">
                        Sind Sie sicher, dass Sie diese Benutzerlizenz deaktivieren möchten?
                    </div>
                    <div v-if="deactivation_dialog_licence || deactivation_dialog_role_name" class="deactivation-dialog-meta mt-3">
                        <div v-if="deactivation_dialog_licence?.name"><strong>Tool:</strong> {{ deactivation_dialog_licence.name }}</div>
                        <div v-if="deactivation_dialog_role_name"><strong>Rolle:</strong> {{ deactivation_dialog_role_name }}</div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeDeactivateUserLicenceDialog" :disabled="deactivation_dialog_loading">Abbrechen</v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        @click="commitDeactivateUserLicence"
                        :loading="deactivation_dialog_loading"
                        :disabled="deactivation_dialog_loading">
                        Deaktivieren
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="renewal_dialog_open" max-width="560">
            <v-card>
                <v-card-title>Benutzerlizenz verlängern</v-card-title>
                <v-card-text>
                    <div class="text-body-2 mb-3" v-if="renewal_dialog_licence">
                        <strong>{{ renewal_dialog_licence.name }}</strong>
                        <span v-if="renewal_dialog_role_name"> · Rolle: {{ renewal_dialog_role_name }}</span>
                    </div>

                    <div class="text-body-2 mb-2" v-if="renewal_dialog_current_valid_until">
                        Aktuell gültig bis: <strong>{{ formatDateDisplay(renewal_dialog_current_valid_until) }}</strong>
                    </div>
                    <div class="text-body-2 mb-3" v-if="renewal_dialog_new_valid_until">
                        Neu gültig bis: <strong>{{ formatDateDisplay(renewal_dialog_new_valid_until) }}</strong>
                    </div>

                    <div class="text-body-2 mb-2" v-if="!renewal_dialog_payment_active">
                        Nur kostenlose Optionen sind auswählbar.
                    </div>

                    <v-radio-group v-model="renewal_dialog_selected_plan_id" density="compact" hide-details>
                        <v-radio
                            v-for="plan in renewal_dialog_plans"
                            :key="`renewal-plan-${plan.id}`"
                            :value="plan.id"
                            :disabled="!plan.is_selectable">
                            <template #label>
                                <div class="activation-plan-option">
                                    <div class="activation-plan-title">{{ plan.text || 'Plan' }}</div>
                                    <div class="activation-plan-price">
                                        {{ formatPlanPrice(plan.price_per_year) }}
                                        <span v-if="!plan.is_selectable" class="activation-plan-disabled-note"> (nicht auswählbar)</span>
                                    </div>
                                </div>
                            </template>
                        </v-radio>
                    </v-radio-group>

                    <div v-if="renewal_dialog_plans.length === 0" class="text-body-2 text-medium-emphasis mt-2">
                        Keine passenden Optionen verfügbar.
                    </div>
                    <div v-else-if="!renewal_dialog_plans.some((plan) => plan.is_selectable)" class="text-body-2 text-medium-emphasis mt-2">
                        Aktuell ist keine auswählbare Option verfügbar.
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeRenewUserLicenceDialog" :disabled="renewal_dialog_loading">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        @click="commitRenewUserLicence"
                        :loading="renewal_dialog_loading"
                        :disabled="renewal_dialog_loading || !renewal_dialog_selected_plan_id">
                        Bestätigen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useHealthStore } from '@/stores/admin/HealthStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    components: {},

    async beforeMount() {
        if (typeof window.ensureCsrfCookie === 'function') {
            await window.ensureCsrfCookie()
        } else {
            await axios.get('/sanctum/csrf-cookie')
        }
        this.adminStore = useAdminStore()
        this.healthStore = useHealthStore()
        this.schoolStore = useSchoolStore()
        this.adminStore.is_loading++
        try {
            if (this.config?.is_auth && !this.config?.environment_versions) {
                await this.adminStore.loadConfig({
                    includeSchoolInfos: true,
                    includeEnvironmentVersions: this.isAllowed(['admin', 'super_admin']),
                })
            }
            if (this.config?.is_auth) {
                if (this.config?.school_infos) {
                    this.schoolStore.applySchoolInfos(this.config.school_infos)
                } else {
                    await this.schoolStore.loadSchoolInfos(this.config?.selected_school?.id)
                }
                await this.healthStore.fetchStatus()
                this.health_loaded = true
            }
        } finally {
            this.adminStore.is_loading = Math.max(0, Number(this.adminStore.is_loading || 0) - 1)
        }
    },

    mounted() {
        this.health_poll_interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.refreshHealth(false)
            }
        }, 30_000)
        window.addEventListener('focus', this.handleHealthFocus)
    },

    unmounted() {
        if (this.health_poll_interval !== null) {
            window.clearInterval(this.health_poll_interval)
            this.health_poll_interval = null
        }
        window.removeEventListener('focus', this.handleHealthFocus)
    },

    data() {
        return {
            adminStore: null,
            healthStore: null,
            schoolStore: null,
            health_loaded: false,
            health_loading: false,
            health_poll_interval: null,
            queue_test_running: false,
            queue_test_visible: false,
            diagnostics_visible: false,
            version_details_visible: false,
            account_details_visible: false,
            activation_dialog_open: false,
            activation_dialog_loading: false,
            activation_dialog_licence: null,
            activation_dialog_role_name: null,
            activation_dialog_selected_plan_id: null,
            activation_dialog_plans: [],
            activation_dialog_payment_active: false,
            deactivation_dialog_open: false,
            deactivation_dialog_loading: false,
            deactivation_dialog_licence: null,
            deactivation_dialog_role_name: null,
            renewal_dialog_open: false,
            renewal_dialog_loading: false,
            renewal_dialog_licence: null,
            renewal_dialog_role_name: null,
            renewal_dialog_selected_plan_id: null,
            renewal_dialog_plans: [],
            renewal_dialog_payment_active: false,
            renewal_dialog_current_valid_until: null,
            renewal_dialog_new_valid_until: null,
            restart_queues_loading: false,
            restart_countdown: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'health']),
        ...mapWritableState(useSchoolStore, ['school_licences', 'school_admins']),
        appVersion() {
            return this.config?.environment_versions?.app || this.config?.version || 'x.x.x'
        },
        versionItems() {
            const versions = this.config?.environment_versions || {}
            const packages = versions.about?.packages || {}

            return [
                { key: 'laravel', label: 'Laravel', value: versions.laravel },
                { key: 'php', label: 'PHP', value: versions.php },
                { key: 'composer', label: 'Composer', value: versions.composer },
                { key: 'npm', label: 'npm', value: versions.npm },
                { key: 'node', label: 'Node.js', value: versions.node },
                { key: 'vue', label: 'Vue', value: versions.vue },
                { key: 'vuetify', label: 'Vuetify', value: versions.vuetify },
                { key: 'vite', label: 'Vite', value: versions.vite },
                { key: 'pulse', label: 'Pulse', value: packages.pulse },
                { key: 'livewire', label: 'Livewire', value: packages.livewire },
                { key: 'permissions', label: 'Berechtigungen', value: packages.permissions },
            ].map((item) => ({
                ...item,
                value: item.value || 'nicht verfügbar',
            }))
        },
        aboutSections() {
            const about = this.config?.environment_versions?.about || {}
            const environment = about.environment || {}
            const cache = about.cache || {}
            const drivers = about.drivers || {}
            const status = (value, activeLabel, inactiveLabel) => {
                if (typeof value !== 'boolean') {
                    return { value: 'nicht verfügbar', tone: null }
                }

                return {
                    value: value ? activeLabel : inactiveLabel,
                    tone: value ? 'warning' : 'success',
                }
            }
            const cacheStatus = (value) => {
                if (typeof value !== 'boolean') {
                    return { value: 'nicht verfügbar', tone: null }
                }

                return {
                    value: value ? 'gecached' : 'nicht gecached',
                    tone: value ? 'success' : null,
                }
            }
            const debugStatus = status(environment.debug_mode, 'aktiv', 'aus')
            const maintenanceStatus = status(environment.maintenance_mode, 'aktiv', 'aus')

            return [
                {
                    key: 'environment',
                    label: 'Laufzeit',
                    items: [
                        { key: 'environment', label: 'Umgebung', value: environment.environment || 'nicht verfügbar' },
                        { key: 'debug', label: 'Debug-Modus', ...debugStatus },
                        { key: 'maintenance', label: 'Wartungsmodus', ...maintenanceStatus },
                        { key: 'url', label: 'Host', value: environment.url || 'nicht verfügbar' },
                        { key: 'timezone', label: 'Zeitzone', value: environment.timezone || 'nicht verfügbar' },
                        { key: 'locale', label: 'Sprache', value: environment.locale || 'nicht verfügbar' },
                    ],
                },
                {
                    key: 'cache',
                    label: 'Cache',
                    items: [
                        { key: 'config', label: 'Konfiguration', ...cacheStatus(cache.config) },
                        { key: 'events', label: 'Events', ...cacheStatus(cache.events) },
                        { key: 'routes', label: 'Routen', ...cacheStatus(cache.routes) },
                        { key: 'views', label: 'Views', ...cacheStatus(cache.views) },
                    ],
                },
                {
                    key: 'drivers',
                    label: 'Treiber',
                    items: [
                        { key: 'broadcasting', label: 'Broadcasting', value: drivers.broadcasting || 'nicht verfügbar' },
                        { key: 'cache', label: 'Cache', value: drivers.cache || 'nicht verfügbar' },
                        { key: 'database', label: 'Datenbank', value: drivers.database || 'nicht verfügbar' },
                        { key: 'logs', label: 'Logs', value: drivers.logs || 'nicht verfügbar' },
                        { key: 'mail', label: 'Mail', value: drivers.mail || 'nicht verfügbar' },
                        { key: 'queue', label: 'Queue', value: drivers.queue || 'nicht verfügbar' },
                        { key: 'scout', label: 'Scout', value: drivers.scout || 'nicht verfügbar' },
                        { key: 'session', label: 'Session', value: drivers.session || 'nicht verfügbar' },
                    ],
                },
            ]
        },
        activeLicenceCount() {
            const activeSchoolLicences = this.schoolLicencesWithSchoolLicence.filter((licence) => this.isLicenceActive(licence)).length
            const activePersonalLicences = this.myLicenceEntries.filter((entry) => this.isMyLicenceEntryActive(entry)).length

            return activeSchoolLicences + activePersonalLicences
        },
        expiredLicenceCount() {
            const expiredSchoolLicences = this.schoolLicencesWithSchoolLicence.filter((licence) => !this.isLicenceActive(licence)).length
            const expiredPersonalLicences = this.myLicenceEntries.filter((entry) => !this.isMyLicenceEntryActive(entry)).length

            return expiredSchoolLicences + expiredPersonalLicences
        },
        schoolLicencesWithSchoolLicence() {
            return (this.school_licences || []).filter((licence) => licence.school_licence_enabled)
        },
        myLicenceEntries() {
            const entries = []
            for (const licence of this.school_licences || []) {
                if (licence.my_admin_licence) {
                    entries.push({
                        key: `admin-${licence.id}`,
                        licence_name: licence.name,
                        type_label: 'Admin-Lizenz',
                        is_active: licence.my_admin_licence.is_active,
                        valid_until: licence.my_admin_licence.valid_until,
                    })
                }
                if (licence.my_user_licence) {
                    entries.push({
                        key: `user-${licence.id}`,
                        licence_name: licence.name,
                        type_label: 'Benutzer-Lizenz',
                        is_active: licence.my_user_licence.is_active,
                        valid_until: licence.my_user_licence.valid_until,
                    })
                }
            }
            return entries
        },
    },

    methods: {
        isAllowed(roles) {
            return this.config.user.roles.some((role) => roles.includes(role))
        },
        async restartQueues() {
            if (this.restart_queues_loading || this.health_loading) return
            this.restart_queues_loading = true
            this.restart_countdown = 0
            const counterInterval = setInterval(() => {
                this.restart_countdown += 1
            }, 1000)
            try {
                await axios.post('/api/admin/restart_queues')
                await this.refreshHealth()
                useNotificationStore().notify({
                    message: 'Queues wurden neu gestartet und geprüft.',
                    type: 'success',
                    timeout: 5000,
                })
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Neustart der Queues.',
                    type: 'error',
                    timeout: 5000,
                })
            } finally {
                clearInterval(counterInterval)
                this.restart_queues_loading = false
                this.restart_countdown = 0
            }
        },
        handleHealthFocus() {
            this.refreshHealth(false)
        },
        async refreshHealth(notifyOnError = true) {
            if (!this.healthStore || this.health_loading) return
            this.health_loading = true
            try {
                const status = await this.healthStore.fetchStatus({ notifyOnError })
                this.health_loaded = true
                if (status?.is_healthy === true) {
                    this.diagnostics_visible = false
                }
            } finally {
                this.health_loading = false
            }
        },
        async runQueueTest() {
            if (!this.healthStore || this.queue_test_running) return
            this.queue_test_running = true
            this.queue_test_visible = true
            try {
                const result = await this.healthStore.testQueue()
                if (!result?.test_id) {
                    this.queue_test_running = false
                    return
                }

                let attempts = 0
                const maxAttempts = 15

                while (attempts < maxAttempts) {
                    await new Promise((resolve) => setTimeout(resolve, 1000))
                    const status = await this.healthStore.checkQueueTest(result.test_id)
                    if (!status) break
                    if (status.is_completed) break
                    attempts++
                }
            } catch {
                // queue_test store state reflects failure
            } finally {
                this.queue_test_running = false
            }
        },
        formatHeartbeat(isoString) {
            if (!isoString) return 'Nie'
            try {
                const date = new Date(isoString)
                if (isNaN(date.getTime())) return isoString
                const hours = String(date.getHours()).padStart(2, '0')
                const minutes = String(date.getMinutes()).padStart(2, '0')
                const seconds = String(date.getSeconds()).padStart(2, '0')
                return `${hours}:${minutes}:${seconds}`
            } catch {
                return isoString
            }
        },

        user(id) {
            return this.school_admins.find((a) => a.id === id)
        },
        localDateKey(date = new Date()) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        formatDateDisplay(value) {
            if (value == null) return ''
            const raw = String(value).trim()
            if (!raw) return ''

            // Keep date-only values stable (avoid timezone shifts from Date parsing).
            const plainDateMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/)
            if (plainDateMatch) {
                const [, year, month, day] = plainDateMatch
                return `${day}.${month}.${year}`
            }

            const parsed = new Date(raw)
            if (!Number.isNaN(parsed.getTime())) {
                const year = parsed.getFullYear()
                const month = String(parsed.getMonth() + 1).padStart(2, '0')
                const day = String(parsed.getDate()).padStart(2, '0')
                return `${day}.${month}.${year}`
            }

            return raw
        },
        toBool(value, fallback = false) {
            if (typeof value === 'boolean') return value
            if (typeof value === 'number') return value === 1
            if (typeof value === 'string') {
                const normalized = value.trim().toLowerCase()
                if (['1', 'true', 'yes', 'ja'].includes(normalized)) return true
                if (['0', 'false', 'no', 'nein'].includes(normalized)) return false
            }
            return fallback
        },
        isLicenceActive(licence) {
            const schoolLicenceRequired = this.toBool(licence?.licence_model?.school_licence_required, true)
            if (!schoolLicenceRequired) return true
            const validUntil = licence?.valid_until
            if (!validUntil) return true
            return String(validUntil) >= this.localDateKey()
        },
        isMyLicenceEntryActive(entry) {
            if (!entry?.is_active) return false
            const validUntil = entry?.valid_until
            if (!validUntil) return true
            return String(validUntil) >= this.localDateKey()
        },
        userLicenceRoleEntries(licence) {
            const roles = licence?.current_user_licence?.roles
            return Array.isArray(roles) ? roles.filter((entry) => entry && typeof entry === 'object' && entry.role_name) : []
        },
        isUserLicenceRoleActive(roleEntry) {
            return !!roleEntry?.is_active
        },
        userLicenceRoleDotClass(roleEntry) {
            if (!this.isUserLicenceRoleActive(roleEntry)) return 'is-inactive'
            if (this.shouldShowRenewUserLicenceButton(roleEntry)) return 'is-warning'
            return 'is-active'
        },
        licenceRenewalDays() {
            const raw = Number(this.config?.licence_renewal_days)
            return Number.isFinite(raw) && raw >= 0 ? raw : 30
        },
        parseDateOnly(value) {
            if (value == null) return null
            const raw = String(value).trim()
            if (!raw) return null
            const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})/)
            if (!m) return null
            const year = Number(m[1])
            const monthIndex = Number(m[2]) - 1
            const day = Number(m[3])
            const date = new Date(year, monthIndex, day)
            return Number.isNaN(date.getTime()) ? null : date
        },
        daysRemainingUntil(value) {
            const target = this.parseDateOnly(value)
            if (!target) return null
            const today = new Date()
            const startToday = new Date(today.getFullYear(), today.getMonth(), today.getDate())
            const diffMs = target.getTime() - startToday.getTime()
            return Math.floor(diffMs / 86400000)
        },
        shouldShowRenewUserLicenceButton(roleEntry) {
            if (!this.isUserLicenceRoleActive(roleEntry)) return false
            if (!roleEntry?.valid_until) return false
            const remaining = this.daysRemainingUntil(roleEntry.valid_until)
            if (remaining == null) return false
            return remaining <= this.licenceRenewalDays()
        },
        addOneYearToDateString(value) {
            const date = this.parseDateOnly(value)
            if (!date) return null
            const next = new Date(date.getFullYear() + 1, date.getMonth(), date.getDate())
            const y = next.getFullYear()
            const m = String(next.getMonth() + 1).padStart(2, '0')
            const d = String(next.getDate()).padStart(2, '0')
            return `${y}-${m}-${d}`
        },
        buildRolePlanOptions(licence, roleName, paymentActive) {
            const plansByRole = licence?.licence_model?.user_licence_plans_by_role || {}
            const rawPlans = Array.isArray(plansByRole[roleName]) ? plansByRole[roleName] : []
            return rawPlans
                .filter((plan) => plan && typeof plan === 'object')
                .map((plan) => ({
                    id: Number(plan.id),
                    text: typeof plan.text === 'string' ? plan.text : String(plan.text ?? ''),
                    price_per_year: typeof plan.price_per_year === 'string' ? plan.price_per_year : String(plan.price_per_year ?? ''),
                    is_selectable: paymentActive || this.isFreeUserLicencePrice(plan.price_per_year),
                }))
                .filter((plan) => Number.isInteger(plan.id) && plan.id > 0)
        },
        openActivateUserLicenceDialog(licence, forcedRoleName = null) {
            const notification = useNotificationStore()
            const currentUserLicence = licence?.current_user_licence || {}
            const primaryRoleName = currentUserLicence?.role_name || null
            const normalizedForcedRoleName = typeof forcedRoleName === 'string' && forcedRoleName.trim() ? forcedRoleName.trim() : null

            const relevantRoleNames = Array.isArray(currentUserLicence?.roles)
                ? currentUserLicence.roles
                      .map((entry) => (entry && typeof entry === 'object' ? String(entry.role_name || '').trim() : ''))
                      .filter((roleName, index, arr) => roleName && arr.indexOf(roleName) === index)
                : []

            const candidateRoleNames = normalizedForcedRoleName
                ? [normalizedForcedRoleName]
                : [
                      ...(primaryRoleName ? [String(primaryRoleName).trim()] : []),
                      ...relevantRoleNames,
                  ].filter((roleName, index, arr) => roleName && arr.indexOf(roleName) === index)

            if (candidateRoleNames.length === 0) {
                notification.notify({
                    status: 422,
                    message: 'Keine Benutzerlizenz-Rolle gefunden.',
                    type: 'warning',
                    timeout: 3000,
                })
                return
            }

            const paymentActive = this.toBool(this.config?.payment_active, false)
            const roleOptions = candidateRoleNames
                .map((roleName) => {
                    const plans = this.buildRolePlanOptions(licence, roleName, paymentActive)
                    return { roleName, plans }
                })
                .filter((entry) => entry.plans.length > 0)

            if (roleOptions.length === 0) {
                notification.notify({
                    status: 422,
                    message: paymentActive ? 'Keine Optionen verfügbar.' : 'Für diese Funktion ist keine kostenlose Option verfügbar.',
                    type: 'warning',
                    timeout: 3500,
                })
                return
            }

            const selectableRoleOptions = roleOptions.filter((entry) => entry.plans.some((plan) => plan.is_selectable))
            const selectedRoleOptions = normalizedForcedRoleName
                ? (roleOptions.find((entry) => entry.roleName === normalizedForcedRoleName) || roleOptions[0])
                : (selectableRoleOptions.find((entry) => entry.roleName === primaryRoleName) ||
                    selectableRoleOptions[0] ||
                    roleOptions.find((entry) => entry.roleName === primaryRoleName) ||
                    roleOptions[0])
            const roleName = selectedRoleOptions.roleName
            const plans = selectedRoleOptions.plans

            this.activation_dialog_licence = licence
            this.activation_dialog_role_name = roleName
            this.activation_dialog_plans = plans
            const preferredPlanId = licence?.current_user_licence?.plan_id || null
            const preferredPlan = plans.find((plan) => plan.id === preferredPlanId && plan.is_selectable)
            const firstSelectablePlan = plans.find((plan) => plan.is_selectable)
            this.activation_dialog_selected_plan_id = preferredPlan?.id || firstSelectablePlan?.id || null
            this.activation_dialog_payment_active = paymentActive
            this.activation_dialog_open = true
        },
        closeActivateUserLicenceDialog() {
            this.activation_dialog_open = false
            this.activation_dialog_loading = false
            this.activation_dialog_licence = null
            this.activation_dialog_role_name = null
            this.activation_dialog_selected_plan_id = null
            this.activation_dialog_plans = []
            this.activation_dialog_payment_active = false
        },
        openRenewUserLicenceDialog(licence, roleEntry) {
            const notification = useNotificationStore()
            const roleName = roleEntry?.role_name ? String(roleEntry.role_name).trim() : ''
            if (!roleName) return

            const currentValidUntil = roleEntry?.valid_until || null
            const newValidUntil = this.addOneYearToDateString(currentValidUntil)
            if (!currentValidUntil || !newValidUntil) {
                notification.notify({
                    status: 422,
                    message: 'Aktuelles Gültigkeitsdatum fehlt oder ist ungültig.',
                    type: 'warning',
                    timeout: 3500,
                })
                return
            }

            const paymentActive = this.toBool(this.config?.payment_active, false)
            const plans = this.buildRolePlanOptions(licence, roleName, paymentActive)
            if (plans.length === 0) {
                notification.notify({
                    status: 422,
                    message: paymentActive ? 'Keine Optionen verfügbar.' : 'Für diese Funktion ist keine kostenlose Option verfügbar.',
                    type: 'warning',
                    timeout: 3500,
                })
                return
            }

            this.renewal_dialog_licence = licence
            this.renewal_dialog_role_name = roleName
            this.renewal_dialog_plans = plans
            this.renewal_dialog_current_valid_until = currentValidUntil
            this.renewal_dialog_new_valid_until = newValidUntil
            const preferredPlanId = roleEntry?.plan_id || null
            const preferredPlan = plans.find((plan) => plan.id === preferredPlanId && plan.is_selectable)
            const firstSelectablePlan = plans.find((plan) => plan.is_selectable)
            this.renewal_dialog_selected_plan_id = preferredPlan?.id || firstSelectablePlan?.id || null
            this.renewal_dialog_payment_active = paymentActive
            this.renewal_dialog_open = true
        },
        closeRenewUserLicenceDialog() {
            this.renewal_dialog_open = false
            this.renewal_dialog_loading = false
            this.renewal_dialog_licence = null
            this.renewal_dialog_role_name = null
            this.renewal_dialog_selected_plan_id = null
            this.renewal_dialog_plans = []
            this.renewal_dialog_payment_active = false
            this.renewal_dialog_current_valid_until = null
            this.renewal_dialog_new_valid_until = null
        },
        async commitRenewUserLicence() {
            const notification = useNotificationStore()
            const licence = this.renewal_dialog_licence
            const schoolLicenceId = licence?.school_licence_id
            const roleName = this.renewal_dialog_role_name
            const planId = Number(this.renewal_dialog_selected_plan_id)
            if (!schoolLicenceId || !roleName || !Number.isInteger(planId) || planId <= 0) return

            this.renewal_dialog_loading = true
            try {
                const response = await axios.post(`/api/admin/school_licences/${schoolLicenceId}/renew_user_licence`, {
                    role_name: roleName,
                    plan_id: planId,
                })

                const message = response?.data?.message || 'Erfolgreich.'
                if (message === 'Payment') {
                    notification.notify({ message: 'Payment', type: 'info', timeout: 3000 })
                    this.closeRenewUserLicenceDialog()
                    return
                }

                notification.notify({ message, type: 'success', timeout: 3000 })
                if (this.config?.selected_school?.id) {
                    await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
                }
                if (this.adminStore?.loadConfig) {
                    await this.adminStore.loadConfig()
                }
                this.closeRenewUserLicenceDialog()
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3500,
                })
            } finally {
                this.renewal_dialog_loading = false
            }
        },
        normalizePriceToNumber(rawPrice) {
            const raw = rawPrice == null ? '' : String(rawPrice).trim()
            if (!raw) return 0

            const lower = raw.toLowerCase()
            if (['kostenlos', 'gratis', 'free'].some((token) => lower.includes(token))) {
                return 0
            }

            // Accept German notations like "0,-", "1.200,00", "EUR 0 / Jahr".
            let normalized = raw.replace(/\s+/g, '')
            if (normalized.includes(',') && normalized.includes('.')) {
                // Treat dot as thousands separator and comma as decimal separator.
                normalized = normalized.replace(/\./g, '').replace(/,/g, '.')
            } else {
                normalized = normalized.replace(/,/g, '.')
            }

            // Strip everything except digits/sign/dot, then clean common trailing price notation.
            normalized = normalized.replace(/[^0-9.\-]/g, '')
            normalized = normalized.replace(/([0-9])\.(?=-|$)/g, '$1') // "0.-" -> "0"
            normalized = normalized.replace(/([0-9])-(?=$)/g, '$1') // "0-" -> "0"

            const match = normalized.match(/-?\d+(?:\.\d+)?/)
            if (!match) return Number.NaN

            return Number(match[0])
        },
        isFreeUserLicencePrice(rawPrice) {
            const parsed = this.normalizePriceToNumber(rawPrice)
            return Number.isFinite(parsed) && parsed <= 0
        },
        formatPlanPrice(rawPrice) {
            return this.isFreeUserLicencePrice(rawPrice) ? 'Kostenlos' : `EUR ${rawPrice} / Jahr`
        },
        async commitActivateUserLicence() {
            const notification = useNotificationStore()
            const licence = this.activation_dialog_licence
            const schoolLicenceId = licence?.school_licence_id
            const roleName = this.activation_dialog_role_name
            const planId = Number(this.activation_dialog_selected_plan_id)

            if (!schoolLicenceId || !roleName || !Number.isInteger(planId) || planId <= 0) return

            this.activation_dialog_loading = true
            try {
                const response = await axios.post(`/api/admin/school_licences/${schoolLicenceId}/activate_user_licence`, {
                    role_name: roleName,
                    plan_id: planId,
                })

                const message = response?.data?.message || 'Erfolgreich.'
                if (message === 'Payment') {
                    notification.notify({
                        message: 'Payment',
                        type: 'info',
                        timeout: 3000,
                    })
                    this.closeActivateUserLicenceDialog()
                    return
                }

                notification.notify({
                    message,
                    type: 'success',
                    timeout: 3000,
                })

                if (this.config?.selected_school?.id) {
                    await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
                }
                if (this.adminStore?.loadConfig) {
                    await this.adminStore.loadConfig()
                }
                this.closeActivateUserLicenceDialog()
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3500,
                })
            } finally {
                this.activation_dialog_loading = false
            }
        },
        confirmDeactivateUserLicence(licence, roleEntry) {
            const roleName = roleEntry?.role_name ? String(roleEntry.role_name).trim() : ''
            const schoolLicenceId = licence?.school_licence_id
            if (!roleName || !schoolLicenceId) return

            this.deactivation_dialog_licence = licence
            this.deactivation_dialog_role_name = roleName
            this.deactivation_dialog_open = true
        },
        closeDeactivateUserLicenceDialog() {
            this.deactivation_dialog_open = false
            this.deactivation_dialog_loading = false
            this.deactivation_dialog_licence = null
            this.deactivation_dialog_role_name = null
        },
        async commitDeactivateUserLicence() {
            const notification = useNotificationStore()
            const roleName = this.deactivation_dialog_role_name ? String(this.deactivation_dialog_role_name).trim() : ''
            const schoolLicenceId = this.deactivation_dialog_licence?.school_licence_id
            if (!roleName || !schoolLicenceId) return

            try {
                this.deactivation_dialog_loading = true
                const response = await axios.post(`/api/admin/school_licences/${schoolLicenceId}/deactivate_user_licence`, {
                    role_name: roleName,
                })

                notification.notify({
                    message: response?.data?.message || 'Benutzerlizenz deaktiviert.',
                    type: 'success',
                    timeout: 3000,
                })

                if (this.config?.selected_school?.id) {
                    await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
                }
                if (this.adminStore?.loadConfig) {
                    await this.adminStore.loadConfig()
                }
                this.closeDeactivateUserLicenceDialog()
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3500,
                })
            } finally {
                this.deactivation_dialog_loading = false
            }
        },
    },
}
</script>

<style scoped>
.admin-dashboard-page {
    min-height: 100vh;
    background: #f7f8fa;
    color: #25332c;
}

.admin-dashboard-page__content {
    max-width: none;
    margin-inline: 0;
    padding: 0;
}

.admin-dashboard-page__onebar {
    min-height: 76px;
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 10px 26px;
    background: #ffffff;
    box-shadow: 0 4px 18px rgb(24 34 48 / 4%);
}

.admin-dashboard-page__brand {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 0 0 auto;
}

.admin-dashboard-page__brand-mark {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgb(var(--v-theme-primary));
    color: #ffffff;
    box-shadow: 0 6px 15px rgba(var(--v-theme-primary), 0.2);
}

.admin-dashboard-page__brand-name {
    font-size: 0.95rem;
    font-weight: 750;
    line-height: 1.15;
}

.admin-dashboard-page__eyebrow {
    color: #7a8580;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    line-height: 1.3;
    text-transform: uppercase;
}

.admin-dashboard-page__health {
    display: flex;
    align-items: center;
    gap: 9px;
    padding-left: 20px;
    border-left: 1px solid #edf0ee;
}

.admin-dashboard-page__health-icon {
    width: 30px;
    height: 30px;
    display: grid;
    flex: 0 0 30px;
    place-items: center;
    border-radius: 9px;
    background: #f7eedc;
    color: #9a6a2f;
}

.admin-dashboard-page__health-icon--healthy {
    background: #dff7f2;
    color: #078b7a;
}

.admin-dashboard-page__health-icon--error {
    background: #f7e7e8;
    color: #a34e52;
}

.admin-dashboard-page__health-title {
    font-size: 0.78rem;
    font-weight: 750;
    line-height: 1.2;
}

.admin-dashboard-page__health-detail {
    margin-top: 2px;
    color: #7a8580;
    font-size: 0.65rem;
    line-height: 1.2;
}

.admin-dashboard-page__metadata {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
}

.admin-dashboard-page__meta-chip,
.admin-dashboard-page__role,
.admin-dashboard-page__licence-summary span {
    padding: 6px 9px;
    border-radius: 8px;
    background: #f2f4f7;
    color: #59655f;
    font-size: 0.65rem;
    font-weight: 700;
    line-height: 1;
}

.admin-dashboard-page__diagnosis-trigger {
    margin-left: auto;
}

.admin-dashboard-page__metadata + .admin-dashboard-page__diagnosis-trigger {
    margin-left: 0;
}

.admin-dashboard-page__diagnosis-trigger,
.admin-dashboard-page__diagnostics :deep(.v-btn) {
    border-radius: 9px;
    font-size: 0.68rem;
    letter-spacing: 0;
    text-transform: none;
}

.admin-dashboard-page__main {
    max-width: 1224px;
    padding: 28px 34px 40px;
}

.admin-dashboard-page__diagnostics {
    margin-bottom: 12px;
    padding: 18px 20px;
    border: 1px solid #efc9cb;
    border-radius: 14px;
    background: #fffafa;
}

.admin-dashboard-page__diagnostics-heading,
.admin-dashboard-page__diagnostics-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.admin-dashboard-page__diagnostics-heading h2 {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.2;
}

.admin-dashboard-page__diagnostics-heading p {
    margin: 3px 0 0;
    color: #7a8580;
    font-size: 0.68rem;
}

.admin-dashboard-page__diagnostics-statuses {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-top: 14px;
}

.admin-dashboard-page__diagnostics-statuses > div {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 2px 12px;
    padding: 11px 13px;
    border-radius: 10px;
    background: #ffffff;
}

.admin-dashboard-page__diagnostics-statuses span,
.admin-dashboard-page__diagnostics-statuses strong {
    font-size: 0.72rem;
}

.admin-dashboard-page__diagnostics-statuses strong {
    color: #367356;
}

.admin-dashboard-page__diagnostics-statuses small {
    grid-column: 1 / -1;
    color: #7a8580;
    font-size: 0.64rem;
}

.admin-dashboard-page__diagnostics-statuses .admin-dashboard-page__diagnostics-error {
    color: #a34e52;
}

.admin-dashboard-page__queue-result {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding: 9px 12px;
    border-radius: 9px;
    background: #f7e7e8;
    color: #8e4448;
    font-size: 0.68rem;
    font-weight: 700;
}

.admin-dashboard-page__queue-result--success {
    background: #dff4f1;
    color: #087e70;
}

.admin-dashboard-page__diagnostics-actions {
    justify-content: flex-end;
    margin-top: 12px;
}

.admin-dashboard-page__overview-grid,
.admin-dashboard-page__licence-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.625fr) minmax(320px, 1fr);
    gap: 12px;
}

.admin-dashboard-page__panel {
    min-width: 0;
    padding: 20px;
    border-color: #e4e7ec;
    background: #ffffff;
}

.admin-dashboard-page__overview-grid > .admin-dashboard-page__panel {
    min-height: 0;
}

.admin-dashboard-page__version-heading,
.admin-dashboard-page__account-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.admin-dashboard-page__version-heading :deep(.v-btn),
.admin-dashboard-page__account-heading :deep(.v-btn) {
    margin-top: 1px;
    border-radius: 9px;
    font-size: 0.68rem;
    letter-spacing: 0;
    text-transform: none;
}

.admin-dashboard-page__section-title {
    margin: 3px 0 0;
    color: #25332c;
    font-size: 1.08rem;
    font-weight: 750;
    line-height: 1.25;
}

.admin-dashboard-page__app-version {
    margin-top: 8px;
    color: rgb(var(--v-theme-primary));
    font-size: 1.65rem;
    font-weight: 750;
    line-height: 1.1;
}

.admin-dashboard-page__version-details {
    margin-top: 18px;
}

.admin-dashboard-page__tech-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}

.admin-dashboard-page__tech-item {
    min-width: 0;
    padding: 11px 13px;
    border-radius: 10px;
    background: #f7f8fa;
}

.admin-dashboard-page__tech-item span,
.admin-dashboard-page__tech-item strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-dashboard-page__tech-item span {
    color: #7a8580;
    font-size: 0.65rem;
}

.admin-dashboard-page__tech-item strong {
    margin-top: 3px;
    font-size: 0.78rem;
}

.admin-dashboard-page__about-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: 8px;
}

.admin-dashboard-page__about-section {
    min-width: 0;
    padding: 13px;
    border: 1px solid #edf0ee;
    border-radius: 10px;
}

.admin-dashboard-page__about-section h2 {
    margin: 0 0 8px;
    color: #55615b;
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.admin-dashboard-page__about-section dl {
    margin: 0;
}

.admin-dashboard-page__about-section dl > div {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    padding: 5px 0;
    border-top: 1px solid #f1f3f2;
}

.admin-dashboard-page__about-section dt,
.admin-dashboard-page__about-section dd {
    overflow: hidden;
    margin: 0;
    font-size: 0.68rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-dashboard-page__about-section dt {
    color: #7a8580;
}

.admin-dashboard-page__about-section dd {
    color: #34423b;
    font-weight: 700;
    text-align: right;
}

.admin-dashboard-page__about-value--success {
    color: #367356 !important;
}

.admin-dashboard-page__about-value--warning {
    color: #9b6321 !important;
}

.admin-dashboard-page__account {
    margin-top: 8px;
}

.admin-dashboard-page__account-copy {
    min-width: 0;
}

.admin-dashboard-page__account-copy strong,
.admin-dashboard-page__account-copy span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-dashboard-page__account-copy .admin-dashboard-page__account-name {
    color: rgb(var(--v-theme-primary));
    font-size: 1.65rem;
    font-weight: 750;
    line-height: 1.1;
}

.admin-dashboard-page__account-copy span {
    margin-top: 8px;
    color: #7a8580;
    font-size: 0.72rem;
}

.admin-dashboard-page__account-details {
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid #edf0ee;
}

.admin-dashboard-page__roles {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 14px;
}

.admin-dashboard-page__role {
    background: #edf8f6;
    color: #087e70;
}

.admin-dashboard-page__licences {
    margin-top: 0;
}

.admin-dashboard-page__licence-heading {
    min-height: 120px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    padding: 30px 2px 14px;
}

.admin-dashboard-page__licence-title {
    margin: 3px 0 0;
    font-size: 1.35rem;
    font-weight: 750;
    line-height: 1.2;
}

.admin-dashboard-page__licence-heading p {
    margin: 4px 0 0;
    color: #7a8580;
    font-size: 0.76rem;
}

.admin-dashboard-page__licence-summary {
    display: flex;
    gap: 6px;
    padding-bottom: 2px;
}

.admin-dashboard-page__licence-summary span {
    background: #dff4f1;
    color: #087e70;
}

.admin-dashboard-page__licence-summary .admin-dashboard-page__summary-expired {
    background: #f7e7e8;
    color: #a34e52;
}

.admin-dashboard-page__licence-panel {
    min-height: 260px;
}

.admin-dashboard-page__licence-panel-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.admin-dashboard-page__licence-panel-heading h3 {
    margin: 3px 0 0;
    font-size: 0.98rem;
}

.admin-dashboard-page__licence-panel-heading > span {
    min-width: 24px;
    padding: 5px 8px;
    border-radius: 8px;
    background: #f2f4f7;
    color: #66716b;
    font-size: 0.68rem;
    font-weight: 750;
    text-align: center;
}

.admin-dashboard-page__licence-list {
    display: grid;
    gap: 8px;
}

.admin-dashboard-page__licence-row {
    min-width: 0;
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) auto;
    align-items: center;
    gap: 11px;
    padding: 11px 12px;
    border: 1px solid #eaecf0;
    border-radius: 11px;
}

.admin-dashboard-page__licence-symbol {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #edf8f6;
    color: #087e70;
}

.admin-dashboard-page__licence-copy {
    min-width: 0;
}

.admin-dashboard-page__licence-copy strong,
.admin-dashboard-page__licence-copy span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-dashboard-page__licence-copy strong {
    font-size: 0.78rem;
}

.admin-dashboard-page__licence-copy span {
    margin-top: 2px;
    color: #7a8580;
    font-size: 0.66rem;
}

.admin-dashboard-page__licence-status {
    padding: 5px 8px;
    border-radius: 999px;
    background: #dff4f1;
    color: #087e70;
    font-size: 0.62rem;
    font-weight: 750;
    white-space: nowrap;
}

.admin-dashboard-page__licence-status--expired {
    background: #f7e7e8;
    color: #a34e52;
}

.admin-dashboard-page__empty {
    min-height: 158px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 22px;
    border-radius: 12px;
    background: #f8f9fa;
    color: #7a8580;
    text-align: center;
}

.admin-dashboard-page__empty strong {
    color: #4f5c55;
    font-size: 0.8rem;
}

.admin-dashboard-page__empty span {
    max-width: 280px;
    font-size: 0.7rem;
    line-height: 1.4;
}

@media (max-width: 1099px) {
    .admin-dashboard-page__onebar {
        flex-wrap: wrap;
    }

    .admin-dashboard-page__metadata {
        display: none;
    }

    .admin-dashboard-page__overview-grid,
    .admin-dashboard-page__licence-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 699px) {
    .admin-dashboard-page__onebar {
        gap: 10px;
        padding: 10px 16px;
    }

    .admin-dashboard-page__health {
        display: none;
    }

    .admin-dashboard-page__main {
        padding: 18px 16px 30px;
    }

    .admin-dashboard-page__panel {
        padding: 16px;
    }

    .admin-dashboard-page__diagnostics-statuses {
        grid-template-columns: 1fr;
    }

    .admin-dashboard-page__diagnostics-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .admin-dashboard-page__diagnostics-actions :deep(.v-btn) {
        width: 100%;
    }

    .admin-dashboard-page__tech-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-dashboard-page__about-grid {
        grid-template-columns: 1fr;
    }

    .admin-dashboard-page__version-heading,
    .admin-dashboard-page__account-heading {
        align-items: flex-end;
    }

    .admin-dashboard-page__licence-heading {
        min-height: auto;
        align-items: flex-start;
        flex-direction: column;
        padding: 28px 2px 14px;
    }

    .admin-dashboard-page__licence-row {
        grid-template-columns: 34px minmax(0, 1fr);
    }

    .admin-dashboard-page__licence-status {
        grid-column: 2;
        justify-self: start;
    }
}
</style>
