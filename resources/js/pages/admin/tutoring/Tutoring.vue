<template>
    <v-container fluid class="tutoring-page ma-0 w-100 pa-2">
        <v-sheet rounded="xl" class="tutoring-hero mb-3">
            <div class="tutoring-hero__bg-orb tutoring-hero__bg-orb--left"></div>
            <div class="tutoring-hero__bg-orb tutoring-hero__bg-orb--right"></div>

            <v-row class="ma-0" align="stretch" dense>
                <v-col cols="12" lg="8" class="pa-2 pa-md-4">
                    <div class="tutoring-hero__eyebrow">Nachhilfe</div>
                    <h1 class="tutoring-hero__title">Nachhilfe-Verwaltung</h1>
                    <div class="tutoring-hero__chips">
                        <v-chip size="small" variant="tonal" color="white" prepend-icon="mdi-domain">
                            {{ selectedSchoolLabel }}
                        </v-chip>
                        <v-chip size="small" variant="tonal" color="white" prepend-icon="mdi-calendar-month-outline">
                            {{ selectedSchoolyearLabel }}
                        </v-chip>
                        <v-chip size="small" variant="tonal" color="white" prepend-icon="mdi-shield-account">
                            {{ selectedRoleLabel }}
                        </v-chip>
                    </div>
                </v-col>

                <v-col cols="12" lg="4" class="pa-2 pa-md-4">
                    <v-card variant="tonal" color="white" class="tutoring-hero__focus-card" rounded="xl">
                        <v-card-text class="pa-4">
                            <div class="tutoring-hero__focus-label">Aktiver Bereich</div>
                            <div class="tutoring-hero__focus-value">
                                <v-icon size="18" :icon="activeSection.icon" />
                                <span>{{ activeSection.label }}</span>
                            </div>
                            <div class="tutoring-hero__focus-note">{{ activeSection.note }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-sheet>

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

import Overview from './components/Overview.vue'
import Settings from './components/Settings.vue'
import Subjects from './components/Subjects.vue'
import Users from './components/Users.vue'

export default {
    components: { Settings, Subjects, Users, Overview },

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
                {
                    key: 'settings',
                    label: 'Einstellungen',
                    meta: 'Konfiguration',
                    icon: 'mdi-cog-outline',
                    visible: isAdmin,
                },
                {
                    key: 'subjects',
                    label: 'Fächer',
                    meta: 'Fächer verwalten',
                    icon: 'mdi-television-shimmer',
                    visible: isAdmin,
                },
                {
                    key: 'users',
                    label: 'Benutzer',
                    meta: 'Benutzer verwalten',
                    icon: 'mdi-account-multiple-outline',
                    visible: isAdmin,
                },
            ].filter((item) => item.visible)
        },
    },
}
</script>

<style scoped>
.tutoring-page {
    --tutoring-hero-primary: #0f172a;
    --tutoring-hero-secondary: #0f766e;
    --tutoring-hero-accent: #14b8a6;
    background: #0f172a;
    min-height: 100vh;
}

.tutoring-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.24);
    background: linear-gradient(132deg, var(--tutoring-hero-primary), var(--tutoring-hero-secondary));
    color: #ffffff;
}

.tutoring-hero__bg-orb {
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 999px;
    filter: blur(12px);
    opacity: 0.34;
    background: radial-gradient(circle at center, #67e8f9 0%, rgba(103, 232, 249, 0.08) 72%);
    pointer-events: none;
}

.tutoring-hero__bg-orb--left {
    top: -64px;
    left: -52px;
}

.tutoring-hero__bg-orb--right {
    right: -58px;
    bottom: -70px;
    background: radial-gradient(circle at center, #99f6e4 0%, rgba(153, 246, 228, 0.08) 72%);
}

.tutoring-hero__eyebrow {
    font-size: 0.76rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.82;
}

.tutoring-hero__title {
    margin-top: 8px;
    font-size: clamp(1.4rem, 2.3vw, 2rem);
    line-height: 1.1;
    font-weight: 750;
}

.tutoring-hero__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 18px;
}

.tutoring-hero__focus-card {
    border: 1px solid rgba(255, 255, 255, 0.26);
    background: rgba(255, 255, 255, 0.16) !important;
    backdrop-filter: blur(3px);
    height: 100%;
}

.tutoring-hero__focus-label {
    font-size: 0.72rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    opacity: 0.72;
}

.tutoring-hero__focus-value {
    margin-top: 8px;
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tutoring-hero__focus-note {
    margin-top: 8px;
    font-size: 0.86rem;
    opacity: 0.84;
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
