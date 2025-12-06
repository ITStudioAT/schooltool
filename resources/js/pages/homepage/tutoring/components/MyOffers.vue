<template>
    <v-card-text v-if="is_loaded">
        <v-card tile flat color="tutoring_card">
            <v-card-title class="bg-tutoring_card_title mb-2">Meine Angebote</v-card-title>
            <v-card-text class="d-flex flex-row flex-wrap ga-2">
                <MyOffer v-for="offer in my_offers" :key="offer.id" :offer="offer" />
            </v-card-text>
        </v-card>
        <v-card>
            {{ my_offers }}
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
