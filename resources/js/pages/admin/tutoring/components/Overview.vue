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
                                <div class="tov-offer-requests" :class="{ 'has-requests': requestCount(item) > 0 }">
                                    <v-icon size="14" :color="requestCount(item) > 0 ? 'info' : 'grey'" icon="mdi-account-question-outline" />
                                    <span v-if="requestCount(item) === 0">Keine Anfragen</span>
                                    <span v-else>{{ requestCountLabel(item) }}: {{ requestStudentSummary(item) }}</span>
                                </div>
                                <div class="tov-offer-request-status" v-if="requestCount(item) > 0">
                                    <span class="tov-req-chip tov-req-chip--open" v-if="requestStatusCounts(item).open > 0">
                                        <v-icon size="12" icon="mdi-email-alert" />
                                        {{ requestStatusCounts(item).open }} offen
                                    </span>
                                    <span class="tov-req-chip tov-req-chip--seen" v-if="requestStatusCounts(item).seen > 0">
                                        <v-icon size="12" icon="mdi-eye" />
                                        {{ requestStatusCounts(item).seen }} gesehen
                                    </span>
                                    <span class="tov-req-chip tov-req-chip--answered" v-if="requestStatusCounts(item).answered > 0">
                                        <v-icon size="12" icon="mdi-email-check" />
                                        {{ requestStatusCounts(item).answered }} beantwortet
                                    </span>
                                    <span class="tov-req-chip tov-req-chip--archived" v-if="requestStatusCounts(item).archived > 0">
                                        <v-icon size="12" icon="mdi-archive" />
                                        {{ requestStatusCounts(item).archived }} archiviert
                                    </span>
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
                            <!-- Detail / Konversation Buttons (single selection) -->
                            <div class="d-flex flex-column ga-3 mt-3" v-if="selected_offers.length === 1">
                                <v-btn block variant="outlined" rounded="lg" color="primary" class="text-caption" prepend-icon="mdi-text-box-outline" @click="detail_view = detail_view === 'details' ? null : 'details'">Details</v-btn>
                                <v-btn block variant="outlined" rounded="lg" color="primary" class="text-caption" prepend-icon="mdi-message-text-outline" @click="detail_view = detail_view === 'conversation' ? null : 'conversation'" v-if="selectedOfferRequests.length > 0">
                                    Konversation ({{ selectedOfferRequests.length }})
                                </v-btn>
                            </div>

                            <v-divider class="my-3 opacity-30" v-if="selected_offers.length === 1" />
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
                            <div class="kpi-card ai-glass-panel">
                                <div class="kpi-label">Anfragen</div>
                                <div class="kpi-value">{{ stats.requests_count }}</div>
                            </div>
                            <div class="kpi-card ai-glass-panel">
                                <div class="kpi-label">Anfragende</div>
                                <div class="kpi-value">{{ stats.requesting_students_count }}</div>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>

    <!-- ##### DIALOG: DETAILS / KONVERSATION ##### -->
    <v-dialog v-model="showDetailDialog" max-width="620" persistent>
        <v-card rounded="xl" class="tov-dialog" v-if="selectedOffer">
            <!-- Dialog Header -->
            <div class="tov-dialog-head">
                <div class="tov-dialog-head-text">
                    <div class="tov-dialog-eyebrow">{{ selectedOffer.subject?.long_name }}</div>
                    <div class="tov-dialog-title">{{ selectedOffer.subject?.short_name }}: {{ selectedOffer.title }}</div>
                </div>
                <v-btn icon variant="text" size="small" @click="detail_view = null">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </div>

            <!-- Tab-Switcher -->
            <div class="tov-dialog-tabs" v-if="selectedOfferRequests.length > 0">
                <v-btn
                    rounded="pill"
                    size="small"
                    :color="detail_view === 'details' ? 'primary' : 'secondary'"
                    :variant="detail_view === 'details' ? 'flat' : 'tonal'"
                    class="text-caption tov-dialog-tab"
                    prepend-icon="mdi-text-box-outline"
                    @click="detail_view = 'details'">
                    Details
                </v-btn>
                <v-btn
                    rounded="pill"
                    size="small"
                    :color="detail_view === 'conversation' ? 'info' : 'secondary'"
                    :variant="detail_view === 'conversation' ? 'flat' : 'tonal'"
                    class="text-caption tov-dialog-tab"
                    prepend-icon="mdi-message-text-outline"
                    @click="detail_view = 'conversation'">
                    Konversation ({{ selectedOfferRequests.length }})
                </v-btn>
            </div>

            <!-- Details Content -->
            <v-card-text class="tov-dialog-body" v-if="detail_view === 'details'">
                <div class="tov-detail-section">
                    <div class="tov-detail-label">Beschreibung</div>
                    <div class="tov-detail-text" style="white-space: pre-line">{{ selectedOffer.description }}</div>
                </div>

                <div class="tov-detail-section">
                    <div class="tov-detail-label">Anbieter</div>
                    <div class="d-flex align-center ga-2">
                        <v-icon size="16" icon="mdi-account" />
                        <span class="font-weight-bold">{{ selectedOffer.user.last_name }} {{ selectedOffer.user.first_name }}</span>
                        <span class="tov-conversation-class-badge" v-if="selectedOffer.user.schoolclass">{{ selectedOffer.user.schoolclass }}</span>
                    </div>
                    <div class="d-flex align-center ga-2 mt-1">
                        <v-icon size="16" icon="mdi-email-outline" />
                        <span>{{ selectedOffer.user.email }}</span>
                    </div>
                </div>

                <div class="tov-detail-section">
                    <div class="tov-detail-label">Klassen</div>
                    <div v-if="unterstufeClasses.length > 0" class="mb-1">
                        <strong>Unterstufe:</strong> {{ unterstufeClasses.join(', ') }}
                    </div>
                    <div v-if="oberstufeClasses.length > 0">
                        <strong>Oberstufe:</strong> {{ oberstufeClasses.join(', ') }}
                    </div>
                </div>

                <div class="tov-detail-section">
                    <div class="tov-detail-label">Gruppenangebot</div>
                    <div v-if="!selectedOffer.is_group">Nein (nur Einzelunterricht)</div>
                    <div v-if="selectedOffer.is_group">Ja (maximal {{ selectedOffer.max_group_members }} Teilnehmer)</div>
                </div>

                <div class="tov-detail-section">
                    <div class="tov-detail-label">Kosten pro Stunde</div>
                    <div>{{ parseFloat(selectedOffer.price_per_hour) }} Euro</div>
                </div>

                <div class="tov-detail-section">
                    <div class="tov-detail-label">Freigabe</div>
                    <div v-if="!selectedOffer.must_be_accepted">Automatisch freigegeben.</div>
                    <div v-else>
                        <div class="d-flex align-center ga-2">
                            <v-icon size="16" :icon="selectedOffer.accepted_at ? 'mdi-check-circle' : 'mdi-help-circle'" :color="selectedOffer.accepted_at ? 'success' : 'warning'" />
                            <span>{{ selectedOffer.accepted_at ? 'Freigabe erteilt' : 'Ausstehend' }}</span>
                        </div>
                        <div class="d-flex align-center ga-2 mt-1" v-if="selectedOffer.email_mentor">
                            <v-icon size="16" icon="mdi-email-outline" />
                            <span>Mentor: {{ selectedOffer.email_mentor }}</span>
                        </div>
                    </div>
                </div>

                <div class="tov-detail-section">
                    <div class="tov-detail-label">Status</div>
                    <div class="d-flex align-center ga-2">
                        <v-icon size="16" :icon="selectedOffer.is_active ? 'mdi-cloud-check' : 'mdi-cloud-off'" :color="selectedOffer.is_active ? 'success' : 'error'" />
                        <span>{{ selectedOffer.is_active ? 'Online' : 'Offline' }}</span>
                    </div>
                    <div class="mt-1" v-if="selectedOffer.is_active && selectedOffer.active_until">
                        Aktiv bis: {{ selectedOffer.active_until }}
                    </div>
                </div>

                <div class="tov-detail-section">
                    <div class="tov-detail-label">Klicks</div>
                    <div>{{ selectedOffer.click_count }}</div>
                </div>
            </v-card-text>

            <!-- Conversation Content -->
            <v-card-text class="tov-dialog-body" v-if="detail_view === 'conversation'">
                <div class="tov-conversation-receiver-bar">
                    <v-icon size="16" icon="mdi-account-arrow-left" class="mr-1" />
                    <span class="font-weight-bold">Empfänger:</span>
                    <span>{{ selectedOffer.user.last_name }} {{ selectedOffer.user.first_name }}</span>
                    <span class="tov-conversation-receiver-class" v-if="selectedOffer.user.schoolclass">{{ selectedOffer.user.schoolclass }}</span>
                </div>

                <div class="d-grid ga-3 mt-3">
                    <div v-for="request in selectedOfferRequests" :key="request.id" class="tov-conversation-card">
                        <div class="tov-conversation-header">
                            <div class="tov-conversation-avatar">
                                <v-icon size="22" icon="mdi-account" />
                            </div>
                            <div class="tov-conversation-sender">
                                <div class="tov-conversation-name">
                                    {{ requestStudentName(request) }}
                                    <span class="tov-conversation-class-badge" v-if="request.from_user?.schoolclass">{{ request.from_user.schoolclass }}</span>
                                </div>
                                <div class="tov-conversation-meta">{{ request.from_user?.email }}</div>
                            </div>
                            <div class="tov-conversation-status-icon">
                                <v-icon size="16" color="warning" icon="mdi-email-alert" v-if="!request.mail_at && !request.seen_at" />
                                <v-icon size="16" color="info" icon="mdi-eye" v-if="request.seen_at && !request.mail_at" />
                                <v-icon size="16" color="success" icon="mdi-email-check" v-if="request.mail_at" />
                            </div>
                        </div>

                        <div class="tov-conversation-message" v-if="request.message">
                            {{ request.message }}
                        </div>
                        <div class="tov-conversation-message tov-conversation-message--empty" v-else>
                            Keine Nachricht
                        </div>

                        <div class="tov-conversation-timeline">
                            <div class="tov-conversation-event">
                                <v-icon size="13" color="primary" icon="mdi-send" />
                                <span>Gesendet: {{ formatRequestDate(request.sent_at || request.created_at) }}</span>
                            </div>
                            <div class="tov-conversation-event" v-if="request.sent_count > 1">
                                <v-icon size="13" color="info" icon="mdi-refresh" />
                                <span>{{ request.sent_count }}x gesendet, zuletzt: {{ formatRequestDate(request.last_sent_at) }}</span>
                            </div>
                            <div class="tov-conversation-event" v-if="request.seen_at">
                                <v-icon size="13" color="success" icon="mdi-eye" />
                                <span>Gesehen: {{ formatRequestDate(request.seen_at) }}</span>
                            </div>
                            <div class="tov-conversation-event" v-if="!request.seen_at && !request.mail_at">
                                <v-icon size="13" color="warning" icon="mdi-eye-off" />
                                <span>Noch nicht gelesen</span>
                            </div>
                            <div class="tov-conversation-event" v-if="request.mail_at">
                                <v-icon size="13" color="success" icon="mdi-email-check" />
                                <span>Beantwortet: {{ formatRequestDate(request.mail_at) }}</span>
                            </div>
                            <div class="tov-conversation-event" v-if="!request.mail_at && request.seen_at">
                                <v-icon size="13" color="warning" icon="mdi-email-alert" />
                                <span>Noch nicht beantwortet</span>
                            </div>
                            <div class="tov-conversation-event" v-if="request.archived_at">
                                <v-icon size="13" color="grey" icon="mdi-archive" />
                                <span>Archiviert (Sender)</span>
                            </div>
                            <div class="tov-conversation-event" v-if="request.to_user_archived_at">
                                <v-icon size="13" color="grey" icon="mdi-archive" />
                                <span>Archiviert (Empfänger)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
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
            detail_view: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useOfferStore, ['offers', 'selected_offers', 'select_accepted', 'select_online', 'meta', 'select_only_me_concerning', 'stats']),

        showDetailDialog: {
            get() {
                return this.detail_view !== null && this.selected_offers.length === 1
            },
            set(val) {
                if (!val) this.detail_view = null
            },
        },
        selectedOffer() {
            const id = this.selected_offers[0]
            const offer = this.offers.find((offer) => offer.id === id)
            return offer
        },
        selectedOffersList() {
            const selectedIds = new Set((this.selected_offers || []).map((id) => Number(id)))
            return (this.offers || []).filter((offer) => selectedIds.has(Number(offer.id)))
        },
        selectedOfferRequests() {
            return Array.isArray(this.selectedOffer?.requests) ? this.selectedOffer.requests : []
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
            this.detail_view = null
            this.selected_offers = this.selected_offers.includes(id)
                ? this.selected_offers.filter((selectedId) => selectedId !== id)
                : [...this.selected_offers, id]
        },
        requestCount(offer) {
            return Number(offer?.requests_count ?? offer?.requests?.length ?? 0)
        },
        requestStatusCounts(offer) {
            const requests = Array.isArray(offer?.requests) ? offer.requests : []
            let open = 0
            let seen = 0
            let answered = 0
            let archived = 0
            for (const r of requests) {
                if (r.archived_at || r.to_user_archived_at) {
                    archived++
                } else if (r.mail_at) {
                    answered++
                } else if (r.seen_at) {
                    seen++
                } else {
                    open++
                }
            }
            return { open, seen, answered, archived }
        },
        requestCountLabel(offer) {
            const count = this.requestCount(offer)
            return count === 1 ? '1 Anfrage' : `${count} Anfragen`
        },
        requestStudentName(request) {
            const user = request?.from_user || {}
            const name = [user.last_name, user.first_name].filter(Boolean).join(' ')
            return name || user.email || 'Unbekannte:r Schüler:in'
        },
        requestStudentMeta(request) {
            const user = request?.from_user || {}
            return [user.schoolclass, user.email].filter(Boolean).join(', ')
        },
        requestStudentSummary(offer) {
            const requests = Array.isArray(offer?.requests) ? offer.requests : []
            const names = requests.map((request) => this.requestStudentName(request)).filter(Boolean)

            if (names.length === 0) {
                return 'Details im Angebot'
            }

            const visibleNames = names.slice(0, 2).join(', ')
            const remainingCount = this.requestCount(offer) - 2

            return remainingCount > 0 ? `${visibleNames} +${remainingCount}` : visibleNames
        },
        formatRequestDate(value) {
            if (!value) return null

            return String(value).replace('T', ' ').slice(0, 16)
        },
        requestStatusLine(request) {
            const parts = []
            const sentAt = this.formatRequestDate(request?.sent_at || request?.created_at)
            const seenAt = this.formatRequestDate(request?.seen_at)
            const mailAt = this.formatRequestDate(request?.mail_at)

            if (sentAt) parts.push(`gesendet: ${sentAt}`)
            if (seenAt) parts.push(`gesehen: ${seenAt}`)
            if (mailAt) parts.push(`E-Mail: ${mailAt}`)

            return parts.join(' | ')
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
<style scoped src="../../../../../css/admin-tutoring-overview-cards.css"></style>
<style scoped>
.tov-offer-requests {
    display: flex;
    align-items: center;
    gap: 4px;
    color: rgba(148, 163, 184, 0.95);
    font-size: 0.78rem;
    line-height: 1.35;
}

.tov-offer-requests.has-requests {
    color: #bfdbfe;
    font-weight: 650;
}

.tov-offer-request-status {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 3px;
}

.tov-req-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 6px;
    line-height: 1.4;
}

.tov-req-chip--open {
    background: rgba(251, 191, 36, 0.18);
    color: #fbbf24;
}

.tov-req-chip--seen {
    background: rgba(96, 165, 250, 0.18);
    color: #60a5fa;
}

.tov-req-chip--answered {
    background: rgba(52, 211, 153, 0.18);
    color: #34d399;
}

.tov-req-chip--archived {
    background: rgba(148, 163, 184, 0.15);
    color: #94a3b8;
}

.tov-request-detail {
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.72);
    padding: 8px 10px;
}

/* Dialog */
.tov-dialog {
    border: 1px solid rgba(16, 38, 58, 0.08);
    box-shadow: 0 18px 48px rgba(16, 38, 58, 0.14);
    overflow: hidden;
}

.tov-dialog-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px 12px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.08);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.92));
}

.tov-dialog-eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(16, 38, 58, 0.5);
}

.tov-dialog-title {
    font-size: 1.1rem;
    font-weight: 750;
    color: rgba(16, 38, 58, 0.92);
    margin-top: 2px;
}

.tov-dialog-tabs {
    display: flex;
    gap: 6px;
    padding: 10px 20px;
    border-bottom: 1px solid rgba(16, 38, 58, 0.06);
    background: rgba(248, 250, 252, 0.6);
}

.tov-dialog-tab {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 700;
}

.tov-dialog-body {
    padding: 16px 20px !important;
    max-height: 65vh;
    overflow-y: auto;
}

.tov-detail-section {
    padding: 10px 0;
    border-bottom: 1px solid rgba(16, 38, 58, 0.06);
    font-size: 0.88rem;
    color: rgba(16, 38, 58, 0.82);
}

.tov-detail-section:last-child {
    border-bottom: none;
}

.tov-detail-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(16, 38, 58, 0.5);
    margin-bottom: 4px;
}

.tov-detail-text {
    line-height: 1.55;
}

/* Conversation cards */
.tov-conversation-card {
    border: 1px solid rgba(15, 23, 42, 0.10);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.78);
    padding: 12px;
    transition: box-shadow 0.2s ease;
}

.tov-conversation-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.tov-conversation-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.tov-conversation-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(33, 150, 243, 0.1);
    color: #2196f3;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tov-conversation-sender {
    flex: 1;
    min-width: 0;
}

.tov-conversation-name {
    font-weight: 700;
    font-size: 0.88rem;
    color: rgba(16, 38, 58, 0.92);
}

.tov-conversation-meta {
    font-size: 0.75rem;
    color: rgba(16, 38, 58, 0.55);
}

.tov-conversation-status-icon {
    flex-shrink: 0;
}

.tov-conversation-message {
    font-size: 0.85rem;
    color: rgba(16, 38, 58, 0.82);
    background: rgba(33, 150, 243, 0.06);
    border-left: 3px solid rgba(33, 150, 243, 0.3);
    padding: 8px 10px;
    border-radius: 0 8px 8px 0;
    white-space: pre-line;
    line-height: 1.5;
    margin-bottom: 8px;
}

.tov-conversation-message--empty {
    font-style: italic;
    color: rgba(16, 38, 58, 0.4);
    background: rgba(0, 0, 0, 0.02);
    border-left-color: rgba(0, 0, 0, 0.08);
}

.tov-conversation-timeline {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.tov-conversation-event {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    color: rgba(16, 38, 58, 0.6);
}

.tov-conversation-receiver-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.82rem;
    color: rgba(16, 38, 58, 0.72);
    padding: 8px 10px;
    margin-bottom: 4px;
    border-radius: 8px;
    background: rgba(33, 150, 243, 0.06);
    border: 1px solid rgba(33, 150, 243, 0.12);
}

.tov-conversation-receiver-class {
    font-weight: 700;
    font-size: 0.75rem;
    background: rgba(33, 150, 243, 0.12);
    color: #1976d2;
    padding: 1px 6px;
    border-radius: 4px;
}

.tov-conversation-class-badge {
    font-weight: 700;
    font-size: 0.72rem;
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
    padding: 1px 5px;
    border-radius: 4px;
    margin-left: 4px;
}
</style>
