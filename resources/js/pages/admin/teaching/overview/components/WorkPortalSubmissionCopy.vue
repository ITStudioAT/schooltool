<template>
    <v-btn v-if="available" :min-height="recommended ? 48 : 44" size="small" variant="tonal" prepend-icon="mdi-dropbox"
        :color="recommended ? 'primary' : undefined" :disabled="disabled || running"
        :aria-label="portalCopyLabel" :title="portalCopyLabel" aria-haspopup="dialog" @click.stop="copyOriginals">{{ portalCopyLabel }}</v-btn>
    <v-dialog v-if="opened" v-model="opened" max-width="640" scrollable :persistent="running">
        <v-card>
            <v-card-title class="text-wrap">{{ portalCopyLabel }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <p v-if="running && !files.length" role="status">Termingruppe und Prüfungsordner werden geprüft …</p>
                <p v-if="files.length" role="status" aria-live="polite">
                    {{ counts.copied }} kopiert · {{ counts.existing }} bereits vorhanden · {{ counts.open }} offen
                    <span v-if="running"> · {{ counts.pending + counts.copying }} ausstehend</span>
                </p>
                <p v-if="!running && !error && !files.length" role="status">Für diesen Termin liegen keine aktiven Portalabgaben vor.</p>
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3">{{ error }}</v-alert>
                <ul v-if="rows.length" class="portal-copy-people mt-3" aria-label="Kandidat:innen und Kopierstatus">
                    <li v-for="row in rows" :key="row.id" class="portal-copy-person"
                        :class="{ 'portal-copy-person--active': row.state === 'copying' }"
                        :aria-current="row.state === 'copying' ? 'true' : undefined">
                        <strong>{{ row.name }}</strong>
                        <span class="portal-copy-person__status" :class="row.state === 'complete' ? 'text-success' : row.state === 'open' ? 'text-error' : ''">
                            <v-progress-circular v-if="row.state === 'copying'" size="16" width="2" indeterminate aria-hidden="true" />
                            <v-icon v-else-if="row.state === 'complete'" icon="mdi-check-circle" size="18" aria-hidden="true" />
                            <v-icon v-else-if="row.state === 'open'" icon="mdi-alert-circle-outline" size="18" aria-hidden="true" />
                            {{ row.label }}
                        </span>
                        <ul v-if="row.openFiles.length" class="portal-copy-person__errors">
                            <li v-for="file in row.openFiles" :key="file.item"><strong>{{ file.name }}</strong> · {{ file.message }}</li>
                        </ul>
                    </li>
                </ul>
            </v-card-text>
            <v-card-actions class="portal-copy-actions">
                <v-btn v-if="!running && counts.open && available" min-height="44" :disabled="disabled"
                    @click="copyOriginals">Erneut prüfen und kopieren</v-btn>
                <v-btn variant="text" min-height="44" :disabled="running" @click="opened = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { copyPortalFile, preparePortalCopy } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkSubmissionController'
import { portalCopyLabel, workHasPortalFiles } from '@/helpers/workProgress'

const props = defineProps({
    work: { type: Object, required: true },
    date: { type: String, default: '' },
    closed: Boolean,
    recommended: Boolean,
    disabled: Boolean,
})
const emit = defineEmits(['started', 'saved'])
const available = computed(() => props.work.status?.submission_method?.channel === 'portal'
    && Boolean(props.work.status?.student_distribution?.ended_at)
    && props.closed && workHasPortalFiles(props.work, props.date))
const opened = ref(false)
const running = ref(false)
const error = ref('')
const files = ref([])
const candidates = ref([])
const counts = computed(() => files.value.reduce((total, file) => {
    total[file.status]++
    return total
}, { copied: 0, existing: 0, open: 0, pending: 0, copying: 0 }))
const people = computed(() => {
    const rows = new Map(candidates.value.map(candidate => [String(candidate.id), { ...candidate, files: [] }]))
    for (const file of files.value) {
        const id = String(file.candidate_id ?? `file-${file.item}`)
        if (!rows.has(id)) {
            rows.set(id, { id, name: file.student_name || 'Nicht eindeutig zugeordnet', files: [] })
        }
        rows.get(id).files.push(file)
    }
    return [...rows.values()]
})
const rows = computed(() => people.value.map(row => {
    const total = row.files.length
    const ready = row.files.filter(file => ['copied', 'existing'].includes(file.status)).length
    const openFiles = row.files.filter(file => file.status === 'open')
    const state = row.files.some(file => file.status === 'copying') ? 'copying'
        : row.files.some(file => file.status === 'pending') ? 'pending'
            : openFiles.length ? 'open' : total ? 'complete' : 'empty'
    const labels = { copying: 'Wird bearbeitet', pending: 'Wartet', open: 'Offen', complete: 'Abgelegt', empty: 'Keine Portalabgabe' }
    return { ...row, state, openFiles, label: total > 1 ? `${labels[state]} · ${ready}/${total} Dateien` : labels[state] }
}))
let controller

function cancel() {
    controller?.abort()
    running.value = false
    opened.value = false
}

async function copyOriginals() {
    if (!available.value || props.disabled || running.value) return
    const workId = props.work.id
    const date = props.date
    controller = new AbortController()
    const { signal } = controller
    emit('started')
    opened.value = true
    running.value = true
    files.value = []
    candidates.value = []
    error.value = ''
    let progress = null

    try {
        const response = await axios.post(preparePortalCopy.url(workId), date ? { date } : {}, { signal })
        if (signal.aborted) return
        const plan = response.data?.data
        if (String(plan?.work_id) !== String(workId) || (date && plan.date !== date) || !Array.isArray(plan.files)) {
            throw new Error('Der Kopierlauf konnte nicht eindeutig bestätigt werden.')
        }
        candidates.value = Array.isArray(plan.candidates) ? plan.candidates : []
        files.value = plan.files.map(file => ({ ...file, status: file.message ? 'open' : 'pending' }))
        for (const file of people.value.flatMap(person => person.files)) {
            if (signal.aborted) return
            if (file.status !== 'pending') continue
            if (!plan.run_id) throw new Error('Für die Dateien fehlt ein gültiger Kopierlauf.')

            file.status = 'copying'
            const result = await axios.post(copyPortalFile.url(workId), { run_id: plan.run_id, item: file.item }, { signal })
            if (signal.aborted) return
            const outcome = result.data?.data
            if (!['copied', 'existing', 'open'].includes(outcome?.status)) {
                throw new Error('Eine Dropbox-Kopie konnte nicht bestätigt werden.')
            }
            file.status = outcome.status
            file.message = outcome.message
            if (outcome.portal_copy_progress && typeof outcome.portal_copy_progress === 'object') progress = outcome.portal_copy_progress
        }
    } catch (failure) {
        if (signal.aborted) return
        error.value = failure.response?.data?.message || failure.message || 'Der Kopierlauf wurde unterbrochen.'
        for (const file of files.value.filter(file => ['pending', 'copying'].includes(file.status))) {
            file.status = 'open'
            file.message = 'Kopie noch nicht bestätigt. Der Kopierlauf kann erneut gestartet werden.'
        }
    } finally {
        if (!signal.aborted) {
            running.value = false
            if (progress) emit('saved', { ...props.work, portal_copy_progress: { ...props.work.portal_copy_progress, ...progress } })
        }
    }
}

watch([() => props.work.id, () => props.date], cancel)
onBeforeUnmount(cancel)
</script>

<style scoped>
.portal-copy-people { list-style: none; padding: 0; }
.portal-copy-person {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: .25rem .75rem;
    align-items: start;
    padding: .5rem .625rem;
    border-block-start: 1px solid rgba(var(--v-border-color), .16);
    overflow-wrap: anywhere;
}
.portal-copy-person--active { background-color: rgba(var(--v-theme-primary), .09); }
.portal-copy-person__status { display: flex; align-items: center; gap: .35rem; font-size: .875rem; }
.portal-copy-person__status :deep(.v-icon), .portal-copy-person__status :deep(.v-progress-circular) { flex: none; }
.portal-copy-person__errors { grid-column: 1 / -1; padding-inline-start: 1rem; font-size: .875rem; }
.portal-copy-actions { flex-wrap: wrap; justify-content: flex-end; }
.portal-copy-actions :deep(.v-btn) { height: auto; max-inline-size: 100%; }
.portal-copy-actions :deep(.v-btn__content) { min-inline-size: 0; white-space: normal; overflow-wrap: anywhere; }
@media (max-width: 400px) {
    .portal-copy-person { grid-template-columns: minmax(0, 1fr); }
}
</style>
