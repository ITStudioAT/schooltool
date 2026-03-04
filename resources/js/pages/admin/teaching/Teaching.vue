<template>
    <v-container fluid class="teaching-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Unterricht"
            title="Lehrbereich und Kurssteuerung"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#1d4ed8"
            right-orb-color="#a5b4fc" />

        <v-sheet rounded="xl" class="teaching-nav mb-2" :class="{ 'is-locked': isNavigationLocked }">
            <div class="teaching-nav__buttons">
                <v-btn
                    v-for="item in visibleNavigationItems"
                    :key="item.key"
                    :data-testid="`teaching-nav-${item.key}`"
                    rounded="xl"
                    :color="main_action === item.key ? 'primary' : 'secondary'"
                    :variant="main_action === item.key ? 'flat' : 'tonal'"
                    class="teaching-nav__button"
                    :disabled="isNavigationLocked"
                    @click="handleNavigation(item.key)">
                    <v-icon size="18" :icon="item.icon" class="mr-2" />
                    <span class="teaching-nav__button-copy">
                        <span class="teaching-nav__button-title">{{ item.label }}</span>
                        <span class="teaching-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
            </div>
        </v-sheet>

        <v-row class="w-100 teaching-content" dense>
            <Overview v-if="main_action === 'overview'" />
            <Settings v-if="main_action === 'settings'" :key="`settings-${settings_view_key}`" />
            <Admin v-if="main_action === 'admin'" />
            <Search v-if="main_action === 'search'" />
            <Schoolyear v-if="main_action === 'schoolyear'" />
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

import Overview from './overview/Overview.vue'
import Settings from './settings/Settings.vue'
import Admin from './admin/Admin.vue'
import Search from './search/Search.vue'
import Schoolyear from './schoolyear/Schoolyear.vue'

export default {
    components: { AdminSectionHero, Overview, Settings, Admin, Search, Schoolyear },

    async beforeMount() {
        this.adminStore = useAdminStore()
        const teachingStore = useTeachingStore()
        if (!teachingStore.settings) {
            await teachingStore.loadSettings()
        }
    },

    unmounted() {
        this.action = ''
        this.action_2 = ''
    },

    data() {
        return {
            adminStore: null,
            main_action: 'overview',
            settings_view_key: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'action_2']),
        isNavigationLocked() {
            return this.action != '' || this.action_2 != ''
        },
        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule gewählt'
        },
        selectedSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || 'Kein Schuljahr gewählt'
        },
        selectedRoleLabel() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            if (!roles.length) {
                return 'Keine Rolle'
            }
            return roles.slice(0, 2).join(' / ')
        },
        headerChips() {
            return [
                {
                    key: 'school',
                    text: this.selectedSchoolLabel,
                    icon: 'mdi-domain',
                },
                {
                    key: 'schoolyear',
                    text: this.selectedSchoolyearLabel,
                    icon: 'mdi-calendar-month-outline',
                },
                {
                    key: 'role',
                    text: this.selectedRoleLabel,
                    icon: 'mdi-shield-account',
                },
            ]
        },
        activeSection() {
            const sections = {
                overview: {
                    label: 'Übersicht',
                    icon: 'mdi-view-dashboard-outline',
                    note: 'Kurse, Schüler:innen und Termine im Tagesfokus.',
                },
                settings: {
                    label: 'Einstellungen',
                    icon: 'mdi-cog-outline',
                    note: 'Schemas, Gewichtungen und Regeln anpassen.',
                },
                admin: {
                    label: 'Admin',
                    icon: 'mdi-shield-crown-outline',
                    note: 'Importe und Ferienverwaltung steuern.',
                },
                search: {
                    label: 'Suche',
                    icon: 'mdi-magnify',
                    note: 'Schüler:innen und Klassen schnell finden.',
                },
                schoolyear: {
                    label: 'Schuljahr',
                    icon: 'mdi-calendar-month-outline',
                    note: 'Aktives Schuljahr prüfen und wechseln.',
                },
            }
            return sections[this.main_action] || sections.overview
        },
        visibleNavigationItems() {
            return [
                {
                    key: 'overview',
                    label: 'Übersicht',
                    meta: 'Tagesansicht',
                    icon: 'mdi-view-dashboard-outline',
                    visible: true,
                },
                {
                    key: 'admin',
                    label: 'Admin',
                    meta: 'Import & Ferien',
                    icon: 'mdi-shield-crown-outline',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin']),
                },
                {
                    key: 'settings',
                    label: 'Einstellungen',
                    meta: 'Schema & Regeln',
                    icon: 'mdi-cog-outline',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin', 'teacher']),
                },
                {
                    key: 'search',
                    label: 'Suche',
                    meta: 'Personen & Klassen',
                    icon: 'mdi-magnify',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin', 'teacher']),
                },
                {
                    key: 'schoolyear',
                    label: 'Schuljahr',
                    meta: this.selectedSchoolyearLabel,
                    icon: 'mdi-calendar-month-outline',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin', 'teacher']),
                },
            ].filter((item) => item.visible)
        },
    },

    methods: {
        hasAnyRole(requiredRoles) {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            return roles.some((role) => requiredRoles.includes(role))
        },
        handleNavigation(target) {
            if (this.isNavigationLocked) {
                return
            }
            if (target === 'settings') {
                this.openSettings()
                return
            }
            this.main_action = target
        },
        openSettings() {
            this.settings_view_key++
            this.main_action = 'settings'
        },
    },
}
</script>

<style scoped>
.teaching-page {
    background: #0f172a;
    min-height: 100vh;
}

.teaching-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.teaching-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.teaching-nav__button {
    height: 40px !important;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.teaching-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.teaching-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.teaching-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.teaching-nav.is-locked {
    opacity: 0.68;
}

.teaching-content {
    margin-top: 2px;
}

</style>
