<template>
    <!-- OVERVIEW-->
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Übersicht" icon="mdi-home" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100"></v-card>
            </div>
        </ItsGridBox>
    </v-col>

    <!-- STATISTIK -->
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Statistiken" icon="mdi-home" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100"></v-card>
            </div>
        </ItsGridBox>
    </v-col>
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
