<template>
    <v-col cols="12">
        <v-card rounded="lg" border class="st-evaluation-settings-card">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-tune-variant" />
                Einstellungen
            </v-card-title>

            <v-card-text>
                <v-alert type="info" variant="tonal" density="comfortable" class="mb-0">
                    Noch keine Bewertungseinstellungen definiert.
                </v-alert>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
export default {
    name: 'EvaluationSettings',
}
</script>

<style scoped>
.st-evaluation-settings-card {
    max-width: 960px;
}
</style>
