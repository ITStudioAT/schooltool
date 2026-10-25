<template>
    <v-btn v-if="available && !ready && !statusOnly" size="small" variant="tonal" prepend-icon="mdi-email-edit-outline"
        :disabled="disabled" @click.stop="openDialog('prepare')">E-Mails vorbereiten</v-btn>
    <v-btn v-if="available && ready && !sent && !statusOnly" size="small" color="primary" variant="tonal" prepend-icon="mdi-send-outline"
        :disabled="disabled" @click.stop="openDialog('send')">E-Mails versenden</v-btn>
    <span v-if="available && statusOnly" class="d-inline-flex" :class="{ 'cursor-pointer': ready }"
        :tabindex="ready ? 0 : undefined" :role="ready ? 'button' : undefined"
        :aria-haspopup="ready ? 'dialog' : undefined" :aria-expanded="ready ? infoOpened : undefined"
        :aria-label="tooltip" :title="tooltip" @click.stop="openInfo"
        @keydown.enter.stop.prevent="openInfo" @keydown.space.stop.prevent="openInfo">
        <v-icon :size="size" :color="color" :aria-label="tooltip" :aria-hidden="false" role="img">mdi-email-edit-outline</v-icon>
        <v-tooltip activator="parent" location="top" :text="tooltip" :open-delay="200" :max-width="420" />
    </span>
    <v-dialog v-if="infoOpened && available && ready && statusOnly" v-model="infoOpened" scrollable max-width="640">
        <v-card>
            <v-card-title class="text-wrap">Versandbereitschaft · {{ work.title || 'Ohne Titel' }}</v-card-title>
            <v-card-text>
                <v-alert type="success" variant="tonal" class="mb-4">{{ sent ? 'Vorbereitung abgeschlossen – E-Mails versandt.' : 'Versandbereit – noch nicht versandt.' }}</v-alert>
                <dl class="preparation-info">
                    <dt>Versandbereit seit</dt><dd>{{ preparedTime || 'Nicht angegeben' }}</dd>
                    <dt>Vorbereitete E-Mails</dt><dd>{{ recipientsCount }}</dd>
                    <dt>Zuletzt geprüft</dt><dd>{{ checkTime || 'Nicht angegeben' }}</dd>
                    <dt>Prüfungsordner</dt><dd>{{ state.folder?.path || state.folder?.name }}</dd>
                </dl>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn @click="infoOpened = false">Schließen</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog v-if="opened && available && !statusOnly" v-model="opened" scrollable max-width="720" :persistent="loading">
        <v-card>
            <v-card-title class="text-wrap">{{ sending ? 'E-Mails versenden' : 'E-Mails vorbereiten' }} · {{ work.title }}</v-card-title>
            <v-card-text>
                <v-alert v-if="ready" type="success" variant="tonal" class="mb-3">Versandbereit – noch nicht versandt.</v-alert>
                <p v-if="preparation?.message" class="text-body-2 mb-3">{{ preparation.message }}</p>
                <template v-if="sending">
                    <p class="text-body-2 mb-3">Gespeicherte E-Mails verwenden: Versandart wählen und den Auftrag an die KI übergeben.</p>
                    <v-btn-toggle v-model="deliveryMode" color="primary" divided :disabled="disabled" aria-label="Versandart für die E-Mails">
                        <v-btn value="live">Tatsächlich versenden</v-btn>
                        <v-btn value="test">Test an mich</v-btn>
                    </v-btn-toggle>
                    <p v-if="localMailpit" class="text-caption text-orange-darken-3 mt-2 font-weight-bold" role="status">{{ deliveryTransport.label }} · Keine Zustellung an externe Postfächer.</p>
                    <p v-if="deliveryMode === 'test'" class="text-caption mt-2">Genau eine vorbereitete E-Mail an {{ result?.context?.delivery?.recipient || admin.config?.user?.email }}. Ohne Versandaufzeichnung.</p>
                    <p class="text-caption mt-2">Der Auftrag gibt die gewählte Versandart frei. Das Kopieren allein sendet nichts.</p>
                </template>
                <template v-else>
                    <p class="text-body-2 mb-3">Den Vorbereitungsauftrag kopieren und an die KI übergeben. Sie bereitet Empfänger, persönliche Links, Nachrichtentexte und ausgewählte Anhänge zur Prüfung vor.</p>
                    <p class="text-caption mb-3">Die Vorbereitung sendet keine E-Mails. Mit „Erneut prüfen“ wird anschließend der gespeicherte Vorbereitungsstand gelesen.</p>
                    <p class="text-body-2 mb-2">Wie sollen die Schüler:innen angesprochen werden?</p>
                    <v-btn-toggle v-model="addressForm" color="primary" divided :disabled="loading" aria-label="Anredeform für die E-Mails">
                        <v-btn value="du">Du-Form</v-btn>
                        <v-btn value="sie">Sie-Form</v-btn>
                    </v-btn-toggle>
                </template>
                <p v-if="preparation?.prepared_at" class="text-caption mb-3">Vorbereitet: {{ preparedTime }}</p>
                <p v-if="loading" role="status" class="text-body-2 mt-3">KI-Auftrag wird geladen …</p>
                <v-alert v-if="error" type="error" variant="tonal" role="alert">{{ error }}</v-alert>
                <p v-if="message" role="status" class="text-body-2 mt-3">{{ message }}</p>
                <details v-if="result" class="mt-3">
                    <summary>{{ requestLabel }} anzeigen</summary>
                    <textarea ref="copyText" :value="result.text" readonly rows="10" :aria-label="`${requestLabel} zum Kopieren`" class="preparation-text" />
                </details>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn :disabled="loading" @click="opened = false">Schließen</v-btn>
                <v-btn v-if="error && !result" :disabled="loading" @click="loadPreparation">Erneut laden</v-btn>
                <v-btn :color="copied ? 'success' : 'primary'" :prepend-icon="copied ? 'mdi-check' : 'mdi-content-copy'"
                    :loading="loading || copying" :disabled="disabled || loading || copying || !result" @click="copyPreparation">{{ copied ? 'Kopiert' : 'Auftrag kopieren' }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { aiExport } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workNeedsPreparation } from '@/helpers/workOverview'
import { workDropboxKey, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'

const props = defineProps({ work: Object, state: Object, date: { type: String, default: '' }, disabled: Boolean, statusOnly: Boolean, size: { type: [Number, String], default: 30 } })
const admin = useAdminStore()
const opened = ref(false)
const infoOpened = ref(false)
const mode = ref('prepare')
const addressForm = ref(null)
const deliveryMode = ref(null)
const loading = ref(false)
const copying = ref(false)
const copied = ref(false)
const result = ref(null)
const error = ref('')
const message = ref('')
const copyText = ref(null)
let sequence = 0
let controller = null
const available = computed(() => workAutomationEnabled(props.work) && workNeedsPreparation(props.work)
    && props.state?.status === 'found' && props.state.userId === admin.config?.user?.id
    && String(props.state.workId) === String(props.work?.id)
    && String(props.state.courseId) === String(props.work?.teaching_course_id) && props.state.date === props.date
    && Boolean(props.state.folder?.id))
const preparation = computed(() => props.state?.emailPreparation)
const ready = computed(() => preparation.value?.status === 'ready')
const sent = computed(() => {
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    return (props.state?.emailDispatch || workExerciseCheck(key, props.work, props.date)?.data?.exercise?.email_dispatch
        || workDropboxStates.get(key)?.exercise?.email_dispatch)?.status === 'sent'
})
const sending = computed(() => mode.value === 'send')
const deliveryTransport = computed(() => admin.config?.teaching_email_delivery || { mode: 'office_outlook', available: true })
const localMailpit = computed(() => deliveryTransport.value.mode === 'mailpit')
const requestLabel = computed(() => sending.value ? 'Versandauftrag' : 'Vorbereitungsauftrag')
const color = computed(() => ready.value ? 'success' : preparation.value?.status === 'unknown' ? 'grey' : 'error')
const tooltip = computed(() => ready.value ? (sent.value ? 'E-Mail-Vorbereitung abgeschlossen' : 'Versandbereit – noch nicht versandt')
    : preparation.value?.status === 'unknown' ? 'E-Mail-Vorbereitung konnte nicht bestätigt werden' : 'E-Mails vorbereiten')
const preparedTime = computed(() => formatTime(preparation.value?.prepared_at))
const checkTime = computed(() => formatTime(props.state?.checkedAt))
const recipientsCount = computed(() => Number.isInteger(preparation.value?.recipients_count) && preparation.value.recipients_count >= 0
    ? String(preparation.value.recipients_count) : 'Nicht angegeben')

function formatTime(value) {
    return Number.isFinite(Date.parse(value)) ? new Intl.DateTimeFormat('de-AT', {
        timeZone: 'Europe/Vienna', dateStyle: 'short', timeStyle: 'short',
    }).format(new Date(value)) : ''
}

function openInfo() {
    if (available.value && ready.value && props.statusOnly) infoOpened.value = true
}

function clear() {
    sequence++
    controller?.abort()
    loading.value = false
    copying.value = false
    copied.value = false
    result.value = null
    error.value = ''
    message.value = ''
}

function openDialog(selectedMode) {
    if (!available.value || props.disabled || (selectedMode === 'send' && !ready.value)) return
    clear()
    mode.value = selectedMode
    addressForm.value = null
    deliveryMode.value = null
    opened.value = true
}

async function loadPreparation() {
    if (!opened.value || !available.value || props.disabled || loading.value
        || (sending.value ? !ready.value || !['live', 'test'].includes(deliveryMode.value) : !['du', 'sie'].includes(addressForm.value))) return
    clear()
    const operation = sequence
    const folderId = props.state.folder.id
    const selectedAddressForm = addressForm.value
    const selectedDeliveryMode = deliveryMode.value
    const dispatch = sending.value
    controller = new AbortController()
    loading.value = true
    try {
        const response = await axios.get(aiExport.url(props.work.id), { params: {
            purpose: dispatch ? 'email_dispatch' : 'email_preparation',
            ...(dispatch ? { delivery_mode: selectedDeliveryMode } : { address_form: selectedAddressForm }),
            ...(props.date ? { date: props.date } : {}) }, signal: controller.signal })
        if (operation !== sequence || !opened.value || !available.value) return
        const data = response.data.data
        if (String(data?.work_id) !== String(props.work.id) || String(data?.course_id) !== String(props.work.teaching_course_id)
            || data.roster_complete !== true || data.context?.purpose !== (dispatch ? 'send-exercise-emails' : 'prepare-exercise-emails')
            || data.context?.exercise_folder?.id !== folderId || data.context?.delivery?.send_authorized !== dispatch
            || (dispatch ? data.context?.email_preparation?.status !== 'ready'
                || data.context?.delivery?.selection !== selectedDeliveryMode
                || data.context?.delivery?.mode !== `${selectedDeliveryMode === 'test' ? 'test_' : 'live_'}${deliveryTransport.value.mode}`
                || (localMailpit.value && (data.context?.delivery?.local_only !== true
                    || data.context?.delivery?.smtp_host !== '127.0.0.1' || data.context?.delivery?.smtp_port !== 1025))
                || data.context?.delivery?.requires_explicit_send_confirmation !== false
                || data.context?.delivery?.record_dispatch !== (selectedDeliveryMode === 'live')
                || (selectedDeliveryMode === 'test' && (data.context?.delivery?.messages_count !== 1
                    || String(data.context?.delivery?.recipient_user_id) !== String(admin.config?.user?.id)
                    || !data.context?.delivery?.recipient || data.context.delivery.recipient !== admin.config?.user?.email?.trim()))
                : data.context?.email_address_form !== selectedAddressForm)
            || typeof data.text !== 'string' || !data.text.trim()) throw new Error(`Der ${requestLabel.value} konnte nicht eindeutig bestätigt werden.`)
        result.value = data
    } catch (exception) {
        if (operation === sequence && !controller?.signal.aborted) error.value = exception.response?.data?.message || exception.message || `${requestLabel.value} konnte nicht geladen werden.`
    } finally {
        if (operation === sequence) loading.value = false
    }
}

async function copyPreparation() {
    if (!opened.value || !available.value || props.disabled || loading.value || copying.value || !result.value || (sending.value && !ready.value)) return
    const operation = sequence
    copying.value = true
    copied.value = false
    error.value = ''
    message.value = ''
    try {
        try {
            await navigator.clipboard.writeText(result.value.text)
        } catch {
            if (operation !== sequence) return
            const details = copyText.value?.closest('details')
            if (details) details.open = true
            copyText.value?.focus()
            copyText.value?.select()
            if (document.execCommand?.('copy') !== true) throw new Error('Der Browser hat das Kopieren blockiert. Bitte erneut auf „Auftrag kopieren“ klicken.')
        }
        if (operation === sequence) {
            copied.value = true
            message.value = `${requestLabel.value} kopiert. Jetzt an die KI übergeben.`
        }
    } catch (exception) {
        if (operation === sequence) error.value = exception.message
    } finally {
        if (operation === sequence) copying.value = false
    }
}

watch(() => [available.value, admin.config?.user?.id, admin.config?.user?.email, props.work?.id, props.work?.teaching_course_id, props.date, props.state?.folder?.id,
    preparation.value?.status, preparation.value?.prepared_at], () => { clear(); opened.value = false; infoOpened.value = false })
watch(opened, value => { if (!value) clear() })
watch(addressForm, () => {
    if (sending.value) return
    if (opened.value && ['du', 'sie'].includes(addressForm.value)) loadPreparation()
    else clear()
})
watch(deliveryMode, () => {
    if (!sending.value) return
    clear()
    if (opened.value && ['live', 'test'].includes(deliveryMode.value)) loadPreparation()
})
onBeforeUnmount(clear)
</script>

<style scoped>
.preparation-text { width: 100%; padding: 8px; border: 1px solid currentColor; border-radius: 4px; font-size: 0.75rem; }
.preparation-info { display: grid; grid-template-columns: minmax(0, 160px) minmax(0, 1fr); gap: 12px 16px; }
.preparation-info dt { font-weight: 600; }
.preparation-info dd { margin: 0; overflow-wrap: anywhere; }
</style>
