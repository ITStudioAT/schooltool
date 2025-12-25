<template>
    <div class="schooltool-background"></div>
    <div class="h-100 w-100 d-flex flex-column" style="max-width: 1024px; margin: auto">
        <div class="h-100 w-100 d-flex flex-column align-center justify-center" v-if="is_init && !school_name && schools.length == 0">
            <v-card tile flat color="transparent">
                <v-alert type="error" title="Das Nachhilfetool kann derzeit nicht verwendet werden." />
            </v-card>
        </div>

        <!-- Überschrift -->
        <div v-if="is_loaded">
            <v-card flat tile>
                <div class="d-flex flex-row justify-center">
                    <div class="text-h6 text-md-h5 text-lg-h4 text-xl-h2">
                        <span class="text-secondary">NACH</span>
                        <span class="text-third font-weight-bold">HILFE</span>
                        <span class="text-secondary">TOOL</span>
                    </div>
                </div>
            </v-card>

            <v-card flat tile color="transparent" class="mt-4" v-if="offer_config.school">
                <div class="d-flex justify-center">
                    <div class="d-flex flex-column align-center">
                        <div class="text-caption">{{ offer_config.school.long_name }}</div>
                        <div style="width: 96px; height: 48px" class="bg-primary-lighten-4">
                            <img :src="'/storage/images/' + offer_config.school.logo" alt="Logo" style="width: 100%; height: 100%; object-fit: contain" />
                        </div>
                    </div>
                </div>
            </v-card>
        </div>

        <v-card tile flat class="mt-4" color="transparent" v-if="is_loaded && !is_login">
            <!-- Menü -->
            <v-card flat color="primary" class="border-md">
                <div class="d-flex flex-wrap justify-center justify-lg-start ga-4">
                    <ItsCard
                        :title="offer_config?.auth?.user?.last_name + ' ' + offer_config?.auth?.user?.first_name"
                        text="Hier gelangst Du zu Deinem persönlichen Bereich. Dort kannst Du auch Angebote erstellen."
                        color="success"
                        button="Mein Bereich"
                        @clickCard="moveToTutoring"
                        v-if="offer_config.auth.is_auth" />

                    <ItsCard
                        title="Mich abmelden"
                        text="Hier kannst Du Dich vom System ausloggen."
                        color="success"
                        button="Abmelden"
                        @clickCard="logout"
                        v-if="offer_config.auth.is_auth" />

                    <ItsCard
                        title="Anmelden"
                        text="Melde Dich kostenlos an, um mehr zu können."
                        color="secondary"
                        button="Los"
                        @clickCard="startLogin"
                        v-if="!offer_config.auth.is_auth" />
                </div>
            </v-card>
        </v-card>

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
            <v-card tile flat color="primary" min-width="300" v-if="!data.status">
                <v-card-title>Bitte die E-Mail eingeben</v-card-title>
                <v-card-text>
                    <v-form ref="form" v-model="is_valid" @submit.prevent="checkEmail(data)" class="mb-4">
                        <v-text-field autofocus v-model="data.email" label="Deine E-Mail-Adresse" :rules="[required(), mail()]" tabindex="1" />
                        <div class="d-flex flex-row align-center justify-space-between mt-4">
                            <v-btn color="warning" slim flat rounded="0" @click="is_login = false">Zurück</v-btn>
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.email" tabindex="2">Weiter</v-btn>
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
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'USER_FOUND' || data.status == 'RETRY_PASSWORD'">
                <v-card-title>Bitte Kennwort eingeben</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
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
                            <v-btn color="success" slim flat rounded="0" type="submit" v-if="data.token_2fa" tabindex="2">Weiter</v-btn>
                        </div>
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
            <v-card tile flat color="primary" min-width="300" v-if="data.status == 'LOGGED_IN'">
                <v-card-title>Login war erfolgreich!</v-card-title>
                <v-card-subtitle>E-Mail: {{ data.email }}</v-card-subtitle>
                <v-card-text>
                    <v-alert class="mt-4" color="success">
                        <div>Du bist erfolgreicht eingeloggt!</div>
                    </v-alert>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="primary" slim flat rounded="0" @click="logout">Logout</v-btn>
                        <v-btn color="primary" slim flat rounded="0" @click="afterLogin">Weiter</v-btn>
                    </div>
                </v-card-text>
            </v-card>
        </div>
        <!-- SUCHLEISTE -->
        <v-expansion-panels v-model="search_panel" color="primary" v-if="offers && offers.length > 0 && offer_config && offer_config.auth.is_auth" class="mt-4">
            <v-expansion-panel>
                <v-expansion-panel-title class="text-body-1 font-weight-medium">Suche</v-expansion-panel-title>
                <v-expansion-panel-text>
                    <v-checkbox-btn color="success" v-model="search.only_in_my_school" label="Nur in Deiner Schule suchen" />
                    <div class="d-flex flex-row align-center ga-2">
                        <v-checkbox-btn color="pink" v-model="search.only_girls" label="Nachhilfe nur von Mädchen" />
                        <v-checkbox-btn color="blue" v-model="search.only_boys" label="Nachhilfe nur von Burschen" />
                    </div>
                    <v-text-field label="Suchtext" v-model="search_text" hide-details class="large-text mt-4" append-icon="mdi-magnify" clearable />
                    <div class="text-right mt-4">
                        <v-btn size="large" tile flat color="success" @click="">Jetzt suchen</v-btn>
                    </div>
                </v-expansion-panel-text>
            </v-expansion-panel>
        </v-expansion-panels>
        <!-- Angebote -->
        <v-card tile flat color="transparent" v-if="is_loaded && !is_login">
            <v-card flat color=" bg-primary" class="border-md mt-4" v-if="offers && offers.length > 0">
                <ItsCard
                    :title="offer.subject.short_name"
                    :subtitle="offer.subject.long_name"
                    :text="offer.title"
                    :description="offer.description"
                    color="secondary"
                    button="Anschauen"
                    @clickCard=""
                    v-for="offer in offers"
                    :key="offer.id" />
            </v-card>
            <!-- KEINE ANGEBOT VORHANDEN-->
            <v-card v-else class="border-md mt-4">
                <v-alert type="info" title="Aktuell sind keine Angebote vorhanden!" text="Melde Dich an und lege selbst ein Angebot an." v-if="!offer_config.auth.is_auth">
                    <v-btn size="small" tile flat color="primary" variant="text" class="ml-4" @click="startLogin">Los</v-btn>
                    <v-btn size="small" tile flat color="primary" variant="text" class="ml-4" @click="moveToTutoring" v-if="offer_config.auth.is_auth">Los</v-btn>
                </v-alert>
                <v-alert type="info" title="Aktuell sind keine Angebote vorhanden!" text="Erstelle Dein eigenes Angebot." v-if="offer_config.auth.is_auth">
                    <v-btn size="small" tile flat color="primary" variant="text" class="ml-4" @click="moveToTutoring" v-if="offer_config.auth.is_auth">Los</v-btn>
                </v-alert>
            </v-card>
        </v-card>

        <v-card>
            offers:
            {{ offers }}
        </v-card>
        <v-card class="mt-4">
            OFFER_CONFIG:
            {{ offer_config }}
        </v-card>
    </div>

    <div class="h-100 w-100 d-flex flex-column justify-center align-center" style="max-width: 1024px; margin: auto" v-if="error">
        <!-- Fehlermeldung -->
        <v-card class="mt-4">
            <v-alert type="error" :title="error.response.data.message + ' (' + error.response.status + ')'" />
        </v-card>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import ItsCard from '@/pages/components/ItsCard.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsCard },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.offerStore = useOfferStore()
        this.userStore = useUserStore()

        await this.offerStore.loadOfferConfig(this.school_name)

        if (!this.offer_config?.auth?.is_auth) {
            this.school_name = this.$route.query.school
            if (!this.school_name) {
                await this.initWithoutSchool()
            } else {
                await this.initWithSchool()
            }
        } else {
            this.school = this.offer_config.school
            this.school_name = this.school.short_name
            await this.initWithSchool()
        }

        this.is_init = true
    },

    mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            offerStore: null,
            userStore: null,
            school_name: '',
            is_loaded: false,
            is_init: false,
            is_login: false,
            is_valid: false,
            search: { only_in_my_school: true },
            search_text: '',
            search_panel: null,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['schools', 'selected_school_id', 'data']),
        ...mapWritableState(useOfferStore, ['offer_config', 'error', 'offers']),
    },

    watch: {
        selected_school_id() {
            if (this.selected_school_id) {
                const school = this.schools.find((s) => s.id === this.selected_school_id)
                if (school) {
                    const params = new URLSearchParams(window.location.search)
                    params.set('school', school.short_name)
                    window.location.href = window.location.pathname + '?' + params.toString()
                }
            }
        },
        'search.only_girls'(newValue) {
            if (newValue) this.search.only_boys = false
        },
        'search.only_boys'(newValue) {
            if (newValue) this.search.only_girls = false
        },
        search: {
            handler(newValue) {
                console.log('search changed after:', newValue)
            },
            deep: true,
            flush: 'post',
        },
    },

    methods: {
        moveToTutoring() {
            this.$router.push('/homepage/tutoring')
        },

        async afterLogin() {
            await this.initWithSchool()
            this.is_login = false
        },
        startLogin() {
            this.data = {}
            this.data.school_id = this.school.id
            this.is_login = true
        },
        async initWithSchool() {
            await this.offerStore.loadOfferConfig(this.school_name)
            this.school = this.offer_config.school
            this.data.school_id = this.school.id
            await this.offerStore.loadOffers(this.school_name)
            this.is_loaded = true
        },
        async initWithoutSchool() {
            await this.tutoringStore.loadConfig()
            if (this.schools.length == 1) {
                const school_name = this.schools[0].short_name
                const params = new URLSearchParams(window.location.search)
                params.set('school', school_name)
                window.location.href = window.location.pathname + '?' + params.toString()
            }
        },

        async logout() {
            await this.userStore.logout()
            await this.offerStore.loadOfferConfig(this.school_name)
            await this.offerStore.loadOffers(this.school_name)
        },

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
            console.log(data)
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

<style scoped>
.schooltool-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('/storage/images/students.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0.2;
    pointer-events: none;
    z-index: -1;
}

.large-text :deep(input) {
    font-size: 28px !important;
}
</style>
