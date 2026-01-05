<template>
    <v-card-text v-if="is_loaded">
        <v-card tile flat color="tutoring_card">
            <v-card-title class="bg-tutoring_card_title mb-2">Meine Anfragen</v-card-title>

            <!-- Es existieren Angebote -->
            <v-card-text v-if="requests && requests.length > 0" class="pa-0">
                <v-card v-for="request in requests" :key="request.id" tile flat class="d-flex flex-row align-start justify-space-between ga-2 border-md mb-2" color="transparent">
                    <v-card tile flat color="transparent" class="w-100 pa-2">
                        <div class="d-flex flex-row justify-space-between flex-wrap ga-2">
                            <div class="text-body-1 font-weight-medium">Anfrage {{ request.school.short_name + ' (' + request.school.long_name + ')' }}</div>

                            <div class="text-caption text-medium-emphasis">Gesendet am: {{ request.sent_at }}</div>
                        </div>
                        <div class="text-body-1 font-weight-medium">{{ request.offer.subject.short_name + ': ' + request.offer.subject.long_name }}</div>
                        <div class="text-body-2 mt-2" v-if="request.offer.title">
                            {{ request.offer.title + ': ' + request.offer.description }}
                        </div>
                        <div class="d-flex flex-row align-center flex-wrap ga-2 mt-2">
                            <v-chip size="small" color="info" v-if="request.last_sent_at">
                                Zuletzt nachgefragt
                                <span v-if="request.sent_count > 0">&nbsp;({{ request.sent_count }}x)</span>
                                : {{ request.last_sent_at }}
                            </v-chip>
                            <v-chip size="small" color="success" v-if="request.last_seen_at || request.seen_at">Gelesen: {{ request.last_seen_at || request.seen_at }}</v-chip>
                            <v-chip size="small" color="warning" v-if="!request.seen_at && !request.last_seen_at">Noch nicht gelesen</v-chip>
                        </div>
                    </v-card>
                    <v-card style="width: 100px; flex-shrink: 0" class="h-100 d-flex flex-column ga-2" tile flat color="transparent">
                        <v-btn block tile flat size="small" color="warning" v-if="!request.seen_at && !request.last_seen_at">Löschen</v-btn>
                        <v-btn block tile flat size="small" color="primary">Archivieren</v-btn>
                    </v-card>
                </v-card>

                <v-card-text class="d-flex flex-row flex-wrap ga-2 align-center justify-space-between">
                    <v-btn
                        tile
                        flat
                        size="x-large"
                        prepend-icon="mdi-arrow-left"
                        color="primary"
                        :disabled="meta.current_page == 1"
                        @click="requestStore.index(meta.current_page - 1)">
                        Vorherige
                    </v-btn>
                    <v-btn
                        tile
                        flat
                        size="x-large"
                        append-icon="mdi-arrow-right"
                        color="primary"
                        :disabled="meta.current_page == meta.last_page"
                        @click="requestStore.index(meta.current_page + 1)">
                        Nächste
                    </v-btn>
                </v-card-text>
            </v-card-text>

            <!-- Es existieren KEINE Angebote -->
            <v-card-text class="d-flex flex-row flex-wrap ga-2" v-else>
                <v-alert type="warning" title="Keine Anfrage vorhanden" text="Du hast noch kein Anfrage erstellt. Klicke auf ein Angebot und danach auf 'Kontakt'."></v-alert>
            </v-card-text>
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
        await this.requestStore.index()
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
        ...mapWritableState(useRequestStore, ['requests', 'meta']),
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
