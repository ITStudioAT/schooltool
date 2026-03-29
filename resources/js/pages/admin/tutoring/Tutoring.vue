<template>
    <v-container fluid class="tutoring-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Nachhilfe"
            title="Nachhilfe-Verwaltung"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#0f766e"
            right-orb-color="#99f6e4" />

        <v-sheet rounded="xl" class="tutoring-nav mb-2" :class="{ 'is-locked': isNavigationLocked }">
            <div class="tutoring-nav__buttons">
                <v-btn
                    v-for="item in visibleNavigationItems"
                    :key="item.key"
                    rounded="xl"
                    :color="main_action === item.key ? 'primary' : 'secondary'"
                    :variant="main_action === item.key ? 'flat' : 'tonal'"
                    class="tutoring-nav__button"
                    :disabled="isNavigationLocked"
                    @click="main_action = item.key">
                    <v-icon size="18" :icon="item.icon" class="mr-2" />
                    <span class="tutoring-nav__button-copy">
                        <span class="tutoring-nav__button-title">{{ item.label }}</span>
                        <span class="tutoring-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
            </div>
        </v-sheet>

        <div class="tutoring-content">
            <v-row class="w-100 ma-0" dense>
                <Overview v-if="main_action == 'overview'" />
                <Settings v-if="main_action == 'settings'" />
                <Subjects v-if="main_action == 'subjects'" />
                <Users v-if="main_action == 'users'" />
            </v-row>
        </div>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

import Overview from './components/Overview.vue'
import Settings from './components/Settings.vue'
import Subjects from './components/Subjects.vue'
import Users from './components/Users.vue'

export default {
    components: { AdminSectionHero, Settings, Subjects, Users, Overview },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.action = ''
        this.action_2 = ''
    },

    data() {
        return {
            adminStore: null,
            main_action: 'overview',
            admins: ['super_admin', 'admin', 'tutoring_admin'],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'action_2', 'is_loading']),
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
                    icon: 'mdi-home',
                    note: 'Allgemeine Übersicht des Nachhilfe-Tools.',
                },
                settings: {
                    label: 'Einstellungen',
                    icon: 'mdi-cog-outline',
                    note: 'Konfiguration des Nachhilfe-Tools.',
                },
                subjects: {
                    label: 'Fächer',
                    icon: 'mdi-television-shimmer',
                    note: 'Fächer und Angebote verwalten.',
                },
                users: {
                    label: 'Benutzer',
                    icon: 'mdi-account-multiple-outline',
                    note: 'Benutzer:innen des Nachhilfe-Tools verwalten.',
                },
            }
            return sections[this.main_action] || sections.overview
        },
        visibleNavigationItems() {
            const isAdmin = this.config.roles.some((item) => this.admins.includes(item))
            return [
                {
                    key: 'overview',
                    label: 'Übersicht',
                    meta: 'Tagesansicht',
                    icon: 'mdi-home',
                    visible: true,
                },
            ].filter((item) => item.visible)
        },
    },
}
</script>

<style scoped>
.tutoring-page {
    background: #0f172a;
    min-height: 100vh;
}

.tutoring-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.tutoring-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tutoring-nav__button {
    min-height: 54px;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.tutoring-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.tutoring-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.tutoring-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.tutoring-nav.is-locked {
    opacity: 0.68;
}

.tutoring-content {
    margin-top: 2px;
    max-width: 1160px;
}

@media (max-width: 960px) {
    .tutoring-nav__button {
        flex: 1 1 calc(50% - 8px);
    }
}
</style>
