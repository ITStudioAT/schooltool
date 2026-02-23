<template>
    <div class="super-admin-page" :class="{ 'is-overview': isOverviewPage }" v-if="canAccessSuperAdminPage">
        <div class="super-admin-bg" v-if="isOverviewPage">
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
                :class="{ 'super-admin-menu-row--overview': isOverviewPage, 'is-disabled': action != '' }"
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
                icon="mdi-school"
                :color="main_action == 'teachers_overview' ? 'primary' : 'secondary'"
                @click="main_action = 'teachers_overview'"
                v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
            <its-menu-button
                subtitle="Log"
                icon="mdi-file-document"
                :color="main_action == 'log' ? 'primary' : 'secondary'"
                @click="main_action = 'log'"
                v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
            <its-menu-button subtitle="Horizon" icon="mdi-horizontal-rotate-clockwise" color="secondary" @click="moveToHorizon" />
            </v-card>
            <v-card
                tile
                flat
                color="transparent"
                class="d-flex flex-row flex-wrap ga-2 w-100 mb-2 super-admin-menu-row"
                :class="{ 'super-admin-menu-row--overview': isOverviewPage, 'is-disabled': action != '' }"
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

            <div class="super-admin-overview-shell" :class="{ 'super-admin-overview-shell--active': isOverviewPage }">
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
                    <Log v-if="main_action == 'log' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
                </v-row>
            </div>
        </v-container>

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

<style scoped>
.super-admin-page {
    position: relative;
    min-height: 100%;
}

.super-admin-page.is-overview {
    background: #101d2a;
}

.super-admin-bg {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}

.super-admin-bg-image {
    position: absolute;
    inset: 0;
    opacity: 1;
    background: #101d2a;
}

.super-admin-bg-glow {
    display: none;
}

.super-admin-bg-glow-left {
    width: 360px;
    height: 360px;
    left: -80px;
    top: 180px;
    background: radial-gradient(circle, rgba(255, 198, 124, 0.65), rgba(255, 198, 124, 0));
}

.super-admin-bg-glow-right {
    width: 420px;
    height: 420px;
    right: -120px;
    top: 120px;
    background: radial-gradient(circle, rgba(88, 143, 194, 0.55), rgba(88, 143, 194, 0));
}

.super-admin-page-inner {
    position: relative;
    z-index: 1;
}

.super-admin-page.is-overview .super-admin-page-inner {
    max-width: 1160px;
    margin: 0 auto;
}

.super-admin-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
    border-radius: 20px;
    padding: 14px 16px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.68));
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(10px);
}

.super-admin-brand {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
}

.super-admin-brand-badge {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    background: linear-gradient(180deg, #f68a2e, #e16f16);
    box-shadow: 0 8px 18px rgba(208, 98, 18, 0.28);
}

.super-admin-brand-eyebrow {
    color: rgba(16, 38, 58, 0.88);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.super-admin-brand-title {
    margin: 0;
    color: #112536;
    font-size: 1.25rem;
    line-height: 1.05;
    letter-spacing: 0.01em;
}

.super-admin-brand-subtitle {
    margin: 8px 0 0;
    color: rgba(18, 40, 60, 0.9);
    font-size: 0.88rem;
    line-height: 1.35;
    max-width: 62ch;
}

.super-admin-header-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.super-admin-meta-pill {
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

.super-admin-meta-pill span {
    font-size: 0.75rem;
    opacity: 0.9;
}

.super-admin-meta-pill strong {
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 280px;
}

.super-admin-menu-row {
    flex-wrap: wrap;
}

.super-admin-menu-row--overview {
    border-radius: 18px;
    padding: 10px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.68));
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(10px);
}

.super-admin-menu-row.is-disabled {
    opacity: 0.7;
}

.super-admin-menu-row--overview :deep(.v-card) {
    border-radius: 16px !important;
    border: 1px solid rgba(16, 38, 58, 0.08);
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(10px);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.super-admin-menu-row--overview :deep(.v-card.bg-secondary) {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.68)) !important;
    color: #163146 !important;
}

.super-admin-menu-row--overview :deep(.v-card.bg-primary) {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8)) !important;
    border-color: rgba(57, 73, 171, 0.28) !important;
    color: #2f41a8 !important;
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.75), 0 0 0 2px rgba(57, 73, 171, 0.06);
}

.super-admin-menu-row--overview :deep(.v-card:hover) {
    transform: translateY(-1px);
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.65), 0 8px 18px rgba(16, 38, 58, 0.08);
}

.super-admin-menu-row--overview :deep(.v-card .v-icon) {
    opacity: 0.95;
}

.super-admin-menu-row--overview :deep(.v-card.bg-primary .v-icon),
.super-admin-menu-row--overview :deep(.v-card.bg-primary .text-caption),
.super-admin-menu-row--overview :deep(.v-card.bg-primary .text-body-2) {
    color: #2f41a8 !important;
}

.super-admin-overview-shell--active {
    padding: 0;
}

@media (max-width: 960px) {
    .super-admin-header {
        flex-direction: column;
        align-items: stretch;
    }

    .super-admin-header-meta {
        justify-content: flex-start;
    }
}

@media (max-width: 640px) {
    .super-admin-header {
        border-radius: 16px;
        padding: 12px;
    }

    .super-admin-menu-row--overview :deep(.v-card) {
        width: 132px !important;
        height: 64px !important;
    }
}
</style>
