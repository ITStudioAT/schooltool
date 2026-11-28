<template>
    <span v-if="workAutomationEnabled(work) && (linked || needsPreparation || !state)" class="d-inline-flex">
        <v-btn v-if="iconOnly && linked" icon width="40" height="40" variant="text" :aria-label="actionLabel"
            aria-haspopup="dialog" :disabled="disabled" @click.stop="openFolder">
            <v-icon icon="mdi-link-variant" size="20" aria-hidden="true" />
            <v-tooltip activator="parent" location="top" open-on-focus :text="actionLabel" />
        </v-btn>
        <v-btn v-else-if="labelled && linked" size="small" min-height="44" variant="tonal" prepend-icon="mdi-link-variant"
            :disabled="disabled" @click.stop="openFolder">{{ actionLabel }}</v-btn>
        <span v-else-if="!labelled && !iconOnly" class="d-inline-flex" :class="{ 'cursor-pointer': linked }"
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
        </span>
        <WorkFolderBrowser ref="browser" :work="work" :date="date" :module="!directExerciseId" :direct-exercise-id="directExerciseId" />
    </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { workAssessmentActivity } from '@/helpers/workAssessmentRun'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workNeedsPreparation } from '@/helpers/workOverview'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, loadWorkExercise, workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'
import WorkFolderBrowser from './WorkFolderBrowser.vue'

const props = defineProps({ work: { type: Object, required: true }, size: { type: [Number, String], default: 13 }, date: { type: String, default: '' },
    directExerciseId: { type: String, default: '' }, labelled: Boolean, iconOnly: Boolean, disabled: Boolean })
const admin = useAdminStore()
const activity = computed(() => workAssessmentActivity(admin.config?.user?.id, props.work))
const loadError = ref(false)
const browser = ref(null)
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const state = computed(() => workDropboxStates.get(key.value))
const needsPreparation = computed(() => workNeedsPreparation(props.work, state.value))
const linkedFolder = computed(() => state.value?.module_link?.folder || state.value?.legacy_folders?.module || state.value?.folder)
const linked = computed(() => Boolean(linkedFolder.value?.id))
const actionLabel = computed(() => {
    if (props.directExerciseId) return 'Prüfungsordner ansehen'
    return activity.value ? 'Lernmodul ansehen' : 'Verbindung verwalten'
})
const color = computed(() => state.value ? (linked.value ? 'success' : 'error') : 'grey')
const tooltip = computed(() => {
    if (props.directExerciseId) return `Prüfungsordner ansehen: ${linkedFolder.value?.name || ''}`
    if (linked.value) return state.value?.module_link || state.value?.legacy_folders?.module
        ? `Ordner verknüpft: ${linkedFolder.value.path || linkedFolder.value.name}` : 'Ordner verknüpft · Lernmodulordner öffnen'
    if (state.value) return 'Kein Ordner mit dieser Arbeit verknüpft'
    return loadError.value ? 'Ordnerverknüpfung konnte nicht geprüft werden' : 'Ordnerverknüpfung wird geprüft …'
})

function openFolder() {
    if (linked.value && !props.disabled) browser.value?.open()
}

watch(() => [key.value, workAutomationEnabled(props.work)], async ([value, enabled]) => {
    loadError.value = false
    if (!enabled) return
    try {
        if (!props.labelled && !props.iconOnly && workExerciseCheck(value, props.work, props.date)) await loadWorkExercise(value, props.work, props.date)
        else await loadWorkDropbox(value, props.work.id)
    } catch {
        if (key.value === value) loadError.value = true
    }
}, { immediate: true })
</script>
