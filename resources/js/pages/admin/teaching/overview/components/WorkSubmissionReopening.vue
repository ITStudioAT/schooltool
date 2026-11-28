<template>
    <v-btn icon width="40" height="40" variant="text" aria-label="Portalabgabe wieder öffnen" aria-haspopup="dialog"
        :disabled="disabled" @click.stop="open">
        <v-icon icon="mdi-lock-open-outline" size="20" aria-hidden="true" />
        <v-tooltip activator="parent" location="top" open-on-focus text="Portalabgabe wieder öffnen" />
    </v-btn>
    <v-dialog v-model="opened" max-width="560" scrollable :persistent="saving">
        <v-card v-if="opened">
            <v-card-title class="text-wrap">Portalabgabe wieder öffnen</v-card-title>
            <v-card-text>
                <p v-if="options?.date" class="mb-3">Durchführung am {{ displayDate }}</p>
                <p v-if="loading" role="status">Teilnehmende werden geladen …</p>
                <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
                <template v-if="options">
                    <p v-if="options.ambiguous" class="text-body-2 mb-3">
                        Personen mit mehreren Durchführungsterminen werden bei dieser Wiederöffnung ausgelassen.
                    </p>
                    <ul v-if="alreadyOpen.length" class="mb-3 pl-5">
                        <li v-for="student in alreadyOpen" :key="student.id">
                            {{ student.name }} · offen bis {{ formatViennaDateTime(student.open_until) }}
                        </li>
                    </ul>
                    <p v-if="!eligible.length">Für diese Durchführung gibt es keine geschlossene Portalabgabe zum Wiederöffnen.</p>
                    <form v-else :id="formId" @submit.prevent="save">
                        <fieldset class="submission-reopening-choices" :disabled="saving || disabled">
                            <legend>Für wen soll der Upload wieder geöffnet werden?</legend>
                            <label><input v-model="scope" type="radio" :name="formId" value="all" /> Alle mit geschlossener Abgabe ({{ eligible.length }})</label>
                            <label><input v-model="scope" type="radio" :name="formId" value="selected" /> Einzelne Personen auswählen</label>
                            <div v-if="scope === 'selected'" class="ml-4">
                                <label v-for="student in eligible" :key="student.id">
                                    <input v-model="selected" type="checkbox" :value="student.id" /> {{ student.name }}
                                </label>
                            </div>
                            <label class="submission-reopening-deadline mt-3">
                                Neue Upload-Frist (Wiener Ortszeit)
                                <input v-model="deadline" type="datetime-local" required />
                            </label>
                            <p v-if="deadline && !deadlineValid" class="text-body-2 text-error mt-2" role="status">
                                Gib eine gültige zukünftige Upload-Frist in Wiener Ortszeit ein.
                            </p>
                        </fieldset>
                    </form>
                </template>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <v-spacer />
                <v-btn variant="text" min-height="44" :disabled="saving" @click="opened = false">Schließen</v-btn>
                <v-btn v-if="eligible.length" min-height="44" color="primary" variant="flat"
                    type="submit" :form="formId" :disabled="!canSave" :loading="saving">Wieder öffnen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, ref, useId, watch } from 'vue'
import { reopen, reopeningOptions } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkSubmissionController'
import { formatViennaDateTime, viennaDateTime, viennaDateTimeIso } from '@/helpers/date'

const props = defineProps({ work: { type: Object, required: true }, date: String, now: Number, disabled: Boolean })
const emit = defineEmits(['saved'])
const formId = useId()
const opened = ref(false)
const loading = ref(false)
const saving = ref(false)
const options = ref(null)
const error = ref('')
const scope = ref(null)
const selected = ref([])
const deadline = ref('')
const openedAt = ref(0)
const halfHour = 30 * 60 * 1000
const eligible = computed(() => (options.value?.students ?? []).filter(student => !student.open_until))
const alreadyOpen = computed(() => (options.value?.students ?? []).filter(student => student.open_until))
const displayDate = computed(() => options.value?.date?.split('-').reverse().join('.'))
const deadlineInstant = computed(() => Date.parse(viennaDateTimeIso(deadline.value)))
const deadlineValid = computed(() => deadlineInstant.value > Math.max(openedAt.value, props.now ?? 0))
const canSave = computed(() => opened.value && !loading.value && !saving.value && !props.disabled && eligible.value.length > 0
    && deadlineValid.value
    && (scope.value === 'all' || (scope.value === 'selected' && selected.value.length > 0)))
let sequence = 0

async function open() {
    if (props.disabled || loading.value || saving.value) return
    const operation = ++sequence
    const workId = props.work.id
    const date = props.date
    opened.value = true
    loading.value = true
    options.value = null
    error.value = ''
    scope.value = null
    selected.value = []
    openedAt.value = Date.now()
    deadline.value = viennaDateTime(Math.ceil((openedAt.value + halfHour) / halfHour) * halfHour)
    try {
        const response = await axios.get(reopeningOptions.url(workId), { params: date ? { date } : {} })
        if (operation === sequence && opened.value) options.value = response.data.data
    } catch (failure) {
        if (operation === sequence) error.value = errorMessage(failure, 'Teilnehmende konnten nicht geladen werden.')
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function save() {
    if (!canSave.value) return
    if (deadlineInstant.value <= Date.now()) {
        error.value = 'Die Upload-Frist ist bereits abgelaufen. Wähle einen neuen Zeitpunkt.'
        return
    }
    const operation = sequence
    const [deadlineDate, deadlineTime] = deadline.value.split('T')
    saving.value = true
    error.value = ''
    try {
        const response = await axios.post(reopen.url(props.work.id), {
            date: options.value.date, scope: scope.value, deadline_date: deadlineDate, deadline_time: deadlineTime,
            ...(scope.value === 'selected' ? { student_ids: selected.value } : {}),
        })
        if (operation === sequence) {
            opened.value = false
            emit('saved', response.data.data)
        }
    } catch (failure) {
        if (operation === sequence) error.value = errorMessage(failure, 'Die Portalabgabe konnte nicht wieder geöffnet werden.')
    } finally {
        if (operation === sequence) saving.value = false
    }
}

function errorMessage(failure, fallback) {
    const response = failure.response?.data
    return Object.values(response?.errors ?? {}).flat()[0] || response?.message || fallback
}

watch(() => [props.work.id, props.date], () => {
    sequence++
    opened.value = false
    loading.value = false
    saving.value = false
})
</script>

<style scoped>
.submission-reopening-choices { border: 0; padding: 0; min-width: 0; }
.submission-reopening-choices legend { font-weight: 600; }
.submission-reopening-choices label { display: flex; align-items: center; gap: .5rem; min-height: 44px; }
.submission-reopening-choices input { accent-color: rgb(var(--v-theme-primary)); }
.submission-reopening-choices .submission-reopening-deadline { flex-direction: column; align-items: flex-start; }
.submission-reopening-deadline input { border: 1px solid currentColor; border-radius: 4px; min-height: 44px; padding: .375rem .5rem; }
</style>
