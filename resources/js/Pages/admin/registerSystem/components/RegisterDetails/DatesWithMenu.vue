<template>
    <!-- TERMINE -->
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Termine" class="w-100" v-if="register_dates.length == 0">
            <div class="d-flex flex-row align-cebter justify-space-between">
                <div>Keine Termine vorhanden!</div>
                <v-btn
                    flat
                    tile
                    class="mt-1 ml-2"
                    color="primary"
                    variant="outlined"
                    icon="mdi-refresh"
                    @click="refresh"></v-btn>
            </div>
        </its-grid-box>
        <its-grid-box color="primary" title="Termine" class="w-100" v-if="register_dates.length > 0">
            <v-card tile flat color="transparent">
                <v-form ref="form" v-model="is_valid" @submit.prevent="search(search_string)" class="mb-4">
                    <div class="d-flex flex-row align-start">
                        <v-text-field
                            clearable
                            autofocus
                            v-model="search_string"
                            label="Suche"
                            :rules="[maxLength(255)]"
                            @click:clear="refresh" />
                        <v-btn
                            flat
                            tile
                            class="mt-1 ml-2"
                            color="primary"
                            variant="outlined"
                            icon="mdi-magnify"
                            tyoe="submit"
                            @click="search(search_string)" />
                    </div>
                </v-form>
            </v-card>
            <!-- Tage zur Auswahl -->
            <v-card
                tile
                flat
                color="transparent"
                class="d-flex flex-row flex-wrap align-center ga-2"
                :disabled="action != ''">
                <its-menu-button
                    :title="day.date"
                    :subtitle="day.day"
                    :color="day == selected_day ? 'success' : 'primary'"
                    @click="selectDay(day)"
                    v-for="day in days" />
            </v-card>

            <!-- Abwählen / Auswählen-->
            <v-card
                tile
                flat
                color="transparent"
                class="d-flex flex-row flex-wrap align-center ga-2 mt-2"
                :disabled="action != ''">
                <v-btn color="primary" slim flat tile @click="selectAllRegisterDates">
                    Alle auswählen [{{ register_dates.length - selected_register_dates.length }}]
                </v-btn>
                <v-btn color="primary" slim flat tile @click="selectNoRegisterDates">
                    Alle abwählen [{{ selected_register_dates.length }}]
                </v-btn>
            </v-card>

            <v-list
                variant="elevated"
                select-strategy="leaf"
                v-model:selected="selected_register_dates"
                color="success-lighten-2"
                :disabled="action != ''">
                <v-list-item v-for="register_date in register_dates" :key="register_date.id" :value="register_date.id">
                    <template v-slot:title>
                        <div
                            class="d-flex flex-row align-center justify-space-between"
                            :class="registerDateClass(register_date)">
                            <div>
                                <div class="text-body-1">
                                    {{
                                        register_date.from +
                                        ' - ' +
                                        register_date.to +
                                        ' [' +
                                        register_date.count_bookings +
                                        '/' +
                                        register_date.max_registrations +
                                        ']'
                                    }}
                                </div>
                                <div class="text-caption" v-if="search_string != ''">
                                    {{ register_date.date }}
                                </div>
                            </div>
                            <div class="text-body-2">
                                {{ register_date.supervisor }}
                            </div>
                        </div>
                    </template>
                </v-list-item>
            </v-list>
        </its-grid-box>
    </v-col>

    <!-- MENÜ -->
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Menü" class="w-100" :disabled="action != ''">
            <div class="d-flex flex-row flex-wrap align-center ga-2">
                <v-card
                    tile
                    flat
                    color="transparent"
                    class="d-flex flex-row flex-wrap align-center ga-2"
                    v-if="selected_register_dates.length == 1">
                    <its-menu-button
                        title="Person"
                        subtitle="anmelden"
                        icon="mdi-account-plus"
                        color="primary"
                        @click="addPerson(selected_register_dates[0])" />

                    <its-menu-button
                        title="Anmeldungen"
                        subtitle="anzeigen"
                        icon="mdi-view-list"
                        color="primary"
                        @click="" />
                </v-card>

                <v-card
                    tile
                    flat
                    color="transparent"
                    class="d-flex flex-row flex-wrap align-center ga-2"
                    v-if="selected_register_dates.length >= 1">
                    <its-menu-button
                        :title="selected_register_dates.length == 1 ? 'Termin' : 'Termine'"
                        subtitle="sperren"
                        icon="mdi-lock"
                        color="warning-lighten-2"
                        @click="addDates" />

                    <its-menu-button
                        :title="selected_register_dates.length == 1 ? 'Termin' : 'Termine'"
                        subtitle="entsperren"
                        icon="mdi-lock-open"
                        color="success-lighten-2"
                        @click="addDates" />

                    <its-menu-button
                        :title="selected_register_dates.length == 1 ? 'Termin' : 'Termine'"
                        subtitle="löschen"
                        icon="mdi-delete"
                        color="warning"
                        @click="addDates" />
                </v-card>

                <its-menu-button
                    title="Termine"
                    subtitle="anlegen"
                    icon="mdi-calendar-plus"
                    color="primary"
                    @click="addDates" />
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
        this.register_dates = []
        await this.loadDays()
        this.selected_day = null
        if (this.days.length > 0) this.selected_day = this.days[0]
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
            is_valid: false,
            search_string: '',
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, [
            'config',
            'selected_school',
            'selected_schoolyear',
            'selected_register',
            'selected_active_register',
            'action',
        ]),
        ...mapWritableState(useRegisterStore, []),
        ...mapWritableState(useRegisterDateStore, [
            'days',
            'selected_day',
            'register_dates',
            'data',
            'selected_register_dates',
        ]),
        ...mapWritableState(useRegisterDateBookingStore, ['person']),
    },
    watch: {
        async selected_day() {
            this.selected_register_dates = []
            if (this.selected_day) await this.loadRegisterDates(this.selected_day.date)
        },
    },

    methods: {
        async refresh() {
            this.search_string = ''
            if (this.days.length == 0) return
            if (!this.selected_day) this.selected_day = this.days[0]
            this.loadRegisterDates(this.selected_day.date)
        },
        async search(search_string) {
            if (!search_string || search_string == '') {
                await this.loadRegisterDates(this.selected_day.date)
                return
            }
            this.selected_day = null
            await this.registerDateStore.filterRegisterDates(search_string)
        },

        registerDateClass(register_date) {
            if (register_date.count_bookings > register_date.max_registrations) return 'text-error'
            if (register_date.count_bookings == register_date.max_registrations) return 'text-warning'
            if (register_date.count_bookings > 0) return 'text-success'
        },
        selectAllRegisterDates() {
            this.selected_register_dates = this.register_dates.map((item) => item.id)
        },

        selectNoRegisterDates() {
            this.selected_register_dates = []
        },

        selectDay(day) {
            this.selected_day = day
        },
        async loadDays() {
            this.register_dates = []
            this.search_string = ''
            await this.registerDateStore.loadDays()
        },

        async loadRegisterDates(date) {
            await this.registerDateStore.loadRegisterDates(date)
        },

        addPerson(register_date) {
            this.person = {
                register_date_id: register_date.id,
            }
            this.action = 'add_person'
        },

        addDates() {
            this.data = {
                date_from: new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10),
                time_from: '08:00',
                time_until: '12:00',
                monday: true,
                tuesday: true,
                wednesday: true,
                thursday: true,
                friday: true,
                saturday: false,
                sunday: false,
                min_per_date: 1,
                pause: 0,
                max_registrations: 1,
            }
            this.action = 'add_dates'
        },
    },
}
</script>
