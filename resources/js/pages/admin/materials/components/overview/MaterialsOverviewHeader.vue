<template>
    <v-row align="center" class="mb-4">
        <v-col cols="12" lg="5">
            <div class="text-h4 font-weight-bold mb-2">Übersicht</div>
        </v-col>

        <v-col v-if="!strukturModus" cols="12" lg="7">
            <div class="materials-overview-header-actions">
                <div
                    v-if="!hideOverviewModeToggle"
                    class="overview-mode-switcher"
                    role="tablist"
                    aria-label="Ansicht wählen">
                    <button
                        v-for="mode in modes"
                        :key="mode.value"
                        type="button"
                        role="tab"
                        :aria-selected="overviewViewMode === mode.value"
                        :class="['overview-mode-switcher__item', { 'is-active': overviewViewMode === mode.value }]"
                        :title="mode.label"
                        @click="$emit('update:overviewViewMode', mode.value)">
                        <v-icon :icon="mode.icon" size="20" class="overview-mode-switcher__icon" />
                        <span class="overview-mode-switcher__label">{{ mode.label }}</span>
                    </button>
                </div>

                <v-btn
                    prepend-icon="mdi-refresh"
                    color="primary"
                    variant="flat"
                    rounded="lg"
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
    data() {
        return {
            modes: [
                { value: 'list', label: 'Liste', icon: 'mdi-format-list-bulleted' },
                { value: 'grid', label: 'Karten', icon: 'mdi-view-grid-outline' },
                { value: 'alpha', label: 'A-Z', icon: 'mdi-sort-alphabetical-ascending' },
                { value: 'subjects_contents', label: 'Fächer/Inhalte', icon: 'mdi-file-tree-outline' },
            ],
        }
    },
}
</script>

<style scoped>
.materials-overview-header-actions {
    display: flex;
    align-items: stretch;
    flex-wrap: wrap;
    gap: 10px;
    width: 100%;
    min-width: 0;
}

.overview-mode-switcher {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 4px;
    flex: 1 1 0;
    min-width: 0;
    padding: 4px;
    background: rgba(var(--v-theme-surface-variant), 0.45);
    border: 1px solid rgba(var(--v-theme-primary), 0.15);
    border-radius: 12px;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.overview-mode-switcher__item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 0;
    min-height: 40px;
    padding: 6px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: rgba(var(--v-theme-on-surface), 0.78);
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.overview-mode-switcher__item:hover {
    background: rgba(var(--v-theme-primary), 0.08);
    color: rgb(var(--v-theme-primary));
}

.overview-mode-switcher__item:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
}

.overview-mode-switcher__item.is-active {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    box-shadow: 0 2px 6px rgba(var(--v-theme-primary), 0.35);
}

.overview-mode-switcher__item.is-active:hover {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
}

.overview-mode-switcher__icon {
    flex-shrink: 0;
}

.overview-mode-switcher__label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-overview-header-refresh-btn {
    flex: 0 0 auto;
    height: auto;
    min-height: 48px;
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

@media (max-width: 1279px) {
    .overview-mode-switcher {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 599px) {
    .overview-mode-switcher__label {
        display: none;
    }

    .overview-mode-switcher {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .overview-mode-switcher__item {
        padding: 6px;
    }

    .materials-overview-header-refresh-btn {
        width: 100%;
    }
}
</style>
