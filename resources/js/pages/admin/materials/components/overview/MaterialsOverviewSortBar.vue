<template>
    <div class="d-flex flex-wrap align-center ga-2 mb-4">
        <div class="text-caption text-medium-emphasis">Sortierung:</div>
        <v-btn-toggle
            :model-value="overviewSortMode"
            mandatory
            color="primary"
            variant="tonal"
            density="comfortable"
            class="overview-sort-toggle"
            @update:modelValue="$emit('update:overviewSortMode', $event)">
            <v-btn value="date" prepend-icon="mdi-calendar-clock">
                Datum
            </v-btn>
            <v-btn value="name" prepend-icon="mdi-sort-alphabetical-ascending">
                Name
            </v-btn>
        </v-btn-toggle>
    </div>
</template>

<script>
export default {
    name: 'MaterialsOverviewSortBar',
    props: {
        overviewSortMode: {
            type: String,
            required: true,
        },
    },
    emits: ['update:overviewSortMode'],
}
</script>

<style scoped>
.overview-sort-toggle {
    max-width: 100%;
}

@media (max-width: 959px) {
    .overview-sort-toggle {
        width: 100%;
    }

    .overview-sort-toggle :deep(.v-btn) {
        flex: 1 1 0;
    }
}
</style>
