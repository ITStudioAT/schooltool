<template>
    <div class="schooltool-background"></div>
    <!-- Überschrift SCHOOLTOOL / NACHHILFETOOL-->
    <div v-if="auth">
        <v-card flat tile class="mt-4">
            <div class="d-flex flex-row justify-center">
                <div class="text-h6 text-md-h5 text-lg-h4 text-xl-h2">
                    <span class="text-secondary">NACH</span>
                    <span class="text-third font-weight-bold">HILFE</span>
                    <span class="text-secondary">TOOL</span>
                </div>
            </div>
        </v-card>

        <v-card flat tile color="transparent" class="mt-4">
            <div class="d-flex justify-center">
                <div class="d-flex flex-column align-center">
                    <div class="text-caption">{{ auth?.school_long_name }}</div>
                    <div style="width: 96px; height: 48px" class="bg-primary-lighten-4">
                        <img :src="'/storage/images/' + auth.school_logo" alt="Logo" style="width: 100%; height: 100%; object-fit: contain" />
                    </div>
                </div>
            </div>
        </v-card>
    </div>
    <div class="h-100 w-100 d-flex flex-column mt-4" style="max-width: 1024px; margin: auto" v-if="auth">
        <v-card flat tile :disabled="action != ''">
            <v-card-title>
                {{ auth.auth_user.last_name + ' ' + auth.auth_user.first_name + ', ' + auth.auth_user.schoolclass }}
            </v-card-title>

            <!-- BENUTZER-MENÜ -->
            <v-card-text tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2" :disabled="action != ''">
                <ItsMenuButton
                    title="Profil"
                    subtitle="ändern"
                    icon="mdi-account"
                    :color="action == 'profile' ? 'button_primary_selected' : 'button_primary'"
                    @click="editProfile" />
                <ItsMenuButton
                    title="Kennwort"
                    subtitle="ändern"
                    icon="mdi-form-textbox-password"
                    :color="action == 'password' ? 'button_primary_selected' : 'button_primary'"
                    @click="editPassword" />
            </v-card-text>
        </v-card>

        <!-- Menü -->
        <v-card tile flat color="transparent" class="mt-4" :disabled="action != ''">
            <v-card flat color="primary" class="border-md">
                <div class="d-flex flex-wrap justify-center justify-lg-start ga-4">
                    <ItsCard
                        title="Zurück zur Übersicht"
                        text="Hier gelangst Du wieder zurück zur Übersicht."
                        color="success"
                        button="Zur Übersicht"
                        @clickCard="moveToTutoringOverview" />

                    <ItsCard title="Neue Nachhilfe" text="Hier kannst Du ein neues Nachhilfe-Angebot erstellen." color="success" button="Los" @clickCard="createOffer" />

                    <ItsCard title="Mich abmelden" text="Hier kannst Du Dich vom System ausloggen." color="warning" button="Abmelden" @clickCard="logout" />
                </div>
            </v-card>
        </v-card>

        <v-card tile flat class="bg-tutoring_background-lighten-1 w-100" max-width="1024" v-if="auth">
            <!-- MEINE ANGEBOTE -->
            <MyOffers v-if="action == '' || action == 'edit_offer'" />

            <!-- PROFIL -->
            <Profile v-if="action == 'profile'" />

            <!-- PASSWORD   -->
            <Password v-if="action == 'password'" />

            <!-- OFFER   -->
            <Offer v-if="action == 'create_offer'" />

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
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
import { useSubjectStore } from '@/stores/tutoring/SubjectStore'
import { useOfferStore } from '@/stores/tutoring/OfferStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Profile from './components/Profile.vue'
import Password from './components/Password.vue'
import Offer from './components/Offer.vue'
import MyOffers from './components/MyOffers.vue'
import ItsCard from '@/pages/components/ItsCard.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox, Profile, Password, Offer, MyOffers, ItsCard },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        this.subjectStore = useSubjectStore()
        this.offerStore = useOfferStore()
        await this.tutoringStore.loadAuth()
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            offerStore: null,
            subjectStore: null,
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
        ...mapWritableState(useOfferStore, []),
    },

    watch: {},

    methods: {
        moveToTutoringOverview() {
            this.$router.push('/homepage/tutoring_overview/?school=' + this.auth?.school_short_name)
        },

        createOffer() {
            this.action = 'create_offer'
        },

        editProfile() {
            this.action = 'profile'
        },

        editPassword() {
            this.action = 'password'
        },

        async logout() {
            const school = this.auth?.school_short_name
            await this.userStore.logout()
            // await this.tutoringStore.loadAuth()
            this.action = ''
            this.$router.push('/homepage/tutoring_overview/?school=' + school)
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
</style>
