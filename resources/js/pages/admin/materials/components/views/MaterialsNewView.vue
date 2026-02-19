<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <v-row class="mb-6" align="center">
            <v-col cols="12">
                <div class="text-h4 font-weight-bold mb-2">Neues Material</div>
                <div class="text-subtitle-1 subline">Wähle eine Möglichkeit: Neues Material, Upload oder Zwischenablage.</div>
            </v-col>
        </v-row>

        <v-row v-if="!createFormOpen" dense>
            <v-col cols="12" md="4">
                <MaterialsAddOption @add-material="openCreateForm" />
            </v-col>

            <v-col cols="12" md="4">
                <MaterialsDropOption />
            </v-col>

            <v-col cols="12" md="4">
                <MaterialsClipboardOption />
            </v-col>
        </v-row>

        <v-expand-transition>
            <div v-if="createFormOpen" class="mt-6">
                <v-alert
                    v-if="clipboardModeActive"
                    :type="clipboardStatus.type"
                    variant="tonal"
                    class="mb-4">
                    {{ clipboardStatus.message }}
                </v-alert>

                <v-btn
                    v-if="clipboardModeActive"
                    block
                    size="x-large"
                    color="primary"
                    class="mb-4"
                    prepend-icon="mdi-clipboard-check-outline"
                    @click="runClipboardTransfer">
                    Zwischenablage übernehmen
                </v-btn>

                <v-textarea
                    v-if="clipboardModeActive"
                    ref="manualPasteField"
                    v-model="manualPasteBuffer"
                    label="Manuell aus Zwischenablage"
                    placeholder="Hier hineinklicken und Strg+V drücken"
                    variant="outlined"
                    density="comfortable"
                    rows="2"
                    auto-grow
                    hide-details="auto"
                    class="mb-4"
                    @paste="onManualPaste" />

                <v-card
                    v-if="clipboardSummary"
                    variant="outlined"
                    class="pa-4 mb-4">
                    <div class="text-subtitle-2 font-weight-bold mb-2">Erkannt eingefügt</div>

                    <div class="d-flex flex-wrap ga-2 mb-3">
                        <v-chip v-for="(type, index) in clipboardSummary.types" :key="`${type}-${index}`" size="small" variant="flat" color="primary">
                            {{ type }}
                        </v-chip>
                    </div>

                    <div v-if="clipboardSummary.link" class="text-body-2 mb-1">
                        <strong>Link:</strong>
                        {{ clipboardSummary.link }}
                    </div>

                    <div v-if="clipboardSummary.textPreview" class="text-body-2 mb-1 clipboard-full-text">
                        <strong>Text:</strong>
                        {{ clipboardSummary.textPreview }}
                    </div>

                    <div v-if="clipboardSummary.files.length" class="text-body-2 mb-1">
                        <strong>Dateien:</strong>
                        {{ clipboardSummary.files.join(', ') }}
                    </div>

                    <div v-if="clipboardImagePreviews.length" class="text-body-2 mt-3 mb-2">
                        <strong>Grafikvorschau:</strong>
                    </div>

                    <div v-if="clipboardImagePreviews.length" class="clipboard-image-grid mb-2">
                        <v-card
                            v-for="(preview, index) in clipboardImagePreviews"
                            :key="`preview-${index}`"
                            variant="outlined"
                            class="pa-1">
                            <v-img :src="preview.url" :alt="preview.name" width="92" height="92" cover />
                        </v-card>
                    </div>

                    <div class="text-caption text-medium-emphasis mt-2">
                        Quelle: {{ clipboardSummary.source }} | {{ clipboardSummary.time }}
                    </div>
                </v-card>

                <v-alert
                    v-if="pendingAttachments.length"
                    type="success"
                    variant="tonal"
                    class="mb-4">
                    {{ pendingAttachments.length }} Datei(en) erkannt. Sie werden nach dem Speichern als Anhänge hinzugefügt.
                </v-alert>

                <div class="material-form-width mx-auto">
                    <MaterialsCreateInlineForm
                        ref="createInlineForm"
                        :title="createForm.title"
                        :description="createForm.description"
                        :material-type="createForm.type"
                        :pending-attachments="pendingAttachments"
                        :max-upload-size-kb="maxUploadSizeKb"
                        :type-options="typeOptions"
                        :can-manage-types="canManageTypeValues"
                        :status="createForm.status"
                        :status-options="statusOptions"
                        :classifications="createForm.classifications"
                        :classification-tree="classificationTree"
                        :classification-editor-visible="createClassificationEditorVisible"
                        :classification-toggleable="true"
                        :is-saving="isSaving"
                        :autofocus-title="!clipboardModeActive"
                        @update:title="createForm.title = $event"
                        @update:description="createForm.description = $event"
                        @update:materialType="createForm.type = $event"
                        @update:pendingAttachments="pendingAttachments = $event"
                        @remove-temp-upload="removePendingTempUpload"
                        @upload-error="notifyUploadError"
                        @add-files="addPendingAttachmentsFromPicker"
                        @update:status="createForm.status = $event"
                        @update:classifications="createForm.classifications = $event"
                        @update:classificationEditorVisible="createClassificationEditorVisible = $event"
                        @manage-types="openTypeManager"
                        @save="saveNewMaterial"
                        @cancel="cancelCreateForm" />
                </div>
            </div>
        </v-expand-transition>
    </v-card>

    <MaterialTypeManagerDialog v-model="typeManagerDialogOpen" />
</template>

<script>
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import MaterialsAddOption from '../options/MaterialsAddOption.vue'
import MaterialsClipboardOption from '../options/MaterialsClipboardOption.vue'
import MaterialsDropOption from '../options/MaterialsDropOption.vue'
import MaterialsCreateInlineForm from '../forms/MaterialsCreateInlineForm.vue'
import MaterialTypeManagerDialog from '../forms/MaterialTypeManagerDialog.vue'

export default {
    name: 'MaterialsNewView',
    emits: ['menu-lock-change'],
    components: {
        MaterialsAddOption,
        MaterialsClipboardOption,
        MaterialsDropOption,
        MaterialsCreateInlineForm,
        MaterialTypeManagerDialog,
    },
    data() {
        return {
            materialCardStore: null,
            createFormOpen: false,
            isSaving: false,
            typeManagerDialogOpen: false,
            createClassificationEditorVisible: false,
            pendingAttachments: [],
            clipboardModeActive: false,
            clipboardSummary: null,
            clipboardImagePreviews: [],
            clipboardStatus: {
                type: 'info',
                message: 'Bereit: Klicke auf „Zwischenablage übernehmen“. Falls nötig, Strg+V im Feld darunter.',
            },
            manualPasteBuffer: '',
            createForm: {
                title: '',
                description: '',
                type: '',
                status: '',
                classifications: [{ subject: '', topic: '', unit: '' }],
            },
        }
    },
    computed: {
        statusOptions() {
            const items = this.materialCardStore?.config?.status_values
            return Array.isArray(items) && items.length
                ? items
                : [
                    { value: 'inbox', label: 'Neu/Idee', color: '#607d8b' },
                    { value: 'in_progress', label: 'In Arbeit', color: '#f9a825' },
                    { value: 'done', label: 'ok', color: '#2e7d32' },
                    { value: 'update_needed', label: 'Änderung nötig', color: '#c62828' },
                ]
        },
        typeOptions() {
            const items = this.materialCardStore?.config?.type_values
            return Array.isArray(items) ? items : []
        },
        canManageTypeValues() {
            return this.materialCardStore?.config?.can_manage_type_values === true
        },
        classificationTree() {
            const items = this.materialCardStore?.config?.classification_tree
            return Array.isArray(items) ? items : []
        },
        defaultStatusValue() {
            return String(this.statusOptions?.[0]?.value || '').trim() || 'inbox'
        },
        maxUploadSizeKb() {
            const value = Number(this.materialCardStore?.config?.file_settings?.max_upload_size_kb)
            if (!Number.isFinite(value) || value <= 0) return 20480
            return Math.max(1, Math.round(value))
        },
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        if (!this.materialCardStore.config) {
            await this.materialCardStore.loadConfig()
        }
        if (!String(this.createForm.status || '').trim()) {
            this.createForm.status = this.defaultStatusValue
        }
    },
    unmounted() {
        this.cleanupPendingTempUploads().catch(() => {})
        this.revokeClipboardImagePreviews()
        this.$emit('menu-lock-change', false)
    },
    methods: {
        openCreateForm(payload = null, options = {}) {
            const clipboardMode = options?.clipboardMode === true
            this.resetCreateForm()
            this.clipboardModeActive = clipboardMode

            if (payload && typeof payload === 'object') {
                this.applyClipboardPayload(payload, options?.sourceLabel || 'Direktzugriff')
            }

            this.createFormOpen = true
            this.$emit('menu-lock-change', true)

            if (clipboardMode) {
                this.focusManualPasteField()
            } else {
                this.focusCreateTitleField()
            }
        },
        focusCreateTitleField() {
            this.$nextTick(() => {
                const form = this.$refs.createInlineForm
                if (form && typeof form.focusTitle === 'function') {
                    form.focusTitle()
                }
            })
        },
        async insertFromClipboard() {
            this.openCreateForm(null, { clipboardMode: true })
            await this.runClipboardTransfer()
        },
        async runClipboardTransfer() {
            try {
                const payload = await this.readClipboardPayload()
                const hasDirectContent = !!payload && (payload.title || payload.description || payload.files?.length)

                if (hasDirectContent) {
                    this.applyClipboardPayload(payload, 'Direktzugriff')
                    const typesLabel = Array.isArray(payload?.summary?.types) ? payload.summary.types.join(', ') : 'Inhalt'
                    this.setClipboardStatus('success', `Übernahme erfolgreich (${typesLabel}).`)
                    this.focusManualPasteField()
                    return
                }

                const hasManualBuffer = String(this.manualPasteBuffer || '').trim().length > 0
                if (hasManualBuffer) {
                    this.applyManualBuffer()
                    this.setClipboardStatus('success', 'Übernahme aus dem Textfeld erfolgreich.')
                    this.focusManualPasteField()
                    return
                }

                this.setClipboardStatus('warning', 'Kein Inhalt erkannt. Bitte Strg+V im Feld verwenden und erneut klicken.')
                this.focusManualPasteField()
            } catch (error) {
                const hasManualBuffer = String(this.manualPasteBuffer || '').trim().length > 0
                if (hasManualBuffer) {
                    this.applyManualBuffer()
                    this.setClipboardStatus('success', 'Direktzugriff blockiert, Übernahme aus dem Textfeld erfolgreich.')
                } else {
                    this.setClipboardStatus('warning', 'Direktzugriff blockiert. Bitte Strg+V im Feld verwenden und erneut klicken.')
                }
                this.focusManualPasteField()
            }
        },
        setClipboardStatus(type, message) {
            this.clipboardStatus = {
                type: type || 'info',
                message: message || '',
            }
        },
        focusManualPasteField() {
            const attemptFocus = (attempt = 0) => {
                const field = this.$refs.manualPasteField
                const textarea = field?.$el?.querySelector?.('textarea')

                if (field && typeof field.focus === 'function') {
                    field.focus()
                }
                if (textarea && typeof textarea.focus === 'function') {
                    textarea.focus()
                }

                const focused = document?.activeElement === textarea
                if (!focused && attempt < 6) {
                    setTimeout(() => attemptFocus(attempt + 1), 80)
                }
            }

            this.$nextTick(() => {
                attemptFocus(0)
            })
        },
        async readClipboardPayload() {
            let text = ''
            let html = ''
            const files = []
            let hasTable = false

            if (navigator?.clipboard?.read) {
                const items = await navigator.clipboard.read()
                for (const item of items) {
                    if (!text && item.types.includes('text/plain')) {
                        const plainBlob = await item.getType('text/plain')
                        text = await plainBlob.text()
                    }
                    if (!html && item.types.includes('text/html')) {
                        const htmlBlob = await item.getType('text/html')
                        html = await htmlBlob.text()
                        hasTable = /<table[\s>]/i.test(html)
                    }

                    const imageType = item.types.find((type) => type.startsWith('image/'))
                    if (imageType) {
                        const imageBlob = await item.getType(imageType)
                        const extension = imageType.split('/')[1] || 'png'
                        const fileName = `zwischenablage-${Date.now()}.${extension}`
                        files.push(new File([imageBlob], fileName, { type: imageType }))
                    }
                }
            } else if (navigator?.clipboard?.readText) {
                text = await navigator.clipboard.readText()
            } else {
                throw new Error('clipboard_api_not_supported')
            }

            if (!text && html) {
                text = this.htmlToText(html)
            }

            return this.buildClipboardPayload({
                text,
                html,
                files,
                hasTable,
            })
        },
        buildClipboardPayload({ text = '', html = '', files = [], hasTable = false }) {
            const parsed = this.parseClipboardText(text)
            const link = this.extractFirstUrl(text)
            const types = []

            if (text) types.push('Text')
            if (link) types.push('Link')
            if (html) types.push('Formatierter Inhalt')
            if (hasTable) types.push('Tabelle')
            if (files.some((file) => String(file.type || '').startsWith('image/'))) types.push('Bild')
            if (files.some((file) => !String(file.type || '').startsWith('image/'))) types.push('Datei')
            if (!types.length) types.push('Unbekannt')

            return {
                ...parsed,
                files,
                summary: {
                    types,
                    link,
                    textPreview: this.toFullText(text),
                    files: files.map((file) => file.name || 'Datei ohne Namen'),
                },
            }
        },
        applyClipboardPayload(payload, sourceLabel = 'Übernahme') {
            const title = String(payload?.title || '').trim()
            const description = String(payload?.description || '').trim()
            const files = Array.isArray(payload?.files) ? payload.files : []

            if (title) this.createForm.title = title.slice(0, 255)
            if (description) this.createForm.description = description
            if (files.length) {
                this.pendingAttachments = this.mergeUniquePendingAttachments(this.pendingAttachments, files, 'clipboard')
            }
            this.updateClipboardImagePreviews(this.pendingAttachments)

            const summary = payload?.summary || {}
            this.clipboardSummary = {
                types: Array.isArray(summary.types) ? summary.types : ['Unbekannt'],
                link: summary.link || '',
                textPreview: summary.textPreview || '',
                files: Array.isArray(summary.files) ? summary.files : [],
                source: sourceLabel,
                time: new Intl.DateTimeFormat('de-AT', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                }).format(new Date()),
            }
        },
        updateClipboardImagePreviews(attachments) {
            this.revokeClipboardImagePreviews()
            const files = this.extractFilesFromPendingAttachments(attachments)
            const imageFiles = (files || []).filter((file) => String(file?.type || '').startsWith('image/'))
            this.clipboardImagePreviews = imageFiles.map((file) => ({
                name: file?.name || 'Grafik',
                url: URL.createObjectURL(file),
            }))
        },
        revokeClipboardImagePreviews() {
            if (!Array.isArray(this.clipboardImagePreviews)) return
            this.clipboardImagePreviews.forEach((item) => {
                if (item?.url) {
                    URL.revokeObjectURL(item.url)
                }
            })
            this.clipboardImagePreviews = []
        },
        htmlToText(html) {
            const temp = document.createElement('div')
            temp.innerHTML = String(html || '')
            return String(temp.innerText || temp.textContent || '').trim()
        },
        parseClipboardText(rawText) {
            const clipboardText = String(rawText || '').trim()
            if (!clipboardText) {
                return { title: '', description: '' }
            }

            const normalized = clipboardText.replace(/\r\n/g, '\n')
            const lines = normalized
                .split('\n')
                .map((line) => line.trim())
                .filter((line) => line.length > 0)

            let title = (lines[0] || normalized).trim().slice(0, 255)
            let description = ''
            if (lines.length > 1) {
                description = lines.slice(1).join('\n').trim()
            }

            return { title, description }
        },
        extractFirstUrl(text) {
            const match = String(text || '').match(/https?:\/\/[^\s]+/i)
            return match ? match[0] : ''
        },
        toFullText(text) {
            return String(text || '').trim()
        },
        defaultAttachmentTitle(fileName) {
            const normalized = String(fileName || '').trim()
            if (!normalized) return 'Datei'

            const dot = normalized.lastIndexOf('.')
            const base = dot > 0 ? normalized.slice(0, dot) : normalized
            return base.slice(0, 255) || 'Datei'
        },
        toPendingAttachments(value) {
            const input = Array.isArray(value) ? value : []
            const result = []

            for (let index = 0; index < input.length; index += 1) {
                const item = input[index]
                const tempUpload = String(item?.tempUpload || '').trim()
                if (tempUpload) {
                    const fileName = String(item?.fileName || '').trim() || `Datei ${index + 1}`
                    const rawTitle = String(item?.title || '').trim()
                    const source = String(item?.source || '').trim()
                    const key = String(item?.key || '') || `temp|${tempUpload}|${index}`

                    result.push({
                        tempUpload,
                        file: null,
                        fileName,
                        title: rawTitle || this.defaultAttachmentTitle(fileName),
                        source: source || 'filepond',
                        key,
                    })
                    continue
                }

                const file = item instanceof File ? item : item?.file
                if (!(file instanceof File)) continue

                const fileName = String(file.name || '').trim() || `Datei ${index + 1}`
                const rawTitle = item instanceof File ? '' : String(item?.title || '').trim()
                const source = item instanceof File ? '' : String(item?.source || '').trim()
                const key = String(item instanceof File ? '' : item?.key || '') || `${fileName}|${file.size}|${file.lastModified}|${index}`

                result.push({
                    tempUpload: '',
                    file,
                    fileName,
                    title: rawTitle || this.defaultAttachmentTitle(fileName),
                    source: source || 'manual',
                    key,
                })
            }

            return result
        },
        extractFilesFromPendingAttachments(value) {
            return this.toPendingAttachments(value)
                .filter((item) => item.file instanceof File)
                .map((item) => item.file)
        },
        mergeUniquePendingAttachments(existingAttachments, newFiles, source = 'manual') {
            const list = this.toPendingAttachments(existingAttachments)
            const getKey = (file) => `${file?.name || ''}|${file?.size || 0}|${file?.type || ''}|${file?.lastModified || 0}`
            const seen = new Set(list.map((item) => getKey(item.file)))

            for (const file of newFiles || []) {
                if (!(file instanceof File)) continue
                const key = getKey(file)
                if (!seen.has(key)) {
                    seen.add(key)
                    list.push({
                        file,
                        title: this.defaultAttachmentTitle(file.name),
                        source: String(source || 'manual').trim(),
                        key: `${key}|${seen.size}`,
                    })
                }
            }

            return list
        },
        addPendingAttachmentsFromPicker(files) {
            const incoming = Array.isArray(files) ? files : []
            if (!incoming.length) return

            this.pendingAttachments = this.mergeUniquePendingAttachments(this.pendingAttachments, incoming, 'picker')
            this.updateClipboardImagePreviews(this.pendingAttachments)
        },
        notifyUploadError(message) {
            const text = String(message || '').trim()
            const notification = useNotificationStore()
            notification.notify({
                message: text || 'Datei konnte nicht hochgeladen werden.',
                type: 'error',
                timeout: 3500,
            })
        },
        async removePendingTempUpload(uploadId) {
            const value = String(uploadId || '').trim()
            if (!value) return
            await this.materialCardStore.deleteTempUpload(value, false)
        },
        async cleanupPendingTempUploads(rows = null) {
            const list = this.toPendingAttachments(rows ?? this.pendingAttachments)
            const uploads = list
                .map((item) => String(item?.tempUpload || '').trim())
                .filter((value) => value !== '')

            for (const uploadId of uploads) {
                await this.materialCardStore.deleteTempUpload(uploadId, false)
            }
        },
        openTypeManager() {
            if (!this.canManageTypeValues) return
            this.typeManagerDialogOpen = true
        },
        toNullable(value) {
            const text = String(value ?? '').trim()
            return text === '' ? null : text
        },
        onManualPaste(event) {
            const data = event?.clipboardData
            if (!data) return

            const html = data.getData('text/html') || ''
            const text = data.getData('text/plain') || this.htmlToText(html)
            const files = []
            const hasTable = /<table[\s>]/i.test(html)

            if (data.items?.length) {
                for (const item of data.items) {
                    if (item.kind === 'file') {
                        const file = item.getAsFile()
                        if (file) files.push(file)
                    }
                }
            }

            const payload = this.buildClipboardPayload({ text, html, files, hasTable })
            this.applyClipboardPayload(payload, 'Manuelle Übernahme (Strg+V)')
            this.setClipboardStatus('success', 'Inhalt wurde eingefügt und erkannt. Jetzt auf „Speichern“ klicken oder erneut übernehmen.')
        },
        applyManualBuffer() {
            const raw = String(this.manualPasteBuffer || '').trim()
            if (!raw) {
                this.setClipboardStatus('warning', 'Das Textfeld ist leer.')
                return
            }

            const payload = this.buildClipboardPayload({
                text: raw,
                html: '',
                files: [],
                hasTable: false,
            })
            this.applyClipboardPayload(payload, 'Manuelle Übernahme (Button)')
        },
        async cancelCreateForm() {
            await this.cleanupPendingTempUploads()
            this.$emit('menu-lock-change', false)
            this.resetCreateForm()
            this.createFormOpen = false
        },
        resetCreateForm() {
            this.createClassificationEditorVisible = false
            this.pendingAttachments = []
            this.clipboardModeActive = false
            this.clipboardSummary = null
            this.revokeClipboardImagePreviews()
            this.clipboardStatus = {
                type: 'info',
                message: 'Bereit: Klicke auf „Zwischenablage übernehmen“. Falls nötig, Strg+V im Feld darunter.',
            }
            this.manualPasteBuffer = ''
            this.createForm = {
                title: '',
                description: '',
                type: '',
                status: this.defaultStatusValue,
                classifications: [{ subject: '', topic: '', unit: '' }],
            }
        },
        normalizeClassifications(value) {
            const input = Array.isArray(value) ? value : []
            const result = []
            const seen = new Set()

            for (const row of input) {
                if (!row || typeof row !== 'object') continue
                const subject = String(row.subject ?? '').trim().slice(0, 255)
                const topic = String(row.topic ?? '').trim().slice(0, 255)
                let unit = String(row.unit ?? '').trim().slice(0, 255)
                if (!subject) continue
                if (!topic) {
                    unit = ''
                }
                const key = `${subject.toLocaleLowerCase()}|${topic.toLocaleLowerCase()}|${unit.toLocaleLowerCase()}`
                if (seen.has(key)) continue
                seen.add(key)
                result.push({ subject, topic, unit })
            }

            return result
        },
        async saveNewMaterial() {
            const title = String(this.createForm.title || '').trim()
            if (!title || this.isSaving) return

            this.isSaving = true
            const description = String(this.createForm.description || '').trim()
            const classifications = this.normalizeClassifications(this.createForm.classifications)

            const saved = await this.materialCardStore.quickStore({
                title,
                source_text: description || null,
                type: this.toNullable(this.createForm.type),
                status: String(this.createForm.status || '').trim() || this.defaultStatusValue,
                classifications,
            })

            const attachments = this.toPendingAttachments(this.pendingAttachments)
            if (saved?.id && attachments.length) {
                for (const attachment of attachments) {
                    if (attachment.tempUpload) {
                        await this.materialCardStore.addTempFileAttachment(
                            saved.id,
                            attachment.tempUpload,
                            this.toNullable(attachment.title) || attachment.fileName || ''
                        )
                        continue
                    }

                    if (attachment.file instanceof File) {
                        await this.materialCardStore.addFileAttachment(
                            saved.id,
                            attachment.file,
                            this.toNullable(attachment.title) || attachment.file.name || ''
                        )
                    }
                }
            }

            this.isSaving = false

            if (saved) {
                await this.cancelCreateForm()
            }
        },
    },
}
</script>

<style scoped>
.clipboard-full-text {
    white-space: pre-wrap;
    word-break: break-word;
}

.clipboard-image-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.material-form-width {
    max-width: 640px;
}
</style>
