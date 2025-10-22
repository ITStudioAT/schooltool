<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2" :disabled="action != ''">
            <its-menu-button subtitle="Zurück" icon="mdi-arrow-left" to="/admin/register_system" color="secondary" />
        </v-card>

        <!-- Überblick über das Anmeldesystem -->
        <v-row class="w-100" dense>
            <v-col cols="12">
                <its-grid-box
                    color="primary"
                    :title="selected_register?.name + ' ' + selected_schoolyear?.name"
                    class="h-100 w-100">
                    <v-card tile flat color="transparent">
                        <v-card-text class="d-flex flex-row align-center text-body-1">
                            <v-icon icon="mdi-circle" :color="selected_register.is_active ? 'success' : 'primary'" />
                            <div class="ml-2">
                                Anmeldesystem {{ selected_register.is_active ? 'geöffnet' : 'geschlossen' }}
                            </div>
                            <v-btn flat tile @click="toggleRegister(selected_register)" icon color="transparent">
                                <v-icon
                                    icon="mdi-power-standby"
                                    :color="selected_register.is_active ? 'success' : 'error'"></v-icon>
                            </v-btn>
                        </v-card-text>
                    </v-card>
                </its-grid-box>
            </v-col>
        </v-row>

        <v-row class="w-100" dense>
            <!-- TERMINE -->

            <v-col cols="12" md="4" xl="3">
                <its-grid-box color="primary" title="Termine" class="w-100">
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
                        <v-list-item
                            v-for="register_date in register_dates"
                            :key="register_date.id"
                            :value="register_date.id">
                            <template v-slot:title>
                                <div class="d-flex flex-row align-center justify-space-between">
                                    <div class="text-body-1">
                                        {{ register_date.from + ' - ' + register_date.to }}
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
                                title="Anmeldung"
                                subtitle="hinzufügen"
                                icon="mdi-account-plus"
                                color="primary"
                                @click="addDates" />
                        </v-card>

                        <v-card
                            tile
                            flat
                            color="transparent"
                            class="d-flex flex-row flex-wrap align-center ga-2"
                            v-if="selected_register_dates.length >= 1">
                            <its-menu-button
                                title="Termin/e"
                                subtitle="sperren"
                                icon="mdi-lock"
                                color="warning-lighten-2"
                                @click="addDates" />

                            <its-menu-button
                                title="Termin/e"
                                subtitle="entsperren"
                                icon="mdi-lock-open"
                                color="success-lighten-2"
                                @click="addDates" />

                            <its-menu-button
                                title="Termin/e"
                                subtitle="löschen"
                                icon="mdi-lock"
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

            <!-- NEUE TERMINE ANLEGEN -->
            <v-col cols="12" md="4" xl="3">
                <its-grid-box color="primary" title="Neue Termine" class="w-100" v-if="action == 'add_dates'">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="createDates(data)" class="mb-4">
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    autofocus
                                    v-model="data.date_from"
                                    hide-details
                                    label="Datum von (JJJJ-MM-TT)"
                                    :rules="[date()]" />
                            </v-col>
                        </v-row>
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.date_until"
                                    hide-details
                                    label="Datum bis (JJJJ-MM-TT), kann leer bleiben"
                                    :rules="[dateOrNull()]" />
                            </v-col>
                        </v-row>

                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.time_from"
                                    label="Uhrzeit von (hh:mm)"
                                    :rules="[required(), time()]" />
                            </v-col>
                        </v-row>

                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.time_until"
                                    label="Uhrzeit bis (hh:mm)"
                                    :rules="[required(), time()]" />
                            </v-col>
                        </v-row>

                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.min_per_date"
                                    label="Minuten pro Termin"
                                    :rules="[required(), min(1)]" />
                            </v-col>
                        </v-row>

                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.pause"
                                    label="Pause in Minuten"
                                    :rules="[required(), min(0)]" />
                            </v-col>
                        </v-row>

                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.max_registrations"
                                    label="Max. Registrierungen pro Termin (0=unbegrenzt)"
                                    :rules="[required(), min(0)]" />
                            </v-col>
                        </v-row>

                        <v-card tile flat color="primary" class="mt-2">
                            <v-card-text>Tage, an denen Termine festgelegt werden sollen.</v-card-text>
                        </v-card>
                        <v-row>
                            <v-col cols="12" class="d-flex flex-row flex-wrap align-center ga-2">
                                <v-checkbox hide-details v-model="data.monday" label="Mo" />
                                <v-checkbox hide-details v-model="data.tuesday" label="Di" />
                                <v-checkbox hide-details v-model="data.wednesday" label="Mi" />
                                <v-checkbox hide-details v-model="data.thursday" label="Do" />
                                <v-checkbox hide-details v-model="data.friday" label="Fr" />
                                <v-checkbox hide-details v-model="data.saturday" label="Sa" />
                                <v-checkbox hide-details v-model="data.sunday" label="So" />
                            </v-col>
                        </v-row>

                        <v-card tile flat color="primary" class="mt-2">
                            <v-card-text>Es werden für jeden Berater die gleichen Termine festgelegt.</v-card-text>
                        </v-card>

                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="data.supervisor_1"
                                    label="Berater 1"
                                    :rules="[required(), maxLength(255)]" />
                            </v-col>
                        </v-row>
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field v-model="data.supervisor_2" label="Berater 2" :rules="[maxLength(255)]" />
                            </v-col>
                        </v-row>
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field v-model="data.supervisor_3" label="Berater 3" :rules="[maxLength(255)]" />
                            </v-col>
                        </v-row>
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field v-model="data.supervisor_4" label="Berater 4" :rules="[maxLength(255)]" />
                            </v-col>
                        </v-row>
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field v-model="data.supervisor_5" label="Berater 5" :rules="[maxLength(255)]" />
                            </v-col>
                        </v-row>

                        <v-row>
                            <v-col cols="12" class="d-flex flex-row align-center justify-space-between">
                                <v-btn color="warning" slim flat @click="abort">Abbruch</v-btn>
                                <v-btn color="success" slim flat type="submit">Speichern</v-btn>
                            </v-col>
                        </v-row>
                    </v-form>
                </its-grid-box>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useRegisterDateStore } from '@/stores/admin/RegisterDateStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Schoolyears from '@/pages/admin/components/schoolyears/Schoolyears.vue'
import Registers from '@/pages/admin/registerSystem/components/Registers.vue'
import ActiveRegisters from '@/pages/admin/registerSystem/components/ActiveRegisters.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox, Schoolyears, Registers, ActiveRegisters },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
        this.registerDateStore = useRegisterDateStore()
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
            data: {},
            selected_register_dates: [],
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
        ...mapWritableState(useRegisterDateStore, ['days', 'selected_day', 'register_dates']),
    },
    watch: {
        async selected_day() {
            this.selected_register_dates = []
            if (this.selected_day) await this.loadRegisterDates(this.selected_day.date)
        },
    },

    methods: {
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
            await this.registerDateStore.loadDays()
        },

        async loadRegisterDates(date) {
            await this.registerDateStore.loadRegisterDates(date)
        },
        abort() {
            this.action = ''
        },
        async createDates(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            var answer = false
            answer = await this.registerDateStore.createDates(data)
            if (answer) this.action = ''
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

        async toggleRegister(register) {
            await this.registerStore.toggleRegister(register)
            await this.registerStore.loadActiveRegisters()
            this.selected_register.is_active = !this.selected_register.is_active
        },
    },
}
</script>
