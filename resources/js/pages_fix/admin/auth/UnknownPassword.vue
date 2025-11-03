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
                <v-autocomplete v-model="selected_school_id" :items="data.schools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                <v-btn block color="success" slim flat rounded="0" @click="passwordUnknownStepSchool()" v-if="school">Weiter</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartPasswordUnknown">Zurück</v-btn>
            </v-card-text>

            <!-- Kennwort vergessen PASSWORD_UNKNOWN_ENTER_TOKEN = Token_2fa -->
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

            <!-- Login STEP LOGIN_ENTER_PASSWORD = Password -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_ENTER_PASSWORD'">
                <v-card-subtitle class="mb-4">
                    <div>{{ data?.email }}</div>
                    <div>{{ data?.school?.long_name }}</div>
                </v-card-subtitle>
                <v-form ref="form" v-model="is_valid" @submit.prevent="passwordUnknownStepPassword()" class="mb-4">
                    <div class="text-caption text-text">Bitte das neue Kennwort eingeben</div>
                    <v-text-field
                        autofocus
                        label="Kennwort"
                        :append-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
                        :type="is_password_visible ? 'text' : 'password'"
                        @click:append="() => (is_password_visible = !is_password_visible)"
                        :rules="[required(), minLength(8), maxLength(255)]"
                        v-model="data.password" />

                    <v-text-field
                        label="Wiederholung Kennwort"
                        :append-icon="is_password_visible_confirm ? 'mdi-eye' : 'mdi-eye-off'"
                        :type="is_password_visible_confirm ? 'text' : 'password'"
                        @click:append="() => (is_password_visible_confirm = !is_password_visible_confirm)"
                        :rules="[required(), minLength(8), maxLength(255), passwordMatch(data.password)]"
                        v-model="data.password_confirm" />

                    <v-btn block color="success" slim flat rounded="0" type="submit">Neu setzen</v-btn>
                </v-form>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text" @click="restartPasswordUnknown">Zurück</v-btn>
            </v-card-text>

            <!-- Kennwort vergessen PASSWORD_UNKNOWN_ENTER_TOKEN = Token_2fa -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_FINISHED'">
                <v-card-subtitle class="mb-4">
                    <div>{{ data?.email }}</div>
                    <div>{{ data?.school?.long_name }}</div>
                </v-card-subtitle>

                <div class="text-body-1">
                    <div>Das Kennwort wurde erfolreich neu gesetzt.</div>
                    <div class="mt-2">Sie können sich jetzt am System anmelden.</div>
                    <v-btn block color="success" slim flat rounded="0" class="mt-2" @click="login">Zum Login</v-btn>
                </div>
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
        await axios.get('/sanctum/csrf-cookie')
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
            is_password_visible: false,
            is_password_visible_confirm: false,
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

        login() {
            this.$router.push('/admin/login')
        },

        register() {
            this.$router.push('/admin/register')
        },

        restartPasswordUnknown() {
            this.data.password = null
            this.data.token_2fa = null
            this.step = 'PASSWORD_UNKNOWN_ENTER_EMAIL'
        },

        async passwordUnknownStepEmail() {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.data.step = 'LOGIN_ENTER_EMAIL'
            if (!(await this.adminStore.loginStepEmail(this.data))) return

            if (!this.data.school) {
                this.selected_school_id = null
                this.step = 'PASSWORD_UNKNOWN_SELECT_SCHOOL'
            } else {
                this.step = 'PASSWORD_UNKNOWN_ENTER_TOKEN'
            }
        },

        async passwordUnknownStepSchool() {
            if (!this.school) return
            this.step = 'xxx'
            delete this.data.users_count
            delete this.data.schools
            this.data.school_id = this.school.id
            this.data.step = 'PASSWORD_UNKNOWN_SELECT_SCHOOL'
            if (!(await this.adminStore.passwordUnknownStepSchool(this.data))) return
            this.step = 'PASSWORD_UNKNOWN_ENTER_TOKEN'
        },

        async passwordUnknownStepToken() {
            if (!this.data.token_2fa || this.data.token_2fa.length != 6) return
            this.data.step = 'PASSWORD_UNKNOWN_STEP_TOKEN'
            if (!(await this.adminStore.passwordUnknownStepToken(this.data))) return
            this.step = 'PASSWORD_UNKNOWN_ENTER_PASSWORD'
        },

        async passwordUnknownStepPassword() {
            if (!this.data.token_2fa || this.data.token_2fa.length != 6) return
            this.data.step = 'PASSWORD_UNKNOWN_STEP_PASSWORD'
            if (!(await this.adminStore.passwordUnknownStepPassword(this.data))) return
            this.step = 'PASSWORD_UNKNOWN_FINISHED'
        },
    },
}
</script>
