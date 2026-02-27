<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <div class="d-flex justify-space-between align-start flex-wrap ga-3 mb-4">
            <div>
                <div class="text-h4 font-weight-bold mb-2">Inbox</div>
                <div class="text-subtitle-1 subline">Benutzer, die etwas mit dir geteilt haben.</div>
            </div>
            <div class="d-flex align-center flex-wrap ga-2">
                <v-btn
                    flat
                    color="primary"
                    prepend-icon="mdi-refresh"
                    :loading="isLoading"
                    @click="loadInboxUsers">
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

        <div v-if="isLoading" class="text-body-2 text-medium-emphasis py-4">
            Lade Inbox ...
        </div>

        <v-card v-else-if="users.length === 0" variant="outlined" class="pa-4">
            <div class="text-body-2 text-medium-emphasis">
                Noch keine eingehenden Freigaben gefunden.
            </div>
        </v-card>

        <v-card v-else variant="outlined" class="pa-0">
            <v-list lines="two">
                <v-list-item
                    v-for="user in users"
                    :key="`inbox-user-${user.id}`">
                    <v-list-item-title class="font-weight-medium">
                        {{ user.label }}
                        <span v-if="user.school_label"> · {{ user.school_label }}</span>
                    </v-list-item-title>
                    <v-list-item-subtitle>
                        {{ user.email || 'ohne E-Mail' }}
                    </v-list-item-subtitle>
                    <v-expansion-panels v-if="user.shared_items.length > 0" class="inbox-shared-panels">
                        <v-expansion-panel>
                            <v-expansion-panel-title>
                                Anzeigen, was geteilt wurde ({{ user.shared_items.length }})
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <div class="inbox-shared-list">
                                    <div
                                        v-for="item in user.shared_items"
                                        :key="`inbox-user-${user.id}-rule-${item.rule_id}`"
                                        class="inbox-shared-object-card">
                                        <div class="inbox-shared-object-head">
                                            <div class="inbox-shared-object-head-top">
                                                <div class="inbox-shared-object-scope">
                                                    {{ item.scope_label }}
                                                </div>
                                                <v-chip
                                                    size="x-small"
                                                    variant="flat"
                                                    :color="permissionChipColor(item.permission)">
                                                    {{ item.permission_label }}
                                                </v-chip>
                                            </div>
                                            <div class="inbox-shared-object-title">
                                                {{ item.scope_object_label }}
                                            </div>
                                            <div class="inbox-shared-object-path">
                                                {{ item.scope_path_label }}
                                            </div>
                                            <div v-if="item.updated_at" class="text-caption text-medium-emphasis">
                                                Aktualisiert: {{ formatDateTime(item.updated_at) }}
                                            </div>
                                        </div>
                                        <div class="inbox-shared-object-actions">
                                            <v-btn size="small" variant="tonal" color="primary" @click.stop="onDummyObjectAction(item, 'open')">
                                                Öffnen
                                            </v-btn>
                                            <v-btn size="small" variant="tonal" color="warning" @click.stop="onDummyObjectAction(item, 'bookmark')">
                                                Merken
                                            </v-btn>
                                            <v-btn size="small" variant="tonal" color="secondary" @click.stop="onDummyObjectAction(item, 'more')">
                                                Mehr
                                            </v-btn>
                                        </div>
                                    </div>
                                </div>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                    <template #append>
                        <v-chip color="primary" variant="tonal" size="small">
                            {{ user.shared_rules_count }} Freigabe{{ user.shared_rules_count === 1 ? '' : 'n' }}
                        </v-chip>
                    </template>
                </v-list-item>
            </v-list>
        </v-card>
    </v-card>
</template>

<script>
import axios from 'axios'

export default {
    name: 'MaterialsInboxView',
    data() {
        return {
            isLoading: false,
            users: [],
            needsMigration: false,
            errorMessage: '',
        }
    },
    mounted() {
        this.loadInboxUsers()
    },
    methods: {
        async loadInboxUsers() {
            this.isLoading = true
            this.errorMessage = ''
            try {
                const response = await axios.get('/api/admin/materials/shares/inbox-users')
                const rows = Array.isArray(response.data?.data) ? response.data.data : []
                this.users = rows.map((row) => ({
                    id: Number(row?.id || 0),
                    label: String(row?.label || '').trim() || 'Benutzer',
                    email: String(row?.email || '').trim(),
                    school_label: String(row?.school_label || '').trim(),
                    shared_rules_count: Math.max(0, Number(row?.shared_rules_count || 0)),
                    shared_items: Array.isArray(row?.shared_items)
                        ? row.shared_items.map((item) => ({
                            rule_id: Number(item?.rule_id || 0),
                            scope_label: String(item?.scope_label || '').trim() || 'Bereich',
                            scope_object_label: String(item?.scope_object_label || '').trim() || 'Unbekannt',
                            scope_path_label: String(item?.scope_path_label || '').trim() || 'Fach - Thema - Einheit',
                            permission: String(item?.permission || '').trim() || 'read_only',
                            permission_label: String(item?.permission_label || '').trim() || 'NUR LESEN',
                            updated_at: String(item?.updated_at || '').trim(),
                        })).filter((item) => item.rule_id > 0)
                        : [],
                })).filter((row) => row.id > 0)
                this.needsMigration = !!response.data?.meta?.needs_migration
            } catch (error) {
                this.users = []
                this.needsMigration = false
                this.errorMessage = error?.response?.data?.message || 'Inbox konnte nicht geladen werden.'
            } finally {
                this.isLoading = false
            }
        },
        formatDateTime(value) {
            if (!value) return ''
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return ''
            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date)
        },
        permissionChipColor(permission) {
            const normalized = String(permission || '').trim()
            if (normalized === 'full_access') return 'error'
            if (normalized === 'read_write') return 'warning'
            return 'primary'
        },
        onDummyObjectAction() {
            // Placeholder for future object actions.
        },
    },
}
</script>

<style scoped>
.inbox-shared-panels {
    margin-top: 6px;
}

.inbox-shared-list {
    display: grid;
    gap: 10px;
}

.inbox-shared-object-card {
    border: 1px solid rgba(35, 61, 76, 0.16);
    border-radius: 10px;
    background: rgba(248, 239, 231, 0.42);
    padding: 10px 12px;
}

.inbox-shared-object-head {
    margin-bottom: 10px;
}

.inbox-shared-object-head-top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.inbox-shared-object-scope {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    color: #1f6f8b;
    text-transform: uppercase;
}

.inbox-shared-object-title {
    font-size: 1rem;
    font-weight: 700;
    color: #233d4c;
    line-height: 1.25;
    margin-top: 2px;
}

.inbox-shared-object-path {
    margin-top: 4px;
    font-size: 0.84rem;
    color: #3c5a6d;
}

.inbox-shared-object-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
</style>
