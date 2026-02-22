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
                            <div class="user-avatar">
                                {{ (config?.user?.first_name?.[0] || '') + (config?.user?.last_name?.[0] || '') }}
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
                                <div class="person-avatar">
                                    {{ (admin?.first_name?.[0] || '') + (admin?.last_name?.[0] || '') }}
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
                                <div class="licence-left">
                                    <div class="licence-dot" :class="isLicenceActive(licence) ? 'is-active' : 'is-expired'"></div>
                                    <div>
                                        <div class="licence-name">{{ licence.name }}</div>
                                        <div class="licence-long" v-if="licence.long_name">{{ licence.long_name }}</div>
                                    </div>
                                </div>
                                <div class="licence-right">
                                    <div class="licence-validity" v-if="isLicenceActive(licence)">
                                        <span v-if="licence.valid_until">aktiv bis {{ licence.valid_until }}</span>
                                        <span v-else>aktiv (unbegrenzt)</span>
                                    </div>
                                    <div class="licence-validity is-expired-text" v-else>abgelaufen seit {{ licence.valid_until }}</div>
                                    <div class="licence-price">EUR {{ licence.price_per_year }} / Jahr</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="empty-state">Keine Lizenzen vorhanden.</div>
                    </section>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useHealthStore } from '@/stores/admin/HealthStore'

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
        async runTests() {
            // Cron-Job-Status
            this.cron_test_status = 'running'
            await this.healthStore.checkCronStatus()
            this.cron_test_result = this.cron_status.is_healthy
            this.cron_test_status = 'finished'

            this.test_step = 0
            this.all_tests_result = 0
            this.queue_test_status = 'waiting'
            this.queue_test_result = 0

            this.queue_test_status = 'running'
            await this.healthStore.testQueue()
            // Mehrmals prüfen bis completed
            let attempts = 0
            let maxAttempts = 10
            let status = null
            let is_completed = false

            this.queue_test_result = 999
            while (attempts < maxAttempts && !is_completed) {
                status = await this.healthStore.checkQueueStatus(this.data.testId)

                if (status.is_completed) {
                    this.queue_test_result = 1
                    break
                } else {
                    await new Promise((resolve) => setTimeout(resolve, 1000)) // 1 Sekunde warten
                    attempts++
                }
            }
            this.queue_test_status = 'finished'

            this.all_tests_result = 1
            if (this.queue_test_result != 1 || this.cron_test_result != 1) this.all_tests_result = 999
            this.test_step = 999
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
        isLicenceActive(licence) {
            const validUntil = licence?.valid_until
            if (!validUntil) return true
            return String(validUntil) >= this.localDateKey()
        },
    },
}
</script>

<style scoped>
.admin-index-page {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    background: #101d2a;
}

.admin-bg {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}

.admin-bg-image {
    opacity: 0.95;
}

.admin-bg-glow-left {
    width: 360px;
    height: 360px;
    left: -80px;
    top: 180px;
    background: radial-gradient(circle, rgba(255, 198, 124, 0.65), rgba(255, 198, 124, 0));
}

.admin-bg-glow-right {
    width: 420px;
    height: 420px;
    right: -120px;
    top: 120px;
    background: radial-gradient(circle, rgba(88, 143, 194, 0.55), rgba(88, 143, 194, 0));
}

.admin-hero {
    position: relative;
    z-index: 1;
    padding: 20px 18px 48px;
}

.admin-shell {
    max-width: 1160px;
}

.ai-glass-panel {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.68));
    border-color: rgba(16, 38, 58, 0.08);
}

.admin-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-radius: 20px;
    padding: 14px 16px;
}

.admin-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.admin-brand-badge {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    flex-shrink: 0;
}

.admin-brand-eyebrow {
    color: rgba(16, 38, 58, 0.88);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.admin-brand-title {
    margin: 0;
    color: #112536;
    font-size: 1.25rem;
    line-height: 1.05;
    letter-spacing: 0.01em;
}

.admin-header-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.admin-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 11px;
    border-radius: 999px;
    border: 1px solid rgba(16, 38, 58, 0.12);
    background: rgba(255, 255, 255, 0.82);
    color: #1d3448;
    max-width: 100%;
}

.admin-meta-pill span {
    font-size: 0.75rem;
    opacity: 0.9;
}

.admin-meta-pill strong {
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-run-tests-btn {
    text-transform: none;
    letter-spacing: 0;
    color: #13283a !important;
    font-weight: 700;
}

.admin-hero-copy {
    margin-top: 34px;
    max-width: 840px;
}

.admin-hero-title {
    margin: 0;
    color: #fff;
    font-size: clamp(1.7rem, 2.8vw, 2.7rem);
    line-height: 1.02;
    letter-spacing: 0.015em;
    text-shadow: 0 8px 22px rgba(27, 15, 6, 0.2);
}

.admin-hero-description {
    margin: 12px 0 0;
    color: rgba(255, 255, 255, 0.92);
    font-size: 1rem;
    line-height: 1.45;
    max-width: 66ch;
}

.admin-kpi-grid {
    margin-top: 22px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.kpi-card {
    border-radius: 18px;
    padding: 14px 16px;
    color: #12273a;
}

.kpi-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: rgba(18, 39, 58, 0.86);
    font-weight: 700;
}

.kpi-value {
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.15rem;
    font-weight: 800;
    color: #10263a;
}

.kpi-sub {
    margin-top: 4px;
    color: rgba(18, 39, 58, 0.92);
    font-size: 0.82rem;
}

.admin-content-grid {
    margin-top: 16px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 400px);
    gap: 14px;
}

.admin-card {
    border-radius: 20px;
    padding: 16px;
}

.admin-card-tests {
    grid-column: 1;
}

.admin-card-user {
    grid-column: 2;
}

.admin-card-admins {
    grid-column: 1;
}

.admin-card-licences {
    grid-column: 2;
    grid-row: span 2;
}

.admin-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.admin-card-eyebrow {
    color: rgba(16, 38, 58, 0.86);
    text-transform: uppercase;
    letter-spacing: 0.09em;
    font-size: 0.72rem;
    font-weight: 700;
}

.admin-card-title {
    margin: 4px 0 0;
    color: #10263a;
    font-size: 1.15rem;
    line-height: 1.15;
}

.status-list {
    display: grid;
    gap: 10px;
}

.status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-radius: 14px;
    padding: 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    background: rgba(255, 255, 255, 0.78);
}

.status-copy {
    min-width: 0;
}

.status-name {
    color: #12283c;
    font-weight: 700;
    font-size: 0.9rem;
}

.status-note {
    margin-top: 2px;
    color: rgba(18, 40, 60, 0.9);
    font-size: 0.78rem;
}

.status-badge {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 0.74rem;
    font-weight: 700;
    border: 1px solid transparent;
    text-transform: capitalize;
}

.status-badge.is-success {
    background: rgba(46, 164, 79, 0.1);
    border-color: rgba(46, 164, 79, 0.2);
    color: #206d36;
}

.status-badge.is-error {
    background: rgba(220, 53, 69, 0.1);
    border-color: rgba(220, 53, 69, 0.17);
    color: #912336;
}

.status-badge.is-running {
    background: rgba(63, 99, 255, 0.1);
    border-color: rgba(63, 99, 255, 0.17);
    color: #3049b5;
}

.status-badge.is-waiting {
    background: rgba(245, 129, 32, 0.11);
    border-color: rgba(245, 129, 32, 0.17);
    color: #9c5317;
}

.user-block {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(16, 38, 58, 0.1);
}

.user-avatar,
.person-avatar {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 0.84rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(180deg, #4f88b8, #2f628c);
    box-shadow: 0 8px 18px rgba(36, 76, 109, 0.22);
    flex-shrink: 0;
}

.user-name {
    font-weight: 800;
    color: #112638;
    line-height: 1.1;
}

.user-email {
    margin-top: 3px;
    color: rgba(17, 38, 56, 0.92);
    font-size: 0.8rem;
}

.role-list {
    margin-top: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.role-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 6px 10px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(16, 38, 58, 0.12);
    color: #183449;
    font-size: 0.76rem;
    font-weight: 700;
}

.people-list {
    display: grid;
    gap: 10px;
}

.person-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(16, 38, 58, 0.1);
}

.person-body {
    min-width: 0;
}

.person-name {
    color: #10263a;
    font-weight: 700;
    line-height: 1.15;
}

.person-email {
    margin-top: 3px;
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.92);
    word-break: break-word;
}

.person-roles {
    margin-top: 5px;
    font-size: 0.74rem;
    color: rgba(16, 38, 58, 0.86);
}

.licence-list {
    display: grid;
    gap: 10px;
}

.licence-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    border-radius: 14px;
    padding: 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    background: rgba(255, 255, 255, 0.78);
}

.licence-left {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
}

.licence-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
}

.licence-dot.is-active {
    background: #2ea44f;
    box-shadow: 0 0 0 4px rgba(46, 164, 79, 0.14);
}

.licence-dot.is-expired {
    background: #dc3545;
    box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.12);
}

.licence-name {
    color: #10263a;
    font-weight: 800;
    line-height: 1.15;
}

.licence-long {
    margin-top: 4px;
    color: rgba(16, 38, 58, 0.86);
    font-size: 0.78rem;
    font-style: italic;
}

.licence-right {
    flex-shrink: 0;
    text-align: right;
    min-width: 140px;
}

.licence-validity {
    color: rgba(16, 38, 58, 0.94);
    font-size: 0.78rem;
}

.licence-validity.is-expired-text {
    color: #932f3c;
}

.licence-price {
    margin-top: 5px;
    color: #10263a;
    font-weight: 700;
    font-size: 0.82rem;
}

.empty-state {
    border-radius: 14px;
    border: 1px dashed rgba(16, 38, 58, 0.14);
    background: rgba(255, 255, 255, 0.72);
    color: rgba(16, 38, 58, 0.92);
    padding: 12px;
    font-size: 0.86rem;
}

@media (max-width: 1100px) {
    .admin-content-grid {
        grid-template-columns: 1fr;
    }

    .admin-card-tests,
    .admin-card-user,
    .admin-card-admins,
    .admin-card-licences {
        grid-column: auto;
        grid-row: auto;
    }

    .licence-right {
        min-width: 0;
    }
}

@media (max-width: 860px) {
    .admin-header {
        flex-direction: column;
        align-items: stretch;
    }

    .admin-header-meta {
        justify-content: flex-start;
    }

    .admin-kpi-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .admin-hero {
        padding: 14px 12px 28px;
    }

    .admin-header {
        border-radius: 16px;
        padding: 12px;
    }

    .admin-card {
        border-radius: 16px;
        padding: 12px;
    }

    .status-row,
    .licence-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .status-badge,
    .licence-right {
        text-align: left;
    }

    .admin-meta-pill {
        max-width: 100%;
    }
}
</style>
