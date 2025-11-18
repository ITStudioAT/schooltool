<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <h1>TUTORING</h1>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            registerStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['selected_schoolyear', 'main_menu']),
    },

    methods: {},
}
</script>
