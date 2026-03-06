<template>
    <v-card class="materials-shell pa-4 pa-md-6" rounded="xl" elevation="0">
        <div class="text-overline font-weight-bold text-medium-emphasis mb-2">Teilen</div>
        <h2 class="text-h5 font-weight-bold mb-2">Teilen (Dummy)</h2>
        <div class="text-subtitle-1 subline mb-4">
            Diese Seite ist ein Platzhalter für die neue Teilen-Umsetzung.
        </div>

        <div class="d-flex flex-wrap ga-2 mb-4">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-share-variant-outline">
                Teilen
            </v-btn>
            <v-btn color="secondary" variant="flat" prepend-icon="mdi-folder-multiple-outline">
                Geteiltes Materialien
            </v-btn>
        </div>

        <v-alert type="info" variant="tonal" rounded="lg">
            Hier entsteht der neue Freigabe-Bereich.
        </v-alert>
    </v-card>
</template>

<script>
export default {
    name: 'MaterialsFreigabeView',
}
</script>
