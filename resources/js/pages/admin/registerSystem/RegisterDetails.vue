<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != ''">
            <its-menu-button subtitle="Zurück" icon="mdi-arrow-left" to="/admin/register_system" color="secondary" />
        </v-card>

        <!-- Überblick über das Anmeldesystem -->
        <Overview />

        <v-row class="w-100" dense>
            <MainMenu />
        </v-row>

        <v-row class="w-100" dense v-if="main_menu == ''">
            <!-- TERMINE mit Menü-->
            <DatesWithMenu />

            <!-- NEUE TERMINE ANLEGEN -->
            <AddDates v-if="action == 'add_dates'" />

            <!-- NEUE PERSON ZU TERMIN HINZUFÜGEN ANLEGEN -->
            <AddPerson v-if="action == 'add_person'" />

            <!-- BUCHUNGEN ANZEIGEN -->
            <ShowBookings v-if="action == 'show_bookings'" />

            <!-- REGISTER USERS ANZEIGEN -->
            <RegisterUsers v-if="action == 'show_users'" />
        </v-row>

        <v-row class="w-100" dense v-if="main_menu == 'register_users'">
            <!-- REGISTER USERS ANZEIGEN -->
            <RegisterUsers />
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Schoolyears from '@/pages/admin/components/schoolyears/Schoolyears.vue'
import Overview from './components/RegisterDetails/Overview.vue'
import MainMenu from './components/RegisterDetails/MainMenu.vue'
import DatesWithMenu from './components/RegisterDetails/DatesWithMenu.vue'
import AddDates from './components/RegisterDetails/AddDates.vue'
import AddPerson from './components/RegisterDetails/AddPerson.vue'
import ShowBookings from './components/RegisterDetails/ShowBookings.vue'
import RegisterUsers from './components/RegisterDetails/RegisterUsers.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Schoolyears, Overview, DatesWithMenu, AddDates, AddPerson, ShowBookings, RegisterUsers, MainMenu },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['main_menu', 'action']),
    },
    watch: {},

    methods: {},
}
</script>
