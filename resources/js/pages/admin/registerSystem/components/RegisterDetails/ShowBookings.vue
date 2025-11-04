<template>
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Anmeldungen anzeigen" class="w-100">
            <v-card tile flat color="primary" :disabled="booking_action != ''">
                <v-card-text class="d-flex flex-row flex-wrap align-center ga-2">
                    <its-menu-button title="Zurück" subtitle="Übersicht" icon="mdi-arrow-left" color="secondary" @click="action = ''" />

                    <its-menu-button
                        :title="selected_bookings.length == 1 ? 'Anmeldung' : 'Anmeldungen'"
                        subtitle="löschen"
                        icon="mdi-delete"
                        color="warning"
                        @click="remove"
                        v-if="selected_bookings.length > 0" />
                </v-card-text>
            </v-card>

            <!-- LÖSCHEN VON ANMELDUNGEN -->
            <v-card tile flat color="warning" v-if="booking_action == 'remove_booking'">
                <v-card-text>
                    <its-grid-box color="primary" title="LÖSCHEN" class="h-100 w-100">
                        <v-form ref="form" v-model="is_valid" @submit.prevent="deleteBookings(selected_bookings, delete_notify)" class="mb-4">
                            <div class="text-h6" v-if="selected_bookings.length > 1">Sollen die markierten Anmeldungen wirklich gelöscht werden?</div>
                            <div class="text-h6" v-if="selected_bookings.length == 1">Soll die markierte Anmeldung wirklich gelöscht werden?</div>
                            <div class="d-flex flex-row align-center justify-end">
                                <v-checkbox label="Lösch-Verständigung per E-Mail?" v-model="delete_notify"></v-checkbox>
                            </div>
                            <div class="d-flex flex-row align-center justify-space-between mt-4">
                                <v-btn color="success" slim flat @click="abortBooking">Abbruch</v-btn>
                                <v-btn color="error" slim flat type="submit">Löschen</v-btn>
                            </div>
                        </v-form>
                    </its-grid-box>
                </v-card-text>
            </v-card>

            <div v-for="booking in bookings" :key="booking.id" :value="booking.id" class="mt-4">
                <div class="d-flex flex-row align-center text-body-1 ga-2">
                    <div>{{ '📅 ' + booking.date }}</div>
                    <div>{{ '🕒 ' + booking.from + ' -  ' + booking.to }}</div>
                    <div>{{ '👷 ' + booking.supervisor }}</div>
                </div>
                <v-list
                    variant="elevated"
                    select-strategy="leaf"
                    v-model:selected="selected_bookings"
                    color="success-lighten-2"
                    :disabled="booking_action != ''"
                    v-if="booking.bookings.length >= 1">
                    <v-list-item v-for="item in booking.bookings" :key="item.id" :value="item.id">
                        <template v-slot:title>
                            <div class="d-flex flex-row align-center text-body-2 ga-2">
                                <div>{{ item.last_name + ' ' + item.first_name }}</div>
                                <div>{{ '✉️ ' + item.email }}</div>
                                <div v-if="item.phone">{{ '☎️ ' + item.phone }}</div>
                            </div>
                            <div class="d-flex flex-row align-center text-body-1 ga-2">
                                <div>{{ '👩‍🎓 ' + item.student_last_name + ' ' + item.student_first_name }}</div>
                                <div class="text-body-2" v-if="item.student_birthdate">
                                    {{ '🎂 ' + item.student_birthdate }}
                                </div>
                            </div>
                            <div class="text-caption" v-if="item.note">{{ item.note }}</div>
                        </template>
                    </v-list-item>
                </v-list>
                <div class="mt-4 text-body-1 font-weight-bold" v-if="booking.bookings.length == 0">Keine Buchungen vorhanden!</div>
            </div>
        </its-grid-box>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useRegisterDateStore } from '@/stores/admin/RegisterDateStore'
import { useRegisterDateBookingStore } from '@/stores/admin/RegisterDateBookingStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
        this.registerDateStore = useRegisterDateStore()
        this.registerDateBookingStore = useRegisterDateBookingStore()
        await this.loadBookings(this.selected_register_dates)
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
            registerDateStore: null,
            registerDateBookingStore: null,
            is_valid: false,
            search_string: '',
            is_valid: false,
            booking_action: '',
            delete_notify: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterStore, []),
        ...mapWritableState(useRegisterDateStore, ['register_dates', 'selected_register_dates']),
        ...mapWritableState(useRegisterDateBookingStore, ['bookings', 'selected_bookings']),
    },
    watch: {},

    methods: {
        abortBooking() {
            this.booking_action = ''
        },
        async loadBookings(dates) {
            await this.registerDateBookingStore.loadBookings(dates)
        },

        async deleteBookings(bookings, notify) {
            var bookings_90 = bookings

            if (!(await this.registerDateBookingStore.deleteBookings(bookings, notify))) return

            // Löschen der Buchungen aus deer Übersicht
            this.bookings = this.bookings.map((dateBlock) => {
                // Filter out bookings that should be deleted
                const remainingBookings = dateBlock.bookings.filter((b) => !bookings.includes(b.id))

                // Calculate how many were removed
                const removedCount = dateBlock.bookings.length - remainingBookings.length

                return {
                    ...dateBlock,
                    bookings: remainingBookings,
                    count_bookings: Math.max(0, dateBlock.count_bookings - removedCount),
                }
            })

            // Löschen der Buchungen aus den gesamten Buchungen
            this.bookings = this.bookings.filter((booking) => !bookings.includes(booking.id))

            this.register_dates = this.register_dates.map((date) => {
                // Filter out bookings whose IDs are in bookings_90
                const remainingBookings = date.bookings.filter((booking) => !bookings_90.includes(booking.id))

                // Calculate how many were removed
                const removedCount = date.bookings.length - remainingBookings.length

                // Return updated date entry
                return {
                    ...date,
                    bookings: remainingBookings,
                    count_bookings: Math.max(0, date.count_bookings - removedCount),
                }
            })

            this.booking_action = ''
        },
        remove() {
            this.delete_notify = false
            this.booking_action = 'remove_booking'
        },
        abort() {
            this.action = ''
        },
    },
}
</script>
