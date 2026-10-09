<template>
    <div class="border rounded pa-3 mb-4">
        <strong>Dropbox · Quick-Import</strong>
        <p class="text-caption mt-1">Liest den aktuellen synchronisierten Cloudstand. Vorschau und Übernahme erfolgen separat.</p>
        <p v-if="loading" role="status">Dropbox-Verbindung prüfen …</p>
        <p v-else-if="state">{{ state.message }}</p>
        <p v-if="state?.folder" class="mt-1">Zugeordneter Ordner: {{ state.folder.name }}</p>
        <div v-if="state?.configured && state?.installed" class="d-flex flex-wrap ga-2 mt-2">
            <v-btn v-if="!state.connected" variant="outlined" :disabled="busy || loading" @click="connectDropbox">Dropbox verbinden</v-btn>
            <template v-else>
                <v-btn variant="outlined" :disabled="busy || loading" @click="browseRoot">{{ state.folder ? 'Dropbox-Ordner ändern' : 'Dropbox-Ordner zuordnen' }}</v-btn>
                <v-btn v-if="state.folder" variant="tonal" :disabled="busy || loading" @click="$emit('preview')">Dropbox-Vorschau laden</v-btn>
                <v-btn variant="text" :disabled="busy || loading" @click="disconnectDropbox">Verbindung in Schooltool entfernen</v-btn>
            </template>
        </div>
        <v-alert v-if="error" type="error" variant="tonal" class="mt-2">{{ error }}</v-alert>
        <div v-if="browsing" class="mt-3">
            <p>Dropbox / {{ ancestors.map(folder => folder.name).join(' / ') }}</p>
            <div class="d-flex flex-wrap ga-2 mt-2">
                <v-btn v-if="ancestors.length" variant="outlined" :disabled="busy || loading" @click="browseParent">Übergeordneter Ordner</v-btn>
                <v-btn v-if="ancestors.length" color="primary" :disabled="busy || loading" @click="assignFolder">Diesen Ordner zuordnen</v-btn>
                <v-btn variant="text" :disabled="loading" @click="browsing = false">Ordnerauswahl schließen</v-btn>
            </div>
            <ul class="mt-2 ps-5">
                <li v-for="folder in folders" :key="folder.id"><v-btn variant="text" class="text-wrap" :disabled="busy || loading" @click="browseFolder(folder)">{{ folder.name }}</v-btn></li>
            </ul>
            <p v-if="!loading && !folders.length" class="mt-2">Keine weiteren Unterordner.</p>
            <v-btn v-if="cursor" variant="outlined" class="mt-2" :disabled="busy || loading" @click="loadFolders(true)">Weitere Ordner</v-btn>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { connect, disconnect, folders as listFolders, storeFolder } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { clearWorkDropboxStates, loadWorkDropbox, workDropboxKey, workDropboxStates } from '@/helpers/workDropbox'

const props = defineProps({ work: Object, active: Boolean, busy: Boolean })
const emit = defineEmits(['preview', 'changed'])
const admin = useAdminStore()
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const state = computed(() => workDropboxStates.get(key.value))
const loading = ref(false)
const error = ref('')
const browsing = ref(false)
const ancestors = ref([])
const folders = ref([])
const cursor = ref(null)
let requestSequence = 0

function errorText(exception) {
    return Object.values(exception.response?.data?.errors || {}).flat().join(' ') || exception.response?.data?.message || 'Dropbox ist momentan nicht verfügbar. Bitte den normalen Ordnerupload verwenden.'
}

async function perform(operation) {
    if (props.busy || loading.value || !key.value) return
    const selectedKey = key.value
    const sequence = ++requestSequence
    loading.value = true
    error.value = ''
    try {
        const result = await operation()
        if (props.active && key.value === selectedKey && sequence === requestSequence) return result
    } catch (exception) {
        if (key.value === selectedKey && sequence === requestSequence) error.value = errorText(exception)
    } finally {
        if (sequence === requestSequence) loading.value = false
    }
}

async function connectDropbox() {
    const response = await perform(() => axios.post(connect.url(props.work.id)))
    if (response) window.location.assign(response.data.url)
}

async function disconnectDropbox() {
    const response = await perform(() => axios.delete(disconnect.url(props.work.id)))
    if (response) {
        clearWorkDropboxStates()
        workDropboxStates.set(key.value, response.data)
        browsing.value = false
        emit('changed')
    }
}

async function loadFolders(more = false) {
    const parent = ancestors.value.at(-1)
    const response = await perform(() => axios.get(listFolders.url(props.work.id, { query: {
        ...(parent ? { folder_id: parent.id } : {}), ...(more && cursor.value ? { cursor: cursor.value } : {}),
    } })))
    if (response) {
        folders.value = more ? [...folders.value, ...response.data.folders] : response.data.folders
        cursor.value = response.data.cursor
    }
}

function browseRoot() {
    ancestors.value = []
    browsing.value = true
    loadFolders()
}

function browseFolder(folder) {
    ancestors.value = [...ancestors.value, folder]
    folders.value = []
    cursor.value = null
    loadFolders()
}

function browseParent() {
    ancestors.value = ancestors.value.slice(0, -1)
    folders.value = []
    cursor.value = null
    loadFolders()
}

async function assignFolder() {
    const response = await perform(() => axios.post(storeFolder.url(props.work.id), { folder_id: ancestors.value.at(-1).id }))
    if (response) {
        workDropboxStates.set(key.value, response.data)
        browsing.value = false
        emit('changed')
    }
}

watch(() => [props.active, key.value], async () => {
    requestSequence++
    loading.value = false
    error.value = ''
    browsing.value = false
    if (props.active && key.value) {
        await perform(() => loadWorkDropbox(key.value, props.work.id, true))
        const parameters = new URLSearchParams(window.location.search)
        if (parameters.get('dropbox_work') === String(props.work.id)) {
            if (parameters.get('dropbox') === 'failed') error.value = 'Dropbox-Verbindung fehlgeschlagen. Bitte App-Konfiguration und Leserechte prüfen und erneut verbinden.'
            if (parameters.get('dropbox') === 'cancelled') error.value = 'Dropbox-Verbindung wurde abgebrochen.'
            parameters.delete('dropbox')
            parameters.delete('dropbox_work')
            window.history.replaceState(window.history.state, '', `${window.location.pathname}?${parameters}${window.location.hash}`)
        }
    }
}, { immediate: true })
</script>
