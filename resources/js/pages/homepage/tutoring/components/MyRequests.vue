<template>
    <v-card-text v-if="is_loaded">
        <v-card tile flat color="tutoring_card">
            <v-card-title class="bg-tutoring_card_title mb-2">Meine Anfragen</v-card-title>

            <!-- Es existieren Angebote -->
            <v-card-text class="d-flex flex-row flex-wrap ga-2" v-if="my_requests && my_requests.length > 0">
                <v-card
                    v-for="request in formattedRequests"
                    :key="request.id"
                    tile
                    flat
                    class="border-md flex-grow-1 px-4 py-3"
                    color="transparent"
                    style="min-width: 320px; max-width: 420px">
                    <div class="d-flex flex-row justify-space-between flex-wrap ga-2">
                        <div class="text-body-1 font-weight-medium">Anfrage zu Angebot #{{ request.offer_id }}</div>
                        <div class="text-caption text-medium-emphasis">Erstellt am {{ request.createdLabel }}</div>
                    </div>

                    <div class="text-body-2 mt-2" v-if="request.message">
                        {{ request.message }}
                    </div>
                    <div class="text-body-2 font-italic text-medium-emphasis mt-2" v-else>Keine Nachricht angegeben.</div>

                    <div class="d-flex flex-row flex-wrap ga-2 mt-3">
                        <v-chip size="small" color="info" v-if="request.sentLabel">Gesendet: {{ request.sentLabel }}</v-chip>
                        <v-chip size="small" color="info" v-if="request.seenLabel">Gesehen: {{ request.seenLabel }}</v-chip>
                        <v-chip size="small" color="warning" v-if="request.archived_at">Archiviert</v-chip>
                    </div>
                </v-card>
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
        formattedRequests() {
            if (!Array.isArray(this.my_requests)) return []
            return this.my_requests.map((request) => {
                const createdLabel = this.formatDate(request.created_at) || '-'
                const sentLabel = this.formatDate(request.last_sent_at || request.sent_at)
                const seenLabel = this.formatDate(request.last_seen_at || request.seen_at)

                return {
                    ...request,
                    createdLabel,
                    sentLabel,
                    seenLabel,
                }
            })
        },
    },

    watch: {},

    methods: {
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
