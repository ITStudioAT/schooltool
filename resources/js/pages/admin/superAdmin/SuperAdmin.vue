<template>
    <div class="super-admin-page" :class="{ 'is-overview': usesOverviewTheme }" v-if="canAccessSuperAdminPage">
        <div class="super-admin-bg" v-if="usesOverviewTheme">
            <div class="super-admin-bg-image"></div>
            <div class="super-admin-bg-glow super-admin-bg-glow-left"></div>
            <div class="super-admin-bg-glow super-admin-bg-glow-right"></div>
        </div>

        <v-container fluid class="ma-0 w-100 pa-2 super-admin-page-inner">
            <header class="super-admin-header" v-if="isOverviewPage">
                <div class="super-admin-brand">
                    <div class="super-admin-brand-badge">
                        <v-icon size="20" color="white">mdi-shield-crown</v-icon>
                    </div>
                    <div>
                        <div class="super-admin-brand-eyebrow">Admin Dashboard</div>
                        <h1 class="super-admin-brand-title">Super-Admin</h1>
                        <p class="super-admin-brand-subtitle">
                            Verwaltung von Schulen, Schuljahren, Lizenzen und Benutzern in einer Oberfläche.
                        </p>
                    </div>
                </div>

                <div class="super-admin-header-meta">
                    <div class="super-admin-meta-pill" v-if="config?.selected_school?.long_name || config?.selected_school?.short_name">
                        <span>Schule</span>
                        <strong>{{ config?.selected_school?.long_name || config?.selected_school?.short_name }}</strong>
                    </div>
                    <div class="super-admin-meta-pill" v-if="config?.version">
                        <span>Version</span>
                        <strong>{{ config.version }}</strong>
                    </div>
                    <div class="super-admin-meta-pill" v-if="isImpersonating">
                        <span>Status</span>
                        <strong>Übernahme aktiv</strong>
                    </div>
                </div>
            </header>

            <!-- Menüleiste oben -->
            <v-card
                tile
                flat
                color="transparent"
                class="d-flex flex-row ga-2 w-100 mb-2 super-admin-menu-row"
                :class="{ 'super-admin-menu-row--overview': usesOverviewTheme, 'is-disabled': action != '' }"
                :disabled="action != ''">
            <its-menu-button
                subtitle="Übersicht"
                icon="mdi-home"
                :color="main_action == '' ? 'primary' : 'secondary'"
                @click="main_action = ''"
                v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />

            <its-menu-button
                subtitle="Schulen"
                icon="mdi-school"
                :color="main_action == 'schools' ? 'primary' : 'secondary'"
                @click="main_action = 'schools'"
                v-if="['super_admin'].some((role) => config.roles.includes(role))" />

            <its-menu-button
                subtitle="Schuljahre"
                icon="mdi-calendar-multiple"
                :color="main_action == 'schoolyears' ? 'primary' : 'secondary'"
                @click="main_action = 'schoolyears'"
                v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />

            <its-menu-button
                subtitle="Lizenzen"
                icon="mdi-card-account-details"
                :color="main_action == 'licences' ? 'primary' : 'secondary'"
                @click="openLicencesOverview"
                v-if="['super_admin'].some((role) => config.roles.includes(role))" />
            <its-menu-button
                subtitle="Rollen"
                icon="mdi-badge-account-horizontal-outline"
                :color="main_action == 'roles' ? 'primary' : 'secondary'"
                @click="main_action = 'roles'"
                v-if="['super_admin'].some((role) => config.roles.includes(role))" />
            <its-menu-button
                subtitle="Benutzer"
                icon="mdi-account-multiple"
                :color="main_action == 'users' ? 'primary' : 'secondary'"
                @click="main_action = 'users'"
                v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
            <its-menu-button
                subtitle="Benutzer wechseln"
                icon="mdi-account-switch"
                color="secondary"
                @click="openImpersonationDialog"
                v-if="config.roles.includes('super_admin') && !isImpersonating" />
            <its-menu-button
                subtitle="Lehrer"
                icon="mdi-account-tie"
                :color="main_action == 'teachers_overview' ? 'primary' : 'secondary'"
                @click="main_action = 'teachers_overview'"
                v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
            <its-menu-button
                subtitle="Log"
                icon="mdi-file-document"
                color="secondary"
                @click="log_dialog = true"
                v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
            <its-menu-button subtitle="Horizon" icon="mdi-horizontal-rotate-clockwise" color="secondary" @click="moveToHorizon" />
            </v-card>
            <v-card
                tile
                flat
                color="transparent"
                class="d-flex flex-row flex-wrap ga-2 w-100 mb-2 super-admin-menu-row"
                :class="{ 'super-admin-menu-row--overview': usesOverviewTheme, 'is-disabled': action != '' }"
                :disabled="action != ''"
                v-if="main_action == 'licences' && ['super_admin'].some((role) => config.roles.includes(role))">
            <its-menu-button
                subtitle="Alle Lizenzen"
                icon="mdi-home"
                :color="licences_action == 'overview' ? 'primary' : 'secondary'"
                @click="licences_action = 'overview'" />
            <its-menu-button
                subtitle="Lizenzvergaben"
                icon="mdi-card-account-details-outline"
                :color="licences_action == 'schools' ? 'primary' : 'secondary'"
                @click="licences_action = 'schools'" />
            </v-card>

            <div class="super-admin-overview-shell" :class="{ 'super-admin-overview-shell--active': usesOverviewTheme }">
                <v-row class="w-100 ma-0" dense>
                    <ActiveSchool v-if="main_action == '' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
                    <Schools v-if="main_action == 'schools' && ['super_admin'].some((role) => config.roles.includes(role))" />
                    <Schoolyears v-if="main_action == 'schoolyears' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
                    <Licences v-if="main_action == 'licences' && licences_action == 'overview' && ['super_admin'].some((role) => config.roles.includes(role))" />
                    <LicenceSchools v-if="main_action == 'licences' && licences_action == 'schools' && ['super_admin'].some((role) => config.roles.includes(role))" />
                    <Roles v-if="main_action == 'roles' && ['super_admin'].some((role) => config.roles.includes(role))" />
                    <Users v-if="main_action == 'users' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
                    <TeacherOverview v-if="main_action == 'teachers_overview' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
                    <Teachers v-if="main_action == 'teachers' && (config.roles.includes('super_admin') || config.roles.includes('admin'))" />
                    <TeachersList v-if="main_action == 'teachers_list' && (config.roles.includes('super_admin') || config.roles.includes('admin'))" />
                </v-row>
            </div>
        </v-container>

        <Log v-model="log_dialog" v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />

        <v-dialog v-model="impersonation_dialog" max-width="720">
            <v-card>
                <v-card-title>Benutzer wechseln</v-card-title>
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
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Schools from './components/Schools.vue'
import Schoolyears from './components/Schoolyears.vue'
import Licences from './components/Licences.vue'
import LicenceSchools from './components/LicenceSchools.vue'
import Roles from './components/Roles.vue'
import Users from './components/Users.vue'
import TeacherOverview from './components/TeacherOverview.vue'
import Teachers from './components/Teachers.vue'
import TeachersList from './components/TeachersList.vue'

import ActiveSchool from './components/ActiveSchool.vue'

import Log from './components/Log.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Schools, Schoolyears, ActiveSchool, Licences, LicenceSchools, Roles, Users, TeacherOverview, Log, Teachers, TeachersList },

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
        isOverviewPage() {
            return this.main_action == ''
        },
        usesOverviewTheme() {
            return this.main_action == '' || this.main_action == 'schools' || this.main_action == 'schoolyears'
        },
        canAccessSuperAdminPage() {
            const roles = this.config?.roles || []
            return ['super_admin', 'admin'].some((role) => roles.includes(role)) || this.isImpersonating
        },
        isImpersonating() {
            return !!this.config?.impersonation?.is_impersonating
        },
        selectedImpersonationUserId() {
            return Array.isArray(this.selected_impersonation_users) && this.selected_impersonation_users.length >= 1
                ? this.selected_impersonation_users[0]
                : null
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
        openLicencesOverview() {
            this.main_action = 'licences'
            this.licences_action = 'overview'
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
            if (!(await this.adminStore.startImpersonation(this.selectedImpersonationUserId))) return
            this.closeImpersonationDialog()
            this.action = ''
            this.main_action = ''
        },
        moveToHorizon() {
            window.open('/horizon', '_blank')
        },
    },
}
</script>

<style scoped src="../../../../css/admin-superadmin-overview-shell.css"></style>
