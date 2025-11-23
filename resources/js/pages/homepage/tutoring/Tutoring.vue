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
                <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2">
                    <its-menu-button title="Profil" subtitle="ändern" icon="mdi-account" color="button_primary_selected" @click="" />
                    <its-menu-button title="Kennwort" subtitle="ändern" icon="mdi-form-textbox-password" color="button_primary" @click="" />
                    <its-menu-button title="Mich" subtitle="abmelden" icon="mdi-logout" color="button_primary" @click="" />
                </v-card>
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
import { useTutoringStore } from '@/stores/homepage/TutoringStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.tutoringStore = useTutoringStore()
        await this.tutoringStore.loadAuth()
    },

    async mounted() {},

    unmounted() {},

    data() {
        return {
            tutoringStore: null,
        }
    },

    computed: {
        ...mapWritableState(useTutoringStore, ['auth']),
    },

    watch: {},

    methods: {},
}
</script>
