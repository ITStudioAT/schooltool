<template>
    <!-- PROFIL -->
    <v-card-text v-if="action == 'profile'">
        <v-card tile flat color="tutoring_card" max-width="600">
            <v-form ref="form" v-model="is_valid" @submit.prevent="updateProfile(data)">
                <v-card-title class="bg-tutoring_card_title mb-2">Profil ändern</v-card-title>
                <!-- Nachanme, Vorname, E-Mail eingeben-->
                <v-card-text>
                    <v-card tile flat color="transparent" :disabled="data.status == 'CONFIRM_EMAIL' || data.status == 'RE_CONFIRM_EMAIL'">
                        <v-text-field autofocus flat rounded="0" v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                        <v-text-field flat rounded="0" v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                        <v-radio-group v-model="data.sex" :rules="[required()]">
                            <v-radio label="Männlich" value="m" color="blue"></v-radio>
                            <v-radio label="Weiblich" value="f" color="pink"></v-radio>
                            <v-radio label="Divers" value="d" color="yellow"></v-radio>
                        </v-radio-group>
                        <v-text-field flat rounded="0" v-model="data.email" label="E-Mail" :rules="[required(), mail(), maxLength(255)]" />
                    </v-card>
                </v-card-text>
                <!-- Neue E-Mail: Bestätigungscode eingeben -->
                <v-card-text v-if="data.status == 'CONFIRM_EMAIL'">
                    <v-alert class="mt-4" color="primary">
                        <div class="mt-2">Wir haben Dir eine E-Mail an deine neue E-Mail-Adresse mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>
                    <v-otp-input autofocus v-model="data.token_2fa" />
                </v-card-text>
                <!-- Neue E-Mail: Bestätigungscode war falsch, erneut Bestätigungscode eingeben -->
                <v-card-text v-if="data.status == 'RE_CONFIRM_EMAIL'">
                    <v-alert class="mt-4" color="primary">
                        <div class="mt-2">Der Code war falsch oder abgelaufen.</div>
                        <div class="mt-2">Wir haben Dir erneut eine E-Mail an deine neue E-Mail-Adresse mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>
                    <v-otp-input autofocus v-model="data.token_2fa" />
                </v-card-text>
                <!-- ERROR-->
                <v-card-text v-if="error">
                    <v-alert type="error">{{ error?.response?.data?.message + ' (' + error?.response?.status + ')' }}</v-alert>
                </v-card-text>
                <!-- SCHLIESSEN/SPEICHERN-->
                <v-card-actions>
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <its-menu-button subtitle="Abbruch" icon="mdi-close" color="warning" @click="action = ''" />
                        <its-menu-button subtitle="Speichern" icon="mdi-content-save" color="success" @click="updateProfile(data)" />
                    </div>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-card-text>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    props: [],
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        await this.tutoringStore.loadAuth()
        this.editProfile()
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action']),
        ...mapWritableState(useUserStore, ['error', 'data']),
    },

    watch: {},

    methods: {
        async updateProfile(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.userStore.update(data))) return

            if (this.data.status != 'OK') return

            await this.tutoringStore.loadAuth()
            this.action = ''
        },
        editProfile() {
            this.error = null
            this.data = {
                id: this.auth.auth_user.id,
                last_name: this.auth.auth_user.last_name,
                first_name: this.auth.auth_user.first_name,
                email: this.auth.auth_user.email,
                sex: this.auth.auth_user.sex,
            }
        },
    },
}
</script>
