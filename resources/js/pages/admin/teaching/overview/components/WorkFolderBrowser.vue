<template>
    <slot :open="open" />
    <v-dialog v-if="opened" v-model="opened" persistent scrollable max-width="780">
        <v-card class="folder-browser-card" @click.stop>
            <v-card-title class="text-wrap">{{ section || (module ? 'Lernmodulordner' : exercise ? 'Leistungsfeststellungsordner' : 'Arbeitsordner') }} · {{ work?.title }}</v-card-title>
            <v-card-text>
                <template v-if="module">
                    <div v-if="current?.path" class="folder-browser-path text-body-2 mb-2" aria-label="Dropbox-Ordnerpfad">{{ modulePathPrefix }}<strong>{{ modulePathLastPart }}</strong>{{ modulePathTrailingSlashes }}</div>
                    <p v-else class="text-body-2 mb-2">Für diese Verknüpfung ist kein Lernmodulpfad gespeichert.</p>
                    <p v-if="exerciseFolderName" class="folder-browser-path text-body-1 text-primary">{{ exerciseFolderName }}</p>
                    <p v-else class="text-body-2">LF-Ordner nicht bestätigt.</p>
                    <p v-if="hasModuleLink && !unlinkBlocked && !activity" class="text-body-2 mt-2">
                        Beim Trennen löscht Schooltool auch den zugehörigen unvorbereiteten Prüfungsordner in Dropbox, falls vorhanden.
                    </p>
                    <v-progress-linear v-if="loading" indeterminate aria-label="Verknüpfung wird geladen" />
                    <v-alert v-if="error" type="error" variant="tonal" class="my-3" role="alert">{{ error }}</v-alert>
                </template>
                <template v-else>
                <div v-if="current" class="folder-browser-path text-caption mb-3" aria-label="Dropbox-Ordnerpfad">{{ current.path }}</div>
                <nav v-if="visibleBreadcrumbs.length" class="d-flex flex-wrap align-center mb-2" aria-label="Ordnernavigation">
                    <v-btn v-for="(folder, index) in visibleBreadcrumbs" :key="folder.id" size="small" variant="text"
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
                </template>
                <p v-if="unlinking" role="status" class="text-body-2 mt-3">Dropbox-Verbindung wird geprüft und getrennt …</p>
                <v-alert v-if="!section && unlinkHint?.message" :type="unlinkHint.status === 'blocked' ? 'warning' : 'info'" variant="tonal" class="mt-3">{{ unlinkHint.message }}</v-alert>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <v-btn v-if="!section && !activity" color="error" variant="text" prepend-icon="mdi-link-variant-off" :loading="unlinking"
                    :disabled="disabled || loading || unlinking || checkingUnlink || !linkVersion || unlinkBlocked" @click="unlink">Verbindung trennen</v-btn>
                <v-btn v-if="!section && !activity && linkVersion && unlinkBlocked" variant="text" :loading="checkingUnlink"
                    :disabled="disabled || loading || unlinking || checkingUnlink" @click="checkUnlink">LF-Stand prüfen</v-btn>
                <v-spacer />
                <v-btn :disabled="unlinking" @click="close">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { folders, show, storeFolder } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import { confirmedExerciseFolder } from '@/helpers/workOverview'
import { workAssessmentActivity } from '@/helpers/workAssessmentRun'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { useAdminStore } from '@/stores/admin/AdminStore'

const props = defineProps({ work: Object, disabled: Boolean, module: Boolean, exercise: Boolean, date: { type: String, default: '' }, section: { type: String, default: '' } })
const unlinkTimeout = 120000
const admin = useAdminStore()
const activity = computed(() => workAssessmentActivity(admin.config?.user?.id, props.work))
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const hasModuleLink = computed(() => Boolean(workDropboxStates.get(key.value)?.module_link?.folder?.id))
const opened = ref(false)
const loading = ref(false)
const unlinking = ref(false)
const checkingUnlink = ref(false)
const error = ref('')
const current = ref(null)
const exerciseFolderName = computed(() => {
    if (!opened.value || !props.module) return ''
    const checked = workExerciseCheck(key.value, props.work, props.date)
    return confirmedExerciseFolder(checked)?.name || ''
})
const modulePathParts = computed(() => {
    const path = current.value?.path || ''
    const match = path.match(/([^/]+)(\/*)$/)
    return match ? [path.slice(0, match.index), match[1], match[2]] : [path, '', '']
})
const modulePathPrefix = computed(() => modulePathParts.value[0])
const modulePathLastPart = computed(() => modulePathParts.value[1])
const modulePathTrailingSlashes = computed(() => modulePathParts.value[2])
const entries = ref([])
const breadcrumbs = ref([])
const visibleBreadcrumbs = computed(() => props.module && current.value?.path ? breadcrumbs.value.slice(0, -1) : breadcrumbs.value)
const cursor = ref(null)
const linkVersion = ref(null)
const unlinkState = ref(null)
const unlinkHint = computed(() => {
    if (unlinkState.value) return unlinkState.value.message ? unlinkState.value : null
    const state = workDropboxStates.get(key.value)
    const checked = workExerciseCheck(key.value, props.work, props.date)
    if (checked?.status === 'found' || state?.exercise?.status === 'found') {
        return { status: 'blocked', message: 'Zuerst „Leistungsfeststellung löschen“ bestätigen. Danach kann die Verbindung getrennt werden.' }
    }
    return null
})
const unlinkBlocked = computed(() => ['blocked', 'unknown'].includes(unlinkState.value?.status ?? unlinkHint.value?.status))
const sortedEntries = computed(() => [...entries.value].sort((left, right) =>
    Number(right.type === 'folder') - Number(left.type === 'folder') || left.name.localeCompare(right.name, 'de')))
let operation = 0
let controller = null

function reset() {
    operation++
    controller?.abort()
    controller = null
    loading.value = false
    checkingUnlink.value = false
}

function close() {
    reset()
    opened.value = false
    entries.value = []
    breadcrumbs.value = []
    current.value = null
    linkVersion.value = null
    unlinkState.value = null
    error.value = ''
}

function failureMessage(failure, fallback = 'Der Ordnerinhalt konnte nicht geladen werden. Bitte erneut versuchen.') {
    return Object.values(failure.response?.data?.errors || {}).flat()[0]
        || failure.response?.data?.message || fallback
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
    unlinkState.value = null
    error.value = ''
    if (props.module) {
        const saved = workDropboxStates.get(key.value)
        current.value = saved?.module_link?.folder || (saved?.folder ? { id: saved.folder.id, name: saved.folder.name } : null)
        linkVersion.value = saved?.link_version || null
        if (current.value?.id && linkVersion.value) return
    }
    const request = operation
    const currentKey = key.value
    controller = new AbortController()
    loading.value = true
    try {
        const { data } = await axios.get(show.url(props.work.id), { signal: controller.signal, timeout: 45000,
            ...(props.exercise ? { params: { include_folder_path: 1 } } : {}) })
        if (request !== operation || key.value !== currentKey) return
        if (!data.connected || !(data.module_link?.folder?.id || data.folder?.id)) throw new Error('Kein Arbeitsordner verknüpft.')
        const previous = workDropboxStates.get(currentKey)
        workDropboxStates.set(currentKey, !data.exercise && previous?.exercise && previous.link_version === data.link_version
            ? { ...data, exercise: previous.exercise } : data)
        linkVersion.value = data.link_version
        if (props.module) current.value = data.module_link?.folder || (data.folder ? { id: data.folder.id, name: data.folder.name } : null)
        else await read()
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
                ...(props.module ? { module: 1 } : {}),
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

async function checkUnlink() {
    if (props.disabled || activity.value || loading.value || unlinking.value || checkingUnlink.value || !linkVersion.value) return
    const request = operation
    const currentKey = key.value
    checkingUnlink.value = true
    error.value = ''
    try {
        const { data } = await axios.get(show.url(props.work.id), { timeout: unlinkTimeout, params: { include_unlink_status: 1 } })
        if (request !== operation || currentKey !== key.value) return
        if (data.link_version !== linkVersion.value) {
            if (data.connected && data.link_version && !data.module_conflict && !data.module_link?.folder?.id && !data.folder?.id) {
                workDropboxStates.set(currentKey, data)
                close()
                return
            }
            unlinkState.value = { status: 'unknown', message: 'Die Zuordnung hat sich geändert. Bitte den Dialog erneut öffnen.' }
            return
        }
        unlinkState.value = data.unlink_status || { status: 'unknown', message: 'Der LF-Stand konnte nicht eindeutig bestätigt werden.' }
    } catch (failure) {
        if (request === operation) unlinkState.value = { status: 'unknown',
            message: failureMessage(failure, 'Der LF-Stand konnte nicht geprüft werden. Bitte erneut versuchen.') }
    } finally {
        if (request === operation) checkingUnlink.value = false
    }
}

async function unlink() {
    if (props.disabled || activity.value || loading.value || unlinking.value || checkingUnlink.value || !linkVersion.value || unlinkBlocked.value) return
    const request = operation
    const currentKey = key.value
    unlinking.value = true
    error.value = ''
    try {
        const { data } = await axios.post(storeFolder.url(props.work.id), { unlink: true, link_version: linkVersion.value }, { timeout: unlinkTimeout })
        if (request !== operation || currentKey !== key.value) return
        workDropboxStates.set(currentKey, data)
        close()
    } catch (failure) {
        if (request === operation) {
            const timedOut = ['ECONNABORTED', 'ETIMEDOUT'].includes(failure.code)
            error.value = timedOut ? 'Der Server hat noch nicht geantwortet. Mit „LF-Stand prüfen“ kannst du den aktuellen Stand abrufen.'
                : failureMessage(failure, 'Das Trennen der Dropbox-Verbindung konnte nicht bestätigt werden. Bitte den LF-Stand prüfen.')
            unlinkState.value = { status: 'unknown' }
        }
    } finally {
        unlinking.value = false
    }
}

function fileSize(size) {
    return Number.isFinite(size) ? `${new Intl.NumberFormat('de-AT').format(size)} Bytes` : 'Datei'
}

watch(() => [key.value, props.module, props.exercise, props.date, props.section, workAutomationEnabled(props.work)], close)
watch(() => workDropboxStates.get(key.value)?.link_version, value => {
    if (opened.value && linkVersion.value && value !== linkVersion.value) close()
})
onBeforeUnmount(reset)
defineExpose({ open })
</script>

<style scoped>
.folder-browser-card { cursor: auto; }
.folder-browser-path,
.folder-browser-entry :deep(.v-list-item-title) {
    overflow-wrap: anywhere;
    white-space: normal;
}
</style>
