<template>
    <ItsGridBox title="Angeforderte Schüler-Wiederherstellungen" icon="mdi-account-convert" color="primary" class="w-100 mt-4">
        <p class="text-body-2 mb-3">
            Prüfen Sie die gespeicherte Identität. Stellen Sie fehlende Schüler zuerst über die bestehende
            Benutzerverwaltung bzw. den Schülerimport wieder her. Ordnen Sie anschließend ausschließlich dieselbe
            Person zu. Konten und Zugangsdaten werden hier nicht verändert.
        </p>
        <v-btn variant="tonal" :loading="loading" :disabled="Boolean(saving)" class="mb-3" @click="loadRequests">Aktualisieren</v-btn>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
        <v-alert v-if="success" type="success" variant="tonal" class="mb-3">{{ success }}</v-alert>
        <p v-if="!loading && requests.length === 0" class="text-body-2">Keine Wiederherstellung angefordert.</p>
        <section v-for="request in requests" :key="request.id" class="mb-5">
            <h3 class="text-subtitle-1 mb-2">{{ request.owner_name }} · Sicherung {{ request.id }}</h3>
            <div v-for="identity in request.missing.users" :key="`user-${identity.id}`" class="mb-3">
                <p class="text-body-2">Gesichert: {{ identity.last_name }}, {{ identity.first_name }} · {{ identity.schoolclass }} · {{ identity.email }} · bisherige ID {{ identity.id }}</p>
                <v-autocomplete v-model="request.student_mappings[identity.id]" :items="studentOptions" item-title="label" item-value="id" label="Wiederhergestelltes Schülerkonto" :disabled="Boolean(saving)" clearable hide-details="auto" />
            </div>
            <div v-for="identity in request.missing.imports" :key="`import-${identity.id}`" class="mb-3">
                <p class="text-body-2">Gesicherter Import: {{ identity.last_name }}, {{ identity.first_name }} · {{ identity.class }} · {{ identity.student_code }} · bisherige ID {{ identity.id }}</p>
                <v-autocomplete v-model="request.import_mappings[identity.id]" :items="importOptions.filter(option => Number(option.schoolyear_id) === Number(identity.schoolyear_id))" item-title="label" item-value="id" label="Wiederhergestellter Schülerimport desselben Schuljahres" :disabled="Boolean(saving)" clearable hide-details="auto" />
            </div>
            <v-checkbox v-model="request.confirmed" label="Ich habe geprüft, dass jede Zuordnung dieselbe Person betrifft." :disabled="Boolean(saving)" hide-details />
            <v-btn color="primary" variant="flat" :disabled="!request.confirmed || Boolean(saving)" :loading="saving === request.id" @click="saveMappings(request)">Zuordnung freigeben</v-btn>
        </section>
    </ItsGridBox>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import { index, resolve } from '@/actions/App/Http/Controllers/Admin/Teaching/PersonalTeachingBackupRecoveryController'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

const requests = ref([])
const studentOptions = ref([])
const importOptions = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')

async function loadRequests() {
    if (loading.value) return
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get(index.url())
        requests.value = data.data.map(request => ({ ...request, student_mappings: { ...request.student_mappings }, import_mappings: { ...request.import_mappings }, confirmed: false }))
        studentOptions.value = data.meta.student_options
        importOptions.value = data.meta.import_options
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Die Wiederherstellungen konnten nicht geladen werden.'
    } finally {
        loading.value = false
    }
}

function selectedMappings(mappings) {
    return Object.fromEntries(Object.entries(mappings).filter(([, target]) => target !== null && target !== ''))
}

async function saveMappings(request) {
    if (!request.confirmed || saving.value) return
    saving.value = request.id
    error.value = ''
    success.value = ''
    try {
        await axios.post(resolve.url(request.id), {
            student_mappings: selectedMappings(request.student_mappings),
            import_mappings: selectedMappings(request.import_mappings),
            confirm_identity: request.confirmed,
        })
        success.value = 'Die Zuordnung wurde freigegeben. Die Lehrkraft kann die Sicherung jetzt erneut wiederherstellen.'
        await loadRequests()
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Die Zuordnung konnte nicht gespeichert werden.'
    } finally {
        saving.value = false
    }
}

onMounted(loadRequests)
</script>
