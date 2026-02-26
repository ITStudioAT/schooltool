<template>
    <v-col cols="12" md="10" xl="9" v-if="users && action != 'create_user' && action != 'edit_user'">
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
                        <div v-for="item in users" :key="item.id" class="tutoring-users-row-stack">
                            <div
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
                                    <div class="tutoring-users-item-meta">
                                        <div class="tutoring-users-item-mail">{{ item.email }}</div>
                                        <button
                                            type="button"
                                            class="tutoring-users-offer-count"
                                            :class="{ 'is-expanded': expanded_offer_users.includes(item.id) }"
                                            @click.stop="toggleOffersExpand(item.id)">
                                            <v-icon size="13" icon="mdi-account-school-outline" />
                                            <span>{{ item.tutoring_offers_count ?? 0 }} Angebot<span v-if="(item.tutoring_offers_count ?? 0) !== 1">e</span></span>
                                            <v-icon
                                                size="13"
                                                :icon="expanded_offer_users.includes(item.id) ? 'mdi-chevron-up' : 'mdi-chevron-down'" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="tutoring-users-offers-expand" v-if="expanded_offer_users.includes(item.id)">
                                <div class="tutoring-users-offers-empty" v-if="!item.tutoring_offers || item.tutoring_offers.length === 0">
                                    Keine Angebote vorhanden.
                                </div>

                                <div class="tutoring-users-offers-list" v-else>
                                    <button
                                        v-for="offer in item.tutoring_offers"
                                        :key="offer.id"
                                        type="button"
                                        class="tutoring-users-offer-item"
                                        @click.stop="openOfferDialog(item, offer)">
                                        <div class="tutoring-users-offer-item__head">
                                            <div class="tutoring-users-offer-item__title">
                                                {{ offer.subject_short_name ? offer.subject_short_name + ': ' : '' }}{{ offer.title }}
                                            </div>
                                            <div class="tutoring-users-offer-item__status">
                                                <v-icon size="14" color="success" icon="mdi-check" v-if="offer.is_accepted" />
                                                <v-icon size="14" color="warning" icon="mdi-help" v-else />
                                                <v-icon size="14" color="success" icon="mdi-cloud" v-if="offer.is_active" />
                                                <v-icon size="14" color="warning" icon="mdi-cloud-off" v-else />
                                            </div>
                                        </div>
                                        <div class="tutoring-users-offer-item__meta">
                                            <span v-if="offer.subject_long_name">{{ offer.subject_long_name }}</span>
                                            <span v-if="offer.accepted_at">Genehmigt: {{ offer.accepted_at }}</span>
                                            <span v-else>Nicht genehmigt</span>
                                        </div>
                                    </button>
                                </div>
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

    <v-dialog v-model="offer_dialog_open" persistent max-width="820">
        <v-card class="tutoring-users-offer-dialog">
            <div class="tutoring-users-offer-dialog__head">
                <div class="tov-card-heading">
                    <div class="admin-card-eyebrow">Angebot</div>
                    <h3 class="admin-card-title">
                        {{ offer_dialog_offer?.subject_short_name ? offer_dialog_offer.subject_short_name + ': ' : '' }}{{ offer_dialog_offer?.title }}
                    </h3>
                </div>
                <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeOfferDialog" />
            </div>

            <div class="tutoring-users-offer-dialog__body" v-if="offer_dialog_offer">
                <div class="tutoring-users-offer-dialog__meta">
                    <span class="tutoring-users-offer-pill">
                        {{ offer_dialog_user?.last_name }} {{ offer_dialog_user?.first_name }}
                        <template v-if="offer_dialog_user?.schoolclass"> ({{ offer_dialog_user.schoolclass }})</template>
                    </span>
                    <span class="tutoring-users-offer-pill" v-if="offer_dialog_user?.email">{{ offer_dialog_user.email }}</span>
                    <span class="tutoring-users-offer-pill" v-if="offer_dialog_offer.subject_long_name">{{ offer_dialog_offer.subject_long_name }}</span>
                </div>

                <div class="tutoring-users-offer-dialog__grid">
                    <section class="tutoring-users-offer-dialog__panel">
                        <div class="tutoring-users-offer-dialog__label">Beschreibung</div>
                        <div class="tutoring-users-offer-dialog__text" style="white-space: pre-line">
                            {{ offer_dialog_offer.description || 'Keine Beschreibung' }}
                        </div>
                    </section>

                    <section class="tutoring-users-offer-dialog__panel">
                        <div class="tutoring-users-offer-dialog__label">Klassen</div>
                        <div class="tutoring-users-offer-dialog__class-list" v-if="offerClassLabels(offer_dialog_offer).length">
                            <span class="tutoring-users-offer-pill" v-for="label in offerClassLabels(offer_dialog_offer)" :key="label">{{ label }}</span>
                        </div>
                        <div class="tutoring-users-offer-dialog__text" v-else>Keine Angaben</div>
                    </section>

                    <section class="tutoring-users-offer-dialog__panel">
                        <div class="tutoring-users-offer-dialog__label">Unterricht</div>
                        <div class="tutoring-users-offer-dialog__stack">
                            <div>
                                {{ offer_dialog_offer.is_group ? `Gruppenangebot (max. ${offer_dialog_offer.max_group_members || '-'} Teilnehmer)` : 'Einzelunterricht' }}
                            </div>
                            <div>{{ formatOfferPrice(offer_dialog_offer.price_per_hour) }} pro Stunde</div>
                        </div>
                    </section>

                    <section class="tutoring-users-offer-dialog__panel">
                        <div class="tutoring-users-offer-dialog__label">Freigabe / Mentor</div>
                        <div class="tutoring-users-offer-dialog__stack">
                            <div v-if="!offer_dialog_offer.must_be_accepted">Automatisch freigegeben</div>
                            <div v-else>
                                {{ offer_dialog_offer.is_accepted ? `Genehmigt (${offer_dialog_offer.accepted_at || ''})` : 'Nicht genehmigt' }}
                            </div>
                            <div v-if="offer_dialog_offer.email_mentor">Mentor: {{ offer_dialog_offer.email_mentor }}</div>
                        </div>
                    </section>

                    <section class="tutoring-users-offer-dialog__panel">
                        <div class="tutoring-users-offer-dialog__label">Status</div>
                        <div class="tutoring-users-offer-dialog__stack">
                            <div>{{ offer_dialog_offer.is_active ? 'Online' : 'Offline' }}</div>
                            <div v-if="offer_dialog_offer.active_until">Aktiv bis: {{ offer_dialog_offer.active_until }}</div>
                            <div>Klicks: {{ offer_dialog_offer.click_count ?? 0 }}</div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="tutoring-users-offer-dialog__actions">
                <div class="tutoring-users-offer-dialog__action-buttons" v-if="offer_dialog_offer">
                    <v-btn
                        :color="offer_dialog_offer.is_accepted ? 'warning' : 'success'"
                        variant="tonal"
                        rounded="lg"
                        :prepend-icon="offer_dialog_offer.is_accepted ? 'mdi-help' : 'mdi-check'"
                        :loading="offer_dialog_saving"
                        :disabled="offer_dialog_saving"
                        @click="setDialogOfferAccepted(!offer_dialog_offer.is_accepted)">
                        {{ offer_dialog_offer.is_accepted ? 'Nicht genehmigt' : 'Genehmigt' }}
                    </v-btn>

                    <v-btn
                        :color="offer_dialog_offer.is_active ? 'warning' : 'success'"
                        variant="tonal"
                        rounded="lg"
                        :prepend-icon="offer_dialog_offer.is_active ? 'mdi-cloud-off' : 'mdi-cloud'"
                        :loading="offer_dialog_saving"
                        :disabled="offer_dialog_saving || (!offer_dialog_offer.is_active && !offer_dialog_offer.is_accepted)"
                        @click="setDialogOfferOnline(!offer_dialog_offer.is_active)">
                        {{ offer_dialog_offer.is_active ? 'Offline' : 'Online' }}
                    </v-btn>
                </div>

                <v-btn color="primary" rounded="lg" prepend-icon="mdi-close" @click="closeOfferDialog">Schließen</v-btn>
            </div>
        </v-card>
    </v-dialog>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

// SPECIFIC

import { useOfferStore } from '@/stores/admin/tutoring/OfferStore'
import { useTutoringUserStore } from '@/stores/admin/tutoring/UserStore'
import { useUserStore } from '@/stores/admin/UserStore20'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.offerStore = useOfferStore()
        this.tutoringUserStore = useTutoringUserStore()
        this.userStore = useUserStore()
        await this.tutoringUserStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            offerStore: null,
            tutoringUserStore: null,
            userStore: null,

            is_valid: false,
            upload_file: null,
            is_uploading: false,
            expanded_offer_users: [],
            offer_dialog_open: false,
            offer_dialog_offer: null,
            offer_dialog_user: null,
            offer_dialog_saving: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTutoringUserStore, ['users', 'meta', 'selected_users', 'search_string', 'data', 'answer', 'role', 'count_deletable_users', 'selected_filter']),
    },

    watch: {
        users() {
            this.expanded_offer_users = []
        },
    },

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
        toggleOffersExpand(userId) {
            this.expanded_offer_users = this.expanded_offer_users.includes(userId)
                ? this.expanded_offer_users.filter((id) => id !== userId)
                : [...this.expanded_offer_users, userId]
        },
        openOfferDialog(user, offer) {
            this.offer_dialog_user = {
                id: user.id,
                first_name: user.first_name,
                last_name: user.last_name,
                schoolclass: user.schoolclass,
                email: user.email,
            }
            this.offer_dialog_offer = JSON.parse(JSON.stringify(offer))
            this.offer_dialog_open = true
        },
        closeOfferDialog() {
            this.offer_dialog_open = false
            this.offer_dialog_offer = null
            this.offer_dialog_user = null
        },
        async refreshUsersAndSyncOfferDialog() {
            const page = this.meta?.current_page || 1
            const dialogUserId = this.offer_dialog_user?.id
            const dialogOfferId = this.offer_dialog_offer?.id

            await this.tutoringUserStore.index(page)

            if (!dialogUserId || !dialogOfferId) return

            const user = this.users.find((u) => u.id === dialogUserId)
            const offer = user?.tutoring_offers?.find((o) => o.id === dialogOfferId)

            if (!user || !offer) {
                this.closeOfferDialog()
                return
            }

            this.offer_dialog_user = {
                id: user.id,
                first_name: user.first_name,
                last_name: user.last_name,
                schoolclass: user.schoolclass,
                email: user.email,
            }
            this.offer_dialog_offer = JSON.parse(JSON.stringify(offer))
        },
        async setDialogOfferAccepted(accepted) {
            if (!this.offer_dialog_offer?.id) return
            this.offer_dialog_saving = true
            try {
                if (!(await this.offerStore.toggleAccepted(this.offer_dialog_offer.id, accepted))) return
                await this.refreshUsersAndSyncOfferDialog()
            } finally {
                this.offer_dialog_saving = false
            }
        },
        async setDialogOfferOnline(isActive) {
            if (!this.offer_dialog_offer?.id) return
            if (isActive && !this.offer_dialog_offer?.is_accepted) return
            this.offer_dialog_saving = true
            try {
                if (!(await this.offerStore.toggleActive(this.offer_dialog_offer.id, isActive))) return
                await this.refreshUsersAndSyncOfferDialog()
            } finally {
                this.offer_dialog_saving = false
            }
        },
        offerClassLabels(offer) {
            const classes = offer?.classes || {}
            return Object.entries(classes)
                .filter(([, value]) => !!value)
                .map(([key]) => Number(key))
                .filter((num) => Number.isFinite(num) && num > 0)
                .sort((a, b) => a - b)
                .map((num) => `${num}. Klasse`)
        },
        formatOfferPrice(value) {
            const num = Number(value)
            if (!Number.isFinite(num)) return `${value ?? '-'} Euro`
            return `${num.toFixed(2).replace('.', ',')} Euro`
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
