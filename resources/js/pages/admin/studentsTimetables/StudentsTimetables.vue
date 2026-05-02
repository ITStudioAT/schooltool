<template>
    <div class="students-timetables-page">
        <AdminSectionHero
            eyebrow="StudentsTimetables"
            title="Schülerstundenpläne"
            :active-section="{
                icon: 'mdi-calendar-clock',
                label: 'Dummy-Modul',
                note: 'Vorbereitung für schulspezifische Stundenplan-Lösungen',
            }" />

        <v-container fluid class="pa-4">
            <v-row>
                <v-col cols="12" md="8" lg="6">
                    <v-card rounded="lg" border>
                        <v-card-title class="d-flex align-center ga-2">
                            <v-icon icon="mdi-tools" />
                            StudentsTimetables
                        </v-card-title>
                        <v-card-text>
                            <v-alert type="info" variant="tonal" class="mb-4">
                                Dieses Modul ist vorbereitet und liefert aktuell nur Dummy-Daten.
                            </v-alert>

                            <v-list density="comfortable">
                                <v-list-item title="Status" :subtitle="dashboard?.status || 'Lade...'" />
                                <v-list-item title="Schule" :subtitle="dashboard?.school?.name || '-'" />
                                <v-list-item title="Einträge" :subtitle="String(dashboard?.items?.length || 0)" />
                            </v-list>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script>
import axios from 'axios'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

export default {
    components: {
        AdminSectionHero,
    },
    data() {
        return {
            dashboard: null,
        }
    },
    async mounted() {
        const response = await axios.get('/api/admin/students-timetables')
        this.dashboard = response.data.data
    },
}
</script>
