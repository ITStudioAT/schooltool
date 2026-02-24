<template>
    <v-row align="center" class="mb-4">
        <v-col cols="12" md="8">
            <div class="text-h4 font-weight-bold mb-2">Übersicht</div>
        </v-col>

        <v-col cols="12" md="4" class="d-flex justify-md-end align-center flex-wrap ga-2">
            <v-btn-toggle
                v-if="!hideOverviewModeToggle"
                :model-value="overviewViewMode"
                mandatory
                color="primary"
                variant="tonal"
                density="comfortable"
                class="overview-mode-toggle"
                @update:modelValue="$emit('update:overviewViewMode', $event)">
                <v-btn value="list" prepend-icon="mdi-format-list-bulleted">
                    Liste
                </v-btn>
                <v-btn value="grid" prepend-icon="mdi-view-grid-outline">
                    Karten
                </v-btn>
                <v-btn value="alpha" prepend-icon="mdi-sort-alphabetical-ascending">
                    A-Z
                </v-btn>
                <v-btn value="subjects_contents" prepend-icon="mdi-file-tree-outline">
                    Fächer/Inhalte
                </v-btn>
            </v-btn-toggle>

            <v-btn
                prepend-icon="mdi-refresh"
                color="primary"
                variant="flat"
                :loading="isLoading"
                :disabled="actionDisabled"
                @click="$emit('refresh')">
                Aktualisieren
            </v-btn>
        </v-col>
    </v-row>
</template>

<script>
export default {
    name: 'MaterialsOverviewHeader',
    props: {
        hideOverviewModeToggle: {
            type: Boolean,
            default: false,
        },
        overviewViewMode: {
            type: String,
            required: true,
        },
        isLoading: {
            type: Boolean,
            default: false,
        },
        actionDisabled: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:overviewViewMode', 'refresh'],
}
</script>

<style scoped>
.overview-mode-toggle {
    max-width: 100%;
}

@media (max-width: 959px) {
    .overview-mode-toggle {
        width: 100%;
    }

    .overview-mode-toggle :deep(.v-btn) {
        flex: 1 1 0;
    }
}
</style>
