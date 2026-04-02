<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Benutzer" icon="mdi-account-multiple-outline">
            <template #header-actions>
                <div class="d-flex ga-2 align-center flex-wrap">
                    <div class="restaurant-users-header-count" :class="{ 'is-attention': pendingConfirmationCount > 0 }">
                        <span class="restaurant-users-header-count__label">Zu bestätigen</span>
                        <span class="restaurant-users-header-count__value">{{ pendingConfirmationCount }}</span>
                    </div>

                    <v-btn
                        size="small"
                        :color="only_pending_confirmation ? 'error' : 'secondary'"
                        :variant="only_pending_confirmation ? 'flat' : 'tonal'"
                        prepend-icon="mdi-filter-check-outline"
                        @click="togglePendingConfirmationFilter">
                        {{ only_pending_confirmation ? 'Alle Benutzer' : 'Nur zu bestätigen' }}
                    </v-btn>

                    <v-btn
                        size="small"
                        color="warning"
                        variant="flat"
                        prepend-icon="mdi-refresh"
                        @click="reloadUsers">
                        Aktualisieren
                    </v-btn>
                </div>
            </template>

            <div class="restaurant-users-search-panel mb-4">
                <div class="restaurant-users-search-panel__copy">
                    <div class="restaurant-users-search-panel__eyebrow">Restaurant</div>
                    <div class="restaurant-users-search-panel__headline">Restaurant-Benutzer suchen</div>
                    <div class="restaurant-users-search-panel__meta">
                        Suche nach Name, E-Mail-Adresse oder Klasse.
                    </div>
                </div>

                <div class="restaurant-users-search-panel__actions">
                    <v-text-field
                        v-model="searchDraft"
                        label="Benutzer suchen"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        hide-details
                        class="restaurant-users-search-panel__field"
                        @keyup.enter="applySearch"
                        @click:clear="clearSearch" />

                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-magnify"
                        @click="applySearch">
                        Suchen
                    </v-btn>
                </div>
            </div>

            <div v-if="only_pending_confirmation" class="restaurant-users-filter-banner mb-4">
                <div class="restaurant-users-filter-banner__copy">
                    <div class="restaurant-users-filter-banner__eyebrow">Filter aktiv</div>
                    <div class="restaurant-users-filter-banner__title">Es werden nur Benutzer angezeigt, die noch bestätigt werden müssen.</div>
                    <div class="restaurant-users-filter-banner__meta">
                        Aktuell offen: {{ pendingConfirmationCount }}
                    </div>
                </div>

                <v-btn
                    size="small"
                    color="error"
                    variant="flat"
                    prepend-icon="mdi-close-circle-outline"
                    @click="togglePendingConfirmationFilter">
                    Filter aufheben
                </v-btn>
            </div>

            <div class="restaurant-users-toolbar mb-4">
                <div class="restaurant-users-summary__pill">
                    <span class="restaurant-users-summary__label">Treffer</span>
                    <span class="restaurant-users-summary__value">{{ totalUsers }}</span>
                </div>

                <div class="restaurant-users-summary__pill" v-if="activeSearchLabel">
                    <span class="restaurant-users-summary__label">Filter</span>
                    <span class="restaurant-users-summary__value">{{ activeSearchLabel }}</span>
                </div>

                <div class="restaurant-users-toolbar__spacer" />

                <div class="restaurant-users-toolbar__range text-body-2 text-medium-emphasis" v-if="totalUsers > 0">
                    {{ paginationSummary }}
                </div>
            </div>

            <v-alert v-if="!users.length" type="info" variant="tonal" class="mb-3">
                Keine Restaurant-Benutzer gefunden.
            </v-alert>

            <div v-else class="restaurant-users-list">
                <v-card
                    v-for="user in users"
                    :key="user.id"
                    rounded="xl"
                    variant="outlined"
                    class="restaurant-users-card">
                    <v-card-text class="restaurant-users-card__body pa-3">
                        <div class="restaurant-users-card__head">
                            <div class="restaurant-users-card__identity">
                                <div class="restaurant-users-card__name">
                                    {{ fullName(user) }}
                                </div>
                                <div class="restaurant-users-card__mail">
                                    {{ user.email }}
                                </div>
                            </div>

                            <div class="restaurant-users-card__controls">
                                <div class="restaurant-users-card__state">
                                    <v-chip
                                        size="x-small"
                                        :color="user.has_sepa ? 'success' : 'error'"
                                        variant="tonal">
                                        {{ user.has_sepa ? 'SEPA' : 'Kein SEPA' }}
                                    </v-chip>

                                    <v-chip
                                        size="x-small"
                                        :color="user.is_verified ? 'success' : 'warning'"
                                        variant="tonal">
                                        {{ user.is_verified ? 'E-Mail ok' : 'E-Mail offen' }}
                                    </v-chip>

                                    <v-chip
                                        size="x-small"
                                        :color="user.is_restaurant_confirmed ? 'success' : 'secondary'"
                                        variant="tonal">
                                        {{ user.is_confirmed ? 'Bestätigt' : 'Offen' }}
                                    </v-chip>

                                    <span class="text-caption text-medium-emphasis">
                                        {{ user.is_restaurant_confirmed ? 'Restaurant bestaetigt' : 'Restaurant offen' }}
                                    </span>
                                </div>

                                <v-btn
                                    v-if="user.roles?.includes('lunch_candidate')"
                                    size="small"
                                    color="primary"
                                    variant="flat"
                                    prepend-icon="mdi-check-decagram-outline"
                                    :loading="confirmUserId === user.id"
                                    :disabled="confirmUserId === user.id || !user.is_verified"
                                    @click="confirmRestaurantUser(user)">
                                    Bestätigen
                                </v-btn>

                                <v-btn
                                    v-if="user.roles?.includes('lunch_candidate')"
                                    size="small"
                                    color="error"
                                    variant="text"
                                    prepend-icon="mdi-delete-outline"
                                    :loading="deleteUserId === user.id"
                                    :disabled="deleteUserId === user.id"
                                    @click="openDeleteCandidateDialog(user)">
                                    Löschen
                                </v-btn>

                                <v-btn
                                    size="small"
                                    :color="user.has_sepa ? 'warning' : 'success'"
                                    :variant="user.has_sepa ? 'outlined' : 'flat'"
                                    :prepend-icon="user.has_sepa ? 'mdi-close-circle-outline' : 'mdi-check-circle-outline'"
                                    :loading="sepaUserId === user.id"
                                    :disabled="sepaUserId === user.id || user.roles?.includes('lunch_candidate')"
                                    @click="toggleSepa(user)">
                                    {{ user.has_sepa ? 'SEPA entfernen' : 'SEPA bestätigen' }}
                                </v-btn>
                            </div>
                        </div>

                        <div class="restaurant-users-card__meta">
                            <div class="restaurant-users-card__meta-item restaurant-users-card__meta-item--origins">
                                <v-icon size="16" icon="mdi-source-branch" />
                                <div class="restaurant-users-card__origins">
                                    <span class="restaurant-users-card__origins-label">Herkunft</span>
                                    <div class="restaurant-users-card__origins-list">
                                        <v-chip
                                            v-for="originLabel in originLabels(user)"
                                            :key="`${user.id}-${originLabel}`"
                                            size="x-small"
                                            variant="tonal"
                                            color="info">
                                            {{ originLabel }}
                                        </v-chip>
                                    </div>
                                </div>
                            </div>

                            <div class="restaurant-users-card__meta-item" v-if="user.import116_id">
                                <v-icon size="16" icon="mdi-database-import-outline" />
                                <span>Import116 #{{ user.import116_id }}</span>
                            </div>

                            <div class="restaurant-users-card__meta-item" v-if="user.schoolclass">
                                <v-icon size="16" icon="mdi-school-outline" />
                                <span>{{ user.schoolclass }}</span>
                            </div>

                            <div class="restaurant-users-card__meta-item" v-if="user.phone">
                                <v-icon size="16" icon="mdi-phone-outline" />
                                <span>{{ user.phone }}</span>
                            </div>
                        </div>

                        <div class="restaurant-users-card__children" v-if="user.import116_children?.length">
                            <div class="restaurant-users-card__children-label">
                                Kinder
                            </div>

                            <div class="restaurant-users-card__children-list">
                                <div
                                    v-for="(child, index) in user.import116_children"
                                    :key="`${user.id}-child-${index}`"
                                    class="restaurant-users-card__children-item">
                                    <span class="restaurant-users-card__children-name">{{ child.name || 'Unbekannt' }}</span>
                                    <span
                                        v-if="child.email"
                                        class="restaurant-users-card__children-email">
                                        {{ child.email }}
                                    </span>
                                </div>
                            </div>
                        </div>

                    </v-card-text>
                </v-card>
            </div>

            <div class="restaurant-users-pagination" v-if="totalUsers > 0">
                <div class="text-body-2 text-medium-emphasis">
                    Seite {{ currentPage }} von {{ lastPage }}
                </div>

                <v-pagination
                    v-if="lastPage > 1"
                    v-model="currentPage"
                    :length="lastPage"
                    :total-visible="6"
                    density="comfortable"
                    @update:model-value="handlePageChange" />
            </div>

            <v-dialog v-model="deleteDialog" max-width="460" persistent>
                <v-card rounded="xl">
                    <v-card-title>Kandidat löschen</v-card-title>

                    <v-card-text>
                        <div class="text-body-1">
                            Soll die ausstehende Restaurant-Anmeldung von
                            <strong>{{ fullName(pendingDeleteUser) }}</strong>
                            wirklich gelöscht werden?
                        </div>

                        <div v-if="pendingDeleteUser?.email" class="text-body-2 text-medium-emphasis mt-3">
                            {{ pendingDeleteUser.email }}
                        </div>
                    </v-card-text>

                    <v-card-actions class="px-6 pb-5">
                        <v-spacer />
                        <v-btn variant="text" @click="closeDeleteCandidateDialog">Abbrechen</v-btn>
                        <v-btn
                            color="error"
                            variant="flat"
                            :loading="deleteUserId === pendingDeleteUser?.id"
                            :disabled="deleteUserId === pendingDeleteUser?.id"
                            @click="confirmDeleteCandidate">
                            Löschen
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useRestaurantUserStore } from '@/stores/admin/restaurant/RestaurantUserStore'

export default {
    components: { ItsGridBox },

    async beforeMount() {
        this.restaurantUserStore = useRestaurantUserStore()
        this.searchDraft = this.search_string || ''
        this.syncPendingConfirmationFilterFromRoute()
        await this.restaurantUserStore.index()
    },

    data() {
        return {
            restaurantUserStore: null,
            searchDraft: '',
            sepaUserId: null,
            confirmUserId: null,
            deleteUserId: null,
            deleteDialog: false,
            pendingDeleteUser: null,
        }
    },

    computed: {
        ...mapState(useRestaurantUserStore, ['users', 'meta', 'search_string', 'only_pending_confirmation']),
        currentPage() {
            return Number(this.meta?.current_page || 1)
        },
        lastPage() {
            return Number(this.meta?.last_page || 1)
        },
        totalUsers() {
            return Number(this.meta?.total || 0)
        },
        paginationSummary() {
            if (! this.totalUsers) {
                return '0 - 0 von 0'
            }

            return `${this.meta?.from || 1} - ${this.meta?.to || this.users.length} von ${this.totalUsers}`
        },
        activeSearchLabel() {
            const normalized = String(this.search_string || '').trim()

            return normalized === '' ? '' : `"${normalized}"`
        },
        pendingConfirmationCount() {
            return Number(this.meta?.pending_confirmation_total || 0)
        },
    },

    watch: {
        '$route.query.only_pending_confirmation'() {
            this.syncPendingConfirmationFilterFromRoute()
        },
    },

    methods: {
        fullName(user) {
            const firstName = String(user?.first_name || '').trim()
            const lastName = String(user?.last_name || '').trim()
            const fullName = `${lastName} ${firstName}`.trim()

            return fullName === '' ? user?.email || 'Unbekannt' : fullName
        },
        originLabels(user) {
            const labels = Array.isArray(user?.origin_labels) ? user.origin_labels : []

            return labels.length > 0 ? labels : ['Extern']
        },
        async applySearch() {
            this.restaurantUserStore.search_string = String(this.searchDraft || '').trim()
            await this.restaurantUserStore.index(1)
        },
        async clearSearch() {
            this.searchDraft = ''
            this.restaurantUserStore.search_string = ''
            await this.restaurantUserStore.index(1)
        },
        async reloadUsers() {
            await this.restaurantUserStore.index(this.currentPage)
        },
        async handlePageChange(page) {
            await this.restaurantUserStore.index(page)
        },
        async togglePendingConfirmationFilter() {
            this.restaurantUserStore.only_pending_confirmation = ! this.restaurantUserStore.only_pending_confirmation
            this.syncPendingConfirmationFilterRoute()
            await this.restaurantUserStore.index(1)
        },
        async toggleSepa(user) {
            this.sepaUserId = user?.id ?? null

            try {
                await this.restaurantUserStore.updateSepa(user.id, ! user.has_sepa)
            } finally {
                this.sepaUserId = null
            }
        },
        async confirmRestaurantUser(user) {
            this.confirmUserId = user?.id ?? null

            try {
                const confirmedUser = await this.restaurantUserStore.confirmUser(user.id)

                if (confirmedUser && this.restaurantUserStore.only_pending_confirmation) {
                    await this.restaurantUserStore.index(this.currentPage)
                }
            } finally {
                this.confirmUserId = null
            }
        },
        openDeleteCandidateDialog(user) {
            this.pendingDeleteUser = user ?? null
            this.deleteDialog = true
        },
        closeDeleteCandidateDialog() {
            this.deleteDialog = false
            this.pendingDeleteUser = null
        },
        async confirmDeleteCandidate() {
            if (! this.pendingDeleteUser?.id) {
                return
            }

            const targetPage = this.users.length === 1 && this.currentPage > 1
                ? this.currentPage - 1
                : this.currentPage

            this.deleteUserId = this.pendingDeleteUser.id

            try {
                const deleted = await this.restaurantUserStore.destroyCandidate(this.pendingDeleteUser.id)

                if (deleted) {
                    await this.restaurantUserStore.index(targetPage)
                    this.closeDeleteCandidateDialog()
                }
            } finally {
                this.deleteUserId = null
            }
        },
        syncPendingConfirmationFilterFromRoute() {
            const rawValue = this.$route?.query?.only_pending_confirmation

            this.restaurantUserStore.only_pending_confirmation = rawValue === '1' || rawValue === 1 || rawValue === true || rawValue === 'true'
        },
        syncPendingConfirmationFilterRoute() {
            const query = {
                ...(this.$route?.query || {}),
            }

            if (this.restaurantUserStore.only_pending_confirmation) {
                query.only_pending_confirmation = '1'
            } else {
                delete query.only_pending_confirmation
            }

            this.$router.replace({
                query,
            }).catch(() => {})
        },
    },
}
</script>

<style scoped>
.restaurant-users-search-panel {
    display: flex;
    gap: 1rem;
    align-items: end;
    justify-content: space-between;
    flex-wrap: wrap;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(14, 116, 144, 0.12);
    border-radius: 1.1rem;
    background:
        radial-gradient(circle at top left, rgba(224, 242, 254, 0.78), transparent 36%),
        linear-gradient(135deg, rgba(248, 250, 252, 0.98), rgba(241, 245, 249, 0.92));
    box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.42);
}

.restaurant-users-filter-banner {
    display: flex;
    gap: 1rem;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    padding: 0.95rem 1rem;
    border: 1px solid rgba(220, 38, 38, 0.18);
    border-radius: 1.1rem;
    background:
        radial-gradient(circle at top left, rgba(254, 202, 202, 0.72), transparent 38%),
        linear-gradient(135deg, rgba(254, 242, 242, 0.98), rgba(254, 226, 226, 0.94));
    box-shadow: 0 16px 32px -28px rgba(127, 29, 29, 0.28);
}

.restaurant-users-filter-banner__copy {
    display: grid;
    gap: 0.12rem;
}

.restaurant-users-filter-banner__eyebrow {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgb(185, 28, 28);
}

.restaurant-users-filter-banner__title {
    font-size: 1rem;
    font-weight: 800;
    color: rgb(127, 29, 29);
}

.restaurant-users-filter-banner__meta {
    font-size: 0.9rem;
    color: rgba(127, 29, 29, 0.88);
}

.restaurant-users-header-count {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.7rem;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(255, 255, 255, 0.92);
    color: rgba(15, 23, 42, 0.96);
}

.restaurant-users-header-count.is-attention {
    border-color: rgba(220, 38, 38, 0.24);
    background: rgba(254, 226, 226, 0.92);
    color: rgb(153, 27, 27);
}

.restaurant-users-header-count__label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.restaurant-users-header-count__value {
    font-size: 0.94rem;
    font-weight: 800;
}

.restaurant-users-search-panel__copy {
    display: grid;
    gap: 0.1rem;
    max-width: 26rem;
}

.restaurant-users-search-panel__eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(8, 145, 178, 0.92);
}

.restaurant-users-search-panel__headline {
    font-size: 1rem;
    font-weight: 700;
}

.restaurant-users-search-panel__meta {
    font-size: 0.9rem;
    color: rgba(71, 85, 105, 0.92);
}

.restaurant-users-search-panel__actions {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    flex-wrap: wrap;
    margin-left: auto;
}

.restaurant-users-search-panel__field {
    flex: 1 1 18rem;
    min-width: min(100%, 16rem);
}

.restaurant-users-toolbar {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    flex-wrap: wrap;
}

.restaurant-users-summary__pill {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.55rem 0.8rem;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.38);
}

.restaurant-users-toolbar__spacer {
    flex: 1 1 auto;
}

.restaurant-users-toolbar__range {
    margin-left: auto;
}

.restaurant-users-summary__label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(71, 85, 105, 0.92);
}

.restaurant-users-summary__value {
    font-weight: 700;
    color: rgb(15, 23, 42);
}

.restaurant-users-list {
    display: grid;
    gap: 0.65rem;
}

.restaurant-users-card {
    background: rgba(255, 255, 255, 0.96);
    border-color: rgba(15, 23, 42, 0.1);
}

.restaurant-users-card__body {
    display: grid;
    gap: 0.55rem;
}

.restaurant-users-card__head {
    display: flex;
    justify-content: space-between;
    gap: 0.8rem;
    align-items: center;
    flex-wrap: wrap;
}

.restaurant-users-card__identity {
    min-width: 0;
}

.restaurant-users-card__name {
    font-size: 0.96rem;
    font-weight: 700;
    color: rgb(15, 23, 42);
}

.restaurant-users-card__mail {
    margin-top: 0.1rem;
    font-size: 0.88rem;
    color: rgba(71, 85, 105, 0.92);
    word-break: break-word;
}

.restaurant-users-card__state {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.restaurant-users-card__controls {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.55rem;
    flex-wrap: wrap;
}

.restaurant-users-card__meta {
    display: flex;
    gap: 0.8rem;
    row-gap: 0.35rem;
    flex-wrap: wrap;
    margin-top: 0.1rem;
    font-size: 0.84rem;
    color: rgba(51, 65, 85, 0.94);
}

.restaurant-users-card__meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
}

.restaurant-users-card__meta-item--origins {
    align-items: flex-start;
}

.restaurant-users-card__origins {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.restaurant-users-card__origins-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(71, 85, 105, 0.92);
}

.restaurant-users-card__origins-list {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.restaurant-users-card__children {
    display: grid;
    gap: 0.35rem;
    padding: 0.65rem 0.75rem;
    border-radius: 0.8rem;
    background: rgba(248, 250, 252, 0.96);
    border: 1px solid rgba(148, 163, 184, 0.16);
}

.restaurant-users-card__children-label {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(71, 85, 105, 0.92);
}

.restaurant-users-card__children-list {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.restaurant-users-card__children-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.32rem 0.55rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(148, 163, 184, 0.18);
    font-size: 0.82rem;
}

.restaurant-users-card__children-name {
    font-weight: 600;
    color: rgb(15, 23, 42);
}

.restaurant-users-card__children-email {
    color: rgba(71, 85, 105, 0.92);
}

.restaurant-users-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.9rem;
    padding-top: 0.85rem;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
}

@media (max-width: 760px) {
    .restaurant-users-toolbar__range {
        width: 100%;
        margin-left: 0;
    }

    .restaurant-users-card__head {
        align-items: flex-start;
    }

    .restaurant-users-card__controls {
        justify-content: flex-start;
    }
}
</style>
