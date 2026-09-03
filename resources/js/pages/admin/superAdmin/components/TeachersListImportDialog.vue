<template>
    <v-dialog :model-value="modelValue" max-width="720" persistent @update:model-value="updateDialog">
        <v-card rounded="xl">
            <v-card-title class="d-flex align-center ga-2 pa-5 pb-2">
                <v-icon icon="mdi-database-import-outline" color="primary" />
                Lehrer:innen importieren
            </v-card-title>

            <v-card-text class="pa-5 pt-3">
                <div class="empty-state crud-form-section mb-4">
                    <div class="kpi-sub">
                        Es muss sich um eine Excel- oder CSV-Datei (*.xlsx, *.xls, *.csv) handeln. Benötigte Spalten:
                        <strong>Nachname/Familienname, Vorname, Email/EMail</strong>. Optional:
                        <strong>Kurz/Kürzel</strong>
                    </div>
                </div>

                <FileUpload
                    v-if="!is_upload_finished"
                    :path="teachersListUploadPath"
                    fileLabel
                    class="mt-2"
                    @uploadStart="importStarted"
                    @fileUploadFinished="fileUploadFinished"
                    @error="importUploadFailed" />

                <v-alert
                    v-if="is_import_running"
                    type="info"
                    variant="tonal"
                    rounded="lg"
                    title="Import läuft"
                    class="mt-4">
                    Die Lehrerliste wird importiert. Bitte warten Sie, bis die Verarbeitung abgeschlossen ist.
                    <v-progress-linear color="primary" indeterminate rounded height="6" class="mt-3" />
                </v-alert>

                <v-alert
                    v-else-if="is_import_finished"
                    :type="import_status === 200 ? 'success' : 'error'"
                    variant="tonal"
                    rounded="lg"
                    :title="import_status === 200 ? 'Import abgeschlossen' : 'Import fehlgeschlagen'"
                    class="mt-4">
                    {{ import_message }}
                </v-alert>
            </v-card-text>

            <v-card-actions class="pa-5 pt-0">
                <v-spacer />
                <v-btn
                    v-if="!is_import_finished"
                    color="warning"
                    variant="text"
                    rounded="lg"
                    :disabled="is_import_running"
                    @click="closeDialog">
                    Abbruch
                </v-btn>
                <v-btn
                    v-else
                    color="success"
                    variant="flat"
                    rounded="lg"
                    prepend-icon="mdi-check"
                    @click="finishImport">
                    Fertig
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import { teachersListApi } from '@/domains/teachersList/api'
import FileUpload from '@/pages/components/FileUpload.vue'
import { useTeachersListStore } from '@/stores/admin/TeachersListStore'

export default {
    components: { FileUpload },

    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['imported', 'update:modelValue'],

    mounted() {
        this.teachersListStore = useTeachersListStore()
        window.addEventListener('teachers-list-import-finished', this.handleImportFinished)
    },

    beforeUnmount() {
        window.removeEventListener('teachers-list-import-finished', this.handleImportFinished)
        this.stopImportStatusPolling()
    },

    data() {
        return {
            teachersListStore: null,
            is_upload_finished: false,
            is_import_running: false,
            is_import_finished: false,
            import_status: null,
            import_message: '',
            import_status_poll_timer: null,
            import_status_poll_in_flight: false,
        }
    },

    computed: {
        teachersListUploadPath() {
            return teachersListApi.upload()
        },
    },

    methods: {
        updateDialog(value) {
            if (!value) {
                this.closeDialog()
            }
        },

        closeDialog() {
            if (this.is_import_running) { return }

            this.resetImportState()
            this.$emit('update:modelValue', false)
        },

        importStarted() {
            this.stopImportStatusPolling()
            this.is_import_running = true
            this.is_import_finished = false
            this.import_status = null
            this.import_message = ''
        },

        fileUploadFinished() {
            this.is_upload_finished = true
            this.startImportStatusPolling()
        },

        importUploadFailed() {
            this.stopImportStatusPolling()
            this.is_import_running = false
            this.is_import_finished = false
        },

        async handleImportFinished(event) {
            await this.applyImportCompletion(event.detail || {})
        },

        startImportStatusPolling() {
            this.stopImportStatusPolling()

            if (!this.is_import_running) { return }

            this.pollImportStatus()
            this.import_status_poll_timer = window.setInterval(() => this.pollImportStatus(), 1000)
        },

        stopImportStatusPolling() {
            if (this.import_status_poll_timer !== null) {
                window.clearInterval(this.import_status_poll_timer)
            }

            this.import_status_poll_timer = null
            this.import_status_poll_in_flight = false
        },

        async pollImportStatus() {
            if (!this.is_import_running || this.import_status_poll_in_flight) { return }

            this.import_status_poll_in_flight = true

            try {
                const response = await axios.get(teachersListApi.importStatus())

                if (response.data?.state === 'finished') {
                    await this.applyImportCompletion(response.data)
                }
            } catch {
                return
            } finally {
                this.import_status_poll_in_flight = false
            }
        },

        async applyImportCompletion(payload) {
            if (!this.is_import_running) { return }

            this.stopImportStatusPolling()
            this.is_upload_finished = true
            this.is_import_running = false
            this.is_import_finished = true
            this.import_status = Number(payload.status)
            this.import_message = payload.message || 'Die Verarbeitung der Lehrerliste wurde abgeschlossen.'

            if (this.import_status === 200) {
                await this.teachersListStore.index()
                this.$emit('imported')
            }
        },

        finishImport() {
            this.closeDialog()
        },

        resetImportState() {
            this.stopImportStatusPolling()
            this.is_upload_finished = false
            this.is_import_running = false
            this.is_import_finished = false
            this.import_status = null
            this.import_message = ''
        },
    },
}
</script>

<style scoped src="../../../../../css/admin-index-page.css"></style>
<style scoped src="../../../../../css/admin-crud-panel.css"></style>
