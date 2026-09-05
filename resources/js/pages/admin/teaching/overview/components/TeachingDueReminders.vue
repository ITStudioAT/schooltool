<template>
    <section class="mb-3" aria-label="Fällige Erinnerungen">
        <div class="d-flex align-center flex-wrap ga-2 text-caption mb-2">
            <v-btn v-if="permission === 'default'" size="small" variant="tonal" @click="enableBrowserNotifications">
                Browser-Erinnerungen aktivieren
            </v-btn>
            <span>{{ browserStatus }}</span>
        </div>
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
import { computed, onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'
import { index } from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingReminderController'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'

const entries = useCourseBehaviourEntryStore()
const reminders = ref([])
const error = ref('')
const savingId = ref(null)
const permission = ref(typeof window.Notification === 'function' ? window.Notification.permission : 'unsupported')
const notified = new Set()
const controller = new AbortController()
let intervalId
let polling = false
let stopped = false
let revision = 0

const browserStatus = computed(() => {
    if (permission.value === 'unsupported') {
        return 'Browser-Erinnerungen sind hier nicht verfügbar. Fällige Erinnerungen werden im Unterricht angezeigt.'
    }
    if (permission.value === 'denied') {
        return 'Browser-Erinnerungen sind blockiert. Du kannst sie in den Website-Einstellungen deines Browsers erlauben.'
    }
    return 'Browser-Erinnerungen funktionieren, solange diese Unterrichtsseite geöffnet ist.'
})

function formatDate(value) {
    return value?.split('-').reverse().join('.') || ''
}

function notifyDueReminders() {
    if (permission.value !== 'granted' || stopped) {
        return
    }
    for (const reminder of reminders.value) {
        const key = JSON.stringify([reminder.id, reminder.due_date, reminder.due_time, reminder.description])
        if (notified.has(key)) {
            continue
        }
        try {
            new window.Notification(`Erinnerung: ${reminder.student_name}`, {
                body: `${reminder.course_title}: ${reminder.description}`,
                tag: `teaching-reminder-${reminder.id}`,
            })
            notified.add(key)
        } catch {
            permission.value = 'unsupported'
            break
        }
    }
}

async function enableBrowserNotifications() {
    try {
        permission.value = await window.Notification.requestPermission()
        notifyDueReminders()
    } catch {
        permission.value = 'unsupported'
    }
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
        notifyDueReminders()
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
