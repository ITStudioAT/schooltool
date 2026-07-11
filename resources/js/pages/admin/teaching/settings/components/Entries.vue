<template>
    <ItsGridBox
        variant="overview"
        color="primary"
        title="Einträge"
        icon="mdi-format-list-bulleted-type"
        class="w-100">
        <template #header-actions>
            <v-chip size="small" color="info" variant="tonal" prepend-icon="mdi-flask-outline">
                Version 2
            </v-chip>
        </template>

        <div class="d-flex flex-wrap align-center ga-2 mt-2">
            <v-chip size="small" color="primary" variant="tonal">
                Vorschau
            </v-chip>
            <span class="text-body-2 text-medium-emphasis">Noch ohne Speichern</span>
        </div>

        <v-tabs
            v-model="activeCategory"
            class="entry-settings-category-tabs"
            color="primary"
            density="comfortable"
            show-arrows>
            <v-tab
                v-for="category in categoryOptions"
                :key="category"
                :value="category">
                {{ category }}
            </v-tab>
        </v-tabs>

        <v-list density="compact" class="entry-settings-list bg-transparent">
            <v-list-item
                v-for="entry in filteredEntries"
                :key="entry.id"
                class="entry-settings-list-row px-0">
                <div class="entry-settings-list-content">
                    <div class="entry-settings-entry-text">
                        <span class="entry-settings-short-name">{{ entry.short_name }}</span>
                        <span class="entry-settings-name">{{ entry.name }}</span>
                    </div>
                    <v-btn
                        flat
                        tile
                        size="x-small"
                        color="primary"
                        icon="mdi-pencil"
                        :title="`${entry.short_name} bearbeiten`"
                        @click="openEditDialog(entry)" />
                </div>
            </v-list-item>
        </v-list>

        <v-alert v-if="!filteredEntries.length" type="info" variant="tonal" class="mt-3">
            Keine Einträge in dieser Kategorie.
        </v-alert>

        <v-dialog v-model="editDialogOpen" persistent max-width="520">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Eintrag bearbeiten</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeEditDialog" />
                </v-card-title>
                <v-card-text v-if="selectedEntry">
                    <div class="entry-settings-dialog-entry">
                        <span class="entry-settings-short-name">{{ selectedEntry.short_name }}</span>
                        <span class="entry-settings-name">{{ selectedEntry.name }}</span>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn color="primary" variant="text" @click="closeEditDialog">
                        Schliessen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </ItsGridBox>
</template>

<script>
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    data() {
        return {
            activeCategory: 'Benotung',
            categoryOptions: ['Benotung', 'Verhalten', 'Weitere'],
            editDialogOpen: false,
            entries: [
                {
                    id: 'participation',
                    short_name: 'M',
                    name: 'Mitarbeit',
                    category: 'Benotung',
                    has_properties: true,
                    properties_mode: 'fixed',
                    fixed_properties: ['+', '-'],
                },
                {
                    id: 'test',
                    short_name: 'T',
                    name: 'Test',
                    category: 'Benotung',
                    has_properties: true,
                    properties_mode: 'fixed',
                    fixed_properties: ['1', '2', '3', '4', '5'],
                },
                {
                    id: 'behaviour',
                    short_name: 'V',
                    name: 'Verhalten',
                    category: 'Verhalten',
                    has_properties: false,
                    properties_mode: 'free',
                    fixed_properties: [],
                },
                {
                    id: 'info',
                    short_name: 'I',
                    name: 'Weitere Information',
                    category: 'Weitere',
                    has_properties: true,
                    properties_mode: 'free',
                    fixed_properties: [],
                },
            ],
            selectedEntry: null,
        }
    },

    computed: {
        filteredEntries() {
            return this.entries.filter((entry) => entry.category === this.activeCategory)
        },
    },

    methods: {
        normalizeShortName(entryOrIndex) {
            const entry = typeof entryOrIndex === 'number' ? this.entries[entryOrIndex] : entryOrIndex
            if (!entry) {
                return
            }

            entry.short_name = String(entry.short_name || '').toUpperCase().slice(0, 2)
        },
        openEditDialog(entry) {
            this.selectedEntry = entry
            this.editDialogOpen = true
        },
        closeEditDialog() {
            this.editDialogOpen = false
            this.selectedEntry = null
        },
    },
}
</script>

<style scoped>
.entry-settings-category-tabs {
    flex-wrap: wrap;
    height: auto !important;
    margin-top: 2px;
}

.entry-settings-list {
    margin-top: 10px;
}

.entry-settings-list-row:not(:last-child) {
    border-bottom: 1px solid rgba(148, 163, 184, 0.26);
}

.entry-settings-list-content {
    align-items: center;
    display: flex;
    gap: 12px;
    justify-content: space-between;
    width: 100%;
}

.entry-settings-entry-text {
    align-items: center;
    display: flex;
    gap: 10px;
    min-width: 0;
}

.entry-settings-short-name {
    color: #1e3a8a;
    flex: 0 0 auto;
    font-size: 0.88rem;
    font-weight: 800;
    min-width: 34px;
}

.entry-settings-name {
    color: #172033;
    font-size: 0.94rem;
    font-weight: 500;
    overflow-wrap: anywhere;
}

.entry-settings-dialog-entry {
    align-items: center;
    display: flex;
    gap: 10px;
}
</style>
