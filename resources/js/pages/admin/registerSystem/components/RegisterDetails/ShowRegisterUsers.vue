<template>
    <v-col cols="12" md="6" xl="4">
        <its-grid-box color="primary" title="Benutzer anzeigen" class="w-100">
            <v-card tile flat color="primary">
                <v-card-text class="d-flex flex-row flex-wrap align-center ga-2">
                    <its-menu-button title="Zurück" subtitle="Übersicht" icon="mdi-arrow-left" color="secondary" @click="action = ''" />
                </v-card-text>

                <v-card-text>
                    {{ selected_register }}
                </v-card-text>

                <v-card-text>
                    {{ users }}
                </v-card-text>

                <v-card-text>
                    {{ meta }}
                </v-card-text>
            </v-card>
        </its-grid-box>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterUserStore } from '@/stores/admin/RegisterUserStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerUserStore = useRegisterUserStore()
        await this.registerUserStore.index(this.selected_register?.id)
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerUserStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterUserStore, ['users', 'meta', 'search_string']),
    },
    watch: {},

    methods: {},
}
</script>
