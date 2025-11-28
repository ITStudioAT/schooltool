<template>
    <v-container fluid class="ma-0 w-100 h-100 pa-2 d-flex align-center justify-center bg-tutoring_background text-tutoring_text">
        <div class="d-flex flex-row border-md pa-0">
            <img src="/storage/images/books.jpg" width="300" v-if="$vuetify.display.smAndUp" />

            <v-card flat tile width="300" class="flex-grow-1 d-flex flex-column" color="transparent">
                <v-card-title class="bg-tutoring_secondary text-uppercase text-h4">Tutoring</v-card-title>

                <!-- Schulen auswählen, wenn sie nicht im Aufruf mitgeliefert wurde, z. B. ?school=cdgym -->
                <div class="mt-4" v-if="!school">
                    <div class="text-body-1 font-weight-bold ml-2">Bitte die Schule auswählen</div>
                    <div>
                        <v-autocomplete dense hide-details v-model="selected_school_id" :items="schools" item-title="long_name" item-value="id" label="Auswahl Schule" />
                    </div>
                </div>

                <v-card-text v-if="school">
                    <div>
                        <img :src="`/storage/images/${school?.logo}`" alt="Logo" class="logo" v-if="school?.logo" height="100" />
                    </div>
                </v-card-text>

                <!-- E-Mail muss eingegeben werden -->
                <v-card-text v-if="school && !data.status">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="checkEmail(data)" class="mb-4">
                        <v-text-field autofocus v-model="data.email" label="Deine E-Mail-Adresse" :rules="[required(), mail()]" tabindex="1" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" to="/">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email" tabindex="2">Weiter</v-btn>
                        </div>
                    </v-form>
                </v-card-text>

                <!-- Neuer Benutzer: Name muss eingegeben werden -->
                <v-card-text v-if="data.status == 'NEW_USER'">
                    <div class="text-h6 font-weight-medium">Neuer Benutzer</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="createUser(data)" class="my-4">
                        <v-text-field autofocus v-model="data.last_name" label="Dein Nachname" :rules="[required(), maxLength(255)]" tabindex="1" />
                        <v-text-field v-model="data.first_name" label="Dein Vorname" :rules="[maxLength(255)]" tabindex="1" />

                        <v-radio-group v-model="data.sex" :rules="[required()]">
                            <v-radio label="Männlich" value="m" color="blue"></v-radio>
                            <v-radio label="Weiblich" value="f" color="pink"></v-radio>
                        </v-radio-group>

                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="data.status = ''">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email" tabindex="2">Weiter</v-btn>
                        </div>
                    </v-form>
                </v-card-text>

                <!-- Benutzer inactive -->
                <v-card-text v-if="data.status == 'USER_INACTIVE'">
                    <div class="text-h6 font-weight-medium">Benutzer inaktiv!</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>

                    <v-alert class="mt-4" color="warning">
                        <div>Der Benutzer ist auf gesperrt.</div>
                        <div class="mt-2">Wenden Sie sich an Ihren Administrator.</div>
                    </v-alert>
                </v-card-text>

                <!-- Benutzer muss E-Mail bestätigen -->
                <v-card-text v-if="data.status == 'CONFIRM_EMAIL'">
                    <div class="text-h6 font-weight-medium">Bitte Code eingeben</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>
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

                <!-- Login mit Code war erfolgreich -->
                <v-card-text v-if="data.status == 'EMAIL_VERIFIED'">
                    <div class="text-h6 font-weight-medium">E-Mail erfolgreich bestätigt!</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>

                    <v-alert class="mt-4" color="success">
                        <div>Die E-Mail wurde erfolgreich bestätigt!</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="enterPassword">Weiter</v-btn>
                    </div>
                </v-card-text>

                <!-- EMail-Confirm war nicht erfolgreich -->
                <v-card-text v-if="data.status == 'CONFIRM_EMAIL_AGAIN'">
                    <div class="text-h6 font-weight-medium">EMail nicht verifiziert!</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>

                    <v-alert class="mt-4" color="warning">
                        <div>Die E-Mail-Verifizierung war nicht erfolgreich.</div>
                        <div class="mt-2">Wir haben Dir eine neue E-Mail mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="newConfirmEmail">Weiter</v-btn>
                    </div>
                </v-card-text>

                <!-- Benutzer muss confirmed werden -->
                <v-card-text v-if="data.status == 'USER_MUST_BE_CONFIRMED'">
                    <div class="text-h6 font-weight-medium">Freischaltung ausständig</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>

                    <v-alert class="mt-4" color="warning">
                        <div>Du mußt vom Administrator freigeschaltet werden.</div>
                        <div class="mt-2">Bitte um etwas Geduld. Du wirst nach Freigabe per E-Mail verständigt.</div>
                    </v-alert>
                </v-card-text>

                <!-- Benutzer existiert: Kennwort eingeben -->
                <v-card-text v-if="data.status == 'USER_FOUND' || data.status == 'RETRY_PASSWORD'">
                    <div class="text-h6 font-weight-medium">Bitte Kennwort eingeben</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="loginWithPassword(data)" class="my-4">
                        <v-alert class="mt-4" color="warning" v-if="data.status == 'RETRY_PASSWORD'">
                            <div>Das Kennwort war falsch.</div>
                            <div class="mt-2">Bitte probiere es erneut oder klicke auf 'Kennwort unbekannt'.</div>
                        </v-alert>

                        <v-text-field autofocus type="password" v-model="data.password" label="Dein Kennwort" :rules="[required(), maxLength(255)]" tabindex="1" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="data.status = ''">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.password" tabindex="2">Weiter</v-btn>
                        </div>
                        <div class="mt-4 d-flex flex-column align-center justify-center ga-2">
                            <div>oder</div>
                            <v-btn color="primary" slim flat rounded="0" @click="unknownPassword(data)">Kennwort unbekannt</v-btn>
                        </div>
                    </v-form>
                </v-card-text>

                <!-- Benutzer existiert: Code statt Kennwort eingeben -->
                <v-card-text v-if="data.status == 'LOGIN_WITH_TOKEN'">
                    <div class="text-h6 font-weight-medium">Login mit Code</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>

                    <v-alert class="mt-4" color="warning">
                        <div>Du hast eine E-Mail erhalten. Bitte gib den Code hier ein.</div>
                    </v-alert>

                    <v-form ref="form" v-model="is_valid" @submit.prevent="loginWithToken(data)" class="my-4">
                        <v-otp-input autofocus v-model="data.token_2fa" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="data.status = ''">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.token_2fa" tabindex="2">Weiter</v-btn>
                        </div>
                    </v-form>
                </v-card-text>

                <!-- Login mit Code war nicht erfolgreich -->
                <v-card-text v-if="data.status == 'RETRY_LOGIN_WITH_TOKEN'">
                    <div class="text-h6 font-weight-medium">Login nicht erfolgreich!</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>

                    <v-alert class="mt-4" color="warning">
                        <div>Der Code war falsch oder bereits abgelaufen.</div>
                        <div class="mt-2">Wir haben Dir eine neue E-Mail mit einem Code geschickt. Bitte gib den Code hier ein.</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="retryLoginWithToken">Weiter</v-btn>
                    </div>
                </v-card-text>

                <!-- Login war erfolgreich -->
                <v-card-text v-if="data.status == 'LOGGED_IN'">
                    <div class="text-h6 font-weight-medium">Login war erfolgreich!</div>
                    <div class="text-body-2">E-Mail: {{ data.email }}</div>

                    <v-alert class="mt-4" color="success">
                        <div>Du bist erfolgreicht eingeloggt!</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="logout">Logout</v-btn>
                        <v-btn color="primary" slim flat rounded="0" to="/homepage/tutoring">Weiter</v-btn>
                    </div>
                </v-card-text>
            </v-card>
        </div>
    </v-container>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.school_name = this.$route.query.school
        await this.tutoringStore.loadConfig()
        this.data = {}
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            school_name: null,

            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['schools', 'selected_school_id', 'school', 'data']),
    },

    watch: {
        selected_school_id: {
            handler(newId) {
                this.school = this.schools.find((school) => school.id === newId) || null
            },
            immediate: true,
        },
        schools: {
            handler(newSchools) {
                if (newSchools.length > 0 && this.school_name) {
                    const foundSchool = newSchools.find((school) => school.short_name.toLowerCase() === this.school_name.toLowerCase())
                    if (foundSchool) {
                        this.school = foundSchool
                        this.selected_school_id = foundSchool.id
                    }
                }
            },
            immediate: true,
        },
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
            data['status'] = 'UNKNOWN_PASSWORD'
            if (!(await this.tutoringStore.unknownPassword(data))) return
        },
    },
}
</script>
