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

            <div v-if="activeRoles.length > 0" class="settings-roles-bar mb-2">
                <v-icon size="14" color="rgba(255,255,255,0.4)" class="mr-1">mdi-shield-account</v-icon>
                <v-chip
                    v-for="role in activeRoles"
                    :key="role"
                    size="x-small"
                    variant="tonal"
                    color="indigo-lighten-3"
                    class="settings-role-chip">
                    {{ role }}
                </v-chip>
            </div>

            <nav
                v-if="isSuperAdminTab || isAdminTab"
                class="settings-subnav settings-section-subnav mb-2"
                :aria-label="`${activeSection} Einstellungen`">
                <v-btn-toggle v-model="sub_action" mandatory divided color="primary" class="settings-section-subnav__switcher">
                    <v-btn
                        v-for="item in subNavigationItems"
                        :key="item.key"
                        :value="item.key"
                        :prepend-icon="item.icon"
                        :aria-pressed="sub_action === item.key"
                        class="settings-section-subnav__button">
                        <span class="settings-section-subnav__copy">
                            <span>{{ item.label }}</span>
                            <span class="settings-section-subnav__meta">{{ item.meta }}</span>
                        </span>
                    </v-btn>
                </v-btn-toggle>
            </nav>

            <v-sheet v-else-if="showsSubNavigation" rounded="xl" class="settings-subnav mb-2">
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

            <v-sheet
                v-if="showsGeneralSubNavigation"
                rounded="xl"
                class="settings-general-subnav mb-2">
                <div class="settings-general-subnav__inner">
                    <div class="settings-general-subnav__items">
                        <v-btn
                            v-for="item in generalNavigationItems"
                            :key="item.key"
                            size="small"
                            rounded="xl"
                            :variant="general_action === item.key ? 'flat' : 'tonal'"
                            :class="general_action === item.key ? 'settings-general-subnav__item--active' : 'settings-general-subnav__item--idle'"
                            class="settings-general-subnav__item"
                            @click="general_action = item.key">
                            {{ item.label }}
                        </v-btn>
                    </div>
                </div>
            </v-sheet>

            <div class="settings-content">
                <v-row class="w-100 ma-0" dense>
                    <div v-if="isSuperAdminTab && sub_action === 'general'" class="settings-general-wrap">
                        <ModuleStatusesCard v-if="general_action === 'module_visibility'" />
                    </div>

                    <div v-else-if="(isSuperAdminTab || isAdminTab) && sub_action === 'schools'" class="settings-schools-wrap">
                        <Schools />
                    </div>

                    <div v-else-if="isAdminTab && sub_action === 'schoolyears'" class="settings-schoolyears-wrap">
                        <Schoolyears />
                    </div>

                    <div v-else-if="isAdminTab && sub_action === 'users'" class="settings-users-wrap">
                        <Users />
                    </div>

                    <div v-else-if="isAdminTab && sub_action === 'school_groups'" class="settings-groups-wrap">
                        <Groups :embedded="true" embedded-filter="school" />
                    </div>

                    <div v-else-if="isAdminTab && sub_action === 'log'" class="settings-log-wrap">
                        <Log :embedded="true" />
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'licence_models'" class="settings-licences-wrap">
                        <Licences v-if="licence_models_action === 'overview'" />
                        <LicenceSchools v-else-if="licence_models_action === 'schools'" />
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'storage_audit'" class="settings-storage-audit-wrap">
                        <StorageAudit />
                    </div>

                    <v-col v-else-if="isSuperAdminTab && canAccessSuperAdminSettingsTab && sub_action === 'preview'" cols="12">
                        <PreviewAccess />
                    </v-col>

                    <div v-else-if="isSuperAdminTab && sub_action === 'roles'" class="settings-roles-wrap">
                        <Roles />
                    </div>

                    <div v-else-if="isRegisterTab && sub_action === 'users'" class="settings-users-wrap">
                        <RegisterUsers />
                    </div>

                    <div v-else-if="isTutoringTab && sub_action === 'tutoring_settings'" class="settings-tutoring-wrap">
                        <TutoringSettings />
                    </div>

                    <div v-else-if="isTutoringTab && sub_action === 'tutoring_subjects'" class="settings-tutoring-wrap">
                        <TutoringSubjects />
                    </div>

                    <div v-else-if="isTutoringTab && sub_action === 'tutoring_users'" class="settings-tutoring-wrap">
                        <TutoringUsers />
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'school_switch'" class="settings-school-switch-wrap">
                        <ActiveSchool :hide-details="true" />
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'user_impersonation'" class="settings-user-impersonation-wrap">
                        <UserImpersonation />
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
import { defineAsyncComponent } from 'vue'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { administration as teachingAdministration } from '@/routes/admin/teaching'

const Schools = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/Schools.vue'))
const Schoolyears = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/Schoolyears.vue'))
const Users = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/Users.vue'))
const Licences = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/Licences.vue'))
const LicenceSchools = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/LicenceSchools.vue'))
const Roles = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/Roles.vue'))
const Log = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/Log.vue'))
const RegisterUsers = defineAsyncComponent(() => import('@/pages/admin/settings/components/RegisterUsers.vue'))
const ModuleStatusesCard = defineAsyncComponent(() => import('@/pages/admin/settings/components/ModuleStatusesCard.vue'))
const ActiveSchool = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/ActiveSchool.vue'))
const UserImpersonation = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/UserImpersonation.vue'))
const StorageAudit = defineAsyncComponent(() => import('@/pages/admin/superAdmin/components/StorageAudit.vue'))
const PreviewAccess = defineAsyncComponent(() => import('@/pages/admin/settings/components/PreviewAccess.vue'))
const TutoringSettings = defineAsyncComponent(() => import('@/pages/admin/tutoring/components/Settings.vue'))
const TutoringSubjects = defineAsyncComponent(() => import('@/pages/admin/tutoring/components/Subjects.vue'))
const TutoringUsers = defineAsyncComponent(() => import('@/pages/admin/tutoring/components/Users.vue'))
const Groups = defineAsyncComponent(() => import('@/pages/admin/groups/Groups.vue'))

export default {
    components: { Schools, Schoolyears, Users, Licences, LicenceSchools, Roles, Log, RegisterUsers, ModuleStatusesCard, ActiveSchool, UserImpersonation, StorageAudit, PreviewAccess, TutoringSettings, TutoringSubjects, TutoringUsers, Groups },

    mounted() {
        this.syncRouteQuery()
    },

    data() {
        return {
            main_action: this.initialTab(),
            sub_action: this.initialSubAction(),
            licence_models_action: this.initialLicenceModelsAction(),
            general_action: this.initialGeneralAction(),
        }
    },

    watch: {
        main_action() {
            this.sub_action = this.defaultSubAction
            this.licence_models_action = 'overview'
            this.general_action = 'module_visibility'
            this.syncRouteQuery()
        },
        sub_action(val) {
            if (!this.showsLicenceSubNavigation || val !== 'licence_models') {
                this.licence_models_action = 'overview'
            }
            if (!this.showsGeneralSubNavigation || val !== 'general') {
                this.general_action = 'module_visibility'
            }
            this.syncRouteQuery()
        },
        licence_models_action() {
            if (this.showsLicenceSubNavigation && this.sub_action === 'licence_models') {
                this.syncRouteQuery()
            }
        },
        general_action() {
            if (this.showsGeneralSubNavigation && this.sub_action === 'general') {
                this.syncRouteQuery()
            }
        },
        '$route.query.tab'(val) {
            if (val === 'materials' || val === 'restaurant' || val === 'groups') {
                this.syncRouteQuery()
                return
            }
            if (val === 'profile') {
                this.$router.replace('/admin/profile')
                return
            }
            const tab = val || 'super_admin'
            if (this.navigationItems.some((i) => i.key === tab)) {
                this.main_action = tab
            }
        },
        '$route.query.panel'(val) {
            if (!this.showsSubNavigation) {
                return
            }

            const panel = this.isSuperAdminTab && (!val || val === 'general') && this.$route.query.general_panel === 'licences'
                ? 'licence_models'
                : val || this.defaultSubAction
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
        '$route.query.general_panel'(val) {
            if (!this.showsGeneralSubNavigation) {
                return
            }

            if (val === 'licences') {
                this.sub_action = 'licence_models'
                return
            }

            const generalPanel = val || 'module_visibility'
            if (this.generalNavigationItems.some((i) => i.key === generalPanel)) {
                this.general_action = generalPanel
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
        tabRoleMap() {
            return {
                super_admin: ['super_admin'],
                admin: ['super_admin', 'admin'],
                register: ['super_admin', 'admin', 'register_admin'],
                teaching: ['super_admin', 'admin', 'teaching_admin'],
                tutoring: ['super_admin', 'admin', 'tutoring_admin'],
            }
        },
        activeRoles() {
            return this.tabRoleMap[this.main_action] || []
        },
        activeSection() {
            const item = this.navigationItems.find((i) => i.key === this.main_action)
            return item ? item.label : ''
        },
        configuredCapabilities() {
            return this.config?.capabilities || {}
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
            if (typeof this.configuredCapabilities.register_system === 'boolean') {
                return this.configuredCapabilities.register_system
            }

            return ['super_admin', 'admin', 'register_admin'].some((role) => this.configuredRoleNames.includes(role))
        },
        canAccessMaterialsAdministration() {
            return ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((role) => this.configuredRoleNames.includes(role))
        },
        canAccessRestaurantSettingsTab() {
            return this.canAccessRestaurantSettings(this.configuredRoleNames, this.configuredCapabilities)
        },
        canAccessTutoringSettingsTab() {
            if (typeof this.configuredCapabilities.tutoring === 'boolean') {
                return this.configuredCapabilities.tutoring
            }

            return ['super_admin', 'admin', 'tutoring_admin'].some((role) => this.configuredRoleNames.includes(role))
        },
        canAccessTeachingSettingsTab() {
            if (typeof this.configuredCapabilities.teaching === 'boolean') {
                return this.configuredCapabilities.teaching
            }

            return ['super_admin', 'admin', 'teaching_admin', 'teacher'].some((role) => this.configuredRoleNames.includes(role))
        },
        showsSubNavigation() {
            return ['super_admin', 'admin', 'register', 'tutoring'].includes(this.main_action)
        },
        defaultSubAction() {
            if (this.main_action === 'admin') return 'schoolyears'
            if (this.main_action === 'register') return 'users'
            if (this.main_action === 'teaching') return 'teaching_admin'
            if (this.main_action === 'tutoring') return 'tutoring_settings'
            return 'general'
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
        isTutoringTab() {
            return this.main_action === 'tutoring'
        },
        isTeachingTab() {
            return this.main_action === 'teaching'
        },
        showsLicenceSubNavigation() {
            return this.isSuperAdminTab && this.sub_action === 'licence_models'
        },
        showsGeneralSubNavigation() {
            return this.isSuperAdminTab && this.sub_action === 'general'
        },
        generalNavigationItems() {
            return [
                {
                    key: 'module_visibility',
                    label: 'Sichtbarkeit Modul',
                },
            ]
        },
        activeSubSection() {
            if (!this.showsSubNavigation) {
                return ''
            }

            const item = this.subNavigationItems.find((i) => i.key === this.sub_action)
            return item ? item.label : ''
        },
        subNavigationItems() {
            if (this.isTutoringTab) {
                return [
                    { key: 'tutoring_settings', label: 'Einstellungen', meta: 'Nachhilfe', icon: 'mdi-cog-outline' },
                    { key: 'tutoring_subjects', label: 'Fächer', meta: 'Fächer verwalten', icon: 'mdi-television-shimmer' },
                    { key: 'tutoring_users', label: 'Benutzer', meta: 'Nachhilfe', icon: 'mdi-account-multiple-outline' },
                ]
            }

            if (this.isRegisterTab) {
                return [
                    { key: 'users', label: 'Benutzer', meta: 'Anmeldetool', icon: 'mdi-account-group-outline' },
                ]
            }

            if (this.isTeachingTab) {
                return []
            }

            if (this.isAdminTab) {
                return [
                    { key: 'schoolyears', label: 'Schuljahre', meta: 'Kalender', icon: 'mdi-calendar-multiple' },
                    { key: 'schools', label: 'Schule', meta: 'Darstellung', icon: 'mdi-palette-outline' },
                    { key: 'users', label: 'Benutzer', meta: 'Organisation', icon: 'mdi-account-group-outline' },
                    { key: 'school_groups', label: 'Schulgruppen', meta: 'Gruppen', icon: 'mdi-account-multiple-outline' },
                    { key: 'log', label: 'Log', meta: 'System', icon: 'mdi-file-document-outline' },
                ]
            }

            return [
                { key: 'general', label: 'Grundeinstellungen', meta: 'Allgemein', icon: 'mdi-tune-variant' },
                { key: 'schools', label: 'Schulen', meta: 'Verwaltung', icon: 'mdi-school' },
                { key: 'licence_models', label: 'Lizenzen Modelle', meta: 'Lizenzverwaltung', icon: 'mdi-card-account-details' },
                { key: 'storage_audit', label: 'Speicherprüfung', meta: 'R2 & Datenbank', icon: 'mdi-database-search' },
                { key: 'roles', label: 'Rollen', meta: 'Rechte', icon: 'mdi-badge-account-horizontal-outline' },
                { key: 'school_switch', label: 'Schule wechseln', meta: 'Aktive Schule', icon: 'mdi-swap-horizontal' },
                { key: 'user_impersonation', label: 'Benutzer wechseln', meta: 'Übernahme', icon: 'mdi-account-switch' },
                { key: 'preview', label: 'Vorschau', meta: 'Zugang verwalten', icon: 'mdi-flask-outline' },
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
                { key: 'tutoring', label: 'Nachhilfe', icon: 'mdi-account-group', visible: this.canAccessTutoringSettingsTab },
            ].filter((item) => item.visible !== false)
        },
    },

    methods: {
        availableTabKeys(
            canAccessSuperAdminTab,
            canAccessAdminTab,
            canAccessRegisterTab,
            canAccessTutoringTab,
            canAccessTeachingTab,
        ) {
            return [
                canAccessSuperAdminTab ? 'super_admin' : null,
                canAccessAdminTab ? 'admin' : null,
                canAccessRegisterTab ? 'register' : null,
                canAccessTutoringTab ? 'tutoring' : null,
                canAccessTeachingTab ? 'teaching' : null,
            ].filter(Boolean)
        },
        canAccessRestaurantSettings(configuredRoleNames, configuredCapabilities) {
            if (typeof configuredCapabilities.restaurant === 'boolean') {
                return configuredCapabilities.restaurant
            }

            if (Object.keys(configuredCapabilities).length > 0) {
                return false
            }

            return ['super_admin', 'admin', 'lunch_admin'].some((role) => configuredRoleNames.includes(role))
        },
        initialTab() {
            const tab = this.$route?.query?.tab || 'super_admin'
            const adminStore = useAdminStore()
            const configuredRoleNames = Array.isArray(adminStore?.config?.roles) ? adminStore.config.roles : []
            const configuredCapabilities = adminStore?.config?.capabilities || {}
            const canAccessSuperAdminTab = configuredRoleNames.includes('super_admin')
            const canAccessAdminTab = ['super_admin', 'admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessRegisterTab = typeof configuredCapabilities.register_system === 'boolean'
                ? configuredCapabilities.register_system
                : ['super_admin', 'admin', 'register_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessTutoringTab = typeof configuredCapabilities.tutoring === 'boolean'
                ? configuredCapabilities.tutoring
                : ['super_admin', 'admin', 'tutoring_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessTeachingTab = typeof configuredCapabilities.teaching === 'boolean'
                ? configuredCapabilities.teaching
                : ['super_admin', 'admin', 'teaching_admin', 'teacher'].some((role) => configuredRoleNames.includes(role))
            const keys = this.availableTabKeys(
                canAccessSuperAdminTab,
                canAccessAdminTab,
                canAccessRegisterTab,
                canAccessTutoringTab,
                canAccessTeachingTab,
            )

            return keys.includes(tab) ? tab : keys[0]
        },
        initialSubAction() {
            const panel = this.$route?.query?.panel || 'general'
            const tab = this.$route?.query?.tab || 'super_admin'
            const adminStore = useAdminStore()
            const configuredRoleNames = Array.isArray(adminStore?.config?.roles) ? adminStore.config.roles : []
            const configuredCapabilities = adminStore?.config?.capabilities || {}
            const canAccessSuperAdminTab = configuredRoleNames.includes('super_admin')
            const canAccessAdminTab = ['super_admin', 'admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessRegisterTab = typeof configuredCapabilities.register_system === 'boolean'
                ? configuredCapabilities.register_system
                : ['super_admin', 'admin', 'register_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessTutoringTab = typeof configuredCapabilities.tutoring === 'boolean'
                ? configuredCapabilities.tutoring
                : ['super_admin', 'admin', 'tutoring_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessTeachingTab = typeof configuredCapabilities.teaching === 'boolean'
                ? configuredCapabilities.teaching
                : ['super_admin', 'admin', 'teaching_admin', 'teacher'].some((role) => configuredRoleNames.includes(role))
            const availableTabs = this.availableTabKeys(
                canAccessSuperAdminTab,
                canAccessAdminTab,
                canAccessRegisterTab,
                canAccessTutoringTab,
                canAccessTeachingTab,
            )
            const resolvedTab = availableTabs.includes(tab)
                ? tab
                : availableTabs[0]
            let keys, fallback
            if (resolvedTab === 'admin') {
                keys = ['schoolyears', 'schools', 'users', 'school_groups', 'log']
                fallback = 'schoolyears'
            } else if (resolvedTab === 'tutoring') {
                keys = ['tutoring_settings', 'tutoring_subjects', 'tutoring_users']
                fallback = 'tutoring_settings'
            } else if (resolvedTab === 'register') {
                keys = ['users']
                fallback = 'users'
            } else if (resolvedTab === 'teaching') {
                keys = ['teaching_admin']
                fallback = 'teaching_admin'
            } else {
                keys = ['general', 'schools', 'licence_models', 'storage_audit', 'roles', 'school_switch', 'user_impersonation', 'preview']
                fallback = 'general'
            }

            if (resolvedTab === 'super_admin' && panel === 'general' && this.$route?.query?.general_panel === 'licences') {
                return 'licence_models'
            }

            return keys.includes(panel) ? panel : fallback
        },
        initialLicenceModelsAction() {
            const tab = this.$route?.query?.licence_tab || 'overview'
            const keys = ['overview', 'schools']
            return keys.includes(tab) ? tab : 'overview'
        },
        initialGeneralAction() {
            const panel = this.$route?.query?.general_panel || 'module_visibility'
            const keys = ['module_visibility']

            return keys.includes(panel) ? panel : 'module_visibility'
        },
        syncRouteQuery() {
            if (this.$route.query?.tab === 'groups' && this.configuredRoleNames.includes('super_admin')) {
                const panel = this.$route.query?.panel === 'groups_own' ? 'groups_own' : 'groups_overview'
                this.$router.replace(`/admin/groups?panel=${panel}`)
                return
            }

            if (this.$route.query?.tab === 'restaurant' && this.canAccessRestaurantSettingsTab) {
                const requestedPanel = this.$route.query?.panel
                const panels = ['general', 'categories', 'ingredient-icons', 'free-days', 'eating-times', 'users', 'sepa', 'online']
                const panel = panels.includes(requestedPanel) ? requestedPanel : 'general'
                this.$router.replace(`/admin/restaurant/settings?panel=${panel}`)
                return
            }

            if (this.$route.query?.tab === 'materials' && this.canAccessMaterialsAdministration) {
                const panel = this.$route.query?.panel === 'material_groups' ? 'material_groups' : 'material_settings'
                this.$router.replace(`/admin/materials-v2?section=admin&panel=${panel}`)
                return
            }

            if (this.main_action === 'teaching') {
                if (!['super_admin', 'admin', 'teaching_admin'].some((role) => this.configuredRoleNames.includes(role))) {
                    this.$router.replace('/admin/profile')
                    return
                }

                const requestedPanel = this.$route.query?.tab === 'teaching' ? this.$route.query?.panel : null
                const panel = ['teachers', 'import', 'holidays', 'school_hours'].includes(requestedPanel) ? requestedPanel : 'import'
                this.$router.replace(teachingAdministration.url({ query: { panel } }))
                return
            }

            if (this.$route.query?.tab === 'profile' || !this.main_action) {
                this.$router.replace('/admin/profile')
                return
            }

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

            if (this.isSuperAdminTab && this.sub_action === 'general' && this.general_action !== 'module_visibility') {
                query.general_panel = this.general_action
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

.settings-roles-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
}

.settings-roles-label {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.4);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-right: 2px;
}

.settings-role-chip {
    font-size: 0.7rem !important;
    font-weight: 600;
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

.settings-section-subnav {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 8px;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8) !important;
    padding: 10px;
}

.settings-section-subnav__switcher {
    width: 100%;
    flex-wrap: wrap;
    row-gap: 6px;
    height: auto !important;
}

.settings-section-subnav__button {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
    height: auto !important;
    min-height: 56px !important;
}

.settings-section-subnav__copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.settings-section-subnav__meta {
    color: rgba(255, 255, 255, 0.98);
    font-size: 0.76rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 2px 8px;
}

.settings-schools-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-schoolyears-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-general-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-users-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-log-wrap {
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

.settings-school-switch-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-user-impersonation-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-tutoring-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-licence-subnav {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 8px;
}

.settings-general-subnav {
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(15, 23, 42, 0.7) !important;
    padding: 8px 12px;
}

.settings-general-subnav__inner {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
}

.settings-general-subnav__items {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
}

.settings-general-subnav__item {
    text-transform: none !important;
    letter-spacing: 0 !important;
    font-weight: 600;
    height: 30px !important;
    font-size: 0.82rem;
}

.settings-general-subnav__item--idle {
    background: rgba(99, 102, 241, 0.15) !important;
    color: #a5b4fc !important;
    border: 1px solid rgba(99, 102, 241, 0.25) !important;
}

.settings-general-subnav__item--idle:hover {
    background: rgba(99, 102, 241, 0.28) !important;
    color: #c7d2fe !important;
}

.settings-general-subnav__item--active {
    background: linear-gradient(135deg, #4f46e5, #6366f1) !important;
    color: #fff !important;
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.45) !important;
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

.settings-groups-wrap {
    width: 100%;
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
