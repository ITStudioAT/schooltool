<template>
    <v-container fluid class="teaching-page ma-0 w-100 pa-2">
        <v-sheet rounded="xl" class="teaching-hero mb-3">
            <div class="teaching-hero__bg-orb teaching-hero__bg-orb--left"></div>
            <div class="teaching-hero__bg-orb teaching-hero__bg-orb--right"></div>

            <v-row class="ma-0" align="stretch" dense>
                <v-col cols="12" lg="8" class="pa-2 pa-md-4">
                    <div class="teaching-hero__eyebrow">Unterricht</div>
                    <h1 class="teaching-hero__title">Lehrbereich und Kurssteuerung</h1>
                    <div class="teaching-hero__chips">
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
                    <v-card variant="tonal" color="white" class="teaching-hero__focus-card" rounded="xl">
                        <v-card-text class="pa-4">
                            <div class="teaching-hero__focus-label">Aktiver Bereich</div>
                            <div class="teaching-hero__focus-value">
                                <v-icon size="18" :icon="activeSection.icon" />
                                <span>{{ activeSection.label }}</span>
                            </div>
                            <div class="teaching-hero__focus-note">{{ activeSection.note }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-sheet>

        <v-sheet rounded="xl" class="teaching-nav mb-2" :class="{ 'is-locked': isNavigationLocked }">
            <div class="teaching-nav__buttons">
                <v-btn
                    v-for="item in visibleNavigationItems"
                    :key="item.key"
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

import Overview from './overview/Overview.vue'
import Settings from './settings/Settings.vue'
import Admin from './admin/Admin.vue'
import Search from './search/Search.vue'
import Schoolyear from './schoolyear/Schoolyear.vue'

export default {
    components: { Overview, Settings, Admin, Search, Schoolyear },

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
    --teaching-hero-primary: #0f172a;
    --teaching-hero-secondary: #1d4ed8;
    --teaching-hero-accent: #0ea5e9;
}

.teaching-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.24);
    background: linear-gradient(132deg, var(--teaching-hero-primary), var(--teaching-hero-secondary));
    color: #ffffff;
}

.teaching-hero__bg-orb {
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 999px;
    filter: blur(12px);
    opacity: 0.34;
    background: radial-gradient(circle at center, #67e8f9 0%, rgba(103, 232, 249, 0.08) 72%);
    pointer-events: none;
}

.teaching-hero__bg-orb--left {
    top: -64px;
    left: -52px;
}

.teaching-hero__bg-orb--right {
    right: -58px;
    bottom: -70px;
    background: radial-gradient(circle at center, #a5b4fc 0%, rgba(165, 180, 252, 0.08) 72%);
}

.teaching-hero__eyebrow {
    font-size: 0.76rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.82;
}

.teaching-hero__title {
    margin-top: 8px;
    font-size: clamp(1.4rem, 2.3vw, 2rem);
    line-height: 1.1;
    font-weight: 750;
}

.teaching-hero__description {
    margin-top: 12px;
    max-width: 64ch;
    font-size: 0.95rem;
    line-height: 1.45;
    opacity: 0.9;
}

.teaching-hero__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 18px;
}

.teaching-hero__focus-card {
    border: 1px solid rgba(255, 255, 255, 0.26);
    background: rgba(255, 255, 255, 0.16) !important;
    backdrop-filter: blur(3px);
    height: 100%;
}

.teaching-hero__focus-label {
    font-size: 0.72rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    opacity: 0.72;
}

.teaching-hero__focus-value {
    margin-top: 8px;
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.teaching-hero__focus-note {
    margin-top: 8px;
    font-size: 0.86rem;
    opacity: 0.84;
}

.teaching-nav {
    border: 1px solid rgba(148, 163, 184, 0.32);
    background: linear-gradient(132deg, rgba(30, 41, 59, 0.02), rgba(14, 116, 144, 0.08));
    padding: 10px;
}

.teaching-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.teaching-nav__button {
    min-height: 54px;
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

@media (max-width: 960px) {
    .teaching-nav__button {
        flex: 1 1 calc(50% - 8px);
    }
}
</style>
