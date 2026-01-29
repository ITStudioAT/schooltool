<template>
    <ItsGridBox color="primary" title="Meine Fächer" icon="mdi-invoice-list" class="w-100">
        <div class="d-flex flex-row align-start">
            <v-card tile flat color="transparent" class="w-100">
                {{ config.selected_schoolyear }}
            </v-card>
        </div>
    </ItsGridBox>
</template>
<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

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
