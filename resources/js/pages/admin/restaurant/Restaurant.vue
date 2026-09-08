<template>
    <v-container fluid class="restaurant-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern"
            title="Restaurant"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#b45309"
            right-orb-color="#fcd34d" />

        <v-sheet v-if="main_action !== 'settings'" class="restaurant-nav mb-2" :class="{ 'is-locked': isNavigationLocked }">
            <v-btn-toggle
                :model-value="main_action"
                mandatory
                divided
                color="primary"
                class="restaurant-nav__buttons"
                :disabled="isNavigationLocked"
                @update:model-value="handleNavigation">
                <v-btn
                    v-for="item in visibleNavigationItems"
                    :key="item.key"
                    :data-testid="`restaurant-nav-${item.key}`"
                    :value="item.key"
                    :prepend-icon="item.icon"
                    :aria-pressed="main_action === item.key"
                    class="restaurant-nav__button"
                    :disabled="isNavigationLocked">
                    <span class="restaurant-nav__button-copy">
                        <span class="restaurant-nav__button-title">{{ item.label }}</span>
                        <span class="restaurant-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
            </v-btn-toggle>
            <v-btn-toggle
                :model-value="main_action"
                mandatory
                divided
                color="primary"
                class="restaurant-nav__actions"
                :disabled="isNavigationLocked">
                <v-btn
                    value="settings"
                    prepend-icon="mdi-cog-outline"
                    :aria-pressed="main_action === 'settings'"
                    class="restaurant-nav__button"
                    data-testid="restaurant-admin"
                    :disabled="isNavigationLocked"
                    @click="handleNavigation('settings')">
                    <span class="restaurant-nav__button-copy">
                        <span class="restaurant-nav__button-title">Admin</span>
                        <span class="restaurant-nav__button-meta">Einstellungen</span>
                    </span>
                </v-btn>
            </v-btn-toggle>
        </v-sheet>

        <Settings v-if="main_action === 'settings'" class="restaurant-settings pa-0">
            <template #navigation="{ selectedPanel, activatePanel, isPanelNavigationDisabled, disabled }">
                <v-sheet class="restaurant-nav mb-2" data-testid="restaurant-settings-nav">
                    <v-btn-toggle
                        :model-value="selectedPanel"
                        mandatory
                        divided
                        color="primary"
                        class="restaurant-nav__buttons"
                        :disabled="isNavigationLocked"
                        @update:model-value="activatePanel">
                        <v-btn
                            v-for="item in settingsNavigationItems"
                            :key="item.key"
                            :data-testid="`restaurant-settings-${item.key}`"
                            :value="item.key"
                            :prepend-icon="item.icon"
                            :aria-pressed="selectedPanel === item.key"
                            class="restaurant-nav__button"
                            :disabled="isNavigationLocked || isPanelNavigationDisabled(item.key)">
                            <span class="restaurant-nav__button-copy">
                                <span class="restaurant-nav__button-title">{{ item.label }}</span>
                                <span class="restaurant-nav__button-meta">{{ item.meta }}</span>
                            </span>
                        </v-btn>
                    </v-btn-toggle>
                    <v-btn-toggle
                        :model-value="main_action"
                        mandatory
                        divided
                        color="primary"
                        class="restaurant-nav__actions"
                        :disabled="disabled || isNavigationLocked">
                        <v-btn
                            value="overview"
                            prepend-icon="mdi-silverware-fork-knife"
                            :aria-pressed="false"
                            class="restaurant-nav__button"
                            data-testid="restaurant-back"
                            :disabled="disabled || isNavigationLocked"
                            @click="handleNavigation('overview')">
                            <span class="restaurant-nav__button-copy">
                                <span class="restaurant-nav__button-title">Restaurant</span>
                                <span class="restaurant-nav__button-meta">Überblick</span>
                            </span>
                        </v-btn>
                    </v-btn-toggle>
                </v-sheet>
            </template>
        </Settings>

        <div v-else class="restaurant-content">
            <v-row class="w-100 ma-0" dense>
                <Overview v-if="main_action === 'overview'" />
                <Foods v-if="main_action === 'foods'" />
                <Menus v-if="main_action === 'menus'" />
                <MenuPlans v-if="main_action === 'menu-plans'" ref="menuPlansSection" />
                <Reports v-if="main_action === 'reports'" />
                <Users v-if="main_action === 'users'" />
                <RestaurantSepa v-if="main_action === 'sepa'" />
                <CdgymLegacy v-if="main_action === 'cdgym' && isCdgymSchool" />
            </v-row>
        </div>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { defineAsyncComponent } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import Overview from './components/Overview.vue'

const Foods = defineAsyncComponent(() => import('./components/Foods.vue'))
const Menus = defineAsyncComponent(() => import('./components/Menus.vue'))
const MenuPlans = defineAsyncComponent(() => import('./components/MenuPlans.vue'))
const Reports = defineAsyncComponent(() => import('./components/Reports.vue'))
const RestaurantSepa = defineAsyncComponent(() => import('./components/RestaurantSepa.vue'))
const Users = defineAsyncComponent(() => import('./components/Users.vue'))
const Settings = defineAsyncComponent(() => import('./components/Settings.vue'))
const CdgymLegacy = defineAsyncComponent(() => import('./components/CdgymLegacy.vue'))

export default {
    components: { AdminSectionHero, Overview, Foods, Menus, MenuPlans, Reports, RestaurantSepa, Users, Settings, CdgymLegacy },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.restaurantStore = useRestaurantStore()
        this.foodStore = useFoodStore()
        this.menuStore = useMenuStore()
        this.action = ''
        this.action_2 = ''
        await this.loadPageData()
        this.ensureAllowedSection()
    },

    data() {
        return {
            adminStore: null,
            restaurantStore: null,
            foodStore: null,
            menuStore: null,
            main_action: this.$route.params.section || 'overview',
            isRefreshing: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'action_2']),
        isNavigationLocked() {
            return this.action !== '' || this.action_2 !== ''
        },
        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule gew\u00e4hlt'
        },
        isCdgymSchool() {
            return this.config?.selected_school?.long_name === 'Christian-Doppler-Gymnasium Salzburg'
        },
        headerChips() {
            return [
                {
                    key: 'school',
                    text: this.selectedSchoolLabel,
                    icon: 'mdi-domain',
                },
                {
                    key: 'role',
                    text: this.selectedRoleLabel,
                    icon: 'mdi-shield-account',
                },
            ]
        },
        selectedRoleLabel() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []

            if (roles.length === 0) {
                return 'Keine Rolle'
            }

            return roles.slice(0, 2).join(' / ')
        },
        activeSection() {
            const sections = {
                overview: {
                    label: '\u00dcberblick',
                    icon: 'mdi-view-dashboard-outline',
                    note: '',
                },
                foods: {
                    label: 'Speisen',
                    icon: 'mdi-silverware-variant',
                    note: '',
                },
                menus: {
                    label: 'Men\u00fcs',
                    icon: 'mdi-food-takeout-box-outline',
                    note: '',
                },
                'menu-plans': {
                    label: 'Men\u00fcpl\u00e4ne',
                    icon: 'mdi-calendar-text-outline',
                    note: '',
                },
                reports: {
                    label: 'Auswertungen',
                    icon: 'mdi-chart-box-outline',
                    note: 'Statistiken',
                },
                users: {
                    label: 'Benutzer',
                    icon: 'mdi-account-multiple-outline',
                    note: '',
                },
                sepa: {
                    label: 'SEPA Verwaltung',
                    icon: 'mdi-bank-transfer',
                    note: '',
                },
                settings: {
                    label: 'Einstellungen',
                    icon: 'mdi-cog-outline',
                    note: '',
                },
                cdgym: {
                    label: 'Alte Version, Cdgym',
                    icon: 'mdi-open-in-new',
                    note: 'cdgym.info',
                },
            }

            return sections[this.main_action] || sections.overview
        },
        visibleNavigationItems() {
            const items = [
                {
                    key: 'overview',
                    label: '\u00dcberblick',
                    meta: 'Startseite',
                    icon: 'mdi-view-dashboard-outline',
                },
                {
                    key: 'foods',
                    label: 'Speisen',
                    meta: 'Gerichte verwalten',
                    icon: 'mdi-silverware-variant',
                },
                {
                    key: 'menus',
                    label: 'Men\u00fcs',
                    meta: 'Men\u00fcfolgen pflegen',
                    icon: 'mdi-food-takeout-box-outline',
                },
                {
                    key: 'menu-plans',
                    label: 'Men\u00fcpl\u00e4ne',
                    meta: 'Pl\u00e4ne vorbereiten',
                    icon: 'mdi-calendar-text-outline',
                },
                {
                    key: 'reports',
                    label: 'Auswertungen',
                    meta: 'Statistiken',
                    icon: 'mdi-chart-box-outline',
                },
                {
                    key: 'users',
                    label: 'Benutzer',
                    meta: 'Personen verwalten',
                    icon: 'mdi-account-multiple-outline',
                },
                {
                    key: 'sepa',
                    label: 'SEPA Verwaltung',
                    meta: 'SEPA verwalten',
                    icon: 'mdi-bank-transfer',
                },
            ]

            if (this.isCdgymSchool) {
                items.push({
                    key: 'cdgym',
                    label: 'Alte Version, Cdgym',
                    meta: 'cdgym.info',
                    icon: 'mdi-open-in-new',
                })
            }

            return items
        },
        allowedSectionKeys() {
            return [...this.visibleNavigationItems.map((item) => item.key), 'settings']
        },
        settingsNavigationItems() {
            return [
                { key: 'general', label: 'Allgemein', meta: 'Schulweite Einstellungen', icon: 'mdi-tune-variant' },
                { key: 'categories', label: 'Kategorien', meta: 'Speisen strukturieren', icon: 'mdi-shape-outline' },
                { key: 'ingredient-icons', label: 'Zutaten-Symbole', meta: 'Kennzeichnungen', icon: 'mdi-image-multiple-outline' },
                { key: 'free-days', label: 'Freie Tage', meta: 'Schließzeiten', icon: 'mdi-calendar-remove-outline' },
                { key: 'eating-times', label: 'Speisezeiten', meta: 'Ausgabe planen', icon: 'mdi-clock-outline' },
                { key: 'users', label: 'Benutzer', meta: 'Mittagskonten', icon: 'mdi-account-group-outline' },
                { key: 'sepa', label: 'SEPA', meta: 'Lastschriftmandat', icon: 'mdi-bank-transfer' },
                { key: 'online', label: 'Online', meta: 'Bestellung & Sichtbarkeit', icon: 'mdi-web' },
            ]
        },
    },

    watch: {
        '$route.params.section'(section) {
            this.main_action = section || 'overview'
            this.ensureAllowedSection()
        },
    },

    methods: {
        loadPageData() {
            return Promise.all([this.restaurantStore.loadSettings(), this.foodStore.index(), this.menuStore.index()])
        },
        async refreshPageData() {
            if (this.isNavigationLocked || this.isRefreshing) {
                return
            }

            this.isRefreshing = true

            try {
                if (this.main_action === 'menu-plans' && this.$refs.menuPlansSection?.refreshData) {
                    await this.$refs.menuPlansSection.refreshData()
                    return
                }

                await this.loadPageData()
            } finally {
                this.isRefreshing = false
            }
        },
        handleNavigation(target) {
            if (this.isNavigationLocked) {
                return
            }

            this.navigateTo(target)
        },
        ensureAllowedSection() {
            if (this.allowedSectionKeys.includes(this.main_action)) {
                return
            }

            this.navigateTo('overview')
        },
        navigateTo(section) {
            this.main_action = section
            const path = section === 'overview' ? '/admin/restaurant' : `/admin/restaurant/${section}`
            this.$router.replace({ path })
        },
    },
}
</script>

<style scoped>
.restaurant-page {
    background: #0f172a;
    min-height: 100vh;
}

.restaurant-nav {
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.restaurant-nav__buttons {
    flex-wrap: wrap;
    row-gap: 6px;
    height: auto !important;
    flex: 1 1 0;
    min-width: 0;
}

.restaurant-nav__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    height: auto !important;
}

.restaurant-nav__button {
    min-height: 56px !important;
    height: auto !important;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.restaurant-nav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.restaurant-nav__button-title {
    font-weight: 650;
}

.restaurant-nav__button-meta {
    color: rgba(255, 255, 255, 0.98);
    font-size: 0.76rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 2px 8px;
}

.restaurant-nav.is-locked {
    opacity: 0.68;
}

.restaurant-content {
    margin-top: 2px;
    max-width: 1240px;
}

.restaurant-settings :deep(> .v-row) {
    max-width: 1240px;
    margin: 0;
}

@media (max-width: 960px) {
    .restaurant-nav__buttons {
        flex-basis: 100%;
    }

    .restaurant-nav__button {
        flex: 1 1 220px;
        min-width: 0;
        max-width: 100%;
        justify-content: start;
        padding-block: 8px;
    }

    .restaurant-nav__button :deep(.v-btn__content) {
        min-width: 0;
        white-space: normal;
        text-align: left;
    }

    .restaurant-nav__button-copy,
    .restaurant-nav__button-meta {
        min-width: 0;
        max-width: 100%;
        overflow-wrap: anywhere;
    }

    .restaurant-nav__actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
