<template>
    <file-pond
        name="file"
        ref="pond"
        :label-idle="
            isFileLabel
                ? isShortLabel
                    ? 'Datei'
                    : '<strong>Ziehen Sie eine Datei hierher oder <i>klicken Sie hier.</i></strong>'
                : isShortLabel
                ? 'Bild'
                : '<strong>Ziehen Sie ein Bild hierher oder <i>klicken Sie hier.</i></strong>'
        "
        :allow-replace="true"
        :allow-multiple="allowMultipleSafe"
        :chunk-uploads="true"
        :chunk-force="true"
        :allow-file-type-validation="allowedFileTypesSafe.length > 0"
        :accepted-file-types="allowedFileTypesSafe"
        :allow-remove="false"
        :allow-revert="false"
        :label-file-processing-complete="isShortLabel ? 'OK' : 'Upload durchgeführt'"
        :files="upload_files"
        :server="{
            process: {
                url: path,
                method: 'POST',
                timeout: 60000,
                headers: { 'X-CSRF-TOKEN': csfr },
                onerror: onServerError,
            },
            patch: {
                url: path + '?patch=',
                method: 'PATCH',
                timeout: 60000,
                headers: { 'X-CSRF-TOKEN': csfr },
                onload: onPatchLoad,
                onerror: onServerError,
            },
            revert: null,
            restore: null,
            load: null,
            fetch: null,
        }"
        @processfilestart="onUploadStart"
        @processfiles="uploadFinished"
        @processfileerror="goError"
        @processfileabort="goError"
        @error="goError"
        @processfile="onProcessFile"
        v-if="csfr" />
</template>
<script>
// Import Vue FilePond
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'

// Import FilePond styles
import 'filepond/dist/filepond.min.css'

// Import FilePond plugins
// Please note that you need to install these plugins separately

// Import image preview plugin styles
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css'

// Import image preview and file type validation plugins
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import FilePondPluginImagePreview from 'filepond-plugin-image-preview'

// Create component
const FilePond = vueFilePond(FilePondPluginFileValidateType, FilePondPluginImagePreview)

export default {
    props: ['path', 'shortLabel', 'refreshFilePond', 'fileLabel', 'allowedFileTypes', 'allowMultiple'],
    emits: ['fileUploadFinished', 'error', 'uploadStart'],

    components: { FilePond },

    async beforeMount() {
        this.csfr = document.head.querySelector('meta[name="csrf-token"]').content
        var response = await axios.get('/api/admin/token', {})
        this.csfr = response.data
    },

    unmounted() {
        delete this.upload_files
        delete this.csfr
    },

    data() {
        return {
            upload_files: [],
            csfr: null,
            finalName: null,
            lastServerError: '',
        }
    },

    watch: {
        refreshFilePond() {
            this.upload_files = []
        },
    },

    computed: {
        isShortLabel() {
            return this.shortLabel !== undefined
        },

        isFileLabel() {
            return this.fileLabel !== undefined
        },

        allowedFileTypesSafe() {
            return this.allowedFileTypes || []
        },

        allowMultipleSafe() {
            return Boolean(this.allowMultiple)
        },
    },

    methods: {
        onUploadStart() {
            this.$emit('uploadStart')
        },

        onPatchLoad(res) {
            // res can be "logo.jpg" (string) or an XMLHttpRequest with responseText
            const text = typeof res === 'string' ? res : (res && res.responseText) || ''
            if (text && text !== 'OK') this.finalName = text // save only on final chunk
            return text // returning text is fine
        },

        onProcessFile(error, file) {
            if (error) return this.goError(error)
            this.$emit('fileUploadFinished', { id: file.serverId, name: this.finalName })
        },
        onServerError(response) {
            this.lastServerError = this.serverErrorMessage(response)

            return this.lastServerError || response
        },
        serverErrorMessage(error) {
            const response = error?.response?.data
                ?? error?.response?.body
                ?? error?.body
                ?? error?.responseText
                ?? error

            if (response && typeof response === 'object') {
                return typeof response.message === 'string' ? response.message : ''
            }

            if (typeof response !== 'string' || response.trim() === '') return ''

            try {
                const parsed = JSON.parse(response)

                return typeof parsed?.message === 'string' ? parsed.message : response
            } catch {
                return response
            }
        },
        goError(error) {
            const message = this.lastServerError || this.serverErrorMessage(error)
            this.lastServerError = ''
            this.$emit('error', message)
        },

        uploadFinished(response) {
            if (this.$refs.pond) {
                this.$refs.pond.removeFiles()
            }
        },
    },
}
</script>
