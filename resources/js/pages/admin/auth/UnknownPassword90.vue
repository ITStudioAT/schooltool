<template>
    <v-container fluid class="h-100 w-100 d-flex align-center justify-center" v-if="config">
        <v-card class="mx-auto" width="300">
            <v-img height="80px" :src="'/storage/images/' + config.logo" @click="homepage" class="hover"></v-img>
            <v-card-subtitle class="text-caption text-text">
                {{ config.version }}
            </v-card-subtitle>
            <v-card-title class="mb-4 bg-accent">
                Neues Kennwort
            </v-card-title>

            <!-- Password unknown STEP PASSWORD_UNKNOWN_ENTER_EMAIL = E-Mail -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_ENTER_EMAIL'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="passwordUnknownStep1(data)" class="mb-4">
                    <div class="text-caption text-text">Bitte die E-Mail-Adresse eingeben</div>
                    <v-text-field autofocus v-model="data.email" label="Email" :rules="[required(), mail()]" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" @click="passwordUnknownStep1(data)">Weiter</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="primary" slim flat rounded="0" variant="text" @click="login">Zurück zum
                    Login</v-btn>
            </v-card-text>

            <!-- Password unknown STEP PASSWORD_UNKNOWN_ENTER_TOKEN = Token -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_ENTER_TOKEN'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="passwordUnknownStep2(data)" class="mb-4">
                    <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails" />
                    <div class="text-caption text-text">Bitte den Code laut E-Mail eingeben</div>
                    <v-otp-input autofocus v-model="data.token_2fa" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" @click="passwordUnknownStep2(data)">Weiter</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text"
                    @click="restartPasswordUnknown">Zurück</v-btn>
            </v-card-text>

            <!-- Password unknown STEP PASSWORD_UNKNOWN_ENTER_TOKEN_2 = Token -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_ENTER_TOKEN_2'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="passwordUnknownStep3(data)" class="mb-4">
                    <v-alert closable color="success" type="info"
                        text="Bitte prüfen Sie Ihre E-Mails der 2. E-Mail-Adresse" />
                    <div class="text-caption text-text">Bitte den Code laut E-Mail Ihrer 2. E-Mail-Adresse eingeben
                    </div>
                    <v-otp-input autofocus v-model="data.token_2fa_2" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" @click="passwordUnknownStep3(data)">Weiter</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text"
                    @click="restartPasswordUnknown">Zurück</v-btn>
            </v-card-text>

            <!-- Password unknown STEP PASSWORD_UNKNOWN_ENTER_PASSWORD = Neues Kennwort erfassen -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_ENTER_PASSWORD'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="passwordUnknownStep1(data)" class="mb-4">
                    <div class="text-caption text-text">Bitte das neue Kennwort eingeben</div>
                    <v-text-field autofocus label="Kennwort"
                        :append-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
                        :type="is_password_visible ? 'text' : 'password'"
                        @click:append="() => (is_password_visible = !is_password_visible)"
                        :rules="[required(), minLength(8), maxLength(255)]" v-model="data.password" />
                    <v-text-field label="Wiederholung Kennwort"
                        :append-icon="is_password_visible_repeat ? 'mdi-eye' : 'mdi-eye-off'"
                        :type="is_password_visible_repeat ? 'text' : 'password'"
                        @click:append="() => (is_password_visible_repeat = !is_password_visible_repeat)"
                        :rules="[required(), minLength(8), maxLength(255), passwordMatch(data.password)]"
                        v-model="data.password_repeat" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" @click="passwordUnknownStep4(data)">Speichern</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="primary" slim flat rounded="0" variant="text" @click="login">Zurück zum
                    Login</v-btn>
            </v-card-text>

            <!-- Password unknown STEP PASSWORD_UNKNOWN_FINISEH = Reset des Kennwortes erfolgreich abgeschlossen -->
            <v-card-text v-if="step == 'PASSWORD_UNKNOWN_FINISHED'">

                <v-alert closable color="success" type="info" text="Ihr Kennwort wurde erfolreich zurückgesetzt." />
                <div class="text-caption text-text">Sie können sich jetzt mit dem neuen Kennwort einloggen</div>

                <v-btn block color="success" slim flat rounded="0" @click="login">Zum Login</v-btn>
            </v-card-text>

        </v-card>



    </v-container>
</template>

<script>
import { useValidationRulesSetup } from "@/helpers/rules";
import { mapWritableState } from "pinia";
import { useAdminStore } from "@/stores/admin/AdminStore";

export default {

    setup() { return useValidationRulesSetup(); },

    components: {},

    async beforeMount() {
        await axios.get('/sanctum/csrf-cookie');
        this.adminStore = useAdminStore();
        this.restartPasswordUnknown();

    },

    data() {
        return {
            adminStore: null,
            data: {},
            is_valid: false,
            step: null,
            is_password_visible: false,
            is_password_visible_repeat: false,

        };
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'error', 'api_response']),

    },

    methods: {

        homepage() {
            window.location.href = "/";
        },

        login() {
            this.$router.push('/admin/login');
        },

        restartPasswordUnknown() {
            this.data.password = null;
            this.data.token_2fa = null;
            this.step = 'PASSWORD_UNKNOWN_ENTER_EMAIL';
        },

        passwordUnknown() {
            this.restartPasswordUnknown();
        },

        async passwordUnknownStep1(data) {
            this.is_valid = false;
            await this.$refs.form.validate(); if (!this.is_valid) return;
            data.step = 'PASSWORD_UNKNOWN_ENTER_EMAIL';
            if (await this.adminStore.passwordUnknownStep1(data)) this.step = 'PASSWORD_UNKNOWN_ENTER_TOKEN';
        },

        async passwordUnknownStep2(data) {
            if (this.data.token_2fa.length != 6) return;
            data.step = 'PASSWORD_UNKNOWN_ENTER_TOKEN';
            if (await this.adminStore.passwordUnknownStep2(data)) {
                if (this.api_response.data.step == 'PASSWORD_UNKNOWN_SUCCESS') { this.step = "PASSWORD_UNKNOWN_ENTER_PASSWORD" } else { this.step = 'PASSWORD_UNKNOWN_ENTER_TOKEN_2'; }
            }
        },

        async passwordUnknownStep3(data) {
            if (this.data.token_2fa.length != 6) return;
            data.step = 'PASSWORD_UNKNOWN_ENTER_TOKEN_2';
            if (await this.adminStore.passwordUnknownStep3(data)) this.step = "PASSWORD_UNKNOWN_ENTER_PASSWORD";
        },

        async passwordUnknownStep4(data) {
            this.is_valid = false;
            await this.$refs.form.validate(); if (!this.is_valid) return;
            data.step = 'PASSWORD_UNKNOWN_ENTER_PASSWORD';
            if (await this.adminStore.passwordUnknownStep4(data)) this.step = "PASSWORD_UNKNOWN_FINISHED";

        },
    },


}
</script>