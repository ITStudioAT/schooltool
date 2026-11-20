<template>
    <div>
        <div v-if="!editing" class="d-flex align-center ga-2">
            <span>{{ title || 'Ohne Titel' }}</span>
            <v-btn v-if="canEdit" icon="mdi-pencil" size="x-small" variant="text" title="Titel bearbeiten"
                aria-label="Titel bearbeiten" :disabled="disabled" @click="start" />
        </div>
        <div v-else>
            <v-text-field v-model="draft" label="Titel" maxlength="255" autofocus :disabled="saving"
                :error-messages="error" @keydown.esc.stop.prevent="cancel" />
            <div class="d-flex ga-2">
                <v-btn variant="text" :disabled="saving" @click="cancel">Abbrechen</v-btn>
                <v-btn color="primary" variant="flat" :loading="saving" :disabled="saving" @click="save">Titel speichern</v-btn>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { update } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'

const props = defineProps({ workId: [Number, String], title: String, disabled: Boolean, canEdit: Boolean })
const emit = defineEmits(['saved'])
const editing = ref(false)
const draft = ref('')
const saving = ref(false)
const error = ref('')

function start() {
    if (!props.canEdit || props.disabled) return
    draft.value = props.title || ''
    error.value = ''
    editing.value = true
}

function cancel() {
    if (saving.value) return
    draft.value = ''
    error.value = ''
    editing.value = false
}

async function save() {
    if (!props.canEdit || props.disabled || saving.value) return
    const title = draft.value.trim()
    if (!title) {
        error.value = 'Bitte einen Titel eingeben.'
        return
    }
    if (title.length > 255) {
        error.value = 'Der Titel darf höchstens 255 Zeichen enthalten.'
        return
    }
    saving.value = true
    error.value = ''
    try {
        const response = await axios.put(update.url(props.workId), { title })
        emit('saved', response.data.data)
        editing.value = false
    } catch (exception) {
        error.value = Object.values(exception.response?.data?.errors || {}).flat()[0]
            || exception.response?.data?.message || 'Der Titel konnte nicht gespeichert werden. Bitte erneut versuchen.'
    } finally {
        saving.value = false
    }
}

watch(() => props.workId, cancel)
watch(() => props.canEdit, allowed => { if (!allowed) cancel() })
</script>
