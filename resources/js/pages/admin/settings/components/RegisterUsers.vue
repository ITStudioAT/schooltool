<template>
    <v-col cols="12" xl="11" v-if="users">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Anmeldetool</div>
                    <h2 class="admin-card-title crud-title">Benutzer</h2>
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

const FORCED_ROLE = 'register_user'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.userStore = useUserStore()
        this.userStore.role = FORCED_ROLE
        await this.userStore.index()
    },

    beforeUnmount() {
        this.userStore.role = ''
        this.userStore.selected_users = []
    },

    data() {
        return {
            adminStore: null,
            userStore: null,
            is_valid: false,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useUserStore, ['users', 'meta', 'selected_users', 'search_string', 'data', 'answer']),
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

    methods: {
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

        async saveUser(data) {
            if (this.is_uploading) { return }
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) { return }

            data.roles = [{ name: FORCED_ROLE, checked: true }]

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
            this.action = 'edit_user'
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
