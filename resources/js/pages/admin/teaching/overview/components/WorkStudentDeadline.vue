<template>
    <div>
    <div class="d-flex flex-wrap align-center ga-2 text-caption" data-testid="work-dialog-student-deadline">
        <span>Frist für Schüler:innen (optional): <strong>{{ deadlineLabel }}</strong></span>
        <v-btn size="small" variant="text" prepend-icon="mdi-pencil-outline" :disabled="disabled || !work?.id" @click="openDialog">
            {{ current ? 'Ändern' : 'Festlegen' }}
        </v-btn>
        <span v-if="!work?.id">Arbeit zuerst speichern.</span>
    </div>
    <v-dialog v-model="opened" max-width="520" :persistent="saving">
        <v-card>
            <v-card-title>Frist für Schüler:innen</v-card-title>
            <v-card-text>
                <p class="text-body-2 mb-4">Diese Frist ist für die Schülerunterlagen vorgesehen. Die Uhrzeit ist optional. Sie darf nicht nach der endgültigen Abgabefrist liegen.</p>
                <v-text-field v-model="date" type="date" label="Datum (optional)" variant="outlined" clearable :disabled="saving" @update:model-value="clearTimeWithoutDate" />
                <WorkTimeInput v-model="time" :disabled="saving || !date" />
                <p class="text-caption mt-3">Nach einer Änderung müssen betroffene Schülerunterlagen und vorbereitete E-Mails aktualisiert werden.</p>
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <v-btn v-if="current" color="error" :disabled="saving" @click="removeDeadline">Frist entfernen</v-btn>
                <v-spacer />
                <v-btn :disabled="saving" @click="opened = false">Abbrechen</v-btn>
                <v-btn color="primary" :loading="saving" :disabled="saving || invalidTime" @click="saveDeadline">Speichern</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { studentDeadline } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import WorkTimeInput from './WorkTimeInput.vue'

const props = defineProps({ work: Object, disabled: Boolean })
const emit = defineEmits(['saved'])
const current = computed(() => props.work?.communicated_deadline || null)
const deadlineLabel = computed(() => {
    if (!current.value?.date) return 'Nicht festgelegt'
    const [year, month, day] = current.value.date.split('-')
    return `${day}.${month}.${year}${current.value.time ? ` · ${current.value.time}` : ''}`
})
const opened = ref(false)
const saving = ref(false)
const date = ref('')
const time = ref('')
const expected = ref(null)
const error = ref('')
const invalidTime = computed(() => Boolean(time.value && !/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(time.value)))

function openDialog() {
    if (props.disabled || !props.work?.id) return
    date.value = current.value?.date || ''
    time.value = current.value?.time || ''
    expected.value = { date: current.value?.date || null, time: current.value?.time || null }
    error.value = ''
    opened.value = true
}

function clearTimeWithoutDate(value) {
    if (!value) time.value = ''
}

function removeDeadline() {
    date.value = ''
    time.value = ''
    return saveDeadline()
}

async function saveDeadline() {
    if (saving.value || invalidTime.value || !props.work?.id) return
    const workId = props.work.id
    saving.value = true
    error.value = ''
    try {
        const response = await axios.post(studentDeadline.url(workId), {
            date: date.value || null, time: time.value || null, expected: expected.value,
        })
        if (String(props.work?.id) !== String(workId)) return
        if (String(response.data?.data?.id) !== String(workId)) throw new Error('Die gespeicherte Arbeit konnte nicht bestätigt werden.')
        emit('saved', response.data)
        opened.value = false
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || failure.message || 'Die Schülerfrist konnte nicht gespeichert werden.'
    } finally {
        saving.value = false
    }
}

watch(() => props.work?.id, () => { opened.value = false })
</script>
