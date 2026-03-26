<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Benutzer" icon="mdi-account-multiple-outline">
            <template #header-actions>
                <div class="d-flex ga-2 align-center flex-wrap">
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
                                        :color="user.has_sepa ? 'success' : 'secondary'"
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
                                        :color="user.is_confirmed ? 'success' : 'secondary'"
                                        variant="tonal">
                                        {{ user.is_confirmed ? 'Bestätigt' : 'Offen' }}
                                    </v-chip>
                                </div>

                                <v-btn
                                    size="small"
                                    :color="user.has_sepa ? 'warning' : 'success'"
                                    :variant="user.has_sepa ? 'outlined' : 'flat'"
                                    :prepend-icon="user.has_sepa ? 'mdi-close-circle-outline' : 'mdi-check-circle-outline'"
                                    :loading="sepaUserId === user.id"
                                    :disabled="sepaUserId === user.id"
                                    @click="toggleSepa(user)">
                                    {{ user.has_sepa ? 'SEPA entfernen' : 'SEPA bestätigen' }}
                                </v-btn>
                            </div>
                        </div>

                        <div class="restaurant-users-card__meta">
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
        await this.restaurantUserStore.index()
    },

    data() {
        return {
            restaurantUserStore: null,
            searchDraft: '',
            sepaUserId: null,
        }
    },

    computed: {
        ...mapState(useRestaurantUserStore, ['users', 'meta', 'search_string']),
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
    },

    methods: {
        fullName(user) {
            const firstName = String(user?.first_name || '').trim()
            const lastName = String(user?.last_name || '').trim()
            const fullName = `${lastName} ${firstName}`.trim()

            return fullName === '' ? user?.email || 'Unbekannt' : fullName
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
        async toggleSepa(user) {
            this.sepaUserId = user?.id ?? null

            try {
                await this.restaurantUserStore.updateSepa(user.id, ! user.has_sepa)
            } finally {
                this.sepaUserId = null
            }
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
