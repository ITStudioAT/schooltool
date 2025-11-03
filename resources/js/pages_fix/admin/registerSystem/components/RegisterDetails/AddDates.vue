<template>
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Neue Termine" class="w-100">
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
                        <v-text-field v-model="data.pause" label="Pause in Minuten" :rules="[required(), min(0)]" />
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
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useRegisterDateStore } from '@/stores/admin/RegisterDateStore'
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
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
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
        ...mapWritableState(useRegisterDateStore, ['data']),
    },
    watch: {},

    methods: {
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
    },
}
</script>
