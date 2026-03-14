<template>
    <v-col cols="12" md="7" lg="6" xl="5">
        <ItsGridBox
            variant="overview"
            color="primary"
            title="Meine ABAs"
            :subtitle="`Schuljahr ${currentSchoolyearLabel}`"
            icon="mdi-certificate-outline">
            <template #header-actions>
                <v-btn
                    icon="mdi-plus"
                    size="small"
                    variant="tonal"
                    color="primary"
                    title="Neue ABA erstellen"
                    :disabled="isSaving || isRefreshing"
                    @click="openCreateDialog" />
            </template>

            <v-card flat color="transparent" class="w-100">
                <v-card-text class="text-body-1 d-flex flex-column ga-3">
                    <div class="d-flex align-center justify-space-between ga-2 flex-wrap">
                        <div class="text-subtitle-1 font-weight-bold">Schuljahr {{ currentSchoolyearLabel }}</div>
                        <v-chip size="x-small" color="primary" variant="tonal">
                            {{ abas.length }} ABA{{ abas.length === 1 ? '' : 's' }}
                        </v-chip>
                    </div>

                    <v-progress-linear v-if="isLoading && !isRefreshing" indeterminate color="primary" rounded />

                    <v-alert v-if="loadError" type="warning" variant="tonal" rounded="lg">
                        {{ loadError }}
                    </v-alert>

                    <v-list v-else class="aba-list" lines="two">
                        <v-list-item v-for="aba in abas" :key="aba.id" class="aba-list-item">
                            <template #title>
                                <v-list-item-title class="aba-title-row aba-title-text">
                                    <span>{{ aba.title }}</span>
                                    <v-btn
                                        icon="mdi-pencil"
                                        size="x-small"
                                        variant="tonal"
                                        color="primary"
                                        :disabled="isRefreshing"
                                        @click="openEditDialog(aba)" />
                                </v-list-item-title>
                            </template>
                            <template #subtitle>
                                <v-list-item-subtitle class="aba-info-text mt-1">
                                    {{ aba.student_name }} · Erstellt: {{ formatDate(aba.created_at) }} · Ausgewertet: {{ formatDate(aba.evaluated_on) }}
                                </v-list-item-subtitle>
                                <v-list-item-subtitle class="aba-meta-text mt-1">
                                    Hauptdokument:
                                    <span class="aba-main-doc-name">{{ mainDocumentName(aba) }}</span>
                                </v-list-item-subtitle>
                                <v-list-item-subtitle
                                    v-if="additionalDocumentNames(aba).length > 0"
                                    class="aba-meta-text mt-1 d-flex flex-wrap ga-1">
                                    <span class="mr-2">Weitere Dokumente:</span>
                                    <v-chip
                                        v-for="(documentName, index) in additionalDocumentNames(aba)"
                                        :key="`${aba.id}-additional-${index}-${documentName}`"
                                        size="small"
                                        color="primary"
                                        variant="flat"
                                        class="aba-doc-chip">
                                        {{ documentName }}
                                    </v-chip>
                                </v-list-item-subtitle>
                                <v-list-item-subtitle class="aba-meta-text mt-1 d-flex align-center ga-2 flex-wrap">
                                    <span>EXTRAKTION:</span>
                                    <span>{{ analysisStatusLine(aba) }}</span>
                                    <span v-if="analysisStatusMessage(aba) && ['failed', 'aborted'].includes(analysisStatusFor(aba))">
                                        · {{ analysisStatusMessage(aba) }}
                                    </span>
                                </v-list-item-subtitle>
                            </template>
                            <template #default>
                                <div class="mt-2 mb-1 d-flex align-center ga-2 flex-wrap">
                                    <v-btn size="small" variant="flat" color="primary" prepend-icon="mdi-file-multiple-outline" :disabled="isRefreshing" @click="openFilesDialog(aba)">
                                        Dateien
                                    </v-btn>
                                    <v-btn
                                        v-if="mainAttachmentFor(aba)"
                                        size="small"
                                        :variant="isAnalysisRunning(aba) ? 'flat' : 'outlined'"
                                        color="primary"
                                        prepend-icon="mdi-brain"
                                        :loading="startingAnalysisAbaId === aba.id || isAnalysisRunning(aba)"
                                        :disabled="startingAnalysisAbaId === aba.id || isAnalysisRunning(aba) || isRefreshing"
                                        @click="requestAnalysis(aba)">
                                        {{ isAnalysisRunning(aba) ? 'Läuft...' : 'Analyse' }}
                                    </v-btn>
                                    <v-btn
                                        v-if="latestAnalysisRunFor(aba)"
                                        size="small"
                                        variant="tonal"
                                        color="info"
                                        prepend-icon="mdi-poll"
                                        :disabled="isRefreshing"
                                        @click="openResults(aba)">
                                        Ergebnisse
                                    </v-btn>
                                </div>
                            </template>
                        </v-list-item>
                        <v-list-item v-if="!isLoading && abas.length === 0">
                            <v-list-item-title class="aba-empty-text py-2">
                                Keine ABAs im aktuellen Schuljahr vorhanden.
                            </v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-card-text>
            </v-card>
        </ItsGridBox>

        <v-dialog v-model="formDialogOpen" persistent max-width="700">
            <v-card>
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                    <v-icon size="18">{{ formMode === 'create' ? 'mdi-plus-circle-outline' : 'mdi-pencil-outline' }}</v-icon>
                    {{ formMode === 'create' ? 'Neue ABA erstellen' : 'ABA bearbeiten' }}
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-form ref="abaForm" v-model="isFormValid">
                        <v-text-field
                            v-model="form.title"
                            label="Titel"
                            variant="outlined"
                            density="comfortable"
                            :rules="[required(), maxLength(255)]"
                            :disabled="isSaving" />

                        <v-text-field
                            v-model="form.student_name"
                            label="Schülerin"
                            variant="outlined"
                            density="comfortable"
                            :rules="[required(), maxLength(255)]"
                            :disabled="isSaving" />

                        <v-text-field
                            v-model="form.student_class"
                            label="Klasse"
                            variant="outlined"
                            density="comfortable"
                            :rules="[maxLength(100)]"
                            :disabled="isSaving" />

                        <v-select
                            v-model="form.schoolyear_id"
                            :items="schoolyearOptions"
                            item-title="name"
                            item-value="id"
                            label="Schuljahr"
                            variant="outlined"
                            density="comfortable"
                            :rules="[required()]"
                            :loading="isSchoolyearsLoading"
                            :disabled="isSaving || isSchoolyearsLoading" />
                    </v-form>
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn variant="tonal" color="warning" :disabled="isSaving" @click="closeFormDialog">
                        Abbrechen
                    </v-btn>
                    <v-spacer />
                    <v-btn
                        variant="flat"
                        color="primary"
                        :loading="isSaving"
                        :disabled="isSaving"
                        @click="saveAba">
                        {{ formMode === 'create' ? 'Erstellen' : 'Speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="uploadDialogOpen" persistent max-width="760">
            <v-card>
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                    <v-icon size="18">mdi-upload-outline</v-icon>
                    Dokumente hochladen
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="text-body-2 mb-1">
                        ABA: <strong>{{ selectedUploadAba?.title || '-' }}</strong>
                    </div>
                    <div class="text-caption text-medium-emphasis mb-3">
                        Hauptdokument: {{ selectedUploadMainDocumentName }} · Weitere Dokumente: {{ selectedUploadAdditionalCount }}
                    </div>

                    <div class="text-subtitle-2 font-weight-medium mb-2">Dokumenttyp</div>
                    <v-btn-toggle
                        v-model="uploadDocumentKind"
                        mandatory
                        divided
                        color="primary"
                        class="upload-kind-toggle mb-3"
                        :disabled="isUploadSaving">
                        <v-btn :value="documentKindMain" prepend-icon="mdi-file-document-outline" variant="outlined">
                            Hauptdokument
                        </v-btn>
                        <v-btn :value="documentKindAdditional" prepend-icon="mdi-file-plus-outline" variant="outlined">
                            Weiteres Dokument
                        </v-btn>
                    </v-btn-toggle>

                    <file-pond
                        v-if="csrfToken"
                        ref="abaUploadPond"
                        name="file"
                        :allow-multiple="uploadDocumentKind === documentKindAdditional"
                        :chunk-uploads="true"
                        :chunk-force="true"
                        :allow-revert="true"
                        :instant-upload="true"
                        :label-idle="'<strong>Datei hierher ziehen oder <i>klicken</i></strong>'"
                        :label-file-processing-complete="'Upload bereit'"
                        :server="pondServerConfig"
                        @processfilestart="onUploadProcessFileStart"
                        @processfile="onUploadProcessFile"
                        @processfileerror="onUploadProcessFileError"
                        @error="onUploadProcessFileError" />

                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn variant="tonal" color="warning" :disabled="isUploadDialogLocked" @click="closeUploadDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="filesDialogOpen" persistent max-width="760">
            <v-card>
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                    <v-icon size="18">mdi-file-multiple-outline</v-icon>
                    Dateien
                    <v-spacer />
                    <v-btn
                        size="small"
                        variant="flat"
                        color="primary"
                        prepend-icon="mdi-upload-outline"
                        :disabled="!selectedFilesAba || isDeletingAttachment || isUploadDialogLocked"
                        @click="openUploadDialog(selectedFilesAba)">
                        Upload
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="text-body-2 mb-3">
                        ABA: <strong>{{ selectedFilesAba?.title || '-' }}</strong>
                    </div>

                    <v-list class="files-list" lines="three">
                        <v-list-item v-for="attachment in selectedFilesAttachments" :key="attachment.id" class="files-list-item">
                            <template #title>
                                <v-list-item-title class="files-name-text">
                                    {{ attachment.original_name || 'Unbenanntes Dokument' }}
                                </v-list-item-title>
                            </template>
                            <template #subtitle>
                                <v-list-item-subtitle class="files-kind-text">
                                    {{ attachment.document_kind === documentKindMain ? 'Hauptdokument' : 'Weiteres Dokument' }}
                                </v-list-item-subtitle>
                                <v-list-item-subtitle class="files-size-text">
                                    {{ formatFileSize(attachment.size_bytes) }}
                                </v-list-item-subtitle>
                            </template>
                            <template #append>
                                <v-btn
                                    icon="mdi-delete-outline"
                                    size="small"
                                    color="error"
                                    variant="tonal"
                                    :disabled="isDeletingAttachment"
                                    @click="requestAttachmentDelete(attachment)" />
                            </template>
                        </v-list-item>
                        <v-list-item v-if="selectedFilesAttachments.length === 0">
                            <v-list-item-title class="files-size-text py-2">
                                Keine Dateien vorhanden.
                            </v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn variant="tonal" color="warning" :disabled="isDeletingAttachment" @click="closeFilesDialog">
                        Schließen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteAttachmentDialogOpen" persistent max-width="520">
            <v-card>
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                    <v-icon size="18" color="error">mdi-alert-outline</v-icon>
                    Datei löschen?
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="text-body-2 mb-2">
                        Diese Datei wird dauerhaft gelöscht:
                    </div>
                    <div class="text-body-2 font-weight-bold">
                        {{ selectedDeleteAttachment?.original_name || '-' }}
                    </div>
                    <div class="text-caption text-medium-emphasis mt-1">
                        {{ selectedDeleteAttachment ? formatFileSize(selectedDeleteAttachment.size_bytes) : '-' }}
                    </div>
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn
                        variant="tonal"
                        color="warning"
                        :disabled="isDeletingAttachment"
                        @click="cancelAttachmentDelete">
                        Abbrechen
                    </v-btn>
                    <v-spacer />
                    <v-btn
                        variant="flat"
                        color="error"
                        :loading="isDeletingAttachment"
                        :disabled="isDeletingAttachment || !selectedDeleteAttachment"
                        @click="confirmAttachmentDelete">
                        Löschen bestätigen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="analysisConfirmDialogOpen" persistent max-width="480">
            <v-card>
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                    <v-icon size="18" color="primary">mdi-brain</v-icon>
                    Analyse wirklich starten?
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="text-body-2 mb-2">
                        Für diese ABA wird eine neue Analyse gestartet:
                    </div>
                    <div class="text-body-2 font-weight-bold">
                        {{ pendingAnalysisAba?.title || '-' }}
                    </div>
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn variant="tonal" color="warning" @click="cancelAnalysis">
                        Abbrechen
                    </v-btn>
                    <v-spacer />
                    <v-btn variant="flat" color="primary" @click="confirmAnalysis">
                        Analyse starten
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useValidationRulesSetup } from '@/helpers/rules'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'

const FilePond = vueFilePond(FilePondPluginFileValidateType)

export default {
    components: { ItsGridBox, FilePond },

    props: {
        isRefreshing: {
            type: Boolean,
            default: false,
        },
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        await Promise.all([this.initializeCsrfToken(), this.loadSchoolyears(), this.loadAbas()])
    },

    mounted() {
        this.startStatusPolling()
    },

    unmounted() {
        this.stopStatusPolling()
    },

    data() {
        const rules = useValidationRulesSetup()

        return {
            documentKindMain: 'main_document',
            documentKindAdditional: 'additional_document',
            adminStore: null,
            abas: [],
            isLoading: false,
            isSaving: false,
            isUploadSaving: false,
            uploadProcessCounter: 0,
            isDeletingAttachment: false,
            startingAnalysisAbaId: null,
            statusPollTimer: null,
            statusPollInFlight: false,
            isSchoolyearsLoading: false,
            loadError: '',
            csrfToken: null,
            formDialogOpen: false,
            formMode: 'create',
            editingAbaId: null,
            isFormValid: false,
            uploadDialogOpen: false,
            uploadDocumentKind: 'additional_document',
            selectedUploadAba: null,
            filesDialogOpen: false,
            selectedFilesAba: null,
            deleteAttachmentDialogOpen: false,
            pendingDeleteAttachmentId: null,
            analysisConfirmDialogOpen: false,
            pendingAnalysisAba: null,
            schoolyearOptions: [],
            required: rules.required,
            maxLength: rules.maxLength,
            form: {
                title: '',
                student_name: '',
                student_class: '',
                schoolyear_id: null,
            },
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        currentSchoolyearId() {
            return this.config?.selected_schoolyear?.id || null
        },
        currentSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || 'Kein Schuljahr'
        },
        selectedUploadMainDocumentName() {
            const mainAttachment = this.mainAttachmentFor(this.selectedUploadAba)
            return mainAttachment?.original_name || 'nicht vorhanden'
        },
        selectedUploadAdditionalCount() {
            return this.additionalDocumentsCount(this.selectedUploadAba)
        },
        selectedFilesAttachments() {
            const entries = this.attachmentsFor(this.selectedFilesAba)
            return [...entries].sort((left, right) => {
                const leftMain = left?.document_kind === this.documentKindMain ? 0 : 1
                const rightMain = right?.document_kind === this.documentKindMain ? 0 : 1
                if (leftMain !== rightMain) {
                    return leftMain - rightMain
                }

                return Number(right?.id || 0) - Number(left?.id || 0)
            })
        },
        selectedDeleteAttachment() {
            if (!this.pendingDeleteAttachmentId) {
                return null
            }

            return this.selectedFilesAttachments.find(
                (attachment) => Number(attachment.id) === Number(this.pendingDeleteAttachmentId)
            ) || null
        },
        isUploadDialogLocked() {
            return this.isUploadSaving || this.uploadProcessCounter > 0
        },
        pondServerConfig() {
            return {
                process: {
                    url: '/api/admin/aba/uploads/chunk',
                    method: 'POST',
                    timeout: 120000,
                    withCredentials: true,
                    headers: this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {},
                },
                patch: {
                    url: '/api/admin/aba/uploads/chunk?patch=',
                    method: 'PATCH',
                    timeout: 120000,
                    withCredentials: true,
                    headers: this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {},
                },
                revert: (uniqueFileId, load, error) => {
                    const uploadId = String(uniqueFileId || '').trim()
                    if (!uploadId) {
                        load()
                        return
                    }

                    axios
                        .delete(`/api/admin/aba/uploads/chunk/${encodeURIComponent(uploadId)}`)
                        .then(() => load())
                        .catch(() => error('Cleanup fehlgeschlagen'))
                },
                restore: null,
                load: null,
                fetch: null,
            }
        },
    },

    watch: {
        currentSchoolyearId() {
            this.loadAbas()
            if (!this.formDialogOpen) {
                this.form.schoolyear_id = this.currentSchoolyearId
            }
        },
    },

    methods: {
        async initializeCsrfToken() {
            const metaToken = document?.head?.querySelector?.('meta[name="csrf-token"]')?.content
            this.csrfToken = String(metaToken || '').trim() || null

            try {
                const response = await axios.get('/api/admin/token')
                const token = String(response?.data || '').trim()
                if (token) {
                    this.csrfToken = token
                }
            } catch {
                // Fallback auf vorhandenen Meta-Token
            }
        },
        formatDate(dateValue) {
            if (!dateValue) {
                return '-'
            }
            const date = new Date(dateValue)
            if (Number.isNaN(date.getTime())) {
                return dateValue
            }
            return date.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatTime(dateValue) {
            if (!dateValue) {
                return '--:--:--'
            }
            const date = new Date(dateValue)
            if (Number.isNaN(date.getTime())) {
                return '--:--:--'
            }
            return date.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
        },
        formatDuration(totalSeconds) {
            const seconds = Number(totalSeconds)
            if (!Number.isFinite(seconds) || seconds < 0) {
                return null
            }

            const days = Math.floor(seconds / 86400)
            const hours = Math.floor((seconds % 86400) / 3600)
            const minutes = Math.floor((seconds % 3600) / 60)
            const secs = seconds % 60

            const parts = []
            if (days > 0) {
                parts.push(`${days}d`)
            }
            if (hours > 0 || days > 0) {
                parts.push(`${hours}h`)
            }
            parts.push(`${minutes}m`)
            parts.push(`${secs}s`)

            return parts.join(' ')
        },
        formatFileSize(sizeBytes) {
            const bytes = Number(sizeBytes || 0)
            if (!Number.isFinite(bytes) || bytes <= 0) {
                return '-'
            }

            const units = ['B', 'KB', 'MB', 'GB']
            let unitIndex = 0
            let value = bytes

            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024
                unitIndex += 1
            }

            const precision = value >= 10 || unitIndex === 0 ? 0 : 1
            return `${value.toFixed(precision)} ${units[unitIndex]}`
        },
        attachmentsFor(aba) {
            const items = aba?.attachments || []
            return Array.isArray(items) ? items : []
        },
        mainAttachmentFor(aba) {
            return this.attachmentsFor(aba).find((attachment) => attachment.document_kind === this.documentKindMain) || null
        },
        additionalDocumentsCount(aba) {
            return this.attachmentsFor(aba).filter((attachment) => attachment.document_kind === this.documentKindAdditional).length
        },
        additionalDocumentNames(aba) {
            return this.attachmentsFor(aba)
                .filter((attachment) => attachment.document_kind === this.documentKindAdditional)
                .map((attachment) => String(attachment.original_name || '').trim())
                .filter((name) => name.length > 0)
        },
        mainDocumentName(aba) {
            const mainAttachment = this.mainAttachmentFor(aba)
            return mainAttachment?.original_name || 'nicht vorhanden'
        },
        latestAnalysisRunFor(aba) {
            return aba?.latest_analysis_run || null
        },
        analysisStatusFor(aba) {
            return this.latestAnalysisRunFor(aba)?.status || null
        },
        analysisStatusLabel(aba) {
            const status = this.analysisStatusFor(aba)
            if (status === 'started') return 'gestartet'
            if (status === 'running') return 'läuft'
            if (status === 'completed') return 'abgeschlossen'
            if (status === 'aborted') return 'abgebrochen'
            if (status === 'failed') return 'Fehler'
            return 'nicht gestartet'
        },
        analysisStatusTimestamp(aba) {
            const run = this.latestAnalysisRunFor(aba)
            if (!run) {
                return null
            }

            return run.completed_at || run.failed_at || run.aborted_at || run.running_at || run.started_at || run.updated_at || null
        },
        analysisDuration(aba) {
            const run = this.latestAnalysisRunFor(aba)
            if (!run) {
                return null
            }

            const startRaw = run.started_at || run.running_at || run.created_at || null
            if (!startRaw) {
                return null
            }

            const start = new Date(startRaw)
            if (Number.isNaN(start.getTime())) {
                return null
            }

            const endRaw = run.completed_at || run.failed_at || run.aborted_at || null
            const end = endRaw ? new Date(endRaw) : null
            if (end && Number.isNaN(end.getTime())) {
                return null
            }

            const durationSeconds = Math.max(0, Math.floor(((end ?? new Date()).getTime() - start.getTime()) / 1000))
            return this.formatDuration(durationSeconds)
        },
        analysisStatusLine(aba) {
            const timestamp = this.analysisStatusTimestamp(aba)
            const statusLabel = this.analysisStatusLabel(aba)
            if (!timestamp) {
                return statusLabel === 'nicht gestartet' ? 'nicht gestartet' : `Analyse ${statusLabel}`
            }

            const duration = this.analysisDuration(aba)
            const durationPart = duration ? ` (${duration})` : ''
            return `${this.formatDate(timestamp)} · ${this.formatTime(timestamp)} · Analyse ${statusLabel}${durationPart}`
        },
        analysisStatusMessage(aba) {
            const run = this.latestAnalysisRunFor(aba)
            return run?.status_message || run?.error_message || null
        },
        isAnalysisRunning(aba) {
            const status = this.analysisStatusFor(aba)
            return status === 'started' || status === 'running'
        },
        hasPendingAnalysis() {
            return this.abas.some((aba) => {
                const status = this.analysisStatusFor(aba)
                return status === 'started' || status === 'running'
            })
        },
        startStatusPolling() {
            this.stopStatusPolling()
            this.statusPollTimer = window.setInterval(() => {
                this.pollAnalysisStatus()
            }, 4000)
        },
        stopStatusPolling() {
            if (this.statusPollTimer) {
                clearInterval(this.statusPollTimer)
                this.statusPollTimer = null
            }
        },
        async pollAnalysisStatus() {
            if (this.statusPollInFlight) {
                return
            }

            if (!this.hasPendingAnalysis()) {
                return
            }

            this.statusPollInFlight = true
            try {
                await this.loadAbas(true)
            } finally {
                this.statusPollInFlight = false
            }
        },
        async loadSchoolyears() {
            this.isSchoolyearsLoading = true
            try {
                const response = await axios.get('/api/admin/aba/schoolyears')
                const items = Array.isArray(response.data) ? response.data : []
                this.schoolyearOptions = items
            } catch (_) {
                this.schoolyearOptions = []
            } finally {
                this.isSchoolyearsLoading = false
            }
        },
        async loadAbas(silent = false) {
            if (!silent) {
                this.isLoading = true
                this.loadError = ''
            }
            try {
                const response = await axios.get('/api/admin/abas')
                const items = response?.data?.data || []
                this.abas = Array.isArray(items) ? items : []
            } catch (error) {
                if (!silent) {
                    this.abas = []
                }
                if (!silent) {
                    if (error.response?.status === 500) {
                        this.loadError = 'ABA-Liste konnte nicht geladen werden. Falls die Tabelle fehlt: bitte Migration ausführen.'
                    } else {
                        this.loadError = error.response?.data?.message || 'ABA-Liste konnte nicht geladen werden.'
                    }
                }
            } finally {
                if (!silent) {
                    this.isLoading = false
                }
            }
        },
        resetForm() {
            this.form = {
                title: '',
                student_name: '',
                student_class: '',
                schoolyear_id: this.currentSchoolyearId,
            }
            this.editingAbaId = null
        },
        async openCreateDialog() {
            await this.loadSchoolyears()
            this.formMode = 'create'
            this.resetForm()
            this.formDialogOpen = true
        },
        async openEditDialog(aba) {
            await this.loadSchoolyears()
            this.formMode = 'edit'
            this.editingAbaId = aba.id
            this.form = {
                title: aba.title || '',
                student_name: aba.student_name || '',
                student_class: aba.student_class || '',
                schoolyear_id: aba.schoolyear_id || this.currentSchoolyearId,
            }
            this.formDialogOpen = true
        },
        closeFormDialog() {
            this.formDialogOpen = false
            this.resetForm()
            this.$nextTick(() => this.$refs.abaForm?.resetValidation?.())
        },
        async saveAba() {
            const formRef = this.$refs.abaForm
            if (formRef?.validate) {
                const validation = await formRef.validate()
                if (!validation?.valid) {
                    return
                }
            }

            if (!this.form.title || !this.form.student_name || !this.form.schoolyear_id) {
                return
            }

            const payload = {
                title: this.form.title,
                student_name: this.form.student_name,
                schoolyear_id: this.form.schoolyear_id,
            }

            this.isSaving = true
            try {
                if (this.formMode === 'create') {
                    await axios.post('/api/admin/abas', { data: payload })
                } else {
                    await axios.put(`/api/admin/abas/${this.editingAbaId}`, { data: payload })
                }

                this.formDialogOpen = false
                await this.loadAbas()

                useNotificationStore().notify({
                    message: this.formMode === 'create' ? 'ABA wurde erstellt.' : 'ABA wurde aktualisiert.',
                    type: 'success',
                    timeout: 3000,
                })
                this.resetForm()
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'ABA konnte nicht gespeichert werden.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
            } finally {
                this.isSaving = false
            }
        },
        requestAnalysis(aba) {
            this.pendingAnalysisAba = aba
            this.analysisConfirmDialogOpen = true
        },
        cancelAnalysis() {
            this.analysisConfirmDialogOpen = false
            this.pendingAnalysisAba = null
        },
        async confirmAnalysis() {
            const aba = this.pendingAnalysisAba
            this.analysisConfirmDialogOpen = false
            this.pendingAnalysisAba = null
            await this.startAnalysis(aba)
        },
        async startAnalysis(aba) {
            const abaId = Number(aba?.id || 0)
            if (!abaId) {
                return
            }

            this.startingAnalysisAbaId = abaId
            try {
                const response = await axios.post(`/api/admin/abas/${abaId}/analysis`)
                await this.loadAbas(true)

                useNotificationStore().notify({
                    message: response?.data?.message || 'Analyselauf wurde gestartet.',
                    type: response?.status === 422 ? 'warning' : 'success',
                    timeout: 3000,
                })
            } catch (error) {
                await this.loadAbas(true)
                const status = error.response?.status || 500
                useNotificationStore().notify({
                    status,
                    message: error.response?.data?.message || 'Analyselauf konnte nicht gestartet werden.',
                    type: status === 422 ? 'warning' : 'error',
                    timeout: this.config?.timeout,
                })
            } finally {
                this.startingAnalysisAbaId = null
            }
        },
        openResults(aba) {
            const abaId = Number(aba?.id || 0)
            if (!abaId) {
                return
            }

            this.$router.push(`/admin/aba/results/${abaId}`)
        },
        openUploadDialog(aba) {
            this.selectedUploadAba = aba
            this.uploadDocumentKind = this.mainAttachmentFor(aba) ? this.documentKindAdditional : this.documentKindMain
            this.uploadDialogOpen = true
            this.$nextTick(() => this.resetUploadPond())
        },
        closeUploadDialog() {
            this.resetUploadPond()
            this.uploadDialogOpen = false
            this.selectedUploadAba = null
        },
        openFilesDialog(aba) {
            this.selectedFilesAba = aba
            this.filesDialogOpen = true
        },
        closeFilesDialog() {
            this.filesDialogOpen = false
            this.selectedFilesAba = null
            this.cancelAttachmentDelete()
        },
        requestAttachmentDelete(attachment) {
            this.pendingDeleteAttachmentId = Number(attachment?.id || 0) || null
            this.deleteAttachmentDialogOpen = this.pendingDeleteAttachmentId !== null
        },
        cancelAttachmentDelete() {
            this.deleteAttachmentDialogOpen = false
            this.pendingDeleteAttachmentId = null
        },
        resetUploadPond() {
            const pond = this.$refs.abaUploadPond
            if (pond && typeof pond.removeFiles === 'function') {
                pond.removeFiles()
            }
            this.uploadProcessCounter = 0
        },
        onUploadProcessFileStart() {
            this.uploadProcessCounter += 1
        },
        markUploadProcessFinished() {
            if (this.uploadProcessCounter > 0) {
                this.uploadProcessCounter -= 1
            }
        },
        async refreshSelectedUploadAba() {
            if (!this.selectedUploadAba?.id) {
                return
            }

            const updated = this.abas.find((item) => item.id === this.selectedUploadAba.id)
            if (updated) {
                this.selectedUploadAba = updated
            }
        },
        async refreshSelectedFilesAba() {
            if (!this.selectedFilesAba?.id) {
                return
            }

            const updated = this.abas.find((item) => item.id === this.selectedFilesAba.id)
            if (updated) {
                this.selectedFilesAba = updated
            }
        },
        async confirmAttachmentDelete() {
            const attachment = this.selectedDeleteAttachment
            const abaId = this.selectedFilesAba?.id
            if (!attachment || !abaId) {
                return
            }

            this.isDeletingAttachment = true
            try {
                await axios.delete(`/api/admin/abas/${abaId}/attachments/${attachment.id}`)

                await this.loadAbas()
                await this.refreshSelectedFilesAba()
                await this.refreshSelectedUploadAba()

                useNotificationStore().notify({
                    message: 'Datei wurde gelöscht.',
                    type: 'success',
                    timeout: 3000,
                })
                this.cancelAttachmentDelete()
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status || 500,
                    message: error.response?.data?.message || 'Datei konnte nicht gelöscht werden.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
            } finally {
                this.isDeletingAttachment = false
            }
        },
        async onUploadProcessFile(error, fileItem) {
            if (error) {
                this.onUploadProcessFileError(error)
                return
            }

            const uploadId = String(fileItem?.serverId || '').trim()
            const abaId = this.selectedUploadAba?.id
            if (!uploadId || !abaId) {
                this.markUploadProcessFinished()
                return
            }

            this.isUploadSaving = true
            try {
                await axios.post(`/api/admin/abas/${abaId}/attachments/from-temp`, {
                    data: {
                        upload_id: uploadId,
                        document_kind: this.uploadDocumentKind,
                        original_name: String(fileItem?.filename || ''),
                    },
                })

                await this.loadAbas()
                await this.refreshSelectedUploadAba()
                await this.refreshSelectedFilesAba()

                useNotificationStore().notify({
                    message: this.uploadDocumentKind === this.documentKindMain
                        ? 'Hauptdokument erfolgreich hochgeladen.'
                        : 'Zusatzdokument erfolgreich hochgeladen.',
                    type: 'success',
                    timeout: 3000,
                })
            } catch (requestError) {
                useNotificationStore().notify({
                    status: requestError.response?.status || 500,
                    message: requestError.response?.data?.message || 'Dokument konnte nicht zugeordnet werden.',
                    type: 'error',
                    timeout: this.config?.timeout,
                })
            } finally {
                this.isUploadSaving = false
                this.markUploadProcessFinished()
            }
        },
        onUploadProcessFileError(error) {
            this.markUploadProcessFinished()
            useNotificationStore().notify({
                status: 422,
                message: typeof error === 'string' ? error : 'Upload fehlgeschlagen.',
                type: 'error',
                timeout: this.config?.timeout,
            })
        },
    },
}
</script>

<style scoped>
.aba-list {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.9);
}

.aba-list-item {
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}

.aba-list-item:last-child {
    border-bottom: 0;
}

.aba-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.aba-title-text {
    font-size: 1.08rem;
    font-weight: 600;
    line-height: 1.35;
}

.aba-info-text {
    font-size: 0.95rem;
    line-height: 1.45;
    color: #000 !important;
    opacity: 1 !important;
}

.aba-meta-text {
    font-size: 0.92rem;
    line-height: 1.45;
    color: #000 !important;
    opacity: 1 !important;
}

.aba-main-doc-name {
    display: inline-block;
    margin-left: 6px;
    font-size: 0.98rem;
    font-weight: 700;
    color: #000;
}

.aba-empty-text {
    color: rgba(var(--v-theme-on-surface), 0.72);
}

.aba-doc-chip {
    font-size: 0.82rem;
    font-weight: 600;
}

.upload-kind-toggle {
    width: 100%;
}

.upload-kind-toggle :deep(.v-btn) {
    flex: 1 1 0;
    font-weight: 600;
    text-transform: none;
}

.files-list {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 12px;
}

.files-list-item {
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}

.files-list-item:last-child {
    border-bottom: 0;
}

.files-name-text {
    font-size: 0.96rem;
    font-weight: 600;
    color: #000;
}

.files-kind-text {
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(15, 23, 42, 0.9);
}

.files-size-text {
    font-size: 0.84rem;
    color: rgba(15, 23, 42, 0.82);
}
</style>
