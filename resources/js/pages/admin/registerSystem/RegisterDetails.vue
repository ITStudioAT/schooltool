<template>
    <v-container fluid class="register-details-page ma-0 w-100 pa-2">

        <AdminPageHeader
            class="mb-3"
            location="Anmeldetool"
            :section="activeSection.label"
            :context-label="selectedSchoolyearLabel"
            :status-label="registrationStatusLabel"
            :is-open="selected_register?.is_active === true" />

        <div class="d-flex mb-2">
            <v-btn
                rounded="xl"
                color="white"
                variant="tonal"
                size="small"
                prepend-icon="mdi-arrow-left"
                to="/admin/register_system"
                :disabled="action !== ''">
                Zurück
            </v-btn>
        </div>

        <Overview />

        <MainMenu />

        <v-row class="w-100" dense v-if="main_menu === ''">
            <DatesWithMenu />
            <AddDates v-if="action === 'add_dates'" />
            <AddPerson v-if="action === 'add_person'" />
            <ShowBookings v-if="action === 'show_bookings'" />
            <RegisterUsers v-if="action === 'show_users'" />
        </v-row>

        <v-row class="w-100" dense v-if="main_menu === 'register_users'">
            <RegisterUsers />
        </v-row>

    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import AdminPageHeader from '@/pages/admin/components/AdminPageHeader.vue'
import Overview from './components/RegisterDetails/Overview.vue'
import MainMenu from './components/RegisterDetails/MainMenu.vue'
import DatesWithMenu from './components/RegisterDetails/DatesWithMenu.vue'
import AddDates from './components/RegisterDetails/AddDates.vue'
import AddPerson from './components/RegisterDetails/AddPerson.vue'
import ShowBookings from './components/RegisterDetails/ShowBookings.vue'
import RegisterUsers from './components/RegisterDetails/RegisterUsers.vue'

export default {
    components: { AdminPageHeader, Overview, DatesWithMenu, AddDates, AddPerson, ShowBookings, RegisterUsers, MainMenu },

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
        ...mapWritableState(useAdminStore, ['config', 'main_menu', 'action', 'selected_register', 'selected_schoolyear']),
        ...mapWritableState(useRegisterStore, ['active_registers']),

        selectedSchoolyearLabel() {
            if (this.selected_register?.schoolyear_name) return this.selected_register.schoolyear_name
            const schoolyear = this.selected_schoolyear || this.config?.selected_schoolyear

            return schoolyear && String(schoolyear.id) === String(this.selected_register?.schoolyear_id)
                ? schoolyear.name
                : 'Kein Schuljahr verfügbar'
        },

        registrationStatusLabel() {
            if (this.selected_register?.is_active === true) return 'Anmeldesystem geöffnet'
            if (this.selected_register?.is_active === false) return 'Anmeldesystem geschlossen'

            return 'Öffnungsstatus nicht verfügbar'
        },

        activeSection() {
            const sections = {
                '': { icon: 'mdi-calendar-clock-outline', label: 'Termine', note: 'Termine und Anmeldungen verwalten.' },
                add_dates: { icon: 'mdi-calendar-plus', label: 'Termine anlegen', note: 'Neue Terminblöcke erstellen.' },
                add_person: { icon: 'mdi-account-plus', label: 'Person anmelden', note: 'Manuelle Anmeldung durchführen.' },
                show_bookings: { icon: 'mdi-view-list-outline', label: 'Anmeldungen', note: 'Buchungen einsehen und verwalten.' },
                show_users: { icon: 'mdi-account-multiple', label: 'Benutzer', note: 'Angemeldete Personen anzeigen.' },
                delete_dates: { icon: 'mdi-delete-outline', label: 'Termine löschen', note: 'Markierte Termine entfernen.' },
                print: { icon: 'mdi-printer-outline', label: 'Drucken', note: 'Export als PDF oder Excel.' },
            }
            if (this.main_menu === 'register_users') {
                return { icon: 'mdi-account-multiple', label: 'Benutzer', note: 'Alle Anmelder dieses Systems.' }
            }
            return sections[this.action] || sections['']
        },
    },

    watch: {},
    methods: {},
}
</script>

<style scoped>
.register-details-page {
    background: #0f172a;
    min-height: 100vh;
}
</style>
