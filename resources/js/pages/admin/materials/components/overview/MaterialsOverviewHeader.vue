<template>
    <v-row align="center" class="mb-4">
        <v-col cols="12" md="8">
            <div class="text-h4 font-weight-bold mb-2">Übersicht</div>
        </v-col>

        <v-col v-if="!strukturModus" cols="12" md="4">
            <div class="materials-overview-header-actions d-flex align-center flex-nowrap ga-2 w-100">
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
                    class="materials-overview-header-refresh-btn"
                    :loading="isLoading"
                    :disabled="actionDisabled"
                    @click="$emit('refresh')">
                    Aktualisieren
                </v-btn>
            </div>
        </v-col>
    </v-row>

    <v-row class="materials-overview-header-row mb-4" align="center">
        <v-col cols="12" class="materials-overview-header-row__content">
            <slot />
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
        strukturModus: {
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
    min-width: 0;
    flex: 1 1 auto;
}

.materials-overview-header-actions {
    min-width: 0;
}

.materials-overview-header-row {
    background: rgba(var(--v-theme-info), 0.12);
    border-top: 2px solid rgba(var(--v-theme-info), 0.28);
    border-radius: 8px;
    padding: 2px 12px;
}

.materials-overview-header-row__content {
    min-height: 0;
}

.materials-overview-header-refresh-btn {
    flex-shrink: 0;
}

@media (max-width: 959px) {
    .materials-overview-header-actions {
        flex-wrap: wrap;
    }

    .overview-mode-toggle {
        width: 100%;
    }

    .overview-mode-toggle :deep(.v-btn) {
        flex: 1 1 0;
    }
}
</style>
