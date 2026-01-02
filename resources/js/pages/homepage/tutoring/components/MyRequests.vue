<template>
    <v-card-text v-if="is_loaded">
        <v-card tile flat color="tutoring_card">
            <v-card-title class="bg-tutoring_card_title mb-2">Meine Anfragen</v-card-title>

            <!-- Es existieren Angebote -->
            <v-card-text class="d-flex flex-row flex-wrap ga-2" v-if="my_requests && my_requests.length > 0">
                Meine Anfrage
                <!--
                <MyOffer v-for="offer in my_offers" :key="offer.id" :offer="offer" />
                -->
            </v-card-text>

            <!-- Es existieren KEINE Angebote -->
            <v-card-text class="d-flex flex-row flex-wrap ga-2" v-else>
                <v-alert type="warning" title="Keine Anfrage vorhanden" text="Du hast noch kein Anfrage erstellt. Klicke auf ein Angebot und danach auf 'Kontakt'."></v-alert>
            </v-card-text>

            <v-card-text>my_requests: {{ my_requests }}</v-card-text>
        </v-card>
    </v-card-text>
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
        await this.requestStore.loadMyRequests()
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
        ...mapWritableState(useRequestStore, ['my_requests']),
    },

    watch: {},

    methods: {},
}
</script>
