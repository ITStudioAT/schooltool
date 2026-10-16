<template>
    <div class="border rounded pa-2 mb-2 work-dropbox-source">
        <strong class="text-body-2">Dropbox · Quick-Import</strong>
        <div v-if="boundFolder" class="mt-2" data-testid="dropbox-bound-folder">
            <p class="text-body-2"><strong>{{ boundFolder.name }}</strong></p>
            <p class="text-caption work-dropbox-bound-path" :role="folderPathLoading ? 'status' : undefined">{{ boundFolder.path || (folderPathLoading || resolving ? 'Vollständigen Pfad laden …' : 'Vollständiger Pfad nicht verfügbar.') }}</p>
            <template v-if="!boundFolder.path && !folderPathLoading && !resolving">
                <p v-if="folderPathError" role="alert" class="text-caption">{{ folderPathError }}</p>
                <v-btn size="small" variant="text" @click="ensureFolderPath(undefined, true)">Pfad erneut laden</v-btn>
            </template>
        </div>
        <div v-if="resolving" role="status" aria-live="polite" class="d-flex align-center ga-2 mt-3" data-testid="dropbox-folder-search">
            <v-progress-circular indeterminate size="18" width="2" aria-hidden="true" />
            <span class="text-body-2">{{ state?.folder ? 'Importordner öffnen …' : 'Passenden Importordner suchen …' }}</span>
            <v-btn size="small" variant="text" @click="cancelResolution">Abbrechen</v-btn>
        </div>
        <template v-else>
            <p v-if="loading" role="status" class="text-caption">{{ listing ? 'Unterordner laden …' : 'Dropbox-Verbindung prüfen …' }}</p>
            <p v-else-if="state && !state.can_quick_import" class="text-caption">{{ state.message }}</p>
            <v-alert v-if="state?.folder_plausibility?.status === 'mismatch'" type="error" variant="tonal" icon="mdi-alert-circle" role="alert" class="mt-2 work-folder-mismatch">{{ state.folder_plausibility.message }}</v-alert>
            <p v-else-if="state?.folder_plausibility?.status === 'unknown'" role="status" class="text-caption mt-2">{{ state.folder_plausibility.message }}</p>
            <div v-if="state?.configured && state?.installed" class="d-flex flex-wrap align-center ga-2 mt-2">
                <v-btn v-if="!state.connected" size="small" variant="outlined" :disabled="busy || loading" @click="connectDropbox">Dropbox verbinden</v-btn>
                <template v-else>
                    <v-btn v-if="state.folder" size="small" variant="tonal" :disabled="busy || loading || state.can_preview === false" @click="$emit('preview')">Dropbox-Vorschau laden</v-btn>
                    <v-btn size="small" variant="text" :disabled="busy || loading" @click="browseSuggested">{{ state.folder ? 'Dropbox-Ordner ändern' : 'Ordner manuell wählen' }}</v-btn>
                    <details class="text-caption"><summary>Verbindung verwalten</summary>
                        <v-btn size="small" variant="text" :disabled="busy || loading" @click="disconnectDropbox">Verbindung in Schooltool entfernen</v-btn>
                    </details>
                </template>
            </div>
            <v-alert v-if="error" type="error" variant="tonal" class="mt-2">{{ error }}</v-alert>
            <v-btn v-if="!state && error" size="small" variant="text" @click="initializeSource">Erneut prüfen</v-btn>
            <template v-if="!browsing">
                <p v-if="suggestionMessage" role="status" class="text-caption mt-2">{{ suggestionMessage }}</p>
                <div v-if="suggestedFolder" class="mt-2" data-testid="dropbox-folder-proposal">
                    <p class="text-body-2"><strong>Vorschlag: {{ suggestedFolder.name }}</strong></p>
                    <p class="text-caption work-dropbox-suggestion-path">{{ suggestedFolder.path || 'Vollständiger Pfad nicht verfügbar.' }}</p>
                    <p class="text-caption">Noch nicht zugeordnet.</p>
                    <v-btn v-if="!state?.folder && directSuggestion?.id === suggestedFolder.id && suggestedFolder.path?.startsWith('/')" size="small" color="primary" :disabled="busy || loading"
                        @click="assignFolder(suggestedFolder, true)">Vorschlag zuordnen und Vorschau laden</v-btn>
                </div>
            </template>
            <div v-if="suggestions.length && (!suggestedFolder || suggestions.length > 1)" class="mt-2">
                <strong class="text-body-2">Mögliche Ordner · Vorschläge</strong>
                <ul class="ps-5">
                    <li v-for="folder in suggestions" :key="folder.id">
                        <v-btn variant="text" class="text-wrap" :disabled="busy || loading" @click="browseSuggestion(folder)">{{ folder.name }}</v-btn>
                        <p class="text-caption">{{ folder.path || 'Vollständiger Pfad nicht verfügbar.' }}</p>
                        <p v-if="folder.reason" class="text-caption">{{ folder.reason }}</p>
                    </li>
                </ul>
            </div>
            <div v-if="browsing" class="mt-3">
                <p v-if="suggestionMessage" role="status" class="text-caption">{{ suggestionMessage }}</p>
                <p v-if="suggestedFolder && suggestedFolder.id === ancestors.at(-1)?.id" class="text-caption">Vorschlag: {{ suggestedFolder.name }}. Bitte prüfen und ausdrücklich zuordnen.</p>
                <p class="work-dropbox-path">{{ folderLocation }}</p>
                <div class="d-flex flex-wrap ga-2 mt-2">
                    <v-btn v-if="ancestors.length" variant="text" :disabled="busy || loading" @click="browseRoot">Dropbox-Hauptordner</v-btn>
                    <v-btn v-if="ancestors.length" variant="outlined" :disabled="busy || loading" @click="browseParent">Übergeordneter Ordner</v-btn>
                    <v-btn v-if="ancestors.length" color="primary" :disabled="busy || loading" @click="assignFolder()">Diesen Ordner zuordnen</v-btn>
                    <v-btn variant="text" @click="cancelResolution">Ordnerauswahl schließen</v-btn>
                    <v-btn v-if="error && !loading" variant="outlined" @click="loadFolders()">Ordner erneut laden</v-btn>
                </div>
                <ul class="mt-2 ps-5">
                    <li v-for="folder in folders" :key="folder.id"><v-btn variant="text" class="text-wrap" :disabled="busy || loading" @click="browseFolder(folder)">{{ folder.name }}</v-btn></li>
                </ul>
                <p v-if="!loading && !folders.length" class="mt-2">Keine weiteren Unterordner.</p>
                <v-btn v-if="cursor" variant="outlined" class="mt-2" :disabled="busy || loading" @click="loadFolders(true)">Weitere Ordner</v-btn>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { connect, disconnect, folders as listFolders, show, storeFolder } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { clearWorkDropboxStates, loadWorkDropbox, loadWorkDropboxSuggestions, workDropboxKey, workDropboxStates } from '@/helpers/workDropbox'

const props = defineProps({ work: Object, active: Boolean, busy: Boolean })
const emit = defineEmits(['preview', 'changed'])
const admin = useAdminStore()
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const state = computed(() => workDropboxStates.get(key.value))
const resolvedFolder = ref(null)
const folderPathLoading = ref(false)
const folderPathError = ref('')
const boundFolder = computed(() => {
    const folder = state.value?.folder
    if (!folder) return null
    return { ...folder, path: validFolderPath(folder.path) ? folder.path
        : resolvedFolder.value?.id === folder.id ? resolvedFolder.value.path : null }
})
let folderPathRequest = null
let folderPathAttempt = null
const loading = ref(false)
const error = ref('')
const browsing = ref(false)
const ancestors = ref([])
const folders = ref([])
const cursor = ref(null)
const suggestions = ref([])
const suggestedFolder = ref(null)
const directSuggestion = ref(null)
const suggestionMessage = ref('')
const suggestionStart = ref(null)
const resolving = ref(false)
const listing = ref(false)
let resolutionRequest = null
const folderLocation = computed(() => {
    const folder = ancestors.value.at(-1)
    if (!folder) return 'Dropbox /'
    if (typeof folder.path === 'string' && folder.path.startsWith('/')) {
        return 'Dropbox / ' + folder.path.split('/').filter(Boolean).join(' / ')
    }
    return `Dropbox / ${folder.name} · Vollständiger Pfad nicht verfügbar.`
})
let requestSequence = 0

function errorText(exception) {
    if (['ECONNABORTED', 'ETIMEDOUT'].includes(exception.code)) return 'Die Dropbox-Anfrage hat zu lange gedauert. Bitte erneut versuchen.'
    return Object.values(exception.response?.data?.errors || {}).flat().join(' ') || exception.response?.data?.message || 'Dropbox ist momentan nicht verfügbar. Bitte den normalen Ordnerupload verwenden.'
}

function validFolderPath(path) {
    return typeof path === 'string' && path.startsWith('/')
}

function cancelFolderPath() {
    folderPathRequest?.controller.abort()
    folderPathRequest = null
    folderPathLoading.value = false
}

async function ensureFolderPath(folder = state.value?.folder, retry = false) {
    if (!props.active || !key.value || !folder?.id) return
    if (validFolderPath(folder.path)) {
        resolvedFolder.value = folder
        folderPathError.value = ''
        return
    }
    if (resolvedFolder.value?.id === folder.id && validFolderPath(resolvedFolder.value.path)) return
    const attempt = `${key.value}:${folder.id}`
    if (!retry && folderPathAttempt === attempt && folderPathError.value) return
    if (folderPathRequest?.key === key.value && folderPathRequest.folderId === folder.id) return folderPathRequest.promise
    cancelFolderPath()
    const request = { key: key.value, folderId: folder.id, controller: new AbortController(), promise: null }
    folderPathRequest = request
    folderPathAttempt = attempt
    folderPathLoading.value = true
    folderPathError.value = ''
    const current = () => props.active && key.value === request.key && folderPathRequest === request
        && state.value?.folder?.id === request.folderId && !request.controller.signal.aborted
    request.promise = Promise.resolve().then(() => axios.get(show.url(props.work.id, { query: { include_folder_path: true } }),
        { timeout: 45000, signal: request.controller.signal })).then(response => {
        if (!current()) return
        const received = response.data.folder
        if (received?.id !== request.folderId || !validFolderPath(received.path)) {
            folderPathError.value = 'Der vollständige Dropbox-Pfad konnte nicht bestätigt werden.'
            return
        }
        resolvedFolder.value = received
    }).catch(exception => {
        if (current()) folderPathError.value = errorText(exception)
    }).finally(() => {
        if (folderPathRequest === request) {
            folderPathRequest = null
            folderPathLoading.value = false
        }
    })
    return request.promise
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
        suggestions.value = []
        suggestedFolder.value = null
        directSuggestion.value = null
        resolvedFolder.value = null
        folderPathAttempt = null
        cancelFolderPath()
        suggestionStart.value = null
        suggestionMessage.value = ''
        emit('changed')
    }
}

async function loadFolders(more = false, options = {}) {
    if (props.busy || loading.value || !key.value) return
    const sequence = requestSequence + 1
    listing.value = true
    try {
        const parent = ancestors.value.at(-1)
        const response = await perform(() => axios.get(listFolders.url(props.work.id, { query: {
            ...(parent ? { folder_id: parent.id } : {}), ...(more && cursor.value ? { cursor: cursor.value } : {}),
        } }), { timeout: 45000, ...options }))
        if (response) {
            folders.value = (more ? [...folders.value, ...response.data.folders] : response.data.folders)
                .sort((first, second) => first.name.localeCompare(second.name, 'de-AT', { sensitivity: 'base', numeric: true })
                    || first.id.localeCompare(second.id))
            cursor.value = response.data.cursor
            if (parent && response.data.current?.id === parent.id) {
                ancestors.value = ancestors.value.map(folder => folder.id === parent.id ? { ...folder, ...response.data.current } : folder)
            }
            if (parent && response.data.parent) {
                ancestors.value = ancestors.value.map(folder => folder.id === parent.id ? { ...folder, parent: response.data.parent } : folder)
            }
        }
    } finally {
        if (sequence === requestSequence) listing.value = false
    }
}

function browseRoot() {
    ancestors.value = []
    folders.value = []
    cursor.value = null
    browsing.value = true
    loadFolders()
}

async function browseSuggested() {
    if (props.busy || loading.value || !key.value) return
    const assigned = state.value?.folder
    const start = assigned || suggestedFolder.value || suggestionStart.value
    ancestors.value = start ? [start] : []
    folders.value = []
    cursor.value = null
    browsing.value = true
    const controller = new AbortController()
    resolutionRequest = controller
    try {
        await loadFolders(false, { signal: controller.signal })
    } finally {
        if (resolutionRequest === controller) {
            resolutionRequest = null
        }
    }
}

function cancelResolution() {
    cancelFolderPath()
    resolutionRequest?.abort()
    resolutionRequest = null
    requestSequence++
    loading.value = false
    resolving.value = false
    listing.value = false
    browsing.value = false
}

async function initializeSource() {
    if (!props.active || !key.value) return
    const selectedKey = key.value
    const sequence = ++requestSequence
    const controller = new AbortController()
    resolutionRequest = controller
    resolving.value = true
    loading.value = true
    error.value = ''
    let searching = false
    const current = () => props.active && key.value === selectedKey && sequence === requestSequence
    try {
        const connection = await loadWorkDropbox(selectedKey, props.work.id, true, { signal: controller.signal })
        if (!current() || !connection?.configured || !connection.installed || !connection.connected) return
        if (connection.folder) {
            ensureFolderPath(connection.folder)
            return
        }
        searching = true
        const data = await loadWorkDropboxSuggestions(selectedKey, props.work, { signal: controller.signal })
        if (!current()) return
        suggestions.value = data.suggestions || []
        suggestedFolder.value = data.suggested_folder || null
        directSuggestion.value = suggestedFolder.value
        suggestionStart.value = data.start_folder || null
        suggestionMessage.value = data.message || ''
    } catch (exception) {
        if (current()) {
            if (searching) suggestionMessage.value = `Ordnersuche fehlgeschlagen: ${errorText(exception)} Bitte den Ordner manuell auswählen.`
            else error.value = errorText(exception)
        }
    } finally {
        if (current()) {
            resolving.value = false
            loading.value = false
            resolutionRequest = null
        }
    }
}

function browseSuggestion(folder) {
    browsing.value = true
    suggestedFolder.value = folder
    ancestors.value = [folder]
    folders.value = []
    cursor.value = null
    loadFolders()
}

function browseFolder(folder) {
    ancestors.value = [...ancestors.value, folder]
    folders.value = []
    cursor.value = null
    loadFolders()
}

function browseParent() {
    const parent = ancestors.value.at(-1)?.parent
    ancestors.value = ancestors.value.length === 1 && parent ? [parent] : ancestors.value.slice(0, -1)
    folders.value = []
    cursor.value = null
    loadFolders()
}

async function assignFolder(folder = ancestors.value.at(-1), previewAfterAssignment = false) {
    if (!folder?.id) return
    const selectedKey = key.value
    const response = await perform(() => axios.post(storeFolder.url(props.work.id), { folder_id: folder.id }))
    if (response) {
        const assigned = response.data.folder
        if (assigned?.id === folder.id && validFolderPath(assigned.path || folder.path)) {
            resolvedFolder.value = { ...assigned, path: assigned.path || folder.path }
        }
        workDropboxStates.set(selectedKey, response.data)
        browsing.value = false
        suggestions.value = []
        suggestedFolder.value = null
        directSuggestion.value = null
        suggestionMessage.value = ''
        emit('changed')
        if (previewAfterAssignment && props.active && key.value === selectedKey) emit('preview')
    }
}

watch(() => [props.active, key.value], async () => {
    cancelResolution()
    error.value = ''
    browsing.value = false
    ancestors.value = []
    folders.value = []
    cursor.value = null
    suggestions.value = []
    suggestedFolder.value = null
    directSuggestion.value = null
    suggestionMessage.value = ''
    suggestionStart.value = null
    resolvedFolder.value = null
    folderPathAttempt = null
    folderPathError.value = ''
    if (props.active && key.value) {
        const selectedKey = key.value
        await initializeSource()
        if (!props.active || key.value !== selectedKey) return
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
watch(() => [props.active, key.value, state.value?.folder?.id], () => {
    if (props.active && state.value?.folder) ensureFolderPath()
})
onBeforeUnmount(cancelResolution)
</script>

<style scoped>
.work-dropbox-source { overflow-wrap: anywhere; }
.work-dropbox-source summary { cursor: pointer; }
</style>
