<template>
    <v-btn v-if="work?.id && (!portal || hasPortalFiles)" min-height="44" size="small" variant="tonal" prepend-icon="mdi-inbox-arrow-down-outline"
        :disabled="disabled || !available" @click.stop="openAction">{{ repeat && !portal ? 'Erneut einsammeln' : 'Abgaben einsammeln' }}</v-btn>
    <v-dialog v-if="opened" v-model="opened" max-width="720" scrollable :persistent="loading">
        <v-card>
            <v-card-title class="text-wrap">{{ portal ? 'Abgaben im Schülerbereich' : repeat ? 'Abgaben erneut einsammeln' : 'Abgaben einsammeln' }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <p v-if="!portal" class="mb-3">KI-Auftrag für das Einsammeln per E-Mail kopieren.</p>
                <p v-if="!portal && work.status?.submission_method?.email" class="text-body-2 mb-3">Abgabepostfach: {{ work.status.submission_method.email }}</p>
                <p v-if="loading" role="status">{{ portal ? 'Abgaben werden geladen …' : 'KI-Auftrag wird geladen …' }}</p>
                <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
                <template v-if="portal && result">
                    <p v-if="!result.files.length">Für diese Auswahl sind keine Portalabgaben gespeichert.</p>
                    <ul v-else class="collection-files">
                        <li v-for="file in result.files" :key="file.id">
                            <span><strong>{{ file.student_name }}</strong><br />{{ file.name }}</span>
                            <v-btn icon="mdi-download-outline" variant="text" :href="file.download_url" :download="file.name"
                                :aria-label="file.name + ' herunterladen'" />
                        </li>
                    </ul>
                </template>
                <template v-else-if="result">
                    <details><summary>Übergabetext anzeigen</summary><textarea ref="copyText" :value="result.text" readonly rows="10" aria-label="KI-Auftrag zum Einsammeln" class="collection-order-text" /></details>
                    <p v-if="message" role="status" class="mt-3" :class="copied ? 'text-success' : 'text-warning'">{{ message }}</p>
                </template>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn v-if="!portal" min-height="44" :disabled="!result || loading || copying || disabled || !available" :color="copied ? 'success' : 'primary'" :prepend-icon="copied ? 'mdi-check' : 'mdi-content-copy'" @click="copyOrder">{{ copied ? 'Kopiert' : 'Auftrag kopieren' }}</v-btn>
                <v-btn min-height="44" :disabled="loading" @click="opened = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { aiExport } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workHasPortalFiles } from '@/helpers/workProgress'
const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, repeat: Boolean, disabled: Boolean })
const channel = computed(() => props.work.status?.submission_method?.channel)
const portal = computed(() => channel.value === 'portal' || (!channel.value && !!props.work.status?.portal_submissions?.length))
const hasPortalFiles = computed(() => workHasPortalFiles(props.work, props.date))
const available = computed(() => Boolean(props.work.status?.student_distribution?.ended_at)
    && (portal.value ? hasPortalFiles.value : channel.value === 'email' || workAutomationEnabled(props.work)))
const opened = ref(false)
const loading = ref(false)
const copying = ref(false)
const copied = ref(false)
const result = ref(null)
const error = ref('')
const message = ref('')
const copyText = ref(null)
let sequence = 0
let controller = null

function clear() {
    sequence++
    controller?.abort()
    loading.value = false
    copying.value = false
    copied.value = false
    result.value = null
    error.value = ''
    message.value = ''
}

async function openAction() {
    if (props.disabled || !available.value || !props.work?.id) return
    clear()
    opened.value = true
    loading.value = true
    const operation = sequence
    const mode = props.repeat ? 'repeat' : 'collect'
    controller = new AbortController()
    try {
        const response = await axios.get(aiExport.url(props.work.id), { params: { purpose: 'submission_collection', collection_mode: mode,
            ...(props.date ? { date: props.date } : {}) }, signal: controller.signal })
        if (operation !== sequence || !opened.value) return
        const data = response.data?.data
        if (portal.value && data?.channel === 'portal' && String(data.work_id) === String(props.work.id)
            && String(data.course_id) === String(props.work.teaching_course_id) && Array.isArray(data.files)) {
            result.value = data
            return
        }
        if (String(data?.work_id) !== String(props.work.id) || String(data?.course_id) !== String(props.work.teaching_course_id)
            || data.roster_complete !== true || data.context?.purpose !== 'collect-exercise-submissions' || data.context?.mode !== mode
            || data.context?.operation?.collect_submissions !== true || data.context?.operation?.preserve_existing !== true
            || data.context?.operation?.grade !== false || data.context?.operation?.send !== false || data.context?.operation?.schooltool_import !== false
            || typeof data.text !== 'string' || !data.text.trim()) throw new Error('Der Einsammelauftrag konnte nicht eindeutig bestätigt werden.')
        result.value = data
    } catch (failure) {
        if (operation === sequence && !controller?.signal.aborted) error.value = failure.response?.data?.message || failure.message || 'KI-Auftrag konnte nicht geladen werden.'
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function copyOrder() {
    if (portal.value || !result.value || loading.value || copying.value || props.disabled || !available.value) return
    const operation = sequence
    copying.value = true
    copied.value = false
    message.value = ''
    try {
        await navigator.clipboard.writeText(result.value.text)
        if (operation === sequence) {
            copied.value = true
            message.value = 'KI-Auftrag kopiert. Jetzt an die KI übergeben.'
        }
    } catch {
        if (operation !== sequence) return
        const details = copyText.value?.closest('details')
        if (details) details.open = true
        copyText.value?.focus()
        copyText.value?.select()
        message.value = 'Automatisches Kopieren ist nicht verfügbar. Der Text ist markiert; bitte mit Strg+C kopieren.'
    } finally {
        if (operation === sequence) copying.value = false
    }
}

watch(() => [props.work?.id, props.work?.teaching_course_id, props.work?.updated_at, props.date, props.repeat], () => { opened.value = false; clear() })
watch(available, value => { if (!value && opened.value) { opened.value = false; clear() } })
watch(opened, value => { if (!value) clear() })
onBeforeUnmount(clear)
</script>

<style scoped>
.collection-order-text { width: 100%; margin-top: 12px; padding: 10px; border: 1px solid #aaa; border-radius: 4px; font: 12px/1.5 monospace; white-space: pre-wrap; }
.collection-files { list-style: none; padding: 0; }
.collection-files li { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-block: .5rem; border-bottom: 1px solid rgba(var(--v-border-color), .16); overflow-wrap: anywhere; }
</style>
