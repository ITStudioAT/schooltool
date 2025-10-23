<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <ActiveRegisters />
        <Schoolyears />
        <Registers />
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Schoolyears from '@/pages/admin/components/schoolyears/Schoolyears.vue'
import Registers from '@/pages/admin/registerSystem/components/RegisterSystem/Registers.vue'
import ActiveRegisters from '@/pages/admin/registerSystem/components/RegisterSystem/ActiveRegisters.vue'

export default {
    components: { ItsMenuButton, ItsGridBox, Schoolyears, Registers, ActiveRegisters },

    async beforeMount() {},

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            registerStore: null,
        }
    },

    computed: {
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
        ...mapWritableState(useRegisterStore, ['registers', 'active_registers']),
    },

    methods: {},
}
</script>
