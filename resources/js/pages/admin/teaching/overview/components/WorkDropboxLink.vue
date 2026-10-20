<template>
    <v-dialog v-model="opened" max-width="680" :persistent="busy">
        <v-card>
            <v-card-title>Verknüpfen · {{ work?.title }}</v-card-title>
            <v-card-text>
                <p class="mb-4">Ziehen Sie die handshake.json des Lernmoduls hierher oder wählen Sie die Datei mit einem Klick aus.</p>
                <file-pond v-if="opened" :key="pondKey" name="handshake" :allow-multiple="false" :max-files="1"
                    :instant-upload="false" :allow-process="false" :allow-revert="false" :credits="false"
                    :disabled="busy || !state?.connected" :before-add-file="acceptFile"
                    :accepted-file-types="['application/json']" :file-validate-type-detect-type="detectType"
                    label-file-type-not-allowed="Zulässig ist ausschließlich die Datei handshake.json."
                    label-idle='handshake.json hierher ziehen oder <span class="filepond--label-action">Datei auswählen</span>'
                    @updatefiles="selectFiles" />
                <p class="text-caption text-medium-emphasis">Nur die kleine Handshake-Datei wird gelesen. Schooltool prüft das Modul im verbundenen Dropbox-Konto.</p>
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3">{{ error }}</v-alert>
                <v-alert v-if="state && !state.connected" type="info" variant="tonal" class="mt-3">
                    {{ state.message }}
                    <v-btn v-if="state.configured && state.installed" class="mt-2" @click="connectDropbox">Dropbox verbinden</v-btn>
                </v-alert>
                <v-progress-linear v-if="busy" class="mt-4" indeterminate aria-label="Dropbox wird geprüft" />
                <div v-if="resolved" class="mt-4">
                    <v-alert :type="resolved.conflict ? 'error' : 'info'" variant="tonal">{{ resolved.message }}</v-alert>
                    <div v-if="resolved.candidate" class="mt-3">
                        <div class="text-subtitle-2">Gefundener Dropbox-Ordner</div>
                        <div class="text-body-2 module-folder-path" data-testid="module-folder-path">{{ resolved.candidate.folder_path }}</div>
                        <div class="text-caption mt-2">Modul-ID: {{ resolved.module_id }}</div>
                    </div>
                    <div v-for="candidate in resolved.candidates || []" :key="candidate.handshake_file_id" class="module-folder-path mt-2">{{ candidate.folder_path }}</div>
                </div>
                <div v-else-if="state?.module_link" class="mt-4">
                    <div class="text-subtitle-2">Aktuell verknüpft</div>
                    <div class="module-folder-path">{{ state.module_link.folder.path }}</div>
                </div>
                <v-btn v-if="contents && error && state?.connected" class="mt-3" variant="text" :disabled="busy" @click="resolve">Erneut prüfen</v-btn>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn :disabled="busy" @click="opened = false">Abbrechen</v-btn>
                <v-btn color="primary" variant="flat" :disabled="busy || !resolved?.can_link || !resolved?.proof || resolved?.conflict" @click="link">Verknüpfen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import 'filepond/dist/filepond.min.css'
import { connect, storeFolder } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, workDropboxKey, workDropboxStates } from '@/helpers/workDropbox'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { readModuleHandshake } from '@/helpers/moduleHandshake'

const FilePond = vueFilePond(FilePondPluginFileValidateType)
const props = defineProps({ courseId: [Number, String] })
const admin = useAdminStore()
const opened = ref(false)
const work = ref(null)
const state = ref(null)
const error = ref('')
const contents = ref('')
const filename = ref('')
const resolved = ref(null)
const busy = ref(false)
const pondKey = ref(0)
const key = computed(() => workDropboxKey(admin.config?.user?.id, work.value))
let operation = 0
let controller = null

function resetRequest() {
    operation++
    controller?.abort()
    controller = new AbortController()
    busy.value = false
}

function message(exception) {
    return Object.values(exception.response?.data?.errors || {}).flat()[0]
        || exception.response?.data?.message || exception.message || 'Dropbox konnte nicht geprüft werden. Bitte erneut versuchen.'
}

async function openLink(value) {
    if (!workAutomationEnabled(value)) return
    resetRequest()
    work.value = value
    state.value = null
    error.value = ''
    contents.value = ''
    filename.value = ''
    resolved.value = null
    pondKey.value++
    opened.value = true
    const current = operation
    busy.value = true
    try {
        const result = await loadWorkDropbox(key.value, value.id, true, { signal: controller.signal })
        if (current === operation) state.value = result
    } catch (exception) {
        if (current === operation) error.value = message(exception)
    } finally {
        if (current === operation) busy.value = false
    }
}

function acceptFile(item) {
    const file = item.file
    if (file?.name?.toLowerCase() !== 'handshake.json' || file.size > 4096) {
        resolved.value = null
        contents.value = ''
        filename.value = ''
        error.value = 'Bitte genau eine handshake.json mit höchstens 4 KB auswählen.'
        return false
    }
    return true
}

function detectType(file, type) {
    return Promise.resolve(file.name?.toLowerCase() === 'handshake.json' ? 'application/json' : type)
}

async function selectFiles(items) {
    resetRequest()
    const current = operation
    resolved.value = null
    contents.value = ''
    filename.value = ''
    if (items.length !== 1) return
    if (!acceptFile(items[0])) return
    error.value = ''
    try {
        const selected = await readModuleHandshake(items[0].file)
        if (current !== operation) return
        contents.value = selected.contents
        filename.value = selected.filename
        await resolve()
    } catch (exception) {
        if (current === operation) error.value = message(exception)
    }
}

async function resolve() {
    resetRequest()
    const current = operation
    const currentKey = key.value
    error.value = ''
    resolved.value = null
    busy.value = true
    try {
        const response = await axios.post(storeFolder.url(work.value.id), { handshake: contents.value, filename: filename.value, resolve: true },
            { signal: controller.signal, timeout: 120000 })
        if (current === operation && key.value === currentKey) resolved.value = response.data
    } catch (exception) {
        if (current === operation) error.value = message(exception)
    } finally {
        if (current === operation) busy.value = false
    }
}

async function link() {
    if (!resolved.value?.can_link || !resolved.value.proof || resolved.value.conflict || busy.value) return
    const current = operation
    const currentKey = key.value
    busy.value = true
    error.value = ''
    try {
        const response = await axios.post(storeFolder.url(work.value.id), { handshake: contents.value, filename: filename.value, proof: resolved.value.proof },
            { signal: controller.signal, timeout: 120000 })
        if (current !== operation || key.value !== currentKey) return
        workDropboxStates.set(currentKey, response.data)
        opened.value = false
    } catch (exception) {
        if (current === operation) {
            error.value = message(exception)
            resolved.value = null
        }
    } finally {
        if (current === operation) busy.value = false
    }
}

async function connectDropbox() {
    const response = await axios.post(connect.url(work.value.id))
    if (opened.value) window.location.assign(response.data.url)
}

watch(() => admin.config?.user?.id, () => { resetRequest(); opened.value = false })
watch(() => props.courseId, () => { resetRequest(); opened.value = false })
watch(opened, value => { if (!value) resetRequest() })
onBeforeUnmount(resetRequest)
defineExpose({ openLink })
</script>

<style scoped>
.module-folder-path {
    overflow-wrap: anywhere;
}
</style>
