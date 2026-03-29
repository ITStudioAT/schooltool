<template>
    <v-col cols="12" xl="11" v-if="users">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Verwaltung</div>
                    <h2 class="admin-card-title crud-title">Benutzer Accounts</h2>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalUsersCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField :store="userStore" selected_field="selected_users" />
                        </div>

                        <div class="d-flex flex-wrap ga-2" :disabled="action != ''">
                            <v-btn
                                :color="selected_role == null ? 'primary' : 'secondary'"
                                variant="tonal"
                                rounded="lg"
                                class="text-caption"
                                @click="selected_role = null">
                                Alle
                            </v-btn>
                            <v-btn
                                v-for="role in roles"
                                :key="role.name"
                                :color="selected_role == role.name ? 'primary' : 'secondary'"
                                variant="tonal"
                                rounded="lg"
                                class="text-caption"
                                @click="changeSelectedRole(role.name)">
                                {{ role.name }}
                            </v-btn>
                        </div>

                        <div class="d-flex flex-wrap ga-2" :disabled="action != ''">
                            <v-btn color="primary" variant="tonal" rounded="lg" class="text-caption" @click="selectAll">
                                Alle auswählen [{{ Math.max(0, users.length - selected_users.length) }}]
                            </v-btn>
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Alle abwählen [{{ selected_users.length }}]
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state pa-2" v-if="users.length === 0">Keine Benutzer gefunden.</div>
                    <div class="empty-state pa-2" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="crud-list"
                            select-strategy="leaf"
                            v-model:selected="selected_users"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in users"
                                :key="item.id"
                                :value="item.id"
                                class="crud-list-item"
                                :class="{ 'is-selected': isSelectedUser(item.id) }">
                                <template #title>
                                    <div class="person-row crud-item-row">
                                        <div class="d-flex align-start" style="min-width: 0">
                                            <div class="person-body" style="min-width: 0">
                                                <div class="person-name d-flex align-center ga-1">
                                                    <v-icon v-if="!item.is_active" color="error" size="14" icon="mdi-lock" />
                                                    {{ item.last_name }} {{ item.first_name }}
                                                </div>
                                                <div class="person-roles person-roles-chips">
                                                    <v-chip
                                                        v-for="roleName in normalizeRoleNames(item.roles)"
                                                        :key="`${item.id}-${roleName}`"
                                                        size="x-small"
                                                        rounded="lg"
                                                        class="person-role-chip"
                                                        color="primary"
                                                        variant="tonal">
                                                        {{ formatRoleLabel(roleName) }}
                                                    </v-chip>
                                                    <v-chip
                                                        v-if="normalizeRoleNames(item.roles).length === 0"
                                                        size="x-small"
                                                        rounded="lg"
                                                        class="person-role-chip"
                                                        color="primary"
                                                        variant="outlined">
                                                        Keine Rolle
                                                    </v-chip>
                                                </div>
                                                <div v-if="normalizedSchoolclass(item.schoolclass)" class="person-schoolclass">
                                                    Klasse {{ normalizedSchoolclass(item.schoolclass) }}
                                                </div>
                                                <div class="person-email">{{ item.email }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination :meta="meta" :store="userStore" selected_field="selected_users" />
                    </div>
                </section>

                <aside class="crud-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Benutzer verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Verfügbare Schritte für die aktuelle Auswahl.</div>

                        <div class="crud-actions-primary">
                            <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createUser">
                                Hinzufügen
                            </v-btn>
                        </div>

                        <template v-if="selected_users.length >= 1">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <template v-if="selected_users.length == 1">
                                    <v-btn
                                        block
                                        color="primary"
                                        variant="tonal"
                                        rounded="lg"
                                        prepend-icon="mdi-pencil"
                                        @click="editUser(selected_users[0])">
                                        Ändern
                                    </v-btn>

                                    <v-btn
                                        v-if="selectedUser(selected_users[0])?.is_active"
                                        block
                                        color="error"
                                        variant="tonal"
                                        rounded="lg"
                                        class="crud-action-btn-offset"
                                        prepend-icon="mdi-lock"
                                        @click="toggleIsActive(selected_users[0])">
                                        Sperren
                                    </v-btn>
                                    <v-btn
                                        v-else
                                        block
                                        color="success"
                                        variant="tonal"
                                        rounded="lg"
                                        class="crud-action-btn-offset"
                                        prepend-icon="mdi-lock-open"
                                        @click="toggleIsActive(selected_users[0])">
                                        Entsperren
                                    </v-btn>
                                </template>

                                <v-btn
                                    block
                                    color="warning"
                                    variant="tonal"
                                    rounded="lg"
                                    class="crud-action-btn-offset"
                                    prepend-icon="mdi-delete"
                                    @click="deleteUser">
                                    Löschen
                                </v-btn>
                            </div>
                        </template>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="userDialogOpen" persistent :max-width="userDialogMaxWidth" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow" :class="{ 'crud-delete-eyebrow': action == 'delete_user' }">
                        {{ action == 'delete_user' ? 'Achtung' : 'Benutzer' }}
                    </div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ userDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <template v-if="action == 'create_user' || action == 'edit_user'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="saveUser(data)" class="mb-2 crud-form" :disabled="is_uploading">
                        <div class="empty-state crud-form-section">
                            <v-row dense>
                                <v-col cols="12">
                                    <v-text-field autofocus v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.email" label="E-Mail" :rules="[mail(), maxLength(255)]" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="data.phone" label="Telefon" :rules="[maxLength(255)]" />
                                </v-col>
                            </v-row>
                        </div>

                        <div class="empty-state crud-form-section mt-3">
                            <div class="admin-card-eyebrow">Rollen</div>
                            <template v-if="canManageRoles()">
                                <div class="kpi-sub mt-1 mb-2">
                                    <template v-if="isCurrentUserSuperAdmin()">
                                        Hinweis: Bei der Vergabe von Rollen gibt es Einschränkungen. Zum Beispiel kann einem Benutzer, der im Anmeldetool registriert ist, der register_user nicht entzogen werden.
                                    </template>
                                    <template v-else>
                                        Mit der Rolle <code>admin</code> können alle Rollen außer <code>super_admin</code> vergeben oder entzogen werden.
                                    </template>
                                </div>
                                <div class="d-flex flex-row flex-wrap align-center ga-2">
                                    <v-checkbox
                                        v-for="role in data.roles"
                                        :key="role.name"
                                        v-model="role.checked"
                                        :label="role.name"
                                        :disabled="isRoleLocked(role)"
                                        hide-details
                                        color="success"
                                        dense />
                                </div>
                            </template>
                            <div class="kpi-sub mt-1" v-else>Rollen dürfen nur von <code>admin</code> oder <code>super_admin</code> verwaltet werden.</div>
                        </div>

                        <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                            <v-btn color="success" variant="flat" rounded="lg" type="submit" :loading="is_uploading">Speichern</v-btn>
                        </div>
                    </v-form>
                </template>

                <template v-else-if="action == 'delete_user'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteUsers(selected_users)" class="crud-form">
                        <div class="empty-state crud-delete-alert">
                            <div class="admin-card-eyebrow crud-delete-eyebrow">Achtung</div>
                            <div class="admin-card-title crud-delete-title">Benutzer löschen</div>
                            <div class="kpi-sub mt-2" v-if="selected_users.length == 1">
                                Es soll ein Benutzer gelöscht werden. Sind Sie sicher, dass Sie den markierten Benutzer löschen möchten?
                            </div>
                            <div class="kpi-sub mt-2" v-if="selected_users.length > 1">
                                Es sollen {{ selected_users.length }} Benutzer gelöscht werden. Sind Sie sicher, dass Sie die markierten Benutzer löschen möchten?
                            </div>
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
import { useUserStore } from '@/stores/admin/UserStore20'
import { useRoleStore } from '@/stores/admin/RoleStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.userStore = useUserStore()
        this.roleStore = useRoleStore()
        await this.userStore.index()
        await this.roleStore.loadRoles()
    },

    data() {
        return {
            adminStore: null,
            userStore: null,
            roleStore: null,
            is_valid: false,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useUserStore, ['users', 'meta', 'selected_users', 'search_string', 'data', 'answer', 'role']),
        ...mapWritableState(useRoleStore, ['roles', 'selected_role']),
        userDialogOpen: {
            get() {
                return ['create_user', 'edit_user', 'delete_user'].includes(this.action)
            },
            set(value) {
                if (!value) { this.action = '' }
            },
        },
        userDialogMaxWidth() {
            return this.action == 'delete_user' ? 640 : 760
        },
        userDialogTitle() {
            if (this.action == 'create_user') { return 'Neuer Benutzer' }
            if (this.action == 'edit_user') { return 'Benutzer ändern' }
            if (this.action == 'delete_user') { return 'Löschen bestätigen' }
            return 'Benutzer'
        },
        totalUsersCount() {
            const total = Number(this.meta?.total)
            return Number.isFinite(total) && total >= 0 ? total : this.users.length
        },
    },

    watch: {
        async selected_role() {
            this.userStore.role = this.selected_role
            await this.userStore.index()
        },
    },

    methods: {
        changeSelectedRole(role_name) {
            this.selected_users = []
            this.selected_role = role_name
        },

        async toggleIsActive(user_id) {
            await this.userStore.toggleIsActive(user_id)
            await this.userStore.index(this.meta.current_page)
        },

        selectedUser(user_id) {
            return this.users.find((s) => s.id === user_id)
        },

        isSelectedUser(id) {
            return this.selected_users.includes(id)
        },

        normalizeRoleNames(roles) {
            const rawRoles = Array.isArray(roles) ? roles : typeof roles === 'string' ? roles.split(',') : []
            const names = rawRoles
                .map((role) => (typeof role === 'string' ? role : role?.name))
                .map((name) => (name || '').toString().trim())
                .filter((name) => name.length > 0)

            return [...new Set(names)]
        },

        formatRoleLabel(roleName) {
            return roleName
                .toString()
                .replaceAll('_', ' ')
                .replace(/\b\w/g, (char) => char.toUpperCase())
        },

        normalizedSchoolclass(schoolclass) {
            return (schoolclass || '').toString().trim()
        },

        async saveUser(data) {
            if (this.is_uploading) { return }
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) { return }
            if (this.canManageRoles()) {
                this.enforceProtectedSuperAdminRole(data)
            } else {
                delete data.roles
            }

            if (data.id) {
                if (!(await this.userStore.update(data))) { return }
                await this.userStore.index(this.meta.current_page)
            } else {
                if (!(await this.userStore.store(data))) { return }
                await this.userStore.index(this.meta.current_page)
            }
            await this.adminStore.loadConfig()
            this.data = {}
            this.action = ''
        },

        createUser() {
            this.data = { is_selectable: true }
            if (this.canManageRoles()) {
                this.data.roles = this.roles.map((role) => ({ ...role }))
                this.enforceProtectedSuperAdminRole(this.data)
            }
            this.action = 'create_user'
        },

        deleteUser() {
            this.action = 'delete_user'
        },

        async doDeleteUsers(data) {
            if (!(await this.userStore.deleteUsers(data))) { return }
            this.selected_users = []
            await this.userStore.index()
            this.action = ''
        },

        editUser(user_id) {
            const user = this.users.find((s) => s.id === user_id)
            this.data = JSON.parse(JSON.stringify(user))

            if (this.canManageRoles()) {
                this.data.roles = this.roles.map((role) => ({
                    ...role,
                    checked: user.roles.includes(role.name),
                }))
                this.enforceProtectedSuperAdminRole(this.data)
            }

            this.action = 'edit_user'
        },

        isCurrentUserSuperAdmin() {
            return (this.config?.roles || []).includes('super_admin')
        },
        canManageRoles() {
            return (this.config?.roles || []).some((role) => ['super_admin', 'admin'].includes(role))
        },

        isProtectedSuperAdminUser(userData = this.data) {
            return (userData?.email || '').toString().trim().toLowerCase() === 'kron@naturwelt.at'
        },

        isEditingSelf() {
            return this.data?.id && this.data.id === this.config?.user?.id
        },

        isSuperAdminRoleLocked(role) {
            if (role?.name !== 'super_admin') { return false }
            if (!this.isCurrentUserSuperAdmin()) { return true }
            return this.isProtectedSuperAdminUser()
        },

        isRoleLocked(role) {
            if (this.isSuperAdminRoleLocked(role)) { return true }
            if (['super_admin', 'admin'].includes(role?.name) && this.isEditingSelf()) { return true }
            return false
        },

        enforceProtectedSuperAdminRole(userData = this.data) {
            if (!Array.isArray(userData?.roles)) { return }
            if (!this.isProtectedSuperAdminUser(userData)) { return }
            const superAdminRole = userData.roles.find((role) => role.name === 'super_admin')
            if (superAdminRole) {
                superAdminRole.checked = true
            }
        },

        abort() {
            this.action = ''
        },

        selectAll() {
            this.selected_users = this.users.map((item) => item.id)
        },

        unselectAll() {
            this.selected_users = []
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
<style scoped>
.person-roles-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 2px;
}

.person-role-chip {
    font-size: 0.69rem;
    font-weight: 400;
    letter-spacing: 0.03em;
    border: 1px solid color-mix(in srgb, currentColor 28%, transparent);
    background-color: color-mix(in srgb, currentColor 14%, transparent);
    opacity: 0.98;
}

.person-schoolclass {
    margin-top: 4px;
    font-size: 0.78rem;
    font-weight: 600;
    color: rgb(var(--v-theme-primary));
}
</style>
