<template>
    <v-col cols="12" xl="11" v-if="requests !== null">
        <section class="crud-shell admin-card ai-glass-panel" :class="{ 'is-disabled': action != '' }">
            <div class="admin-card-head crud-head mb-4">
                <div>
                    <div class="admin-card-eyebrow">Kommunikation</div>
                    <h2 class="admin-card-title crud-title">Anfragen der Schüler:innen</h2>
                </div>
                <div class="admin-kpi-grid crud-kpis" v-if="meta">
                    <div class="kpi-card ai-glass-panel">
                        <div class="kpi-label">Gesamt</div>
                        <div class="kpi-value">{{ meta.total }}</div>
                    </div>
                </div>
            </div>

            <div class="crud-content-grid">
                <section class="admin-card ai-glass-panel crud-main-card pa-3">
                    <div class="d-grid ga-3 mb-3">
                        <div class="empty-state crud-search-panel">
                            <SearchField :store="requestStore" selected_field="selected_requests" />
                        </div>
                        <div class="tov-filter-groups">
                            <div class="tov-filter-group">
                                <div class="tov-filter-label-row">
                                    <div class="tov-filter-label">Status</div>
                                    <v-icon size="14" icon="mdi-message-text-outline" class="tov-filter-label-icon" />
                                </div>
                                <div class="d-flex flex-row flex-wrap align-center ga-1 tov-filter-options">
                                    <v-btn :color="select_status == 'all' ? 'primary' : 'secondary'" variant="tonal" rounded="pill" size="small" class="text-caption tov-filter-btn" @click="toggleStatus('all')" :disabled="action != ''">Alle</v-btn>
                                    <v-btn :color="select_status == 'open' ? 'warning' : 'secondary'" variant="tonal" rounded="pill" size="small" class="text-caption tov-filter-btn" @click="toggleStatus('open')" :disabled="action != ''">Offen</v-btn>
                                    <v-btn :color="select_status == 'answered' ? 'success' : 'secondary'" variant="tonal" rounded="pill" size="small" class="text-caption tov-filter-btn" @click="toggleStatus('answered')" :disabled="action != ''">Beantwortet</v-btn>
                                    <v-btn :color="select_status == 'archived' ? 'info' : 'secondary'" variant="tonal" rounded="pill" size="small" class="text-caption tov-filter-btn" @click="toggleStatus('archived')" :disabled="action != ''">Archiviert</v-btn>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="empty-state" v-if="requests.length === 0">Keine Anfragen gefunden.</div>
                    <div v-else>
                        <div
                            v-for="item in requests"
                            :key="item.id"
                            class="crud-list-item tov-offer-item tov-request-row"
                            :class="{ 'is-selected': selected_requests.includes(item.id) }"
                            @click="onRequestClick(item.id)">
                            <div class="tov-offer-status">
                                <v-icon size="16" color="warning" icon="mdi-email-alert" v-if="!item.mail_at && !item.seen_at" />
                                <v-icon size="16" color="info" icon="mdi-eye" v-if="item.seen_at && !item.mail_at" />
                                <v-icon size="16" color="success" icon="mdi-email-check" v-if="item.mail_at" />
                                <v-icon size="16" color="grey" icon="mdi-archive" v-if="item.archived_at || item.to_user_archived_at" />
                            </div>
                            <div class="person-body tov-offer-copy">
                                <div class="person-name">
                                    {{ userName(item.from_user) }}
                                    <span class="text-medium-emphasis text-caption ml-1" v-if="item.from_user?.schoolclass">{{ item.from_user.schoolclass }}</span>
                                    <v-icon size="14" icon="mdi-arrow-right" class="mx-1" />
                                    {{ userName(item.to_user) }}
                                </div>
                                <div class="person-email" v-if="item.offer">
                                    {{ item.offer.subject?.short_name }}: {{ item.offer.title }}
                                </div>
                                <div class="tov-request-message" v-if="item.message">
                                    {{ truncateMessage(item.message) }}
                                </div>
                                <div class="tov-request-meta">
                                    <span v-if="item.sent_at">{{ formatDate(item.sent_at) }}</span>
                                    <span v-if="item.sent_count > 1" class="ml-1">({{ item.sent_count }}x)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="crud-pagination mt-3" v-if="meta">
                        <Pagination :meta="meta" :store="requestStore" selected_field="selected_requests" />
                    </div>
                </section>

                <!-- Detail-Sidebar -->
                <aside class="crud-side-stack" v-if="selectedRequest">
                    <section class="admin-card ai-glass-panel">
                        <div class="admin-card-head mb-2">
                            <div class="tov-card-heading">
                                <div class="admin-card-eyebrow">Detail</div>
                                <h3 class="admin-card-title">Anfrage</h3>
                            </div>
                        </div>

                        <!-- Von -->
                        <div class="tov-detail-block">
                            <div class="tov-detail-label">Von (Anfragend)</div>
                            <div class="tov-detail-user">
                                <v-icon size="18" icon="mdi-account" class="mr-1" />
                                <div>
                                    <div class="tov-detail-name">{{ userName(selectedRequest.from_user) }}</div>
                                    <div class="tov-detail-sub" v-if="selectedRequest.from_user?.schoolclass">{{ selectedRequest.from_user.schoolclass }}</div>
                                    <div class="tov-detail-sub">{{ selectedRequest.from_user?.email }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- An -->
                        <div class="tov-detail-block">
                            <div class="tov-detail-label">An (Anbieter)</div>
                            <div class="tov-detail-user">
                                <v-icon size="18" icon="mdi-account-outline" class="mr-1" />
                                <div>
                                    <div class="tov-detail-name">{{ userName(selectedRequest.to_user) }}</div>
                                    <div class="tov-detail-sub" v-if="selectedRequest.to_user?.schoolclass">{{ selectedRequest.to_user.schoolclass }}</div>
                                    <div class="tov-detail-sub">{{ selectedRequest.to_user?.email }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Angebot -->
                        <div class="tov-detail-block" v-if="selectedRequest.offer">
                            <div class="tov-detail-label">Angebot</div>
                            <div class="tov-detail-value">
                                <strong>{{ selectedRequest.offer.subject?.short_name }}: {{ selectedRequest.offer.title }}</strong>
                            </div>
                            <div class="tov-detail-sub" v-if="selectedRequest.offer.description" style="white-space: pre-line">{{ selectedRequest.offer.description }}</div>
                        </div>

                        <!-- Nachricht -->
                        <div class="tov-detail-block" v-if="selectedRequest.message">
                            <div class="tov-detail-label">Nachricht</div>
                            <div class="tov-detail-message" style="white-space: pre-line">{{ selectedRequest.message }}</div>
                        </div>

                        <!-- Status -->
                        <div class="tov-detail-block">
                            <div class="tov-detail-label">Status</div>
                            <div class="d-flex flex-column ga-1">
                                <div class="tov-detail-status">
                                    <v-icon size="14" color="primary" icon="mdi-send" />
                                    <span>Gesendet: {{ formatDate(selectedRequest.sent_at) }}</span>
                                </div>
                                <div class="tov-detail-status" v-if="selectedRequest.sent_count > 1">
                                    <v-icon size="14" color="info" icon="mdi-refresh" />
                                    <span>{{ selectedRequest.sent_count }}x gesendet, zuletzt: {{ formatDate(selectedRequest.last_sent_at) }}</span>
                                </div>
                                <div class="tov-detail-status" v-if="selectedRequest.seen_at">
                                    <v-icon size="14" color="success" icon="mdi-eye" />
                                    <span>Gesehen: {{ formatDate(selectedRequest.seen_at) }}</span>
                                </div>
                                <div class="tov-detail-status" v-if="selectedRequest.seen_count > 1">
                                    <v-icon size="14" color="info" icon="mdi-eye-refresh" />
                                    <span>{{ selectedRequest.seen_count }}x gesehen, zuletzt: {{ formatDate(selectedRequest.last_seen_at) }}</span>
                                </div>
                                <div class="tov-detail-status" v-if="selectedRequest.mail_at">
                                    <v-icon size="14" color="success" icon="mdi-email-check" />
                                    <span>Beantwortet: {{ formatDate(selectedRequest.mail_at) }}</span>
                                </div>
                                <div class="tov-detail-status" v-if="!selectedRequest.mail_at">
                                    <v-icon size="14" color="warning" icon="mdi-email-alert" />
                                    <span>Noch nicht beantwortet</span>
                                </div>
                                <div class="tov-detail-status" v-if="selectedRequest.archived_at">
                                    <v-icon size="14" color="grey" icon="mdi-archive" />
                                    <span>Archiviert (Sender): {{ formatDate(selectedRequest.archived_at) }}</span>
                                </div>
                                <div class="tov-detail-status" v-if="selectedRequest.to_user_archived_at">
                                    <v-icon size="14" color="grey" icon="mdi-archive" />
                                    <span>Archiviert (Empfänger): {{ formatDate(selectedRequest.to_user_archived_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRequestStore } from '@/stores/admin/tutoring/RequestStore'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'

export default {
    components: { SearchField, Pagination },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.requestStore = useRequestStore()
        this.selected_requests = []
        await this.requestStore.index()
    },

    data() {
        return {
            adminStore: null,
            requestStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useRequestStore, ['requests', 'selected_requests', 'meta', 'select_status']),

        selectedRequest() {
            if (this.selected_requests.length !== 1) return null
            return this.requests?.find((r) => r.id === this.selected_requests[0]) || null
        },
    },

    methods: {
        onRequestClick(id) {
            this.selected_requests = this.selected_requests.includes(id)
                ? this.selected_requests.filter((sid) => sid !== id)
                : [id]
        },

        async toggleStatus(status) {
            this.select_status = status
            this.selected_requests = []
            await this.requestStore.index()
        },

        userName(user) {
            if (!user) return 'Unbekannt'
            return [user.last_name, user.first_name].filter(Boolean).join(' ') || user.email || 'Unbekannt'
        },

        truncateMessage(message) {
            if (!message) return ''
            return message.length > 80 ? message.substring(0, 80) + '...' : message
        },

        formatDate(value) {
            if (!value) return null
            return String(value).replace('T', ' ').slice(0, 16)
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
<style scoped src="../../../../../css/admin-tutoring-overview-cards.css"></style>
<style scoped>
.tov-request-row {
    cursor: pointer;
}

.tov-request-message {
    font-size: 0.78rem;
    color: rgba(148, 163, 184, 0.95);
    margin-top: 2px;
    font-style: italic;
    line-height: 1.35;
}

.tov-request-meta {
    font-size: 0.72rem;
    color: rgba(148, 163, 184, 0.65);
    margin-top: 2px;
}

.tov-detail-block {
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.72);
    padding: 8px 10px;
    margin-bottom: 8px;
}

.tov-detail-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(16, 38, 58, 0.6);
    margin-bottom: 4px;
}

.tov-detail-user {
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.tov-detail-name {
    font-weight: 650;
    font-size: 0.88rem;
    color: rgba(16, 38, 58, 0.92);
}

.tov-detail-sub {
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.6);
}

.tov-detail-value {
    font-size: 0.88rem;
    color: rgba(16, 38, 58, 0.85);
}

.tov-detail-message {
    font-size: 0.85rem;
    color: rgba(16, 38, 58, 0.82);
    background: rgba(33, 150, 243, 0.06);
    border-left: 3px solid rgba(33, 150, 243, 0.3);
    padding: 8px 10px;
    border-radius: 0 6px 6px 0;
    line-height: 1.5;
}

.tov-detail-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    color: rgba(16, 38, 58, 0.72);
}
</style>
