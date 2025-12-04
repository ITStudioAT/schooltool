<template>
    <v-container fluid class="ma-0 w-100 h-100 pa-0 d-flex align-center justify-center bg-tutoring_background text-tutoring_text">
        <v-card tile flat class="bg-tutoring_background-lighten-1 w-100 fill-height" max-width="1024" v-if="auth">
            <!-- HEADER -->
            <v-card-text>
                <div class="d-flex flex-row align-center justify-space-between">
                    <div>
                        <div class="text-caption">{{ auth.school_long_name }}</div>
                        <div style="width: 96px; height: 48px">
                            <img :src="'/storage/images/' + auth.school_logo" alt="Logo" style="width: 100%; height: 100%; object-fit: contain" />
                        </div>
                    </div>
                    <div class="text-body-1">{{ auth.auth_user.last_name + ' ' + auth.auth_user.first_name }}</div>
                </div>
            </v-card-text>

            <!-- TITLE -->
            <v-card-title class="text-h4">NACHHILFE</v-card-title>

            <!-- MENÜ -->
            <v-card-text>
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2" :disabled="action != ''">
                    <its-menu-button
                        title="Profil"
                        subtitle="ändern"
                        icon="mdi-account"
                        :color="action == 'profile' ? 'button_primary_selected' : 'button_primary'"
                        @click="editProfile" />
                    <its-menu-button
                        title="Kennwort"
                        subtitle="ändern"
                        icon="mdi-form-textbox-password"
                        :color="action == 'password' ? 'button_primary_selected' : 'button_primary'"
                        @click="editPassword" />
                    <its-menu-button
                        title="Mich"
                        subtitle="abmelden"
                        icon="mdi-logout"
                        :color="action == 'logout' ? 'button_primary_selected' : 'button_primary'"
                        @click="logout" />
                </v-card>
            </v-card-text>
            <!-- MENÜ 2. Zeile -->
            <v-card-text>
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2" :disabled="action != ''">
                    <its-menu-button
                        title="Nachilfe"
                        subtitle="anbieten"
                        icon="mdi-offer"
                        :color="action == 'search' ? 'button_primary_selected' : 'button_primary'"
                        @click="createOffer" />

                    <!--
                    <its-menu-button
                        title="Nachilfe"
                        subtitle="suchen"
                        icon="mdi-card-search"
                        :color="action == 'offer' ? 'button_primary_selected' : 'button_primary'"
                        @click="" />
                        -->
                </v-card>
            </v-card-text>

            <!-- ANGEBOTE -->
            <v-card-text>
                <h1>DEINE ANGEBOTE</h1>
            </v-card-text>

            <!-- NEUES ANGEBOT  -->
            <v-card-text v-if="action == 'create_offer'">
                <v-card tile flat color="tutoring_card" max-width="600">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="doCreateOffer(data)">
                        <v-card-title class="bg-tutoring_card_title mb-2">Nachhilfe anbieten</v-card-title>
                        <!-- Nachanme, Vorname, E-Mail eingeben-->
                        <v-card-text>
                            <v-card tile flat color="transparent">
                                <div>
                                    <v-chip-group selected-class="text-success" column color="primary">
                                        <v-chip color="primary" v-for="subject in subjects" :key="subject.id" @click="selectSubject(subject)">
                                            {{ subject.long_name + ' (' + subject.short_name + ')' }}
                                        </v-chip>
                                    </v-chip-group>
                                </div>
                                <div v-if="selectedSubject" class="mt-4">
                                    <v-text-field autofocus flat rounded="0" v-model="data.title" label="Titel" :rules="[required(), maxLength(255)]" />
                                    <v-textarea flat rounded="0" v-model="data.description" label="Beschreibe Deine Nachhilfe im Detail" :rules="[maxLength(1024)]" />
                                </div>

                                <div class="mt-4">
                                    <label class="text-subtitle-2 mb-2 d-block">Für folgende Klassen ist Deine Nachhilfe gedacht:</label>

                                    <div class="mb-3">
                                        <div class="text-caption text-medium-emphasis mb-1">Unterstufe</div>
                                        <v-row dense>
                                            <v-col v-for="classNumber in 4" :key="classNumber" cols="12" sm="6" md="3">
                                                <v-checkbox v-model="data.classes[classNumber]" :label="`${classNumber}. Klasse`" hide-details />
                                            </v-col>
                                        </v-row>
                                    </div>

                                    <div>
                                        <div class="text-caption text-medium-emphasis mb-1">Oberstufe</div>
                                        <v-row dense>
                                            <v-col v-for="classNumber in 5" :key="classNumber + 4" cols="12" sm="6" md="3">
                                                <v-checkbox v-model="data.classes[classNumber + 4]" :label="`${classNumber + 4}. Klasse`" hide-details />
                                            </v-col>
                                        </v-row>
                                    </div>
                                </div>
                            </v-card>
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

            <!-- PASSWORD -->
            <v-card-text v-if="action == 'password'">
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

            <!-- SUBJECTS -->
            <v-card-text>
                SUBJECTS:
                <v-list>
                    <v-list-item v-for="(value, key) in subjects" :key="key">
                        <v-list-item-title>{{ key }}</v-list-item-title>
                        <v-list-item-subtitle>{{ value }}</v-list-item-subtitle>
                    </v-list-item>
                </v-list>
            </v-card-text>

            <!-- AUTH
            <v-card-text>
                <v-list>
                    <v-list-item v-for="(value, key) in auth" :key="key">
                        <v-list-item-title>{{ key }}</v-list-item-title>
                        <v-list-item-subtitle>{{ value }}</v-list-item-subtitle>
                    </v-list-item>
                </v-list>
            </v-card-text>
            -->
        </v-card>
    </v-container>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useSubjectStore } from '@/stores/tutoring/SubjectStore'
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
        this.subjectStore = useSubjectStore()
        await this.tutoringStore.loadAuth()
        await this.subjectStore.index()
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
            selectedSubject: null,
            step: 0,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action']),
        ...mapWritableState(useUserStore, ['error', 'data']),
        ...mapWritableState(useSubjectStore, ['subjects']),
    },

    watch: {},

    methods: {
        selectSubject(subject) {
            this.selectedSubject = subject
            this.data.title = 'Biete ' + subject.long_name + ' Nachhilfe'
        },
        createOffer() {
            this.action = 'create_offer'
            this.data = {
                title: '',
                description: '',
                classes: { 1: false, 2: false, 3: false, 4: false, 5: false, 6: false, 7: false, 8: false, 9: false },
            }
        },

        async doCreateOffer(data) {},

        async updatePassword(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.userStore.updatePassword(data))) return

            if (this.data.status != 'OK') return

            await this.tutoringStore.loadAuth()
            this.action = ''
        },

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
            this.action = 'profile'
        },

        editPassword() {
            this.error = null
            this.data = {
                id: this.auth.auth_user.id,
            }
            this.action = 'password'
        },

        async logout() {
            await this.userStore.logout()
            await this.tutoringStore.loadAuth()
            this.action = ''
            this.$router.push('/homepage/tutoring_intro/?school=' + this.auth?.school_short_name)
        },
    },
}
</script>
