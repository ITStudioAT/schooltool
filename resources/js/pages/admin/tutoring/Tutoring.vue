<template>
    <div class="super-admin-page is-overview tutoring-admin-page">
        <div class="super-admin-bg">
            <div class="super-admin-bg-image"></div>
            <div class="super-admin-bg-glow super-admin-bg-glow-left"></div>
            <div class="super-admin-bg-glow super-admin-bg-glow-right"></div>
        </div>

        <v-container fluid class="ma-0 w-100 pa-2 super-admin-page-inner">
            <header class="super-admin-header tutoring-admin-header">
                <div class="super-admin-brand">
                    <div class="super-admin-brand-badge tutoring-admin-brand-badge">
                        <v-icon size="20" color="white">mdi-account-school</v-icon>
                    </div>
                    <div>
                        <div class="super-admin-brand-eyebrow">Admin Dashboard</div>
                        <h1 class="super-admin-brand-title">Nachhilfe-Admin</h1>
                        <p class="super-admin-brand-subtitle">
                            Verwaltung von Einstellungen, Fächern und Benutzer:innen im Nachhilfe-Tool.
                        </p>
                    </div>
                </div>
            </header>

            <!-- Menüleiste oben -->
            <div class="tutoring-admin-menu-wrap mb-2">
                <v-card
                    tile
                    flat
                    color="transparent"
                    class="d-flex flex-row ga-2 w-100 super-admin-menu-row super-admin-menu-row--overview tutoring-admin-menu-row"
                    :class="{ 'is-disabled': action != '' || action_2 != '' }"
                    :disabled="action != '' || action_2 != ''">
                    <its-menu-button
                        subtitle="Übersicht"
                        icon="mdi-home"
                        :color="main_action == 'overview' ? 'primary' : 'secondary'"
                        class="tutoring-admin-menu-button"
                        @click="selectMainAction('overview')" />
                    <its-menu-button
                        subtitle="Einstellungen"
                        icon="mdi-cog"
                        :color="main_action == 'settings' ? 'primary' : 'secondary'"
                        class="tutoring-admin-menu-button"
                        @click="selectMainAction('settings')"
                        v-if="config.roles.some((item) => admins.includes(item))" />
                    <its-menu-button
                        subtitle="Fächer"
                        icon="mdi-television-shimmer"
                        :color="main_action == 'subjects' ? 'primary' : 'secondary'"
                        class="tutoring-admin-menu-button"
                        @click="selectMainAction('subjects')"
                        v-if="config.roles.some((item) => admins.includes(item))" />
                    <its-menu-button
                        subtitle="Benutzer"
                        icon="mdi-account-multiple"
                        :color="main_action == 'users' ? 'primary' : 'secondary'"
                        class="tutoring-admin-menu-button"
                        @click="selectMainAction('users')"
                        v-if="config.roles.some((item) => admins.includes(item))" />
                </v-card>
            </div>

            <div class="super-admin-overview-shell super-admin-overview-shell--active tutoring-admin-shell">
                <v-row class="w-100 ma-0" dense>
                    <Overview v-if="main_action == 'overview'" />
                    <Settings v-if="main_action == 'settings'" />
                    <Subjects v-if="main_action == 'subjects'" />
                    <Users v-if="main_action == 'users'" />
                </v-row>
            </div>
        </v-container>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

import Overview from './components/Overview.vue'
import Settings from './components/Settings.vue'
import Subjects from './components/Subjects.vue'
import Users from './components/Users.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Settings, Subjects, Users, Overview },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.action = ''
        this.action_2 = ''
    },

    data() {
        return {
            adminStore: null,
            main_action: 'overview',
            admins: ['super_admin', 'admin', 'tutoring_admin'],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'action_2', 'is_loading']),
    },

    methods: {
        selectMainAction(action) {
            this.main_action = action
        },
    },
}
</script>

<style scoped src="../../../../css/admin-superadmin-overview-shell.css"></style>
<style scoped src="../../../../css/admin-tutoring-overview-shell.css"></style>
