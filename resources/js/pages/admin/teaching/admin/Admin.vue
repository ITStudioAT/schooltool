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

                    <div class="text-caption">Es muss sich um eine Excel-Datei (*.xlsx) handeln.</div>

                    <div v-if="!is_upload_finished">
                        <FileUpload
                            path="/api/admin/teaching_upload/116"
                            fileLabel
                            :allowedFileTypes="['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']"
                            :refreshFilePond="refresh_file_pond"
                            @fileUploadFinished="fileUploadFinished"
                            @uploadStart="onUploadStart"
                            @error="uploadError"
                            class="mt-2" />
                    </div>

                    <v-alert type="info" class="mt-2" v-if="is_importing">
                        <div class="d-flex flex-row align-center ga-2">
                            <v-progress-circular indeterminate size="26" width="3" color="primary" />
                            <div>Datei hochgeladen. Die Verarbeitung läuft – Sie erhalten eine Meldung, sobald der Import abgeschlossen ist.</div>
                        </div>
                    </v-alert>
                    <v-alert type="error" class="mt-2" v-if="is_upload_error">Upload fehlgeschlagen.</v-alert>
                    <v-btn color="warning" flat tile class="mt-2" v-if="is_upload_finished || is_upload_error" @click="resetUpload">Neu hochladen</v-btn>
                </v-card>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import FileUpload from '@/pages/components/FileUpload.vue'

export default {
    components: { ItsGridBox, FileUpload },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    mounted() {
        this.onImportFinished = () => {
            this.is_importing = false
        }
        window.addEventListener('import116-finished', this.onImportFinished)
    },

    unmounted() {
        if (this.onImportFinished) {
            window.removeEventListener('import116-finished', this.onImportFinished)
        }
    },

    data() {
        return {
            adminStore: null,
            is_upload_finished: false,
            is_upload_error: false,
            refresh_file_pond: false,
            is_importing: false,
            onImportFinished: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
    },

    watch: {},

    methods: {
        onUploadStart() {
            this.is_upload_finished = false
            this.is_upload_error = false
            if (this.config?.is_auth) this.adminStore.initializeEcho()
            this.is_importing = true
        },
        fileUploadFinished() {
            this.is_upload_finished = true
        },
        uploadError() {
            this.is_upload_error = true
            this.refresh_file_pond = !this.refresh_file_pond
            this.is_importing = false
        },
        resetUpload() {
            this.is_upload_finished = false
            this.is_upload_error = false
            this.refresh_file_pond = !this.refresh_file_pond
            this.is_importing = false
        },
    },
}
</script>
