<template>
    <section class="release-deadline" aria-label="Abgabefrist der Arbeit">
        <div class="release-deadline__header">
            <div>
                <h3>Abgabefrist der Arbeit</h3>
                <p><strong>{{ deadlineLabel }}</strong></p>
            </div>
            <v-btn v-if="!editing && !readOnly" min-height="44" variant="text" size="small" prepend-icon="mdi-pencil-outline"
                :disabled="disabled" @click="begin">Abgabefrist bearbeiten</v-btn>
        </div>
        <v-alert v-if="notice" :type="warning ? 'warning' : 'success'" variant="tonal" density="compact" class="mt-2" role="status">{{ notice }}</v-alert>
        <form v-if="editing && !readOnly" class="release-deadline__form" @submit.prevent="save">
            <div class="release-deadline__fields">
                <v-text-field v-model="date" type="date" label="Datum der Abgabefrist" variant="outlined"
                    :disabled="saving || disabled" hide-details="auto" @update:model-value="dateChanged" />
                <WorkTimeInput v-model="time" :disabled="!date || saving || disabled" />
            </div>
            <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2" role="alert">{{ error }}</v-alert>
            <div class="release-deadline__actions">
                <v-btn v-if="conflict" min-height="44" variant="text" :disabled="saving || disabled" @click="reload">Aktuelle Frist laden</v-btn>
                <v-spacer />
                <v-btn min-height="44" variant="text" :disabled="saving" @click="editing = false">Abbrechen</v-btn>
                <v-btn min-height="44" type="submit" color="primary" variant="flat" :loading="saving"
                    :disabled="!canSave">Abgabefrist speichern</v-btn>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { show, update } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { parseLocalDate } from '@/helpers/date'
import WorkTimeInput from './WorkTimeInput.vue'

const props = defineProps({ work: { type: Object, required: true }, disabled: Boolean, readOnly: Boolean })
const emit = defineEmits(['saved', 'busy'])
const editing = ref(false)
const saving = ref(false)
const conflict = ref(false)
const date = ref(null)
const time = ref(null)
const expected = ref(null)
const error = ref('')
const notice = ref('')
const warning = ref(false)
const formatter = new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
const deadlineOf = work => ({ date: work.finish_until_date?.slice(0, 10) || null, time: work.finish_until_time || null })
const deadline = computed(() => deadlineOf(props.work))
const deadlineLabel = computed(() => {
    if (!deadline.value.date) return 'Nicht festgelegt'
    const localDate = formatter.format(parseLocalDate(deadline.value.date))
    return deadline.value.time ? `${localDate} um ${deadline.value.time} Uhr` : localDate
})
const draft = computed(() => ({ date: date.value || null, time: time.value || null }))
const canSave = computed(() => editing.value && !saving.value && !props.disabled && !props.readOnly && !conflict.value
    && (!time.value || (date.value && /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(time.value)))
    && (expected.value.date !== draft.value.date || expected.value.time !== draft.value.time))

function begin() {
    if (props.disabled || props.readOnly || saving.value) return
    expected.value = deadline.value
    date.value = deadline.value.date
    time.value = deadline.value.time
    notice.value = ''
    error.value = ''
    conflict.value = false
    editing.value = true
}

function dateChanged(value) {
    if (!value) time.value = null
}

async function save() {
    if (!canSave.value) return
    const workId = props.work.id
    const submitted = draft.value
    saving.value = true
    error.value = ''
    notice.value = ''
    try {
        const { data } = await axios.put(update.url(workId), {
            finish_until_date: submitted.date, finish_until_time: submitted.time, expected_final_deadline: expected.value,
        })
        if (String(props.work.id) !== String(workId)) return
        const saved = data?.data
        if (String(saved?.id) !== String(workId) || deadlineOf(saved).date !== submitted.date || deadlineOf(saved).time !== submitted.time) {
            throw new Error('Die gespeicherte Abgabefrist konnte nicht bestätigt werden.')
        }
        warning.value = Boolean(data.deadline_sync_warning)
        notice.value = data.deadline_sync_warning || 'Abgabefrist gespeichert.'
        emit('saved', saved)
        editing.value = false
    } catch (failure) {
        if (String(props.work.id) !== String(workId)) return
        conflict.value = failure.response?.status === 409
        error.value = Object.values(failure.response?.data?.errors || {}).flat()[0]
            || failure.response?.data?.message || failure.message || 'Die Abgabefrist konnte nicht gespeichert werden.'
    } finally {
        saving.value = false
    }
}

async function reload() {
    if (!conflict.value || saving.value || props.disabled) return
    const workId = props.work.id
    saving.value = true
    error.value = ''
    try {
        const { data } = await axios.get(show.url(workId))
        if (String(props.work.id) !== String(workId)) return
        if (String(data?.data?.id) !== String(workId)) throw new Error()
        expected.value = deadlineOf(data.data)
        emit('saved', data.data)
        conflict.value = false
        warning.value = true
        notice.value = 'Aktuelle Frist geladen. Prüfe deine Eingabe vor dem Speichern.'
    } catch {
        error.value = 'Die aktuelle Abgabefrist konnte nicht geladen werden.'
    } finally {
        saving.value = false
    }
}

watch(editing, value => emit('busy', value), { flush: 'sync' })
onBeforeUnmount(() => emit('busy', false))
</script>

<style scoped>
.release-deadline { border-top: 1px solid rgba(var(--v-border-color), .16); padding: .5rem 1rem; min-width: 0; }
.release-deadline__header { display: flex; flex-wrap: wrap; gap: .25rem .75rem; align-items: center; justify-content: space-between; }
.release-deadline h3 { font-size: .9375rem; font-weight: 650; }
.release-deadline p { margin-block-start: .125rem; font-size: .875rem; }
.release-deadline__form { padding-block: .625rem .375rem; }
.release-deadline__fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; align-items: start; }
.release-deadline__fields :deep(.v-field) { min-height: 56px; }
.release-deadline__actions { display: flex; flex-wrap: wrap; gap: .25rem; margin-block-start: .5rem; }
.release-deadline :deep(.v-btn) { text-transform: none; letter-spacing: normal; }
@media (max-width: 540px) { .release-deadline__fields { grid-template-columns: minmax(0, 1fr); } }
</style>
