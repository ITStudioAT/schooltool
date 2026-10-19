<template>
    <v-card flat border rounded="xl" class="connections-card">
        <div class="connections-heading"><h2>Verbindungen</h2><v-btn size="small" variant="text" color="primary" :loading="loading" @click="load(true)">Verbindungen prüfen</v-btn></div>
        <p v-if="error" role="alert" class="text-body-2 text-error">{{ error }}</p>
        <p v-if="!data && loading" role="status" class="text-body-2">Verbindungsstatus laden …</p>
        <div v-if="data" class="connections-grid">
            <section>
                <h3>Dropbox <span class="text-caption text-medium-emphasis">· Ihr Zugang</span></h3>
                <p>{{ !data.dropbox.configured ? 'Nicht eingerichtet' : data.dropbox.connected ? 'Benutzerfreigabe gespeichert' : 'Nicht verbunden' }}</p>
                <p v-if="data.dropbox.connected" class="text-caption">Lesen: {{ data.dropbox.read_checked_at ? 'erfolgreich geprüft' : data.dropbox.read_granted ? 'freigegeben, noch nicht geprüft' : 'noch nicht bestätigt' }}<br>
                    Schreiben: {{ data.dropbox.write_checked_at ? 'erfolgreich geprüft' : data.dropbox.write_granted ? 'freigegeben, noch nicht geprüft' : 'zusätzliche Freigabe erforderlich' }}</p>
                <p v-if="data.dropbox.read_checked_at" class="text-caption">Lesen zuletzt bestätigt: {{ date(data.dropbox.read_checked_at) }}</p>
                <p v-if="data.dropbox.write_checked_at" class="text-caption">Schreiben zuletzt bestätigt: {{ date(data.dropbox.write_checked_at) }}</p>
                <p v-if="data.dropbox.cleanup_complete !== null && data.dropbox.cleanup_complete !== undefined" class="text-caption">{{ data.dropbox.cleanup_complete ? 'Eigene Testartefakte vollständig entfernt' : 'Bereinigung noch nicht vollständig bestätigt' }}</p>
                <p v-if="data.dropbox.error" class="text-caption text-error">{{ data.dropbox.error }}</p>
                <v-btn v-if="data.dropbox.configured && data.can_connect_dropbox" size="small" variant="outlined" class="mt-2" :disabled="loading" @click="reconnect">{{ data.dropbox.write_granted ? 'Erneut verbinden' : 'Lesen und Schreiben freigeben' }}</v-btn>
            </section>
            <section>
                <h3>Postmark <span class="text-caption text-medium-emphasis">· Schooltool</span></h3>
                <p>{{ data.postmark.configured ? 'Konfiguriert' : 'Nicht eingerichtet' }}</p>
                <p v-if="data.postmark.configured" class="text-caption">{{ data.postmark.used_for_mail ? 'Für den Mailversand ausgewählt' : data.postmark.smtp_selected ? 'Mailversand über SMTP · hier nicht geprüft' : 'Aktuell anderer Versandweg ausgewählt' }}<br>
                    {{ data.postmark.checked_at ? 'Zugang zuletzt bestätigt: ' + date(data.postmark.checked_at) : 'Zugang noch nicht geprüft' }}</p>
                <p v-if="data.postmark.error" class="text-caption text-error">{{ data.postmark.error }}</p>
                <p v-if="data.postmark.test_mail" class="text-caption mt-1">{{ data.postmark.test_mail.message }}</p>
            </section>
            <section><h3>Office / Microsoft</h3><p class="text-body-2">{{ data.office.message }}</p></section>
        </div>
        <p v-if="data" class="text-caption text-medium-emphasis mt-3">„Verbindungen prüfen“ testet Dropbox mit einer eigenen temporären Datei und sendet eine Testmail über Postmark an Ihre hinterlegte E-Mail-Adresse. Eigene Testdatei und Testordner werden wieder entfernt. Beim Laden dieser Übersicht wird nichts geschrieben oder versendet.</p>
    </v-card>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { status, check, connect } from '@/routes/admin/connections'

const data = ref(null)
const loading = ref(false)
const error = ref('')

function date(value) { return new Date(value).toLocaleString('de-AT') }

async function load(verify = false) {
    if (loading.value) return
    loading.value = true
    error.value = ''
    try { data.value = (verify ? await axios.post(check.url()) : await axios.get(status.url())).data }
    catch { error.value = 'Die Verbindungsinformationen konnten nicht geladen werden.' }
    finally { loading.value = false }
}

async function reconnect() {
    loading.value = true
    error.value = ''
    try { const response = await axios.post(connect.url()); window.location.assign(response.data.url) }
    catch (failure) { error.value = failure.response?.data?.message || 'Die zusätzliche Dropbox-Freigabe konnte nicht geöffnet werden.'; loading.value = false }
}

onMounted(() => load())
</script>

<style scoped>
.connections-card { padding: 20px; margin-block: 12px; border-color: #e4e7ec; }
.connections-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.connections-heading h2 { font-size: 1.1rem; }
.connections-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 18px; }
.connections-grid h3 { font-size: .95rem; margin-bottom: 6px; }
.connections-grid p { font-size: .88rem; overflow-wrap: anywhere; }
</style>
