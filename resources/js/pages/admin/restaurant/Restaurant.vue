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

        <v-sheet rounded="xl" class="restaurant-content pa-6">
            <div class="d-flex align-center ga-3 mb-4">
                <v-icon size="32" color="warning">mdi-silverware-fork-knife</v-icon>
                <div>
                    <div class="text-h6 font-weight-bold">Restaurant</div>
                    <div class="text-body-2 text-medium-emphasis">Internes Mittagessen – wird noch entwickelt</div>
                </div>
            </div>
            <v-alert type="info" variant="tonal" rounded="lg">
                Diese Funktion ist in Vorbereitung. Der Bereich für das interne Mittagessen (Restaurant) wird hier verfügbar sein.
            </v-alert>
        </v-sheet>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

export default {
    components: { AdminSectionHero },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    data() {
        return {
            adminStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule gewählt'
        },
        headerChips() {
            return [
                {
                    key: 'school',
                    text: this.selectedSchoolLabel,
                    icon: 'mdi-domain',
                },
            ]
        },
        activeSection() {
            return {
                label: 'Restaurant',
                icon: 'mdi-silverware-fork-knife',
                note: 'Internes Mittagessen.',
            }
        },
    },
}
</script>

<style scoped>
.restaurant-page {
    background: #0f172a;
    min-height: 100vh;
}

.restaurant-content {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    max-width: 800px;
}
</style>
