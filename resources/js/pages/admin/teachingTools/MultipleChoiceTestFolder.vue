<template>
    <v-card>
        <v-card-title>Testordner · {{ test.title }}</v-card-title>
        <v-card-text>
            <p class="text-body-2 mb-3">Verbindung für diesen Test merken. Exportiert werden Zugangslinks, zugeteilte Fragen, Antworten und Ergebnisse.
                Importiert werden nach Prüfung abgeschlossene Ergebnisse. Laufende Bearbeitungen und widersprüchliche Stände werden nicht überschrieben.</p>
            <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
            <v-alert v-if="notice" type="success" variant="tonal" class="mb-3" role="status">{{ notice }}</v-alert>
            <p v-if="loading" role="status">Ordnerverbindung laden …</p>
            <template v-else-if="state">
                <p v-if="state.folder" class="text-body-2 mb-2"><strong>Gemerkter Ordner:</strong> {{ state.folder.name }} · {{ state.folder.provider === 'dropbox' ? 'Dropbox' : 'lokal' }}</p>
                <p v-if="state.folder?.provider === 'dropbox'" class="mc-folder-path text-caption mb-3">{{ state.folder.path || (suggesting ? 'Vollständigen Dropbox-Pfad laden …' : 'Vollständiger Pfad noch nicht bestätigt. Bitte Ordnerempfehlung anzeigen.') }}</p>
                <v-btn-toggle v-model="provider" mandatory color="primary" variant="outlined" class="mb-3">
                    <v-btn value="dropbox">Dropbox</v-btn><v-btn value="local">Lokaler Ordner</v-btn>
                </v-btn-toggle>
                <template v-if="provider === 'dropbox'">
                    <p v-if="!state.configured" class="text-body-2">Dropbox ist noch nicht eingerichtet. Der lokale Import und Download stehen zur Verfügung.</p>
                    <template v-else>
                        <p v-if="!state.writable" class="text-body-2 mb-2">Für den Export ist zusätzlich die Dropbox-Freigabe zum Schreiben erforderlich. Die Zustimmung erfolgt bei Dropbox.</p>
                        <v-btn v-if="!state.writable" color="primary" :disabled="!!busy" @click="reconnect">Dropbox zum Lesen und Schreiben verbinden</v-btn>
                        <div v-if="state.connected" class="mt-3">
                            <p v-if="suggesting" role="status" class="text-body-2 mb-2">Passenden Testordner suchen …</p>
                            <p v-if="suggestionMessage" class="text-body-2 mb-2">{{ suggestionMessage }}</p>
                            <v-alert v-if="suggestionWarning" type="warning" variant="tonal" class="mb-2">{{ suggestionWarning }}</v-alert>
                            <p v-if="suggestions.length" class="text-body-2 mb-2"><strong>Ordnervorschläge · noch nicht zugeordnet</strong></p>
                            <ul v-if="suggestions.length" class="mc-folder-list" aria-label="Vorgeschlagene Testordner">
                                <li v-for="folder in suggestions" :key="folder.id" class="mb-2">
                                    <strong>{{ folder.name }}</strong><p class="mc-folder-path text-caption">{{ folder.path }}</p>
                                    <p v-if="folder.reason" class="text-caption">{{ folder.reason }}</p>
                                    <v-btn size="small" color="primary" variant="outlined" :disabled="!!busy" @click="bindDropbox(folder)">Diesen Vorschlag merken</v-btn>
                                </li>
                            </ul>
                            <v-btn variant="text" :disabled="!!busy" @click="task('suggest', resolveSuggestions)">Ordnerempfehlung anzeigen</v-btn>
                            <v-btn variant="outlined" :disabled="!!busy" @click="listFolders()">Dropbox-Ordner auswählen</v-btn>
                            <div v-if="listing" class="border rounded pa-3 mt-2">
                                <p class="text-body-2 mb-2">{{ listing.current?.name || 'Dropbox' }}</p>
                                <p v-if="listing.current?.path" class="mc-folder-path text-caption mb-2">{{ listing.current.path }}</p>
                                <v-btn v-if="listing.current" size="small" variant="text" @click="listFolders(listing.parent?.id || '')">Eine Ebene zurück</v-btn>
                                <ul class="mc-folder-list">
                                    <li v-for="folder in listing.folders" :key="folder.id"><button type="button" :disabled="!!busy" @click="listFolders(folder.id)">{{ folder.name }}</button></li>
                                </ul>
                                <v-btn v-if="listing.cursor" size="small" variant="text" @click="listFolders(listing.current?.id || '', listing.cursor)">Weitere Ordner</v-btn>
                                <v-btn v-if="listing.current" color="primary" size="small" :disabled="!!busy" @click="bindDropbox">Diesen Ordner merken</v-btn>
                            </div>
                            <div v-if="state.folder?.provider === 'dropbox'" class="d-flex flex-wrap ga-2 mt-3">
                                <v-btn color="primary" :disabled="!!busy || !state.writable" @click="exportDropbox">Aktuellen Stand in Dropbox speichern</v-btn>
                                <v-btn variant="outlined" :disabled="!!busy" @click="previewDropbox">Aus Dropbox lesen und prüfen</v-btn>
                                <v-btn variant="text" :disabled="!!busy || !state.writable" @click="verifyWrite">Schreibzugriff prüfen</v-btn>
                            </div>
                        </div>
                    </template>
                </template>
                <template v-else>
                    <template v-if="directorySupported">
                        <v-btn variant="outlined" :disabled="!!busy" @click="chooseLocal">{{ directory ? 'Lokalen Ordner ändern' : 'Lokalen Ordner auswählen' }}</v-btn>
                        <p class="text-caption mt-2">Der Browser merkt die Ordnerfreigabe auf diesem Gerät. Eine erneute Freigabe kann erforderlich sein.</p>
                    </template>
                    <template v-else>
                        <p class="text-body-2 mb-2">Dieser Browser erlaubt keinen dauerhaften direkten Ordnerzugriff. Der Ordnername bleibt gemerkt; zum Importieren wählen Sie die Datei, den Download speichern Sie in diesem Ordner.</p>
                        <v-text-field v-model="localName" label="Name des lokalen Testordners" density="compact" variant="outlined" maxlength="255" />
                        <v-btn variant="outlined" :disabled="!!busy || !localName.trim()" @click="task('bind', rememberLocal)">Ordnernamen merken</v-btn>
                    </template>
                    <div class="d-flex flex-wrap ga-2 my-3">
                        <v-btn color="primary" :disabled="!!busy" @click="exportLocal">{{ directory ? 'Aktuellen Stand im Ordner speichern' : 'Aktuellen Stand herunterladen' }}</v-btn>
                        <v-btn v-if="directory" variant="outlined" :disabled="!!busy" @click="readLocal">Aus gemerktem Ordner lesen und prüfen</v-btn>
                    </div>
                    <FilePond name="mc-test-exchange" :allow-multiple="false" :max-files="1" :instant-upload="false" :allow-process="false"
                        :allow-revert="false" :allow-replace="true" :allow-paste="false" :server="null" :credits="false" :disabled="!!busy"
                        label-idle="<strong>Testdatei hierher ziehen oder <span class='filepond--label-action'>Datei auswählen</span></strong>"
                        label-file-load-error="Datei konnte nicht gelesen werden" @updatefiles="readFiles" />
                    <v-btn v-if="packageData" variant="outlined" :disabled="!!busy" @click="previewPackage">Datei prüfen</v-btn>
                </template>
                <div v-if="preview" class="mt-4">
                    <p class="text-body-2">{{ preview.changes.length }} Ergebnisse zur Übernahme · {{ preview.unchanged }} unverändert · {{ preview.pending }} noch nicht abgeschlossen</p>
                    <v-alert v-for="conflict in preview.conflicts" :key="conflict" type="warning" variant="tonal" class="mt-2">{{ conflict }}</v-alert>
                    <ul class="my-2"><li v-for="change in preview.changes" :key="change.attempt_id">{{ change.participant }}: {{ change.before.score ?? 'offen' }} → {{ change.after.score ?? 'ohne Bewertung' }} Punkte</li></ul>
                    <p class="text-caption mb-2">Quelle und Teilnehmerzuordnung wurden geprüft. Vor jeder Übernahme wird der bisherige Stand privat gesichert.</p>
                    <v-btn color="primary" :disabled="!!busy || !preview.changes.length || !!preview.conflicts.length" @click="applyImport">Geprüfte Ergebnisse übernehmen</v-btn>
                </div>
            </template>
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn :disabled="!!busy" @click="$emit('close')">Schließen</v-btn></v-card-actions>
    </v-card>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, shallowRef, watch } from 'vue'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import { status, store, connect, folders, save, read, remember, checkWrite } from '@/routes/admin/multiple-choice/tests/folder'
import { loadWorkImportDirectory, saveWorkImportDirectory, supportsWorkImportDirectory } from '@/helpers/workImportDirectory'

const props = defineProps({ test: { type: Object, required: true }, user: { type: Object, required: true } })
const emit = defineEmits(['close', 'changed'])
const FilePond = vueFilePond()
const state = ref(null)
const provider = ref('local')
const loading = ref(true)
const busy = ref('')
const error = ref('')
const notice = ref('')
const listing = ref(null)
const suggestions = ref([])
const suggestionMessage = ref('')
const suggestionWarning = ref('')
const suggesting = ref(false)
const suggestionStart = ref(null)
const localName = ref('')
const directory = shallowRef(null)
const directorySupported = supportsWorkImportDirectory()
const packageData = shallowRef(null)
const preview = ref(null)
const importSource = ref('file')
const directoryKey = computed(() => `mc:${props.user.school_id}:${props.user.id}:${props.test.id}:${state.value?.identity}`)
let readVersion = 0
let disposed = false
onBeforeUnmount(() => { disposed = true; readVersion++ })
watch(provider, () => { preview.value = null; error.value = '' })

async function task(name, callback) {
    if (busy.value) return
    busy.value = name
    error.value = ''
    notice.value = ''
    try { await callback() }
    catch (failure) {
        if (failure.name !== 'AbortError' && !disposed) {
            error.value = Object.values(failure.response?.data?.errors ?? {}).flat().join(' ') || failure.response?.data?.message || failure.message || 'Der Testordner ist momentan nicht erreichbar.'
        }
    } finally { busy.value = '' }
}

onMounted(async () => {
    await task('status', async () => {
        const { data } = await axios.get(status.url(props.test.id))
        if (disposed) return
        state.value = data
        provider.value = data.folder?.provider || (data.connected ? 'dropbox' : 'local')
        localName.value = data.folder?.provider === 'local' ? data.folder.name : ''
        if (data.folder?.provider === 'local') directory.value = await loadWorkImportDirectory(directoryKey.value)
        if (data.connected && data.folder?.provider !== 'local') {
            await resolveSuggestions()
        }
    })
    loading.value = false
})

async function resolveSuggestions() {
    suggesting.value = true
    suggestionWarning.value = ''
    try {
        const response = await axios.get(folders.url(props.test.id), { params: { suggest: 1 } })
        if (!disposed) {
            suggestions.value = response.data.suggestions || []
            suggestionMessage.value = response.data.message || ''
            suggestionWarning.value = response.data.warning || ''
            suggestionStart.value = response.data.suggested_folder || response.data.start_folder || null
            if ('folder' in response.data) state.value.folder = response.data.folder
        }
    } catch (failure) {
        if (!disposed) {
            suggestionMessage.value = 'Die Ordnersuche konnte nicht abgeschlossen werden. Bitte erneut versuchen oder manuell auswählen.'
            suggestionWarning.value = Object.values(failure.response?.data?.errors ?? {}).flat().join(' ') || failure.response?.data?.message || 'Die Anfrage zur Ordnersuche ist fehlgeschlagen.'
        }
    } finally { suggesting.value = false }
}

async function reconnect() {
    await task('connect', async () => {
        const { data } = await axios.post(connect.url(props.test.id))
        if (!disposed) window.location.assign(data.url)
    })
}

async function listFolders(id = null, cursor = null) {
    await task('folders', async () => {
        id ??= state.value?.folder?.folder_id || suggestionStart.value?.id || ''
        const { data } = await axios.get(folders.url(props.test.id), { params: { folder_id: id || undefined, cursor: cursor || undefined } })
        if (!disposed) listing.value = cursor ? { ...data, folders: [...listing.value.folders, ...data.folders] } : data
    })
}

async function bindDropbox(folder = null) {
    await task('bind', async () => {
        const { data } = await axios.put(store.url(props.test.id), { provider: 'dropbox', folder_id: folder?.id || listing.value.current.id })
        if (!disposed) { state.value = data; listing.value = null; suggestions.value = []; suggestionMessage.value = ''; suggestionWarning.value = ''; preview.value = null }
    })
}

async function rememberLocal() {
    const { data } = await axios.put(store.url(props.test.id), { provider: 'local', name: localName.value.trim() })
    if (!disposed) { state.value = data; preview.value = null }
}

async function chooseLocal() {
    await task('directory', async () => {
        const handle = await window.showDirectoryPicker({ id: 'schooltool-mc-test-export', mode: 'readwrite' })
        if (disposed) return
        localName.value = handle.name
        await rememberLocal()
        await saveWorkImportDirectory(directoryKey.value, handle)
        directory.value = handle
    })
}

async function authorizeDirectory() {
    if (typeof directory.value.queryPermission === 'function' && await directory.value.queryPermission({ mode: 'readwrite' }) !== 'granted'
        && await directory.value.requestPermission({ mode: 'readwrite' }) !== 'granted') {
        throw new Error('Bitte den gemerkten Ordner erneut freigeben oder einen anderen Ordner wählen.')
    }
}

async function existingFile() {
    try { return await (await directory.value.getFileHandle(state.value.filename)).getFile() }
    catch (failure) { if (failure.name === 'NotFoundError') return null; throw failure }
}

async function exportLocal() {
    await task('export', async () => {
        let oldText = null
        if (directory.value) {
            await authorizeDirectory()
            const old = await existingFile()
            oldText = old ? await old.text() : null
        }
        const { data } = await axios.post(save.url(props.test.id), { previous: oldText ? JSON.parse(oldText) : null })
        if (disposed) return
        const contents = JSON.stringify(data.package, null, 2) + '\n'
        if (directory.value) {
            const current = await existingFile()
            if ((current ? await current.text() : null) !== oldText) throw new Error('Die Ordnerdatei wurde inzwischen geändert. Bitte zuerst erneut lesen.')
            const handle = await directory.value.getFileHandle(data.filename, { create: true })
            let writable
            try { writable = await handle.createWritable(); await writable.write(contents); await writable.close() }
            catch (failure) { await writable?.abort().catch(() => {}); if (oldText === null) await directory.value.removeEntry(data.filename).catch(() => {}); throw failure }
            await axios.post(remember.url(props.test.id), { package: data.package })
            notice.value = `Aktueller Teststand im gemerkten Ordner „${directory.value.name}“ gespeichert.`
        } else {
            const url = URL.createObjectURL(new Blob([contents], { type: 'application/json;charset=utf-8' }))
            const anchor = document.createElement('a')
            anchor.href = url; anchor.download = data.filename; document.body.appendChild(anchor)
            try { anchor.click() } finally { anchor.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000) }
            notice.value = `Teststand heruntergeladen. Bitte ${data.filename} im gemerkten Testordner speichern.`
        }
    })
}

async function exportDropbox() {
    await task('export', async () => {
        await axios.post(save.url(props.test.id), { dropbox: true })
        if (!disposed) notice.value = 'Aktueller Teststand im gemerkten Dropbox-Ordner gespeichert.'
    })
}

async function verifyWrite() {
    await task('check-write', async () => {
        await axios.post(checkWrite.url(props.test.id))
        if (!disposed) notice.value = 'Schreiben und Zurücklesen erfolgreich geprüft. Die eigene Prüfdatei wurde wieder entfernt.'
    })
}

async function readFiles(items) {
    const version = ++readVersion
    packageData.value = null; preview.value = null; error.value = ''
    if (!items[0]?.file) return
    try {
        const file = items[0].file
        if (file.size > 6 * 1024 * 1024) throw new Error('Die Testdatei ist größer als 6 MiB.')
        const parsed = JSON.parse(await file.text())
        if (!disposed && version === readVersion) { packageData.value = parsed; importSource.value = 'file' }
    } catch (failure) { if (version === readVersion && !disposed) error.value = 'Die Testdatei konnte nicht gelesen werden. Bitte eine gültige JSON-Testdatei auswählen.' }
}

async function readLocal() {
    await task('preview', async () => {
        await authorizeDirectory()
        const file = await existingFile()
        if (!file) throw new Error(`Im gemerkten Ordner fehlt ${state.value.filename}.`)
        if (file.size > 6 * 1024 * 1024) throw new Error('Die Testdatei ist größer als 6 MiB.')
        packageData.value = JSON.parse(await file.text()); importSource.value = 'directory'
        const { data } = await axios.post(read.url(props.test.id), { package: packageData.value })
        if (!disposed) preview.value = data
    })
}

async function previewPackage() {
    await task('preview', async () => {
        const { data } = await axios.post(read.url(props.test.id), { package: packageData.value })
        if (!disposed) { preview.value = data; importSource.value = 'file' }
    })
}

async function previewDropbox() {
    await task('preview', async () => {
        const { data } = await axios.post(read.url(props.test.id))
        if (!disposed) { preview.value = data; importSource.value = 'dropbox' }
    })
}

async function applyImport() {
    await task('apply', async () => {
        if (importSource.value === 'directory') {
            const file = await existingFile()
            if (!file || JSON.stringify(JSON.parse(await file.text())) !== JSON.stringify(packageData.value)) throw new Error('Die Ordnerdatei wurde seit der Vorschau geändert. Bitte erneut lesen.')
        }
        const { data } = await axios.post(read.url(props.test.id), {
            ...(importSource.value === 'dropbox' ? {} : { package: packageData.value }), apply: true, hash: preview.value.hash,
        })
        if (!disposed) { preview.value = null; notice.value = `${data.imported} Ergebnisse übernommen.`; emit('changed') }
    })
}
</script>

<style scoped>
.mc-folder-list { list-style: none; padding: 0; margin: 8px 0; max-height: 240px; overflow-y: auto; }
.mc-folder-list button { display: block; width: 100%; text-align: left; padding: 8px; border-radius: 4px; overflow-wrap: anywhere; }
.mc-folder-list button:hover, .mc-folder-list button:focus-visible { background: rgba(var(--v-theme-primary), .1); }
.mc-folder-path { overflow-wrap: anywhere; word-break: break-word; white-space: normal; user-select: text; }
</style>
