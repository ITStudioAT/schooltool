<template>
    <span v-if="workAutomationEnabled(work) && (linked || needsPreparation || !state)" class="d-inline-flex" :class="{ 'cursor-pointer': linked }"
        :role="linked ? 'button' : undefined" :tabindex="linked ? 0 : undefined" :aria-label="tooltip" :title="tooltip"
        @click.stop="openFolder" @keydown.enter.stop.prevent="openFolder" @keydown.space.stop.prevent="openFolder">
        <v-icon
            :size="size"
            :color="color"
            :aria-label="tooltip"
            :aria-hidden="false"
            role="img">
            mdi-link-variant
        </v-icon>
        <v-tooltip activator="parent" location="top" :text="tooltip" :open-delay="200" :max-width="420" />
        <WorkFolderBrowser ref="browser" :work="work" :date="date" module />
    </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workNeedsPreparation } from '@/helpers/workOverview'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, loadWorkExercise, workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import WorkFolderBrowser from './WorkFolderBrowser.vue'

const props = defineProps({ work: { type: Object, required: true }, size: { type: [Number, String], default: 13 }, date: { type: String, default: '' } })
const admin = useAdminStore()
const loadError = ref(false)
const browser = ref(null)
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const state = computed(() => workDropboxStates.get(key.value))
const needsPreparation = computed(() => workNeedsPreparation(props.work, state.value))
const linkedFolder = computed(() => state.value?.module_link?.folder || state.value?.legacy_folders?.module || state.value?.folder)
const linked = computed(() => Boolean(linkedFolder.value?.id))
const color = computed(() => state.value ? (linked.value ? 'success' : 'error') : 'grey')
const tooltip = computed(() => {
    if (linked.value) return state.value?.module_link || state.value?.legacy_folders?.module
        ? `Ordner verknüpft: ${linkedFolder.value.path || linkedFolder.value.name}` : 'Ordner verknüpft · Lernmodulordner öffnen'
    if (state.value) return 'Kein Ordner mit dieser Arbeit verknüpft'
    return loadError.value ? 'Ordnerverknüpfung konnte nicht geprüft werden' : 'Ordnerverknüpfung wird geprüft …'
})

function openFolder() {
    if (linked.value) browser.value?.open()
}

watch(() => [key.value, workAutomationEnabled(props.work)], async ([value, enabled]) => {
    loadError.value = false
    if (!enabled) return
    try {
        if (workExerciseCheck(value, props.work, props.date)) await loadWorkExercise(value, props.work, props.date)
        else await loadWorkDropbox(value, props.work.id)
    } catch {
        if (key.value === value) loadError.value = true
    }
}, { immediate: true })
</script>
