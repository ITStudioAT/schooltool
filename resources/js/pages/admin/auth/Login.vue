<template>
    <v-container fluid class="h-100 w-100 d-flex flex-column align-center justify-center" v-if="config">
        <v-card class="mx-auto w-100" max-width="600">
            <v-img height="80px" :src="'/storage/images/' + config.logo" @click="homepage" class="hover"></v-img>
            <v-card-subtitle class="text-caption text-text">
                {{ config.version }}
            </v-card-subtitle>
            <v-card-title class="mb-4 bg-secondary">Login</v-card-title>

            <!-- Login STEP LOGIN_ENTER_EMAIL = E-Mail -->
            <v-card-text v-if="step == 'LOGIN_ENTER_EMAIL'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="loginStepEmail()" class="mb-4">
                    <div class="text-caption text-text">Bitte die E-Mail-Adresse eingeben</div>
                    <v-text-field autofocus v-model="data.email" label="Email" :rules="[required(), mail()]" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" type="submit" @click="loginStepEmail()">Weiter</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>

                <v-btn block color="primary" slim flat rounded="0" variant="text" @click="passwordUnknown">Kennwort unbekannt</v-btn>
                <div v-if="config.register_admin_allowed">
                    <div class="text-caption text-center font-weight-light">oder</div>
                    <v-btn block color="success" slim flat rounded="0" variant="text" @click="register">Neu registrieren</v-btn>
                </div>
            </v-card-text>

            <!-- Login STEP LOGIN_SELECT_SCHOOL -->
            <v-card-text v-if="step == 'LOGIN_SELECT_SCHOOL'">
                <div class="text-h6">Bitte die Schule auswählen</div>
                <v-autocomplete v-model="selected_school_id" :items="data.schools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                <v-btn block color="success" slim flat rounded="0" @click="loginStepSchool()" v-if="school">Weiter</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartLogin">Zurück</v-btn>
            </v-card-text>

            <!-- Login STEP LOGIN_ENTER_PASSWORD = Password -->
            <v-card-text v-if="step == 'LOGIN_ENTER_PASSWORD'">
                <v-card-subtitle class="mb-4">
                    {{ data?.school?.long_name }}
                </v-card-subtitle>
                <v-form ref="form" v-model="is_valid" @submit.prevent="loginStep2()" class="mb-4">
                    <div class="text-caption text-text">Bitte das Kennwort eingeben</div>
                    <v-text-field
                        autofocus
                        label="Kennwort"
                        :append-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
                        :type="is_password_visible ? 'text' : 'password'"
                        @click:append="() => (is_password_visible = !is_password_visible)"
                        :rules="[required(), minLength(8), maxLength(255)]"
                        v-model="data.password" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" @click="loginStep2()">Anmelden</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartLogin">Zurück</v-btn>
            </v-card-text>

            <!-- Login STEP LOGIN_ENTER_TOKEN = Token_2fa -->
            <v-card-text v-if="step == 'LOGIN_ENTER_TOKEN'">
                <v-card-subtitle class="mb-4">
                    {{ data?.school?.long_name }}
                </v-card-subtitle>
                <v-form ref="form" v-model="is_valid" @submit.prevent="loginStep3()" class="mb-4">
                    <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails" />
                    <div class="text-caption text-text">Bitte den Code laut E-Mail eingeben</div>
                    <v-otp-input autofocus v-model="data.token_2fa" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" @click="loginStep3()">Anmelden</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartLogin">Zurück</v-btn>
            </v-card-text>
        </v-card>
    </v-container>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: {},

    async beforeMount() {
        await axios.get('/sanctum/csrf-cookie')
        this.adminStore = useAdminStore()
        if (!this.config?.is_auth) await this.adminStore.executeLogout()
        // await this.adminStore.loadConfig()
        this.restartLogin()
    },

    data() {
        return {
            adminStore: null,
            is_valid: false,
            step: null,
            is_password_visible: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'error', 'api_response', 'load_config', 'school', 'selected_school_id', 'data']),
    },

    watch: {
        selected_school_id() {
            if (this.selected_school_id) {
                this.school = this.data.schools.find((s) => s.id === this.selected_school_id)
                this.data.school = this.school
            } else {
                this.school = null
                this.data.school = null
            }
        },
    },

    methods: {
        homepage() {
            window.location.href = '/'
        },

        passwordUnknown() {
            this.$router.push('/admin/unknown_password')
        },

        register() {
            this.$router.push('/admin/register')
        },

        restartLogin() {
            this.data.password = null
            this.data.token_2fa = null
            this.step = 'LOGIN_ENTER_EMAIL'
        },

        loginStepSchool() {
            if (!this.school) return
            this.step = 'LOGIN_ENTER_PASSWORD'
        },

        async loginStepEmail() {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.data.step = 'LOGIN_ENTER_EMAIL'
            if (!(await this.adminStore.loginStepEmail(this.data))) return

            if (!this.data.school) {
                this.selected_school_id = null
                this.step = 'LOGIN_SELECT_SCHOOL'
            } else {
                this.step = 'LOGIN_ENTER_PASSWORD'
            }
        },

        async loginStep2() {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.data.step = 'LOGIN_ENTER_PASSWORD'

            if (!(await this.adminStore.loginStep2(this.data))) return

            if (this.data.step == 'LOGIN_SUCCESS') {
                await this.adminStore.loadConfig()
                this.$router.push('/admin/')
            } else {
                this.step = 'LOGIN_ENTER_TOKEN'
            }
        },

        async loginStep3() {
            if (this.data.token_2fa.length != 6) return
            this.data.step = 'LOGIN_ENTER_TOKEN'
            if (!(await this.adminStore.loginStep3(this.data))) return

            if (this.data.step == 'LOGIN_SUCCESS') {
                await this.adminStore.loadConfig()
                this.$router.push('/admin/')
            }
        },
    },
}
</script>
