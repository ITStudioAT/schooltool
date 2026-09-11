<template>
    <v-col cols="12" xl="11">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">SEPP</div>
                    <div class="d-flex align-center flex-wrap ga-2">
                        <h2 class="admin-card-title crud-title">{{ title }}</h2>
                        <v-chip
                            v-if="roleChipText"
                            size="x-small"
                            variant="tonal"
                            color="indigo-lighten-3"
                            prepend-icon="mdi-shield-account-outline">
                            {{ roleChipText }}
                        </v-chip>
                    </div>
                </div>

                <div class="admin-kpi-grid crud-kpis">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ totalAdminUsersCount }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField :store="adminUserStore" selected_field="selected_admin_users" />
                        </div>

                        <div class="d-flex flex-wrap ga-2" :disabled="action != ''">
                            <v-btn color="primary" variant="text" rounded="lg" class="text-caption" @click="unselectAll">
                                Auswahl aufheben
                            </v-btn>
                        </div>
                    </div>

                    <div class="empty-state pa-2" v-if="admin_users.length === 0">Keine {{ title }} gefunden.</div>
                    <div class="empty-state pa-2" v-else>
                        <v-list
                            dense
                            variant="flat"
                            class="crud-list"
                            select-strategy="leaf"
                            v-model:selected="selected_admin_users"
                            color="success-lighten-2">
                            <v-list-item
                                v-for="item in admin_users"
                                :key="item.id"
                                :value="item.id"
                                class="crud-list-item"
                                :class="{ 'is-selected': isSelectedAdminUser(item.id) }">
                                <template #title>
                                    <div class="person-row crud-item-row">
                                        <div class="d-flex align-start" style="min-width: 0">
                                            <div class="person-body" style="min-width: 0">
                                                <div class="person-name d-flex align-center ga-1">
                                                    <v-icon v-if="!item.is_active" color="error" size="14" icon="mdi-lock" />
                                                    <span>
                                                        {{ item.last_name }} {{ item.first_name }}<span v-if="item.short"> ({{ item.short }})</span>
                                                    </span>
                                                </div>
                                                <div class="person-email-row">
                                                    <span class="person-email">{{ item.email || '-' }}</span>
                                                    <v-btn
                                                        v-if="item.email"
                                                        :icon="copiedEmailId === item.id ? 'mdi-check' : 'mdi-content-copy'"
                                                        :color="copiedEmailId === item.id ? 'success' : undefined"
                                                        variant="text"
                                                        density="compact"
                                                        size="x-small"
                                                        class="person-email-copy"
                                                        :aria-label="copiedEmailId === item.id ? `E-Mail-Adresse kopiert: ${item.email}` : `E-Mail-Adresse kopieren: ${item.email}`"
                                                        :title="copiedEmailId === item.id ? 'Kopiert!' : `E-Mail-Adresse kopieren: ${item.email}`"
                                                        @click.stop="copyEmail(item)" />
                                                    <v-chip
                                                        v-if="copiedEmailId === item.id"
                                                        size="x-small"
                                                        color="success"
                                                        variant="tonal"
                                                        class="person-email-copied-chip">
                                                        Kopiert
                                                    </v-chip>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <div class="empty-state crud-pagination mt-3 pa-3">
                        <Pagination :meta="meta" :store="adminUserStore" selected_field="selected_admin_users" />
                    </div>
                </section>

                <aside class="crud-side-stack">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div>
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">{{ title }} verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Verfügbare Schritte für die aktuelle Auswahl.</div>

                        <div class="crud-actions-primary">
                            <v-btn block color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createAdminUser">
                                Hinzufügen
                            </v-btn>
                        </div>

                        <template v-if="selected_admin_users.length === 1">
                            <v-divider class="crud-actions-divider" />
                            <div class="crud-actions-secondary">
                                <v-btn
                                    block
                                    color="primary"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-pencil"
                                    @click="editAdminUser(selected_admin_users[0])">
                                    Ändern
                                </v-btn>

                                <v-btn
                                    v-if="selectedAdminUser(selected_admin_users[0]) && !selectedAdminUser(selected_admin_users[0]).is_active"
                                    block
                                    color="success"
                                    variant="tonal"
                                    rounded="lg"
                                    class="crud-action-btn-offset"
                                    prepend-icon="mdi-lock-open"
                                    @click="toggleIsActive(selected_admin_users[0])">
                                    Entsperren
                                </v-btn>
                            </div>
                        </template>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <v-dialog v-model="adminUserDialogOpen" persistent max-width="640" scrollable>
        <v-card class="crud-dialog-card ai-glass-panel">
            <div class="crud-dialog-head">
                <div>
                    <div class="admin-card-eyebrow">SEPP</div>
                    <div class="admin-card-title" style="margin-top: 4px">{{ adminUserDialogTitle }}</div>
                </div>

                <v-btn icon="mdi-close" variant="text" rounded="lg" @click="abort" />
            </div>

            <v-card-text class="crud-dialog-body">
                <v-form ref="form" v-model="is_valid" @submit.prevent="saveAdminUser(data)" class="mb-2 crud-form">
                    <div class="empty-state crud-form-section">
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    autofocus
                                    v-model="data.short"
                                    label="Kurzzeichen"
                                    :rules="[maxLength(10)]"
                                    @input="data.short = data.short?.toUpperCase()" />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field v-model="data.email" label="E-Mail" :rules="[required(), mail(), maxLength(255)]" />
                            </v-col>
                        </v-row>
                    </div>

                    <div class="crud-form-actions d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" variant="text" rounded="lg" @click="abort">Abbruch</v-btn>
                        <v-btn color="success" variant="flat" rounded="lg" type="submit">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useValidationRulesSetup } from '@/helpers/rules'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useStudentsTimetablesAdminUserStore } from '@/stores/admin/studentsTimetables/AdminUserStore'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

const roleChipLabels = {
    admins: 'studentstimetables_admin',
    moderators: 'studentstimetables_moderator',
}

export default {
    components: { Pagination, SearchField },

    props: {
        roleKey: {
            type: String,
            default: 'admins',
        },
        title: {
            type: String,
            default: 'Admins',
        },
        singularTitle: {
            type: String,
            default: 'Admin',
        },
    },

    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminUserStore = useStudentsTimetablesAdminUserStore()
        this.adminUserStore.role_key = this.roleKey
        await this.adminUserStore.index(null, this.roleKey)
    },

    data() {
        return {
            adminUserStore: null,
            is_valid: false,
            copiedEmailId: null,
            copyEmailResetTimeout: null,
        }
    },

    beforeUnmount() {
        if (this.copyEmailResetTimeout) {
            clearTimeout(this.copyEmailResetTimeout)
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useStudentsTimetablesAdminUserStore, ['admin_users', 'meta', 'selected_admin_users', 'data']),
        createAction() {
            return `create_students_timetables_${this.roleKey}_user`
        },
        editAction() {
            return `edit_students_timetables_${this.roleKey}_user`
        },
        adminUserDialogOpen: {
            get() {
                return [this.createAction, this.editAction].includes(this.action)
            },
            set(value) {
                if (!value) { this.action = '' }
            },
        },
        adminUserDialogTitle() {
            if (this.action === this.createAction) { return `Neue:r ${this.singularTitle}` }
            if (this.action === this.editAction) { return `${this.singularTitle} ändern` }
            return this.singularTitle
        },
        totalAdminUsersCount() {
            const total = Number(this.meta?.total)

            return Number.isFinite(total) && total >= 0 ? total : this.admin_users.length
        },
        roleChipText() {
            return roleChipLabels[this.roleKey] || this.roleKey
        },
    },

    watch: {
        selected_admin_users(selectedAdminUsers) {
            if (!Array.isArray(selectedAdminUsers) || selectedAdminUsers.length <= 1) { return }

            this.selected_admin_users = [selectedAdminUsers[selectedAdminUsers.length - 1]]
        },
    },

    methods: {
        async saveAdminUser(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) { return }

            if (data.id) {
                if (!(await this.adminUserStore.update(data, this.roleKey))) { return }
            } else {
                if (!(await this.adminUserStore.store(data, this.roleKey))) { return }
            }

            this.selected_admin_users = []
            await this.adminUserStore.index(null, this.roleKey)
            this.data = {}
            this.action = ''
        },
        createAdminUser() {
            this.data = { is_selectable: true }
            this.action = this.createAction
        },
        editAdminUser(adminUserId) {
            const adminUser = this.admin_users.find((user) => user.id === adminUserId)
            this.data = JSON.parse(JSON.stringify(adminUser))
            this.action = this.editAction
        },
        abort() {
            this.action = ''
        },
        unselectAll() {
            this.selected_admin_users = []
        },
        isSelectedAdminUser(id) {
            return this.selected_admin_users.includes(id)
        },
        selectedAdminUser(adminUserId) {
            return this.admin_users.find((user) => user.id === adminUserId)
        },
        async copyEmail(adminUser) {
            const emailAddress = (adminUser?.email || '').toString().trim()
            if (!emailAddress) { return false }

            const copied = await this.copyTextToClipboard(emailAddress)
            if (!copied) { return false }

            this.copiedEmailId = adminUser.id

            if (this.copyEmailResetTimeout) {
                clearTimeout(this.copyEmailResetTimeout)
            }

            this.copyEmailResetTimeout = setTimeout(() => {
                this.copiedEmailId = null
                this.copyEmailResetTimeout = null
            }, 1500)

            return true
        },
        async copyTextToClipboard(text) {
            if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text)

                return true
            }

            if (typeof document === 'undefined') { return false }

            const textArea = document.createElement('textarea')
            textArea.value = text
            textArea.setAttribute('readonly', '')
            textArea.style.position = 'fixed'
            textArea.style.opacity = '0'
            document.body.appendChild(textArea)
            textArea.select()
            const copied = document.execCommand('copy')
            document.body.removeChild(textArea)

            return copied
        },
        async toggleIsActive(userId) {
            await this.adminUserStore.toggleIsActive(userId, this.roleKey)
            await this.adminUserStore.index(this.meta.current_page, this.roleKey)
        },
    },
}
</script>

<style scoped src="@/../css/admin-index-page.css"></style>
<style scoped src="@/../css/admin-crud-panel.css"></style>
<style scoped>
.person-email-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    min-width: 0;
}

.person-email {
    min-width: 0;
    overflow-wrap: anywhere;
}

.person-email-copy {
    flex: 0 0 auto;
    opacity: 0.72;
}

.person-email-copy:hover {
    opacity: 1;
}

.person-email-copied-chip {
    flex: 0 0 auto;
}
</style>
