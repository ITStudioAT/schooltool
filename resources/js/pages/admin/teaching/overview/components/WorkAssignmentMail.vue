<template>
    <v-btn v-if="!hideActivator" variant="tonal" color="primary" prepend-icon="mdi-email-outline" :disabled="disabled || !work.id" @click.stop="open">
        {{ stored ? 'E-Mails verwalten' : 'E-Mails vorbereiten' }}
    </v-btn>
    <v-dialog v-model="opened" max-width="790" scrollable>
        <v-card>
            <v-card-title class="text-wrap">Aufgaben per E-Mail · {{ work.title }}</v-card-title>
            <v-card-text aria-live="polite">
                <v-select v-if="dates.length > 1 && !date" v-model="selectedDate" :items="dates" label="Durchführungstermin"
                    :disabled="busy" density="compact" @update:model-value="load" />
                <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
                <v-btn v-if="error && !loaded" variant="tonal" :disabled="busy" @click="load()">Erneut laden</v-btn>
                <p v-if="busy && !preview && !loaded" role="status">Gespeicherte Vorbereitung wird geladen …</p>
                <template v-if="loaded && !preview">
                    <v-select v-if="local && !work.local_task_copy" v-model="transport" :items="transportOptions" label="Versandweg" density="compact" hide-details />
                    <p class="my-3">Die Vorschau zeigt die persönliche Nachricht und die Anhänge für jede zugeordnete Person.</p>
                    <v-btn color="primary" :loading="busy" @click="prepare">Entwürfe vorbereiten</v-btn>
                </template>
                <template v-if="preview">
                    <v-alert v-if="preview.unavailable" type="warning" variant="tonal" class="mb-3">{{ preview.unavailable }}</v-alert>
                    <p class="text-subtitle-1 font-weight-bold">{{ batch.withdrawn_at ? 'Versand gestoppt' : states[batch.state] }}</p>
                    <p class="text-body-2 mb-4">{{ transportLabel }} · {{ acceptedCount }} von {{ batch.recipients.length }} {{ batch.transport === 'mailpit' ? 'im lokalen Testpostfach' : 'vom Versanddienst angenommen' }}</p>
                    <dl class="mail-addresses">
                        <dt>Absender</dt><dd>{{ preview.from }}</dd>
                        <dt>Antwort an</dt><dd>{{ preview.reply_to }}</dd>
                        <dt>Anhänge</dt><dd>
                            <a v-for="(name, index) in preview.attachments" :key="index" class="d-block"
                               :href="mail.attachment.url({ course_work: work.id, batch: batch.id, index }, { query: { date: slot || undefined } })" download>{{ name }}</a>
                            <span v-if="!preview.attachments.length">Keine</span>
                        </dd>
                    </dl>
                    <v-select v-model="selected" :items="recipients" label="Persönliche Nachricht" class="mt-4" density="compact" hide-details />
                    <template v-if="message">
                        <p class="mt-3"><strong>{{ message.subject }}</strong></p>
                        <p v-if="currentRow?.error" class="text-body-2 text-error my-2">{{ currentRow.error }}</p>
                        <iframe :srcdoc="message.html" sandbox="" referrerpolicy="no-referrer" class="mail-preview" title="Persönliche E-Mail-Vorschau" />
                        <details><summary>Klartext anzeigen</summary><p class="mail-text">{{ message.text }}</p></details>
                    </template>
                    <v-checkbox v-if="previousCount && !batch.withdrawn_at" v-model="includePreviouslySent" density="compact" hide-details
                        :disabled="busy" :label="`Früheren Aufgabenversand an ${previousCount} Person(en) ausdrücklich wiederholen`" />
                    <div class="d-flex ga-2 flex-wrap mt-5">
                        <v-btn v-if="!preview.unavailable && !batch.withdrawn_at && canSend" color="primary" :disabled="busy" @click="act('send')">
                            {{ alreadySent ? 'Bestätigten Versand übernehmen' : batch.transport === 'mailpit' ? 'An lokales Testpostfach senden' : 'An Schüler:innen senden' }}
                        </v-btn>
                        <v-btn v-if="batch.state === 'uncertain'" variant="tonal" :disabled="busy" @click="act('reconcile')">Versandbelege prüfen</v-btn>
                        <v-btn v-if="canDiscard && !batch.withdrawn_at" variant="text" :disabled="busy" @click="act('discard')">Entwürfe verwerfen</v-btn>
                        <v-btn v-else-if="!batch.withdrawn_at" variant="tonal" :disabled="busy" @click="act('withdraw')">Verteilung zurückziehen</v-btn>
                        <v-btn v-else variant="tonal" :disabled="busy" @click="act('restore')">Verteilung wieder öffnen</v-btn>
                        <v-btn v-if="batch.withdrawn_at && !unclear" variant="text" :disabled="busy" @click="prepare">Neue Fassung vorbereiten</v-btn>
                    </div>
                    <p v-if="acceptedCount || batch.withdrawn_at" class="text-body-2 mt-3">Bereits versandte Nachrichten bleiben im Empfängerpostfach. Beim Zurückziehen wird der über den E-Mail-Versand erteilte Abgabezugang geschlossen.</p>
                </template>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <slot name="secondary-actions" :close="close" />
                <v-spacer />
                <v-btn variant="text" @click="close">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import * as mail from '@/actions/App/Http/Controllers/Admin/Teaching/WorkAssignmentMailController'

const props = defineProps({ work: { type: Object, required: true }, date: { type: String, default: '' }, disabled: Boolean, hideActivator: Boolean })
const emit = defineEmits(['saved'])
const opened = ref(false)
const loaded = ref(false)
const busy = ref(false)
const local = ref(false)
const error = ref('')
const preview = ref(null)
const selected = ref('')
const includePreviouslySent = ref(false)
const selectedDate = ref('')
const transport = ref('postmark')
const transportOptions = [{ title: 'Lokales Testpostfach (Mailpit)', value: 'mailpit' }, { title: 'Tatsächlicher Versand (Postmark)', value: 'postmark' }]
const states = { draft: 'Vorbereitet', already_sent: 'Identische Fassung bereits angenommen', queued: 'In der Warteschlange', sending: 'Versand läuft', accepted: 'Angenommen',
    sent: 'Versand abgeschlossen', partial: 'Teilweise abgeschlossen', rejected: 'Abgelehnt', uncertain: 'Einlieferung ungeklärt' }
const dates = computed(() => [...new Set((props.work.groups || []).map(group => group.date || props.work.date_for_all_groups).filter(Boolean))].sort())
const date = computed(() => props.date || '')
const slot = computed(() => date.value || selectedDate.value || props.work.date_for_all_groups || dates.value[0] || '')
const stored = computed(() => props.work.status?.assignment_mail?.batches?.[slot.value || 'all'])
const batch = computed(() => preview.value?.batch)
const acceptedCount = computed(() => batch.value?.recipients.filter(row => row.state === 'accepted').length || 0)
const alreadySent = computed(() => batch.value?.recipients.every(row => row.state === 'already_sent' || row.state === 'accepted'))
const previousCount = computed(() => batch.value?.transport === 'postmark'
    ? batch.value.recipients.filter(row => row.previously_sent_at && ['draft', 'rejected'].includes(row.state)).length : 0)
const recipients = computed(() => (batch.value?.recipients || []).map(row => ({
    title: (preview.value.messages[row.key]?.to || '') + ' · ' + states[row.state], value: row.key,
})))
const currentRow = computed(() => batch.value?.recipients.find(row => row.key === selected.value))
const message = computed(() => preview.value?.messages[selected.value])
const canSend = computed(() => batch.value?.recipients.some(row => row.state === 'already_sent'
    || ['draft', 'rejected'].includes(row.state) && (!previousCount.value || !row.previously_sent_at || includePreviouslySent.value)) && batch.value.state !== 'sending')
const canDiscard = computed(() => batch.value?.recipients.every(row => ['draft', 'already_sent', 'rejected'].includes(row.state)))
const unclear = computed(() => batch.value?.recipients.some(row => ['sending', 'queued', 'uncertain'].includes(row.state)))
const transportLabel = computed(() => batch.value?.transport === 'mailpit' ? 'Lokaler Testversand' : 'Postmark')
let sequence = 0
let timer = null
let source = null

function stop() {
    clearTimeout(timer)
    timer = null
    source?.abort()
    sequence++
    busy.value = false
}

async function request(call, announce = false, poll = false) {
    stop()
    const current = sequence
    source = new AbortController()
    busy.value = !poll
    if (!poll) error.value = ''
    try {
        const { data } = await call(source.signal)
        if (!opened.value || current !== sequence) return
        if (String(data.data?.id) !== String(props.work.id)) throw new Error('Die Arbeitszuordnung hat sich geändert.')
        if (!loaded.value) {
            local.value = data.local
            transport.value = data.local ? 'mailpit' : 'postmark'
        }
        loaded.value = true
        if (preview.value?.batch?.id !== data.preview?.batch?.id) includePreviouslySent.value = false
        preview.value = data.preview
        if (!data.preview?.messages?.[selected.value]) selected.value = data.preview?.batch?.recipients[0]?.key || ''
        if (announce || poll) emit('saved', data.data)
    } catch (failure) {
        if (opened.value && current === sequence) {
            error.value = Object.values(failure.response?.data?.errors || {}).flat()[0] || failure.response?.data?.message || failure.message || 'Die E-Mails konnten nicht geladen werden.'
        }
    } finally {
        if (current === sequence) {
            busy.value = false
            if (opened.value && batch.value?.state === 'sending') timer = setTimeout(() => load(true), 2300)
        }
    }
}

function load(poll = false) {
    if (poll !== true) {
        preview.value = null
        loaded.value = false
    }
    return request(signal => axios.get(mail.show.url(props.work.id), { params: { date: slot.value || undefined }, signal }), false, poll === true)
}
function open() {
    if (props.disabled || !props.work.id) return
    opened.value = true
    selectedDate.value = date.value || dates.value[0] || ''
    loaded.value = false
    preview.value = null
    load()
}
function close() {
    opened.value = false
}
function prepare() {
    if (busy.value) return
    return request(signal => axios.post(mail.prepare.url(props.work.id), { date: slot.value || null,
        transport: batch.value?.transport || transport.value, previous: batch.value?.id || null }, { signal, timeout: 120000 }), true)
}
function act(action) {
    if (busy.value || !batch.value) return
    return request(signal => axios.post(mail.act.url(props.work.id), {
        date: slot.value || null, batch_id: batch.value.id, action, confirmed: true,
        ...(action === 'send' && includePreviouslySent.value ? { include_previously_sent: true } : {}),
    }, { signal }), true)
}
watch(opened, value => { if (!value) stop() })
watch(() => [props.work.id, props.date], () => { opened.value = false; stop() })
onBeforeUnmount(stop)
defineExpose({ open })
</script>

<style scoped>
.mail-addresses { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: .3rem .9rem; font-size: .9rem; }
.mail-addresses dt { color: rgb(var(--v-theme-on-surface-variant)); }
.mail-addresses dd { margin: 0; overflow-wrap: anywhere; }
.mail-preview { width: 100%; height: min(53vh, 520px); min-height: 220px; border: 1px solid rgba(var(--v-theme-on-surface), .16); border-radius: 5px; margin: .6rem 0; }
.mail-text { white-space: pre-wrap; overflow-wrap: anywhere; margin: 8px 0; }
</style>
