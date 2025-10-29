<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != ''">
            <its-menu-button
                subtitle="Schulen"
                icon="mdi-school"
                :color="main_action == 'schools' ? 'primary' : 'secondary'"
                @click="main_action == 'schools' ? (main_action = '') : (main_action = 'schools')"
                v-if="config.roles.includes('super_admin')" />

            <its-menu-button
                subtitle="Lizenzen"
                icon="mdi-card-account-details"
                :color="main_action == 'licences' ? 'primary' : 'secondary'"
                @click="main_action == 'licences' ? (main_action = '') : (main_action = 'licences')" />
        </v-card>
        <v-row class="w-100" dense>
            <ActiveSchool v-if="main_action == '' && config.roles.includes('super_admin')" />
            <Schools v-if="main_action == 'schools' && config.roles.includes('super_admin')" />
            <Licences v-if="main_action == 'licences' && config.roles.includes('super_admin')"></Licences>
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Schools from './components/Schools.vue'
import Licences from './components/Licences.vue'
import ActiveSchool from './components/ActiveSchool.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Schools, ActiveSchool, Licences },

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
        ...mapWritableState(useAdminStore, ['config', 'action']),
    },

    methods: {},
}
</script>
