<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != '' || action_2 != ''">
            <its-menu-button subtitle="Übersicht" icon="mdi-home" :color="main_action == 'overview' ? 'primary' : 'secondary'" @click="main_action = 'overview'" />
            <its-menu-button
                subtitle="Einstellungen"
                icon="mdi-cog"
                :color="main_action == 'settings' ? 'primary' : 'secondary'"
                @click="main_action = 'settings'"
                v-if="config.roles.some((item) => admins.includes(item))" />
            <its-menu-button
                subtitle="Fächer"
                icon="mdi-television-shimmer"
                :color="main_action == 'subjects' ? 'primary' : 'secondary'"
                @click="main_action = 'subjects'"
                v-if="config.roles.some((item) => admins.includes(item))" />
            <its-menu-button
                subtitle="Benutzer"
                icon="mdi-account-multiple"
                :color="main_action == 'users' ? 'primary' : 'secondary'"
                @click="main_action = 'users'"
                v-if="config.roles.some((item) => admins.includes(item))" />
            <its-menu-button
                subtitle="Angebote"
                icon="mdi-account-multiple"
                :color="main_action == 'offers' ? 'primary' : 'secondary'"
                @click="main_action = 'offers'"
                v-if="config.roles.some((item) => admins.includes(item))" />
        </v-card>
        <v-row class="w-100" dense>
            <Overview v-if="main_action == 'overview'" />
            <Settings v-if="main_action == 'settings'" />
            <Subjects v-if="main_action == 'subjects'" />
            <Users v-if="main_action == 'users'" />
            <Offers v-if="main_action == 'offers'" />
        </v-row>
    </v-container>
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
import Offers from './components/Offers.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Settings, Subjects, Users, Offers, Overview },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            main_action: 'overview',
            admins: ['super_admin', 'admin', 'tutoring_admin'],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'action_2']),
    },

    methods: {},
}
</script>
