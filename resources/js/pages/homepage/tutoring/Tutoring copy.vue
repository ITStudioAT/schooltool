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
                    <div class="text-body-1">{{ auth.auth_user.last_name + ' ' + auth.auth_user.first_name + ', ' + auth?.auth_user?.schoolclass }}</div>
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
                </v-card>
            </v-card-text>

            <!-- MEINE ANGEBOTE -->
            <MyOffers v-if="action == '' || action == 'edit_offer'" />

            <!-- PROFIL -->
            <Profile v-if="action == 'profile'" />

            <!-- PASSWORD   -->
            <Password v-if="action == 'password'" />

            <!-- OFFER   -->
            <Offer v-if="action == 'create_offer'" />

            <!-- SUBJECTS -->
            <v-card-text>
                {{ action }}
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
import Profile from './components/Profile.vue'
import Password from './components/Password.vue'
import Offer from './components/Offer.vue'
import MyOffers from './components/MyOffers.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox, Profile, Password, Offer, MyOffers },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        this.userStore = useUserStore()
        this.subjectStore = useSubjectStore()
        await this.tutoringStore.loadAuth()
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
            await this.userStore.logout()
            await this.tutoringStore.loadAuth()
            this.action = ''
            this.$router.push('/homepage/tutoring_intro/?school=' + this.auth?.school_short_name)
        },
    },
}
</script>
