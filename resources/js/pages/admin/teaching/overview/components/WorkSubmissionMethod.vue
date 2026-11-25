<template>
    <v-btn variant="tonal" size="small" min-height="44" prepend-icon="mdi-inbox-arrow-down-outline"
        :disabled="disabled || !work?.id" @click="open">
        {{ method || releaseEnded ? 'Abgabeweg verwalten' : 'Abgabeweg festlegen' }}
    </v-btn>
    <v-dialog v-model="dialog" max-width="590" scrollable :persistent="saving">
        <v-card v-if="dialog">
            <v-card-title class="text-wrap">Abgabeweg {{ method || releaseEnded ? 'verwalten' : 'festlegen' }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <WorkSubmissionFields v-model:channel="channel" v-model:email="email"
                    :disabled="saving || disabled" :lock-reason="lockReason" :portal-closure-notice="portalClosureNotice" />
                <p v-if="!releaseEnded" class="text-body-2 mt-4">Abgaben können erst nach „Freigabe beenden“ eingesammelt werden.</p>
                <div v-if="releaseEnded && submissions.length" class="mt-5">
                    <h3 class="text-subtitle-1 font-weight-bold">Abgaben im Schülerbereich</h3>
                    <ul class="submission-files">
                        <li v-for="file in submissions" :key="file.id">
                            <div>
                                <strong>{{ file.student_name }}</strong>
                                <span>{{ file.name }}</span>
                                <small>{{ file.withdrawn_at ? 'Zurückgezogen' : 'Abgegeben' }} · {{ dateTime(file.withdrawn_at || file.submitted_at) }}</small>
                            </div>
                            <v-btn v-if="!file.withdrawn_at" variant="text" icon="mdi-download-outline" size="small"
                                :href="download.url({ course_work: work.id, fileId: file.id })"
                                :download="file.name" :aria-label="file.name + ' herunterladen'" />
                        </li>
                    </ul>
                </div>
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <v-spacer />
                <v-btn variant="text" min-height="44" :disabled="saving" @click="dialog = false">Schließen</v-btn>
                <v-btn v-if="!lockReason" color="primary" variant="flat" min-height="44" :loading="saving"
                    :disabled="saving || !channel || (channel === 'email' && !email)" @click="save(channel)">Speichern</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { download, update } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkSubmissionController'

import WorkSubmissionFields from './WorkSubmissionFields.vue'

const props = defineProps({ work: { type: Object, required: true }, disabled: Boolean, portalClosureNotice: String })
const emit = defineEmits(['saved'])
const releaseEnded = computed(() => Boolean(props.work.status?.student_distribution?.ended_at))
const method = computed(() => props.work.status?.submission_method || null)
const submissions = computed(() => [...(props.work.status?.portal_submissions || [])].reverse())
const dialog = ref(false)
const channel = ref(null)
const email = ref('')
const expected = ref(null)
const error = ref('')
const saving = ref(false)
const serverLock = ref(null)
const lockReason = computed(() => {
    const reason = serverLock.value || props.work.submission_lock_reason
    if (releaseEnded.value) return 'ended'
    if (method.value?.channel === 'portal' && props.portalClosureNotice) return 'deadline'
    return expected.value || reason === 'email_pending' ? reason : null
})
const dateFormatter = new Intl.DateTimeFormat('de-AT', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Vienna' })
const dateTime = value => dateFormatter.format(new Date(value))

function open() {
    if (props.disabled || !props.work.id) return
    expected.value = method.value ? { ...method.value } : null
    channel.value = expected.value?.channel || null
    email.value = expected.value?.email || ''
    serverLock.value = null
    error.value = ''
    dialog.value = true
}

async function save(selection) {
    if (saving.value || props.disabled || lockReason.value || !selection) return
    const workId = props.work.id
    saving.value = true
    error.value = ''
    try {
        const { data } = await axios.put(update.url(workId), {
            channel: selection, email: selection === 'email' ? email.value : null, expected: expected.value,
        })
        if (workId !== props.work.id) return
        emit('saved', data.data)
        dialog.value = false
    } catch (failure) {
        const conflict = failure.response?.data
        if (workId === props.work.id && conflict?.submission_lock_reason) {
            serverLock.value = conflict.submission_lock_reason
            expected.value = conflict.current_submission
            channel.value = expected.value?.channel ?? null
            email.value = expected.value?.email ?? ''
        }
        if (workId === props.work.id) error.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || 'Der Abgabeweg konnte nicht gespeichert werden.'
    } finally {
        saving.value = false
    }
}

watch(() => props.work.id, () => { dialog.value = false })
</script>

<style scoped>
.submission-files { list-style: none; padding: 0; margin: .25rem 0 0; }
.submission-files li { display: flex; justify-content: space-between; align-items: center; gap: .75rem; border-top: 1px solid rgba(var(--v-theme-on-surface), .15); padding: .5rem 0; }
.submission-files li > div { display: grid; min-width: 0; overflow-wrap: anywhere; }
.submission-files small { color: rgba(var(--v-theme-on-surface), .72); }
</style>
