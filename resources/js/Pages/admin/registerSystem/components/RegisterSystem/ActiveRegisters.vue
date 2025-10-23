<template>
    <!-- Geöffnete Anmeldesysteme -->
    <v-row class="w-100">
        <v-col cols="12">
            <its-grid-box
                color="primary"
                :title="'Geöffnete Anmeldesysteme'"
                class="h-100 w-100"
                :disabled="action != ''">
                <div class="d-flex flex-wrap flex-row align-center ga-2" v-if="active_registers?.length > 0">
                    <its-menu-button
                        :title="register.name"
                        :subtitle="register.schoolyear_name"
                        :color="register.id == selected_active_register?.id ? 'success' : 'primary'"
                        @click="selected_active_register = register"
                        v-for="register in active_registers" />
                </div>
                <div class="text-body-1 font-weight-medium" v-if="active_registers?.length == 0">
                    Keine geöffneten Anmeldesysteme.
                </div>
                <template
                    v-slot:title
                    v-if="
                        config?.user?.roles.some((role) => ['super_admin', 'admin', 'register_admin'].includes(role))
                    ">
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <div class="mr-4">Geöffnete Anmeldesysteme</div>
                        <div class="d-flex flex-row align-center">
                            <div class="d-flex flex-row align-center" v-if="selected_active_register">
                                <v-btn
                                    flat
                                    tile
                                    @click="toggleRegister(selected_active_register)"
                                    icon
                                    color="transparent">
                                    <v-icon icon="mdi-power-standby" color="success"></v-icon>
                                </v-btn>
                            </div>
                        </div>
                    </div>
                </template>
            </its-grid-box>
        </v-col>
    </v-row>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        this.registerStore = useRegisterStore()
        if (this.active_registers.length == 0) this.loadActiveRegisters()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            registerStore: null,
            is_valid: false,
            data: {},
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, [
            'config',
            'selected_school',
            'selected_schoolyear',
            'selected_register',
            'action',
        ]),
        ...mapWritableState(useSchoolyearStore, ['selected_active_register']),
        ...mapWritableState(useRegisterStore, ['registers', 'active_registers']),
    },

    watch: {},

    methods: {
        async loadActiveRegisters() {
            this.registerStore.loadActiveRegisters()
        },

        async setActiveRegister(register) {
            await this.registerStore.setActiveRegister(register.id)
            this.selected_register = register
        },

        async toggleRegister(register) {
            await this.registerStore.toggleRegister(register)
            await this.registerStore.loadActiveRegisters()
            this.selected_active_register = null
        },
    },
}
</script>
