<template>
    <div class="my-offers-section" v-if="is_loaded">
        <!-- Section Header -->
        <div class="section-header">
            <div class="header-icon">
                <v-icon size="28" color="white">mdi-book-multiple</v-icon>
            </div>
            <div class="header-text">
                <h2 class="section-title">Meine Angebote</h2>
                <p class="section-subtitle">Verwalte Deine Nachhilfe-Angebote</p>
            </div>
        </div>

        <!-- Offers Grid -->
        <div class="offers-grid" data-testid="tutoring-my-offers-grid" v-if="my_offers && my_offers.length > 0">
            <MyOffer v-for="offer in my_offers" :key="offer.id" :offer="offer" />
        </div>

        <!-- No Offers -->
        <div class="no-offers" v-else>
            <div class="no-offers-card">
                <div class="no-offers-icon">
                    <v-icon size="64" color="grey-lighten-1">mdi-book-plus-outline</v-icon>
                </div>
                <h3 class="no-offers-title">Kein Angebot vorhanden</h3>
                <p class="no-offers-text">
                    Du hast noch kein Angebot erstellt. Unter "Neue Nachhilfe" kannst Du ein neues Angebot erstellen.
                </p>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div class="loading-state" v-else>
        <v-progress-circular indeterminate color="primary" size="48" />
        <p>Angebote werden geladen...</p>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import MyOffer from './MyOffer.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { MyOffer },

    async beforeMount() {
        this.offerStore = useOfferStore()
        await this.offerStore.loadMyOffers()
        this.is_loaded = true
    },

    data() {
        return {
            offerStore: null,
            is_loaded: false,
        }
    },

    computed: {
        ...mapWritableState(useOfferStore, ['my_offers']),
    },
}
</script>

<style scoped>
.my-offers-section {
    margin-top: 8px;
}

/* Section Header */
.section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px 24px;
    background: linear-gradient(135deg, #3AAA35 0%, #2d8a2a 100%);
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

/* Offers Grid */
.offers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

/* No Offers */
.no-offers {
    display: flex;
    justify-content: center;
    padding: 20px 0;
}

.no-offers-card {
    text-align: center;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    padding: 48px 32px;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    max-width: 400px;
}

.no-offers-icon {
    margin-bottom: 20px;
}

.no-offers-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #263238;
    margin: 0 0 12px 0;
}

.no-offers-text {
    font-size: 0.95rem;
    color: #607D8B;
    line-height: 1.6;
    margin: 0;
}

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    gap: 16px;
    color: #607D8B;
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

    .offers-grid {
        grid-template-columns: 1fr;
    }

    .no-offers-card {
        padding: 32px 24px;
    }
}
</style>
