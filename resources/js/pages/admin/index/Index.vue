<template>
    <div class="admin-index-page" v-if="config && config.is_auth">
        <div class="admin-bg">
            <div class="admin-bg-image ai-cloudflare-bg-image ai-cloudflare-bg-image--dark"></div>
            <div class="admin-bg-glow ai-glow-blob admin-bg-glow-left"></div>
            <div class="admin-bg-glow ai-glow-blob admin-bg-glow-right"></div>
        </div>

        <section class="admin-hero">
            <div class="admin-shell ai-shell-1320">
                <header class="admin-header ai-glass-panel">
                    <div class="admin-brand">
                        <div class="admin-brand-badge ai-brand-badge">
                            <v-icon size="20" color="white">mdi-school</v-icon>
                        </div>
                        <div>
                            <div class="admin-brand-eyebrow">Admin Dashboard</div>
                            <h1 class="admin-brand-title">SchoolTool</h1>
                        </div>
                    </div>

                    <div class="admin-header-meta">
                        <div class="admin-meta-pill">
                            <span>Version</span>
                            <strong>{{ config?.version }}</strong>
                        </div>
                        <div class="admin-meta-pill" v-if="config?.selected_school?.long_name || config?.selected_school?.name">
                            <span>Schule</span>
                            <strong>{{ config?.selected_school?.long_name || config?.selected_school?.name }}</strong>
                        </div>
                    </div>
                </header>

                <div class="admin-hero-copy">
                    <h2 class="admin-hero-title">Zentrale Übersicht</h2>
                </div>

                <div class="admin-kpi-grid">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">System-Health</div>
                        <div class="kpi-value">
                            <v-icon
                                size="20"
                                :icon="health_loaded ? (healthStore.is_healthy ? 'mdi-checkbox-marked-circle' : 'mdi-alert-circle') : 'mdi-progress-clock'"
                                :color="health_loaded ? (healthStore.is_healthy ? 'success' : 'error') : 'warning'" />
                            <span>{{ health_loaded ? (healthStore.is_healthy ? 'OK' : 'Fehler') : 'Wird geladen...' }}</span>
                        </div>
                        <div v-if="isAllowed(['admin', 'super_admin'])" class="mt-3">
                            <div v-if="restart_queues_loading" class="restart-status mb-2">
                                <v-icon size="16" class="restart-status-icon mdi-spin mr-1">mdi-loading</v-icon>
                                <span class="text-caption restart-status-text">Neustart + Tests laufen... {{ restart_countdown }}s</span>
                            </div>
                            <v-btn
                                size="small"
                                variant="flat"
                                color="warning"
                                prepend-icon="mdi-restart"
                                :loading="restart_queues_loading"
                                :disabled="restart_queues_loading || health_loading"
                                block
                                @click="restartQueues">
                                Queues neu starten
                            </v-btn>
                        </div>
                    </div>

                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Aktive Lizenzen</div>
                        <div class="kpi-value">{{ activeLicenceCount }}</div>
                        <div class="kpi-sub" v-if="expiredLicenceCount > 0">{{ expiredLicenceCount }} abgelaufen</div>
                        <div class="kpi-sub" v-else>Keine abgelaufenen Lizenzen</div>
                    </div>
                </div>

                <div class="admin-content-grid">
                    <section class="admin-card ai-glass-panel admin-card-tests">
                        <div class="admin-card-head" style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div class="admin-card-eyebrow">Monitoring</div>
                                <h3 class="admin-card-title">System-Health</h3>
                            </div>
                            <div class="d-flex ga-2">
                                <v-btn
                                    v-if="isAllowed(['admin', 'super_admin'])"
                                    size="small"
                                    variant="flat"
                                    color="primary"
                                    rounded="lg"
                                    prepend-icon="mdi-refresh"
                                    @click="refreshHealth"
                                    :loading="health_loading"
                                    :disabled="health_loading">
                                    Status prüfen
                                </v-btn>
                                <v-btn
                                    v-if="isAllowed(['admin', 'super_admin'])"
                                    size="small"
                                    variant="tonal"
                                    color="secondary"
                                    rounded="lg"
                                    prepend-icon="mdi-play-circle-outline"
                                    @click="runQueueTest"
                                    :loading="queue_test_running"
                                    :disabled="queue_test_running || health_loading">
                                    Queue testen
                                </v-btn>
                            </div>
                        </div>

                        <div class="status-list">
                            <div class="status-row">
                                <div class="status-copy">
                                    <div class="status-name">Gesamtstatus</div>
                                    <div class="status-note" v-if="!health_loaded">Wird geladen...</div>
                                    <div class="status-note" v-else-if="healthStore.is_healthy">Scheduler und Worker laufen</div>
                                    <div class="status-note" v-else>Mindestens ein Dienst antwortet nicht</div>
                                </div>
                                <div class="status-badge" :class="!health_loaded ? 'is-waiting' : healthStore.is_healthy ? 'is-success' : 'is-error'">
                                    <v-icon
                                        size="16"
                                        :icon="!health_loaded ? 'mdi-help-circle-outline' : healthStore.is_healthy ? 'mdi-check-circle' : 'mdi-alert-circle'" />
                                    <span>{{ !health_loaded ? 'Laden...' : healthStore.is_healthy ? 'OK' : 'Fehler' }}</span>
                                </div>
                            </div>

                            <div class="status-row">
                                <div class="status-copy">
                                    <div class="status-name">Scheduler</div>
                                    <div class="status-note" v-if="!health_loaded">Wird geladen...</div>
                                    <div class="status-note" v-else-if="healthStore.scheduler?.is_healthy">Letzter Heartbeat: {{ formatHeartbeat(healthStore.scheduler.last_heartbeat) }}</div>
                                    <div class="status-note" v-else-if="healthStore.scheduler?.last_heartbeat">Letzter Heartbeat: {{ formatHeartbeat(healthStore.scheduler.last_heartbeat) }} (veraltet)</div>
                                    <div class="status-note" v-else>Kein Heartbeat empfangen</div>
                                </div>
                                <div class="status-badge" :class="!health_loaded ? 'is-waiting' : healthStore.scheduler?.is_healthy ? 'is-success' : 'is-error'">
                                    <v-icon
                                        size="16"
                                        :icon="!health_loaded ? 'mdi-help-circle-outline' : healthStore.scheduler?.is_healthy ? 'mdi-check-circle' : 'mdi-alert-circle'" />
                                    <span>{{ !health_loaded ? 'Laden...' : healthStore.scheduler?.is_healthy ? 'OK' : 'Fehler' }}</span>
                                </div>
                            </div>

                            <div class="status-row">
                                <div class="status-copy">
                                    <div class="status-name">Worker</div>
                                    <div class="status-note" v-if="!health_loaded">Wird geladen...</div>
                                    <div class="status-note" v-else-if="healthStore.worker?.is_healthy">Letzter Heartbeat: {{ formatHeartbeat(healthStore.worker.last_heartbeat) }}</div>
                                    <div class="status-note" v-else-if="healthStore.worker?.last_heartbeat">Letzter Heartbeat: {{ formatHeartbeat(healthStore.worker.last_heartbeat) }} (veraltet)</div>
                                    <div class="status-note" v-else>Kein Heartbeat empfangen</div>
                                </div>
                                <div class="status-badge" :class="!health_loaded ? 'is-waiting' : healthStore.worker?.is_healthy ? 'is-success' : 'is-error'">
                                    <v-icon
                                        size="16"
                                        :icon="!health_loaded ? 'mdi-help-circle-outline' : healthStore.worker?.is_healthy ? 'mdi-check-circle' : 'mdi-alert-circle'" />
                                    <span>{{ !health_loaded ? 'Laden...' : healthStore.worker?.is_healthy ? 'OK' : 'Fehler' }}</span>
                                </div>
                            </div>

                            <div class="status-row" v-if="queue_test_visible">
                                <div class="status-copy">
                                    <div class="status-name">Queue-Test</div>
                                    <div class="status-note" v-if="queue_test_running">Job wird verarbeitet...</div>
                                    <div class="status-note" v-else-if="healthStore.queue_test?.is_completed">Verarbeitet in {{ healthStore.queue_test.duration_seconds }}s</div>
                                    <div class="status-note" v-else-if="healthStore.queue_test?.status === 'dispatched'">Job wartend in Warteschlange</div>
                                    <div class="status-note" v-else>Test fehlgeschlagen</div>
                                </div>
                                <div class="status-badge" :class="queue_test_running ? 'is-running' : healthStore.queue_test?.is_completed ? 'is-success' : 'is-error'">
                                    <v-icon
                                        size="16"
                                        :class="{ 'mdi-spin': queue_test_running }"
                                        :icon="queue_test_running ? 'mdi-loading' : healthStore.queue_test?.is_completed ? 'mdi-check-circle' : 'mdi-alert-circle'" />
                                    <span>{{ queue_test_running ? 'Läuft...' : healthStore.queue_test?.is_completed ? 'OK' : 'Fehler' }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="admin-card ai-glass-panel admin-card-user">
                        <div class="admin-card-head">
                            <div>
                                <div class="admin-card-eyebrow">Account</div>
                                <h3 class="admin-card-title">Angemeldeter Benutzer</h3>
                            </div>
                        </div>

                        <div class="user-block">
                            <div class="user-avatar" :class="{ 'has-short-code': !!userBadgeShortName(config?.user) }" :title="userBadgeText(config?.user)">
                                {{ userBadgeText(config?.user) }}
                            </div>
                            <div>
                                <div class="user-name">{{ config?.user?.last_name + ' ' + config?.user?.first_name }}</div>
                                <div class="user-email">{{ config?.user?.email }}</div>
                            </div>
                        </div>

                        <div class="role-list">
                            <div class="role-pill" v-for="role in config?.user?.roles || []" :key="role">
                                <v-icon size="14" icon="mdi-shield-account" />
                                <span>{{ role }}</span>
                            </div>
                        </div>
                    </section>


                    <div class="admin-licence-row">
                        <section class="admin-card ai-glass-panel">
                            <div class="admin-card-head">
                                <div>
                                    <div class="admin-card-eyebrow">Lizenzen</div>
                                    <h3 class="admin-card-title">Schullizenzen</h3>
                                </div>
                            </div>

                            <div class="licence-list" v-if="schoolLicencesWithSchoolLicence.length > 0">
                                <div v-for="licence in schoolLicencesWithSchoolLicence" :key="licence.id" class="licence-item">
                                    <div class="licence-name">{{ licence.name }}</div>
                                    <div class="licence-long" v-if="licence.long_name">{{ licence.long_name }}</div>
                                    <div class="licence-detail-lines">
                                        <div class="licence-detail-line">
                                            <span v-if="isLicenceActive(licence) && licence.valid_until" class="licence-tag is-active">gültig bis {{ formatDateDisplay(licence.valid_until) }}</span>
                                            <span v-else-if="isLicenceActive(licence)" class="licence-tag is-active">unbegrenzt</span>
                                            <span v-else class="licence-tag is-expired">abgelaufen</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="empty-state">Keine Schullizenzen vorhanden.</div>
                        </section>

                        <section class="admin-card ai-glass-panel">
                            <div class="admin-card-head">
                                <div>
                                    <div class="admin-card-eyebrow">Lizenzen</div>
                                    <h3 class="admin-card-title">Meine Lizenzen</h3>
                                </div>
                            </div>

                            <div class="licence-list" v-if="myLicenceEntries.length > 0">
                                <div v-for="entry in myLicenceEntries" :key="entry.key" class="licence-item">
                                    <div class="licence-name">{{ entry.licence_name }}</div>
                                    <div class="licence-detail-lines">
                                        <div class="licence-detail-line">
                                            <span>{{ entry.type_label }}</span>
                                            <span v-if="entry.is_active && entry.valid_until" class="licence-tag is-active">gültig bis {{ formatDateDisplay(entry.valid_until) }}</span>
                                            <span v-else-if="entry.is_active" class="licence-tag is-active">unbegrenzt</span>
                                            <span v-else class="licence-tag is-expired">abgelaufen</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="empty-state">Keine persönlichen Lizenzen vorhanden.</div>
                        </section>
                    </div>
                </div>
            </div>
        </section>

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
        if (this.config?.is_auth) {
            if (this.config?.school_infos) {
                this.schoolStore.applySchoolInfos(this.config.school_infos)
            } else {
                await this.schoolStore.loadSchoolInfos(this.config?.selected_school?.id)
            }
            await this.healthStore.fetchStatus()
            this.health_loaded = true
        }
        this.adminStore.is_loading--
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            healthStore: null,
            schoolStore: null,
            health_loaded: false,
            health_loading: false,
            queue_test_running: false,
            queue_test_visible: false,
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
        async refreshHealth() {
            if (!this.healthStore) return
            this.health_loading = true
            try {
                await this.healthStore.fetchStatus()
                this.health_loaded = true
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
        userBadgeShortName(user) {
            const shortName = String(user?.short ?? user?.short_name ?? '').trim()
            return shortName || ''
        },
        userBadgeText(user) {
            const shortName = this.userBadgeShortName(user)
            if (shortName) return shortName
            return ((user?.first_name || '').slice(0, 1) + (user?.last_name || '').slice(0, 1)).toUpperCase()
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

<style scoped src="../../../../css/admin-index-page.css"></style>

<style scoped>
.user-avatar.has-short-code,
.person-avatar.has-short-code {
    width: auto;
    min-width: 38px;
    max-width: 120px;
    padding: 0 10px;
    font-size: 0.72rem;
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
