<template>
    <section class="submission" aria-label="Meine Abgabe">
        <h4>Abgabe</h4>
        <p v-if="task.submission_deadline_at" class="text-body-2">
            Persönliche Upload-Frist: {{ formatViennaDateTime(task.submission_deadline_at) }}
        </p>
        <p v-if="(task.status === 'ended' && !reopened) || (method?.channel === 'portal' && expired)">Die Abgabe ist geschlossen.</p>
        <p v-else-if="!method">Die Lehrkraft hat den Abgabeweg noch nicht festgelegt.</p>
        <p v-else-if="method.channel === 'email'">
            <template v-if="released && !expired && method.email">
                Sende deine Datei per E-Mail an
                <a :href="mailLink">{{ method.email }}</a>.
            </template>
            <template v-else-if="expired">Die Frist für die E-Mail-Abgabe ist abgelaufen.</template>
            <template v-else>Die Abgabe erfolgt per E-Mail.</template>
        </p>
        <p v-else-if="!canUpload">Abgabe im Schülerbereich</p>
        <form v-else class="submission-form" @submit.prevent="send">
            <label :for="'submission-file-' + task.id">Datei auswählen <small>(bis 20 MB)</small></label>
            <input :id="'submission-file-' + task.id" ref="picker" type="file" :disabled="!!busy" @change="choose" />
            <v-btn variant="flat" color="primary" min-height="44" :disabled="busy || !selected" :loading="busy === 'upload'" type="submit">Datei abgeben</v-btn>
        </form>
        <ul v-if="task.submission_files?.length" class="submission-receipts">
            <li v-for="file in task.submission_files" :key="file.id">
                <span><strong>{{ file.name }}</strong><small>{{ file.withdrawn_at ? 'Zurückgezogen' : 'Abgegeben' }} · {{ formatTime(file.withdrawn_at || file.submitted_at) }}</small></span>
                <v-btn v-if="canUpload && !file.withdrawn_at" min-height="44" variant="text" :disabled="!!busy"
                    :loading="busy === file.id" @click="remove(file)">Zurückziehen</v-btn>
            </li>
        </ul>
        <p v-if="error" role="alert" class="text-error">{{ error }}</p>
        <p v-if="notice" role="status">{{ notice }}</p>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { formatViennaDateTime } from '@/helpers/date'
import { store, withdraw } from '@/routes/student/course-work/submissions'

const props = defineProps({ task: { type: Object, required: true }, courseId: { type: [String, Number], required: true }, expired: Boolean })
const emit = defineEmits(['saved', 'refresh'])
const method = computed(() => props.task.submission_method)
const released = computed(() => ['released', 'email_delivery'].includes(props.task.status || 'released'))
const reopened = computed(() => Boolean(props.task.submission_deadline_at) && !props.expired)
const canUpload = computed(() => method.value?.channel === 'portal' && props.task.can_submit
    && (released.value || reopened.value) && !props.expired)
const mailLink = computed(() => 'mailto:' + encodeURIComponent(method.value.email).replace('%40', '@') + '?subject=' + encodeURIComponent(props.task.title || 'Aufgabenabgabe'))
const selected = ref(null)
const picker = ref(null)
const busy = ref('')
const error = ref('')
const notice = ref('')
const dateFormatter = new Intl.DateTimeFormat('de-AT', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Vienna' })
const formatTime = value => dateFormatter.format(new Date(value))

function choose(event) {
    selected.value = event.target.files?.[0] || null
    error.value = ''
    notice.value = ''
    if (selected.value?.size > 20 * 1024 * 1024) {
        selected.value = null
        error.value = 'Eine Datei darf höchstens 20 MB groß sein.'
        event.target.value = ''
    }
}

function resetPicker() {
    selected.value = null
    if (picker.value) picker.value.value = ''
}

async function change(file) {
    if (busy.value || !canUpload.value || (!file && !selected.value)) return
    busy.value = file?.id || 'upload'
    error.value = ''
    notice.value = ''
    const route = { courseId: props.courseId, course_work: props.task.id }
    try {
        let response
        if (file) {
            response = await axios.delete(withdraw.url({ ...route, fileId: file.id }))
        } else {
            const data = new FormData()
            data.append('file', selected.value)
            response = await axios.post(store.url(route), data)
        }
        emit('saved', response.data.files)
        notice.value = file ? 'Datei zurückgezogen.' : 'Deine Datei ist abgegeben.'
        resetPicker()
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat()[0] || failure.response?.data?.message
            || 'Die Abgabe konnte nicht geändert werden. Bitte erneut versuchen.'
        if ([401, 403, 404, 409].includes(failure.response?.status)) emit('refresh')
    } finally {
        busy.value = ''
    }
}

function send() { return change(null) }
function remove(file) { return change(file) }

watch(canUpload, allowed => { if (!allowed) resetPicker() })
</script>

<style scoped>
.submission { border-top: 1px solid #e7ecf5; padding: 16px 24px; }
.submission h4 { margin-bottom: .3rem; }
.submission-form { display: flex; flex-wrap: wrap; gap: .5rem .8rem; align-items: center; }
.submission-form label { flex-basis: 100%; }
.submission-form input { max-width: 100%; min-width: 0; }
.submission-receipts { padding: 0; margin: .4rem 0 0; list-style: none; }
.submission-receipts li { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .25rem 0; }
.submission-receipts li span { display: grid; margin-right: auto; overflow-wrap: anywhere; }
@media (max-width: 600px) { .submission { padding: 16px 18px; } }
</style>
