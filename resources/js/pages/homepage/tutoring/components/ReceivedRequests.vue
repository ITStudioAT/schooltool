<template>
    <div class="received-requests-section" v-if="is_loaded">
        <!-- Section Header -->
        <div class="section-header">
            <div class="header-icon">
                <v-icon size="28" color="white">mdi-inbox-arrow-down</v-icon>
            </div>
            <div class="header-text">
                <h2 class="section-title">
                    <span v-if="!show_to_user_archived">Erhaltene Anfragen</span>
                    <span v-if="show_to_user_archived">Erhaltene archivierte Anfragen</span>
                </h2>
                <p class="section-subtitle">{{ meta?.total || 0 }} Anfragen</p>
            </div>
        </div>

        <!-- Requests Grid -->
        <div class="requests-grid" v-if="requests && requests.length > 0">
            <div class="request-card" v-for="request in requests" :key="request.id">
                <div class="card-glow"></div>
                <div class="card-content">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="card-icon">
                            <v-icon size="32">mdi-account</v-icon>
                        </div>
                        <div class="card-header-text">
                            <h3 class="card-title">{{ request.from_user.first_name }} {{ request.from_user.last_name }}</h3>
                            <p class="card-school">{{ request.school.short_name }} ({{ request.school.long_name }})</p>
                        </div>
                    </div>

                    <!-- Subject Info -->
                    <div class="card-subject">
                        <v-icon size="18" class="mr-1">mdi-book-open-variant</v-icon>
                        {{ request.offer.subject.short_name }}: {{ request.offer.subject.long_name }}
                    </div>

                    <!-- Offer Details -->
                    <div class="card-offer" v-if="request.offer.title">
                        <strong>{{ request.offer.title }}</strong>
                        <p>{{ request.offer.description }}</p>
                    </div>

                    <!-- Message -->
                    <div class="card-message" v-if="request.message">
                        <v-icon size="18" class="mr-1">mdi-message-text</v-icon>
                        {{ request.message }}
                    </div>

                    <!-- Email Warning -->
                    <v-alert type="warning" class="mt-3" variant="tonal" rounded="lg" v-if="!request.mail_at">
                        <div class="text-body-2">Bitte schicke Deinem Kontakt eine Antwort per E-Mail:</div>
                        <div class="d-flex flex-row align-center flex-wrap ga-2 mt-2">
                            <v-btn
                                color="warning"
                                variant="flat"
                                size="small"
                                rounded="lg"
                                :href="'mailto:' + request.from_user.email + '?subject=Antwort auf Deine Anfrage zu ' + request.offer.subject.long_name"
                                @click="mailClicked(request.id)">
                                <v-icon start>mdi-email</v-icon>
                                {{ request.from_user.email }}
                            </v-btn>
                            <v-btn color="success" variant="flat" size="small" rounded="lg" @click="mailClicked(request.id)">
                                <v-icon start>mdi-check</v-icon>
                                Erledigt!
                            </v-btn>
                        </div>
                    </v-alert>

                    <!-- Status Chips -->
                    <div class="card-chips">
                        <v-chip size="small" color="primary" variant="tonal">
                            <v-icon start size="14">mdi-send</v-icon>
                            {{ request.sent_at }}
                        </v-chip>
                        <v-chip size="small" color="info" variant="tonal" v-if="request.last_sent_at">
                            <v-icon start size="14">mdi-refresh</v-icon>
                            {{ request.sent_count }}x - {{ request.last_sent_at }}
                        </v-chip>
                        <v-chip size="small" color="success" variant="tonal" v-if="request.seen_at">
                            <v-icon start size="14">mdi-eye</v-icon>
                            {{ request.seen_at }}
                        </v-chip>
                        <v-chip size="small" color="info" variant="tonal" v-if="request.mail_at">
                            <v-icon start size="14">mdi-email-check</v-icon>
                            {{ request.mail_at }}
                        </v-chip>
                        <v-chip size="small" color="warning" variant="tonal" v-if="!request.mail_at">
                            <v-icon start size="14">mdi-email-alert</v-icon>
                            Keine Antwort
                        </v-chip>
                    </div>

                    <!-- Card Actions -->
                    <div class="card-action">
                        <v-btn color="primary" variant="tonal" size="small" rounded="lg" @click="toUserArchive(request)" v-if="request.mail_at && !request.to_user_archived_at">
                            <v-icon start>mdi-archive</v-icon>
                            Archivieren
                        </v-btn>
                        <v-btn color="success" variant="tonal" size="small" rounded="lg" @click="toUserActive(request)" v-if="request.to_user_archived_at">
                            <v-icon start>mdi-archive-arrow-up</v-icon>
                            Aktivieren
                        </v-btn>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-card" v-if="requests && requests.length > 0 && meta.last_page > 1">
            <v-btn variant="tonal" color="primary" size="large" rounded="lg" :disabled="meta.current_page == 1" @click="requestStore.receivedRequests(meta.current_page - 1)">
                <v-icon start>mdi-arrow-left</v-icon>
                Vorherige
            </v-btn>
            <div class="page-indicator">
                <span class="page-current">{{ meta.current_page }}</span>
                <span class="page-separator">von</span>
                <span class="page-total">{{ meta.last_page }}</span>
            </div>
            <v-btn
                variant="tonal"
                color="primary"
                size="large"
                rounded="lg"
                :disabled="meta.current_page == meta.last_page"
                @click="requestStore.receivedRequests(meta.current_page + 1)">
                Nächste
                <v-icon end>mdi-arrow-right</v-icon>
            </v-btn>
        </div>

        <!-- No Requests -->
        <div class="no-requests" v-if="!requests || requests.length === 0">
            <div class="no-requests-card">
                <div class="no-requests-icon">
                    <v-icon size="72" color="grey-lighten-1">mdi-inbox-outline</v-icon>
                </div>
                <h3 class="no-requests-title">Keine Anfragen vorhanden</h3>
                <p class="no-requests-text">Du hast noch keine Anfragen erhalten.</p>
            </div>
        </div>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useRequestStore } from '@/stores/tutoring/RequestStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import MyOffer from './MyOffer.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox, MyOffer },

    async beforeMount() {
        this.requestStore = useRequestStore()
        await this.requestStore.receivedRequests()
        this.is_loaded = true
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            requestStore: null,
            is_loaded: false,
        }
    },

    computed: {
        ...mapWritableState(useRequestStore, ['requests', 'meta', 'show_to_user_archived']),
    },

    watch: {
        show_to_user_archived: {
            async handler() {
                await this.requestStore.receivedRequests()
            },
            // immediate: true  // falls du beim Mount auch laden willst
        },
    },

    methods: {
        async toUserArchive(request) {
            await this.requestStore.toUserArchive(request.id)
            await this.requestStore.receivedRequests(this.meta.current_page)
        },
        async toUserActive(request) {
            await this.requestStore.toUserActive(request.id)
            await this.requestStore.receivedRequests(this.meta.current_page)
        },
        async mailClicked(request_id) {
            await this.requestStore.requestMailClicked(request_id)
        },
        formatDate(value) {
            if (!value) return null
            const date = new Date(value)
            if (Number.isNaN(date.getTime())) return value
            return new Intl.DateTimeFormat('de-DE', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date)
        },
    },
}
</script>

<style scoped>
/* Section */
.received-requests-section {
    animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Section Header */
.section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px 24px;
    background: linear-gradient(135deg, #3aaa35 0%, #2d8a2a 100%);
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(58, 170, 53, 0.25);
}

.header-icon {
    width: 56px;
    height: 56px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.header-text {
    color: white;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.section-subtitle {
    font-size: 0.95rem;
    opacity: 0.9;
    margin: 4px 0 0 0;
}

/* Requests Grid */
.requests-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 24px;
}

/* Request Card */
.request-card {
    position: relative;
    background: white;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
}

.request-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.card-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3aaa35, #4bc044);
    transition: height 0.3s ease;
}

.request-card:hover .card-glow {
    height: 5px;
}

.card-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    flex: 1;
}

/* Card Header */
.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.card-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(58, 170, 53, 0.1);
    color: #3aaa35;
    flex-shrink: 0;
    transition: transform 0.3s ease;
}

.request-card:hover .card-icon {
    transform: scale(1.1);
}

.card-header-text {
    flex: 1;
    min-width: 0;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #263238;
    margin: 0;
    line-height: 1.3;
}

.card-school {
    font-size: 0.85rem;
    color: #607d8b;
    margin: 4px 0 0 0;
}

/* Card Subject */
.card-subject {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    font-weight: 600;
    color: #3aaa35;
    margin-bottom: 12px;
    padding: 8px 12px;
    background: rgba(58, 170, 53, 0.08);
    border-radius: 8px;
}

/* Card Offer */
.card-offer {
    font-size: 0.9rem;
    color: #546e7a;
    margin-bottom: 12px;
    padding: 12px;
    background: #f5f7fa;
    border-radius: 8px;
}

.card-offer strong {
    color: #37474f;
    display: block;
    margin-bottom: 4px;
}

.card-offer p {
    margin: 0;
    line-height: 1.5;
}

/* Card Message */
.card-message {
    display: flex;
    align-items: flex-start;
    font-size: 0.9rem;
    color: #37474f;
    margin-bottom: 12px;
    padding: 12px;
    background: rgba(33, 150, 243, 0.08);
    border-radius: 8px;
    border-left: 3px solid #2196f3;
    white-space: pre-line;
}

/* Card Chips */
.card-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}

/* Card Action */
.card-action {
    display: flex;
    gap: 8px;
    margin-top: auto;
}

/* Pagination */
.pagination-card {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    flex-wrap: wrap;
}

.page-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
}

.page-current {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3aaa35;
}

.page-separator {
    font-size: 0.9rem;
    color: #90a4ae;
}

.page-total {
    font-size: 1.1rem;
    font-weight: 600;
    color: #546e7a;
}

/* No Requests */
.no-requests {
    display: flex;
    justify-content: center;
    padding: 20px 0;
}

.no-requests-card {
    text-align: center;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 48px 40px;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    max-width: 420px;
}

.no-requests-icon {
    margin-bottom: 20px;
}

.no-requests-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 12px 0;
}

.no-requests-text {
    font-size: 1rem;
    color: #607d8b;
    line-height: 1.6;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .section-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }

    .section-title {
        font-size: 1.25rem;
    }

    .request-card {
        padding: 20px;
    }

    .pagination-card {
        flex-direction: column;
        gap: 16px;
        padding: 16px;
    }

    .no-requests-card {
        padding: 32px 24px;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .received-requests-section,
    .request-card {
        animation: none;
    }
    .request-card:hover {
        transform: none;
    }
}
</style>
