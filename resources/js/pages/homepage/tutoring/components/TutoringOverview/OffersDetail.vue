<template>
    <v-dialog v-model="is_offer_dialog" max-width="500">
        <v-card class="w-100">
            <v-card-title>
                {{ offer.school.short_name }}
            </v-card-title>
            <v-card-subtitle>{{ offer.school.long_name }}</v-card-subtitle>

            <v-card-title>
                {{ offer.subject.short_name }}
            </v-card-title>
            <v-card-subtitle>{{ offer.subject.long_name }}</v-card-subtitle>
            <v-card-text>
                <div class="text-h6">{{ offer.title }}</div>
                <div class="mt-2 text-body-1" style="white-space: pre-line">
                    {{ offer.description }}
                </div>
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
            <v-card-actions>
                <v-btn class="ms-auto" text="Ok" @click="is_offer_dialog = false"></v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
<script>
import { mapWritableState } from 'pinia'
import { useOfferStore } from '@/stores/tutoring/OfferStore'

export default {
    props: ['offer'],
    async beforeMount() {
        this.offerStore = useOfferStore()
    },
    data() {
        return {
            offerStore: null,
            dialog: false,
        }
    },
    computed: {
        ...mapWritableState(useOfferStore, ['is_offer_dialog']),

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
}
</script>
