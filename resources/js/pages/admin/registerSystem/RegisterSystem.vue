<template>
    <v-container fluid class="register-system-page ma-0 w-100 pa-2">

        <AdminPageHeader
            class="mb-3"
            location="Anmeldetool"
            :section="activeSection.label === 'Übersicht' ? '' : activeSection.label"
            :context-label="selectedSchoolyearLabel"
            :status-label="registrationStatusLabel"
            :is-open="active_registers_status === 'ready' && activeCount > 0" />

        <v-sheet class="register-system-nav mb-2">
            <div class="register-system-nav__sections" role="group" aria-label="Anmeldetool-Bereiche">
                <v-btn
                    variant="flat"
                    class="register-system-nav__button"
                    :class="{ 'v-btn--active': activePanel === 'registers' }"
                    :color="activePanel === 'registers' ? 'primary' : undefined"
                    :aria-pressed="activePanel === 'registers'"
                    :disabled="action !== ''"
                    prepend-icon="mdi-clipboard-list-outline"
                    @click="selectPanel('registers')">
                    <span class="register-system-nav__button-copy">
                        <span>Anmeldesysteme</span>
                        <span class="register-system-nav__button-meta" aria-hidden="true">Schuljahre &amp; Termine</span>
                    </span>
                </v-btn>
                <v-btn
                    variant="flat"
                    class="register-system-nav__button"
                    :class="{ 'v-btn--active': activePanel === 'users' }"
                    :color="activePanel === 'users' ? 'primary' : undefined"
                    :aria-pressed="activePanel === 'users'"
                    :disabled="action !== ''"
                    prepend-icon="mdi-account-group-outline"
                    @click="selectPanel('users')">
                    <span class="register-system-nav__button-copy">
                        <span>Benutzer</span>
                        <span class="register-system-nav__button-meta" aria-hidden="true">Konten &amp; Zugänge</span>
                    </span>
                </v-btn>
            </div>
        </v-sheet>

        <v-row v-if="activePanel === 'users'" class="w-100 ma-0" dense>
            <RegisterUsers />
        </v-row>
        <template v-else>
            <Schoolyears menu-style />
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
import AdminPageHeader from '@/pages/admin/components/AdminPageHeader.vue'
import Schoolyears from '@/pages/admin/components/schoolyears/Schoolyears.vue'
import Registers from '@/pages/admin/registerSystem/components/RegisterSystem/Registers.vue'
import ActiveRegisters from '@/pages/admin/registerSystem/components/RegisterSystem/ActiveRegisters.vue'

const RegisterUsers = defineAsyncComponent(() => import('@/pages/admin/settings/components/RegisterUsers.vue'))

export default {
    components: { AdminPageHeader, Schoolyears, Registers, ActiveRegisters, RegisterUsers },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        this.registerStore = useRegisterStore()
        this.main_menu = ''
        if (this.activePanel === 'users') {
            await this.registerStore.loadActiveRegisters()
        }
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
        ...mapWritableState(useRegisterStore, ['registers', 'active_registers', 'active_registers_status']),

        activePanel() {
            return this.$route.query.panel === 'users' ? 'users' : 'registers'
        },

        currentSchoolyear() {
            return this.selected_schoolyear || this.config?.selected_schoolyear
        },

        selectedSchoolyearLabel() {
            return this.currentSchoolyear?.name || 'Kein Schuljahr ausgewählt'
        },

        activeCount() {
            if (!this.currentSchoolyear?.id) return 0

            return this.active_registers.filter((register) =>
                String(register.schoolyear_id) === String(this.currentSchoolyear.id),
            ).length
        },

        registrationStatusLabel() {
            if (!this.currentSchoolyear?.id || this.active_registers_status === 'error') {
                return 'Öffnungsstatus nicht verfügbar'
            }
            if (this.active_registers_status !== 'ready') return 'Öffnungsstatus wird geladen …'
            if (this.activeCount === 0) return 'Kein Anmeldesystem geöffnet'

            return this.activeCount === 1 ? '1 Anmeldesystem geöffnet' : `${this.activeCount} Anmeldesysteme geöffnet`
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
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.register-system-nav__sections {
    display: flex;
    flex-wrap: wrap;
    row-gap: 6px;
    width: 100%;
    min-width: 0;
}

.register-system-nav__button {
    min-height: 56px !important;
    height: auto !important;
    border-radius: 0;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.register-system-nav__button:first-child {
    border-start-start-radius: 4px;
    border-end-start-radius: 4px;
    border-inline-end: 1px solid rgba(0, 0, 0, 0.12);
}

.register-system-nav__button:last-child {
    border-start-end-radius: 4px;
    border-end-end-radius: 4px;
}

.register-system-nav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.register-system-nav__button-meta {
    color: var(--admin-page-muted, #65716c);
    background: #f2f4f7;
    border: 1px solid var(--admin-page-border, #e7e9ef);
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 0.76rem;
    font-weight: 700;
}

@media (max-width: 480px) {
    .register-system-nav__button { width: 100%; min-width: 0; }
    .register-system-nav__button:first-child { border-inline-end: 0; }
}

</style>
