<template>
    <section class="network-device-questions">
        <p v-if="loading" role="status">Musterantworten werden geladen …</p>
        <form v-else-if="answerKey" @submit.prevent="save">
            <div v-for="question in questions" :key="question.key" class="mb-4">
                <label :for="`device-answer-${question.key}`" class="d-block font-weight-bold mb-1">
                    {{ question.text }}
                </label>
                <textarea :id="`device-answer-${question.key}`" v-model="answerKey.answers[question.key]"
                    class="network-device-questions__answer text-body-2" rows="3" maxlength="2000"
                    :disabled="saving" required @input="saved = false"></textarea>
            </div>
            <v-btn type="submit" color="primary" variant="flat" height="44" class="text-none"
                :loading="saving" :disabled="saving || !canSave || conflict">
                Musterantworten speichern
            </v-btn>
            <p v-if="saved" class="mt-2" role="status">Musterantworten gespeichert.</p>
        </form>
        <p v-if="error" class="mt-2" role="alert">{{ error }}</p>
        <v-btn v-if="!loading && (!answerKey || conflict)" type="button" variant="outlined" height="44"
            class="text-none mt-2" @click="load">
            {{ conflict ? 'Gespeicherten Stand laden' : 'Erneut laden' }}
        </v-btn>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { show, update } from '@/actions/App/Http/Controllers/Admin/NetworkDeviceAnswerKeyController'

const questions = [
    { key: 'patch_panel', text: 'Was ist die Aufgabe des Patchpanels?' },
    { key: 'switch', text: 'Was ist die Aufgabe des Switchs?' },
    { key: 'router', text: 'Was ist die Aufgabe des Routers?' },
    { key: 'data_server', text: 'Was ist die Aufgabe des Datenservers?' },
    { key: 'access_point', text: 'Was ist die Aufgabe des WLAN-Access-Points?' },
]
const answerKey = ref(null)
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const conflict = ref(false)
const error = ref('')
const canSave = computed(() => questions.every(({ key }) => answerKey.value?.answers[key]?.trim()))

onMounted(load)

async function load() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await window.axios.get(show.url())
        answerKey.value = data
        conflict.value = false
        saved.value = false
    } catch {
        error.value = 'Die Musterantworten konnten nicht geladen werden.'
    } finally {
        loading.value = false
    }
}

async function save() {
    if (saving.value || !canSave.value || conflict.value) return
    saving.value = true
    error.value = ''
    saved.value = false
    try {
        const { data } = await window.axios.put(update.url(), answerKey.value)
        answerKey.value = data
        saved.value = true
    } catch (failure) {
        conflict.value = failure.response?.status === 409
        error.value = conflict.value
            ? 'Der gespeicherte Stand hat sich inzwischen geändert. Deine Eingaben sind weiterhin sichtbar.'
            : 'Die Musterantworten konnten nicht gespeichert werden.'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.network-device-questions {
    max-inline-size: 960px;
    padding-inline: 6px;
}

.network-device-questions__answer {
    display: block;
    inline-size: 100%;
    min-block-size: 84px;
    padding: 8px 12px;
    border: 1px solid rgba(var(--v-theme-on-surface), .42);
    border-radius: 4px;
    background: rgb(var(--v-theme-surface));
    color: rgb(var(--v-theme-on-surface));
    resize: vertical;
}

.network-device-questions__answer:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 1px;
}
</style>
