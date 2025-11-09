<template>
    <!-- TERMINE -->
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Termine" class="w-100" v-if="register_dates.length == 0">
            <div class="d-flex flex-row align-cebter justify-space-between">
                <div>Keine Termine vorhanden!</div>
                <v-btn flat tile class="mt-1 ml-2" color="primary" variant="outlined" icon="mdi-refresh" @click="refresh"></v-btn>
            </div>
        </its-grid-box>
        <its-grid-box color="primary" title="Termine" class="w-100" v-if="register_dates.length > 0">
            <v-card tile flat color="transparent">
                <v-form ref="form" v-model="is_valid" @submit.prevent="search(search_string)" class="mb-4">
                    <div class="d-flex flex-row align-start">
                        <v-text-field clearable autofocus v-model="search_string" label="Suche" :rules="[maxLength(255)]" @click:clear="refresh" />
                        <v-btn flat tile class="mt-1 ml-2" color="primary" variant="outlined" icon="mdi-magnify" tyoe="submit" @click="search(search_string)" />
                    </div>
                </v-form>
            </v-card>
            <!-- Tage zur Auswahl -->
            <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2" :disabled="action != ''">
                <its-menu-button
                    :title="day.date"
                    :subtitle="day.day + ' (' + day.bookings_count + ')'"
                    :color="day == selected_day ? 'success' : 'primary'"
                    @click="selectDay(day)"
                    v-for="day in days" />
            </v-card>

            <!-- Abwählen / Auswählen-->
            <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action != ''">
                <v-btn color="primary" slim flat tile class="text-caption" @click="selectAllRegisterDates">
                    Alle auswählen [{{ register_dates.length - selected_register_dates.length }}]
                </v-btn>
                <v-btn color="primary" slim flat tile class="text-caption" @click="selectNoRegisterDates">Alle abwählen [{{ selected_register_dates.length }}]</v-btn>
            </v-card>

            <v-list variant="elevated" select-strategy="leaf" v-model:selected="selected_register_dates" color="success-lighten-2" :disabled="action != ''">
                <v-list-item v-for="register_date in register_dates" :key="register_date.id" :value="register_date.id">
                    <template v-slot:title>
                        <div class="d-flex flex-row align-center justify-space-between" :class="registerDateClass(register_date)">
                            <div>
                                <div class="text-body-1">
                                    <v-icon icon="mdi-lock" class="mr-2" v-if="register_date.is_locked" />
                                    {{ register_date.from + ' - ' + register_date.to + ' [' + register_date.count_bookings + '/' + register_date.max_registrations + ']' }}
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
    <v-col cols="12" md="4" xl="3" v-if="action == ''">
        <its-grid-box color="primary" title="Menü" class="w-100">
            <div class="d-flex flex-column flex-wrap ga-2">
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2">
                    <its-menu-button
                        title="Person"
                        subtitle="anmelden"
                        icon="mdi-account-plus"
                        color="primary"
                        @click="addPerson(selected_register_dates[0])"
                        v-if="selected_register_dates.length == 1" />

                    <its-menu-button
                        title="Anmeldungen"
                        subtitle="anzeigen"
                        icon="mdi-view-list"
                        color="primary"
                        @click="showBookings()"
                        v-if="selected_register_dates.length >= 1" />

                    <its-menu-button title="Anmeldungen" subtitle="drucken" icon="mdi-cloud-print-outline" color="primary" @click="action = 'print'" />
                </v-card>

                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2" v-if="selected_register_dates.length >= 1">
                    <its-menu-button
                        :title="selected_register_dates.length == 1 ? 'Termin' : 'Termine'"
                        subtitle="sperren"
                        icon="mdi-lock"
                        color="warning-lighten-2"
                        @click="lockDates(selected_register_dates)" />

                    <its-menu-button
                        :title="selected_register_dates.length == 1 ? 'Termin' : 'Termine'"
                        subtitle="entsperren"
                        icon="mdi-lock-open"
                        color="success-lighten-2"
                        @click="unlockDates(selected_register_dates)" />
                </v-card>
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2" v-if="selected_register_dates.length >= 1">
                    <its-menu-button
                        :title="selected_register_dates.length == 1 ? 'Termin' : 'Termine'"
                        subtitle="löschen"
                        icon="mdi-delete"
                        color="warning"
                        @click="deleteDates" />
                </v-card>
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2">
                    <its-menu-button title="Termine" subtitle="anlegen" icon="mdi-calendar-plus" color="primary" @click="addDates" />
                </v-card>
            </div>
        </its-grid-box>
    </v-col>

    <!-- MENÜ ZUM DRUCKEN -->
    <v-col cols="12" md="4" xl="3" v-if="action == 'print'">
        <its-grid-box color="primary" title="Druck-Menü" class="w-100">
            <div class="d-flex flex-column flex-wrap ga-2">
                <div class="d-flex flex-column flex-wrap ga-2">
                    <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2">
                        <its-menu-button title="Menü" subtitle="zurück" icon="mdi-arrow-left" color="primary" @click="action = ''" />
                    </v-card>
                </div>
                <div class="d-flex flex-column flex-wrap ga-2" v-if="subaction == ''">
                    <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2">
                        <its-menu-button title="EXCEL" subtitle="Ausgabe" icon="mdi-microsoft-excel" color="primary" @click="printExcel(selected_register.id)" />
                        <its-menu-button title="PDF" subtitle="Betreuer" icon="mdi-file-pdf-box" color="primary" @click="printSupervisor(selected_register.id)" />
                        <its-menu-button title="PDF" subtitle="Tag" icon="mdi-file-pdf-box" color="primary" @click="printDate(selected_register.id)" />
                    </v-card>
                </div>
                <v-alert title="Exel Auswertung" type="info" v-if="subaction == 'excel'">
                    <template #text>
                        <div class="d-flex flex-column">
                            <div>Der Auftrag wurde erteilt. Sobald die Excel-Auswertung fertig ist, erhalten Sie das Ergebis per E-Mail.</div>
                            <v-btn class="mt-4" tile flat color="primary" @click="subaction = ''">Weiter</v-btn>
                        </div>
                    </template>
                </v-alert>
                <v-alert title="PDF Auswertung nach Betreuer" type="info" v-if="subaction == 'supervisor'">
                    <template #text>
                        <div class="d-flex flex-column">
                            <div>Der Auftrag sortiert/getrennt nach Betreuer wurde erteilt. Sobald das PDF fertig erstellt ist, erhalten Sie das Ergebis per E-Mail.</div>
                            <v-btn class="mt-4" tile flat color="primary" @click="subaction = ''">Weiter</v-btn>
                        </div>
                    </template>
                </v-alert>
                <v-alert title="PDF Auswertung nach Betreuer" type="info" v-if="subaction == 'date'">
                    <template #text>
                        <div class="d-flex flex-column">
                            <div>Der Auftrag sortiert/getrennt nach Tag wurde erteilt. Sobald das PDF fertig erstellt ist, erhalten Sie das Ergebis per E-Mail.</div>
                            <v-btn class="mt-4" tile flat color="primary" @click="subaction = ''">Weiter</v-btn>
                        </div>
                    </template>
                </v-alert>
            </div>
        </its-grid-box>
    </v-col>
    <!-- MENÜ DELETE_DATES -->
    <v-col cols="12" md="4" xl="3" v-if="action == 'delete_dates'">
        <!-- LÖSCHEN VON ANMELDUNGEN -->
        <v-card tile flat color="warning">
            <v-card-text>
                <its-grid-box color="primary" title="LÖSCHEN" class="h-100 w-100">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doDeleteDates(selected_register_dates)" class="mb-4">
                        <!-- Buchungen vorhanden -->
                        <div v-if="countRegistrations(selected_register_dates) > 0">
                            <div class="text-h6">
                                <div class="text-h6" v-if="selected_register_dates.length > 1">
                                    Die Termine können nicht gelöscht werden, weil sie
                                    <span v-if="countRegistrations(selected_register_dates) == 1">eine Buchung.</span>
                                    <span v-if="countRegistrations(selected_register_dates) > 1">{{ countRegistrations(selected_register_dates) }} Buchungen</span>
                                    beinhalten.
                                </div>

                                <div class="text-h6" v-if="selected_register_dates.length == 1">
                                    Der Termin kann nicht gelöscht werden, weil er
                                    <span v-if="countRegistrations(selected_register_dates) == 1">eine Buchung</span>
                                    <span v-if="countRegistrations(selected_register_dates) > 1">{{ countRegistrations(selected_register_dates) }} Buchungen</span>
                                    beinhaltet.
                                </div>
                            </div>
                        </div>
                        <!-- Buchungen nicht vorhanden -->
                        <div v-if="countRegistrations(selected_register_dates) == 0">
                            <div class="text-h6" v-if="selected_register_dates.length > 1">Sollen die markierten Termine wirklich gelöscht werden?</div>
                            <div class="text-h6" v-if="selected_register_dates.length == 1">Soll der markierte Termin wirklich gelöscht werden?</div>
                        </div>

                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="success" slim flat @click="abortDelete">Abbruch</v-btn>
                            <v-btn color="error" slim flat type="submit" v-if="countRegistrations(selected_register_dates) == 0">Löschen</v-btn>
                        </div>
                    </v-form>
                </its-grid-box>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useRegisterPrintStore } from '@/stores/admin/RegisterPrintStore'
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
        this.registerPrintStore = useRegisterPrintStore()
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
            registerPrintStore: null,
            is_valid: false,
            search_string: '',
            is_valid: false,
            subaction: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterStore, []),
        ...mapWritableState(useRegisterDateStore, ['days', 'selected_day', 'register_dates', 'data', 'selected_register_dates']),
        ...mapWritableState(useRegisterDateBookingStore, ['person']),
    },
    watch: {
        async selected_day() {
            if (this.selected_day == null) return
            this.selected_register_dates = []
            this.search_string = ''
            if (this.selected_day) await this.loadRegisterDates(this.selected_day.date)
        },
    },

    methods: {
        async printExcel(register_id) {
            await this.registerPrintStore.printExcel(register_id)
            this.subaction = 'excel'
        },

        async printSupervisor(register_id) {
            await this.registerPrintStore.printSupervisor(register_id)
            this.subaction = 'supervisor'
        },

        async printDate(register_id) {
            await this.registerPrintStore.printDate(register_id)
            this.subaction = 'date'
        },

        countRegistrations(register_dates) {
            return this.register_dates.filter((date) => register_dates.includes(date.id)).reduce((sum, date) => sum + date.count_bookings, 0)
        },
        abortDelete() {
            this.action = ''
        },
        async lockDates(register_dates) {
            if (!(await this.registerDateStore.lockRegisterDates(register_dates))) return

            this.register_dates = this.register_dates.map((date) => {
                if (register_dates.includes(date.id)) {
                    return { ...date, is_locked: true }
                }
                return date
            })
        },
        async unlockDates(register_dates) {
            if (!(await this.registerDateStore.unlockRegisterDates(register_dates))) return

            this.register_dates = this.register_dates.map((date) => {
                if (register_dates.includes(date.id)) {
                    return { ...date, is_locked: false }
                }
                return date
            })
        },
        async refresh() {
            this.search_string = ''
            if (this.days.length == 0) return
            if (!this.selected_day) this.selected_day = this.days[0]
            await this.loadRegisterDates(this.selected_day.date)
            this.selected_register_dates = []
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

        showBookings() {
            this.action = 'show_bookings'
        },

        deleteDates() {
            this.action = 'delete_dates'
        },

        async doDeleteDates(register_dates) {
            if (!(await this.registerDateStore.deleteRegisterDates(register_dates))) return
            await this.registerDateStore.loadDays()
            this.selected_day = this.days.find((item) => item.id === this.selected_day.id)
            this.refresh()
            this.action = ''
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
                date_from: this.selected_day ? this.selected_day.date : new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10),
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
