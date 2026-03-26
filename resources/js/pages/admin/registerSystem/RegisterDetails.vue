<template>
    <v-container fluid class="register-details-page ma-0 w-100 pa-2">

        <AdminSectionHero
            class="mb-3"
            eyebrow="Anmeldetool"
            :title="selected_register?.name || 'Details'"
            :active-section="activeSection"
            :chips="heroChips"
            :show-current-user-chip="false"
            primary-color="#0d2016"
            secondary-color="#15803d"
            left-orb-color="#4ade80"
            right-orb-color="#86efac">
            <template #chips>
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
            </template>
        </AdminSectionHero>

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
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import Overview from './components/RegisterDetails/Overview.vue'
import MainMenu from './components/RegisterDetails/MainMenu.vue'
import DatesWithMenu from './components/RegisterDetails/DatesWithMenu.vue'
import AddDates from './components/RegisterDetails/AddDates.vue'
import AddPerson from './components/RegisterDetails/AddPerson.vue'
import ShowBookings from './components/RegisterDetails/ShowBookings.vue'
import RegisterUsers from './components/RegisterDetails/RegisterUsers.vue'

export default {
    components: { AdminSectionHero, Overview, DatesWithMenu, AddDates, AddPerson, ShowBookings, RegisterUsers, MainMenu },

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

        heroChips() {
            const chips = []
            if (this.selected_schoolyear?.name) {
                chips.push({ key: 'sy', text: this.selected_schoolyear.name, icon: 'mdi-calendar-month-outline' })
            }
            if (this.selected_register?.bookings_count != null) {
                chips.push({ key: 'bookings', text: `${this.selected_register.bookings_count} Anmeldungen`, icon: 'mdi-account-multiple-outline' })
            }
            if (this.selected_register?.dates_count != null) {
                chips.push({ key: 'dates', text: `${this.selected_register.dates_count} Termine`, icon: 'mdi-calendar-clock-outline' })
            }
            if (this.selected_register) {
                chips.push({
                    key: 'status',
                    text: this.selected_register.is_active ? 'Geöffnet' : 'Geschlossen',
                    icon: this.selected_register.is_active ? 'mdi-check-circle-outline' : 'mdi-circle-off-outline',
                    color: this.selected_register.is_active ? 'success' : 'white',
                    variant: this.selected_register.is_active ? 'flat' : 'tonal',
                })
            }
            return chips
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
