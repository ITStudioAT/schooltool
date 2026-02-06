<template>
    <!-- Offer Card -->
    <div class="offer-card" v-if="action == '' && !is_edit">
        <div class="card-glow" :class="{ 'glow-online': offer.is_active, 'glow-offline': !offer.is_active }"></div>

        <!-- Card Header -->
        <div class="card-header">
            <div class="subject-badge">
                <span class="subject-short">{{ offer.subject.short_name }}</span>
            </div>
            <div class="subject-info">
                <h3 class="subject-name">{{ offer.subject.long_name }}</h3>
                <span class="offer-title">{{ offer.title }}</span>
            </div>
        </div>

        <!-- Status Section -->
        <div class="status-section">
            <!-- Pending Approval -->
            <div class="status-pending" v-if="offer.must_be_accepted && !offer.accepted_at">
                <div class="status-icon">
                    <v-icon size="20" color="warning">mdi-clock-outline</v-icon>
                </div>
                <div class="status-text">
                    <span class="status-label">Bestätigung ausstehend</span>
                    <span class="status-detail">{{ offer.email_mentor }}</span>
                </div>
            </div>

            <!-- Approved -->
            <div class="status-approved" v-if="!offer.must_be_accepted || offer.accepted_at">
                <div class="status-badge success">
                    <v-icon size="16">mdi-check-circle</v-icon>
                    <span>Freigegeben</span>
                </div>

                <!-- Online/Offline Toggle -->
                <div class="online-toggle">
                    <div class="toggle-status" :class="{ 'is-online': offer.is_active, 'is-offline': !offer.is_active }">
                        <v-icon size="18">{{ offer.is_active ? 'mdi-web' : 'mdi-web-off' }}</v-icon>
                        <span>{{ offer.is_active ? 'ONLINE' : 'OFFLINE' }}</span>
                    </div>
                    <v-btn :color="offer.is_active ? 'error' : 'success'" variant="tonal" size="small" rounded="lg" @click="toggleActive(offer)">
                        {{ offer.is_active ? 'Ausschalten' : 'Einschalten' }}
                    </v-btn>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-item">
                <div class="stat-icon">
                    <v-icon size="20" color="primary">mdi-cursor-default-click</v-icon>
                </div>
                <div class="stat-info">
                    <span class="stat-value">{{ offer.click_count }}</span>
                    <span class="stat-label">Klicks</span>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <v-icon size="20" color="success">mdi-currency-eur</v-icon>
                </div>
                <div class="stat-info">
                    <span class="stat-value">{{ parseFloat(offer.price_per_hour) }}</span>
                    <span class="stat-label">Euro/Std.</span>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <v-icon size="20" color="secondary">{{ offer.is_group ? 'mdi-account-group' : 'mdi-account' }}</v-icon>
                </div>
                <div class="stat-info">
                    <span class="stat-value">{{ offer.is_group ? 'Gruppe' : 'Einzel' }}</span>
                    <span class="stat-label" v-if="offer.is_group">max. {{ offer.max_group_members }}</span>
                    <span class="stat-label" v-else>Unterricht</span>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="description-section" v-if="offer.description">
            <p class="description-text">{{ offer.description }}</p>
        </div>

        <!-- Classes Section -->
        <div class="classes-section" v-if="unterstufeClasses.length > 0 || oberstufeClasses.length > 0">
            <div class="classes-group" v-if="unterstufeClasses.length > 0">
                <span class="classes-label">Unterstufe:</span>
                <div class="classes-chips">
                    <span class="class-chip" v-for="cls in unterstufeClasses" :key="cls">{{ cls }}</span>
                </div>
            </div>
            <div class="classes-group" v-if="oberstufeClasses.length > 0">
                <span class="classes-label">Oberstufe:</span>
                <div class="classes-chips">
                    <span class="class-chip chip-upper" v-for="cls in oberstufeClasses" :key="cls">{{ cls }}</span>
                </div>
            </div>
        </div>

        <!-- Valid Until -->
        <div class="validity-section">
            <v-icon size="16" class="mr-1">mdi-calendar-clock</v-icon>
            <span>Gültig bis:</span>
            <strong v-if="offer.active_until">{{ formattedActiveUntil }}</strong>
            <strong v-else>Unbegrenzt</strong>
        </div>

        <!-- Actions -->
        <div class="card-actions">
            <template v-if="delete_level == 0">
                <v-btn color="primary" variant="flat" rounded="lg" @click="editOffer(offer)">
                    <v-icon start>mdi-pencil</v-icon>
                    Bearbeiten
                </v-btn>

                <v-btn color="warning" variant="tonal" rounded="lg" @click="delete_level = 1">
                    <v-icon start>mdi-delete</v-icon>
                    Löschen
                </v-btn>
            </template>

            <template v-if="delete_level == 1">
                <v-btn color="success" variant="tonal" rounded="lg" @click="delete_level = 0">
                    <v-icon start>mdi-close</v-icon>
                    Abbrechen
                </v-btn>
                <v-btn color="error" variant="flat" rounded="lg" @click="deleteOffer(offer)">
                    <v-icon start>mdi-delete-forever</v-icon>
                    Bestätigen
                </v-btn>
            </template>
        </div>
    </div>

    <!-- Edit Offer -->
    <Offer :offer="offer_to_edit" v-if="is_edit" @finished="reloadOffers" />
</template>

<script>
import { mapWritableState } from 'pinia'
import { parseLocalDate } from '@/helpers/date'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import Offer from './Offer.vue'

export default {
    props: ['offer'],
    components: { Offer },

    async beforeMount() {
        this.offerStore = useOfferStore()
        this.tutoringStore = useTutoringStore()
    },

    data() {
        return {
            delete_level: 0,
            offerStore: null,
            offer_to_edit: null,
            is_edit: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['action', 'config']),
        ...mapWritableState(useOfferStore, ['offer_config']),

        selectedClasses() {
            return Object.entries(this.offer.classes || {})
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

        formattedActiveUntil() {
            if (!this.offer.active_until) return ''
            const date = parseLocalDate(this.offer.active_until)
            const weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']
            const weekday = weekdays[date.getDay()]
            const day = String(date.getDate()).padStart(2, '0')
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const year = date.getFullYear()
            return `${day}.${month}.${year} (${weekday})`
        },
    },

    methods: {
        async reloadOffers() {
            this.is_edit = false
            await this.offerStore.loadMyOffers()
        },

        async toggleActive(offer) {
            if (!(await this.offerStore.toggleActive(offer.id))) return
            offer.is_active = !offer.is_active
        },

        editOffer(offer) {
            this.offer_to_edit = offer
            this.is_edit = true
            this.action = 'edit_offer'
        },

        async deleteOffer(offer) {
            if (!(await this.offerStore.delete(offer))) return
            await this.offerStore.loadMyOffers()
            this.delete_level = 0
        },
    },
}
</script>

<style scoped>
.offer-card {
    position: relative;
    background: white;
    border-radius: 16px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
}

.offer-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

/* Card Glow */
.card-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.glow-online {
    background: linear-gradient(90deg, #3aaa35, #4bc044);
}

.glow-offline {
    background: linear-gradient(90deg, #78909c, #90a4ae);
}

/* Card Header */
.card-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 20px 20px 16px;
    background: linear-gradient(135deg, rgba(58, 170, 53, 0.06), rgba(58, 170, 53, 0.02));
}

.subject-badge {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #3aaa35, #2d8a2a);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.subject-short {
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
}

.subject-info {
    flex: 1;
    min-width: 0;
}

.subject-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 4px 0;
    line-height: 1.3;
}

.offer-title {
    font-size: 0.85rem;
    color: #607d8b;
    display: block;
}

/* Status Section */
.status-section {
    padding: 0 20px 16px;
}

.status-pending {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 152, 0, 0.08);
    border: 1px solid rgba(255, 152, 0, 0.2);
    border-radius: 10px;
}

.status-icon {
    flex-shrink: 0;
}

.status-text {
    display: flex;
    flex-direction: column;
}

.status-label {
    font-weight: 600;
    font-size: 0.85rem;
    color: #e65100;
}

.status-detail {
    font-size: 0.75rem;
    color: #ff9800;
}

.status-approved {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    width: fit-content;
}

.status-badge.success {
    background: rgba(58, 170, 53, 0.1);
    color: #2e7d32;
}

.online-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    background: #f5f5f5;
    border-radius: 10px;
}

.toggle-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 0.85rem;
}

.toggle-status.is-online {
    color: #2e7d32;
}

.toggle-status.is-offline {
    color: #78909c;
}

/* Stats Section */
.stats-section {
    display: flex;
    gap: 8px;
    padding: 0 20px 16px;
}

.stat-item {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 10px;
}

.stat-icon {
    flex-shrink: 0;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-weight: 700;
    font-size: 0.95rem;
    color: #263238;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.7rem;
    color: #90a4ae;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Description */
.description-section {
    padding: 0 20px 16px;
}

.description-text {
    font-size: 0.9rem;
    color: #546e7a;
    line-height: 1.5;
    margin: 0;
    white-space: pre-line;
}

/* Classes Section */
.classes-section {
    padding: 0 20px 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.classes-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.classes-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #546e7a;
}

.classes-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.class-chip {
    padding: 4px 10px;
    background: rgba(58, 170, 53, 0.1);
    color: #2e7d32;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.chip-upper {
    background: rgba(243, 146, 0, 0.1);
    color: #e65100;
}

/* Validity */
.validity-section {
    padding: 0 20px 16px;
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    color: #607d8b;
}

.validity-section strong {
    color: #37474f;
    margin-left: 4px;
}

/* Card Actions */
.card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 16px 20px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
    margin-top: auto;
}

.card-actions .v-btn {
    flex: 1;
    min-width: 100px;
}

/* Responsive */
@media (max-width: 400px) {
    .stats-section {
        flex-wrap: wrap;
    }

    .stat-item {
        flex: 1 1 45%;
    }

    .card-actions {
        flex-direction: column;
    }

    .card-actions .v-btn {
        width: 100%;
    }
}
</style>
