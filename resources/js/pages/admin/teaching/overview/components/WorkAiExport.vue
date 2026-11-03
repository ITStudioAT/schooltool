<template>
    <v-btn v-if="canCreateFolder" size="small" variant="tonal" prepend-icon="mdi-robot-outline" title="Leistungsfeststellung erstellen" aria-label="Leistungsfeststellung erstellen" :disabled="loading" @click.stop="openExport">Leistungsfeststellung erstellen</v-btn>
    <v-dialog v-model="open" scrollable max-width="900">
        <v-card>
            <v-card-title class="text-wrap text-body-1 font-weight-bold">Leistungsfeststellung erstellen · {{ result?.context?.work?.title || 'Auftrag laden' }}</v-card-title>
            <v-card-text>
                <p class="text-caption mb-2">Frisch geladene, gespeicherte Arbeitsdaten und vollständiger Kursbestand. Ungespeicherte Änderungen zuerst speichern.</p>
                <p v-if="loading" role="status">Arbeitsdaten und Kursliste laden …</p>
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-2">{{ error }}</v-alert>
                <template v-if="result">
                    <p class="text-body-2 mb-2">{{ result.participants_count }} Personen im Kurs · {{ result.active_participants_count }} aktiv. Auftrag vollständig kopieren und in Codex einfügen.</p>
                    <details v-if="result.warnings.length" class="mb-2 text-caption">
                        <summary>Ordner und fehlende Angaben prüfen ({{ result.warnings.length }})</summary>
                        <p v-for="warning in result.warnings" :key="warning" class="mt-1">{{ warning }}</p>
                    </details>
                    <textarea ref="exportText" :value="result.text" readonly spellcheck="false" aria-label="Vollständiger Auftrag an Codex" class="work-ai-export-text" />
                    <v-alert v-if="copyMessage" :type="copySucceeded ? 'success' : 'warning'" variant="tonal" density="compact" class="mt-2" role="status">{{ copyMessage }}</v-alert>
                </template>
            </v-card-text>
            <v-card-actions class="work-ai-export-actions">
                <v-btn :disabled="loading" @click="loadExport">Erneut laden</v-btn>
                <v-spacer />
                <v-btn :color="copySucceeded ? 'success' : 'primary'" :disabled="!result || loading || !isCurrentWork"
                    :prepend-icon="copySucceeded ? 'mdi-check' : 'mdi-content-copy'" @click="copyExport">{{ copySucceeded ? 'Kopiert' : 'Auftrag kopieren' }}</v-btn>
                <v-btn @click="open = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { aiExport } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { loadWorkDropbox, workDropboxKey, workDropboxStates } from '@/helpers/workDropbox'

const props = defineProps({ work: { type: Object, default: null } })
const courseStore = useCourseStore()
const admin = useAdminStore()
const directoryLoading = ref(true)
const directoryLoadError = ref(false)
const directoryKey = computed(() => workDropboxKey(admin.config?.user?.id, props.work))
const directoryState = computed(() => workDropboxStates.get(directoryKey.value))
const canCreateFolder = computed(() => Boolean(isCurrentWork.value && !directoryLoading.value && !directoryLoadError.value
    && directoryState.value?.module_link?.folder?.id && Object.hasOwn(directoryState.value, 'folder') && !directoryState.value.folder?.id))
const open = ref(false)
const loading = ref(false)
const result = ref(null)
const error = ref('')
const exportText = ref(null)
const copyMessage = ref('')
const copySucceeded = ref(false)
let requestSequence = 0
const isCurrentWork = computed(() => Boolean(workAutomationEnabled(props.work) && props.work?.id && courseStore.selected_course?.id
    && String(props.work.teaching_course_id) === String(courseStore.selected_course.id)))

function clearExport() {
    requestSequence++
    loading.value = false
    result.value = null
    error.value = ''
    copyMessage.value = ''
    copySucceeded.value = false
}

async function openExport() {
    if (!canCreateFolder.value) return
    open.value = true
    await loadExport()
}

async function loadExport() {
    clearExport()
    if (!open.value || !canCreateFolder.value) return
    const sequence = requestSequence
    const workId = props.work.id
    const courseId = props.work.teaching_course_id
    loading.value = true
    try {
        const response = await axios.get(aiExport.url(workId))
        if (sequence !== requestSequence || !open.value || !isCurrentWork.value) return
        const data = response.data.data
        if (String(data.work_id) !== String(workId) || String(data.course_id) !== String(courseId)
            || data.roster_complete !== true || !Number.isInteger(data.participants_count) || data.participants_count <= 0
            || typeof data.text !== 'string' || !data.text.trim() || !data.context?.work || !Array.isArray(data.warnings)) {
            throw new Error('Arbeits- oder Kursliste ist nicht vollständig bestätigt. Bitte erneut laden.')
        }
        result.value = data
    } catch (exception) {
        if (sequence === requestSequence) {
            error.value = Object.values(exception.response?.data?.errors || {}).flat().join(' ')
                || exception.message || 'Der Auftrag zum Erstellen der Leistungsfeststellung konnte nicht geladen werden.'
        }
    } finally {
        if (sequence === requestSequence) loading.value = false
    }
}

async function copyExport() {
    if (!result.value || loading.value || !canCreateFolder.value) return
    await loadExport()
    if (!result.value || !open.value || !isCurrentWork.value) return
    await nextTick()
    const sequence = requestSequence
    copySucceeded.value = false
    try {
        await navigator.clipboard.writeText(result.value.text)
        if (sequence === requestSequence) {
            copySucceeded.value = true
            copyMessage.value = 'Der vollständige Auftrag wurde kopiert.'
        }
    } catch {
        if (sequence !== requestSequence) return
        exportText.value?.focus()
        exportText.value?.select()
        copyMessage.value = 'Automatisches Kopieren ist nicht verfügbar. Der vollständige Text ist markiert. Drücken Sie Strg+C oder wählen Sie im Kontextmenü „Kopieren“.'
    }
}

watch(() => [directoryKey.value, courseStore.selected_course?.id, workAutomationEnabled(props.work)], () => {
    open.value = false
    clearExport()
})
watch(() => [directoryKey.value, props.work?.status?.folder_imported_at, isCurrentWork.value], async ([key], previous) => {
    directoryLoadError.value = false
    const fresh = previous?.[0] === key
    directoryLoading.value = fresh || !workDropboxStates.has(key)
    if (!isCurrentWork.value || !key) return
    try {
        await loadWorkDropbox(key, props.work.id, fresh)
    } catch {
        if (directoryKey.value === key) directoryLoadError.value = true
    } finally {
        if (directoryKey.value === key) directoryLoading.value = false
    }
}, { immediate: true })
watch(open, value => { if (!value) clearExport() })
watch(canCreateFolder, allowed => { if (!allowed) open.value = false })
onBeforeUnmount(clearExport)
</script>

<style scoped>
.work-ai-export-text { width: 100%; height: 45vh; min-height: 220px; padding: 12px; border: 1px solid rgba(var(--v-theme-on-surface), 0.3); border-radius: 4px; background: rgb(var(--v-theme-surface)); color: inherit; font: 13px/1.45 monospace; resize: vertical; }
.work-ai-export-text:focus { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: 2px; }
summary { cursor: pointer; }
.v-card-title { overflow-wrap: anywhere; }
.work-ai-export-actions { flex-wrap: wrap; }
@media (max-width: 600px) { .work-ai-export-actions :deep(.v-btn__content) { white-space: normal; } }
</style>
