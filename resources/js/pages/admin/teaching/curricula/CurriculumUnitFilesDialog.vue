<template>
    <v-dialog
        :model-value="modelValue"
        persistent
        max-width="760"
        @update:model-value="onDialogModelUpdate">
        <v-card rounded="xl" class="curriculum-unit-files-dialog">
            <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                <v-icon color="primary" size="21">mdi-file-plus-outline</v-icon>
                Material hinzufügen
            </v-card-title>

            <v-card-text class="px-4 pt-2 pb-2">
                <div class="text-body-2 mb-4">
                    Dateien für <strong>{{ unitTitle }}</strong>
                </div>

                <file-pond
                    v-if="modelValue"
                    ref="pond"
                    :key="pondKey"
                    name="files[]"
                    allow-multiple
                    :instant-upload="false"
                    :allow-revert="false"
                    :credits="false"
                    :disabled="isBusy"
                    :label-idle="'<strong>Dateien hierher ziehen oder <i>klicken</i></strong>'"
                    @updatefiles="handleFilesUpdate" />

                <div class="text-caption text-medium-emphasis mt-2">
                    Maximal 20 Dateien, je Datei höchstens 100 MB.
                </div>

                <v-alert
                    v-if="errorMessage"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="mt-3">
                    {{ errorMessage }}
                </v-alert>

                <v-divider class="my-4" />

                <div class="d-flex align-center mb-2">
                    <div class="text-subtitle-2">Gespeicherte Dateien</div>
                    <v-spacer />
                    <v-progress-circular v-if="isLoading" indeterminate color="primary" size="20" />
                </div>

                <v-list v-if="files.length" bg-color="transparent" density="compact" class="pa-0">
                    <v-list-item
                        v-for="file in files"
                        :key="file.id"
                        class="curriculum-unit-files-dialog__file mb-1 px-3"
                        rounded="lg">
                        <template #prepend>
                            <v-icon size="19" color="primary" class="mr-2">mdi-file-document-outline</v-icon>
                        </template>
                        <v-list-item-title class="text-body-2">{{ file.name }}</v-list-item-title>
                        <v-list-item-subtitle class="text-caption">
                            {{ fileMeta(file) }}
                        </v-list-item-subtitle>
                        <template #append>
                            <v-btn
                                v-if="file.preview_url"
                                icon="mdi-eye-outline"
                                variant="text"
                                size="small"
                                color="primary"
                                title="Vorschau"
                                :href="file.preview_url"
                                target="_blank" />
                            <v-btn
                                icon="mdi-download-outline"
                                variant="text"
                                size="small"
                                color="primary"
                                title="Herunterladen"
                                :href="file.download_url"
                                target="_blank" />
                            <v-btn
                                icon="mdi-delete-outline"
                                variant="text"
                                size="small"
                                color="error"
                                title="Datei löschen"
                                :disabled="isBusy"
                                @click="fileToDelete = file" />
                        </template>
                    </v-list-item>
                </v-list>

                <div v-else-if="!isLoading" class="text-center text-caption text-medium-emphasis py-4">
                    Noch keine Dateien gespeichert.
                </div>
            </v-card-text>

            <v-card-actions class="px-4 pb-4">
                <v-spacer />
                <v-btn variant="text" color="secondary" :disabled="isBusy" @click="closeDialog">
                    Schließen
                </v-btn>
                <v-btn
                    variant="flat"
                    color="primary"
                    prepend-icon="mdi-content-save-outline"
                    :loading="isUploading"
                    :disabled="pendingFiles.length === 0 || isBusy"
                    @click="saveFiles">
                    Dateien speichern
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog :model-value="Boolean(fileToDelete)" persistent max-width="440">
        <v-card rounded="xl">
            <v-card-title class="text-subtitle-1">Datei löschen?</v-card-title>
            <v-card-text>
                {{ fileToDelete?.name }} wird dauerhaft aus dieser Einheit entfernt.
            </v-card-text>
            <v-card-actions class="px-4 pb-4">
                <v-spacer />
                <v-btn variant="text" :disabled="isDeleting" @click="fileToDelete = null">Abbrechen</v-btn>
                <v-btn color="error" variant="flat" :loading="isDeleting" @click="deleteFile">
                    Löschen
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'

const FilePond = vueFilePond()

export default {
    name: 'CurriculumUnitFilesDialog',
    components: { FilePond },
    props: {
        modelValue: { type: Boolean, default: false },
        curriculumId: { type: [Number, String], required: true },
        topicId: { type: [Number, String], default: null },
        unitId: { type: [Number, String], default: null },
        unitTitle: { type: String, default: '' },
    },
    emits: ['update:modelValue', 'changed'],
    data() {
        return {
            files: [],
            pendingFiles: [],
            isLoading: false,
            isUploading: false,
            isDeleting: false,
            errorMessage: '',
            fileToDelete: null,
            pondKey: 0,
        }
    },
    computed: {
        isBusy() {
            return this.isLoading || this.isUploading || this.isDeleting
        },
        endpoint() {
            if (!this.curriculumId || !this.topicId || !this.unitId) return null

            return `/api/admin/teaching/curricula/${this.curriculumId}/topics/`
                + `${encodeURIComponent(this.topicId)}/units/${encodeURIComponent(this.unitId)}/files`
        },
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(isOpen) {
                if (isOpen) {
                    this.resetSelection()
                    this.loadFiles()
                }
            },
        },
    },
    methods: {
        onDialogModelUpdate(value) {
            if (!value) this.closeDialog()
        },
        closeDialog() {
            if (this.isBusy) return

            this.fileToDelete = null
            this.errorMessage = ''
            this.resetSelection()
            this.$emit('update:modelValue', false)
        },
        handleFilesUpdate(fileItems) {
            this.pendingFiles = (Array.isArray(fileItems) ? fileItems : [])
                .map((fileItem) => fileItem?.file)
                .filter((file) => file instanceof File)
        },
        resetSelection() {
            this.pendingFiles = []
            this.pondKey += 1
        },
        async loadFiles() {
            if (!this.endpoint) return

            this.isLoading = true
            this.errorMessage = ''

            try {
                const response = await axios.get(this.endpoint)
                this.files = Array.isArray(response?.data?.data) ? response.data.data : []
            } catch (error) {
                this.files = []
                this.errorMessage = this.requestError(error, 'Die Dateien konnten nicht geladen werden.')
            } finally {
                this.isLoading = false
            }
        },
        async saveFiles() {
            if (!this.endpoint || this.pendingFiles.length === 0 || this.isBusy) return

            this.isUploading = true
            this.errorMessage = ''

            const formData = new FormData()
            this.pendingFiles.forEach((file) => formData.append('files[]', file, file.name))

            try {
                await axios.post(this.endpoint, formData)
                this.resetSelection()
                await this.loadFiles()
                this.$emit('changed', this.files)
            } catch (error) {
                this.errorMessage = this.requestError(error, 'Die Dateien konnten nicht gespeichert werden.')
            } finally {
                this.isUploading = false
            }
        },
        async deleteFile() {
            if (!this.endpoint || !this.fileToDelete || this.isDeleting) return

            this.isDeleting = true
            this.errorMessage = ''

            try {
                await axios.delete(`${this.endpoint}/${this.fileToDelete.id}`)
                this.files = this.files.filter((file) => file.id !== this.fileToDelete.id)
                this.fileToDelete = null
                this.$emit('changed', this.files)
            } catch (error) {
                this.errorMessage = this.requestError(error, 'Die Datei konnte nicht gelöscht werden.')
            } finally {
                this.isDeleting = false
            }
        },
        fileMeta(file) {
            return file?.size_bytes ? this.formatBytes(file.size_bytes) : 'Datei'
        },
        formatBytes(value) {
            const bytes = Number(value)
            if (!Number.isFinite(bytes) || bytes <= 0) return '0 B'

            const units = ['B', 'KB', 'MB', 'GB']
            const unitIndex = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
            const amount = bytes / (1024 ** unitIndex)

            return `${amount >= 10 || unitIndex === 0 ? Math.round(amount) : amount.toFixed(1)} ${units[unitIndex]}`
        },
        requestError(error, fallback) {
            const validationErrors = error?.response?.data?.errors
            const firstValidationError = validationErrors && Object.values(validationErrors).flat()[0]

            return firstValidationError || error?.response?.data?.message || fallback
        },
    },
}
</script>

<style scoped>
.curriculum-unit-files-dialog__file {
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(148, 163, 184, 0.06);
}

:deep(.filepond--root) {
    margin-bottom: 0;
}
</style>
