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
                        <v-btn
                            color="white"
                            variant="flat"
                            rounded="xl"
                            class="admin-run-tests-btn"
                            prepend-icon="mdi-refresh"
                            @click="runTests"
                            :loading="queue_test_status == 'running' || cron_test_status == 'running'"
                            :disabled="queue_test_status == 'running'">
                            Tests prüfen
                        </v-btn>
                    </div>
                </header>

                <div class="admin-hero-copy">
                    <h2 class="admin-hero-title">Zentrale Übersicht für Systemzustand, Team und Lizenzen</h2>
                    <p class="admin-hero-description">
                        Behalten Sie Admins, Gesundheitschecks und aktive Schul-Lizenzen in einer Oberfläche im Blick. Schnell prüfen, reagieren und verwalten.
                    </p>
                </div>

                <div class="admin-kpi-grid">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Systemtests</div>
                        <div class="kpi-value">
                            <v-icon
                                size="20"
                                :icon="test_step == 999 ? 'mdi-checkbox-marked-circle' : 'mdi-progress-clock'"
                                :color="test_step == 999 && all_tests_result == 1 ? 'success' : test_step == 999 ? 'error' : 'warning'" />
                            <span>
                                {{
                                    test_step == 999
                                        ? all_tests_result == 1
                                            ? 'OK'
                                            : 'Fehler'
                                        : 'Prüfung läuft / ausstehend'
                                }}
                            </span>
                        </div>
                        <div v-if="isAllowed(['super_admin'])" class="mt-3">
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
                                :disabled="restart_queues_loading || queue_test_status == 'running' || cron_test_status == 'running'"
                                block
                                @click="restartQueues">
                                Queues neu starten
                            </v-btn>
                        </div>
                    </div>

                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Admins</div>
                        <div class="kpi-value">{{ school_admins?.length || 0 }}</div>
                        <div class="kpi-sub">{{ (config?.selected_school?.name || 'Schule') + ' Team' }}</div>
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
                        <div class="admin-card-head">
                            <div>
                                <div class="admin-card-eyebrow">Monitoring</div>
                                <h3 class="admin-card-title">System-Checks</h3>
                            </div>
                        </div>

                        <div class="status-list">
                            <div class="status-row">
                                <div class="status-copy">
                                    <div class="status-name">Gesamtstatus</div>
                                    <div class="status-note" v-if="test_step != 999">Tests aktiv oder noch nicht abgeschlossen</div>
                                    <div class="status-note" v-else>Letzte Prüfserie abgeschlossen</div>
                                </div>
                                <div class="status-badge" :class="test_step == 999 ? (all_tests_result == 1 ? 'is-success' : 'is-error') : 'is-waiting'">
                                    <v-icon
                                        size="16"
                                        :icon="
                                            test_step == 999
                                                ? all_tests_result == 1
                                                    ? 'mdi-check-circle'
                                                    : 'mdi-alert-circle'
                                                : 'mdi-dots-horizontal-circle'
                                        " />
                                    <span>
                                        {{
                                            test_step == 999
                                                ? all_tests_result == 1
                                                    ? 'OK'
                                                    : 'Fehler'
                                                : 'Läuft / wartend'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div class="status-row">
                                <div class="status-copy">
                                    <div class="status-name">Warteschlange</div>
                                    <div class="status-note" v-if="queue_test_status == 'waiting'">Test wartend</div>
                                    <div class="status-note" v-else-if="queue_test_status == 'running'">Queue-Test aktiv</div>
                                    <div class="status-note" v-else>Queue-Test abgeschlossen</div>
                                </div>
                                <div class="status-badge" :class="queue_test_status == 'finished' ? (queue_test_result == 1 ? 'is-success' : 'is-error') : queue_test_status == 'running' ? 'is-running' : 'is-waiting'">
                                    <v-icon
                                        size="16"
                                        :class="{ 'mdi-spin': queue_test_status == 'running' }"
                                        :icon="
                                            queue_test_status == 'finished'
                                                ? queue_test_result == 1
                                                    ? 'mdi-check-circle'
                                                    : 'mdi-alert-circle'
                                                : queue_test_status == 'running'
                                                  ? 'mdi-loading'
                                                  : 'mdi-clock-outline'
                                        " />
                                    <span>{{ queue_test_status }}</span>
                                </div>
                            </div>

                            <div class="status-row">
                                <div class="status-copy">
                                    <div class="status-name">Cron / Timer</div>
                                    <div class="status-note" v-if="cron_test_status == 'finished'">{{ cron_status?.health_at || 'Zeit unbekannt' }}</div>
                                    <div class="status-note" v-else-if="cron_test_status == 'running'">Prüfung aktiv (1-2 min möglich)</div>
                                    <div class="status-note" v-else>Prüfung wartend</div>
                                </div>
                                <div class="status-badge" :class="cron_test_status == 'finished' ? (cron_test_result == 1 ? 'is-success' : 'is-error') : cron_test_status == 'running' ? 'is-running' : 'is-waiting'">
                                    <v-icon
                                        size="16"
                                        :class="{ 'mdi-spin': cron_test_status == 'running' }"
                                        :icon="
                                            cron_test_status == 'finished'
                                                ? cron_test_result == 1
                                                    ? 'mdi-check-circle'
                                                    : 'mdi-alert-circle'
                                                : cron_test_status == 'running'
                                                  ? 'mdi-loading'
                                                  : 'mdi-clock-outline'
                                        " />
                                    <span>{{ cron_test_status }}</span>
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

                    <section class="admin-card ai-glass-panel admin-card-admins">
                        <div class="admin-card-head">
                            <div>
                                <div class="admin-card-eyebrow">Team</div>
                                <h3 class="admin-card-title">Admins</h3>
                            </div>
                        </div>

                        <div class="people-list" v-if="(school_admins || []).length > 0">
                            <div v-for="admin in school_admins" :key="admin.id" class="person-row">
                                <div class="person-avatar" :class="{ 'has-short-code': !!userBadgeShortName(admin) }" :title="userBadgeText(admin)">
                                    {{ userBadgeText(admin) }}
                                </div>
                                <div class="person-body">
                                    <div class="person-name">{{ admin.last_name + ' ' + admin.first_name }}</div>
                                    <div class="person-email">{{ admin.email }}</div>
                                    <div class="person-roles">{{ admin.roles.join(', ') }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="empty-state">Keine Admins gefunden.</div>
                    </section>

                    <section class="admin-card ai-glass-panel admin-card-licences">
                        <div class="admin-card-head">
                            <div>
                                <div class="admin-card-eyebrow">Abrechnung / Zugriff</div>
                                <h3 class="admin-card-title">Lizenzen</h3>
                            </div>
                        </div>

                        <div class="licence-list" v-if="(school_licences || []).length > 0">
                            <div v-for="licence in school_licences" :key="licence.id" class="licence-item">
                                <div class="licence-main">
                                    <div class="licence-left">
                                        <div class="licence-dot" :class="isLicenceActive(licence) ? 'is-active' : 'is-expired'"></div>
                                        <div>
                                            <div class="licence-name">{{ licence.name }}</div>
                                            <div class="licence-long" v-if="licence.long_name">{{ licence.long_name }}</div>
                                        </div>
                                    </div>
                                    <div class="licence-right">
                                        <div class="licence-validity" v-if="isLicenceActive(licence)">
                                            <span v-if="licence.valid_until">aktiv bis {{ formatDateDisplay(licence.valid_until) }}</span>
                                            <span v-else>aktiv (unbegrenzt)</span>
                                        </div>
                                        <div class="licence-validity is-expired-text" v-else>abgelaufen seit {{ formatDateDisplay(licence.valid_until) }}</div>
                                        <div class="licence-price">EUR {{ licence.price_per_year }} / Jahr</div>
                                    </div>
                                </div>

                                <div
                                    v-if="isLicenceActive(licence) && licence.current_user_licence?.enabled"
                                    class="user-licence-box">
                                    <template v-if="userLicenceRoleEntries(licence).length > 0">
                                        <div class="user-licence-title">Benutzerlizenzen</div>
                                        <div
                                            v-for="roleEntry in userLicenceRoleEntries(licence)"
                                            :key="`user-licence-role-${licence.school_licence_id}-${roleEntry.role_name}`"
                                            class="user-licence-role-card">
                                            <div class="user-licence-role-head">
                                                <span
                                                    class="user-licence-role-dot"
                                                    :class="userLicenceRoleDotClass(roleEntry)"></span>
                                                <div class="user-licence-role-name">{{ roleEntry.role_name }}</div>
                                            </div>

                                            <template v-if="isUserLicenceRoleActive(roleEntry)">
                                                <div class="user-licence-line">Status: aktiv</div>
                                                <div class="user-licence-line" v-if="roleEntry?.plan?.text">Plan: {{ roleEntry.plan.text }}</div>
                                                <div class="user-licence-line">
                                                    Preis:
                                                    {{ roleEntry?.plan?.price_per_year ? formatPlanPrice(roleEntry.plan.price_per_year) : '-' }}
                                                </div>
                                                <div class="user-licence-line">
                                                    Gültig bis:
                                                    {{ roleEntry?.valid_until ? formatDateDisplay(roleEntry.valid_until) : 'unbegrenzt' }}
                                                </div>
                                                <v-btn
                                                    v-if="shouldShowRenewUserLicenceButton(roleEntry)"
                                                    size="small"
                                                    variant="outlined"
                                                    color="primary"
                                                    rounded="lg"
                                                    class="mt-2 mr-2"
                                                    @click="openRenewUserLicenceDialog(licence, roleEntry)">
                                                    Verlängern
                                                </v-btn>
                                                <v-btn
                                                    size="small"
                                                    variant="outlined"
                                                    color="error"
                                                    rounded="lg"
                                                    class="mt-2"
                                                    @click="confirmDeactivateUserLicence(licence, roleEntry)">
                                                    Deaktivieren
                                                </v-btn>
                                            </template>

                                            <template v-else>
                                                <div class="user-licence-line is-warning-text">Status: nicht aktiv</div>
                                                <div class="user-licence-line">Für diese Funktion ist eine Benutzerlizenz erforderlich.</div>
                                                <v-btn
                                                    size="small"
                                                    variant="outlined"
                                                    color="primary"
                                                    rounded="lg"
                                                    class="mt-2"
                                                    @click="openActivateUserLicenceDialog(licence, roleEntry.role_name)">
                                                    Aktivieren
                                                </v-btn>
                                            </template>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="user-licence-title is-warning">Benutzerlizenz nicht aktiv</div>
                                        <div class="user-licence-line">Für Ihre Rollen ist aktuell keine Benutzerlizenz zugeordnet.</div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div v-else class="empty-state">Keine Lizenzen vorhanden.</div>
                    </section>
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
        await axios.get('/sanctum/csrf-cookie')
        this.adminStore = useAdminStore()
        this.healthStore = useHealthStore()
        this.schoolStore = useSchoolStore()
        this.adminStore.is_loading++
        if (this.config?.is_auth) await this.schoolStore.loadSchoolInfos(this.config?.selected_school?.id)
        if (this.config?.is_auth) this.runTests()
        this.adminStore.is_loading--
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            healthStore: null,
            schoolStore: null,
            test_step: 0,
            all_tests_result: 0,
            queue_test_status: 'waiting',
            queue_test_result: 0,
            cron_test_status: 'waiting',
            cron_test_result: 0,
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
        ...mapWritableState(useHealthStore, ['data', 'data_2', 'cron_status']),
        ...mapWritableState(useSchoolStore, ['school_licences', 'school_admins']),
        activeLicenceCount() {
            return (this.school_licences || []).filter((licence) => this.isLicenceActive(licence)).length
        },
        expiredLicenceCount() {
            return (this.school_licences || []).filter((licence) => !this.isLicenceActive(licence)).length
        },
    },

    methods: {
        isAllowed(roles) {
            return this.config.user.roles.some((role) => roles.includes(role))
        },
        async restartQueues() {
            if (this.restart_queues_loading || this.queue_test_status == 'running' || this.cron_test_status == 'running') return
            this.restart_queues_loading = true
            this.restart_countdown = 0
            const counterInterval = setInterval(() => {
                this.restart_countdown += 1
            }, 1000)
            try {
                await axios.post('/api/admin/restart_queues')
                await this.runTests()
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
        async runTests() {
            if (!this.healthStore) return

            // Cron-Job-Status
            this.cron_test_status = 'running'
            try {
                await this.healthStore.checkCronStatus()
                this.cron_test_result = this.cron_status?.is_healthy ?? 0
            } catch {
                this.cron_test_result = 0
            } finally {
                this.cron_test_status = 'finished'
            }

            this.test_step = 0
            this.all_tests_result = 0
            this.queue_test_status = 'running'
            this.queue_test_result = 0

            try {
                const queueTestStarted = await this.healthStore.testQueue()
                const queueTestId = this.data?.testId
                if (!queueTestStarted || !queueTestId) {
                    throw new Error('Queue test could not be initialized')
                }

                // Mehrmals prüfen bis completed
                let attempts = 0
                const maxAttempts = 20

                while (attempts < maxAttempts) {
                    const status = await this.healthStore.checkQueueStatus(queueTestId)
                    if (!status) {
                        break
                    }

                    if (status.is_completed) {
                        this.queue_test_result = 1
                        break
                    }
                    await new Promise((resolve) => setTimeout(resolve, 1000))
                    attempts++
                }
            } catch {
                this.queue_test_result = 0
            } finally {
                this.queue_test_status = 'finished'
            }

            this.all_tests_result = this.queue_test_result == 1 && this.cron_test_result == 1 ? 1 : 999
            this.test_step = 999
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
        userLicenceRoleEntries(licence) {
            const roles = licence?.current_user_licence?.roles
            return Array.isArray(roles) ? roles.filter((entry) => entry && typeof entry === 'object' && entry.role_name) : []
        },
        isUserLicenceRoleActive(roleEntry) {
            return !!roleEntry?.is_active && !!roleEntry?.is_activated
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
