<template>
    <v-col cols="12" class="teaching-admin-import-col">
        <ItsGridBox
            variant="overview"
            color="primary"
            title="E-Mail-Adressen aus Login-Listen ergänzen"
            subtitle="Nur fehlende Schüler-E-Mail-Adressen aus einem ZIP-Archiv eintragen"
            icon="mdi-email-plus-outline"
            class="w-100">
            <div class="pa-4">
                <v-alert type="info" variant="tonal" class="mb-4">
                    ZIP-Datei mit den HTML-Login-Listen auswählen. Vorhandene E-Mail-Adressen bleiben erhalten.
                    Die Passwortspalte wird nicht importiert.
                </v-alert>

                <v-file-input
                    v-model="file"
                    label="Login-Listen als ZIP auswählen"
                    accept=".zip,application/zip"
                    show-size
                    :disabled="importing"
                    @update:model-value="clearFeedback" />

                <v-btn color="primary" class="mt-3" :loading="importing" :disabled="!selectedFile || importing" @click="importEmails">
                    Fehlende E-Mail-Adressen ergänzen
                </v-btn>

                <v-alert v-if="error" type="error" variant="tonal" class="mt-4">{{ error }}</v-alert>

                <v-alert v-if="result" type="success" variant="tonal" class="mt-4" data-testid="student-email-import-result">
                    <div>{{ result.total }} eindeutige Einträge, {{ result.matched }} zugeordnet, {{ result.updated }} ergänzt.</div>
                    <div>{{ result.skipped_existing }} mit vorhandener E-Mail übersprungen, {{ result.skipped_conflict }} wegen Kontokonflikt übersprungen, {{ result.unmatched }} nicht zugeordnet.</div>
                    <div v-if="result.ambiguous">Davon {{ result.ambiguous }} mehrdeutig.</div>
                    <div v-if="result.unmatched_examples?.length" class="mt-2">
                        Nicht zugeordnet (erste {{ result.unmatched_examples.length }}):
                        <ul class="ml-5">
                            <li v-for="(entry, index) in result.unmatched_examples" :key="index">
                                {{ entry.class }} – {{ entry.name }}
                            </li>
                        </ul>
                    </div>
                </v-alert>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script setup>
import { computed, ref } from 'vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import importStudentEmails from '@/actions/App/Http/Controllers/Admin/Teaching/StudentEmailZipImportController'

const file = ref(null)
const importing = ref(false)
const result = ref(null)
const error = ref('')
const selectedFile = computed(() => Array.isArray(file.value) ? file.value[0] : file.value)

function clearFeedback() {
    result.value = null
    error.value = ''
}

async function importEmails() {
    if (!selectedFile.value || importing.value) {
        return
    }

    clearFeedback()
    importing.value = true
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    try {
        const response = await axios.post(importStudentEmails.url(), formData)
        result.value = response.data
    } catch (exception) {
        error.value = exception?.response?.data?.errors?.file?.[0]
            || exception?.response?.data?.message
            || 'Die E-Mail-Adressen konnten nicht importiert werden.'
    } finally {
        importing.value = false
    }
}
</script>
