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
                        @click="openSwitchSchool">
                        Schule wechseln
                    </v-btn>
                </div>

                <template v-if="action == ''">
                    <div class="sa-school-block">
                        <div class="sa-school-avatar" :title="effectiveSchool.short_name || effectiveSchool.long_name || 'Schule'">
                            {{ effectiveSchool.short_name || effectiveSchool.long_name || 'Schule' }}
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
                        <v-text-field
                            v-model="switch_email"
                            label="E-Mail"
                            variant="outlined"
                            hide-details="auto"
                            class="mt-3"
                            prepend-inner-icon="mdi-email-outline"
                            @keydown.enter.prevent="refreshSwitchableSchools" />

                        <div class="d-flex flex-row align-center justify-space-between ga-2 mt-2">
                            <div class="text-caption">Schulen zu dieser E-Mail laden</div>
                            <v-btn type="button" color="secondary" variant="tonal" rounded="lg" prepend-icon="mdi-refresh" @click="refreshSwitchableSchools">
                                Liste laden
                            </v-btn>
                        </div>

                        <div v-if="quickSwitchSchoolChips.length > 0" class="mt-3">
                            <div class="text-caption mb-2">Schnellwechsel (gleicher Nachname in anderen Schulen)</div>
                            <div class="d-flex flex-wrap ga-2">
                            <v-chip
                                v-for="chip in quickSwitchSchoolChips"
                                :key="`switch-user-${chip.email}-${chip.school_id}`"
                                size="small"
                                color="primary"
                                variant="outlined"
                                :title="chip.email"
                                @click="quickSwitchToSchool(chip)">
                                {{ chip.last_name }} {{ chip.first_name }} • {{ chip.email }} • {{ chip.school_label || 'Schule' }}
                            </v-chip>
                            </div>
                        </div>
                        <div v-else-if="switch_last_name && switch_last_name.trim() !== ''" class="text-caption mt-2">
                            Keine Treffer für diesen Nachnamen.
                        </div>

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
                        <div class="sa-user-avatar" :class="{ 'is-short-name': !!adminBadgeShortName(admin) }" :title="adminBadgeText(admin)">
                            {{ adminBadgeText(admin) }}
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
            this.switch_email = this.currentUserEmail
            await this.schoolStore.loadSwitchableSchools(this.switch_email)
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
            switch_email: '',
            switch_last_name: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config', 'roles', 'main_action']),
        ...mapWritableState(useSchoolStore, ['selected_school', 'switchable_schools', 'switch_user_matches', 'school_licences', 'school_admins', 'teachers']),
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
        currentUserEmail() {
            return String(this.config?.user?.email || '').trim()
        },
        currentUserLastName() {
            return String(this.config?.user?.last_name || '').trim()
        },
        quickSwitchSchoolChips() {
            return (Array.isArray(this.switch_user_matches) ? this.switch_user_matches : [])
                .flatMap((match) => {
                    const schools = Array.isArray(match?.schools) ? match.schools : []
                    return schools.map((school) => ({
                        email: String(match?.email || '').trim(),
                        first_name: String(match?.first_name || '').trim(),
                        last_name: String(match?.last_name || '').trim(),
                        school_id: Number(school?.id || 0),
                        school_label: String(school?.label || '').trim(),
                    }))
                })
                .filter((chip) => chip.email && chip.school_id > 0)
        },
    },

    methods: {
        async openSwitchSchool() {
            this.switch_email = this.currentUserEmail
            this.switch_last_name = this.currentUserLastName
            this.selected_school_id = null
            this.action = 'switch_school'
            await this.refreshSwitchableSchools()
            await this.loadQuickSwitchUsers()
        },
        async refreshSwitchableSchools() {
            const ok = await this.schoolStore.loadSwitchableSchools(this.switch_email)
            if (!ok) return false

            if (!Array.isArray(this.switchable_schools)) {
                this.selected_school_id = null
                return true
            }

            const selectedStillExists = this.switchable_schools.some((school) => school?.id === this.selected_school_id)
            if (!selectedStillExists) this.selected_school_id = null

            return true
        },
        async loadQuickSwitchUsers() {
            const search = String(this.switch_last_name || '').trim()
            if (search === '') {
                this.switch_user_matches = []
                return true
            }

            const result = await this.schoolStore.searchSwitchUsers(search)
            return !!result
        },
        async quickSwitchToSchool(chip) {
            const schoolId = Number(chip?.school_id || 0)
            const email = String(chip?.email || '').trim()
            if (!schoolId || email === '') return

            this.switch_email = email
            this.selected_school_id = schoolId
            await this.doSwitch(schoolId, email)
        },
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

        async doSwitch(school_id, emailOverride = null) {
            if (!school_id) return
            const email = typeof emailOverride === 'string' && emailOverride.trim() !== '' ? emailOverride.trim() : this.switch_email
            if (!(await this.schoolStore.switchSchool(school_id, email))) return
            await this.adminStore.loadConfig()
            this.switch_email = this.currentUserEmail
            this.switch_last_name = this.currentUserLastName
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
        adminBadgeShortName(admin) {
            const shortName = String(admin?.short ?? admin?.short_name ?? '').trim()
            return shortName || ''
        },
        adminBadgeText(admin) {
            const shortName = this.adminBadgeShortName(admin)
            if (shortName) return shortName
            return ((admin?.first_name || '').slice(0, 1) + (admin?.last_name || '').slice(0, 1)).toUpperCase()
        },
    },
}
</script>
<style scoped src="../../../../../css/admin-overview-card-foundation.css"></style>
<style scoped src="../../../../../css/admin-superadmin-overview-cards.css"></style>
