<template>
    <!-- Schuljahre -->
    <v-col cols="12" md="6" xl="4" v-if="schoolyears.length > 0">
        <ItsGridBox color="primary" title="Schuljahr" icon="mdi-calendar" class="w-100" :disabled="is_loading">
            <div class="d-flex flex-row align-start">
                <div class="d-flex flex-wrap flex-row align-center ga-2">
                    <ItsMenuButton
                        :title="schoolyear.name"
                        :color="schoolyear.id == config.selected_schoolyear?.id ? 'success' : 'primary'"
                        @click="setActiveSchoolyear(schoolyear)"
                        v-for="schoolyear in schoolyears" />
                </div>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    components: { ItsGridBox, ItsMenuButton },

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
            is_loading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
    },

    watch: {},

    methods: {
        async setActiveSchoolyear(schoolyear) {
            this.is_loading = true
            await this.schoolyearStore.setActiveSchoolyear(schoolyear.id)
            await this.adminStore.loadConfig()
            this.selected_schoolyear = schoolyear
            this.is_loading = false
        },
    },
}
</script>
