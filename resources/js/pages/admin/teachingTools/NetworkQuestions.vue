<template>
    <section class="network-questions">
        <p v-if="error && !pendingDelete" class="text-error mb-2" role="alert">{{ error }}</p>
        <p v-if="status" class="mb-2" role="status">{{ status }}</p>
        <v-btn v-if="stale && !loading && !pendingDelete" variant="outlined" height="44" class="text-none mb-3" @click="load">
            Gespeicherten Stand laden
        </v-btn>
        <p v-if="loading" role="status">Fragen werden geladen …</p>
        <v-btn v-else-if="!loaded" variant="outlined" height="44" class="text-none" @click="load">
            Erneut laden
        </v-btn>
        <template v-else>
            <v-btn v-if="!draft" ref="addQuestionButton" color="primary" prepend-icon="mdi-plus" height="44" class="text-none mb-4"
                :disabled="busy || pendingDelete !== null" @click="edit()">
                Frage hinzufügen
            </v-btn>

            <v-card v-else class="mb-4 pa-4" variant="outlined">
                <form @submit.prevent="save">
                    <label for="network-question" class="d-block font-weight-bold mb-1">Frage</label>
                    <input id="network-question" ref="questionInput" v-model="draft.question" class="network-questions__field mb-3"
                        type="text" maxlength="500" :disabled="busy" required />
                    <label for="network-answer" class="d-block font-weight-bold mb-1">Antwort</label>
                    <textarea id="network-answer" v-model="draft.answer" class="network-questions__field mb-3"
                        rows="3" maxlength="4000" :disabled="busy" required></textarea>
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn type="submit" color="primary" height="44" class="text-none"
                            :loading="busy" :disabled="busy || stale || !canSave">Speichern</v-btn>
                        <v-btn type="button" variant="text" height="44" class="text-none"
                            :disabled="busy" @click="cancel">Abbrechen</v-btn>
                    </div>
                </form>
            </v-card>

            <p v-if="!questions.length && !draft">Noch keine Fragen vorhanden.</p>
            <v-card v-for="entry in questions" :key="entry.id" tag="article" class="mb-2 pa-3" variant="outlined">
                <header class="network-questions__card-header">
                    <h2 class="text-subtitle-1 font-weight-bold pt-1">{{ entry.question }}</h2>
                    <div class="d-flex ga-1">
                        <v-btn type="button" icon="mdi-pencil-outline" size="36" variant="text" title="Frage bearbeiten"
                            :aria-label="`Frage „${entry.question}“ bearbeiten`"
                            :disabled="busy || draft !== null || pendingDelete !== null" @click="edit(entry)" />
                        <v-btn type="button" icon="mdi-delete-outline" size="36" variant="text" color="error"
                            title="Frage löschen" :aria-label="`Frage „${entry.question}“ löschen`" aria-haspopup="dialog"
                            :disabled="busy || draft !== null || pendingDelete !== null" @click="openDelete(entry, $event)" />
                    </div>
                </header>
                <p class="network-questions__answer mt-1">{{ entry.answer }}</p>
            </v-card>
        </template>

        <v-dialog :model-value="pendingDelete !== null" persistent scrollable max-width="480"
            aria-labelledby="network-question-delete-title" aria-describedby="network-question-delete-description"
            @after-enter="focusDeleteCancel" @after-leave="restoreDeleteFocus">
            <v-card>
                <v-card-title id="network-question-delete-title">Frage löschen?</v-card-title>
                <v-card-text class="network-questions__dialog-text">
                    <p id="network-question-delete-description">{{ pendingDelete?.question }}</p>
                    <p v-if="error" class="text-error mt-3" role="alert">{{ error }}</p>
                    <v-btn v-if="stale" type="button" variant="outlined" height="44" class="text-none mt-3"
                        :loading="loading" :disabled="loading" @click="load">Gespeicherten Stand laden</v-btn>
                </v-card-text>
                <v-card-actions class="justify-end flex-wrap ga-2">
                    <v-btn ref="deleteCancelButton" type="button" variant="text" height="44" class="text-none"
                        :disabled="busy || loading" @click="cancel">Abbrechen</v-btn>
                    <v-btn type="button" color="error" variant="flat" height="44" class="text-none"
                        :loading="busy" :disabled="busy || loading || stale" @click="remove">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import * as deviceQuestionRoutes from '@/routes/admin/teaching-tools/network-device-questions'
import * as additionalQuestionRoutes from '@/routes/admin/teaching-tools/network-test-questions'

const props = defineProps({ device: Boolean })
const routes = props.device ? deviceQuestionRoutes : additionalQuestionRoutes
const questions = ref([])
const draft = ref(null)
const questionInput = ref(null)
const addQuestionButton = ref(null)
const deleteCancelButton = ref(null)
const schoolId = ref(null)
const pendingDelete = ref(null)
const loading = ref(true)
const loaded = ref(false)
const busy = ref(false)
const error = ref('')
const status = ref('')
const stale = ref(false)
const canSave = computed(() => Boolean(draft.value?.question.trim() && draft.value?.answer.trim()))
let deleteTrigger = null

onMounted(load)

async function load() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await window.axios.get(routes.index.url())
        questions.value = props.device ? data.questions : data
        schoolId.value = props.device ? data.school_id : null
        cancel()
        status.value = ''
        loaded.value = true
    } catch {
        error.value = 'Die Fragen konnten nicht geladen werden.'
    } finally {
        loading.value = false
    }
}

async function edit(entry = null) {
    error.value = ''
    status.value = ''
    draft.value = entry ? { ...entry } : { id: null, question: '', answer: '' }
    await nextTick()
    questionInput.value?.focus()
}

function cancel() {
    draft.value = null
    pendingDelete.value = null
    error.value = ''
    stale.value = false
}

async function save() {
    if (busy.value || stale.value || !canSave.value) return

    const { id, question, answer, revision } = draft.value
    const payload = { question: question.trim(), answer: answer.trim() }
    if (id === null && props.device) payload.school_id = schoolId.value
    busy.value = true
    error.value = ''
    status.value = ''
    try {
        const { data } = id === null
            ? await window.axios.post(routes.store.url(), payload)
            : await window.axios.put(routes.update.url({ question: id }), { ...payload, revision })
        questions.value = id === null
            ? [...questions.value, data]
            : questions.value.map(entry => entry.id === id ? data : entry)
        draft.value = null
        status.value = 'Frage gespeichert.'
    } catch (failure) {
        showMutationError(failure, 'Die Frage konnte nicht gespeichert werden.')
    } finally {
        busy.value = false
    }
}

function openDelete(entry, event) {
    deleteTrigger = event.currentTarget
    pendingDelete.value = entry
    error.value = ''
    status.value = ''
}

function focusDeleteCancel() {
    deleteCancelButton.value?.$el.focus()
}

function restoreDeleteFocus() {
    const target = deleteTrigger?.isConnected ? deleteTrigger : addQuestionButton.value?.$el
    target?.focus()
    deleteTrigger = null
}

async function remove() {
    if (busy.value || loading.value || stale.value || !pendingDelete.value) return

    const { id, revision } = pendingDelete.value
    busy.value = true
    error.value = ''
    status.value = ''
    try {
        await window.axios.delete(routes.destroy.url({ question: id }), { data: { revision } })
        questions.value = questions.value.filter(entry => entry.id !== id)
        pendingDelete.value = null
        status.value = 'Frage gelöscht.'
    } catch (failure) {
        showMutationError(failure, 'Die Frage konnte nicht gelöscht werden.')
    } finally {
        busy.value = false
    }
}

function showMutationError(failure, message) {
    stale.value = [404, 409].includes(failure.response?.status)
    error.value = stale.value ? 'Der gespeicherte Stand hat sich geändert. Lade den aktuellen Stand.' : message
}
</script>

<style scoped>
.network-questions {
    max-inline-size: 960px;
    padding-inline: 6px;
    overflow-wrap: anywhere;
}

.network-questions__card-header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 8px;
}

.network-questions__answer {
    white-space: pre-wrap;
}

.network-questions__dialog-text {
    overflow-wrap: anywhere;
}

.network-questions__field {
    display: block;
    inline-size: 100%;
    min-block-size: 44px;
    padding: 8px 12px;
    border: 1px solid rgba(var(--v-theme-on-surface), .42);
    border-radius: 4px;
    background: rgb(var(--v-theme-surface));
    color: rgb(var(--v-theme-on-surface));
}

textarea.network-questions__field {
    resize: vertical;
}

.network-questions__field:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 1px;
}
</style>
