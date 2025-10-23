<template>
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Person anmelden" class="w-100" v-if="step == 0">
            <v-form ref="form" v-model="is_valid" @submit.prevent="getUserWithEmail(person)" class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-card tile flat color="primary" class="mt-2">
                            <v-card-text>
                                <div>{{ '📅 ' + selectedRegisterDate.date }}</div>
                                <div>{{ '🕒 ' + selectedRegisterDate.from + ' - ' + selectedRegisterDate.to }}</div>
                                <div>{{ '👷 ' + selectedRegisterDate.supervisor }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
                <v-row dense>
                    <v-col cols="12">
                        <v-text-field autofocus v-model="person.email" label="E-Mail" :rules="[mail()]" />
                    </v-col>
                </v-row>
                <v-row>
                    <v-col cols="12" class="d-flex flex-row align-center justify-space-between">
                        <v-btn color="warning" slim flat @click="abort">Abbruch</v-btn>
                        <v-btn color="success" slim flat type="submit">Weiter</v-btn>
                    </v-col>
                </v-row>
            </v-form>
        </its-grid-box>

        <its-grid-box
            color="primary"
            :title="user ? 'Anmelder prüfen' : 'Neuer Anmelder'"
            class="w-100"
            v-if="step == 1">
            <v-form ref="form" v-model="is_valid" @submit.prevent="updateOrCreateUser(person)" class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-card tile flat color="primary" class="mt-2">
                            <v-card-text>
                                <div>{{ '📅 ' + selectedRegisterDate.date }}</div>
                                <div>{{ '🕒 ' + selectedRegisterDate.from + ' - ' + selectedRegisterDate.to }}</div>
                                <div>{{ '👷 ' + selectedRegisterDate.supervisor }}</div>
                            </v-card-text>
                            <v-card-text class="text-h6">
                                {{ person.email }}
                            </v-card-text>
                            <v-card-text v-if="!user">
                                Unter der angegeben E-Mail ist kein Benuter gespeichert. Der Benutzer muss neu
                                registriert werden.
                            </v-card-text>
                            <v-card-text v-if="user">
                                Die E-Mail existiert. Die Daten können geändert/ergänzt werden.
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            autofocus
                            v-model="person.last_name"
                            label="Nachname"
                            :rules="[required(), maxLength(255)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="person.first_name" label="Vorname" :rules="[maxLength(255)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field v-model="person.phone" label="Telefon" :rules="[maxLength(255)]" />
                    </v-col>
                </v-row>
                <v-row>
                    <v-col cols="12" class="d-flex flex-row align-center justify-space-between">
                        <v-btn color="warning" slim flat @click="step--">Zurück</v-btn>
                        <v-btn color="error" slim flat @click="abort">Abbruch</v-btn>
                        <v-btn color="success" slim flat type="submit">Weiter</v-btn>
                    </v-col>
                </v-row>
            </v-form>
        </its-grid-box>

        <its-grid-box color="primary" title="Person erfassen" class="w-100" v-if="step == 2">
            <v-form
                ref="form"
                v-model="is_valid"
                @submit.prevent="createBooking(selectedRegisterDate, person)"
                class="mb-4">
                <v-row dense>
                    <v-col cols="12">
                        <v-card tile flat color="primary" class="mt-2">
                            <v-card-text>
                                <div>{{ '📅 ' + selectedRegisterDate.date }}</div>
                                <div>{{ '🕒 ' + selectedRegisterDate.from + ' - ' + selectedRegisterDate.to }}</div>
                                <div>{{ '👷 ' + selectedRegisterDate.supervisor }}</div>
                            </v-card-text>
                            <v-card-text class="text-body-1">
                                <div>{{ person.last_name + ' ' + person.first_name }}</div>
                                <div>{{ '✉️ ' + person.email }}</div>
                                <div v-if="person.phone">{{ '☎️ ' + person.phone }}</div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            autofocus
                            v-model="person.student_last_name"
                            label="Nachname des Kindes"
                            :rules="[required(), maxLength(255)]" />
                    </v-col>
                    <v-col cols="12">
                        <v-text-field
                            v-model="person.student_first_name"
                            label="Vorname des Kindes"
                            :rules="[maxLength(255)]" />
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            v-model="person.student_birthdate"
                            label="Geburtsdatum (JJJJ-MM-TT)"
                            :rules="[dateOrNull()]" />
                    </v-col>
                </v-row>
                <v-row>
                    <v-col cols="12" class="d-flex flex-row align-center justify-space-between">
                        <v-btn color="warning" slim flat @click="step--">Zurück</v-btn>
                        <v-btn color="error" slim flat @click="abort">Abbruch</v-btn>
                        <v-btn color="success" slim flat type="submit">Speichern</v-btn>
                    </v-col>
                </v-row>
            </v-form>
        </its-grid-box>
    </v-col>
    <v-col cols="12">{{ person }}</v-col>
    <v-col cols="12">{{ user }}</v-col>
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
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
            registerDateStore: null,
            registerDateBookingStore: null,
            is_valid: false,
            step: 0,
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
        ...mapWritableState(useRegisterDateStore, ['selected_day', 'selected_register_dates', 'register_dates']),
        ...mapWritableState(useRegisterDateBookingStore, ['person', 'user']),

        selectedRegisterDate() {
            const selectedId = this.selected_register_dates[0]
            const found = this.register_dates.find((item) => item.id === selectedId)
            return found
        },
    },
    watch: {},

    methods: {
        abort() {
            this.action = ''
        },

        async getUserWithEmail(person) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.user = null
            var answer = false
            answer = await this.registerDateBookingStore.getUserWithEmail(person)

            if (!answer) return

            this.person.last_name = this.user ? this.user.last_name : null
            this.person.first_name = this.user ? this.user.first_name : null
            this.person.phone = this.user ? this.user.phone : null

            this.step = 1
        },

        async updateOrCreateUser(person) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.user = null
            var answer = false
            answer = await this.registerDateBookingStore.updateOrCreateUser(person)

            if (!answer) return

            this.person.last_name = this.user ? this.user.last_name : null
            this.person.first_name = this.user ? this.user.first_name : null
            this.person.phone = this.user ? this.user.phone : null

            this.person.student_last_name = this.person.last_name

            this.step = 2
        },

        async createBooking(register_date, person) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.user = null
            var answer = false
            person.register_date_id = register_date.id

            answer = await this.registerDateBookingStore.createBooking(person)
            if (!answer) return

            const index = this.register_dates.findIndex((d) => d.id === register_date.id)
            this.register_dates[index].count_bookings++
            this.action = ''
        },
    },
}
</script>
