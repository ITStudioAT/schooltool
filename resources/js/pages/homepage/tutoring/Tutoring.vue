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
                        @click="action = 'password'" />
                    <its-menu-button
                        title="Mich"
                        subtitle="abmelden"
                        icon="mdi-logout"
                        :color="action == 'logout' ? 'button_primary_selected' : 'button_primary'"
                        @click="action = 'logout'" />
                </v-card>
            </v-card-text>

            <v-card-text v-if="action == 'profile'">
                <v-card tile flat color="tutoring_card" max-width="600">
                    <v-form ref="form" v-model="is_valid" @submit.prevent="updateProfile(data)">
                        <v-card-title class="bg-tutoring_card_title mb-2">Profil ändern</v-card-title>

                        <v-card-text>
                            <v-text-field autofocus flat rounded="0" v-model="data.last_name" label="Nachname" :rules="[required(), maxLength(255)]" />
                            <v-text-field flat rounded="0" v-model="data.first_name" label="Vorname" :rules="[maxLength(255)]" />
                            <v-text-field flat rounded="0" v-model="data.email" label="E-Mail" :rules="[required(), mail(), maxLength(255)]" />
                        </v-card-text>
                        <v-card-text v-if="error">
                            <v-alert type="error">{{ error?.response?.data?.message + ' (' + error?.response?.status + ')' }}</v-alert>
                        </v-card-text>
                        <v-card-actions>
                            <div class="d-flex flex-row align-center justify-space-between w-100">
                                <its-menu-button subtitle="Abbruch" icon="mdi-close" color="warning" @click="action = ''" />
                                <its-menu-button subtitle="Speichern" icon="mdi-content-save" color="success" @click="updateProfile(data)" />
                            </div>
                        </v-card-actions>
                    </v-form>
                </v-card>
            </v-card-text>

            <!-- DATA-->
            <v-card-text>
                <v-list>
                    <v-list-item v-for="(value, key) in data" :key="key">
                        <v-list-item-title>{{ key }}</v-list-item-title>
                        <v-list-item-subtitle>{{ value }}</v-list-item-subtitle>
                    </v-list-item>
                </v-list>
            </v-card-text>

            <!-- AUTH-->
            <v-card-text>
                <v-list>
                    <v-list-item v-for="(value, key) in auth" :key="key">
                        <v-list-item-title>{{ key }}</v-list-item-title>
                        <v-list-item-subtitle>{{ value }}</v-list-item-subtitle>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>
    </v-container>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useTutoringStore } from '@/stores/tutoring/TutoringStore'
import { useUserStore } from '@/stores/tutoring/UserStore'
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
        await this.tutoringStore.loadAuth()
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
            userStore: null,
            is_valid: false,
            data: {},
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth', 'action']),
        ...mapWritableState(useUserStore, ['error']),
    },

    watch: {},

    methods: {
        async updateProfile(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.userStore.update(data))) return
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
            }
            this.action = 'profile'
        },
    },
}
</script>
