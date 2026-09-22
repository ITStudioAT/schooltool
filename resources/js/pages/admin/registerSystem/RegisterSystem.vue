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

        <v-sheet rounded="xl" class="register-system-nav mb-2">
            <div class="register-system-nav__sections" role="group" aria-label="Anmeldetool-Bereiche">
                <v-btn
                    size="small"
                    rounded="lg"
                    :variant="activePanel === 'registers' ? 'flat' : 'text'"
                    :color="activePanel === 'registers' ? 'primary' : 'white'"
                    :aria-pressed="activePanel === 'registers'"
                    :disabled="action !== ''"
                    prepend-icon="mdi-clipboard-list-outline"
                    @click="selectPanel('registers')">
                    Anmeldesysteme
                </v-btn>
                <v-btn
                    size="small"
                    rounded="lg"
                    :variant="activePanel === 'users' ? 'flat' : 'text'"
                    :color="activePanel === 'users' ? 'primary' : 'white'"
                    :aria-pressed="activePanel === 'users'"
                    :disabled="action !== ''"
                    prepend-icon="mdi-account-group-outline"
                    @click="selectPanel('users')">
                    Benutzer
                </v-btn>
            </div>
            <v-spacer />
            <v-btn
                icon
                size="small"
                variant="text"
                color="grey"
                class="register-system-nav__settings-btn"
                title="Anmeldetool-Einstellungen"
                :disabled="action !== ''"
                @click="$router.push('/admin/settings?tab=register')">
                <v-icon size="20">mdi-cog-outline</v-icon>
            </v-btn>
        </v-sheet>

        <v-row v-if="activePanel === 'users'" class="w-100 ma-0" dense>
            <RegisterUsers />
        </v-row>
        <template v-else>
            <Schoolyears />
            <ActiveRegisters />
            <Registers v-if="selected_schoolyear" />
        </template>

    </v-container>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import Schoolyears from '@/pages/admin/components/schoolyears/Schoolyears.vue'
import Registers from '@/pages/admin/registerSystem/components/RegisterSystem/Registers.vue'
import ActiveRegisters from '@/pages/admin/registerSystem/components/RegisterSystem/ActiveRegisters.vue'

const RegisterUsers = defineAsyncComponent(() => import('@/pages/admin/settings/components/RegisterUsers.vue'))

export default {
    components: { AdminSectionHero, Schoolyears, Registers, ActiveRegisters, RegisterUsers },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        this.registerStore = useRegisterStore()
        this.main_menu = ''
    },

    unmounted() {},

    beforeRouteUpdate() {
        return this.action === ''
    },

    beforeRouteLeave() {
        return this.action === ''
    },

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

        activePanel() {
            return this.$route.query.panel === 'users' ? 'users' : 'registers'
        },

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
            if (this.activePanel === 'users') {
                return { icon: 'mdi-account-group-outline', label: 'Benutzer', note: 'Benutzer des Anmeldetools verwalten.' }
            }
            const sections = {
                '': { icon: 'mdi-view-list-outline', label: 'Übersicht', note: 'Anmeldesysteme und Schuljahre verwalten.' },
                edit_register: { icon: 'mdi-pencil-outline', label: 'Anmeldesystem bearbeiten', note: 'Einstellungen und Felder anpassen.' },
                create_register: { icon: 'mdi-plus-circle-outline', label: 'Neues Anmeldesystem', note: 'Neues Anmeldetool für dieses Schuljahr anlegen.' },
                remove_register: { icon: 'mdi-delete-outline', label: 'Anmeldesystem löschen', note: 'Dieser Vorgang kann nicht rückgängig gemacht werden.' },
            }
            return sections[this.action] || sections['']
        },
    },

    methods: {
        selectPanel(panel) {
            if (this.action !== '' || this.activePanel === panel) return

            const query = { ...this.$route.query }
            if (panel === 'users') {
                query.panel = 'users'
            } else {
                delete query.panel
            }
            this.$router.push({ path: this.$route.path, query })
        },
    },
}
</script>

<style scoped>
.register-system-page {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    min-height: 100vh;
}

.register-system-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 6px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.register-system-nav__sections {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    min-width: 0;
}

.register-system-nav__settings-btn {
    flex-shrink: 0;
    opacity: 0.5;
    transition: opacity 0.2s;
}

.register-system-nav__settings-btn:hover {
    opacity: 1;
}
</style>
