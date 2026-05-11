<template>
    <v-col cols="12" md="6" lg="7" xl="4">
        <v-card rounded="lg" border>
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-view-dashboard-outline" />
                Übersicht
            </v-card-title>
            <v-card-text>
                <v-alert type="info" variant="tonal">
                    Stundenplan-Übersicht wird vorbereitet.
                </v-alert>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
export default {
    name: 'StudentsTimetablesOverview',
}
</script>
