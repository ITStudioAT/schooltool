<template>
    <v-col cols="12" md="4" xl="3">
        <its-grid-box color="primary" title="Aktive Schule" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-title>{{ config.selected_school.long_name }}</v-card-title>
                    <v-card-subtitle>{{ config.selected_school.short_name }}</v-card-subtitle>
                    <v-card-text>{{ '✉️ ' + config.selected_school.email }}</v-card-text>
                    <v-card-text class="d-flex flex-row align-center">
                        <its-menu-button
                            title="Schule"
                            subtitle="wechseln"
                            icon="mdi-swap-horizontal"
                            color="primary" />
                    </v-card-text>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>
</template>
<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

import { useSchoolStore } from '@/stores/admin/SchoolStore'

export default {
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useSchoolStore, ['selected_school']),
    },

    methods: {},
}
</script>
