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

        <v-sheet rounded="xl" class="restaurant-nav mb-2" :class="{ 'is-locked': isNavigationLocked }">
            <div class="restaurant-nav__buttons">
                <v-btn
                    v-for="item in visibleNavigationItems"
                    :key="item.key"
                    :data-testid="`restaurant-nav-${item.key}`"
                    rounded="xl"
                    :color="main_action === item.key ? 'primary' : 'secondary'"
                    :variant="main_action === item.key ? 'flat' : 'tonal'"
                    class="restaurant-nav__button"
                    :disabled="isNavigationLocked"
                    @click="handleNavigation(item.key)">
                    <v-icon size="18" :icon="item.icon" class="mr-2" />
                    <span class="restaurant-nav__button-copy">
                        <span class="restaurant-nav__button-title">{{ item.label }}</span>
                        <span class="restaurant-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
            </div>
            <div class="restaurant-nav__actions">
                <v-btn
                    rounded="xl"
                    icon
                    size="small"
                    variant="text"
                    color="grey"
                    class="restaurant-nav__settings-btn"
                    title="Restaurant-Einstellungen"
                    @click="$router.push('/admin/settings?tab=restaurant')">
                    <v-icon size="20">mdi-cog-outline</v-icon>
                </v-btn>
            </div>
        </v-sheet>

        <div class="restaurant-content">
            <v-row class="w-100 ma-0" dense>
                <Overview v-if="main_action === 'overview'" />
                <Foods v-if="main_action === 'foods'" />
                <Menus v-if="main_action === 'menus'" />
                <MenuPlans v-if="main_action === 'menu-plans'" ref="menuPlansSection" />
                <Reports v-if="main_action === 'reports'" />
                <Users v-if="main_action === 'users'" />
                <RestaurantSepa v-if="main_action === 'sepa'" />
                <Settings v-if="main_action === 'settings'" />
            </v-row>
        </div>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useFoodStore } from '@/stores/admin/restaurant/FoodStore'
import { useMenuStore } from '@/stores/admin/restaurant/MenuStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import Overview from './components/Overview.vue'
import Foods from './components/Foods.vue'
import Menus from './components/Menus.vue'
import MenuPlans from './components/MenuPlans.vue'
import Reports from './components/Reports.vue'
import RestaurantSepa from './components/RestaurantSepa.vue'
import Users from './components/Users.vue'
import Settings from './components/Settings.vue'

export default {
    components: { AdminSectionHero, Overview, Foods, Menus, MenuPlans, Reports, RestaurantSepa, Users, Settings },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.restaurantStore = useRestaurantStore()
        this.foodStore = useFoodStore()
        this.menuStore = useMenuStore()
        this.action = ''
        this.action_2 = ''
        await this.loadPageData()
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
            }

            return sections[this.main_action] || sections.overview
        },
        visibleNavigationItems() {
            return [
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
        },
    },

    watch: {
        '$route.params.section'(section) {
            this.main_action = section || 'overview'
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
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.restaurant-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    flex: 1;
}

.restaurant-nav__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.restaurant-nav__settings-btn {
    flex-shrink: 0;
    opacity: 0.5;
    transition: opacity 0.2s;
}

.restaurant-nav__settings-btn:hover {
    opacity: 1;
}

.restaurant-nav__button {
    min-height: 54px;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.restaurant-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.restaurant-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.restaurant-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.restaurant-nav.is-locked {
    opacity: 0.68;
}

.restaurant-content {
    margin-top: 2px;
    max-width: 1240px;
}

@media (max-width: 960px) {
    .restaurant-nav__button {
        flex: 1 1 calc(50% - 8px);
    }

    .restaurant-nav__actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
