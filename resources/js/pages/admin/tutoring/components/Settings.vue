<template>
    <v-col cols="12" md="6" xl="4" v-if="data">
        <its-grid-box color="primary" title="Einstellungen" icon="mdi-cog" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <!-- ANZEIGE EINSTELLUNGEN -->
                    <v-card-text class="text-body-1 d-flex flex-column ga-2" v-if="action == ''">
                        <div class="d-flex flex-row align-center justify-space-between w-100">
                            <div>Neuer Benutzer muss bestätigt werden?</div>
                            <div v-if="data.tutoring_student_must_be_confirmed">✅ ja</div>
                            <div v-if="!data.tutoring_student_must_be_confirmed">❌ nein</div>
                        </div>

                        <div class="d-flex flex-row align-center justify-space-between w-100" v-if="data.tutoring_student_must_be_confirmed">
                            <div>E-Mail dessen, der bestätigt:</div>
                            <div>{{ data.tutoring_confirmer_email }}</div>
                        </div>

                        <hr />
                        <div class="d-flex flex-row align-center justify-space-between w-100">
                            <div>Anzahl gleichzeitiger Angebote pro Schüler:in (0=unbegrenzt):</div>
                            <div>{{ data.tutoring_max_offers_per_student }}</div>
                        </div>

                        <div class="mt-4">
                            <its-menu-button title="Einstellungen" subtitle="ändern" icon="mdi-cog" color="primary" @click="editSettings" />
                        </div>
                    </v-card-text>

                    <!-- ÄNDERN EINSTELLUNGEN -->
                    <v-card-text v-if="action == 'edit_settings'">
                        <v-form ref="form" v-model="is_valid" @submit.prevent="saveTutoringSettings(data_new)">
                            <v-switch label="Neuer Benutzer muss bestätigt werden?" v-model="data_new.tutoring_student_must_be_confirmed" color="success" tabindex="1" />

                            <v-text-field
                                v-model="data_new.tutoring_confirmer_email"
                                label="E-Mail dessen, der bestätigt"
                                :rules="[required(), mail()]"
                                v-if="data_new.tutoring_student_must_be_confirmed"
                                tabindex="1" />

                            <v-text-field
                                v-model="data_new.tutoring_max_offers_per_student"
                                label="Anzahl gleichzeitiger Angebote pro Schüler:in (0=unbegrenzt)"
                                :rules="[required(), min(0)]"
                                tabindex="2" />

                            <div class="d-flex flex-row align-center justify-space-between">
                                <v-btn color="warning" flat tile @click="abortSettings">Abbruch</v-btn>
                                <v-btn color="success" flat tile type="submit" tabindex="2">Speichern</v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolToolStore } from '@/stores/admin/SchoolToolStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolToolStore = useSchoolToolStore()
        await this.schoolToolStore.loadConfig()
        this.action = ''
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolToolStore: null,
            is_valid: false,
            data_new: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useSchoolToolStore, ['data']),
    },

    methods: {
        async saveTutoringSettings(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!this.schoolToolStore.saveTutoringSettings(data)) return
            this.action = ''
        },

        editSettings() {
            this.data_new = JSON.parse(JSON.stringify(this.data))
            this.action = 'edit_settings'
        },
        abortSettings() {
            this.action = ''
        },
    },
}
</script>
