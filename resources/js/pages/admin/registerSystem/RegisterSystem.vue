<template>
    <v-container fluid class="register-system-page ma-0 w-100 pa-2">

        <AdminSectionHero
            class="mb-3"
            eyebrow="Verwaltung"
            title="Anmeldesystem"
            :active-section="activeSection"
            :chips="heroChips"
            :show-current-user-chip="true"
            user-chip-prefix="Benutzer"
            primary-color="#0d2016"
            secondary-color="#15803d"
            left-orb-color="#4ade80"
            right-orb-color="#86efac" />

        <Schoolyears />
        <ActiveRegisters />
        <Registers v-if="selected_schoolyear" />

    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import Schoolyears from '@/pages/admin/components/schoolyears/Schoolyears.vue'
import Registers from '@/pages/admin/registerSystem/components/RegisterSystem/Registers.vue'
import ActiveRegisters from '@/pages/admin/registerSystem/components/RegisterSystem/ActiveRegisters.vue'

export default {
    components: { AdminSectionHero, Schoolyears, Registers, ActiveRegisters },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        this.registerStore = useRegisterStore()
        this.main_menu = ''
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            registerStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'selected_schoolyear', 'main_menu']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
        ...mapWritableState(useRegisterStore, ['registers', 'active_registers']),

        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule'
        },

        selectedSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || 'Kein Schuljahr'
        },

        heroChips() {
            const chips = [
                { key: 'school', text: this.selectedSchoolLabel, icon: 'mdi-domain' },
                { key: 'schoolyear', text: this.selectedSchoolyearLabel, icon: 'mdi-calendar-month-outline' },
            ]
            const activeCount = Array.isArray(this.active_registers) ? this.active_registers.length : 0
            chips.push({
                key: 'active',
                text: activeCount > 0 ? `${activeCount} geöffnet` : 'Keines geöffnet',
                icon: activeCount > 0 ? 'mdi-check-circle-outline' : 'mdi-circle-off-outline',
                color: activeCount > 0 ? 'success' : 'white',
                variant: activeCount > 0 ? 'flat' : 'tonal',
            })
            const total = Array.isArray(this.registers) ? this.registers.length : 0
            if (total) {
                chips.push({ key: 'total', text: `${total} Anmeldetools`, icon: 'mdi-clipboard-list-outline' })
            }
            return chips
        },

        activeSection() {
            const sections = {
                '': { icon: 'mdi-view-list-outline', label: 'Übersicht', note: 'Anmeldesysteme und Schuljahre verwalten.' },
                edit_register: { icon: 'mdi-pencil-outline', label: 'Anmeldesystem bearbeiten', note: 'Einstellungen und Felder anpassen.' },
                create_register: { icon: 'mdi-plus-circle-outline', label: 'Neues Anmeldesystem', note: 'Neues Anmeldetool für dieses Schuljahr anlegen.' },
                remove_register: { icon: 'mdi-delete-outline', label: 'Anmeldesystem löschen', note: 'Dieser Vorgang kann nicht rückgängig gemacht werden.' },
            }
            return sections[this.action] || sections['']
        },
    },

    methods: {},
}
</script>

<style scoped>
.register-system-page {
    background: #0f172a;
    min-height: 100vh;
}
</style>
