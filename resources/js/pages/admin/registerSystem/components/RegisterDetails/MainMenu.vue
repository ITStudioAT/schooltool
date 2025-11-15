<!-- Überblick über das Anmeldesystem -->
<template>
    <v-row class="w-100" dense>
        <v-col cols="12" md="6" xl="4">
            <its-grid-box color="primary" title="Menüauswahl" class="w-100">
                <v-card tile flat color="transparent" class="d-flex flex-row align-center ga-2" :disabled="action != ''">
                    <its-menu-button title="Übersicht" icon="mdi-home" :color="main_menu == '' ? 'primary' : 'secondary'" @click="main_menu = ''" />
                    <its-menu-button
                        title="Benutzer"
                        icon="mdi-account-multiple"
                        :color="main_menu == 'register_users' ? 'primary' : 'secondary'"
                        @click="main_menu = 'register_users'" />
                </v-card>
            </its-grid-box>
        </v-col>
    </v-row>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['main_menu', 'action']),
    },
    watch: {},

    methods: {},
}
</script>
