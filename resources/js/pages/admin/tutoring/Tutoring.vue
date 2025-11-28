<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != '' || action_2 != ''">
            <its-menu-button subtitle="Übersicht" icon="mdi-home" :color="main_action == '' ? 'primary' : 'secondary'" @click="main_action = ''" />
            <its-menu-button subtitle="Einstellungen" icon="mdi-cog" :color="main_action == 'settings' ? 'primary' : 'secondary'" @click="main_action = 'settings'" />
            <its-menu-button subtitle="Fächer" icon="mdi-television-shimmer" :color="main_action == 'subjects' ? 'primary' : 'secondary'" @click="main_action = 'subjects'" />
        </v-card>
        <v-row class="w-100" dense>
            <Settings v-if="main_action == 'settings'" />
            <Subjects v-if="main_action == 'subjects'" />
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

import Settings from './components/Settings.vue'
import Subjects from './components/Subjects.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Settings, Subjects },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            main_action: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2']),
    },

    methods: {},
}
</script>
