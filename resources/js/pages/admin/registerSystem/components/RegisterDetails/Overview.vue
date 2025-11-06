<!-- Überblick über das Anmeldesystem -->
<template>
    <v-row class="w-100" dense>
        <v-col cols="12">
            <its-grid-box color="primary" :title="selected_register?.name + ' ' + selected_schoolyear?.name" class="h-100 w-100" v-if="selected_register">
                <v-card tile flat color="transparent">
                    <v-card-text class="d-flex flex-row align-center text-body-1">
                        <v-icon icon="mdi-circle" :color="selected_register.is_active ? 'success' : 'error'" />
                        <div class="ml-2">Anmeldesystem {{ selected_register.is_active ? 'geöffnet' : 'geschlossen' }}</div>
                        <v-btn
                            flat
                            tile
                            @click="toggleRegister(selected_register)"
                            icon
                            :color="selected_register.is_active ? 'success' : 'error'"
                            variant="outlined"
                            class="ml-4"
                            :disabled="action != ''">
                            <v-icon icon="mdi-power-standby" :color="selected_register.is_active ? 'success' : 'error'"></v-icon>
                        </v-btn>
                    </v-card-text>
                    <v-card-text>
                        selected_register
                        {{ selected_register }}
                    </v-card-text>
                    <v-card-text class="mt-5">
                        selected_active_register
                        {{ active_registers }}
                    </v-card-text>
                </v-card>
            </its-grid-box>
        </v-col>
    </v-row>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'selected_active_register', 'action']),
        ...mapWritableState(useRegisterStore, ['active_registers']),
    },
    watch: {},

    methods: {
        async toggleRegister(register) {
            await this.registerStore.toggleRegister(register)
            await this.registerStore.loadActiveRegisters()
            this.selected_register.is_active = !this.selected_register.is_active
        },
    },
}
</script>
