<template>
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Anmeldungen anzeigen" class="w-100">
            <v-row>
                <v-col cols="12" class="d-flex flex-row align-center justify-space-between">
                    <v-btn color="warning" slim flat @click="abort">Abbruch</v-btn>
                    <v-btn color="success" slim flat type="submit">Weiter</v-btn>
                </v-col>
            </v-row>
        </its-grid-box>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useRegisterDateStore } from '@/stores/admin/RegisterDateStore'
import { useRegisterDateBookingStore } from '@/stores/admin/RegisterDateBookingStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerStore = useRegisterStore()
        this.registerDateStore = useRegisterDateStore()
        this.registerDateBookingStore = useRegisterDateBookingStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerStore: null,
            registerDateStore: null,
            registerDateBookingStore: null,
            is_valid: false,
            search_string: '',
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, [
            'config',
            'selected_school',
            'selected_schoolyear',
            'selected_register',
            'selected_active_register',
            'action',
        ]),
        ...mapWritableState(useRegisterStore, []),
        ...mapWritableState(useRegisterDateStore, []),
        ...mapWritableState(useRegisterDateBookingStore, []),
    },
    watch: {},

    methods: {
        abort() {
            this.action = ''
        },
    },
}
</script>
