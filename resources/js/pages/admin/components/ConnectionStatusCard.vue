<template>
    <v-card flat border rounded="xl" class="connections-card">
        <div class="connections-heading"><h2>Verbindungen</h2><v-btn size="small" variant="text" color="primary" :loading="loading" @click="load(true)">Verbindungen prüfen</v-btn></div>
        <p v-if="error" role="alert" class="text-body-2 text-error">{{ error }}</p>
        <div class="connections-grid">
            <section>
                <h3>Dropbox <span class="text-caption text-medium-emphasis">· Ihr Zugang</span></h3>
                <p role="status" :aria-busy="pending.dropbox">
                    <v-progress-circular v-if="pending.dropbox" indeterminate :size="14" :width="2" color="primary" class="mr-1" aria-label="Dropbox wird geprüft" />
                    {{ pending.dropbox ? 'Ordnerzugriff wird geprüft …' : data.dropbox.error ? 'Prüfung fehlgeschlagen' : !data.dropbox.configured ? 'Nicht eingerichtet' : !data.dropbox.installed ? 'Einrichtung unvollständig' : data.dropbox.connected ? (data.dropbox.read_checked_at ? 'Ordnerzugriff bestätigt' : 'Benutzerfreigabe gespeichert') : 'Nicht verbunden – bitte erneut freigeben' }}
                </p>
                <p v-if="data.dropbox.connected" class="text-caption">Schreiben: {{ data.dropbox.write_checked_at ? 'zuletzt manuell geprüft' : data.dropbox.write_granted ? 'freigegeben, noch nicht geprüft' : 'zusätzliche Freigabe erforderlich' }}</p>
                <p v-if="!pending.dropbox && data.dropbox.read_checked_at && !data.dropbox.error" class="text-caption">Ordnerzugriff zuletzt bestätigt: {{ date(data.dropbox.read_checked_at) }}</p>
                <p v-if="data.dropbox.write_checked_at" class="text-caption">Schreibtest zuletzt bestätigt: {{ date(data.dropbox.write_checked_at) }}</p>
                <p v-if="data.dropbox.cleanup_complete !== null && data.dropbox.cleanup_complete !== undefined" class="text-caption">{{ data.dropbox.cleanup_complete ? 'Eigene Testartefakte vollständig entfernt' : 'Bereinigung noch nicht vollständig bestätigt' }}</p>
                <p v-if="!pending.dropbox && data.dropbox.error" role="alert" class="text-caption text-error">{{ data.dropbox.error }}</p>
                <v-btn v-if="data.dropbox.configured && data.can_connect_dropbox" size="small" variant="outlined" class="mt-2" :disabled="loading" @click="reconnect">{{ data.dropbox.write_granted ? 'Erneut verbinden' : 'Lesen und Schreiben freigeben' }}</v-btn>
            </section>
            <section>
                <h3>Postmark <span class="text-caption text-medium-emphasis">· Schooltool</span></h3>
                <p role="status" :aria-busy="pending.postmark">
                    <v-progress-circular v-if="pending.postmark" indeterminate :size="14" :width="2" color="primary" class="mr-1" aria-label="Postmark wird geprüft" />
                    {{ pending.postmark ? 'API-Zugang wird geprüft …' : data.postmark.error ? 'Prüfung fehlgeschlagen' : !data.postmark.configured ? 'Nicht eingerichtet' : data.postmark.checked_at ? 'API-Zugang bestätigt' : 'Konfiguriert' }}
                </p>
                <p v-if="data.postmark.configured" class="text-caption">{{ data.postmark.used_for_mail ? 'Für den Mailversand ausgewählt' : data.postmark.smtp_selected ? 'Mailversand über SMTP · hier nicht geprüft' : 'Aktuell anderer Versandweg ausgewählt' }}<br>
                    <template v-if="!pending.postmark && !data.postmark.error">{{ data.postmark.checked_at ? 'API-Zugang zuletzt bestätigt: ' + date(data.postmark.checked_at) : 'Zugang noch nicht geprüft' }}</template></p>
                <p v-if="!pending.postmark && data.postmark.error" role="alert" class="text-caption text-error">{{ data.postmark.error }}</p>
                <p v-if="data.postmark.test_mail" class="text-caption mt-1">Letzter manueller Mailtest: {{ data.postmark.test_mail.message }}</p>
                <p v-else-if="data.postmark.configured" class="text-caption">Mailversand hier noch nicht geprüft.</p>
            </section>
            <section><h3>Office / Microsoft</h3><p role="status" :aria-busy="pending.office" class="text-body-2">
                <v-progress-circular v-if="pending.office" indeterminate :size="14" :width="2" color="primary" class="mr-1" aria-label="Office-Einrichtung wird geladen" />
                {{ pending.office ? 'Einrichtung wird geladen …' : data.office.message || 'Office-Status konnte nicht geladen werden.' }}
            </p></section>
        </div>
        <p class="text-caption text-medium-emphasis mt-3">Beim Laden werden nur der Dropbox-Ordnerzugriff und der Postmark-API-Zugang geprüft. Dateiinhalte, Schreibfähigkeit und Mailversand sind damit nicht bestätigt. „Verbindungen prüfen“ testet zusätzlich Dropbox mit einer eigenen temporären Datei und sendet eine Testmail über Postmark an Ihre hinterlegte E-Mail-Adresse. Eigene Testdatei und Testordner werden wieder entfernt.</p>
    </v-card>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { status, check, connect } from '@/routes/admin/connections'

const emit = defineEmits(['status-change'])
const data = ref({ dropbox: {}, postmark: {}, office: {} })
const loading = ref(false)
const error = ref('')
const pending = ref({ dropbox: true, postmark: true, office: true })

function date(value) { return new Date(value).toLocaleString('de-AT') }

async function load(verify = false) {
    if (loading.value) return
    loading.value = true
    error.value = ''
    pending.value = { dropbox: true, postmark: true, office: true }
    publishStatus()
    try {
        data.value = (verify ? await axios.post(check.url()) : await axios.get(status.url())).data
        pending.value.office = false
        if (!verify) {
            await Promise.all(['dropbox', 'postmark'].map(probe))
        }
    } catch {
        error.value = 'Die Verbindungsinformationen konnten nicht geladen werden. Bitte später erneut versuchen.'
        data.value.dropbox = { ...data.value.dropbox, error: 'Dropbox-Status konnte nicht geladen werden.' }
        data.value.postmark = { ...data.value.postmark, error: 'Postmark-Status konnte nicht geladen werden.' }
        if (!verify) data.value.office = { message: 'Office-Status konnte nicht geladen werden.' }
    } finally {
        pending.value = { dropbox: false, postmark: false, office: false }
        loading.value = false
        publishStatus()
    }
}

async function probe(service) {
    const connection = data.value[service]
    try {
        if (!connection.configured || (service === 'dropbox' && (!connection.connected || !connection.installed))) return
        const response = await axios.get(status.url({ query: { probe: service } }))
        data.value[service] = response.data[service]
    } catch {
        data.value[service] = { ...connection, read_checked_at: null, checked_at: null,
            error: 'Die Prüfung konnte nicht abgeschlossen werden. Bitte später erneut versuchen.' }
    } finally {
        pending.value[service] = false
        publishStatus()
    }
}

function publishStatus() {
    emit('status-change', {
        loading: Object.values(pending.value).some(Boolean),
        hasError: Boolean(error.value || data.value.dropbox.error || data.value.postmark.error
            || (data.value.dropbox.configured && (!data.value.dropbox.connected || !data.value.dropbox.installed))
            || data.value.dropbox.cleanup_complete === false
            || (data.value.postmark.test_mail && data.value.postmark.test_mail.accepted !== true)),
    })
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
