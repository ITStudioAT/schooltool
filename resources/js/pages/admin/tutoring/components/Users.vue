<template>
    <v-col cols="12" md="10" xl="9" v-if="users">
        <section class="admin-card ai-glass-panel tutoring-users-card" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head mb-3">
                <div class="tov-card-heading">
                    <div class="admin-card-eyebrow">Nachhilfe</div>
                    <h2 class="admin-card-title">Benutzer</h2>
                </div>
            </div>

            <div class="kpi-sub mb-4">Verwalte Nachhilfe-Benutzer:innen, Auswahl-Filter und Freigabe-/Sperr-Aktionen.</div>

            <div class="tutoring-users-layout">
                <section class="tutoring-users-panel tutoring-users-main">
                    <div class="tutoring-users-search-panel">
                        <SearchField :store="tutoringUserStore" selected_field="selected_users" />
                    </div>

                    <div class="tutoring-users-filter-groups">
                        <div class="tutoring-users-filter-group">
                            <div class="tutoring-users-filter-group__head">
                                <div class="tutoring-users-filter-group__label">Auswahl</div>
                                <div class="tutoring-users-filter-group__count">{{ selected_users.length }}/{{ users.length }}</div>
                            </div>
                            <div class="tutoring-users-filter-group__actions">
                                <v-btn
                                    color="primary"
                                    variant="tonal"
                                    rounded="pill"
                                    size="small"
                                    class="tutoring-users-filter-btn"
                                    @click="selectAll"
                                    :disabled="action != '' || users.length === 0 || selected_users.length === users.length">
                                    Alle auswählen
                                </v-btn>
                                <v-btn
                                    color="secondary"
                                    variant="tonal"
                                    rounded="pill"
                                    size="small"
                                    class="tutoring-users-filter-btn"
                                    @click="unselectAll"
                                    :disabled="action != '' || selected_users.length === 0">
                                    Alle abwählen
                                </v-btn>
                            </div>
                        </div>

                        <div class="tutoring-users-filter-group">
                            <div class="tutoring-users-filter-group__head">
                                <div class="tutoring-users-filter-group__label">Filter</div>
                                <v-icon size="14" icon="mdi-filter-variant" />
                            </div>
                            <div class="tutoring-users-filter-group__actions">
                                <v-btn :color="selected_filter == null ? 'primary' : 'secondary'" variant="tonal" rounded="pill" size="small" class="tutoring-users-filter-btn" @click="toggleFilter(null)">
                                    Alle
                                </v-btn>
                                <v-btn :color="selected_filter == 'confirmation' ? 'primary' : 'secondary'" variant="tonal" rounded="pill" size="small" class="tutoring-users-filter-btn" @click="toggleFilter('confirmation')">
                                    Bestätigung ausstehend
                                </v-btn>
                                <v-btn :color="selected_filter == 'email' ? 'primary' : 'secondary'" variant="tonal" rounded="pill" size="small" class="tutoring-users-filter-btn" @click="toggleFilter('email')">
                                    E-Mail nicht bestätigt
                                </v-btn>
                            </div>
                        </div>
                    </div>

                    <div class="empty-state" v-if="users.length === 0">Keine Benutzer gefunden.</div>

                    <div v-else class="tutoring-users-records">
                        <div
                            v-for="item in users"
                            :key="item.id"
                            class="tutoring-users-list-row"
                            :class="{ 'is-selected': selected_users.includes(item.id) }"
                            @click="onUserClick(item.id)">
                            <div class="tutoring-users-select">
                                <v-icon
                                    size="18"
                                    :icon="selected_users.includes(item.id) ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'"
                                    :color="selected_users.includes(item.id) ? 'primary' : 'grey-darken-1'" />
                            </div>

                            <div class="tutoring-users-item-icons">
                                <v-icon color="error" size="small" icon="mdi-lock" v-if="!item.is_active" />
                                <v-icon icon="mdi-email" color="warning" v-if="!item.email_verified_at" size="small" />
                                <v-icon icon="mdi-help" color="warning" v-if="!item.confirmed_at" size="small" />
                            </div>

                            <div class="tutoring-users-item-copy">
                                <div class="tutoring-users-item-name">
                                    {{ item.last_name + ' ' + item.first_name }}
                                    <span class="tutoring-users-item-class" v-if="item.schoolclass">({{ item.schoolclass }})</span>
                                </div>
                                <div class="tutoring-users-item-mail">{{ item.email }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="tutoring-users-pagination">
                        <Pagination :meta="meta" :store="tutoringUserStore" selected_field="selected_users" />
                    </div>
                </section>

                <aside class="tutoring-users-side-stack">
                    <section class="tutoring-users-panel">
                        <div class="tutoring-users-panel__head">
                            <div class="tutoring-users-panel__title">Aktionen</div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Verfügbare Schritte für die aktuelle Auswahl.</div>
                        <div class="kpi-sub mt-2">{{ selected_users.length }} ausgewählt</div>

                        <div class="tutoring-users-card-menu mt-3">
                            <v-btn block color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-plus" @click="createUser">Hinzufügen</v-btn>
                        </div>

                        <template v-if="selected_users.length == 1">
                            <v-divider class="my-3 opacity-30" />
                            <div class="tutoring-users-card-menu">
                                <v-btn block color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-pencil" @click="editUser(selected_users[0])">Ändern</v-btn>
                                <v-btn
                                    block
                                    color="error"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-lock"
                                    @click="toggleIsActive(selected_users[0])"
                                    v-if="selectedUser(selected_users[0]).is_active">
                                    Sperren
                                </v-btn>
                                <v-btn
                                    block
                                    color="success"
                                    variant="tonal"
                                    rounded="lg"
                                    prepend-icon="mdi-lock-open"
                                    @click="toggleIsActive(selected_users[0])"
                                    v-if="!selectedUser(selected_users[0]).is_active">
                                    Entsperren
                                </v-btn>
                            </div>
                        </template>

                        <template v-if="selected_users.length >= 1">
                            <v-divider class="my-3 opacity-30" />
                            <div class="tutoring-users-card-menu">
                                <v-btn block color="success" variant="tonal" rounded="lg" prepend-icon="mdi-check" @click="confirmUsers(selected_users)">Bestätigen</v-btn>
                                <v-btn block color="warning" variant="tonal" rounded="lg" prepend-icon="mdi-delete" @click="deleteUser">Löschen</v-btn>
                            </div>
                        </template>
                    </section>

                    <section class="tutoring-users-panel" v-if="count_deletable_users > 0">
                        <div class="tutoring-users-panel__head">
                            <div class="tutoring-users-panel__title">Bereinigung Benutzer</div>
                        </div>
                        <div class="kpi-sub">Nicht bestätigte E-Mail-Adressen können gesammelt entfernt werden.</div>
                        <div class="tutoring-users-clean-count">{{ count_deletable_users }}</div>
                        <div class="tutoring-users-card-menu">
                            <v-btn block color="warning" variant="tonal" rounded="lg" prepend-icon="mdi-vacuum" @click="cleanUsers">Bereinigen</v-btn>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <!-- Neuer Benutzer / Benutzer ändern -->
    <v-col cols="12" md="8" xl="6" v-if="action == 'create_user' || action == 'edit_user'">
        <section class="admin-card ai-glass-panel tutoring-users-form-card">
            <div class="admin-card-head mb-3">
                <div class="tov-card-heading">
                    <div class="admin-card-eyebrow">Benutzer</div>
                    <h2 class="admin-card-title">{{ data.id ? 'Benutzer ändern' : 'Neuer Benutzer' }}</h2>
                </div>
            </div>

            <v-form ref="form" v-model="is_valid" @submit.prevent="saveUser(data)" class="tutoring-users-form">
                <div class="tutoring-users-form-section">
                    <v-text-field autofocus v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                    <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                    <v-text-field v-model="data.email" label="E-Mail" :rules="[mail(), maxLength(255)]" />
                    <v-text-field v-model="data.schoolclass" label="Schulklasse" :rules="[required(), maxLength(10)]" />
                </div>

                <div class="tutoring-users-form-section">
                    <div class="tutoring-users-form-label">Geschlecht</div>
                    <v-radio-group v-model="data.sex" :rules="[required()]" hide-details="auto">
                        <v-radio label="Männlich" value="m" color="blue"></v-radio>
                        <v-radio label="Weiblich" value="f" color="pink"></v-radio>
                        <v-radio label="Divers" value="d" color="yellow"></v-radio>
                    </v-radio-group>
                </div>

                <div class="tutoring-users-card-menu" :class="{ 'is-disabled': is_uploading }">
                    <v-btn color="warning" rounded="lg" prepend-icon="mdi-close" @click="abort">Abbruch</v-btn>
                    <v-btn color="success" rounded="lg" prepend-icon="mdi-content-save" type="submit">Speichern</v-btn>
                </div>
            </v-form>
        </section>
    </v-col>

    <!-- Löschen -->
    <v-col cols="12" md="8" xl="6" v-if="action == 'delete_user'">
        <section class="admin-card ai-glass-panel tutoring-users-form-card">
            <div class="admin-card-head mb-3">
                <div class="tov-card-heading">
                    <div class="admin-card-eyebrow">Benutzer</div>
                    <h2 class="admin-card-title">Löschen</h2>
                </div>
            </div>

            <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteUsers(selected_users)" class="tutoring-users-form">
                <div class="tutoring-users-delete-box">
                    <div v-if="selected_users.length == 1">Es soll ein Benutzer gelöscht werden. Sind Sie sicher, dass Sie den markierten Benutzer löschen möchten?</div>
                    <div v-if="selected_users.length > 1">
                        Es sollen {{ selected_users.length }} Benutzer gelöscht werden. Sind Sie sicher, dass Sie die markierten Benutzer löschen möchten?
                    </div>
                </div>

                <div class="tutoring-users-card-menu">
                    <v-btn color="success" rounded="lg" prepend-icon="mdi-close" @click="action = ''">Abbruch</v-btn>
                    <v-btn color="error" rounded="lg" prepend-icon="mdi-delete" type="submit">Löschen</v-btn>
                </div>
            </v-form>
        </section>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

// SPECIFIC

import { useTutoringUserStore } from '@/stores/admin/tutoring/UserStore'
import { useUserStore } from '@/stores/admin/UserStore20'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.tutoringUserStore = useTutoringUserStore()
        this.userStore = useUserStore()
        await this.tutoringUserStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            tutoringUserStore: null,
            userStore: null,

            is_valid: false,
            upload_file: null,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTutoringUserStore, ['users', 'meta', 'selected_users', 'search_string', 'data', 'answer', 'role', 'count_deletable_users', 'selected_filter']),
    },

    watch: {},

    methods: {
        async cleanUsers() {
            await this.tutoringUserStore.cleanUsers()
            await this.tutoringUserStore.index(this.meta.current_page)
        },

        async toggleFilter(item) {
            this.selected_filter = item
            await this.tutoringUserStore.index(this.meta.current_page)
        },
        async toggleIsActive(user_id) {
            await this.userStore.toggleIsActive(user_id)
            await this.tutoringUserStore.index(this.meta.current_page)
        },
        selectedUser(user) {
            return this.users.find((s) => s.id === user)
        },

        async saveUser(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (data.id) {
                if (!(await this.tutoringUserStore.update(data))) return
                await this.tutoringUserStore.index(this.meta.current_page)
            } else {
                if (!(await this.tutoringUserStore.store(data))) return
                await this.tutoringUserStore.index(this.meta.current_page)
            }
            this.data = {}
            this.action = ''
        },

        createUser() {
            this.data = {}
            this.action = 'create_user'
        },

        deleteUser() {
            this.action = 'delete_user'
        },

        async doDeleteUsers(data) {
            if (!(await this.tutoringUserStore.deleteUsers(data))) return
            this.selected_users = []

            await this.tutoringUserStore.index()
            this.action = ''
        },

        async confirmUsers(users) {
            if (!(await this.tutoringUserStore.confirmUsers(users))) return
            await this.tutoringUserStore.index(this.meta.current_page)
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
        onUserClick(id) {
            this.selected_users = this.selected_users.includes(id)
                ? this.selected_users.filter((selectedId) => selectedId !== id)
                : [...this.selected_users, id]
        },
    },
}
</script>
<style scoped src="@/../css/admin-index-page.css"></style>
<style scoped src="@/../css/admin-tutoring-overview-cards.css"></style>
<style scoped src="@/../css/admin-tutoring-users-card.css"></style>
