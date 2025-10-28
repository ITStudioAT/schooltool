<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <v-row class="w-100" no-gutters>
            <v-col cols="12" md="6" lg="4" xl="3">
                <its-grid-box color="primary" title="Anmeldesystem" class="h-100 w-100">Hello Index</its-grid-box>
            </v-col>
        </v-row>
        <v-row>
            {{ config }}
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
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
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
    },

    methods: {},
}
</script>
