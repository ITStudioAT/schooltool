<template>
    <v-card-text v-if="is_loaded">
        <v-card tile flat color="tutoring_card">
            <v-card-title class="bg-tutoring_card_title mb-2">Meine Angebote</v-card-title>

            <!-- Es existieren Angebote -->
            <v-card-text class="d-flex flex-row flex-wrap ga-2" v-if="my_offers && my_offers.length > 0">
                <MyOffer v-for="offer in my_offers" :key="offer.id" :offer="offer" />
            </v-card-text>

            <!-- Es existieren KEINE Angebote -->
            <v-card-text class="d-flex flex-row flex-wrap ga-2" v-else>
                <v-alert
                    type="warning"
                    title="Kein Angebot vorhanden"
                    text="Du hast noch kein Angebot erstellt. Unter 'Neue Nachhilfe' kann Du ein neues Angebot erstellen."></v-alert>
            </v-card-text>
        </v-card>
    </v-card-text>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import MyOffer from './MyOffer.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox, MyOffer },

    async beforeMount() {
        this.offerStore = useOfferStore()
        await this.offerStore.loadMyOffers()
        this.is_loaded = true
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            offerStore: null,
            is_loaded: false,
        }
    },

    computed: {
        ...mapWritableState(useOfferStore, ['my_offers']),
    },

    watch: {},

    methods: {},
}
</script>
