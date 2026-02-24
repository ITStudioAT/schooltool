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
                            </td>
                            <td>
                                <div class="d-flex flex-wrap ga-1">
                                    <v-chip
                                        v-for="target in row.targets"
                                        :key="`share-target-${row.id}-${target.id}`"
                                        :color="targetChipColor(target)"
                                        variant="flat"
                                        size="x-small">
                                        {{ targetChipLabel(target) }}
                                    </v-chip>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-center ga-2">
                                    <v-switch
                                        :model-value="!!row.is_active"
                                        color="success"
                                        density="compact"
                                        hide-details
                                        inset
                                        :disabled="isStatusBusy(row.id)"
                                        :loading="isStatusBusy(row.id)"
                                        @update:modelValue="updateRuleActive(row, $event)" />
                                    <v-chip
                                        :color="row.is_active ? 'success' : 'secondary'"
                                        variant="flat"
                                        size="x-small">
                                        {{ row.is_active ? 'aktiv' : 'inaktiv' }}
                                    </v-chip>
                                </div>
                            </td>
                            <td>
                                <span class="text-body-2">{{ formatDateTime(row.updated_at) }}</span>
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </div>
        </template>

        <template v-else>
            <v-card class="mb-4" rounded="xl" elevation="0" border>
                <v-card-text class="d-flex justify-space-between align-center flex-wrap ga-3">
                    <div>
                        <div class="d-flex align-center ga-2 flex-wrap">
                            <div class="text-subtitle-2">Gesamten Workspace freigeben</div>
                            <v-icon
                                v-if="workspaceShareIndicatorColor"
                                icon="mdi-share-variant"
                                :color="workspaceShareIndicatorColor"
                                size="18" />
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            Freigabe für alle Materialien im Workspace (Ebene: Alles) verwalten.
                        </div>
                    </div>
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-share-variant"
                        :disabled="needsMigration"
                        @click="openWorkspaceShareDialog">
                        Workspace freigeben
                    </v-btn>
                </v-card-text>
            </v-card>

            <MaterialsOverviewView
                forced-overview-mode="subjects_contents"
                :hide-overview-mode-toggle="true"
                :hide-subjects-overview-print-button="true"
                :read-only-material-actions="true"
                :enable-share-buttons="true" />

            <MaterialShareDialog
                v-model="workspaceShareDialogOpen"
                :target="workspaceShareTarget"
                :assignments="workspaceShareAssignments"
                :loading="workspaceShareAssignmentsLoading"
                :error="workspaceShareAssignmentsError"
                @reload-assignments="loadWorkspaceShareAssignments"
                @shares-changed="handleWorkspaceSharesChanged" />
        </template>
    </v-card>
</template>

<script>
import axios from 'axios'
import MaterialsOverviewView from './MaterialsOverviewView.vue'
import MaterialShareDialog from '../overview/dialogs/MaterialShareDialog.vue'

export default {
    name: 'MaterialsSharesView',
    components: {
        MaterialsOverviewView,
        MaterialShareDialog,
    },
    data() {
        return {
            activeSubmenu: 'overview',
            isLoading: false,
            rows: [],
            needsMigration: false,
            errorMessage: '',
            statusBusyIds: [],
            workspaceShareDialogOpen: false,
            workspaceShareAssignmentsLoading: false,
            workspaceShareAssignmentsError: '',
            workspaceShareAssignments: [],
        }
    },
    computed: {
        workspaceShareTarget() {
            return {
                level: 'all',
                id: null,
                label: 'Gesamter Workspace',
                parentLabel: '',
            }
        },
        workspaceShareIndicatorColor() {
            const rows = Array.isArray(this.rows) ? this.rows : []
            const workspaceRows = rows.filter((row) => String(row?.scope_type || '').trim() === 'all')
            let bestRank = 0
            let bestColor = ''

            for (const row of workspaceRows) {
                const targets = Array.isArray(row?.targets) ? row.targets : []
                for (const target of targets) {
                    const permission = String(target?.permission || '').trim()
                    const rank = this.permissionRank(permission)
                    if (rank > bestRank) {
                        bestRank = rank
                        bestColor = this.permissionColor(permission)
                    }
                }
            }

            return bestColor
        },
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
            const byPermission = ({ full_access: 'error', read_write: 'warning', read_only: 'primary' })[String(target?.permission || '').trim()]
            if (byPermission) return byPermission
            if (target?.target_type === 'everyone') return 'success'
            if (target?.target_type === 'group') return 'primary'
            return 'secondary'
        },
        targetChipLabel(target) {
            const baseLabel = String(target?.label || '-').trim() || '-'
            const isOtherSchoolUser = String(target?.target_type || '') === 'user' && !!target?.meta?.is_other_school
            const schoolLabel = String(target?.meta?.school_label || '').trim()
            if (!isOtherSchoolUser || schoolLabel === '') return baseLabel
            return `${baseLabel} · ${schoolLabel}`
        },
        permissionRank(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 3
            if (normalized === 'read_write') return 2
            if (normalized === 'read_only') return 1
            return 0
        },
        permissionColor(permission) {
            return ({ full_access: 'error', read_write: 'warning', read_only: 'primary' })[String(permission || '').trim()] || ''
        },
        isStatusBusy(id) {
            return this.statusBusyIds.includes(Number(id))
        },
        pushStatusBusy(id) {
            const normalized = Number(id)
            if (!Number.isFinite(normalized)) return
            if (!this.statusBusyIds.includes(normalized)) {
                this.statusBusyIds = [...this.statusBusyIds, normalized]
            }
        },
        popStatusBusy(id) {
            const normalized = Number(id)
            this.statusBusyIds = this.statusBusyIds.filter((entry) => entry !== normalized)
        },
        async updateRuleActive(row, nextValue) {
            const ruleId = Number(row?.id || 0)
            if (ruleId <= 0) return

            this.pushStatusBusy(ruleId)
            this.errorMessage = ''
            try {
                const response = await axios.patch(`/api/admin/materials/shares/${ruleId}`, {
                    is_active: !!nextValue,
                })
                const updated = response.data?.rule
                if (updated && Number(updated.id || 0) === ruleId) {
                    this.rows = this.rows.map((entry) => (Number(entry.id || 0) === ruleId ? updated : entry))
                } else {
                    this.rows = this.rows.map((entry) =>
                        Number(entry.id || 0) === ruleId
                            ? { ...entry, is_active: !!nextValue }
                            : entry
                    )
                }
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Freigabe-Status konnte nicht gespeichert werden.'
            } finally {
                this.popStatusBusy(ruleId)
            }
        },
        openWorkspaceShareDialog() {
            this.workspaceShareAssignments = []
            this.workspaceShareAssignmentsError = ''
            this.workspaceShareDialogOpen = true
            this.loadWorkspaceShareAssignments()
        },
        async loadWorkspaceShareAssignments() {
            this.workspaceShareAssignmentsLoading = true
            this.workspaceShareAssignmentsError = ''
            try {
                const response = await axios.get('/api/admin/materials/shares', {
                    params: { scope_type: 'all' },
                })
                this.workspaceShareAssignments = Array.isArray(response.data?.data) ? response.data.data : []
            } catch (error) {
                this.workspaceShareAssignments = []
                this.workspaceShareAssignmentsError = error?.response?.data?.message || 'Workspace-Freigaben konnten nicht geladen werden.'
            } finally {
                this.workspaceShareAssignmentsLoading = false
            }
        },
        handleWorkspaceSharesChanged() {
            this.loadWorkspaceShareAssignments()
            this.loadShares()
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
