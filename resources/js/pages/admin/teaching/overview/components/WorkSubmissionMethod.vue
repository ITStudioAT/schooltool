<template>
    <v-btn variant="tonal" size="small" min-height="44" prepend-icon="mdi-inbox-arrow-down-outline"
        :disabled="disabled || !work?.id" @click="open">
        {{ method ? 'Abgabeweg verwalten' : 'Abgabeweg festlegen' }}
    </v-btn>
    <v-dialog v-model="dialog" max-width="590" scrollable :persistent="saving">
        <v-card v-if="dialog">
            <v-card-title class="text-wrap">Abgabe · {{ work.title }}</v-card-title>
            <v-card-text>
                <fieldset class="submission-methods" :disabled="saving">
                    <legend>Wo sollen die Schüler:innen abgeben?</legend>
                    <label><input v-model="channel" type="radio" :name="radioGroup" value="portal" /> Datei unter „Meine Aufgaben“ hochladen</label>
                    <label><input v-model="channel" type="radio" :name="radioGroup" value="email" /> Datei per E-Mail schicken</label>
                </fieldset>
                <v-text-field v-if="channel === 'email'" v-model.trim="email" type="email"
                    label="E-Mail-Adresse für die Abgabe" :disabled="saving" class="mt-3" hide-details="auto" />
                <div v-if="submissions.length" class="mt-5">
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
                <v-btn v-if="method" variant="text" min-height="44" :disabled="saving" @click="save(null)">Abgabeweg entfernen</v-btn>
                <v-spacer />
                <v-btn variant="text" min-height="44" :disabled="saving" @click="dialog = false">Schließen</v-btn>
                <v-btn color="primary" variant="flat" min-height="44" :loading="saving"
                    :disabled="saving || !channel || (channel === 'email' && !email)" @click="save(channel)">Speichern</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, ref, useId, watch } from 'vue'
import { download, update } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkSubmissionController'

const props = defineProps({ work: { type: Object, required: true }, disabled: Boolean })
const emit = defineEmits(['saved'])
const radioGroup = useId()
const method = computed(() => props.work.status?.submission_method || null)
const submissions = computed(() => [...(props.work.status?.portal_submissions || [])].reverse())
const dialog = ref(false)
const channel = ref(null)
const email = ref('')
const expected = ref(null)
const error = ref('')
const saving = ref(false)
const dateFormatter = new Intl.DateTimeFormat('de-AT', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Vienna' })
const dateTime = value => dateFormatter.format(new Date(value))

function open() {
    if (props.disabled || !props.work.id) return
    expected.value = method.value ? { ...method.value } : null
    channel.value = expected.value?.channel || null
    email.value = expected.value?.email || ''
    error.value = ''
    dialog.value = true
}

async function save(selection) {
    if (saving.value || props.disabled) return
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
        if (workId === props.work.id) error.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || 'Der Abgabeweg konnte nicht gespeichert werden.'
    } finally {
        saving.value = false
    }
}

watch(() => props.work.id, () => { dialog.value = false })
</script>

<style scoped>
.submission-methods { padding: 0; border: 0; }
.submission-methods legend { font-weight: 600; }
.submission-methods label { display: flex; gap: .6rem; align-items: center; min-height: 44px; cursor: pointer; }
.submission-methods input { accent-color: rgb(var(--v-theme-primary)); }
.submission-files { list-style: none; padding: 0; margin: .25rem 0 0; }
.submission-files li { display: flex; justify-content: space-between; align-items: center; gap: .75rem; border-top: 1px solid rgba(var(--v-theme-on-surface), .15); padding: .5rem 0; }
.submission-files li > div { display: grid; min-width: 0; overflow-wrap: anywhere; }
.submission-files small { color: rgba(var(--v-theme-on-surface), .72); }
</style>
