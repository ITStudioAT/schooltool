<template>
    <div>
        <!-- SCHULE AUSWÄHLEN-->
        <!-- Wenn mehr als eine Schule möglich sind -->
        <div class="mt-8 w-100 d-flex flex-column align-center justify-center" v-if="is_init && !school_name && schools.length > 1">
            <v-card tile flat color="primary" min-width="300">
                <v-card-title>Bitte die Schule auswählen</v-card-title>
                <v-card-text>
                    <v-autocomplete dense hide-details v-model="selected_school_id" :items="schools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                </v-card-text>
            </v-card>
        </div>

        <div class="mt-8 w-100 d-flex flex-column align-center justify-center" v-if="is_init && is_login">
            <!-- Email Adresse eingeben-->
            <v-card tile flat color="primary" min-width="300" v-if="!data.status" data-testid="tutoring-login-step-email">
                <v-card-title>Bitte die E-Mail eingeben</v-card-title>
                <v-card-text>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="checkEmail(data)" class="mb-4">
                        <v-text-field autofocus v-model="data.email" label="Deine E-Mail-Adresse" :rules="[required(), mail()]" tabindex="1" data-testid="tutoring-login-email" id="tutoring-login-email" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="$emit('cancel-login')">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email" tabindex="2" data-testid="tutoring-login-continue-password">Weiter</v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>

            <!-- Benutzerdaten eingeben-->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'NEW_USER'">
                <v-card-title>Bitte Benutzerdaten eingeben</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="createUser(data)" class="my-4">
                        <v-text-field autofocus v-model="data.last_name" label="Dein Nachname" :rules="[required(), maxLength(255)]" tabindex="1" />
                        <v-text-field v-model="data.first_name" label="Dein Vorname" :rules="[maxLength(255)]" tabindex="2" />
                        <v-text-field
                            v-model="data.schoolclass"
                            label="Deine Klasse"
                            :rules="[required(), maxLength(10)]"
                            tabindex="3"
                            @update:modelValue="data.schoolclass = $event?.toUpperCase()" />

                        <v-radio-group v-model="data.sex" :rules="[required()]">
                            <v-radio label="Männlich" value="m" color="blue"></v-radio>
                            <v-radio label="Weiblich" value="f" color="pink"></v-radio>
                            <v-radio label="Divers" value="d" color="yellow"></v-radio>
                        </v-radio-group>

                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="data.status = ''">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email" tabindex="2">Weiter</v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>

            <!-- Benutzer inaktiv -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'USER_INACTIVE'">
                <v-card-title>Benutzer inaktiv!</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="warning">
                        <div>Der Benutzer ist gesperrt!</div>
                        <div class="mt-2">Wenden Sie sich an Ihren Administrator.</div>
                    </v-alert>
                </v-card-text>
            </v-card>

            <!-- E-Mail bestätigen -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'CONFIRM_EMAIL'">
                <v-card-title>Bitte Code eingeben</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="primary">
                        <div class="mt-2">Wir haben Dir eine E-Mail mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="confirmEmail(data)" class="my-4">
                        <v-otp-input autofocus v-model="data.token_2fa" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="data.status = ''">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.token_2fa" tabindex="2">Weiter</v-btn>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>

            <!-- E-Mail wurde erfolgreich bestätigt  -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'EMAIL_VERIFIED'">
                <v-card-title>E-Mail erfolgreich bestätigt!</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="success">
                        <div>Die E-Mail wurde erfolgreich bestätigt!</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="enterPassword">Weiter</v-btn>
                    </div>
                </v-card-text>
            </v-card>

            <!-- EMail-Confirm war nicht erfolgreich  -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'CONFIRM_EMAIL_AGAIN'">
                <v-card-title>EMail nicht verifiziert!</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="warning">
                        <div>Die E-Mail-Verifizierung war nicht erfolgreich.</div>
                        <div class="mt-2">Wir haben Dir eine neue E-Mail mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="newConfirmEmail">Weiter</v-btn>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Benutzer muss confirmed werden -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'USER_MUST_BE_CONFIRMED'">
                <v-card-title>Freischaltung ausständig</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="warning">
                        <div>Du mußt vom Administrator freigeschaltet werden.</div>
                        <div class="mt-2">Bitte um etwas Geduld. Du wirst nach Freigabe per E-Mail verständigt.</div>
                    </v-alert>
                </v-card-text>
            </v-card>

            <!-- Benutzer existiert: Kennwort eingeben -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'USER_FOUND' || data.status == 'RETRY_PASSWORD'" data-testid="tutoring-login-step-password">
                <v-card-title>Bitte Kennwort eingeben</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="loginWithPassword(data)" class="my-4">
                        <v-alert class="mt-4" color="warning" v-if="data.status == 'RETRY_PASSWORD'" data-testid="tutoring-login-password-retry-alert">
                            <div>Das Kennwort war falsch.</div>
                            <div class="mt-2">Bitte probiere es erneut oder klicke auf 'Kennwort unbekannt'.</div>
                        </v-alert>

                        <v-text-field
                            autofocus
                            type="password"
                            v-model="data.password"
                            label="Dein Kennwort"
                            :rules="[required(), maxLength(255)]"
                            tabindex="1"
                            data-testid="tutoring-login-password"
                            id="tutoring-login-password" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="data.status = ''">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.password" tabindex="2" data-testid="tutoring-login-submit-password">Weiter</v-btn>
                        </div>
                        <div class="mt-4 d-flex flex-column align-center justify-center ga-2">
                            <div>oder</div>
                            <v-btn
                                color="primary"
                                slim
                                flat
                                rounded="0"
                                data-testid="tutoring-login-unknown-password"
                                :disabled="!isCodeLoginAvailable"
                                @click="unknownPassword(data)">
                                Kennwort unbekannt
                            </v-btn>
                            <v-alert v-if="!isCodeLoginAvailable" density="compact" type="warning" variant="tonal" class="w-100 mt-2">
                                !Login mit Code derzeit nicht möglich
                            </v-alert>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>

            <!-- Benutzer existiert: Code statt Kennwort eingeben -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'LOGIN_WITH_TOKEN'">
                <v-card-title>Login mit Code</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="warning">
                        <div>Du hast eine E-Mail erhalten. Bitte gib den Code hier ein.</div>
                    </v-alert>

                    <v-form ref="form" v-model="is_valid" @submit.prevent="loginWithToken(data)" class="my-4">
                        <v-otp-input autofocus v-model="data.token_2fa" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="data.status = ''">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.token_2fa" tabindex="2" :disabled="!isCodeLoginAvailable">Weiter</v-btn>
                        </div>
                        <v-alert v-if="!isCodeLoginAvailable" density="compact" type="warning" variant="tonal" class="w-100 mt-2">
                            !Login mit Code derzeit nicht möglich
                        </v-alert>
                    </v-form>
                </v-card-text>
            </v-card>

            <!-- Login mit Code war nicht erfolgreich -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'RETRY_LOGIN_WITH_TOKEN'">
                <v-card-title>Login nicht erfolgreich!</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="warning">
                        <div>Der Code war falsch oder bereits abgelaufen.</div>
                        <div class="mt-2">Wir haben Dir eine neue E-Mail mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="retryLoginWithToken">Weiter</v-btn>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Login war erfolgreich -->
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'LOGGED_IN'" data-testid="tutoring-login-step-success">
                <v-card-title>Login war erfolgreich!</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="success">
                        <div>Du bist erfolgreicht eingeloggt!</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="$emit('logout')">Logout</v-btn>
                        <v-btn color="primary" slim flat rounded="0" data-testid="tutoring-login-continue-after-success" @click="$emit('login-success')">
                            Weiter
                        </v-btn>
                    </div>
                </v-card-text>
            </v-card>
        </div>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'

export default {
    name: 'SchoolAndUser',

    setup() {
        return useValidationRulesSetup()
    },

    props: {
        is_init: {
            type: Boolean,
            required: true,
        },
        is_login: {
            type: Boolean,
            required: true,
        },
        school_name: {
            type: String,
            default: '',
        },
        school: {
            type: Object,
            default: null,
        },
        queue_working: {
            type: Boolean,
            default: null,
        },
    },

    emits: ['cancel-login', 'login-success', 'logout'],

    data() {
        return {
            tutoringStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['schools', 'selected_school_id', 'data', 'config']),
        isCodeLoginAvailable() {
            if (this.queue_working === false) {
                return false
            }

            if (this.config?.health?.queue_working === false) {
                return false
            }

            return true
        },
    },

    beforeMount() {
        this.tutoringStore = useTutoringStore()
    },

    methods: {
        enterPassword() {
            this.data['status'] = 'USER_FOUND'
        },

        retryLoginWithToken() {
            this.data['token_2fa'] = ''
            this.data['status'] = 'LOGIN_WITH_TOKEN'
        },

        async loginWithToken(data) {
            if (!this.isCodeLoginAvailable) return

            if (!(await this.tutoringStore.loginWithToken(data))) return
        },

        async loginWithPassword(data) {
            if (!(await this.tutoringStore.loginWithPassword(data))) return
        },

        newConfirmEmail() {
            this.data['token_2fa'] = ''
            this.data['status'] = 'CONFIRM_EMAIL'
        },

        async confirmEmail(data) {
            if (!(await this.tutoringStore.confirmEmail(data))) return
        },

        async checkEmail(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            data.school_id = this.school?.id ?? null
            if (!(await this.tutoringStore.checkEmail(data))) return
        },

        async createUser(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            data.school_id = this.school?.id ?? null
            if (!(await this.tutoringStore.createUser(data))) return
        },

        async unknownPassword(data) {
            if (!this.isCodeLoginAvailable) return

            data['status'] = 'UNKNOWN_PASSWORD'
            if (!(await this.tutoringStore.unknownPassword(data))) return
        },
    },
}
</script>
