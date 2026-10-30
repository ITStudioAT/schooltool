<template>
    <div>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-download" :loading="loading" :disabled="loading" @click.stop="open">{{ section }} (PDF)</v-btn>
        <div v-if="files.length > 1" class="d-flex flex-wrap ga-2 mt-2" aria-label="PDF herunterladen">
            <v-btn v-for="file in files" :key="file.id" size="small" variant="text" prepend-icon="mdi-download"
                :disabled="loading" @click.stop="downloadPdf(file)">{{ file.name }}</v-btn>
        </div>
        <p v-if="error" class="text-caption text-error mt-2" role="alert">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { folders } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { workDropboxKey } from '@/helpers/workDropbox'
import { useAdminStore } from '@/stores/admin/AdminStore'

const props = defineProps({ work: Object, date: { type: String, default: '' }, section: String })
const admin = useAdminStore()
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const loading = ref(false)
const error = ref('')
const files = ref([])
let sequence = 0
let controller = null
const params = computed(() => ({ contents: 1, exercise: 1, section: props.section, ...(props.date ? { date: props.date } : {}) }))

function close() {
    sequence++
    controller?.abort()
    loading.value = false
    files.value = []
    error.value = ''
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
        if (files.value.length === 1) await downloadPdf(files.value[0])
    } catch (failure) {
        const message = await failureMessage(failure)
        if (operation === sequence) error.value = message
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function downloadPdf(file) {
    const operation = sequence
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get(folders.url(props.work.id), { params: { ...params.value, pdf: 1, file_id: file.id },
            responseType: 'blob', signal: controller.signal, timeout: 120000 })
        if (operation !== sequence) return
        if (!(data instanceof Blob) || data.type !== 'application/pdf') throw new Error('Keine gültige PDF erhalten.')
        const url = URL.createObjectURL(data)
        const link = document.createElement('a')
        link.href = url
        link.download = file.name
        link.click()
        setTimeout(() => URL.revokeObjectURL(url), 60000)
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

