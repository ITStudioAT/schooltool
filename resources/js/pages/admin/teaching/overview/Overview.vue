<template>
    <!-- OVERVIEW-->
    <v-col cols="12" md="6" xl="4">OVERVIEW</v-col>

    <!-- STATISTIK -->
    <v-col cols="12" md="6" xl="4">STATS</v-col>
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
