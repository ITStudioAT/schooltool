<template>
    <span v-if="workAutomationEnabled(work)" class="work-import-action" @click.stop>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-folder-upload-outline" :disabled="disabled" @click.stop="$emit('open', work, false)">Importieren</v-btn>
    </span>
    <span v-if="workAutomationEnabled(work) && canQuickImport" class="work-import-action work-quick-import-action" :title="quickImportHint" @click.stop>
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

const props = defineProps({ work: Object, disabled: Boolean })
const emit = defineEmits(['open'])
const admin = useAdminStore()
const confirmedKey = ref(null)
const statusLoading = ref(false)
let statusRequestVersion = 0
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const canQuickImport = computed(() => !statusLoading.value && confirmedKey.value === key.value
    && workDropboxStates.get(key.value)?.can_quick_import === true)
const quickImportHint = computed(() => workDropboxStates.get(key.value)?.message || 'Dropbox-Ordner erneut lesen und Vorschau öffnen.')
watch(() => [key.value, props.work?.status?.folder_imported_at, workAutomationEnabled(props.work)], async ([value], previous) => {
    const requestVersion = ++statusRequestVersion
    confirmedKey.value = null
    statusLoading.value = false
    if (!value || !workAutomationEnabled(props.work)) return
    statusLoading.value = true
    try {
        await loadWorkDropbox(value, props.work?.id, previous?.[0] === value)
        if (requestVersion !== statusRequestVersion || key.value !== value) return
        confirmedKey.value = value
        const parameters = new URLSearchParams(window.location.search)
        if (value && key.value === value && parameters.get('dropbox_work') === String(props.work?.id)) emit('open', props.work, false)
    } catch {
        if (requestVersion === statusRequestVersion) confirmedKey.value = null
    } finally {
        if (requestVersion === statusRequestVersion) statusLoading.value = false
    }
}, { immediate: true })
</script>

<style scoped>
.work-import-action {
    align-self: flex-start;
    display: inline-flex;
    max-width: 100%;
    vertical-align: top;
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
