<template>
    <v-row class="w-100">
        <v-col cols="12">
            <its-grid-box color="primary" title="Schuljahre" class="h-100 w-100" :disabled="action != ''">
                <div class="d-flex flex-wrap flex-row align-center ga-2">
                    <its-menu-button
                        :title="schoolyear.name"
                        :color="schoolyear.id == selected_schoolyear?.id ? 'success' : 'primary'"
                        @click="setActiveSchoolyear(schoolyear)"
                        v-for="schoolyear in schoolyears" />
                </div>

                <template v-slot:title v-if="config?.user?.roles.some((role) => ['super_admin', 'admin'].includes(role))">
                    <div class="d-flex flex-row align-center justify-space-between w-100">
                        <div class="mr-4">Schuljahre</div>
                        <div class="d-flex flex-row align-center"></div>
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
        await this.schoolyearStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,

            is_valid: false,
            data: {},
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'action']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
    },

    methods: {
        abort() {
            this.action = ''
            this.data = {}
        },

        async setActiveSchoolyear(schoolyear) {
            await this.schoolyearStore.setActiveSchoolyear(schoolyear.id)
            await this.adminStore.loadConfig()
            this.selected_schoolyear = schoolyear
            this.selected_register = null
        },
    },
}
</script>
