<template>
    <v-container fluid class="aba-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern"
            title="ABA"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#1d4ed8"
            right-orb-color="#93c5fd" />

        <v-sheet rounded="xl" class="aba-nav mb-2" :class="{ 'is-locked': isNavigationLocked }">
            <div class="aba-nav__buttons">
                <v-btn
                    v-for="item in navigationItems"
                    :key="item.key"
                    rounded="xl"
                    :color="item.key === 'overview' ? 'primary' : 'secondary'"
                    :variant="item.key === 'overview' ? 'flat' : 'tonal'"
                    class="aba-nav__button"
                    :disabled="isNavigationLocked"
                    @click="navigateTo(item.key)">
                    <v-icon size="18" :icon="item.icon" class="mr-2" />
                    <span class="aba-nav__button-copy">
                        <span class="aba-nav__button-title">{{ item.label }}</span>
                        <span class="aba-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
                <v-spacer />
                <v-btn
                    size="small"
                    variant="outlined"
                    color="white"
                    prepend-icon="mdi-refresh"
                    :disabled="isNavigationLocked"
                    @click="refreshPage">
                    Aktualisieren
                </v-btn>
            </div>
            <v-progress-linear
                v-if="isRefreshing"
                indeterminate
                color="primary"
                class="aba-nav__progress" />
        </v-sheet>

        <v-row class="w-100 ma-0" dense>
            <Overview ref="overview" :is-refreshing="isRefreshing" />
        </v-row>

        <v-dialog v-model="schoolyearDialogOpen" persistent max-width="620">
            <v-card>
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                    <v-icon size="18">mdi-calendar-month-outline</v-icon>
                    Schuljahr wechseln
                    <v-spacer />
                    <v-chip size="x-small" color="primary" variant="tonal">{{ currentSchoolyearLabel }}</v-chip>
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="text-body-2 text-medium-emphasis mb-3">
                        Wählen Sie das aktive Schuljahr für Ihren ABA-Bereich.
                    </div>

                    <v-select
                        v-model="schoolyearDialogSelection"
                        :items="availableSchoolyears"
                        item-title="name"
                        item-value="id"
                        label="Schuljahr"
                        density="comfortable"
                        variant="outlined"
                        :loading="schoolyearDialogLoading"
                        :disabled="schoolyearDialogLoading || schoolyearSaveLoading" />
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn
                        variant="tonal"
                        color="warning"
                        :disabled="schoolyearSaveLoading"
                        @click="cancelSchoolyearSwitch">
                        Abbrechen
                    </v-btn>
                    <v-spacer />
                    <v-btn
                        variant="flat"
                        color="primary"
                        :loading="schoolyearSaveLoading"
                        :disabled="!schoolyearDialogSelection || schoolyearDialogLoading"
                        @click="confirmSchoolyearSwitch">
                        Bestätigen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import Overview from '@/pages/admin/aba/components/Overview.vue'

export default {
    components: { AdminSectionHero, Overview },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    data() {
        return {
            adminStore: null,
            isRefreshing: false,
            schoolyearDialogOpen: false,
            schoolyearDialogLoading: false,
            schoolyearSaveLoading: false,
            schoolyearDialogSelection: null,
            availableSchoolyears: [],
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'action_2']),
        isNavigationLocked() {
            return this.action != '' || this.action_2 != '' || this.isRefreshing
        },
        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule gewählt'
        },
        selectedRoleLabel() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            if (!roles.length) {
                return 'Keine Rolle'
            }
            return roles.slice(0, 2).join(' / ')
        },
        currentSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || 'Kein Schuljahr'
        },
        selectedSchoolyearId() {
            return this.config?.selected_schoolyear?.id || null
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
        activeSection() {
            return {
                label: 'Überblick',
                icon: 'mdi-view-dashboard-outline',
                note: 'Aktuelle ABA-Hauptansicht.',
            }
        },
        navigationItems() {
            return [
                {
                    key: 'overview',
                    label: 'Überblick',
                    meta: 'Meine ABAs',
                    icon: 'mdi-view-dashboard-outline',
                },
                {
                    key: 'schoolyear',
                    label: this.currentSchoolyearLabel,
                    meta: 'Aktives Schuljahr',
                    icon: 'mdi-calendar-month-outline',
                },
            ]
        },
    },

    methods: {
        async refreshPage() {
            this.isRefreshing = true
            try {
                await this.$refs.overview?.loadAbas()
            } finally {
                this.isRefreshing = false
            }
        },
        async navigateTo(target) {
            if (this.isNavigationLocked) {
                return
            }

            if (target === 'schoolyear') {
                await this.openSchoolyearDialog()
            }
        },
        async openSchoolyearDialog() {
            this.schoolyearDialogOpen = true
            this.schoolyearDialogSelection = this.selectedSchoolyearId
            await this.loadSchoolyearOptions()
        },
        async loadSchoolyearOptions() {
            this.schoolyearDialogLoading = true
            try {
                const response = await axios.get('/api/admin/aba/schoolyears')
                this.availableSchoolyears = Array.isArray(response.data) ? response.data : []
                if (!this.schoolyearDialogSelection) {
                    this.schoolyearDialogSelection = this.selectedSchoolyearId
                }
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Schuljahre konnten nicht geladen werden.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
                this.schoolyearDialogOpen = false
            } finally {
                this.schoolyearDialogLoading = false
            }
        },
        cancelSchoolyearSwitch() {
            this.schoolyearDialogSelection = this.selectedSchoolyearId
            this.schoolyearDialogOpen = false
        },
        async confirmSchoolyearSwitch() {
            if (!this.schoolyearDialogSelection) {
                return
            }

            this.schoolyearSaveLoading = true
            try {
                await axios.post('/api/admin/aba/schoolyears/set_active', {
                    schoolyear_id: this.schoolyearDialogSelection,
                })

                await this.adminStore.loadConfig()
                this.schoolyearDialogOpen = false

                useNotificationStore().notify({
                    message: 'Aktives Schuljahr wurde aktualisiert.',
                    type: 'success',
                    timeout: 3000,
                })
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Schuljahr konnte nicht gespeichert werden.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
            } finally {
                this.schoolyearSaveLoading = false
            }
        },
    },
}
</script>

<style scoped>
.aba-page {
    background: #0f172a;
    min-height: 100vh;
}

.aba-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    overflow: hidden;
}

.aba-nav__progress {
    margin: 8px -10px -10px;
    width: calc(100% + 20px);
}

.aba-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.aba-nav__button {
    min-height: 54px;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.aba-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.aba-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.aba-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.aba-nav.is-locked {
    opacity: 0.68;
}
</style>
