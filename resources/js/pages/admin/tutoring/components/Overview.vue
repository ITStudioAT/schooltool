<template>
    <!-- FREIZUGEBENDE ANGEBOTE (CRUD style) -->
    <v-col cols="12" xl="11" v-if="offers !== null">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Freigabe</div>
                    <h2 class="admin-card-title crud-title">Angebote</h2>
                </div>
                <div class="admin-kpi-grid crud-kpis" v-if="stats">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Offen</div>
                        <div class="kpi-value">{{ stats.count - stats.accepted_count }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <!-- Hauptliste -->
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField :store="offerStore" selected_field="selected_offers" />
                        </div>
                        <div class="tov-filter-groups">
                            <div class="tov-filter-group">
                                <div class="tov-filter-label-row">
                                    <div class="tov-filter-label">Genehmigung</div>
                                    <v-icon size="14" icon="mdi-shield-check-outline" class="tov-filter-label-icon" />
                                </div>
                                <div class="d-flex flex-row flex-wrap align-center ga-1 tov-filter-options">
                                    <v-btn :color="select_accepted == 'all' ? 'primary' : 'secondary'" variant="tonal" rounded="pill" size="small" class="text-caption tov-filter-btn" @click="toggleAccepted('all')" :disabled="action != ''">Alle</v-btn>
                                    <v-btn
                                        :color="select_accepted == 'no' ? 'warning' : 'secondary'"
                                        variant="tonal"
                                        rounded="pill"
                                        size="small"
                                        class="text-caption tov-filter-btn tov-filter-btn--open"
                                        @click="toggleAccepted('no')"
                                        :disabled="action != ''">
                                        Nur Offene
                                    </v-btn>
                                    <v-btn
                                        :color="select_accepted == 'yes' ? 'success' : 'secondary'"
                                        variant="tonal"
                                        rounded="pill"
                                        size="small"
                                        class="text-caption tov-filter-btn tov-filter-btn--accepted"
                                        @click="toggleAccepted('yes')"
                                        :disabled="action != ''">
                                        Nur Genehmigte
                                    </v-btn>
                                </div>
                            </div>
                            <div class="tov-filter-group">
                                <div class="tov-filter-label-row">
                                    <div class="tov-filter-label">Online</div>
                                    <v-icon size="14" icon="mdi-cloud-outline" class="tov-filter-label-icon" />
                                </div>
                                <div class="d-flex flex-row flex-wrap align-center ga-1 tov-filter-options">
                                    <v-btn :color="select_online == 'all' ? 'primary' : 'secondary'" variant="tonal" rounded="pill" size="small" class="text-caption tov-filter-btn" @click="toggleOnline('all')" :disabled="action != ''">Alle</v-btn>
                                    <v-btn :color="select_online == 'yes' ? 'success' : 'secondary'" variant="tonal" rounded="pill" size="small" class="text-caption tov-filter-btn" @click="toggleOnline('yes')" :disabled="action != ''">Nur Online</v-btn>
                                    <v-btn :color="select_online == 'no' ? 'warning' : 'secondary'" variant="tonal" rounded="pill" size="small" class="text-caption tov-filter-btn" @click="toggleOnline('no')" :disabled="action != ''">Nur Offline</v-btn>
                                </div>
                            </div>
                            <div class="tov-filter-group">
                                <div class="tov-filter-label-row">
                                    <div class="tov-filter-label">Auswahl</div>
                                    <div class="tov-filter-count">{{ selected_offers.length }}/{{ offers.length }}</div>
                                </div>
                                <div class="d-flex flex-row flex-wrap align-center ga-1 tov-filter-options">
                                    <v-btn
                                        color="primary"
                                        variant="tonal"
                                        rounded="pill"
                                        size="small"
                                        class="text-caption tov-filter-btn"
                                        @click="selectAll"
                                        :disabled="action != '' || offers.length === 0 || selected_offers.length === offers.length">
                                        Alle auswählen
                                    </v-btn>
                                    <v-btn
                                        color="secondary"
                                        variant="tonal"
                                        rounded="pill"
                                        size="small"
                                        class="text-caption tov-filter-btn"
                                        @click="unselectAll"
                                        :disabled="action != '' || selected_offers.length === 0">
                                        Auswahl löschen
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="empty-state" v-if="offers.length === 0">Keine Angebote gefunden.</div>
                    <div v-else>
                        <div
                            v-for="item in offers"
                            :key="item.id"
                            class="crud-list-item tov-offer-item"
                            :class="{ 'is-selected': selected_offers.includes(item.id) }"
                            @click="onOfferClick(item.id)">
                            <div class="tov-offer-select">
                                <v-icon
                                    size="18"
                                    :icon="selected_offers.includes(item.id) ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'"
                                    :color="selected_offers.includes(item.id) ? 'primary' : 'grey-darken-1'" />
                            </div>
                            <div class="tov-offer-status">
                                <v-icon size="16" color="success" icon="mdi-cloud-check" v-if="item.is_active" />
                                <v-icon size="16" color="error-lighten-3" icon="mdi-cloud-off" v-if="!item.is_active" />
                                <v-icon size="16" color="error" icon="mdi-lock" v-if="!item?.user?.is_active" />
                            </div>
                            <div class="person-body tov-offer-copy">
                                <div class="person-name">{{ item.subject.short_name }}: {{ item.title }}</div>
                                <div class="person-email">{{ item?.user?.last_name }} {{ item?.user?.first_name }} ({{ item?.user?.schoolclass }}, {{ item?.user?.email }})</div>
                                <div class="tov-offer-mentor" v-if="!item.accepted_at">
                                    <v-icon size="14" color="warning" icon="mdi-help" />
                                    <span>{{ item.email_mentor }}</span>
                                </div>
                                <div class="tov-offer-mentor tov-offer-mentor--accepted" v-if="item.accepted_at">
                                    <v-icon size="14" color="success" icon="mdi-check" />
                                    <span>{{ item.email_mentor }} ({{ item.accepted_at }})</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="crud-pagination mt-3" v-if="meta">
                        <Pagination :meta="meta" :store="offerStore" selected_field="selected_offers" />
                    </div>
                </section>

                <!-- Aktions-Sidebar -->
                <aside class="crud-side-stack">
                    <!-- Aktionen -->
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div class="tov-card-heading">
                                <div class="admin-card-eyebrow">Aktionen</div>
                                <h3 class="admin-card-title">Angebot verwalten</h3>
                            </div>
                        </div>
                        <div class="kpi-sub" style="margin-top: -2px">Verfügbare Schritte für die aktuelle Auswahl.</div>

                        <template v-if="selected_offers.length >= 1">
                            <div class="kpi-sub mt-2">{{ selected_offers.length }} ausgewählt</div>
                            <div class="d-grid ga-2 mt-3">
                                <v-btn block variant="tonal" rounded="lg" color="warning" class="text-caption" prepend-icon="mdi-help" @click="setSelectedOffersAccepted(false)" v-if="canBulkRejectOffers">Nicht genehm.</v-btn>
                                <v-btn block variant="tonal" rounded="lg" color="success" class="text-caption" prepend-icon="mdi-check" @click="setSelectedOffersAccepted(true)" v-if="canBulkAcceptOffers">Genehmigen</v-btn>
                            </div>

                            <v-divider class="my-3 opacity-30" />
                            <div class="d-grid ga-2">
                                <v-btn block variant="tonal" rounded="lg" color="error" class="text-caption" prepend-icon="mdi-lock" @click="setSelectedUsersActive(false)" v-if="canBulkBlockUsers">Sperren</v-btn>
                                <v-btn block variant="tonal" rounded="lg" color="success" class="text-caption" prepend-icon="mdi-lock-open" @click="setSelectedUsersActive(true)" v-if="canBulkUnblockUsers">Entsperren</v-btn>
                            </div>

                            <template v-if="selectionHasAcceptedOffers">
                                <v-divider class="my-3 opacity-30" />
                                <div class="d-grid ga-2">
                                    <v-btn block variant="tonal" rounded="lg" color="warning" class="text-caption" prepend-icon="mdi-cloud-off" @click="setSelectedOffersOnline(false)" v-if="canBulkSetOffline">Offline</v-btn>
                                    <v-btn block variant="tonal" rounded="lg" color="success" class="text-caption" prepend-icon="mdi-cloud" @click="setSelectedOffersOnline(true)" v-if="canBulkSetOnline">Online</v-btn>
                                </div>
                            </template>

                            <v-divider class="my-3 opacity-30" />
                            <div class="d-grid ga-2">
                                <v-btn block variant="tonal" rounded="lg" color="warning" class="text-caption" prepend-icon="mdi-delete" @click="delete_level++" v-if="delete_level == 0">Löschen</v-btn>
                                <v-btn block variant="tonal" rounded="lg" color="success" class="text-caption" prepend-icon="mdi-delete-off" @click="delete_level = 0" v-if="delete_level == 1">Abbruch</v-btn>
                                <v-btn block variant="tonal" rounded="lg" color="error" class="text-caption mt-2" prepend-icon="mdi-delete" @click="deleteSelectedOffers" v-if="delete_level == 1">Löschen</v-btn>
                            </div>

                            <div class="kpi-sub mt-3" v-if="showMixedSelectionHint">
                                Gemischte Auswahl: Einige Aktionen sind nur verfügbar, wenn alle ausgewählten Angebote denselben Status haben.
                            </div>
                        </template>

                        <div v-else class="kpi-sub mt-3">Kein Angebot ausgewählt.</div>
                    </section>

                    <!-- Statistik -->
                    <section class="admin-card ai-glass-panel" v-if="stats">
                        <div class="admin-card-head mb-2">
                            <div class="tov-card-heading">
                                <div class="admin-card-eyebrow">Übersicht</div>
                                <h3 class="admin-card-title">Statistik</h3>
                            </div>
                        </div>
                        <div class="d-grid ga-2 tov-stats-grid">
                            <div class="kpi-card ai-glass-panel">
                                <div class="kpi-label">Angebote gesamt</div>
                                <div class="kpi-value">{{ stats.count }}</div>
                            </div>
                            <div class="kpi-card ai-glass-panel">
                                <div class="kpi-label">Freigegeben</div>
                                <div class="kpi-value">{{ stats.accepted_count }}</div>
                            </div>
                            <div class="kpi-card ai-glass-panel">
                                <div class="kpi-label">Online</div>
                                <div class="kpi-value">{{ stats.online_count }}</div>
                            </div>
                            <div class="kpi-card ai-glass-panel">
                                <div class="kpi-label">Schüler:innen</div>
                                <div class="kpi-value">{{ stats.users_count }}</div>
                            </div>
                            <div class="kpi-card ai-glass-panel">
                                <div class="kpi-label">Mit Angeboten</div>
                                <div class="kpi-value">{{ stats.students_count }}</div>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <!-- ##### ANGEBOT IM DETAIL ##### -->
    <v-col cols="12" md="6" xl="4" v-if="selected_offers.length == 1">
        <its-grid-box
            color="primary"
            :subtitle="selectedOffer.subject?.long_name"
            :title="selectedOffer.subject?.short_name + ': ' + selectedOffer?.title"
            class="w-100"
            :disabled="action != ''">
            <!-- Beschreibung -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Beschreibung des Angebots:</label>
                <div class="text-body-1" style="white-space: pre-line">
                    {{ selectedOffer.description }}
                </div>
            </div>

            <!-- Anbieter -->
            <div>
                <label class="text-subtitle-2 mt-2 d-block">Anbieter:</label>
                <div class="text-body-1">
                    <div class="d-flex align-center ga-2">
                        <div>
                            {{ selectedOffer.user.last_name + ' ' + selectedOffer.user.first_name }}
                        </div>
                        <div>{{ selectedOffer.user.schoolclass }}</div>
                    </div>
                    <div class="d-flex flex-row align-center ga-2">
                        <v-icon icon="mdi-mail" />
                        <div>{{ selectedOffer.user.email }}</div>
                    </div>
                </div>
            </div>

            <!-- Klassen  -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Klassen:</label>
                <div v-if="unterstufeClasses.length > 0" class="mb-2">
                    <strong>Unterstufe:</strong>
                    <div class="text-body-2">
                        {{ unterstufeClasses.join(', ') }}
                    </div>
                </div>
                <div v-if="oberstufeClasses.length > 0">
                    <strong>Oberstufe:</strong>
                    <div class="text-body-2">
                        {{ oberstufeClasses.join(', ') }}
                    </div>
                </div>
            </div>

            <!-- Gruppenangebot -->
            <div>
                <label class="text-subtitle-2 mt-2 d-block">Gruppenangebot:</label>
                <div class="text-body-1" v-if="!selectedOffer.is_group">NEIN (nur Einzelunterricht)</div>
                <div class="text-body-1" v-if="selectedOffer.is_group">JA (maximal {{ selectedOffer.max_group_members }} Teilnehmer)</div>
            </div>

            <!-- Kosten -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Kosten pro Stunde:</label>
                <div class="text-body-1">
                    {{ parseFloat(selectedOffer.price_per_hour) + ' Euro' }}
                </div>
            </div>

            <!-- Freigabe -->
            <div>
                <label class="text-subtitle-2 mt-2 d-block">Freigabe des Angebots:</label>
                <div class="text-body-1" v-if="!selectedOffer.must_be_accepted">Angebot wurde automatisch freigegeben.</div>
                <div class="text-body-1" v-if="selectedOffer.must_be_accepted">
                    <div>Freigabe muss erteilt werden</div>
                    <div class="d-flex flex-row align-center ga-2">
                        <div>Freigabe:</div>
                        <div class="d-flex flex-row align-center ga-2" v-if="selectedOffer.accepted_at">
                            <div>Freigabe erteilt!</div>
                            <v-icon icon="mdi-check" color="success" size="small" />
                        </div>
                        <div class="d-flex flex-row align-center ga-2" v-if="!selectedOffer.accepted_at">
                            <div>ausstehend!</div>
                            <v-icon icon="mdi-help" color="warning" size="small" />
                        </div>
                    </div>
                    <div class="d-flex flex-row align-center ga-2">
                        <div>Mentor:</div>
                        <div class="d-flex flex-row align-center ga-2">
                            <v-icon icon="mdi-mail" />
                            {{ selectedOffer?.email_mentor }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Online/Offline -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Status:</label>
                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="selectedOffer.is_active">
                    <v-icon icon="mdi-cloud" color="success" />
                    <div>ONLINE</div>
                </div>
                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="!selectedOffer.is_active">
                    <v-icon icon="mdi-cloud-off" color="error" />
                    <div>OFFLINE</div>
                </div>
                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="selectedOffer.is_active && selectedOffer.active_until">
                    <div>Aktiv bis:</div>
                    <div>{{ selectedOffer?.active_until }}</div>
                </div>
            </div>

            <!-- Klicks -->
            <div class="bg-primary-lighten-3">
                <label class="text-subtitle-2 mt-2 d-block">Informationen:</label>
                <div class="text-body-1 d-flex flex-row align-center ga-2">
                    <div>Anzahl Klicks:</div>
                    <div>{{ selectedOffer?.click_count }}</div>
                </div>
            </div>
        </its-grid-box>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useUserStore } from '@/stores/admin/UserStore20'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

import { useOfferStore } from '@/stores/admin/tutoring/OfferStore'

// SPECIFIC

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.userStore = useUserStore()
        this.offerStore = useOfferStore()
        this.select_only_me_concerning = true
        this.selected_offers = []
        this.select_accepted = 'all'
        await this.offerStore.getStats()
        await this.offerStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            userStore: null,
            offerStore: null,
            is_valid: false,
            delete_level: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useOfferStore, ['offers', 'selected_offers', 'select_accepted', 'select_online', 'meta', 'select_only_me_concerning', 'stats']),

        selectedOffer() {
            const id = this.selected_offers[0]
            const offer = this.offers.find((offer) => offer.id === id)
            return offer
        },
        selectedOffersList() {
            const selectedIds = new Set((this.selected_offers || []).map((id) => Number(id)))
            return (this.offers || []).filter((offer) => selectedIds.has(Number(offer.id)))
        },
        selectedOfferUserIds() {
            return [...new Set(this.selectedOffersList.map((offer) => Number(offer?.user?.id || 0)).filter((id) => id > 0))]
        },
        canBulkAcceptOffers() {
            return this.selectedOffersList.length > 0 && this.selectedOffersList.every((offer) => !offer.accepted_at)
        },
        canBulkRejectOffers() {
            return this.selectedOffersList.length > 0 && this.selectedOffersList.every((offer) => !!offer.accepted_at)
        },
        selectionHasAcceptedOffers() {
            return this.selectedOffersList.some((offer) => !!offer.accepted_at)
        },
        canBulkSetOffline() {
            return this.selectedOffersList.length > 0 && this.selectedOffersList.every((offer) => !!offer.accepted_at && !!offer.is_active)
        },
        canBulkSetOnline() {
            return this.selectedOffersList.length > 0 && this.selectedOffersList.every((offer) => !!offer.accepted_at && !offer.is_active)
        },
        canBulkBlockUsers() {
            if (this.selectedOffersList.length === 0 || this.selectedOfferUserIds.length === 0) return false
            return this.selectedOffersList.every((offer) => !!offer?.user?.is_active)
        },
        canBulkUnblockUsers() {
            if (this.selectedOffersList.length === 0 || this.selectedOfferUserIds.length === 0) return false
            return this.selectedOffersList.every((offer) => !offer?.user?.is_active)
        },
        showMixedSelectionHint() {
            if (this.selectedOffersList.length <= 1) return false
            return !this.canBulkAcceptOffers
                && !this.canBulkRejectOffers
                || (!this.canBulkBlockUsers && !this.canBulkUnblockUsers)
                || (this.selectionHasAcceptedOffers && !this.canBulkSetOffline && !this.canBulkSetOnline)
        },

        selectedClasses() {
            return Object.entries(this.selectedOffer.classes || {})
                .filter(([key, value]) => value === true)
                .map(([key]) => parseInt(key))
                .sort((a, b) => a - b)
        },

        unterstufeClasses() {
            return this.selectedClasses.filter((num) => num <= 4).map((num) => `${num}. Klasse`)
        },

        oberstufeClasses() {
            return this.selectedClasses.filter((num) => num > 4).map((num) => `${num}. Klasse`)
        },
    },

    watch: {},

    methods: {
        async refreshOverviewData() {
            await this.offerStore.index(this.meta?.current_page || 1)
            await this.offerStore.getStats()
        },
        async toggleIsActive(user_id) {
            await this.userStore.toggleIsActive(user_id)
            await this.offerStore.index(this.meta.current_page)
        },
        async doDelete(offer) {
            this.selected_offers = []
            this.delete_level = 0
            await this.offerStore.delete(offer)
            await this.offerStore.index(this.meta.current_page)
            await this.offerStore.getStats()
        },
        async doRecordtoggleAccepted(id) {
            this.selected_offers = []
            await this.offerStore.toggleAccepted(id)
            await this.offerStore.index(this.meta.current_page)
            await this.offerStore.getStats()
        },
        async doRecordtoggleActive(id) {
            await this.offerStore.toggleActive(id)
            await this.offerStore.index(this.meta.current_page)
            await this.offerStore.getStats()
        },
        async setSelectedOffersAccepted(accepted) {
            const ids = this.selectedOffersList.map((offer) => offer.id)
            if (!ids.length) return
            this.delete_level = 0
            if (!(await this.offerStore.toggleAccepted(ids, accepted))) return
            this.selected_offers = []
            await this.refreshOverviewData()
        },
        async setSelectedUsersActive(isActive) {
            const userIds = this.selectedOfferUserIds
            if (!userIds.length) return
            this.delete_level = 0
            if (!(await this.userStore.toggleIsActive(userIds, isActive))) return
            this.selected_offers = []
            await this.refreshOverviewData()
        },
        async setSelectedOffersOnline(isActive) {
            const ids = this.selectedOffersList.map((offer) => offer.id)
            if (!ids.length) return
            this.delete_level = 0
            if (!(await this.offerStore.toggleActive(ids, isActive))) return
            this.selected_offers = []
            await this.refreshOverviewData()
        },
        async deleteSelectedOffers() {
            const ids = this.selectedOffersList.map((offer) => offer.id)
            if (!ids.length) return
            this.delete_level = 0
            if (!(await this.offerStore.delete(ids))) return
            this.selected_offers = []
            await this.refreshOverviewData()
        },

        async toggleAccepted(status) {
            this.select_accepted = status
            this.selected_offers = []
            await this.offerStore.index()
        },
        async toggleOnline(status) {
            this.select_online = status
            this.selected_offers = []
            await this.offerStore.index()
        },

        selectAll() {
            this.selected_offers = this.offers.map((item) => item.id)
        },
        unselectAll() {
            this.selected_offers = []
        },
        onOfferClick(id) {
            this.delete_level = 0
            this.selected_offers = this.selected_offers.includes(id)
                ? this.selected_offers.filter((selectedId) => selectedId !== id)
                : [...this.selected_offers, id]
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
<style scoped src="../../../../../css/admin-tutoring-overview-cards.css"></style>
