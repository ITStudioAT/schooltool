<template>
    <v-container fluid class="h-100 w-100 d-flex flex-column align-center justify-center" v-if="config">
        <v-card class="mx-auto w-100" max-width="600">
            <v-img height="80px" :src="'/storage/images/' + config.logo" @click="homepage" class="hover"></v-img>
            <v-card-subtitle class="text-caption text-text">
                {{ config.version }}
            </v-card-subtitle>
            <v-card-title class="mb-4 bg-secondary">Kennwort unbekannt</v-card-title>

            <!-- Kennwort vergessen STEP PASSWORD_UNKNOWN_ENTER_EMAIL = E-Mail -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_ENTER_EMAIL'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="passwordUnknownStepEmail()" class="mb-4">
                    <div class="text-caption text-text">Bitte die E-Mail-Adresse eingeben</div>
                    <v-text-field autofocus v-model="data.email" label="Email" :rules="[required(), mail()]" />

                    <v-btn block color="success" slim flat rounded="0" type="submit">Weiter</v-btn>
                </v-form>

                <!-- Zurück zum Login -->
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="primary" slim flat rounded="0" variant="text" @click="login">Login</v-btn>

                <!-- Zu Neuregistrierung -->
                <div v-if="config.register_admin_allowed">
                    <div class="text-caption text-center font-weight-light">oder</div>
                    <v-btn block color="success" slim flat rounded="0" variant="text" @click="register">Neu registrieren</v-btn>
                </div>
            </v-card-text>

            <!-- Kennwort vergessen STEP PASSWORD_UNKNOWN_SELECT_SCHOOL -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_SELECT_SCHOOL'">
                <v-card-subtitle class="mb-4">
                    <div>{{ data?.email }}</div>
                </v-card-subtitle>
                <div class="text-h6">Bitte die Schule auswählen</div>
                <v-autocomplete v-model="selected_school_id" :items="schools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                <v-btn block color="success" slim flat rounded="0" @click="passwordUnknownStepSchool()" v-if="selected_school_id">Weiter</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartPasswordUnknown">Zurück</v-btn>
            </v-card-text>

            <!-- Kennwort vergessen PASSWORD_UNKNOWN_ENTER_TOKEN  -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_ENTER_TOKEN'">
                <v-card-subtitle class="mb-4">
                    <div>{{ data?.email }}</div>
                    <div>{{ data?.school?.long_name }}</div>
                </v-card-subtitle>
                <v-form ref="form" v-model="is_valid" @submit.prevent="passwordUnknownStepToken()" class="mb-4">
                    <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails" />
                    <div class="text-caption text-text">Bitte den Code laut E-Mail eingeben</div>
                    <v-otp-input autofocus v-model="data.token_2fa" />
                    <v-btn block color="success" slim flat rounded="0" type="submit">Weiter</v-btn>
                </v-form>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartPasswordUnknown">Zurück</v-btn>
            </v-card-text>

            <!-- Kennwort vergessen PASSWORD_UNKNOWN_ENTER_TOKEN_2 bei 2-Faktoren Authentifizierung  -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_ENTER_TOKEN_2'">
                <v-card-subtitle class="mb-4">
                    <div>{{ data?.email }}</div>
                    <div>{{ data?.school?.long_name }}</div>
                </v-card-subtitle>
                <v-form ref="form" v-model="is_valid" @submit.prevent="passwordUnknownStepToken2()" class="mb-4">
                    <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails von der 2-Faktoren-EMail-Adresse" />
                    <div class="text-caption text-text">Bitte den Code laut E-Mail eingeben</div>
                    <v-otp-input autofocus v-model="data.token_2fa_2" />
                    <v-btn block color="success" slim flat rounded="0" type="submit">Weiter</v-btn>
                </v-form>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartPasswordUnknown">Zurück</v-btn>
            </v-card-text>

            <v-card-text v-if="step === 'LOGIN_ENTER_TWO_FACTOR'">
                <div class="text-h6 mb-4">Zwei-Faktor-Authentifizierung</div>
                <v-btn-toggle v-model="twoFactorMode" mandatory color="primary" variant="outlined" divided class="mb-4 w-100">
                    <v-btn value="code" class="flex-1-1">Authenticator-Code</v-btn>
                    <v-btn value="recovery" class="flex-1-1">Wiederherstellungscode</v-btn>
                </v-btn-toggle>
                <v-form @submit.prevent="submitTwoFactorChallenge" class="mb-4">
                    <template v-if="twoFactorMode === 'code'">
                        <div class="text-caption text-text">Geben Sie den sechsstelligen Code aus Ihrer Authenticator-App ein.</div>
                        <v-otp-input autofocus v-model="twoFactorCode" length="6" type="number" />
                    </template>
                    <v-text-field
                        v-else
                        autofocus
                        v-model="recoveryCode"
                        label="Wiederherstellungscode"
                        variant="outlined"
                        autocomplete="one-time-code" />
                    <v-btn block color="success" slim flat rounded="0" type="submit">Anmeldung abschließen</v-btn>
                </v-form>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartPasswordUnknown">Zurück</v-btn>
            </v-card-text>

            <v-card-text v-if="step === 'LOGIN_PROCESSING'">
                <div class="text-body-1">Anmeldung wird durchgeführt …</div>
            </v-card-text>
        </v-card>
    </v-container>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: {},

    async beforeMount() {
        if (typeof window.ensureCsrfCookie === 'function') {
            await window.ensureCsrfCookie()
        } else {
            await axios.get('/sanctum/csrf-cookie')
        }
        this.adminStore = useAdminStore()
        await this.adminStore.executeLogout()
        await this.adminStore.loadConfig()
        this.restartPasswordUnknown()
    },

    data() {
        return {
            adminStore: null,
            is_valid: false,
            step: null,
            twoFactorMode: 'code',
            twoFactorCode: '',
            recoveryCode: '',
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'error', 'api_response', 'load_config', 'school', 'selected_school_id', 'data', 'schools']),
    },

    watch: {},

    methods: {
        homepage() {
            window.location.href = '/'
        },

        login() {
            this.$router.push('/admin/login')
        },

        register() {
            this.$router.push('/admin/register')
        },

        restartPasswordUnknown() {
            this.data.password = null
            this.data.token_2fa = null
            this.data.token_2fa_2 = null
            this.twoFactorCode = ''
            this.recoveryCode = ''
            this.twoFactorMode = 'code'
            this.selected_school_id = null
            this.step = 'PASSWORD_UNKNOWN_ENTER_EMAIL'
        },

        async passwordUnknownStepEmail() {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return

            this.data['step'] = 'PASSWORD_UNKNOWN_ENTER_EMAIL'
            if (!(await this.adminStore.passwordUnknownStepEmail(this.data))) return
            this.step = this.data['step']
        },

        async passwordUnknownStepSchool() {
            if (!this.selected_school_id) return
            delete this.data.schools
            this.data['school_id'] = this.selected_school_id
            if (!(await this.adminStore.passwordUnknownStepSchool(this.data))) return
            this.step = this.data['step']
        },

        async passwordUnknownStepToken() {
            if (!this.data.token_2fa || this.data.token_2fa.length !== 6) return
            if (!(await this.adminStore.passwordUnknownStepToken(this.data))) return
            await this.handleLoginStep()
        },

        async passwordUnknownStepToken2() {
            if (!this.data.token_2fa_2 || this.data.token_2fa_2.length !== 6) return
            if (!(await this.adminStore.passwordUnknownStepToken2(this.data))) return
            await this.handleLoginStep()
        },

        async handleLoginStep() {
            this.step = this.data.step
            if (this.step !== 'LOGIN_SUCCESS') return

            this.step = 'LOGIN_PROCESSING'
            window.location.replace('/admin')
        },

        async submitTwoFactorChallenge() {
            const payload = this.twoFactorMode === 'recovery'
                ? { recovery_code: String(this.recoveryCode || '').trim() }
                : { code: String(this.twoFactorCode || '').replace(/\s+/g, '') }

            if (this.twoFactorMode === 'code' && !/^\d{6}$/.test(payload.code)) return
            if (this.twoFactorMode === 'recovery' && !payload.recovery_code) return

            this.step = 'LOGIN_PROCESSING'
            const response = await this.adminStore.loginTwoFactorChallenge(payload)
            this.twoFactorCode = ''
            this.recoveryCode = ''

            if (!response) {
                this.step = 'LOGIN_ENTER_TWO_FACTOR'
                return
            }

            window.location.replace('/admin')
        },
    },
}
</script>
