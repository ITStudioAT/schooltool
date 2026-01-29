<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != '' || action_2 != ''">
            <its-menu-button subtitle="Übersicht" icon="mdi-home" :color="main_action == 'overview' ? 'primary' : 'secondary'" @click="main_action = 'overview'" />
            <its-menu-button
                subtitle="Admin"
                icon="mdi-shield-crown-outline"
                :color="main_action == 'admin' ? 'primary' : 'secondary'"
                @click="main_action = 'admin'"
                v-if="config.roles.some((item) => ['super_admin', 'admin', 'teaching_admin'].includes(item))" />
            <its-menu-button
                subtitle="Einstellungen"
                icon="mdi-cog"
                :color="main_action == 'settings' ? 'primary' : 'secondary'"
                @click="main_action = 'settings'"
                v-if="config.roles.some((item) => ['super_admin', 'admin', 'teaching_admin', 'teacher'].includes(item))" />
            <its-menu-button
                subtitle="Suche"
                icon="mdi-magnify"
                :color="main_action == 'search' ? 'primary' : 'secondary'"
                @click="main_action = 'search'"
                v-if="config.roles.some((item) => ['super_admin', 'admin', 'teaching_admin', 'teacher'].includes(item))" />

            <its-menu-button
                :title="config.selected_schoolyear?.name"
                subtitle="Schuljahr"
                icon="mdi-calendar"
                :color="main_action == 'schoolyear' ? 'primary' : 'secondary'"
                @click="main_action = 'schoolyear'"
                v-if="config.roles.some((item) => ['super_admin', 'admin', 'teaching_admin', 'teacher'].includes(item))" />
        </v-card>
        <v-row class="w-100" dense>
            <Overview v-if="main_action == 'overview'" />
            <Settings v-if="main_action == 'settings'" />
            <Admin v-if="main_action == 'admin'" />
            <Search v-if="main_action == 'search'" />
            <Schoolyear v-if="main_action == 'schoolyear'" />
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

import Overview from './overview/Overview.vue'
import Settings from './settings/Settings.vue'
import Admin from './admin/Admin.vue'
import Search from './search/Search.vue'
import Schoolyear from './schoolyear/Schoolyear.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Overview, Settings, Admin, Search, Schoolyear },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            main_action: 'overview',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'action_2']),
    },

    methods: {},
}
</script>
