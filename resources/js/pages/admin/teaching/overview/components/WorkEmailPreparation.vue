<template>
    <v-btn v-if="available && !ready && !statusOnly" size="small" variant="tonal" prepend-icon="mdi-email-edit-outline"
        :disabled="disabled" @click.stop="openDialog('prepare')">E-Mails vorbereiten</v-btn>
    <v-btn v-if="available && ready && !sent && !statusOnly" size="small" color="primary" variant="tonal" prepend-icon="mdi-send-outline"
        :disabled="disabled" @click.stop="openDialog('send')">E-Mails versenden</v-btn>
    <span v-if="statusOnly && (workAutomationEnabled(work) || workHasProgress(work))" class="d-inline-flex" :class="{ 'cursor-pointer': canInspect }"
        :tabindex="canInspect ? 0 : undefined" :role="canInspect ? 'button' : undefined"
        :aria-disabled="!canInspect" :aria-haspopup="canInspect ? 'dialog' : undefined" :aria-expanded="canInspect ? infoOpened : undefined"
        :aria-label="tooltip" :title="tooltip" @click.stop="openInfo"
        @keydown.enter.stop.prevent="openInfo" @keydown.space.stop.prevent="openInfo">
        <v-icon :size="size" :color="color" :aria-label="tooltip" :aria-hidden="false" role="img">mdi-email-edit-outline</v-icon>
        <v-tooltip activator="parent" location="top" :text="tooltip" :open-delay="200" :max-width="420" />
    </span>
    <v-dialog v-if="infoOpened && available && ready && statusOnly" v-model="infoOpened" scrollable :max-width="emailPreview ? 900 : 640">
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
                <p v-if="emailPreviewLoading" role="status" class="text-body-2 mt-4">E-Mail wird geladen …</p>
                <v-alert v-if="emailPreviewError" type="error" variant="tonal" role="alert" class="mt-4">{{ emailPreviewError }}</v-alert>
                <section v-if="emailPreview" aria-labelledby="email-preview-title" class="email-preview mt-4">
                    <h3 id="email-preview-title">Eine vorbereitete E-Mail</h3>
                    <dl class="preparation-info mt-3">
                        <dt>Von</dt><dd>{{ emailPreview.from }}</dd>
                        <dt>An</dt><dd>{{ emailPreview.to }}</dd>
                        <dt>Betreff</dt><dd>{{ emailPreview.subject }}</dd>
                        <dt>Anhänge</dt><dd>{{ emailPreview.attachments.length ? emailPreview.attachments.join(', ') : 'Keine' }}</dd>
                    </dl>
                    <p class="text-caption mt-3">Persönliche Links und externe Inhalte sind in dieser Vorschau deaktiviert.</p>
                    <iframe :srcdoc="emailPreview.document" sandbox="" referrerpolicy="no-referrer" title="Vorschau der vorbereiteten E-Mail" class="email-preview__frame" />
                </section>
            </v-card-text>
            <v-card-actions>
                <v-btn color="primary" variant="tonal" prepend-icon="mdi-eye-outline" :loading="emailPreviewLoading"
                    :disabled="emailPreviewLoading || !canInspect" @click="loadEmailPreview">{{ emailPreview ? 'Vorschau aktualisieren' : 'E-Mail ansehen' }}</v-btn>
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
import { aiExport, emailPreparationPreview } from '@/actions/App/Http/Controllers/Admin/Teaching/CourseWorkController'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workNeedsPreparation } from '@/helpers/workOverview'
import { workHasProgress } from '@/helpers/workProgress'
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
const emailPreview = ref(null)
const emailPreviewLoading = ref(false)
const emailPreviewError = ref('')
let sequence = 0
let controller = null
let emailPreviewSequence = 0
let emailPreviewController = null
const available = computed(() => workAutomationEnabled(props.work) && workNeedsPreparation(props.work)
    && props.state?.status === 'found' && props.state.userId === admin.config?.user?.id
    && String(props.state.workId) === String(props.work?.id)
    && String(props.state.courseId) === String(props.work?.teaching_course_id) && props.state.date === props.date
    && Boolean(props.state.folder?.id))
const preparation = computed(() => props.state?.emailPreparation)
const ready = computed(() => preparation.value?.status === 'ready')
const canInspect = computed(() => available.value && ready.value && !props.disabled)
const sent = computed(() => {
    const key = workDropboxKey(admin.config?.user?.id, props.work)
    return (props.state?.emailDispatch || workExerciseCheck(key, props.work, props.date)?.data?.exercise?.email_dispatch
        || workDropboxStates.get(key)?.exercise?.email_dispatch)?.status === 'sent'
})
const sending = computed(() => mode.value === 'send')
const deliveryTransport = computed(() => admin.config?.teaching_email_delivery || { mode: 'office_outlook', available: true })
const localMailpit = computed(() => deliveryTransport.value.mode === 'mailpit')
const requestLabel = computed(() => sending.value ? 'Versandauftrag' : 'Vorbereitungsauftrag')
const color = computed(() => !available.value ? 'grey' : ready.value ? 'success' : preparation.value?.status === 'unknown' ? 'grey' : 'error')
const tooltip = computed(() => !available.value ? 'E-Mails vorbereiten: zuerst Leistungsfeststellung bestätigen'
    : ready.value ? (sent.value ? 'E-Mail-Vorbereitung abgeschlossen' : 'Versandbereit – noch nicht versandt')
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
    if (canInspect.value && props.statusOnly) infoOpened.value = true
}

function clearEmailPreview() {
    emailPreviewSequence++
    emailPreviewController?.abort()
    emailPreviewController = null
    emailPreview.value = null
    emailPreviewLoading.value = false
    emailPreviewError.value = ''
}

function isolatedEmailDocument(html) {
    const template = document.createElement('template')
    template.innerHTML = html
    template.content.querySelectorAll('form').forEach(form => form.replaceWith(...form.childNodes))
    template.content.querySelectorAll('script, iframe, frame, frameset, object, embed, base, meta, link, audio, video, source, track, template, animate, animateMotion, animateTransform, set, discard').forEach(element => element.remove())
    template.content.querySelectorAll('*').forEach(element => {
        for (const attribute of [...element.attributes]) {
            const name = attribute.name.toLowerCase()
            const isInlineImage = element.tagName.toLowerCase() === 'img' && name === 'src'
                && /^data:image\/(?:png|jpeg|gif|webp);base64,/i.test(attribute.value)
            if (name.startsWith('on') || ['href', 'xlink:href', 'srcset', 'srcdoc', 'formaction', 'action', 'target', 'ping', 'poster', 'background'].includes(name)
                || (name === 'src' && !isInlineImage)) element.removeAttribute(attribute.name)
        }
    })
    const policy = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; font-src data:; form-action 'none'; base-uri 'none'; object-src 'none'; connect-src 'none'; media-src 'none'; frame-src 'none'"
    return `<!doctype html><html lang="de"><head><meta http-equiv="Content-Security-Policy" content="${policy}"></head><body>${template.innerHTML}</body></html>`
}

async function loadEmailPreview() {
    if (!infoOpened.value || !canInspect.value || emailPreviewLoading.value) return
    clearEmailPreview()
    const operation = emailPreviewSequence
    const requestController = new AbortController()
    emailPreviewController = requestController
    emailPreviewLoading.value = true
    try {
        const response = await axios.get(emailPreparationPreview.url(props.work.id), {
            params: props.date ? { date: props.date } : {}, signal: requestController.signal,
        })
        if (operation !== emailPreviewSequence || !infoOpened.value || !canInspect.value) return
        const data = response.data?.data
        if (String(data?.work_id) !== String(props.work.id) || String(data?.course_id) !== String(props.work.teaching_course_id)
            || data?.folder_id !== props.state.folder.id
            || (preparation.value?.prepared_at && Date.parse(data?.prepared_at) !== Date.parse(preparation.value.prepared_at))
            || !['from', 'to', 'subject', 'html'].every(key => typeof data?.[key] === 'string' && data[key].trim())
            || !Array.isArray(data.attachments) || !data.attachments.every(name => typeof name === 'string')) {
            throw new Error('Die vorbereitete E-Mail konnte nicht eindeutig zugeordnet werden.')
        }
        emailPreview.value = { from: data.from, to: data.to, subject: data.subject, attachments: data.attachments,
            document: isolatedEmailDocument(data.html) }
    } catch (exception) {
        if (operation === emailPreviewSequence && !requestController.signal.aborted) {
            emailPreviewError.value = exception.response?.data?.message || exception.message || 'Die E-Mail konnte nicht geladen werden.'
        }
    } finally {
        if (operation === emailPreviewSequence) emailPreviewLoading.value = false
    }
}

function clear() {
    sequence++
    controller?.abort()
    clearEmailPreview()
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
watch(infoOpened, value => { if (!value) clearEmailPreview() })
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
.email-preview__frame { display: block; width: 100%; height: 430px; margin-top: 12px; border: 1px solid #d4dce5; background: white; }
</style>
