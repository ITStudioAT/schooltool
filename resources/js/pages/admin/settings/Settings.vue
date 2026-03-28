<template>
    <div class="settings-page" v-if="config && config.is_auth">
        <div class="settings-bg">
            <div class="settings-bg-image"></div>
            <div class="settings-bg-glow settings-bg-glow-left"></div>
            <div class="settings-bg-glow settings-bg-glow-right"></div>
        </div>

        <v-container fluid class="ma-0 w-100 pa-2 settings-page-inner">
            <AdminSectionHero
                class="mb-3"
                eyebrow="Verwaltung"
                title="Einstellungen"
                :active-section="activeSection"
                :chips="headerChips"
                :show-current-user-chip="true" />

            <v-sheet rounded="xl" class="settings-tabs-sheet mb-2">
                <v-tabs v-model="main_action" color="white" bg-color="transparent" slider-color="white" show-arrows>
                    <v-tab v-for="item in navigationItems" :key="item.key" :value="item.key" :prepend-icon="item.icon">
                        {{ item.label }}
                    </v-tab>
                </v-tabs>
            </v-sheet>

            <v-sheet rounded="xl" class="settings-subnav mb-2">
                <div class="settings-subnav__buttons">
                    <v-btn
                        v-for="item in subNavigationItems"
                        :key="item.key"
                        rounded="xl"
                        :color="sub_action === item.key ? 'primary' : 'secondary'"
                        :variant="sub_action === item.key ? 'flat' : 'tonal'"
                        class="settings-subnav__button"
                        @click="sub_action = item.key">
                        <v-icon size="18" :icon="item.icon" class="mr-2" />
                        <span class="settings-subnav__button-copy">
                            <span class="settings-subnav__button-title">{{ item.label }}</span>
                            <span class="settings-subnav__button-meta">{{ item.meta }}</span>
                        </span>
                    </v-btn>
                </div>
            </v-sheet>

            <div class="settings-content">
                <v-row class="w-100 ma-0" dense>
                    <div v-if="sub_action === 'schools'" class="settings-schools-wrap">
                        <Schools />
                    </div>

                    <v-col v-else cols="12">
                        <v-sheet rounded="xl" class="pa-6 settings-empty-card">
                            <div class="settings-empty-icon">
                                <v-icon size="48" color="grey-lighten-1">mdi-cog-outline</v-icon>
                            </div>
                            <div class="settings-empty-text">
                                Einstellungen für <strong>{{ activeSection }}</strong> &rsaquo; <strong>{{ activeSubSection }}</strong> werden hier bald verfügbar sein.
                            </div>
                        </v-sheet>
                    </v-col>
                </v-row>
            </div>
        </v-container>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import Schools from '@/pages/admin/superAdmin/components/Schools.vue'

export default {
    components: { Schools },

    data() {
        return {
            main_action: this.initialTab(),
            sub_action: 'schools',
        }
    },

    watch: {
        main_action(val) {
            this.sub_action = 'schools'
            const query = val === 'super_admin' ? '' : `?tab=${val}`
            const target = `/admin/settings${query}`
            if (this.$route.fullPath !== target) {
                this.$router.replace(target)
            }
        },
        '$route.query.tab'(val) {
            const tab = val || 'super_admin'
            if (this.navigationItems.some((i) => i.key === tab)) {
                this.main_action = tab
            }
        },
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        headerChips() {
            const chips = []
            if (this.config?.selected_school?.long_name || this.config?.selected_school?.name) {
                chips.push({
                    label: this.config.selected_school.long_name || this.config.selected_school.name,
                    icon: 'mdi-school',
                })
            }
            return chips
        },
        activeSection() {
            const item = this.navigationItems.find((i) => i.key === this.main_action)
            return item ? item.label : ''
        },
        activeSubSection() {
            const item = this.subNavigationItems.find((i) => i.key === this.sub_action)
            return item ? item.label : ''
        },
        subNavigationItems() {
            return [
                { key: 'schools', label: 'Schulen', meta: 'Verwaltung', icon: 'mdi-school' },
                { key: 'display', label: 'Anzeige', meta: 'Darstellung', icon: 'mdi-palette-outline' },
                { key: 'advanced', label: 'Erweitert', meta: 'Optionen', icon: 'mdi-tune-variant' },
            ]
        },
        navigationItems() {
            return [
                { key: 'super_admin', label: 'Super-Admin', icon: 'mdi-shield-crown' },
                { key: 'admin', label: 'Admin', icon: 'mdi-shield-account' },
                { key: 'register', label: 'Anmeldetool', icon: 'mdi-calendar-check' },
                { key: 'tutoring', label: 'Nachhilfe', icon: 'mdi-account-group' },
                { key: 'teaching', label: 'Unterricht', icon: 'mdi-book-open-variant' },
                { key: 'groups', label: 'Gruppen', icon: 'mdi-account-multiple-outline' },
                { key: 'restaurant', label: 'Restaurant', icon: 'mdi-silverware-fork-knife' },
                { key: 'profile', label: 'Profil', icon: 'mdi-account-circle' },
            ]
        },
    },

    methods: {
        initialTab() {
            const tab = this.$route?.query?.tab || 'super_admin'
            const keys = ['super_admin', 'admin', 'register', 'tutoring', 'teaching', 'groups', 'restaurant', 'profile']
            return keys.includes(tab) ? tab : 'super_admin'
        },
    },
}
</script>

<style scoped>
.settings-page {
    position: relative;
    min-height: 100vh;
}

.settings-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    pointer-events: none;
}

.settings-bg-image {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
}

.settings-bg-glow {
    position: absolute;
    width: 600px;
    height: 600px;
    border-radius: 50%;
    filter: blur(120px);
    opacity: 0.18;
}

.settings-bg-glow-left {
    top: -200px;
    left: -100px;
    background: #6366f1;
}

.settings-bg-glow-right {
    bottom: -200px;
    right: -100px;
    background: #818cf8;
}

.settings-page-inner {
    position: relative;
    z-index: 1;
}

.settings-tabs-sheet {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.settings-tabs-sheet :deep(.v-tab:not(.v-tab--selected)) {
    color: rgba(255, 255, 255, 0.45) !important;
}

.settings-subnav {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 8px;
}

.settings-subnav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.settings-subnav__button {
    text-transform: none !important;
    letter-spacing: 0 !important;
    padding: 6px 16px !important;
    height: auto !important;
    min-height: 44px;
}

.settings-subnav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.settings-subnav__button-title {
    font-weight: 700;
    font-size: 0.82rem;
}

.settings-subnav__button-meta {
    font-size: 0.68rem;
    opacity: 0.65;
}

.settings-schools-wrap {
    width: 1000px;
    max-width: 100%;
}

.settings-empty-card {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.08);
    text-align: center;
    padding: 48px 24px !important;
}

.settings-empty-icon {
    margin-bottom: 16px;
}

.settings-empty-text {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.95rem;
}
</style>
