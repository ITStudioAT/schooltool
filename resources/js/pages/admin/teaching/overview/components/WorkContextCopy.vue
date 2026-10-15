<template>
    <div class="d-flex flex-column align-start ga-1" @click.stop>
        <v-btn
            size="small"
            variant="tonal"
            prepend-icon="mdi-content-copy"
            :disabled="!contextText || copying"
            :loading="loading || copying"
            @click.stop="copyContext">
            Kontext kopieren
        </v-btn>
        <span v-if="message" role="status" class="text-caption">{{ message }}</span>
        <template v-if="loadError">
            <span role="alert" class="text-caption text-error">Ordnerverknüpfung konnte nicht geprüft werden.</span>
            <v-btn size="small" variant="text" @click.stop="loadFolder">Erneut prüfen</v-btn>
        </template>
        <textarea
            v-if="manualCopy"
            ref="copyText"
            :value="contextText"
            readonly
            rows="4"
            aria-label="Arbeitskontext zum Kopieren"
            class="work-context-copy-text" />
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, workDropboxKey, workDropboxStates } from '@/helpers/workDropbox'

const props = defineProps({
    work: { type: Object, required: true },
    course: { type: Object, required: true },
    date: { type: String, default: '' },
})
const admin = useAdminStore()
const loading = ref(false)
const copying = ref(false)
const loadError = ref(false)
const message = ref('')
const manualCopy = ref(false)
const copyText = ref(null)
let sequence = 0
const key = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const isCurrentWork = computed(() => Boolean(key.value && props.course?.id
    && String(props.work.teaching_course_id) === String(props.course.id)))
const folderState = computed(() => workDropboxStates.get(key.value))

function singleLine(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim()
}

const contextText = computed(() => {
    if (!isCurrentWork.value || !folderState.value || loading.value || loadError.value) return ''
    const workTitle = [singleLine(props.work.type), singleLine(props.work.title)].filter(Boolean).join(' – ')
    const title = singleLine(props.course.title)
    const classes = Array.isArray(props.course.classes) ? props.course.classes : singleLine(props.course.classes).split(',')
    const missingClasses = classes.map(singleLine).filter(value => value && !title.includes(value))
    const courseTitle = [title, missingClasses.length ? `(${missingClasses.join(', ')})` : ''].filter(Boolean).join(' ')
    const date = /^\d{4}-\d{2}-\d{2}$/.test(props.date) ? props.date.split('-').reverse().join('.') : 'nicht angegeben'
    const folder = folderState.value.folder
    const folderText = folder?.id
        ? `${singleLine(folder.name)} (Dropbox-ID: ${singleLine(folder.id)}; kein Weblink gespeichert)`
        : 'nicht verknüpft'

    return `Arbeit: ${workTitle || 'Ohne Titel'} (ID: ${props.work.id})\nKurs: ${courseTitle || 'Ohne Bezeichnung'} (ID: ${props.course.id})\nDatum: ${date}\nOrdner: ${folderText}`
})

async function loadFolder() {
    const current = ++sequence
    loadError.value = false
    message.value = ''
    manualCopy.value = false
    copying.value = false
    loading.value = isCurrentWork.value
    if (!isCurrentWork.value) return
    try {
        await loadWorkDropbox(key.value, props.work.id)
    } catch {
        if (sequence === current) loadError.value = true
    } finally {
        if (sequence === current) loading.value = false
    }
}

async function copyContext() {
    if (!contextText.value || copying.value) return
    const current = sequence
    copying.value = true
    message.value = ''
    manualCopy.value = false
    try {
        await navigator.clipboard.writeText(contextText.value)
        if (sequence === current) message.value = 'Arbeitskontext kopiert.'
    } catch {
        if (sequence !== current) return
        manualCopy.value = true
        message.value = 'Automatisches Kopieren ist nicht verfügbar. Den markierten Text mit Strg+C kopieren.'
        await nextTick()
        if (sequence === current) {
            copyText.value?.focus()
            copyText.value?.select()
        }
    } finally {
        if (sequence === current) copying.value = false
    }
}

watch(() => [key.value, props.course?.id, props.date], loadFolder, { immediate: true })
</script>

<style scoped>
.work-context-copy-text {
    width: 100%;
    min-width: 260px;
    padding: 8px;
    border: 1px solid currentColor;
    border-radius: 4px;
    font-size: 0.75rem;
}
</style>
