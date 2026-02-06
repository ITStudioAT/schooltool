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

            <v-card-actions v-if="!is_contact">
                <v-btn
                    color="success"
                    :text="offer.my_request ? 'Nachfragen' : 'Kontakt'"
                    @click="is_contact = true"
                    v-if="
                        config.auth.is_auth && !offer.is_own_offer && !offer?.my_request?.mail_at && (!offer.my_request || (offer.my_request && offer.my_request.sent_count < 3))
                    " />

                <v-chip
                    size="small"
                    color="warning"
                    v-if="config.auth.is_auth && !offer.is_own_offer && offer.my_request && !offer?.my_request?.mail_at && offer.my_request.sent_count >= 3">
                    Bereits {{ offer?.my_request?.sent_count }}x nachgefragt
                </v-chip>

                <v-chip size="small" color="info" v-if="config.auth.is_auth && !offer.is_own_offer && offer.my_request && offer?.my_request?.mail_at">
                    Bereits Antwort bekommen
                </v-chip>

                <v-chip size="small" color="info" v-if="config.auth.is_auth && offer.is_own_offer">Das ist dein eigenes Angebot</v-chip>

                <v-btn class="ms-auto" text="Fertig" @click="is_offer_dialog = false" />
            </v-card-actions>

            <!-- IS_CONTACT -->
            <v-form ref="form" v-model="is_valid" @submit.prevent="sendRequest" class="mb-4" v-if="!send_request_status">
                <v-card-text v-if="is_contact">
                    <div class="text-body-1 font-weight-medium">Deine Anfrage:</div>
                    <v-alert type="info">Bitte schicke nur eine ernst gemeinte Anfrage ab!</v-alert>
                    <v-textarea
                        autofocus
                        v-model="request_message"
                        label="Deine Nachricht"
                        :rules="[maxLength(1024)]"
                        counter="1024"
                        v-if="!offer?.my_request?.sent_count || offer?.my_request?.sent_count == 0" />
                    <v-checkbox color="success" v-model="is_serious_request" label="Ich bestätige, dass es sich um eine ernst gemeinte Anfrage handelt." />
                </v-card-text>
                <v-card-actions class="d-flex flex-row align-center justify-space-between w-100" v-if="is_contact">
                    <v-btn color="error" text="Abbruch" @click="is_contact = false" />
                    <v-btn color="success" type="submit" text="Absenden" v-if="is_serious_request" />
                </v-card-actions>
            </v-form>
            <v-card-text v-if="send_request_status == 'NEW_REQUEST'">
                <v-alert type="success">Deine Anfrage wurde versandt! Bitte warte auf die Antwort.</v-alert>
            </v-card-text>

            <v-card-text v-if="send_request_status == 'EXISTING_REQUEST'">
                <v-alert type="warning">Du hast bereits eine Anfrage geschickt! Bitte warte auf die Antwort.</v-alert>
            </v-card-text>
            <v-card-actions v-if="send_request_status == 'EXISTING_REQUEST' || send_request_status == 'NEW_REQUEST'">
                <div></div>
                <v-btn class="ms-auto" text="Fertig" @click="sendRequestFinished" />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { mapWritableState } from 'pinia'
import { useOfferStore } from '@/stores/tutoring/OfferStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    props: ['offer', 'config'],
    async beforeMount() {
        this.offerStore = useOfferStore()
    },
    data() {
        return {
            offerStore: null,
            dialog: false,
            is_contact: false,
            request_message: '',
            is_serious_request: false,
            is_valid: false,
        }
    },
    computed: {
        ...mapWritableState(useOfferStore, ['is_offer_dialog', 'send_request_status', 'offer_request', 'actual_offer']),

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
        sendRequestFinished() {
            this.offer_request = null
            this.send_request_status = null
            this.is_offer_dialog = false
        },
        async sendRequest() {
            if (!this.is_serious_request) return
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            if (!this.offerStore.sendRequest(this.offer.id, this.request_message)) return
        },
    },
}
</script>
