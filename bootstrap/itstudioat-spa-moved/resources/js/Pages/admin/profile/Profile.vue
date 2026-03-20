<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <!-- Menüleiste oben -->
        <v-row class="d-flex flex-row ga-2 mb-2 mt-0 w-100" no-gutters>
            <!--
            <its-menu-button subtitle="Home" icon="mdi-home" color="secondary" to="/admin" />
            <its-menu-button subtitle="Kennwort ändern" icon="mdi-form-textbox-password" color="secondary"
                @click="wantToChangePassword" />
                -->
            <its-menu-button :title="item.title" :subtitle="item.subtitle" :icon="item.icon" :to="item.to"
                :color="item.color" @click="runAction(item.action)" v-for="(item, i) in navigationStore.menu" />
        </v-row>
        <v-row class="w-100" no-gutters>
            <v-col cols="12" sm="6" md="4" xl="3">
                <!-- PROFILDATEN ÄNDERN-->
                <its-grid-box color="primary" title="Benutzerprofil"
                    :subtitle="config.user.last_name + ' ' + config.user.first_name" icon="mdi-account"
                    v-if="step == ''">

                    <v-form ref="form" v-model="is_valid" @submit.prevent="save(data)" :disabled="!is_edit">
                        <v-text-field autofocus flat rounded="0" v-model="data.last_name" label="Nachname"
                            :rules="[required(), maxLength(255)]" />

                        <v-text-field flat rounded="0" v-model="data.first_name" label="Vorname"
                            :rules="[required(), maxLength(255)]" />

                        <v-text-field flat rounded="0" v-model="data.email" label="E-Mail"
                            :rules="[required(), mail(), maxLength(255)]" />

                        <v-switch true-icon="mdi-check" v-model="data.is_2fa" label="2-Faktoren-Authentifizierung"
                            hide-details color="success" :base-color="is_edit ? 'error' : ''" disabled />

                        <v-text-field flat rounded="0" v-model="data.email_2fa"
                            label="E-Mail 2-Faktoren-Authentifizierung" :rules="[required(), mail(), maxLength(255)]"
                            v-if="data.is_2fa" disabled />

                    </v-form>
                    <div v-if="!is_edit">
                        <v-btn block color="primary" slim flat rounded="0" @click="is_edit = true">Ändern</v-btn>
                    </div>
                    <v-row no-gutters v-if="is_edit">
                        <v-col cols=12 sm=6>
                            <v-btn block color="success" slim flat rounded="0" @click="save(data)"
                                type="submit">Speichern</v-btn>
                        </v-col>
                        <v-col cols=12 sm=6>
                            <v-btn block color="error" slim flat rounded="0" @click="abort">Abbruch</v-btn>
                        </v-col>
                    </v-row>

                </its-grid-box>

                <!-- CODE BESTÄTIGEN - BEI GEÄNDERTER E-MAIL -->
                <its-grid-box color="primary" title="Benutzerprofil"
                    :subtitle="config.user.last_name + ' ' + config.user.first_name" icon="mdi-account"
                    v-if="step == 'INPUT_CODE'">

                    <div class="text-body-1">
                        <div class="mb-2">Sie beabsichtigen Ihre E-Mail-Adresse zu ändern.</div>
                        <div>Bisher: {{ api_answer.email }}</div>
                        <div class="mb-2">Neu: {{ api_answer.email_new }}</div>
                    </div>

                    <v-form ref="form" v-model="is_valid" @submit.prevent="updateWithCode(data)">
                        <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails" />
                        <div class="text-caption text-text">Bitte den Code laut E-Mail eingeben</div>
                        <v-otp-input autofocus v-model="data.token_2fa" />

                        <v-row no-gutters v-if="step == 'INPUT_CODE'">
                            <v-col cols=12 sm=6>
                                <v-btn block color="success" slim flat rounded="0" @click="updateWithCode(data)"
                                    type="submit">Bestätigen</v-btn>
                            </v-col>
                            <v-col cols=12 sm=6>
                                <v-btn block color="error" slim flat rounded="0" @click="abort">Abbruch</v-btn>
                            </v-col>
                        </v-row>
                    </v-form>
                </its-grid-box>


                <!-- KENNWORT ÄNDERN-->
                <its-grid-box color="primary" title="Kennwort ändern"
                    :subtitle="config.user.last_name + ' ' + config.user.first_name" icon="mdi-account"
                    v-if="step == 'CHANGE_PASSWORD'">
                    <v-form ref="form" @submit.prevent="savePassword(data)" v-model="is_valid">
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

                    <v-row no-gutters v-if="step == 'CHANGE_PASSWORD'">
                        <v-col cols=12 sm=6>
                            <v-btn block color="success" slim flat rounded="0" @click="savePassword(data)"
                                type="submit">Weiter</v-btn>
                        </v-col>
                        <v-col cols=12 sm=6>
                            <v-btn block color="error" slim flat rounded="0" @click="abort">Abbruch</v-btn>
                        </v-col>
                    </v-row>
                </its-grid-box>

                <!-- KENNWORT ÄNDERN CODE ERFASSEN-->
                <its-grid-box color="primary" title="Kennwort ändern"
                    :subtitle="config.user.last_name + ' ' + config.user.first_name" icon="mdi-account"
                    v-if="step == 'PASSWORD_ENTER_TOKEN'">

                    <v-form ref="form" v-model="is_valid" @submit.prevent="savePasswordWithCode(data)" class="mb-4">
                        <v-alert closable color="success" type="info" text="Bitte prüfen Sie Ihre E-Mails" />
                        <div class="text-caption text-text">Bitte den Code laut E-Mail eingeben</div>
                        <v-otp-input autofocus v-model="data.token_2fa" />
                    </v-form>

                    <v-row no-gutters v-if="step == 'PASSWORD_ENTER_TOKEN'">
                        <v-col cols=12 sm=6>
                            <v-btn block color="success" slim flat rounded="0"
                                @click="savePasswordWithCode(data)">Weiter</v-btn>
                        </v-col>
                        <v-col cols=12 sm=6>
                            <v-btn block color="error" slim flat rounded="0" @click="abort">Abbruch</v-btn>
                        </v-col>
                    </v-row>
                </its-grid-box>

                <!-- 2FA ÄNDERN-->
                <its-grid-box color="primary" title="2-Faktoren-Authentifizierung"
                    :subtitle="config.user.last_name + ' ' + config.user.first_name" icon="mdi-account"
                    v-if="step == 'CHANGE_2FA'">
                    <v-form ref="form" @submit.prevent="save2Fa(data)" v-model="is_valid">
                        <v-switch true-icon="mdi-check" v-model="data.is_2fa" label="2-Faktoren-Authentifizierung"
                            hide-details color="success" :base-color="is_edit ? 'error' : ''" />

                        <v-text-field flat rounded="0" v-model="data.email_2fa"
                            label="E-Mail 2-Faktoren-Authentifizierung" :rules="[required(), mail(), maxLength(255)]"
                            v-if="data.is_2fa" />
                    </v-form>

                    <v-row no-gutters>
                        <v-col cols=12 sm=6>
                            <v-btn block color="success" slim flat rounded="0" @click="save2Fa(data)"
                                type="submit">Speichern</v-btn>
                        </v-col>
                        <v-col cols=12 sm=6>
                            <v-btn block color="error" slim flat rounded="0" @click="abort2Fa">Abbruch</v-btn>
                        </v-col>
                    </v-row>
                </its-grid-box>

                <!-- 2FA abgeschaltet-->
                <its-grid-box color="primary" title="2-Faktoren-Authentifizierung"
                    :subtitle="config.user.last_name + ' ' + config.user.first_name" icon="mdi-account"
                    v-if="step == 'TWO_FA_DELETE'">

                    <v-alert color="success" type="info" text="Die Zwei-Faktoren-Authentifizierung wurde ausgeschaltet!"
                        class="mb-2" />
                    <v-row no-gutters>
                        <v-col cols=12 sm=6>

                        </v-col>
                        <v-col cols=12 sm=6>
                            <v-btn block color="error" slim flat rounded="0" @click="abort2Fa">Fertig</v-btn>
                        </v-col>
                    </v-row>
                </its-grid-box>


                <!-- 2FA eingeschlatet, neue E-Mail: CODE ERFASSEN-->
                <its-grid-box color="primary" title="2-Faktoren-Authentifizierung"
                    :subtitle="config.user.last_name + ' ' + config.user.first_name" icon="mdi-account"
                    v-if="step == 'TWO_FA_EMAIL_IS_NEW' || step == 'TWO_FA_EMAIL_MUST_BE_VERIFIED'">

                    <v-form ref="form" v-model="is_valid" @submit.prevent="" class="mb-4">
                        <v-alert closable color="success" type="info"
                            :text="'Bitte prüfen Sie Ihre E-Mails: ' + data.email_2fa" />
                        <div class="text-caption text-text">Bitte den Code laut E-Mail eingeben</div>
                        <v-otp-input autofocus v-model="data.token_2fa" />
                    </v-form>

                    <v-row no-gutters>
                        <v-col cols=12 sm=6>
                            <v-btn block color="success" slim flat rounded="0"
                                @click="save2FaWithCode(data)">Weiter</v-btn>
                        </v-col>
                        <v-col cols=12 sm=6>
                            <v-btn block color="error" slim flat rounded="0" @click="abort2Fa">Abbruch</v-btn>
                        </v-col>
                    </v-row>
                </its-grid-box>

                <!-- 2FA set-->
                <its-grid-box color="primary" title="2-Faktoren-Authentifizierung"
                    :subtitle="config.user.last_name + ' ' + config.user.first_name" icon="mdi-account"
                    v-if="step == 'TWO_FA_SET' || step == 'TWO_FA_OK'">

                    <v-alert color="success" type="info" text="Die Zwei-Faktoren-Authentifizierung wurde eingeschaltet!"
                        class="mb-2" />
                    <v-row no-gutters>
                        <v-col cols=12 sm=6>

                        </v-col>
                        <v-col cols=12 sm=6>
                            <v-btn block color="error" slim flat rounded="0" @click="abort2Fa">Fertig</v-btn>
                        </v-col>
                    </v-row>
                </its-grid-box>


            </v-col>
        </v-row>
    </v-container>


</template>

<script>
import { useValidationRulesSetup } from "@/helpers/rules";
import { mapWritableState } from "pinia";
import { useAdminStore } from "@/stores/admin/AdminStore";
import { useUserStore } from "@/stores/admin/UserStore";
import { useNavigationStore } from "@/stores/admin/NavigationStore";
import ItsMenuButton from "@/pages/components/ItsMenuButton.vue";
import ItsGridBox from "@/pages/components/ItsGridBox.vue";

export default {

    setup() { return useValidationRulesSetup(); },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore(); this.adminStore.initialize(this.$router);
        this.userStore = useUserStore(); this.userStore.initialize(this.$router);

        this.navigationStore = useNavigationStore();
        await this.navigationStore.loadMenu('profile_menu');

        await this.userStore.show(this.config.user.id);
        if (this.item) this.data = JSON.parse(JSON.stringify(this.item));

    },

    unmounted() {
    },

    data() {
        return {
            adminStore: null,
            userStore: null,
            navigationStore: null,
            is_valid: false,
            data: {},
            is_edit: false,
            step: '',
            is_password_visible: false,
            is_password_visible_repeat: false,
        };
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'show_navigation_drawer', 'load_config']),
        ...mapWritableState(useUserStore, ['item', 'api_answer']),
    },

    methods: {

        abort() {
            this.is_edit = false;
            this.step = '';
            this.data = JSON.parse(JSON.stringify(this.item));
        },

        abort2Fa() {
            this.step = '';
            this.data = JSON.parse(JSON.stringify(this.item));
        },

        wantToChangePassword() {
            this.abort();
            this.data = {};
            this.step = 'CHANGE_PASSWORD'
        },

        wantToChange2Fa() {
            this.abort();
            this.step = 'CHANGE_2FA'
        },


        async save(data) {
            await this.$refs.form.validate(); if (!this.is_valid) return;

            this.userStore.api_answer = null;
            await this.userStore.updateProfile(data);
            this.is_edit = false;
            if (this.api_answer?.answer == 'INPUT_CODE') {
                this.step = 'INPUT_CODE';
            } else {
                this.data = JSON.parse(JSON.stringify(this.item));
                await this.adminStore.loadConfig();
                this.abort();
            }
        },
        async save2Fa(data) {
            await this.$refs.form.validate(); if (!this.is_valid) return;
            const result = await this.userStore.save2Fa(data);
            if (result) {
                this.step = result;
            }
        },

        async save2FaWithCode(data) {
            await this.$refs.form.validate(); if (!this.is_valid) return;
            const result = await this.userStore.save2FaWithCode(data);
            if (result) {
                this.step = result;
            }
        },

        async updateWithCode(data) {
            if (this.data.token_2fa.length != 6) return;
            if (await this.userStore.updateWithCode(data)) {
                await this.adminStore.loadConfig();
                this.abort();
            }
        },

        async savePassword(data) {
            await this.$refs.form.validate(); if (!this.is_valid) return;
            const answer = await this.userStore.savePassword(data);
            if (answer) {
                this.step = answer;
            } else {
                this.abort();
            }
        },

        async savePasswordWithCode(data) {
            if (this.data.token_2fa.length != 6) return;
            if (await this.userStore.savePasswordWithCode(data)) {
                this.abort();
            }

        },

        runAction(methodName) {
            if (typeof this[methodName] === 'function') {
                this[methodName]();
            }
        }


    }

}
</script>
