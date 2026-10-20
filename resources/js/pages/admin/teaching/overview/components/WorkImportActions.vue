<template>
    <span v-if="workAutomationEnabled(work)" class="work-import-action flex-column align-start" @click.stop>
        <v-btn v-if="!hasLink" size="small" variant="tonal" prepend-icon="mdi-link-variant" :disabled="disabled || statusLoading" @click.stop="$emit('link', work)">Verknüpfen</v-btn>
        <v-menu v-else v-model="connectionMenu" location="bottom start" :max-width="520">
            <template #activator="{ props: activatorProps }">
                <v-btn v-bind="activatorProps" size="small" variant="text" prepend-icon="mdi-link-variant"
                    append-icon="mdi-chevron-down" :disabled="disabled || unlinking || statusLoading" :loading="unlinking" @click.stop>Verbindung</v-btn>
            </template>
            <v-list density="compact" aria-label="Verbindung" @click.stop>
                <div class="px-4 py-2 text-caption connection-folder-path" aria-label="Dropbox-Ordnerpfad">{{ folderPath || (pathError ? 'Dropbox-Ordnerpfad konnte nicht geladen werden.' : 'Dropbox-Ordnerpfad wird geladen …') }}</div>
                <v-list-item title="Verbindung trennen" prepend-icon="mdi-link-variant-off"
                    :disabled="disabled || unlinking || statusLoading || !state?.link_version" @click.stop="unlink" />
            </v-list>
        </v-menu>
        <span v-if="unlinkError" role="alert" class="text-caption text-error mt-1">{{ unlinkError }}</span>
    </span>
    <span v-if="!linkOnly && workAutomationEnabled(work) && canQuickImport" class="work-import-action work-quick-import-action" :title="quickImportHint" @click.stop>
        <v-btn class="work-quick-import" size="small" variant="flat" prepend-icon="mdi-folder-refresh-outline" :disabled="disabled"
        :title="quickImportHint"
        @click.stop="$emit('open', work, true)">Quick-Import</v-btn>
    </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, workDropboxStates, workDropboxKey } from '@/helpers/workDropbox'
import { show, storeFolder } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkDropboxController'

const props = defineProps({ work: Object, disabled: Boolean, linkOnly: Boolean })
const emit = defineEmits(['open', 'link'])
const admin = useAdminStore()
const confirmedKey = ref(null)
const statusLoading = ref(false)
const unlinking = ref(false)
const unlinkError = ref('')
const connectionMenu = ref(false)
const pathError = ref(false)
let statusRequestVersion = 0
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const state = computed(() => workDropboxStates.get(key.value))
const hasLink = computed(() => Boolean(state.value?.module_link?.folder?.id || state.value?.folder?.id))
const folderPath = computed(() => state.value?.module_link?.folder?.path || state.value?.folder?.path || '')
const canQuickImport = computed(() => !statusLoading.value && confirmedKey.value === key.value
    && workDropboxStates.get(key.value)?.can_quick_import === true)
const quickImportHint = computed(() => workDropboxStates.get(key.value)?.message || 'Dropbox-Ordner erneut lesen und Vorschau öffnen.')

async function unlink() {
    if (props.disabled || unlinking.value || statusLoading.value || !hasLink.value || !state.value?.link_version) return
    const currentKey = key.value
    const currentRequest = statusRequestVersion
    const version = state.value.link_version
    connectionMenu.value = false
    unlinking.value = true
    unlinkError.value = ''
    try {
        const response = await axios.post(storeFolder.url(props.work.id), { unlink: true, link_version: version }, { timeout: 45000 })
        if (key.value === currentKey && currentRequest === statusRequestVersion) workDropboxStates.set(currentKey, response.data)
    } catch (exception) {
        if (key.value === currentKey && currentRequest === statusRequestVersion) {
            unlinkError.value = Object.values(exception.response?.data?.errors || {}).flat()[0]
                || exception.response?.data?.message || 'Verbindung konnte nicht getrennt werden. Bitte erneut versuchen.'
        }
    } finally {
        if (key.value === currentKey && currentRequest === statusRequestVersion) unlinking.value = false
    }
}

watch(() => [key.value, props.work?.status?.folder_imported_at, workAutomationEnabled(props.work)], async ([value], previous) => {
    const requestVersion = ++statusRequestVersion
    confirmedKey.value = null
    unlinkError.value = ''
    unlinking.value = false
    connectionMenu.value = false
    statusLoading.value = false
    if (!value || !workAutomationEnabled(props.work)) return
    statusLoading.value = true
    try {
        await loadWorkDropbox(value, props.work?.id, previous?.[0] === value)
        if (requestVersion !== statusRequestVersion || key.value !== value) return
        confirmedKey.value = value
        const parameters = new URLSearchParams(window.location.search)
        if (value && key.value === value && parameters.get('dropbox_work') === String(props.work?.id)) emit('link', props.work)
    } catch {
        if (requestVersion === statusRequestVersion) confirmedKey.value = null
    } finally {
        if (requestVersion === statusRequestVersion) statusLoading.value = false
    }
}, { immediate: true })

watch(connectionMenu, async opened => {
    pathError.value = false
    if (!opened || folderPath.value || !hasLink.value) return
    const currentKey = key.value
    const currentState = state.value
    try {
        const response = await axios.get(show.url(props.work.id), { params: { include_folder_path: 1 } })
        if (currentKey === key.value && state.value === currentState) workDropboxStates.set(currentKey, response.data)
    } catch {
        if (currentKey === key.value && state.value === currentState) pathError.value = true
    }
})
</script>

<style scoped>
.work-import-action {
    align-self: flex-start;
    display: inline-flex;
    max-width: 100%;
    vertical-align: top;
}
.connection-folder-path {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.work-quick-import {
    background-color: #244c81;
    color: #fff;
}
.work-quick-import:hover {
    background-color: #193b68;
}
.work-quick-import:focus-visible {
    outline: 2px solid #244c81;
    outline-offset: 3px;
}
.work-quick-import.v-btn--disabled {
    background-color: #e2e8f0;
    color: #334155;
    opacity: 1;
}
.work-quick-import.v-btn--disabled :deep(.v-btn__overlay) {
    opacity: 0;
}
</style>
