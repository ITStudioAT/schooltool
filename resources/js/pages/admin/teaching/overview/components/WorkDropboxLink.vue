<template>
    <component :is="container" v-if="!inline || opened" v-bind="containerProps" @update:model-value="opened = $event">
        <v-card>
            <v-card-title>Lernmodul prüfen · {{ work?.title }}</v-card-title>
            <v-card-text>
                <template v-if="legacyLink && state">
                    <p class="mb-4">Wählen Sie den vorhandenen Leistungsfeststellungsordner in Dropbox. Die Zuordnung verändert keine Bewertungen, Abgaben oder Versandbelege.</p>
                    <WorkDropboxSource v-if="opened" :work="work" :active="opened" selection-only @changed="folderLinked" @busy="folderBusy = $event" />
                </template>
                <p v-if="showPicker" class="mb-4">Ziehen Sie die handshake.json des Lernmoduls hierher oder wählen Sie die Datei mit einem Klick aus.</p>
                <file-pond v-if="opened && showPicker" :key="pondKey" name="handshake" :allow-multiple="false" :max-files="1"
                    :instant-upload="false" :allow-process="false" :allow-revert="false" :credits="false"
                    :disabled="busy || !state?.connected" :before-add-file="acceptFile"
                    :accepted-file-types="['application/json']" :file-validate-type-detect-type="detectType"
                    label-file-type-not-allowed="Zulässig ist ausschließlich die Datei handshake.json."
                    label-idle='handshake.json hierher ziehen oder <span class="filepond--label-action">Datei auswählen</span>'
                    @updatefiles="selectFiles" />
                <p v-if="showPicker" class="text-caption text-medium-emphasis">Nur die kleine Handshake-Datei wird gelesen. Schooltool prüft das Modul im verbundenen Dropbox-Konto.</p>
                <p v-if="inline && contents && !showPicker && !state?.module_link?.folder?.id" class="text-caption text-medium-emphasis">Ausgewählte Datei: {{ filename }}</p>
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3">{{ error }}</v-alert>
                <v-alert v-if="state && !state.connected" type="info" variant="tonal" class="mt-3">
                    {{ state.message }}
                    <v-btn v-if="state.configured && state.installed" class="mt-2" @click="connectDropbox">Dropbox verbinden</v-btn>
                </v-alert>
                <v-progress-linear v-if="busy && !progress" class="mt-4" indeterminate aria-label="Dropbox wird geprüft" />
                <section v-if="progress" class="link-progress mt-4" aria-label="Fortschritt der Verknüpfung" aria-live="polite">
                    <strong>{{ progress.failed ? 'Prüfung oder Verknüpfung unterbrochen' : progress.completed.includes('save') ? 'Verknüpfung abgeschlossen' : progress.active === 'save' ? 'Verknüpfung läuft' : progress.active ? 'Lernmodul wird geprüft' : 'Bereit zum Verknüpfen' }}</strong>
                    <ul>
                        <li v-for="step in progressSteps" :key="step.id" :class="progressClass(step.id)">
                            <span class="link-progress-icon" aria-hidden="true">{{ progress.completed.includes(step.id) ? '✓' : progress.failed && progress.active === step.id ? '!' : '•' }}</span>
                            <span>{{ step.label }}</span>
                            <span class="link-progress-caption">{{ progressText(step.id) }}</span>
                        </li>
                    </ul>
                </section>
                <div v-if="resolved" class="mt-4">
                    <v-alert :type="resolved.conflict ? 'error' : 'info'" variant="tonal">{{ resolved.message }}</v-alert>
                    <div v-if="resolved.candidate" class="mt-3">
                        <div class="text-subtitle-2">Gefundener Dropbox-Ordner</div>
                        <div class="text-body-2 module-folder-path" data-testid="module-folder-path">{{ resolved.candidate.folder_path }}</div>
                        <div class="text-caption mt-2">Modul-ID: {{ resolved.module_id }}</div>
                        <p v-if="resolved.can_link" class="text-caption mt-2">Beim Verknüpfen wird, sofern die Angaben eindeutig sind, der Prüfungsordner mit unvorbereitetem Status angelegt.</p>
                    </div>
                    <div v-for="candidate in resolved.candidates || []" :key="candidate.handshake_file_id" class="module-folder-path mt-2">{{ candidate.folder_path }}</div>
                </div>
                <div v-else-if="state?.module_link" class="mt-4">
                    <div class="text-subtitle-2">Aktuell verknüpft</div>
                    <div class="module-folder-path">{{ state.module_link.folder.path }}</div>
                </div>
                <v-alert v-if="linkPreparation" :type="linkPreparation.status === 'partial' ? 'warning' : 'success'" variant="tonal" class="mt-3" role="status">
                    {{ linkPreparation.status === 'partial' ? linkPreparation.message : linkPreparation.status === 'already_prepared' ? 'Lernmodul verknüpft; die Leistungsfeststellung ist bereits vorbereitet.' : 'Lernmodul verknüpft; Prüfungsordner angelegt, Prüfung noch unvorbereitet.' }}
                    <span v-if="linkPreparation.folder" class="module-folder-path d-block">{{ linkPreparation.folder }}</span>
                </v-alert>
                <v-alert v-if="canRetryPreparation && !linkPreparation" type="warning" variant="tonal" class="mt-3">Der Prüfungsordner ist noch nicht bestätigt. Sie können die Anlage erneut versuchen.</v-alert>
                <v-btn v-if="canRetryPreparation" class="mt-3" variant="tonal" :disabled="busy" @click="retryPreparation">Prüfungsordner erneut anlegen</v-btn>
                <v-btn v-if="contents && (error || resolved?.conflict || (resolved && (!resolved.can_link || !resolved.proof))) && state?.connected" class="mt-3" variant="text" :disabled="busy" @click="resolve(autoCommitAuthorized)">Erneut prüfen</v-btn>
                <v-btn v-if="inline && contents && error && !state" class="mt-3" variant="text" :disabled="busy" @click="retryConnection">Erneut prüfen</v-btn>
                <v-btn v-if="inline && contents && !showPicker && !progress?.completed.includes('save')" class="mt-3" variant="text" :disabled="busy" @click="changeFile">Andere Datei auswählen</v-btn>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn v-if="!inline" :disabled="busy || folderBusy" @click="opened = false">{{ state?.module_link?.folder?.id ? 'Schließen' : 'Abbrechen' }}</v-btn>
                <v-btn v-else :disabled="busy" variant="text" @click="defer">Später</v-btn>
                <v-btn v-if="!legacyLink && !progress?.completed.includes('save')" color="primary" variant="flat" :disabled="busy || !resolved?.can_link || !resolved?.proof || resolved?.conflict" @click="link">Verknüpfen und Prüfungsordner anlegen</v-btn>
            </v-card-actions>
        </v-card>
    </component>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { VDialog } from 'vuetify/components/VDialog'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import 'filepond/dist/filepond.min.css'
import { connect, storeFolder } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, workDropboxKey, workDropboxStates } from '@/helpers/workDropbox'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { readModuleHandshake } from '@/helpers/moduleHandshake'
import { workNeedsPreparation } from '@/helpers/workOverview'
import WorkDropboxSource from './WorkDropboxSource.vue'

const FilePond = vueFilePond(FilePondPluginFileValidateType)
const props = defineProps({ courseId: [Number, String], inline: Boolean })
const emit = defineEmits(['linked', 'link-saved', 'closed', 'defer'])
const admin = useAdminStore()
const opened = ref(false)
const work = ref(null)
const state = ref(null)
const error = ref('')
const contents = ref('')
const filename = ref('')
const resolved = ref(null)
const busy = ref(false)
const folderBusy = ref(false)
const progress = ref(null)
const linkPreparation = ref(null)
const progressSteps = [
    { id: 'file', label: 'Handshake-Datei lesen und prüfen' },
    { id: 'online', label: 'Lernmodul in Dropbox prüfen' },
    { id: 'save', label: 'Verknüpfung und Prüfungsordner speichern' },
]
const pondKey = ref(0)
const linkFromCreation = ref(false)
const autoCommitAuthorized = ref(false)
const choosingNewFile = ref(false)
const container = computed(() => props.inline ? 'div' : VDialog)
const containerProps = computed(() => props.inline ? { 'data-testid': 'work-module-inline' }
    : { modelValue: opened.value, maxWidth: 680, persistent: busy.value || folderBusy.value })
const key = computed(() => workDropboxKey(admin.config?.user?.id, work.value))
const legacyLink = computed(() => Boolean(work.value) && !linkFromCreation.value && !workNeedsPreparation(work.value, state.value))
const showPicker = computed(() => !legacyLink.value && !state.value?.module_link?.folder?.id && (!props.inline || !contents.value || choosingNewFile.value))
const canRetryPreparation = computed(() => Boolean(state.value?.module_link?.folder?.id) && !legacyLink.value
    && (linkPreparation.value?.status === 'partial' || (!progress.value
        && (state.value?.exercise?.status === 'unknown' || (state.value?.exercise?.status === 'missing'
            && state.value.exercise.reusable !== 'linked')))))
let operation = 0
let controller = null

function resetRequest() {
    operation++
    controller?.abort()
    controller = new AbortController()
    busy.value = false
    folderBusy.value = false
}

function folderLinked() {
    if (workDropboxStates.get(key.value)?.folder?.id) opened.value = false
}

function progressClass(step) {
    if (progress.value.completed.includes(step)) return 'link-progress-done'
    if (progress.value.active === step) return progress.value.failed ? 'link-progress-failed' : 'link-progress-active'
    return 'link-progress-pending'
}

function progressText(step) {
    if (progress.value.completed.includes(step)) return 'Erledigt'
    if (progress.value.active === step) return progress.value.failed ? 'Nicht abgeschlossen' : 'Läuft …'
    return 'Ausstehend'
}

function message(exception) {
    return Object.values(exception.response?.data?.errors || {}).flat()[0]
        || exception.response?.data?.message || exception.message || 'Dropbox konnte nicht geprüft werden. Bitte erneut versuchen.'
}

async function openLink(value, selected = null, creationFlow = false, autoCommit = creationFlow === true) {
    if (!workAutomationEnabled(value)) return
    resetRequest()
    work.value = value
    linkFromCreation.value = creationFlow || Boolean(selected)
    autoCommitAuthorized.value = autoCommit === true && Boolean(selected?.contents && selected?.filename)
    state.value = null
    error.value = ''
    contents.value = selected?.contents || ''
    filename.value = selected?.filename || ''
    choosingNewFile.value = false
    resolved.value = null
    progress.value = null
    linkPreparation.value = null
    pondKey.value++
    opened.value = true
    const current = operation
    busy.value = true
    try {
        let result = await loadWorkDropbox(key.value, value.id, true, { signal: controller.signal })
        if (current !== operation) return
        if (result?.module_link?.folder?.id) {
            try {
                result = await loadWorkDropbox(key.value, value.id, true, { signal: controller.signal, params: { include_exercise: 1, refresh: 1 } })
            } catch (exception) {
                if (current !== operation) return
                result = { ...result, exercise: { status: 'unknown', message: message(exception) } }
            }
            if (current !== operation) return
            if (!result.exercise) result = { ...result, exercise: { status: 'unknown' } }
            workDropboxStates.set(key.value, result)
        }
        if (current === operation) {
            state.value = result
            if (result?.module_link?.folder?.id && !legacyLink.value) {
                if (result.exercise?.status === 'found' || result.exercise?.reusable === 'linked') {
                    emit('linked', work.value)
                    opened.value = false
                } else {
                    emit('link-saved', work.value)
                }
            } else if (selected && !legacyLink.value) {
                if (result.connected) await resolve(autoCommitAuthorized.value)
                else error.value = 'Die Arbeit wurde erstellt. Bitte Dropbox verbinden und die Verknüpfung abschließen.'
            }
        }
    } catch (exception) {
        if (current === operation) error.value = message(exception)
    } finally {
        if (current === operation) busy.value = false
    }
}

function retryConnection() {
    if (work.value && contents.value) openLink(work.value, { contents: contents.value, filename: filename.value }, true, autoCommitAuthorized.value)
}

function changeFile() {
    resetRequest()
    autoCommitAuthorized.value = false
    error.value = ''
    contents.value = ''
    filename.value = ''
    resolved.value = null
    progress.value = null
    choosingNewFile.value = true
    pondKey.value++
}

function defer() {
    if (busy.value) return
    resetRequest()
    autoCommitAuthorized.value = false
    opened.value = false
    emit('defer', Boolean(state.value?.module_link?.folder?.id))
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
    autoCommitAuthorized.value = false
    const current = operation
    resolved.value = null
    contents.value = ''
    filename.value = ''
    progress.value = null
    if (items.length !== 1) return
    if (!acceptFile(items[0])) return
    error.value = ''
    progress.value = { completed: [], active: 'file', failed: false }
    busy.value = true
    try {
        const selected = await readModuleHandshake(items[0].file)
        if (current !== operation) return
        contents.value = selected.contents
        filename.value = selected.filename
        choosingNewFile.value = false
        progress.value.completed.push('file')
        await resolve()
    } catch (exception) {
        if (current === operation) {
            error.value = message(exception)
            progress.value.failed = true
        }
    } finally {
        if (current === operation) busy.value = false
    }
}

async function resolve(autoLink = false) {
    resetRequest()
    const current = operation
    const currentKey = key.value
    error.value = ''
    resolved.value = null
    busy.value = true
    progress.value = { completed: ['file'], active: 'online', failed: false }
    try {
        const response = await axios.post(storeFolder.url(work.value.id), { handshake: contents.value, filename: filename.value, resolve: true },
            { signal: controller.signal, timeout: 120000 })
        if (current === operation && key.value === currentKey) {
            resolved.value = response.data
            if (response.data?.can_link && response.data?.proof && !response.data?.conflict) {
                progress.value.completed.push('online')
                progress.value.active = null
            } else {
                progress.value.failed = true
            }
        }
    } catch (exception) {
        if (current === operation) {
            error.value = message(exception)
            progress.value.failed = true
        }
    } finally {
        if (current === operation) busy.value = false
    }
    if (autoLink === true && current === operation && key.value === currentKey
        && resolved.value?.can_link && resolved.value.proof && !resolved.value.conflict) {
        await link()
    }
}

async function link() {
    if (!resolved.value?.can_link || !resolved.value.proof || resolved.value.conflict || busy.value) return
    const current = operation
    const currentKey = key.value
    busy.value = true
    error.value = ''
    progress.value.active = 'save'
    progress.value.failed = false
    try {
        const response = await axios.post(storeFolder.url(work.value.id), { handshake: contents.value, filename: filename.value, proof: resolved.value.proof },
            { signal: controller.signal, timeout: 120000 })
        if (current !== operation || key.value !== currentKey) return
        applyLinkResult(response.data, currentKey)
    } catch (exception) {
        if (current === operation) {
            error.value = message(exception)
            resolved.value = null
            progress.value.failed = true
        }
    } finally {
        if (current === operation) busy.value = false
    }
}

function applyLinkResult(data, currentKey) {
    state.value = data
    workDropboxStates.set(currentKey, data)
    const linked = Boolean(data?.module_link?.folder?.id)
    if (!linked) {
        error.value = 'Die Lernmodul-Verknüpfung konnte nicht bestätigt werden. Bitte erneut prüfen.'
        resolved.value = null
        if (progress.value) progress.value.failed = true
        return
    }
    resolved.value = null
    linkPreparation.value = data?.link_preparation || { status: 'partial', message: 'Die Verknüpfung wurde gespeichert, der Prüfungsordner ist noch nicht bestätigt.' }
    emit('link-saved', work.value)
    if (progress.value && !progress.value.completed.includes('save')) progress.value.completed.push('save')
    if (progress.value) progress.value.active = null
    if (linked && ['initialized', 'already_prepared'].includes(linkPreparation.value.status)) {
        emit('linked', work.value)
        opened.value = false
    }
}

async function retryPreparation() {
    if (!work.value?.id || !state.value?.module_link?.folder?.id || busy.value) return
    resetRequest()
    const current = operation
    const currentKey = key.value
    error.value = ''
    busy.value = true
    progress.value = { completed: ['file', 'online'], active: 'save', failed: false }
    try {
        const response = await axios.post(storeFolder.url(work.value.id), { retry_preparation: true },
            { signal: controller.signal, timeout: 120000 })
        if (current === operation && key.value === currentKey) applyLinkResult(response.data, currentKey)
    } catch (exception) {
        if (current === operation) {
            error.value = message(exception)
            linkPreparation.value = { status: 'partial', message: 'Das Lernmodul ist verknüpft; der Prüfungsordner ist noch nicht bestätigt.' }
            progress.value.failed = true
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
watch(opened, value => { if (!value) { resetRequest(); emit('closed') } })
onBeforeUnmount(resetRequest)
defineExpose({ openLink })
</script>

<style scoped>
.module-folder-path {
    overflow-wrap: anywhere;
}
.link-progress { padding: 12px 14px; border: 1px solid #d8e4f0; border-radius: 10px; background: #f7fafc; color: #233b53; }
.link-progress ul { list-style: none; padding: 0; margin: 8px 0 0; }
.link-progress li { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; padding: 4px 0; font-size: 0.8rem; }
.link-progress-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 20px; width: 20px; height: 20px; border-radius: 50%; background: #e4ebf1; color: #63798c; font-weight: 700; }
.link-progress-done .link-progress-icon { background: #d6f2e3; color: #08783e; }
.link-progress-active .link-progress-icon { background: #d8eaff; color: #1565b8; }
.link-progress-failed .link-progress-icon { background: #fde3e3; color: #b42318; }
.link-progress-caption { margin-left: auto; color: #536d83; font-size: 0.72rem; }
.link-progress-done .link-progress-caption { color: #08783e; }
.link-progress-failed .link-progress-caption { color: #b42318; }
</style>
