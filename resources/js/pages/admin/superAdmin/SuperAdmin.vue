<template>
    <div class="super-admin-page" :class="{ 'is-overview': usesOverviewTheme }" v-if="canAccessSuperAdminPage">
        <div class="super-admin-bg" v-if="usesOverviewTheme">
            <div class="super-admin-bg-image"></div>
            <div class="super-admin-bg-glow super-admin-bg-glow-left"></div>
            <div class="super-admin-bg-glow super-admin-bg-glow-right"></div>
        </div>

        <v-container fluid class="ma-0 w-100 pa-2 super-admin-page-inner">
            <AdminSectionHero
                v-if="shouldShowHeader"
                class="mb-3"
                eyebrow="Admin Dashboard"
                title="Super-Admin"
                :active-section="activeSection"
                :chips="headerChips"
                :show-current-user-chip="true" />

            <v-sheet rounded="xl" class="super-admin-nav mb-2" :class="{ 'is-locked': isNavigationLocked }">
                <div class="super-admin-nav__buttons">
                    <v-btn
                        v-for="item in visibleNavigationItems"
                        :key="item.key"
                        rounded="xl"
                        :color="isNavigationItemActive(item) ? 'primary' : 'secondary'"
                        :variant="isNavigationItemActive(item) ? 'flat' : 'tonal'"
                        class="super-admin-nav__button"
                        :disabled="isNavigationLocked"
                        @click="handleNavigation(item)">
                        <v-icon size="18" :icon="item.icon" class="mr-2" />
                        <span class="super-admin-nav__button-copy">
                            <span class="super-admin-nav__button-title">{{ item.label }}</span>
                            <span class="super-admin-nav__button-meta">{{ item.meta }}</span>
                        </span>
                    </v-btn>
                </div>
            </v-sheet>

            <v-sheet
                v-if="main_action == 'licences' && ['super_admin'].some((role) => config.roles.includes(role))"
                rounded="xl"
                class="super-admin-subnav mb-2"
                :class="{ 'is-locked': isNavigationLocked }">
                <v-btn-toggle v-model="licences_action" mandatory class="super-admin-subnav__switcher" color="primary" divided :disabled="isNavigationLocked">
                    <v-btn
                        v-for="item in visibleLicenceNavigationItems"
                        :key="item.key"
                        :value="item.key"
                        class="super-admin-subnav__button"
                        :prepend-icon="item.icon">
                        {{ item.label }}
                    </v-btn>
                </v-btn-toggle>
            </v-sheet>

            <v-sheet
                v-if="['teachers', 'teachers_list'].includes(main_action) && ['super_admin', 'admin'].some((role) => config.roles.includes(role))"
                rounded="xl"
                class="super-admin-subnav mb-2"
                :class="{ 'is-locked': isNavigationLocked }">
                <v-btn-toggle v-model="main_action" mandatory class="super-admin-subnav__switcher" color="primary" divided :disabled="isNavigationLocked">
                    <v-btn
                        v-for="item in visibleTeacherNavigationItems"
                        :key="item.key"
                        :value="item.key"
                        class="super-admin-subnav__button"
                        :prepend-icon="item.icon">
                        {{ item.label }}
                    </v-btn>
                </v-btn-toggle>
            </v-sheet>

            <div class="super-admin-overview-shell" :class="{ 'super-admin-overview-shell--active': usesOverviewTheme }">
                <v-row class="w-100 ma-0" dense>
                    <ActiveSchool v-if="main_action == '' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
                    <Schools v-if="main_action == 'schools' && ['super_admin'].some((role) => config.roles.includes(role))" />
                    <Schoolyears v-if="main_action == 'schoolyears' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
                    <Licences v-if="main_action == 'licences' && licences_action == 'overview' && ['super_admin'].some((role) => config.roles.includes(role))" />
                    <LicenceSchools v-if="main_action == 'licences' && licences_action == 'schools' && ['super_admin'].some((role) => config.roles.includes(role))" />
                    <Roles v-if="main_action == 'roles' && ['super_admin'].some((role) => config.roles.includes(role))" />
                    <Users v-if="main_action == 'users' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
                    <Teachers v-if="main_action == 'teachers' && (config.roles.includes('super_admin') || config.roles.includes('admin'))" />
                    <TeachersList v-if="main_action == 'teachers_list' && (config.roles.includes('super_admin') || config.roles.includes('admin'))" />
                </v-row>
            </div>
        </v-container>

        <Log v-model="log_dialog" v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />

        <v-dialog v-model="impersonation_dialog" max-width="720" persistent>
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Benutzer wechseln</span>
                    <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeImpersonationDialog" />
                </v-card-title>
                <v-card-text>
                    <v-select
                        class="mb-2"
                        v-model="selected_impersonation_school_id"
                        :items="impersonatable_schools"
                        item-title="display_name"
                        item-value="id"
                        label="Schule auswählen"
                        no-data-text="Keine Schulen gefunden"
                        @update:model-value="onImpersonationSchoolChange" />

                    <v-form @submit.prevent="searchImpersonationUsers">
                        <div class="d-flex flex-row align-start">
                            <v-text-field
                                clearable
                                v-model="impersonation_user_search_string"
                                label="Benutzer suchen"
                                :disabled="!selected_impersonation_school_id"
                                @click:clear="searchImpersonationUsers" />
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-magnify"
                                type="submit"
                                :disabled="!selected_impersonation_school_id"
                                @click="searchImpersonationUsers" />
                        </div>
                    </v-form>

                    <v-list
                        dense
                        variant="elevated"
                        select-strategy="leaf"
                        class="mt-2"
                        v-model:selected="selected_impersonation_users"
                        @update:selected="onSelectedImpersonationUsersUpdate"
                        color="success-lighten-2"
                        v-if="impersonatable_users.length >= 1">
                        <v-list-item v-for="item in impersonatable_users" :key="`impersonation-user-${item.id}`" :value="item.id">
                            <template #title>
                                <div class="d-flex flex-column ga-1 py-1">
                                    <div class="text-body-1">{{ item.last_name }} {{ item.first_name }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ item.school_name || '-' }}</div>
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                    <div class="text-caption text-medium-emphasis mt-2 mb-3" v-else>Keine passenden Benutzer gefunden.</div>

                    <v-card tile flat color="transparent" v-if="impersonatable_users_meta && impersonatable_users_meta.total >= 1">
                        <div class="text-caption d-flex flex-row align-center justify-space-between">
                            <div>{{ impersonationMetaInfoText }}</div>
                            <div>{{ impersonationMetaPageText }}</div>
                        </div>
                        <div class="d-flex flex-row align-center justify-space-between">
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-page-first"
                                @click="firstImpersonationUsersPage"
                                :disabled="impersonatable_users_meta.current_page <= 1" />
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-page-previous-outline"
                                @click="prevImpersonationUsersPage"
                                :disabled="impersonatable_users_meta.current_page <= 1" />
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-page-next-outline"
                                @click="nextImpersonationUsersPage"
                                :disabled="impersonatable_users_meta.current_page >= impersonatable_users_meta.last_page" />
                            <v-btn
                                flat
                                tile
                                class="mt-1 ml-2"
                                color="primary"
                                variant="outlined"
                                icon="mdi-page-last"
                                @click="lastImpersonationUsersPage"
                                :disabled="impersonatable_users_meta.current_page >= impersonatable_users_meta.last_page" />
                        </div>
                    </v-card>
                </v-card-text>
                <v-card-actions class="d-flex justify-space-between">
                    <v-btn color="warning" variant="flat" @click="closeImpersonationDialog">Abbrechen</v-btn>
                    <v-btn color="success" variant="flat" :disabled="!selected_impersonation_school_id || !selectedImpersonationUserId" @click="startImpersonation">Wechseln</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import Schools from './components/Schools.vue'
import Schoolyears from './components/Schoolyears.vue'
import Licences from './components/Licences.vue'
import LicenceSchools from './components/LicenceSchools.vue'
import Roles from './components/Roles.vue'
import Users from './components/Users.vue'
import Teachers from './components/Teachers.vue'
import TeachersList from './components/TeachersList.vue'

import ActiveSchool from './components/ActiveSchool.vue'

import Log from './components/Log.vue'

export default {
    components: { AdminSectionHero, Schools, Schoolyears, ActiveSchool, Licences, LicenceSchools, Roles, Users, Log, Teachers, TeachersList },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.main_action = ''
        this.action = ''
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            licences_action: 'overview',
            log_dialog: false,
            impersonation_dialog: false,
            selected_impersonation_school_id: null,
            impersonation_user_search_string: '',
            selected_impersonation_users: [],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'main_action', 'impersonatable_schools', 'impersonatable_users', 'impersonatable_users_meta']),
        isNavigationLocked() {
            return this.action != ''
        },
        isOverviewPage() {
            return this.main_action == ''
        },
        shouldShowHeader() {
            return ['', 'schools', 'schoolyears', 'licences', 'roles', 'users', 'teachers', 'teachers_list'].includes(this.main_action)
        },
        usesOverviewTheme() {
            return ['', 'schools', 'schoolyears', 'licences', 'roles', 'users', 'teachers', 'teachers_list'].includes(this.main_action)
        },
        canAccessSuperAdminPage() {
            const roles = this.config?.roles || []
            return ['super_admin', 'admin'].some((role) => roles.includes(role)) || this.isImpersonating
        },
        isImpersonating() {
            return !!this.config?.impersonation?.is_impersonating
        },
        activeSection() {
            const map = {
                '': { icon: 'mdi-home', label: 'Übersicht', note: 'Dashboard & aktive Schule' },
                schools: { icon: 'mdi-school', label: 'Schulen', note: 'Schulverwaltung' },
                schoolyears: { icon: 'mdi-calendar-multiple', label: 'Schuljahre', note: 'Schuljahresverwaltung' },
                licences: { icon: 'mdi-card-account-details', label: 'Lizenzen', note: 'Lizenzverwaltung' },
                roles: { icon: 'mdi-badge-account-horizontal-outline', label: 'Rollen', note: 'Rollenverwaltung' },
                users: { icon: 'mdi-account-multiple', label: 'Benutzer', note: 'Benutzerverwaltung' },
                teachers: { icon: 'mdi-account-tie', label: 'Lehrer', note: 'Lehrerverwaltung' },
                teachers_list: { icon: 'mdi-view-list', label: 'Lehrerliste', note: 'Lehrerverwaltung' },
            }
            return map[this.main_action] ?? { icon: 'mdi-dots-horizontal', label: this.main_action, note: '' }
        },
        headerChips() {
            const selectedSchoolName = this.config?.selected_school?.long_name || this.config?.selected_school?.short_name || ''

            return [
                {
                    key: 'school',
                    text: selectedSchoolName,
                    icon: 'mdi-domain',
                },
                {
                    key: 'version',
                    text: this.config?.version || '',
                    icon: 'mdi-tag-outline',
                },
                {
                    key: 'impersonation',
                    text: 'Übernahme aktiv',
                    icon: 'mdi-account-switch',
                    color: 'warning',
                    visible: this.isImpersonating,
                },
            ]
        },
        visibleNavigationItems() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            const hasAnyRole = (requiredRoles) => roles.some((role) => requiredRoles.includes(role))

            return [
                {
                    key: 'overview',
                    label: 'Übersicht',
                    meta: 'Dashboard',
                    icon: 'mdi-home',
                    targetAction: '',
                    visible: hasAnyRole(['super_admin', 'admin']),
                },
                {
                    key: 'schools',
                    label: 'Schulen',
                    meta: 'Verwaltung',
                    icon: 'mdi-school',
                    targetAction: 'schools',
                    visible: hasAnyRole(['super_admin']),
                },
                {
                    key: 'schoolyears',
                    label: 'Schuljahre',
                    meta: 'Kalender',
                    icon: 'mdi-calendar-multiple',
                    targetAction: 'schoolyears',
                    visible: hasAnyRole(['super_admin', 'admin']),
                },
                {
                    key: 'licences',
                    label: 'Lizenzen',
                    meta: 'Modelle',
                    icon: 'mdi-card-account-details',
                    targetAction: 'licences',
                    visible: hasAnyRole(['super_admin']),
                },
                {
                    key: 'roles',
                    label: 'Rollen',
                    meta: 'Rechte',
                    icon: 'mdi-badge-account-horizontal-outline',
                    targetAction: 'roles',
                    visible: hasAnyRole(['super_admin']),
                },
                {
                    key: 'users',
                    label: 'Benutzer',
                    meta: 'Accounts',
                    icon: 'mdi-account-multiple',
                    targetAction: 'users',
                    visible: hasAnyRole(['super_admin', 'admin']),
                },
                {
                    key: 'teachers',
                    label: 'Lehrer',
                    meta: 'Lehrerliste',
                    icon: 'mdi-account-tie',
                    targetAction: 'teachers',
                    visible: hasAnyRole(['super_admin', 'admin']),
                },
                {
                    key: 'impersonation',
                    label: 'Benutzer wechseln',
                    meta: 'Übernahme',
                    icon: 'mdi-account-switch',
                    action: 'impersonation',
                    visible: roles.includes('super_admin') && !this.isImpersonating,
                },
                {
                    key: 'log',
                    label: 'Log',
                    meta: 'System',
                    icon: 'mdi-file-document',
                    action: 'log',
                    visible: hasAnyRole(['super_admin', 'admin']),
                },
                {
                    key: 'horizon',
                    label: 'Horizon',
                    meta: 'Queue',
                    icon: 'mdi-horizontal-rotate-clockwise',
                    action: 'horizon',
                    visible: true,
                },
            ].filter((item) => item.visible)
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
        visibleTeacherNavigationItems() {
            return [
                {
                    key: 'teachers',
                    label: 'Lehrer',
                    meta: 'Verwaltung',
                    icon: 'mdi-account-tie',
                },
                {
                    key: 'teachers_list',
                    label: 'Lehrerliste',
                    meta: 'Verwaltung',
                    icon: 'mdi-view-list',
                },
            ]
        },
        selectedImpersonationUserId() {
            return Array.isArray(this.selected_impersonation_users) && this.selected_impersonation_users.length >= 1
                ? this.selected_impersonation_users[0]
                : null
        },
        selectedImpersonationUser() {
            const selectedId = Number(this.selectedImpersonationUserId || 0)
            if (!selectedId) {
                return null
            }

            return (Array.isArray(this.impersonatable_users) ? this.impersonatable_users : [])
                .find((item) => Number(item?.id || 0) === selectedId) || null
        },
        impersonationMetaInfoText() {
            const meta = this.impersonatable_users_meta || {}
            const from = meta.from || 0
            const to = meta.to || 0
            const total = meta.total || 0
            return `${from} - ${to} von ${total}`
        },
        impersonationMetaPageText() {
            const meta = this.impersonatable_users_meta || {}
            const currentPage = meta.current_page || 1
            const lastPage = meta.last_page || 1
            return `Seite ${currentPage} von ${lastPage}`
        },
    },

    methods: {
        isNavigationItemActive(item) {
            if (item.targetAction === 'teachers') {
                return ['teachers', 'teachers_list'].includes(this.main_action)
            }

            return item.targetAction !== undefined && this.main_action === item.targetAction
        },
        async handleNavigation(item) {
            if (this.isNavigationLocked) {
                return
            }

            if (item.action === 'impersonation') {
                await this.openImpersonationDialog()
                return
            }

            if (item.action === 'log') {
                this.log_dialog = true
                return
            }

            if (item.action === 'horizon') {
                this.moveToHorizon()
                return
            }

            if (item.targetAction === 'licences') {
                this.openLicencesOverview()
                return
            }

            if (item.targetAction === 'teachers') {
                this.openTeachersOverview()
                return
            }

            this.main_action = item.targetAction
        },
        openLicencesOverview() {
            this.main_action = 'licences'
            this.licences_action = 'overview'
        },
        openTeachersOverview() {
            this.main_action = 'teachers'
        },
        async openImpersonationDialog() {
            this.impersonation_dialog = true
            this.impersonation_user_search_string = ''
            this.selected_impersonation_users = []
            this.selected_impersonation_school_id = null

            const schools = await this.adminStore.loadImpersonatableSchools()
            if (!schools || !Array.isArray(schools) || !schools.length) return

            const currentSchoolId = this.config?.selected_school?.id || null
            const schoolExists = schools.some((school) => Number(school.id) === Number(currentSchoolId))
            this.selected_impersonation_school_id = schoolExists ? currentSchoolId : schools[0].id
            await this.adminStore.loadImpersonatableUsers('', this.selected_impersonation_school_id, 1)
        },
        closeImpersonationDialog() {
            this.impersonation_dialog = false
            this.selected_impersonation_school_id = null
            this.impersonation_user_search_string = ''
            this.selected_impersonation_users = []
        },
        async onImpersonationSchoolChange() {
            this.selected_impersonation_users = []
            this.impersonation_user_search_string = ''
            if (!this.selected_impersonation_school_id) {
                this.impersonatable_users = []
                this.impersonatable_users_meta = []
                return
            }
            await this.adminStore.loadImpersonatableUsers('', this.selected_impersonation_school_id, 1)
        },
        async searchImpersonationUsers() {
            if (!this.selected_impersonation_school_id) return
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, 1)
        },
        onSelectedImpersonationUsersUpdate(value) {
            if (!Array.isArray(value)) {
                this.selected_impersonation_users = []
                return
            }
            if (value.length <= 1) {
                this.selected_impersonation_users = value
                return
            }
            this.selected_impersonation_users = [value[value.length - 1]]
        },
        async firstImpersonationUsersPage() {
            if (!this.selected_impersonation_school_id) return
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, 1)
        },
        async prevImpersonationUsersPage() {
            if (!this.selected_impersonation_school_id) return
            const currentPage = Number(this.impersonatable_users_meta?.current_page || 1)
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, Math.max(1, currentPage - 1))
        },
        async nextImpersonationUsersPage() {
            if (!this.selected_impersonation_school_id) return
            const currentPage = Number(this.impersonatable_users_meta?.current_page || 1)
            const lastPage = Number(this.impersonatable_users_meta?.last_page || 1)
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, Math.min(lastPage, currentPage + 1))
        },
        async lastImpersonationUsersPage() {
            if (!this.selected_impersonation_school_id) return
            const lastPage = Number(this.impersonatable_users_meta?.last_page || 1)
            this.selected_impersonation_users = []
            await this.adminStore.loadImpersonatableUsers(this.impersonation_user_search_string || '', this.selected_impersonation_school_id, Math.max(1, lastPage))
        },
        async startImpersonation() {
            if (!this.selectedImpersonationUserId) return
            const selectedUser = this.selectedImpersonationUser
            if (!(await this.adminStore.startImpersonation(this.selectedImpersonationUserId))) return
            const redirectTarget = this.impersonationTargetPath(selectedUser)
            this.closeImpersonationDialog()
            this.action = ''
            this.main_action = ''
            this.redirectAfterImpersonation(redirectTarget)
        },
        adminAccessRoles() {
            return ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'teacher']
        },
        targetCanAccessAdmin(user) {
            const userRoles = Array.isArray(user?.roles) ? user.roles : []
            if (!userRoles.length) {
                return false
            }

            return userRoles.some((role) => this.adminAccessRoles().includes(String(role)))
        },
        impersonationTargetPath(user) {
            if (!user) {
                return '/admin'
            }
            return this.targetCanAccessAdmin(user) ? '/admin' : '/'
        },
        redirectAfterImpersonation(path) {
            window.location.href = path
        },
        moveToHorizon() {
            window.open('/horizon', '_blank')
        },
    },
}
</script>

<style scoped src="../../../../css/admin-superadmin-overview-shell.css"></style>
