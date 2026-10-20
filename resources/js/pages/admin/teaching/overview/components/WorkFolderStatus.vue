<template>
    <v-icon
        v-if="workAutomationEnabled(work)"
        :size="size"
        :color="color"
        :title="tooltip"
        :aria-label="tooltip"
        :aria-hidden="false"
        role="img">
        mdi-link-variant
    </v-icon>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, workDropboxKey, workDropboxStates } from '@/helpers/workDropbox'

const props = defineProps({ work: { type: Object, required: true }, size: { type: [Number, String], default: 13 } })
const admin = useAdminStore()
const loadError = ref(false)
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const state = computed(() => workDropboxStates.get(key.value))
const linkedFolder = computed(() => state.value?.module_link?.folder || state.value?.folder)
const linked = computed(() => Boolean(linkedFolder.value?.id))
const color = computed(() => state.value ? (linked.value ? 'success' : 'error') : 'grey')
const tooltip = computed(() => {
    if (linked.value) return `Ordner verknüpft: ${linkedFolder.value.path || linkedFolder.value.name}`
    if (state.value) return 'Kein Ordner mit dieser Arbeit verknüpft'
    return loadError.value ? 'Ordnerverknüpfung konnte nicht geprüft werden' : 'Ordnerverknüpfung wird geprüft …'
})

watch(() => [key.value, workAutomationEnabled(props.work)], async ([value, enabled]) => {
    loadError.value = false
    if (!enabled) return
    try {
        await loadWorkDropbox(value, props.work.id)
    } catch {
        if (key.value === value) loadError.value = true
    }
}, { immediate: true })
</script>
