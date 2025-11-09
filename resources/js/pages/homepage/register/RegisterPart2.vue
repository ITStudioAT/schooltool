<template>
    <v-container fluid class="h-100 w-100 d-flex flex-column align-center justify-center bg-background" v-if="config">
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary">
            <v-card-title class="d-flex flex-row align-center">
                <img :src="`/storage/images/${config?.school?.logo}`" alt="Logo" class="logo" v-if="config?.school?.logo" />
                <div class="ml-2"></div>
            </v-card-title>

            <!-- ANZEIGE DER SCHULE-->
            <v-card-subtitle class="d-flex flex-row align-center justify-space-between">
                <div>
                    {{ config?.school?.long_name }}
                </div>
            </v-card-subtitle>

            <!-- REGISTER PART 2 -->
            <v-card class="mx-auto w-100" max-width="600" tile flat color="primary">
                <v-card-title>{{ active_register.name }}</v-card-title>

                <!-- description_on_website anzeigen -->
                <v-alert closable tile color="primary" border="start" border-color="secondary" class="mt-4" v-if="active_register.description_on_website">
                    <div style="white-space: pre-line">
                        {{ active_register.description_on_website }}
                    </div>
                </v-alert>

                <v-card-text class="bg-secondary">
                    <div class="text-h6">
                        {{ config?.user?.last_name + ' ' + config?.user?.first_name }}
                    </div>
                    <div class="text-body-1 mt-2">
                        {{ '✉️ ' + config?.user?.email }}
                    </div>
                    <div class="text-body-1 mt-2" v-if="config?.user?.phone">
                        {{ '📞 ' + config?.user?.phone }}
                    </div>
                </v-card-text>

                <!-- TERMINE ANZEIGEN -->
                <v-card-text v-if="action == '' && bookings.length == 0">
                    <div class="text-h6">Verfügbare Tage:</div>

                    <div class="d-flex flex-row flex-wrap align-center ga-2 mt-2">
                        <its-menu-button
                            :title="date.date"
                            :subtitle="date.weekday"
                            :color="selected_date == date ? 'success' : 'secondary'"
                            v-for="date in dates"
                            :key="date.date"
                            @click="selectDate(date)" />
                    </div>

                    <v-list variant="elevated" select-strategy="single-leaf" v-model:selected="selected_register_date" color="success-lighten-2" class="mt-4">
                        <v-list-item
                            v-for="register_date in possibleRegisterDates"
                            :key="register_date.id"
                            :value="register_date.id"
                            :disabled="register_date.is_locked || register_date.max_registrations - register_date.bookings_count == 0">
                            <div class="d-flex flex-row align-center justify-space-between">
                                <div>{{ register_date.from + ' - ' + register_date.to }}</div>
                                <div class="text-success" v-if="!register_date.is_locked && register_date.max_registrations - register_date.bookings_count > 0">
                                    {{ register_date.max_registrations - register_date.bookings_count + ' frei' }}
                                </div>
                                <div class="text-error" v-if="!register_date.is_locked && register_date.max_registrations - register_date.bookings_count == 0">ausgebucht</div>
                                <div class="text-warning" v-if="register_date.is_locked">gesperrt</div>
                            </div>
                        </v-list-item>
                    </v-list>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" slim flat rounded="0" @click="logout">Abmelden</v-btn>
                        <v-btn color="success" slim flat rounded="0" @click="editKid" v-if="selected_register_date.length > 0">Weiter</v-btn>
                    </div>
                </v-card-text>

                <!-- ES EXISTIEREN BUCHUNGEN -->
                <v-card-text v-if="action == '' && bookings.length > 0">
                    <div class="text-h6">Sie haben gebucht:</div>
                    <div v-for="booking in bookings" :key="booking.id">
                        <div class="border-md border-secondary mb-2">
                            <div class="d-flex flex-row justify-space-between">
                                <div class="d-flex flex-row align-center ga-2 text-body-1 px-2 py-1">
                                    <div>
                                        {{ '📅 ' + booking.date }}
                                    </div>
                                    <div>
                                        {{ '🕒 ' + booking.from + ' - ' + booking.to }}
                                    </div>
                                </div>
                                <v-btn tile flat color="error" size="small" icon="mdi-delete" @click="deleteBooking(booking)"></v-btn>
                            </div>
                            <div class="px-2 py-1">
                                {{ '🎓 ' + ((booking.student_last_name || '') + ' ' + (booking.student_first_name || '')) }}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" slim flat rounded="0" @click="logout">Abmelden</v-btn>
                    </div>
                </v-card-text>

                <!-- BUCHUNG ERFASSEN UND BESTÄTIGEN -->
                <v-card-text v-if="action == 'edit_kid'">
                    <div>
                        <div>Terminwunsch:</div>
                        <div class="text-h6 d-flex flex-row ga-2">
                            <div>
                                {{ '📅 ' + registerDate(selected_register_date[0]).date + ', ' + weekday(registerDate(selected_register_date[0]).date) }}
                            </div>
                            <div>
                                {{ '🕒 ' + registerDate(selected_register_date[0]).from + ' - ' + registerDate(selected_register_date[0]).to + ' Uhr' }}
                            </div>
                        </div>
                    </div>

                    <div class="text-h6 mt-4">Ihr Kind:</div>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="book(data)" class="mb-4">
                        <v-row v-if="active_register.show_student_last_name">
                            <v-col cols="12">
                                <v-text-field
                                    autofocus
                                    v-model="data.student_last_name"
                                    label="Nachname des Schülers"
                                    :rules="active_register.must_student_last_name ? [required(), maxLength(255)] : [maxLength(255)]" />
                            </v-col>
                        </v-row>
                        <v-row v-if="active_register.show_student_first_name">
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.student_first_name"
                                    label="Vorname des Schülers"
                                    :rules="active_register.must_student_first_name ? [required(), maxLength(255)] : [maxLength(255)]" />
                            </v-col>
                        </v-row>

                        <v-row v-if="active_register.show_student_birthdate">
                            <v-col cols="6">
                                <v-text-field
                                    v-model="data.student_birthdate"
                                    label="Geburtsdatum (JJJJ-MM-TT)"
                                    :rules="active_register.must_student_birthdate ? [required(), date()] : [date()]" />
                            </v-col>
                        </v-row>

                        <v-row v-if="active_register.show_note">
                            <v-col cols="12">
                                <v-text-field v-model="data.note" label="Anmerkungen" :rules="active_register.must_note ? [required(), maxLength(255)] : [maxLength(255)]" />
                            </v-col>
                        </v-row>

                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="logout">Abmelden</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="selected_register_date.length > 0">Buchen</v-btn>
                        </div>
                    </v-form>
                </v-card-text>

                <!-- BUCHUNG ERFOLGREICH DURCHGEFÜHRT -->
                <v-card-text v-if="action == 'booked'">
                    <div>
                        <div>Terminwunsch:</div>
                        <div class="text-h6 d-flex flex-row ga-2">
                            <div>
                                {{ '📅 ' + registerDate(selected_register_date[0]).date + ', ' + weekday(registerDate(selected_register_date[0]).date) }}
                            </div>
                            <div>
                                {{ '🕒 ' + registerDate(selected_register_date[0]).from + ' - ' + registerDate(selected_register_date[0]).to + ' Uhr' }}
                            </div>
                        </div>
                    </div>

                    <div class="text-h6 mt-4">Anmeldung für:</div>

                    <div class="text-body-1">
                        <div>{{ data.student_last_name + ' ' + data.student_first_name }}</div>
                        <div v-if="data.student_birthdate">{{ 'Geburtsdatum: ' + data.student_birthdate }}</div>
                        <div v-if="data.note">{{ data.note }}</div>
                    </div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" slim flat rounded="0" @click="logout">Abmelden</v-btn>
                        <v-btn color="success" slim flat rounded="0" @click="bookingFinished">Übersicht</v-btn>
                    </div>
                </v-card-text>
            </v-card>
        </v-card>
    </v-container>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'
import { useRegisterStore } from '@/stores/homepage/RegisterStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton },

    async beforeMount() {
        this.homepageStore = useHomepageStore()
        this.registerStore = useRegisterStore()
        await this.registerStore.loadRegisterAndUser()
        if (this.dates.length > 0) this.selected_date = this.dates[0]
    },

    unmounted() {},

    data() {
        return {
            homepageStore: null,
            registerStore: null,
            selected_date: null,
            selected_register_date: [],
            action: '',
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useRegisterStore, ['config', 'registers', 'active_register', 'selected_register_id', 'data', 'register_dates', 'dates', 'bookings']),

        possibleRegisterDates() {
            if (!this.selected_date || !this.selected_date.date) return []
            return this.register_dates.filter((d) => d.date === this.selected_date.date)
        },
    },

    watch: {},
    methods: {
        async deleteBooking(booking) {
            if (!(await this.registerStore.deleteBooking(booking.id))) return
            await this.registerStore.loadRegisterAndUser()
            if (this.dates.length > 0) this.selected_date = this.dates[0]
            this.selected_register_date = []
        },

        async book(input) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            const data = {
                register_id: this.active_register?.id ?? null,
                register_date_id: this.selected_register_date[0] ?? null,
                student_last_name: input?.student_last_name ?? null,
                student_first_name: input?.student_first_name ?? null,
                student_birthdate: input?.student_birthdate ?? null,
                note: input?.note ?? null,
            }

            if (!(await this.registerStore.book(data))) return

            this.action = 'booked'
            //
        },

        async bookingFinished() {
            this.action = ''
            await this.registerStore.loadRegisterAndUser()
            if (this.dates.length > 0) this.selected_date = this.dates[0]
            this.selected_register_date = []
        },

        weekday(date) {
            if (!date) return '' // guard for null/undefined
            const d = new Date(date)

            // ensure it's a valid date
            if (isNaN(d)) return ''

            // use Intl.DateTimeFormat for German locale
            return new Intl.DateTimeFormat('de-DE', { weekday: 'long' }).format(d)
        },

        registerDate(register_date_id) {
            return this.register_dates.find((d) => d.id === register_date_id)
        },

        editKid() {
            if (!this.data.student_last_name) this.data.student_last_name = this.config?.user?.last_name
            this.action = 'edit_kid'
        },

        selectDate(date) {
            this.selected_date = date
        },
        async logout() {
            await this.homepageStore.logout()
            this.$router.push('/homepage')
        },
    },
}
</script>
<style scoped>
.logo {
    display: block;
    max-height: 90px; /* or 2em, relative to font size */
    height: auto;
    width: auto;
    object-fit: contain;
}
</style>
;
