<template>
    <!-- SEARCH -->
    <v-col cols="12" md="6" xl="4">Search</v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    components: {},

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
    },

    watch: {},

    methods: {},
}
</script>
