<template>
    <slot :open="open" />
    <v-dialog v-if="opened" v-model="opened" persistent scrollable max-width="780">
        <v-card>
            <v-card-title class="text-wrap">{{ section || 'Arbeitsordner' }} · {{ work?.title }}</v-card-title>
            <v-card-text>
                <div v-if="current" class="folder-browser-path text-caption mb-3" aria-label="Dropbox-Ordnerpfad">{{ current.path }}</div>
                <nav v-if="breadcrumbs.length" class="d-flex flex-wrap align-center mb-2" aria-label="Ordnernavigation">
                    <v-btn v-for="(folder, index) in breadcrumbs" :key="folder.id" size="small" variant="text"
                        :disabled="loading || unlinking" @click="navigate(folder, index)">{{ folder.name }}</v-btn>
                </nav>
                <v-progress-linear v-if="loading" indeterminate aria-label="Ordnerinhalt wird geladen" />
                <v-alert v-if="error" type="error" variant="tonal" class="my-3" role="alert">{{ error }}</v-alert>
                <v-list v-if="entries.length" aria-label="Ordner und Dateien">
                    <v-list-item v-for="entry in sortedEntries" :key="entry.id" class="folder-browser-entry"
                        :prepend-icon="entry.type === 'folder' ? 'mdi-folder-outline' : 'mdi-file-document-outline'"
                        :title="entry.name" :subtitle="entry.type === 'folder' ? 'Ordner' : fileSize(entry.size)"
                        :link="entry.type === 'folder'" :disabled="loading || unlinking" @click="enter(entry)" />
                </v-list>
                <p v-else-if="!loading && !error" class="text-body-2 py-4">Dieser Ordner ist leer.</p>
                <v-btn v-if="cursor" variant="text" :disabled="loading || unlinking" @click="read(current.id, true)">Weitere Einträge laden</v-btn>
                <v-btn v-if="error && !loading" variant="text" :disabled="unlinking" @click="open">Erneut laden</v-btn>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <v-btn v-if="!section" color="error" variant="text" prepend-icon="mdi-link-variant-off" :loading="unlinking"
                    :disabled="disabled || loading || unlinking || !linkVersion" @click="unlink">Verbindung trennen</v-btn>
                <v-spacer />
                <v-btn :disabled="unlinking" @click="close">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { folders, show, storeFolder } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { workDropboxKey, workDropboxStates } from '@/helpers/workDropbox'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { useAdminStore } from '@/stores/admin/AdminStore'

const props = defineProps({ work: Object, disabled: Boolean, exercise: Boolean, date: { type: String, default: '' }, section: { type: String, default: '' } })
const admin = useAdminStore()
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const opened = ref(false)
const loading = ref(false)
const unlinking = ref(false)
const error = ref('')
const current = ref(null)
const entries = ref([])
const breadcrumbs = ref([])
const cursor = ref(null)
const linkVersion = ref(null)
const sortedEntries = computed(() => [...entries.value].sort((left, right) =>
    Number(right.type === 'folder') - Number(left.type === 'folder') || left.name.localeCompare(right.name, 'de')))
let operation = 0
let controller = null

function reset() {
    operation++
    controller?.abort()
    controller = null
    loading.value = false
}

function close() {
    reset()
    opened.value = false
    entries.value = []
    breadcrumbs.value = []
    current.value = null
    linkVersion.value = null
    error.value = ''
}

function failureMessage(failure) {
    return Object.values(failure.response?.data?.errors || {}).flat()[0]
        || failure.response?.data?.message || 'Der Ordnerinhalt konnte nicht geladen werden. Bitte erneut versuchen.'
}

async function open() {
    if (props.disabled || !key.value || !workAutomationEnabled(props.work) || unlinking.value) return
    reset()
    opened.value = true
    entries.value = []
    breadcrumbs.value = []
    current.value = null
    cursor.value = null
    linkVersion.value = null
    error.value = ''
    const request = operation
    const currentKey = key.value
    controller = new AbortController()
    loading.value = true
    try {
        const { data } = await axios.get(show.url(props.work.id), { signal: controller.signal, timeout: 45000 })
        if (request !== operation || key.value !== currentKey) return
        if (!data.connected || !(data.module_link?.folder?.id || data.folder?.id)) throw new Error('Kein Arbeitsordner verknüpft.')
        const previous = workDropboxStates.get(currentKey)
        workDropboxStates.set(currentKey, !data.exercise && previous?.exercise && previous.link_version === data.link_version
            ? { ...data, exercise: previous.exercise } : data)
        linkVersion.value = data.link_version
        await read()
    } catch (failure) {
        if (request === operation) error.value = failure.message === 'Kein Arbeitsordner verknüpft.' ? failure.message : failureMessage(failure)
    } finally {
        if (request === operation) loading.value = false
    }
}

async function read(folderId = null, append = false) {
    const request = operation
    const currentKey = key.value
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get(folders.url(props.work.id), { signal: controller.signal, timeout: 120000,
            params: { contents: 1, ...(props.exercise ? { exercise: 1 } : {}), ...(props.date ? { date: props.date } : {}),
                ...(props.section ? { section: props.section } : {}),
                ...(folderId ? { folder_id: folderId } : {}), ...(append && cursor.value ? { cursor: cursor.value } : {}) } })
        if (request !== operation || key.value !== currentKey) return false
        if (!data.root?.id || !data.current?.id || !Array.isArray(data.entries) || data.link_version !== linkVersion.value) {
            throw new Error('Die Verbindung hat sich geändert. Bitte den Dialog erneut öffnen.')
        }
        current.value = data.current
        const merged = append ? [...entries.value, ...data.entries] : data.entries
        entries.value = [...new Map(merged.map(entry => [entry.id, entry])).values()]
        cursor.value = data.cursor
        if (!breadcrumbs.value.length) breadcrumbs.value = [data.root]
        return true
    } catch (failure) {
        if (request === operation) {
            if (!append) entries.value = []
            cursor.value = null
            error.value = failure.message === 'Die Verbindung hat sich geändert. Bitte den Dialog erneut öffnen.' ? failure.message : failureMessage(failure)
        }
        return false
    } finally {
        if (request === operation) loading.value = false
    }
}

async function enter(entry) {
    if (entry.type !== 'folder' || loading.value || unlinking.value) return
    if (await read(entry.id)) breadcrumbs.value.push({ id: entry.id, name: entry.name })
}

async function navigate(folder, index) {
    if (loading.value || unlinking.value) return
    if (await read(folder.id)) breadcrumbs.value = breadcrumbs.value.slice(0, index + 1)
}

async function unlink() {
    if (props.disabled || loading.value || unlinking.value || !linkVersion.value) return
    const request = operation
    const currentKey = key.value
    unlinking.value = true
    error.value = ''
    try {
        const { data } = await axios.post(storeFolder.url(props.work.id), { unlink: true, link_version: linkVersion.value }, { timeout: 45000 })
        if (request !== operation || currentKey !== key.value) return
        workDropboxStates.set(currentKey, data)
        close()
    } catch (failure) {
        if (request === operation) error.value = failureMessage(failure)
    } finally {
        unlinking.value = false
    }
}

function fileSize(size) {
    return Number.isFinite(size) ? `${new Intl.NumberFormat('de-AT').format(size)} Bytes` : 'Datei'
}

watch(() => [key.value, props.exercise, props.date, props.section, workAutomationEnabled(props.work)], close)
watch(() => workDropboxStates.get(key.value)?.link_version, value => {
    if (opened.value && linkVersion.value && value !== linkVersion.value) close()
})
onBeforeUnmount(reset)
defineExpose({ open })
</script>

<style scoped>
.folder-browser-path,
.folder-browser-entry :deep(.v-list-item-title) {
    overflow-wrap: anywhere;
    white-space: normal;
}
</style>
