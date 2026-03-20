<template>
    <v-container fluid class="h-100 w-100 d-flex align-center justify-center" v-if="config">
        <v-card class="mx-auto" width="300">
            <v-img height="80px" :src="'/storage/images/' + config.logo" @click="homepage" class="hover"></v-img>
            <v-card-subtitle class="text-caption text-text">
                {{ config.version }}
            </v-card-subtitle>
            <v-card-title class="bg-secondary">
                E-Mail-Verifikation
            </v-card-title>
            <v-card-title class="text-primary mb-4  ">
                {{ $route.query.email }}
            </v-card-title>
            <v-card-text v-if="!verification_step">
                <v-alert text="Wir prüfen jetzt Ihre E-Mail-Adresse. Bitte um kurze Geduld!" title="E-Mail-Verifikation"
                    type="info" variant="tonal" />
            </v-card-text>
            <v-card-text v-if="verification_step == 'VERIFICATION_SUCCESS'">
                <v-alert text="Die Überprüfung der E-Mail-Adresse war erfolgreich!" title="E-Mail-Verifikation"
                    type="success" variant="tonal" />
                <div class="my-2">Wenn Sie wollen, können Sie sich jetzt anmelden.</div>
                <v-btn block color="success" slim flat rounded="0" to="/admin/login">Zum Login</v-btn>
            </v-card-text>
            <v-card-text v-if="verification_step == 'ALREADY_VERIFIED'">
                <v-alert text="Ihre E-Mail-Adresse wurde bereits erfolgreich verifiziert!" title="E-Mail-Verifikation"
                    type="info" variant="tonal" />
                <div class="my-2">Wenn Sie wollen, können Sie sich jetzt anmelden.</div>
                <v-btn block color="success" slim flat rounded="0" to="/admin/login">Zum Login</v-btn>
            </v-card-text>
            <v-card-text v-if="verification_step == 'VERIFICATION_ERROR'">
                <v-alert text="Die Überprüfung der E-Mail-Adresse war nicht erfolgreich!" title="E-Mail-Verifikation"
                    type="error" variant="tonal" />
                <div class="my-2">Sie können den Vorgang wiederholen, wenn Sie auf folgenden Link klicken.</div>
                <v-btn block color="success" slim flat rounded="0"
                    @click="sendVerificationEmailInitializedFromUser($route.query.email)">Neue
                    Überprüfung</v-btn>
            </v-card-text>
            <v-card-text v-if="verification_step == 'EMAIL_SENT'">
                <v-alert text="Es wurde eine E-Mail zur Verifikation zugesandt. Bitte überprüfen Sie Ihre E-Mails."
                    title="E-Mail-Verifikation" type="info" variant="tonal" />
                <div class="my-2">Sie können dieses Fenster nun schließen!</div>
            </v-card-text>


        </v-card>

    </v-container>
</template>

<script>
import { mapWritableState } from "pinia";
import { useAdminStore } from "@/stores/admin/AdminStore";
import { useUserStore } from "@/stores/admin/UserStore";

export default {

    components: {},

    async beforeMount() {
        this.adminStore = useAdminStore();
        this.userStore = useUserStore();
        this.verification_step = null;
        const answer = await this.userStore.emailVerification(this.$route.query.email, this.$route.query.uuid);
        if (answer) this.verification_step = answer; else this.verification_step = "VERIFICATION_ERROR";


    },

    data() {
        return {
            adminStore: null,
            userStore: null,
            verification_step: null,
        };
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'is_loading', 'error', 'api_response', 'load_config']),

    },

    methods: {

        async sendVerificationEmailInitializedFromUser(email) {
            const answer = await this.userStore.sendVerificationEmailInitializedFromUser(email);
            if (answer) this.verification_step = answer;

        },

        homepage() {
            window.location.href = "/";
        }



    },


}
</script>