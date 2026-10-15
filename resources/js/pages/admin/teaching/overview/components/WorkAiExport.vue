<template>
    <v-btn v-if="work?.id && workAutomationEnabled(work)" size="small" variant="tonal" prepend-icon="mdi-robot-outline" :disabled="!isCurrentWork || loading" @click.stop="openExport">KI-Export</v-btn>
    <v-dialog v-model="open" scrollable max-width="900">
        <v-card>
            <v-card-title class="text-wrap text-body-1 font-weight-bold">KI-Export · {{ result?.context?.work?.title || 'Auftrag laden' }}</v-card-title>
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
                <v-btn color="primary" :disabled="!result || loading || !isCurrentWork" prepend-icon="mdi-content-copy" @click="copyExport">Auftrag kopieren</v-btn>
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

const props = defineProps({ work: { type: Object, default: null } })
const courseStore = useCourseStore()
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
    if (!isCurrentWork.value) return
    open.value = true
    await loadExport()
}

async function loadExport() {
    clearExport()
    if (!open.value || !isCurrentWork.value) return
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
                || exception.message || 'KI-Export konnte nicht geladen werden.'
        }
    } finally {
        if (sequence === requestSequence) loading.value = false
    }
}

async function copyExport() {
    if (!result.value || loading.value || !isCurrentWork.value) return
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

watch(() => [props.work?.id, props.work?.teaching_course_id, courseStore.selected_course?.id, workAutomationEnabled(props.work)], () => {
    open.value = false
    clearExport()
})
watch(open, value => { if (!value) clearExport() })
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
