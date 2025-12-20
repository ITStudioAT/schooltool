<template>
    <v-container fluid class="ma-0 w-100 pa-2" v-if="config.roles.includes('super_admin')">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != ''">
            <its-menu-button subtitle="Übersicht" icon="mdi-home" :color="main_action == '' ? 'primary' : 'secondary'" @click="main_action = ''" />

            <its-menu-button
                subtitle="Schulen"
                icon="mdi-school"
                :color="main_action == 'schools' ? 'primary' : 'secondary'"
                @click="main_action = 'schools'"
                v-if="config.roles.includes('super_admin')" />

            <its-menu-button subtitle="Lizenzen" icon="mdi-card-account-details" :color="main_action == 'licences' ? 'primary' : 'secondary'" @click="main_action = 'licences'" />
            <its-menu-button subtitle="Benutzer" icon="mdi-account-multiple" :color="main_action == 'users' ? 'primary' : 'secondary'" @click="main_action = 'users'" />
            <its-menu-button subtitle="Log" icon="mdi-file-document" :color="main_action == 'log' ? 'primary' : 'secondary'" @click="main_action = 'log'" />
            <its-menu-button subtitle="Horizon" icon="mdi-horizontal-rotate-clockwise" color="secondary" @click="moveToHorizon" />
        </v-card>
        <v-row class="w-100" dense>
            <ActiveSchool v-if="main_action == '' && config.roles.includes('super_admin')" />
            <Schools v-if="main_action == 'schools' && config.roles.includes('super_admin')" />
            <Licences v-if="main_action == 'licences' && config.roles.includes('super_admin')" />
            <Users v-if="main_action == 'users' && config.roles.includes('super_admin')" />
            <Teachers v-if="main_action == 'teachers' && config.roles.includes('super_admin')" />
            <TeachersList v-if="main_action == 'teachers_list' && config.roles.includes('super_admin')" />
            <Log v-if="main_action == 'log' && config.roles.includes('super_admin')" />
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
import Users from './components/Users.vue'
import Teachers from './components/Teachers.vue'
import TeachersList from './components/TeachersList.vue'

import ActiveSchool from './components/ActiveSchool.vue'

import Log from './components/Log.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Schools, ActiveSchool, Licences, Users, Log, Teachers, TeachersList },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.main_action = ''
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'main_action']),
    },

    methods: {
        moveToHorizon() {
            window.open('/horizon', '_blank')
        },
    },
}
</script>
