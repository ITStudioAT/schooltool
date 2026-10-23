<template>
    <v-btn size="small" variant="tonal" prepend-icon="mdi-file-pdf-box" @click.stop="open">{{ section }} (PDF)</v-btn>
    <v-dialog v-if="opened" v-model="opened" persistent scrollable max-width="1100">
        <v-card>
            <v-card-title class="text-wrap">{{ filename || section }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <v-progress-linear v-if="loading" indeterminate aria-label="PDF wird geladen" />
                <v-alert v-if="error" type="error" variant="tonal" class="my-3" role="alert">{{ error }}</v-alert>
                <div v-if="files.length > 1" class="d-flex flex-wrap ga-2 my-3" aria-label="PDF auswählen">
                    <v-btn v-for="file in files" :key="file.id" size="small" variant="text" prepend-icon="mdi-file-pdf-box"
                        :disabled="loading" @click="readPdf(file)">{{ file.name }}</v-btn>
                </div>
                <CurriculumPdfPreview v-if="pdfUrl" :src="pdfUrl" :document-id="fileId" class="exercise-pdf-preview" />
                <v-btn v-if="error && !loading" variant="text" @click="open">Erneut laden</v-btn>
            </v-card-text>
            <v-card-actions><v-spacer /><v-btn @click="close">Schließen</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { folders } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { workDropboxKey } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'
import CurriculumPdfPreview from '../../curricula/CurriculumPdfPreview.vue'

const props = defineProps({ work: Object, date: { type: String, default: '' }, section: String })
const admin = useAdminStore()
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const opened = ref(false)
const loading = ref(false)
const error = ref('')
const files = ref([])
const pdfUrl = ref('')
const filename = ref('')
const fileId = ref('')
let sequence = 0
let controller = null
const params = computed(() => ({ contents: 1, exercise: 1, section: props.section, ...(props.date ? { date: props.date } : {}) }))

function resetPdf() {
    if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value)
    pdfUrl.value = ''
    filename.value = ''
    fileId.value = ''
}

function close() {
    sequence++
    controller?.abort()
    opened.value = false
    loading.value = false
    files.value = []
    resetPdf()
}

async function failureMessage(failure) {
    let data = failure.response?.data
    if (data instanceof Blob) {
        try { data = JSON.parse(await data.text()) } catch { data = null }
    }
    return Object.values(data?.errors || {}).flat()[0] || data?.message || failure.message || 'Die PDF konnte nicht geladen werden.'
}

async function open() {
    if (!key.value) return
    close()
    opened.value = true
    loading.value = true
    error.value = ''
    controller = new AbortController()
    const operation = sequence
    try {
        let cursor = null
        const entries = []
        for (let page = 0; page < 10; page++) {
            const { data } = await axios.get(folders.url(props.work.id), { params: { ...params.value, ...(cursor ? { cursor } : {}) },
                signal: controller.signal, timeout: 120000 })
            if (operation !== sequence) return
            entries.push(...data.entries)
            cursor = data.cursor
            if (!cursor) break
        }
        if (cursor) throw new Error('Die PDF-Liste konnte nicht vollständig geladen werden.')
        files.value = [...new Map(entries.filter(entry => entry.type === 'file' && /\.pdf$/i.test(entry.name))
            .map(entry => [entry.id, entry])).values()].sort((left, right) => left.name.localeCompare(right.name, 'de'))
        if (!files.value.length) throw new Error(`Keine PDF für ${props.section} vorhanden.`)
        if (files.value.length === 1) await readPdf(files.value[0])
    } catch (failure) {
        const message = await failureMessage(failure)
        if (operation === sequence) error.value = message
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function readPdf(file) {
    const operation = sequence
    loading.value = true
    error.value = ''
    resetPdf()
    try {
        const { data } = await axios.get(folders.url(props.work.id), { params: { ...params.value, pdf: 1, file_id: file.id },
            responseType: 'blob', signal: controller.signal, timeout: 120000 })
        if (operation !== sequence) return
        if (!(data instanceof Blob) || data.type !== 'application/pdf') throw new Error('Keine gültige PDF erhalten.')
        pdfUrl.value = URL.createObjectURL(data)
        filename.value = file.name
        fileId.value = file.id
    } catch (failure) {
        const message = await failureMessage(failure)
        if (operation === sequence) error.value = message
    } finally {
        if (operation === sequence) loading.value = false
    }
}

watch(() => [key.value, props.date, props.section], close)
onBeforeUnmount(close)
</script>

<style scoped>
.exercise-pdf-preview { height: 70vh; min-height: 320px; }
</style>
