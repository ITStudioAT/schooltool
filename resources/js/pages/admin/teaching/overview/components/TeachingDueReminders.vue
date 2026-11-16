<template>
    <section class="mb-3" aria-label="Fällige Erinnerungen">
        <v-alert v-if="error" type="warning" variant="tonal" class="mb-2">{{ error }}</v-alert>
        <v-alert v-for="reminder in reminders" :key="reminder.id" type="info" variant="tonal" class="mb-2">
            <strong>{{ reminder.student_name }} · {{ reminder.course_title }}</strong>
            <div class="reminder-text">{{ reminder.description }}</div>
            <div class="text-caption">
                Fällig: {{ formatDate(reminder.due_date) }}<span v-if="reminder.due_time"> um {{ reminder.due_time }}</span>
            </div>
            <v-btn class="mt-2" size="small" variant="tonal" :disabled="savingId !== null" @click="markDone(reminder)">
                Erledigt
            </v-btn>
        </v-alert>
    </section>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'
import { index } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingReminderController'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'

const entries = useCourseBehaviourEntryStore()
const reminders = ref([])
const error = ref('')
const savingId = ref(null)
const controller = new AbortController()
let intervalId
let polling = false
let stopped = false
let revision = 0

function formatDate(value) {
    return value?.split('-').reverse().join('.') || ''
}

async function refresh() {
    if (polling || stopped || savingId.value !== null) {
        return
    }
    polling = true
    const requestedRevision = revision
    try {
        const response = await axios.get(index.url(), { signal: controller.signal })
        if (stopped || requestedRevision !== revision) {
            return
        }
        reminders.value = response.data.data || []
        error.value = ''
    } catch {
        if (!stopped) {
            error.value = 'Fällige Erinnerungen konnten nicht aktualisiert werden. Ein neuer Versuch folgt automatisch.'
        }
    } finally {
        polling = false
    }
}

async function markDone(reminder) {
    if (savingId.value !== null) {
        return
    }
    savingId.value = reminder.id
    revision++
    try {
        const result = await entries.update({
            id: reminder.id,
            teaching_course_id: reminder.course_id,
            kind: 'notification',
            description: reminder.description,
            date: reminder.date,
            is_due: true,
            due_date: reminder.due_date,
            due_time: reminder.due_time,
            is_done: true,
            done_date: new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Vienna' }).format(new Date()),
        })
        if (result) {
            error.value = ''
            reminders.value = reminders.value.filter((entry) => entry.id !== reminder.id)
        }
    } catch {
        error.value = 'Die Erinnerung konnte nicht als erledigt gespeichert werden. Bitte erneut versuchen.'
    } finally {
        savingId.value = null
    }
}

onMounted(() => {
    refresh()
    intervalId = window.setInterval(refresh, 60000)
})

onUnmounted(() => {
    stopped = true
    window.clearInterval(intervalId)
    controller.abort()
})
</script>

<style scoped>
.reminder-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
</style>
