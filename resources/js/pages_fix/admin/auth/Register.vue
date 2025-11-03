<template>
    <v-container fluid class="h-100 w-100 d-flex align-center justify-center" v-if="config">
        <v-card class="mx-auto" width="300">
            <v-img height="80px" :src="'/storage/images/' + config.logo" @click="homepage" class="hover"></v-img>
            <v-card-subtitle class="text-caption text-text">
                {{ config.version }}
            </v-card-subtitle>
            <v-card-title class="mb-4 bg-success">
                Neu registrieren
            </v-card-title>

            <!-- Register STEP REGISTER_ENTER_EMAIL = E-Mail -->
            <v-card-text v-if="step == 'REGISTER_ENTER_EMAIL'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="registerStep1(data)" class="mb-4">
                    <div class="text-caption text-text">Bitte die E-Mail-Adresse eingeben</div>
                    <v-text-field autofocus v-model="data.email" label="Email" :rules="[required(), mail()]" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" @click="registerStep1(data)">Weiter</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="primary" slim flat rounded="0" variant="text" @click="login">Zurück zum
                    Login</v-btn>
            </v-card-text>

            <!-- Register STEP REGISTER_ENTER_TOKEN = Token_2fa -->
            <v-card-text v-if="step == 'REGISTER_ENTER_TOKEN'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="registerStep2(data)" class="mb-4">
                    <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails" />
                    <div class="text-caption text-text">Bitte den Code laut E-Mail eingeben</div>
                    <v-otp-input autofocus v-model="data.token_2fa" />
                </v-form>
                <v-btn block color="success" slim flat rounded="0" @click="registerStep2(data)">Anmelden</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="warning" slim flat rounded="0" variant="text"
                    @click="restartRegister">Zurück</v-btn>
            </v-card-text>

            <!-- Register STEP REGISTER_ENTER_FIELDS = Felder eingeben -->
            <v-card-text v-if="step == 'REGISTER_ENTER_FIELDS'">
                <v-form ref="form" v-model="is_valid" @submit.prevent="registerStep3(data)" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld) </div>
                    <v-text-field autofocus v-model="data.last_name" label="Nachname *"
                        :rules="[required(), maxLength(255)]" />
                    <v-text-field v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />

                    <v-text-field label="Kennwort" :append-icon="is_password_visible ? 'mdi-eye' : 'mdi-eye-off'"
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
                <v-btn block color="success" slim flat rounded="0" @click="registerStep3(data)">Speichern</v-btn>
                <div class="text-caption text-center font-weight-light">oder</div>
                <v-btn block color="primary" slim flat rounded="0" variant="text"
                    @click="restartRegister">Zurück</v-btn>
            </v-card-text>

            <v-card-text v-if="step == 'REGISTER_MUST_BE_CONFIRMED' || step == 'REGISTER_FINISHED'">
                <v-alert closable color="success" type="info"
                    text="Gratulation! Sie wurden als Benutzer im System erfolgreich angelegt." />
                <div class="text-caption text-text" v-if="step == 'REGISTER_FINISHED'">Sie können sich jetzt am
                    System einloggen.</div>
                <div class="text-caption text-text" v-if="step == 'REGISTER_MUST_BE_CONFIRMED'">Bitte warten Sie noch
                    auf eine Bestätigung Ihrer Registrierung. Sie können dieses Fenster jetzt schließen
                </div>

                <v-btn block color="success" slim flat rounded="0" v-if="step == 'REGISTER_FINISHED'" @click="login">Zum
                    Login</v-btn>
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
        this.restartRegister();

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

        restartRegister() {
            this.data.password = null;
            this.data.password_repeat = null;
            this.data.token_2fa = null;
            this.step = 'REGISTER_ENTER_EMAIL';
        },

        register() {
            this.restartRegister();
        },

        async registerStep1(data) {
            this.is_valid = false;
            await this.$refs.form.validate(); if (!this.is_valid) return;
            data.step = 'REGISTER_ENTER_EMAIL';
            if (await this.adminStore.registerStep1(data)) this.step = 'REGISTER_ENTER_TOKEN';
        },

        async registerStep2(data) {
            if (this.data.token_2fa.length != 6) return;
            data.step = 'REGISTER_ENTER_TOKEN';
            if (await this.adminStore.registerStep2(data)) this.step = 'REGISTER_ENTER_FIELDS';
        },

        async registerStep3(data) {
            this.is_valid = false;
            await this.$refs.form.validate(); if (!this.is_valid) return;
            data.step = 'REGISTER_ENTER_FIELDS';
            if (await this.adminStore.registerStep3(data)) this.step = this.api_response.data.step;
        },

    },


}
</script>