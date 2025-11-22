<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != ''">
            <its-menu-button subtitle="Übersicht" icon="mdi-home" :color="main_action == '' ? 'primary' : 'secondary'" @click="main_action = ''" />
            <its-menu-button subtitle="Einstellungen" icon="mdi-home" :color="main_action == 'settings' ? 'primary' : 'secondary'" @click="main_action = 'settings'" />
        </v-card>
        <v-row class="w-100" dense>
            <Settings v-if="main_action == 'settings'" />
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

import Settings from './components/Settings.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Settings },

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
        ...mapWritableState(useAdminStore, ['action']),
    },

    methods: {},
}
</script>
