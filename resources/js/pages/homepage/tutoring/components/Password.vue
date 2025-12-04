<template>
    <!-- PASSWORD -->
    <v-card-text>
        <v-card tile flat color="tutoring_card" max-width="600">
            <v-form ref="form" v-model="is_valid" @submit.prevent="updatePassword(data)">
                <v-card-title class="bg-tutoring_card_title mb-2">Kennwort ändern</v-card-title>
                <v-card-text>
                    <v-card tile flat color="transparent" :disabled="data.status == 'CONFIRM_PASSWORD' || data.status == 'RE_CONFIRM_PASSWORD'">
                        <v-text-field
                            flat
                            tile
                            autofocus
                            label="Kennwort"
                            :append-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
                            :type="is_password_visible ? 'text' : 'password'"
                            @click:append="() => (is_password_visible = !is_password_visible)"
                            :rules="[required(), minLength(8), maxLength(255)]"
                            v-model="data.password" />

                        <v-text-field
                            flat
                            tile
                            label="Wiederholung Kennwort"
                            :append-icon="is_password_visible_confirm ? 'mdi-eye' : 'mdi-eye-off'"
                            :type="is_password_visible_confirm ? 'text' : 'password'"
                            @click:append="() => (is_password_visible_confirm = !is_password_visible_confirm)"
                            :rules="[required(), minLength(8), maxLength(255), passwordMatch(data.password)]"
                            v-model="data.password_confirm" />
                    </v-card>
                </v-card-text>

                <v-card-text v-if="data.status == 'CONFIRM_PASSWORD'">
                    <v-alert class="mt-4" color="primary">
                        <div class="mt-2">Wir haben Dir eine E-Mail mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>
                    <v-otp-input autofocus v-model="data.token_2fa" />
                </v-card-text>
                <v-card-text v-if="data.status == 'RE_CONFIRM_PASSWORD'">
                    <v-alert class="mt-4" color="primary">
                        <div class="mt-2">Der Code war falsch oder abgelaufen.</div>
                        <div class="mt-2">Wir haben Dir eine erneut E-Mail mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>
                    <v-otp-input autofocus v-model="data.token_2fa" />
                </v-card-text>

                <!-- ERROR -->
                <v-card-text v-if="error">
                    <v-alert type="error">{{ error?.response?.data?.message + ' (' + error?.response?.status + ')' }}</v-alert>
                </v-card-text>
                <!-- SCHLIESSEN/SPEICHERN-->
                <v-card-actions>
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <its-menu-button subtitle="Abbruch" icon="mdi-close" color="warning" @click="action = ''" />
                        <its-menu-button subtitle="Speichern" icon="mdi-content-save" color="success" @click="updatePassword(data)" />
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
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        await this.tutoringStore.loadAuth()
        this.editPassword()
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            is_valid: false,
            is_password_visible: false,
            is_password_visible_confirm: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action']),
        ...mapWritableState(useUserStore, ['error', 'data']),
    },

    watch: {},

    methods: {
        async updatePassword(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.userStore.updatePassword(data))) return

            if (this.data.status != 'OK') return

            await this.tutoringStore.loadAuth()
            this.action = ''
        },

        editPassword() {
            this.error = null
            this.data = {
                id: this.auth.auth_user.id,
            }
        },
    },
}
</script>
