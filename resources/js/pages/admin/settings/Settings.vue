<template>
    <div class="settings-page" v-if="config && config.is_auth">
        <div class="settings-bg">
            <div class="settings-bg-image"></div>
            <div class="settings-bg-glow settings-bg-glow-left"></div>
            <div class="settings-bg-glow settings-bg-glow-right"></div>
        </div>

        <v-container fluid class="ma-0 w-100 pa-2 settings-page-inner">
            <AdminSectionHero
                class="mb-3"
                eyebrow="Verwaltung"
                title="Einstellungen"
                :active-section="activeSection"
                :chips="headerChips"
                :show-current-user-chip="true" />

            <v-sheet rounded="xl" class="settings-tabs-sheet mb-2">
                <v-tabs v-model="main_action" color="white" bg-color="transparent" slider-color="white" show-arrows>
                    <v-tab v-for="item in navigationItems" :key="item.key" :value="item.key" :prepend-icon="item.icon">
                        {{ item.label }}
                    </v-tab>
                </v-tabs>
            </v-sheet>

            <v-sheet v-if="showsSubNavigation" rounded="xl" class="settings-subnav mb-2">
                <div class="settings-subnav__buttons">
                    <v-btn
                        v-for="item in subNavigationItems"
                        :key="item.key"
                        rounded="xl"
                        :color="sub_action === item.key ? 'primary' : 'secondary'"
                        :variant="sub_action === item.key ? 'flat' : 'tonal'"
                        class="settings-subnav__button"
                        @click="sub_action = item.key">
                        <v-icon size="18" :icon="item.icon" class="mr-2" />
                        <span class="settings-subnav__button-copy">
                            <span class="settings-subnav__button-title">{{ item.label }}</span>
                            <span class="settings-subnav__button-meta">{{ item.meta }}</span>
                        </span>
                    </v-btn>
                </div>
            </v-sheet>

            <v-sheet
                v-if="showsLicenceSubNavigation"
                rounded="xl"
                class="settings-licence-subnav mb-2">
                <v-btn-toggle v-model="licence_models_action" mandatory class="settings-licence-subnav__switcher" color="primary" divided>
                    <v-btn
                        v-for="item in visibleLicenceNavigationItems"
                        :key="item.key"
                        :value="item.key"
                        class="settings-licence-subnav__button"
                        :prepend-icon="item.icon">
                        {{ item.label }}
                    </v-btn>
                </v-btn-toggle>
            </v-sheet>

            <div class="settings-content">
                <v-row class="w-100 ma-0" dense>
                    <div v-if="isSuperAdminTab && sub_action === 'schools'" class="settings-schools-wrap">
                        <Schools />
                    </div>

                    <div v-else-if="isAdminTab && sub_action === 'schoolyears'" class="settings-schoolyears-wrap">
                        <Schoolyears />
                    </div>

                    <div v-else-if="isAdminTab && sub_action === 'users'" class="settings-users-wrap">
                        <Users />
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'licence_models'" class="settings-licences-wrap">
                        <Licences v-if="licence_models_action === 'overview'" />
                        <LicenceSchools v-else-if="licence_models_action === 'schools'" />
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'roles'" class="settings-roles-wrap">
                        <Roles />
                    </div>

                    <div v-else-if="isRegisterTab && sub_action === 'users'" class="settings-users-wrap">
                        <RegisterUsers />
                    </div>

                    <div v-else-if="isProfileTab" class="settings-profile-wrap">
                        <Profile :embedded="true" />
                    </div>

                    <v-col v-else cols="12">
                        <v-sheet rounded="xl" class="pa-6 settings-empty-card">
                            <div class="settings-empty-icon">
                                <v-icon size="48" color="grey-lighten-1">mdi-cog-outline</v-icon>
                            </div>
                            <div class="settings-empty-text">
                                <template v-if="activeSubSection">
                                    Einstellungen für <strong>{{ activeSection }}</strong> &rsaquo; <strong>{{ activeSubSection }}</strong> werden hier bald verfügbar sein.
                                </template>
                                <template v-else>
                                    Einstellungen für <strong>{{ activeSection }}</strong> werden hier bald verfügbar sein.
                                </template>
                            </div>
                        </v-sheet>
                    </v-col>
                </v-row>
            </div>
        </v-container>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import Schools from '@/pages/admin/superAdmin/components/Schools.vue'
import Schoolyears from '@/pages/admin/superAdmin/components/Schoolyears.vue'
import Users from '@/pages/admin/superAdmin/components/Users.vue'
import Licences from '@/pages/admin/superAdmin/components/Licences.vue'
import LicenceSchools from '@/pages/admin/superAdmin/components/LicenceSchools.vue'
import Roles from '@/pages/admin/superAdmin/components/Roles.vue'
import RegisterUsers from '@/pages/admin/settings/components/RegisterUsers.vue'
import Profile from '@/pages/admin/profile/Profile.vue'

export default {
    components: { Schools, Schoolyears, Users, Licences, LicenceSchools, Roles, RegisterUsers, Profile },

    mounted() {
        this.syncRouteQuery()
    },

    data() {
        return {
            main_action: this.initialTab(),
            sub_action: this.initialSubAction(),
            licence_models_action: this.initialLicenceModelsAction(),
        }
    },

    watch: {
        main_action() {
            this.sub_action = this.defaultSubAction
            this.licence_models_action = 'overview'
            this.syncRouteQuery()
        },
        sub_action(val) {
            if (!this.showsLicenceSubNavigation || val !== 'licence_models') {
                this.licence_models_action = 'overview'
            }
            this.syncRouteQuery()
        },
        licence_models_action() {
            if (this.showsLicenceSubNavigation && this.sub_action === 'licence_models') {
                this.syncRouteQuery()
            }
        },
        '$route.query.tab'(val) {
            const tab = val || 'super_admin'
            if (this.navigationItems.some((i) => i.key === tab)) {
                this.main_action = tab
            }
        },
        '$route.query.panel'(val) {
            if (!this.showsSubNavigation) {
                return
            }

            const panel = val || this.defaultSubAction
            if (this.subNavigationItems.some((i) => i.key === panel)) {
                this.sub_action = panel
            }
        },
        '$route.query.licence_tab'(val) {
            if (!this.isSuperAdminTab) {
                return
            }

            const licenceTab = val || 'overview'
            if (this.visibleLicenceNavigationItems.some((i) => i.key === licenceTab)) {
                this.licence_models_action = licenceTab
            }
        },
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        headerChips() {
            const chips = []
            if (this.config?.selected_school?.long_name || this.config?.selected_school?.name) {
                chips.push({
                    label: this.config.selected_school.long_name || this.config.selected_school.name,
                    icon: 'mdi-school',
                })
            }
            return chips
        },
        activeSection() {
            const item = this.navigationItems.find((i) => i.key === this.main_action)
            return item ? item.label : ''
        },
        configuredRoleNames() {
            return Array.isArray(this.config?.roles) ? this.config.roles : []
        },
        canAccessSuperAdminSettingsTab() {
            return this.configuredRoleNames.includes('super_admin')
        },
        canAccessAdminSettingsTab() {
            return ['super_admin', 'admin'].some((role) => this.configuredRoleNames.includes(role))
        },
        canAccessRegisterSettingsTab() {
            return ['super_admin', 'admin', 'register_admin'].some((role) => this.configuredRoleNames.includes(role))
        },
        showsSubNavigation() {
            return ['super_admin', 'admin', 'register'].includes(this.main_action)
        },
        defaultSubAction() {
            if (this.main_action === 'admin') return 'schoolyears'
            if (this.main_action === 'register') return 'users'
            return 'schools'
        },
        isSuperAdminTab() {
            return this.main_action === 'super_admin'
        },
        isAdminTab() {
            return this.main_action === 'admin'
        },
        isRegisterTab() {
            return this.main_action === 'register'
        },
        isProfileTab() {
            return this.main_action === 'profile'
        },
        showsLicenceSubNavigation() {
            return this.isSuperAdminTab && this.sub_action === 'licence_models'
        },
        activeSubSection() {
            if (!this.showsSubNavigation) {
                return ''
            }

            const item = this.subNavigationItems.find((i) => i.key === this.sub_action)
            return item ? item.label : ''
        },
        subNavigationItems() {
            if (this.isRegisterTab) {
                return [
                    { key: 'users', label: 'Benutzer', meta: 'Organisation', icon: 'mdi-account-group-outline' },
                    { key: 'notifications', label: 'Benachrichtigungen', meta: 'E-Mails', icon: 'mdi-bell-outline' },
                    { key: 'templates', label: 'Vorlagen', meta: 'Dokumente', icon: 'mdi-file-document-outline' },
                ]
            }

            if (this.isAdminTab) {
                return [
                    { key: 'schoolyears', label: 'Schuljahre', meta: 'Kalender', icon: 'mdi-calendar-multiple' },
                    { key: 'users', label: 'Benutzer', meta: 'Organisation', icon: 'mdi-account-group-outline' },
                ]
            }

            return [
                { key: 'schools', label: 'Schulen', meta: 'Verwaltung', icon: 'mdi-school' },
                { key: 'licence_models', label: 'Lizenzen Modelle', meta: 'Lizenzverwaltung', icon: 'mdi-card-account-details' },
                { key: 'roles', label: 'Rollen', meta: 'Rechte', icon: 'mdi-badge-account-horizontal-outline' },
            ]
        },
        visibleLicenceNavigationItems() {
            return [
                {
                    key: 'overview',
                    label: 'Alle Lizenzen',
                    meta: 'Übersicht',
                    icon: 'mdi-home',
                },
                {
                    key: 'schools',
                    label: 'Lizenzvergaben',
                    meta: 'Schulen',
                    icon: 'mdi-card-account-details-outline',
                },
            ]
        },
        navigationItems() {
            return [
                { key: 'super_admin', label: 'Super-Admin', icon: 'mdi-shield-crown', visible: this.canAccessSuperAdminSettingsTab },
                { key: 'admin', label: 'Admin', icon: 'mdi-shield-account', visible: this.canAccessAdminSettingsTab },
                { key: 'register', label: 'Anmeldetool', icon: 'mdi-calendar-check', visible: this.canAccessRegisterSettingsTab },
                { key: 'tutoring', label: 'Nachhilfe', icon: 'mdi-account-group' },
                { key: 'teaching', label: 'Unterricht', icon: 'mdi-book-open-variant' },
                { key: 'groups', label: 'Gruppen', icon: 'mdi-account-multiple-outline' },
                { key: 'restaurant', label: 'Restaurant', icon: 'mdi-silverware-fork-knife' },
                { key: 'profile', label: 'Profil', icon: 'mdi-account-circle' },
            ].filter((item) => item.visible !== false)
        },
    },

    methods: {
        availableTabKeys(canAccessSuperAdminTab, canAccessAdminTab, canAccessRegisterTab) {
            return [
                canAccessSuperAdminTab ? 'super_admin' : null,
                canAccessAdminTab ? 'admin' : null,
                canAccessRegisterTab ? 'register' : null,
                'tutoring',
                'teaching',
                'groups',
                'restaurant',
                'profile',
            ].filter(Boolean)
        },
        initialTab() {
            const tab = this.$route?.query?.tab || 'super_admin'
            const adminStore = useAdminStore()
            const configuredRoleNames = Array.isArray(adminStore?.config?.roles) ? adminStore.config.roles : []
            const canAccessSuperAdminTab = configuredRoleNames.includes('super_admin')
            const canAccessAdminTab = ['super_admin', 'admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessRegisterTab = ['super_admin', 'admin', 'register_admin'].some((role) => configuredRoleNames.includes(role))
            const keys = this.availableTabKeys(canAccessSuperAdminTab, canAccessAdminTab, canAccessRegisterTab)

            return keys.includes(tab) ? tab : keys[0]
        },
        initialSubAction() {
            const panel = this.$route?.query?.panel || 'schools'
            const tab = this.$route?.query?.tab || 'super_admin'
            const adminStore = useAdminStore()
            const configuredRoleNames = Array.isArray(adminStore?.config?.roles) ? adminStore.config.roles : []
            const canAccessSuperAdminTab = configuredRoleNames.includes('super_admin')
            const canAccessAdminTab = ['super_admin', 'admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessRegisterTab = ['super_admin', 'admin', 'register_admin'].some((role) => configuredRoleNames.includes(role))
            const resolvedTab = this.availableTabKeys(canAccessSuperAdminTab, canAccessAdminTab, canAccessRegisterTab).includes(tab)
                ? tab
                : this.availableTabKeys(canAccessSuperAdminTab, canAccessAdminTab, canAccessRegisterTab)[0]
            let keys, fallback
            if (resolvedTab === 'admin') {
                keys = ['schoolyears', 'users']
                fallback = 'schoolyears'
            } else if (resolvedTab === 'register') {
                keys = ['users', 'notifications', 'templates']
                fallback = 'users'
            } else {
                keys = ['schools', 'licence_models', 'roles']
                fallback = 'schools'
            }

            return keys.includes(panel) ? panel : fallback
        },
        initialLicenceModelsAction() {
            const tab = this.$route?.query?.licence_tab || 'overview'
            const keys = ['overview', 'schools']
            return keys.includes(tab) ? tab : 'overview'
        },
        syncRouteQuery() {
            const query = {}

            if (this.main_action !== 'super_admin') {
                query.tab = this.main_action
            }

            if (this.showsSubNavigation && this.sub_action !== this.defaultSubAction) {
                query.panel = this.sub_action
            }

            if (this.isSuperAdminTab && this.sub_action === 'licence_models' && this.licence_models_action !== 'overview') {
                query.licence_tab = this.licence_models_action
            }

            const search = new URLSearchParams(query).toString()
            const target = search ? `/admin/settings?${search}` : '/admin/settings'

            if (this.$route.fullPath !== target) {
                this.$router.replace(target)
            }
        },
    },
}
</script>

<style scoped>
.settings-page {
    position: relative;
    min-height: 100vh;
}

.settings-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    pointer-events: none;
}

.settings-bg-image {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
}

.settings-bg-glow {
    position: absolute;
    width: 600px;
    height: 600px;
    border-radius: 50%;
    filter: blur(120px);
    opacity: 0.18;
}

.settings-bg-glow-left {
    top: -200px;
    left: -100px;
    background: #6366f1;
}

.settings-bg-glow-right {
    bottom: -200px;
    right: -100px;
    background: #818cf8;
}

.settings-page-inner {
    position: relative;
    z-index: 1;
}

.settings-tabs-sheet {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.settings-tabs-sheet :deep(.v-tab:not(.v-tab--selected)) {
    color: rgba(255, 255, 255, 0.45) !important;
}

.settings-subnav {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 8px;
}

.settings-subnav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.settings-subnav__button {
    text-transform: none !important;
    letter-spacing: 0 !important;
    padding: 6px 16px !important;
    height: auto !important;
    min-height: 44px;
}

.settings-subnav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.settings-subnav__button-title {
    font-weight: 700;
    font-size: 0.82rem;
}

.settings-subnav__button-meta {
    font-size: 0.68rem;
    opacity: 0.65;
}

.settings-schools-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-schoolyears-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-users-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-licences-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-roles-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-profile-wrap {
    width: 100%;
}

.settings-licence-subnav {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 8px;
}

.settings-licence-subnav__switcher {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.settings-licence-subnav__button {
    text-transform: none !important;
    letter-spacing: 0 !important;
}

.settings-empty-card {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
    text-align: center;
    padding: 48px 24px !important;
}

.settings-empty-icon {
    margin-bottom: 16px;
}

.settings-empty-text {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.95rem;
}
</style>
