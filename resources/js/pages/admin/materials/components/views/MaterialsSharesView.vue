<template>
    <v-card class="materials-shell pa-4 pa-md-6" rounded="xl" elevation="0">
        <div class="d-flex justify-space-between align-start flex-wrap ga-3 mb-4">
            <div>
                <div class="text-overline font-weight-bold text-medium-emphasis">Freigaben</div>
                <h2 class="text-h5 font-weight-bold mb-1">
                    {{ activeSubmenu === 'overview' ? 'Freigaben-Übersicht' : 'Freigeben' }}
                </h2>
                <div class="text-subtitle-1 subline">
                    {{ activeSubmenu === 'overview' ? 'Übersicht: Was ist freigegeben und für wen.' : 'Neue Freigaben anlegen (Platzhalter).' }}
                </div>
            </div>
            <div class="d-flex align-center flex-wrap ga-2">
                <v-chip v-if="activeSubmenu === 'overview'" color="secondary" variant="flat" size="small">
                    {{ rows.length }} Einträge
                </v-chip>
                <v-btn
                    v-if="activeSubmenu === 'overview'"
                    flat
                    color="primary"
                    prepend-icon="mdi-refresh"
                    :loading="isLoading"
                    @click="loadShares">
                    Aktualisieren
                </v-btn>
            </div>
        </div>

        <div class="d-flex flex-wrap ga-2 mb-4">
            <v-btn
                flat
                rounded="pill"
                :color="activeSubmenu === 'overview' ? 'primary' : 'secondary'"
                prepend-icon="mdi-view-list-outline"
                @click="activeSubmenu = 'overview'">
                Übersicht
            </v-btn>
            <v-btn
                flat
                rounded="pill"
                :color="activeSubmenu === 'create' ? 'primary' : 'secondary'"
                prepend-icon="mdi-share-variant-outline"
                @click="activeSubmenu = 'create'">
                Freigeben
            </v-btn>
        </div>

        <template v-if="activeSubmenu === 'overview'">
            <v-alert
                v-if="needsMigration"
                type="warning"
                variant="flat"
                class="mb-4">
                Freigaben-Tabellen sind noch nicht vorhanden. Bitte Migration ausführen (`php artisan migrate`).
            </v-alert>

            <v-alert
                v-else-if="errorMessage"
                type="error"
                variant="flat"
                class="mb-4">
                {{ errorMessage }}
            </v-alert>

            <div v-if="isLoading && rows.length === 0" class="text-body-2 text-medium-emphasis py-6">
                Lade Freigaben ...
            </div>

            <div v-else-if="!needsMigration && rows.length === 0" class="text-body-2 text-medium-emphasis py-6">
                Noch keine Freigaben vorhanden.
            </div>

            <div v-else class="shares-table-wrap">
                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Ebene</th>
                            <th>Objekt</th>
                            <th>Freigegeben an</th>
                            <th>Status</th>
                            <th>Erstellt von</th>
                            <th>Aktualisiert</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="`share-row-${row.id}`">
                            <td>
                                <v-chip color="primary" variant="flat" size="x-small">
                                    {{ row.scope_label }}
                                </v-chip>
                            </td>
                            <td>
                                <div class="font-weight-medium">{{ row.scope_object_label }}</div>
                                <div class="text-caption text-medium-emphasis">#{{ row.id }}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap ga-1">
                                    <v-chip
                                        v-for="target in row.targets"
                                        :key="`share-target-${row.id}-${target.id}`"
                                        :color="targetChipColor(target)"
                                        variant="flat"
                                        size="x-small">
                                        {{ target.label }}
                                    </v-chip>
                                </div>
                            </td>
                            <td>
                                <v-chip
                                    :color="row.is_active ? 'success' : 'secondary'"
                                    variant="flat"
                                    size="x-small">
                                    {{ row.is_active ? 'aktiv' : 'inaktiv' }}
                                </v-chip>
                            </td>
                            <td>
                                <span class="text-body-2">{{ row.created_by_label || 'Unbekannt' }}</span>
                            </td>
                            <td>
                                <span class="text-body-2">{{ formatDateTime(row.updated_at) }}</span>
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </div>
        </template>

        <MaterialsOverviewView
            v-else
            forced-overview-mode="subjects_contents"
            :hide-overview-mode-toggle="true"
            :hide-subjects-overview-print-button="true"
            :read-only-material-actions="true" />
    </v-card>
</template>

<script>
import axios from 'axios'
import MaterialsOverviewView from './MaterialsOverviewView.vue'

export default {
    name: 'MaterialsSharesView',
    components: {
        MaterialsOverviewView,
    },
    data() {
        return {
            activeSubmenu: 'overview',
            isLoading: false,
            rows: [],
            needsMigration: false,
            errorMessage: '',
        }
    },
    mounted() {
        this.loadShares()
    },
    methods: {
        async loadShares() {
            this.isLoading = true
            this.errorMessage = ''
            try {
                const response = await axios.get('/api/admin/materials/shares')
                this.rows = Array.isArray(response.data?.data) ? response.data.data : []
                this.needsMigration = !!response.data?.meta?.needs_migration
            } catch (error) {
                this.rows = []
                this.needsMigration = false
                this.errorMessage = error?.response?.data?.message || 'Freigaben konnten nicht geladen werden.'
            } finally {
                this.isLoading = false
            }
        },
        formatDateTime(value) {
            if (!value) return '-'
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return '-'
            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date)
        },
        targetChipColor(target) {
            if (target?.target_type === 'everyone') return 'success'
            if (target?.target_type === 'group') return 'primary'
            return 'secondary'
        },
    },
}
</script>

<style scoped>
.shares-table-wrap {
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(253, 128, 46, 0.2);
    background: rgba(255, 255, 255, 0.62);
}
</style>
