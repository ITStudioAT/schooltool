<template>
    <span class="work-import-action" @click.stop>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-folder-upload-outline" :disabled="disabled" @click.stop="$emit('open', work, false)">Importieren</v-btn>
    </span>
    <span class="work-import-action work-quick-import-action" :title="quickImportHint" @click.stop>
        <v-btn class="work-quick-import" size="small" variant="flat" prepend-icon="mdi-folder-refresh-outline" :disabled="disabled || !canQuickImport"
        :title="quickImportHint"
        @click.stop="$emit('open', work, true)">Quick-Import</v-btn>
        <span v-if="!canQuickImport" class="work-quick-import-hint" role="status">{{ quickImportHint }}</span>
    </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, workDropboxStates, workDropboxKey } from '@/helpers/workDropbox'

const props = defineProps({ work: Object, disabled: Boolean })
const emit = defineEmits(['open'])
const admin = useAdminStore()
const directoryLoadError = ref(false)
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const canQuickImport = computed(() => Boolean(workDropboxStates.get(key.value)?.can_quick_import))
const quickImportHint = computed(() => directoryLoadError.value
    ? 'Dropbox-Verbindung konnte nicht geprüft werden. Bitte Importieren verwenden.'
    : (workDropboxStates.get(key.value)?.message || 'Dropbox-Verbindung prüfen …'))
watch(key, async value => {
    directoryLoadError.value = false
    try {
        await loadWorkDropbox(value, props.work?.id)
        const parameters = new URLSearchParams(window.location.search)
        if (value && key.value === value && parameters.get('dropbox_work') === String(props.work?.id)) emit('open', props.work, false)
    } catch { if (key.value === value) directoryLoadError.value = true }
}, { immediate: true })
</script>

<style scoped>
.work-import-action {
    display: inline-flex;
    max-width: 100%;
}
.work-quick-import-action {
    flex-direction: column;
    align-items: flex-start;
}
.work-quick-import-hint {
    margin-top: 4px;
    max-width: 32rem;
    color: #334155;
    font-size: 0.75rem;
    line-height: 1.4;
    white-space: normal;
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
