<template>
    <v-col cols="12" xl="11">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title crud-title">Rollen</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalRolesCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField :store="roleStore" selected_field="selected_roles" />
                        </div>

                        <div class="d-flex flex-wrap ga-2" :disabled="action != ''">
                            <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                Alle auswählen [{{ Math.max(0, roles.length - selected_roles.length) }}]
                            </v-btn>
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_roles.length }}]
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state pa-2" v-if="roles.length === 0">Keine Rollen gefunden.</div>
                    <div class="empty-state pa-2" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="crud-list"
                            select-strategy="leaf"
                            v-model:selected="selected_roles"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in roles"
                                :key="item.id"
                                :value="item.id"
                                class="crud-list-item"
                                :class="{ 'is-selected': isSelectedRole(item.id) }">
                                <template #title>
                                    <div class="person-row crud-item-row">
                                        <div class="d-flex align-start justify-space-between ga-3 w-100" style="min-width: 0">
                                            <div class="person-body" style="min-width: 0">
                                                <div class="person-name">{{ item.name }}</div>
                                                <div v-if="item.is_admin" class="kpi-sub mt-1">
                                                    Darf <code>/admin</code> öffnen
                                                </div>
                                            </div>
                                            <div class="d-flex align-center" @click.stop>
                                                <span class="mr-2 text-caption">{{ item.is_admin ? 'Dashboard' : 'Kein Dashboard' }}</span>
                                                <v-switch
                                                    :model-value="!!item.is_admin"
                                                    color="success"
                                                    density="compact"
                                                    hide-details
                                                    inset
                                                    @update:modelValue="toggleRoleAdminAccess(item, $event)" />
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination :meta="meta" :store="roleStore" selected_field="selected_roles" />
                    </div>
                </section>

                <aside class="crud-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Rollen verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Verfügbare Schritte für die aktuelle Auswahl.</div>

                        <div class="crud-actions-primary">
                            <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createRole">
                                Hinzufügen
                            </v-btn>
                        </div>

                        <template v-if="selected_roles.length >= 1">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <v-btn
                                    v-if="selected_roles.length == 1"
                                    block
                                    color="primary"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-pencil"
                                    @click="editRole(selected_roles[0])">
                                    Ändern
                                </v-btn>

                                <v-btn
                                    block
                                    color="warning"
                                    variant="tonal"
                                    rounded="lg"
                                    class="crud-action-btn-offset"
                                    prepend-icon="mdi-delete"
                                    @click="deleteRole">
                                    Löschen
                                </v-btn>
                            </div>
                        </template>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="roleDialogOpen" persistent :max-width="roleDialogMaxWidth" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow" :class="{ 'crud-delete-eyebrow': action == 'delete_role' }">
                        {{ action == 'delete_role' ? 'Achtung' : 'Rolle' }}
                    </div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ roleDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <template v-if="action == 'create_role' || action == 'edit_role'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveRole(data)" class="mb-2 crud-form">
                        <div class="empty-state crud-form-section">
                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field autofocus v-model="data.name" label="Rollenname" :rules="[required(), maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-checkbox
                                        v-model="data.is_admin"
                                        color="primary"
                                        label="Dashboard (/admin)"
                                        hide-details />
                                    <div class="kpi-sub mt-1">
                                        Wenn diese Eigenschaft aktiv ist, dürfen Benutzer mit dieser Rolle den Adminbereich unter
                                        <code>/admin</code> öffnen.
                                    </div>
                                </v-col>
                            </v-row>
                        </div>

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="success" variant="flat" rounded="lg" type="submit">Speichern</v-btn>
                        </div>
                    </v-form>
                </template>

                <template v-else-if="action == 'delete_role'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteRoles(selected_roles)" class="crud-form">
                        <div class="empty-state crud-delete-alert">
                            <div class="admin-card-eyebrow crud-delete-eyebrow">Achtung</div>
                            <div class="admin-card-title crud-delete-title">Rolle löschen</div>
                            <div class="kpi-sub mt-2" v-if="selected_roles.length == 1">
                                Es soll eine Rolle gelöscht werden. Sind Sie sicher, dass Sie die markierte Rolle löschen möchten?
                            </div>
                            <div class="kpi-sub mt-2" v-if="selected_roles.length > 1">
                                Es sollen {{ selected_roles.length }} Rollen gelöscht werden. Sind Sie sicher, dass Sie die markierten Rollen löschen möchten?
                            </div>
                            <div class="kpi-sub mt-2">Hinweis: Zugeordnete Rollen und die Rolle <code>super_admin</code> können nicht gelöscht werden.</div>
                        </div>

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="success" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="error" variant="flat" rounded="lg" type="submit" prepend-icon="mdi-delete">Löschen</v-btn>
                        </div>
                    </v-form>
                </template>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import { useSuperAdminRoleStore } from '@/stores/admin/SuperAdminRoleStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.roleStore = useSuperAdminRoleStore()
        await this.roleStore.index()
    },

    data() {
        return {
            adminStore: null,
            roleStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useSuperAdminRoleStore, ['roles', 'meta', 'selected_roles', 'search_string', 'data']),
        roleDialogOpen: {
            get() {
                return ['create_role', 'edit_role', 'delete_role'].includes(this.action)
            },
            set(value) {
                if (!value) { this.action = '' }
            },
        },
        roleDialogMaxWidth() {
            return this.action == 'delete_role' ? 640 : 600
        },
        roleDialogTitle() {
            if (this.action == 'create_role') { return 'Neue Rolle' }
            if (this.action == 'edit_role') { return 'Rolle ändern' }
            if (this.action == 'delete_role') { return 'Löschen bestätigen' }
            return 'Rolle'
        },
        totalRolesCount() {
            const total = Number(this.meta?.total)
            return Number.isFinite(total) && total >= 0 ? total : this.roles.length
        },
    },

    methods: {
        deleteRole() {
            this.action = 'delete_role'
        },

        async doDeleteRoles(selected_roles) {
            if (!(await this.roleStore.deleteRoles(selected_roles))) { return }
            this.selected_roles = []
            await this.roleStore.index()
            this.action = ''
        },

        async saveRole(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) { return }

            if (data.id) {
                if (!(await this.roleStore.update(data))) { return }
            } else {
                if (!(await this.roleStore.store(data))) { return }
            }
            this.data = {}
            this.action = ''
        },

        async toggleRoleAdminAccess(role, nextValue) {
            const previousValue = !!role.is_admin
            role.is_admin = !!nextValue

            const updated = await this.roleStore.update({
                ...role,
                is_admin: !!nextValue,
            })

            if (!updated) {
                role.is_admin = previousValue
            }
        },

        createRole() {
            this.data = { is_admin: false }
            this.action = 'create_role'
        },

        editRole(role_id) {
            const role = this.roles.find((item) => item.id === role_id)
            this.data = JSON.parse(JSON.stringify(role))
            this.action = 'edit_role'
        },

        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_roles = this.roles.map((item) => item.id)
        },

        unselectAll() {
            this.selected_roles = []
        },

        isSelectedRole(id) {
            return this.selected_roles.includes(id)
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
