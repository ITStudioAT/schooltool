<template>
    <section class="network-additional-questions">
        <p v-if="loading" role="status">Fragen werden geladen …</p>
        <v-btn v-else-if="!loaded" variant="outlined" height="44" class="text-none" @click="load">
            Erneut laden
        </v-btn>
        <template v-else>
            <v-btn v-if="!draft" color="primary" prepend-icon="mdi-plus" height="44" class="text-none mb-4"
                :disabled="busy || pendingDelete !== null" @click="edit()">
                Frage hinzufügen
            </v-btn>

            <v-card v-else class="mb-4 pa-4" variant="outlined">
                <form @submit.prevent="save">
                    <label for="additional-question" class="d-block font-weight-bold mb-1">Frage</label>
                    <input id="additional-question" v-model="draft.question" class="network-additional-questions__field mb-3"
                        type="text" maxlength="500" :disabled="busy" required />
                    <label for="additional-answer" class="d-block font-weight-bold mb-1">Antwort</label>
                    <textarea id="additional-answer" v-model="draft.answer" class="network-additional-questions__field mb-3"
                        rows="3" maxlength="4000" :disabled="busy" required></textarea>
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn type="submit" color="primary" height="44" class="text-none"
                            :loading="busy" :disabled="busy || !canSave">Speichern</v-btn>
                        <v-btn type="button" variant="text" height="44" class="text-none"
                            :disabled="busy" @click="cancel">Abbrechen</v-btn>
                    </div>
                </form>
            </v-card>

            <p v-if="!questions.length && !draft">Noch keine Fragen vorhanden.</p>
            <v-card v-for="entry in questions" :key="entry.id" class="mb-3 pa-4" variant="outlined">
                <h2 class="text-subtitle-1 font-weight-bold">{{ entry.question }}</h2>
                <p class="network-additional-questions__answer my-2">{{ entry.answer }}</p>
                <div v-if="pendingDelete === entry.id" class="d-flex align-center flex-wrap ga-2">
                    <span>Diese Frage löschen?</span>
                    <v-btn color="error" variant="tonal" height="44" class="text-none"
                        :loading="busy" :disabled="busy" @click="remove(entry.id)">Löschen</v-btn>
                    <v-btn variant="text" height="44" class="text-none"
                        :disabled="busy" @click="cancel">Abbrechen</v-btn>
                </div>
                <div v-else class="d-flex flex-wrap ga-2">
                    <v-btn variant="text" height="44" class="text-none" prepend-icon="mdi-pencil-outline"
                        :disabled="busy || draft !== null || pendingDelete !== null" @click="edit(entry)">
                        Bearbeiten
                    </v-btn>
                    <v-btn variant="text" color="error" height="44" class="text-none" prepend-icon="mdi-delete-outline"
                        :disabled="busy || draft !== null || pendingDelete !== null" @click="pendingDelete = entry.id">
                        Löschen
                    </v-btn>
                </div>
            </v-card>
        </template>
        <p v-if="error" class="text-error mt-2" role="alert">{{ error }}</p>
        <p v-if="status" class="mt-2" role="status">{{ status }}</p>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { destroy, index, store, update } from '@/actions/App/Http/Controllers/Admin/NetworkTestQuestionController'

const questions = ref([])
const draft = ref(null)
const pendingDelete = ref(null)
const loading = ref(true)
const loaded = ref(false)
const busy = ref(false)
const error = ref('')
const status = ref('')
const canSave = computed(() => Boolean(draft.value?.question.trim() && draft.value?.answer.trim()))

onMounted(load)

async function load() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await window.axios.get(index.url())
        questions.value = data
        loaded.value = true
    } catch {
        error.value = 'Die Fragen konnten nicht geladen werden.'
    } finally {
        loading.value = false
    }
}

function edit(entry = null) {
    error.value = ''
    status.value = ''
    draft.value = entry ? { ...entry } : { id: null, question: '', answer: '' }
}

function cancel() {
    draft.value = null
    pendingDelete.value = null
    error.value = ''
}

async function save() {
    if (busy.value || !canSave.value) return

    const { id, question, answer } = draft.value
    const payload = { question: question.trim(), answer: answer.trim() }
    busy.value = true
    error.value = ''
    status.value = ''
    try {
        const { data } = id === null
            ? await window.axios.post(store.url(), payload)
            : await window.axios.put(update.url({ question: id }), payload)
        questions.value = id === null
            ? [...questions.value, data]
            : questions.value.map(entry => entry.id === id ? data : entry)
        draft.value = null
        status.value = 'Frage gespeichert.'
    } catch {
        error.value = 'Die Frage konnte nicht gespeichert werden.'
    } finally {
        busy.value = false
    }
}

async function remove(id) {
    if (busy.value) return

    busy.value = true
    error.value = ''
    status.value = ''
    try {
        await window.axios.delete(destroy.url({ question: id }))
        questions.value = questions.value.filter(entry => entry.id !== id)
        pendingDelete.value = null
        status.value = 'Frage gelöscht.'
    } catch {
        error.value = 'Die Frage konnte nicht gelöscht werden.'
    } finally {
        busy.value = false
    }
}
</script>

<style scoped>
.network-additional-questions {
    max-inline-size: 960px;
    padding-inline: 6px;
}

.network-additional-questions__answer {
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.network-additional-questions__field {
    display: block;
    inline-size: 100%;
    min-block-size: 44px;
    padding: 8px 12px;
    border: 1px solid rgba(var(--v-theme-on-surface), .42);
    border-radius: 4px;
    background: rgb(var(--v-theme-surface));
    color: rgb(var(--v-theme-on-surface));
}

textarea.network-additional-questions__field {
    resize: vertical;
}

.network-additional-questions__field:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 1px;
}
</style>
