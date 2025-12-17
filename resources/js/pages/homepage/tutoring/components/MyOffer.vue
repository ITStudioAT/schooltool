<template>
    <v-card tile flat border="md" width="300" min-height="200" class="d-flex flex-column" v-if="action == ''">
        <v-card-title>{{ offer.subject.short_name }}</v-card-title>
        <v-card-subtitle>{{ offer.subject.long_name }}</v-card-subtitle>
        <v-card-text>
            <div class="text-body-1">{{ offer.title }}</div>
            <div class="text-body-2" style="white-space: pre-line">{{ offer.description }}</div>
        </v-card-text>

        <v-card-text class="text-body-1">
            <div v-if="unterstufeClasses.length > 0" class="mb-2">
                <strong>Unterstufe:</strong>
                <div class="text-body-2">
                    {{ unterstufeClasses.join(', ') }}
                </div>
            </div>
            <div v-if="oberstufeClasses.length > 0">
                <strong>Oberstufe:</strong>
                <div class="text-body-2">
                    {{ oberstufeClasses.join(', ') }}
                </div>
            </div>
        </v-card-text>

        <v-card-text class="text-body-1">
            <div class="text-body-1 font-weight-bold">
                Gültig bis:
                <span v-if="offer.active_until">{{ formattedActiveUntil }}</span>
                <span v-else>unendlich</span>
            </div>
        </v-card-text>

        <v-card-text>
            <div class="text-body-1 font-weight-bold">
                <div v-if="!offer.is_group">Einzelunterricht</div>
                <div v-else>Gruppenunterricht</div>
                <div v-if="offer.is_group">Maximal {{ offer.max_group_members }} Teilnehmer in der Gruppe</div>
                <div>Kosten pro Stunde: {{ parseFloat(offer.price_per_hour) }} Euro</div>
            </div>
        </v-card-text>

        <v-card-text v-if="!offer.accepted_at">
            <v-alert type="warning">
                <div>Bestätigung ausstehend</div>
                <div class="text-caption">{{ offer.email_mentor }}</div>
            </v-alert>
        </v-card-text>

        <v-card-text v-if="offer.accepted_at">
            <v-alert type="success">Freigegeben!</v-alert>
            <v-card tile flat class="mt-2" v-if="offer.is_active">
                <v-card-title class="d-flex flex-row align-center ga-2 bg-success">
                    <v-icon icon="mdi-web" />
                    <div>ONLINE</div>
                </v-card-title>
                <div class="d-flex justify-end">
                    <v-btn tile flat size="small" color="error" @click="toggleActive(offer)">Ausschalten</v-btn>
                </div>
            </v-card>
            <v-card tile flat class="mt-2" v-if="!offer.is_active">
                <v-card-title class="d-flex flex-row align-center ga-2 bg-error">
                    <v-icon icon="mdi-web-off" />
                    <div>OFFLINE</div>
                </v-card-title>
                <div class="d-flex justify-end">
                    <v-btn tile flat size="small" color="success" @click="toggleActive(offer)">Einschalten</v-btn>
                </div>
            </v-card>
        </v-card-text>

        <v-card-text class="text-body-1 flex-grow-1">
            <div class="text-body-1 font-weight-bold">
                Bisherige Klicks:
                <span>{{ offer.click_count }}</span>
            </div>
        </v-card-text>

        <!-- MENÜ - Bleibt immer unten -->
        <v-card-text class="h-100 d-flex align-end justify-end">
            <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap justify-center align-center ga-2">
                <its-menu-button title="Angebot" subtitle="ändern" icon="mdi-pencil" color="primary" @click="editOffer(offer)" />
                <its-menu-button title="Angebot" subtitle="löschen" icon="mdi-delete" color="warning" @click="delete_level = 1" v-if="delete_level == 0" />
                <its-menu-button title="Angebot" subtitle="nicht löschen" icon="mdi-delete-off" color="success" @click="delete_level = 0" v-if="delete_level == 1" />
                <its-menu-button title="Angebot" subtitle="löschen" icon="mdi-delete" color="error" @click="" v-if="delete_level == 1" />
            </v-card>
        </v-card-text>
    </v-card>
    <!-- OFFER   -->

    <Offer :offer="offer" v-if="action == 'edit_offer'" @finished="reloadOffers" />
</template>
<script>
import { mapWritableState } from 'pinia'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import Offer from './Offer.vue'
export default {
    props: ['offer'],

    components: { ItsMenuButton, Offer },
    async beforeMount() {
        this.offerStore = useOfferStore()
        this.tutoringStore = useTutoringStore()
    },

    data() {
        return {
            delete_level: 0,
            offerStore: null,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['action']),
        ...mapWritableState(useOfferStore, []),

        selectedClasses() {
            // Konvertiere Object zu Array der ausgewählten Keys
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

            const date = new Date(this.offer.active_until)
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
            await this.offerStore.loadMyOffers()
        },
        async toggleActive(offer) {
            await this.offerStore.toggleActive(offer.id)
            offer.is_active = !offer.is_active
        },
        editOffer(offer) {
            this.action = 'edit_offer'
        },
    },
}
</script>
