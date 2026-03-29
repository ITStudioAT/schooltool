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
                v-if="showsTeacherSubNavigation"
                rounded="xl"
                class="settings-licence-subnav mb-2">
                <v-btn-toggle v-model="teachers_action" mandatory class="settings-licence-subnav__switcher" color="primary" divided>
                    <v-btn value="teachers" class="settings-teacher-subnav__button" prepend-icon="mdi-account-tie" size="small">
                        Lehrer
                    </v-btn>
                    <v-btn value="teachers_list" class="settings-teacher-subnav__button" prepend-icon="mdi-view-list" size="small">
                        Lehrerliste
                    </v-btn>
                </v-btn-toggle>
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
                    <div v-if="isSuperAdminTab && sub_action === 'general'" class="settings-general-wrap">
                        <v-sheet rounded="xl" class="pa-6 settings-empty-card">
                            <div class="d-flex align-center mb-4">
                                <v-icon size="32" color="indigo-lighten-2" class="mr-3">mdi-tune-variant</v-icon>
                                <div>
                                    <div class="text-h6 font-weight-bold" style="color: rgba(255,255,255,0.9)">Grundeinstellungen</div>
                                    <div class="text-body-2" style="color: rgba(255,255,255,0.5)">Allgemeine Konfiguration der Anwendung</div>
                                </div>
                            </div>
                            <v-divider class="mb-4" style="border-color: rgba(255,255,255,0.08)" />
                            <div class="text-body-2" style="color: rgba(255,255,255,0.4)">
                                Dieser Bereich wird in Kürze verfügbar sein.
                            </div>
                        </v-sheet>
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'schools'" class="settings-schools-wrap">
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

                    <div v-else-if="isMaterialsTab && sub_action === 'material_groups'" class="settings-groups-wrap">
                        <Groups :embedded="true" embedded-filter="materials" />
                    </div>

                    <div v-else-if="isMaterialsTab && sub_action === 'material_settings'" class="settings-materials-wrap">
                        <MaterialsSettingsView />
                    </div>

                    <div v-else-if="isTeachingTab && sub_action === 'teaching_admin'" class="settings-teaching-admin-wrap">
                        <TeachingAdmin />
                    </div>

                    <div v-else-if="isTeachingTab && sub_action === 'teachers'" class="settings-teachers-wrap">
                        <Teachers v-if="teachers_action === 'teachers'" :hide-back-button="true" />
                        <TeachersList v-else-if="teachers_action === 'teachers_list'" :hide-back-button="true" />
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'school_switch'" class="settings-school-switch-wrap">
                        <ActiveSchool :hide-details="true" />
                    </div>

                    <div v-else-if="isSuperAdminTab && sub_action === 'user_impersonation'" class="settings-user-impersonation-wrap">
                        <UserImpersonation />
                    </div>

                    <div v-else-if="isGroupsTab && sub_action === 'groups_overview'" class="settings-groups-wrap">
                        <Groups :embedded="true" />
                    </div>

                    <div v-else-if="isGroupsTab && sub_action === 'groups_own'" class="settings-groups-wrap">
                        <Groups :embedded="true" embedded-filter="own" />
                    </div>

                    <div v-else-if="isRestaurantTab" class="settings-restaurant-wrap">
                        <RestaurantSettings :embedded="true" :panel="sub_action" />
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
import Log from '@/pages/admin/superAdmin/components/Log.vue'
import RegisterUsers from '@/pages/admin/settings/components/RegisterUsers.vue'
import Profile from '@/pages/admin/profile/Profile.vue'
import ActiveSchool from '@/pages/admin/superAdmin/components/ActiveSchool.vue'
import UserImpersonation from '@/pages/admin/superAdmin/components/UserImpersonation.vue'
import Teachers from '@/pages/admin/superAdmin/components/Teachers.vue'
import TeachersList from '@/pages/admin/superAdmin/components/TeachersList.vue'
import TutoringSettings from '@/pages/admin/tutoring/components/Settings.vue'
import TutoringSubjects from '@/pages/admin/tutoring/components/Subjects.vue'
import TutoringUsers from '@/pages/admin/tutoring/components/Users.vue'
import TeachingAdmin from '@/pages/admin/teaching/admin/Admin.vue'
import MaterialsSettingsView from '@/pages/admin/materials/components/views/MaterialsSettingsView.vue'
import Groups from '@/pages/admin/groups/Groups.vue'
import RestaurantSettings from '@/pages/admin/restaurant/components/Settings.vue'

export default {
    components: { Schools, Schoolyears, Users, Licences, LicenceSchools, Roles, Log, RegisterUsers, Profile, ActiveSchool, UserImpersonation, Teachers, TeachersList, TutoringSettings, TutoringSubjects, TutoringUsers, TeachingAdmin, MaterialsSettingsView, Groups, RestaurantSettings },

    mounted() {
        this.syncRouteQuery()
    },

    data() {
        return {
            main_action: this.initialTab(),
            sub_action: this.initialSubAction(),
            licence_models_action: this.initialLicenceModelsAction(),
            teachers_action: 'teachers',
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
        tabRoleMap() {
            return {
                super_admin: ['super_admin'],
                admin: ['super_admin', 'admin'],
                register: ['super_admin', 'admin', 'register_admin'],
                teaching: ['super_admin', 'admin', 'teaching_admin'],
                tutoring: ['super_admin', 'admin', 'tutoring_admin'],
                materials: ['super_admin', 'admin', 'materials_admin', 'materials_moderator'],
                groups: ['super_admin', 'admin', 'materials_admin', 'materials_moderator'],
                restaurant: ['super_admin', 'admin', 'lunch_admin'],
                profile: ['Jede/r'],
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
            return ['super_admin', 'admin', 'register_admin'].some((role) => this.configuredRoleNames.includes(role))
        },
        canAccessMaterialsSettingsTab() {
            return ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((role) => this.configuredRoleNames.includes(role))
        },
        canAccessGroupsSettingsTab() {
            return ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((role) => this.configuredRoleNames.includes(role))
        },
        canAccessRestaurantSettingsTab() {
            return ['super_admin', 'admin', 'lunch_admin'].some((role) => this.configuredRoleNames.includes(role))
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
        canAccessProfileTab() {
            if (typeof this.configuredCapabilities.profile === 'boolean') {
                return this.configuredCapabilities.profile
            }

            return ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin', 'aba_teacher'].some((role) => this.configuredRoleNames.includes(role))
        },
        showsSubNavigation() {
            return ['super_admin', 'admin', 'register', 'teaching', 'tutoring', 'materials', 'groups', 'restaurant'].includes(this.main_action)
        },
        defaultSubAction() {
            if (this.main_action === 'admin') return 'schoolyears'
            if (this.main_action === 'register') return 'users'
            if (this.main_action === 'teaching') return 'teachers'
            if (this.main_action === 'tutoring') return 'tutoring_settings'
            if (this.main_action === 'materials') return 'material_settings'
            if (this.main_action === 'groups') return 'groups_overview'
            if (this.main_action === 'restaurant') return 'general'
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
        isMaterialsTab() {
            return this.main_action === 'materials'
        },
        isTeachingTab() {
            return this.main_action === 'teaching'
        },
        isGroupsTab() {
            return this.main_action === 'groups'
        },
        isRestaurantTab() {
            return this.main_action === 'restaurant'
        },
        isProfileTab() {
            return this.main_action === 'profile'
        },
        showsTeacherSubNavigation() {
            return this.isTeachingTab && this.sub_action === 'teachers'
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
                return [
                    { key: 'teachers', label: 'Lehrer', meta: 'Lehrerliste', icon: 'mdi-account-tie' },
                    { key: 'teaching_admin', label: 'Admin', meta: 'Import, Ferien, Stunden', icon: 'mdi-import' },
                ]
            }

            if (this.isMaterialsTab) {
                return [
                    { key: 'material_settings', label: 'Einstellungen', meta: 'Materialien', icon: 'mdi-cog-outline' },
                    { key: 'material_groups', label: 'Materialgruppen', meta: 'Gruppen', icon: 'mdi-folder-multiple-outline' },
                ]
            }

            if (this.isGroupsTab) {
                return [
                    { key: 'groups_overview', label: 'Überblick', meta: 'Alle Gruppentypen', icon: 'mdi-view-dashboard-outline' },
                    { key: 'groups_own', label: 'Eigene Gruppen', meta: 'Verwalten', icon: 'mdi-account-multiple-outline' },
                ]
            }

            if (this.isRestaurantTab) {
                return [
                    { key: 'general', label: 'Allgemein', meta: 'Schulweite Einstellungen', icon: 'mdi-tune-variant' },
                    { key: 'categories', label: 'Kategorien', meta: 'Speisen strukturieren', icon: 'mdi-shape-outline' },
                    { key: 'ingredient-icons', label: 'Zutaten-Symbole', meta: 'Kennzeichnungen', icon: 'mdi-image-multiple-outline' },
                    { key: 'free-days', label: 'Freie Tage', meta: 'Schließzeiten', icon: 'mdi-calendar-remove-outline' },
                    { key: 'eating-times', label: 'Speisezeiten', meta: 'Ausgabe planen', icon: 'mdi-clock-outline' },
                    { key: 'online', label: 'Online', meta: 'Bestellung & Sichtbarkeit', icon: 'mdi-web' },
                ]
            }

            if (this.isAdminTab) {
                return [
                    { key: 'schoolyears', label: 'Schuljahre', meta: 'Kalender', icon: 'mdi-calendar-multiple' },
                    { key: 'users', label: 'Benutzer', meta: 'Organisation', icon: 'mdi-account-group-outline' },
                    { key: 'school_groups', label: 'Schulgruppen', meta: 'Gruppen', icon: 'mdi-account-multiple-outline' },
                    { key: 'log', label: 'Log', meta: 'System', icon: 'mdi-file-document-outline' },
                ]
            }

            return [
                { key: 'general', label: 'Grundeinstellungen', meta: 'Allgemein', icon: 'mdi-tune-variant' },
                { key: 'schools', label: 'Schulen', meta: 'Verwaltung', icon: 'mdi-school' },
                { key: 'licence_models', label: 'Lizenzen Modelle', meta: 'Lizenzverwaltung', icon: 'mdi-card-account-details' },
                { key: 'roles', label: 'Rollen', meta: 'Rechte', icon: 'mdi-badge-account-horizontal-outline' },
                { key: 'school_switch', label: 'Schule wechseln', meta: 'Aktive Schule', icon: 'mdi-swap-horizontal' },
                { key: 'user_impersonation', label: 'Benutzer wechseln', meta: 'Übernahme', icon: 'mdi-account-switch' },
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
                { key: 'teaching', label: 'Unterricht', icon: 'mdi-book-open-variant', visible: this.canAccessTeachingSettingsTab },
                { key: 'materials', label: 'Materialien', icon: 'mdi-package-variant-closed', visible: this.canAccessMaterialsSettingsTab },
                { key: 'groups', label: 'Gruppen', icon: 'mdi-account-multiple-outline', visible: this.canAccessGroupsSettingsTab },
                { key: 'restaurant', label: 'Restaurant', icon: 'mdi-silverware-fork-knife', visible: this.canAccessRestaurantSettingsTab },
                { key: 'profile', label: 'Profil', icon: 'mdi-account-circle', visible: this.canAccessProfileTab },
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
            canAccessMaterialsTab,
            canAccessGroupsTab,
            canAccessRestaurantTab,
            canAccessProfileTab,
        ) {
            return [
                canAccessSuperAdminTab ? 'super_admin' : null,
                canAccessAdminTab ? 'admin' : null,
                canAccessRegisterTab ? 'register' : null,
                canAccessTutoringTab ? 'tutoring' : null,
                canAccessTeachingTab ? 'teaching' : null,
                canAccessMaterialsTab ? 'materials' : null,
                canAccessGroupsTab ? 'groups' : null,
                canAccessRestaurantTab ? 'restaurant' : null,
                canAccessProfileTab ? 'profile' : null,
            ].filter(Boolean)
        },
        initialTab() {
            const tab = this.$route?.query?.tab || 'super_admin'
            const adminStore = useAdminStore()
            const configuredRoleNames = Array.isArray(adminStore?.config?.roles) ? adminStore.config.roles : []
            const configuredCapabilities = adminStore?.config?.capabilities || {}
            const canAccessSuperAdminTab = configuredRoleNames.includes('super_admin')
            const canAccessAdminTab = ['super_admin', 'admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessRegisterTab = ['super_admin', 'admin', 'register_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessTutoringTab = typeof configuredCapabilities.tutoring === 'boolean'
                ? configuredCapabilities.tutoring
                : ['super_admin', 'admin', 'tutoring_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessTeachingTab = typeof configuredCapabilities.teaching === 'boolean'
                ? configuredCapabilities.teaching
                : ['super_admin', 'admin', 'teaching_admin', 'teacher'].some((role) => configuredRoleNames.includes(role))
            const canAccessMaterialsTab = ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((role) => configuredRoleNames.includes(role))
            const canAccessGroupsTab = ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((role) => configuredRoleNames.includes(role))
            const canAccessRestaurantTab = ['super_admin', 'admin', 'lunch_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessProfileTab = typeof configuredCapabilities.profile === 'boolean'
                ? configuredCapabilities.profile
                : ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin', 'aba_teacher'].some((role) => configuredRoleNames.includes(role))
            const keys = this.availableTabKeys(
                canAccessSuperAdminTab,
                canAccessAdminTab,
                canAccessRegisterTab,
                canAccessTutoringTab,
                canAccessTeachingTab,
                canAccessMaterialsTab,
                canAccessGroupsTab,
                canAccessRestaurantTab,
                canAccessProfileTab,
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
            const canAccessRegisterTab = ['super_admin', 'admin', 'register_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessTutoringTab = typeof configuredCapabilities.tutoring === 'boolean'
                ? configuredCapabilities.tutoring
                : ['super_admin', 'admin', 'tutoring_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessTeachingTab = typeof configuredCapabilities.teaching === 'boolean'
                ? configuredCapabilities.teaching
                : ['super_admin', 'admin', 'teaching_admin', 'teacher'].some((role) => configuredRoleNames.includes(role))
            const canAccessMaterialsTab = ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((role) => configuredRoleNames.includes(role))
            const canAccessGroupsTab = ['super_admin', 'admin', 'materials_admin', 'materials_moderator'].some((role) => configuredRoleNames.includes(role))
            const canAccessRestaurantTab = ['super_admin', 'admin', 'lunch_admin'].some((role) => configuredRoleNames.includes(role))
            const canAccessProfileTab = typeof configuredCapabilities.profile === 'boolean'
                ? configuredCapabilities.profile
                : ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin', 'aba_teacher'].some((role) => configuredRoleNames.includes(role))
            const availableTabs = this.availableTabKeys(
                canAccessSuperAdminTab,
                canAccessAdminTab,
                canAccessRegisterTab,
                canAccessTutoringTab,
                canAccessTeachingTab,
                canAccessMaterialsTab,
                canAccessGroupsTab,
                canAccessRestaurantTab,
                canAccessProfileTab,
            )
            const resolvedTab = availableTabs.includes(tab)
                ? tab
                : availableTabs[0]
            let keys, fallback
            if (resolvedTab === 'admin') {
                keys = ['schoolyears', 'users', 'school_groups', 'log']
                fallback = 'schoolyears'
            } else if (resolvedTab === 'tutoring') {
                keys = ['tutoring_settings', 'tutoring_subjects', 'tutoring_users']
                fallback = 'tutoring_settings'
            } else if (resolvedTab === 'materials') {
                keys = ['material_settings', 'material_groups']
                fallback = 'material_settings'
            } else if (resolvedTab === 'register') {
                keys = ['users']
                fallback = 'users'
            } else if (resolvedTab === 'teaching') {
                keys = ['teachers', 'teaching_admin']
                fallback = 'teachers'
            } else if (resolvedTab === 'groups') {
                keys = ['groups_overview', 'groups_own']
                fallback = 'groups_overview'
            } else if (resolvedTab === 'restaurant') {
                keys = ['general', 'categories', 'ingredient-icons', 'free-days', 'eating-times', 'online']
                fallback = 'general'
            } else {
                keys = ['general', 'schools', 'licence_models', 'roles', 'school_switch', 'user_impersonation']
                fallback = 'general'
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

.settings-restaurant-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-teaching-admin-wrap {
    width: 100%;
    max-width: 100%;
}

.settings-teachers-wrap {
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

.settings-teacher-subnav__button {
    text-transform: none !important;
    letter-spacing: 0 !important;
    font-size: 0.78rem !important;
    padding: 0 12px !important;
}

.settings-groups-wrap {
    width: 100%;
}

.settings-materials-wrap {
    width: 520px;
    max-width: 100%;
    margin: 0;
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
