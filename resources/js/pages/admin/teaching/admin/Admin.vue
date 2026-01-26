<template>
    <!-- Admin -->
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Import Sokrates 116" icon="mdi-import" class="w-100">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-alert type="info">
                        <div class="mb-4">Hier können die Schüler- und Elterndaten aus Sokrates-Bund übernommen werden.</div>
                        <div class="text-decoration-underline">Folgende Datei ist zu importieren:</div>
                        <div>Sokrates Bund ➜ Auswertungen ➜ Dynamische Suche ➜ Name der Abfrage: 116 ➜</div>
                        <div>Alle auswählen > Ausführen ➜ Exportieren (XLSX)</div>
                    </v-alert>

                    //TODO FILEUPLOAD
                </v-card>
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
