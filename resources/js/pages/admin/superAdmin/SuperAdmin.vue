<template>
    <v-container fluid class="ma-0 w-100 pa-2" v-if="['super_admin', 'admin'].some((role) => config.roles.includes(role))">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != ''">
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
            class="d-flex flex-row flex-wrap ga-2 w-100 mb-2"
            :disabled="action != ''"
            v-if="main_action == 'licences' && ['super_admin'].some((role) => config.roles.includes(role))">
            <its-menu-button
                subtitle="Überblick"
                icon="mdi-home"
                :color="licences_action == 'overview' ? 'primary' : 'secondary'"
                @click="licences_action = 'overview'" />
        </v-card>
        <v-row class="w-100" dense>
            <ActiveSchool v-if="main_action == '' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
            <Schools v-if="main_action == 'schools' && ['super_admin'].some((role) => config.roles.includes(role))" />
            <Schoolyears v-if="main_action == 'schoolyears' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
            <Licences v-if="main_action == 'licences' && licences_action == 'overview' && ['super_admin'].some((role) => config.roles.includes(role))" />
            <Roles v-if="main_action == 'roles' && ['super_admin'].some((role) => config.roles.includes(role))" />
            <Users v-if="main_action == 'users' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
            <Teachers v-if="main_action == 'teachers' && (config.roles.includes('super_admin') || config.roles.includes('admin'))" />
            <TeachersList v-if="main_action == 'teachers_list' && (config.roles.includes('super_admin') || config.roles.includes('admin'))" />
            <Log v-if="main_action == 'log' && ['super_admin', 'admin'].some((role) => config.roles.includes(role))" />
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Schools from './components/Schools.vue'
import Schoolyears from './components/Schoolyears.vue'
import Licences from './components/Licences.vue'
import Roles from './components/Roles.vue'
import Users from './components/Users.vue'
import Teachers from './components/Teachers.vue'
import TeachersList from './components/TeachersList.vue'

import ActiveSchool from './components/ActiveSchool.vue'

import Log from './components/Log.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Schools, Schoolyears, ActiveSchool, Licences, Roles, Users, Log, Teachers, TeachersList },

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
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'main_action']),
    },

    methods: {
        openLicencesOverview() {
            this.main_action = 'licences'
            this.licences_action = 'overview'
        },
        moveToHorizon() {
            window.open('/horizon', '_blank')
        },
    },
}
</script>
