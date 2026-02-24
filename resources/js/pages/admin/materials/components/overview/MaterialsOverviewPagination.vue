<template>
    <div class="overview-pagination d-flex flex-wrap align-center justify-end ga-2 mt-2">
        <div class="text-caption text-medium-emphasis page-indicator">
            Seite {{ currentPage }} von {{ lastPage }}
        </div>

        <v-btn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-page-first"
            :disabled="actionDisabled || !hasPreviousPage"
            @click="$emit('first')">
            Erste
        </v-btn>

        <v-btn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-chevron-left"
            :disabled="actionDisabled || !hasPreviousPage"
            @click="$emit('previous')">
            Zurück
        </v-btn>

        <v-btn
            size="small"
            variant="tonal"
            color="primary"
            append-icon="mdi-chevron-right"
            :disabled="actionDisabled || !hasNextPage"
            @click="$emit('next')">
            Weiter
        </v-btn>

        <v-btn
            size="small"
            variant="tonal"
            color="primary"
            append-icon="mdi-page-last"
            :disabled="actionDisabled || !hasNextPage"
            @click="$emit('last')">
            Letzte
        </v-btn>
    </div>
</template>

<script>
export default {
    name: 'MaterialsOverviewPagination',
    props: {
        currentPage: { type: Number, required: true },
        lastPage: { type: Number, required: true },
        hasPreviousPage: { type: Boolean, default: false },
        hasNextPage: { type: Boolean, default: false },
        actionDisabled: { type: Boolean, default: false },
    },
    emits: ['first', 'previous', 'next', 'last'],
}
</script>

<style scoped>
.overview-pagination {
    width: 100%;
}

.page-indicator {
    margin-right: 4px;
}

@media (max-width: 959px) {
    .overview-pagination {
        justify-content: stretch;
    }

    .overview-pagination :deep(.v-btn) {
        flex: 1 1 calc(50% - 8px);
    }
}
</style>
