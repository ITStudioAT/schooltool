<template>
    <v-container v-if="canAccessGroups" fluid class="group-administration ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Verwaltung"
            title="Gruppen"
            :active-section="activeSection"
            :show-current-user-chip="true" />

        <v-sheet class="group-administration-nav mb-2">
            <v-btn-toggle v-model="selectedPanel" mandatory divided color="primary" class="group-administration-panels">
                <v-btn
                    v-for="panel in panels"
                    :key="panel.key"
                    :value="panel.key"
                    :prepend-icon="panel.icon"
                    :aria-pressed="selectedPanel === panel.key"
                    class="group-administration-button">
                    <span class="group-administration-button-copy">
                        <span>{{ panel.label }}</span>
                        <span class="group-administration-button-meta">{{ panel.meta }}</span>
                    </span>
                </v-btn>
            </v-btn-toggle>
        </v-sheet>

        <Groups :embedded="true" :embedded-filter="selectedPanel === 'groups_own' ? 'own' : ''" />
    </v-container>
</template>

<script>
import { mapState } from 'pinia'
import { defineAsyncComponent } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

const Groups = defineAsyncComponent(() => import('./Groups.vue'))

export default {
    components: { AdminSectionHero, Groups },

    computed: {
        ...mapState(useAdminStore, ['config']),
        canAccessGroups() {
            return Array.isArray(this.config?.roles) && this.config.roles.includes('super_admin')
        },
        panels() {
            return [
                { key: 'groups_overview', label: 'Überblick', meta: 'Alle Gruppentypen', icon: 'mdi-view-dashboard-outline' },
                { key: 'groups_own', label: 'Eigene Gruppen', meta: 'Verwalten', icon: 'mdi-account-multiple-outline' },
            ]
        },
        selectedPanel: {
            get() {
                return this.$route.query?.panel === 'groups_own' ? 'groups_own' : 'groups_overview'
            },
            set(panel) {
                if (!this.canAccessGroups || !this.panels.some((item) => item.key === panel)) {
                    return
                }

                this.$router.replace({ path: '/admin/groups', query: { panel } })
            },
        },
        activeSection() {
            return this.panels.find((panel) => panel.key === this.selectedPanel)
        },
    },

    watch: {
        canAccessGroups: {
            immediate: true,
            handler(canAccess) {
                if (!canAccess) {
                    this.$router.replace('/admin')
                }
            },
        },
    },
}
</script>

<style scoped>
.group-administration {
    background: #0f172a;
    min-height: 100vh;
}

.group-administration-nav {
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.group-administration-panels {
    width: 100%;
    flex-wrap: wrap;
    height: auto !important;
    row-gap: 6px;
}

.group-administration-button {
    min-height: 56px !important;
    height: auto !important;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.group-administration-button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.group-administration-button-meta {
    color: rgba(255, 255, 255, 0.98);
    font-size: 0.76rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 2px 8px;
}
</style>
