<template>
    <!-- NUR FÜR SUPER_ADMIN -->
    <v-col cols="12" v-if="config.roles.includes('super_admin')">
        <div class="sa-overview-grid">
            <section class="sa-card sa-card-school">
                <div class="sa-card-head">
                    <div>
                        <div class="sa-card-eyebrow">Aktive Schule</div>
                        <h3 class="sa-card-title">{{ effectiveSchool.long_name || effectiveSchool.short_name || 'Schule' }}</h3>
                    </div>
                    <v-btn
                        v-if="action == ''"
                        color="white"
                        variant="flat"
                        rounded="xl"
                        class="sa-switch-btn"
                        prepend-icon="mdi-swap-horizontal"
                        @click="action = 'switch_school'">
                        Schule wechseln
                    </v-btn>
                </div>

                <template v-if="action == ''">
                    <div class="sa-school-block">
                        <div class="sa-school-avatar">
                            {{ (effectiveSchool.short_name || effectiveSchool.long_name || 'S').slice(0, 2).toUpperCase() }}
                        </div>
                        <div class="sa-school-copy">
                            <div class="sa-school-name">{{ effectiveSchool.long_name || '-' }}</div>
                            <div class="sa-school-short">{{ effectiveSchool.short_name || '-' }}</div>
                            <div class="sa-school-email" v-if="effectiveSchool.email">✉ {{ effectiveSchool.email }}</div>
                        </div>
                    </div>

                    <div class="sa-kpi-grid">
                        <div class="sa-kpi-card">
                            <div class="sa-kpi-label">Lizenzen aktiv</div>
                            <div class="sa-kpi-value">{{ activeLicenceCount }}</div>
                            <div class="sa-kpi-sub" v-if="expiredLicenceCount > 0">{{ expiredLicenceCount }} abgelaufen</div>
                            <div class="sa-kpi-sub" v-else>Keine abgelaufen</div>
                        </div>
                        <div class="sa-kpi-card">
                            <div class="sa-kpi-label">Admins</div>
                            <div class="sa-kpi-value">{{ (school_admins || []).length }}</div>
                            <div class="sa-kpi-sub">zugeordnet</div>
                        </div>
                        <div class="sa-kpi-card">
                            <div class="sa-kpi-label">Auswahl</div>
                            <div class="sa-kpi-value">{{ (switchable_schools || []).length }}</div>
                            <div class="sa-kpi-sub">wechselbare Schulen</div>
                        </div>
                    </div>
                </template>

                <div v-else-if="action == 'switch_school'" class="sa-switch-shell">
                    <div class="sa-inline-banner">Schule wechseln</div>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doSwitch(selected_school_id)">
                        <v-autocomplete
                            v-model="selected_school_id"
                            :items="switchable_schools"
                            item-title="long_name"
                            item-value="id"
                            label="Auswahl Schule"
                            variant="outlined"
                            hide-details="auto"
                            class="mt-3"
                            v-if="switchable_schools" />

                        <div class="d-flex flex-row align-center justify-space-between mt-4 ga-2">
                            <v-btn color="warning" variant="tonal" rounded="lg" @click="action = ''">Abbruch</v-btn>
                            <v-btn color="primary" variant="flat" rounded="lg" type="submit">Wechseln</v-btn>
                        </div>
                    </v-form>
                </div>
            </section>

            <section class="sa-card sa-card-licences" v-if="action == ''">
                <div class="sa-card-head">
                    <div>
                        <div class="sa-card-eyebrow">Abrechnung / Zugriff</div>
                        <h3 class="sa-card-title">Lizenzen</h3>
                    </div>
                </div>

                <div class="sa-list" v-if="school_licences && school_licences.length > 0">
                    <div v-for="licence in school_licences" :key="licence.id" class="sa-list-item sa-licence-item">
                        <div class="sa-licence-main">
                            <div class="sa-licence-left">
                                <span class="sa-status-dot" :class="isLicenceActive(licence) ? 'is-success' : 'is-error'"></span>
                                <div>
                                    <div class="sa-item-title">{{ licence.name }}</div>
                                    <div class="sa-item-sub" v-if="licence.long_name">{{ licence.long_name }}</div>
                                </div>
                            </div>
                            <div class="sa-licence-right">
                                <div class="sa-item-meta">{{ licenceValidityText(licence) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="sa-empty">Keine gültigen Lizenzen</div>
            </section>

            <section class="sa-card sa-card-admins" v-if="action == ''">
                <div class="sa-card-head">
                    <div>
                        <div class="sa-card-eyebrow">Team</div>
                        <h3 class="sa-card-title">Admins</h3>
                    </div>
                </div>

                <div class="sa-list" v-if="school_admins && school_admins.length > 0">
                    <div v-for="admin in school_admins" :key="admin.id" class="sa-list-item sa-admin-item">
                        <div class="sa-user-avatar">
                            {{ ((admin.first_name || '').slice(0, 1) + (admin.last_name || '').slice(0, 1)).toUpperCase() }}
                        </div>
                        <div class="sa-admin-copy">
                            <div class="sa-item-title">{{ admin.last_name + ' ' + admin.first_name }}</div>
                            <div class="sa-item-sub">{{ admin.email }}</div>
                            <div class="sa-role-list">
                                <span v-for="role in admin.roles || []" :key="`${admin.id}-${role}`" class="sa-role-pill">{{ role }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="sa-empty">Keine Admins zugeordnet</div>
            </section>
        </div>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useLicenceStore } from '@/stores/admin/LicenceStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: {},

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        this.licenceStore = useLicenceStore()

        if (this.config.roles.includes('super_admin')) {
            await this.schoolStore.loadSwitchableSchools()
            if (this.config.selected_school.id) await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
            await this.licenceStore.loadLicences()
            await this.adminStore.loadRoles()
            if (['add_admin', 'delete_admin', 'add_licence'].includes(this.action)) {
                this.action = ''
            }
        }
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            selected_school_id: null,
            is_valid: false,
            selected_licence_id: null,
            data: {},
            selected_roles: [],
            admin: null,
            is_delete_complete: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config', 'roles', 'main_action']),
        ...mapWritableState(useSchoolStore, ['selected_school', 'switchable_schools', 'school_licences', 'school_admins', 'teachers']),
        ...mapWritableState(useLicenceStore, ['licences']),
        effectiveSchool() {
            return this.selected_school && Object.keys(this.selected_school).length ? this.selected_school : this.config?.selected_school || {}
        },
        activeLicenceCount() {
            return (this.school_licences || []).filter((licence) => this.isLicenceActive(licence)).length
        },
        expiredLicenceCount() {
            return (this.school_licences || []).filter((licence) => !this.isLicenceActive(licence)).length
        },
    },

    methods: {
        async doAddAdmin(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (this.selected_roles.length == 0) return

            if (!(await this.schoolStore.addAdmin(data, this.selected_roles))) return
            await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
            this.action = ''
        },
        addAdmin() {
            this.data = {}
            this.selected_roles = []
            this.action = 'add_admin'
        },

        deleteAdmin(admin) {
            this.admin = admin
            this.is_delete_complete = false
            this.action = 'delete_admin'
        },

        async doDeleteAdmin(admin, is_delete_complete) {
            if (!(await this.schoolStore.deleteAdmin(admin.id, is_delete_complete))) return
            await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)
            this.action = ''
        },

        async deleteLicence(school_licence_id) {
            await this.schoolStore.deleteLicence(school_licence_id)
        },

        async doSwitch(school_id) {
            if (!school_id) return
            await this.schoolStore.switchSchool(school_id)
            await this.adminStore.loadConfig()
            await this.schoolStore.loadSchoolInfos(this.config.selected_school.id)

            this.action = ''
        },

        async doAddLicence(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (!(await this.schoolStore.addLicence(data))) return
            this.action = ''
        },
        localDateKey(date = new Date()) {
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        isSchoolLicenceNotNeeded(licence) {
            const model = this.normalizeLicenceModel(licence?.licence_model || null)
            return model.school_licence_required === false
        },
        isLicenceActive(licence) {
            if (this.isSchoolLicenceNotNeeded(licence)) return true
            const validUntil = licence?.valid_until
            if (!validUntil) return true
            return String(validUntil) >= this.localDateKey()
        },
        licenceValidityText(licence) {
            if (this.isSchoolLicenceNotNeeded(licence)) return 'nicht erforderlich'
            if (this.isLicenceActive(licence)) return licence?.valid_until ? `aktiv bis ${licence.valid_until}` : 'aktiv (unbegrenzt)'
            return licence?.valid_until ? `${licence.valid_until} (abgelaufen)` : 'abgelaufen'
        },
        normalizeLicenceModel(licenceModel) {
            const fallback = {
                school_licence_required: true,
                affected_roles: [],
                user_licence_required_by_role: {},
            }

            if (typeof licenceModel === 'string') {
                try {
                    licenceModel = JSON.parse(licenceModel)
                } catch (_) {
                    return fallback
                }
            }

            if (!licenceModel || typeof licenceModel !== 'object') return fallback

            return {
                school_licence_required: this.toBool(licenceModel.school_licence_required, true),
                affected_roles: Array.isArray(licenceModel.affected_roles) ? licenceModel.affected_roles : [],
                user_licence_required_by_role:
                    licenceModel.user_licence_required_by_role && typeof licenceModel.user_licence_required_by_role === 'object'
                        ? licenceModel.user_licence_required_by_role
                        : {},
            }
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
    },
}
</script>
<style scoped>
.sa-overview-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
    gap: 14px;
}

.sa-card {
    border-radius: 20px;
    padding: 16px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.68));
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(10px);
}

.sa-card-school {
    grid-column: 1 / -1;
}

.sa-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.sa-card-eyebrow {
    color: rgba(16, 38, 58, 0.86);
    text-transform: uppercase;
    letter-spacing: 0.09em;
    font-size: 0.72rem;
    font-weight: 700;
}

.sa-card-title {
    margin: 4px 0 0;
    color: #10263a;
    font-size: 1.15rem;
    line-height: 1.15;
}

.sa-switch-btn {
    text-transform: none;
    letter-spacing: 0;
    color: #13283a !important;
    font-weight: 700;
}

.sa-school-block {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(16, 38, 58, 0.1);
}

.sa-school-avatar,
.sa-user-avatar {
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

.sa-school-copy {
    min-width: 0;
}

.sa-school-name {
    color: #112638;
    font-weight: 800;
    line-height: 1.1;
}

.sa-school-short {
    margin-top: 3px;
    color: rgba(17, 38, 56, 0.92);
    font-size: 0.8rem;
}

.sa-school-email {
    margin-top: 4px;
    color: rgba(17, 38, 56, 0.92);
    font-size: 0.8rem;
    word-break: break-word;
}

.sa-kpi-grid {
    margin-top: 12px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.sa-kpi-card {
    border-radius: 14px;
    padding: 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    background: rgba(255, 255, 255, 0.78);
}

.sa-kpi-label {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(18, 39, 58, 0.86);
    font-weight: 700;
}

.sa-kpi-value {
    margin-top: 5px;
    color: #10263a;
    font-size: 1.1rem;
    font-weight: 800;
}

.sa-kpi-sub {
    margin-top: 4px;
    color: rgba(18, 39, 58, 0.92);
    font-size: 0.78rem;
}

.sa-switch-shell {
    border-radius: 14px;
    padding: 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    background: rgba(255, 255, 255, 0.78);
}

.sa-inline-banner {
    border-radius: 10px;
    padding: 8px 10px;
    color: #fff;
    font-weight: 700;
    background: #3949ab;
}

.sa-list {
    display: grid;
    gap: 10px;
}

.sa-list-item {
    border-radius: 14px;
    padding: 12px;
    border: 1px solid rgba(16, 38, 58, 0.1);
    background: rgba(255, 255, 255, 0.78);
}

.sa-licence-main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.sa-licence-left {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
}

.sa-licence-right {
    text-align: right;
    min-width: 0;
}

.sa-status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
}

.sa-status-dot.is-success {
    background: #2ea44f;
    box-shadow: 0 0 0 4px rgba(46, 164, 79, 0.14);
}

.sa-status-dot.is-error {
    background: #dc3545;
    box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.12);
}

.sa-item-title {
    color: #10263a;
    font-weight: 700;
    line-height: 1.15;
}

.sa-item-sub {
    margin-top: 3px;
    color: rgba(16, 38, 58, 0.86);
    font-size: 0.78rem;
    word-break: break-word;
}

.sa-item-meta {
    color: rgba(16, 38, 58, 0.94);
    font-size: 0.78rem;
}

.sa-admin-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.sa-admin-copy {
    min-width: 0;
}

.sa-role-list {
    margin-top: 6px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.sa-role-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(16, 38, 58, 0.12);
    color: #183449;
    font-size: 0.72rem;
    font-weight: 700;
}

.sa-empty {
    border-radius: 14px;
    border: 1px dashed rgba(16, 38, 58, 0.14);
    background: rgba(255, 255, 255, 0.72);
    color: rgba(16, 38, 58, 0.92);
    padding: 12px;
    font-size: 0.86rem;
}

@media (max-width: 1100px) {
    .sa-overview-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .sa-kpi-grid {
        grid-template-columns: 1fr;
    }

    .sa-card {
        border-radius: 16px;
        padding: 12px;
    }

    .sa-card-head {
        flex-direction: column;
        align-items: stretch;
    }

    .sa-licence-main {
        flex-direction: column;
        align-items: flex-start;
    }

    .sa-licence-right {
        text-align: left;
    }
}
</style>
