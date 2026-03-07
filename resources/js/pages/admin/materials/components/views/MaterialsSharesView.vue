<template>
    <v-card class="materials-shell pa-4 pa-md-6" rounded="xl" elevation="0">
        <div class="d-flex justify-space-between align-start flex-wrap ga-3 mb-4">
            <div>
                <div class="text-overline font-weight-bold text-medium-emphasis">Freigaben</div>
                <h2 class="text-h5 font-weight-bold mb-1">Freigaben-Übersicht</h2>
                <div class="text-subtitle-1 subline">Übersicht: Was ist freigegeben und für wen.</div>
            </div>
            <div class="d-flex align-center flex-wrap ga-2">
                <v-btn
                    flat
                    color="primary"
                    prepend-icon="mdi-refresh"
                    :loading="isLoading"
                    @click="loadShares">
                    Aktualisieren
                </v-btn>
            </div>
        </div>
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
                            <div class="d-flex flex-wrap ga-2">
                                <div
                                    v-for="target in row.targets"
                                    :key="`share-target-${row.id}-${target.id}`"
                                    class="share-target-pill d-flex align-center flex-wrap ga-1">
                                    <v-chip
                                        :color="targetChipColor(target)"
                                        variant="flat"
                                        size="x-small">
                                        {{ targetChipLabel(target) }}
                                    </v-chip>
                                    <v-menu location="bottom end">
                                        <template #activator="{ props: permissionMenuActivatorProps }">
                                            <v-chip
                                                v-bind="permissionMenuActivatorProps"
                                                :color="permissionColor(target.permission)"
                                                variant="tonal"
                                                size="x-small"
                                                append-icon="mdi-chevron-down"
                                                :disabled="isTargetBusy(target.id)">
                                                {{ permissionLabel(target.permission) }}
                                            </v-chip>
                                        </template>
                                        <v-list density="comfortable" style="min-width: 220px;">
                                            <v-list-subheader>Berechtigung ändern</v-list-subheader>
                                            <v-list-item
                                                v-for="option in permissionOptionsForScope(row.scope_type)"
                                                :key="`share-target-permission-${target.id}-${option.value}`"
                                                :active="normalizePermission(target.permission) === option.value"
                                                :disabled="isTargetBusy(target.id)"
                                                @click="updateTargetPermission(row, target, option.value)">
                                                <v-list-item-title>{{ option.label }}</v-list-item-title>
                                            </v-list-item>
                                        </v-list>
                                    </v-menu>
                                    <v-btn
                                        icon="mdi-delete-outline"
                                        size="x-small"
                                        color="warning"
                                        variant="text"
                                        :disabled="isTargetBusy(target.id)"
                                        :loading="isTargetBusy(target.id)"
                                        @click="removeTarget(row, target)" />
                                </div>
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
    </v-card>
</template>

<script>
import axios from 'axios'

export default {
    name: 'MaterialsSharesView',
    data() {
        return {
            isLoading: false,
            rows: [],
            needsMigration: false,
            errorMessage: '',
            statusBusyIds: [],
            targetBusyIds: [],
        }
    },
    computed: {
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
            if (target?.target_type === 'everyone') return 'success'
            if (target?.target_type === 'group') return 'primary'
            return 'secondary'
        },
        normalizePermission(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'full_access'
            if (normalized === 'read_write') return 'read_write'
            if (normalized === 'read_only') return 'read_only'
            return 'read_only'
        },
        permissionOptionsForScope() {
            return [
                { value: 'full_access', label: 'VOLLZUGRIFF' },
                { value: 'read_write', label: 'LESEN/SCHREIBEN' },
                { value: 'read_only', label: 'NUR LESEN' },
            ]
        },
        permissionLabel(permission) {
            const normalized = this.normalizePermission(permission)
            if (normalized === 'full_access') return 'VOLLZUGRIFF'
            if (normalized === 'read_write') return 'LESEN/SCHREIBEN'
            return 'NUR LESEN'
        },
        targetChipLabel(target) {
            const baseLabel = String(target?.label || '-').trim() || '-'
            const isUser = String(target?.target_type || '') === 'user'
            const isOtherSchoolUser = isUser && !!target?.meta?.is_other_school
            const schoolLabel = String(target?.meta?.school_label || '').trim()
            const email = String(target?.meta?.email || '').trim()
            const showEmail = isUser && email !== '' && email !== baseLabel
            let label = showEmail ? `${baseLabel} (${email})` : baseLabel
            if (isOtherSchoolUser && schoolLabel !== '') label += ` · ${schoolLabel}`
            return label
        },
        permissionRank(permission) {
            const normalized = this.normalizePermission(permission)
            if (normalized === 'full_access') return 3
            if (normalized === 'read_write') return 2
            if (normalized === 'read_only') return 1
            return 0
        },
        permissionColor(permission) {
            return ({ full_access: 'error', read_write: 'warning', read_only: 'primary' })[this.normalizePermission(permission)] || 'primary'
        },
        isStatusBusy(id) {
            return this.statusBusyIds.includes(Number(id))
        },
        isTargetBusy(id) {
            return this.targetBusyIds.includes(Number(id))
        },
        pushStatusBusy(id) {
            const normalized = Number(id)
            if (!Number.isFinite(normalized)) return
            if (!this.statusBusyIds.includes(normalized)) {
                this.statusBusyIds = [...this.statusBusyIds, normalized]
            }
        },
        pushTargetBusy(id) {
            const normalized = Number(id)
            if (!Number.isFinite(normalized)) return
            if (!this.targetBusyIds.includes(normalized)) {
                this.targetBusyIds = [...this.targetBusyIds, normalized]
            }
        },
        popStatusBusy(id) {
            const normalized = Number(id)
            this.statusBusyIds = this.statusBusyIds.filter((entry) => entry !== normalized)
        },
        popTargetBusy(id) {
            const normalized = Number(id)
            this.targetBusyIds = this.targetBusyIds.filter((entry) => entry !== normalized)
        },
        replaceRuleInCollections(updatedRule) {
            const ruleId = Number(updatedRule?.id || 0)
            if (ruleId <= 0) return
            const mergeRule = (entry) => (Number(entry?.id || 0) === ruleId ? updatedRule : entry)
            this.rows = this.rows.map(mergeRule)
        },
        removeTargetFromCollections(ruleId, targetId) {
            const pruneRules = (entries) =>
                (Array.isArray(entries) ? entries : [])
                    .map((entry) => {
                        if (Number(entry?.id || 0) !== ruleId) {
                            return entry
                        }
                        const nextTargets = Array.isArray(entry?.targets)
                            ? entry.targets.filter((entryTarget) => Number(entryTarget?.id || 0) !== targetId)
                            : []
                        return {
                            ...entry,
                            targets: nextTargets,
                            targets_count: nextTargets.length,
                        }
                    })
                    .filter((entry) => {
                        if (Number(entry?.id || 0) !== ruleId) {
                            return true
                        }
                        return Array.isArray(entry?.targets) && entry.targets.length > 0
                    })

            this.rows = pruneRules(this.rows)
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
                    this.replaceRuleInCollections(updated)
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
        async updateTargetPermission(row, target, nextPermission) {
            const ruleId = Number(row?.id || 0)
            const targetId = Number(target?.id || 0)
            const permission = this.normalizePermission(nextPermission)
            const currentPermission = this.normalizePermission(target?.permission)
            if (ruleId <= 0 || targetId <= 0) return
            if (permission === currentPermission) return

            this.pushTargetBusy(targetId)
            this.errorMessage = ''

            try {
                const response = await axios.patch(`/api/admin/materials/shares/targets/${targetId}`, {
                    permission,
                })
                const updatedRule = response.data?.rule
                if (updatedRule && Number(updatedRule?.id || 0) === ruleId) {
                    this.replaceRuleInCollections(updatedRule)
                    return
                }

                this.rows = this.rows.map((entry) => {
                    if (Number(entry?.id || 0) !== ruleId) return entry
                    const targets = Array.isArray(entry?.targets)
                        ? entry.targets.map((entryTarget) =>
                              Number(entryTarget?.id || 0) === targetId
                                  ? {
                                        ...entryTarget,
                                        permission,
                                        permission_label: this.permissionLabel(permission),
                                    }
                                  : entryTarget
                          )
                        : []
                    return { ...entry, targets }
                })
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Berechtigung konnte nicht gespeichert werden.'
            } finally {
                this.popTargetBusy(targetId)
            }
        },
        async removeTarget(row, target) {
            const ruleId = Number(row?.id || 0)
            const targetId = Number(target?.id || 0)
            if (ruleId <= 0 || targetId <= 0) return

            this.pushTargetBusy(targetId)
            this.errorMessage = ''
            try {
                await axios.delete(`/api/admin/materials/shares/targets/${targetId}`)
                this.removeTargetFromCollections(ruleId, targetId)
            } catch (error) {
                this.errorMessage = error?.response?.data?.message || 'Freigabe konnte nicht entfernt werden.'
            } finally {
                this.popTargetBusy(targetId)
            }
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
